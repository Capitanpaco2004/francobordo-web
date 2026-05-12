<?php
	require( 'includes/application_top.php' );
	require( DIR_WS_LANGUAGES . $language . '/' . FILENAME_CONTACT_US );

	// Breadcrumb
	$breadcrumb->add( NAVBAR_TITLE, tep_href_link( FILENAME_CONTACT_US ) );

	// Si nos han enviado el formulario
	if( isset( $_GET['action'] ) && $_GET['action'] == 'send' )
	{
		// Variables
		$sNombre = tep_db_prepare_input( $_POST['name'] );
		$sEmail = tep_db_prepare_input( $_POST['email'] );
		$sAsunto = tep_db_prepare_input( $_POST['subject'] );
		$sConsulta = tep_db_prepare_input( $_POST['enquiry'] );
		$termsAgree = isset($_POST['policy']) && intval($_POST['policy']) == 1 ? 'true' : 'false';
		$bError = false;

		// Reseteamos
		$messageStack->reset();

		// CAPTCHA //

		$secret = RECAPTCHA_PRIVATE_KEY;
        $remoteip = $_SERVER["REMOTE_ADDR"];
        $response = $_POST["g-recaptcha-response"];
        $url = "https://www.google.com/recaptcha/api/siteverify";

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, array(
            'secret' => $secret,
            'response' => $response,
            'remoteip' => $remoteip
            ));

        $curlData = curl_exec($curl);
        unset($curl);
        $recaptcha = json_decode($curlData, true);

        if (!$recaptcha["success"]) {
			$messageStack->add( 'contact_error', ERROR_CAPTCHA );
			$bError = true;
		}

		// Comprobamos la politica de privacidad
		if( $termsAgree != 'true' )
		{
			$bError = true;
			$messageStack->add( 'contact_error', ERROR_POLITICA );
		}

		// Comprobamos el nombre
		if( $sNombre == '' )
		{
			$messageStack->add( 'contact_error', ERROR_NOMBRE );
			$bError = true;
		}

		// Comprobamos el email
		if( $sEmail == '' || ! tep_validate_email( $sEmail ) )
		{
			$messageStack->add( 'contact_error', ERROR_EMAIL );
			$bError = true;
		}

		// Comprobamos la consulta
		if( $sConsulta == '' )
		{
			$messageStack->add( 'contact_error', ERROR_CONSULTA );
			$bError = true;
		}

		// Si no existen errores enviamos el email
		if( !$bError )
		{
			// Construimos el email
			$email = ' <table style="line-height: 20px; font-size: 15px; font-family: Arial; letter-spacing: 0.2px; color: #5f5f5f;" border="0" cellspacing="0" cellpadding="0">
							<tr>
								<td width="162" style="vertical-align: top; border-top: 1px solid #e4e4e4; border-right: 1px solid #e4e4e4; padding: 13px 0 13px 16px; text-align: left;">
									<font style="font-weight: bold">' . FORM_NOMBRE . '</font>
								</td>
								<td style="padding: 13px 23px; border-top: 1px solid #e4e4e4">' . $sNombre . '</td>
							</tr>
							<tr>
								<td width="162" style="vertical-align: top; border-top: 1px solid #e4e4e4; border-right: 1px solid #e4e4e4; padding: 13px 0 13px 16px; text-align: left;">
									<font style="font-weight: bold">' . FORM_EMAIL . '</font>
								</td>
								<td style="padding: 13px 23px; border-top: 1px solid #e4e4e4"><a href="mailto:' . $sEmail . '" style="color: #25a3d1; font-weight: bold;">' . $sEmail . '</a></td>
							</tr>
							<tr>
								<td width="162" style="vertical-align: top; border-top: 1px solid #e4e4e4; border-right: 1px solid #e4e4e4; padding: 13px 0 13px 16px; text-align: left;">
									<font style="font-weight: bold">' . FORM_ASUNTO . '</font>
								</td>
								<td style="padding: 13px 23px; border-top: 1px solid #e4e4e4;">' . $sAsunto . '</td>
							</tr>
							<tr>
								<td width="162" style="vertical-align: top; border-top: 1px solid #e4e4e4; border-bottom: 1px solid #e4e4e4; border-right: 1px solid #e4e4e4; padding: 13px 0 13px 16px; text-align: left;">
									<font style="font-weight: bold">' . FORM_CONSULTA . '</font>
								</td>
								<td style="padding: 13px 23px; border-top: 1px solid #e4e4e4; border-bottom: 1px solid #e4e4e4;">' . str_replace( chr(13), "\n\n", $sConsulta ) . '</td>
							</tr>
						</table>';

			include( DIR_WS_MODULES . 'UHtmlEmails/'. ULTIMATE_HTML_EMAIL_LAYOUT . '/varios.php' );
			$email = $sHtmlEmail;

			// Enviamos el email a la tienda
			tep_mail( STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, EXTRA_SUBJECT_STOREOWNER . ' ' . $sAsunto, $email, $sNombre, $sEmail );

			// Si hemos pedido copia
			if( $_POST['send_copy_customer'] )
				tep_mail( $sNombre, $sEmail, EXTRA_SUBJECT_CUSTOMER . ' ' . $sAsunto, $email, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS );

			// Redireccionamos
			$messageStack->add_session( 'contact_correcto', CORRECTO );
			tep_redirect( tep_href_link( FILENAME_CONTACT_US ) );
		}
	}
	// Si estamos logeados obtenemos el nombre y email
	elseif( tep_session_is_registered('customer_id') )
	{
		$aDatos = tep_db_query( 'select customers_firstname, customers_lastname, customers_email_address
								 from ' . TABLE_CUSTOMERS . '
								 where customers_id = ' . (int)$customer_id );
		$aDatos = tep_db_fetch_array( $aDatos );
		$sNombre = $aDatos['customers_firstname'] . ' ' . $aDatos['customers_lastname'];
		$sEmail = $aDatos['customers_email_address'];
	}

	require(DIR_THEME. 'html/header.php');
?>

<div class="information_contenido fced">
	<p>
		<?php echo getInformationByID( 21 ); ?>
	</p>
	<p>&nbsp;</p>
	<div style="background-image: url('theme/web/images/general/icon_tlf.png'); background-position: 23px 8px; padding: 16px 16px 16px 78px; background-color: #f3f3f3;" class="info-list-imge">
		<?php echo CONTACT_HORARIO; ?>
	</div>
	<p>&nbsp;</p>


	<p>&nbsp;</p>

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

	<?php echo tep_draw_form('contact_us', tep_href_link(FILENAME_CONTACT_US, 'action=send'), 'post', ' id="contact_us_form" '); ?>
		<div style="background-color: #f3f3f3; padding: 12px 11px;">
			<p class="campo">
				<label for="name"><?php echo FORM_NOMBRE; ?></label>
				<?php echo tep_draw_input_field( 'name', $sNombre ); ?>
			</p>

			<p class="campo">
				<label for="email"><?php echo FORM_EMAIL; ?></label>
				<?php echo tep_draw_input_field( 'email', $sEmail ); ?>
			</p>

			<p class="campo">
				<label for="subject"><?php echo FORM_ASUNTO; ?></label>
				<?php echo tep_draw_input_field( 'subject', $sAsunto ); ?>
			</p>

			<p class="campo">
				<label for="enquiry"><?php echo FORM_CONSULTA; ?></label>
				<?php echo tep_draw_textarea_field( 'enquiry', 'soft', 50, 15, tep_sanitize_string( $sConsulta, '', false ) ); ?>
			</p>
		</div>

		<p class="campo">
			<div class="campo" id="recaptcha_div"></div>
		</p>

		<p class="campo xform">
			<?php echo '<input type="checkbox" name="send_copy_customer" value="1" id="send_copy_customer"><label style="margin-right: 0px;" for="send_copy_customer"><span></span> ' . FORM_COPIA . '</label>'; ?>
		</p>
		<p class="campo xform">
		<?php 
		$aText = json_decode( str_replace( array( '\"' ), array('"'), RGPD_TERMS_TRADE_TEXT_CHECK ), true );
		$aTextTooltop = json_decode( str_replace( array( '\"' ), array('"'), RGPD_TERMS_TRADE_TEXT ), true );
		echo '<input type="checkbox" name="policy" value="1" id="policy" required><label style="margin-right: 0px;" for="policy"><span></span>' . str_replace( '{LINK}', tep_href_link('information.php', 'info_id=' . RGPD_TERMS_TRADE_INFO_ID),  html_entity_decode( $aText[$languages_id] ) ) . ' <i title="' . $aTextTooltop[$languages_id] . '" class="fa fa-exclamation-circle"></i></label>';

		//echo $rgpd->formCheckTermsGeneral(); 
		
		?>
		</p>

		<div class="botonera">
			<button class="g-recaptcha bton-dflt" data-sitekey="<?php echo RECAPTCHA_PUBLIC_KEY; ?>" data-callback="captchaSubmit"><?php echo IMAGE_BUTTON_CONTINUE; ?></button>
		</div>
	</form>
</div>

<br>
<iframe src="https://www.google.com/maps/embed?pb=!4v1575973713949!6m8!1m7!1sCAoSLEFGMVFpcFBkYU81U2hGQ3h1dlVQelhTY0IxMmhXam9XR2NGaVFySWlVOWtC!2m2!1d40.52521891828918!2d-3.659400224858473!3f39.76!4f2.6400000000000006!5f0.7820865974627469" width="100%" height="350" frameborder="0" style="border:0; margin:40px 0;" allowfullscreen=""></iframe>
<?php
	require( DIR_THEME. 'html/footer.php' );
	require( DIR_WS_INCLUDES . 'application_bottom.php' );
?>
