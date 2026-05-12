<?php
	require( 'includes/application_top.php' );
	require( DIR_WS_LANGUAGES . $language . '/' . FILENAME_TE_LLAMAMOS );

	// Breadcrumb
	$breadcrumb->add( NAVBAR_TITLE, tep_href_link( FILENAME_TE_LLAMAMOS ) );
	if( ! isAjax() ) {
		tep_redirect('index.php');
	}

	// Si nos han enviado el formulario
	if( isset( $_GET['action'] ) && $_GET['action'] == 'send' )
	{

		// Variables
		$sNombre = tep_db_prepare_input( $_POST['name'] );
		$sPhone = tep_db_prepare_input( $_POST['phone'] );
		$sDay = tep_db_prepare_input( $_POST['day'] );
		$sHour = tep_db_prepare_input( $_POST['hour'] );
		$termsAgree = $rgpd->postFormCheckTermsGeneral();
		$bError = false;

		// Reseteamos
		$messageStack->reset();
		/*
		// CAPTCHA //

		// Campos en array
		$aFields = array(
			'privatekey' => urlencode( RECAPTCHA_PRIVATE_KEY ),
			'remoteip' => $_SERVER['REMOTE_ADDR'],
			'challenge' => urlencode( $_POST['recaptcha_challenge_field'] ),
			'response' => urlencode( $_POST['recaptcha_response_field'] )
		);

		// Campos en string
		$sFields = '';
		foreach( $aFields as $sKey => $sValue )
			$sFields .= $sKey . '=' . $sValue . '&';
		rtrim( $sFields, '&' );

		$cURL = curl_init();
		curl_setopt( $cURL, CURLOPT_URL, 'https://www.google.com/recaptcha/api/verify' );
		curl_setopt( $cURL, CURLOPT_HEADER, 0 );
		curl_setopt( $cURL, CURLOPT_POST, count( $aFields ) );
		curl_setopt( $cURL, CURLOPT_POSTFIELDS, $sFields );
		curl_setopt( $cURL, CURLOPT_RETURNTRANSFER, true );
		$sResponse = curl_exec( $cURL );
		curl_close( $cURL );

		// Validamos el captcha
		if( ! preg_match( '/^true/i', $sResponse ) )
		{
			$messageStack->add( 'contact_error', ERROR_CAPTCHA );
			$bError = true;
		}
		*/
		// Comprobamos la politica de privacidad
		if( $termsAgree == '' )
		{
          $error = true;
          $messageStack->add('contact_error', ERROR_POLITICA );
		}

		// Comprobamos el nombre
		if( $sNombre == '' )
		{
			$messageStack->add( 'contact_error', ERROR_NOMBRE );
			$bError = true;
		}

		// Comprobamos el teléfono
		if( $sPhone == '' )
		{
			$messageStack->add( 'contact_error', ERROR_PHONE );
			$bError = true;
		}

		// Si no existen errores enviamos el email
		if( !$bError )
		{
			// Construimos el email
			$email = ' <table style="line-height: 20px; font-size: 15px; font-family: Arial; letter-spacing: 0.2px; color: #5f5f5f; width: 100%;" border="0" cellspacing="0" cellpadding="0">
							<tr>
								<td width="162" style="vertical-align: top; border-top: 1px solid #e4e4e4; border-right: 1px solid #e4e4e4; padding: 13px 0 13px 16px; text-align: left;">
									<font style="font-weight: bold">' . FORM_NOMBRE . '</font>
								</td>
								<td style="padding: 13px 23px; border-top: 1px solid #e4e4e4">' . $sNombre . '</td>
							</tr>
							<tr>
								<td width="162" style="vertical-align: top; border-top: 1px solid #e4e4e4; border-right: 1px solid #e4e4e4; padding: 13px 0 13px 16px; text-align: left;">
									<font style="font-weight: bold">' . FORM_PHONE . '</font>
								</td>
								<td style="padding: 13px 23px; border-top: 1px solid #e4e4e4;">' . $sPhone . '</td>
							</tr>
							<tr>
								<td width="162" style="vertical-align: top; border-top: 1px solid #e4e4e4; border-right: 1px solid #e4e4e4; padding: 13px 0 13px 16px; text-align: left;">
									<font style="font-weight: bold">' . FORM_DAY . '</font>
								</td>
								<td style="padding: 13px 23px; border-top: 1px solid #e4e4e4;">' . $sDay . '</td>
							</tr>
							<tr>
								<td width="162" style="vertical-align: top; border-top: 1px solid #e4e4e4; border-right: 1px solid #e4e4e4; padding: 13px 0 13px 16px; text-align: left;">
									<font style="font-weight: bold">' . FORM_HOUR . '</font>
								</td>
								<td style="padding: 13px 23px; border-top: 1px solid #e4e4e4;">' . $sHour . '</td>
							</tr>
						</table>';

			include( DIR_WS_MODULES . 'UHtmlEmails/'. ULTIMATE_HTML_EMAIL_LAYOUT . '/varios.php' );
			$email = $sHtmlEmail;

			// Enviamos el email a la tienda
			//tep_mail( STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, EXTRA_SUBJECT_STOREOWNER . ' ' . 'Petición de llamada', $email, $sNombre, 'llamame@francobordo.com' );
			//tep_mail( STORE_OWNER, 'daniellucia84@gmail.com', EXTRA_SUBJECT_STOREOWNER . ' ' . 'Petición de llamada', $email, $sNombre, STORE_OWNER_EMAIL_ADDRESS );
			tep_mail( STORE_OWNER, 'llamame@gespaula.es', EXTRA_SUBJECT_STOREOWNER . ' ' . 'Petición de llamada', $email, $sNombre, 'llamame@francobordo.com' );

			// Redireccionamos
			$messageStack->add_session( 'contact_correcto', CORRECTO );
			tep_redirect( tep_href_link( 'te_llamamos.php' ) );
		}

	}
	// Si estamos logeados obtenemos el nombre y email
	elseif( $customerCore->hasLogin() ) 
	{
		$aDatos = tep_db_query( 'select customers_firstname, customers_lastname, customers_email_address
								 from ' . TABLE_CUSTOMERS . '
								 where customers_id = ' . (int)$customer_id );
		$aDatos = tep_db_fetch_array( $aDatos );
		$sNombre = $aDatos['customers_firstname'] . ' ' . $aDatos['customers_lastname'];
	}


?>

<div class="teLlamamosContainer">
	<form method="post" action="<?php echo tep_href_link( FILENAME_TE_LLAMAMOS, 'action=send'); ?>" onsubmit="teLlamamosAjax(); return false;">
		<div class="teLlamamosCampos">
			<h2><?php echo HEADING_TITLE; ?></h2>
			<p class="campo infoText">
				<?php echo FORM_INFO; ?>
			</p>
			<?php if( $messageStack->size( 'contact_error' ) > 0 ): ?>
				<div class="msje msje-eror">
					<div class="msje-icon"></div>
					<?php echo str_replace( chr(10), ' ', $messageStack->output( 'contact_error' ) ); ?>
				</div>
			<?php endif; ?>

			<?php if( $messageStack->size( 'contact_correcto' ) > 0 ): ?>
				<div class="msje msje-crrt">
					<div class="msje-icon"></div>
					<?php echo str_replace( chr(10), ' ', $messageStack->output( 'contact_correcto' ) ); ?>
				</div>
			<?php endif; ?>
			<p class="campo icon name">
				<?php echo tep_draw_input_field( 'name', $sNombre, ' placeholder="'.FORM_NOMBRE.'" ' ); ?>
			</p>

			<p class="campo icon phone">
				<?php echo tep_draw_input_field( 'phone', $sPhone, ' placeholder="'.FORM_PHONE.'" ' ); ?>
			</p>

			<p class="campo icon day">
				<input name="day" placeholder="<?php echo FORM_DAY; ?>" data-toggle="datepicker" id="day" type="date" value="<?php echo $sDay; ?>">
			</p>

			<p class="campo icon hour">
				<?php echo tep_draw_input_field( 'hour', $sHour, ' placeholder="'.FORM_HOUR.'" class="timePicker" ' ); ?>
			</p>
		</div>
		<div class="teLlamamosBotonera">
			<p>
				<?php echo $rgpd->formCheckTermsGeneral(); ?>
			</p>
			<p class="Buttons">
				<button type="submit" class="Button buttonGradient">Enviar</button>
			</p>
		</div>
	</form>
</div>

<?php
require( DIR_WS_INCLUDES . 'application_bottom.php' );
?>
