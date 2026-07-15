<?php

// Tools
use util\tools as tools;
use util\date as date;

// Si nos mandan a instalar cambiamos el modulo por login para que forbidden no salte y podamos instalarlo
if (isset($_GET['action']) && in_array($_GET['action'], array('install', 'shopify_importer'))) {
	$_SERVER['PHP_SELF'] = 'login_admin.php';
	$_SERVER['SCRIPT_FILENAME'] = 'login_admin.php';
}

// Incluimos el application_top
require_once('includes/application_top.php');

// Mostrar errores
error_reporting(E_ALL ^ E_NOTICE);
// ini_set('display_errors', 1); // OFF 2026-07-14: no pisar display_errors=Off del admin (avisos impresos rompen AJAX/redirects)

// Variables
$sUrlPage = 'log_profesionals_removed.php';
$sPathModule = 'includes/modules/log_profesionals_removed';
$sPathTemplate = $sPathModule . '/template';
$sTitle = 'Log de cuestas profesionales que han sido convertidas en cuentas normales';
$sSubtitle = '';
$aButtons = array();
$sPostAction = array_key_exists('action', $_POST) ? tep_db_input($_POST['action']) : (array_key_exists('action', $_GET) ? tep_db_input($_GET['action']) : false);
$sGetPage = tep_db_prepare_input(isset($_GET['page']) ? $_GET['page'] : '');
$sGetOrderby = tep_db_prepare_input(isset($_GET['orderby']) ? $_GET['orderby'] : '');
$sGetSort = tep_db_prepare_input(isset($_GET['sort']) ? $_GET['sort'] : '');
$aOperators = array();



// Acciones
switch (true) {
	// Instalar módulo
	case ($sPostAction == 'install'):
		// Insertamos admin file
		tools::insertAdminFiles($sUrlPage, 1);

		tep_db_query("CREATE TABLE `customers_profesionals_removed_log` (
					  `log_id` int(11) NOT NULL AUTO_INCREMENT,
					  `customer_id` int(11) NOT NULL,
					  `date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE current_timestamp(),
					  `numero_pedidos` int(11) NOT NULL,
					  `total_values` decimal(15,4) DEFAULT NULL,
					  PRIMARY KEY (`log_id`)
					) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
					");
		// Reset cache
		tools::createCacheFile();
		// Mensajes
		$messageStack->addSession('success', 'El módulo <em>Log de profesionales cambiados</em> se ha instalado correctamente.', 'success');

		// Redireccionamos
		tep_redirect($sUrlPage);
		break;

	default:

		include($sPathModule . '/log.php');
		break;
}

// Pintamos
echo includeTemplate($sPathTemplate . '/base.php');
