<?php
// Current EP Version
define ('EP_CURRENT_VERSION', '2.8-231');

require('includes/application_top.php');
require_once('includes/database_tables.php');
/*
$aDatos = tep_db_query( 'SELECT products_id
FROM products_description
WHERE products_name = "nuevo desde importador"' );

while( $aDato = tep_db_fetch_array($aDatos) )
{
	echo $aDato['products_id'];
	tep_remove_product($aDato['products_id']);
}*/

if (!function_exists('getCombobox'))
{
	// Devolvemos el array creado por defecto, si no los datos del SQL
	function getCombobox($sSql, $aPropiedades = true)
	{
		$bArray = (empty( $aPropiedades['ARRAY'] ) ? true : $aPropiedades['ARRAY'] );
		$aAddArray = (empty( $aPropiedades['ADD'] ) ? array() : array( $aPropiedades['ADD'] ) );

		// Descomponemos el SQL
		$aAux = explode( '^', $sSql );
		$aDatos = tep_db_query( 'select ' . $aAux[1] . ' as id, ' . $aAux[2] . ' as text from ' . $aAux[0] . (isset( $aAux[4] ) ? ' where ' . $aAux[4] : '') . ' order by ' . $aAux[2] . (isset( $aAux[3] ) ? $aAux[3] : ' asc') );
		
		// Si tenemos que devolver un array
		if( $bArray )
		{
			$aAux = $aAddArray;
			while( $aDato = tep_db_fetch_array( $aDatos ) )
				$aAux[] = array( 'id' => $aDato['id'], 'text' => $aDato['text'] );

			return $aAux;
		}
		else
			return $aDatos;
	}
}

if (!function_exists('tep_get_uploaded_file')){
function tep_get_uploaded_file($filename) {
	if (isset($_FILES[$filename])) {
		$uploaded_file = array('name' => $_FILES[$filename]['name'],
		'type' => $_FILES[$filename]['type'],
		'size' => $_FILES[$filename]['size'],
		'tmp_name' => $_FILES[$filename]['tmp_name']);
	} elseif (isset($_FILES[$filename])) {
		$uploaded_file = array('name' => $_FILES[$filename]['name'],
		'type' => $_FILES[$filename]['type'],
		'size' => $_FILES[$filename]['size'],
		'tmp_name' => $_FILES[$filename]['tmp_name']);
	} else {
		$uploaded_file = array('name' => $GLOBALS[$filename . '_name'],
		'type' => $GLOBALS[$filename . '_type'],
		'size' => $GLOBALS[$filename . '_size'],
		'tmp_name' => $GLOBALS[$filename]);
	}

return $uploaded_file;
}
}

// the $filename parameter is an array with the following elements:
// name, type, size, tmp_name
function tep_copy_uploaded_file($filename, $target) {
	if (substr($target, -1) != '/') $target .= '/';

	$target .= $filename['name'];

	move_uploaded_file($filename['tmp_name'], $target);
}

////
// Recursively go through the categories and retreive all sub-categories IDs
// TABLES: categories
if (!function_exists('tep_get_sub_categories')) {
  function tep_get_sub_categories(&$categories, $categories_id) {
    $sub_categories_query = tep_db_query("select categories_id from " . TABLE_CATEGORIES . " where parent_id = '" . (int)$categories_id . "'");
    while ($sub_categories = tep_db_fetch_array($sub_categories_query)) {
      if ($sub_categories['categories_id'] == 0) return true;
      $categories[sizeof($categories)] = $sub_categories['categories_id'];
      if ($sub_categories['categories_id'] != $categories_id) {
        tep_get_sub_categories($categories, $sub_categories['categories_id']);
      }
    }
  }
}

if (!function_exists('tep_get_tax_class_rate')){
function tep_get_tax_class_rate($tax_class_id) {
	$tax_multiplier = 0;
	$tax_query = tep_db_query("select SUM(tax_rate) as tax_rate from " . TABLE_TAX_RATES . " WHERE  tax_class_id = '" . $tax_class_id . "' GROUP BY tax_priority");
	if (tep_db_num_rows($tax_query)) {
		while ($tax = tep_db_fetch_array($tax_query)) {
			$tax_multiplier += $tax['tax_rate'];
		}
	}
	return $tax_multiplier;
}
}

if (!function_exists('tep_get_tax_title_class_id')){
function tep_get_tax_title_class_id($tax_class_title) {
	$classes_query = tep_db_query("select tax_class_id from " . TABLE_TAX_CLASS . " WHERE tax_class_title = '" . $tax_class_title . "'" );
	$tax_class_array = tep_db_fetch_array($classes_query);
	$tax_class_id = $tax_class_array['tax_class_id'];
	return $tax_class_id ;
}
}

if (!function_exists('print_el')){
function print_el( $item2 ) {
	return " | " . substr(strip_tags($item2), 0, 10);
}
}

if (!function_exists('print_el1')){
function print_el1( $item2 ) {
	echo sprintf("| %'.4s ", substr(strip_tags($item2), 0, 80));
}
}

$system = tep_get_system_information();

//
//*******************************
//*******************************
// C O N F I G U R A T I O N
// V A R I A B L E S
//*******************************
//*******************************

// Obtenemos los datos
$aDatos = tep_db_query( 'select configuration_key, configuration_value from configuration where configuration_group_id = 6504' );
$aOpciones = array();

while( $aDato = tep_db_fetch_array( $aDatos ) )
	$aOpciones[$aDato['configuration_key']] = $aDato['configuration_value'];


// Sampedro: Campo identificador si products_id o products_model
define( 'EP_IDENTIFICADOR', $aOpciones['dximport_identificador'] );
// Sampedro debug
define( 'EP_DEBUG', false );
// Sampedro nombre del archivo
$sNameFileExport = 'csv-products.php';


//////////////////////////////////////////////////////
// *** Show all these settings on EP main page ***
// use this to debug your settings. Copy the settings 
// to your post on the forum if you need help.
//////////////////////////////////////////////////////
define ('EP_SHOW_EP_SETTINGS', false); // default is: false


// **** Temp directory **** 
/* ////////////////////////////////////////////////////////////////////////
//
// *IF* you changed your directory structure from stock and do not 
// have /catalog/temp/, then you'll need to change this accordingly.
//
// *IF* your shop is in the default /catalog/ installation directory
// on your website, skip this Temp Directory settings info.
//
///////////////////////////////////////////////////////////////////////////

   CREATING THE TEMP DIRECTORY

   If your shop is in the root of your public site ( /home/myaccount/public_html/index.php ), 
   you should create a folder called temp from the root of your web space so that the
   full path looks like this: /home/myaccount/public_html/temp/
   
   Then you must set the permissions to 777. If you don't know how, ask your host.


   THE DIR_FS_DOCUMENT_ROOT SETTING

   DIR_FS_DOCUMENT_ROOT is set in your /catalog/admin/includes/configure.php
   You should look at the setting DIR_FS_DOCUMENT_ROOT setting.
   if it looks like this (recommended, but doesn't always work): 

       define ('DIR_FS_DOCUMENT_ROOT', $DOCUMENT_ROOT);

   then leave it alone. If it looks like this: 

       define ('DIR_FS_DOCUMENT_ROOT', '/home/myaccount/public_html'); 

   ask your host if the "/home/myaccount/public_html" portion points to your public
   web space and is correct. Whether you add the trailing slash on the
   path or not doesn't matter to this contrib, as long as you make the 
   right choice on the following setting. The best thing is to leave it
   alone as long as your host can confirm it is correct and everything else
   is working fine. Having said that, NO trailing slash is technically correct.



   THE DIR_WS_CATALOG & DIR_FS_CATALOG SETTINGS
   
   DIR_WS_CATALOG & DIR_FS_CATALOG are set in your /catalog/admin/includes/configure.php
   They may look like this if your shop is in the root of your web space. 
   If you have something different, don't just change it to this. 
   There is probably a good reason. I'm providing this as a reference 
   to you-all. The DIR_FS_DOCUMENT_ROOT, the DIR_WS_CATALOG, and the 
   DIR_FS_CATALOG settings all combine to create the temp location below.
   
       define('DIR_WS_CATALOG', '/');
       define('DIR_FS_CATALOG', DIR_FS_DOCUMENT_ROOT . DIR_WS_CATALOG);



   THIS EP_TEMP_DIRECTORY SETTING

   Next, the following setting should set so that the DIR_FS_CATALOG setting
   plus this following setting makes a correct full path to your temporary 
   location, like this: /home/myaccount/public_html/temp/

   if /home/myaccount/public_html/temp/ is the correct full path to your temp
   location, then: 

       define ('EP_TEMP_DIRECTORY', DIR_FS_CATALOG . 'temp/');

   is the correct setting here.  Wow, I really hope this stops the forum traffic about this !!

////////////////////////////////////////////////////////////////////////// */
// **** Temp directory **** 
define ('EP_TEMP_DIRECTORY', DIR_FS_CATALOG . 'temp/'); 


//**** File Splitting Configuration ****
// we attempt to set the timeout limit longer for this script to avoid having to split the files
// NOTE:  If your server is running in safe mode, this setting cannot override the timeout set in php.ini
// uncomment this if you are not on a safe mode server and you are getting timeouts
// set_time_limit(330);

// if you are splitting files, this will set the maximum number of records to put in each file.
// if you set your php.ini to a long time, you can make this number bigger
define ('EP_SPLIT_MAX_RECORDS', 300);  // default, seems to work for most people.  Reduce if you hit timeouts
//define ('EP_SPLIT_MAX_RECORDS', 4); // for testing


//**** Image Defaulting ****
// set them to your own default "We don't have any picture" gif
//define ('EP_DEFAULT_IMAGE_MANUFACTURER', 'no_image_manufacturer.gif'); 
//define ('EP_DEFAULT_IMAGE_PRODUCT', 'no_image_product.gif'); 
//define ('EP_DEFAULT_IMAGE_CATEGORY', 'no_image_category.gif'); 

// or let them get set to nothing
define ('EP_DEFAULT_IMAGE_MANUFACTURER', ''); 
define ('EP_DEFAULT_IMAGE_PRODUCT', ''); 
define ('EP_DEFAULT_IMAGE_CATEGORY', ''); 

//**** Large Images osc 2.3.1 Supportg ****
define ('EP_PRODUCTS_IMAGES', false);  // default is false
define ('EP_PRODUCTS_IMAGES_MAX', 6);  // default is 6, maximum number of columns

//**** Status Field Setting ****
// Set the v_status field to "Inactive" if you want the status=0 in the system
define ('EP_TEXT_ACTIVE', 'Active'); 
define ('EP_TEXT_INACTIVE', 'Inactive'); 

// Set the v_status field to "Delete" if you want to remove the item from the system
define ('EP_DELETE_IT', 'Delete');


// If zero_qty_inactive is true, then items with zero qty will automatically be inactive in the store.
define ('EP_INACTIVATE_ZERO_QUANTITIES', false);  // default is false

//**** Price includes tax? ****
// Set the EP_PRICE_WITH_TAX to
// false if you want the price that is exported to be the same value as stored in the database (no tax added).
// true if you want the tax to be added to the export price and subtracted from the import price.
define ('EP_PRICE_WITH_TAX', ($aOpciones['dximport_tax'] == 'true' ? true : false));


//**** Price calculation precision ****
// NOTE: when entering into the database all prices will be converted to 4 decimal places.
define ('EP_PRECISION', 4);  // default is 2


// **** Quote -> Escape character conversion ****
// If you have extensive html in your descriptions and it's getting mangled on upload, turn this off
// set to true = replace quotes with escape characters
// set to false = no quote replacement
define ('EP_REPLACE_QUOTES', false);  // default is false


// **** Field Separator ****
// change this if you can't use the default of tabs
// Tab is the default, comma and semicolon are commonly supported by various progs
// Remember, if your descriptions contain this character, you will confuse EP!
// if EP_EXCEL_SAFE_OUTPUT if false (below) you must make EP_PRESERVE_TABS_CR_LF false also.
$ep_separator = "\t"; // tab is default
//$ep_separator = ',';  // comma
//$ep_separator = ';';  // semi-colon
//$ep_separator = '~';  // tilde
//$ep_separator = '*';  // splat


// *** Excel safe output ***
// this setting will supersede the previous $ep_separator setting and create a file
// that excel will import without spanning cells from embedded commas or tabs in your products.
// if EP_EXCEL_SAFE_OUTPUT if false (below) you must make EP_PRESERVE_TABS_CR_LF false also.
define ('EP_EXCEL_SAFE_OUTPUT', true); // default is: true

if (EP_EXCEL_SAFE_OUTPUT == true) { 
  if ($language == 'english') { 
    $ep_separator = ',';  // comma
  } elseif ($language == 'german') {
    $ep_separator = ';';  // semi-colon
  } else {
    $ep_separator = ',';  // comma  // default for all others.
  }
}

// if EP_EXCEL_SAFE_OUTPUT if true (above) there is an alternative line parsing routine
//  provided by Maynard that will use a manual php approach.  There is a bug in some
// PHP versions that may require you to use this routine.  This should also provide proper
// parsing when quotes are used within a string. I suspect this should also resolve an issue
// recently reported in which characters with a german "Umlaute" like ÄäÖöÜü at the Beginning 
// of some text, they will disappear when importing some csv-file, reported by TurboTB.
define ('EP_EXCEL_SAFE_OUTPUT_ALT_PARCE', false); // default is: false


// *** Preserve Tabs, Carriage returns and Line feeds ***
// this setting will preserve the special chars that can cause problems in 
// a text based output. When used with EP_EXCEL_SAFE_OUTPUT, it will safely
// preserve these elements in the export and import.
define ('EP_PRESERVE_TABS_CR_LF', false); // default is: false


// **** Max Category Levels ****
// change this if you need more or fewer categories.
// set this to the maximum depth of your categories.

