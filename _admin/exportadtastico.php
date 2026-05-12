<?php
/* 
  $Id: exportorders.php,v 1.1 April 21, 2006 Harris Ahmed $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2004 Oscommerce

  Use this module on your own risk. I will be updating a new one soon. This template is used to create
  the csv export for Ideal Computer Systems Accounting Software
*/


require('includes/application_top.php'); 
require(DIR_WS_LANGUAGES . $language . '/exportadtastico.php');

// Check if the form is submitted
if (!$_GET['submitted'])
{
?>

<?php require(THEME . 'html/header.php'); ?>

<table border="0" width="100%" cellspacing="2" cellpadding="2">
  <tr>
    <td width="<?php echo BOX_WIDTH; ?>" valign="top"><table border="0" width="<?php echo BOX_WIDTH; ?>" cellspacing="1" cellpadding="1" class="columnLeft">
        <!-- left_navigation //-->
        <?php require(DIR_WS_INCLUDES . 'column_left.php'); ?>
        <!-- left_navigation_eof //-->
      </table></td>
    <!-- body_text //-->
    <td width="100%" valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
        <tr>
          <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
              <tr>
                <td class="pageHeading"><?php echo ADTASTICO_HEADING_TITLE; ?></td>
                <td class="pageHeading" align="right"></td>
              </tr>
            </table></td>
        </tr>
        <!-- first ends // -->
        <tr>
          <td><table border="0" style="font-family:tahoma;font-size:11px;" width="100%" cellspacing="2" cellpadding="2">
              <tr>
                <td><form method="GET" action="<?php echo $PHP_SELF; ?>">
                    <table border="0" style="font-family:tahoma;font-size:11px;" cellpadding="3">
                      <tr>
                        <td><input type="submit" value="<?php echo ADTASTICO_INPUT_VALID; ?>"></td>
                      </tr>
                    </table>
                    <input type="hidden" name="submitted" value="1">
                  </form></td>
              </tr>
              <tr>
                <td><?php echo ADTASTICO_INPUT_DESC; ?></td>
              </tr>
              <tr>
                <td>&nbsp;</td>
              </tr>
              <tr>
                <td>&nbsp;</td>
              </tr>
            </table></td>
        </tr>
      </table></td>
  </tr>
</table>

<?php require(THEME . 'html/footer.php'); ?>

<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
<?php
}
// submitted so generate csv if the form is submitted
else
{

generatecsv();
}

