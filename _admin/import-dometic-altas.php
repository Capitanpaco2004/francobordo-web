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
 * Importador Dometic (altas) — SIN ATRIBUTOS (cada fila = producto suelto)
 *
 * Fuentes:
 *   - xlsx  /import/Dometic/Lista de precios.xlsx  (hoja "Lista de precios PVP"):
 *       A = SKU (numérico)  ·  B = DESCRIPCIÓN (inglés)  ·  C = PVP (IVA NO incl.)
 *       D = DESCUENTO  ·  E = PRECIO NETO (= coste)  ·  F = CÓDIGO EAN (EAN-13 real)
 *       Las cabeceras de sección en col A (sin SKU) definen la jerarquía de categorías
 *       por color de fondo: cian=Departamento, negro=Familia, gris=Serie.
 *       C/E/F son fórmulas XLOOKUP a un libro externo → se leen sus VALORES cacheados.
 *   - Web   https://www.dometic.com/es-es  (Magento + Next.js/Algolia):
 *       descripción ES (JSON-LD), imágenes (externalassets + galería) y ficha técnica.
 *       Enlace por SKU: /es-es/buscar?q=SKU embebe los resultados Algolia → se busca
 *       el SKU EXACTO y se obtiene su slug → /es-es/producto/<slug>.
 *
 * Cobertura web ~60%: muchos productos pro de autocaravana (aires FJX, ventanas S4,
 * baterías…) NO están en el site de consumo. Decisión usuario: SIN ficha web → NO se
 * importa (igual que el resto de importadores: sin imagen no se inserta).
 *
 * Decisiones (usuario 2026-05-26):
 *   - products_price = roundToNickel(col C)   (col C ya es PVP NETO sin IVA)
 *   - products_cost  = col E (precio neto)
 *   - G1 (Profesionales) = roundToNickel(tiers de margen + piso cost×1,10)
 *   - SIN variantes/atributos. Cada SKU es un producto suelto.
 *   - Categorías: "Dometic Nuevos" (status 0, oculta) > Departamento > [Familia] > Serie
 *     replicando la jerarquía del xlsx (Title-Case de los nombres ALL-CAPS).
 *   - Nombre: el de Dometic (JSON-LD, ya incluye "Dometic ..."); fallback "Dometic "+descEN.
 *   - Descripción ES de la web (JSON-LD) + ficha técnica; EN traducido vía LLM (glosario).
 *   - EAN real del xlsx (col F); sin EAN válido → interno prefijo 28 (id-based, sin colisión).
 *   - products_status=2, check_stock=0, stock NO se toca.
 *   - Dedup: products_model/reference_prov/EAN GLOBAL (Dometic ya tiene 700+ legacy sin origin).
 * ────────────────────────────────────────────────────────────────────────── */

const DOM_DIR        = '/home/francobordo/public_html/import/Dometic/';
const DOM_CACHE_DIR  = DOM_DIR . 'cache/';
const DOM_PARSE_CACHE= DOM_CACHE_DIR . 'parsed.json';
const DOM_WEB_DIR    = DOM_CACHE_DIR . 'web/';
define('IMG_ABS_DIR', dirname(dirname(__FILE__)) . '/images/productos/');

const SEARCH_URL  = 'https://www.dometic.com/es-es/buscar?q=';
const PRODUCT_URL = 'https://www.dometic.com/es-es/producto/';

const PARENT_CATEGORY_NAME_ES = 'Dometic Nuevos';
const PARENT_CATEGORY_NAME_EN = 'Dometic New';
const TAX_CLASS_IVA21 = 1;
const LANG_ID_ES      = 3;
const LANG_ID_EN      = 1;
const G1_GROUP_ID     = 1;
const PRODUCT_NAME_MAX= 96;
const MFG_NAME        = 'Dometic';
const ORIGIN_FLAG     = 'dometic';
const EAN_INTERNAL_PREFIX = 28;          // compartido id-based (sin colisión); EANs Dometic casi todos reales
const IMG_HTTP_TIMEOUT= 25;
const IMG_MIN_BYTES   = 2048;
const MAX_SUBIMAGES   = 6;
const DOM_VAT_RATE    = 0.21;
const G1_FLOOR_FACTOR = 1.10;
const DEFAULT_WEIGHT  = 1.0;
const WEB_SLEEP_US    = 250000;          // 0,25s entre peticiones web (cortesía)
const MAX_SPECS       = 40;

const LLM_URL   = 'http://217.127.199.171:28001/v1/chat/completions';
const LLM_MODEL = 'qwen36-sakamaki-nvfp4';

// Glosario náutico/autocaravana ligero (Dometic = RV + náutica + camping)
const GLOSARIO = "Glosario EN→ES: RV=autocaravana, motorhome=autocaravana, caravan=caravana, awning=toldo, air conditioner=aire acondicionado, fridge/refrigerator=nevera/frigorífico, cooler=nevera portátil, sink=fregadero, hob/cooktop=placa de cocina, toilet=inodoro/sanitario, holding tank=depósito de aguas residuales, water tank=depósito de agua, blind=estor/oscurecedor, fly screen=mosquitera, skylight/rooflight=claraboya, window=ventana, hinged=abatible, sliding=corredera, inverter=inversor, charger=cargador, solar panel=panel solar, battery=batería, hull=casco, deck=cubierta, cleat=cornamusa, hatch=tapa de registro, fender=defensa.";

