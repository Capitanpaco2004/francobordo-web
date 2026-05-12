<?php
/*
  $Id: shop_by_price.php,v 2.5 2008/03/07 $
  
  Contribution by Meltus  http://www.highbarn-consulting.com
  Adapted for OsCommerce MS2 by Sylvio Ruiz suporte@leilodata.com
  Modified by Hugues Deriau on 09/23/2006 - display the price ranges in the selected currency
  Modified by Glassraven for dropdown list 24/10/2006 www.glassraven.com
  Modified by -GuiGui- (http://www.gpuzin.com) - 07/03/2008 - fix the title and work with the contribution " Product Listing in Columns"
  
  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
*/

$sel_currency = array();

$price_ranges = Array( 	"Menos de " .  $currencies->format(1),
						"Desde " . $currencies->format(1) . " a " . $currencies->format(2),
						"Desde " . $currencies->format(2). " a " . $currencies->format(3),
						"Desde " . $currencies->format(3). " a " . $currencies->format(4),
						"Desde " . $currencies->format(4). " a " . $currencies->format(5),
						"Desde " . $currencies->format(5). " a " . $currencies->format(6),
						"Desde " . $currencies->format(6). " a " . $currencies->format(7),
						"Desde " . $currencies->format(7). " a " . $currencies->format(8),
						"Desde " . $currencies->format(8). " a " . $currencies->format(9),
						"Desde " . $currencies->format(9). " a " . $currencies->format(10),
						"Desde " . $currencies->format(10). " a " . $currencies->format(15),
						"Mas de " . $currencies->format(15),
						"Todos los Precios");

$price_min = Array(  0,
                    1,
					2,
					3,
					4,
					5,
					6,
					7,
					8,
					9,
					10,
					15,
					0);

$price_max = Array( 1,
                    2,
					3,
					4,
					5,
					6,
					7,
					8,
					9,
					10,
					15,
					0,
					0);


define('NAVBAR_TITLE', 'Filtrar por precios');
// the following $range references come from catalog/shop_by_price.php
if ( isset($price_ranges[$range]) )
	define('HEADING_TITLE', 'Comprar por precios - ' . $price_ranges[$range]);
else
	define('HEADING_TITLE', 'Comprar por precios');
define('BOX_HEADING_SHOP_BY_PRICE', 'Compra por precios');
define('TABLE_HEADING_BUY_NOW', 'Comprar ahora!');
define('TABLE_HEADING_IMAGE', '');
define('TABLE_HEADING_MANUFACTURER', 'Fabricantes');
define('TABLE_HEADING_MODEL', 'Modelo');
define('TABLE_HEADING_PRICE', 'Precio');
define('TABLE_HEADING_PRODUCTS', 'Productos Name');
define('TABLE_HEADING_QUANTITY', 'Cantidad');
define('TABLE_HEADING_WEIGHT', 'Peso');
// Product Listing in Columns - Start (You can remove those 3 lines if you are not using it).
define('TABLE_HEADING_MULTIPLE', 'Cantidad: ');
// Product Listing in Columns - End
define('TEXT_NO_PRODUCTS', '<p align="center"><br>Lo sentimos, pero no tenemos productos con este rango de precios.</p>');
?>