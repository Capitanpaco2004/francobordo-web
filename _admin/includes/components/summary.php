<?php if (tep_admin_check_boxes('reports.php')): ?>
	<?php
	// Inicializar $aOrdersStatusSummary como un array vacío
	$aOrdersStatusSummary = [];

	// Get orders statuses and quantities in one query
	$orders_statuses = [];
	$orders_contents = '';
	$orders_status_query = tep_db_query("SELECT
												os.orders_status_id,
												os.orders_status_name,
												o.currency,
												COUNT(o.orders_id) AS qty,
												SUM(ot.value) AS order_total
											FROM " . TABLE_ORDERS_STATUS . " os
											LEFT JOIN " . TABLE_ORDERS . " o ON (os.orders_status_id = o.orders_status)
											LEFT JOIN " . TABLE_ORDERS_TOTAL . " ot ON (o.orders_id = ot.orders_id)
											WHERE ot.class = 'ot_total'
												AND os.language_id = '" . (int)$languages_id . "'
											GROUP BY os.orders_status_id");

	while ($orders_status = tep_db_fetch_array($orders_status_query)) {
		$aOrdersStatusSummary[] = ['id' => $orders_status['orders_status_id'], 'text' => $orders_status['orders_status_name'], 'qty' => $orders_status['qty'], 'total' => $orders_status['order_total']];
	}

	$aOrdersToday = ['total_orders' => '0', 'total_value' => '0.00'];

	$orders_today_query = tep_db_query("SELECT count(o.orders_id) as total, o.currency, SUM(ot.value) AS order_total
									  FROM " . TABLE_ORDERS . " o
									  LEFT JOIN " . TABLE_ORDERS_TOTAL . " ot ON (o.orders_id = ot.orders_id)
									  WHERE DATE(o.date_purchased) = CURDATE()
										AND ot.class = 'ot_total'
									  GROUP BY o.currency
									  ORDER BY o.currency");

	while ($orders_today = tep_db_fetch_array($orders_today_query)) {
		$aOrdersToday = ['total_orders' => $orders_today['total'], 'total_value' => $orders_today['order_total']];
	}

	// Variables para almacenar las cadenas HTML
	$ordersStatusOutput = '';
	$ordersTotalOutput = '';
	$hoyOutput = '';
	$pedidosTotalesOutput = '';

	// Verificar si la variable contiene elementos antes de usar count()
	$totalElements = isset($aOrdersStatusSummary) && is_array($aOrdersStatusSummary) ? count($aOrdersStatusSummary) : 0;

	foreach ($aOrdersStatusSummary as $key => $aOrderStatusSummary) {
		$ordersStatusOutput .= '<a href="' . tep_href_link(FILENAME_ORDERS, 'status=' . (int)$aOrderStatusSummary['id']) . '">' . $aOrderStatusSummary['text'] . '</a>:</strong> ' . $aOrderStatusSummary['qty'] . ' - ' . $currencies->format($aOrderStatusSummary['total'], false);

		// Verificamos si este elemento no es el último
		if ($key < $totalElements - 1) {
			$ordersStatusOutput .= ' | '; // Agregamos el separador solo si no es el último elemento
		}
	}
	?>
	<div class="box box-info box-odrs">
		<div style="font-size: 14px; font-weight: bold">Hoy: <?php echo $aOrdersToday['total_orders']; ?> pedidos - <?php echo $currencies->format($aOrdersToday['total_value'], false); ?></div>
		<div class="status-list">
			<?php echo $ordersStatusOutput; ?>
		</div>
	</div>

<?php endif; ?>
