<?php
/*
  $Id: shopping_cart.php 1739 2007-12-20 00:52:16Z hpdl $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2007 osCommerce

  Released under the GNU General Public License
*/

define('NAVBAR_TITLE', 'Cart Contents');
define('HEADING_TITLE', 'What\'s In My Cart?');
define('TABLE_HEADING_REMOVE', 'Remove');
define('TABLE_HEADING_QUANTITY', 'Qty.');
define('TABLE_HEADING_MODEL', 'Model');
define('TABLE_HEADING_PRODUCTS', 'Product(s)');
define('TABLE_HEADING_IMAGE', 'Image');
define('TABLE_HEADING_TOTAL', 'Total');
define('TEXT_CART_EMPTY', 'Your Shopping Cart is empty!');
define('MODULE_ORDER_TOTAL_SHIPPING_TITLE:', 'Shipping Cost:');
define('SUB_TITLE_SUB_TOTAL', 'Sub-Total:');
define('SUB_TITLE_TOTAL', 'Total:');

define('OUT_OF_STOCK_CANT_CHECKOUT', 'Products marked with ' . STOCK_MARK_PRODUCT_OUT_OF_STOCK . ' dont exist in desired quantity in our stock.<br />Please alter the quantity of products marked with (' . STOCK_MARK_PRODUCT_OUT_OF_STOCK . '), Thank you');
define('OUT_OF_STOCK_CAN_CHECKOUT', 'Products marked with ' . STOCK_MARK_PRODUCT_OUT_OF_STOCK . ' dont exist in desired quantity in our stock.<br />You can buy them anyway and check the quantity we have in stock for immediate deliver in the checkout process.');

define('TEXT_ALTERNATIVE_CHECKOUT_METHODS', '- OR -');
define('TABLE_HEADING_MODEL', 'Reference');

define('TEXT_NO_CHECKOUT_BUNDLE_ONLY', 'You are not allowed to check out when any products sold only as a part of a bundle are in your cart. Please remove any items marked as not for separate sale.');
?>