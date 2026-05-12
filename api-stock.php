<?php
/**
 * API de stock + precio en vivo.
 * Uso:
 *   GET /api-stock.php?products_ids=12,34,56                       (hasta 50 ids)
 *   GET /api-stock.php?skus=A341151,B22330                         (lookup por products_model)
 *   GET /api-stock.php?products_ids=12,34&skus=A341151             (combina ambos)
 *   GET /api-stock.php?...&include_variants=1                      (incluye desglose por variante)
 * Limite total: 50 elementos entre ids y skus.
 * Auth y allowlist iguales que api-orders.php.
 */
declare(strict_types=1);

// Limita la precision serializada de floats en json_encode para evitar 22.8500000...014
ini_set('serialize_precision', '14');

$_isHttps = !empty($_SERVER['HTTPS']) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
if (!$_isHttps) { http_response_code(403); exit('https only'); }

$ALLOWED_IPS = ['217.127.199.171', '40.115.11.160']; // Oct8ne bot 2026-05-07
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $ALLOWED_IPS, true)) {
    http_response_code(403); exit('forbidden');
}

require_once '/home/francobordo/api_auth.php';
$_tokens = fb_api_load_tokens('/home/francobordo/.api-tokens');
if (empty($_tokens)) { http_response_code(500); exit('config'); }
$_authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
$_apiConsumer = fb_api_authorize($_authHeader, $_tokens);
if ($_apiConsumer === null) { http_response_code(401); exit('unauthorized'); }

