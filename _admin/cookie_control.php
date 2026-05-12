<?php
	require('includes/application_top.php');
	include( THEME . 'html/header.php' ); 
	
	// Si nos envian el formulario guardamos
	if( $_SERVER['REQUEST_METHOD'] === 'POST' )
	{
		// Si no esta consentimiento automatico es que esta en off
		if( !array_key_exists( 'cookie_control_automatico', $_POST ) )
			$_POST['cookie_control_automatico'] = 'off';

		// Si no esta control de borrado es que esta en off
		if( !array_key_exists( 'cookie_control_borrado', $_POST ) )
			$_POST['cookie_control_borrado'] = 'off';
			
		// Recorremos lo que no envian por post para realizar las actualizaciones
		foreach( $_POST as $key => $value )
		{
			// Si es cookie_control
			if( preg_match( '/cookie_control_/i', $key ) )
			{
				$aSql = array( 'configuration_value' => $value );
				tep_db_perform( 'configuration', $aSql, 'update', 'configuration_key = "' . $key . '"' );
			}
		}

		// Si nos envian una imagen
		$objImagen = new upload( 'cookie_control_imagen' );
		$objImagen->set_destination( getcwd() . '/../images/upload/' );
		
		if( $objImagen->parse() && $objImagen->save() )
		{
			rename( getcwd() . '/../images/upload/' . $objImagen->filename, getcwd() . '/../images/upload/cookie_control_imagen.png' );
			$value = 'images/upload/cookie_control_imagen.png';
			$key = 'cookie_control_imagen';
			$aSql = array( 'configuration_value' => $value );
			tep_db_perform( 'configuration', $aSql, 'update', 'configuration_key = "' . $key . '"' );
		}
		
		// Recargamos la cache
		require ('includes/configuration_cache.php');
		
		$messageStack->add( 'Los datos se guardaron correctamente', 'success' );
	}

	// Obtenemos todas las paginas de información
	$aDatos = tep_db_query( 'select information_id as id, information_title as text from information where language_id = 3 order by information_title asc' );
	$aInformacion = array();

	while( $aDato = tep_db_fetch_array( $aDatos ) )
		$aInformacion[] = $aDato;
		
	// Obtenemos los datos
	$aDatos = tep_db_query( 'select configuration_key, configuration_value from configuration where configuration_group_id = 6503' );
	$aValues = array();

	while( $aDato = tep_db_fetch_array( $aDatos ) )
		$aValues[$aDato['configuration_key']] = $aDato['configuration_value'];
		
	// Tipos de vista
	$aVistas = array(
		array( 'id' => 'top', 'text' => 'Arriba' ),
		array( 'id' => 'botom', 'text' => 'Abajo' ),
		array( 'id' => 'izqd', 'text' => 'Inferior izquierda' ),
		array( 'id' => 'drch', 'text' => 'Inferior derecha' )
	);
?>


