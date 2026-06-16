<?php
/**
 * mcp.php — Servidor MCP (Model Context Protocol) de catalogo Francobordo. (anadido 2026-06-15)
 * Solo-lectura, SIN auth (catalogo publico). Streamable HTTP, JSON-RPC 2.0, stateless (responde
 * application/json, sin SSE). Envuelve los endpoints del RAG commerce-intelligence por Tailscale.
 * Tools: search_products, get_product, check_stock, recommend_products, get_policy.
 * URL publica: https://www.francobordo.com/mcp  (conector de Claude / App de ChatGPT cuando abra UE).
 */

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);

const RAG_BASE = 'http://100.82.226.46:8002';
const RAG_TIMEOUT = 45;
const MCP_LOG = '/home/francobordo/logs/mcp.log';
const SERVER_NAME = 'francobordo-catalog';
const SERVER_VERSION = '1.0.0';
const SUPPORTED_PROTOCOLS = ['2025-06-18', '2025-03-26', '2024-11-05'];
const DEFAULT_PROTOCOL = '2025-06-18';

function mcp_log($dir, $data) {
    $line = json_encode(['t' => date('c'), 'dir' => $dir, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    @file_put_contents(MCP_LOG, substr((string)$line, 0, 8000) . "\n", FILE_APPEND);
}

function rag_call($path, $payload) {
    $ch = curl_init(RAG_BASE . $path);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => RAG_TIMEOUT,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    if ($resp === false || $code < 200 || $code >= 300) {
        return [null, "RAG HTTP $code $err"];
    }
    $data = json_decode($resp, true);
    if (!is_array($data)) return [null, 'RAG respuesta no-JSON'];
    return [$data, null];
}

function fmt_product($p) {
    if (!is_array($p)) return '';
    $l = [];
    $l[] = '• ' . ($p['name'] ?? '?');
    if (!empty($p['brand'])) $l[] = '  Marca: ' . $p['brand'];
    if (isset($p['price'])) $l[] = '  Precio: ' . $p['price'] . ' ' . ($p['currency'] ?? 'EUR') . (!empty($p['availability']) ? '  (' . $p['availability'] . ')' : '');
    if (!empty($p['sku'])) $l[] = '  Ref: ' . $p['sku'];
    if (!empty($p['delivery_estimate'])) $l[] = '  Plazo: ' . $p['delivery_estimate'];
    if (!empty($p['url'])) $l[] = '  Enlace: ' . $p['url'];
    if (!empty($p['description'])) $l[] = '  ' . mb_substr(trim((string)$p['description']), 0, 280);
    return implode("\n", $l);
}

function tool_text($text) { return ['content' => [['type' => 'text', 'text' => (string)$text]]]; }
function tool_error($msg) { return ['content' => [['type' => 'text', 'text' => 'Error: ' . $msg]], 'isError' => true]; }

function tool_search_products($a) {
    $query = trim((string)($a['query'] ?? ''));
    if ($query === '') return tool_error("Falta 'query'.");
    $payload = ['query' => $query, 'limit' => min(20, max(1, (int)($a['limit'] ?? 5)))];
    $filters = [];
    if (!empty($a['brand'])) $filters['brand'] = (string)$a['brand'];
    if (isset($a['price_max'])) $filters['price_max'] = (float)$a['price_max'];
    if ($filters) $payload['filters'] = $filters;
    [$d, $e] = rag_call('/search/products', $payload);
    if ($e) return tool_error($e);
    $res = $d['results'] ?? [];
    if (!$res) return tool_text("No se encontraron productos para: $query");
    return tool_text("Productos para \"$query\" (" . count($res) . "):\n\n" . implode("\n\n", array_map('fmt_product', $res)));
}

function tool_get_product($a) {
    $id = $a['product_id'] ?? $a['sku'] ?? null;
    if (!$id) return tool_error("Falta 'product_id'.");
    [$d, $e] = rag_call('/products/context', ['product_ids' => [(string)$id], 'include_stock' => true, 'include_recommendations' => false]);
    if ($e) return tool_error($e);
    $items = $d['items'] ?? [];
    if (!$items) return tool_text("Producto no encontrado: $id");
    $out = [];
    foreach ($items as $it) {
        $block = fmt_product($it['product'] ?? $it);
        $st = $it['stock'] ?? null;
        if (is_array($st)) {
            $b = [];
            if (!empty($st['availability'])) $b[] = $st['availability'];
            if (isset($st['quantity'])) $b[] = 'stock ' . $st['quantity'];
            if (!empty($st['delivery_estimate'])) $b[] = $st['delivery_estimate'];
            if ($b) $block .= "\n  Disponibilidad: " . implode(' · ', $b);
        }
        $out[] = $block;
    }
    return tool_text(implode("\n\n", $out));
}

function tool_check_stock($a) {
    $skus = $a['skus'] ?? $a['refs'] ?? null;
    if (is_string($skus)) $skus = array_map('trim', explode(',', $skus));
    if (!is_array($skus) || !$skus) return tool_error("Falta 'skus'.");
    $skus = array_slice(array_map('strval', $skus), 0, 50);
    [$d, $e] = rag_call('/stock/check', ['skus' => $skus]);
    if ($e) return tool_error($e);
    $items = $d['items'] ?? [];
    if (!$items) return tool_text('Sin datos de stock.');
    $out = [];
    foreach ($items as $it) {
        $line = '• Ref ' . ($it['sku'] ?? '?') . ': ' . ($it['availability'] ?? '?');
        if (isset($it['quantity'])) $line .= ' (' . $it['quantity'] . ')';
        if (!empty($it['delivery_estimate'])) $line .= ' — ' . $it['delivery_estimate'];
        $vars = $it['variants'] ?? null;
        if (is_array($vars) && $vars) {
            $line .= "\n  Variantes (" . count($vars) . '):';
            $n = 0;
            foreach ($vars as $v) {
                if ($n++ >= 8) { $line .= "\n    …(+" . (count($vars) - 8) . ')'; break; }
                $line .= "\n    - " . ($v['label'] ?? ($v['attributes'] ?? '?')) . ': ' . ($v['availability'] ?? '?') . (isset($v['quantity']) ? ' (' . $v['quantity'] . ')' : '');
            }
        }
        $out[] = $line;
    }
    return tool_text(implode("\n", $out));
}

function tool_recommend_products($a) {
    $ids = $a['product_ids'] ?? $a['product_id'] ?? null;
    if (is_string($ids) || is_int($ids)) $ids = [$ids];
    if (!is_array($ids) || !$ids) return tool_error("Falta 'product_ids'.");
    [$d, $e] = rag_call('/recommend/products', ['positive_product_ids' => array_map('strval', $ids), 'limit' => min(10, max(1, (int)($a['limit'] ?? 5)))]);
    if ($e) return tool_error($e);
    $res = $d['results'] ?? [];
    if (!$res) return tool_text('Sin recomendaciones.');
    return tool_text("Productos similares:\n\n" . implode("\n\n", array_map('fmt_product', $res)));
}

function tool_get_policy($a) {
    $q = trim((string)($a['question'] ?? $a['query'] ?? ''));
    if ($q === '') return tool_error("Falta 'question'.");
    [$d, $e] = rag_call('/knowledge/retrieve', ['query' => $q, 'limit' => min(8, max(1, (int)($a['limit'] ?? 4)))]);
    if ($e) return tool_error($e);
    $ans = trim((string)($d['suggested_answer'] ?? ''));
    $chunks = $d['chunks'] ?? [];
    if ($ans === '' && !$chunks) return tool_text("No encontre informacion para: $q");
    if ($ans === '') $ans = implode("\n\n---\n\n", array_map(function ($c) { return trim((string)($c['text'] ?? '')); }, $chunks));
    $srcs = [];
    foreach (($d['citations'] ?? []) as $c) {
        $s = $c['source_path'] ?? ($c['ref'] ?? null);
        // Solo exponer fuentes PUBLICAS (http); nunca rutas internas del RAG (C:/AGENTES/...).
        if (is_string($s) && preg_match('#^https?://#i', $s)) $srcs[$s] = true;
    }
    if ($srcs) $ans .= "\n\nMas info: " . implode(', ', array_keys($srcs));
    return tool_text($ans);
}

function dispatch_tool($name, $args) {
    if (!is_array($args)) $args = [];
    switch ($name) {
        case 'search_products': return tool_search_products($args);
        case 'get_product': return tool_get_product($args);
        case 'check_stock': return tool_check_stock($args);
        case 'recommend_products': return tool_recommend_products($args);
        case 'get_policy': return tool_get_policy($args);
        default: return null;
    }
}

function tools_definitions() {
    $ro = ['readOnlyHint' => true, 'openWorldHint' => true];
    return [
        ['name' => 'search_products', 'annotations' => $ro,
         'description' => 'Busca productos nauticos en el catalogo de Francobordo por lenguaje natural. Devuelve nombre, precio, disponibilidad y el ENLACE de compra de cada producto. Usalo cuando el usuario pregunte si vendeis algo o quiera encontrar un producto.',
         'inputSchema' => ['type' => 'object', 'required' => ['query'], 'properties' => [
             'query' => ['type' => 'string', 'description' => 'Que busca el usuario, ej. "bomba de achique 12V" o "chaleco salvavidas 150N"'],
             'limit' => ['type' => 'integer', 'description' => 'Maximo de resultados (1-20, por defecto 5)'],
             'brand' => ['type' => 'string', 'description' => 'Filtrar por marca (opcional)'],
             'price_max' => ['type' => 'number', 'description' => 'Precio maximo en EUR (opcional)'],
         ]]],
        ['name' => 'get_product', 'annotations' => $ro,
         'description' => 'Ficha detallada de un producto (descripcion, specs, stock, enlace) por su product_id o referencia, normalmente obtenido de search_products.',
         'inputSchema' => ['type' => 'object', 'required' => ['product_id'], 'properties' => [
             'product_id' => ['type' => 'string', 'description' => 'ID del producto (ej. "FB-151") o referencia/SKU'],
         ]]],
        ['name' => 'check_stock', 'annotations' => $ro,
         'description' => 'Stock, disponibilidad y precio actual (con ofertas) de una o varias referencias/SKU, con desglose por variante.',
         'inputSchema' => ['type' => 'object', 'required' => ['skus'], 'properties' => [
             'skus' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Referencias/SKU a consultar'],
         ]]],
        ['name' => 'recommend_products', 'annotations' => $ro,
         'description' => 'Recomienda productos similares/alternativos a partir de uno o varios product_id semilla.',
         'inputSchema' => ['type' => 'object', 'required' => ['product_ids'], 'properties' => [
             'product_ids' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'product_id(s) de referencia'],
             'limit' => ['type' => 'integer', 'description' => 'Maximo de recomendaciones (1-10)'],
         ]]],
        ['name' => 'get_policy', 'annotations' => $ro,
         'description' => 'Responde sobre politicas de Francobordo: envios, plazos, devoluciones, garantia, formas de pago.',
         'inputSchema' => ['type' => 'object', 'required' => ['question'], 'properties' => [
             'question' => ['type' => 'string', 'description' => 'Pregunta sobre envio, devolucion, garantia, pago, etc.'],
         ]]],
    ];
}

function send_json($obj, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function rpc_result($id, $r) { return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $r]; }
function rpc_error($id, $code, $m) { return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $m]]; }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') { http_response_code(204); exit; }
if ($method !== 'POST') {
    header('Allow: POST');
    send_json(['jsonrpc' => '2.0', 'error' => ['code' => -32000, 'message' => 'Servidor MCP stateless: usa POST (sin SSE).']], 405);
}

