<?php
require 'includes/application_top.php';
require_once DIR_FS_DOCUMENT_ROOT . 'includes/vendor/autoload.php';
require_once dirname(__FILE__) . '/includes/mb_reformat_helpers.php';

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);

const MB_DIR        = '/home/francobordo/public_html/import/MB/';
const MB_CACHE_DIR  = '/home/francobordo/public_html/import/MB/cache/';
const MB_WEB_BASE_ES = 'https://marinebusiness.net/wp-json/wc/store/v1/products';
const MB_WEB_BASE_EN = 'https://marinebusiness.net/en/wp-json/wc/store/v1/products';
// Portal B2B inaCatalog — reemplaza la WC API (bloqueada por Cloudflare desde ~2026-06-25).
// Accesible desde el servidor (nginx, sin challenge). Login por Base64 + sesión.
const MB_PORTAL_BASE = 'https://marinebusiness.b2binacatalog.com';
const MB_PORTAL_USER = 'CL1166';
const MB_PORTAL_PASS = 'B82574690';
const MB_PORTAL_UA   = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36';
define('IMG_ABS_DIR',     dirname(dirname(__FILE__)) . '/images/productos/');

const PARENT_CATEGORY_ID = 1743;          // "Marine Business Nuevos" — padre
const PARENT_CATEGORY_NAME_ES = 'Marine Business Nuevos';
const PARENT_CATEGORY_NAME_EN = 'Marine Business New';
const MFG_MARINE_BUSINESS  = 47;          // manufacturers_id existente
const TAX_CLASS_IVA21      = 1;
const LANG_ID_ES           = 3;
const LANG_ID_EN           = 1;
const G1_GROUP_ID          = 1;
const PRODUCT_NAME_MAX     = 96;          // products_description.products_name varchar(255) — sobrado
const NAME_SUFFIX          = ' - Marine Business';
const IMG_HTTP_TIMEOUT     = 15;
const WEB_HTTP_TIMEOUT     = 25;
const IMG_MIN_BYTES        = 4096;
const MAX_SUBIMAGES        = 6;
const ORIGIN_FLAG          = 'marinebusiness';
const EAN_INTERNAL_PREFIX  = 26;
const MB_VAT_RATE          = 0.21;
const PRICE_MULT_NET       = 0.95;
const COST_MULT            = 0.50;
const G1_MULT              = 0.70;
const FALLBACK_SUBCAT      = 'VARIOS';    // para filas sin prefijo claro o aisladas
const SUBCAT_MIN_ROWS      = 2;           // prefijos con menos filas → FALLBACK_SUBCAT

const LLM_URL    = 'http://217.127.199.171:28001/v1/chat/completions';
const LLM_MODEL  = 'qwen36-sakamaki-nvfp4';

const LLM_FORMAT_PROMPT_ES = "Eres un experto en maquetar fichas de producto náuticas/marinas. Recibes una descripción comercial que PUEDE venir en inglés, italiano o mezclada, y la transformas en HTML legible y atractivo EN ESPAÑOL DE ESPAÑA. Si el texto (o cualquier parte) NO está en español, TRADÚCELO al español — traducir al español NO cuenta como inventar ni parafrasear, es OBLIGATORIO. Ej.: \"Break Resistant\"->\"Resistente a roturas\", \"Dishwasher & Microwave Safe\"->\"Apto para lavavajillas y microondas\", \"BPA Free\"->\"Sin BPA\", \"Non slip\"->\"Antideslizante\".\n\nREGLAS OBLIGATORIAS:\n\n1. PÁRRAFO INTRODUCTORIO: el primer <p> es la frase descriptiva más completa del producto. Si el texto introductorio tiene más de 5 frases, parte en varios <p> de 5 frases máximo cada uno.\n\n2. TÍTULOS DE SECCIÓN (si el producto tiene >6 features, agrupa en secciones): cada título es <p><strong>Título</strong></p>. NUNCA uses <h1>..<h6>. Antes de cada título de sección inserta <p>&nbsp;</p> como separador. Ejemplos de títulos: \"Características principales\", \"Materiales\", \"Especificaciones técnicas\", \"Cuidados y mantenimiento\".\n\n3. LISTAS DE FEATURES: cada bullet va en su propio <p>• texto</p>. NUNCA uses <ul> ni <li>. Identifica el concepto clave (1-4 palabras al inicio) y envuélvelo en <strong>, seguido de dos puntos. Ejemplo: <p>• <strong>Melamina 100%:</strong> material ligero e irrompible.</p>. NO inventes texto.\n\n4. SÍMBOLOS PROHIBIDOS: elimina ™ ® © en todo el texto (incluido el de los títulos).\n\n5. PALABRAS EN MAYÚSCULAS: si una palabra está toda en mayúsculas y tiene 4 letras o más, pásala a Title Case (\"TRITAN\" → \"Tritan\", \"POLIESTER\" → \"Poliester\"). Preserva acrónimos cortos (BPA, UV, LED, IP, USB, ABS, PVC, AISI…).\n\n6. PRESERVA enlaces <a href> existentes sin tocarlos.\n\n7. NO resumas, NO parafrasees, NO inventes información: conserva TODO el texto original. Solo añades estructura HTML, secciones, bold, dos puntos y formato.\n\n8. Si el texto de entrada es solo 1-2 frases cortas, devuelve <p>texto</p> sin lista.\n\n9. Etiquetas permitidas: <p>, <strong>, <a>. Prohibidas: <h1>..<h6>, <br>, <div>, <span>, <ul>, <li>, <table>, <ol>.\n\n10. Salida: SOLO el HTML, sin markdown, sin comentarios, sin tres-back-ticks.";

const LLM_FORMAT_PROMPT_EN = "You are an expert in formatting nautical/marine product datasheets. You receive a commercial description in ENGLISH and transform it into clean, readable HTML.\n\nMANDATORY RULES:\n\n1. INTRODUCTORY PARAGRAPH: the first <p> is the most complete descriptive sentence about the product. If introductory text has more than 5 sentences, split into several <p> blocks of max 5 sentences each.\n\n2. SECTION TITLES (if product has >6 features, group into sections): each title is <p><strong>Title</strong></p>. NEVER use <h1>..<h6>. Before each section title insert <p>&nbsp;</p> as separator. Title examples: \"Key features\", \"Materials\", \"Technical specs\", \"Care\".\n\n3. FEATURE LISTS: each bullet goes in its own <p>• text</p>. NEVER use <ul> or <li>. Identify the key concept (1-4 words at start) and wrap it in <strong>, followed by colon. Example: <p>• <strong>100% melamine:</strong> lightweight and unbreakable.</p>. DO NOT invent text.\n\n4. FORBIDDEN SYMBOLS: remove ™ ® © from all text (titles included).\n\n5. ALL-CAPS WORDS: any word in all caps with 4+ letters → Title Case (\"TRITAN\" → \"Tritan\", \"POLYESTER\" → \"Polyester\"). Preserve short acronyms (BPA, UV, LED, IP, USB, ABS, PVC, AISI…).\n\n6. PRESERVE existing <a href> tags untouched.\n\n7. DO NOT summarize, DO NOT paraphrase, DO NOT invent information: keep ALL original text. Only add HTML structure, sections, bold, colons and formatting.\n\n8. If input is only 1-2 short sentences, return <p>text</p> without list.\n\n9. Allowed tags: <p>, <strong>, <a>. Forbidden: <h1>..<h6>, <br>, <div>, <span>, <ul>, <li>, <table>, <ol>.\n\n10. Output: ONLY the HTML, no markdown, no comments, no triple-backticks.";

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$max    = isset($_POST['max']) ? (int) $_POST['max'] : (isset($_GET['max']) ? (int) $_GET['max'] : 0);
$dryRun = isset($_POST['dry_run']) || isset($_GET['dry_run']) || $action === 'dry_run';
$skipFormat = isset($_POST['skip_format']) || isset($_GET['skip_format']);
$selectedSubcat = trim((string) ($_POST['subcat'] ?? $_GET['subcat'] ?? 'all'));

