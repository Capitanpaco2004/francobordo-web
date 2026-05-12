<?php
/*
  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Servired Payment Module 
  Copyright (c) 2004 qadram software
  http://www.qadram.com

  versión 4.01 - 27/09/2009
  A. Miguel Zúñiga - http://www.ibercomp.com

  I do apologise, but I'm not Sakespeare, I translate module text
   only to help internationalization. Before use this module
   in english, please correct it.

  Released under the GNU General Public License
*/

  define('MODULE_PAYMENT_SERVIRED_TEXT_TITLE', 'Credit Card (Servired)');
  define('MODULE_PAYMENT_SERVIRED_TRANSFER_TEXT_TITLE', 'Money Transfer (Servired)');
  define('MODULE_PAYMENT_SERVIRED_TEXT_DESCRIPTION', '<font color=red>Admin TPV: </font><a href="' . MODULE_PAYMENT_SERVIRED_ADMIN . '" target="_blank" onClick="window.open(this.href, this.target, ' . $medidas_popup . '); return false;"> aquí</a>');
  define('MODULE_PAYMENT_SERVIRED_TEXT_ERROR_MESSAGE','Process error');
  define('MODULE_PAYMENT_SERVIRED_TEXT_CANCEL','Process canceled');	

  define('MODULE_PAYMENT_SERVIRED_TEXT_UNKNOW_ERROR','Unknow error ');

  define('MODULE_PAYMENT_SERVIRED_ERROR_SIGN','Signs are not equal: Authentication error');

  define('MODULE_PAYMENT_SERVIRED_ERROR_101','Expired Card');
  define('MODULE_PAYMENT_SERVIRED_ERROR_102','Card locked by the issuing bank');  	
  define('MODULE_PAYMENT_SERVIRED_ERROR_107','Order to contact the card issuing bank');  	
  define('MODULE_PAYMENT_SERVIRED_ERROR_180','Card not supported by the system');  	
  define('MODULE_PAYMENT_SERVIRED_ERROR_184','Authentication of the cardholder failed');  	
  define('MODULE_PAYMENT_SERVIRED_ERROR_190','Denied by the card issuing bank for various reasons');  	
  define('MODULE_PAYMENT_SERVIRED_ERROR_201','Expired card. Order to remove the card');  	
  define('MODULE_PAYMENT_SERVIRED_ERROR_202','Blocked by the bank card issuer. Order to remove the card');  	
  define('MODULE_PAYMENT_SERVIRED_ERROR_290','Rejected for various reasons. Order to remove the card');
  define('MODULE_PAYMENT_SERVIRED_ERROR_909','System error');
  define('MODULE_PAYMENT_SERVIRED_ERROR_912','Unavailable resolver Center');
  define('MODULE_PAYMENT_SERVIRED_ERROR_913','Received duplicate message');
  define('MODULE_PAYMENT_SERVIRED_ERROR_949','Expiry date wrong card');
  define('MODULE_PAYMENT_SERVIRED_ERROR_9111','Bank card issuer does not respond');
  define('MODULE_PAYMENT_SERVIRED_ERROR_9093','Card Number nonexistent');
  define('MODULE_PAYMENT_SERVIRED_ERROR_9112','Card Number nonexistent');	
?>
