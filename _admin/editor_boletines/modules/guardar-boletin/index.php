<?php
	// Variables
	$sAction = tep_db_prepare_input( $_GET['a'] );

	switch( $sAction )
	{
		case 'exportar':
			$sHtml = preg_replace("/[\r\n\t]+/", "", file_get_contents( DIR_EDITOR_BOLETINES_HTML . '/' . $sNombreBoletin . '/boletin.html' ) );
			$sHtml = str_replace( '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">', '', $sHtml );
		break;
	
		case 'check_exists':
			// Variables
			$sNombre = tep_db_prepare_input( $_POST['nombre'] );
			
			// Comprobamos si existe
			if( file_exists( DIR_EDITOR_BOLETINES_HTML . $sNombre ) and is_dir( DIR_EDITOR_BOLETINES_HTML . $sNombre ) )
				echo 'true';
			else
				echo 'false';

			exit();
		break;
	
		case 'guardar':
			// Variables
			$sHtml = str_replace( array( '\"' ), array( '"', '' ), $_POST['html'] );
			$sNombre = tep_db_prepare_input( $_POST['nombre'] );
			
			// Guardamos el nombre
			$sNombreBoletin = $sNombre;
			
			// Creamos en la parte de arriba texto extra
			$sHtmlExtraTop = '<table width="100%" align="center"  border="0" cellpadding="0" cellspacing="0">
				<tr><td height="10"> </td><tr>
				<tr>
					<td> </td>			
					<td width="728">
						<table width="100%" align="center"  border="0" cellpadding="0" cellspacing="0">
							<tr>
								<td>
									<font face="verdana" style="font-family: verdana,helvetica,arial,sans-serif;" size="2" color="#616161">¿No puede ver correctamente este correo electrónico? <a href="[web_version]" style="color: #64a8ee; text-decoration: underline;" title="Ir a la versión web">Ir a la versión web</a></font>
								</td>
								<td align="right">
									<font face="verdana" style="font-family: verdana,helvetica,arial,sans-serif;" size="2" color="#616161">Boletín ' . date('d/m/Y') . '</font>
								</td>
							</tr>
						</table>
					<td> </td>
				</tr>
				<tr><td height="10"> </td><tr>
			</table>';
			
			// Creamos en la parte de abajo de texto extra
			$sHtmlExtraFooter = '<table width="100%" align="center"  border="0" cellpadding="0" cellspacing="0">
				<tr><td height="10"> </td><tr>
				<tr>
					<td> </td>			
					<td width="728">
						<table width="100%" align="center"  border="0" cellpadding="0" cellspacing="0">
							<tr>
								<td align="center">
									<font face="verdana" style="font-family: verdana,helvetica,arial,sans-serif;" size="2" color="#616161">Si deseas no recibir mas boletines siga el <a href="[unsubscribe_url_direct]" style="color: #64a8ee; text-decoration: underline;" title="Ir a la versión web">ENLACE</a></font>
								</td>
							</tr>
							<tr><td height="10"> </td></tr>
							<tr>
								<td align="center">
									<font face="verdana" style="font-family: verdana,helvetica,arial,sans-serif;" size="1" color="#616161">Antes de imprimir este mensaje, por favor, compruebe que es necesario. Una tonelada de papel implica la tala de 15 árboles y el consumo de 250.000 litros de agua. El Medio Ambiente es cuestión de TODOS.</font>
								</td>
							</tr>
							<tr><td height="10"> </td></tr>
						</table>
					<td> </td>
				</tr>
				<tr><td height="10"> </td><tr>
			</table>';
			
			// Comprobamos si existe el email, si es asi lo eliminamos
			if( $sNombre != '' && file_exists( DIR_EDITOR_BOLETINES_HTML . $sNombre ) and is_dir( DIR_EDITOR_BOLETINES_HTML . $sNombre ) )
				recursiveDelete( DIR_EDITOR_BOLETINES_HTML . $sNombre . '/' );

			// Creamos el directorio del boletin
			mkdir( DIR_EDITOR_BOLETINES_HTML . $sNombre . '/' );

			// Variables
			$dcDocument = new DOMDocument();
			$aRowAttrData = array( 'td', 'img', 'tr', 'a', 'tbody', 'font', 'p' );
			
			$sHtml = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd"><html lang="es"><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /><meta name="language" content="es" /><title>Boletín ' . $sNombre . '</title><meta http-equiv="Expires" content="0"><meta http-equiv="Last-Modified" content="0"><meta http-equiv="Cache-Control" content="no-cache, mustrevalidate"><meta http-equiv="Pragma" content="no-cache"></head><body style="margin: 0px; padding: 0px;">' . $sHtml . '</body></html>';
			
			// Copiamos todas las imagenes del theme boletin al directorio boletin
			$aFiles = scandir( DIR_EDITOR_BOLETINES_THEME . 'email/' . $sThemeBoletin . '/images/' );

			// Recorremos las imagenes
			foreach( $aFiles as $sFile )
			{
				// Si no es una imagen continuamos
				if( in_array( $sFile, array( '.', '..' ) ) || is_dir( DIR_EDITOR_BOLETINES_THEME . 'email/' . $sThemeBoletin . '/images/' . $sFile ) )
					continue;

				// Copiamos
				copy( DIR_EDITOR_BOLETINES_THEME . 'email/' . $sThemeBoletin . '/images/' . $sFile, DIR_EDITOR_BOLETINES_HTML . '/' . $sNombre . '/' . $sFile );
			}	

			// Cargamos el HTML
			@$dcDocument->loadHTML( $sHtml );
			
			// Guardamos los banners
			// Obtenemos todas las imagenes
			$dmsImages = $dcDocument->getElementsByTagName( "img" );
			
			// Recorremos las imagenes en busca de banners para guardarlos
			foreach( $dmsImages as $dmImagen )
			{
				// Si es un banner lo guardamos
				if( $dmImagen->getAttribute("data-theme-banner") == 'true' )
				{
					// Obtenemos el nombre de la imagen
					$sImagen = str_replace( HTTP_BANNER, '', $dmImagen->getAttribute("src") );

					// Copiamos
					copy( DIR_BANNER . $sImagen, DIR_EDITOR_BOLETINES_HTML . '/' . $sNombre . '/' . $sImagen );
					
					// Modificamos el html
					$dmImagen->setAttribute( 'src', preg_replace( '/(http\:)/i', 'https:', HTTPS_SERVER ) . '/boletines/html/' . $sNombre . '/' . $sImagen );
				}
			}
			
			// Contador de imagenes
			$nCont = 0;

			// Recorremos las imagenes para buscar imagenes en 64 para reconstruirlas
			foreach( $dmsImages as $dmImagen )
			{		
				// Si es una imagen de base 64 la reconstruimos
				if( $dmImagen->getAttribute("data-theme-64") == 'true' )
				{
					// Escribimos
					$dmImagen->setAttribute( 'src', $_POST['imagen_64_' . $nCont] );

					// Aumentamos archivos
					$nCont++;
				}
			}
			
			// Obtenemos el html
			$sHtml = $dcDocument->saveHTML();

			// Modificamos las url de las imagenes del theme boletin al boletin nuevo
			$sHtml = str_replace( preg_replace( '/(http\:)/i', 'https:', HTTPS_SERVER ) . '/boletines/themes/email/' . $sThemeBoletin . '/images/', preg_replace( '/(http\:)/i', 'https:', HTTPS_SERVER ) . '/boletines/html/' . $sNombre . '/', $sHtml );
		
			// Obtenemos la configuracion para guardarla
			$aConfig = array(
				'theme' => $sThemeBoletin,
				'grupo_cliente' => $nCustomerGroupId,
				'nombre_boletin' => $sNombreBoletin,
				'padding_products' => $aThemePaddingProducts
			);

			// Guardamos la configuracion
			$flFile = fopen( DIR_EDITOR_BOLETINES_HTML . $sNombre . '/config.cgf', 'w' );
			fwrite( $flFile, json_encode( $aConfig ) );
			fclose( $flFile );

			// Guardamos el html en el boletin editor
			$flFile = fopen( DIR_EDITOR_BOLETINES_HTML . $sNombre . '/boletin_editor.html', 'w' );
			fwrite( $flFile, $sHtml );
			fclose( $flFile );

			// Volvemos a recrear el dom html
			$dcDocument = new DOMDocument();
			@$dcDocument->loadHTML( $sHtml );

			// Obtenemos todas las imagenes
			$dmsImages = $dcDocument->getElementsByTagName( "img" );

			// Contador de imagenes
			$nCont = 0;

			// Recorremos las imagenes para crearlas
			foreach( $dmsImages as $dmImagen )
			{
				// Si es una imagen de base 64 la creamos
				if( $dmImagen->getAttribute("data-theme-64") == 'true' )
				{
					// Imagen quitandole del src el tema del base64 etc
					$sImagen = preg_replace( '/^.+\,/i', '', $_POST['imagen_64_' . $nCont] );

					// Si la imagen llega vacio intentamos eliminar solo el data image
					if( $sImagen == '' )
						$sImagen = str_replace( 'data:image/png;base64,', '', $_POST['imagen_64_' . $nCont] );
				
					// Creamos el archivo
					file_put_contents( DIR_EDITOR_BOLETINES_HTML . '/' . $sNombre . '/imagen_' . $nCont . '.png', base64_decode( $sImagen ) );

					// Escribimos
					$dmImagen->setAttribute( 'src', preg_replace( '/(http\:)/i', 'https:', HTTP_SERVER ) . '/boletines/html/' . $sNombre . '/imagen_' . $nCont . '.png' );
					
					// Aumentamos archivos
					$nCont++;
				}
			}

			// Recorremos los posibles campos para ser limpiados de los "data"
			foreach( $aRowAttrData as $sElement )
			{
				// Obtenemos los elementos
				$dmsAuxs = $dcDocument->getElementsByTagName( $sElement );
				
				// Recorremos los elementos
				foreach( $dmsAuxs as $dmAux )
				{
					// Recorremos atributos
					for( $nIndex = $dmAux->attributes->length - 1; $nIndex >= 0; --$nIndex )
					{
						// Si el atributo es data-theme
						if( preg_match( '/^data-theme/i', $dmAux->attributes->item( $nIndex )->nodeName ) )
							$dmAux->removeAttributeNode( $dmAux->attributes->item( $nIndex ) );
					}
				}
			}

			// Guardamos el html
			$sHtml = $dcDocument->saveHTML();
			$flFile = fopen( DIR_EDITOR_BOLETINES_HTML . $sNombre . '/boletin.html', 'w' );
			fwrite( $flFile, str_replace( array(
					'<body style="margin: 0px; padding: 0px;">',
					'</body>'
				),
				array( 
					'<body style="margin: 0px; padding: 0px;">' . $sHtmlExtraTop,
					$sHtmlExtraFooter . '</body>'
				),
				$sHtml 
			) );
			fclose( $flFile );
			
			exit();
		break;
	};
