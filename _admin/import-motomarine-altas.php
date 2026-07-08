<?php
require 'includes/application_top.php';
require_once DIR_FS_DOCUMENT_ROOT . 'includes/vendor/autoload.php';
require_once dirname(__FILE__) . '/includes/mb_reformat_helpers.php';

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);

/* ============================ CONFIG ============================ */
const MM_DIR        = '/home/francobordo/public_html/import/Motomarine/';
const CACHE_DIR     = '/home/francobordo/public_html/import/Motomarine/cache/';
const PAGES_DIR     = '/home/francobordo/public_html/import/Motomarine/cache/pages/';
const PARSED_PATH   = '/home/francobordo/public_html/import/Motomarine/cache/parsed.json';
const URLMAP_PATH   = '/home/francobordo/public_html/import/Motomarine/cache/url_by_code.json';
const STATE_PATH    = '/home/francobordo/public_html/import/Motomarine/cache/state.json';
define('IMG_ABS_DIR', dirname(dirname(__FILE__)) . '/images/productos/');

const BASE_URL = 'https://www.motomarine.it';
const UA       = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

const PARENT_CATEGORY_NAME_ES  = 'Motomarine Nuevos';
const PARENT_CATEGORY_NAME_EN  = 'Motomarine New';
const PARENT_CATEGORY_NAME_KEY = 'Motomarine Nuevos';
const FALLBACK_SUBCAT          = 'VARIOS';

const TAX_CLASS_IVA21   = 1;
const LANG_ID_ES        = 3;       // traducción
const LANG_ID_EN        = 1;       // idioma nativo de la web
const G1_GROUP_ID       = 1;
const G1_FLOOR_FACTOR   = 1.10;
const IMG_HTTP_TIMEOUT  = 15;
const IMG_MIN_BYTES     = 2048;
const MAX_SUBIMAGES     = 6;
const ORIGIN_FLAG       = 'motomarine';
const VARIANT_OPTION_ID = 3;       // "Modelo"
const VAT_RATE_ES       = 0.21;
const VAT_RATE_IT       = 0.22;    // prezzo consigliato IVA inclusa italiana
const MARKUP_FALLBACK   = 1.6;     // markup sobre coste cuando falta PVP o el PVP es por-unidad (col J=MT) y queda < coste
const EAN_INTERNAL_PREFIX = 28;    // pid-based, sin colisión con otros importadores

const CRAWL_CHUNK    = 500;        // códigos procesados por click en build_cache
const CRAWL_PARALLEL = 20;

// Marcas a excluir (ya gestionadas con importador/precio propio). Comparación sobre slug normalizado.
const EXCLUDED_BRANDS = ['garmin' => 1, 'vetus' => 1, 'marine-business' => 1, 'marinebusiness' => 1];

// Marcas conocidas de Motomarine (página /en/brands). El logo /img_site/marchi/* viene con ruido
// ("vetus-logo", "logo-quick-rosso", "jp-spxflow-lockup50-2c"…) → se casa contra esta lista para sacar la marca real.
const BRAND_KNOWN = ['3c','3m','albatross','ancor','ankerplex','antal','aquapac','astel-marine','attwood','autonautic','bamar','barr','base','bennet','bep','blue-wave','boss-marine','bowmann','bucchi','can','can-sb','castro','cem','ceredi','cfg','clarion','cobra','crc','dhr','dolphin','dometic','douglas','elica-alice','enrico-polipodio','euromeci','eurovinil','fastmount','fender-design','fendress','fidlock','fitt','foresti-e-suardi','forma','fusion','garmin','glomex','griffin','guidi','helly-hansen','hermann-sprenger','hoses-technology','icey-tek','icom','iosso','jabsco','jobe','kodak','lahnakoski','liros','loxx','mac','majoni','marco','marine-business','martyr','maucour','mcgard','mercury','motomarine','musto','naster','nat','navishell','nikon','ocean','orca-bay','plastimo','polyform','pvs','quick','quicksilver','racor','raymarine','riviera','roca','rocna','rule','sacs','savoretti-armando','schenker','scoprega','sea-flo','separ','sidermarine','sika','silpar-tk','silwy','solas','sole-advance','southco','spade','spx-flow','star-brite','sting-ray','techimpex-marine-cookers','tecma','tecnoseal','tersma','tessilmare','tiki-yachting','torggler','tor-marine','trim-lok','uflex','ultraflex','ultramarine','unimer','varta','vdo','veleria-san-giorgio','veratron','vetus','vitrifrigo','volpi-tecno-energia','vulcan','wd-40','wichard','yanmar','ykk','kong'];

// Overrides de nombre legible para marcas con grafías raras (slug → display). El resto: title-case del slug.
const BRAND_DISPLAY = [
    '3m' => '3M', 'wd-40' => 'WD-40', 'bep' => 'BEP', 'spx-flow' => 'SPX Flow', 'vdo' => 'VDO',
    'dhr' => 'DHR', 'cem' => 'CEM', 'cfg' => 'CFG', 'pvs' => 'PVS', 'ykk' => 'YKK', 'can' => 'CAN',
    'can-sb' => 'CAN-SB', 'omc' => 'OMC', 'hermann-sprenger' => 'Hermann Sprenger',
    'volpi-tecno-energia' => 'Volpi Tecno Energia', 'silpar-tk' => 'Silpar TK', 'tor-marine' => 'Tor Marine',
    'icey-tek' => 'Icey-Tek', 'orca-bay' => 'Orca Bay', 'helly-hansen' => 'Helly Hansen',
    'sting-ray' => 'Sting-Ray', 'blue-wave' => 'Blue Wave', 'foresti-e-suardi' => 'Foresti e Suardi',
    'veleria-san-giorgio' => 'Veleria San Giorgio', 'savoretti-armando' => 'Savoretti Armando',
    'enrico-polipodio' => 'Enrico Polipodio', 'techimpex-marine-cookers' => 'Techimpex',
    'hoses-technology' => 'Hoses Technology', 'star-brite' => 'Star Brite', 'fender-design' => 'Fender Design',
];

const LLM_URL    = 'http://217.127.199.171:28001/v1/chat/completions';
const LLM_MODEL  = 'qwen36-sakamaki-nvfp4';
// Dirección EN→ES (la web está en inglés).
const LLM_GLOSSARY = 'Contexto: equipamiento, recambios y accesorios para embarcaciones de recreo y profesionales (náutica). Glosario orientativo EN→ES (no exhaustivo): rope/line=cabo, shackle=grillete, cleat=cornamusa, fairlead/chock=pasacable, winch=winche, windlass=molinete, thruster=hélice de maniobra, bilge=sentina, hatch=tambucho/escotilla, fender=defensa, mooring=amarre, anchor=ancla, chain=cadena, hull=casco, deck=cubierta, rudder=timón, through-hull=pasacascos, seacock=grifo de fondo, stainless steel=acero inoxidable, galvanized=galvanizado, outboard=fueraborda, stern drive=cola, fuel tank=depósito de combustible, hose=manguera, bracket=soporte. NO traduzcas como términos de otros dominios (ej. póker, informática); siempre sentido náutico.';
const LLM_PROMPT_NAME = 'Eres un traductor profesional de inglés a español de España, especializado en productos náuticos, marinos, ferretería y seguridad. Usa terminología técnica náutica precisa. ' . LLM_GLOSSARY . ' Conserva marcas, modelos, códigos alfanuméricos y unidades (mm, cm, m, kg, V, W, L, Ø) sin traducir. Texto plano. Responde SOLO con la traducción, sin comentarios ni comillas.';
const LLM_PROMPT_DESC = 'Eres un traductor profesional de inglés a español de España, especializado en productos náuticos, marinos, ferretería y seguridad. Usa terminología técnica náutica precisa. ' . LLM_GLOSSARY . ' Conserva marcas, modelos, códigos alfanuméricos y unidades sin traducir. Conserva las etiquetas <br>/<p>/<ul>/<li>/<strong> si las hay. Responde SOLO con la traducción, sin comentarios.';
const LLM_FORMAT_PROMPT_ES = "Eres un experto en maquetar fichas de producto náuticas/marinas. Recibes una descripción comercial en ESPAÑOL y la transformas en HTML legible.\n\nREGLAS:\n1. PÁRRAFO INTRODUCTORIO: el primer <p> es la frase descriptiva más completa.\n2. AGRUPACIÓN: si >6 features, secciones <h3>...</h3> + <ul><li>...</li></ul>; si <6, una sola lista.\n3. <strong>: en CADA <li>, concepto clave (1-4 palabras) en <strong> seguido de dos puntos. NO inventes.\n4. PRESERVA <a href> y <sup>.\n5. NO resumas/parafrasees/inventes: conserva TODO el texto. Solo añades estructura.\n6. Si es 1-2 frases cortas, devuelve <p>texto</p> sin lista.\n7. Permitidas: <h3>,<h4>,<p>,<ul>,<li>,<strong>,<a>,<sup>. Prohibidas: <h1>,<h2>,<br>,<div>,<span>.\n8. Salida: SOLO el HTML, sin markdown ni comentarios.";
const LLM_FORMAT_PROMPT_EN = "You are an expert in formatting nautical/marine product datasheets. You receive a commercial description in ENGLISH and transform it into clean HTML.\n\nRULES:\n1. INTRO PARAGRAPH: first <p> is the most complete descriptive sentence.\n2. GROUPING: if >6 features, <h3>...</h3> + <ul><li>...</li></ul>; if <6, one single list.\n3. <strong>: in EACH <li>, key concept (1-4 words) in <strong> + colon. DO NOT invent.\n4. PRESERVE <a href> and <sup>.\n5. DO NOT summarize/paraphrase/invent: keep ALL text. Only add structure.\n6. If 1-2 short sentences, return <p>text</p> without list.\n7. Allowed: <h3>,<h4>,<p>,<ul>,<li>,<strong>,<a>,<sup>. Forbidden: <h1>,<h2>,<br>,<div>,<span>.\n8. Output: ONLY the HTML, no markdown, no comments.";