// generates csv file from $start order to $end order, inclusive
function generatecsv()
{
$already_sent = array();

// Detect default currency
$query_currency = tep_db_query("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'DEFAULT_CURRENCY'");
$row_currency = tep_db_fetch_array( $query_currency );

// Get default currency value
$query_currency_rate = tep_db_query("SELECT value FROM " . TABLE_CURRENCIES . " WHERE code = '" . addslashes($row_currency['configuration_value']) . "'");
$row_currency_rate = tep_db_fetch_array( $query_currency_rate );

// Detect default language code
$query_language_code = tep_db_query("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'DEFAULT_LANGUAGE'");
$row_language_code = tep_db_fetch_array( $query_language_code );

// Detect default language ID
$query_language_id = tep_db_query("SELECT languages_id FROM " . TABLE_LANGUAGES . " WHERE code = '" . $row_language_code['configuration_value'] . "'");
$row_language_id = tep_db_fetch_array( $query_language_id );

// Get all categories
$categories_query = tep_db_query("SELECT * 
FROM (" . TABLE_CATEGORIES . ") 
LEFT JOIN " . TABLE_CATEGORIES_DESCRIPTION . " ON ( " . TABLE_CATEGORIES_DESCRIPTION . ".categories_id = " . TABLE_CATEGORIES . ".categories_id AND " . TABLE_CATEGORIES_DESCRIPTION . ".language_id = '" . $row_language_id['languages_id'] . "')"
);

while( $row_cat = tep_db_fetch_array( $categories_query ) ) {
	foreach ($row_cat as $i=>$v) {
		$CAT_ARR[$row_cat['categories_id']][$i] = $v;
	}
}

// Grab the products
$products_query = tep_db_query("SELECT
manuf.manufacturers_name AS manufacturer,
prd.products_id AS id,
prd.products_id AS mpc,
prd.products_model AS mpn,
prd.products_quantity AS quantity,
prdsc.products_name AS name,
prdsc.products_description AS description,
prd.products_price AS price,
prd.products_tax_class_id,
prd.products_image,
prdtocat.categories_id

FROM (" . TABLE_PRODUCTS . " prd,
" . TABLE_PRODUCTS_DESCRIPTION . " AS prdsc,
" . TABLE_PRODUCTS_TO_CATEGORIES . " AS prdtocat)
LEFT JOIN " . TABLE_MANUFACTURERS . " AS manuf ON ( manuf.manufacturers_id = prd.manufacturers_id )
WHERE 
( prd.products_id = prdsc.products_id AND prdsc.language_id = '" . $row_language_id['languages_id'] . "' )
AND prd.products_id = prdtocat.products_id
AND prd.products_status = 1 
ORDER BY prdtocat.categories_id DESC
LIMIT 10000");

// Check for any applicable specials for the corresponding products_id
$specials_query = tep_db_query("SELECT
" . TABLE_SPECIALS . ".products_id AS idS,
" . TABLE_SPECIALS . ".specials_new_products_price AS priceS
FROM
" . TABLE_SPECIALS . ",
" . TABLE_PRODUCTS . "
WHERE
" . TABLE_SPECIALS . ".products_id = " . TABLE_PRODUCTS . ".products_id
AND " . TABLE_SPECIALS . ".status = 1 
AND " . TABLE_PRODUCTS . ".products_status = 1");

while( $row_s = tep_db_fetch_array( $specials_query ) )
{
	foreach ($row_s as $i=>$v) {
		$SPECIALS[$row_s['idS']][$i] = $v;
	}
}

$header_line = "Sku Name,Model,Manufacturer,In Stock,Price,Category,Url";
$csv_output = $header_line;
$csv_output .= "\n";

// Print the products
while( $row = tep_db_fetch_array( $products_query ) )
{
	// If we've sent this one, skip the rest - this is to ensure that we do not get duplicate products
	if ($already_sent[$row['mpc']] == 1) continue;
	
	$row['product_url'] = HTTP_SERVER . DIR_WS_CATALOG . 'product_info.php?products_id=' . $row['id'];
	if($row['products_image']) {
		
		if (preg_match("/http\:\/\//", $row['products_image'])) {
			$row['image_url'] = $row['products_image'];
		}
		elseif (preg_match("/http\:\/\//", DIR_WS_IMAGES)) {
			$row['image_url'] = DIR_WS_IMAGES . $row['products_image'];
		}
		elseif (preg_match("/http\:\/\//", DIR_WS_CATALOG)) {
			$row['image_url'] = DIR_WS_CATALOG . DIR_WS_IMAGES . $row['products_image'];
		}
		else {
			$row['image_url'] = HTTP_SERVER . DIR_WS_CATALOG . DIR_WS_IMAGES . $row['products_image'];
		}		
	}	
	
  // Datafeed specific settings
  $datafeed_separator = ",";

	// Reset the products price to our special price if there is one for this product
	if( $applyspecial == "on" && $SPECIALS[$row['id']]['idS'] ){
		$row['price'] = $SPECIALS[$row['id']]['priceS'];
	}
	   
	// Clean product name (new lines)	
	$row['name'] = str_replace("\n", " ", strip_tags($row['name']));		
	$row['name'] = str_replace("\r", "", strip_tags($row['name']));
	$row['name'] = str_replace("\t", " ", strip_tags($row['name']));
	
	// Clean product description (Replace new line with <BR>). In order to make sure the code does not contains other HTML code it might be a good ideea to strip_tags()	
    $row['description'] = str_replace("\n", "<BR>", $row['description']);
	$row['description'] = str_replace("\r", "", $row['description']);
	$row['description'] = str_replace("\t", " ", $row['description']);
	
	// Clean product names and descriptions (separators)
	if ($datafeed_separator == "\t") {
		// Continue... tabs were already removed
	}
	elseif ($datafeed_separator == ",") {
		$row['mpn'] = str_replace(",", " ", strip_tags($row['mpn']));
		$row['name'] = str_replace(",", " ", strip_tags($row['name']));
		$row['description'] = str_replace(",", " ", $row['description']);
	}
	else {
		print "Incorrect columns separator.";
		exit;			
	}
	
	// Get category name
	$category_name = get_full_cat($row['categories_id'], $CAT_ARR);
	
	// Apply currency exchange rates
	$row['price'] = number_format($row_currency_rate['value'] * $row['price'], 2);
		
	$csv_output .=
		$row['name'] . $datafeed_separator .
		$row['mpn'] . $datafeed_separator .
		$row['manufacturer'] . $datafeed_separator .
		$row['quantity'] . $datafeed_separator .
		$row['price'] . $datafeed_separator .
		$category_name . $datafeed_separator .
		$row['product_url'];
	$csv_output .= "\n";
	$already_sent[$row['mpc']] = 1;
}

print
header("Content-Type: application/force-download\n");
header("Cache-Control: cache, must-revalidate");   
header("Pragma: public");
header("Content-Disposition: attachment; filename=adtasticofeed_" . date("Ymd") . ".csv");

print $csv_output; 
exit;
}//function main

function filter_text($text) {
$filter_array = array(",","\r","\n","\t");
return str_replace($filter_array,"",$text);
} // function for the filter

// Function to get category with full path
function get_full_cat($cat_id, $CATEGORY_ARR) {

	$item_arr = $CATEGORY_ARR[$cat_id];
	$cat_name = $item_arr['categories_name'];
	
	while( isset( $CATEGORY_ARR[$item_arr['parent_id']] ) && sizeof($CATEGORY_ARR[$item_arr['parent_id']]) > 0 && is_array($CATEGORY_ARR[$item_arr['parent_id']]) ) {
		
		$cat_name = $CATEGORY_ARR[$item_arr['parent_id']]['categories_name'] . " > " . $cat_name;		
		$item_arr = $CATEGORY_ARR[$item_arr['parent_id']];
	}
	
	// Strip html from category name
	$cat_name = html_to_text($cat_name);
	
	return $cat_name;
}

function html_to_text($string){

	$search = array (
		"'<script[^>]*?>.*?</script>'si",  // Strip out javascript
		"'<[\/\!]*?[^<>]*?>'si",  // Strip out html tags
		"'([\r\n])[\s]+'",  // Strip out white space
		"'&(quot|#34);'i",  // Replace html entities
		"'&(amp|#38);'i",
		"'&(lt|#60);'i",
		"'&(gt|#62);'i",
		"'&(nbsp|#160);'i",
		"'&(iexcl|#161);'i",
		"'&(cent|#162);'i",
		"'&(pound|#163);'i",
		"'&(copy|#169);'i",
		"'&(reg|#174);'i",
		"'&#8482;'i",
		"'&#149;'i",
		"'&#151;'i",
		"'&#(\d+);'"
		);  // evaluate as php
	
	$replace = array (
		" ",
		" ",
		"\\1",
		"\"",
		"&",
		"<",
		">",
		" ",
		"&iexcl;",
		"&cent;",
		"&pound;",
		"&copy;",
		"&reg;",
		"<sup><small>TM</small></sup>",
		"&bull;",
		"-",
		"uchr(\\1)"
		);
	
	$text = preg_replace ($search, $replace, $string);
	return $text;
	
}


?>
