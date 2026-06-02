<?php
require 'includes/application_top.php';
require_once DIR_FS_DOCUMENT_ROOT . 'includes/vendor/autoload.php';
require_once dirname(__FILE__) . '/includes/mb_reformat_helpers.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);

/* ──────────────────────────────────────────────────────────────────────────
 * Importador RAM Mounts (altas)
 *
 * Fuentes:
 *   - xlsx  /import/Ram/1. Lista de Precios Generales.xlsx  (hoja "Pricing"):
 *       A = RAM SKU  ·  B = UPC (UPC-A 12 díg → EAN-13 con un 0 delante)
 *       C = DESCRIPTION (no se usa)  ·  D = PVP (IVA incluido)  ·  E = PVE (= COSTE)
 *   - Web   https://rammount.com  (Shopify, products.json):
 *       nombre (title), descripción (body_html, inglés), imágenes, y product_type.
 *
 * Enlace: variant.sku (Shopify) == RAM SKU del xlsx. La web aporta qué productos
 * existen + nombre/descripción/imágenes/categoría; el xlsx aporta SKU, EAN y PRECIOS.
 * El catálogo Shopify es 100% mono-variante; igualmente se soporta multi-variante
 * (se importa cada SKU priceable como producto suelto).
 *
 * Decisiones (usuario 2026-06-01):
 *   - products_cost  = PVE (col E del xlsx).
 *   - products_price = roundToNickel(PVP_xlsx / 1,21)   (el PVP del xlsx es IVA incluido).
 *   - G1 (Profesionales) = roundToNickel(tiers de margen + piso cost×1,10).
 *   - SIN prefijo de nombre (el título ya empieza por "RAM®"); se limpia ®/™/©.
 *   - Categorías: "RAM Mounts Nuevos" (status 0) > subcategoría por product_type Shopify.
 *   - Bilingüe ES+EN (traducción + maquetado vía LLM; glosario de soportes/mounts).
 *   - EAN real del xlsx (UPC-A→EAN-13); sin EAN válido → interno (prefijo 28, id-based).
 *   - Sin imagen → no se importa. Skip si SKU/EAN ya está en BD o en lista negra.
 * ────────────────────────────────────────────────────────────────────────── */

const RAM_DIR        = '/home/francobordo/public_html/import/Ram/';
const RAM_CACHE_DIR  = RAM_DIR . 'cache/';
const RAM_CACHE      = RAM_CACHE_DIR . 'catalog.json';
const RAM_SKUMAP_CACHE = RAM_CACHE_DIR . 'skumap.json';
const RAM_XLSX_SHEET = 'Pricing';
const SHOP_BASE      = 'https://rammount.com';
define('IMG_ABS_DIR', dirname(dirname(__FILE__)) . '/images/productos/');

const PARENT_CATEGORY_NAME_ES = 'RAM Mounts Nuevos';
const PARENT_CATEGORY_NAME_EN = 'RAM Mounts New';
const TAX_CLASS_IVA21 = 1;
const LANG_ID_ES      = 3;
const LANG_ID_EN      = 1;
const G1_GROUP_ID     = 1;
const PRODUCT_NAME_MAX = 128;
const MFG_NAME        = 'RAM Mounts';
const IMG_HTTP_TIMEOUT = 20;
const IMG_MIN_BYTES   = 3072;
const MAX_SUBIMAGES   = 10;          // RAM trae galerías de hasta 11 fotos (distintos montajes/ángulos)
const ORIGIN_FLAG     = 'ram';
const EAN_INTERNAL_PREFIX = 28;           // compartido (id-based → sin colisión). Raro: ~100% traen UPC real.
const RAM_VAT_RATE    = 0.21;
const G1_FLOOR_FACTOR = 1.10;
const DEFAULT_WEIGHT  = 1.0;

/* product_type Shopify → nombre ES de la subcategoría (clave normalizada sin ®/™). EN = el propio type. */
const RAM_TYPE_ES = [
    'Components'            => 'Componentes',
    'Universal Mounts'     => 'Soportes Universales',
    'GPS Mounts'           => 'Soportes para GPS',
    'Phone Mounts'         => 'Soportes para Móvil',
    'Tablet Mounts'        => 'Soportes para Tablet',
    'IntelliSkin'          => 'IntelliSkin',
    'Laptop Mounts'        => 'Soportes para Portátil',
    'Camera Mounts'        => 'Soportes para Cámara',
    'Monitor Mounts'       => 'Soportes para Monitor',
    'Fish Finder Mounts'   => 'Soportes para Sonda',
    'Fishing Rod Holders'  => 'Portacañas',
    'Radio Mounts'         => 'Soportes para Radio',
    'Printer Mounts'       => 'Soportes para Impresora',
    'Cup Holder Mounts'    => 'Soportes para Posavasos',
    'Handheld PC Mounts'   => 'Soportes para PC de Mano',
    'Notepad Mounts'       => 'Soportes para Bloc de Notas',
    'Transducer Mounts'    => 'Soportes para Transductor',
    'Spotlight Mounts'     => 'Soportes para Foco',
    'Paddle Holders'       => 'Soportes para Remo',
    'Radar Detector Mounts'=> 'Soportes para Detector de Radar',
    'Scanner Gun Mounts'   => 'Soportes para Lector de Códigos',
    'Gun Holster Mounts'   => 'Soportes para Pistolera',
];

const LLM_URL   = 'http://217.127.199.171:28001/v1/chat/completions';
const LLM_MODEL = 'qwen36-sakamaki-nvfp4';

