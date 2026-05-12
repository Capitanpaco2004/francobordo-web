<?php

class messageStack extends tableBox
{
	public $style = 'solenopsis';
	public array $messages = array();

	// class constructor
	public function __construct()
	{
		global $messageToStack;

		if (tep_session_is_registered('messageToStack')) {
			for ($i = 0, $n = count($messageToStack); $i < $n; $i++) {
				$this->add($messageToStack[$i]['id'], $messageToStack[$i]['text'], $messageToStack[$i]['type'], $messageToStack[$i]['nuevo']);
			}
			tep_session_unregister('messageToStack');
		}
	}


// class methods
	public function add($id, $message, $type = 'error', $nuevo = false)
	{
		// Si es nuevo la forma de mostrar mensajes insertamos los mensajes en grupo de errores en vez de crear una lista de arrays
		if ($nuevo && isset($this->messages[$id])) {
			$this->messages[$id]['text'] = $this->messages[$id]['text'] . '<br/>- ' . $message;
		} elseif ($nuevo) {
			if ($type == 'error') {
				$this->messages[$id] = array('class' => 'eror', 'params' => 'class="messageStackError"', 'id' => $id, 'text' => '- ' . $message);
			} elseif ($type == 'warning') {
				$this->messages[$id] = array('class' => 'wrng', 'params' => 'class="messageStackWarning"', 'id' => $id, 'text' => '- ' . $message);
			} elseif ($type == 'success') {
				$this->messages[$id] = array('class' => 'crrt', 'params' => 'class="messageStackSuccess"', 'id' => $id, 'text' => '- ' . $message);
			} else {
				$this->messages[$id] = array('class' => 'eror', 'params' => 'class="messageStackError"', 'id' => $id, 'text' => '- ' . $message);
			}
		} else {
			if ($type == 'error') {
				$this->messages[] = array('class' => 'eror', 'params' => 'class="messageStackError"', 'id' => $id, 'text' => '- ' . $message);
			} elseif ($type == 'warning') {
				$this->messages[] = array('class' => 'wrng', 'params' => 'class="messageStackWarning"', 'id' => $id, 'text' => '- ' . $message);
			} elseif ($type == 'success') {
				$this->messages[] = array('class' => 'crrt', 'params' => 'class="messageStackSuccess"', 'id' => $id, 'text' => '- ' . $message);
			} else {
				$this->messages[] = array('class' => 'eror', 'params' => 'class="messageStackError"', 'id' => $id, 'text' => '- ' . $message);
			}
		}
	}

	// Metodo provisional para insertar mensajes mediante session. No es el definitivo ya que oscommerce utiliza el metodo add_session para insertar mensajes en session
	// actualmente y no se puede remplazar hasta qu no estemos seguro de que todo el proyecto utilice este metodo
	public function addSession($id, $message, $type = 'error')
	{
		global $messageToStack;

		if (!tep_session_is_registered('messageToStack')) {
			tep_session_register('messageToStack');
			$messageToStack = array();
		}

		$messageToStack[] = array('id' => $id, 'text' => $message, 'type' => $type, 'nuevo' => true);
	}

	public function add_session($id, $message, $type = 'error')
	{
		global $messageToStack;

		if (!tep_session_is_registered('messageToStack')) {
			tep_session_register('messageToStack');
			$messageToStack = array();
		}

		$messageToStack[] = array('id' => $id, 'text' => $message, 'type' => $type, 'nuevo' => false);
	}

	public function reset()
	{
		$this->messages = array();
	}

	public function output($id)
	{
		$this->table_data_parameters = 'class="messageBox"';

		$output = array();
		for ($i = 0, $n = count($this->messages); $i < $n; $i++) {
			if ($this->messages[$i]['id'] == $id) {
				$output[] = $this->messages[$i];
			}
		}

		return $this->tableBox($output);
	}

	// Metodo provisional para mostrar mensajes con estilo. No es el definitivo ya que oscommerce utiliza el metodo ouput para mostrar mensajes
	// actualmente y no se puede remplazar hasta qu no estemos seguro de que todo el proyecto utilice este metodo
	public function show($id)
	{
		if (is_array($id)) {
			$this->messages['aux'] = array('class' => $id['class'], 'text' => $id['text']);

			$id = 'aux';
		}

		if ($this->check($id)) {
			switch ($this->style) {
				case 'default':
					return ('<div class="msje msje-' . $this->messages[$id]['class'] . '">
						<div class="msje-icon"></div>
						' . $this->messages[$id]['text'] . '
					</div>');
					break;

				case 'solenopsis':
					$aStyle = array(
						'crrt' => array('fa-check-circle', 'success'),
						'success' => array('fa-check-circle', 'success'),
						'info' => array('fa-info-circle', 'info'),
						'information' => array('fa-info-circle', 'info'),
						'wrng' => array('fa-exclamation-triangle', 'warning'),
						'warning' => array('fa-exclamation-triangle', 'warning'),
						'eror' => array('fa-exclamation-circle', 'error'),
						'error' => array('fa-exclamation-circle', 'error')
					);

					return '<div class="xmessage xmessage-' . $aStyle[$this->messages[$id]['class']][1] . '">
						<input type="checkbox"/><i class="fa fa-times"></i>
						<div>
							<i class="fa ' . $aStyle[$this->messages[$id]['class']][0] . '"></i>
							' . $this->messages[$id]['text'] . '
						</div>
					</div>';
					break;
			}
		}
	}

	// Metodo provisional para comprobar si existe el mensaje. No es el definitivo ya que oscommerce utiliza el metodo size para comprobar mensajes
	// actualmente y no se puede remplazar hasta qu no estemos seguro de que todo el proyecto utilice este metodo
	public function check($id)
	{
		if (isset($this->messages[$id]) && count($this->messages[$id]) > 0)
			return true;
		else
			return false;
	}

	public function size($id)
	{
		$count = 0;

		for ($i = 0, $n = count($this->messages); $i < $n; $i++) {
			if (isset($this->messages[$i]) && $this->messages[$i]['id'] == $id) {
				$count++;
			}
		}

		return $count;
	}
}

?>
