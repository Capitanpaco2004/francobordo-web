<?php
require 'includes/application_top.php';
require_once DIR_FS_DOCUMENT_ROOT . 'includes/vendor/autoload.php';

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);

const VETUS_DIR        = '/home/francobordo/public_html/import/Vetus/';
const VETUS_CACHE_DIR  = '/home/francobordo/public_html/import/Vetus/cache/';
const WEB_BASE         = 'https://webshop.vetus.com';
// Credenciales B2B: con sesión, la ficha muestra RRP + % descuento + precio neto dealer (=coste).
const VETUS_WEB_USER   = 'f.rodriguez@francobordo.com';
const VETUS_WEB_PASS   = 'Vetus0908';
const VETUS_LOGIN_URL  = 'https://webshop.vetus.com/en/account/login';
define('IMG_ABS_DIR',     dirname(dirname(__FILE__)) . '/images/productos/');

const PARENT_CATEGORY_NAME_ES  = 'Vetus Nuevos';
const PARENT_CATEGORY_NAME_EN  = 'Vetus New';
const PARENT_CATEGORY_NAME_KEY = 'Vetus Nuevos';
const FALLBACK_SUBCAT          = 'VARIOS';

const TAX_CLASS_IVA21   = 1;
const LANG_ID_ES        = 3;
const LANG_ID_EN        = 1;
const G1_GROUP_ID       = 1;
const G1_MULT           = 0.80;          // Profesionales = 80% del PVP neto (sin tiers, sin piso)
const PRODUCT_NAME_MAX  = 80;
const IMG_HTTP_TIMEOUT  = 15;
const WEB_HTTP_TIMEOUT  = 25;
const IMG_MIN_BYTES     = 2048;          // < 2 KB = placeholder / error
const MAX_SUBIMAGES     = 6;
const ORIGIN_FLAG       = 'vetus';
const MANUFACTURER_NAME = 'Vetus';
const EAN_INTERNAL_PREFIX = 28;          // siguiente tras Yachticon=27
const VARIANT_OPTION_ID = 3;             // "Modelo" (mismo que el resto de importadores)
const LCP_VARIANT_RATIO = 0.30;
const LCP_VARIANT_HIGH  = 0.50;
const PRICE_DISPERSION_MAX = 10.0;
const STATUS_PUBLISH    = 'RECOMENDAMOS PUBLICAR';
const VETUS_VAT_RATE    = 0.21;

// Categorías top del breadcrumb del webshop EN → nombre ES de la subcategoría.
// Las no listadas conservan su nombre EN (el usuario las renombra al revisar).
const SUBCAT_MAP = [
    'ENGINES'                         => 'Motores',
    'ENGINES AND AROUND THE ENGINE'   => 'Motores y periféricos',
    'AROUND THE ENGINE'               => 'Periféricos del motor',
    'ELECTRIC PROPULSION'             => 'Propulsión eléctrica',
    'STERN GEAR SYSTEMS'              => 'Línea de ejes',
    'STERN GEAR'                      => 'Línea de ejes',
    'EXHAUST SYSTEMS'                 => 'Sistemas de escape',
    'BOAT INSTRUMENTS'                => 'Instrumentos',
    'FUEL SYSTEMS'                    => 'Sistemas de combustible',
    'FRESH WATER SYSTEMS'             => 'Agua dulce',
    'FRESH WATER'                     => 'Agua dulce',
    'WASTE WATER SYSTEMS'             => 'Aguas residuales',
    'WASTE WATER'                     => 'Aguas residuales',
    'THRUSTER SYSTEMS'                => 'Hélices de maniobra',
    'POWER HYDRAULICS'                => 'Hidráulica',
    'HYDRAULICS'                      => 'Hidráulica',
    'ELECTRICITY ON BOARD'            => 'Electricidad a bordo',
    'BOAT WINDOWS'                    => 'Ventanas y portillos',
    'STEERING SYSTEMS'               => 'Sistemas de gobierno',
    'VENTILATION'                     => 'Ventilación',
    'DECK EQUIPMENT'                  => 'Equipo de cubierta',
    'ANCHORING AND MOORING'           => 'Fondeo y amarre',
    'SPARE PARTS'                     => 'Recambios',
    'OUTLET'                          => 'Outlet',
];

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
// Por defecto NO se agrupan variantes (cada SKU = producto suelto, como la web de Vetus).
// La agrupación por LCP fusionaba modelos distintos (voltaje/TDC/serie) → desactivada salvo opt-in.
$groupVariants   = isset($_POST['group_variants']) || isset($_GET['group_variants']);
$skipVariants    = !$groupVariants;
$skipWebEnrich   = isset($_POST['skip_web_enrich'])  || isset($_GET['skip_web_enrich']);
$onlyCodesRaw    = trim((string) ($_POST['codes'] ?? $_GET['codes'] ?? ''));
$onlyCodes       = [];
if ($onlyCodesRaw !== '') {
    foreach (preg_split('/[\s,;]+/', $onlyCodesRaw) as $c) {
        $c = trim($c);
        if ($c !== '') $onlyCodes[strtoupper($c)] = true;
    }
}

function logMsg($msg) {
    $line = '[' . date('H:i:s') . '] ' . $msg . "\n";
    echo '<pre style="margin:0;padding:2px 8px;border-bottom:1px solid #eee;white-space:pre-wrap;overflow-wrap:break-word;word-break:break-word;font-family:monospace;font-size:12px;">' . htmlspecialchars($line) . '</pre>';
    @flush();
}

function vetusParseNum($v) {
    $v = trim((string) $v);
    if ($v === '' || strtoupper($v) === 'N/A') return null;
    $v = str_replace(',', '.', $v);
    if (!is_numeric($v)) return null;
    return (float) $v;
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
        $l = trim(preg_replace('/[ \t\x{A0}]+/u', ' ', $l));
        if ($l === '') {
            if ($emptyStreak < 1 && !empty($out)) { $out[] = ''; }
            $emptyStreak++;
            continue;
        }
        $out[] = $l;
        $emptyStreak = 0;
    }
    return trim(implode("\n", $out));
}