/* ============================ PARAMS ============================ */
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$max    = isset($_POST['max']) ? (int) $_POST['max'] : (isset($_GET['max']) ? (int) $_GET['max'] : 0);
$dryRun = isset($_POST['dry_run']) || isset($_GET['dry_run']) || $action === 'dry_run';
$skipTranslation = isset($_POST['skip_translation']) || isset($_GET['skip_translation']);
$noGroup         = isset($_POST['no_group']) || isset($_GET['no_group']);   // por defecto AGRUPA
$selectedBrand   = trim((string) ($_POST['brand'] ?? $_GET['brand'] ?? 'all'));  // slug de marca o 'all'
$onlyCodesRaw    = trim((string) ($_POST['codes'] ?? $_GET['codes'] ?? ''));
$onlyCodes = [];
if ($onlyCodesRaw !== '') {
    foreach (preg_split('/[\s,;]+/', $onlyCodesRaw) as $c) { $c = trim($c); if ($c !== '') $onlyCodes[strtoupper($c)] = true; }
}

/* ============================ HELPERS ============================ */
function logMsg($msg) {
    echo '<pre style="margin:0;padding:2px 8px;border-bottom:1px solid #eee;white-space:pre-wrap;overflow-wrap:break-word;word-break:break-word;font-family:monospace;font-size:12px;">'
        . htmlspecialchars('[' . date('H:i:s') . '] ' . $msg) . "</pre>";
    @flush();
}
function mmParseNum($v) {
    $v = trim((string) $v);
    if ($v === '' || strtoupper($v) === 'N/A') return null;
    $v = str_replace(',', '.', $v);
    return is_numeric($v) ? (float) $v : null;
}
function mmStrip($s) { return trim(preg_replace('/\s+/u', ' ', preg_replace('/<[^>]*>/', ' ', (string)$s))); }
function cleanHtmlAggressive($html) {
    if ($html === null || $html === '') return '';
    $html = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html);
    $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
    $html = preg_replace('#<\s*br\s*/?\s*>#i', "\n", $html);
    $html = preg_replace('#</\s*(p|div|li|tr|h[1-6]|ul|ol|table|article|section)\s*>#i', "\n", $html);
    $html = preg_replace('#<\s*li\b[^>]*>#i', "- ", $html);
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $out = []; $empty = 0;
    foreach (preg_split("/\r\n|\r|\n/", $text) as $l) {
        $l = trim(preg_replace('/[ \t\x{A0}]+/u', ' ', $l));
        if ($l === '') { if ($empty < 1 && !empty($out)) $out[] = ''; $empty++; continue; }
        $out[] = $l; $empty = 0;
    }
    return trim(implode("\n", $out));
}
function mmSlugify($text, $maxLen = 50) {
    $t = trim((string) $text);
    if (function_exists('iconv')) { $c = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t); if ($c !== false && $c !== '') $t = $c; }
    $t = preg_replace('/[^a-z0-9]+/', '-', strtolower($t));
    $t = trim($t, '-');
    if (strlen($t) > $maxLen) $t = trim(substr($t, 0, $maxLen), '-');
    return $t === '' ? 'producto' : $t;
}
function ean13Checksum($p) { if (strlen($p)!==12||!ctype_digit($p)) return -1; $s=0; for($i=0;$i<12;$i++){$d=(int)$p[$i];$s+=($i%2===0)?$d:$d*3;} return (10-($s%10))%10; }
function isValidEan13($e) { $e=trim((string)$e); if(strlen($e)!==13||!ctype_digit($e))return false; return ean13Checksum(substr($e,0,12))===(int)$e[12]; }
function generateInternalEan13($pid, $prefix) {
    $pp=(int)$prefix; if($pp<20||$pp>28) return ''; if($pid<=0||$pid>9999999999) return '';
    $payload=str_pad((string)$pp,2,'0',STR_PAD_LEFT).str_pad((string)$pid,10,'0',STR_PAD_LEFT);
    $c=ean13Checksum($payload); return $c<0?'':($payload.$c);
}
function roundToNickel($net) {
    $r = round(((float)$net) * (1 + VAT_RATE_ES) * 20) / 20;
    return round($r / (1 + VAT_RATE_ES), 4);
}
function calcG1Price($price, $cost) {
    $price=(float)$price; $cost=(float)$cost; if ($price<=0) return 0.0;
    $mult=0.90;
    if ($cost>0) { $m=($price-$cost)/$price;
        if ($m>=0.45) $mult=0.75; elseif ($m>=0.40) $mult=0.80; elseif ($m>=0.35) $mult=0.82; elseif ($m>=0.30) $mult=0.85; }
    return round(max($price*$mult, $cost*G1_FLOOR_FACTOR), 4);
}
function llmCall($systemPrompt, $userText, $maxTokens = 1500, $maxRetries = 2) {
    if (trim((string) $userText) === '') return '';
    $payload = json_encode(['model'=>LLM_MODEL,
        'messages'=>[['role'=>'system','content'=>$systemPrompt],['role'=>'user','content'=>$userText]],
        'temperature'=>0.2,'max_tokens'=>$maxTokens,'chat_template_kwargs'=>['enable_thinking'=>false]], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    for ($i=0;$i<=$maxRetries;$i++) {
        $ch=curl_init(LLM_URL);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POSTFIELDS=>$payload,CURLOPT_TIMEOUT=>90,CURLOPT_CONNECTTIMEOUT=>10]);
        $resp=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
        if ($resp!==false && $code===200) { $j=json_decode($resp,true); $c=$j['choices'][0]['message']['content']??null; if (is_string($c)&&trim($c)!=='') return trim($c); }
        usleep(400000);
    }
    return '';
}
function formatLooksValid($html, $minLenInput) {
    if (!is_string($html) || trim($html)==='') return false;
    $low=strtolower($html);
    if (strpos($low,'<p>')===false && strpos($low,'<ul>')===false && strpos($low,'<h3>')===false) return false;
    $plain=mb_strlen(trim(strip_tags($html)),'UTF-8');
    if ($minLenInput>200 && $plain<$minLenInput*0.4) return false;
    return true;
}
function downloadImage($url, $destAbs) {
    $url=trim((string)$url); if ($url==='' || strpos($url,' ')!==false) return false;
    if (!preg_match('#^https?://#i',$url)) return false;
    $ch=curl_init($url); $fp=fopen($destAbs,'wb'); if (!$fp) return false;
    curl_setopt_array($ch,[CURLOPT_FILE=>$fp,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>IMG_HTTP_TIMEOUT,CURLOPT_CONNECTTIMEOUT=>5,
        CURLOPT_USERAGENT=>UA,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_HTTPHEADER=>['Accept: image/avif,image/webp,image/*,*/*;q=0.8']]);
    $ok=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); fclose($fp);
    $ok=$ok && $code===200 && filesize($destAbs)>=IMG_MIN_BYTES;
    if (!$ok) @unlink($destAbs);
    return $ok;
}
function downloadImagesToTmp(array $urls, $maxImages) {
    $seen=[]; $tmp=[];
    foreach ($urls as $url) {
        if (count($tmp)>=$maxImages) break;
        $url=trim((string)$url); if ($url===''||isset($seen[$url])) continue; $seen[$url]=true;
        $abs=IMG_ABS_DIR.'mm-tmp-'.uniqid('',true).'.jpg';
        if (downloadImage($url,$abs)) $tmp[]=$abs;
    }
    return $tmp;
}
function longestCommonPrefix(array $strs) {
    if (empty($strs)) return ''; $strs=array_values($strs); $p=$strs[0]; $pl=mb_strlen($p,'UTF-8');
    foreach ($strs as $s) { while ($pl>0 && mb_substr($s,0,$pl,'UTF-8')!==$p){$pl--;$p=mb_substr($p,0,$pl,'UTF-8');} if($pl===0)return ''; }
    return $p;
}
function normMeasure($s) {
    $s=trim((string)$s); if ($s==='') return '';
    $s=preg_replace_callback('/(\d+(?:[.,]\d+)?)\s*(mm|cm|m|kg|g|lt|l|ml|in|inch|"|hp|w|v|a|ah|hz|°|oz|btu)\b/iu', fn($m)=>$m[1].' '.strtolower($m[2]), $s);
    return trim(preg_replace('/\s+/',' ',$s));
}
function extractMeasure($t) {
    $t=(string)$t;
    if (preg_match('/Ø\s*(\d+(?:[.,]\d+)?)\s*mm(?:\s+(\d+(?:[.,]\d+)?)\s*m)?/iu',$t,$m)) return 'Ø'.$m[1].'mm'.(isset($m[2])?' '.$m[2].'m':'');
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*[xX]\s*(\d+(?:[.,]\d+)?)(?:\s*[xX]\s*(\d+(?:[.,]\d+)?))?/u',$t,$m)) return $m[1].'x'.$m[2].(isset($m[3])?'x'.$m[3]:'');
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*(mm|cm|m|kg|g|lt|l|ml|hp|btu|V|W|A|Ah)\b/iu',$t,$m)) return $m[1].' '.strtolower($m[2]);
    if (preg_match('/\b(N[ºo]?|Talla|Size)\s*\.?\s*(\d+)\b/iu',$t,$m)) return 'Nº '.$m[2];
    return '';
}
function computeLabels(array $items) {
    $common = preg_replace('/[\s\-–·,]+$/u', '', longestCommonPrefix(array_map(fn($r)=>$r['NAME'],$items)));
    $strip = function($n,$c){ if($c===''||mb_strpos($n,$c)!==0)return ''; $r=preg_replace('/^[\s\-–·,;]+/u','',trim(mb_substr($n,mb_strlen($c,'UTF-8'),null,'UTF-8'))); return mb_strlen($r,'UTF-8')<=64?$r:''; };
    $sigs=[]; foreach($items as $c=>$it){ $sigs[$c]=['measure'=>normMeasure(extractMeasure($it['NAME'])),'lcp'=>normMeasure($strip($it['NAME'],$common))]; }
    $labels=[];
    foreach(['measure','lcp'] as $sig){ $vals=array_map(fn($x)=>$x[$sig],$sigs); $ne=array_filter($vals,fn($v)=>$v!==''); if(count($ne)===count($vals)&&count(array_unique($ne))===count($vals)){$labels=$vals;break;} }
    foreach($items as $c=>$it){ if(empty($labels[$c]))$labels[$c]=$c; $labels[$c]=mb_substr($labels[$c],0,64,'UTF-8'); }
    return $labels;
}

