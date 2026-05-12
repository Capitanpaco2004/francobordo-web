<?php
/*
  $Id: edit_orders.php v5.0 08/05/2007 djmonkey1 Exp $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2007 osCommerce

  Released under the GNU General Public License
*/

/* Traducido por ReyBlack  */

define('HEADING_TITLE', 'Editar Pedido #%s de %s');
define('ADDING_TITLE', 'Añadir Producto(s) al Pedido #%s');

define('ENTRY_UPDATE_TO_CC', '(Actualizar a ' . ORDER_EDITOR_CREDIT_CARD . ' para ver los campos de Tarjeta de Credito.)');
define('TABLE_HEADING_COMMENTS', 'Comentarios');
define('TABLE_HEADING_STATUS', 'Estado');
define('TABLE_HEADING_NEW_STATUS', 'Nuevo Estado');
define('TABLE_HEADING_ACTION', 'Accion');
define('TABLE_HEADING_DELETE', 'Borrar?');
define('TABLE_HEADING_QUANTITY', 'Ctdad.');
define('TABLE_HEADING_PRODUCTS_MODEL', 'Modelo');
define('TABLE_HEADING_PRODUCTS', 'Productos');
define('TABLE_HEADING_TAX', 'Impuestos');
define('TABLE_HEADING_TOTAL', 'Total');
define('TABLE_HEADING_BASE_PRICE', 'Prec. base');
define('TABLE_HEADING_UNIT_PRICE', 'Prec. excl.');
define('TABLE_HEADING_UNIT_PRICE_TAXED', 'Prec. incl.');
define('TABLE_HEADING_TOTAL_PRICE', 'Total excl.');
define('TABLE_HEADING_TOTAL_PRICE_TAXED', 'Total incl.');
define('TABLE_HEADING_OT_TOTALS', 'Total Pedido:');
define('TABLE_HEADING_OT_VALUES', 'Valor:');
define('TABLE_HEADING_SHIPPING_QUOTES', 'Observaciones de envio:');
define('TABLE_HEADING_NO_SHIPPING_QUOTES', 'No hay observaciones de envio para mostrar!');

define('TABLE_HEADING_CUSTOMER_NOTIFIED', 'Cliente <br>Notificado');
define('TABLE_HEADING_DATE_ADDED', 'Fecha Añadido');

define('ENTRY_CUSTOMER', 'Cliente');
define('ENTRY_NAME', 'Nombre:');
define('ENTRY_CITY_STATE', 'Ciudad, Provincia:');
define('ENTRY_SHIPPING_ADDRESS', 'Direccion de Envio');
define('ENTRY_BILLING_ADDRESS', 'Direccion de Facturacion');
define('ENTRY_PAYMENT_METHOD', 'Forma de Pago');
define('ENTRY_CREDIT_CARD_TYPE', 'Tipo Tarjeta:');
define('ENTRY_CREDIT_CARD_OWNER', 'Titular Tarjeta:');
define('ENTRY_CREDIT_CARD_NUMBER', 'Numero de Tarjeta:');
define('ENTRY_CREDIT_CARD_EXPIRES', 'Tarjeta valida hasta:');
define('ENTRY_SUB_TOTAL', 'Sub-Total:');
define('ENTRY_TYPE_BELOW', 'Escriba debajo');

//the definition of ENTRY_TAX is important when dealing with certain tax components and scenarios
define('ENTRY_TAX', 'Impuestos');
//do not use a colon (:) in the defintion, ie 'VAT' is ok, but 'VAT:' is not

define('ENTRY_SHIPPING', 'Envio:');
define('ENTRY_TOTAL', 'Total:');
define('ENTRY_STATUS', 'Estado:');
define('ENTRY_NOTIFY_CUSTOMER', 'Notificar al Cliente:');
define('ENTRY_NOTIFY_COMMENTS', 'Enviar Comentarios:');
define('ENTRY_CURRENCY_TYPE', 'Moneda');
define('ENTRY_CURRENCY_VALUE', 'Valor Moneda');

define('TEXT_INFO_PAYMENT_METHOD', 'Forma de Pago:');
define('TEXT_NO_ORDER_PRODUCTS', 'Este pedido no contiene productos');
define('TEXT_ADD_NEW_PRODUCT', 'Añadir productos');
define('TEXT_PACKAGE_WEIGHT_COUNT', 'Peso Paquete: %s  |  Cantidad Productos: %s');