function vetusSlugify($text, $maxLen = 50) {
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

/** Construye el nombre final "Vetus {base} {model}" garantizando que el modelo
 *  queda al final dentro de varchar(80). Idempotente respecto al prefijo "Vetus". */
function vetusBuildName($base, $model) {
    $base = trim(preg_replace('/\s+/u', ' ', (string) $base));
    $model = trim((string) $model);
    // Quita un "Vetus" inicial del base para no duplicar
    $base = preg_replace('/^vetus\s+/i', '', $base);
    $prefix = 'Vetus ';
    $suffix = $model !== '' ? ' ' . $model : '';
    $room = PRODUCT_NAME_MAX - mb_strlen($prefix, 'UTF-8') - mb_strlen($suffix, 'UTF-8');
    if ($room < 1) {
        // Modelo larguísimo: deja al menos el prefijo + modelo recortado
        return mb_substr($prefix . $base . $suffix, 0, PRODUCT_NAME_MAX, 'UTF-8');
    }
    if (mb_strlen($base, 'UTF-8') > $room) {
        $base = rtrim(mb_substr($base, 0, $room, 'UTF-8'));
    }
    return $prefix . $base . $suffix;
}

/** Resuelve (o crea) el manufacturer "Vetus". */
function resolveVetusManufacturer($mysqli, $dryRun, &$createdLog) {
    $q = $mysqli->real_escape_string(MANUFACTURER_NAME);
    $r = $mysqli->query("SELECT manufacturers_id FROM manufacturers WHERE UPPER(TRIM(manufacturers_name))=UPPER('$q') LIMIT 1");
    if ($r && $row = $r->fetch_assoc()) return (int) $row['manufacturers_id'];
    if ($dryRun) { $createdLog[] = "manufacturer '" . MANUFACTURER_NAME . "' (dry-run, no creado)"; return 0; }
    $mysqli->query("INSERT INTO manufacturers (manufacturers_name, date_added) VALUES ('$q', NOW())");
    $id = (int) $mysqli->insert_id;
    $mysqli->query("INSERT INTO manufacturers_info (manufacturers_id, languages_id, manufacturers_url) VALUES ($id, " . LANG_ID_ES . ", '')");
    $mysqli->query("INSERT INTO manufacturers_info (manufacturers_id, languages_id, manufacturers_url) VALUES ($id, " . LANG_ID_EN . ", '')");
    $createdLog[] = "manufacturer '" . MANUFACTURER_NAME . "' (id=$id)";
    return $id;
}

/** Mapa de duplicados — modelo/EAN ya en BD. Modelo filtrado por origin/manufacturer Vetus;
 *  EAN GLOBAL (GS1). */
function buildExistingMap($mysqli, $vetusMfgId) {
    $existing = [];
    $f = "(p.products_import_origin LIKE 'vetus%'" . ($vetusMfgId > 0 ? " OR p.manufacturers_id=" . (int)$vetusMfgId : "") . ")";
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

/** Ensure padre "Vetus Nuevos" (parent=0, status=0). Reusa existente por nombre. */
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
    if ($dryRun) { $cache[$key] = 0; $createdLog[] = "subcategoría '$nm' bajo $parentId (dry-run, no creada)"; return 0; }
    $r = $mysqli->query("SELECT IFNULL(MAX(sort_order), 0) + 1 AS nso FROM categories WHERE parent_id=$parentId");
    $nso = (int) ($r->fetch_assoc()['nso'] ?? 1);
    $mysqli->query("INSERT INTO categories (parent_id, sort_order, date_added, last_modified, categories_status) VALUES ($parentId, $nso, NOW(), NOW(), 0)");  // subcat oculta (status=0): staging para revisar
    $newId = (int) $mysqli->insert_id;
    $mysqli->query("INSERT INTO categories_description (categories_id, language_id, categories_name) VALUES ($newId, " . LANG_ID_ES . ", '$qName')");
    $mysqli->query("INSERT INTO categories_description (categories_id, language_id, categories_name) VALUES ($newId, " . LANG_ID_EN . ", '$qName')");
    $cache[$key] = $newId;
    $createdLog[] = "subcategoría '$nm' (id=$newId) bajo $parentId";
    return $newId;
}

/** Mapea la categoría top del breadcrumb EN al nombre ES de la subcategoría. */
function vetusSubcatName($enCategory) {
    $en = trim(preg_replace('/\s+/u', ' ', (string) $enCategory));
    if ($en === '') return FALLBACK_SUBCAT;
    $key = mb_strtoupper($en, 'UTF-8');
    return SUBCAT_MAP[$key] ?? $en;
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

function vetusFormatLooksValid($html, $minLenInput) {
    if (!is_string($html) || trim($html) === '') return false;
    $low = strtolower($html);
    $hasStruct = (strpos($low, '<p>') !== false) || (strpos($low, '<ul>') !== false) || (strpos($low, '<h3>') !== false);
    if (!$hasStruct) return false;
    $plainOut = strip_tags($html);
    $plainOutLen = mb_strlen(trim($plainOut), 'UTF-8');
    if ($minLenInput > 200 && $plainOutLen < $minLenInput * 0.4) return false;
    return true;
}

function downloadImage($url, $destAbs) {
    if (empty($url)) return false;
    $url = trim($url);
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
            'Referer: https://webshop.vetus.com/',
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
        $tmpAbs = IMG_ABS_DIR . 'vetus-tmp-' . uniqid('', true) . '.jpg';
        if (downloadImage($url, $tmpAbs)) $tmpFiles[] = $tmpAbs;
    }
    return $tmpFiles;
}

/** Lista única de URLs candidatas: col Q del xlsx (jpeg de platform.vetus.com) + og:image de la web. */
function vetusImageUrls($rowImg, $webImg) {
    $out = [];
    $rowImg = trim((string) $rowImg);
    if ($rowImg !== '' && strtoupper($rowImg) !== 'N/A' && preg_match('#^https?://#i', $rowImg)) $out[] = $rowImg;
    if ($webImg !== '') $out[] = trim($webImg);
    $seen = []; $unique = [];
    foreach ($out as $u) { if (!isset($seen[$u])) { $seen[$u] = true; $unique[] = $u; } }
    return $unique;
}

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
function generateInternalEan13($productId, $providerPrefix) {
    $pp = (int) $providerPrefix;
    if ($pp < 20 || $pp > 28) return '';
    if ($productId <= 0 || $productId > 9999999999) return '';
    $payload = str_pad((string) $pp, 2, '0', STR_PAD_LEFT) . str_pad((string) $productId, 10, '0', STR_PAD_LEFT);
    $check = ean13Checksum($payload);
    return $check < 0 ? '' : ($payload . $check);
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

function vetusNormalizeMeasure($s) {
    $s = trim((string) $s);
    if ($s === '') return '';
    $s = preg_replace_callback('/(\d+(?:[.,]\d+)?)\s*(mm|cm|m|kg|g|lt|l|ml|in|inch|"|hp|w|v|a|ah|hz|°|oz)\b/iu', function($m){
        return $m[1] . ' ' . strtolower($m[2]);
    }, $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
}
function vetusExtractMeasure($title) {
    $title = (string) $title;
    if (preg_match('/\b(SZ|Size|Talla)\s*(\d+)\b/iu', $title, $m)) return 'SZ ' . $m[2];
    if (preg_match('/(?:Ø|diameter|diam\.?)\s*(\d+(?:[.,]\d+)?)\s*(mm|cm)?/iu', $title, $m)) {
        return 'Ø ' . $m[1] . ' ' . (isset($m[2]) && $m[2] !== '' ? strtolower($m[2]) : 'mm');
    }
    if (preg_match('/(M?\d+(?:[.,]\d+)?)\s*[Xx]\s*(\d+(?:[.,]\d+)?)\b/u', $title, $m)) return $m[1] . 'x' . $m[2];
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*(Ah|AMP|kg|g|mm|cm|mt|m|lt|l|ml|in|inch|"|HP|W|V|A|Hz|°)\b/iu', $title, $m)) {
        return $m[1] . ' ' . strtolower($m[2]);
    }
    return '';
}

/** Calcula la etiqueta EN de cada variante: medida del nombre → resto tras LCP → SKU. */
function vetusComputeVariantLabels(array $items) {
    $titlesAll = array_map(fn($it) => $it['_NAME_EN'], $items);
    $common = rtrim(longestCommonPrefix($titlesAll), " -–·,");
    $lcpStrip = function ($name, $common) {
        if ($common === '' || mb_strpos($name, $common) !== 0) return '';
        $rest = ltrim(trim(mb_substr($name, mb_strlen($common, 'UTF-8'), null, 'UTF-8')), " -–·,;");
        return (mb_strlen($rest, 'UTF-8') <= 64) ? $rest : '';
    };
    $sigs = [];
    foreach ($items as $code => $it) {
        $sigs[$code] = [
            'measure' => vetusNormalizeMeasure(vetusExtractMeasure($it['_NAME_EN'])),
            'lcp'     => vetusNormalizeMeasure($lcpStrip($it['_NAME_EN'], $common)),
        ];
    }
    $labels = [];
    foreach (['measure', 'lcp'] as $signal) {
        $vals = array_map(fn($c) => $c[$signal], $sigs);
        $nonEmpty = array_filter($vals, fn($v) => $v !== '');
        if (count($nonEmpty) === count($vals) && count(array_unique($nonEmpty)) === count($vals)) { $labels = $vals; break; }
    }
    foreach ($items as $code => $it) {
        if (empty($labels[$code])) $labels[$code] = $code;
        $labels[$code] = mb_substr($labels[$code], 0, 64, 'UTF-8');
    }
    return $labels;
}

/** Traduce una etiqueta de variante EN→ES (cacheada). Medidas puras / códigos no se traducen. */
function vetusTranslateLabel($labelEn, &$cache, $skipTranslation) {
    if ($skipTranslation || $labelEn === '') return $labelEn;
    if (!preg_match('/[A-Za-z]{3,}/', $labelEn)) return $labelEn;   // "16 mm", "SZ 1", códigos → tal cual
    if (isset($cache[$labelEn])) return $cache[$labelEn];
    // Encuadre: es el nombre de una VARIANTE de un accesorio náutico (talla/color/material/montaje),
    // NO jerga de naipes ni de otros dominios (evita "straight flush" → "escala de color").
    $userText = "Traduce al español de España el nombre de esta variante de un producto náutico/marino "
        . "(describe talla, material, color o tipo de montaje; \"flush\"=empotrado, \"straight\"=recto, \"s.s.\"=inox):\n" . $labelEn;
    $t = llmCall(LLM_PROMPT_NAME, $userText, 80);
    $es = ($t !== '') ? mb_substr(trim($t), 0, 64, 'UTF-8') : $labelEn;
    $cache[$labelEn] = $es;
    return $es;
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
    $newId = (int) ($nq->fetch_assoc()['nid']);
    $nameEnSafe = $mysqli->real_escape_string($nameEn);
    $mysqli->query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($newId, " . LANG_ID_ES . ", '$nameEsSafe', '')");
    $mysqli->query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($newId, " . LANG_ID_EN . ", '$nameEnSafe', '')");
    $mysqli->query("INSERT INTO products_options_values_to_products_options (products_options_id, products_options_values_id) VALUES (" . VARIANT_OPTION_ID . ", $newId)");
    return $newId;
}

function vetusBrowserHeaders() {
    return [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.9',
        'Upgrade-Insecure-Requests: 1',
    ];
}

/** Parsea un número en formato europeo ("1.824,50" → 1824.50, "46" → 46). */
function vetusEuNum($s) {
    $s = trim((string) $s);
    if ($s === '') return 0.0;
    $s = str_replace('.', '', $s);   // separador de miles
    $s = str_replace(',', '.', $s);  // separador decimal
    $s = preg_replace('/[^0-9.\-]/', '', $s);
    return is_numeric($s) ? (float) $s : 0.0;
}

function vetusHttpGet($url, $cookie = '') {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => WEB_HTTP_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER => vetusBrowserHeaders(),
        CURLOPT_ENCODING => '',
        CURLOPT_SSL_VERIFYPEER => false,
    ];
    if ($cookie !== '') { $opts[CURLOPT_COOKIEFILE] = $cookie; $opts[CURLOPT_COOKIEJAR] = $cookie; }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);
    if ($body === false || $http !== 200) return null;
    return $body;
}

/** Inicia sesión B2B y devuelve la ruta del cookie file (o '' si falla). */
function vetusLogin() {
    if (!is_dir(VETUS_CACHE_DIR)) @mkdir(VETUS_CACHE_DIR, 0775, true);
    $cookie = VETUS_CACHE_DIR . 'session_cookie.txt';
    @unlink($cookie);
    // 1) GET login → _token + cookie de sesión
    $ch = curl_init(VETUS_LOGIN_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $cookie, CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_HTTPHEADER => vetusBrowserHeaders(), CURLOPT_ENCODING => '',
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => WEB_HTTP_TIMEOUT,
    ]);
    $html = curl_exec($ch); unset($ch);
    if (!is_string($html) || !preg_match('#name="_token"\s+value="([^"]+)"#', $html, $m)) return '';
    $token = $m[1];
    // 2) POST credenciales
    $ch = curl_init(VETUS_LOGIN_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $cookie, CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['_token' => $token, 'email' => VETUS_WEB_USER, 'password' => VETUS_WEB_PASS]),
        CURLOPT_HTTPHEADER => vetusBrowserHeaders(), CURLOPT_ENCODING => '',
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => WEB_HTTP_TIMEOUT,
    ]);
    curl_exec($ch); unset($ch);
    // 3) Verificar sesión en el dashboard
    $dash = vetusHttpGet(WEB_BASE . '/en/account/dashboard', $cookie);
    if ($dash !== null && stripos($dash, 'logout') !== false) return $cookie;
    return '';
}

