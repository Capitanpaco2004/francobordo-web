<?php
/*
  $Id: banktransfer.php,v 1.3 2002/05/31 19:02:02 thomasamoulton Exp $
  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com
  Copyright (c) 2002 osCommerce
  Released under the GNU General Public License
*/
  define('MODULE_PAYMENT_BANKTRANSFER_TEXT_TITLE', 'Transferencia Bancaria');
  define('MODULE_PAYMENT_BANKTRANSFER_TEXT_DESCRIPTION', 'Por favor use los siguientes datos para transferir el valor total de su compra:<br>
<table border="1" cellspacing="0" cellpadding="2">
<tr><td class="main">Número de Cuenta</td><td class="main">' . MODULE_PAYMENT_BANKTRANSFER_ACCNAM . '</td></tr>
<tr><td class="main">Nombre del Banco</td><td class="main">' . MODULE_PAYMENT_BANKTRANSFER_BANKNAM . '</td></tr>
<tr><td class="main">Número de Cuenta</td><td class="main">' . MODULE_PAYMENT_BANKTRANSFER_ACCNUM . '</td></tr>
</table>
<br>La compra no se enviará hasta que no aparezca el importe en nuestra cuenta.');
  define('MODULE_PAYMENT_BANKTRANSFER_TEXT_EMAIL_FOOTER', '<font style="line-height: 10px;">Por favor use los siguientes datos para transferir el valor total de su compra:' . "\n" . '</font>' . "\n" . '<font color="#27a5d2">Nº de Cuenta:</font> <b>' . MODULE_PAYMENT_BANKTRANSFER_ACCNUM . '</b>' . "\n" . '<font color="#27a5d2">Titular de la Cuenta:</font> <b>' . MODULE_PAYMENT_BANKTRANSFER_ACCNAM . '</b>' . "\n" . '<font color="#27a5d2">Nombre del Banco:</font> <b>' . MODULE_PAYMENT_BANKTRANSFER_BANKNAM . '</b>' . "\n\n" . 'Por favor cuando haga la transferencia ponga en las observaciones su número de pedido, gracias.' . "\n\n" . '<span style="display: inline-block;"><img width="16" height="14" border="0" src="' . HTTP_SERVER . DIR_WS_CATALOG . DIR_THEME . 'images/email/alert.jpg" style="display: block; border: 0;"></span><font color="#fd6590">Su pedido no se enviará hasta que recibamos el pago en nuestra cuenta.</font>');
  if (!defined('MODULE_PAYMENT_BANKTRANSFER_SORT_ORDER')) define('MODULE_PAYMENT_BANKTRANSFER_SORT_ORDER', 'Sort order of display');
if (!defined('MODULE_PAYMENT_BANKTRANSFER_ORDER_STATUS_ID')) define('MODULE_PAYMENT_BANKTRANSFER_ORDER_STATUS_ID', 'Set the order status');
?>
