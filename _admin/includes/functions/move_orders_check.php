<?php
/*
  $Id: move_orders_check.php, v 2.0 20/08/2006 Gnidhal Exp $
  Part of contribution OrdersCheck. 
  This script is not included in the original version of osCommerce

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
*/


function straight_move($ocID, $dest_ocID='') 
{
			//  ------------------------------------------------------------------------------------------
         $orders_move_id = ($dest_ocID == '') ? $ocID : $dest_ocID ;

         //  ------------------------------------------------------------------------------------------
         //  TABLE_HOLDING_ORDERS >> TABLE_ORDERS
         $ORDERS_move_query = tep_db_query("select * from " . TABLE_HOLDING_ORDERS . " where orders_id = '" . $ocID . "'");
         $ORDERS_move = tep_db_fetch_array($ORDERS_move_query);
         $ORDERS_move['orders_id'] = $orders_move_id ;

         tep_db_perform(TABLE_ORDERS, $ORDERS_move);


         //  ------------------------------------------------------------------------------------------
         //  TABLE_HOLDING_ORDERS_TOTAL >> TABLE_ORDERS_TOTAL
         $ORDERS_TOTAL_move_query = tep_db_query("select * from " . TABLE_HOLDING_ORDERS_TOTAL . " where orders_id = '" . $ocID . "'");

         while ($ORDERS_TOTAL_move = tep_db_fetch_array($ORDERS_TOTAL_move_query)) {
             $ORDERS_TOTAL_move = array(
             'orders_total_id' => '',
             'orders_id' => $orders_move_id,
             'title' => $ORDERS_TOTAL_move['title'],
             'text' => $ORDERS_TOTAL_move['text'],
             'value' => $ORDERS_TOTAL_move['value'], 
             'class' => $ORDERS_TOTAL_move['class'], 
             'sort_order' => $ORDERS_TOTAL_move['sort_order']
             );
             tep_db_perform(TABLE_ORDERS_TOTAL, $ORDERS_TOTAL_move);
         }

         //  ------------------------------------------------------------------------------------------
         //  TABLE_HOLDING_ORDERS_PRODUCTS >> TABLE_ORDERS_PRODUCTS
         $ORDERS_PRODUCTS_move_query = tep_db_query("select * from " . TABLE_HOLDING_ORDERS_PRODUCTS . " where orders_id = '" . $ocID . "'");
         while ($ORDERS_PRODUCTS_move = tep_db_fetch_array($ORDERS_PRODUCTS_move_query)) {
         $ORDERS_PRODUCTS_move_out = array (
                                   'orders_id' => $orders_move_id,
                                   'products_id' => $ORDERS_PRODUCTS_move['products_id'],
                                   'products_model' => $ORDERS_PRODUCTS_move['products_model'],
								   'products_ubicacion' => $ORDERS_PRODUCTS_move['products_ubicacion'],
                                   'products_name' => $ORDERS_PRODUCTS_move['products_name'],
                                   'products_price' => $ORDERS_PRODUCTS_move['products_price'],
                                   'final_price' => $ORDERS_PRODUCTS_move['final_price'],
                                   'products_tax' => $ORDERS_PRODUCTS_move['products_tax'],
				   
                                   'products_quantity' => $ORDERS_PRODUCTS_move['products_quantity']
                                   );
         tep_db_perform(TABLE_ORDERS_PRODUCTS, $ORDERS_PRODUCTS_move_out);
         $order_products_id = tep_db_insert_id();
         //  ------------------------------------------------------------------------------------------
         //  TABLE_HOLDING_ORDERS_PRODUCTS_ATTRIBUTES >> TABLE_ORDERS_PRODUCTS_ATTRIBUTES
         $ORDERS_PRODUCTS_ATTRIBUTES_move_query = tep_db_query("select * from " . TABLE_HOLDING_ORDERS_PRODUCTS_ATTRIBUTES . " where orders_id = '" . $ocID . "'AND orders_products_id ='".$ORDERS_PRODUCTS_move['orders_products_id']."'" );
         while ($ORDERS_PRODUCTS_ATTRIBUTES_move = tep_db_fetch_array($ORDERS_PRODUCTS_ATTRIBUTES_move_query)){
              $ORDERS_PRODUCTS__ATTRIBUTES_move_out = array (
	    	'orders_id' =>$orders_move_id,
                'orders_products_id'=>$order_products_id,
            	'orders_products_attributes_id'=> '',
		'products_options' => $ORDERS_PRODUCTS_ATTRIBUTES_move['products_options'],
		'products_options_values' => $ORDERS_PRODUCTS_ATTRIBUTES_move['products_options_values'],
		'options_values_price' => $ORDERS_PRODUCTS_ATTRIBUTES_move['options_values_price'],
		'price_prefix' => $ORDERS_PRODUCTS_ATTRIBUTES_move['price_prefix'],
		'options_values_weight' => $ORDERS_PRODUCTS_ATTRIBUTES_move['options_values_weight'],
	        'NIDATRIB'=> $ORDERS_PRODUCTS_ATTRIBUTES_move['NIDATRIB']
		);
               tep_db_perform(TABLE_ORDERS_PRODUCTS_ATTRIBUTES, $ORDERS_PRODUCTS__ATTRIBUTES_move_out);
         } 

         //  ------------------------------------------------------------------------------------------
         //  IF !!!  TABLE_HOLDING_ORDERS_PRODUCTS_DOWNLOAD >> TABLE_ORDERS_PRODUCTS_DOWNLOAD
         if ((DOWNLOAD_ENABLED == 'true') && isset($attributes_values['products_attributes_filename']) && tep_not_null($attributes_values['products_attributes_filename'])) {

             $ORDERS_PRODUCTS_DOWNLOAD_move_query = tep_db_query("select * from " . TABLE_HOLDING_ORDERS_PRODUCTS_DOWNLOAD . " where orders_id = '" . $ocID . "'");
             $ORDERS_PRODUCTS_DOWNLOAD_move = tep_db_fetch_array($ORDERS_PRODUCTS_DOWNLOAD_move_query);
             $ORDERS_PRODUCTS_DOWNLOAD_move['orders_id'] = $orders_move_id ;
             $ORDERS_PRODUCTS_DOWNLOAD_move['orders_products_id'] = $order_products_id;
             $ORDERS_PRODUCTS_DOWNLOAD_move['orders_products_download_id'] ='';

             tep_db_perform(TABLE_ORDERS_PRODUCTS_DOWNLOAD, $ORDERS_PRODUCTS_DOWNLOAD_move);
         }
   }

         //  ------------------------------------------------------------------------------------------
         //  TABLE_HOLDING_ORDERS_STATUS_HISTORY >> TABLE_ORDERS_STATUS_HISTORY
         $ORDERS_STATUS_HISTORY_move_query = tep_db_query("select * from " . TABLE_HOLDING_ORDERS_STATUS_HISTORY . " where orders_id = '" . $ocID . "'");
         $ORDERS_STATUS_HISTORY_move = tep_db_fetch_array($ORDERS_STATUS_HISTORY_move_query) ;
             $ORDERS_STATUS_HISTORY_move['orders_id'] = $orders_move_id ;
             $ORDERS_STATUS_HISTORY_move['orders_status_history_id'] = '';
             if (tep_not_null($ORDERS_STATUS_HISTORY_move)) {
               tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $ORDERS_STATUS_HISTORY_move);
             }


         // Destock products
             $order_stock_query = tep_db_query(" select products_id, products_quantity from " . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . $orders_move_id . "'");
             while ($order_stock = tep_db_fetch_array($order_stock_query)) {
                 tep_db_query("update " . TABLE_PRODUCTS . " set products_quantity = products_quantity - " . $order_stock['products_quantity'] . ", products_ordered = products_ordered + " . $order_stock['products_quantity'] . " where products_id = '" . $order_stock['products_id'] . "'");
             }
             







} // end function straight_move


//------------------------------------------------------------------------------------------
//  used in the deleteconfirm area
function tep_remove_order_check($order_id, $restock = false) 
{

         tep_db_query("delete from " . TABLE_HOLDING_ORDERS . " where orders_id = '" . tep_db_input($order_id) . "'");
         tep_db_query("delete from " . TABLE_HOLDING_ORDERS_PRODUCTS . " where orders_id = '" . tep_db_input($order_id) . "'");
         tep_db_query("delete from " . TABLE_HOLDING_ORDERS_PRODUCTS_ATTRIBUTES . " where orders_id = '" . tep_db_input($order_id) . "'");
         tep_db_query("delete from " . TABLE_HOLDING_ORDERS_STATUS_HISTORY . " where orders_id = '" . tep_db_input($order_id) . "'");
         tep_db_query("delete from " . TABLE_HOLDING_ORDERS_TOTAL . " where orders_id = '" . tep_db_input($order_id) . "'");
         tep_db_query("delete from " . TABLE_HOLDING_ORDERS_PRODUCTS_DOWNLOAD . " where orders_id = '" . tep_db_input($order_id) . "'");

} // end function tep_remove_order_check
//------------------------------------------------------------------------------------------
?>
