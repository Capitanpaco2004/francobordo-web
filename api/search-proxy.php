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

/**
 * Note: This file may contain artifacts of previous malicious infection.
 * However, the dangerous code has been removed, and the file is now safe to use.
 */

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
const HYBRID_TIMEOUT_MS    = 1800;   // tope del intento híbrido
const EMBED_BREAKER_FILE   = '/home/francobordo/_search/logs/.embedder_slow';
const EMBED_BREAKER_COOLDOWN = 30;   // s que evitamos el híbrido tras un fallo
function fb_embedder_reset(): void { if (@filemtime(EMBED_BREAKER_FILE) !== false) @unlink(EMBED_BREAKER_FILE); }

// --- Exclusión de marca al buscar su propio nombre ---
// Cuando el cliente busca el nombre de ciertas marcas, NO queremos mostrar los
// productos de ESA marca (p.ej. al buscar "seaflo" se ocultan los de brand=Seaflo,
// pero sí salen productos de otras marcas que mencionan Seaflo en el título).
// Mapa: token de búsqueda normalizado => valor EXACTO del campo brand en Meili.
const HIDE_BRAND_WHEN_SEARCHED = [
    'seaflo' => 'Seaflo',
];
$resp = null;
