<?php
/**
 * SalesManago — abandoned cart scanner.
 *
 * URL: /_admin/sm_cart_scanner.php?token=<SALESMANAGO_CRON_TOKEN>
 *
 * Called by cron every 15 min. Auth via the same token as the queue worker.
 *
 * Logic:
 *   1. Find customers with non-empty baskets whose latest basket modification
 *      is between (NOW - MAX_HOURS) and (NOW - MIN_MINUTES).
 *   2. Exclude those who have placed an order AFTER their last basket activity.
 *   3. Exclude sm_excluded contacts.
 *   4. For each abandoned cart, call SalesManagoQueue::emitAbandonedCart() —
 *      which dedups on cart-contents hash, so unchanged carts don't re-fire.
 *
 * The worker (sm_worker.php) then dispatches CART events to SM.
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
if (!defined('SALESMANAGO_SEND_CART') || SALESMANAGO_SEND_CART !== 'true') {
    echo "SEND_CART=off — skip (set SALESMANAGO_SEND_CART=true to enable)\n";
    exit;
}

require_once DIR_FS_CATALOG . 'includes/classes/SalesManagoQueue.php';

$minMinutes = defined('SALESMANAGO_CART_MIN_MINUTES') ? max(1, (int) SALESMANAGO_CART_MIN_MINUTES) : 30;
$maxHours   = defined('SALESMANAGO_CART_MAX_HOURS')   ? max(1, (int) SALESMANAGO_CART_MAX_HOURS)   : 24;

$tStart = microtime(true);

// Candidates: customers with carts inside the window who haven't ordered after their last basket activity
$sql = "SELECT b.customers_id, b.last_mod
        FROM (
          SELECT customers_id, MAX(customers_basket_modified) AS last_mod
          FROM customers_basket
          WHERE customers_basket_modified BETWEEN DATE_SUB(NOW(), INTERVAL $maxHours HOUR)
                                              AND DATE_SUB(NOW(), INTERVAL $minMinutes MINUTE)
            AND customers_basket_quantity > 0
          GROUP BY customers_id
        ) b
        JOIN customers c ON c.customers_id = b.customers_id
        LEFT JOIN (
          SELECT customers_id, MAX(date_purchased) AS last_order
          FROM orders
          WHERE date_purchased >= DATE_SUB(NOW(), INTERVAL " . ($maxHours + 1) . " HOUR)
          GROUP BY customers_id
        ) o ON o.customers_id = b.customers_id
        WHERE c.sm_excluded = 0
          AND c.customers_email_address LIKE '%@%.%'
          AND (o.last_order IS NULL OR o.last_order < b.last_mod)";

$res = tep_db_query($sql);
$candidates = [];
while ($r = tep_db_fetch_array($res)) {
    $candidates[] = $r;
}

echo "candidates: " . count($candidates) . " (min=$minMinutes min, max=$maxHours h)\n";

$nEnqueued = $nSkipped = 0;
foreach ($candidates as $r) {
    $cid = (int) $r['customers_id'];
    $id  = SalesManagoQueue::emitAbandonedCart($cid);
    if ($id === null) {
        $nSkipped++;
    } elseif ($id === 0) {
        // dedup hit — same cart already in queue
        $nSkipped++;
    } else {
        $nEnqueued++;
        echo "  +cart #$id customer=$cid\n";
    }
}

$elapsed = round((microtime(true) - $tStart) * 1000);
echo "done: enqueued=$nEnqueued skipped=$nSkipped elapsed_ms=$elapsed\n";
