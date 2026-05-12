<?php
	include( 'includes/application_top.php' );

	// Variables //
	$sUrlPage = 'products_together.php';
	$sGetAction = tep_db_prepare_input( $_GET['a'] ?? '' );
	$sHtmlModule = '';
	$sHeadTitle = '';
	$sHeadSubTitle = '';
	$aHeadIcons = array();
	$nCustomerGroupId = 0;


	// Actions //
	switch( $sGetAction )
	{
		// Javascript //
		case 'load_javascript':
			echo '
			// Cuando se envia el formulario para guardar comprobamos que existan productos
			$("#form").submit(function(e)
			{
				e.stopPropagation();

				if( $(this).find("input[name*=id_producto_principal]").length <= 0 )
				{
					alert( "Debes introducir un producto principal" );
					return false;
				}
				
				if( $(this).find("input[name*=id_producto_relacionado]").length <= 0 )
				{
					alert( "Debes introducir mínimo un producto relacionado" );
					return false;
				}

				return true;
			});
			
			// Al redimensionar la pantalla
			$( window ).resize(function()
			{
				// Poisicionamos el pie del admin
				$("#box-right").attr( "style", "" );

				if( $("#box-right").height() < $("#box-left").height() )
					$("#box-right").height( $("#box-left").height() - 32 );
			});

			$( window ).trigger("resize");

			// Doble click en un registro de la tabla llamara a su src
			$(".dbclick td").not(".check").dblclick(function()
			{
				if( $(this).parent().data( "target" ) )
					window.open( $(this).parent().data("src"), "_blank" );
				else
					document.location.href = $(this).parent().data("src");
			});

			// Function eliminar
			var deleteElement = function(dmElement)
			{
				if( confirm("¿Realmente deseas eliminarlo?" ) )
				{
					if( $(dmElement).closest("#box-producto_principal").attr("id") ==  "box-producto_principal" )
						$(dmElement).closest("table").prev().find(".ex_autocomplete").parent().css("display", "block");
					
					$(dmElement).closest("tr").remove();
				}
			};

			// Evento eliminar elemento
			$(".box-sort .icos-trash").each(function()
			{
				$(this).click(function()
				{
					deleteElement(this);
				});
			});
					
			// Evento eliminar elemento
			$(".icos-trash.delete").parent().each(function()
			{
				$(this).click(function()
				{
					if( confirm("¿Realmente deseas eliminarlo?" ) )
						return true;
					else				
						return false;
				});
			});

			// Autocomplete
			$(".ex_autocomplete").each(function()
			{
				// Variables
				var sType = $(this).data("type");

				$(this).autocomplete(
				{
					minLength: 4,
					select: function( event, ui )
					{
						// Html
						var sHtml = "<td>" + ui.item.id + "<input type=\'hidden\' name=\'id_"+ sType +"[]\' value=\'" + ui.item.id + "\' /></td>";
						sHtml += "<td><img src=\'" + ui.item.image + "\' width=\"120\" height=\"120\" /></td>";
						sHtml += "<td>" + ui.item.value; + "</td>";
						
						// Si es producto principal
						if( sType == "producto_principal" )						
							$(this).parent().css("display", "none");
						else
						{
							sHtml += "<td><s>" + ui.item.price_last + "</s> <span data-price=\"" + ui.item.price_format + "\" class=\"prco\">" + ui.item.price + "</span></td>";
							sHtml += "<td><input type=\'hidden\' name=\'tax[]\' value=\'" + ui.item.tax + "\' /> Porcentaje: <input type=\"number\" class=\"porcentaje\" value=\"0\" max=\"100\" min=\"0\" step=\"0.01\" style=\"width:60px;\" /> Precio: <input type=\"text\" class=\"precio\" name=\"precio[]\" value=\"" + ui.item.price_format + "\" /></td>";
						}

						sHtml += "<td><strong>Para ver los atributos, debe guardar el elemento.</strong></td>"

						// Acciones
						sHtml += "<td style=\'text-align: center;\'><span class=\'new-delete icos-trash\' style=\'cursor: pointer; float: none;\'></span></td>";
							
						// Pintamos los elementos
						$("#box-" + sType).append( \'<tr>\' + sHtml + \'</tr>\' );

						// Asignamos evento
						$("#box-" + sType).find(".new-delete").click(function(){deleteElement(this);}).removeClass("new-delete");

						// Asignamos evento
						if( sType != "producto_principal" )
						{
							var dmParent = $("#box-" + sType).find("tr:last-child");
							// Evento para porcentaje
							dmParent.find(".precio").change(function(){priceInput($(this));});

							// Evento para precio
							dmParent.find(".porcentaje").change(function(){priceDiscount($(this));});
						}
						
						// Vaciamos y llamamos evento click
						$(this).val("");
						$(this).trigger("click");
						return false;
					}
				});

				// Cambiamos la url del autocomplete
				$(this).click(function()
				{
					var sUrl = "' . tep_href_link( $sUrlPage ) . '?a=autocomplete";

					$("#box-" + sType + " input[name*=id_producto_relacionado]").each(function()
					{
						sUrl += "&id[]=" + $(this).val();
					});

					$(this).autocomplete( "option", "source", sUrl  );
				});
			});
			
			// Realiza descuento
			var priceInput = function(dmInput)
			{
				var dmParent = dmInput.parent();
				var nPrecioOficinal = dmParent.prev().find(".prco").data("price");
				var nPrecio = dmInput.val();
				var nResult = 100 - (nPrecio * 100 / nPrecioOficinal);
				
				dmParent.find(".porcentaje").val( nResult.toFixed(2) );
			}
			
			var priceDiscount = function(dmInput)
			{
				var dmParent = dmInput.parent();
				var nPrecioOficinal = dmParent.prev().find(".prco").data("price");
				var nDiscount = dmInput.val();
				var nResult = nPrecioOficinal - (nDiscount * nPrecioOficinal / 100);
				
				dmParent.find(".precio").val( nResult.toFixed(2) );
			}
			
			// Recorremos si tenemos precios listado para calcular pocentaje y añadir eventos
			$("#form").find("input[class=precio]").each(function(dmElement)
			{
				// Calculamos el porcentaje segun el precio
				priceInput( $(this) );
				
				// Evento para porcentaje
				$(this).prev().change(function(){priceDiscount($(this));});
				
				// Evento para precio
				$(this).change(function(){priceInput($(this));});
			});
			';

			// Detenemos
			exit();
		break;

		case 'autocomplete':
			// Librerias
			include( DIR_WS_CLASSES . 'currencies.php' );
		
			// Variables
			$currencies = new currencies();
			$sGetTern = tep_db_prepare_input( $_GET['term'] );
			$aGetId = tep_db_prepare_input( $_GET['id'] );
			$aKeywords = array();
			$aReturn = array();

			// Creamos el array de keywords segun lo buscado
			tep_parse_search_string( $sGetTern, $aKeywords );

			if( isset($aKeywords) && (sizeof($aKeywords) > 0) )
			{
				$sWhere .= " (";

				for( $i = 0, $n = sizeof($aKeywords); $i < $n; $i++ )
				{
					switch ($aKeywords[$i])
					{
						case '(':
						case ')':
						case 'and':
						case 'or':
							$sWhere .= " " . strtolower($aKeywords[$i]) . " ";
						break;

						default:
							$keyword = tep_db_prepare_input($aKeywords[$i]);
							$sWhere .= "(LOWER(pd.products_name) like '%" . strtolower(tep_db_input($keyword)) . "%')";
						break;
					}
				}

				$sWhere .= " )";
			}

			// Consultamos
			if( is_array( $aGetId ) && count( $aGetId ) > 0 )
				$sWhere .= ' and pd.products_id not in(' . implode( ',', $aGetId ) . ')';

			// Creamos la consulta del producto
			$sSelect = 'select p.products_id, p.products_image, p.products_tax_class_id, pd.products_name, IF(s.specials_new_products_price is not null, p.products_price, NULL) as products_price_anterior, IF(s.specials_new_products_price is not null, s.specials_new_products_price, p.products_price) as products_price';
		
			// Uniones
			$sFrom = 'from products p 
					  inner join products_description pd on (p.products_id = pd.products_id)
					  left join specials s on (s.products_id = p.products_id and s.customers_group_id = "' . $nCustomerGroupId . '")';			
			// Where		 
			$sWhere = 'where ' . $sWhere . ' and p.products_status = 1 and pd.language_id = 3 order by pd.products_name LIMIT 30';

			// Construimos el SQL
			$sSql = $sSelect . ' ' . $sFrom . ' ' . $sWhere;

			// Consulta
			$aDatos = tep_db_query( $sSql );

			// Si tenemos datos mostramos
			if( isset( $aDatos ) )
			{
				while ($aDato = tep_db_fetch_array($aDatos))
				{
					// Datos
					$sPrecio = $currencies->display_price( $aDato['products_price'], ($nCustomerGroupId != 0 ? 0 : tep_get_tax_rate( $aDato['products_tax_class_id'] )) );
					$sOferta = '';
					
					// Si tiene oferta
					if( $aDato['products_price_anterior'] != '' )
					{
						$sPrecio = $currencies->display_price( $aDato['products_price'], ($nCustomerGroupId != 0 ? 0 : tep_get_tax_rate( $aDato['products_tax_class_id'] )) );
						$sPrecio = str_replace( array( '&euro;' ), array( '€' ), $sPrecio );
						$sOferta = $currencies->display_price( $aDato['products_price_anterior'], ($nCustomerGroupId != 0 ? 0 : tep_get_tax_rate( $aDato['products_tax_class_id'] )) );
					}
				
					$aReturn[$aDato['products_id']] = array( 
						'id' => $aDato['products_id'],
						'image' => '/images/productos/' . $aDato['products_image'],
						'label' => '#' . $aDato['products_id'] . '# '  . $aDato['products_name'], 
						'value' => $aDato['products_name'],
						'price_last' => $sOferta,
						'price' => $sPrecio,
						'tax' => tep_get_tax_rate( $aDato['products_tax_class_id'] ),
						'price_format' => floatval( str_replace( array( '&euro;', ',' ), array( '', '.' ), $sPrecio ) )
					);
				}

				echo json_encode( $aReturn );
			}

			exit();
		break;
		
		// Eliminar //
		case 'delete':
			$sGetId = tep_db_prepare_input( $_GET['id'] );
			
			// Eliminamos
			tep_db_query( 'delete from products_together where parent_id = "' . $sGetId . '"' );
			
			// Mensaje
			$messageStack->addSession( 'mensaje', 'Producto relacionado eliminado correctamente', 'success' );
			tep_redirect( tep_href_link( $sUrlPage ) );
		break;
		
		// Salvar //
		case 'save':
			// Variables
			$sGetId = tep_db_prepare_input( $_GET['id'] );
			$sPostParentId = tep_db_prepare_input( $_POST['id_producto_principal'] );			
			$sPostProductsId = tep_db_prepare_input( $_POST['id_producto_relacionado'] );
			$sPostTax = tep_db_prepare_input( $_POST['tax'] );
			$sPostPrice = tep_db_prepare_input( $_POST['precio'] );
			
			// Si estamos editando eliminamos
			if( $sGetId !== '' )
				tep_db_query( 'delete from products_together where parent_id = "' . $sPostParentId[0] . '"' );
			
			// Insertamos
			foreach( $sPostProductsId as $sKey => $sId )
			{
				$nPrice = $sPostPrice[$sKey] / (1 + ($sPostTax[$sKey] / 100) );				
				tep_db_query( 'insert into products_together (parent_id, products_id, price) values (' . $sPostParentId[0] . ', ' . $sId . ', ' . $nPrice . ')' );
			}

			foreach($_POST['id_producto_relacionado'] as $id) {
				$attributes = '';

				if (isset($_POST['attributes'][$id])) {
					$attributes = addslashes(json_encode($_POST['attributes'][$id]));
				}
				$sql = sprintf('UPDATE products_together SET attributes = "%s" WHERE parent_id = %d AND products_id = %d', $attributes, $sPostParentId[0], $id);
				tep_db_query($sql);
			}
			
			// Actualizamos
			$messageStack->addSession( 'mensaje', 'Producto relacionado ' . ($sGetId == '' ? 'insertado' : 'actualizado') . ' correctamente', 'success' );
			tep_redirect( tep_href_link( $sUrlPage ) );
			exit();
		break;
		
		// Añadir, editar producto relacionado //
		case 'add':
		case 'edit':
			// Librerias
			include( DIR_WS_CLASSES . 'currencies.php' );
		
			// Variables
			$currencies = new currencies();
			$sGetId = tep_db_prepare_input( $_GET['id'] );
			
			// Obtenemmos producto
			if( $sGetId != '' )
			{
				$aProducto = tep_db_query( 'select pd.products_name, p.products_image, p.products_id
											from products p
											inner join products_description pd on(pd.products_id = p.products_id)
											where pd.language_id = 3 and p.products_id = "' . (int)$sGetId . '"' );

				// Si no existe retornamos
				if( tep_db_num_rows( $aProducto ) == 0 )
				{
					tep_redirect( tep_href_link( $sUrlPage ) );
					exit();
				}
				else
					$aProducto = tep_db_fetch_array( $aProducto );
			}

			// Titulo
			if( $sGetId != '' )
			{
				$sHeadTitle = 'Producto relacionado ' . $aProducto['products_name'];
				$sHeadSubTitle = 'Editar producto relacionado';
				$sUrlForm = tep_href_link( $sUrlPage, 'a=save&id=' . $sGetId );
			}
			else
			{
				$sHeadTitle = 'Nuevo producto relacionado';
				$sHeadSubTitle = 'Nuevo producto';
				$sUrlForm = tep_href_link( $sUrlPage, 'a=save' );
			}

			// Guardar y volver
			$aHeadIcons[] = array( 'EXTRA' => 'onclick="$(\'#form\').submit();"', 'SRC' => 'images/icons/icon_save_return.png', 'TITLE' => 'Guardar y volver' );
			$aHeadIcons[] = array( 'HREF' => tep_href_link( $sUrlPage ), 'SRC' => 'images/icons/icon_back.png', 'TITLE' => 'Volver' );

			// Tablas //
			$sHtmlModule .= '<form id="form" method="post" action="' . $sUrlForm . '">';
			
				if( $sGetId != '' )
					$sHtmlModule .= '<input name="id" type="hidden" value="' . $sGetId . '" />';

				$sHtmlModule .= '<div class="fluid grid">';
					$sHtmlModule .= '<div class="box-tbl grid12">';
						$sHtmlModule .= '<div class="box-head">';
							$sHtmlModule .= '<div class="grid3"><h6>Producto principal</h6></div>';
							$sHtmlModule .= '<div class="grid9">';
								$sHtmlModule .= '<p style="position: relative; float: right; right: 5px; top: 0px; display: ' . ($sGetId != '' ? ' none' : 'block') . ';">';
									$sHtmlModule .= '<label>Producto: </label>';
									$sHtmlModule .= '<input type="text" style="width: 500px;" data-type="producto_principal" class="ex_autocomplete" />';
								$sHtmlModule .= '</p>';
							$sHtmlModule .= '</div>';
							$sHtmlModule .= '<div class="clear"></div>';
						$sHtmlModule .= '</div>';

					$sHtmlModule .= '<table id="table" class="tAlt wGeneral tDefault" width="100%" cellspacing="0" cellpadding="0">';
						$sHtmlModule .= '<thead>';
							$sHtmlModule .= '<tr>';
								$sHtmlModule .= '<td style="text-align: left; width: 1px;">Id</td>';
								$sHtmlModule .= '<td width="125" style="text-align: center;">Imagen</td>';
								$sHtmlModule .= '<td style="text-align: left;">Nombre</td>';
								
								if( $sGetId == '' )
									$sHtmlModule .= '<td width="125"  style="text-align: center;">Acción</td>';
							$sHtmlModule .= '</tr>';
						$sHtmlModule .= '</thead>';
						$sHtmlModule .= '<tbody class="box-sort" id="box-producto_principal">';
							if( $sGetId != '' )
							{							
								$sHtmlModule .= '<tr>';
									$sHtmlModule .= '<td style="text-align: left; width: 1px;">' . $aProducto['products_id'] . '<input type="hidden" name="id_producto_principal[]" value="' . $aProducto['products_id'] . '" /></td>';
									$sHtmlModule .= '<td style="text-align: left;"><img src="/images/productos/' . $aProducto['products_image'] . '" width="120" height="120" /></td>';
									$sHtmlModule .= '<td style="text-align: left;">' . $aProducto['products_name'] . '</td>';
									
									if( $sGetId == '' )
										$sHtmlModule .= '<td style="text-align: center;"><span class="icos-trash" style="cursor: pointer; float: none;"></span></td>';
								$sHtmlModule .= '</tr>';
							}
						$sHtmlModule .= '</tbody>';
					$sHtmlModule .= '</table>';
					$sHtmlModule .= '</div>';
				$sHtmlModule .= '</div>';
			
				// Productos relacionados //
				$sHtmlModule .= '<div class="fluid grid">';
					$sHtmlModule .= '<div class="box-tbl grid12">';
						$sHtmlModule .= '<div class="box-head">';
							$sHtmlModule .= '<div class="grid3"><h6>Productos relacionados</h6></div>';
							$sHtmlModule .= '<div class="grid9">';
								$sHtmlModule .= '<p style="position: relative; float: right; right: 5px; top: 0px;">';
									$sHtmlModule .= '<label>Producto: </label>';
									$sHtmlModule .= '<input type="text" style="width: 500px;" data-type="producto_relacionado" data-id="' . $sGetIdCategoria . '" class="ex_autocomplete" />';
								$sHtmlModule .= '</p>';
							$sHtmlModule .= '</div>';
							$sHtmlModule .= '<div class="clear"></div>';
						$sHtmlModule .= '</div>';

					$sHtmlModule .= '<table id="table" class="tAlt wGeneral tDefault" width="100%" cellspacing="0" cellpadding="0">';
						$sHtmlModule .= '<thead>';
							$sHtmlModule .= '<tr>';
								$sHtmlModule .= '<td style="text-align: left; width: 1px;">Id</td>';
								$sHtmlModule .= '<td width="125" style="text-align: left;">Imagen</td>';
								$sHtmlModule .= '<td style="text-align: left;">Nombre</td>';
								$sHtmlModule .= '<td style="text-align: left;">Precio base</td>';
								$sHtmlModule .= '<td style="text-align: left;">Descuento</td>';
								$sHtmlModule .= '<td style="text-align: left;">Atributos</td>';
								$sHtmlModule .= '<td width="125">Acciones</td>';
							$sHtmlModule .= '</tr>';
						$sHtmlModule .= '</thead>';
						$sHtmlModule .= '<tbody class="box-sort" id="box-producto_relacionado">';			
							if( $sGetId != '' )
							{
								$aDatos = tep_db_query( 'select pg.attributes, p.products_id, p.products_image, p.products_tax_class_id, pg.price, pd.products_name, IF(s.specials_new_products_price is not null, p.products_price, NULL) as products_price_anterior, IF(s.specials_new_products_price is not null, s.specials_new_products_price, p.products_price) as products_price
														from products p 
														inner join products_together pg on (p.products_id = pg.products_id)
														inner join products_description pd on (p.products_id = pd.products_id)
														left join specials s on (s.products_id = p.products_id and s.customers_group_id = "' . $nCustomerGroupId . '")
														where pg.parent_id = "' . (int)$sGetId . '" and p.products_status = 1 and pd.language_id = 3' );

								while( $aDato = tep_db_fetch_array( $aDatos ) )
								{
									// Datos
									$sPrecioRelacionado = floatval( str_replace( array( '&euro;', ',' ), array( '', '.' ), $currencies->display_price( $aDato['price'], ($nCustomerGroupId != 0 ? 0 : tep_get_tax_rate( $aDato['products_tax_class_id'] )) ) ) );
									$sPrecio = $currencies->display_price( $aDato['products_price'], ($nCustomerGroupId != 0 ? 0 : tep_get_tax_rate( $aDato['products_tax_class_id'] )) );
									$sPrecioFormat = floatval( str_replace( array( '&euro;', ',' ), array( '', '.' ), $sPrecio ) );
									$sOferta = '';
									
									// Si tiene oferta
									if( $aDato['products_price_anterior'] != '' )
									{
										$sPrecio = $currencies->display_price( $aDato['products_price'], ($nCustomerGroupId != 0 ? 0 : tep_get_tax_rate( $aDato['products_tax_class_id'] )) );
										$sPrecio = str_replace( array( '&euro;' ), array( '€' ), $sPrecio );
										$sOferta = $currencies->display_price( $aDato['products_price_anterior'], ($nCustomerGroupId != 0 ? 0 : tep_get_tax_rate( $aDato['products_tax_class_id'] )) );
									}
								
									$sHtmlModule .= '<tr>';
										$sHtmlModule .= '<td style="text-align: left; width: 1px;">' . $aDato['products_id'] . '<input type="hidden" name="id_producto_relacionado[]" value="' . $aDato['products_id'] . '" /></td>';
										$sHtmlModule .= '<td style="text-align: left;"><img src="/images/productos/' . $aDato['products_image'] . '" width="120" height="120" /></td>';
										$sHtmlModule .= '<td style="text-align: left;">' . $aDato['products_name'] . '</td>';
										$sHtmlModule .= '<td style="text-align: left;"><s>' . $sOferta . '</s> <span data-price="' . $sPrecioFormat . '" class="prco">' . $sPrecio . '</span></td>';
										$sHtmlModule .= '<td style="text-align: left;"><input type="hidden" value="' . tep_get_tax_rate( $aDato['products_tax_class_id'] ) . '" name="tax[]" /> Porcentaje: <input style="width:60px;" type="number" step="0.01" min="0" max="100" value="0" class="porcentaje"/> Precio: <input type="text" value="' . $sPrecioRelacionado . '" name="precio[]" class="precio"/></td>';
										
										$sql = "SELECT popt.products_options_name, popt.products_options_id, patrib.options_values_id , patrib.products_attributes_id, IF(ps.products_stock_quantity < 0, 0, ps.products_stock_quantity) as products_stock_quantity, pag.options_values_price, pov.products_options_values_name
										FROM products_options popt
										LEFT JOIN products_attributes patrib ON patrib.options_id = popt.products_options_id
										LEFT JOIN products_options_values pov ON pov.products_options_values_id = patrib.options_values_id AND pov.language_id = 3
										LEFT JOIN products_attributes_groups pag ON pag.products_attributes_id = patrib.products_attributes_id
										LEFT JOIN products_stock ps ON ps.products_stock_attributes = CONCAT(popt.products_options_id, '-', patrib.options_values_id) AND ps.products_id = '" . $aDato['products_id'] . "'
										WHERE patrib.products_id='" . $aDato['products_id'] . "' AND popt.language_id = 3 GROUP BY options_values_id ORDER BY options_values_price asc";
										$query = tep_db_query($sql);
										$attributes = '';

										$attributesActual = [];
										if ($aDato['attributes'] != '') {
											$attributesActual = json_decode($aDato['attributes'], true);
										}
										
										if (tep_db_num_rows($query) > 0) {

											$attributes .= '<ul style="display: grid;grid-template-columns: repeat(3, 1fr);gap: 3px;">';
											while ($attribute = tep_db_fetch_array($query)) {
												$id = $attribute['products_options_id'].'-'.$attribute['options_values_id'];
												$selected = in_array($id, $attributesActual) ? ' checked="checked" ' : '';
												$attributes .= '
												<li>
													<label><input type="checkbox" name="attributes['.$aDato['products_id'].'][]" value="'.$id.'" '.$selected.'> '.$attribute['products_options_name'].' '.$attribute['products_options_values_name'].'</label>
												</li>';
											}
											$attributes .= '<ul>';
										}

										$sHtmlModule .= '<td style="text-align: left;">' . $attributes . '</td>';

										$sHtmlModule .= '<td style="text-align: center;"><span class="icos-trash" style="cursor: pointer; float: none;"></span></td>';
									$sHtmlModule .= '</tr>';
								}
							}
						$sHtmlModule .= '</tbody>';
					$sHtmlModule .= '</table>';
					$sHtmlModule .= '</div>';
				$sHtmlModule .= '</div>';
			$sHtmlModule .= '</form>';
		break;

		// Mostrar lista de menus //
		default:
			// Titulos, iconos y menu //
			$sHeadTitle = 'Productos relacionados';
			$sHeadSubTitle = 'Listado de productos relacionados';
			$aHeadIcons = array(
				array( 'HREF' => tep_href_link( $sUrlPage, 'a=add' ), 'SRC' => 'images/icons/icon_add.png', 'TITLE' => 'Añadir producto' )
			);

			// Busqueda
			$search = '';
			$sGetSearch = tep_db_prepare_input( $_GET['search'] ?? '' );
			
			$search = ' where pd.language_id = 3 ';

			if (isset($_GET['search']) && tep_not_null($_GET['search']))
			{
				$search .= ' and ';
			
				// Obtenemos las keywords
				$keywords = tep_db_input(tep_db_prepare_input($_GET['search']));

				// Obtenemos todas las combinaciones
				$aBusquedas = combinations( $keywords );

				// Componemos el where
				if( count( $aBusquedas ) == 0 )
					$aBusquedas[] = array($keywords);
					
				foreach( $aBusquedas as $aCadena )
					$search .= '(LOWER(pd.products_name) like "%' . strtolower( implode( '%', $aCadena ) ) . '%"  or pt.parent_id like "%' . implode( '%', $aCadena ) . '%") OR ';

				$search = substr( $search, 0, -4 );
			}
			
			// Formamos consulta SQL
			$sSql = 'select pt.parent_id, pd.products_name, pt.products_together_id
					 from products_together pt
					 inner join products_description pd on (pt.parent_id = pd.products_id)
					 ' . $search . '
					 group by pt.parent_id';
			
			// Obtenemos los productos principales
			$sSql = preg_replace("/[\r\n\t]+/", " ", $sSql );
			$aDatosSlipt = new splitPageResults($_GET['page'], MAX_DISPLAY_SEARCH_RESULTS, $sSql, $nAux, 'SELECT COUNT(table_aux.parent_id) as total FROM (' . $sSql . ') as table_aux' );
			$aDatos = tep_db_query( $sSql );

			// Variable para saber si hemos encontrado datos o no
			$bFoundDatos = tep_db_num_rows( $aDatos ) > 0;

			// Si no encontramos ninguna lista
			if( !$bFoundDatos )
				$messageStack->add( 'No existen productos relacionados, insertar algun producto', 'warning', 'true' );
			else
			{			
				$sHtmlModule .= '<div class="box-tbl" style="width: 100%">';

					// Filtro
					$sHtmlModule .= '<div class="box-head">';
						$sHtmlModule .= '<h6>Productos relacionados</h6>';
						$sHtmlModule .= '<a class="tOptions filter-togle" href="javascript:void(0);" data-id="1" title="Filtrar"><img src="theme/web/images/icons/usual/icon-cog3.png" alt=""></a>';
						$sHtmlModule .= '<div class="clear"></div>';
					$sHtmlModule .= '</div>';
					
					$sHtmlModule .= '<div class="fluid grid tablePars" data-id="1" style="display: none;">';
						$sHtmlModule .= '<form method="get" action="' . tep_href_link( $sUrlPage ) . '" name="search">';
							$sHtmlModule .= '<div class="grid12">';
								$sHtmlModule .= '<div style="border: none; padding: 7px 16px;" class="formRow">';
									$sHtmlModule .= '<div class="grid2"><label>Buscar:</label></div>';
									$sHtmlModule .= '<div class="grid10">' . tep_draw_input_field( 'search', $sGetSearch, 'placeholder="Introduce búsqueda..."' ) . '</div>';
									$sHtmlModule .= '<div class="clear"></div>';
								$sHtmlModule .= '</div>';
								$sHtmlModule .= '<div style="border: none; padding: 7px 16px;" class="formRow">';
									$sHtmlModule .= '<div class="grid12">';
										$sHtmlModule .= '<input type="submit" style="cursor: pointer;" class="buttonS bGreen" value="Filtrar">';
									$sHtmlModule .= '</div>';
									$sHtmlModule .= '<div class="clear"></div>';
								$sHtmlModule .= '</div>';
							$sHtmlModule .= '</div>';
						$sHtmlModule .= '</form>';
					$sHtmlModule .= '</div>';

					// Tabla //
					$sHtmlModule .= '<table id="table" class="tAlt wGeneral tDefault" width="100%" cellspacing="0" cellpadding="0">';
						$sHtmlModule .= '<thead>';
							$sHtmlModule .= '<tr>';
								$sHtmlModule .= '<td style="text-align: left">Producto</td>';
								$sHtmlModule .= '<td width="125">Acciones</td>';
							$sHtmlModule .= '</tr>';
						$sHtmlModule .= '</thead>';
						$sHtmlModule .= '<tbody id="drop-rows-filter-filt">';

							// Recorremos
							while( $aDato = tep_db_fetch_array( $aDatos ) )
							{
								$sHtmlModule .= '<tr data-src="' . tep_href_link( $sUrlPage, 'a=edit&id=' . $aDato['parent_id'] ) . '" class="dbclick dlte-filt box-sort">';

									$sHtmlModule .= '<td>' . $aDato['products_name'] . '</td>';
									$sHtmlModule .= '<td align="center">';
										$sHtmlModule .= '<div style="display: inline-block; margin-bottom: -7px;" class="btn-group">';
											$sHtmlModule .= '<a href="#" data-toggle="dropdown" class="buttonS bDefault">Acciones<span class="caret"></span></a>';
											$sHtmlModule .= '<ul class="dropdown-menu" style="left: -70px;">';
												$sHtmlModule .= '<li><a href="' . tep_href_link( $sUrlPage, 'a=edit&id=' . $aDato['parent_id'] ) . '"><span style="padding-top: 1px;" class="icos-pencil"></span>Editar</a></li>';
												$sHtmlModule .= '<li><a href="' . tep_href_link( $sUrlPage, 'a=delete&id=' . $aDato['parent_id'] ) . '"><span style="padding-top: 1px;" class="icos-trash delete"></span>Eliminar</a></li>';
											$sHtmlModule .= '</ul>';
										$sHtmlModule .= '</div>';
									$sHtmlModule .= '</td>';
								$sHtmlModule .= '</tr>';
							}

						$sHtmlModule .= '</tbody>';
					$sHtmlModule .= '</table>';
					$sHtmlModule .= $aDatosSlipt->showPaginateTable( tep_get_all_get_params( array( 'page', 'info', 'x', 'y', 'cID' ) ) );
				$sHtmlModule .= '</div>';
			}
		break;
	}

	// MessageStack
	$sMensajeStack = $messageStack->output(false);
	$messageStack->reset();

	// Header //
	include( THEME . 'html/header.php' );

	// Titulos e iconos //
	echo '<div>';
		echo '<div class="toolbarHead">';
			echo '<div class="hdr-tlbr">';
				echo '<h1 class="pageHeading ftitl" style="top: 6px;">' . $sHeadTitle . '</h1>';
				echo '<h2 class="stitl" style="top: 13px;">' . $sHeadSubTitle . '</h2>';
				echo '<div class="btn-right">';
					foreach( $aHeadIcons as $aHeadIcon )
						echo '<a ' . (array_key_exists( 'EXTRA', $aHeadIcon ) ? $aHeadIcon['EXTRA'] : '') . ' ' . (array_key_exists( 'TITLE', $aHeadIcon ) ? 'title="' . $aHeadIcon['TITLE'] . '"' : '') . ' href="' . (array_key_exists( 'HREF', $aHeadIcon ) ? $aHeadIcon['HREF'] : 'javascript:void(0);') . '"><img src="' . $aHeadIcon['SRC'] . '" class="dx-hovr"></a>';
				echo '</div>';
			echo '</div>';
		echo '</div>';
	echo '</div>';

	// Modulo //
	echo '<table width="100%;"><tr><td>' . $sMensajeStack . $sHtmlModule;

	// Javascript //
	$sJavascript = '<script type="text/javascript" src="' . tep_href_link( $sUrlPage, 'a=load_javascript' ) . '"></script> <style type="text/css"> .ui-autocomplete{ height: 220px; overflow: scroll; width: 494px !important;} </style>';

	// Footer //
	include( THEME . 'html/footer.php' );

	// Librerias //
	include(DIR_WS_INCLUDES . 'application_bottom.php' );
?>