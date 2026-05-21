<?php
/**
 * SalesManago — order scanner (PURCHASE safety net).
 *
 * URL: /_admin/sm_order_scanner.php?token=<SALESMANAGO_CRON_TOKEN>[&hours=N]
 *
 * Why this exists:
 *   The PURCHASE emitter in checkout_process.php only fires for payment methods
 *   that finalise the order in that script (e.g. bank transfer). Gateway
 *   payments (Redsys card / PayPal / Bizum) confirm the order via IPN/callback
 *   or holding-order conversion, which never reaches checkout_process.php.
 *
 *   This scanner works at the DATA layer: it finds orders in the `orders`
 *   table created within the window that don't yet have a PURCHASE event, and
 *   enqueues them. Robust regardless of how the order was created.
 *
 * Dedup: PURCHASE:{orders_id} guarantees no duplicate even if checkout_process
 * already emitted the same order.
 *
 * @see includes/classes/SalesManagoQueue.php
 */

require_once 'includes/application_top.php';

@set_time_limit(290);
@ini_set('display_errors', '0');
header('Content-Type: text/plain; charset=utf-8');

// --- Auth ---
$token = $_GET['token'] ?? '';
$expected = defined('SALESMANAGO_CRON_TOKEN') ? (string) SALESMANAGO_CRON_TOKEN : '';
if ($expected === '' || !hash_equals($expected, (string) $token)) {
    http_response_code(403);
    echo "forbidden\n";
    exit;
}

// --- Master switch ---
if (!defined('SALESMANAGO_STATUS') || SALESMANAGO_STATUS !== 'true') {
    echo "SM_STATUS=off — skip\n";
    exit;
}
if (!defined('SALESMANAGO_SEND_PURCHASE') || SALESMANAGO_SEND_PURCHASE !== 'true') {
    echo "SEND_PURCHASE=off — skip\n";
    exit;
}

require_once DIR_FS_CATALOG . 'includes/classes/SalesManagoQueue.php';

// Window: GET ?hours overrides config (for one-off backfills)
$hours = isset($_GET['hours'])
    ? max(1, min(720, (int) $_GET['hours']))
    : (defined('SALESMANAGO_PURCHASE_SCAN_HOURS') ? max(1, (int) SALESMANAGO_PURCHASE_SCAN_HOURS) : 6);

// Excluded statuses (CSV of orders_status ids that are NOT a purchase)
$excludeRaw = defined('SALESMANAGO_PURCHASE_EXCLUDE_STATUS') ? (string) SALESMANAGO_PURCHASE_EXCLUDE_STATUS : '1';
$excludeIds = array_filter(array_map('intval', explode(',', $excludeRaw)), fn($v) => $v > 0);
$excludeClause = !empty($excludeIds)
    ? ' AND o.orders_status NOT IN (' . implode(',', $excludeIds) . ')'
    : '';

$tStart = microtime(true);

$sql = "SELECT o.orders_id
        FROM orders o
        WHERE o.date_purchased >= DATE_SUB(NOW(), INTERVAL $hours HOUR)
          $excludeClause
          AND NOT EXISTS (
            SELECT 1 FROM sm_event_queue q
            WHERE q.dedup_key = CONCAT('PURCHASE:', o.orders_id)
          )
        ORDER BY o.orders_id ASC";

$res = tep_db_query($sql);
$orderIds = [];
while ($r = tep_db_fetch_array($res)) {
    $orderIds[] = (int) $r['orders_id'];
}

echo "candidates: " . count($orderIds) . " (window={$hours}h, exclude_status=[" . implode(',', $excludeIds) . "])\n";

$nEnqueued = $nSkipped = 0;
foreach ($orderIds as $oid) {
    // emitPurchase enqueues with dedup PURCHASE:{oid}; returns id or 0/null
    SalesManagoQueue::emitPurchase($oid);
    // Confirm it actually landed (emitPurchase is void; check the queue)
    $chk = tep_db_fetch_array(tep_db_query(
        "SELECT 1 AS ok FROM sm_event_queue WHERE dedup_key='PURCHASE:" . $oid . "' LIMIT 1"));
    if ($chk) { $nEnqueued++; echo "  +purchase order=$oid\n"; }
    else      { $nSkipped++; }
}

$elapsed = round((microtime(true) - $tStart) * 1000);
echo "done: enqueued=$nEnqueued skipped=$nSkipped elapsed_ms=$elapsed\n";
