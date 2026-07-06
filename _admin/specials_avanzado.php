<?php
require('includes/application_top.php');
require(DIR_WS_CLASSES . 'currencies.php');

$currencies = new currencies();

$action = (isset($_GET['action']) ? $_GET['action'] : 'list');

$categories_head = array(
	array(
		'id' => '', 
		'text' => @SPECIALS_ENHANCED_CATEGORIES
	)
);

$categories_list = array_merge($categories_head, tep_get_category_tree());

$manufacturers_list = array(
	array(
		'id' => '', 
		'text' => @SPECIALS_ENHANCED_MANUFACTURERS
	)
);

$manufacturers_query = tep_db_query("select manufacturers_id, manufacturers_name from " . TABLE_MANUFACTURERS . " order by manufacturers_name");
while ($manufacturers = tep_db_fetch_array($manufacturers_query))
{
	$manufacturers_list[] = array(
		'id' => $manufacturers['manufacturers_id'],
		'text' => $manufacturers['manufacturers_name']
	);
}

// Nombres de los grupos de cliente (para mostrar el grupo de cada oferta)
$group_names = array();
$groups_query = tep_db_query("select customers_group_id, customers_group_name from " . TABLE_CUSTOMERS_GROUPS . " order by customers_group_id");
while ($g = tep_db_fetch_array($groups_query))
	$group_names[$g['customers_group_id']] = $g['customers_group_name'];

$sort_fields = array(
	'product_id' => array(
		'text' => '',
		'field' => 'p.products_id'
 	),
	'product_model' => array(
		'text' => @SPECIALS_ENHANCED_TH_MODEL,
		'field' => 'p.products_model'
 	),
	'product_name' => array(
		'text' => @SPECIALS_ENHANCED_TH_PRODUCTS,
		'field' => 'pd.products_name'
 	)
);

$sort_list = array();
foreach($sort_fields as $k => $v) {
	$sort_list[] = array(
		'id' => $k,
		'text' => $v['text']
	);
}

$product_name =  (isset($_GET['product_name']) && !empty($_GET['product_name']) ? trim($_GET['product_name']):null);

$sort_type = (isset($_GET['sort_type']) && in_array($_GET['sort_type'], array('asc', 'desc')) ? $_GET['sort_type'] : 'asc');
$sort = (isset($_GET['sort']) && isset($sort_fields[$_GET['sort']]['field']) ? $_GET['sort'] : 'product_id');
$sort_field = $sort_fields[$sort]['field'] . ($sort != 'product_id' ? ' ' . $sort_type : '');

$category_id = (isset($_GET['cPath']) && $_GET['cPath'] != '' ? intval($_GET['cPath']) : null);
$subcats_flag = (isset($_GET['subcats_flag']) && $_GET['subcats_flag'] == '1' ? true:false);
$specials_flag = (isset($_GET['specials_flag']) && $_GET['specials_flag'] == '1' ? true:false);
$manufacturer_id = (isset($_GET['manufacturer_id']) && $_GET['manufacturer_id'] != '' ? intval($_GET['manufacturer_id']) : null);

$current_category_id = (isset($_GET['cPath']) && $_GET['cPath'] != '' ? intval($_GET['cPath']) : '');

$discount_percent = (isset($_GET['percent_flag']) && $_GET['percent_flag'] == '1' ? true:false);
$discount = (isset($_GET['discount']) && !empty($_GET['discount']) ? trim($_GET['discount']):null);
if($discount !== null)
{
	if(!$discount_percent && preg_match("/^(.*)(%|p)$/", $discount)) {
		$discount_percent = true;
	}

	// Parseo robusto de importe:
	//  - con coma ("1.478,51" o "49,99"): formato espanol -> quitar puntos de miles y coma -> punto
	//  - sin coma ("49.99" o "1478"): el punto es decimal -> floatval directo
	// (antes, "1.478,51" -> floatval("1.478.51") = 1.478 y corrompia precios >= 1.000)
	if (strpos($discount, $currencies->currencies[DEFAULT_CURRENCY]["decimal_point"]) !== false) {
		$discount = str_replace($currencies->currencies[DEFAULT_CURRENCY]["thousands_point"], "", $discount);
		$discount = str_replace($currencies->currencies[DEFAULT_CURRENCY]["decimal_point"], ".", $discount);
	}
	$discount = floatval($discount);
}

$date = (isset($_GET['date']) && !empty($_GET['date']) ? trim($_GET['date']):null);
if(preg_match('/^([0-9]{1,2})\/([0-9]{1,2})\/([0-9]{4,4})$/',(string)($date ?? '')))
{
	$date_array = explode('/',$date);
	$date = date("Y-m-d H:i:s", mktime(23, 59, 59, $date_array[1], $date_array[0], $date_array[2]));
}

$start_date = (isset($_GET['start_date']) && !empty($_GET['start_date']) ? trim($_GET['start_date']):null);
if(preg_match('/^([0-9]{1,2})\/([0-9]{1,2})\/([0-9]{4,4})$/',(string)($start_date ?? '')))
{
	$start_date_array = explode('/',$start_date);
	$start_date = date("Y-m-d H:i:s", mktime(0, 0, 0, $start_date_array[1], $start_date_array[0], $start_date_array[2]));
}

$page = (isset($_GET['page']) && intval($_GET['page']) > 0 ? intval($_GET['page']):1);

// Repetir oferta cada 14 dias (null = no tocar; solo se envia en edicion por fila)
$expires_repeat = (isset($_GET['expires_repeat']) ? (($_GET['expires_repeat'] == '1') ? 1 : 0) : null);

