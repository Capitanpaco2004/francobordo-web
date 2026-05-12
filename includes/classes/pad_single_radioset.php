<?php
	require_once(DIR_WS_CLASSES . 'pad_single_dropdown.php');

	class pad_single_radioset extends pad_single_dropdown
	{
		function _draw_stocked_attributes()
		{
			global $languages_id, $sGetProductsId, $customer_group_id, $currencies, $aProductInfoAux, $dxWishlist;

			$attributes = $this->_build_attributes_array(true, false);

			if( count( $attributes ) == 0 )
				return '';

				// Si los atribtos son dos compaginamos
				if( count($attributes) > 1 )
				{
					$tmp_html .= '<div class="wrpr-titu">' . PULL_DOWN_DEFAULT . ' ' . $attributes[0]['oname'] . '</div>';
					$tmp_html .= '<div class="wrpr-rows">';

						foreach( $attributes[0]['ovals'] as $key => $attr1 )
						{
							if( $key == 0 )
								continue;

							// Obtenemos si hay que chequear stock
							$aCheck = tep_db_query( 'SELECT check_stock FROM products WHERE products_id = "' . $sGetProductsId . '";' );
							$aCheck = tep_db_fetch_array( $aCheck );

							$nStock = stock_en_atributos($attr1[0]['oid'], $attr1[0]['id'], $sGetProductsId );
							$sClass = claseBotonComprar( $nStock, $aCheck['check_stock'] );

							$aDatos = tep_db_query( "select pov.products_options_values_id, pov.products_options_values_name, pa.options_values_price, pa.reference, pa.price_prefix, pa.products_attributes_id, pa.options_values_weight, pa.weight_prefix
													 from " . TABLE_PRODUCTS_ATTRIBUTES . " pa
													 inner join " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov on (pa.options_values_id = pov.products_options_values_id)
													 where pa.products_id = '" . (int)$sGetProductsId . "' and pa.options_id = '" . (int)$attributes[0]['oid'] . "' and pov.language_id = '" . (int)$languages_id . "' and find_in_set('".$customer_group_id."', attributes_hide_from_groups) = 0 and products_options_values_id = " . $attr1['id'] . " order by pa.products_options_sort_order");

							$aDato1 = tep_db_fetch_array( $aDatos );
							//Modificamos el precio si el cliente esta en otro grupo
							if ((int)$customer_group_id <> 0) {
							   $sql = 'SELECT products_attributes_id, options_values_price, price_prefix FROM '.TABLE_PRODUCTS_ATTRIBUTES_GROUPS .' WHERE products_id = '.(int)$sGetProductsId.'	AND customers_group_id = '.$customer_group_id .' AND products_attributes_id = '.$aDato1['products_attributes_id'];
							   $products_options_query_group = tep_db_query($sql);
							   while ($products_options_group = tep_db_fetch_array($products_options_query_group)) {
								   $aDato1['options_values_price'] = ($products_options_group['price_prefix'] == "-" ? -1 * abs( $products_options_group['options_values_price'] ) : $products_options_group['options_values_price']);
							   }
						    }

							foreach( $attributes[1]['ovals'] as $key => $attr2 )
							{
								if( $key == 0 )
									continue;

								$aDatos = tep_db_query( "select pov.products_options_values_id, pov.products_options_values_name, pa.options_values_price, pa.reference, pa.price_prefix, pa.products_attributes_id, pa.options_values_weight, pa.weight_prefix
														 from " . TABLE_PRODUCTS_ATTRIBUTES . " pa
														 inner join " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov on (pa.options_values_id = pov.products_options_values_id)
														 where pa.products_id = '" . (int)$sGetProductsId . "' and pa.options_id = '" . (int)$attributes[1]['oid'] . "' and pov.language_id = '" . (int)$languages_id . "' and find_in_set('".$customer_group_id."', attributes_hide_from_groups) = 0 and products_options_values_id = " . $attr2['id'] . " order by pa.products_options_sort_order");
								$aDato2 = tep_db_fetch_array( $aDatos );

								$tmp_html .= '<div class="row d-flex flex-column-tx flex-column-mx align-items-center ' . $sClass . '">';
									$tmp_html .= '<div class="col-1 d-flex align-items-center"><div>';
										$tmp_html .= '<div class="titu">' . $attr1['text'] . ' ' . $attr2['text'] . '</div>';

										if( $aDato1['reference'] != '' || $aDato2['reference'] != '' )
											$tmp_html .= '<div class="ref"><small>Ref.: ' . $aDato1['reference'] . ' ' . $aDato2['reference'] . '</small></div>';

									$tmp_html .= '</div></div>';

									$ofertas_price_atributos = (tep_get_products_special_price($sGetProductsId));

									$tmp_html .= tep_draw_form('cart_quantity', tep_href_link('aProductInfoAux.php', tep_get_all_get_params(array('action')) . 'action=add_product'), 'post', 'onsubmit="return false;" class="xprdt ' . $sClass . ' col-2 d-flex align-items-center"');
											$tmp_html .= '<input data-min="' . $aProductInfoAux['products_min_order_qty'] . '" type="text" value="' . $aProductInfoAux['products_min_order_qty'] . '" class="cart_quantity" name="cart_quantity">';

											$tmp_html .= '<div class="prco' . ($ofertas_price_atributos>0 ? ' prdt-ofrt' : '') . '">';
												$nPrecioModificar = 0;

												if( $aDato1['options_values_price'] != 0 )
													$nPrecioModificar += $aDato1['options_values_price'];

												if( $aDato2['options_values_price'] != 0 )
													$nPrecioModificar += $aDato1['options_values_price'];

												if( $nPrecioModificar != '0' )
												{
													if ($ofertas_price_atributos>0)
													{
														$nOferta = $currencies->display_price($nPrecioModificar+$ofertas_price_atributos, tep_get_tax_rate($aProductInfoAux['products_tax_class_id']));

														$tmp_html .= '<s>' . $nOferta . '</s>';
													}

													if( strpos( $aProductInfoAux['products_model'], 'CAG') !== FALSE or strpos( $aProductInfoAux['products_model'], 'CAA' ) !== FALSE )
													{
														$nPrecio = $currencies->display_price($nPrecioModificar+$aProductInfoAux['products_price'], tep_get_tax_rate($aProductInfoAux['products_tax_class_id']));

														$tmp_html .= $nPrecio;
													}
													else
													{
														$nPrecio = $currencies->display_price($nPrecioModificar+$aProductInfoAux['products_price'], tep_get_tax_rate($aProductInfoAux['products_tax_class_id']));

														$tmp_html .= $nPrecio;
													}
												}
												else
												{
													if( $ofertas_price_atributos > 0 )
													{
														$nOferta = $currencies->display_price($ofertas_price_atributos, tep_get_tax_rate($aProductInfoAux['products_tax_class_id']));

														$tmp_html .= '<s>' . $nOferta . '</s>';
													}

													if( strpos($aProductInfoAux['products_model'],'CAG') !== FALSE or strpos($aProductInfoAux['products_model'],'CAA') !== FALSE )
													{
														$nPrecio = $currencies->display_price($aProductInfoAux['products_price'], tep_get_tax_rate($aProductInfoAux['products_tax_class_id']));

														$tmp_html .= $nPrecio;
													}
													else
													{
														$nPrecio = $currencies->display_price($aProductInfoAux['products_price'], tep_get_tax_rate($aProductInfoAux['products_tax_class_id']));

														$tmp_html .= $nPrecio;
													}
												}
											$tmp_html .= '</div>';

											$tmp_html .= '<input type="hidden" name="products_id" value="' . $sGetProductsId . '" />';

											$tmp_html .= tep_draw_radio_field( 'id[' . $attributes[0]['oid'] . ']', $attr1['id'], true, 'style="display:none;"' );
											$tmp_html .= tep_draw_radio_field( 'id[' . $attributes[1]['oid'] . ']', $attr2['id'], true, 'style="display:none;"' );
											$tmp_html .= '<input type="submit" class="bton buy" data-form="true" data-id="' . $sGetProductsId . '" data-atribute="" data-qty="' . (preg_match( '/prdt-agtd/i', $sClass ) ? 0 : 1) . '" value="' . TEXT_ANADIR . '" />';
											$tmp_html .= $dxWishlist->getHtmlIconAdd( $sGetProductsId, $attr1['text'] . ' ' . $attr2['text'], true, 1 );
									$tmp_html .= '</form>';
								$tmp_html .= '</div>';

							}
						}

					$tmp_html .= '</div>';

					return $tmp_html;
				}


				foreach( $attributes as $attr )
				{
					$products_options_query = tep_db_query( "select pov.products_options_values_id, pov.products_options_values_name, pov.products_options_hexcolor, pa.options_values_price, pa.reference, pa.price_prefix, pa.products_attributes_id, pa.options_values_weight, pa.weight_prefix
															 from " . TABLE_PRODUCTS_ATTRIBUTES . " pa
															 inner join " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov on (pa.options_values_id = pov.products_options_values_id)
															 where pa.products_id = '" . (int)$sGetProductsId . "' and pa.options_id = '" . (int)$attr['oid'] . "' and pov.language_id = '" . (int)$languages_id . "' and find_in_set('".$customer_group_id."', attributes_hide_from_groups) = 0 order by pa.products_options_sort_order");

					if( !isset( $cart->contents[$sGetProductsId]['attributes'][$attr['oid']] ) )
						$no_value = true;

					$tmp_html .= '<div class="wrpr-titu">' . PULL_DOWN_DEFAULT . ' ' . $attr['oname'] . '</div>';
					$tmp_html .= '<div class="wrpr-rows">';

					while( $products_options_array = tep_db_fetch_array($products_options_query) )
					{
						//Modificamos el precio si el cliente esta en otro grupo
						if ((int)$customer_group_id <> 0) {
						   $sql = 'SELECT products_attributes_id, options_values_price, price_prefix FROM '.TABLE_PRODUCTS_ATTRIBUTES_GROUPS .' WHERE products_id = '.(int)$sGetProductsId.'	AND customers_group_id = '.$customer_group_id .' AND products_attributes_id = '.$products_options_array['products_attributes_id'];
						   $products_options_query_group = tep_db_query($sql);
						   while ($products_options_group = tep_db_fetch_array($products_options_query_group)) {
							   $products_options_array['options_values_price'] = ($products_options_group['price_prefix'] == "-" ? -1 * abs( $products_options_group['options_values_price'] ) : $products_options_group['options_values_price']);
						   }
						}

						if( $products_options_array['products_options_values_id'] == $cart->contents[$sGetProductsId]['attributes'][$attr['oid']] || $no_value )
							$no_value = false;

						// Obtenemos si hay que chequear stock
						$aCheck = tep_db_query( 'SELECT check_stock FROM products WHERE products_id = "' . $sGetProductsId . '";' );
						$aCheck = tep_db_fetch_array( $aCheck );

						$nStock = stock_en_atributos($attr['oid'], $products_options_array['products_options_values_id'], $sGetProductsId );
						$sClass = claseBotonComprar( $nStock, $aCheck['check_stock'] );

						// Variables
						$nAdd1 = 0;
						$nAdd2 = 24;
						$sEstimate = '';

						// Entre 2 y 6 días
						if( trim( $sClass ) == 'prdt-4dias' )
						{
							$nAdd1 = ( 24 * 2 );
							$nAdd2 = ( 24 * 6 );
						}
						// Entre 8 y 13 días
						else if( trim( $sClass ) == 'prdt-5dias' )
						{
							$nAdd1 = ( 24 * 8 );
							$nAdd2 = ( 24 * 13 );
						}
						// Bajo pedido / Agotado
						else if( trim( $sClass ) == 'prdt-bjpdd' || trim( $sClass ) == 'prdt-agtd' )
						{
							$nAdd1 = false;
							$nAdd2 = false;
							$sEstimate = '<span class="cl2">' . ucfirst( (trim( $sClass ) == 'prdt-bjpdd' ? TEXT_BAJO_PEDIDO : TEXT_SIN_STOCK) ) . '</span>';
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

						$tmp_html .= '<div class="row d-flex flex-column-tx flex-column-mx align-items-center ' . $sClass . '">';
							$tmp_html .= '<div class="col-1 d-flex align-items-center">';
								if( $products_options_array['products_options_hexcolor'] != '' )
								{
									$aHex = explode( ', ', $products_options_array['products_options_hexcolor'] );
									$nPorcentColors = 100 / count( $aHex ); // 33%
									$nPorcent = 0;

									for( $nCont2 = 0; $nCont2 < count( $aHex ); ++$nCont2 )
									{
										$sColor .= $aHex[$nCont2] . ' ' . $nPorcent . '%,';
										$nPorcent += $nPorcentColors;
										$sColor .= $aHex[$nCont2] . ' ' . $nPorcent . '%,';
										
									}

									list($sRed, $sGreen, $sBlue) = sscanf($aHex[0], "#%02x%02x%02x");

									$tmp_html .= '<div class="crcl-clor" style="background: ' . (count( $aHex ) > 1 ? 'linear-gradient(90deg, ' . substr( $sColor, 0, -1 ) . ')' : $aHex[0] . ($sRed >= 190 && $sGreen >= 190 && $sBlue >= 190 ? '; color: #000' : '')) . ';"></div>';
								}
								$tmp_html .= '<div>';
									$tmp_html .= '<div class="titu">' . $products_options_array['products_options_values_name'] . '</div>';

									$tmp_html .= '<div class="ref">';
									if( $products_options_array['reference'] != '' )
										$tmp_html .= '<small>Ref.: ' . $products_options_array['reference'] . '</small>';
									if( $sEstimate != '' )
										$tmp_html .= $sEstimate;
									$tmp_html .= '</div>';
								$tmp_html .= '</div>';
							$tmp_html .= '</div>';

							$ofertas_price_atributos = (tep_get_products_special_price($sGetProductsId));

							$tmp_html .= tep_draw_form('cart_quantity', tep_href_link('products_info.php', tep_get_all_get_params(array('action')) . 'action=add_product'), 'post', 'onsubmit="return false;" class="xprdt ' . $sClass . ' col-2 d-flex align-items-center"');
								$tmp_html .= '<input data-min="' . $aProductInfoAux['products_min_order_qty'] . '" type="text" value="' . $aProductInfoAux['products_min_order_qty'] . '" class="cart_quantity" name="cart_quantity">';

								$tmp_html .= '<div class="prco' . ($ofertas_price_atributos>0 ? ' prdt-ofrt' : '') . '">';
									if( $products_options_array['options_values_price'] != '0' )
									{
										$products_options_array['options_values_price'] = ($products_options_array['price_prefix'] == "-" ? -1 * abs( $products_options_array['options_values_price'] ) : $products_options_array['options_values_price']);
										if ($ofertas_price_atributos>0)
										{
											$nOferta = $currencies->display_price($products_options_array['options_values_price']+$ofertas_price_atributos, tep_get_tax_rate($aProductInfoAux['products_tax_class_id']));

											$tmp_html .= '<s>' . $nOferta . '</s>';
										}

										if( strpos( $aProductInfoAux['products_model'], 'CAG') !== FALSE or strpos( $aProductInfoAux['products_model'], 'CAA' ) !== FALSE )
										{
											$nPrecio = $currencies->display_price($products_options_array['options_values_price']+$aProductInfoAux['products_price'], tep_get_tax_rate($aProductInfoAux['products_tax_class_id']));

											$tmp_html .= $nPrecio;
										}
										else
										{
											$nPrecio = $currencies->display_price($products_options_array['options_values_price']+$aProductInfoAux['products_price'], tep_get_tax_rate($aProductInfoAux['products_tax_class_id']));

											$tmp_html .= $nPrecio;
										}
									}
									else
									{
										if( $ofertas_price_atributos > 0 )
										{
											$nOferta = $currencies->display_price($ofertas_price_atributos, tep_get_tax_rate($aProductInfoAux['products_tax_class_id']));

											$tmp_html .= '<s>' . $nOferta . '</s>';
										}

										if( strpos($aProductInfoAux['products_model'],'CAG') !== FALSE or strpos($aProductInfoAux['products_model'],'CAA') !== FALSE )
										{
											$nPrecio = $currencies->display_price($aProductInfoAux['products_price'], tep_get_tax_rate($aProductInfoAux['products_tax_class_id']));

											$tmp_html .= $nPrecio;
										}
										else
										{
											$nPrecio = $currencies->display_price($aProductInfoAux['products_price'], tep_get_tax_rate($aProductInfoAux['products_tax_class_id']));

											$tmp_html .= $nPrecio;
										}
									}
								$tmp_html .= '</div>';

								$tmp_html .= '<input type="hidden" name="products_id" value="' . $sGetProductsId . '" />';
								$tmp_html .= tep_draw_radio_field( 'id[' . $attr['oid'] . ']', $products_options_array['products_options_values_id'], true, 'style="display:none;"' );
								$tmp_html .= '<input type="submit" class="bton buy' . (preg_match( '/prdt-agtd/i', $sClass ) ? '' : ' img') . '" data-form="true" data-id="' . $sGetProductsId . '" data-atribute="" data-qty="' . (preg_match( '/prdt-agtd/i', $sClass ) ? 0 : 1) . '" value="' . (!preg_match( '/prdt-agtd/i', $sClass ) ? TEXT_ANADIR : TEXT_SIN_STOCK) . '" />';
								$tmp_html .= $dxWishlist->getHtmlIconAdd( $sGetProductsId, $products_options_array['products_options_values_name'], true, 1, $attr['oid'] . '-' . $products_options_array['products_options_values_id'] );
								
								// Popup bajo demanda
								if( preg_match( '/prdt-bjpdd/i', $sClass ) )
									$tmp_html .= '<a class="ajx-bjo" href="ajax_bajodemanda.php" class="mgp-ajax" style="display: none;"></a>';
							$tmp_html .= '</form>';
						$tmp_html .= '</div>';
					}

					$opciones_cont = $opciones_cont + 1;
					$tmp_html .= '</div>';
				}

			return $tmp_html;
		}
	}
?>
