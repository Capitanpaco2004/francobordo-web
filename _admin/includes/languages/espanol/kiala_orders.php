<?php
define('TEXT_CALL_KIALA_WS', 'Llamada Kiala WebService');

define('TABLE_HEADING_KIALA_WS_CALL_STATUS', 'Sincronización de estado de la orden con Kiala');

if (!defined('HEADING_TITLE')) define('HEADING_TITLE', 'Pedidos');
if (!defined('HEADING_TITLE_SEARCH')) define('HEADING_TITLE_SEARCH', 'ID Pedido:');
define('HEADING_TITLE_STATUS', 'Estado:');

define('TABLE_HEADING_COMMENTS', 'Comentarios');
define('TABLE_HEADING_CUSTOMERS', 'Clientes');
define('TABLE_HEADING_ORDER_TOTAL', 'Total Pedido');
define('TABLE_HEADING_DATE_PURCHASED', 'Fecha de Compra');
if (!defined('TABLE_HEADING_STATUS')) define('TABLE_HEADING_STATUS', 'Estado');
define('TABLE_HEADING_ACTION', 'Acción');
define('TABLE_HEADING_QUANTITY', 'Cantidad');
define('TABLE_HEADING_PRODUCTS_MODEL', 'Modelo');
define('TABLE_HEADING_PRODUCTS', 'Productos');
define('TABLE_HEADING_TAX', 'Impuesto');
define('TABLE_HEADING_TOTAL', 'Total');
define('TABLE_HEADING_PRICE_EXCLUDING_TAX', 'Precio (ex)');
define('TABLE_HEADING_PRICE_INCLUDING_TAX', 'Precio (inc)');
define('TABLE_HEADING_TOTAL_EXCLUDING_TAX', 'Total (ex)');
define('TABLE_HEADING_TOTAL_INCLUDING_TAX', 'Total (inc)');

define('TABLE_HEADING_CUSTOMER_NOTIFIED', 'Cliente Notificado');
define('TABLE_HEADING_DATE_ADDED', 'Añadido el');

define('ENTRY_CUSTOMER', 'Cliente:');
define('ENTRY_SOLD_TO', 'Cliente:');
if (!defined('ENTRY_STREET_ADDRESS')) define('ENTRY_STREET_ADDRESS', 'Dirección:');
if (!defined('ENTRY_SUBURB')) define('ENTRY_SUBURB', '');
if (!defined('ENTRY_CITY')) define('ENTRY_CITY', 'Población:');
if (!defined('ENTRY_POST_CODE')) define('ENTRY_POST_CODE', 'Código Postal:');
if (!defined('ENTRY_STATE')) define('ENTRY_STATE', 'Provincia:');
if (!defined('ENTRY_COUNTRY')) define('ENTRY_COUNTRY', 'País:');
define('ENTRY_TELEPHONE', 'Teléfono:');
if (!defined('ENTRY_EMAIL_ADDRESS')) define('ENTRY_EMAIL_ADDRESS', 'E-Mail:');
define('ENTRY_DELIVERY_TO', 'Enviar A:');
define('ENTRY_SHIP_TO', 'Enviar A:');
define('ENTRY_SHIPPING_ADDRESS', 'Dirección de Envío:');
define('ENTRY_BILLING_ADDRESS', 'Dirección de Facturación:');
define('ENTRY_PAYMENT_METHOD', 'Método de Pago:');
define('ENTRY_CREDIT_CARD_TYPE', 'Tipo Tarjeta Crédito:');
define('ENTRY_CREDIT_CARD_OWNER', 'Titular Tarjeta Crédito:');
define('ENTRY_CREDIT_CARD_NUMBER', 'Número Tarjeta Crédito:');
define('ENTRY_CREDIT_CARD_EXPIRES', 'Caducidad Tarjeta Crédito:');
define('ENTRY_SUB_TOTAL', 'Subtotal:');
define('ENTRY_TAX', 'Impuestos:');
define('ENTRY_SHIPPING', 'Gastos de Envío:');
define('ENTRY_TOTAL', 'Total:');
define('ENTRY_DATE_PURCHASED', 'Fecha de Compra:');
define('ENTRY_STATUS', 'Estado:');
define('ENTRY_DATE_LAST_UPDATED', 'Última Modificación:');
define('ENTRY_NOTIFY_CUSTOMER', 'Notificar Cliente:');
define('ENTRY_NOTIFY_COMMENTS', 'Añadir Comentarios:');
define('ENTRY_PRINTABLE', 'Imprimir Factura');

define('TEXT_INFO_HEADING_DELETE_ORDER', 'Eliminar Pedido');
define('TEXT_INFO_DELETE_INTRO', '¡Seguro que quiere eliminar este pedido?');
if (!defined('TEXT_INFO_RESTOCK_PRODUCT_QUANTITY')) define('TEXT_INFO_RESTOCK_PRODUCT_QUANTITY', 'Añada productos al almacen');
define('TEXT_DATE_ORDER_CREATED', 'Añadido el:');
define('TEXT_DATE_ORDER_LAST_MODIFIED', 'Modificado:');
define('TEXT_INFO_PAYMENT_METHOD', 'Método de Pago:');

define('TEXT_ALL_ORDERS', 'Todos');
define('TEXT_NO_ORDER_HISTORY', 'No hay histórico');

define('EMAIL_SEPARATOR', '------------------------------------------------------');
define('EMAIL_TEXT_SUBJECT', 'Actualización del Pedido');
define('EMAIL_TEXT_ORDER_NUMBER', 'Número de Pedido:');
define('EMAIL_TEXT_INVOICE_URL', 'Pedido Detallado:');
define('EMAIL_TEXT_DATE_ORDERED', 'Fecha del Pedido:');
define('EMAIL_TEXT_STATUS_UPDATE', 'Su pedido ha sido actualizado al siguiente estado.' . "\n\n" . 'Nuevo estado: %s' . "\n\n" . 'Por favor responda a este email si tiene alguna pregunta que hacer.' . "\n");
define('EMAIL_TEXT_COMMENTS_UPDATE', 'Los comentarios para su pedido son' . "\n\n%s\n\n");

define('ERROR_ORDER_DOES_NOT_EXIST', 'Error: No existe pedido.');
define('SUCCESS_ORDER_UPDATED', 'Éxito: Pedido actualizado correctamente.');
define('WARNING_ORDER_NOT_UPDATED', 'Advertencia: Nada que cambiar. El pedido no fue actualizado.');
define('CSV_EXPORT', 'CSV Export');
define('UPS_EXPORT', 'UPS WorldShip');
define('CALL_WS', 'Call Kiala WS');
define('K_ID', 'KialaPoint ID');
define('K_FILE', 'File');
?>
