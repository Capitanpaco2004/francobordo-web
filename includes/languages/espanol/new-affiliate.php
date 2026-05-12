<?php

/**
 * #XCC-313-91043
 */

define('NAVBAR_TITLE', 'Registro como afiliado');
define('NAVBAR_TITLE_1', 'Registro como afiliado');
define('NAVBAR_TITLE_2', 'Proceso');
define('HEADING_TITLE', 'Crear cuenta de afiliado');
define('TEXT_ORIGIN_LOGIN', '<font color="#FF0000"><small><strong>NOTA:</strong></font></small> Si ya ha pasado por este proceso y tiene una cuenta, por favor <a href="%s">entre</a> en ella.');

define('EMAIL_SUBJECT', 'Bienvenido a nuestros sistema de afiliados' . STORE_NAME);
define('EMAIL_GREET_MR', 'Estimado ' . stripslashes((string)($_POST['lastname'] ?? '')) . ',' . "\n\n");
define('EMAIL_GREET_MS', 'Estimado ' . stripslashes((string)($_POST['lastname'] ?? '')) . ',' . "\n\n");
define('EMAIL_GREET_NONE', 'Estimado ' . stripslashes((string)($_POST['firstname'] ?? '')) . ',' . "\n\n");
define('EMAIL_WELCOME', 'Le damos la bienvenida a <strong>' . STORE_NAME . '</strong>.' . "\n\n");
define('EMAIL_TEXT', 'Ahora puede disfrutar de los <strong>servicios</strong> que le ofrecemos. Algunos de estos servicios son:' . "\n\n" . '<li><strong>Carrito Permanente</strong> - Cualquier producto añadido a su carrito permanecerá en el hasta que lo elimine, o hasta que realice la compra.' . "\n" . '<li><strong>Libro de Direcciones</strong> - Podemos enviar sus productos a otras direcciones aparte de la suya! Esto es perfecto para enviar regalos de cumpleaños directamente a la persona que cumple años.' . "\n" . '<li><strong>Historial de Pedidos</strong> - Vea la relación de compras que ha realizado con nosotros.' . "\n" . '<li><strong>Comentarios</strong> - Comparta su opinión sobre los productos con otros clientes.' . "\n" . '<li><strong>Boletín de Noticias</strong> - subscríbase a nuestro Boletín y estarás al día de todas nuestras ofertas y novedades.' . "\n\n");
define('EMAIL_CONTACT', 'Para cualquier consulta sobre nuestros servicios, por favor escriba a: ' . STORE_OWNER_EMAIL_ADDRESS . '.' . "\n\n");
define('EMAIL_WARNING', '<strong>Nota:</strong> Esta dirección fue suministrada por uno de nuestros clientes. Si usted no se ha suscrito como socio, por favor comuníquelo a ' . STORE_OWNER_EMAIL_ADDRESS . '.' . "\n");
define('EMAIL_WELCOME_POINTS', '<li><strong>Programa de Puntos</strong> - Ahora formas parte del programa de Puntos de nuestra web! Al ser un nuevo usuario, nosotros le regalamos %s con un total de %s puntos para que realice su próxima compra valorados en %s .' . "\n" . 'Por favor visita %s y las condiciones de uso.');
define('EMAIL_POINTS_ACCOUNT', 'Cuenta del Sistema de Puntos');
define('EMAIL_POINTS_FAQ', 'Programa de Puntos FAQ');

define('CREATE_ACCOUNT_TITLE_SOY', 'Soy');
define('CREATE_ACCOUNT_PARTICULAR', 'Particular');
define('CREATE_ACCOUNT_EMPRESA', 'Empresa, autónomo u organización');

define('RGPD_WINDOW_MODAL_TITLE_DOB', '¿Eres mayor de 16 años?');
define('RGPD_WINDOW_MODAL_SUBTITLE_DOB', 'Debido a la nueva Ley de protección de datos Europea, debes de tener 16 años o más como establece el artículo 8 de la RGPD, para poder seguir en este sitio debes aceptar estos términos antes de registrarte como cliente.');
define('RGPD_WINDOW_MODAL_DOB_DENEGATE', 'No soy mayor');
define('RGPD_WINDOW_MODAL_DOB_ACCEPT', 'Aceptar y continuar');

define('ENTRY_DATE_OF_BIRTH_OLD_ERROR', 'Debido a la  Ley de protección de datos Europea, debes de tener 16 años o más como estable el artículo 8 de la RGPD, para poder registrarte como cliente en este sitio debes cumplir estos términos.');

define('ENTRY_USER_NAME', 'Nombre de usuario en redes sociales:');
define('ENTRY_USER_NAME_TEXT', '*');
define('ENTRY_USER_NAME_ERROR', 'Para el registro como afiliado, necesitamos el nombre de usuario usado comunmente en redes sociales.');

define('ENTRY_SOCIAL_NETWORKS', 'Enlaces a redes sociales:');
define('ENTRY_SOCIAL_NETWORKS_TEXT', '*');
define('ENTRY_SOCIAL_NETWORKS_ERROR', 'Necesitamos que nos digas algunos links a tus redes sociales.');
define('AFFILIATE_EXISTS', sprintf('Ya estás registrado como afiliado. En el caso de que no recuerdes tu clave puedes <a href="%s">solicitar una nueva desde aquí</a>', tep_href_link('password_forgotten.php')));

define('AFFILIATES_DESCRIPTION', '
<p class="pageHeading">¡Únete a nosotros como Afiliado!</p>
<p><strong>'.STORE_NAME.'</strong> busca crear un equipo de personas con presencia en redes sociales capaces de crear y aportar contenido de calidad para la marca.</p>
<p>Una vez registrado, nuestro equipo de marketing revisará tus redes sociales para ver si te consideramos un integrante digno de formar parte de la comunidad de <strong>'.STORE_NAME.'</strong>. Es importante que entendáis que no todos seréis aprobados como afiliados, para ello debéis de cumplir con una serie de requisitos de calidad que consideramos indispensables para poder ser unirte a nosotros: ¡Tendremos en cuenta la profesionalidad, la cantidad de seguidores, la fuerza del usuario en la redes sociales y sobretodo que transmitas la esencia de nuestra marca!</p>
<p>Si eres uno de los afortunados contarás con un sistema de comisiones del que podrás beneficiarte por cada venta donde se utilice tu cupón descuento. ¡En '.STORE_NAME.' todos ganamos!</p>
<p>Queremos dar la máxima libertad a nuestros colaboradores, por lo que tu decides sobre que productos hablar y cuales de ellos promocionar. Unicamente pedirte la sinceridad mas absoluta,  porque nos preocupamos por cada productos, cada sabor, y cada textura para ofrecer siempre el 100% de calidad.</p>
<p>¿A que esperas? Únete a nosotros y aporta tu granito de arena.</p>
<p>¡Danos y nosotros te daremos aún más!</p>
');

define( 'RGPD_INGRESOS', 'Ingresos por comisiones' );