<?php

/**
 * #XCC-313-91043
 */

define('NAVBAR_TITLE_1', 'Mi Cuenta');
define('NAVBAR_TITLE_2', 'Mis datos de afiliado');
define('NAVBAR_TITLE_3', 'Editar mi información');
define('HEADING_VALUE_COMISSION', 'Acumulado');
define('HEADING_COMISSION', 'Comisión');
define('HEADING_COMISSION_STATUS', 'Estado');
define('HEADING_COUPON', 'Cupón:');
define('HEADING_BIO', 'Bio:');
define('HEADING_BIO_IMAGE', 'Imagen:');
define('HEADING_URL', 'Url afiliado:');
define('HEADING_DETAILS', 'Detalles');
define('HEADING_DATE', 'Fecha pedido');
define('HEADING_DAYS_LEFT', 'Días verificación de pedido');

define('HEADING_HISTORY_ID', 'ID');
define('HEADING_HISTORY_TOTAL', 'Total');
define('HEADING_HISTORY_STATUS', 'Estado');
define('HEADING_HISTORY_DATE', 'Fecha solicitud');
define('HEADING_HISTORY', 'Historial de pagos');
define('TEXT_BOTTOM', '
<div class="info-invoices">
<i class="fas fa-info-circle"></i>
<ul>
	<li>La factura debe enviarse al email <strong>%s</strong></li>
	<li>El asunto del email de la factura después de "Retirar fondos" debe contener el ID de la retirada Ejemplo: 0001 + Fecha de la retirada</li>
	<li><strong>Importante.</strong> El importe mínimo a retirar son <strong>%s</strong> por lo que en caso de necesitar una transfertencia inferior debereis contactar con ' . STORE_OWNER_EMAIL_ADDRESS . ' para gestionar la  retirada por parte de nuestros administradores</li>
</ul>
<p style="margin-top: 10px;"><a href="'.tep_href_link('information.php', 'info_id=' . (defined('AFFILLIATES_INFO_ID') ? AFFILLIATES_INFO_ID : '0')).'" target="_blank" style="color: #2faded; text-decoration: underline;">Más información</a></p>
</div>');
