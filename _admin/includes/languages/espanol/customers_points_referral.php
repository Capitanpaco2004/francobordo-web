<?php
/*
  $Id: customers_points_pending.php, V2.1rc2a 2008/SEP/29 15:17:12 dsa_ Exp $
  http://www.deep-silver.com

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2005 osCommerce

  Released under the GNU General Public License
*/
define('MOD_VER', '2.00');

define('HEADING_TITLE', 'Referidos- Puntos Pendientes de Comentarios');
define('HEADING_RATE', 'Exchange Rates : ');
define('HEADING_AWARDS', 'Conseguidos : ');
define('HEADING_REDEEM', 'Canjeados : ');
define('HEADING_POINT', 'punto');
define('HEADING_POINTS', 'puntos');
define('HEADING_TITLE_SEARCH', 'Buscar Nº Pedido:');

define('TABLE_HEADING_CUSTOMERS', 'Clientes');
define('TABLE_HEADING_POINTS_TYPE', 'Concepto Puntos:');
define('TABLE_HEADING_DATE_ADDED', 'Fecha:');
define('TABLE_HEADING_POINTS_STATUS', 'Points Status');
define('TABLE_HEADING_ACTION', 'Action');
define('TABLE_HEADING_POINTS', 'Puntos');
define('TABLE_HEADING_POINTS_VALUE', 'Valor:');

define('TABLE_HEADING_SORT', 'Sort this rows by ');
define('TABLE_HEADING_SORT_UA', ' --> A-B-C From Top');
define('TABLE_HEADING_SORT_U1', ' --> 1-2-3 From Top');
define('TABLE_HEADING_SORT_DA', ' --> Z-Y-X From Top');
define('TABLE_HEADING_SORT_D1', ' --> 3-2-1 From Top');

define('TEXT_DEFAULT_REFERRAL', 'Puntos Referidos');
define('TEXT_DEFAULT_REVIEWS', 'Puntos por Comentario');
define('TEXT_TYPE_REFERRAL', 'Referidos');
define('TEXT_TYPE_REVIEW', 'Comentarios');

define('TEXT_POINTS_PENDING', 'Pendientes');
define('TEXT_POINTS_CONFIRMED', 'Confirmados');
define('TEXT_POINTS_CANCELLED', 'Cancelados');
define('TEXT_SHOW_ALL', 'Mostrar Todos');

define('TEXT_INFO_POINTS_COMMENT', 'Current Points Comment : ');
define('TEXT_INFO_ORDER_ID', 'Order Id:');
define('TEXT_INFO_ORDER_TOTAL', 'Order Total:');
define('TEXT_INFO_ORDER_STATUS', 'Order Status:');
define('TEXT_INFO_PRODUCT_ID', 'Product Id:');
define('TEXT_INFO_REVIEW_ID', 'Comentario Id:');
define('TEXT_INFO_PRODUCT_NAME', 'Producto Comentado:');
define('TEXT_INFO_REFERRED', 'Referred:');
define('TEXT_INFO_PAYMENT_METHOD', 'Payment Method:');
define('TEXT_INFO_CURRENT_BALANCE', 'Current Points Balance:');

define('TEXT_INFO_HEADING_ADJUST_POINTS', 'Adjust Pending Points.');
define('TEXT_INFO_HEADING_DELETE_RECORD', 'Delete record');
define('TEXT_INFO_HEADING_PENDING_NO', 'Pending points for order no.');
define('TEXT_CONFIRM_POINTS', 'Confirm Pending Points to Customer ?');
define('TEXT_CONFIRM_POINTS_LONG', 'You can confirm points to customer with/without queuing points table.<br>confirming points without queuing will remove this line from table else, the Current points status will replaced with "Confirmed" .');
define('TEXT_CANCEL_POINTS', 'Cancel Customer Pending Points?');
define('TEXT_CANCEL_POINTS_LONG', 'You can cancel points to customer with/without queuing points table.<br>Cancelling points without queuing will remove this line from table else, pending points status will show "Cancelled" and default comment will be replaced with your Cancellation Reason.');
define('TEXT_CANCELLATION_REASON', 'Cancellation Reason :');
define('TEXT_ADJUST_INTRO', 'This option enable you to adjust the total amount of pending points before confirming them.<br>Note that this will replace the current pending points amount and can not be undone.');
define('TEXT_DELETE_INTRO', 'Are you sure you want to delete this record ?<br>This will remove the recored from database.');
define('TEXT_POINTS_TO_ADJUST', 'New points amount :');
define('TEXT_ROLL_POINTS', 'Roll Back points.');
define('TEXT_ROLL_POINTS_LONG', 'This option enable you to rollback confirmed points to pending status.<br>Points will be deducted from customer account and status will show default pending status.');
define('TEXT_ROLL_REASON', 'Roll Back Reason :');

define('TEXT_QUEUE_POINTS_TABLE', 'Queue customers points table');
define('TEXT_NOTIFY_CUSTOMER', 'Notify Customer');
define('TEXT_SET_EXPIRE', 'Set new expire date');

