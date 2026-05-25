<?php
require 'includes/application_top.php';
require_once DIR_FS_DOCUMENT_ROOT . 'includes/vendor/autoload.php';

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);

const LALIZAS_DIR     = '/home/francobordo/public_html/import/Lalizas/';
const XML_PATH        = '/home/francobordo/public_html/import/feed/lalizas.xml';
const B2B_DESC_PATH   = '/home/francobordo/public_html/import/Lalizas/cache/b2b_desc.json'; // EAN→{desc,lang} de webs de marca
define('IMG_ABS_DIR',    dirname(dirname(__FILE__)) . '/images/productos/');

const PARENT_CATEGORY_NAME_ES  = 'Lalizas Nuevos';
const PARENT_CATEGORY_NAME_EN  = 'Lalizas New';
const PARENT_CATEGORY_NAME_KEY = 'Lalizas Nuevos';
const FALLBACK_SUBCAT          = 'VARIOS';

const TAX_CLASS_IVA21   = 1;
const LANG_ID_ES        = 3;       // idioma nativo del feed (español)
const LANG_ID_EN        = 1;       // traducción
const G1_GROUP_ID       = 1;
const G1_FLOOR_FACTOR   = 1.10;
const IMG_HTTP_TIMEOUT  = 15;
const IMG_MIN_BYTES     = 2048;
const MAX_SUBIMAGES     = 6;
const ORIGIN_FLAG       = 'lalizas';
const VARIANT_OPTION_ID = 3;       // "Modelo"
const VETUS_VAT_RATE    = 0.21;
// Agrupación estricta (opt-in): umbrales altos para no fusionar productos distintos.
const LCP_VARIANT_RATIO = 0.55;
const LCP_VARIANT_HIGH  = 0.70;
const PRICE_DISPERSION_MAX = 5.0;

// Item Group (col C xlsx) → manufacturers_id en BD (genéricos → Lalizas=3).
const MFG_MAP = [
    'LALIZAS'        => 3,
    'NUOVARADE'      => 96,
    'LOFRANS'        => 16,
    'OCEAN'          => 246,
    'MAXPOWER'       => 376,
    'SELECTIONITEMS' => 3,
    'COMMERCIALONLY' => 3,
];
// Item Group → nombre legible de la subcategoría / manufacturer a crear si no está en MFG_MAP.
const GROUP_LABELS = [
    'LALIZAS'        => 'Lalizas',
    'NUOVARADE'      => 'Nuova Rade',
    'LOFRANS'        => 'Lofrans',
    'OCEAN'          => 'Ocean',
    'MAXPOWER'       => 'MaxPower',
    'SELECTIONITEMS' => 'Selection Items',
    'COMMERCIALONLY' => 'Commercial Only',
    'ARIMAR'         => 'Arimar',
];

const LLM_URL    = 'http://217.127.199.171:28001/v1/chat/completions';
const LLM_MODEL  = 'qwen36-sakamaki-nvfp4';
// Traducción ES → EN (al revés que los demás importadores).
const LLM_PROMPT_NAME = 'You are a professional Spanish-to-English translator specialised in nautical, marine, hardware and safety products. Use precise English nautical terminology. Nautical reference glossary (ES↔EN): cabo=rope/line, grillete=shackle, cornamusa=cleat, pasacable=fairlead/chock, winche=winch, molinete=windlass, hélice de maniobra=thruster, sentina=bilge, escotilla=hatch, defensa=fender, amarre=mooring, ancla=anchor, cadena=chain, casco=hull, cubierta=deck, timón=rudder, pasacascos=through-hull, grifo de fondo=seacock, acero inoxidable=stainless steel, galvanizado=galvanized, fueraborda=outboard. Always use the nautical meaning; do not translate as terms from other domains. Keep brands, model names, alphanumeric codes and units (mm, cm, kg, V, W, L) unchanged. Plain text. Answer ONLY with the translation, no comments, no quotes.';
const LLM_PROMPT_DESC = 'You are a professional Spanish-to-English translator specialised in nautical, marine, hardware and safety products. Use precise English nautical terminology. Nautical reference glossary (ES↔EN): cabo=rope/line, grillete=shackle, cornamusa=cleat, pasacable=fairlead/chock, winche=winch, molinete=windlass, hélice de maniobra=thruster, sentina=bilge, escotilla=hatch, defensa=fender, amarre=mooring, ancla=anchor, cadena=chain, casco=hull, cubierta=deck, timón=rudder, pasacascos=through-hull, grifo de fondo=seacock, acero inoxidable=stainless steel, galvanizado=galvanized, fueraborda=outboard. Always use the nautical meaning; do not translate as terms from other domains. Keep brands, models, alphanumeric codes and units unchanged. Keep any <br>/<p>/<ul>/<li>/<strong> tags. Answer ONLY with the translation, no comments.';
// Dirección inversa EN→ES (para descripciones que vienen del B2B / webs de marca, en inglés).
const LLM_PROMPT_DESC_EN2ES = 'Eres un traductor profesional de inglés a español de España, especializado en productos náuticos, marinos, ferretería y seguridad. Usa terminología técnica náutica precisa. Glosario náutico de referencia (EN↔ES): rope/line=cabo, shackle=grillete, cleat=cornamusa, fairlead/chock=pasacable, winch=winche, windlass=molinete, thruster=hélice de maniobra, bilge=sentina, hatch=escotilla/tambucho, fender=defensa, mooring=amarre, anchor=ancla, chain=cadena, hull=casco, deck=cubierta, rudder=timón, through-hull=pasacascos, seacock=grifo de fondo, stainless steel=acero inoxidable, galvanized=galvanizado, outboard=fueraborda. Usa siempre el sentido náutico; no lo traduzcas como términos de otros dominios. Conserva marcas, modelos, códigos alfanuméricos y unidades sin traducir. Conserva las etiquetas <br>/<p>/<ul>/<li>/<strong> si las hay. Responde SOLO con la traducción, sin comentarios.';

