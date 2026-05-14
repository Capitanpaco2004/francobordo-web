<?php
    function _product($aArgumentos = array())
    {
        // Variables
		global $aProducto, $nProductosTotal, $dxWishlist, $bComparasion, $aAllPromotions;

		$aProducto = array_key_exists( 'PRODUCTO', $aArgumentos ) ? $aArgumentos['PRODUCTO'] : $aProducto;
		$sClassExtra = array_key_exists( 'CLASS', $aArgumentos ) ? $aArgumentos['CLASS'] : '';
		$sClassPrecio = array_key_exists( 'CLASS_PRECIO', $aArgumentos ) ? $aArgumentos['CLASS_PRECIO'] : '';
		$nImagenWidth = array_key_exists( 'IMAGEN_WIDTH', $aArgumentos ) ? $aArgumentos['IMAGEN_WIDTH'] : '130';
		$nImagenHeight = array_key_exists( 'IMAGEN_HEIGHT', $aArgumentos ) ? $aArgumentos['IMAGEN_HEIGHT'] : '130';
		$bDescription = array_key_exists( 'DESCRIPCION', $aArgumentos ) ? $aArgumentos['DESCRIPCION'] : true;
		$bOferta = array_key_exists( 'OFERTA', $aArgumentos ) ? $aArgumentos['OFERTA'] : true;
		$bEnvio = array_key_exists( 'ENVIO', $aArgumentos ) ? $aArgumentos['ENVIO'] : true;
		$bStock = array_key_exists( 'STOCK', $aArgumentos ) ? $aArgumentos['STOCK'] : false;
		$bVista = array_key_exists( 'VISTA', $aArgumentos ) ? $aArgumentos['VISTA'] : true;
		$nDescriptionSize = array_key_exists( 'DESCRIPTION_SIZE', $aArgumentos ) ? $aArgumentos['DESCRIPTION_SIZE'] : 100;
		$sHtml = '';
		$bPromo = false;
		$bDescuento = false;

		// Decidimos la clase para el producto segun la vista que tenga la web
		$sClass = ($bVista && !empty($_SESSION['vista'] ) && $_SESSION['vista'] == 'chng-vsta-hrzt' ? 'prdt-hrzt' : '');

		if( $aProducto['products_status'] == 2 )
		{
			$aProducto['CLASS'] = 'prdt-agtd';
			$aProducto['CLASS_OFERTA'] = '';
		}

		$sHtml = '<div class="xprdt prdt col ' . ($sClassExtra != '' ? $sClassExtra : 'a03 t04 m06') . ' d-flex ' . $sClass . ' ' . $aProducto['CLASS'] . '">';
			// Solo renderizamos la capa .offr cuando hay oferta; si no es una caja blanca absolute que tapa la esquina de la imagen
			if ($aProducto['CLASS_OFERTA'] != '') {
			$sHtml .= '<div class="offr d-flex">';
				$sHtml .= '<div class="icon"><span>' . $aProducto['OFERTA_PORCENTAJE'] . '</span></div>';
				$sHtml .= '<div class="wrpr-offr flex-grow-1">';
					if (defined('SHOW_LEFT_UNITS_OFFER_IPS'))
					{
						$aIps = explode('|', SHOW_LEFT_UNITS_OFFER_IPS);

						if (defined('SHOW_LEFT_UNITS_OFFER_ACTIVE') && (SHOW_LEFT_UNITS_OFFER_ACTIVE == 'Si' || in_array($_SERVER['REMOTE_ADDR'], $aIps)))
						{
							if ($bOferta && $aProducto['CLASS_OFERTA'] != '' && $aProducto['products_quantity'] > 0 && $aProducto['products_quantity'] <= intval(SHOW_LEFT_UNITS_OFFER_NUMBER))
							{
								$sHtml .= '<div class="stok">' . ($aProducto['products_quantity'] == 1 ? STOCK_LEFT_SINGULAR : sprintf(STOCK_LEFT_PLURAL, $aProducto['products_quantity'])) . '</div>';
							}
						}
					}

					// Oferta express
					if( $aProducto['CLASS_OFERTA'] != '' && $aProducto['OFERTA_FECHA'] != '' )
					{
						$aTime = dateTimeDiff( date( 'Y-m-d H:i:s' ), $aProducto['expires_date'] );

						$sHtml .= '<div class="infr">' . SPECIALS_CUENTA_ATRAS . '</div>';
						$sHtml .= '<div class="hour">
							<span class="d">' . $aTime['dia'] . '</span> ' . ($aTime['dia'] > 1 ? SPECIALS_CUENTA_ATRAS_DIAS : SPECIALS_CUENTA_ATRAS_DIA) . '
							<span class="h">' . $aTime['hora'] . '</span>h:
							<span class="m">' . $aTime['minuto'] . '</span>m:
							<span class="s">' . $aTime['segundo'] . '</span>s
						</div>';
					}
				$sHtml .= '</div>';
			$sHtml .= '</div>';
			} // end if CLASS_OFERTA

			// Icono promocion
			if( $aProducto['grcats'] != '' )
				$aAllCatsPr = explode( ', ', substr( $aProducto['grcats'], 0, -2 ) );
			else
				$aAllCatsPr[] = $aProducto['categories_id'];

			if( isset( $aAllPromotions ) && count( $aAllPromotions ) > 0 )
			{
				$sIconPromo = '';
				$nSpecialPromo = 0;
				foreach( $aAllPromotions as $aElements )
				{
					foreach( $aElements['elements'] as $aElement )
					{
						if( $aElement['element_operation'] == 'p' )
						{
							if( $aElement['element_type'] == 'c' && in_array( $aElement['element_id'], $aAllCatsPr )  )
								$bPromo = true;
							elseif( $aElement['element_type'] == 'm' && $aProducto['manufacturers_id'] == $aElement['element_id']  )
								$bPromo = true;
							elseif( $aElement['element_type'] == 'p' && $aProducto['products_id'] == $aElement['element_id']  )
								$bPromo = true;
						}
						elseif( $aElement['element_operation'] == 'd' )
						{
							if( $aElement['element_type'] == 'c' && in_array( $aElement['element_id'], $aAllCatsPr )  )
								$bDescuento = true;
							elseif( $aElement['element_type'] == 'm' && $aProducto['manufacturers_id'] == $aElement['element_id']  )
								$bDescuento = true;
							elseif( $aElement['element_type'] == 'p' && $aProducto['products_id'] == $aElement['element_id']  )
								$bDescuento = true;
						}
					}

					if( $bPromo || $bDescuento )
					{
						$sIconPromo = $aElements['icon'];
						$nSpecialPromo = $aElements['special'];
						break;
					}
				}

				$sHtml .= (
				$bPromo || $bDescuento
					? ($sIconPromo == ''
					? '<a class="prmt" href="promociones-i-23.html" title="Estas son nuestras promociones" alt="Estas son nuestras promociones"></a>'
					: '<a class="prmicon" href="promociones-i-23.html" title="Estas son nuestras promociones" alt="Estas son nuestras promociones"><img src="' . DIR_WS_IMAGES . 'landings/' . $sIconPromo . '" /></a>'
				)
					: ''
				);
			}

			$sHtml .= '<a rel="nofollow" class="img" title="' . $aProducto['TITLE'] . '" href="' . $aProducto['HREF'] . '">';
				$sHtml .= tep_image( DIR_WS_IMAGES . 'productos/' . $aProducto['products_image'], $aProducto['TITLE'], 271, 200, '', false );
			$sHtml .= '</a>';

			$sHtml .= '<div class="wrpr d-flex flex-grow-1">';
				$sHtml .= '<a href="' . $aProducto['HREF'] . '" title="' . $aProducto['TITLE'] . '" class="titu">' . $aProducto['products_name'] . '</a>';
				$sHtml .= '<div class="desc">' . truncate( $aProducto['products_description'], array( 'SIZE' => 400 ) ) . ' <a href="' . $aProducto['HREF'] . '" rel="nofollow" class="prdt-more">' . TEXT_READ_MORE . '</a></div>';

				$sHtml .= '<div class="mt-auto">';
					$sHtml .= '<span class="star st' . ($aProducto['review_rating'] > 0 ? (int)$aProducto['review_rating'] : 5) . '"><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i></span>';

					$sHtml .= '<span class="prco" data-tax="' . TEXT_IVA . (mostrarIva() ? '' : ' NO') . ' incl.">' . $aProducto['PRECIO'] . ($aProducto['PRECIO_ANTERIOR'] != '' ? ' <s>' . $aProducto['PRECIO_ANTERIOR'] . '</s>' : '') . '</span>';
				
					$sHtml .= '<div class="opcty d-flex">';
						// Popup bajo demanda
						if( preg_match( '/prdt-bjpdd/i', $aProducto['CLASS'] ) )
							$sHtml .= '<a class="ajx-bjo" href="ajax_bajodemanda.php" class="mgp-ajax" style="display: none;"></a>';

						$nAtributo = tep_has_product_attributes( $aProducto['products_id'] );
						if( preg_match( '/prdt-agtd/i', $aProducto['CLASS'] ) && $aProducto['products_status'] == 1 )
							$sHtml .= '<a class="buy mgp-ajax" rel="nofollow" data-qty="0" title="Avísame: ' . $aProducto['products_name'] . '" href="notify.php?id=' . $aProducto['products_id'] . '">' . TEXT_AVISEME . '</a>';
						else
							$sHtml .= '<a rel="nofollow" data-qty="' .  (preg_match( '/prdt-agtd/i', $aProducto['CLASS'] ) ? 0 : 1) . '" data-atribute="' . $nAtributo .  '" data-id="' . $aProducto['products_id'] . '" class="buy flex-grow-1" title="' . IMAGE_BUTTON_RP_BUY_NOW . ' ' . $aProducto['TITLE'] . '" data-href="' . tep_href_link( FILENAME_PRODUCT_INFO, 'products_id=' . $aProducto["products_id"] ) . '" href="javascript:void(0);">' . (preg_match( '/prdt-bjpdd/i', $aProducto['CLASS'] ) ? TEXT_BAJO_PEDIDO : (preg_match( '/prdt-agtd/i', $aProducto['CLASS'] ) ? TEXT_SIN_STOCK : IMAGE_BUTTON_RP_BUY_NOW)) . '</a>';

						$sHtml .= $dxWishlist->getHtmlIconAdd( $aProducto['products_id'], $aProducto['products_name'], false, $nAtributo );

						$sHtml .= '<div class="text d-flex">';
							if( !preg_match( '/prdt-agtd/i', $aProducto['CLASS'] ) )
							{
								// Si el producto tiene atributos, mostramos un placeholder
								// (la disponibilidad real depende de la opción elegida en la ficha).
								if( !$nAtributo )
								{
									$sHtml .= '<i class="tt tt-6 icon"></i>';
									$sHtml .= '<span>' . (preg_match( '/prdt-4dias/i', $aProducto['CLASS'] ) ? sprintf( TEXT_ENTREGA_EN, '2-4') : (preg_match( '/prdt-5dias/i', $aProducto['CLASS'] ) ? sprintf( TEXT_ENTREGA_EN, '7-10') : (preg_match( '/prdt-bjpdd/i', $aProducto['CLASS'] ) ? sprintf( TEXT_ENTREGA_SUPR, '30 ' . SPECIALS_CUENTA_ATRAS_DIAS ) : TEXT_ENTREGA_24))) . '</span>';
								}
								else
								{
									$sHtml .= '<span style="color:#f7a521;">Seleccionar opción</span>';
								}
							}
							else
								$sHtml .= '<span>' . TEXT_SIN_STOCK . '</span>';
							$sHtml .= '<span class="tax ml-auto">' . TEXT_IVA . (mostrarIva() ? '' : ' NO') . ' incl.</span>';
						$sHtml .= '</div>';
						if( $bComparasion )
							$sHtml .= '<div class="compr">' . TEXT_COMPARAR . '<input type="checkbox" value="' . $aProducto['products_id'] . '" name="comp[]"/></div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';
			$sHtml .= '</div>';
		$sHtml .= '</div>';

		return $sHtml;
    }

	function _product_slide_box($aArgumentos = array())
	{
        // Variables
        global $aProducto;
        $sHtml = '';

		$sHtml = '<div class="prdct-slde ' . $aProducto['CLASS_ENVIO'] . ' ' . $aProducto['CLASS_OFERTA'] . '">';

			$sHtml .= ($aProducto['CLASS_OFERTA'] != '' ? '<div class="icon-ofrt"></div>' : '');
			$sHtml .= ($aProducto['CLASS_ENVIO'] != '' ? '<div class="icon-envo"></div>' : '');

			$sHtml .= '<h3><a class="prdct-title" href="' . $aProducto['HREF'] . '" title="' . $aProducto['TITLE'] . '">' . $aProducto['products_name'] . '</a></h3>';

			$sHtml .= '<a class="prdct-img" href="' . $aProducto['HREF'] . '" title="' . $aProducto['TITLE'] . '">' . tep_image(DIR_WS_IMAGES . 'productos/' .$aProducto['products_image'], $aProducto['TITLE'], 130, 130, '', false ) . '</a>';

			$sHtml .= '<div class="prdct-prco">
				<div class="prco prco-s">
					' . getPrecioImagen( $aProducto ) . ' <s>' . $aProducto['PRECIO_ANTERIOR'] . '</s>' . '
				</div>
			</div>';

			$sHtml .= '<a class="prdct-cmpr" title="Comprar ' . $aProducto['TITLE'] . '" href="' . tep_href_link( FILENAME_DEFAULT, tep_get_all_get_params( array('action') ) . 'action=buy_now&products_id=' . $aProducto["products_id"] ) . '">Comprar ' . $aProducto['products_name'] . '</a>';

		$sHtml .= '</div>';

        return $sHtml;
	}
?>