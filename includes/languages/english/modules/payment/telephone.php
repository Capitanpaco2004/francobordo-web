<?php
/*
$Id: telephone.php,v 1.1 2003/04/30
osCommerce, Open Source E-Commerce Solutions
http://www.oscommerce.com
Copyright (c) 2002 osCommerce
Released under the GNU General Public License
Based on Ausbank.php ; adapted/translated in French by Gelong Shenphen
 */
define('MODULE_PAYMENT_TELEPHONE_TEXT_TITLE', 'Phone');
define('MODULE_PAYMENT_TELEPHONE_TEXT_DESCRIPTION', 'You choose the payment method : <b>' . MODULE_PAYMENT_TELEPHONE_TEXT_TITLE . '</b><br >
Our opening hours : <b>' . MODULE_PAYMENT_TELEPHONE_OUVERTURE_ES . '</b><br >
  Number phone : <b>' . MODULE_PAYMENT_TELEPHONE_NUM . '</b><br >
  More information : <b>' . MODULE_PAYMENT_TELEPHONE_PRECISION_ES . '</b>
  <br >Your order will not ship until we receive payment.');
define('MODULE_PAYMENT_TELEPHONE_TEXT_EMAIL_FOOTER', "Use the following information to contact us and finalize your order :\nYou can contact us " . MODULE_PAYMENT_TELEPHONE_OUVERTURE_ES . " following number : " . MODULE_PAYMENT_TELEPHONE_NUM . ".\nRemember : " . MODULE_PAYMENT_TELEPHONE_PRECISION_ES . "\n\nYour order will not ship until we receive payment.");
