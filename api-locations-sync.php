<?php
/**
 * api-locations-sync.php
 *
 * Recibe el snapshot completo de ubicaciones físicas por variante desde el
 * sync VStock (sync_locations_to_web.py en el servidor Kayako, sale por
 * 217.127.199.171). Rellena product_warehouse_locations para que
 * product_info.php muestre stock+ubicaciones al staff interno.
 *
 * Auth: IP allowlist ONLY (Imunify360 reescribe shared secrets — mismo patrón
 * que api-warehouse-sync.php / api-stock-sync.php). TCP+TLS source IP no es
 * spoofeable y el endpoint sólo escribe datos no sensibles.
 *
 * Body (POST, form field `data` para evitar el mangling de JSON de Imunify):
 *   {
 *     "full_snapshot": true,
 *     "rows": [
 *       {"sku":"11249","prop1":"2,9X32MM/20UNID","prop2":"","prop3":"",
 *        "ubicacion":"1T1061301","unidades":44}
 *     ]
 *   }
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

// IP allowlist ONLY
$allowedIps = ['217.127.199.171', '127.0.0.1', '::1'];
$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$clientIp = trim(explode(',', $clientIp)[0]);
if (!in_array($clientIp, $allowedIps, true)) {
    http_response_code(403);
    echo json_encode(['error' => 'ip_not_allowed', 'ip' => $clientIp]);
    exit;
}

$rawBody = file_get_contents('php://input');
$jsonRaw = isset($_POST['data']) ? (string)$_POST['data'] : (string)$rawBody;
if ($jsonRaw === '') {
    http_response_code(400);
    echo json_encode(['error' => 'empty_body']);
    exit;
}
$payload = json_decode($jsonRaw, true);
if (!is_array($payload) || !isset($payload['rows']) || !is_array($payload['rows'])) {
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
$db->query("SET SESSION sql_mode = ''");

$nowSql = date('Y-m-d H:i:s');
$fullSnapshot = !empty($payload['full_snapshot']);
$inserted = 0;
$skipped = 0;

$db->begin_transaction();
try {
    // En snapshot completo, vaciamos toda la tabla y reescribimos.
    if ($fullSnapshot) {
        $db->query("DELETE FROM product_warehouse_locations");
    }

    $stmt = $db->prepare(
        "INSERT INTO product_warehouse_locations "
        . "(sku, prop1, prop2, prop3, variante, ubicacion, unidades, disponible, last_updated) "
        . "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) "
        . "ON DUPLICATE KEY UPDATE variante=VALUES(variante), unidades=VALUES(unidades), disponible=VALUES(disponible), last_updated=VALUES(last_updated)"
    );

    foreach ($payload['rows'] as $row) {
        if (!is_array($row)) { $skipped++; continue; }
        $sku = isset($row['sku']) ? trim((string)$row['sku']) : '';
        $ubic = isset($row['ubicacion']) ? trim((string)$row['ubicacion']) : '';
        if ($sku === '' || $ubic === '') { $skipped++; continue; }
        $p1 = isset($row['prop1']) ? (string)$row['prop1'] : '';
        $p2 = isset($row['prop2']) ? (string)$row['prop2'] : '';
        $p3 = isset($row['prop3']) ? (string)$row['prop3'] : '';
        $u  = isset($row['unidades']) ? (float)$row['unidades'] : 0.0;
        $var = isset($row['variante']) ? (string)$row['variante'] : '';
        $disp = isset($row['disponible']) ? (float)$row['disponible'] : 0.0;
        $stmt->bind_param('ssssssdds', $sku, $p1, $p2, $p3, $var, $ubic, $u, $disp, $nowSql);
        $stmt->execute();
        $inserted++;
    }

    $db->commit();
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
    'rows_inserted' => $inserted,
    'rows_skipped' => $skipped,
    'full_snapshot' => $fullSnapshot,
    'updated_at' => $nowSql,
]);
