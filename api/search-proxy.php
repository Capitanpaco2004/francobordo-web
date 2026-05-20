<?php
/**
 * /api/search-proxy.php
 *
 * Proxy LIGERO entre el navegador del cliente y Meilisearch en .112.
 *
 * Diseñado para ser seguro bajo concurrencia (a diferencia del embed-proxy
 * que tumbó PHP-FPM): timeouts cortos, whitelist de paths, sin cURL retries.
 *
 * Path: /home/francobordo/public_html/api/search-proxy.php
 * URL:  https://www.francobordo.com/api/search-proxy.php?endpoint=search
 *
 * Body: el cuerpo JSON que enviarías a Meili tal cual.
 *
 * Endpoints permitidos (whitelist):
 *  - search          -> POST /indexes/products/search
 *  - multi-search    -> POST /multi-search
 *  - facet-search    -> POST /indexes/products/facet-search
 */
declare(strict_types=1);

// --- Config (server-side; nunca expuesto al cliente) ---
const MEILI_BASE     = 'http://217.127.199.171:28700';
const MEILI_SEARCH_KEY = 'e86c194b8e7077d7524edc11e596b9eac5e9beba32d01639c29e366dd47ccd0a';  // search-only, patrón products* (ES+EN)
const MAX_BODY       = 65536;   // 64 KB
const CURL_TIMEOUT   = 25;
const CURL_CONNECT_T = 2;

// Índice según idioma (whitelist — no permitimos índices arbitrarios)
$INDEX_BY_LANG = [
    'es' => 'products',
    'en' => 'products_en',
];
$lang  = $_GET['lang'] ?? 'es';
$INDEX_NAME = $INDEX_BY_LANG[$lang] ?? 'products';   // fallback seguro a ES

// --- CORS headers (igual para errores y éxito) ---
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (preg_match('#^https://(www\.)?francobordo\.com$#', $origin)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 600');
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// --- Preflight OPTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- Solo POST ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method not allowed']);
    exit;
}

// --- Endpoint whitelist ---
$endpoint = $_GET['endpoint'] ?? 'search';
$ENDPOINTS = [
    'search'       => '/indexes/' . $INDEX_NAME . '/search',
    'multi-search' => '/multi-search',
    'facet-search' => '/indexes/' . $INDEX_NAME . '/facet-search',
];
if (!isset($ENDPOINTS[$endpoint])) {
    http_response_code(400);
    echo json_encode(['error' => 'unknown endpoint', 'allowed' => array_keys($ENDPOINTS)]);
    exit;
}

// --- Body cap ---
$body = file_get_contents('php://input', false, null, 0, MAX_BODY + 1);
if (strlen($body) > MAX_BODY) {
    http_response_code(413);
    echo json_encode(['error' => 'payload too large']);
    exit;
}

// JSON sanity check + inyectar showRankingScore para que el logger pueda
// distinguir matches fuertes (BM25) de matches débiles (puro semántico).
$bodyJson = null;
if ($body !== '') {
    $bodyJson = json_decode($body, true);
    if ($bodyJson === null && json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid json']);
        exit;
    }
}
// --- Helper: POST a Meili. Devuelve [body|false, http_code, curl_error] ---
function fb_meili_post(string $url, string $payload): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . MEILI_SEARCH_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => CURL_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => CURL_CONNECT_T,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_FAILONERROR    => false,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    // sin curl_close(): deprecado desde PHP 8.5 y sin efecto desde 8.0
    return [$resp, $code, $err];
}

$url = MEILI_BASE . $ENDPOINTS[$endpoint];
$resp = null;
$code = 0;
$err  = '';

