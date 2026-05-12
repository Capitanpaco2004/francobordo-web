<?php
// Tools
use util\tools as tools;
use util\date as date;

// Incluimos el application_top
require_once('includes/application_top.php');

// Mostrar errores
ini_set('display_errors', 1);
error_reporting(E_ERROR | E_WARNING | E_PARSE);

// Variables
$sUrlPage = 'configuration.php';
$sPathModule = 'includes/modules/configuration';
$sPathTemplate = $sPathModule . '/template';
$sTitle = CONFIGURATION_TITLE;
$sSubtitle = '';
$aButtons = [];
$sPostAction = array_key_exists('action', $_POST) ? tep_db_input($_POST['action']) : (array_key_exists('action', $_GET) ? tep_db_input($_GET['action']) : false);
$sGetPage = tep_db_prepare_input($_GET['page'] ?? '');
$sGetOrderby = tep_db_prepare_input($_GET['orderby'] ?? '');
$sGetSort = tep_db_prepare_input($_GET['sort'] ?? '');
$sGetgID = tep_db_prepare_input($_GET['gID'] ?? false);

// Si nos envian url antigua
if ($sGetgID !== false){
	tep_redirect(tep_href_link('configuration.php', 'action=options&id=' . $sGetgID));
}

# Messagestack estilo
$messageStack->style = 'solenopsis';

// Acciones
$actions = [
	'options' => "$sPathModule/options.php",
	'history' => "$sPathModule/history.php",
];

$actionKey = strtok($sPostAction, '_'); // e.g., "options_xyz" => "options"

$includeFile = $actions[$actionKey] ?? "$sPathModule/group.php";
include $includeFile;

// Pintamos
echo includeTemplate($sPathTemplate . '/base.php');