const LLM_FORMAT_PROMPT_ES = "Eres un experto en maquetar fichas de producto náuticas/marinas. Recibes una descripción comercial en ESPAÑOL y la transformas en HTML legible y atractivo.\n\nREGLAS OBLIGATORIAS:\n\n1. PÁRRAFO INTRODUCTORIO: el primer <p> es la frase descriptiva más completa del producto.\n\n2. AGRUPACIÓN POR SECCIONES (OBLIGATORIO si el producto tiene >6 features): clasifica las features en secciones temáticas con <h3>. Cada sección abre con <h3>...</h3> y debajo un <ul><li>...</li></ul>. Si el producto tiene <6 features, una sola lista sin h3.\n\n3. ÉNFASIS CON <strong>: en CADA <li>, identifica el concepto clave (1-4 palabras al inicio) y envuélvelo en <strong> seguido de dos puntos. NO inventes texto.\n\n4. PRESERVA enlaces <a href> y <sup> existentes.\n\n5. NO resumas, NO parafrasees, NO inventes: conserva TODO el texto original. Solo añades estructura.\n\n6. Si el texto es solo 1-2 frases cortas, devuelve <p>texto</p> sin lista.\n\n7. Etiquetas permitidas: <h3>, <h4>, <p>, <ul>, <li>, <strong>, <a>, <sup>. Prohibidas: <h1>, <h2>, <br>, <div>, <span>.\n\n8. Salida: SOLO el HTML, sin markdown, sin comentarios, sin tres-back-ticks.";
const LLM_FORMAT_PROMPT_EN = "You are an expert in formatting nautical/marine product datasheets. You receive a commercial description in ENGLISH and transform it into clean, readable HTML.\n\nMANDATORY RULES:\n\n1. INTRODUCTORY PARAGRAPH: the first <p> is the most complete descriptive sentence.\n\n2. SECTION GROUPING (MANDATORY if >6 features): classify features into thematic sections with <h3> followed by <ul><li>...</li></ul>. If <6 features, one single list without h3.\n\n3. <strong> EMPHASIS: in EACH <li>, wrap the key concept (1-4 words at start) in <strong> followed by a colon. DO NOT invent text.\n\n4. PRESERVE existing <a href> and <sup> tags.\n\n5. DO NOT summarize/paraphrase/invent: keep ALL original text. Only add structure.\n\n6. If input is only 1-2 short sentences, return <p>text</p> without a list.\n\n7. Allowed tags: <h3>, <h4>, <p>, <ul>, <li>, <strong>, <a>, <sup>. Forbidden: <h1>, <h2>, <br>, <div>, <span>.\n\n8. Output: ONLY the HTML, no markdown, no comments, no triple-backticks.";

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$max    = isset($_POST['max']) ? (int) $_POST['max'] : (isset($_GET['max']) ? (int) $_GET['max'] : 0);
$dryRun = isset($_POST['dry_run']) || isset($_GET['dry_run']) || $action === 'dry_run';
$skipTranslation = isset($_POST['skip_translation']) || isset($_GET['skip_translation']);
// Atributos OPCIONALES (off por defecto) y ESTRICTOS cuando se activan.
$groupVariants   = isset($_POST['group_variants']) || isset($_GET['group_variants']);
$skipVariants    = !$groupVariants;
$selectedBrand   = trim((string) ($_POST['brand'] ?? $_GET['brand'] ?? 'all'));   // Item Group (col C) o 'all'
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

function lalParseNum($v) {
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
    $out = []; $emptyStreak = 0;
    foreach ($lines as $l) {
        $l = trim(preg_replace('/[ \t\x{A0}]+/u', ' ', $l));
        if ($l === '') { if ($emptyStreak < 1 && !empty($out)) $out[] = ''; $emptyStreak++; continue; }
        $out[] = $l; $emptyStreak = 0;
    }
    return trim(implode("\n", $out));
}

function lalSlugify($text, $maxLen = 50) {
    $t = trim((string) $text);
    if (function_exists('iconv')) { $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t); if ($conv !== false && $conv !== '') $t = $conv; }
    $t = strtolower($t);
    $t = preg_replace('/[^a-z0-9]+/', '-', $t);
    $t = trim($t, '-');
    if (strlen($t) > $maxLen) $t = substr($t, 0, $maxLen);
    $t = trim($t, '-');
    return $t === '' ? 'producto' : $t;
}

function groupKey($raw) { return strtoupper(preg_replace('/\s+/', '', (string) $raw)); }
function groupLabel($raw) {
    $k = groupKey($raw);
    return GROUP_LABELS[$k] ?? (trim((string) $raw) !== '' ? trim((string) $raw) : FALLBACK_SUBCAT);
}

/** Resuelve manufacturer por Item Group. Genéricos → Lalizas (3). Desconocidos → crear por nombre. */
function lalResolveManufacturer($mysqli, $groupRaw, $dryRun, &$cache, &$createdLog) {
    $k = groupKey($groupRaw);
    if (isset(MFG_MAP[$k])) return MFG_MAP[$k];
    if (isset($cache[$k])) return $cache[$k];
    $display = groupLabel($groupRaw);
    $q = $mysqli->real_escape_string($display);
    $r = $mysqli->query("SELECT manufacturers_id FROM manufacturers WHERE UPPER(TRIM(manufacturers_name))=UPPER('$q') LIMIT 1");
    if ($r && $row = $r->fetch_assoc()) { $cache[$k] = (int) $row['manufacturers_id']; return $cache[$k]; }
    if ($dryRun) { $cache[$k] = 3; $createdLog[] = "manufacturer '$display' (dry-run, usaría Lalizas=3)"; return 3; }
    $mysqli->query("INSERT INTO manufacturers (manufacturers_name, date_added) VALUES ('$q', NOW())");
    $id = (int) $mysqli->insert_id;
    $mysqli->query("INSERT INTO manufacturers_info (manufacturers_id, languages_id, manufacturers_url) VALUES ($id, " . LANG_ID_ES . ", '')");
    $mysqli->query("INSERT INTO manufacturers_info (manufacturers_id, languages_id, manufacturers_url) VALUES ($id, " . LANG_ID_EN . ", '')");
    $cache[$k] = $id;
    $createdLog[] = "manufacturer '$display' (id=$id)";
    return $id;
}

/** Dedup: modelo scoped a manufacturers Lalizas/origin; EAN global (GS1). */
function buildExistingMap($mysqli) {
    $existing = [];
    $f = "(p.products_import_origin LIKE 'lalizas%' OR p.manufacturers_id IN (3,16,96,246,376))";
    foreach (["SELECT LOWER(p.products_model) m FROM products p WHERE p.products_model<>'' AND p.products_model IS NOT NULL AND $f",
              "SELECT LOWER(p.reference_prov) m FROM products p WHERE p.reference_prov<>'' AND p.reference_prov IS NOT NULL AND $f",
              "SELECT LOWER(pa.reference) m FROM products_attributes pa INNER JOIN products p ON p.products_id=pa.products_id WHERE pa.reference<>'' AND pa.reference IS NOT NULL AND $f",
              "SELECT LOWER(pa.reference_prov) m FROM products_attributes pa INNER JOIN products p ON p.products_id=pa.products_id WHERE pa.reference_prov<>'' AND pa.reference_prov IS NOT NULL AND $f"] as $sql) {
        $r = $mysqli->query($sql); while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
    }
    foreach (["SELECT LOWER(product_ean) m FROM products WHERE product_ean<>'' AND product_ean IS NOT NULL",
              "SELECT LOWER(products_attributes_ean) m FROM products_attributes WHERE products_attributes_ean<>'' AND products_attributes_ean IS NOT NULL"] as $sql) {
        $r = $mysqli->query($sql); while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
    }
    return $existing;
}

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
        $r = $mysqli->query("SELECT c.categories_id FROM categories c INNER JOIN categories_description cd ON cd.categories_id=c.categories_id AND cd.language_id=" . LANG_ID_ES . " WHERE c.parent_id=$parentId AND UPPER(TRIM(cd.categories_name))=UPPER('$qName') LIMIT 1");
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