define('TEXT_STEP_1', '<b>Paso 1:</b>');
define('TEXT_STEP_2', '<b>Paso 2:</b>');
define('TEXT_STEP_3', '<b>Paso 3:</b>');
define('TEXT_STEP_4', '<b>Paso 4:</b>');
define('TEXT_SELECT_CATEGORY', '- Selecciona una categoria de la lista -');
define('TEXT_PRODUCT_SEARCH', '<b>- O introduce un termino de busqueda en el campo de abajo para ver potenciales coincidencias -</b>');
define('TEXT_ALL_CATEGORIES', 'Todas las Categorias/Todos los Productos');
define('TEXT_SELECT_PRODUCT', '- Seleccione un Producto -');
define('TEXT_BUTTON_SELECT_OPTIONS', 'Seleccione Estas Opciones');
define('TEXT_BUTTON_SELECT_CATEGORY', 'Seleccione Esta Categoria');
define('TEXT_BUTTON_SELECT_PRODUCT', 'Seleccione Este Producto');
define('TEXT_SKIP_NO_OPTIONS', '<em>Sin Opciones - Saltar esto...</em>');
define('TEXT_QUANTITY', 'Cantidad:');
define('TEXT_BUTTON_ADD_PRODUCT', 'Añadir al Pedido');
define('TEXT_CLOSE_POPUP', '<u>Cerrar</u> [x]');
define('TEXT_ADD_PRODUCT_INSTRUCTIONS', 'Siga añadiendo productos hasta que termine.<br>Entonces cierre esta pestaña/ventana, regrese a la pestaña/ventana principal, y presionne el Boton "Actualizar".');
define('TEXT_PRODUCT_NOT_FOUND', '<b>Producto no Encontrado<b>');
define('TEXT_SHIPPING_SAME_AS_BILLING', 'Direccion de Envio igual que la de Facturacion');
define('TEXT_BILLING_SAME_AS_CUSTOMER', 'Direccion de Facturacion igual a la direccion del Cliente');

define('IMAGE_ADD_NEW_OT', 'Insertar un Nuevo Pedido despues de este');
define('IMAGE_REMOVE_NEW_OT', 'Borrar esta Pedido por completo');
define('IMAGE_NEW_ORDER_EMAIL', 'Enviar una nueva Confirmacion de Pedido por email');

define('TEXT_NO_ORDER_HISTORY', 'Historial de Pedidos No Disponible');

define('PLEASE_SELECT', 'Seleccione, por favor');

define('EMAIL_SEPARATOR', '------------------------------------------------------');
define('EMAIL_TEXT_SUBJECT', 'Su pedido ha sido actualizado');
define('EMAIL_TEXT_ORDER_NUMBER', 'Numero de Pedido:');
define('EMAIL_TEXT_INVOICE_URL', 'Factura Detallada:');
define('EMAIL_TEXT_DATE_ORDERED', 'Fecha de Pedido:');
define('EMAIL_TEXT_STATUS_UPDATE', 'Muchas gracias por confiarnos sus pedidos!' . "\n\n" . 'El estado de su pedido ha sido actualizado.' . "\n\n" . 'Nuevo estado: %s' . "\n\n");
define('EMAIL_TEXT_STATUS_UPDATE2', 'Si tiene alguna question al respecto, no dude en ponerse en contacto con nosotros mediante email o telefono.' . "\n\n" . 'Reciba un cordial saludo del Equipo de ' . STORE_NAME . "\n");
define('EMAIL_TEXT_COMMENTS_UPDATE', 'Los comentarios de su pedido son' . "\n\n%s\n\n");

define('ERROR_ORDER_DOES_NOT_EXIST', 'Error: El Pedido %s No Existe.');
define('ERROR_NO_ORDER_SELECTED', 'No ha seleccionado ningun pedido para editar, o el Numero de Pedido no ha sido grabado.');
define('SUCCESS_ORDER_UPDATED', 'Correcto: El pedido ha sido actualizado correctamente.');
define('SUCCESS_EMAIL_SENT', 'Completado: El pedido ha sido actualizado y un email con la nueva informacion le ha sido enviado.');

