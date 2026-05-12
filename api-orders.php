<?php
/**
 * API de pedidos para alimentar al RAG (commerce-intelligence-service).
 * Uso:
 *   GET /api-orders.php?email=cliente@ejemplo.com&limit=10
 *   GET /api-orders.php?orders_id=10358506
 * Auth:
 *   Authorization: Bearer <key>   (consumidores en /home/francobordo/.api-tokens, ver api_auth.php)
 * Allowlist:
 *   Solo IPs en $ALLOWED_IPS (RAG/Kayako salen por 217.127.199.171)
 */
declare(strict_types=1);

// 1) HTTPS only (rechaza HTTP)
$_isHttps = !empty($_SERVER['HTTPS']) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
if (!$_isHttps) {
    http_response_code(403);
    exit('https only');
}

// 2) IP allowlist
$ALLOWED_IPS = ['217.127.199.171', '40.115.11.160']; // Oct8ne bot 2026-05-07
$_remoteIP = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($_remoteIP, $ALLOWED_IPS, true)) {
    http_response_code(403);
    exit('forbidden');
}

// 3) Bearer auth (multi-consumidor; etiquetas en /home/francobordo/.api-tokens)
require_once '/home/francobordo/api_auth.php';
$_tokens = fb_api_load_tokens('/home/francobordo/.api-tokens');
if (empty($_tokens)) {
    http_response_code(500);
    exit('config');
}
$_authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
$_apiConsumer = fb_api_authorize($_authHeader, $_tokens);
if ($_apiConsumer === null) {
    http_response_code(401);
    exit('unauthorized');
}

// 4) DB
require '/home/francobordo/public_html/includes/configure.php';
$pdo = new PDO(
    'mysql:host=' . DB_SERVER . ';dbname=' . DB_DATABASE . ';charset=utf8mb4',
    DB_SERVER_USERNAME,
    DB_SERVER_PASSWORD,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

header('Content-Type: application/json; charset=utf-8');

// === Helpers ===

function status_names(PDO $pdo): array {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach ($pdo->query("SELECT orders_status_id, orders_status_name FROM orders_status WHERE language_id = 3") as $r) {
            $cache[(int)$r['orders_status_id']] = $r['orders_status_name'];
        }
    }
    return $cache;
}

