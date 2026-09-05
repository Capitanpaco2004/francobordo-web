<?php
require 'includes/application_top.php';

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);

const BW_DIR              = '/home/francobordo/public_html/import/Bluewave/';
const BW_CACHE_DIR        = '/home/francobordo/public_html/import/Bluewave/cache/';
const BW_MAP_PATH         = '/home/francobordo/public_html/import/Bluewave/bw_map.json';
define('IMG_ABS_DIR',       dirname(dirname(__FILE__)) . '/images/productos/');
const NEW_CATEGORY_NAME   = 'Bluewave Nuevos';
const BW_MANUFACTURER_ID  = 455;          // Blue Wave (ya existe)
const TAX_CLASS_IVA21     = 1;
const LANG_ID_ES          = 3;
const LANG_ID_EN          = 1;
const G1_GROUP_ID         = 1;
const G1_FLOOR_FACTOR     = 1.10;
const PRODUCT_NAME_MAX    = 80;
const IMG_HTTP_TIMEOUT    = 15;
const IMG_MIN_BYTES       = 4096;
const MAX_SUBIMAGES       = 6;
const ORIGIN_FLAG         = 'bluewave';
const EAN_INTERNAL_PREFIX = 26;
const VARIANT_OPTION_ID   = 3;
const PVP_DIVISOR         = 0.6;           // pvp_neto = cost / 0.6 → margen 40% sobre PVP
const VAT_RATE            = 0.21;

const LLM_URL    = 'http://217.127.199.171:28001/v1/chat/completions';
const LLM_MODEL  = 'qwen36-sakamaki-nvfp4';

const LLM_PROMPT_NAME = 'Eres un traductor profesional de inglés a español especializado en productos náuticos, marinos, hardware y accesorios de jarcia inox. Usa terminología técnica náutica precisa en español de España (cabo no rope, grillete no shackle, terminal no terminal, mosquetón no carabiner, tensor no rigging screw, etc.). Conserva marcas, modelos, códigos alfanuméricos y unidades (mm, cm, kg, V, W) sin traducir. Texto plano. Responde SOLO con la traducción, sin comentarios ni explicaciones, sin comillas.';
const LLM_PROMPT_DESC = 'Eres un traductor profesional de inglés a español especializado en productos náuticos, marinos, hardware y accesorios de jarcia inox. Usa terminología técnica náutica precisa en español de España (cabo, grillete, tensor, mosquetón, garrucha, candelero, candela, calidad AISI 316 / AISI 316L, etc.). Conserva marcas, modelos, códigos alfanuméricos y unidades sin traducir. Conserva los <br> y <p>/<ul>/<li>/<strong> si los hay. Responde SOLO con la traducción, sin comentarios ni explicaciones.';

const LLM_FORMAT_PROMPT_ES = "Eres un experto en maquetar fichas de producto náuticas. Recibes una descripción comercial en ESPAÑOL y la transformas en HTML legible.\n\nREGLAS:\n1. PRIMER <p>: frase descriptiva más completa del producto.\n2. SECCIONES con <h3> SOLO si hay >6 features. Si ≤6 features, una sola <ul> sin h3.\n3. EN CADA <li>: identifica el concepto clave (1-4 palabras al inicio) y envuélvelo en <strong>concepto:</strong> + resto. Ej: <li><strong>Acero inoxidable AISI 316:</strong> resistente a corrosión marina.</li>. NO inventes texto.\n4. PRESERVA <a href> y <sup> existentes.\n5. NO resumas, NO parafrasees: conserva TODO el texto original.\n6. Si 1-2 frases, devuelve <p>texto</p> sin lista.\n7. Etiquetas permitidas: <h3>, <h4>, <p>, <ul>, <li>, <strong>, <a>, <sup>. Prohibidas: <h1>, <h2>, <br>, <div>, <span>.\n8. Salida: SOLO el HTML, sin markdown ni comentarios.";
const LLM_FORMAT_PROMPT_EN = "You format nautical product datasheets. Receive ENGLISH commercial description, return clean HTML.\n1. First <p>: most complete descriptive sentence.\n2. <h3> sections only if >6 features. ≤6 features: single <ul>.\n3. EACH <li>: identify key concept (1-4 words) and wrap in <strong>concept:</strong> + rest.\n4. PRESERVE <a href> and <sup>.\n5. NO summarizing, NO paraphrasing.\n6. If 1-2 short sentences, return <p>text</p>.\n7. Allowed: <h3>, <h4>, <p>, <ul>, <li>, <strong>, <a>, <sup>. Forbidden: <h1>, <h2>, <br>, <div>, <span>.\n8. Output: ONLY HTML, no markdown.";

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$max    = isset($_POST['max']) ? (int) $_POST['max'] : (isset($_GET['max']) ? (int) $_GET['max'] : 0);
$dryRun = isset($_POST['dry_run']) || isset($_GET['dry_run']) || $action === 'dry_run';
$skipTranslation  = isset($_POST['skip_translation']) || isset($_GET['skip_translation']);
$skipVariants     = isset($_POST['skip_variants'])    || isset($_GET['skip_variants']);
$skipImages       = isset($_POST['skip_images'])      || isset($_GET['skip_images']);
$onlyCodesRaw     = trim((string) ($_POST['codes'] ?? $_GET['codes'] ?? ''));
$selectedFamily   = trim((string) ($_POST['family'] ?? $_GET['family'] ?? 'all'));
$onlyCodes = [];
if ($onlyCodesRaw !== '') {
    foreach (preg_split('/[\s,;]+/', $onlyCodesRaw) as $c) {
        $c = trim($c);
        if ($c !== '') $onlyCodes[$c] = true;
    }
}