/* ===== Marca / manufacturer ===== */
function brandSlug($raw) {
    $s = strtolower(trim((string)$raw));
    $s = preg_replace('/\.(jpg|jpeg|png|gif|webp)$/i','',$s);
    $s = preg_replace('/[^a-z0-9]+/','-', $s);
    return trim($s,'-');
}
function brandDisplay($slug) {
    if (isset(BRAND_DISPLAY[$slug])) return BRAND_DISPLAY[$slug];
    return ucwords(str_replace('-', ' ', $slug));
}
function isExcludedBrand($slug) { return $slug !== '' && isset(EXCLUDED_BRANDS[$slug]); }
/** Normaliza el nombre del logo (/img_site/marchi/<X>) a una marca real, casando contra BRAND_KNOWN.
 *  Idempotente: un slug ya limpio ("rocna") se devuelve igual. Si no casa, limpia el ruido del filename. */
function brandFromLogo($raw) {
    $raw = (string) $raw;
    $t = preg_replace('/[^a-z0-9]+/', '', strtolower(preg_replace('/\.(jpg|jpeg|png|gif|webp|svg)$/i', '', $raw)));
    if ($t === '') return '';
    $tc = str_replace('logo', '', $t);           // quita ruido 'logo'
    $best = ''; $bl = 0;
    foreach (BRAND_KNOWN as $slug) {
        $n = preg_replace('/[^a-z0-9]+/', '', $slug);
        if ($n === '') continue;
        $hit = ($t === $n)
            || (strlen($n) >= 4 && strpos($t, $n) !== false)        // logo contiene la marca
            || (strlen($tc) >= 4 && strpos($n, $tc) !== false);     // token (sin 'logo') es parte de la marca
        if ($hit && strlen($n) > $bl) { $best = $slug; $bl = strlen($n); }
    }
    if ($best !== '') return $best;
    // fallback: filename limpio (sin logo/marchi/dígitos/ruido)
    $f = preg_replace('/[^a-z0-9]+/', ' ', strtolower(preg_replace('/\.(jpg|jpeg|png|gif|webp|svg)$/i', '', $raw)));
    $f = preg_replace('/\b(logo|marchi|rosso|rossa|new|old|lockup\w*|jp)\b|\d+/', '', $f);
    return trim(preg_replace('/\s+/', '-', trim($f)), '-');
}
function resolveManufacturer($mysqli, $slug, $dryRun, &$cache, &$createdLog) {
    if ($slug === '') return 1;   // Motomarine genérico (id 1 = default); se ajusta si hace falta
    if (isset($cache[$slug])) return $cache[$slug];
    $display = brandDisplay($slug);
    $q = $mysqli->real_escape_string($display);
    $r = $mysqli->query("SELECT manufacturers_id FROM manufacturers WHERE UPPER(TRIM(manufacturers_name))=UPPER('$q') LIMIT 1");
    if ($r && $row=$r->fetch_assoc()) { $cache[$slug]=(int)$row['manufacturers_id']; return $cache[$slug]; }
    if ($dryRun) { $cache[$slug]=0; $createdLog[]="manufacturer '$display' (dry-run, se crearía)"; return 0; }
    $mysqli->query("INSERT INTO manufacturers (manufacturers_name, date_added) VALUES ('$q', NOW())");
    $id=(int)$mysqli->insert_id;
    $mysqli->query("INSERT INTO manufacturers_info (manufacturers_id, languages_id, manufacturers_url) VALUES ($id, ".LANG_ID_ES.", '')");
    $mysqli->query("INSERT INTO manufacturers_info (manufacturers_id, languages_id, manufacturers_url) VALUES ($id, ".LANG_ID_EN.", '')");
    $cache[$slug]=$id; $createdLog[]="manufacturer '$display' (id=$id)";
    return $id;
}

/* ===== Categorías ===== */
function ensureParentCategory($mysqli, $dryRun, &$createdLog) {
    $r=$mysqli->query("SELECT c.categories_id FROM categories c INNER JOIN categories_description cd ON cd.categories_id=c.categories_id AND cd.language_id=".LANG_ID_ES." WHERE c.parent_id=0 AND UPPER(TRIM(cd.categories_name))=UPPER('".PARENT_CATEGORY_NAME_KEY."') LIMIT 1");
    if ($r && $row=$r->fetch_assoc()) return (int)$row['categories_id'];
    if ($dryRun) { $createdLog[]="categoría padre '".PARENT_CATEGORY_NAME_ES."' (dry-run)"; return 0; }
    $mysqli->query("INSERT INTO categories (parent_id, sort_order, date_added, last_modified, categories_status) VALUES (0,0,NOW(),NOW(),0)");
    $pid=(int)$mysqli->insert_id;
    $mysqli->query("INSERT INTO categories_description (categories_id, language_id, categories_name) VALUES ($pid, ".LANG_ID_ES.", '".$mysqli->real_escape_string(PARENT_CATEGORY_NAME_ES)."')");
    $mysqli->query("INSERT INTO categories_description (categories_id, language_id, categories_name) VALUES ($pid, ".LANG_ID_EN.", '".$mysqli->real_escape_string(PARENT_CATEGORY_NAME_EN)."')");
    $createdLog[]="categoría padre $pid"; return $pid;
}
function getOrCreateSubcategory($mysqli, $name, $parentId, $dryRun, &$cache, &$createdLog) {
    $nm=trim(preg_replace('/\s+/u',' ',(string)$name)); if ($nm==='') $nm=FALLBACK_SUBCAT;
    $key=mb_strtoupper($nm,'UTF-8');
    if (isset($cache[$key])) return $cache[$key];
    $q=$mysqli->real_escape_string($nm); $parentId=(int)$parentId;
    if ($parentId>0) {
        $r=$mysqli->query("SELECT c.categories_id FROM categories c INNER JOIN categories_description cd ON cd.categories_id=c.categories_id AND cd.language_id=".LANG_ID_ES." WHERE c.parent_id=$parentId AND UPPER(TRIM(cd.categories_name))=UPPER('$q') LIMIT 1");
        if ($r && $row=$r->fetch_assoc()) { $cache[$key]=(int)$row['categories_id']; return $cache[$key]; }
    }
    if ($dryRun) { $cache[$key]=0; $createdLog[]="subcategoría '$nm' (dry-run)"; return 0; }
    $nso=(int)($mysqli->query("SELECT IFNULL(MAX(sort_order),0)+1 nso FROM categories WHERE parent_id=$parentId")->fetch_assoc()['nso'] ?? 1);
    $mysqli->query("INSERT INTO categories (parent_id, sort_order, date_added, last_modified, categories_status) VALUES ($parentId,$nso,NOW(),NOW(),1)");
    $id=(int)$mysqli->insert_id;
    $mysqli->query("INSERT INTO categories_description (categories_id, language_id, categories_name) VALUES ($id, ".LANG_ID_ES.", '$q')");
    $mysqli->query("INSERT INTO categories_description (categories_id, language_id, categories_name) VALUES ($id, ".LANG_ID_EN.", '$q')");
    $cache[$key]=$id; $createdLog[]="subcategoría '$nm' (id=$id) bajo $parentId"; return $id;
}
function findOrCreateOptionValue($mysqli, $nameEs, $nameEn=null) {
    if ($nameEn===null) $nameEn=$nameEs;
    $q=$mysqli->query("SELECT pov.products_options_values_id FROM products_options_values pov INNER JOIN products_options_values_to_products_options p2 ON p2.products_options_values_id=pov.products_options_values_id WHERE p2.products_options_id=".VARIANT_OPTION_ID." AND pov.language_id=".LANG_ID_ES." AND pov.products_options_values_name='".$mysqli->real_escape_string($nameEs)."' LIMIT 1");
    if ($row=$q->fetch_assoc()) return (int)$row['products_options_values_id'];
    $newId=(int)($mysqli->query("SELECT IFNULL(MAX(products_options_values_id),0)+1 n FROM products_options_values")->fetch_assoc()['n']);
    $mysqli->query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($newId, ".LANG_ID_ES.", '".$mysqli->real_escape_string($nameEs)."', '')");
    $mysqli->query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($newId, ".LANG_ID_EN.", '".$mysqli->real_escape_string($nameEn)."', '')");
    $mysqli->query("INSERT INTO products_options_values_to_products_options (products_options_id, products_options_values_id) VALUES (".VARIANT_OPTION_ID.", $newId)");
    return $newId;
}