// Nº de ofertas temporales ya caducadas (para el boton de limpieza)
$expired_temp_count = 0;
$etc_q = tep_db_query("SELECT COUNT(*) AS n FROM " . TABLE_SPECIALS . " WHERE is_temp = '1' AND expires_date > 0 AND expires_date < NOW()");
if($etc_r = tep_db_fetch_array($etc_q)) $expired_temp_count = (int)$etc_r['n'];

/* FUNCTIONS *********************************************************************** */

/*
	gets the id list of the filtered products
*/
function specials_enhanced_get_all_id()
{
	global $category_id, $subcats_flag, $manufacturer_id, $specials_flag, $product_name, $languages_id;

	$tables = array();
	
	if($specials_flag)
		$tables[] = TABLE_PRODUCTS . ' p INNER JOIN ' . TABLE_SPECIALS . ' s ON p.products_id = s.products_id';
	else
		$tables[] = TABLE_PRODUCTS . ' p LEFT JOIN ' . TABLE_SPECIALS . ' s ON p.products_id = s.products_id';
	
	$clauses = array();

	if ($product_name != null)
	{
	   $tables[] = ' INNER JOIN ' . TABLE_PRODUCTS_DESCRIPTION . ' pd ON p.products_id = pd.products_id';
	   $clauses[] = "pd.products_name LIKE '%" .  tep_db_input($product_name) . "%'";
	   $clauses[] = 'pd.language_id = '. (int)$languages_id;
	}
	
	if($category_id !== null && $category_id >= 0)
	{
		$tables[] = 'INNER JOIN ' . TABLE_PRODUCTS_TO_CATEGORIES . ' p2c ON p.products_id = p2c.products_id';

		if($subcats_flag)
		{
			$categories_array = tep_get_category_tree($category_id,'','0','', true);
			$cats = array();
			foreach($categories_array as $cat)
				$cats[] = $cat['id'];
			$clauses[] = "p2c.categories_id IN (" . implode(',',$cats) . ")";
		}
		else
			$clauses[] = "p2c.categories_id = '$category_id'";
	}
	
	if($manufacturer_id !== null)
	{
		$clauses[] = "p.manufacturers_id = '$manufacturer_id'";
	}
	
	$tables_text = implode(' ', $tables);
	
	$clauses_text = '1=1';
	if(count($clauses) > 0)
		$clauses_text = implode(' AND ', $clauses);

	$ids_query = tep_db_query("SELECT p.products_id AS pid, s.specials_id AS sid FROM $tables_text WHERE $clauses_text");
	
	$ids = array();
	while($id = tep_db_fetch_array($ids_query))
		$ids[] = $id;

	return $ids;
}

/*
	gets all the filtered products
*/
function specials_enhanced_get_all_products(&$products_split, &$products_query_numrows)
{
	global $category_id, $subcats_flag, $manufacturer_id, $specials_flag, $languages_id, $page, $sort_field, $product_name;

	$tables = array();
	$clauses = array();

	$fields = 'p.products_id, p.products_price, p.products_tax_class_id, p.products_model, pd.products_name, s.specials_id, s.specials_new_products_price, s.expires_date, s.status, s.start_date, s.customers_group_id, s.expires_repeat, s.is_temp, pg.customers_group_price';

	if($specials_flag)
	{
		$tables[] = TABLE_PRODUCTS . ' p INNER JOIN ' . TABLE_SPECIALS . ' s ON p.products_id = s.products_id INNER JOIN ' . TABLE_PRODUCTS_DESCRIPTION . ' pd ON p.products_id = pd.products_id LEFT JOIN ' . TABLE_PRODUCTS_GROUPS . ' pg ON pg.products_id = p.products_id AND pg.customers_group_id = s.customers_group_id';
	}
	else
	{
		$tables[] = TABLE_PRODUCTS . ' p LEFT JOIN ' . TABLE_SPECIALS . ' s ON p.products_id = s.products_id INNER JOIN ' . TABLE_PRODUCTS_DESCRIPTION . ' pd ON p.products_id = pd.products_id LEFT JOIN ' . TABLE_PRODUCTS_GROUPS . ' pg ON pg.products_id = p.products_id AND pg.customers_group_id = s.customers_group_id';
	}
	
	$clauses[] = 'pd.language_id = '. (int)$languages_id;
	
	if($category_id !== null && $category_id >= 0)
	{
		$tables[] = 'INNER JOIN ' . TABLE_PRODUCTS_TO_CATEGORIES . ' p2c ON p.products_id = p2c.products_id';

		if($subcats_flag)
		{
			$categories_array = tep_get_category_tree($category_id,'','0','', true);
			$cats = array();
			foreach($categories_array as $cat)
				$cats[] = $cat['id'];
			$clauses[] = "p2c.categories_id IN (" . implode(',',$cats) . ")";
		}
		else
			$clauses[] = "p2c.categories_id = '$category_id'";
	}

	if($manufacturer_id !== null && $manufacturer_id > 0)
	{
		$clauses[] = "p.manufacturers_id = '$manufacturer_id'";
	}
	
	if ($product_name != null)
	{
	   $clauses[] = "pd.products_name LIKE '%" .  tep_db_input($product_name) . "%'";
	}
	
	$tables_text = implode(' ', $tables);
	
	$clauses_text = '1=1';
	if(count($clauses) > 0)
		$clauses_text = implode(' AND ', $clauses);

	$products_query_text = "select $fields from $tables_text WHERE $clauses_text ORDER BY $sort_field";
	
	$products_split = new splitPageResults($page, MAX_DISPLAY_SEARCH_RESULTS, $products_query_text, $products_query_numrows);
	$products_query = tep_db_query($products_query_text);
	
	$products = array();
	while ($product = tep_db_fetch_array($products_query))
		$products[] = $product;
	
	return $products;
}

