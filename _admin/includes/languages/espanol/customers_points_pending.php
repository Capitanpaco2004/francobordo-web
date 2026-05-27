<?php
/*
  $Id: customers_points_pending.php, v 1.50 2005/AUG/10 15:17:12 dgw_ Exp $
  http://www.deep-silver.com

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2005 osCommerce

  Released under the GNU General Public License
*/
define('MOD_VER', '2.00');

define('HEADING_TITLE', 'Puntos Pendientes de Clientes');
define('HEADING_RATE', 'Cambios : ');
define('HEADING_AWARDS', 'Conseguidos : ');
define('HEADING_REDEEM', 'Canjeados : ');
define('HEADING_POINT', 'punto');
define('HEADING_POINTS', 'puntos');
define('HEADING_TITLE_SEARCH', 'Buscar numero de Pedido:');

define('TABLE_HEADING_CUSTOMERS', 'Clientes');
define('TABLE_HEADING_ORDER_TOTAL', 'Importe Total');
define('TABLE_HEADING_DATE_PURCHASED', 'Fecha Compra');
define('TABLE_HEADING_ORDERS_STATUS', 'Estado Pedido');
define('TABLE_HEADING_POINTS_STATUS', 'Estado Puntos');
define('TABLE_HEADING_ACTION', 'Acci&oacute;n');
define('TABLE_HEADING_POINTS', 'Puntos');
define('TABLE_HEADING_POINTS_VALUE', 'Valor');
// Usadas por el helper tep_send_points_notification() en includes/functions/redemptions.php
define('TABLE_HEADING_POINTS_TYPE', 'Concepto Puntos:');
define('TABLE_HEADING_DATE_ADDED', 'Fecha:');

define('TABLE_HEADING_SORT', 'Sort this rows by ');
define('TABLE_HEADING_SORT_UA', ' --> A-B-C From Top');
define('TABLE_HEADING_SORT_U1', ' --> 1-2-3 From Top');
define('TABLE_HEADING_SORT_DA', ' --> Z-Y-X From Top');
define('TABLE_HEADING_SORT_D1', ' --> 3-2-1 From Top');

define('TEXT_DEFAULT_COMMENT', 'Puntos de Compra');
define('TEXT_DEFAULT_REDEEMED', 'Puntos Utlizados');

define('TEXT_POINTS_PENDING', 'Pendiente');
define('TEXT_POINTS_PROCESSING', 'En Proceso');
define('TEXT_POINTS_CONFIRMED', 'Confirmado');
define('TEXT_POINTS_CANCELLED', 'Cancelado');
define('TEXT_POINTS_REDEEMED', 'Utilizados');
define('TEXT_DATE_ORDER_CREATED', 'Pedido Creado');
define('TEXT_DATE_ORDER_LAST_MODIFIED', '&Uacute;ltima Modificaci&oacute;n:');
define('TEXT_SHOW_ALL', 'Show All');
define('TEXT_INFO_POINTS_COMMENT', 'Actual Comentario de Puntos: ');
define('TEXT_INFO_PAYMENT_METHOD', 'M&eacute;todo de Pago: ');

