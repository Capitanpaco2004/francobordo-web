<?php
	// Crea un SQL para buscar productos
	function getSqlSearchProducts_bd($sSearch)
	{
		// Variables
		$sSearch = strtolower( (string) $sSearch );
		$sSelect = '';
		$sJoins = '';
		$sWhere = '';
		$aSearchPlural = [];
		$aSearchSingular = [];
		$aWords = null;
		$bBuscarId = false;
		$nCustomerGroupId = 0;
		global $languages_id;

		// Consulta de productos por EAN / Modelo / ID
		$sQuery = 'SELECT p.products_id, pd.products_name, p.products_price, p.products_tax_class_id FROM products p INNER JOIN products_description pd ON (p.products_id = pd.products_id) WHERE ';

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
		$sSelect = ' p.products_id, p.products_ubicacion, p.products_free_shipping, p.products_model, p.products_price, p.products_tax_class_id, p.products_quantity, p.products_image, p.products_date_available, pd.products_name, pd.products_description';

		// Construimos los joins
		$sJoins = TABLE_PRODUCTS . ' p ';
		$sJoins .= 'INNER JOIN ' . TABLE_PRODUCTS_DESCRIPTION . ' pd ON (p.products_id = pd.products_id AND pd.language_id = ' . (int) $languages_id . ') ';

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
					if (in_array( $aWord, [ 'a', 'ante', 'bajo', 'cabe', 'con', 'contra', 'de', 'desde', 'en', 'entre', 'hacia', 'hasta', 'para', 'por', 'según', 'segun', 'sin', 'so', 'sobre', 'tras', 'del', 'al', 'el', 'la', 'las', 'los', 'un', 'unos', 'un', 'una', 'unas', 'y', 'u', 'o', 'e' ] )) {
                        continue;
                    }

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
						if (preg_match( '/ces$/', $aWord )) {
                            $aSearchSingular[] = preg_replace( '/ces$/', 'z', $aWord );
                        } elseif (preg_match( '/es$/', $aWord )) {
                            $aSearchSingular[] = preg_replace( '/es$/', '', $aWord );
                        } elseif (preg_match( '/s$/', $aWord )) {
                            $aSearchSingular[] = preg_replace( '/s$/', '', $aWord );
                        }
					}
					// Si la palabra está en singular
					else
					{
						// Añadimos al array singular
						$aSearchSingular[] = $aWord;

						// Obtenemos su plural
						if (preg_match( '/[a]$|[e]$|[o]$/', $aWord )) {
                            $aSearchPlural[] = $aWord . 's';
                        } elseif (preg_match( '/[z]$/', $aWord )) {
                            $aSearchPlural[] = preg_replace( '/z$/', 'ces', $aWord );
                        } else {
                            $aSearchPlural[] = $aWord . 'es';
                        }
					}
				}
			}

			// Recorremos las palabras y las añadimos al WHERE para el nombre
			foreach( array_keys($aSearchSingular) as $nWord )
				$sWhere .= '( ( LCASE( products_name ) LIKE "%' . tep_db_input( $aSearchSingular[$nWord] ) . '%" ) OR ( LCASE( products_name ) LIKE "%' . tep_db_input( $aSearchPlural[$nWord] ) . '%" ) ) AND ';

			$sWhere = substr( $sWhere, 0, -4 );
		}

		// Estado del producto
		$sWhere = 'p.products_status = 1 ' . ($sWhere !== '' ? 'AND (' . $sWhere . ')' : '');

		// Generamos el orden
		$sOrder = 'ORDER BY pd.products_name DESC';

		// Construimos la consulta SQL
		$sSql = 'SELECT ' . $sSelect . ' FROM ' . $sJoins . ' WHERE ' . $sWhere . ' ' . $sOrder . ' LIMIT 100';

		// Si somos un cliente distinto al cliente final cambiamos el SQL para el precio por grupo de cliente
		if( $nCustomerGroupId !== 0 )
		{
			// Si no esta la tabla por grupo de cliente la añadimos
			if (!preg_match( '/from products_groups|inner join products_groups|left join products_groups|right join products_groups]/i', $sSql )) {
                $sSql = preg_replace( '/where/i', 'left join products_groups pg on (pg.customers_group_id = "' . $nCustomerGroupId . '" and pg.products_id = p.products_id) where', $sSql, 1 );
            }

			// Si no esta la tabla de ofertas la añadimos
			if (!preg_match( '/from specials|inner join specials|left join specials|right join specials/i', (string) $sSql )) {
                $sSql = preg_replace( '/where/i', 'left join specials s on (s.products_id = p.products_id and s.customers_group_id = "' . $nCustomerGroupId . '") where', (string) $sSql, 1 );
            }

			// Cambiamos los precios
			$sSql = preg_replace( '/p\.products_price/i', 'IF(s.specials_new_products_price IS NOT NULL and s.status = 1, s.specials_new_products_price, IF(pg.customers_group_price IS NOT NULL, pg.customers_group_price, p.products_price)) as products_price, IF(s.specials_new_products_price IS NOT NULL and s.status = 1, IF(pg.customers_group_price IS NOT NULL, pg.customers_group_price, p.products_price), NULL) as products_price_anterior', (string) $sSql, 1 );
		}
		else
		{
			// Si no esta la tabla de ofertas la añadimos
			if (! preg_match( '/from specials|inner join specials|left join specials|right join specials/i', $sSql )) {
                $sSql = preg_replace( '/where/i', 'left join specials s on (s.products_id = p.products_id and s.customers_group_id = "' . $nCustomerGroupId . '") where', $sSql, 1 );
            }

			// Cambiamos los precios
			$sSql = preg_replace( '/p\.products_price/i', 'IF(s.specials_new_products_price IS NOT NULL and s.status = 1, s.specials_new_products_price, p.products_price) as products_price, IF(s.specials_new_products_price IS NOT NULL and s.status = 1, p.products_price, NULL) as products_price_anterior', (string) $sSql, 1 );
		}
		
		// Retornamos
		return $sSql;
	}
?>