/** Resuelve la URL canónica del producto buscando el SKU en el webshop. */
function vetusResolveUrl($sku, $cookie = '') {
    $body = vetusHttpGet(WEB_BASE . '/en/search?q=' . rawurlencode($sku), $cookie);
    if ($body === null) return '';
    if (preg_match_all('#href="(https://webshop\.vetus\.com/en/product/[^"]+)"#i', $body, $m)) {
        $slugSku = strtolower(preg_replace('/[^a-z0-9]/i', '', $sku));
        // Preferir el producto cuyo slug empieza por el SKU; si no, el primero.
        foreach ($m[1] as $u) {
            $slug = strtolower(preg_replace('/[^a-z0-9]/', '', basename($u)));
            if ($slugSku !== '' && strpos($slug, $slugSku) === 0) return $u;
        }
        return $m[1][0];
    }
    return '';
}

/** Descarga (con cache) la ficha del producto. Con cookie de sesión, la ficha incluye el precio.
 *  La cache se guarda solo si la página trae el bloque de precio (sesión válida) para no
 *  cachear versiones sin precio. */
function vetusFetchProductHtml($sku, $cookie = '', $force = false) {
    $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $sku);
    $cachePath = VETUS_CACHE_DIR . $safe . '.html';
    if (!$force && file_exists($cachePath) && filesize($cachePath) > 4000) {
        $h = @file_get_contents($cachePath);
        if ($h !== false && (strpos($h, 'product-item__sku') !== false || strpos($h, 'breadcrumbs') !== false)) {
            // Si pedimos con sesión pero la cache no tiene precio, re-descargar.
            if ($cookie === '' || strpos($h, 'product-item__price') !== false) return $h;
        }
    }
    if (!is_dir(VETUS_CACHE_DIR)) @mkdir(VETUS_CACHE_DIR, 0775, true);
    $url = vetusResolveUrl($sku, $cookie);
    if ($url === '') return false;
    $body = vetusHttpGet($url, $cookie);
    if ($body === null) return false;
    if (strpos($body, 'product-item__sku') === false && strpos($body, 'breadcrumbs') === false) return false;
    @file_put_contents($cachePath, $body);
    return $body;
}

/** Parsea la ficha. Devuelve verified=true sólo si el SKU exacto aparece en la página
 *  ("SKU: XXX") — para no atribuir descripción/specs/categoría de un producto vecino. */