/*
	rewrited tep_set_specials_status, the osCommerce one has a strange behaviour when setting the status to 1
*/
function specials_enhanced_set_status($specials_id, $status)
{
    if ($status == '1') 
	{
		return tep_db_query("update " . TABLE_SPECIALS . " set status = '1', date_status_change = NULL where specials_id = '" . (int)$specials_id . "'");
    } 
	elseif ($status == '0')
	{
      return tep_db_query("update " . TABLE_SPECIALS . " set status = '0', date_status_change = now() where specials_id = '" . (int)$specials_id . "'");
    } 
	else 
	{
		return -1;
    }
}

/*
	Updates a product special offer
*/
function specials_enhanced_update_product($product_id, $discount = null, $discount_percent = false, $date = null, $start_date = null, $expires_repeat = null, $activate = true)
{
	$product_query = tep_db_query("SELECT p.products_id AS id, p.products_price AS price, p.products_tax_class_id AS tax FROM " . TABLE_PRODUCTS . " p WHERE p.products_id = $product_id");
	$product = tep_db_fetch_array($product_query);

	$fields = array();
	
	if($discount !== null)
	{
		if($discount_percent) 
			$discounted_price = ($product['price'] - (($discount / 100) * $product['price']));
		elseif(DISPLAY_PRICE_WITH_TAX == 'true') 
		{
			$discounted_price = floatval($discount/(1 + tep_get_tax_rate_value($product['tax'])/100));
		}
		else
		{
			$discounted_price = floatval($discount);
		}

		$fields['specials_new_products_price'] = $discounted_price;
	}

	if($date !== null)
	{
		$fields['expires_date'] = $date;
	}

	if($start_date !== null)
	{
		$fields['start_date'] = $start_date;
	}

	if($expires_repeat !== null)
	{
		$fields['expires_repeat'] = (int)$expires_repeat;
	}

	$aOfertas = array();
	// Obtenemos ofertas del producto — SOLO Retail (cgid=0). Las ofertas de otros
	// grupos (Profesionales, Amazon...) se gestionan por fila con su sid; sin este
	// filtro, "Aplicar a todos" pisaba la oferta G1 con precios calculados sobre Retail.
	$aAuxs = tep_db_query( 'SELECT * FROM specials WHERE customers_group_id = 0 AND products_id = "' . (int)$product_id . '"' );
	
	// Guardamos ofertas del producto en un array
	while( $aAux = tep_db_fetch_array( $aAuxs ) )
	{
		if( $aAux['start_date'] != '' && $aAux['start_date'] != '0000-00-00 00:00:00' )
			$aOfertas['fechas'] = $aAux['specials_id'];
		else
			$aOfertas['normal'] = $aAux['specials_id'];
	}
	
	// Si no tenemos oferta, la creamos
	if( count( $aOfertas ) <= 0 )
	{
		$key_fields = array();
		$value_fields = array();
		foreach($fields as $k => $v)
		{
			$key_fields[] = $k;
			$value_fields[] = "'$v'";
		}
		
		$key_fields = implode(', ', $key_fields);
		$value_fields = implode(', ', $value_fields);

		tep_db_query("INSERT INTO " . TABLE_SPECIALS . " (products_id, specials_date_added, status, $key_fields) VALUES ('" . (int)$product_id . "', now(), '" . ($activate ? '1' : '0') . "', $value_fields)");
	}
	else
	{
		// Si hemos introducido fecha de inicio
		if( $start_date != '' && $start_date != '0000-00-00 00:00:00' )
			$nSpecialId = $aOfertas['fechas'] ?? 0;
		else
			$nSpecialId = $aOfertas['normal'] ?? 0;

		if( $nSpecialId > 0 )
		{
			$set_fields = array();
			foreach($fields as $k => $v)
				$set_fields[] = "$k = '" . tep_db_input($v) . "'";

			if($activate)
				$set_fields[] = "status = '1'";

			$set_fields = implode(', ', $set_fields);

			tep_db_query("UPDATE " . TABLE_SPECIALS . " SET $set_fields WHERE specials_id = '" . (int)$nSpecialId . "'");
		}
		else
		{
			$key_fields = array();
			$value_fields = array();
			foreach($fields as $k => $v)
			{
				$key_fields[] = $k;
				$value_fields[] = "'$v'";
			}
			
			$key_fields = implode(', ', $key_fields);
			$value_fields = implode(', ', $value_fields);

			tep_db_query("INSERT INTO " . TABLE_SPECIALS . " (products_id, specials_date_added, status, $key_fields) VALUES ('" . (int)$product_id . "', now(), '" . ($activate ? '1' : '0') . "', $value_fields)");
		}
	}

	return true;
}