const LLM_NAME_PROMPT_ES = "Traduce este nombre de producto (soportes/monturas RAM Mounts) del INGLÉS al ESPAÑOL. Conserva la marca RAM y nombres propios de modelo/serie (Tough-Claw, Tough-Ball, X-Grip, EZ-Roll'r, Quick-Grip, IntelliSkin, GDS, Pin-Lock, Tough-Bar, Form-Fit…), códigos (RAM-…, RAP-…, RAM-HOL-…), medidas y unidades. Glosario EN→ES: mount=soporte, ball=bola, socket=rótula, socket arm=brazo de rótula, double socket arm=brazo de doble rótula, arm=brazo, base=base, cradle=cuna, clamp=pinza, rail clamp=pinza de raíl, rail=raíl, handlebar=manillar, suction cup=ventosa, adapter=adaptador, bracket=soporte, knob=pomo, plate=placa, holder=soporte, mounting=montaje, diamond base=base diamante, drill-down=de atornillar, composite=compuesto, wireless charger=cargador inalámbrico. Responde SOLO con el nombre traducido, una línea, sin comillas.";
const LLM_TRANSLATE_ES = "Traduce el siguiente texto descriptivo de producto (soportes/monturas RAM Mounts) del INGLÉS al ESPAÑOL. Conserva ÍNTEGRAMENTE toda la información, números, medidas y unidades (mm, cm, in, lbs, kg, °). Conserva la marca RAM y nombres de modelo/serie. Glosario EN→ES: mount=soporte, ball=bola, socket=rótula, socket arm=brazo de rótula, double socket arm=brazo de doble rótula, arm=brazo, base=base, cradle=cuna, clamp=pinza, rail=raíl, handlebar=manillar, suction cup=ventosa, adapter=adaptador, bracket=soporte, knob=pomo, plate=placa, holder=soporte, rubber ball=bola de goma, spring-loaded=con muelle, device=dispositivo, rugged=resistente, vibration=vibración. NO resumas ni inventes. Devuelve SOLO la traducción en español, sin comentarios.";
const LLM_FORMAT_PROMPT_ES = "Eres un experto en maquetar fichas de producto. Recibes una descripción comercial YA EN ESPAÑOL y la transformas en HTML legible.\n\nREGLAS:\n1. Primer <p>: frase introductoria (máx 5 frases por <p>).\n2. Si hay >6 características, agrúpalas bajo títulos <p><strong>Título</strong></p> (nunca <h1>-<h6>); antes de cada título inserta <p>&nbsp;</p>.\n3. Cada característica en su propio <p>• texto</p> (nunca <ul>/<li>). Concepto clave (1-4 palabras) en <strong> + dos puntos.\n4. Elimina ™ ® ©. Palabras EN MAYÚSCULAS de 4+ letras → Title Case (conserva acrónimos: LED, IP, USB, UV, PVC, GPS, GDS, RAM).\n5. NO traduzcas, NO resumas, NO inventes: conserva TODO el texto, solo añades estructura HTML.\n6. Etiquetas permitidas: <p>, <strong>, <a>. Prohibidas: <h1>-<h6>, <br>, <div>, <span>, <ul>, <li>, <table>, <ol>.\n7. Salida: SOLO el HTML, sin markdown ni comentarios.";
const LLM_FORMAT_PROMPT_EN = "You format product datasheets. You receive a description ALREADY IN ENGLISH and turn it into clean HTML.\n\nRULES:\n1. First <p>: intro sentence (max 5 sentences per <p>).\n2. If >6 features, group under <p><strong>Title</strong></p> headings (never <h1>-<h6>); insert <p>&nbsp;</p> before each title.\n3. Each feature in its own <p>• text</p> (never <ul>/<li>). Key concept (1-4 words) in <strong> + colon.\n4. Remove ™ ® ©. ALL-CAPS words 4+ letters → Title Case (keep acronyms: LED, IP, USB, UV, PVC, GPS, GDS, RAM).\n5. DO NOT translate, summarize or invent: keep ALL text, only add HTML structure.\n6. Allowed tags: <p>, <strong>, <a>. Forbidden: <h1>-<h6>, <br>, <div>, <span>, <ul>, <li>, <table>, <ol>.\n7. Output: ONLY the HTML, no markdown, no comments.";

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$max    = isset($_POST['max']) ? (int) $_POST['max'] : (isset($_GET['max']) ? (int) $_GET['max'] : 0);
$dryRun = isset($_POST['dry_run']) || isset($_GET['dry_run']) || $action === 'dry_run';
$skipTranslation = isset($_POST['skip_translation']) || isset($_GET['skip_translation']);
$forceRefresh = isset($_POST['refresh']) || isset($_GET['refresh']);
$handlesParam = trim((string) ($_POST['handles'] ?? $_GET['handles'] ?? ''));
$selectedType = trim((string) ($_POST['ptype'] ?? $_GET['ptype'] ?? 'all'));

function logMsg($msg) {
    $line = '[' . date('H:i:s') . '] ' . $msg . "\n";
    echo '<pre style="margin:0;padding:2px 8px;border-bottom:1px solid #eee;white-space:pre-wrap;overflow-wrap:break-word;word-break:break-word;font-family:monospace;font-size:12px;">' . htmlspecialchars($line) . '</pre>';
    @flush();
}

/* ── EAN-13 / UPC-A ── */
function ean13Checksum($payload12) {
    if (strlen($payload12) !== 12 || !ctype_digit($payload12)) return -1;
    $sum = 0;
    for ($i = 0; $i < 12; $i++) { $d = (int) $payload12[$i]; $sum += ($i % 2 === 0) ? $d : $d * 3; }
    return (10 - ($sum % 10)) % 10;
}
function isValidEan13($ean) {
    $ean = trim((string) $ean);
    if (strlen($ean) !== 13 || !ctype_digit($ean)) return false;
    return ean13Checksum(substr($ean, 0, 12)) === (int) $ean[12];
}
/** UPC-A (12 díg) → EAN-13 anteponiendo un 0. Acepta ya-EAN13. Devuelve '' si no es válido. */
function ramUpcToEan13($raw) {
    $d = preg_replace('/\D/', '', (string) $raw);
    if ($d === '') return '';
    if (strlen($d) === 11) $d = '0' . $d;            // por si el xlsx perdió un cero a la izquierda
    if (strlen($d) === 12) { $e = '0' . $d; return isValidEan13($e) ? $e : ''; }
    if (strlen($d) === 13) return isValidEan13($d) ? $d : '';
    return '';
}
function generateInternalEan13($id, $providerPrefix) {
    $pp = (int) $providerPrefix;
    if ($pp < 20 || $pp > 28) return '';
    if ($id <= 0 || $id > 9999999999) return '';
    $payload = str_pad((string) $pp, 2, '0', STR_PAD_LEFT) . str_pad((string) $id, 10, '0', STR_PAD_LEFT);
    if (strncmp($payload, '299', 3) === 0) return '';
    $check = ean13Checksum($payload);
    return $check < 0 ? '' : ($payload . $check);
}