function logMsg($msg) {
    $line = '[' . date('H:i:s') . '] ' . $msg . "\n";
    echo '<pre style="margin:0;padding:2px 8px;border-bottom:1px solid #eee;white-space:pre-wrap;overflow-wrap:break-word;word-break:break-word;font-family:monospace;font-size:12px;">' . htmlspecialchars($line) . '</pre>';
    @flush();
}

function mbParseNum($v) {
    $v = trim((string) $v);
    if ($v === '') return null;
    $v = str_replace(',', '.', $v);
    return is_numeric($v) ? (float) $v : null;
}

function mbCleanHtml($html) {
    if ($html === null || $html === '') return '';
    $html = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html);
    $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
    $html = preg_replace('#<\s*br\s*/?\s*>#i', "\n", $html);
    $html = preg_replace('#</\s*(p|div|li|tr|h[1-6]|ul|ol|table|article|section)\s*>#i', "\n", $html);
    $html = preg_replace('#<\s*li\b[^>]*>#i', "- ", $html);
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $lines = preg_split("/\r\n|\r|\n/", $text);
    $out = [];
    $emptyStreak = 0;
    foreach ($lines as $l) {
        $l = trim(preg_replace('/[ \t\x{A0}]+/u', ' ', $l));
        if ($l === '') { if ($emptyStreak < 1 && !empty($out)) $out[] = ''; $emptyStreak++; continue; }
        $out[] = $l;
        $emptyStreak = 0;
    }
    return trim(implode("\n", $out));
}

function mbSlugify($text, $maxLen = 50) {
    $t = trim((string) $text);
    if (function_exists('iconv')) {
        $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t);
        if ($conv !== false && $conv !== '') $t = $conv;
    }
    $t = strtolower($t);
    $t = preg_replace('/[^a-z0-9]+/', '-', $t);
    $t = trim($t, '-');
    if (strlen($t) > $maxLen) $t = substr($t, 0, $maxLen);
    return trim($t, '-') ?: 'producto';
}

/** Quita "- Marine Business" del final si ya estaba (idempotente). */
function mbStripSuffix($name) {
    $s = (string) $name;
    $suf = NAME_SUFFIX;
    $sufLen = strlen($suf);
    if (strlen($s) >= $sufLen && substr_compare($s, $suf, -$sufLen, $sufLen, true) === 0) {
        $s = rtrim(substr($s, 0, -$sufLen));
    }
    return $s;
}

/** Añade el sufijo "- Marine Business" y trunca al máximo (preservando el sufijo). */
function mbApplySuffix($name) {
    // Decode HTML entities (&#8211;, &amp;, &#038;, etc.) que la WC API devuelve encodadas
    $name = html_entity_decode((string) $name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $name = mbStripSuffix($name);
    $name = trim(preg_replace('/\s+/u', ' ', $name));
    if ($name === '') $name = '(sin nombre)';
    $suf = NAME_SUFFIX;
    $sufLen = mb_strlen($suf, 'UTF-8');
    $maxBody = PRODUCT_NAME_MAX - $sufLen;
    if (mb_strlen($name, 'UTF-8') > $maxBody) {
        $name = rtrim(mb_substr($name, 0, $maxBody, 'UTF-8'));
    }
    return $name . $suf;
}

/** Devuelve el prefijo de subcategoría a partir del nombre del producto (xlsx col B).
 *  Splits en el primer dash-like char (-, –, —). Uppercase + trim. */
function mbSubcatPrefix($desc) {
    $d = trim((string) $desc);
    if ($d === '') return '';
    if (preg_match('/^(.*?)[\-\x{2013}\x{2014}]/u', $d, $m)) {
        $p = trim($m[1]);
    } else {
        $p = $d;
    }
    $p = preg_replace('/\s+/u', ' ', $p);
    $p = mb_strtoupper($p, 'UTF-8');
    // Cualifier: si tiene comas o demasiados caracteres → no es prefijo limpio
    if (strpos($p, ',') !== false) return '';
    if (mb_strlen($p, 'UTF-8') > 30) return '';
    if (preg_match('/\d{2,}/u', $p)) return '';
    return $p;
}

function ean13Checksum($payload12) {
    if (strlen($payload12) !== 12 || !ctype_digit($payload12)) return -1;
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $d = (int) $payload12[$i];
        $sum += ($i % 2 === 0) ? $d : $d * 3;
    }
    return (10 - ($sum % 10)) % 10;
}
function isValidEan13($ean) {
    $ean = trim((string) $ean);
    if (strlen($ean) !== 13 || !ctype_digit($ean)) return false;
    return ean13Checksum(substr($ean, 0, 12)) === (int) $ean[12];
}
function generateInternalEan13($productId, $providerPrefix) {
    $pp = (int) $providerPrefix;
    if ($pp < 20 || $pp > 28) return '';
    if ($productId <= 0 || $productId > 9999999999) return '';
    $payload = str_pad((string) $pp, 2, '0', STR_PAD_LEFT) . str_pad((string) $productId, 10, '0', STR_PAD_LEFT);
    $check = ean13Checksum($payload);
    return $check < 0 ? '' : ($payload . $check);
}

/** Redondea un PVP NETO de modo que el precio CON IVA quede en múltiplo de 0.05. */
function roundToNickel($net) {
    $withIva = ((float) $net) * (1 + MB_VAT_RATE);
    $rounded = round($withIva * 20) / 20;
    return round($rounded / (1 + MB_VAT_RATE), 4);
}

function mbBrowserHeaders() {
    return [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Accept: application/json, text/javascript, */*; q=0.01',
        'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
        'Accept-Encoding: gzip, deflate, br',
        'Referer: https://marinebusiness.net/',
    ];
}

function mbPortalCookie() { return sys_get_temp_dir() . '/mb_portal_ck.txt'; }

function mbPortalCurl($method, $url, $post = null, $hdr = []) {
    $ck = mbPortalCookie();
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR      => $ck,
        CURLOPT_COOKIEFILE     => $ck,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => WEB_HTTP_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT      => MB_PORTAL_UA,
        CURLOPT_HTTPHEADER     => array_merge(['Accept: */*'], $hdr),
    ]);
    if ($method === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $post); }
    $b = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); unset($ch);
    return [$c, $b];
}

