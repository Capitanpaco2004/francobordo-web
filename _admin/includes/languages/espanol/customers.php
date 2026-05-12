<?php
/*
  $Id: customers_improved.php, v1.5 2008/11/05 13:54:44 kremit Exp $

Customers Improved v1.5

Copyright (c) 2005 Wesley Haines
<kremit AT wrpn.net>, http://wrpn.net/


  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2002 osCommerce

  Released under the GNU General Public License
*/

define('HEADING_TITLE', 'Listado de clientes');
if (!defined('HEADING_TITLE_SEARCH')) define('HEADING_TITLE_SEARCH', 'Buscar:');
define('HEADING_TITLE_EDIT', 'Editar cliente');

define('CUSTOMERS_NEW_CUSTOMER', 'Crear cliente');
define('CUSTOMERS_EXPORT_CUSTOMERS', 'Exportar clientes');
define('CUSTOMERS_FILTER', 'Filtrar');
define('CUSTOMERS_CLEAN_FILTER', 'Limpiar filtro');

define('TABLE_HEADING_FIRSTNAME', 'Nombre');
define('TABLE_HEADING_LASTNAME', 'Apellidos');
define('TABLE_HEADING_ACCOUNT_CREATED', 'Fecha Reg.');
define('TABLE_HEADING_LAST_LOGIN', '&Uacute;lt. acceso');
define('TABLE_HEADING_NUM_LOGINS', 'Veces');
define('TABLE_HEADING_LOCATION', 'Ubicaci&oacute;n');
define('TABLE_HEADING_TELEPHONE', 'Tel&eacute;fono');
define('TABLE_HEADING_DOB', 'Edad');
define('TABLE_HEADING_ACTIONS', 'Acci&oacute;n');
define('TABLE_HEADING_NEWSLETTER', 'Bolet&iacute;n');
define('SET_NEWSLETTER_YES', 'Si');
define('SET_NEWSLETTER_NO', 'No');

define('TEXT_INFO_HEADING_DELETE_CUSTOMER', 'Eliminar Cliente');
define('TEXT_DELETE_CUSTOMER', '¿Seguro que quieres borrar la cuenta de este cliente?');
define('TEXT_DELETE_ACCOUNT', 'Si, BORRA esta cuenta!');
define('TEXT_DELETE_ACCOUNT_CANCEL', 'NO, NO borres la cuenta!');
define('TEXT_VOID', '-');

// Admin edit any customer address
define('TEXT_INFO_HEADING_DELETE_ADDRESS', 'Eliminar Direcci&oacute;n');
define('TEXT_DELETE_CUSTOMER_ADDRESS', '¿Seguro que quieres borrar esta Direcci&oacute;n de este cliente?');
define('TEXT_DELETE_ADDRESS', 'Si, BORRA esta Direcci&oacute;n!');
define('TEXT_DELETE_ADDRESS_CANCEL', 'NO, NO borres la Direcci&oacute;n!');

