<?php
/*
  $Id: espanol.php 1743 2007-12-20 18:02:36Z hpdl $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2007 osCommerce

  Released under the GNU General Public License
*/

// look in your $PATH_LOCALE/locale directory for available locales
// or type locale -a on the server.
// Examples:
// on RedHat try 'es_ES'
// on FreeBSD try 'es_ES.ISO_8859-1'
// on Windows try 'sp', or 'Spanish'
//@setlocale(LC_TIME, 'es_ES.ISO_8859-1');
@setlocale(LC_TIME, 'es_ES.UTF-8');
setlocale( LC_CTYPE, 'C' );

define('DATE_FORMAT_SHORT', '%d/%m/%Y');  // this is used for strftime()
define('DATE_FORMAT_LONG', '%d/%m/%Y'); // this is used for strftime()
define('DATE_FORMAT', 'd/m/Y');  // this is used for date()
define('DATE_TIME_FORMAT', DATE_FORMAT_SHORT . ' %H:%M:%S');

////
// Return date in raw format
// $date should be in format mm/dd/yyyy
// raw date is in format YYYYMMDD, or DDMMYYYY

if( ! function_exists( 'tep_date_raw' ) )
{
	function tep_date_raw($date, $reverse = false) {
	  if ($reverse) {
		return substr($date, 0, 2) . substr($date, 3, 2) . substr($date, 6, 4);
	  } else {
		return substr($date, 6, 4) . substr($date, 3, 2) . substr($date, 0, 2);
	  }
	}
}

// if USE_DEFAULT_LANGUAGE_CURRENCY is true, use the following currency, instead of the applications default currency (used when changing language)
define('LANGUAGE_CURRENCY', 'EUR');
define('LANGUAGE_LOCALE', 'es-ES');

// Global entries for the <html> tag
define('HTML_PARAMS','lang="es-ES"');

// charset for web pages and emails
define('CHARSET', 'UTF-8');

// page title
define('TITLE', STORE_NAME);

// header text in includes/header.php
define('BOX_ALL_CATEGORIES', 'Todas');
define('BOX_HEADER_ADDFAVORITE', 'Agregar a favoritos');
define('HEADER_TITLE_CREATE_ACCOUNT', 'registro cliente');
define('HEADER_TITLE_MY_ACCOUNT', 'Mi Cuenta');
define('HEADER_TITLE_CART_CONTENTS', 'Ver Cesta');
define('HEADER_TITLE_CHECKOUT', 'Realizar Pedido');
define('HEADER_TITLE_TOP', 'Inicio');
define('HEADER_TITLE_CATALOG', 'Cat&aacute;logo');
define('HEADER_TITLE_LOGOFF', 'Salir');
define('HEADER_TITLE_LOGIN', 'Entrar');

// footer text in includes/footer.php
define('FOOTER_TEXT_REQUESTS_SINCE', 'peticiones desde');

// text for gender
define('MALE', 'Var&oacute;n');
define('FEMALE', 'Mujer');
define('MALE_ADDRESS', 'Sr.');
define('FEMALE_ADDRESS', 'Sra.');

// text for date of birth example
define('DOB_FORMAT_STRING', 'dd/mm/aaaa');

// categories box text in includes/boxes/categories.php
define('BOX_HEADING_CATEGORIES', 'Categorías');

// manufacturers box text in includes/boxes/manufacturers.php
define('BOX_HEADING_MANUFACTURERS', 'Marcas');

// whats_new box text in includes/boxes/whats_new.php
define('BOX_HEADING_WHATS_NEW', 'Novedades');

// quick_find box text in includes/boxes/quick_find.php
define('BOX_HEADING_SEARCH', 'B&uacute;squeda');
define('BOX_SEARCH_TEXT', 'Use palabras clave para encontrar el producto que busca.');
define('BOX_SEARCH_ADVANCED_SEARCH', 'B&uacute;squeda Avanzada');

// specials box text in includes/boxes/specials.php
define('BOX_HEADING_SPECIALS', 'Ofertas');

// reviews box text in includes/boxes/reviews.php
define('BOX_HEADING_REVIEWS', 'Comentarios');
define('BOX_REVIEWS_WRITE_REVIEW', 'Escriba un comentario para este producto');
define('BOX_REVIEWS_NO_REVIEWS', 'En este momento, no hay ningun comentario');
define('BOX_REVIEWS_TEXT_OF_5_STARS', '%s de 5 Estrellas!');

// shopping_cart box text in includes/boxes/shopping_cart.php
define('BOX_HEADING_SHOPPING_CART', 'Compras');
define('BOX_SHOPPING_CART_EMPTY', '0 productos');

// order_history box text in includes/boxes/order_history.php
define('BOX_HEADING_CUSTOMER_ORDERS', 'Mis Pedidos');

// best_sellers box text in includes/boxes/best_sellers.php
define('BOX_HEADING_BESTSELLERS', 'Top Ventas');
define('BOX_HEADING_BESTSELLERS_IN', 'Los Mas Vendidos en <br />&nbsp;&nbsp;');

// notifications box text in includes/boxes/products_notifications.php
define('BOX_HEADING_NOTIFICATIONS', 'Notificaciones');
define('BOX_NOTIFICATIONS_NOTIFY', 'Notifiqueme de cambios a <strong>%s</strong>');
define('BOX_NOTIFICATIONS_NOTIFY_REMOVE', 'No me notifique de cambios a <strong>%s</strong>');

// manufacturer box text
define('BOX_HEADING_MANUFACTURER_INFO', 'Marcas');
define('BOX_MANUFACTURER_INFO_HOMEPAGE', 'P&aacute;gina de %s');
define('BOX_MANUFACTURER_INFO_OTHER_PRODUCTS', 'Otros productos');

// languages box text in includes/boxes/languages.php
define('BOX_HEADING_LANGUAGES', 'Idiomas');

// currencies box text in includes/boxes/currencies.php
define('BOX_HEADING_CURRENCIES', 'Monedas');

// information box text in includes/boxes/information.php
define('BOX_HEADING_INFORMATION', 'Informaci&oacute;n');
define('BOX_INFORMATION_PRIVACY', 'Confidencialidad');
define('BOX_INFORMATION_CONDITIONS', 'Condiciones de uso');
define('BOX_INFORMATION_SHIPPING', 'Envios/Devoluciones');
define('BOX_INFORMATION_CONTACT', 'Contáctenos');
define('BOX_INFORMATION_MY_POINTS_HELP', 'Programa de Puntos FAQ');//Points/Rewards Module V2.00

// tell a friend box text in includes/boxes/tell_a_friend.php
define('BOX_HEADING_TELL_A_FRIEND', 'D&iacute;selo a un Amigo');
define('BOX_TELL_A_FRIEND_TEXT', 'Env&iacute;a esta pagina a un amigo con un comentario.');

// checkout procedure text
define('CHECKOUT_BAR_DELIVERY', 'entrega');
define('CHECKOUT_BAR_PAYMENT', 'pago');
define('CHECKOUT_BAR_CONFIRMATION', 'confirmaci&oacute;n');
define('CHECKOUT_BAR_FINISHED', 'finalizado!');

// pull down default text
define('PULL_DOWN_DEFAULT', 'Seleccione');
define('PULL_DOWN_CITY', 'Seleccione ciudad');
define('PULL_DOWN_STATE', 'Seleccione provincia');
define('PULL_DOWN_COUNTRY', 'Seleccione país');
define('TYPE_BELOW', 'Escriba Debajo');

// javascript messages
define('JS_ERROR', 'Hay errores en su formulario!\nPor favor, haga las siguientes correciones:\n\n');

