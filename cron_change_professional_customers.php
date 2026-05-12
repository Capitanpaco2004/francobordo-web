<?php
include 'includes/application_top.php';

$query = tep_db_query('SELECT c.customers_id, COUNT(o.orders_id) as nOrders, SUM(ot.value) as sumOrders FROM customers c
LEFT JOIN orders o ON  c.customers_id = o.customers_id
LEFT JOIN orders_total ot ON ot.orders_id = o.orders_id AND ot.class = "ot_total"
WHERE customers_group_id = 1 AND (o.date_purchased >= DATE_SUB(NOW(), INTERVAL 1 YEAR) OR o.date_purchased IS NULL) GROUP BY c.customers_id');

$customers_id = array();
$sLog = "";
if(tep_db_num_rows($query)>0){
	while($customer = tep_db_fetch_array($query)){
		if($customer['nOrders'] == 0 || $customer['sumOrders']<250){
			$customers_id[] = $customer['customers_id'];
			$totalValue = $customer['nOrders'] == 0? "NULL": $customer['sumOrders'];
			$sLog .= "({$customer['customers_id']}, NOW(), {$customer['nOrders']}, {$totalValue}), ";
		}
	}
	if (count($customers_id) > 0) {
		$sLog = substr($sLog, 0, -2);
		$sCustomersID = implode(',', $customers_id);
		tep_db_query("INSERT INTO customers_profesionals_removed_log (customer_id,date, numero_pedidos, total_values) VALUES ".$sLog);
		tep_db_query("UPDATE customers SET customers_group_id  = 0 WHERE customers_id IN ({$sCustomersID})");
		echo 'OK - ' . count($customers_id) . ' clientes profesionales eliminados (no cumplen requisitos).';
	} else {
		echo 'OK - Todos los clientes profesionales cumplen los requisitos. Sin cambios.';
	}
} else {
	echo 'OK - No hay clientes profesionales que revisar.';
}
