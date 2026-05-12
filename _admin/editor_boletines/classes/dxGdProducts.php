<?php
	class dxGdProducts
	{
		public function __construct($aArgumentos = array())
		{
			// Variables
			$this->sTheme = getcwd() . '/' . $aArgumentos['theme'];
			$this->sTitulo = $aArgumentos['titulo'];
			$this->sImagen = $aArgumentos['imagen'];
			$this->sDirectorioImagen = $aArgumentos['directorio_imagen'];
			$this->sPrecio = $aArgumentos['precio'];
			//$this->sTax = $aArgumentos['tax'];
			$this->sDesde = $aArgumentos['desde'];
			$this->sOferta = $aArgumentos['oferta'];
			$this->sPorcentaje = $aArgumentos['porcentaje'];
			$this->bEnvioGratis = $aArgumentos['envio_gratis'];
			$this->sDescription  = $aArgumentos['descripcion'];
			$this->aPadding = array_key_exists( 'padding', $aArgumentos ) ? $aArgumentos['padding'] : array( 0, 0, 0, 0 );
			$this->bIcon = array_key_exists( 'icon', $aArgumentos ) ? $aArgumentos['icon'] : true;

			// Obtenemos el sprite
			$this->sSprite = $this->sTheme . 'sprite.png';

			// Obtenemos el style
			$this->aStyle = @Spyc::YAMLLoad( $this->sTheme . 'style.yml' );

			// Recorremos los style y si tenemos padding deberemos modificarle las posiciones
			if( $this->aPadding[0] > 0 || $this->aPadding[1] > 0 || $this->aPadding[2] > 0 || $this->aPadding[3] > 0 )
			{
				foreach( $this->aStyle as $key => $aStyle )
				{
					// Modificacion del ancho y alto del fondo
					if( $key == 'fondo' ||$key == 'fondo-off' )
					{
						$this->aStyle[$key]['width'] += $this->aPadding[1] + $this->aPadding[3];
						$this->aStyle[$key]['height'] += $this->aPadding[0] + $this->aPadding[2];
						continue;
					}

					// Modificamos el top y left
					foreach( array( 'left', 'top' ) as $sAttribute )
					{
						if( array_key_exists( $sAttribute, $aStyle ) )
							if( $sAttribute == 'left' )
								$this->aStyle[$key]['left'] += $this->aPadding[3];
							elseif( $sAttribute == 'top' )
								$this->aStyle[$key]['top'] += $this->aPadding[0];
					}
				}
			}

			// Imagen sprite completo
			$this->gdSprite = imagecreatefrompng( $this->sSprite );
			imagealphablending( $this->gdSprite, true );
			imagesavealpha( $this->gdSprite, true );

			// Creamos el producto
			$this->createFondo();

			// Titulo del producto
			if( $this->sDescription != '' )
			{
				$this->sDescription = preg_replace("/[\r\n\t]+/", "", substr( html_entity_decode( strip_tags( $this->sDescription ) ), 0, $this->aStyle['description']['substr'] ) ) . '...';

				$this->createDescription();
			}

			// Titulo del producto
			if( $this->sTitulo != '' )
			{
				$this->sTitulo = strip_tags( $this->sTitulo );

				if( strlen( $this->sTitulo ) > $this->aStyle['titulo']['substr'] )
					$this->sTitulo = substr( $this->sTitulo, 0, $this->aStyle['titulo']['substr'] ) . '...';

				$this->createTitle();
			}

			// Imagen del producto
			if( $this->sImagen != '' && file_exists( $this->sDirectorioImagen . $this->sImagen ) )
			{
				$this->sImagen = $this->sDirectorioImagen . $this->sImagen;
				$this->createImagen();
			}

			// Producto en oferta
			if( $this->sOferta != '' )
				$this->createOferta();

			// Producto envio gratis
			if( $this->bEnvioGratis != '' && $this->bIcon )
				$this->createElementSprite('envio');

			// Precio del producto
			if( $this->sPrecio != '' )
				$this->createPrices();

			// Precio del producto
			if( $this->sPorcentaje != '' && $this->sOferta != '' )
				$this->createPorcentaje();
			// Tax del producto
			//if( $this->sTax != '' )
				//$this->createTax();

			// Producto en rapel (texto desde)
			if( $this->sDesde != '' )
				$this->createDesde();
		}

		private function createFondo()
		{
			// Creamos una imagen con un ancho y un alto
			$this->gdFondo = imagecreatetruecolor( $this->aStyle[($this->sOferta != '' ? 'fondo-off' : 'fondo')]['width'], $this->aStyle[($this->sOferta != '' ? 'fondo-off' : 'fondo')]['height']);
			imagesavealpha($this->gdFondo, true);

			// La imagen la ponemos con fondo transparente
			imagefill( $this->gdFondo, 0, 0, imagecolorallocatealpha( $this->gdFondo, 0, 0, 0, 127 ) );

			// Realizamos un crop a la imagen sprite para obtener la imagen de fondo
			imagecopyresampled( $this->gdFondo, $this->gdSprite, $this->aPadding[3], $this->aPadding[0], $this->aStyle[($this->sOferta != '' ? 'fondo-off' : 'fondo')]['background-position'][0], $this->aStyle[($this->sOferta != '' ? 'fondo-off' : 'fondo')]['background-position'][1], $this->aStyle[($this->sOferta != '' ? 'fondo-off' : 'fondo')]['width'] - $this->aPadding[1] - $this->aPadding[3], $this->aStyle[($this->sOferta != '' ? 'fondo-off' : 'fondo')]['height'] - $this->aPadding[0] - $this->aPadding[2], $this->aStyle[($this->sOferta != '' ? 'fondo-off' : 'fondo')]['width'] - $this->aPadding[1] - $this->aPadding[3], $this->aStyle[($this->sOferta != '' ? 'fondo-off' : 'fondo')]['height'] - $this->aPadding[0] - $this->aPadding[2] );
		}

		private function createDescription()
		{
			imagettftextbox( $this->gdFondo, $this->aStyle['description']['font']['size'], 0, $this->aStyle['description']['left'], $this->aStyle['description']['top'], $this->aStyle['description']['color'], $this->sTheme . $this->aStyle['description']['font']['family'], $this->sDescription, $this->aStyle['description']['width'], $this->aStyle['description']['align'], $this->aStyle['description']['line-height'], $this->aStyle['description']['height'] );
		}

		private function createTitle()
		{
			imagettftextbox( $this->gdFondo, $this->aStyle['titulo']['font']['size'], 0, $this->aStyle['titulo']['left'], $this->aStyle['titulo']['top'], $this->aStyle['titulo']['color'], $this->sTheme . $this->aStyle['titulo']['font']['family'], $this->sTitulo, $this->aStyle['titulo']['width'], $this->aStyle['titulo']['align'], $this->aStyle['titulo']['line-height'], $this->aStyle['titulo']['height'] );
		}

		private function createImagen()
		{
			// Informacion de la imagen
			$aImageInfo = @getimagesize( $this->sImagen );

			// Segun el mime realizamos una instancia diferente
			switch( $aImageInfo['mime'] )
			{
				case 'image/jpeg':
					$gdImage = imagecreatefromjpeg( $this->sImagen );
				break;

				case 'image/gif':
					$gdImage = imagecreatefromgif( $this->sImagen );
				break;

				case 'image/png':
					$gdImage = imagecreatefrompng( $this->sImagen );
				break;
			}

			imagealphablending($gdImage, true);

			// Obtenemos el ancho y el alto de la imagen final dentro del thumb
			$aScale = scaleSize( $aImageInfo[0], $aImageInfo[1], $this->aStyle['imagen']['width'], $this->aStyle['imagen']['height'] );

			// Creamos una imagen con un ancho y un alto
			$gdThumb = imagecreatetruecolor( $this->aStyle['imagen']['width'], $this->aStyle['imagen']['height'] );
			imagesavealpha($gdThumb, true);

			// La imagen la ponemos con fondo transparente
			imagefill( $gdThumb, 0, 0, imagecolorallocatealpha( $gdThumb, 0, 0, 0, 127 ) );

			// Creamos el thumb centrado
			imagecopyresampled( $gdThumb, $gdImage, ($this->aStyle['imagen']['width'] / 2) - ($aScale['WIDTH'] / 2), ($this->aStyle['imagen']['height'] / 2) - ($aScale['HEIGHT'] / 2), 0, 0, $aScale['WIDTH'], $aScale['HEIGHT'], $aImageInfo[0], $aImageInfo[1] );

			// Creamos la imagen encima
			imagecopyresampled( $this->gdFondo, $gdThumb, $this->aStyle['imagen']['left'], $this->aStyle['imagen']['top'], 0, 0, $this->aStyle['imagen']['width'], $this->aStyle['imagen']['height'], $this->aStyle['imagen']['width'], $this->aStyle['imagen']['height'] );
		}

		private function createOferta()
		{
			// Precio de la oferta
			$aInfo = imagettftextbox( $this->gdFondo, $this->aStyle['oferta_precio']['font']['size'], 0, $this->aStyle['oferta_precio']['left'], $this->aStyle['oferta_precio']['top'], $this->aStyle['oferta_precio']['color'], $this->sTheme . $this->aStyle['oferta_precio']['font']['family'], $this->sOferta, $this->aStyle['oferta_precio']['width'], $this->aStyle['oferta_precio']['align'] );

			// Creamos el icono de oferta
			if( $this->bIcon )
				$this->createElementSprite('oferta');

			// Tachado de la imagen
			// Tamaño de la imagen
			$imImage = @imagecreate( $aInfo['width'], 2 );
			// Color de relleno en RGB
			$imRelleno = imagecolorallocate( $imImage, $this->aStyle['oferta_tachado']['color'][0], $this->aStyle['oferta_tachado']['color'][1], $this->aStyle['oferta_tachado']['color'][2] );

			// Creamos la instancia del rectangulo
			imagefilledrectangle( $imImage, 0, 0, $aInfo['width'], 1, $imRelleno );

			// Creamos la imagen encima
			imagecopyresampled( $this->gdFondo, $imImage, $this->aStyle['oferta_precio']['left'], $this->aStyle['oferta_tachado']['top'], 0, 0, $aInfo['width'], 1, $aInfo['width'], 1 );
		}

		private function createPorcentaje()
		{
			imagettftextbox( $this->gdFondo, $this->aStyle['porcentaje']['font']['size'], 0, $this->aStyle['porcentaje']['left'], $this->aStyle['porcentaje']['top'], $this->aStyle['porcentaje']['color'], $this->sTheme . $this->aStyle['porcentaje']['font']['family'], $this->sPorcentaje, $this->aStyle['porcentaje']['width'], $this->aStyle['porcentaje']['align'], $this->aStyle['porcentaje']['line-height'], $this->aStyle['porcentaje']['height'] );
		}

		private function createElementSprite($sClass)
		{
			// Imagen sprite
			$gdSpriteAux = imagecreatetruecolor( $this->aStyle[$sClass]['width'], $this->aStyle[$sClass]['height'] );
			imagealphablending( $gdSpriteAux, false );
			imagesavealpha( $gdSpriteAux, true );

			// Realizamos un crop a la imagen sprite para obtener la imagen
			imagecopyresampled( $gdSpriteAux, $this->gdSprite, 0, 0, $this->aStyle[$sClass]['background-position'][0], $this->aStyle[$sClass]['background-position'][1], imagesx( $this->gdSprite ), imagesy( $this->gdSprite ), imagesx( $this->gdSprite ), imagesy( $this->gdSprite ) );

			// Creamos la imagen encima
			imagecopyresampled( $this->gdFondo, $gdSpriteAux, $this->aStyle[$sClass]['left'], $this->aStyle[$sClass]['top'], 0, 0, $this->aStyle[$sClass]['width'], $this->aStyle[$sClass]['height'], $this->aStyle[$sClass]['width'], $this->aStyle[$sClass]['height'] );
		}

		private function createPrices()
		{
			// Obtenemos el precio
			$sPrice = str_replace( array( '€', '&euro;' ), array( 'E', 'E' ), $this->sPrecio );

			// Total de caracteres
			$nTotal = strlen( $sPrice );

			// Comprobar si son decimales o no
			$bDecimal = false;

			// Ancho total que ocupara el precio
			$nAnchoTotal = 0;

			// Array donde vamos almacenando los numeros
			$aNumeros = array();

			// Recorremos los numeros para obtener el ancho total y su informacion del style
			for( $nCont = 0; $nCont < $nTotal; $nCont++ )
			{
				// Guardamos el caracter
				$sCaracter = $sPrice[$nCont];

				// Obtenemos el caracter del array de numeros, comprobando si es decimal o no
				if( $bDecimal && array_key_exists( 'decimal_' . $sCaracter, $this->aStyle['precio'] ) )
					$aNumeros[$nCont] = $this->aStyle['precio']['decimal_' . $sCaracter];
				else
					$aNumeros[$nCont] = $this->aStyle['precio'][$sCaracter];

				// Vamos sumando el ancho total
				$nAnchoTotal += $aNumeros[$nCont]['width'] + $this->aStyle['precio']['margin'];

				// Si ha sido una coma lo proximo seran decimales
				if( $sPrice[$nCont] == ',' )
					$bDecimal = true;
			}

			// Restamo del ultimo número su margin
			$nAnchoTotal -= $this->aStyle['precio']['margin'];

			// Posicion del numero
			switch( $this->aStyle['precio']['align'] )
			{
				case 'center':
					$nLeft = ($this->aStyle['precio']['width'] / 2) - ($nAnchoTotal / 2);
				break;

				case is_integer( $this->aStyle['precio']['align'] ) && $this->aStyle['precio']['align'] > 0:
					$nLeft = $this->aStyle['precio']['align'];
				break;

				default:
					$nLeft = 0;
				break;
			}

			// Si contiene left
			if( array_key_exists( 'left', $this->aStyle['precio'] ) )
				$nLeft += $this->aStyle['precio']['left'];

			// Recorremos los numeros obtenidos
			foreach( $aNumeros as $aNumero )
			{
				// Imagen sprite
				$gdSpritePrice = imagecreatetruecolor( $aNumero['width'], $aNumero['height'] );
				imagealphablending( $gdSpritePrice, false );
				imagesavealpha( $gdSpritePrice, true );

				// Realizamos un crop a la imagen sprite para obtener la imagen de envio
				imagecopyresampled( $gdSpritePrice, $this->gdSprite, 0, 0, $aNumero['background-position'][0], $aNumero['background-position'][1], imagesx( $this->gdSprite ), imagesy( $this->gdSprite ), imagesx( $this->gdSprite ), imagesy( $this->gdSprite ) );

				// Posicion top
				$nTop = $this->aStyle['precio']['top'];

				// Si contenemos margin top aumentamos
				if( array_key_exists( 'top', $aNumero ) )
					$nTop += $aNumero['top'];

				// Creamos la imagen de envio encima
				imagecopyresampled( $this->gdFondo, $gdSpritePrice, $nLeft, $nTop, 0, 0, $aNumero['width'], $aNumero['height'], $aNumero['width'], $aNumero['height'] );

				// Vamos aumentando la posicion left
				$nLeft += $aNumero['width'] + $this->aStyle['precio']['margin'];
			}
		}

		private function createTax()
		{
			imagettftextbox( $this->gdFondo, $this->aStyle['tax']['font']['size'], 0, $this->aStyle['tax']['left'], $this->aStyle['tax']['top'], $this->aStyle['tax']['color'], $this->sTheme . $this->aStyle['tax']['font']['family'], $this->sTax, $this->aStyle['tax']['width'], $this->aStyle['tax']['align'] );
		}

		private function createDesde()
		{
			imagettftextbox( $this->gdFondo, $this->aStyle['desde']['font']['size'], 0, $this->aStyle['desde']['left'], $this->aStyle['desde']['top'], $this->aStyle['desde']['color'], $this->sTheme . $this->aStyle['desde']['font']['family'], $this->sDesde, $this->aStyle['desde']['width'], $this->aStyle['desde']['align'] );
		}
		public function show($b64 = false)
		{
			// Mostarlos directamente o devolver la imagen en base 64
			if( $b64 )
			{
				// Capturamos la salida de la imagen
				ob_start();

				// Mostramos la imagen
				imagepng( $this->gdFondo );

				// Obtenemos la imagen en base 64
				$sImageBytes = ob_get_clean();

				// Retornamos
				return base64_encode( $sImageBytes );
			}
			else
			{
				// Cabeceras
				header( 'Content-type: image/png' );
				header( 'Cache-Control: no-cache, must-revalidate' );
				header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );

				// Mostramos imagen
				imagepng( $this->gdFondo );
			}

			// Liberamos
			imagedestroy( $this->gdFondo );
			imagedestroy( $this->gdSprite );
		}

		// Propiedades privadas
		private $gdSprite;
		private $gdProducto;
		private $gdImagen;
		private $aStyle;
		private $sSprite;
		private $sTitulo;
		private $sImagen;
		private $sPrecio;
		private $sPorcentaje;
		//private $sTax;
		private $sDesde;
		private $bEnvioGratis;
		private $sOferta;
	}
?>