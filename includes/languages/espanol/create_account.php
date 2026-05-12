<?php
	define( 'NAVBAR_TITLE', 'Crear una Cuenta' );
	define( 'NAVBAR_TITLE_1', 'Crear una Cuenta' );
	define( 'NAVBAR_TITLE_2', 'Proceso' );
	define( 'HEADING_TITLE', 'Crear cuenta' );
	define( 'TEXT_ORIGIN_LOGIN', '<font color="#FF0000"><small><strong>NOTA:</strong></font></small> Si ya ha pasado por este proceso y tiene una cuenta, por favor <a href="%s">entre</a> en ella.' );
	define( 'TEXT_PROFESIONAL_WARNING', '<font color="#FF0000"><small><strong>NOTA:</strong></font></small> <b>Se está usted registrando como profesional, no podrá acceder a nuestro precios como profesional hasta que se valide por nuestro personal que esta cuenta reúne los requisitos de profesional del sector náutico. Este validación puede llevar un tiempo de hasta 48 horas.</b><br /><b>Solo damos de alta como profesionales a aquellas empresas o autónomos cuya actividad es la compraventa de accesorios náuticos y mantenimiento o reparación de embarcaciones o buques.</b>' );
	define( 'EMAIL_SUBJECT', 'Bienvenido a ' . STORE_NAME );
	define( 'EMAIL_GREET_MR', 'Estimado ' . stripslashes($_POST['lastname'] ?? '') . ',' . "\n\n" );
	define( 'EMAIL_GREET_MS', 'Estimado ' . stripslashes($_POST['lastname'] ?? '') . ',' . "\n\n" );
	define( 'EMAIL_GREET_NONE', 'Estimado ' . stripslashes($_POST['firstname'] ?? '') . ',' . "\n\n" );
	define( 'EMAIL_WELCOME', 'Le damos la bienvenida a <strong>' . STORE_NAME . '</strong>.' . "\n\n" );
	define( 'EMAIL_TEXT', 'Ahora puede disfrutar de los <strong>servicios</strong> que le ofrecemos. Algunos de estos servicios son:' . "\n\n" . '<li><strong>Carrito Permanente</strong> - Cualquier producto añadido a su carrito permanecerá en el hasta que lo elimine, o hasta que realice la compra.' . "\n" . '<li><strong>Libro de Direcciones</strong> - Podemos enviar sus productos a otras direcciones aparte de la suya! Esto es perfecto para enviar regalos de cumpleaños directamente a la persona que cumple años.' . "\n" . '<li><strong>Historial de Pedidos</strong> - Vea la relación de compras que ha realizado con nosotros.' . "\n" . '<li><strong>Comentarios</strong> - Comparta su opinión sobre los productos con otros clientes.' . "\n" . '<li><strong>Boletín de Noticias</strong> - subscríbase a nuestro Boletín y estarás al día de todas nuestras ofertas y novedades.' . "\n\n" );
	define( 'EMAIL_CONTACT', 'Para cualquier consulta sobre nuestros servicios, por favor escriba a: ' . STORE_OWNER_EMAIL_ADDRESS . '.' . "\n\n" );
	define( 'EMAIL_WARNING', '<strong>Nota:</strong> Esta dirección fue suministrada por uno de nuestros clientes. Si usted no se ha suscrito como socio, por favor comuníquelo a ' . STORE_OWNER_EMAIL_ADDRESS . '.' . "\n" );

	define( 'CREATE_ACCOUNT_TITLE_SOY', 'Soy' );
	define( 'CREATE_ACCOUNT_PARTICULAR', 'Particular' );
	define( 'CREATE_ACCOUNT_EMPRESA', 'Empresa, autónomo u organización' );
	define( 'CREATE_ACCOUNT_RE', 'Me acojo al recargo de equivalencia' );

	define( 'RGPD_WINDOW_MODAL_TITLE_DOB', '¿Eres mayor de 16 años?' );
	define( 'RGPD_WINDOW_MODAL_SUBTITLE_DOB', 'Debido a la nueva Ley de protección de datos Europea, debes de tener 16 años o más como establece el artículo 8 de la RGPD, para poder seguir en este sitio debes aceptar estos términos antes de registrarte como cliente.' );
	define( 'RGPD_WINDOW_MODAL_DOB_DENEGATE', 'No soy mayor' );
	define( 'RGPD_WINDOW_MODAL_DOB_ACCEPT', 'Aceptar y continuar' );

	define('ENTRY_DATE_OF_BIRTH_OLD_ERROR', 'Debido a la  Ley de protección de datos Europea, debes de tener 16 años o más como estable el artículo 8 de la RGPD, para poder registrarte como cliente en este sitio debes cumplir estos términos.');

?>