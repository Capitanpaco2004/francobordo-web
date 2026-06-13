<?php
// Tools
use util\tools as tools;
use util\strings;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function tep_admin_check_login() {
	global $PHP_SELF, $login_groups_id;
	if (!tep_session_is_registered('login_id')) {
		tep_redirect(tep_href_link(FILENAME_LOGIN, '', 'SSL'));
	} else {
		$filename = basename($PHP_SELF);
		if ($filename != FILENAME_DEFAULT && $filename != FILENAME_FORBIDDEN && $filename != FILENAME_LOGOFF_ADMIN && $filename != FILENAME_ADMIN_ACCOUNT && $filename != FILENAME_POPUP_IMAGE && $filename != 'packingslip.php' && $filename != 'invoice.php') {
			$db_file_query = tep_db_query("select admin_files_name from " . TABLE_ADMIN_FILES . " where FIND_IN_SET( '" . $login_groups_id . "', admin_groups_id) and admin_files_is_boxes = '0' and admin_files_name = '" . tep_db_input($filename) . "'");
			if (!tep_db_num_rows($db_file_query)) {
				tep_redirect(tep_href_link(FILENAME_FORBIDDEN));
			}
		}
	}
}

// Funcion usada para las cabeceras de los listados con tablas para ordenar
function tableSetSort($sId, $sName, $aGetParamerters = []) {
	global $sGetOrderby, $sGetSort, $sUrlPage;
	$sSort = 'DESC';

	if ($sGetOrderby == $sId && $sGetSort == 'DESC')
		$sSort = 'ASC';

	$sClass = '';
	if ($sGetOrderby == $sId)
		$sClass = ($sSort == 'DESC' ? 'asc' : 'desc');

	return '<span class="sort ' . $sClass . '"></span><a href="' . tep_href_link($sUrlPage, tep_get_all_get_params(array_merge(['page', 'orderby', 'sort'], $aGetParamerters)) . 'orderby=' . $sId . '&amp;sort=' . $sSort) . '">' . $sName . '</a>';
}

////
//Return 'true' or 'false' value to display boxes and files in index.php and column_left.php
function tep_admin_check_boxes($filename, $boxes = '') {
	global $login_groups_id;
  static $cache = [];

  $cache_key = $filename . '|' . $boxes;
  if (isset($cache[$cache_key])) return $cache[$cache_key];

	$is_boxes = 1;
	if ($boxes == 'sub_boxes') {
		$is_boxes = 0;
	}
	$dbquery = tep_db_query("select admin_files_id from " . TABLE_ADMIN_FILES . " where FIND_IN_SET( '" . $login_groups_id . "', admin_groups_id) and admin_files_is_boxes = '" . $is_boxes . "' and admin_files_name = '" . $filename . "'");

	$return_value = false;
	if (tep_db_num_rows($dbquery)) {
		$return_value = true;
	}
  $cache[$cache_key] = $return_value;
	return $return_value;
}

////
//Return files stored in box that can be accessed by user
function tep_admin_files_boxes($filename, $sub_box_name, $parameters = '') {
	global $login_groups_id;
	$sub_boxes = '';

	if ($login_groups_id == 1) {
		$sub_boxes = '<a href="' . tep_href_link($filename, $parameters) . '" class="menuBoxContentLink">' . $sub_box_name . '</a>';
	} else {
		$dbquery = tep_db_query("select admin_files_name from " . TABLE_ADMIN_FILES . " where FIND_IN_SET( '" . $login_groups_id . "', admin_groups_id) and admin_files_is_boxes = '0' and admin_files_name = '" . tep_db_input($filename) . "'");
		if (tep_db_num_rows($dbquery)) {
			$sub_boxes = '<a href="' . tep_href_link($filename, $parameters) . '" class="menuBoxContentLink">' . $sub_box_name . '</a>';
		}
	}
	return $sub_boxes;
}

////
//Get selected file for index.php
function tep_selected_file($filename) {
	global $login_groups_id;
	$randomize = FILENAME_ADMIN_ACCOUNT;

	$dbquery = tep_db_query("select admin_files_id as boxes_id from " . TABLE_ADMIN_FILES . " where FIND_IN_SET( '" . $login_groups_id . "', admin_groups_id) and admin_files_is_boxes = '1' and admin_files_name = '" . tep_db_input($filename) . "'");
	if (tep_db_num_rows($dbquery)) {
		$boxes_id        = tep_db_fetch_array($dbquery);
		$randomize_query = tep_db_query("select admin_files_name from " . TABLE_ADMIN_FILES . " where FIND_IN_SET( '" . $login_groups_id . "', admin_groups_id) and admin_files_is_boxes = '0' and admin_files_to_boxes = '" . $boxes_id['boxes_id'] . "'");
		if (tep_db_num_rows($randomize_query)) {
			$file_selected = tep_db_fetch_array($randomize_query);
			$randomize     = $file_selected['admin_files_name'];
		}
	}
	return $randomize;
}

// EOE Access with Level Account (v. 2.2a) for the Admin Area of osCommerce (MS2) 1 of 1
function tep_array_merge($array1, $array2, $array3 = '') {
	if ($array3 == '') {
		$array3 = [];
	}

	if (function_exists('array_merge')) {
		$array_merged = array_merge($array1, $array2, $array3);
	} else {
		foreach ($array1 as $key => $val) $array_merged[$key] = $val;
		foreach ($array2 as $key => $val) $array_merged[$key] = $val;

		if (count($array3) > 0) {
			foreach ($array3 as $key => $val)
				$array_merged[$key] = $val;
		}
	}

	return $array_merged;
}

// BOF: XSell
function rdel($path, $deldir = true) {
	// $path is the path on the php file
	// $deldir (optional, defaults to true) allow if you want to delete the directory (true) or empty only (false)

	// it first checks the name of the directory contents "/" at the end, if we add it
	if ($path[strlen($path) - 1] != "/")
		$path .= "/";

	if (is_dir($path)) {
		$d = opendir($path);

		while ($f = readdir($d)) {
			if ($f != "." && $f != "..") {
				$rf = $path . $f; // path of the php file

				if (is_dir($rf)) // if it is the directory of the function recursively call
					rdel($rf);
				else // if you delete the file
					unlink($rf);
			}
		}
		closedir($d);

		if ($deldir) // if $deldir is true you delete the directory
			rmdir($path);
	} else {
		unlink($path);
	}
}

// EOF: XSell

////
// Redirect to another page or site
function tep_redirect($url) {
	if ((str_contains((string)$url, "\n")) || (str_contains((string)$url, "\r"))) {
		tep_redirect(tep_href_link(FILENAME_DEFAULT, '', 'NONSSL'));
	}

	if (str_contains((string)$url, '&amp;')) {
		$url = str_replace('&amp;', '&', $url);
	}

	header('Location: ' . $url);

	exit;
}

////
// Parse the data used in the html tags to ensure the tags will not break
function tep_parse_input_field_data($data, $parse) {
	return strtr(trim((string)$data), $parse);
}

function tep_output_string($string, $translate = false, $protected = false) {
	if ($protected == true) {
		return htmlspecialchars($string ?? '');
	} else if ($translate == false) {
		return tep_parse_input_field_data($string ?? '', ['"' => '&quot;']);
	} else {
		return tep_parse_input_field_data($string ?? '', $translate);
	}
}


function tep_output_string_protected($string) {
	return tep_output_string($string, false, true);
}

function tep_sanitize_string($string) {
	$string = preg_replace('{ +}', ' ', trim((string)$string));

	return preg_replace("/[<>]/", '_', (string)$string);
}

function tep_customers_name($customers_id) {
	$customers        = tep_db_query("select customers_firstname, customers_lastname from " . TABLE_CUSTOMERS . " where customers_id = '" . (int)$customers_id . "'");
	$customers_values = tep_db_fetch_array($customers);

	return $customers_values['customers_firstname'] . ' ' . $customers_values['customers_lastname'];
}

function tep_get_path($current_category_id = '') {
	global $cPath_array, $countproducts;

	if ($current_category_id == '') {
		if (!isset($cPath_array) || (sizeof($cPath_array) == 0)) {
			$cPath_new = '';
		} else {
			$cPath_new = implode('_', $cPath_array);
		}
	} else {
		if (!isset($cPath_array) || (sizeof($cPath_array) == 0)) {
			$cPath_new = $current_category_id;
		} else {
			$cPath_new = '';
			if (is_object($countproducts)) {
				$last_category['parent_id'] = '';
				$parentID                   = $countproducts->getParentCategory((int)$cPath_array[(sizeof($cPath_array) - 1)]);
				if ($parentID !== false) {
					$last_category['parent_id'] = $parentID;
				} // end if ($parentID !== false)
			} else {
				$last_category_query = tep_db_query("select parent_id from " . TABLE_CATEGORIES . " where categories_id = '" . (int)$cPath_array[(sizeof($cPath_array) - 1)] . "'");
				$last_category       = tep_db_fetch_array($last_category_query);
			}

			if (is_object($countproducts)) {
				$current_category['parent_id'] = '';
				$parentID                      = $countproducts->getParentCategory((int)$current_category_id);
				if ($parentID !== false) {
					$current_category['parent_id'] = $parentID;
				} // end if ($parentID !== false)
			} else {
				$current_category_query = tep_db_query("select parent_id from " . TABLE_CATEGORIES . " where categories_id = '" . (int)$current_category_id . "'");
				$current_category       = tep_db_fetch_array($current_category_query);
			}

			if ($last_category['parent_id'] == $current_category['parent_id']) {
				for ($i = 0, $n = sizeof($cPath_array) - 1; $i < $n; $i++) {
					$cPath_new .= '_' . $cPath_array[$i];
				}
			} else {
				for ($i = 0, $n = sizeof($cPath_array); $i < $n; $i++) {
					$cPath_new .= '_' . $cPath_array[$i];
				}
			}

			$cPath_new .= '_' . $current_category_id;

			if (substr($cPath_new, 0, 1) == '_') {
				$cPath_new = substr($cPath_new, 1);
			}
		}
	}

	return 'cPath=' . $cPath_new;
}

function tep_get_all_get_params($exclude_array = '') {
	global $_GET;

	if ($exclude_array == '') {
		$exclude_array = [];
	}

	$get_url = '';

	foreach ($_GET as $key => $value) {
		if (($key != tep_session_name()) && ($key != 'error') && (!in_array($key, $exclude_array))) {
			if (is_array($value)) {
				foreach ($value as $keyAux => $sAux)
					$get_url .= $key . '[' . $keyAux . ']=' . $sAux . '&';
			} else {
				$get_url .= $key . '=' . $value . '&';
			}
		}
	}

	return $get_url;
}

function tep_date_long($raw_date) {
	if (($raw_date == '0000-00-00 00:00:00') || ($raw_date == '')) {
		return false;
	}

	$year   = (int)substr((string)$raw_date, 0, 4);
	$month  = (int)substr((string)$raw_date, 5, 2);
	$day    = (int)substr((string)$raw_date, 8, 2);
	$hour   = (int)substr((string)$raw_date, 11, 2);
	$minute = (int)substr((string)$raw_date, 14, 2);
	$second = (int)substr((string)$raw_date, 17, 2);

	$aReplaceEn = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY', 'JANUARY', 'FEBRUARY', 'MARCH', 'APRIL', 'MAY', 'JUNE', 'JULY', 'AUGUST', 'SEPTEMBER', 'OCTOBER', 'NOVEMBER', 'DECEMBER'];
	$aReplaceEs = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

	return str_ireplace($aReplaceEn, $aReplaceEs, date('Y-m-d H:i:s', mktime($hour, $minute, $second, $month, $day, $year)));
}

////
// Output a raw date string in the selected locale date format
// $raw_date needs to be in this format: YYYY-MM-DD HH:MM:SS
// NOTE: Includes a workaround for dates before 01/01/1970 that fail on windows servers
function tep_date_short($raw_date) {
	if (($raw_date == '0000-00-00 00:00:00') || ($raw_date == '')) {
		return false;
	}

	$year   = substr((string)$raw_date, 0, 4);
	$month  = (int)substr((string)$raw_date, 5, 2);
	$day    = (int)substr((string)$raw_date, 8, 2);
	$hour   = (int)substr((string)$raw_date, 11, 2);
	$minute = (int)substr((string)$raw_date, 14, 2);
	$second = (int)substr((string)$raw_date, 17, 2);

	if (@date('Y', mktime($hour, $minute, $second, $month, $day, $year)) === $year) {
		return date(DATE_FORMAT, mktime($hour, $minute, $second, $month, $day, $year));
	} else {
		return preg_replace('/2037$/', $year, date(DATE_FORMAT, mktime($hour, $minute, $second, $month, $day, 2037)));
	}

}

function tep_datetime_short($raw_datetime) {
	if (($raw_datetime == '0000-00-00 00:00:00') || ($raw_datetime == '')) {
		return false;
	}

	$year   = (int)substr((string)$raw_datetime, 0, 4);
	$month  = (int)substr((string)$raw_datetime, 5, 2);
	$day    = (int)substr((string)$raw_datetime, 8, 2);
	$hour   = (int)substr((string)$raw_datetime, 11, 2);
	$minute = (int)substr((string)$raw_datetime, 14, 2);
	$second = (int)substr((string)$raw_datetime, 17, 2);

	$date = new DateTime("$year-$month-$day $hour:$minute:$second");

	return $date->format('d/m/Y H:i');
}

function tep_date_day_short($raw_datetime) {
	if (($raw_datetime == '0000-00-00 00:00:00') || ($raw_datetime == '')) {
		return false;
	}

	return (int)substr((string)$raw_datetime, 8, 2);
}


function tep_date_month_short($raw_datetime) {
	if (($raw_datetime == '0000-00-00 00:00:00') || ($raw_datetime == '')) {
		return false;
	}
	$month = (int)substr((string)$raw_datetime, 5, 2);


	if ($month == 1)
		$mes = 'Ene';
	if ($month == 2)
		$mes = 'Feb';
	if ($month == 3)
		$mes = 'Mar';
	if ($month == 4)
		$mes = 'Abr';
	if ($month == 5)
		$mes = 'May';
	if ($month == 6)
		$mes = 'Jun';
	if ($month == 7)
		$mes = 'Jul';
	if ($month == 8)
		$mes = 'Ago';
	if ($month == 9)
		$mes = 'Sep';
	if ($month == 10)
		$mes = 'Oct';
	if ($month == 11)
		$mes = 'Nov';
	if ($month == 12)
		$mes = 'Dic';

	return $mes;
}

function tep_datetime_hour_short($raw_datetime) {
	if (($raw_datetime == '0000-00-00 00:00:00') || ($raw_datetime == '')) {
		return false;
	}

	$datetime = new DateTime($raw_datetime);
	return $datetime->format('H:i') . 'h.';
}


