<?php
/**
 * api-warehouse-sync.php
 *
 * Inbound endpoint that receives per-line warehouse status from the RAG host
 * (192.168.1.112 / desktop-1e4veeu, NAT'd through 217.127.199.171). Used by the
 * customer order history page to show "reservado" / "esperando" badges on each
 * product of orders in status=2 (Proceso).
 *
 * Auth:
 *   - Authorization: Bearer <key from /home/francobordo/.api-warehouse-sync-key>
 *   - Source IP must match WAREHOUSE_SYNC_ALLOWED_IP
 *
 * Body (POST application/json):
 *   {
 *     "orders": [
 *       {
 *         "orders_id": 10358673,
 *         "lines": [
 *           {"sku": "A341151", "status": "reservado", "qty": 3},
 *           {"sku": "A341152", "status": "esperando", "qty": 1}
 *         ]
 *       },
 *       ...
 *     ]
 *   }
 *
 * Behavior:
 *   For every order_id in the payload, the existing rows in
 *   orders_warehouse_status are wiped and replaced atomically with the lines
 *   provided. The endpoint also resolves products_id by joining
 *   products.CCODIART = sku (best effort; defaults to 0 if not found).
 */

// ---- Defensive request method check ------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

// ---- Auth model: IP allowlist ONLY ------------------------------------
// Imunify360 (WAF on this host) silently rewrites high-entropy strings inside
// request bodies/headers/query, breaking shared-secret auth for endpoints
// that POST large bodies. The IP allowlist is the only secure boundary
// (HTTPS+TCP source IP can't be spoofed; LAN-internal address). To re-enable
// token auth, whitelist the URL in WHM > Imunify360 > Proactive Defense.
$allowedIps = ['217.127.199.171', '127.0.0.1', '::1'];
// SOLO REMOTE_ADDR (IP TCP real). NO usar X-Forwarded-For: es una cabecera que
// el cliente puede falsear ("X-Forwarded-For: 217.127.199.171") y permitiria
// saltarse el allowlist. REMOTE_ADDR es la fuente fiable en este host (igual que
// api-stock.php / api-orders.php).
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($clientIp, $allowedIps, true)) {
    http_response_code(403);
    echo json_encode(['error' => 'ip_not_allowed', 'ip' => $clientIp]);
    exit;
}

// ---- Read body ---------------------------------------------------------
// Hybrid: prefer form-encoded `data` field (Imunify-safe), fall back to raw JSON.
$rawBody = file_get_contents('php://input');
$jsonRaw = isset($_POST['data']) ? (string)$_POST['data'] : (string)$rawBody;
if ($jsonRaw === '') {
    http_response_code(400);
    echo json_encode(['error' => 'empty_body']);
    exit;
}
$payload = json_decode($jsonRaw, true);
if (!is_array($payload) || !isset($payload['orders']) || !is_array($payload['orders'])) {
    http_response_code(400);
    echo json_encode(['error' => 'malformed_payload']);
    exit;
}

// ---- DB connect (reuse osCommerce credentials) ------------------------
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
$ordersTouched = 0;
$linesUpserted = 0;
$skippedOrders = 0;
$statusTransitions = ['5_to_7' => 0, '7_to_5' => 0];

$fullSnapshot = !empty($payload['full_snapshot']);
$activeIds = [];
foreach ($payload['orders'] as $orderPayload) {
    if (is_array($orderPayload) && isset($orderPayload['orders_id'])) {
        $oid = (int)$orderPayload['orders_id'];
        if ($oid > 0) { $activeIds[$oid] = true; }
    }
}
$ordersDeleted = 0;