define('JS_REVIEW_TEXT', '* Su \'Comentario\' debe tener al menos ' . REVIEW_TEXT_MIN_LENGTH . ' letras.\n');
define('JS_REVIEW_RATING', '* Debe evaluar el producto sobre el que opina.\n');

define('JS_ERROR_NO_PAYMENT_MODULE_SELECTED', '* Por favor seleccione un m&eacute;todo de pago para su pedido.\n');

define('JS_ERROR_SUBMITTED', 'Ya ha enviado el formulario. Pulse Aceptar y espere a que termine el proceso.');

define('ERROR_NO_PAYMENT_MODULE_SELECTED', 'Por favor seleccione un m&eacute;todo de pago para su pedido.');

define('CATEGORY_COMPANY', 'Empresa');
define('CATEGORY_PERSONAL', 'Personal');
define('CATEGORY_ADDRESS', 'Direcci&oacute;n');
define('CATEGORY_CONTACT', 'Contacto');
define('CATEGORY_OPTIONS', 'Opciones');
define('CATEGORY_PASSWORD', 'Contrase&ntilde;a');

define('ENTRY_COMPANY', 'Empresa:');
define('ENTRY_COMPANY_ERROR', '');
define('ENTRY_COMPANY_TEXT', '');
define('ENTRY_COMPANY_TAX_ID', 'Nº Identificación (Solo Clientes especiales):');
define('ENTRY_COMPANY_TAX_ID_ERROR', '');
define('ENTRY_COMPANY_TAX_ID_TEXT', '');
define('ENTRY_GENDER', 'Sexo:');
define('ENTRY_GENDER_ERROR', 'Por favor seleccione una opción.');
define('ENTRY_GENDER_TEXT', '*');
define('ENTRY_FIRST_NAME', 'Nombre:');
define('ENTRY_FIRST_NAME_ERROR', 'Su Nombre debe tener al menos ' . ENTRY_FIRST_NAME_MIN_LENGTH . ' letras.');
define('ENTRY_FIRST_NAME_TEXT', '*');
define('ENTRY_LAST_NAME', 'Apellidos:');
define('ENTRY_LAST_NAME_ERROR', 'Sus apellidos deben tener al menos ' . ENTRY_LAST_NAME_MIN_LENGTH . ' letras.');
define('ENTRY_LAST_NAME_TEXT', '*');
define('ENTRY_DATE_OF_BIRTH', 'Fecha de Nacimiento:');
define('ENTRY_DATE_OF_BIRTH_ERROR', 'Su fecha de nacimiento debe tener este formato: DD/MM/AAAA (p.ej. 21/05/1970)');
define('ENTRY_DATE_OF_BIRTH_TEXT', '* (p.ej. 21/05/1970)');
define('ENTRY_EMAIL_ADDRESS', 'E-Mail:');
define('ENTRY_EMAIL_ADDRESS_ERROR', 'Su dirección de E-Mail debe tener al menos ' . ENTRY_EMAIL_ADDRESS_MIN_LENGTH . ' letras.');
define('ENTRY_EMAIL_ADDRESS_CHECK_ERROR', 'Su dirección de E-Mail no parece válida - por favor haga los cambios necesarios.');
define('ENTRY_EMAIL_ADDRESS_ERROR_EXISTS', 'Su dirección de E-Mail ya figura entre nuestros clientes - puede entrar a su cuenta con esta dirección o crear una cuenta nueva con una dirección diferente.');
define('ENTRY_EMAIL_ADDRESS_TEXT', '*');
define('ENTRY_STREET_ADDRESS', 'Dirección:');
define('ENTRY_STREET_ADDRESS_ERROR', 'Su dirección debe tener al menos ' . ENTRY_STREET_ADDRESS_MIN_LENGTH . ' letras.');
define('ENTRY_STREET_ADDRESS_TEXT', '*');
define('ENTRY_SUBURB', 'Suburbio');
define('ENTRY_SUBURB_ERROR', '');
define('ENTRY_SUBURB_TEXT', '');
define('ENTRY_POST_CODE', 'Código Postal:');
define('ENTRY_POST_CODE_ERROR', 'Su código postal debe tener al menos ' . ENTRY_POSTCODE_MIN_LENGTH . ' letras.');
define('ENTRY_POST_CODE_TEXT', '*');
define('ENTRY_CITY', 'Ciudad:');
define('ENTRY_CITY_ERROR', 'Su ciudad debe tener al menos ' . ENTRY_CITY_MIN_LENGTH . ' letras.');
define('ENTRY_CITY_TEXT', '*');
define('ENTRY_STATE', 'Provincia:');
define('ENTRY_STATE_ERROR', 'Su provincia debe tener al menos ' . ENTRY_STATE_MIN_LENGTH . ' letras.');
define('ENTRY_STATE_ERROR_SELECT', 'Por favor seleccione de la lista desplegable.');
define('ENTRY_STATE_TEXT', '*');
define('ENTRY_COUNTRY', 'País:');
define('ENTRY_COUNTRY_ERROR', 'Debe seleccionar un país de la lista desplegable.');
define('ENTRY_COUNTRY_TEXT', '*');
define('ENTRY_TELEPHONE_NUMBER', 'Teléfono:');
define('ENTRY_TELEPHONE_NUMBER_ERROR', 'Su número de teléfono debe tener al menos ' . ENTRY_TELEPHONE_MIN_LENGTH . ' letras.');
define('ENTRY_TELEPHONE_NUMBER_TEXT', '*');
define('ENTRY_FAX_NUMBER', 'Fax:');
define('ENTRY_FAX_NUMBER_ERROR', '');
define('ENTRY_FAX_NUMBER_TEXT', '');
define('ENTRY_NEWSLETTER', 'Boletín de noticias:');
define('ENTRY_NEWSLETTER_TEXT', '');
define('ENTRY_NEWSLETTER_YES', 'suscribirse');
define('ENTRY_NEWSLETTER_NO', 'no suscribirse');
define('ENTRY_NEWSLETTER_ERROR', '');
define('ENTRY_PASSWORD', 'Contraseña:');
define('ENTRY_PASSWORD_ERROR', 'Su contraseña debe tener al menos ' . ENTRY_PASSWORD_MIN_LENGTH . ' letras.');
define('ENTRY_PASSWORD_ERROR_NOT_MATCHING', 'La confirmación de la contraseña debe ser igual a la contraseña.');
define('ENTRY_PASSWORD_TEXT', '*');
define('ENTRY_PASSWORD_CONFIRMATION', 'Confirme Contraseña:');
define('ENTRY_PASSWORD_CONFIRMATION_TEXT', '*');
define('ENTRY_PASSWORD_CURRENT', 'Contraseña Actual:');
define('ENTRY_PASSWORD_CURRENT_TEXT', '*');
define('ENTRY_PASSWORD_CURRENT_ERROR', 'Su contraseña debe tener al menos ' . ENTRY_PASSWORD_MIN_LENGTH . ' letras.');
define('ENTRY_PASSWORD_NEW', 'Nueva Contraseña:');
define('ENTRY_PASSWORD_NEW_TEXT', '*');
define('ENTRY_PASSWORD_NEW_ERROR', 'Su contraseña nueva debe tener al menos ' . ENTRY_PASSWORD_MIN_LENGTH . ' letras.');
define('ENTRY_PASSWORD_NEW_ERROR_NOT_MATCHING', 'La confirmación de su contraseña debe coincidir con su contraseña nueva.');
define('PASSWORD_HIDDEN', '--OCULTO--');

