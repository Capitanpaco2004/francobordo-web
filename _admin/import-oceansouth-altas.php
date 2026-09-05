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
 * Importador Oceansouth (altas)
 *
 * Fuentes:
 *   - xlsx  /import/OceanSouth/PRICE LIST 2025.xlsx  (hoja "PRICE LIST 2025"):
 *       A = Item No. (SKU)  ·  C = EUR (NET) = COSTE neto  ·  D = Barcode (EAN-13)
 *   - Web   https://oceansouth.com/en-eu  (Shopify, products.json):
 *       nombre (title), descripción (body_html, inglés), PVP (variant.price, EUR),
 *       imágenes, y la AGRUPACIÓN de variantes (cada variant tiene su sku).
 *
 * Enlace: SKU de la variante Shopify == Item No. del xlsx. La web es la fuente de
 * qué productos existen; el xlsx aporta coste + EAN por SKU. Una variante sin coste
 * en el xlsx no se puede valorar → se descarta; un producto sin ninguna variante
 * priceable → se salta.
 *
 * Decisiones (usuario 2026-05-25):
 *   - products_price = roundToNickel(PVP_web / 1,21)   (el PVP web es IVA incluido)
 *   - products_cost  = EUR (NET) del xlsx              (ya es coste neto)
 *   - G1 (Profesionales) = roundToNickel(tiers de margen + piso cost×1,10)
 *   - Nombre con PREFIJO "Oceansouth " (no sufijo).
 *   - Categoría única "Oceansouth Nuevos" (status 0). Sin subcategorías.
 *   - Sin imagen → no se importa. Skip si el SKU/EAN ya está en BD.
 *   - EAN real del xlsx por variante; los raros sin EAN → interno (prefijo 28, id-based).
 * ────────────────────────────────────────────────────────────────────────── */

const OS_DIR        = '/home/francobordo/public_html/import/OceanSouth/';
const OS_CACHE_DIR  = OS_DIR . 'cache/';
const OS_CACHE      = OS_CACHE_DIR . 'catalog.json';
const OS_FAMILIES_CACHE = OS_CACHE_DIR . 'families.json';
const OS_SKUMAP_CACHE = OS_CACHE_DIR . 'skumap.json';
const OS_XLSX_SHEET = 'PRICE LIST 2025';
const SHOP_BASE     = 'https://oceansouth.com/en-eu';
define('IMG_ABS_DIR', dirname(dirname(__FILE__)) . '/images/productos/');

const PARENT_CATEGORY_NAME_ES = 'Oceansouth Nuevos';
const PARENT_CATEGORY_NAME_EN = 'Oceansouth New';
const TAX_CLASS_IVA21 = 1;
const LANG_ID_ES      = 3;
const LANG_ID_EN      = 1;
const G1_GROUP_ID     = 1;
const VARIANT_OPTION_ID = 3;              // "Modelo"
const PRODUCT_NAME_MAX = 96;
const NAME_PREFIX     = 'Oceansouth ';
const MFG_NAME        = 'Oceansouth';
const IMG_HTTP_TIMEOUT = 20;
const IMG_MIN_BYTES   = 3072;
const MAX_SUBIMAGES   = 6;
const ORIGIN_FLAG     = 'oceansouth';
const EAN_INTERNAL_PREFIX = 28;           // compartido (id-based → sin colisión). Raro: ~99% traen EAN real.
const OS_VAT_RATE     = 0.21;

/* Familias del megamenú oceansouth → colecciones Shopify (orden = prioridad, first-match-wins).
 * El producto se asigna a la 1ª familia cuya colección lo contiene. Regenerar si la web reorganiza. */
const OS_FAMILIES = [
    'Trolling Motor Covers'        => ['trolling-motor-covers','trolling-motor-covers-for-garmin','trolling-motor-covers-for-minn-kota','trolling-motor-covers-for-motor-guide','trolling-motor-covers-for-lowrance-simrad','for-garmin','for-minn-kota','for-motor-guide'],
    'Outboard Covers'              => ['outboard-motor-covers','cowling-covers','propeller-covers'],
    'Bimini Tops'                  => ['biminis','aluminium-bimini-tops','stainless-steel-bimini-tops','sailboat-bimini-tops','bimini-components-and-replacements','bimini-top-components','bimini-hardware','bimini-fabrics','bimini-support-poles','bimini-stainless-steel-frame-replacements','bimini-straps-and-webbings','bimini-top-accessories'],
    'Shade Extensions'             => ['shade-extensions-1','bimini-extension-kit','shade-extensions','bimini-top-t-top-extensions'],
    'T-Tops & Boat Tops'           => ['t-tops-and-boat-tops','t-top-components','t-top-components-1','deck-top-components-fabrics','fisherman-top-components-fabrics','seagull-t-tops-components-fabric','targa-top-components-fabrics','heavy-duty-t-top-components-fabrics','classic-t-top-components-fabrics'],
    'Sailing Dinghy Covers'        => ['sailing-dinghy-covers'],
    'Boat Covers'                  => ['boat-covers','inflatable-boat-style','jet-ski-pwc-covers','jet-ski-covers','hatch-covers','boat-cover-accessories'],
    'Sailing'                      => ['all-sailing-covers','winch-covers'],
    'Seating'                      => ['seating','boat-seats','boat-seat-boxes','swivels','seat-pedestals'],
    'Marine Furniture'             => ['marine-furniture-1'],
    'Cooler Cushions'              => ['cooler-cushions'],
    'Boat Cushions'                => ['cushions'],
    'Oars & Paddles'               => ['oars-paddles-boathooks','paddles','kayak-paddles','hooks','oar-components'],
    'Fishing Gear'                 => ['fishing-gear','bait-fillet-tables','boat-storage-tackle-accessories','rod-holders'],
    'Rocket Launchers'             => ['for-fishing-rod-rack-rocket-launcher','boat-arches','components-boat-arches'],
    'Air Horns'                    => ['signal-horns'],
    'Trailer Accessories'          => ['trailer-accessories','boat-trailer-accessories'],
    'Boarding Ladders & Handrails' => ['boarding-ladders-handrails','aluminum-ladders','stainless-ladders','handrails-handles'],
];
// Nombre ES de cada familia (categoría en la tienda). EN = el propio nombre de la familia.
const OS_FAMILY_ES = [
    'Trolling Motor Covers'        => 'Fundas de Motor de Arrastre',
    'Outboard Covers'              => 'Fundas de Motor Fueraborda',
    'Bimini Tops'                  => 'Biminis',
    'Shade Extensions'             => 'Extensiones de Sombra',
    'T-Tops & Boat Tops'           => 'T-Tops y Toldos',
    'Sailing Dinghy Covers'        => 'Fundas para Vela Ligera',
    'Boat Covers'                  => 'Fundas de Barco',
    'Sailing'                      => 'Vela',
    'Seating'                      => 'Asientos',
    'Marine Furniture'             => 'Mobiliario Náutico',
    'Cooler Cushions'              => 'Cojines para Nevera',
    'Boat Cushions'                => 'Cojines',
    'Oars & Paddles'               => 'Remos y Palas',
    'Fishing Gear'                 => 'Equipo de Pesca',
    'Rocket Launchers'             => 'Portacañas y Arcos',
    'Air Horns'                    => 'Bocinas',
    'Trailer Accessories'          => 'Accesorios de Remolque',
    'Boarding Ladders & Handrails' => 'Escaleras y Pasamanos',
];
const G1_FLOOR_FACTOR = 1.10;
const DEFAULT_WEIGHT  = 1.0;

const LLM_URL   = 'http://217.127.199.171:28001/v1/chat/completions';
const LLM_MODEL = 'qwen36-sakamaki-nvfp4';

