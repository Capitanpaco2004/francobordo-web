<?
/*
  $Id: support_email.php,v 1.3 2003/02/05 12:55:51 puddled Exp $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2002 Puddled Computer Services
  Contributed by Puddled Computer services
  http://www.puddled.co.uk

  Author David Howarth
  Email dave@puddled.co.uk

  Released under the GNU General Public License



*/

/* this section covers the very first confirmationemail to a customer,
to say that their ticket has been recieved */
define('EMAIL_SUBJECT_OPEN', 'Ticket de Soporte enviado a: ' . STORE_NAME);
define('EMAIL_TEXT_TICKET_OPEN', 'TICKET ID -<b><i>' . $ticket_id . '</b></i>' . "\n\n");
define('EMAIL_THANKS_OPEN', 'Gracias por enviar su solicitud de soporte a <b>' . STORE_NAME . '</b>.' . "\n\n");
define('EMAIL_TEXT_OPEN', 'Su ticket ha sido enviado al dpto correspondiente para su an&aacute;lisis' . "\n\n" . 'Si necesita contactar con nosotros antes de que le hayamos respondido, por favor, ind&iacute;quenos el <i>n&uacute;mero de ticket</i> para que podamos ayudarle con m&aacute;s celeridad.' . "\n\n");
define('EMAIL_CONTACT_OPEN', 'Para solicitar ayuda de cualquiera de nuestros serivicos On-Line, por favor env&iacute;e un email a : ' . STORE_OWNER_EMAIL_ADDRESS . '.' . "\n\n");
define('EMAIL_WARNING_OPEN', '<b>Nota:</b> Esta direcci&oacute;n de email nos ha sido enviada por alguien que nos ha remitido un ticket de soporte. Si usted no realiz&oacute; esta solicitud, por favor env&iacute;e un email a ' . STORE_OWNER_EMAIL_ADDRESS . '.' . "\n");

/* this section covers the confirmation email sent after a ticket has been edited by a customer,
to say that their ticket has been updated */

define('EMAIL_SUBJECT_UPDATE', 'Ticket de Soporte actualizado' . STORE_NAME);
define('EMAIL_TEXT_TICKET_UPDATE', 'TICKET ID -<b><i>' . $ticket_id . '</b></i>' . "\n\n");
define('EMAIL_THANKS_UPDATE', 'Gracias por enviar su solicitud de soporte a  <b>' . STORE_NAME . '</b>.' . "\n\n");
define('EMAIL_TEXT_UPDATE', 'Su ticket ha sido actualizado y enviado al dpto correspondiente para su an&aacute;lisis.' . "\n\n" . 'Si necesita contactar con nosotros antes de que le hayamos respondido, por favor, ind&iacute;quenos el <i>n&uacute;mero de ticket</i> para que podamos ayudarle con m&aacute;s celeridad.' . "\n\n");
define('EMAIL_CONTACT_UPDATE', 'Para solicitar ayuda de cualquiera de nuestros serivicos On-Line,por favor env&iacute;e un email a : ' . STORE_OWNER_EMAIL_ADDRESS . '.' . "\n\n");
define('EMAIL_WARNING_UPDATE', '<b>Nota:</b> Esta direcci&oacute;n de email nos ha sido enviada por alguien que nos ha remitido un ticket de soporte. Si usted no realiz&oacute; esta solicitud, por favor env&iacute;e un email a  ' . STORE_OWNER_EMAIL_ADDRESS . '.' . "\n");

/* this section covers the confirmation email sent after a ticket has been edited by a customer,
to the assigned administrator, to say that the ticket has been edited */

define('EMAIL_SUBJECT_ADMIN', 'Ticket de Soporte actualizado');
define('EMAIL_TEXT_TICKET_ADMIN', 'TICKET ID -<b><i>' . $ticket_id . '</b></i>' . "\n\n");
define('EMAIL_THANKS_ADMIN', 'El ticket siguiente ha sido modificado por el Cliente' . "\n\n");
define('EMAIL_TEXT_ADMIN', 'Por favor, entre en Admin para ver los cambios.' . "\n\n" . 'Si necesita contactar con nosotros antes de que le hayamos respondido, por favor, adjunte el  <i>numero de ticket</i> para que podamos ayudarle con m&aacute;s celeridad' . "\n\n");
define('EMAIL_CONTACT_ADMIN', 'Para ayudarnos con cualquier otra informaci&oacute;n por favor env&iacute;e un e-mail a: ' . STORE_OWNER_EMAIL_ADDRESS . '.' . "\n\n");
define('EMAIL_WARNING_ADMIN', '<b>Nota:</b> Esta direcci&oacute;n de email nos ha sido enviada por alguien que nos ha remitido un ticket de soporte. Si usted no realiz&oacute; esta solicitud, por favor env&iacute;e un email a ' . STORE_OWNER_EMAIL_ADDRESS . '.' . "\n");



