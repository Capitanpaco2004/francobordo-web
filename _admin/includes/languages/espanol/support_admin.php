<?php
/*
  $Id: support_admin.php,v 1.5 2002/01/29 14:43:00 hpdl Exp $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2002 osCommerce

  Released under the GNU General Public License
*/

define('HEADING_TITLE', 'Supporters');

define('TABLE_HEADING_ORDERS_STATUS', 'Supporters');
define('TABLE_HEADING_ACTION', 'Acci&oacute;n');

define('TEXT_INFO_EDIT_INTRO', 'Por favor realice los cambios necesarios');
define('TEXT_INFO_ORDERS_STATUS_NAME', 'Nombre Administrador:');
define('TEXT_INFO_INSERT_INTRO', 'Por favor introduzca detalles del nuevo administrador');
define('TEXT_INFO_DELETE_INTRO', 'Est&aacute; seguro de querer borrar este administrador?');
define('TEXT_INFO_HEADING_NEW_ORDERS_STATUS', 'Nuevo Administrador');
define('TEXT_INFO_HEADING_EDIT_ORDERS_STATUS', 'Editar Administrador');
define('TEXT_INFO_HEADING_DELETE_ORDERS_STATUS', 'Borrar Administtrador');

define('ENTRY_ADMIN_NAME', 'Nombre Admin ');
define('ENTRY_ADMIN_EMAIL', 'Email Admin ');

define('ERROR_REMOVE_DEFAULT_ORDER_STATUS', 'Error: El estado por defecto de la orden no se puede borrar. Por favor asigne un estado por defecto e int&eacute;ntelo de nuevo.');
define('ERROR_STATUS_USED_IN_ORDERS', 'Error: Este estado de orden actualmente se utiliza en algunas ordenes.');
define('ERROR_STATUS_USED_IN_HISTORY', 'Error: Este estado de orden existe en las &oacute;rdenes del hist&oacute;rico.');
?>