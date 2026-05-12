<?
/* support_email for admin use only

*/

/* this defines the email sent to a customer when a ticket has been updated */

define('EMAIL_SEPARATOR', '------------------------------------------------------');
define('EMAIL_TEXT_SUBJECT', 'Acualizaci&oacute;n del Ticket');
define('EMAIL_TEXT_ORDER_NUMBER', 'N&uacute;mero de TICKET:');
define('EMAIL_TEXT_INVOICE_URL', 'Resumen detallado:');
define('EMAIL_TEXT_DATE_ORDERED', 'Fecha Enviado:');
define('EMAIL_TEXT_STATUS_UPDATE', 'Su ticket ha sido actualizado al siguiente estado.' . "\n\n" . 'Nuevo estado: %s' . "\n\n");
define('EMAIL_TEXT_COMMENTS_UPDATE', 'Los comentarios para su ticket son' . "\n\n%s\n\n");
define('EMAIL_TEXT_ADD_COMMENTS', 'Por favor, a&ntilde;ada al ticket cualquier comentario que considere necesario' . "\n\n");
define('EMAIL_TEXT_RE_OPEN', 'Por favor, reabra el ticket si no ha quedado satisfecho con la respuesta' . "\n\n");


/* this defines the email sent to acustomer when a ticket has had the administrator changed */

define('EMAIL_SEPARATOR', '------------------------------------------------------');
define('EMAIL_TEXT_SUBJECT_NEW_ADMIN', 'Actualizaci&oacute;n de Ticket');
define('EMAIL_TEXT_ORDER_NUMBER_NEW_ADMIN', 'Numero de TICKET:');
define('EMAIL_TEXT_INVOICE_URL_NEW_ADMIN', 'Resumen detallado:');
define('EMAIL_TEXT_DATE_ORDERED_NEW_ADMIN', 'Fecha Enviado:');
define('EMAIL_TEXT_STATUS_UPDATE_NEW_ADMIN', 'Su ticket ha sido enviado a.' . "\n\n" . 'Administrador: %s' . "\n\n" . 'Por favor, responda a este e-mail para cualquier aclaraci&oacute;n.' . "\n");
define('EMAIL_TEXT_COMMENTS_UPDATE_NEW_ADMIN', 'Los comentarios a su ticket son' . "\n\n%s\n\n");

/* this defines the email sent to acustomer when a ticket has been closed */

define('EMAIL_SEPARATOR', '------------------------------------------------------');
define('EMAIL_TEXT_SUBJECT_CLOSED', 'Actualizacion del Ticket');
define('EMAIL_TEXT_ORDER_NUMBER_CLOSED', 'N&uacute;mero de :');
define('EMAIL_TEXT_INVOICE_URL_CLOSED', 'Resumen detallado:');
define('EMAIL_TEXT_DATE_ORDERED_CLOSED', 'Fecha Enviado:');
define('EMAIL_TEXT_STATUS_UPDATE_CLOSED', 'Hemos cerrado su ticket' . "\n\n" . 'Por favor, responda a este e-mail para cualquier aclaraci&oacute;n' . "\n");
define('EMAIL_TEXT_COMMENTS_UPDATE_CLOSED', 'Los comentarios para su ticket son' . "\n\n%s\n\n");

/* this defines the email sent to an administrator when a ticket has been assigned to them */

define('EMAIL_SEPARATOR', '------------------------------------------------------');
define('EMAIL_TEXT_SUBJECT_ADMIN', 'Actualizacion del Ticket');
define('EMAIL_TEXT_ORDER_NUMBER_ADMIN', 'N&uacute;mero de :');
define('EMAIL_TEXT_INVOICE_URL_ADMIN',  'Resumen detallado:');
define('EMAIL_TEXT_DATE_ORDERED_ADMIN', 'Fecha Enviado:');
define('EMAIL_TEXT_STATUS_UPDATE_ADMIN', 'Le ha sido asignado el ticket.' . "\n\n" . 'ID: ' . $oID . "\n\n" . 'Por favor, responda este e-mail para cualquier aclaraci&oacute;n.' . "\n");
define('EMAIL_TEXT_COMMENTS_UPDATE_ADMIN', 'Los comentarios para este ticket son:' . "\n\n%s\n\n");