/*
	Updates a single special offer by its specials_id (edicion fila a fila)
*/
function specials_enhanced_update_special($sid, $discount = null, $discount_percent = false, $date = null, $start_date = null, $expires_repeat = null, $activate = true)
{
	$sid = (int)$sid;
	$row_query = tep_db_query("SELECT s.products_id, s.customers_group_id, p.products_price AS price, p.products_tax_class_id AS tax FROM " . TABLE_SPECIALS . " s INNER JOIN " . TABLE_PRODUCTS . " p ON p.products_id = s.products_id WHERE s.specials_id = $sid");
	if(!($row = tep_db_fetch_array($row_query)))
		return false;

	// Si la oferta es de un grupo (Profesionales, etc.), el % se aplica sobre el
	// precio base de ESE grupo, no sobre el Retail.
	if((int)$row['customers_group_id'] > 0)
	{
		$gp_query = tep_db_query("SELECT customers_group_price FROM " . TABLE_PRODUCTS_GROUPS . " WHERE products_id = '" . (int)$row['products_id'] . "' AND customers_group_id = '" . (int)$row['customers_group_id'] . "'");
		if($gp = tep_db_fetch_array($gp_query))
			$row['price'] = $gp['customers_group_price'];
	}

	$fields = array();

	if($discount !== null)
	{
		if($discount_percent)
			$discounted_price = ($row['price'] - (($discount / 100) * $row['price']));
		elseif(DISPLAY_PRICE_WITH_TAX == 'true')
			$discounted_price = floatval($discount/(1 + tep_get_tax_rate_value($row['tax'])/100));
		else
			$discounted_price = floatval($discount);

		$fields['specials_new_products_price'] = $discounted_price;
	}

	if($date !== null)
		$fields['expires_date'] = $date;

	if($start_date !== null)
		$fields['start_date'] = $start_date;

	if($expires_repeat !== null)
		$fields['expires_repeat'] = (int)$expires_repeat;

	if($activate)
		$fields['status'] = '1';

	if(count($fields) == 0)
		return true;

	$set_fields = array();
	foreach($fields as $k => $v)
		$set_fields[] = "$k = '" . tep_db_input($v) . "'";
	$set_fields = implode(', ', $set_fields);

	tep_db_query("UPDATE " . TABLE_SPECIALS . " SET $set_fields WHERE specials_id = $sid");

	return true;
}

/*
	Crea una oferta TEMPORAL (is_temp=1) con fecha de inicio y fin, sin tocar la oferta principal del producto.
	Al caducar, tep_expire_specials la desactiva (status=0) y reaparece la principal; luego se purga con remove_expired_temp.
*/
function specials_enhanced_add_temp($product_id, $group, $discount, $discount_percent, $start_date, $expires_date)
{
	$product_id = (int)$product_id;
	$product_query = tep_db_query("SELECT products_price AS price, products_tax_class_id AS tax FROM " . TABLE_PRODUCTS . " WHERE products_id = $product_id");
	if(!($product = tep_db_fetch_array($product_query)))
		return false;

	if($discount_percent)
		$price = ($product['price'] - (($discount / 100) * $product['price']));
	elseif(DISPLAY_PRICE_WITH_TAX == 'true')
		$price = floatval($discount/(1 + tep_get_tax_rate_value($product['tax'])/100));
	else
		$price = floatval($discount);

	tep_db_query("INSERT INTO " . TABLE_SPECIALS . " (products_id, customers_group_id, specials_new_products_price, specials_date_added, start_date, expires_date, status, expires_repeat, is_temp) VALUES ('" . $product_id . "', '" . (int)$group . "', '" . tep_db_input($price) . "', now(), '" . tep_db_input($start_date) . "', '" . tep_db_input($expires_date) . "', '1', '0', '1')");

	return true;
}

/* *********************************************************************** */

if (tep_not_null($action)) 
{
    switch ($action) 
	{
		// Enables/disables the special offer
		case 'setflag':
			specials_enhanced_set_status($_GET['id'], $_GET['flag']);
			tep_redirect(tep_href_link(FILENAME_SPECIALS_AVANZADO, tep_get_all_get_params(array('action','flag','id')), 'NONSSL'));
		break;
		
		// Enables/disables the specials of the filtered products
		case 'setflag_all':
			$specials_flag = 1;
			$ids = specials_enhanced_get_all_id();
		
			foreach($ids as $id)
			{
				if($id['sid'] != null)
					specials_enhanced_set_status($id['sid'], $_GET['flag']);
			}

			tep_redirect(tep_href_link(FILENAME_SPECIALS_AVANZADO, tep_get_all_get_params(array('action','flag','id')), 'NONSSL'));
		break;
		
		// Lists the filtered products
		case 'list':
			$specials_array = specials_enhanced_get_all_products($products_split, $products_query_numrows);
		break;
		
		// Updates a single product/special offer
		case 'update':
			$id = (isset($_GET['id']) ? intval($_GET['id']):0);
			$sid = (isset($_GET['sid']) ? intval($_GET['sid']):0);

			$has_change = ($discount !== null || $date !== null || $start_date !== null || $expires_repeat !== null);

			if($sid > 0 && $has_change)
				specials_enhanced_update_special($sid, $discount, $discount_percent, $date, $start_date, $expires_repeat, true);
			elseif($id && $discount !== null)
				specials_enhanced_update_product($id, $discount, $discount_percent, $date, $start_date, $expires_repeat, true);

			tep_redirect(tep_href_link(FILENAME_SPECIALS_AVANZADO, tep_get_all_get_params(array('action','id','sid','discount','date','start_date','expires_repeat')), 'NONSSL'));
		break;

		// Updates all the filtered products/special offers
		case 'update_all':
			if($discount !== null || $date !== null || $start_date !== null)
			{
				if($discount === null && $date != null)
					$specials_flag = 1;

				if($discount === null && $start_date != null)
                    $specials_flag = 1;

				$ids = specials_enhanced_get_all_id();
				foreach($ids as $id)
					specials_enhanced_update_product($id['pid'], $discount, $discount_percent, $date, $start_date, null, true);
			}

			tep_redirect(tep_href_link(FILENAME_SPECIALS_AVANZADO, tep_get_all_get_params(array('action','id','sid','discount','date','start_date','expires_repeat')), 'NONSSL'));
		break;

		// Crea una oferta temporal (boton +) para un producto
		case 'add_temp':
			$id = (isset($_GET['id']) ? intval($_GET['id']):0);
			if($id && $discount !== null && $start_date !== null && $date !== null)
				specials_enhanced_add_temp($id, 0, $discount, $discount_percent, $start_date, $date);

			tep_redirect(tep_href_link(FILENAME_SPECIALS_AVANZADO, tep_get_all_get_params(array('action','id','sid','discount','date','start_date','expires_repeat','percent_flag')), 'NONSSL'));
		break;

		// Borra todas las ofertas temporales ya caducadas (limpieza)
		case 'remove_expired_temp':
			tep_db_query("DELETE FROM " . TABLE_SPECIALS . " WHERE is_temp = '1' AND expires_date > 0 AND expires_date < NOW()");
			tep_redirect(tep_href_link(FILENAME_SPECIALS_AVANZADO, tep_get_all_get_params(array('action','id','sid','discount','date','start_date','expires_repeat','percent_flag')), 'NONSSL'));
		break;

		// removes a single special offer
		case 'remove':
			$sid = (isset($_GET['sid']) ? intval($_GET['sid']):0);
			tep_db_query("DELETE FROM " . TABLE_SPECIALS . " WHERE specials_id = $sid");
			tep_redirect(tep_href_link(FILENAME_SPECIALS_AVANZADO, tep_get_all_get_params(array('action','id')), 'NONSSL'));
		break;
		
		// removes all the filtered special offers
		case 'remove_all':
			$specials_flag = 1;
			$ids = specials_enhanced_get_all_id();

			foreach($ids as $id)
			{
				if($id['sid'] != null)
					tep_db_query("DELETE FROM " . TABLE_SPECIALS . " WHERE specials_id = $id[sid]");
			}
			tep_redirect(tep_href_link(FILENAME_SPECIALS_AVANZADO, tep_get_all_get_params(array('action')), 'NONSSL'));
		break;
    }
}
?>