function vetusParseProduct($html, $sku) {
    $out = ['verified' => false, 'desc' => '', 'specs' => [], 'subcat_en' => '', 'ogimg' => '',
            'has_price' => false, 'rrp' => 0.0, 'disc' => 0.0, 'cost' => 0.0];
    if (!is_string($html) || $html === '') return $out;

    // Verificación del SKU
    $skuUp = strtoupper(trim($sku));
    if (preg_match('#SKU:\s*([^<]+)<#i', $html, $m)) {
        if (strtoupper(trim($m[1])) === $skuUp) $out['verified'] = true;
    }
    if (!$out['verified']) {
        // Acepta también si el SKU exacto aparece como token en la página
        if (preg_match('/(?<![A-Za-z0-9])' . preg_quote($skuUp, '/') . '(?![A-Za-z0-9])/i', $html)) $out['verified'] = true;
    }
    if (!$out['verified']) return $out;

    // Descripción larga
    if (preg_match('#product-item__desc[^>]*>(.*?)</div>#is', $html, $m)) {
        $out['desc'] = cleanHtmlAggressive($m[1]);
    }

    // Specs (sólo filas métricas; salta valores vacíos o "0")
    if (preg_match_all('#<tr class="unit-metric">\s*<td[^>]*>(.*?)</td>\s*<td[^>]*>(.*?)</td>#is', $html, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $row) {
            $label = trim(html_entity_decode(strip_tags($row[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $value = trim(html_entity_decode(strip_tags($row[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($label === '' || $value === '' || $value === '0') continue;
            $out['specs'][$label] = $value;
        }
    }

    // Breadcrumb → categoría top (primer item tras "Products")
    if (preg_match('#id="breadcrumbs".*?</nav>#is', $html, $bm)) {
        if (preg_match_all('#breadcrumbs__link[^>]*>([^<]+)<#i', $bm[0], $bb)) {
            $chain = array_map(fn($x) => trim(html_entity_decode($x, ENT_QUOTES | ENT_HTML5, 'UTF-8')), $bb[1]);
            $chain = array_values(array_filter($chain, fn($x) => $x !== '' && strcasecmp($x, 'Home') !== 0 && strcasecmp($x, 'Products') !== 0));
            if (!empty($chain)) $out['subcat_en'] = $chain[0];
        }
    }

    // og:image (fallback de imagen)
    if (preg_match('#<meta\s+property="og:image"\s+content="([^"]+)"#i', $html, $m)) {
        $out['ogimg'] = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // Precio (solo visible con sesión B2B): tabla "Retail price" + "Discount percentage".
    // coste = RRP × (1 − dto%). Verificado: 220,39 × (1−0,46) = 119,01.
    if (preg_match('#Retail price.*?<span>EUR</span>\s*<span>([^<]+)</span>.*?<td>\s*([0-9.,]+)\s*%#is', $html, $pm)) {
        $rrp  = vetusEuNum($pm[1]);
        $disc = vetusEuNum($pm[2]);
        if ($rrp > 0) {
            $out['has_price'] = true;
            $out['rrp']  = $rrp;
            $out['disc'] = $disc;
            $out['cost'] = round($rrp * (1 - $disc / 100), 4);
        }
    }
    return $out;
}

/** Precio final de un item: si la web trae precio → price=roundToNickel(RRP), cost=neto dealer.
 *  Si no → fallback price=roundToNickel(col P), cost=0. G1 siempre = roundToNickel(price × 0,80). */
function vetusComputePricing($priceData, $colPNum) {
    if (!empty($priceData['has_price']) && $priceData['rrp'] > 0) {
        $price = roundToNickel($priceData['rrp']);
        $cost  = round((float) $priceData['cost'], 4);
    } else {
        $price = roundToNickel($colPNum);
        $cost  = 0.0;
    }
    $g1 = roundToNickel($price * G1_MULT);
    return [$price, $cost, $g1];
}

/** Construye un bloque de texto plano de especificaciones a partir de specs web + datos xlsx. */
function vetusBuildSpecLines(array $webSpecs, $medidas, $weightKg) {
    $lines = [];
    foreach ($webSpecs as $label => $value) $lines[] = "$label: $value";
    $medidas = trim((string) $medidas);
    if ($medidas !== '' && strtoupper($medidas) !== 'N/A' && !isset($webSpecs['Dimensions']) && !isset($webSpecs['Dimensiones'])) {
        $lines[] = "Dimensions (mm): $medidas";
    }
    $w = vetusParseNum($weightKg);
    if ($w !== null && $w > 0 && !isset($webSpecs['Weight (kg)'])) {
        $lines[] = "Weight (kg): " . rtrim(rtrim(number_format($w, 3, '.', ''), '0'), '.');
    }
    return $lines;
}

function roundToNickel($net) {
    $withIva = ((float) $net) * (1 + VETUS_VAT_RATE);
    $roundedWithIva = round($withIva * 20) / 20;
    return round($roundedWithIva / (1 + VETUS_VAT_RATE), 4);
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

/** Lee el xlsx Vetus. Headers en fila 2, datos desde fila 3.
 *  A=No.(modelo) B/C=Desc EN D/E=Desc ES F=Lifecycle L=peso M=medidas N=EAN P=precio Q=imagen R=STATUS */
function loadXlsxRows($file) {
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file);
    $reader->setReadDataOnly(true);
    $ss = $reader->load($file);
    $sheet = $ss->getActiveSheet();
    $hi = $sheet->getHighestRow();
    $rows = [];
    for ($r = 3; $r <= $hi; $r++) {
        $rows[] = [
            'CODE'    => trim((string) $sheet->getCell('A' . $r)->getValue()),
            'DESC_EN' => trim((string) $sheet->getCell('B' . $r)->getValue()),
            'DESC_EN2'=> trim((string) $sheet->getCell('C' . $r)->getValue()),
            'DESC_ES' => trim((string) $sheet->getCell('D' . $r)->getValue()),
            'DESC_ES2'=> trim((string) $sheet->getCell('E' . $r)->getValue()),
            'WEIGHT'  => trim((string) $sheet->getCell('L' . $r)->getValue()),
            'MEDIDAS' => trim((string) $sheet->getCell('M' . $r)->getValue()),
            'EAN'     => trim((string) $sheet->getCell('N' . $r)->getValue()),
            'PRICE'   => trim((string) $sheet->getCell('P' . $r)->getValue()),
            'IMG'     => trim((string) $sheet->getCell('Q' . $r)->getValue()),
            'STATUS'  => trim((string) $sheet->getCell('R' . $r)->getValue()),
        ];
    }
    return $rows;
}

/** ¿Es familia legítima de variantes? (LCP del nombre EN). Adaptado de Lankhorst. */
function isLegitimateVetusFamily(array $items) {
    if (count($items) < 2) return false;
    $names = array_map(fn($r) => $r['_NAME_EN'], $items);
    if (count(array_unique($names)) < count($names)) return false;
    $lcp = longestCommonPrefix($names);
    $lcpLen = mb_strlen($lcp, 'UTF-8');
    $minLen = min(array_map(fn($s) => mb_strlen($s, 'UTF-8'), $names));
    if ($minLen <= 0) return false;
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
    $prices = array_map(fn($r) => max($r['_PRICE'], 0.01), $items);
    if (max($prices) / min($prices) > PRICE_DISPERSION_MAX) return false;
    if ($ratio >= LCP_VARIANT_HIGH) return true;
    foreach ($names as $name) {
        $suffix = mb_substr($name, $lcpLen, null, 'UTF-8');
        if (!preg_match('/\d/u', $suffix)) return false;
    }
    return true;
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
    <h2>Importador Vetus — <?php echo $dryRun ? 'DRY-RUN (sin cambios)' : 'EJECUCIÓN REAL'; ?></h2>
    <p><a href="<?php echo tep_href_link('import-vetus-altas.php'); ?>" class="xbutton small hv9">← Volver</a></p>
    <div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n";
@flush();

if (!empty($onlyCodes) && $max > 0) { $maxOverridden = $max; $max = 0; }

logMsg("Modo: " . ($dryRun ? "dry-run" : "EXECUTE")
    . (!empty($onlyCodes) ? " | codes=" . count($onlyCodes) : "")
    . ($skipTranslation ? " | sin traducción LLM" : "")
    . ($groupVariants   ? " | AGRUPANDO variantes (LCP)" : " | sin agrupar (cada SKU suelto)")
    . ($skipWebEnrich   ? " | sin scraping web"    : "")
    . (isset($maxOverridden) ? " | max=$maxOverridden IGNORADO (import por codes)" : ($max > 0 ? " | max=$max" : "")));

$xlsx = findNewestXlsx(VETUS_DIR);
if (!$xlsx) { logMsg("ERROR: no hay xlsx en " . VETUS_DIR); goto end_action; }
logMsg("xlsx: " . basename($xlsx) . " (" . round(filesize($xlsx)/1024) . " KB, mtime " . date('Y-m-d H:i', filemtime($xlsx)) . ")");

logMsg("Leyendo xlsx…");
$rows = loadXlsxRows($xlsx);
logMsg("Filas leídas: " . count($rows));

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_error) { logMsg("ERROR DB: " . $mysqli->connect_error); goto end_action; }
$mysqli->set_charset('utf8');

if (!$dryRun && !is_dir(IMG_ABS_DIR)) { @mkdir(IMG_ABS_DIR, 0775, true); }
if (!is_dir(VETUS_CACHE_DIR)) { @mkdir(VETUS_CACHE_DIR, 0775, true); }

// Login B2B en el webshop: la sesión hace visible el precio (RRP + dto% + neto dealer = coste).
$cookieFile = '';
if (!$skipWebEnrich) {
    $cookieFile = vetusLogin();
    logMsg($cookieFile !== '' ? "Login webshop Vetus: OK (precios/coste disponibles)" : "Login webshop Vetus: FALLÓ — sin coste; price=col P, cost=0");
}

$catCreatedLog = [];
$vetusMfgId = resolveVetusManufacturer($mysqli, $dryRun, $catCreatedLog);
logMsg("Manufacturer Vetus: id=" . ($vetusMfgId ?: '(se creará)'));
$parentCatId = ensureParentCategory($mysqli, $dryRun, $catCreatedLog);
logMsg("Categoría padre 'Vetus Nuevos': id=" . ($parentCatId ?: '(se creará)'));

logMsg("Cargando IDs existentes en BD…");
$existing = buildExistingMap($mysqli, $vetusMfgId);
logMsg("  → " . count($existing) . " referencias ya en BD");

$candidates = [];
$skipExist = $skipNoCode = $skipNoName = $skipBadPrice = $skipNotPublish = 0;
foreach ($rows as $row) {
    $code = $row['CODE'];
    if ($code === '') { $skipNoCode++; continue; }
    if (strcasecmp($row['STATUS'], STATUS_PUBLISH) !== 0) { $skipNotPublish++; continue; }
    $nameEn = trim($row['DESC_EN'] . ' ' . $row['DESC_EN2']);
    if ($nameEn === '') { $skipNoName++; continue; }
    if (isset($existing[strtolower($code)])) { $skipExist++; continue; }
    if ($row['EAN'] !== '' && isset($existing[strtolower($row['EAN'])])) { $skipExist++; continue; }

    $price = vetusParseNum($row['PRICE']);
    if ($price === null || $price <= 0) { $skipBadPrice++; continue; }
    $row['_NAME_EN'] = $nameEn;
    $row['_NAME_ES_RAW'] = trim($row['DESC_ES'] . ' ' . $row['DESC_ES2']);
    $row['_COLP']   = $price;                       // col P bruto (fallback de PVP)
    $row['_COST']   = 0.0;                          // se sobreescribe con neto dealer web
    $row['_PRICE']  = roundToNickel($price);        // se sobreescribe con RRP web
    $row['_G1']     = roundToNickel($price * G1_MULT);
    $w = vetusParseNum($row['WEIGHT']);
    $row['_WEIGHT'] = ($w !== null && $w > 0) ? round($w, 3) : 1.0;
    $candidates[$code] = $row;
}
logMsg("Candidatos tras pre-filtro: " . count($candidates));
logMsg("  pre-skip: existentes=$skipExist | sin code=$skipNoCode | sin nombre=$skipNoName | precio=$skipBadPrice | no-publicar=$skipNotPublish");

// ---- Agrupación de variantes por LCP del nombre EN ----
$families = [];
$standalone = [];
if ($skipVariants) {
    $standalone = $candidates;
} else {
    $byKey = $candidates;
    uasort($byKey, fn($a, $b) => strcmp($a['_NAME_EN'], $b['_NAME_EN']));
    $bucket = []; $allBuckets = [];
    foreach ($byKey as $code => $row) {
        if (empty($bucket)) { $bucket[$code] = $row; continue; }
        $newLcp = longestCommonPrefix(array_merge(array_map(fn($r)=>$r['_NAME_EN'], $bucket), [$row['_NAME_EN']]));
        $minNameLen = min(array_map(fn($r) => mb_strlen($r['_NAME_EN'], 'UTF-8'), array_merge($bucket, ['_'=>$row])));
        if ($minNameLen > 0 && mb_strlen($newLcp, 'UTF-8') / $minNameLen >= LCP_VARIANT_RATIO) {
            $bucket[$code] = $row;
        } else {
            $allBuckets[] = $bucket;
            $bucket = [$code => $row];
        }
    }
    if (!empty($bucket)) $allBuckets[] = $bucket;
    $famVar = $famSplit = 0;
    foreach ($allBuckets as $b) {
        if (count($b) === 1) { foreach ($b as $code => $r) $standalone[$code] = $r; continue; }
        if (isLegitimateVetusFamily($b)) { $families[array_key_first($b)] = $b; $famVar++; }
        else { foreach ($b as $code => $r) $standalone[$code] = $r; $famSplit++; }
    }
    logMsg("Agrupación: $famVar familias multi-variante | $famSplit grupos divididos en sueltos");
}

// Filtro por codes: conservar familias completas que contengan algún code pedido.
if (!empty($onlyCodes)) {
    $famF = [];
    foreach ($families as $k => $items) {
        foreach ($items as $c => $_) { if (isset($onlyCodes[strtoupper($c)])) { $famF[$k] = $items; break; } }
    }
    $stdF = [];
    foreach ($standalone as $c => $r) { if (isset($onlyCodes[strtoupper($c)])) $stdF[$c] = $r; }
    $families = $famF; $standalone = $stdF;
}
logMsg("Tras consolidar: " . count($families) . " familias + " . count($standalone) . " sueltos");

$subcatCache = [];
$labelTransCache = [];
$nInserted = $nFamiliesIns = $nStandaloneIns = 0;
$nWithVar = $nWithImg = $imgFail = $skippedNoImg = $translateFail = $formatFail = $errors = 0;
$webFails = $webUnverified = $nSubImgTotal = 0;

/** Enriquecimiento web por SKU: descripción + specs + subcat + og:image + precio (verificado). */
function processWebEnrich($sku, $skipWebEnrich, $cookie, &$webFails, &$webUnverified) {
    $empty = ['verified'=>false,'desc'=>'','specs'=>[],'subcat_en'=>'','ogimg'=>'','has_price'=>false,'rrp'=>0.0,'disc'=>0.0,'cost'=>0.0];
    if ($skipWebEnrich) return $empty;
    $html = vetusFetchProductHtml($sku, $cookie);
    if ($html === false) { $webFails++; return $empty; }
    $p = vetusParseProduct($html, $sku);
    if (!$p['verified']) $webUnverified++;
    return $p;
}

/** Genera (nameEn, descEn, nameEs, descEs) de un item, con web + xlsx + LLM. */
function buildContent($row, $web, $skipTranslation, $dryRun, &$translateFail, &$formatFail) {
    $code = $row['CODE'];
    $nameEn = vetusBuildName($row['_NAME_EN'], $code);
    // Base de descripción: web (si verificada) > desc EN xlsx
    $descBaseEn = ($web['verified'] && $web['desc'] !== '') ? $web['desc'] : $row['_NAME_EN'];
    // Bloque de specs (web + medidas/peso xlsx)
    $specLines = vetusBuildSpecLines($web['verified'] ? $web['specs'] : [], $row['MEDIDAS'], $row['WEIGHT']);
    $descEnFull = $descBaseEn;
    if (!empty($specLines)) $descEnFull .= "\n\nSpecifications:\n- " . implode("\n- ", $specLines);

    $nameEs = $nameEn;
    $descEn = nl2br(htmlspecialchars_decode($descEnFull), false);
    $descEs = $descEn;

    if ($dryRun) return [$nameEn, $descEn, $nameEs, $descEs];

    if (!$skipTranslation) {
        // Nombre ES: usa el ES del xlsx si existe, sino traduce el base EN
        $baseEs = $row['_NAME_ES_RAW'] !== '' ? $row['_NAME_ES_RAW'] : '';
        if ($baseEs === '') {
            $tn = llmCall(LLM_PROMPT_NAME, $row['_NAME_EN'], 200);
            if ($tn !== '') $baseEs = $tn; else $translateFail++;
        }
        if ($baseEs !== '') $nameEs = vetusBuildName($baseEs, $code);

        // Descripción ES: traduce el texto completo (desc + specs) del EN
        $td = llmCall(LLM_PROMPT_DESC, $descEnFull, 1500);
        $descEsFull = ($td !== '') ? $td : $descEnFull;
        if ($td === '') $translateFail++;

        // Maquetado HTML EN + ES
        $inLenEn = mb_strlen(strip_tags($descEnFull), 'UTF-8');
        $fmtEn = llmCall(LLM_FORMAT_PROMPT_EN, $descEnFull, 2500);
        $descEn = vetusFormatLooksValid($fmtEn, $inLenEn) ? $fmtEn : (nl2br(htmlspecialchars($descEnFull), false));
        if (!vetusFormatLooksValid($fmtEn, $inLenEn)) $formatFail++;

        $inLenEs = mb_strlen(strip_tags($descEsFull), 'UTF-8');
        $fmtEs = llmCall(LLM_FORMAT_PROMPT_ES, $descEsFull, 2500);
        $descEs = vetusFormatLooksValid($fmtEs, $inLenEs) ? $fmtEs : (nl2br(htmlspecialchars($descEsFull), false));
        if (!vetusFormatLooksValid($fmtEs, $inLenEs)) $formatFail++;
    } else {
        if ($row['_NAME_ES_RAW'] !== '') $nameEs = vetusBuildName($row['_NAME_ES_RAW'], $code);
    }
    return [$nameEn, $descEn, $nameEs, $descEs];
}

/** Inserta un producto (familia o suelto). $items: array code=>row; el más barato es el padre.
 *  $labelsEs/$labelsEn: etiquetas de variante precomputadas y traducidas (keyed por code). */
function insertProduct($mysqli, $items, $isFamily, $web, $vetusMfgId, $parentCatId, $subcatId,
                       $nameEn, $descEn, $nameEs, $descEs, &$counters, $labelsEs = [], $labelsEn = []) {
    uasort($items, fn($a, $b) => $a['_PRICE'] <=> $b['_PRICE']);
    $cheapestCode = array_key_first($items);
    $cheap = $items[$cheapestCode];

    $imageUrls = vetusImageUrls($cheap['IMG'], $web['ogimg']);
    if (empty($imageUrls)) { $counters['skippedNoImg']++; logMsg("SKIP $cheapestCode: sin URL de imagen"); return false; }
    if (!is_dir(IMG_ABS_DIR)) @mkdir(IMG_ABS_DIR, 0775, true);
    $tmpFiles = downloadImagesToTmp($imageUrls, MAX_SUBIMAGES + 1);
    if (empty($tmpFiles)) { $counters['skippedNoImg']++; $counters['imgFail']++; logMsg("SKIP $cheapestCode: " . count($imageUrls) . " URL probadas, ninguna válida"); return false; }

    $mysqli->begin_transaction();
    try {
        $qmodel = $mysqli->real_escape_string($cheapestCode);
        $qean   = $mysqli->real_escape_string($cheap['EAN']);
        $qmfg   = (int) $vetusMfgId;
        $sql = "INSERT INTO products (products_quantity, check_stock, products_model, products_image, products_price, products_cost, products_date_added, products_weight, products_status, products_tax_class_id, manufacturers_id, product_ean, reference_prov, products_import_origin)
            VALUES (0, 0, \"$qmodel\", \"\", " . number_format($cheap['_PRICE'], 4, '.', '') . ", " . number_format($cheap['_COST'], 4, '.', '') . ", NOW(), {$cheap['_WEIGHT']}, 2, " . TAX_CLASS_IVA21 . ", $qmfg, \"$qean\", \"$qmodel\", \"" . ORIGIN_FLAG . "\")";
        if (!$mysqli->query($sql)) throw new Exception("products: " . $mysqli->error);
        $pid = (int) $mysqli->insert_id;

        $qNameEs = $mysqli->real_escape_string($nameEs);
        $qDescEs = $mysqli->real_escape_string($descEs);
        $qNameEn = $mysqli->real_escape_string($nameEn);
        $qDescEn = $mysqli->real_escape_string($descEn);
        if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_ES . ", \"$qNameEs\", \"$qDescEs\", 0)")) throw new Exception("desc ES: " . $mysqli->error);
        if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_EN . ", \"$qNameEn\", \"$qDescEn\", 0)")) throw new Exception("desc EN: " . $mysqli->error);
        $catTarget = $subcatId > 0 ? $subcatId : $parentCatId;
        if (!$mysqli->query("INSERT INTO products_to_categories (products_id, categories_id) VALUES ($pid, " . (int)$catTarget . ")")) throw new Exception("p2c: " . $mysqli->error);
        if (!$mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . G1_GROUP_ID . ", $pid, " . number_format($cheap['_G1'], 4, '.', '') . ", 1, 1)")) throw new Exception("g1: " . $mysqli->error);

        // Imágenes: 1ª = products_image, resto = products_subimages
        $slug = vetusSlugify($nameEs ?: $nameEn);
        $imgFinalNames = [];
        foreach ($tmpFiles as $i => $tmpAbs) {
            $suffix = ($i === 0) ? '' : ('-' . ($i + 1));
            $finalName = $slug . '-' . $pid . $suffix . '.jpg';
            if (@rename($tmpAbs, IMG_ABS_DIR . $finalName)) $imgFinalNames[] = $finalName;
            else @unlink($tmpAbs);
        }
        if (empty($imgFinalNames)) throw new Exception("rename de imágenes falló");
        $mainImg = array_shift($imgFinalNames);
        $mysqli->query("UPDATE products SET products_image=\"" . $mysqli->real_escape_string($mainImg) . "\" WHERE products_id=$pid");
        if (!empty($imgFinalNames)) {
            $subJson = json_encode($imgFinalNames, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $mysqli->query("UPDATE products SET products_subimages='" . $mysqli->real_escape_string($subJson) . "' WHERE products_id=$pid");
        }
        $counters['nWithImg']++;
        $counters['nSubImgTotal'] += count($imgFinalNames);

        $variantsCreated = 0;
        if ($isFamily && count($items) > 1) {
            // Etiquetas ya calculadas y traducidas en el bucle (fallback al code).
            foreach ($items as $code => $it) {
                $labEs = $labelsEs[$code] ?? ($labelsEn[$code] ?? $code);
                $labEn = $labelsEn[$code] ?? $code;
                $delta   = round($it['_PRICE'] - $cheap['_PRICE'], 4);
                $prefix  = $delta < 0 ? '-' : '+';
                $valueId = findOrCreateOptionValue($mysqli, $labEs, $labEn);
                $qrefv   = $mysqli->real_escape_string($code);
                $qveanv  = $mysqli->real_escape_string($it['EAN']);
                $weightDelta = round($it['_WEIGHT'] - $cheap['_WEIGHT'], 3);
                $weightPrefix = $weightDelta < 0 ? '-' : '+';
                if (!$mysqli->query("INSERT INTO products_attributes SET
                    products_id=$pid, options_id=" . VARIANT_OPTION_ID . ", options_values_id=$valueId,
                    options_values_price=" . number_format(abs($delta), 4, '.', '') . ", price_prefix='$prefix',
                    reference='$qrefv', reference_prov='$qrefv', products_attributes_ean='$qveanv',
                    options_values_weight=" . number_format(abs($weightDelta), 3, '.', '') . ", weight_prefix='$weightPrefix'"))
                    throw new Exception("attr: " . $mysqli->error);
                $paId = (int) $mysqli->insert_id;
                $variantsCreated++;
                $g1Delta = round($it['_G1'] - $cheap['_G1'], 4);
                $g1Prefix = $g1Delta < 0 ? '-' : '+';
                if (!$mysqli->query("INSERT INTO products_attributes_groups (products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id, options_values_weight, weight_prefix) VALUES ($paId, " . G1_GROUP_ID . ", " . number_format(abs($g1Delta), 4, '.', '') . ", '$g1Prefix', $pid, 0, '+')"))
                    throw new Exception("attr_groups: " . $mysqli->error);
            }
            if ($variantsCreated > 0) $counters['nWithVar']++;
        }

        $mysqli->commit();

        if (!isValidEan13($cheap['EAN'])) {
            $genEan = generateInternalEan13($pid, EAN_INTERNAL_PREFIX);
            if ($genEan !== '') {
                $mysqli->query("UPDATE products SET product_ean='" . $mysqli->real_escape_string($genEan) . "' WHERE products_id=$pid AND (product_ean IS NULL OR product_ean='' OR LENGTH(product_ean)<>13)");
            }
        }
        $nSubs = count($imgFinalNames);
        logMsg(sprintf("OK %s pid=%d code=%s%s price=%.2f cost=%.2f g1=%.2f imgs=%d subcat=%d",
            $isFamily ? 'FAMILIA' : 'SUELTO', $pid, $cheapestCode,
            $isFamily ? " [{$variantsCreated} variantes]" : '',
            $cheap['_PRICE'], $cheap['_COST'], $cheap['_G1'], 1 + $nSubs, $catTarget));
        return $pid;
    } catch (Exception $e) {
        $mysqli->rollback();
        $counters['errors']++;
        foreach ($tmpFiles as $tmpAbs) { if (file_exists($tmpAbs)) @unlink($tmpAbs); }
        logMsg("ERROR $cheapestCode: " . $e->getMessage());
        return false;
    }
}

$counters = ['skippedNoImg'=>0,'imgFail'=>0,'nWithImg'=>0,'nSubImgTotal'=>0,'nWithVar'=>0,'errors'=>0];

// ---- 1) Familias ----
foreach ($families as $famKey => $items) {
    if ($max > 0 && $nInserted >= $max) { logMsg("Alcanzado límite max=$max."); break; }
    uasort($items, fn($a, $b) => $a['_PRICE'] <=> $b['_PRICE']);  // provisional (col P)
    $cheapestCode = array_key_first($items);
    $web = processWebEnrich($cheapestCode, $skipWebEnrich, $cookieFile, $webFails, $webUnverified);
    $subcatName = $web['verified'] && $web['subcat_en'] !== '' ? vetusSubcatName($web['subcat_en']) : FALLBACK_SUBCAT;

    // Precio web por variante: el padre usa $web; el resto se descarga (solo en EXECUTE).
    if (!$skipWebEnrich) {
        foreach ($items as $vc => $vr) {
            if ($vc === $cheapestCode)      $pd = $web;
            elseif (!$dryRun)               { $vh = vetusFetchProductHtml($vc, $cookieFile); $pd = ($vh !== false) ? vetusParseProduct($vh, $vc) : ['has_price'=>false,'rrp'=>0.0,'cost'=>0.0]; }
            else                            $pd = ['has_price'=>false,'rrp'=>0.0,'cost'=>0.0];
            [$p, $c, $g] = vetusComputePricing($pd, $vr['_COLP']);
            $items[$vc]['_PRICE'] = $p; $items[$vc]['_COST'] = $c; $items[$vc]['_G1'] = $g;
        }
        uasort($items, fn($a, $b) => $a['_PRICE'] <=> $b['_PRICE']);  // re-ordenar por precio web
        $cheapestCode = array_key_first($items);
    }
    $cheap = $items[$cheapestCode];

    if ($dryRun) {
        $nInserted++; $nFamiliesIns++;
        if ($nFamiliesIns <= 15) {
            $lcp = longestCommonPrefix(array_map(fn($r) => $r['_NAME_EN'], $items));
            logMsg(sprintf("  WOULD INSERT FAMILIA key=%s (%d variantes) cheap=%s price=%.2f cost=%.2f web=%s subcat='%s' lcp='%s'",
                $famKey, count($items), $cheapestCode, $cheap['_PRICE'], $cheap['_COST'],
                $web['has_price'] ? 'precio' : ($web['verified'] ? 'sin-precio' : 'no'), $subcatName, mb_substr($lcp, 0, 40, 'UTF-8')));
        }
        continue;
    }
    $subcatId = getOrCreateSubcategory($mysqli, $subcatName, $parentCatId, false, $subcatCache, $catCreatedLog);
    // Etiquetas de variante (EN) + traducción ES (cacheada, fuera de la transacción).
    $labelsEn = vetusComputeVariantLabels($items);
    $labelsEs = [];
    foreach ($labelsEn as $vc => $le) $labelsEs[$vc] = vetusTranslateLabel($le, $labelTransCache, $skipTranslation);
    [$nameEn, $descEn, $nameEs, $descEs] = buildContent($cheap, $web, $skipTranslation, false, $translateFail, $formatFail);
    $pid = insertProduct($mysqli, $items, true, $web, $vetusMfgId, $parentCatId, $subcatId, $nameEn, $descEn, $nameEs, $descEs, $counters, $labelsEs, $labelsEn);
    if ($pid) { $nInserted++; $nFamiliesIns++; }
}

// ---- 2) Sueltos ----
foreach ($standalone as $code => $row) {
    if ($max > 0 && $nInserted >= $max) { logMsg("Alcanzado límite max=$max."); break; }
    $web = processWebEnrich($code, $skipWebEnrich, $cookieFile, $webFails, $webUnverified);
    $subcatName = $web['verified'] && $web['subcat_en'] !== '' ? vetusSubcatName($web['subcat_en']) : FALLBACK_SUBCAT;
    [$p, $c, $g] = vetusComputePricing($web, $row['_COLP']);
    $row['_PRICE'] = $p; $row['_COST'] = $c; $row['_G1'] = $g;

    if ($dryRun) {
        $nInserted++; $nStandaloneIns++;
        if ($nStandaloneIns <= 10) {
            logMsg(sprintf("  WOULD INSERT SUELTO code=%s price=%.2f cost=%.2f web=%s subcat='%s' name='%s'",
                $code, $row['_PRICE'], $row['_COST'],
                $web['has_price'] ? 'precio' : ($web['verified'] ? 'sin-precio' : 'no'), $subcatName,
                mb_substr(vetusBuildName($row['_NAME_EN'], $code), 0, 60, 'UTF-8')));
        }
        continue;
    }
    $subcatId = getOrCreateSubcategory($mysqli, $subcatName, $parentCatId, false, $subcatCache, $catCreatedLog);
    [$nameEn, $descEn, $nameEs, $descEs] = buildContent($row, $web, $skipTranslation, false, $translateFail, $formatFail);
    $pid = insertProduct($mysqli, [$code => $row], false, $web, $vetusMfgId, $parentCatId, $subcatId, $nameEn, $descEn, $nameEs, $descEs, $counters);
    if ($pid) { $nInserted++; $nStandaloneIns++; }
}

logMsg("==================== RESUMEN ====================");
logMsg("Insertados: $nInserted (familias=$nFamiliesIns sueltos=$nStandaloneIns)");
logMsg("Con imagen: {$counters['nWithImg']} (sub-imágenes: {$counters['nSubImgTotal']}) | fallos descarga: {$counters['imgFail']} | skip sin imagen: {$counters['skippedNoImg']}");
logMsg("Familias con variantes: {$counters['nWithVar']}");
logMsg("Web: fetches fallidos=$webFails | sin verificar SKU=$webUnverified");
logMsg("Traducciones fallidas: $translateFail | maquetados fallidos: $formatFail");
logMsg("Errores INSERT: {$counters['errors']}");
if (!empty($catCreatedLog)) {
    logMsg(($dryRun ? "Se crearían" : "Creados") . " (categorías/manufacturer): " . count($catCreatedLog));
    foreach (array_slice($catCreatedLog, 0, 30) as $v) logMsg("  · $v");
}

end_action:
?>
    </div>
    <p style="margin-top:15px;"><a href="<?php echo tep_href_link('import-vetus-altas.php'); ?>" class="xbutton small hv9">← Volver</a></p>
<?php else: ?>
    <h2>Importador Vetus (altas)</h2>
    <?php
        $xlsx = findNewestXlsx(VETUS_DIR);
        if (!$xlsx) echo '<p style="color:red;">No hay xlsx en ' . VETUS_DIR . '</p>';
        else echo '<p style="color:#666;font-size:13px;">xlsx detectado: <code>' . htmlspecialchars(basename($xlsx)) . '</code> (' . round(filesize($xlsx)/1024) . ' KB, mtime ' . date('Y-m-d H:i', filemtime($xlsx)) . ')</p>';
    ?>
    <p>
        Lee el xlsx más reciente de <code><?php echo VETUS_DIR; ?></code>, importa SOLO filas con
        <code>STATUS = <?php echo STATUS_PUBLISH; ?></code>, scrapea <code>webshop.vetus.com</code> (búsqueda por SKU)
        para descripción, especificaciones, categoría e imagen, y crea productos en
        <strong><?php echo PARENT_CATEGORY_NAME_ES; ?></strong> con subcategorías por categoría del webshop.
    </p>
    <p style="background:#fffbe6;border:1px solid #ffd700;padding:10px;border-radius:4px;font-size:13px;">
        <strong>Filtros que se aplican siempre</strong>: solo <code>STATUS = <?php echo STATUS_PUBLISH; ?></code>;
        skip si el <code>modelo</code> (col A) o el <code>EAN</code> (col N) ya existen en BD.<br>
        <strong>Nombre</strong>: «Vetus {descripción} {modelo}». <strong>Variantes</strong>: por defecto cada SKU es un producto independiente (igual que la web de Vetus).<br>
        <strong>Precio</strong>: con login B2B saca de la web el RRP + % dto + neto dealer → <code>price = roundToNickel(RRP web)</code>, <code>cost = neto dealer</code>, <code>G1 = roundToNickel(price × 0,80)</code>. Fallback a col P (cost 0) si no hay precio web.
    </p>
    <form method="get" style="background:#f5f5f5;padding:15px;border-radius:5px;">
        <p>
            <strong>Codes específicos</strong> (opcional, ignora el resto del catálogo):<br>
            <textarea name="codes" rows="3" style="width:100%;font-family:monospace;" placeholder="Uno o varios SKU (col A) separados por coma, espacio o salto de línea. Si el code es variante de una familia, se importan también el resto de variantes."><?php echo htmlspecialchars($onlyCodesRaw); ?></textarea>
        </p>
        <p><label><input type="checkbox" name="dry_run" value="1" checked> Dry-run (sin cambios)</label></p>
        <p><label><input type="checkbox" name="skip_translation" value="1"> Saltar traducción/maquetado LLM (ES = EN, mucho más rápido)</label></p>
        <p><label><input type="checkbox" name="group_variants" value="1"> Agrupar variantes por prefijo común del nombre (LCP) — <strong>⚠ puede fusionar modelos distintos (voltaje/serie); por defecto desactivado</strong></label></p>
        <p><label><input type="checkbox" name="skip_web_enrich" value="1"> Saltar scraping web (sin descripción larga, specs ni categoría — todo a VARIOS)</label></p>
        <p>Inserts máximos por ejecución (0 = sin límite): <input type="number" name="max" value="10" min="0" style="width:80px;"></p>
        <button type="submit" name="action" value="dry_run" class="xbutton small hv9">Dry-run</button>
        <button type="submit" name="action" value="execute" class="xbutton small hv9 verde" onclick="return confirm('¿Ejecutar de verdad? Insertará productos en la BD.');">Ejecutar</button>
    </form>
    <p style="margin-top:20px;color:#888;font-size:12px;">
        <strong>Reglas aplicadas:</strong><br>
        - Solo filas con <code>STATUS = <?php echo STATUS_PUBLISH; ?></code> (~5.285 de 16.553).<br>
        - <code>products_price</code> = roundToNickel(RRP de la web B2B); fallback col P. <code>products_cost</code> = precio neto dealer web (RRP × (1−dto%)); fallback 0.<br>
        - G1 (Profesionales) = roundToNickel(price × <?php echo G1_MULT; ?>).<br>
        - Login B2B en webshop.vetus.com para ver el precio (cada variante tiene su propia ficha/precio).<br>
        - Nombre: «Vetus {descripción EN/ES} {modelo}», máx <?php echo PRODUCT_NAME_MAX; ?> chars.<br>
        - <code>products_model</code> y <code>reference_prov</code> = SKU (col A). EAN = col N; si no pasa checksum → interno prefijo <?php echo EAN_INTERNAL_PREFIX; ?>.<br>
        - Web: descripción + especificaciones + categoría (breadcrumb) + imagen, solo si el SKU se verifica en la ficha.<br>
        - Imagen: col Q (platform.vetus.com) + og:image de la web. Skip producto sin imagen.<br>
        - Por defecto SIN variantes (cada SKU suelto). Solo si marcas «Agrupar variantes»: <code>products_attributes</code> (options_id=<?php echo VARIANT_OPTION_ID; ?>, «Modelo»), padre = más barato.<br>
        - ES traducido/maquetado vía LLM. Stock NO se toca (qty=0, status=2, check_stock=0).<br>
        - Categoría: <strong><?php echo PARENT_CATEGORY_NAME_ES; ?></strong> + subcategoría por categoría del webshop (fallback <?php echo FALLBACK_SUBCAT; ?>).<br>
        - Output en streaming en tiempo real.
    </p>
<?php endif; ?>
</div>
</td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