define('SELECT_ADDRESS', 'Seleccionar direcci&oacute;n del cliente a Editar: ');
define('DELETE_ADDRESS', 'Borrar esta direcci&oacute;n del cliente');
define('TEXT_DELETE_MAIN_ADDRESS', 'Esta es la Direcci&oacute;n Principal del cliente (no se puede borrar)');
define('TEXT_DELETE_ADDRESS_ERROR', 'Error, la direcci&oacute;n no se puede borrar!');
// Admin edit any customer address
define('TEXT_DATE_ACCOUNT_CREATED', 'Cuenta Creada:');
define('TEXT_DATE_ACCOUNT_LAST_MODIFIED', 'Ultima Modificaci&oacute;n:');
define('TEXT_INFO_DATE_LAST_LOGON', 'Ultima Visita:');
define('TEXT_INFO_NUMBER_OF_LOGONS', 'N&uacute;mero de visitas:');
define('TEXT_INFO_COUNTRY', 'Pa&iacute;s:');
define('TEXT_INFO_NUMBER_OF_REVIEWS', 'N&uacute;mero de Comentarios:');
define('TEXT_DELETE_INTRO', 'Seguro que desea eliminar este cliente?');
define('TEXT_DELETE_REVIEWS', 'Eliminar %s comentario(s)');
if (!defined('TEXT_INFO_HEADING_DELETE_CUSTOMER')) define('TEXT_INFO_HEADING_DELETE_CUSTOMER', 'Eliminar Cliente');
define('TYPE_BELOW', 'Escriba debajo');
define('PLEASE_SELECT', 'Seleccione');
define('TABLE_HEADING_CUSTOMERS_GROUPS', 'Grupo&#160;Cliente');
define('TABLE_HEADING_REQUEST_AUTHENTICATION', 'RA');
define('ENTRY_CUSTOMERS_PAYMENT_SET', 'Configurar modulos de pago para el cliente');
define('ENTRY_CUSTOMERS_PAYMENT_DEFAULT', 'Usar configuraciones desde el Grupo o Configuración');
define('ENTRY_CUSTOMERS_PAYMENT_SET_EXPLAIN', 'Si escoges <b><i>Configurar modulos de pago para el cliente</i></b> pero no escoges ninguno, la configuracion por defecto (configuraciones desde el Grupo o Configuración) estará todavia en uso.');
define('ENTRY_CUSTOMERS_SHIPPING_SET', 'Configurar modulos de envio para el cliente');
define('ENTRY_CUSTOMERS_SHIPPING_DEFAULT', 'Usar configuraciones desde el Grupo o Configuración');
define('ENTRY_CUSTOMERS_SHIPPING_SET_EXPLAIN', 'Si escoges <b><i>Configurar modulos de envio para el cliente</i></b> pero no escoges ninguno, la configuracion por defecto (configuraciones desde el Grupo o Configuración) estará todavia en uso.');
define('ENTRY_CUSTOMERS_ORDER_TOTAL_SET', 'Establecer módulos de total de pedidos para el cliente');
define('ENTRY_CUSTOMERS_ORDER_TOTAL_DEFAULT', 'Usar la configuración de Grupo o Configuración');
define('ENTRY_CUSTOMERS_ORDER_TOTAL_SET_EXPLAIN', 'Si escoges <b><i>Set order total modules for the customer</i></b> pero no escoges ninguno, la configuracion por defecto (configuraciones desde el Grupo o Configuración) estará todavia en uso.');
define('TABLE_HEADING_ACTION', 'Acción');
define('HEADING_TITLE_CUSTOMERS_TAX_RATES_EXEMPT', 'Cliente exento de tasas impositivas específicas');
define('ENTRY_CUSTOMERS_TAX_RATES_EXEMPT', 'Tasas de impuestos exentas del cliente');
define('ENTRY_CUSTOMERS_TAX_RATES_DEFAULT', 'Utilice la configuración del grupo o la configuración (según la zona)');
define('ENTRY_CUSTOMERS_TAX_RATES_EXEMPT_EXPLAIN', 'Si escoges <b><i>Tasas de impuestos exentas del cliente</i></b> pero no escoges ninguno, la configuracion por defecto (configuraciones desde el Grupo o Configuración) estará todavia en uso.<br />If this customer is in a group that is "Tax Exempt", none of these settings will have any effect.');
define('SORT_BY_COMPANYNAME', 'Ordenar por Nombre empresa --> A-B-C Desde arriba ');
define('SORT_BY_COMPANYNAME_DESC', 'Ordenar por Nombre empresa --> Z-X-Y Desde arriba ');
define('SORT_BY_FIRSTNAME', 'Ordenar por Nombre ascending --> A-B-C Desde arriba ');
define('SORT_BY_FIRSTNAME_DESC', 'Ordenar por Nombre descending --> Z-X-Y Desde arriba ');
define('SORT_BY_LASTNAME', 'Ordenar por Apellido ascending --> A-B-C Desde arriba ');
define('SORT_BY_LASTNAME_DESC', 'Ordenar por Apellido descending --> Z-Y-X Desde arriba ');
define('SORT_BY_CUSTOMER_GROUP', 'Ordenar por Grupo&#160;Cliente ascending --> A-B-C Desde arriba ');
define('SORT_BY_CUSTOMER_GROUP_DESC', 'Ordenar por Grupo&#160;Cliente descending --> Z-X-Y Desde arriba ');
define('SORT_BY_ACCOUNT_CREATED', 'Ordenar por Cuenta Creada ascending  --> 1-2-3 Desde arriba ');
define('SORT_BY_ACCOUNT_CREATED_DESC', 'Ordenar por Cuenta Creada descending  --> 3-2-1 Desde arriba ');
define('SORT_BY_RA', 'Ordenar por Solicitar autorización --> RA first (to Top) ');
define('SORT_BY_RA_DESC', 'Ordenar por Solicitar autorización --> RA last (to Bottom) ');

