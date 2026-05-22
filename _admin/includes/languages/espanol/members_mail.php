<?php
	// Correo de APROBACIÓN (acción accept). Cuerpo autocontenido; %s = importe mínimo anual configurable.
	define('EMAIL_TEXT_CONFIRM_M', "Nos complace informarte de que hemos recibido correctamente tu solicitud de cliente profesional para <b>" . STORE_NAME . "</b> y que tu cuenta ha sido aprobada.\n\nA partir de ahora, ya puedes acceder a nuestra tienda con tus datos de usuario para consultar condiciones profesionales, ver productos y realizar tus compras.\n\nTe recordamos que las cuentas profesionales están sujetas a un volumen mínimo de compras de <b>%s € anuales</b>. En caso de no alcanzarse dicho importe durante el año, la cuenta pasará a modalidad retail de forma automática.\n\nSi necesitas ayuda o soporte técnico, puedes contactar con nosotros en: " . STORE_OWNER_EMAIL_ADDRESS . "\n\nNota: Si no has solicitado el registro de una cuenta en nuestra tienda, por favor, comunícanoslo escribiendo a " . STORE_OWNER_EMAIL_ADDRESS . ".\n\nGracias por confiar en " . STORE_NAME . ".\n\nUn saludo,\nEl equipo de " . STORE_NAME);

	define('EMAIL_TEXT_SUBJECT_M', '¡Tu cuenta ha sido aprobada! - Francobordo');

	// Correo de RECHAZO (acción confirm). Cuerpo autocontenido (incluye contacto, nota y despedida).
	define('EMAIL_TEXT_CANCEL_M', "Gracias por registrarte en " . STORE_NAME . ".\n\nTras revisar tu solicitud, lamentamos informarte de que tu cuenta no ha sido aprobada como cliente profesional. No obstante, puedes seguir realizando tus compras en nuestra tienda como cliente final.\n\nSolo damos de alta como profesionales a aquellas empresas o autónomos cuya actividad es la compraventa de accesorios náuticos y mantenimiento o reparación de embarcaciones o buques.\n\nSi en el futuro consideras que cumples los requisitos para acceder a una cuenta profesional, podrás volver a contactarnos para que revisemos tu caso.\n\nSi tienes cualquier pregunta, puedes escribirnos a: " . STORE_OWNER_EMAIL_ADDRESS . "\n\nUn saludo,\nEl equipo de " . STORE_NAME);

	define('EMAIL_TEXT_SUBJECT_CANCEL_M', 'Tu cuenta de profesional no ha sido aprobada - Francobordo');

	define('EMAIL_CONTACT_M', 'Si tiene alguna pregunta, no dude en contactarnos.');
	define('EMAIL_WARNING_M', 'Este es un email automático, por favor no responda directamente a este mensaje.');
?>