function llmCall($systemPrompt, $userText, $maxTokens = 1500, $maxRetries = 2) {
    if (trim((string) $userText) === '') return '';
    $payload = json_encode(['model' => LLM_MODEL,
        'messages' => [['role'=>'system','content'=>$systemPrompt],['role'=>'user','content'=>$userText]],
        'temperature' => 0.2, 'max_tokens' => $maxTokens, 'chat_template_kwargs' => ['enable_thinking' => false]], JSON_UNESCAPED_UNICODE);
    for ($i = 0; $i <= $maxRetries; $i++) {
        $ch = curl_init(LLM_URL);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_POSTFIELDS=>$payload, CURLOPT_TIMEOUT=>90, CURLOPT_CONNECTTIMEOUT=>10]);
        $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); unset($ch);
        if ($resp !== false && $code === 200) {
            $j = json_decode($resp, true);
            $content = $j['choices'][0]['message']['content'] ?? null;
            if (is_string($content) && trim($content) !== '') return trim($content);
        }
        usleep(500000);
    }
    return '';
}

function formatLooksValid($html, $minLenInput) {
    if (!is_string($html) || trim($html) === '') return false;
    $low = strtolower($html);
    if (strpos($low,'<p>')===false && strpos($low,'<ul>')===false && strpos($low,'<h3>')===false) return false;
    $plainOutLen = mb_strlen(trim(strip_tags($html)), 'UTF-8');
    if ($minLenInput > 200 && $plainOutLen < $minLenInput * 0.4) return false;
    return true;
}

function downloadImage($url, $destAbs) {
    if (empty($url)) return false;
    $url = trim($url);
    if (strpos($url, ',') !== false || strpos($url, ' ') !== false) return false;
    if (!preg_match('#^https?://#i', $url)) return false;
    $ch = curl_init($url); $fp = fopen($destAbs, 'wb');
    if (!$fp) return false;
    curl_setopt_array($ch, [CURLOPT_FILE=>$fp, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>IMG_HTTP_TIMEOUT, CURLOPT_CONNECTTIMEOUT=>5,
        CURLOPT_USERAGENT=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_HTTPHEADER=>['Accept: image/avif,image/webp,image/*,*/*;q=0.8']]);
    $ok = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); unset($ch); fclose($fp);
    $ok = $ok && $code === 200 && filesize($destAbs) >= IMG_MIN_BYTES;
    if (!$ok) @unlink($destAbs);
    return $ok;
}
function downloadImagesToTmp(array $urls, $maxImages) {
    $seen = []; $tmpFiles = [];
    foreach ($urls as $url) {
        if (count($tmpFiles) >= $maxImages) break;
        $url = trim($url); if ($url === '' || isset($seen[$url])) continue; $seen[$url] = true;
        $tmpAbs = IMG_ABS_DIR . 'lal-tmp-' . uniqid('', true) . '.jpg';
        if (downloadImage($url, $tmpAbs)) $tmpFiles[] = $tmpAbs;
    }
    return $tmpFiles;
}

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

function ean13Checksum($p) { if (strlen($p)!==12||!ctype_digit($p)) return -1; $s=0; for($i=0;$i<12;$i++){$d=(int)$p[$i];$s+=($i%2===0)?$d:$d*3;} return (10-($s%10))%10; }
function isValidEan13($e) { $e=trim((string)$e); if(strlen($e)!==13||!ctype_digit($e))return false; return ean13Checksum(substr($e,0,12))===(int)$e[12]; }

function longestCommonPrefix(array $strs) {
    if (empty($strs)) return ''; $strs = array_values($strs); $prefix = $strs[0]; $prefixLen = mb_strlen($prefix,'UTF-8');
    foreach ($strs as $s) { while ($prefixLen>0 && mb_substr($s,0,$prefixLen,'UTF-8')!==$prefix){$prefixLen--;$prefix=mb_substr($prefix,0,$prefixLen,'UTF-8');} if($prefixLen===0)return ''; }
    return $prefix;
}
function normMeasure($s) {
    $s = trim((string)$s); if ($s==='') return '';
    $s = preg_replace_callback('/(\d+(?:[.,]\d+)?)\s*(mm|cm|m|kg|g|lt|l|ml|in|inch|"|hp|w|v|a|ah|hz|°|oz)\b/iu', fn($m)=>$m[1].' '.strtolower($m[2]), $s);
    return trim(preg_replace('/\s+/',' ',$s));
}
function extractMeasure($t) {
    $t=(string)$t;
    if (preg_match('/\b(Nº|No|N|Talla|Size)\s*\.?\s*(\d+)\b/iu',$t,$m)) return 'Nº '.$m[2];
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*[xX]\s*(\d+(?:[.,]\d+)?)(?:\s*mm)?\b/u',$t,$m)) return $m[1].'x'.$m[2];
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*(mm|cm|m|kg|g|lt|l|ml|prs|personas|V|W|A|Ah)\b/iu',$t,$m)) return $m[1].' '.strtolower($m[2]);
    return '';
}