$db->begin_transaction();
try {
    $stmtDelete = $db->prepare("DELETE FROM orders_warehouse_status WHERE orders_id = ?");
    $stmtResolveProductId = $db->prepare("SELECT products_id FROM products WHERE CCODIART = ? LIMIT 1");
    $stmtInsert = $db->prepare(
        "INSERT INTO orders_warehouse_status "
        . "(orders_id, products_id, sku, variante, status, qty, arrival_date, last_updated) "
        . "VALUES (?, ?, ?, ?, ?, ?, ?, ?) "
        . "ON DUPLICATE KEY UPDATE products_id=VALUES(products_id), status=VALUES(status), "
        . "qty=VALUES(qty), arrival_date=VALUES(arrival_date), last_updated=VALUES(last_updated)"
    );

    // If the payload is a full snapshot, drop rows for orders that are no
    // longer in the active set. Stale rows from shipped/cancelled orders are
    // cleaned up on every sync this way.
    if ($fullSnapshot) {
        if (empty($activeIds)) {
            $res = $db->query("DELETE FROM orders_warehouse_status");
            $ordersDeleted = $db->affected_rows;
        } else {
            $idList = implode(',', array_map('intval', array_keys($activeIds)));
            $res = $db->query("DELETE FROM orders_warehouse_status WHERE orders_id NOT IN ($idList)");
            $ordersDeleted = $db->affected_rows;
        }
    }

    // Prepared statements for the 5⇄7 status auto-correction (run once per
    // order after its lines are upserted). We only ever transition between
    // 5 (Enviado) and 7 (Enviado Parcialmente) — any other state is left
    // untouched (Procesando, En preparación, Cancelado, etc. are managed by
    // QFacWin and the operators).
    $stmtCurrentStatus = $db->prepare("SELECT orders_status FROM orders WHERE orders_id = ? LIMIT 1");
    $stmtUpdateStatus = $db->prepare("UPDATE orders SET orders_status = ? WHERE orders_id = ?");
    $stmtInsertHistory = $db->prepare(
        "INSERT INTO orders_status_history (orders_id, orders_status_id, date_added, customer_notified, comments) "
        . "VALUES (?, ?, ?, 0, ?)"
    );

    foreach ($payload['orders'] as $orderPayload) {
        if (!is_array($orderPayload)) { continue; }
        $oid = isset($orderPayload['orders_id']) ? (int)$orderPayload['orders_id'] : 0;
        if ($oid <= 0) { $skippedOrders++; continue; }
        $lines = isset($orderPayload['lines']) && is_array($orderPayload['lines']) ? $orderPayload['lines'] : [];

        // Wipe-and-rewrite per order so a closed order or a removed line
        // disappears immediately on the next sync.
        $stmtDelete->bind_param('i', $oid);
        $stmtDelete->execute();

        // Track per-order line counts to drive the 5⇄7 auto-correction.
        $pendingCount = 0;
        $shippedCount = 0;

        foreach ($lines as $line) {
            if (!is_array($line)) { continue; }
            $sku    = isset($line['sku']) ? (string)$line['sku'] : '';
            $status = isset($line['status']) ? (string)$line['status'] : '';
            $qty    = isset($line['qty']) ? (float)$line['qty'] : 1.0;
            $arrival = null;
            if (isset($line['arrival_date']) && $line['arrival_date']) {
                $candidate = (string)$line['arrival_date'];
                // Accept YYYY-MM-DD (canonical) — anything else we ignore.
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $candidate)) {
                    $arrival = $candidate;
                }
            }
            if ($sku === '' || !in_array($status, ['reservado', 'esperando', 'enviado'], true)) { continue; }

            // Resolve products_id from products.CCODIART (best effort)
            $productsId = 0;
            $stmtResolveProductId->bind_param('s', $sku);
            $stmtResolveProductId->execute();
            $resolved = $stmtResolveProductId->get_result();
            if ($resolved && ($r = $resolved->fetch_assoc())) {
                $productsId = (int)$r['products_id'];
            }

            $variante = isset($line['variante']) ? (string)$line['variante'] : '';
            $stmtInsert->bind_param('iisssdss', $oid, $productsId, $sku, $variante, $status, $qty, $arrival, $nowSql);
            $stmtInsert->execute();
            $linesUpserted++;

            if ($status === 'reservado' || $status === 'esperando') {
                $pendingCount++;
            } elseif ($status === 'enviado') {
                $shippedCount++;
            }
        }
        $ordersTouched++;

        // ---- 5⇄7 auto-correction --------------------------------------
        // Only act when there's at least one shipped line (= QFacWin already
        // facturó al menos un albarán). Otherwise we don't have authority to
        // touch the status — the order is still being prepared.
        if ($shippedCount > 0) {
            $stmtCurrentStatus->bind_param('i', $oid);
            $stmtCurrentStatus->execute();
            $resCur = $stmtCurrentStatus->get_result();
            $rowCur = $resCur ? $resCur->fetch_assoc() : null;
            $currentStatus = $rowCur ? (int)$rowCur['orders_status'] : 0;

            if ($currentStatus === 5 || $currentStatus === 7) {
                $shouldBe = ($pendingCount > 0) ? 7 : 5;
                if ($shouldBe !== $currentStatus) {
                    if ($shouldBe === 7) {
                        // 5 → 7: caso defensivo cuando QFacWin facturó antes
                        // de que el sync VStock viera las líneas pendientes
                        // de la siguiente TAREA.
                        $comment = '<p>Estimado cliente:</p>' . "\n"
                            . '<p>Su pedido se ha reclasificado como <strong>Enviado Parcialmente</strong> porque quedan productos pendientes de enviar. Le notificaremos cuando se complete el envío del resto.</p>';
                    } else {
                        // 7 → 5: caso típico cuando llega el último albarán y
                        // ya no hay líneas reservado/esperando.
                        $comment = '<p>Estimado cliente:</p>' . "\n"
                            . '<p>Su pedido se ha completado: todos los productos han sido enviados. Gracias por su confianza.</p>';
                    }
                    $stmtUpdateStatus->bind_param('ii', $shouldBe, $oid);
                    $stmtUpdateStatus->execute();
                    $stmtInsertHistory->bind_param('iiss', $oid, $shouldBe, $nowSql, $comment);
                    $stmtInsertHistory->execute();
                    $key = $currentStatus . '_to_' . $shouldBe;
                    if (isset($statusTransitions[$key])) {
                        $statusTransitions[$key]++;
                    }
                }
            }
        }
    }

    // ---- Anulados en VStock (sin albaran QFac) -> cancelar en web ----
    // Regla 2026-07-16: VStock PCL_ESTADO=18 + QFac CSERVIDA='S' + SIN albaran
    // (el sync ya filtro; los CON albaran son ENVIOS DIRECTOS y no llegan aqui).
    // Solo se cancelan pedidos en estados iniciales; nunca enviados/entregados.
    $webCancelled = 0;
    $cancelSkipped = 0;
    if (isset($payload['cancelled_orders']) && is_array($payload['cancelled_orders'])) {
        $stmtStatus = $db->prepare("SELECT orders_status FROM orders WHERE orders_id = ? LIMIT 1");
        $stmtCancel = $db->prepare("UPDATE orders SET orders_status = 4 WHERE orders_id = ? AND orders_status = ?");
        $stmtHist = $db->prepare(
            "INSERT INTO orders_status_history (orders_id, orders_status_id, date_added, customer_notified, comments) "
            . "VALUES (?, 4, ?, 0, ?)"
        );
        $cancellableStates = [1, 2, 13, 309];
        $comment = '<p>Pedido anulado en almacén (VStock) y cerrado en QFac sin albarán. '
            . 'Cancelado automáticamente por la sincronización de almacén.</p>';
        foreach ($payload['cancelled_orders'] as $cid) {
            $cid = (int)$cid;
            if ($cid <= 0) { continue; }
            $stmtStatus->bind_param('i', $cid);
            $stmtStatus->execute();
            $resSt = $stmtStatus->get_result();
            $rowSt = $resSt ? $resSt->fetch_assoc() : null;
            if (!$rowSt) { $cancelSkipped++; continue; }
            $curSt = (int)$rowSt['orders_status'];
            if (!in_array($curSt, $cancellableStates, true)) { $cancelSkipped++; continue; }
            $stmtCancel->bind_param('ii', $cid, $curSt);
            $stmtCancel->execute();
            if ($stmtCancel->affected_rows === 1) {
                $stmtHist->bind_param('iss', $cid, $nowSql, $comment);
                $stmtHist->execute();
                $webCancelled++;
            } else {
                $cancelSkipped++;
            }
        }
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
    'orders_touched' => $ordersTouched,
    'lines_upserted' => $linesUpserted,
    'skipped_orders' => $skippedOrders,
    'orders_purged' => $ordersDeleted,
    'full_snapshot' => $fullSnapshot,
    'status_transitions' => $statusTransitions,
    'web_cancelled' => $webCancelled,
    'cancel_skipped' => $cancelSkipped,
    'updated_at' => $nowSql,
]);
