<?php
require 'includes/application_top.php';
require_once DIR_FS_DOCUMENT_ROOT . 'includes/vendor/autoload.php';

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);

const LANK_DIR        = '/home/francobordo/public_html/import/Lankhorst/';
const LANK_CACHE_DIR  = '/home/francobordo/public_html/import/Lankhorst/cache/';
const LANK_WEB_BASE   = 'https://portal.lankhorst-taselaar.com/en/';
define('IMG_ABS_DIR',     dirname(dirname(__FILE__)) . '/images/productos/');
const NEW_CATEGORY_ID   = 1742;
const TAX_CLASS_IVA21   = 1;
const LANG_ID_ES        = 3;
const LANG_ID_EN        = 1;
const G1_GROUP_ID       = 1;
const G1_FLOOR_FACTOR   = 1.10;
const PRODUCT_NAME_MAX  = 80;
const IMG_HTTP_TIMEOUT  = 15;
const WEB_HTTP_TIMEOUT  = 25;
const IMG_MIN_BYTES     = 4096;  // < 4 KB = placeholder "no image" del CDN
const MAX_SUBIMAGES     = 6;     // tope de sub-imágenes (mismo patrón Cressi/Garmin)
const ORIGIN_FLAG       = 'lankhorst';
const EAN_INTERNAL_PREFIX = 25;
const VARIANT_OPTION_ID = 3;
const LCP_VARIANT_RATIO = 0.30;
const LCP_VARIANT_HIGH  = 0.50;
const PRICE_DISPERSION_MAX = 10.0;

// Aliases manufacturer xlsx → BD existente
const BRAND_ALIASES = [
    'BEP'        => 'BEP Marine',
    'LEWMAR'     => 'Lewmar',
    'ENO'        => 'Eno',
    'Boss audio' => 'Boss Audio',
];

// Marcas para las que NO se prefija el nombre del fabricante al título (UPPERCASE)
const BRAND_NO_PREFIX = ['GENERICO'];

const LLM_URL    = 'http://217.127.199.171:28001/v1/chat/completions';
const LLM_MODEL  = 'qwen36-sakamaki-nvfp4';
const LLM_PROMPT_NAME = 'Eres un traductor profesional de inglés a español especializado en productos náuticos, marinos, hardware y accesorios. Usa terminología técnica náutica precisa en español de España. Glosario náutico de referencia (EN↔ES): rope/line=cabo, shackle=grillete, cleat=cornamusa, fairlead/chock=pasacable, winch=winche, windlass=molinete, thruster=hélice de maniobra, bilge=sentina, hatch=escotilla/tambucho, fender=defensa, mooring=amarre, anchor=ancla, chain=cadena, hull=casco, deck=cubierta, rudder=timón, through-hull=pasacascos, seacock=grifo de fondo, stainless steel=acero inoxidable, galvanized=galvanizado, outboard=fueraborda. Usa siempre el sentido náutico; no lo traduzcas como términos de otros dominios. Conserva marcas, modelos, códigos alfanuméricos y unidades (mm, cm, kg, V, W, L) sin traducir. Texto plano. Responde SOLO con la traducción, sin comentarios ni explicaciones, sin comillas.';
const LLM_PROMPT_DESC = 'Eres un traductor profesional de inglés a español especializado en productos náuticos, marinos, hardware y accesorios. Usa terminología técnica náutica precisa en español de España. Glosario náutico de referencia (EN↔ES): rope/line=cabo, shackle=grillete, cleat=cornamusa, fairlead/chock=pasacable, winch=winche, windlass=molinete, thruster=hélice de maniobra, bilge=sentina, hatch=escotilla/tambucho, fender=defensa, mooring=amarre, anchor=ancla, chain=cadena, hull=casco, deck=cubierta, rudder=timón, through-hull=pasacascos, seacock=grifo de fondo, stainless steel=acero inoxidable, galvanized=galvanizado, outboard=fueraborda. Usa siempre el sentido náutico; no lo traduzcas como términos de otros dominios. Conserva marcas, modelos, códigos alfanuméricos y unidades sin traducir. Conserva los <br> y <p>/<ul>/<li>/<strong> si los hay. Responde SOLO con la traducción, sin comentarios ni explicaciones.';

const LLM_FORMAT_PROMPT_ES = "Eres un experto en maquetar fichas de producto náuticas/marinas. Recibes una descripción comercial en ESPAÑOL y la transformas en HTML legible y atractivo.\n\nREGLAS OBLIGATORIAS:\n\n1. PÁRRAFO INTRODUCTORIO: el primer <p> es la frase descriptiva más completa del producto.\n\n2. AGRUPACIÓN POR SECCIONES (OBLIGATORIO si el producto tiene >6 features): clasifica las features en secciones temáticas con <h3>. Ejemplos: \"Características principales\", \"Materiales\", \"Instalación y uso\", \"Especificaciones técnicas\", \"Compatibilidad\". Cada sección abre con <h3>...</h3> y debajo un <ul><li>...</li></ul>. Si el producto tiene <6 features, una sola lista sin h3.\n\n3. ÉNFASIS CON <strong>: en CADA <li>, identifica el concepto clave (1-4 palabras al inicio) y envuélvelo en <strong>. Sigue al concepto con dos puntos \":\" + el resto de la frase. Ejemplo: <li><strong>Acero inoxidable AISI 316:</strong> resistente a la corrosión en ambientes salinos.</li>. NO inventes texto: identifica las palabras clave que ya están en la frase.\n\n4. PRESERVA enlaces <a href> y <sup> existentes sin tocarlos.\n\n5. NO resumas, NO parafrasees, NO inventes información: conserva TODO el texto original. Solo añades estructura HTML, secciones, bold y dos puntos.\n\n6. Si el texto de entrada es solo 1-2 frases cortas (descripción mínima), devuelve <p>texto</p> sin lista.\n\n7. Etiquetas permitidas: <h3>, <h4>, <p>, <ul>, <li>, <strong>, <a>, <sup>. Prohibidas: <h1>, <h2>, <br>, <div>, <span>.\n\n8. Salida: SOLO el HTML, sin markdown, sin comentarios, sin tres-back-ticks.";

const LLM_FORMAT_PROMPT_EN = "You are an expert in formatting nautical/marine product datasheets. You receive a commercial description in ENGLISH and transform it into clean, readable HTML.\n\nMANDATORY RULES:\n\n1. INTRODUCTORY PARAGRAPH: the first <p> is the most complete descriptive sentence about the product.\n\n2. SECTION GROUPING (MANDATORY if product has >6 features): classify features into thematic sections with <h3>. Examples: \"Key features\", \"Materials\", \"Installation & use\", \"Technical specs\", \"Compatibility\". Each section opens with <h3>...</h3> followed by <ul><li>...</li></ul>. If <6 features, one single list without h3.\n\n3. <strong> EMPHASIS: in EACH <li>, identify the key concept (1-4 words at start) and wrap it in <strong>. Follow with colon \":\" + rest of the sentence. Example: <li><strong>AISI 316 stainless steel:</strong> corrosion resistant in salty environments.</li>. DO NOT invent text: identify keywords already present in the sentence.\n\n4. PRESERVE existing <a href> and <sup> tags untouched.\n\n5. DO NOT summarize, DO NOT paraphrase, DO NOT invent information: keep ALL original text. You only add HTML structure, sections, bold, and colons.\n\n6. If the input is only 1-2 short sentences (minimal description), return <p>text</p> without a list.\n\n7. Allowed tags: <h3>, <h4>, <p>, <ul>, <li>, <strong>, <a>, <sup>. Forbidden: <h1>, <h2>, <br>, <div>, <span>.\n\n8. Output: ONLY the HTML, no markdown, no comments, no triple-backticks.";

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$max    = isset($_POST['max']) ? (int) $_POST['max'] : (isset($_GET['max']) ? (int) $_GET['max'] : 0);
$dryRun = isset($_POST['dry_run']) || isset($_GET['dry_run']) || $action === 'dry_run';
$skipTranslation = isset($_POST['skip_translation']) || isset($_GET['skip_translation']);
$skipVariants    = isset($_POST['skip_variants'])    || isset($_GET['skip_variants']);
$skipWebEnrich   = isset($_POST['skip_web_enrich'])  || isset($_GET['skip_web_enrich']);
$selectedBrand   = trim((string) ($_POST['brand'] ?? $_GET['brand'] ?? 'all'));
$onlyCodesRaw    = trim((string) ($_POST['codes'] ?? $_GET['codes'] ?? ''));
$onlyCodes       = [];
if ($onlyCodesRaw !== '') {
    foreach (preg_split('/[\s,;]+/', $onlyCodesRaw) as $c) {
        $c = trim($c);
        if ($c !== '') $onlyCodes[$c] = true;
    }
}

function logMsg($msg) {
    $line = '[' . date('H:i:s') . '] ' . $msg . "\n";
    echo '<pre style="margin:0;padding:2px 8px;border-bottom:1px solid #eee;white-space:pre-wrap;overflow-wrap:break-word;word-break:break-word;font-family:monospace;font-size:12px;">' . htmlspecialchars($line) . '</pre>';
    @flush();
}

