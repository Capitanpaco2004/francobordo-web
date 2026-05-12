<?php
/*
  $Id: customers_points.php, v 1.50 2005/AUG/10 15:17:12 dgw_ Exp $
  http://www.deep-silver.com

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2005 osCommerce

  Released under the GNU General Public License
*/
define('MOD_VER', '2.00');

define('HEADING_TITLE', 'Puntos Conseguidos por Clientes');
define('HEADING_RATE', 'Cambios : ');
define('HEADING_AWARDS', 'Conseguidos : ');
define('HEADING_REDEEM', 'Canjeados : ');
define('HEADING_POINT', 'punto');
define('HEADING_POINTS', 'puntos');
define('HEADING_TITLE_SEARCH', '<b>Buscar</b> (por nombre, mail o puntos totales) : ');

define('TABLE_HEADING_FIRSTNAME', 'Apellido');
define('TABLE_HEADING_LASTNAME', 'Nombre');
define('TABLE_HEADING_DOB', 'Fecha Nacimiento');
define('TABLE_HEADING_ACTION', 'Acci&oacute;n');
define('TABLE_HEADING_POINTS', 'Puntos');
define('TABLE_HEADING_POINTS_VALUE', 'Valor');
define('TABLE_HEADING_POINTS_EXPIRES', 'Expire');

define('TABLE_HEADING_SORT', 'Sort this row by ');
define('TABLE_HEADING_SORT_UA', ' --> A-B-C From Top');
define('TABLE_HEADING_SORT_U1', ' --> 1-2-3 From Top');
define('TABLE_HEADING_SORT_DA', ' --> Z-Y-X From Top');
define('TABLE_HEADING_SORT_D1', ' --> 3-2-1 From Top');

define('TEXT_SHOW_ALL', 'Show All');
define('TEXT_SORT_CUSTOMERS', 'Show Customers');
define('TEXT_SORT_POINTS', 'With points');
define('TEXT_SORT_NO_POINTS', 'Without points');
define('TEXT_SORT_BIRTH', 'B.day this month');
define('TEXT_SORT_BIRTH_NEXT', 'B.day next month');
define('TEXT_SORT_EXPIRE', 'Expire this month');
define('TEXT_SORT_EXPIRE_NEXT', 'Expire next month');
define('TEXT_SORT_EXPIRE_WIN', 'Expire within 1 month');
define('TEXT_DATE_ACCOUNT_CREATED', 'Cuenta Creada:');
define('TEXT_DATE_ACCOUNT_LAST_MODIFIED', '&uacute;ltima Modificaci&oacute;n:');

define('TEXT_INFO_HEADING_AJUST_POINTS', 'Ajustar los Puntos del Cliente.');
define('TEXT_INFO_DATE_LAST_LOGON', '&Uacute;ltima Visita:');
define('TEXT_INFO_NUMBER_OF_LOGONS', 'N&uacute;mero de Visitas:');
define('TEXT_INFO_COUNTRY', 'Pais:');
define('TEXT_INFO_NUMBER_OF_ORDERS', 'Total de Pedido :');
define('TEXT_INFO_NUMBER_OF_OTHERS', 'Registros introducidos por el Administrador:');
define('TEXT_INFO_NUMBER_OF_PENDING', 'Pedidos con puntos pendientes:');

define('TEXT_ADD_POINTS', 'A&ntilde;adir Puntos.');
define('TEXT_ADD_POINTS_LONG', 'Se puede a&ntilde;adir puntos a un cliente registr&aacute;ndolo o no en la cola de puntos pendientes.<br>Registr&aacute;ndo puntos en la cola, a&ntilde;adir&aacute; una l&iacute;nea a la tabla con el comentario. En caso contrario &uacute;nicamente se a&ntilde;adir&aacute;n los puntos.');
define('TEXT_ADJUST_INTRO', 'Esta opci&oacute;n te permite r&aacute;pidamente ajustar la cantidad de puntos.<br>Ten encuenta que esto reemplazar&aacute; el valor actual de puntos del cliente y a &eacute;ste no se le notificar&aacute;.');
define('TEXT_DELETE_POINTS', 'Elimina Puntos.');
define('TEXT_DELETE_POINTS_LONG', 'Se puede eliminar puntos a un cliente registr&aacute;ndolo o no en la cola de puntos pendientes.<br>Registr&aacute;ndo puntos en la cola, a&ntilde;adir&aacute; una l&iacute;nea a la tabla con el comentario. En caso contrario &uacute;nicamente se a&ntilde;adir&aacute;n los puntos.');
define('TEXT_POINTS_TO_ADD', 'Puntos a a&ntilde;adir :');
define('TEXT_POINTS_TO_AJUST', 'Nueva cantidad de Puntos :');
define('TEXT_POINTS_TO_DELETE', 'Puntos a elimiar :');
define('TEXT_COMMENT', 'Comentario :');

