<?php
/* FUNCTIONS *********************************************************************** */

/*
	gets the id list of the filtered products
*/
function specials_enhanced_get_all_id()
{
	global $category_id, $subcats_flag, $manufacturer_id, $specials_flag, $not_specials_flag, $product_name, $languages_id;

	$tables = [];

	if ($specials_flag) {
        $tables[] = TABLE_PRODUCTS . ' p INNER JOIN ' . TABLE_SPECIALS . ' s ON p.products_id = s.products_id';
    } else {
        $tables[] = TABLE_PRODUCTS . ' p LEFT JOIN ' . TABLE_SPECIALS . ' s ON p.products_id = s.products_id';
    }

	$clauses = [];

	if ($product_name != null)
	{
	   $tables[] = ' INNER JOIN ' . TABLE_PRODUCTS_DESCRIPTION . ' pd ON p.products_id = pd.products_id';
	   $clauses[] = "pd.products_name LIKE '%" .  $product_name . "%'";
	   $clauses[] = 'pd.language_id = '. (int)$languages_id;
	}

	if($category_id !== null && $category_id >= 0)
	{
		$tables[] = 'INNER JOIN ' . TABLE_PRODUCTS_TO_CATEGORIES . ' p2c ON p.products_id = p2c.products_id';

		if($subcats_flag)
		{
			$categories_array = tep_get_category_tree($category_id,'','0','', true);
			$cats = [];
			foreach($categories_array as $cat)
				$cats[] = $cat['id'];
			$clauses[] = "p2c.categories_id IN (" . implode(',',$cats) . ")";
		}
		else {
            $clauses[] = "p2c.categories_id = '$category_id'";
        }
	}

	if($manufacturer_id !== null)
	{
		$clauses[] = "p.manufacturers_id = '$manufacturer_id'";
	}

	if($not_specials_flag != null)
	{
		$clauses[] = "p.products_id NOT IN (Select products_id from specials where status = 1)";
	}

	$tables_text = implode(' ', $tables);

	$clauses_text = '1=1';
	if (count($clauses) > 0) {
        $clauses_text = implode(' AND ', $clauses);
    }

	$ids_query = tep_db_query("SELECT p.products_id AS pid, s.specials_id AS sid FROM $tables_text WHERE $clauses_text");

	$ids = [];
	while($id = tep_db_fetch_array($ids_query))
		$ids[] = $id;

	return $ids;
}

/*
	gets all the filtered products
*/
function specials_enhanced_get_all_products(&$products_split, &$products_query_numrows, $customer_group = 0)
{
	global $category_id, $subcats_flag, $manufacturer_id, $specials_flag, $not_specials_flag, $languages_id, $page, $sort_field, $product_name;

	$tables = [];
	$clauses = [];

	$fields = 'p.products_id, p.products_price, p.products_tax_class_id, p.products_model, pd.products_name, s.specials_id, s.specials_new_products_price, s.customers_group_id, s.expires_date, s.status, s.date_start';

	if($specials_flag)
	{
		$tables[] = TABLE_PRODUCTS . ' p INNER JOIN ' . TABLE_SPECIALS . ' s ON p.products_id = s.products_id and s.customers_group_id = ' . $customer_group . ' INNER JOIN ' . TABLE_PRODUCTS_DESCRIPTION . ' pd ON p.products_id = pd.products_id';
	}
	else
	{
		$tables[] = TABLE_PRODUCTS . ' p LEFT JOIN ' . TABLE_SPECIALS . ' s ON p.products_id = s.products_id and s.customers_group_id = ' . $customer_group . ' INNER JOIN ' . TABLE_PRODUCTS_DESCRIPTION . ' pd ON p.products_id = pd.products_id';
	}

	$clauses[] = 'pd.language_id = '. (int)$languages_id;

	if($category_id !== null && $category_id >= 0)
	{
		$tables[] = 'INNER JOIN ' . TABLE_PRODUCTS_TO_CATEGORIES . ' p2c ON p.products_id = p2c.products_id';

		if($subcats_flag)
		{
			$categories_array = tep_get_category_tree($category_id,'','0','', true);
			$cats = [];
			foreach($categories_array as $cat)
				$cats[] = $cat['id'];
			$clauses[] = "p2c.categories_id IN (" . implode(',',$cats) . ")";
		}
		else {
            $clauses[] = "p2c.categories_id = '$category_id'";
        }
	}

	if($manufacturer_id !== null && $manufacturer_id > 0)
	{
		$clauses[] = "p.manufacturers_id = '$manufacturer_id'";
	}

	if ($product_name != null)
	{
	   $clauses[] = "pd.products_name LIKE '%" .  $product_name . "%'";
	}

	if($not_specials_flag != null)
	{
		$clauses[] = "p.products_id NOT IN (Select products_id from specials where status = 1)";
	}

	$tables_text = implode(' ', $tables);

	$clauses_text = '1=1';
	if (count($clauses) > 0) {
        $clauses_text = implode(' AND ', $clauses);
    }

	$products_query_text = "select $fields from $tables_text WHERE $clauses_text ORDER BY $sort_field";

	if( $customer_group != 0 )
	{
		// Si no esta la tabla por grupo de cliente la añadimos
		if (!preg_match( '/from products_groups|inner join products_groups|left join products_groups|right join products_groups]/i', $products_query_text )) {
            $products_query_text = preg_replace( '/where/i', 'left join products_groups pg on (pg.customers_group_id = "' . $customer_group . '" and pg.products_id = p.products_id) where', $products_query_text, 1 );
        }

		// Cambiamos los precios
		$products_query_text = preg_replace( '/p\.products_price/i', 'IF(pg.customers_group_price IS NOT NULL, pg.customers_group_price, p.products_price) as products_price', (string) $products_query_text, 1 );
	}

	$products_split = new splitPageResults($page, MAX_DISPLAY_SEARCH_RESULTS, $products_query_text, $products_query_numrows);
	$products_query = tep_db_query($products_query_text);

	$products = [];
	while ($product = tep_db_fetch_array($products_query))
		$products[] = $product;

	return $products;
}

