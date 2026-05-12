<?php
/*
  $Id: orders_status.php 1739 2007-12-20 00:52:16Z hpdl $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2007 osCommerce

  Released under the GNU General Public License
*/

define('HEADING_TITLE', 'Estado Pedidos');

define('TABLE_HEADING_ORDERS_STATUS', 'Estado Pedidos');
define('TABLE_HEADING_PUBLIC_STATUS', 'Estado p&uacute;blico');
define('TABLE_HEADING_DOWNLOADS_STATUS', 'Estado de descarga');
define('TABLE_HEADING_ACTION', 'Acci&oacute;n');

define('TEXT_STATUS_NEW', 'Nuevo');
define('TEXT_STATUS_STATUS', 'estado');
define('TEXT_STATUS_EDIT', 'Editar');

define('TEXT_STATUS_INSERT_CONFIRM', 'El estado se insert&oacute; correctamente');
define('TEXT_STATUS_EDIT_CONFIRM', 'El estado se actualiz&oacute; correctamente');

define('TEXT_STATUS_DELETE_SUCCESS', 'Los registros se han eliminado correctamente');

define('TEXT_INFO_EDIT_INTRO', 'Haga los cambios necesarios');
define('TEXT_INFO_ORDERS_STATUS_NAME', 'Estado Pedido:');
define('TEXT_INFO_INSERT_INTRO', 'Introduzca un nombre y los datos del nuevo estado de pedido');
define('TEXT_INFO_DELETE_INTRO', 'Esta seguro que desea suprimir permanentemente este estado de pedido?');
define('TEXT_INFO_HEADING_NEW_ORDERS_STATUS', 'Nuevo Estado Pedido');
define('TEXT_INFO_HEADING_EDIT_ORDERS_STATUS', 'Editar Estado Pedido');
define('TEXT_INFO_HEADING_DELETE_ORDERS_STATUS', 'Eliminar Estado Pedido');

define('TEXT_SET_PUBLIC_STATUS', 'Mostrar el pedido al cliente en este nivel de estado del pedido');
define('TEXT_SET_DOWNLOADS_STATUS', 'Permitir descargas de productos virtuales en este nivel de estado del pedido');

define('ERROR_REMOVE_DEFAULT_ORDER_STATUS', 'Error: El estado de pedido por defecto no se puede eliminar. Establezca otro estado de pedido predeterminado y pruebe de nuevo.');
define('ERROR_STATUS_USED_IN_ORDERS', 'Error: Este estado de pedido esta siendo usado actualmente.');
define('ERROR_STATUS_USED_IN_HISTORY', 'Error: Este estado de pedido se esta usando en algun hist&oacute;rico de algun pedido.');
define('ORDERS_STATUS_TEXT_ORDER', 'Orden');
define('ORDERS_STATUS_TEXT_COLOR', 'Color');

define('TEXT_APPLY_ACTION', 'Aplicar acci&oacute;n');
define('TABLE_ACTIONS', 'Acciones');
define('TEXT_DELETES_CONFIRM', '&iquest;Realmente deseas eliminar los registros?');
define('TEXT_DELETE_ERROR', 'Para realizar alguna de estas operaciones necesitas seleccionar alg&uacute;n registro');
define('TEXT_DELETES', 'Eliminar registros');
?>
