<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2010 osCommerce

  Released under the GNU General Public License
*/

require('includes/application_top.php');
require(DIR_WS_CLASSES . 'order.php');
require_once(DIR_FS_CATALOG.DIR_WS_CLASSES . 'payment.php');
if (!defined('DIR_FS_SEQURA')) {
	define('DIR_FS_SEQURA', DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/SeQura/');
}
$charset = strtolower(CHARSET);
define('ISUTF8', $charset == 'utf8' || $charset == 'utf-8');

include_once(DIR_FS_CATALOG . 'includes/compat/compatibility_functions.php');
require_once(DIR_FS_SEQURA . 'SequraHelper.php');

$builder = SequraHelper::getBuilder();
$builder->buildDeliveryReport();
$client = SequraHelper::getClient();
$client->sendDeliveryReport($builder->getDeliveryReport());
$status= $client->getStatus();
if ( $status == 204) {
	$builder->setOrdersAsShipped();
	die('ok');
} elseif ($status >= 200 && $status <= 299 || $status == 409) {
  http_response_code(599);
	$x = json_decode($client->result, true); // return array, not object
	die('ko');
}
require(DIR_WS_INCLUDES . 'application_bottom.php');

