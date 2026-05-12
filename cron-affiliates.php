<?php

/**
 * #XCC-313-91043
 * @author Daniel Lucia <daniel.lucia@denox.es>
 */

include 'includes/application_top.php';

$orderStatus = array_map('intval', explode(',', AFFILLIATES_ORDERS_STATUS));
$sql = sprintf(
    'SELECT ao.id FROM affiliates_orders ao
	LEFT JOIN orders o ON o.orders_id = ao.orders_id
	WHERE DATE(ao.date_order_completed) <= (NOW() - INTERVAL %d DAY) AND ao.status = "%s" AND o.orders_status IN (%s)',
    AFFILLIATES_DAYS_LEFT,
	'pending',
	implode(',', $orderStatus)
);

$sql = tep_db_query($sql);

$ids = [];
while ($order = tep_db_fetch_array($sql)) {
    $ids[] = $order['id'];
}

if (!empty($ids)) {
    $sql = 'UPDATE affiliates_orders SET date_processed = NOW(), status = "prepared" WHERE id IN (' . implode(',', $ids) . ')';
    tep_db_query($sql);
}
