<?php
//fast easy checkout start
define('NAVBAR_TITLE', 'Sus datos');
define('HEADING_TITLE', 'Sus datos');
define('LOGINBOX_NEW_CUSTOMER', 'Primera compra - Por favor rellene sus datos:');
define('LOGINBOX_EXISTING_CUSTOMER', ' EL cliente ya existe');
define('LOGINBOX_EXSISTING_CUSTOMER_NOW', '<b>Si ya tiene cuenta de cliente por favor pulse aquí para entrar en su cuenta</b>  ');
define('CATEGORY_CREATE_ACCOUNT', '<b class="cabecera_form5">¿Quiere crear una cuenta de cliente?</b>');
define('YES_ACCOUNT', '<b>Si por favor, quiero crear mi cuenta de cliente.</b>');
define('NO_ACCOUNT', 'No gracias, esto es una prueba de compra.');
define('HEADING_TITLES','Formulario de Pedido');
define('TITLE_SHIPPING_ADDRESS', 'Dirección de envío:');
define('TITLE_FORM', 'Formulario de pedido rápido');
define('TEXT_ENTER_SHIPPING_INFORMATION',  '');
define('TITLE_PAYMENT_ADDRESS', 'Dirección del cliente');
define('PAYMENT_SHIPMENT', 'Presione si la dirección de envio es diferente de la dirección de la cuenta del cliente:');
define('TEXT_EDIT', 'Editar');
define('HEADING_TITLE_2', ' Crear una cuenta');
define('HEADING_TITLE2', 'Crear una cuenta');
define('PRIMARY_ADDRESS_DESCRIPTION', '<font color="#FF0000"><small><b>Nota:</b></font></small> Si usted ya posee una cuenta con nosotros por favor ingrese su cuenta de e-mail y contraseña en <a href="login.php"><u>la pagina de acceso a su cuenta</u></a>.');
 define('ENTRY_PASSWORD_CURRENT2', 'Introduzca contraseña');
  define('LOGINBOX_EXSISTING_CUSTOMER', '<b class="cabecera_form">Si ya es cliente introduzca el e-mail y contraseña de su cuenta:</b>');
  define('LOGINBOX_EMAIL', 'Dirección de Email');
  define('LOGINBOX_PASSWORD', 'Contraseña');
  define('LOGINBOX_FORGOT_PASSWORD', 'recordar contraseña');
  define('LOGINBOX_TEXT_PASSWORD', '¿Ha olvidado su contraseña?, ');
  define('ENTRY_CREATEACCOUNT', 'Sólo dándose de alta como cliente tendra acceso a nuestra promocion de puntos y a consultar el estado de sus pedidos y no tendrá que volver a rellenar sus datos cada vez que compre en Francobordo. Solo tiene que introducir una contraseña y se creará su cuenta<br>');
 define('ENTRY_PASSWORD_NEW2', 'Introduzca Contraseña');
 define('SUCCESS_PASSWORD_UPDATED', 'Su cuenta se ha creado correctamente');
define('ERROR_CURRENT_PASSWORD_NOT_MATCHING', 'Su contraseña de confirmación no se encuentra grabada en nuestro registro. Por favor intentelo de nuevo.');

define('ENTRY_SHIPPING_CITY_ERROR', 'La ciudad de envío debe contener al menos ' . ENTRY_CITY_MIN_LENGTH . ' caracteres.');
define('ENTRY_SHIPPING_STATE_ERROR_SELECT', 'Por favor seleccione una provincia para el envío del despegable de provincias.');
define('ENTRY_SHIPPING_TELEPHONE_NUMBER_ERROR', 'El Número de Teéfono de envío de contener un minimo de  ' . ENTRY_TELEPHONE_MIN_LENGTH . ' caracteres.');
define('ENTRY_SHIPPING_POST_CODE_ERROR', 'El código postal de envío debe contener un minimo de ' . ENTRY_POSTCODE_MIN_LENGTH . ' números.');

define('ENTRY_SHIPPING_STREET_ADDRESS_ERROR', 'La dirección de envío debe contener un mínimo de ' . ENTRY_STREET_ADDRESS_MIN_LENGTH . ' caracteres.');
define('ENTRY_SHIPPING_COUNTRY_ERROR', 'Debe elegir un pais para el envío del menú desplegable.');
define('ENTRY_SHIPPING_LAST_NAME_ERROR', 'El apellido para envío debe contener un m&oacute;inimo de ' . ENTRY_LAST_NAME_MIN_LENGTH . ' caracteres.');
define('ENTRY_SHIPPING_FIRST_NAME_ERROR', 'El nombre para el envío debe contener un mínimo de ' . ENTRY_FIRST_NAME_MIN_LENGTH . ' caracteres.');
define('ENTRY_SHIPPING_TELEPHONE_NUMBER_ERROR', 'Su n&oacute;umero de teléfono debe contener un mínimo de ' . ENTRY_TELEPHONE_MIN_LENGTH . ' caracteres.');
define('ERROR_TOTAL_NOW', 'Por favor compruebe su nuevo Total y continue');
define('MY_PASSWORD_TITLE', 'Por favor elija una contraseña para crear una cuenta de cliente');
  define('EMAIL_TEXT_INVOICE_PASSWORD', 'Gracias por comprar con nosotros. Usted puede crear una cuenta para poder consultar el estado de sus pedidos y no tener que volver a rellenar todos los datos en la siguiente compra. Por favor siga el link de abajo para crear una cuenta');
  define('EMAIL_TEXT_INVOICE_PASSWORD_NOLINK', 'Si no funciona el link copie y pegue la url en su navegador para crear una cuenta de cliente con nosotros.');
 define('PASSWORD_CREATED', 'Hemos creado una contraseña para su cuenta. Su contraseña es');
 define('FEC_TEXT_SUCCESS', '¡Su pedido ha sido procesado satisfactoriamente!. Sus productos llegararán al destino indicado en 3 a 7 días laborables.');
  define('FEC_TEXT_SEE_ORDERS', '');
   define('FEC_TEXT_CONTACT_STORE_OWNER', 'Por favor cualquier consulta que quiera dirigirnos hagalo a  <a href="' . tep_href_link(FILENAME_CONTACT_US) . '">store owner</a>.');
    define('FEC_TEXT_THANKS_FOR_SHOPPING', 'Gracias por comprar con nosotros. Usted puede crear una cuenta para poder consultar el estado de sus pedidos y no tener que volver a rellenar todos los datos en la siguiente compra así como obtener las ventajas de nuestros otros miembros. Simplemente elija un password abajo para crear su cuenta. ');
//fast easy checkout end
?>