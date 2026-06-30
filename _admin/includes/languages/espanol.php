<?php
/*
  $Id: espanol.php 1739 2007-12-20 00:52:16Z hpdl $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2007 osCommerce

  Released under the GNU General Public License
*/

// look in your $PATH_LOCALE/locale directory for available locales..
// on RedHat6.0 I used 'es_ES'
// on FreeBSD 4.0 I use 'es_ES.ISO_8859-1'
// this may not work under win32 environments..
//setlocale(LC_TIME, 'es_ES.ISO_8859-1');
setlocale( LC_CTYPE, 'C' );

define('DATE_FORMAT_SHORT', '%d/%m/%Y');  // this is used for strftime()
define('DATE_FORMAT_SHORT_NEW', 'd/m/Y');  // this is used for strftime()
define('DATE_FORMAT_LONG', '%d/%m/%Y'); // this is used for strftime()
define('DATE_FORMAT', 'd/m/Y');  // this is used for date()
define('PHP_DATE_TIME_FORMAT', 'd/m/Y H:i:s'); // this is used for date()
define('DATE_TIME_FORMAT', DATE_FORMAT_SHORT . ' %H:%M:%S');

////
// Return date in raw format
// $date should be in format mm/dd/yyyy
// raw date is in format YYYYMMDD, or DDMMYYYY
function tep_date_raw($date, $reverse = false) {
  if ($reverse) {
    return substr((string) $date, 0, 2) . substr((string) $date, 3, 2) . substr((string) $date, 6, 4);
  } else {
    return substr((string) $date, 6, 4) . substr((string) $date, 3, 2) . substr((string) $date, 0, 2);
  }
}

// return a dbase formatted date for ddmmyyyy format adjust if you use the mmddyyyy format
function tep_store_date ($date) {
   return substr((string) $date, 6, 4) . '-' . substr((string) $date, 3, 2) . '-' . substr((string) $date, 0, 2) . ' ' . date('H:i:s');
  }

// Global entries for the <html> tag
define('HTML_PARAMS','dir="ltr" lang="es"');

// charset for web pages and emails
define('CHARSET', 'UTF-8');

// page title
define('TITLE', 'Zona Administrador');

// header text in includes/header.php
define('HEADER_TITLE_TOP', 'Administraci&oacute;n');
define('HEADER_TITLE_SUPPORT_SITE', 'Soporte');
define('HEADER_TITLE_ONLINE_CATALOG', 'Cat&aacute;logo');
define('HEADER_TITLE_ADMINISTRATION', 'Administraci&oacute;n');

// Seleccionar idioma
define('SELECT_LANGUAGE_MODAL', '<div>SELECCIONA IDIOMA</div><small>CAMBIA AL IDIOMA QUE MÁS SE AJUSTE A TÍ.</small>');

// text for gender
define('MALE', 'Var&oacute;n');
define('FEMALE', 'Mujer');

// Si / No
define('TEXT_YES', 'Si');
define('TEXT_NO', 'No');

// text for date of birth example
define('DOB_FORMAT_STRING', 'dd/mm/aaaa');

define('BOX_SEARCH_PLACEHOLDER', 'Buscar ...');

// configuration box text in includes/boxes/configuration.php
define('BOX_HEADING_CONFIGURATION', 'Configuraci&oacute;n');
define('BOX_CONFIGURATION_MYSTORE', 'Mi Tienda');
define('BOX_CONFIGURATION_LOGGING', 'Registro');
define('BOX_CONFIGURATION_CACHE', 'Cach&eacute;');
define('BOX_CONFIGURATION_ADMINISTRATORS', 'Administrators');

// modules box text in includes/boxes/modules.php
define('BOX_HEADING_MODULES', 'M&oacute;dulos');
define('BOX_MODULES_PAYMENT', 'Pago');
define('BOX_MODULES_SHIPPING', 'Env&iacute;o');
define('BOX_MODULES_ORDER_TOTAL', 'Totalización');
define('BOX_MODULES_SHIP_2_PAY', 'Ship 2 Pay');

// categories box text in includes/boxes/catalog.php
define('BOX_HEADING_CATALOG', 'Catálogo');
define('BOX_CATALOG_CATEGORIES_PRODUCTS', 'Categorias/Productos');
define('BOX_CATALOG_CATEGORIES_PRODUCTS_ATTRIBUTES', 'Atributos/Valores');
define('BOX_CATALOG_MANUFACTURERS', 'Marcas');
define('BOX_CATALOG_REVIEWS', 'Comentarios Productos');
define('BOX_CATALOG_SPECIALS', 'Ofertas');
define('BOX_CATALOG_PRODUCTS_EXPECTED', 'Pr&oacute;ximamente');
define('BOX_CATALOG_CATEGORIES_CONFIGURE_TOP', 'Configurar inicio');
define('BOX_CATALOG_CATEGORIES_FEATURED_BANNERS', 'Banners destacados');
define('BOX_CATALOG_CATEGORIES_PRODUCTS_ON_ROOT', 'Productos en inicio');
define('BOX_CATALOG_CATEGORIES_FILTERS_LIST', 'Listado de filtros');
define('BOX_CATALOG_CATEGORIES_RELATED_PRODUCTS', 'Productos relacionados');
define('BOX_CATALOG_CATEGORIES_MASS_ORGANIZER', 'Organizador Masivo');
define('BOX_CATALOG_CATEGORIES_DIGITAL_CANON', 'Canon Digital');
define('BOX_CATALOG_CATEGORIES_EXPORT_CSV', 'Exportar/Importar productos CSV');

// customers box text in includes/boxes/customers.php
define('BOX_HEADING_CUSTOMERS', 'Clientes');
define('BOX_CUSTOMERS_CUSTOMERS', 'Clientes');
define('BOX_CUSTOMERS_ORDERS', 'Pedidos');
define('BOX_CUSTOMERS_LIST', 'Listado de clientes');
define('BOX_CUSTOMERS_NEW', 'Crear cliente');
define('BOX_CUSTOMERS_GROUPS', 'Grupos de clientes');
define('BOX_CUSTOMERS_APPROVE', 'Aprobar clientes');
define('BOX_CUSTOMERS_STATUS', 'Estado clientes');
define('BOX_CUSTOMERS_TYPE', 'Tipo de clientes');
define('BOX_CUSTOMERS_OPINIONS', 'Opiniones generales');
define('BOX_CUSTOMERS_POINTS', 'Puntos de Clientes');
define('BOX_CUSTOMERS_POINTS_PENDING', 'Puntos Pendientes');
define('BOX_CUSTOMERS_POINTS_REFERRAL', 'Puntos de Referidos');
// Pedidos
define('BOX_HEADING_ORDERS', 'Pedidos');
define('BOX_CUSTOMERS_ORDERS_LIST', 'Listado de Pedidos');
define('BOX_CUSTOMERS_ORDERS_BILL_LIST', 'Listado de Facturas');
define('BOX_CUSTOMERS_ORDERS_NEW', 'Crear Pedido');
define('BOX_CUSTOMERS_RMA_LIST', 'Listado de Devoluciones');
define('BOX_CUSTOMERS_ORDERS_CHECK', 'Salvaguardados');
define('BOX_LOCALIZATION_ORDERS_STATUS', 'Estado Pedidos');

// taxes box text in includes/boxes/taxes.php
define('BOX_HEADING_LOCATION_AND_TAXES', 'Zonas/Impuestos');
define('BOX_TAXES_COUNTRIES', 'Paises');
define('BOX_TAXES_ZONES', 'Provincias');
define('BOX_TAXES_GEO_ZONES', 'Zonas de Impuestos');
define('BOX_TAXES_GEO_ZONES_TYPE', 'Tipos de Zonas');
define('BOX_TAXES_TAX_CLASSES', 'Tipos de Impuestos');
define('BOX_TAXES_TAX_RATES', 'Impuestos');
define('BOX_TAXES_TAX_CITIES', 'Ciudades');
define('BOX_TAXES_TAX_LOCALIZATION', 'Localización');

