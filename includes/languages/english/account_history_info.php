<?php
/*
  $Id: account_history_info.php 1739 2007-12-20 00:52:16Z hpdl $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2002 osCommerce

  Released under the GNU General Public License
*/

define('NAVBAR_TITLE_1', 'My Account');
define('NAVBAR_TITLE_2', 'History');
define('NAVBAR_TITLE_3', 'Order #%s');

define('HEADING_TITLE', 'Order Information');

define('HEADING_ORDER_NUMBER', 'Order #%s');
define('HEADING_ORDER_DATE', 'Order Date:');
define('HEADING_ORDER_TOTAL', 'Order Total:');

define('HEADING_DELIVERY_ADDRESS', 'Delivery Address');
define('HEADING_SHIPPING_METHOD', 'Shipping Method');

define('HEADING_PRODUCTS', 'Products');
define('HEADING_TAX', 'Tax');
define('HEADING_TOTAL', 'Total');

define('HEADING_BILLING_INFORMATION', 'Billing Information');
define('HEADING_BILLING_ADDRESS', 'Billing Address');
define('HEADING_PAYMENT_METHOD', 'Payment Method');

define('HEADING_ORDER_HISTORY', 'Order History');
define('HEADING_COMMENT', 'Comments');
define('TEXT_NO_COMMENTS_AVAILABLE', 'No comments available.');

define('TABLE_HEADING_DOWNLOAD_DATE', 'Link expires: ');
define('TABLE_HEADING_DOWNLOAD_COUNT', ' downloads remaining');
define('HEADING_DOWNLOAD', 'Download links');

define('TEXT_RETURN_PRODUCT','Return');
define('TEXT_RMA', '<br />Return');
define( 'TITLE_HISTORY_INFO_CANCEL_TITLE', 'Cancel the order' );
define( 'TITLE_HISTORY_INFO_CANCEL_CONFIRM', 'Are you sure you want to cancel the order?<br />The amount paid will be returned by the same means of payment that you selected.<br />The products of the order will be added back to the cart in case you want to complete your order.' );
define('TEXT_CANCEL_ORDER', 'Cancel');
define('TEXT_CANCEL_ORDER_SUCCESS', 'The order has been successfully canceled.<br />The amount paid will be returned to you by the same means of payment that you selected.<br />The products of the order will be added back to the cart in case you want to complete your order.');

// delivery_estimate module
define( 'DELIVERY_ESTIMATE_TITLE',              'Estimated delivery date' );
define( 'DELIVERY_ESTIMATE_RULE_STOCK_OK',      'All products are in stock.' );
define( 'DELIVERY_ESTIMATE_RULE_NO_STOCK',      'Some product in your order is pending restock.' );
define( 'DELIVERY_ESTIMATE_RULE_BACKORDER',     'Some product in your order is made on demand.' );
define( 'DELIVERY_ESTIMATE_RULE_MANUAL',        'Date manually updated by our team.' );
define( 'DELIVERY_ESTIMATE_DAYS_REMAINING',     'In about %d days' );
define( 'DELIVERY_ESTIMATE_TOMORROW',           'Tomorrow' );
define( 'DELIVERY_ESTIMATE_TODAY',              'Today' );
define( 'DELIVERY_ESTIMATE_DUE',                'Expected today or earlier' );
define( 'DELIVERY_ESTIMATE_MANUAL_BADGE',       'Updated by the team' );
define( 'DELIVERY_ESTIMATE_COMMENT_LABEL',      'Team comment:' );
?>