/** Redondea un PVP NETO de modo que el precio CON IVA quede en múltiplo de 0,05. */
function roundToNickel($net) {
    $withIva = ((float) $net) * (1 + RAM_VAT_RATE);
    $rounded = round($withIva * 20) / 20;
    return round($rounded / (1 + RAM_VAT_RATE), 4);
}
/** PVP del xlsx (IVA incluido) → products_price NETO con el bruto redondeado a 0,05. */
function priceNetFromGross($gross) {
    return roundToNickel(((float) $gross) / (1 + RAM_VAT_RATE));
}
/** G1 (Profesionales) con tiers de margen + piso cost×1.10. */
function calcG1Price($price, $cost) {
    $price = (float) $price; $cost = (float) $cost;
    if ($price <= 0) return 0.0;
    $mult = 0.90;
    if ($cost > 0) {
        $margin = ($price - $cost) / $price;
        if      ($margin >= 0.45) $mult = 0.75;
        elseif  ($margin >= 0.40) $mult = 0.80;
        elseif  ($margin >= 0.35) $mult = 0.82;
        elseif  ($margin >= 0.30) $mult = 0.85;
    }
    return round(max($price * $mult, $cost * G1_FLOOR_FACTOR), 4);
}

function ramSlugify($text, $maxLen = 50) {
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

/** Limpia el nombre web: decodifica entidades, quita ®/™/©, colapsa espacios, recorta. */
function ramCleanName($name) {
    $name = html_entity_decode((string) $name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $name = str_replace(['®', '™', '©'], '', $name);
    $name = trim(preg_replace('/\s+/u', ' ', $name));
    if ($name === '') $name = '(sin nombre)';
    if (mb_strlen($name, 'UTF-8') > PRODUCT_NAME_MAX) $name = rtrim(mb_substr($name, 0, PRODUCT_NAME_MAX, 'UTF-8'));
    return $name;
}

/** Normaliza el product_type para buscar en RAM_TYPE_ES (quita ®/™/© y espacios). */
function ramTypeKey($type) {
    return trim(str_replace(['®', '™', '©'], '', (string) $type));
}

/** Limpia la descripción HTML (inglesa) a texto plano para el LLM. */
function ramCleanHtmlToText($html) {
    if ($html === null || $html === '') return '';
    $html = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html);
    $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
    $html = preg_replace('#<\s*br\s*/?\s*>#i', "\n", $html);
    $html = preg_replace('#</\s*(p|div|li|tr|h[1-6]|ul|ol|table|article|section)\s*>#i', "\n", $html);
    $html = preg_replace('#<\s*li\b[^>]*>#i', "- ", $html);
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace("\xc2\xa0", ' ', $text);
    $text = str_replace("\xef\xbf\xbd", '', $text);
    $lines = preg_split("/\r\n|\r|\n/", $text);
    $out = []; $empty = 0;
    foreach ($lines as $l) {
        $l = trim(preg_replace('/[ \t\x{A0}]+/u', ' ', $l));
        if ($l === '') { if ($empty < 1 && !empty($out)) $out[] = ''; $empty++; continue; }
        $out[] = $l; $empty = 0;
    }
    return trim(implode("\n", $out));
}

function ramBrowserUA() {
    return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';
}

function downloadImage($url, $destAbs) {
    if (empty($url)) return false;
    $url = trim($url);
    if (strpos($url, ' ') !== false) $url = str_replace(' ', '%20', $url);
    if (!preg_match('#^https?://#i', $url)) return false;
    $ch = curl_init($url);
    $fp = fopen($destAbs, 'wb');
    if (!$fp) return false;
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => IMG_HTTP_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => ramBrowserUA(),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
            'Referer: https://rammount.com/',
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
        $tmpAbs = IMG_ABS_DIR . 'ram-tmp-' . uniqid('', true) . '.jpg';
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
        usleep(400000);
    }
    return '';
}
function ramLlmLine($systemPrompt, $text) {
    $out = llmCall($systemPrompt, $text, 120);
    $out = trim(preg_replace('/\s+/u', ' ', strip_tags($out)));
    return trim($out, " \t\"'");
}
function ramFormatLooksValid($html, $minLenInput) {
    if (!is_string($html) || trim($html) === '') return false;
    if (stripos($html, '<p>') === false && stripos($html, '<strong>') === false) return false;
    $plainOut = mb_strlen(trim(strip_tags($html)), 'UTF-8');
    if ($minLenInput > 200 && $plainOut < $minLenInput * 0.4) return false;
    if (preg_match_all('#<p>\s*•\s*(.*?)</p>#is', $html, $m) && count($m[1]) >= 3) {
        $items = array_map(fn($t) => mb_strtolower(trim(strip_tags($t)), 'UTF-8'), $m[1]);
        if (count($items) - count(array_unique($items)) >= max(2, count($items) * 0.4)) return false;
    }
    return true;
}

