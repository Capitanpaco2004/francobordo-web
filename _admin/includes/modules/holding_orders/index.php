<?php
require('includes/application_top.php');

require(DIR_WS_CLASSES . 'currencies.php');
$currencies = new currencies();

// Variables comunes
$sUrlPage    = 'holding_orders.php';
$sPathModule = 'includes/modules/holding_orders';
$sTitle      = 'Salvaguardados';
$sSubtitle   = 'Gestión de pre-pedidos no confirmados en pasarelas externas (TPV, Redsys, Paypal, etc...)';
$aButtons    = [];
$sPostAction = array_key_exists('action', $_POST) ? tep_db_input($_POST['action']) : (array_key_exists('action', $_GET) ? tep_db_input($_GET['action']) : false);
$sGetPage    = tep_db_prepare_input(isset($_GET['page']) ? $_GET['page'] : '');
$sGetOrderby = tep_db_prepare_input(isset($_GET['orderby']) ? $_GET['orderby'] : '');
$sGetSort    = tep_db_prepare_input(isset($_GET['sort']) ? $_GET['sort'] : '');
$ocID        = tep_db_prepare_input($_GET['ocID'] ?? $_POST['ocID'] ?? 0);

# Messagestack estilo
$messageStack->style = 'solenopsis';

// Cargar estados de pedidos
$orders_statuses     = [];
$orders_status_array = tep_get_orders_status();

$aJs = [$sPathModule . '/js/javascript.js'];

// Acciones
switch ($sPostAction) {

	case 'move':
		include($sPathModule . '/actions/move.php');
		break;

	case 'delete':
		include($sPathModule . '/actions/delete.php');
		break;

	case 'view_order':
		include($sPathModule . '/actions/view_order.php');

		$aStyle[] = $sPathModule . '/css/view_order.css';

		break;

	default:
		$aStyle = [$sPathModule . '/css/style.css', $sPathModule . '/css/tippy.min.css'];
		$aJs    = array_merge($aJs, ['https://unpkg.com/@popperjs/core@2', 'https://unpkg.com/tippy.js@6']);

		include($sPathModule . '/actions/default.php');
		break;
}


// Renderizar vista base
echo includeTemplate($sPathModule . '/templates/base.php');

require(DIR_WS_INCLUDES . 'application_bottom.php');
