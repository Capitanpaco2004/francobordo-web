<?php
use util\ShippingEstimator;

if( count( $aProductoInfo ) == 0 )
{
	echo '<div class="web-cntd">';
		echo $messageStack->show( array( 'class' => 'wrng', 'text' => TEXT_PRODUCT_NOT_FOUND ) );
	echo '</div>';

	return false;
}

include(DIR_WS_COMPONENTS . 'productos_descatalogados.php');

if( $aProductoInfo['products_status'] == 2 )
{
	$aProductoInfo['CLASS'] = 'prdt-agtd';
	$aProductoInfo['CLASS_OFERTA'] = '';
}

$bSpecifications = tep_has_specifications( $aProductoInfo['products_id'] );

echo tep_draw_form('cart_quantity', tep_href_link('product_info.php', tep_get_all_get_params(array('action')) . 'action=add_product'), 'post', 'id="fich" class="xprdt web-cntd ax row atop ' . $aProductoInfo['CLASS'] . '" data-stock="' . (int)$aProductoInfo['products_quantity'] . '" data-check-stock="' . (int)$aProductoInfo['check_stock'] . '" data-product-name="' . htmlspecialchars($aProductoInfo['products_name'], ENT_QUOTES) . '" onsubmit="return false;"');

	echo '<div class="col a06 t12 m12 left">';
		// Solo renderizamos la capa .offr cuando el producto está en oferta;
		// si no, era una caja blanca position:absolute z-index:1 background:#fff que tapaba la esquina superior de la imagen.
		if ($aProductoInfo['CLASS_OFERTA'] != '') {
		echo '<div class="offr d-flex">';
			echo '<div class="icon"><span>' . $aProductoInfo['OFERTA_PORCENTAJE'] . '</span></div>';
			echo '<div class="wrpr-offr flex-grow-1">';
				$bLastStock = false;
				if (defined('SHOW_LEFT_UNITS_OFFER_IPS'))
				{
					$aIps = explode('|', SHOW_LEFT_UNITS_OFFER_IPS);

					if (defined('SHOW_LEFT_UNITS_OFFER_ACTIVE') && (SHOW_LEFT_UNITS_OFFER_ACTIVE == 'Si' || in_array($_SERVER['REMOTE_ADDR'], $aIps)))
					{
						if ($aProductoInfo['CLASS_OFERTA'] != '' && $aProductoInfo['products_quantity'] > 0 && $aProductoInfo['products_quantity'] <= intval(SHOW_LEFT_UNITS_OFFER_NUMBER))
						{
							$bLastStock = true;
							echo '<div class="stok">' . ($aProductoInfo['products_quantity'] == 1 ? STOCK_LEFT_SINGULAR : sprintf(STOCK_LEFT_PLURAL, $aProductoInfo['products_quantity'])) . '</div>';
						}
					}
				}

				// Oferta express
				if( $aProductoInfo['CLASS_OFERTA'] != '' && $aProductoInfo['OFERTA_FECHA'] != '' )
				{
					$aTime = dateTimeDiff( date( 'Y-m-d H:i:s' ), $aProductoInfo['expires_date'] );

					echo '<div class="infr">' . SPECIALS_CUENTA_ATRAS . '</div>';
					echo '<div class="hour">
						<span class="d">' . $aTime['dia'] . '</span> ' . ($aTime['dia'] > 1 ? SPECIALS_CUENTA_ATRAS_DIAS : SPECIALS_CUENTA_ATRAS_DIA) . '
						<span class="h">' . $aTime['hora'] . '</span>h:
						<span class="m">' . $aTime['minuto'] . '</span>m:
						<span class="s">' . $aTime['segundo'] . '</span>s
					</div>';
				}
			echo '</div>';
		echo '</div>';
		} // end if CLASS_OFERTA

		// Icono promocion
		$bPromo = false;
		$bDescuento = false;
		$aAllCatsPr = explode( ', ', substr( (string)($aProductoInfo['grcats'] ?? ''), 0, -2 ) );

		if( isset( $aAllPromotions ) && count( $aAllPromotions ) > 0 )
		{
			foreach( $aAllPromotions as $aElements )
			{
				foreach( $aElements['elements'] as $aElement )
				{
					if( $aElement['element_operation'] == 'p' )
					{
						if( $aElement['element_type'] == 'c' && in_array( $aElement['element_id'], $aAllCatsPr )  )
							$bPromo = true;
						elseif( $aElement['element_type'] == 'm' && $aProductoInfo['manufacturers_id'] == $aElement['element_id']  )
							$bPromo = true;
						elseif( $aElement['element_type'] == 'p' && $aProductoInfo['products_id'] == $aElement['element_id']  )
							$bPromo = true;
					}
					elseif( $aElement['element_operation'] == 'd' )
					{
						if( $aElement['element_type'] == 'c' && in_array( $aElement['element_id'], $aAllCatsPr )  )
							$bDescuento = true;
						elseif( $aElement['element_type'] == 'm' && $aProductoInfo['manufacturers_id'] == $aElement['element_id']  )
							$bDescuento = true;
						elseif( $aElement['element_type'] == 'p' && $aProductoInfo['products_id'] == $aElement['element_id']  )
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

			if ($bPromo || $bDescuento) {
				echo (
				$sIconPromo == ''
					? '<a class="prmt" href="promociones-i-23.html" title="Estas son nuestras promociones"></a>'
					: '<a class="prmicon" href="promociones-i-23.html" title="Estas son nuestras promociones"><img src="' . DIR_WS_IMAGES . 'landings/' . $sIconPromo . '" /></a>'
				);
			}
		}

		echo '<div id="slick-fich">';
			if(count($aProductoInfo['imagenes']) > 0)
			{
				foreach( $aProductoInfo['imagenes'] as $key => $sImagen )
				{
					// Ruta real de la imagen o fallback a no_image
					$sImagenReal = ($sImagen && file_exists( DIR_WS_IMAGES . 'productos/' . $sImagen)) ? DIR_WS_IMAGES . 'productos/' . $sImagen : 'theme/web/images/general/no_image.jpg';

					// Generamos thumbnail
					$sThumb = tep_image_thumb( $sImagenReal, 101, 101 );

					echo '<div class="item' . ($key == 0 ? ' img actv' : '') . '" data-thumb="' . $sThumb . '" data-src="' . $sImagenReal . '">
						' . tep_image($sImagenReal, $aProductoInfo['products_name'], 588, 506, '', false, false, false) . '
					</div>';
				}
			}

			// Slide del visor 3D: solo si existe el modelo .glb del producto (detección por id, igual que el resto del pipeline).
			$bHas3d = false;
			$s3dWeb = DIR_WS_IMAGES . 'productos/3d/' . (int)$aProductoInfo['products_id'] . '.glb';
			if( file_exists( $s3dWeb ) )
			{
				$bHas3d = true;
				// Poster = primera imagen del producto (o no_image); el .glb no se descarga hasta interactuar (reveal="interaction").
				$sImg0 = $aProductoInfo['imagenes'][0] ?? '';
				$sPoster3d = ($sImg0 && file_exists( DIR_WS_IMAGES . 'productos/' . $sImg0 ))
					? tep_image_thumb( DIR_WS_IMAGES . 'productos/' . $sImg0, 588, 506 )
					: 'theme/web/images/general/no_image.jpg';

				echo '<div class="item item-3d" data-thumb="theme/web/images/general/badge_3d.svg" data-glb="' . $s3dWeb . '">
					<model-viewer src="' . $s3dWeb . '" poster="' . $sPoster3d . '" alt="' . htmlspecialchars( $aProductoInfo['products_name'], ENT_QUOTES ) . ' 3D"
						reveal="interaction" camera-controls touch-action="pan-y" auto-rotate auto-rotate-delay="3000"
						ar ar-modes="webxr scene-viewer quick-look" environment-image="neutral" tone-mapping="neutral" shadow-intensity="1.2" shadow-softness="0.8" exposure="1"
						style="width:100%;height:506px;background:linear-gradient(180deg,#fbfcfd 0%,#dfe6ec 100%);--poster-color:#f5f5f5;">
					</model-viewer>
				</div>';
			}
		echo '</div>';
		// model-viewer auto-hospedado, FUERA del #slick-fich (un <script> hijo del slider se vuelve un slide fantasma y NO se ejecuta) y FUERA del bundle minify (es un ES module).
		if( $bHas3d )
		{
			echo '<script type="module" src="theme/web/js/model-viewer.min.js"></script>';
			// Al entrar en el slide 3D, disparamos la carga del modelo (reveal="interaction" no carga hasta interactuar; aquí lo hacemos al elegir el dot 3D).
			echo '<script>document.addEventListener("DOMContentLoaded",function(){if(!window.jQuery)return;jQuery("#slick-fich").on("afterChange.mv3dload",function(e,s,c){if(jQuery(s.$slides.get(c)).hasClass("item-3d")){document.querySelectorAll("#slick-fich .slick-current model-viewer,#slick-fich .slick-active model-viewer").forEach(function(m){try{m.dismissPoster();}catch(_){}});}});});</script>';
		}
	echo '</div>';

	echo '<div class="col a06 t12 m12 right">';
		echo '<div class="titu">' . $aProductoInfo['products_name'] . '</div>';
		if( $aProductoInfo['products_model'] != '' ) {
			echo '<span class="mdel">Ref.: ' . $aProductoInfo['products_model'] . '</span>';
		}

		echo '<div class="wrpe-str d-flex">';


			echo '<span class="star st' . ($aProductoInfo['review_rating'] > 0 ? (int)$aProductoInfo['review_rating'] : 5) . '"><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i></span>';

				echo '<div class="infr"><strong>' . ( ceil($aProductoInfo['review_rating'] *10)/10) . '</strong>/5 | <b>' . $aProductoInfo['review_count'] . '</b> ' . strtolower( MY_REVIEWS ) . ' | <u class="mhide" data-tabs-anchor="xtabs-' . ($bSpecifications ? '3' : '2') . '">' . TEXT_DEJAR_OPINION . '</u></div>';
			echo '</div>';

			$sSmallDescription = strip_tags( html_entity_decode( $sDescripcionProducto ) );
			echo '<div class="descp-smll">' . truncate( $sSmallDescription, array( 'SIZE' => 230 ) ) . (mb_strlen( $sSmallDescription ) > 230 ? '<a style="color: #3fb4df;" href="javascript:void(0);" data-tabs-anchor="xtabs-1" rel="nofollow" class="prdt-more">' . TEXT_READ_MORE . '</a>' : '') . '</div>';

			if( $aHtmlOption != '' ) {
				echo '<div class="attrs"><p class="attrs-title">' . ATTRIBUTES_TITLE_TEXT . '</p>' . $aHtmlOption. '</div>';
			}
			echo '<div class="wrpr-prco-buy d-flex flex-column-mx">';
				echo '<div class="wrpr-prco">';
					echo ($aProductoInfo['CLASS_OFERTA'] != '' ? '<s>' . $aProductoInfo['PRECIO_ANTERIOR'] . '</s>' : '');
						echo '<div class="prco sequra-product-price-js">' . $aProductoInfo['PRECIO'] . '</div>';
					echo '<div class="tax">' . TEXT_IVA . (mostrarIva() ? '' : ' NO') . ' Incl.</div>';
				echo '</div>';

				echo '<div class="wrpr-buy d-flex">';
						echo '<input data-min="' . $aProductoInfo['products_min_order_qty'] . '" type="text" value="' . $aProductoInfo['products_min_order_qty'] . '" class="cart_quantity" name="cart_quantity">';
						echo '<a href="notify.php?id=' . $aProductoInfo['products_id'] . '" rel="nofollow" class="mgp-ajax buy button-notify" style="display: none;">' . TEXT_AVISEME . '</a>';

						if( preg_match( '/prdt-agtd/i', $aProductoInfo['CLASS'] ) && $aProductoInfo['products_status'] == 1 )
							echo '<a href="notify.php?id=' . $aProductoInfo['products_id'] . '" rel="nofollow" class="mgp-ajax buy button-notify-original">' . TEXT_AVISEME . '</a>';
						else
							echo '<input value="' . (preg_match( '/prdt-agtd/i', $aProductoInfo['CLASS'] ) ? TEXT_SIN_STOCK : IMAGE_BUTTON_RP_BUY_NOW) . '" data-qty="' . (preg_match( '/prdt-agtd/i', $aProductoInfo['CLASS'] ) ? 0 : 1) . '" data-form="true" data-id="' . $aProductoInfo['products_id'] . '" type="submit" class="buy hv9" title="' . IMAGE_BUTTON_RP_BUY_NOW . $aProductoInfo['products_name'] . '"/>';
						echo $dxWishlist->getHtmlIconAdd( $aProductoInfo['products_id'], $aProductoInfo['products_name'], true );
				echo '</div>';
			echo '</div>';

			// Panel interno almacen (debajo del precio) -- solo IP Francobordo
			echo ( isset($sStaffStockPanel) ? $sStaffStockPanel : '' );

			echo '<div class="wrpr-shimg-seqr d-flex">';
				echo '<div class="shimg"' . ($aProductoInfo['PRECIO_RICHSNIPPET'] < 100.00 ? ' style="width:100%!important;"' : '') . '>';
					// Si el producto tiene atributos, mostramos un placeholder hasta que el cliente
					// elija una opción (la rellena el AJAX get-shipping-text desde app.js).
					$bWaitingForOption = ($aHtmlOption != '');
					echo '<div class="ship"' . ($bWaitingForOption ? ' style="color:#f7a521;"' : '') . '>';
						echo '<div class="tt tt-6 icon"></div>';
						echo '<div class="text-shipping">';
							if ($bWaitingForOption) {
								echo '<span>Seleccionar opción para ver disponibilidad</span>';
							} else {
								echo ($bLastStock ? '<b>' . TEXT_ULT_UNID . '</b><br>' : '');
								echo ShippingEstimator::buildText(trim($aProductoInfo['CLASS']));
							}
						echo '</div>';
					echo '</div>';
					if( $aProductoInfo['CLASS_ENVIO'] != '' && !preg_match( '/prdt-agtd/i', $aProductoInfo['CLASS'] ) )
					{
						echo '<a href="portes-gratis-i-17.html" title="' . FREE_SHIPPING_TITLE . '" target="_blank" class="eur">';
							echo '<div class="tt tt-46 icon"></div>';
							echo '<div>' . TEXT_ENVIO_GRATIS . '</div>';
						echo '</a>';
					}
				echo '</div>';
					echo '<div class="seqr">';
						include(DIR_WS_INCLUDES . 'modules/payment/sequra_pp.php');
						if (class_exists('sequra_pp') && $aProductoInfo['products_status'] == 1) {
							sequra_pp::drawTeaser();
						}
						// Banner PayPal Pay Later (justo debajo del teaser Sequra, misma columna) -- solo IP Francobordo
						echo ( isset($sPayPalPayLaterBanner) ? $sPayPalPayLaterBanner : '' );
					echo '</div>';
			echo '</div>';

			echo '<div class="wrpr-infr">';
				if( (int)$aProductoInfo['products_min_order_qty'] > 1 && $aProductoInfo['products_status'] == 1 )
					echo '<div class="row"><b>' . sprintf( TEXT_PEDIDO_MINIMO, $aProductoInfo['products_min_order_qty'] ) . '</div>';

				if( $customer_group_id == '0' && !preg_match( '/prdt-agtd/i', $aProductoInfo['CLASS'] ) )
				{
					if( (USE_POINTS_SYSTEM == 'true') && (DISPLAY_POINTS_INFO == 'true') && (USE_POINTS_FOR_SPECIALS == 'true') || $new_price == false )
						echo '<a rel="nofollow" href="' . tep_href_link( 'my_points_ajax.php?price=' . preg_replace( '/(\&euro\;)/i', '', $aProductoInfo['PRECIO'] ) ) . '" class="row mgp-ajax">' . sprintf( TEXT_PUNTOS_ACUMU, number_format($products_points,POINTS_DECIMAL_PLACES,",",".") ) . '</a>';
				}

				if( !preg_match( '/prdt-agtd/i', $aProductoInfo['CLASS'] ) )
				{
					echo '<div class="row"><b>' . TEXT_DUDA . '</b> <a href="' . tep_href_link( 'resolver_duda.php', 'id=' . $sGetProductsId ) . '" rel="nofollow" class="mgp-ajax">' . TEXT_DUDA_2 . '</a></div>';

					echo '<div class="row">' . TEXT_MEJOR_PRECIO . ' <a href="' . tep_href_link( 'mejorar_precio.php', 'id=' . $sGetProductsId ) . '" rel="nofollow" class="mgp-ajax">' . TEXT_INFORMANOS . '</a></div>';

					$aDescargas = array();

					if( $aProductoInfo['products_pdfupload'] != '' && $aProductoInfo['products_pdfupload'] != 'null' && $aProductoInfo['products_pdfupload'] != 'NULL' )
						$aDescargas[] = '<i class="tt tt-22"></i><a target="_blank" rel="nofollow" href="manuals/' . $aProductoInfo['products_pdfupload'] . '" class="pdfs">' . TEXT_DESCARGAR . '</a> <b>' . TEXT_FICHA_PDF . '</b>';

					$sFileUpload = $aProductoInfo['products_fileupload'] ?? '';
					if( $sFileUpload != '' && $sFileUpload != 'null' && $sFileUpload != 'NULL' && $sFileUpload != 'products_fileupload' && strpos( $sFileUpload, '.' ) !== false )
					{
						// El nombre en BD puede venir doble-codificado (conexión utf8 sobre bytes UTF-8); probar también la variante ISO-8859-1 para casar con el fichero en disco
						$sFileUploadFs = null;
						foreach( array( $sFileUpload, @mb_convert_encoding( $sFileUpload, 'ISO-8859-1', 'UTF-8' ) ) as $sCand )
							if( $sCand !== '' && @file_exists( DIR_WS_IMAGES . 'upload/' . $sCand ) ) { $sFileUploadFs = $sCand; break; }

						if( $sFileUploadFs !== null )
							$aDescargas[] = '<i class="tt tt-22"></i><a target="_blank" rel="nofollow" href="' . DIR_WS_IMAGES . 'upload/' . rawurlencode( $sFileUploadFs ) . '" class="pdfs">' . TEXT_DESCARGAR . '</a> <b>' . TEXT_DOCUMENTACION . '</b>';
					}

					if( $aDescargas )
						echo '<div class="row">' . implode( '<span style="display:inline-block;width:48px;"></span>', $aDescargas ) . '</div>';

					// Archivo CAD descargable EN SU PROPIA FILA (debajo del PDF): preferimos el .zip (mucho mas pequeno)
					$nPidCad = (int)$aProductoInfo['products_id'];
					$sStepZipWeb = DIR_WS_IMAGES . 'productos/3d/' . $nPidCad . '.step.zip';
					$sStepWeb    = DIR_WS_IMAGES . 'productos/3d/' . $nPidCad . '.step';
					if( file_exists( $sStepZipWeb ) )
						echo '<div class="row"><i class="tt tt-22"></i><a href="' . $sStepZipWeb . '" download rel="nofollow" class="pdfs">' . TEXT_DESCARGAR . '</a> <b>Modelo CAD (STEP, .zip)</b></div>';
					elseif( file_exists( $sStepWeb ) )
						echo '<div class="row"><i class="tt tt-22"></i><a href="' . $sStepWeb . '" download rel="nofollow" class="pdfs">' . TEXT_DESCARGAR . '</a> <b>Modelo CAD (STEP)</b></div>';
				}
			echo '</div>';

			// Popup bajo demanda
				echo '<a class="ajx-bjo" href="ajax_bajodemanda.php" class="mgp-ajax" style="display: none;"></a>';

		echo '</div>';

		echo '<input type="hidden" value="' . $aProductoInfo['products_id'] . '" name="products_id">';
	echo '</div>';
echo '</form>';

echo getTableRappels( $sGetProductsId, $aProductoInfo['PRECIO'] );

include( DIR_WS_COMPONENTS . 'bundled_products.php' );

// Atributos
if( $aProductoInfo['products_status'] == 1 )
{
	// Productos relacionados con oferta
	include( DIR_WS_COMPONENTS . 'products_together.php' );
}

echo '<div id="fich-tabs" class="tabs web-cntd">';
	echo '<ul class="xtabs" data-tabs id="home-tab">';
		echo '<li class="xtabs-title actv" data-tabs-link><span class="skew">' . TEXT_DESCRIPTION . '</span></li>';
		if( $bSpecifications )
			echo '<li class="xtabs-title" data-tabs-link><span class="skew">' . TEXT_ESPECIFICACIONES . '</span></li>';
		echo '<li class="xtabs-title" data-tabs-link><span class="skew">' . TEXT_COMMENTS . ' (' . $aProductoInfo['review_count'] . ')</span></li>';
	echo '</ul>';

	echo '<div class="xtabs-content" data-tabs-content="home-tab">';
		echo '<div id="fich-desp" class="xtabs-item fich-infr-text">';
			echo $sDescripcionProducto;
			if( $aProductoInfo['products_youtube'] != '' )
			{
				echo '<div class="video" style="text-align: center;">';
					echo '<iframe width="100%" height="370" style="max-width:600px; margin-top: 20px; margin-bottom: 20px;" src="https://www.youtube.com/embed/' . $aProductoInfo['products_youtube'] . '" frameborder="0" allowfullscreen></iframe>';
				echo '</div>';
			}
		echo '</div>';

		if( $bSpecifications )
		{
			echo '<div class="xtabs-item fich-infr-text">';
				include(DIR_WS_MODULES . 'products_specifications.php');
			echo '</div>';
		}

		echo '<div id="fich-cmmt" class="col a12 ax row atop xtabs-item">';
			include(DIR_WS_COMPONENTS . 'comentarios.php');
		echo '</div>';
	echo '</div>';
echo '</div>';

include( DIR_WS_COMPONENTS . 'repuestos.php' );

	$aBlocks = array();

	include(DIR_WS_COMPONENTS . 'optional_related_products.php');

	$sSell = '';
	if ((USE_CACHE == 'true') && empty($SID))
		$sSell = tep_cache_xsell_products(3600);
	else
		include(DIR_WS_COMPONENTS . FILENAME_XSELL_PRODUCTS);
	if( $sSell != '' )
		$aBlocks['xsell'] = $sSell;

	$sAlso = '';
	if ((USE_CACHE == 'true') && empty($SID))
		$sAlso = tep_cache_also_purchased(3600);
	else
		include(DIR_WS_COMPONENTS . FILENAME_ALSO_PURCHASED_PRODUCTS);
	if( $sAlso != '' )
		$aBlocks['also'] = $sAlso;

	if (count($aBlocks) > 0) {
		echo '<div class="fich-prdt web-cntd ax row">';
		$sClassNum = (count( $aBlocks ) >= 3 ? '4' : '6');
		foreach( $aBlocks as $sBlock )
		{
			echo '<div class="col a0' . $sClassNum . ' t12 m12">';
				echo $sBlock;
			echo '</div>';
		}
		echo '</div>';
	}

$aProducto = $aProductoInfo;
?>
