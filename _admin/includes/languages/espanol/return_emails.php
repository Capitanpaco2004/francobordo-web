<?
/*
$id author Puddled Internet - http://www.puddled.co.uk
  email support@puddled.co.uk
   osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2002 osCommerce

  Released under the GNU General Public License

*/

/* This section covers the very first confirmation email sent to a customer,
to say that their RMA request has been received. */
define('EMAIL_SUBJECT_OPEN', 'Solicitud de Devolución de Material(RMA) enviada a ' . STORE_NAME);
define('EMAIL_TEXT_TICKET_OPEN', 'RMA número: <b><i>' . $rma_value . '</b></i>' . "\n");
define('EMAIL_THANKS_OPEN', 'Gracias por enviar su solicitud de devolución a <b>' . STORE_NAME . '</b>.' . "\n");
define('EMAIL_TEXT_OPEN', 'Su solicitud ha sido enviada al departamento correspondiente para su tramitación. ' . 'Si necesita ponerse en contacto con nosotros en relación con este asunto, por favor indique el número de SDM arriba para que podamos hacer un seguimiento de toda la correspondencia pertinente.<br>');
define('EMAIL_CONTACT_OPEN', 'Para obtener ayuda con cualquiera de nuestros servicios, por favor contacte con nosotros en: ' . STORE_OWNER_EMAIL_ADDRESS . '.' . "\n\n");
define('EMAIL_WARNING_OPEN', '<b>Nota:</b> Esta dirección de correo electrónico nos fue dada por alguien que la usó para enviar una solicitud de soporte. Si no envió esta solicitud, por favor envíe un mensaje a' . STORE_OWNER_EMAIL_ADDRESS . '.');


/* This section covers the confirmation email sent to the assigned administrator after an RMA request has been edited by a customer, in order to inform the admin that the ticket has been edited. */

define('EMAIL_SUBJECT_ADMIN', STORE_NAME . ' Requerimiento RMA del Usuario');
define('EMAIL_TEXT_TICKET_ADMIN', 'RMA número -<b><i>' . $rma_value . '</b></i>' . "\n\n");
define('EMAIL_THANKS_ADMIN', 'Este mensaje es para informarle de que la solicitud de devolución anterior ha sido actualizada por el cliente');
define('EMAIL_TEXT_ADMIN', 'Por favor, inicie sesión en el área de administración para ver la información de la devolución.' . "\n\n");
define('EMAIL_WARNING_ADMIN', '<b>Note:</b> Esta dirección de correo electrónico nos fue dada por alguien que la usó para enviar una solicitud de soporte. Si no envió esta solicitud, por favor envíe un mensaje a ' . STORE_OWNER_EMAIL_ADDRESS . '.');
?>