define('TEXT_QUEUE_POINTS_TABLE', '&iquest;Se A&ntilde;ade a la tabla de puntos de clientes?');
define('TEXT_NOTIFY_CUSTOMER', 'Informar al Cliente:');

define('BUTTON_TEXT_ADD_POINTS', 'A&ntilde;adir puntos');
define('BUTTON_TEXT_DELETE_POINTS', 'Eliminar Puntos');
define('TEXT_SET_EXPIRE', 'Ajustar nueva fecha de expiración de puntos');
define('BUTTON_TEXT_ADJUST_POINTS', 'Ajustar la cantidad actual de puntos');

define('EMAIL_SEPARATOR', 'También puede ponerse en contacto con nosotros por teléfono llamando al número <b>916 528 858</b> de lunes<br>
							a viernes de 10:00 a 19:00 h o a través de correo postal enviando una carta a nuestra dirección:<br>
							<b>Calle San Rafael, 8, 28108 Alcobendas, Madrid.</b> <br>');
define('EMAIL_TEXT_SUBJECT', 'Actualización de su Cuenta de Puntos.');
define('EMAIL_GREET_MR', 'Estimado S. %s,');
define('EMAIL_GREET_MS', 'Estimada Srta. %s,');
define('EMAIL_GREET_NONE', 'Estimado/a %s<br>');
define('EMAIL_TEXT', 'Este mail es para indicarte que tu Cuenta de Puntos por Compra ha sido actualizada.');
define('EMAIL_TEXT_BALANCE_ADD', '¡Enhorabuena! <br>Hemos añadido a su cuenta, %s Puntos valorados en %s');
define('EMAIL_TEXT_BALANCE_DEL', '· Lo lamentamos pero de su cuenta de puntos han sido deducidos un total de %s puntos valorados en %s .');
define('EMAIL_TEXT_BALANCE', '· Su saldo actual es de <b style="color: #22a1d1;">%s Puntos</b> valorados en %s<br>');
define('EMAIL_TEXT_SUCCESS_POINTS', 'Los Puntos están disponibles en su cuenta, en su próximo pedido usted podrá usarlos como parte del pago<br>
									 <br>Muchas gracias por comprar en <b style="color: #22a1d1;">' . STORE_NAME . '</b>  y esperamos poder servirle de nuevo<br>');
define('EMAIL_CONTACT', 'Si tiene alguna consulta o necesita ayuda con alguno de nuestros servicios online, por favor envíenos<br>un e-mail a: <b style="color: #22a1d1;">' . STORE_OWNER_EMAIL_ADDRESS . '</b><br>' );

define('SUCCESS_POINTS_UPDATED', '&iexcl;Atenci&oacute;n!Correcto!: La Cuenta de Puntos de los Clientes se ha actualizado.');
define('SUCCESS_DATABASE_UPDATED', '&iexcl;Atenci&oacute;n!Insertado en Cola!: La base de datos ha sido correctamente actualizada con este comentario: " '. $comment . ' ".');
define('NOTICE_EMAIL_SENT_TO', '&iexcl;Atenci&oacute;n! E-mail enviado a: %s');
define('WARNING_DATABASE_NOT_UPDATED', '&iexcl;Alerta!: Campos vac&iacute;os. Nada se ha cambiado. No se ha actualizado la base de datos.');
define('POINTS_ENTER_JS_ERROR', '&iexcl;Valor incorrecto!<br> Únicamente se aceptan números');

define('EMAIL_TEXT_INTRO', 'Le enviamos este correo para informarle de hemos actualizado su saldo de Puntos.');
define('EMAIL_TEXT_EXPIRE', '· Sus puntos caducarán el:  %s<br>');
define('EMAIL_TEXT_POINTS_URL', '· Este es el enlace para consultar su Cuenta de Puntos:<br><br>&nbsp;&nbsp;<a href="%s" style="font-weight: bold; color: #22a1d1;">%s</a>');
define('EMAIL_TEXT_POINTS_URL_HELP', '· Toda la información sobre el Sistema de Puntos puede consultarla en el siguiente enlace:<br><br>&nbsp;&nbsp;<a href="%s" style="font-weight: bold; color: #22a1d1;">%s</a>');
define('EMAIL_TEXT_COMMENT', 'Comentario: %s');

define('EMAIL_AUTO', 'Esta es una respuesta automática, por favor no responda a la misma.');

define('TEXT_LINK_CREDIT', 'Click here to run the <a href="customers_points_credit.php"><u>Auto Credit</u></a> or <a href="customers_points_expire.php"><u>Auto Expire</u></a> script manually.');
?>