function isLegitimateFamily(array $items) {
    if (count($items) < 2) return false;
    $names = array_map(fn($r)=>$r['NAME'], $items);
    if (count(array_unique($names)) < count($names)) return false;
    $lcp = longestCommonPrefix($names); $lcpLen = mb_strlen($lcp,'UTF-8');
    $minLen = min(array_map(fn($s)=>mb_strlen($s,'UTF-8'), $names));
    if ($minLen <= 0) return false;
    // anti-corte de código: si el LCP acaba en palabra alfanumérica ≥3 y el siguiente char es alfanumérico
    if ($lcpLen > 0) {
        $tokens = preg_split('/[\s\-_\.\/,]+/', $lcp); $last = end($tokens) ?: '';
        if (mb_strlen($last,'UTF-8') >= 3 && preg_match('/[A-Za-z0-9]/u', mb_substr($lcp,-1,1,'UTF-8'))) {
            foreach ($names as $n) { $nx = mb_substr($n,$lcpLen,1,'UTF-8'); if ($nx!=='' && preg_match('/[A-Za-z0-9]/u',$nx)) return false; }
        }
    }
    $ratio = $lcpLen / $minLen;
    if ($ratio < LCP_VARIANT_RATIO) return false;
    $prices = array_map(fn($r)=>max($r['_PRICE'],0.01), $items);
    if (max($prices)/min($prices) > PRICE_DISPERSION_MAX) return false;
    if ($ratio >= LCP_VARIANT_HIGH) return true;
    foreach ($names as $n) { if (!preg_match('/\d/u', mb_substr($n,$lcpLen,null,'UTF-8'))) return false; }
    return true;
}

function findOrCreateOptionValue($mysqli, $nameEs, $nameEn = null) {
    if ($nameEn === null) $nameEn = $nameEs;
    $nameEsSafe = $mysqli->real_escape_string($nameEs);
    $q = $mysqli->query("SELECT pov.products_options_values_id FROM products_options_values pov INNER JOIN products_options_values_to_products_options pov2po ON pov2po.products_options_values_id = pov.products_options_values_id WHERE pov2po.products_options_id = " . VARIANT_OPTION_ID . " AND pov.language_id = " . LANG_ID_ES . " AND pov.products_options_values_name = '$nameEsSafe' LIMIT 1");
    if ($row = $q->fetch_assoc()) return (int) $row['products_options_values_id'];
    $newId = (int) ($mysqli->query("SELECT IFNULL(MAX(products_options_values_id),0)+1 AS n FROM products_options_values")->fetch_assoc()['n']);
    $nameEnSafe = $mysqli->real_escape_string($nameEn);
    $mysqli->query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($newId, " . LANG_ID_ES . ", '$nameEsSafe', '')");
    $mysqli->query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($newId, " . LANG_ID_EN . ", '$nameEnSafe', '')");
    $mysqli->query("INSERT INTO products_options_values_to_products_options (products_options_id, products_options_values_id) VALUES (" . VARIANT_OPTION_ID . ", $newId)");
    return $newId;
}

function roundToNickel($net) {
    $r = round(((float)$net) * (1 + VETUS_VAT_RATE) * 20) / 20;
    return round($r / (1 + VETUS_VAT_RATE), 4);
}

function findNewestXlsx($dir) {
    if (!is_dir($dir)) return null; $best=null;$bestT=0;
    foreach (scandir($dir) as $f) { if (substr($f,-5)!=='.xlsx'||substr($f,0,1)==='~') continue; $t=filemtime($dir.$f); if($t>$bestT){$bestT=$t;$best=$dir.$f;} }
    return $best;
}

/** Lee la hoja "precios": A=Ref B=Nombre C=ItemGroup D=EAN F=coste neto G=PVP sin IVA. */
function loadXlsxRows($file) {
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file);
    $reader->setReadDataOnly(true);
    $sheet = $reader->load($file)->getSheetByName('precios');
    if (!$sheet) return [];
    $hi = $sheet->getHighestRow();
    $rows = [];
    for ($r = 2; $r <= $hi; $r++) {
        $rows[] = [
            'REF'   => trim((string) $sheet->getCell('A'.$r)->getValue()),
            'NAME'  => trim((string) $sheet->getCell('B'.$r)->getValue()),
            'GROUP' => trim((string) $sheet->getCell('C'.$r)->getValue()),
            'EAN'   => trim((string) $sheet->getCell('D'.$r)->getValue()),
            'COST'  => trim((string) $sheet->getCell('F'.$r)->getValue()),
            'PVP'   => trim((string) $sheet->getCell('G'.$r)->getValue()),
        ];
    }
    return $rows;
}

/** Lista de Item Groups (marcas) distintos del xlsx con su recuento, para el desplegable. */
function listGroups($rows) {
    $g = [];
    foreach ($rows as $r) { $name = trim((string)$r['GROUP']); if ($name === '') $name = '(sin grupo)'; $g[$name] = ($g[$name] ?? 0) + 1; }
    uksort($g, fn($a,$b)=>strcasecmp($a,$b));
    return $g;
}

/** Carga del XML lalizas: imagen + descripción por EAN y por Code. */
function loadXmlMedia($path) {
    $byEan = []; $byCode = [];
    if (!file_exists($path)) return [$byEan, $byCode];
    $x = @simplexml_load_file($path);
    if ($x === false) return [$byEan, $byCode];
    foreach ($x->Row as $row) {
        $code = strtolower(trim((string) $row->Code));
        $ean  = trim((string) $row->Barcode);
        $rec = ['img' => trim((string) $row->Image), 'desc' => trim((string) $row->Description)];
        if ($ean !== '')  $byEan[$ean] = $rec;
        if ($code !== '') $byCode[$code] = $rec;
    }
    return [$byEan, $byCode];
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
    <h2>Importador Lalizas — <?php echo $dryRun ? 'DRY-RUN (sin cambios)' : 'EJECUCIÓN REAL'; ?></h2>
    <p><a href="<?php echo tep_href_link('import-lalizas-altas.php'); ?>" class="xbutton small hv9">← Volver</a></p>
    <div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n";
@flush();
if (!empty($onlyCodes) && $max > 0) { $maxOverridden = $max; $max = 0; }

logMsg("Modo: " . ($dryRun ? "dry-run" : "EXECUTE")
    . " | marca=" . ($selectedBrand === 'all' ? 'TODAS' : $selectedBrand)
    . (!empty($onlyCodes) ? " | codes=" . count($onlyCodes) : "")
    . ($skipTranslation ? " | sin traducción LLM" : "")
    . ($groupVariants ? " | AGRUPANDO variantes (estricto)" : " | sin agrupar (cada SKU suelto)"));

$xlsx = findNewestXlsx(LALIZAS_DIR);
if (!$xlsx) { logMsg("ERROR: no hay xlsx en " . LALIZAS_DIR); goto end_action; }
logMsg("xlsx: " . basename($xlsx) . " (" . round(filesize($xlsx)/1024) . " KB)");
logMsg("XML media: " . (file_exists(XML_PATH) ? basename(XML_PATH) : "NO EXISTE — sin imágenes/descripciones"));

$rows = loadXlsxRows($xlsx);
logMsg("Filas xlsx 'precios': " . count($rows));
list($xmlByEan, $xmlByCode) = loadXmlMedia(XML_PATH);
logMsg("XML: " . count($xmlByEan) . " por EAN / " . count($xmlByCode) . " por code");
// Descripciones enriquecidas del B2B (webs de marca), por EAN. Solo las usables (con texto).
$b2bDesc = file_exists(B2B_DESC_PATH) ? (json_decode(file_get_contents(B2B_DESC_PATH), true) ?: []) : [];
$b2bDescCount = 0; foreach ($b2bDesc as $v) { if (trim((string)($v['desc'] ?? '')) !== '') $b2bDescCount++; }
logMsg("B2B descripciones cacheadas: " . count($b2bDesc) . " (con texto usable: $b2bDescCount)");

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_error) { logMsg("ERROR DB: " . $mysqli->connect_error); goto end_action; }
$mysqli->set_charset('utf8');

