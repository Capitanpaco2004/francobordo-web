<?php
/*
  $Id: check_orders_check.php, v 2.0 20/08/2006 Gnidhal Exp $
  Part of contribution OrdersCheck. 
  This script is not included in the original version of osCommerce

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
*/

function check_duplicate_orders($orders_check_id, $languages_id) {

  $flag = check_duplicate_orders_flag($orders_check_id, $languages_id) ;
      if ($flag == true) {
      	echo tep_image(DIR_WS_IMAGES . 'icon_status_green.png', '', 10, 10);
      } else {
      	echo tep_image(DIR_WS_IMAGES . 'icon_status_red.png', '', 10, 10);
      }
      
} // end function check_duplicate_orders

function check_duplicate_orders_flag($orders_check_id, $languages_id) {
      $orders_query_duplicate_orders = "select o.orders_id, o.customers_id, o.customers_name, o.payment_method, s.orders_status_name, ot.text as order_total from " . TABLE_ORDERS . " o left join " . TABLE_ORDERS_TOTAL . " ot on (o.orders_id = ot.orders_id), " . TABLE_ORDERS_STATUS . " s where o.orders_id = '" . $orders_check_id . "' and o.orders_status = s.orders_status_id and s.language_id = '" . $languages_id . "' and ot.class = 'ot_total' order by o.orders_id DESC";
      
      $check_duplicate_orders = tep_db_query($orders_query_duplicate_orders);
      $check_duplicate_orders = tep_db_fetch_array($check_duplicate_orders);
      
      $orders_query_duplicate_holding = "select oc.orders_id, oc.customers_id, oc.customers_name, oc.payment_method, s.orders_status_name, ot.text as order_total from " . TABLE_HOLDING_ORDERS . " oc left join " . TABLE_HOLDING_ORDERS_TOTAL . " ot on (oc.orders_id = ot.orders_id), " . TABLE_ORDERS_STATUS . " s where oc.orders_id = '" . $orders_check_id . "' and oc.orders_status = s.orders_status_id and s.language_id = '" . $languages_id . "' and ot.class = 'ot_total' order by oc.orders_id DESC";
      
      $check_duplicate_holding = tep_db_query($orders_query_duplicate_holding);
      $check_duplicate_holding = tep_db_fetch_array($check_duplicate_holding);
      
     $flag = ($check_duplicate_holding == $check_duplicate_orders) ? true : false;
     return $flag ;  
}
?>