function lankParseNum($v) {
    $v = trim((string) $v);
    if ($v === '') return null;
    $v = str_replace(',', '.', $v);
    return is_numeric($v) ? (float) $v : null;
}

function cleanHtmlAggressive($html) {
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
        // OJO: usar modificador /u para no partir secuencias UTF-8 multi-byte como ®
        // (\xC2\xAE). Sin /u, [\xC2\xA0] consume el primer byte de ® como NBSP, dejando
        // \xAE huérfano que MySQL/UTF-8 luego renderiza como "?".
        $l = trim(preg_replace('/[ \t\x{A0}]+/u', ' ', $l));
        if ($l === '') {
            if ($emptyStreak < 1 && !empty($out)) { $out[] = ''; }
            $emptyStreak++;
            continue;
        }
        $out[] = $l;
        $emptyStreak = 0;
    }
    return nl2br(trim(implode("\n", $out)), false);
}

function lankSlugify($text, $maxLen = 50) {
    $t = trim((string) $text);
    if (function_exists('iconv')) {
        $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t);
        if ($conv !== false && $conv !== '') $t = $conv;
    }
    $t = strtolower($t);
    $t = preg_replace('/[^a-z0-9]+/', '-', $t);
    $t = trim($t, '-');
    if (strlen($t) > $maxLen) $t = substr($t, 0, $maxLen);
    $t = trim($t, '-');
    return $t === '' ? 'producto' : $t;
}

function lankNormalizeManufacturer($name) {
    $name = trim(preg_replace('/\s+/', ' ', (string) $name));
    if ($name === '') return '';
    if (isset(BRAND_ALIASES[$name])) return BRAND_ALIASES[$name];
    $upKey = strtoupper($name);
    foreach (BRAND_ALIASES as $k => $v) { if (strtoupper($k) === $upKey) return $v; }
    $words = explode(' ', $name);
    $out = [];
    foreach ($words as $w) {
        if ($w === '') continue;
        $alpha = preg_replace('/[^A-Za-z]/', '', $w);
        if (strlen($alpha) > 0 && strtoupper($alpha) === $alpha && strlen($alpha) <= 4) {
            $out[] = $w;
        } else {
            $out[] = mb_convert_case(mb_strtolower($w, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        }
    }
    return implode(' ', $out);
}

/** Devuelve "<Brand normalizado> " si tiene sentido prefijarlo al título.
 *  Vacío si la marca está en BRAND_NO_PREFIX o si el nombre YA empieza con la marca. */
function lankBrandPrefix($rawBrand, $name) {
    $brand = trim((string) $rawBrand);
    if ($brand === '') return '';
    if (in_array(strtoupper($brand), BRAND_NO_PREFIX, true)) return '';
    $brandNorm = lankNormalizeManufacturer($brand);
    // Si el nombre ya empieza por la marca (case-insensitive), no la duplicamos
    $firstWord = strtoupper(trim(explode(' ', trim($name))[0] ?? ''));
    if ($firstWord !== '' && $firstWord === strtoupper($brandNorm)) return '';
    if (mb_stripos(trim($name), $brandNorm . ' ', 0, 'UTF-8') === 0) return '';
    return $brandNorm . ' ';
}

/** Antepone el prefijo de marca a $name y trunca al máximo de varchar(80). */
function lankApplyBrandPrefix($prefix, $name) {
    if ($name === null || $name === '') return $prefix !== '' ? rtrim($prefix) : '';
    return mb_substr($prefix . $name, 0, PRODUCT_NAME_MAX, 'UTF-8');
}

function resolveManufacturer($mysqli, $rawName, &$cache, &$createdLog, $dryRun) {
    $key = strtoupper(trim($rawName));
    if ($key === '') return null;
    if (isset($cache[$key])) return $cache[$key];
    $display = lankNormalizeManufacturer($rawName);
    $qDisplay = $mysqli->real_escape_string($display);
    $r = $mysqli->query("SELECT manufacturers_id FROM manufacturers WHERE UPPER(TRIM(manufacturers_name))=UPPER('$qDisplay') LIMIT 1");
    if ($r && $row = $r->fetch_assoc()) { $cache[$key] = (int) $row['manufacturers_id']; return $cache[$key]; }
    $qkey = $mysqli->real_escape_string($key);
    $r = $mysqli->query("SELECT manufacturers_id FROM manufacturers WHERE UPPER(TRIM(manufacturers_name))=\"$qkey\" LIMIT 1");
    if ($r && $row = $r->fetch_assoc()) { $cache[$key] = (int) $row['manufacturers_id']; return $cache[$key]; }
    if ($dryRun) { $cache[$key] = 0; $createdLog[$key] = $display; return 0; }
    $mysqli->query("INSERT INTO manufacturers (manufacturers_name, date_added) VALUES (\"$qDisplay\", NOW())");
    $id = (int) $mysqli->insert_id;
    $mysqli->query("INSERT INTO manufacturers_info (manufacturers_id, languages_id, manufacturers_url) VALUES ($id, " . LANG_ID_ES . ", \"\")");
    $mysqli->query("INSERT INTO manufacturers_info (manufacturers_id, languages_id, manufacturers_url) VALUES ($id, " . LANG_ID_EN . ", \"\")");
    $cache[$key] = $id;
    $createdLog[$key] = $display . " (id=$id)";
    return $id;
}

/** Mapa de duplicados — FILTRADO por origin Lankhorst (fix 2026-05-11).
 *  Lankhorst distribuye 89 marcas distintas (Lewmar, Petzl, BEP, etc.), así que aquí no podemos
 *  filtrar por un único manufacturer. Filtro: products_import_origin LIKE 'lankhorst%'.
 *  SKUs en BD con otros origin no bloquean el alta. EAN sigue siendo GLOBAL (GS1). */
function buildExistingMap($mysqli) {
    $existing = [];
    $f = "p.products_import_origin LIKE 'lankhorst%'";
    $r = $mysqli->query("SELECT LOWER(p.products_model) m FROM products p WHERE p.products_model<>'' AND p.products_model IS NOT NULL AND $f");
    while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
    $r = $mysqli->query("SELECT LOWER(p.reference_prov) m FROM products p WHERE p.reference_prov<>'' AND p.reference_prov IS NOT NULL AND $f");
    while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
    $r = $mysqli->query("SELECT LOWER(pa.reference) m FROM products_attributes pa INNER JOIN products p ON p.products_id=pa.products_id WHERE pa.reference<>'' AND pa.reference IS NOT NULL AND $f");
    while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
    $r = $mysqli->query("SELECT LOWER(pa.reference_prov) m FROM products_attributes pa INNER JOIN products p ON p.products_id=pa.products_id WHERE pa.reference_prov<>'' AND pa.reference_prov IS NOT NULL AND $f");
    while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
    // EAN global (GS1)
    $r = $mysqli->query("SELECT LOWER(product_ean) m FROM products WHERE product_ean<>'' AND product_ean IS NOT NULL");
    while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
    $r = $mysqli->query("SELECT LOWER(products_attributes_ean) m FROM products_attributes WHERE products_attributes_ean<>'' AND products_attributes_ean IS NOT NULL");
    while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
    // Lista negra de reimportación: trata como "ya existentes" los códigos/EAN de productos borrados a propósito.
    require_once dirname(__FILE__) . '/includes/import_blacklist.php';
    $existing += fb_blacklist_keys();
    return $existing;
}

/** Valida que el HTML maquetado tiene estructura mínima (al menos un <p>, <ul> o <h3>). */
function lankFormatLooksValid($html, $minLenInput) {
    if (!is_string($html) || trim($html) === '') return false;
    $low = strtolower($html);
    $hasStruct = (strpos($low, '<p>') !== false) || (strpos($low, '<ul>') !== false) || (strpos($low, '<h3>') !== false);
    if (!$hasStruct) return false;
    // Anti-truncado: la salida no puede ser <40% del input (en chars sin tags) — el LLM perdió info.
    $plainOut = strip_tags($html);
    $plainOutLen = mb_strlen(trim($plainOut), 'UTF-8');
    if ($minLenInput > 200 && $plainOutLen < $minLenInput * 0.4) return false;
    return true;
}

function llmCall($systemPrompt, $userText, $maxTokens = 1500, $maxRetries = 2) {
    if (trim((string) $userText) === '') return '';
    $payload = json_encode([
        'model' => LLM_MODEL,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userText],
        ],
        'temperature' => 0.2,
        'max_tokens' => $maxTokens,
        'chat_template_kwargs' => ['enable_thinking' => false],
    ], JSON_UNESCAPED_UNICODE);
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

function downloadImage($url, $destAbs) {
    if (empty($url)) return false;
    $url = trim($url);
    // Reject obvious garbage (commas indicate URL list, spaces are invalid)
    if (strpos($url, ',') !== false || strpos($url, ' ') !== false) return false;
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
            'Accept-Language: en-US,en;q=0.9',
            'Referer: https://portal.lankhorst-taselaar.com/',
        ],
    ]);
    $ok = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);
    fclose($fp);
    // Reject placeholders ("no image" CDN devuelve ~1.7 KB) y respuestas vacías
    $ok = $ok && $code === 200 && filesize($destAbs) >= IMG_MIN_BYTES;
    if (!$ok) @unlink($destAbs);
    return $ok;
}

