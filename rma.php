<?php

require( 'includes/application_top.php' );
require($_SERVER['DOCUMENT_ROOT'] . '/' . DIR_WS_CLASSES . 'rma.php');

//error_reporting(E_ALL);
//ini_set('display_errors', '1');

$rma = new rma($_GET['orders_id'], $_GET['products_id']);

if (!isAjax()) {
	require(DIR_THEME. 'html/header.php');
	require(DIR_THEME. 'html/column_left.php');
}

$nStep = intval($_POST['step']);
if (!$rma->isValid()) {
	$nStep = 5;
}

if ($nStep == 2 && !$rma->showReembolso($_POST['option_return'])) {
	$nStep = 3;
}

$aFields = array();
switch ($nStep) {
	case 1:
		//Comprobamos que se ha enviado razón de devolución
		if (intval($_POST['option_return']) == 0) {
			$nStep = 0;
		} else {
			if ($rma->checkOptionReturn()) {
				$aFields = $_POST;
			} else {
				$nStep = 0;
			}
		}
	break;
	case 2:
		$aFields = $_POST;
	break;
	case 3:
		$aFields = $_POST;
		$rma->rmaProcess($aFields);
	break;
}
include( DIR_THEME_ROOT . 'html/templates/' . basename(__FILE__) );

if (!isAjax()) {
	require( DIR_THEME. 'html/column_right.php' );
	require( DIR_THEME. 'html/footer.php' );
}
	require( DIR_WS_INCLUDES . 'application_bottom.php' );
?>
