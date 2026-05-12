<?php
define('TEXT_ORDER_NUMBER', 'Pedidos');
define('TABLE_HEADING_CUSTOMERS_GROUPS', 'Grupo&#160;Cliente');
if (!defined('HEADING_TITLE_SEARCH')) define('HEADING_TITLE_SEARCH', 'Pedido:');
define('HEADING_TITLE_STATUS', 'Estado:');

define('TABLE_HEADING_COMMENTS', 'Comentarios');
define('TABLE_HEADING_CUSTOMERS', 'Clientes');
define('TABLE_HEADING_ORDER_TOTAL', 'Total Pedido');
define('TABLE_HEADING_DATE_PURCHASED', 'Fecha de Compra');
define('TABLE_HEADING_STATUS', 'Estado');
define('TABLE_HEADING_ACTION', 'Acci&oacute;n');
define('TABLE_HEADING_QUANTITY', 'Cantidad');
define('TABLE_HEADING_PRODUCTS_MODEL', 'Modelo');
define('TABLE_HEADING_PRODUCTS', 'Productos ');
define('TABLE_HEADING_TAX', 'Impuesto');
define('TABLE_HEADING_TOTAL', 'Total');
define('TABLE_HEADING_PRICE_EXCLUDING_TAX', 'Precio (ex)');
define('TABLE_HEADING_PRICE_INCLUDING_TAX', 'Precio (inc)');
define('TABLE_HEADING_TOTAL_EXCLUDING_TAX', 'Total (ex)');
define('TABLE_HEADING_TOTAL_INCLUDING_TAX', 'Total (inc)');

define('DIV_ADD_PRODUCT_HEADING', 'Añadir Producto');
define('ADD_PRODUCT_SELECT_PRODUCT', 'Introduce el nombre del producto');
define('PRODUCTS_SEARCH_RESULTS', 'Resultados');

define('TABLE_HEADING_CUSTOMER_NOTIFIED', 'Cliente Notificado');
define('TABLE_HEADING_DATE_ADDED', 'A&ntilde;adido el');

define('ENTRY_CUSTOMER', 'Datos del Cliente');
define('ENTRY_SOLD_TO', 'Cliente:');
define('ENTRY_DELIVERY_TO', 'Enviar A:');
define('ENTRY_SHIP_TO', 'Enviar A:');
define('ENTRY_CUSTOMER_GROUP', 'Tarifa cliente:');
define('ENTRY_FECHA_REGISTRO', 'Fecha Registro:');
define('ENTRY_SHIPPING_ADDRESS', 'Direcci&oacute;n de Env&iacute;o');
define('ENTRY_BILLING_ADDRESS', 'Direcci&oacute;n de Facturaci&oacute;n');
if (!defined('ENTRY_BILLING_ADDRESS')) define('ENTRY_BILLING_ADDRESS', 'Direcci&oacute;n de Facturaci&oacute;n');
define('ENTRY_PAYMENT_METHOD', 'M&eacute;todo de Pago:');
define('ENTRY_CREDIT_CARD_TYPE', 'Tipo Tarjeta Credito:');
define('ENTRY_CREDIT_CARD_OWNER', 'Titular Tarjeta Credito:');
define('ENTRY_CREDIT_CARD_NUMBER', 'N&uacute;mero Tarjeta Credito:');
define('ENTRY_CREDIT_CARD_EXPIRES', 'Caducidad Tarjeta Credito:');
define('ENTRY_SUB_TOTAL', 'Subtotal:');
define('ENTRY_TAX', 'Impuestos:');
define('ENTRY_SHIPPING', 'Gastos de Env&iacute;o:');
define('ENTRY_TOTAL', 'Total:');
define('ENTRY_DATE_PURCHASED', 'Fecha de Compra:');
define('ENTRY_STATUS', 'Estado:');
define('ENTRY_DATE_LAST_UPDATED', 'Ultima Modificaci&oacute;n:');
define('ENTRY_NOTIFY_CUSTOMER', 'Notificar Cliente:');
define('ENTRY_NOTIFY_COMMENTS', 'A&ntilde;adir Comentarios:');
define('ENTRY_PRINTABLE', 'Imprimir Factura');

