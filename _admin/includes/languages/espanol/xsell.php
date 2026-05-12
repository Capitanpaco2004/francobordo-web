<?php
/* $Id$ 
osCommerce, Open Source E-Commerce Solutions 
http://www.oscommerce.com 
Copyright (c) 2002 osCommerce 

Released under the GNU General Public License 
xsell.php
Original Idea From Isaac Mualem im@imwebdesigning.com <mailto:im@imwebdesigning.com> 
Complete Recoding From Stephen Walker admin@snjcomputers.com
*/ 

define('CROSS_SELL_SUCCESS', 'Cross Sell Items Successfully Update For Cross Sell Product #'.($_GET['add_related_product_ID'] ?? ''));
define('SORT_CROSS_SELL_SUCCESS', 'Sort Order Successfully Update For Cross Sell Product #'.($_GET['add_related_product_ID'] ?? ''));
define('HEADING_TITLE', 'Cross-Sell (X-Sell) Control_Panel');
define('TABLE_HEADING_PRODUCT_ID', 'Producto ID');
define('TABLE_HEADING_PRODUCT_MODEL', 'Modelo');
define('TABLE_HEADING_PRODUCT_NAME', 'Nombre del Producto');
define('TABLE_HEADING_CURRENT_SELLS', 'Current Cross-Sells');
define('TABLE_HEADING_UPDATE_SELLS', 'Update Cross-Sells');
define('TABLE_HEADING_PRODUCT_IMAGE', 'Producto Imagen');
define('TABLE_HEADING_PRODUCT_PRICE', 'Producto Precio');
define('TABLE_HEADING_CROSS_SELL_THIS', 'Cross-Sell This?');
define('TEXT_EDIT_SELLS', 'Editar');
define('TEXT_SORT', 'Priorizar');
define('TEXT_SETTING_SELLS', 'Setting Cross-Sells For');
define('TEXT_PRODUCT_ID', 'Producto ID');
define('TEXT_MODEL', 'Modelo');
define('TABLE_HEADING_PRODUCT_SORT', 'Ordenar');
define('TEXT_NO_IMAGE', 'Sin Imagen');
define('TEXT_CROSS_SELL', 'Cross-Sell');
if (!defined('HEADING_TITLE_SEARCH')) define('HEADING_TITLE_SEARCH', 'Buscar : ');
define('TEXT_RECIPROCAL_LINK', '¿Link reciproco?');
?>