// Obtiene todas las categorias y devuelve un array en una sola consulta
function getAllCategoryArray() {
	// Variables
	global $languages_id;
	$aReturn = [];

	$aDatos = tep_db_query('select c.categories_id, cd.categories_name, c.parent_id, c.categories_image
								 from categories c
								 inner join categories_description cd on(c.categories_id = cd.categories_id)
								 where cd.language_id = "' . (int)$languages_id . '"
								 order by sort_order, cd.categories_name');

	while ($aDato = tep_db_fetch_array($aDatos))
		$aReturn[$aDato['parent_id']][] = $aDato;
	ksort($aReturn);

	return $aReturn;
}

// Obtenemos recursivamente array de categorias
function getRecursiveIdCategories($aCategories, $nSearch, &$aReturn, $sSpace = '') {
	if (isset($aCategories[$nSearch])) {
		foreach ($aCategories[$nSearch] as $aCategory) {
			$aReturn[] = ['id' => $aCategory['categories_id'], 'text' => $sSpace . $aCategory['categories_name']];
			getRecursiveIdCategories($aCategories, $aCategory['categories_id'], $aReturn, '&nbsp;&nbsp;&nbsp;' . $sSpace);
		}
	}
}


// Obtenemos recursivamente las id de las categorias
function getRecursiveIdCategoriesByComma($aCategoria, $nIdSearch) {
	$sReturn = '';

	if (array_key_exists($nIdSearch, $aCategoria)) {
		foreach ($aCategoria[$nIdSearch] as $aAux) {
			$sIds    = getRecursiveIdCategoriesByComma($aCategoria, $aAux['categories_id']);
			$sReturn .= $aAux['categories_id'] . ',' . ($sIds != '' ? $sIds : '');
		}
	}

	return $sReturn;
}

function getCategoryTreeInMemory($name = 'cPath') {
	$allCategories   = getAllCategoryArray();
	$buildCategories = [['id' => '0', 'text' => TEXT_TOP]];

	if (!function_exists('getCategoryTreeInMemoryRecursive')) {
		function getCategoryTreeInMemoryRecursive(array $categories, int $idCategory, int $deph, array &$buildCategories) {
			if (isset($categories[$idCategory])) {
				$prefix = $deph == 0 ? '' : str_repeat('&nbsp;&nbsp;&nbsp;', $deph);

				foreach ($categories[$idCategory] as $category) {
					$buildCategories[] = ['id' => $category['categories_id'], 'text' => $prefix . $category['categories_name']];

					getCategoryTreeInMemoryRecursive($categories, $category['categories_id'], $deph + 1, $buildCategories);
				}
			}
		}
	}

	getCategoryTreeInMemoryRecursive($allCategories, 0, 0, $buildCategories);

	return '<input class="select-categories-search" name="' . $name . '" placeholder="' . TEXT_CATEGORIES . '" onChange="this.form.submit();" /><div style="display: none;" class="select-categories-search-result">' . json_encode($buildCategories) . '</div>';
}

function tep_get_category_tree($parent_id = '0', $spacing = '', $exclude = '', $category_tree_array = '', $include_itself = false) {
	global $languages_id;

	if (!is_array($category_tree_array)) {
		$category_tree_array = [];
	}
	if ((count($category_tree_array) < 1) && ($exclude != '0')) {
		$category_tree_array[] = ['id' => '0', 'text' => TEXT_TOP];
	}

	if ($include_itself) {
		$category_query        = tep_db_query("select cd.categories_name from " . TABLE_CATEGORIES_DESCRIPTION . " cd where cd.language_id = '" . (int)$languages_id . "' and cd.categories_id = '" . (int)$parent_id . "'");
		$category              = tep_db_fetch_array($category_query);
		$category_tree_array[] = ['id' => $parent_id, 'text' => $category['categories_name']];
	}

	$categories_query = tep_db_query("select c.categories_id, cd.categories_name, c.parent_id from " . TABLE_CATEGORIES . " c INNER JOIN " . TABLE_CATEGORIES_DESCRIPTION . " cd on (c.categories_id = cd.categories_id) where cd.language_id = '" . (int)$languages_id . "' and c.parent_id = '" . (int)$parent_id . "' order by c.sort_order, cd.categories_name");
	while ($categories = tep_db_fetch_array($categories_query)) {
		if ($exclude != $categories['categories_id']) {
			$category_tree_array[] = ['id' => $categories['categories_id'], 'text' => $spacing . $categories['categories_name']];
		}
		$category_tree_array = tep_get_category_tree($categories['categories_id'], $spacing . '&nbsp;&nbsp;&nbsp;', $exclude, $category_tree_array);
	}

	return $category_tree_array;
}

function tep_draw_products_pull_down($name, $parameters = '', $exclude = '', $selected = '', $sWhere = false) {
	global $currencies, $languages_id;

	if ($exclude == '') {
		$exclude = [];
	}

	$select_string = '<select name="' . $name . '"';

	if ($parameters) {
		$select_string .= ' ' . $parameters;
	}

	$select_string .= '>';

	// BOF Separate Pricing Per Customer
	$all_groups             = [];
	$customers_groups_query = tep_db_query("select customers_group_name, customers_group_id from " . TABLE_CUSTOMERS_GROUPS . " order by customers_group_id ");
	while ($existing_groups = tep_db_fetch_array($customers_groups_query)) {
		$all_groups[$existing_groups['customers_group_id']] = $existing_groups['customers_group_name'];
	}
	// EOF Separate Pricing Per Customer
	$products_query = tep_db_query("select p.products_id, pd.products_name, p.products_price, p.products_tax_class_id from " . TABLE_PRODUCTS . " p INNER JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON (p.products_id = pd.products_id) where pd.language_id = '" . (int)$languages_id . "' " . ($sWhere ? $sWhere : '') . " order by products_name limit 10");
	while ($products = tep_db_fetch_array($products_query)) {
		// BOF Separate Price Per Customer
		if (!in_array($products['products_id'], $exclude)) {
			$price_query    = tep_db_query("select customers_group_price, customers_group_id from " . TABLE_PRODUCTS_GROUPS . " where products_id = " . $products['products_id']);
			$product_prices = [];
			while ($prices_array = tep_db_fetch_array($price_query)) {
				$product_prices[$prices_array['customers_group_id']] = $prices_array['customers_group_price'];
			}
			reset($all_groups);
			$price_string = "";
			$sRel         = "";
			$sde          = 0;
			$sTax         = $products['products_tax_class_id'];

			foreach ($all_groups as $sdek => $sdev) {
				if (!in_array((int)$products['products_id'] . ":" . (int)$sdek, $exclude)) {
					if ($sde)
						$price_string .= ", ";
					$price_string .= $sdev . ": " . $currencies->format(isset($product_prices[$sdek]) ? $product_prices[$sdek] : $products['products_price']);
					$sRel         .= (isset($product_prices[$sdek]) ? $product_prices[$sdek] : $products['products_price']) . ',';
					$sde          = 1;
				}
			}
			$select_string .= '<option data-tax="' . $sTax . '" rel="' . substr($sRel, 0, -1) . '" value="' . $products['products_id'] . '" ' . ($products['products_id'] == $selected ? 'selected="selected"' : '') . '>' . $products['products_name'] . ' (' . $price_string . ')</option>\n';
		}
		// EOF 	Separate Pricing Per Customer
	} // end while ($products = tep_db_fetch_array($products_query))

	$select_string .= '</select>';

	return $select_string;
}


function tep_options_name($options_id) {
	global $languages_id;

	$options        = tep_db_query("select products_options_name from " . TABLE_PRODUCTS_OPTIONS . " where products_options_id = '" . (int)$options_id . "' and language_id = '" . (int)$languages_id . "'");
	$options_values = tep_db_fetch_array($options);

	return $options_values['products_options_name'];
}

function tep_values_name($values_id) {
	global $languages_id;

	$values        = tep_db_query("select products_options_values_name from " . TABLE_PRODUCTS_OPTIONS_VALUES . " where products_options_values_id = '" . (int)$values_id . "' and language_id = '" . (int)$languages_id . "'");
	$values_values = tep_db_fetch_array($values);

	return $values_values['products_options_values_name'];
}

function tep_info_image($image, $alt, $width = '', $height = '') {
	//$image=buscar_imagen($image);
	if (tep_not_null($image) && (file_exists(DIR_FS_CATALOG_IMAGES . $image))) {
		$image = tep_image(DIR_WS_CATALOG_IMAGES . $image, $alt, $width, $height);
	} else {
		$image = 'La imagen no existe';
	}

	return $image;
}

function tep_break_string($string, $len, $break_char = '-') {
	$l      = 0;
	$output = '';
	for ($i = 0, $n = strlen((string)$string); $i < $n; $i++) {
		$char = substr((string)$string, $i, 1);
		if ($char !== ' ') {
			$l++;
		} else {
			$l = 0;
		}
		if ($l > $len) {
			$l      = 1;
			$output .= $break_char;
		}
		$output .= $char;
	}

	return $output;
}

function tep_get_country_name($country_id) {
	$country_query = tep_db_query("select countries_name from " . TABLE_COUNTRIES . " where countries_id = '" . (int)$country_id . "'");

	if (!tep_db_num_rows($country_query)) {
		return $country_id;
	} else {
		$country = tep_db_fetch_array($country_query);
		return $country['countries_name'];
	}
}

function tep_get_zone_name($country_id, $zone_id, $default_zone) {
	$zone_query = tep_db_query("select zone_name from " . TABLE_ZONES . " where zone_country_id = '" . (int)$country_id . "' and zone_id = '" . (int)$zone_id . "'");
	if (tep_db_num_rows($zone_query)) {
		$zone = tep_db_fetch_array($zone_query);
		return $zone['zone_name'];
	} else {
		return $default_zone;
	}
}

function tep_not_null($value) {
	if (is_array($value) && count($value) > 0) {
		return true;
	}

	if (is_string($value) && trim($value) !== '') {
		return true;
	}
	return is_int($value) || is_float($value);
}

function tep_browser_detect($component) {
	global $HTTP_USER_AGENT;

	return stristr((string)$HTTP_USER_AGENT, (string)$component);
}

function tep_tax_classes_pull_down($parameters, $selected = '') {
	$select_string = '<select ' . $parameters . '>';
	$classes_query = tep_db_query("select tax_class_id, tax_class_title from " . TABLE_TAX_CLASS . " order by tax_class_title");
	while ($classes = tep_db_fetch_array($classes_query)) {
		$select_string .= '<option value="' . $classes['tax_class_id'] . '"';
		if ($selected == $classes['tax_class_id']) $select_string .= ' SELECTED';
		$select_string .= '>' . $classes['tax_class_title'] . '</option>';
	}
	$select_string .= '</select>';

	return $select_string;
}

function tep_geo_zones_pull_down($parameters, $selected = '', $geoZonesType = null) {
	$select_string = '<select ' . $parameters . '>';
	$zones_query   = tep_db_query("select geo_zone_id, geo_zone_name from " . TABLE_GEO_ZONES . ($geoZonesType !== null ? ' where geo_zones_type_id = "' . (int)$geoZonesType . '"' : '') . " order by geo_zone_name");
	while ($zones = tep_db_fetch_array($zones_query)) {
		$select_string .= '<option value="' . $zones['geo_zone_id'] . '"';
		if ($selected == $zones['geo_zone_id']) {
			$select_string .= ' SELECTED';
		}
		$select_string .= '>' . $zones['geo_zone_name'] . '</option>';
	}

	return $select_string . '</select>';
}

function tep_get_geo_zone_name($geo_zone_id) {
	$zones_query = tep_db_query("select geo_zone_name from " . TABLE_GEO_ZONES . " where geo_zone_id = '" . (int)$geo_zone_id . "'");

	if (!tep_db_num_rows($zones_query)) {
		$geo_zone_name = $geo_zone_id;
	} else {
		$zones         = tep_db_fetch_array($zones_query);
		$geo_zone_name = $zones['geo_zone_name'];
	}

	return $geo_zone_name;
}

function tep_address_format($address_format_id, $address, $html, $boln, $eoln) {
	$address_format_query = tep_db_query("select address_format as format from " . TABLE_ADDRESS_FORMAT . " where address_format_id = '" . (int)$address_format_id . "'");
	$address_format       = tep_db_fetch_array($address_format_query);

	$company = tep_output_string_protected($address['company']);
	$nif = isset($address['nif']) ? tep_output_string_protected($address['nif']) : ''; // Verificar si 'nif' existe en $address

	if (isset($address['firstname']) && tep_not_null($address['firstname'])) {
		$firstname = tep_output_string_protected($address['firstname']);
		$lastname  = tep_output_string_protected($address['lastname']);
	} else if (isset($address['name']) && tep_not_null($address['name'])) {
		$firstname = tep_output_string_protected($address['name']);
		$lastname  = '';
	} else {
		$firstname = '';
		$lastname  = '';
	}
	$street    = tep_output_string_protected($address['street_address']);
	$suburb    = tep_output_string_protected($address['suburb']);
	$city      = tep_output_string_protected($address['city']);
	$state     = tep_output_string_protected($address['state']);
	$telephone = tep_output_string_protected($address['telephone']);
	if (isset($address['country_id']) && tep_not_null($address['country_id'])) {
		$country = tep_get_country_name($address['country_id']);

		if (isset($address['zone_id']) && tep_not_null($address['zone_id'])) {
			$state = tep_get_zone_code($address['country_id'], $address['zone_id'], $state);
		}
	} else if (isset($address['country']) && tep_not_null($address['country'])) {
		$country = tep_output_string_protected($address['country']);
	} else {
		$country = '';
	}
	$postcode = tep_output_string_protected($address['postcode']);
	$zip      = $postcode;

	if ($html) {
		// HTML Mode
		$HR = '<hr />';
		$hr = '<hr />';
		if (($boln == '') && ($eoln == "\n")) { // Values not specified, use rational defaults
			$CR   = '<br />';
			$cr   = '<br />';
			$eoln = $cr;
		} else { // Use values supplied
			$CR = $eoln . $boln;
			$cr = $CR;
		}
	} else {
		// Text Mode
		$CR = $eoln;
		$cr = $CR;
		$HR = '----------------------------------------';
		$hr = '----------------------------------------';
	}

	$statecomma = '';
	$streets    = $street;
	if ($suburb != '') $streets = $street . $cr . $suburb;
	if ($country == '') $country = tep_output_string_protected($address['country']);
	if ($state != '') $statecomma = $state . ', ';

	$fmt = $address_format['format'];
	eval("\$address = \"$fmt\";");

	if ((ACCOUNT_COMPANY == 'true') && (tep_not_null($company))) {
		$address = $company . $cr . $address;
	}

	return $address;
}

////////////////////////////////////////////////////////////////////////////////////////////////
//
// Function    : tep_get_zone_code
//
// Arguments   : country           country code string
//               zone              state/province zone_id
//               def_state         default string if zone==0
//
// Return      : state_prov_code   state/province code
//
// Description : Function to retrieve the state/province code (as in FL for Florida etc)
//
////////////////////////////////////////////////////////////////////////////////////////////////
function tep_get_zone_code($country, $zone, $def_state) {

	$state_prov_query = tep_db_query("select zone_code from " . TABLE_ZONES . " where zone_country_id = '" . (int)$country . "' and zone_id = '" . (int)$zone . "'");

	if (!tep_db_num_rows($state_prov_query)) {
		$state_prov_code = $def_state;
	} else {
		$state_prov_values = tep_db_fetch_array($state_prov_query);
		$state_prov_code   = $state_prov_values['zone_code'];
	}

	return $state_prov_code;
}

function tep_get_uprid($prid, $params) {
	$uprid = $prid;
	if ((is_array($params)) && (!strstr($prid, '{'))) {
		foreach ($params as $option => $value) {
			$uprid = $uprid . '{' . $option . '}' . $value;
		}
	}

	return $uprid;
}

function tep_get_prid($uprid) {
	$pieces = explode('{', $uprid);

	return $pieces[0];
}

function tep_get_languages($bKeyId = false) {
	$languages_query = tep_db_query("select languages_id, name, code, image, directory from " . TABLE_LANGUAGES . " order by sort_order");
	$nCont           = 0;
	while ($languages = tep_db_fetch_array($languages_query)) {
		$languages_array[($bKeyId ? $languages['languages_id'] : $nCont)] = ['id'        => $languages['languages_id'],
																			 'name'      => $languages['name'],
																			 'code'      => $languages['code'],
																			 'image'     => $languages['image'],
																			 'directory' => $languages['directory']];
		$nCont++;
	}

	return $languages_array;
}

function tep_get_category_name($category_id, $language_id) {
	$category_query = tep_db_query("select categories_name from " . TABLE_CATEGORIES_DESCRIPTION . " where categories_id = '" . (int)$category_id . "' and language_id = '" . (int)$language_id . "'");
	$category       = tep_db_fetch_array($category_query);

	if ($category !== false && isset($category['categories_name'])) {
		return $category['categories_name'];
	}

	return '';
}

function tep_get_products_specials($products_id) {
	$category_query = tep_db_query("select specials_new_products_price from specials where products_id = " . (int)$products_id);
	$category       = tep_db_fetch_array($category_query);
	if (isset($category['specials_new_products_price'])) {
		return $category['specials_new_products_price'];
	} else {
		return false;
	}
}

function tep_get_orders_status_name($orders_status_id, $language_id = '') {
	global $languages_id;

	if (!$language_id) {
		$language_id = $languages_id;
	}
	$orders_status_query = tep_db_query("select orders_status_name from " . TABLE_ORDERS_STATUS . " where orders_status_id = '" . (int)$orders_status_id . "' and language_id = '" . (int)$language_id . "'");
	$orders_status       = tep_db_fetch_array($orders_status_query);

	return $orders_status['orders_status_name'];
}

function tep_get_orders_status() {
	global $languages_id;

	$orders_status_array = [];
	$orders_status_query = tep_db_query("select orders_status_id, orders_status_name from " . TABLE_ORDERS_STATUS . " where language_id = '" . (int)$languages_id . "' order by orders_status_id");
	while ($orders_status = tep_db_fetch_array($orders_status_query)) {
		$orders_status_array[] = ['id'   => $orders_status['orders_status_id'],
								  'text' => $orders_status['orders_status_name']];
	}

	return $orders_status_array;
}

function tep_get_products_url($product_id, $language_id = 0) {
	global $languages_id;

	if ($language_id == 0) {
		$language_id = $languages_id;
	}
	$product_query = tep_db_query("select products_url from " . TABLE_PRODUCTS_DESCRIPTION . " where products_id = '" . (int)$product_id . "' and language_id = '" . (int)$language_id . "'");
	$product       = tep_db_fetch_array($product_query);

	if ($product !== false && isset($product['products_url'])) {
		return $product['products_url'];
	} else {
		return '';
	}
}

function tep_get_products_name($product_id, $language_id = 0) {
	global $languages_id;

	if ($language_id == 0) {
		$language_id = $languages_id;
	}
	$product_query = tep_db_query("select products_name from " . TABLE_PRODUCTS_DESCRIPTION . " where products_id = '" . (int)$product_id . "' and language_id = '" . (int)$language_id . "'");
	$product       = tep_db_fetch_array($product_query);

	if ($product !== false && isset($product['products_name'])) {
		return $product['products_name'];
	} else {
		return '';
	}
}

function tep_get_products_model($product_id) {
	$product_query = tep_db_query("select products_model from " . TABLE_PRODUCTS . " where products_id = '" . (int)$product_id . "'");
	$product       = tep_db_fetch_array($product_query);

	if ($product !== false && isset($product['products_model'])) {
		return $product['products_model'];
	} else {
		return '';
	}
}

function getUbicacion($product_id) {
	$product_query = tep_db_query("select products_ubicacion from " . TABLE_PRODUCTS . " where products_id = '" . (int)$product_id . "'");
	$product       = tep_db_fetch_array($product_query);

	if ($product !== false && isset($product['products_ubicacion'])) {
		return $product['products_ubicacion'];
	} else {
		return '';
	}
}

function tep_get_products_description($product_id, $language_id) {
	$product_query = tep_db_query("select products_description from " . TABLE_PRODUCTS_DESCRIPTION . " where products_id = '" . (int)$product_id . "' and language_id = '" . (int)$language_id . "'");
	$product       = tep_db_fetch_array($product_query);

	if ($product !== false && isset($product['products_description'])) {
		return $product['products_description'];
	} else {
		return '';
	}
}

////
// Return the manufacturers URL in the needed language
// TABLES: manufacturers_info
function tep_get_manufacturer_url($manufacturer_id, $language_id) {
	$manufacturer_query = tep_db_query("select manufacturers_url from " . TABLE_MANUFACTURERS_INFO . " where manufacturers_id = '" . (int)$manufacturer_id . "' and languages_id = '" . (int)$language_id . "'");
	$manufacturer       = tep_db_fetch_array($manufacturer_query);

	return $manufacturer['manufacturers_url'];
}

////
// Wrapper for class_exists() function
// This function is not available in all PHP versions so we test it before using it.
function tep_class_exists($class_name) {
	if (function_exists('class_exists')) {
		return class_exists($class_name);
	} else {
		return true;
	}
}

////
// Count how many products exist in a category
// TABLES: products, products_to_categories, categories
function tep_products_in_category_count($category_id, $include_inactive = false) {
	global $countproducts;
	$products_count = 0;
	if (is_object($countproducts)) {
		$products_count += $countproducts->CountProductsInCategory($category_id);
	} else {
		if ($include_inactive == true) {
			$products_query = tep_db_query("select count(*) as total from " . TABLE_PRODUCTS . " p INNER JOIN " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c on (p.products_id = p2c.products_id) where p2c.categories_id = '" . (int)$category_id . "'");
		} else {
			$products_query = tep_db_query("select count(*) as total from " . TABLE_PRODUCTS . " p INNER JOIN " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c on (p.products_id = p2c.products_id) where p.products_status = '1' and p2c.categories_id = '" . (int)$category_id . "'");
		}
		$products       = tep_db_fetch_array($products_query);
		$products_count += $products['total'];
	} // end if/else (is_object($countproducts)

	if (is_object($countproducts)) {
		$child_categories = $countproducts->hasChildCategories($category_id);
		if ($child_categories !== false) {
			foreach ($child_categories as $child_categories_id) {
				$products_count += tep_products_in_category_count($child_categories_id, $include_inactive);
			}
		}
	} else {
		$child_categories_query = tep_db_query("select categories_id from " . TABLE_CATEGORIES . " where parent_id = '" . (int)$category_id . "'");
		if (tep_db_num_rows($child_categories_query)) {
			while ($child_categories = tep_db_fetch_array($child_categories_query)) {
				$products_count += tep_products_in_category_count($child_categories['categories_id'], $include_inactive);
			}
		}
	} // end if/else (is_object($countproducts))

	return $products_count;
}

////
// Count how many subcategories exist in a category
// TABLES: categories
function tep_childs_in_category_count($categories_id) {
	$categories_count = 0;
	global $countproducts;
	if (is_object($countproducts)) {
		$child_categories = $countproducts->hasChildCategories($categories_id);
		if ($child_categories !== false) {
			foreach ($child_categories as $categories_id) {
				$categories_count++;
				$categories_count += tep_childs_in_category_count($categories_id);
			}
			return $categories_count;
		} else {
			return $categories_count;
		}
	} else {
		$categories_query = tep_db_query("select categories_id from " . TABLE_CATEGORIES . " where parent_id = '" . (int)$categories_id . "'");
		while ($categories = tep_db_fetch_array($categories_query)) {
			$categories_count++;
			$categories_count += tep_childs_in_category_count($categories['categories_id']);
		}
		return $categories_count;
	}
}

////
// Returns an array with countries
// TABLES: countries
function tep_get_countries($default = '') {
	$countries_array = [];
	if ($default) {
		$countries_array[] = ['id'   => '',
							  'text' => $default];
	}
	$countries_query = tep_db_query("select countries_id, countries_name from " . TABLE_COUNTRIES . " order by countries_name");
	while ($countries = tep_db_fetch_array($countries_query)) {
		$countries_array[] = ['id'   => $countries['countries_id'],
							  'text' => $countries['countries_name']];
	}

	return $countries_array;
}

////
// return an array with country zones
function tep_get_country_zones($country_id) {
	$zones_array = [];
	$zones_query = tep_db_query("select zone_id, zone_name from " . TABLE_ZONES . " where zone_country_id = '" . (int)$country_id . "' order by zone_name");
	while ($zones = tep_db_fetch_array($zones_query)) {
		$zones_array[] = ['id'   => $zones['zone_id'],
						  'text' => $zones['zone_name']];
	}

	return $zones_array;
}

function tep_prepare_country_zones_pull_down($country_id = '') {
	// preset the width of the drop-down for Netscape
	$pre = '';
	if ((!tep_browser_detect('MSIE')) && (tep_browser_detect('Mozilla/4'))) {
		for ($i = 0; $i < 45; $i++) $pre .= '&nbsp;';
	}

	$zones = tep_get_country_zones($country_id);

	if (count($zones) > 0) {
		$zones_select = [['id' => '', 'text' => PLEASE_SELECT]];
		$zones        = array_merge($zones_select, $zones);
	} else {
		$zones = [['id' => '', 'text' => TYPE_BELOW]];
		// create dummy options for Netscape to preset the height of the drop-down
		if ((!tep_browser_detect('MSIE')) && (tep_browser_detect('Mozilla/4'))) {
			for ($i = 0; $i < 9; $i++) {
				$zones[] = ['id' => '', 'text' => $pre];
			}
		}
	}

	return $zones;
}

////
// Get list of address_format_id's
function tep_get_address_formats() {
	$address_format_query = tep_db_query("select address_format_id from " . TABLE_ADDRESS_FORMAT . " order by address_format_id");
	$address_format_array = [];
	while ($address_format_values = tep_db_fetch_array($address_format_query)) {
		$address_format_array[] = ['id'   => $address_format_values['address_format_id'],
								   'text' => $address_format_values['address_format_id']];
	}
	return $address_format_array;
}

////
// Alias function for Store configuration values in the Administration Tool
function tep_cfg_pull_down_country_list($country_id) {
	global $configurationOption;

	$key = isset($configurationOption) ? $configurationOption['configuration_key'] : 'configuration_value';
	return tep_draw_pull_down_menu($key, tep_get_countries(), $country_id);
}

function tep_cfg_pull_down_zone_list($zone_id) {
	global $configurationOption;

	$key = isset($configurationOption) ? $configurationOption['configuration_key'] : 'configuration_value';
	return tep_draw_pull_down_menu($key, tep_get_country_zones(STORE_COUNTRY), $zone_id);
}

function tep_cfg_pull_down_tax_classes($tax_class_id, $key = '') {
	$name = (($key) ? 'configuration[' . $key . ']' : 'configuration_value');

	$tax_class_array = [['id' => '0', 'text' => TEXT_NONE]];
	$tax_class_query = tep_db_query("select tax_class_id, tax_class_title from " . TABLE_TAX_CLASS . " order by tax_class_title");
	while ($tax_class = tep_db_fetch_array($tax_class_query)) {
		$tax_class_array[] = ['id'   => $tax_class['tax_class_id'],
							  'text' => $tax_class['tax_class_title']];
	}

	return tep_draw_pull_down_menu($name, $tax_class_array, $tax_class_id);
}

////
// Function to read in text area in admin
function tep_cfg_textarea($text) {
	global $configurationOption;

	$key = isset($configurationOption) ? $configurationOption['configuration_key'] : 'configuration_value';

	return tep_draw_textarea_field($key, false, 35, 5, $text);
}

function tep_cfg_get_zone_name($zone_id) {
	$zone_query = tep_db_query("select zone_name from " . TABLE_ZONES . " where zone_id = '" . (int)$zone_id . "'");

	if (!tep_db_num_rows($zone_query)) {
		return $zone_id;
	} else {
		$zone = tep_db_fetch_array($zone_query);
		return $zone['zone_name'];
	}
}

////
// Sets the status of a banner
function tep_set_banner_status($banners_id, $status) {
	if ($status == '1') {
		return tep_db_query("update " . TABLE_BANNERS . " set status = '1', expires_impressions = NULL, expires_date = NULL, date_status_change = NULL where banners_id = '" . $banners_id . "'");
	} else if ($status == '0') {
		return tep_db_query("update " . TABLE_BANNERS . " set status = '0', date_status_change = now() where banners_id = '" . $banners_id . "'");
	} else {
		return -1;
	}
}

function tep_set_product_import_exclude($products_id, $import_exclude) {
	if ($import_exclude == '1') {
		return tep_db_query("update " . TABLE_PRODUCTS . " set products_import_exclude = '1', products_last_modified = now() where products_id = '" . (int)$products_id . "'");
	} else if ($import_exclude == '0') {
		return tep_db_query("update " . TABLE_PRODUCTS . " set products_import_exclude = '0', products_last_modified = now() where products_id = '" . (int)$products_id . "'");
	} else {
		return -1;
	}
}

////
// Sets the status of a product
function tep_set_product_status($products_id, $status) {
	if ($status == '1') {
		return tep_db_query("update " . TABLE_PRODUCTS . " set products_status = '1', products_last_modified = now() where products_id = '" . (int)$products_id . "'");
	} else if ($status == '0') {
		return tep_db_query("update " . TABLE_PRODUCTS . " set products_status = '0', products_last_modified = now() where products_id = '" . (int)$products_id . "'");
	} else if ($status == '2') {
		return tep_db_query("update " . TABLE_PRODUCTS . " set products_status = '2', products_last_modified = now() where products_id = '" . (int)$products_id . "'");
	} else {
		return -1;
	}
}

// Sets the status of a product
function tep_set_product_amazon_status($products_id, $status) {
	if ($status == '1') {
		return tep_db_query("update " . TABLE_PRODUCTS . " set amazon_status = '1', products_last_modified = now() where products_id = '" . (int)$products_id . "'");
	} else if ($status == '0') {
		return tep_db_query("update " . TABLE_PRODUCTS . " set amazon_status = '0', products_last_modified = now() where products_id = '" . (int)$products_id . "'");
	} else {
		return -1;
	}
}

////
// Sets the status of a manufacturer
function tep_set_manufacturer_status($manufacturer_id, $status) {
	if ($status == '1') {
		return tep_db_query("update " . TABLE_MANUFACTURERS . " set manufacturers_status = '1', last_modified = now() where manufacturers_id = '" . (int)$manufacturer_id . "'");
	} else if ($status == '0') {
		return tep_db_query("update " . TABLE_MANUFACTURERS . " set manufacturers_status = '0', last_modified = now() where manufacturers_id = '" . (int)$manufacturer_id . "'");
	} else {
		return -1;
	}
}


////
// Sets the status of a product on special
function tep_set_specials_status($specials_id, $status) {
	if ($status == '1') {
		return tep_db_query("update " . TABLE_SPECIALS . " set status = '1', expires_date = NULL, date_status_change = NULL where specials_id = '" . (int)$specials_id . "'");
	} else if ($status == '0') {
		return tep_db_query("update " . TABLE_SPECIALS . " set status = '0', date_status_change = now() where specials_id = '" . (int)$specials_id . "'");
	} else {
		return -1;
	}
}

////
// Sets timeout for the current script.
// Cant be used in safe mode.
function tep_set_time_limit($limit)
{
	if (!get_cfg_var('safe_mode')) {
		set_time_limit($limit);
	}
}

////
// Alias function for Store configuration values in the Administration Tool
function tep_cfg_select_option($select_array, $key_value, $key = '', $cfg_cache = false)
{
	global $configurationOption;
	$string = '';
	$allowConfigurationOption = isset($configurationOption);

	for ($i = 0, $n = count($select_array); $i < $n; $i++) {
		$name = ((tep_not_null($key)) ? 'configuration[' . $key . ']' : 'configuration_value');

		$name = $allowConfigurationOption ? $configurationOption['configuration_key'] : $name;

		$string .= ($allowConfigurationOption ? '' : '<br>') . '<input type="radio" name="' . $name . '" value="' . $select_array[$i] . '"';

		if ($key_value == $select_array[$i]) {
            $string .= ' CHECKED';
        }

		$string = $allowConfigurationOption ? $string . ' id="' . $configurationOption['configuration_key'] . '_' . $select_array[$i] . '"' : $string;

		$string .= '> ' . ($allowConfigurationOption ? '<label for="' . $configurationOption['configuration_key'] . '_' . $select_array[$i] . '"><span></span>' . $select_array[$i] . '</label>' : $select_array[$i]);
	}

	if ($cfg_cache === true) {
		$string .= tep_draw_hidden_field('cfg_cache', 'true');
	}

	return $string;
}

// Alias to allow filebased caching of configuration parameters
function tep_cfg_cache($key_value)
{
	return tep_draw_input_field('configuration_value', $key_value) .
		tep_draw_hidden_field('cfg_cache', 'true');
}

/////
// Alias function for module configuration keys
function tep_mod_select_option($select_array, $key_name, $key_value)
{
	foreach ($select_array as $key => $value) {
		if (is_int($key)) {
            $key = $value;
        }
		$string .= '<br><input type="radio" name="configuration[' . $key_name . ']" value="' . $key . '"';
		if ($key_value == $key) {
            $string .= ' CHECKED';
        }
		$string .= '> ' . $value;
	}

	return $string;
}

////
// Retreive server information
function tep_get_system_information()
{
	global $_SERVER;

	$db_query = tep_db_query("select now() as datetime");
	$db = tep_db_fetch_array($db_query);

	@[$system, $host, $kernel] = preg_split('/[\s,]+/', @exec('uname -a'), 5);

	$data = [];

	$data['oscommerce'] = ['version' => 'oscDenox v 3.0'];

	$data['system'] = ['date' => date('Y-m-d H:i:s O T'),
		'os' => PHP_OS,
		'kernel' => $kernel,
		'uptime' => @exec('uptime'),
		'http_server' => $_SERVER['SERVER_SOFTWARE']];

	$data['mysql'] = ['version' => tep_db_get_server_info(),
		'date' => $db['datetime']];

	$data['php'] = ['version' => PHP_VERSION,
		'zend' => zend_version(),
		'sapi' => PHP_SAPI,
		'int_size' => defined('PHP_INT_SIZE') ? PHP_INT_SIZE : '',
		'safe_mode' => (int)@ini_get('safe_mode'),
		'open_basedir' => (int)@ini_get('open_basedir'),
		'memory_limit' => @ini_get('memory_limit'),
		'error_reporting' => error_reporting(),
		'display_errors' => (int)@ini_get('display_errors'),
		'allow_url_fopen' => (int)@ini_get('allow_url_fopen'),
		'allow_url_include' => (int)@ini_get('allow_url_include'),
		'file_uploads' => (int)@ini_get('file_uploads'),
		'upload_max_filesize' => @ini_get('upload_max_filesize'),
		'post_max_size' => @ini_get('post_max_size'),
		'disable_functions' => @ini_get('disable_functions'),
		'disable_classes' => @ini_get('disable_classes'),
		'enable_dl' => (int)@ini_get('enable_dl'),
		'magic_quotes_gpc' => (int)@ini_get('magic_quotes_gpc'),
		'register_globals' => (int)@ini_get('register_globals'),
		'filter.default' => @ini_get('filter.default'),
		'zend.ze1_compatibility_mode' => (int)@ini_get('zend.ze1_compatibility_mode'),
		'unicode.semantics' => (int)@ini_get('unicode.semantics'),
		'zend_thread_safty' => (int)function_exists('zend_thread_id'),
		'extensions' => get_loaded_extensions()];

	return $data;
}

function tep_generate_category_path($id, $from = 'category', $categories_array = '', $index = 0) {
	global $languages_id;

	if (!is_array($categories_array)) $categories_array = [];

	if ($from == 'product') {
		$categories_query = tep_db_query("select ptc.categories_id from " . TABLE_PRODUCTS_TO_CATEGORIES . " ptc INNER JOIN categories_description cd on (ptc.categories_id = cd.categories_id) where cd.language_id = 3 and ptc.products_id = '" . (int)$id . "'");
		while ($categories = tep_db_fetch_array($categories_query)) {
			if ($categories['categories_id'] == '0') {
				$categories_array[$index][] = ['id' => '0', 'text' => TEXT_TOP];
			} else {
				$category_query             = tep_db_query("select cd.categories_name, c.parent_id from " . TABLE_CATEGORIES . " c INNER JOIN " . TABLE_CATEGORIES_DESCRIPTION . " cd on (c.categories_id = cd.categories_id) where c.categories_id = '" . (int)$categories['categories_id'] . "' and cd.language_id = '" . (int)$languages_id . "'");
				$category                   = tep_db_fetch_array($category_query);
				$categories_array[$index][] = ['id' => $categories['categories_id'], 'text' => $category['categories_name']];
				if ((tep_not_null($category['parent_id'])) && ($category['parent_id'] != '0')) $categories_array = tep_generate_category_path($category['parent_id'], 'category', $categories_array, $index);
				$categories_array[$index] = array_reverse($categories_array[$index]);
			}
			$index++;
		}
	} else if ($from == 'category') {
		$category_query             = tep_db_query("select cd.categories_name, c.parent_id from " . TABLE_CATEGORIES . " c INNER JOIN " . TABLE_CATEGORIES_DESCRIPTION . " cd on (c.categories_id = cd.categories_id) where c.categories_id = '" . (int)$id . "' and cd.language_id = '" . (int)$languages_id . "'");
		$category                   = tep_db_fetch_array($category_query);
		$categories_array[$index][] = ['id' => $id, 'text' => $category['categories_name']];
		if ((tep_not_null($category['parent_id'])) && ($category['parent_id'] != '0')) $categories_array = tep_generate_category_path($category['parent_id'], 'category', $categories_array, $index);
	}

	return $categories_array;
}

function tep_output_generated_category_path($id, $from = 'category') {
	$calculated_category_path_string = '';
	$calculated_category_path        = tep_generate_category_path($id, $from);
	for ($i = 0, $n = sizeof($calculated_category_path); $i < $n; $i++) {
		for ($j = 0, $k = sizeof($calculated_category_path[$i]); $j < $k; $j++) {
			$calculated_category_path_string .= $calculated_category_path[$i][$j]['text'] . '&nbsp;&gt;&nbsp;';
		}
		$calculated_category_path_string = substr($calculated_category_path_string, 0, -16) . '<br><br>';
	}
	$calculated_category_path_string = substr($calculated_category_path_string, 0, -4);

	if (strlen($calculated_category_path_string) < 1) $calculated_category_path_string = 'Sin relación';

	return $calculated_category_path_string;
}

function tep_get_generated_category_path_ids($id, $from = 'category') {
	$calculated_category_path_string = '';
	$calculated_category_path        = tep_generate_category_path($id, $from);
	for ($i = 0, $n = sizeof($calculated_category_path); $i < $n; $i++) {
		for ($j = 0, $k = sizeof($calculated_category_path[$i]); $j < $k; $j++) {
			$calculated_category_path_string .= $calculated_category_path[$i][$j]['id'] . '_';
		}
		$calculated_category_path_string = substr($calculated_category_path_string, 0, -1) . '<br>';
	}
	$calculated_category_path_string = substr($calculated_category_path_string, 0, -4);

	if (strlen($calculated_category_path_string) < 1) $calculated_category_path_string = TEXT_TOP;

	return $calculated_category_path_string;
}

function tep_remove_category($category_id) {
	$category_image_query = tep_db_query("select categories_image from " . TABLE_CATEGORIES . " where categories_id = '" . (int)$category_id . "'");
	$category_image       = tep_db_fetch_array($category_image_query);

	$duplicate_image_query = tep_db_query("select count(*) as total from " . TABLE_CATEGORIES . " where categories_image = '" . tep_db_input($category_image['categories_image']) . "'");
	$duplicate_image       = tep_db_fetch_array($duplicate_image_query);

	if ($duplicate_image['total'] < 2) {
		if (file_exists(DIR_FS_CATALOG_IMAGES . 'categorias/' . $category_image['categories_image'])) {
			@unlink(DIR_FS_CATALOG_IMAGES . 'categorias/' . $category_image['categories_image']);
		}
	}

	tep_db_query("delete from " . TABLE_CATEGORIES . " where categories_id = '" . (int)$category_id . "'");
	tep_db_query("delete from " . TABLE_CATEGORIES_DESCRIPTION . " where categories_id = '" . (int)$category_id . "'");
	tep_db_query("delete from " . TABLE_PRODUCTS_TO_CATEGORIES . " where categories_id = '" . (int)$category_id . "'");

	if (USE_CACHE == 'true') {
		tep_reset_cache_block('categories');
		tep_reset_cache_block('also_purchased');
		tep_reset_cache_block('xsell_products');
	}
}

function tep_remove_product($product_id) {
	// begin Bundled Products
	global $messageStack, $languages_id;
	tep_db_query("DELETE FROM " . TABLE_PRODUCTS_BUNDLES . " WHERE bundle_id = " . (int)$product_id);
	$bundle_check = tep_db_query('select p.products_model, pd.products_name from ' . TABLE_PRODUCTS . ' p, ' . TABLE_PRODUCTS_DESCRIPTION . ' pd, ' . TABLE_PRODUCTS_BUNDLES . ' pb where p.products_id = pd.products_id and pd.language_id = ' . (int)$languages_id . ' and p.products_id = pb.bundle_id and pb.subproduct_id = ' . (int)$product_id);
	// if product being deleted is contained in any bundles warn the user
	while ($bundle = tep_db_fetch_array($bundle_check)) {
		$messageStack->add_session(WARNING_PRODUCT_IN_BUNDLE . '(' . $bundle['products_model'] . ') ' . $bundle['products_name'], 'warning');
	}
	tep_db_query("DELETE FROM " . TABLE_PRODUCTS_BUNDLES . " WHERE subproduct_id = " . (int)$product_id);
	// end Bundled Products

	// BOF: More Pics 6
	$product_subimage1_query = tep_db_query("select products_subimage1 from " . TABLE_PRODUCTS . " where products_id = '" . tep_db_input($product_id) . "'");
	$product_subimage1       = tep_db_fetch_array($product_subimage1_query);

	$duplicate_subimage1_query = tep_db_query("select count(*) as total from " . TABLE_PRODUCTS . " where products_subimage1 = '" . tep_db_input($product_subimage1['products_subimage1']) . "'");
	$duplicate_subimage1       = tep_db_fetch_array($duplicate_subimage1_query);

	if ($duplicate_subimage1['total'] < 2) {
		if (file_exists(DIR_FS_CATALOG_IMAGES . $product_subimage1['products_subimage1'])) {
			@unlink(DIR_FS_CATALOG_IMAGES . $product_subimage1['products_subimage1']);
		}
	}

	$product_subimage2_query = tep_db_query("select products_subimage2 from " . TABLE_PRODUCTS . " where products_id = '" . tep_db_input($product_id) . "'");
	$product_subimage2       = tep_db_fetch_array($product_subimage2_query);

	$duplicate_subimage2_query = tep_db_query("select count(*) as total from " . TABLE_PRODUCTS . " where products_subimage2 = '" . tep_db_input($product_subimage2['products_subimage2']) . "'");
	$duplicate_subimage2       = tep_db_fetch_array($duplicate_subimage2_query);

	if ($duplicate_subimage2['total'] < 2) {
		if (file_exists(DIR_FS_CATALOG_IMAGES . $product_subimage2['products_subimage2'])) {
			@unlink(DIR_FS_CATALOG_IMAGES . $product_subimage2['products_subimage2']);
		}
	}

	$product_subimage3_query = tep_db_query("select products_subimage3 from " . TABLE_PRODUCTS . " where products_id = '" . tep_db_input($product_id) . "'");
	$product_subimage3       = tep_db_fetch_array($product_subimage3_query);

	$duplicate_subimage3_query = tep_db_query("select count(*) as total from " . TABLE_PRODUCTS . " where products_subimage3 = '" . tep_db_input($product_subimage3['products_subimage3']) . "'");
	$duplicate_subimage3       = tep_db_fetch_array($duplicate_subimage3_query);

	if ($duplicate_subimage3['total'] < 2) {
		if (file_exists(DIR_FS_CATALOG_IMAGES . $product_subimage3['products_subimage3'])) {
			@unlink(DIR_FS_CATALOG_IMAGES . $product_subimage3['products_subimage3']);
		}
	}

	$product_subimage4_query = tep_db_query("select products_subimage4 from " . TABLE_PRODUCTS . " where products_id = '" . tep_db_input($product_id) . "'");
	$product_subimage4       = tep_db_fetch_array($product_subimage4_query);

	$duplicate_subimage4_query = tep_db_query("select count(*) as total from " . TABLE_PRODUCTS . " where products_subimage4 = '" . tep_db_input($product_subimage4['products_subimage4']) . "'");
	$duplicate_subimage4       = tep_db_fetch_array($duplicate_subimage4_query);

	if ($duplicate_subimage4['total'] < 2) {
		if (file_exists(DIR_FS_CATALOG_IMAGES . $product_subimage4['products_subimage4'])) {
			@unlink(DIR_FS_CATALOG_IMAGES . $product_subimage4['products_subimage4']);
		}
	}

	$product_subimage5_query = tep_db_query("select products_subimage5 from " . TABLE_PRODUCTS . " where products_id = '" . tep_db_input($product_id) . "'");
	$product_subimage5       = tep_db_fetch_array($product_subimage5_query);

	$duplicate_subimage5_query = tep_db_query("select count(*) as total from " . TABLE_PRODUCTS . " where products_subimage5 = '" . tep_db_input($product_subimage5['products_subimage5']) . "'");
	$duplicate_subimage5       = tep_db_fetch_array($duplicate_subimage5_query);

	if ($duplicate_subimage5['total'] < 2) {
		if (file_exists(DIR_FS_CATALOG_IMAGES . $product_subimage5['products_subimage5'])) {
			@unlink(DIR_FS_CATALOG_IMAGES . $product_subimage5['products_subimage5']);
		}
	}

	$product_subimage6_query = tep_db_query("select products_subimage6 from " . TABLE_PRODUCTS . " where products_id = '" . tep_db_input($product_id) . "'");
	$product_subimage6       = tep_db_fetch_array($product_subimage6_query);

	$duplicate_subimage6_query = tep_db_query("select count(*) as total from " . TABLE_PRODUCTS . " where products_subimage6 = '" . tep_db_input($product_subimage6['products_subimage6']) . "'");
	$duplicate_subimage6       = tep_db_fetch_array($duplicate_subimage6_query);

	if ($duplicate_subimage6['total'] < 2) {
		if (file_exists(DIR_FS_CATALOG_IMAGES . $product_subimage6['products_subimage6'])) {
			@unlink(DIR_FS_CATALOG_IMAGES . $product_subimage6['products_subimage6']);
		}
	}
	// EOF: More Pics 6
	$product_image_query = tep_db_query("select products_image from " . TABLE_PRODUCTS . " where products_id = '" . (int)$product_id . "'");
	$product_image       = tep_db_fetch_array($product_image_query);

	$duplicate_image_query = tep_db_query("select count(*) as total from " . TABLE_PRODUCTS . " where products_image = '" . tep_db_input($product_image['products_image']) . "'");
	$duplicate_image       = tep_db_fetch_array($duplicate_image_query);

	if ($duplicate_image['total'] < 2) {
		if (file_exists(DIR_FS_CATALOG_IMAGES . $product_image['products_image'])) {
			@unlink(DIR_FS_CATALOG_IMAGES . $product_image['products_image']);
		}
	}

	// Inicio, repuestos
	// Comprobamos si existe imagen si es asi eliminamos
	$aImagenes = glob(getcwd() . '/../images/repuestos/' . $product_id . '-*');

	// Si existe eliminamos
	if (count($aImagenes) > 0) {
		@unlink($aImagenes[0]);
		@unlink($aImagenes[1]);
	}

	// Eleminamos repuestos
	tep_db_query("delete from repuesto where products_id = '" . (int)$product_id . "'");
	tep_db_query("delete from repuesto where products_id_repuesto = '" . (int)$product_id . "'");
	// Fin, repuestos

	tep_db_query("delete from " . TABLE_SPECIALS . " where products_id = '" . (int)$product_id . "'");
	tep_db_query("delete from " . TABLE_PRODUCTS . " where products_id = '" . (int)$product_id . "'");
	tep_db_query("delete from " . TABLE_PRODUCTS_GROUPS . " where products_id = '" . (int)$product_id . "'");
	tep_db_query("delete from " . TABLE_PRODUCTS_PRICE_BREAK . " where products_id = '" . (int)$product_id . "'");
	tep_db_query("delete from " . TABLE_PRODUCTS_TO_DISCOUNT_CATEGORIES . " where products_id = '" . (int)$product_id . "'");
	tep_db_query("delete from " . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . (int)$product_id . "'");
	tep_db_query("delete from " . TABLE_PRODUCTS_DESCRIPTION . " where products_id = '" . (int)$product_id . "'");
	tep_db_query("delete from " . TABLE_PRODUCTS_ATTRIBUTES . " where products_id = '" . (int)$product_id . "'");
	tep_db_query("delete from " . TABLE_CUSTOMERS_BASKET . " where products_id = '" . (int)$product_id . "' or products_id like '" . (int)$product_id . "{%'");
	tep_db_query("delete from " . TABLE_CUSTOMERS_BASKET_ATTRIBUTES . " where products_id = '" . (int)$product_id . "' or products_id like '" . (int)$product_id . "{%'");
	tep_db_query("delete from " . TABLE_PRODUCTS_YEARLY_SALES . " where products_id = '" . (int)$product_id . "'");


	$product_reviews_query = tep_db_query("select reviews_id from " . TABLE_REVIEWS . " where products_id = '" . (int)$product_id . "'");
	while ($product_reviews = tep_db_fetch_array($product_reviews_query)) {
		tep_db_query("delete from " . TABLE_REVIEWS_DESCRIPTION . " where reviews_id = '" . (int)$product_reviews['reviews_id'] . "'");
	}
	tep_db_query("delete from " . TABLE_REVIEWS . " where products_id = '" . (int)$product_id . "'");

	/*
    EBAY. Añadimos el productos a la cola para borrarlo dandole la máxima prioridad.
    @daniel.lucia
    */
	define('EBAY_ROUTE', getcwd() . '/');
	require_once(getcwd() . '/includes/modules/ebay/functions.php');
	ebayQueueAdd((int)$product_id, 10, 'removeProductoEbay');
	/* FIN - EBAY */

	if (USE_CACHE == 'true') {
		tep_reset_cache_block('categories');
		tep_reset_cache_block('also_purchased');
		tep_reset_cache_block('xsell_products');
	}
}

function tep_remove_order($order_id, $restock = false) {
	// Comentado para bundle products
	/*if ($restock == 'on') {
      $order_query = tep_db_query("select products_id, products_quantity from " . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . (int)$order_id . "'");
      while ($order = tep_db_fetch_array($order_query)) {
        tep_db_query("update " . TABLE_PRODUCTS . " set products_quantity = products_quantity + " . $order['products_quantity'] . ", products_ordered = products_ordered - " . $order['products_quantity'] . ", products_status = 1 where products_id = '" . (int)$order['products_id'] . "'");
      }
    }*/

	// begin Bundled Products
	if (!function_exists("restock_bundle")) {
		function restock_bundle($bundle_id, $restock_qty) {
			$bundle_query = tep_db_query('select pb.subproduct_id, pb.subproduct_qty, p.products_bundle from ' . TABLE_PRODUCTS_BUNDLES . ' pb, ' . TABLE_PRODUCTS . ' p where p.products_id = pb.subproduct_id and bundle_id = ' . (int)$bundle_id);
			while ($bundle_info = tep_db_fetch_array($bundle_query)) {
				$qty_restocked = $bundle_info['subproduct_qty'] * $restock_qty;
				if ($bundle_info['products_bundle'] == 'yes') {
					restock_bundle($bundle_info['subproduct_id'], $qty_restocked);
				} else {
					tep_db_query("update " . TABLE_PRODUCTS . " set products_quantity = products_quantity + " . (int)$qty_restocked . ", products_ordered = products_ordered - " . (int)$qty_restocked . " where products_id = " . (int)$bundle_info['subproduct_id']);
				}
			}
			// reduce number of bundle sold
			tep_db_query("update " . TABLE_PRODUCTS . " set products_ordered = products_ordered - " . (int)$restock_qty . " where products_id = " . (int)$bundle_id);
		} // end function restock_bundle
		if ($restock == 'on') {
			$order_query = tep_db_query("select o.products_id, o.products_quantity, p.products_bundle from " . TABLE_ORDERS_PRODUCTS . " o, " . TABLE_PRODUCTS . " p where o.products_id = p.products_id and orders_id = " . (int)$order_id);
			while ($order = tep_db_fetch_array($order_query)) {
				if ($order['products_bundle'] == 'yes') {
					restock_bundle($order['products_id'], $order['products_quantity']);
				} else {
					tep_db_query("update " . TABLE_PRODUCTS . " set products_quantity = products_quantity + " . (int)$order['products_quantity'] . ", products_ordered = products_ordered - " . (int)$order['products_quantity'] . " where products_id = '" . (int)$order['products_id'] . "'");
				}
			}
		}
		// end Bundled Products
	}

	tep_db_query("delete from " . TABLE_ORDERS . " where orders_id = '" . (int)$order_id . "'");
	tep_db_query("delete from " . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . (int)$order_id . "'");
	tep_db_query("delete from " . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . " where orders_id = '" . (int)$order_id . "'");
	tep_db_query("delete from " . TABLE_ORDERS_STATUS_HISTORY . " where orders_id = '" . (int)$order_id . "'");
	tep_db_query("delete from " . TABLE_ORDERS_TOTAL . " where orders_id = '" . (int)$order_id . "'");
	tep_db_query("delete from " . TABLE_CUSTOMERS_POINTS_PENDING . " where orders_id = '" . (int)$order_id . "'");
	$sql = "optimize table " . TABLE_CUSTOMERS_POINTS_PENDING . "";
}


function tep_reset_cache_block($cache_block) {
	global $cache_blocks;

	$pid = '*';
	if ($cache_block == 'xsell_products') {
		$pid = '';
		if (isset($_GET['add_related_product_ID'])) {
			$pid = $_GET['add_related_product_ID'];
		}
		if (!$pid) $pid = '*';
	}

	for ($i = 0, $n = sizeof($cache_blocks); $i < $n; $i++) {
		if ($cache_blocks[$i]['code'] == $cache_block) {
			$glob_pattern = preg_replace('#-language.+$#', '-*', $cache_blocks[$i]['file']);
			foreach (glob(DIR_FS_CACHE . $glob_pattern . '.cache' . $pid) as $cache_file) {
				@unlink($cache_file);
			}
			break;
		}
	}
}

// EOF: XSell

function tep_get_file_permissions($mode) {
	// determine type
	if (($mode & 0xC000) == 0xC000) { // unix domain socket
		$type = 's';
	} else if (($mode & 0x4000) == 0x4000) { // directory
		$type = 'd';
	} else if (($mode & 0xA000) == 0xA000) { // symbolic link
		$type = 'l';
	} else if (($mode & 0x8000) == 0x8000) { // regular file
		$type = '-';
	} else if (($mode & 0x6000) == 0x6000) { //bBlock special file
		$type = 'b';
	} else if (($mode & 0x2000) == 0x2000) { // character special file
		$type = 'c';
	} else if (($mode & 0x1000) == 0x1000) { // named pipe
		$type = 'p';
	} else { // unknown
		$type = '?';
	}

	// determine permissions
	$owner['read']    = ($mode & 00400) ? 'r' : '-';
	$owner['write']   = ($mode & 00200) ? 'w' : '-';
	$owner['execute'] = ($mode & 00100) ? 'x' : '-';
	$group['read']    = ($mode & 00040) ? 'r' : '-';
	$group['write']   = ($mode & 00020) ? 'w' : '-';
	$group['execute'] = ($mode & 00010) ? 'x' : '-';
	$world['read']    = ($mode & 00004) ? 'r' : '-';
	$world['write']   = ($mode & 00002) ? 'w' : '-';
	$world['execute'] = ($mode & 00001) ? 'x' : '-';

	// adjust for SUID, SGID and sticky bit
	if ($mode & 0x800) $owner['execute'] = ($owner['execute'] == 'x') ? 's' : 'S';
	if ($mode & 0x400) $group['execute'] = ($group['execute'] == 'x') ? 's' : 'S';
	if ($mode & 0x200) $world['execute'] = ($world['execute'] == 'x') ? 't' : 'T';

	return $type .
		$owner['read'] . $owner['write'] . $owner['execute'] .
		$group['read'] . $group['write'] . $group['execute'] .
		$world['read'] . $world['write'] . $world['execute'];
}

function tep_remove($source) {
	global $messageStack, $tep_remove_error;

	if (isset($tep_remove_error)) $tep_remove_error = false;

	if (is_dir($source)) {
		$dir = dir($source);
		while ($file = $dir->read()) {
			if (($file != '.') && ($file != '..')) {
				if (is_writeable($source . '/' . $file)) {
					tep_remove($source . '/' . $file);
				} else {
					$messageStack->add(sprintf(ERROR_FILE_NOT_REMOVEABLE, $source . '/' . $file), 'error');
					$tep_remove_error = true;
				}
			}
		}
		$dir->close();

		if (is_writeable($source)) {
			rmdir($source);
		} else {
			$messageStack->add(sprintf(ERROR_DIRECTORY_NOT_REMOVEABLE, $source), 'error');
			$tep_remove_error = true;
		}
	} else {
		if (is_writeable($source)) {
			unlink($source);
		} else {
			$messageStack->add(sprintf(ERROR_FILE_NOT_REMOVEABLE, $source), 'error');
			$tep_remove_error = true;
		}
	}
}

////
// Output the tax percentage with optional padded decimals
function tep_display_tax_value($value, $padding = TAX_DECIMAL_PLACES) {
	if (strpos($value, '.')) {
		$loop = true;
		while ($loop) {
			if (substr($value, -1) == '0') {
				$value = substr($value, 0, -1);
			} else {
				$loop = false;
				if (substr($value, -1) == '.') {
					$value = substr($value, 0, -1);
				}
			}
		}
	}

	if ($padding > 0) {
		if ($decimal_pos = strpos($value, '.')) {
			$decimals = strlen(substr($value, ($decimal_pos + 1)));
			for ($i = $decimals; $i < $padding; $i++) {
				$value .= '0';
			}
		} else {
			$value .= '.';
			for ($i = 0; $i < $padding; $i++) {
				$value .= '0';
			}
		}
	}

	return $value;
}

function tep_mail($to_name, $to_email_address, $email_subject, $email_text, $from_email_name, $from_email_address, $attachFile = false) {
	// Replica la lógica de envío del frontend (includes/functions/general.php).
	// Antes el admin enviaba siempre por sendmail local, lo que dejaba todos los
	// emails atascados en la cola de exim. Ahora respeta STORE_OWNER_EMAIL_ADDRESS_GROUP
	// y, en su defecto, el SMTP global (Sendgrid/Synology) configurado en BD.
	$mail = new PHPMailer(true);

	if (!$mail->validateAddress($to_email_address)) {
		return false;
	}

	$aEmails = defined('STORE_OWNER_EMAIL_ADDRESS_GROUP') ? json_decode(stripslashes(STORE_OWNER_EMAIL_ADDRESS_GROUP), true) : [];

	$bSmtp     = false;
	$sEmail    = '';
	$sHost     = '';
	$sPort     = '';
	$sPassword = '';

	if (is_array($aEmails) && count($aEmails) > 0) {
		foreach ($aEmails as $sUser => $aEmail) {
			$aSecciones = explode(',', $aEmail[2]);
			foreach ($aSecciones as $sSeccion) {
				if (preg_match('/' . $sSeccion . '/i', $_SERVER['SCRIPT_NAME'])) {
					$sEmail    = $sUser;
					$sHost     = $aEmail[0];
					$sPort     = $aEmail[1];
					$sPassword = tools::decrypt($aEmail[3]);
					if ($sEmail != '' && $sHost != '' && $sPort != '' && $sPassword != '')
						$bSmtp = true;
					break;
				}
			}
			if ($bSmtp) break;
		}
	}

	if ($bSmtp || (defined('SMTP_ACTIVE') && SMTP_ACTIVE == 'smtp' && defined('SMTP_HOST') && SMTP_HOST != '' && defined('SMTP_PUERTO') && SMTP_PUERTO != '' && defined('SMTP_PASS') && SMTP_PASS != '')) {
		$mail->IsSMTP();
		$mail->SMTPAuth = true;
		$mail->Host     = ($bSmtp ? $sHost : SMTP_HOST);
		$mail->Port     = ($bSmtp ? $sPort : SMTP_PUERTO);
		$mail->Username = ($bSmtp ? $sEmail : (defined('SMTP_USER') && SMTP_USER != '' ? SMTP_USER : STORE_OWNER_EMAIL_ADDRESS));
		$mail->Password = ($bSmtp ? $sPassword : tools::decrypt(SMTP_PASS));

		if ($mail->Port == 465) {
			$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
		} else if ($mail->Port == 587) {
			$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
		} else {
			$mail->SMTPSecure = '';
		}
		$mail->SMTPDebug = 0;
		$mail->setFrom(($bSmtp ? $sEmail : STORE_OWNER_EMAIL_ADDRESS), $from_email_name);
		$mail->AddReplyTo($from_email_address);
		$mail->FromName = $from_email_name;
	} else {
		$mail->Host = "localhost";
		$mail->setFrom($from_email_address, $from_email_name);
	}

	$mail->CharSet = 'utf-8';
	$mail->IsHTML(true);
	$mail->Subject = $email_subject;
	$mail->AddAddress($to_email_address, $to_name);

	if ($attachFile)
		$mail->AddAttachment($attachFile['tmp_name'], $attachFile['name']);

	$mail->Body    = $email_text;
	$mail->AltBody = htmlentities((string) $mail->Body);

	try {
		$mail->send();
	} catch (Exception $e) {
		// Notificación a soporte cada 6h ante fallo crítico de SMTP
		if ($e->getCode() == $mail::STOP_CRITICAL) {
			$smtpErrorDate             = pharaonix_queryOne('SELECT configuration_value FROM configuration WHERE configuration_key = "SMTP_ERROR_DATE"', true);
			$smtpErrorDateLastHour     = $smtpErrorDate->records['configuration_value'] ?? (new DateTime())->sub(new DateInterval("PT6H"))->format('Y-m-d H:i:s');
			$smtpErrorDateSubtractHour = (new DateTime($smtpErrorDateLastHour))->add(new DateInterval("PT6H"))->format('Y-m-d H:i:s');

			if (date('Y-m-d H:i:s') >= $smtpErrorDateSubtractHour) {
				if ($smtpErrorDate->num_rows == 0) {
					// Inline de checkConfigurationGroupIdFromTitle (sólo existe en frontend)
					$cg = tep_db_fetch_array(tep_db_query("SELECT configuration_group_id FROM configuration_group WHERE configuration_group_title = 'Configurar Emails'"));
					$configGroupId = (int)($cg['configuration_group_id'] ?? 1);
					tep_db_perform('configuration', ['configuration_value' => date('Y-m-d H:i:s'), 'configuration_key' => 'SMTP_ERROR_DATE', 'configuration_group_id' => $configGroupId]);
				} else {
					tep_db_perform('configuration', ['configuration_value' => date('Y-m-d H:i:s')], 'update', 'configuration_key = "SMTP_ERROR_DATE"');
				}

				$mail->Host    = "localhost";
				$mail->CharSet = 'utf-8';
				$mail->IsHTML(true);
				$mail->From     = STORE_OWNER_EMAIL_ADDRESS;
				$mail->FromName = STORE_OWNER;
				$mail->Subject  = "[" . STORE_NAME . "] - Error conexión SMTP (admin)";
				$mail->AddAddress('info@denox.es', 'Denox');
				$mail->Body    = STORE_NAME . ", error SMTP en admin con mensaje: <br><br>" . $e->getMessage();
				$mail->AltBody = htmlentities($mail->Body);
				@$mail->Send();
			}
		}
	}

	return true;
}

function tep_get_tax_class_title($tax_class_id) {
	if ($tax_class_id == '0') {
		return TEXT_NONE;
	} else {
		$classes_query = tep_db_query("select tax_class_title from " . TABLE_TAX_CLASS . " where tax_class_id = '" . (int)$tax_class_id . "'");
		$classes       = tep_db_fetch_array($classes_query);

		return $classes['tax_class_title'];
	}
}

function tep_banner_image_extension() {
	if (function_exists('imagetypes')) {
		if (imagetypes() & IMG_PNG) {
			return 'png';
		} else if (imagetypes() & IMG_JPG) {
			return 'jpg';
		} else if (imagetypes() & IMG_GIF) {
			return 'gif';
		}
	} else if (function_exists('imagecreatefrompng') && function_exists('imagepng')) {
		return 'png';
	} else if (function_exists('imagecreatefromjpeg') && function_exists('imagejpeg')) {
		return 'jpg';
	} else if (function_exists('imagecreatefromgif') && function_exists('imagegif')) {
		return 'gif';
	}

	return false;
}

////
// Wrapper function for round() for php3 compatibility
function tep_round($value, $precision) {
	if (PHP_VERSION < 4) {
		$exp = pow(10, $precision);
		return round((float)$value * $exp) / $exp;
	} else {
		return round((float)$value, $precision);
	}
}

////
// Add tax to a products price
function tep_add_tax($price, $tax, $override = false) {
	if (((DISPLAY_PRICE_WITH_TAX == 'true') || ($override == true)) && ($tax > 0)) {
		return $price + tep_calculate_tax($price, $tax);
	} else {
		return $price;
	}
}

// Calculates Tax rounding the result
function tep_calculate_tax($price, $tax) {
	return $price * $tax / 100;
}

////
// Returns the tax rate for a zone / class
// TABLES: tax_rates, zones_to_geo_zones
function tep_get_tax_rate($class_id, $country_id = -1, $zone_id = -1) {
	global $customer_zone_id, $customer_country_id;

	if (($country_id == -1) && ($zone_id == -1)) {
		if (!tep_session_is_registered('customer_id')) {
			$country_id = STORE_COUNTRY;
			$zone_id    = STORE_ZONE;
		} else {
			$country_id = $customer_country_id;
			$zone_id    = $customer_zone_id;
		}
	}

	$tax_query = tep_db_query("select tax_rate from " . TABLE_TAX_RATES . " tr left join " . TABLE_ZONES_TO_GEO_ZONES . " za ON tr.tax_zone_id = za.geo_zone_id left join " . TABLE_GEO_ZONES . " tz ON tz.geo_zone_id = tr.tax_zone_id WHERE (za.zone_country_id IS NULL OR za.zone_country_id = '0' OR za.zone_country_id = '" . (int)$country_id . "') AND (za.zone_id IS NULL OR za.zone_id = '0' OR za.zone_id = '" . (int)$zone_id . "') AND tr.tax_class_id = '" . (int)$class_id . "' GROUP BY tr.tax_class_id");

	if (tep_db_num_rows($tax_query)) {
		$tax_multiplier = 0;
		while ($tax = tep_db_fetch_array($tax_query)) {
			$tax_multiplier += $tax['tax_rate'];
		}
		return $tax_multiplier;
	} else {
		return 0;
	}
}


////
// Returns the tax rate for a tax class
// TABLES: tax_rates
function tep_get_tax_rate_value($class_id) {
	$tax_query = tep_db_query("SELECT tax_rate
								  FROM tax_rates tr
								  INNER JOIN zones_to_geo_zones za on (tr.tax_zone_id = za.geo_zone_id)
								  WHERE za.zone_country_id= '" . (int)STORE_COUNTRY . "' AND tr.tax_class_id = '" . (int)$class_id . "'
								  GROUP BY tr.tax_class_id");

	if (tep_db_num_rows($tax_query)) {
		$tax = tep_db_fetch_array($tax_query);
		return $tax['tax_rate'];
	}

	return 0;
}


function tep_call_function($function, $parameter, $object = '') {
	if ($object == '') {
		return call_user_func($function, $parameter);
	} else {
		return call_user_func([$object, $function], $parameter);
	}
}

function tep_get_zone_class_title($zone_class_id) {
	if ($zone_class_id == '0') {
		return TEXT_NONE;
	} else {
		$classes_query = tep_db_query("select geo_zone_name from " . TABLE_GEO_ZONES . " where geo_zone_id = '" . (int)$zone_class_id . "'");
		$classes       = tep_db_fetch_array($classes_query);

		return $classes['geo_zone_name'];
	}
}

function tep_cfg_pull_down_zone_classes($zone_class_id, $key = '') {
	$name = (($key) ? 'configuration[' . $key . ']' : 'configuration_value');

	$zone_class_array = [['id' => '0', 'text' => TEXT_NONE]];
	$zone_class_query = tep_db_query("select geo_zone_id, geo_zone_name from " . TABLE_GEO_ZONES . " where geo_zones_type_id = 1 order by geo_zone_name");

	while ($zone_class = tep_db_fetch_array($zone_class_query)) {
		$zone_class_array[] = ['id'   => $zone_class['geo_zone_id'],
							   'text' => $zone_class['geo_zone_name']];
	}

	return tep_draw_pull_down_menu($name, $zone_class_array, $zone_class_id);
}

function tep_cfg_pull_down_order_statuses($order_status_id, $key = '') {
	global $languages_id;

	$name = (($key) ? 'configuration[' . $key . ']' : 'configuration_value');

	$statuses_array = [['id' => '0', 'text' => TEXT_DEFAULT]];
	$statuses_query = tep_db_query("select orders_status_id, orders_status_name from " . TABLE_ORDERS_STATUS . " where language_id = '" . (int)$languages_id . "' order by orders_status_name");
	while ($statuses = tep_db_fetch_array($statuses_query)) {
		$statuses_array[] = ['id'   => $statuses['orders_status_id'],
							 'text' => $statuses['orders_status_name']];
	}

	return tep_draw_pull_down_menu($name, $statuses_array, $order_status_id);
}

function tep_get_order_status_name($order_status_id, $language_id = '') {
	global $languages_id;

	if ($order_status_id < 1) return TEXT_DEFAULT;

	if (!is_numeric($language_id)) $language_id = $languages_id;

	$status_query = tep_db_query("select orders_status_name from " . TABLE_ORDERS_STATUS . " where orders_status_id = '" . (int)$order_status_id . "' and language_id = '" . (int)$language_id . "'");
	$status       = tep_db_fetch_array($status_query);

	return $status['orders_status_name'];
}

////
// Return a random value
function tep_rand($min = null, $max = null) {
	static $seeded;

	if (!isset($seeded)) {
		mt_srand((float)microtime() * 1000000);
		$seeded = true;
	}

	if (isset($min) && isset($max)) {
		if ($min >= $max) {
			return $min;
		} else {
			return mt_rand($min, $max);
		}
	} else {
		return mt_rand();
	}
}

// nl2br() prior PHP 4.2.0 did not convert linefeeds on all OSs (it only converted \n)
function tep_convert_linefeeds($from, $to, $string) {
	if ((PHP_VERSION < "4.0.5") && is_array($from)) {
		return preg_replace('/(' . implode('|', $from) . ')/', $to, $string);
	} else {
		return str_replace($from, $to, $string);
	}
}

function tep_string_to_int($string) {
	return (int)$string;
}

////
// Parse and secure the cPath parameter values
function tep_parse_category_path($cPath) {
	// make sure the category IDs are integers
	$cPath_array = array_map('tep_string_to_int', explode('_', $cPath));

	// make sure no duplicate category IDs exist which could lock the server in a loop
	$tmp_array = [];
	$n         = sizeof($cPath_array);
	for ($i = 0; $i < $n; $i++) {
		if (!in_array($cPath_array[$i], $tmp_array)) {
			$tmp_array[] = $cPath_array[$i];
		}
	}

	return $tmp_array;
}

//BOF QPBPP for SPPC
function qpbpp_insert_update_discount_cats($products_id, $current_discount_categories_id, $new_discount_categories_id, $customers_group_id) {
	if (!tep_not_null($products_id)) {
		return false; // if $products_id is not set stop here
	}
	if ($current_discount_categories_id == $new_discount_categories_id) {
		return true; // if they are the same no update is necessary
	}
	if ($current_discount_categories_id == 0 && $new_discount_categories_id > 0) {
		// insert needed
		tep_db_query("insert into " . TABLE_PRODUCTS_TO_DISCOUNT_CATEGORIES . " (products_id, discount_categories_id, customers_group_id) values ('" . (int)$products_id . "', '" . (int)$new_discount_categories_id . "', '" . (int)$customers_group_id . "')");
		return true;
	}
	if ($current_discount_categories_id > 0 && $new_discount_categories_id == 0) {
		// delete needed
		tep_db_query("delete from " . TABLE_PRODUCTS_TO_DISCOUNT_CATEGORIES . " where products_id = '" . (int)$products_id . "' and customers_group_id = '" . (int)$customers_group_id . "'");
		return true;
	}
	if ($current_discount_categories_id > 0 && ($current_discount_categories_id !== $new_discount_categories_id)) {
		// update needed
		tep_db_query("update " . TABLE_PRODUCTS_TO_DISCOUNT_CATEGORIES . " set discount_categories_id = '" . (int)$new_discount_categories_id . "' where products_id = '" . (int)$products_id . "' and discount_categories_id = '" . (int)$current_discount_categories_id . "' and customers_group_id = '" . (int)$customers_group_id . "'");
		return true;
	}
	return false; // for good measure
}

function sortByQty($a, $b) {
	if ($a['products_qty'] == $b['products_qty']) {
		return 0;
	}
	if ($a['products_qty'] < $b['products_qty']) {
		return -1;
	}
	return 1;
}

//EOF QPBPP for SPPC
//////create a pull down for all payment installed payment methods for Order Editor configuration

// Get list of all payment modules available
function tep_cfg_pull_down_payment_methods() {
	global $language;
	$enabled_payment  = [];
	$module_directory = DIR_FS_CATALOG_MODULES . 'payment/';
	$file_extension   = '.php';

	if ($dir = @dir($module_directory)) {
		while ($file = $dir->read()) {
			if (!is_dir($module_directory . $file)) {
				if (substr($file, strrpos($file, '.')) == $file_extension) {
					$directory_array[] = $file;
				}
			}
		}
		sort($directory_array);
		$dir->close();
	}

	// For each available payment module, check if enabled
	for ($i = 0, $n = sizeof($directory_array); $i < $n; $i++) {
		$file = $directory_array[$i];

		include(DIR_FS_CATALOG_LANGUAGES . $language . '/modules/payment/' . $file);
		include($module_directory . $file);

		$class = substr($file, 0, strrpos($file, '.'));
		if (tep_class_exists($class)) {
			$module = new $class;
			if ($module->check() > 0) {
				// If module enabled create array of titles
				$enabled_payment[] = ['id' => $module->title, 'text' => $module->title];

			}
		}
	}

	$enabled_payment[] = ['id' => 'Other', 'text' => 'Other'];

	//draw the dropdown menu for payment methods and default to the order value
	return tep_draw_pull_down_menu('configuration_value', $enabled_payment, '', '');
}


/////end payment method dropdown

////
// Return a product's special price (returns nothing if there is no offer)
// TABLES: products
function tep_get_products_special_price($product_id) {
	$product_query = tep_db_query("select specials_new_products_price from " . TABLE_SPECIALS . " where products_id = '" . (int)$product_id . "' and status");
	$product       = tep_db_fetch_array($product_query);

	return $product["specials_new_products_price"] ?? null;
}

//---  Beginning of addition: Ultimate HTML Emails  ---//
//This function will look in the catalog/includes/modules/HtmlEmail directory and check which different Layouts exists
//This is done by looking for all different maps. Each map is a layout.
//Finally it will create a pulldown menu that is used in the admin panel

function tep_cfg_pull_down_uhtml_email_layout_list($default_id) {
	$handle      = @opendir(DIR_FS_CATALOG_MODULES . 'UHtmlEmails/');
	$LayoutArray = [];
	$i           = 0;

	while ($file = @readdir($handle)) {
		if ($file != '.' & $file != '..') {
			$LayoutArray[$i]['id']   = $file;
			$LayoutArray[$i]['text'] = $file;
			$i++;
		}
	}

	@closedir($handle);
	sort($LayoutArray);
	return tep_draw_pull_down_menu('configuration_value', $LayoutArray, $default_id);
}

//---  End of addition: Ultimate HTML Emails  ---//

// Function to reset SEO URLs database cache entries
// Ultimate SEO URLs v2.1
function tep_reset_cache_data_seo_urls($action) {
	switch ($action) {
		case 'reset':
			tep_db_query("DELETE FROM cache WHERE cache_name LIKE '%seo_urls%'");
			tep_db_query("UPDATE configuration SET configuration_value='false' WHERE configuration_key='SEO_URLS_CACHE_RESET'");
			break;
		default:
			break;
	}
	# The return value is used to set the value upon viewing
	# It's NOT returining a false to indicate failure!!
	return 'false';
}

// Sets the status of a testimonial
function tep_set_testimonials_status($testimonials_id, $status) {
	if ($status == '1') {
		return tep_db_query("update " . TABLE_TESTIMONIALS . " set status = '1' where testimonials_id = '" . $testimonials_id . "'");
	} else if ($status == '0') {
		return tep_db_query("update " . TABLE_TESTIMONIALS . " set status = '0' where testimonials_id = '" . $testimonials_id . "'");
	} else {
		return -1;
	}
}

//Optional Related Products
function tep_version_readonly($value) {
	$version_text = '<br>Version ' . $value;
	return $version_text;
}

function tep_configuration_update($cID, $configuration_value) {
	$configuration_values_query = tep_db_query("select configuration_value, configuration_title, configuration_description from configuration where configuration_id = '" . (int)$cID . "'");
	$configuration_values       = tep_db_fetch_array($configuration_values_query);
	tep_db_query("insert into " . TABLE_CONFIGURATION_CHANGES . " (change_date,previous_setting,new_setting,change_title,change_description) values (now(),'" . tep_db_input($configuration_values['configuration_value']) . "','" . tep_db_input($configuration_value) . "','" . $configuration_values['configuration_title'] . "','" . tep_db_input($configuration_values['configuration_description']) . "')");
	//	tep_mail(STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, EMAIL_CONFIGURATION_CHANGE_TEXT_SUBJECT, EMAIL_CONFIGURATION_CHANGE_TEXT_BODY, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
}

function tep_module_change($action, $class) {
	tep_db_query("insert into " . TABLE_CONFIGURATION_CHANGES . " (change_date,previous_setting,new_setting,change_title,change_description) values (now(),'','" . $action . "','" . $class . "','')");
	//	tep_mail(STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, EMAIL_CONFIGURATION_CHANGE_TEXT_SUBJECT, EMAIL_CONFIGURATION_CHANGE_TEXT_BODY, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
}

//#### bof customers options
// get inactive customers clearance
function get_inactive_customers_clearance() {
	$icc = [['id' => '1', 'text' => TEXT_NERVER_LOGIN],
			['id' => '2', 'text' => TEXT_NERVER_LOGIN_AND_NOT_SUBSCRI],
			['id' => '3', 'text' => TEXT_DUPLICATED_F_LNAME],
	];
	return $icc;
}

// get customer are duplicated fname and lname

function getAll() {
	$rs     = tep_db_query("SELECT COUNT( * ) AS cnt, GROUP_CONCAT( `customers_id`
							SEPARATOR ';' ) ids, `customers_firstname` , `customers_lastname`
							FROM customers
							GROUP BY `customers_firstname` , `customers_lastname`
							HAVING COUNT( * ) >1 ");
	$result = "";
	$data   = [];
	while ($item = tep_db_fetch_array($rs)) {
		$cus_array = preg_split("/\;/", $item['ids']);
		for ($i = 0; $i < sizeof($cus_array); $i++) {
			$data[] = $cus_array[$i];
		}
	}

	if (sizeof($data) > 0) {
		for ($idex = 0; $idex < sizeof($data) - 1; $idex++) {
			$result .= $data[$idex] . ",";
		}
	}
	$result .= $data[$idex];
	if ($result == '') {
		$result = 0;
	}
	return $result;
}

//#### bof customers options


function tep_get_category_seo_url($categories_id, $language_id = 0) {
	global $languages_id;

	if ($language_id == 0) $language_id = $languages_id;
	$category_query = tep_db_query("select categories_seo_url from " . TABLE_CATEGORIES_DESCRIPTION . " where categories_id = '" . (int)$categories_id . "' and language_id = '" . (int)$language_id . "'");
	$category       = tep_db_fetch_array($category_query);

	return $category['categories_seo_url'];
}


function tep_get_products_seo_url($product_id, $language_id = 0) {
	global $languages_id;

	if ($language_id == 0) $language_id = $languages_id;
	$product_query = tep_db_query("select products_seo_url from " . TABLE_PRODUCTS_DESCRIPTION . " where products_id = '" . (int)$product_id . "' and language_id = '" . (int)$language_id . "'");
	$product       = tep_db_fetch_array($product_query);

	return $product['products_seo_url'];
}

function tep_output_generated_category_path_fs($id, $from = 'category') {
	$calculated_category_path_string = '';
	$calculated_category_path        = tep_generate_category_path($id, $from);
	for ($i = 0, $n = sizeof($calculated_category_path); $i < $n; $i++) {
		$ii = ($n - 1) - $i;
		for ($j = 0, $k = sizeof($calculated_category_path[$ii]); $j < $k; $j++) {
			$jj                         = ($k - 1) - $j;
			$dir_path                   = preg_replace("/[^a-z0-9._]/", "", str_replace(" ", "_", str_replace("%20", "_", strtolower($calculated_category_path[$ii][$jj]['text']))));
			$dir_path                   = str_replace('.', '', $dir_path);
			$calculated_category_string .= $dir_path . '/';
		}
	}
	return $calculated_category_string;
}

// Remove / from text
function tep_noslash_string($string) {
	$search = [chr(92), chr(47)];
	return str_replace($search, '', $string);
}

function bu_gzip($directory, $file_in, $delete_file = false, $level = 6) {
	$in_file  = $directory . $file_in;
	$out_file = $directory . $file_in . '.gz';
	if (!file_exists($in_file) || !is_readable($in_file)) {
		return false;
	}
	if (file_exists($out_file)) {
		return false;
	}
	$fin_file = fopen($in_file, "rb");
	if (!$fout_file = gzopen($out_file, "wb" . $level)) {
		return false;
	}

	while (!feof($fin_file)) {
		$buffer = fread($fin_file, 8192); // 8 kB is maximum value
		gzwrite($fout_file, $buffer, 8192);
	}

	fclose($fin_file);
	gzclose($fout_file);
	if ($delete_file == true) {
		unlink($in_file);
	}
	return true;
}

function cambiarFormatoFecha($fecha) {
	[$anio, $mes, $dia] = explode("-", $fecha);
	return $dia . "-" . $mes . "-" . $anio;
}

function obtener_fabricante($products_id) {
	$sql   = "select products_weight from products where products_id = " . $products_id;
	$act   = tep_db_query($sql);
	$valor = tep_db_fetch_array($act);

	return $valor['products_weight'];
}

function obtener_bi_factura_productos($order_id) {
	$sql = "select value from orders_total where orders_id = '" . $order_id . "' and class = 'ot_subtotal'";
	$act = tep_db_query($sql) or die($sql);
	$valor = tep_db_fetch_array($act);

	return $valor['value'];
}

function obtener_bi_factura_envios($order_id) {
	$sql = "select value from orders_total where orders_id = '" . $order_id . "' and class = 'ot_shipping'";
	$act = tep_db_query($sql) or die($sql);
	$valor = tep_db_fetch_array($act);

	return $valor['value'];
}

function obtener_bi_factura_recargo($order_id) {
	$sql = "select value from orders_total where orders_id = '" . $order_id . "' and class = 'ot_fixed_payment_chg'";
	$act = tep_db_query($sql) or die($sql);
	$valor = tep_db_fetch_array($act);

	return $valor['value'];
}

function obtener_iva_factura_4($order_id) {
	$sql = "select value from orders_total where orders_id = '" . $order_id . "' and class = 'ot_tax' and title = 'IVA 4%'";
	$act = tep_db_query($sql) or die($sql);
	$valor = tep_db_fetch_array($act);

	return $valor['value'];
}

function obtener_iva_factura_10($order_id) {
	$sql = "select value from orders_total where orders_id = '" . $order_id . "' and class = 'ot_tax' and title = 'IVA 10%'";
	$act = tep_db_query($sql) or die($sql);
	$valor = tep_db_fetch_array($act);

	return $valor['value'];
}

function obtener_iva_factura_21($order_id) {
	$sql = "select value from orders_total where orders_id = '" . $order_id . "' and class = 'ot_tax' and title = 'IVA 21%:'";
	$act = tep_db_query($sql) or die($sql);
	$valor = tep_db_fetch_array($act);

	return $valor['value'];
}

function obtener_total_factura($order_id) {
	$sql = "select value from orders_total where orders_id = '" . (int)$order_id . "' and class = 'ot_total'";
	$act = tep_db_query($sql) or die($sql);
	$valor = tep_db_fetch_array($act);

	return $valor['value'];
}

function redondear_dos_decimal_plus($valor) {
	$float_redondeado = round($valor * 100) / 100;
	$float_redondeo   = explode('.', $float_redondeado);
	if (strlen($float_redondeo[1] ?? '') == 1) $float_redondeado = $float_redondeado . '0';
	if (strlen($float_redondeo[1] ?? '') == 0) $float_redondeado = $float_redondeado . '.00';
	$float_redondeado = str_replace('.', ',', $float_redondeado);

	return $float_redondeado;

}

//START Search Engine
/**
 * recursive function to get the product-path (cPath) including the categorie-names, notice a rather strange format
 * is chosen to better support array_merge_recursive
 */
function tep_get_product_path_tree($products_id, $delim = '-', $categories_id = '', $categories_name = '', &$category_item = '') {
	global $languages_id;

	if (!is_array($category_item)) {
		$category_query = tep_db_query("
        select
          p2c.categories_id,
          cd.categories_name
        from
          " . TABLE_PRODUCTS . " p
          LEFT JOIN " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c ON (p.products_id = p2c.products_id)
          LEFT JOIN " . TABLE_CATEGORIES_DESCRIPTION . " cd ON (p2c.categories_id = cd.categories_id and cd.language_id = '" . (int)$languages_id . "')
        where
          p.products_id = '" . (int)$products_id . "' and p.products_status = '1' limit 1");
		if (tep_db_num_rows($category_query)) {
			$category      = tep_db_fetch_array($category_query);
			$category_item = [(int)$products_id];
			tep_get_product_path_tree($products_id, $delim, $category['categories_id'], $category['categories_name'], $category_item);
		}
	} else {
		$category_query = tep_db_query("
        select
          c.parent_id,
          cd.categories_name
        from
          " . TABLE_CATEGORIES . " c
          LEFT JOIN " . TABLE_CATEGORIES_DESCRIPTION . " cd ON (c.parent_id = cd.categories_id and cd.language_id = '" . (int)$languages_id . "')
        where
          c.categories_id = '" . (int)$categories_id . "' limit 1");
		if (tep_db_num_rows($category_query)) {
			$category = tep_db_fetch_array($category_query);
			if ($category['parent_id'] == 0) {
				//$category_tree_array = array_reverse($category_tree_array);
				$category_child = $category_item;
				$category_item  = [$delim . $categories_id . $delim . $categories_name => $category_child];
				return true;
			} else if ($category['parent_id'] != $categories_id) {
				$category_child = $category_item;
				$category_item  = [$delim . $categories_id . $delim . $categories_name => $category_child];
				tep_get_product_path_tree($products_id, $delim, $category['parent_id'], $category['categories_name'], $category_item);
			}
		}
	}

	return $category_item;
}

function tep_getTextFromTree(&$tree) {
	$text = [];
	if (is_array($tree)) {
		foreach ($tree as $key => &$value) {
			$matches = [];
			if (preg_match('/c([0-9]+)t(.*)/', $key, $matches)) {
				$text[] = $matches[2];
			} else {
				$text = array_merge($text, tep_getTextFromTree($value));
			}
		}
	}
	return $text;
}

function tep_array_unique_recursive(&$tree) {
	if (is_array($tree)) {
		foreach ($tree as $key => &$value) {
			if (is_array($value)) {
				tep_array_unique_recursive($value);
			}
		}
		$tree = array_unique($tree);
	}
}

function tep_wrap_style($content, $style) {
	return '<span style="' . $style . '">' . $content . '</span>';
}

function tep_sql_pretty_print($query) {
	$query = strtolower(trim($query));
	$query = preg_replace('/[\r\n\t]+/', ' ', $query);
	$query = preg_replace('/[ ]{2,}/', ' ', $query);

	$results = [];
	if (0 != preg_match("/(select)(.*)(from)/", $query, $results)) {
		$part  = preg_replace('/,[ ]*/', ',' . "\n  ", $results[2]);
		$query = preg_replace('/' . $results[2] . '/', $part, $query);
	}

	$query = preg_replace('/select[ ]*/', tep_wrap_style('SELECT', 'font-weight:bold; font-size:larger;') . "\n" . '  ', $query);
	$query = preg_replace('/from[ ]*/', "\n" . tep_wrap_style('FROM', 'font-weight:bold; font-size:larger;') . "\n" . '  ', $query);
	$query = preg_replace('/where[ ]*/', "\n" . tep_wrap_style('WHERE', 'font-weight:bold; font-size:larger;') . "\n" . '  ', $query);
	$query = preg_replace('/([\t ]+)(and)([\t ]+)/', "\n" . '  ' . tep_wrap_style('AND', 'font-weight:bold; font-size:larger;') . ' ', $query);
	$query = preg_replace('/([\t ]+)(or)([\t ]+)/', "\n" . '  ' . tep_wrap_style('OR', 'font-weight:bold; font-size:larger;') . ' ', $query);

	$query = preg_replace('/left/', "\n" . '  ' . tep_wrap_style('LEFT', 'font-weight:bold; font-size:larger;'), $query);
	$query = preg_replace('/right/', "\n" . '  ' . tep_wrap_style('RIGHT', 'font-weight:bold; font-size:larger;'), $query);
	$query = preg_replace('/join/', tep_wrap_style('JOIN', 'font-weight:bold; font-size:larger;'), $query);
	$query = preg_replace('/([\t ]+)(on)([\t ]+)/', ' ' . tep_wrap_style('ON', 'font-weight:bold; font-size:larger;') . ' ', $query);

	$query = preg_replace('/group by/', "\n" . tep_wrap_style('GROUP BY', 'font-weight:bold; font-size:larger;') . "\n" . '  ', $query);
	$query = preg_replace('/order by/', "\n" . tep_wrap_style('ORDER BY', 'font-weight:bold; font-size:larger;') . "\n" . '  ', $query);

	return '<span style="background-color: #ffffff; color: #000000;"><pre>' . $query . '</pre></span>';
}

function tep_getMicrotime() {
	[$usec, $sec] = explode(" ", microtime());
	return (float)$usec + (float)$sec;
}

function tep_getParsetime($startTime) {
	$endTime = tep_getMicrotime();
	return number_format($endTime - $startTime, 4, ',', '') . 's';
}

function tep_cfg_pull_down_multiple_product_options($products_options_id, $key = '') {
	global $languages_id;
	$name  = 'configuration_value[]';
	$query = tep_db_query("select products_options_id, products_options_name from " . TABLE_PRODUCTS_OPTIONS . " where language_id = '" . (int)$languages_id . "' order by products_options_name");
	while ($row = tep_db_fetch_array($query)) {
		$options[] = ['id' => $row['products_options_id'], 'text' => $row['products_options_name']];
	}
	return tep_draw_pull_down_menu($name, $options, $products_options_id, "multiple");
}

function tep_get_multiple_product_options_names($values, $language_id = '') {
	global $languages_id;
	$names   = "";
	$options = unserialize($values);
	if (is_array($options) && 0 != count($options)) {
		if (!is_numeric($language_id)) {
			$language_id = $languages_id;
		}
		$query = tep_db_query("select products_options_name from " . TABLE_PRODUCTS_OPTIONS . " where products_options_id IN (" . implode($options, ',') . ") and language_id = '" . (int)$language_id . "'");
		while ($row = tep_db_fetch_array($query)) {
			$names .= $row['products_options_name'] . ', ';
		}
	}
	return trim($names, ', ');
}

function tep_cfg_pull_down_multiple_product_extra_fields($products_extra_fields_id, $key = '') {
	global $languages_id;
	if (defined(TABLE_PRODUCTS_EXTRA_FIELDS)) {
		$name  = 'configuration_value[]';
		$query = tep_db_query("select products_extra_fields_id, products_extra_fields_name from " . TABLE_PRODUCTS_EXTRA_FIELDS . " where language_id = '" . (int)$languages_id . "' order by products_extra_fields_name");
		while ($row = tep_db_fetch_array($query)) {
			$options[] = ['id' => $row['products_extra_fields_id'], 'text' => $row['products_extra_fields_name']];
		}
		return tep_draw_pull_down_menu($name, $options, $products_extra_fields_id, "multiple");
	}
}

function tep_get_multiple_product_extra_fields_names($values, $language_id = '') {
	global $languages_id;
	$names = "";
	if (defined(TABLE_PRODUCTS_EXTRA_FIELDS)) {
		$options = unserialize($values);
		if (is_array($options) && 0 != count($options)) {
			if (!is_numeric($language_id)) {
				$language_id = $languages_id;
			}
			$query = tep_db_query("select products_extra_fields_name from " . TABLE_PRODUCTS_EXTRA_FIELDS . " where products_extra_fields_id IN (" . implode($options, ',') . ") and language_id = '" . (int)$language_id . "'");
			while ($row = tep_db_fetch_array($query)) {
				$names .= $row['products_extra_fields_name'] . ', ';
			}
		}
	}
	return trim($names, ', ');
}

// Return the manufacturers NAME
// TABLES: manufacturers
function tep_get_manufacturer_name($manufacturer_id) {
	$manufacturer_query = tep_db_query("select manufacturers_name from " . TABLE_MANUFACTURERS . " where manufacturers_id = '" . (int)$manufacturer_id . "'");
	$manufacturer       = tep_db_fetch_array($manufacturer_query);

	return $manufacturer['manufacturers_name'];
}


function get_image_extension($filename, $include_dot = true, $shorter_extensions = true) {
	$image_info = @getimagesize($filename);
	if (!$image_info || empty($image_info[2])) {
		return false;
	}

	if (!function_exists('image_type_to_extension')) {
		/**
		 * Given an image filename, get the file extension.
		 *
		 * @param $imagetype
		 *   One of the IMAGETYPE_XXX constants.
		 * @param $include_dot
		 *   Whether to prepend a dot to the extension or not. Default to TRUE.
		 * @param $shorter_extensions
		 *   Whether to use a shorter extension or not. Default to TRUE.
		 *
		 * @return
		 *   A string with the extension corresponding to the given image type, or
		 *   FALSE on failure.
		 */
		function image_type_to_extensiona($imagetype, $include_dot = true) {
			// Note we do not use the IMAGETYPE_XXX constants as these will not be
			// defined if GD is not enabled.
			$extensions = [
				1  => 'gif',
				2  => 'jpeg',
				3  => 'png',
				4  => 'swf',
				5  => 'psd',
				6  => 'bmp',
				7  => 'tiff',
				8  => 'tiff',
				9  => 'jpc',
				10 => 'jp2',
				11 => 'jpf',
				12 => 'jb2',
				13 => 'swc',
				14 => 'aiff',
				15 => 'wbmp',
				16 => 'xbm',
			];

			// We are expecting an integer between 1 and 16.
			$imagetype = (int)$imagetype;
			if (!$imagetype || !isset($extensions[$imagetype])) {
				return false;
			}

			return ($include_dot ? '.' : '') . $extensions[$imagetype];
		}
	}

	$extension = image_type_to_extension($image_info[2], $include_dot);
	if (!$extension) {
		return false;
	}

	if ($shorter_extensions) {
		$replacements = [
			'jpeg' => 'jpg',
			'tiff' => 'tif',
		];
		$extension    = strtr($extension, $replacements);
	}
	return $extension;
}


function getFileExtension($fileName) {
	$parts = explode(".", $fileName);
	return $parts[count($parts) - 1];
}

function nombre_imagen($file_name) {
	$find    = ["á", "é", "í", "ó", "ú", " ", '.', ',', "ñ", "Á", "É", "Í", "Ó", "Ú"];
	$replace = ["a", "e", "i", "o", "u", "", "-", "-", "n", "a", "e", "i", "o", "u"];

	return str_ireplace($find, $replace, preg_replace('/[^\w ]/', '', $file_name));
}

function tep_get_parent_category($current_category_id = '') {
	global $cPath_array;
	$current_category_query = tep_db_query("select parent_id from " . TABLE_CATEGORIES . " where categories_id = '" . (int)$current_category_id . "'");
	$current_category       = tep_db_fetch_array($current_category_query);
	if ($current_category['parent_id'] == 0) {
		return $current_category_id;
	} else {
		$current_category_query_2 = tep_db_query("select parent_id from " . TABLE_CATEGORIES . " where categories_id = '" . (int)$current_category['parent_id'] . "'");
		$current_category_2       = tep_db_fetch_array($current_category_query_2);
		if ($current_category_2['parent_id'] == 0) {
			return $current_category['parent_id'];
		} else {
			$current_category_query_3 = tep_db_query("select parent_id from " . TABLE_CATEGORIES . " where categories_id = '" . (int)$current_category2['parent_id'] . "'");
			$current_category_3       = tep_db_fetch_array($current_category_query_3);
			if ($current_category_3['parent_id'] == 0) {
				return $current_category_2['parent_id'];
			} else {
				$current_category_query_4 = tep_db_query("select parent_id from " . TABLE_CATEGORIES . " where categories_id = '" . (int)$current_category3['parent_id'] . "'");
				$current_category_4       = tep_db_fetch_array($current_category_query_3);
				if ($current_category_4['parent_id'] == 0) {
					return $current_category_3['parent_id'];
				} else {
					return $current_category_4['parent_id'];
				}
			}
		}
	}
}

//Comprobar si la imagen existe o no
function buscar_imagen($ruta) {

	if (!file_exists($ruta)) {
		$ruta = 'productos/' . $ruta;
	}
	return $ruta;
}

function tep_get_proveedor($id) {
	$proveedor_query = tep_db_query("select proveedores_id from " . TABLE_PRODUCTS . " where products_id = '" . (int)$id . "'");
	$proveedor       = tep_db_fetch_array($proveedor_query);

	return $proveedor['proveedores_id'];
}

function tep_get_proveedor_nombre($id) {
	$proveedor_query = tep_db_query("select proveedores_nombre from proveedores where proveedores_id = '" . (int)$id . "'");
	$proveedor       = tep_db_fetch_array($proveedor_query);

	return $proveedor['proveedores_nombre'];
}

function tep_customers_lname($customers_id) {
	$customers        = tep_db_query("select customers_lastname from " . TABLE_CUSTOMERS . " where customers_id = '" . (int)$customers_id . "'");
	$customers_values = tep_db_fetch_array($customers);

	return $customers_values['customers_lastname'];
}

function tep_customers_join_date($customers_id) {
	$customers        = tep_db_query("select customers_info_date_account_created from " . TABLE_CUSTOMERS_INFO . " where customers_info_id = '" . (int)$customers_id . "'");
	$customers_values = tep_db_fetch_array($customers);

	return $customers_values['customers_info_date_account_created'];
}

function extraer_id_video($url) {
	if (is_numeric($url)) {
		return $url;
	} else {
		$pos  = strpos($url, 'youtube');
		$pos2 = strpos($url, 'vimeo');
		if ($pos !== false) {
			parse_str(parse_url($url, PHP_URL_QUERY));
			$key = !empty($v) ? $v : $url;
			return $key;
		} else if ($pos2 !== false) {
			$salida = str_replace('vimeo.com/', '', strstr($url, "vimeo.com/"));
			return $salida;
		} else {
			return $url;
		}
	}
}

function tep_set_category_status($categories_id, $status) {
	if ($status == '1') {
		tep_db_query("update " . TABLE_CATEGORIES . " set categories_status = '1', last_modified = now() where categories_id = '" . (int)$categories_id . "'");
	} else if ($status == '0') {
		tep_db_query("update " . TABLE_CATEGORIES . " set categories_status = '0', last_modified = now() where categories_id = '" . (int)$categories_id . "'");
	} else {
		return -1;
	}

	buscar_subcategoria($categories_id, $status);

}

function tep_set_category_status_recursive($categories_id, $status) {
	tep_db_query("update " . TABLE_CATEGORIES . " set categories_status = '" . $status . "', last_modified = now() where categories_id = '" . (int)$categories_id . "'");

	$aAllCategory = getAllCategoryArray_();
	$categorias   = getRecursiveIdCategories_($aAllCategory, $categories_id) . ',' . $categories_id;

	$products_query = tep_db_query('SELECT products_id FROM products_to_categories where categories_id IN (' . $categorias . ')');
	if (tep_db_num_rows($products_query) > 0) {
		$products = [];
		while ($product = tep_db_fetch_array($products_query)) {
			$products[] = (int)$product['products_id'];
		}
		tep_db_query('UPDATE products set products_status = ' . (int)$status . ' WHERE products_id IN (' . implode(',', $products) . ')');
	}
	return true;
}

function getAllCategoryArray_() {
	// Variables
	global $languages_id;
	$aReturn = [];
	$aRows   = [];
	$nAnt    = -1;

	$aDatos = tep_db_query('select c.categories_id, cd.categories_name, c.parent_id, c.categories_image
  							 from categories c
  							 inner join categories_description cd on(c.categories_id = cd.categories_id)
  							 where cd.language_id = "' . (int)$languages_id . '" and c.categories_status = 1
  							 order by sort_order, cd.categories_name');

	while ($aDato = tep_db_fetch_array($aDatos))
		$aRows[] = $aDato;

	// Ordenamos por el padre
	foreach ($aRows as $aRow)
		$aReturn[$aRow['parent_id']][] = $aRow;

	return $aReturn;
}

function getRecursiveIdCategories_($aCategoria, $nIdSearch) {
	$sReturn = '';

	if (array_key_exists($nIdSearch, $aCategoria)) {
		foreach ($aCategoria[$nIdSearch] as $aAux) {
			$sReturn .= getRecursiveIdCategories_($aCategoria, $aAux['categories_id']) . ', ';
		}
	}

	return $sReturn . $nIdSearch;
}

function buscar_subcategoria($parent_id, $status) {
	$categories_query = tep_db_query("select categories_id from categories where parent_id = '" . (int)$parent_id . "'");
	$aux              = 0;

	while ($categories = tep_db_fetch_array($categories_query)) {
		$aux           = 1;
		$categories_id = $categories['categories_id'];
		if ($status == '1') {
			tep_db_query("update " . TABLE_CATEGORIES . " set categories_status = '1', last_modified = now() where categories_id = '" . (int)$categories_id . "'");
		} else {
			tep_db_query("update " . TABLE_CATEGORIES . " set categories_status = '0', last_modified = now() where categories_id = '" . (int)$categories_id . "'");
		}
		$products_query = tep_db_query("select products_id from products_to_categories where categories_id = '" . (int)$categories_id . "'");
		if (tep_db_num_rows($products_query)) {
			while ($products = tep_db_fetch_array($products_query)) {
				if ($status == '1') {
					tep_db_query("update " . TABLE_PRODUCTS . " set products_status = '1', products_last_modified = now() where products_id = '" . (int)$products['products_id'] . "'");
				} else {
					tep_db_query("update " . TABLE_PRODUCTS . " set products_status = '0', products_last_modified = now() where products_id = '" . (int)$products['products_id'] . "'");
				}
			}
		}
		if (tep_db_num_rows($categories_query)) {
			buscar_subcategoria((int)$categories_id, $status);
		}
	}

	if ($aux == 0) {
		$products_query = tep_db_query("select products_id from products_to_categories where categories_id = '" . (int)$parent_id . "'");
		if (tep_db_num_rows($products_query)) {
			while ($products = tep_db_fetch_array($products_query)) {
				if ($status == '1') {
					tep_db_query("update " . TABLE_PRODUCTS . " set products_status = '1', products_last_modified = now() where products_id = '" . (int)$products['products_id'] . "'");
				} else {
					tep_db_query("update " . TABLE_PRODUCTS . " set products_status = '0', products_last_modified = now() where products_id = '" . (int)$products['products_id'] . "'");
				}
			}
		}
	}
}

function tep_add_base_ref($string) {
	$i      = 0;
	$output = '';
	$n      = strlen($string);
	for ($i = 0; $i < $n; $i++) {
		$char  = substr($string, $i, 1);
		$char5 = substr($string, $i, 5);
		if ($char5 == 'src="') {
			$output .= 'src="' . HTTP_SERVER;
			$i      = $i + 4;
		} else {
			$output .= $char;
		}
	}
	return $output;
}

//BEGIN NEXT AND PREVIOUS ORDERS DISPLAY IN ADMIN

function get_order_id($orderid, $mode = 'next') {
	if ($mode == 'prev')
		$op = '<';
	else if ($mode == 'next')
		$op = '>';
	if ($op == '<' or $op == '>')
		$nextprev_resource = tep_db_query("select orders_id from " . TABLE_ORDERS . " where orders_id $op '" . (int)$orderid . "' order by orders_id");
	if ($mode == 'prev') {
		while ($nextprev_values = tep_db_fetch_array($nextprev_resource)) {
			$nextprev_value = $nextprev_values;
		}
	} else if ($mode == 'next')
		$nextprev_value = tep_db_fetch_array($nextprev_resource);
	if (!empty($nextprev_value['orders_id']))  // RLJ - added quoted values - PHP complains about unknown constants.
		return $nextprev_value['orders_id']; // RLJ - added quoted values - PHP complains about unknown constants.
	else
		return false;
}

//END NEXT AND PREVIOUS ORDERS DISPLAY IN ADMIN
function tep_address_label($customers_id, $address_id = 1, $html = false, $boln = '', $eoln = "\n") {
	if (is_array($address_id) && !empty($address_id)) {
		return tep_address_format($address_id['address_format_id'], $address_id, $html, $boln, $eoln);
	}

	$address_query = tep_db_query("select entry_firstname as firstname, entry_lastname as lastname, entry_company as company, entry_street_address as street_address, entry_telephone as telephone, entry_suburb as suburb, entry_city as city, entry_postcode as postcode, entry_state as state, entry_zone_id as zone_id, entry_country_id as country_id, entry_nif as nif from " . TABLE_ADDRESS_BOOK . " where customers_id = '" . (int)$customers_id . "' and address_book_id = '" . (int)$address_id . "'");
	$address       = tep_db_fetch_array($address_query);

	$format_id = tep_get_address_format_id($address['country_id']);

	return tep_address_format($format_id, $address, $html, $boln, $eoln);
}

function tep_get_address_format_id($country_id) {
	$address_format_query = tep_db_query("select address_format_id as format_id from " . TABLE_COUNTRIES . " where countries_id = '" . (int)$country_id . "'");
	if (tep_db_num_rows($address_format_query)) {
		$address_format = tep_db_fetch_array($address_format_query);
		return $address_format['format_id'];
	} else {
		return '1';
	}
}

include('qtpro_functions.php');


/**
 * Función que obtiene todas las combinaciones posibles de una frase dividida en palabras
 *
 * - @param string Cadena separada por espacios en la que se obtendrán las combinaciones
 *
 * @return array
 */
function combinations($sString) {
	// Variables
	$aStrings = null;
	$aArray   = [];
	$aReturn  = [];

	// Dividimos las cadenas por el espacio
	$aStrings = explode(' ', (string)$sString);

	// Eliminamos repetidos
	$aStrings = array_unique($aStrings);

	// Función showCombo
	if (!function_exists('showCombo')) {
		// Función showCombo
		function showCombo($aExcludes, $aStrings, &$aArray) {
			// Array que retornaremos
			$aReturn = [];

			// Recorremos las cadenas
			foreach ($aStrings as $aString) {
				// Si no está en el array excluido
				if (!in_array($aString, $aExcludes)) {
					// Obtenemos la cadena actual
					$aTemp   = $aExcludes;
					$aTemp[] = $aString;

					// Guardamos la combinación
					$aArray[] = $aTemp;

					// Buscamos más combinaciones
					$aComb = showCombo($aTemp, $aStrings, $aArray);

					// Si hemos obtenido una combinación
					if (count($aComb) > 0) {
						$aReturn[] = $aComb;
					}
				}
			}

			return $aReturn;
		}
	}

	// Obtenemos el combo de cadenas
	showCombo([], $aStrings, $aArray);

	// Quitamos las menores de x caracteres
	foreach ($aArray as $aAux)
		if (count($aAux) == count($aStrings)) {
			$aReturn[] = $aAux;
		}

	return $aReturn;
}

/**
 * Obtenemos si la peticion es AJAX
 */
function isAjax() {
	return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Obtenemos la url actual donde nos enconramos
 *
 * @return string $sUrl
 */
function getCurrentUrl() {
	// Variables
	$sUrl = 'http';

	if ($_SERVER["HTTPS"] == "on") {
		$sUrl .= "s";
	}

	$sUrl .= "://";

	if ($_SERVER["SERVER_PORT"] != "80" && $_SERVER["SERVER_PORT"] != "443") {
		$sUrl .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
	} else {
		$sUrl .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
	}

	return $sUrl;
}

function cambiaf_a_normal($fecha) {
	preg_match("/([0-9]{2,4})-([0-9]{1,2})-([0-9]{1,2})/", $fecha, $mifecha);
	$lafecha = $mifecha[3] . "/" . $mifecha[2] . "/" . $mifecha[1];
	return $lafecha;
}


function getFabricanteName($id) {

	$id_fabricante       = tep_db_query("select manufacturers_id from products where products_id='" . $id . "'");
	$id_fabricante_value = tep_db_fetch_array($id_fabricante);

	$nombre_fabricante       = tep_db_query("select manufacturers_name from manufacturers where manufacturers_id='" . $id_fabricante_value['manufacturers_id'] . "'");
	$nombre_fabricante_value = tep_db_fetch_array($nombre_fabricante);

	return $nombre_fabricante_value['manufacturers_name'];

}

function getFechaRegistroCliente($email_cliente, $id) {
	$mail   = $email_cliente;
	$query  = 'select customers_id from ' . TABLE_CUSTOMERS . ' WHERE customers_email_address="' . $mail . '"';
	$result = tep_db_query($query);
	while ($registro = tep_db_fetch_array($result)) {
		$id = $registro['customers_id'];
	}
	if ($id != '') {
		$query  = 'select * from ' . TABLE_CUSTOMERS_INFO . ' WHERE customers_info_id=' . $id;
		$result = tep_db_query($query);
		while ($registro = tep_db_fetch_array($result)) {
			$valor = '<strong>' . cambiaf_a_normal($registro['customers_info_date_account_created']) . '</strong>';
		}

		$query    = 'select * from ' . TABLE_ORDERS . ' WHERE customers_id=' . $id;
		$result   = tep_db_query($query);
		$num_rows = tep_db_num_rows($result);
		$query    = 'select * from ' . TABLE_ORDERS . ' WHERE customers_id=' . $id . ' LIMIT 1';
		$result   = tep_db_query($query);
		while ($registro = tep_db_fetch_array($result)) {
			$valor .= ' <a href="orders.php?cID=' . $id . '">Ultimos pedidos (' . $num_rows . ' en total)</a>';
		}
	} else {
		$valor .= '<font color="red"><b>El cliente ha borrado su cuenta</b></font>';
	}
	return $valor;
}


function getImagenBanner($sId) {
	$sDirBanners = DIR_FS_CATALOG_IMAGES . 'banners/';
	if (!is_dir($sDirBanners)) return '';

	$aImagenes = scandir($sDirBanners);
	$aIdiomas  = tep_get_languages();

	foreach ($aIdiomas as $aIdioma) {
		$matches = preg_grep('/^' . $sId . '_' . $aIdioma['id'] . '/i', $aImagenes);

		if (count($matches) > 0) {
			$matches = array_values($matches);
			return tep_image(DIR_WS_CATALOG_IMAGES . 'banners/' . $matches[0], ICON_PREVIEW, SMALL_IMAGE_WIDTH, SMALL_IMAGE_HEIGHT);
		}
	}

	return '';
}

function getImagenBannerSrc($sId) {
	$sDirBanners = DIR_FS_CATALOG_IMAGES . 'banners/';
	if (!is_dir($sDirBanners)) return '';

	$aImagenes = scandir($sDirBanners);
	$aIdiomas  = tep_get_languages();

	foreach ($aIdiomas as $aIdioma) {
		$matches = preg_grep('/^' . $sId . '_' . $aIdioma['id'] . '/i', $aImagenes);

		if (count($matches) > 0) {
			$matches = array_values($matches);
			return DIR_WS_CATALOG_IMAGES . 'banners/' . $matches[0];
		}
	}
}

function deleteImagenBannerDestacado($nId, $nIdIdioma, $sTipo) {
	$aImagenes      = scandir(DIR_FS_CATALOG_IMAGES . 'banners_destacados/');
	$aImagenesThumb = scandir(DIR_FS_CATALOG_IMAGES . 'banners_destacados/thumbnails/');

	// Eliminamos las imagenes
	$aFiles = preg_grep('/^' . $nId . '_' . $nIdIdioma . '_' . $sTipo . '_/i', $aImagenes);

	foreach ($aFiles as $sFile)
		@unlink(DIR_FS_CATALOG_IMAGES . 'banners_destacados/' . $sFile);

	// Eliminamos los thumbs
	$aFiles = preg_grep('/^' . $nId . '_' . $nIdIdioma . '_' . $sTipo . '_/i', $aImagenesThumb);

	foreach ($aFiles as $sFile)
		@unlink(DIR_FS_CATALOG_IMAGES . 'banners_destacados/thumbnails/' . $sFile);
}

function getImagenBannerDestacado($nId, $nIdIdioma, $sTipo = '', $bImagen = true) {
	$sDirBanners = DIR_FS_CATALOG_IMAGES . 'banners_destacados/';
	if (!is_dir($sDirBanners)) return false;

	$aImagenes = scandir($sDirBanners);

	// Buscar por tipo específico o cualquier tipo si no se indica
	if ($sTipo != '') {
		$matches = preg_grep('/^' . $nId . '_' . $nIdIdioma . '_' . $sTipo . '_/i', $aImagenes);
	} else {
		$matches = preg_grep('/^' . $nId . '_' . $nIdIdioma . '_/i', $aImagenes);
	}

	// Preferir formato nuevo {id}_{lang}_{tipo} sobre legacy Media Manager {id}_{lang}_g_{slug}
	$matchesNoLegacy = array_values(array_filter($matches, function($f) use ($nId, $nIdIdioma) {
		return !preg_match('/^' . $nId . '_' . $nIdIdioma . '_g_/i', $f);
	}));
	if (count($matchesNoLegacy) > 0) {
		$matches = $matchesNoLegacy;
	}

	// Filtrar webp para mostrar jpg/png preferentemente
	$matchesNoWebp = array_values(array_filter($matches, function($f) { return !preg_match('/\.webp$/i', $f); }));
	if (count($matchesNoWebp) > 0) {
		$matches = $matchesNoWebp;
	} else {
		$matches = array_values($matches);
	}

	if (count($matches) > 0) {
		if ($bImagen)
			return tep_image(DIR_WS_CATALOG_IMAGES . 'banners_destacados/' . $matches[0], 'Previsualizacion', SMALL_IMAGE_WIDTH, SMALL_IMAGE_HEIGHT, '', false);
		else
			return DIR_WS_CATALOG_IMAGES . 'banners_destacados/' . $matches[0];
	}

	return false;
}

function tep_get_purchases_remarks($purchases_id) {
	$purchase_query = tep_db_query("select purchase_remarks from " . TABLE_PURCHASES_REMARKS . " where purchases_id = '" . (int)$purchases_id . "'");
	$purchase       = tep_db_fetch_array($purchase_query);

	return $purchase['purchase_remarks'];
}


function getProductFreeShippingByGeoZone() {
	// Si estamos logueados
	if (tep_session_is_registered('customer_id') && FREE_SHIPPING_PENINSULA == 1) {
		// Comprobamos si el usuario pertenece a la península
		$aZone = tep_db_query('SELECT * FROM ' . TABLE_ZONES_TO_GEO_ZONES . ' WHERE zone_id = ' . $_SESSION['customer_zone_id'] . ' AND geo_zone_id = 12;');

		// Si tenemos registros pertenece a la península
		return tep_db_num_rows($aZone) > 0;
	}

	return true;
}

/**
 * Obtiene el recargo de equivalencia asociado a un cliente.
 *
 * @param int $id ID del cliente
 *
 * @return string Recargo de equivalencia del cliente (o cadena vacía si no se encuentra)
 */
function tiene_recargo($id = 0) {
	$salida = ''; // Inicializar la variable de salida

	// Consultar el recargo de equivalencia del cliente
	$recargo_query = tep_db_query("SELECT recargo_equivalencia FROM " . TABLE_CUSTOMERS . " WHERE customers_id='" . (int)$id . "'");

	while ($recargo = tep_db_fetch_array($recargo_query)) {
		$salida = $recargo['recargo_equivalencia'];
	}

	return $salida;
}

// Devolvemos el array creado por defecto, si no los datos del SQL
function getCombobox($sSql, $aPropiedades = true) {
	$bArray    = (empty($aPropiedades['ARRAY']) ? true : $aPropiedades['ARRAY']);
	$aAddArray = (empty($aPropiedades['ADD']) ? [] : [$aPropiedades['ADD']]);

	// Descomponemos el SQL
	$aAux   = explode('^', $sSql);
	$aDatos = tep_db_query('select ' . $aAux[1] . ' as id, ' . $aAux[2] . ' as text from ' . $aAux[0] . (isset($aAux[4]) ? ' where ' . $aAux[4] : '') . ' order by ' . $aAux[2] . (isset($aAux[3]) ? $aAux[3] : ' asc'));

	// Si tenemos que devolver un array
	if ($bArray) {
		$aAux = $aAddArray;
		while ($aDato = tep_db_fetch_array($aDatos))
			$aAux[] = ['id' => $aDato['id'], 'text' => $aDato['text']];

		return $aAux;
	} else
		return $aDatos;
}

// Devuelve los archivos de un directorio ordenados por fecha
function scandirByDate($sDir, $sAllow = '') {
	$aIgnored = ['.', '..', '.svn', '.htaccess'];
	$aFiles   = [];

	foreach (scandir($sDir) as $sFile) {
		if (in_array($sFile, $aIgnored))
			continue;

		if ($sAllow != '' && !preg_match($sAllow, $sFile))
			continue;

		$aFiles[$sFile] = filemtime($sDir . '/' . $sFile);
	}

	arsort($aFiles);
	$aFiles = array_keys($aFiles);

	return ($aFiles ? $aFiles : []);
}

// Eliminar imagen de categoria/producto
function deleteImagenAndThumb($sImagen, $sType = 'productos') {
	if (empty($sImagen)) return;

	// Obtenemos todos los thumbs
	$aFiles = glob(getcwd() . '/../images/' . $sType . '/thumbnails/' . preg_replace('/\\.[^.\\s]{3,4}$/', '', $sImagen) . '_thumb*');

	// Recorremos para eliminar
	if (is_array($aFiles))
		foreach ($aFiles as $sFile)
			@unlink($sFile);

	// Eliminamos la imagen
	@unlink(DIR_FS_CATALOG_IMAGES . $sType . '/' . $sImagen);
}

/**
 * Devuelve true o false si la cadena pasada es un json
 */
function is_json($string) {
	if ($string == '' || $string == null || $string == null || $string == 'null' || $string == 'NULL' || is_numeric($string))
		return false;

	json_decode($string);
	return (json_last_error() == JSON_ERROR_NONE);
}

// Crea el objeto info dada una tabla, se pueden añadir campos, eliminar campos o definir variables a un campo
function createObjectInfo($sTable, $aPropiedades = []) {
	// Variables
	$aInsert = array_key_exists('INSERT', $aPropiedades) ? $aPropiedades['INSERT'] : [];
	$aDelete = array_key_exists('DELETE', $aPropiedades) ? $aPropiedades['DELETE'] : [];
	$aDefine = array_key_exists('DEFINE', $aPropiedades) ? $aPropiedades['DEFINE'] : [];
	$bIdioma = array_key_exists('IDIOMA', $aPropiedades) ? $aPropiedades['IDIOMA'] : false;
	$aAux    = [];

	// Obtenemos los campos de la tabla
	$aDatos = tep_db_query('DESCRIBE ' . $sTable);

	// Recorremos los campos
	while ($aDato = tep_db_fetch_array($aDatos)) {
		// No mostrar campo
		if (in_array($aDato['Field'], $aDelete))
			continue;

		// Si hemos definido algun campo
		if (isset($aDefine[$aDato['Field']]))
			$aAux[$aDato['Field']] = $aDefine[$aDato['Field']];
		else
			$aAux[$aDato['Field']] = '';
	}

	// Recorremos los campos a insertar
	foreach ($aInsert as $key => $value) {
		// Si el valor es un array, el campo es definido
		if (is_array($value))
			$aAux[key($value)] = $value[key($value)];
		else
			$aAux[$value] = '';
	}

	// Si nos han enviado idioma
	if ($bIdioma) {
		$aIdiomas = tep_get_languages();
		$aAux2    = $aAux;
		$aAux     = [];

		foreach ($aIdiomas as $aIdiomas)
			$aAux[$aIdiomas['id']] = $aAux2;
	}

	return new objectInfo($aAux);
}


// Cargar formulario YML
function loadForm($sYml) {
	$aArray = @Spyc::YAMLLoad(file_get_contents($sYml));
	return $aArray;
}

// Mostrar formulario
function showForm($aForms) {
	// Variables
	$sHtml = '';

	// Comprobamos si no es un array, entonces sera un yml
	if (!is_array($aForms))
		$aForms = loadForm($aForms);

	// Recorremos los distintas partes del form
	foreach ($aForms['blocks'] as $aForm) {
		// Variables
		$bLink             = false;
		$nSizeDefaultLabel = $aForm['size_label'];
		$nSizeDefaultRow   = $aForm['size_row'];
		$sMetodoForm       = strtolower((array_key_exists('method', $aForm) ? $aForm['method'] : 'post'));
		$sValueRow         = '';

		$sHtml .= '<div class="fluid grid">';
		$sHtml .= '<div class="box-tbl grid12">';
		$sHtml .= '<div class="box-head">';
		$sHtml .= '<h6>' . $aForm['title'] . '</h6>';

		// Si disponemos html
		if (array_key_exists('html_head', $aForm))
			$sHtml .= $aForm['html_head'];

		$sHtml .= '<div class="clear"></div>';
		$sHtml .= '</div>';

		// Recorremos los campos
		foreach ($aForm['rows'] as $nTabIndex => $aRow) {
			// Reseteamos
			$sPropiedadesRow = '';
			$sValueInfo      = '';

			// Comprobamos si estara visible
			if (array_key_exists('visible', $aRow)) {
				// Si no es un campo anidado y anteriormente si lo estaba cerramos
				if (!array_key_exists('link', $aRow) && $bLink) {
					$sHtml .= '<div class="clear"></div>';
					$sHtml .= '</div>';
					$bLink = false;
				}

				// Si es un campo bool y esta en false pasamos de el
				if (is_bool($aRow['visible']) === true && $aRow['visible'] === false)
					continue;
				else {
					// Obtenemos el valor
					eval('$sAux = ' . $aRow['visible'] . ' ;');

					// Si el campo es false pasamos de el
					if ($sAux === 'false')
						continue;
				}
			}

			// Si no es un campo anidado
			if (!$bLink)
				$sHtml .= '<div ' . (array_key_exists('style_row', $aRow) ? 'style="' . $aRow['style_row'] . '"' : '') . ' class="formRow ' . (array_key_exists('class_row', $aRow) ? $aRow['class_row'] : '') . '">';

			// Tamaños
			$nSizeLabel = $nSizeDefaultLabel;
			$nSizeRow   = $nSizeDefaultRow;

			// Obtenemos tamaño y el label
			if (is_array($aRow['label'])) {
				$sLabel     = $aRow['label']['text'];
				$nSizeLabel = $aRow['label']['size'];
			} else
				$sLabel = $aRow['label'];

			// Label
			$sHtml .= '<div class="grid' . $nSizeLabel . '">';
			$sHtml .= '<label for="' . $aRow['name'] . '">';
			// Requerido
			if (array_key_exists('required', $aRow))
				$sHtml .= '<span class="fieldRequired">* </span>';

			$sHtml .= $sLabel;
			$sHtml .= '</label>';
			$sHtml .= '</div>';

			// Obtenemos tamaño y el campo
			if (is_array($aRow['type'])) {
				$sRow     = $aRow['type']['type'];
				$nSizeRow = $aRow['type']['size'];
				$sIdRow   = $aRow['type']['id'];
			} else
				$sRow = $aRow['type'];

			// Si contiene propiedades la fila
			if (array_key_exists('property_row', $aRow)) {
				foreach ($aRow['property_row'] as $key => $value)
					if ($key != 'class')
						$sPropiedadesRow .= ' ' . $key . '="' . $value . '"';
			}

			// Añadimos las clases a la fila
			$sPropiedadesRow .= 'class="grid' . $nSizeRow . ' ' . $aRow['property_row']['class'] . '"';

			// Campo
			$sHtml .= '<div ' . $sPropiedadesRow . '>';
			// Propiedades
			// ID
			$sPropiedades = 'id="' . $aRow['name'] . '"';

			// Tabindex
			$sPropiedades .= ' tabindex="' . $nTabIndex . '"';

			// Si contiene propiedades la añadimos el campo
			if (array_key_exists('property', $aRow)) {
				foreach ($aRow['property'] as $key => $value)
					$sPropiedades .= ' ' . $key . '="' . $value . '"';
			}

			// Inicio validadores
			// Requerido
			if (array_key_exists('required', $aRow))
				$sPropiedades .= ' required';
			// Fin validadores

			// Si el nombre es un array obtenemos el valor del campo en el info
			if (array_key_exists('name_array', $aRow) && $aRow['name_array']) {
				if (!(array_key_exists('info', $aForms) && $aForms['info']))
					die('Falta el objeto info para poder mostrar un campo como array');

				if (is_object($aForms['info']))
					$sValueInfo = $aForms['info']->$aRow['name_array'];
				else {
					eval('global ' . $aForms['info'] . ';');
					eval('$sValueInfo = ' . $aForms['info'] . '->' . $aRow['name_array'] . ';');
				}
			}


			// Valor
			$sValueRow = '';

			// Si contiene un objeto info
			if (array_key_exists('info', $aForms) && $aForms['info']) {
				if (is_object($aForms['info']))
					$sValueRow = $aForms['info']->$aRow['name'];
				else {
					eval('global ' . $aForms['info'] . ';');
					eval('$sValueRow = ' . $aForms['info'] . '->' . $aRow['name'] . ';');
				}
			}

			// Obtenemos el valor
			if (array_key_exists('value', $aRow))
				$sValueRow = $aRow['value'];

			// Comprobamos si tenemos valor por post
			if ($sMetodoForm == 'post' && $_SERVER['REQUEST_METHOD'] == 'POST' && array_key_exists('dxsendform', $_POST)) {
				// Si es un array
				if (array_key_exists('name_array', $aRow) && $aRow['name_array']) {
					$sAux      = tep_db_prepare_input($_POST[$aRow['name']][$sValueInfo]);
					$sValueRow = $sAux;
				} else {
					$sAux      = tep_db_prepare_input($_POST[$aRow['name']]);
					$sValueRow = $sAux;
				}
			} else if ($_SERVER['REQUEST_METHOD'] == 'GET' && array_key_exists('dxsendform', $_GET)) // Si no sera por get
			{
				// Si es un array
				if (array_key_exists('name_array', $aRow) && $aRow['name_array']) {
					$sAux      = tep_db_prepare_input($_GET[$aRow['name']][$sValueInfo]);
					$sValueRow = $sAux;
				} else {
					$sAux      = tep_db_prepare_input($_GET[$aRow['name']]);
					$sValueRow = $sAux;
				}
			}

			// Si existe una funcion para pintar el campo
			if (function_exists('call_Form_getRow_' . $aRow['name'])) {
				eval('$sHtml .= call_Form_getRow_' . $aRow['name'] . '($aRow, $aDato,$sValueInfo);');

				// No pasamos por el switch
				$sRow = null;
			}

			// Obtenemos los data, si no existe intentamos llamar una funcion para cargarlos
			if (!array_key_exists('data', $aRow) && function_exists('call_Form_getData_' . $aRow['name']))
				eval('$aRow["data"] = call_Form_getData_' . $aRow['name'] . '();');

			// Modificamos el name si se enviara como array
			if (array_key_exists('name_array', $aRow) && $aRow['name_array'])
				$aRow['name'] .= '[' . $sValueInfo . ']';

			// Decidimos el campo
			switch ($sRow) {
				case 'email':
				case 'text':
					$sHtml .= '<input type="' . $sRow . '" value="' . $sValueRow . '" name="' . $aRow['name'] . '">';
					break;

				case 'date':
					$sHtml .= tep_draw_input_field($aRow['name'], $sValueRow, $sPropiedades . ' class="datepicker"');
					break;

				case 'textarea':
					$sHtml .= tep_draw_textarea_field($aRow['name'], '', '', '', $sValueRow, $sPropiedades);
					break;

				case 'image':
					$sHtml .= tep_draw_file_field($aRow['name'], '');
					$sHtml .= '<input type="hidden" name="' . $aRow['name'] . '_hidden" value="' . str_replace(getcwd(), '', $sValueRow) . '" />';

					if ($sValueRow != '' && file_exists($sValueRow)) {
						// $sHtml .= ' <a style="position: relative; top: 7px;" title="Elminar" href="javascript:void(0);"><img width="19" height="18" border="0" title="Eliminar" alt="Eliminar" src="images/borrar_noticia.gif" /></a>';
						$sHtml .= '<a style="position: relative; top: 7px;" title="Previsualizar" target="_blank" href="' . str_replace(getcwd(), '', $sValueRow) . '"><img width="19" height="18" border="0" title="Previsualizar" alt="Previsualizar" src="images/icons/preview.png" /></a>';
					}
					break;

				case 'combobox':
					$sHtml .= tep_draw_pull_down_menu($aRow['name'], $aRow['data'], $sValueRow);
					break;

				case 'radio':
					foreach ($aRow['data'] as $aData) {
						$sHtml .= '<input ' . ($sValueRow == $aData['id'] ? 'checked="checked"' : '') . ' type="radio" name="' . $aRow['name'] . '" value="' . $aData['id'] . '"/>';
						$sHtml .= '<label style="margin-right: 10px;">' . $aData['text'] . '</label>';
					}
					break;
			}

			// Modificamos el name si se enviara como array
			if (array_key_exists('note', $aRow) && $aRow['note'])
				$sHtml .= '<span class="note">' . $aRow['note'] . '</span>';

			$sHtml .= '</div>';

			// Si no es campo aninado
			if (!array_key_exists('link', $aRow)) {
				$sHtml .= '<div class="clear"></div>';
				$sHtml .= '</div>';
				$bLink = false;
			} else
				$bLink = true;
		}

		$sHtml .= '</div>';
		$sHtml .= '</div>';
	}

	return $sHtml;
}

////
// Return all subcategory IDs
// TABLES: categories
function tep_get_subcategories(&$subcategories_array, $parent_id = 0) {
	$subcategories_query = tep_db_query("select categories_id from " . TABLE_CATEGORIES . " where parent_id = '" . (int)$parent_id . "'");
	while ($subcategories = tep_db_fetch_array($subcategories_query)) {
		$subcategories_array[sizeof($subcategories_array)] = $subcategories['categories_id'];
		if ($subcategories['categories_id'] != $parent_id) {
			tep_get_subcategories($subcategories_array, $subcategories['categories_id']);
		}
	}
}

// End copied functions


//begin Supportticketsystem
include('includes/functions/support_functions.php');
//end Supportticketsystem

// Start products specifications

// Functions copied from catalog/includes/functions/general.php
////
// Return true if the category has subcategories
// TABLES: categories
function tep_has_category_subcategories($category_id) {
	$child_category_query = tep_db_query("select count(*) as count from " . TABLE_CATEGORIES . " where parent_id = '" . (int)$category_id . "'");
	$child_category       = tep_db_fetch_array($child_category_query);

	if ($child_category['count'] > 0) {
		return true;
	} else {
		return false;
	}
}

////
// Return the products_tab_x data from the database
// TABLES: products_description
function tep_get_products_tabs($product_id, $language_id) {
	$product_query_raw = "
      select
        products_tab_1,
        products_tab_2,
        products_tab_3,
        products_tab_4,
        products_tab_5,
        products_tab_6
      from
        " . TABLE_PRODUCTS_DESCRIPTION . "
      where
        products_id = '" . (int)$product_id . "'
        and language_id = '" . (int)$language_id . "'
    ";
	$product_query     = tep_db_query($product_query_raw);

	$product_tabs = [];
	$product      = tep_db_fetch_array($product_query);
	for ($i = 1, $n = 7; $i < $n; $i++) {
		$product_tabs[$i] = $product['products_tab_' . $i];
	}

	return $product_tabs;
}

// End products specifications


function tep_set_product_sort_order($products_id, $sort_order) {
	return tep_db_query("update " . TABLE_PRODUCTS . " set products_sort_order = '" . $sort_order . "', products_last_modified = now() where products_id = '" . (int)$products_id . "'");
}

function tep_create_random_value($length, $type = 'mixed') {
	if (($type != 'mixed') && ($type != 'chars') && ($type != 'digits')) return false;

	$rand_value = '';
	while (strlen($rand_value) < $length) {
		if ($type == 'digits') {
			$char = tep_rand(0, 9);
		} else {
			$char = chr(tep_rand(0, 255));
		}
		if ($type == 'mixed') {
			if (preg_match('/^[a-z0-9]$/i', $char)) $rand_value .= $char;
		} else if ($type == 'chars') {
			if (preg_match('/^[a-z]$/i', $char)) $rand_value .= $char;
		} else if ($type == 'digits') {
			if (preg_match('/^[0-9]$/i', $char)) $rand_value .= $char;
		}
	}

	return $rand_value;
}

// Parse search string into indivual objects
function tep_parse_search_string($search_str, &$objects) {
	$search_str = trim(strtolower($search_str));

	// Break up $search_str on whitespace; quoted string will be reconstructed later
	$pieces = preg_split('/[[:space:]]+/', $search_str);
	$objects = [];
	$tmpstring = '';
	$flag = '';
    $counter = count($pieces);

	for ($k = 0; $k < $counter; $k++) {
		while (str_starts_with($pieces[$k], '(')) {
			$objects[] = '(';
			$pieces[$k] = strlen($pieces[$k]) > 1 ? substr($pieces[$k], 1) : '';
		}

		$post_objects = [];

		while (str_ends_with($pieces[$k], ')')) {
			$post_objects[] = ')';
			$pieces[$k] = strlen($pieces[$k]) > 1 ? substr($pieces[$k], 0, -1) : '';
		}

		// Check individual words

		if ((!str_ends_with($pieces[$k], '"')) && (!str_starts_with($pieces[$k], '"'))) {
			$objects[] = trim($pieces[$k]);

			for ($j = 0; $j < count($post_objects); $j++) {
				$objects[] = $post_objects[$j];
			}
		} else {
			/* This means that the $piece is either the beginning or the end of a string.
			So, we'll slurp up the $pieces and stick them together until we get to the
			end of the string or run out of pieces.
			*/

			// Add this word to the $tmpstring, starting the $tmpstring
			$tmpstring = trim((string) preg_replace('/"/', ' ', $pieces[$k]));

			// Check for one possible exception to the rule. That there is a single quoted word.
			if (str_ends_with($pieces[$k], '"')) {
				// Turn the flag off for future iterations
				$flag = 'off';

				$objects[] = trim((string) preg_replace('/"/', ' ', $pieces[$k]));

				for ($j = 0; $j < count($post_objects); $j++) {
					$objects[] = $post_objects[$j];
				}

				unset($tmpstring);

				// Stop looking for the end of the string and move onto the next word.
				continue;
			}

			// Otherwise, turn on the flag to indicate no quotes have been found attached to this word in the string.
			$flag = 'on';

			// Move on to the next word
			$k++;

			// Keep reading until the end of the string as long as the $flag is on

			while (($flag === 'on') && ($k < count($pieces))) {
				while (str_ends_with($pieces[$k], ')')) {
					$post_objects[] = ')';
					$pieces[$k] = strlen($pieces[$k]) > 1 ? substr($pieces[$k], 0, -1) : '';
				}

				// If the word doesn't end in double quotes, append it to the $tmpstring.
				if (!str_ends_with($pieces[$k], '"')) {
					// Tack this word onto the current string entity
					$tmpstring .= ' ' . $pieces[$k];

					// Move on to the next word
					$k++;
					continue;
				} else {
					/* If the $piece ends in double quotes, strip the double quotes, tack the
					$piece onto the tail of the string, push the $tmpstring onto the $haves,
					kill the $tmpstring, turn the $flag "off", and return.
					*/
					$tmpstring .= ' ' . trim((string) preg_replace('/"/', ' ', $pieces[$k]));

					// Push the $tmpstring onto the array of stuff to search for
					$objects[] = trim($tmpstring);

					for ($j = 0; $j < count($post_objects); $j++) {
						$objects[] = $post_objects[$j];
					}

					unset($tmpstring);

					// Turn off the flag to exit the loop
					$flag = 'off';
				}
			}
		}
	}

	// add default logical operators if needed
	$temp = [];
	for ($i = 0; $i < (count($objects) - 1); $i++) {
		$temp[] = $objects[$i];
		if (
			($objects[$i] !== 'and') &&
			($objects[$i] !== 'or') &&
			($objects[$i] !== '(') &&
			($objects[$i + 1] !== 'and') &&
			($objects[$i + 1] !== 'or') &&
			($objects[$i + 1] !== ')')
		) {
			$temp[] = ADVANCED_SEARCH_DEFAULT_OPERATOR;
		}
	}
	$temp[] = $objects[$i];
	$objects = $temp;

	$keyword_count = 0;
	$operator_count = 0;
	$balance = 0;
    $counter = count($objects);
	for ($i = 0; $i < $counter; $i++) {
		if ($objects[$i] == '(') {
            $balance--;
        }
		if ($objects[$i] == ')') {
            $balance++;
        }
		if (
			($objects[$i] == 'and') ||
			($objects[$i] == 'or')
		) {
			$operator_count++;
		} elseif (
			($objects[$i]) &&
			($objects[$i] != '(') &&
			($objects[$i] != ')')
		) {
			$keyword_count++;
		}
	}

	if (($operator_count < $keyword_count) && ($balance == 0)) {
		return true;
	} else {
		return false;
	}
}

function fecha_mysql($fecha) {
	$fecha = explode('/', $fecha);
	return $fecha[2] . '-' . $fecha[1] . '-' . $fecha[0];
}

function fecha_normal($fecha) {
	preg_match("/([0-9]{2,4})-([0-9]{1,2})-([0-9]{1,2})/i", $fecha, $mifecha);
	$lafecha = $mifecha[3] . "/" . $mifecha[2] . "/" . $mifecha[1];
	return $lafecha;
}

function tep_get_category_amazon_tree($parent_id = '0', $spacing = '', $exclude = '', $category_tree_array = '') {
	if ($exclude == '')
		$exclude = [];

	if (!is_array($category_tree_array))
		$category_tree_array = [];

	if (sizeof($category_tree_array) < 1 && !in_array(0, $exclude))
		$category_tree_array[] = ['id' => '0', 'text' => 'Seleccionar'];

	$categories_query = tep_db_query("select c.categories_id, cd.categories_name, c.parent_id from categories_amazon c INNER JOIN categories_amazon_description cd on (c.categories_id = cd.categories_id) where cd.language_id = '3' and c.parent_id = '" . (int)$parent_id . "' order by cd.categories_name");
	while ($categories = tep_db_fetch_array($categories_query)) {
		if (!in_array($categories['categories_id'], $exclude)) $category_tree_array[] = ['id' => $categories['categories_id'], 'text' => $spacing . $categories['categories_name']];
		$category_tree_array = tep_get_category_amazon_tree($categories['categories_id'], $spacing . '&nbsp;&nbsp;&nbsp;', $exclude, $category_tree_array);
	}

	return $category_tree_array;
}

/**
 * Token efimero para "conectar como cliente" desde el admin.
 * Sustituye a la contrasena maestra estatica (MAST_PW): va ligado al email del
 * cliente y firmado con SECURITY_KEY. Lo valida Customer::validateMasterToken()
 * en el front. Caduca por si solo (por defecto 6h, suficiente para una sesion
 * de trabajo con el listado/ficha abierto).
 */
function tep_master_connect_token($email, $ttl = 21600) {
	$exp = time() + (int)$ttl;
	return $exp . '.' . hash_hmac('sha256', strtolower(trim((string)$email)) . '|' . $exp, SECURITY_KEY);
}

function getSlug($sTexto, $sSeparator = '-') {
	// Convertimos los caracteres especiales

	$sTexto = htmlentities($sTexto);
	$sTexto = preg_replace('/&([a-zA-Z])(uml|acute|grave|circ|tilde);/', '$1', $sTexto);
	$sTexto = html_entity_decode($sTexto);

	// Pasamos a minusculas
	$sTexto = strtolower($sTexto);

	// Convertimos los caracteres no permitidos a espacios
	$sTexto = preg_replace('/\W/', ' ', $sTexto);

	// Reemplazamos los espacios por el separador
	$sTexto = preg_replace('/\ +/', $sSeparator, $sTexto);

	// Hacemos un trim para quitar espacios sobrantes
	$sTexto = trim($sTexto, $sSeparator);

	return $sTexto;
}


/*
	 * Funcion que devuelve un array con los textos de un archivo del idioma
	 *
	 * @return array
	*/
function getLangugeFile($sFile, $bJson = true) {
	global $language, $sDirNameScriptName;
	$aDenegado = ['<?', '<?php', '?>', '']; // Lineas denegadas cuando leemos un archivo
	$aReturn   = [];

	// Si no nos envian la ruta
	if (!preg_match('/\//i', (string)$sFile)) {
		$sFile = DIR_FS_CATALOG . DIR_WS_LANGUAGES . $language . '/' . $sFile;
	}

	// Comprobamos si el archivo existe
	if (file_exists($sFile)) {
		$aDatos = getDefineKeysValuesByFile($sFile, $aDenegado);

		foreach ($aDatos as $key => $value)
			$aReturn[tep_db_prepare_input($key)] = html_entity_decode((string)$value, ENT_QUOTES, "UTF-8");
	}
	if ($bJson) {
		echo json_encode($aReturn);
	} else {
		return $aReturn;
	}
	return null;
}

/*
	 * Funcion que lee un archivo y devuelve un array linea a linea en utf8
	 *
	 * @return array
	*/
function getLinesFileUtf8($sFile, $sCharset = 'UTF-8') {
	$sData = '';

	if (!file_exists($sFile))
		return false;

	if (floatval(phpversion()) >= 4.3)
		$sData = file_get_contents($sFile);
	else {
		$flFile = fopen($sFile, 'r');

		if (!$flFile)
			return false;

		while (!feof($flFile))
			$sData .= fread($flFile, filesize($sFile));

		fclose($flFile);
	}

	if (!isset($sFile))
		return false;

	if ($sData && $sEncoding = mb_detect_encoding($sData, 'auto', true) != $sCharset)
		$sData = @mb_convert_encoding($sData, $sCharset, $sEncoding);

	return preg_split('/\R/', $sData);
}

/*
	 * Funcion que lee un archivo lleno de defines y devuelve un array con KEY, VALUE
	 *
	 * @return array
	*/
function getDefineKeysValuesByFile($sRutaCompleta, $aDenegado) {
	// Array de retorno
	$aReturn = [];

	// Abrimos el archivo
	$flFile = getLinesFileUtf8($sRutaCompleta);

	// Si hemos obtenido el archivo
	if ($flFile) {
		// Recorremos las lineas
		foreach ($flFile as $sLine) {
			// Inicio, Limpiamos la linea \\

			// Quitamos tabuladores
			$sLine = str_replace("\t", '', $sLine);

			// Quitamos los alt+255
			$sLine = str_replace(" ", '', $sLine);

			// Quitamos espacios
			$sLine = trim($sLine);

			// Fin, Limpiamos la linea \\

			// Comprobamos que la linea obtenida no sea algo que no queremos
			if (in_array($sLine, $aDenegado))
				continue;

			// Comprobamos que sea un define
			if (!preg_match('/^(define)(\s?)(\()/i', $sLine))
				continue;

			// Obtenemos los define de la linea, normalmente sera uno por cada linea, pero puede existir el caso que haya mas de un define en una linea
			preg_match_all("/(define)(\s?)*(\()(.*)(\);)/Ui", $sLine, $aDefines, PREG_PATTERN_ORDER);

			// Si no hemos obtenido nada es que hemos encontrado algun define sin ; al final
			if (count($aDefines[0]) == 0)
				preg_match_all("/(define)(\s?)*(\()(.*)(\))/Ui", $sLine, $aDefines, PREG_PATTERN_ORDER);

			// Recorremos los define obtenidos
			foreach ($aDefines[0] as $sLine) {
				// echo htmlentities($sLine) . '<br/><br/>-----------------------------------<br/><br/>';

				// Inicio, descomponer el define obtenido \\
				// Descomponemos el define obtenido en KEY y VALUE
				//preg_match('/(define)(\s*)(\()((\'|\")*)(?<KEY>[^,]+)((\'|\")*)(\s*)(\,)(\s*)((\'|\")*)(?<VALUE>.+)((\'|\")*)(\s*)(\))(\;?)$/i', $sLine, $aAux);
				preg_match('/(define)(\s*)(\()(?<KEY>[^,]+)(\s*)(\,)(\s*)(?<VALUE>.+)(\s*)(\))(\;?)$/i', $sLine, $aAux);

				// Comprobamos que el key sea una llamada a funcion y se ha quedado rota, de ser asi utilizamos otro preg_match para obtener el KEY y VALUE
				if (preg_match('/\(/i', $aAux['KEY']) && !preg_match('/\)$/i', $aAux['KEY']))
					preg_match('/(define)(\s*)(\()(?<KEY>.+)(\s*)(\,)(\s*)(?<VALUE>.+)(\s*)(\))(\;?)$/i', $sLine, $aAux);
				// Fin, descomponer el define obtenido \\

				// Inicio, limpiamos el key \\
				// Quitamos espacios
				$aAux['KEY'] = trim($aAux['KEY']);

				// Si contiene una concatenación la cadena dejamos las comas por el contrario eliminariamos
				if (!preg_match('/(\'|")(\s*)\.|\.(\s*)(\'|")/i', $aAux['KEY']))
					$aAux['KEY'] = preg_replace('/^(\'|")|(\'|")$/i', '', $aAux['KEY']);
				// Fin, limpiamos el key \\

				// Inicio, limpiamos el value \\
				// Quitamos espacios
				$aAux['VALUE'] = trim($aAux['VALUE']);

				eval('$sAux = ' . $aAux['VALUE'] . ';');
				$aAux['VALUE'] = $sAux;

				// Si contiene una concatenación la cadena dejamos las comas por el contrario eliminariamos
				if (!preg_match('/(\'|")(\s*)\.(.+)|(\s*)\.(\s*)(\'|")(.+)/i', $aAux['VALUE']))
					$aAux['VALUE'] = preg_replace('/^(\'|")|(\'|")$/i', '', $aAux['VALUE']);

				// Mostramos html como texto para que no afecte cuando se muestre en el input
				$aAux['VALUE'] = htmlentities($aAux['VALUE'], ENT_QUOTES, "UTF-8");
				// Fin, limpiamos el value \\

				// Añadimos la linea al array
				$aReturn[$aAux['KEY']] = $aAux['VALUE'];
			}

			// die();
		}

		return $aReturn;
	}

	return false;
}

include('includes/functions/refund_functions.php');

/**
 * Incluye un archivo template
 *
 * @param string $sFile Archivo a cargar
 * @param array  $aVars Variables del template
 */
function includeTemplate($sFile, $aVars = []) {
	// Juntamos las variables globales con las variables pasadas
	$aVars = array_merge($GLOBALS, $aVars);

	// Extraemos las variables para el template
	extract($aVars);

	// Almacenamos la salida hasta aqui para obtener solo el contenido a incluir
	ob_start();

	// Incluimos el archivo
	include($sFile);

	// Contenido obtenido
	$sHtmlContent = ob_get_contents();

	// Continuamos con la salida por donde ibamos
	ob_end_clean();

	// Retornamos
	return $sHtmlContent;
}

/**
 * Obtiene el valor de un campo si está definido, de lo contrario devuelve una cadena vacía.
 *
 * @param string $field El nombre del campo a obtener.
 *
 * @return string El valor del campo si está definido, de lo contrario una cadena vacía.
 */
function getFieldValue($field) {
	return isset($_POST[$field]) ? tep_db_prepare_input($_POST[$field]) : '';
}

/**
 * Obtiene los valores seleccionados en un conjunto de opciones.
 *
 * @param array $options El conjunto de opciones.
 *
 * @return string Los valores seleccionados separados por punto y coma (;).
 */
function getSelectedValues($options) {
	$selectedValues = '';

	if (isset($options)) {
		foreach ($options as $val) {
			if ($val == true) {
				$selectedValues .= tep_db_prepare_input($val) . ';';
			}
		}

		$selectedValues = rtrim($selectedValues, ';');
	}

	return $selectedValues;
}

function buttonGenerarDevolucion(int $oID): string {

	global $currencies;

	$allowed = ['redsys', 'bizum', 'paypal_express', 'paypal'];

	if (empty($allowed)) {
		return '';
	}

	$order_total = 0;
	$sql         = sprintf('SELECT value FROM orders_total WHERE orders_id = %d AND class="ot_total"', $oID);

	$sql = tep_db_query($sql);
	if (tep_db_num_rows($sql)) {
		$order       = tep_db_fetch_array($sql);
		$order_total = round($order['value'], 2);
	}

	$sql = sprintf(
		'SELECT reference, SUM(value) as amount
				FROM redsys_payment_movements
				WHERE orders_id = %d AND module IN (%s)
				GROUP BY orders_id',
		$oID,
		'"' . implode('","', $allowed) . '"',
	);
	$sql = tep_db_query($sql);
	if (!tep_db_num_rows($sql)) {
		return '<p style="margin-top: 10px; color: grey; opacity: .7;">Para este pedido no está disponible la opción de devolución.</p>';
	}

	$order = tep_db_fetch_array($sql);

	$sql           = sprintf(
		'SELECT r.reference, r.value, r.admin_id, a.admin_firstname, a.admin_lastname, r.date_created
				FROM redsys_payment_movements r
				LEFT JOIN admin a ON (a.admin_id = r.admin_id)
				WHERE r.orders_id = %d AND r.value > 0',
		$oID,
	);
	$sql           = tep_db_query($sql);
	$init          = tep_db_fetch_array($sql);
	$init['value'] = round($init['value'], 2);

	if ($order['amount'] == 0) {
		$response = '<p style="margin-top: 10px; color: red;">Este pedido ha sido devuelto por completo.</p>';
	} else {

		if ($init['value'] > $order_total) {
			//$order['amount'] = $init['value'] - $order_total;
		}

		$amount   = number_format(floatval($order['amount']), 2, '.', '');
		$response = '<form method="post" style="margin-top: 10px;padding: 0;display: flex;border: none;" action="' . tep_href_link('orders.php', 'action=refund-order&oID=' . $oID) . '" class="formRow" id="refund-order">';
		$response .= '<input id="refund-order-amount" type="text" name="amount" value="' . $amount . '" style="text-align: right; margin: 0; max-width: 100px; height: auto; margin: 0 5px 0 0;" autocomplete="off" />';
		$response .= '<input id="refund-order-max" type="hidden" value="' . $amount . '" />';
		$response .= '<button type="submit" class="buttonS bRed">Generar devolución</button>';
		$response .= '</form>';
	}

	if ($init['value'] > $order_total) {
		$response .= '<pre style="margin-top: 10px;border-top: 1px solid #ddd;display: flex;gap: 10px;padding: 5px 0;justify-content: space-between;"><strong style="width: 100%;">Inicial</strong><strong style="width: 100%;">Editado</strong><strong style="width: 100%;">Diferencia</strong></pre>';
		$response .= '<pre style="display: flex;gap: 10px;padding: 0 0 5px 0;justify-content: space-between;"><span style="width: 100%;">' . $currencies->format($init['value']) . '</span><span style="width: 100%;">' . $currencies->format($order_total) . '</span><strong style="width: 100%;">' . $currencies->format($init['value'] - $order_total) . '</strong></pre>';
	}

	$sql = sprintf(
		'SELECT r.reference, r.value, r.admin_id, a.admin_firstname, a.admin_lastname, r.date_created
					FROM redsys_payment_movements r
					LEFT JOIN admin a ON (a.admin_id = r.admin_id)
					WHERE r.orders_id = %d AND r.value < 0',
		$oID,
	);

	$sql = tep_db_query($sql);
	if (tep_db_num_rows($sql)) {
		$response .= '<div style="margin-top: 10px">';
		$response .= '<pre style="display: flex;gap: 10px;padding: 5px 0;justify-content: space-between;"><strong style="width: 100%;"></strong><span style="width: 100%;">Cantidad</span><span style="width: 100%;">Fecha</span></pre>';
		$response .= '<pre style="border-top: 1px solid #ddd;display: flex;gap: 10px;padding: 5px 0;justify-content: space-between;"><strong style="width: 100%;">Total pedido</strong><span style="width: 100%;">' . $currencies->format($init['value']) . '</span><span style="width: 100%;">' . $init['date_created'] . '</span></pre>';
		$restante = $init['value'];
		while ($log = tep_db_fetch_array($sql)) {
			$response .= '<pre style="border-top: 1px solid #ddd;display: flex;gap: 10px;padding: 5px 0;justify-content: space-between;"><strong style="width: 100%;">' . $log['admin_firstname'] . ' ' . $log['admin_lastname'] . '</strong><span style="width: 100%; color: red;">' . $currencies->format($log['value']) . '</span><span style="width: 100%;">' . $log['date_created'] . '</span></pre>';
			$restante = $restante + $log['value'];
		}

		$response .= '<pre style="border-top: 1px solid #ddd;display: flex;gap: 10px;padding: 5px 0;justify-content: space-between;"><strong style="width: 100%;">Restante</strong><span style="width: 100%;">' . $currencies->format($restante) . '</span><span style="width: 100%;">--</span></pre>';
		$response .= '</div>';
	}

	$response .= '<div id="redsys-form-content"></div>';

	$response .= '
			<script>
			document.addEventListener("DOMContentLoaded", function() {
				$("#refund-order").submit(function() {
					amount = parseFloat($("#refund-order-amount").val())
					max = parseFloat($("#refund-order-max").val())

					if (amount > max) {
						alert("Revise la cantidad a devolver.")
						return false
					}

					if (confirm("¿Estás seguro?")) {
						$.ajax({
							type: "GET",
							url: "' . tep_href_link('orders.php', 'action=refund-order&oID=' . $oID) . '",
							data: {
								amount: amount
							}
						}).done(function( data ) {
							location.href = location.href
							//console.log(data)
						})
					}

					return false
				})
			}, false);
			</script>
			';

	return $response;

}


/**
 * Obtiene el precio de un producto
 *
 * @param integer $products_id
 *
 * @return float
 */
if (!function_exists('getPriceFromProductsId')) {
	function getPriceFromProductsId(int $products_id): float {
		global $nCustomerGroupId;

		$sql   = 'SELECT IF(s.status, s.specials_new_products_price, p.products_price) as final_price
           from products p
           left join specials s on (s.products_id = p.products_id and s.status = 1 and s.customers_group_id = "' . $nCustomerGroupId . '")
           WHERE p.products_id = ' . $products_id;
		$datos = tep_db_query($sql);

		if (tep_db_num_rows($datos)) {
			$dato = tep_db_fetch_array($datos);
			return (float)$dato['final_price'];
		} else {
			return 0.00;
		}
	}
}

if (!function_exists('stock_en_atributos')) {
	function stock_en_atributos($opcion, $valor, $pID) {
		$sql = "select products_stock_quantity from products_stock where products_stock_attributes = '" . $opcion . "-" . $valor . "' and products_id = $pID";
		$act = tep_db_query($sql) or die($sql);
		$val = tep_db_fetch_array($act);
		return $val['products_stock_quantity'];
	}
}

// ===================================================================================
// Devolución manual en puntos (admin orders.php) — 2026-05-18
// El operador introduce el importe en € desde el panel del pedido; opcionalmente
// añade el bonus +10% (capado a +50€) y se acreditan puntos al cliente.
// El handler está en orders.php case 'refund-points'.
// ===================================================================================
if (!function_exists('buttonDevolverPuntos')) {
function buttonDevolverPuntos($oID) {
    $oID = (int) $oID;
    if ($oID <= 0) return '';

    // Total del pedido IVA inc.
    $q = tep_db_query(sprintf('SELECT value FROM orders_total WHERE orders_id=%d AND class="ot_total" LIMIT 1', $oID));
    $r = tep_db_fetch_array($q);
    if (!$r) return '';
    $total = round((float) $r['value'], 2);

    // Cliente del pedido + saldo actual
    $q = tep_db_query(sprintf('SELECT o.customers_id, c.customers_shopping_points
        FROM orders o LEFT JOIN customers c ON c.customers_id = o.customers_id
        WHERE o.orders_id = %d LIMIT 1', $oID));
    $r = tep_db_fetch_array($q);
    if (!$r || !$r['customers_id']) return '';
    $saldoActual = (int) round((float) $r['customers_shopping_points']);

    $rate = (float) (defined('REDEEM_POINT_VALUE') ? REDEEM_POINT_VALUE : 0.05);
    if ($rate <= 0) $rate = 0.05;
    $puntosBase = (int) round($total / $rate);

    $rateStr      = number_format($rate, 2, ',', '.');
    $totalStr     = number_format($total, 2, '.', '');
    $saldoStr     = number_format($saldoActual, 0, ',', '.');
    $puntosStr    = number_format($puntosBase, 0, ',', '.');
    $actionUrl    = tep_href_link('orders.php', 'action=refund-points&oID=' . $oID);

    ob_start();
    ?>
    <div style="margin-top:14px;padding:10px;background:#eaf6fb;border:1px solid #c0d8e8;border-radius:4px;font-family:Arial,sans-serif">
      <p style="margin:0 0 4px;font-weight:bold;color:#155a78;font-size:13px">Devolver en puntos</p>
      <p style="margin:0 0 8px;font-size:11px;color:#666">Saldo actual del cliente: <strong><?php echo $saldoStr; ?> pts</strong>. Conversión: 1 pt = <?php echo $rateStr; ?> €.</p>
      <form method="post" action="<?php echo $actionUrl; ?>" id="refund-points-form" style="margin:0;padding:0;border:none">
        <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin:0 0 6px">
          <input type="number" step="0.01" min="0" name="amount" id="refund-points-amount"
                 value="<?php echo $totalStr; ?>"
                 style="text-align:right;width:90px;height:auto;margin:0;padding:5px;border:1px solid #aaa;border-radius:3px"
                 autocomplete="off" />
          <span style="font-size:12px;color:#666">€</span>
          <span style="font-size:12px;color:#888">=</span>
          <strong id="refund-points-preview" style="color:#155a78;font-size:13px"><?php echo $puntosStr; ?> pts</strong>
        </div>
        <label style="display:block;font-size:11px;color:#444;margin:4px 0 8px;cursor:pointer">
          <input type="checkbox" name="bonus" id="refund-points-bonus" value="1" style="vertical-align:middle" />
          Aplicar bonus <strong>+10%</strong> (máx. +50€)
        </label>
        <button type="submit" class="buttonS"
                style="background:#155a78;color:#fff;border:none;padding:6px 14px;border-radius:3px;cursor:pointer;font-weight:bold;font-size:12px">
          Devolver en puntos
        </button>
      </form>
      <script>
      (function(){
        var pv = <?php echo json_encode($rate); ?>;
        var maxBonus = 50.00;
        var $amount = document.getElementById('refund-points-amount');
        var $bonus  = document.getElementById('refund-points-bonus');
        var $prev   = document.getElementById('refund-points-preview');
        var $form   = document.getElementById('refund-points-form');
        function update(){
          var v = parseFloat($amount.value) || 0;
          var bEur = $bonus.checked ? Math.min(v * 0.10, maxBonus) : 0;
          var pts = Math.round((v + bEur) / pv);
          var html = pts.toLocaleString('es-ES') + ' pts';
          if (bEur > 0) html += ' <span style="color:#888;font-weight:normal;font-size:11px">(+' + bEur.toFixed(2).replace('.', ',') + '€ bonus)</span>';
          $prev.innerHTML = html;
        }
        $amount.addEventListener('input', update);
        $bonus.addEventListener('change', update);
        $form.addEventListener('submit', function(e){
          var v = parseFloat($amount.value) || 0;
          var bEur = $bonus.checked ? Math.min(v * 0.10, maxBonus) : 0;
          var pts = Math.round((v + bEur) / pv);
          if (pts <= 0) { e.preventDefault(); alert('Indica un importe > 0'); return; }
          if (!confirm('¿Devolver ' + pts.toLocaleString('es-ES') + ' pts al cliente del pedido #<?php echo $oID; ?>?')) { e.preventDefault(); }
        });
      })();
      </script>

      <?php
      // Listado de devoluciones en puntos previas hechas a este pedido (desde auditoría)
      $qLog = tep_db_query("
          SELECT created_at, admin_email, points_delta, comment,
                 JSON_UNQUOTE(JSON_EXTRACT(data_after, '$.amount_eur')) AS amount_eur,
                 JSON_UNQUOTE(JSON_EXTRACT(data_after, '$.bonus_eur'))  AS bonus_eur
          FROM customers_points_audit
          WHERE action = 'order_refund_points'
            AND CAST(JSON_UNQUOTE(JSON_EXTRACT(data_after, '$.orders_id')) AS UNSIGNED) = " . $oID . "
          ORDER BY created_at DESC
          LIMIT 50
      ");
      $nLog = tep_db_num_rows($qLog);
      if ($nLog > 0):
          $sumPts = 0; $sumEur = 0.0;
      ?>
      <div style="margin-top:12px;padding-top:10px;border-top:1px solid #c0d8e8">
        <p style="margin:0 0 6px;font-weight:bold;font-size:12px;color:#155a78">
          Devoluciones en puntos previas (<?php echo $nLog; ?>):
        </p>
        <table style="width:100%;border-collapse:collapse;font-size:11px">
          <thead>
            <tr style="background:#d4e8f5;color:#155a78">
              <th align="left"  style="padding:4px 6px">Fecha</th>
              <th align="left"  style="padding:4px 6px">Admin</th>
              <th align="right" style="padding:4px 6px">Δ pts</th>
              <th align="right" style="padding:4px 6px">Importe €</th>
              <th align="right" style="padding:4px 6px">Bonus €</th>
            </tr>
          </thead>
          <tbody>
          <?php while ($rLog = tep_db_fetch_array($qLog)):
              $sumPts += (int) $rLog['points_delta'];
              $sumEur += (float) $rLog['amount_eur'];
          ?>
            <tr style="border-bottom:1px solid #e0e8ee">
              <td style="padding:4px 6px;color:#555;white-space:nowrap"><?php echo htmlspecialchars($rLog['created_at']); ?></td>
              <td style="padding:4px 6px;color:#555"><?php echo htmlspecialchars((string) $rLog['admin_email']); ?></td>
              <td align="right" style="padding:4px 6px;color:#2a7a2a;font-weight:bold;white-space:nowrap">+<?php echo number_format((int) $rLog['points_delta'], 0, ',', '.'); ?></td>
              <td align="right" style="padding:4px 6px;color:#555;white-space:nowrap"><?php echo $rLog['amount_eur'] !== null ? number_format((float) $rLog['amount_eur'], 2, ',', '.') . '€' : '—'; ?></td>
              <td align="right" style="padding:4px 6px;color:<?php echo ((float) $rLog['bonus_eur'] > 0) ? '#155a78' : '#aaa'; ?>;white-space:nowrap"><?php echo ((float) $rLog['bonus_eur'] > 0) ? '+' . number_format((float) $rLog['bonus_eur'], 2, ',', '.') . '€' : '—'; ?></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
          <tfoot>
            <tr style="background:#eaf6fb;font-weight:bold;color:#155a78">
              <td colspan="2" style="padding:4px 6px">Total devuelto en puntos</td>
              <td align="right" style="padding:4px 6px">+<?php echo number_format($sumPts, 0, ',', '.'); ?></td>
              <td align="right" style="padding:4px 6px"><?php echo number_format($sumEur, 2, ',', '.'); ?>€</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
}