define('TEXT_INFO_HEADING_DELETE_ORDER', 'Eliminar Pedido');
define('TEXT_INFO_DELETE_INTRO', 'Seguro que quiere eliminar este pedido?');
if (!defined('TEXT_INFO_RESTOCK_PRODUCT_QUANTITY')) define('TEXT_INFO_RESTOCK_PRODUCT_QUANTITY', 'A&ntilde;adir productos al almacen');
define('TEXT_DATE_ORDER_CREATED', 'A&ntilde;adido el:');
define('TEXT_DATE_ORDER_LAST_MODIFIED', 'Modificado:');
define('TEXT_INFO_PAYMENT_METHOD', 'M&eacute;todo de Pago:');

define('TEXT_ALL_ORDERS', 'Todos');
define('TEXT_NO_ORDER_HISTORY', 'No hay hist&oacute;rico');

define('EMAIL_SEPARATOR', '------------------------------------------------------');
define('EMAIL_TEXT_SUBJECT', 'Actualización del Pedido');
define('EMAIL_TEXT_ORDER_NUMBER', 'N&uacute;mero de Pedido:');
define('EMAIL_TEXT_INVOICE_URL', 'Pedido Detallado:');
define('EMAIL_TEXT_DATE_ORDERED', 'Fecha del Pedido:');
define('EMAIL_TEXT_STATUS_UPDATE', 'Su pedido ha sido actualizado al siguiente estado.' . "\n\n" . 'Nuevo estado: %s' . "\n\n" . 'Por favor responda a este email si tiene alguna pregunta que hacer.' . "\n");
define('EMAIL_TEXT_COMMENTS_UPDATE', 'Los comentarios sobre su pedido son' . "\n\n%s\n\n");

define('ERROR_ORDER_DOES_NOT_EXIST', 'Error: No existe pedido.');
define('SUCCESS_ORDER_UPDATED', 'Exito: Pedido actualizado correctamente.');
define('WARNING_ORDER_NOT_UPDATED', 'Advertencia: No se ha actualizado el pedido, no habia nada que actualizar.');
define('ENTRY_NOTIFY_POINTS', 'Confirmar los Puntos Pendientes:');
define('ENTRY_QUE_POINTS', 'y Guardar');
define('ENTRY_QUE_DEL_POINTS', 'y Eliminar:');
define('ENTRY_CONFIRMED_POINTS', 'Puntos Confirmados.  ');
define('TABLE_HEADING_PRODUCTS_REF', 'Referencia');

$_oID_lang = ($_GET['oID'] ?? '') ?? '';
$postcode_query = tep_DB_query ("select delivery_postcode from " . TABLE_ORDERS . " where orders_id = '" . (int)$_oID_lang . "'");
$postcode=tep_db_fetch_array($postcode_query);
$postcode_envio = is_array($postcode) ? ($postcode['delivery_postcode'] ?? '') : '';

