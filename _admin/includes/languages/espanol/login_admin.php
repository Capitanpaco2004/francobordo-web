<?php
/*
  $Id: login.php,v 1.2 2005/05/04 20:11:09 tropic Exp $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2002 osCommerce

  Released under the GNU General Public License
  Incluye La Contribución:
  Tenga acceso con la cuenta llana (v. 2.2a) para el área del Admin del osCommerce (MS2

  Este archivo puede ser suprimido si inhabilita la contribución antedicha
*/

// Translation by Piero Trono http://php-multishop.com


define('NAVBAR_TITLE', 'Acceso a Oleopolis');
define('HEADING_TITLE', 'Bienvenido, puedes entrar en tu parte de administración');
define('TEXT_STEP_BY_STEP', 'paso a paso'); // should be empty


define('HEADING_RETURNING_ADMIN', 'Panel de Login:');
define('HEADING_PASSWORD_FORGOTTEN', 'Password Olvidada:');
define('TEXT_RETURNING_ADMIN', 'Solo Staff!');
define('ENTRY_EMAIL_ADDRESS', 'Dirección E-Mail:');
define('ENTRY_PASSWORD', 'Password:');
define('ENTRY_FIRSTNAME', 'Nombre:');
define('IMAGE_BUTTON_LOGIN', 'Enviar');

define('TEXT_PASSWORD_FORGOTTEN', 'Password olvidada?');

define('ADMIN_EMAIL_RESET_SUBJECT', '%s - Olvido su contraseña %s %s');
define('ADMIN_EMAIL_RESET_TEXT', 'Una nueva contraseña ha sido solicitada de su cuenta en %s.<br/><br/>Por favor, siga este enlace personal para cambiar su contraseña de forma segura:<br/><a href="%s">%s</a><br/><br/>Este enlace se descartará automáticamente después de 24 horas o después de que su contraseña ha sido cambiada.');
define('TEXT_FORGOTTEN_RESET_SUCCESS', 'Por favor, comprueba tu e-mail para obtener instrucciones sobre cómo cambiar tu contraseña, tanto en la bandeja de entrada, como la de correos no deseado o spam. Las instrucciones contienen un enlace que solo es válido durante 24 horas o hasta que la contraseña se ha actualizado.');

define('TEXT_LOGIN_ERROR', 'ERROR: Nombre de usuario o contraseña errónea!');
define('TEXT_FORGOTTEN_ERROR', '<font color="#ff0000"><b>ERROR:</b></font> Nombre de usuario o contraseña no encontrada!');
define('TEXT_FORGOTTEN_FAIL', 'Ya has intentado acceder más de 3 veces. Por seguridad contacta tu Administrador para obtener una nueva contraseña.<br>&nbsp;<br>&nbsp;');
define('TEXT_FORGOTTEN_SUCCESS', 'La contraseña nueva ha sido enviada a tu correo electrónico. Utilízala para acceder al panel.<br>&nbsp;<br>&nbsp;');

define('TEXT_MAIN_RESET', 'Por favor, introduzca una nueva contraseña para su cuenta.');

define('TEXT_NO_RESET_LINK_FOUND', 'Error: El vínculo de cambio de contraseña no se encuentra en nuestros registros, por favor intente de nuevo mediante la generación de un nuevo enlace.');
define('TEXT_NO_EMAIL_ADDRESS_FOUND', 'Error: la dirección de email no fue encontrado en nuestros registros, por favor intente de nuevo.');

define('HEADING_TITLE_RESET', 'Restablecer contraseña');
define('SUCCESS_PASSWORD_RESET', 'Su contraseña ha sido actualizada correctamente. Inicia sesión con tu nueva contraseña.');
define('ENTRY_PASSWORD_NEW_ERROR', 'Su contraseña nueva debe tener al menos ' . ENTRY_PASSWORD_MIN_LENGTH . ' letras, al menos una letra en mayúsculas, al menos una letra en minúsculas y al menos un número.');
define('ENTRY_PASSWORD_NEW_ERROR_NOT_MATCHING', 'La confirmación de su contraseña debe coincidir con su contraseña nueva.');

define('ADMIN_EMAIL_SUBJECT', 'Nueva Contraseña en %s para el administrador %s %s');
define('ADMIN_EMAIL_TEXT', 'Hola %s,' . "\n\n" . 'Puedes entrar en tu área de administración de la tienda virtual con la siguiente contraseña. Después de entrar, es mejor cambiar tu password!' . "\n\n" . 'Dirección Web : %s' . "\n" . 'E-mail usuario: %s' . "\n" . 'Contraseña: %s' . "\n\n" . 'Gracias!' . "\n" . '%s' . "\n\n" . 'Esto es un mail automático enviado por el sistema de la tienda, por favor no respondas!');
?>