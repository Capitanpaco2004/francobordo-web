<?php

define('HEADING_TITLE', 'Editar cuenta de administrador');
define('HEADING_SUBTITLE', 'Editar información de la cuenta de administración.');
define('TABLE_HEADING_ACCOUNT', 'Datos de mi cuenta');
define('TABLE_HEADING_PASSWORD', 'Cambio de contraseña');
define('TABLE_HEADING_DATES', 'Información de fechas');
define('TEXT_BUTTON_EDIT', 'Editar cuenta');
define('TEXT_BUTTON_CHECK', 'Comprobar contraseña');

define('TEXT_INFO_FULLNAME', 'Nombre:');
define('TEXT_INFO_FIRSTNAME', 'Nombre:');
define('TEXT_INFO_LASTNAME', 'Apellido:');
define('TEXT_INFO_EMAIL', 'Email:');
define('TEXT_INFO_NEW_PASSWORD', 'Nueva contraseña:');
define('TEXT_INFO_PASSWORD', 'Contraseña:');
define('TEXT_INFO_PASSWORD_HIDDEN', '****');
define('TEXT_INFO_PASSWORD_CONFIRM', 'Repetir contraseña:');
define('TEXT_INFO_CREATED', 'Fecha de alta:');
define('TEXT_INFO_LOGDATE', 'Fecha de último acceso:');
define('TEXT_INFO_LOGNUM', 'Número de log:');
define('TEXT_INFO_GROUP', 'Nivel de grupo administrador:');
define('TEXT_INFO_MODIFIED', 'Fecha de modificación: ');
define('TEXT_INFO_HEADING_CONFIRM_PASSWORD', 'Confirmar contraseña');

define('TEXT_WRNG_PASSWORD', 'Recuerda, para mejorar su protección, la contraseña debe tener al menos un carácter minúscula, un carácter mayúscula, un dígito numérico y un signo especial @#-_$%^&+=§!? ');

define('TEXT_ERROR_INCORRECT_PASSWORD', 'La contraseña introducida no es la correcta.');
define('TEXT_ERROR_EMAIL_EXISTS', 'Ya existe una cuenta con ese mismo email.');
define('TEXT_ERROR_PASSWORD_CONFIRM', 'No has introducido la misma contraseña en repetir contraseña.');
define('TEXT_ERROR_PASSWORD_SAME', 'Estás introduciendo la misma contraseña que ya tiene configurada la cuenta.');
define('TEXT_ERROR_PASSWORD_REGEX', 'La contraseña debe tener al menos un carácter minúscula, un carácter mayúscula, un dígito numérico y un signo especial @#-_$%^&+=§!?');

define('TEXT_INFO_INTRO_DEFAULT_FIRST_TIME', '<b><font color="#ff0000">ATENCIÓN:</font></b><br>¡Hola <b>%s</b>!, Acabas de acceder por primera vez en tu cuenta de administración.<br><br>¿Que tal si empezamos modificando tu contraseña para personalizarla por una propia más segura que la aleatoria?');

define('ADMIN_EMAIL_SUBJECT', 'Cambio de Información Personal en %s para %s %s');
define('ADMIN_EMAIL_TEXT', 'Hola %s,' . "\n\n" . 'Esto es un e-mail automático para informarte de que tus datos personales, tu password o tu nivel de acceso a las categorías de la página han sido modificados. Si esto no era tu intención, contacta con el administrador inmediatamente para ver quien ha realizado los cambios oportunos.' . "\n\n" . 'Web : %s' . "\n" . 'Usuario: %s' . "\n" . 'Contraseña: %s' . "\n\n" . '¡Gracias!' . "\n" . '%s' . "\n\n" . 'Esto es un mail automático, por favor no respondas!');

