<?php
require 'includes/application_top.php';
require_once DIR_FS_DOCUMENT_ROOT . 'includes/vendor/autoload.php';
require_once dirname(__FILE__) . '/includes/mb_reformat_helpers.php';

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);

const YT_DIR              = '/home/francobordo/public_html/import/Yachticon/';
const YT_CACHE_DIR        = '/home/francobordo/public_html/import/Yachticon/cache/';
const YT_BASE_ES          = 'https://yachticon.es';
const YT_BASE_EN          = 'https://yachticon.com';
const YT_URLMAP_FILE      = '/home/francobordo/public_html/import/Yachticon/cache/urls_by_sku.json';
const YT_SITEMAP_INDEX_ES = '/home/francobordo/public_html/import/Yachticon/cache/sitemap_index_es.xml';
const YT_SITEMAP_INDEX_EN = '/home/francobordo/public_html/import/Yachticon/cache/sitemap_index_en.xml';
define('IMG_ABS_DIR',       dirname(dirname(__FILE__)) . '/images/productos/');

const PARENT_CATEGORY_NAME_ES = 'Yachticon Nuevos';
const PARENT_CATEGORY_NAME_EN = 'Yachticon New';
const MFG_YACHTICON           = 52;
const TAX_CLASS_IVA21         = 1;
const LANG_ID_ES              = 3;
const LANG_ID_EN              = 1;
const G1_GROUP_ID             = 1;
const PRODUCT_NAME_MAX        = 96;
const NAME_SUFFIX             = ' - Yachticon';
const IMG_HTTP_TIMEOUT        = 15;
const WEB_HTTP_TIMEOUT        = 25;
const IMG_MIN_BYTES           = 4096;
const MAX_SUBIMAGES           = 6;
const ORIGIN_FLAG             = 'yachticon';
const EAN_INTERNAL_PREFIX     = 27;
const VAT_RATE_ES             = 0.21;
const VAT_RATE_DE             = 0.19;
const COST_FROM_J             = true;       // cost = J (Handel EK/Stück, neto)
const G1_MULT                 = 0.80;       // G1 = 80% de Retail_neto
const FALLBACK_SUBCAT         = 'VARIOS';
const SUBCAT_MIN_ROWS         = 2;
const CRAWL_PARALLEL          = 24;
const CRAWL_CHUNK_PAGES       = 600;        // pages crawled per "build_cache" invocation
const PARENT_CATEGORY_NAME_KEY = 'Yachticon Nuevos';

const LLM_URL    = 'http://217.127.199.171:28001/v1/chat/completions';
const LLM_MODEL  = 'qwen36-sakamaki-nvfp4';

const LLM_FORMAT_PROMPT_ES = "Eres un experto en maquetar fichas de producto náuticas/marinas. Recibes una descripción comercial en ESPAÑOL y la transformas en HTML legible y atractivo.\n\nREGLAS OBLIGATORIAS:\n\n1. PÁRRAFO INTRODUCTORIO: el primer <p> es la frase descriptiva más completa del producto. Si el texto introductorio tiene más de 5 frases, parte en varios <p> de 5 frases máximo cada uno.\n\n2. TÍTULOS DE SECCIÓN (si el producto tiene >6 features, agrupa en secciones): cada título es <p><strong>Título</strong></p>. NUNCA uses <h1>..<h6>. Antes de cada título de sección inserta <p>&nbsp;</p> como separador.\n\n3. LISTAS DE FEATURES: cada bullet va en su propio <p>• texto</p>. NUNCA uses <ul> ni <li>. Identifica el concepto clave (1-4 palabras al inicio) y envuélvelo en <strong>, seguido de dos puntos.\n\n4. SÍMBOLOS PROHIBIDOS: elimina ™ ® © en todo el texto.\n\n5. PALABRAS EN MAYÚSCULAS: si una palabra está toda en mayúsculas y tiene 4+ letras → Title Case. Preserva acrónimos cortos (BPA, UV, LED, IP, USB, ABS, PVC, AISI…).\n\n6. PRESERVA enlaces <a href> existentes.\n\n7. NO resumas, NO parafrasees, NO inventes información: conserva TODO el texto original. Solo añades estructura HTML.\n\n8. Si el texto de entrada es solo 1-2 frases cortas, devuelve <p>texto</p> sin lista.\n\n9. Etiquetas permitidas: <p>, <strong>, <a>. Prohibidas: <h1>..<h6>, <br>, <div>, <span>, <ul>, <li>, <table>, <ol>.\n\n10. Salida: SOLO el HTML, sin markdown ni comentarios.";

const LLM_FORMAT_PROMPT_EN = "You are an expert in formatting nautical/marine product datasheets. You receive a commercial description in ENGLISH and transform it into clean, readable HTML.\n\nMANDATORY RULES:\n\n1. INTRODUCTORY PARAGRAPH: the first <p> is the most complete descriptive sentence. If introductory text has more than 5 sentences, split into several <p> blocks of max 5 sentences each.\n\n2. SECTION TITLES (if product has >6 features, group into sections): each title is <p><strong>Title</strong></p>. NEVER use <h1>..<h6>. Before each section title insert <p>&nbsp;</p> as separator.\n\n3. FEATURE LISTS: each bullet goes in its own <p>• text</p>. NEVER use <ul> or <li>. Identify the key concept (1-4 words at start) and wrap it in <strong>, followed by colon.\n\n4. FORBIDDEN SYMBOLS: remove ™ ® © from all text.\n\n5. ALL-CAPS WORDS: any word in all caps with 4+ letters → Title Case. Preserve short acronyms (BPA, UV, LED, IP, USB, ABS, PVC, AISI…).\n\n6. PRESERVE existing <a href> tags.\n\n7. DO NOT summarize, DO NOT paraphrase, DO NOT invent information.\n\n8. If input is only 1-2 short sentences, return <p>text</p>.\n\n9. Allowed tags: <p>, <strong>, <a>. Forbidden: <h1>..<h6>, <br>, <div>, <span>, <ul>, <li>, <table>, <ol>.\n\n10. Output: ONLY the HTML, no markdown.";

$action         = $_POST['action'] ?? $_GET['action'] ?? '';
$max            = isset($_POST['max']) ? (int) $_POST['max'] : (isset($_GET['max']) ? (int) $_GET['max'] : 0);
$dryRun         = isset($_POST['dry_run']) || isset($_GET['dry_run']) || $action === 'dry_run';
$skipFormat     = isset($_POST['skip_format']) || isset($_GET['skip_format']);
$selectedSubcat = trim((string) ($_POST['subcat'] ?? $_GET['subcat'] ?? 'all'));
$onlyCodesRaw   = trim((string) ($_POST['codes'] ?? $_GET['codes'] ?? ''));
$onlyCodes = [];
if ($onlyCodesRaw !== '') {
    foreach (preg_split('/[\s,;]+/', $onlyCodesRaw) as $c) {
        $c = trim($c);
        if ($c !== '') $onlyCodes[strtolower($c)] = true;
    }
}
// Cuando se especifican SKUs concretos, ignorar el límite max para no truncar la selección.
if (!empty($onlyCodes) && $max > 0) $max = 0;