define('CUSTOMERS_SEARCH_FILTER_NO_RESULT', 'No existen clientes con el filtro seleccionado');
define('CUSTOMERS_FILTER_SELECT', 'Seleccione');
define('CUSTOMERS_FILTER_CUSTOMERS_GROUP', 'Grupo de cliente');
define('CUSTOMERS_FILTER_CUSTOMERS_TYPE', 'Tipo cliente');
define('CUSTOMERS_FILTER_CUSTOMERS_STATUS', 'Estado cliente');

define('CUSTOMERS_TABLE_NAME', 'Nombre');
define('CUSTOMERS_TABLE_SURNAME', 'Apellidos');
define('CUSTOMERS_TABLE_COMPANY', 'Empresa');
define('CUSTOMERS_TABLE_CUSTOMERS_GROUP', 'Grupo de cliente');
define('CUSTOMERS_TABLE_TYPE', 'Tipo de cliente');
define('CUSTOMERS_TABLE_STATUS', 'Estado del cliente');
define('CUSTOMERS_TABLE_DATE_REGISTER', 'Fecha Reg.');
define('CUSTOMERS_TABLE_LAST_ACCESS', 'Últ. acceso');
define('CUSTOMERS_TABLE_TIMES', 'Veces');
define('CUSTOMERS_TABLE_LOCATION', 'Ubicación');
define('CUSTOMERS_TABLE_NEWSLETTER', 'Boletín');
define('CUSTOMERS_TABLE_BORN', 'Nacimiento');
define('CUSTOMERS_TABLE_ORDERS_QTY', 'Cantidad de pedidos');
define('CUSTOMERS_TABLE_LAST_ORDER', 'Último pedido');
define('CUSTOMERS_TABLE_ACTIONS', 'Acciones');

define('CUSTOMERS_ACTIONS_EDIT', 'Editar');
define('CUSTOMERS_ACTIONS_DELETE', 'Eliminar');
define('CUSTOMERS_ACTIONS_DELETE_CONFIRM', '¿Realmente deseas borrar el cliente?');
define('CUSTOMERS_ACTIONS_SEE_ORDERS', 'Ver Pedidos');
define('CUSTOMERS_ACTIONS_NEW_ORDER', 'Crear Pedido');
define('CUSTOMERS_ACTIONS_CHANGE_PASS', 'Cambiar Contraseña');
define('CUSTOMERS_ACTIONS_CONNECT_AS', 'Conectar como...');
define('CUSTOMERS_ACTIONS_DELETE_NOTE_CONFIRM', '¿Realmente deseas eliminar la nota?');

define('CUSTOMERS_TOTAL_BY_GROUPS', 'Total de clientes por grupos');