function tracking_for(PDO $pdo, int $orders_id): ?array {
    // Solo se inyecta tracking en el comment cuando el estado pasa a Enviado (5) o Enviado Parcialmente (7)
    $stmt = $pdo->prepare("SELECT date_added, comments FROM orders_status_history
                            WHERE orders_id = ? AND orders_status_id IN (5, 7)
                              AND comments LIKE '%http%'
                            ORDER BY date_added DESC LIMIT 1");
    $stmt->execute([$orders_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    if (!preg_match('@https?://[^\s<"\']+@i', $row['comments'], $m)) return null;
    $url = $m[0];
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    if (str_contains($host, 'correosexpress'))                          $carrier = 'Correos Express';
    elseif (str_contains($host, 'correos.es'))                          $carrier = 'Correos';
    elseif (str_contains($host, 'alertran') || str_contains($host, 'ontimegts')) $carrier = 'On Time';
    else                                                                 $carrier = $host;
    return ['url' => $url, 'carrier' => $carrier, 'added_at' => $row['date_added']];
}

function order_total(PDO $pdo, int $orders_id): ?float {
    $stmt = $pdo->prepare("SELECT value FROM orders_total WHERE orders_id = ? AND class = 'ot_total' LIMIT 1");
    $stmt->execute([$orders_id]);
    $v = $stmt->fetchColumn();
    return $v === false ? null : round((float)$v, 2);
}

/**
 * Devuelve el progreso de envío del pedido cruzando orders_warehouse_status
 * (alimentado por el sync VStock cada 5 min) con orders_products y products.
 * Sólo devuelve algo si hay datos de almacén — pedidos sin seguimiento (muy
 * antiguos o no procesados aún por VStock) reciben null.
 *
 * Estructura:
 *   {
 *     total_ordered: <float>,
 *     total_shipped: <float>,
 *     items_shipped: [{sku, name, qty}],
 *     items_pending: [{sku, name, qty, status: 'reservado'|'esperando', arrival_date?: 'YYYY-MM-DD'}]
 *   }
 */
function shipping_progress_for(PDO $pdo, int $orders_id): ?array {
    $check = $pdo->prepare("SELECT COUNT(*) FROM orders_warehouse_status WHERE orders_id = ?");
    $check->execute([$orders_id]);
    if ((int)$check->fetchColumn() === 0) return null;

    $tot = $pdo->prepare("SELECT COALESCE(SUM(products_quantity),0) FROM orders_products WHERE orders_id = ?");
    $tot->execute([$orders_id]);
    $totalOrdered = (float)$tot->fetchColumn();

    // Resuelve el nombre del producto en español (language_id=3) vía CCODIART.
    $linesStmt = $pdo->prepare("
        SELECT ws.sku, ws.status, ws.qty, ws.arrival_date,
               COALESCE(NULLIF(TRIM(pd.products_name), ''), '') AS name
        FROM orders_warehouse_status ws
        LEFT JOIN products p ON p.CCODIART = ws.sku
        LEFT JOIN products_description pd ON pd.products_id = p.products_id AND pd.language_id = 3
        WHERE ws.orders_id = ?
        ORDER BY ws.status, ws.sku
    ");
    $linesStmt->execute([$orders_id]);

    $fmtQty = static function (float $q) {
        return abs($q - round($q)) < 0.001 ? (int)round($q) : round($q, 3);
    };

    $itemsShipped = [];
    $itemsPending = [];
    $totalShipped = 0.0;
    foreach ($linesStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $qty = (float)$r['qty'];
        $entry = [
            'sku'  => $r['sku'],
            'name' => $r['name'] !== '' ? $r['name'] : null,
            'qty'  => $fmtQty($qty),
        ];
        if ($r['status'] === 'enviado') {
            $itemsShipped[] = $entry;
            $totalShipped += $qty;
        } else {
            $entry['status'] = $r['status']; // 'reservado' o 'esperando'
            if (!empty($r['arrival_date'])) {
                $entry['arrival_date'] = $r['arrival_date'];
            }
            $itemsPending[] = $entry;
        }
    }

    return [
        'total_ordered' => $fmtQty($totalOrdered),
        'total_shipped' => $fmtQty($totalShipped),
        'items_shipped' => $itemsShipped,
        'items_pending' => $itemsPending,
    ];
}

function order_to_json(PDO $pdo, array $o, bool $includeItems): array {
    $statuses = status_names($pdo);
    $orders_id = (int)$o['orders_id'];
    $statusId = (int)$o['orders_status'];

    $est = $pdo->prepare("SELECT estimated_date, rule_applied, email_sent FROM orders_delivery_estimate WHERE orders_id = ?");
    $est->execute([$orders_id]);
    $estRow = $est->fetch(PDO::FETCH_ASSOC) ?: [];

    $out = [
        'orders_id' => $orders_id,
        'date_purchased' => $o['date_purchased'],
        'orders_status_id' => $statusId,
        'orders_status' => $statuses[$statusId] ?? null,
        'estimated_delivery' => $estRow['estimated_date'] ?? null,
        'estimated_delivery_rule' => $estRow['rule_applied'] ?? null,
        'estimated_delivery_notified' => isset($estRow['email_sent']) ? (bool)$estRow['email_sent'] : null,
        'total_amount' => order_total($pdo, $orders_id),
        'currency' => $o['currency'],
        'shipping_module' => $o['shipping_module'],
        'delivery_city' => $o['delivery_city'],
        'delivery_postcode' => $o['delivery_postcode'],
        'delivery_country' => $o['delivery_country'],
        'tracking' => tracking_for($pdo, $orders_id),
        'shipping_progress' => shipping_progress_for($pdo, $orders_id),
    ];

    if ($includeItems) {
        $items = $pdo->prepare("SELECT products_id, products_name, products_quantity, final_price FROM orders_products WHERE orders_id = ?");
        $items->execute([$orders_id]);
        $out["products"] = array_map(fn($i) => [
            'products_id' => (int)$i['products_id'],
            'name' => $i['products_name'],
            'qty' => (int)$i['products_quantity'],
            'price' => round((float)$i['final_price'], 2),
        ], $items->fetchAll(PDO::FETCH_ASSOC));
    }
    return $out;
}

// === Routing ===

try {
    $orderColumns = "orders_id, date_purchased, orders_status, currency, shipping_module,
                     delivery_city, delivery_postcode, delivery_country";

    if (!empty($_GET['email'])) {
        $email = strtolower(trim((string)$_GET['email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid email']);
            exit;
        }
        $limit = max(1, min(50, (int)($_GET['limit'] ?? 10)));
        $stmt = $pdo->prepare("
            SELECT $orderColumns FROM orders
            WHERE LOWER(customers_email_address) = ?
              AND date_purchased >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)
            ORDER BY date_purchased DESC
            LIMIT $limit
        ");
        $stmt->execute([$email]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'email' => $email,
            'count' => count($orders),
            'orders' => array_map(fn($o) => order_to_json($pdo, $o, false), $orders),
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    if (!empty($_GET['orders_id'])) {
        $oid = (int)$_GET['orders_id'];
        if ($oid <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid orders_id']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT $orderColumns FROM orders WHERE orders_id = ?");
        $stmt->execute([$oid]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            http_response_code(404);
            echo json_encode(['error' => 'not found']);
            exit;
        }
        echo json_encode(order_to_json($pdo, $order, true), JSON_UNESCAPED_SLASHES);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'use ?email=... or ?orders_id=...']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'server error']);
}
