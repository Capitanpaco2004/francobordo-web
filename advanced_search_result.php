<?php
	// Includes
	include( 'includes/application_top.php' );
	include( DIR_WS_LANGUAGES . $language . '/' . FILENAME_ADVANCED_SEARCH );

	// Variables
	$sSearch = tep_db_input( strtolower( ( tep_db_prepare_input( $_GET['buscar'] ) ) ) );
	$sSearch2 = '';
	$aSearchPlural = array();
	$aSearchSingular = array();
	$aWords = null;
	$sTime = new DateTime('NOW');
	$sView = 'search' . preg_replace( '/(\.| )/i', '', microtime() );

	$sCategory = ( tep_db_prepare_input( $_GET['category'] ) );
	$sManufacturer = tep_db_prepare_input( $_GET['manufacturer'] );
	$bDescription = (isset( $_GET['description'] ) ? ( tep_db_prepare_input( $_GET['description'] ) ) : (isset( $_GET['search_in_description'] ) ? ( tep_db_prepare_input( $_GET['search_in_description'] ) ) : 0));
	$sPriceFrom = ( tep_db_prepare_input( $_GET['precio_desde'] ) );
	$sPriceTo = ( tep_db_prepare_input( $_GET['precio_hasta'] ) );
	$sPriceBefore = ( tep_db_prepare_input( $_GET['precio_anterior'] ) );
	$nOrder = ( tep_db_prepare_input( $_GET['order'] ) );
	$sTags = '';

	// Consulta de productos por modelo
	$aSearch = tep_db_query( 'select products_id from products where products_model = "' . $sSearch . '" AND products_status = 1;' );

	// Si hemos encontrado un producto por su modelo
	if( tep_db_num_rows( $aSearch ) > 0 )
	{
		// Registro
		$aSearch = tep_db_fetch_array( $aSearch );

		// Redireccionamos
		tep_redirect( tep_href_link( FILENAME_PRODUCT_INFO, 'products_id=' . $aSearch['products_id'] ) );
	}

	/*
	Consulta de productos por EAN @daniel.lucia
	 */
	if ($sSearch != '') {
		 $aSearch = tep_db_query( 'select products_id from products where product_ean = "' . $sSearch . '" AND products_status = 1;' );

		 // Si hemos encontrado un producto por su modelo
		 if( tep_db_num_rows( $aSearch ) == 1 )
		 {
		 	// Registro
		 	$aSearch = tep_db_fetch_array( $aSearch );

		 	// Redireccionamos
		 	tep_redirect( tep_href_link( FILENAME_PRODUCT_INFO, 'products_id=' . $aSearch['products_id'] ) );
		 }
	 }

	// Comprobamos si existe la referencia de ser asi redirect
	$aDatosAux = tep_db_query( 'select distinct products_id from products_attributes where reference = "' . $sSearch . '"' );

	// Si hemos encontrado un producto por la referencia de su atributo
	if( tep_db_num_rows( $aDatosAux ) > 0 )
	{
		$aDatosAux = tep_db_fetch_array( $aDatosAux );

		tep_redirect( tep_href_link( FILENAME_PRODUCT_INFO, 'products_id=' . $aDatosAux['products_id'] ) );
		exit();
	}

	// Filtro categoría
	if( ! is_numeric( $sCategory ) )
		$sCategory = '';

	// Filtro fabricante
	if( is_array( $sManufacturer ) )
		$sManufacturer = $sManufacturer[0];
	if( ! is_numeric( $sManufacturer ) )
		$sManufacturer = '';

	// Obtenemos el filtro de los precios
	if( $sPriceBefore == '' )
	{
		$sPriceFrom = '';
		$sPriceTo = '';
	}
	else
	{
		$sPriceBefore = explode( '_', $sPriceBefore );
		$sPriceFrom = $sPriceBefore[0];
		$sPriceTo = $sPriceBefore[1];
	}

	$sPriceFromPosion = $sPriceFrom;
	$sPriceToPosion = $sPriceTo;

	// Si el precio desde contiene valor y no es numerico retornamos
	if( $sPriceFrom != '' && !is_numeric( $sPriceFrom ) )
		tep_redirect( tep_href_link( FILENAME_ADVANCED_SEARCH, tep_get_all_get_params(), 'NONSSL', true, false ) );

	// Si el precio hasta contiene valor y no es numerico retornamos
	if( $sPriceTo != '' && !is_numeric( $sPriceTo ) )
		tep_redirect( tep_href_link( FILENAME_ADVANCED_SEARCH, tep_get_all_get_params(), 'NONSSL', true, false ) );

	// Si contenemos precio desde y hasta, comprobamos que desde no sea mayor que hasta
	if( $sPriceFrom != '' && $sPriceTo != '' && $sPriceFrom > $sPriceTo )
		tep_redirect( tep_href_link( FILENAME_ADVANCED_SEARCH, tep_get_all_get_params(), 'NONSSL', true, false ) );

	// Si no nos envian nada a buscar retornamos al formulario de busqueda
	if( $sSearch == '' && $sPriceTo == '' && $sPriceFrom == '' )
	{
		$messageStack->addSession( 'error_search', ERROR_AT_LEAST_ONE_INPUT );
		tep_redirect( tep_href_link( FILENAME_ADVANCED_SEARCH, tep_get_all_get_params(), 'NONSSL', true, false ) );
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
	$breadcrumb->add( ADVANCED_SEARCH_BREADCRUMB, tep_href_link( FILENAME_ADVANCED_SEARCH ) );
	$breadcrumb->add( ADVANCED_SEARCH_SUB_BREADCRUMB, tep_href_link( FILENAME_ADVANCED_SEARCH_RESULT, tep_get_all_get_params(), 'NONSSL', true, false ) );

	// Cabecera y columna, si es una peticon ajax no mostramos
	if( ! isAjax() )
	{
		ob_start();
		include( DIR_THEME. 'html/header.php' );
		include( DIR_THEME. 'html/column_left.php' );
	}

	// Variables
	$aProductos = array();
	$sFinalPrice = 'IF(s.specials_new_products_price IS NOT NULL and s.status = 1, s.specials_new_products_price, p.products_price) as products_price';

	// Variables SQL
	$sSelect = '';
	$sJoins = '';
	$sWhere = '';
	$sWherePrecio = '';
	$sOrder = '';

	// Construimos los campos select
	$sSelect = ' p.products_id, IF (products_quantity>0, 1, 0) as cantidad, pd.products_description, IF(s.specials_new_products_price IS NOT NULL and s.status = 1, s.specials_new_products_price, p.products_price) as products_price, IF(s.specials_new_products_price IS NOT NULL and s.status = 1, p.products_price, NULL) as products_price_anterior, p.products_tax_class_id, pd.products_name, p.products_quantity, p.products_image, c.categories_id, cd.categories_name, m.manufacturers_id, m.manufacturers_name, manufacturers_image';

	// Campo ordenar
	$sSelect .= ', MATCH (pd.products_name) AGAINST("' . $sSearch . '") AS order_query ';

	// Construimos los joins
	$sJoins = TABLE_PRODUCTS . ' p ';
	$sJoins .= 'inner join ' . TABLE_PRODUCTS_DESCRIPTION . ' pd on (p.products_id = pd.products_id and pd.language_id = "' . (int)$languages_id . '" and p.products_status = 1) ';
	$sJoins .= 'inner join ' . TABLE_PRODUCTS_TO_CATEGORIES . ' pc on (p.products_id = pc.products_id) ';
	$sJoins .= 'left outer join ' . TABLE_SPECIALS . ' s on (p.products_id = s.products_id AND s.start_date = (SELECT MAX(s2.start_date) FROM specials s2 WHERE s2.products_id = s.products_id AND s2.status = 1 AND DATE( NOW() ) >= DATE( s2.start_date ))) ';
	$sJoins .= 'inner join ' . TABLE_CATEGORIES . ' c on (pc.categories_id = c.categories_id) ';
	$sJoins .= 'inner join ' . TABLE_CATEGORIES_DESCRIPTION . ' cd on (c.categories_id = cd.categories_id and cd.language_id = "' . (int)$languages_id . '") ';
	$sJoins .= 'left outer join ' . TABLE_MANUFACTURERS . ' m on (p.manufacturers_id = m.manufacturers_id) ';

	// Empezamos con el primer where de la palabra a buscar
	$sWhere .= 'pd.products_name LIKE "%' . $sSearch . '%" COLLATE utf8_general_ci ';

	// Separamos las palabras
	$aWords = preg_split( "/[\s]|[,]|[.]|[-]|[)]|[(]/", $sSearch, -1, PREG_SPLIT_NO_EMPTY );

	// Nueva búsqueda

	// Formateamos las palabras de la búsqueda
	foreach( $aWords as $aWord )
	{
		// Eliminamos las palabras demasiado cortas (pronombres, etc)
		if( strlen( $aWord ) > 1 )
		{
			// Si es una preposición, artículo o nexo continuamos
			if( in_array( $aWord, array('a','ante','bajo','cabe','con','contra','de','desde','en','entre','hacia','hasta','para','por','según','segun','sin','so','sobre','tras','del','al') ) ) continue;
			if( in_array( $aWord, array('el', 'la', 'las', 'los', 'un', 'unos', 'un', 'una', 'unas') ) ) continue;
			if( in_array( $aWord, array('y', 'u', 'o', 'e') ) ) continue;

			// Si es un número
			if( is_numeric( $aWord ) )
			{
				// Añadimos el número en singular y plural
				$aSearchPlural[] = $aWord;
				$aSearchSingular[] = $aWord;
				$sSearch2 .= $aWord . ' ';
				continue;
			}

			// Comprobamos si la palabra está en plural (si termina en -s, -es, -ces), para obtener su singular
			if( preg_match( '/s$/', $aWord ) || preg_match( '/es$/', $aWord ) || preg_match( '/ces$/', $aWord ) )
			{
				// Añadimos al array plural
				$aSearchPlural[] = $aWord;

				// Añadimos al array singular
				if( preg_match( '/ces$/', $aWord ) )
				{
					$sSearch2 .= preg_replace( '/ces$/', 'z', $aWord ) . ' ';
					$aSearchSingular[] = preg_replace( '/ces$/', 'z', $aWord );
				}
				else if( preg_match( '/es$/', $aWord ) )
				{
					$sSearch2 .= preg_replace( '/es$/', '', $aWord ) . ' ';
					$aSearchSingular[] = preg_replace( '/es$/', '', $aWord );
				}
				else if( preg_match( '/s$/', $aWord ) )
				{
					$sSearch2 .= preg_replace( '/s$/', '', $aWord ) . ' ';
					$aSearchSingular[] = preg_replace( '/s$/', '', $aWord );
				}
			}
			// Si la palabra está en singular
			else
			{
				// Añadimos al array singular
				$aSearchSingular[] = $aWord;

				// Obtenemos su plural
				if( preg_match( '/[a]$|[e]$|[o]$/', $aWord ) )
					$aSearchPlural[] = $aWord . 's';
				else if( preg_match( '/[z]$/', $aWord ) )
					$aSearchPlural[] = preg_replace( '/z$/', 'ces', $aWord );
				else
					$aSearchPlural[] = $aWord . 'es';

				// Añadimos a la cadena de búsqueda
				$sSearch2 .= $aWord . ' ';
			}
		}
	}

	// Recomponemos el texto a buscar
	$sSearch2 = trim( $sSearch2 );

	// Creamos el array de keywords segun lo buscado
	tep_parse_search_string( $sSearch2, $aKeywords );

	// Si solo tenemos un keyword y es una palabra reservada
	if( sizeof( $aKeywords ) == 1 )
		if( in_array( $aKeywords[0], array( 'and', 'or', '(', ')' ) ) )
			unset( $aKeywords[0] );

	// Si tenemos keywords generamos el resultado
	if( isset( $aKeywords ) && ( sizeof( $aKeywords ) > 0 ) )
	{
		$sWhere .= " OR (";

		// Recorremos las keywords
		for( $nCont = 0, $nQty = sizeof( $aKeywords ); $nCont < $nQty; $nCont++ )
		{
			// Según el keyword
			switch( $aKeywords[$nCont] )
			{
				// Unión
				case '(':
				case ')':
				case 'and':
				case 'or':
					$sWhere .= ' ' . strtolower( $aKeywords[$nCont] ) . ' ';
				break;
				// Cadena
				default:
					$aKeyword = tep_db_prepare_input( $aKeywords[$nCont] );

					$sWhere .= '(MATCH (products_name) AGAINST ("*' . tep_db_input( $aKeyword ) . '*" IN BOOLEAN MODE)';
					$sWhere .= ')';
					$sWhere .= ' OR (MATCH (products_name) AGAINST ("*' . tep_db_input( $aKeyword ) . '*" IN BOOLEAN MODE)';
					$sWhere .= ')';
				break;
			}
		}

		$sWhere .= " )";
	}

	// Obtenemos todas las combinaciones para palabras plural y singular
	if( count( $aSearchSingular ) <= 5 )
	{
		$aSearchSingular = combinations( implode( ' ', $aSearchSingular ) );
		$aSearchPlural = combinations( implode( ' ', $aSearchPlural ) );
	}
	// Si la busqueda excede el tamaño máximo, preparamos para una búsqueda sin combinaciones
	else
	{
		$aSearchSingular = array( $aSearchSingular );
		$aSearchPlural = array( $aSearchPlural );
	}

	// Precio desde
	if( $sPriceFrom != '' )
		$sWherePrecio .= 'and ' . $sFinalPrice . ' >= ' . $sPriceFrom . ' ';

	// Precio hasta
	if( $sPriceTo != '' )
		$sWherePrecio .= 'and ' . $sFinalPrice . ' <= ' . $sPriceTo . ' ';

	// Categoría
	if( $sCategory != '' )
	{
		// Obtenemos las categorias hijo
		$sCategories = getIdCategoriasHijasRecursivoByIdCategoriaPadre( $sCategory );

		// Construimos el where
		$sWhere .= ($sCategories == '' ? 'and pc.categories_id = ' . $sCategory : 'and pc.categories_id in (' . $sCategory . ', ' . $sCategories . ')') . ' ';
	}

	// Fabricante
	if( $sManufacturer != '' )
		$sWhere .= 'and p.manufacturers_id = ' . $sManufacturer . ' ';

	// Construimos el order
	$sOrder = ' order by order_query desc';

	// Construimos la consulta SQL y creamos la vista
	tep_db_query( 'CREATE VIEW ' . $sView . ' AS select ' . $sSelect . ' from ' . $sJoins . ' where ' . $sWhere . $sWherePrecio . ' group by p.products_id ' . $sOrder );

	// Guardamos el select de la vista
	$sUnion = 'select ' . $sSelect . ', 1 as relevance from ' . $sJoins . ' where ' . $sWhere . $sWherePrecio . ' group by p.products_id';

	// Construimos el where
	$sWhere = '(';

	// Contruimos los likes
	foreach( $aSearchSingular as $aSearch )
		$sWhere .= 'LCASE( products_name ) LIKE "%' . implode( '%', $aSearch ) . '%" COLLATE utf8_general_ci OR ';
	foreach( $aSearchPlural as $aSearch )
		$sWhere .= 'LCASE( products_name ) LIKE "%' . implode( '%', $aSearch ) . '%" COLLATE utf8_general_ci OR ';

	// Construimos ahora para la descripcion del producto
	if( $bDescription == 1 )
	{
		// Busqueda para la descripción
		//$sWhere .= 'LCASE( products_description ) = "' . $sSearch . '" OR ';

		// Contruimos los likes
		foreach( $aSearchSingular as $aSearch )
			$sWhere .= 'LCASE( products_description ) LIKE "%' . implode( '%', $aSearch ) . '%" COLLATE utf8_general_ci OR ';
		foreach( $aSearchPlural as $aSearch )
			$sWhere .= 'LCASE( products_description ) LIKE "%' . implode( '%', $aSearch ) . '%" COLLATE utf8_general_ci OR ';
	}

	// Where
	//$sWhere .= 'LCASE( products_name ) LIKE "%' . implode( '%', explode( ' ', $sSearch ) ) . '%" OR ';
	//$sWhere .= 'LCASE( products_description ) LIKE "%' . implode( '%', explode( ' ', $sSearch ) ) . '%" OR ';

	// Eliminamos el ultimo OR
	$sWhere = substr( $sWhere, 0, -4 );

	// Cerramos paréntesis
	$sWhere .= ') ';

	// Generamos el orden
	$sOrder = 'order by relevance asc, ';

	// Según tipo de búsqueda
	switch( $nOrder )
	{
		case 1:
			$sOrder .= 'products_price asc';
		break;

		case 2:
			$sOrder .= 'products_name asc';
		break;

		case 3:
			$sOrder .= 'products_name desc';
		break;

		case 4:
			$sOrder .= 'products_price asc';
		break;

		case 5:
			$sOrder .= 'products_price desc';
		break;

		default:
			$nOrder .= 4;
			$sOrder .= 'products_price asc';
		break;
	}

	// Generamos la consulta final
	$sSql = 'select *, 0 as relevance from ' . $sView . ' where ' . $sWhere . $sOrder;
	//$sSql = '(select *, 0 as relevance from ' . $sView . ' where ' . $sWhere . ') UNION (' . $sUnion . ') ' . $sOrder;

	// Obtenemos el paginador y los productos
	$aAux = changePriceCustomer( $sSql, array( 'AJAX' => true, 'COUNT_KEY' => 'products_id', 'ADD_SPECIALS' => false ) );
	$aProductos = $aAux['PRODUCTOS'];
	$aPaginador = $aAux['PAGE_PRODUCTOS'];
	$nProductosTotal = $aAux['TOTAL'];

	// Eliminamos la vista
	tep_db_query( 'DROP VIEW ' . $sView . ';' );

	// Si solo hemos encontrado un resultado redireccionamos al product info
	if( $nProductosTotal == 1 && ! isset( $_GET['page'] ) && ! isAjax() )
	{
		// Redireccionamos
		$aProducto = eachProducts();
		tep_redirect( tep_href_link( FILENAME_PRODUCT_INFO, 'products_id=' . $aProducto['products_id'] ) );
	}

	// Si no hemos encontrado resultados, buscamos por el ID del producto
	if( $nProductosTotal == 0 )
	{
		// Consulta de productos por ID
		$aSearch = tep_db_query( 'select products_id from products where products_id = "' . $sSearch . '" AND products_status = 1;' );

		// Si hemos encontrado un producto por su ID
		if( tep_db_num_rows( $aSearch ) > 0 )
		{
			// Registro
			$aSearch = tep_db_fetch_array( $aSearch );

			// Redireccionamos
			tep_redirect( tep_href_link( FILENAME_PRODUCT_INFO, 'products_id=' . $aSearch['products_id'] ) );
		}
	}

	// Theme
	include( DIR_THEME_ROOT . 'html/templates/advanced_search_result.php' );

	// Pie y columna, si es una peticon ajax no mostramos
	if( ! isAjax() )
	{
		include( DIR_THEME. 'html/column_right.php' );
		include( DIR_THEME. 'html/footer.php' );
		include( DIR_WS_INCLUDES . 'application_bottom.php' );
	}
?>