<?php
/*
  $Id: index.php 1739 2007-12-20 00:52:16Z hpdl $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2006 osCommerce

  Released under the GNU General Public License
*/

define('BOX_TITLE_ORDERS', 'Pedidos');
if (!defined('HEADING_TITLE')) define('HEADING_TITLE', 'Clientes Online');
define('BOX_ENTRY_CUSTOMERS', 'Clientes');

if (!defined('HEADING_TITLE_SEARCH')) define('HEADING_TITLE_SEARCH', 'Buscar:');
define('TABLE_HEADING_FIRSTNAME', 'Nombre');
define('TABLE_HEADING_LASTNAME', 'Apellidos');
define('TABLE_HEADING_ACCOUNT_CREATED', 'Cuenta Creada');
define('TABLE_HEADING_ACTION', 'Acción');
define('TEXT_DATE_ACCOUNT_CREATED', 'Cuenta Creada:');
define('TEXT_DATE_ACCOUNT_LAST_MODIFIED', 'Última modificacion:');
define('TEXT_INFO_DATE_LAST_LOGON', 'Última Conexion:');
define('TEXT_INFO_NUMBER_OF_LOGONS', 'Numero de Conexiones:');
define('TEXT_INFO_COUNTRY', 'Pais:');
define('TEXT_INFO_NUMBER_OF_REVIEWS', 'Comentarios:');
define('TEXT_DELETE_INTRO', 'Estás seguro de querer eliminar a este cliente?');
define('TEXT_DELETE_REVIEWS', 'Eliminar %s comentario(s)');
define('TEXT_INFO_HEADING_DELETE_CUSTOMER', 'Eliminar Cliente');
define('TYPE_BELOW', 'Type below');
define('PLEASE_SELECT', 'Seleccione');