/* ── xlsx → SKU → {cost (PVE), pvp (col D, IVA inc), ean} ── */
function ramFindXlsx() {
    $best = null; $bestT = 0;
    foreach (glob(RAM_DIR . '*.xlsx') ?: [] as $f) {
        if (strpos(basename($f), '~$') === 0) continue;
        $t = filemtime($f);
        if ($t > $bestT) { $bestT = $t; $best = $f; }
    }
    return $best;
}
/** Lee un valor numérico de una celda, resolviendo fórmulas vía el valor cacheado.
 *  (En este xlsx PVE = "=D*42%" es fórmula; PVP es número literal.) */
function ramCellNum($cell) {
    if ($cell->getDataType() === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA) {
        $v = $cell->getOldCalculatedValue();
        if ($v === null) { try { $v = $cell->getCalculatedValue(); } catch (Throwable $e) { $v = null; } }
        return is_numeric($v) ? (float) $v : null;
    }
    $v = $cell->getValue();
    return (is_numeric($v)) ? (float) $v : null;
}

function ramLoadSkuMap(&$err) {
    $err = '';
    $f = ramFindXlsx();
    if (!$f) { $err = 'No se encontró ningún .xlsx en ' . RAM_DIR; return []; }
    $sig = basename($f) . ':' . filemtime($f) . ':' . filesize($f);
    if (file_exists(RAM_SKUMAP_CACHE)) {
        $c = json_decode((string) file_get_contents(RAM_SKUMAP_CACHE), true);
        if (is_array($c) && ($c['sig'] ?? '') === $sig && isset($c['map']) && is_array($c['map'])) return $c['map'];
    }
    try {
        $reader = IOFactory::createReaderForFile($f);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([RAM_XLSX_SHEET]);
        $ss = $reader->load($f);
        $sh = $ss->getSheet(0);
    } catch (Throwable $e) {
        try { $reader = IOFactory::createReaderForFile($f); $reader->setReadDataOnly(true); $ss = $reader->load($f); $sh = $ss->getSheetByName(RAM_XLSX_SHEET) ?: $ss->getSheet(0); }
        catch (Throwable $e2) { $err = 'xlsx: ' . $e2->getMessage(); return []; }
    }
    $map = [];
    foreach ($sh->getRowIterator(2) as $row) {
        $ri  = $row->getRowIndex();
        $sku = strtoupper(trim((string) $sh->getCell('A' . $ri)->getValue()));
        if ($sku === '' || $sku === 'RAM SKU') continue;
        $upc = $sh->getCell('B' . $ri)->getValue();   // texto-fórmula ="123…"; el strip \D lo resuelve
        $pvp = ramCellNum($sh->getCell('D' . $ri));   // PVP, IVA incluido (número literal)
        $pve = ramCellNum($sh->getCell('E' . $ri));   // PVE = coste (fórmula =D*42% → valor cacheado)
        $map[$sku] = [
            'cost' => ($pve === null || $pve <= 0) ? null : $pve,
            'pvp'  => ($pvp === null || $pvp <= 0) ? null : $pvp,
            'ean'  => ramUpcToEan13($upc),
        ];
    }
    if (!empty($map)) {
        if (!is_dir(RAM_CACHE_DIR)) @mkdir(RAM_CACHE_DIR, 0775, true);
        @file_put_contents(RAM_SKUMAP_CACHE, json_encode(['sig' => $sig, 'map' => $map], JSON_UNESCAPED_UNICODE));
    }
    return $map;
}

