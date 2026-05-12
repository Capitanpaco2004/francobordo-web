<?php
	// Funciones //
	// Devuelve el orden por la priorida enviada
	function getOrderCasePriority( $sPriority1, $sPriority2, $sPriority3, $sValues1, $sValues2, $sValues3 )
	{
		$sValues1 = (substr($sValues1, -1) == ',' ? substr($sValues1, 0, -1) : $sValues1);
		$sValues2 = (substr($sValues2, -1) == ',' ? substr($sValues2, 0, -1) : $sValues2);
		$sValues3 = (substr($sValues3, -1) == ',' ? substr($sValues3, 0, -1) : $sValues3);
		
		return 'case
					' . ($sValues1 != '' ? 'when ' . $sPriority1 . ' IN( ' . $sValues1 . ' )
						then 1' : '') . '
					' . ($sValues2 != '' ? 'when ' . $sPriority2 . ' IN( ' . $sValues2 . ' )
						then 2' : '') . '
					' . ($sValues3 != '' ? 'when ' . $sPriority3 . ' IN( ' . $sValues3 . ' )
						then 3' : '') . '
					else 4
				end';
	}
	// FIN; Funciones //

	// Variables
	$nElementos = RELATED_PRODUCTS_LIMIT_SHOW;
	$nIdProducto = preg_replace( '/\{.+$/i', '', $_GET['products_id'] );
	$aExcludes = array( $nIdProducto );
	$aProductos = array();
	$aRelateds = array();

	// Si no tenemos categoria actual, obtener la del producto
	if( (int)$current_category_id == 0 && (int)$nIdProducto > 0 ) {
		$cat_query = tep_db_query("SELECT categories_id FROM products_to_categories WHERE products_id = " . (int)$nIdProducto . " LIMIT 1");
		if( tep_db_num_rows($cat_query) > 0 ) {
			$cat_row = tep_db_fetch_array($cat_query);
			$current_category_id = $cat_row['categories_id'];
		}
	}

	// Obtenemos todos los grupos y sus relaciones
	$aGroupsRelated = tep_db_query( 'SELECT rpg.related_groups_id, rpg.idproducts as gproducts, rpg.idcategories as gcategories, rpg.idbrands as gbrands, rpr.idproducts as rproducts, rpr.idcategories as rcategories, rpr.idbrands as rbrands
									 FROM related_products_related rpr
									 INNER JOIN related_groups_to_related rtr ON (rpr.related_related_id = rtr.related_related_id)
									 INNER JOIN related_products_groups rpg ON (rtr.related_groups_id = rpg.related_groups_id)
									 WHERE rpg.related_status = 1
									 AND rtr.related_status = 1' );

	// Recorremos los grupos-relaciones
	while( $aGroupRelated = tep_db_fetch_array( $aGroupsRelated ) )
	{
		// Convertimos a array los valores de productos, categorias y marcas
		$aGProducts = array_filter( explode( ',', $aGroupRelated['gproducts'] ?? '' ) );
		$aGCategories = array_filter( explode( ',', getRecursiveIdCategories( $_aAllCategorias, $aGroupRelated['gcategories'] ) . ($aGroupRelated['gcategories'] ?? '') ) );
		$aGBrands = array_filter( explode( ',', $aGroupRelated['gbrands'] ?? '' ) );

		// Si el producto está dentro de los productos, categorias o marcas del grupo, obtenemos sus relacionados
		if( in_array( (int)$nIdProducto, $aGProducts ) || in_array( (int)$current_category_id, $aGCategories ) || in_array( (int)($aProducto['manufacturers_id'] ?? 0), $aGBrands ) )
		{
			// Guardamos relaciones del grupo //

			// Productos individuales
			if( $aGroupRelated['rproducts'] != '' )
			{
				if( isset( $aRelateds['products'] ) )
					$aRelateds['products'] = array_merge( $aRelateds['products'], explode( ',', $aGroupRelated['rproducts'] ?? '' ) );
				else
					$aRelateds['products'] = explode( ',', $aGroupRelated['rproducts'] ?? '' );
			}

			// Marcas
			if( $aGroupRelated['rbrands'] != '' )
			{
				if( isset( $aRelateds['brands'] ) )
					$aRelateds['brands'] = array_merge( $aRelateds['brands'], explode( ',', $aGroupRelated['rbrands'] ?? '' ) );
				else
					$aRelateds['brands'] = explode( ',', $aGroupRelated['rbrands'] ?? '' );
			}

			// Categorias
			if( $aGroupRelated['rcategories'] != '' )
			{
				$aExplCategories = explode( ',', $aGroupRelated['rcategories'] ?? '' );

				// Si tenemos relacionados de categorias
				if( $aGroupRelated['rcategories'] != '' && count( $aExplCategories ) > 0 )
				{
					// Recorremos las categorias relacionadas y obtenemos sus hijas
					foreach( $aExplCategories as $nIdCategory )
					{
						$aRCategories = explode( ',', getRecursiveIdCategories( $_aAllCategorias, $nIdCategory ) . $nIdCategory );

						if( isset( $aRelateds['categories'] ) )
							$aRelateds['categories'] = array_merge( $aRelateds['categories'], $aRCategories );
						else
							$aRelateds['categories'] = $aRCategories;
					}
				}
			}
		}
	}

	// Si no tenemos relacionados y tenemos activo la opción de busqueda reciproca
	if( count( $aRelateds ) <= 0 )
	{
		// Obtenemos todos los grupos activos
		$aGroupsRelated = tep_db_query( 'SELECT rpg.related_groups_id, rpg.idproducts as gproducts, rpg.idcategories as gcategories, rpg.idbrands as gbrands
										 FROM related_products_groups rpg
										 LEFT JOIN related_groups_to_related rtr ON (rpg.related_groups_id = rtr.related_groups_id)
										 WHERE rpg.related_status = 1
										 AND rtr.related_groups_id IS NULL' );

		// Recorremos los grupos
		while( $aGroupRelated = tep_db_fetch_array( $aGroupsRelated ) )
		{
			// Convertimos a array los valores de productos, categorias y marcas
			$aGProducts = array_filter( explode( ',', $aGroupRelated['gproducts'] ?? '' ) );
			$aGCategories = array_filter( explode( ',', getRecursiveIdCategories( $_aAllCategorias, $aGroupRelated['gcategories'] ) . ($aGroupRelated['gcategories'] ?? '') ) );
			$aGBrands = array_filter( explode( ',', $aGroupRelated['gbrands'] ?? '' ) );

			// Si el producto está dentro de los productos, categorias o marcas del grupo, obtenemos sus relacionados
			if( in_array( (int)$nIdProducto, $aGProducts ) || in_array( (int)$current_category_id, $aGCategories ) || in_array( (int)($aProducto['manufacturers_id'] ?? 0), $aGBrands ) )
			{
				// Guardamos relaciones del grupo //

				// Productos individuales
				if( $aGroupRelated['gproducts'] != '' )
				{
					if( isset( $aRelateds['products'] ) )
						$aRelateds['products'] = array_merge( $aRelateds['products'], explode( ',', $aGroupRelated['gproducts'] ?? '' ) );
					else
						$aRelateds['products'] = explode( ',', $aGroupRelated['gproducts'] ?? '' );
				}

				// Marcas
				if( $aGroupRelated['gbrands'] != '' )
				{
					if( isset( $aRelateds['brands'] ) )
						$aRelateds['brands'] = array_merge( $aRelateds['brands'], explode( ',', $aGroupRelated['gbrands'] ?? '' ) );
					else
						$aRelateds['brands'] = explode( ',', $aGroupRelated['gbrands'] ?? '' );
				}

				// Categorias
				if( $aGroupRelated['gcategories'] != '' )
				{
					$aExplCategories = explode( ',', $aGroupRelated['gcategories'] ?? '' );

					// Si tenemos relacionados de categorias
					if( $aGroupRelated['gcategories'] != '' && count( $aExplCategories ) > 0 )
					{
						// Recorremos las categorias relacionadas y obtenemos sus hijas
						foreach( $aExplCategories as $nIdCategory )
						{
							$aRCategories = explode( ',', getRecursiveIdCategories( $_aAllCategorias, $nIdCategory ) . $nIdCategory );

							if( isset( $aRelateds['categories'] ) )
								$aRelateds['categories'] = array_merge( $aRelateds['categories'], $aRCategories );
							else
								$aRelateds['categories'] = $aRCategories;
						}
					}
				}
			}
		}
	}

	if( count( $aRelateds ) > 0 )
	{
		// Limpiamos resultados duplicados y convertimos los arrays de relacionados a string separados por coma para las busquedas de productos
		$sIdRProducts = '';
		$sIdRCategories = '';
		$sIdRBrands = '';

		// Si tenemos relacionados por productos individuales
		if( isset( $aRelateds['products'] ) && count( $aRelateds['products'] ) > 0 )
		{
			$aRelateds['products'] = array_values( array_unique ( $aRelateds['products'] ) );
			$sIdRProducts = implode( ',', $aRelateds['products'] );
			$sIdRProducts = (substr($sIdRProducts, -1) == ',' ? substr($sIdRProducts, 0, -1) : $sIdRProducts);
		}

		// Si tenemos relacionados por categorias
		if( isset( $aRelateds['categories'] ) && count( $aRelateds['categories'] ) > 0 )
		{
			$aRelateds['categories'] = array_values( array_unique ( $aRelateds['categories'] ) );
			$sIdRCategories = implode( ',', $aRelateds['categories'] );
		}

		// Si tenemos relacionados por marcas
		if( isset( $aRelateds['brands'] ) && count( $aRelateds['brands'] ) > 0 )
		{
			$aRelateds['brands'] = array_values( array_unique ( $aRelateds['brands'] ) );
			$sIdRBrands = implode( ',', $aRelateds['brands'] );
		}

		// Obtenemos el orden para la prioridad
		if( RELATED_PRODUCTS_PRIORITY == 1 )
			$sSqlPriority = getOrderCasePriority( 'p.products_id', 'ptc.categories_id', 'p.manufacturers_id', $sIdRProducts, $sIdRCategories, $sIdRBrands );
		elseif( RELATED_PRODUCTS_PRIORITY == 2 )
			$sSqlPriority = getOrderCasePriority( 'p.products_id', 'p.manufacturers_id', 'ptc.categories_id', $sIdRProducts, $sIdRBrands, $sIdRCategories );
		elseif( RELATED_PRODUCTS_PRIORITY == 3 )
			$sSqlPriority = getOrderCasePriority( 'ptc.categories_id', 'p.products_id', 'p.manufacturers_id', $sIdRCategories, $sIdRProducts, $sIdRBrands );
		elseif( RELATED_PRODUCTS_PRIORITY == 4 )
			$sSqlPriority = getOrderCasePriority( 'ptc.categories_id', 'p.manufacturers_id', 'p.products_id', $sIdRCategories, $sIdRBrands, $sIdRProducts );
		elseif( RELATED_PRODUCTS_PRIORITY == 5 )
			$sSqlPriority = getOrderCasePriority( 'p.manufacturers_id', 'p.products_id', 'ptc.categories_id', $sIdRBrands, $sIdRProducts, $sIdRCategories );
		elseif( RELATED_PRODUCTS_PRIORITY == 6 )
			$sSqlPriority = getOrderCasePriority( 'p.manufacturers_id', 'ptc.categories_id', 'p.products_id', $sIdRBrands, $sIdRCategories, $sIdRProducts );

		// Consulta
		$sSql = 'SELECT ' . SQL_SELECT . ' p.products_id, p.products_ship_free, pd.products_name, p.products_model,
					p.products_price, p.products_quantity, p.products_tax_class_id, p.products_status, p.products_image,
					(' . $sSqlPriority . ') as priority
				 FROM products p
				 INNER JOIN products_description pd ON( p.products_id = pd.products_id )
				 INNER JOIN products_to_categories ptc ON( p.products_id = ptc.products_id )
				 WHERE pd.language_id = ' . (int)$languages_id . '
				 AND p.products_id !=  ' . (int)$nIdProducto . '
				 AND p.products_status = 1
				 AND ('
						. ($sIdRProducts != '' ? 'p.products_id IN( ' . $sIdRProducts . ' )' : '')
						. ($sIdRCategories != '' ? ($sIdRProducts != '' ? ' OR ' : '') . 'ptc.categories_id IN( ' . $sIdRCategories . ' )' : '')
						. ($sIdRBrands != '' ? ($sIdRProducts != '' || $sIdRCategories != '' ? ' OR ' : '') . 'p.manufacturers_id IN( ' . $sIdRBrands . ' )' : '') .
					 ')
				 GROUP BY p.products_id
				 ORDER BY priority ASC, ' . RELATED_PRODUCTS_ORDERBY . ' ASC' .
				 (RELATED_PRODUCTS_TOGETHER == 1 && (int)$nElementos > 0 ? ' LIMIT ' . (int)$nElementos : '');

		// Obtenemos los productos cambiando el precio segun tipo de cliente
		$aAux = changePriceCustomer( $sSql, array( 'PAGINAR' => false, 'PRODUCTS_ARRAY' => true ) );

		// Añadiamos los posibles productos obtenidos
		$aProductos = $aAux['PRODUCTOS'];

		// Si queremos los productos mezclados, tenemos un limite y hemos superado el limite de productos a mostrar
		if( RELATED_PRODUCTS_TOGETHER == 2 && (int)$nElementos > 0 && count( $aProductos ) > $nElementos )
		{
			// Reducimos el array hasta el límite
			$aProductos = array_slice( $aProductos, 0, $nElementos );

			// Ordenamos el array
			$aOrderBy = array();
			foreach ($aProductos as $nKey => $aRow)
				$aOrderBy[$nKey] = $aRow[RELATED_PRODUCTS_ORDERBY];

			array_multisort($aOrderBy, SORT_ASC, $aProductos);
		}
	}

	// Si tenemos un mínimo y no hemos obtenido totalmente todos los productos, obtenemos los productos de alguna de las categorias
	if( $cPath != '' && (int)RELATED_PRODUCTS_MIN_SHOW > 0 && count( $aProductos ) == 0 )
	{
		// Recorremos los productos obtenidos hasta ahora para guardar los IDs y excluirlos en las nuevas búsquedas
		foreach( $aProductos as $aProducto )
			$aExcludes[] = $aProducto['products_id'];

		$aCategorias = explode( '_', $cPath );
		$aCategorias = array_reverse( $aCategorias );

		foreach( $aCategorias as $sCategoria )
		{
			// Buscamos las categorias hijas
			$sCategoriaIn = getRecursiveIdCategories( $_aAllCategorias, $sCategoria ) . $sCategoria;

			// Si no contiene categorias hijas buscamos solo en la actual
			if( $sCategoriaIn == '' )
				$sCategoriaIn = $sCategoria;

			$sSql = 'SELECT p.products_id, p.products_ship_free, pa.products_name, p.products_model, p.products_date_available, p.products_price, p.products_quantity, p.products_status, p.products_tax_class_id, p.products_image
					 FROM ' . TABLE_PRODUCTS_DESCRIPTION . ' pa
					 INNER JOIN ' . TABLE_PRODUCTS . ' p on (p.products_id = pa.products_id)
					 INNER JOIN ' . TABLE_PRODUCTS_TO_CATEGORIES . '  ptc on ptc.products_id=p.products_id
					 WHERE language_id = ' . (int)$languages_id . '
					 AND products_status = 1
					 AND p.products_id NOT IN( ' . implode( ',', $aExcludes ) . ' )
					 AND ptc.categories_id IN( ' . $sCategoriaIn . ' )
					 ORDER BY rand() LIMIT ' . (RELATED_PRODUCTS_MIN_SHOW - count( $aProductos ));

			// Obtenemos los productos cambiando el precio segun tipo de cliente
			$aAux = changePriceCustomer( $sSql, array( 'PAGINAR' => false, 'PRODUCTS_ARRAY' => true ) );
			$aAux = $aAux['PRODUCTOS'];

			// Si hemos obtenido resultados
			if( count( $aAux ) > 0 )
			{
				// Si tenemos un limite y hemos superado el limite de productos a mostrar, reducimos el array hasta el límite
				if( (int)$nElementos > 0 && (count( $aAux ) + count( $aProductos )) > $nElementos )
				{
					$aAux = array_slice( $aAux, 0, ($nElementos - count( $aProductos )) );
					break;
				}

				// Añadiamos los productos obtenidos
				$aProductos = array_merge( $aProductos, $aAux );

				// Si ya tenemos los productos deseados
				if( count( $aProductos ) >= RELATED_PRODUCTS_MIN_SHOW )
					break;

				// Añadimos nuevamente los IDs obtenidos para excluirlos en la próxima búsqueda
				foreach( $aProductos as $aProducto )
					$aExcludes[] = $aProducto['products_id'];
			}
		}

		// Si queremos los productos mezclados
		if( RELATED_PRODUCTS_TOGETHER == 2 )
		{
			// Ordenamos el array
			$aOrderBy = array();
			foreach ($aProductos as $nKey => $aRow)
				$aOrderBy[$nKey] = $aRow[RELATED_PRODUCTS_ORDERBY];

			array_multisort($aOrderBy, SORT_ASC, $aProductos);
		}
	}

	// Si hemos obtenido resultados pintamos
	if( count( $aProductos ) > 0 )
	{
		// Idioma
		include( DIR_WS_LANGUAGES . $language . '/' . basename(__FILE__) );
		$nAuxIndexEachProducts = 0;

		// Template
		include( DIR_THEME. 'html/components/' . basename(__FILE__) );
	}
?>