const LLM_TRANSLATE_EN = "Translate the following Spanish product description into ENGLISH. Keep ALL information, numbers, measurements and units (mm, cm, m, kg, W, V, A, L, °C). Do NOT summarize or invent. Marine/RV glossary ES→EN: autocaravana=RV, caravana=caravan, toldo=awning, aire acondicionado=air conditioner, nevera=fridge, fregadero=sink, inodoro/sanitario=toilet, claraboya=skylight, ventana=window, abatible=hinged, corredera=sliding, inversor=inverter, cargador=charger, panel solar=solar panel, batería=battery. Return ONLY the English translation, no comments.";

const LLM_TRANSLATE_LABELS_ES = "Traduce al ESPAÑOL las ETIQUETAS de esta ficha técnica (formato 'Etiqueta: valor', una por línea). Traduce SOLO la etiqueta (parte antes de los dos puntos); deja el valor EXACTAMENTE igual (números, unidades, modelos, códigos intactos). Conserva el mismo número de líneas y el formato 'Etiqueta: valor'. No añadas comentarios.";

const LLM_FORMAT_PROMPT_ES = "Eres un experto en maquetar fichas de producto. Recibes una descripción comercial YA EN ESPAÑOL (posiblemente seguida de una sección de especificaciones técnicas) y la transformas en HTML legible.\n\nREGLAS:\n1. Primer <p>: frase introductoria (máx 5 frases por <p>).\n2. Si hay un bloque de especificaciones técnicas, ponlo bajo <p>&nbsp;</p><p><strong>Especificaciones técnicas</strong></p> y cada dato en su propio <p>• <strong>Etiqueta</strong>: valor</p>.\n3. Demás características, una por <p>• texto</p> (nunca <ul>/<li>).\n4. Elimina ™ ® ©. Palabras EN MAYÚSCULAS de 4+ letras → Title Case (conserva acrónimos: LED, IP, USB, UV, PVC, AISI, RV, AGM, AC, DC).\n5. NO traduzcas, NO resumas, NO inventes: conserva TODO el texto, solo añades estructura HTML.\n6. Etiquetas permitidas: <p>, <strong>, <a>. Prohibidas: <h1>-<h6>, <br>, <div>, <span>, <ul>, <li>, <table>, <ol>.\n7. Salida: SOLO el HTML, sin markdown ni comentarios.";
const LLM_FORMAT_PROMPT_EN = "You format product datasheets. You receive a description ALREADY IN ENGLISH (possibly followed by a technical specifications block) and turn it into clean HTML.\n\nRULES:\n1. First <p>: intro sentence (max 5 sentences per <p>).\n2. If there is a technical specs block, put it under <p>&nbsp;</p><p><strong>Technical specifications</strong></p> and each datum in its own <p>• <strong>Label</strong>: value</p>.\n3. Other features, one per <p>• text</p> (never <ul>/<li>).\n4. Remove ™ ® ©. ALL-CAPS words 4+ letters → Title Case (keep acronyms: LED, IP, USB, UV, PVC, AISI, RV, AGM, AC, DC).\n5. DO NOT translate, summarize or invent: keep ALL text, only add HTML structure.\n6. Allowed tags: <p>, <strong>, <a>. Forbidden: <h1>-<h6>, <br>, <div>, <span>, <ul>, <li>, <table>, <ol>.\n7. Output: ONLY the HTML, no markdown, no comments.";

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$max    = isset($_POST['max']) ? (int) $_POST['max'] : (isset($_GET['max']) ? (int) $_GET['max'] : 0);
$dryRun = isset($_POST['dry_run']) || isset($_GET['dry_run']) || $action === 'dry_run';
$skipTranslation = isset($_POST['skip_translation']) || isset($_GET['skip_translation']);
$forceRefresh = isset($_POST['refresh']) || isset($_GET['refresh']);          // re-parsea xlsx
$webRefresh   = isset($_POST['web_refresh']) || isset($_GET['web_refresh']);  // ignora cache web
$skipSpecs    = isset($_POST['skip_specs']) || isset($_GET['skip_specs']);
$allowNoImage = isset($_POST['allow_no_image']) || isset($_GET['allow_no_image']);  // importa stubs del xlsx sin ficha web
$codesParam   = trim((string) ($_POST['codes'] ?? $_GET['codes'] ?? ''));
$selectedDept = trim((string) ($_POST['dept'] ?? $_GET['dept'] ?? 'all'));

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
    if (strncmp($payload, '299', 3) === 0) return '';
    $check = ean13Checksum($payload);
    return $check < 0 ? '' : ($payload . $check);
}