// Estadisticas
define('BOX_HEADING_STATS', 'Estadísticas');
define('BOX_HEADING_STATS_STOCK_REPORT', 'Informe Stock (Inventario)');
define('BOX_HEADING_STATS_MONTHLY_SALES', 'Ventas Mensuales');
define('BOX_HEADING_STATS_347_REPORT', 'Informe Modelo 347');
define('BOX_HEADING_STATS_PAYMENT_METHODS', 'Formas de Pago');
define('BOX_HEADING_STATS_RECOVER_CART', 'Carritos Recuperados');
define('BOX_HEADING_STATS_NEWSLETTERS', 'Suscritos al Boletín');
define('BOX_HEADING_STATS_CUSTOMERS_WO_PURCHASES', 'Clientes sin Compras');
define('BOX_HEADING_STATS_PRODUCTS_STATS', 'Estadísticas por Productos');
define('BOX_HEADING_STATS_REPOSITION_NOTIFICATIONS', 'Notificaciones de reposición de productos para clientes');
define('BOX_HEADING_STATS_GSHOPPING_DISABLED', 'Productos Desactivados G. Shopping');
define('BOX_HEADING_STATS_CUSTOMERS_STATS', 'Estadísticas por Cliente');
// reports box text in includes/boxes/reports.php
define('BOX_HEADING_REPORTS', 'Informes');
define('BOX_REPORTS_PRODUCTS_VIEWED', 'Los Mas Vistos');
define('BOX_REPORTS_PRODUCTS_PURCHASED', 'Los Mas Comprados');
define('BOX_REPORTS_ORDERS_TOTAL', 'Total por Cliente');
define('BOX_HEADING_WARNING_MESSAGES', 'Mensajes de advertencia');
define('BOX_REPORTS_PROMOTION_SEO', 'Archivos SEO');
define('BOX_REPORTS_SOCIAL_MEDIA', 'Redes Sociales');
define('BOX_REPORTS_NEWSLETTERS', 'Suscritos al Boletín');
define('BOX_REPORTS_STRUCTURED_DATA', 'Datos estructurados');

define('BOX_REPORTS_STATS_LOW_STOCK_ATTRIB', 'Informe Stock por atributos');
// marketing/CRM text in includes/boxes/marketing.php
define('BOX_HEADING_MARKETING', 'Marketing');
// tools text in includes/boxes/tools.php
define('BOX_HEADING_TOOLS', 'Herramientas');
define('BOX_TOOLS_BANNER_MANAGER', 'Banners');
define('BOX_TOOLS_CACHE', 'Control de Cach&eacute;');
define('BOX_TOOLS_DEFINE_LANGUAGE', 'Definir Idiomas');
define('BOX_TOOLS_FILE_MANAGER', 'Archivos');
define('BOX_TOOLS_MAIL', 'Enviar Email');
define('BOX_TOOLS_NEWSLETTER_MANAGER', 'Boletines');
define('BOX_TOOLS_SERVER_INFO', 'Informaci&oacute;n');
define('BOX_TOOLS_WHOS_ONLINE', 'Usuarios conectados');
define('BOX_TOOLS_RECOVER_CART', 'Recuperador de Carritos');
define('BOX_TOOLS_REDIRECTS', 'Redirecciones');
define('BOX_TOOLS_BACKUP', 'Copia de Seguridad');

// localizaion box text in includes/boxes/localization.php
define('BOX_HEADING_LOCALIZATION', 'Localizaci&oacute;n');
define('BOX_LOCALIZATION_CURRENCIES', 'Monedas');
define('BOX_LOCALIZATION_LANGUAGES', 'Idiomas');

// Promoción / SEO
define('BOX_HEADING_SEO_PROMOTION', 'Promoción/SEO');

// Sistema
define('BOX_HEADING_SYSTEM', 'Sistema');
define('BOX_SYSTEM_ADMINS', 'Administradores');
define('BOX_SYSTEM_ADMIN_LIST', 'Listado de administradores');
define('BOX_SYSTEM_ACCOUNT_EDIT', 'Editar mi cuenta');
define('BOX_SYSTEM_ADMIN_LOG', 'Logs de Administradores mi cuenta');
define('BOX_SYSTEM_CONFIGURATION', 'Configuración');
define('BOX_SYSTEM_RGPD_CONFIGURATION', 'Configurar RGPD');
define('BOX_SYSTEM_SEARCH_CONFIGURATION', 'Configurar Buscador');
define('BOX_SYSTEM_EMAIL_CONFIGURATION', 'Configurar Emails');
define('BOX_SYSTEM_404_CONFIGURATION', 'Configurar 404');
define('BOX_SYSTEM_500_CONFIGURATION', 'Configurar 500');

// javascript messages
define('JS_ERROR', 'Ha habido errores procesando su formulario!\nPor favor, haga las siguientes modificaciones:\n\n');
define('JS_ERROR_SUBMITTED', 'Ya ha enviado el formulario. Pulse Aceptar y espere a que termine el proceso.');

define('JS_OPTIONS_VALUE_PRICE', '* El atributo necesita un precio\n');
define('JS_OPTIONS_VALUE_PRICE_PREFIX', '* El atributo necesita un prefijo para el precio\n');

define('JS_PRODUCTS_NAME', '* El producto necesita un nombre\n');
define('JS_PRODUCTS_DESCRIPTION', '* El producto necesita una descripci&oacute;n\n');
define('JS_PRODUCTS_PRICE', '* El producto necesita un precio\n');
define('JS_PRODUCTS_WEIGHT', '* Debe especificar el peso del producto\n');
define('JS_PRODUCTS_QUANTITY', '* Debe especificar la cantidad\n');
define('JS_PRODUCTS_MODEL', '* Debe especificar el modelo\n');
define('JS_PRODUCTS_IMAGE', '* Debe suministrar una imagen\n');

define('JS_SPECIALS_PRODUCTS_PRICE', '* Debe rellenar el precio\n');

define('JS_GENDER', '* Debe elegir un \'Sexo\'.\n');
define('JS_FIRST_NAME', '* El \'Nombre\' debe tener al menos ' . ENTRY_FIRST_NAME_MIN_LENGTH . ' letras.\n');
define('JS_LAST_NAME', '* El \'Apellido\' debe tener al menos ' . ENTRY_LAST_NAME_MIN_LENGTH . ' letras.\n');
define('JS_DOB', '* La \'Fecha de Nacimiento\' debe tener el formato: xx/xx/xxxx (dia/mes/a&ntilde;o).\n');
define('JS_EMAIL_ADDRESS', '* El \'E-Mail\' debe tener al menos ' . ENTRY_EMAIL_ADDRESS_MIN_LENGTH . ' letras.\n');
define('JS_ADDRESS', '* El \'Domicilio\' debe tener al menos ' . ENTRY_STREET_ADDRESS_MIN_LENGTH . ' letras.\n');
define('JS_POST_CODE', '* El \'C&oacute;digo Postal\' debe tener al menos ' . ENTRY_POSTCODE_MIN_LENGTH . ' letras.\n');
define('JS_CITY', '* La \'Ciudad\' debe tener al menos ' . ENTRY_CITY_MIN_LENGTH . ' letras.\n');
define('JS_STATE', '* Debe indicar la \'Provincia\'.\n');
define('JS_STATE_SELECT', '-- Seleccione Arriba --');
define('JS_ZONE', '* La \'Provincia\' se debe seleccionar de la lista para este pais.');
define('JS_COUNTRY', '* Debe seleccionar un \'Pais\'.\n');
define('JS_TELEPHONE', '* El \'Telefono\' debe tener al menos ' . ENTRY_TELEPHONE_MIN_LENGTH . ' letras.\n');
define('JS_PASSWORD', '* La \'Contrase&ntilde;a\' y \'Confirmaci&oacute;n\' deben ser iguales y tener al menos ' . ENTRY_PASSWORD_MIN_LENGTH . ' letras.\n');

