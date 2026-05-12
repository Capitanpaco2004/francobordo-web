<?php
// Tools
use util\tools as tools;

if ($sPostAction === 'options') {
    // Variables
    $sGetId = array_key_exists( 'id', $_POST ) ? tep_db_input( $_POST['id'] ) : (array_key_exists( 'id', $_GET ) ? tep_db_input( $_GET['id'] ) : false);
    $aMessageError = [];
    $aButtons = [
			[ 'title' => TEXT_BACK, 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-arrow-left' ],
			[ 'title' => TEXT_SAVE, 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' ]
		];
    // Obtenemos
    $options = pharaonix_query( 'SELECT * FROM configuration WHERE configuration_group_id = "' . (int)$sGetId . '" order by sort_order', true );
    $groupConfig = pharaonix_queryOne( 'SELECT * FROM configuration_group WHERE configuration_group_id = "' . (int)$sGetId . '"' )->records;
    // Si no existe
    if( $options->num_rows == 0 )
		{
			$messageStack->addSession( 'success', CONFIGURATION_RECORD_NOT_EXISTS, 'error' );
			tep_redirect( tep_href_link(  $sUrlPage ) );
		}
    // Registros
    $options = $options->records;
    // Titulo
    $sSubtitle = ($sGetId != '' ? CONFIGURATION_TEXT_EDIT : CONFIGURATION_TEXT_ADD) . ' ' . CONFIGURATION_OPTIONS_FOR . ' "' . ($groupConfig['configuration_group_title'] ?? '') . '"';
    // Insertar o actualizar
    if( $_SERVER['REQUEST_METHOD'] === 'POST' )
		{
			// Variables
			$sqlUpdate = '';
			$config = [];
			$keys = [];
			$idConfigurationGroup = $_POST['id'];

			// Eliminamos
			unset($_POST['id']);

			// Obtenemos las configuraciones key => value
			foreach($options as $option){
				$option['configuration_value'] = addslashes((string) $option['configuration_value']);
				$config[$option['configuration_key']] = $option;
			}

			// Recorremos
			foreach ($_POST as $key => $value){
				// Si no hemos cambiado el valor
				if ($value == $config[$key]['configuration_value']) {
					continue;
				}

				// Limpiamos
				$value = tep_db_input(tep_db_prepare_input($value), 'db_link', true);
				$key = tep_db_prepare_input($key);
				$value = (is_array($value)) ? serialize($value) : $value;

				// Creamos SQL
				$sqlUpdate .= "WHEN '" . $key . "' THEN '" . $value . "' ";
				$keys[] = "'" . $key . "'";

				// Historial de cambiamos
				tep_db_perform('configuration_changes', [
					'change_date' => date('Y-m-d H:i:s'),
					'previous_setting' => tep_db_input($config[$key]['configuration_value']),
					'new_setting' => $value,
					'change_title' => tep_db_input($config[$key]['configuration_title']),
					'change_description' => tep_db_input($config[$key]['configuration_description']),
				]);
			}

			// Si tenemos cambios
			if (count($keys) > 0){
				tep_db_query('UPDATE configuration SET configuration_value = case configuration_key ' . $sqlUpdate . ' end, last_modified = now() WHERE configuration_key IN (' . implode(',', $keys) . ')');
			}

			// Mensaje
			$messageStack->addSession( 'success', CONFIGURATION_CONFIG_SUCCESS, 'success' );

			// Cache
			tools::createCacheFile();

			// Redireccionamos
			tep_redirect( $_SERVER['HTTP_REFERER'] );
		}
    // Template
    $sHtmlModule = includeTemplate( $sPathTemplate . '/options_crud.php' );
}
?>