// ───────── helpers básicos ─────────
function logMsg($msg) {
    $line = '[' . date('H:i:s') . '] ' . $msg . "\n";
    echo '<pre style="margin:0;padding:2px 8px;border-bottom:1px solid #eee;white-space:pre-wrap;overflow-wrap:break-word;word-break:break-word;font-family:monospace;font-size:12px;">' . htmlspecialchars($line) . '</pre>';
    @flush();
}

function bwParseNum($v) {
    $v = trim((string) $v);
    if ($v === '') return null;
    $v = str_replace(',', '.', $v);
    return is_numeric($v) ? (float) $v : null;
}

/** Redondea PVP neto para que con IVA caiga en múltiplo de 0.05. */
function roundToNickel($net) {
    $withIva = ((float) $net) * (1 + VAT_RATE);
    $r = round($withIva * 20) / 20;
    return round($r / (1 + VAT_RATE), 4);
}

function bwSlugify($text, $maxLen = 50) {
    $t = trim((string) $text);
    if (function_exists('iconv')) {
        $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t);
        if ($conv !== false && $conv !== '') $t = $conv;
    }
    $t = strtolower($t);
    $t = preg_replace('/[^a-z0-9]+/', '-', $t);
    $t = trim($t, '-');
    if (strlen($t) > $maxLen) $t = substr($t, 0, $maxLen);
    return $t === '' ? 'producto' : trim($t, '-');
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
    foreach ($lines as $l) {
        $l = trim(preg_replace('/[ \t\x{A0}]+/u', ' ', $l));
        if ($l !== '') $out[] = $l;
    }
    return nl2br(trim(implode("\n", $out)), false);
}

function bwFormatLooksValid($html, $minLenInput) {
    if (!is_string($html) || trim($html) === '') return false;
    $low = strtolower($html);
    $hasStruct = (strpos($low, '<p>') !== false) || (strpos($low, '<ul>') !== false) || (strpos($low, '<h3>') !== false);
    if (!$hasStruct) return false;
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

function downloadImage($url, $destAbs, $minBytes = IMG_MIN_BYTES) {
    if (empty($url)) return false;
    $url = trim($url);
    if (strpos($url, ',') !== false || strpos($url, ' ') !== false) return false;
    if (!preg_match('#^https?://#i', $url)) return false;
    $ch = curl_init($url);
    $fp = fopen($destAbs, 'wb');
    if (!$fp) { return false; }
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => IMG_HTTP_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING => '',   // gzip/br: el WAF de Simply exige Accept-Encoding de navegador real
        CURLOPT_HTTPHEADER => [
            // Header set "navegador completo": sin Sec-Fetch + Accept-Encoding el WAF responde 454.
            'Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Accept-Encoding: gzip, deflate, br',
            'Sec-Fetch-Dest: image',
            'Sec-Fetch-Mode: no-cors',
            'Sec-Fetch-Site: same-origin',
            'Referer: https://bluewave.dk/',
        ],
    ]);
    $ok = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);
    fclose($fp);
    $ok = $ok && $code === 200 && filesize($destAbs) >= $minBytes;
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
        $tmpAbs = IMG_ABS_DIR . 'bw-tmp-' . uniqid('', true) . '.jpg';
        if (downloadImage($url, $tmpAbs)) $tmpFiles[] = $tmpAbs;
    }
    return $tmpFiles;
}

/** Tier de G1 según margen real + piso cost × G1_FLOOR_FACTOR. */
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
    if ($pp < 20 || $pp > 29) return '';
    if ($productId <= 0 || $productId > 9999999999) return '';
    $payload = str_pad((string) $pp, 2, '0', STR_PAD_LEFT) . str_pad((string) $productId, 10, '0', STR_PAD_LEFT);
    $check = ean13Checksum($payload);
    return $check < 0 ? '' : ($payload . $check);
}