if (!$dryRun && !is_dir(IMG_ABS_DIR)) @mkdir(IMG_ABS_DIR, 0775, true);

$catCreatedLog = []; $mfgCache = [];
$parentCatId = ensureParentCategory($mysqli, $dryRun, $catCreatedLog);
logMsg("Categoría padre 'Lalizas Nuevos': id=" . ($parentCatId ?: '(se creará)'));

logMsg("Cargando IDs existentes en BD…");
$existing = buildExistingMap($mysqli);
logMsg("  → " . count($existing) . " referencias ya en BD");

$candidates = [];
$skipExist = $skipNoRef = $skipNoName = $skipBadPrice = $skipNoImg = $skipNoDesc = $skipCodes = $skipBrand = 0;
$descFromXml = $descFromB2b = 0;
$selBrandKey = groupKey($selectedBrand);
foreach ($rows as $row) {
    $ref = $row['REF']; $ean = $row['EAN'];
    if ($ref === '' && $ean === '') { $skipNoRef++; continue; }
    if ($row['NAME'] === '') { $skipNoName++; continue; }
    if (!empty($onlyCodes) && !isset($onlyCodes[strtoupper($ref)]) && !isset($onlyCodes[strtoupper($ean)])) { $skipCodes++; continue; }
    // Filtro por marca (Item Group). Ignorado si se piden codes específicos.
    if (empty($onlyCodes) && $selectedBrand !== 'all' && groupKey($row['GROUP']) !== $selBrandKey) { $skipBrand++; continue; }
    // Imagen: del XML (obligatoria).
    $media = $xmlByEan[$ean] ?? ($xmlByCode[strtolower($ref)] ?? null);
    $img = $media['img'] ?? '';
    if ($img === '') { $skipNoImg++; continue; }
    // Descripción: XML (ES) preferente; si no, B2B (EN, web de marca).
    $xmlDescTxt = trim((string)($media['desc'] ?? ''));
    $b2bDescTxt = trim((string)($b2bDesc[$ean]['desc'] ?? ''));
    if ($xmlDescTxt === '' && $b2bDescTxt === '') { $skipNoDesc++; continue; }
    // dedup
    if ($ref !== '' && isset($existing[strtolower($ref)])) { $skipExist++; continue; }
    if ($ean !== '' && isset($existing[strtolower($ean)])) { $skipExist++; continue; }

    $cost = lalParseNum($row['COST']);
    $pvp  = lalParseNum($row['PVP']);
    if ($pvp === null || $pvp <= 0) { if ($cost !== null && $cost > 0) $pvp = $cost * 2.0; else { $skipBadPrice++; continue; } }
    if ($cost === null || $cost < 0) $cost = 0.0;
    $row['_COST']   = round($cost, 4);
    $row['_PRICE']  = roundToNickel($pvp);
    $row['_G1']     = roundToNickel(calcG1Price($row['_PRICE'], $cost));
    $row['_WEIGHT'] = 1.0;
    $row['_IMG']    = $img;
    if ($xmlDescTxt !== '') { $row['_DESC_SRC'] = 'xml'; $row['_DESC_ES'] = cleanHtmlAggressive($xmlDescTxt); $row['_DESC_EN'] = ''; $descFromXml++; }
    else                    { $row['_DESC_SRC'] = 'b2b'; $row['_DESC_EN'] = cleanHtmlAggressive($b2bDescTxt); $row['_DESC_ES'] = ''; $descFromB2b++; }
    $candidates[$ref !== '' ? $ref : $ean] = $row;
}
logMsg("Candidatos (con imagen + descripción [XML o B2B], no en BD): " . count($candidates) . " | desc XML=$descFromXml | desc B2B=$descFromB2b");
logMsg("  pre-skip: existentes=$skipExist | sin ref=$skipNoRef | sin nombre=$skipNoName | precio=$skipBadPrice | sin imagen=$skipNoImg | sin descripción=$skipNoDesc" . ($selectedBrand !== 'all' ? " | otra-marca=$skipBrand" : "") . (!empty($onlyCodes) ? " | fuera-de-codes=$skipCodes" : ""));

// ---- Agrupación (opt-in, estricta) ----
$families = []; $standalone = [];
if ($skipVariants) {
    $standalone = $candidates;
} else {
    $byKey = $candidates;
    uasort($byKey, fn($a, $b) => strcmp($a['NAME'], $b['NAME']));
    $bucket = []; $allBuckets = [];
    foreach ($byKey as $code => $row) {
        if (empty($bucket)) { $bucket[$code] = $row; continue; }
        $newLcp = longestCommonPrefix(array_merge(array_map(fn($r)=>$r['NAME'],$bucket),[$row['NAME']]));
        $minLen = min(array_map(fn($r)=>mb_strlen($r['NAME'],'UTF-8'), array_merge($bucket,['_'=>$row])));
        if ($minLen>0 && mb_strlen($newLcp,'UTF-8')/$minLen >= LCP_VARIANT_RATIO) $bucket[$code]=$row;
        else { $allBuckets[]=$bucket; $bucket=[$code=>$row]; }
    }
    if (!empty($bucket)) $allBuckets[] = $bucket;
    $fv=$fs=0;
    foreach ($allBuckets as $b) {
        if (count($b)===1){ foreach($b as $c=>$r)$standalone[$c]=$r; continue; }
        if (isLegitimateFamily($b)){ $families[array_key_first($b)]=$b; $fv++; }
        else { foreach($b as $c=>$r)$standalone[$c]=$r; $fs++; }
    }
    logMsg("Agrupación estricta: $fv familias | $fs grupos divididos en sueltos");
}
if (!empty($onlyCodes)) {
    $famF=[]; foreach($families as $k=>$it){ foreach($it as $c=>$_){ if(isset($onlyCodes[strtoupper($c)])){$famF[$k]=$it;break;} } }
    $stdF=[]; foreach($standalone as $c=>$r){ if(isset($onlyCodes[strtoupper($c)]))$stdF[$c]=$r; }
    $families=$famF; $standalone=$stdF;
}
logMsg("Tras consolidar: " . count($families) . " familias + " . count($standalone) . " sueltos");

