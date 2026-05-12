<?php

/**
 * #XCC-313-91043
 */
	 
require 'includes/application_top.php';

/*$sql = 'SELECT orders_id FROM orders_products WHERE profit > 0 GROUP BY orders_id';
$sql = tep_db_query($sql);

$ids = [];
while ($order = tep_db_fetch_array($sql)) {
$ids[$order['orders_id']] = Affiliates::calculateOrderProfit($order['orders_id']);
//$ids[] = $order['orders_id'];
}

echo '<pre>' . print_r($ids, 1) . '</pre>';
die();*/
/*
$sql = 'SELECT orders_id, date_purchased FROM orders WHERE profit = 0 ORDER BY orders_id DESC LIMIT 1000';
$sql = tep_db_query($sql);

$ids = [];
while ($order = tep_db_fetch_array($sql)) {
echo '<pre>' . print_r($order, 1) . '</pre>';

//Affiliates::calculateOrderProfit($order['orders_id']);
}
 */
//echo '<pre>' . print_r($ids, 1) . '</pre>';
//die();

$sql = 'SELECT count(*) as total FROM orders_products WHERE profit = 0';
$sql = tep_db_query($sql);
$product = tep_db_fetch_array($sql);
echo '<pre>'.print_r($product, 1).'</pre>';


$sql = 'SELECT orders_products_id, orders_id, products_id, profit, products_cost as cost, products_price, products_tax as tax FROM orders_products WHERE profit = 0 ORDER BY orders_id DESC LIMIT 10000';
$sql = tep_db_query($sql);

$ids = [];
while ($product = tep_db_fetch_array($sql)) {

    if ($product['cost'] == 0) {
        $sqlCost = 'SELECT products_cost FROM products WHERE products_id = ' . $product['products_id'];
        $sqlCost = tep_db_query($sqlCost);
        $cost = tep_db_fetch_array($sqlCost);

        $product['cost'] = $cost['products_cost'];
    }
    $profit = Affiliates::calculateProductProfit($product['products_price'], $product);

    //echo '<pre>' . print_r($product, 1) . '</pre>';
    //echo '<pre>' . print_r($profit, 1) . '</pre>';

    $sqlUpdate = 'UPDATE orders_products SET profit = ' . $profit . ' WHERE orders_products_id = ' . $product['orders_products_id'];
    tep_db_query($sqlUpdate);

    //echo '<pre>' . print_r($sqlUpdate, 1) . '</pre>';

    //Affiliates::calculateOrderProfit($order['orders_id']);
}


echo '<script>setTimeout(function(){ location.href = "'.tep_href_link('calculate-orders-profit.php').'";  }, 10000);</script>';
