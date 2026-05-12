<?php
/*
  $Id: orders_status.php,v 1.5 2002/01/29 14:43:00 hpdl Exp $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2002 osCommerce

  Released under the GNU General Public License
*/

define('HEADING_TITLE', 'Departamento de Soporte');

define('TABLE_HEADING_ORDERS_STATUS', 'Departamentos de soporte');
define('TABLE_HEADING_ACTION', 'Accion');

define('TEXT_INFO_EDIT_INTRO', 'Por favor, realice los cambios necesarios');
define('TEXT_INFO_ORDERS_STATUS_NAME', 'Departamentos de Soporte');
define('TEXT_INFO_INSERT_INTRO', 'Por favor introduzca el nuevo departamento con su fecha');
define('TEXT_INFO_DELETE_INTRO', 'Seguro que quiere borrar este dpto?');
define('TEXT_INFO_HEADING_NEW_ORDERS_STATUS', 'Nuevo Dpto de soporte');
define('TEXT_INFO_HEADING_EDIT_ORDERS_STATUS', 'Editar Dpto de soporte');
define('TEXT_INFO_HEADING_DELETE_ORDERS_STATUS', 'borrar Dpto de soporte');

define('ERROR_REMOVE_DEFAULT_ORDER_STATUS', 'Error: El estado por defecto de la orden no se puede borrar. Por favor asigne un estado por defecto e inténtelo de nuevo.');
define('ERROR_STATUS_USED_IN_ORDERS', 'Error: Este estado de orden actualmente se utiliza en algunas ordenes.');
define('ERROR_STATUS_USED_IN_HISTORY', 'Error: Este estado de orden existe en las órdenes del histórico.');
?>