// Obtenemos cual categoria tiene el mayor nivel
function getCategoriesMaxLevel()
{
	// Variables
	$aCategorias = array();
	$aReturn = array();
	$nCheck = 0;
	$nCheckAnterior = 0;

	// Obtenemos todas las categorias para no realizar muchas consultas
	$aDatos = tep_db_query( 'select c.categories_id, c.parent_id, cd.categories_name
							from categories c
							inner join categories_description cd on(cd.categories_id = c.categories_id)
							where cd.language_id = 3
							order by c.parent_id desc' );
	
	$nTotal = tep_db_num_rows( $aDatos );

					
	// Volcamos todas las categorias en un array para operar con el
	while ( $aDato = tep_db_fetch_array( $aDatos ) )
		$aCategorias[$aDato['categories_id']] = $aDato;
	
	// Hasta que no procesemos todas las categorias estaremos dando vueltas
	while(true)
	{
		// Recorremos categorias
		foreach( $aCategorias as $aCategoria )
		{
			// Si ya tenemos la categoria añadida, continuamos
			if( array_key_exists( $aCategoria['categories_id'], $aReturn ) )
				continue;

			// Si la categoria es la padre la insertamos directamente
			if( $aCategoria['parent_id'] == 0 )
			{
				$sNombre = $aCategoria['categories_name'];
				$nLevel = 1;
			}
			else
			{
				// Comprobamos si el padre esta insertado para obtener su nombre, si no continuamos
				if( array_key_exists( $aCategoria['parent_id'], $aReturn ) )
				{	
					// Obtenemos el padre que ya ha sido insertado con sus respectivos hijos
					$aAuxCategoriaPadre = $aReturn[$aCategoria['parent_id']]['breadcrumb'];
					// Concatenamos el padre junto a la categoria
					$sNombre = $aAuxCategoriaPadre . ' [dxsepare] ' . $aCategoria['categories_name'];
					
					$nLevel = count( explode( ' [dxsepare] ', $sNombre ) );
				}
				else
					continue;
			}
			
			// Insertamos
			$aReturn[$aCategoria['categories_id']] = array( 'breadcrumb' => trim($sNombre) , 'level' => $nLevel );
			$nCheck++;
		}

		$nCheckAnterior = $nCheck;
		
		// Si hemos procesado todas las categorias paramos
		if( $nCheck == $nTotal )
			break;
	}
	
	$nNivelMaximo = 0;
	
	// Recorremos en busca del nivel mayor
	foreach( $aReturn as $aAux )
		if( $aAux['level'] > $nNivelMaximo )
			$nNivelMaximo = $aAux['level'];
	
	// Devolvemos el nivel
	return $nNivelMaximo;
}


define( 'EP_MAX_CATEGORIES', getCategoriesMaxLevel() );


// VJ product attributes begin
// **** Product Attributes ****
// change this to false, if do not want to download product attributes
define ('EP_PRODUCTS_WITH_ATTRIBUTES', false);  // default is true

// change this to true, if you use QTYpro and want to set attributes stock with EP.
define ('EP_PRODUCTS_ATTRIBUTES_STOCK', false); // default is false

// change this if you want to download only selected product options (attributes).
// If you have a lot of product options, and your output file exceeds 256 columns, 
// which is the max. limit MS Excel is able to handle, then load-up this array with
// attributes to skip when generating the export.
$attribute_options_select = '';
// $attribute_options_select = array('Size', 'Model'); // uncomment and fill with product options name you wish to download // comment this line, if you wish to download all product options
// VJ product attributes end


// ******************************************************************
// BEGIN Define Custom Fields for your products database
// ******************************************************************
// the following line is always left as is.
$custom_fields = array();
//
// The following setup will allow you to define any additional 
// field into the "products" and "products_description" tables
// in your shop. If you have  installed a custom contribution
// that adds fields to these tables you may simply and easily add
//
// ********************
// ** products table **
// Lets say you have added a field to your "products" table called
// "products_upc". The header name in your import file will be
// called "v_products_upc".  Then below you will change the line
// that looks like this (without the comment double-slash at the beginning):
// $custom_fields[TABLE_PRODUCTS] = array(); // this line is used if you have no custom fields to import/export
//
// TO:
// $custom_fields[TABLE_PRODUCTS] = array( 'products_upc' => 'UPC' );
//
// If you have multiple fields this is what it would look like:
// $custom_fields[TABLE_PRODUCTS] = array( 'products_upc' => 'UPC', 'products_restock_quantity' => 'Restock' );
//
// ********************************
// ** products_description table **
// Lets say you have added a field to your "products_description" table called
// "products_short_description". The header name in your import file will be
// called "v_products_short_description_1" for English, "v_products_short_description_2" for German,
// "v_products_short_description_3" for Spanish. Other languages will vary. Be sure to use the 
// langugage ID of the custom language you installed if it is other then the original
// 3 installed languages of osCommerce. If you are unsure what language ID you need to
// use, do a complete export and examine the file headers produces.
//
// Then below you will change the line that looks like this (without the comment double-slash at the beginning):
// $custom_fields[TABLE_PRODUCTS_DESCRIPTION] = array(); // this line is used if you have no custom fields to import/export
//
// TO:
// $custom_fields[TABLE_PRODUCTS_DESCRIPTION] = array( 'products_short_description' => 'short' );
//
// If you have multiple fields this is what it would look like:
// $custom_fields[TABLE_PRODUCTS_DESCRIPTION] = array( 'products_short_description' => 'short', 'products_viewed' => 'Viewed' );
//
// the array format is: array( 'table_field_name' => 'Familiar Name' )
// the array key ('table_field_name') is always the exact name of the 
// field in the table. The array value ('Familiar Name') is any text
// name that will be used in the custom EP export download checkbox.
//
// I believe this will only work for text/varchar and numeric field
// types.  If your custom field is a date/time or any other type, you
// may need to incorporate custom code to correctly import your data.
//

$aAux = json_decode( $aOpciones['dximport_extra'], true );
$custom_fields[TABLE_PRODUCTS] = $aAux; // this line is used if you have no custom fields to import/export
$custom_fields[TABLE_PRODUCTS_DESCRIPTION] = array(); // this line is used if you have no custom fields to import/export

//
// FINAL NOTE: this currently only works with the "products" & "products_description" table.
// If it works well and I don't get a plethora of problems reported,
// I may expand it to more tables. Feel free to make requests, but
// as always, only as me free time allows.
//
// ******************************************************************
// END Define Custom Fields for your products database
// ******************************************************************



// ****************************************
// Froogle configuration variables
// Here are some links regarding Bulk uploads
// http://www.google.com/base/attributes.html
// http://www.google.com/base/help/custom-attributes.html
// ****************************************

// **** Froogle product info page path ****
// We can't use the tep functions to create the link, because the links will point to the 
// admin, since that's where we're at. So put the entire path to your product_info.php page here
define ('EP_FROOGLE_PRODUCT_INFO_PATH', HTTP_CATALOG_SERVER . DIR_WS_CATALOG . "product_info.php");

// **** Froogle product image path ****
// Set this to the path to your images directory
define ('EP_FROOGLE_IMAGE_PATH', HTTP_CATALOG_SERVER . DIR_WS_CATALOG_IMAGES);

// **** Froogle - search engine friendly setting
// if your store has SEARCH ENGINE FRIENDLY URLS set, then turn this to true
// I did it this way because I'm having trouble with the code seeing the constants
// that are defined in other places.
define ('EP_FROOGLE_SEF_URLS', false);  // default is false

// **** Froogle Currency Setting
define ('EP_FROOGLE_CURRENCY', 'USD');  // default is 'USD'

// ****************************************
// End: Froogle configuration variables
// 


// ***********************************
// *** Other Contributions Support ***
// ***********************************

// More Pics 6 v1.3
define ('EP_MORE_PICS_6_SUPPORT', true);  // default is false

// Header Tags Controller Support v2.0
define ('EP_HTC_SUPPORT', false);  // default is false

// Separate Pricing Per Customer (SPPC) v4.1.x
define ('EP_SPPC_SUPPORT', ($aOpciones['dximport_grupo_cliente'] == 'true' ? true : false));  // default is false

// X-Sell 2.6 Support
define ('EP_XSELL_SUPPORT', false);  // default is false

// Additional Images v2.1.1
define ('EP_ADDITIONAL_IMAGES', false);  // default is false
define ('EP_ADDITIONAL_IMAGES_MAX', 6);  // default is 6, maximum number of columns

// Multi Vendor System (MVS) 1.2 support
define ('EP_MVS_SUPPORT', false);  // default is false

// Extra Fields Contribution 
define ('EP_EXTRA_FIELDS_SUPPORT', false);  // default is false

// UltraPics 2.05 LightBox Contrib (***FUNCTIONAL***)
define ('EP_ULTRPICS_SUPPORT', false);  // default is false

// PDF File Upload and Display v2.01 (***FUNCTIONAL***)
define ('EP_PDF_UPLOAD_SUPPORT', false);  // default is false

// Master Products for v2.3x 2.0
define ('MASTER_PRODUCTS_SUPPORT', false);  // default is false

//*******************************
//*******************************
// E N D
// C O N F I G U R A T I O N
// V A R I A B L E S
//*******************************
//*******************************





//*******************************
//*******************************
// S T A R T
// INITIALIZATION
//*******************************
//*******************************

// modify tableBlock for use here.
  class epbox extends tableBlock {
    // constructor
    function __construct($contents, $direct_ouput = true) {
      $this->table_width = '';
      if (!empty($contents) && $direct_ouput == true) {
        echo $this->tableBlock($contents);
      }
    }
    // only member function
    function output($contents) {
      return $this->tableBlock($contents);
    }
  }

if (!empty($languages_id) && !empty($language)) {
  define ('EP_DEFAULT_LANGUAGE_ID', $languages_id);
  define ('EP_DEFAULT_LANGUAGE_NAME', $language);
} else {
  //elari check default language_id from configuration table DEFAULT_LANGUAGE
  $epdlanguage_query = tep_db_query("select languages_id, name from " . TABLE_LANGUAGES . " where code = '" . DEFAULT_LANGUAGE . "'");
  if (tep_db_num_rows($epdlanguage_query) > 0) {
    $epdlanguage = tep_db_fetch_array($epdlanguage_query);
    define ('EP_DEFAULT_LANGUAGE_ID', $epdlanguage['languages_id']);
    define ('EP_DEFAULT_LANGUAGE_NAME', $epdlanguage['name']);
  } else {
    echo 'Strange but there is no default language to work... That may not happen, just in case... ';
  }
}

$languages = tep_get_languages();

// VJ product attributes begin
$attribute_options_array = array();

if (EP_PRODUCTS_WITH_ATTRIBUTES == true) {
    if (is_array($attribute_options_select) && (count($attribute_options_select) > 0)) {
        foreach ($attribute_options_select as $value) {
            $attribute_options_query = "select distinct products_options_id from " . TABLE_PRODUCTS_OPTIONS . " where products_options_name = '" . $value . "'";

            $attribute_options_values = tep_db_query($attribute_options_query);

            if ($attribute_options = tep_db_fetch_array($attribute_options_values)){
                $attribute_options_array[] = array('products_options_id' => $attribute_options['products_options_id']);
            }
        }
    } else {
        $attribute_options_query = "select distinct products_options_id from " . TABLE_PRODUCTS_OPTIONS . " order by products_options_id";

        $attribute_options_values = tep_db_query($attribute_options_query);

        while ($attribute_options = tep_db_fetch_array($attribute_options_values)){
            $attribute_options_array[] = array('products_options_id' => $attribute_options['products_options_id']);
        }
    }
}
// VJ product attributes end


// these are the fields that will be defaulted to the current values in 
// the database if they are not found in the incoming file
$default_these = array();
foreach ($languages as $key => $lang){
  // $default_these[] = 'v_products_name_' . $lang['id'];
  // $default_these[] = 'v_products_description_' . $lang['id'];
  // $default_these[] = 'v_products_url_' . $lang['id'];
  foreach ($custom_fields[TABLE_PRODUCTS_DESCRIPTION] as $key => $name) { 
    $default_these[] = 'v_' . $key . '_' . $lang['id'];
  }
  if (EP_HTC_SUPPORT == true) {
    $default_these[] = 'v_products_head_title_tag_' . $lang['id'];
    $default_these[] = 'v_products_head_desc_tag_' . $lang['id'];
    $default_these[] = 'v_products_head_keywords_tag_' . $lang['id'];
  }
}
$default_these[] = 'v_products_image';
if (is_array($custom_fields[TABLE_PRODUCTS]) || is_object($custom_fields[TABLE_PRODUCTS])){
foreach ($custom_fields[TABLE_PRODUCTS] as $key => $name) { 
  $default_these[] = 'v_' . $key;
	}
}
// build images fields. Note: this building limited!
if (EP_PRODUCTS_IMAGES == true) { 
  //$default_these[] = 'v_products_images_htmlcontent';
  for ($i=1;$i<=EP_PRODUCTS_IMAGES_MAX+1;$i++) {
        $default_these[] = 'v_products_images_image_' . $i;
        $default_these[] = 'v_products_images_htmlcontent_' . $i;
  }
}
if (EP_MVS_SUPPORT == true) { 
  $default_these[] = 'v_vendor';
}
if (EP_ADDITIONAL_IMAGES == true) { 
  $default_these[] = 'v_products_image_description';
  for ($i=2;$i<=EP_ADDITIONAL_IMAGES_MAX+1;$i++) {
    $default_these[] = 'v_products_image_' . $i;
    $default_these[] = 'v_products_image_description_' . $i;
  }
}
if (EP_MORE_PICS_6_SUPPORT == true) { 
  $default_these[] = 'v_products_subimage1';
  $default_these[] = 'v_products_subimage2';
  $default_these[] = 'v_products_subimage3';
  $default_these[] = 'v_products_subimage4';
  $default_these[] = 'v_products_subimage5';
  $default_these[] = 'v_products_subimage6';
}
if (EP_ULTRPICS_SUPPORT == true) { 
  $default_these[] = 'v_products_image_med';
  $default_these[] = 'v_products_image_lrg';
  $default_these[] = 'v_products_image_sm_1';
  $default_these[] = 'v_products_image_xl_1';
  $default_these[] = 'v_products_image_sm_2';
  $default_these[] = 'v_products_image_xl_2';
  $default_these[] = 'v_products_image_sm_3';
  $default_these[] = 'v_products_image_xl_3';
  $default_these[] = 'v_products_image_sm_4';
  $default_these[] = 'v_products_image_xl_4';
  $default_these[] = 'v_products_image_sm_5';
  $default_these[] = 'v_products_image_xl_5';
  $default_these[] = 'v_products_image_sm_6';
  $default_these[] = 'v_products_image_xl_6';
}

if (EP_PDF_UPLOAD_SUPPORT == true) { 
  $default_these[] = 'v_products_pdfupload';
  $default_these[] = 'v_products_fileupload';
}

if (MASTER_PRODUCTS_SUPPORT == true) { 
  $default_these[] = 'v_products_model_id';
  $default_these[] = 'v_products_master';
  $default_these[] = 'v_products_master_status';  
}

$default_these[] = 'v_categories_id';
$default_these[] = 'v_products_price';
$default_these[] = 'v_products_quantity';
$default_these[] = 'v_products_weight';
$default_these[] = 'v_status_current';
$default_these[] = 'v_date_avail';
$default_these[] = 'v_date_added';
$default_these[] = 'v_tax_class_title';
$default_these[] = 'v_manufacturers_name';
$default_these[] = 'v_manufacturers_id';

$filelayout = '';
$filelayout_count = '';
$filelayout_sql = '';
$fileheaders = '';



if ( !empty($_GET['dltype']) ) {
    // if dltype is set, then create the filelayout.  Otherwise it gets read from the uploaded file
    list($filelayout, $filelayout_count, $filelayout_sql, $fileheaders) = ep_create_filelayout($_GET['dltype'], $attribute_options_array, $languages, $custom_fields); // get the right filelayout for this download
}

//*******************************
//*******************************
// E N D
// INITIALIZATION
//*******************************
//*******************************




//*******************************
//*******************************
// DOWNLOAD FILE (EXPORT)
//*******************************
//*******************************
if ( !empty($_GET['download']) && ($_GET['download'] == 'stream' or $_GET['download'] == 'activestream' or $_GET['download'] == 'tempfile') ){
    $filestring = ""; // this holds the csv file we want to download
	
    $result = tep_db_query($filelayout_sql);
    $row =  tep_db_fetch_array($result);

    // $EXPORT_TIME=time();  // start export time when export is started.
    $EXPORT_TIME = date('YMd-Hi');
    if ($dltype=="froogle"){
        $EXPORT_TIME = "FroogleEP" . $EXPORT_TIME;
    } else {
        $EXPORT_TIME = "EP" . $EXPORT_TIME;
    }

    // Here we need to allow for the mapping of internal field names to external field names
    // default to all headers named like the internal ones
    // the field mapping array only needs to cover those fields that need to have their name changed
    if ( is_array($fileheaders) && count($fileheaders) != 0 ){
        $filelayout_header = $fileheaders; // if they gave us fileheaders for the dl, then use them
    } else {
        $filelayout_header = $filelayout; // if no mapping was spec'd use the internal field names for header names
    }
	
    //We prepare the table heading with layout values
    foreach( $filelayout_header as $key => $value ){
        $filestring .= $key . $ep_separator;
    }
    // now lop off the trailing tab
    $filestring = substr($filestring, 0, strlen($filestring)-1);

    // set the type
    if ( $dltype == 'froogle' ){
        $endofrow = "\n";
    } else {
        // default to normal end of row
        $endofrow = $ep_separator . 'EOREOR' . "\n";
    }
    $filestring .= $endofrow;

    if ($_GET['download'] == 'activestream'){
//		header('Content-Transfer-Encoding: none');
        if (EP_EXCEL_SAFE_OUTPUT == true) {

		if( !EP_DEBUG )
			header('Content-type: text/x-csv; charset="utf-8"');
			//header("Content-type: text/x-csv");
		
//		header("Content-type: application/vnd.ms-excel");
//		header('Content-type: application/vnd.ms-excel; charset="utf-8"');
		} else {
		header("Content-type: text/plain");
		}
		
		if( !EP_DEBUG )
			header("Content-disposition: attachment; filename=$EXPORT_TIME" . ((EP_EXCEL_SAFE_OUTPUT == true)?".csv":".txt"));
      // Changed if using SSL, helps prevent program delay/timeout (add to backup.php also)
      if ($request_type== 'NONSSL'){
        header("Pragma: no-cache");
      } else {
        header("Pragma: ");
      }
      header("Expires: 0");

      echo $filestring;
    }

    $num_of_langs = count($languages);
    while ($row){


        // if the filelayout says we need a products_name, get it
        // build the long full froogle image path
        $row['v_products_fullpath_image'] = EP_FROOGLE_IMAGE_PATH . $row['v_products_image'];
        // Other froogle defaults go here for now
        $row['v_froogle_quantitylevel']     = $row['v_products_quantity'];
        $row['v_froogle_manufacturer_id']   = '';
        $row['v_froogle_exp_date']          = date('Y-m-d', strtotime('+30 days'));
        $row['v_froogle_product_type']      = $row['v_categories_id'];
        $row['v_froogle_product_id']        = $row['v_products_model'];
        $row['v_froogle_currency']          = EP_FROOGLE_CURRENCY;
        
        // names and descriptions require that we loop thru all languages that are turned on in the store
        foreach ($languages as $key => $lang){
            $lid = $lang['id'];

            // for each language, get the description and set the vals
            $sql2 = "SELECT *
                FROM ".TABLE_PRODUCTS_DESCRIPTION."
                WHERE
                    products_id = " . $row['v_products_id'] . " AND
                    language_id = '" . $lid . "'
                ";
            $result2 = tep_db_query($sql2);
            $row2 =  tep_db_fetch_array($result2);

            // I'm only doing this for the first language, since right now froogle is US only.. Fix later!
            // adding url for froogle, but it should be available no matter what
            if (EP_FROOGLE_SEF_URLS == true){
                // if only one language
                if ($num_of_langs == 1){
                    $row['v_froogle_products_url_' . $lid] = EP_FROOGLE_PRODUCT_INFO_PATH . '/products_id/' . $row['v_products_id'];
                } else {
                    $row['v_froogle_products_url_' . $lid] = EP_FROOGLE_PRODUCT_INFO_PATH . '/products_id/' . $row['v_products_id'] . '/language/' . $lid;
                }
            } else {
                // if ($num_of_langs == 1){
                    // $row['v_froogle_products_url_' . $lid] = EP_FROOGLE_PRODUCT_INFO_PATH . '?products_id=' . $row['v_products_id'];
                // } else {
                    // $row['v_froogle_products_url_' . $lid] = EP_FROOGLE_PRODUCT_INFO_PATH . '?products_id=' . $row['v_products_id'] . '&language=' . $lid;
                // }
            }

            $row['v_products_name_' . $lid]          = $row2['products_name'];
            $row['v_products_description_' . $lid]   = $row2['products_description'];
            // $row['v_products_url_' . $lid]           = $row2['products_url'];
            foreach ($custom_fields[TABLE_PRODUCTS_DESCRIPTION] as $key => $name) { 
              $row['v_' . $key . '_' . $lid]           = $row2[$key];
            }
            // froogle advanced format needs the quotes around the name and desc
            $row['v_froogle_products_name_' . $lid] = '"' . strip_tags(str_replace('"','""',$row2['products_name'])) . '"';
            $row['v_froogle_products_description_' . $lid] = '"' . strip_tags(str_replace('"','""',$row2['products_description'])) . '"';

            // support for Linda's Header Controller 2.0 here
            if(isset($filelayout['v_products_head_title_tag_' . $lid])){
                $row['v_products_head_title_tag_' . $lid]     = $row2['products_head_title_tag'];
                $row['v_products_head_desc_tag_' . $lid]      = $row2['products_head_desc_tag'];
                $row['v_products_head_keywords_tag_' . $lid]  = $row2['products_head_keywords_tag'];
            }
            // end support for Header Controller 2.0
		
        }
						// get data from products images table
						if (EP_PRODUCTS_IMAGES == true) {
							$i = 1;
							$pi_result = tep_db_query("SELECT * FROM ".TABLE_PRODUCTS_IMAGES."  WHERE products_id = " . $row['v_products_id'] . " limit " . EP_PRODUCTS_IMAGES_MAX);
							while ($pi_row = tep_db_fetch_array($pi_result)) {
								$row['v_products_images_image_'.$i] = $pi_row['image'];
								$row['v_products_images_htmlcontent_'.$i] = $pi_row['htmlcontent'];
								$i++;
							}
						}

            if (EP_ADDITIONAL_IMAGES == true) {
              $i = 2;
              $ai_result = tep_db_query("SELECT * FROM ".TABLE_ADDITIONAL_IMAGES."  WHERE products_id = " . $row['v_products_id'] . " limit " . EP_ADDITIONAL_IMAGES_MAX);
              while ($ai_row = tep_db_fetch_array($ai_result)) {
                $row['v_products_image_'.$i] = (!empty($ai_row['popup_images'])?$ai_row['popup_images']:$ai_row['thumb_images']);
                $row['v_products_image_description_'.$i] = $ai_row['images_description'];
                $i++;
              }
            }
		
		
            if (EP_MVS_SUPPORT == true) { 
              $vend_result = tep_db_query("select vendors_name from ".TABLE_VENDORS." where vendors_id = " . $row['v_vendor_id'] . "");
              if ($vend_row = tep_db_fetch_array($vend_result)) {
                $row['v_vendor'] = $vend_row['vendors_name'];
              }
            }

		// for the categories, we need to keep looping until we find the root category
		// start with v_categories_id
		// Get the category description
		// set the appropriate variable name
		// if parent_id is not null, then follow it up.
		// we'll populate an aray first, then decide where it goes in the
		$thecategory_id = $row['v_categories_id'];
		$fullcategory = ''; // this will have the entire category stack for froogle
		for( $categorylevel=1; $categorylevel<=EP_MAX_CATEGORIES; $categorylevel++){
			if ($thecategory_id){

				$sql3 = "SELECT parent_id, 
								categories_image
						 FROM ".TABLE_CATEGORIES."
						 WHERE    
								categories_id = " . $thecategory_id . '';
				$result3 = tep_db_query($sql3);
				if ($row3 = tep_db_fetch_array($result3)) {
					$temprow['v_categories_image_' . $categorylevel] = $row3['categories_image'];
				}

				foreach ($languages as $key => $lang){
					$sql2 = "SELECT categories_name
							 FROM ".TABLE_CATEGORIES_DESCRIPTION."
							 WHERE    
									categories_id = " . $thecategory_id . " AND
									language_id = " . $lang['id'];
					$result2 = tep_db_query($sql2);
					if ($row2 =  tep_db_fetch_array($result2)) {
						$temprow['v_categories_name_' . $categorylevel . '_' . $lang['id']] = $row2['categories_name'];
					}
					if ($lang['id'] == '1') {
						//$fullcategory .= " > " . $row2['categories_name'];
						$fullcategory = $row2['categories_name'] . " > " . $fullcategory;
					}

				}

				// now get the parent ID if there was one
				$theparent_id = $row3['parent_id'];
				if ($theparent_id != ''){
					// there was a parent ID, lets set thecategoryid to get the next level
					$thecategory_id = $theparent_id;
				} else {
					// we have found the top level category for this item,
					$thecategory_id = false;
				}
 
			} else {
				$temprow['v_categories_image_' . $categorylevel] = '';
				foreach ($languages as $key => $lang){
					$temprow['v_categories_name_' . $categorylevel . '_' . $lang['id']] = '';
				}
			}
		}

		// now trim off the last ">" from the category stack
		$row['v_category_fullpath'] = substr($fullcategory,0,strlen($fullcategory)-3);

		// temprow has the old style low to high level categories.
		$newlevel = 1;
		// let's turn them into high to low level categories
		for( $categorylevel=EP_MAX_CATEGORIES; $categorylevel>0; $categorylevel--){
			$found = false;
			if ($temprow['v_categories_image_' . $categorylevel] != ''){
				$row['v_categories_image_' . $newlevel] = $temprow['v_categories_image_' . $categorylevel];
				$found = true;
			}
			foreach ($languages as $key => $lang){
				if ($temprow['v_categories_name_' . $categorylevel . '_' . $lang['id']] != ''){
					$row['v_categories_name_' . $newlevel . '_' . $lang['id']] = $temprow['v_categories_name_' . $categorylevel . '_' . $lang['id']];
					$found = true;
				}
			}
			if ($found == true) {
				$newlevel++;
			}
		}


        // if the filelayout says we need a manufacturers name, get it
        if (isset($filelayout['v_manufacturers_name'])){
            if ($row['v_manufacturers_id'] != ''){
                $sql2 = "SELECT manufacturers_name
                    FROM ".TABLE_MANUFACTURERS."
                    WHERE
                    manufacturers_id = " . $row['v_manufacturers_id']
                    ;
                $result2 = tep_db_query($sql2);
                $row2 =  tep_db_fetch_array($result2);
                $row['v_manufacturers_name'] = $row2['manufacturers_name'];
            }
        }


        // If you have other modules that need to be available, put them here

        // VJ product attribs begin
        if (isset($filelayout['v_attribute_options_id_1'])){

            $attribute_options_count = 1;
      foreach ($attribute_options_array as $attribute_options) {
                $row['v_attribute_options_id_' . $attribute_options_count]     = $attribute_options['products_options_id'];

                for ($i=0, $n=sizeof($languages); $i<$n; $i++) {
                    $lid = $languages[$i]['id'];

                    $attribute_options_languages_query = "select products_options_name from " . TABLE_PRODUCTS_OPTIONS . " where products_options_id = '" . (int)$attribute_options['products_options_id'] . "' and language_id = '" . (int)$lid . "'";

                    $attribute_options_languages_values = tep_db_query($attribute_options_languages_query);

                    $attribute_options_languages = tep_db_fetch_array($attribute_options_languages_values);

                    $row['v_attribute_options_name_' . $attribute_options_count . '_' . $lid] = $attribute_options_languages['products_options_name'];
                }

                $attribute_values_query = "select products_options_values_id from " . TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS . " where products_options_id = '" . (int)$attribute_options['products_options_id'] . "' order by products_options_values_id";

                $attribute_values_values = tep_db_query($attribute_values_query);

                $attribute_values_count = 1;
                while ($attribute_values = tep_db_fetch_array($attribute_values_values)) {
                    $row['v_attribute_values_id_' . $attribute_options_count . '_' . $attribute_values_count]     = $attribute_values['products_options_values_id'];

                    $attribute_values_price_query = "select options_values_price, price_prefix from " . TABLE_PRODUCTS_ATTRIBUTES . " where products_id = '" . (int)$row['v_products_id'] . "' and options_id = '" . (int)$attribute_options['products_options_id'] . "' and options_values_id = '" . (int)$attribute_values['products_options_values_id'] . "'";

                    $attribute_values_price_values = tep_db_query($attribute_values_price_query);

                    $attribute_values_price = tep_db_fetch_array($attribute_values_price_values);

                    $row['v_attribute_values_price_' . $attribute_options_count . '_' . $attribute_values_count]     = $attribute_values_price['price_prefix'] . $attribute_values_price['options_values_price'];

    //// attributes stock add start        
    if ( EP_PRODUCTS_ATTRIBUTES_STOCK    == true ) {   
           $stock_attributes = $attribute_options['products_options_id'].'-'.$attribute_values['products_options_values_id'];
           
           $stock_quantity_query = tep_db_query("select products_stock_quantity from " . TABLE_PRODUCTS_STOCK . " where products_id = '" . (int)$row['v_products_id'] . "' and products_stock_attributes = '" . $stock_attributes . "'");
           $stock_quantity = tep_db_fetch_array($stock_quantity_query);
           
           $row['v_attribute_values_stock_' . $attribute_options_count . '_' . $attribute_values_count] = $stock_quantity['products_stock_quantity'];
     }
    //// attributes stock add end  
                    
                    
                    for ($i=0, $n=sizeof($languages); $i<$n; $i++) {
                        $lid = $languages[$i]['id'];

                        $attribute_values_languages_query = "select products_options_values_name from " . TABLE_PRODUCTS_OPTIONS_VALUES . " where products_options_values_id = '" . (int)$attribute_values['products_options_values_id'] . "' and language_id = '" . (int)$lid . "'";

                        $attribute_values_languages_values = tep_db_query($attribute_values_languages_query);

                        $attribute_values_languages = tep_db_fetch_array($attribute_values_languages_values);

                        $row['v_attribute_values_name_' . $attribute_options_count . '_' . $attribute_values_count . '_' . $lid] = $attribute_values_languages['products_options_values_name'];
                    }

                    $attribute_values_count++;
                }

                $attribute_options_count++;
            }
        }
        // VJ product attribs end

        // this is for the cross sell contribution
        if (isset($filelayout['v_cross_sell'])){
            $px_models = '';
			$sql2 = "SELECT
                    px.products_id,
                    px.xsell_id,
					p.products_model
                FROM
                    ".TABLE_PRODUCTS_XSELL." px, 
					".TABLE_PRODUCTS." p
                WHERE
                px.xsell_id = p.products_id and 
                px.products_id = " . $row['v_products_id'] . "
                ";
            $cross_sell_result = tep_db_query($sql2);
			while( $cross_sell_row = tep_db_fetch_array($cross_sell_result) ){
				$px_models .= $cross_sell_row['products_model'] . ',';
			}
			if (strlen($px_models) > 0) { $px_models = substr($px_models, 0, -1); }
			$row['v_cross_sell'] = $px_models;
		}

		$row_tax_multiplier         = tep_get_tax_class_rate($row['v_tax_class_id']);

        // this is for the separate price per customer module
        if (isset($filelayout['v_customer_price_1'])){
            $sql2 = "SELECT
                    customers_group_price,
                    customers_group_id
                FROM
                    ".TABLE_PRODUCTS_GROUPS."
                WHERE
                products_id = " . $row['v_products_id'] . "
                ORDER BY
                customers_group_id"
                ;
            $result2 = tep_db_query($sql2);
            $ll = 1;
            $row2 =  tep_db_fetch_array($result2);

            // do pricing specials
            if (isset($filelayout['v_customer_specials_price_1'])){

              $sppc_specials = array();
              $specials_result = tep_db_query("SELECT specials_new_products_price, status, customers_group_id FROM ".TABLE_SPECIALS." WHERE products_id = " . $row['v_products_id'] . " and customers_group_id <> 0 and expires_date < CURRENT_TIMESTAMP ORDER BY specials_id DESC");
              while( $specials_result_row = tep_db_fetch_array($specials_result) ){
				$sppc_specials[$specials_result_row['customers_group_id']] = $specials_result_row['specials_new_products_price'] + (EP_PRICE_WITH_TAX == true ? round( ($specials_result_row['specials_new_products_price'] * $row_tax_multiplier / 100), EP_PRECISION) : 0);
                // $sppc_specials[$specials_result_row['customers_group_id']] = $specials_result_row['specials_new_products_price'];
              }
            }
			
            while( $row2 ){
                $row['v_customer_group_id_' . $ll]     = $row2['customers_group_id'];
                // $row['v_customer_price_' . $ll]     = $row2['customers_group_price'];
				$row['v_customer_price_' . $ll] = $row2['customers_group_price'] + (EP_PRICE_WITH_TAX == true ? round( ($row2['customers_group_price'] * $row_tax_multiplier / 100), EP_PRECISION) : 0);
                // do pricing specials
                if (isset($filelayout['v_customer_specials_price_1'])){
                  $row['v_customer_specials_price_' . $ll]     = $sppc_specials[$ll];
                }
                $row2 = tep_db_fetch_array($result2);
                $ll++;
            }
            // do pricing specials
            $specials_result = tep_db_query("SELECT specials_new_products_price, status FROM ".TABLE_SPECIALS." WHERE products_id = " . $row['v_products_id'] . " and customers_group_id <> 0 and expires_date < CURRENT_TIMESTAMP ORDER BY specials_id DESC");
            if( $specials_result_row = tep_db_fetch_array($specials_result) ){			
              if ($specials_result_row['status'] == 1) {
                $row['v_products_specials_price']     = $specials_result_row['specials_new_products_price'];
              } else {
                $row['v_products_specials_price']     = $specials_result_row['specials_new_products_price'];
              }
            } else {
              $row['v_products_specials_price']     = '';
            }

        }

        if ($dltype == 'froogle'){
            // For froogle, we check the specials prices for any applicable specials, and use that price
            // by grabbing the specials id descending, we always get the most recently added special price
            // I'm checking status because I think you can turn off specials
            $sql2 = "SELECT
                    specials_new_products_price
                FROM
                    ".TABLE_SPECIALS."
                WHERE
                products_id = " . $row['v_products_id'] . " and
                status = 1 and
                expires_date < CURRENT_TIMESTAMP
                ORDER BY
                    specials_id DESC"
                ;
            $result2 = tep_db_query($sql2);
            $ll = 1;
            $row2 =  tep_db_fetch_array($result2);
            if( $row2 ){
                // reset the products price to our special price if there is one for this product
                $row['v_products_price']     = $row2['specials_new_products_price'];
            }
        }

        //elari -
        //We check the value of tax class and title instead of the id
        //Then we add the tax to price if EP_PRICE_WITH_TAX is set to true
        $row['v_tax_class_title']   = tep_get_tax_class_title($row['v_tax_class_id']);
        $row['v_products_price']    = $row['v_products_price'] + (EP_PRICE_WITH_TAX == true ? round( ($row['v_products_price'] * $row_tax_multiplier / 100), EP_PRECISION) : 0);

			
        // do pricing specials
        if (EP_SPPC_SUPPORT == true) { $SPPC_extra_query = 'and customers_group_id = 0 '; } else { $SPPC_extra_query = ''; }
        if (isset($filelayout['v_products_specials_price'])){
            $specials_result = tep_db_query("SELECT specials_new_products_price, status FROM ".TABLE_SPECIALS." WHERE products_id = " . $row['v_products_id'] . " " . $SPPC_extra_query . "and (expires_date < CURRENT_TIMESTAMP or expires_date is null) ORDER BY specials_id DESC");
            if( $specials_result_row = tep_db_fetch_array($specials_result) ){
              if ($specials_result_row['status'] == 1) {
                $row['v_products_specials_price'] = $specials_result_row['specials_new_products_price']  + (EP_PRICE_WITH_TAX == true ? round( ($specials_result_row['specials_new_products_price'] * $row_tax_multiplier / 100), EP_PRECISION) : 0);
              } else {
                $row['v_products_specials_price'] = $specials_result_row['specials_new_products_price']  + (EP_PRICE_WITH_TAX == true ? round( ($specials_result_row['specials_new_products_price'] * $row_tax_multiplier / 100), EP_PRECISION) : 0);
              }
            } else {
              $row['v_products_specials_price']     = '';
            }
        }

        // Now set the status to a word the user specd in the config vars
        if ( $row['v_status'] == '1' ){
            $row['v_status'] = EP_TEXT_ACTIVE;
        } else {
            $row['v_status'] = EP_TEXT_INACTIVE;
        }

        // remove any bad things in the texts that could confuse
        $therow = '';
        foreach( $filelayout as $key => $value ){
            //echo "The field was $key<br />";

            $thetext = $row[$key];
            // kill the carriage returns and tabs in the descriptions, they're killing me!
            if (EP_PRESERVE_TABS_CR_LF == false || $dltype == 'froogle') {
              $thetext = str_replace("\r",' ',$thetext);
              $thetext = str_replace("\n",' ',$thetext);
              $thetext = str_replace("\t",' ',$thetext);
            }
            if (EP_EXCEL_SAFE_OUTPUT == true && $dltype != 'froogle') {
              // use quoted values and escape the embedded quotes for excel safe output.
              $therow .= '"'.str_replace('"','""',$thetext).'"' . $ep_separator;
            } else {
              // and put the text into the output separated by $ep_separator defined above
              $therow .= $thetext . $ep_separator;
            }
        }

        // lop off the trailing separator, then append the end of row indicator
        $therow = substr($therow,0,strlen($therow)-1) . $endofrow;

        if ($_GET['download'] == 'activestream'){
          echo $therow;
        } else {
          $filestring .= $therow;
        }
        // grab the next row from the db
        $row =  tep_db_fetch_array($result);
    }

    // now either stream it to them or put it in the temp directory
    if ($_GET['download'] == 'activestream'){
        die();
    } elseif ($_GET['download'] == 'stream'){
        //*******************************
        // STREAM FILE
        //*******************************
//		header('Content-Transfer-Encoding: none');
        if (EP_EXCEL_SAFE_OUTPUT == true) {
		header("Content-type: text/x-csv");
//		header('Content-type: text/x-csv; charset="utf-8"');
//		header("Content-type: application/vnd.ms-excel");
//		header('Content-type: application/vnd.ms-excel; charset="utf-8"');
		} else {
		header("Content-type: text/plain");
		}
        header("Content-disposition: attachment; filename=$EXPORT_TIME" . ((EP_EXCEL_SAFE_OUTPUT == true)?".csv":".txt"));
        // Changed if using SSL, helps prevent program delay/timeout (add to backup.php also)
        if ($request_type== 'NONSSL'){
          header("Pragma: no-cache");
        } else {
          header("Pragma: ");
        }
        header("Expires: 0");
        echo $filestring;
        die();
    } elseif ($_GET['download'] == 'tempfile') {
        //*******************************
        // PUT FILE IN TEMP DIR
        //*******************************
        $tmpfname = EP_TEMP_DIRECTORY . "$EXPORT_TIME" . ((EP_EXCEL_SAFE_OUTPUT == true)?".csv":".txt");
        //unlink($tmpfname);
        $fp = fopen( $tmpfname, "w+");
        fwrite($fp, $filestring);
        fclose($fp);
		
		// Redireccionamos			
		$messageStack->addSession( 'exportar', 'El archivo se ha creado correctamente, puedes verlo <a href="' . tep_href_link( $sNameFileExport, 'a=importar' ) . '">aquí</a> en el apartado <i>Archivos en el servidor</i>', 'success', false );
		tep_redirect( $_SERVER['HTTP_REFERER'] );
    }
} 



//*******************************
//*******************************
// S T A R T
// PAGE DELIVERY
//*******************************
//*******************************

$sAction = tep_db_prepare_input( $_GET['a'] );

// Eliminamos el log siempre que no sea la action
if( $sAction != 'importar_log' && file_exists( getcwd() . '/../temp/dx-csv-products-log.txt' ) )
	unlink( getcwd() . '/../temp/dx-csv-products-log.txt' );

if( !in_array( $sAction, array('importar_download', 'importar_log') ) && !array_key_exists('split', $_GET) )
{
	ob_start();
	include( THEME . 'html/header.php' );
}

//*******************************
//*******************************
// UPLOAD AND INSERT FILE
//*******************************
//*******************************
if (!empty($_POST['localfile']) or (isset($_FILES['usrfl']) && isset($_GET['split']) && $_GET['split']==0))
{
    if (isset($_FILES['usrfl']))
	{
		header('Cache-Control: no-cache');

        // move the file to where we can work with it
        $file = tep_get_uploaded_file('usrfl');

        if( is_uploaded_file($file['tmp_name']) )
			tep_copy_uploaded_file($file, EP_TEMP_DIRECTORY);

		// Si no hemos subido nada
		if( $file['size'] == 0 )
			die("Error");
		
        // echo "<p class=smallText>";
        // echo "File uploaded. <br />";
        // echo "Temporary filename: " . $file['tmp_name'] . "<br />";
        // echo "User filename: " . $file['name'] . "<br />";
        // echo "Size: " . $file['size'] . "<br />";
		
        // get the entire file into an array
        $readed = file(EP_TEMP_DIRECTORY . $file['name']);
    }

    if (!empty($_POST['localfile']))
	{
        // move the file to where we can work with it
        //$file = tep_get_uploaded_file('usrfl');

        //$attribute_options_query = "select distinct products_options_id from " . TABLE_PRODUCTS_OPTIONS . " order by products_options_id";
        //$attribute_options_values = tep_db_query($attribute_options_query);
        //$attribute_options_count = 1;
        //while ($attribute_options = tep_db_fetch_array($attribute_options_values)){

        //if (is_uploaded_file($file['tmp_name'])) {
        //    tep_copy_uploaded_file($file, EP_TEMP_DIRECTORY);
        //}

        // echo "<p class=smallText>";
        // echo "Filename: " . $_POST['localfile'] . "<br />";

        // get the entire file into an array
        $readed = file(EP_TEMP_DIRECTORY . $_POST['localfile']);
    }
    
	
    if (EP_EXCEL_SAFE_OUTPUT == true) 
	{
        // do excel safe input
        $fp = fopen(EP_TEMP_DIRECTORY . (isset($_FILES['usrfl'])?$file['name']:$_POST['localfile']),'r') or die('##Can not open file for reading. Script will terminate.<br />');  // open file
        // determine the separator character.
        $header_line = fgets($fp);
        if (strpos($header_line,',') !== false) { $ep_separator = ','; }
        if (strpos($header_line,';') !== false) { $ep_separator = ';'; }
        if (strpos($header_line,"\t") !== false) { $ep_separator = "\t"; }
        if (strpos($header_line,'~') !== false) { $ep_separator = '~'; }
        if (strpos($header_line,'-') !== false) { $ep_separator = '-'; }
        if (strpos($header_line,'*') !== false) { $ep_separator = '*'; }
        fclose($fp); 

        if (EP_EXCEL_SAFE_OUTPUT_ALT_PARCE == false)
		{
            unset($readed);                    // kill array setup with above code
            $readed = array();                 // start a new one for excel_safe_output
            $fp = fopen(EP_TEMP_DIRECTORY . (isset($_FILES['usrfl'])?$file['name']:$_POST['localfile']),'r') or die('##Can not open file for reading. Script will terminate.<br />');  // open file
            while($line = fgetcsv($fp,32768,$ep_separator))   // read new line (max 32K bytes)
            {
                unset($line[(sizeof($line)-1)]);  // remove EOREOR at the end of the array
				
				// sampedro: modificacion para utf8
				$aAux = implode( '[dx_separe]', $line);
				if( $sEncoding = mb_detect_encoding( $aAux, 'auto', true ) != 'UTF-8' )
				{
					$aAux = @mb_convert_encoding( $aAux, 'UTF-8', $sEncoding );
					$line = explode( '[dx_separe]', $aAux);
				}
				
                $readed[] = $line;                // add to array we will process later				
            }
            $theheaders_array = $readed[0];     // pull out header line
            fclose($fp);                        // close file
        } else {
        
            // Maynard's CSV Fix ###################################################
            // some versions of fgetcsv don't like a field beginning with "", so we'll parse it ourselves
            // use readed, but parse it ourselves according to $ep_separator, and " as field encloser
            
            // I copied this from below:
            // now we string the entire thing together in case there were carriage returns in the data
              $newreaded = "";
              foreach ($readed as $read){
                $newreaded .= $read;
              }
              // now newreaded has the entire file together without the carriage returns.
              // if for some reason excel put qoutes around our EOREOR, remove them then split into rows
              $newreaded = str_replace('"EOREOR"', 'EOREOR', $newreaded);
              $readed = explode( $ep_separator . 'EOREOR',$newreaded);
            // dump the last entry - it's blank
            unset($readed[sizeof($readed)-1]);    
            // parse it all!
            foreach ($readed as $key => $line) {
                $ppp = 0; // pointer
                $line1 = array();
                $remains = trim($line);
                while ($remains != '') {
                    if (substr($remains,0,1) == '"') {
                        // this is an enclosed portion
                        $ppp1 = -1; // so that our first loop points to the right place in $remains
                        $ppp2 = -1;
                        // we've got to find the next " that isn't ""    
                        while($ppp1 < strlen($remains) && $ppp1 == $ppp2 && $ppp1 !== FALSE) {
                            $ppp1 = strpos($remains, '"', $ppp2+2);
                            $ppp2 = strpos($remains, '""', $ppp2+2); // use ppp2 because then """ will get properly understood                
                        }
                        // now $ppp1 points to the closing "
                        // we are pulling out the determined chunk and 
                        // replacing any double quotes with single quotes 
                        // since that is how CSV handles quotes
                        $line1[] = str_replace('""','"',substr($remains,1,$ppp1-1));
                        // find the next separator - for a well formed CSV file, it MUST be a separator (and not in quoted text)
                        $ppp1 = strpos($remains, $ep_separator, $ppp1);
                        $remains = ($ppp1 !== false) ? substr($remains,$ppp1+1) : '';             
                    } else {
                        // this is an un-enclosed portion - find the next separator
                        $ppp1 = strpos($remains, $ep_separator, 0);
                        $line1[] = ($ppp1 !== false) ? substr($remains,0,$ppp1) : $remains;
                        $remains = ($ppp1 !== false) ? substr($remains,$ppp1+1) : '';                        
                    }
                }
                // replace that line with our new array
                $readed[$key] = $line1;
            }
            $theheaders_array = $readed[0];     // pull out header line    
            // EOF Maynard's CSV Fix ###################################################      
        }

    } else {
      // do normal EP input
      // now we string the entire thing together in case there were carriage returns in the data
      $newreaded = "";
      foreach ($readed as $read){
        $newreaded .= $read;
      }

      // now newreaded has the entire file together without the carriage returns.
      // if for some reason excel put qoutes around our EOREOR, remove them then split into rows
      $newreaded = str_replace('"EOREOR"', 'EOREOR', $newreaded);
      $readed = explode( $ep_separator . 'EOREOR',$newreaded);

      // Now we'll populate the filelayout based on the header row.
      $theheaders_array = explode( $ep_separator, $readed[0] ); // explode the first row, it will be our filelayout
    }
    
    $lll = 0;
    $filelayout = array();
    foreach( $theheaders_array as $header ){
        $cleanheader = str_replace( '"', '', $header);
        // echo "Fileheader was $header<br /><br /><br />";
        $filelayout[ $cleanheader ] = $lll++; //
    }
    unset($readed[0]); //  we don't want to process the headers with the data

	$flFile = fopen( getcwd() . '/../temp/dx-csv-products-log.txt', 'w' );
	
    // now we've got the array broken into parts by the expicit end-of-row marker.
	$nCont = 1;
	
    foreach( $readed as $tkey => $readed_row ) 
	{
		$aAux = process_row($readed_row, $filelayout, $filelayout_count, $default_these, $ep_separator, $languages, $custom_fields);
	
		// Mostramos solo si lo hemos procesado
		if( $aAux[0] != '' )
		{
			fwrite( $flFile, htmlentities( '<div class="dxlog" style="background: ' . $aAux[0] . '"><span>' . $nCont . '</span>' . $aAux[1] . '</div>' ) );
			++$nCont;
		}
	}

	fwrite( $flFile, 'DX_TERMINADO' );
	fclose( $flFile );
	exit();

//*******************************
//*******************************
// UPLOAD AND SPLIT FILE
//*******************************
//*******************************
} elseif (isset($_FILES['usrfl']) && isset($_GET['split']) && $_GET['split']==1) {
    // move the file to where we can work with it
    $file = tep_get_uploaded_file('usrfl');
    //echo "Trying to move file...";
    if (is_uploaded_file($file['tmp_name'])) {
        tep_copy_uploaded_file($file, EP_TEMP_DIRECTORY);
    }
	
    $infp = fopen(EP_TEMP_DIRECTORY . $file['name'], "r");

    $ext_tmp = substr( $file['name'], -3 , 3 );
	
    //toprow has the field headers
    $toprow = fgets($infp,32768);

    $filecount = 1;
	
	$sNameArchivo = preg_replace( '/\..+$/i', '', $file['name'] );

    // echo "Creating file EP_Split" . $filecount . '.'.$ext_tmp.' ...  ';
    $tmpfname = EP_TEMP_DIRECTORY . $sNameArchivo . '_' . $filecount.'.'.$ext_tmp;
    $fp = fopen( $tmpfname, "w+");
    fwrite($fp, $toprow);

    $linecount = 0;
    $line = fgets($infp,32768);
    while ($line){
        // walking the entire file one row at a time
        // but a line is not necessarily a complete row, we need to split on rows that have "EOREOR" at the end
        $line = str_replace('"EOREOR"', 'EOREOR', $line);
        fwrite($fp, $line);
        if (strpos($line, 'EOREOR')){
            // we found the end of a line of data, store it
            $linecount++; // increment our line counter
            if ($linecount >= EP_SPLIT_MAX_RECORDS){
                // echo "Added $linecount records and closing file... <br />";
                $linecount = 0; // reset our line counter
                // close the existing file and open another;
                fclose($fp);
                // increment filecount
                $filecount++;
                // echo "Creating file EP_Split" . $filecount . '.'.$ext_tmp.' ...  ';
                $tmpfname = EP_TEMP_DIRECTORY . $sNameArchivo . '_' . $filecount.'.'.$ext_tmp;
                //Open next file name
                $fp = fopen( $tmpfname, "w+");
                fwrite($fp, $toprow);
            }
        }
        $line=fgets($infp,32768);
    }
    // echo "Added $linecount records and closing file...<br /><br /> ";
    fclose($fp);
    fclose($infp);

	// Redireccionamos			
	$messageStack->addSession( 'importar', 'El archivo se ha subido correctamente, puedes verlo en el apartado <i>Archivos en el servidor</i>', 'success', false );
	tep_redirect( $_SERVER['HTTP_REFERER'] );	

	//echo "You can download your split files in the Tools/Files under " . EP_TEMP_DIRECTORY;
}


//*******************************
//*******************************
// MAIN MENU
//*******************************
//*******************************

	// Variables
	$sTitular = '';
	$sHtml = '';	
	$aFiles = scandirByDate( EP_TEMP_DIRECTORY, '/csv$/i' );
	$aTiposImportar = array(
		array( 'id' => 'normal', 'text' => 'Importar todos' ),
		array( 'id' => 'addnew', 'text' => 'Importar solo nuevos' ),
		array( 'id' => 'update', 'text' => 'Importar solo existentes' ),
	);
	$aTiposExportar =  array( 
		array( 'id' => 'activestream', 'text' => 'se descargará' ),
		array( 'id' => 'stream', 'text' => 'se creara en el servidor y se descargará' ),
		array( 'id' => 'tempfile', 'text' => 'se creara en el servidor' )
	);
	$aTipoConjunto =  array( 
		array( 'id' => 'full', 'text' => 'completo' ),
		array( 'id' => 'custom', 'text' => 'personalizado' )
	);
	$aCampos = array(
		'epcust_name' => 'Nombre',
		'epcust_description' => 'Descripción',
		'epcust_image' => 'Imagenes',
		'epcust_category' => 'Categoria',
		'epcust_manufacturer' => 'Fabricante',
		'epcust_price' => 'Precio',
		'epcust_specials_price' => 'Oferta',
		'epcust_quantity' => 'Cantidad',
		'epcust_weight' => 'Peso',
		'epcust_tax_class' => 'Impuesto',
		'epcust_avail' => 'Disponible',
		'epcust_date_added' => 'Fecha de añadido',
		'epcust_status' => 'Estado',
	);
	
	// Añadimos campos especiales si lo tenemos en las opciones
	if( EP_PRODUCTS_WITH_ATTRIBUTES == true )
		$aCampos['epcust_attributes'] = 'Atributos';

	if( EP_MVS_SUPPORT == true )
		$aCampos['epcust_vendor'] = 'Vendedor';
	
	if( EP_XSELL_SUPPORT == true ) 
		$aCampos['epcust_cross_sell'] = 'Ventas cruzadas';

	// Añadimos campos extras de la tabla products
	if (is_array($custom_fields[TABLE_PRODUCTS]) || is_object($custom_fields[TABLE_PRODUCTS]))
	{
	foreach( $custom_fields[TABLE_PRODUCTS] as $key => $name )
		$aCampos['epcust_' . $key] = $name;
	}
	// Añadimos campos extras de la tabla descripcion del producto
	foreach( $custom_fields[TABLE_PRODUCTS_DESCRIPTION] as $key => $name )
		$aCampos['epcust_' . $key] = $name;

	// Si no tenemos action sera exportar por defecto
	if( $sAction == '' )
		$sAction = 'exportar';

	// Acciones del modulo
	switch( $sAction )
	{
		case 'importar_log':
			header('Cache-Control: no-cache');

			$sHtml = '';
			
			if( file_exists( getcwd() . '/../temp/dx-csv-products-log.txt' ) )
			{		
				$flFile = fopen( getcwd() . '/../temp/dx-csv-products-log.txt', 'r' );
				$sHtml = html_entity_decode( fread($flFile, filesize( getcwd() . '/../temp/dx-csv-products-log.txt' ) ) );
				fclose( $flFile );
			}
			
			echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
				<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="es-ES">
					<head>
						<title>Zona Administrador</title>
						<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
						<meta name="language" content="es" />';
						
						if( !preg_match( '/DX_TERMINADO$/i', $sHtml ) )
							echo '<meta http-equiv="refresh" content="2;url=' . $sNameArchivo . '?a=importar_log">';
						
						echo '<link rel="stylesheet" type="text/css" href="theme/web/css/style.css"/>
						<link rel="stylesheet" type="text/css" href="theme/web/css/font.css"/>
						<link rel="stylesheet" type="text/css" href="theme/web/css/plugins.css"/>
						<style type="text/css">
							.dxlog
							{
								background: none repeat scroll 0 0 #EFEFEF;
								color: #666666;
								font-size: 11px;
								margin: 10px 10px 10px 7px;
								padding: 5px 15px;
								position: relative;
							}

							.dxlog span
							{
								background: none repeat scroll 0 0 #666666;
								border-radius: 20px 20px 20px 20px;
								color: #FFFFFF;
								display: block;
								font-weight: bold;
								left: -7px;
								line-height: 17px;
								margin-top: -8px;
								padding: 0 5px;
								position: absolute;
								top: 50%;
							}
						</style>
					</head>
					<body style="background: #FFF;">';
						echo preg_replace( '/DX_TERMINADO/i', '', $sHtml );

						if( preg_match( '/DX_TERMINADO$/i', $sHtml ) )
						{
							echo '<script type="text/javascript">
								parent.document.getElementById( "ttlr-dats" ).style.background="#FFF";
								javascript:alert("Proceso terminado");
							</script>';
						}
					echo '</body>';
				echo '</html>';		

			exit();
		break;
	
		case 'importar_download':
			header( 'Content-type: text/x-csv; charset="utf-8"' );
			header( 'Content-Disposition: attachment; filename=' . $_GET['file'] );
			header( 'Expires: 0' );
			header( 'Pragma: no-cache' );
			readfile( getcwd() . '/../temp/' . $_GET['file'] );
			die();
		break;
	
		case 'importar_delete':
			// Eliminamos
			unlink( getcwd() . '/../temp/' . $_GET['file'] );

			// Redireccionamos			
			$messageStack->addSession( 'importar', 'El archivo se ha eliminado correctamente', 'success', false );
			tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;
	
		// Ventana principal y exportador
		case 'exportar':
			// Titular
			$sTitular = 'Exportar datos';
			
			// Mensaje de aviso
			$sHtml .= $messageStack->show( array( 'class' => 'wrng', 'text' => 'Por favor, recuerde hacer una <a style="text-decoration: underline;" href="backup.php">copia de seguridad</a> de su Base de Datos antes.' ) );
			
			$sHtml .= tep_draw_form( 'custom', $sNameFileExport, (defined('SID') && tep_not_null(SID) ? tep_session_name() . '=' . tep_session_id() : ''), 'get', 'id="custom"' );
				if( defined('SID') && tep_not_null(SID) )
					echo tep_draw_hidden_field( tep_session_name(), tep_session_id() );
			
				// Opciones de exportación
				$sHtml .= '<div class="fluid grid">';
					$sHtml .= '<div class="box-tbl grid12" style="margin-top: 10px;">';
						$sHtml .= '<div class="box-head">';
							$sHtml .= '<h6>Opciones de exportación</h6>';
							$sHtml .= '<div class="clear"></div>';
						$sHtml .= '</div>';
						$sHtml .= '<div class="formRow">';
							$sHtml .= 'El archivo ' . tep_draw_pull_down_menu( 'download', $aTiposExportar, '', 'id="download"' ) . ' y este se hará ' . tep_draw_pull_down_menu( 'dltype', $aTipoConjunto, '', 'id="dltype"' ) . ' .';
						$sHtml .= '</div>';
					$sHtml .= '</div>';			
				$sHtml .= '</div>';
				
				// Campos a exportar
				$sHtml .= '<div class="fluid grid">';
					$sHtml .= '<div class="box-tbl grid12">';
						$sHtml .= '<div id="tble-bg-chck-trns"></div>';
						$sHtml .= '<div class="box-head">';
							$sHtml .= '<h6>Campos a exportar</h6>';
							$sHtml .= '<div class="clear"></div>';
						$sHtml .= '</div>';
						$sHtml .= '<div id="tble-bg-chck" class="formRow">';
								$sHtml .= '<div id="load"></div>';
								$sHtml .= '<div id="tbls">';
								
									$sHtml .= '<table cellspacing="0" cellpadding="0" border="0" width="100%">';
									
									$nCont = 0;
									
									foreach( $aCampos as $key => $value )
									{
										if( $nCont == 0 )
											$sHtml .= '<tr>';

										$sHtml .= '<td class="check">';
											$sHtml .= tep_draw_checkbox_field( $key, 'show', 'true', '', ($aDato['identificador'] == 'true' ? 'disabled="disabled"' : '') ) . '<label class="labl-chck">' . $value . '</label>' . ($aDato['identificador'] == 'true' ? '<span title="Campo identificador" class="icos-flag"></span>' : '');

											if( $aDato['identificador'] == 'true' )
												$sHtml .= '<input type="hidden" name="rows[' . $aDato['importador_id'] . ']" value="' . $aDato['importador_id'] . '" />';
										$sHtml .='</td>';
										
										if( $nCont == 7 )
										{
											$sHtml .= '</tr>';
											$nCont = 0;
										}
										else
											$nCont++;
									}

									// Si no terminamos en 0 es que hemos dejado alguna fila abierta, cerramos
									if( $nCont != 0 )
										$sHtml .= '</tr>';
									
									$sHtml .= '</table>';
								
								$sHtml .= '</div>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';	
				$sHtml .= '</div>';
				
				// Campos a filtar
				$aCategorias = array_merge( array( array( 'id' => '', 'text' => '-- Categorias --' ) ), tep_get_category_tree() );
				$aFabricantes = getCombobox( 'manufacturers^manufacturers_id^manufacturers_name', array( 'ADD' => array( 'id' => '', 'text' => '-- Fabricantes --' ) ) );
				$aEstados = array(
					array( 'id' => '', 'text' => '-- Estados --' ),
					array( 'id' => '1', 'text' => 'Activado' ),
					array( 'id' => '0', 'text' => 'Desactivado' )
				);

				$sHtml .= '<div class="fluid grid">';
					$sHtml .= '<div class="box-tbl grid12">';
						$sHtml .= '<div class="box-head">';
							$sHtml .= '<h6>Filtar por</h6>';
							$sHtml .= '<div class="clear"></div>';
						$sHtml .= '</div>';
						$sHtml .= '<div class="formRow">';
							$sHtml .= tep_draw_pull_down_menu( 'epcust_category_filter', $aCategorias ) . '&nbsp;&nbsp;&nbsp; - &nbsp;&nbsp;&nbsp;';
							$sHtml .= tep_draw_pull_down_menu( 'epcust_manufacturer_filter', $aFabricantes ) . '&nbsp;&nbsp;&nbsp; - &nbsp;&nbsp;&nbsp;';
							$sHtml .= tep_draw_pull_down_menu( 'epcust_status_filter', $aEstados, '', 'id="epcust_status_filter"' );
						$sHtml .= '</div>';
					$sHtml .= '</div>';	
				$sHtml .= '</div>';
				
				// Boton para procesar
				$sHtml .= '<div class="fluid grid" style="margin-top: 35px; text-align: right;">';
					$sHtml .= '<div class="grid12">';
						$sHtml .= '<a id="expt-head" style="height: 16px; top: -1px; position: relative; margin-right: 12px;" href="javascript:void(0);" class="buttonS bGreyish">Exportar solo cabeceras</a>';
						$sHtml .= '<input class="buttonS bGreen" type="submit" value="Exportar datos" />';
					$sHtml .= '</div>';
				$sHtml .= '</div>';
			$sHtml .= '</form>';			
		break;
		
		case 'importar':	
			// Titular
			$sTitular = 'Importar datos';
		
			// Mensaje de aviso
			$sHtml .= $messageStack->show( array( 'class' => 'wrng', 'text' => 'Por favor, recuerde hacer una <a style="text-decoration: underline;" href="backup.php">copia de seguridad</a> de su Base de Datos antes.' ) );
		
			// Iframe para procesar
			$sHtml .= '<div id="dxload-ifrme">
				<h1 id="ttlr-dats" class="ttlr" style="background: url(\'images/loading.gif\') no-repeat scroll 881px center transparent !important">Importando datos</h1>
				<iframe id="imprt-ifme-log" style="border: medium none; width: 899px; margin: 7px 0px 0px 16px; height: 277px;"></iframe>
				<iframe name="imprt-ifme" id="imprt-ifme" style="display:none;"></iframe>
			</div>';
			$sHtml .= '<div id="dxbg"></div>';
		
			// Subir y procesar
			$sHtml .= '<div class="fluid grid">';
				$sHtml .= '<div class="box-tbl grid12" style="margin-top: 10px;">';
					$sHtml .= '<div class="box-head">';
						$sHtml .= '<h6>Subir e importar</h6>';
						$sHtml .= '<div class="clear"></div>';
					$sHtml .= '</div>';
					$sHtml .= '<form id="form-imprt" ' . (EP_DEBUG ? '' : 'target="imprt-ifme"' ) . ' enctype="multipart/form-data" action="' . $sNameFileExport . '?split=0' . (defined('SID') && tep_not_null(SID) ? '&' . tep_session_name() . '=' . tep_session_id() : '') . '" method="post">';
						$sHtml .= '<div class="formRow">';			
							$sHtml .= '<div class="grid12" style="margin-top: 0px;">';

								if( defined('SID') && tep_not_null(SID) )
									$sHtml .= tep_draw_hidden_field( tep_session_name(), tep_session_id() );
								
								$sHtml .= '<input type="hidden" name="MAX_FILE_SIZE" value="100000000" />';
								$sHtml .= '<input name="usrfl" id="usrfl" type="file" size="50" />';

								$sHtml .= '<div style="position: relative; left: 10px; display: inline-block; top: 3px;">' . tep_draw_pull_down_menu( 'imput_mode', $aTiposImportar ) . '</div>';
								$sHtml .= '<div style="display: inline-block; left: 20px; position: relative; top: 1px;"><input name="buttoninsert" class="buttonS bGreen" type="submit" value="Importar datos" /></div>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="clear"></div>';
						$sHtml .= '</div>';
					$sHtml .= '</form>';
				$sHtml .= '</div>';
			$sHtml .= '</div>';

			// Subir y dividir archivo
			$sHtml .= '<div class="fluid grid">';
				$sHtml .= '<div class="box-tbl grid12">';
					$sHtml .= '<div class="box-head">';
						$sHtml .= '<h6>Subir y dividir archivo</h6>';
						$sHtml .= '<div class="clear"></div>';
					$sHtml .= '</div>';
					$sHtml .= '<form enctype="multipart/form-data" action="' . $sNameFileExport . '?split=1' . (defined('SID') && tep_not_null(SID) ? '&' . tep_session_name() . '=' . tep_session_id() : '') . '" method="post">';
						$sHtml .= '<div class="formRow">';			
							$sHtml .= '<div class="grid12" style="margin-top: 0px;">';

								if( defined('SID') && tep_not_null(SID) )
									$sHtml .= tep_draw_hidden_field( tep_session_name(), tep_session_id() );

								$sHtml .= '<input type="hidden" name="MAX_FILE_SIZE" value="100000000" />';
								$sHtml .= '<input name="usrfl" type="file" size="50" />';

								$sHtml .= '<div style="display: inline-block; left: 20px; position: relative; top: 1px;"><input class="buttonS bBlue" type="submit" value="Subir archivo" /></div>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="clear"></div>';
						$sHtml .= '</div>';
					$sHtml .= '</form>';
				$sHtml .= '</div>';
			$sHtml .= '</div>';
			
			// Si existen ficheros para importar
			if( count( $aFiles ) > 0 )
			{
				$sHtml .= '<div class="fluid grid">';
					$sHtml .= '<div class="box-tbl grid12">';
						$sHtml .= '<div class="box-head">';
							$sHtml .= '<h6>Archivos en el servidor</h6>';
							$sHtml .= '<div class="clear"></div>';
						$sHtml .= '</div>';
						$sHtml .= '<table class="tDefault" width="100%" cellspacing="0" cellpadding="0">';
							$sHtml .= '<thead>';
								$sHtml .= '<tr>';
									$sHtml .= '<td style="text-align: left;">Nombre del archivo</td>';
									$sHtml .= '<td>Tipo</td>';
									$sHtml .= '<td width="75">Acciones</td>';
								$sHtml .= '</tr>';
							$sHtml .= '</thead>';
							$sHtml .= '<tbody>';

								foreach( $aFiles as $sFile )
								{
									$sHtml .= '<tr>';
										$sHtml .= '<td>' . $sFile . '</td>';
										$sHtml .= '<td width="120">' . tep_draw_pull_down_menu( 'tipo', $aTiposImportar ) . '</td>';
										$sHtml .= '<td width="80">
											<a title="Importar archivo" class="impr" href="' . tep_href_link( $sNameFileExport, 'localfile=' . $sFile ) . '"><span class="icos-excel"></span></a>
											<a title="Descargar archivo" class="dwnl" target="_blank" href="' . tep_href_link( $sNameFileExport, 'a=importar_download&file=' . $sFile ) . '"><span class="icos-download"></span></a>
											<a title="Eliminar archivo" class="dlte" href="' . tep_href_link( $sNameFileExport, 'a=importar_delete&file=' . $sFile ) . '"><span class="elnr icos-cross"></span></a>
										</td>';
									$sHtml .= '</tr>';
								}						
							
							$sHtml .= '</tbody>';
						$sHtml .= '</table>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';
			}
		break;
		
		case 'opciones_save':
			// Variables
			$aAux = array();
		
			// Recorremos los campos
			foreach( $_POST['row'] as $key => $value )
			{
				$_POST['alias'][$key] = trim( $_POST['alias'][$key] );
				$_POST['alias'][$key] = $_POST['alias'][$key] != '' ? $_POST['alias'][$key] : $value;

				$aAux[$value] = $_POST['alias'][$key];
			}

			// Guardamos el grupo de cliente
			tep_db_perform( 'configuration', array( 'configuration_value' => json_encode( $aAux ) ), 'update', 'configuration_key = "dximport_extra"' );

			// Guardamos el grupo de cliente
			tep_db_perform( 'configuration', array( 'configuration_value' => $_POST['grupo_cliente'] ), 'update', 'configuration_key = "dximport_grupo_cliente"' );

			// Guardamos el tax
			tep_db_perform( 'configuration', array( 'configuration_value' => $_POST['tax'] ), 'update', 'configuration_key = "dximport_tax"' );
			
			// Guardamos el identificador
			tep_db_perform( 'configuration', array( 'configuration_value' => $_POST['identificador'] ), 'update', 'configuration_key = "dximport_identificador"' );

			// Redireccionamos			
			$messageStack->addSession( 'opcion', 'Los datos se guardaron correctamente', 'success', false );
			tep_redirect( tep_href_link( $sNameFileExport, 'a=opciones' ) );
		break;
		
		case 'ayuda':
			// Titular
			$sTitular = 'Ayuda';
			
			$sHtml .= '<div id="ayda">';

				$sHtml .= '<ul class="ayda-lsta">';
					$sHtml .= '<li><a href="#ayda1">1. Exportar</a>';
						$sHtml .= '<ul>';
							$sHtml .= '<li><a href="#ayda11">1.1 Exportar cabeceras</a></li>';
							$sHtml .= '<li><a href="#ayda12">1.2 Añadir campos personalizados para exportar</a></li>';
						$sHtml .= '</ul>';
					$sHtml .= '</li>';
					
					$sHtml .= '<li><a href="#ayda2">2. Importar</a>';
						$sHtml .= '<ul>';
							$sHtml .= '<li><a href="#ayda21">2.1 Uso de excel para importar CSV</a></li>';
							$sHtml .= '<li><a href="#ayda22">2.2 Configuración de Excel para el separador de precios de miles y decimales</a></li>';
						$sHtml .= '</ul>';
					$sHtml .= '</li>';
				$sHtml .= '</ul>';
				
				$sHtml .= '<div id="ayda1" class="titu">
					<h2 class="titl">1. Exportar</h2>
					<h3 class="stit">Exportar datos CSV</h3>
				</div>
				<p>Exportar los datos de nuestra tienda hacia un archivo CSV nos da la posibilidad de poder editarlos para posteriormente importarlos con los nuevos valores de forma más rápida, sobre todo si deseamos insertar o editar muchos productos.</p>
				<p>Para exportar los datos realizaremos click en la columna izquierda en la opción exportar. En esta pantalla encontraremos:</p>
				<ul>
					<li><b>- Opciones de exportación:</b> Donde podremos decirle al sistema si queremos descargar el archivo, crearlo en el servidor o ambas opciones. También podremos seleccionar la opción de personalizar los campos a exportar</li>
					<li><b>- Campos a exportar:</b> Podremos seleccionar que campos deseamos exportar</li>
					<li><b>- Filtrar por:</b> Pequeños filtros por categoría, fabricantes y estado</li>
				</ul>
				<p>Una vez seleccionado nuestras opciones, realizar click en el botón "Exportar datos"</p>';

				$sHtml .= '<div id="ayda11" class="titu" style="margin-top: 50px;">
					<h2 class="titl">1.1 Exportar cabeceras</h2>
					<h3 class="stit">Exportar solo columnas sin datos</h3>
				</div>				
				<p>La opción de exportar solo las cabeceras, nos ayuda a exportar un archivo CSV vacío sin datos, solo con las columnas preparado para añadirle datos. Esto nos ayuda si un distribuidor nos da un PDF o un Excel con productos y deseamos importarlos rápidamente hacia nuestra tienda.</p>			
				<p>Para exportar solo las cabeceras, realizaremos click en la columna izquierda en la opción exportar, seleccionaremos las opciones explicamos en el punto 1 y pulsaremos sobre el botón "Exportar solo las cabeceras".</p>';
				
				$sHtml .= '<div id="ayda12" class="titu" style="margin-top: 50px;">
					<h2 class="titl">1.2 Añadir campos personalizados para exportar</h2>
					<h3 class="stit">Como añadir campos que solo se encuentre en nuestro proyecto</h3>
				</div>
				
				<p>El sistema de exportación/importación lo hemos realizado solo para los datos más comunes de las tiendas, pero puede darse el caso que en tu tienda tienes un campo especial que te gustaría añadir como por ejemplo "Video de youtube", para añadir este campo en la importación/exportación deberes realizar estos pasos:</p>
				<ul>
					<li>1) Realizaremos click en la columna izquierda en el menu opciones</li>
					<li>2) Buscaremos el campo nuevo en la columna derecha llamada "Campos", en este ejemplo será "products_youtube"</li>
					<li>3) Arrastramos este campo hacia "Campos personalizados" que se encuentra a su izquierda</li>
					<li>4) Arrastramos este campo hacia "Campos personalizados" que se encuentra a su izquierda</li>
					<li>5) Si deseamos añadimos un alias para identificarlo mejor, por ejemplo "Videos de youtube"</li>
					<li>6) Por último pulsamos sobre el botón guardar cambios.</li>
				</ul>';
				
				$sHtml .= '<div id="ayda2" class="titu" style="margin-top: 50px;">
					<h2 class="titl">2. Importar</h2>
					<h3 class="stit">Importar datos CSV</h3>
				</div>
				<p>Importar los datos hacia nuestra tienda desde un archivo CSV nos da la posibilidad de poder editar/insertar productos de forma más rápida, sobre todo si deseamos insertar o editar muchos productos.</p>
				<p>Para importar los datos realizaremos click en la columna izquierda en la opción importar. En esta pantalla encontraremos:</p>
				<ul>
					<li><b>- Subir e importar:</b> Esta opción se usara para subir un archivo CSV y procesar acto seguido, recuerda de realizar backup del sistema antes.</li>
					<li><b>- Subir y dividir archivo:</b> Si tenemos un archivo excel demasiado grande como por ejemplo más de 1000 registros lo mejor que podemos hacer es utilizar esta herramienta que se encargara solo de subir el archivo CSV y dividirlo en trozos en el servidor para posteriormente podamos importar cada archivo dividido y no saturar el servidor.</li>
					<li><b>- Archivos en el servidor:</b> Son los archivos CSV creados por la opción anterior o la opción del exportador de crear archivo en el servidor.</li>
				</ul>';
				
				
				$sHtml .= '<div id="ayda21" class="titu" style="margin-top: 50px;">
					<h2 class="titl">2.1 Uso de excel para importar CSV</h2>
					<h3 class="stit">Importar datos CSV desde Excel</h3>
				</div>
				<p>Para importar datos CSV desde Excel, necesitaremos primero descargar desde nuestro exportador un archivo CSV preparado, para ello realizamos las operaciones que necesitamos como explica el punto 1 o el punto 1.1.</p>
				<p>Una vez descargado nuestro CSV abriremos un Excel nuevo, no directamente el archivo, esto es importante. En nuestro ejemplo usamos Microsoft Excel 2010.</p>
				<p>1) Desde Excel seleccionamos la pestaña Datos y en ella la opción "Desde texto"</p>
				<img src="images/dx-csv-products-ayda-1.png"/>
				<p>2) Cargaremos el archivo que acabamos de descargar.</p>
				<p>3) Nos aparecerá un asistente de importación donde seleccionaremos:</p>
				<ul>
					<li><b>Tipos de los datos originales:</b> Seleccionaremos "Delimitados"</li>
					<li><b>Origen del archivo:</b> Seleccionar Unicode (UTF-8), esto es muy importante ya que seguramente nuestros datos tendrán acentos y "caracteres extraños" para procesar.</li>
				</ul>
				<img src="images/dx-csv-products-ayda-2.png"/>
				<p>4) En el siguiente paso del asistente, seleccionaremos en la opción de separadores "Coma"</p>
				<img src="images/dx-csv-products-ayda-3.png"/>
				<p>5) En el último paso del asistente, seleccionaremos todas las columnas del apartado "Vista previa de los datos" y acto seguido pulsaremos sobre la opción "Texto" de "Formato de los datos en columnas".</p>
				<p>Es importante que todas las columnas se representen como texto, si no Excel al tratar las columnas automáticamente puede existir errores con los precios por ejemplo ya que mete decimales, puntos o comas donde no debería y al importar los datos fallaría.</p>
				<img src="images/dx-csv-products-ayda-4.png"/>
				<p>6) Por último pulsaremos aceptar</p>
				<img src="images/dx-csv-products-ayda-5.png"/>
				<p>7) Una vez editado los datos necesarios pulsaremos sobre "Archivo" y "Guardar"</p>
				<img src="images/dx-csv-products-ayda-6.png"/>
				<p>8) Es muy importante guardar el archivo como "CSV(delimitado por comas)(*.csv)"</p>
				<img src="images/dx-csv-products-ayda-7.png"/>
				<p>9) En el caso que apareciese algun mensaje indicándonos algún fallo o algo pulsaremos siempre en el botón "Aceptar" o botón "Si"</p>
				<p>10) Ya para por último para terminar la importación realizamos los pasos de la ayuda 2.</p>';
				
				$sHtml .= '<div id="ayda22" class="titu" style="margin-top: 50px;">
					<h2 class="titl">2.2 Configuración de Excel para el separador de precios de miles y decimales</h2>
					<h3 class="stit">Configuración de Excel para precios</h3>
				</div>
				<p>Si tenemos precios en nuestra tienda con decimales o miles, que suele ser lo más normal, deberemos configurar Excel para que trate los datos de la misma forma que lo trata nuestra tienda. Para ellos buscamos "Opciones" y seguidamente "Avanzadas" en esta ventana buscaremos "Separadores del sistema" dejandolo configurado de la siguiente forma:</p>
				<p>Separador de miles: , (la coma)</p>
				<p>Separador de decimales: . (el punto).</p>
				<img src="images/dx-csv-products-ayda-8.png"/>';
				
				
			$sHtml .= '</div>';	
		break;
		
		case 'opciones':
			// Titular
			$sTitular = 'Opciones';
			
			// Variables
			$aIdentificadores = array(
				array( 'id' => 'products_id', 'text' => 'products_id' ),
				array( 'id' => 'products_model', 'text' => 'products_model' ),
				array( 'id' => 'product_ean', 'text' => 'product_ean' )
			);
			$aTrueFalse = array(
				array( 'id' => 'true', 'text' => 'Si' ),
				array( 'id' => 'false', 'text' => 'No' )
			);
			
			// Array con los campos ignorados
			$aIgnorados = array( 'products_id', 'products_quantity', 'products_image', 'products_subimage1', 'products_subimage2', 'products_subimage3', 'products_subimage4', 'products_subimage5', 'products_subimage6', 'products_price', 'products_weight', 'products_status', 'products_tax_class_id', 'manufacturers_id' );
			
			// Obtenemos todos los campos de la tabla
			$aDatos = tep_db_query( 'show columns from products' );
			$aCamposTabla = array();
			while( $aDato = tep_db_fetch_array( $aDatos ) )
				$aCamposTabla[] = $aDato['Field'];
			
			// Eliminamos los campos que deseamos ignorar
			$aCamposTabla = array_diff( $aCamposTabla, $aIgnorados );
			
			// Campos que ya han sido añadidos
			$aCamposAnadidos = json_decode( $aOpciones['dximport_extra'], true );
			
			$sHtml .= '<form action="' . $sNameFileExport . '?a=opciones_save" method="post">';
				// Opciones
				$sHtml .= '<div class="fluid grid">';
					$sHtml .= '<div class="box-tbl grid12" style="margin-top: 10px;">';
						$sHtml .= '<div class="box-head">';
							$sHtml .= '<h6>Opciones</h6>';
							$sHtml .= '<div class="clear"></div>';
						$sHtml .= '</div>';						
						$sHtml .= '<div class="formRow">';
							$sHtml .= '<div class="grid2">';
								$sHtml .= '<label>Identificador:</label>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="grid10">';
								$sHtml .= tep_draw_pull_down_menu( 'identificador', $aIdentificadores, $aOpciones['dximport_identificador'], 'id="identificador"' );
								$sHtml .= '<span style="font-style: italic; white-space: normal;" class="note">Campo identificador por el cual se editaran los registros.</span>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="clear"></div>';
						$sHtml .= '</div>';
						
						$sHtml .= '<div class="formRow">';
							$sHtml .= '<div class="grid2">';
								$sHtml .= '<label>Grupos de clientes:</label>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="grid10">';
								$sHtml .= tep_draw_pull_down_menu( 'grupo_cliente', $aTrueFalse, $aOpciones['dximport_grupo_cliente'], 'id="identificador"' );
								$sHtml .= '<span style="font-style: italic; white-space: normal;" class="note">Marca esta opción si vendes a más de un grupo de cliente.</span>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="clear"></div>';
						$sHtml .= '</div>';
						
						$sHtml .= '<div class="formRow">';
							$sHtml .= '<div class="grid2">';
								$sHtml .= '<label>Precio con impuesto:</label>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="grid10">';
								$sHtml .= tep_draw_pull_down_menu( 'tax', $aTrueFalse, $aOpciones['dximport_tax'], 'id="identificador"' );
								$sHtml .= '<span style="font-style: italic; white-space: normal;" class="note">Marca esta opción si deseas exportar/importar con o sin impuesto.</span>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="clear"></div>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';
				
				// Campos extras
				$nCampos = count( $aCamposAnadidos );
				$sHtmlCampos = '';
				
				if (is_array($aCamposAnadidos) || is_object($aCamposAnadidos))
				{
					foreach( $aCamposAnadidos as $key => $value )
					{
						$sHtmlCampos .= '<tr data-row="' . $key . '" data-drop="true">';
							$sHtmlCampos .= '<td>' . $key . '<input value="' . $key . '" type="hidden" name="row[]"/></td>';
							$sHtmlCampos .= '<td><input name="alias[]" value="' . $value . '" style="width: 100%;" type="text" /></td>';
						$sHtmlCampos .= '</tr>';
					}
				}
				
				$sHtml .= '<div class="fluid grid">';
					$sHtml .= '<div class="grid9">';
						$sHtml .= '<div class="box-tbl">';
							$sHtml .= '<div class="box-head">';
								$sHtml .= '<h6>Campos extras</h6>';
								$sHtml .= '<div class="clear"></div>';
							$sHtml .= '</div>';
							$sHtml .= '<div id="drop-rows-cntd" class="' . ($nCampos == 0 ? 'formRow' : 'tDefault') . '">';
								$sHtml .= '<table width="100%" cellspacing="0" cellpadding="0">
									<thead ' . ($nCampos == 0 ? '' : 'style="display: table-header-group;"') . '>
										<tr>
											<td>Campo</td>
											<td>Alias</td>
										</tr>
									</thead>
									<tbody id="drop-rows" ' . ($nCampos == 0 ? '' : 'class="drop-rows-empty"') . '>
										' . $sHtmlCampos . '
									</tbody>
								</table>';
							$sHtml .= '</div>';
						$sHtml .= '</div>';
				
						$sHtml .= '<div class="grid12" style="margin: 10px 0px 0px 0px">';
							$sHtml .= '<input style="float: right;" class="buttonS bGreen" type="submit" value="Guardar los cambios" />';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
					
					$sHtml .= '<div class="box-tbl grid3">';
						$sHtml .= '<div class="box-head">';
							$sHtml .= '<h6>Campos</h6>';
							$sHtml .= '<div class="clear"></div>';
						$sHtml .= '</div>';						
						$sHtml .= '<div id="campos" class="formRow">';
							$sHtml .= '<ul id="rows-drag">';
							
								foreach( $aCamposTabla as $sCampo )
									$sHtml .= '<li ' . (array_key_exists($sCampo, $aCamposAnadidos) || ($sCampo == $aOpciones['dximport_identificador']) ? 'class="drag-sltc"' : '') . ' id="' . $sCampo . '"><span>::</span>' . $sCampo . '</li>';
							
							$sHtml .= '</ul>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';						
				$sHtml .= '</div>';
				
			$sHtml .= '</form>';
		break;
	}

	echo '<style type="text/css">
		table thead td.sortCol > div {cursor: pointer;position: relative;}table thead td span {background: url("../images/tables/sort.png") no-repeat scroll 0 center transparent;display: block;float: right;height: 16px;margin: 2px 2px 0 5px;width: 7px;}table thead td.headerSortUp span {background: url("../images/tables/sortUp.png") no-repeat scroll 0 center transparent;}table thead td.headerSortDown span {background: url("../images/tables/sortDown.png") no-repeat scroll 0 center transparent;}.checkAll tbody tr td:first-child {margin: 0;padding: 0;vertical-align: middle;width: 40px;}.checkAll tbody tr td:first-child .checker, .checkAll tbody tr td:first-child .radio {float: none;margin: 0 auto;}.justTable td {vertical-align: middle;}.justTable tbody tr:first-child {border-top: medium none;}.justTable tbody tr:first-child td {box-shadow: 0 1px 0 #FAFAFA inset;}.tDefault tbody td, .tDefault thead td {border-left: 1px solid #DFDFDF;box-shadow: 0 1px 0 #FAFAFA inset;}.tDefault tbody td:first-child, .tDefault thead td:first-child {border-left: medium none;}.checkAll thead td:first-child > img {padding-bottom: 2px;vertical-align: middle;}.tDefault thead td {background: none repeat scroll 0 0 #EEEEEE;color: #909090;font-size: 11px;padding: 3px 5px 2px;text-align: center;}.tDefault tbody td {padding: 7px 11px;vertical-align: middle;}.tDefault tbody tr {border-top: 1px solid #DFDFDF;}.tDefault tbody tr:first-child {box-shadow: 0 1px 0 #FFFFFF inset;}.tDefault tbody tr:nth-child(2n) {background: none repeat scroll 0 0 #F2F2F2;}.tLight tbody td, .tLight thead td {border-left: 1px solid #DADADA;}.tLight tbody td:first-child, .tLight thead td:first-child {border-left: medium none;}.tLight tbody td {color: #777777;padding: 9px 16px;vertical-align: middle;}.tLight tbody tr {border-top: 1px solid #DADADA;}.tLight thead td {background: -moz-linear-gradient(center top , #F8F8F8 0%, #EFEFEF 100%) repeat scroll 0 0 transparent;font-weight: bold;padding: 7px 12px;text-align: center;}.tDark tbody td {border-left: 1px solid #DADADA;}.tDark thead td {border-left: 1px solid #808080;}.tDark tbody td:first-child, .tDark thead td:first-child {border-left: medium none;}.tDark tbody td {color: #777777;padding: 9px 16px;vertical-align: middle;}.tDark tbody tr {border-top: 1px solid #DADADA;}.tDark thead td {background: url("../images/backgrounds/sidebar.jpg") repeat scroll 0 0 transparent;color: #F5F5F5;font-weight: bold;padding: 7px 12px;text-align: center;}.tDark tbody tr:nth-child(2n) {background: none repeat scroll 0 0 #F4F4F4;}.tMedia thead td a {color: #878787;}.tMedia thead td:first-child > .checker {float: none;margin: 0 auto;}.tMedia tbody td {text-align: center;vertical-align: middle;}.tMedia tfoot tr {background: -moz-linear-gradient(center top , #F8F8F8 0%, #EFEFEF 100%) repeat scroll 0 0 transparent;border-top: 1px solid #DDDDDD;height: 50px;}.tMedia tfoot tr td {padding: 7px 11px;}.tableActs > a {margin: 0 2px;}
	
		/* tablas campos */
		#rows-drag li.drag-sltc
		{
			filter: alpha(opacity=60);
			opacity: 0.6;
			-moz-opacity:0.6;
		}

		#rows-drag li, .hlpe-drag
		{
			cursor: move;
			line-height: 22px;
		}

		#rows-drag li span, .hlpe-drag span
		{
			font-weight: bold;
			color: #000;
			padding-right: 5px;
		}

		#drop-rows-cntd .drag
		{
			background: #cecece;
			filter: alpha(opacity=30);
			opacity: 0.3;
			-moz-opacity:0.3;
		}

		#drop-rows-cntd tbody
		{
			border: 1px dashed #CCC;
			display: block;
			height: 35px;
		}

		#drop-rows-cntd tbody.drop-rows-empty
		{
			display: table-row-group;
			height: auto;
			border: none !important;
		}

		#drop-rows.drop-hovr
		{
			border: 1px dashed #5DADE2;
		}

		#drop-rows-cntd thead
		{
			display: none;
		}
		/* tablas campos */
	
		/* contenedor */
		#ayda
		{
			font-size: 12px;
		}
		
		#ayda img
		{
			margin: 10px;
		}
		
		#ayda p
		{
			text-align: justify;
			line-height: 27px;
		}
		
		#ayda b
		{
			color: #474747;
			font-weight: bold;
		}
		
		#ayda ul
		{
			padding: 5px 0 5px 25px;
		}
		
		#ayda ul li
		{
			padding: 2px 0px;
		}
		
		.titu
		{
			margin-bottom: 10px;
		}
		
		.titu .titl
		{
			color: #666666;
			font-size: 1.3em;
		}
		
		.stit 
		{
			color: #999999;
			font-size: 0.9em;
		}
		
		.ayda-lsta a
		{
			color: #666666;
		}
		
		.ayda-lsta a:hover
		{
			text-decoration: none;
		}
		
		.ayda-lsta
		{
			font-size: 12px;
			margin-bottom: 40px;
		}
		
		.ayda-lsta ul
		{
			padding-left: 22px;
			margin-bottom: 8px;
			padding: 5px 0 10px 25px !important;
		}
		
		.ayda-lsta li
		{
			padding: 1px 0px;
		}
		
		#dxload-ifrme h1
		{
			margin: 0;
			padding: 17px;
		}		
		
		#dxload-ifrme
		{
			background: #FFF;
			border-radius: 5px 5px 5px 5px;
			display: none;
			height: 350px;
			left: 50%;
			margin: -175px -465px;
			position: fixed;
			top: 50%;
			width: 930px;
			z-index: 241;
		}

		#dxbg
		{
			display:none;
			position: fixed;
			top: 0px;
			width: 100%;
			background: none repeat scroll 0px 0px rgb(102, 102, 102);
			height: 100%;
			left: 0px;
			opacity: 0.3;
			z-index: 240;
		}
		
		#cntd-cntd
		{
			margin-bottom: 15px;
			min-height: 300px;
			padding: 16px 0 0 142px;
			position: relative;
		}
		/* /contenedor */
		
		/* tablas */
		#tble-bg-chck-trns
		{
			background: #FFF;	
			position: absolute;
			top: 0px;
			left: 0px;
			width: 100%;
			height: 100%;
			z-index: 100;

			-ms-filter: "progid:DXImageTransform.Microsoft.Alpha(Opacity=90)";
			filter: alpha(opacity=40);
			-moz-opacity: 0.4;
			-khtml-opacity: 0.4;
			opacity: 0.4;
		}

		#tbls td
		{
			padding: 2px 0px;
		}
		
		.labl-chck
		{
			left: -2px;
			position: relative;
			top: 1px;
		}
		/* /tablas */

		
		/* menu_vertical */
		#div-menu-vril
		{
			background: url("images/menu_admin_bg.png") repeat-y scroll right top transparent;
			height: 100%;
			left: 0;
			padding: 35px 0 55px;
			position: absolute;
			top: -20px;
			width: 120px;
		}

		#div-menu-vril a
		{
			display: block;
			width: 120px;
			height: 28px;
			background-image: url("images/menu_admin_dx_csv_products_bg.png");
			text-indent: -9999em;
			filter: alpha(opacity=80);
			opacity: 0.8;
			-moz-opacity:0.8;
		}

		#div-menu-vril a.menu-expr{ background-position: 0 -90px; }
		#div-menu-vril a.menu-impr{ background-position: 0 -117px; }
		#div-menu-vril a.menu-opci{ background-position: 0 -145px; }
		#div-menu-vril a.menu-ayda{ background-position: 0 -207px; }

		#div-menu-vril a:hover
		{
			filter: alpha(opacity=100);
			opacity: 1;
			-moz-opacity:1;
		}

		#div-menu-vril .actv a
		{
			filter: alpha(opacity=100); opacity: 1; -moz-opacity:1;
		}

		#div-menu-vril .actv a.menu-expr{ background-position: 0 0px; }
		#div-menu-vril .actv a.menu-impr{ background-position: 0 -28px; }
		#div-menu-vril .actv a.menu-opci{ background-position: 0 -56px; }
		#div-menu-vril .actv a.menu-ayda{ background-position: 0 -177px; }
		/* /menu_vertical */

		/* titulares */
		.ttlr 
		{
			color: #0099FF;
			font-size: 22px;
			font-weight: bold;
			line-height: 22px;
			margin-bottom: 8px;
			padding-bottom: 10px;
			vertical-align: bottom;
		}
		/* /titulares */
	</style>';	

	echo '<div id="cntd-cntd">';
		echo '<div id="div-menu-vril">
			<ul>
				<li' . ($sAction == 'exportar' ? ' class="actv"' : '' ) . '><a href="' . tep_href_link( $sNameFileExport, 'a=exportar' ) . '" class="menu-expr">Exportar</a></li>
				<li' . ($sAction == 'importar' ? ' class="actv"' : '' ) . '><a href="' . tep_href_link( $sNameFileExport, 'a=importar' ) . '" class="menu-impr">Importar</a></li>
				<li' . ($sAction == 'opciones' ? ' class="actv"' : '' ) . '><a href="' . tep_href_link( $sNameFileExport, 'a=opciones' ) . ' " class="menu-opci">Opciones</a></li>
				<li' . ($sAction == 'ayuda' ? ' class="actv"' : '' ) . '><a href="' . tep_href_link( $sNameFileExport, 'a=ayuda' ) . ' " class="menu-ayda">Ayuda</a></li>
			</ul>
		</div>
		
		<h1 class="ttlr">' . $sTitular . '</h1>';
		echo $messageStack->output();
		echo $sHtml .  '
	</div>';
?>
<?php
//*******************************
//*******************************
// end: MAIN MENU
//*******************************
//*******************************






///////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////
// 
// ep_create_filelayout()
// 
///////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////
function ep_create_filelayout($dltype, $attribute_options_array, $languages, $custom_fields){

    // depending on the type of the download the user wanted, create a file layout for it.
    $fieldmap = array(); // default to no mapping to change internal field names to external.

    // build filters
    $sql_filter = '';
    if (!empty($_GET['epcust_category_filter'])) {
      $sub_categories = array();
      $categories_query_addition = 'ptoc.categories_id = ' . (int)$_GET['epcust_category_filter'] . '';
      tep_get_sub_categories($sub_categories, $_GET['epcust_category_filter']);
      foreach ($sub_categories AS $ckey => $category ) {
        $categories_query_addition .= ' or ptoc.categories_id = ' . (int)$category . '';
      }
      $sql_filter .= ' and (' . $categories_query_addition . ')';
    }
    if ($_GET['epcust_manufacturer_filter']!='') {
      $sql_filter .= ' and p.manufacturers_id = ' . (int)$_GET['epcust_manufacturer_filter'];
    }
    if ($_GET['epcust_status_filter']!='') {
      $sql_filter .= ' and p.products_status = ' . (int)$_GET['epcust_status_filter'];
    }

		// building fields
		if (EP_PRODUCTS_IMAGES == true) { 
			for ($i=1;$i<=EP_PRODUCTS_IMAGES_MAX+1;$i++) {
				$ep_products_layout_product .= '$filelayout[\'v_products_images_image_'.$i.'\'] = $iii++;
																				$filelayout[\'v_products_images_htmlcontent_'.$i.'\'] = $iii++;
																												';
			}
		}

    // /////////////////////////////////////////////////////////////////////
    //
    // Start: Support for other contributions
    //
    // /////////////////////////////////////////////////////////////////////
    
    $ep_additional_layout_product = '';
    $ep_additional_layout_product_select = '';
    $ep_additional_layout_product_description = '';
    $ep_additional_layout_pricing = '';
    
    if ( $dltype == 'full'){
		    if (is_array($custom_fields[TABLE_PRODUCTS]) || is_object($custom_fields[TABLE_PRODUCTS]))
		    {
        foreach ($custom_fields[TABLE_PRODUCTS] as $key => $name) { 
          $ep_additional_layout_product .= '$filelayout[\'v_' . $key . '\'] = $iii++;
                                            ';
          $ep_additional_layout_product_select .= 'p.' . $key . ' as v_' . $key . ',';
		    }
        }
    }


    if (EP_ADDITIONAL_IMAGES == true) { 
      $ep_additional_layout_product .= '$filelayout[\'v_products_image_description\'] = $iii++;
                                       ';
	  for ($i=2;$i<=EP_ADDITIONAL_IMAGES_MAX+1;$i++) {
        $ep_additional_layout_product .= '$filelayout[\'v_products_image_'.$i.'\'] = $iii++;
                                          $filelayout[\'v_products_image_description_'.$i.'\'] = $iii++;
                                          ';
	  }
	} 
    if (EP_ADDITIONAL_IMAGES == true) { 
      $ep_additional_layout_product_select .= 'p.products_image_description as v_products_image_description,';
    }    

    if (EP_MORE_PICS_6_SUPPORT == true) { 
      $ep_additional_layout_product .= '$filelayout[\'v_products_subimage1\'] = $iii++;
                                        $filelayout[\'v_products_subimage2\'] = $iii++;
                                        $filelayout[\'v_products_subimage3\'] = $iii++;
                                        $filelayout[\'v_products_subimage4\'] = $iii++;
                                        $filelayout[\'v_products_subimage5\'] = $iii++;
                                        $filelayout[\'v_products_subimage6\'] = $iii++;
                                        ';
    }    
    if (EP_MORE_PICS_6_SUPPORT == true) { 
      $ep_additional_layout_product_select .= 'p.products_subimage1 as v_products_subimage1, p.products_subimage2 as v_products_subimage2, p.products_subimage3 as v_products_subimage3, p.products_subimage4 as v_products_subimage4, p.products_subimage5 as v_products_subimage5, p.products_subimage6 as v_products_subimage6,';
    }    

    if (EP_ULTRPICS_SUPPORT == true) { 
      $ep_additional_layout_product .= '$filelayout[\'v_products_image_med\'] = $iii++;
					$filelayout[\'v_products_image_lrg\'] = $iii++;
					$filelayout[\'v_products_image_sm_1\'] = $iii++;
					$filelayout[\'v_products_image_xl_1\'] = $iii++;
					$filelayout[\'v_products_image_sm_2\'] = $iii++;
					$filelayout[\'v_products_image_xl_2\'] = $iii++;
					$filelayout[\'v_products_image_sm_3\'] = $iii++;
					$filelayout[\'v_products_image_xl_3\'] = $iii++;
					$filelayout[\'v_products_image_sm_4\'] = $iii++;
					$filelayout[\'v_products_image_xl_4\'] = $iii++;
					$filelayout[\'v_products_image_sm_5\'] = $iii++;
					$filelayout[\'v_products_image_xl_5\'] = $iii++;
					$filelayout[\'v_products_image_sm_6\'] = $iii++;
					$filelayout[\'v_products_image_xl_6\'] = $iii++;
                                        ';
    }
    if (EP_ULTRPICS_SUPPORT == true) { 
      $ep_additional_layout_product_select .= 'p.products_image_med as v_products_image_med, p.products_image_lrg as v_products_image_lrg, p.products_image_sm_1 as v_products_image_sm_1, p.products_image_xl_1 as v_products_image_xl_1, p.products_image_sm_2 as v_products_image_sm_2, p.products_image_xl_2 as v_products_image_xl_2, p.products_image_sm_3 as v_products_image_sm_3, p.products_image_xl_3 as v_products_image_xl_3, p.products_image_sm_4 as v_products_image_sm_4, p.products_image_xl_4 as v_products_image_xl_4, p.products_image_sm_5 as v_products_image_sm_5, p.products_image_xl_5 as v_products_image_xl_5, p.products_image_sm_6 as v_products_image_sm_6,p.products_image_xl_6 as v_products_image_xl_6,';
    }
	
    if (EP_PDF_UPLOAD_SUPPORT == true) { 
      $ep_additional_layout_product .= '$filelayout[\'v_products_pdfupload\'] = $iii++;
					$filelayout[\'v_products_fileupload\'] = $iii++;
					';
    }
	
    if (EP_PDF_UPLOAD_SUPPORT == true) {
      $ep_additional_layout_product_select .='p.products_pdfupload as v_products_pdfupload,p.products_fileupload as v_products_fileupload,';
    }
    
	if (EP_SPPC_SUPPORT == true) 
	{	
		// Obtenemos los grupo de clientes
		$aDatos = tep_db_query( 'select	customers_group_id from customers_groups where customers_group_id > 0 order by customers_group_id asc' );

		if( !empty( $_GET['epcust_specials_price'] ) || $dltype == 'full' )
		{ 
			while( $aDato = tep_db_fetch_array( $aDatos ) )
				$ep_additional_layout_pricing .= '$filelayout[\'v_customer_price_' . $aDato['customers_group_id'] . '\'] = $iii++;
												  $filelayout[\'v_customer_specials_price_' . $aDato['customers_group_id'] . '\'] = $iii++; 
												  $filelayout[\'v_customer_group_id_' . $aDato['customers_group_id'] . '\'] = $iii++;';
		
			/*
			Se ha realizado la consulta anterior para X grupo de clientes
			$ep_additional_layout_pricing .= '$filelayout[\'v_customer_price_1\'] = $iii++;
			$filelayout[\'v_customer_specials_price_1\'] = $iii++;
			$filelayout[\'v_customer_group_id_1\'] = $iii++;
			$filelayout[\'v_customer_price_2\'] = $iii++;
			$filelayout[\'v_customer_specials_price_2\'] = $iii++;
			$filelayout[\'v_customer_group_id_2\'] = $iii++;
			$filelayout[\'v_customer_price_3\'] = $iii++;
			$filelayout[\'v_customer_specials_price_3\'] = $iii++;
			$filelayout[\'v_customer_group_id_3\'] = $iii++;
			$filelayout[\'v_customer_price_4\'] = $iii++;
			$filelayout[\'v_customer_specials_price_4\'] = $iii++;
			$filelayout[\'v_customer_group_id_4\'] = $iii++;
			';*/
		}
		else
		{
			while( $aDato = tep_db_fetch_array( $aDatos ) )
				$ep_additional_layout_pricing .= '$filelayout[\'v_customer_price_' . $aDato['customers_group_id'] . '\'] = $iii++;
												  $filelayout[\'v_customer_group_id_' . $aDato['customers_group_id'] . '\'] = $iii++;';
		
			/*
			Se ha realizado la consulta anterior para X grupo de clientes
			$ep_additional_layout_pricing .= '$filelayout[\'v_customer_price_1\'] = $iii++;
			$filelayout[\'v_customer_group_id_1\'] = $iii++;
			$filelayout[\'v_customer_price_2\'] = $iii++;
			$filelayout[\'v_customer_group_id_2\'] = $iii++;
			$filelayout[\'v_customer_price_3\'] = $iii++;
			$filelayout[\'v_customer_group_id_3\'] = $iii++;
			$filelayout[\'v_customer_price_4\'] = $iii++;
			$filelayout[\'v_customer_group_id_4\'] = $iii++;
			';*/
		}
	}

    if (EP_HTC_SUPPORT == true) {
      $ep_additional_layout_product_description .= '$filelayout[\'v_products_head_title_tag_\'.$lang[\'id\']]    = $iii++;
                                                    $filelayout[\'v_products_head_desc_tag_\'.$lang[\'id\']]     = $iii++;
                                                    $filelayout[\'v_products_head_keywords_tag_\'.$lang[\'id\']] = $iii++;
                                                    ';
    }
    
    if (EP_MVS_SUPPORT == true) {
      $ep_additional_layout_product_select .= 'p.vendors_id as v_vendor_id,';
    }

    if (MASTER_PRODUCTS_SUPPORT == true) {
      $ep_additional_layout_product_select .= 'p.products_id as v_products_model_id, p.products_master as v_products_master, p.products_master_status as v_products_master_status, ';
    }
    
    // /////////////////////////////////////////////////////////////////////
    // End: Support for other contributions
    // /////////////////////////////////////////////////////////////////////

    switch( $dltype ){

    case 'full':
        // The file layout is dynamically made depending on the number of languages
        $iii = 0;
        $filelayout = array();

		// sampedro: cambio de products_model con products_id
        $filelayout['v_' . EP_IDENTIFICADOR] = $iii++;

        foreach ($languages as $key => $lang){
            $filelayout['v_products_name_'.$lang['id']]        = $iii++;
            $filelayout['v_products_description_'.$lang['id']] = $iii++;
            // $filelayout['v_products_url_'.$lang['id']]         = $iii++;
            foreach ($custom_fields[TABLE_PRODUCTS_DESCRIPTION] as $key => $name) { 
              $filelayout['v_' . $key . '_'.$lang['id']]         = $iii++;
            }
            if (!empty($ep_additional_layout_product_description)) {
              eval($ep_additional_layout_product_description);
            }
			
        }

   //     $filelayout['v_products_image'] = $iii++;

  //      if (!empty($ep_additional_layout_product)) {
  //        eval($ep_additional_layout_product);
  //      }
		
		$filelayout['v_products_image'] = $iii++;

if (!empty($ep_products_layout_product)) { // fix for product images
eval($ep_products_layout_product);
}

if (!empty($ep_additional_layout_product)) {
eval($ep_additional_layout_product);
}

        $filelayout['v_products_price']    = $iii++;
        $filelayout['v_products_specials_price'] = $iii++;

        if (EP_MVS_SUPPORT == true) { 
          $filelayout['v_vendor']    = $iii++;
        }

        if (!empty($ep_additional_layout_pricing)) {
          eval($ep_additional_layout_pricing);
        }
		
        if (MASTER_PRODUCTS_SUPPORT == true) { 
          $filelayout['v_products_master']    = $iii++;
          $filelayout['v_products_master_status']    = $iii++;
        }
        
        $filelayout['v_products_quantity'] = $iii++;
        $filelayout['v_products_weight']   = $iii++;
        $filelayout['v_date_avail']        = $iii++;
        $filelayout['v_date_added']        = $iii++;

		// build the categories name section of the array based on the number of categores the user wants to have
		for($i=1; $i<EP_MAX_CATEGORIES+1; $i++){
			$filelayout['v_categories_image_' . $i] = $iii++;
			foreach ($languages as $key => $lang){
				$filelayout['v_categories_name_' . $i . '_' . $lang['id']] = $iii++;
			}
		}

        $filelayout['v_manufacturers_name'] = $iii++;

        // VJ product attribs begin
        $attribute_options_count = 1;
        foreach ($attribute_options_array as $tkey => $attribute_options_values) {
            $filelayout['v_attribute_options_id_'.$attribute_options_count] = $iii++;
            foreach ($languages as $tkey => $lang ) {
                $filelayout['v_attribute_options_name_'.$attribute_options_count.'_'.$lang['id']] = $iii++;
            }

            $attribute_values_query = "select products_options_values_id  from " . TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS . " where products_options_id = '" . (int)$attribute_options_values['products_options_id'] . "' order by products_options_values_id";
            $attribute_values_values = tep_db_query($attribute_values_query);

            $attribute_values_count = 1;
            while ($attribute_values = tep_db_fetch_array($attribute_values_values)) {
                $filelayout['v_attribute_values_id_'.$attribute_options_count.'_'.$attribute_values_count] = $iii++;
                foreach ($languages as $tkey => $lang ) {
                    $filelayout['v_attribute_values_name_'.$attribute_options_count.'_'.$attribute_values_count.'_'.$lang['id']] = $iii++;
                }
                $filelayout['v_attribute_values_price_'.$attribute_options_count.'_'.$attribute_values_count] = $iii++;
                //// attributes stock add start        
                if ( EP_PRODUCTS_ATTRIBUTES_STOCK == true ) { 
                    $filelayout['v_attribute_values_stock_'.$attribute_options_count.'_'.$attribute_values_count] = $iii++;
                }                
                //// attributes stock add end         
                $attribute_values_count++;
             }
            $attribute_options_count++;
        }
        // VJ product attribs end

        $filelayout['v_tax_class_title']  = $iii++;
        $filelayout['v_status']           = $iii++;

        $filelayout_sql = "SELECT
            p.products_id as v_products_id,
            p.products_model as v_products_model,
            p.products_image as v_products_image,
            $ep_additional_layout_product_select
            p.products_price as v_products_price,
            p.products_weight as v_products_weight,
            p.products_date_available as v_date_avail,
            p.products_date_added as v_date_added,
            p.products_tax_class_id as v_tax_class_id,
            p.products_quantity as v_products_quantity,
            p.manufacturers_id as v_manufacturers_id,
            subc.categories_id as v_categories_id,
            p.products_status as v_status
            FROM
            ".TABLE_PRODUCTS." as p,
            ".TABLE_CATEGORIES." as subc,
            ".TABLE_PRODUCTS_TO_CATEGORIES." as ptoc
            WHERE
            p.products_id = ptoc.products_id AND
            ptoc.categories_id = subc.categories_id
            " . $sql_filter;

        break;

    case 'priceqty':
        $iii = 0;
        $filelayout = array();

		// sampedro: cambio de products_model con products_id
        $filelayout['v_' . EP_IDENTIFICADOR] = $iii++;
        $filelayout['v_products_price']    = $iii++;
        $filelayout['v_products_quantity'] = $iii++;
        
        if (!empty($ep_additional_layout_pricing)) {
          eval($ep_additional_layout_pricing);
        }

        $filelayout_sql = "SELECT
            p.products_id as v_products_id,
            p.products_model as v_products_model,
            p.products_price as v_products_price,
            p.products_tax_class_id as v_tax_class_id,
            p.products_quantity as v_products_quantity
            FROM
            ".TABLE_PRODUCTS." as p
            ";
        break;

    case 'masterstatus':
        $iii = 0;
        $filelayout = array();

		// sampedro: cambio de products_model con products_id
        $filelayout['v_' . EP_IDENTIFICADOR] = $iii++;
        $filelayout['v_products_model']         = $iii++;
        $filelayout['v_products_master']        = $iii++;
        $filelayout['v_products_master_status'] = $iii++;
        
        if (!empty($ep_additional_layout_pricing)) {
          eval($ep_additional_layout_pricing);
        }

        $filelayout_sql = "SELECT
            p.products_id as v_products_id,
            p.products_id as v_products_model_id,
            p.products_model as v_products_model,
            p.products_master as v_products_master,
            p.products_master_status as v_products_master_status
            FROM
            ".TABLE_PRODUCTS." as p
            ";
        break;

        case 'prodimage':
        $iii = 0;
        $filelayout = array();

		// sampedro: cambio de products_model con products_id
        $filelayout['v_' . EP_IDENTIFICADOR] = $iii++;

        foreach ($languages as $key => $lang){
                $filelayout['v_products_name_'.$lang['id']]     = $iii++;
                //$filelayout['v_products_description_'.$lang['id']] = $iii++;
                //$filelayout['v_products_url_'.$lang['id']]            = $iii++;
                foreach ($custom_fields[TABLE_PRODUCTS_DESCRIPTION] as $key => $name) { 
                $filelayout['v_' . $key . '_'.$lang['id']]              = $iii++;
                }
                if (!empty($ep_additional_layout_product_description)) {
                eval($ep_additional_layout_product_description);
                }
                        
        }

   $filelayout['v_products_image'] = $iii++;

if (!empty($ep_products_layout_product)) { // fix for product images
eval($ep_products_layout_product);
}

if (!empty($ep_additional_layout_product)) {
eval($ep_additional_layout_product);
}

        
        $filelayout_sql = "SELECT
                p.products_id as v_products_id,
                p.products_model as v_products_model,
                p.products_image as v_products_image,
                $ep_additional_layout_product_select
                p.products_status as v_status
                FROM
                ".TABLE_PRODUCTS." as p
                " . $sql_filter;
        break;

    case 'custom':
        $iii = 0;
        $filelayout = array();

		// sampedro: cambio de products_model con products_id
        $filelayout['v_' . EP_IDENTIFICADOR] = $iii++;
		
        if (!empty($_GET['epcust_status'])) { $filelayout['v_status'] = $iii++; }

        foreach ($languages as $key => $lang){
            if (!empty($_GET['epcust_name'])) { $filelayout['v_products_name_'.$lang['id']] = $iii++; }
            if (!empty($_GET['epcust_description'])) { $filelayout['v_products_description_'.$lang['id']] = $iii++; }
            // if (!empty($_GET['epcust_url'])) { $filelayout['v_products_url_'.$lang['id']] = $iii++; }
            foreach ($custom_fields[TABLE_PRODUCTS_DESCRIPTION] as $key => $name) { 
              if (!empty($_GET['epcust_' . $key])) { $filelayout['v_' . $key . '_'.$lang['id']] = $iii++; }
            }
        }

        if (!empty($_GET['epcust_image']) || !empty($_GET['epcust_add_images'])) {
$filelayout['v_products_image'] = $iii++;

if (!empty($ep_products_layout_product)) { // fix for product images
eval($ep_products_layout_product);
}

if (!empty($ep_additional_layout_product)) {
eval($ep_additional_layout_product);
}

        }
 
        foreach ($custom_fields[TABLE_PRODUCTS] as $key => $name) { 
            if (!empty($_GET['epcust_' . $key])) {
                $filelayout['v_' . $key] = $iii++;
                $ep_additional_layout_product_select .= 'p.' . $key . ' as v_' . $key . ',';
            }
        }
		
        if (!empty($ep_additional_layout_pricing)) {
          eval($ep_additional_layout_pricing);
        }

        if (!empty($_GET['epcust_price'])) { $filelayout['v_products_price'] = $iii++; }
        if (!empty($_GET['epcust_specials_price'])) { $filelayout['v_products_specials_price'] = $iii++; }
        if (!empty($_GET['epcust_quantity'])) { $filelayout['v_products_quantity'] = $iii++; }
        if (!empty($_GET['epcust_weight'])) { $filelayout['v_products_weight'] = $iii++; }
        if (!empty($_GET['epcust_avail'])) { $filelayout['v_date_avail'] = $iii++; }
        if (!empty($_GET['epcust_date_added'])) { $filelayout['v_date_added'] = $iii++; }
		
		if (!empty($_GET['epcust_category'])) {
		  // build the categories name section of the array based on the number 
		  // of categores the user wants to have
		  for($i=1; $i<=EP_MAX_CATEGORIES; $i++){
			$filelayout['v_categories_image_'.$i] = $iii++;
			foreach ($languages as $key => $lang){
			  $filelayout['v_categories_name_'.$i.'_'.$lang['id']] = $iii++;
			}
		  }
		}

        if (!empty($_GET['epcust_manufacturer'])) { $filelayout['v_manufacturers_name'] = $iii++; }

        if (!empty($_GET['epcust_attributes'])) {
          // VJ product attribs begin
          $attribute_options_count = 1;
          foreach ($attribute_options_array as $tkey => $attribute_options_values) {
            $filelayout['v_attribute_options_id_'.$attribute_options_count] = $iii++;
            foreach ($languages as $tkey => $lang ) {
                $filelayout['v_attribute_options_name_'.$attribute_options_count.'_'.$lang['id']] = $iii++;
            }

            $attribute_values_query = "select products_options_values_id  from " . TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS . " where products_options_id = '" . (int)$attribute_options_values['products_options_id'] . "' order by products_options_values_id";
            $attribute_values_values = tep_db_query($attribute_values_query);

            $attribute_values_count = 1;
            while ($attribute_values = tep_db_fetch_array($attribute_values_values)) {
                $filelayout['v_attribute_values_id_'.$attribute_options_count.'_'.$attribute_values_count] = $iii++;
                foreach ($languages as $tkey => $lang ) {
                    $filelayout['v_attribute_values_name_'.$attribute_options_count.'_'.$attribute_values_count.'_'.$lang['id']] = $iii++;
                }
                $filelayout['v_attribute_values_price_'.$attribute_options_count.'_'.$attribute_values_count] = $iii++;
                //// attributes stock add start        
                if ( EP_PRODUCTS_ATTRIBUTES_STOCK == true ) { 
                    $filelayout['v_attribute_values_stock_'.$attribute_options_count.'_'.$attribute_values_count] = $iii++;
                }                
                //// attributes stock add end         
                $attribute_values_count++;
             }
            $attribute_options_count++;
          }
          // VJ product attribs end
        }
        if (EP_MVS_SUPPORT == true) {
          if (!empty($_GET['epcust_vendor'])) { $filelayout['v_vendor'] = $iii++; }
        }

        if (MASTER_PRODUCTS_SUPPORT == true) {
          if (!empty($_GET['epcust_products_master'])) { $filelayout['v_products_master'] = $iii++; }
          if (!empty($_GET['epcust_products_master_status'])) { $filelayout['v_products_master_status'] = $iii++; }
          if (!empty($_GET['epcust_products_model_id'])) { $filelayout['v_products_model_id'] = $iii++; }
        }
        
        if (!empty($_GET['epcust_sppc'])) {
         if (!empty($ep_additional_layout_pricing)) {
          eval($ep_additional_layout_pricing);
         }
        }

        if (!empty($_GET['epcust_tax_class'])) { $filelayout['v_tax_class_title'] = $iii++; }
        if (!empty($_GET['epcust_comment'])) { $filelayout['v_products_comment'] = $iii++; }
        if (!empty($_GET['epcust_cross_sell'])) { $filelayout['v_cross_sell'] = $iii++; }

        $filelayout_sql = "SELECT
            p.products_id as v_products_id,
            p.products_model as v_products_model,
            p.products_status as v_status,
            p.products_price as v_products_price,
            p.products_quantity as v_products_quantity,
            p.products_weight as v_products_weight,
            p.products_image as v_products_image,
            $ep_additional_layout_product_select
            p.manufacturers_id as v_manufacturers_id,
            p.products_date_available as v_date_avail,
            p.products_date_added as v_date_added,
            p.products_tax_class_id as v_tax_class_id,
            subc.categories_id as v_categories_id
            FROM
            ".TABLE_PRODUCTS." as p,
            ".TABLE_CATEGORIES." as subc,
            ".TABLE_PRODUCTS_TO_CATEGORIES." as ptoc
            WHERE
            p.products_id = ptoc.products_id AND
            ptoc.categories_id = subc.categories_id
            " . $sql_filter;
        break;

    case 'category':
        $iii = 0;
        $filelayout = array();
        
		// sampedro: cambio de products_model con products_id
		$filelayout['v_' . EP_IDENTIFICADOR] = $iii++;

		for($i=1; $i<EP_MAX_CATEGORIES+1; $i++){
			$filelayout['v_categories_image_'.$i] = $iii++;
			foreach ($languages as $key => $lang){
					$filelayout['v_categories_name_'.$i.'_'.$lang['id']] = $iii++;
			}
		}

        $filelayout_sql = "SELECT
            p.products_id as v_products_id,
            p.products_model as v_products_model,
            subc.categories_id as v_categories_id
            FROM
            ".TABLE_PRODUCTS." as p,
            ".TABLE_CATEGORIES." as subc,
            ".TABLE_PRODUCTS_TO_CATEGORIES." as ptoc            
            WHERE
            p.products_id = ptoc.products_id AND
            ptoc.categories_id = subc.categories_id
            ";
        break;

    case 'extra_fields':
    // start EP for product extra field ============================= DEVSOFTVN - 10/20/2005     
        $iii = 0;
        $filelayout = array(
		// sampedro: cambio de products_model con products_id
        'v_' . EP_IDENTIFICADOR        => $iii++,          
            'v_products_extra_fields_name'        => $iii++, 
            'v_products_extra_fields_id'        => $iii++,
//            'v_products_id'        => $iii++,
            'v_products_extra_fields_value'        => $iii++,
                        );
    
        $filelayout_sql = "SELECT
                        p.products_id as v_products_id,
                        p.products_model as v_products_model,
                        subc.products_extra_fields_id as v_products_extra_fields_id,
                        subc.products_extra_fields_value as v_products_extra_fields_value,
                        ptoc.products_extra_fields_name as v_products_extra_fields_name
                        FROM
                        ".TABLE_PRODUCTS." as p,
                        ".TABLE_PRODUCTS_TO_PRODUCTS_EXTRA_FIELDS." as subc,
                        ".TABLE_PRODUCTS_EXTRA_FIELDS." as ptoc
                        WHERE
                        p.products_id = subc.products_id AND
                        ptoc.products_extra_fields_id = subc.products_extra_fields_id
                        ";    
    // end of EP for extra field code ======= DEVSOFTVN================       
        break;


    case 'froogle':
        // this is going to be a little interesting because we need
        // a way to map from internal names to external names
        //
        // Before it didn't matter, but with froogle needing particular headers,
        // The file layout is dynamically made depending on the number of languages
        $iii = 0;
        $filelayout = array();

        // $filelayout['v_froogle_products_url_1'] = $iii++;
        $filelayout['v_froogle_products_name_'.EP_DEFAULT_LANGUAGE_ID] = $iii++;
        $filelayout['v_froogle_products_description_'.EP_DEFAULT_LANGUAGE_ID] = $iii++;
        $filelayout['v_products_price'] = $iii++;
        $filelayout['v_products_fullpath_image'] = $iii++;
        $filelayout['v_froogle_product_id'] = $iii++;
        $filelayout['v_froogle_quantitylevel'] = $iii++;
        $filelayout['v_category_fullpath'] = $iii++;
        $filelayout['v_froogle_exp_date'] = $iii++;
        $filelayout['v_froogle_currency'] = $iii++;

        $iii=0;
        $fileheaders = array();

        // EP Support mapping new names to the export headers.
        // use the $fileheaders[''] vars to do that.
        $fileheaders['link'] = $iii++;
        $fileheaders['title'] = $iii++;
        $fileheaders['description'] = $iii++;
        $fileheaders['price'] = $iii++;
        $fileheaders['image_link'] = $iii++;
        $fileheaders['id'] = $iii++;
        $fileheaders['quantity'] = $iii++;
        $fileheaders['product_type'] = $iii++;
        $fileheaders['expiration_date'] = $iii++;
        $fileheaders['currency'] = $iii++;

        $filelayout_sql = "SELECT
            p.products_id as v_products_id,
            p.products_model as v_products_model,
            p.products_image as v_products_image,
            p.products_price as v_products_price,
            p.products_weight as v_products_weight,
            p.products_date_added as v_date_added,
            p.products_date_available as v_date_avail,
            p.products_tax_class_id as v_tax_class_id,
            p.products_quantity as v_products_quantity,
            p.manufacturers_id as v_manufacturers_id,
            subc.categories_id as v_categories_id
            FROM
            ".TABLE_PRODUCTS." as p,
            ".TABLE_CATEGORIES." as subc,
            ".TABLE_PRODUCTS_TO_CATEGORIES." as ptoc
            WHERE
            p.products_id = ptoc.products_id AND
            ptoc.categories_id = subc.categories_id
            " . $sql_filter;
        break;

    // VJ product attributes begin
    case 'attrib':
        $iii = 0;
        $filelayout = array();

		// sampedro: cambio de products_model con products_id
        $filelayout['v_' . EP_IDENTIFICADOR] = $iii++;

        $attribute_options_count = 1;
        foreach ($attribute_options_array as $tkey1 => $attribute_options_values) {
            $filelayout['v_attribute_options_id_'.$attribute_options_count] = $iii++;
            foreach ($languages as $tkey => $lang ) {
                $filelayout['v_attribute_options_name_'.$attribute_options_count.'_'.$lang['id']] = $iii++;
            }

            $attribute_values_query = "select products_options_values_id  from " . TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS . " where products_options_id = '" . (int)$attribute_options_values['products_options_id'] . "' order by products_options_values_id";
            $attribute_values_values = tep_db_query($attribute_values_query);

            $attribute_values_count = 1;
            while ($attribute_values = tep_db_fetch_array($attribute_values_values)) {
                $filelayout['v_attribute_values_id_'.$attribute_options_count.'_'.$attribute_values_count] = $iii++;
                foreach ($languages as $tkey2 => $lang ) {
                    $filelayout['v_attribute_values_name_'.$attribute_options_count.'_'.$attribute_values_count.'_'.$lang['id']] = $iii++;
                }
                $filelayout['v_attribute_values_price_'.$attribute_options_count.'_'.$attribute_values_count] = $iii++;
                //// attributes stock add start        
                if ( EP_PRODUCTS_ATTRIBUTES_STOCK    == true ) { 
                    $header_array['v_attribute_values_stock_'.$attribute_options_count.'_'.$attribute_values_count] = $iii++;
                }                
                //// attributes stock add end         
                $attribute_values_count++;
            }
            $attribute_options_count++;
        }

        $filelayout_sql = "SELECT
                            p.products_id as v_products_id,
                            p.products_model as v_products_model
                            FROM
                            ".TABLE_PRODUCTS." as p
                            ";

        break;
        // VJ product attributes end
    }


    $filelayout_count = count($filelayout);

  return array($filelayout, $filelayout_count, $filelayout_sql, $fileheaders);

}






///////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////
// 
// process_row()
//
//   Processes one row of the import file
// 
///////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////
function process_row( $item1, $filelayout, $filelayout_count, $default_these, $ep_separator, $languages, $custom_fields )
{
	// Obtenemos el nivel de categorias
	$nLevelCategorias = EP_MAX_CATEGORIES;
	$aAux = array_keys( $filelayout );
	foreach( $aAux as $value )
	{
		if( preg_match( '/^v_categories_name_/i', $value ) )
		{
			$value = preg_replace( '/^v_categories_name_|_.+$/i', '', $value );
			$nLevelCategorias = $value;
		}
	}

	$sHtml = '';

    // first we clean up the row of data
    if (EP_EXCEL_SAFE_OUTPUT == true) {
      $items = $item1;
    } else {
      // chop blanks from each end
      $item1 = ltrim(rtrim($item1));
    
      // blow it into an array, splitting on the tabs
      $items = explode($ep_separator, $item1);
    }

    // make sure all non-set things are set to '';
    // and strip the quotes from the start and end of the stings.
    // escape any special chars for the database.
    foreach( $filelayout as $key => $value){
        $i = $filelayout[$key];
		
	
        if (isset($items[$i]) == false) {
            $items[$i]='';
        } else {
            // Check to see if either of the magic_quotes are turned on or off;
            // And apply filtering accordingly.
            if (function_exists('ini_get')) {
                //echo "Getting ready to check magic quotes<br />";
                if (ini_get('magic_quotes_runtime') == 1){
                    // The magic_quotes_runtime are on, so lets account for them
                    // check if the first & last character are quotes;
                    // if it is, chop off the quotes.
                    if (substr($items[$i],-1) == '"' && substr($items[$i],0,1) == '"'){
                        $items[$i] = substr($items[$i],2,strlen($items[$i])-4);
                    }
                    // now any remaining doubled double quotes should be converted to one doublequote
                    if (EP_REPLACE_QUOTES == true){
                        if (EP_EXCEL_SAFE_OUTPUT == true) {
                          $items[$i] = str_replace('\"\"',"&#34;",$items[$i]);
                        }
                        $items[$i] = str_replace('\"',"&#34;",$items[$i]);
                        $items[$i] = str_replace("\'","&#39;",$items[$i]);
                    }
                } else { // no magic_quotes are on
                    // check if the last character is a quote;
                    // if it is, chop off the 1st and last character of the string.
                    if (substr($items[$i],-1) == '"' && substr($items[$i],0,1) == '"'){
                        $items[$i] = substr($items[$i],1,strlen($items[$i])-2);
                    }
                    // now any remaining doubled double quotes should be converted to one doublequote
                    if (EP_REPLACE_QUOTES == true){
                        if (EP_EXCEL_SAFE_OUTPUT == true) {
                          $items[$i] = str_replace('""',"&#34;",$items[$i]);
                        }
                        $items[$i] = str_replace('"',"&#34;",$items[$i]);
                        $items[$i] = str_replace("'","&#39;",$items[$i]);
                    }
                }
            }
        }
    }

	// /////////////////////////////////////////////////////////////
    // Do specific functions without processing entire range of vars
    // /////////////////////////////
    // first do product extra fields
    if (isset($items[$filelayout['v_products_extra_fields_id']]) ){
    
        $v_products_model = $items[$filelayout['v_products_model']];
        // EP for product extra fields Contrib by minhmaster DEVSOFTVN ==========
        $v_products_extra_fields_id = $items[$filelayout['v_products_extra_fields_id']];
//        $v_products_id    =    $items[$filelayout['v_products_id']];
        $v_products_extra_fields_value    =    $items[$filelayout['v_products_extra_fields_value']];
        
        $sql = "SELECT p.products_id as v_products_id FROM ".TABLE_PRODUCTS." as p WHERE p.products_model = '" . $v_products_model . "'";
        $result = tep_db_query($sql);
        $row =  tep_db_fetch_array($result);

		$sql_exist	=	"SELECT products_extra_fields_value FROM ".TABLE_PRODUCTS_TO_PRODUCTS_EXTRA_FIELDS. " WHERE (products_id ='".$row['v_products_id']. "') AND (products_extra_fields_id ='".$v_products_extra_fields_id ."')";

		if (tep_db_num_rows(tep_db_query($sql_exist)) > 0) {
			$sql_extra_field	=	"UPDATE ".TABLE_PRODUCTS_TO_PRODUCTS_EXTRA_FIELDS." SET products_extra_fields_value='".$v_products_extra_fields_value."' WHERE (products_id ='". $row['v_products_id'] . "') AND (products_extra_fields_id ='".$v_products_extra_fields_id ."')";
			$str_err_report= " $v_products_extra_fields_id | $v_products_id  | $v_products_model | $v_products_extra_fields_value | <b><font color=black>Campos extras de producto actualizado</font></b><br />";
		} else {
			$sql_extra_field	=	"INSERT INTO ".TABLE_PRODUCTS_TO_PRODUCTS_EXTRA_FIELDS."(products_id,products_extra_fields_id,products_extra_fields_value) VALUES ('". $row['v_products_id'] ."','".$v_products_extra_fields_id."','".$v_products_extra_fields_value."')";
			$str_err_report= " $v_products_extra_fields_id | $v_products_id | $v_products_model | $v_products_extra_fields_value | <b><font color=green>Campos extras de producto insertado</font></b><br />";
		}

        $result = tep_db_query($sql_extra_field);
        
        $sHtml .= $str_err_report;
        // end (EP for product extra fields Contrib by minhmt DEVSOFTVN) ============
        
    // /////////////////////
    // or do product deletes
    } elseif ( $items[$filelayout['v_status']] == EP_DELETE_IT ) {
      // Get the ID
      $sql = "SELECT p.products_id as v_products_id    FROM ".TABLE_PRODUCTS." as p WHERE p.products_model = '" . $items[$filelayout['v_products_model']] . "'";
      $result = tep_db_query($sql);
      $row =  tep_db_fetch_array($result);
      if (tep_db_num_rows($result) == 1 ) {
        tep_db_query("delete from " . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . $row['v_products_id'] . "'");

        $product_categories_query = tep_db_query("select count(*) as total from " . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . $row['v_products_id'] . "'");
        $product_categories = tep_db_fetch_array($product_categories_query);

        if ($product_categories['total'] == '0') {
          // gather product attribute data
          $result = tep_db_query("select pov.products_options_values_id from " . TABLE_PRODUCTS_ATTRIBUTES . " pa left join " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov on pa.options_values_id=pov.products_options_values_id where pa.products_id = '" . (int)$row['v_products_id'] . "'");
          $remove_attribs = array();
          while ($tmp_attrib = tep_db_fetch_array($result)) {
            $remove_attribs[] = $tmp_attrib;
          }

          // check each attribute name for links to other products
          foreach ($remove_attribs as $rakey => $ravalue) {
            $product_attribs_query = tep_db_query("select count(*) as total from " . TABLE_PRODUCTS_ATTRIBUTES . " where options_values_id = '" . (int)$ravalue['products_options_values_id'] . "'");
            $product_attribs = tep_db_fetch_array($product_attribs_query);
            // if no other products linked, remove attribute name
            if ((int)$product_attribs['total'] == 1) {
              tep_db_query("delete from " . TABLE_PRODUCTS_OPTIONS_VALUES. " where products_options_values_id = '" . (int)$ravalue['products_options_values_id'] . "'");
            }
          }
          // remove attribute records
          tep_db_query("delete from " . TABLE_PRODUCTS_ATTRIBUTES . " where products_id = '" . (int)$row['v_products_id'] . "'");

          // remove product
          tep_remove_product($row['v_products_id']);
        }

        if (USE_CACHE == 'true') {
          tep_reset_cache_block('categories');
          tep_reset_cache_block('also_purchased');
        }
        $sHtml .= "Borrado el producto " . $items[$filelayout['v_' . EP_IDENTIFICADOR]] . " de la base de datos<br />";
        
      } else {
          $sHtml .= "No se puede eliminar " . $items['v_' . EP_IDENTIFICADOR] . " ya que no es único<br> ";
      }
  
    // //////////////////////////////////
    // or do regular product processing
    // //////////////////////////////////
    } else {
        // /////////////////////////////////////////////////////////////////////
        //
        // Start: Support for other contributions in products table
        //
        // /////////////////////////////////////////////////////////////////////
        $ep_additional_select = '';
	
        if (EP_ADDITIONAL_IMAGES == true) {
          $ep_additional_select .= 'p.products_image_description as v_products_image_description,';
        }

        if (EP_MORE_PICS_6_SUPPORT == true) { 
          $ep_additional_select .= 'p.products_subimage1 as v_products_subimage1,p.products_subimage2 as v_products_subimage2,p.products_subimage3 as v_products_subimage3,p.products_subimage4 as v_products_subimage4,p.products_subimage5 as v_products_subimage5,p.products_subimage6 as v_products_subimage6,';
        }    

        if (EP_ULTRPICS_SUPPORT == true) { 
          $ep_additional_select .= 'products_image_med as v_products_image_med,products_image_lrg as v_products_image_lrg,products_image_sm_1 as v_products_image_sm_1,products_image_xl_1 as v_products_image_xl_1,products_image_sm_2 as v_products_image_sm_2,products_image_xl_2 as v_products_image_xl_2,products_image_sm_3 as v_products_image_sm_3,products_image_xl_3 as v_products_image_xl_3,products_image_sm_4 as v_products_image_sm_4,products_image_xl_4 as v_products_image_xl_4,products_image_sm_5 as v_products_image_sm_5,products_image_xl_5 as v_products_image_xl_5,products_image_sm_6 as v_products_image_sm_6,products_image_xl_6 as v_products_image_xl_6,';
        }
		
        if (EP_PDF_UPLOAD_SUPPORT == true) { 
          $ep_additional_select .='p.products_pdfupload as v_products_pdfupload, p.products_fileupload as v_products_fileupload,';
        }

        if (EP_MVS_SUPPORT == true) {
          $ep_additional_select .= 'vendors_id as v_vendor_id,';
        }

        if (MASTER_PRODUCTS_SUPPORT == true) {
          $ep_additional_select .='p.products_master as v_products_master, p.products_master_status as v_products_master_status,';
        }

        foreach ($custom_fields[TABLE_PRODUCTS] as $key => $name) { 
          $ep_additional_select .= 'p.' . $key . ' as v_' . $key . ',';
        }

        // /////////////////////////////////////////////////////////////////////
        // End: Support for other contributions in products table
        // /////////////////////////////////////////////////////////////////////
    
    
        // now do a query to get the record's current contents
        $sql = "SELECT
                    p.products_id as v_products_id,
                    p.products_model as v_products_model,
                    p.products_image as v_products_image,
                    $ep_additional_select
                    p.products_price as v_products_price,
                    p.products_weight as v_products_weight,
                    p.products_date_available as v_date_avail,
                    p.products_date_added as v_date_added,
                    p.products_tax_class_id as v_tax_class_id,
                    p.products_quantity as v_products_quantity,
                    p.manufacturers_id as v_manufacturers_id,
                    subc.categories_id as v_categories_id,
                    p.products_status as v_status_current
                FROM
                    ".TABLE_PRODUCTS." as p,
                    ".TABLE_CATEGORIES." as subc,
                    ".TABLE_PRODUCTS_TO_CATEGORIES." as ptoc
                WHERE
                    p." . EP_IDENTIFICADOR . " = '" . $items[$filelayout['v_' . EP_IDENTIFICADOR]] . "' AND
                    p.products_id = ptoc.products_id AND
                    ptoc.categories_id = subc.categories_id
                LIMIT 1
            ";

	
        $result = tep_db_query($sql);
        $row =  tep_db_fetch_array($result);

        // determine processing status based on dropdown choice on EP menu
        if ((sizeof($row) > 1) && ($_POST['imput_mode'] == "normal" || $_POST['imput_mode'] == "update")) {
            $process_product = true;
        } elseif ((sizeof($row) == 1) && ($_POST['imput_mode'] == "normal" || $_POST['imput_mode'] == "addnew")) {
            $process_product = true;
        } else {
            $process_product = false;
        }
		
        if ($process_product == true) {
			
            while ($row)
			{
                // OK, since we got a row, the item already exists.
                // Let's get all the data we need and fill in all the fields that need to be defaulted 
                // to the current values for each language, get the description and set the vals
                foreach ($languages as $key => $lang){
                    // products_name, products_description, products_url
                    $sql2 = "SELECT * 
                            FROM ".TABLE_PRODUCTS_DESCRIPTION."
                            WHERE
                                products_id = " . $row['v_products_id'] . " AND
                                language_id = '" . $lang['id'] . "'
                            LIMIT 1
                            ";
                    $result2 = tep_db_query($sql2);
                    $row2 =  tep_db_fetch_array($result2);
                    // Need to report from ......_name_1 not ..._name_0
                    $row['v_products_name_' . $lang['id']]         = $row2['products_name'];
                    $row['v_products_description_' . $lang['id']]     = $row2['products_description'];
                    // $row['v_products_url_' . $lang['id']]         = $row2['products_url'];
                    foreach ($custom_fields[TABLE_PRODUCTS_DESCRIPTION] as $key => $name) { 
                      $row['v_' . $key . '_' . $lang['id']]         = $row2[$key];
                    }
                    // header tags controller support
                    if(isset($filelayout['v_products_head_title_tag_' . $lang['id'] ])){
                        $row['v_products_head_title_tag_' . $lang['id']]     = $row2['products_head_title_tag'];
                        $row['v_products_head_desc_tag_' . $lang['id']]     = $row2['products_head_desc_tag'];
                        $row['v_products_head_keywords_tag_' . $lang['id']]     = $row2['products_head_keywords_tag'];
                    }
                    // end: header tags controller support
                }
       
                // start with v_categories_id
                // Get the category description
                // set the appropriate variable name
                // if parent_id is not null, then follow it up.
		
				$thecategory_id = $row['v_categories_id'];
				for( $categorylevel=1; $categorylevel<=($nLevelCategorias+1); $categorylevel++){
					if ($thecategory_id){
		
						$sql3 = "SELECT parent_id, 
						                categories_image
							     FROM ".TABLE_CATEGORIES."
							     WHERE    
								        categories_id = " . $thecategory_id . '';
						$result3 = tep_db_query($sql3);
						if ($row3 = tep_db_fetch_array($result3)) {
							$temprow['v_categories_image_' . $categorylevel] = $row3['categories_image'];
						}
		
						foreach ($languages as $key => $lang){
							$sql2 = "SELECT categories_name
								     FROM ".TABLE_CATEGORIES_DESCRIPTION."
								     WHERE    
									        categories_id = " . $thecategory_id . " AND
									        language_id = " . $lang['id'];
							$result2 = tep_db_query($sql2);
							if ($row2 =  tep_db_fetch_array($result2)) {
								$temprow['v_categories_name_' . $categorylevel . '_' . $lang['id']] = $row2['categories_name'];
							}
						}
		
						// now get the parent ID if there was one
						$theparent_id = $row3['parent_id'];
						if ($theparent_id != ''){
							// there was a parent ID, lets set thecategoryid to get the next level
							$thecategory_id = $theparent_id;
						} else {
							// we have found the top level category for this item,
							$thecategory_id = false;
						}
		 
					} else {
						$temprow['v_categories_image_' . $categorylevel] = '';
						foreach ($languages as $key => $lang){
							$temprow['v_categories_name_' . $categorylevel . '_' . $lang['id']] = '';
						}
					}
				}
				
				
                // temprow has the old style low to high level categories.
				$newlevel = 1;
				// let's turn them into high to low level categories
				for( $categorylevel=$nLevelCategorias+1; $categorylevel>0; $categorylevel--){
					$found = false;
					if ($temprow['v_categories_image_' . $categorylevel] != ''){
						$row['v_categories_image_' . $newlevel] = $temprow['v_categories_image_' . $categorylevel];
						$found = true;
					}
					foreach ($languages as $key => $lang){
						if ($temprow['v_categories_name_' . $categorylevel . '_' . $lang['id']] != ''){
							$row['v_categories_name_' . $newlevel . '_' . $lang['id']] = $temprow['v_categories_name_' . $categorylevel . '_' . $lang['id']];
							$found = true;
						}
					}
					if ($found == true) {
						$newlevel++;
					}
				}

				// default the manufacturer        
                if ($row['v_manufacturers_id'] != ''){
                    $sql2 = "SELECT manufacturers_name
                        FROM ".TABLE_MANUFACTURERS."
                        WHERE
                        manufacturers_id = " . $row['v_manufacturers_id']
                        ;
                    $result2 = tep_db_query($sql2);
                    $row2 =  tep_db_fetch_array($result2);
                    $row['v_manufacturers_name'] = $row2['manufacturers_name'];
                }

                if (EP_MVS_SUPPORT == true) {
                  $result2 = tep_db_query("select vendors_name from ".TABLE_VENDORS." WHERE vendors_id = " . $row['v_vendor_id']);
                  $row2 =  tep_db_fetch_array($result2);		
                  $row['v_vendor'] = $row2['vendors_name'];
                }

/*                if (MASTER_PRODUCTS_SUPPORT == true) {
                  $result2 = tep_db_query("select products_master from ".TABLE_PRODUCTS." WHERE products_id = " . $row['v_products_id']);
                  $row2 =  tep_db_fetch_array($result2);
                  $row['v_products_master'] = $row2['vendors_name'];
                }
*/                
                //elari -
                //We check the value of tax class and title instead of the id
                //Then we add the tax to price if EP_PRICE_WITH_TAX is set to true
                $row_tax_multiplier = tep_get_tax_class_rate($row['v_tax_class_id']);
                $row['v_tax_class_title'] = tep_get_tax_class_title($row['v_tax_class_id']);
                if (EP_PRICE_WITH_TAX == true){
                    $row['v_products_price'] = $row['v_products_price'] + round(($row['v_products_price'] * $row_tax_multiplier / 100), EP_PRECISION);
                }

				// sampedro, default, por defecto
				
                // now create the internal variables that will be used
                // the $$thisvar is on purpose: it creates a variable named what ever was in $thisvar and sets the value
                foreach ($default_these as $tkey => $thisvar){
                    $$thisvar = $row[$thisvar];
                }
				
                $row =  tep_db_fetch_array($result);
            }
        
            // this is an important loop.  What it does is go thru all the fields in the incoming 
            // file and set the internal vars. Internal vars not set here are either set in the 
            // loop above for existing records, or not set at all (null values) the array values 
            // are handled separatly, although they will set variables in this loop, we won't use them.
            foreach( $filelayout as $key => $value ){
                if (!($key == 'v_date_added' && empty($items[ $value ]))) {
                     $$key = $items[ $value ];
                }
            }

            //elari... we get the tax_clas_id from the tax_title
            //on screen will still be displayed the tax_class_title instead of the id....
            if ( isset( $v_tax_class_title) ){
                $v_tax_class_id          = tep_get_tax_title_class_id($v_tax_class_title);
            }
            //we check the tax rate of this tax_class_id
                $row_tax_multiplier = tep_get_tax_class_rate($v_tax_class_id);
        
            //And we recalculate price without the included tax...
            //Since it seems display is made before, the displayed price will still include tax
            //This is same problem for the tax_clas_id that display tax_class_title
            if (EP_PRICE_WITH_TAX == true){
                $v_products_price = round( $v_products_price / (1 + ($row_tax_multiplier * .01)), EP_PRECISION);
            }
        
            // if they give us one category, they give us all categories. convert data structure to a multi-dim array
            unset ($v_categories_name); // default to not set.
            unset ($v_categories_image); // default to not set.
			foreach ($languages as $key => $lang){
			  $baselang_id = $lang['id'];
			  break;
			}
            if ( isset( $filelayout['v_categories_name_1_' . $baselang_id] ) ){
				$v_categories_name = array();
				$v_categories_image = array();
                $newlevel = 1;
                for( $categorylevel=$nLevelCategorias; $categorylevel>0; $categorylevel--){
					$found = false;
					if ($items[$filelayout['v_categories_image_' . $categorylevel]] != '') {
					  $v_categories_image[$newlevel] = $items[$filelayout['v_categories_image_' . $categorylevel]];
					  $found = true;
					}
					foreach ($languages as $key => $lang){
						if ( $items[$filelayout['v_categories_name_' . $categorylevel . '_' . $lang['id']]] != ''){
							$v_categories_name[$newlevel][$lang['id']] = $items[$filelayout['v_categories_name_' . $categorylevel . '_' . $lang['id']]];
							$found = true;
						}
                    }
					if ($found == true) {
					  $newlevel++;
					}
                }
                while( $newlevel < $nLevelCategorias+1){
                    $v_categories_image[$newlevel] = ''; // default the remaining items to nothing
					foreach ($languages as $key => $lang){
                      $v_categories_name[$newlevel][$lang['id']] = ''; // default the remaining items to nothing
					}
                    $newlevel++;
                }
            }
        
            if (ltrim(rtrim($v_products_quantity)) == '') {
                $v_products_quantity = 1;
            }
        
            if (empty($v_date_avail)) {
                $v_date_avail = 'NULL';
            } else {
                $v_date_avail = "'" . date("Y-m-d H:i:s",strtotime($v_date_avail)) . "'";
            }
        
            if (empty($v_date_added)) {
                $v_date_added = "'" . date("Y-m-d H:i:s") . "'";
            } else {
                $v_date_added = "'" . date("Y-m-d H:i:s",strtotime($v_date_added)) . "'";
            }
        
            // default the stock if they spec'd it or if it's blank
            if (isset($v_status_current)){
              $v_db_status = strval($v_status_current); // default to current value
            } else {
              $v_db_status = '1'; // default to active
            }
            if (trim($v_status) == EP_TEXT_INACTIVE){
                // they told us to deactivate this item
                $v_db_status = '0';
            } elseif (trim($v_status) == EP_TEXT_ACTIVE) {
                $v_db_status = '1';
            }    
        
            if (EP_INACTIVATE_ZERO_QUANTITIES == true && $v_products_quantity == 0) {
                // if they said that zero qty products should be deactivated, let's deactivate if the qty is zero
                $v_db_status = '0';
            }
        
            if ($v_manufacturer_id==''){
                $v_manufacturer_id="NULL";
            }
        
            if (trim($v_products_image)==''){
                $v_products_image = EP_DEFAULT_IMAGE_PRODUCT;
            }
        
            // OK, we need to convert the manufacturer's name into id's for the database
            if ( isset($v_manufacturers_name) && $v_manufacturers_name != '' ){
                $sql = "SELECT man.manufacturers_id
                    FROM ".TABLE_MANUFACTURERS." as man
                    WHERE
                        man.manufacturers_name = '" . tep_db_input($v_manufacturers_name) . "'";
                $result = tep_db_query($sql);
                $row =  tep_db_fetch_array($result);
                if ( $row != '' ){
                    foreach( $row as $item ){
                        $v_manufacturer_id = $item;
                    }
                } else {
                    // to add, we need to put stuff in categories and categories_description
                    $sql = "SELECT MAX( manufacturers_id) max FROM ".TABLE_MANUFACTURERS;
                    $result = tep_db_query($sql);
                    $row =  tep_db_fetch_array($result);
                    $max_mfg_id = $row['max']+1;
                    // default the id if there are no manufacturers yet
                    if (!is_numeric($max_mfg_id) ){
                        $max_mfg_id=1;
                    }
        
                    // Uncomment this query if you have an older 2.2 codebase
                    /*
                    $sql = "INSERT INTO ".TABLE_MANUFACTURERS."(
                        manufacturers_id,
                        manufacturers_name,
                        manufacturers_image
                        ) VALUES (
                        $max_mfg_id,
                        '$v_manufacturers_name',
                        '".EP_DEFAULT_IMAGE_MANUFACTURER."'
                        )";
                    */
        
                    // Comment this query out if you have an older 2.2 codebase
                    $sql = "INSERT INTO ".TABLE_MANUFACTURERS."(
                        manufacturers_id,
                        manufacturers_name,
                        manufacturers_image,
                        date_added,
                        last_modified,
						manufacturers_status
                        ) VALUES (
                        $max_mfg_id,
                        '".tep_db_input($v_manufacturers_name)."',
                        '".EP_DEFAULT_IMAGE_MANUFACTURER."',
                        '".date("Y-m-d H:i:s")."',
                        '".date("Y-m-d H:i:s")."',
						1
                        )";
                    $result = tep_db_query($sql);
                    $v_manufacturer_id = $max_mfg_id;
        
                    $sql = "INSERT INTO ".TABLE_MANUFACTURERS_INFO."(
                        manufacturers_id,
                        manufacturers_url,
                        languages_id
                        ) VALUES (
                        $max_mfg_id,
                        '',
                        '".EP_DEFAULT_LANGUAGE_ID."'
                        )";
                    $result = tep_db_query($sql);
                }
            }
            
            // if the categories names are set then try to update them
			foreach ($languages as $key => $lang){
			  $baselang_id = $lang['id'];
			  break;
			}
					
            if ( isset( $filelayout['v_categories_name_1_' . $baselang_id] ) ){
                // start from the highest possible category and work our way down from the parent
                $v_categories_id = 0;
                $theparent_id = 0;
                for ( $categorylevel=$nLevelCategorias+1; $categorylevel>0; $categorylevel-- ){
				  //foreach ($languages as $key => $lang){
                    $thiscategoryname = $v_categories_name[$categorylevel][$baselang_id];
					
					
                    if ( $thiscategoryname != ''){
                        // we found a category name in this field, look for database entry
                        $sql = "SELECT cat.categories_id
                            FROM ".TABLE_CATEGORIES." as cat, 
                                 ".TABLE_CATEGORIES_DESCRIPTION." as des
                            WHERE
                                cat.categories_id = des.categories_id AND
                                des.language_id = " . $baselang_id . " AND
                                cat.parent_id = " . $theparent_id . " AND
                                des.categories_name like '" . tep_db_input($thiscategoryname) . "'";
                        $result = tep_db_query($sql);
                        $row =  tep_db_fetch_array($result);

                        if ( $row != '' ){
                            // we have an existing category, update image and date
							foreach( $row as $item ){
                                $thiscategoryid = $item;
                            }
							$cat_image = '';
							if (!empty($v_categories_image[$categorylevel])) {
							   $cat_image = "categories_image='".tep_db_input($v_categories_image[$categorylevel])."', ";
							} elseif (isset($filelayout['v_categories_image_' . $categorylevel])) {
							   $cat_image = "categories_image='', ";
							} 
                            $query = "UPDATE ".TABLE_CATEGORIES."
                                      SET 
                                        $cat_image
                                        last_modified = '".date("Y-m-d H:i:s")."'
                                      WHERE 
                                        categories_id = '".$row['categories_id']."'
                                      LIMIT 1";
                
                            tep_db_query($query);
                        } else {
                            // to add, we need to put stuff in categories and categories_description
                            $sql = "SELECT MAX( categories_id) max FROM ".TABLE_CATEGORIES;
                            $result = tep_db_query($sql);
                            $row =  tep_db_fetch_array($result);
                            $max_category_id = $row['max']+1;
                            if (!is_numeric($max_category_id) ){
                                $max_category_id=1;
                            }
                            $sql = "INSERT INTO ".TABLE_CATEGORIES." (
                                        categories_id,
                                        parent_id,
                                        categories_image,
                                        sort_order,
                                        date_added,
                                        last_modified
                                   ) VALUES (
                                        $max_category_id,
                                        $theparent_id,
                                        '".tep_db_input($v_categories_image[$categorylevel])."',
                                        0,
                                        '".date("Y-m-d H:i:s")."',
                                        '".date("Y-m-d H:i:s")."'
                                   )";
                            $result = tep_db_query($sql);
                            
                            foreach ($languages as $key => $lang){
                                $sql = "INSERT INTO ".TABLE_CATEGORIES_DESCRIPTION." (
                                                categories_id,
                                                language_id,
                                                categories_name
                                       ) VALUES (
                                                $max_category_id,
                                                '".$lang['id']."',
                                                '".(!empty($v_categories_name[$categorylevel][$lang['id']])?tep_db_input($v_categories_name[$categorylevel][$lang['id']]):'')."'
                                       )";
                                tep_db_query($sql);
                            }
                            
                            $thiscategoryid = $max_category_id;
                        }
                        // the current catid is the next level's parent
                        $theparent_id = $thiscategoryid;
                        $v_categories_id = $thiscategoryid; // keep setting this, we need the lowest level category ID later
                    }
				 // }
                }
            }

			// Sampedro, cambio de produts_model a productos_id
			eval( '$sIdentificador = $v_' . EP_IDENTIFICADOR . ';' );
			
            //if ($v_products_model != "") {
			if( $sIdentificador != "" || (EP_IDENTIFICADOR == 'products_id') ) {
                //   products_model exists!
                // foreach ($items as $tkey => $item)
					// $sHtml .= print_el($item);
      
                // find the vendor id from the name imported
                if (EP_MVS_SUPPORT == true) {
                  $vend_result = tep_db_query("SELECT vendors_id FROM ".TABLE_VENDORS." WHERE vendors_name = '". $v_vendor . "'");
                  $vend_row = tep_db_fetch_array($vend_result);
                  $v_vendor_id = $vend_row['vendors_id'];
                }

				// Sampedro, cambio de products_model a products_id
                // process the PRODUCTS table
                $result = tep_db_query("SELECT products_id FROM ".TABLE_PRODUCTS." WHERE (" . EP_IDENTIFICADOR . " = '". $sIdentificador . "')");
        
				// Log
				$sHtml .= $v_products_name_3 . ' [' . EP_IDENTIFICADOR . ': ' . $sIdentificador . ']';

/*while( $aDato = tep_db_fetch_array($result) )
	echo $aDato['products_id'] . '<br/>';
		        die('a');*/
				
                // First we check to see if this is a product in the current db.
                if (tep_db_num_rows($result) == 0)  {
                
                    //   insert into products
                    $sHtml .= "<font color='green'> !Nuevo producto!</font><br />";
					$sNuevoEditar = '#DDF2C7';
        
                    // /////////////////////////////////////////////////////////////////////
                    //
                    // Start: Support for other contributions
                    //
                    // /////////////////////////////////////////////////////////////////////
                    $ep_additional_fields = '';
                    $ep_additional_data = '';

                    if (EP_ADDITIONAL_IMAGES == true) { 
                      $ep_additional_fields .= 'products_image_description,';
                      $ep_additional_data .= "'".tep_db_input($v_products_image_description)."',";
                    } 
			
                    foreach ($custom_fields[TABLE_PRODUCTS] as $key => $name) { 
                      $ep_additional_fields .= $key . ',';
                    }

                    foreach ($custom_fields[TABLE_PRODUCTS] as $key => $name) { 
                      $tmp_var = 'v_' . $key;
                      $ep_additional_data .= "'" . $$tmp_var . "',";
                    }

                    if (EP_MORE_PICS_6_SUPPORT == true) { 
                      $ep_additional_fields .= 'products_subimage1,products_subimage2,products_subimage3,products_subimage4,products_subimage5,products_subimage6,';
                      $ep_additional_data .= "'$v_products_subimage1','$v_products_subimage2','$v_products_subimage3','$v_products_subimage4','$v_products_subimage5','$v_products_subimage6',";
                    }    
        
                    if (EP_ULTRPICS_SUPPORT == true) { 
                      $ep_additional_fields .= 'products_image_med,products_image_lrg,products_image_sm_1,products_image_xl_1,products_image_sm_2,products_image_xl_2,products_image_sm_3,products_image_xl_3,products_image_sm_4,products_image_xl_4,products_image_sm_5,products_image_xl_5,products_image_sm_6,products_image_xl_6,';
                      $ep_additional_data .= "'$v_products_image_med','$v_products_image_lrg','$v_products_image_sm_1','$v_products_image_xl_1','$v_products_image_sm_2','$v_products_image_xl_2','$v_products_image_sm_3','$v_products_image_xl_3','$v_products_image_sm_4','$v_products_image_xl_4','$v_products_image_sm_5','$v_products_image_xl_5','$v_products_image_sm_6','$v_products_image_xl_6',";
                    }
					
                    if (EP_PDF_UPLOAD_SUPPORT == true) { 
                      $ep_additional_fields .= 'products_pdfupload,products_fileupload,';
                      $ep_additional_data .= "'$v_products_pdfupload','$v_products_fileupload',";
                    }
					
                    if (EP_MVS_SUPPORT == true) {
                      $ep_additional_fields .= 'vendors_id,';
                      $ep_additional_data .= "'$v_vendor_id',";
                    }
			
                    if (MASTER_PRODUCTS_SUPPORT == true) {
                      $ep_additional_fields .= 'products_master, products_master_status,';
                      $ep_additional_data .= "'$v_products_master','$v_products_master_status',";
                    }

                    // /////////////////////////////////////////////////////////////////////
                    // End: Support for other contributions
                    // /////////////////////////////////////////////////////////////////////
                    
                    $query = "INSERT INTO ".TABLE_PRODUCTS." (
                                products_image,
                                $ep_additional_fields
                                products_model,
                                products_price,
                                products_status,
                                products_last_modified,
                                products_date_added,
                                products_date_available,
                                products_tax_class_id,
                                products_weight,
                                products_quantity,
                                manufacturers_id )
                              VALUES (
                                ".(!empty($v_products_image)?"'".$v_products_image."'":'NULL').",
                                $ep_additional_data
                                '$v_products_model',
                                '$v_products_price',
                                '$v_db_status',
                                '".date("Y-m-d H:i:s")."',
                                ".$v_date_added.",
                                ".$v_date_avail.",
                                '$v_tax_class_id',
                                '$v_products_weight',
                                '$v_products_quantity',
                                ".(!empty($v_manufacturer_id)?$v_manufacturer_id:'NULL').")
                                ";
                    $result = tep_db_query($query);
                    
                    $v_products_id = tep_db_insert_id();
        
                } else {

					// existing product(s), get the id from the query
                  // and update the product data
                  while ($row = tep_db_fetch_array($result)) {

                    $v_products_id = $row['products_id'];
                    $sHtml .= "<font color='black'> Actualizado</font><br />";
					$sNuevoEditar = '#EFEFEF';
                    
                    // /////////////////////////////////////////////////////////////////////
                    //
                    // Start: Support for other contributions
                    //
                    // /////////////////////////////////////////////////////////////////////
                    $ep_additional_updates = '';

                    foreach ($custom_fields[TABLE_PRODUCTS] as $key => $name) { 
                      $tmp_var = 'v_' . $key;
                      $ep_additional_updates .= $key . "='" . $$tmp_var . "',";
                    }

                    if (EP_ADDITIONAL_IMAGES == true && isset($v_products_image_description)) { 
                      $ep_additional_updates .= "products_image_description='".tep_db_input($v_products_image_description)."',";
                    } 

                    if (EP_MORE_PICS_6_SUPPORT == true) { 
                      $ep_additional_updates .= "products_subimage1='$v_products_subimage1',products_subimage2='$v_products_subimage2',products_subimage3='$v_products_subimage3',products_subimage4='$v_products_subimage4',products_subimage5='$v_products_subimage5',products_subimage6='$v_products_subimage6',";
                    }    

                    if (EP_ULTRPICS_SUPPORT == true) { 
                      $ep_additional_updates .= "products_image_med='$v_products_image_med',products_image_lrg='$v_products_image_lrg',products_image_sm_1='$v_products_image_sm_1',products_image_xl_1='$v_products_image_xl_1',products_image_sm_2='$v_products_image_sm_2',products_image_xl_2='$v_products_image_xl_2',products_image_sm_3='$v_products_image_sm_3',products_image_xl_3='$v_products_image_xl_3',products_image_sm_4='$v_products_image_sm_4',products_image_xl_4='$v_products_image_xl_4',products_image_sm_5='$v_products_image_sm_5',products_image_xl_5='$v_products_image_xl_5',products_image_sm_6='$v_products_image_sm_6',products_image_xl_6='$v_products_image_xl_6',";
                    }

                    if (EP_PDF_UPLOAD_SUPPORT == true) {
                      $ep_additional_updates .= "products_pdfupload='$v_products_pdfupload',products_fileupload='$v_products_fileupload',";
                    }

                    if (EP_MVS_SUPPORT == true) {
                      $ep_additional_updates .= "vendors_id='$v_vendor_id',";
                    }

                    if (MASTER_PRODUCTS_SUPPORT == true) {
                      $ep_additional_updates .= "products_master='$v_products_master',products_master_status='$v_products_master_status',";
                    }
                    
                    // /////////////////////////////////////////////////////////////////////
                    // End: Support for other contributions
                    // /////////////////////////////////////////////////////////////////////
                    // only include the products image if it has been included in the spreadsheet
                    $tmp_products_image_update = '';
                    if (isset($v_products_image)) {
                      $tmp_products_image_update = "products_image=".(!empty($v_products_image)?"'".$v_products_image."'":'NULL').", 
										    ";
                      if (EP_ADDITIONAL_IMAGES == true && isset($filelayout['v_products_image'])) { 
                        $tmp_products_image_update .= "products_image_med=NULL, 
                                                     products_image_pop=NULL, ";
                      }
                    }
					// Cambiar cantidad para aumentar o disminuir			
					if( preg_match( '/[\+|-]/i', $v_products_quantity ) )
					{
						// Obtenemos la cantidad que disponemos para restar o sumar
						$aDatos = tep_db_query( 'select products_quantity from products where (products_id = ' . $v_products_id . ') LIMIT 1' );
						$aDato = tep_db_fetch_array( $aDatos );
						eval( '$v_products_quantity = $aDato[\'products_quantity\'] ' . trim( $v_products_quantity ) . ';' );
					}
					
                    $query = "UPDATE ".TABLE_PRODUCTS."
                              SET
                                products_price='$v_products_price', 
                                $tmp_products_image_update 
                                $ep_additional_updates
                                products_weight='$v_products_weight', 
                                products_tax_class_id='$v_tax_class_id', 
                                products_date_available=".$v_date_avail.", 
                                products_date_added=".$v_date_added.", 
                                products_last_modified='".date("Y-m-d H:i:s")."', 
                                products_quantity = $v_products_quantity, 
                                manufacturers_id = ".(!empty($v_manufacturer_id)?$v_manufacturer_id:'NULL').", 
                                products_status = $v_db_status
                              WHERE
                                (products_id = $v_products_id)
                              LIMIT 1";
        
                    tep_db_query($query);
                  }
                }
		
		  if (isset($v_products_specials_price)) {
		      if (EP_SPPC_SUPPORT == true) { $SPPC_extra_query = ' and customers_group_id = 0'; } else { $SPPC_extra_query = ''; }
			  $result = tep_db_query('select * from '.TABLE_SPECIALS.' WHERE products_id = ' . $v_products_id . $SPPC_extra_query );

				if (EP_PRICE_WITH_TAX == true){
					$v_products_specials_price = round( $v_products_specials_price / (1 + ($row_tax_multiplier * .01)), EP_PRECISION);
				}
			  
			  if ($v_products_specials_price == '') {
			  
				  $result = tep_db_query('DELETE FROM '.TABLE_SPECIALS.' WHERE products_id = ' . $v_products_id . $SPPC_extra_query );
			      if (EP_SPPC_SUPPORT == true) { 
				    $result = tep_db_query('DELETE FROM specials_retail_prices WHERE products_id = ' . $v_products_id );
			      }

			  } else {
				  if ($specials = tep_db_fetch_array($result)) {
					  $sql_data_array = array('products_id' => $v_products_id,
											  'specials_new_products_price' => $v_products_specials_price,
											  'specials_last_modified' => 'now()'
					  );
					  tep_db_perform(TABLE_SPECIALS, $sql_data_array, 'update', 'specials_id = '.$specials['specials_id']);
					  
			          if (EP_SPPC_SUPPORT == true) { 
					    $sql_data_array = array('products_id' => $v_products_id,
											    'specials_new_products_price' => $v_products_specials_price
					    );
					    tep_db_perform('specials_retail_prices', $sql_data_array, 'update', 'products_id = '.$v_products_id);
				      }
				  } else {
					  $sql_data_array = array('products_id' => $v_products_id,
											  'specials_new_products_price' => $v_products_specials_price,
											  'specials_date_added' => 'now()',
											  'status' => '1'
					  );
		              if (EP_SPPC_SUPPORT == true) { $sql_data_array = array_merge($sql_data_array,array('customers_group_id' => '0')); }
					  tep_db_perform(TABLE_SPECIALS, $sql_data_array, 'insert');
					  
			          if (EP_SPPC_SUPPORT == true) { 
					    $sql_data_array = array('products_id' => $v_products_id,
											    'specials_new_products_price' => $v_products_specials_price,
											    'status' => '1',
											    'customers_group_id' => '0'
					    );
					    tep_db_perform('specials_retail_prices', $sql_data_array, 'insert');
				      }
				  }
			  }
          }
			
			// upgrade method only. Note: if you upgrades more and more the id number will be hihger. I suggest that picture upgrades use last
			// or delete always v_products_images_image_1 column to prevent effect
			if (EP_PRODUCTS_IMAGES == true) { 
				if (isset($filelayout['v_products_images_image_1'])) {
					tep_db_query("delete from " . TABLE_PRODUCTS_IMAGES . " where products_id = '" . (int)$v_products_id . "'");
					for ($i=1;$i<=EP_PRODUCTS_IMAGES_MAX;$i++) {
						$pi_htmlcontent_var = 'v_products_images_htmlcontent_'.$i;
						$pi_image_var = 'v_products_images_image_'.$i;
						if (!empty($$pi_image_var) || !empty($$pi_htmlcontent_var)) {
							tep_db_query("insert into " . TABLE_PRODUCTS_IMAGES . " (products_id, image, htmlcontent, sort_order) values ('" . (int)$v_products_id . "', '" . tep_db_input($$pi_image_var) . "', '" . tep_db_input($$pi_htmlcontent_var) . "', '" . tep_db_input($$i) . "')");
						}
					}
				}
			}
        
			if (EP_ADDITIONAL_IMAGES == true) { 
			  if (isset($filelayout['v_products_image_2'])) {
				  tep_db_query("delete from " . TABLE_ADDITIONAL_IMAGES . " where products_id = '" . (int)$v_products_id . "'");
				  for ($i=2;$i<=EP_ADDITIONAL_IMAGES_MAX+1;$i++) {
					$ai_description_var = 'v_products_image_description_'.$i;
					$ai_image_var = 'v_products_image_'.$i;
					if (!empty($$ai_image_var) || !empty($$ai_description_var)) {
					  tep_db_query("insert into " . TABLE_ADDITIONAL_IMAGES . " (products_id, images_description, thumb_images) values ('" . (int)$v_products_id . "', '" . tep_db_input($$ai_description_var) . "', '" . tep_db_input($$ai_image_var) . "')");
					}
				  }
			  }
			}
			
			
			
                // process the PRODUCTS_DESCRIPTION table
                foreach ($languages as $tkey => $lang){

                    $doit = false;
                    foreach ($custom_fields[TABLE_PRODUCTS_DESCRIPTION] as $key => $name) { 
                      if (isset($filelayout['v_' . $key . '_'.$lang['id']])) { $doit = true; }
                    }

                    if ( isset($filelayout['v_products_name_'.$lang['id']]) || isset($filelayout['v_products_description_'.$lang['id']]) || isset($filelayout['v_products_url_'.$lang['id']]) || isset($filelayout['v_products_head_title_tag_'.$lang['id']]) || $doit == true ) {
        
                        $sql = "SELECT * FROM ".TABLE_PRODUCTS_DESCRIPTION." WHERE
                                products_id = $v_products_id AND
                                language_id = " . $lang['id'];
                        $result = tep_db_query($sql);
        
                        $products_var = 'v_products_name_'.$lang['id'];
                        $description_var = 'v_products_description_'.$lang['id'];
                        // $url_var = 'v_products_url_'.$lang['id'];

                        // /////////////////////////////////////////////////////////////////////
                        //
                        // Start: Support for other contributions
                        //
                        // /////////////////////////////////////////////////////////////////////
                        $ep_additional_updates = '';
                        $ep_additional_fields = '';
                        $ep_additional_data = '';

                        foreach ($custom_fields[TABLE_PRODUCTS_DESCRIPTION] as $key => $name) { 
                          $tmp_var = 'v_' . $key . '_' . $lang['id'];
                          $ep_additional_updates .= $key . " = '" . tep_db_input($$tmp_var) ."',";
                          $ep_additional_fields .= $key . ",";
                          $ep_additional_data .= "'". tep_db_input($$tmp_var) . "',";
                        }

                        // header tags controller support
                        if (isset($filelayout['v_products_head_title_tag_'.$lang['id']])){
                          $head_title_tag_var = 'v_products_head_title_tag_'.$lang['id'];
                          $head_desc_tag_var = 'v_products_head_desc_tag_'.$lang['id'];
                          $head_keywords_tag_var = 'v_products_head_keywords_tag_'.$lang['id'];
        
                          $ep_additional_updates .= "products_head_title_tag = '" . tep_db_input($$head_title_tag_var) ."', products_head_desc_tag = '" . tep_db_input($$head_desc_tag_var) ."', products_head_keywords_tag = '" . tep_db_input($$head_keywords_tag_var);
                          $ep_additional_fields .= "products_head_title_tag,products_head_desc_tag,products_head_keywords_tag,";
                          $ep_additional_data .= "'". tep_db_input($$head_title_tag_var) . "','". tep_db_input($$head_desc_tag_var) . "','". tep_db_input($$head_keywords_tag_var);
                        }
                        // end: header tags controller support
                        
                        // /////////////////////////////////////////////////////////////////////
                        // End: Support for other contributions
                        // /////////////////////////////////////////////////////////////////////
        
                        
                        // existing product?
                        if (tep_db_num_rows($result) > 0) {
                            // already in the description, let's just update it
                            $sql =
                                "UPDATE ".TABLE_PRODUCTS_DESCRIPTION." 
                                 SET
                                    products_name='" . tep_db_input($$products_var) . "',
                                    products_description='" . tep_db_input($$description_var) . "'
                                    " . ($ep_additional_updates != '' ? ', ' . $ep_additional_updates : '') . "
                                 WHERE
                                    products_id = '$v_products_id' AND
                                    language_id = '".$lang['id']."'
                                 LIMIT 1";
								 
							 
                            $result = tep_db_query($sql);
							
							
        
                        } else {
        
                            // nope, this is a new product description
                            $result = tep_db_query($sql);
                            $sql =
                                "INSERT INTO ".TABLE_PRODUCTS_DESCRIPTION."
                                    ( products_id,
                                      language_id,
                                      products_name,
                                      products_description
                                      " . ($ep_additional_fields != '' ? ', ' . $ep_additional_fields : '') . "
                                    )
                                 VALUES (
                                        '" . $v_products_id . "',
                                        " . $lang['id'] . ",
                                        '". tep_db_input($$products_var) . "',
                                        '". tep_db_input($$description_var) . "'
										" . ($ep_additional_fields != '' ? ', ' . $ep_additional_data : '') . "
                                        )";
                            $result = tep_db_query($sql);
                        }
                    }
                }
        

                if (isset($v_categories_id)){
                    //find out if this product is listed in the category given
                    $result_incategory = tep_db_query('SELECT
                                '.TABLE_PRODUCTS_TO_CATEGORIES.'.products_id,
                                '.TABLE_PRODUCTS_TO_CATEGORIES.'.categories_id
                                FROM
                                    '.TABLE_PRODUCTS_TO_CATEGORIES.'
                                WHERE
                                '.TABLE_PRODUCTS_TO_CATEGORIES.'.products_id='.$v_products_id.' AND
                                '.TABLE_PRODUCTS_TO_CATEGORIES.'.categories_id='.$v_categories_id);
        
                    if (tep_db_num_rows($result_incategory) == 0) {
                        // nope, this is a new category for this product
                        $res1 = tep_db_query('INSERT INTO '.TABLE_PRODUCTS_TO_CATEGORIES.' (products_id, categories_id)
                                              VALUES ("' . $v_products_id . '", "' . $v_categories_id . '")');
                    } else {
                        // already in this category, nothing to do!
                    }
                }
        
                // this is for the cross sell contribution
                if (isset($v_cross_sell)){
                  tep_db_query("delete from ".TABLE_PRODUCTS_XSELL." where products_id = " . $v_products_id . " or xsell_id = " . $v_products_id . "");
                  if (!empty($v_cross_sell)){
                    $xsells_array = explode(',',$v_cross_sell);
                      foreach ($xsells_array as $xs_key => $xs_model ) {
                        $cross_sell_sql = "select products_id from ".TABLE_PRODUCTS." where products_model = '" . trim($xs_model) . "' limit 1";
                        $cross_sell_result = tep_db_query($cross_sell_sql);
                        $cross_sell_row = tep_db_fetch_array($cross_sell_result);
                        
                        tep_db_query("insert into ".TABLE_PRODUCTS_XSELL." (products_id, xsell_id, sort_order) 
                                      values ( ".$v_products_id.", ".$cross_sell_row['products_id'].", 1)");
                        tep_db_query("insert into ".TABLE_PRODUCTS_XSELL." (products_id, xsell_id, sort_order) 
								  values ( ".$cross_sell_row['products_id'].", ".$v_products_id.", 1)");
                    }
                  }
		}

                // for the separate prices per customer (SPPC) module
                $ll=1;
						
                if (isset($v_customer_price_1)){
                    
                    if (($v_customer_group_id_1 == '') AND ($v_customer_price_1 != ''))  {
                        $sHtml .= "<font color=red>ERROR - v_customer_group_id and v_customer_price - 234</font>";
                        die();
                    }
                    // they spec'd some prices, so clear all existing entries
                    $result = tep_db_query('
                                DELETE
                                FROM
                                    '.TABLE_PRODUCTS_GROUPS.'
                                WHERE
                                    products_id = ' . $v_products_id
                                );
                    // and insert the new record
				if (isset($v_customer_price_1) && $v_customer_price_1 != ''){
						$v_customer_price_1 = round( $v_customer_price_1 / (1 + ($row_tax_multiplier * .01)), EP_PRECISION);
					
                        $result = tep_db_query('
                                    INSERT INTO
                                        '.TABLE_PRODUCTS_GROUPS.'
                                    VALUES
                                    (
                                        ' . $v_customer_group_id_1 . ',
                                        ' . $v_customer_price_1 . ',
                                        ' . $v_products_id . ',
										1,
										1
                                        )'
                                    );
                    }
				if (isset($v_customer_price_2) && $v_customer_price_2 != ''){
						$v_customer_price_2 = round( $v_customer_price_2 / (1 + ($row_tax_multiplier * .01)), EP_PRECISION);
                        $result = tep_db_query('
                                    INSERT INTO
                                        '.TABLE_PRODUCTS_GROUPS.'
                                    VALUES
                                    (
                                        ' . $v_customer_group_id_2 . ',
                                        ' . $v_customer_price_2 . ',
                                        ' . $v_products_id . ',
										1,
										1
                                        )'
                                    );
                    }
				if (isset($v_customer_price_3) && $v_customer_price_3 != ''){
						$v_customer_price_3 = round( $v_customer_price_3 / (1 + ($row_tax_multiplier * .01)), EP_PRECISION);
                        $result = tep_db_query('
                                    INSERT INTO
                                        '.TABLE_PRODUCTS_GROUPS.'
                                    VALUES
                                    (
                                        ' . $v_customer_group_id_3 . ',
                                        ' . $v_customer_price_3 . ',
                                        ' . $v_products_id . ',
										1,
										1
                                        )'
                                    );
                    }
				if (isset($v_customer_price_4) && $v_customer_price_4 != ''){
						$v_customer_group_id_4 = round( $v_customer_group_id_4 / (1 + ($row_tax_multiplier * .01)), EP_PRECISION);
                        $result = tep_db_query('
                                    INSERT INTO
                                        '.TABLE_PRODUCTS_GROUPS.'
                                    VALUES
                                    (
                                        ' . $v_customer_group_id_4 . ',
                                        ' . $v_customer_price_4 . ',
                                        ' . $v_products_id . ',
										1,
										1
                                        )'
                                    );
                    }

                    if (isset($v_customer_specials_price_1)) {
                    $result = tep_db_query('select * from '.TABLE_SPECIALS.' WHERE products_id = ' . $v_products_id . ' and customers_group_id = 1' );

                      if ($v_customer_specials_price_1 == '') {
			  
                        $result = tep_db_query('DELETE FROM '.TABLE_SPECIALS.' WHERE products_id = ' . $v_products_id . ' and customers_group_id = 1' );

                      } else {
						$v_customer_specials_price_1 = round( $v_customer_specials_price_1 / (1 + ($row_tax_multiplier * .01)), EP_PRECISION);
                        if ($specials = tep_db_fetch_array($result)) {
					  $sql_data_array = array('products_id' => $v_products_id,
											  'specials_new_products_price' => $v_customer_specials_price_1,
											  'specials_last_modified' => 'now()'
					  );
					  tep_db_perform(TABLE_SPECIALS, $sql_data_array, 'update', 'specials_id = '.$specials['specials_id']);
                        } else {
					  $sql_data_array = array('products_id' => $v_products_id,
											  'specials_new_products_price' => $v_customer_specials_price_1,
											  'specials_date_added' => 'now()',
											  'status' => '1',
											  'customers_group_id' => '1'
					  );
					  tep_db_perform(TABLE_SPECIALS, $sql_data_array, 'insert');
                        }
                      }
                    }
			
                }
                // end: separate prices per customer (SPPC) module
        
        
                // VJ product attribs begin
                if (isset($v_attribute_options_id_1)){
                    $attribute_rows = 1; // master row count
        
                    // product options count
                    $attribute_options_count = 1;
                    $v_attribute_options_id_var = 'v_attribute_options_id_' . $attribute_options_count;
        
                    while (isset($$v_attribute_options_id_var) && !empty($$v_attribute_options_id_var)) {
                        // remove product attribute options linked to this product before proceeding further
                        // this is useful for removing attributes linked to a product
                        $attributes_clean_query = "delete from " . TABLE_PRODUCTS_ATTRIBUTES . " where products_id = '" . (int)$v_products_id . "' and options_id = '" . (int)$$v_attribute_options_id_var . "'";
        
                        tep_db_query($attributes_clean_query);
        
                        $attribute_options_query = "select products_options_name from " . TABLE_PRODUCTS_OPTIONS . " where products_options_id = '" . (int)$$v_attribute_options_id_var . "'";
        
                        $attribute_options_values = tep_db_query($attribute_options_query);
        
                        // option table update begin
                        if ($attribute_rows == 1) {
                            // insert into options table if no option exists
                            if (tep_db_num_rows($attribute_options_values) <= 0) {
                                for ($i=0, $n=sizeof($languages); $i<$n; $i++) {
                                    $lid = $languages[$i]['id'];
        
                                  $v_attribute_options_name_var = 'v_attribute_options_name_' . $attribute_options_count . '_' . $lid;
        
                                    if (isset($$v_attribute_options_name_var)) {
                                        $attribute_options_insert_query = "insert into " . TABLE_PRODUCTS_OPTIONS . " (products_options_id, language_id, products_options_name) values ('" . (int)$$v_attribute_options_id_var . "', '" . (int)$lid . "', '" . $$v_attribute_options_name_var . "')";
        
                                        $attribute_options_insert = tep_db_query($attribute_options_insert_query);
                                    }
                                }
                            } else { // update options table, if options already exists
                                for ($i=0, $n=sizeof($languages); $i<$n; $i++) {
                                    $lid = $languages[$i]['id'];
        
                                    $v_attribute_options_name_var = 'v_attribute_options_name_' . $attribute_options_count . '_' . $lid;
        
                                    if (isset($$v_attribute_options_name_var)) {
                                        $attribute_options_update_lang_query = "select products_options_name from " . TABLE_PRODUCTS_OPTIONS . " where products_options_id = '" . (int)$$v_attribute_options_id_var . "' and language_id ='" . (int)$lid . "'";
        
                                        $attribute_options_update_lang_values = tep_db_query($attribute_options_update_lang_query);
        
                                        // if option name doesn't exist for particular language, insert value
                                        if (tep_db_num_rows($attribute_options_update_lang_values) <= 0) {
                                            $attribute_options_lang_insert_query = "insert into " . TABLE_PRODUCTS_OPTIONS . " (products_options_id, language_id, products_options_name) values ('" . (int)$$v_attribute_options_id_var . "', '" . (int)$lid . "', '" . $$v_attribute_options_name_var . "')";
        
                                            $attribute_options_lang_insert = tep_db_query($attribute_options_lang_insert_query);
                                        } else { // if option name exists for particular language, update table
                                            $attribute_options_update_query = "update " . TABLE_PRODUCTS_OPTIONS . " set products_options_name = '" . $$v_attribute_options_name_var . "' where products_options_id ='" . (int)$$v_attribute_options_id_var . "' and language_id = '" . (int)$lid . "'";
        
                                            $attribute_options_update = tep_db_query($attribute_options_update_query);
                                        }
                                    }
                                }
                            }
                        }
                        // option table update end
        
                        // product option values count
                        $attribute_values_count = 1;
                        $v_attribute_values_id_var = 'v_attribute_values_id_' . $attribute_options_count . '_' . $attribute_values_count;
        
                        while (isset($$v_attribute_values_id_var) && !empty($$v_attribute_values_id_var)) {
                            $attribute_values_query = "select products_options_values_name from " . TABLE_PRODUCTS_OPTIONS_VALUES . " where products_options_values_id = '" . (int)$$v_attribute_values_id_var . "'";
        
                            $attribute_values_values = tep_db_query($attribute_values_query);
        
                            // options_values table update begin
                            if ($attribute_rows == 1) {
                                // insert into options_values table if no option exists
                                if (tep_db_num_rows($attribute_values_values) <= 0) {
                                    for ($i=0, $n=sizeof($languages); $i<$n; $i++) {
                                        $lid = $languages[$i]['id'];
        
                                        $v_attribute_values_name_var = 'v_attribute_values_name_' . $attribute_options_count . '_' . $attribute_values_count . '_' . $lid;
        
                                        if (isset($$v_attribute_values_name_var)) {
                                            $attribute_values_insert_query = "insert into " . TABLE_PRODUCTS_OPTIONS_VALUES . " (products_options_values_id, language_id, products_options_values_name) values ('" . (int)$$v_attribute_values_id_var . "', '" . (int)$lid . "', '" . tep_db_input($$v_attribute_values_name_var) . "')";
        
                                            $attribute_values_insert = tep_db_query($attribute_values_insert_query);
                                        }
                                    }
        
        
                                    // insert values to pov2po table
                                    $attribute_values_pov2po_query = "insert into " . TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS . " (products_options_id, products_options_values_id) values ('" . (int)$$v_attribute_options_id_var . "', '" . (int)$$v_attribute_values_id_var . "')";
        
                                    $attribute_values_pov2po = tep_db_query($attribute_values_pov2po_query);
                                } else { // update options table, if options already exists
                                    for ($i=0, $n=sizeof($languages); $i<$n; $i++) {
                                        $lid = $languages[$i]['id'];
        
                                        $v_attribute_values_name_var = 'v_attribute_values_name_' . $attribute_options_count . '_' . $attribute_values_count . '_' . $lid;
        
                                        if (isset($$v_attribute_values_name_var)) {
                                            $attribute_values_update_lang_query = "select products_options_values_name from " . TABLE_PRODUCTS_OPTIONS_VALUES . " where products_options_values_id = '" . (int)$$v_attribute_values_id_var . "' and language_id ='" . (int)$lid . "'";
        
                                            $attribute_values_update_lang_values = tep_db_query($attribute_values_update_lang_query);
        
                                            // if options_values name doesn't exist for particular language, insert value
                                            if (tep_db_num_rows($attribute_values_update_lang_values) <= 0) {
                                                $attribute_values_lang_insert_query = "insert into " . TABLE_PRODUCTS_OPTIONS_VALUES . " (products_options_values_id, language_id, products_options_values_name) values ('" . (int)$$v_attribute_values_id_var . "', '" . (int)$lid . "', '" . tep_db_input($$v_attribute_values_name_var) . "')";
        
                                                $attribute_values_lang_insert = tep_db_query($attribute_values_lang_insert_query);
                                            } else { // if options_values name exists for particular language, update table
                                                $attribute_values_update_query = "update " . TABLE_PRODUCTS_OPTIONS_VALUES . " set products_options_values_name = '" . tep_db_input($$v_attribute_values_name_var) . "' where products_options_values_id ='" . (int)$$v_attribute_values_id_var . "' and language_id = '" . (int)$lid . "'";
        
                                                $attribute_values_update = tep_db_query($attribute_values_update_query);
                                            }
                                        }
                                    }
                                }
                            }
                            // options_values table update end
        
                            // options_values price update begin
                          $v_attribute_values_price_var = 'v_attribute_values_price_' . $attribute_options_count . '_' . $attribute_values_count;
        
                            if (isset($$v_attribute_values_price_var) && ($$v_attribute_values_price_var != '')) {
                                $attribute_prices_query = "select options_values_price, price_prefix from " . TABLE_PRODUCTS_ATTRIBUTES . " where products_id = '" . (int)$v_products_id . "' and options_id ='" . (int)$$v_attribute_options_id_var . "' and options_values_id = '" . (int)$$v_attribute_values_id_var . "'";
        
                                $attribute_prices_values = tep_db_query($attribute_prices_query);
        
                                $attribute_values_price_prefix = ($$v_attribute_values_price_var < 0) ? '-' : '+';
                                // if negative, remove the negative sign for storing since the prefix is stored in another field.
                                if ( $$v_attribute_values_price_var < 0 ) $$v_attribute_values_price_var = strval(-((float)$$v_attribute_values_price_var));
        
                                // options_values_prices table update begin
                                // insert into options_values_prices table if no price exists
                                if (tep_db_num_rows($attribute_prices_values) <= 0) {
                                    $attribute_prices_insert_query = "insert into " . TABLE_PRODUCTS_ATTRIBUTES . " (products_id, options_id, options_values_id, options_values_price, price_prefix) values ('" . (int)$v_products_id . "', '" . (int)$$v_attribute_options_id_var . "', '" . (int)$$v_attribute_values_id_var . "', '" . (float)$$v_attribute_values_price_var . "', '" . $attribute_values_price_prefix . "')";
        
                                    $attribute_prices_insert = tep_db_query($attribute_prices_insert_query);
                                } else { // update options table, if options already exists
                                    $attribute_prices_update_query = "update " . TABLE_PRODUCTS_ATTRIBUTES . " set options_values_price = '" . $$v_attribute_values_price_var . "', price_prefix = '" . $attribute_values_price_prefix . "' where products_id = '" . (int)$v_products_id . "' and options_id = '" . (int)$$v_attribute_options_id_var . "' and options_values_id ='" . (int)$$v_attribute_values_id_var . "'";
        
                                    $attribute_prices_update = tep_db_query($attribute_prices_update_query);
                                }
                            }
                            // options_values price update end
        
                            //////// attributes stock add start
                            $v_attribute_values_stock_var = 'v_attribute_values_stock_' . $attribute_options_count . '_' . $attribute_values_count;
                        
                            if (isset($$v_attribute_values_stock_var) && ($$v_attribute_values_stock_var != '')) {
                                
                                $stock_attributes = $$v_attribute_options_id_var.'-'.$$v_attribute_values_id_var;
                                $attribute_stock_query = tep_db_query("select products_stock_quantity from " . TABLE_PRODUCTS_STOCK . " where products_id = '" . (int)$v_products_id . "' and products_stock_attributes ='" . $stock_attributes . "'");        
                                
                                // insert into products_stock_quantity table if no stock exists
                                if (tep_db_num_rows($attribute_stock_query) <= 0) {
                                    $attribute_stock_insert_query =tep_db_query("insert into " . TABLE_PRODUCTS_STOCK . " (products_id, products_stock_attributes, products_stock_quantity) values ('" . (int)$v_products_id . "', '" . $stock_attributes . "', '" . (int)$$v_attribute_values_stock_var . "')");
                                        
                                } else { // update options table, if options already exists
                                    $attribute_stock_insert_query = tep_db_query("update " . TABLE_PRODUCTS_STOCK. " set products_stock_quantity = '" . (int)$$v_attribute_values_stock_var . "' where products_id = '" . (int)$v_products_id . "' and products_stock_attributes = '" . $stock_attributes . "'");
                                                
                                    // turn on stock tracking on products_options table
                                    $stock_tracking_query = tep_db_query("update " . TABLE_PRODUCTS_OPTIONS . " set products_options_track_stock = '1' where products_options_id = '" . (int)$$v_attribute_options_id_var . "'");
                                
                                }
                            }
                            //////// attributes stock add end                    
                            
                            $attribute_values_count++;
                            $v_attribute_values_id_var = 'v_attribute_values_id_' . $attribute_options_count . '_' . $attribute_values_count;
                        }
        
                        $attribute_options_count++;
                        $v_attribute_options_id_var = 'v_attribute_options_id_' . $attribute_options_count;
                    }
        
                    $attribute_rows++;
                }
                // VJ product attribs end
        
            } else {
                // this record was missing the product_model
                $sHtml .= "<p class=smallText>No existe el indentificador " . EP_IDENTIFICADOR . " en este registro, no se ha podido importar. ";
                foreach ($items as $tkey => $item)
					$sHtml .= print_el($item);

				$sHtml .= "<br /><br /></p>";
				$sHtml .= "<br /><br /></p>";
            }
            // end of row insertion code
            
        }

    // EP for product extra fields Contrib by minhmaster DEVSOFTVN ==========
    }
    // end (EP for product extra fields Contrib by minhmt DEVSOFTVN) ============

	return array( $sNuevoEditar, $sHtml, $v_products_id );
}

require(THEME . 'html/footer.php'); ?>

<script type="text/javascript">
	// Subir e importar
	jQuery("#form-imprt").submit(function(e)
	{
		e.stopPropagation();
		
		if( jQuery("#usrfl").val() == "" )
		{
			alert("Debes de subir un archivo para importar");
			return false;
		}

		jQuery("#dxbg").css("display", "block");
		jQuery("#dxload-ifrme").css("display", "block");
		jQuery("#imprt-ifme-log").attr( "src", "<?php echo $sNameFileExport;?>?a=importar_log&b=1" );

		return true;
	});
	
	// Cerrar el importador
	jQuery("#dxbg").click(function(e)
	{
		e.stopPropagation();

		if( confirm("¿Deseas cerrar el log del importador?") )
			window.location.href = window.location.href;

		return false;
	});

	var sValueAnterior;

	// Combobox de identificador
	jQuery("#identificador").change(function(e)
	{
		// Obtenemos el campo en el seleccionador 
		var dmAux = jQuery("#rows-drag #" + jQuery(this).val());
		
		// Si existe
		if( dmAux.length > 0 )
		{
			// Si se puede hacer drag lo cancelamos ya que ahora es un identificador
			if( !dmAux.hasClass("drag-sltc") )
				dmAux.addClass("drag-sltc");
		}
		
		// Obtenemos el campo anterior que tenia el identificador
		var dmAux = jQuery("#" + sValueAnterior);

		// Si existe
		if( dmAux.length > 0 )
		{
			// Si no  puede hacer drag, le quitamos la clase
			if( dmAux.hasClass("drag-sltc") )
				dmAux.removeClass("drag-sltc");
		}
		
		var sValue = jQuery(this).val();
		
		// Obtenemos todos los campos que exsitan que sean de la tabla para quitarlos del drag
		$("#drop-rows tr td:nth-child(1)").each(function(i, elmt)
		{		
			if( jQuery(elmt).text().trim() == sValue )
				jQuery(elmt).parent().remove();
		});
		
		// Quitamos el titular y añadimos el borde
		if( jQuery("#drop-rows").find("tr").length == 0 )
		{
			jQuery("#drop-rows-cntd thead").css("display", "none");
			jQuery("#drop-rows-cntd tbody").removeClass("drop-rows-empty");
			jQuery("#drop-rows-cntd").addClass("formRow").removeClass("tDefault");
		}
	}).click(function(e)
	{
		sValueAnterior = jQuery(this).val();
	});


	// Exportar cabeceras
	jQuery("#expt-head").click( function(e)
	{
		// Detenemos evento
		e.stopPropagation();
		
		// Mensaje
		if( confirm( '¿Deseas descargar solo las cabeceras del archivo?' ) )
		{
			jQuery("#epcust_status_filter").find("option:selected").val("25");
			jQuery("#custom").submit();
		}

		return false;
	});

	// Lista de campos para hacer drag
	jQuery( "#rows-drag li" ).draggable({
		scroll: true,
		cursorAt: { top: 8, left: 8 },
		cursor: "move",
		cancel: '.drag-sltc',
		revert: function(bDrop)
		{
			if( bDrop )
			{
				return false;
			} else 
			{
				jQuery(this).removeClass( "drag-sltc" );
				return true;
			}
		},
		start: function(e)
		{
			var dmElement = jQuery(e.currentTarget);
			dmElement.addClass("drag-sltc");
		},
		helper: function(e)
		{
			var dmElement = jQuery(e.currentTarget);
			return "<div style='z-index: 100; width: auto;' class='hlpe-drag'><span>::</span>" + dmElement.text() + "</div>";
		}
	});

	// Zona drop para añadir campos
	jQuery("#drop-rows").droppable(
	{
		cursorAt: { top: 8, left: 8 },
		greedy: true,
		drop: function(e, ui)
		{	
			// Obtenemos el elemento
			var dmElement = jQuery(ui.draggable);
	
			// Si el emento se hace drop asi mismo en el caso de ordenar la lista no se inserte de nuevo
			if( !dmElement.data( "drop" ) )
			{
				// Copiamos el combobox de tipos
				var dmComboTipos = jQuery("#tipos").clone();
			
				// Creamos el elemento
				var dmDiv = jQuery( "<tr/>",
				{
					"data-row": dmElement.text().replace("::", ""),
					"data-drop": "true",
					class: "",
					html: '<td> \
						' + dmElement.text().replace("::", "") + '<input value="' + dmElement.text().replace("::", "") + '" type="hidden" name="row[]"/> \
					</td> \
					<td> \
						<input name="alias[]" style="width: 100%;" type="text" /> \
					</td>'
				});
				
				// Insertamos
				dmDiv.appendTo( this );
				
				// Eliminamos la clase hover
				jQuery(this).removeClass("drop-hovr");
			}
			
			// Mostramos el titular y quitamos el borde si es la primera vez que insertamos un campo
			if( jQuery(this).find("tr").length == 1 )
			{
				jQuery("#drop-rows-cntd thead").css("display", "table-row-group");
				jQuery("#drop-rows-cntd tbody").addClass("drop-rows-empty");
				jQuery("#drop-rows-cntd").addClass("tDefault").removeClass("formRow");
			}
		},
		over: function(e, ui )
		{	
			jQuery(this).addClass("drop-hovr");
		},
		out: function(e, ui )
		{
			sortableIn = 0; 
		
			jQuery(this).removeClass("drop-hovr");
		}
	}).sortable(
	{
		cursor: "move",
		over: function(e, ui)
		{
			sortableIn = 1; 
		},
		out: function(e, ui)
		{
			sortableIn = 0; 
		},					
		receive: function(e, ui)
		{
			sortableIn = 1; 
		},
		beforeStop: function(e, ui) 
		{
			if (sortableIn == 0)
			{	
				var dmElement = ui.item;
				
				// Si estamos en la tabla devolvemos el campo
				if( jQuery("#tablas").val() == dmElement.data("table") )
					jQuery("#rows-drag #" + dmElement.data("row")).removeClass("drag-sltc");
				
				// Eliminamos el elemento
				ui.item.remove();
				
				// Quitamos el titular y añadimos el borde
				if( jQuery(this).find("tr").length == 1 )
				{
					jQuery("#drop-rows-cntd thead").css("display", "none");
					jQuery("#drop-rows-cntd tbody").removeClass("drop-rows-empty");
					jQuery("#drop-rows-cntd").addClass("formRow").removeClass("tDefault");
				}
			}
		},
		helper: function(e)
		{
			return "<div class='drag'></div>";
		}
	});

	function getUrlParams(url)
	{
        var params = {};
        url.substring(1).replace(/[?&]+([^=&]+)=([^&]*)/gi,
                function (str, key, value) {
                     params[key] = value;
                });
        return params;
	}
	
	// Cuando seleccionamos un tipo de conjunto
	jQuery("#dltype").change(function()
	{
		// Completo
		if( jQuery(this).val() == "full" )
		{
			jQuery("#tbls .check :checkbox").prop( "checked", true );
			jQuery.uniform.update( "#tbls .check :checkbox" );

			// Mostramos la capa al ser completo
			jQuery("#tble-bg-chck-trns").css( "display", "block" );
		}
		// Personalizado
		else
		{
			// Ocultamos la capa al ser personalizado
			jQuery("#tble-bg-chck-trns").css( "display", "none" );
		}
	});
	

	// Mensaje de aceptacion para descargar
	jQuery(".dwnl").click( function(e)
	{
		// Detenemos evento
		e.stopPropagation();
		
		// Mensaje
		if( confirm( '¿Deseas realizar la descarga del archivo?' ) )
			return true;

		return false;
	});
	
	// Mensaje de aceptacion de eliminar
	jQuery(".elnr").click( function(e)
	{
		// Detenemos evento
		e.stopPropagation();
		
		// Mensaje
		if( confirm( '¿Deseas eliminar el archivo?' ) )
			return true;

		return false;
	});
	
	// Mensaje de aceptacion de importar
	jQuery(".impr").click( function(e)
	{
		// Detenemos evento
		e.stopPropagation();
		
		// Mensaje
		if( confirm( '¿Deseas importar el archivo, recuerda realizar backup antes?' ) )
		{
			// Creamos campos
			var aCampos = getUrlParams( jQuery(this).attr("href") );
			var sHtml = "";

			jQuery.each( aCampos, function(key, value)
			{
				sHtml += '<input type="text" name="' + key + '" value="' + value + '" />';
			});

			sHtml += '<input type="text" name="imput_mode" value="' + jQuery(this).parent().prev().find("select").val() + '" />';

			// Creamos el form
			var dmForm = jQuery( "<form/>",
			{
				target: "imprt-ifme",
				style: "display:none",
				method: "post",
				action: "<?php echo $sNameFileExport; ?>?a=importar",
				html: sHtml
			}).submit(function(e)
			{
				e.stopPropagation();

				// Mostramos el log
				jQuery("#dxbg").css("display", "block");
				jQuery("#dxload-ifrme").css("display", "block");
				jQuery("#imprt-ifme-log").attr( "src", "<?php echo $sNameFileExport; ?>?a=importar_log&b=1" );

				return true;
			});

			dmForm.appendTo( jQuery(this).parent() );
			
			// Enviamos el formulario
			jQuery(dmForm).submit();
		}

		return false;
	});
</script>

<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>