define('TEXT_INFO_HEADING_ADJUST_POINTS', 'Ajusta puntos Pendientes.');
define('TEXT_INFO_HEADING_DELETE_RECORD', 'Borra Registro');
define('TEXT_INFO_HEADING_PENDING_NO', 'Puntos Pendientes del pedido Nº.');
define('TEXT_CONFIRM_POINTS', '¿Confirmar Puntos Pendientes al Cliente?');
define('TEXT_CONFIRM_POINTS_LONG', 'Puedes confirmar puntos a clientes con/sin a&ntilde;adir a la tabla de puntos de clientes.<br>confirmando puntos sin a&ntilde;adir a la tabla eliminar&aacute; esta l&iacute;nea de la tabla y el estado de los puntos ser&aacute; reemplazado por "Confirmado".');
define('TEXT_CANCEL_POINTS', '¿Cancelar Puntos Pendientes del cliente?');
define('TEXT_CANCEL_POINTS_LONG', 'Puedes cancelar puntos a un cliente con/sin a&ntilde;adir a la tabla de puntos de clientes.<br>Cancelando puntos sin a&ntilde;adir a la tabla elimina&aacute; esta l&iacute;nea de la tabla y el estado de los puntos ser&aacute; reemplazado por "Cancelado" y el comentario por defecto ser&aacute; reemplazado por tu Motivo de Cancelaci&oacute;n.');
define('TEXT_CANCELLATION_REASON', 'Motivo de Cancelaci&oacute;n :');
define('TEXT_ADJUST_INTRO', 'This option enable you to adjust the total amount of pending points before confirming them.<br>Note that this will replace the current pending points amount and can not be undone.');
define('TEXT_DELETE_INTRO', '¿Est&aacute;s seguro que quieres borrar este registro?<br>Borrar&aacute; este registro de la base de datos.');
define('TEXT_POINTS_TO_ADJUST', 'Nueva Cantidad de Puntos :');
define('TEXT_ROLL_POINTS', 'Retrotraer Puntos.');
define('TEXT_ROLL_POINTS_LONG', 'Esta opci&oacute;n te permite retrotraer puntos confirmados a estado de "Pendientes".<br>Estos puntos se reducir&aacute;n de la cuenta del cliente y se mostrar&aacute;m como "Pendientes".');
define('TEXT_ROLL_REASON', 'Motivo de retrotraer puntos:');

define('TEXT_QUEUE_POINTS_TABLE', 'Cola de Tabla de Puntos de Clientes');
define('TEXT_NOTIFY_CUSTOMER', 'Notificar al Cliente');
define('TEXT_SET_EXPIRE', 'Set new expire date');

define('BUTTON_TEXT_ADJUST_POINTS', 'Ajusta la cantidad de puntos pendientes.');
define('BUTTON_TEXT_CANCEL_PENDING_POINTS', 'Cancela Puntos de Cliente');
define('BUTTON_TEXT_CONFIRM_PENDING_POINTS', 'Confirma Puntos de Cliente');
define('BUTTON_TEXT_REMOVE_RECORD', 'Borra este registro de la base de datos');
define('BUTTON_TEXT_ROLL_POINTS', 'Retrotrae puntos a estado de Pendientes');
define('ICON_PREVIEW_EDIT', 'Ver los detalles del pedido o edita su estado');

