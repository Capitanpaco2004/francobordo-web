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
// MEILI_BASE y MEILI_SEARCH_KEY viven FUERA del docroot y fuera del mirror de git.
// Estuvieron hardcodeadas aquí hasta 2026-09-02 y acabaron publicadas en el repo
// espejo público; la clave se rotó y el transporte pasó a Tailscale.
require_once '/home/francobordo/search_proxy_config.php';
const MAX_BODY       = 65536;   // 64 KB
const CURL_TIMEOUT   = 25;      // solo como red de seguridad; cada llamada fija su tope en ms
const CURL_CONNECT_T = 2;
// Topes por llamada (ms). Sin esto un Meili que acepta y no contesta retiene un
// worker de PHP-FPM 25 s por intento, y el pool del dominio son 15 workers.
const T_STRICT_MS    = 800;
const T_BM25_MS      = 1200;
const T_PLAIN_MS     = 1500;
// Antiabuso: el cliente controlaba limit/offset/attributesToRetrieve, lo que
// convertía el proxy en una API de volcado de catálogo.
const MAX_LIMIT      = 48;
const MAX_OFFSET     = 1000;
const RL_MAX_PER_MIN = 240;     // generoso: el widget busca a cada pulsación
const RL_DIR         = '/home/francobordo/_search/logs/rl';

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
// multi-search y facet-search RETIRADOS 2026-09-02: el widget no los usa (una sola
// URL con endpoint=search) y por ellos se escapaban las reglas de negocio —
// multi-search ignoraba la exclusión de marca y facet-search devolvía el censo
// completo de marcas con sus recuentos.
$ENDPOINTS = [
    'search' => '/indexes/' . $INDEX_NAME . '/search',
];
if (!isset($ENDPOINTS[$endpoint])) {
    http_response_code(400);
    echo json_encode(['error' => 'unknown endpoint', 'allowed' => array_keys($ENDPOINTS)]);
    exit;
}