define('TABLE_HEADING_COMENTARIO_1', 'Estimado cliente:
Le comunicamos que hoy ha sido enviado su pedido por correos.
Forma de envio: Correos Express 48/72 Horas
Codigo de seguimiento: 
Pagina web para el seguimiento: www.correos.es

Asi mismo recordarle que se ha emitido la factura en formato PDF de su pedido

Si desea descargarla, deberá entrar en Su Cuenta y acceder a historial de pedidos, después pulse ver datos del pedido y al final del mismo podrá ver el enlace para descargar la factura.');
define('TABLE_HEADING_COMENTARIO_2', 'Estimado cliente:
Le comunicamos que hoy ha sido enviado su pedido por correos.
Forma de envio: correos certificado
Codigo de seguimiento: CD00
Pagina web para el seguimiento: www.correos.es

Asi mismo recordarle que se ha emitido la factura en formato PDF de su pedido

Si desea descargarla, deberá entrar en Su Cuenta y acceder a historial de pedidos, después pulse ver datos del pedido y al final del mismo podrá ver el enlace para descargar la factura.');
define('TABLE_HEADING_COMENTARIO_3', 'Estimado cliente:
Le comunicamos que hoy ha sido enviado su pedido por correos.
Forma de envio: Correos Contrareembolso
Codigo de seguimiento: RB000
Pagina web para el seguimiento: www.correos.es

Asi mismo recordarle que se ha emitido la factura en formato PDF de su pedido

Si desea descargarla, deberá entrar en Su Cuenta y acceder a historial de pedidos, después pulse ver datos del pedido y al final del mismo podrá ver el enlace para descargar la factura.');
define('TABLE_HEADING_COMENTARIO_4', 'Estimado cliente:

Le comunicamos que hoy ha sido enviado su pedido por la agencia de transportes SEUR

Forma de envio: mensajeria SEUR
Para hacer el seguimiento de su pedido pulse en el siguiente enlace: http://www.seur.com/seguimiento/'.($_GET['oID'] ?? '').'/fecha/'.str_replace('/','-',date('d/m/Y')).'
Línea de atención telefónica: 902 10 10 10
Pagina web para el seguimiento: www.seur.com

Asi mismo recordarle que se ha emitido la factura en formato PDF de su pedido

Si desea descargarla, deberá entrar en Su Cuenta y acceder a historial de pedidos, después pulse ver datos del pedido y al final del mismo podrá ver el enlace para descargar la factura.');
define('TABLE_HEADING_COMENTARIO_5', 'Estimado cliente:
Le comunicamos que hoy ha sido enviado su pedido por la agencia de transportes SEUR
Forma de envio: mensajeria SEUR contrareembolso
Codigo de seguimiento: A-
Línea de atención telefónica: 902 10 10 10
Pagina web para el seguimiento: www.seur.com

Asi mismo recordarle que se ha emitido la factura en formato PDF de su pedido

Si desea descargarla, deberá entrar en Su Cuenta y acceder a historial de pedidos, después pulse ver datos del pedido y al final del mismo podrá ver el enlace para descargar la factura.');
define('TABLE_HEADING_COMENTARIO_6', 'Estimado cliente:
Le comunicamos que hoy ha sido enviado su pedido por la agencia de transportes TNT
Forma de envio: mensajeria TNT
Nº de Expedición: 
Línea de atención telefónica: 902 111 868
Pagina web para el seguimiento:
 http://www.tnt.com/express/es_es/site/home.html
 
 Asi mismo recordarle que se ha emitido la factura en formato PDF de su pedido
 
 Si desea descargarla, deberá entrar en Su Cuenta y acceder a historial de pedidos, después pulse ver datos del pedido y al final del mismo podrá ver el enlace para descargar la factura.');
define('TABLE_HEADING_COMENTARIO_7', 'Estimado cliente:
Le comunicamos que hoy ha sido enviado su pedido por la agencia de transportes TNT
Forma de envio: mensajeria TNT contrareembolso
Nº de Expedición: 
Línea de atención telefónica: 902 111 868
Pagina web para el seguimiento:
 http://www.tnt.com/express/es_es/site/home.html
 
 Asi mismo recordarle que se ha emitido la factura en formato PDF de su pedido
 
 Si desea descargarla, deberá entrar en Su Cuenta y acceder a historial de pedidos, después pulse ver datos del pedido y al final del mismo podrá ver el enlace para descargar la factura.');
define('TABLE_HEADING_COMENTARIO_16', 'Estimado cliente:
Le comunicamos que hoy ha sido enviado su pedido por la agencia de transportes TYPSA
Forma de envio: mensajeria TIPS@
Línea de atención telefónica: 902 10 10 47
Para hacer el seguimiento de su pedido pulse en el siguiente enlace:
  http://www.tip-sa.com/cliente/datos.php?id=02800108513'.($_GET['oID'] ?? '').$postcode_envio.'
  
  Asi mismo recordarle que se ha emitido la factura en formato PDF de su pedido
  
  Si desea descargarla, deberá entrar en Su Cuenta y acceder a historial de pedidos, después pulse ver datos del pedido y al final del mismo podrá ver el enlace para descargar la factura.');
define('TABLE_HEADING_COMENTARIO_17', 'Estimado cliente:
Le comunicamos que hoy ha sido enviado su pedido por la agencia de transportes TYPSA
Forma de envio: mensajeria TIPS@ contrareembolso
Nº de Expedición: 
Línea de atención telefónica: 902 10 10 47
Para hacer el seguimiento de su pedido pulse en el siguiente enlace:
  http://www.tip-sa.com/cliente/datos.php?id=02800108513'.($_GET['oID'] ?? '').$postcode_envio.'
  
  Asi mismo recordarle que se ha emitido la factura en formato PDF de su pedido
  
  Si desea descargarla, deberá entrar en Su Cuenta y acceder a historial de pedidos, después pulse ver datos del pedido y al final del mismo podrá ver el enlace para descargar la factura.');
define('TABLE_HEADING_COMENTARIO_18', 'Estimado cliente:
Le comunicamos que hoy ha sido enviado su pedido por la agencia de transportes GLS
Forma de envio: mensajeria GLS
Línea de atención telefónica: 902 102 735
Para hacer el seguimiento de su pedido pulse en el siguiente enlace:
http://www.gls-group.eu/276-I-PORTAL-WEB/content/GLS/ES01/ES/5004.htm?txtAction=71010&un=7240003784&pw=1234&rf=&crf='.($_GET['oID'] ?? '').'&lc=ES&no=7240003784

Asi mismo recordarle que se ha emitido la factura en formato PDF de su pedido

Si desea descargarla, deberá entrar en Su Cuenta y acceder a historial de pedidos, después pulse ver datos del pedido y al final del mismo podrá ver el enlace para descargar la factura.');
define('TABLE_HEADING_COMENTARIO_19', 'Estimado cliente:
Le comunicamos que hoy ha sido enviado su pedido por la agencia de transportes GLS
Forma de envio: mensajeria GLS contrareembolso
Nº de Expedición: 
Línea de atención telefónica: 902 102 735
Para hacer el seguimiento de su pedido pulse en el siguiente enlace:
 http://www.gls-group.eu/276-I-PORTAL-WEB/content/GLS/ES01/ES/5004.htm?txtAction=71010&un=7240003784&pw=1234&rf=&crf='.($_GET['oID'] ?? '').'&lc=ES&no=7240003784
 
 Asi mismo recordarle que se ha emitido la factura en formato PDF de su pedido
 
 Si desea descargarla, deberá entrar en Su Cuenta y acceder a historial de pedidos, después pulse ver datos del pedido y al final del mismo podrá ver el enlace para descargar la factura.');
define('TABLE_HEADING_COMENTARIO_21', 'Estimado cliente:
Le comunicamos que hoy ha sido enviado su pedido al punto Kiala solicitado
Forma de envio: Kiala
Para hacer el seguimiento de su pedido pulse en el siguiente enlace:
 http://trackandtrace.kiala.com/search?countryid=ES&language=es&dspid=34600140&dspparcelid='.($_GET['oID'] ?? '').'
 
 Asi mismo recordarle que se ha emitido la factura en formato PDF de su pedido
 
 Si desea descargarla, deberá entrar en Su Cuenta y acceder a historial de pedidos, después pulse ver datos del pedido y al final del mismo podrá ver el enlace para descargar la factura.');
define('TABLE_HEADING_COMENTARIO_22', 'Estimado cliente:
Le comunicamos que hoy ha sido enviado su pedido al punto Kiala solicitado
Forma de envio: Kiala contrarrembolso
Para hacer el seguimiento de su pedido pulse en el siguiente enlace:
 http://trackandtrace.kiala.com/search?countryid=ES&language=es&dspid=34600140&dspparcelid='.($_GET['oID'] ?? '').'
 
 Asi mismo recordarle que se ha emitido la factura en formato PDF de su pedido
 
 Si desea descargarla, deberá entrar en Su Cuenta y acceder a historial de pedidos, después pulse ver datos del pedido y al final del mismo podrá ver el enlace para descargar la factura.');
define('TABLE_HEADING_COMENTARIO_23', 'Estimado cliente:

Le comunicamos que hoy ha sido enviado su pedido por la agencia de transportes Correos Express

Forma de envio: mensajeria Correos Express
Para hacer el seguimiento de su pedido pulse en el siguiente enlace: https://www.correosexpress.com/url/v?s=F' . ($_GET['oID'] ?? '') . '&cp=<CP>
Línea de atención telefónica: 902 1 22 333
Pagina web para el seguimiento: https://www.correosexpress.com/

Asi mismo recordarle que se ha emitido la factura en formato PDF de su pedido

Si desea descargarla, deberá entrar en Su Cuenta y acceder a historial de pedidos, después pulse ver datos del pedido y al final del mismo podrá ver el enlace para descargar la factura.');

?>