/** Redondea un PVP NETO de modo que el precio CON IVA quede en múltiplo de 0,05. */
function roundToNickel($net) {
    $withIva = ((float) $net) * (1 + DOM_VAT_RATE);
    $rounded = round($withIva * 20) / 20;
    return round($rounded / (1 + DOM_VAT_RATE), 4);
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

/** Marca real del producto a partir de la descripción del xlsx (col B).
 * La lista de precios mezcla varias marcas del grupo; '' (Dometic) es el defecto. */
function domDetectBrand($descB) {
    $b = (string) $descB;
    if (preg_match('/B[üu]ttner/iu', $b) || preg_match('/\bTempra\b/i', $b)) return 'Büttner';
    if (preg_match('/Green\s?Power/i', $b)) return 'Green Power';
    if (preg_match('/\bDyno/i', $b))        return 'Dyno';
    if (preg_match('/\bNDS\b/i', $b))       return 'NDS';
    return 'Dometic';
}

function domSlugify($text, $maxLen = 50) {
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

/** ALL-CAPS → Title Case conservando códigos/series (palabras con dígito, ≤3 letras o acrónimos). */
function domTitleCase($s) {
    $s = trim(preg_replace('/\s+/u', ' ', (string) $s));
    if ($s === '') return '';
    $minus = ['Y','E','O','U','DE','DEL','LA','EL','LOS','LAS','EN','PARA','POR','CON','A'];
    $words = explode(' ', $s);
    $out = [];
    foreach ($words as $i => $w) {
        if ($w === '') continue;
        // separar puntuación final (comas)
        $tail = '';
        if (preg_match('/[,.;:]+$/', $w, $mm)) { $tail = $mm[0]; $w = substr($w, 0, -strlen($tail)); }
        $upper = mb_strtoupper($w, 'UTF-8');
        if ($i > 0 && in_array($upper, $minus, true)) { $out[] = mb_strtolower($w, 'UTF-8') . $tail; continue; }
        // conservar tal cual códigos/series: con dígito (FJX4233, S4, CFX5) o ≤3 letras (PR, PW, RV, MPS)
        if (preg_match('/\d/', $w) || mb_strlen($w, 'UTF-8') <= 3) { $out[] = $w . $tail; continue; }
        $out[] = mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8') . mb_strtolower(mb_substr($w, 1, null, 'UTF-8'), 'UTF-8') . $tail;
    }
    return implode(' ', $out);
}

function domBrowserUA() {
    return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';
}

/** GET con cache en disco gzip (cache/web/<key>.html.gz). */
function domHttpGet($url, $cacheKey, $forceRefresh, &$fromCache) {
    $fromCache = false;
    if (!is_dir(DOM_WEB_DIR)) @mkdir(DOM_WEB_DIR, 0775, true);
    $cf = DOM_WEB_DIR . $cacheKey . '.html.gz';
    if (!$forceRefresh && file_exists($cf)) {
        $raw = @file_get_contents($cf);
        $body = $raw !== false ? @gzdecode($raw) : false;
        if ($body !== false && $body !== '') { $fromCache = true; return $body; }
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => domBrowserUA(),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => ['Accept-Language: es-ES,es;q=0.9,en;q=0.8'],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($body === false || $code !== 200 || strlen($body) < 5000) return '';
    @file_put_contents($cf, gzencode($body, 6));
    usleep(WEB_SLEEP_US);
    return $body;
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
        CURLOPT_USERAGENT => domBrowserUA(),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => ['Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8', 'Referer: https://www.dometic.com/'],
    ]);
    $ok = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
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
        $tmpAbs = IMG_ABS_DIR . 'dom-tmp-' . uniqid('', true) . '.jpg';
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
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload, CURLOPT_TIMEOUT => 90, CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($resp !== false && $code === 200) {
            $j = json_decode($resp, true);
            $content = $j['choices'][0]['message']['content'] ?? null;
            if (is_string($content) && trim($content) !== '') return trim($content);
        }
        usleep(400000);
    }
    return '';
}
function domFormatLooksValid($html, $minLenInput) {
    if (!is_string($html) || trim($html) === '') return false;
    if (stripos($html, '<p>') === false && stripos($html, '<strong>') === false) return false;
    $plainOut = mb_strlen(trim(strip_tags($html)), 'UTF-8');
    if ($minLenInput > 200 && $plainOut < $minLenInput * 0.4) return false;
    return true;
}

/* ── xlsx → filas + jerarquía de categorías (cacheado por firma) ── */
function domFindXlsx() {
    $best = null; $bestT = 0;
    foreach (glob(DOM_DIR . '*.xlsx') ?: [] as $f) {
        if (strpos(basename($f), '~$') === 0) continue;
        $t = filemtime($f);
        if ($t > $bestT) { $bestT = $t; $best = $f; }
    }
    return $best;
}
function domCellVal($cell) {
    if ($cell === null) return null;
    if ($cell->isFormula()) { $v = $cell->getOldCalculatedValue(); return ($v === null) ? null : $v; }
    return $cell->getValue();
}
function domParseXlsx($forceRefresh, &$err) {
    $err = '';
    $f = domFindXlsx();
    if (!$f) { $err = 'No se encontró ningún .xlsx en ' . DOM_DIR; return []; }
    $sig = basename($f) . ':' . filemtime($f) . ':' . filesize($f);
    if (!$forceRefresh && file_exists(DOM_PARSE_CACHE)) {
        $c = json_decode((string) file_get_contents(DOM_PARSE_CACHE), true);
        if (is_array($c) && ($c['sig'] ?? '') === $sig && isset($c['rows'])) return $c['rows'];
    }
    try {
        $reader = IOFactory::createReaderForFile($f);
        $reader->setReadDataOnly(false);
        $ss = $reader->load($f);
        $sh = $ss->getSheet(0);
    } catch (Throwable $e) { $err = 'xlsx: ' . $e->getMessage(); return []; }
    $hr = $sh->getHighestRow();
    $dept = ''; $family = ''; $series = '';
    $rows = [];
    for ($r = 5; $r <= $hr; $r++) {
        $a = trim((string) $sh->getCell("A$r")->getValue());
        if ($a === '') continue;
        if (preg_match('/^\d{6,}$/', $a)) {
            $pvp  = domCellVal($sh->getCell("C$r"));
            $cost = domCellVal($sh->getCell("E$r"));
            $ean  = trim((string) domCellVal($sh->getCell("F$r")));
            $path = array_values(array_filter([$dept, $family, $series], fn($x) => $x !== ''));
            $rows[] = [
                'sku'  => $a,
                'desc' => trim((string) $sh->getCell("B$r")->getValue()),
                'pvp'  => ($pvp === null || $pvp === '') ? null : (float) $pvp,
                'cost' => ($cost === null || $cost === '') ? 0.0 : (float) $cost,
                'ean'  => isValidEan13($ean) ? $ean : '',
                'path' => $path,
            ];
            continue;
        }
        // cabecera de sección → nivel por color de fondo
        $fill = $sh->getStyle("A$r")->getFill()->getStartColor()->getARGB();
        $label = domTitleCase($a);
        if ($fill === 'FF00FFFF') { $dept = $label; $family = ''; $series = ''; }
        elseif ($fill === 'FF000000') { $family = $label; $series = ''; }
        else { $series = $label; }   // gris (D9D9D9) u otro → serie/hoja
    }
    if (!empty($rows)) {
        if (!is_dir(DOM_CACHE_DIR)) @mkdir(DOM_CACHE_DIR, 0775, true);
        @file_put_contents(DOM_PARSE_CACHE, json_encode(['sig' => $sig, 'rows' => $rows], JSON_UNESCAPED_UNICODE));
    }
    return $rows;
}