define('EMAIL_SEPARATOR', 'También puede ponerse en contacto con nosotros por teléfono llamando al número <b>916 528 858</b> de lunes<br>
							a viernes de 10:00 a 19:00 h o a través de correo postal enviando una carta a nuestra dirección:<br>
							<b>Calle San Rafael, 8, 28108 Alcobendas, Madrid.</b> <br>');
define('EMAIL_TEXT_SUBJECT', 'Actualización de Puntos de la Cuenta');
define('EMAIL_GREET_MR', 'Estimado S. %s,');
define('EMAIL_GREET_MS', 'Estimada Srta. %s,');
define('EMAIL_GREET_NONE', 'Estimado/a %s<br>');
define('EMAIL_TEXT_ORDER_NUMBER', 'N&deg; de Pedido:');
define('EMAIL_TEXT_DATE_ORDERED', 'Fecha del Pedido:');
define('EMAIL_TEXT_ORDER_STAUTS', 'Estado del Pedido:');
define('EMAIL_TEXT_INTRO', 'Le enviamos este correo para informarle de que los puntos correspondientes a su último pedido han sido activados');
define('EMAIL_TEXT_CANCEL', 'Este correo es para informarle que sus Puntos han sido cancelados.');

define('EMAIL_TEXT', 'Este mail te informa que tu cuenta de Puntos por Compra se ha actualizado.');
define('EMAIL_TEXT_BALANCE_CANCELLED', 'Le enviamos este correo para informarle que sus puntos han sido cancelados para el siguiente pedido.');
define('EMAIL_TEXT_BALANCE_CONFIRMED', 'Puntos confirmados del siguiente Pedido:');
define('EMAIL_TEXT_BALANCE_ROLL_BACK', '· Los puntos Confirmados por este Pedido se han retrotraido al Estado indicado.');
define('EMAIL_TEXT_ROLL_COMMENT', 'Comentario :');
define('EMAIL_TEXT_BALANCE', '· Su saldo actual es de <b style="color: #22a1d1;">%s Puntos</b> valorados en %s<br>');
define('EMAIL_TEXT_EXPIRE', '· Sus puntos caducarán el: %s<br>');
define('EMAIL_TEXT_POINTS_URL', '· Este es el enlace para consultar su Cuenta de Puntos:<br><br>&nbsp;&nbsp;<a href="%s" style="font-weight: bold; color: #22a1d1;">%s</a>');
define('EMAIL_TEXT_POINTS_URL_HELP', '· Toda la información sobre el Sistema de Puntos puede consultarla en el siguiente enlace:<br><br>&nbsp;&nbsp;<a href="%s" style="font-weight: bold; color: #22a1d1;">%s</a>');
define('EMAIL_TEXT_COMMENT', 'Motivo de la cancelación :');
define('EMAIL_TEXT_SUCCESS_POINTS', 'Los Puntos están disponibles en su cuenta, en su próximo pedido usted podrá usarlos como parte del pago<br>
									 <br>Muchas gracias por comprar en <b style="color: #22a1d1;">' . STORE_NAME . '</b>  y esperamos poder servirle de nuevo<br>');
define('EMAIL_CONTACT', 'Si tiene alguna consulta o necesita ayuda con alguno de nuestros servicios online, por favor envíenos<br>un e-mail a: <b style="color: #22a1d1;">' . STORE_OWNER_EMAIL_ADDRESS . '</b><br>');
//Auto Remainder bof
define('EMAIL_EXPIRE_SUBJECT', 'Sus puntos caducarán en  ' . POINTS_EXPIRES_REMIND.' dias');
define('EMAIL_EXPIRE_INTRO', 'Este es un recordatorio automatizado para recordarle que sus puntos caducarán en ' . POINTS_EXPIRES_REMIND . ' días.');
define('EMAIL_EXPIRE_DET', '· Tiene un total de <span style="font-weight: bold; color: #22a1d1;">%s Puntos</span><br>');
define('EMAIL_EXPIRE_DET2', '· Sus puntos caducarán el: %s<br>');
define('EMAIL_EXPIRE_TEXT', 'Después de esta fecha, el balance total de sus puntos acumulados los perderá y usted empezará a acumular puntos desde el principio.');
//Auto Remainder eof
define('SUCCESS_POINTS_UPDATED', 'Correcto. La cuenta de Puntos del Cliente ha sido actualizada correctamente.');
define('SUCCESS_DATABASE_UPDATED', 'Actualizada Cola: La base de datos ha sido actualizada correctamente y los puntos se han asignado como  ' . TEXT_POINTS_CANCELLED . '  con este comentario: " '. $comment . ' ".');
define('NOTICE_EMAIL_SENT_TO', '&iexcl;Atenci&oacute;n! Se ha mandado un E-mail a : %s');
define('NOTICE_RECORED_REMOVED', '&iexcl;Atenci&oacute;n! Los puntos asignados al pedido No. ' . $oID . ' se han borrado de la base de datos.');
define('WARNING_DATABASE_NOT_UPDATED', '&iexcl;Aviso! Campos vac&iacute;os, nada que cambiar. La base de datos no se ha actualizado.');
define('POINTS_ENTER_JS_ERROR', '&iexcl;Valor incorrecto! \n En este campo se deben introducir &uacute;nicamente n&uacute;meros.');
define('TEXT_LINK_CREDIT', 'Pulsar aqui para activar <a href="customers_points_credit.php"><u>Auto Credit</u></a> or <a href="customers_points_expire.php"><u>Auto Expire</u></a> script manually.');

define('EMAIL_AUTO', 'Esta es una respuesta automática, por favor no responda a la misma.');
?>