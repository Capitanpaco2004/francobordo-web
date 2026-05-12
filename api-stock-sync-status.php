<?php
/**
 * api-stock-sync-status.php
 *
 * Receives the run summary from sync_stock_to_web.py at the end of every cron
 * run and inserts it into stock_sync_runs so the admin dashboard at
 * /_admin/stock_sync.php can render trends without SSH'ing into Kayako.
 *
 * Same auth pattern as api-stock-sync.php: Bearer + IP allowlist.
 * Reuses /home/francobordo/.api-stock-sync-key (single key for the family).
 *
 * Body:
 *   {
 *     "started_at": "2026-04-30 19:14:38",
 *     "finished_at": "2026-04-30 19:14:47",
 *     "elapsed_ms": 9380,
 *     "dry_run": true,
 *     "stats": {
 *       "lines_received": 85997, "no_change": 75612,
 *       "product_qty_updated": 15600, "variant_qty_updated": 342,
 *       "preserved_sentinel": 12700,
 *       "sku_not_found": 9383, "variant_not_found": 6498
 *     },
 *     "diff_sample": [...up to 50 entries...]
 *   }
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

// Auth via IP allowlist only — see api-stock-sync.php for rationale (Imunify360
// rewrites high-entropy strings in request bodies, breaking shared-secret auth).
$allowedIps = ['217.127.199.171', '127.0.0.1', '::1'];
$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$clientIp = trim(explode(',', $clientIp)[0]);
if (!in_array($clientIp, $allowedIps, true)) {
    http_response_code(403);
    echo json_encode(['error' => 'ip_not_allowed', 'ip' => $clientIp]);
    exit;
}

$rawBody = file_get_contents('php://input');
$jsonRaw = isset($_POST['data']) ? (string)$_POST['data'] : $rawBody;
$payload = json_decode($jsonRaw, true);
if (!is_array($payload) || !isset($payload['stats']) || !is_array($payload['stats'])) {
    http_response_code(400);
    echo json_encode(['error' => 'malformed_payload']);
    exit;
}

require_once __DIR__ . '/includes/configure.php';
$db = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($db->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'db_connect_failed']);
    exit;
}
$db->set_charset('utf8mb4');

$stats     = $payload['stats'];
$startedAt = isset($payload['started_at']) ? (string)$payload['started_at'] : date('Y-m-d H:i:s');
$finishedAt= isset($payload['finished_at']) ? (string)$payload['finished_at'] : date('Y-m-d H:i:s');
$elapsedMs = (int)($payload['elapsed_ms'] ?? 0);
$dryRun    = !empty($payload['dry_run']) ? 1 : 0;
$diffSampleJson = isset($payload['diff_sample']) ? json_encode($payload['diff_sample'], JSON_UNESCAPED_UNICODE) : null;
$skuNFSampleJson = isset($payload['sku_not_found_sample']) ? json_encode($payload['sku_not_found_sample'], JSON_UNESCAPED_UNICODE) : null;
$varNFSampleJson = isset($payload['variant_not_found_sample']) ? json_encode($payload['variant_not_found_sample'], JSON_UNESCAPED_UNICODE) : null;

$stmt = $db->prepare(
    "INSERT INTO stock_sync_runs "
    . "(started_at, finished_at, elapsed_ms, dry_run, lines_received, no_change, "
    . "product_qty_updated, variant_qty_updated, preserved_sentinel, sku_not_found, "
    . "variant_not_found, diff_sample, sku_not_found_sample, variant_not_found_sample) "
    . "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
$linesReceived     = (int)($stats['lines_received'] ?? 0);
$noChange          = (int)($stats['no_change'] ?? 0);
$productQtyUpdated = (int)($stats['product_qty_updated'] ?? 0);
$variantQtyUpdated = (int)($stats['variant_qty_updated'] ?? 0);
$preservedSentinel = (int)($stats['preserved_sentinel'] ?? 0);
$skuNotFound       = (int)($stats['sku_not_found'] ?? 0);
$variantNotFound   = (int)($stats['variant_not_found'] ?? 0);
$stmt->bind_param(
    'ssiiiiiiiiisss',
    $startedAt, $finishedAt, $elapsedMs, $dryRun,
    $linesReceived, $noChange, $productQtyUpdated, $variantQtyUpdated,
    $preservedSentinel, $skuNotFound, $variantNotFound,
    $diffSampleJson, $skuNFSampleJson, $varNFSampleJson
);
$stmt->execute();
$runId = $db->insert_id;

// House-keeping: drop runs older than 7 days
$db->query("DELETE FROM stock_sync_runs WHERE started_at < NOW() - INTERVAL 7 DAY");

http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['ok' => true, 'run_id' => $runId]);
