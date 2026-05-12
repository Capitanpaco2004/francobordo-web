<?php
	// Variables
	$sImagen        = $_GET['img'];
	$nWidth         = $_GET['w'];
	$nHeight        = $_GET['h'];
	$gdImage        = null;
	$gdThumb        = null;
	$aImageInfo     = null;
	$sHttpProtocol  = null;
	$sPathThumbnail = null;
	$aInfoFile      = null;
	$sFileNameThumb = null;
	$bCacheControl  = true;
	$sSprite        = 'theme/web/images/custom/sprite.png';
	$bOferta        = (!empty( $_GET['oferta'] ) ? $_GET['oferta'] : 'false');
	$bEnvio         = (!empty( $_GET['envio'] ) ? $_GET['envio'] : 'false');
	$bDelete        = (!empty( $_GET['delete'] ) ? $_GET['delete'] : 'false');
	// Fondo blanco opaco (?bg=white) — útil para emails que no respetan transparencia.
	// Se cachea con sufijo distinto para no afectar al resto del sitio.
	$bBgWhite       = ( isset($_GET['bg']) && $_GET['bg'] === 'white' );
	$aInfoOferta    = array( 'WIDTH' => 47, 'HEIGHT' => 45, 'X_SPRITE' => 135, 'Y_SPRITE' => 676, 'X_THUMB' => 0, 'Y_THUMB' => 0 );
	$aInfoEnvio     = array( 'WIDTH' => 47, 'HEIGHT' => 34, 'X_SPRITE' => 294, 'Y_SPRITE' => 80, 'X_THUMB' => 119, 'Y_THUMB' => 125 );

	// Dado un tamaño de imagen y un tamaño maximo, tanto ancho como alto
	// escala las dimensiones si sobrepasan el maximo permitido
	function scaleSize($nWidth, $nHeight, $nWidthMax, $nHeightMax)
	{
		// Si el alto supera lo permitido reducimos
		if( $nHeight > $nHeightMax )
		{
			$nWidth  = (int)( ( $nHeightMax / $nHeight ) * $nWidth );
			$nHeight = $nHeightMax;
		}

		// Si el ancho supera lo permitido reducimos
		if( $nWidth > $nWidthMax )
		{
			$nHeight = (int)( ( $nWidthMax / $nWidth ) * $nHeight);
			$nWidth  = $nWidthMax;
		}

		return array( 'WIDTH' => $nWidth, 'HEIGHT' => $nHeight );
	}

	// Si no tienes imagen
	if( !preg_match( '/\./i', $sImagen ) )
		$sImagen .= '/no_image.jpg';

	// Comprobamos si existe la imagen pasada por argumento
	if( file_exists( $sImagen ) )
	{
		// Directorio del thumnail
		$sPathThumbnail = str_replace( basename( $sImagen ), '', $sImagen ) . 'thumbnails/';

		// Informacion del archivo, nombre, extension etc.
		$aInfoFile = pathinfo( $sImagen );

		// Nombre del archivo thumb
		$sFileNameThumb = $aInfoFile['filename'] . '_thumb_' . $nWidth . 'x' . $nHeight . ($bOferta == 'true' ? '_o' : '') . ($bEnvio == 'true' ? '_e' : '') . ($bBgWhite ? '_w' : '') . '.png';

		// Si existe la imagen del thumb cargamos desde la cache y no queremos eliminarla
		if( file_exists( $sPathThumbnail . $sFileNameThumb ) && $bDelete == 'false' )
		{
			// Mostramos la imagen ya guardada
			$gdImage = imagecreatefrompng( $sPathThumbnail . $sFileNameThumb );
			imagealphablending( $gdImage, false );
			imagesavealpha( $gdImage, true );
		}
		// Si no existe la imagen la creamos
		else
		{
			// Si la imagen existe y deseamos eliminarla
			if( file_exists( $sPathThumbnail . $sFileNameThumb ) && $bDelete == 'true' )
				unlink( $sPathThumbnail . $sFileNameThumb );

			// Creamos un directorio thumbnail si no existe
			if( !is_dir( $sPathThumbnail ) )
			{
				mkdir( $sPathThumbnail );
				chmod( $sPathThumbnail, 0777 );
			}

			// Informacion de la imagen
			$aImageInfo = @getimagesize( $sImagen );

			// Segun el mime realizamos una instancia diferente
			switch( $aImageInfo['mime'] )
			{
				case 'image/jpeg':
					$gdImage = imagecreatefromjpeg( $sImagen );
				break;

				case 'image/gif':
					$gdImage = imagecreatefromgif( $sImagen );
				break;

				case 'image/png':
					$gdImage = imagecreatefrompng( $sImagen );
				break;
			}

			imagealphablending($gdImage, true);

			// Obtenemos el ancho y el alto de la imagen final dentro del thumb
			$aScale = scaleSize( $aImageInfo[0], $aImageInfo[1], $nWidth, $nHeight );

			// Creamos una imagen con un ancho y un alto
			$gdThumb = imagecreatetruecolor( $nWidth, $nHeight );
			imagesavealpha($gdThumb, true);

			// Fondo: blanco opaco si bg=white, transparente en cualquier otro caso (default original)
			if( $bBgWhite ) {
				imagefill( $gdThumb, 0, 0, imagecolorallocate( $gdThumb, 255, 255, 255 ) );
			} else {
				imagefill( $gdThumb, 0, 0, imagecolorallocatealpha( $gdThumb, 0, 0, 0, 127 ) );
			}

			// Creamos el thumb centrado
			imagecopyresampled( $gdThumb, $gdImage, ($nWidth / 2) - ($aScale['WIDTH'] / 2), ($nHeight / 2) - ($aScale['HEIGHT'] / 2), 0, 0, $aScale['WIDTH'], $aScale['HEIGHT'], $aImageInfo[0], $aImageInfo[1] );

			// Si la imagen esta en oferta o envio
			if( $bOferta == 'true' || $bEnvio == 'true' )
			{
				// Imagen sprite completo
				$gdSprite = imagecreatefrompng($sSprite);
				imagealphablending( $gdSprite, true );
			}

			// Comprobamos si la imagen es en oferta
			if( $bOferta == 'true' )
			{
				// Imagen sprite de oferta
				$gdOferta = imagecreatetruecolor( $aInfoOferta['WIDTH'], $aInfoOferta['HEIGHT'] );
				imagealphablending( $gdOferta, false );
				imagesavealpha( $gdOferta, true );

				// Realizamos un crop a la imagen sprite para obtener la imagen de oferta
				imagecopyresampled( $gdOferta, $gdSprite, 0, 0, $aInfoOferta['X_SPRITE'], $aInfoOferta['Y_SPRITE'], imagesx( $gdSprite ), imagesy( $gdSprite ), imagesx( $gdSprite ), imagesy( $gdSprite ) );

				// Creamos la imagen de oferta encima
				imagecopyresampled( $gdThumb, $gdOferta, $aInfoOferta['X_THUMB'], $aInfoOferta['Y_THUMB'], 0, 0, $aInfoOferta['WIDTH'], $aInfoOferta['HEIGHT'], $aInfoOferta['WIDTH'], $aInfoOferta['HEIGHT'] );
			}

			// Comprobamos si la imagen es envio
			if( $bEnvio == 'true' )
			{
				// Imagen sprite de envio
				$gdEnvio = imagecreatetruecolor( $aInfoOferta['WIDTH'], $aInfoOferta['HEIGHT'] );
				imagealphablending( $gdEnvio, false );
				imagesavealpha( $gdEnvio, true );

				// Realizamos un crop a la imagen sprite para obtener la imagen de envio
				imagecopyresampled( $gdEnvio, $gdSprite, 0, 0, $aInfoEnvio['X_SPRITE'], $aInfoEnvio['Y_SPRITE'], imagesx( $gdSprite ), imagesy( $gdSprite ), imagesx( $gdSprite ), imagesy( $gdSprite ) );

				// Creamos la imagen de envio encima
				imagecopyresampled( $gdThumb, $gdEnvio, $aInfoEnvio['X_THUMB'], $aInfoEnvio['Y_THUMB'], 0, 0, $aInfoEnvio['WIDTH'], $aInfoEnvio['HEIGHT'], $aInfoEnvio['WIDTH'], $aInfoEnvio['HEIGHT'] );
			}

			// Almacenamos la salida
			ob_start();

			// Salida de la imagen
			imagepng( $gdThumb );

			// Escribimos el buffer de salida a un archivo en modo binario
			file_put_contents( $sPathThumbnail . $sFileNameThumb, ob_get_contents());

			// Permisos
			chmod( $sPathThumbnail . $sFileNameThumb, 0777 );

			// Asignamos el thumb a la imagen para usarlo mostrarlo finalmente
			$gdImage = $gdThumb;

			// Liberamos
			if(isset($gdThumb))
			imagedestroy( $gdThumb );
		}

		# Inicio cabeceras oscommerce

		// Obtenemos el protocolo web
		$sHttpProtocol = isset($_SERVER['SERVER_PROTOCOL']) && $_SERVER['SERVER_PROTOCOL'] == 'HTTP/1.1' ? 'HTTP/1.1' : 'HTTP/1.0';

		// Construimos la ruta de la imagen
		$sImagePath = $sPathThumbnail . $sFileNameThumb;

		// Comprobamos si la imagen existe
		if (!file_exists($sImagePath)) {
			header($sHttpProtocol . ' 404 Not Found');
			exit();
		}

		// Obtenemos la fecha de la última modificación de la imagen
		$lastModified = filemtime($sImagePath);
		$lastModifiedGMT = $lastModified - date('Z');
		$lastModifiedHttpFormat = gmdate('D, d M Y H:i:s \G\M\T', $lastModified);
		
		// Construimos el ETag de la imagen
		$eTag = '"1fa44b7-' . dechex(filesize($sImagePath)) . '-' . dechex($lastModifiedGMT) . '"';

		// Comprobamos si la imagen ha sido modificada
		$is304 = false;
		if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && $_SERVER['HTTP_IF_MODIFIED_SINCE'] === $lastModifiedHttpFormat) {
			$is304 = true;
		} elseif (isset($_SERVER['HTTP_IF_NONE_MATCH']) && stripslashes($_SERVER['HTTP_IF_NONE_MATCH']) === $eTag) {
			$is304 = true;
		}

		if ($is304) {
			header('ETag: ' . $eTag);
			header($sHttpProtocol . ' 304 Not Modified');
			exit();
		}

		// Cargamos la imagen
		$gdImage = imagecreatefrompng($sImagePath);

		// Comprobamos si la imagen se ha creado correctamente
		if (!$gdImage) {
			header($sHttpProtocol . ' 500 Internal Server Error');
			exit();
		}

		// Cabecera imagen png
		header('Content-type: image/png');

		// Cache control
		$bCacheControl = isset($_SERVER['HTTP_CACHE_CONTROL']) && strtolower($_SERVER['HTTP_CACHE_CONTROL']) != 'no-cache';

		if ($bCacheControl) {
			header('ETag: ' . $eTag);
			header('Last-Modified: ' . $lastModifiedHttpFormat);
			header('Cache-Control: private');
		} else {
			header('Cache-Control: no-cache');
		}

		// Mostramos la imagen
		imagepng($gdImage);

		// Liberamos la memoria
		// imagedestroy ya no es necesario en PHP 8+
		unset($gdImage);
	}
	else
	{
		// Respuesta 404
		include 'includes/modules/404/index.php';
	}