/** Descarga la lista de URLs candidatas a temporales únicos, deduplicando.
 *  Devuelve array de paths absolutos descargados con éxito (>= IMG_MIN_BYTES). */
function downloadImagesToTmp(array $urls, $maxImages) {
    $seen = [];
    $tmpFiles = [];
    foreach ($urls as $url) {
        if (count($tmpFiles) >= $maxImages) break;
        $url = trim($url);
        if ($url === '' || isset($seen[$url])) continue;
        $seen[$url] = true;
        $tmpAbs = IMG_ABS_DIR . 'lank-tmp-' . uniqid('', true) . '.jpg';
        if (downloadImage($url, $tmpAbs)) {
            $tmpFiles[] = $tmpAbs;
        }
    }
    return $tmpFiles;
}

/** Devuelve la lista única de URLs candidatas para un row, en orden de preferencia.
 *  Soporta col J con múltiples URLs separadas por coma. */
function lankImageUrls($rowImg, $webImg) {
    $out = [];
    foreach (explode(',', (string) $rowImg) as $u) {
        $u = trim($u);
        if ($u !== '') $out[] = $u;
    }
    if ($webImg !== '') $out[] = trim($webImg);
    // Dedup conservando orden
    $seen = []; $unique = [];
    foreach ($out as $u) {
        if (!isset($seen[$u])) { $seen[$u] = true; $unique[] = $u; }
    }
    return $unique;
}

