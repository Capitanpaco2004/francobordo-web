<?php
/*
$Id: telephone.php,v 1.1 2003/04/30
osCommerce, Open Source E-Commerce Solutions
http://www.oscommerce.com
Copyright (c) 2002 osCommerce
Released under the GNU General Public License
Based on Ausbank.php ; adapted/translated in French by Gelong Shenphen
 */
define('MODULE_PAYMENT_TELEPHONE_TEXT_TITLE', 'Tarjeta de Crédito por Teléfono');
define('MODULE_PAYMENT_TELEPHONE_TEXT_DESCRIPTION', 'Usted elige como forma de pago: <b>' . MODULE_PAYMENT_TELEPHONE_TEXT_TITLE . '</b><br >
Horario de Atención telefónica: <b>' . MODULE_PAYMENT_TELEPHONE_OUVERTURE_ES . '</b><br >
  Número de Teléfono : <b>' . MODULE_PAYMENT_TELEPHONE_NUM . '</b><br >
  Mas Información : <b>' . MODULE_PAYMENT_TELEPHONE_PRECISION_ES . '</b>
  <br >Su pedido no será procesado hasta que recibamos el pago.');
define('MODULE_PAYMENT_TELEPHONE_TEXT_EMAIL_FOOTER', "Use la siguiente información para contactarnos y finalizar su pedido:\n Usted puede contactar con Francobordo " . MODULE_PAYMENT_TELEPHONE_OUVERTURE_ES . " en el siguiente número: " . MODULE_PAYMENT_TELEPHONE_NUM . ".\nRecuerde : " . MODULE_PAYMENT_TELEPHONE_PRECISION_ES . "\n\n Su pedido no se tramitará hasta que no recibamos el pago.");
