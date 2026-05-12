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

	$discount = floatval(str_replace($currencies->currencies[DEFAULT_CURRENCY]["decimal_point"], ".", $discount));
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
	   $clauses[] = "pd.products_name LIKE '%" .  $product_name . "%'";
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

	$fields = 'p.products_id, p.products_price, p.products_tax_class_id, p.products_model, pd.products_name, s.specials_id, s.specials_new_products_price, s.expires_date, s.status, s.start_date';
	
	if($specials_flag)
	{
		$tables[] = TABLE_PRODUCTS . ' p INNER JOIN ' . TABLE_SPECIALS . ' s ON p.products_id = s.products_id INNER JOIN ' . TABLE_PRODUCTS_DESCRIPTION . ' pd ON p.products_id = pd.products_id';
	}
	else
	{
		$tables[] = TABLE_PRODUCTS . ' p LEFT JOIN ' . TABLE_SPECIALS . ' s ON p.products_id = s.products_id INNER JOIN ' . TABLE_PRODUCTS_DESCRIPTION . ' pd ON p.products_id = pd.products_id';
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
	   $clauses[] = "pd.products_name LIKE '%" .  $product_name . "%'";
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
function specials_enhanced_update_product($product_id, $discount = null, $discount_percent = false, $date = null, $start_date = null)
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

	$aOfertas = array();
	// Obtenemos ofertas del producto
	$aAuxs = tep_db_query( 'SELECT * FROM specials WHERE products_id = "' . (int)$product_id . '"' );
	
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

		tep_db_query("INSERT INTO " . TABLE_SPECIALS . " (products_id, specials_date_added, status, $key_fields) VALUES ('" . (int)$product_id . "', now(), '0', $value_fields)");
	}
	else
	{
		// Si hemos introducido fecha de inicio
		if( $start_date != '' && $start_date != '0000-00-00 00:00:00' )
			$nSpecialId = $aOfertas['fechas'];
		else
			$nSpecialId = $aOfertas['normal'];

		if( $nSpecialId > 0 )
		{
			$set_fields = array();
			foreach($fields as $k => $v)
				$set_fields[] = "$k = '$v'";

			$set_fields = implode(', ', $set_fields);
			
			tep_db_query("UPDATE " . TABLE_SPECIALS . " SET $set_fields WHERE specials_id = '$nSpecialId'");
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

			tep_db_query("INSERT INTO " . TABLE_SPECIALS . " (products_id, specials_date_added, status, $key_fields) VALUES ('" . (int)$product_id . "', now(), '0', $value_fields)");
		}
	}

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
			
			if($id && $discount !== null)
				specials_enhanced_update_product($id, $discount, $discount_percent, $date, $start_date);

			tep_redirect(tep_href_link(FILENAME_SPECIALS_AVANZADO, tep_get_all_get_params(array('action','id','discount','date', 'start_date')), 'NONSSL'));
		break;

		// Updates all the filtered products/special offers
		case 'update_all':
			if($discount !== null || $date !== null)
			{
				if($discount === null && $date != null)
					$specials_flag = 1;

				if($discount === null && $start_date != null)
                    $specials_flag = 1;

				$ids = specials_enhanced_get_all_id();
				foreach($ids as $id)
					specials_enhanced_update_product($id['pid'], $discount, $discount_percent, $date, $start_date);
			}

			tep_redirect(tep_href_link(FILENAME_SPECIALS_AVANZADO, tep_get_all_get_params(array('action','id','discount','date', 'start_date')), 'NONSSL'));
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
														    <input name="product_name" type="text" value="<?php echo $product_name; ?>" size="20" maxlength="100"/>
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
																	<td><input name="start_date" type="text" value="" size="10" maxlength="10"/></td>
																	<td></td>
																</tr>
																<tr>
																	<td><span class="main">Fecha Fin (dd/mm/yyyy):</span></td>
																	<td><input name="date" type="text" value="" size="10" maxlength="10" /></td>
																	<td>&nbsp;&nbsp;<input type="image" src="includes/languages/<?php echo $language;?>/images/buttons/button_specials_enh_apply_discount.gif" onclick="this.form.action.value='update_all';this.form.submit();" alt="<?php echo @SPECIALS_ENHANCED_APPLY_DISCOUNT; ?>"/></td>
																</tr>
															</table>
														</td>
														<td align="right" valign="bottom">
															<input type="image" src="includes/languages/<?php echo $language;?>/images/buttons/button_specials_enh_activate.gif" onclick="if(!confirm('<?php echo @SPECIALS_ENHANCED_GENERAL_CONFIRM;?>')) return false;this.form.action.value='setflag_all';this.form.flag.value='1';this.form.submit();" alt="<?php echo @SPECIALS_ENHANCED_ACTIVATE_ALL; ?>"/>
															<input type="image" src="includes/languages/<?php echo $language;?>/images/buttons/button_specials_enh_deactivate.gif" onclick="if(!confirm('<?php echo @SPECIALS_ENHANCED_GENERAL_CONFIRM;?>')) return false;this.form.action.value='setflag_all';this.form.flag.value='0';this.form.submit();" alt="<?php echo @SPECIALS_ENHANCED_DEACTIVATE_ALL; ?>"/>
															&nbsp;
															<input type="image" src="includes/languages/<?php echo $language;?>/images/buttons/button_specials_enh_remove.gif" onclick="if(!confirm('<?php echo @SPECIALS_ENHANCED_GENERAL_CONFIRM;?>')) return false;this.form.action.value='remove_all';this.form.submit();" alt="<?php echo @SPECIALS_ENHANCED_REMOVE_ALL; ?>"/>
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
													<td width="25%" class="dataTableHeadingContent"><?php echo @SPECIALS_ENHANCED_TH_PRODUCTS; ?></td>
													<td width="8%" class="dataTableHeadingContent" align="center"><?php echo @SPECIALS_ENHANCED_TH_PRICE . '(' . (DISPLAY_PRICE_WITH_TAX == 'true' ? SPECIALS_ENHANCED_TH_GROSS:SPECIALS_ENHANCED_TH_NET) . ')'; ?></td>
													<td width="15%" class="dataTableHeadingContent" align="center"><?php echo @SPECIALS_ENHANCED_TH_DISCOUNTED_PRICE . '(' . (DISPLAY_PRICE_WITH_TAX == 'true' ? SPECIALS_ENHANCED_TH_GROSS:SPECIALS_ENHANCED_TH_NET) . ') / %'; ?></td>
													<td width="8%" class="dataTableHeadingContent" align="center"><?php echo @SPECIALS_ENHANCED_TH_DISCOUNT_PERCENT; ?></td>
													<td width="10%" class="dataTableHeadingContent" align="center">Fecha Inicio (dd/mm/yyyy)</td>
													<td width="10%" class="dataTableHeadingContent" align="center">Fecha Fin (dd/mm/yyyy)</td>
													<td width="8%" class="dataTableHeadingContent" align="center"><?php echo @SPECIALS_ENHANCED_TH_STATUS; ?></td>
													<td class="dataTableHeadingContent" align="right"><?php echo @SPECIALS_ENHANCED_TH_ACTIONS; ?></td>
												</tr>
												<?php
												foreach($specials_array as $specials) 
												{
													$tax_rate = tep_get_tax_rate_value($specials['products_tax_class_id']);
												?>
												<tr class="dataTableRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)">
													<td colspan="9">
														<form>
															<input type="hidden" name="cPath" value="<?php echo $category_id;?>"/>
															<input type="hidden" name="manufacturer_id" value="<?php echo $manufacturer_id;?>"/>
															<input type="hidden" name="subcats_flag" value="<?php echo ($subcats_flag ? '1':'');?>"/>
															<input type="hidden" name="specials_flag" value="<?php echo ($specials_flag ? '1':'');?>"/>
															<input type="hidden" name="sort" value="<?php echo $sort;?>"/>
															<input type="hidden" name="sort_type" value="<?php echo $sort_type;?>"/>
															<input type="hidden" name="page" value="<?php echo $page;?>"/>
															<input type="hidden" name="product_name" value="<?php echo $product_name;?>"/>
															<input type="hidden" name="action" value="update"/>
															<input type="hidden" name="id" value="<?php echo $specials['products_id'];?>"/>
															<input type="hidden" name="sid" value="<?php echo $specials['specials_id'];?>"/>
															<table width="100%">
																<tr>
																	<td width="10%" class="dataTableContent"><?php echo $specials['products_model']; ?></td>
																	<td width="25%" class="dataTableContent"><?php echo $specials['products_name']; ?></td>
																	<td width="8%" class="dataTableContent" align="center"><?php echo $currencies->display_price($specials['products_price'], $tax_rate); ?></td>
																	<td width="15%" class="dataTableContent" align="center"><?php echo $currencies->currencies[DEFAULT_CURRENCY]['symbol_left']; ?><input name="discount" style="border:1px solid #ccc;text-align:right" type="text" size="8" value="<?php echo number_format(tep_add_tax($specials['specials_new_products_price'], $tax_rate),intval($currencies->currencies[DEFAULT_CURRENCY]["decimal_places"]), $currencies->currencies[DEFAULT_CURRENCY]["decimal_point"], $currencies->currencies[DEFAULT_CURRENCY]["thousands_point"]);?>"/> <?php echo $percent_select; ?></td>
																	<td width="8%" class="dataTableContent" align="center"><?php if($specials['specials_new_products_price']){echo number_format(-1*($specials['products_price'] - $specials['specials_new_products_price'])*100/$specials['products_price'], intval($currencies->currencies[DEFAULT_CURRENCY]["decimal_places"]), $currencies->currencies[DEFAULT_CURRENCY]["decimal_point"], $currencies->currencies[DEFAULT_CURRENCY]["thousands_point"]).'%';}else{ echo '---';} ?></td>
																	<td width="10%" class="dataTableContent" align="center"><input name="start_date" style="border:1px solid #ccc;" type="text" value="<?php echo (!empty($specials['start_date']) && $specials['start_date'] != '0000-00-00 00:00:00' ? preg_replace('/([0-9]{4,4})-([0-9]{2,2})-([0-9]{2,2}) ([0-9]{2,2}):([0-9]{2,2}):([0-9]{2,2})/','$3/$2/$1', $specials['start_date']):''); ?>" size="10" maxlength="10" onfocus="this.select();"/></td>
																	<td width="10%" class="dataTableContent" align="center"><input name="date" style="border:1px solid #ccc;" type="text" value="<?php echo (!empty($specials['expires_date']) && $specials['expires_date'] != '0000-00-00 00:00:00' ? preg_replace('/([0-9]{4,4})-([0-9]{2,2})-([0-9]{2,2}) ([0-9]{2,2}):([0-9]{2,2}):([0-9]{2,2})/','$3/$2/$1', $specials['expires_date']):''); ?>" size="10" maxlength="10" onfocus="this.select();"/></td>
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
																	<td align="right">
																		<input type="image" src="images/button_specials_enh_update.gif" alt="<?php echo @SPECIALS_ENHANCED_UPDATE; ?>" style="border:1px solid #ccc;margin-right:5px;"/><?php if($specials['specials_new_products_price']) { ?><input type="image" src="images/button_specials_enh_remove.gif" alt="<?php echo @SPECIALS_ENHANCED_REMOVE; ?>" onclick="if(!confirm('<?php echo @SPECIALS_ENHANCED_REMOVE_CONFIRM; ?>')) return false;this.form.action.value ='remove';this.form.submit();" style="border:1px solid #ccc;"/><?php }else{?><img src="images/button_specials_enh_no_remove.gif" alt="<?php echo @SPECIALS_ENHANCED_REMOVE; ?>" style="border:1px solid #ccc;"/><?php } ?>
																	</td>
																</tr>
															</table>
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


<!-- footer //-->
<?php require(THEME . 'html/footer.php'); ?>
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>