/** Login una vez por ejecución en el portal inaCatalog. Éxito = respuesta vacía. */
function mbPortalLogin() {
    static $done = false, $ok = false;
    if ($done) return $ok;
    $done = true;
    @unlink(mbPortalCookie());
    $enc = function ($x) { return '_enc_' . str_replace('/', '_', base64_encode((string) $x)); };
    mbPortalCurl('GET', MB_PORTAL_BASE . '/login');
    list($code, $body) = mbPortalCurl('POST', MB_PORTAL_BASE . '/Usuario/Login', http_build_query([
        'datLoginEncoded'    => $enc(MB_PORTAL_USER),
        'datPasswordEncoded' => $enc(MB_PORTAL_PASS),
        'codIdioma'          => 'es',
    ]), ['X-Requested-With: XMLHttpRequest', 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8', 'Referer: ' . MB_PORTAL_BASE . '/login']);
    $ok = ($code === 200 && trim((string) $body) === '');
    return $ok;
}

/** Devuelve el artículo del portal por código (cache en memoria + disco). */
function mbPortalArticle($code) {
    static $mem = [];
    $code = trim((string) $code);
    if ($code === '') return null;
    if (array_key_exists($code, $mem)) return $mem[$code];
    $mem[$code] = null;
    if (!is_dir(MB_CACHE_DIR)) @mkdir(MB_CACHE_DIR, 0775, true);
    $cf = MB_CACHE_DIR . 'portal_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $code) . '.json';
    if (file_exists($cf) && filesize($cf) >= 2) {
        $j = json_decode(@file_get_contents($cf), true);
        if (is_array($j)) { $mem[$code] = $j[$code] ?? null; return $mem[$code]; }
    }
    if (!mbPortalLogin()) return null;
    list($hc, $hb) = mbPortalCurl('POST', MB_PORTAL_BASE . '/Articulo/ObtenerArticulos/?t',
        '["' . addslashes($code) . '"]',
        ['X-Requested-With: XMLHttpRequest', 'Content-Type: application/json; charset=UTF-8', 'Referer: ' . MB_PORTAL_BASE . '/']);
    if ($hc !== 200 || $hb === false) return null;
    $j = json_decode($hb, true);
    if (!is_array($j)) return null;
    @file_put_contents($cf, $hb);
    $mem[$code] = $j[$code] ?? null;
    return $mem[$code];
}

/**
 * Reemplazo de la WC API: obtiene el artículo del portal B2B y lo devuelve con la
 * MISMA forma que consumía el importador (name / description / images[].src).
 * Imagen full-size (768px) = MB_PORTAL_BASE + imagen + '.jpg' (estática, pública).
 * ES: nombre+descripción del portal. EN: solo imágenes (el importador cae al xlsx inglés).
 */
function mbWcFetch($sku, $lang) {
    $art = mbPortalArticle($sku);
    if (!is_array($art)) return null;
    $imgs = [];
    $imagen = trim((string) ($art['imagen'] ?? ''));
    if ($imagen !== '') $imgs[] = ['src' => MB_PORTAL_BASE . $imagen . '.jpg'];
    if ($lang === 'es') {
        return [
            'name'        => (string) ($art['desarticulo'] ?? ''),
            'description' => (string) ($art['obsarticulo'] ?? ''),
            'images'      => $imgs,
        ];
    }
    return ['name' => '', 'description' => '', 'images' => $imgs];
}

function downloadImage($url, $destAbs) {
    if (empty($url)) return false;
    $url = trim($url);
    if (strpos($url, ' ') !== false) return false;
    if (!preg_match('#^https?://#i', $url)) return false;
    $ch = curl_init($url);
    $fp = fopen($destAbs, 'wb');
    if (!$fp) return false;
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => IMG_HTTP_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
            'Accept-Language: es-ES,es;q=0.9',
            'Referer: https://marinebusiness.net/',
        ],
    ]);
    $ok = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);
    fclose($fp);
    $ok = $ok && $code === 200 && filesize($destAbs) >= IMG_MIN_BYTES;
    if (!$ok) @unlink($destAbs);
    return $ok;
}

function downloadImagesToTmp(array $urls, $maxImages) {
    $seen = [];
    $tmpFiles = [];
    foreach ($urls as $url) {
        if (count($tmpFiles) >= $maxImages) break;
        $url = trim($url);
        if ($url === '' || isset($seen[$url])) continue;
        $seen[$url] = true;
        $tmpAbs = IMG_ABS_DIR . 'mb-tmp-' . uniqid('', true) . '.jpg';
        if (downloadImage($url, $tmpAbs)) {
            $tmpFiles[] = $tmpAbs;
        }
    }
    return $tmpFiles;
}

/** Construye una lista HTML con specs del xlsx (col por col). */
/** Traduce un valor ES → EN si está en la tabla. Si no, devuelve el valor original.
 *  Maneja también case-insensitive y palabras dentro de strings compuestos (ej "POLIESTER/ALGODON" → "POLYESTER/COTTON"). */