function calcG1Price($price, $cost) {
    $price = (float) $price;
    $cost = (float) $cost;
    if ($price <= 0) return 0.0;
    $mult = 0.90;
    if ($cost > 0) {
        $margin = ($price - $cost) / $price;
        if      ($margin >= 0.45) $mult = 0.75;
        elseif  ($margin >= 0.40) $mult = 0.80;
        elseif  ($margin >= 0.35) $mult = 0.82;
        elseif  ($margin >= 0.30) $mult = 0.85;
    }
    $tier  = $price * $mult;
    $floor = $cost * G1_FLOOR_FACTOR;
    return round(max($tier, $floor), 4);
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

function longestCommonSuffix(array $strs) {
    if (empty($strs)) return '';
    $strs = array_values($strs);
    $suffix = $strs[0];
    $suffixLen = mb_strlen($suffix, 'UTF-8');
    foreach ($strs as $s) {
        $sLen = mb_strlen($s, 'UTF-8');
        while ($suffixLen > 0 && mb_substr($s, $sLen - $suffixLen, $suffixLen, 'UTF-8') !== $suffix) {
            $suffixLen--;
            $suffix = mb_substr($suffix, 1, null, 'UTF-8');
        }
        if ($suffixLen === 0) return '';
    }
    return $suffix;
}

function longestCommonPrefix(array $strs) {
    if (empty($strs)) return '';
    $strs = array_values($strs);
    $prefix = $strs[0];
    $prefixLen = mb_strlen($prefix, 'UTF-8');
    foreach ($strs as $s) {
        while ($prefixLen > 0 && mb_substr($s, 0, $prefixLen, 'UTF-8') !== $prefix) {
            $prefixLen--;
            $prefix = mb_substr($prefix, 0, $prefixLen, 'UTF-8');
        }
        if ($prefixLen === 0) return '';
    }
    return $prefix;
}

function lankNormalizeMeasure($s) {
    $s = trim((string) $s);
    if ($s === '') return '';
    // Normaliza "20 MM" / "20mm" / "20 mm" → "20 mm"
    $s = preg_replace_callback('/(\d+(?:[.,]\d+)?)\s*(mm|cm|m|kg|g|lt|l|ml|in|inch|"|hp|w|v|a|ah|hz|°|oz)\b/iu', function($m){
        return $m[1] . ' ' . strtolower($m[2]);
    }, $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
}

function lankExtractMeasure($title) {
    $title = (string) $title;
    // Talla SZ\d+ / Size \d+ / Talla \d+ (común en Lewmar)
    if (preg_match('/\b(SZ|Size|Talla)\s*(\d+)\b/iu', $title, $m)) {
        return 'SZ ' . $m[2];
    }
    if (preg_match('/(?:Ø|diameter|diam\.?)\s*(\d+(?:[.,]\d+)?)\s*(mm|cm)?/iu', $title, $m)) {
        return 'Ø ' . $m[1] . ' ' . (isset($m[2]) && $m[2] !== '' ? strtolower($m[2]) : 'mm');
    }
    if (preg_match('/(M?\d+(?:[.,]\d+)?)\s*[Xx]\s*(\d+(?:[.,]\d+)?)\b/u', $title, $m)) {
        return $m[1] . 'x' . $m[2];
    }
    if (preg_match('/(\d+(?:[.,]\d+)?\s*-\s*\d+(?:[.,]\d+)?)\s*(kg|g|mm|cm|mt|m|l|ml|HP|W|V|A|Ah|AMP|Hz|°)\b/iu', $title, $m)) {
        return trim($m[1]) . ' ' . strtolower($m[2]);
    }
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*(Ah|AMP|kg|g|mm|cm|mt|m|lt|l|ml|in|inch|"|HP|W|V|A|Hz|°|fl\s*oz|oz)\b/iu', $title, $m)) {
        return $m[1] . ' ' . strtolower($m[2]);
    }
    return '';
}

function findOrCreateOptionValue($mysqli, $nameEs, $nameEn = null) {
    if ($nameEn === null) $nameEn = $nameEs;
    $nameEsSafe = $mysqli->real_escape_string($nameEs);
    $q = $mysqli->query("SELECT pov.products_options_values_id
        FROM products_options_values pov
        INNER JOIN products_options_values_to_products_options pov2po ON pov2po.products_options_values_id = pov.products_options_values_id
        WHERE pov2po.products_options_id = " . VARIANT_OPTION_ID . "
          AND pov.language_id = " . LANG_ID_ES . "
          AND pov.products_options_values_name = '$nameEsSafe'
        LIMIT 1");
    if ($row = $q->fetch_assoc()) return (int) $row['products_options_values_id'];
    $nq = $mysqli->query("SELECT IFNULL(MAX(products_options_values_id), 0) + 1 AS nid FROM products_options_values");
    $nrow = $nq->fetch_assoc();
    $newId = (int) $nrow['nid'];
    $nameEnSafe = $mysqli->real_escape_string($nameEn);
    $mysqli->query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($newId, " . LANG_ID_ES . ", '$nameEsSafe', '')");
    $mysqli->query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($newId, " . LANG_ID_EN . ", '$nameEnSafe', '')");
    $mysqli->query("INSERT INTO products_options_values_to_products_options (products_options_id, products_options_values_id) VALUES (" . VARIANT_OPTION_ID . ", $newId)");
    return $newId;
}

function lankBrowserHeaders() {
    return [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.9',
        'Accept-Encoding: gzip, deflate, br',
        'Sec-Fetch-Dest: document',
        'Sec-Fetch-Mode: navigate',
        'Sec-Fetch-Site: none',
        'Sec-Fetch-User: ?1',
        'Upgrade-Insecure-Requests: 1',
    ];
}

function lankFetchProductHtml($code, $force = false) {
    $cachePath = LANK_CACHE_DIR . $code . '.html';
    if (!$force && file_exists($cachePath) && filesize($cachePath) > 5000) {
        $h = @file_get_contents($cachePath);
        if ($h !== false && strpos($h, 'pd-description') !== false) return $h;
    }
    if (!is_dir(LANK_CACHE_DIR)) @mkdir(LANK_CACHE_DIR, 0775, true);
    $url = LANK_WEB_BASE . rawurlencode($code) . '.html';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => WEB_HTTP_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER => lankBrowserHeaders(),
        CURLOPT_ENCODING => '',
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $body = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);
    if ($body === false || $http !== 200) return false;
    if (strpos($body, 'Cloudflare') !== false && strpos($body, 'pd-description') === false) return false;
    @file_put_contents($cachePath, $body);
    return $body;
}

function lankExtractMpn($html) {
    if (!is_string($html)) return '';
    if (preg_match('#data-th="MPN"\s*>\s*(.*?)\s*</div>#s', $html, $m)) {
        $v = trim(strip_tags($m[1]));
        $v = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return preg_replace('/\s+/', ' ', $v);
    }
    return '';
}

function lankExtractDescription($html) {
    if (!is_string($html)) return '';
    if (preg_match('#<div id="pd-description".*?<div class="description">(.*?)</div>#s', $html, $m)) {
        $raw = $m[1];
        // Modificador /u obligatorio: sin él, [\xC2\xA0] partía caracteres UTF-8 multi-byte
        // (® = \xC2\xAE) y los corrompía a "?".
        $raw = preg_replace('/[ \t\x{A0}]+/u', ' ', $raw);
        return cleanHtmlAggressive($raw);
    }
    if (preg_match('#<meta\s+name="description"\s+content="([^"]+)"#i', $html, $m)) {
        return cleanHtmlAggressive($m[1]);
    }
    return '';
}

function lankExtractOgImage($html) {
    if (!is_string($html)) return '';
    if (preg_match('#<meta\s+property="og:image"\s+content="([^"]+)"#i', $html, $m)) {
        return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return '';
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

function loadXlsxRows($file) {
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file);
    $reader->setReadDataOnly(true);
    $ss = $reader->load($file);
    $sheet = $ss->getSheetByName('Sheet1') ?: $ss->getActiveSheet();
    $hi = $sheet->getHighestRow();
    $rows = [];
    for ($r = 2; $r <= $hi; $r++) {
        $rows[] = [
            'CODE'    => trim((string) $sheet->getCell('A' . $r)->getValue()),
            'DESC'    => trim((string) $sheet->getCell('B' . $r)->getValue()),
            'VP'      => trim((string) $sheet->getCell('C' . $r)->getValue()),
            'RRP'     => trim((string) $sheet->getCell('D' . $r)->getValue()),
            'WHOLE'   => trim((string) $sheet->getCell('F' . $r)->getValue()),
            'BRAND'   => trim((string) $sheet->getCell('G' . $r)->getValue()),
            'CATEG'   => trim((string) $sheet->getCell('H' . $r)->getValue()),
            'EAN'     => trim((string) $sheet->getCell('I' . $r)->getValue()),
            'IMG'     => trim((string) $sheet->getCell('J' . $r)->getValue()),
        ];
    }
    return $rows;
}

function listBrandsFromXlsx($file) {
    $rows = loadXlsxRows($file);
    $brands = [];
    foreach ($rows as $r) {
        if ($r['VP'] !== 'STK') continue;
        $b = $r['BRAND'];
        if ($b === '') $b = '(sin marca)';
        $brands[$b] = ($brands[$b] ?? 0) + 1;
    }
    // Orden alfabético (case-insensitive) por nombre de marca
    uksort($brands, fn($a, $b) => strcasecmp($a, $b));
    return $brands;
}

function isLegitimateLankFamily(array $items) {
    if (count($items) < 2) return false;
    $names = array_map(fn($r) => $r['DESC'], $items);
    // Si los nombres se repiten (algún par idéntico), no podemos diferenciar las variantes
    // con datos legibles → mejor tratarlos como sueltos. Caso real: Lewmar 76322080-83
    // tiene 4 SKUs y solo 2 nombres distintos en el xlsx (y en la web).
    if (count(array_unique($names)) < count($names)) return false;
    $lcp = longestCommonPrefix($names);
    $lcpLen = mb_strlen($lcp, 'UTF-8');
    $minLen = min(array_map(fn($s) => mb_strlen($s, 'UTF-8'), $names));
    if ($minLen <= 0) return false;

    // Anti-corte-en-medio-de-código: si el LCP termina en una "palabra" alfanumérica de
    // ≥3 chars Y los siguientes chars de cualquier SKU también son alfanuméricos →
    // probablemente estamos cortando un MPN compartido en medio (no es familia legítima).
    // Caso real: Lewmar 65568121/65567601/65568711/65568712 con LCP="299260" (6 chars de MPN)
    // mete un Shackle, un Toggle, un Footblock y un Jamming Foot como "variantes".
    // Excepción: si la última "palabra" del LCP tiene ≤2 chars (típicamente inicial de palabra
    // como "Headlamp A" donde "A" es el principio de Actik/Aria), permitir — son palabras
    // distintas, no un MPN partido.
    if ($lcpLen > 0) {
        $lastChar = mb_substr($lcp, -1, 1, 'UTF-8');
        if (preg_match('/[A-Za-z0-9]/u', $lastChar)) {
            $tokens = preg_split('/[\s\-_\.\/]+/', $lcp);
            $lastToken = end($tokens) ?: '';
            if (mb_strlen($lastToken, 'UTF-8') >= 3) {
                foreach ($names as $name) {
                    $next = mb_substr($name, $lcpLen, 1, 'UTF-8');
                    if ($next !== '' && preg_match('/[A-Za-z0-9]/u', $next)) return false;
                }
            }
        }
    }

    $ratio = $lcpLen / $minLen;
    if ($ratio < LCP_VARIANT_RATIO) return false;
    // Dispersión de precios: descarta agrupaciones de productos no relacionados.
    $costs = array_map(fn($r) => max($r['_COST'], 0.01), $items);
    if (max($costs) / min($costs) > PRICE_DISPERSION_MAX) return false;
    if ($ratio >= LCP_VARIANT_HIGH) return true;
    foreach ($names as $name) {
        $suffix = mb_substr($name, $lcpLen, null, 'UTF-8');
        if (!preg_match('/\d/u', $suffix)) return false;
    }
    return true;
}

/** Variante de isLegitimateLankFamily que usa el SUFIJO COMÚN (LCS) en lugar del prefijo.
 *  Captura el caso donde el feed pone el MPN al INICIO del nombre (Lewmar
 *  "29471036BK SZ1 ENDSTOP 2CL BKT" / "29472036BK SZ2 ENDSTOP 2CL BKT" / ...).
 *  El LCP es bajo (sólo "2947", 4 chars) pero el LCS es alto (" ENDSTOP 2CL BKT", 16 chars). */
function isLegitimateLankFamilyByLcs(array $items) {
    if (count($items) < 2) return false;
    $names = array_map(fn($r) => $r['DESC'], $items);
    if (count(array_unique($names)) < count($names)) return false;
    $lcs = longestCommonSuffix($names);
    $lcsLen = mb_strlen($lcs, 'UTF-8');
    $minLen = min(array_map(fn($s) => mb_strlen($s, 'UTF-8'), $names));
    if ($minLen <= 0) return false;

    // Anti-corte-en-medio-de-código (simétrico al LCP): si el LCS empieza con una palabra
    // alfanumérica de ≥3 chars Y los chars previos de algún SKU son alfanuméricos →
    // estamos cortando un MPN compartido en medio. Excepción: token corto (≤2 chars).
    if ($lcsLen > 0) {
        $firstChar = mb_substr($lcs, 0, 1, 'UTF-8');
        if (preg_match('/[A-Za-z0-9]/u', $firstChar)) {
            $tokens = preg_split('/[\s\-_\.\/]+/', $lcs);
            $firstToken = $tokens[0] ?? '';
            if (mb_strlen($firstToken, 'UTF-8') >= 3) {
                foreach ($names as $name) {
                    $sLen = mb_strlen($name, 'UTF-8');
                    $prev = mb_substr($name, $sLen - $lcsLen - 1, 1, 'UTF-8');
                    if ($prev !== '' && preg_match('/[A-Za-z0-9]/u', $prev)) return false;
                }
            }
        }
    }

    $ratio = $lcsLen / $minLen;
    if ($ratio < LCP_VARIANT_RATIO) return false;
    $costs = array_map(fn($r) => max($r['_COST'], 0.01), $items);
    if (max($costs) / min($costs) > PRICE_DISPERSION_MAX) return false;
    if ($ratio >= LCP_VARIANT_HIGH) return true;
    // Zona gris: cada prefijo (lo de antes del LCS) debe contener al menos un dígito
    foreach ($names as $name) {
        $prefix = mb_substr($name, 0, mb_strlen($name, 'UTF-8') - $lcsLen, 'UTF-8');
        if (!preg_match('/\d/u', $prefix)) return false;
    }
    return true;
}

/** Redondea un PVP NETO de modo que el precio CON IVA quede en múltiplo de 0.05.
 *  El cliente ve el precio con IVA (21%); ése es el que debe acabar en .X0/.X5.
 *  Ej con IVA 21%: 5.57 neto → 6.7397 con IVA → 6.75 → 5.5785 neto.
 *       102.11 neto → 123.55 con IVA → 102.1074 neto.
 *       14.53 neto → 17.60 con IVA → 14.5455 neto.
 */
const LANK_VAT_RATE = 0.21;
function roundToNickel($net) {
    $withIva = ((float) $net) * (1 + LANK_VAT_RATE);
    $roundedWithIva = round($withIva * 20) / 20;
    return round($roundedWithIva / (1 + LANK_VAT_RATE), 4);
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
    <h2>Importador Lankhorst — <?php echo $dryRun ? 'DRY-RUN (sin cambios)' : 'EJECUCIÓN REAL'; ?></h2>
    <p>
        <a href="<?php echo tep_href_link('import-lankhorst-altas.php'); ?>" class="xbutton small hv9">← Volver</a>
    </p>
    <div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n";
@flush();

// Cuando hay codes específicos, ignoramos $max (el usuario ya es explícito sobre qué importar)
if (!empty($onlyCodes) && $max > 0) {
    $maxOverridden = $max;
    $max = 0;
}

logMsg("Modo: " . ($dryRun ? "dry-run" : "EXECUTE")
    . " | brand=" . ($selectedBrand === 'all' ? 'TODAS' : $selectedBrand)
    . (!empty($onlyCodes) ? " | codes=" . count($onlyCodes) . " (" . implode(',', array_slice(array_keys($onlyCodes), 0, 5)) . (count($onlyCodes) > 5 ? '…' : '') . ")" : "")
    . ($skipTranslation ? " | sin traducción LLM" : "")
    . ($skipVariants    ? " | sin variantes"      : "")
    . ($skipWebEnrich   ? " | sin scraping web (sin MPN ni descripción)" : "")
    . (isset($maxOverridden) ? " | max=$maxOverridden IGNORADO (importación por codes)" : ($max > 0 ? " | max=$max" : "")));

$xlsx = findNewestXlsx(LANK_DIR);
if (!$xlsx) { logMsg("ERROR: no hay xlsx en " . LANK_DIR); goto end_action; }
logMsg("xlsx: " . basename($xlsx) . " (" . round(filesize($xlsx)/1024) . " KB, mtime " . date('Y-m-d H:i', filemtime($xlsx)) . ")");

logMsg("Leyendo xlsx…");
$rows = loadXlsxRows($xlsx);
logMsg("Filas leídas: " . count($rows));

// Si hay codes específicos, deducimos automáticamente las marcas
// y forzamos el agrupado de variantes en cada marca implicada.
if (!empty($onlyCodes)) {
    $brandsFromCodes = [];
    $codesFound = [];
    foreach ($rows as $row) {
        if (isset($onlyCodes[$row['CODE']])) {
            $brandsFromCodes[strtoupper($row['BRAND'] !== '' ? $row['BRAND'] : 'Generico')] = $row['BRAND'] !== '' ? $row['BRAND'] : 'Generico';
            $codesFound[$row['CODE']] = true;
        }
    }
    $missing = array_diff_key($onlyCodes, $codesFound);
    if (!empty($missing)) {
        logMsg("AVISO: codes no encontrados en xlsx: " . implode(', ', array_keys($missing)));
    }
    if (empty($brandsFromCodes)) {
        logMsg("ERROR: ningún code de la lista existe en el xlsx. Abortando.");
        goto end_action;
    }
    logMsg("Codes detectados en xlsx: " . count($codesFound) . " | marcas implicadas: " . implode(', ', $brandsFromCodes));
    // Sobreescribimos brand selector: el filtro por brand se hace luego permitiendo cualquiera de las marcas implicadas
    if (count($brandsFromCodes) === 1) {
        $selectedBrand = reset($brandsFromCodes);
    } else {
        // Múltiples marcas → desactivar filtro single-brand pero guardar lista
        $selectedBrand = 'all';
    }
}

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_error) { logMsg("ERROR DB: " . $mysqli->connect_error); goto end_action; }
$mysqli->set_charset('utf8');

if (!$dryRun && !is_dir(IMG_ABS_DIR)) { @mkdir(IMG_ABS_DIR, 0775, true); }
if (!is_dir(LANK_CACHE_DIR)) { @mkdir(LANK_CACHE_DIR, 0775, true); }

logMsg("Cargando IDs existentes en BD…");
$existing = buildExistingMap($mysqli);
logMsg("  → " . count($existing) . " referencias ya en BD");

$candidates = [];
$skipExist = $skipNoCode = $skipNoName = $skipBadPrice = $skipNotStk = $skipBrand = 0;
$selBrandUp = strtoupper($selectedBrand);
$brandsFromCodesUp = [];
if (!empty($onlyCodes) && isset($brandsFromCodes)) {
    foreach ($brandsFromCodes as $upKey => $_) { $brandsFromCodesUp[$upKey] = true; }
}
foreach ($rows as $row) {
    $code = $row['CODE'];
    if ($code === '') { $skipNoCode++; continue; }
    if ($row['DESC'] === '') { $skipNoName++; continue; }
    if ($row['VP'] !== 'STK') { $skipNotStk++; continue; }
    // Si hay codes específicos con múltiples marcas, filtrar por esas marcas
    if (!empty($brandsFromCodesUp)) {
        if (!isset($brandsFromCodesUp[strtoupper($row['BRAND'] !== '' ? $row['BRAND'] : 'Generico')])) { $skipBrand++; continue; }
    } elseif ($selectedBrand !== 'all') {
        if (strtoupper($row['BRAND']) !== $selBrandUp) { $skipBrand++; continue; }
    }
    if (isset($existing[strtolower($code)])) { $skipExist++; continue; }
    if ($row['EAN'] !== '' && isset($existing[strtolower($row['EAN'])])) { $skipExist++; continue; }

    $cost  = lankParseNum($row['WHOLE']);
    $price = lankParseNum($row['RRP']);
    if ($cost === null || $cost <= 0) { $skipBadPrice++; continue; }
    if ($price === null || $price <= 0) { $price = $cost * 2.0; }
    if ($price < $cost) { $price = $cost * 2.0; }
    $row['_COST']  = round($cost, 4);
    $row['_PRICE'] = roundToNickel($price);
    $row['_G1']    = roundToNickel(calcG1Price($row['_PRICE'], $cost));
    $row['_WEIGHT'] = 1.0;
    $candidates[$code] = $row;
}
logMsg("Candidatos tras pre-filtro: " . count($candidates));
logMsg("  pre-skip: existentes=$skipExist | sin code=$skipNoCode | sin nombre=$skipNoName | precio=$skipBadPrice | no-STK=$skipNotStk | brand-filter=$skipBrand");

// Agrupación por brand + LCP del nombre
$families = [];
$standalone = [];
$useVariantGrouping = !$skipVariants && ($selectedBrand !== 'all' || !empty($onlyCodes));
if (!$useVariantGrouping) {
    if ($selectedBrand === 'all' && !$skipVariants && empty($onlyCodes)) {
        logMsg("NOTA: en modo TODAS las marcas no agrupo variantes (la detección por LCP requiere subset coherente). Pasarán todos como sueltos.");
    }
    $standalone = $candidates;
} else {
    // Cuando hay codes específicos con varias marcas, agrupamos por brand y aplicamos LCP por separado
    $groupsByBrand = [];
    if (!empty($onlyCodes) && $selectedBrand === 'all') {
        foreach ($candidates as $code => $row) {
            $brandKey = strtoupper($row['BRAND'] !== '' ? $row['BRAND'] : 'Generico');
            $groupsByBrand[$brandKey][$code] = $row;
        }
    } else {
        $groupsByBrand['_ALL_'] = $candidates;
    }

    $famVar = $famSplit = 0;
    foreach ($groupsByBrand as $brandCandidates) {
        // Buckets por LCP iterativo (ordenando por nombre para que LCPs largos queden contiguos)
        $byKey = $brandCandidates;
        uasort($byKey, fn($a, $b) => strcmp($a['DESC'], $b['DESC']));
        $bucket = [];
        $allBuckets = [];
        foreach ($byKey as $code => $row) {
            if (empty($bucket)) { $bucket[$code] = $row; continue; }
            $newLcp = longestCommonPrefix(array_merge(array_map(fn($r)=>$r['DESC'], $bucket), [$row['DESC']]));
            $minNameLen = min(array_map(fn($r) => mb_strlen($r['DESC'], 'UTF-8'), array_merge($bucket, ['_'=>$row])));
            if ($minNameLen > 0 && mb_strlen($newLcp, 'UTF-8') / $minNameLen >= LCP_VARIANT_RATIO) {
                $bucket[$code] = $row;
            } else {
                $allBuckets[] = $bucket;
                $bucket = [$code => $row];
            }
        }
        if (!empty($bucket)) $allBuckets[] = $bucket;

        $thisBrandStandalone = [];
        foreach ($allBuckets as $b) {
            if (count($b) === 1) {
                foreach ($b as $code => $r) $thisBrandStandalone[$code] = $r;
                continue;
            }
            if (isLegitimateLankFamily($b)) {
                $key = key($b); // primer code como id de familia
                $families[$key] = $b;
                $famVar++;
            } else {
                foreach ($b as $code => $r) $thisBrandStandalone[$code] = $r;
                $famSplit++;
            }
        }

        // Segunda pasada: agrupar por SUFIJO COMÚN (LCS) los que quedaron sueltos.
        // Captura el caso "MPN distinto al inicio + sufijo descriptivo idéntico" (Lewmar SZ1/SZ2/SZ3).
        $byKeyRev = $thisBrandStandalone;
        uasort($byKeyRev, fn($a, $b) => strcmp(strrev($a['DESC']), strrev($b['DESC'])));
        $bucketRev = [];
        $allBucketsRev = [];
        foreach ($byKeyRev as $code => $row) {
            if (empty($bucketRev)) { $bucketRev[$code] = $row; continue; }
            $newLcs = longestCommonSuffix(array_merge(array_map(fn($r)=>$r['DESC'], $bucketRev), [$row['DESC']]));
            $minNameLen = min(array_map(fn($r) => mb_strlen($r['DESC'], 'UTF-8'), array_merge($bucketRev, ['_'=>$row])));
            if ($minNameLen > 0 && mb_strlen($newLcs, 'UTF-8') / $minNameLen >= LCP_VARIANT_RATIO) {
                $bucketRev[$code] = $row;
            } else {
                $allBucketsRev[] = $bucketRev;
                $bucketRev = [$code => $row];
            }
        }
        if (!empty($bucketRev)) $allBucketsRev[] = $bucketRev;

        foreach ($allBucketsRev as $b) {
            if (count($b) === 1) {
                foreach ($b as $code => $r) $standalone[$code] = $r;
                continue;
            }
            if (isLegitimateLankFamilyByLcs($b)) {
                $key = key($b);
                $families[$key] = $b;
                $famVar++;
            } else {
                foreach ($b as $code => $r) $standalone[$code] = $r;
                $famSplit++;
            }
        }
    }
    logMsg("Agrupación: $famVar familias multi-variante (LCP + LCS) | $famSplit grupos divididos en sueltos");
}

// Si hay codes específicos, filtrar familias/sueltos a SOLO los que contienen al menos un code pedido.
// Las familias completas se conservan: aunque pidas un solo code, si es variante de una familia se importan todas las variantes.
if (!empty($onlyCodes)) {
    $famFiltered = [];
    foreach ($families as $famKey => $items) {
        if (!empty(array_intersect_key($items, $onlyCodes))) {
            $famFiltered[$famKey] = $items;
        }
    }
    $stdFiltered = [];
    foreach ($standalone as $code => $row) {
        if (isset($onlyCodes[$code])) $stdFiltered[$code] = $row;
    }
    $families = $famFiltered;
    $standalone = $stdFiltered;
    logMsg("Filtro por codes específicos: quedan " . count($families) . " familias + " . count($standalone) . " sueltos");
}
logMsg("Tras consolidar: " . count($families) . " familias + " . count($standalone) . " sueltos");

$mfgCache = [];
$mfgCreated = [];
$nInserted = $nFamiliesIns = $nStandaloneIns = 0;
$nWithVar = $nWithImg = $imgFail = $imgEmpty = $translateFail = $errors = $skippedNoImg = $skippedNoMpn = 0;
$webFails = 0;
$formatFail = 0;
$nSubImgTotal = 0;

function processWebEnrich($code, $skipWebEnrich, &$webFails) {
    if ($skipWebEnrich) return ['mpn' => '', 'desc' => '', 'img' => ''];
    $html = lankFetchProductHtml($code);
    if ($html === false) { $webFails++; return ['mpn' => '', 'desc' => '', 'img' => '']; }
    return [
        'mpn'  => lankExtractMpn($html),
        'desc' => lankExtractDescription($html),
        'img'  => lankExtractOgImage($html),
    ];
}

// ---- 1) Familias con variantes ----
foreach ($families as $famKey => $items) {
    if ($max > 0 && $nInserted >= $max) { logMsg("Alcanzado límite max=$max, parando."); break; }

    uasort($items, fn($a, $b) => $a['_COST'] <=> $b['_COST']);
    $cheapestCode = array_key_first($items);
    $cheap = $items[$cheapestCode];
    $brand = $cheap['BRAND'] !== '' ? $cheap['BRAND'] : 'Generico';
    $mfgId = resolveManufacturer($mysqli, $brand, $mfgCache, $mfgCreated, $dryRun);

    // Web enrichment para el padre
    $web = processWebEnrich($cheapestCode, $skipWebEnrich, $webFails);
    $mpn = $web['mpn'];
    $descEnRaw = $web['desc'];
    $webImg = $web['img'];

    $rawNameEn = $cheap['DESC'];
    $brandPrefix = lankBrandPrefix($brand, $rawNameEn);
    $titleEn = lankApplyBrandPrefix($brandPrefix, $rawNameEn);
    $descEn = $descEnRaw !== '' ? $descEnRaw : cleanHtmlAggressive($rawNameEn);

    $nameEs = $titleEn;
    $descEs = $descEn;
    if (!$skipTranslation && !$dryRun) {
        $tn = llmCall(LLM_PROMPT_NAME, $rawNameEn, 200);
        if ($tn !== '') $nameEs = lankApplyBrandPrefix($brandPrefix, $tn); else $translateFail++;
        if ($descEn !== '') {
            $td = llmCall(LLM_PROMPT_DESC, $descEn, 1500);
            if ($td !== '') $descEs = $td; else $translateFail++;
        }
        // Maquetado HTML estilo Cressi/Garmin (EN y ES)
        if ($descEn !== '') {
            $inLen = mb_strlen(strip_tags($descEn), 'UTF-8');
            $fmtEn = llmCall(LLM_FORMAT_PROMPT_EN, $descEn, 2500);
            if (lankFormatLooksValid($fmtEn, $inLen)) $descEn = $fmtEn; else $formatFail++;
        }
        if ($descEs !== '') {
            $inLen = mb_strlen(strip_tags($descEs), 'UTF-8');
            $fmtEs = llmCall(LLM_FORMAT_PROMPT_ES, $descEs, 2500);
            if (lankFormatLooksValid($fmtEs, $inLen)) $descEs = $fmtEs; else $formatFail++;
        }
    }

    if ($dryRun) {
        $nInserted++; $nFamiliesIns++;
        if ($nFamiliesIns <= 12) {
            $lcp = longestCommonPrefix(array_map(fn($r) => $r['DESC'], $items));
            logMsg(sprintf("  WOULD INSERT FAMILIA key=%s (%d variantes) cheap=%s %.2f€ mpn='%s' lcp='%s'",
                $famKey, count($items), $cheapestCode, $cheap['_COST'], $mpn,
                mb_substr($lcp, 0, 50, 'UTF-8')));
        }
        continue;
    }

    // Imágenes ANTES del INSERT — si no se descarga ninguna válida, skip el producto.
    $imageUrls = lankImageUrls($cheap['IMG'], $webImg);
    if (empty($imageUrls)) { $skippedNoImg++; logMsg("SKIP familia $famKey: sin URL de imagen"); continue; }
    if (!is_dir(IMG_ABS_DIR)) @mkdir(IMG_ABS_DIR, 0775, true);
    $tmpFiles = downloadImagesToTmp($imageUrls, MAX_SUBIMAGES + 1);
    if (empty($tmpFiles)) { $skippedNoImg++; $imgFail++; logMsg("SKIP familia $famKey: " . count($imageUrls) . " URL probadas, ninguna válida (placeholder o error)"); continue; }

    $mysqli->begin_transaction();
    try {
        $qmodel = $mysqli->real_escape_string($mpn !== '' ? $mpn : $cheapestCode);
        $qref   = $mysqli->real_escape_string($cheapestCode);
        $qean   = $mysqli->real_escape_string($cheap['EAN']);
        $qmfg   = (int) $mfgId;
        $sql = "INSERT INTO products (products_quantity, check_stock, products_model, products_image, products_price, products_cost, products_date_added, products_weight, products_status, products_tax_class_id, manufacturers_id, product_ean, reference_prov, products_import_origin)
            VALUES (0, 0, \"$qmodel\", \"\", " . number_format($cheap['_PRICE'], 4, '.', '') . ", " . number_format($cheap['_COST'], 4, '.', '') . ", NOW(), {$cheap['_WEIGHT']}, 2, " . TAX_CLASS_IVA21 . ", $qmfg, \"$qean\", \"$qref\", \"" . ORIGIN_FLAG . "\")";
        if (!$mysqli->query($sql)) throw new Exception("products: " . $mysqli->error);
        $pid = (int) $mysqli->insert_id;

        $qNameEs = $mysqli->real_escape_string($nameEs);
        $qDescEs = $mysqli->real_escape_string($descEs);
        $qNameEn = $mysqli->real_escape_string($titleEn);
        $qDescEn = $mysqli->real_escape_string($descEn);
        if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_ES . ", \"$qNameEs\", \"$qDescEs\", 0)")) throw new Exception("desc ES: " . $mysqli->error);
        if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_EN . ", \"$qNameEn\", \"$qDescEn\", 0)")) throw new Exception("desc EN: " . $mysqli->error);
        if (!$mysqli->query("INSERT INTO products_to_categories (products_id, categories_id) VALUES ($pid, " . NEW_CATEGORY_ID . ")")) throw new Exception("p2c: " . $mysqli->error);
        if (!$mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . G1_GROUP_ID . ", $pid, " . number_format($cheap['_G1'], 4, '.', '') . ", 1, 1)")) throw new Exception("g1: " . $mysqli->error);

        // Renombrar imágenes temporales: 1ª = products_image, resto = products_subimages
        $slug = lankSlugify($nameEs ?: $titleEn);
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

        // Etiquetas variantes: medida del title → resto tras LCP → resto antes del LCS → CODE
        $titlesAll = array_map(fn($it) => $it['DESC'], $items);
        $common = rtrim(longestCommonPrefix($titlesAll), " -–·,");
        $commonSuf = ltrim(longestCommonSuffix($titlesAll), " -–·,");
        $lcpStrip = function ($name, $common) {
            if ($common === '' || mb_strpos($name, $common) !== 0) return '';
            $rest = trim(mb_substr($name, mb_strlen($common, 'UTF-8'), null, 'UTF-8'));
            $rest = ltrim($rest, " -–·,;");
            return (mb_strlen($rest, 'UTF-8') <= 64) ? $rest : '';
        };
        $lcsStrip = function ($name, $commonSuf) {
            if ($commonSuf === '') return '';
            $sLen = mb_strlen($name, 'UTF-8');
            $cLen = mb_strlen($commonSuf, 'UTF-8');
            if ($sLen <= $cLen) return '';
            if (mb_substr($name, -$cLen, $cLen, 'UTF-8') !== $commonSuf) return '';
            $rest = trim(mb_substr($name, 0, $sLen - $cLen, 'UTF-8'));
            $rest = rtrim($rest, " -–·,;");
            // Quitar MPN inicial si parece código alfanumérico largo (≥6 chars sin espacios)
            if (preg_match('/^[A-Za-z0-9]{6,}\s+(.+)$/', $rest, $m)) $rest = $m[1];
            return (mb_strlen($rest, 'UTF-8') <= 64) ? $rest : '';
        };
        $sigs = [];
        foreach ($items as $code => $it) {
            $sigs[$code] = [
                'measure' => lankNormalizeMeasure(lankExtractMeasure($it['DESC'])),
                'lcp'     => lankNormalizeMeasure($lcpStrip($it['DESC'], $common)),
                'lcs'     => lankNormalizeMeasure($lcsStrip($it['DESC'], $commonSuf)),
            ];
        }
        $labels = [];
        foreach (['measure', 'lcs', 'lcp'] as $signal) {
            $vals = array_map(fn($c) => $c[$signal], $sigs);
            $nonEmpty = array_filter($vals, fn($v) => $v !== '');
            if (count($nonEmpty) === count($vals) && count(array_unique($nonEmpty)) === count($vals)) {
                $labels = $vals;
                break;
            }
        }
        foreach ($items as $code => $it) {
            if (empty($labels[$code])) $labels[$code] = $code;
            $labels[$code] = mb_substr($labels[$code], 0, 64, 'UTF-8');
        }

        $variantsCreated = 0;
        foreach ($items as $code => $it) {
            $delta   = round($it['_PRICE'] - $cheap['_PRICE'], 4);
            $prefix  = $delta < 0 ? '-' : '+';
            $valueId = findOrCreateOptionValue($mysqli, $labels[$code]);
            // MPN scrapeado por variante (reference=Modelo). Cae al MPN del padre o al Code si falla.
            $variantMpn = '';
            if (!$skipWebEnrich) {
                if ($code === $cheapestCode) {
                    $variantMpn = $mpn;
                } else {
                    $vHtml = lankFetchProductHtml($code);
                    if ($vHtml !== false) $variantMpn = lankExtractMpn($vHtml);
                }
            }
            if ($variantMpn === '') $variantMpn = $code;
            $qrefv   = $mysqli->real_escape_string($variantMpn);
            $qprovv  = $mysqli->real_escape_string($code);
            $qveanv  = $mysqli->real_escape_string($it['EAN']);
            // Peso variante = DELTA sobre el padre (shopping_cart.php suma con prefix +/-)
            $weightDelta = round($it['_WEIGHT'] - $cheap['_WEIGHT'], 3);
            $weightPrefix = $weightDelta < 0 ? '-' : '+';
            $weightAbs = abs($weightDelta);
            if (!$mysqli->query("INSERT INTO products_attributes SET
                products_id=$pid, options_id=" . VARIANT_OPTION_ID . ", options_values_id=$valueId,
                options_values_price=" . number_format(abs($delta), 4, '.', '') . ", price_prefix='$prefix',
                reference='$qrefv', reference_prov='$qprovv', products_attributes_ean='$qveanv',
                options_values_weight=" . number_format($weightAbs, 3, '.', '') . ", weight_prefix='$weightPrefix'"))
                throw new Exception("attr: " . $mysqli->error);
            $paId = (int) $mysqli->insert_id;
            $variantsCreated++;

            $g1Delta = round($it['_G1'] - $cheap['_G1'], 4);
            $g1Prefix = $g1Delta < 0 ? '-' : '+';
            if (!$mysqli->query("INSERT INTO products_attributes_groups (products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id, options_values_weight, weight_prefix) VALUES ($paId, " . G1_GROUP_ID . ", " . number_format(abs($g1Delta), 4, '.', '') . ", '$g1Prefix', $pid, 0, '+')"))
                throw new Exception("attr_groups: " . $mysqli->error);
        }
        if ($variantsCreated > 0) $nWithVar++;

        $mysqli->commit();

        // EAN interno si el del feed no es válido
        if (!isValidEan13($cheap['EAN'])) {
            $genEan = generateInternalEan13($pid, EAN_INTERNAL_PREFIX);
            if ($genEan !== '') {
                $mysqli->query("UPDATE products SET product_ean='" . $mysqli->real_escape_string($genEan) . "' WHERE products_id=$pid AND (product_ean IS NULL OR product_ean='' OR LENGTH(product_ean)<>13)");
            }
        }

        $nInserted++; $nFamiliesIns++;
        $nSubsHere = isset($imgFinalNames) ? count($imgFinalNames) : 0;
        logMsg(sprintf("OK FAMILIA pid=%d cheap=%s [%d variantes] mpn='%s' price=%.2f cost=%.2f g1=%.2f imgs=%d",
            $pid, $cheapestCode, $variantsCreated, $mpn,
            $cheap['_PRICE'], $cheap['_COST'], $cheap['_G1'], 1 + $nSubsHere));
    } catch (Exception $e) {
        $mysqli->rollback();
        $errors++;
        // Cleanup imágenes temporales que no se hayan renombrado todavía
        foreach ($tmpFiles as $tmpAbs) { if (file_exists($tmpAbs)) @unlink($tmpAbs); }
        logMsg("ERROR familia $famKey: " . $e->getMessage());
    }
}

// ---- 2) Sueltos ----
foreach ($standalone as $code => $row) {
    if ($max > 0 && $nInserted >= $max) { logMsg("Alcanzado límite max=$max, parando."); break; }

    $brand = $row['BRAND'] !== '' ? $row['BRAND'] : 'Generico';
    $mfgId = resolveManufacturer($mysqli, $brand, $mfgCache, $mfgCreated, $dryRun);

    $web = processWebEnrich($code, $skipWebEnrich, $webFails);
    $mpn = $web['mpn'];
    $descEnRaw = $web['desc'];
    $webImg = $web['img'];

    $rawNameEn = $row['DESC'];
    $brandPrefix = lankBrandPrefix($brand, $rawNameEn);
    $titleEn = lankApplyBrandPrefix($brandPrefix, $rawNameEn);
    $descEn = $descEnRaw !== '' ? $descEnRaw : cleanHtmlAggressive($rawNameEn);

    $nameEs = $titleEn;
    $descEs = $descEn;
    if (!$skipTranslation && !$dryRun) {
        $tn = llmCall(LLM_PROMPT_NAME, $rawNameEn, 200);
        if ($tn !== '') $nameEs = lankApplyBrandPrefix($brandPrefix, $tn); else $translateFail++;
        if ($descEn !== '') {
            $td = llmCall(LLM_PROMPT_DESC, $descEn, 1500);
            if ($td !== '') $descEs = $td; else $translateFail++;
        }
        // Maquetado HTML estilo Cressi/Garmin (EN y ES)
        if ($descEn !== '') {
            $inLen = mb_strlen(strip_tags($descEn), 'UTF-8');
            $fmtEn = llmCall(LLM_FORMAT_PROMPT_EN, $descEn, 2500);
            if (lankFormatLooksValid($fmtEn, $inLen)) $descEn = $fmtEn; else $formatFail++;
        }
        if ($descEs !== '') {
            $inLen = mb_strlen(strip_tags($descEs), 'UTF-8');
            $fmtEs = llmCall(LLM_FORMAT_PROMPT_ES, $descEs, 2500);
            if (lankFormatLooksValid($fmtEs, $inLen)) $descEs = $fmtEs; else $formatFail++;
        }
    }

    if ($dryRun) {
        $nInserted++; $nStandaloneIns++;
        if ($nStandaloneIns <= 8) {
            logMsg("  WOULD INSERT SUELTO code=$code mpn='$mpn' name='" . mb_substr($titleEn, 0, 60, 'UTF-8') . "' price={$row['_PRICE']} cost={$row['_COST']}");
        }
        continue;
    }

    // Imágenes ANTES del INSERT — si no se descarga ninguna válida, skip el producto.
    $imageUrls = lankImageUrls($row['IMG'], $webImg);
    if (empty($imageUrls)) { $skippedNoImg++; logMsg("SKIP $code: sin URL de imagen"); continue; }
    if (!is_dir(IMG_ABS_DIR)) @mkdir(IMG_ABS_DIR, 0775, true);
    $tmpFiles = downloadImagesToTmp($imageUrls, MAX_SUBIMAGES + 1);
    if (empty($tmpFiles)) { $skippedNoImg++; $imgFail++; logMsg("SKIP $code: " . count($imageUrls) . " URL probadas, ninguna válida (placeholder o error)"); continue; }

    $mysqli->begin_transaction();
    try {
        $qmodel = $mysqli->real_escape_string($mpn !== '' ? $mpn : $code);
        $qref   = $mysqli->real_escape_string($code);
        $qean   = $mysqli->real_escape_string($row['EAN']);
        $qmfg   = (int) $mfgId;
        $sql = "INSERT INTO products (products_quantity, check_stock, products_model, products_image, products_price, products_cost, products_date_added, products_weight, products_status, products_tax_class_id, manufacturers_id, product_ean, reference_prov, products_import_origin)
            VALUES (0, 0, \"$qmodel\", \"\", " . number_format($row['_PRICE'], 4, '.', '') . ", " . number_format($row['_COST'], 4, '.', '') . ", NOW(), {$row['_WEIGHT']}, 2, " . TAX_CLASS_IVA21 . ", $qmfg, \"$qean\", \"$qref\", \"" . ORIGIN_FLAG . "\")";
        if (!$mysqli->query($sql)) throw new Exception("products: " . $mysqli->error);
        $pid = (int) $mysqli->insert_id;

        $qNameEs = $mysqli->real_escape_string($nameEs);
        $qDescEs = $mysqli->real_escape_string($descEs);
        $qNameEn = $mysqli->real_escape_string($titleEn);
        $qDescEn = $mysqli->real_escape_string($descEn);
        if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_ES . ", \"$qNameEs\", \"$qDescEs\", 0)")) throw new Exception("desc ES: " . $mysqli->error);
        if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_EN . ", \"$qNameEn\", \"$qDescEn\", 0)")) throw new Exception("desc EN: " . $mysqli->error);
        if (!$mysqli->query("INSERT INTO products_to_categories (products_id, categories_id) VALUES ($pid, " . NEW_CATEGORY_ID . ")")) throw new Exception("p2c: " . $mysqli->error);
        if (!$mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . G1_GROUP_ID . ", $pid, " . number_format($row['_G1'], 4, '.', '') . ", 1, 1)")) throw new Exception("g1: " . $mysqli->error);

        $slug = lankSlugify($nameEs ?: $titleEn);
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

        if (!isValidEan13($row['EAN'])) {
            $genEan = generateInternalEan13($pid, EAN_INTERNAL_PREFIX);
            if ($genEan !== '') {
                $mysqli->query("UPDATE products SET product_ean='" . $mysqli->real_escape_string($genEan) . "' WHERE products_id=$pid AND (product_ean IS NULL OR product_ean='' OR LENGTH(product_ean)<>13)");
            }
        }

        $nInserted++; $nStandaloneIns++;
        $nSubsHere = isset($imgFinalNames) ? count($imgFinalNames) : 0;
        logMsg(sprintf("OK SUELTO pid=%d code=%s mpn='%s' price=%.2f cost=%.2f g1=%.2f imgs=%d",
            $pid, $code, $mpn, $row['_PRICE'], $row['_COST'], $row['_G1'], 1 + $nSubsHere));
    } catch (Exception $e) {
        $mysqli->rollback();
        $errors++;
        foreach ($tmpFiles as $tmpAbs) { if (file_exists($tmpAbs)) @unlink($tmpAbs); }
        logMsg("ERROR suelto $code: " . $e->getMessage());
    }
}