// 2FA
define('TABLE_HEADING_2FA', 'Autenticacion de doble factor (2FA)');
define('TEXT_2FA_STATUS', 'Estado 2FA:');
define('TEXT_2FA_ENABLED', 'Activo');
define('TEXT_2FA_DISABLED', 'Inactivo');
define('TEXT_2FA_BUTTON_ENABLE', 'Activar 2FA');
define('TEXT_2FA_BUTTON_DISABLE', 'Desactivar 2FA');
define('TEXT_2FA_BUTTON_REGEN_CODES', 'Regenerar codigos');
define('TEXT_2FA_RECOVERY_REMAINING', 'Codigos de recuperacion restantes:');
define('TEXT_2FA_SETUP_TITLE', 'Configurar autenticacion de doble factor');
define('TEXT_2FA_SETUP_PASSWORD_INTRO', 'Para activar la autenticacion de doble factor, introduce tu contrasena actual como confirmacion.');
define('TEXT_2FA_SETUP_INTRO', 'Escanea el codigo QR con tu aplicacion de autenticacion (Google Authenticator, Authy, etc.) e introduce el codigo de 6 digitos para verificar.');
define('TEXT_2FA_SETUP_MANUAL_KEY', 'Clave manual (si no puedes escanear el QR):');
define('TEXT_2FA_SETUP_CODE_LABEL', 'Codigo de verificacion:');
define('TEXT_2FA_SETUP_CODE_PLACEHOLDER', 'Introduce el codigo de 6 digitos');
define('TEXT_2FA_SETUP_SUBMIT', 'Verificar y activar');
define('TEXT_2FA_ERROR_INVALID_CODE', 'El codigo introducido no es valido. Intentalo de nuevo.');
define('TEXT_2FA_ERROR_NO_PENDING', 'No hay un setup de 2FA en proceso. Vuelve a empezar.');
define('TEXT_2FA_ACTIVATED_TITLE', '2FA activado correctamente');
define('TEXT_2FA_ACTIVATED_INTRO', 'Tu autenticacion de doble factor ha sido activada. Guarda estos codigos de recuperacion en un lugar seguro. Solo se mostraran una vez.');
define('TEXT_2FA_ACTIVATED_WARNING', 'Si pierdes el acceso a tu aplicacion de autenticacion, necesitaras uno de estos codigos para iniciar sesion.');
define('TEXT_2FA_ACTIVATED_CONFIRM', 'He guardado mis codigos');
define('TEXT_2FA_ACTIVATED_PRINT', 'Imprimir codigos');
define('TEXT_2FA_DISABLE_TITLE', 'Desactivar autenticacion de doble factor');
define('TEXT_2FA_DISABLE_INTRO', 'Para desactivar el 2FA, introduce tu contrasena actual como confirmacion.');
define('TEXT_2FA_DISABLE_PASSWORD_LABEL', 'Contrasena actual:');
define('TEXT_2FA_DISABLE_SUBMIT', 'Confirmar desactivacion');
define('TEXT_2FA_DISABLED_SUCCESS', 'La autenticacion de doble factor ha sido desactivada.');
define('TEXT_2FA_ERROR_WRONG_PASSWORD', 'La contrasena introducida no es correcta.');
define('TEXT_2FA_RECOVERY_TITLE', 'Regenerar codigos de recuperacion');
define('TEXT_2FA_RECOVERY_INTRO', 'Al regenerar los codigos, los anteriores quedaran invalidados. Introduce tu contrasena para confirmar.');
define('TEXT_2FA_RECOVERY_SUBMIT', 'Regenerar codigos');
define('TEXT_2FA_RECOVERY_SUCCESS_TITLE', 'Nuevos codigos de recuperacion');
define('TEXT_2FA_RECOVERY_SUCCESS_INTRO', 'Tus nuevos codigos de recuperacion. Guardalos en un lugar seguro. Solo se mostraran una vez.');
define('TEXT_2FA_ERROR_NOT_ENABLED', 'El 2FA no esta activado en tu cuenta.');
define('TEXT_2FA_ALREADY_ENABLED', 'El 2FA ya esta activado. Desactivalo primero si quieres reconfigurarlo.');

?>