/* this section covers the confirmation email sent after a ticket has been closed by a customer,
to say that their ticket has been updated */

define('EMAIL_SUBJECT_CLOSED', 'Ticket de Soporte enviado a' . STORE_NAME);
define('EMAIL_TEXT_TICKET_CLOSED', 'TICKET ID -<b><i>' . $ticket_id . '</b></i>' . "\n\n");
define('EMAIL_THANKS_CLOSED', 'Gracias por actualizar su ticket de soporte enviado a <b>' . STORE_NAME . '</b>.' . "\n\n");
define('EMAIL_TEXT_CLOSED', 'Su ticket se ha cerrado y no se realizar&aacute;n m&aacute;s acciones al respecto.'  . "\n\n");
define('EMAIL_CONTACT_CLOSED', 'Para solicitar ayuda de cualquiera de nuestros serivicos On-Line,por favor env&iacute;e un email a: ' . STORE_OWNER_EMAIL_ADDRESS . '.' . "\n\n");
define('EMAIL_WARNING_CLOSED', '<b>Nota:</b> Esta direcci&oacute;n de email nos ha sido enviada por alguien que nos ha remitido un ticket de soporte. Si usted no realiz&oacute; esta solicitud, por favor env&iacute;e un email a' . STORE_OWNER_EMAIL_ADDRESS . '.' . "\n");


/* this section covers the confirmation email sent after a ticket has been re-opened by a customer,
to say that their ticket has been updated */

define('EMAIL_SUBJECT_REOPEN', 'Ticket de Soporte enviado a' . STORE_NAME);
define('EMAIL_TEXT_TICKET_REOPEN', 'TICKET ID -<b><i>' . $ticket_id . '</b></i>' . "\n\n");
define('EMAIL_THANKS_REOPEN', 'Gracias por actualizar su ticket de soporte enviado a<b>' . STORE_NAME . '</b>.' . "\n\n");
define('EMAIL_TEXT_REOPEN', 'Su ticket ha sido actualizado y enviado al dpto correspondiente para su an&aacute;lisis.' . "\n\n" . 'Si necesita contactar con nosotros antes de que le hayamos respondido, por favor, ind&iacute;quenos el <i>n&uacute;mero de ticket</i> para que podamos ayudarle con m&aacute;s celeridad.' . "\n\n");
define('EMAIL_CONTACT_REOPEN', 'Para solicitar ayuda de cualquiera de nuestros serivicos On-Line,por favor env&iacute;e un email a: ' . STORE_OWNER_EMAIL_ADDRESS . '.' . "\n\n");
define('EMAIL_WARNING_REOPEN', '<b>Nota:</b> Esta direcci&oacute;n de email nos ha sido enviada por alguien que nos ha remitido un ticket de soporte. Si usted no realiz&oacute; esta solicitud, por favor env&iacute;e un email a' . STORE_OWNER_EMAIL_ADDRESS . '.' . "\n");


/* this section covers the confirmation email sent after a ticket has been closed by a customer,
to the assigned administrator, to say that the ticket has been edited */

define('EMAIL_SUBJECT_ADMIN_CLOSED', 'Soporte enviado a ' . STORE_NAME);
define('EMAIL_TEXT_TICKET_ADMIN_CLOSED', 'TICKET ID -<b><i>' . $ticket_id . '</b></i>' . "\n\n");
define('EMAIL_THANKS_ADMIN_CLOSED', 'Este e-mail es para informarle de que la solicitud de soporte enviada a  <b>' . STORE_NAME . '</b>Ha sido cerrada por el Cliente.' . "\n\n");
define('EMAIL_TEXT_ADMIN_CLOSED', 'No se requiere ninguna otra acci&oacute;n en este ticket.' . "\n\n" . 'Si necesita contactar con nosotros antes de que le hayamos respondido, por favor, ind&iacute;quenos el <i>n&uacute;mero de ticket</i> para que podamos ayudarle con m&aacute;s celeridad.' . "\n\n");
define('EMAIL_CONTACT_ADMIN_CLOSED', 'Para solicitar ayuda de cualquiera de nuestros serivicos On-Line,por favor env&iacute;e un email a: ' . STORE_OWNER_EMAIL_ADDRESS . '.' . "\n\n");
define('EMAIL_WARNING_ADMIN_CLOSED', '<b>Nota:</b> Esta direcci&oacute;n de email nos ha sido enviada por alguien que nos ha remitido un ticket de soporte. Si usted no realiz&oacute; esta solicitud, por favor env&iacute;e un email a' . STORE_OWNER_EMAIL_ADDRESS . '.' . "\n");
