<?php
	// Action: send_test — envía un email de prueba a la dirección indicada.
	//
	// Variables disponibles (inyectadas por index.php):
	//   $sUrlPage, $module (delivery_estimate_admin), $messageStack

	$sEmail = trim( tep_db_prepare_input( $_POST['test_email'] ?? '' ) );
	$nLangId = (int)( $_POST['test_lang_id'] ?? 0 );

	if( $sEmail === '' || ! filter_var( $sEmail, FILTER_VALIDATE_EMAIL ) ) {
		$messageStack->addSession( 'success', 'Introduce una dirección de email válida para la prueba.', 'error' );
		tep_redirect( tep_href_link( $sUrlPage ) );
	}

	$bOk = $module->sendTestEmail( $sEmail, $nLangId );

	if( $bOk ) {
		$messageStack->addSession( 'success', 'Email de prueba enviado a ' . htmlspecialchars( $sEmail, ENT_QUOTES, 'UTF-8' ) . '. Revisa la bandeja de entrada (o MailHog si estás en local).', 'success' );
	}
	else {
		$messageStack->addSession( 'success', 'No se pudo enviar el email de prueba. Revisa la configuración SMTP.', 'error' );
	}

	tep_redirect( tep_href_link( $sUrlPage ) );
?>