define('JS_ORDER_DOES_NOT_EXIST', 'El n&uacute;mero de pedido %s no existe!');

define('CATEGORY_PERSONAL', 'Personal');
define('CATEGORY_ADDRESS', 'Domicilio');
define('CATEGORY_CONTACT', 'Contacto');
define('CATEGORY_COMPANY', 'Empresa');
define('CATEGORY_OPTIONS', 'Opciones');

define('ENTRY_GENDER', 'Sexo:');
define('ENTRY_GENDER_ERROR', '&nbsp;<span class="errorText">obligatorio</span>');
define('ENTRY_FIRST_NAME', 'Nombre:');
define('ENTRY_FIRST_NAME_ERROR', '&nbsp;<span class="errorText">min ' . ENTRY_FIRST_NAME_MIN_LENGTH . ' letras</span>');
define('ENTRY_LAST_NAME', 'Apellidos:');
define('ENTRY_LAST_NAME_ERROR', '&nbsp;<span class="errorText">min ' . ENTRY_LAST_NAME_MIN_LENGTH . ' letras</span>');
define('ENTRY_DATE_OF_BIRTH', 'Fecha de Nacimiento:');
define('ENTRY_DATE_OF_BIRTH_ERROR', '&nbsp;<span class="errorText">(p.ej. 21/05/1970)</span>');
define('ENTRY_EMAIL_ADDRESS', 'E-Mail:');
define('ENTRY_EMAIL_ADDRESS_ERROR', '&nbsp;<span class="errorText">min ' . ENTRY_EMAIL_ADDRESS_MIN_LENGTH . ' letras</span>');
define('ENTRY_EMAIL_ADDRESS_CHECK_ERROR', '&nbsp;<span class="errorText">Su Email no parece correcto!</span>');
define('ENTRY_EMAIL_ADDRESS_ERROR_EXISTS', '&nbsp;<span class="errorText">email ya existe!</span>');
define('ENTRY_COMPANY', 'Nombre empresa:');
define('ENTRY_COMPANY_ERROR', 'Error Nombre de Empresa');
define('ENTRY_STREET_ADDRESS', 'Direcci&oacute;n:');
define('ENTRY_STREET_ADDRESS_ERROR', '&nbsp;<span class="errorText">min ' . ENTRY_STREET_ADDRESS_MIN_LENGTH . ' letras</span>');
define('ENTRY_SUBURB', '');
define('ENTRY_POST_CODE', 'C&oacute;digo Postal:');
define('ENTRY_POST_CODE_ERROR', '&nbsp;<span class="errorText">min ' . ENTRY_POSTCODE_MIN_LENGTH . ' letras</span>');
define('ENTRY_CITY', 'Poblaci&oacute;n:');
define('ENTRY_CITY_ERROR', '&nbsp;<span class="errorText">min ' . ENTRY_CITY_MIN_LENGTH . ' letras</span>');
define('ENTRY_STATE', 'Provincia:');
define('ENTRY_STATE_ERROR', '&nbsp;<span class="errorText">obligatorio</span>');
define('ENTRY_COUNTRY', 'Pa&iacute;s:');
define('ENTRY_TELEPHONE_NUMBER', 'Tel&eacute;fono:');
define('ENTRY_TELEPHONE_NUMBER_ERROR', '&nbsp;<span class="errorText">min ' . ENTRY_TELEPHONE_MIN_LENGTH . ' letras</span>');
define('ENTRY_FAX_NUMBER', 'Fax:');
define('ENTRY_NEWSLETTER', 'Bolet&iacute;n:');
define('ENTRY_NEWSLETTER_YES', 'suscrito');
define('ENTRY_NEWSLETTER_NO', 'no suscrito');

//NIF start
define('ENTRY_NIF', 'NIF/CIF:');
define('JS_NIF', 'NIF requerido');
//NIF end
// images
define('IMAGE_ANI_SEND_EMAIL', 'Enviando E-Mail');
define('IMAGE_ADD', 'Añadir');
define('IMAGE_BACK', 'Volver');
define('IMAGE_BACKUP', 'Copiar');
define('IMAGE_CANCEL', 'Cancelar');
define('IMAGE_CONFIRM', 'Confirmar');
define('IMAGE_COPY', 'Copiar');
define('IMAGE_COPY_TO', 'Copiar A');
define('IMAGE_DETAILS', 'Detalle');
define('IMAGE_DELETE', 'Eliminar');
define('IMAGE_EDIT', 'Editar');
define('IMAGE_EMAIL', 'Email');
define('IMAGE_FILE_MANAGER', 'Archivos');
define('IMAGE_ICON_STATUS_GREEN', 'Activado');
define('IMAGE_ICON_STATUS_GREEN_LIGHT', 'Activar');
define('IMAGE_ICON_STATUS_RED', 'Desactivado');
define('IMAGE_ICON_STATUS_RED_LIGHT', 'Desactivar');
define('IMAGE_ICON_INFO', 'Datos');
define('IMAGE_INSERT', 'Insertar');
define('IMAGE_LOCK', 'Bloqueado');
define('IMAGE_MODULE_INSTALL', 'Instalar M&oacute;dulo');
define('IMAGE_MODULE_REMOVE', 'Quitar M&oacute;dulo');
define('IMAGE_MOVE', 'Mover');
define('IMAGE_NEW_BANNER', 'Nuevo Banner');
define('IMAGE_NEW_CATEGORY', 'Nueva Categoria');
define('IMAGE_NEW_COUNTRY', 'Nuevo Pais');
define('IMAGE_NEW_CURRENCY', 'Nueva Moneda');
define('IMAGE_NEW_FILE', 'Nuevo Fichero');
define('IMAGE_NEW_FOLDER', 'Nueva Carpeta');
define('IMAGE_NEW_LANGUAGE', 'Nueva Idioma');
define('IMAGE_NEW_NEWSLETTER', 'Nuevo Bolet&iacute;n');
define('IMAGE_NEW_PRODUCT', 'Nuevo Producto');
define('IMAGE_NEW_TAX_CLASS', 'Nuevo Tipo de Impuesto');
define('IMAGE_NEW_TAX_RATE', 'Nuevo Impuesto');
define('IMAGE_NEW_TAX_ZONE', 'Nueva Zona');
define('IMAGE_NEW_ZONE', 'Nueva Zona');
define('IMAGE_ORDERS', 'Pedidos');
define('IMAGE_ORDERS_INVOICE', 'Factura');
define('IMAGE_ORDERS_PACKINGSLIP', 'Albar&aacute;n');
define('IMAGE_PREVIEW', 'Ver');
define('IMAGE_RESET', 'Resetear');
define('IMAGE_RESTORE', 'Restaurar');
define('IMAGE_SAVE', 'Guardar');
define('IMAGE_SEARCH', 'Buscar');
define('IMAGE_SELECT', 'Seleccionar');
define('IMAGE_SEND', 'Enviar');
define('IMAGE_SEND_EMAIL', 'Send Email');
define('IMAGE_UNLOCK', 'Desbloqueado');
define('IMAGE_UPDATE', 'Actualizar');
define('IMAGE_UPDATE_CURRENCIES', 'Actualizar Cambio de Moneda');
define('IMAGE_UPLOAD', 'Subir');
define('TEXT_SEARCH', 'Buscar');
define('TEXT_SEARCH_PLACEHOLDER', 'Introduce búsqueda...');