// --- Rate limit por IP (cubo por minuto en fichero; falla ABIERTO siempre) ---
// Sin APCu en este servidor. Es barato: un fichero pequeño por IP y minuto.
function fb_rate_limited(): bool {
    try {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        if ($ip === '') return false;
        $ip = trim(explode(',', $ip)[0]);
        if (!is_dir(RL_DIR)) { @mkdir(RL_DIR, 0755, true); }
        $f = RL_DIR . '/' . substr(md5($ip), 0, 16) . '-' . date('YmdHi');
        $n = (int) @file_get_contents($f);
        if ($n >= RL_MAX_PER_MIN) return true;
        @file_put_contents($f, (string)($n + 1), LOCK_EX);
        // GC oportunista: borra cubos de minutos pasados
        if (mt_rand(0, 199) === 0) {
            foreach (@glob(RL_DIR . '/*') ?: [] as $old) {
                if (@filemtime($old) < time() - 180) @unlink($old);
            }
        }
        return false;
    } catch (Throwable $e) {
        return false;   // nunca bloquear por un fallo del limitador
    }
}
if (fb_rate_limited()) {
    http_response_code(429);
    header('Retry-After: 60');
    echo json_encode(['error' => 'too many requests']);
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
// $timeoutMs: si se pasa, fija un timeout total en milisegundos (para acotar el
// intento híbrido que depende del embedder externo). Si es null, usa CURL_TIMEOUT.
function fb_meili_post(string $url, string $payload, ?int $timeoutMs = null): array {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . MEILI_SEARCH_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_CONNECTTIMEOUT => CURL_CONNECT_T,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_FAILONERROR    => false,
    ];
    if ($timeoutMs !== null) {
        $opts[CURLOPT_TIMEOUT_MS] = $timeoutMs;
    } else {
        $opts[CURLOPT_TIMEOUT] = CURL_TIMEOUT;
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    // sin curl_close(): deprecado desde PHP 8.5 y sin efecto desde 8.0
    return [$resp, $code, $err];
}

// --- Resiliencia del embedder (búsqueda híbrida) ---
// El componente semántico depende de un servicio de embeddings BGE-M3 en GPU
// (.112). Cuando ese servicio se satura/cae, Meili BLOQUEA la búsqueda esperándolo
// (4-30 s). Para que la búsqueda NUNCA dependa de él: intentamos el híbrido con un
// timeout corto y, si falla, caemos a BM25 (instantáneo). Un circuit-breaker en
// fichero evita pagar el timeout en cada pulsación mientras el embedder esté caído.
const HYBRID_TIMEOUT_MS    = 1800;   // tope del intento híbrido
const EMBED_BREAKER_FILE   = '/home/francobordo/_search/logs/.embedder_slow';
const EMBED_BREAKER_COOLDOWN = 30;   // s que evitamos el híbrido tras un fallo
function fb_embedder_tripped(): bool {
    $t = @filemtime(EMBED_BREAKER_FILE);
    return $t !== false && (time() - $t) < EMBED_BREAKER_COOLDOWN;
}
function fb_embedder_trip(): void  { @touch(EMBED_BREAKER_FILE); }
function fb_embedder_reset(): void { if (@filemtime(EMBED_BREAKER_FILE) !== false) @unlink(EMBED_BREAKER_FILE); }

// --- Endurecido del cuerpo: topes y campos devueltos ---
// El cliente controlaba limit/offset/attributesToRetrieve: 1.000 productos con
// precio, EAN, stock y ref_prov en una sola llamada. Además el widget no acotaba
// campos y cada resultado viajaba con description/categorías/variantes (139 MB
// servidos en un día). $withRefProv sólo se activa en la rama estricta por código,
// que es la única que necesita la referencia de proveedor.
function fb_harden_body(array $b, bool $withRefProv = false): array {
    $b['limit']  = min(max((int)($b['limit']  ?? 24), 1), MAX_LIMIT);
    $b['offset'] = min(max((int)($b['offset'] ?? 0), 0), MAX_OFFSET);
    $allowed = [
        'id', 'pid', 'aid', 'title', 'brand', 'price', 'image', 'link',
        'in_stock', 'availability', 'stock_qty', 'ean', 'mpn',
    ];
    if ($withRefProv) $allowed[] = 'ref_prov';
    $req = $b['attributesToRetrieve'] ?? null;
    $b['attributesToRetrieve'] = (is_array($req) && $req && !in_array('*', $req, true))
        ? array_values(array_intersect($req, $allowed))
        : $allowed;
    if (!$b['attributesToRetrieve']) $b['attributesToRetrieve'] = $allowed;
    return $b;
}

// --- Exclusión de marca al buscar su propio nombre ---
// Cuando el cliente busca el nombre de ciertas marcas, NO queremos mostrar los
// productos de ESA marca (p.ej. al buscar "seaflo" se ocultan los de brand=Seaflo,
// pero sí salen productos de otras marcas que mencionan Seaflo en el título).
// Mapa: token de búsqueda normalizado => valor EXACTO del campo brand en Meili.
const HIDE_BRAND_WHEN_SEARCHED = [
    'seaflo' => 'Seaflo',
];
function fb_inject_brand_exclusions(array $bodyJson): array {
    $q = mb_strtolower(trim((string)($bodyJson['q'] ?? '')), 'UTF-8');
    $q = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $q);
    $tokens = preg_split('/[^a-z0-9]+/', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $extra = [];
    foreach ($tokens as $tok) {
        foreach (HIDE_BRAND_WHEN_SEARCHED as $needle => $brandName) {
            // Match por PREFIJO en ambos sentidos, desde 4 caracteres: con match
            // exacto se escapaban "seafl" (mientras se teclea, que es donde el
            // cliente mira) y "seaflow"/"seaflos". El legacy search.php ya usaba
            // prefijo; esto alinea el proxy con él, no al revés.
            $hit = (strlen($tok) >= 4 && (str_starts_with($tok, $needle) || str_starts_with($needle, $tok)))
                || $tok === $needle;
            if ($hit) {
                $brand = str_replace(['\\', '"'], ['\\\\', '\\"'], $brandName);
                $extra['brand != "' . $brand . '"'] = true;   // clave = dedup
            }
        }
    }
    if (!$extra) return $bodyJson;
    $extra = array_keys($extra);

    $existing = $bodyJson['filter'] ?? null;
    if (is_array($existing)) {
        // En Meili, los elementos de un array de filtros van AND-eados.
        $bodyJson['filter'] = array_merge($existing, $extra);
    } elseif (is_string($existing) && trim($existing) !== '') {
        $bodyJson['filter'] = '(' . $existing . ') AND ' . implode(' AND ', $extra);
    } else {
        $bodyJson['filter'] = implode(' AND ', $extra);
    }
    return $bodyJson;
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
        $esc = str_replace(['\\', '"'], ['\\\\', '\\"'], $rawQ);
        $clauses = [];
        if (preg_match('/^\d+$/', $rawQ)) {
            $clauses[] = 'pid = ' . $rawQ;
            // EAN: 12-14 dígitos. Sin esto un código inexistente caía a la búsqueda
            // difusa y devolvía OTRO producto a 1-2 erratas de distancia, que en
            // mostrador y almacén es un error caro.
            if (preg_match('/^\d{12,14}$/', $rawQ)) {
                $clauses[] = 'ean = "' . $esc . '"';
            }
        }
        $clauses[] = 'ref_prov = "' . $esc . '"';
        $strict = $bodyJson;
        $strict['q'] = '';
        // COMBINAR con los filtros de faceta, no reemplazarlos: buscar un código
        // con la marca Osculati marcada devolvía productos Lalizas.
        $prev = $bodyJson['filter'] ?? null;
        $codeFilter = '(' . implode(' OR ', $clauses) . ')';
        if (is_array($prev) && $prev) {
            $strict['filter'] = array_merge($prev, [$codeFilter]);
        } elseif (is_string($prev) && trim($prev) !== '') {
            $strict['filter'] = '(' . $prev . ') AND ' . $codeFilter;
        } else {
            $strict['filter'] = $codeFilter;
        }
        unset($strict['hybrid']);                        // exacto: sin semántico
        $strict['showRankingScore'] = true;
        $strict = fb_harden_body($strict, true);         // aquí sí se devuelve ref_prov
        [$sResp, $sCode, $sErr] = fb_meili_post($url, json_encode($strict), T_STRICT_MS);
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
        $bodyJson = fb_inject_brand_exclusions($bodyJson);
        $bodyJson['showRankingScore'] = true;
        $bodyJson = fb_harden_body($bodyJson);   // topes + campos devueltos
        $body = json_encode($bodyJson);

        if (isset($bodyJson['hybrid'])) {
            // Búsqueda híbrida: depende del embedder externo. Intento acotado + fallback.
            $tryHybrid = !fb_embedder_tripped();   // breaker: si cayó hace poco, ni lo intentamos
            if ($tryHybrid) {
                [$resp, $code, $err] = fb_meili_post($url, $body, HYBRID_TIMEOUT_MS);
                // El breaker solo debe saltar por caída/lentitud real. Antes lo
                // disparaba CUALQUIER no-200: un 400 por cuerpo malformado apagaba
                // el semántico 30 s para todos, y era trivial de provocar desde fuera.
                if ($resp === false || $code >= 500) {
                    fb_embedder_trip();            // embedder lento/caído
                    $resp = null;                  // fuerza fallback BM25 abajo
                } elseif ($code !== 200) {
                    $resp = null;                  // error del cliente: reintenta sin semántico
                } else {
                    fb_embedder_reset();           // recuperado
                }
            }
            if ($resp === null) {
                // Fallback BM25: misma query sin la parte semántica -> instantáneo.
                $bm25 = $bodyJson;
                unset($bm25['hybrid']);
                [$resp, $code, $err] = fb_meili_post($url, json_encode($bm25), T_BM25_MS);
            }
        } else {
            [$resp, $code, $err] = fb_meili_post($url, $body, T_PLAIN_MS);
        }
    } else {
        [$resp, $code, $err] = fb_meili_post($url, $body);
    }
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
            // El log es TSV y lo consume el aprendiz de sinónimos: si $q lleva
            // tabuladores o saltos de línea se pueden forjar filas enteras.
            $qSafe  = strtr($q,  ["\t" => ' ', "\n" => ' ', "\r" => ' ']);
            $qnSafe = strtr((string)$qn, ["\t" => ' ', "\n" => ' ', "\r" => ' ']);
            $line = sprintf("%s\t%d\t%d\t%.4f\t%s\t%s\n",
                date('c'), $n, $took, $top_score, $qnSafe, $qSafe);
            @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        }
    } catch (Throwable $e) {
        // Silencioso: el logging no debe romper el proxy
    }
}
