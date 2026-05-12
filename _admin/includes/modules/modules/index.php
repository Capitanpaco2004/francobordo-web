<?php

// Incluimos el application_top
require_once( 'includes/application_top.php' );
include 'includes/modules/modules/includes/functions/modules.php';
// Incluimos fichero de idioma
if (file_exists(dirname(__FILE__) . '/languages/' . $language  . '.php')) {
    include dirname(__FILE__) . '/languages/' . $language  . '.php';
} else {
    include dirname(__FILE__) . '/languages/espanol.php';
}

// Variables
$sUrlPage =  'modules.php';
$sPathModule = 'includes/modules/modules';
$sPathTemplate = $sPathModule . '/template';
$sTitle = HEADING_TITLE_INDEX;
$sSubtitle = '';
$aButtons = [];
$sPostAction = array_key_exists( 'action', $_POST ) ? tep_db_input( $_POST['action'] ) : (array_key_exists( 'action', $_GET ) ? tep_db_input( $_GET['action'] ) : false);
$sGetPage = tep_db_prepare_input( $_GET['page'] ?? '' );
$sGetOrderby = tep_db_prepare_input( $_GET['orderby'] ?? '' );
$sGetSort = tep_db_prepare_input( $_GET['sort'] ?? '' );

$sModuleType = array_key_exists( 'set', $_POST ) ? tep_db_input( $_POST['set'] ) : (array_key_exists( 'set', $_GET ) ? tep_db_input( $_GET['set'] ) : 'payment');

$sCheckoutModulePath = '../includes/modules/checkout';

# Messagestack estilo
$messageStack->style = 'solenopsis';

// Constantes
switch($sModuleType) {
	case 'shipping':
		$sTitle = HEADING_TITLE_SHIPPING;
		$sModuleDirectory = DIR_FS_CATALOG_MODULES . 'shipping/';
		$sModuleType = 'shipping';
		$sModuleKey = 'MODULE_SHIPPING_INSTALLED';
		$sTableHeading = HEADING_TABLE_SHIPPING;
		break;

	case 'ordertotal':
	case 'order_total':
		$sTitle = HEADING_TITLE_TOTALIZATIONS;
		$sModuleDirectory = DIR_FS_CATALOG_MODULES . 'order_total/';
		$sModuleType = 'order_total';
		$sModuleKey = 'MODULE_ORDER_TOTAL_INSTALLED';
		$sTableHeading = HEADING_TABLE_TOTALIZATIONS;
		break;

	case 'payment':
	default:
		$sTitle = HEADING_TITLE_PAYMENTS;
		$sModuleDirectory = DIR_FS_CATALOG_MODULES . 'payment/';
		$sModuleType = 'payment';
		$sModuleKey = 'MODULE_PAYMENT_INSTALLED';
		$sTableHeading = HEADING_TABLE_PAYMENTS;
		break;
}