define('ENTRY_NIF', 'DNI/NIF:');
define('ENTRY_NO_NIF_ERROR', 'Ha de introducir su DNI/NIF.');
define('ENTRY_FORMATO_NIF_ERROR', 'El DNI/NIF ha de tener 5 caracteres. En el caso del NIF, rellene con ceros a la izquierda si es necesario.');
define('ENTRY_LETRA_NIF_ERROR', 'La letra del DNI es incorrecta.');
define('ENTRY_NIF_TEXT', '*');
define('ENTRY_NIF_EXAMPLE', '(por ejemplo: 01234567L)');
define('FORM_REQUIRED_INFORMATION', '* Dato Obligatorio');

// constants for use in tep_prev_next_display function
define('TEXT_RESULT_PAGE', 'P&aacute;ginas de Resultados:');
define('TEXT_DISPLAY_NUMBER_OF_PRODUCTS', 'Viendo del <strong>%d</strong> al <strong>%d</strong> (de <strong>%d</strong> productos)');
define('TEXT_DISPLAY_NUMBER_OF_ORDERS', 'Viendo del <strong>%d</strong> al <strong>%d</strong> (de <strong>%d</strong> pedidos)');
define('TEXT_DISPLAY_NUMBER_OF_REVIEWS', 'Viendo del <strong>%d</strong> al <strong>%d</strong> (de <strong>%d</strong> comentarios)');
define('TEXT_DISPLAY_NUMBER_OF_PRODUCTS_NEW', 'Viendo del <strong>%d</strong> al <strong>%d</strong> (de <strong>%d</strong> productos nuevos)');
define('TEXT_DISPLAY_NUMBER_OF_SPECIALS', 'Viendo del<strong>%d</strong> al <strong>%d</strong> (de <strong>%d</strong> ofertas)');

define('PREVNEXT_TITLE_FIRST_PAGE', 'Principio');
define('PREVNEXT_TITLE_PREVIOUS_PAGE', 'Anterior');
define('PREVNEXT_TITLE_NEXT_PAGE', 'Siguiente');
define('PREVNEXT_TITLE_LAST_PAGE', 'Final');
define('PREVNEXT_TITLE_PAGE_NO', 'P&aacute;gina %d');
define('PREVNEXT_TITLE_PREV_SET_OF_NO_PAGE', 'Anteriores %d P&aacute;ginas');
define('PREVNEXT_TITLE_NEXT_SET_OF_NO_PAGE', 'Siguientes %d P&aacute;ginas');
define('PREVNEXT_BUTTON_FIRST', '&lt;&lt;PRINCIPIO');
define('PREVNEXT_BUTTON_PREV', 'Anterior');
define('PREVNEXT_BUTTON_NEXT', 'Siguiente');
define('PREVNEXT_BUTTON_LAST', 'FINAL&gt;&gt;');

define('IMAGE_BUTTON_ADD_ADDRESS', 'A&ntilde;adir Direcci&oacute;n');
define('IMAGE_BUTTON_ADDRESS_BOOK', 'Direcciones');
define('IMAGE_BUTTON_BACK', 'Volver');
define('IMAGE_BUTTON_BUY_NOW', 'Compre Ahora');
define('IMAGE_BUTTON_CHANGE_ADDRESS', 'Cambiar Direcci&oacute;n');
define('IMAGE_BUTTON_CHECKOUT', 'Realizar Pedido');
define('IMAGE_BUTTON_CONFIRM_ORDER', 'Confirmar Pedido');
define('IMAGE_BUTTON_CONTINUE', 'Continuar');
define('IMAGE_BUTTON_WAIT', 'Espere...');
define('IMAGE_BUTTON_CONTINUE_SHOPPING', 'Seguir Comprando');
define('IMAGE_BUTTON_DELETE', 'Eliminar');
define('IMAGE_BUTTON_EDIT_ACCOUNT', 'Editar Cuenta');
define('IMAGE_BUTTON_HISTORY', 'Historial de Pedidos');
define('IMAGE_BUTTON_LOGIN', 'Entrar');
define('IMAGE_BUTTON_IN_CART', 'A&ntilde;adir a la Cesta');
define('IMAGE_BUTTON_NOTIFICATIONS', 'Notificaciones');
define('IMAGE_BUTTON_QUICK_FIND', 'B&uacute;squeda R&aacute;pida');
define('IMAGE_BUTTON_REMOVE_NOTIFICATIONS', 'Eliminar Notificaciones');
define('IMAGE_BUTTON_REVIEWS', 'Comentarios');
define('IMAGE_BUTTON_SEARCH', 'Buscar');
define('IMAGE_BUTTON_SHIPPING_OPTIONS', 'Opciones de Env&iacute;o');
define('IMAGE_BUTTON_TELL_A_FRIEND', 'D&iacute;selo a un Amigo');
define('IMAGE_BUTTON_UPDATE', 'Actualizar');
define('IMAGE_BUTTON_UPDATE_CART', 'Actualizar Cesta');
define('IMAGE_BUTTON_WRITE_REVIEW', 'Escribir Comentario');

define('SMALL_IMAGE_BUTTON_DELETE', 'Eliminar');
define('SMALL_IMAGE_BUTTON_EDIT', 'Modificar');
define('SMALL_IMAGE_BUTTON_VIEW', 'Ver');

define('ICON_ARROW_RIGHT', 'm&aacute;s');
define('ICON_CART', 'En Cesta');
define('ICON_ERROR', 'Error');
define('ICON_SUCCESS', 'Correcto');
define('ICON_WARNING', 'Advertencia');

define('TEXT_GREETING_PERSONAL', 'Bienvenido <strong><span class="greetUser">%s!</span></strong>');
define('TEXT_GREETING_PERSONAL_RELOGON', '<small>Si no es %s, por favor <a href="%s"><u>entre aqui</u></a> e introduzca sus datos.</small>');
define('TEXT_GREETING_GUEST', 'Bienvenido <span class="greetUser">Invitado!</span> &iquest;Le gustaria <a href="%s"><u>entrar en su cuenta</u></a> o preferiria <a href="%s"><u>crear una cuenta nueva</u></a>?');

define('TEXT_SORT_PRODUCTS', 'Ordenar Productos ');
define('TEXT_DESCENDINGLY', 'Descendentemente');
define('TEXT_ASCENDINGLY', 'Ascendentemente');
define('TEXT_BY', ' por ');

define('TEXT_REVIEW_BY', 'por %s');
define('TEXT_REVIEW_WORD_COUNT', '%s palabras');
define('TEXT_REVIEW_RATING', 'Evaluaci&oacute;n: %s [%s]');
define('TEXT_REVIEW_DATE_ADDED', 'Fecha Alta: %s');
define('TEXT_NO_REVIEWS', 'En este momento, no hay ningun comentario.');

define('TEXT_NO_NEW_PRODUCTS', 'Ahora mismo no hay novedades.');

define('TEXT_UNKNOWN_TAX_RATE', 'Impuesto desconocido');

define('TEXT_REQUIRED', '<span class="errorText">Obligatorio</span>');
define ('DEFAULT_COUNTRY', '195');

