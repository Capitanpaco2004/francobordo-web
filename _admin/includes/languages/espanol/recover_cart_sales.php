<?php
$logo = HTTP_SERVER . DIR_WS_CATALOG . DIR_THEME .'logo-trans.png';
$url = HTTP_SERVER . DIR_WS_CATALOG ;

define('MESSAGE_STACK_CUSTOMER_ID', 'Carrito de Cliente-ID ');
define('MESSAGE_STACK_DELETE_SUCCESS', ' borrado Satisfactoriamente');
define('HEADING_TITLE', 'recuperar carritos olvidados');
define('HEADING_EMAIL_SENT', 'E-mail reportes');
define('EMAIL_TEXT_1', '<tbd>' . "\n\n");
define('EMAIL_TEXT_2', '<tbd>' . "\n\n");
define('EMAIL_TEXT_ACCOUNT', '<a href="'.$url.'"><img src="'.$logo.'" alt="'.$logo.'" border="0"></a><br />Estimado cliente,<br><br>Agradecemos tu reciente visita a <a href="'.$url.'">Francobordo.com</a><br><br>Hemos podido comprobar que hace unos días intentaste realizar un pedido en nuestra tienda virtual y nos gustaría saber si tuviste algún problema, experimentaste algún fallo técnico o simplemente no te convenció algún aspecto en el proceso de compra. Tal vez incluso necesites más información sobre los productos, y para ello ¡queremos ayudarte!<br><br>Desde Francobordo.com valoramos toda la información que nos aportas para poder mejorar cada día y poder ofrecerte el mejor servicio.<br><br>Si aún tienes problemas para finalizar tu pedido o tienes dudas de los productos que estás adquiriendo y necesitas asesoramiento de alguno de nuestros especialistas, puedes contactar con nosotros a través del teléfono <strong>916 528 858</strong> y te ayudaremos encantados.<br><br>Si desea acceder a tu cuenta de cliente que tienes en nuestra tienda virtual, desde donde podrás comprobar tu pedido pendiente, puedes hacerlo desde el siguiente enlace:');
define('EMAIL_TEXT_SHOPPINGCART', 'La cesta de compras de tu cuenta:');

define('EMAIL_TEXT_3', 'Productos en su Cesta:');
define('EMAIL_TEXT_4', '<tb>Reciba un cordial saludo.<br><br>Departamento Atención al Cliente<br>Teléfono: 916 528 858<br>Email: info@francobordo.com<br><a href="' . HTTP_CATALOG_SERVER . '">' . HTTP_CATALOG_SERVER  . '</a>');

define('EMAIL_FEEDBACK', STORE_OWNER_EMAIL_ADDRESS);

define('DAYS_FIELD_PREFIX', 'Enseñar para los últimos ');
define('DAYS_FIELD_POSTFIX', ' dias ');
define('DAYS_FIELD_BUTTON', 'Go');
define('TABLE_HEADING_DATE', 'FECHA');
define('TABLE_HEADING_CONTACT', 'CONTACTADO');
define('TABLE_HEADING_CUSTOMER', 'NOMBRE CLIENTE');
define('TABLE_HEADING_EMAIL', 'E-MAIL');
define('TABLE_HEADING_PHONE', 'TELEFONO');
define('TABLE_HEADING_MODEL', 'PRODUCTO');
define('TABLE_HEADING_DESCRIPTION', 'DESCRIPCION');
define('TABLE_HEADING_QUANTY', 'NUM');
define('TABLE_HEADING_PRICE', 'PRECIO');
define('TABLE_HEADING_TOTAL', 'TOTAL');
define('TABLE_GRAND_TOTAL', 'Gran Total: ');
define('TABLE_CART_TOTAL', 'Carrito Total: ');
define('TEXT_CURRENT_CUSTOMER', 'CLIENTE');
define('TEXT_SEND_EMAIL', 'Enviar E-mail');
define('TEXT_RETURN', '[Clic aquí para volver]');
define('TEXT_NOT_CONTACTED', 'NO Avisado!!');
define('PSMSG', 'Mensaje adicional a enviar: ');



define('EMAIL_TEXT_LOGIN', 'Login en tu cuenta aqui:');
define('EMAIL_SEPARATOR', '__');
define('EMAIL_TEXT_SUBJECT', '¿Te dejaste algo en tu cesta?');
define('EMAIL_TEXT_SALUTATION', '<table width="600" border="0" cellspacing="0" cellpadding="8"><tr><td style="background-color:#2781bb;"><a href="http://www.francobordo.com/" target="_blank"><img border="0" src="http://www.francobordo.com/images/francobordo-logomail.gif" alt="Francobordo.com" /></a></td></tr><tr><td style="background-color:#f4f4f4; font-family:Arial, Helvetica, sans-serif; font-size:12px; padding:20px">Estimado ' );
define('EMAIL_TEXT_NEWCUST_INTRO', "\n\n" . 'Gracias por visitar ' . STORE_NAME .
                                   ' y considerarnos para sus compras. ');
define('EMAIL_TEXT_CURCUST_INTRO', "\n\n" . 'Queremos darte las gracias por haber llenado tu carrito en ' .
                                   STORE_NAME . ' hace unos dias. ');
define('EMAIL_TEXT_BODY_HEADER',
	'Hemos detectado que durante tu última visita en nuestra tienda te dejaste ' . 'los siguientes productos en tu carrito de la compra, pero por alguna razón ' . 'no pudiste finalizar el pedido.' . "\n\n" . '<b>Contenido de tu carrito:</b>' . "\n\n"  );
	
define('EMAIL_TEXT_BODY_FOOTER',
	'Estaríamos muy agradecidos si nos pudieras indicar si tuviste algún problema técnico o símplemente no te convenció algún aspecto de la compra. ' .
	'Puede ser que necesites mas información sobre los productos y para ello queremos ayudarte. ' ."\n\n".
	'Desde Francobordo.com valoramos toda la información que nos aportes para poder <b>mejorar nuestro servicio cada día</b>.'."\n\n". ($_POST['message'] ?? '')."\n\n".	'De nuevo, te damos las GRACIAS por tu tiempo y si necesitas algún tipo de asesoramiento puedes contactar con nosotros a través del teléfono '."<b>" .'916 528 858'. "</b>".
	' ' . STORE_NAME .  "\n\n". 'Atentamente,' ."\n\n" );
?>