/** Extrae medida del nombre xlsx (M5, M6, 1/4", 5/16", Ø8 mm, etc.) */
function bwExtractMeasure($name) {
    $name = (string) $name;
    // M-thread metric: M5, M6, M8, M10..., con o sin sufijo
    if (preg_match('/\bM(\d+(?:[.,]\d+)?)\b/i', $name, $m)) return 'M' . $m[1];
    // Imperial fraction: 1/4", 5/16", 3/8", 1 1/2", etc.
    if (preg_match('/(\d+(?:\s+\d+)?\s*\/\s*\d+)\s*"?/', $name, $m)) {
        $f = preg_replace('/\s+/', ' ', trim($m[1]));
        return $f . '"';
    }
    // Diameter: Ø8 mm
    if (preg_match('/(?:Ø|diameter|diam\.?)\s*(\d+(?:[.,]\d+)?)\s*(mm|cm)?/iu', $name, $m)) {
        return 'Ø ' . $m[1] . ' ' . (isset($m[2]) && $m[2] !== '' ? strtolower($m[2]) : 'mm');
    }
    // Talla SZ\d+
    if (preg_match('/\b(SZ|Size)\s*(\d+)\b/iu', $name, $m)) return 'SZ ' . $m[2];
    // Plain number + unit
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*(mm|cm|m)\b/iu', $name, $m)) return $m[1] . ' ' . strtolower($m[2]);
    return '';
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

/** Encuentra o crea una categoría por nombre + parent. Retorna categories_id. */
function findOrCreateCategory($mysqli, $name, $parentId, $dryRun) {
    $qName = $mysqli->real_escape_string($name);
    $r = $mysqli->query("SELECT c.categories_id
        FROM categories c
        INNER JOIN categories_description cd ON cd.categories_id=c.categories_id
        WHERE c.parent_id=$parentId AND cd.language_id=" . LANG_ID_ES . " AND cd.categories_name='$qName'
        LIMIT 1");
    if ($r && $row = $r->fetch_assoc()) return (int) $row['categories_id'];
    if ($dryRun) return 0;
    $mysqli->query("INSERT INTO categories (categories_image, parent_id, sort_order, date_added, categories_status) VALUES ('', $parentId, 99, NOW(), 0)");
    $cid = (int) $mysqli->insert_id;
    $mysqli->query("INSERT INTO categories_description (categories_id, language_id, categories_name) VALUES ($cid, " . LANG_ID_ES . ", '$qName')");
    $mysqli->query("INSERT INTO categories_description (categories_id, language_id, categories_name) VALUES ($cid, " . LANG_ID_EN . ", '$qName')");
    return $cid;
}

/** Mapa global de referencias ya en BD (origin=bluewave + EAN globales). */
function buildExistingMap($mysqli) {
    $existing = [];
    $f = "p.products_import_origin LIKE 'bluewave%'";
    $r = $mysqli->query("SELECT LOWER(p.products_model) m FROM products p WHERE p.products_model<>'' AND p.products_model IS NOT NULL AND $f");
    while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
    $r = $mysqli->query("SELECT LOWER(p.reference_prov) m FROM products p WHERE p.reference_prov<>'' AND p.reference_prov IS NOT NULL AND $f");
    while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
    $r = $mysqli->query("SELECT LOWER(pa.reference) m FROM products_attributes pa INNER JOIN products p ON p.products_id=pa.products_id WHERE pa.reference<>'' AND pa.reference IS NOT NULL AND $f");
    while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
    $r = $mysqli->query("SELECT LOWER(pa.reference_prov) m FROM products_attributes pa INNER JOIN products p ON p.products_id=pa.products_id WHERE pa.reference_prov<>'' AND pa.reference_prov IS NOT NULL AND $f");
    while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
    // EAN global
    $r = $mysqli->query("SELECT LOWER(product_ean) m FROM products WHERE product_ean<>'' AND product_ean IS NOT NULL");
    while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
    $r = $mysqli->query("SELECT LOWER(products_attributes_ean) m FROM products_attributes WHERE products_attributes_ean<>'' AND products_attributes_ean IS NOT NULL");
    while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
    // Lista negra de reimportación: trata como "ya existentes" los códigos/EAN de productos borrados a propósito.
    require_once dirname(__FILE__) . '/includes/import_blacklist.php';
    $existing += fb_blacklist_keys();
    return $existing;
}

/** Lee el xlsx de Bluewave. Estructura: header en fila 4, datos desde fila 5. */
function loadBwXlsx($path) {
    require_once dirname(dirname(__FILE__)) . '/includes/vendor/autoload.php';
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
    $reader->setReadDataOnly(true);
    $ss = $reader->load($path);
    $sheet = $ss->getSheetByName('Ark1') ?: $ss->getActiveSheet();
    $hi = $sheet->getHighestRow();
    $rows = [];
    for ($r = 5; $r <= $hi; $r++) {
        $code = trim((string) $sheet->getCell('A' . $r)->getValue());
        $name = trim((string) $sheet->getCell('B' . $r)->getValue());
        $cost = bwParseNum($sheet->getCell('D' . $r)->getValue());
        $pack = trim((string) $sheet->getCell('E' . $r)->getValue());
        $country = trim((string) $sheet->getCell('G' . $r)->getValue());
        $ean  = trim((string) $sheet->getCell('H' . $r)->getValue());
        if ($code === '' || $name === '' || $cost === null || $cost <= 0) continue;
        // Limpia asterisco final del code (xlsx marca "variante con bronce" con *)
        $code = preg_replace('/[*\s]+$/', '', $code);
        $rows[] = [
            'CODE'  => $code,
            'NAME'  => $name,
            'COST'  => round($cost, 4),
            'PACK'  => $pack,
            'COUNTRY' => $country,
            'EAN'   => $ean,
        ];
    }
    return $rows;
}

function loadBwMap($path) {
    if (!file_exists($path)) return ['skus' => [], 'pages' => []];
    $j = json_decode((string) file_get_contents($path), true);
    return is_array($j) ? $j : ['skus' => [], 'pages' => []];
}

/** Mapa 'Familia' => ['pages'=>N, 'skus'=>M] a partir del bw_map (orden alfabético).
 *  La "familia" es la sub_category del breadcrumb web (Rigging screws, Terminals, etc.). */
function bwFamiliesFromMap($bwMap) {
    $fams = [];
    foreach (($bwMap['pages'] ?? []) as $page) {
        $f = $page['sub_category'] ?? '';
        if ($f === '') $f = 'Spares & accessories';
        if (!isset($fams[$f])) $fams[$f] = ['pages' => 0, 'skus' => 0];
        $fams[$f]['pages']++;
        $fams[$f]['skus'] += count($page['art_nos'] ?? []);
    }
    uksort($fams, 'strcasecmp');
    return $fams;
}

// ───────── Acción ─────────
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
    <h2>Importador Bluewave — <?php echo $dryRun ? 'DRY-RUN (sin cambios)' : 'EJECUCIÓN REAL'; ?></h2>
    <p>
        <a href="<?php echo tep_href_link('import-bluewave-altas.php'); ?>" class="xbutton small hv9">← Volver</a>
    </p>
    <div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n";
@flush();

logMsg("Modo: " . ($dryRun ? "dry-run" : "EXECUTE")
    . ($skipTranslation ? " | sin LLM" : "")
    . ($skipVariants    ? " | sin variantes" : "")
    . ($skipImages      ? " | sin imágenes" : "")
    . (!empty($onlyCodes) ? " | codes=" . count($onlyCodes) : "")
    . ((strcasecmp($selectedFamily, 'all') !== 0 && $selectedFamily !== '') ? " | familia='$selectedFamily'" : "")
    . ($max > 0 ? " | max=$max" : ""));

$xlsx = BW_DIR . 'Pricelist Marine EUR.xlsx';
if (!file_exists($xlsx)) { logMsg("ERROR: no encuentro $xlsx"); goto end_action; }
logMsg("xlsx: " . basename($xlsx) . " (" . round(filesize($xlsx)/1024) . " KB, mtime " . date('Y-m-d H:i', filemtime($xlsx)) . ")");

if (!file_exists(BW_MAP_PATH)) { logMsg("ERROR: no encuentro bw_map.json en " . BW_MAP_PATH); goto end_action; }
$bwMap = loadBwMap(BW_MAP_PATH);
logMsg("bw_map.json: " . ($bwMap['total_pages'] ?? '?') . " páginas, " . count($bwMap['skus']) . " SKUs mapeados");

logMsg("Leyendo xlsx…");
$xlsxRows = loadBwXlsx($xlsx);
logMsg("Filas válidas: " . count($xlsxRows));

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_error) { logMsg("ERROR DB: " . $mysqli->connect_error); goto end_action; }
$mysqli->set_charset('utf8');

if (!$dryRun && !is_dir(IMG_ABS_DIR)) { @mkdir(IMG_ABS_DIR, 0775, true); }

// Categoría raíz lazy
$rootCatId = findOrCreateCategory($mysqli, NEW_CATEGORY_NAME, 0, $dryRun);
logMsg("Categoría raíz '" . NEW_CATEGORY_NAME . "' → id=$rootCatId" . ($rootCatId === 0 ? " (dry-run; no creada)" : ""));

logMsg("Cargando IDs existentes en BD…");
$existing = buildExistingMap($mysqli);
logMsg("  → " . count($existing) . " referencias ya en BD (origin=bluewave + EAN globales)");

// Pre-filtrado: skip códigos ya importados, codes filter y familia
$candidates = [];
$skipExist = $skipNoMap = $skipNotInCodes = $skipFamily = 0;
$familyFilter = ($selectedFamily !== '' && strcasecmp($selectedFamily, 'all') !== 0);
foreach ($xlsxRows as $row) {
    $code = $row['CODE'];
    if (!empty($onlyCodes) && !isset($onlyCodes[$code])) { $skipNotInCodes++; continue; }
    if (isset($existing[strtolower($code)])) { $skipExist++; continue; }
    if ($row['EAN'] !== '' && isset($existing[strtolower($row['EAN'])])) { $skipExist++; continue; }
    if ($familyFilter) {
        // La familia del SKU sale del bw_map; los SKUs sin match web caen en 'Spares & accessories'
        $fam = $bwMap['skus'][$code]['sub_category'] ?? 'Spares & accessories';
        if (strcasecmp($fam, $selectedFamily) !== 0) { $skipFamily++; continue; }
    }
    $candidates[$code] = $row;
}
logMsg("Pre-filtrado: candidates=" . count($candidates) . " | existentes=$skipExist | fuera-codes=$skipNotInCodes" . ($familyFilter ? " | fuera-familia=$skipFamily" : ""));

// Agrupa por slug del bw_map (cada slug = 1 producto padre)
$groupedBySlug = [];   // slug => [code => row]
$noMatch = [];          // codes sin bw_map → se importan como sueltos
foreach ($candidates as $code => $row) {
    $meta = $bwMap['skus'][$code] ?? null;
    if (!$meta || empty($meta['slug'])) {
        $noMatch[$code] = $row;
        continue;
    }
    // Enriquece con datos que sólo están en pages[] (datatable_html, etc.)
    $pageInfo = $bwMap['pages'][$meta['slug']] ?? null;
    if ($pageInfo) {
        $meta['datatable_html'] = $pageInfo['datatable_html'] ?? '';
    }
    $bwMap['skus'][$code] = $meta;
    $groupedBySlug[$meta['slug']][$code] = $row;
}
logMsg("Agrupación bw_map: " . count($groupedBySlug) . " productos web | " . count($noMatch) . " SKUs sin match web (irán como sueltos)");

// Si skip_variants → forzar todo a sueltos
if ($skipVariants) {
    foreach ($groupedBySlug as $slug => $items) {
        foreach ($items as $code => $row) $noMatch[$code] = $row;
    }
    $groupedBySlug = [];
    logMsg("skip_variants ON → todos como sueltos");
}

// Helpers de inserción
$catCache = [];                // sub_category → cat_id bajo bluewave-nuevos
$nInserted = $nFamilies = $nStandalone = 0;
$nWithImg = $imgFail = $skippedNoImg = $translateFail = $formatFail = $errors = 0;
$nSubImgTotal = 0;

function bwResolveSubcat($mysqli, $subcatName, $rootCatId, &$catCache, $dryRun) {
    if ($subcatName === '') $subcatName = 'Spares & accessories';
    if (isset($catCache[$subcatName])) return $catCache[$subcatName];
    $cid = findOrCreateCategory($mysqli, $subcatName, $rootCatId, $dryRun);
    $catCache[$subcatName] = $cid;
    return $cid;
}

function bwBuildDescription($descHtmlRaw, $metaDesc, $pageName) {
    $base = trim((string) $descHtmlRaw);
    if ($base === '' && $metaDesc !== '') $base = '<p>' . htmlspecialchars($metaDesc) . '</p>';
    if ($base === '') $base = '<p>' . htmlspecialchars($pageName) . '</p>';
    return $base;
}

/** Apéndice de tabla de variantes (extraído del cache).
 *  Cabecera ES: "Tabla de especificaciones". Cabecera EN: "Specifications table".
 *  Si no hay datatable_html en el meta, devuelve ''. */
function bwAppendDatatable($desc, $datatableHtml, $lang) {
    $dt = trim((string) $datatableHtml);
    if ($dt === '') return $desc;
    $heading = ($lang === 'es') ? 'Tabla de especificaciones' : 'Specifications table';
    return $desc . "\n<h3>" . $heading . "</h3>\n" . $dt;
}

function bwImgUrls($meta) {
    // El technical_drawing NO va en la galería: se incrusta inline encima de la datatable.
    $urls = [];
    if (!empty($meta['main_image']))        $urls[] = $meta['main_image'];
    if (!empty($meta['gallery']))           foreach ($meta['gallery'] as $g) $urls[] = $g;
    $seen = []; $out = [];
    foreach ($urls as $u) { if (!isset($seen[$u])) { $seen[$u] = true; $out[] = $u; } }
    return $out;
}

/** Inserta $html justo ANTES de la datatable (o antes de su <h3>) en la descripción.
 *  Si no hay datatable, lo añade al final. Idempotente respecto a duplicados. */
function bwInjectAboveDatatable($desc, $html) {
    if (trim($html) === '') return $desc;
    // Preferimos colocarlo justo antes del <h3> de la tabla para que quede: dibujo → título → tabla.
    foreach (['<h3>Tabla de especificaciones</h3>', '<h3>Specifications table</h3>'] as $h3) {
        $pos = strpos($desc, $h3);
        if ($pos !== false) {
            return substr($desc, 0, $pos) . $html . "\n" . substr($desc, $pos);
        }
    }
    $needle = '<table class="bw-datatable"';
    $pos = strpos($desc, $needle);
    if ($pos !== false) {
        return substr($desc, 0, $pos) . $html . "\n" . substr($desc, $pos);
    }
    return $desc . "\n" . $html;
}

/** Construye el <img> del dibujo técnico (centrado, responsive) para la descripción. */
function bwTechDrawingImgTag($file, $alt) {
    return '<p style="text-align:center;margin:10px 0;"><img src="/images/productos/' . htmlspecialchars($file, ENT_QUOTES)
        . '" alt="' . htmlspecialchars($alt, ENT_QUOTES) . ' - dimensiones" style="max-width:100%;height:auto;" /></p>';
}

function bwBrandPrefix($name) {
    if (preg_match('/^blue\s*wave/i', trim($name))) return '';
    return 'Blue Wave ';
}

// Función monolítica para crear un producto (padre + variantes opcionales)
function bwInsertProduct($mysqli, $context, $padre, array $variants, $meta, $dryRun, &$state) {
    $brandPrefix = bwBrandPrefix($meta['page_name'] ?: $padre['NAME']);
    $rawNameEn = $meta['page_name'] ?: $padre['NAME'];
    $titleEn = mb_substr($brandPrefix . $rawNameEn, 0, PRODUCT_NAME_MAX, 'UTF-8');
    $descEnRaw = bwBuildDescription($meta['description_html'] ?? '', $meta['meta_description'] ?? '', $rawNameEn);
    $descEn = $descEnRaw;
    $nameEs = $titleEn;
    $descEs = cleanHtmlAggressive($descEnRaw);

    if (!$context['skipTranslation'] && !$dryRun) {
        $tn = llmCall(LLM_PROMPT_NAME, $rawNameEn, 200);
        if ($tn !== '') $nameEs = mb_substr($brandPrefix . $tn, 0, PRODUCT_NAME_MAX, 'UTF-8'); else $state['translateFail']++;
        $td = llmCall(LLM_PROMPT_DESC, cleanHtmlAggressive($descEnRaw), 1500);
        if ($td !== '') $descEs = $td; else $state['translateFail']++;
        // Format HTML (ES + EN)
        $inLenEs = mb_strlen(strip_tags($descEs), 'UTF-8');
        $fmtEs = llmCall(LLM_FORMAT_PROMPT_ES, $descEs, 2500);
        if (bwFormatLooksValid($fmtEs, $inLenEs)) $descEs = $fmtEs; else $state['formatFail']++;
        $inLenEn = mb_strlen(strip_tags($descEnRaw), 'UTF-8');
        $fmtEn = llmCall(LLM_FORMAT_PROMPT_EN, $descEnRaw, 2500);
        if (bwFormatLooksValid($fmtEn, $inLenEn)) $descEn = $fmtEn; else $state['formatFail']++;
    }

    // Apéndice datatable (después del LLM para que no la toque) — independiente de skipTranslation
    $datatable = $meta['datatable_html'] ?? '';
    $descEs = bwAppendDatatable($descEs, $datatable, 'es');
    $descEn = bwAppendDatatable($descEn, $datatable, 'en');

    // Sub-cat
    $subcatId = bwResolveSubcat($mysqli, $meta['sub_category'] ?? '', $context['rootCatId'], $state['catCache'], $dryRun);

    // Precio del padre = la variante más barata (típica convención Lankhorst)
    uasort($variants, fn($a, $b) => $a['COST'] <=> $b['COST']);
    $cheapestCode = array_key_first($variants);
    $cheap = $variants[$cheapestCode];
    $padrePrice = roundToNickel($cheap['COST'] / PVP_DIVISOR);
    $padreG1    = roundToNickel(calcG1Price($padrePrice, $cheap['COST']));

    if ($dryRun) {
        logMsg(sprintf("  WOULD INSERT slug=%s cat='%s' cheap=%s cost=%.2f price=%.2f g1=%.2f vars=%d",
            $meta['slug'] ?? '(no-slug)', $meta['sub_category'] ?? '?', $cheapestCode,
            $cheap['COST'], $padrePrice, $padreG1, count($variants)));
        $state['nInserted']++;
        if (count($variants) > 1) $state['nFamilies']++; else $state['nStandalone']++;
        return true;
    }

    // Imágenes ANTES del INSERT (skip producto si no hay imagen real)
    $imgFinalNames = [];
    $tdTmp = null;   // tmp del dibujo técnico (se incrusta inline, no en galería)
    if (!$context['skipImages']) {
        $imageUrls = bwImgUrls($meta);
        if (empty($imageUrls)) { $state['skippedNoImg']++; logMsg("SKIP " . ($meta['slug'] ?? '?') . ": sin URL de imagen en bw_map"); return false; }
        $tmpFiles = downloadImagesToTmp($imageUrls, MAX_SUBIMAGES + 1);
        if (empty($tmpFiles)) { $state['skippedNoImg']++; $state['imgFail']++; logMsg("SKIP " . ($meta['slug'] ?? '?') . ": " . count($imageUrls) . " URLs probadas, ninguna válida"); return false; }
        // Dibujo técnico (dimensiones de la tabla) — descarga aparte, min-bytes bajo (línea fina)
        $tdUrl = trim((string) ($meta['technical_drawing'] ?? ''));
        if ($tdUrl !== '') {
            $cand = IMG_ABS_DIR . 'bw-td-' . uniqid('', true) . '.' . (preg_match('/\.png(\?|$)/i', $tdUrl) ? 'png' : 'jpg');
            if (downloadImage($tdUrl, $cand, 1024)) $tdTmp = $cand;
        }
    } else {
        $tmpFiles = [];
    }

    $mysqli->begin_transaction();
    try {
        // Si hay variantes con EAN, el padre NO lleva EAN (convención francobordo)
        $variantsWithEan = array_filter($variants, fn($v) => isValidEan13($v['EAN']));
        $padreEan = (count($variants) > 1 && !empty($variantsWithEan)) ? '' : ($cheap['EAN'] !== '' ? $cheap['EAN'] : '');

        $qmodel = $mysqli->real_escape_string($cheapestCode);
        $qref   = $mysqli->real_escape_string($cheapestCode);
        $qean   = $mysqli->real_escape_string($padreEan);
        $sql = "INSERT INTO products (products_quantity, check_stock, products_model, products_image, products_price, products_cost, products_date_added, products_weight, products_status, products_tax_class_id, manufacturers_id, product_ean, reference_prov, products_import_origin)
            VALUES (0, 0, \"$qmodel\", \"\", " . number_format($padrePrice, 4, '.', '') . ", " . number_format($cheap['COST'], 4, '.', '') . ", NOW(), 1.0, 2, " . TAX_CLASS_IVA21 . ", " . BW_MANUFACTURER_ID . ", \"$qean\", \"$qref\", \"" . ORIGIN_FLAG . "\")";
        if (!$mysqli->query($sql)) throw new Exception("products: " . $mysqli->error);
        $pid = (int) $mysqli->insert_id;

        // Dibujo técnico: renombra a fichero definitivo e incrusta inline encima de la tabla.
        if ($tdTmp !== null && file_exists($tdTmp)) {
            $ext = (substr($tdTmp, -4) === '.png') ? 'png' : 'jpg';
            $tdFinal = bwSlugify($nameEs ?: $titleEn) . '-' . $pid . '-td.' . $ext;
            if (@rename($tdTmp, IMG_ABS_DIR . $tdFinal)) {
                $imgTag = bwTechDrawingImgTag($tdFinal, $nameEs ?: $titleEn);
                $descEs = bwInjectAboveDatatable($descEs, $imgTag);
                $descEn = bwInjectAboveDatatable($descEn, $imgTag);
                $tdTmp = null;
                $state['nTechDrawing'] = ($state['nTechDrawing'] ?? 0) + 1;
            }
        }

        $qNameEs = $mysqli->real_escape_string($nameEs);
        $qDescEs = $mysqli->real_escape_string($descEs);
        $qNameEn = $mysqli->real_escape_string($titleEn);
        $qDescEn = $mysqli->real_escape_string($descEn);
        if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_ES . ", \"$qNameEs\", \"$qDescEs\", 0)")) throw new Exception("desc ES: " . $mysqli->error);
        if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_EN . ", \"$qNameEn\", \"$qDescEn\", 0)")) throw new Exception("desc EN: " . $mysqli->error);
        if (!$mysqli->query("INSERT INTO products_to_categories (products_id, categories_id) VALUES ($pid, $subcatId)")) throw new Exception("p2c: " . $mysqli->error);
        if (!$mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . G1_GROUP_ID . ", $pid, " . number_format($padreG1, 4, '.', '') . ", 1, 1)")) throw new Exception("g1: " . $mysqli->error);

        // Imágenes
        if (!empty($tmpFiles)) {
            $slugFs = bwSlugify($nameEs ?: $titleEn);
            foreach ($tmpFiles as $i => $tmpAbs) {
                $suffix = ($i === 0) ? '' : ('-' . ($i + 1));
                $finalName = $slugFs . '-' . $pid . $suffix . '.jpg';
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
            $state['nWithImg']++;
            $state['nSubImgTotal'] += count($imgFinalNames);
        }

        // Variantes (si hay más de 1, o también si hay 1 con label inferible)
        if (count($variants) > 1) {
            // Etiquetas: medida del nombre, o LCP-strip, o el code
            $labels = [];
            $names = array_map(fn($v) => $v['NAME'], $variants);
            $lcp = preg_replace('/[\s\-–·,]+$/u', '', longestCommonPrefix($names));
            foreach ($variants as $code => $v) {
                $m = bwExtractMeasure($v['NAME']);
                if ($m !== '') { $labels[$code] = $m; continue; }
                $rest = '';
                if ($lcp !== '' && mb_strpos($v['NAME'], $lcp) === 0) {
                    $rest = trim(mb_substr($v['NAME'], mb_strlen($lcp, 'UTF-8'), null, 'UTF-8'));
                    $rest = preg_replace('/^[\s\-–·,;]+/u', '', $rest);
                }
                $labels[$code] = $rest !== '' && mb_strlen($rest, 'UTF-8') <= 40 ? $rest : $code;
            }
            // Anti-colisión: dedup (pid, option_id, value_id)
            $usedValueIds = [];
            $variantsCreated = 0;
            foreach ($variants as $code => $v) {
                $delta = round(roundToNickel($v['COST'] / PVP_DIVISOR) - $padrePrice, 4);
                $prefix = $delta < 0 ? '-' : '+';
                $label = $labels[$code];
                $valueId = findOrCreateOptionValue($mysqli, $label);
                if (isset($usedValueIds[$valueId])) {
                    // Conflicto: usa el code para garantizar unicidad
                    $valueId = findOrCreateOptionValue($mysqli, $label . ' (' . $code . ')');
                }
                $usedValueIds[$valueId] = true;
                $qrefv = $mysqli->real_escape_string($code);
                $qprovv = $mysqli->real_escape_string($code);
                $qveanv = $mysqli->real_escape_string($v['EAN']);
                if (!$mysqli->query("INSERT INTO products_attributes SET
                    products_id=$pid, options_id=" . VARIANT_OPTION_ID . ", options_values_id=$valueId,
                    options_values_price=" . number_format(abs($delta), 4, '.', '') . ", price_prefix='$prefix',
                    reference='$qrefv', reference_prov='$qprovv', products_attributes_ean='$qveanv',
                    options_values_weight=0, weight_prefix='+'"))
                    throw new Exception("attr: " . $mysqli->error);
                $paId = (int) $mysqli->insert_id;
                $variantsCreated++;

                $vG1 = roundToNickel(calcG1Price(roundToNickel($v['COST'] / PVP_DIVISOR), $v['COST']));
                $g1Delta = round($vG1 - $padreG1, 4);
                $g1Prefix = $g1Delta < 0 ? '-' : '+';
                if (!$mysqli->query("INSERT INTO products_attributes_groups (products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id, options_values_weight, weight_prefix) VALUES ($paId, " . G1_GROUP_ID . ", " . number_format(abs($g1Delta), 4, '.', '') . ", '$g1Prefix', $pid, 0, '+')"))
                    throw new Exception("attr_groups: " . $mysqli->error);

                // EAN interno por variante si feed inválido
                if (!isValidEan13($v['EAN'])) {
                    $genEan = generateInternalEan13($paId, EAN_INTERNAL_PREFIX);
                    if ($genEan !== '') {
                        $mysqli->query("UPDATE products_attributes SET products_attributes_ean='" . $mysqli->real_escape_string($genEan) . "' WHERE products_attributes_id=$paId");
                    }
                }
            }
            $state['nFamilies']++;
        } else {
            // Producto suelto sin variantes — EAN interno al padre si feed inválido
            if (!isValidEan13($cheap['EAN'])) {
                $genEan = generateInternalEan13($pid, EAN_INTERNAL_PREFIX);
                if ($genEan !== '') {
                    $mysqli->query("UPDATE products SET product_ean='" . $mysqli->real_escape_string($genEan) . "' WHERE products_id=$pid AND (product_ean IS NULL OR product_ean='' OR LENGTH(product_ean)<>13)");
                }
            }
            $state['nStandalone']++;
        }

        $mysqli->commit();
        if ($tdTmp !== null && file_exists($tdTmp)) @unlink($tdTmp);  // tmp huérfano (rename falló)
        $state['nInserted']++;
        logMsg(sprintf("OK pid=%d slug=%s cat='%s' cheap=%s vars=%d price=%.2f cost=%.2f g1=%.2f imgs=%d%s",
            $pid, $meta['slug'] ?? '?', $meta['sub_category'] ?? '?', $cheapestCode, count($variants),
            $padrePrice, $cheap['COST'], $padreG1, count($tmpFiles),
            !empty($meta['technical_drawing']) ? ' +dibujo' : ''));
        return true;
    } catch (Exception $e) {
        $mysqli->rollback();
        $state['errors']++;
        foreach ($tmpFiles as $tmpAbs) { if (file_exists($tmpAbs)) @unlink($tmpAbs); }
        if ($tdTmp !== null && file_exists($tdTmp)) @unlink($tdTmp);
        logMsg("ERROR slug " . ($meta['slug'] ?? '?') . ": " . $e->getMessage());
        return false;
    }
}

$state = [
    'nInserted' => 0, 'nFamilies' => 0, 'nStandalone' => 0,
    'nWithImg' => 0, 'imgFail' => 0, 'skippedNoImg' => 0,
    'translateFail' => 0, 'formatFail' => 0, 'errors' => 0,
    'nSubImgTotal' => 0, 'catCache' => &$catCache,
];
$ctx = [
    'rootCatId' => $rootCatId,
    'skipTranslation' => $skipTranslation,
    'skipImages' => $skipImages,
];

// 1) Familias (grouped by slug)
foreach ($groupedBySlug as $slug => $items) {
    if ($max > 0 && $state['nInserted'] >= $max) { logMsg("Alcanzado max=$max, parando."); break; }
    $cheapestCode = array_keys($items)[0];
    $meta = $bwMap['skus'][$cheapestCode] ?? null;
    if (!$meta) { logMsg("SKIP slug=$slug: cheapest=$cheapestCode sin meta en bw_map"); continue; }
    bwInsertProduct($mysqli, $ctx, $items[$cheapestCode], $items, $meta, $dryRun, $state);
}

// 2) Sueltos (sin match en bw_map)
foreach ($noMatch as $code => $row) {
    if ($max > 0 && $state['nInserted'] >= $max) { logMsg("Alcanzado max=$max, parando."); break; }
    // Meta sintético: usamos el name del xlsx como page_name y heurística de sub_category
    $fakeMeta = [
        'slug' => 'no-web-' . strtolower($code),
        'page_name' => $row['NAME'],
        'sub_category' => 'Spares & accessories',
        'meta_description' => '',
        'description_html' => '',
        'main_image' => '',
        'technical_drawing' => '',
        'gallery' => [],
    ];
    bwInsertProduct($mysqli, $ctx, $row, [$code => $row], $fakeMeta, $dryRun, $state);
}

logMsg("==================== RESUMEN ====================");
logMsg(sprintf("INSERT padres: %d (familias=%d, sueltos=%d)", $state['nInserted'], $state['nFamilies'], $state['nStandalone']));
logMsg("Con imagen: " . $state['nWithImg'] . " | sub-imgs totales: " . $state['nSubImgTotal'] . " | con dibujo técnico inline: " . ($state['nTechDrawing'] ?? 0));
logMsg("Saltados sin imagen válida: " . $state['skippedNoImg']);
logMsg("Errores: " . $state['errors'] . " | imagen-fail: " . $state['imgFail']);
logMsg("LLM: translate-fail=" . $state['translateFail'] . " format-fail=" . $state['formatFail']);
logMsg(sprintf("Pre-skip: existentes=%d, fuera-codes=%d, fuera-familia=%d", $skipExist, $skipNotInCodes, $skipFamily));

end_action:
?>
    </div>
<?php else: ?>

<h2>Importador Bluewave</h2>
<p>Lee xlsx de <code><?php echo BW_DIR; ?></code> + <code>bw_map.json</code> (productos web scrapeados) y crea productos en la categoría "<strong><?php echo NEW_CATEGORY_NAME; ?></strong>" con status=2 (pendiente revisión).</p>
<p>Fórmula precio: <code>PVP_neto = cost / <?php echo PVP_DIVISOR; ?> (margen 40% sobre PVP), redondeado al múltiplo 0.05 IVA inc.</code></p>
<?php
$xlsx = BW_DIR . 'Pricelist Marine EUR.xlsx';
$mapOk = file_exists(BW_MAP_PATH);
$mapInfo = '';
$familiesList = [];
if ($mapOk) {
    $j = json_decode((string) file_get_contents(BW_MAP_PATH), true);
    $mapInfo = ($j['total_pages'] ?? '?') . " páginas, " . count($j['skus'] ?? []) . " SKUs";
    $familiesList = bwFamiliesFromMap($j);
}
/** Render reutilizable del <select> de familias. */
function bwFamilySelect($familiesList) {
    $h = '<select name="family"><option value="all">— Todas las familias —</option>';
    foreach ($familiesList as $fam => $cnt) {
        $h .= '<option value="' . htmlspecialchars($fam, ENT_QUOTES) . '">'
            . htmlspecialchars($fam) . ' (' . $cnt['pages'] . ' prod, ' . $cnt['skus'] . ' SKUs)</option>';
    }
    return $h . '</select>';
}
?>
<table style="margin:10px 0;border:1px solid #ddd;border-collapse:collapse;">
    <tr><th>xlsx</th><td><?php echo file_exists($xlsx) ? basename($xlsx) . ' (' . round(filesize($xlsx)/1024) . ' KB)' : '<span style="color:red">NO ENCONTRADO</span>'; ?></td></tr>
    <tr><th>bw_map.json</th><td><?php echo $mapOk ? $mapInfo : '<span style="color:red">NO ENCONTRADO — ejecuta bw_build_cache.php primero</span>'; ?></td></tr>
    <tr><th>Manufacturer</th><td>Blue Wave (id=<?php echo BW_MANUFACTURER_ID; ?>)</td></tr>
    <tr><th>EAN interno</th><td>prefijo <?php echo EAN_INTERNAL_PREFIX; ?> (cuando falta o es inválido)</td></tr>
    <tr><th>Categoría destino</th><td>"<?php echo NEW_CATEGORY_NAME; ?>" (se crea si no existe) + subcategorías por breadcrumb web</td></tr>
</table>
<form method="post" action="<?php echo tep_href_link('import-bluewave-altas.php', 'action=dry_run'); ?>" style="margin:10px 0;">
    <fieldset><legend>1. Dry-run (sin cambios en BD)</legend>
        <label>familia: <?php echo bwFamilySelect($familiesList); ?></label>
        <label>max productos: <input name="max" type="number" min="0" value="0" style="width:80px"></label>
        <label>codes (csv): <input name="codes" type="text" size="40" placeholder="011205,011306A"></label>
        <label><input type="checkbox" name="skip_translation"> sin LLM</label>
        <label><input type="checkbox" name="skip_variants"> sin variantes</label>
        <label><input type="checkbox" name="skip_images"> sin imágenes</label>
        <button type="submit">Dry-run</button>
    </fieldset>
</form>
<form method="post" action="<?php echo tep_href_link('import-bluewave-altas.php', 'action=execute'); ?>" style="margin:10px 0;" onsubmit="return confirm('Ejecutar inserciones REALES en BD?');">
    <fieldset><legend>2. EJECUTAR (INSERT real, status=2)</legend>
        <label>familia: <?php echo bwFamilySelect($familiesList); ?></label>
        <label>max productos: <input name="max" type="number" min="0" value="0" style="width:80px"></label>
        <label>codes (csv): <input name="codes" type="text" size="40" placeholder="011205,011306A"></label>
        <label><input type="checkbox" name="skip_translation"> sin LLM</label>
        <label><input type="checkbox" name="skip_variants"> sin variantes</label>
        <label><input type="checkbox" name="skip_images"> sin imágenes</label>
        <button type="submit" style="background:#c30;color:#fff;">EXECUTE</button>
    </fieldset>
</form>
<?php endif; ?>
</div>
</td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