const LLM_NAME_PROMPT_ES = "Traduce este nombre de producto náutico del INGLÉS al ESPAÑOL. Conserva nombres propios de modelo/marca, códigos, medidas y unidades. Glosario náutico EN→ES: cover=funda, bimini=bimini, hull=casco, deck=cubierta, T-Top=T-Top, pedestal=pedestal, swivel=base giratoria, oar=remo, paddle=pala, cushion=cojín, rod holder=portacañas, cleat=cornamusa, hatch=tapa de registro. Responde SOLO con el nombre traducido, una línea, sin comillas.";
const LLM_TRANSLATE_ES = "Traduce el siguiente texto descriptivo de producto náutico del INGLÉS al ESPAÑOL. Conserva ÍNTEGRAMENTE toda la información, números, medidas y unidades (mm, cm, m, g/m², kg, ft, °). Glosario náutico EN→ES: cover=funda/cubierta, bimini top=bimini, hull=casco, deck=cubierta, T-Top=T-Top, frame=estructura, pole=tubo, fabric=tejido/lona, webbing=cincha, strap=correa, buckle=hebilla, cleat=cornamusa, grommet=ojal, stainless steel=acero inoxidable, pedestal=pedestal, swivel=base giratoria, oar=remo, paddle=pala, oarlock/rowlock=chumacera, rod holder=portacañas, hatch=tapa de registro/cubierta, mooring=amarre, fender=defensa, cushion=cojín, mildew=moho, UV protection=protección UV. NO resumas ni inventes. Devuelve SOLO la traducción en español, sin comentarios.";
const LLM_FORMAT_PROMPT_ES = "Eres un experto en maquetar fichas de producto náuticas. Recibes una descripción comercial YA EN ESPAÑOL y la transformas en HTML legible.\n\nREGLAS:\n1. Primer <p>: frase introductoria (máx 5 frases por <p>).\n2. Si hay >6 características, agrúpalas bajo títulos <p><strong>Título</strong></p> (nunca <h1>-<h6>); antes de cada título inserta <p>&nbsp;</p>.\n3. Cada característica en su propio <p>• texto</p> (nunca <ul>/<li>). Concepto clave (1-4 palabras) en <strong> + dos puntos.\n4. Elimina ™ ® ©. Palabras EN MAYÚSCULAS de 4+ letras → Title Case (conserva acrónimos: LED, IP, USB, UV, PVC, AISI).\n5. NO traduzcas, NO resumas, NO inventes: conserva TODO el texto, solo añades estructura HTML.\n6. Etiquetas permitidas: <p>, <strong>, <a>. Prohibidas: <h1>-<h6>, <br>, <div>, <span>, <ul>, <li>, <table>, <ol>.\n7. Salida: SOLO el HTML, sin markdown ni comentarios.";
const LLM_FORMAT_PROMPT_EN = "You format nautical product datasheets. You receive a description ALREADY IN ENGLISH and turn it into clean HTML.\n\nRULES:\n1. First <p>: intro sentence (max 5 sentences per <p>).\n2. If >6 features, group under <p><strong>Title</strong></p> headings (never <h1>-<h6>); insert <p>&nbsp;</p> before each title.\n3. Each feature in its own <p>• text</p> (never <ul>/<li>). Key concept (1-4 words) in <strong> + colon.\n4. Remove ™ ® ©. ALL-CAPS words 4+ letters → Title Case (keep acronyms: LED, IP, USB, UV, PVC, AISI).\n5. DO NOT translate, summarize or invent: keep ALL text, only add HTML structure.\n6. Allowed tags: <p>, <strong>, <a>. Forbidden: <h1>-<h6>, <br>, <div>, <span>, <ul>, <li>, <table>, <ol>.\n7. Output: ONLY the HTML, no markdown, no comments.";
const LLM_LABEL_PROMPT_ES = "Traduce esta etiqueta corta de variante de producto náutico del INGLÉS al ESPAÑOL (ej. color, acabado, tipo de funda). Conserva números, medidas y unidades. Glosario: cover=funda, deck=cubierta, mast=mástil, navy blue=azul marino, sand=arena, grey/gray=gris, black=negro, white=blanco, charcoal=antracita, full=completa, vented=ventilada, storage=almacenaje. Responde SOLO con la traducción, una línea, sin comillas.";

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$max    = isset($_POST['max']) ? (int) $_POST['max'] : (isset($_GET['max']) ? (int) $_GET['max'] : 0);
$dryRun = isset($_POST['dry_run']) || isset($_GET['dry_run']) || $action === 'dry_run';
$skipTranslation = isset($_POST['skip_translation']) || isset($_GET['skip_translation']);
$noGroup = isset($_POST['no_group']) || isset($_GET['no_group']);
$forceRefresh = isset($_POST['refresh']) || isset($_GET['refresh']);
$handlesParam = trim((string) ($_POST['handles'] ?? $_GET['handles'] ?? ''));
$selectedFamily = trim((string) ($_POST['family'] ?? $_GET['family'] ?? 'all'));

function logMsg($msg) {
    $line = '[' . date('H:i:s') . '] ' . $msg . "\n";
    echo '<pre style="margin:0;padding:2px 8px;border-bottom:1px solid #eee;white-space:pre-wrap;overflow-wrap:break-word;word-break:break-word;font-family:monospace;font-size:12px;">' . htmlspecialchars($line) . '</pre>';
    @flush();
}

/* ── EAN-13 ── */
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
function generateInternalEan13($id, $providerPrefix) {
    $pp = (int) $providerPrefix;
    if ($pp < 20 || $pp > 29) return '';
    if ($id <= 0 || $id > 9999999999) return '';
    $payload = str_pad((string) $pp, 2, '0', STR_PAD_LEFT) . str_pad((string) $id, 10, '0', STR_PAD_LEFT);
    if (strncmp($payload, '299', 3) === 0) return '';   // no pisar el rango interno de QFacWin
    $check = ean13Checksum($payload);
    return $check < 0 ? '' : ($payload . $check);
}

