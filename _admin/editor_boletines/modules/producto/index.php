<?php
	// Variables
	$sAction = tep_db_prepare_input( $_GET['a'] );

	switch( $sAction )
	{
		case 'select_productos':
			// Librerias
			include( DIR_WS_CLASSES . 'currencies.php' );
		
			// Variables
			$currencies = new currencies();
			$sIdProductos = substr( tep_db_prepare_input( $_POST['id_producto'] ), 0, -1 );
			$sTheme = tep_db_prepare_input( $_GET['theme'] );
			$aThemes = array();
			$sDescripcion = '';
			
			// Comprobamos si nos envian themes por post
			if( array_key_exists( 'themes', $_POST ) )
				$aThemes = json_decode( str_replace( '\"', '"', $_POST['themes'] ), 1 );

			// Creamos la consulta del producto
			$sSelect = 'select p.products_id, pd.products_description, p.products_image, p.products_ship_free, p.products_tax_class_id, pd.products_name';
		
			// Uniones
			$sFrom = 'from products p 
					  inner join products_description pd on (p.products_id = pd.products_id)
					  left join specials s on (s.products_id = p.products_id and s.customers_group_id = "' . $nCustomerGroupId . '")';

			// Si es grupo de cliente
			if( $nCustomerGroupId == 0 )
				$sSelect .= ', IF(s.specials_new_products_price is not null and s.status = 1, p.products_price, NULL) as products_price_anterior, IF(s.specials_new_products_price is not null and s.status = 1, s.specials_new_products_price, p.products_price) as products_price';
			else
			{
				$sFrom .= ' left join products_groups pg on (pg.products_id = p.products_id and pg.customers_group_id = "' . $nCustomerGroupId . '")';
				//$sSelect .= ', IF(pg.customers_group_price is not null, pg.customers_group_price, p.products_price) as products_price';
				$sSelect .= ', IF(s.specials_new_products_price IS NOT NULL and s.status = 1, s.specials_new_products_price, IF(pg.products_price8 > 0, pg.products_price8, IF(pg.products_price7 > 0, pg.products_price7, IF(pg.products_price6 > 0, pg.products_price6, IF(pg.products_price5 > 0, pg.products_price5, IF(pg.products_price4 > 0, pg.products_price4, IF(pg.products_price3 > 0, pg.products_price3, IF(pg.products_price2 > 0, pg.products_price2, IF(pg.products_price1 > 0, pg.products_price1, IF(pg.customers_group_price IS NOT NULL, pg.customers_group_price, p.products_price)))))))))) as products_price, IF(s.specials_new_products_price IS NOT NULL and s.status = 1, IF(pg.customers_group_price IS NOT NULL, pg.customers_group_price, p.products_price), NULL) as products_price_anterior, IF(s.specials_new_products_price IS NOT NULL and s.status = 1, 0, IF(pg.products_price8 > 0, 1, IF(pg.products_price7 > 0, 1, IF(pg.products_price6 > 0, 1, IF(pg.products_price5 > 0, 1, IF(pg.products_price4 > 0, 1, IF(pg.products_price3 > 0, 1, IF(pg.products_price2 > 0, 1, IF(pg.products_price1 > 0, 1, 0))))))))) as rapel';
			}
				
			// Where		 
			$sWhere = 'where p.products_status = 1 and pd.language_id = 3 and p.products_id in (' . $sIdProductos . ')';

			// Construimos el SQL
			$sSql = $sSelect . ' ' . $sFrom . ' ' . $sWhere;

			// Consultamos
			$aDatos = tep_db_query( $sSql );

			// Recorremos
			while( $aDato = tep_db_fetch_array( $aDatos ) )
			{
				// Datos
				$sPrecio = $currencies->display_price( $aDato['products_price'], ($nCustomerGroupId != 0 ? 0 : tep_get_tax_rate( $aDato['products_tax_class_id'] )) );
				$sTitulo = $aDato['products_name'];
				if( $sTheme == 'horizontal' )
					$sDescripcion = $aDato['products_description'];
				$sImagen = $aDato['products_image'];
				$sOferta = '';
				$sDesde = '';
				$sEnvio = false;
				
				// Si somos grupo de cliente distinto a 0 sera sin iva
				/*if( $nCustomerGroupId != 0 )
					$sTax = 'IVA NO Incl.';
				else
					$sTax = 'IVA Incl.';*/

				if( array_key_exists( 'rapel', $aDato) && $aDato['rapel'] == 1 )
					$sDesde = 'Desde';

				// Si tiene gastos de envio gratuito
				if( $aDato['products_ship_free'] == 1 )
					$sEnvio = true;

				$nPorcentaje = 0;
				// Si tiene oferta
				if( $aDato['products_price_anterior'] != '' )
				{
					$sPrecio = $currencies->display_price( $aDato['products_price'], ($nCustomerGroupId != 0 ? 0 : tep_get_tax_rate( $aDato['products_tax_class_id'] )) );
					$sPrecio = str_replace( array( '&euro;' ), array( '€' ), $sPrecio );
					$sOferta = str_replace( array( '&euro;' ), array( '€' ), $currencies->display_price( $aDato['products_price_anterior'], ($nCustomerGroupId != 0 ? 0 : tep_get_tax_rate( $aDato['products_tax_class_id'] )) ) );

					$nPrecio = $aDato['products_price'] * (($nCustomerGroupId != 0 ? 1 : (tep_get_tax_rate( $aDato['products_tax_class_id'] ) / 100 + 1 )));
					$nPrecioAnterior = $aDato['products_price_anterior'] * (($nCustomerGroupId != 0 ? 1 : (tep_get_tax_rate( $aDato['products_tax_class_id'] ) / 100 + 1 )));
					$nPorcentaje = 100 - (int)(($nPrecio * 100) / $nPrecioAnterior);
				}

				// Si contenemos themes vamos cambiando la variables
				if( count( $aThemes ) > 0 )
					$sTheme = $aThemes[$aDato['products_id']];

				// Clase con el producto
				$objProductos = new dxGdProducts( array(
					'padding' => $aThemePaddingProducts[$sTheme],
					'theme' => DIR_EDITOR_BOLETINES_THEME . 'producto/' . $sTheme . '/',
					'titulo' => $sTitulo,
					'directorio_imagen' => getcwd() . '/../images/productos/',
					'imagen' => $sImagen,
					'precio' => $sPrecio,
					'porcentaje' => ($nPorcentaje . "%"),
					//'tax' => $sTax,
					'envio_gratis' => $sEnvio,
					'descripcion' => $sDescripcion,
					'oferta' => $sOferta,
					'icon' => ($nCustomerGroupId == 0 ? true : false),
					'desde' => $sDesde
				) );

				echo '<a style="float: left;" href="' . preg_replace( '/(http\:)/i', 'https:', HTTP_SERVER ) . '/product_info.php?products_id=' . $aDato['products_id'] . '&ref=bol" data-theme-id="' . $aDato['products_id'] . '" data-theme-theme="' . $sTheme . '">
					<img data-theme-64="true" style="display:block; border: 0;" border="0" src="data:image/png;base64,' . $objProductos->show(true) . '" />
				</a>';
			}

			exit();
		break;
	
		case 'search_date':
			// Variables
			$sGetFrom = tep_db_prepare_input( $_GET['from'] );
			$sGetTo = tep_db_prepare_input( $_GET['to'] );
			$sIdCategoria = tep_db_prepare_input( $_GET['id_categoria'] );
			
			// Obtenemos las categorias hijas
			$sIdCategoria = getIdCategoriasHijasRecursivoByIdCategoriaPadre( $sIdCategoria );
			
			// Si hemos recibido algo añadimos comilla
			if( $sIdCategoria != '' )
				$sIdCategoria .= ', ';
				
			// Conctatenamos la categoria
			$sIdCategoria .= $_GET['id_categoria'];

			// Obtenemos los productos según el texto enviado
			$aElements = tep_db_query( 'SELECT pd.products_name, pd.products_id
										FROM products p
										inner join products_description pd on (p.products_id = pd.products_id)
										INNER JOIN products_to_categories ptc ON (ptc.products_id = pd.products_id)
										WHERE p.products_status = 1 and ptc.categories_id in (' . $sIdCategoria . ') and (DATE_FORMAT(p.products_date_added, "%Y/%m/%d") >= "'  . chageDateFormat($sGetFrom) .  '" and DATE_FORMAT(p.products_date_added, "%Y/%m/%d") <= "'  . chageDateFormat($sGetTo) .  '") AND pd.language_id = 3' );

			// Rellenamos de elementos
			$aReturn = array();
			while( $aElement = tep_db_fetch_array( $aElements ) )			
				$aReturn[] = array( 'id' => $aElement['products_id'], 'value' => $aElement['products_name'] );
		
			echo json_encode( $aReturn );
			exit();
		break;
	
		case 'search':
			// Variables
			$sBuscar = tep_db_prepare_input( $_GET['term'] );
			$sIdCategoria = tep_db_prepare_input( $_GET['id_categoria'] );

			// Obtenemos las categorias hijas
			$sIdCategoria = getIdCategoriasHijasRecursivoByIdCategoriaPadre( $sIdCategoria );
			
			// Si hemos recibido algo añadimos comilla
			if( $sIdCategoria != '' )
				$sIdCategoria .= ', ';
				
			// Conctatenamos la categoria
			$sIdCategoria .= $_GET['id_categoria'];

			// Obtenemos los productos según el texto enviado
			$aElements = tep_db_query( 'SELECT pd.products_name, pd.products_id
										FROM products p
										inner join products_description pd on (p.products_id = pd.products_id)
										INNER JOIN products_to_categories ptc ON (ptc.products_id = pd.products_id)
										WHERE p.products_status = 1 and ptc.categories_id in (' . $sIdCategoria . ') and LCASE( pd.products_name ) LIKE "%' . strtolower( $sBuscar ) . '%" AND pd.language_id = 3' );

			// Rellenamos de elementos
			$aReturn = array();
			while( $aElement = tep_db_fetch_array( $aElements ) )			
				$aReturn[] = array( 'id' => $aElement['products_id'], 'value' => $aElement['products_name'] );
		
			echo json_encode( $aReturn );
			exit();
		break;
	}
?>

<div id="lgbox-izqd">
	<form id="form-producto" method="post" action="editor_boletines.php?m=<?php echo $sModule; ?>&a=select_productos">
		<label style="width: 190px;">Buscar producto por nombre: </label>
		<input type="text" class="focus" id="producto" name="producto" placeholder="Escribe el nombre del producto" style="width: 346px;" />
		<label style="width: 190px;">Buscar producto por fechas: </label>
		<div id="date-range" style="cursor: pointer; width: 252px; top: 120px; left: 224px; position: absolute; height: 39px;"></div>
		<input type="text" id="from" name="from" style="width: 122px;"/> - <input type="text" id="to" name="to" style="width: 122px;"/>
		<div id="search-date" class="bton bton-vrde bton-plne" style="margin-left: 10px;">Buscar</div>
		<label style="width: 190px;">Estilo del producto: </label>
		<?php echo tep_draw_pull_down_menu( 'style', getAllThemeProducto(), '', 'style="width: 346px;" id="style"' ); ?>
		<div class="dual-list">
			<select name="id_productos" style="height:186px;" class="multiple" multiple="multiple" id="box1View">
<!--<option value="6628">Auriculares estéreo Hi-Fi 601 Camuflaje Desierto</option>
<option value="2780">Wii Play + Motion Plus </option>
<option value="3030">3ds blanca</option><option value="6629">Auriculares estéreo Hi-Fi 602 Camuflaje Antartida</option><option value="6630">Auriculares estéreo Hi-Fi 603 Fiesta</option><option value="6632">Auriculares Acousticals + Micro Negro Takems</option>-->
			</select>
			<div class="dualControl">
				<button class="bton bsmal" type="button" id="slct-dlte">Eliminar seleccionados</button>
				<button class="bton bsmal" type="button" style="float: right;" id="all-dlte">Eliminar todos</button>
			</div>
		</div>
		<div class="text-info"></div>
		<button class="bton bton-vrde" type="submit">Aceptar</button>
	</form>
</div>
<div id="lgbox-drch">
	<div class="box-info">
		<div class="icon"></div>
		Introduce el nombre del producto que deseas buscar. Recuerda que filtrara los productos por la categoría en la que te encuentras.
	</div>
	<div class="box-info" style="margin-top: 20px;">
		<div class="icon"></div>
		O introduce una fecha de inicio y fin en la cual se ha insertado los productos que deseas añadir. Recuerda que filtrara los productos por la categoría en la que te encuentras.
	</div>
	<div class="box-info" style="margin-top: 20px;">
		<div class="icon"></div>
		Para seleccionar varios productos en el listado pulsa la tecla ctrl más click en cada producto. Si pulsas doble click en el producto te llevara a su ficha para ver más información.
	</div>
</div>

<script type="text/javascript" src="<?php echo DIR_EDITOR_BOLETINES . 'modules/' . $sModule; ?>/js/functions.js"></script>