/*
	rewrited tep_set_specials_status, the osCommerce one has a strange behaviour when setting the status to 1
*/
function specials_enhanced_set_status($specials_id, $status, $customer_group = 0)
{
    if ($status == '1') 
	{
		return tep_db_query("update " . TABLE_SPECIALS . " set status = '1', date_status_change = NULL, date_start = NULL where specials_id = '" . (int)$specials_id . "' and customers_group_id = '" . $customer_group . "'");
    } 
	elseif ($status == '0')
	{
      return tep_db_query("update " . TABLE_SPECIALS . " set status = '0', date_status_change = now(), date_start = NULL where specials_id = '" . (int)$specials_id . "' and customers_group_id = '" . $customer_group . "'");
    } 
	else 
	{
		return -1;
    }
}

/*
	Updates a product special offer
*/
function specials_enhanced_update_product($product_id, $discount = null, $discount_percent = false, $customer_group = 0, $date = null, $date_start = null)
{
	$product_query = tep_db_query("SELECT p.products_id AS id, p.products_price AS price, p.products_tax_class_id AS tax FROM " . TABLE_PRODUCTS . " p WHERE p.products_id = $product_id");
	$product = tep_db_fetch_array($product_query);

	$customer_group_price_query = tep_db_query("select customers_group_price from " . TABLE_PRODUCTS_GROUPS . " where products_id = $product_id and customers_group_id = $customer_group");
	 if ($customer_group_price = tep_db_fetch_array($customer_group_price_query)) {
		$product['price']= $customer_group_price['customers_group_price'];
	 }

	$fields = [];

	if($discount !== null)
	{
		if ($discount_percent) {
            $discounted_price = ($product['price'] - (($discount / 100) * $product['price']));
        } elseif(DISPLAY_PRICE_WITH_TAX == 'true') 
		{
			$discounted_price = floatval($discount/(1 + tep_get_tax_rate_value($product['tax'])/100));
		} else
		{
			$discounted_price = floatval($discount);
		}

		$fields['specials_new_products_price'] = $discounted_price;
	}

	if($date !== null)
	{
		$fields['expires_date'] = $date;
	}

	if($date_start !== null)
	{
		$fields['date_start'] = $date_start;
	}

	$fields['customers_group_id'] = $customer_group;

	if(tep_db_num_rows(tep_db_query("SELECT specials_id FROM " . TABLE_SPECIALS . " WHERE customers_group_id = $customer_group and products_id = $product_id")) == 1)
	{
		$set_fields = [];
		foreach($fields as $k => $v)
			$set_fields[] = "$k = '$v'";

		$set_fields = implode(', ', $set_fields);

		tep_db_query("UPDATE " . TABLE_SPECIALS . " SET $set_fields WHERE customers_group_id = $customer_group and products_id = '$product_id'");
	}
	else
	{
		$key_fields = [];
		$value_fields = [];
		foreach($fields as $k => $v)
		{
			$key_fields[] = $k;
			$value_fields[] = "'$v'";
		}

		$key_fields = implode(', ', $key_fields);
		$value_fields = implode(', ', $value_fields);

		tep_db_query("INSERT INTO " . TABLE_SPECIALS . " (products_id, specials_date_added, status, $key_fields) VALUES ('" . (int)$product_id . "', now(), '0', $value_fields)");
	}

	return true;
}
/* *********************************************************************** */
?>