define('CUSTOMERS_EDIT_SUCCESS', 'El cliente %s se ha editado correctamente');
define('CUSTOMERS_DELETE_SUCCESS', 'El cliente #%s se ha eliminado correctamente');
define('CUSTOMERS_ERROR_NAME', 'El nombre del cliente no puede tener menos de %s caracteres.');
define('CUSTOMERS_ERROR_SURNAME', 'El apellido del cliente no puede tener menos de %s caracteres.');
define('CUSTOMERS_ERROR_EMAIL', 'El email del cliente no puede tener menos de %s caracteres.');
define('CUSTOMERS_ERROR_EMAIL_NOT_VALID', 'El email %s no es un email valido.');
define('CUSTOMERS_ERROR_EMAIL_IN_USE', 'El email %s ya esta en uso por el cliente #%s.');
define('CUSTOMERS_ERROR_TELEPHONE', 'El telefono del cliente no puede tener menos de %s caracteres.');
define('CUSTOMERS_ERROR_ADDRESS_NAME', 'El nombre del cliente en la dirección #%s no puede tener menos de %s caracteres.');
define('CUSTOMERS_ERROR_ADDRESS_SURNAME', 'El apellido del cliente en la dirección #%s no puede tener menos de %s caracteres.');
define('CUSTOMERS_ERROR_ADDRESS', 'La dirección del cliente en la dirección #%s no puede tener menos de %s caracteres.');
define('CUSTOMERS_ERROR_ADDRESS_CP', 'El código postal del cliente en la dirección #%s no es valido.');
define('CUSTOMERS_ERROR_ADDRESS_CITY', 'La ciudad del cliente en la dirección #%s no puede tener menos de %s caracteres.');
define('CUSTOMERS_ERROR_COUNTRY', 'Debes seleccionar un pais para la dirección #%s.');
define('CUSTOMERS_RGPD_MODIFIED_BY_ADMIN', 'Cuenta modificada por administrador. Aceptación telefónica.');
define('CUSTOMERS_NO_ORDERS', 'El cliente no ha realizado ningun pedido.');
define('CUSTOMERS_MENU_DATA', 'Datos');
define('CUSTOMERS_MENU_ADDRESS', 'Direcciones');
define('CUSTOMERS_MENU_ORDERS', 'Pedidos');
define('CUSTOMERS_MENU_OPTIONS', 'Opciones');
define('CUSTOMERS_MENU_MODULES', 'Módulos');
define('CUSTOMERS_MENU_NOTES', 'Notas');
define('CUSTOMERS_ADDRESS_MAIN_ADDRESS', 'Dirección principal');
define('CUSTOMERS_ADDRESS_ADDRESS', 'Dirección');
define('CUSTOMERS_ADDRESS_DELETE', 'Eliminar dirección');
define('CUSTOMERS_ORDERS_LIST', 'Listado de pedidos');
define('CUSTOMERS_ORDERS_NUMBER', 'Núm.');
define('CUSTOMERS_ORDERS_TOTAL', 'Total Pedido');
define('CUSTOMERS_ORDERS_DATE_PURCHASED', 'Fecha de Compra');
define('CUSTOMERS_ORDERS_DATE_PAYMENT', 'F. Pago');
define('CUSTOMERS_ORDERS_STATUS', 'Estado');
define('CUSTOMERS_ORDERS_ACTIONS', 'Acciones');
define('CUSTOMERS_ORDERS_EDIT_ORDER', 'Editar pedido');
define('CUSTOMERS_ORDERS_VIEW_ORDER', 'Ver pedido');
define('CUSTOMERS_MODULES_PAYMENT_MODULE', 'Módulos de Pago');
define('CUSTOMERS_MODULES_SHIPPING_MODULE', 'Módulos de Envío');
define('CUSTOMERS_MODULES_TOTALIZATION', 'Módulos de totalización');
define('CUSTOMERS_MODULES_EXCEMPT_TAXES', 'Excenciones para ciertos tipos de impuestos');
define('CUSTOMERS_NOTES_FIRST_STATUS', 'El primer estado que se muestra sera el estado actual del cliente.');
define('CUSTOMERS_NOTES_ADD_NOTE', 'Añadir nota');
define('CUSTOMERS_NOTES_NOTE', 'Nota');
define('CUSTOMERS_NOTES_STATUS', 'Estado');
define('CUSTOMERS_NOTES_NO_NOTES', 'No existe ninguna nota. Inserta alguna nota para que aparezca en la lista.');
define('CUSTOMERS_NOTES_INSERT_NOTE', 'Insertar nota');
define('CUSTOMERS_VIEW_IAE', 'Ver IAE');
define('CUSTOMERS_MEMBER_APPROVE', 'Aprobar Cliente');

?>