/* ===== xlsx ===== */
function findNewestXlsx($dir) {
    if (!is_dir($dir)) return null; $best=null;$t=0;
    foreach (scandir($dir) as $f){ if(substr($f,-5)!=='.xlsx'||$f[0]==='~')continue; $m=filemtime($dir.$f); if($m>$t){$t=$m;$best=$dir.$f;} }
    return $best;
}
/** A=code B=name_it H=cost(neto IVA escl) I=PVP(IVA inc IT) K=EAN. */
function loadXlsxRows($file) {
    $reader=\PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file);
    $reader->setReadDataOnly(true);
    $sheet=$reader->load($file)->getSheet(0);
    $hi=$sheet->getHighestRow(); $rows=[];
    for ($r=2;$r<=$hi;$r++) {
        $code=trim((string)$sheet->getCell('A'.$r)->getValue());
        if ($code==='') continue;
        $rows[$code]=[
            'CODE' => $code,
            'NAME_IT' => trim((string)$sheet->getCell('B'.$r)->getValue()),
            'COST' => trim((string)$sheet->getCell('H'.$r)->getValue()),
            'PVP_IT'=> trim((string)$sheet->getCell('I'.$r)->getValue()),
            'EAN'  => trim((string)$sheet->getCell('K'.$r)->getValue()),
        ];
    }
    return $rows;
}

/* ============================ WEB CRAWL / CACHE ============================ */
function cachePagePath($code) {
    $h=md5($code); return PAGES_DIR.substr($h,0,2).'/'.$h.'.html.gz';
}
function cachePageRead($code) {
    $p=cachePagePath($code); if (!file_exists($p)) return null;
    $d=@file_get_contents($p); if ($d===false) return null;
    $u=@gzdecode($d); return $u===false?null:$u;
}
function cachePageWrite($code, $html) {
    $p=cachePagePath($code); $dir=dirname($p); if (!is_dir($dir)) @mkdir($dir,0775,true);
    @file_put_contents($p, gzencode($html,6));
}
/** curl_multi de GETs. $reqs = [key=>url]. Devuelve [key=>[httpcode,body]]. */
function multiGet(array $reqs) {
    $mh=curl_multi_init(); $hs=[]; $res=[];
    foreach ($reqs as $k=>$url) {
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_FOLLOWLOCATION=>1,CURLOPT_TIMEOUT=>25,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_USERAGENT=>UA,CURLOPT_SSL_VERIFYPEER=>0]);
        curl_multi_add_handle($mh,$ch); $hs[$k]=$ch;
    }
    do { $st=curl_multi_exec($mh,$run); if($run)curl_multi_select($mh,1.0); } while ($run && $st==CURLM_OK);
    foreach ($hs as $k=>$ch) { $res[$k]=[curl_getinfo($ch,CURLINFO_HTTP_CODE), curl_multi_getcontent($ch)?:'']; curl_multi_remove_handle($mh,$ch); }
    curl_multi_close($mh); return $res;
}
/** curl_multi de POSTs JSON al buscador. $codes lista. Devuelve [code=>body]. */
function multiSearch(array $codes) {
    $mh=curl_multi_init(); $hs=[]; $res=[];
    foreach ($codes as $code) {
        $ch=curl_init(BASE_URL.'/en/product/search');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_POST=>1,CURLOPT_TIMEOUT=>25,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_USERAGENT=>UA,CURLOPT_SSL_VERIFYPEER=>0,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json; charset=UTF-8'],CURLOPT_POSTFIELDS=>json_encode(['query'=>$code])]);
        curl_multi_add_handle($mh,$ch); $hs[$code]=$ch;
    }
    do { $st=curl_multi_exec($mh,$run); if($run)curl_multi_select($mh,1.0); } while ($run && $st==CURLM_OK);
    foreach ($hs as $code=>$ch) { $res[$code]=curl_multi_getcontent($ch)?:''; curl_multi_remove_handle($mh,$ch); }
    curl_multi_close($mh); return $res;
}
function resolveUrlFromSearch($code, $html) {
    if (preg_match('#href="(/en/product/[^"]*-'.preg_quote($code,'#').')"#',$html,$m)) return BASE_URL.$m[1];
    if (preg_match('#data-codice_prodotto="'.preg_quote($code,'#').'"#',$html) && preg_match('#href="(/en/product/[^"#]+)"#',$html,$m)) return BASE_URL.$m[1];
    return null;
}
/** Parser de ficha (validado). Devuelve registro o null. */
function parseProductPage($html, $code) {
    if (trim($html)==='') return null;
    $name = preg_match('#<h1[^>]*>(.*?)</h1>#is',$html,$m) ? mmStrip($m[1]) : '';
    if ($name==='') return null;
    $brand = preg_match('#/img_site/marchi/([^"\'/]+)\.(?:jpg|jpeg|png|gif|webp)#i',$html,$m) ? brandSlug($m[1]) : '';
    // descripción (content-2)
    $desc='';
    $i=strpos($html,'id="content-2"');
    if ($i!==false) {
        $seg=substr($html,$i,5000);
        if (preg_match('#<h2[^>]*>\s*Description\s*</h2>(.*?)</div>#is',$seg,$mm)) {
            $d=preg_replace('#<p>\s*<p>#i','<p>',$mm[1]); $d=preg_replace('#</p>\s*</p>#i','</p>',$d); $desc=trim($d);
        }
    }
    // breadcrumb
    $cats=[];
    if (preg_match('#breadcrumbs[^>]*>(.*?)</(nav|ol|ul)>#is',$html,$m)) {
        if (preg_match_all('#<(?:a|span|li)[^>]*>(.*?)</(?:a|span|li)>#is',$m[1],$mm)) {
            foreach ($mm[1] as $c){ $c=mmStrip($c); if($c!==''&&stripos($c,'home')===false)$cats[]=$c; }
        }
    }
    if (count($cats)>=1) array_pop($cats);  // último = nombre producto
    // imágenes galería (excluye logos /marchi/), preferir super
    $imgs=[]; $gid='';
    if (preg_match_all('#/img_site/category/[0-9/]+/(?:super|big|med|small)/[^"\'\s]+?\.(?:jpg|jpeg|png|webp)#i',$html,$m)) {
        $bySize=['super'=>[],'big'=>[],'med'=>[],'small'=>[]];
        foreach (array_unique($m[0]) as $path){ if (preg_match('#/(super|big|med|small)/([^/]+)$#i',$path,$x)) $bySize[strtolower($x[1])][$x[2]]=$path; }
        $chosen=$bySize['super']?:($bySize['big']?:($bySize['med']?:$bySize['small']));
        uksort($chosen,function($a,$b){ $na=preg_match('#_(\d+)\.#',$a,$x)?(int)$x[1]:0; $nb=preg_match('#_(\d+)\.#',$b,$y)?(int)$y[1]:0; return $na<=>$nb ?: strcmp($a,$b); });
        foreach ($chosen as $p) $imgs[]=BASE_URL.$p;
        if (preg_match('#/img_site/category/((?:\d/)+)(?:super|big|med|small)/#',$m[0][0],$x)) $gid=ltrim(str_replace('/','',$x[1]),'0');
    }
    if ($gid==='' && preg_match('#/en/product-group/[^"\'#\s>]*?-(\d+)\b#',$html,$m)) $gid=$m[1];
    return [
        'code'=>$code, 'name'=>$name, 'brand'=>$brand, 'group_id'=>$gid,
        'breadcrumb'=>$cats,
        'subcat'=>$cats[1] ?? ($cats[0] ?? ''),       // 2º nivel del breadcrumb (sección)
        'images'=>$imgs, 'desc_en'=>$desc, 'specs'=>mmExtractSpecs($html),
    ];
}
/** Extrae las especificaciones del bloque "Specifications" (id=content-3):
 *  <ul class="list-items"><li><span>etiqueta</span><span>valor</span></li>…</ul>.
 *  Devuelve [['k'=>etiqueta,'v'=>valor], …]. */