require '/home/francobordo/public_html/includes/configure.php';
$pdo = new PDO(
    'mysql:host=' . DB_SERVER . ';dbname=' . DB_DATABASE . ';charset=utf8mb4',
    DB_SERVER_USERNAME, DB_SERVER_PASSWORD,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

header('Content-Type: application/json; charset=utf-8');

// Mapea stock crudo (con la convencion francobordo de negativos) a availability/stock_status/delivery_estimate.
function fb_stock_status(int $stock, int $products_status): array {
    $delivery = '';
    if ($products_status != 1) {
        return ['out_of_stock', 'discontinued', $delivery];
    }
    if ($stock <= -900) {
        return ['out_of_stock', 'discontinued', 'Agotado de momento'];
    }
    if ($stock == -800) {
        return ['in_stock', 'long_lead', 'Producto bajo pedido, el plazo puede ser superior a 30 días'];
    }
    if ($stock <= 0) {
        return ['in_stock', 'supplier_order', 'Plazo de entrega de 7 a 10 días laborables'];
    }
    if ($stock <= 3) {
        return ['low_stock', 'low', $delivery];
    }
    return ['in_stock', 'available', $delivery];
}

try {
    $rawIds = $_GET['products_ids'] ?? '';
    $rawSkus = $_GET['skus'] ?? '';

    if ($rawIds === '' && $rawSkus === '') {
        http_response_code(400);
        echo json_encode(['error' => 'use ?products_ids=12,34,56 and/or ?skus=A341151,B22330']);
        exit;
    }

    // products_ids -> ids
    $ids = [];
    if ($rawIds !== '') {
        foreach (explode(',', $rawIds) as $x) {
            $i = (int) trim($x);
            if ($i > 0) $ids[$i] = true;
        }
    }

    // skus -> resolver a products_id via products_model
    $skusNotFound = [];
    if ($rawSkus !== '') {
        $skuList = [];
        foreach (explode(',', $rawSkus) as $x) {
            $s = trim($x);
            if ($s !== '') $skuList[$s] = true;
        }
        $skuList = array_keys($skuList);
        if ($skuList) {
            $skuPh = implode(',', array_fill(0, count($skuList), '?'));
            $stmt = $pdo->prepare("SELECT products_id, products_model
                                   FROM products
                                   WHERE products_model IN ($skuPh)");
            $stmt->execute($skuList);
            $resolved = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $resolved[$r['products_model']] = (int)$r['products_id'];
            }
            foreach ($skuList as $s) {
                if (isset($resolved[$s])) {
                    $ids[$resolved[$s]] = true;
                } else {
                    $skusNotFound[] = $s;
                }
            }
        }
    }

    $ids = array_keys($ids);

    if (empty($ids) && empty($skusNotFound)) {
        http_response_code(400);
        echo json_encode(['error' => 'no valid ids or skus']);
        exit;
    }

    if (count($ids) + count($skusNotFound) > 50) {
        http_response_code(400);
        echo json_encode(['error' => 'max 50 ids/skus per request']);
        exit;
    }

    $includeVariants = !empty($_GET['include_variants']);

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    // 1) Datos basicos
    $stmt = $pdo->prepare("SELECT products_id, products_model, products_quantity, products_price, products_status
                           FROM products WHERE products_id IN ($placeholders)");
    $stmt->execute($ids);
    $byId = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $byId[(int)$r['products_id']] = $r;
    }

    // 2) Suma de variantes (si las hay)
    $stmt = $pdo->prepare("SELECT products_id, SUM(products_stock_quantity) AS variant_stock
                           FROM products_stock WHERE products_id IN ($placeholders)
                           GROUP BY products_id");
    $stmt->execute($ids);
    $variantStock = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $variantStock[(int)$r['products_id']] = (int)$r['variant_stock'];
    }

    // 3) Specials activos
    $stmt = $pdo->prepare("SELECT products_id, specials_new_products_price
                           FROM specials
                           WHERE products_id IN ($placeholders)
                             AND status = 1
                             AND (customers_group_id = 0 OR customers_group_id IS NULL)
                             AND (start_date IS NULL OR start_date <= NOW())
                             AND (expires_date IS NULL OR expires_date = '0000-00-00 00:00:00' OR expires_date >= NOW())");
    $stmt->execute($ids);
    $special = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $special[(int)$r['products_id']] = (float)$r['specials_new_products_price'];
    }

    // 4) Variantes detalladas (solo si include_variants=1)
    $variantsByPid = [];
    if ($includeVariants) {
        $stmt = $pdo->prepare("SELECT products_id, products_stock_id, products_stock_attributes,
                                       products_stock_quantity
                               FROM products_stock
                               WHERE products_id IN ($placeholders)
                               ORDER BY products_id, products_stock_id");
        $stmt->execute($ids);
        $variantRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Recolectar IDs de option/value
        $optionIds = [];
        $valueIds = [];
        foreach ($variantRows as $r) {
            foreach (explode(',', (string)$r['products_stock_attributes']) as $pair) {
                $pair = trim($pair);
                if ($pair === '') continue;
                if (strpos($pair, '-') !== false) {
                    [$opt, $val] = explode('-', $pair, 2);
                    if (ctype_digit($opt) && (int)$opt > 0) $optionIds[(int)$opt] = true;
                    if (ctype_digit($val) && (int)$val > 0) $valueIds[(int)$val] = true;
                } elseif (ctype_digit($pair)) {
                    $valueIds[(int)$pair] = true;
                }
            }
        }

        // Resolver labels en idioma 3 (espanol)
        $optionLabels = [];
        if ($optionIds) {
            $optList = array_keys($optionIds);
            $optPh = implode(',', array_fill(0, count($optList), '?'));
            $stmt = $pdo->prepare("SELECT products_options_id, products_options_name
                                   FROM products_options
                                   WHERE products_options_id IN ($optPh) AND language_id = 3");
            $stmt->execute($optList);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $optionLabels[(int)$r['products_options_id']] = $r['products_options_name'];
            }
        }
        $valueLabels = [];
        if ($valueIds) {
            $valList = array_keys($valueIds);
            $valPh = implode(',', array_fill(0, count($valList), '?'));
            $stmt = $pdo->prepare("SELECT products_options_values_id, products_options_values_name
                                   FROM products_options_values
                                   WHERE products_options_values_id IN ($valPh) AND language_id = 3");
            $stmt->execute($valList);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $valueLabels[(int)$r['products_options_values_id']] = $r['products_options_values_name'];
            }
        }

        foreach ($variantRows as $r) {
            $pid = (int)$r['products_id'];
            $stock = (int)$r['products_stock_quantity'];
            $attrs = (string)$r['products_stock_attributes'];
            $parts = [];
            foreach (explode(',', $attrs) as $pair) {
                $pair = trim($pair);
                if ($pair === '') continue;
                if (strpos($pair, '-') !== false) {
                    [$opt, $val] = explode('-', $pair, 2);
                    $optName = $optionLabels[(int)$opt] ?? "opt{$opt}";
                    $valName = $valueLabels[(int)$val] ?? "val{$val}";
                    $parts[] = "{$optName}: {$valName}";
                } elseif (ctype_digit($pair)) {
                    $valName = $valueLabels[(int)$pair] ?? "val{$pair}";
                    $parts[] = $valName;
                } else {
                    $parts[] = $pair;
                }
            }
            $pStatus = isset($byId[$pid]) ? (int)$byId[$pid]['products_status'] : 1;
            [$vAvail, $vStatus, $vDelivery] = fb_stock_status($stock, $pStatus);
            $variantsByPid[$pid][] = [
                'attributes' => $attrs,
                'label' => implode(' / ', $parts),
                'stock' => max($stock, 0),
                'availability' => $vAvail,
                'stock_status' => $vStatus,
                'delivery_estimate' => $vDelivery,
            ];
        }
    }

    // 5) Componer respuesta
    $items = [];
    foreach ($ids as $id) {
        if (!isset($byId[$id])) {
            $items[] = [
                'sku' => '',
                'products_id' => $id,
                'availability' => 'out_of_stock',
                'stock_status' => 'not_found',
                'delivery_estimate' => '',
                'reason' => 'not_found',
            ];
            continue;
        }
        $row = $byId[$id];
        $simpleStock = (int)$row['products_quantity'];
        $varStock = $variantStock[$id] ?? null;
        $stock = $varStock !== null ? $varStock : $simpleStock;

        [$availability, $stock_status, $delivery_estimate] = fb_stock_status($stock, (int)$row['products_status']);

        $price = $special[$id] ?? (float)$row['products_price'];

        $entry = [
            'sku' => (string)($row['products_model'] ?? $id),
            'products_id' => $id,
            'products_model' => $row['products_model'],
            'stock' => max($stock, 0),
            'availability' => $availability,
            'stock_status' => $stock_status,
            'delivery_estimate' => $delivery_estimate,
            'price' => round($price, 2),
            'currency' => 'EUR',
            'has_special' => isset($special[$id]),
            'products_status' => (int)$row['products_status'],
        ];
        if ($includeVariants) {
            $entry['variants'] = $variantsByPid[$id] ?? [];
        }
        $items[] = $entry;
    }
    foreach ($skusNotFound as $s) {
        $items[] = [
            'sku' => $s,
            'products_id' => null,
            'availability' => 'out_of_stock',
            'stock_status' => 'not_found',
            'delivery_estimate' => '',
            'reason' => 'sku_not_found',
        ];
    }
    echo json_encode(['count' => count($items), 'items' => $items], JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'server error']);
}