/** Redondea un PVP NETO de modo que el precio CON IVA quede en múltiplo de 0,05. */
function roundToNickel($net) {
    $withIva = ((float) $net) * (1 + OS_VAT_RATE);
    $rounded = round($withIva * 20) / 20;
    return round($rounded / (1 + OS_VAT_RATE), 4);
}
/** PVP web (IVA incluido) → products_price NETO con el bruto redondeado a 0,05. */
function priceNetFromWebGross($gross) {
    return roundToNickel(((float) $gross) / (1 + OS_VAT_RATE));
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

function osSlugify($text, $maxLen = 50) {
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

function osStripPrefix($name) {
    $s = trim((string) $name);
    $p = NAME_PREFIX;
    if (strncasecmp($s, $p, strlen($p)) === 0) $s = trim(substr($s, strlen($p)));
    return $s;
}
function osApplyPrefix($name) {
    $name = html_entity_decode((string) $name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $name = osStripPrefix($name);
    $name = trim(preg_replace('/\s+/u', ' ', $name));
    if ($name === '') $name = '(sin nombre)';
    $maxBody = PRODUCT_NAME_MAX - mb_strlen(NAME_PREFIX, 'UTF-8');
    if (mb_strlen($name, 'UTF-8') > $maxBody) $name = rtrim(mb_substr($name, 0, $maxBody, 'UTF-8'));
    return NAME_PREFIX . $name;
}

/** Limpia la descripción HTML (inglesa) a texto plano para el LLM. */
function osCleanHtmlToText($html) {
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
        if (osIsTagline($l)) continue;   // eslogan de marketing → no importar
        if ($l === '') { if ($empty < 1 && !empty($out)) $out[] = ''; $empty++; continue; }
        $out[] = $l; $empty = 0;
    }
    return trim(implode("\n", $out));
}

/** Eslóganes de marketing de Oceansouth que NO queremos en la descripción. */
function osIsTagline($s) {
    return (bool) preg_match('/through your lens|a trav[eé]s de su lente/iu', (string) $s);
}
/** Quita el párrafo del eslogan ("…through your lens" / "…a través de su lente") del HTML ya maquetado.
 *  Cubre el <p> cerrado y el <p> SIN cerrar al final (el body_html de Oceansouth lo deja abierto). */
function osStripTagline($html) {
    if (!is_string($html) || $html === '') return $html;
    $html = preg_replace_callback('#<p\b[^>]*>.*?(?:</p>|$)#isu', fn($m) => osIsTagline($m[0]) ? '' : $m[0], $html);
    return trim($html);
}

function osBrowserUA() {
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
        CURLOPT_USERAGENT => osBrowserUA(),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
            'Referer: https://oceansouth.com/',
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
        $tmpAbs = IMG_ABS_DIR . 'os-tmp-' . uniqid('', true) . '.jpg';
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
        usleep(400000);
    }
    return '';
}
function osLlmLine($systemPrompt, $text) {
    $out = llmCall($systemPrompt, $text, 120);
    $out = trim(preg_replace('/\s+/u', ' ', strip_tags($out)));
    return trim($out, " \t\"'");
}
function osFormatLooksValid($html, $minLenInput) {
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

/* ── xlsx → SKU → {cost, ean} ── */
function osFindXlsx() {
    $best = null; $bestT = 0;
    foreach (glob(OS_DIR . '*.xlsx') ?: [] as $f) {
        if (strpos(basename($f), '~$') === 0) continue;
        $t = filemtime($f);
        if ($t > $bestT) { $bestT = $t; $best = $f; }
    }
    return $best;
}
function osLoadSkuMap(&$err) {
    $err = '';
    $f = osFindXlsx();
    if (!$f) { $err = 'No se encontró ningún .xlsx en ' . OS_DIR; return []; }
    // Cache JSON: parsear el xlsx (5,8 MB → ~60s) solo cuando cambia (clave = nombre:mtime:size).
    $sig = basename($f) . ':' . filemtime($f) . ':' . filesize($f);
    if (file_exists(OS_SKUMAP_CACHE)) {
        $c = json_decode((string) file_get_contents(OS_SKUMAP_CACHE), true);
        if (is_array($c) && ($c['sig'] ?? '') === $sig && isset($c['map']) && is_array($c['map'])) return $c['map'];
    }
    try {
        $reader = IOFactory::createReaderForFile($f);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([OS_XLSX_SHEET]);
        $ss = $reader->load($f);
        $sh = $ss->getSheet(0);
    } catch (Throwable $e) {
        try { $reader = IOFactory::createReaderForFile($f); $reader->setReadDataOnly(true); $ss = $reader->load($f); $sh = $ss->getSheetByName(OS_XLSX_SHEET) ?: $ss->getSheet(0); }
        catch (Throwable $e2) { $err = 'xlsx: ' . $e2->getMessage(); return []; }
    }
    $map = [];
    foreach ($sh->getRowIterator(2) as $row) {
        $ri  = $row->getRowIndex();
        $sku = trim((string) $sh->getCell('A' . $ri)->getValue());
        if ($sku === '' || strtoupper($sku) === 'ITEM NO.') continue;
        $cost = $sh->getCell('C' . $ri)->getValue();
        $ean  = trim((string) $sh->getCell('D' . $ri)->getValue());
        $map[$sku] = [
            'cost' => ($cost === null || $cost === '') ? null : (float) $cost,
            'ean'  => isValidEan13($ean) ? $ean : '',
        ];
    }
    if (!empty($map)) {
        if (!is_dir(OS_CACHE_DIR)) @mkdir(OS_CACHE_DIR, 0775, true);
        @file_put_contents(OS_SKUMAP_CACHE, json_encode(['sig' => $sig, 'map' => $map], JSON_UNESCAPED_UNICODE));
    }
    return $map;
}

/* ── Web Shopify → catálogo crudo (cacheado en disco) ── */
function osFetchAllProducts() {
    $all = []; $page = 1;
    while ($page <= 40) {
        $url = SHOP_BASE . '/products.json?limit=250&page=' . $page;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => osBrowserUA(),
            CURLOPT_TIMEOUT => 40,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);
        if ($resp === false || $code !== 200) break;
        $j = json_decode($resp, true);
        if (empty($j['products'])) break;
        foreach ($j['products'] as $p) $all[] = $p;
        $n = count($j['products']);
        if ($n < 250) break;
        $page++;
    }
    return $all;
}
function osLoadCatalog($forceRefresh, &$msg) {
    $msg = '';
    if (!$forceRefresh && file_exists(OS_CACHE)) {
        $j = json_decode((string) file_get_contents(OS_CACHE), true);
        if (is_array($j) && !empty($j)) { $msg = 'cache (' . date('Y-m-d H:i', filemtime(OS_CACHE)) . ')'; return $j; }
    }
    $all = osFetchAllProducts();
    if (!empty($all)) {
        if (!is_dir(OS_CACHE_DIR)) @mkdir(OS_CACHE_DIR, 0775, true);
        @file_put_contents(OS_CACHE, json_encode($all, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $msg = 'web (descargado ' . count($all) . ')';
    } elseif (file_exists(OS_CACHE)) {
        $all = json_decode((string) file_get_contents(OS_CACHE), true) ?: [];
        $msg = 'cache (fallo web, usando copia previa)';
    }
    return $all;
}

/* ── Familias: producto_id → familia (vía colecciones Shopify, cacheado) ── */
function osFetchCollectionIds($handle) {
    $ids = []; $page = 1;
    while ($page <= 6) {
        $ch = curl_init(SHOP_BASE . '/collections/' . $handle . '/products.json?limit=250&page=' . $page);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_USERAGENT => osBrowserUA(),
            CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); unset($ch);
        if ($resp === false || $code !== 200) break;
        $j = json_decode($resp, true);
        if (empty($j['products'])) break;
        foreach ($j['products'] as $p) $ids[] = (string) $p['id'];
        if (count($j['products']) < 250) break;
        $page++;
    }
    return array_values(array_unique($ids));
}
function osBuildFamilyMap() {
    $id2fam = [];
    foreach (OS_FAMILIES as $fam => $colls) {
        foreach ($colls as $c) {
            foreach (osFetchCollectionIds($c) as $id) if (!isset($id2fam[$id])) $id2fam[$id] = $fam;
        }
    }
    return $id2fam;
}
function osLoadFamilyMap($forceRefresh, &$msg) {
    $msg = '';
    if (!$forceRefresh && file_exists(OS_FAMILIES_CACHE)) {
        $j = json_decode((string) file_get_contents(OS_FAMILIES_CACHE), true);
        if (is_array($j)) { $msg = 'cache'; return $j; }
    }
    $m = osBuildFamilyMap();
    if (!empty($m)) {
        if (!is_dir(OS_CACHE_DIR)) @mkdir(OS_CACHE_DIR, 0775, true);
        @file_put_contents(OS_FAMILIES_CACHE, json_encode($m, JSON_UNESCAPED_UNICODE));
        $msg = 'web (' . count($m) . ' productos clasificados)';
    } elseif (file_exists(OS_FAMILIES_CACHE)) {
        $m = json_decode((string) file_get_contents(OS_FAMILIES_CACHE), true) ?: [];
        $msg = 'cache (fallo web)';
    }
    return $m;
}

/** Construye los grupos importables: producto web → variantes con coste en xlsx. */
function osBuildGroups($catalog, $skuMap, $noGroup = false) {
    $groups = [];
    foreach ($catalog as $p) {
        $images = [];
        foreach (($p['images'] ?? []) as $im) if (!empty($im['src'])) $images[] = $im['src'];
        // nombres de las opciones (option1/2/3) y detección de estructura Modelo+Longitud de eje
        $optNames = [];
        foreach (($p['options'] ?? []) as $o) $optNames[] = (string) ($o['name'] ?? '');
        $hasShaft = false; $isMotor = false;
        foreach ($optNames as $on) {
            if (stripos($on, 'shaft') !== false) $hasShaft = true;
            if (stripos($on, 'shaft') !== false || stripos($on, 'engine') !== false || stripos($on, 'trolling motor') !== false) $isMotor = true;
        }
        $shaftImg = $hasShaft ? osExtractBodyImage($p['body_html'] ?? '') : '';
        $items = [];
        foreach (($p['variants'] ?? []) as $v) {
            $sku = trim((string) ($v['sku'] ?? ''));
            if ($sku === '' || !isset($skuMap[$sku])) continue;          // sin entrada en xlsx
            $cost = $skuMap[$sku]['cost'];
            if ($cost === null || $cost <= 0) continue;                  // sin coste → no priceable
            $gross = (float) ($v['price'] ?? 0);
            if ($gross <= 0) continue;
            $price = priceNetFromWebGross($gross);
            $g1    = roundToNickel(calcG1Price($price, $cost));
            $label = trim((string) ($v['title'] ?? ''));
            if (strcasecmp($label, 'Default Title') === 0) $label = '';
            // mapa nombre-opción → valor de esta variante
            $opts = [];
            foreach ($optNames as $i => $on) { $ov = trim((string) ($v['option' . ($i + 1)] ?? '')); if ($on !== '' && $ov !== '') $opts[$on] = $ov; }
            $items[$sku] = [
                'sku' => $sku, '_COST' => round($cost, 4), '_PRICE' => $price, '_G1' => $g1,
                'ean' => $skuMap[$sku]['ean'], 'label' => $label, 'opts' => $opts,
            ];
        }
        if (empty($items)) continue;                                     // ningún variante priceable
        $pidWeb = (string) $p['id'];
        $common = ['name' => $p['title'] ?? '', 'desc' => $p['body_html'] ?? '', 'pid_web' => $pidWeb,
                   'handle' => $p['handle'] ?? '', 'images' => $images, 'is_motor' => $isMotor, 'shaft_img' => $shaftImg];
        if ($noGroup) {
            // separa cada variante como producto suelto (1 item por grupo)
            $i = 0;
            foreach ($items as $sku => $it) {
                $groups['__' . $pidWeb . '_' . (++$i)] = $common + ['items' => [$sku => $it]];
            }
        } else {
            $groups[$pidWeb] = $common + ['items' => $items];
        }
    }
    return $groups;
}

/* ── Dedup map (SKU scoped por fabricante/origin; EAN global) ── */
function buildExistingMap($mysqli, $mfgId) {
    $existing = [];
    // Scope SKU al espacio Oceansouth. Si el fabricante aún no existe (mfgId=0, p.ej. dry-run),
    // NO incluir "manufacturers_id = 0" o casaría con todos los productos sin marca.
    $f = ((int)$mfgId > 0)
        ? "(p.manufacturers_id = " . (int)$mfgId . " OR p.products_import_origin LIKE 'oceansouth%')"
        : "(p.products_import_origin LIKE 'oceansouth%')";
    foreach ([
        "SELECT LOWER(p.products_model) m FROM products p WHERE p.products_model<>'' AND $f",
        "SELECT LOWER(p.reference_prov) m FROM products p WHERE p.reference_prov<>'' AND $f",
        "SELECT LOWER(pa.reference) m FROM products_attributes pa INNER JOIN products p ON p.products_id=pa.products_id WHERE pa.reference<>'' AND $f",
        "SELECT LOWER(pa.reference_prov) m FROM products_attributes pa INNER JOIN products p ON p.products_id=pa.products_id WHERE pa.reference_prov<>'' AND $f",
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
    // Lista negra de reimportación: trata como "ya existentes" los códigos/EAN de productos borrados a propósito.
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

function findOrCreateOptionValue($mysqli, $nameEs, $nameEn = null) {
    if ($nameEn === null) $nameEn = $nameEs;
    $nameEsSafe = $mysqli->real_escape_string($nameEs);
    $q = $mysqli->query("SELECT pov.products_options_values_id FROM products_options_values pov
        INNER JOIN products_options_values_to_products_options pov2po ON pov2po.products_options_values_id = pov.products_options_values_id
        WHERE pov2po.products_options_id = " . VARIANT_OPTION_ID . " AND pov.language_id = " . LANG_ID_ES . "
          AND pov.products_options_values_name = '$nameEsSafe' LIMIT 1");
    if ($q && $row = $q->fetch_assoc()) return (int) $row['products_options_values_id'];
    $nq = $mysqli->query("SELECT IFNULL(MAX(products_options_values_id),0)+1 nid FROM products_options_values");
    $newId = (int) ($nq->fetch_assoc()['nid'] ?? 1);
    $nameEnSafe = $mysqli->real_escape_string($nameEn);
    $mysqli->query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($newId, " . LANG_ID_ES . ", '$nameEsSafe', '')");
    $mysqli->query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($newId, " . LANG_ID_EN . ", '$nameEnSafe', '')");
    $mysqli->query("INSERT INTO products_options_values_to_products_options (products_options_id, products_options_values_id) VALUES (" . VARIANT_OPTION_ID . ", $newId)");
    return $newId;
}

/** Recorta el nombre base para que el sufijo de desambiguación sobreviva el límite de 64
 *  (sql_mode no estricto trunca en silencio el final, llevándose el sufijo). */
function osFitName($base, $suffix, $max = 64) {
    if (mb_strlen($base . $suffix, 'UTF-8') <= $max) return $base . $suffix;
    return mb_substr($base, 0, $max - mb_strlen($suffix, 'UTF-8'), 'UTF-8') . $suffix;
}

/** Traduce una etiqueta de variante EN→ES por segmentos ' / '; conserva los que llevan dígitos. */
function osTranslateLabel($labelEn, $skipTranslation, &$cache) {
    if ($labelEn === '') return '';
    if ($skipTranslation) return $labelEn;
    $segs = [];
    foreach (explode(' / ', $labelEn) as $seg) {
        $seg = trim($seg);
        if ($seg === '') continue;
        if (preg_match('/\d/', $seg) || !preg_match('/\p{L}{3,}/u', $seg) || mb_strlen($seg, 'UTF-8') <= 3) {
            $segs[] = $seg;
        } else {
            if (!isset($cache[$seg])) {
                $t = osLlmLine(LLM_LABEL_PROMPT_ES, $seg);
                $cache[$seg] = ($t !== '') ? $t : $seg;
            }
            $segs[] = $cache[$seg];
        }
    }
    return implode(' / ', $segs);
}

/* ──────────────────────────────────────────────────────────────────────────
 * Fundas de motor (outboard/trolling): variantes con estructura Modelo + Longitud
 * de eje (+ a veces Color/Tipo de agua). El variant.title es larguísimo → en su
 * lugar generamos (a) un nombre de variante CORTO y traducido y (b) una tabla de
 * especificaciones en la descripción (Referencia | Modelo | Longitud del Eje …)
 * + la imagen del diagrama de longitud de eje.
 * Traducción ES por reglas deterministas (HP→CV, 4STR→4T, 4CYL→4CIL, onwards→…).
 * ────────────────────────────────────────────────────────────────────────── */

/** Traduce una cadena de modelo de motor EN→ES (reglas fijas). Para la TABLA (texto completo). */
function osTranslateModelEs($s) {
    $s = (string) $s;
    $s = preg_replace('/\bup to mid\b/i', 'hasta mediados de', $s);
    $s = preg_replace('/\bup to\b/i', 'hasta', $s);
    $s = preg_replace('/\bmid\s+(\d{4})/i', 'mediados de $1', $s);
    $s = preg_replace('/\bonwards\b/i', 'en adelante', $s);
    $s = preg_replace('/\bto\b/i', 'a', $s);                 // rangos "75HP a 115HP"
    $s = preg_replace('/(\d)\s*STR\b/i', '${1}T', $s);       // 4STR → 4T
    $s = preg_replace('/(\d)\s*CYL\b/i', '${1}CIL', $s);     // 4CYL / 1 CYL → 4CIL
    $s = str_ireplace('HP', 'CV', $s);
    $s = str_ireplace('Thrust', 'Empuje', $s);
    $s = str_ireplace('Fresh Water', 'Agua Dulce', $s);
    $s = str_ireplace('Salt Water', 'Agua Salada', $s);
    return trim(preg_replace('/\s+/u', ' ', $s));
}
/** Versión CORTA para el nombre de la variante (era abreviada). */
function osTranslateModelEsShort($s) {
    $s = osTranslateModelEs($s);
    $s = preg_replace('/\(\s*(\d{4})\s*en adelante\s*\)/u', '($1 +)', $s);
    $s = preg_replace('/\(\s*mediados de (\d{4}) en adelante\s*\)/u', '(med. $1 +)', $s);
    return $s;
}
/** Longitud de eje compacta: "635mm (25\")" → "635mm". */
function osShaftShort($s) {
    $s = (string) $s;
    if (preg_match('/([\d.,]+\s*mm)/u', $s, $m)) return trim($m[1]);
    return trim(explode('(', $s)[0]);
}
/** Imagen del diagrama de longitud de eje incrustada en el body_html del producto. */
function osExtractBodyImage($bodyHtml) {
    if (!is_string($bodyHtml) || $bodyHtml === '') return '';
    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $bodyHtml, $m)) return trim($m[1]);
    return '';
}
/** Descarga una imagen compartida (mismo diagrama para todos) a images/productos/, dedup por URL. */
function osDownloadSharedImage($url) {
    $url = trim((string) $url);
    if ($url === '' || !preg_match('#^https?://#i', $url)) return '';
    $ext = 'jpg';
    if (preg_match('/\.(jpe?g|png|webp|gif)(\?|$)/i', $url, $m)) $ext = strtolower($m[1]);
    $name = 'os-shaft-' . substr(md5($url), 0, 12) . '.' . $ext;
    $abs = IMG_ABS_DIR . $name;
    if (file_exists($abs) && filesize($abs) >= IMG_MIN_BYTES) return $name;   // ya descargada
    if (downloadImage($url, $abs)) return $name;
    return '';
}

/** Clasifica las opciones de una variante en modelo / eje / extras (color, tipo de agua). */
function osClassifyOpts(array $opts) {
    $model = ''; $shaft = ''; $extras = [];
    foreach ($opts as $name => $val) {
        $val = trim((string) $val);
        if ($val === '' || strcasecmp($val, 'Default Title') === 0) continue;
        $n = mb_strtolower($name, 'UTF-8');
        if (strpos($n, 'shaft') !== false) {
            // a veces el valor del eje lleva color pegado: "914mm (36″) | Grey"
            if (strpos($val, '|') !== false) { $parts = array_map('trim', explode('|', $val, 2)); $shaft = $parts[0]; if (!empty($parts[1])) $extras[] = $parts[1]; }
            else $shaft = $val;
        } elseif (strpos($n, 'color') !== false || strpos($n, 'colour') !== false || strpos($n, 'water') !== false) {
            $extras[] = $val;
        } else {
            $model = ($model === '') ? $val : ($model . ' ' . $val);
        }
    }
    return ['model' => $model, 'shaft' => $shaft, 'extras' => $extras];
}

/** Versión EN corta del modelo: abrevia la era "(2014 onwards)" → "(2014 +)" (mantiene inglés). */
function osModelEnShort($s) {
    $s = (string) $s;
    $s = preg_replace('/\(\s*mid\s+(\d{4})\s*onwards\s*\)/i', '(mid $1 +)', $s);
    $s = preg_replace('/\(\s*(\d{4})\s*onwards\s*\)/i', '($1 +)', $s);
    return trim(preg_replace('/\s+/u', ' ', $s));
}
/** Etiqueta corta de variante para fundas de motor: modelo traducido + eje [+ extras]. */
function osShaftVariantName($meta, $lang) {
    $model = $lang === 'es' ? osTranslateModelEsShort($meta['model']) : osModelEnShort($meta['model']);
    $parts = [];
    if ($model !== '') $parts[] = $model;
    if (!empty($meta['shaft'])) $parts[] = osShaftShort($meta['shaft']);
    foreach ($meta['extras'] as $e) $parts[] = ($lang === 'es' ? osTranslateModelEs($e) : $e);
    $out = implode(' / ', $parts);
    return mb_substr(trim($out), 0, 64, 'UTF-8');
}

/** Tabla de especificaciones (Referencia | Modelo [| Longitud del Eje] [| Color]) — columnas dinámicas.
 *  Estilo idéntico al importador Osculati (clase osc-spec-table: cabecera #008cc6, zebra #e2f2f9). */
function osBuildSpecTable(array $rows, $lang) {
    // $rows: [ ['sku'=>, 'meta'=>['model'=>,'shaft'=>,'extras'=>]] , ... ]  ya ordenadas
    $hasShaftCol = false; $hasExtra = false;
    foreach ($rows as $r) { if (!empty($r['meta']['shaft'])) $hasShaftCol = true; if (!empty($r['meta']['extras'])) $hasExtra = true; }
    $L = $lang === 'es'
        ? ['ref' => 'Referencia', 'model' => 'Modelo', 'shaft' => 'Longitud del Eje', 'extra' => 'Color']
        : ['ref' => 'Reference', 'model' => 'Model', 'shaft' => 'Shaft Length', 'extra' => 'Colour'];
    $title = $lang === 'es' ? 'Tabla de referencia (elige según tu motor)' : 'Reference table (choose by your engine)';
    $cols = ['ref', 'model']; if ($hasShaftCol) $cols[] = 'shaft'; if ($hasExtra) $cols[] = 'extra';
    $FONT  = 'font-family: tahoma, arial, helvetica, sans-serif; font-size: 10pt;';
    $open  = '<table class="osc-spec-table" style="border-collapse: collapse; border: 1px solid rgb(206, 212, 217);" border="1" cellspacing="3" cellpadding="3"><tbody>';
    $hCell = fn($t) => '<td style="background-color: #008cc6; text-align: center; padding: 2px;"><span style="' . $FONT . ' color: #ffffff;">' . htmlspecialchars($t) . '</span></td>';
    $dCell = fn($t) => '<td style="text-align: center; padding: 2px;"><span style="' . $FONT . '">' . htmlspecialchars($t) . '</span></td>';
    $zebra = ' style="background-color: #e2f2f9;"';
    $html = '<p><strong>' . htmlspecialchars($title) . '</strong></p>' . $open . '<tr>';
    foreach ($cols as $c) $html .= $hCell($L[$c]);
    $html .= '</tr>';
    $i = 0;
    foreach ($rows as $r) {
        $i++;
        $m = $r['meta'];
        $cell = [
            'ref'   => $r['sku'],
            'model' => $lang === 'es' ? osTranslateModelEs($m['model']) : $m['model'],
            'shaft' => $m['shaft'] !== '' ? $m['shaft'] : '—',
            'extra' => !empty($m['extras']) ? implode(', ', array_map(fn($e) => $lang === 'es' ? osTranslateModelEs($e) : $e, $m['extras'])) : '',
        ];
        $html .= '<tr' . ($i % 2 === 0 ? $zebra : '') . '>';
        foreach ($cols as $c) $html .= $dCell($cell[$c]);
        $html .= '</tr>';
    }
    return $html . '</tbody></table>';
}

const OS_SHAFT_MARK_START = '<!--OS_SHAFT_START-->';
const OS_SHAFT_MARK_END   = '<!--OS_SHAFT_END-->';
/** Quita un bloque de eje previamente insertado (para reconstruir sin duplicar). */
function osStripShaftBlock($html) {
    return trim(preg_replace('/' . preg_quote(OS_SHAFT_MARK_START, '/') . '.*?' . preg_quote(OS_SHAFT_MARK_END, '/') . '/s', '', (string) $html));
}
/** Bloque de descripción para fundas de motor: imagen del eje + tabla de specs (con marcadores). */
function osShaftDescBlock($shaftImgFile, array $rows, $lang) {
    $block = OS_SHAFT_MARK_START;
    if ($shaftImgFile !== '') {
        $cap = $lang === 'es' ? 'Elige la longitud de eje correcta para la funda' : 'Choose the correct shaft length for the cover';
        $block .= '<p>&nbsp;</p><p style="text-align:center;"><img src="https://www.francobordo.com/images/productos/' . htmlspecialchars($shaftImgFile) . '" alt="' . htmlspecialchars($cap) . '" style="max-width:420px;height:auto;"></p>';
        $block .= '<p style="text-align:center;font-size:12px;color:#666;">' . htmlspecialchars($cap) . '</p>';
    }
    $block .= osBuildSpecTable($rows, $lang);
    $block .= OS_SHAFT_MARK_END;
    return $block;
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
    <h2>Importador Oceansouth — <?php echo $dryRun ? 'DRY-RUN (sin cambios)' : 'EJECUCIÓN REAL'; ?></h2>
    <p><a href="<?php echo tep_href_link('import-oceansouth-altas.php'); ?>" class="xbutton small hv9">← Volver</a></p>
    <div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n";
@flush();

logMsg("Modo: " . ($dryRun ? "dry-run" : "EXECUTE")
    . " | familia=" . ($selectedFamily === 'all' ? 'TODAS' : $selectedFamily)
    . ($handlesParam !== '' ? " | handles=$handlesParam" : "")
    . ($skipTranslation ? " | sin LLM" : "")
    . ($noGroup ? " | sin variantes" : "")
    . ($max > 0 ? " | max=$max" : ""));

$skuErr = '';
$skuMap = osLoadSkuMap($skuErr);
if ($skuErr) { logMsg("ERROR xlsx: $skuErr"); goto end_action; }
logMsg("xlsx: " . count($skuMap) . " SKUs con coste/EAN (" . basename(osFindXlsx()) . ")");

$catMsg = '';
$catalog = osLoadCatalog($forceRefresh, $catMsg);
if (empty($catalog)) { logMsg("ERROR: no se pudo cargar el catálogo web ni cache."); goto end_action; }
logMsg("Catálogo web: " . count($catalog) . " productos [$catMsg]");

$groups = osBuildGroups($catalog, $skuMap, $noGroup);
logMsg("Grupos importables (con ≥1 variante priceable): " . count($groups));

$famMsg = '';
$famMap = osLoadFamilyMap($forceRefresh, $famMsg);
logMsg("Familias: " . count($famMap) . " productos clasificados [$famMsg]");

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_error) { logMsg("ERROR DB: " . $mysqli->connect_error); goto end_action; }
$mysqli->set_charset('utf8');
if (!is_dir(IMG_ABS_DIR)) @mkdir(IMG_ABS_DIR, 0775, true);

$createdLog = []; $catCache = [];
$mfgId = ensureManufacturer($mysqli, MFG_NAME, $dryRun, $createdLog);
$rootCatId = getOrCreateCategory($mysqli, PARENT_CATEGORY_NAME_ES, PARENT_CATEGORY_NAME_EN, 0, 0, $dryRun, $catCache, $createdLog);

logMsg("Cargando IDs existentes en BD…");
$existing = buildExistingMap($mysqli, $mfgId);
logMsg("  → " . count($existing) . " referencias Oceansouth ya en BD");

$wantHandles = [];
if ($handlesParam !== '') foreach (preg_split('/[\s,;]+/', $handlesParam) as $h) { $h = trim($h); if ($h !== '') $wantHandles[strtolower($h)] = true; }

$labelTransCache = [];
$nInserted = $nVarFamilies = $nSingles = $nVariants = $skipExist = $skipNoImg = $errors = $formatFail = 0;
$ts0 = microtime(true);

foreach ($groups as $gid => $g) {
    if ($max > 0 && $nInserted >= $max) { logMsg("Alcanzado max=$max, parando."); break; }

    if ($handlesParam !== '' && !isset($wantHandles[strtolower($g['handle'])])) continue;

    $family = $famMap[$g['pid_web']] ?? '';
    if ($selectedFamily !== 'all' && $handlesParam === '') {
        if (mb_strtolower($family, 'UTF-8') !== mb_strtolower($selectedFamily, 'UTF-8')) continue;
    }

    // dedup: si CUALQUIER sku/EAN del grupo ya existe en BD → saltar el grupo entero
    $already = false;
    foreach ($g['items'] as $sku => $it) {
        if (isset($existing[strtolower($sku)]) || ($it['ean'] !== '' && isset($existing[strtolower($it['ean'])]))) { $already = true; break; }
    }
    if ($already) { $skipExist++; continue; }

    $imageUrls = array_values(array_filter((array) $g['images']));
    if (empty($imageUrls)) { $skipNoImg++; logMsg("SKIP '" . mb_substr($g['name'],0,40,'UTF-8') . "': sin imagen"); continue; }

    $items = $g['items'];
    uasort($items, fn($a, $b) => $a['_PRICE'] <=> $b['_PRICE']);
    $skusOrdered = array_keys($items);
    $cheapestSku = $skusOrdered[0];
    $cheap = $items[$cheapestSku];
    $isFamily = count($items) > 1;

    // nombre + descripción
    $nameEnRaw = trim((string) $g['name']);
    $descText  = osCleanHtmlToText($g['desc']);
    if ($skipTranslation || $dryRun) {
        $nameEn = osApplyPrefix($nameEnRaw);
        $nameEs = osApplyPrefix($nameEnRaw);
        $descEn = $descText !== '' ? '<p>' . htmlspecialchars($descText) . '</p>' : '';
        $descEs = $descEn;
    } else {
        $trEs = osLlmLine(LLM_NAME_PROMPT_ES, $nameEnRaw);
        $nameEs = osApplyPrefix($trEs !== '' ? $trEs : $nameEnRaw);
        $nameEn = osApplyPrefix($nameEnRaw);
        // EN: maquetar el body_html (inglés). ES: traducir + maquetar.
        $descEn = '';
        if ($descText !== '') {
            $inLen = mb_strlen($descText, 'UTF-8');
            $fEn = llmCall(LLM_FORMAT_PROMPT_EN, $descText, 2500);
            $descEn = osFormatLooksValid($fEn, $inLen) ? $fEn : ('<p>' . htmlspecialchars($descText) . '</p>');
            if (!osFormatLooksValid($fEn, $inLen)) $formatFail++;
        }
        $descEs = '';
        if ($descText !== '') {
            $inLen = mb_strlen($descText, 'UTF-8');
            $esText = llmCall(LLM_TRANSLATE_ES, $descText, 2000);
            if (trim($esText) === '') $esText = $descText;
            $fEs = llmCall(LLM_FORMAT_PROMPT_ES, $esText, 2500);
            $descEs = osFormatLooksValid($fEs, $inLen) ? $fEs : ('<p>' . htmlspecialchars($esText) . '</p>');
            if (!osFormatLooksValid($fEs, $inLen)) $formatFail++;
        }
    }
    $descEs = osStripTagline(mbReformatDescription($descEs));
    $descEn = osStripTagline(mbReformatDescription($descEn));

    // Fundas de motor (Modelo [+ Longitud de eje]): añade imagen del eje + tabla de specs a la descripción.
    // (Los nombres de variante cortos/traducidos se generan en el loop de variantes más abajo.)
    if (!empty($g['is_motor'])) {
        $shaftRows = [];
        foreach ($g['items'] as $sku => $it) $shaftRows[] = ['sku' => $sku, 'meta' => osClassifyOpts($it['opts'] ?? [])];
        $shaftImgFile = (!$dryRun && !empty($g['shaft_img'])) ? osDownloadSharedImage($g['shaft_img']) : '';
        $descEs .= osShaftDescBlock($shaftImgFile, $shaftRows, 'es');
        $descEn .= osShaftDescBlock($shaftImgFile, $shaftRows, 'en');
    }

    // categoría: raíz > familia (si está clasificado). Sin familia → cuelga de la raíz.
    if ($family !== '') {
        $famEs = OS_FAMILY_ES[$family] ?? $family;
        $famCatId = getOrCreateCategory($mysqli, $famEs, $family, $rootCatId ?: 0, 1, $dryRun, $catCache, $createdLog);
        $targetCat = $famCatId ?: $rootCatId;
    } else {
        $targetCat = $rootCatId;
    }

    if ($dryRun) {
        $nInserted++;
        if ($nInserted <= 20) {
            logMsg(sprintf("  WOULD INSERT '%s' [%s] %s price=%.2f cost=%.2f g1=%.2f imgs=%d sku=%s",
                mb_substr($nameEs,0,42,'UTF-8'), ($family !== '' ? $family : 'sin familia'),
                $isFamily ? (count($items).' variantes') : 'suelto',
                $cheap['_PRICE'], $cheap['_COST'], $cheap['_G1'], count($imageUrls), $cheapestSku));
        }
        continue;
    }

    $tmpFiles = downloadImagesToTmp($imageUrls, MAX_SUBIMAGES + 1);
    if (empty($tmpFiles)) { $skipNoImg++; logMsg("SKIP '" . mb_substr($g['name'],0,40,'UTF-8') . "': imágenes no descargables"); continue; }

    $mysqli->begin_transaction();
    try {
        $qmodel = $mysqli->real_escape_string($cheapestSku);
        $price  = number_format($cheap['_PRICE'], 4, '.', '');
        $cost   = number_format($cheap['_COST'], 4, '.', '');
        $weight = number_format(DEFAULT_WEIGHT, 3, '.', '');
        // EAN máster: solo si es suelto (las familias no llevan máster — convención)
        $masterEan = (!$isFamily && $cheap['ean'] !== '') ? $cheap['ean'] : '';
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
        if (!$mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . G1_GROUP_ID . ", $pid, " . number_format($cheap['_G1'], 4, '.', '') . ", 1, 1)")) throw new Exception("g1: " . $mysqli->error);

        // imágenes
        $slug = osSlugify(osStripPrefix($nameEs));
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

        if ($isFamily) {
            $isShaft = !empty($g['is_motor']);
            // etiquetas: para fundas de motor → nombre corto traducido (modelo+eje);
            // resto → variante.title. Desambiguar con SKU si se repiten o están vacías.
            $rawLabels = []; $counts = [];
            foreach ($items as $sku => $it) {
                $lab = $isShaft ? osShaftVariantName(osClassifyOpts($it['opts'] ?? []), 'en') : $it['label'];
                $rawLabels[$sku] = $lab;
                $counts[$lab] = ($counts[$lab] ?? 0) + 1;
            }
            $ovsUsados = [];   // guardia anti-colisión pa-dupe por producto (ver francobordo_pa_duplicates)
            foreach ($items as $sku => $it) {
                if ($isShaft) {
                    $meta   = osClassifyOpts($it['opts'] ?? []);
                    $labEn  = osShaftVariantName($meta, 'en');
                    $labEs  = osShaftVariantName($meta, 'es');
                } else {
                    $labEn = $rawLabels[$sku];
                    $labEs = osTranslateLabel($labEn, $skipTranslation, $labelTransCache);
                }
                $needsSku = ($labEn === '' || ($counts[$labEn] ?? 0) > 1);
                // truncar la BASE antes de añadir el sufijo SKU para que éste sobreviva el límite de 64
                $finalEn = ($labEn === '') ? mb_substr($sku, 0, 64, 'UTF-8') : ($needsSku ? osFitName($labEn, ' · ' . $sku) : mb_substr($labEn, 0, 64, 'UTF-8'));
                $finalEs = ($labEs === '') ? mb_substr($sku, 0, 64, 'UTF-8') : ($needsSku ? osFitName($labEs, ' · ' . $sku) : mb_substr($labEs, 0, 64, 'UTF-8'));

                $delta  = round($it['_PRICE'] - $cheap['_PRICE'], 4);
                $prefix = $delta < 0 ? '-' : '+';
                $valueId = findOrCreateOptionValue($mysqli, $finalEs, $finalEn);
                if (isset($ovsUsados[$valueId])) {
                    // backstop: este OV ya se usó para otra variante de ESTE producto -> desambiguar por SKU
                    $finalEs = osFitName(($labEs !== '' ? $labEs : $sku), ' · ' . $sku);
                    $finalEn = osFitName(($labEn !== '' ? $labEn : $sku), ' · ' . $sku);
                    $valueId = findOrCreateOptionValue($mysqli, $finalEs, $finalEn);
                }
                $ovsUsados[$valueId] = true;
                $qprovv = $mysqli->real_escape_string($sku);
                $vEan = $it['ean'] !== '' ? $it['ean'] : '';
                $qvEan = $mysqli->real_escape_string($vEan);
                if (!$mysqli->query("INSERT INTO products_attributes SET
                    products_id=$pid, options_id=" . VARIANT_OPTION_ID . ", options_values_id=$valueId,
                    options_values_price=" . number_format(abs($delta), 4, '.', '') . ", price_prefix='$prefix',
                    reference='$qprovv', reference_prov='$qprovv', products_attributes_ean='$qvEan',
                    options_values_weight=0.000, weight_prefix='+'"))
                    throw new Exception("attr: " . $mysqli->error);
                $paId = (int) $mysqli->insert_id;
                if ($vEan === '') {
                    $genEan = generateInternalEan13($paId, EAN_INTERNAL_PREFIX);
                    if ($genEan !== '') $mysqli->query("UPDATE products_attributes SET products_attributes_ean='" . $mysqli->real_escape_string($genEan) . "' WHERE products_attributes_id=$paId");
                }
                $g1Delta = round($it['_G1'] - $cheap['_G1'], 4);
                $g1Prefix = $g1Delta < 0 ? '-' : '+';
                if (!$mysqli->query("INSERT INTO products_attributes_groups (products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id, options_values_weight, weight_prefix) VALUES ($paId, " . G1_GROUP_ID . ", " . number_format(abs($g1Delta), 4, '.', '') . ", '$g1Prefix', $pid, 0, '+')"))
                    throw new Exception("attr_groups: " . $mysqli->error);
                $nVariants++;
            }
        }

        $mysqli->commit();

        // suelto sin EAN real → interno por pid
        if (!$isFamily && $masterEan === '') {
            $genEan = generateInternalEan13($pid, EAN_INTERNAL_PREFIX);
            if ($genEan !== '') $mysqli->query("UPDATE products SET product_ean='" . $mysqli->real_escape_string($genEan) . "' WHERE products_id=$pid AND (product_ean IS NULL OR product_ean='')");
        }

        $nInserted++;
        if ($isFamily) $nVarFamilies++; else $nSingles++;
        logMsg(sprintf("OK pid=%d %s cheap=%s [%s] cat='%s' price=%.2f cost=%.2f g1=%.2f imgs=%d name='%s'",
            $pid, $isFamily ? 'FAMILIA' : 'SUELTO', $cheapestSku,
            $isFamily ? (count($items).'v') : '1', ($family !== '' ? $family : 'raíz'),
            $cheap['_PRICE'], $cheap['_COST'], $cheap['_G1'], 1 + count($imgFinal),
            mb_substr($nameEs, 0, 45, 'UTF-8')));
    } catch (Exception $e) {
        $mysqli->rollback();
        $errors++;
        foreach ($tmpFiles as $t) if (file_exists($t)) @unlink($t);
        logMsg("ERROR grupo=$gid: " . $e->getMessage());
    }
}

$elapsed = microtime(true) - $ts0;
logMsg("==================== RESUMEN ====================");
logMsg(sprintf("Insertados: %d (%d familias + %d sueltos, %d variantes) en %.1fs", $nInserted, $nVarFamilies, $nSingles, $nVariants, $elapsed));
logMsg("Skip existentes: $skipExist | skip sin imagen: $skipNoImg | maquetados fallidos: $formatFail | errores: $errors");
if (!empty($createdLog)) {
    logMsg(($dryRun ? "Se crearían" : "Creadas") . " " . count($createdLog) . " entidades:");
    foreach ($createdLog as $c) logMsg("  · $c");
}

end_action:
?>
    </div>
    <p style="margin-top:15px;"><a href="<?php echo tep_href_link('import-oceansouth-altas.php'); ?>" class="xbutton small hv9">← Volver</a></p>
<?php else: ?>
    <h2>Importador Oceansouth (altas)</h2>
    <?php
        $skuErr = '';
        $skuMap = osLoadSkuMap($skuErr);
        $catMsg = '';
        $catalog = $forceRefresh ? osLoadCatalog(true, $catMsg) : (loadJson(OS_CACHE) ?: []);
        if ($skuErr) {
            echo '<p style="color:red;">' . htmlspecialchars($skuErr) . '</p>';
        }
        if (empty($catalog)) {
            echo '<p style="color:#a60;">No hay catálogo web en cache. Pulsa <em>Refrescar catálogo web</em> para descargarlo de oceansouth.com.</p>';
            echo '<p><a href="' . tep_href_link('import-oceansouth-altas.php', 'refresh=1') . '" class="xbutton small hv9">Refrescar catálogo web</a></p>';
        }
        if (!empty($catalog) && !empty($skuMap)) {
            $groups = osBuildGroups($catalog, $skuMap, false);
            $nFam = 0; $nSingle = 0; $nVar = 0;
            foreach ($groups as $g) { if (count($g['items']) > 1) { $nFam++; $nVar += count($g['items']); } else $nSingle++; }
            $famMsg = '';
            $famMap = $forceRefresh ? osLoadFamilyMap(true, $famMsg) : (loadJson(OS_FAMILIES_CACHE) ?: []);
            $famCount = []; $noFam = 0;
            foreach ($groups as $g) {
                $fm = $famMap[$g['pid_web']] ?? '';
                if ($fm === '') { $noFam++; continue; }
                $famCount[$fm] = ($famCount[$fm] ?? 0) + 1;
            }
            uksort($famCount, fn($a, $b) => array_search($a, array_keys(OS_FAMILIES)) <=> array_search($b, array_keys(OS_FAMILIES)));
    ?>
    <p style="color:#666;font-size:13px;">
        Fuente coste/EAN: <code><?php echo htmlspecialchars(basename(osFindXlsx() ?: '')); ?></code>
        (hoja <code>PRICE LIST 2025</code>: A=SKU, C=EUR NET, D=Barcode) — <strong><?php echo count($skuMap); ?></strong> SKUs.
        Enriquecimiento: Shopify <code>oceansouth.com/en-eu</code> (nombre, descripción, PVP, imágenes, variantes) por SKU.
        <strong><?php echo count($catalog); ?></strong> productos web → <strong><?php echo count($groups); ?></strong> importables
        (<?php echo $nFam; ?> con variantes / <?php echo $nVar; ?> variantes + <?php echo $nSingle; ?> sueltos). Catálogo: <?php echo htmlspecialchars($catMsg ?: 'cache'); ?>.
    </p>
    <p style="background:#fffbe6;border:1px solid #ffd700;padding:10px;border-radius:4px;font-size:13px;">
        <strong>Reglas siempre aplicadas</strong>:<br>
        • Fabricante: <code><?php echo MFG_NAME; ?></code> (se crea si no existe).<br>
        • Nombre con <strong>prefijo "Oceansouth "</strong>.<br>
        • <code>products_cost</code> = EUR NET del xlsx &nbsp;|&nbsp;
          <code>products_price</code> = roundToNickel(PVP_web / 1,21) — el PVP web es IVA incluido &nbsp;|&nbsp;
          <code>G1</code> = roundToNickel(tiers de margen + piso cost×<?php echo G1_FLOOR_FACTOR; ?>).<br>
        • Variantes Shopify → un producto con variantes (options_id=3 "Modelo"); padre = variante más barata.<br>
        • Categorías: <strong><?php echo PARENT_CATEGORY_NAME_ES; ?></strong> (status 0, oculta) &gt; subcategoría por <strong>familia</strong> del megamenú (Fundas de Barco, Biminis…); sin familia → cuelga de la raíz.<br>
        • Descripción y nombre traducidos EN→ES vía LLM (EN se maqueta); glosario náutico (salvo "sin LLM").<br>
        • EAN real del xlsx por variante/producto; sin EAN → interno prefijo <?php echo EAN_INTERNAL_PREFIX; ?> (nunca <code>299…</code>).<br>
        • <strong>Sin imagen → no se importa.</strong> Imágenes Shopify (1 principal + hasta <?php echo MAX_SUBIMAGES; ?> sub).<br>
        • Variantes sin coste en el xlsx se descartan; producto sin variante priceable se salta.<br>
        • <code>products_status=2</code> (revisión), <code>check_stock=0</code>, stock NO se toca.<br>
        • Skip si el SKU (por fabricante) o el EAN (global) ya existe en BD.
    </p>
    <form method="get" style="background:#f5f5f5;padding:15px;border-radius:5px;">
        <p><strong>Familia a importar</strong>:
            <select name="family" style="min-width:340px;">
                <option value="all">— Todas las familias (<?php echo array_sum($famCount) + $noFam; ?>) —</option>
                <?php foreach ($famCount as $fam => $n) {
                    $famEs = OS_FAMILY_ES[$fam] ?? $fam;
                    $sel = (mb_strtolower($selectedFamily,'UTF-8') === mb_strtolower($fam,'UTF-8')) ? ' selected' : '';
                    echo '<option value="' . htmlspecialchars($fam) . '"' . $sel . '>' . htmlspecialchars($famEs) . ' (' . $n . ')</option>';
                } ?>
            </select>
            <?php if (empty($famMap)) echo '<br><span style="color:#a60;font-size:12px;">⚠ Aún no hay clasificación de familias en cache — marca "Refrescar catálogo web" para construirla.</span>'; ?>
            <?php if ($noFam > 0) echo '<br><span style="color:#888;font-size:12px;">' . $noFam . ' producto(s) sin familia → cuelgan de la raíz "Oceansouth Nuevos".</span>'; ?>
        </p>
        <p><strong>Handles concretos</strong> (opcional, coma/espacio; ej. <code>tasar-covers</code>; ignora el filtro de familia):<br>
            <textarea name="handles" rows="2" style="width:100%;" placeholder="tasar-covers, fixed-socket-pedestal-flat-base"><?php echo htmlspecialchars($handlesParam); ?></textarea>
        </p>
        <p><label><input type="checkbox" name="dry_run" value="1" checked> Dry-run (sin cambios)</label></p>
        <p><label><input type="checkbox" name="skip_translation" value="1"> Saltar LLM (nombre/descripción quedan en inglés, mucho más rápido)</label></p>
        <p><label><input type="checkbox" name="no_group" value="1"> Sin variantes (cada SKU como producto suelto)</label></p>
        <p><label><input type="checkbox" name="refresh" value="1"> Refrescar catálogo web (re-descarga products.json + reconstruye familias)</label></p>
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