if (!defined('HEADING_TITLE')) define('HEADING_TITLE', 'Pedidos');
if (!defined('HEADING_TITLE_SEARCH')) define('HEADING_TITLE_SEARCH', 'Pedidos ID:');
define('HEADING_TITLE_STATUS', 'Estados:');
define('TABLE_HEADING_COMMENTS', 'Commentarios');
define('TABLE_HEADING_CUSTOMERS', 'Clientes');
define('TABLE_HEADING_ORDER_TOTAL', 'Total Pedidos');
define('TABLE_HEADING_DATE_PURCHASED', 'Fecha Compra');
define('TABLE_HEADING_STATUS', 'Estados');
if (!defined('TABLE_HEADING_ACTION')) define('TABLE_HEADING_ACTION', 'Acción');
define('TABLE_HEADING_QUANTITY', 'Cantidad');
define('TABLE_HEADING_PRODUCTS_MODEL', 'Modelo');
define('TABLE_HEADING_PRODUCTS', 'Productos');
define('TABLE_HEADING_TAX', 'Impuesto');
define('TABLE_HEADING_TOTAL', 'Total');
define('TABLE_HEADING_PRICE_EXCLUDING_TAX', 'Precio (ex)');
define('TABLE_HEADING_PRICE_INCLUDING_TAX', 'Precio (inc)');
define('TABLE_HEADING_TOTAL_EXCLUDING_TAX', 'Total (ex)');
define('TABLE_HEADING_TOTAL_INCLUDING_TAX', 'Total (inc)');
define('TABLE_HEADING_CUSTOMER_NOTIFIED', 'Notificar Cliente');
define('TABLE_HEADING_DATE_ADDED', 'Fecha Añadido');
define('ENTRY_CUSTOMER', 'Clientes:');
define('ENTRY_SOLD_TO', 'VENDIDO A:');
define('ENTRY_DELIVERY_TO', 'Entregar al:');
define('ENTRY_SHIP_TO', 'ENVIAR A:');
define('ENTRY_SHIPPING_ADDRESS', 'Dirección de envio:');
define('ENTRY_BILLING_ADDRESS', 'Dirección de Facturación:');
define('ENTRY_PAYMENT_METHOD', 'Forma de Pago:');
define('ENTRY_CREDIT_CARD_TYPE', 'Credit Card Type:');
define('ENTRY_CREDIT_CARD_OWNER', 'Credit Card Owner:');
define('ENTRY_CREDIT_CARD_NUMBER', 'Credit Card Number:');
define('ENTRY_CREDIT_CARD_EXPIRES', 'Credit Card Expires:');
define('ENTRY_SUB_TOTAL', 'SubTotal:');
define('ENTRY_TAX', 'Impuestos:');
define('ENTRY_SHIPPING', 'Envío:');
define('ENTRY_TOTAL', 'Total:');
define('ENTRY_DATE_PURCHASED', 'Fecha de Compra:');
define('ENTRY_STATUS', 'Estado:');
define('ENTRY_DATE_LAST_UPDATED', 'Ultima Actualizaciónd:');
define('ENTRY_NOTIFY_CUSTOMER', 'Notificar al Cliente:');
define('ENTRY_NOTIFY_COMMENTS', 'Comentarios:');
define('ENTRY_PRINTABLE', 'Imprimir Factura');
define('TEXT_INFO_HEADING_DELETE_ORDER', 'Eliminar Pedido');
define('TEXT_INFO_DELETE_INTRO', '¿Estás seguro de querer eliminar este pedido?');
if (!defined('TEXT_INFO_RESTOCK_PRODUCT_QUANTITY')) define('TEXT_INFO_RESTOCK_PRODUCT_QUANTITY', 'Restock la cantidad del producto');
define('TEXT_DATE_ORDER_CREATED', 'Fecha Creación:');
define('TEXT_DATE_ORDER_LAST_MODIFIED', 'Ultima Modificacion:');
define('TEXT_INFO_PAYMENT_METHOD', 'Forma de Pago:');
define('TEXT_ALL_ORDERS', 'Todos los Pedidos');
define('TEXT_NO_ORDER_HISTORY', 'No hay Pedidos disponibles en el Historial');
define('EMAIL_SEPARATOR', '------------------------------------------------------');
define('EMAIL_TEXT_SUBJECT', 'Actualizar Pedido');
define('EMAIL_TEXT_ORDER_NUMBER', 'Pedido Numero:');
define('EMAIL_TEXT_INVOICE_URL', 'Detalles Factura:');
define('EMAIL_TEXT_DATE_ORDERED', 'Fecha Pedido:');
define('EMAIL_TEXT_STATUS_UPDATE', 'Tu Pedido ha sido actualizado al siguiente estado.' . "\n\n" . 'Nuevo estado: %s' . "\n\n" . 'Por favor, responda a este email si tiene alguna pregunta.' . "\n");
define('EMAIL_TEXT_COMMENTS_UPDATE', 'Los comentarios de tu Pedido son' . "\n\n%s\n\n");
define('ERROR_ORDER_DOES_NOT_EXIST', 'Error: El Pedido no existe.');
define('SUCCESS_ORDER_UPDATED', 'Éxito: Pedido actualizado con éxito.');
define('WARNING_ORDER_NOT_UPDATED', 'Atención: No cambiar. El pedido no ha sido actualizado.');

if (!defined('TEXT_ALL_ORDERS')) define('TEXT_ALL_ORDERS', 'Todos');
if (!defined('HEADING_TITLE')) define('HEADING_TITLE', 'Clientes Conectados');
define('TABLE_HEADING_ONLINE', 'Online');
define('TABLE_HEADING_CUSTOMER_ID', 'ID');
define('TABLE_HEADING_FULL_NAME', 'Nombre completo');
define('TABLE_HEADING_IP_ADDRESS', 'IP Address');
define('TABLE_HEADING_ENTRY_TIME', 'Tiempo');
define('TABLE_HEADING_LAST_CLICK', 'Ultimo Click');
define('TABLE_HEADING_LAST_PAGE_URL', 'Ultimo URL');
if (!defined('TABLE_HEADING_ACTION')) define('TABLE_HEADING_ACTION', 'Acción');
define('TABLE_HEADING_SHOPPING_CART', 'Carrito del Cliente');
define('TEXT_SHOPPING_CART_SUBTOTAL', 'Subtotal');
define('TEXT_NUMBER_OF_CUSTOMERS', 'Actualmente hay %s Clientes online');
?>