/* ── Web Shopify → catálogo crudo (cacheado en disco) ── */
function ramFetchAllProducts() {
    $all = []; $page = 1;
    while ($page <= 40) {
        $ch = curl_init(SHOP_BASE . '/products.json?limit=250&page=' . $page);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => ramBrowserUA(),
            CURLOPT_TIMEOUT => 40,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);
        if ($resp === false || $code !== 200) break;
        $j = json_decode($resp, true);
        if (empty($j['products'])) break;
        foreach ($j['products'] as $p) $all[] = $p;
        if (count($j['products']) < 250) break;
        $page++;
    }
    return $all;
}
function ramLoadCatalog($forceRefresh, &$msg) {
    $msg = '';
    if (!$forceRefresh && file_exists(RAM_CACHE)) {
        $j = json_decode((string) file_get_contents(RAM_CACHE), true);
        if (is_array($j) && !empty($j)) { $msg = 'cache (' . date('Y-m-d H:i', filemtime(RAM_CACHE)) . ')'; return $j; }
    }
    $all = ramFetchAllProducts();
    if (!empty($all)) {
        if (!is_dir(RAM_CACHE_DIR)) @mkdir(RAM_CACHE_DIR, 0775, true);
        @file_put_contents(RAM_CACHE, json_encode($all, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $msg = 'web (descargado ' . count($all) . ')';
    } elseif (file_exists(RAM_CACHE)) {
        $all = json_decode((string) file_get_contents(RAM_CACHE), true) ?: [];
        $msg = 'cache (fallo web, usando copia previa)';
    }
    return $all;
}

/** Construye la lista de ítems importables: un producto francobordo por SKU priceable.
 *  Cada ítem hereda nombre/desc/imágenes/type del producto Shopify; precio/coste/EAN del xlsx. */
function ramBuildItems($catalog, $skuMap) {
    $items = [];
    foreach ($catalog as $p) {
        $images = [];
        foreach (($p['images'] ?? []) as $im) if (!empty($im['src'])) $images[] = $im['src'];
        // variantes priceables (con coste y PVP en el xlsx)
        $priceable = [];
        foreach (($p['variants'] ?? []) as $v) {
            $sku = strtoupper(trim((string) ($v['sku'] ?? '')));
            if ($sku === '' || !isset($skuMap[$sku])) continue;
            $cost = $skuMap[$sku]['cost']; $pvp = $skuMap[$sku]['pvp'];
            if ($cost === null || $cost <= 0 || $pvp === null || $pvp <= 0) continue;
            $vt = trim((string) ($v['title'] ?? ''));
            if (strcasecmp($vt, 'Default Title') === 0) $vt = '';
            $priceable[$sku] = ['vtitle' => $vt];
        }
        if (empty($priceable)) continue;
        $multi   = count($priceable) > 1;        // el catálogo es 100% mono-variante; defensivo
        $pidWeb  = (string) $p['id'];
        $name    = ramCleanName($p['title'] ?? '');
        $desc    = $p['body_html'] ?? '';
        $type    = ramTypeKey($p['product_type'] ?? '');
        $handle  = $p['handle'] ?? '';
        foreach ($priceable as $sku => $pv) {
            $cost  = round((float) $skuMap[$sku]['cost'], 4);
            $price = priceNetFromGross((float) $skuMap[$sku]['pvp']);
            $g1    = roundToNickel(calcG1Price($price, $cost));
            // multi-variante: desambigua el nombre con el título de variante; mono: nombre tal cual
            $nm = ($multi && $pv['vtitle'] !== '') ? ramCleanName($name . ' - ' . $pv['vtitle']) : $name;
            $items[$sku] = [
                'sku' => $sku, 'name' => $nm, 'desc' => $desc, 'type' => $type, 'handle' => $handle,
                'pid_web' => $pidWeb, 'images' => $images,
                '_COST' => $cost, '_PRICE' => $price, '_G1' => $g1, 'ean' => $skuMap[$sku]['ean'],
            ];
        }
    }
    return $items;
}

/* ── Dedup map (SKU scoped por fabricante/origin; EAN global) + lista negra ── */
function buildExistingMap($mysqli, $mfgId) {
    $existing = [];
    $f = ((int)$mfgId > 0)
        ? "(p.manufacturers_id = " . (int)$mfgId . " OR p.products_import_origin LIKE 'ram%')"
        : "(p.products_import_origin LIKE 'ram%')";
    foreach ([
        "SELECT LOWER(p.products_model) m FROM products p WHERE p.products_model<>'' AND $f",
        "SELECT LOWER(p.reference_prov) m FROM products p WHERE p.reference_prov<>'' AND $f",
    ] as $sql) {
        $r = $mysqli->query($sql);
        if ($r) while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
    }
    foreach ([
        "SELECT LOWER(product_ean) m FROM products WHERE product_ean<>'' AND product_ean IS NOT NULL",
        "SELECT LOWER(products_attributes_ean) m FROM products_attributes WHERE products_attributes_ean<>'' AND products_attributes_ean IS NOT NULL",
    ] as $sql) {
        $r = $mysqli->query($sql);
        if ($r) while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
    }
    require_once dirname(__FILE__) . '/includes/import_blacklist.php';
    $existing += fb_blacklist_keys();
    return $existing;
}

function ensureManufacturer($mysqli, $name, $dryRun, &$createdLog) {
    $q = $mysqli->real_escape_string($name);
    $r = $mysqli->query("SELECT manufacturers_id FROM manufacturers WHERE manufacturers_name='$q' LIMIT 1");
    if ($r && $row = $r->fetch_assoc()) return (int) $row['manufacturers_id'];
    if ($dryRun) { $createdLog[] = "fabricante '$name' (dry-run, no creado)"; return 0; }
    $mysqli->query("INSERT INTO manufacturers (manufacturers_name, date_added) VALUES ('$q', NOW())");
    $id = (int) $mysqli->insert_id;
    $createdLog[] = "fabricante '$name' (id=$id)";
    return $id;
}

function getOrCreateCategory($mysqli, $nameEs, $nameEn, $parentId, $status, $dryRun, &$cache, &$createdLog) {
    $nm = trim(preg_replace('/\s+/u', ' ', (string) $nameEs));
    if ($nm === '') $nm = 'VARIOS';
    $en = trim(preg_replace('/\s+/u', ' ', (string) ($nameEn ?: $nameEs)));
    $key = $parentId . '|' . mb_strtoupper($nm, 'UTF-8');
    if (isset($cache[$key])) return $cache[$key];
    $qName = $mysqli->real_escape_string($nm);
    $parentId = (int) $parentId;
    $r = $mysqli->query("SELECT c.categories_id FROM categories c
        INNER JOIN categories_description cd ON cd.categories_id=c.categories_id AND cd.language_id=" . LANG_ID_ES . "
        WHERE c.parent_id=$parentId AND UPPER(TRIM(cd.categories_name))=UPPER('$qName') LIMIT 1");
    if ($r && $row = $r->fetch_assoc()) { $cache[$key] = (int) $row['categories_id']; return $cache[$key]; }
    if ($dryRun) { $cache[$key] = 0; $createdLog[] = "categoría '$nm' bajo $parentId (dry-run)"; return 0; }
    $r = $mysqli->query("SELECT IFNULL(MAX(sort_order),0)+1 nso FROM categories WHERE parent_id=$parentId");
    $nso = (int) ($r->fetch_assoc()['nso'] ?? 1);
    $mysqli->query("INSERT INTO categories (parent_id, sort_order, date_added, last_modified, categories_status) VALUES ($parentId, $nso, NOW(), NOW(), " . (int)$status . ")");
    $newId = (int) $mysqli->insert_id;
    $mysqli->query("INSERT INTO categories_description (categories_id, language_id, categories_name) VALUES ($newId, " . LANG_ID_ES . ", '$qName')");
    $mysqli->query("INSERT INTO categories_description (categories_id, language_id, categories_name) VALUES ($newId, " . LANG_ID_EN . ", '" . $mysqli->real_escape_string($en) . "')");
    $cache[$key] = $newId;
    $createdLog[] = "categoría '$nm' (id=$newId) bajo $parentId";
    return $newId;
}

function loadJson($path) {
    if (!file_exists($path)) return null;
    $j = json_decode((string) file_get_contents($path), true);
    return is_array($j) ? $j : null;
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
    <h2>Importador RAM Mounts — <?php echo $dryRun ? 'DRY-RUN (sin cambios)' : 'EJECUCIÓN REAL'; ?></h2>
    <p><a href="<?php echo tep_href_link('import-ram-altas.php'); ?>" class="xbutton small hv9">← Volver</a></p>
    <div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n";
@flush();

logMsg("Modo: " . ($dryRun ? "dry-run" : "EXECUTE")
    . " | tipo=" . ($selectedType === 'all' ? 'TODOS' : $selectedType)
    . ($handlesParam !== '' ? " | handles=$handlesParam" : "")
    . ($skipTranslation ? " | sin LLM" : "")
    . ($max > 0 ? " | max=$max" : ""));

$skuErr = '';
$skuMap = ramLoadSkuMap($skuErr);
if ($skuErr) { logMsg("ERROR xlsx: $skuErr"); goto end_action; }
logMsg("xlsx: " . count($skuMap) . " SKUs con coste/PVP/EAN (" . basename(ramFindXlsx()) . ")");

$catMsg = '';
$catalog = ramLoadCatalog($forceRefresh, $catMsg);
if (empty($catalog)) { logMsg("ERROR: no se pudo cargar el catálogo web ni cache."); goto end_action; }
logMsg("Catálogo web: " . count($catalog) . " productos [$catMsg]");

$items = ramBuildItems($catalog, $skuMap);
logMsg("Ítems importables (web ∩ xlsx priceable): " . count($items));

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_error) { logMsg("ERROR DB: " . $mysqli->connect_error); goto end_action; }
$mysqli->set_charset('utf8');
if (!is_dir(IMG_ABS_DIR)) @mkdir(IMG_ABS_DIR, 0775, true);

$createdLog = []; $catCache = [];
$mfgId = ensureManufacturer($mysqli, MFG_NAME, $dryRun, $createdLog);
$rootCatId = getOrCreateCategory($mysqli, PARENT_CATEGORY_NAME_ES, PARENT_CATEGORY_NAME_EN, 0, 0, $dryRun, $catCache, $createdLog);

logMsg("Cargando IDs existentes en BD…");
$existing = buildExistingMap($mysqli, $mfgId);
logMsg("  → " . count($existing) . " referencias RAM / lista negra ya en BD");

$wantHandles = [];
if ($handlesParam !== '') foreach (preg_split('/[\s,;]+/', $handlesParam) as $h) { $h = trim($h); if ($h !== '') $wantHandles[strtolower($h)] = true; }

$nInserted = $skipExist = $skipNoImg = $errors = $formatFail = 0;
$ts0 = microtime(true);

foreach ($items as $sku => $it) {
    if ($max > 0 && $nInserted >= $max) { logMsg("Alcanzado max=$max, parando."); break; }

    if ($handlesParam !== '' && !isset($wantHandles[strtolower($it['handle'])])) continue;
    if ($selectedType !== 'all' && $handlesParam === '') {
        if (mb_strtolower($it['type'], 'UTF-8') !== mb_strtolower($selectedType, 'UTF-8')) continue;
    }

    // dedup: SKU (por fabricante) o EAN (global) o lista negra → saltar
    if (isset($existing[strtolower($sku)]) || ($it['ean'] !== '' && isset($existing[strtolower($it['ean'])]))) { $skipExist++; continue; }

    $imageUrls = array_values(array_filter((array) $it['images']));
    if (empty($imageUrls)) { $skipNoImg++; logMsg("SKIP $sku: sin imagen"); continue; }

    // nombre + descripción
    $nameEnRaw = $it['name'];
    $descText  = ramCleanHtmlToText($it['desc']);
    if ($skipTranslation || $dryRun) {
        $nameEn = $nameEnRaw;
        $nameEs = $nameEnRaw;
        $descEn = $descText !== '' ? '<p>' . htmlspecialchars($descText) . '</p>' : '';
        $descEs = $descEn;
    } else {
        $trEs = ramLlmLine(LLM_NAME_PROMPT_ES, $nameEnRaw);
        $nameEs = ramCleanName($trEs !== '' ? $trEs : $nameEnRaw);
        $nameEn = $nameEnRaw;
        $descEn = '';
        if ($descText !== '') {
            $inLen = mb_strlen($descText, 'UTF-8');
            $fEn = llmCall(LLM_FORMAT_PROMPT_EN, $descText, 2500);
            $descEn = ramFormatLooksValid($fEn, $inLen) ? $fEn : ('<p>' . htmlspecialchars($descText) . '</p>');
            if (!ramFormatLooksValid($fEn, $inLen)) $formatFail++;
        }
        $descEs = '';
        if ($descText !== '') {
            $inLen = mb_strlen($descText, 'UTF-8');
            $esText = llmCall(LLM_TRANSLATE_ES, $descText, 2000);
            if (trim($esText) === '') $esText = $descText;
            $fEs = llmCall(LLM_FORMAT_PROMPT_ES, $esText, 2500);
            $descEs = ramFormatLooksValid($fEs, $inLen) ? $fEs : ('<p>' . htmlspecialchars($esText) . '</p>');
            if (!ramFormatLooksValid($fEs, $inLen)) $formatFail++;
        }
    }
    $descEs = mbReformatDescription($descEs);
    $descEn = mbReformatDescription($descEn);

    // categoría: raíz > subcategoría por product_type. Sin type → cuelga de la raíz.
    $type = $it['type'];
    if ($type !== '') {
        $typeEs = RAM_TYPE_ES[$type] ?? $type;
        $subCatId = getOrCreateCategory($mysqli, $typeEs, $type, $rootCatId ?: 0, 1, $dryRun, $catCache, $createdLog);
        $targetCat = $subCatId ?: $rootCatId;
    } else {
        $targetCat = $rootCatId;
    }

    if ($dryRun) {
        $nInserted++;
        if ($nInserted <= 25) {
            logMsg(sprintf("  WOULD INSERT %s [%s] price=%.2f cost=%.2f g1=%.2f ean=%s imgs=%d '%s'",
                $sku, ($type !== '' ? $type : 'sin tipo'),
                $it['_PRICE'], $it['_COST'], $it['_G1'], $it['ean'] ?: '(interno)', count($imageUrls),
                mb_substr($nameEs, 0, 46, 'UTF-8')));
        }
        continue;
    }

    $tmpFiles = downloadImagesToTmp($imageUrls, MAX_SUBIMAGES + 1);
    if (empty($tmpFiles)) { $skipNoImg++; logMsg("SKIP $sku: imágenes no descargables"); continue; }

    $mysqli->begin_transaction();
    try {
        $qmodel = $mysqli->real_escape_string($sku);
        $price  = number_format($it['_PRICE'], 4, '.', '');
        $cost   = number_format($it['_COST'], 4, '.', '');
        $weight = number_format(DEFAULT_WEIGHT, 3, '.', '');
        $masterEan = $it['ean'] !== '' ? $it['ean'] : '';
        $qEan = $mysqli->real_escape_string($masterEan);
        $sql = "INSERT INTO products (products_quantity, check_stock, products_model, products_image, products_price, products_cost, products_date_added, products_weight, products_status, products_tax_class_id, manufacturers_id, product_ean, reference_prov, products_import_origin)
            VALUES (0, 0, \"$qmodel\", \"\", $price, $cost, NOW(), $weight, 2, " . TAX_CLASS_IVA21 . ", " . (int)$mfgId . ", \"$qEan\", \"$qmodel\", \"" . ORIGIN_FLAG . "\")";
        if (!$mysqli->query($sql)) throw new Exception("products: " . $mysqli->error);
        $pid = (int) $mysqli->insert_id;

        $qNameEs = $mysqli->real_escape_string($nameEs);
        $qDescEs = $mysqli->real_escape_string($descEs);
        $qNameEn = $mysqli->real_escape_string($nameEn);
        $qDescEn = $mysqli->real_escape_string($descEn);
        if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_ES . ", \"$qNameEs\", \"$qDescEs\", 0)")) throw new Exception("desc ES: " . $mysqli->error);
        if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_EN . ", \"$qNameEn\", \"$qDescEn\", 0)")) throw new Exception("desc EN: " . $mysqli->error);

        if (!$mysqli->query("INSERT INTO products_to_categories (products_id, categories_id) VALUES ($pid, " . (int)$targetCat . ")")) throw new Exception("p2c: " . $mysqli->error);
        if (!$mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . G1_GROUP_ID . ", $pid, " . number_format($it['_G1'], 4, '.', '') . ", 1, 1)")) throw new Exception("g1: " . $mysqli->error);

        // imágenes
        $slug = ramSlugify($nameEs);
        $imgFinal = [];
        foreach ($tmpFiles as $i => $tmpAbs) {
            $suffix = ($i === 0) ? '' : ('-' . ($i + 1));
            $finalName = $slug . '-' . $pid . $suffix . '.jpg';
            if (@rename($tmpAbs, IMG_ABS_DIR . $finalName)) $imgFinal[] = $finalName;
            else @unlink($tmpAbs);
        }
        if (empty($imgFinal)) throw new Exception("rename imágenes falló");
        $mainImg = array_shift($imgFinal);
        $mysqli->query("UPDATE products SET products_image=\"" . $mysqli->real_escape_string($mainImg) . "\" WHERE products_id=$pid");
        if (!empty($imgFinal)) {
            $subJson = json_encode($imgFinal, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $mysqli->query("UPDATE products SET products_subimages='" . $mysqli->real_escape_string($subJson) . "' WHERE products_id=$pid");
        }

        $mysqli->commit();

        // sin EAN real → interno por pid
        if ($masterEan === '') {
            $genEan = generateInternalEan13($pid, EAN_INTERNAL_PREFIX);
            if ($genEan !== '') $mysqli->query("UPDATE products SET product_ean='" . $mysqli->real_escape_string($genEan) . "' WHERE products_id=$pid AND (product_ean IS NULL OR product_ean='')");
        }

        $nInserted++;
        logMsg(sprintf("OK pid=%d %s [%s] cat='%s' price=%.2f cost=%.2f g1=%.2f imgs=%d '%s'",
            $pid, $sku, $type ?: 'raíz', $type !== '' ? (RAM_TYPE_ES[$type] ?? $type) : 'raíz',
            $it['_PRICE'], $it['_COST'], $it['_G1'], 1 + count($imgFinal),
            mb_substr($nameEs, 0, 45, 'UTF-8')));
    } catch (Exception $e) {
        $mysqli->rollback();
        $errors++;
        foreach ($tmpFiles as $t) if (file_exists($t)) @unlink($t);
        logMsg("ERROR sku=$sku: " . $e->getMessage());
    }
}