?>

<?php if( $sAction == 'exportar' ): ?>
	<div id="lgbox-izqd">
		<textarea style="width: 100%; height: 269px;"><?php echo $sHtml; ?></textarea>
		<a target="_blank" href="<?php echo preg_replace( '/(http\:)/i', 'https:', HTTP_SERVER ) . '/boletines/html/' . $sNombreBoletin; ?>/boletin.html" style="color: #64a8ee; text-decoration: underline;" title="Ir a la versión web">Ver boletiín</a>
	</div>
	<div id="lgbox-drch">
		<div class="box-info">
			<div class="icon"></div>
			Este es el HTML resultante de su boletín, copia y pege este HTML en las herramientas que le hagan falta. Recuerde que si cambias este HTML resultante el boletin generado del servidor no se cambiara.
		</div>
	</div>
<?php else: ?>
	<div id="lgbox-izqd">
		<form id="form-guardar" method="post" action="editor_boletines.php?m=<?php echo $sModule; ?>&a=guardar" style="height: 100%; position: relative;">
			<input class="focus" type="text" id="nombre" name="nombre" value="<?php echo $sNombreBoletin; ?>" placeholder="Escribe el nombre del boletín"/>
			<div id="guardar" style="position: absolute; bottom: 0px; left: 0px;" class="bton bton-vrde">Guardar</div>
			<a id="exportar" style="position: absolute; bottom: 0px; left: 105px;" href="javascript:void(0);" class="bton">Exportar</a>
		</form>
	</div>
	<div id="lgbox-drch">
		<div class="box-info">
			<div class="icon"></div>
			Para poder exportar un boletín a HTML este deberá ser guardado antes. Recuerda que el nombre del boletín deberá ser en minúsculas y este podrá tener caracteres, números y guiones, no pudiendo tener caracteres extraños ni espacios (el sistema revisara e intentara remplazar el nombre automáticamente si detecta algún problema)
		</div>
	</div>
<?php endif; ?>

<script type="text/javascript" src="<?php echo DIR_EDITOR_BOLETINES . 'modules/' . $sModule; ?>/js/functions.js"></script>