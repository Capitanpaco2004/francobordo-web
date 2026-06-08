<?php
	// No indexar
	header( 'X-Robots-Tag: noindex,nofollow' );

	require( 'includes/application_top.php' );
	require( DIR_WS_LANGUAGES . $language . '/recomendar-email.php' );

	function textLinks($sText)
	{
		$sText = preg_replace( '#(script|about|applet|activex|chrome):#is', "\\1:", $sText );
		$sText = ' ' . $sText;
		$sText = preg_replace( "#(^|[\n ])([\w]+?://[\w\#$%&~/.\-;:=,?@\[\]+]*)#is", "\\1<a style=\"color:#135EAE;outline:medium none;\" href=\"\\2\" target=\"_blank\">\\2</a>", $sText );
		$sText = preg_replace( "#(^|[\n ])((www|ftp)\.[\w\#$%&~/.\-;:=,?@\[\]+]*)#is", "\\1<a style=\"color:#135EAE;outline:medium none;\" href=\"http://\\2\" target=\"_blank\">\\2</a>", $sText );
		$sText = preg_replace( "#(^|[\n ])([a-z0-9&\-_.]+?)@([\w\-]+\.([\w\-\.]+\.)*[\w]+)#i", "\\1<a style=\"color:#135EAE;outline:medium none;\" href=\"mailto:\\2@\\3\">\\2@\\3</a>", $sText );
		$sText = preg_replace( "#(^|[\n ])(\#)([\w\#$%&~/.\-;:=,?@\[\]+]*)#is", "\\1\\2<a href=\"http://search.twitter.com/search?q=%23\\3\" target=\"_blank\">\\3</a>", $sText );
		$sText = preg_replace( "#(^|[\n ])(\@)([\w\#$%&~/.\-;:=,?@\[\]+]*)#is", "\\1\\2<a href=\"http://twitter.com/\\3\" target=\"_blank\">\\3</a>", $sText );
		$sText = substr( $sText, 1 );

		return $sText;
	}

	// Variables
	// Si nos envian una respuesta por post
	if( $_SERVER['REQUEST_METHOD'] == 'POST' )
	{
		$bError = false;
		$sMensaje = '';
		$termsAgree = $rgpd->postFormCheckTermsGeneral();
		
		// Comprobamos nombre
		if( isset( $_POST['nombre'] ) && $_POST['nombre'] == '' )
		{
			$messageStack->add( 'contact_error', ERROR_NOMBRE_RECOMENDAR_EMAIL, 'error', true );
			$bError = true;
		}

		// Comprobamos email
		if( isset( $_POST['para'] ) && $_POST['para'] == '' )
		{
			$bError = true;
			$messageStack->add( 'contact_error', ERROR_EMAIL_RECOMENDAR_EMAIL, 'error', true );
		}
		
		// Comprobamos mensaje
		if( isset( $_POST['mensaje'] ) && $_POST['mensaje'] == '' )
		{
			$bError = true;
			$messageStack->add( 'contact_error', ERROR_MENSAJE_RECOMENDAR_EMAIL, 'error', true );
		}
		
		// Politica de privacidad
		if( $termsAgree == '' )
		{
			$bError = true;
			$messageStack->add('contact_error', ERROR_POLITICA, 'error', true);
		}		

		// Comprobamos los emails si son validos
		if( isset( $_POST['para'] ) && ! $bError )
		{
			$aEmails = explode( ',', $_POST['para'] );
			
			foreach( $aEmails as $aEmail )
			{
				$aEmail = trim( $aEmail );

				if( tep_validate_email( $aEmail ) == false )
				{
					$bError = true;
					$messageStack->add( 'contact_error', ERROR_EMAIL_VALID_RECOMENDAR_EMAIL, 'error', true );
					break 1;
				}
			}
		}

		// Si todo esta correcto
		if( isset( $_POST['nombre'] ) && ! $bError )
		{
			// Convertimos los enlaces en anchor
			$sMensaje = textLinks( str_replace( chr(10), '<br /> ', $_POST['mensaje'] ) );

			// Obtenemos el producto
			$aProduct = tep_db_query( 'select pd.products_name, pd.products_description, p.products_image
									from products p
									inner join products_description pd on (pd.products_id = p.products_id)
									where p.products_id = ' . (int)$_GET['pid'] . ' and pd.language_id = ' . (int)$languages_id );
			$aProduct = tep_db_fetch_array( $aProduct );
			
			$sPlantilla = '<div style="height:27px; background: #626262; width: 100%;"><div style="color:#FFFFFF;font:bold 11px \'Lucida Grande\',Arial,Helvetica,sans-serif;margin-bottom:auto;margin-left:auto;margin-right:auto;padding:7px 0 0;text-align:right;width:650px;">' . date( 'd/m/Y H:i:s' ) . '</div></div>
							<div style="font:12px/18px \'Lucida Grande\',Arial,Helvetica,sans-serif;margin:0;padding:0;margin:auto;width:650px;">
								<div style="margin:15px 0;position:relative;">
								%LOGO%
								</div>
								<div style="color:#191919;">
									<h1 style="font-size:1.3em;font-weight:bold;margin-bottom:20px;">%TITULO%</h1>
							<table style="font-size:1em;">
								<tr>
									<td style="padding-right: 10px;">
										%IMAGEN_PRODUCTO%
									</td>
									<td>
										<b style="line-height:1.8em;margin-bottom:10px;text-align:justify; font-weight:bold;">' . DESCRIPTION_RECOMENDAR_EMAIL . '</b>
										<p style="line-height:1.8em;margin-bottom:10px;text-align:justify;">%DESCRIPCION_PRODUCTO%</p>
										<b style="line-height:1.8em;text-align:justify; font-weight:bold;">' . COMMENT_RECOMENDAR_EMAIL . '</b>
										<span style="line-height:1.8em;text-align:justify; display:block;">%COMENTARIO%</span>
									</td>
								</tr>
							</table>
								</div>
								<div style="background-color:#F9FAE8;border:1px dashed #FCCB57;color:#4F4F4F;font-size:0.9em;font-weight:bold;margin-bottom:10px;margin-top:20px;padding:5px;">%SUGERENCIA%</div>

							</div>';

			$sMensaje =  str_replace( 
				array( '%LOGO%',
					  '%TITULO%',
					  '%IMAGEN_PRODUCTO%', 
					  '%DESCRIPCION_PRODUCTO%',
					  '%COMENTARIO%',
					  '%SUGERENCIA%' ),
				array ( '<img src="'. HTTP_SERVER . DIR_WS_CATALOG . DIR_THEME .'logo-trans.png" style="vertical-align:bottom;  border: none;"/>',
					   HI_RECOMENDAR_EMAIL . ', ' . $_POST['nombre'] . ' ' . HI2_RECOMENDAR_EMAIL,
					   '<img alt="' . $aProduct['products_name'] . '" src="' . HTTP_SERVER . DIR_WS_CATALOG . '/product_thumb.php?img=images/productos/' . $aProduct['products_image'] . '&w=210&h=210"/>',
					   truncate( $aProduct['products_description'], 350 ),
					   $sMensaje,
					   OFERTA_RECOMENDAR_EMAIL . '<a href="' . HTTP_SERVER . DIR_WS_CATALOG . '" style="color:#135EAE; outline:medium none;">' . HTTP_SERVER . '</a>' ), 
			   $sPlantilla );

			// Recorremos los emails para enviarlo
			$aEmails = explode( ',', $_POST['para'] );
			
			foreach( $aEmails as $aEmail )
			{
				$sheader  = 'MIME-Version: 1.0' . "\r\n";
				$sheader .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
				$sheader .= 'To: ' . $_POST['nombre'] . ' <' . $aEmail . '>' . "\r\n";
				$sheader .= 'From: ' . TITLE . ' <' . STORE_OWNER_EMAIL_ADDRESS . '>' . "\r\n";

				$aEmail = trim( $aEmail );

				mail( $aEmail, $_POST['nombre'] . ' ' . RECOM_EMAIL_RECOMENDAR_EMAIL, $sMensaje, $sheader);
			}
			
			$messageStack->addSession( 'contact_correcto', PROCCESS_RECOMENDAR_EMAIL, 'success' );
			tep_redirect( tep_href_link( 'recomendar-email.php?pid=' . $_GET['pid'] ) );
		}
	}
?>

<div class="wind-mdal">
	<div class="titu ax row aflex amiddle">
		<span class="col"><?php echo TITLE_RECOMENDAR_EMAIL; ?></span>
	</div>

	<?php
		echo $messageStack->show( 'contact_error' );
		echo $messageStack->show( 'contact_correcto' );
	?>

	<form onsubmit="return app.get('alert').formAjax(this);" class="row ax xform sp10 amiddle" method="post" action="<?php echo tep_href_link( 'recomendar-email.php?pid=' . $_GET['pid'] ) ?>">
		<label class="col a03 m12 tright mtleft" for="nombre"><?php echo NOMBRE_RECOMENDAR_EMAIL;?></label>
		<div class="col a09 m12">
			<input <?php echo (tep_session_is_registered( 'customer_id' ) ? 'value="' . $customer_first_name . '"' : ''); ?> type="text" id="nombre" tabindex="1" autocomplete="off" name="nombre" value="<?php echo $_POST['nombre']; ?>" />
		</div>

		<label class="col a03 m12 tright mtleft" for="email"><?php echo EMAIL_RECOMENDAR_EMAIL;?></label>
		<div class="col a09 m12">
			<input type="text" id="email" tabindex="3" autocomplete="off" name="para" value="<?php  echo $_POST['para']; ?>" />
		</div>
		
		<label class="col a03 m12 tright mtleft" for="mensaje"><?php echo MENSAJE_RECOMENDAR_EMAIL; ?></label>
		<div class="col a09 m12">
			<textarea id="mensaje" tabindex="4" autocomplete="off" name="mensaje" cols="30" rows="4"><?php echo RECOMENDAR_RECOMENDAR_EMAIL . ' ' . tep_href_link( 'product_info.php', 'products_id=' . $_GET['pid'] ); ?> </textarea>
		</div>

		<div class="col a12 ax row aflex fotr amiddle">
			<div class="col"><?php echo $rgpd->formCheckTermsGeneral(); ?></div>
			<div class="col afluid m12">
				<input type="submit" value="Enviar" class="xbutton verde sbmt" />
			</div>
		</div>
	</form>
</div>