$subcatCache = []; $labelTransCache = [];
$nInserted=$nFam=$nStd=0; $counters=['skippedNoImg'=>0,'imgFail'=>0,'nWithImg'=>0,'nSubImgTotal'=>0,'nWithVar'=>0,'errors'=>0];
$translateFail=$formatFail=0;

function buildContent($row, $skipTranslation, &$translateFail, &$formatFail) {
    $nameEs = $row['NAME'];
    // La descripción puede venir en ES (XML) o en EN (B2B / web de marca).
    $src = $row['_DESC_SRC'] ?? 'xml';
    $descEsRaw = $src === 'xml' ? (string)($row['_DESC_ES'] ?? '') : '';
    $descEnRaw = $src === 'b2b' ? (string)($row['_DESC_EN'] ?? '') : '';

    $nameEn = $nameEs;
    $descEs = nl2br(htmlspecialchars($descEsRaw !== '' ? $descEsRaw : $descEnRaw), false);
    $descEnHtml = nl2br(htmlspecialchars($descEnRaw !== '' ? $descEnRaw : $descEsRaw), false);

    if (!$skipTranslation) {
        // Nombre: siempre ES (xlsx) → EN.
        $tn = llmCall(LLM_PROMPT_NAME, $nameEs, 200);
        if ($tn !== '') $nameEn = $tn; else $translateFail++;
        // Completar el idioma que falte de la descripción.
        if ($src === 'xml') {
            $td = ($descEsRaw !== '') ? llmCall(LLM_PROMPT_DESC, $descEsRaw, 1500) : '';
            $descEnRaw = ($td !== '') ? $td : $descEsRaw;
            if ($descEsRaw !== '' && $td === '') $translateFail++;
        } else { // b2b: EN nativo → traducir a ES
            $td = ($descEnRaw !== '') ? llmCall(LLM_PROMPT_DESC_EN2ES, $descEnRaw, 1500) : '';
            $descEsRaw = ($td !== '') ? $td : $descEnRaw;
            if ($descEnRaw !== '' && $td === '') $translateFail++;
        }
        // maquetar ES
        $inEs = mb_strlen(strip_tags($descEsRaw),'UTF-8');
        $fes = llmCall(LLM_FORMAT_PROMPT_ES, $descEsRaw, 2500);
        $descEs = formatLooksValid($fes,$inEs) ? $fes : nl2br(htmlspecialchars($descEsRaw),false);
        if (!formatLooksValid($fes,$inEs)) $formatFail++;
        // maquetar EN
        $inEn = mb_strlen(strip_tags($descEnRaw),'UTF-8');
        $fen = llmCall(LLM_FORMAT_PROMPT_EN, $descEnRaw, 2500);
        $descEnHtml = formatLooksValid($fen,$inEn) ? $fen : nl2br(htmlspecialchars($descEnRaw),false);
        if (!formatLooksValid($fen,$inEn)) $formatFail++;
    }
    return [$nameEn, $descEnHtml, $nameEs, $descEs];
}

function insertProduct($mysqli, $items, $isFamily, $mfgId, $parentCatId, $subcatId,
                       $nameEn, $descEn, $nameEs, $descEs, &$counters, $labelsEs = [], $labelsEn = []) {
    uasort($items, fn($a, $b) => $a['_PRICE'] <=> $b['_PRICE']);
    $cheapestCode = array_key_first($items); $cheap = $items[$cheapestCode];

    if (trim((string)$cheap['_IMG']) === '') { $counters['skippedNoImg']++; logMsg("SKIP $cheapestCode: sin URL de imagen"); return false; }
    if (!is_dir(IMG_ABS_DIR)) @mkdir(IMG_ABS_DIR, 0775, true);
    $tmpFiles = downloadImagesToTmp([$cheap['_IMG']], MAX_SUBIMAGES + 1);
    if (empty($tmpFiles)) { $counters['skippedNoImg']++; $counters['imgFail']++; logMsg("SKIP $cheapestCode: imagen no descargable"); return false; }

    $mysqli->begin_transaction();
    try {
        $qmodel = $mysqli->real_escape_string($cheapestCode);
        $qean   = $mysqli->real_escape_string($cheap['EAN']);
        $sql = "INSERT INTO products (products_quantity, check_stock, products_model, products_image, products_price, products_cost, products_date_added, products_weight, products_status, products_tax_class_id, manufacturers_id, product_ean, reference_prov, products_import_origin)
            VALUES (0, 0, \"$qmodel\", \"\", " . number_format($cheap['_PRICE'],4,'.','') . ", " . number_format($cheap['_COST'],4,'.','') . ", NOW(), {$cheap['_WEIGHT']}, 2, " . TAX_CLASS_IVA21 . ", " . (int)$mfgId . ", \"$qean\", \"$qmodel\", \"" . ORIGIN_FLAG . "\")";
        if (!$mysqli->query($sql)) throw new Exception("products: " . $mysqli->error);
        $pid = (int) $mysqli->insert_id;

        $qNameEs=$mysqli->real_escape_string($nameEs); $qDescEs=$mysqli->real_escape_string($descEs);
        $qNameEn=$mysqli->real_escape_string($nameEn); $qDescEn=$mysqli->real_escape_string($descEn);
        if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_ES . ", \"$qNameEs\", \"$qDescEs\", 0)")) throw new Exception("desc ES: ".$mysqli->error);
        if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_EN . ", \"$qNameEn\", \"$qDescEn\", 0)")) throw new Exception("desc EN: ".$mysqli->error);
        $catTarget = $subcatId > 0 ? $subcatId : $parentCatId;
        if (!$mysqli->query("INSERT INTO products_to_categories (products_id, categories_id) VALUES ($pid, " . (int)$catTarget . ")")) throw new Exception("p2c: ".$mysqli->error);
        if (!$mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . G1_GROUP_ID . ", $pid, " . number_format($cheap['_G1'],4,'.','') . ", 1, 1)")) throw new Exception("g1: ".$mysqli->error);

        $slug = lalSlugify($nameEs ?: $nameEn); $imgFinal = [];
        foreach ($tmpFiles as $i => $tmpAbs) { $suf = ($i===0)?'':('-'.($i+1)); $fn=$slug.'-'.$pid.$suf.'.jpg'; if (@rename($tmpAbs, IMG_ABS_DIR.$fn)) $imgFinal[]=$fn; else @unlink($tmpAbs); }
        if (empty($imgFinal)) throw new Exception("rename de imágenes falló");
        $main = array_shift($imgFinal);
        $mysqli->query("UPDATE products SET products_image=\"" . $mysqli->real_escape_string($main) . "\" WHERE products_id=$pid");
        if (!empty($imgFinal)) $mysqli->query("UPDATE products SET products_subimages='" . $mysqli->real_escape_string(json_encode($imgFinal, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)) . "' WHERE products_id=$pid");
        $counters['nWithImg']++; $counters['nSubImgTotal'] += count($imgFinal);

        $variantsCreated = 0;
        if ($isFamily && count($items) > 1) {
            foreach ($items as $code => $it) {
                $labEs = $labelsEs[$code] ?? ($labelsEn[$code] ?? $code);
                $labEn = $labelsEn[$code] ?? $code;
                $delta = round($it['_PRICE'] - $cheap['_PRICE'], 4); $pf = $delta<0?'-':'+';
                $valueId = findOrCreateOptionValue($mysqli, $labEs, $labEn);
                $qrefv = $mysqli->real_escape_string($code); $qveanv = $mysqli->real_escape_string($it['EAN']);
                if (!$mysqli->query("INSERT INTO products_attributes SET products_id=$pid, options_id=" . VARIANT_OPTION_ID . ", options_values_id=$valueId, options_values_price=" . number_format(abs($delta),4,'.','') . ", price_prefix='$pf', reference='$qrefv', reference_prov='$qrefv', products_attributes_ean='$qveanv', options_values_weight=0, weight_prefix='+'")) throw new Exception("attr: ".$mysqli->error);
                $paId = (int) $mysqli->insert_id; $variantsCreated++;
                $g1d = round($it['_G1'] - $cheap['_G1'], 4); $g1p = $g1d<0?'-':'+';
                if (!$mysqli->query("INSERT INTO products_attributes_groups (products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id, options_values_weight, weight_prefix) VALUES ($paId, " . G1_GROUP_ID . ", " . number_format(abs($g1d),4,'.','') . ", '$g1p', $pid, 0, '+')")) throw new Exception("attr_groups: ".$mysqli->error);
            }
            if ($variantsCreated > 0) $counters['nWithVar']++;
        }
        $mysqli->commit();
        logMsg(sprintf("OK %s pid=%d code=%s%s price=%.2f cost=%.2f g1=%.2f imgs=%d subcat=%d",
            $isFamily?'FAMILIA':'SUELTO', $pid, $cheapestCode, $isFamily?" [{$variantsCreated}v]":'',
            $cheap['_PRICE'], $cheap['_COST'], $cheap['_G1'], 1+count($imgFinal), $catTarget));
        return $pid;
    } catch (Exception $e) {
        $mysqli->rollback(); $counters['errors']++;
        foreach ($tmpFiles as $t) { if (file_exists($t)) @unlink($t); }
        logMsg("ERROR $cheapestCode: " . $e->getMessage());
        return false;
    }
}

