<?php

require('includes/application_top.php');

//error_reporting(E_ALL);
//ini_set('display_errors', '1');
require('includes/modules/rma/functions.php');

$sAction = false;
$sTitle = false;
rmaSection();
require(DIR_WS_INCLUDES . 'application_bottom.php');