<?php require(THEME . 'html/header.php'); ?>
<div id="popupcalendar" class="text"></div>


		<!-- body //-->
		<table border="0" width="100%" cellspacing="2" cellpadding="2">
			<tr>
				<!-- body_text //-->
				<td width="100%" valign="top">
					<table border="0" width="100%" cellspacing="0" cellpadding="2">
						<tr>
							<td width="100%">
								<table border="0" width="100%" cellspacing="0" cellpadding="0">
									<tr>
										<td class="pageHeading"><?php echo HEADING_TITLE; ?></td>
									</tr>
								</table>
							</td>
						</tr>
						<tr>
							<td>
								<form>
									<input name="action" type="hidden" value="list"/>
									<table border="0" cellpadding="0" cellspacing="0" width="100%">
										<tr>
											<td>
												<table>
													<tr>
														<td class="main"><b><?php echo @SPECIALS_ENHANCED_FILTER;?></b></td>
														<td class="main">
														    <?php echo @SPECIALS_ENHANCED_NAME;?>
														    <input name="product_name" type="text" value="<?php echo htmlspecialchars((string)$product_name, ENT_QUOTES); ?>" size="20" maxlength="100"/>
															<?php echo tep_draw_pull_down_menu('cPath', $categories_list, $current_category_id, '');?>
															<?php $current_manufacturer_id = $current_manufacturer_id ?? ''; echo tep_draw_pull_down_menu('manufacturer_id', $manufacturers_list, $current_manufacturer_id);?>
														</td>
														<td><input type="image" src="includes/languages/<?php echo $language;?>/images/buttons/button_specials_enh_apply.gif" onclick="this.form.action.value='list';this.form.submit();" alt="<?php echo @SPECIALS_ENHANCED_LIST; ?>"/></td>
													</tr>
													<tr>
														<td class="main"><b><?php echo @SPECIALS_ENHANCED_ORDERING;?></b></td>
														<td>
															<?php echo tep_draw_pull_down_menu('sort', $sort_list, $sort, '');?>
															<?php echo tep_draw_pull_down_menu('sort_type', array(array('id' => 'asc', 'text' => @SPECIALS_ENHANCED_ASC), array('id' => 'desc', 'text' => @SPECIALS_ENHANCED_DESC)), $sort_type, '');?>
														</td>
														<td></td>
													</tr>
													<tr>
														<td></td>
														<td>
															<input type="checkbox" name="subcats_flag" value="1"<?php echo (isset($_GET['subcats_flag']) && $_GET['subcats_flag'] == '1' ? 'checked="checked"':'') ?>/><span class="main"><?php echo @SPECIALS_ENHANCED_INCLUDE_SUBCATEGORIES; ?></span>
															<br/>
															<input type="checkbox" name="specials_flag" value="1"<?php echo (isset($_GET['specials_flag']) && $_GET['specials_flag'] == '1' ? 'checked="checked"':'') ?>/><span class="main"><?php echo @SPECIALS_ENHANCED_ONLY_SPECIALS; ?></span>
														</td>
														<td></td>
													</tr>
												</table>
											</td>
										</tr>
										<tr>
											<td><hr/></td>
										</tr>
										<tr>
											<td>
												<input type="hidden" name="flag" value=""/>
												<table width="100%" cellspacing="0" cellpadding="0">
													<tr>
														<td>
															<table border="0" cellpadding="0" cellspacing="0">
																<tr>
																	<td><span class="main"><?php echo @SPECIALS_ENHANCED_DISCOUNT; ?></span></td>
																	<td>
																		<input name="discount" type="text" value="" size="10"/>
																		<?php
																		ob_start();
																		?>
																		<select name="percent_flag">
																			<option value="0"><?php echo !empty($currencies->currencies[DEFAULT_CURRENCY]['symbol_left']) ? $currencies->currencies[DEFAULT_CURRENCY]['symbol_left']:$currencies->currencies[DEFAULT_CURRENCY]['symbol_right'] ; ?></option>
																			<option value="1">%</option>
																		</select>
																		<?php
																		$percent_select = ob_get_contents();
																		ob_end_clean();
																		echo $percent_select;
																		?>
																	</td>
																	<td></td>
																</tr>
																
																<tr>
																	<td><span class="main">Fecha Inicio (dd/mm/yyyy):</span></td>
																	<td><input name="start_date" class="dxdatepicker" type="text" value="" size="10" maxlength="10"/></td>
																	<td></td>
																</tr>
																<tr>
																	<td><span class="main">Fecha Fin (dd/mm/yyyy):</span></td>
																	<td><input name="date" class="dxdatepicker" type="text" value="" size="10" maxlength="10" /></td>
																	<td>&nbsp;&nbsp;<button type="submit" class="btn btn-primary" onclick="return confirmApplyAll(this.form);"><?php echo @SPECIALS_ENHANCED_APPLY_DISCOUNT; ?></button></td>
																</tr>
															</table>
														</td>
														<td align="right" valign="bottom">
															<button type="submit" class="btn" onclick="return confirmFlagAll(this.form,'1');"><?php echo @SPECIALS_ENHANCED_ACTIVATE_ALL; ?></button>
															<button type="submit" class="btn" onclick="return confirmFlagAll(this.form,'0');"><?php echo @SPECIALS_ENHANCED_DEACTIVATE_ALL; ?></button>
															&nbsp;
															<button type="submit" class="btn btn-danger" onclick="return confirmRemoveAll(this.form);"><?php echo @SPECIALS_ENHANCED_REMOVE_ALL; ?></button>
															<br/><br/>
															<button type="submit" class="btn" title="Elimina de la base de datos las ofertas temporales (boton +) que ya han caducado" onclick="return confirmRemoveExpiredTemp(this.form);"><i class="fa fa-broom"></i> Borrar temporales caducadas (<?php echo (int)$expired_temp_count; ?>)</button>
														</td>
													</tr>
												</table>
											</td>
										</tr>
									</table>
								</form>
							</td>
						</tr>
						<tr>
							<td>
								<table border="0" width="100%" cellspacing="0" cellpadding="0">
									<tr>
										<td valign="top">
											<table border="0" width="100%" cellspacing="0" cellpadding="2">
												<tr class="dataTableHeadingRow">
													<td width="10%" class="dataTableHeadingContent"><?php echo @SPECIALS_ENHANCED_TH_MODEL; ?></td>
													<td width="20%" class="dataTableHeadingContent"><?php echo @SPECIALS_ENHANCED_TH_PRODUCTS; ?></td>
														<td width="9%" class="dataTableHeadingContent" align="center">Grupo</td>
													<td width="8%" class="dataTableHeadingContent" align="center"><?php echo @SPECIALS_ENHANCED_TH_PRICE . '(' . (DISPLAY_PRICE_WITH_TAX == 'true' ? SPECIALS_ENHANCED_TH_GROSS:SPECIALS_ENHANCED_TH_NET) . ')'; ?></td>
													<td width="15%" class="dataTableHeadingContent" align="center"><?php echo @SPECIALS_ENHANCED_TH_DISCOUNTED_PRICE . '(' . (DISPLAY_PRICE_WITH_TAX == 'true' ? SPECIALS_ENHANCED_TH_GROSS:SPECIALS_ENHANCED_TH_NET) . ') / %'; ?></td>
													<td width="8%" class="dataTableHeadingContent" align="center"><?php echo @SPECIALS_ENHANCED_TH_DISCOUNT_PERCENT; ?></td>
													<td width="10%" class="dataTableHeadingContent" align="center">Fecha Inicio (dd/mm/yyyy)</td>
													<td width="10%" class="dataTableHeadingContent" align="center">Fecha Fin (dd/mm/yyyy)</td>
														<td width="7%" class="dataTableHeadingContent" align="center">Repetir</td>
													<td width="8%" class="dataTableHeadingContent" align="center"><?php echo @SPECIALS_ENHANCED_TH_STATUS; ?></td>
													<td class="dataTableHeadingContent" align="right"><?php echo @SPECIALS_ENHANCED_TH_ACTIONS; ?></td>
												</tr>
												<?php
												$row_idx = 0;
												foreach($specials_array as $specials)
												{
													$row_idx++;
													$tax_rate = tep_get_tax_rate_value($specials['products_tax_class_id']);
													// Precio base de la fila: el del grupo de la oferta si lo hay (G1, etc.), si no el Retail
													if((int)($specials['customers_group_id'] ?? 0) > 0 && !empty($specials['customers_group_price']))
														$specials['products_price'] = $specials['customers_group_price'];
												?>
												<tr class="dataTableRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)">
													<td colspan="11">
														<form>
															<input type="hidden" name="cPath" value="<?php echo $category_id;?>"/>
															<input type="hidden" name="manufacturer_id" value="<?php echo $manufacturer_id;?>"/>
															<input type="hidden" name="subcats_flag" value="<?php echo ($subcats_flag ? '1':'');?>"/>
															<input type="hidden" name="specials_flag" value="<?php echo ($specials_flag ? '1':'');?>"/>
															<input type="hidden" name="sort" value="<?php echo $sort;?>"/>
															<input type="hidden" name="sort_type" value="<?php echo $sort_type;?>"/>
															<input type="hidden" name="page" value="<?php echo $page;?>"/>
															<input type="hidden" name="product_name" value="<?php echo htmlspecialchars((string)$product_name, ENT_QUOTES);?>"/>
															<input type="hidden" name="action" value="update"/>
															<input type="hidden" name="id" value="<?php echo $specials['products_id'];?>"/>
															<input type="hidden" name="sid" value="<?php echo $specials['specials_id'];?>"/>
															<table width="100%">
																<tr>
																	<td width="10%" class="dataTableContent"><?php echo $specials['products_model']; ?></td>
																	<td width="20%" class="dataTableContent"><?php if(!empty($specials['is_temp'])) echo '<i class="fa fa-hourglass-half" style="color:#2980b9;margin-right:5px;" title="Oferta temporal"></i>'; echo $specials['products_name']; ?></td>
																		<td width="9%" class="dataTableContent" align="center"><?php echo ($specials['specials_id'] != null ? (isset($group_names[$specials['customers_group_id']]) ? $group_names[$specials['customers_group_id']] : ('Grupo ' . (int)$specials['customers_group_id'])) : '----'); ?></td>
																	<td width="8%" class="dataTableContent" align="center"><?php echo $currencies->display_price($specials['products_price'], $tax_rate); ?></td>
																	<td width="15%" class="dataTableContent" align="center"><?php echo $currencies->currencies[DEFAULT_CURRENCY]['symbol_left']; ?><input name="discount" style="border:1px solid #ccc;text-align:right" type="text" size="8" value="<?php echo number_format(tep_add_tax((float)($specials['specials_new_products_price'] ?? 0), $tax_rate),intval($currencies->currencies[DEFAULT_CURRENCY]["decimal_places"]), $currencies->currencies[DEFAULT_CURRENCY]["decimal_point"], $currencies->currencies[DEFAULT_CURRENCY]["thousands_point"]);?>"/> <?php echo $percent_select; ?></td>
																	<td width="8%" class="dataTableContent" align="center"><?php if($specials['specials_new_products_price']){echo number_format(-1*($specials['products_price'] - $specials['specials_new_products_price'])*100/$specials['products_price'], intval($currencies->currencies[DEFAULT_CURRENCY]["decimal_places"]), $currencies->currencies[DEFAULT_CURRENCY]["decimal_point"], $currencies->currencies[DEFAULT_CURRENCY]["thousands_point"]).'%';}else{ echo '---';} ?></td>
																	<td width="10%" class="dataTableContent" align="center"><input name="start_date" class="dxdatepicker" style="border:1px solid #ccc;" type="text" value="<?php echo (!empty($specials['start_date']) && $specials['start_date'] != '0000-00-00 00:00:00' ? preg_replace('/([0-9]{4,4})-([0-9]{2,2})-([0-9]{2,2}) ([0-9]{2,2}):([0-9]{2,2}):([0-9]{2,2})/','$3/$2/$1', $specials['start_date']):''); ?>" size="10" maxlength="10" onfocus="this.select();"/></td>
																	<td width="10%" class="dataTableContent" align="center"><input name="date" class="dxdatepicker" style="border:1px solid #ccc;" type="text" value="<?php echo (!empty($specials['expires_date']) && $specials['expires_date'] != '0000-00-00 00:00:00' ? preg_replace('/([0-9]{4,4})-([0-9]{2,2})-([0-9]{2,2}) ([0-9]{2,2}):([0-9]{2,2}):([0-9]{2,2})/','$3/$2/$1', $specials['expires_date']):''); ?>" size="10" maxlength="10" onfocus="this.select();"/></td>
																		<td width="7%" class="dataTableContent" align="center"><?php if($specials['specials_id'] != null) { echo tep_draw_pull_down_menu('expires_repeat', array(array('id'=>0,'text'=>'No'), array('id'=>1,'text'=>'Si')), (int)$specials['expires_repeat']);} else { echo '----'; } ?></td>
																	<td width="8%" class="dataTableContent" align="center">
																	<?php
																		if($specials['status'] != null)
																		{
																	      if ($specials['status'] == '1') {
																	        echo tep_image(DIR_WS_IMAGES . 'icon_status_green.gif', IMAGE_ICON_STATUS_GREEN, 10, 10) . '&nbsp;&nbsp;<a href="' . tep_href_link(FILENAME_SPECIALS_AVANZADO, 'action=setflag&flag=0&id=' . $specials['specials_id']. '&' . tep_get_all_get_params(array('id','flag','action','date','discount')), 'NONSSL') . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_red_light.gif', IMAGE_ICON_STATUS_RED_LIGHT, 10, 10) . '</a>';
																	      } else {
																	        echo '<a href="' . tep_href_link(FILENAME_SPECIALS_AVANZADO, 'action=setflag&flag=1&id=' . $specials['specials_id'] . '&' . tep_get_all_get_params(array('id','flag','action','date','discount')), 'NONSSL') . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_green_light.gif', IMAGE_ICON_STATUS_GREEN_LIGHT, 10, 10) . '</a>&nbsp;&nbsp;' . tep_image(DIR_WS_IMAGES . 'icon_status_red.gif', IMAGE_ICON_STATUS_RED, 10, 10);
																	      }
																		}
																		else
																			echo '----';
																	?>
																	</td>
																	<td align="right" style="white-space:nowrap;">
																		<button type="button" class="btn btn-success" title="A&ntilde;adir oferta temporal" style="margin-right:4px;" onclick="toggleTempRow('temprow_<?php echo $row_idx; ?>');"><i class="fa fa-plus"></i></button><button type="submit" class="btn" title="Guardar" style="margin-right:4px;"><i class="fa fa-save"></i></button><?php if($specials['specials_new_products_price']) { ?><button type="submit" class="btn btn-danger" title="Borrar" onclick="if(!confirm('<?php echo @SPECIALS_ENHANCED_REMOVE_CONFIRM; ?>')) return false; this.form.action.value='remove'; return true;"><i class="fa fa-trash"></i></button><?php } ?>
																	</td>
																</tr>
															</table>
														</form>
													</td>
												</tr>
												
																				<tr class="dataTableRow" id="temprow_<?php echo $row_idx; ?>" style="display:none;background:#eef6ff;">
																					<td colspan="11">
																						<form>
																							<input type="hidden" name="cPath" value="<?php echo $category_id;?>"/>
																							<input type="hidden" name="manufacturer_id" value="<?php echo $manufacturer_id;?>"/>
																							<input type="hidden" name="subcats_flag" value="<?php echo ($subcats_flag ? '1':'');?>"/>
																							<input type="hidden" name="specials_flag" value="<?php echo ($specials_flag ? '1':'');?>"/>
																							<input type="hidden" name="sort" value="<?php echo $sort;?>"/>
																							<input type="hidden" name="sort_type" value="<?php echo $sort_type;?>"/>
																							<input type="hidden" name="page" value="<?php echo $page;?>"/>
																							<input type="hidden" name="product_name" value="<?php echo htmlspecialchars((string)$product_name, ENT_QUOTES);?>"/>
																							<input type="hidden" name="action" value="add_temp"/>
																							<input type="hidden" name="id" value="<?php echo $specials['products_id'];?>"/>
																							<table width="100%"><tr>
																								<td class="dataTableContent" style="padding:6px;"><b><i class="fa fa-plus"></i> Nueva oferta temporal</b> &nbsp; (Retail)</td>
																								<td class="dataTableContent" align="center">Precio/Dto: <input name="discount" type="text" size="8" style="border:1px solid #ccc;text-align:right" value=""/> <?php echo $percent_select; ?></td>
																								<td class="dataTableContent" align="center">Inicio: <input name="start_date" class="dxdatepicker" type="text" size="10" maxlength="10" style="border:1px solid #ccc;" value=""/></td>
																								<td class="dataTableContent" align="center">Fin: <input name="date" class="dxdatepicker" type="text" size="10" maxlength="10" style="border:1px solid #ccc;" value=""/></td>
																								<td class="dataTableContent" align="right" style="white-space:nowrap;">
																									<button type="submit" class="btn btn-primary" title="Crear oferta temporal" onclick="return confirmAddTemp(this.form);"><i class="fa fa-plus"></i> Crear</button>
																									<button type="button" class="btn" title="Cancelar" onclick="toggleTempRow('temprow_<?php echo $row_idx; ?>');"><i class="fa fa-times"></i></button>
																								</td>
																							</tr></table>
																						</form>
																					</td>
																				</tr>
																<?php
												}
												?>
												<tr>
													<td colspan="4">
														<table border="0" width="100%" cellpadding="0"cellspacing="2">
															<tr>
																<td class="smallText" valign="top"><?php echo $products_split->display_count($products_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, $page, TEXT_DISPLAY_NUMBER_OF_PRODUCTS); ?></td>
																<td class="smallText" align="right"><?php echo $products_split->display_links($products_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, MAX_DISPLAY_PAGE_LINKS, $page, tep_get_all_get_params(array('page', 'x', 'y'))); ?></td>
															</tr>
														</table>
													</td>
												</tr>
											</table>
										</td>
									</tr>
								</table>
							</td>
						</tr>
					</table>
				</td>
				<!-- body_text_eof //-->
			</tr>
		</table>