define('BUTTON_TEXT_ADJUST_POINTS', 'Adjust the current pending points amount');
define('BUTTON_TEXT_CANCEL_PENDING_POINTS', 'Cancel Customer Points');
define('BUTTON_TEXT_CONFIRM_PENDING_POINTS', 'Confirm Points to Customer');
define('BUTTON_TEXT_REMOVE_RECORD', 'Delete this record from databse');
define('BUTTON_TEXT_ROLL_POINTS', 'Roll Back points to pending status');
define('ICON_PREVIEW_EDIT', 'View order details or edit status');
define('ICON_REVIEWS_EDIT', 'View or edit Review contains');

define('EMAIL_SEPARATOR', 'También puede ponerse en contacto con nosotros por teléfono llamando al número <b>916 528 858</b> de lunes<br>
							a viernes de 10:00 a 19:00 h o a través de correo postal enviando una carta a nuestra dirección:<br>
							<b>Calle San Rafael, 8, 28108 Alcobendas, Madrid.</b> <br>');
define('EMAIL_TEXT_SUBJECT', 'Actualización de Puntos de la Cuenta');
define('EMAIL_TEXT_SUBJECT', 'Actualizacion de Puntos .');
define('EMAIL_GREET_MR', 'Estimado S. %s,');
define('EMAIL_GREET_MS', 'Estimada Srta. %s,');
define('EMAIL_GREET_NONE', 'Estimado/a %s<br>');
define('EMAIL_TEXT_ORDER_NUMBER', 'N&deg; de Pedido:');
define('EMAIL_TEXT_DATE_ORDERED', 'Fecha de Pedido:');
define('EMAIL_TEXT_ORDER_STAUTS', 'Estado del pedido:');
define('EMAIL_TEXT_INTRO', 'Le enviamos este correo para informarle de que sus puntos han sido activados');
define('EMAIL_TEXT', 'Este mail te informa que tu cuenta de Puntos por Compra se ha actualizado.');
define('EMAIL_TEXT_BALANCE_CANCELLED', 'Le enviamos este correo para informarle que sus puntos han sido cancelados.');
define('EMAIL_TEXT_BALANCE_CONFIRMED', 'Puntos confirmados:');
define('EMAIL_TEXT_BALANCE_ROLL_BACK', '· Los puntos Confirmados por este Pedido se han retrotraido al Estado indicado.');
define('EMAIL_TEXT_ROLL_COMMENT', 'Comentario :');
define('EMAIL_TEXT_BALANCE', '· Su saldo actual es de <b style="color: #22a1d1;">%s Puntos</b> valorados en %s<br>');
define('EMAIL_TEXT_EXPIRE', '· Sus puntos caducarán el:  %s<br>');
define('EMAIL_TEXT_POINTS_URL', '· Este es el enlace para consultar su Cuenta de Puntos:<br><br>&nbsp;&nbsp;<a href="%s" style="font-weight: bold; color: #22a1d1;">%s</a>');
define('EMAIL_TEXT_POINTS_URL_HELP', '· Toda la información sobre el Sistema de Puntos puede consultarla en el siguiente enlace:<br><br>&nbsp;&nbsp;<a href="%s" style="font-weight: bold; color: #22a1d1;">%s</a>');
define('EMAIL_TEXT_COMMENT', 'Motivo de la cancelación :');
define('EMAIL_TEXT_SUCCESS_POINTS', 'Los Puntos están disponibles en su cuenta, en su próximo pedido usted podrá usarlos como parte del pago<br>
									 <br>Muchas gracias por comprar en <b style="color: #22a1d1;">' . STORE_NAME . '</b>  y esperamos poder servirle de nuevo<br>');
define('EMAIL_CONTACT', 'Si tiene alguna consulta o necesita ayuda con alguno de nuestros servicios online, por favor envíenos<br>un e-mail a: <b style="color: #22a1d1;">' . STORE_OWNER_EMAIL_ADDRESS . '</b><br>');

define('SUCCESS_POINTS_UPDATED', 'Correcto. La cuenta de Puntos del Cliente ha sido actualizada correctamente.');
define('SUCCESS_DATABASE_UPDATED', 'Actualizada Cola: La base de datos ha sido actualizada correctamente y los puntos se han asignado como  ' . TEXT_POINTS_CANCELLED . '  con este comentario: " '. $comment . ' ".');
define('NOTICE_EMAIL_SENT_TO', '&iexcl;Atenci&oacute;n! Se ha mandado un E-mail a : %s');
define('NOTICE_RECORED_REMOVED', '&iexcl;Atenci&oacute;n! Los puntos asignados al pedido No. ' . $oID . ' se han borrado de la base de datos.');
define('WARNING_DATABASE_NOT_UPDATED', '&iexcl;Aviso! Campos vac&iacute;os, nada que cambiar. La base de datos no se ha actualizado.');
define('POINTS_ENTER_JS_ERROR', '&iexcl;Valor incorrecto! \n En este campo se deben introducir &uacute;nicamente n&uacute;meros.');

define('TEXT_LINK_CREDIT', 'Pulsar aqui para activar <a href="customers_points_credit.php"><u>Auto Credit</u></a> or <a href="customers_points_expire.php"><u>Auto Expire</u></a> script manually.');

define('EMAIL_AUTO', 'Esta es una respuesta automática, por favor no responda a la misma.');
?>