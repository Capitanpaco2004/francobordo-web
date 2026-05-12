<?php

class payment {
	public $modules;

	// class constructor
	function __construct() {
		if (defined('MODULE_PAYMENT_INSTALLED') && tep_not_null(MODULE_PAYMENT_INSTALLED)) {
			$allmods = explode(';', (string)MODULE_PAYMENT_INSTALLED);

			$this->modules = [];

			for ($i = 0, $n = count($allmods); $i < $n; $i++) {
				$file                       = $allmods[$i];
				$class                      = substr($file, 0, strrpos($file, '.'));
				$this->modules[$i]['class'] = $class;
				$this->modules[$i]['file']  = $file;
			}
		}
	}

	function getPayMethod(array $selected = [], string $language = 'espanol') {
		if (!isset($selected)) {
			$selected = [];
		}

		$aPayMethods = ['selected' => [], 'noSelected' => []];
		for ($i = 0, $n = count($this->modules); $i < $n; $i++) {
			$sClass = $this->modules[$i]['class'];
			$sFile  = $this->modules[$i]['file'];
			$path   = getcwd() . '/../includes/modules/payment/' . $sFile;
			if (file_exists($path)) {
				require_once($path);
				$path = getcwd() . '/../includes/languages/' . $language . '/modules/payment/' . $sFile;
				if (file_exists($path)) {
					require_once($path);
				}
				$paymentMethod = new $sClass();
			}
			$title = $paymentMethod->title;
			if (!in_array($sFile, $selected)) {
				$aPayMethods['noSelected'][] = ['text' => $title ?? $sClass, 'id' => $sFile];
			} else {
				$aPayMethods['selected'][] = ['text' => $title ?? $sClass, 'id' => $sFile];
			}
		}

		return $aPayMethods;
	}

	function getClassNameDictionary($language) {
		$dictionary = [];
		for ($i = 0, $n = count($this->modules); $i < $n; $i++) {
			$name  = $this->modules[$i]['class'];
			$sFile = $this->modules[$i]['file'];

			$path = getcwd() . '/../includes/modules/payment/' . $sFile;
			if (file_exists($path)) {
				require_once($path);
				$path = getcwd() . '/../includes/languages/' . $language . '/modules/payment/' . $sFile;
				if (file_exists($path)) {
					require_once($path);
				}
				$paymentMethod      = new $name();
				$title              = $paymentMethod->title;
				$dictionary[$sFile] = $title ?? $name;
				$dictionary[$sFile] .= '<br>';
			}
		}
		return $dictionary;
	}
}
