<?php
	// Obtenemos la imagen GD
	$aImagenGD = glob( getcwd() . '/images/repuestos/' . $sIdProducto . '-imagen-gd.*' );
	
	// Si contenemos imagen
	if( count( $aImagenGD ) > 0 )
	{
		echo '<div class="fich-bg no">';
			echo '<div class="web-cntd fich-wrpr-rows">';
				echo '<div class="wrpr-titu">' . TEXT_REPUESTOS . '</div>';
				echo '<div class="fich-prts"><img src="' . str_replace( getcwd() . '/', '', $aImagenGD[0] ) . '"/></div>';
				echo '<div class="wrpr-rows">';
					while( $aProducto = eachProducts() )
					{
						$sClassComprar = claseBotonComprar($aProducto['products_quantity'], $aProducto['check_stock']);

						// Variables
						$nAdd1 = 0;
						$nAdd2 = 24;
						$sEstimate = '';

						// Entre 2 y 6 días
						if( trim( $sClassComprar ) == 'prdt-4dias' )
						{
							$nAdd1 = ( 24 * 2 );
							$nAdd2 = ( 24 * 6 );
						}
						// Entre 8 y 13 días
						else if( trim( $sClassComprar ) == 'prdt-5dias' )
						{
							$nAdd1 = ( 24 * 8 );
							$nAdd2 = ( 24 * 13 );
						}
						// Bajo pedido / Agotado
						else if( trim( $sClassComprar ) == 'prdt-bjpdd' || trim( $sClassComprar ) == 'prdt-agtd' )
						{
							$nAdd1 = false;
							$nAdd2 = false;
						}

						// Si tenemos predicción
						if( $nAdd1 !== false )
						{
							// Obtenemos las dos estimaciones
							$aEstimate1 = getShippingEstimate( true, false, $nAdd1 );
							$aEstimate2 = getShippingEstimate( true, false, $nAdd2 );

							// Si las fechas son iguales, sumamos un día
							if( $aEstimate1['date'] == $aEstimate2['date'] )
								$aEstimate2 = addHoursToDate( $aEstimate2['year'] . '-' . $aEstimate2['month'] . '-' . $aEstimate2['day'], 24 );

							// Mostramos el mensaje
							$sEstimate = '<span class="cl2">' . str_replace( array( '%s1', '%s2' ), array( dateToSpanish( date( 'l j ' . SHIPPING_PREDICTION_FROM . ' F', strtotime( $aEstimate1['year'] . '-' . $aEstimate1['month'] . '-' . $aEstimate1['day'] ) ) ), dateToSpanish( date( 'l j ' . SHIPPING_PREDICTION_FROM . ' F', strtotime( $aEstimate2['year'] . '-' . $aEstimate2['month'] . '-' . $aEstimate2['day'] ) ) ) ), SHIPPING_PREDICTION_BUY_NOW ) . '.</span>';
						}


						echo '<form class="xprdt row d-flex flex-column-tx flex-column-mx align-items-center ' . $sClassComprar . '" onsubmit="return false;" method="post" action="' . tep_href_link('products_info.php', tep_get_all_get_params(array('action')) . 'action=add_product') . '" name="cart_quantity">';
							echo '<div class="col-1 d-flex align-items-center">';
								echo '<div class="crcl">' . $aProducto['alias'] . '</div>';
								echo '<div>';
									echo '<div class="titu">' . $aProducto['products_name'] . '</div>';
									echo '<div class="ref">';
										echo '<small>Ref.: ' . $aProducto['products_model'] . '</small>';
										echo $sEstimate;
									echo '</div>';

									/**
									 * #EXE-972-18979
									 * @author Daniel Lucia <daniel.lucia@denox.es>
									 */
									$attributes = [];
									if ($aProducto['attributes'] != '') {
										$attributes = json_decode(stripslashes($aProducto['attributes']), true);
									}
									echo getAttributesSelectHtml(intval($aProducto['products_id']), $aProducto, $attributes);

								echo '</div>';
							echo '</div>';
							echo '<div class="col-2 d-flex align-items-center">';
								echo '<input data-min="' . $aProducto['products_min_order_qty'] . '" type="text" value="' . $aProducto['products_min_order_qty'] . '" class="cart_quantity" name="cart_quantity">';
								echo '<div class="prco">' . $aProducto['PRECIO'] . '</div>';
								echo '<input type="hidden" name="products_id" value="' . $aProducto['products_id'] . '" />';
								echo '<input type="submit" class="bton buy hv9" data-form="true" data-id="' . $aProducto['products_id'] . '" data-atribute="" data-qty="' . (preg_match( '/prdt-agtd/i', $sClassComprar ) ? 0 : 1) . '" value="' . (!preg_match( '/prdt-agtd/i', $sClassComprar ) ? TEXT_ANADIR : TEXT_SIN_STOCK) . '" />';
							echo '</div>';
						echo '</form>';

					}
				echo '</div>';
			echo '</div>';
		echo '</div>';
	}
?>