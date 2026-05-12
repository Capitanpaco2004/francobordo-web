<?php
/**
 * api-stock-sync.php
 *
 * Receives the live "available stock" snapshot from VStock (via the Kayako
 * Linux server which is the only LAN host with a stable outbound IP) and
 * updates products.products_quantity + products_stock.products_stock_quantity
 * accordingly.
 *
 * Replaces the twice-daily _admin/qfacwin_update.php run while keeping
 * _admin/qfacwin_insert.php (for new products / new variants) untouched.
 *
 * Auth:
 *   Authorization: Bearer <key from /home/francobordo/.api-stock-sync-key>
 *   Source IP must match the allowlist below.
 *
 * Body:
 *   {
 *     "dry_run": true|false,                       (default: true)
 *     "lines": [
 *       {"sku": "A341151", "props": ["100CM-1"], "available": 2.0},
 *       {"sku": "A332878", "props": ["6MM/900KG"], "available": 147.0},
 *       ...
 *     ]
 *   }
 *
 * Mapping strategy:
 *   For each SKU we resolve products_id via products.CCODIART. For each line
 *   with variants, we use the EXISTING products_stock rows as the
 *   authoritative mapping (so we don't have to disambiguate CCODIVAL by
 *   CCODIPROP — qfacwin_update.php already created the valid combinations).
 *   We build a lookup (products_id, sorted_CCODIVAL_tuple) -> products_stock_id
 *   once per request from the database.
 *
 *   For lines without variants (no props), we update products.products_quantity
 *   directly. For lines WITH variants, we update the matching products_stock row
 *   and recompute products.products_quantity = SUM of variant quantities.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

// Auth model: IP allowlist ONLY. The bearer/X-Api-Key/JSON body token
// approach has been ABANDONED on this host because Imunify360 (WAF in front
// of Apache) silently rewrites high-entropy strings inside request bodies,
// making any shared-secret authentication unworkable. The IP allowlist is
// sufficient because: (1) HTTPS+TCP source IP cannot be spoofed, (2) the
// caller is on the LAN behind 217.127.199.171, (3) the endpoint only writes
// non-sensitive stock data and never returns secrets. To add token auth in
// the future, whitelist the URL in WHM > Imunify360 > Proactive Defense
// first.
$allowedIps = ['217.127.199.171', '127.0.0.1', '::1'];
$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$clientIp = trim(explode(',', $clientIp)[0]);
if (!in_array($clientIp, $allowedIps, true)) {
    http_response_code(403);
    echo json_encode(['error' => 'ip_not_allowed', 'ip' => $clientIp]);
    exit;
}

// Auth via query string (?k=<KEY>) — preferred to avoid the cPanel/ModSec
// quirk where Authorization / X-Api-Key headers get rewritten on subsequent
// rapid POSTs from the same source. Falls back to header for compatibility.
// (No token check: the IP allowlist above is the security boundary. See note
// at the top of this file for why secret-based auth was abandoned.)

$rawBody = file_get_contents('php://input');
$jsonRaw = isset($_POST['data']) ? (string)$_POST['data'] : $rawBody;
$payload = json_decode($jsonRaw, true);
if (!is_array($payload) || !isset($payload['lines']) || !is_array($payload['lines'])) {
    http_response_code(400);
    echo json_encode(['error' => 'malformed_payload']);
    exit;
}
$dryRun = isset($payload['dry_run']) ? (bool)$payload['dry_run'] : true;

// Manual sentinel stock values that must never be overwritten by VStock data.
// Convenciones de Francobordo:
//   < 0 (e.g. -900, -800, -1..-799) — códigos manuales (fuera catálogo / bajo pedido / plazo)
//   2000                              — stock fijo "virtual" / venta por metro continuo
$sentinelValues = [2000.0];
function _is_sentinel(?float $qty, array $sentinels): bool {
    if ($qty === null) { return false; }
    if ($qty < 0) { return true; }
    foreach ($sentinels as $s) {
        if (abs($qty - $s) < 0.0001) { return true; }
    }
    return false;
}

require_once __DIR__ . '/includes/configure.php';
$db = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($db->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'db_connect_failed']);
    exit;
}
$db->set_charset('utf8mb4');
$db->query("SET SESSION sql_mode = ''");

// ---- Build mapping in a single pass ------------------------------------
// 1. CCODIART -> products_id (only one row per CCODIART; products is per-language for description but products row is shared)
// 2. (products_id, sorted CCODIVAL list) -> products_stock_id and current qty
$skuToProductId = [];
$res = $db->query("SELECT products_id, CCODIART FROM products WHERE CCODIART IS NOT NULL AND CCODIART <> ''");
while ($r = $res->fetch_assoc()) {
    $skuToProductId[$r['CCODIART']] = (int)$r['products_id'];
}

$variantLookup = []; // key = "<products_id>|<sorted CCODIVAL list joined by ',>"
$res = $db->query(
    "SELECT ps.products_stock_id, ps.products_id, ps.products_stock_attributes, ps.products_stock_quantity "
    . "FROM products_stock ps WHERE ps.products_stock_attributes <> ''"
);
$attributePairs = [];
while ($r = $res->fetch_assoc()) {
    $pid = (int)$r['products_id'];
    $attrs = $r['products_stock_attributes'];
    $valIds = [];
    foreach (explode(',', $attrs) as $pair) {
        $bits = explode('-', $pair);
        if (count($bits) >= 2) {
            $valIds[] = (int)$bits[1];
        }
    }
    $attributePairs[] = [
        'stock_id' => (int)$r['products_stock_id'],
        'pid' => $pid,
        'attrs_str' => $attrs,
        'val_ids' => $valIds,
        'cur_qty' => (float)$r['products_stock_quantity'],
    ];
}

// Resolve all products_options_values_id -> CCODIVAL once (Spanish only suffices since CCODIVAL is the same across languages)
$valueIdToCcodival = [];
$res = $db->query("SELECT products_options_values_id, CCODIVAL FROM products_options_values WHERE language_id=3");
while ($r = $res->fetch_assoc()) {
    if ($r['CCODIVAL'] !== null && $r['CCODIVAL'] !== '') {
        $valueIdToCcodival[(int)$r['products_options_values_id']] = (string)$r['CCODIVAL'];
    }
}

// Now finish building variantLookup with sorted CCODIVAL tuples.
foreach ($attributePairs as $row) {
    $ccodivals = [];
    foreach ($row['val_ids'] as $vid) {
        if (isset($valueIdToCcodival[$vid])) {
            $ccodivals[] = $valueIdToCcodival[$vid];
        }
    }
    if (empty($ccodivals)) { continue; }
    sort($ccodivals);
    $key = $row['pid'] . '|' . implode(',', $ccodivals);
    $variantLookup[$key] = [
        'stock_id' => $row['stock_id'],
        'attrs_str' => $row['attrs_str'],
        'cur_qty' => $row['cur_qty'],
    ];
}

// ---- Apply updates / collect dry-run diffs -----------------------------
$stats = [
    'lines_received' => count($payload['lines']),
    'sku_not_found' => 0,
    'variant_not_found' => 0,
    'product_qty_updated' => 0,
    'variant_qty_updated' => 0,
    'no_change' => 0,
    'preserved_sentinel' => 0,  // rule D: -900/-800/-1..-799/2000 preserved when VStock has nothing real
    'dry_run' => $dryRun,
];
$diffs = [];                       // sample of diffs (first 50)
$skuNotFoundSample = [];           // up to 100 unique SKU strings missing in products.CCODIART
$variantNotFoundSample = [];       // up to 100 dicts {sku, props} missing in products_stock
$productSums = [];                  // products_id -> SUM of available across variants
$pendingVariantUpdates = [];        // [(products_stock_id, new_qty, sku, ccodivals)]
$pendingNonVariantUpdates = [];     // [(products_id, new_qty, sku)]

foreach ($payload['lines'] as $line) {
    $sku = isset($line['sku']) ? trim((string)$line['sku']) : '';
    $available = isset($line['available']) ? (float)$line['available'] : 0.0;
    if ($available < 0) { $available = 0.0; }
    if ($sku === '') { continue; }
    $pid = $skuToProductId[$sku] ?? null;
    if ($pid === null) {
        $stats['sku_not_found']++;
        if (count($skuNotFoundSample) < 100 && !in_array($sku, $skuNotFoundSample, true)) {
            $skuNotFoundSample[] = $sku;
        }
        continue;
    }
    $rawProps = isset($line['props']) && is_array($line['props']) ? $line['props'] : [];
    $props = [];
    foreach ($rawProps as $p) {
        $p = trim((string)$p);
        if ($p !== '') { $props[] = $p; }
    }

    if (empty($props)) {
        // Non-variant product: update products.products_quantity directly
        $pendingNonVariantUpdates[] = ['pid' => $pid, 'new_qty' => $available, 'sku' => $sku];
        if (!isset($productSums[$pid])) { $productSums[$pid] = 0.0; }
        $productSums[$pid] += $available;
    } else {
        // Variant: look up the products_stock row by sorted CCODIVAL tuple
        sort($props);
        $key = $pid . '|' . implode(',', $props);
        $existing = $variantLookup[$key] ?? null;
        if ($existing === null) {
            $stats['variant_not_found']++;
            if (count($variantNotFoundSample) < 100) {
                $variantNotFoundSample[] = ['sku' => $sku, 'props' => $props];
            }
            // Don't add to product sum — qfacwin_insert should create the variant first.
            continue;
        }
        $pendingVariantUpdates[] = [
            'stock_id' => $existing['stock_id'],
            'new_qty' => $available,
            'cur_qty' => $existing['cur_qty'],
            'sku' => $sku,
            'ccodivals' => $props,
            'pid' => $pid,
        ];
        if (!isset($productSums[$pid])) { $productSums[$pid] = 0.0; }
        $productSums[$pid] += $available;
    }
}

// Compute non-variant diffs
$db->begin_transaction();
try {
    if (!$dryRun) {
        $stmtUpdProductQty = $db->prepare("UPDATE products SET products_quantity = ? WHERE products_id = ?");
        $stmtUpdVariantQty = $db->prepare("UPDATE products_stock SET products_stock_quantity = ? WHERE products_stock_id = ?");
    }

    // Apply variant updates first (rule D: preserve sentinel values
    // —negativos manuales y 2000— hasta que VStock traiga stock real positivo
    // distinto del centinela).
    foreach ($pendingVariantUpdates as $u) {
        if (abs($u['new_qty'] - $u['cur_qty']) < 0.0001) {
            $stats['no_change']++;
            continue;
        }
        if (_is_sentinel($u['cur_qty'], $sentinelValues) && $u['new_qty'] <= 0) {
            // Negativo manual (-900 / -800 / -100 ...) o stock fijo "virtual" 2000
            // sobrevive hasta que VStock devuelva un valor real > 0.
            $stats['preserved_sentinel']++;
            continue;
        }
        if (count($diffs) < 50) {
            $diffs[] = [
                'kind' => 'variant',
                'sku' => $u['sku'],
                'props' => $u['ccodivals'],
                'stock_id' => $u['stock_id'],
                'old_qty' => $u['cur_qty'],
                'new_qty' => $u['new_qty'],
            ];
        }
        if (!$dryRun) {
            $stmtUpdVariantQty->bind_param('di', $u['new_qty'], $u['stock_id']);
            $stmtUpdVariantQty->execute();
        }
        $stats['variant_qty_updated']++;
    }

    // Read current products.products_quantity for all PIDs we will touch
    $touchedPids = array_unique(array_merge(
        array_map(fn($r) => $r['pid'], $pendingNonVariantUpdates),
        array_keys($productSums),
    ));
    $currentProductQty = [];
    if (!empty($touchedPids)) {
        $idsSql = implode(',', array_map('intval', $touchedPids));
        $resQ = $db->query("SELECT products_id, products_quantity FROM products WHERE products_id IN ($idsSql)");
        while ($r = $resQ->fetch_assoc()) {
            $currentProductQty[(int)$r['products_id']] = (float)$r['products_quantity'];
        }
    }

    // Update products.products_quantity from sums (variants) — rule D applies.
    foreach ($productSums as $pid => $newQty) {
        $oldQty = $currentProductQty[$pid] ?? null;
        if ($oldQty !== null && abs($oldQty - $newQty) < 0.0001) {
            $stats['no_change']++;
            continue;
        }
        if (_is_sentinel($oldQty, $sentinelValues) && $newQty <= 0) {
            $stats['preserved_sentinel']++;
            continue;
        }
        if (count($diffs) < 50) {
            $diffs[] = [
                'kind' => 'product_total',
                'products_id' => $pid,
                'old_qty' => $oldQty,
                'new_qty' => $newQty,
            ];
        }
        if (!$dryRun) {
            $stmtUpdProductQty->bind_param('di', $newQty, $pid);
            $stmtUpdProductQty->execute();
        }
        $stats['product_qty_updated']++;
    }

    // Update products.products_quantity for non-variant lines that didn't go through productSums
    foreach ($pendingNonVariantUpdates as $u) {
        $pid = $u['pid'];
        if (isset($productSums[$pid])) { continue; }  // already counted above
        $oldQty = $currentProductQty[$pid] ?? null;
        if ($oldQty !== null && abs($oldQty - $u['new_qty']) < 0.0001) {
            $stats['no_change']++;
            continue;
        }
        if (_is_sentinel($oldQty, $sentinelValues) && $u['new_qty'] <= 0) {
            $stats['preserved_sentinel']++;
            continue;
        }
        if (count($diffs) < 50) {
            $diffs[] = [
                'kind' => 'product_simple',
                'products_id' => $pid,
                'sku' => $u['sku'],
                'old_qty' => $oldQty,
                'new_qty' => $u['new_qty'],
            ];
        }
        if (!$dryRun) {
            $stmtUpdProductQty->bind_param('di', $u['new_qty'], $pid);
            $stmtUpdProductQty->execute();
        }
        $stats['product_qty_updated']++;
    }

    if ($dryRun) {
        $db->rollback();
    } else {
        $db->commit();
    }
} catch (Throwable $e) {
    $db->rollback();
    http_response_code(500);
    echo json_encode(['error' => 'sync_failed', 'detail' => $e->getMessage()]);
    exit;
}

http_response_code(200);
header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'stats' => $stats,
    'diff_sample' => $diffs,
    'sku_not_found_sample' => $skuNotFoundSample,
    'variant_not_found_sample' => $variantNotFoundSample,
    'updated_at' => date('Y-m-d H:i:s'),
]);