function mmExtractSpecs($html) {
    $html = (string) $html;
    if ($html === '') return [];
    if (!preg_match('#<h2[^>]*>\s*Specifications\s*</h2>\s*<ul[^>]*class="list-items"[^>]*>(.*?)</ul>#is', $html, $m)) return [];
    $specs = [];
    if (preg_match_all('#<li[^>]*>\s*<span[^>]*>(.*?)</span>\s*<span[^>]*>(.*?)</span>\s*</li>#is', $m[1], $rows, PREG_SET_ORDER)) {
        foreach ($rows as $r) {
            // mmStrip solo quita tags; hay que DECODIFICAR entidades (la web trae "&lt; 7,5")
            // a texto plano, para que mmSpecsBlock luego codifique UNA sola vez (evita "&amp;lt;").
            $k = html_entity_decode(mmStrip($r[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $v = html_entity_decode(mmStrip($r[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($k !== '' && $v !== '') $specs[] = ['k' => $k, 'v' => $v];
        }
    }
    return $specs;
}

const MM_SPEC_LABELS_ES = [
    'dim mm'=>'Dimensiones mm','dim'=>'Dimensiones','dimensions'=>'Dimensiones','dimension'=>'Dimensión',
    'weight'=>'Peso','weight kg'=>'Peso kg','capacity'=>'Capacidad','voltage'=>'Voltaje','power'=>'Potencia',
    'flow'=>'Caudal','length'=>'Longitud','width'=>'Anchura','height'=>'Altura','depth'=>'Profundidad',
    'diameter'=>'Diámetro','material'=>'Material','colour'=>'Color','color'=>'Color','weight g'=>'Peso g',
];
/** Traduce la etiqueta de una spec EN→ES: glosario → unidad/sigla intacta → LLM (cache) → EN. */
function mmSpecLabelEs($label, $skipTranslation) {
    static $cache = [];
    $key = mb_strtolower(trim($label), 'UTF-8');
    if (isset(MM_SPEC_LABELS_ES[$key])) return MM_SPEC_LABELS_ES[$key];
    // unidad/sigla pura (V, W, A, Ah, kg, mm, L, Hz, "… bar", "… min") → no traducir
    if (preg_match('/^[A-Za-z0-9%°.\/ ]+$/', $label) && !preg_match('/[A-Za-z]{4,}/', $label)) return $label;
    if ($skipTranslation) return $label;
    if (!isset($cache[$key])) { $t = llmCall(LLM_PROMPT_NAME, $label, 40); $cache[$key] = ($t !== '') ? trim($t) : $label; }
    return $cache[$key];
}
/** Bloque HTML "Especificaciones" (mismo estilo bullets que mbReformat). Valores intactos. */
function mmSpecsBlock(array $specs, $lang, $skipTranslation) {
    if (empty($specs)) return '';
    $title = $lang === 'en' ? 'Specifications' : 'Especificaciones';
    $h = "\n<p>&nbsp;</p>\n<p><strong>{$title}</strong></p>\n";
    foreach ($specs as $s) {
        $k = $lang === 'en' ? $s['k'] : mmSpecLabelEs($s['k'], $skipTranslation);
        $h .= '<p>• <strong>' . htmlspecialchars($k) . ':</strong> ' . htmlspecialchars($s['v']) . "</p>\n";
    }
    return rtrim($h);
}

function loadJson($p) { return file_exists($p) ? (json_decode(file_get_contents($p),true) ?: []) : []; }
function saveJson($p, $d) { @file_put_contents($p, json_encode($d, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); }

/** Marcas presentes en la cache (importables: con foto), con marca propagada por grupo, sin excluidas. slug=>count. */
function listBrandsFromCache(array $parsed) {
    $byGid = [];
    foreach ($parsed as $r) { if (!$r) continue; $g=$r['group_id']??''; $b=brandFromLogo($r['brand']??''); if ($g!==''&&$b!=='') $byGid[$g]=$b; }
    $counts = [];
    foreach ($parsed as $r) {
        if (!$r || empty($r['images'])) continue;
        $b=brandFromLogo($r['brand']??''); $g=$r['group_id']??'';
        if ($b===''&&$g!==''&&isset($byGid[$g])) $b=$byGid[$g];
        if ($b==='') $b='(sin marca)';
        if (isExcludedBrand($b)) continue;
        $counts[$b]=($counts[$b]??0)+1;
    }
    uksort($counts, fn($a,$b)=>strcasecmp($a,$b));
    return $counts;
}

/* ============================ ACTIONS ============================ */
$isAction = in_array($action, ['execute','dry_run','build_cache'], true);
if ($isAction) {
    @header('X-Accel-Buffering: no'); @header('Content-Type: text/html; charset=utf-8');
    while (ob_get_level()>0) @ob_end_flush(); @ob_implicit_flush(true);
    if (session_status()===PHP_SESSION_ACTIVE) @session_write_close();
}
?>
<?php require THEME . 'html/header.php'; ?>
<table style="width:100%;"><tr><td>
<div style="padding:20px;">
<?php if ($isAction): ?>
    <h2>Importador Motomarine — <?php echo $action==='build_cache'?'CONSTRUIR CACHE WEB':($dryRun?'DRY-RUN':'EJECUCIÓN REAL'); ?></h2>
    <p><a href="<?php echo tep_href_link('import-motomarine-altas.php'); ?>" class="xbutton small hv9">← Volver</a></p>
    <div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:620px;overflow-y:auto;">
<?php
echo str_pad('<!-- pad -->', 4096) . "\n"; @flush();
if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0775, true);
if (!is_dir(PAGES_DIR)) @mkdir(PAGES_DIR, 0775, true);

$xlsx = findNewestXlsx(MM_DIR);
if (!$xlsx) { logMsg("ERROR: no hay xlsx en ".MM_DIR); goto end_action; }
logMsg("xlsx: ".basename($xlsx)." (".round(filesize($xlsx)/1024)." KB)");
$rows = loadXlsxRows($xlsx);
logMsg("Filas xlsx (con código): ".count($rows));

/* -------- BUILD CACHE -------- */
if ($action === 'build_cache') {
    $urlMap = loadJson(URLMAP_PATH);
    $parsed = loadJson(PARSED_PATH);
    logMsg("Cache actual: ".count($urlMap)." URLs resueltas / ".count($parsed)." fichas parseadas");

    // códigos pendientes de resolver URL
    $codes = array_keys($rows);
    if (!empty($onlyCodes)) $codes = array_values(array_filter($codes, fn($c)=>isset($onlyCodes[strtoupper($c)])));
    $pending = array_values(array_filter($codes, fn($c)=>!isset($parsed[$c]) && !array_key_exists($c,$parsed)));
    logMsg("Pendientes de cachear: ".count($pending)." (procesando hasta ".CRAWL_CHUNK." este click)");
    $chunk = array_slice($pending, 0, CRAWL_CHUNK);
    if (empty($chunk)) { logMsg("✅ Cache COMPLETA. Nada pendiente."); goto end_action; }

    $okUrl=$noUrl=$okParse=$noParse=0;
    foreach (array_chunk($chunk, CRAWL_PARALLEL) as $batch) {
        // 1) resolver URLs faltantes
        $toSearch = array_values(array_filter($batch, fn($c)=>empty($urlMap[$c])));
        if ($toSearch) {
            $sres = multiSearch($toSearch);
            foreach ($sres as $code=>$shtml) { $u=resolveUrlFromSearch($code,$shtml); if ($u){$urlMap[$code]=$u;$okUrl++;}else{$urlMap[$code]='';$noUrl++;} }
        }
        // 2) fetch + parse de los que tienen URL
        $reqs=[]; foreach ($batch as $c){ if (!empty($urlMap[$c])) $reqs[$c]=$urlMap[$c]; }
        if ($reqs) {
            $pres = multiGet($reqs);
            foreach ($pres as $code=>$pp) {
                [$hc,$phtml]=$pp;
                if ($hc===200 && trim($phtml)!=='') {
                    cachePageWrite($code,$phtml);
                    $rec=parseProductPage($phtml,$code);
                    if ($rec){ $parsed[$code]=$rec; $okParse++; } else { $parsed[$code]=null; $noParse++; }
                } else { $parsed[$code]=null; $noParse++; }
            }
        }
        // marcar como procesados los sin URL (parsed=null) para no reintentar infinitamente
        foreach ($batch as $c){ if (empty($urlMap[$c]) && !array_key_exists($c,$parsed)) $parsed[$c]=null; }
        saveJson(URLMAP_PATH,$urlMap); saveJson(PARSED_PATH,$parsed);
        logMsg("  batch: +URL=$okUrl sinURL=$noUrl | +parse=$okParse sinParse=$noParse  (total parsed ".count($parsed).")");
    }
    $remaining = count(array_filter(array_keys($rows), fn($c)=>!array_key_exists($c,$parsed)));
    logMsg("Guardado. Resueltas $okUrl, sin URL $noUrl, parseadas $okParse, sin parse $noParse.");
    logMsg($remaining>0 ? "↻ Quedan ~$remaining por cachear — vuelve a pulsar 'Construir cache'." : "✅ Cache COMPLETA.");
    goto end_action;
}

/* -------- DRY-RUN / EXECUTE -------- */
logMsg("Modo: ".($dryRun?"dry-run":"EXECUTE")." | marca=".($selectedBrand==='all'?'TODAS':$selectedBrand).(!empty($onlyCodes)?" | codes=".count($onlyCodes):"").($noGroup?" | SIN agrupar":" | agrupando por grupo").($skipTranslation?" | sin LLM":""));
$parsed = loadJson(PARSED_PATH);
logMsg("Fichas web cacheadas: ".count(array_filter($parsed)));
if (empty(array_filter($parsed))) { logMsg("⚠ Cache web vacía — ejecuta primero 'Construir cache web'."); }

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_error) { logMsg("ERROR DB: ".$mysqli->connect_error); goto end_action; }
$mysqli->set_charset('utf8');
if (!$dryRun && !is_dir(IMG_ABS_DIR)) @mkdir(IMG_ABS_DIR,0775,true);

$catCreatedLog=[]; $mfgCache=[]; $subcatCache=[];
$parentCatId = ensureParentCategory($mysqli,$dryRun,$catCreatedLog);
logMsg("Categoría padre 'Motomarine Nuevos': id=".($parentCatId?:'(se creará)'));

// dedup BD: código scoped a origin motomarine; EAN global
logMsg("Cargando IDs existentes…");
$existing=[];
foreach (["SELECT LOWER(products_model) m FROM products WHERE products_import_origin LIKE 'motomarine%' AND products_model<>''",
          "SELECT LOWER(reference_prov) m FROM products WHERE products_import_origin LIKE 'motomarine%' AND reference_prov<>''",
          "SELECT LOWER(product_ean) m FROM products WHERE product_ean<>'' AND product_ean IS NOT NULL",
          "SELECT LOWER(products_attributes_ean) m FROM products_attributes WHERE products_attributes_ean<>'' AND products_attributes_ean IS NOT NULL"] as $sql) {
    $r=$mysqli->query($sql); while($row=$r->fetch_assoc()) $existing[$row['m']]=true;
}
// Lista negra de reimportación: trata como "ya existentes" los códigos/EAN de productos borrados a propósito.
require_once dirname(__FILE__) . '/includes/import_blacklist.php';
$existing += fb_blacklist_keys();
logMsg("  → ".count($existing)." refs en BD");

// ---- candidatos ----
$cand=[];
$cNoWeb=$cNoImg=$cExcl=$cExist=$cBadPrice=$cCodes=$cPriceFb=$cBrand=0;
foreach ($rows as $code=>$row) {
    if (!empty($onlyCodes) && !isset($onlyCodes[strtoupper($code)]) && !isset($onlyCodes[strtoupper($row['EAN'])])) { $cCodes++; continue; }
    $web = $parsed[$code] ?? null;
    if (!$web) { $cNoWeb++; continue; }
    if (empty($web['images'])) { $cNoImg++; continue; }   // sin foto → saltar
    if (isset($existing[strtolower($code)])) { $cExist++; continue; }
    if ($row['EAN']!=='' && isset($existing[strtolower($row['EAN'])])) { $cExist++; continue; }
    $cost=mmParseNum($row['COST']); $pvpIt=mmParseNum($row['PVP_IT']);
    if ($cost===null||$cost<0) $cost=0.0;
    $retailNet = ($pvpIt!==null && $pvpIt>0) ? $pvpIt/(1+VAT_RATE_IT) : ($cost>0 ? $cost*MARKUP_FALLBACK : 0);
    $price = roundToNickel($retailNet);
    // Guarda de unidades: col I (PVP) puede venir por-unidad (J=MT) mientras el coste es por-bobina (PCK) → precio < coste.
    if ($cost>0 && $price < $cost) { $price = roundToNickel($cost*MARKUP_FALLBACK); $cPriceFb++; }
    if ($price<=0) { $cBadPrice++; continue; }
    $row['_COST']=round($cost,4);
    $row['_PRICE']=$price;
    $row['_G1']=roundToNickel(calcG1Price($price,$cost));
    $row['_WEIGHT']=1.0;
    $row['NAME']=$web['name'];
    $row['_BRAND']=brandFromLogo($web['brand'] ?? '');
    $row['_GID']=$web['group_id'] ?? '';
    $row['_IMGS']=$web['images'];
    $row['_DESC_EN']=cleanHtmlAggressive($web['desc_en'] ?? '');
    $row['_SUBCAT']=$web['subcat'] ?: FALLBACK_SUBCAT;
    // specs: del parsed.json si las trae (crawls nuevos); si no, del HTML cacheado (auto-reparable)
    $row['_SPECS']=$web['specs'] ?? mmExtractSpecs(cachePageRead($code) ?? '');
    $cand[$code]=$row;
}

// propagar marca dentro del grupo (la ficha-líder a veces no muestra logo)
$brandByGid=[];
foreach ($cand as $r){ $g=$r['_GID']; if($g!==''&&$r['_BRAND']!=='') $brandByGid[$g]=$r['_BRAND']; }
foreach ($cand as $code=>$r){ if($r['_BRAND']===''&&$r['_GID']!==''&&isset($brandByGid[$r['_GID']])) $cand[$code]['_BRAND']=$brandByGid[$r['_GID']]; }

// filtrado por marca (TRAS propagar, para que la marca sea fiable): excluidas siempre; marca elegida salvo que se pidan codes.
$wantNone     = ($selectedBrand === '__none__');
$selBrandSlug = $wantNone ? '' : brandSlug($selectedBrand);
foreach ($cand as $code=>$r) {
    if (isExcludedBrand($r['_BRAND'])) { unset($cand[$code]); $cExcl++; continue; }
    if (empty($onlyCodes) && $selectedBrand!=='all') {
        $match = $wantNone ? ($r['_BRAND']==='') : ($r['_BRAND']===$selBrandSlug);
        if (!$match) { unset($cand[$code]); $cBrand++; continue; }
    }
}
logMsg("Candidatos: ".count($cand)." | sin web=$cNoWeb | sin foto=$cNoImg | ya en BD=$cExist | precio=$cBadPrice | precio<coste→markup=$cPriceFb | marca excluida=$cExcl".($selectedBrand!=='all'?" | marca='".$selectedBrand."' (otras=$cBrand)":"").(!empty($onlyCodes)?" | fuera-codes=$cCodes":""));

// ---- agrupar por group_id ----
$families=[]; $standalone=[];
if ($noGroup) {
    $standalone=$cand;
} else {
    $byGid=[];
    foreach ($cand as $code=>$r){ $g=$r['_GID']; if($g==='') $standalone[$code]=$r; else $byGid[$g][$code]=$r; }
    foreach ($byGid as $g=>$items){ if (count($items)>1) $families[$g]=$items; else foreach($items as $c=>$r)$standalone[$c]=$r; }
}
// filtro codes: si un code pertenece a una familia, importa la familia completa
if (!empty($onlyCodes)) {
    $fF=[]; foreach($families as $g=>$it){ foreach($it as $c=>$_){ if(isset($onlyCodes[strtoupper($c)])){$fF[$g]=$it;break;} } }
    $sF=[]; foreach($standalone as $c=>$r){ if(isset($onlyCodes[strtoupper($c)]))$sF[$c]=$r; }
    $families=$fF; $standalone=$sF;
}
logMsg("Tras agrupar: ".count($families)." familias + ".count($standalone)." sueltos");

$nIns=$nFam=$nStd=0; $counters=['imgFail'=>0,'nWithImg'=>0,'nSubImgTotal'=>0,'nWithVar'=>0,'errors'=>0,'skippedNoImg'=>0];
$translateFail=$formatFail=0; $labelTransCache=[];

function buildContent($row, $skipTranslation, &$translateFail, &$formatFail) {
    $nameEn=$row['NAME'];
    $descEnRaw=(string)($row['_DESC_EN'] ?? '');
    // desc mínima si la web no trae descripción (decisión usuario)
    if ($descEnRaw==='') $descEnRaw = $nameEn.'.'.(($row['_SUBCAT']??'')!=='' && $row['_SUBCAT']!==FALLBACK_SUBCAT ? ' '.$row['_SUBCAT'].'.' : '');
    $nameEs=$nameEn; $descEsRaw=$descEnRaw;
    $descEnHtml=nl2br(htmlspecialchars($descEnRaw),false);
    $descEs=$descEnHtml;
    if (!$skipTranslation) {
        $tn=llmCall(LLM_PROMPT_NAME,$nameEn,200); if($tn!=='')$nameEs=$tn; else $translateFail++;
        $td=llmCall(LLM_PROMPT_DESC,$descEnRaw,1500); $descEsRaw=($td!=='')?$td:$descEnRaw; if($td==='')$translateFail++;
        $inEn=mb_strlen(strip_tags($descEnRaw),'UTF-8'); $fen=llmCall(LLM_FORMAT_PROMPT_EN,$descEnRaw,2500);
        $descEnHtml=formatLooksValid($fen,$inEn)?$fen:nl2br(htmlspecialchars($descEnRaw),false); if(!formatLooksValid($fen,$inEn))$formatFail++;
        $inEs=mb_strlen(strip_tags($descEsRaw),'UTF-8'); $fes=llmCall(LLM_FORMAT_PROMPT_ES,$descEsRaw,2500);
        $descEs=formatLooksValid($fes,$inEs)?$fes:nl2br(htmlspecialchars($descEsRaw),false); if(!formatLooksValid($fes,$inEs))$formatFail++;
    }
    // Red de seguridad estética post-LLM (quita ™®©, ALL-CAPS→Title, normaliza espaciados/entidades).
    $descEnHtml = mbReformatDescription($descEnHtml);
    $descEs     = mbReformatDescription($descEs);
    // Bloque "Especificaciones" del content-3 de la web, anexado DESPUÉS del LLM (no se le pasa,
    // para no inflarlo ni que altere medidas). Etiquetas traducidas; valores intactos.
    $specs = $row['_SPECS'] ?? [];
    if (!empty($specs)) {
        $descEnHtml = trim($descEnHtml) . mmSpecsBlock($specs, 'en', $skipTranslation);
        $descEs     = trim($descEs)     . mmSpecsBlock($specs, 'es', $skipTranslation);
    }
    return [$nameEn,$descEnHtml,$nameEs,$descEs];
}

/** Quita la marca de agua "MTM MOTOMARINE" (gris claro, esquina inf-dcha sobre blanco) de una
 *  imagen de producto Motomarine (1500x1080). Blanquea solo gris-claro de baja saturación en la
 *  caja esquina → preserva el producto oscuro/colorido. Idempotente. */
function mmDewatermark($absPath) {
    if (!is_file($absPath)) return;
    $info=@getimagesize($absPath);
    if (!$info || $info[2]!==IMAGETYPE_JPEG) return;
    $W=$info[0]; $H=$info[1];
    $im=@imagecreatefromjpeg($absPath);
    if (!$im) return;
    $x0=(int)floor($W*0.85); $y0=(int)floor($H*0.87);
    $white=imagecolorallocate($im,255,255,255); $changed=0;
    for ($y=$y0;$y<$H;$y++) for ($x=$x0;$x<$W;$x++) {
        $rgb=imagecolorat($im,$x,$y); $r=($rgb>>16)&0xFF; $g=($rgb>>8)&0xFF; $b=$rgb&0xFF;
        $lum=($r+$g+$b)/3; $sat=max($r,$g,$b)-min($r,$g,$b);
        if ($lum>=130 && $sat<=35 && !($r===255&&$g===255&&$b===255)) { imagesetpixel($im,$x,$y,$white); $changed++; }
    }
    if ($changed>0) @imagejpeg($im,$absPath,92);
    imagedestroy($im);
}

function insertProduct($mysqli, $items, $isFamily, $mfgId, $parentCatId, $subcatId, $nameEn, $descEn, $nameEs, $descEs, &$counters, $labelsEs=[], $labelsEn=[]) {
    uasort($items, fn($a,$b)=>$a['_PRICE']<=>$b['_PRICE']);
    $cheapCode=array_key_first($items); $cheap=$items[$cheapCode];
    $imgUrls=$cheap['_IMGS'] ?? [];
    if (empty($imgUrls)) { $counters['skippedNoImg']++; logMsg("SKIP $cheapCode: sin imagen"); return false; }
    if (!is_dir(IMG_ABS_DIR)) @mkdir(IMG_ABS_DIR,0775,true);
    $tmp=downloadImagesToTmp($imgUrls, MAX_SUBIMAGES+1);
    if (empty($tmp)) { $counters['skippedNoImg']++; $counters['imgFail']++; logMsg("SKIP $cheapCode: imagen no descargable"); return false; }

    $mysqli->begin_transaction();
    try {
        $qmodel=$mysqli->real_escape_string($cheapCode); $qean=$mysqli->real_escape_string($cheap['EAN']);
        $sql="INSERT INTO products (products_quantity, check_stock, products_model, products_image, products_price, products_cost, products_date_added, products_weight, products_status, products_tax_class_id, manufacturers_id, product_ean, reference_prov, products_import_origin)
            VALUES (0,0,\"$qmodel\",\"\",".number_format($cheap['_PRICE'],4,'.','').",".number_format($cheap['_COST'],4,'.','').",NOW(),{$cheap['_WEIGHT']},2,".TAX_CLASS_IVA21.",".(int)$mfgId.",\"$qean\",\"$qmodel\",\"".ORIGIN_FLAG."\")";
        if (!$mysqli->query($sql)) throw new Exception("products: ".$mysqli->error);
        $pid=(int)$mysqli->insert_id;

        $qNameEs=$mysqli->real_escape_string($nameEs); $qDescEs=$mysqli->real_escape_string($descEs);
        $qNameEn=$mysqli->real_escape_string($nameEn); $qDescEn=$mysqli->real_escape_string($descEn);
        if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid,".LANG_ID_ES.",\"$qNameEs\",\"$qDescEs\",0)")) throw new Exception("desc ES: ".$mysqli->error);
        if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid,".LANG_ID_EN.",\"$qNameEn\",\"$qDescEn\",0)")) throw new Exception("desc EN: ".$mysqli->error);
        $catTarget=$subcatId>0?$subcatId:$parentCatId;
        if (!$mysqli->query("INSERT INTO products_to_categories (products_id, categories_id) VALUES ($pid,".(int)$catTarget.")")) throw new Exception("p2c: ".$mysqli->error);
        if (!$mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (".G1_GROUP_ID.",$pid,".number_format($cheap['_G1'],4,'.','').",1,1)")) throw new Exception("g1: ".$mysqli->error);

        $slug=mmSlugify($nameEs?:$nameEn); $imgFinal=[];
        foreach ($tmp as $i=>$abs){ $suf=($i===0)?'':('-'.($i+1)); $fn=$slug.'-'.$pid.$suf.'.jpg'; if(@rename($abs,IMG_ABS_DIR.$fn)){ mmDewatermark(IMG_ABS_DIR.$fn); $imgFinal[]=$fn; } else @unlink($abs); }
        if (empty($imgFinal)) throw new Exception("rename imágenes falló");
        $main=array_shift($imgFinal);
        $mysqli->query("UPDATE products SET products_image=\"".$mysqli->real_escape_string($main)."\" WHERE products_id=$pid");
        if (!empty($imgFinal)) $mysqli->query("UPDATE products SET products_subimages='".$mysqli->real_escape_string(json_encode($imgFinal,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))."' WHERE products_id=$pid");
        $counters['nWithImg']++; $counters['nSubImgTotal']+=count($imgFinal);

        $vCreated=0;
        if ($isFamily && count($items)>1) {
            foreach ($items as $code=>$it) {
                $labEs=$labelsEs[$code]??($labelsEn[$code]??$code); $labEn=$labelsEn[$code]??$labEs;
                $delta=round($it['_PRICE']-$cheap['_PRICE'],4); $pf=$delta<0?'-':'+';
                $valueId=findOrCreateOptionValue($mysqli,$labEs,$labEn);
                $qref=$mysqli->real_escape_string($code); $qveanv=$mysqli->real_escape_string($it['EAN']);
                if (!$mysqli->query("INSERT INTO products_attributes SET products_id=$pid, options_id=".VARIANT_OPTION_ID.", options_values_id=$valueId, options_values_price=".number_format(abs($delta),4,'.','').", price_prefix='$pf', reference='$qref', reference_prov='$qref', products_attributes_ean='$qveanv', options_values_weight=0, weight_prefix='+'")) throw new Exception("attr: ".$mysqli->error);
                $paId=(int)$mysqli->insert_id; $vCreated++;
                // EAN interno por variante (paid-based, prefijo 28) si el del feed no es válido.
                // El EAN va en CADA variante (no en el master) — convención francobordo.
                if (!isValidEan13($it['EAN'])) {
                    $vEan=generateInternalEan13($paId, EAN_INTERNAL_PREFIX);
                    if ($vEan!=='') $mysqli->query("UPDATE products_attributes SET products_attributes_ean='".$mysqli->real_escape_string($vEan)."' WHERE products_attributes_id=$paId AND (products_attributes_ean IS NULL OR products_attributes_ean='' OR LENGTH(products_attributes_ean)<>13)");
                }
                $g1d=round($it['_G1']-$cheap['_G1'],4); $g1p=$g1d<0?'-':'+';
                if (!$mysqli->query("INSERT INTO products_attributes_groups (products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id, options_values_weight, weight_prefix) VALUES ($paId,".G1_GROUP_ID.",".number_format(abs($g1d),4,'.','').",'$g1p',$pid,0,'+')")) throw new Exception("attr_groups: ".$mysqli->error);
            }
            if ($vCreated>0) $counters['nWithVar']++;
        }
        $mysqli->commit();

        // EAN interno del MASTER (pid-based, prefijo 28) SOLO para sueltos. En productos con
        // variantes el EAN va por variante (arriba) y el master se deja vacío — convención francobordo.
        if ($vCreated===0 && !isValidEan13($cheap['EAN'])) {
            $gen=generateInternalEan13($pid, EAN_INTERNAL_PREFIX);
            if ($gen!=='') $mysqli->query("UPDATE products SET product_ean='".$mysqli->real_escape_string($gen)."' WHERE products_id=$pid AND (product_ean IS NULL OR product_ean='' OR LENGTH(product_ean)<>13)");
        }
        logMsg(sprintf("OK %s pid=%d code=%s%s price=%.2f cost=%.2f g1=%.2f imgs=%d subcat=%d mfg=%d",
            $isFamily?'FAMILIA':'SUELTO',$pid,$cheapCode,$isFamily?" [{$vCreated}v]":'',$cheap['_PRICE'],$cheap['_COST'],$cheap['_G1'],1+count($imgFinal),$catTarget,$mfgId));
        return $pid;
    } catch (Exception $e) {
        $mysqli->rollback(); $counters['errors']++;
        foreach ($tmp as $t){ if(file_exists($t))@unlink($t); }
        logMsg("ERROR $cheapCode: ".$e->getMessage());
        return false;
    }
}

// ---- 1) Familias ----
foreach ($families as $gid=>$items) {
    if ($max>0 && $nIns>=$max) { logMsg("Límite max=$max."); break; }
    uasort($items, fn($a,$b)=>$a['_PRICE']<=>$b['_PRICE']);
    $cheap=$items[array_key_first($items)];
    $brand=$cheap['_BRAND'];
    $subcatName=$cheap['_SUBCAT'];
    if ($dryRun) {
        $nIns++; $nFam++;
        if ($nFam<=15) logMsg(sprintf("  WOULD FAMILIA gid=%s (%dv) price=%.2f cost=%.2f g1=%.2f marca=%s subcat=%s name='%s'",$gid,count($items),$cheap['_PRICE'],$cheap['_COST'],$cheap['_G1'],$brand?:'(?)',$subcatName,mb_substr($cheap['NAME'],0,45,'UTF-8')));
        continue;
    }
    $mfgId=resolveManufacturer($mysqli,$brand,false,$mfgCache,$catCreatedLog);
    $subcatId=getOrCreateSubcategory($mysqli,$subcatName,$parentCatId,false,$subcatCache,$catCreatedLog);
    $labelsEs=computeLabels($items); $labelsEn=$labelsEs;
    [$nameEn,$descEn,$nameEs,$descEs]=buildContent($cheap,$skipTranslation,$translateFail,$formatFail);
    $pid=insertProduct($mysqli,$items,true,$mfgId,$parentCatId,$subcatId,$nameEn,$descEn,$nameEs,$descEs,$counters,$labelsEs,$labelsEn);
    if ($pid){ $nIns++; $nFam++; }
}
// ---- 2) Sueltos ----
foreach ($standalone as $code=>$row) {
    if ($max>0 && $nIns>=$max) { logMsg("Límite max=$max."); break; }
    $brand=$row['_BRAND']; $subcatName=$row['_SUBCAT'];
    if ($dryRun) {
        $nIns++; $nStd++;
        if ($nStd<=15) logMsg(sprintf("  WOULD SUELTO code=%s price=%.2f cost=%.2f g1=%.2f marca=%s subcat=%s name='%s'",$code,$row['_PRICE'],$row['_COST'],$row['_G1'],$brand?:'(?)',$subcatName,mb_substr($row['NAME'],0,50,'UTF-8')));
        continue;
    }
    $mfgId=resolveManufacturer($mysqli,$brand,false,$mfgCache,$catCreatedLog);
    $subcatId=getOrCreateSubcategory($mysqli,$subcatName,$parentCatId,false,$subcatCache,$catCreatedLog);
    [$nameEn,$descEn,$nameEs,$descEs]=buildContent($row,$skipTranslation,$translateFail,$formatFail);
    $pid=insertProduct($mysqli,[$code=>$row],false,$mfgId,$parentCatId,$subcatId,$nameEn,$descEn,$nameEs,$descEs,$counters);
    if ($pid){ $nIns++; $nStd++; }
}

logMsg("==================== RESUMEN ====================");
logMsg("Insertados: $nIns (familias=$nFam sueltos=$nStd)");
logMsg("Con imagen: {$counters['nWithImg']} (sub: {$counters['nSubImgTotal']}) | fallos img: {$counters['imgFail']} | skip sin img: {$counters['skippedNoImg']}");
logMsg("Familias con variantes: {$counters['nWithVar']} | Traduc. fallidas: $translateFail | maquetados fallidos: $formatFail | Errores INSERT: {$counters['errors']}");
if (!empty($catCreatedLog)) { logMsg(($dryRun?"Se crearían":"Creados").": ".count($catCreatedLog)); foreach (array_slice($catCreatedLog,0,40) as $v) logMsg("  · $v"); }

end_action:
?>
    </div>
    <p style="margin-top:15px;"><a href="<?php echo tep_href_link('import-motomarine-altas.php'); ?>" class="xbutton small hv9">← Volver</a></p>
<?php else: ?>
    <h2>Importador Motomarine (altas)</h2>
    <?php $xlsx=findNewestXlsx(MM_DIR); $parsed=loadJson(PARSED_PATH);
        echo '<p style="color:#666;font-size:13px;">xlsx: <code>'.($xlsx?htmlspecialchars(basename($xlsx)):'NO EXISTE').'</code> | fichas web cacheadas: <code>'.count(array_filter($parsed)).'</code> de '.count($parsed).' procesadas</p>'; ?>
    <p>
        xlsx (hoja <code>Worksheet</code>): A=código, B=nombre IT, <strong>H=coste neto</strong>, <strong>I=PVP IVA inc (IT 22%)</strong>, K=EAN.
        Contenido (nombre EN, descripción, categoría, imágenes, marca) del scraping de <code>motomarine.it</code> vía buscador por código.
    </p>
    <p style="background:#fffbe6;border:1px solid #ffd700;padding:10px;border-radius:4px;font-size:13px;">
        <strong>1)</strong> Construye primero la <strong>cache web</strong> (resumable, ~<?php echo CRAWL_CHUNK; ?> códigos/click).
        <strong>2)</strong> Luego Dry-run / Ejecutar.<br>
        <strong>Reglas</strong>: skip si no hay foto. Sin descripción → desc mínima (nombre/categoría). Variantes por grupo del proveedor.
        Marca real (excluidas: garmin, vetus, marine-business). Precio: coste=H, PVP=roundToNickel(I/1.22), G1=tiers+piso ×<?php echo G1_FLOOR_FACTOR; ?>. EAN interno prefijo <?php echo EAN_INTERNAL_PREFIX; ?>.
    </p>
    <form method="get" style="background:#eef;padding:12px;border-radius:5px;margin-bottom:10px;">
        <strong>Paso 1 — Cache web</strong> (puedes filtrar con codes para probar):<br>
        <p><textarea name="codes" rows="2" style="width:100%;font-family:monospace;" placeholder="(opcional) códigos separados por coma/espacio"><?php echo htmlspecialchars($onlyCodesRaw); ?></textarea></p>
        <button type="submit" name="action" value="build_cache" class="xbutton small hv9">Construir cache web</button>
    </form>
    <form method="get" style="background:#f5f5f5;padding:15px;border-radius:5px;">
        <strong>Paso 2 — Importar</strong><br>
        <p>
            <strong>Marca</strong>:
            <select name="brand" style="min-width:300px;">
                <option value="all"<?php echo $selectedBrand==='all'?' selected':''; ?>>— Todas las marcas —</option>
                <?php
                foreach (listBrandsFromCache($parsed) as $bslug => $bcnt) {
                    $val = ($bslug === '(sin marca)') ? '__none__' : $bslug;
                    $sel = ($selectedBrand === $val) ? ' selected' : '';
                    $disp = ($bslug === '(sin marca)') ? '(sin marca)' : brandDisplay($bslug);
                    echo '<option value="' . htmlspecialchars($val) . '"' . $sel . '>' . htmlspecialchars($disp) . ' (' . $bcnt . ')</option>';
                }
                ?>
            </select>
            <span style="color:#888;font-size:12px;">(recuento = importables con foto en la cache; el filtro se ignora si pones codes)</span>
        </p>
        <p><strong>Codes específicos</strong> (código o EAN; si es variante, importa la familia):<br>
            <textarea name="codes" rows="2" style="width:100%;font-family:monospace;"><?php echo htmlspecialchars($onlyCodesRaw); ?></textarea></p>
        <p><label><input type="checkbox" name="dry_run" value="1" checked> Dry-run (sin cambios)</label></p>
        <p><label><input type="checkbox" name="skip_translation" value="1"> Saltar traducción/maquetado LLM (ES = EN)</label></p>
        <p><label><input type="checkbox" name="no_group" value="1"> NO agrupar variantes (cada código suelto)</label></p>
        <p>Inserts máximos (0 = sin límite): <input type="number" name="max" value="10" min="0" style="width:80px;"></p>
        <button type="submit" name="action" value="dry_run" class="xbutton small hv9">Dry-run</button>
        <button type="submit" name="action" value="execute" class="xbutton small hv9 verde" onclick="return confirm('¿Ejecutar de verdad? Insertará productos.');">Ejecutar</button>
    </form>
    <p style="margin-top:20px;color:#888;font-size:12px;">
        - Nombre nativo EN (web) → ES por LLM. Stock NO se toca (qty 0, status 2). Subcategoría = 2º nivel del breadcrumb.<br>
        - Variantes agrupadas por el <code>group_id</code> del proveedor (de la ruta de imagen). Etiqueta por medida del nombre.<br>
    </p>
<?php endif; ?>
</div>
</td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
