<?php
class navigationHistory {
	public $path, $snapshot;

	function __construct() {
		$this->reset();
	}

	function reset() {
		$this->path = array();
		$this->snapshot = array();
	}

	function add_current_page() {
		global $PHP_SELF, $_GET, $_POST, $request_type, $cPath;

		// Saltarnos este metodo si viene desde paypal
		if (array_key_exists('HTTP_REFERER', $_SERVER) && preg_match('/paypal/i', $_SERVER['HTTP_REFERER'])) {
			return false;
		}

		// No guardamos peticiones ajax
		if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
			return false;
		}

		$set = 'true';
      for ($i=0, $n=count($this->path); $i<$n; $i++) {
			if ($this->path[$i]['page'] == basename($PHP_SELF)) {
				if (isset($cPath)) {
					if (!isset($this->path[$i]['get']['cPath'])) {
						continue;
					} else {
						if ($this->path[$i]['get']['cPath'] == $cPath) {
							array_splice($this->path, ($i + 1));
							$set = 'false';
							break;
						} else {
							$old_cPath = explode('_', $this->path[$i]['get']['cPath']);
							$new_cPath = explode('_', $cPath);

                for ($j=0, $n2=count($old_cPath); $j<$n2; $j++) {
								if ($old_cPath[$j] != $new_cPath[$j]) {
									array_splice($this->path, ($i));
									$set = 'true';
									break 2;
								}
							}
						}
					}
				} else {
					array_splice($this->path, ($i));
					$set = 'true';
					break;
				}
			}
		}

		if ($set == 'true') {
			$this->path[] = array(
				'page' => basename($PHP_SELF),
				'mode' => $request_type,
				'get' => $this->filter_parameters($_GET),
				'post' => $this->filter_parameters($_POST)
			);
		}
	}

	function remove_current_page() {
		global $PHP_SELF;

      $last_entry_position = count($this->path) - 1;
		if ($this->path[$last_entry_position]['page'] == basename($PHP_SELF)) {
			unset($this->path[$last_entry_position]);
		}
	}

	function set_snapshot($page = '') {
		global $PHP_SELF, $_GET, $_POST, $request_type;

		if (is_array($page)) {
			$this->snapshot = array(
				'page' => $page['page'],
				'mode' => $page['mode'],
				'get' => $this->filter_parameters($page['get']),
				'post' => $this->filter_parameters($page['post'])
			);
		} else {
			$this->snapshot = array(
				'page' => basename($PHP_SELF),
				'mode' => $request_type,
				'get' => $this->filter_parameters($_GET),
				'post' => $this->filter_parameters($_POST)
			);
		}
	}

	function clear_snapshot() {
		$this->snapshot = array();
	}

	function add_last_page() {
		// Variables
		global $navigationLast;
		$url = is_array($_SERVER) && isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

		if ($url != '' && !preg_match('/index\.php$/', $url)) { // Evitar registrar index.php como última página
			$navigationLast = $url;
			$_SESSION['navigationLast'] = $navigationLast; // Usamos $_SESSION directamente para simplicidad
		}
	}

	function get_last_page() {
		// Variables
		global $navigationLast;

		if (!isset($_SESSION['navigationLast']) || $_SESSION['navigationLast'] == '') {
			$_SESSION['navigationLast'] = 'index.php';
		}

		$navigationLast = $_SESSION['navigationLast'];
		return $navigationLast;
	}

	function set_path_as_snapshot($history = 0) {
		$pos = (count($this->path) - 1 - $history);
		if (isset($this->path[$pos])) {
			$this->snapshot = array(
				'page' => $this->path[$pos]['page'],
				'mode' => $this->path[$pos]['mode'],
				'get' => $this->path[$pos]['get'],
				'post' => $this->path[$pos]['post']
			);
		}
	}

	function debug() {
      for ($i=0, $n=count($this->path); $i<$n; $i++) {
			echo $this->path[$i]['page'] . '?';
			foreach ($this->path[$i]['get'] as $key => $value) {
				echo $key . '=' . $value . '&';
			}
        if (count($this->path[$i]['post']) > 0) {
				echo '<br />';
				foreach ($this->path[$i]['post'] as $key => $value) {
					echo '&nbsp;&nbsp;<strong>' . $key . '=' . $value . '</strong><br />';
				}
			}
			echo '<br />';
		}

      if (count($this->snapshot) > 0) {
			echo '<br /><br />';

			echo $this->snapshot['mode'] . ' ' . $this->snapshot['page'] . '?' . tep_array_to_string($this->snapshot['get'], array(tep_session_name())) . '<br />';
		}
	}

	function filter_parameters($parameters) {
		$clean = array();

		if (is_array($parameters)) {
			foreach ($parameters as $key => $value) {
				if (strpos($key, '_nh-dns') < 1) {
					$clean[$key] = $value;
				}
			}
		}

		return $clean;
	}

	function unserialize($broken) {
		foreach ($broken as $key => $value) {
			if (gettype($this->$key) != "user function") {
				$this->$key = $value;
			}
		}
	}
}