define('ICON_CROSS', 'Falso');
define('ICON_CURRENT_FOLDER', 'Directorio Actual');
define('ICON_DELETE', 'Eliminar');
define('ICON_ERROR', 'Error');
define('ICON_FILE', 'Fichero');
define('ICON_FILE_DOWNLOAD', 'Descargar');
define('ICON_FOLDER', 'Abrir Carpeta');
define('ICON_MOVE', 'Mover');
define('ICON_LOCKED', 'Bloqueado');
define('ICON_PREVIOUS_LEVEL', 'Nivel Anterior');
define('ICON_PREVIEW', 'Previsualizar');
define('ICON_EDIT', 'Editar');
define('ICON_DUPLICATE', 'Duplicar/Copiar');
define('ICON_STATISTICS', 'Estadisticas');
define('ICON_SUCCESS', 'Exito');
define('ICON_TICK', 'Verdadero');
define('ICON_UNLOCKED', 'Desbloqueado');
define('ICON_WARNING', 'Advertencia');

// constants for use in tep_prev_next_display function
define('TEXT_RESULT_PAGE', 'P&aacute;gina %s de %d');
define('TEXT_DISPLAY_NUMBER_OF_BANNERS', 'Viendo del <b>%d</b> al <b>%d</b> (de <b>%d</b> banners)');
define('TEXT_DISPLAY_NUMBER_OF_COUNTRIES', 'Viendo del <b>%d</b> al <b>%d</b> (de <b>%d</b> paises)');
define('TEXT_DISPLAY_NUMBER_OF_CUSTOMERS', 'Viendo del <b>%d</b> al <b>%d</b> (de <b>%d</b> clientes)');
define('TEXT_DISPLAY_NUMBER_OF_CURRENCIES', 'Viendo del <b>%d</b> al <b>%d</b> (de <b>%d</b> monedas)');
define('TEXT_DISPLAY_NUMBER_OF_LANGUAGES', 'Viendo del <b>%d</b> al <b>%d</b> (de <b>%d</b> idiomas)');
define('TEXT_DISPLAY_NUMBER_OF_MANUFACTURERS', 'Viendo del <b>%d</b> al <b>%d</b> (de <b>%d</b> Marcas)');
define('TEXT_DISPLAY_NUMBER_OF_NEWSLETTERS', 'Viendo del <b>%d</b> al <b>%d</b> (de <b>%d</b> boletines)');
define('TEXT_DISPLAY_NUMBER_OF_ORDERS', 'Viendo del <b>%d</b> al <b>%d</b> (de <b>%d</b> pedidos)');
define('TEXT_DISPLAY_NUMBER_OF_ORDERS_STATUS', 'Viendo del <b>%d</b> al <b>%d</b> (de <b>%d</b> estado de pedidos)');
define('TEXT_DISPLAY_NUMBER_OF_PRODUCTS', 'Viendo del <b>%d</b> al <b>%d</b> (de <b>%d</b> productos)');
define('TEXT_DISPLAY_NUMBER_OF_PRODUCTS_EXPECTED', 'Viendo del <b>%d</b> al <b>%d</b> (de <b>%d</b> productos esperados)');
define('TEXT_DISPLAY_NUMBER_OF_REVIEWS', 'Viendo del <b>%d</b> al <b>%d</b> (de <b>%d</b> comentarios)');
define('TEXT_DISPLAY_NUMBER_OF_SPECIALS', 'Viendo del <b>%d</b> al <b>%d</b> (de <b>%d</b> ofertas)');
define('TEXT_DISPLAY_NUMBER_OF_TAX_ZONES', 'Viendo del <b>%d</b> al <b>%d</b> (de <b>%d</b> zonas de impuestos)');
define('TEXT_DISPLAY_NUMBER_OF_TAX_RATES', 'Viendo del <b>%d</b> al <b>%d</b> (de <b>%d</b> porcentajes de impuestos)');
define('TEXT_DISPLAY_NUMBER_OF_TAX_CLASSES', 'Viendo del <b>%d</b> al <b>%d</b> (de <b>%d</b> tipos de impuesto)');
define('TEXT_DISPLAY_NUMBER_OF_ZONES', 'Viendo del <b>%d</b> al <b>%d</b> (de <b>%d</b> zonas)');

define('PREVNEXT_BUTTON_PREV', '&lt;&lt;');
define('PREVNEXT_BUTTON_NEXT', '&gt;&gt;');

define('TEXT_DEFAULT', 'predeterminado/a');
define('TEXT_SET_DEFAULT', 'Establecer como predeterminado/a');
define('TEXT_FIELD_REQUIRED', '&nbsp;<span class="fieldRequired">* Obligatorio</span>');

define('ERROR_NO_DEFAULT_CURRENCY_DEFINED', 'Error: No hay moneda predeterminada. Por favor establezca una en: Herramientas de Administracion->Localizaci&oacute;n->Monedas');
define('ERROR_STATE_OR_POSTAL_CODE', 'Debe seleccionar una provincia o código postal');

define('TEXT_CACHE_CATEGORIES', 'Categorias');
define('TEXT_CACHE_MANUFACTURERS', 'Marcas');
define('TEXT_CACHE_ALSO_PURCHASED', 'Tambi&eacute;n Han Comprado');
define('TEXT_CACHE_PRODUCTS_COUNT', 'Contador Productos');


define('TEXT_NONE', '--ninguno--');
define('TEXT_TOP', 'Principio');
define('TEXT_CATEGORIES', 'Categorias');

define('ERROR_DESTINATION_DOES_NOT_EXIST', 'Error: Destino no existe.');
define('ERROR_DESTINATION_NOT_WRITEABLE', 'Error: No se puede escribir en el destino.');
define('ERROR_FILE_NOT_SAVED', 'Error: El archivo subido no se ha guardado.');
define('ERROR_FILETYPE_NOT_ALLOWED', 'Error: Extension de fichero no permitida.');
define('SUCCESS_FILE_SAVED_SUCCESSFULLY', 'Exito: Fichero guardado con &eacute;xito.');
define('WARNING_NO_FILE_UPLOADED', 'Advertencia: No se ha subido ningun archivo.');
define('WARNING_FILE_UPLOADS_DISABLED', 'Atención: Se ha desactivado la subida de archivos en el fichero de configuraci&oacute;n php.ini.');

define('BOX_HEADING_INFORMATION', 'Editar Información');
define('BOX_TOOLS_NEWS', 'Editar Noticias');
define('BOX_CATALOG_QUICK_UPDATES', 'Actualizador Rápido');

define('TEXT_DISPLAY_NUMBER_OF_PAYMENTS', 'Displaying <b>%d</b> to <b>%d</b> (of <b>%d</b> ship 2 pay)');
define('BOX_GOOGLE_SITEMAP', 'Google SiteMaps');
define('ENTRY_COMPANY_TAX_ID', 'Nº Identificación Impuesto (Compañia):');
define('ENTRY_COMPANY_TAX_ID_ERROR', '');
define('ENTRY_CUSTOMERS_GROUP_REQUEST_AUTHENTICATION', 'Desactiva la alerta de autentificación:');
define('ENTRY_CUSTOMERS_GROUP_RA_NO', 'Alerta apagada');
define('ENTRY_CUSTOMERS_GROUP_RA_YES', 'Alerta encendida');
define('ENTRY_CUSTOMERS_GROUP_RA_ERROR', '');
define('ENTRY_CUSTOMERS_GROUP_NAME', 'Grupo del Cliente:');
define('BOX_TOOLS_AMEND_DB', 'Comandos Masivos');
define('BOX_TOOLS_TESTIMONIALS_MANAGER', 'Opinión Clientes');
define('BOX_HEADING_PUNTOS', 'Sistema de Puntos');

define('BOX_TOOLS_DATABASE_ADMIN', 'Administrar BD');
define('BOX_TOOLS_EDITOR', 'Editar Archivos');

