<?php
/*
  $Id: orders_status.php,v 1.5 2002/01/29 14:43:00 hpdl Exp $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2002 osCommerce

  Released under the GNU General Public License
*/

define('HEADING_TITLE', 'Prioridad de Ticket');

define('TABLE_HEADING_ORDERS_STATUS', 'Prioridad de Ticket');
define('TABLE_HEADING_ACTION', 'Accion');

define('TEXT_INFO_EDIT_INTRO', 'Por favor, realice los cambios necesarios');
define('TEXT_INFO_ORDERS_STATUS_NAME', 'Prioridad de Ticket:');
define('TEXT_INFO_INSERT_INTRO', 'Por favor, introduzca la prioridad del ticket y su fecha');
define('TEXT_INFO_DELETE_INTRO', 'Borrar prioridad?');
define('TEXT_INFO_HEADING_NEW_ORDERS_STATUS', 'Nueva prioridad de Ticket');
define('TEXT_INFO_HEADING_EDIT_ORDERS_STATUS', 'Actualizar prioridad de Ticket');
define('TEXT_INFO_HEADING_DELETE_ORDERS_STATUS', 'Borrar prioridad de Ticket');

define('ERROR_REMOVE_DEFAULT_ORDER_STATUS',  'Error: El estado por defecto de la orden no se puede borrar. Por favor asigne un estado por defecto e inténtelo de nuevo.');
define('ERROR_STATUS_USED_IN_ORDERS','Error: Este estado de orden actualmente se utiliza en algunas ordenes.');
define('ERROR_STATUS_USED_IN_HISTORY', 'Error: Este estado de orden existe en las órdenes del histórico.');
?>