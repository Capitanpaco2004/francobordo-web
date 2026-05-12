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
	$bOferta        = (empty( $_GET['oferta'] ) ? 'false' : $_GET['oferta']);
	$bEnvio         = (empty( $_GET['envio'] ) ? 'false' : $_GET['envio']);
	$bDelete        = (empty( $_GET['delete'] ) ? 'false' : $_GET['delete']);
	$aInfoOferta    = [ 'WIDTH' => 47, 'HEIGHT' => 45, 'X_SPRITE' => 135, 'Y_SPRITE' => 676, 'X_THUMB' => 0, 'Y_THUMB' => 0 ];
	$aInfoEnvio     = [ 'WIDTH' => 47, 'HEIGHT' => 34, 'X_SPRITE' => 294, 'Y_SPRITE' => 80, 'X_THUMB' => 119, 'Y_THUMB' => 125 ];

	// Dado un tama�o de imagen y un tama�o maximo, tanto ancho como alto
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

		return [ 'WIDTH' => $nWidth, 'HEIGHT' => $nHeight ];
	}

	// Si no tienes imagen
	if (!preg_match( '/\./i', (string) $sImagen )) {
        $sImagen .= '/no_image.jpg';
    }

	// Comprobamos si existe la imagen pasada por argumento
	if( file_exists( $sImagen ) )
	{
		// Directorio del thumnail
		$sPathThumbnail = str_replace( basename( (string) $sImagen ), '', $sImagen ) . 'thumbnails/';

		// Informacion del archivo, nombre, extension etc.
		$aInfoFile = pathinfo( (string) $sImagen );

		// Nombre del archivo thumb
		$sFileNameThumb = $aInfoFile['filename'] . '_thumb_' . $nWidth . 'x' . $nHeight . ($bOferta == 'true' ? '_o' : '') . ($bEnvio == 'true' ? '_e' : '') . '.png';

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
			if (file_exists( $sPathThumbnail . $sFileNameThumb ) && $bDelete == 'true') {
                unlink( $sPathThumbnail . $sFileNameThumb );
            }

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

			// La imagen la ponemos con fondo transparente
			imagefill( $gdThumb, 0, 0, imagecolorallocatealpha( $gdThumb, 0, 0, 0, 127 ) );

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
			imagedestroy( $gdThumb );
		}

		# Inicio cabeceras oscommerce

		// Obtenemos el protocolo web
		if (isset( $_SERVER['SERVER_PROTOCOL'] ) && $_SERVER['SERVER_PROTOCOL'] == 'HTTP/1.1') {
            $sHttpProtocol = 'HTTP/1.1';
        } else {
            $sHttpProtocol = 'HTTP/1.0';
        }

		// Cabecera imagen png
		header( 'Content-type: image/png' );

		// Cache control
		if (isset( $_SERVER["HTTP_CACHE_CONTROL"] )) {
            $bCacheControl = strtolower( (string) $_SERVER["HTTP_CACHE_CONTROL"]) !== "no-cache";
        }

		// Construimos la cabecera tag "inode-lastmodtime-filesize"
		$lastModified = filemtime( $sPathThumbnail . $sFileNameThumb );
		$lastModifiedGMT = $lastModified - date('Z');
		$lastModifiedHttpFormat = gmstrftime("%a, %d %b %Y %T %Z", $lastModified);
		// Don't use inode in eTag when you have multiple webservers, instead I use a dummy value (1fa44b7), oscommerce
		$eTag = '"1fa44b7-' . dechex( filesize( $sPathThumbnail . $sFileNameThumb ) ) . "-" . dechex( $lastModifiedGMT ) . '"';

		// Cache control, oscommerce
		if( $bCacheControl )
		{
			$lastModifiedFromHttp = "xxx";
			if (isset ($_SERVER["HTTP_IF_MODIFIED_SINCE"] )) {
                $lastModifiedFromHttp = ($_SERVER["HTTP_IF_MODIFIED_SINCE"] === "") ? "xxx" : $_SERVER["HTTP_IF_MODIFIED_SINCE"];
            }

			// Read sent eTag by browser
			$foundETag = "";
			if (isset ($_SERVER["HTTP_IF_NONE_MATCH"] )) {
                $foundETag = stripslashes( (string) $_SERVER["HTTP_IF_NONE_MATCH"] );
            }

			// Last Modification Time
			if ($lastModifiedFromHttp == $lastModifiedHttpFormat) {
                $sameLastModified = true;
            } elseif (str_contains((string) $lastModifiedFromHttp,$lastModifiedHttpFormat)) {
                $sameLastModified = true;
            } else {
                $sameLastModified = false;
            }

			// same eTag and Last Modification Time (e.g. with Firefox)
			if ($eTag === $foundETag && $sameLastModified) {
                $is304 = true;
            } else {
                // no eTag supplied, but Last Modification Time is unchanged (e.g. with IE 6.0)
                $is304 = (($foundETag === "") && $sameLastModified);
            }

			if ($is304)
			{
				// They already have an up to date copy so tell them
				// 946080000 = Dec 24, 1999 4PM
				// only send if valid eTag
				if ($lastModifiedGMT > 946080000) {
                    header( "ETag: " . $eTag );
                }

				header( "Status: 304 Not Modified" );
				header( $sHttpProtocol . " 304 Not Modified" );
				header( "Connection: close" );
				exit();
			}
		}

		// We have to send them the whole page
		header('Pragma: ');
		header('Expires: ');
		if( $bCacheControl )
		{
			// 946080000 = Dec 24, 1999 4PM
			if ($lastModifiedGMT > 946080000) {
                header('ETag: ' . $eTag);
            }

			header('Last-Modified: ' . $lastModifiedHttpFormat);
			header('Cache-Control: private');
		}
		else {
            header('Cache-Control: no-cache');
        }

		# Fin cabeceras oscommerce

		// Mostramos imagen
		imagepng( $gdImage );

		// Liberamos
		if (isset($gdThumb)) {
            imagedestroy( $gdImage );
        }
	}
	else
	{
		// Respuesta 404
		header( 'TEST404: TEST404' );
		header( 'Status: 404 Not Found' );
		header( $sHttpProtocol . ' 404 Not Found' );
		exit();
	}
?>