/* ── Web Dometic: búsqueda Algolia (embebida) → slug → ficha ── */
/** Busca el SKU exacto en los resultados Algolia embebidos. Devuelve [slug, image_url] o null. */
function domParseSearch($html, $sku) {
    if ($html === '') return null;
    // objetos: \"sku\":\"123\",\"name\":\"..\",\"url\":\"slug\",\"image_url\":\"..\"
    if (preg_match('/\\\\"sku\\\\":\\\\"' . preg_quote($sku, '/') . '\\\\",\\\\"name\\\\":\\\\"(?:[^\\\\"]|\\\\\\\\.)*?\\\\",\\\\"url\\\\":\\\\"([^\\\\"]+)\\\\",\\\\"image_url\\\\":\\\\"([^\\\\"]+)\\\\"/', $html, $m)) {
        $slug = stripcslashes($m[1]);
        $img  = stripcslashes($m[2]);
        return ['slug' => $slug, 'image' => $img];
    }
    return null;
}

/** Parsea la ficha de producto: nombre ES, descripción ES, imágenes, specs. */
function domParseProduct($html, $sku, $skipSpecs) {
    if ($html === '') return null;
    $out = ['name' => '', 'descEs' => '', 'images' => [], 'specs' => []];
    // JSON-LD Product
    if (preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $mm)) {
        foreach ($mm[1] as $j) {
            $d = json_decode(trim($j), true);
            if (!is_array($d)) continue;
            $type = $d['@type'] ?? '';
            if ($type === 'Product') {
                $out['name']   = trim((string) ($d['name'] ?? ''));
                $out['descEs'] = trim((string) ($d['description'] ?? ''));
                $imgs = $d['image'] ?? [];
                if (is_string($imgs)) $imgs = [$imgs];
                foreach ((array) $imgs as $u) if (is_string($u) && $u !== '') $out['images'][] = $u;
                break;
            }
        }
    }
    // copia "des-escapada" para regex de imágenes y specs
    $u = str_replace(['\\/', '\\"'], ['/', '"'], $html);
    // imagen de alta resolución keyed por SKU
    if (preg_match_all('#https://media\.dometic\.com/externalassets/[^\s"]*?_' . preg_quote($sku, '#') . '_[^\s"]+?\.(?:png|jpg|jpeg|webp)#i', $u, $em)) {
        // estas van primero
        $out['images'] = array_merge(array_unique($em[0]), $out['images']);
    }
    // galería media/catalog (excluye iconos globalassets)
    if (preg_match_all('#https://www\.dometic\.com/media/catalog/product/[^\s"]+?\.(?:png|jpg|jpeg|webp)#i', $u, $gm)) {
        foreach ($gm[0] as $g) $out['images'][] = $g;
    }
    $out['images'] = array_values(array_unique(array_filter($out['images'])));

    if (!$skipSpecs) {
        // diccionario code→label (inglés): "dom_xxx":"Label"
        $labels = [];
        if (preg_match_all('/"(dom_[a-z0-9]+)":"([^"]{1,80})"/', $u, $lm, PREG_SET_ORDER)) {
            foreach ($lm as $x) if (!isset($labels[$x[1]])) $labels[$x[1]] = $x[2];
        }
        // pares code→value
        $vals = [];
        if (preg_match_all('/"code":"(dom_[a-z0-9]+)","value":"([^"]*)"/', $u, $vm, PREG_SET_ORDER)) {
            foreach ($vm as $x) { $code = $x[1]; $v = stripcslashes($x[2]); if (!isset($vals[$code])) $vals[$code] = $v; }
        }
        // selected_options con label legible
        if (preg_match_all('/"code":"(dom_[a-z0-9]+)","selected_options":\[\{"label":"([^"]*)"/', $u, $sm, PREG_SET_ORDER)) {
            foreach ($sm as $x) { $code = $x[1]; $v = stripcslashes($x[2]); if ($v !== '' && (!isset($vals[$code]) || $vals[$code] === '')) $vals[$code] = $v; }
        }
        $deny = '/(marketing|publication|published|identifier|numberorder|sortorder|^dom_skunumber$|^dom_skumodel$|^dom_skuean13$|seo|url|image|metatitle|metadescription|channel|market|imperial|bulletlist|gridfeature|installation|manual|datasheet|document|video|asset|certificate)/i';
        $specs = [];
        foreach ($vals as $code => $v) {
            $v = trim($v);
            if ($v === '' || strtolower($v) === 'no' || strtolower($v) === 'n/a') continue;
            if (preg_match($deny, $code)) continue;
            if (preg_match('/^[\$\[\{]/', $v) || strpos($v, '\\') !== false) continue;  // refs Next.js / blobs JSON
            $label = $labels[$code] ?? '';
            if ($label === '') continue;                          // sin etiqueta legible → fuera
            if (mb_strlen($v, 'UTF-8') > 120) continue;            // valores larguísimos (texto) fuera
            $specs[] = [$label, $v];
            if (count($specs) >= MAX_SPECS) break;
        }
        $out['specs'] = $specs;
    }
    if ($out['name'] === '' && empty($out['images'])) return null;  // ficha vacía/no válida
    return $out;
}