define('TEXT_SUMMARY_INFO_WHOS_ONLINE', '<b>Clientes Conectados:</b> %s');
define('TEXT_SUMMARY_INFO_CUSTOMERS', '<b>Clientes Totales:</b> %s | <b>Nuevos de Hoy:</b> %s');
define('TEXT_SUMMARY_INFO_ORDERS', '<b>Estado de Pedidos</b></td> %s <td> <span class="text2">Hoy: %s </span></td>');
define('TEXT_SUMMARY_INFO_ORDERS_TOTAL', '</td> %s <td> <span class="text2">Hoy: %s </span></td>');

define('IMAGE_BUTTON_NEW_INSTALL_SQL', 'Install SQL for New Install of Related Products, Version 4.0');
define('IMAGE_BUTTON_UPGRADE_SQL', 'Update SQL for Upgrade Install of Related Products, Version 4.0');
define('IMAGE_BUTTON_REMOVE_SQL', 'Remove SQL for all versions of Related Products');

define('BOX_TOOLS_CONTRIB_TRACKER', 'Contribuciones Instaladas');

define('BOX_REPORTS_CONFIGURATION_CHANGES','Monitorización de Cambios');
define('EMAIL_CONFIGURATION_CHANGE_TEXT_SUBJECT','Monitorización de Cambios en su tienda virtual');
define('EMAIL_CONFIGURATION_CHANGE_TEXT_BODY','La configuración de su tienda virtual a cambiado, puedes visitar su panel de administración para ver más detalles.');
define('BOX_HEADING_BOXES', 'Organizar Columnas');

define('LOGG_OUT', 'Desconectar');

define('HEADING_TITLE2', '10 productos más vistos');
define('TABLE_HEADING_VIEWED2', 'Vistos');

define('BOX_REPORTS_STOCK_LEVEL', 'Informe de Stock Bajo');

define('HEADER_WARNING', 'Atención! Por favor, haz una copia de seguridad antes de cambiar esta configuración. ');

// admin welcome text
define('TEXT5', 'Tienes ');
define('TEXT6', ' clientes en total y ');
define('TEXT7', ' productos en tu tienda. ');
define('TEXT8', ' de tus productos han sido comentados.');
define('DO_USE', 'Puedes usar el navegador rápido de la cabecera para administrar tus pedidos.');
define('WELCOME_BACK', 'Bienvenido ');
define('STOCK_TEXT_WARNING1', '<b><font   color="#990000">¡Atención!</font></b> Actualmente tenemos ');
define('STOCK_TEXT_WARNING2', ' producto(s) que están bajos de stock. Click aquí  ');
define('STOCK_TEXT_WARNING3', ' para ver el stock.');
define('STOCK_TEXT_OK1', '<font color="#009900 ">Tu stock esta completo</font> y no tienes nuevos productos que necesites pedir.<br> Click aquí para ver el ');
define('STOCK_TEXT_OK2', '');
// admin welcome text end


// summary info v1.1 plugin by conceptlaboratory.com
if (!defined('TEXT_SUMMARY_INFO_WHOS_ONLINE')) define('TEXT_SUMMARY_INFO_WHOS_ONLINE', 'Clientes Conectados: %s');
if (!defined('TEXT_SUMMARY_INFO_CUSTOMERS')) define('TEXT_SUMMARY_INFO_CUSTOMERS', 'Total Clientes: %s, Hoy: %s');
if (!defined('TEXT_SUMMARY_INFO_ORDERS')) define('TEXT_SUMMARY_INFO_ORDERS', 'Estados de tus pedidos: <br> %s, <b>Hoy:</b> %s');
define('TEXT_SUMMARY_INFO_REVIEWS', 'Total Comentarios: %s, Hoy: %s');
define('TEXT_SUMMARY_INFO_TICKETS', 'Ticket Status %s');
if (!defined('TEXT_SUMMARY_INFO_ORDERS_TOTAL')) define('TEXT_SUMMARY_INFO_ORDERS_TOTAL', 'Pedidos Totales: <br> %s,<b> Hoy: </b>%s');
define('DASHBOARD_SELLS_TODAY', 'Ventas hoy');
define('DASHBOARD_SELLS_PAYMENT_METHOD', 'Método de Pago');
define('DASHBOARD_SELLS_TOTAL', 'Total');
define('DASHBOARD_SELLS_NO_SELLS', 'No hay datos de pedidos para hoy');
define('DASHBOARD_SELLS_NO_SELLS_MONTH', 'No hay datos de pedidos para el mes actual');
define('DASHBOARD_SELLS_TEXT', 'Ventas');

define('TEXT_SELECT_ALL_PAGE', 'TODOS');
define('BOX_CUSTOMERS_OPTIONS', 'Limpiar Clientes');
define('TEXT_NERVER_LOGIN','Nunca conectaron');
define('TEXT_NERVER_LOGIN_AND_NOT_SUBSCRI','Nunca conectaron y no estan subscritos');
define('TEXT_DUPLICATED_F_LNAME','Duplicados Nombre y Apellidos');
define('MAX_SEARCH_RESULTS_ADMIN', '20');
define('TEXT_ALLS', 'Todos');
define('TEXT_ALL', 'Todo');

define('TABLE_HEADING_EDIT_ORDERS', 'Para modificar el Pedido');
define('TEXT_IMAGE_CREATE','Crear Pedido');
define('TEXT_INFO_CUSTOMER_SERVICE_ID','Introducido por:');
define('TEXT_INFO_NOT_EXIST', 'No existe ningun registro para mostrar.');

define('BOX_REPORTS_MARGIN_REPORT', 'Margen de Beneficio');
if (!defined('BOX_REPORTS_ORDERS_TOTAL')) define('BOX_REPORTS_ORDERS_TOTAL', 'Total de Pedidos de Clientes');
define('TEXT_PRODUCTS_COST_INFO', 'Precio Coste: ');
define('TEXT_PRODUCTS_PROFIT_INFO', 'Porcentaje:');
define('TEXT_PRODUCTS_PRICE_COST', 'Precio (Coste):');
// header text in includes/header.php
define('HEADER_TITLE_ACCOUNT', 'Mi Cuenta');
define('HEADER_TITLE_LOGOFF_ADMIN', 'Salir');

define('BOX_CATALOG_DISCOUNT_COUPONS', 'Cupones Descuento');
define('BOX_REPORTS_DISCOUNT_COUPONS', 'Cupones Descuento');
// Admin Account
define('BOX_HEADING_MY_ACCOUNT', 'Mi cuenta');

// configuration box text in includes/boxes/administrator.php
define('BOX_HEADING_ADMINISTRATOR', 'Administrador');
define('BOX_ADMINISTRATOR_MEMBERS', 'Listado de Administradores');
define('BOX_ADMINISTRATOR_MEMBER', 'Administradores');
define('BOX_ADMINISTRATOR_BOXES', 'Acceso a ficheros');

// images
define('IMAGE_FILE_PERMISSION', 'Permisos a ficheros');
define('IMAGE_GROUPS', 'Lista de grupos');
define('IMAGE_INSERT_FILE', 'Insertar fichero');
define('IMAGE_MEMBERS', 'Lista de miembros');
define('IMAGE_NEW_GROUP', 'Nuevo grupo');
define('IMAGE_NEW_MEMBER', 'Nuevo miembro');
define('IMAGE_NEXT', 'Siguiente');
define('TEXT_IMAGE_NONEXISTENT', 'La imagen no existe en el directorio');

// constants for use in tep_prev_next_display function
define('TEXT_DISPLAY_NUMBER_OF_FILENAMES', 'Mostrando <b>%d</b> a <b>%d</b> (de <b>%d</b> nombres de fichero)');
define('TEXT_DISPLAY_NUMBER_OF_MEMBERS', 'Mostrando <b>%d</b> a <b>%d</b> (de <b>%d</b> miembros)');