define('ERROR_TEP_MAIL', '<font face="Verdana, Arial" size="2" color="#ff0000"><strong><small>TEP ERROR:</small> No he podido enviar el email con el servidor SMTP especificado. Configura tu servidor SMTP en la secci&oacute;n adecuada del fichero php.ini.</strong></font>');
define('WARNING_INSTALL_DIRECTORY_EXISTS', 'Advertencia: El directorio de instalaci&oacute;n existe en: ' . dirname($_SERVER['SCRIPT_FILENAME']) . '/install. Por razones de seguridad, elimine este directorio completamente.');
define('WARNING_CONFIG_FILE_WRITEABLE', 'Advertencia: Puedo escribir en el fichero de configuraci&oacute;n: ' . dirname($_SERVER['SCRIPT_FILENAME']) . '/includes/configure.php. En determinadas circunstancias esto puede suponer un riesgo - por favor corriga los permisos de este fichero.');
define('WARNING_SESSION_DIRECTORY_NON_EXISTENT', 'Advertencia: El directorio para guardar datos de sesi&oacute;n no existe: ' . tep_session_save_path() . '. Las sesiones no funcionar&aacute;n hasta que no se corriga este error.');
define('WARNING_SESSION_DIRECTORY_NOT_WRITEABLE', 'Avertencia: No puedo escribir en el directorio para datos de sesi&oacute;n: ' . tep_session_save_path() . '. Las sesiones no funcionar&aacute;n hasta que no se corriga este error.');
define('WARNING_SESSION_AUTO_START', 'Advertencia: session.auto_start esta activado - desactive esta caracteristica en el fichero php.ini and reinicie el servidor web.');
define('WARNING_DOWNLOAD_DIRECTORY_NON_EXISTENT', 'Advertencia: El directorio para productos descargables no existe: ' . DIR_FS_DOWNLOAD . '. Los productos descargables no funcionar&aacute;n hasta que no se corriga este error.');

define('TEXT_CCVAL_ERROR_INVALID_DATE', 'La fecha de caducidad de la tarjeta de cr&eacute;dito es incorrecta. Compruebe la fecha e int&eacute;ntelo de nuevo.');
define('TEXT_CCVAL_ERROR_INVALID_NUMBER', 'El n&uacute;mero de la tarjeta de cr&eacute;dito es incorrecto. Compruebe el numero e int&eacute;ntelo de nuevo.');
define('TEXT_CCVAL_ERROR_UNKNOWN_CARD', 'Los primeros cuatro digitos de su tarjeta son: %s. Si este n&uacute;mero es correcto, no aceptamos este tipo de tarjetas. Si es incorrecto, int&eacute;ntelo de nuevo.');
define('REDEEM_SYSTEM_ERROR_POINTS_NOT', 'El valor de tus puntos no es el suficiente para pagar por completo su compra. Por favor, seleccione otra forma de pago');
define('REDEEM_SYSTEM_ERROR_POINTS_OVER', 'Error en el canje de puntos! Los puntos no tienen el valor del total de la compra. Por favor vuelva a introducir sus puntos.');
define('REFERRAL_ERROR_SELF', 'Lo sentimos pero no te puedes referir tu mismo.');
define('REFERRAL_ERROR_NOT_VALID', 'El email que has introducido parece no valido, por favor corrija los errores.');
define('REFERRAL_ERROR_NOT_FOUND', 'El email de la persona referida que has insertado no existe.');
define('TEXT_POINTS_BALANCE', 'Estado de Puntos');
define('TEXT_POINTS', 'Puntos:');
define('TEXT_VALUE', 'Valor:');
define('REVIEW_HELP_LINK', ' Escriba un comentario y gane <b>%s</b> en puntos.<br />Por favor revise el %s de puntos para mas información.');

define('FOOTER_TEXT_BODY', 'Copyright &copy; ' . date('Y') . ' <a href="' . tep_href_link(FILENAME_DEFAULT) . '">' . STORE_NAME . '</a>');

define('MINIMUM_ORDER_NOTICE', 'Las unidades mínimas permitidas para %s es de %d. Tu carro ha sido actualizado para mostrarlas.');
define('QUANTITY_BLOCKS_NOTICE', '%s puede ser comprado solo en multiples de %d. Tu carro ha sido actualizado para mostrar esto.');
define('MATC_CONDITION_AGREEMENT', 'He leido y acepto los <a href="%s" target="_blank"><strong><u>Terminos y Condiciones de Uso</u></strong></a> de este sitio: ');
define('MATC_HEADING_CONDITIONS', 'Aceptar terminos y condiciones de uso');
define('MATC_ERROR', 'Tienes que aceptar los terminos y condiciones de uso para continuar.');

define('BOX_HEADING_CUSTOMER_TESTIMONIALS', 'Opinión');
define('BOX_HEADING_FEATURED', 'Productos Destacados');
define('BOX_INFORMATION_CUSTOMER_TESTIMONIALS', 'Opinión');
define('TABLE_HEADING_TESTIMONIALS_ID', 'ID');
define('TABLE_HEADING_TESTIMONIALS_NAME', 'Nombre');
define('TABLE_HEADING_TESTIMONIALS_DESCRIPTION', 'Opinión');
define('TEXT_TESTIM_BY', 'Estrito por:');
define('IMAGE_BUTTON_INSERT', 'Insertar:');

define('BOX_INFORMATION_ALLPRODS', 'Todos los productos');
define('BOX_INFORMATION_RSS', 'RSS');
define('IMAGE_BUTTON_RP_BUY_NOW', 'Comprar');
define('MY_ACCOUNT_DELETE', 'Eliminar Cuenta');

define('ENTRY_DISCOUNT_COUPON_ERROR', 'El cupón introducido no es válido.');
define('ENTRY_DISCOUNT_COUPON_ERROR2', 'No puede usar cupones de descuento.');
define('ENTRY_DISCOUNT_COUPON_AVAILABLE_ERROR', 'El cupón introducido ha superado el numero de veces de uso.');
define('ENTRY_DISCOUNT_COUPON_USE_ERROR', 'Nuestros registros indican que usted ha utilizado este cup&oacute;n %s vez(ces). Usted no puede utilizar el c&oacute;digo más de %s vez(ces).');
define('ENTRY_DISCOUNT_COUPON_MIN_PRICE_ERROR', 'El total de compra m&iacute;nima para este cup&oacute;n es de %s');
define('ENTRY_DISCOUNT_COUPON_MIN_QUANTITY_ERROR', 'El n&uacute;mero m&iacute;nimo de productos necesarios para este cup&oacute;n es de %s');
define('ENTRY_DISCOUNT_COUPON_EXCLUSION_ERROR', 'Algunos o todos los productos en su cesta est&aacute;n excluidos.' );
define('ENTRY_DISCOUNT_COUPON', 'C&oacute;digo Cup&oacute;n:');
define('ENTRY_DISCOUNT_COUPON_SHIPPING_CALC_ERROR', 'Los cargos de env&iacute;o calculados han cambiado.');
define('ENTRY_DISCOUNT_COUPON_ERROR_MAX_ORDER', 'El valor del cupón (%s €) excede al total del pedido (%s €). Por favor, añada más productos al carrito para poder usar el cupón.');

// CATALOG_PRODUCTS_WITH_IMAGES_mod
define('BOX_CATALOG_PRODUCTS_WITH_IMAGES', 'Catálogo Imprimible');
define('BOX_CATALOG_PRODUCTS_WITH_IMAGES_FULL', 'Catálogo Imprimible Completo');
define('IMAGE_BUTTON_UPSORT', 'Ordenar Ascendente');
define('IMAGE_BUTTON_DOWNSORT', 'Ordenar Descendente');

define('TABLE_HEADING_REFERRAL', 'Recomendado por');
define('TEXT_REFERRAL_REFERRED', 'Si algún amigo, familiar o conocido le ha recomendado nuestra tienda por favor, introduzca su dirección de email aqui: ');

define('TABLE_HEADING_FEATURED_PRODUCTS', 'Productos Destacados');
define('TABLE_HEADING_FEATURED_PRODUCTS_CATEGORY', 'Productos Destacados en %s');