/** Enriquecimiento web por SKU (cacheado en cache/web/<sku>.json.gz). null = sin ficha web. */
function domEnrich($sku, $webRefresh, $skipSpecs, &$note) {
    $note = '';
    if (!is_dir(DOM_WEB_DIR)) @mkdir(DOM_WEB_DIR, 0775, true);
    $jf = DOM_WEB_DIR . $sku . '.json.gz';
    if (!$webRefresh && file_exists($jf)) {
        $raw = @gzdecode((string) @file_get_contents($jf));
        $j = $raw ? json_decode($raw, true) : null;
        if (is_array($j)) { $note = 'cache'; return $j['data'] ?? null; }
    }
    $fc = false;
    $sHtml = domHttpGet(SEARCH_URL . urlencode($sku), 's-' . $sku, $webRefresh, $fc);
    $hit = domParseSearch($sHtml, $sku);
    if (!$hit) {
        @file_put_contents($jf, gzencode(json_encode(['data' => null]), 6));   // cachea "no existe"
        $note = 'sin match';
        return null;
    }
    $pHtml = domHttpGet(PRODUCT_URL . $hit['slug'], 'p-' . $sku, $webRefresh, $fc);
    $data = domParseProduct($pHtml, $sku, $skipSpecs);
    if ($data) {
        if (empty($data['images']) && !empty($hit['image'])) $data['images'][] = $hit['image'];
        $data['slug'] = $hit['slug'];
    }
    @file_put_contents($jf, gzencode(json_encode(['data' => $data], JSON_UNESCAPED_UNICODE), 6));
    $note = $data ? 'web' : 'ficha vacía';
    return $data;
}

function specsToText(array $specs) {
    if (empty($specs)) return '';
    $lines = [];
    foreach ($specs as $s) $lines[] = '- ' . $s[0] . ': ' . $s[1];
    return "Especificaciones técnicas:\n" . implode("\n", $lines);
}

