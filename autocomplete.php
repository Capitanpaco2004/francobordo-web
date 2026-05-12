<?php
	// Includes
	include( 'includes/application_top.php' );
	include( DIR_WS_LANGUAGES . $language . '/' . FILENAME_ADVANCED_SEARCH );

	// Cabecera y columna, si es una peticon ajax no mostramos
	if( ! isAjax() )
	{
		ob_start();
		include( DIR_THEME. 'html/header.php' );
		include( DIR_THEME. 'html/column_left.php' );
	}

	// Defines
	define( 'SEARCH_EAN', 'Si' );
	define( 'SEARCH_ID', 'Si' );
	define( 'SEARCH_SHOW_FILTERS', 'Si' );

	// Variables
	$sSearch = strtolower( array_key_exists( 'search', $_GET ) ? tep_db_prepare_input( $_GET['search'] ) : '' );
	$aSearchPlural = array();
	$aSearchSingular = array();
	$aWords = null;
	$aOrders = array();
	$sAction = (array_key_exists( 'a', $_GET ) ? tep_db_prepare_input( $_GET['a'] ) : false);
	$sCategories = (array_key_exists( 'categories', $_GET ) && is_numeric( $_GET['categories'] ) ? tep_db_prepare_input( (int)$_GET['categories'] ) : false);
	$sManufacturers = (array_key_exists( 'manufacturers', $_GET ) && is_numeric( $_GET['manufacturers'] ) ? tep_db_prepare_input( (int)$_GET['manufacturers'] ) : false);
	$nOrder = (array_key_exists( 'order', $_GET ) ? tep_db_prepare_input( $_GET['order'] ) : false);
	$sTags = '';
	$bBuscarId = false;
	$bSearchAttr = false;

	// Buscamos intentos de SQL inyection
	if( preg_match( '/(SELECT)(.*)(FROM|WHERE)/i', $sSearch ) )
		tep_redirect( 'error404.html' );

	// Si no nos envían nada a buscar, mostramos mensaje de error
	if( $sSearch == '' || strlen($sSearch) <= 1 )
	{
		include( DIR_THEME_ROOT . 'html/templates/search.php' );
		include( DIR_THEME. 'html/column_right.php' );
		include( DIR_THEME. 'html/footer.php' );
		include( DIR_WS_INCLUDES . 'application_bottom.php' );

		exit(0);
	}

	// Si tenemos búsqueda por EAN / Modelo / ID
	if( SEARCH_EAN == 'Si' || SEARCH_ID == 'Si' )
	{
		// Consulta de productos por EAN / Modelo / ID
		$sQuery = 'SELECT p.products_id, pd.products_name FROM products p INNER JOIN products_description pd ON (p.products_id = pd.products_id) WHERE ';

		// Si tenemos búsqueda por EAN / Modelo
		if( SEARCH_EAN == 'Si' )
			$sQuery .= 'LCASE( p.products_model ) = "' . $sSearch . '" OR LCASE( p.product_ean ) = "' . $sSearch . '"';

		// Añadimos el OR
		if( SEARCH_EAN == 'Si' && SEARCH_ID == 'Si' )
			$sQuery .= ' OR ';

		// Si tenemos búsqueda por ID
		if( SEARCH_ID == 'Si' )
			$sQuery .= 'LCASE( p.products_id ) = "' . $sSearch . '"';

		// AÃ±adimos comprobación de estado
		$sQuery .= ' AND p.products_status = 1 GROUP BY p.products_id;';

		// Lanzamos la consulta
		$aSearch = tep_db_query( $sQuery );

		// Si hemos encontrado un producto por su EAN / Modelo
		if( tep_db_num_rows( $aSearch ) > 0 )
		{
			// Registro
			$aSearch = tep_db_fetch_array( $aSearch );

			// Redireccionamos, si no es AJAX
			if( ! isAjax() )
				tep_redirect( tep_href_link( FILENAME_PRODUCT_INFO, 'products_id=' . $aSearch['products_id'] ) );
			// Mostramos el resultado en AJAX
			else
			{
				$sWhere = ' p.products_id = "' . (int)$aSearch['products_id'] . '" ';
				$bBuscarId = true;
			}
		}
		else if( tep_db_num_rows( $aSearch ) == 0 )
		{
			// Consulta de productos por EAN / Modelo / ID
			$sQuery = 'SELECT p.products_id, CONCAT( pd.products_name, " - ", pov.products_options_values_name) AS products_name FROM products p INNER JOIN products_description pd ON (p.products_id = pd.products_id) INNER JOIN products_attributes pa ON (p.products_id = pa.products_id) INNER JOIN products_options_values pov ON (pa.options_values_id = pov.products_options_values_id AND pov.language_id = "' . (int)$languages_id . '") WHERE ';

			// Where
			$sQuery .= 'LCASE( pa.products_attributes_ean ) = "' . $sSearch . '" OR LCASE( pa.reference ) = "' . $sSearch . '"';

			// Añadimos comprobación de estado
			$sQuery .= ' AND p.products_status = 1 GROUP BY p.products_id;';

			// Lanzamos la consulta
			$aSearch = tep_db_query( $sQuery );

			// Si hemos encontrado un producto por su EAN / Modelo
			if( tep_db_num_rows( $aSearch ) > 0 )
			{
				// Registro
				$aSearch = tep_db_fetch_array( $aSearch );

				// Redireccionamos, si no es AJAX
				if( ! isAjax() )
					tep_redirect( tep_href_link( FILENAME_PRODUCT_INFO, 'products_id=' . $aSearch['products_id'] ) );
				// Mostramos el resultado en AJAX
				else
				{
					$sWhere = ' p.products_id = "' . (int)$aSearch['products_id'] . '" ';
					$bSearchAttr = true;
					$bBuscarId = true;
				}
			}
		}
	}

	// Creamos el array de order
	$aOrders = array(
		array( 'id' => '1', 'text' => ADVANCED_SEARCH_FILTRO_ORDENAR_POR_NINGUNO ),
		array( 'id' => '2', 'text' => ADVANCED_SEARCH_FILTRO_ORDENAR_POR_NOMBRE_ASC ),
		array( 'id' => '3', 'text' => ADVANCED_SEARCH_FILTRO_ORDENAR_POR_NOMBRE_DESC ),
		array( 'id' => '4', 'text' => ADVANCED_SEARCH_FILTRO_ORDENAR_POR_PRECIO_ASC ),
		array( 'id' => '5', 'text' => ADVANCED_SEARCH_FILTRO_ORDENAR_POR_PRECIO_DESC ),
	);

	// Breadcrumb
	$breadcrumb->add( ADVANCED_SEARCH_SUB_BREADCRUMB, tep_href_link( FILENAME_SEARCH, tep_get_all_get_params(), 'NONSSL', true, false ) );

	// Variables
	$aProductos = array();
	$sFinalPrice = 'p.products_price';

	// Variables SQL
	$sSelect = '';
	$sJoins = '';
	if( !isset( $sWhere ) ) $sWhere = '';
	$sWherePrecio = '';
	$sOrder = '';

	// Construimos los campos select
	$sSelect = ' p.products_id, p.products_model, pd.products_description, p.products_tax_class_id, pd.products_name, p.products_quantity, p.products_image, p.products_date_available';

	// Construimos los joins
	$sJoins = TABLE_PRODUCTS . ' p ';
	$sJoins .= 'INNER JOIN ' . TABLE_PRODUCTS_DESCRIPTION . ' pd ON (p.products_id = pd.products_id AND pd.language_id = "' . (int)$languages_id . '") ';

	// Si tenemos filtro
	if( SEARCH_SHOW_FILTERS == 'Si' )
	{
		// AÃ±adimos al select las categorías y fabricantes
		$sSelect .= ', pc.categories_id, cd.categories_name, m.manufacturers_id, m.manufacturers_name, m.manufacturers_image';

		// AÃ±adimos en los joins las categorías y fabricantes
		$sJoins .= 'LEFT OUTER JOIN ' . TABLE_PRODUCTS_TO_CATEGORIES . ' pc ON (p.products_id = pc.products_id) ';
		$sJoins .= 'LEFT OUTER JOIN ' . TABLE_CATEGORIES_DESCRIPTION . ' cd ON (pc.categories_id = cd.categories_id AND cd.language_id = "' . (int)$languages_id . '") ';
		$sJoins .= 'LEFT OUTER JOIN ' . TABLE_MANUFACTURERS . ' m ON (p.manufacturers_id = m.manufacturers_id) ';
	}

	$sSelect .= ', IF (products_quantity>0, 1, 0) as disponibilidad, p.products_price ';

	// Si es una búsqueda con atributos
	if( $bSearchAttr )
	{
		// Modificamos el select
		$sSelect = preg_replace( '/(pd\.products_name)/i', 'CONCAT( pd.products_name, " - ", pov.products_options_values_name ) AS products_name', $sSelect );
		$sSelect = preg_replace( '/(p\.products_price)/i', '( p.products_price + pa.options_values_price ) AS products_price', $sSelect );

		// Añadimos los Joins
		$sJoins .= 'INNER JOIN products_attributes pa ON (p.products_id = pa.products_id AND (LCASE( pa.products_attributes_ean ) = "' . $sSearch . '" OR LCASE( pa.reference ) = "' . $sSearch . '")) ';
		$sJoins .= 'INNER JOIN products_options_values pov ON (pa.options_values_id = pov.products_options_values_id AND pov.language_id = "' . (int)$languages_id . '")';
	}

	// Si NO queremos buscar solo por ID
	if( ! $bBuscarId )
	{
		// Separamos las palabras
		$aWords = preg_split( "/[\s]|[,]|[.]|[-]/", $sSearch, -1, PREG_SPLIT_NO_EMPTY );

		// Formateamos las palabras de la búsqueda
		foreach( $aWords as $aWord )
		{
			// Eliminamos las palabras demasiado cortas (pronombres, etc)
			if( strlen( $aWord ) > 1 )
			{
				// Si es una preposición, artículo o nexo continuamos
				if( in_array( $aWord, array( 'a', 'ante', 'bajo', 'cabe', 'con', 'contra', 'de', 'desde', 'en', 'entre', 'hacia', 'hasta', 'para', 'por', 'según', 'segun', 'sin', 'so', 'sobre', 'tras', 'del', 'al', 'el', 'la', 'las', 'los', 'un', 'unos', 'un', 'una', 'unas', 'y', 'u', 'o', 'e' ) ) )
					continue;

				// Si es un número
				if( is_numeric( $aWord ) )
				{
					// AÃ±adimos el número en singular y plural
					$aSearchPlural[] = $aWord;
					$aSearchSingular[] = $aWord;
					continue;
				}

				// Comprobamos si la palabra está en plural (si termina en -s, -es, -ces), para obtener su singular
				if( preg_match( '/s$/', $aWord ) || preg_match( '/es$/', $aWord ) || preg_match( '/ces$/', $aWord ) )
				{
					// AÃ±adimos al array plural
					$aSearchPlural[] = $aWord;

					// AÃ±adimos al array singular
					if( preg_match( '/ces$/', $aWord ) )
						$aSearchSingular[] = preg_replace( '/ces$/', 'z', $aWord );
					else if( preg_match( '/es$/', $aWord ) )
						$aSearchSingular[] = preg_replace( '/es$/', '', $aWord );
					else if( preg_match( '/s$/', $aWord ) )
						$aSearchSingular[] = preg_replace( '/s$/', '', $aWord );
				}
				// Si la palabra está en singular
				else
				{
					// AÃ±adimos al array singular
					$aSearchSingular[] = $aWord;

					// Obtenemos su plural
					if( preg_match( '/[a]$|[e]$|[o]$/', $aWord ) )
						$aSearchPlural[] = $aWord . 's';
					else if( preg_match( '/[z]$/', $aWord ) )
						$aSearchPlural[] = preg_replace( '/z$/', 'ces', $aWord );
					else
						$aSearchPlural[] = $aWord . 'es';
				}
			}
		}

		// WHERE //

		// Recorremos las palabras y las aÃ±adimos al WHERE para el nombre
		foreach( $aSearchSingular as $nWord => $aWord )
			$sWhere .= '( ( LCASE( products_name ) LIKE "%' . tep_db_input( $aSearchSingular[$nWord] ) . '%" ) OR ( LCASE( products_name ) LIKE "%' . tep_db_input( $aSearchPlural[$nWord] ) . '%" ) ) AND ';
		$sWhere = substr( $sWhere, 0, -4 );

		// Si queremos buscar en la descripción
		if( SEARCH_DESCRIPTION == 'Si' )
		{
			// Recorremos las palabras y las aÃ±adimos al WHERE para la descripción
			$sWhere .= 'OR ';
			foreach( $aSearchSingular as $nWord => $aWord )
				$sWhere .= '( ( LCASE( products_description ) LIKE "%' . tep_db_input( $aSearchSingular[$nWord] ) . '%" ) OR ( LCASE( products_description ) LIKE "%' . tep_db_input( $aSearchPlural[$nWord] ) . '%" ) ) AND ';
			$sWhere = substr( $sWhere, 0, -4 );
		}
	}

	// Estado del producto
	$sWhere = 'p.products_status = 1 AND (' . $sWhere . ')';

	// Categoría
	if( $sCategories !== false && $sCategories != '' )
	{
		// Obtenemos las categorias hijo
		$aCategories = getIdCategoriasHijasRecursivoByIdCategoriaPadre( $sCategories );
		$aCategories = array_filter( array_map( 'trim', explode( ',', $aCategories ) ) );
		$aCategories[] = $sCategories;

		// Añadimos al WHERE
		$sWhere .= ' AND (';
		foreach( $aCategories as $aCategory )
			$sWhere .= 'pc.categories_id = ' . $aCategory . ' OR ';
		$sWhere = substr( $sWhere, 0, -4 ) . ') ';
	}

	// Fabricante
	if( $sManufacturers != '' )
		$sWhere .= ' AND p.manufacturers_id = ' . $sManufacturers . ' ';

	// Generamos el orden
	$sOrder = 'ORDER BY ';

	// Según tipo de búsqueda
	switch( $nOrder )
	{
		case 3:
			$sOrder .= 'disponibilidad DESC, products_name DESC';
		break;

		case 4:
			$sOrder .= 'disponibilidad DESC, products_price ASC';
		break;

		case 5:
			$sOrder .= 'disponibilidad DESC, products_price DESC';
		break;

		case 2:
		default:
			$sOrder .= 'disponibilidad DESC, products_name ASC';
		break;
	}

	// Si tenemos una búsqueda exhaustiva
	if( SEARCH_EXHAUSTIVE == 'Si' && count( $aSearchSingular ) > 1 )
	{
		// Obtenemos la primera mitad de la UNION
		$sSql1 = '( SELECT ' . $sSelect . ', 1 AS relevance FROM ' . $sJoins . ' WHERE ' . $sWhere . $sWherePrecio . ' )';

		// Cambiamos los AND por OR
		$sSql2 = preg_replace( '/%\" \) \) AND/i', '%" ) ) OR', $sSql1 );
		// Cambiamos el número de relevancia
		$sSql2 = preg_replace( '/1 AS relevance/i', '2 AS relevance', $sSql2 );

		// AÃ±adimos la relevancia al orden
		$sOrder = preg_replace( '/ORDER BY/i', 'ORDER BY relevance ASC, ', $sOrder );

		// Obtenemos la consulta exhaustiva
		$sSql = 'SELECT * FROM ( ' . $sSql1 . ' UNION ' . $sSql2 . ' ) AS search GROUP BY products_id ' . $sOrder;

		// Obtenemos el conteo de la consulta exhaustiva
		$sSqlCount = 'SELECT COUNT(products_id) AS total FROM ( SELECT products_id FROM ( ' . $sSql1 . ' UNION ' . $sSql2 . ' ) AS search GROUP BY products_id ) AS counting;';
	}
	else
	{
		// Construimos la consulta SQL
		$sSql = 'SELECT ' . $sSelect . ' FROM ' . $sJoins . ' WHERE ' . $sWhere . $sWherePrecio . ' GROUP BY p.products_id ' . $sOrder;

		// Indicamos que la consulta de conteo se regenere
		$sSqlCount = false;
	}

	// Cambiamos el SQL si existe un filtro
	changeFilter( $sSql );

	// Cambiamos filtro de categorias
	if( FILTERS_ACTIVE == 'Si' )
		changeFilterCategorie( $sSql, array( 'RECORDAR_SQL' => true ) );

	// Obtenemos el paginador y los productos
	$aAux = changePriceCustomer( $sSql, array( 'COUNT_KEY' => 'p.products_id', 'QUERY_COUNT' => $sSqlCount, 'RECORDAR_SQL' => (FILTERS_ACTIVE == 'Si' ? true : false) ) );
	$aProductos = $aAux['PRODUCTOS'];
	$aPager = $aAux['PAGE_PRODUCTOS'];
	$nProductosTotal = $aAux['TOTAL'];

	// Si solo hemos encontrado un producto, redireccionamos
	if( $sSearch != '' && $nProductosTotal == 1 && ! isset( $_GET['page'] ) && ! isAjax() )
	{
		$aProducto = eachProducts();
		tep_redirect( tep_href_link( FILENAME_PRODUCT_INFO, 'products_id=' . $aProducto['products_id'] ) );
	}

	// Si tenemos filtro
	if( SEARCH_SHOW_FILTERS == 'Si' )
	{
		// Consulta para obtener los filtros
		$aFilters = tep_db_query( $sSql );

		// Variables
		$aElements = array();
		$aElements['categories'] = array();
		$aElements['manufacturers'] = array();

		// Si tenemos categorías / fabricantes
		if( tep_db_num_rows( $aFilters ) > 0 )
		{
			// Recorremos las categorías / fabricantes
			while( $aFilter = tep_db_fetch_array( $aFilters ) )
			{
				// Si tenemos categoría
				if( $aFilter['categories_id'] != '' )
				{
					// Si NO existe la categoría
					if( ! key_exists( $aFilter['categories_id'], $aElements['categories'] ) )
					{
						// AÃ±adimos al array
						$aElements['categories'][$aFilter['categories_id']] = array( 'name' => $aFilter['categories_name'], 'qty' => 1 );

						// AÃ±adimos a los tags
						if( $aFilter['categories_id'] == $sCategories )
							$sTags .= '<a href="' . tep_href_link( 'search.php', tep_get_all_get_params( array( 'categories' ) ), 'NONSSL', true, false ) . '" rel="categories">' . $aFilter['categories_name'] . '</a>';
					}
					// Si SI existe la categoría
					else
						$aElements['categories'][$aFilter['categories_id']]['qty'] += 1;
				}

				// Si tenemos fabricante
				if( $aFilter['manufacturers_id'] != '' )
				{
					// Si NO existe el fabricante
					if( ! key_exists( $aFilter['manufacturers_id'], $aElements['manufacturers'] ) )
					{
						// AÃ±adimos al array
						$aElements['manufacturers'][$aFilter['manufacturers_id']] = array( 'name' => $aFilter['manufacturers_name'], 'image' => $aFilter['manufacturers_image'], 'qty' => 1 );

						// AÃ±adimos a los tags
						if( $aFilter['manufacturers_id'] == $sManufacturers )
							$sTags .= '<a href="' . tep_href_link( 'search.php', tep_get_all_get_params( array( 'manufacturers' ) ), 'NONSSL', true, false ) . '" rel="manufacturers">' . $aFilter['manufacturers_name'] . '</a>';
					}
					// Si SI existe la categoría
					else
						$aElements['manufacturers'][$aFilter['manufacturers_id']]['qty'] += 1;
				}
			}
		}

		// Guardamos en variable de filtros
		$aFilters = $aElements;
	}

	// Theme
	if( $sAction == 'autocomplete' && isAjax() )
		include( DIR_THEME_ROOT . 'html/templates/search_autocomplete.php' );
	elseif( isAjax() && $sAction == 'filter' )
		include( DIR_THEME_ROOT . 'html/templates/search_filter.php' );
	else
		include( DIR_THEME_ROOT . 'html/templates/search.php' );


	// Si no es AJAX
	if( ! isAjax() )
	{
		// Pie y columna, si es una peticon ajax no mostramos
		include( DIR_THEME. 'html/column_right.php' );
		include( DIR_THEME. 'html/footer.php' );
		include( DIR_WS_INCLUDES . 'application_bottom.php' );
	}

?>