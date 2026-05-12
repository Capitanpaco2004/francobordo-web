<?php
/*
  $Id: banktransfer.php,v 1.3 2002/05/31 19:02:02 thomasamoulton Exp $
  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com
  Copyright (c) 2002 osCommerce
  Released under the GNU General Public License
*/
  define('MODULE_PAYMENT_BANKTRANSFER_TEXT_TITLE', 'Bank Transfer Payment');
  define('MODULE_PAYMENT_BANKTRANSFER_TEXT_DESCRIPTION','An Email Has Been Sent With Details To Transfer Your Total Order Value:<br>
<br>
<br>
  We will not ship your order until we receive payment in the above account.');
  define('MODULE_PAYMENT_BANKTRANSFER_TEXT_EMAIL_FOOTER', "Please use the following details to transfer your total order value:\n\nAccount No.:  " . MODULE_PAYMENT_BANKTRANSFER_ACCNUM . "\nSort Code:    " . MODULE_PAYMENT_BANKTRANSFER_SORTCODE . "\nAccount Name: " . MODULE_PAYMENT_BANKTRANSFER_ACCNAM . "\nBank Name:    " . MODULE_PAYMENT_BANKTRANSFER_BANKNAM . "\nIBAN number:    " . MODULE_PAYMENT_BANKTRANSFER_IBAN . "\nSWIFT number:    " . MODULE_PAYMENT_BANKTRANSFER_SWIFT ."\n\nYour order will not ship until we receive payments in the above account.");
  define('MODULE_PAYMENT_BANKTRANSFER_SORT_ORDER', 'Sort order of display');
define('MODULE_PAYMENT_BANKTRANSFER_ORDER_STATUS_ID', 'Set the order status');
?>