function computeLabels(array $items) {
    $common = rtrim(longestCommonPrefix(array_map(fn($r)=>$r['NAME'],$items)), " -–·,");
    $strip = function($n,$c){ if($c===''||mb_strpos($n,$c)!==0)return ''; $r=ltrim(trim(mb_substr($n,mb_strlen($c,'UTF-8'),null,'UTF-8'))," -–·,;"); return mb_strlen($r,'UTF-8')<=64?$r:''; };
    $sigs=[]; foreach($items as $c=>$it){ $sigs[$c]=['measure'=>normMeasure(extractMeasure($it['NAME'])),'lcp'=>normMeasure($strip($it['NAME'],$common))]; }
    $labels=[];
    foreach(['measure','lcp'] as $sig){ $vals=array_map(fn($x)=>$x[$sig],$sigs); $ne=array_filter($vals,fn($v)=>$v!==''); if(count($ne)===count($vals)&&count(array_unique($ne))===count($vals)){$labels=$vals;break;} }
    foreach($items as $c=>$it){ if(empty($labels[$c]))$labels[$c]=$c; $labels[$c]=mb_substr($labels[$c],0,64,'UTF-8'); }
    return $labels;
}
function translateLabel($en, &$cache, $skip) {
    if ($skip || $en==='') return $en;
    if (!preg_match('/[A-Za-zÁÉÍÓÚÑáéíóúñ]{3,}/u',$en)) return $en;
    if (isset($cache[$en])) return $cache[$en];
    $t = llmCall(LLM_PROMPT_NAME, $en, 80);
    return $cache[$en] = ($t!=='') ? mb_substr(trim($t),0,64,'UTF-8') : $en;
}

// ---- 1) Familias ----
foreach ($families as $famKey => $items) {
    if ($max > 0 && $nInserted >= $max) { logMsg("Límite max=$max."); break; }
    uasort($items, fn($a,$b)=>$a['_PRICE']<=>$b['_PRICE']);
    $cheap = $items[array_key_first($items)];
    $mfgId = lalResolveManufacturer($mysqli, $cheap['GROUP'], $dryRun, $mfgCache, $catCreatedLog);
    $subcatName = groupLabel($cheap['GROUP']);
    if ($dryRun) {
        $nInserted++; $nFam++;
        if ($nFam <= 15) logMsg(sprintf("  WOULD FAMILIA key=%s (%dv) price=%.2f cost=%.2f g1=%.2f grupo=%s name='%s'", $famKey, count($items), $cheap['_PRICE'], $cheap['_COST'], $cheap['_G1'], $subcatName, mb_substr($cheap['NAME'],0,45,'UTF-8')));
        continue;
    }
    $subcatId = getOrCreateSubcategory($mysqli, $subcatName, $parentCatId, false, $subcatCache, $catCreatedLog);
    // Etiquetas de variante: nativas en ES (del nombre ES); EN = traducción.
    $labelsEs = computeLabels($items);
    $labelsEn = [];
    foreach ($labelsEs as $vc => $le) $labelsEn[$vc] = translateLabel($le, $labelTransCache, $skipTranslation);
    [$nameEn, $descEn, $nameEs, $descEs] = buildContent($cheap, $skipTranslation, $translateFail, $formatFail);
    $pid = insertProduct($mysqli, $items, true, $mfgId, $parentCatId, $subcatId, $nameEn, $descEn, $nameEs, $descEs, $counters, $labelsEs, $labelsEn);
    if ($pid) { $nInserted++; $nFam++; }
}