$raw = file_get_contents('php://input');
$req = json_decode($raw, true);
mcp_log('in', $req);
if (!is_array($req)) send_json(rpc_error(null, -32700, 'Parse error'), 200);

$isBatch = array_keys($req) === range(0, count($req) - 1) && isset($req[0]);
$requests = $isBatch ? $req : [$req];
$responses = [];

foreach ($requests as $r) {
    if (!is_array($r)) continue;
    $id = $r['id'] ?? null;
    $m = $r['method'] ?? '';
    $params = $r['params'] ?? [];
    $isNotification = !array_key_exists('id', $r);

    if ($m === 'initialize') {
        $cp = $params['protocolVersion'] ?? DEFAULT_PROTOCOL;
        $proto = in_array($cp, SUPPORTED_PROTOCOLS, true) ? $cp : DEFAULT_PROTOCOL;
        $responses[] = rpc_result($id, [
            'protocolVersion' => $proto,
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => ['name' => SERVER_NAME, 'version' => SERVER_VERSION],
            'instructions' => 'Catalogo nautico de Francobordo. Usa search_products para encontrar productos (devuelve enlaces de compra), get_product para detalles, check_stock para disponibilidad, get_policy para envios/devoluciones/garantia.',
        ]);
    } elseif ($m === 'notifications/initialized' || $m === 'initialized') {
        // notificacion, sin respuesta
    } elseif ($m === 'ping') {
        $responses[] = rpc_result($id, new stdClass());
    } elseif ($m === 'tools/list') {
        $responses[] = rpc_result($id, ['tools' => tools_definitions()]);
    } elseif ($m === 'tools/call') {
        $res = dispatch_tool($params['name'] ?? '', $params['arguments'] ?? []);
        $responses[] = ($res === null) ? rpc_error($id, -32602, 'Tool desconocida: ' . ($params['name'] ?? '')) : rpc_result($id, $res);
    } elseif ($m === 'resources/list') {
        $responses[] = rpc_result($id, ['resources' => []]);
    } elseif ($m === 'prompts/list') {
        $responses[] = rpc_result($id, ['prompts' => []]);
    } else {
        if (!$isNotification) $responses[] = rpc_error($id, -32601, 'Metodo no soportado: ' . $m);
    }
}

if (!$responses) { http_response_code(202); exit; }
$out = $isBatch ? $responses : $responses[0];
mcp_log('out', $out);
send_json($out);
