<?php
	// No indexar
	header( 'X-Robots-Tag: noindex,nofollow' );

	// Incluimos libreria
	include( 'includes/application_top.php' );
	require( DIR_WS_LANGUAGES . $language . '/resolver_duda.php' );

	// Variables
	$sGetId = tep_db_prepare_input( $_GET['id'] );
	$sPostNombre = tep_db_prepare_input( $_POST['nombre'] );
	$sPostEmail = tep_db_prepare_input( $_POST['email'] );
	$sPostTelefono = tep_db_prepare_input( $_POST['telefono'] );
	$sPostMensaje = tep_db_prepare_input( $_POST['mensaje'] );
	$bError = false;

	// Si existe el mensaje de enviado, mostramos y paramos
	if( $messageStack->check( 'success' ) )
	{
		echo $messageStack->show( 'success' ) . '<br/>';
		exit(1);
	}

	// Consultamos el producto
	$aDatos = tep_db_query( 'select pd.products_name, p.products_model,  p.products_image
							 from products p
							 inner join products_description pd on (pd.products_id = p.products_id)
							 where p.products_status = 1 and p.products_id = "' . (int)$sGetId . '" and pd.language_id = "' . (int)$languages_id . '"' );

	// Si el producto existe
	if( tep_db_num_rows( $aDatos ) > 0 )
	{
		// Producto
		$aProducto = tep_db_fetch_array( $aDatos );

		// Si nos envian algo por post
		if( $_SERVER['REQUEST_METHOD'] == 'POST' )
		{
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
			$recaptcha = json_decode($curlData, true);

			// Validamos el captcha
			if (!$recaptcha["success"])
			{
				$bError = true;
				$messageStack->add( 'error', 'Error Captcha', 'error', 'true' );
			}

			/**
			 * #ZJE-152-24994
			 * @author Daniel Lucia <daniel.lucia@denox.es>
			 */
			
			$termsAgree = $rgpd->postFormCheckTermsTrade( 3 );
			if( $termsAgree == '' ) {
				$bError = true;
				$messageStack->add('contact_error', ERROR_POLITICA );
			}

			// Comprobamos si tenemos email o telefono relleno
			if( $sPostEmail == '' && $sPostTelefono == '' )
			{
				$bError = true;
				$messageStack->add( 'error', RESOLVER_DUDA_ERROR_EMAIL_TELEFONO, 'error', 'true' );
			}

			// Si tenemos email y no es correcto
			if( $sPostEmail != '' && !tep_validate_email( $sPostEmail ) )
			{
				$bError = true;
				$messageStack->add( 'error', RESOLVER_DUDA_ERROR_EMAIL, 'error', 'true' );
			}

			// Comprobamos mensaje
			if( $sPostMensaje == '' )
			{
				$bError = true;
				$messageStack->add( 'error', RESOLVER_DUDA_ERROR_MENSAJE, 'error', 'true' );
			}

			// Si no hemos recibido ningun error enviamos el email
			if( !$bError )
			{
				// Asunto email
				$sAsuntoEmail = sprintf( RESOLVER_DUDA_SEND_EMAIL_SUBJECT, $aProducto['products_name'], $sPostNombre );

				// Construimos el email
				$email = ' <table style="line-height: 20px; font-size: 15px; font-family: Arial; letter-spacing: 0.2px; color: #5f5f5f;" border="0" cellspacing="0" cellpadding="0">
								<tr>
									<td width="162" style="vertical-align: top; border-top: 1px solid #e4e4e4; border-right: 1px solid #e4e4e4; padding: 13px 0 13px 16px; text-align: left;">
										<font style="font-weight: bold">' . RESOLVER_DUDA_INPUT_NOMBRE . '</font>
									</td>
									<td style="padding: 13px 23px; border-top: 1px solid #e4e4e4">' . $sPostNombre . '</td>
								</tr>
								<tr>
									<td width="162" style="vertical-align: top; border-top: 1px solid #e4e4e4; border-right: 1px solid #e4e4e4; padding: 13px 0 13px 16px; text-align: left;">
										<font style="font-weight: bold">' . RESOLVER_DUDA_INPUT_EMAIL . '</font>
									</td>
									<td style="padding: 13px 23px; border-top: 1px solid #e4e4e4"><a href="mailto:' . $sPostEmail . '" style="color: #25a3d1; font-weight: bold;">' . $sPostEmail . '</a></td>
								</tr>
								<tr>
									<td width="162" style="vertical-align: top; border-top: 1px solid #e4e4e4; border-right: 1px solid #e4e4e4; padding: 13px 0 13px 16px; text-align: left;">
										<font style="font-weight: bold">' . RESOLVER_DUDA_INPUT_TELEFONO . '</font>
									</td>
									<td style="padding: 13px 23px; border-top: 1px solid #e4e4e4">' . $sPostTelefono . '</td>
								</tr>
								<tr>
									<td width="162" style="vertical-align: top; border-top: 1px solid #e4e4e4; border-right: 1px solid #e4e4e4; padding: 13px 0 13px 16px; text-align: left;">
										<font style="font-weight: bold">' . RESOLVER_DUDA_ASUNTO . '</font>
									</td>
									<td style="padding: 13px 23px; border-top: 1px solid #e4e4e4;">' . $sAsuntoEmail . '</td>
								</tr>
								<tr>
									<td width="162" style="vertical-align: top; border-top: 1px solid #e4e4e4; border-right: 1px solid #e4e4e4; padding: 13px 0 13px 16px; text-align: left;">
										<font style="font-weight: bold">' . RESOLVER_DUDA_ENLACE . '</font>
									</td>
									<td style="padding: 13px 23px; border-top: 1px solid #e4e4e4;">' . tep_href_link( 'product_info.php', 'products_id=' . $sGetId ) . '</td>
								</tr>
								<tr>
									<td width="162" style="vertical-align: top; border-top: 1px solid #e4e4e4; border-bottom: 1px solid #e4e4e4; border-right: 1px solid #e4e4e4; padding: 13px 0 13px 16px; text-align: left;">
										<font style="font-weight: bold">' . RESOLVER_DUDA_CONSULTA . '</font>
									</td>
									<td style="padding: 13px 23px; border-top: 1px solid #e4e4e4; border-bottom: 1px solid #e4e4e4;">' . str_replace( chr(13), "\n\n", $sPostMensaje ) . '</td>
								</tr>
							</table>';

				include( DIR_WS_MODULES . 'UHtmlEmails/'. ULTIMATE_HTML_EMAIL_LAYOUT . '/varios.php' );
				$email = $sHtmlEmail;

				// Enviamos el email a la tienda
				tep_mail( STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, EXTRA_SUBJECT_STOREOWNER . ' ' . $sAsuntoEmail, $email, $sPostNombre, $sPostEmail );

				// Mensaje y redireccionar
				$messageStack->addSession( 'success', RESOLVER_DUDA_EXITO, 'success' );
				tep_redirect( tep_href_link( 'resolver_duda.php' ) );
			}
		}

		// Si estamos logueados y no hemos enviado nada por post mostramos el nombre y email por defecto
		if( $_SERVER['REQUEST_METHOD'] == 'POST' && tep_session_is_registered( 'customer_id' ) )
		{
			// Nombre
			$sPostNombre = $customer_first_name;

			// Email
			$aDato = tep_db_query( 'select customers_firstname, customers_lastname, customers_email_address from customers where customers_id = "' . (int)$customer_id . '"' );
			$aDato = tep_db_fetch_array($aDato);
			$sPostEmail = $aDato['customers_email_address'];
		}

		echo '<div id="ltbx-cnsl">';
			echo '<div class="titl">' . sprintf( RESOLVER_DUDA_TITULO, $aProducto['products_name'] ) . '</div>';

			// Comprobamos si tenemos errores
			if( $messageStack->check( 'error' ) )
				echo $messageStack->show( 'error' );

			echo '<div id="rcmd-emil-cntd" style="position: relative;">';
				echo '<form id="contact_us_form" method="post" action="' . tep_href_link( 'resolver_duda.php?id=' . $sGetId ) . '" onsubmit="return app.get(\'alert\').formAjax(this);">';
					echo '<p>
						<label for="nombre">' . RESOLVER_DUDA_INPUT_NOMBRE . '</label>
						<input value="' . $sPostNombre . '" type="text" id="nombre" tabindex="1" autocomplete="off" name="nombre" />
					</p>';

					echo '<p>
						<label for="email">' . RESOLVER_DUDA_INPUT_EMAIL . '</label>
						<input value="' . $sPostEmail . '" type="text" id="email" tabindex="2" autocomplete="off" name="email" />
					</p>';

					echo '<p>
						<label for="telefono">' . RESOLVER_DUDA_INPUT_TELEFONO . '</label>
						<input value="' . $sPostTelefono . '" type="text" id="telefono" tabindex="3" autocomplete="off" name="telefono" />
					</p>';

					echo '<p>
						<label for="mensaje">' . RESOLVER_DUDA_INPUT_MENSAJE . '</label>
						<textarea tabindex="4" rows="7" id="mensaje" name="mensaje">' . $sPostMensaje . '</textarea>
					</p>';
					
					/**
					 * #ZJE-152-24994
					 * @author Daniel Lucia <daniel.lucia@denox.es>
					 */
					echo '<p>' . $rgpd->formCheckTermsTrade( 3 ) . '</p>';

					echo '<input id="submit-form" type="submit" class="sbmt" tabindex="5" value="' . RESOLVER_DUDA_INPUT_BOTON . '">';

					echo "<script>
					setTimeout(function(){
						grecaptcha.render('submit-form', {
						  'sitekey' : '" . RECAPTCHA_PUBLIC_KEY . "',
						  'callback' : captchaSubmitCita
						});
						jQuery( '#submit-form' ).prop( 'disabled', false );
					}, 200);

					var captchaSubmitCita = function (data) {
						jQuery('#contact_us_form').submit();
					}
					</script>";

					echo '<div class="clear"></div>';
				echo '</form>';
			echo '</div>';
		echo '</div>';
	}
	else
		header( 'HTTP/1.0 404 Not Found' );
?>