// ---- 2) Sueltos ----
foreach ($standalone as $code => $row) {
    if ($max > 0 && $nInserted >= $max) { logMsg("Límite max=$max."); break; }
    $mfgId = lalResolveManufacturer($mysqli, $row['GROUP'], $dryRun, $mfgCache, $catCreatedLog);
    $subcatName = groupLabel($row['GROUP']);
    if ($dryRun) {
        $nInserted++; $nStd++;
        if ($nStd <= 12) logMsg(sprintf("  WOULD SUELTO code=%s price=%.2f cost=%.2f g1=%.2f grupo=%s name='%s'", $code, $row['_PRICE'], $row['_COST'], $row['_G1'], $subcatName, mb_substr($row['NAME'],0,50,'UTF-8')));
        continue;
    }
    $subcatId = getOrCreateSubcategory($mysqli, $subcatName, $parentCatId, false, $subcatCache, $catCreatedLog);
    [$nameEn, $descEn, $nameEs, $descEs] = buildContent($row, $skipTranslation, $translateFail, $formatFail);
    $pid = insertProduct($mysqli, [$code => $row], false, $mfgId, $parentCatId, $subcatId, $nameEn, $descEn, $nameEs, $descEs, $counters);
    if ($pid) { $nInserted++; $nStd++; }
}

logMsg("==================== RESUMEN ====================");
logMsg("Insertados: $nInserted (familias=$nFam sueltos=$nStd)");
logMsg("Con imagen: {$counters['nWithImg']} (sub: {$counters['nSubImgTotal']}) | fallos img: {$counters['imgFail']} | skip sin img: {$counters['skippedNoImg']}");
logMsg("Familias con variantes: {$counters['nWithVar']} | Traducciones fallidas: $translateFail | maquetados fallidos: $formatFail | Errores INSERT: {$counters['errors']}");
if (!empty($catCreatedLog)) { logMsg(($dryRun?"Se crearían":"Creados").": ".count($catCreatedLog)); foreach (array_slice($catCreatedLog,0,30) as $v) logMsg("  · $v"); }

end_action:
?>
    </div>
    <p style="margin-top:15px;"><a href="<?php echo tep_href_link('import-lalizas-altas.php'); ?>" class="xbutton small hv9">← Volver</a></p>
<?php else: ?>
    <h2>Importador Lalizas (altas)</h2>
    <?php $xlsx = findNewestXlsx(LALIZAS_DIR); if (!$xlsx) echo '<p style="color:red;">No hay xlsx en ' . LALIZAS_DIR . '</p>'; else echo '<p style="color:#666;font-size:13px;">xlsx: <code>' . htmlspecialchars(basename($xlsx)) . '</code> | XML media: <code>' . (file_exists(XML_PATH)?basename(XML_PATH):'NO EXISTE') . '</code></p>'; ?>
    <p>
        Maestro = hoja <code>precios</code> del xlsx (Ref, nombre, Item Group=marca, EAN, <strong>F=coste neto</strong>, <strong>G=PVP sin IVA</strong>).
        Imagen desde <code>lalizas.xml</code> (por EAN). Descripción: del XML (ES) o, si falta, del B2B/webs de marca (<code>b2b_desc.json</code>, EN). Crea productos en <strong><?php echo PARENT_CATEGORY_NAME_ES; ?></strong> con subcategorías por marca.
    </p>
    <p style="background:#fffbe6;border:1px solid #ffd700;padding:10px;border-radius:4px;font-size:13px;">
        <strong>Solo se importa si tiene imagen (XML) Y descripción (XML o B2B)</strong>. Skip si Ref o EAN ya existen en BD.<br>
        <strong>Precio</strong>: coste=F, PVP=roundToNickel(G), G1=tiers por margen + piso ×<?php echo G1_FLOOR_FACTOR; ?>.<br>
        <strong>Idiomas</strong>: nombre siempre ES (xlsx)→EN. Descripción XML es ES→EN; descripción B2B es EN→ES. Maquetado en ambos idiomas vía LLM.
    </p>
    <form method="get" style="background:#f5f5f5;padding:15px;border-radius:5px;">
        <p>
            <strong>Marca (Item Group)</strong>:
            <select name="brand" style="min-width:280px;">
                <option value="all"<?php echo $selectedBrand==='all'?' selected':''; ?>>— Todas las marcas —</option>
                <?php
                if ($xlsx) {
                    foreach (listGroups(loadXlsxRows($xlsx)) as $gname => $gcnt) {
                        $sel = ($selectedBrand === $gname) ? ' selected' : '';
                        echo '<option value="' . htmlspecialchars($gname) . '"' . $sel . '>' . htmlspecialchars($gname) . ' (' . $gcnt . ')</option>';
                    }
                }
                ?>
            </select>
        </p>
        <p><strong>Codes específicos</strong> (Ref o EAN, separados por coma/espacio; ignoran el filtro de marca):<br>
            <textarea name="codes" rows="2" style="width:100%;font-family:monospace;"><?php echo htmlspecialchars($onlyCodesRaw); ?></textarea></p>
        <p><label><input type="checkbox" name="dry_run" value="1" checked> Dry-run (sin cambios)</label></p>
        <p><label><input type="checkbox" name="skip_translation" value="1"> Saltar traducción/maquetado LLM (EN = ES)</label></p>
        <p><label><input type="checkbox" name="group_variants" value="1"> Agrupar variantes (LCP estricto) — <strong>⚠ opcional; por defecto cada SKU suelto</strong></label></p>
        <p>Inserts máximos (0 = sin límite): <input type="number" name="max" value="10" min="0" style="width:80px;"></p>
        <button type="submit" name="action" value="dry_run" class="xbutton small hv9">Dry-run</button>
        <button type="submit" name="action" value="execute" class="xbutton small hv9 verde" onclick="return confirm('¿Ejecutar de verdad? Insertará productos en la BD.');">Ejecutar</button>
    </form>
    <p style="margin-top:20px;color:#888;font-size:12px;">
        - Coste = col F (precio neto sin IVA). PVP = roundToNickel(col G). G1 = tiers margen + piso cost×<?php echo G1_FLOOR_FACTOR; ?>.<br>
        - Fabricante por Item Group (Lalizas 3 / Nuova Rade 96 / Lofrans 16 / Ocean 246 / MaxPower 376; Selection Items + Commercial Only → Lalizas; Arimar se crea).<br>
        - Subcategorías por marca bajo <?php echo PARENT_CATEGORY_NAME_ES; ?>. Imagen (XML) + descripción (XML o B2B) obligatorias.<br>
        - EAN del feed (col D). Stock no se toca (qty 0, status 2). Atributos opcionales y estrictos.<br>
    </p>
<?php endif; ?>
</div>
</td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