//the hints
define('HINT_UPDATE_TO_CC', 'Seleccione la Forma de Pago a ' . ORDER_EDITOR_CREDIT_CARD . ' y el resto de campos se mostraran automaticamente.  Los campos de las Tarjetas de Credito estan ocultos si otra forma de pago es seleccionada.  El nombre de la forma de pago que, cuando se selecciona, mostrara los campos de la Tarjeta de Credito es configurable en el area de Configuracion del Editor de Pedidos de la Administracion.');
define('HINT_UPDATE_CURRENCY', 'Cambiar la moneda modificara los costes de envio y los totales del pedido deberan recalcularse.');
define('HINT_SHIPPING_ADDRESS', 'Si cambia la provincia/estado de envio, codigo postal o pais se le dara la opcion de recalcular los totales del pedido y los costes de envio.');
define('HINT_TOTALS', 'Puede hacer descuentos añadiendo valores negativoss. Subtotal, total impuestos y Total Pedido no son editables. Cuando añada componentes en el pedido mediante AJAX asegurese de introducir el titulo primero o el codigo no reconocera la entrada (por ejemplo, un componente sin titulo es borrado del pedido).');
define('HINT_PRESS_UPDATE', 'Por favor, pulse "Actualizar" para salvar todos los cambios.');
define('HINT_BASE_PRICE', 'El Precio (base) es el precio de los productos sin atributos (por ejmeplo, el precio de catalogo de un producto)');
define('HINT_PRICE_EXCL', 'El Precio (excl) es el Precio Base mas el precio de los atributos, si existieran');
define('HINT_PRICE_INCL', 'El Precio (incl) es el Precio Excl. incluyendo los impuestos');
define('HINT_TOTAL_EXCL', 'El Total (excl) es el Precio Excl. por la cantidad');
define('HINT_TOTAL_INCL', 'El Total (incl) es el Precio Excl. incluyendo impuestos y cantidad');
//end hints

//new order confirmation email- this is a separate email from order status update
define('ENTRY_SEND_NEW_ORDER_CONFIRMATION', 'New order confirmation:');
define('EMAIL_TEXT_DATE_MODIFIED', 'Date Modified:');
define('EMAIL_TEXT_PRODUCTS', 'Products');
define('EMAIL_TEXT_DELIVERY_ADDRESS', 'Delivery Address');
define('EMAIL_TEXT_BILLING_ADDRESS', 'Billing Address');
define('EMAIL_TEXT_PAYMENT_METHOD', 'Payment Method');
// If you want to include extra payment information, enter text below (use <br> for line breaks):
//define('EMAIL_TEXT_PAYMENT_INFO', ''); //why would this be useful???
// If you want to include footer text, enter text below (use <br> for line breaks):
define('EMAIL_TEXT_FOOTER', '');
//end email

//add-on for downloads
define('ENTRY_DOWNLOAD_COUNT', 'Descargar #');
define('ENTRY_DOWNLOAD_FILENAME', 'Archivo');
define('ENTRY_DOWNLOAD_MAXDAYS', 'Expira en (dias)');
define('ENTRY_DOWNLOAD_MAXCOUNT', 'Descargas disponibles');

//add-on for Ajax
define('AJAX_CONFIRM_PRODUCT_DELETE', 'Esta seguro de que quiere eliminar este producto del pedido?');
define('AJAX_CONFIRM_COMMENT_DELETE', 'Esta seguro de que quiere eliminar este comentario del historial de estados del pedido?');
define('AJAX_MESSAGE_STACK_SUCCESS', 'Correcto! \' + %s + \' ha sido actualizado!');
define('AJAX_CONFIRM_RELOAD_TOTALS', 'Ha cambiado alguna informacion de envio. Quiere recalcular los totales del pedido y los gastos de envio?');
define('AJAX_CANNOT_CREATE_XMLHTTP', 'No se puede crear la peticion XMLHTTP');
define('AJAX_SUBMIT_COMMENT', 'Enviar nuevos comentarios y/o estados');
define('AJAX_NO_QUOTES', 'No hay gastos de envio para mostar.');
define('AJAX_SELECTED_NO_SHIPPING', 'Ha seleccionado un metodo de envio para este pedido pero parece que no hay ninguno almacenado en la base de datos.  Le gustaria añadir este gasto de envio en el pedido?');
define('AJAX_RELOAD_TOTALS', 'El nuevo componente de envio ha sido volcado en la base de datos, pero los totales no han sido recalculados.  Pulse OK ahora para recalcular los totales del pedido.  Si su conexion es lenta espere a que todos los componentes se carguen antes de pulsar OK.');
define('AJAX_NEW_ORDER_EMAIL', 'Esta seguro de que quiere enviar una nueva confirmacion de pedido por email?');
define('AJAX_INPUT_NEW_EMAIL_COMMENTS', 'Por favor, introduzca sus comentarios aqui.  Si esta todo correcto, deje en blanco este apartado.  Por favor, recuerde cuando teclee que pulsando "enter" mantendrá los comentarios tal cual estuvieran antes de pulsar esa tecla.  No es posible introducir saltos de linea.');
define('AJAX_SUCCESS_EMAIL_SENT', 'Correcto!  Un nuevo email de confirmacion ha sido enviado a %s');
define('AJAX_WORKING', 'En proceso, por favor espere....');
define('EDIT_ORDERS_INFO_SHIPPING_CHANGE', 'Atención: El pedido cambio de país de envío, ten en cuenta que tendrás que ajustar el Impuesto correspondiente al país de origen o seleccionar otro método de envío para la zona, pulsando en <span class="trojo" id="changeShiping" style="cursor: pointer;">Cambiar método envío</span>');
?>