<script type="text/javascript">
	var filteredCount = <?php echo (int)($products_query_numrows ?? 0); ?>;

	function confirmApplyAll(form){
		if(!confirm('Vas a aplicar el descuento/fechas a ' + filteredCount + ' productos filtrados.\nSolo se toca la oferta RETAIL de cada producto (las de Profesionales/Amazon/EBAY no se modifican).\nLas ofertas afectadas quedaran ACTIVADAS.\n\nContinuar?')) return false;
		form.action.value = 'update_all';
		return true;
	}

	function confirmFlagAll(form, flag){
		var txt = (flag == '1') ? 'ACTIVAR' : 'DESACTIVAR';
		if(!confirm('Vas a ' + txt + ' las ofertas de ' + filteredCount + ' productos filtrados.\nOJO: afecta a TODAS las ofertas (Retail y grupos: Profesionales, Amazon, EBAY).\n\nContinuar?')) return false;
		form.action.value = 'setflag_all';
		form.flag.value = flag;
		return true;
	}

	function confirmRemoveAll(form){
		if(!confirm('Vas a BORRAR las ofertas de ' + filteredCount + ' productos filtrados.\nOJO: borra TODAS las ofertas (Retail y grupos: Profesionales, Amazon, EBAY).\nEsta accion NO se puede deshacer.\n\nContinuar?')) return false;
		form.action.value = 'remove_all';
		return true;
	}

	function toggleTempRow(rowId){
		var r = document.getElementById(rowId);
		if(!r) return;
		r.style.display = (r.style.display === 'none' || r.style.display === '') ? 'table-row' : 'none';
	}

	function confirmAddTemp(form){
		if(form.discount.value == '' || form.start_date.value == '' || form.date.value == ''){
			alert('Para crear una oferta temporal indica el precio/descuento, la fecha de inicio y la fecha de fin.');
			return false;
		}
		return true;
	}

	function confirmRemoveExpiredTemp(form){
		if(!confirm('Vas a BORRAR de la base de datos todas las ofertas temporales ya caducadas.\nLa oferta principal de cada producto (si la hay) seguira vigente.\n\nContinuar?')) return false;
		form.action.value = 'remove_expired_temp';
		return true;
	}
</script>

<!-- footer //-->
<?php require(THEME . 'html/footer.php'); ?>
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>