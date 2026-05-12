<?php
	if( tep_db_num_rows( $aDatos ) <= 0 )
		return false;

	echo '<div class="fich-bg">';
		echo '<div class="web-cntd fich-wrpr-rows">';
			echo '<div class="wrpr-titu">' . PRODUCTS_TOGETHER_TITLE . '</div>';
			echo '<div class="wrpr-rows">';
				while( $aDato = tep_db_fetch_array( $aDatos ) )
				{
					// Datos
					$nTaxRate = ($nCustomerGroupId != 0 ? 0 : tep_get_tax_rate( $aDato['products_tax_class_id'] ));
					$sPrecioRelacionado = tep_add_tax( $aDato['price'], $nTaxRate );
					$sPrecio = $currencies->display_price( $aDato['products_price'], $nTaxRate );
					$sOferta = '';

					// Si tiene oferta
					if( $aDato['products_price_anterior'] != '' )
					{
						$sPrecio = $currencies->display_price( $aDato['products_price'], $nTaxRate );
						$sPrecio = str_replace( array( '&euro;' ), array( '€' ), $sPrecio );
						$sOferta = $currencies->display_price( $aDato['products_price_anterior'], $nTaxRate );
					}

					// Formateamos
					$sPrecioLastFormat = tep_add_tax( $aDato['products_price'], $nTaxRate );
					$sPrecioFormat = $sPrecioRelacionado;
					$sClass = claseBotonComprar( $aDato['products_quantity'], $aDato['check_stock'] );

					// Calculamos porcentaje de oferta
					$nPorcentaje = floatval( $sPrecioFormat * 100 / $sPrecioLastFormat );

					$attributes = [];
					if ($aDato['attributes'] != '') {
						$attributes = json_decode($aDato['attributes'], true);
					}

					echo '<form class="xprdt row d-flex flex-column-tx flex-column-mx align-items-center ' . $sClass . '" onsubmit="return false;" method="post" action="' . tep_href_link('products_info.php', tep_get_all_get_params(array('action')) . 'action=add_product') . '" name="cart_quantity">';
						echo '<div class="col-1 d-flex align-items-center">';
							echo tep_image( DIR_WS_IMAGES . 'productos/' . $aDato['products_image'], $aDato['products_name'], 66, 72, 'class="imge img"', false );
							echo '<div>';
								echo '<a target="_blank" href="' . tep_href_link( FILENAME_PRODUCT_INFO, 'products_id=' . $aDato['products_id'] ) . '" class="titu" title="' . $aDato['products_name'] . '">' . $aDato['products_name'] . '</a>';

								/**
								 * #EXE-972-18979
								 * @author Daniel Lucia <daniel.lucia@denox.es>
								 */
								echo getAttributesSelectHtml(intval($aDato['products_id']), $aDato, $attributes);

							echo '</div>';
						echo '</div>';
						echo '<div class="col-2 d-flex align-items-center">';
							echo '<input data-min="' . $aDato['products_min_order_qty'] . '" type="text" value="' . $aDato['products_min_order_qty'] . '" class="cart_quantity" name="cart_quantity">';

							echo '<div class="prco' . ($sPrecioRelacionado < $sPrecioLastFormat ? ' prdt-ofrt' : '') . ($sPrecioRelacionado <= 0 ? ' free' : '') . '">';
								if  ($sPrecioRelacionado < $sPrecioLastFormat && $sPrecioRelacionado > 0)
									echo '<s>' . $sPrecio . '</s>';

								echo ($sPrecioRelacionado > 0 ? $currencies->display_price( $sPrecioRelacionado, 0 ) : TEXT_GRATIS);
							echo '</div>';

							echo '<input type="hidden" name="products_id" value="' . $aDato['products_id'] . '" />';

							if( preg_match( '/prdt-agtd/i', $sClass ) && $aDato['products_status'] == 1 )
								echo '<a href="notify.php?id=' . $aDato['products_id'] . '" rel="nofollow" class="mgp-ajax bton buy">' . TEXT_AVISEME . '</a>';
							else
								echo '<input type="submit" class="bton buy hv9" data-form="true" data-id="' . $aDato['products_id'] . '" data-atribute="" data-qty="' . (preg_match( '/prdt-agtd/i', $sClass ) ? 0 : 1) . '" value="' . (!preg_match( '/prdt-agtd/i', $sClass ) ? TEXT_ANADIR : TEXT_SIN_STOCK) . '" />';
						echo '</div>';
					echo '</form>';
				}
			echo '</div>';
		echo '</div>';
	echo '</div>';
?>