<table border="0" width="100%" cellspacing="2" cellpadding="2">
	<tr>
		<td width="100%" valign="top">
			<h1 class="pageHeading">Cookie control</h1>
			
			<?php
				$messageStack->output();
			?>

			<form action="cookie_control.php" method="post" enctype="multipart/form-data">
				<div class="fluid grid">
					<div class="box-tbl grid12">
						<div class="box-head">
							<h6>Opciones</h6>
							<div class="clear"></div>
						</div>
						<div class="formRow">
							<div class="grid2">
								<label>Texto para el título:</label>
							</div>
							<div class="grid10">
								<?php echo tep_draw_input_field( 'cookie_control_texto_titulo', $aValues['cookie_control_texto_titulo'] ); ?>
								<span class="note" style="font-style: italic; white-space: normal;">Texto que aparece en el título de aceptación de cookies</span>
							</div>
							<div class="clear"></div>
						</div>
						<div class="formRow">
							<div class="grid2">
								<label>Texto para el mensaje:</label>
							</div>
							<div class="grid10">
								<?php echo tep_draw_textarea_field( 'cookie_control_texto_mensaje', '', '', '', $aValues['cookie_control_texto_mensaje'], 'style="margin: 0px;"' ); ?>
								<span class="note" style="font-style: italic; white-space: normal;">Texto que aparece en el box de aviso de cookie</span>
							</div>
							<div class="clear"></div>
						</div>
						<div class="formRow">
							<div class="grid2">
								<label>Texto para el boton:</label>
							</div>
							<div class="grid10">
								<?php echo tep_draw_input_field( 'cookie_control_texto_boton', $aValues['cookie_control_texto_boton'] ); ?>
								<span class="note" style="font-style: italic; white-space: normal;">Texto que aparece en el botón de aceptación de cookies</span>
							</div>
							<div class="clear"></div>
						</div>
						<div class="formRow">
							<div class="grid2">
								<label>Url para la política:</label>
							</div>
							<div class="grid10">
								<?php echo tep_draw_pull_down_menu( 'cookie_control_url', $aInformacion, $aValues['cookie_control_url']); ?>
								<span class="note" style="font-style: italic; white-space: normal;">Página de información de las políticas de cookies</span>
							</div>
							<div class="clear"></div>
						</div>
						<div class="formRow">
							<div class="grid2">
								<label>¿Consentimiento automático?:</label>
							</div>
							<div class="grid10 check">
								<?php echo tep_draw_checkbox_field( 'cookie_control_automatico', '', ($aValues['cookie_control_automatico'] == 'on' ? true : false) ); ?>
								<span class="note" style="display: block; font-style: italic; white-space: normal; float: left; width: 100%;">El consentimiento automático es una aceptación automática de la política de cookies. Esto se aplicara pasado X minutos desde que el usuario entra en la web. Esto ayuda tenerlo activado a no perder nuestros datos de análisis web mediante Google analytics</span>
							</div>
							<div class="clear"></div>
						</div>
						<div class="formRow">
							<div class="grid2">
								<label>Minutos para el consentimiento automático:</label>
							</div>
							<div class="grid10">
								<?php echo tep_draw_input_field( 'cookie_control_automatico_minuto', $aValues['cookie_control_automatico_minuto'] ); ?>
								<span class="note" style="font-style: italic; white-space: normal;">Total de tiempo automático en minutos para la aceptación de la política de cookies</span>
							</div>
							<div class="clear"></div>
						</div>
						<div class="formRow">
							<div class="grid2">
								<label>¿Borrado inicial?:</label>
							</div>
							<div class="grid10 check">
								<?php echo tep_draw_checkbox_field( 'cookie_control_borrado', '', ($aValues['cookie_control_borrado'] == 'on' ? true : false) ); ?>
								<span class="note" style="display: block; font-style: italic; white-space: normal; float: left; width: 100%;">El borrado inicial ayuda a reiniciar el sistema de aceptación de cookies, eliminando todas las cookies de su sistema y volviendo a empezar con el consentimiento del cliente de que desea las cookies.</span>
							</div>
							<div class="clear"></div>
						</div>
						<div class="formRow">
							<div class="grid2">
								<label>Nombre para la cookie de control:</label>
							</div>
							<div class="grid10">
								<?php echo tep_draw_input_field( 'cookie_control_nombre', $aValues['cookie_control_nombre'] ); ?>
								<span class="note" style="font-style: italic; white-space: normal;">Nombre que se le asignara a la cookie de control, recuerda añadirla a la política de cookies</span>
							</div>
							<div class="clear"></div>
						</div>
						<div class="formRow">
							<div class="grid2">
								<label>Vista:</label>
							</div>
							<div class="grid10">
								<?php echo tep_draw_pull_down_menu( 'cookie_control_view', $aVistas, $aValues['cookie_control_view']); ?>
								<span class="note" style="font-style: italic; white-space: normal;">Forma en la que se vera el mensaje de las políticas de cookie</span>
							</div>
							<div class="clear"></div>
						</div>
					</div>
				</div>
				
				<div class="fluid grid">
					<div class="box-tbl grid12">
						<div class="box-head">
							<h6>Personalización de colores en hexadecimal</h6>
							<div class="clear"></div>
						</div>
						<div class="formRow">
							<div class="grid2">
								<label>Color de fondo del box:</label>
							</div>
							<div class="grid4">
								<?php echo tep_draw_input_field( 'cookie_control_color_fondo_box', $aValues['cookie_control_color_fondo_box'] ); ?>
							</div>
							<div class="grid2">
								<label>Color del borde del box:</label>
							</div>
							<div class="grid4">
								<?php echo tep_draw_input_field( 'cookie_control_color_borde_box', $aValues['cookie_control_color_borde_box'] ); ?>
							</div>
							<div class="clear"></div>
						</div>
						<div class="formRow">
							<div class="grid2">
								<label>Imagen icono:</label>
							</div>
							<div class="grid10">
								<input type="file" name="cookie_control_imagen" />
							</div>
							<div class="clear"></div>
						</div>
						<div class="formRow">
							<div class="grid2">
								<label>Color de cierre:</label>
							</div>
							<div class="grid4">
								<?php echo tep_draw_input_field( 'cookie_control_color_cierre', $aValues['cookie_control_color_cierre'] ); ?>
							</div>
							<div class="grid2">
								<label>Color del enlace:</label>
							</div>
							<div class="grid4">
								<?php echo tep_draw_input_field( 'cookie_control_color_enlace', $aValues['cookie_control_color_enlace'] ); ?>
							</div>
							<div class="clear"></div>
						</div>
						<div class="formRow">
							<div class="grid2">
								<label>Color del título:</label>
							</div>
							<div class="grid4">
								<?php echo tep_draw_input_field( 'cookie_control_color_titulo', $aValues['cookie_control_color_titulo'] ); ?>
							</div>
							<div class="grid2">
								<label>Color del texto:</label>
							</div>
							<div class="grid4">
								<?php echo tep_draw_input_field( 'cookie_control_color_texto', $aValues['cookie_control_color_texto'] ); ?>
							</div>
							<div class="clear"></div>
						</div>
						<div class="formRow">
							<div class="grid2">
								<label>Color de fondo del boton:</label>
							</div>
							<div class="grid4">
								<?php echo tep_draw_input_field( 'cookie_control_color_fondo_boton', $aValues['cookie_control_color_fondo_boton'] ); ?>
							</div>
							<div class="grid2">
								<label>Color del borde del boton:</label>
							</div>
							<div class="grid4">
								<?php echo tep_draw_input_field( 'cookie_control_color_borde_boton', $aValues['cookie_control_color_borde_boton'] ); ?>
							</div>
							<div class="clear"></div>
						</div>
					</div>
					<div style="margin: 10px 0px 0px 0px" class="grid12">
						<input type="submit" value="Actualizar datos" class="buttonS bGreen" style="float: right;" />
					</div>
				</div>
			</form>
		</td>
	</tr>
</table>
<?php
	require(THEME . 'html/footer.php'); 
	require(DIR_WS_INCLUDES . 'application_bottom.php');
?>