define('VISUAL_VERIFY_CODE_CHARACTER_POOL', 'abcdefghkmnpstwxyABCDEFGHJKMNPRSTWXY23456789FJWNVB63HDLAJAF');  //no zeros or O
define('VISUAL_VERIFY_CODE_CATEGORY', '<br />Sistema Anti-Spam (Sensitivo a may&uacute;sculas)<br />');
define('VISUAL_VERIFY_CODE_ENTRY_ERROR', 'El codigo de seguridad que has introducido no coincide con el que se muestra en la imagen. Por favor, int&eacute;ntelo de nuevo.');
define('VISUAL_VERIFY_CODE_ENTRY_TEXT', '*');

define('VISUAL_VERIFY_CODE_TEXT_INSTRUCTIONS', 'Escriba el c&oacute;digo de seguridad:');
define('VISUAL_VERIFY_CODE_BOX_IDENTIFIER', '(refrescar p&aacute;gina para renovar)');
define('ENTRY_REMEMBER_ME', 'Recordarme');

define('MESSAGE_WAIT','Por favor espere...');
define('TEXT_PRICE_BREAKS', 'Desde');
define('TEXT_ON_SALE', 'On sale');

define('FREE_SHIPPING_TITLE', '¡Envío Gratuito!');
define('FREE_SHIPPING_DESCRIPTION', 'Gastos de envío gratuitos');


// Filtro
define('FILTRO_FILTRO', 'Marcas:');
define('FILTRO_ORDENAR', 'Ordenar:');
define('FILTRO_NUMERO', 'Nº Articulos:');
define('FILTRO_NO_EXISTEN', 'No existen productos que correspondan con el filtro seleccionado.');

// Paginador
define('PAGINADOR_MOSTRAR', 'Mostrando %d de %d productos');
define('PAGINADOR_MAS', 'Mostrar más productos');

define('TABLE_HEADING_IMAGEN', 'Imagen');


define('TEXT_ERROR_SHIPPING', 'Lo sentimos, pero es necesario para continuar con tu pedido seleccionar una forma de envío disponible de la siguiente lista.');

// Politicas
define('EMAIL_POLITICA', 'De acuerdo con lo establecido en la ley Orgánica 15/1999, de 13 de diciembre, de Protección de datos de carácter personal, le informamos que sus datos personales incluidos en nuestra base de datos, forman parte de un fichero automatizado responsabilidad de la empresa y que se encuentra registrado en la Agencia Española de Protección de Datos. Estos Datos Personales solamente serán utilizados para realizar una correcta Gestión de nuestra Relación Comercial. Si lo desean,podrán ejercitar en todo momento los derechos de acceso,rectificación,cancelación y, en su caso, el de oposición, dirigiéndose por correo electronico a info@francobordo.com o al teléfono 916 528 858.');
define( 'PIE_EMAIL', 'Calle San Rafael nº 8. Alcobenda. 28108 MADRID<br>info@francobordo.com<br>Copyright &copy; ' . date( 'Y' ) . '   www.francobordo.com' );

//begin Supportticketsystem
define('BOX_HEADING_SUPPORT', 'Soporte');
//end Supportticketsystem

// XSell (English)
define('TEXT_XSELL_PRODUCTS', 'We Also Recommend');

//+ Insurance 2.03
define('TEXT_SHIPPING_INSURANCE_TITLE', 'Seguro de Envío');
define('TEXT_SHIPPING_INSURANCE_CHOICE', '¿Le gustaría asegurar su envío por <strong>%s</strong>? ');
define('TEXT_SHIPPING_INSURANCE_DISCLAIMER', '(Le recomedamos que asegure su envío. Desmarque esta opción si no quiere asegurar su envío.) ');
//- Insurance 2.03

//BOF Bundled Products
define('IMAGE_BUTTON_OUT_OF_STOCK', 'Out of Stock');
define('TEXT_BUNDLE_ONLY', 'Not Sold Separately');
//EOF Bundled Products

define('TEXT_SHOW_ALL', 'Ver todos');

// Añadido traduccion //
define('TEXT_LOGIN_IN', 'Entrar');
define('TEXT_LOGIN_REGISTER', 'REGÍSTRATE');
define( 'TEXT_NEWS', 'Novedades' );
define( 'TEXT_SPECIALS', 'Ofertas' );
define( 'TEXT_INFORMATION', 'Informacion' );
/**
 * #XCC-313-91043
 */
define('TEXT_AFFILIADOS', 'Afiliados');
define( 'TEXT_CONTACT', 'Contacto' );
define( 'TEXT_CONTACT_US', 'Contacta con nosotros' );
define( 'TEXT_REMEMBER_PASS', 'Recordar contraseña' );
define( 'TEXT_MY_ACCOUNT', 'Mi cuenta' );
define( 'TEXT_MY_ORDERS', 'Mis pedidos' );
define( 'TEXT_MY_WISHLIST', 'Mis favoritos' );
define( 'TEXT_MY_POINTS', 'Mis puntos' );
define( 'TEXT_EXIT', 'Salir' );
define( 'TEXT_SEARCH', 'Buscar' );
define( 'TEXT_FILTER_MANUFACTURERS', 'Filtrar por marca' );
define( 'TEXT_SEE_ALL', 'ver todas' );
define( 'TEXT_NAUTICA', 'Náutica' );
define( 'TEXT_PESCA', 'Pesca' );
define( 'TEXT_TIEMPO_LIBRE', 'Tiempo libre' );
define( 'TEXT_SUBMARINISMO', 'Submarinismo' );
define( 'TEXT_PRIVACIDAD', 'Política de Privacidad' );
define( 'TEXT_BOLETIN', 'suscríbete a nuestro boletín.' );
define( 'TEXT_BOLETIN_INFO', 'Sé el primero en enterarte de todas las novedades' );
define( 'TEXT_SUBSCRIBE', 'Suscribirse' );
define( 'TEXT_DISTRIBUIDOR', 'Área profesionales' );
define( 'TEXT_DISTRIBUIDOR_INFO', 'Regístrese y aproveche los descuentos y ventajas de ser Profesional de la Náutica' );
define( 'TEXT_DISTRIBUIDOR_REGISTRO', 'Iniciar registro' );
define( 'TEXT_FOOTER1', 'FRANCOBORDO.COM | TU TIENDA DE NÁUTICA, PESCA, TIEMPO LIBRE Y SUBMARINISMO' );
define( 'TEXT_FOOTER2', 'FRANCOBORDO MADRID: Calle San Rafael 8. Alcobendas. 28108 MADRID' );
define( 'TEXT_FOOTER3', 'HORARIO de TIENDA:<br/>de 10:00 a 20:00 de Lunes a Viernes<br/>Sábados de 10:00 a 14:00' );
define( 'TEXT_DEVELOPED', '' );
define( 'TEXT_OLVIDO', '¿Olvidó su contraseña?' );
define( 'TEXT_ACOGERSE', 'Me acojo al recargo de equivalencia' );
define( 'TEXT_PRODUCT', 'producto' );
define( 'TEXT_VISTA', 'Vista' );
define( 'TEXT_NUM_MOSTRAR', 'Número de productos a mostrar en esta página' );
if (!defined('TEXT_READ_MORE')) define( 'TEXT_READ_MORE', 'leer+' );
define( 'TEXT_DESCRIPTION', 'Descripción' );
define( 'TEXT_ESPECIFICACIONES', 'Especificaciones' );
define( 'TEXT_COMMENTS', 'Comentarios' );
define( 'TEXT_RELATED', 'Relacionados' );
define( 'TEXT_SHARE', 'Compártelo en' );
define( 'TEXT_OPTIONAL_RELATED', 'Accesorios y relacionados' );
define( 'TEXT_PRICE', 'Precio' );
define( 'TEXT_CAPTCHA', 'Escribe lo que ves a continuación:' );
define( 'TEXT_SELECT_STORE', 'Seleccione tienda para su recogida:' );

