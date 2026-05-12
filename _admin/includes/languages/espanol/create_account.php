<?php
/*
  $Id: create_account.php,v 1.13 2003/05/19 20:17:51 hpdl Exp $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
*/

define('HEADING_TITLE_CREATE_ACCOUNT', 'Crear una nueva cuenta de Cliente');
define('PULL_DOWN_DEFAULT', 'Por favor, seleccione...');

define('HEADING_TITLE_CREATE_ACCOUNT_SUCCESS', 'Se ha creado la cuenta con éxito');

define('EMAIL_PASS_1', 'Contraseña: ');
define('EMAIL_PASS_2', "\n" . 'Puedes modificarla despues de acceder a tu cuenta.' . "\n\n");

define('EMAIL_SUBJECT', 'Bienvenido a ' . STORE_NAME);
define('EMAIL_GREET_MR', 'Estimado ' . stripslashes((string)($_POST['lastname'] ?? '')) . ',' . "\n\n");
define('EMAIL_GREET_MS', 'Estimado ' . stripslashes((string)($_POST['lastname'] ?? '')) . ',' . "\n\n");
define('EMAIL_GREET_NONE', 'Estimado ' . stripslashes((string)($_POST['firstname'] ?? '')) . ',' . "\n\n");
define('EMAIL_WELCOME', 'Le damos la bienvenida a <b>' . STORE_NAME . '</b>.' . "\n\n");
define('EMAIL_TEXT', 'Ahora puede disfrutar de los <b>servicios</b> que le ofrecemos. Algunos de estos servicios son:' . "\n\n" . '<li><b>Carrito Permanente</b> - Cualquier producto añadido a su carrito permanecera en el hasta que lo elimine, o hasta que realice la compra.' . "\n" . '<li><b>Libro de Direcciones</b> - Podemos enviar sus productos a otras direcciones aparte de la suya! Esto es perfecto para enviar regalos de cumpleaños directamente a la persona que cumple años.' . "\n" . '<li><b>Historia de Pedidos</b> - Vea la relacion de compras que ha realizado con nosotros.' . "\n" . '<li><b>Comentarios</b> - Comparta su opinion sobre los productos con otros clientes.' . "\n\n");
define('EMAIL_CONTACT', 'Para cualquier consulta sobre nuestros servicios, por favor escriba a: ' . STORE_OWNER_EMAIL_ADDRESS . '.' . "\n\n");
define('EMAIL_WARNING', '<b>Nota:</b> Esta direccion fue suministrada por uno de nuestros clientes. Si usted no se ha suscrito como socio, por favor comuniquelo a ' . STORE_OWNER_EMAIL_ADDRESS . '.' . "\n");
define('EMAIL_WELCOME_POINTS', '<li><strong>Programa de Puntos</strong> - Ahora formas parte del programa de Puntos de nuestra web! Al ser un nuevo usuario, nosotros le regalamos %s con un total de %s puntos para que realices tu proxima compra valorados en %s .' . "\n" . 'Por favor visita %s y las condiciones de uso.');
define('EMAIL_POINTS_ACCOUNT', 'Cuenta del Sistema de Puntos');
define('EMAIL_POINTS_FAQ', 'Programa de Pûntos FAQ');
?>