function logMsg($msg) {
    $line = '[' . date('H:i:s') . '] ' . $msg . "\n";
    echo '<pre style="margin:0;padding:2px 8px;border-bottom:1px solid #eee;white-space:pre-wrap;overflow-wrap:break-word;word-break:break-word;font-family:monospace;font-size:12px;">' . htmlspecialchars($line) . '</pre>';
    @flush();
}

function ytParseNum($v) {
    $v = trim((string) $v);
    if ($v === '') return null;
    $v = str_replace(',', '.', $v);
    return is_numeric($v) ? (float) $v : null;
}

function ytSlugify($text, $maxLen = 50) {
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

function ytStripSuffix($name) {
    $s = (string) $name;
    $suf = NAME_SUFFIX;
    $sufLen = strlen($suf);
    if (strlen($s) >= $sufLen && substr_compare($s, $suf, -$sufLen, $sufLen, true) === 0) {
        $s = rtrim(substr($s, 0, -$sufLen));
    }
    return $s;
}

function ytApplySuffix($name) {
    $name = html_entity_decode((string) $name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $name = ytStripSuffix($name);
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
    $withIva = ((float) $net) * (1 + VAT_RATE_ES);
    $rounded = round($withIva * 20) / 20;
    return round($rounded / (1 + VAT_RATE_ES), 4);
}

function ytBrowserHeaders($lang = 'es') {
    return [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: ' . ($lang === 'en' ? 'en-US,en;q=0.9' : 'es-ES,es;q=0.9,en;q=0.8'),
        'Accept-Encoding: gzip, deflate',
    ];
}

function ytHttpGet($url, $lang = 'es') {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => WEB_HTTP_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER => ytBrowserHeaders($lang),
        CURLOPT_ENCODING => '',
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $body = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);
    if ($body === false || $http !== 200) return null;
    return $body;
}

/** Multi-fetch: descarga $urls (array URL → callback handler($url, $body)). */
function ytMultiFetch(array $urls, $lang, callable $onEach, $parallel = CRAWL_PARALLEL) {
    if (empty($urls)) return;
    $mh = curl_multi_init();
    $active = [];
    $queue = array_values($urls);
    $i = 0;
    $headers = ytBrowserHeaders($lang);
    $launch = function () use (&$queue, &$active, &$i, $mh, $headers, $parallel) {
        while (count($active) < $parallel && !empty($queue)) {
            $url = array_shift($queue);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => WEB_HTTP_TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_ENCODING => '',
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            curl_multi_add_handle($mh, $ch);
            $active[(int) $ch] = ['ch' => $ch, 'url' => $url];
        }
    };
    $launch();
    do {
        $status = curl_multi_exec($mh, $running);
        if ($status === CURLM_OK) curl_multi_select($mh, 1.0);
        while ($info = curl_multi_info_read($mh)) {
            $ch  = $info['handle'];
            $id  = (int) $ch;
            $url = $active[$id]['url'] ?? '';
            $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $body = curl_multi_getcontent($ch);
            $onEach($url, $http === 200 ? $body : null, $http);
            curl_multi_remove_handle($mh, $ch);
            unset($ch);
            unset($active[$id]);
            $launch();
        }
    } while ($running > 0 || !empty($queue));
    curl_multi_close($mh);
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
            'Referer: https://yachticon.es/',
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
    $seen = []; $tmpFiles = [];
    foreach ($urls as $url) {
        if (count($tmpFiles) >= $maxImages) break;
        $url = trim($url);
        if ($url === '' || isset($seen[$url])) continue;
        $seen[$url] = true;
        $tmpAbs = IMG_ABS_DIR . 'yt-tmp-' . uniqid('', true) . '.jpg';
        if (downloadImage($url, $tmpAbs)) $tmpFiles[] = $tmpAbs;
    }
    return $tmpFiles;
}

function llmCall($systemPrompt, $userText, $maxTokens = 2500, $maxRetries = 2) {
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

function ytFormatLooksValid($html, $minLenInput) {
    if (!is_string($html) || trim($html) === '') return false;
    $low = strtolower($html);
    $hasStruct = (strpos($low, '<p>') !== false) || (strpos($low, '<ul>') !== false) || (strpos($low, '<h3>') !== false);
    if (!$hasStruct) return false;
    $plainOut = mb_strlen(trim(strip_tags($html)), 'UTF-8');
    if ($minLenInput > 200 && $plainOut < $minLenInput * 0.4) return false;
    if (preg_match_all('#<li[^>]*>(.*?)</li>|<p>\s*•\s*(.*?)</p>#is', $html, $m, PREG_SET_ORDER) && count($m) >= 3) {
        $items = [];
        foreach ($m as $row) {
            $t = trim(strip_tags($row[1] !== '' ? $row[1] : $row[2]));
            $t = preg_replace('/\s+/u', ' ', $t);
            $items[] = mb_strtolower($t, 'UTF-8');
        }
        $unique = array_unique($items);
        if (count($items) - count($unique) >= max(2, count($items) * 0.4)) return false;
    }
    return true;
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

/**
 * Lee xlsx Yachticon (sheet "Handel_D", headers en fila 15).
 * Devuelve array indexado por MC (col B). Skip filas con MC vacío, MC duplicada (1ª gana),
 * E != "0" (Auslaufartikel), o sin precio K.
 */
function loadYachticonXlsx($file, &$stats) {
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file);
    $reader->setReadDataOnly(true);
    $ss = $reader->load($file);
    $sheet = $ss->getSheetByName('Handel_D');
    if (!$sheet) throw new Exception("Sheet 'Handel_D' no existe");
    $hr = $sheet->getHighestRow();
    $rows = $sheet->toArray(null, true, true, true);

    $byMc = [];
    $stats = [
        'total_rows'       => 0,
        'skip_auslauf'     => 0,
        'skip_no_mc'       => 0,
        'skip_dup_mc'      => [],
        'skip_no_price'    => 0,
        'skip_no_artikel'  => 0,
    ];
    for ($r = 16; $r <= $hr; $r++) {
        $row = $rows[$r] ?? null;
        if (!$row) continue;
        $artikel = trim((string)($row['A'] ?? ''));
        $mc      = trim((string)($row['B'] ?? ''));
        $desc    = trim((string)($row['C'] ?? ''));
        if ($artikel === '' && $mc === '' && $desc === '') continue;
        $stats['total_rows']++;
        $st = trim((string)($row['E'] ?? ''));
        if ($st !== '0' && $st !== '') { $stats['skip_auslauf']++; continue; }
        if ($mc === '') { $stats['skip_no_mc']++; continue; }
        if ($artikel === '') { $stats['skip_no_artikel']++; continue; }
        $pvp = ytParseNum($row['K'] ?? '');
        if ($pvp === null || $pvp <= 0) { $stats['skip_no_price']++; continue; }
        if (isset($byMc[$mc])) {
            $stats['skip_dup_mc'][$mc] = ($stats['skip_dup_mc'][$mc] ?? 1) + 1;
            continue;
        }
        $j = ytParseNum($row['J'] ?? '');
        $byMc[$mc] = [
            'MC'        => $mc,
            'ARTIKEL'   => $artikel,
            'DESC_DE'   => $desc,
            'EAN'       => trim((string)($row['F'] ?? '')),
            'COST'      => ($j !== null && $j > 0) ? $j : ($pvp / (1 + VAT_RATE_DE) * 0.5),
            'PVP_DE_G'  => $pvp,
            'PE'        => trim((string)($row['I'] ?? '')),
            'VE_QTY'    => trim((string)($row['H'] ?? '')),
        ];
    }
    return $byMc;
}

/**
 * Map "ya en BD" (filtrado a Yachticon).
 * - SKU (model/reference_prov): mfg=52 OR origin LIKE yachticon%
 * - EAN: global
 * - También considera la posibilidad de que productos legacy usen el Artikel-Nr truncado.
 */
function buildExistingMap($mysqli) {
    $existing = [];
    $f = "(p.manufacturers_id = " . MFG_YACHTICON . " OR p.products_import_origin LIKE 'yachticon%')";

    $r = $mysqli->query("SELECT LOWER(p.products_model) m FROM products p WHERE p.products_model<>'' AND p.products_model IS NOT NULL AND $f");
    while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
    $r = $mysqli->query("SELECT LOWER(p.reference_prov) m FROM products p WHERE p.reference_prov<>'' AND p.reference_prov IS NOT NULL AND $f");
    while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
    $r = $mysqli->query("SELECT LOWER(pa.reference) m FROM products_attributes pa INNER JOIN products p ON p.products_id=pa.products_id WHERE pa.reference<>'' AND pa.reference IS NOT NULL AND $f");
    while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
    $r = $mysqli->query("SELECT LOWER(pa.reference_prov) m FROM products_attributes pa INNER JOIN products p ON p.products_id=pa.products_id WHERE pa.reference_prov<>'' AND pa.reference_prov IS NOT NULL AND $f");
    while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;

    // EAN globales
    $r = $mysqli->query("SELECT LOWER(product_ean) m FROM products WHERE product_ean<>'' AND product_ean IS NOT NULL");
    while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
    $r = $mysqli->query("SELECT LOWER(products_attributes_ean) m FROM products_attributes WHERE products_attributes_ean<>'' AND products_attributes_ean IS NOT NULL");
    while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
    // Lista negra de reimportación: trata como "ya existentes" los códigos/EAN de productos borrados a propósito.
    require_once dirname(__FILE__) . '/includes/import_blacklist.php';
    $existing += fb_blacklist_keys();
    return $existing;
}

/** Ensure padre "Yachticon Nuevos" (parent=0, status=0). Reusa existente si lo hay por nombre. */
function ensureParentCategory($mysqli, $dryRun, &$createdLog) {
    $r = $mysqli->query("SELECT c.categories_id FROM categories c INNER JOIN categories_description cd ON cd.categories_id=c.categories_id AND cd.language_id=" . LANG_ID_ES . " WHERE c.parent_id=0 AND UPPER(TRIM(cd.categories_name))=UPPER('" . PARENT_CATEGORY_NAME_KEY . "') LIMIT 1");
    if ($r && $row = $r->fetch_assoc()) return (int) $row['categories_id'];
    if ($dryRun) { $createdLog[] = "categoría padre '" . PARENT_CATEGORY_NAME_ES . "' (dry-run, no creada)"; return 0; }
    $mysqli->query("INSERT INTO categories (parent_id, sort_order, date_added, last_modified, categories_status) VALUES (0, 0, NOW(), NOW(), 0)");
    $pid = (int) $mysqli->insert_id;
    $qEs = $mysqli->real_escape_string(PARENT_CATEGORY_NAME_ES);
    $qEn = $mysqli->real_escape_string(PARENT_CATEGORY_NAME_EN);
    $mysqli->query("INSERT INTO categories_description (categories_id, language_id, categories_name) VALUES ($pid, " . LANG_ID_ES . ", '$qEs')");
    $mysqli->query("INSERT INTO categories_description (categories_id, language_id, categories_name) VALUES ($pid, " . LANG_ID_EN . ", '$qEn')");
    $createdLog[] = "categoría padre $pid ('" . PARENT_CATEGORY_NAME_ES . "')";
    return $pid;
}

function getOrCreateSubcategory($mysqli, $name, $parentId, $dryRun, &$cache, &$createdLog) {
    $nm = trim(preg_replace('/\s+/u', ' ', (string) $name));
    if ($nm === '') $nm = FALLBACK_SUBCAT;
    $key = mb_strtoupper($nm, 'UTF-8');
    if (isset($cache[$key])) return $cache[$key];

    $qName = $mysqli->real_escape_string($nm);
    $parentId = (int) $parentId;
    if ($parentId > 0) {
        $r = $mysqli->query("SELECT c.categories_id FROM categories c
            INNER JOIN categories_description cd ON cd.categories_id=c.categories_id AND cd.language_id=" . LANG_ID_ES . "
            WHERE c.parent_id=$parentId AND UPPER(TRIM(cd.categories_name))=UPPER('$qName')
            LIMIT 1");
        if ($r && $row = $r->fetch_assoc()) { $cache[$key] = (int) $row['categories_id']; return $cache[$key]; }
    }
    if ($dryRun) {
        $cache[$key] = 0;
        $createdLog[] = "subcategoría '$nm' bajo $parentId (dry-run, no creada)";
        return 0;
    }
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

// ============================== SITEMAP / URL CACHE ==============================

/** Descarga sitemap index y todos sus children, devuelve array de URLs de producto. */
function ytDownloadSitemap($base, $indexFile) {
    $indexUrl = $base . '/sitemap.xml';
    $body = ytHttpGet($indexUrl);
    if ($body === null) return null;
    @file_put_contents($indexFile, $body);
    $children = [];
    if (preg_match_all('#<loc>([^<]+)</loc>#', $body, $m)) {
        foreach ($m[1] as $u) $children[] = trim($u);
    }
    $productUrls = [];
    foreach ($children as $child) {
        if (strpos($child, 'sitemap') === false) continue;
        $ch = ytHttpGet($child);
        if ($ch === null) continue;
        // Si está gzip, ytHttpGet ya descomprime via CURLOPT_ENCODING. Pero los .gz a veces no se anuncian.
        if (substr($child, -3) === '.gz' && substr($ch, 0, 2) === "\x1f\x8b") {
            $tmp = @gzdecode($ch);
            if ($tmp !== false) $ch = $tmp;
        }
        if (preg_match_all('#<loc>([^<]+/product/[^<]+)</loc>#', $ch, $mm)) {
            foreach ($mm[1] as $u) $productUrls[] = trim($u);
        }
    }
    return array_values(array_unique($productUrls));
}

/** Extrae datos de una página de producto Shopware Yachticon. */
function ytParseProductPage($html) {
    if (!is_string($html) || $html === '') return null;
    $data = ['name' => '', 'sku' => '', 'ean' => '', 'desc' => '', 'images' => [], 'breadcrumb' => []];

    // Nombre h1 (Shopware suele tener class="product-detail-name")
    if (preg_match('#<h1[^>]*class="[^"]*product-detail-name[^"]*"[^>]*>(.*?)</h1>#is', $html, $m)) {
        $data['name'] = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    if ($data['name'] === '' && preg_match('#<meta property="og:title" content="([^"]+)"#i', $html, $m)) {
        $data['name'] = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    // SKU/Artikel-Nr: bloque "Número de producto: X" o "Product number: X"
    if (preg_match('#(?:product-detail-ordernumber|class="product-detail-ordernumber")[^>]*>\s*([0-9.]+)\s*<#i', $html, $m)) {
        $data['sku'] = trim($m[1]);
    } else {
        // fallback: buscar "Número de producto:" o "Product number:" en texto
        if (preg_match('#(?:Número de producto|Product number|Artikelnummer|Numéro de produit)[^0-9]{0,40}([0-9]\.[0-9]+\.[0-9]+\.[0-9]+)#i', $html, $m)) {
            $data['sku'] = trim($m[1]);
        }
    }

    // EAN
    if (preg_match('#EAN[^0-9]{0,30}([0-9]{13})#i', $html, $m)) $data['ean'] = $m[1];

    // Descripción (Shopware: product-detail-description-text)
    if (preg_match('#<div[^>]*class="[^"]*product-detail-description-text[^"]*"[^>]*>(.*?)</div>\s*<(?:div|section)#is', $html, $m)) {
        $data['desc'] = trim($m[1]);
    } elseif (preg_match('#<meta name="description" content="([^"]+)"#i', $html, $m)) {
        $data['desc'] = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    // Imágenes: tomamos las /media/.../...jpg (tamaño completo) o thumbnail 1920x1920
    $imgs = [];
    if (preg_match_all('#https://yachticon\.(?:es|com)/(?:media|thumbnail)/[a-f0-9/]+/\d+/[^"\'\s]+\.(?:jpg|jpeg|png|webp)#i', $html, $mm)) {
        foreach ($mm[0] as $u) {
            $base = preg_replace('#_\d+x\d+\.(jpg|jpeg|png|webp)$#i', '.$1', $u);
            $base = str_replace('/thumbnail/', '/media/', $base);
            $low = strtolower(basename($base));
            // descartar logos y favicons
            if (strpos($low, 'logo') !== false || strpos($low, 'favicon') !== false || strpos($low, 'dhl') !== false || strpos($low, 'hellmann') !== false || strpos($low, 'yachticon-logo') !== false) continue;
            $imgs[$base] = true;
        }
    }
    $data['images'] = array_keys($imgs);

    // Breadcrumb (Shopware: class="breadcrumb")
    if (preg_match('#<(?:ol|ul)[^>]*class="[^"]*breadcrumb[^"]*"[^>]*>(.*?)</(?:ol|ul)>#is', $html, $m)) {
        $bc = $m[1];
        if (preg_match_all('#<(?:li|span)[^>]*itemprop="itemListElement"[^>]*>.*?<(?:a|span)[^>]*>(.*?)</(?:a|span)>.*?</(?:li|span)>#is', $bc, $mm2)) {
            foreach ($mm2[1] as $t) {
                $t2 = trim(html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($t2 !== '' && stripos($t2, 'inicio') === false && stripos($t2, 'home') === false) $data['breadcrumb'][] = $t2;
            }
        } else {
            if (preg_match_all('#<(?:a|span)[^>]*>([^<]{2,80})</(?:a|span)>#i', $bc, $mm2)) {
                foreach ($mm2[1] as $t) {
                    $t2 = trim(html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    if ($t2 !== '' && stripos($t2, 'inicio') === false && stripos($t2, 'home') === false) $data['breadcrumb'][] = $t2;
                }
            }
        }
    }
    return $data;
}

function ytLoadUrlMap() {
    if (!file_exists(YT_URLMAP_FILE)) return [];
    $j = json_decode((string) @file_get_contents(YT_URLMAP_FILE), true);
    return is_array($j) ? $j : [];
}

/** Path al archivo cacheado (.html.gz) para una URL en un idioma. */
function ytCachePagePath($url, $lang) {
    $hash = md5($url);
    return YT_CACHE_DIR . "pages_$lang/" . substr($hash, 0, 2) . '/' . $hash . '.html.gz';
}

/** Devuelve true si el HTML está cacheado (acepta .html.gz o legacy .html). */
function ytCachePageExists($url, $lang) {
    $gz = ytCachePagePath($url, $lang);
    if (file_exists($gz)) return true;
    $plain = preg_replace('/\.gz$/', '', $gz);
    return file_exists($plain);
}

/** Lee el HTML cacheado (descomprime si .gz). '' si no existe. */
function ytCachePageRead($url, $lang) {
    $gz = ytCachePagePath($url, $lang);
    if (file_exists($gz)) {
        $bin = @file_get_contents($gz);
        if ($bin === false) return '';
        $dec = @gzdecode($bin);
        return $dec !== false ? $dec : '';
    }
    $plain = preg_replace('/\.gz$/', '', $gz);
    if (file_exists($plain)) return (string) @file_get_contents($plain);
    return '';
}

/** Escribe el HTML cacheado en .html.gz (gzip nivel 6). */
function ytCachePageWrite($url, $lang, $body) {
    $gz = ytCachePagePath($url, $lang);
    $dir = dirname($gz);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $bin = gzencode((string) $body, 6);
    if ($bin === false) return false;
    return (bool) @file_put_contents($gz, $bin);
}

function ytSaveUrlMap(array $map) {
    if (!is_dir(YT_CACHE_DIR)) @mkdir(YT_CACHE_DIR, 0775, true);
    @file_put_contents(YT_URLMAP_FILE, json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

// ============================== ACTIONS ==============================

$isAction = in_array($action, ['execute', 'dry_run', 'build_cache'], true);

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
<?php if ($action === 'build_cache'): ?>
    <h2>Yachticon — construir cache de URLs por idioma</h2>
    <p><a href="<?php echo tep_href_link('import-yachticon-altas.php'); ?>" class="xbutton small hv9">← Volver</a></p>
    <div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n";
@flush();
if (!is_dir(YT_CACHE_DIR)) @mkdir(YT_CACHE_DIR, 0775, true);

logMsg("Construyendo cache de URLs y páginas (chunked, reanudable).");

// Sitemaps (descarga si faltan)
foreach ([['es', YT_BASE_ES, YT_SITEMAP_INDEX_ES], ['en', YT_BASE_EN, YT_SITEMAP_INDEX_EN]] as $cfg) {
    [$lang, $base, $indexFile] = $cfg;
    $urlsFile = YT_CACHE_DIR . "product_urls_$lang.json";
    if (!file_exists($urlsFile)) {
        logMsg("[$lang] descargando sitemap y URLs de producto…");
        $urls = ytDownloadSitemap($base, $indexFile);
        if ($urls === null) { logMsg("[$lang] ERROR descargando sitemap"); continue; }
        @file_put_contents($urlsFile, json_encode($urls, JSON_UNESCAPED_SLASHES));
        logMsg("[$lang] " . count($urls) . " URLs de producto guardadas en " . basename($urlsFile));
    } else {
        $urls = json_decode((string) @file_get_contents($urlsFile), true);
        logMsg("[$lang] " . count($urls) . " URLs (cache existente)");
    }
}

// Crawl chunk: hash url -> archivo html en disco. Saltar las ya descargadas.
$urlMap = ytLoadUrlMap();
$crawlState = []; // [sku] => ['url_es' => ..., 'url_en' => ...]
foreach ($urlMap as $sku => $u) $crawlState[$sku] = $u;

$processedThisRun = 0;
foreach (['es', 'en'] as $lang) {
    $urlsFile = YT_CACHE_DIR . "product_urls_$lang.json";
    if (!file_exists($urlsFile)) continue;
    $allUrls = json_decode((string) @file_get_contents($urlsFile), true) ?: [];
    $pageDir = YT_CACHE_DIR . "pages_$lang/";
    if (!is_dir($pageDir)) @mkdir($pageDir, 0775, true);

    // Filtra: solo los aún no descargados (acepta cache .html.gz o legacy .html)
    $todo = [];
    foreach ($allUrls as $u) {
        if (!ytCachePageExists($u, $lang)) $todo[] = $u;
    }
    logMsg("[$lang] crawl: " . count($todo) . " pendientes de " . count($allUrls) . " totales");
    $chunk = array_slice($todo, 0, CRAWL_CHUNK_PAGES);
    if (empty($chunk)) { logMsg("[$lang] nada nuevo que crawlear"); continue; }

    $ts0 = microtime(true);
    $count = 0;
    ytMultiFetch($chunk, $lang, function ($url, $body, $http) use (&$count, &$crawlState, $lang) {
        if ($body !== null) {
            ytCachePageWrite($url, $lang, $body);
            $data = ytParseProductPage($body);
            if ($data && $data['sku']) {
                if (!isset($crawlState[$data['sku']])) $crawlState[$data['sku']] = [];
                $crawlState[$data['sku']]['url_' . $lang] = $url;
            }
        }
        $count++;
    });
    ytSaveUrlMap($crawlState);
    $processedThisRun += $count;
    logMsg(sprintf("[$lang] %d páginas descargadas en %.1fs (avg %.2fs/req)", $count, microtime(true)-$ts0, max(0.01, (microtime(true)-$ts0)/max(1,$count))));
}

logMsg("Total processed: $processedThisRun | URL map ahora con " . count($crawlState) . " SKUs mapeados");
logMsg("Vuelve a pulsar 'Construir cache' hasta que ambos idiomas marquen 0 pendientes.");
?>
    </div>
    <p style="margin-top:15px;"><a href="<?php echo tep_href_link('import-yachticon-altas.php'); ?>" class="xbutton small hv9">← Volver</a></p>

<?php elseif ($isAction): ?>
    <h2>Importador Yachticon — <?php echo $dryRun ? 'DRY-RUN (sin cambios)' : 'EJECUCIÓN REAL'; ?></h2>
    <p><a href="<?php echo tep_href_link('import-yachticon-altas.php'); ?>" class="xbutton small hv9">← Volver</a></p>
    <div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n";
@flush();

logMsg("Modo: " . ($dryRun ? "dry-run" : "EXECUTE")
    . " | subcat=" . ($selectedSubcat === 'all' ? 'TODAS' : $selectedSubcat)
    . ($skipFormat ? " | sin maquetado LLM" : "")
    . ($max > 0 ? " | max=$max" : ""));

$xlsx = findNewestXlsx(YT_DIR);
if (!$xlsx) { logMsg("ERROR: no hay xlsx en " . YT_DIR); goto end_action; }
logMsg("xlsx: " . basename($xlsx) . " (" . round(filesize($xlsx)/1024) . " KB, mtime " . date('Y-m-d H:i', filemtime($xlsx)) . ")");

logMsg("Leyendo xlsx Handel_D…");
$xlsxStats = [];
try { $byMc = loadYachticonXlsx($xlsx, $xlsxStats); }
catch (Exception $e) { logMsg("ERROR xlsx: " . $e->getMessage()); goto end_action; }
logMsg("Filas xlsx totales: {$xlsxStats['total_rows']} | descartadas: Auslauf={$xlsxStats['skip_auslauf']} sin_MC={$xlsxStats['skip_no_mc']} sin_artikel={$xlsxStats['skip_no_artikel']} sin_PVP={$xlsxStats['skip_no_price']} duplicados_MC=" . count($xlsxStats['skip_dup_mc']));
logMsg("Candidatos válidos por MC único: " . count($byMc));

$urlMap = ytLoadUrlMap();
logMsg("Cache de URLs por SKU cargado: " . count($urlMap) . " SKUs mapeados");

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_error) { logMsg("ERROR DB: " . $mysqli->connect_error); goto end_action; }
$mysqli->set_charset('utf8');

if (!is_dir(IMG_ABS_DIR)) @mkdir(IMG_ABS_DIR, 0775, true);
if (!is_dir(YT_CACHE_DIR)) @mkdir(YT_CACHE_DIR, 0775, true);

logMsg("Cargando IDs existentes en BD…");
$existing = buildExistingMap($mysqli);
logMsg("  → " . count($existing) . " referencias ya en BD (filtrado a Yachticon + EAN global)");

$createdCats = [];
$parentId = ensureParentCategory($mysqli, $dryRun, $createdCats);
logMsg("Categoría padre: " . ($parentId > 0 ? "id=$parentId" : "(se crearía en execute)"));
$subcatCache = [];

// Pre-filter: dedup, parse precio, mapear breadcrumb subcat
$candidates = [];
$skipExist = $skipBadPrice = $skipNoData = $skipNotInCodes = 0;
$subcatTotals = [];

if (!empty($onlyCodes)) {
    logMsg("Filtro por SKUs/Artikel/EAN específicos: " . count($onlyCodes) . " códigos solicitados");
}

foreach ($byMc as $mc => $row) {
    $artikelTrunc = preg_replace('/\.00000$/', '', $row['ARTIKEL']);

    // Filtro por códigos: match contra MC, Artikel-Nr completo, Artikel truncado o EAN
    if (!empty($onlyCodes)) {
        $hit = isset($onlyCodes[strtolower($mc)])
            || isset($onlyCodes[strtolower($row['ARTIKEL'])])
            || isset($onlyCodes[strtolower($artikelTrunc)])
            || ($row['EAN'] !== '' && isset($onlyCodes[strtolower($row['EAN'])]));
        if (!$hit) { $skipNotInCodes++; continue; }
    }

    if (isset($existing[strtolower($mc)])) { $skipExist++; continue; }
    if (isset($existing[strtolower($artikelTrunc)])) { $skipExist++; continue; }
    if (isset($existing[strtolower($row['ARTIKEL'])])) { $skipExist++; continue; }
    if (!empty($row['EAN']) && isset($existing[strtolower($row['EAN'])])) { $skipExist++; continue; }

    // PVP_neto a partir de K (gross con 19% MwSt DE)
    $pvpGrossDe = (float) $row['PVP_DE_G'];
    $retailNet  = $pvpGrossDe / (1 + VAT_RATE_DE);
    if ($retailNet <= 0) { $skipBadPrice++; continue; }
    $cost = ytParseNum($row['COST']);
    if ($cost === null || $cost <= 0) { $skipBadPrice++; continue; }

    $row['_RETAIL_NET'] = round($retailNet, 4);
    $row['_COST']       = round($cost, 4);
    $row['_PRICE']      = roundToNickel($retailNet);
    $row['_G1']         = roundToNickel($retailNet * G1_MULT);
    $row['_WEIGHT']     = 1.0;

    // URLs ES/EN desde mapping por Artikel-Nr
    $urlEs = $urlMap[$row['ARTIKEL']]['url_es'] ?? '';
    $urlEn = $urlMap[$row['ARTIKEL']]['url_en'] ?? '';
    if ($urlEs === '' && $urlEn === '') { $skipNoData++; continue; }
    $row['_URL_ES'] = $urlEs;
    $row['_URL_EN'] = $urlEn;

    $candidates[$mc] = $row;
}
logMsg("Pre-filtro: candidatos={" . count($candidates) . "} skip_existentes=$skipExist | skip_precio=$skipBadPrice | skip_sin_pagina_web=$skipNoData" . (!empty($onlyCodes) ? " | skip_no_en_codes=$skipNotInCodes" : ""));
if (!empty($onlyCodes) && count($candidates) === 0) {
    logMsg("⚠️  Ningún código de los " . count($onlyCodes) . " solicitados pasó el filtro. Verifica MC/Artikel-Nr/EAN y que no estén ya en BD ni sean Auslaufartikel.");
}

$nInserted = $nWithImg = $imgFail = $skippedNoImg = $apiFails = $formatFail = $errors = 0;
$ts0 = microtime(true);

foreach ($candidates as $mc => $row) {
    if ($max > 0 && $nInserted >= $max) { logMsg("Alcanzado límite max=$max."); break; }

    // Cargar HTML cacheado de ES/EN (acepta .html.gz o legacy .html)
    $htmlEs = !empty($row['_URL_ES']) ? ytCachePageRead($row['_URL_ES'], 'es') : '';
    $htmlEn = !empty($row['_URL_EN']) ? ytCachePageRead($row['_URL_EN'], 'en') : '';

    $parsedEs = $htmlEs !== '' ? ytParseProductPage($htmlEs) : null;
    $parsedEn = $htmlEn !== '' ? ytParseProductPage($htmlEn) : null;
    if ($parsedEs === null && $parsedEn === null) $apiFails++;

    // Imágenes: web ES + EN combinadas. Dedup por PATH (los binarios son los mismos
    // en yachticon.es y yachticon.com — Shopware sirve desde el mismo bucket).
    $imageUrls = array_merge(($parsedEs['images'] ?? []), ($parsedEn['images'] ?? []));
    $seenByPath = []; $unique = [];
    foreach ($imageUrls as $u) {
        // Path-only key: descarta el host → "yachticon.{es,com}/media/<hash>/X.jpg" → "media/<hash>/X.jpg"
        $path = preg_replace('#^https?://[^/]+/#', '', $u);
        if (!isset($seenByPath[$path])) { $seenByPath[$path] = true; $unique[] = $u; }
    }
    $imageUrls = $unique;
    if (empty($imageUrls)) {
        $skippedNoImg++;
        logMsg("SKIP MC=$mc ($row[ARTIKEL]): sin imagen en página web");
        continue;
    }

    // Nombre: ES desde web, EN desde web, fallback al alemán xlsx
    $nameEsRaw = $parsedEs['name'] ?? '';
    if ($nameEsRaw === '') $nameEsRaw = $row['DESC_DE'];
    $nameEnRaw = $parsedEn['name'] ?? '';
    if ($nameEnRaw === '') $nameEnRaw = $row['DESC_DE'];
    $nameEs = ytApplySuffix($nameEsRaw);
    $nameEn = ytApplySuffix($nameEnRaw);

    // Descripción
    $rawDescEs = trim($parsedEs['desc'] ?? '');
    if ($rawDescEs === '' && $row['DESC_DE'] !== '') $rawDescEs = '<p>' . htmlspecialchars($row['DESC_DE']) . '</p>';
    $rawDescEn = trim($parsedEn['desc'] ?? '');
    if ($rawDescEn === '' && $row['DESC_DE'] !== '') $rawDescEn = '<p>' . htmlspecialchars($row['DESC_DE']) . '</p>';

    $descEs = $rawDescEs;
    $descEn = $rawDescEn;
    if (!$skipFormat && !$dryRun) {
        if ($rawDescEs !== '') {
            $inLen = mb_strlen(strip_tags($rawDescEs), 'UTF-8');
            $fmt = llmCall(LLM_FORMAT_PROMPT_ES, $rawDescEs, 2500);
            if (ytFormatLooksValid($fmt, $inLen)) $descEs = $fmt; else $formatFail++;
        }
        if ($rawDescEn !== '') {
            $inLen = mb_strlen(strip_tags($rawDescEn), 'UTF-8');
            $fmt = llmCall(LLM_FORMAT_PROMPT_EN, $rawDescEn, 2500);
            if (ytFormatLooksValid($fmt, $inLen)) $descEn = $fmt; else $formatFail++;
        }
    }
    $descEs = mbReformatDescription($descEs);
    $descEn = mbReformatDescription($descEn);

    // Subcat = top del breadcrumb ES (1er nivel debajo de "Inicio"). Fallback: VARIOS.
    $subcatName = FALLBACK_SUBCAT;
    if (!empty($parsedEs['breadcrumb'][0])) {
        $top = $parsedEs['breadcrumb'][0];
        if ($top !== '' && mb_strlen($top, 'UTF-8') <= 40) $subcatName = $top;
    }
    $subcatId = getOrCreateSubcategory($mysqli, $subcatName, $parentId, $dryRun, $subcatCache, $createdCats);
    $subcatTotals[$subcatName] = ($subcatTotals[$subcatName] ?? 0) + 1;

    // Filtro subcat seleccionada (se ignora si hay códigos específicos)
    if (empty($onlyCodes)) {
        $selUp = mb_strtoupper($selectedSubcat, 'UTF-8');
        if ($selectedSubcat !== 'all' && mb_strtoupper($subcatName, 'UTF-8') !== $selUp) continue;
    }

    if ($dryRun) {
        $nInserted++;
        if ($nInserted <= 15) {
            logMsg(sprintf("  WOULD INSERT MC=%s art=%s subcat='%s' retail_net=%.2f price=%.2f cost=%.2f g1=%.2f imgs=%d name='%s'",
                $mc, $row['ARTIKEL'], $subcatName, $row['_RETAIL_NET'], $row['_PRICE'], $row['_COST'], $row['_G1'],
                count($imageUrls), mb_substr($nameEs, 0, 60, 'UTF-8')));
        }
        continue;
    }

    $tmpFiles = downloadImagesToTmp($imageUrls, MAX_SUBIMAGES + 1);
    if (empty($tmpFiles)) {
        $skippedNoImg++; $imgFail++;
        logMsg("SKIP MC=$mc art={$row['ARTIKEL']}: " . count($imageUrls) . " URLs, ninguna descargada >4KB");
        continue;
    }

    $mysqli->begin_transaction();
    try {
        $qmodel = $mysqli->real_escape_string($mc);
        $qref   = $mysqli->real_escape_string($row['ARTIKEL']);
        $qean   = $mysqli->real_escape_string(isValidEan13($row['EAN']) ? $row['EAN'] : '');
        $price  = number_format($row['_PRICE'], 4, '.', '');
        $cost   = number_format($row['_COST'], 4, '.', '');
        $weight = number_format($row['_WEIGHT'], 3, '.', '');
        $sql = "INSERT INTO products (products_quantity, check_stock, products_model, products_image, products_price, products_cost, products_date_added, products_weight, products_status, products_tax_class_id, manufacturers_id, product_ean, reference_prov, products_import_origin)
            VALUES (0, 0, \"$qmodel\", \"\", $price, $cost, NOW(), $weight, 2, " . TAX_CLASS_IVA21 . ", " . MFG_YACHTICON . ", \"$qean\", \"$qref\", \"" . ORIGIN_FLAG . "\")";
        if (!$mysqli->query($sql)) throw new Exception("products: " . $mysqli->error);
        $pid = (int) $mysqli->insert_id;

        $qNameEs = $mysqli->real_escape_string($nameEs);
        $qDescEs = $mysqli->real_escape_string($descEs);
        $qNameEn = $mysqli->real_escape_string($nameEn);
        $qDescEn = $mysqli->real_escape_string($descEn);
        if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_ES . ", \"$qNameEs\", \"$qDescEs\", 0)")) throw new Exception("desc ES: " . $mysqli->error);
        if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_EN . ", \"$qNameEn\", \"$qDescEn\", 0)")) throw new Exception("desc EN: " . $mysqli->error);

        $targetCatId = $subcatId > 0 ? $subcatId : $parentId;
        if (!$mysqli->query("INSERT INTO products_to_categories (products_id, categories_id) VALUES ($pid, $targetCatId)")) throw new Exception("p2c: " . $mysqli->error);

        $g1 = number_format($row['_G1'], 4, '.', '');
        if (!$mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . G1_GROUP_ID . ", $pid, $g1, 1, 1)")) throw new Exception("g1: " . $mysqli->error);

        $slug = ytSlugify(ytStripSuffix($nameEs ?: $nameEn));
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
        $mysqli->commit();

        // EAN interno si el del feed no es válido
        if (!isValidEan13($row['EAN'])) {
            $genEan = generateInternalEan13($pid, EAN_INTERNAL_PREFIX);
            if ($genEan !== '') {
                $mysqli->query("UPDATE products SET product_ean='" . $mysqli->real_escape_string($genEan) . "' WHERE products_id=$pid AND (product_ean IS NULL OR product_ean='' OR LENGTH(product_ean)<>13)");
            }
        }

        $nInserted++;
        $nSubs = isset($imgFinalNames) ? count($imgFinalNames) : 0;
        logMsg(sprintf("OK pid=%d MC=%s art=%s subcat='%s' price=%.2f cost=%.2f g1=%.2f imgs=%d name='%s'",
            $pid, $mc, $row['ARTIKEL'], $subcatName, $row['_PRICE'], $row['_COST'], $row['_G1'],
            1 + $nSubs, mb_substr($nameEs, 0, 50, 'UTF-8')));
    } catch (Exception $e) {
        $mysqli->rollback();
        $errors++;
        foreach ($tmpFiles as $tmpAbs) { if (file_exists($tmpAbs)) @unlink($tmpAbs); }
        logMsg("ERROR MC=$mc: " . $e->getMessage());
    }
}

$elapsed = microtime(true) - $ts0;
logMsg("==================== RESUMEN ====================");
logMsg(sprintf("Insertados: %d en %.1fs", $nInserted, $elapsed));
logMsg("Con imagen: $nWithImg | fallos descarga img: $imgFail | skip sin imagen: $skippedNoImg | sin parseo web ES+EN: $apiFails | maquetado HTML fallido: $formatFail | errores INSERT: $errors");
if (!empty($subcatTotals)) {
    arsort($subcatTotals);
    logMsg("Distribución por subcategoría (candidatos): " . substr(json_encode($subcatTotals, JSON_UNESCAPED_UNICODE), 0, 600));
}
if (!empty($createdCats)) {
    logMsg("Categorías " . ($dryRun ? "que se crearían" : "creadas") . " (" . count($createdCats) . "):");
    foreach ($createdCats as $c) logMsg("  · $c");
}

end_action:
?>
    </div>
    <p style="margin-top:15px;"><a href="<?php echo tep_href_link('import-yachticon-altas.php'); ?>" class="xbutton small hv9">← Volver</a></p>

<?php else: ?>
    <h2>Importador Yachticon (altas)</h2>
    <?php
        $xlsx = findNewestXlsx(YT_DIR);
        if (!$xlsx) {
            echo '<p style="color:red;">No hay xlsx en ' . YT_DIR . '</p>';
        } else {
            echo '<p style="color:#666;font-size:13px;">xlsx detectado: <code>' . htmlspecialchars(basename($xlsx)) . '</code> (' . round(filesize($xlsx)/1024) . ' KB, mtime ' . date('Y-m-d H:i', filemtime($xlsx)) . ')</p>';
        }
        $urlMap = ytLoadUrlMap();
        $cntEs = file_exists(YT_CACHE_DIR . 'product_urls_es.json') ? count(json_decode(@file_get_contents(YT_CACHE_DIR . 'product_urls_es.json'), true) ?: []) : 0;
        $cntEn = file_exists(YT_CACHE_DIR . 'product_urls_en.json') ? count(json_decode(@file_get_contents(YT_CACHE_DIR . 'product_urls_en.json'), true) ?: []) : 0;
        // Pending
        $pendEs = 0; $pendEn = 0;
        if ($cntEs > 0) {
            $list = json_decode(@file_get_contents(YT_CACHE_DIR . 'product_urls_es.json'), true) ?: [];
            foreach ($list as $u) if (!ytCachePageExists($u, 'es')) $pendEs++;
        }
        if ($cntEn > 0) {
            $list = json_decode(@file_get_contents(YT_CACHE_DIR . 'product_urls_en.json'), true) ?: [];
            foreach ($list as $u) if (!ytCachePageExists($u, 'en')) $pendEn++;
        }
    ?>
    <p>
        Lee el xlsx más reciente de <code><?php echo YT_DIR; ?></code> (sheet <code>Handel_D</code>),
        crawlea <code>yachticon.es</code> y <code>yachticon.com</code> (Shopware) para nombre/descripción/imágenes/breadcrumb,
        y crea productos bajo la categoría padre <strong>"<?php echo PARENT_CATEGORY_NAME_ES; ?>"</strong> con subcategorías 1-nivel
        a partir del primer nivel del breadcrumb scrapeado.
    </p>
    <p style="background:#fffbe6;border:1px solid #ffd700;padding:10px;border-radius:4px;font-size:13px;">
        <strong>Reglas siempre aplicadas</strong>:<br>
        • Manufacturer fijo: <code><?php echo MFG_YACHTICON; ?></code> (Yachticon).<br>
        • <code>products_model</code> = MC (col B). Filas con MC duplicado: la primera gana, las siguientes se descartan.<br>
        • Saltar filas con <code>Status</code> ≠ 0 (Auslaufartikel descontinuados).<br>
        • Skip si <code>products_model</code> / <code>reference_prov</code> / EAN ya existen (filtrado a Yachticon en BD; EAN global).<br>
        • Pricing: Retail_net = K/1.19 (quitar IVA alemán 19%) → <code>price</code> = roundToNickel(Retail_net) | <code>cost</code> = J | <code>G1</code> = roundToNickel(Retail_net × <?php echo G1_MULT; ?>). Todo neto, PVP final en múltiplos de 0,05 €.<br>
        • <strong>Sin imagen en la web → no se importa.</strong> Máx <?php echo MAX_SUBIMAGES; ?> sub-imágenes.<br>
        • Sufijo "<code><?php echo NAME_SUFFIX; ?></code>" al final de cada nombre.<br>
        • Sin variantes. Stock NO se toca (qty=0, status=2).<br>
        • EAN del xlsx (col F) si pasa checksum; si no → interno prefijo <?php echo EAN_INTERNAL_PREFIX; ?>.<br>
        • Tax class 1 (IVA 21%).
    </p>

    <fieldset style="background:#f5faff;border:1px solid #c0d8ee;padding:15px;border-radius:5px;margin-top:10px;">
        <legend><strong>Paso 1 — Cache de URLs y páginas (obligatorio antes del primer import)</strong></legend>
        <p style="font-size:13px;color:#444;">
            URLs sitemap: <strong>ES</strong>=<?php echo $cntEs; ?>, <strong>EN</strong>=<?php echo $cntEn; ?>
            · Páginas pendientes de crawlear: <strong>ES</strong>=<?php echo $pendEs; ?>, <strong>EN</strong>=<?php echo $pendEn; ?>
            · SKUs mapeados: <strong><?php echo count($urlMap); ?></strong>
        </p>
        <p style="font-size:12px;color:#666;">
            Cada click crawlea hasta <?php echo CRAWL_CHUNK_PAGES; ?> páginas (con <?php echo CRAWL_PARALLEL; ?> paralelas).
            Pulsa varias veces hasta que ambos contadores estén a 0.
        </p>
        <form method="get" style="display:inline;">
            <input type="hidden" name="action" value="build_cache">
            <button type="submit" class="xbutton small hv9">Construir cache (chunk)</button>
        </form>
    </fieldset>

    <form method="post" style="background:#f5f5f5;padding:15px;border-radius:5px;margin-top:15px;">
        <p>
            <strong>SKUs específicos</strong> (opcional, ignora "Inserts máximos" y filtro de subcategoría):<br>
            <textarea name="codes" rows="3" style="width:100%;font-family:monospace;" placeholder="Uno o varios MC, Artikel-Nr (1.0101.00001.00000) o EAN, separados por coma, espacio o salto de línea. Ej: 7  19  1.0201.00102.00000  4031396000011"><?php echo htmlspecialchars($onlyCodesRaw); ?></textarea>
        </p>
        <p>
            <strong>Subcategoría a importar</strong> (basada en breadcrumb scrapeado):
            <select name="subcat" style="min-width:300px;">
                <option value="all">— Todas —</option>
                <?php
                if (!empty($urlMap)) {
                    // Estimación rápida de subcategorías a partir de la cache: tomar breadcrumb top de páginas ES cacheadas
                    $subCounts = [];
                    $sample = 0; $maxSample = 1500;
                    foreach ($urlMap as $sku => $u) {
                        if (empty($u['url_es'])) continue;
                        if ($sample >= $maxSample) break;
                        $html = ytCachePageRead($u['url_es'], 'es');
                        if ($html === '') continue;
                        $sample++;
                        $p = ytParseProductPage($html);
                        $top = $p['breadcrumb'][0] ?? '';
                        if ($top !== '') $subCounts[$top] = ($subCounts[$top] ?? 0) + 1;
                    }
                    uksort($subCounts, fn($a, $b) => strcasecmp($a, $b));
                    foreach ($subCounts as $name => $c) {
                        $sel = (mb_strtoupper($selectedSubcat, 'UTF-8') === mb_strtoupper($name, 'UTF-8')) ? ' selected' : '';
                        echo '<option value="' . htmlspecialchars($name) . '"' . $sel . '>' . htmlspecialchars($name) . ' (' . $c . ($sample >= $maxSample ? '+' : '') . ')</option>';
                    }
                }
                ?>
            </select>
        </p>
        <p><label><input type="checkbox" name="dry_run" value="1" checked> Dry-run (sin cambios)</label></p>
        <p><label><input type="checkbox" name="skip_format" value="1"> Saltar maquetado LLM</label></p>
        <p>Inserts máximos por ejecución (0 = sin límite):
            <input type="number" name="max" value="10" min="0" style="width:80px;">
        </p>
        <button type="submit" name="action" value="dry_run" class="xbutton small hv9">Dry-run</button>
        <button type="submit" name="action" value="execute" class="xbutton small hv9 verde" onclick="return confirm('¿Ejecutar de verdad? Insertará productos en la BD.');">Ejecutar</button>
    </form>

    <p style="margin-top:20px;color:#888;font-size:12px;">
        <strong>Detalles técnicos</strong>:<br>
        - Shopware en yachticon.es / yachticon.com. Crawl chunked vía sitemap.<br>
        - El SKU de Yachticon (col A xlsx) se extrae de la página web ("Número de producto") para construir el map.<br>
        - Cache local: <code><?php echo YT_CACHE_DIR; ?></code>.<br>
        - Bloque LLM aplica las 8 reglas estéticas MB.<br>
        - Output streaming en tiempo real.
    </p>
<?php endif; ?>
</div>
</td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
