<?php
global $customer_email, $customer_name, $customer_id;
/*
  $Id: support.php,v 1.1 2003/02/04 16:07:06 puddled Exp $
  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com
  Copyright (c) 2002 Puddled Computer Services
  Contributed by Puddled Computer services
  http://www.puddled.co.uk
  Author David Howarth
  Email dave@puddled.co.uk
  Released under the GNU General Public License
*/
//tep_get_products_special_price(1);


define('TEXT_MAIN_SUPPORT', '<BR><p><span class="greetUser">Ayuda Instantanea</span></p>
<p>La respuesta a su pregunta podría estar en: <a href="' . tep_href_link(FILENAME_FAQ, '', 'SSL') . '" style="color:#0000ff">Preguntas Frecuentes (FAQ)</a>.</p>

<p><span class="greetUser">Contactenos</span></p>
<p>Nuestro equipo de atención al usuario está especialmente cualificado para gestionar sus incidencias en un tiempo razonable. <p>
<hr size=1>

<p><strong>Clientes registrados. </strong></p><p>Por favor <a href="' . tep_href_link(FILENAME_SUPPORT, 'action=new', 'NONSSL') . '" style="color:#0000ff; font-size: 12px; font-weight : bold;">Envíe su consulta</a> a través de los tickets de soporte <a href="' . tep_href_link(FILENAME_SUPPORT_TRACK, 'view=all', 'NONSSL') . '"  style="color:#0000ff; font-size: 12px; font-weight : bold;"> o actualice un ticket existente </a>y le responderemos a la mayor brevedad. Si lo desea puede sugerir la inclusión de su pregunta en las FAQ.</p>
  <noscript>
  <a href="'. tep_href_link(CONTACT_US) . '" target="_blank"  style="color:#0000FF">Servicio de respuesta</a>
  </noscript>

de llamadas.</p> <p>Recomendamos la comunicación mediante tickets de soporte ya que esta información será registrada en nuestros sistemas, mientras que un mensaje enviado por mail podría perderse o ser rechazado por los filtros antispam. El formulario de <em>contacto</em> (abajo) podría perderse o ser rechazado igualmente por los filtros de spam</P>

<hr size=1>

<p><strong>Visitantes.  </strong></P>
<p>Le invitamos a enviarnos un mensaje mediante nuestro formulario de contacto. Por favor


<script language="javascript">
function ppW2(url) {
  window.open(url,\'popupWindow\',\'toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=no,resizable=yes,copyhistory=no,width=500,height=500,screenX=150,screenY=150,top=150,left=150\')
}

	document.write(\'<a href="javascript:ppW2(\\\''. tep_href_link(CONTACT_US, 'fn=' . tep_output_string_protected($customer_name) . '&email=' . tep_output_string_protected($customer_email) . '&id=' . tep_output_string_protected($customer_id))   . '\\\')"  style="color:#0000ff">Haga Click aquí para contactar con nosotros</a>\')

  </script>
  <noscript><a href="'. tep_href_link(CONTACT_US) . '" target="_blank" style="color:#0000ff">Haga Click aquí para contactar con nosotros<br></a></noscript>


.</p>

<hr size=1>

Si tiene dificultades para entender el formulario en Castellano, por favor, pulse aquí para traducirlo:<br>
<a href="http://www.google.com/language_tools?hl=en" target="_blank" style="color:#0000ff">http://www.google.com/language_tools?hl=es</a>

');

define( 'CLOSE_TICKETS', '' );
define( 'SEND_TICKET', 'Enviar&nbsp;Ticket' );
define( 'OPEN_TICKETS', 'Tickets&nbsp;Abiertos' );
define( 'CLOSE_TICKETS', 'Tickets&nbsp;Cerrados' );
define( 'ALL_TICKETS', 'Todos&nbsp;Tickets' );

define('NAVBAR_TITLE', 'Centro de Soporte');
define('HEADING_TITLE', 'Centro de Soporte <br>');
define('NAVBAR_TITLE_1', 'Track tickets');
define('NAVBAR_TITLE_3', 'Info Ticket');
define('NAVBAR_TITLE_4', 'Editar un ticket');
define('HEADING_TITLE_TRACK', 'Informacion de Ticket');
define('HEADING_TITLE_OPTIONS', 'Modificar un ticket');
define('HEADING_TITLE_DELETE', 'Borrar un ticket');
define('HEADING_TICKET_HISTORY', 'Historial de Ticket');
define('HEADING_LAST_MODIFIED', 'Ultima modificacion');
define('HEADING_LAST_ADMIN', 'Modificado por');
define('HEADING_TICKET_INFORMATION', 'Detalles del ticket');
define('HEADING_TICKET_NAME', 'Ticket Enviado por');
define('HEADING_TICKET_EMAIL', 'Direccion de Email');
define('HEADING_TICKET_ADMIN', 'Detalles de soporte');
define('HEADING_TICKET_SUPPORTER', 'Asignado a:');
define('HEADING_TICKET_DEPARTMENT', 'Categoria');
define('HEADING_TICKET_DOMAIN', 'Motivo');
define('HEADING_TICKET_COMPANY', 'Nombre de compañía');
define('TEXT_TICKET_PRIORITY', 'Prioridad de Ticket:');
define('TEXT_NO_RESPONSE_AVAILABLE', 'No hay comentarios disponibles');
define('TEXT_NO_RESPONSE_DATE','');
define('TEXT_SUPPORT_DEPT', 'Categoria');
DEFINE('TEXT_SUPPORT_PRIORITY', 'Prioridad');
define('TEXT_SUPPORT_USER_NAME', 'Tu nombre');
define('TEXT_SUPPORT_USER_EMAIL', 'Tu email');
define('TEXT_SUPPORT_COMPANY' ,'Empresa');
define('TEXT_SUPPORT_DOMAIN', 'Motivo');
define('TEXT_SUPPORT_TEXT', 'Comentarios');
define('TEXT_SUCCESS', 'Gracias por enviar su solicitud de soporte.  Su solicitud ha sido enviada al dpto correspondiente para su análisis.  Se le notificará la respuesta por e-mail.');
define('TEXT_NO_COMMENTS_AVAILABLE', 'No se ha enviado ninguna información');
define('TEXT_LOCATE_ERROR', 'Perdone, no se han encontrado Tickets para esa dirección de e-mail.');
define('TEXT_DISPLAY_NUMBER_OF_TICKETS', 'Mostrando del <b>%d</b> al <b>%d</b> (de <b>%d</b> tickets)');
define('TEXT_TICKET_NUMBER', '#');
define('TEXT_TICKET_DATE', 'Ticket enviado el:');
define('TEXT_TICKET_CLOSED', 'Ticket cerrado el:');
define('TEXT_SUBMITTED_BY', 'Enviado Por: ');
define('TEXT_TICKET_DEPARTMENT', 'Categoria: ');
define('TEXT_TICKET_PRIORITY', 'Prioridad: ');
define('TEXT_TICKET_STATUS', 'Ticket Estado: ');
define('TEXT_VIEW_TICKET', 'Ver');
define('TABLE_HEADING_STATUS', 'Estado');
define('TABLE_HEADING_OLD_DEPT', 'Antiguo Dpto');
define('TABLE_HEADING_NEW_DEPT', 'Categoria');
define('TABLE_HEADING_OLD_ADMIN', 'Antiguo Admin');
define('TABLE_HEADING_NEW_ADMIN', 'Asignado a');
define('TABLE_HEADING_NEW_VALUE', 'Estado');
define('TABLE_HEADING_OLD_VALUE', 'Antiguo Valor');
define('TABLE_HEADING_CUSTOMER_NOTIFIED', 'Cliente Notificado');
define('TABLE_HEADING_DATE_ADDED', 'Fecha Añadido');
define('TEXT_AMEND_TICKET', 'Editar');
define('TEXT_DELETE_TICKET', 'Cerrar');
define('TEXT_TICKET_SUBJECT', 'Motivo');
define('TEXT_ERROR', 'Ninguna opción seleccionada');
define('TEXT_NO_ORDER_HISTORY', 'El administrador todavía no ha respondido a este Ticket.');
define('TEXT_CANCEL_DELETE', 'Cancelar');
define('TEXT_CONFIRM_DELETE', 'Borrar este Ticket');
define('TEXT_NO_PURCHASES', 'No hay tickets abiertos en su historial');
define('TEXT_NO_CLOSED', 'No hay tickets cerrados en su historial');
define('TEXT_TICKET_REMOVAL', 'Su solicitud para cerrar este Ticket se ha completado. El estado de su ticket ha sido modificado y el administrador asignado ha sido informado de su solicitud.  Si necesita reabrir este ticket, lo puede hacer en cualquier momento viendo sus tickets cerrados');
define('ENTRY_NAME', 'Nombre:');
define('TICKET_DETAILS', 'Detalles de Ticket');
define('ENTRY_SUBJECT', 'Ticket Motivo');
define('ENTRY_PRIORITY', 'Prioridad');
define('ENTRY_DEPARTMENT', 'Categoria');
define('ENTRY_PROBLEM', 'Problema');
define('CATEGORY_ADMIN', 'Informacion del Admin');
define('ENTRY_ASSIGN', 'Asignado a');
define('ENTRY_LAST_STATUS', 'Estado actual');
define('ENTRY_ADMIN_COMMENTS', 'Comentarios del Admin');
define('ENTRY_LAST_MODIFIED', 'Ultima modificación');
define('TEXT_TICKET_REOPEN', 'Su solicitud para reabrir este ticket ha sido completada. El estado de su ticket ha sido modificado y el administrador asignado ha sido informado de su solicitud.');
define('TEXT_SUPPORT_FAQ', 'Recomendado para Preguntas Frecuentes(FAQ)');
// define('TEXT_SUPPORT_ALTERNATIVE_EMAIL', 'Dirección Email alternativa');
define('TEXT_SUPPORT_ORDERS', 'Pedido(s) #');
define('TEXT_SUPPORT_IF_APPLICABLE', 'Si es aplicable');
define('TEXT_FAQ_HELP', 'Seleccionando esta opción, está sugiriendo al Administrador que incluya el ticket en la sección FAQ.<Br><BR>Todas las preguntas son consideradas cuidadosamente, y si se considera que su pregunta plantea un tema que no fuera tratado anteriormente, entonces se puede añadir a las FAQ. Gracias por su aporte.');
define('HEADING_FAQ_HELP', 'Sugerir FAQ');
define('TEXT_CLOSE_WINDOW', 'Cerrar esta ventana');
?>
