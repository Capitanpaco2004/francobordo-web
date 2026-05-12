<?php
/*

	Fichero idioma español por Jordi (atencion_clientes@hotmail.com)

  $Id: orders_check.php, v 2.0 20/08/2006 Gnidhal Exp $
  Part of contribution OrdersCheck.
  This script is not included in the original version of osCommerce

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
*/

define('HEADING_TITLE', 'Pedidos Salvaguardados');
define('HEADING_TITLE_SEARCH', 'Pedido ID:');
define('HEADING_TITLE_STATUS', 'Estado:');

define('TABLE_HEADING_COMMENTS', 'Commentarios');
define('TABLE_HEADING_CUSTOMERS', 'Clientes');
define('TABLE_HEADING_ORDER_TOTAL', 'Pedido Total');

define('TABLE_HEADING_ORDER_ID', 'Pedido ID');
define('TABLE_HEADING_ORDER_DUPLICATE', 'Pedido Duplicado');


define('TABLE_HEADING_LEGEND', 'Indicador Leyenda');
define('ORDER_IN_BOTH', 'Pedido parece correcto.');
define('ORDER_NOT_IN_BOTH', 'Posible problema del pedido.');



define('TABLE_HEADING_DATE_PURCHASED', 'Fecha compra');
define('TABLE_HEADING_STATUS', 'Estado');
define('TABLE_HEADING_ACTION', 'Accion');
define('TABLE_HEADING_QUANTITY', 'Cantidad.');
define('TABLE_HEADING_PRODUCTS_MODEL', 'Modelo');
define('TABLE_HEADING_PRODUCTS', 'Productos');
define('TABLE_HEADING_TAX', 'Impuesto');
define('TABLE_HEADING_TOTAL', 'Total');
define('TABLE_HEADING_STATUS', 'Estado');
define('TABLE_HEADING_PRICE_EXCLUDING_TAX', 'Precio (ex)');
define('TABLE_HEADING_PRICE_INCLUDING_TAX', 'Precio (inc)');
define('TABLE_HEADING_TOTAL_EXCLUDING_TAX', 'Total (ex)');
define('TABLE_HEADING_TOTAL_INCLUDING_TAX', 'Total (inc)');

define('TABLE_HEADING_STATUS', 'Estado');
define('TABLE_HEADING_CUSTOMER_NOTIFIED', 'Clientes notificado');
define('TABLE_HEADING_DATE_ADDED', 'Fecha añadida');

define('ENTRY_CUSTOMER', 'Cliente:');
define('ENTRY_SOLD_TO', 'VENDIDO A:');
define('ENTRY_STREET_ADDRESS', 'Direccion:');
define('ENTRY_SUBURB', 'Suburbio:');
define('ENTRY_CITY', 'Ciudad:');
define('ENTRY_POST_CODE', 'Codigo Postal:');
define('ENTRY_STATE', 'Estado:');
define('ENTRY_COUNTRY', 'Pais:');
define('ENTRY_TELEPHONE', 'Telefono:');
define('ENTRY_EMAIL_ADDRESS', 'E-Mail:');
define('ENTRY_DELIVERY_TO', 'Entregar a:');
define('ENTRY_SHIP_TO', 'ENVIADO A:');
define('ENTRY_SHIPPING_ADDRESS', 'Direccion envio:');
define('ENTRY_BILLING_ADDRESS', 'Direccion cobro:');
define('ENTRY_PAYMENT_METHOD', 'Metodo pago:');
define('ENTRY_CREDIT_CARD_TYPE', 'Tipo tarjeta de credito:');
define('ENTRY_CREDIT_CARD_OWNER', 'Dueño tarjeta de credito:');
define('ENTRY_CREDIT_CARD_NUMBER', 'Numero tarjeta de credito:');
define('ENTRY_CREDIT_CARD_EXPIRES', 'Expiracion tarjeta de credito:');
define('ENTRY_SUB_TOTAL', 'Sub-Total:');
define('ENTRY_TAX', 'Impuesto:');
define('ENTRY_SHIPPING', 'Envio:');
define('ENTRY_TOTAL', 'Total:');
define('ENTRY_DATE_PURCHASED', 'Fecha Compra:');
define('ENTRY_STATUS', 'Estado:');
define('ENTRY_DATE_LAST_UPDATED', 'Fecha ultima actualización:');
define('ENTRY_NOTIFY_CUSTOMER', 'Notificar cliente:');
define('ENTRY_NOTIFY_COMMENTS', 'Añadir comentarios:');
define('ENTRY_PRINTABLE', 'Imprimir factura');

define('TEXT_INFO_HEADING_DELETE_ORDER', 'Borrar pedido');
define('TEXT_INFO_DELETE_INTRO', 'Estás seguro de querer borrar el pedido?');
if (!defined('TEXT_INFO_RESTOCK_PRODUCT_QUANTITY')) define('TEXT_INFO_RESTOCK_PRODUCT_QUANTITY', 'Restabler stock del pedido');
define('TEXT_DATE_ORDER_CREATED', 'Fecha creación:');
define('TEXT_DATE_ORDER_LAST_MODIFIED', 'Ultima modificacion:');
define('TEXT_INFO_PAYMENT_METHOD', 'Metodo pago:');

define('TEXT_ALL_ORDERS', 'Todos los pedidos');
define('TEXT_NO_ORDER_HISTORY', 'Sin historial de pedidos');

define('EMAIL_SEPARATOR', '------------------------------------------------------');
define('EMAIL_TEXT_SUBJECT', 'Actualizacion de pedido');
define('EMAIL_TEXT_ORDER_NUMBER', 'Numero pedido:');
define('EMAIL_TEXT_INVOICE_URL', 'Factura detallada:');
define('EMAIL_TEXT_DATE_ORDERED', 'Fecha pedido:');
define('EMAIL_TEXT_STATUS_UPDATE', 'Tu pedido ha sido actualizado al siguiente estado.' . "\n\n" . 'Nuevo estado: %s' . "\n\n" . 'Porfavor responda a este mail para cualquier duda.' . "\n");
define('EMAIL_TEXT_COMMENTS_UPDATE', 'Los comentarios de tu pedido son' . "\n\n%s\n\n");

define('ERROR_ORDER_DOES_NOT_EXIST', 'Error: El pedido no existe.');
define('SUCCESS_ORDER_UPDATED', 'Satisfactorio: El pedido ha sido actualizado correctamente.');
define('WARNING_ORDER_NOT_UPDATED', 'Atencion: Ningun cambio detectado. El pedido no ha sido actualizado.');


define('TEXT_INFO_HEADING_MOVE_ORDER', 'MOVER Pedido');
define('TEXT_INFO_MOVE_INTRO', 'Estas seguro de querer MOVER el pedido?');
define('TEXT_INFO_MOVE_INTRO_EXTENDED', 'Debes borrar el pedido por ti mismo.');
define('WARNING_MOVE_ORDER', 'Atencion: Antes de MOVER este pedido debes contactar con el cliente para preguntar que ha ha ido mal!!');
?>