define( 'CONDITION_AGREEMENT_WARNING', 'Debes de aceptar las Politica de Privacidad y Condiciones antes de continuar' );
define( 'EXTRA_SUBJECT_STOREOWNER', '' );

// Begin: RMA Returns System
define('BOX_INFORMATION_RETURNS', 'Track a Return');
// End: RMA Returns System

define('PRODUCTS_TOGETHER_TITLE', 'Aprovecha y compra también estos productos');

// Ajax transferencia
define( 'AJAX_TRANS_INFO', 'información del pago:' );
define( 'AJAX_TRANS_PLEASE', 'Por favor use los siguientes datos para transferir el valor total de su compra' );
define( 'AJAX_TRANS_NAME', 'Nombre de Cuenta:' );
define( 'AJAX_TRANS_BANK', 'Nombre del Banco:' );
define( 'AJAX_TRANS_NUMBER', 'Número de Cuenta:' );
define( 'AJAX_TRANS_REMEMBER', 'La compra no se enviará hasta que no aparezca el importe en nuestra cuenta' );
define( 'MODULE_ORDER_TOTAL_SHIPPING_TITLE', 'Gastos de Envío');
define( 'MENSAJE_VACACIONES', 'Los pedidos realizados del 8 de Octubre al 16 de Octubre no se gestionarán hasta el Lunes 16 de Octubre.<br>Estamos de traslado a una nuevas instalaciones que nos permitirán dar un mejor servicio a nuestros clientes.<br>Les pedimos disculpas por los inconvenientes que le podamos ocasionar' );
define('REORDER', 'Pedir de nuevo');
define( 'SHIPPING_PREDICTION_BUY_NOW', 'Cómpralo ahora y lo recibes entre el <span>%s1</span> y el <span>%s2</span>' );
define( 'SHIPPING_PREDICTION_BUY_NOW_TOMORROW', 'Cómpralo ahora y lo recibes mañana' );
define( 'SHIPPING_PREDICTION_BUY_NOW_PAST_TOMORROW', 'Cómpralo ahora y lo recibes pasado mañana' );
define( 'SHIPPING_PREDICTION_BEFORE', ' antes de las 13:30h' );
define( 'SHIPPING_PREDICTION_NONE', 'Ha incluido en su compra un producto bajo pedido, el plazo de entrega puede ser mayor de 30 días.' );
define( 'SHIPPING_PREDICTION_FROM', '\d\e' );
define( 'SHIPPING_PREDICTION_MORE_INFO', 'Más información' );
define( 'SHIPPING_PREDICTION_MORE_INFO_DETAILS', '<p>-Las fechas de entrega aquí indicadas son para envíos dentro de la península, para le entrega en sábados habrá de elegir SEUR 13:30.</p>
<p>-Entregas en Baleares: Los plazos indicados son validos eligiendo como empresa de transporte SEUR 13:30, en el caso de elegir otro medio de envío al plazo indicado habrá de sumarle un día laborable mas.</p>
<p>-Entregas en Canarias, Ceuta, Melilla y Destinos Internacionales : Al plazo indicado habrá de añadirle 5 días laborables mas.</p>
<p>- En el caso de elegir como forma de envío CORREOS el plazo de entrega se alargará de uno a dos día mas sobre los plazos anteriormente indicados.</p>' );
define( 'SHIPPING_PREDICTION_EXCEPT', '* Excepto los productos bajo pedido que dependemos de la recepción del mismo.' );

define('ENTRY_CITY_ID_ERROR', 'Debe seleccionar un ciudad');


define('NOTIFICACIONES_TEXT', '¿Quieres ser el primero en conocer las mejores promociones de Francobordo?');
define('NOTIFICACIONES_BUTTON_YES', 'Sí');
define('NOTIFICACIONES_BUTTON_NO', 'No');

define( 'SPECIALS_CUENTA_ATRAS', 'Esta oferta finaliza en: ' );
define( 'SPECIALS_CUENTA_ATRAS_DIA', 'día' );
define( 'SPECIALS_CUENTA_ATRAS_DIAS', 'días' );


define( 'ERROR_POLITICA', 'Debes de leer y aceptar la politica de privacidad antes de seguir.' );
define('LOGIN_LOGOFF', 'Desconectar');
define('MY_WISH', 'Mis favoritos');
define('MY_ACCOUNT', 'Mi cuenta');
define('SHOW_MORE_FILTERS', 'MOSTRAR MÁS FILTROS');
define('SHOW_LESS_FILTERS', 'MOSTRAR MENOS FILTROS');
define('VER_MODIFICAR', 'ver o modificar');
define('VER_MAS', 'ver más');
define( 'ACCOUNT_ERROR_PASSWORD', 'La contraseña no coinciden, intentalo de nuevo.' );
define( 'RGPD_WINDOW_MODAL_SUBTITLE', 'Hemos actualizado nuestras Condiciones y realizado algunos cambios en la Política de datos. Dedica unos minutos a revisar estos cambios e indicar si estás de acuerdo.' );
define( 'RGPD_WINDOW_MODAL_TITLE', 'Cambios en las condiciones y la política de datos' );
define( 'RGPD_WINDOW_MODAL_ACCEPT', 'Aceptar y continuar' );
define( 'RGPD_CHECKBOX_TERMINO_TRADE', 'He leído y acepto los términos "{TITLE}" de este sitio' );
define( 'RGPD_CHECKBOX_TERMINO_TRADE_ERROR', 'Debes de leer y aceptar el término "{TITLE}" antes de seguir.' );
define('TEXT_DATE_DAY', 'D&iacute;a');
define('TEXT_DATE_MONTH', 'Mes');
define('TEXT_DATE_YEAR', 'A&ntilde;o');
define('TEXT_DATE_JAN', 'Enero');
define('TEXT_DATE_FEB', 'Febrero');
define('TEXT_DATE_MAR', 'Marzo');
define('TEXT_DATE_APR', 'Abril');
define('TEXT_DATE_MAY', 'Mayo');
define('TEXT_DATE_JUN', 'Junio');
define('TEXT_DATE_JUL', 'Julio');
define('TEXT_DATE_AUG', 'Agosto');
define('TEXT_DATE_SEP', 'Septiembre');
define('TEXT_DATE_OCT', 'Octubre');
define('TEXT_DATE_NOV', 'Noviembre');
define('TEXT_DATE_DEC', 'Diciembre');
define( 'MY_DOWNLOADS', 'Descargar tu Información' );
define( 'MY_COMMENTS', 'Comentarios' );
define( 'MY_REVIEWS', 'Opiniones' );
define( 'MY_DISABLE', 'Desactivar Cuenta Temporal' );
define( 'NOTIFICACIONES_EMAIL', 'notificaciones por email activas' );
define( 'USA_6_CARACTERES', 'Utiliza 6 o más caracteres' );
define( 'MY_RGPD_TITLE', 'Historial Aceptación de Políticas' );
define( 'MY_RGPD_TEXT', 'Has <b>aceptado</b> las <a href="{LINK}"><u>políticas y términos</u></a> generales en el día y hora {DATE}' );
define( 'MY_RGPD_TEXT_TRADE', 'Has <b>{TYPE}</b> el término "{TERM}" | Fecha: {DATE} h.' );
define( 'MY_RGPD_ACCEPT', 'aceptado' );
define( 'MY_RGPD_DENEY', 'denegado' );
define( 'RGPD_EMAIL_CUSTOMER_DELETE_NOTIFY_SUBJECT', '[Acción Requerida] Su cuenta de cliente en ' . STORE_NAME . ' va a ser eliminada' );
define( 'RGPD_EMAIL_CUSTOMER_DELETE_NOTIFY', '<span style="font-size: 24px;">Estimado {USERNAME},</span><br/><br/>Te informamos de que según la normativa de la RGPD, con fecha {DATE} <strong>sus datos van a ser eliminados de nuestro sistema de forma automática en un periodo de {DAYS} días</strong>.<br><br>Si desea conservar tus datos de cliente en nuestra tienda, necesitamos que <strong>accedas a tu cuenta antes de la fecha</strong>. Para ello puedes acceder cliqueando en el siguiente botón:<br/><br/><a href="{LINK}" style="background-color: #1d9896; border: 1px solid #1d9896; border-radius: 3px; color: #ffffff; display: inline-block; font-family: sans-serif; font-size: 16px; line-height: 44px; text-align: center; text-decoration: none; -webkit-text-size-adjust: none; mso-hide: all; padding: 10px 40px;">ACCEDER A MI CUENTA</a><br/><br/>En el caso de que no recibamos un acceso a su cuenta, llegada la fecha mencionada el sistema borrará automáticamente todos sus datos.<br/><br/>Un saludo de parte del equipo de ' . STORE_NAME );
define( 'RGPD_WINDOW_MODAL_TITLE_DOB', '¿Eres mayor de 16 años?' );
define( 'RGPD_WINDOW_MODAL_SUBTITLE_DOB', 'Debido a la nueva Ley de protección de datos Europea, debes de tener 16 años o más como estable el artículo 8 de la RGPD, para poder seguir en este sitio debes aceptar estos términos antes de registrarte como cliente.' );
define( 'RGPD_WINDOW_MODAL_DOB_DENEGATE', 'No soy mayor' );
define( 'RGPD_WINDOW_MODAL_DOB_ACCEPT', 'Aceptar y continuar' );
define('ENTRY_DATE_OF_BIRTH_OLD_ERROR', 'Debido a la  Ley de protección de datos Europea, debes de tener 16 años o más como estable el artículo 8 de la RGPD, para poder registrarte como cliente en este sitio debes cumplir estos términos.');
define( 'RGPD_ACCOUNT_DISABLE_TITLE', 'Oooh... ¡Tu Cuenta esta Desactivada!' );
define( 'RGPD_ACCOUNT_DISABLE_TEXT', '¡Cómo nos alegra tenerte de nuevo por aquí! No se si recuerdas, que el pasado día <strong>{DATE}h.</strong>, nos solicitaste la Desactivación de tu cuenta de manera Temporal.<br><br>Con ello tus datos han sido restringidos durante todo este tiempo según la normativa de la RGPD vigente, pero ¡no te preocupes! Nuestro sistema puede reactivar tu cuenta en tan solo unos segundos si lo deseas.<br><br>¿Te animas? ¡Haz click en el siguiente botón para proceder!' );
define( 'RGPD_EMAIL_ACTIVE_SUBJECT', 'Cuenta activada, ¡cuanto nos alegra verte de nuevo por ' . STORE_NAME . '!' );
define( 'RGPD_EMAIL_ACTIVE', '<span style="font-size: 24px;">¡Hola {USERNAME}!</span><br/><br/>
Te confirmamos mediante este e-mail, que con fecha {DATE} tu cuenta han sido de nuevo activada para que puedas volver a usar todas las funcionalidades de nuestra tienda online.<br/><br/>¡Cuento nos alegra verte de nuevo de vuelta!<br/><br/>Un saludo de parte del equipo de ' . STORE_NAME );
define( 'BUTTON_DISABLE', 'Desactivar' );
define( 'RGPD_EMAIL_DISABLE_SUBJECT', 'Cuenta Desactivada - Que pena que nos abandones... ' . STORE_NAME );
define( 'RGPD_EMAIL_DISABLE', '<span style="font-size: 24px;">Estimado {USERNAME},</span><br/><br/>
Te confirmamos mediante este e-mail, que con fecha {DATE} tu cuenta ha sido desactivada de maneral temporal. Como ya sabes, puedes volver a activarla cuando desee simplemente con una de las siguientes opciones:<br/><br/>
&nbsp;&nbsp;<strong>- Accede a Tu Cuenta:</strong> con tan solo acceder de nuevo a tu cuenta de usuario en nuestra tienda online, podrás activarla.<br/>
&nbsp;&nbsp;<strong>- Solicitar por e-mail/teléfono:</strong> si lo deseas, nos puedes avisar y nuestro equipo te la vuelve a activar por tí.<br/><br/>Esperamos volver a poder verte pronto ¡ha sido una pena que nos abandones!<br><br>Un saludo de parte del equipo de ' . STORE_NAME );

define( 'PRODUCTS_DESCATALOGADO', 'Lo sentimos, pero <span>este producto se encuentra descatalogado</span> y no va a estar de nuevo disponible para su compra.' );
define( 'PRODUCTS_DESCATALOGADO_2', 'Productos relacionados:' );

define('STOCK_LEFT_SINGULAR', 'Queda solo <strong>1 unidad</strong>');
define('STOCK_LEFT_PLURAL', 'Quedan <strong>%s</strong> unidades');

define('AJAX_PAYPAL_TITLE', '¡AVISO IMPORTANTE!');
define('AJAX_PAYPAL_SUBTITLE', 'Atento a la dirección de tu Paypal');
define('AJAX_PAYPAL', 'Usted va a ser redirigido a Paypal, por favor inicie su sesión. Confirme por favor que son correctos el importe y la dirección de entrega de nuestra web antes de realizar el pago. La dirección de envío deberá coincidir con la que aparece en su perfil de Paypal.
<br /><br />
Una vez finalizado el pago será redirigido de nuevo a nuestra web.
<br /><br />
¡Gracias!.');
define('AJAX_PAYPAL_SOBRECARGO', '<br><br><b style="color:#b75e5e;">¡ATENCIÓN!</b> Este método de pago tiene un recargo del ');
define('VIEW_CART', 'Volver a la cesta de la compra');

define('MAYBE_YOU_WANTED_TO_SAY', 'Quizás quisite decir');

define( 'AJAX_BAJO_DEMANDA', 'Ha incluido en su compra un producto <b>bajo pedido</b>, el plazo de entrega puede ser <b>mayor de 30 días</b>.' );


// TRADUCCIONES REDISEÑO //
define( 'TEXT_ATENDEMOS', 'Solo para' );
define( 'TEXT_HE_COMPRADO', 'He comprado otras veces aquí' );
define( 'TEXT_YA_CLIENTE', 'Ya soy cliente' );
define( 'TEXT_QUIERO_REGISTR', 'Quiero registrarme' );
define( 'TEXT_NUEVO_CLIENTE', 'Nuevo cliente' );
define( 'TEXT_NUEVO_INFO', 'Al crear una cuenta en francobordo.com podrás realizar tus compras rápidamente en nuestra tienda virtual, revisar el estado de tus pedidos y consultar tus operaciones anteriores.<br/><br/>¡Adelante! Te estabamos esperando.' );
define( 'TEXT_ACCEDER', 'Acceder al' );
define( 'TEXT_DISTRI_INFO', 'Regístrate y aprovecha los descuentos y ventajas de ser Profesional de la Náutica</p><p>Únete ya a los mas de de 500 Profesionales de la Náutica' );
define( 'TEXT_REGISTRO_PROFES', 'registro profesional' );
define( 'TEXT_PORTES_GRATIS', 'Los portes serán <u>gratuitos</u> para aquellos pedidos que superen los %s€ en Península, 200€ en Baleares (excepto Formentera), ¡Aprovéchalo!</span><span class="dhide">Portes gratuitos para pedidos superiores a %s€ en Península, 200€ en Baleares (excepto Formentera), ¡Aprovéchalo!' );
define( 'TEXT_PLACE_SEARCH', 'Escribe aquí lo que buscas' );
define( 'TEXT_VOLVER', 'volver a' );
define( 'TEXT_SECCION_ANT', 'sección anterior' );
define( 'TEXT_SECCION_TODAS', 'todas las secciones' );
define( 'TEXT_PORTES_CARRITO', '¡En este pedido tienes los portes GRATIS!' );
define( 'TEXT_ESCRIBE', 'Escribe tu' );
define( 'TEXT_AVISO_LEGAL', 'Aviso Legal' );
define( 'TEXT_COOKIES', 'Política de Cookies' );
define( 'TEXT_ENVIOS_DEVO', 'Envíos y Devoluciones' );
define( 'TEXT_CONFIG_COOKIES', 'Configurar cookies' );
define( 'TEXT_DENOX', 'Desarrollado por' );
define( 'TEXT_SELEC_IDIOMA', 'selecciona tu idioma' );
define( 'TEXT_VER_NOVEDADES', 'ver todas<span class="mhide"> las Novedades</span>' );
define( 'TEXT_VER_OFERTAS', 'ver todas<span class="mhide"> las Ofertas</span>' );
define( 'TEXT_DESTACADOS_EN', 'Artículos destacados en' );
define( 'TEXT_VER_DESTACADOS', 'Ver todos los destacados de ' );
define( 'TEXT_VER_DESTACADOS2', 'ver todos<span class="mhide"> los destacados</span>' );
define( 'TEXT_VER_MARCAS', 'ver todas las marcas' );
define( 'TEXT_MOSTRAR_PAG', 'Mostrando <b class="ctdrows">%s</b> <span class="ml-auto-mx">de <b>%s</b> artículos</span>' );
define( 'TEXT_BUSCAR_MARCAR', 'Escribe aquí la Marca que buscas' );
define( 'TEXT_AVISEME', '¡Avíseme!' );
define( 'TEXT_BAJO_PEDIDO', 'Producto <b>Bajo Pedido</b>' );
define( 'TEXT_ENTREGA_EN', 'Entrega en %s días' );
define( 'TEXT_ENTREGA_SUPR', 'Puede ser superior a %s' );
define( 'TEXT_ENTREGA_24', 'Entrega en 24 horas' );
define( 'TEXT_ENTREGA_24_2', 'Entrega en <b>24 horas</b>' );
define( 'TEXT_SIN_STOCK', 'Sin stock' );
define( 'TEXT_IVA', 'IVA' );
define( 'TEXT_COMPARAR', 'Comparar' );
define( 'TEXT_VER_MAS_PRODUCT', 'ver más productos' );
define( 'TEXT_VER_ANTER', 'ver productos anteriores' );
define( 'TEXT_DEJAR_OPINION', 'Deja tu opinión' );
define( 'TEXT_VER_OPCIONES', 'ver opciones' );
define( 'TEXT_ULT_UNID', 'ÚLTIMAS UNIDADES' );
define( 'TEXT_ENVIO_GRATIS', 'Este artículo tiene <b>ENVÍO GRATIS</b>' );
define( 'TEXT_PEDIDO_MINIMO', 'ATENCIÓN:</b> Pedido mínimo <b>%s</b> unidades' );
define( 'TEXT_PUNTOS_ACUMU', 'Con la compra de este artículo acumulas <b>%s puntos para tu próxima compra</b>' );
define( 'TEXT_DUDA', '¿Alguna pregunta sobre este artículo?' );
define( 'TEXT_DUDA_2', 'Aquí resolvemos tus dudas' );
define( 'TEXT_MEJOR_PRECIO', '<b>¡Mejor precio garantizado!</b> ¿Lo has visto más barato?' );
define( 'TEXT_INFORMANOS', 'Infórmanos' );
define( 'TEXT_DESCARGAR', 'Descargar' );
define( 'TEXT_FICHA_PDF', 'Ficha en PDF' );
define( 'TEXT_ANADIR', 'añadir' );
define( 'TEXT_GRATIS', '¡Gratis!' );
define( 'TEXT_REPUESTOS', '¿Necesitas repuestos para este artículo?' );
define( 'TEXT_OTROS_ARTICULOS', 'otros artículos de la categoría' );
define( 'TEXT_CLIENTES_COMPRARON', 'Clientes que compraron este producto' );
define( 'TEXT_PRODUCTOS_DEMANDA', 'Productos bajo pedido' );
define( 'TEXT_DESCUENTO_CANTIDADES', 'Descuentos por cantidades' );
define( 'TEXT_CANTIDAD', 'Cantidad' );
define( 'TEXT_PRECIO_UNIDAD', 'Precio por unidad' );
define( 'TEXT_COMPARAR_PRODUCTOS', 'comparar productos' );
define( 'AUTHORIZE_DATA', 'Autorizo el tratamiento de mis datos con la finalidad de gestionar la contratación de productos o servicios ofrecidos por FRANCOBORDO.' );

define( 'SELECT_COUNTRY_ZONE_CITY', 'Debe seleccionar una provincia o código postal' );
define( 'SELECT_COUNTRY_CITY_NOT_FOUND', '¿No encuentras tu ciudad? Click aquí' );
define( 'SELECT_COUNTRY_CITY_NOT_FOUND_PLACEHOLDER', 'Escriba el nombre de su ciudad' );
define('ENTRY_IAE_ERROR', 'El archivo IAE debe ser pdf, doc, png o jpg');
define('EMAIL_NO_MODEL', 'Sin modelo');
define('TEXT_SELECCIONE', 'Seleccione...');
define('ATTRIBUTES_TITLE_TEXT', 'Elegir opción:');

if (!defined('MODULE_PAYMENT_REDSYS_TEXT_TITLE')) define('MODULE_PAYMENT_REDSYS_TEXT_TITLE', 'Tarjeta de crédito');
if (!defined('MODULE_PAYMENT_REDSYS_TEXT_DESCRIPTION')) define('MODULE_PAYMENT_REDSYS_TEXT_DESCRIPTION', '<strong>Descripción</strong><br>Permitir Realizar pagos a través de la pasarela Redsys<br><br><strong><a href="https://canales.redsys.es/" target="_blank">Administrador TPV</a></strong>');


// TEXTOS PARA opinions
define('OPINIONS_TEXT_CUSTOMER_OPINIONS_HEADER', '<b>Opiniones de nuestros clientes</b>');
define('OPINIONS_TEXT_CUSTOMER_OPINIONS', 'Opiniones de clientes');
define('OPINIONS_TEXT_VIEW_ALL', 'ver todas las opiniones');
define('OPINIONS_TEXT_EXCELENT', 'Excelente');
define('OPINIONS_TEXT_BASED_COMMENTS', 'Basado en %s comentarios');
if (!defined('OPINIONS_TEXT_ANON_CUSTOMER')) define('OPINIONS_TEXT_ANON_CUSTOMER', 'Cliente Anónimo');
define('PROMOTIONS_TEXT_TITLE', 'Promociones');
define('TEXT_RESULTADOS', 'resultados');