$elapsed = microtime(true) - $ts0;
logMsg("==================== RESUMEN ====================");
logMsg(sprintf("Insertados: %d en %.1fs", $nInserted, $elapsed));
logMsg("Skip existentes: $skipExist | skip sin imagen: $skipNoImg | maquetados fallidos: $formatFail | errores: $errors");
if (!empty($createdLog)) {
    logMsg(($dryRun ? "Se crearían" : "Creadas") . " " . count($createdLog) . " entidades:");
    foreach ($createdLog as $c) logMsg("  · $c");
}

end_action:
?>
    </div>
    <p style="margin-top:15px;"><a href="<?php echo tep_href_link('import-ram-altas.php'); ?>" class="xbutton small hv9">← Volver</a></p>
<?php else: ?>
    <h2>Importador RAM Mounts (altas)</h2>
    <?php
        $skuErr = '';
        $skuMap = ramLoadSkuMap($skuErr);
        $catMsg = '';
        $catalog = $forceRefresh ? ramLoadCatalog(true, $catMsg) : (loadJson(RAM_CACHE) ?: []);
        if ($skuErr) {
            echo '<p style="color:red;">' . htmlspecialchars($skuErr) . '</p>';
        }
        if (empty($catalog)) {
            echo '<p style="color:#a60;">No hay catálogo web en cache. Pulsa <em>Refrescar catálogo web</em> para descargarlo de rammount.com.</p>';
            echo '<p><a href="' . tep_href_link('import-ram-altas.php', 'refresh=1') . '" class="xbutton small hv9">Refrescar catálogo web</a></p>';
        }
        if (!empty($catalog) && !empty($skuMap)) {
            $items = ramBuildItems($catalog, $skuMap);
            $typeCount = []; $noType = 0;
            foreach ($items as $it) {
                $t = $it['type'];
                if ($t === '') { $noType++; continue; }
                $typeCount[$t] = ($typeCount[$t] ?? 0) + 1;
            }
            // ordenar por nº de productos desc
            arsort($typeCount);
    ?>
    <p style="color:#666;font-size:13px;">
        Fuente SKU/EAN/precio: <code><?php echo htmlspecialchars(basename(ramFindXlsx() ?: '')); ?></code>
        (hoja <code>Pricing</code>: A=SKU, B=UPC, D=PVP IVA inc, E=PVE coste) — <strong><?php echo count($skuMap); ?></strong> SKUs.
        Enriquecimiento: Shopify <code>rammount.com</code> (nombre, descripción, imágenes, product_type) por SKU.
        <strong><?php echo count($catalog); ?></strong> productos web → <strong><?php echo count($items); ?></strong> importables. Catálogo: <?php echo htmlspecialchars($catMsg ?: 'cache'); ?>.
    </p>
    <p style="background:#fffbe6;border:1px solid #ffd700;padding:10px;border-radius:4px;font-size:13px;">
        <strong>Reglas siempre aplicadas</strong>:<br>
        • Fabricante: <code><?php echo MFG_NAME; ?></code> (se crea si no existe).<br>
        • <code>products_cost</code> = PVE (col E del xlsx) &nbsp;|&nbsp;
          <code>products_price</code> = roundToNickel(PVP / 1,21) — el PVP del xlsx es IVA incluido &nbsp;|&nbsp;
          <code>G1</code> = roundToNickel(tiers de margen + piso cost×<?php echo G1_FLOOR_FACTOR; ?>).<br>
        • Categorías: <strong><?php echo PARENT_CATEGORY_NAME_ES; ?></strong> (status 0, oculta) &gt; subcategoría por <strong>product_type</strong> de Shopify (Soportes para Móvil, Componentes…); sin tipo → cuelga de la raíz.<br>
        • Nombre limpiado (sin ®/™/©) traducido EN→ES vía LLM; descripción traducida + maquetada (EN se maqueta); glosario de soportes/mounts (salvo "sin LLM").<br>
        • EAN real del xlsx (UPC-A→EAN-13); sin EAN válido → interno prefijo <?php echo EAN_INTERNAL_PREFIX; ?> (nunca <code>299…</code>).<br>
        • <strong>Sin imagen → no se importa.</strong> Imágenes Shopify (1 principal + hasta <?php echo MAX_SUBIMAGES; ?> sub).<br>
        • SKU sin coste/PVP en el xlsx → no se importa.<br>
        • <code>products_status=2</code> (revisión), <code>check_stock=0</code>, stock NO se toca.<br>
        • Skip si el SKU (por fabricante), el EAN (global) o la lista negra ya lo contienen.
    </p>
    <form method="get" style="background:#f5f5f5;padding:15px;border-radius:5px;">
        <p><strong>Tipo a importar</strong>:
            <select name="ptype" style="min-width:340px;">
                <option value="all">— Todos los tipos (<?php echo array_sum($typeCount) + $noType; ?>) —</option>
                <?php foreach ($typeCount as $t => $n) {
                    $tEs = RAM_TYPE_ES[$t] ?? $t;
                    $sel = (mb_strtolower($selectedType,'UTF-8') === mb_strtolower($t,'UTF-8')) ? ' selected' : '';
                    echo '<option value="' . htmlspecialchars($t) . '"' . $sel . '>' . htmlspecialchars($tEs) . ' (' . $n . ')</option>';
                } ?>
            </select>
            <?php if ($noType > 0) echo '<br><span style="color:#888;font-size:12px;">' . $noType . ' producto(s) sin product_type → cuelgan de la raíz "RAM Mounts Nuevos".</span>'; ?>
        </p>
        <p><strong>Handles concretos</strong> (opcional, coma/espacio; ej. <code>rap-b-149-ap-mag-1-w2mu</code>; ignora el filtro de tipo):<br>
            <textarea name="handles" rows="2" style="width:100%;" placeholder="rap-b-149-ap-mag-1-w2mu, ram-tb-69"><?php echo htmlspecialchars($handlesParam); ?></textarea>
        </p>
        <p><label><input type="checkbox" name="dry_run" value="1" checked> Dry-run (sin cambios)</label></p>
        <p><label><input type="checkbox" name="skip_translation" value="1"> Saltar LLM (nombre/descripción quedan en inglés, mucho más rápido)</label></p>
        <p><label><input type="checkbox" name="refresh" value="1"> Refrescar catálogo web (re-descarga products.json)</label></p>
        <p>Inserts máximos por ejecución (0 = sin límite): <input type="number" name="max" value="3" min="0" style="width:80px;"></p>
        <button type="submit" name="action" value="dry_run" class="xbutton small hv9">Dry-run</button>
        <button type="submit" name="action" value="execute" class="xbutton small hv9 verde" onclick="return confirm('¿Ejecutar de verdad? Insertará productos en la BD.');">Ejecutar</button>
    </form>
    <p style="margin-top:20px;color:#888;font-size:12px;">
        <strong>Detalles</strong>: el LLM (qwen) traduce y maqueta; ~3 llamadas por producto (lento — usa "sin LLM" para una pasada rápida y retraduce luego).
        El catálogo web se cachea en <code>cache/catalog.json</code>; marca "Refrescar" para volver a descargarlo.
    </p>
    <?php } ?>
<?php endif; ?>
</div>
</td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
