<?php
	//SI está descatalogado, obtenemos productos de la misma categoria
	if ($aProductoInfo['products_status'] == 2)
	{
		$productsAlternativos = array();
		$category_query_descatalogados = array();

		$aDatos = tep_db_query('SELECT pd.products_name, pda.products_id_alt, pda.id 
								FROM products_descat_alternativos pda 
								INNER JOIN products_description pd ON (pd.products_id = pda.products_id_alt) 
								INNER JOIN products p ON (p.products_id = pd.products_id) 
								WHERE p.products_status = 1 
								AND pda.products_id = '. (int)$aProductoInfo['products_id'] .' 
								AND pd.language_id = ' . (int)$languages_id );

		while( $aDato = tep_db_fetch_array( $aDatos ) )
			$productsAlternativos[] = $aDato['products_id_alt'];

		if (empty($productsAlternativos))
		{
			$category_query_descatalogados = tep_db_query("select p2c.categories_id from " . TABLE_PRODUCTS . " p
														   inner join " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c on (p.products_id = p2c.products_id)
														   where p.products_id = '" . (int)$aProductoInfo['products_id'] . "' 
														   limit 1");
			$category_descatalogados = tep_db_fetch_array($category_query_descatalogados);
		}
		
		$sqlDescatalogados = 'select p.products_image, p2c.categories_id, p.products_date_available, p.products_id, p.products_quantity, p.products_min_order_qty, p.products_image, p.products_model, p.products_tax_class_id, pd.products_name, IF(s.specials_new_products_price is not null, p.products_price, NULL) as products_price_anterior, IF(s.specials_new_products_price is not null, s.specials_new_products_price, p.products_price) as products_price
								from products p
								inner join products_description pd on (p.products_id = pd.products_id)
								left join specials s on (s.products_id = p.products_id and s.customers_group_id = "' . $nCustomerGroupId . '")
								LEFT JOIN products_to_categories p2c on (p2c.products_id = p.products_id)
								WHERE p.products_status = 1 and pd.language_id = ' . (int)$languages_id .
								($category_descatalogados['categories_id'] > 0 ? ' and p2c.categories_id = '.$category_descatalogados['categories_id'].' ' : '') .
								(!empty($productsAlternativos)? ' and p.products_id IN ('.implode(',', $productsAlternativos).') ' : '') .
								' GROUP BY p.products_id ORDER BY p.products_id DESC';

		$aDatos = tep_db_query( $sqlDescatalogados );

		if( tep_db_num_rows( $aDatos ) <= 0 )
		{
			$sqlDescatalogados = 'select p.products_image, p2c.categories_id, p.products_date_available, p.products_id, p.products_quantity, p.products_min_order_qty, p.products_image, p.products_model, p.products_tax_class_id, pd.products_name, IF(s.specials_new_products_price is not null, p.products_price, NULL) as products_price_anterior, IF(s.specials_new_products_price is not null, s.specials_new_products_price, p.products_price) as products_price
								from products p
								inner join products_description pd on (p.products_id = pd.products_id)
								left join specials s on (s.products_id = p.products_id and s.customers_group_id = "' . $nCustomerGroupId . '")
								LEFT JOIN products_to_categories p2c on (p2c.products_id = p.products_id)
								WHERE p.products_status = 1 and pd.language_id = ' . (int)$languages_id . '
								and p.manufacturers_id = '.$aProductoInfo['manufacturers_id'] . '
								GROUP BY p.products_id ORDER BY rand()';

			$aDatos = tep_db_query( $sqlDescatalogados );
		}

		$aProductsDescatalogado = array();
		while( $aDato = tep_db_fetch_array( $aDatos ) )
			$aProductsDescatalogado[]= $aDato;

		include( DIR_THEME. 'html/components/' . basename(__FILE__) );
	}