// Acciones
switch( $sPostAction ) {
	case 'install':
		$sModule = array_key_exists( 'module', $_POST ) ? tep_db_input( $_POST['module'] ) : (array_key_exists( 'module', $_GET ) ? tep_db_input( $_GET['module'] ) : false);

		// Obtener la clase del módulo
		$cModule = getModuleById($sModuleDirectory, $sModuleType, $sModule, false);
		if(!isset($cModule)) {
			tep_redirect(tep_href_link(FILENAME_MODULES, 'set=' . $sModuleType));
			die();
		}

		$aModuleConfigurations = getModuleConfigurations($cModule);

		// Eliminamos primero si ya existe
		if ($cModule->check() > 0) {
			$cModule->remove();
		}

		if ($sModuleType === 'shipping') {
			$queryProducts = tep_db_query("SELECT products_id, shipping_methods FROM " . TABLE_PRODUCTS . " WHERE shipping_methods IS NOT NULL AND shipping_methods <> ''");
			while($record = tep_db_fetch_array($queryProducts)){
				tep_db_query("UPDATE " . TABLE_PRODUCTS . " SET shipping_methods = '" . $record['shipping_methods'] . ";" . basename($sModule) . "' WHERE products_id='" . $record['products_id']."'");
			}
		}

		tep_module_change($sPostAction, basename($sModule));
		$cModule->install();

		require ('includes/configuration_cache.php');

		$messageStack->addSession( 'success', TEXT_MESSAGE_INSTALL_SUCCESS, 'success' );
		tep_redirect(tep_href_link(FILENAME_MODULES, 'set=' . $sModuleType));
		break;

	case 'remove':
		$sModule = array_key_exists( 'module', $_POST ) ? tep_db_input( $_POST['module'] ) : (array_key_exists( 'module', $_GET ) ? tep_db_input( $_GET['module'] ) : false);

		// Obtener la clase del módulo
		$cModule = getModuleById($sModuleDirectory, $sModuleType, $sModule);
		if(!isset($cModule)) {
			tep_redirect(tep_href_link(FILENAME_MODULES, 'set=' . $sModuleType));
			die();
		}

		$aModuleConfigurations = getModuleConfigurations($cModule);

		tep_module_change($sPostAction, basename($sModule));
		$cModule->remove();

		require ('includes/configuration_cache.php');

		$messageStack->addSession( 'success', TEXT_MESSAGE_REMOVE_SUCCESS, 'success' );
		tep_redirect(tep_href_link(FILENAME_MODULES, 'set=' . $sModuleType));
		break;

	case 'edit':
		// 1. Determinar módulo
		$sModule = $_POST['module'] ?? $_GET['module'] ?? '';
		$sModule = tep_db_input($sModule);

		// 2. Instanciar sólo si existe e INSTALADO
		$cModule = getModuleById($sModuleDirectory, $sModuleType, $sModule);
		if ($cModule === null) {
			$messageStack->addSession(
				'error',
				'El módulo “' . htmlspecialchars($sModule) . '” no existe o no está instalado. Revise antes de editarlo',
				'warning'
			);
			tep_redirect(tep_href_link(FILENAME_MODULES, 'set=' . $sModuleType));
		}

		// 3. Ya sabemos que está instalado, carga botones y configuraciones
		$aButtons = [
			['title'=>TEXT_BACK, 'href'=>tep_href_link($sUrlPage, tep_get_all_get_params(['action','module'])), 'icon'=>'fa-arrow-left'],
			['title'=>TEXT_SAVE, 'icon'=>'fa-save','extra'=>'id="saveform"','anchor_class'=>'verde']
		];

		$aModuleConfigurations = getModuleConfigurations($cModule);

		// 4. Guardado POST
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			foreach ($_POST['configuration'] as $key => $value) {
				$dbValue = is_array($value)
					? json_encode($value, JSON_UNESCAPED_UNICODE)
					: $value;
				tep_db_perform(
					TABLE_CONFIGURATION,
					['configuration_value' => $dbValue],
					'update',
					'configuration_key = "' . tep_db_input($key) . '"'
				);
				tep_configuration_update($key, $value);
			}
			require('includes/configuration_cache.php');
			$messageStack->addSession('success', TEXT_MESSAGE_EDIT_SUCCESS, 'success');
			tep_redirect(tep_href_link(FILENAME_MODULES, 'set=' . $sModuleType));
		}

		// 5. Estilos y plantilla de edición
		$aStyle      = [$sPathModule . '/css/admin_modules.css'];
		$sHtmlModule = includeTemplate($sPathTemplate . '/edit.php');
		break;

	default:
		$aButtons = [
			[ 'title' => HEADING_TITLE_PAYMENTS_SHORT, 'href' => tep_href_link( $sUrlPage, 'set=payment' ), 'icon' => 'fa-credit-card' ],
			[ 'title' => HEADING_TITLE_SHIPPING_SHORT, 'href' => tep_href_link( $sUrlPage, 'set=shipping' ), 'icon' => 'fa-shipping-fast' ],
			[ 'title' => HEADING_TITLE_TOTALIZATIONS_SHORT, 'href' => tep_href_link( $sUrlPage, 'set=ordertotal' ), 'icon' => 'fa-coin' ],
		];

		//Cargar array con módulos instalados
		$aModules = getInstalledModules($sModuleDirectory, $sModuleType);
		$aModuleFiles = getInstalledModules($sModuleDirectory, $sModuleType, true, true);

		// Comprobación de sort orders duplicados
		if(checkDuplicatedModuleSortOrder($aModules)) {
			$messageStack->add(TEXT_MESSAGE_DUPLICATED_SORT_ORDERS);
		}

		// Actualizar constante de módulos instalados
		updateInstalledModulesConfiguration($aModuleFiles, $sModuleKey);

		// Modulo
		$sHtmlModule = includeTemplate( $sPathTemplate . '/index.php' );
		break;
}

// Pintamos
echo includeTemplate( $sPathTemplate . '/base.php' );