/* ── Dedup: model / reference_prov / EAN GLOBAL (Dometic ya tiene legacy sin origin) ── */
function buildExistingMap($mysqli) {
    $existing = [];
    foreach ([
        "SELECT LOWER(products_model) m FROM products WHERE products_model<>''",
        "SELECT LOWER(reference_prov) m FROM products WHERE reference_prov<>''",
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

/** Crea la cadena de categorías de un path y devuelve el id de la hoja (la más profunda). */
function ensureCategoryPath($mysqli, array $path, $rootId, $dryRun, &$cache, &$createdLog) {
    $parent = $rootId;
    foreach ($path as $name) {
        $cid = getOrCreateCategory($mysqli, $name, $name, $parent ?: 0, 1, $dryRun, $cache, $createdLog);
        if ($cid) $parent = $cid;       // en dry-run cid puede ser 0; mantenemos el último real
    }
    return $parent ?: $rootId;
}

function buildName($webName, $descEn, $brand = 'Dometic') {
    if ($brand !== 'Dometic') {
        // marcas propias (NDS/Büttner/Green Power/Dyno): la web de Dometic no las nombra
        // correctamente; el nombre del xlsx (col B) ya lleva la marca/modelo.
        $n = trim((string) $descEn);
        if ($n !== '' && stripos($n, $brand) === false && stripos($n, 'tempra') === false) $n = $brand . ' ' . $n;
    } else {
        $n = trim((string) $webName);
        if ($n === '') {
            $n = trim((string) $descEn);
            if ($n !== '' && stripos($n, 'dometic') === false) $n = 'Dometic ' . $n;
        }
    }
    $n = html_entity_decode($n, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $n = trim(preg_replace('/\s+/u', ' ', $n));
    if ($n === '') $n = ($brand !== 'Dometic' ? $brand : 'Dometic');
    if (mb_strlen($n, 'UTF-8') > PRODUCT_NAME_MAX) $n = rtrim(mb_substr($n, 0, PRODUCT_NAME_MAX, 'UTF-8'));
    return $n;
}

function loadParsedCached() {
    if (!file_exists(DOM_PARSE_CACHE)) return [];
    $c = json_decode((string) file_get_contents(DOM_PARSE_CACHE), true);
    return is_array($c) && isset($c['rows']) ? $c['rows'] : [];
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
    <h2>Importador Dometic — <?php echo $dryRun ? 'DRY-RUN (sin cambios)' : 'EJECUCIÓN REAL'; ?></h2>
    <p><a href="<?php echo tep_href_link('import-dometic-altas.php'); ?>" class="xbutton small hv9">← Volver</a></p>
    <div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n";
@flush();

logMsg("Modo: " . ($dryRun ? "dry-run" : "EXECUTE")
    . " | departamento=" . ($selectedDept === 'all' ? 'TODOS' : $selectedDept)
    . ($codesParam !== '' ? " | codes=$codesParam" : "")
    . ($skipTranslation ? " | sin LLM" : "")
    . ($skipSpecs ? " | sin ficha técnica" : "")
    . ($allowNoImage ? " | importa stubs sin ficha" : "")
    . ($webRefresh ? " | web-refresh" : "")
    . ($max > 0 ? " | max=$max" : ""));

$xerr = '';
$rows = domParseXlsx($forceRefresh, $xerr);
if ($xerr) { logMsg("ERROR xlsx: $xerr"); goto end_action; }
logMsg("xlsx: " . count($rows) . " productos (" . basename(domFindXlsx()) . ")");

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_error) { logMsg("ERROR DB: " . $mysqli->connect_error); goto end_action; }
$mysqli->set_charset('utf8');
if (!is_dir(IMG_ABS_DIR)) @mkdir(IMG_ABS_DIR, 0775, true);

$createdLog = []; $catCache = [];
$mfgId = ensureManufacturer($mysqli, MFG_NAME, $dryRun, $createdLog);
$brandMfg = ['Dometic' => $mfgId];   // caché lazy de fabricante por marca (NDS/Büttner/Green Power/Dyno)
$rootCatId = getOrCreateCategory($mysqli, PARENT_CATEGORY_NAME_ES, PARENT_CATEGORY_NAME_EN, 0, 0, $dryRun, $catCache, $createdLog);

logMsg("Cargando referencias existentes en BD (model/ref/EAN globales)…");
$existing = buildExistingMap($mysqli);
logMsg("  → " . count($existing) . " referencias en BD");

$wantCodes = [];
if ($codesParam !== '') foreach (preg_split('/[\s,;]+/', $codesParam) as $c) { $c = trim($c); if ($c !== '') $wantCodes[$c] = true; }

$nInserted = $skipExist = $skipNoPrice = $skipNoWeb = $skipNoImg = $skipDept = $errors = $formatFail = 0;
$ts0 = microtime(true);

foreach ($rows as $row) {
    if ($max > 0 && $nInserted >= $max) { logMsg("Alcanzado max=$max, parando."); break; }
    $sku = $row['sku'];

    if ($codesParam !== '' && !isset($wantCodes[$sku])) continue;

    $dept = $row['path'][0] ?? '';
    if ($codesParam === '' && $selectedDept !== 'all' && mb_strtolower($dept, 'UTF-8') !== mb_strtolower($selectedDept, 'UTF-8')) { $skipDept++; continue; }

    // precio: col C (PVP sin IVA). Sin precio → no valorable.
    if ($row['pvp'] === null || $row['pvp'] <= 0) { $skipNoPrice++; continue; }

    // dedup global
    $ean = $row['ean'];
    if (isset($existing[strtolower($sku)]) || ($ean !== '' && isset($existing[strtolower($ean)]))) { $skipExist++; continue; }

    // marca real (col B): NDS / Büttner / Green Power / Dyno / Dometic
    $brand = domDetectBrand($row['desc']);
    if (!isset($brandMfg[$brand])) $brandMfg[$brand] = ensureManufacturer($mysqli, $brand, $dryRun, $createdLog);
    $useMfg = $brandMfg[$brand];

    // enriquecimiento web (dometic.com). Sin ficha/imagen → se salta, salvo allow_no_image (stub del xlsx).
    $note = '';
    $web = domEnrich($sku, $webRefresh, $skipSpecs, $note);
    $imageUrls = $web ? array_values(array_filter((array) ($web['images'] ?? []))) : [];
    $stub = false;
    if (empty($imageUrls)) {
        if (!$allowNoImage) { $skipNoWeb++; if ($skipNoWeb <= 30) logMsg("SKIP $sku [$brand] '" . mb_substr($row['desc'],0,34,'UTF-8') . "': sin ficha web ($note)"); continue; }
        $stub = true; if (!is_array($web)) $web = [];
    }

    $price = roundToNickel($row['pvp']);
    $cost  = round((float) $row['cost'], 4);
    $g1    = roundToNickel(calcG1Price($price, $cost));

    $name  = buildName($web['name'] ?? '', $row['desc'], $brand);
    $descEsBase = trim((string) ($web['descEs'] ?? ''));
    if ($descEsBase === '') $descEsBase = trim((string) $row['desc']);   // fallback (stub): descripción del xlsx
    $specsText  = $skipSpecs ? '' : specsToText($web['specs'] ?? []);

    // descripciones
    if ($skipTranslation || $dryRun) {
        $plainEs = trim($descEsBase . ($specsText !== '' ? "\n\n" . $specsText : ''));
        $descEs = $plainEs !== '' ? '<p>' . nl2br(htmlspecialchars($plainEs)) . '</p>' : '';
        $descEn = $descEs;
    } else {
        // ES: desc (ya en español) + specs con etiquetas traducidas → maquetar
        $specsEs = $specsText;
        if ($specsText !== '') {
            $tr = llmCall(LLM_TRANSLATE_LABELS_ES, $specsText, 1500);
            if (trim($tr) !== '') $specsEs = $tr;
        }
        $combEs = trim($descEsBase . ($specsEs !== '' ? "\n\n" . $specsEs : ''));
        $inLenEs = mb_strlen($combEs, 'UTF-8');
        $fEs = llmCall(LLM_FORMAT_PROMPT_ES, $combEs, 2500);
        $descEs = domFormatLooksValid($fEs, $inLenEs) ? $fEs : ($combEs !== '' ? '<p>' . nl2br(htmlspecialchars($combEs)) . '</p>' : '');
        if (!domFormatLooksValid($fEs, $inLenEs)) $formatFail++;

        // EN: traducir desc ES→EN + specs (etiquetas inglesas originales) → maquetar
        $descEnBase = $descEsBase !== '' ? llmCall(LLM_TRANSLATE_EN, $descEsBase, 2000) : '';
        if (trim($descEnBase) === '') $descEnBase = $descEsBase;
        $combEn = trim($descEnBase . ($specsText !== '' ? "\n\n" . $specsText : ''));
        $inLenEn = mb_strlen($combEn, 'UTF-8');
        $fEn = llmCall(LLM_FORMAT_PROMPT_EN, $combEn, 2500);
        $descEn = domFormatLooksValid($fEn, $inLenEn) ? $fEn : ($combEn !== '' ? '<p>' . nl2br(htmlspecialchars($combEn)) . '</p>' : '');
        if (!domFormatLooksValid($fEn, $inLenEn)) $formatFail++;
    }
    $descEs = mbReformatDescription($descEs);
    $descEn = mbReformatDescription($descEn);

    if ($dryRun) {
        $nInserted++;
        if ($nInserted <= 25) {
            logMsg(sprintf("  WOULD INSERT %s [%s] '%s' [%s] price=%.2f cost=%.2f g1=%.2f imgs=%d specs=%d ean=%s%s",
                $sku, $brand, mb_substr($name,0,38,'UTF-8'), implode(' > ', $row['path']) ?: 'raíz',
                $price, $cost, $g1, count($imageUrls), count($web['specs'] ?? []), $ean !== '' ? $ean : '(interno)', $stub ? ' STUB' : ''));
        }
        continue;
    }

    $tmpFiles = $stub ? [] : downloadImagesToTmp($imageUrls, MAX_SUBIMAGES + 1);
    if (!$stub && empty($tmpFiles)) { $skipNoImg++; logMsg("SKIP $sku '" . mb_substr($name,0,34,'UTF-8') . "': imágenes no descargables"); continue; }

    $targetCat = ensureCategoryPath($mysqli, $row['path'], $rootCatId, false, $catCache, $createdLog);

    $mysqli->begin_transaction();
    try {
        $qmodel = $mysqli->real_escape_string($sku);
        $pricef = number_format($price, 4, '.', '');
        $costf  = number_format($cost, 4, '.', '');
        $weight = number_format(DEFAULT_WEIGHT, 3, '.', '');
        $qEan   = $mysqli->real_escape_string($ean);
        $sql = "INSERT INTO products (products_quantity, check_stock, products_model, products_image, products_price, products_cost, products_date_added, products_weight, products_status, products_tax_class_id, manufacturers_id, product_ean, reference_prov, products_import_origin)
            VALUES (0, 0, \"$qmodel\", \"\", $pricef, $costf, NOW(), $weight, 2, " . TAX_CLASS_IVA21 . ", " . (int)$useMfg . ", \"$qEan\", \"$qmodel\", \"" . ORIGIN_FLAG . "\")";
        if (!$mysqli->query($sql)) throw new Exception("products: " . $mysqli->error);
        $pid = (int) $mysqli->insert_id;

        $qNameEs = $mysqli->real_escape_string($name);
        $qDescEs = $mysqli->real_escape_string($descEs);
        $qNameEn = $mysqli->real_escape_string($name);
        $qDescEn = $mysqli->real_escape_string($descEn);
        if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_ES . ", \"$qNameEs\", \"$qDescEs\", 0)")) throw new Exception("desc ES: " . $mysqli->error);
        if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_EN . ", \"$qNameEn\", \"$qDescEn\", 0)")) throw new Exception("desc EN: " . $mysqli->error);

        if (!$mysqli->query("INSERT INTO products_to_categories (products_id, categories_id) VALUES ($pid, " . (int)$targetCat . ")")) throw new Exception("p2c: " . $mysqli->error);
        if (!$mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . G1_GROUP_ID . ", $pid, " . number_format($g1, 4, '.', '') . ", 1, 1)")) throw new Exception("g1: " . $mysqli->error);

        // imágenes (los stubs sin ficha web se insertan sin imagen, para revisión)
        $imgFinal = [];
        if (!$stub) {
            $slug = domSlugify($name);
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
        }

        $mysqli->commit();

        // sin EAN real → interno por pid
        if ($ean === '') {
            $genEan = generateInternalEan13($pid, EAN_INTERNAL_PREFIX);
            if ($genEan !== '') $mysqli->query("UPDATE products SET product_ean='" . $mysqli->real_escape_string($genEan) . "' WHERE products_id=$pid AND (product_ean IS NULL OR product_ean='')");
        }

        $nInserted++;
        logMsg(sprintf("OK pid=%d %s [%s] cat='%s' price=%.2f cost=%.2f g1=%.2f imgs=%d specs=%d name='%s'%s",
            $pid, $sku, $brand, implode(' > ', $row['path']) ?: 'raíz', $price, $cost, $g1,
            $stub ? 0 : (1 + count($imgFinal)), count($web['specs'] ?? []), mb_substr($name, 0, 40, 'UTF-8'), $stub ? ' STUB' : ''));
    } catch (Exception $e) {
        $mysqli->rollback();
        $errors++;
        foreach ($tmpFiles as $t) if (file_exists($t)) @unlink($t);
        logMsg("ERROR $sku: " . $e->getMessage());
    }
}