// --- Búsqueda ESTRICTA por id de producto o referencia de proveedor ---
// Si la query es "code-like" (un solo token con al menos un dígito), probamos
// primero un match EXACTO por pid o ref_prov. Si lo hay, devolvemos SOLO eso:
//   - pid       -> el producto y todas sus variantes (todas comparten pid)
//   - ref_prov  -> el producto o la propiedad concreta con esa referencia
// Si no hay match exacto, se cae a la búsqueda normal (híbrida) de abajo.
if ($endpoint === 'search' && is_array($bodyJson)) {
    $rawQ = trim((string)($bodyJson['q'] ?? ''));
    if ($rawQ !== ''
        && preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/\-]{1,31}$/', $rawQ)
        && preg_match('/\d/', $rawQ)) {
        $clauses = [];
        if (preg_match('/^\d+$/', $rawQ)) {
            $clauses[] = 'pid = ' . $rawQ;
        }
        $clauses[] = 'ref_prov = "' . str_replace(['\\', '"'], ['\\\\', '\\"'], $rawQ) . '"';
        $strict = $bodyJson;
        $strict['q'] = '';
        $strict['filter'] = implode(' OR ', $clauses);   // reemplaza filtros de faceta
        unset($strict['hybrid']);                        // exacto: sin semántico
        $strict['showRankingScore'] = true;
        [$sResp, $sCode, $sErr] = fb_meili_post($url, json_encode($strict));
        if ($sResp !== false && $sCode === 200) {
            $sJson = json_decode($sResp, true);
            if (is_array($sJson) && !empty($sJson['hits'])) {
                $resp = $sResp; $code = $sCode; $err = $sErr;
            }
        }
    }
}

// --- Forward normal a Meili (si la estricta no aplicó o no tuvo hits) ---
if ($resp === null) {
    if ($endpoint === 'search' && is_array($bodyJson)) {
        $bodyJson['showRankingScore'] = true;
        $body = json_encode($bodyJson);
    }
    [$resp, $code, $err] = fb_meili_post($url, $body);
}

if ($resp === false) {
    http_response_code(503);
    echo json_encode([
        'error' => 'backend unreachable',
        'detail' => $err,
    ]);
    exit;
}

http_response_code($code ?: 200);
echo $resp;

// --- Logging ASÍNCRONO (fire-and-forget) tras devolver la respuesta ---
// Sólo loguea endpoint=search (no multi-search interno, no facet-search auxiliar)
// El cliente ya recibió el body; este append a fichero es muy rápido y no bloquea.
if ($endpoint === 'search' && function_exists('fastcgi_finish_request')) {
    @fastcgi_finish_request();   // libera al cliente antes del log
}
// El logging para el aprendiz de sinónimos sólo aplica al índice ES por ahora
// (el synonym_learner.py sólo conoce el índice 'products'). Los clicks SÍ se
// trackean en ambos idiomas porque popularity_score es por pid (idioma-agnóstico).
if ($endpoint === 'search' && $lang === 'es') {
    try {
        $reqJson = json_decode($body, true);
        $q = is_array($reqJson) ? trim((string)($reqJson['q'] ?? '')) : '';
        // Filtro de prefijos de tecleo: el widget hace search-as-you-type,
        // así que escribir cabo genera c,ca,cab,cabo y los prefijos
        // cortos contaminan las métricas. Mínimo 3 chars para considerar real.
        if (mb_strlen($q) >= 3 && mb_strlen($q) <= 255) {
            $respJson = json_decode($resp, true);
            $n = is_array($respJson) ? (int)($respJson['estimatedTotalHits'] ?? 0) : 0;
            $took = is_array($respJson) ? (int)($respJson['processingTimeMs'] ?? 0) : 0;
            // Top hit score: indica si el match es "fuerte" (>0.7) o débil (<0.5).
            // Mejor señal que n_results porque el modo híbrido siempre infla con semánticos.
            $top_score = 0.0;
            if (is_array($respJson) && !empty($respJson['hits'][0])) {
                $top_score = (float)($respJson['hits'][0]['_rankingScore'] ?? 0.0);
            }
            // q_norm: minúsculas + collapse de espacios + sin acentos
            $qn = mb_strtolower($q, 'UTF-8');
            $qn = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $qn);
            $qn = preg_replace('/\s+/', ' ', trim($qn));
            $logDir = '/home/francobordo/_search/logs';
            @mkdir($logDir, 0755, true);
            $logFile = $logDir . '/search_events_' . date('Y-m-d') . '.log';
            $line = sprintf("%s\t%d\t%d\t%.4f\t%s\t%s\n",
                date('c'), $n, $took, $top_score, $qn, $q);
            @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        }
    } catch (Throwable $e) {
        // Silencioso: el logging no debe romper el proxy
    }
}
