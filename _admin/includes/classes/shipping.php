<?php
class shipping {
	public $modules;

	function __construct() {
		if (defined('MODULE_SHIPPING_INSTALLED') && tep_not_null(MODULE_SHIPPING_INSTALLED)) {
			$allmods = explode(';', (string) MODULE_SHIPPING_INSTALLED);
			$this->modules = [];

			foreach ($allmods as $file) {
				$class = substr($file, 0, strrpos($file, '.'));
				$this->modules[] = [
					'class' => $class,
					'file'  => $file,
				];
			}
		}
	}

	function shipping_select($parameters, $language, $selected = '', $currentlyAdded = []) {
		// Usamos ruta absoluta al frontend
		$catalogPath = realpath(__DIR__ . '/../../') . '/'; // apunta a /includes/

		$select_string = '<select ' . $parameters . '>';

		foreach ($this->modules as $mod) {
			$sClass = $mod['class'];
			$sFile  = $mod['file'];

			// Archivo principal del módulo
			$moduleFile = $catalogPath . 'modules/shipping/' . $sFile;
			if (file_exists($moduleFile)) {
				require_once($moduleFile);
			}

			// Archivo de idioma del frontend
			$langFile = $catalogPath . 'languages/' . $language . '/modules/shipping/' . $sFile;
			if (file_exists($langFile)) {
				require_once($langFile);
			} else {
				// fallback si el idioma no existe
				$fallbackLang = $catalogPath . 'languages/espanol/modules/shipping/' . $sFile;
				if (file_exists($fallbackLang)) {
					require_once($fallbackLang);
				}
			}

			// Instanciamos la clase del módulo
			if (class_exists($sClass)) {
				$shipMethod = new $sClass();
				$title = $shipMethod->title ?? ucfirst($sClass);
			} else {
				$title = ucfirst($sClass);
			}

			$select_string .= '<option value="' . $sClass . '"';
			if ($selected == $sClass) {
				$select_string .= ' SELECTED';
			} elseif (in_array($sClass, $currentlyAdded)) {
				$select_string .= ' disabled';
			}
			$select_string .= '>' . htmlspecialchars($title) . '</option>';
		}

		return $select_string . '</select>';
	}

	function shippingClassNameDictionary($language) {
		$dictionary = [];
		$catalogPath = realpath(__DIR__ . '/../../') . '/';

		foreach ($this->modules as $mod) {
			$sClass = $mod['class'];
			$sFile  = $mod['file'];

			$moduleFile = $catalogPath . 'modules/shipping/' . $sFile;
			if (file_exists($moduleFile)) {
				require_once($moduleFile);
			}

			$langFile = $catalogPath . 'languages/' . $language . '/modules/shipping/' . $sFile;
			if (file_exists($langFile)) {
				require_once($langFile);
			}

			if (class_exists($sClass)) {
				$shipMethod = new $sClass();
				$title = $shipMethod->title ?? ucfirst($sClass);
			} else {
				$title = ucfirst($sClass);
			}

			$dictionary[$sClass] = $title;
		}

		return $dictionary;
	}
}
