<?php

use util\tools;

function getFilesFromDirectory($sModuleDirectory)
{
	global $PHP_SELF;

	$file_extension = substr((string) $PHP_SELF, strrpos((string) $PHP_SELF, '.'));

	$directory_array = [];
	if ($dir = @dir($sModuleDirectory)) {
		while ($file = $dir->read()) {
			if (!is_dir($sModuleDirectory . $file) && substr($file, strrpos($file, '.')) === $file_extension) {
				$directory_array[] = $file;
			}
		}
		sort($directory_array);
		$dir->close();
	}

	return $directory_array;
}

function getInstalledModules($sModuleDirectory, $sModuleType, $bCheckStatus = false, $bReturnFiles = false)
{
	global $language;

	$modules = [];
	$files = getFilesFromDirectory($sModuleDirectory);

	foreach ($files as $file) {
		if (file_exists(DIR_FS_CATALOG_LANGUAGES . $language . '/modules/' . $sModuleType . '/' . $file)) {
			include_once(DIR_FS_CATALOG_LANGUAGES . $language . '/modules/' . $sModuleType . '/' . $file);
		}

		if (file_exists($sModuleDirectory . $file)) {
			require_once($sModuleDirectory . $file);
		}

		$class = substr((string) $file, 0, strrpos((string) $file, '.'));
		//Si una clase existe comprobamos si esta instalada mirando si sus keys estan definidas o no, en caso de que no esten definidas, las definimos para evitar fallo
		//En caso de que sea un shipping llamamos a su funcion que comprobara si las constantes estan definidas y si no lo estan las definira para evitar errores
		if (tep_class_exists($class)) {
			if (method_exists($class, "define_temp_keys")) {
                $reflectionMethod = new ReflectionMethod($class, "define_temp_keys");
                if($reflectionMethod->isStatic()) {
					$class::define_temp_keys();
				}
            } elseif (method_exists($class, "keys")) {
                $reflectionMethod = new ReflectionMethod($class, "keys");
                if($reflectionMethod->isStatic()) {
					foreach ($class::keys() as $key) {
						if (!defined($key)) {
							define($key, '');
						}
					}
				}
            }
			$module = new $class;

			if($bCheckStatus && $module->check() == 0) {
				continue;
			}

			if ($module->sort_order > 0 && !isset($modules[$module->sort_order])) {
				$modules[$module->sort_order] = $bReturnFiles ? $file : $module;
			} else {
				$modules[] = $bReturnFiles ? $file : $module;
			}
		}
	}

	ksort($modules);

	return $modules;
}

function updateInstalledModulesConfiguration($aInstalledModules, $sModuleKey)
{
	ksort($aInstalledModules);
	$check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = '" . $sModuleKey . "'");
	if (tep_db_num_rows($check_query)) {
		$check = tep_db_fetch_array($check_query);
		if ($check['configuration_value'] != implode(';', $aInstalledModules)) {
			tep_db_query("update " . TABLE_CONFIGURATION . " set configuration_value = '" . implode(';', $aInstalledModules) . "', last_modified = now() where configuration_key = '" . $sModuleKey . "'");
			require('includes/configuration_cache.php');
		}
	} else {
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Installed Modules', '" . $sModuleKey . "', '" . implode(';', $aInstalledModules) . "', 'This is automatically updated. No need to edit.', '6', '0', now())");
		require('includes/configuration_cache.php');
	}
}

function checkDuplicatedModuleSortOrder($aModules)
{
	$aSortOrders = array_column($aModules, 'sort_order');
	//Quitamos elementos vacios y NULL para que no cuenten como repetidos
	$aSortOrders = array_filter($aSortOrders, fn($valor) => !is_null($valor) && $valor !== "");
	return count($aSortOrders) !== count(array_unique($aSortOrders));
}

/**
 * Obtiene un objeto de módulo.
 *
 * @param string $sModuleDirectory Ruta absoluta al directorio de módulos.
 * @param string $sModuleType      Tipo de módulo ('payment', 'shipping', …).
 * @param string $sModuleId        Código del módulo (por ejemplo 'bizum').
 * @param bool   $bCheckStatus     true (default) → solo módulos ya instalados;
 *                                 false → cualquiera (necesario para la acción 'install',
 *                                 que naturalmente recibe módulos aún no instalados — sin esto
 *                                 el botón "Instalar módulo" no hace nada porque getInstalledModules
 *                                 filtra el modulo no-instalado y el caller hace tep_redirect).
 *
 * @return object|null             Instancia del módulo, o null si no se encuentra.
 */
function getModuleById($sModuleDirectory, $sModuleType, $sModuleId, $bCheckStatus = true)
{
	$aModules = getInstalledModules($sModuleDirectory, $sModuleType, $bCheckStatus, false);

	$cModule = null;
	foreach ($aModules as $module) {
		if ($module->code === $sModuleId) {
			$cModule = $module;
			break;
		}
	}

	return $cModule;
}


function getModuleConfigurations($cModule)
{
	$moduleKeys = $cModule->keys();
	$aConfigurations = [];

	foreach ($moduleKeys as $moduleKey) {
		$key_value_query = tep_db_query("select configuration_title, configuration_value, configuration_description, use_function, set_function from " . TABLE_CONFIGURATION . " where configuration_key = '" . $moduleKey . "'");
		$key_value = tep_db_fetch_array($key_value_query);

		$aConfigurations[$moduleKey]['title'] = $key_value['configuration_title'];
		$aConfigurations[$moduleKey]['value'] = $key_value['configuration_value'];
		$aConfigurations[$moduleKey]['description'] = $key_value['configuration_description'];
		$aConfigurations[$moduleKey]['use_function'] = $key_value['use_function'];
		$aConfigurations[$moduleKey]['set_function'] = $key_value['set_function'];
	}

	return $aConfigurations;
}

function getInputByConfiguration(array $aConfiguration, string $configurationKey): string
{
	$value = (string)($aConfiguration['value'] ?? '');

	// Caso 1: set_function
	if (!empty($aConfiguration['set_function'])) {
		$setFunction = $aConfiguration['set_function'];

		// Si es textarea → CKEditor
		if (stripos($setFunction, 'tep_cfg_textarea') !== false) {
			$decodedJson = json_decode($value, true);

			if (json_last_error() === JSON_ERROR_NONE && is_array($decodedJson)) {
				return tools::getInputLanguages("configuration[{$configurationKey}]", '', $decodedJson, '', '', 10, false, false);
			}

			return tools::getInputLanguages("configuration[{$configurationKey}]", '', $value, '', '', 10, false, false);
		}

		// Cualquier otro set_function
		eval('$configurationInput = ' . $setFunction . "'" . $value . "', '" . $configurationKey . "');");
		return preg_replace("/<br>/", "", $configurationInput, 1);
	}

	// Caso 2: JSON sin set_function → multilenguaje en input normal
	$decodedJson = json_decode($value, true);
	if (json_last_error() === JSON_ERROR_NONE && is_array($decodedJson)) {
		$configurationInput = tools::getInputLanguages("configuration[{$configurationKey}]", '', $decodedJson, '', '', 9, true, false);
		$configurationInput = preg_replace("/<label(.*?)<\/label>/", "", $configurationInput);
		return preg_replace("/<br>/", "", $configurationInput, 1);
	}

	// Caso 3: input normal
	$configurationInput = tep_draw_input_field("configuration[{$configurationKey}]", $value);
	return preg_replace("/<br>/", "", (string)$configurationInput, 1);
}