logMsg("==================== RESUMEN ====================");
logMsg("Insertados: $nInserted (familias=$nFamiliesIns sueltos=$nStandaloneIns)");
logMsg("Con imagen: $nWithImg (sub-imágenes totales: $nSubImgTotal) | sin URL: $imgEmpty | fallos descarga: $imgFail | skip por sin imagen: $skippedNoImg");
logMsg("Familias con variantes creadas: $nWithVar");
logMsg("Web fetches fallidos: $webFails");
logMsg("Traducciones EN→ES fallidas: $translateFail");
logMsg("Maquetados HTML fallidos: $formatFail");
logMsg("Errores INSERT: $errors");
if (!empty($mfgCreated)) {
    logMsg("Manufacturers " . ($dryRun ? "que se crearían" : "creados") . " (" . count($mfgCreated) . "):");
    foreach ($mfgCreated as $k => $v) logMsg("  · $v");
}

end_action:
?>
    </div>
    <p style="margin-top:15px;">
        <a href="<?php echo tep_href_link('import-lankhorst-altas.php'); ?>" class="xbutton small hv9">← Volver</a>
    </p>
<?php else: ?>
    <h2>Importador Lankhorst (altas)</h2>
    <?php
        $xlsx = findNewestXlsx(LANK_DIR);
        if (!$xlsx) {
            echo '<p style="color:red;">No hay xlsx en ' . LANK_DIR . '</p>';
        } else {
            echo '<p style="color:#666;font-size:13px;">xlsx detectado: <code>' . htmlspecialchars(basename($xlsx)) . '</code> (' . round(filesize($xlsx)/1024) . ' KB, mtime ' . date('Y-m-d H:i', filemtime($xlsx)) . ')</p>';
        }
    ?>
    <p>
        Lee el xlsx más reciente de <code><?php echo LANK_DIR; ?></code>, scrapea
        <code><?php echo LANK_WEB_BASE; ?>{Code}.html</code> para extraer MPN y descripción,
        y crea productos en categoría <strong><?php echo NEW_CATEGORY_ID; ?> (Lankhorst Nuevos)</strong>.
    </p>
    <p style="background:#fffbe6;border:1px solid #ffd700;padding:10px;border-radius:4px;font-size:13px;">
        <strong>Filtros que se aplican siempre</strong>: solo filas con <code>VP=STK</code> (productos sueltos, no packs/displays);
        skip si <code>Code</code> o <code>EAN</code> ya existen en BD (productos o atributos).<br>
        <strong>Variantes</strong>: si seleccionas una marca concreta, agrupo productos con
        nombres muy parecidos (LCP ≥ <?php echo (int)(LCP_VARIANT_RATIO*100); ?>%) y dispersión
        de coste &lt; <?php echo (int)PRICE_DISPERSION_MAX; ?>×. Etiqueta de variante = medida (mm/cm/kg…) o resto del nombre.
    </p>
    <form method="get" style="background:#f5f5f5;padding:15px;border-radius:5px;">
        <p>
            <strong>Marca a importar</strong>:
            <select name="brand" style="min-width:300px;">
                <option value="all">— Todas las marcas (sin agrupación de variantes) —</option>
                <?php
                if ($xlsx) {
                    $brandList = listBrandsFromXlsx($xlsx);
                    foreach ($brandList as $b => $cnt) {
                        $sel = ($selectedBrand === $b) ? ' selected' : '';
                        echo '<option value="' . htmlspecialchars($b) . '"' . $sel . '>' . htmlspecialchars($b) . ' (' . $cnt . ')</option>';
                    }
                }
                ?>
            </select>
        </p>
        <p>
            <strong>Codes específicos</strong> (opcional, ignora el selector de marca):<br>
            <textarea name="codes" rows="3" style="width:100%;font-family:monospace;" placeholder="Uno o varios Codes del xlsx separados por coma, espacio o salto de línea. Si el code es variante de una familia, se descargarán también el resto de variantes."><?php echo htmlspecialchars($onlyCodesRaw); ?></textarea>
        </p>
        <p>
            <label><input type="checkbox" name="dry_run" value="1" checked> Dry-run (sin cambios)</label>
        </p>
        <p>
            <label><input type="checkbox" name="skip_translation" value="1"> Saltar traducción LLM EN→ES (mucho más rápido; ES queda igual al EN)</label>
        </p>
        <p>
            <label><input type="checkbox" name="skip_variants" value="1"> No agrupar variantes (cada Code como suelto)</label>
        </p>
        <p>
            <label><input type="checkbox" name="skip_web_enrich" value="1"> Saltar scraping web (sin MPN ni descripción larga)</label>
        </p>
        <p>
            Inserts máximos por ejecución (0 = sin límite):
            <input type="number" name="max" value="20" min="0" style="width:80px;">
        </p>
        <button type="submit" name="action" value="dry_run" class="xbutton small hv9">Dry-run</button>
        <button type="submit" name="action" value="execute" class="xbutton small hv9 verde" onclick="return confirm('¿Ejecutar de verdad? Insertará productos en la BD.');">Ejecutar</button>
    </form>
    <p style="margin-top:20px;color:#888;font-size:12px;">
        <strong>Reglas aplicadas:</strong><br>
        - <code>products_cost</code> = Wholesale_Export 2026 (col F, sin IVA).<br>
        - <code>products_price</code> = RRP No VAT (col D); fallback <code>cost × 2</code> si vacío o &lt; cost.<br>
        - G1 con tiers según margen real, piso <code>cost × <?php echo G1_FLOOR_FACTOR; ?></code>.<br>
        - <code>products_model</code> = MPN scrapeado de la web; <code>reference_prov</code> = Code (col A).<br>
        - Variantes en <code>products_attributes</code> con <code>options_id=<?php echo VARIANT_OPTION_ID; ?></code> (Modelo). Padre = más barato.<br>
        - Idiomas: EN del xlsx/web; ES traducido vía LLM (opcional).<br>
        - Imagen: primero col J del xlsx, fallback <code>og:image</code> de la web. Skip producto sin imagen.<br>
        - EAN: si el de col I no pasa checksum → genera EAN interno con prefijo <?php echo EAN_INTERNAL_PREFIX; ?> (Lankhorst).<br>
        - Stock NO se toca. Solo VP=STK.<br>
        - Categoría destino: <strong><?php echo NEW_CATEGORY_ID; ?> (Lankhorst Nuevos)</strong>.<br>
        - Output en streaming en tiempo real.
    </p>
<?php endif; ?>
</div>
</td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
