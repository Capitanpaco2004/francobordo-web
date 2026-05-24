<?php

/**
 * #XCC-313-91043
 */

define('NAVBAR_TITLE', 'Registration as an affiliate');
define('NAVBAR_TITLE_1', 'Registration as an affiliate');
define('NAVBAR_TITLE_2', 'Process');
define('HEADING_TITLE', 'Create affiliate account');
define('TEXT_ORIGIN_LOGIN', '<font color="#FF0000"><small><strong>NOTe:</strong></font></small> If you have already gone through this process and have an account, please <a href="%s">log in</a> to it.');

define('EMAIL_SUBJECT', 'Welcome to our affiliate system' . STORE_NAME);
define('EMAIL_GREET_MR', 'Dear ' . stripslashes($_POST['lastname'] ?? '') . ',' . "\n\n");
define('EMAIL_GREET_MS', 'Dear ' . stripslashes($_POST['lastname'] ?? '') . ',' . "\n\n");
define('EMAIL_GREET_NONE', 'Dear ' . stripslashes($_POST['firstname'] ?? '') . ',' . "\n\n");
define('EMAIL_WELCOME', 'We welcome you to <strong>' . STORE_NAME . '</strong>.' . "\n\n");
define('EMAIL_TEXT', 'Now you can enjoy the <strong>services</strong> we offer you. Some of these services are:' . "\n\n" . '<li><strong>Carrito Permanente</strong> - Cualquier producto añadido a su carrito permanecerá en el hasta que lo elimine, o hasta que realice la compra.' . "\n" . '<li><strong>Libro de Direcciones</strong> - Podemos enviar sus productos a otras direcciones aparte de la suya! Esto es perfecto para enviar regalos de cumpleaños directamente a la persona que cumple años.' . "\n" . '<li><strong>Historial de Pedidos</strong> - Vea la relación de compras que ha realizado con nosotros.' . "\n" . '<li><strong>Comentarios</strong> - Comparta su opinión sobre los productos con otros clientes.' . "\n" . '<li><strong>Boletín de Noticias</strong> - subscríbase a nuestro Boletín y estarás al día de todas nuestras ofertas y novedades.' . "\n\n");
define('EMAIL_CONTACT', 'Para cualquier consulta sobre nuestros servicios, por favor escriba a: ' . STORE_OWNER_EMAIL_ADDRESS . '.' . "\n\n");
define('EMAIL_WARNING', '<strong>Nota:</strong> Esta dirección fue suministrada por uno de nuestros clientes. Si usted no se ha suscrito como socio, por favor comuníquelo a ' . STORE_OWNER_EMAIL_ADDRESS . '.' . "\n");
define('EMAIL_WELCOME_POINTS', '<li><strong>Programa de Puntos</strong> - Ahora formas parte del programa de Puntos de nuestra web! Al ser un nuevo usuario, nosotros le regalamos %s con un total de %s puntos para que realice su próxima compra valorados en %s .' . "\n" . 'Por favor visita %s y las condiciones de uso.');
define('EMAIL_POINTS_ACCOUNT', 'Cuenta del Sistema de Puntos');
define('EMAIL_POINTS_FAQ', 'Programa de Puntos FAQ');

define('CREATE_ACCOUNT_TITLE_SOY', 'Soy');
define('CREATE_ACCOUNT_PARTICULAR', 'Particular');
define('CREATE_ACCOUNT_EMPRESA', 'Company, self-employed or organization');

define('RGPD_WINDOW_MODAL_TITLE_DOB', 'Are you over 16?');
define('RGPD_WINDOW_MODAL_SUBTITLE_DOB', 'Due to the new European data protection law, you must be 16 years old or older as established in article 8 of the RGPD, in order to continue on this site you must accept these terms before registering as a client.');
define('RGPD_WINDOW_MODAL_DOB_DENEGATE', 'I\'m not older');
define('RGPD_WINDOW_MODAL_DOB_ACCEPT', 'Accept and continue');

define('ENTRY_DATE_OF_BIRTH_OLD_ERROR', 'Due to the European Data Protection Law, you must be 16 years old or older as established in article 8 of the RGPD, in order to register as a client on this site you must comply with these terms.');

define('ENTRY_USER_NAME', 'Username in social networks:');
define('ENTRY_USER_NAME_TEXT', '*');
define('ENTRY_USER_NAME_ERROR', 'For registration as an affiliate, we need the username commonly used in social networks.');

define('ENTRY_SOCIAL_NETWORKS', 'Links to social networks:');
define('ENTRY_SOCIAL_NETWORKS_TEXT', '*');
define('ENTRY_SOCIAL_NETWORKS_ERROR', 'We need you to tell us some links to your social networks.');
define('AFFILIATE_EXISTS', sprintf('You are already registered as an affiliate. In case you don\'t remember your password you can <a href="%s">request a new one from here</a>', tep_href_link('password_forgotten.php')));

define('AFFILIATES_DESCRIPTION', '
<p class="pageHeading">Join us as an Affiliate!</p>
<p><strong>'.STORE_NAME.'</strong> seeks to create a team of people with a presence in social networks capable of creating and providing quality content for the brand.</p>
<p>Once registered, our marketing team will review your social networks to see if we consider you a worthy member of the <strong>'.STORE_NAME.'</strong> community. It is important that you understand that not everyone will be approved as affiliates, for this you must meet a series of quality requirements that we consider essential to be able to join us: We will take into account the professionalism, the number of followers, the strength of the user! in social networks and above all that you transmit the essence of our brand!</p>
<p>If you are one of the lucky ones, you will have a commission system that you can benefit from for each sale where your discount coupon is used. In '.STORE_NAME.' everybody wins!</p>
<p>We want to give maximum freedom to our collaborators, so you decide which products to talk about and which ones to promote. We only ask for the most absolute sincerity, because we care about each product, each flavor, and each texture to always offer 100% quality.</p>
<p>What are you waiting for? Join us and contribute your grain of sand.</p>
<p>Give us and we will give you even more!</p>
');

define( 'RGPD_INGRESOS', 'Commission income' );