function mbTranslateValueEsToEn($v) {
    static $words = [
        // Colores
        'azul' => 'blue', 'rojo' => 'red', 'verde' => 'green', 'negro' => 'black', 'blanco' => 'white',
        'amarillo' => 'yellow', 'naranja' => 'orange', 'rosa' => 'pink', 'gris' => 'gray', 'marron' => 'brown',
        'marrón' => 'brown', 'beige' => 'beige', 'turquesa' => 'turquoise', 'transparente' => 'clear',
        'coral' => 'coral', 'acqua' => 'aqua', 'plata' => 'silver', 'oro' => 'gold', 'morado' => 'purple',
        'violeta' => 'purple', 'cian' => 'cyan', 'natural' => 'natural', 'crema' => 'cream',
        'marino' => 'navy', 'navy' => 'navy', 'fucsia' => 'fuchsia',
        // Materiales
        'poliester' => 'polyester', 'algodon' => 'cotton', 'algodón' => 'cotton', 'resina' => 'resin',
        'melamina' => 'melamine', 'cristal' => 'glass', 'vidrio' => 'glass', 'acero' => 'steel',
        'aluminio' => 'aluminum', 'madera' => 'wood', 'plastico' => 'plastic', 'plástico' => 'plastic',
        'caucho' => 'rubber', 'cuero' => 'leather', 'tela' => 'fabric', 'tejido' => 'fabric',
        'inoxidable' => 'stainless', 'sintetico' => 'synthetic', 'sintético' => 'synthetic',
        'piel' => 'leather', 'silicona' => 'silicone', 'bambu' => 'bamboo', 'bambú' => 'bamboo',
        'tritan' => 'tritan',
        // Países
        'españa' => 'spain', 'espana' => 'spain', 'italia' => 'italy', 'china' => 'china',
        'francia' => 'france', 'alemania' => 'germany', 'portugal' => 'portugal', 'india' => 'india',
        'turquia' => 'turkey', 'turquía' => 'turkey', 'japon' => 'japan', 'japón' => 'japan',
        'taiwan' => 'taiwan', 'corea' => 'korea',
        // Sí/No
        'sí' => 'yes', 'si' => 'yes', 'no' => 'no',
        // Sufijos en dimensiones
        'dobladillo' => 'hem',
    ];
    $out = preg_replace_callback('/[\p{L}]+/u', function($m) use ($words) {
        $w = $m[0];
        $low = mb_strtolower($w, 'UTF-8');
        if (!isset($words[$low])) return $w;
        $tr = $words[$low];
        // Preservar casing del original: TODO MAYÚS → upper / Title → ucfirst / lower → lower
        if (mb_strtoupper($w, 'UTF-8') === $w && mb_strlen($w, 'UTF-8') > 1) return mb_strtoupper($tr, 'UTF-8');
        if (mb_strtolower($w, 'UTF-8') !== $w && mb_substr($w, 0, 1, 'UTF-8') === mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8')) {
            return mb_strtoupper(mb_substr($tr, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($tr, 1, null, 'UTF-8');
        }
        return $tr;
    }, $v);
    return $out;
}

function mbXlsxSpecsHtml($row, $lang) {
    $L = function ($es, $en) use ($lang) { return $lang === 'en' ? $en : $es; };
    $V = function ($v) use ($lang) { return $lang === 'en' ? mbTranslateValueEsToEn($v) : $v; };
    $specs = [];
    $material = trim((string)($row['MATERIAL'] ?? ''));
    if ($material !== '') $specs[] = ['k' => $L('Material', 'Material'), 'v' => $V($material)];
    $color = trim((string)($row['COLOR'] ?? ''));
    if ($color !== '') $specs[] = ['k' => $L('Color', 'Color'), 'v' => $V($color)];
    $cap = trim((string)($row['CAP'] ?? ''));
    if ($cap !== '') $specs[] = ['k' => $L('Capacidad', 'Capacity'), 'v' => $cap];
    $dim = trim((string)($row['DIM'] ?? ''));
    if ($dim !== '') $specs[] = ['k' => $L('Dimensiones', 'Dimensions'), 'v' => $V($dim)];
    $weight = mbParseNum($row['WEIGHT_NET']);
    if ($weight !== null && $weight > 0) {
        $w = rtrim(rtrim(number_format($weight, 3, '.', ''), '0'), '.');
        $specs[] = ['k' => $L('Peso neto', 'Net weight'), 'v' => $w . ' kg'];
    }
    $uds = trim((string)($row['UDS'] ?? ''));
    if ($uds !== '' && (int)$uds > 1) $specs[] = ['k' => $L('Unidades por caja', 'Units per box'), 'v' => $uds];
    $origin = trim((string)($row['ORIGIN'] ?? ''));
    if ($origin !== '') $specs[] = ['k' => $L('Origen', 'Origin'), 'v' => $V(mb_convert_case(mb_strtolower($origin, 'UTF-8'), MB_CASE_TITLE, 'UTF-8'))];
    $micro = trim((string)($row['MICRO'] ?? ''));
    $dish  = trim((string)($row['DISH'] ?? ''));
    $slip  = trim((string)($row['SLIP'] ?? ''));
    $boolMap = function ($v, $yesEs, $yesEn) {
        $up = strtoupper(trim((string) $v));
        if ($up === '' || $up === 'NO') return null;
        return ['yes_es' => $yesEs, 'yes_en' => $yesEn];
    };
    if (($b = $boolMap($micro, 'Apto microondas', 'Microwave safe'))) $specs[] = ['k' => $L($b['yes_es'], $b['yes_en']), 'v' => $L('Sí', 'Yes')];
    if (($b = $boolMap($dish,  'Apto lavavajillas', 'Dishwasher safe'))) $specs[] = ['k' => $L($b['yes_es'], $b['yes_en']), 'v' => $L('Sí', 'Yes')];
    if (($b = $boolMap($slip,  'Antideslizante', 'Non slip'))) $specs[] = ['k' => $L($b['yes_es'], $b['yes_en']), 'v' => $L('Sí', 'Yes')];
    if (empty($specs)) return '';
    // Formato nuevo (2026-05-11): <p>&nbsp;</p> + <p><strong>Especificaciones</strong></p> + bullets <p>• <strong>K:</strong> v</p>
    $h  = '<p>&nbsp;</p>' . "\n";
    $h .= '<p><strong>' . $L('Especificaciones', 'Specifications') . '</strong></p>' . "\n";
    foreach ($specs as $s) {
        $h .= '<p>• <strong>' . htmlspecialchars($s['k']) . ':</strong> ' . htmlspecialchars($s['v']) . '</p>' . "\n";
    }
    return rtrim($h);
}

function llmCall($systemPrompt, $userText, $maxTokens = 2500, $maxRetries = 2) {
    if (trim((string) $userText) === '') return '';
    $payload = json_encode([
        'model' => LLM_MODEL,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userText],
        ],
        'temperature' => 0.2, 'top_p' => 0.8, 'top_k' => 20,
        'max_tokens' => $maxTokens,
        'chat_template_kwargs' => ['enable_thinking' => false],
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    for ($i = 0; $i <= $maxRetries; $i++) {
        $ch = curl_init(LLM_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);
        if ($resp !== false && $code === 200) {
            $j = json_decode($resp, true);
            $content = $j['choices'][0]['message']['content'] ?? null;
            if (is_string($content) && trim($content) !== '') return trim($content);
        }
        usleep(500000);
    }
    return '';
}

/**
 * Traduce el NOMBRE del producto al español (el desarticulo del portal a veces viene en
 * inglés — ej. colección Cocoa: "Round Fish", "Tuna", "Tray"). Preserva colección/marca/
 * medidas. Si ya está en español, el LLM lo devuelve igual. Devuelve '' si falla.
 */
function mbTranslateName($name) {
    $name = trim((string) $name);
    if ($name === '') return '';
    $sys = 'Traduces nombres de producto de menaje náutico de la marca Marine Business al español de España. REGLAS: (1) mantén INTACTOS el nombre de colección (primera palabra: Cocoa, Sailor, Mare, Ibiza, Northwind, Wob…), medidas/códigos (30x20, 38x15) y "Set Nu"/"Set N u."; (2) traduce las palabras descriptivas al español: Round Fish→Plato Pez, Small→Pequeño, Big→Grande, Tray→Bandeja, Tuna→Atún, Whale→Ballena, Bowl→Bol, Plate→Plato, Dish→Fuente, Cup→Taza, Mug→Taza, Glass→Vaso; (3) colores: White→Blanco, Black→Negro, Blue→Azul, Clear→Transparente; deja igual nombres de color propios como Acqua; (4) Title Case, sin MAYÚSCULAS completas. Si ya está en español, devuélvelo igual. Responde SOLO el nombre, sin comillas ni comentarios.';
    $out = trim((string) llmCall($sys, $name, 100), "\"' \n\r\t");
    if ($out === '' || mb_strlen($out, 'UTF-8') > 120) return '';
    return $out;
}

function mbFormatLooksValid($html, $minLenInput) {
    if (!is_string($html) || trim($html) === '') return false;
    $low = strtolower($html);
    $hasStruct = (strpos($low, '<p>') !== false) || (strpos($low, '<ul>') !== false) || (strpos($low, '<h3>') !== false);
    if (!$hasStruct) return false;
    $plainOut = mb_strlen(trim(strip_tags($html)), 'UTF-8');
    if ($minLenInput > 200 && $plainOut < $minLenInput * 0.4) return false;

    // Defensa anti-bucle: detectar líneas/bullets repetidos (el LLM ocasionalmente entra
    // en loop generando la misma frase N veces, ej. SKU 13303 → "Seguridad alimentaria: Libre de BPA" ×8).
    // Reglas:
    //  - extraer los <li> o <p>•...</p> como "items".
    //  - si hay ≥3 items y > 40% son duplicados literales, rechazar.
    if (preg_match_all('#<li[^>]*>(.*?)</li>|<p>\s*•\s*(.*?)</p>#is', $html, $m, PREG_SET_ORDER) && count($m) >= 3) {
        $items = [];
        foreach ($m as $row) {
            $t = trim(strip_tags($row[1] !== '' ? $row[1] : $row[2]));
            $t = preg_replace('/\s+/u', ' ', $t);
            $items[] = mb_strtolower($t, 'UTF-8');
        }
        $unique = array_unique($items);
        if (count($items) - count($unique) >= max(2, count($items) * 0.4)) {
            return false; // bucle detectado
        }
    }
    return true;
}

/** Mapa de duplicados para el importador Marine Business.
 *  - SKU (products_model / reference_prov): SOLO se considera duplicado si pertenece al
 *    mismo fabricante MB. Una misma referencia "11211" puede ser de Barton Y de MB sin
 *    conflicto: cada fabricante tiene su propio espacio de SKUs.
 *  - EAN (product_ean / products_attributes_ean): se consideran GLOBALES porque son
 *    identificadores únicos GS1 — si el EAN ya existe, es el MISMO producto físico
 *    independientemente del fabricante registrado en BD. */
function buildExistingMap($mysqli) {
    $existing = [];
    $mbFilter = "(p.manufacturers_id = " . MFG_MARINE_BUSINESS . " OR p.products_import_origin LIKE 'marinebusiness%')";

    // SKUs (model + reference_prov) — filtrados al fabricante Marine Business
    $r = $mysqli->query("SELECT LOWER(p.products_model) m FROM products p WHERE p.products_model<>'' AND p.products_model IS NOT NULL AND $mbFilter");
    while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
    $r = $mysqli->query("SELECT LOWER(p.reference_prov) m FROM products p WHERE p.reference_prov<>'' AND p.reference_prov IS NOT NULL AND $mbFilter");
    while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
    $r = $mysqli->query("SELECT LOWER(pa.reference) m FROM products_attributes pa INNER JOIN products p ON p.products_id=pa.products_id WHERE pa.reference<>'' AND pa.reference IS NOT NULL AND $mbFilter");
    while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
    $r = $mysqli->query("SELECT LOWER(pa.reference_prov) m FROM products_attributes pa INNER JOIN products p ON p.products_id=pa.products_id WHERE pa.reference_prov<>'' AND pa.reference_prov IS NOT NULL AND $mbFilter");
    while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;

    // EAN — globales (identificadores únicos GS1)
    $r = $mysqli->query("SELECT LOWER(product_ean) m FROM products WHERE product_ean<>'' AND product_ean IS NOT NULL");
    while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
    $r = $mysqli->query("SELECT LOWER(products_attributes_ean) m FROM products_attributes WHERE products_attributes_ean<>'' AND products_attributes_ean IS NOT NULL");
    while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;

    // Lista negra de reimportación: trata como "ya existentes" los códigos/EAN de productos borrados a propósito.
    require_once dirname(__FILE__) . '/includes/import_blacklist.php';
    $existing += fb_blacklist_keys();
    return $existing;
}

function findNewestXlsx($dir) {
    if (!is_dir($dir)) return null;
    $best = null; $bestT = 0;
    foreach (scandir($dir) as $f) {
        if (substr($f, -5) !== '.xlsx') continue;
        if (substr($f, 0, 1) === '~') continue;
        $abs = $dir . $f;
        $t = filemtime($abs);
        if ($t > $bestT) { $bestT = $t; $best = $abs; }
    }
    return $best;
}

/** Lee las 2 sheets (esp + eng) del xlsx MB y devuelve un array por código.
 *  Cada entrada: campos del esp + nombre/desc EN del eng (NAME_EN). */
function loadMbXlsx($file) {
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file);
    $reader->setReadDataOnly(true);
    $ss = $reader->load($file);

    $byCode = [];
    foreach (['esp', 'eng'] as $sheetName) {
        $sheet = $ss->getSheetByName($sheetName);
        if (!$sheet) continue;
        $hi = $sheet->getHighestRow();
        for ($r = 2; $r <= $hi; $r++) {
            $code = trim((string) $sheet->getCell('A' . $r)->getValue());
            if ($code === '') continue;
            $desc = trim((string) $sheet->getCell('B' . $r)->getValue());
            if ($sheetName === 'esp') {
                $byCode[$code] = [
                    'CODE'       => $code,
                    'DESC_ES'    => $desc,
                    'DESC_EN'    => '',
                    'RETAIL'     => trim((string) $sheet->getCell('C' . $r)->getValue()),
                    'EAN'        => trim((string) $sheet->getCell('D' . $r)->getValue()),
                    'ORIGIN'     => trim((string) $sheet->getCell('F' . $r)->getValue()),
                    'MATERIAL'   => trim((string) $sheet->getCell('G' . $r)->getValue()),
                    'DIM'        => trim((string) $sheet->getCell('M' . $r)->getValue()),
                    'WEIGHT_NET' => trim((string) $sheet->getCell('R' . $r)->getValue()),
                    'CAP'        => trim((string) $sheet->getCell('T' . $r)->getValue()),
                    'UDS'        => trim((string) $sheet->getCell('U' . $r)->getValue()),
                    'SLIP'       => trim((string) $sheet->getCell('W' . $r)->getValue()),
                    'COLOR'      => trim((string) $sheet->getCell('X' . $r)->getValue()),
                    'MICRO'      => trim((string) $sheet->getCell('Y' . $r)->getValue()),
                    'CHARS'      => trim((string) $sheet->getCell('Z' . $r)->getValue()),
                    'DISH'       => trim((string) $sheet->getCell('AA' . $r)->getValue()),
                ];
            } else {
                if (!isset($byCode[$code])) $byCode[$code] = ['CODE' => $code];
                $byCode[$code]['DESC_EN'] = $desc;
            }
        }
    }
    return $byCode;
}

/** Asegura la categoría padre 1743 y devuelve su id. Crea si falta. */
function ensureParentCategory($mysqli, $dryRun, &$createdLog) {
    $pid = PARENT_CATEGORY_ID;
    $r = $mysqli->query("SELECT categories_id FROM categories WHERE categories_id=$pid LIMIT 1");
    if ($r && $r->fetch_assoc()) return $pid;
    if ($dryRun) { $createdLog[] = "categoría padre $pid (" . PARENT_CATEGORY_NAME_ES . ") (dry-run, no creada)"; return $pid; }
    $mysqli->query("INSERT INTO categories (categories_id, parent_id, sort_order, date_added, last_modified, categories_status) VALUES ($pid, 0, 0, NOW(), NOW(), 0)");
    $qEs = $mysqli->real_escape_string(PARENT_CATEGORY_NAME_ES);
    $qEn = $mysqli->real_escape_string(PARENT_CATEGORY_NAME_EN);
    $mysqli->query("INSERT INTO categories_description (categories_id, language_id, categories_name) VALUES ($pid, " . LANG_ID_ES . ", '$qEs')");
    $mysqli->query("INSERT INTO categories_description (categories_id, language_id, categories_name) VALUES ($pid, " . LANG_ID_EN . ", '$qEn')");
    $createdLog[] = "categoría padre $pid (" . PARENT_CATEGORY_NAME_ES . ")";
    return $pid;
}

/** Devuelve el id de la subcategoría con nombre $name dentro de PARENT_CATEGORY_ID. Crea si falta. */
function getOrCreateSubcategory($mysqli, $name, $parentId, $dryRun, &$cache, &$createdLog) {
    $nm = trim(preg_replace('/\s+/u', ' ', (string) $name));
    if ($nm === '') $nm = FALLBACK_SUBCAT;
    $key = mb_strtoupper($nm, 'UTF-8');
    if (isset($cache[$key])) return $cache[$key];

    $qName = $mysqli->real_escape_string($nm);
    $parentId = (int) $parentId;
    $r = $mysqli->query("SELECT c.categories_id FROM categories c
        INNER JOIN categories_description cd ON cd.categories_id=c.categories_id AND cd.language_id=" . LANG_ID_ES . "
        WHERE c.parent_id=$parentId AND UPPER(TRIM(cd.categories_name))=UPPER('$qName')
        LIMIT 1");
    if ($r && $row = $r->fetch_assoc()) { $cache[$key] = (int) $row['categories_id']; return $cache[$key]; }

    if ($dryRun) {
        $cache[$key] = 0;
        $createdLog[] = "subcategoría '$nm' bajo $parentId (dry-run, no creada)";
        return 0;
    }
    // sort_order = MAX+1 dentro del parent (consistente con cómo navega el catálogo)
    $r = $mysqli->query("SELECT IFNULL(MAX(sort_order), 0) + 1 AS nso FROM categories WHERE parent_id=$parentId");
    $nso = (int) ($r->fetch_assoc()['nso'] ?? 1);
    $mysqli->query("INSERT INTO categories (parent_id, sort_order, date_added, last_modified, categories_status) VALUES ($parentId, $nso, NOW(), NOW(), 1)");
    $newId = (int) $mysqli->insert_id;
    $mysqli->query("INSERT INTO categories_description (categories_id, language_id, categories_name) VALUES ($newId, " . LANG_ID_ES . ", '$qName')");
    $mysqli->query("INSERT INTO categories_description (categories_id, language_id, categories_name) VALUES ($newId, " . LANG_ID_EN . ", '$qName')");
    $cache[$key] = $newId;
    $createdLog[] = "subcategoría '$nm' (id=$newId) bajo $parentId";
    return $newId;
}

/** Mapea cada code → subcategoría (prefijo si tiene ≥SUBCAT_MIN_ROWS filas, si no FALLBACK_SUBCAT). */
function classifySubcategories(array $rowsByCode) {
    $counts = [];
    foreach ($rowsByCode as $code => $r) {
        $p = mbSubcatPrefix($r['DESC_ES'] ?? '');
        if ($p === '') continue;
        $counts[$p] = ($counts[$p] ?? 0) + 1;
    }
    $bySubcat = [];
    foreach ($rowsByCode as $code => $r) {
        $p = mbSubcatPrefix($r['DESC_ES'] ?? '');
        if ($p === '' || ($counts[$p] ?? 0) < SUBCAT_MIN_ROWS) $p = FALLBACK_SUBCAT;
        $bySubcat[$p] = $bySubcat[$p] ?? [];
        $bySubcat[$p][$code] = $r;
    }
    return $bySubcat;
}

$isAction = ($action === 'execute' || $action === 'dry_run');

if ($isAction) {
    @header('X-Accel-Buffering: no');
    @header('Content-Type: text/html; charset=utf-8');
    while (ob_get_level() > 0) @ob_end_flush();
    @ob_implicit_flush(true);
    if (session_status() === PHP_SESSION_ACTIVE) @session_write_close();
}
?>
<?php require THEME . 'html/header.php'; ?>
<table style="width:100%;"><tr><td>
<div style="padding:20px;">
<?php if ($isAction): ?>
    <h2>Importador Marine Business — <?php echo $dryRun ? 'DRY-RUN (sin cambios)' : 'EJECUCIÓN REAL'; ?></h2>
    <p>
        <a href="<?php echo tep_href_link('import-marinebusiness-altas.php'); ?>" class="xbutton small hv9">← Volver</a>
    </p>
    <div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n";
@flush();

logMsg("Modo: " . ($dryRun ? "dry-run" : "EXECUTE")
    . " | subcat=" . ($selectedSubcat === 'all' ? 'TODAS' : $selectedSubcat)
    . ($skipFormat ? " | sin maquetado LLM" : "")
    . ($max > 0 ? " | max=$max" : ""));

$xlsx = findNewestXlsx(MB_DIR);
if (!$xlsx) { logMsg("ERROR: no hay xlsx en " . MB_DIR); goto end_action; }
logMsg("xlsx: " . basename($xlsx) . " (" . round(filesize($xlsx)/1024) . " KB, mtime " . date('Y-m-d H:i', filemtime($xlsx)) . ")");

logMsg("Leyendo xlsx (sheets esp + eng)…");
$byCode = loadMbXlsx($xlsx);
logMsg("Filas: " . count($byCode));

$bySubcat = classifySubcategories($byCode);
logMsg("Subcategorías detectadas: " . count($bySubcat));

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_error) { logMsg("ERROR DB: " . $mysqli->connect_error); goto end_action; }
$mysqli->set_charset('utf8');

if (!is_dir(IMG_ABS_DIR)) @mkdir(IMG_ABS_DIR, 0775, true);
if (!is_dir(MB_CACHE_DIR)) @mkdir(MB_CACHE_DIR, 0775, true);

logMsg("Cargando IDs existentes en BD…");
$existing = buildExistingMap($mysqli);
logMsg("  → " . count($existing) . " referencias ya en BD");

$createdCats = [];
$parentId = ensureParentCategory($mysqli, $dryRun, $createdCats);
$subcatCache = [];

// Selección de subcategoría a importar
$selUp = mb_strtoupper($selectedSubcat, 'UTF-8');
if ($selectedSubcat !== 'all' && !isset($bySubcat[$selUp])) {
    logMsg("ERROR: subcategoría '$selectedSubcat' no detectada en xlsx");
    goto end_action;
}
$workRows = ($selectedSubcat === 'all') ? $byCode : $bySubcat[$selUp];
logMsg("Filas seleccionadas: " . count($workRows));

// Pre-filtrado: skip por código/EAN existente, valida precio retail
$candidates = [];
$skipExist = $skipNoCode = $skipBadPrice = 0;
foreach ($workRows as $code => $row) {
    if ($code === '') { continue; }
    if (isset($existing[strtolower($code)])) { $skipExist++; continue; }
    if (!empty($row['EAN']) && isset($existing[strtolower($row['EAN'])])) { $skipExist++; continue; }
    $retail = mbParseNum($row['RETAIL'] ?? '');
    if ($retail === null || $retail <= 0) { $skipBadPrice++; continue; }
    $row['_RETAIL'] = round($retail, 4);
    $row['_COST']   = round($retail * COST_MULT, 4);
    $row['_PRICE']  = roundToNickel($retail * PRICE_MULT_NET);
    $row['_G1']     = roundToNickel($retail * G1_MULT);
    $w = mbParseNum($row['WEIGHT_NET'] ?? '');
    $row['_WEIGHT'] = ($w !== null && $w > 0) ? round($w, 3) : 1.0;
    $candidates[$code] = $row;
}
logMsg("Candidatos tras pre-filtro: " . count($candidates) . " | pre-skip: existentes=$skipExist | precio=$skipBadPrice");

$nInserted = $nWithImg = $imgFail = $skippedNoImg = $skippedNoApi = $apiFails = $formatFail = $errors = 0;
$nSubImgTotal = 0;
$ts0 = microtime(true);

foreach ($candidates as $code => $row) {
    if ($max > 0 && $nInserted >= $max) { logMsg("Alcanzado límite max=$max, parando."); break; }

    // Subcategoría destino
    $subcatName = FALLBACK_SUBCAT;
    $p = mbSubcatPrefix($row['DESC_ES'] ?? '');
    if ($p !== '' && isset($bySubcat[$p]) && count($bySubcat[$p]) >= SUBCAT_MIN_ROWS) $subcatName = $p;
    $subcatId = getOrCreateSubcategory($mysqli, $subcatName, $parentId, $dryRun, $subcatCache, $createdCats);

    // Fetch WC Store API ES + EN (con cache)
    $apiEs = mbWcFetch($code, 'es');
    $apiEn = mbWcFetch($code, 'en');
    if ($apiEs === null && $apiEn === null) $apiFails++;

    // Nombres: API si existe, si no xlsx col B
    $nameEsRaw = ($apiEs['name'] ?? '') !== '' ? $apiEs['name'] : ($row['DESC_ES'] ?? '');
    $nameEnRaw = ($apiEn['name'] ?? '') !== '' ? $apiEn['name'] : (($row['DESC_EN'] !== '') ? $row['DESC_EN'] : ($row['DESC_ES'] ?? ''));
    // Traducir el nombre ES al español si el desarticulo del portal vino en inglés (LLM up)
    if (!$skipFormat && !$dryRun && $nameEsRaw !== '') {
        $tn = mbTranslateName($nameEsRaw);
        if ($tn !== '') $nameEsRaw = $tn;
    }
    $nameEs = mbApplySuffix($nameEsRaw);
    $nameEn = mbApplySuffix($nameEnRaw);

    // Descripciones: API + bloque de specs del xlsx; maquetado vía LLM (opcional)
    // (decode entities tipo &#8211; → –, &amp; → & — la WC API las devuelve encodadas)
    $apiDescEs = html_entity_decode((string)($apiEs['description'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $apiDescEn = html_entity_decode((string)($apiEn['description'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $charsEs = trim((string)($row['CHARS'] ?? ''));
    $specsEs = mbXlsxSpecsHtml($row, 'es');
    $specsEn = mbXlsxSpecsHtml($row, 'en');

    $rawDescEs = trim($apiDescEs);
    if ($rawDescEs === '' && $row['DESC_ES'] !== '') $rawDescEs = '<p>' . htmlspecialchars($row['DESC_ES']) . '</p>';
    if ($charsEs !== '' && mb_stripos(strip_tags((string)$rawDescEs), $charsEs) === false) $rawDescEs .= "\n\n<p><strong>" . htmlspecialchars($charsEs) . "</strong></p>";

    $rawDescEn = trim($apiDescEn);
    if ($rawDescEn === '' && $row['DESC_EN'] !== '') $rawDescEn = '<p>' . htmlspecialchars($row['DESC_EN']) . '</p>';

    $descEs = $rawDescEs;
    $descEn = $rawDescEn;
    if (!$skipFormat && !$dryRun) {
        if ($rawDescEs !== '') {
            $inLen = mb_strlen(strip_tags($rawDescEs), 'UTF-8');
            $fmt = llmCall(LLM_FORMAT_PROMPT_ES, $rawDescEs, 2500);
            if (mbFormatLooksValid($fmt, $inLen)) $descEs = $fmt; else $formatFail++;
        }
        if ($rawDescEn !== '') {
            $inLen = mb_strlen(strip_tags($rawDescEn), 'UTF-8');
            $fmt = llmCall(LLM_FORMAT_PROMPT_EN, $rawDescEn, 2500);
            if (mbFormatLooksValid($fmt, $inLen)) $descEn = $fmt; else $formatFail++;
        }
    }
    // Pegar bloque de specs si tiene contenido (después del HTML maquetado, no se le pasa al LLM para no inflarlo)
    if ($specsEs !== '') $descEs = trim($descEs) . "\n" . $specsEs;
    if ($specsEn !== '') $descEn = trim($descEn) . "\n" . $specsEn;
    // Reformat final (red de seguridad por si el LLM no obedeció la guía nueva): aplica las 8 reglas estéticas MB.
    $descEs = mbReformatDescription($descEs);
    $descEn = mbReformatDescription($descEn);

    // URLs de imágenes — API es la fuente canónica. Sin imagen → skip.
    $imageUrls = [];
    foreach ((array)($apiEs['images'] ?? []) as $im) { if (!empty($im['src'])) $imageUrls[] = $im['src']; }
    foreach ((array)($apiEn['images'] ?? []) as $im) { if (!empty($im['src'])) $imageUrls[] = $im['src']; }
    // Dedup conservando orden
    $seen = []; $unique = [];
    foreach ($imageUrls as $u) { if (!isset($seen[$u])) { $seen[$u] = true; $unique[] = $u; } }
    $imageUrls = $unique;

    if (empty($imageUrls)) {
        $skippedNoImg++;
        $skippedNoApi++;
        logMsg("SKIP code=$code: sin imagen (no en WC API)");
        continue;
    }

    if ($dryRun) {
        $nInserted++;
        if ($nInserted <= 12) {
            logMsg(sprintf("  WOULD INSERT code=%s subcat='%s' retail=%.2f price=%.2f cost=%.2f g1=%.2f imgs=%d name='%s'",
                $code, $subcatName, $row['_RETAIL'], $row['_PRICE'], $row['_COST'], $row['_G1'],
                count($imageUrls), mb_substr($nameEs, 0, 60, 'UTF-8')));
        }
        continue;
    }

    $tmpFiles = downloadImagesToTmp($imageUrls, MAX_SUBIMAGES + 1);
    if (empty($tmpFiles)) {
        $skippedNoImg++; $imgFail++;
        logMsg("SKIP code=$code: " . count($imageUrls) . " URL probadas, ninguna descargada (>4KB)");
        continue;
    }

    $mysqli->begin_transaction();
    try {
        $qmodel = $mysqli->real_escape_string($code);
        $qref   = $mysqli->real_escape_string($code);
        $qean   = $mysqli->real_escape_string(isValidEan13($row['EAN']) ? $row['EAN'] : '');
        $qmfg   = MFG_MARINE_BUSINESS;
        $price  = number_format($row['_PRICE'], 4, '.', '');
        $cost   = number_format($row['_COST'], 4, '.', '');
        $weight = number_format($row['_WEIGHT'], 3, '.', '');
        $sql = "INSERT INTO products (products_quantity, check_stock, products_model, products_image, products_price, products_cost, products_date_added, products_weight, products_status, products_tax_class_id, manufacturers_id, product_ean, reference_prov, products_import_origin)
            VALUES (0, 0, \"$qmodel\", \"\", $price, $cost, NOW(), $weight, 2, " . TAX_CLASS_IVA21 . ", $qmfg, \"$qean\", \"$qref\", \"" . ORIGIN_FLAG . "\")";
        if (!$mysqli->query($sql)) throw new Exception("products: " . $mysqli->error);
        $pid = (int) $mysqli->insert_id;

        $qNameEs = $mysqli->real_escape_string($nameEs);
        $qDescEs = $mysqli->real_escape_string($descEs);
        $qNameEn = $mysqli->real_escape_string($nameEn);
        $qDescEn = $mysqli->real_escape_string($descEn);
        if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_ES . ", \"$qNameEs\", \"$qDescEs\", 0)")) throw new Exception("desc ES: " . $mysqli->error);
        if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_EN . ", \"$qNameEn\", \"$qDescEn\", 0)")) throw new Exception("desc EN: " . $mysqli->error);

        // Subcategoría + padre (osCommerce permite múltiples categorías por producto — registramos la hija y la padre)
        $targetCatId = $subcatId > 0 ? $subcatId : $parentId;
        if (!$mysqli->query("INSERT INTO products_to_categories (products_id, categories_id) VALUES ($pid, $targetCatId)")) throw new Exception("p2c: " . $mysqli->error);

        // G1
        $g1 = number_format($row['_G1'], 4, '.', '');
        if (!$mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . G1_GROUP_ID . ", $pid, $g1, 1, 1)")) throw new Exception("g1: " . $mysqli->error);

        // Renombrar imágenes temporales: 1ª = products_image, resto = products_subimages
        $slug = mbSlugify(mbStripSuffix($nameEs ?: $nameEn));
        $imgFinalNames = [];
        foreach ($tmpFiles as $i => $tmpAbs) {
            $suffix = ($i === 0) ? '' : ('-' . ($i + 1));
            $finalName = $slug . '-' . $pid . $suffix . '.jpg';
            $finalAbs = IMG_ABS_DIR . $finalName;
            if (@rename($tmpAbs, $finalAbs)) $imgFinalNames[] = $finalName;
            else { @unlink($tmpAbs); }
        }
        if (empty($imgFinalNames)) throw new Exception("rename de imágenes falló");
        $mainImg = array_shift($imgFinalNames);
        $mysqli->query("UPDATE products SET products_image=\"" . $mysqli->real_escape_string($mainImg) . "\" WHERE products_id=$pid");
        if (!empty($imgFinalNames)) {
            $subJson = json_encode($imgFinalNames, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $mysqli->query("UPDATE products SET products_subimages='" . $mysqli->real_escape_string($subJson) . "' WHERE products_id=$pid");
        }
        $nWithImg++;
        $nSubImgTotal += count($imgFinalNames);

        $mysqli->commit();

        // EAN interno si el del feed no es válido (en MB casi siempre lo es)
        if (!isValidEan13($row['EAN'])) {
            $genEan = generateInternalEan13($pid, EAN_INTERNAL_PREFIX);
            if ($genEan !== '') {
                $mysqli->query("UPDATE products SET product_ean='" . $mysqli->real_escape_string($genEan) . "' WHERE products_id=$pid AND (product_ean IS NULL OR product_ean='' OR LENGTH(product_ean)<>13)");
            }
        }

        $nInserted++;
        $nSubsHere = isset($imgFinalNames) ? count($imgFinalNames) : 0;
        logMsg(sprintf("OK pid=%d code=%s subcat='%s' price=%.2f cost=%.2f g1=%.2f imgs=%d name='%s'",
            $pid, $code, $subcatName, $row['_PRICE'], $row['_COST'], $row['_G1'],
            1 + $nSubsHere, mb_substr($nameEs, 0, 50, 'UTF-8')));
    } catch (Exception $e) {
        $mysqli->rollback();
        $errors++;
        foreach ($tmpFiles as $tmpAbs) { if (file_exists($tmpAbs)) @unlink($tmpAbs); }
        logMsg("ERROR code=$code: " . $e->getMessage());
    }
}

$elapsed = microtime(true) - $ts0;
logMsg("==================== RESUMEN ====================");
logMsg(sprintf("Insertados: %d en %.1fs", $nInserted, $elapsed));
logMsg("Con imagen: $nWithImg (sub-imágenes totales: $nSubImgTotal) | fallos descarga imagen: $imgFail | skip por sin imagen/API: $skippedNoImg (de los cuales $skippedNoApi sin match en WC API)");
logMsg("WC API fetches fallidos: $apiFails | maquetados HTML fallidos: $formatFail | errores INSERT: $errors");
if (!empty($createdCats)) {
    logMsg("Categorías " . ($dryRun ? "que se crearían" : "creadas") . " (" . count($createdCats) . "):");
    foreach ($createdCats as $c) logMsg("  · $c");
}

end_action:
?>
    </div>
    <p style="margin-top:15px;">
        <a href="<?php echo tep_href_link('import-marinebusiness-altas.php'); ?>" class="xbutton small hv9">← Volver</a>
    </p>
<?php else: ?>
    <h2>Importador Marine Business (altas)</h2>
    <?php
        $xlsx = findNewestXlsx(MB_DIR);
        if (!$xlsx) {
            echo '<p style="color:red;">No hay xlsx en ' . MB_DIR . '</p>';
        } else {
            echo '<p style="color:#666;font-size:13px;">xlsx detectado: <code>' . htmlspecialchars(basename($xlsx)) . '</code> (' . round(filesize($xlsx)/1024) . ' KB, mtime ' . date('Y-m-d H:i', filemtime($xlsx)) . ')</p>';
        }
    ?>
    <p>
        Lee el xlsx más reciente de <code><?php echo MB_DIR; ?></code> (sheets <code>esp</code> + <code>eng</code>),
        consulta la <strong>WC Store API</strong> pública de <code>marinebusiness.net</code>
        para nombre/descripción/imágenes, y crea productos bajo la categoría
        <strong><?php echo PARENT_CATEGORY_ID; ?> (<?php echo PARENT_CATEGORY_NAME_ES; ?>)</strong>
        en la subcategoría detectada según el prefijo del nombre (parte antes del primer <code>-</code>).
    </p>
    <p style="background:#fffbe6;border:1px solid #ffd700;padding:10px;border-radius:4px;font-size:13px;">
        <strong>Reglas siempre aplicadas</strong>:<br>
        • Manufacturer fijo: <code><?php echo MFG_MARINE_BUSINESS; ?></code> (Marine Business).<br>
        • Skip si <code>products_model</code> / <code>reference_prov</code> / <code>product_ean</code> ya existen en BD (productos o atributos).<br>
        • <code>products_cost</code> = Retail × <?php echo COST_MULT; ?> &nbsp;|&nbsp;
          <code>products_price</code> = roundToNickel(Retail × <?php echo PRICE_MULT_NET; ?>) &nbsp;|&nbsp;
          <code>G1</code> = roundToNickel(Retail × <?php echo G1_MULT; ?>) — todos sin IVA, PVP con IVA en múltiplos de 0,05€.<br>
        • <strong>Sin imagen → no se importa.</strong> Imágenes desde <code>images[]</code> de la WC API (1 principal + hasta <?php echo MAX_SUBIMAGES; ?> sub-imágenes).<br>
        • Sufijo "<code><?php echo NAME_SUFFIX; ?></code>" añadido al final de cada nombre (ES y EN).<br>
        • Sin variantes. Stock NO se toca (products_quantity=0, status=2 desactivado).<br>
        • EAN del xlsx (col D) si pasa checksum; si no → interno con prefijo <?php echo EAN_INTERNAL_PREFIX; ?>.
    </p>
    <form method="get" style="background:#f5f5f5;padding:15px;border-radius:5px;">
        <p>
            <strong>Subcategoría a importar</strong>:
            <select name="subcat" style="min-width:300px;">
                <option value="all">— Todas las subcategorías —</option>
                <?php
                if ($xlsx) {
                    $byCode = loadMbXlsx($xlsx);
                    $bySubcat = classifySubcategories($byCode);
                    ksort($bySubcat);
                    // Subcat "VARIOS" al final
                    $varios = $bySubcat[FALLBACK_SUBCAT] ?? null;
                    unset($bySubcat[FALLBACK_SUBCAT]);
                    foreach ($bySubcat as $name => $rows) {
                        $sel = (mb_strtoupper($selectedSubcat, 'UTF-8') === $name) ? ' selected' : '';
                        echo '<option value="' . htmlspecialchars($name) . '"' . $sel . '>' . htmlspecialchars($name) . ' (' . count($rows) . ')</option>';
                    }
                    if ($varios !== null) {
                        $sel = (mb_strtoupper($selectedSubcat, 'UTF-8') === FALLBACK_SUBCAT) ? ' selected' : '';
                        echo '<option value="' . FALLBACK_SUBCAT . '"' . $sel . '>' . FALLBACK_SUBCAT . ' (sin prefijo o prefijo aislado, ' . count($varios) . ')</option>';
                    }
                }
                ?>
            </select>
        </p>
        <p>
            <label><input type="checkbox" name="dry_run" value="1" checked> Dry-run (sin cambios)</label>
        </p>
        <p>
            <label><input type="checkbox" name="skip_format" value="1"> Saltar maquetado LLM (descripción queda en texto plano de la API)</label>
        </p>
        <p>
            Inserts máximos por ejecución (0 = sin límite):
            <input type="number" name="max" value="10" min="0" style="width:80px;">
        </p>
        <button type="submit" name="action" value="dry_run" class="xbutton small hv9">Dry-run</button>
        <button type="submit" name="action" value="execute" class="xbutton small hv9 verde" onclick="return confirm('¿Ejecutar de verdad? Insertará productos en la BD.');">Ejecutar</button>
    </form>
    <p style="margin-top:20px;color:#888;font-size:12px;">
        <strong>Detalles técnicos</strong>:<br>
        - WC Store API: <code>https://marinebusiness.net/wp-json/wc/store/v1/products?sku=&lt;SKU&gt;</code> (ES);
        <code>/en/wp-json/...</code> (EN). Caché local en <code>import/MB/cache/&lt;sku&gt;.{es,en}.json</code>.<br>
        - Los SKUs no publicados en la web (no aparecen en la WC API) se <strong>saltan</strong> porque no hay imagen.<br>
        - Bloque de "Especificaciones" añadido a la descripción a partir de columnas del xlsx
          (Material, Color, Capacidad, Dimensiones, Peso, Origen, Apto microondas/lavavajillas/antideslizante).<br>
        - Tax class 1 (IVA 21%). Output streaming en tiempo real.
    </p>
<?php endif; ?>
</div>
</td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
