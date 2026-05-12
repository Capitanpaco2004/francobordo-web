<?php
// Pintamos el formulario
$sHtmlModule .= '<table border="0" width="100%"><tr><td>';
			$sHtmlModule .= '<div id="box-left">';
				$sHtmlModule .= '<ul class="nav">';
					$sHtmlModule .= '<li><a href="javascript:void(0);" data-id="4" class="active"><img src="images/icons/icon_landing.png" alt="Landing"><span>Landing</span></a></li>';
					$sHtmlModule .= '<li><a href="javascript:void(0);" data-id="1" class=""><img src="images/icons/icon_promotion.png" alt="Promoción"><span>Detalles Promo</span></a></li>';
					$sHtmlModule .= '<li><a href="javascript:void(0);" data-id="2" class=""><img src="images/icons/icon_product.png" alt="Productos promoción"><span>Productos Activadores Promoción</span></a></li>';
					$sHtmlModule .= '<li><a href="javascript:void(0);" data-id="3" class=""><img src="images/icons/icon_product.png" alt="Productos descuento"><span>Productos Descuento</span></a></li>';
					$sHtmlModule .= '</ul>';
				$sHtmlModule .= '</div>';

			$sHtmlModule .= '<div id="box-right">';

				// Si tenemos un error
				if( $bError )
				$sHtmlModule .= '<div class="msje msje-eror"><div class="msje-icon"></div>Ha ocurrido un error, revise por favor los campos requeridos.</div>';

				$sHtmlModule .= tep_draw_form( 'promotions', FILENAME_PROMOTIONS . '?a=' . (isset( $_GET['promotion'] ) ? 'edit' : 'insert') . (isset( $sGetPage ) ? '&page=' . $sGetPage : '') . (isset( $_GET['promotion'] ) ? '&promotion=' . $_GET['promotion'] : ''), '', 'post', 'id="promotions" enctype="multipart/form-data"', '' );
				$sHtmlModule .= '<table width="100%" cellspacing="0" border="0">';
					$sHtmlModule .= '<tbody>';
					$sHtmlModule .= '<tr>';
						$sHtmlModule .= '<td>';
							$sHtmlModule .= '<div>';
								$sHtmlModule .= '<div class="toolbarHead">';
									$sHtmlModule .= '<div class="hdr-tlbr">';
										$sHtmlModule .= '<h1 class="pageHeading" style="top: 12px;">Promoción</h1>';
										$sHtmlModule .= '<div class="btn-right">';
											$sHtmlModule .= '<a title="Guardar cambios" onclick="$(\'#promotions\').submit();" href="javascript:void(0);"><img src="images/icons/icon_save.png" class="dx-hovr"></a>';
											$sHtmlModule .= '<a href="' . FILENAME_PROMOTIONS . (isset( $sGetPage ) ? '?page=' . $sGetPage : '') . '"><img title="Volver sin guardar" src="images/icons/icon_back.png" class="dx-hovr"></a>';
											$sHtmlModule .= '</div>';
										$sHtmlModule .= '</div>';
									$sHtmlModule .= '</div>';
								$sHtmlModule .= '</div>';
							$sHtmlModule .= '</td>';
						$sHtmlModule .= '</tr>';

					$sHtmlModule .= '<!-- PROMOCION -->';
					$sHtmlModule .= '<tr data-id="1" style="display: none;" class="tab-new">';
						$sHtmlModule .= '<td style="display:block;">';
							$sHtmlModule .= '<div class="fluid grid">';
								$sHtmlModule .= '<div class="box-tbl grid12">';
									$sHtmlModule .= '<div class="box-head">';
										$sHtmlModule .= '<h6>Datos de la promoción</h6>';
										$sHtmlModule .= '<div class="clear"></div>';
										$sHtmlModule .= '</div>';

									$sHtmlModule .= '<div class="formRow">';
										$sHtmlModule .= '<div class="grid1"><label>Título:</label></div>';
										$sHtmlModule .= '<div class="grid11">' . tep_draw_input_field( 'promotion_name', (isset( $sName ) ? $sName : '') ) . '<span class="note">' . ($sErrorName !== false ? '<font color="red">' . $sErrorName . '</font>' : '<font color="green">Nombre de la promoción, solo visible para el administrador.</font>') . '</span></div>';
										$sHtmlModule .= '<div class="clear"></div>';
										$sHtmlModule .= '</div>';
									// Descuento
									$sHtmlModule .= '<div class="formRow">';
										$sHtmlModule .= '<div class="grid1"><label>Descuento:</label></div>';

										// atributos según tipo de descuento
										$attrDiscount = ($sType == 'percent')
										? 'min="0" max="100" step="1"'
										: 'min="0" step="0.01"';

										$sHtmlModule .= '<div class="grid1">'
											. tep_draw_input_field('promotion_discount_percent', (string)$nPercent, $attrDiscount . ' style="width: 115px; height: 18px; margin-top: 7px"', false, 'number')
											. '<span class="note">'
			. ($sErrorPercent !== false
				? '<font color="red">' . $sErrorPercent . '</font>'
				: '<font color="green">Introduce el porcentaje de descuento: Desde 1% hasta el 100%.<br />ó<br />Introduce un valor fijo en euros.</font>')
			. '</span></div>';

										$sHtmlModule .= '<div class="grid1">'
											. '<select name="promotion_discount_type" id="discount_type" style="position: relative; top: 2px;">'
												. '<option value="percent"' . ($sType == 'percent' ? ' selected="selected"' : '') . '>%</option>'
												. '<option value="fixed"'   . ($sType == 'fixed'   ? ' selected="selected"' : '') . '>€</option>'
												. '</select></div>';
										$sHtmlModule .= '<div class="clear"></div>';
										$sHtmlModule .= '</div>';

									// Cantidad
									$sHtmlModule .= '<div class="formRow">';
										$sHtmlModule .= '<div class="grid1"><label>Cantidad:</label></div>';
										$sHtmlModule .= '<div class="grid1">'
											. tep_draw_input_field('promotion_quantity', (string)$nQuantity, 'min="1" step="1"', false, 'number')
											. '<span class="note">'
											. ($sErrorQuantity !== false
												? '<font color="red">' . $sErrorQuantity . '</font>'
												: '<font color="green">Introduce la cantidad de productos necesaria para aplicar la promoción.</font>')
											. '</span></div>';
										$sHtmlModule .= '<div class="clear"></div>';
										$sHtmlModule .= '</div>';

										$sHtmlModule .= '<div class="formRow">';
											$sHtmlModule .= '<div class="grid2"><label>Aplicar descuento a todos:
											<small class="note">
											Por defecto, aunque selecciones varios productos en “Productos con descuento”, el descuento solo se aplicará a uno (el más barato).
											<br>
											Si activas esta opción, el descuento se aplicará a <strong>todos los productos con descuento</strong> configurados en la promoción.
											<br><br>
											Ejemplo: Promo “Compra 1 producto A → 50% en pantalón y pulsera”.
											<br>- Sin marcar: se descuenta solo la pulsera (el más barato).
											<br>- Marcado: se descuentan pantalón y pulsera.</small></label></div>';
											$sHtmlModule .= '<div class="grid1" style="margin: 0;">' . tep_draw_checkbox_field('promotion_all', 1, (isset($nToAll) ? $nToAll : 0), '', 'id="prall"') . '</div>';
											$sHtmlModule .= '<div class="clear"></div>';
										$sHtmlModule .= '</div>';

										$sHtmlModule .= '<div class="formRow">';
											$sHtmlModule .= '<div class="grid2"><label>Extender la promoción:
											<small class="note">
											Por defecto, aunque compres más cantidad de la mínima requerida, la promoción solo se aplica una vez.
											<br>
											Si activas esta opción, la promoción se aplicará <strong>tantas veces como múltiplos</strong> de la cantidad mínima cumplas.
											<br><br>
											Ejemplo: Promo “Compra 2 camisetas → 10 € de descuento en un cinturón”.
											<br>- Sin marcar: si compras 4 camisetas, la promoción se aplica una vez (10 €).
											<br>- Marcado: si compras 4 camisetas, la promoción se aplica dos veces (20 €).</small></label></div>';
											$sHtmlModule .= '<div class="grid1" style="margin: 0;">' . tep_draw_checkbox_field('promotion_extend', 1, (isset($nExtend) ? $nExtend : 0), '', 'id="prext"') . '</div>';
											$sHtmlModule .= '<div class="clear"></div>';
										$sHtmlModule .= '</div>';


									$sHtmlModule .= '<div class="clear"></div>';
									$sHtmlModule .= '</div>';
								$sHtmlModule .= '</div>';
							$sHtmlModule .= '</td>';
						$sHtmlModule .= '</tr>';
					$sHtmlModule .= '<!-- FIN PROMOCION -->';

					$sHtmlModule .= '<!-- FILTRO PRODUCTOS PROMOCIÓN -->';
					$sHtmlModule .= '<tr data-id="2" style="display: none;" class="tab-new">';
						$sHtmlModule .= '<td style="display:block;">';
							$sHtmlModule .= '<div class="fluid grid">';
								$sHtmlModule .= '<div class="box-tbl grid12">';
									$sHtmlModule .= '<div class="box-head">';
										$sHtmlModule .= '<h6>I<font color="#4fc22b">Productos</font>, <font color="#881111">Categorías</font> o <font color="#2b78c2">Marcas</font> que al comprar activan la promoción</h6>';
										$sHtmlModule .= '<div class="clear"></div>';
										$sHtmlModule .= '</div>';

									$sHtmlModule .= '<div class="formRow">';
										$sHtmlModule .= '<div class="grid1"><label>Buscar productos,<br /> categorías o marcas:</label></div>';
										$sHtmlModule .= '<div class="grid11">';
											$sHtmlModule .= '<img class="img-load" src="images/loading_small.gif" style="display: none;" />';
											$sHtmlModule .= '<input type="text" id="products_search" value="" name="products_search" automplete="off"><span class="note">Escribe el nombre del producto, categoría, marca o tipo de producto que quieres añadir</span>';
											$sHtmlModule .= '</div>';
										$sHtmlModule .= '<div class="clear"></div>';
										$sHtmlModule .= '</div>';
									$sHtmlModule .= '</div>';
								$sHtmlModule .= '</div>';

							$sHtmlModule .= '<div class="fluid grid">';
								$sHtmlModule .= '<div class="box-tbl grid6">';
									$sHtmlModule .= '<div class="box-head">';
										$sHtmlModule .= '<h6>Listado filtrado:</h6>';
										$sHtmlModule .= '<input class="buttonS bGreen" type="button" id="btn-prd" value="Añadir todos" style="float: right; margin-right: 10px;">';
										$sHtmlModule .= '<div class="clear"></div>';
										$sHtmlModule .= '</div>';

									$sHtmlModule .= '<div class="formRow" id="campos-prd">';
										$sHtmlModule .= '<ul id="rows-drag-prd">';
											$sHtmlModule .= '</ul>';
										$sHtmlModule .= '</div>';
									$sHtmlModule .= '</div>';

								$sHtmlModule .= '<div class="box-tbl grid6">';
									$sHtmlModule .= '<div class="box-head">';
										$sHtmlModule .= '<h6>Productos, categorías o marcas que se añadirán</h6>';
										$sHtmlModule .= '<input class="buttonS bRed" type="button" id="btn-prd-clear" value="Quitar todos" style="float: right; margin-right: 10px;">';

										$sHtmlModule .= '<div class="clear"></div>';
										$sHtmlModule .= '</div>';
									$sHtmlModule .= '<div class="formRow" id="drop-rows-cntd-prd">';
										$sHtmlModule .= '<ul id="drop-rows-prd" class="ui-droppable ui-sortable ' . (count( $aElementsPromotion ) > 0 ? '' : 'drop-empty') . '">';
											foreach( $aElementsPromotion as $aElement )
											$sHtmlModule .= '<li data-id="' . $aElement['id'] . '" data-drop="true">' . $aElement['display'] . '<input type="hidden" name="row-prd[]" value="' . $aElement['id'] . '"><input type="hidden" name="row-prd-name[]" value="' . $aElement['name'] . '"><input type="hidden" name="row-prd-type[]" value="' . $aElement['type'] . '"></li>';
											$sHtmlModule .= '</ul>';
										$sHtmlModule .= '</div>';
									$sHtmlModule .= '</div>';
								$sHtmlModule .= '</div>';
							$sHtmlModule .= '</td>';
						$sHtmlModule .= '</tr>';
					$sHtmlModule .= '<!-- FIN FILTRO PRODUCTOS PROMOCIÓN -->';

					$sHtmlModule .= '<!-- FILTRO PRODUCTOS DESCUENTO -->';
					$sHtmlModule .= '<tr data-id="3" style="display: none;" class="tab-new">';
						$sHtmlModule .= '<td style="display:block;">';
							$sHtmlModule .= '<div class="fluid grid">';
								$sHtmlModule .= '<div class="box-tbl grid12">';
									$sHtmlModule .= '<div class="box-head">';
										$sHtmlModule .= '<h6><font color="#4fc22b">Productos</font>, <font color="#881111">categorías</font> o <font color="#2b78c2">marcas</font> a las que se aplica el descuento cuando se cumple la promoción</h6>';
										$sHtmlModule .= '<div class="clear"></div>';
										$sHtmlModule .= '</div>';

									$sHtmlModule .= '<div class="formRow">';
										$sHtmlModule .= '<div class="grid1"><label>Buscar productos,<br /> categorías o marcas:</label></div>';
										$sHtmlModule .= '<div class="grid11">';
											$sHtmlModule .= '<img class="img-load" src="images/loading_small.gif" style="display: none;" />';
											$sHtmlModule .= '<input type="text" id="products_search_2" value="" name="products_search_2" automplete="off"><span class="note">Escribe el nombre del producto, categoría, marca o tipo de producto que quieres añadir</span>';
											$sHtmlModule .= '</div>';
										$sHtmlModule .= '<div class="clear"></div>';
										$sHtmlModule .= '</div>';
									$sHtmlModule .= '</div>';
								$sHtmlModule .= '</div>';

							$sHtmlModule .= '<div class="fluid grid">';
								$sHtmlModule .= '<div class="box-tbl grid6">';
									$sHtmlModule .= '<div class="box-head">';
										$sHtmlModule .= '<h6>Listado filtrado:</h6>';
										$sHtmlModule .= '<input class="buttonS bGreen" type="button" id="btn-prd2" value="Añadir todos" style="float: right; margin-right: 10px;">';
										$sHtmlModule .= '<div class="clear"></div>';
										$sHtmlModule .= '</div>';

									$sHtmlModule .= '<div class="formRow" id="campos-prd2">';
										$sHtmlModule .= '<ul id="rows-drag-prd2">';
											$sHtmlModule .= '</ul>';
										$sHtmlModule .= '</div>';
									$sHtmlModule .= '</div>';

								$sHtmlModule .= '<div class="box-tbl grid6">';
									$sHtmlModule .= '<div class="box-head">';
										$sHtmlModule .= '<h6>Productos, categorías o marcas que se añadirán</h6>';
										$sHtmlModule .= '<input class="buttonS bRed" type="button" id="btn-prd2-clear" value="Quitar todos" style="float: right; margin-right: 10px;">';
										$sHtmlModule .= '<div class="clear"></div>';
										$sHtmlModule .= '</div>';
									$sHtmlModule .= '<div class="formRow" id="drop-rows-cntd-prd2">';
										$sHtmlModule .= '<ul id="drop-rows-prd2" class="ui-droppable ui-sortable ' . (count( $aElementsDiscount ) > 0 ? '' : 'drop-empty') . '">';
											foreach( $aElementsDiscount as $aElement )
											$sHtmlModule .= '<li data-id="' . $aElement['id'] . '" data-drop="true">' . $aElement['display'] . '<input type="hidden" name="row-prd2[]" value="' . $aElement['id'] . '"><input type="hidden" name="row-prd2-name[]" value="' . $aElement['name'] . '"><input type="hidden" name="row-prd2-type[]" value="' . $aElement['type'] . '"></li>';
											$sHtmlModule .= '</ul>';
										$sHtmlModule .= '</div>';
									$sHtmlModule .= '</div>';
								$sHtmlModule .= '</div>';
							$sHtmlModule .= '</td>';
						$sHtmlModule .= '</tr>';
					$sHtmlModule .= '<!-- FIN FILTRO PRODUCTOS DESCUENTO -->';

					$sHtmlModule .= '<!-- LANDING -->';
					$sHtmlModule .= '<tr data-id="4" style="display: block;" class="tab-new">';
						$sHtmlModule .= '<td style="display:block;">';
							$sHtmlModule .= '<div class="fluid grid">';
								$sHtmlModule .= '<div class="box-tbl grid12">';
									$sHtmlModule .= '<div class="box-head">';
										$sHtmlModule .= '<h6>Datos de la landing</h6>';
										$sHtmlModule .= '<div class="clear"></div>';
										$sHtmlModule .= '</div>';

									$sHtmlModule .= '<div id="dxctgrtab">';
										$sHtmlModule .= '<div class="grid12">';
											$sHtmlModule .= '<span class="dxctgrtab-title">Idioma:</span>';
											$sHtmlModule .= '<div class="tab-pane" id="tabPane1">';
												$sHtmlModule .= '<script type="text/javascript">tp1 = new WebFXTabPane( document.getElementById( "tabPane1" ) );</script>';

												for( $i=0; $i < sizeof( $languages ); $i++ )
												{
												$sHtmlModule .= '<div id="' . $languages[$i]['name'] . '">';
													$sHtmlModule .= '<h2 class="tab"><nobr>' . tep_image( DIR_WS_CATALOG_LANGUAGES . $languages[$i]['directory'] . '/images/bandera.png', $languages[$i]['name'], 'align="absmiddle"' ) . '</h2>';
													$sHtmlModule .= '<script type="text/javascript">tp1.addTabPage( document.getElementById( "' . $languages[$i]['name'] . '" ) );</script>';
													$sHtmlModule .= '<div class="formRow">';
														$sHtmlModule .= '<div class="grid2"><label>Título:</label></div>';
														$sHtmlModule .= '<div class="grid10">' . tep_draw_input_field( 'landing_title[' . $languages[$i]['id'] . ']', (isset($sTitle[$languages[$i]['id']]) ? $sTitle[$languages[$i]['id']] : '') ) . '<span class="note">' . (isset( $sErrorTitle[$languages[$i]['id']] ) && $sErrorTitle[$languages[$i]['id']] !== false ? '<font color="red">' . $sErrorTitle[$languages[$i]['id']] . '</font>' : '') . '</span></div>';
														$sHtmlModule .= '<div class="clear"></div>';
														$sHtmlModule .= '</div>';
													$sHtmlModule .= '<div class="formRow">';
														$sHtmlModule .= '<div class="grid2"><label>Imagen de la cabecera:</label></div>';
														$sHtmlModule .= '<div class="grid3">' . tep_draw_file_field( 'landing_image[' . $languages[$i]['id'] . ']' ) . '<span class="note">' . ($sErrorImage[$languages[$i]['id']] !== false ? '<font color="red">' . $sErrorImage[$languages[$i]['id']] . '</font>' : '' ) . '</span></div>';
														if( isset($sImage[$languages[$i]['id']]) && $sImage[$languages[$i]['id']] != '' && file_exists( DIR_FS_CATALOG_IMAGES . 'landings/' . $sImage[$languages[$i]['id']] ) )
														$sHtmlModule .= '<div class="grid1" style="top: 2px;left: -25px;"><a href="/images/landings/' . $sImage[$languages[$i]['id']] . '" title="Ver imagen de cabecera" target="_blank"><img src="images/icons/picture_icon.png" alt="Imagen de cabecera" /></a></div>';
														$sHtmlModule .= '<div class="clear"></div>';
														$sHtmlModule .= '</div>';
													$sHtmlModule .= '<div class="formRow">';
														$sHtmlModule .= '<div class="grid2"><label>Contenido:</label></div>';
														$sHtmlModule .= '<div class="grid10">' . tep_draw_textarea_field_tinymce( 'landing_description[' . $languages[$i]['id'] . ']', 'soft', 70, 20, (isset($sDescription[$languages[$i]['id']]) ? stripslashes( $sDescription[$languages[$i]['id']] ) : '') ) . '<span class="note">' . ($sErrorDescription[$languages[$i]['id']] !== false ? '<font color="red">' . $sErrorDescription[$languages[$i]['id']] . '</font>' : '') . '</span></div>';
														$sHtmlModule .= '<div class="clear"></div>';
														$sHtmlModule .= '</div>';
													$sHtmlModule .= '<div class="formRow">';
														$sHtmlModule .= '<div class="grid2"><label>Video:</label></div>';
														$sHtmlModule .= '<div class="grid10">' . tep_draw_input_field( 'landing_video[' . $languages[$i]['id'] . ']', (isset($sVideo[$languages[$i]['id']]) ? $sVideo[$languages[$i]['id']] : '') ) . '<span class="note">' . ($sErrorVideo[$languages[$i]['id']] !== false ? '<font color="red">' . $sErrorVideo[$languages[$i]['id']] . '</font>' : '') . '</span></div>';
														$sHtmlModule .= '<div class="clear"></div>';
														$sHtmlModule .= '</div>';
													$sHtmlModule .= '</div>';
												}

												$sHtmlModule .= '</div>';

											$sHtmlModule .= '<script type="text/javascript">setupAllTabs();</script>';

											$sHtmlModule .= '<div class="formRow">';
												$sHtmlModule .= '<div class="grid2"><label>Fecha Inicio:</label></div>';
												$sHtmlModule .= '<div class="grid3">' . tep_draw_input_field('promotion_start', $dDateStart, 'style="width: 200px;"', false, 'datetime-local') . '</div>';
												$sHtmlModule .= '<div class="clear"></div>';
												$sHtmlModule .= '</div>';

											$sHtmlModule .= '<div class="formRow">';
												$sHtmlModule .= '<div class="grid2"><label>Fecha Fin:</label></div>';
												$sHtmlModule .= '<div class="grid3">' . tep_draw_input_field('promotion_end', $dDateEnd, 'style="width: 200px;"', false, 'datetime-local') . '</div>';
												$sHtmlModule .= '<div class="clear"></div>';
												$sHtmlModule .= '</div>';

											$sHtmlModule .= '<div class="formRow">';
												$sHtmlModule .= '<div class="grid2"><label>Icono promoción:</label></div>';
												$sHtmlModule .= '<div class="grid3">' . tep_draw_file_field( 'promotion_icon' ) . '</div>';
												if( $sIcon != '' && file_exists( DIR_FS_CATALOG_IMAGES . 'landings/' . $sIcon ) )
												$sHtmlModule .= '<div class="grid1" style="top: 2px;left: -25px;"><a href="/images/landings/' . $sIcon . '" title="Ver icono promoción" target="_blank"><img src="images/icons/picture_icon.png" alt="Imagen de icono" /></a></div>';
												$sHtmlModule .= '<div class="clear"></div>';
												$sHtmlModule .= '</div>';

											$sHtmlModule .= '<div class="formRow">';
												$sHtmlModule .= '<div class="grid2"><label>Promoción ofertas:</label></div>';
												$sHtmlModule .= '<div class="grid1">' . tep_draw_checkbox_field( 'promotion_special', 1, (isset( $nSpecial ) ? $nSpecial : 0), '', 'id="prspc"' ) . '</div>';
												$sHtmlModule .= '<div class="clear"></div>';
												$sHtmlModule .= '</div>';
											$sHtmlModule .= '</div>';

										$sHtmlModule .= '<div class="clear"></div>';
										$sHtmlModule .= '</div>';
									$sHtmlModule .= '</div>';
								$sHtmlModule .= '</div>';
							$sHtmlModule .= '</td>';
						$sHtmlModule .= '</tr>';
					$sHtmlModule .= '<!-- FIN LANDING -->';

					$sHtmlModule .= '</tbody>';
					$sHtmlModule .= '</table>';
				$sHtmlModule .= '</form>';
				$sHtmlModule .= '</div>';
			$sHtmlModule .= '</table>';