define('TEXT_CHANGE_PRODUCTS_STATUS', '¿Cambiar el estado de los productos? ');

define('GOOGLEMAP_APIKEY', 'ABQIAAAAGFZowtmD2vAvCvBtqg68kxRi_j0U6kJrkFvY4-OX2XYmEAa76BR68N3Lpk0WOxH8DFZSkpCdKmxKqQ');
define('BOX_PREMADE', 'Comentarios predeterminados');

define('TEXT_PRODUCTS_EAN', 'Código EAN: ');
define('TEXT_SHOW_ALL', 'Mostrar todos');
define('SHOW_COUNTS', false);
define('IMAGE_RELATED_PRODUCTS', 'Relacionados');
define('TEXT_STOCK', 'Stock');
define('TEXT_REFERENCE', 'Referencia');
define('TEXT_EAN_CODE', 'Código EAN');

define('TEXT_SELECT_MASSIVE_OPTION', 'Seleccione opción masiva');
define('TEXT_MASSIVE_OPTION_MOVE_CATEGORIES', 'Mover categorías');
define('TEXT_MASSIVE_OPTION_MOVE_CATEGORIES_TO', 'Mover a:');
define('TEXT_MASSIVE_OPTION_ERROR_NO_SELECTED', 'Para realizar alguna de estas operaciones necesitas seleccionar algún registro');

define('TEXT_NO_CHANGES', 'No Cambiar');
define('TEXT_DISABLE_ALL', 'Desactivar Todos');
define('TEXT_ENABLE_ALL', 'Activar Todos');

define('TEXT_INPUT_FILE_EMPTY', 'No hay archivo seleccionado');
define('TEXT_URL_EXAMPLE', 'url_ejemplo');
define('TEXT_GENERAL_INFORMATION', 'Información general');
define('TEXT_DESIRED_STOCK', 'Stock Deseado');
define('TEXT_NOT_TO_REPLENISH', 'No volver a reponer');
define('TEXT_ATTACH_VIDEO', 'Adjuntar Video');
define('TEXT_ATTACHED_FILE', 'Archivo adjunto');
define('TEXT_LOCATION', 'Ubicación');
define('TEXT_DIGITAL_CANON', 'Canon digital');
define('TEXT_PROFIT', 'Beneficio');
define('TEXT_PRICE', 'Precio');
define('TEXT_NET', 'Neto');
define('TEXT_ATTENTION', 'Atención');
define('TEXT_NET_PRICE', 'Precio neto');
define('TEXT_GROSS_PRICE', 'Precio bruto');
define('TEXT_PERCENT', 'Porcentaje');
define('TEXT_EXPIRATION', 'Expiración');
define('TEXT_NOTES', 'Notas');
define('TEXT_SELECT_LANGUAGE', 'Seleccionar idioma');
define('TEXT_OTHER_OPTIONS', 'Otras opciones');

# Paginador
define('TEXT_SPLIT_PAGE_VIEW', 'Viendo');
define('TEXT_SPLIT_PAGE_OF', 'de');
define('TEXT_SPLIT_PAGE_RECORDS', 'registro(s)');

# Atributos
define('ATTR_MANAGER_TITLE', 'Opciones y valores del producto (Atributos)');
define('ATTR_MANAGER_TEMPLATES', '-- Plantillas --');
define('ATTR_MANAGER_NEW_TEMPLATE', '+ Nueva plantilla');
define('ATTR_MANAGER_SEE_PRICE', 'Ver precio');
define('ATTR_MANAGER_SEE_PRICE_TAXES', 'Con impuestos');
define('ATTR_MANAGER_SEE_PRICE_NO_TAXES', 'Sin impuestos');
define('ATTR_MANAGER_SELECT_OPTION', 'Seleccionar opción');
define('ATTR_MANAGER_ACTIONS', 'Acciones');
define('ATTR_MANAGER_ACTION', 'Acción');
define('ATTR_MANAGER_ACTION_ADD', 'Añadir acción');
define('ATTR_MANAGER_VALUE', 'Valor');
define('ATTR_MANAGER_PRICE', 'Precio');
define('ATTR_MANAGER_WEIGHT', 'Peso');
define('ATTR_MANAGER_REFERENCE', 'Referencia');
define('ATTR_MANAGER_EAN', 'EAN');
define('ATTR_MANAGER_UBICACION', 'Ubicacion');
define('ATTR_MANAGER_EDIT_VALUE', 'Editar valor');
define('ATTR_MANAGER_DELETE', 'Eliminar');
define('ATTR_MANAGER_DESIRED_STOCK', 'Stock deseado');
define('ATTR_MANAGER_COMBINATIONS_COUNT', 'Número de combinaciones');
define('ATTR_MANAGER_COMBINATIONS', 'Combinaciones');
define('ATTR_MANAGER_COMBINATIONS_BETWEEN', 'Combinacion entre ');
define('ATTR_MANAGER_DO_NOT_REPLACE', 'No volver a reponer este producto');
define('ATTR_MANAGER_SELECT_VALUE', 'Seleccionar valor');
define('ATTR_MANAGER_SELECT_MASSIVE_OPTION', 'Seleccione opción masiva');
define('ATTR_MANAGER_CHANGE_IMAGE', 'Cambiar imagen');
define('ATTR_MANAGER_IMAGE_OF_THE_PRODUCT', 'Imágenes del producto');
define('ATTR_MANAGER_IMAGE', 'Imagen');
define('ATTR_MANAGER_FILE', 'Archivo');
define('ATTR_MANAGER_ADD_FILES', 'Añadir archivos');
define('ATTR_MANAGER_CONFIRM_IMAGE_DELETE', '¿Deseas eliminar la imagen?');
define('ATTR_MANAGER_CONFIRM_ACTION_DELETE', '¿Realmente deseas eliminar la acción?');
define('ATTR_MANAGER_ADD_NEW_ACTION', 'Añadir nueva acción');
define('ATTR_MANAGER_UPLOAD_FILE', 'Subir archivo');
define('ATTR_MANAGER_PRODUCTS_FILE', 'Archivo del producto');
define('ATTR_MANAGER_STOCK_REPORT', 'Informe sobre el stock del producto');
define('ATTR_MANAGER_LOAD_TITLE', 'Carga la plantilla seleccionada');
define('ATTR_MANAGER_SAVE_TITLE', 'Guardar los atributos actuales');
define('ATTR_MANAGER_RENAME_TITLE', 'Renombrar la plantilla seleccionada');
define('ATTR_MANAGER_DELETE_TITLE', 'Borrar la plantilla seleccionada');
define('ATTR_MANAGER_LOAD_ERROR', 'Debes seleccionar antes una plantilla de la lista');
define('ATTR_MANAGER_LOAD_CONFIRM', '¿Seguro que quiere recuperar la plantilla %s?. Se sobreescribirán los atributos actuales del producto. La operación no se puede deshacer.');
define('ATTR_MANAGER_SAVE_ERROR', 'Seleccione una plantilla de la lista para editarla o si deseas crear una nueva pantilla seleccione la opción + Nueva plantilla');
define('ATTR_MANAGER_SAVE_PROMPT', 'Por favor introduce el nombre de la nueva plantilla.');
define('ATTR_MANAGER_SAVE_CONFIRM', 'ATENCIÓN: Vas a editar la plantilla %s, si deseas crear una nueva pantilla seleccione la opción + Nueva plantilla. ¿Estas totalmente deacuerdo?');
define('ATTR_MANAGER_DELETE_CONFIRM', '¿Estas totalmente deacuerdo para eliminar la plantilla %s?');
define('ATTR_MANAGER_DELETE_OPTION_CONFIRM', '¿Realmente deseas eliminar el valor?');
define('ATTR_MANAGER_DELETE_OPTIONS_CONFIRM', '¿Realmente deseas eliminar el/los valor/es seleccionado/s?');
define('ATTR_MANAGER_DELETE_OPTIONS_ERROR', 'Para eliminar necesitas seleccionar algún registro');
define('ATTR_MANAGER_DELETE_OPTION_OPTION_CONFIRM', '¿Realmente deseas eliminar la opción?');
define('ATTR_MANAGER_SEARCH_NO_RESULT', 'No existen resultados');
define('ATTR_MANAGER_NEW_OPTION_TITLE', 'Nueva opción');
define('ATTR_MANAGER_NEW_VALUE_TITLE', 'Nuevo valor');

