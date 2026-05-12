<?php
/*
  $Id: deposito.php,v 1.0 2003/08/25 21:00:00 creado por Israel Cabrera y luego modificado para 2 cuentas por Ronald Hernandez Exp $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2002 osCommerce
  Copyright (c) 2003 Mayan Open Source Enterprises

  Released under the GNU General Public License
*/

  define('MODULE_PAYMENT_DEPOSITO_TEXT_TITLE', 'Transferencia/Depósito Bancario');
  define('MODULE_PAYMENT_DEPOSITO_TEXT_TITLE2', '<h2>Transferencia/Depósito Bancario</h2><h3>Tome nota de los Datos para hacer su transferencia/depósito:</h3>Telf. de aclaraciones:&nbsp;<strong>' . MODULE_PAYMENT_DEPOSITO_ACLARATEL . '</strong><br /><br />');
  define('MODULE_PAYMENT_DEPOSITO_TEXT_RESUMEN', 'Módulo de Transferencias/Depósitos Bancarios con notificación de ficha de transferencia/depósito vía FAX');
  define('MODULE_PAYMENT_DEPOSITO_TEXT_DESCRIPTION', '<br /><br /><strong>-Pagos nacionales:</strong><br /><br /><strong>Titular:</strong> Game Service Soluciones Técnicas s.l.<br /><strong>Banco:</strong> Caja Madrid<br /><strong>Numero de cuenta:</strong> 2038-1149-21-6000395655<br /><br /><br /><strong>-Pagos Internacionales:</strong><br /><br /><strong>Titulas:</strong> Game Service Soluciones Técnicas s.l.<br /><strong>Banco:</strong> Caja Madrid<br /><strong>Numero de cuenta:</strong> 2038-1149-21-6000395655<br /><strong>I.B.A.N</strong> ES36 2038 1149 2160 0039 5655<br /><strong>B.I.C</strong> CAHMESMMXXX');
  define('MODULE_PAYMENT_DEPOSITO_TEXT_BANK', 'Banco:&nbsp;<strong>' . MODULE_PAYMENT_DEPOSITO_BANK . '</strong><br />');
  define('MODULE_PAYMENT_DEPOSITO_TEXT_ACCOUNT_NUMBER', 'Número de Cuenta:&nbsp;<strong>' . MODULE_PAYMENT_DEPOSITO_ACCOUNT_NUMBER . '</strong><br />');
  define('MODULE_PAYMENT_DEPOSITO_TEXT_BANK2', 'Banco 2:&nbsp;<strong>' . MODULE_PAYMENT_DEPOSITO_BANK2 . '</strong><br />');
  define('MODULE_PAYMENT_DEPOSITO_TEXT_ACCOUNT_NUMBER2', 'Número de Cuenta 2:&nbsp;<strong>' . MODULE_PAYMENT_DEPOSITO_ACCOUNT_NUMBER2 . '</strong><br />');
  define('MODULE_PAYMENT_DEPOSITO_TEXT_REFERENCIA', 'Enviar Nro. transferencia/depósito por el email:&nbsp;<strong>' . MODULE_PAYMENT_DEPOSITO_REFERENCIA . '</strong><br />');
  define('MODULE_PAYMENT_DEPOSITO_TEXT_FAXNO', 'Enviar transferencia/depósito al FAX:&nbsp;<strong>' . MODULE_PAYMENT_DEPOSITO_FAXNO . '</strong><br />');
  define('MODULE_PAYMENT_DEPOSITO_TEXT_ACLARATEL', 'Telf. de aclaraciones:&nbsp;<strong>' . MODULE_PAYMENT_DEPOSITO_ACLARATEL . '</strong><br /><br />');
  define('MODULE_PAYMENT_DEPOSITO_TEXT_FOOTER', '<i><strong>Su orden no se procesará hasta que se hayan recibido los datos de la transferencia/depósito. Gracias</i></strong>');
?>