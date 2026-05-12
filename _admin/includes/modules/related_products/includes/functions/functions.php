<?php
	// Obtenemos un array con todos los productos
	function getAllProducts()
	{
		// Variables
		$aReturn = array();

		// Consulta todos los productos
		$aProductos = tep_db_query( 'SELECT p.products_id, pd.products_name
									 FROM products p
									 INNER JOIN products_description pd ON (p.products_id = pd.products_id)
									 WHERE pd.language_id = 3' );

		// Recorremos los productos
		while( $aProducto = tep_db_fetch_array( $aProductos ) )
			$aReturn[$aProducto['products_id']] = $aProducto;

		return $aReturn;
	}

	// Obtenemos un array con todas las categorias
	function getAllCategories()
	{
		// Variables
		$aReturn = array();

		// Consulta todas las categorias
		$aCategorias = tep_db_query( 'SELECT c.categories_id, cd.categories_name, c.parent_id
									  FROM categories c
									  INNER JOIN categories_description cd ON (c.categories_id = cd.categories_id)
									  WHERE cd.language_id = 3' );

		// Recorremos los productos
		while( $aCategoria = tep_db_fetch_array( $aCategorias ) )
			$aReturn[$aCategoria['categories_id']] = $aCategoria;

		return $aReturn;
	}

	// Obtenemos un array con todas las marcas
	function getAllBrands()
	{
		// Variables
		$aReturn = array();

		// Consulta todos los productos
		$aMarcas = tep_db_query( 'SELECT manufacturers_id, manufacturers_name FROM manufacturers' );

		// Recorremos los productos
		while( $aMarca = tep_db_fetch_array( $aMarcas ) )
			$aReturn[$aMarca['manufacturers_id']] = $aMarca;

		return $aReturn;
	}

	// Crea un SQL para buscar productos
	function getSqlSearchProducts_pi($sSearch, $sPostIds)
	{
		// Variables
		$sSearch = strtolower( $sSearch );
		$sSelect = '';
		$sJoins = '';
		$sWhere = '';
		$aSearchPlural = array();
		$aSearchSingular = array();
		$aWords = null;
		$bBuscarId = false;
		$nCustomerGroupId = 0;

		// Consulta de productos por EAN / Modelo / ID
		$sQuery = 'SELECT p.products_id, pd.products_name FROM products p INNER JOIN products_description pd ON (p.products_id = pd.products_id) WHERE ';

		// Búsqueda por EAN / Modelo
		$sQuery .= '(LCASE( p.products_model ) = "' . $sSearch . '"  OR ';

		// Búsqueda por ID
		$sQuery .= 'LCASE( p.products_id ) = "' . $sSearch . '")';

		// Añadimos comprobación de estado
		$sQuery .= ' AND p.products_status = 1';

		// Lanzamos la consulta
		$aSearch = tep_db_query( $sQuery );

		// Si hemos encontrado
		if( tep_db_num_rows( $aSearch ) > 0 )
		{
			// Registro
			$aSearch = tep_db_fetch_array( $aSearch );

			// Where products_id
			$sWhere = ' p.products_id = "' . (int)$aSearch['products_id'] . '" ';
			$bBuscarId = true;
		}

		// Construimos los campos select
		$sSelect = ' p.products_id, p.products_model, p.products_price, p.products_tax_class_id, p.products_quantity, p.products_image, pd.products_name';

		// Construimos los joins
		$sJoins = TABLE_PRODUCTS . ' p ';
		$sJoins .= 'INNER JOIN ' . TABLE_PRODUCTS_DESCRIPTION . ' pd ON (p.products_id = pd.products_id AND pd.language_id = 3) ';

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
						// Añadimos el número en singular y plural
						$aSearchPlural[] = $aWord;
						$aSearchSingular[] = $aWord;
						continue;
					}

					// Comprobamos si la palabra está en plural (si termina en -s, -es, -ces), para obtener su singular
					if( preg_match( '/s$/', $aWord ) || preg_match( '/es$/', $aWord ) || preg_match( '/ces$/', $aWord ) )
					{
						// Añadimos al array plural
						$aSearchPlural[] = $aWord;

						// Añadimos al array singular
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
						// Añadimos al array singular
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

			// Recorremos las palabras y las añadimos al WHERE para el nombre
			foreach( $aSearchSingular as $nWord => $aWord )
				$sWhere .= '( ( LCASE( products_name ) LIKE "%' . tep_db_input( $aSearchSingular[$nWord] ) . '%" ) OR ( LCASE( products_name ) LIKE "%' . tep_db_input( $aSearchPlural[$nWord] ) . '%" ) ) AND ';

			$sWhere = substr( $sWhere, 0, -4 );
		}

		// Estado del producto
		$sWhere = 'p.products_status = 1 ' . (!empty($sPostIds) ? ' AND p.products_id not in(' . implode(',', $sPostIds) . ') ' : '') . ($sWhere != '' ? 'AND (' . $sWhere . ')' : '');

		// Generamos el orden
		$sOrder = 'ORDER BY pd.products_name DESC';

		// Construimos la consulta SQL
		$sSql = 'SELECT ' . $sSelect . ' FROM ' . $sJoins . ' WHERE ' . $sWhere . ' ' . $sOrder . ' LIMIT 100';

		// Si somos un cliente distinto al cliente final cambiamos el SQL para el precio por grupo de cliente
		if( $nCustomerGroupId != 0 )
		{
			// Si no esta la tabla por grupo de cliente la añadimos
			if( !preg_match( '/from products_groups|inner join products_groups|left join products_groups|right join products_groups]/i', $sSql ) )
				$sSql = preg_replace( '/where/i', 'left join products_groups pg on (pg.customers_group_id = "' . $nCustomerGroupId . '" and pg.products_id = p.products_id) where', $sSql, 1 );

			// Si no esta la tabla de ofertas la añadimos
			if( !preg_match( '/from specials|inner join specials|left join specials|right join specials/i', $sSql ) )
				$sSql = preg_replace( '/where/i', 'left join specials s on (s.products_id = p.products_id and s.customers_group_id = "' . $nCustomerGroupId . '") where', $sSql, 1 );

			// Cambiamos los precios
			$sSql = preg_replace( '/p\.products_price/i', 'IF(s.specials_new_products_price IS NOT NULL and s.status = 1, s.specials_new_products_price, IF(pg.customers_group_price IS NOT NULL, pg.customers_group_price, p.products_price)) as products_price, IF(s.specials_new_products_price IS NOT NULL and s.status = 1, IF(pg.customers_group_price IS NOT NULL, pg.customers_group_price, p.products_price), NULL) as products_price_anterior', $sSql, 1 );
		}
		else
		{
			// Si no esta la tabla de ofertas la añadimos
			if( ! preg_match( '/from specials|inner join specials|left join specials|right join specials/i', $sSql ) )
				$sSql = preg_replace( '/where/i', 'left join specials s on (s.products_id = p.products_id and s.customers_group_id = "' . $nCustomerGroupId . '") where', $sSql, 1 );

			// Cambiamos los precios
			$sSql = preg_replace( '/p\.products_price/i', 'IF(s.specials_new_products_price IS NOT NULL and s.status = 1, s.specials_new_products_price, p.products_price) as products_price, IF(s.specials_new_products_price IS NOT NULL and s.status = 1, p.products_price, NULL) as products_price_anterior', $sSql, 1 );
		}

		// Retornamos
		return $sSql;
	}

	// Crea un SQL para buscar por marcas
	function getSqlSearchBrands_pi($sSearch, $sPostIdsBr)
	{
		// Variables
		$sSearch = strtolower( $sSearch );
		$sSelect = '';
		$sJoins = '';
		$sWhere = '';
		$aSearchSingular = array();
		$aWords = null;
		$bBuscarId = false;

		// Consulta de marca por ID
		$sQuery = 'SELECT manufacturers_id, manufacturers_name FROM manufacturers WHERE ';

		// Búsqueda por ID
		$sQuery .= 'LCASE( manufacturers_id ) = "' . $sSearch . '"';

		// Lanzamos la consulta
		$aSearch = tep_db_query( $sQuery );

		// Si hemos encontrado
		if( tep_db_num_rows( $aSearch ) > 0 )
		{
			// Registro
			$aSearch = tep_db_fetch_array( $aSearch );

			// Where products_id
			$sWhere = ' manufacturers_id = "' . (int)$aSearch['manufacturers_id'] . '" ';
			$bBuscarId = true;
		}

		// Construimos los campos select
		$sSelect = ' manufacturers_id, manufacturers_name';

		// Construimos los joins
		$sJoins = 'manufacturers ';

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
						// Añadimos el número en singular y plural
						$aSearchSingular[] = $aWord;
						continue;
					}

					// Comprobamos si la palabra está en plural (si termina en -s, -es, -ces), para obtener su singular
					if( preg_match( '/s$/', $aWord ) || preg_match( '/es$/', $aWord ) || preg_match( '/ces$/', $aWord ) )
					{
						// Añadimos al array singular
						if( preg_match( '/ces$/', $aWord ) )
							$aSearchSingular[] = preg_replace( '/ces$/', 'z', $aWord );
						else if( preg_match( '/es$/', $aWord ) )
							$aSearchSingular[] = preg_replace( '/es$/', '', $aWord );
						else if( preg_match( '/s$/', $aWord ) )
							$aSearchSingular[] = preg_replace( '/s$/', '', $aWord );
					}
					// Si la palabra está en singular
					else
						$aSearchSingular[] = $aWord;
				}
			}

			// Recorremos las palabras y las añadimos al WHERE para el nombre
			foreach( $aSearchSingular as $nWord => $aWord )
				$sWhere .= '( LCASE( manufacturers_name ) LIKE "%' . tep_db_input( $aSearchSingular[$nWord] ) . '%" ) AND ';

			$sWhere = substr( $sWhere, 0, -4 );
		}

		// Estado del producto
		$sWhere = 'manufacturers_status = 1 ' . (!empty($sPostIdsBr) ? ' AND manufacturers_id not in(' . implode(',', $sPostIdsBr) . ') ' : '') . ($sWhere != '' ? 'AND (' . $sWhere . ')' : '');

		// Generamos el orden
		$sOrder = 'ORDER BY manufacturers_name DESC';

		// Construimos la consulta SQL
		$sSql = 'SELECT ' . $sSelect . ' FROM ' . $sJoins . ' WHERE ' . $sWhere . ' ' . $sOrder . ' LIMIT 20';

		// Retornamos
		return $sSql;
	}

	// Crea un SQL para buscar por categorias
	function getSqlSearchCategories_pi($sSearch, $sPostIdsCt)
	{
		// Variables
		$sSearch = strtolower( $sSearch );
		$sSelect = '';
		$sJoins = '';
		$sWhere = '';
		$aSearchSingular = array();
		$aWords = null;
		$bBuscarId = false;

		// Consulta de categoria por ID
		$sQuery = 'SELECT cd.categories_id, cd.categories_name FROM categories_description cd WHERE ';

		// Búsqueda por ID
		$sQuery .= 'LCASE( cd.categories_id ) = "' . $sSearch . '"';

		// Añadimos comprobación de idioma
		$sQuery .= ' AND cd.language_id = 3';

		// Lanzamos la consulta
		$aSearch = tep_db_query( $sQuery );

		// Si hemos encontrado
		if( tep_db_num_rows( $aSearch ) > 0 )
		{
			// Registro
			$aSearch = tep_db_fetch_array( $aSearch );

			// Where products_id
			$sWhere = ' cd.categories_id = "' . (int)$aSearch['categories_id'] . '" ';
			$bBuscarId = true;
		}

		// Construimos los campos select
		$sSelect = ' c.parent_id, cd.categories_id, cd.categories_name';

		// Construimos los joins
		$sJoins = 'categories c ';
		$sJoins .= 'INNER JOIN categories_description cd ON(cd.categories_id = c.categories_id)';

		// Si NO queremos buscar solo por ID
		if( ! $bBuscarId )
		{
			// Separamos las palabras
			$aWords = preg_split( "/[\s]|[,]|[.]|[-]/", $sSearch, -1, PREG_SPLIT_NO_EMPTY );

			// Formateamos las palabras de la búsqueda
			foreach( $aWords as $aWord )
			{
				// Eliminamos las palabras demasiado cortas (pronombres, etc)
				if( strlen( $aWord ) > 0 )
				{
					// Si es una preposición, artículo o nexo continuamos
					if( in_array( $aWord, array( 'a', 'ante', 'bajo', 'cabe', 'con', 'contra', 'de', 'desde', 'en', 'entre', 'hacia', 'hasta', 'para', 'por', 'según', 'segun', 'sin', 'so', 'sobre', 'tras', 'del', 'al', 'el', 'la', 'las', 'los', 'un', 'unos', 'un', 'una', 'unas', 'y', 'u', 'o', 'e' ) ) )
						continue;

					// Si es un número
					if( is_numeric( $aWord ) )
					{
						// Añadimos el número en singular
						$aSearchSingular[] = $aWord;
						continue;
					}

					// Comprobamos si la palabra está en plural (si termina en -s, -es, -ces), para obtener su singular
					if( preg_match( '/s$/', $aWord ) || preg_match( '/es$/', $aWord ) || preg_match( '/ces$/', $aWord ) )
					{
						// Añadimos al array singular
						if( preg_match( '/ces$/', $aWord ) )
							$aSearchSingular[] = preg_replace( '/ces$/', 'z', $aWord );
						else if( preg_match( '/es$/', $aWord ) )
							$aSearchSingular[] = preg_replace( '/es$/', '', $aWord );
						else if( preg_match( '/s$/', $aWord ) )
							$aSearchSingular[] = preg_replace( '/s$/', '', $aWord );
					}
					// Si la palabra está en singular
					else
						$aSearchSingular[] = $aWord;
				}
			}

			// Recorremos las palabras y las añadimos al WHERE para el nombre
			foreach( $aSearchSingular as $nWord => $aWord )
				$sWhere .= '( LCASE( categories_name ) LIKE "%' . tep_db_input( $aSearchSingular[$nWord] ) . '%" ) AND ';

			$sWhere = substr( $sWhere, 0, -4 );
		}

		// Estado del producto
		$sWhere = 'cd.language_id = 3 ' . (!empty($sPostIdsCt) ? ' AND c.categories_id not in(' . implode(',', $sPostIdsCt) . ') ' : '') . ($sWhere != '' ? 'AND (' . $sWhere . ')' : '');

		// Generamos el orden
		$sOrder = 'ORDER BY cd.categories_name DESC';

		// Construimos la consulta SQL
		$sSql = 'SELECT ' . $sSelect . ' FROM ' . $sJoins . ' WHERE ' . $sWhere . ' ' . $sOrder . ' LIMIT 20';

		// Retornamos
		return $sSql;
	}

	// Función recursiva para obtener las categorias padre
	function getCategoriesByParent_pi($nCategory, $sCategory, $aAllCategories)
	{
		// Obtenemos la categoria padre
		if( !isset($aAllCategories[$nCategory]) ) return array( 'text' => $sCategory, 'parent_id' => $nCategory );
		$sIdCategoriaPadre = $aAllCategories[$nCategory]['categories_id'];

		// Nombre de categoria
		$sCategory = $aAllCategories[$nCategory]['categories_name'] . ' => ' . $sCategory;

		// Si tenemos padre
		if( $aAllCategories[$nCategory]['parent_id'] != 0 && $aAllCategories[$nCategory]['parent_id'] != '' )
		{
			$aAux2 = getCategoriesByParent_pi( $aAllCategories[$nCategory]['parent_id'], $sCategory, $aAllCategories );
			$sCategory = $aAux2['text'];
			$sIdCategoriaPadre = $aAux2['parent_id'];
		}

		// Retornamos la categoria
		return array( 'text' => $sCategory, 'parent_id' => $sIdCategoriaPadre );
	}
?>