# Log
define('LOG_RECORD', 'El administrador <b>%s</b> ha accedido a <b>%s</b>.');

# Paginator
define('PAGINATOR_FIRST', 'Primero');
define('PAGINATOR_BEFORE', 'Anterior');
define('PAGINATOR_NEXT', 'Siguiente');
define('PAGINATOR_LAST', 'Última');

# QTPro
define('QTPRO_REPORT_STOCK_OK', 'El resumen de la cantidad de productos en stock es correcto.');
define('QTPRO_REPORT_STOCK_OK_DESCRIPTION', 'Esto significa que el resumen del Stock General actual de este producto registrado en base de datos, coincide con el valor que obtenemos del calculo actual.<br />
				<b>El total de stock es: %s.</b>');
define('QTPRO_REPORT_STOCK_KO', 'ATENCIÓN: El resumen de la cantidad de productos en stock NO es correcto.');
define('QTPRO_REPORT_STOCK_KO_DESCRIPTION', 'El Stock General actual registrado en la base de datos de este producto esta descuadrado frente a la suma de las unidades del stock por atributos.<br />
				<b>Stock General según el valor en la base de datos: %s</b><br />
				<b>Stock General según la suma del valor del stock de cada opción asignada: %s</b>');
define('QTPRO_REPORT_STOCK_OPTIONS_OK', 'El stock de las opciones del producto es correcto.');
define('QTPRO_REPORT_STOCK_OPTIONS_OK_DESCRIPTION', 'Todos los registros de la base de datos del Stock por Opciones para este producto aparecen correctamente.<br />
				<b>Número total de registros que tiene este producto: %s</b><br />
				<b>Número de registros con errores: %s</b>');
define('QTPRO_REPORT_STOCK_OPTIONS_KO', 'ATENCIÓN: El stock de las opciones del producto NO es correcto');
define('QTPRO_REPORT_STOCK_OPTIONS_KO_DESCRIPTION', 'Esto significa que al menos uno de los registros de base de datos para este producto no es correcto. O alguna de las opciones del producto no aparece en filas que deber’a o aparece en filas que no deberían.<br />
				<b>Número total de registros que tiene este producto: %s</b><br />
				<b>Número de registros con errores: %s</b>');
define('QTPRO_OPTIONS_NOT_SHOWED', 'Estas opciones no aparecen en la fila(s):');
define('QTPRO_OPTIONS_NOT_SHOWED_SOLUTIONS', 'Posibles soluciones: </span>Borrar la fila correspondiente de la base de datos o dejar de controlar el stock de esta opción.');
define('QTPRO_OPTIONS_INTRUDERS', 'Estas opciones existen en fila(s) aunque no deberían:');
define('QTPRO_OPTIONS_INTRUDERS_SOLUTIONS', 'Posibles soluciones');
define('QTPRO_OPTIONS_INTRUDERS_DELETE', 'Borrar la fila correspondiente de la base de datos o iniciar el control del stock de esta opción.');
define('QTPRO_OPTIONS_AUTOMATICALLY_SOLVED', '¿Solucionar Automáticamente?');
define('QTPRO_OPTIONS_CLEAN', 'Limpiar (Elimina todas las filas desordenadas)');
define('QTPRO_OPTIONS_UPDATE_STOCK', 'Actualizar el Stock General del producto a %s unidades');

# Menu
define('MENU_WELCOME', '¡Bienvenido %s!');
define('MENU_MY_ACCOUNT', 'Mi cuenta');
define('MENU_MY_STORE', 'Su tienda');
define('MENU_CUSTOMERS', 'Clientes');
define('MENU_ORDERS', 'Pedidos');
define('MENU_LOGOFF', 'Desconectar');
define('MENU_WE_ARE_WAITING_FOR_YOU', 'Te estabamos esperando, tenemos que contarte');
define('MENU_WARNING', 'Alerta');
define('MENU_BLOG', 'Blog');
define('MENU_SERVICES', 'Servicios');
define('MENU_ASSISTENCE', 'Asistencia');

# Fechas
define('DATE_SHORT_JAN', 'Ene');
define('DATE_SHORT_FEB', 'Feb');
define('DATE_SHORT_MAR', 'Mar');
define('DATE_SHORT_APR', 'Abr');
define('DATE_SHORT_MAY', 'May');
define('DATE_SHORT_JUN', 'Jun');
define('DATE_SHORT_JUL', 'Jul');
define('DATE_SHORT_AUG', 'Ago');
define('DATE_SHORT_SEP', 'Sep');
define('DATE_SHORT_OCT', 'Oct');
define('DATE_SHORT_NOV', 'Nov');
define('DATE_SHORT_DEC', 'Dic');

# Varios
define('TEXT_SAVE', 'Guardar');
define('TEXT_SAVE_CHANGES', 'Guardar cambios');
define('TEXT_SAVE_AND_BACK', 'Guardar y volver');
define('TEXT_BACK', 'Volver');
define('TEXT_ADD', 'Añadir');
define('TEXT_EDIT', 'Editar');
define('TEXT_SELECT', 'Seleccione');
define('TEXT_CLEAN_FILTER', 'Limpiar filtro');
define('TEXT_FILTER', 'Filtrar');
define('TEXT_DELETE', 'Eliminar');
define('TEXT_CATEGORY', 'Categoría');
define('TEXT_MANUFACTURER', 'Marca');
define('TEXT_ALL_MANUFACTURERS', 'Todas las marcas');
define('TEXT_ACCEPT', 'Aceptar');
define('TEXT_CANCEL', 'Cancelar');
define('TEXT_VIEW', 'Ver');
define('TEXT_OPTIONS', 'Opciones');
define('TEXT_VIEW_FILE', 'Ver archivo');
define('TEXT_DELETE_IMAGE', 'Eliminar imagen');
define('TEXT_SELECT_FILE', 'Seleccionar archivo');

define( 'FILTERS_SORT_ORDER', 'Ordenar' );
define( 'FILTERS_SORT_ASCENDING_TITLE', 'Título ascendente' );
define( 'FILTERS_SORT_DESCENDING_TITLE', 'Título descendente' );
define( 'FILTERS_SORT_DESCENDING_PRICE', 'Precio descendente' );
define( 'FILTERS_SORT_ASCENDING_PRICE', 'Precio ascendente' );
define( 'FILTERS_DEFAULT', 'Por defecto' );
define( 'FILTERS_ALL', 'Todos' );
define( 'FILTERS_TEXT', 'Filtrar' );
define( 'FILTERS_ALL_CATEGORIES', 'Todas las categorias' );

//Warning de headers
define('WARNING_CONFIG_FILE_WRITEABLE', 'Advertencia: Puedo escribir en el fichero de configuración: ' . dirname((string) $_SERVER['SCRIPT_FILENAME']) . '/includes/configure.php. En determinadas circunstancias esto puede suponer un riesgo - por favor corriga los permisos de este fichero.');
define('WARNING_SESSION_DIRECTORY_NON_EXISTENT', 'Advertencia: El directorio para guardar datos de sesión no existe: ' . tep_session_save_path() . '. Las sesiones no funcionarán hasta que no se corriga este error.');
define('WARNING_SESSION_DIRECTORY_NOT_WRITEABLE', 'Avertencia: No puedo escribir en el directorio para datos de sesión: ' . tep_session_save_path() . '. Las sesiones no funcionarán hasta que no se corriga este error.');
define('WARNING_SESSION_AUTO_START', 'Advertencia: session.auto_start esta activado - desactive esta caracteristica en el fichero php.ini and reinicie el servidor web.');
define('WARNING_DOWNLOAD_DIRECTORY_NON_EXISTENT', 'Advertencia: El directorio para productos descargables no existe: ' . DIR_FS_DOWNLOAD . '. Los productos descargables no funcionarán hasta que no se corriga este error.');

define( 'ERROR_CAPTCHA', 'Lo sentimos, pero no hemos podido verificar el reCAPTCHA en su solicitud. Vuelva a intentarlo pasado unos minutos o contacte con nosotros.' );
define( 'ERROR_CAPTCHA_BAD_REQUEST', 'Lo sentimos, pero no hemos podido verificar el reCAPTCHA en su solicitud. La solicitud no es válida o tiene un formato incorrecto.' );
define( 'ERROR_CAPTCHA_TIMEOUT', 'El tiempo de espera para el código CAPTCHA ha expirado. Por favor rellene de nuevo el formulario.' );
define( 'ERROR_CAPTCHA_SCORE', 'Lo sentimos, pero no hemos podido verificar el reCAPTCHA en su solicitud. El Score del envío del formulario es bajo y no hemos podido verificar que no sea un robot rellenando este formulario. Contacte con nosotros.' );

define('QTPRODOCTOR_PROBLEM_RESUME', 'El cálculo del resume de stock esta incorrecto. Por favor revíselo %s.');
define('QTPRODOCTOR_PROBLEM_STOCK', 'Hay errores en las entradas en base de datos del stock de este producto. Por favor revíselo %s.');
define('QTPRODOCTOR_TEXT_HERE', 'aquí');
// XSell (English)
define('BOX_CATALOG_XSELL_PRODUCTS', 'Venta cruzada de productos');
if (!defined('HEADING_TITLE_SEARCH')) define('HEADING_TITLE_SEARCH', 'Buscar:');
define('TEXT_CACHE_XSELL_PRODUCTS', 'Venta cruzada de productos');
define('TEXT_MOBILE_CACHE_XSELL_PRODUCTS', 'Venta cruzada de productos (móvil)');


// added for support system //
define('TEXT_DISPLAY_NUMBER_OF_TICKET_PRIORITY' ,'Displaying <b>%d</b> to <b>%d</b> (of <b>%d</b> Ticket Priorities)');
define('TEXT_DISPLAY_NUMBER_OF_TICKET_STATUS' ,'Displaying <b>%d</b> to <b>%d</b> (of <b>%d</b> Ticket Status)');
define('TEXT_DISPLAY_NUMBER_OF_TICKET_ADMINS' ,'Displaying <b>%d</b> to <b>%d</b> (of <b>%d</b> Ticket Administrators)');//
define('TEXT_DISPLAY_NUMBER_OF_TICKET_DEPARTMENT' ,'Displaying <b>%d</b> to <b>%d</b> (of <b>%d</b> Support Departments)');
define('TEXT_DISPLAY_NUMBER_OF_TICKETS', 'Displaying <b>%d</b> to </b>%d</b> (of <b>%d</b> Support Tickets)');
define('TEXT_DISPLAY_NUMBER_OF_NEWS', 'Displaying <b>%d</b> to <b>%d</b> (of <b>%d</b> News Items)');  //news
define('BOX_SUPPORT_HEADING', 'Support System');
define('BOX_TICKET_STATUS', 'Ticket Status');
define('BOX_TICKET_PRIORITY', 'Ticket Priority');
define('BOX_TICKET_ADMINS', 'Ticket Admins');
define('BOX_TICKET_TICKETS', 'Support Tickets');
define('BOX_TICKET_DEPARTMENT', 'Support Dept\'s');
define('BOX_FAQ_MANAGER', 'Faq manager');
define('BOX_TOOLS_NEWS_MANAGER', 'Support News');
define('WARNING_PRODUCT_IN_BUNDLE', 'WARNING: The product just deleted was contained in a bundle! You need to edit or delete the bundle (model) name: ');

define('BOX_RETURNS_HEADING', 'Devoluciones de Clientes');
define('BOX_RETURNS_REASONS', 'Razones Devolución');
define('BOX_RETURNS_MAIN', 'Productos Devueltos');
define('BOX_RETURNS_TEXT', 'Return Text Edit');
define('BOX_RETURNS_STATUS', 'Estados de RMA');
define('BOX_HEADING_REFUNDS', 'Método de Reembolso');

define( 'TEXT_INFO_RESTOCK_PRODUCT_QUANTITY', 'Restock Product' );
 //BEGIN NEXT AND PREVIOUS ORDERS DISPLAY IN ADMIN

   define('PREV_ORDER', '<b>&lt;&lt;Pedido Anterior</b>');
   define('NEXT_ORDER', '<b>Pedido Siguiente&gt;&gt;</b>');

  //END NEXT AND PREVIOUS ORDERS DISPLAY IN ADMIN


define( 'RGPD_EMAIL_DISABLE', '<span style="font-size: 24px;">Estimado {USERNAME},</span><br/><br/>
Te confirmamos mediante este e-mail, que con fecha {DATE} tu cuenta ha sido desactivada de maneral temporal. Como ya sabes, puedes volver a activarla cuando desee simplemente con una de las siguientes opciones:<br/><br/>
&nbsp;&nbsp;<strong>- Accede a Tu Cuenta:</strong> con tan solo acceder de nuevo a tu cuenta de usuario en nuestra tienda online, podrás activarla.<br/>
&nbsp;&nbsp;<strong>- Solicitar por e-mail/teléfono:</strong> si lo deseas, nos puedes avisar y nuestro equipo te la vuelve a activar por tí.<br/><br/>Esperamos volver a poder verte pronto ¡ha sido una pena que nos abandones!<br><br>Un saludo de parte del equipo de ' . STORE_NAME );

define( 'RGPD_EMAIL_ACTIVE', '<span style="font-size: 24px;">¡Hola {USERNAME}!</span><br/><br/>
Te confirmamos mediante este e-mail, que con fecha {DATE} tu cuenta han sido de nuevo activada para que puedas volver a usar todas las funcionalidades de nuestra tienda online.<br/><br/>¡Cuento nos alegra verte de nuevo de vuelta!<br/><br/>Un saludo de parte del equipo de ' . STORE_NAME );

// Politicas
define('EMAIL_POLITICA', 'En cumplimiento del Reglamento (UE) 2016/679 (RGPD) y la Ley Org&aacute;nica 3/2018, de 5 de diciembre (LOPDGDD), le informamos de que sus datos personales son tratados por Francobordo como responsable del tratamiento, con la finalidad de gestionar su pedido y la relaci&oacute;n comercial. Puede ejercer sus derechos de acceso, rectificaci&oacute;n, supresi&oacute;n, oposici&oacute;n, limitaci&oacute;n y portabilidad escribiendo a info@francobordo.com o llamando al 916 528 858. M&aacute;s informaci&oacute;n en nuestra Pol&iacute;tica de Privacidad: https://www.francobordo.com/politica-de-privacidad-i-15.html');
define( 'PIE_EMAIL', 'Calle San Rafael nº 8. Alcobendas. 28108 MADRID<br>info@francobordo.com<br>Copyright &copy; ' . date( 'Y' ) . '   www.francobordo.com' );
define('GEO_ZONES_CRUD_TYPE', 'Tipo');
define('GEO_ZONES_CRUD_TYPE_HELP', 'Tipo aplicado a la zona');
