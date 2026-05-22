<?php
include 'includes/application_top.php';

// Importe mínimo de compra anual configurable por grupo (customers_groups del grupo Profesionales = 1)
$minRow = tep_db_fetch_array(tep_db_query("SELECT customers_group_min_annual FROM customers_groups WHERE customers_group_id = 1"));
$minAnnual = (float)($minRow['customers_group_min_annual'] ?? 0);
if ($minAnnual <= 0) { $minAnnual = 250; }

$query = tep_db_query('SELECT c.customers_id, COUNT(o.orders_id) as nOrders, SUM(ot.value) as sumOrders FROM customers c
LEFT JOIN orders o ON c.customers_id = o.customers_id AND o.date_purchased >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
LEFT JOIN orders_total ot ON ot.orders_id = o.orders_id AND ot.class = "ot_total"
WHERE customers_group_id = 1 AND (c.customers_professional_since IS NULL OR c.customers_professional_since <= DATE_SUB(NOW(), INTERVAL 1 YEAR)) GROUP BY c.customers_id');

$customers_id = array();
$sLog = "";
if(tep_db_num_rows($query)>0){
	while($customer = tep_db_fetch_array($query)){
		if($customer['nOrders'] == 0 || $customer['sumOrders'] < $minAnnual){
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

		// --- SalesManago — sincronizar el cambio de grupo (Profesional -> Retail) ---
		try {
			if (defined('SALESMANAGO_STATUS') && SALESMANAGO_STATUS === 'true') {
				require_once DIR_FS_CATALOG . 'includes/classes/SalesManagoQueue.php';
				foreach ($customers_id as $smCid) {
					SalesManagoQueue::emitContactUpsert((int) $smCid, false);
				}
			}
		} catch (\Throwable $_smE) { @error_log('SM emit cron-demote: ' . $_smE->getMessage()); }

		echo 'OK - ' . count($customers_id) . ' clientes profesionales eliminados (no cumplen requisitos).';
	} else {
		echo 'OK - Todos los clientes profesionales cumplen los requisitos. Sin cambios.';
	}
} else {
	echo 'OK - No hay clientes profesionales que revisar.';
}