$elapsed = microtime(true) - $ts0;
logMsg("==================== RESUMEN ====================");
logMsg(sprintf("Insertados: %d en %.1fs", $nInserted, $elapsed));
logMsg("Skip ya en BD: $skipExist | sin ficha web: $skipNoWeb | sin imagen: $skipNoImg | sin precio: $skipNoPrice | otro depto: $skipDept | maquetados fallidos: $formatFail | errores: $errors");
if (!empty($createdLog)) {
    logMsg(($dryRun ? "Se crearían" : "Creadas") . " " . count($createdLog) . " entidades:");
    foreach ($createdLog as $c) logMsg("  · $c");
}

end_action:
?>
    </div>
    <p style="margin-top:15px;"><a href="<?php echo tep_href_link('import-dometic-altas.php'); ?>" class="xbutton small hv9">← Volver</a></p>
<?php else: ?>
    <h2>Importador Dometic (altas)</h2>
    <?php
        $xerr = '';
        $rows = $forceRefresh ? domParseXlsx(true, $xerr) : (loadParsedCached() ?: domParseXlsx(false, $xerr));
        if ($xerr) echo '<p style="color:red;">' . htmlspecialchars($xerr) . '</p>';
        // recuento por departamento (nivel 0 del path)
        $deptCount = [];
        foreach ($rows as $r) { $d = $r['path'][0] ?? '(sin sección)'; $deptCount[$d] = ($deptCount[$d] ?? 0) + 1; }
        ksort($deptCount);
    ?>
    <p style="color:#666;font-size:13px;">
        Fuente precio/coste/EAN: <code><?php echo htmlspecialchars(basename(domFindXlsx() ?: '')); ?></code>
        (A=SKU, B=desc, C=PVP sin IVA, D=dto, E=coste neto, F=EAN) — <strong><?php echo count($rows); ?></strong> productos.
        Enriquecimiento (descripción ES, imágenes, ficha técnica): <code>dometic.com/es-es</code> por SKU. Cobertura web ~60%.
    </p>
    <p style="background:#fffbe6;border:1px solid #ffd700;padding:10px;border-radius:4px;font-size:13px;">
        <strong>Reglas siempre aplicadas</strong>:<br>
        • <strong>Fabricante por marca real</strong> (detectada en la descripción del xlsx): Dometic (defecto) y las marcas del grupo <code>NDS</code>, <code>Büttner</code> (incl. Tempra), <code>Green Power</code>, <code>Dyno</code> — se crean si no existen. <strong>SIN atributos/variantes</strong>: cada SKU = producto suelto.<br>
        • <code>products_price</code> = roundToNickel(col C — PVP sin IVA) &nbsp;|&nbsp;
          <code>products_cost</code> = col E (neto) &nbsp;|&nbsp;
          <code>G1</code> = roundToNickel(tiers de margen + piso cost×<?php echo G1_FLOOR_FACTOR; ?>).<br>
        • Categorías: <strong>Dometic Nuevos</strong> (status 0, oculta) &gt; Departamento &gt; [Familia] &gt; Serie (jerarquía del xlsx).<br>
        • Descripción ES de la web (JSON-LD) + ficha técnica; EN traducido vía LLM (glosario RV/náutico).<br>
        • EAN real del xlsx; sin EAN válido → interno prefijo <?php echo EAN_INTERNAL_PREFIX; ?> (nunca <code>299…</code>).<br>
        • <strong>Sin ficha en dometic.com → NO se importa</strong> (sin imagen), salvo que marques "importar stubs". Imágenes: alta-res por SKU + galería (hasta <?php echo MAX_SUBIMAGES; ?> sub). Nota: las marcas propias (NDS, etc.) tienen poca cobertura en dometic.com y su web (ndsenergy.it) no es casable por SKU.<br>
        • <code>products_status=2</code> (revisión), <code>check_stock=0</code>, stock NO se toca.<br>
        • Dedup: <code>products_model</code> / <code>reference_prov</code> / EAN <strong>globales</strong> (Dometic ya tiene catálogo legacy sin origin).
    </p>
    <form method="get" style="background:#f5f5f5;padding:15px;border-radius:5px;">
        <p><strong>Departamento a importar</strong>:
            <select name="dept" style="min-width:340px;">
                <option value="all">— Todos (<?php echo array_sum($deptCount); ?>) —</option>
                <?php foreach ($deptCount as $d => $n) {
                    $sel = (mb_strtolower($selectedDept,'UTF-8') === mb_strtolower($d,'UTF-8')) ? ' selected' : '';
                    echo '<option value="' . htmlspecialchars($d) . '"' . $sel . '>' . htmlspecialchars($d) . ' (' . $n . ')</option>';
                } ?>
            </select>
        </p>
        <p><strong>SKUs concretos</strong> (opcional, coma/espacio; ignora el filtro de departamento):<br>
            <textarea name="codes" rows="2" style="width:100%;" placeholder="9600051162, 9600051231"><?php echo htmlspecialchars($codesParam); ?></textarea>
        </p>
        <p><label><input type="checkbox" name="dry_run" value="1" checked> Dry-run (sin cambios)</label></p>
        <p><label><input type="checkbox" name="skip_translation" value="1"> Saltar LLM (descripción queda en español crudo + specs sin maquetar, mucho más rápido)</label></p>
        <p><label><input type="checkbox" name="skip_specs" value="1"> Sin ficha técnica (solo descripción)</label></p>
        <p><label><input type="checkbox" name="allow_no_image" value="1"> Importar stubs sin ficha web (usa nombre/desc del xlsx, sin imagen — útil para NDS/Büttner/Green Power/Dyno que casi no están en dometic.com; quedan en status 2 para enriquecer)</label></p>
        <p><label><input type="checkbox" name="refresh" value="1"> Re-parsear xlsx (tras actualizar el fichero de precios)</label></p>
        <p><label><input type="checkbox" name="web_refresh" value="1"> Ignorar cache web (re-descarga fichas de dometic.com)</label></p>
        <p>Inserts máximos por ejecución (0 = sin límite): <input type="number" name="max" value="3" min="0" style="width:80px;"></p>
        <button type="submit" name="action" value="dry_run" class="xbutton small hv9">Dry-run</button>
        <button type="submit" name="action" value="execute" class="xbutton small hv9 verde" onclick="return confirm('¿Ejecutar de verdad? Insertará productos en la BD.');">Ejecutar</button>
    </form>
    <p style="margin-top:20px;color:#888;font-size:12px;">
        <strong>Detalles</strong>: el enriquecimiento web busca cada SKU en dometic.com (Algolia) y baja su ficha; ~60% tienen página.
        Las fichas se cachean (gzip) en <code>cache/web/</code>; marca "Ignorar cache web" para re-descargar.
        El LLM (qwen) traduce a EN y maqueta ES/EN; ~3-4 llamadas por producto (lento — usa "Saltar LLM" para una pasada rápida).
    </p>
<?php endif; ?>
</div>
</td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
