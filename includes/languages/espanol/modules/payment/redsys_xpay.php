<?php
	// title en TEXTO PLANO: se persiste en orders.payment_method varchar(64). El icono
	// del selector se gestiona aparte (PNG payment_redsys_xpay.png / theme), no en el title.
	if (!defined('MODULE_PAYMENT_REDSYS_XPAY_TEXT_TITLE')) define('MODULE_PAYMENT_REDSYS_XPAY_TEXT_TITLE', 'Apple Pay / Google Pay');
	if (!defined('MODULE_PAYMENT_REDSYS_XPAY_TEXT_DESCRIPTION')) define('MODULE_PAYMENT_REDSYS_XPAY_TEXT_DESCRIPTION', '<strong>Descripción</strong><br>Apple Pay y Google Pay a través de Redsys (X-Pay). Comparte FUC, clave SHA-256 y terminal con el módulo Redsys de tarjeta. El cliente paga en la página segura de Redsys, que detecta automáticamente el wallet del dispositivo.');
	if (!defined('MODULE_PAYMENT_REDSYS_XPAY_TEXT_ERROR_MESSAGE')) define('MODULE_PAYMENT_REDSYS_XPAY_TEXT_ERROR_MESSAGE','Error en el proceso');
	if (!defined('MODULE_PAYMENT_REDSYS_XPAY_TEXT_CANCEL')) define('MODULE_PAYMENT_REDSYS_XPAY_TEXT_CANCEL','Cancelado el proceso');
?>
