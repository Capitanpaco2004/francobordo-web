<?php
require 'includes/application_top.php';

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);

/* =============================================================================
 * Importador de altas Jobe — modelado sobre import-osculati-altas.php
 * Fuente de datos:
 *   1) Feed XML  public_html/import/feed/jobe.xml  → productId, size, stock, ean, retailPrice
 *   2) Portal B2B joe.jobesports.com (login) →
 *        - modal  /api/product/get-product-modal  → nombre, imagen, precio dealer por talla
 *        - ficha  /en/product/<productId>         → descripción, specs, galería de imágenes
 * Agrupación: 1 producto francobordo por productId. Si el productId tiene >=2 tallas
 * distintas en el feed → variantes (opción 267 "Talla"). Si 1 sola → producto suelto.
 * Las imágenes son del productId (compartidas por todas las tallas) → solo van al
 * producto padre, NO por variante (decisión del usuario 2026-05-29).
 * ===========================================================================*/

const JOBE_USER        = 'f.rodriguez@francobordo.com';
const JOBE_PASS        = 'Jobe0908';
const JOBE_BASE        = 'https://joe.jobesports.com';
const JOBE_IMG_BASE    = 'https://www.jobesports.com/uploads/product/'; // CDN público: -big.jpg con User-Agent de navegador (sin cookie)

define('FEED_PATH',     dirname(dirname(__FILE__)) . '/import/feed/jobe.xml');
define('JOBE_TMP_DIR',  dirname(__FILE__) . '/import-jobe/');
define('IMG_ABS_DIR',   dirname(dirname(__FILE__)) . '/images/productos/');

const JOBE_MFG_ID       = 257;   // manufacturer "Jobe"
const TAX_CLASS_IVA21   = 1;
const LANG_ID_ES        = 3;
const LANG_ID_EN        = 1;
const VARIANT_OPTION_ID = 267;   // opción "Talla" (la dominante en los Jobe existentes)
const EAN_INTERNAL_PREFIX = 27;  // prefijo GS1 in-store libre, origen=jobe
const VAT_RATE          = 0.21;
const DEFAULT_WEIGHT_KG = 1.0;   // feedback_default_weight_1: peso por defecto 1, no 0

// LLM local (mismo endpoint que el resto de importadores)
const LLM_URL    = 'http://217.127.199.171:28001/v1/chat/completions';
const LLM_MODEL  = 'qwen36-sakamaki-nvfp4';
const LLM_PROMPT = 'Eres un traductor profesional de inglés a español especializado en productos de deportes acuáticos y náutica (marca Jobe: wakeboard, esquí acuático, paddle surf/SUP, kayak, neoprenos, chalecos salvavidas, arrastrables/towables, motos de agua). Traduce con terminología técnica precisa en español de España. Glosario de referencia (EN↔ES): wakeboard=wakeboard, waterski=esquí acuático, towable=arrastrable, tube=dónut/arrastrable, SUP/paddle board=tabla de paddle surf, paddle=pala/remo, life vest/buoyancy aid=chaleco salvavidas, wetsuit=neopreno, drysuit=traje seco, neoprene=neopreno, rope/line=cabo, handle=mango, bindings=fijaciones, fin=quilla/aleta, leash=invento/correa, kneeboard=kneeboard, harness=arnés, rash guard=camiseta de licra, deck=cubierta, hull=casco, valve=válvula, inflatable=hinchable, pump=hinchador, rocker=rocker, slalom=slalom, beginner=principiante, intermediate=intermedio, advanced=avanzado. Mantén nombres de modelo y cifras/unidades intactos. Mantén etiquetas HTML si las hay. Responde SOLO con la traducción, sin comentarios.';

const LLM_FORMAT_PROMPT = "Eres un experto en maquetar fichas de producto de deportes acuáticos (Jobe). Recibes una descripción YA EN ESPAÑOL (un párrafo introductorio + una lista de características) y la conviertes en HTML limpio, SIN inventar ni añadir información.\n\nREGLAS OBLIGATORIAS:\n1. NO inventes, NO añadas datos, NO parafrasees ni amplíes. Usa EXACTAMENTE la información del texto. Si el texto es corto, la salida es corta.\n2. El párrafo descriptivo va en un <p>. Las características/ventajas van en un <ul><li>...</li></ul>. Si hay un <strong> de concepto al inicio del <li> mejor, pero NO es obligatorio si la frase no tiene una etiqueta clara.\n3. Una característica = un <li>. No partas una frase en varios <li>.\n4. Conserva el sentido náutico/deportivo y la terminología técnica. Mantén cifras, medidas y unidades.\n5. Etiquetas permitidas: <p>, <ul>, <li>, <strong>. Prohibidas: <h1>,<h2>,<h3>,<h4>,<br>,<div>,<span>,<table>.\n6. Salida: SOLO el HTML, sin markdown, sin comentarios, sin tres-back-ticks.";

// Secciones del catálogo Jobe → nombre ES de la subcategoría bajo "Jobe Nuevos"
const JOBE_SECTIONS = [
	'apparel-en-footwear'             => 'Ropa y Calzado',
	'axopar-x-jobe'                   => 'Axopar x Jobe',
	'boat-accessories'                => 'Accesorios de Barco',
	'brabus-x-jobe-shadow-collection' => 'Brabus x Jobe',
	'foils'                           => 'Foils',
	'infinity-yacht-collection'       => 'Infinity Yacht Collection',
	'kayaks'                          => 'Kayaks',
	'kneeboarding'                    => 'Kneeboards',
	'multi-position-boards'           => 'Tablas Multiposición',
	'promotional-products'            => 'Productos Promocionales',
	'pwc-range'                       => 'Gama PWC (Moto de Agua)',
	'rental-commercial-use'           => 'Alquiler / Uso Comercial',
	'seascooters'                     => 'Seascooters',
	'sup'                             => 'Paddle Surf (SUP)',
	'towables'                        => 'Arrastrables (Towables)',
	'vests'                           => 'Chalecos Salvavidas',
	'wakeboarding'                    => 'Wakeboard',
	'wakesurf-en-wakeskate'           => 'Wakesurf y Wakeskate',
	'waterskiing'                     => 'Esquí Acuático',
	'wetsuits'                        => 'Neoprenos',
	'yamaha-collection'               => 'Colección Yamaha',
];
// Secciones "virtuales" (cross-cutting): no son categoría real, solo se usan si no hay otra.
const JOBE_VIRTUAL_SECTIONS = ['new-collection','new-introductions','closeout','demo-deals'];

// PHP 8.5: curl_close() y otras están deprecated; no ensuciar el log de streaming.
error_reporting(error_reporting() & ~E_DEPRECATED);

/* ----------------------------- Parámetros ---------------------------------- */
$action       = $_POST['action'] ?? $_GET['action'] ?? '';
$max          = isset($_POST['max']) ? (int) $_POST['max'] : (isset($_GET['max']) ? (int) $_GET['max'] : 0);
$dryRun       = isset($_POST['dry_run']) || isset($_GET['dry_run']);
$skipFormat   = isset($_POST['skip_format']) || isset($_GET['skip_format']);
$rebuildCatmap= isset($_POST['rebuild_catmap']) || isset($_GET['rebuild_catmap']);
$family       = trim((string) ($_POST['family'] ?? $_GET['family'] ?? ''));   // slug de sección Jobe; '' = todas
$onlyCodesRaw = trim((string) ($_POST['codes'] ?? $_GET['codes'] ?? ''));
$onlyCodes    = [];
if ($onlyCodesRaw !== '') {
	foreach (preg_split('/[\s,;]+/', $onlyCodesRaw) as $c) {
		$c = trim($c);
		if ($c !== '') $onlyCodes[$c] = true;
	}
}
if (!empty($onlyCodes) && $max > 0) $max = 0;

function logMsg($msg) {
	$line = '[' . date('H:i:s') . '] ' . $msg . "\n";
	echo '<pre style="margin:0;padding:2px 8px;border-bottom:1px solid #eee;white-space:pre-wrap;overflow-wrap:break-word;word-break:break-word;font-family:monospace;font-size:12px;">' . htmlspecialchars($line) . '</pre>';
	@flush();
}

/* ----------------------------- EAN helpers --------------------------------- */
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
	if ($pp < 20 || $pp > 29) return '';
	if ($productId <= 0 || $productId > 9999999999) return '';
	$payload = str_pad((string) $pp, 2, '0', STR_PAD_LEFT) . str_pad((string) $productId, 10, '0', STR_PAD_LEFT);
	$check = ean13Checksum($payload);
	return $check < 0 ? '' : ($payload . $check);
}

/* ----------------------------- Precio helpers ------------------------------ */
/** PVP NETO sin redondear a 0.05: se respeta el PVP exacto de Jobe (retail/1.21).
 *  Decisión usuario 2026-05-29: NO redondear, que el precio con IVA sea el de Jobe (ej. 69,99). */
function roundPrice($net) { return round((float) $net, 4); }
/** G1 (Profesionales): tiers por margen real (PVPneto-coste)/PVPneto + piso coste×1.10. */
function g1PriceNet($pvpNet, $cost) {
	$pvpNet = (float) $pvpNet; $cost = (float) $cost;
	if ($pvpNet <= 0) return $pvpNet;
	$margin = ($cost > 0) ? ($pvpNet - $cost) / $pvpNet : 1.0;
	if      ($margin >= 0.45) $f = 0.75;
	elseif  ($margin >= 0.40) $f = 0.80;
	elseif  ($margin >= 0.35) $f = 0.82;
	elseif  ($margin >= 0.30) $f = 0.85;
	else                       $f = 0.90;
	$g1 = $pvpNet * $f;
	$floor = $cost * 1.10;           // piso de seguridad: margen mínimo 10% (feedback_g1_floor)
	if ($g1 < $floor) $g1 = $floor;
	if ($g1 > $pvpNet) $g1 = $pvpNet; // nunca por encima del PVP
	return roundPrice($g1);
}
/** Parsea un importe "259,99" o "136.84" a float. */
function parseMoney($s) {
	$s = trim((string) $s);
	if ($s === '' || $s === '-') return 0.0;
	$s = str_replace(['€', ' ', "\xc2\xa0"], '', $s);
	// formato europeo "1.234,56" → quitar puntos de millar, coma decimal
	if (preg_match('/,\d{1,2}$/', $s)) { $s = str_replace('.', '', $s); $s = str_replace(',', '.', $s); }
	else { $s = str_replace(',', '', $s); }
	return (float) $s;
}

/* ----------------------------- Slug / talla -------------------------------- */
function slugify($s) {
	$s = mb_strtolower($s, 'UTF-8');
	$s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u','à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u','ç'=>'c']);
	$s = preg_replace('/[^a-z0-9]+/', '-', $s);
	$s = trim($s, '-');
	// FB 2026-07-14: cap 50 (como el resto de importadores); con sufijo -<pid>[-<n>].jpg el
	// nombre de imagen queda <=100 y no rompe el import de QFac (columna CFOTO VARCHAR(100)).
	return substr($s, 0, 50);
}
/** Normaliza la talla del feed: "59INCH" → 59", el resto tal cual (S, M, 2XL, 10/12...). */
function normalizeSize($size) {
	$s = trim((string) $size);
	if (preg_match('/^(\d+(?:[.,]\d+)?)\s*INCH$/i', $s, $m)) return str_replace(',', '.', $m[1]) . '"';
	return $s;
}
/** Tallas genéricas que NO cuentan como variante real (unidad de venta). */
function isGenericSize($size) {
	$s = strtoupper(trim((string) $size));
	return in_array($s, ['PCS.', 'PCS', 'PC', 'UNI', 'STK', ''], true);
}

/* ----------------------------- LLM ----------------------------------------- */
function llmCall($systemPrompt, $userText, $maxTokens = 2000, $temp = 0.3) {
	$payload = json_encode([
		'model' => LLM_MODEL,
		'messages' => [
			['role' => 'system', 'content' => $systemPrompt],
			['role' => 'user',   'content' => $userText],
		],
		'chat_template_kwargs' => ['enable_thinking' => false],
		'max_tokens' => $maxTokens, 'temperature' => $temp,
	], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
	for ($i = 0; $i <= 2; $i++) {
		$ch = curl_init(LLM_URL);
		curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$payload, CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>90]);
		$resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($code === 200 && $resp !== false) {
			$d = json_decode($resp, true);
			$out = trim((string) ($d['choices'][0]['message']['content'] ?? ''));
			if ($out !== '') return $out;
		}
		usleep(400000);
	}
	return '';
}
function translateEN($text) {
	$text = trim((string) $text);
	if ($text === '') return '';
	return trim(llmCall(LLM_PROMPT, 'Traduce al español: ' . $text, 2000, 0.3));
}
function llmFormatHtml($html) {
	$html = trim((string) $html);
	if ($html === '') return '';
	$out = llmCall(LLM_FORMAT_PROMPT, $html, 1500, 0.2);
	$out = preg_replace('/^```[a-z]*\s*|\s*```$/i', '', $out);
	return trim($out);
}
function jobeFmtValid($fmt, $orig) {
	if (stripos($fmt, '<p') === false && stripos($fmt, '<li') === false) return false;
	$lf = mb_strlen(strip_tags($fmt), 'UTF-8'); $lo = mb_strlen(strip_tags($orig), 'UTF-8');
	if ($lo > 0 && $lf < $lo * 0.5) return false;
	if ($lo > 0 && $lf > $lo * 2.2) return false;
	return true;
}
function jobeMaquetar($descEs, $skipFormat, &$status) {
	$status = 'none';
	if ($skipFormat) return $descEs;
	if ($descEs === '' || strpos($descEs, '[TRADUCIR]') === 0) return $descEs;
	if (mb_strlen(strip_tags($descEs), 'UTF-8') < 25) return $descEs;
	$fmt = llmFormatHtml($descEs);
	if ($fmt !== '' && jobeFmtValid($fmt, $descEs)) { $status = 'ok'; return $fmt; }
	$status = 'fail';
	return $descEs;
}

/* ----------------------------- HTTP portal --------------------------------- */
$GLOBALS['JOBE_CK'] = JOBE_TMP_DIR . 'cookies.txt';

function jobeCurl($url, $post = null, $extraHeaders = []) {
	$ch = curl_init($url);
	$headers = array_merge(['X-Requested-With: XMLHttpRequest'], $extraHeaders);
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_TIMEOUT        => 60,
		CURLOPT_COOKIEJAR      => $GLOBALS['JOBE_CK'],
		CURLOPT_COOKIEFILE     => $GLOBALS['JOBE_CK'],
		CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36',
		CURLOPT_HTTPHEADER     => $headers,
	]);
	if ($post !== null) {
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($post) ? http_build_query($post) : $post);
	}
	$resp = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	return [$code, $resp];
}
/** Login en el portal. Devuelve true si la cookie de sesión quedó autenticada. */
function jobeLogin() {
	@unlink($GLOBALS['JOBE_CK']);
	jobeCurl(JOBE_BASE . '/'); // obtener PHPSESSID
	jobeCurl(JOBE_BASE . '/process.php?action=login', [
		'username' => JOBE_USER,
		'password' => JOBE_PASS,
	]);
	// comprobar sesión: la home autenticada contiene "Log Out"
	list($code, $html) = jobeCurl(JOBE_BASE . '/en/');
	return ($code === 200 && stripos((string) $html, 'Log Out') !== false);
}

/** Descarga una imagen del portal (con cookie). Devuelve true si OK. */
function jobeDownloadImage($imgUrl, $targetPath) {
	$ch = curl_init($imgUrl);
	$fp = fopen($targetPath, 'wb');
	curl_setopt_array($ch, [
		CURLOPT_FILE       => $fp,
		CURLOPT_TIMEOUT    => 60,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_USERAGENT  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
		CURLOPT_HTTPHEADER => [
			'Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
			'Accept-Language: es-ES,es;q=0.9',
			'Referer: https://www.jobesports.com/',
		],
	]);
	$ok = curl_exec($ch);
	$httpc = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	fclose($fp);
	// El CDN a veces sirve la imagen con Content-Type incorrecto → validar por contenido real.
	if (!$ok || $httpc !== 200 || filesize($targetPath) < 1000 || @getimagesize($targetPath) === false) {
		@unlink($targetPath);
		return false;
	}
	return true;
}

/* ----------------------------- Parseo del portal --------------------------- */
/** Modal del producto: nombre, imagen principal, [size => dealerPriceNeto]. */
function jobeFetchModal($productId) {
	list($code, $html) = jobeCurl(JOBE_BASE . '/api/product/get-product-modal', ['product' => $productId]);
	if ($code !== 200 || $html === false) return null;
	$d = json_decode($html, true);
	$h = is_array($d) ? ($d['html'] ?? '') : $html;
	if ($h === '') return null;
	$name = '';
	if (preg_match('/title-4[^>]*>([^<]+)</', $h, $m)) $name = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
	$mainImg = '';
	if (preg_match('#uploads/product/([A-Za-z0-9._-]+?)-big\.jpg#', $h, $m)) $mainImg = $m[1];
	$dealer = [];
	if (preg_match_all("/data-price='([^']+)'[^>]*data-size='([^']+)'/", $h, $mm, PREG_SET_ORDER)) {
		foreach ($mm as $r) $dealer[trim($r[2])] = (float) $r[1];
	}
	return ['name' => $name, 'main_img' => $mainImg, 'dealer' => $dealer];
}
/** Ficha del producto: descripción (párrafo + specs) y códigos de galería. */
function jobeFetchDetail($productId) {
	list($code, $h) = jobeCurl(JOBE_BASE . '/en/product/' . $productId);
	if ($code !== 200 || !is_string($h)) return null;
	$name = '';
	if (preg_match('/title-1[^>]*>([^<]+)</', $h, $m)) $name = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
	// Párrafo descriptivo: primer div col-6 col-padding-right tras el título
	$para = '';
	if (preg_match('#col-6 col-padding-right">(.*?)</div>#s', $h, $m)) {
		$para = trim(preg_replace('/\s+/', ' ', strip_tags($m[1])));
	}
	// Specs: <ul class="detailSpecs ..."> ... <span>FEATURE</span>
	$specs = [];
	if (preg_match('#<ul class="detailSpecs[^"]*">(.*?)</ul>#s', $h, $m)) {
		if (preg_match_all('#<span>(.*?)</span>#s', $m[1], $mm)) {
			foreach ($mm[1] as $sp) {
				$sp = trim(preg_replace('/\s+/', ' ', strip_tags($sp)));
				if ($sp !== '') $specs[] = $sp;
			}
		}
	}
	// Galería: uploads/product/<code>-big.jpg (incluye principal y -N)
	$gallery = [];
	if (preg_match_all('#uploads/product/([A-Za-z0-9._-]+?)-big\.jpg#', $h, $mm)) {
		foreach (array_unique($mm[1]) as $g) $gallery[] = $g;
	}
	return ['name' => $name, 'para' => $para, 'specs' => $specs, 'gallery' => $gallery, 'section' => jobeSectionSlug($h)];
}

/** Sección (slug top-level) desde el breadcrumb de la ficha. Más fiable que el catmap.
 *  Los crumbs enlazan a /en/catalog/<slug>[/..] o /en/closeout/<slug>. Prioriza sección real. */
function jobeSectionSlug($html) {
	if (!preg_match('#<ul class="breadCrumbs">(.*?)</ul>#s', (string) $html, $m)) return '';
	preg_match_all('#href="/en/(?:catalog|closeout)/([a-z0-9-]+)#', $m[1], $mm);
	$slugs = $mm[1] ?? [];
	foreach ($slugs as $s) if (array_key_exists($s, JOBE_SECTIONS)) return $s;          // sección real conocida
	foreach ($slugs as $s) if (!in_array($s, JOBE_VIRTUAL_SECTIONS, true)) return $s;    // primera no-virtual
	return '';
}

/** Fallback de sección por palabra clave del nombre (ES/EN), alta confianza. '' si no hay match. */
function jobeNameToSection($name) {
	$s = ' ' . mb_strtolower((string) $name, 'UTF-8') . ' ';
	$s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u']);
	$rules = [
		'catalogo'=>'promotional-products','catalog'=>'promotional-products',
		'seascooter'=>'seascooters','kneeboard'=>'kneeboarding',
		'wakesurf'=>'wakesurf-en-wakeskate','wakeskate'=>'wakesurf-en-wakeskate','wakeboard'=>'wakeboarding',
		'waterski'=>'waterskiing','water ski'=>'waterskiing','esqui'=>'waterskiing','slalom'=>'waterskiing',
		'wetsuit'=>'wetsuits','neopren'=>'wetsuits','drysuit'=>'wetsuits','traje seco'=>'wetsuits',
		'kayak'=>'kayaks','towable'=>'towables','arrastrable'=>'towables',
		'foil'=>'foils','drybag'=>'sup','paddle board'=>'sup','paddleboard'=>'sup','paddle surf'=>'sup',
		'chaleco'=>'vests','life vest'=>'vests','buoyancy'=>'vests',
	];
	foreach ($rules as $kw => $slug) if (strpos($s, $kw) !== false) return $slug;
	return '';
}

/* ----------------------------- Mapa de categorías -------------------------- */
/** Construye productId => sección (slug top-level del catálogo). Cachea en gzip. */
function jobeBuildCategoryMap($rebuild) {
	$cacheFile = JOBE_TMP_DIR . 'catmap.json.gz';
	if (!$rebuild && file_exists($cacheFile)) {
		$raw = @gzdecode(file_get_contents($cacheFile));
		$map = $raw ? json_decode($raw, true) : null;
		if (is_array($map) && $map) { logMsg('Mapa de categorías: ' . count($map) . ' productos (cache)'); return $map; }
	}
	logMsg('Construyendo mapa de categorías (crawl del catálogo)...');
	list($c, $home) = jobeCurl(JOBE_BASE . '/en/');
	$urls = [];
	if (preg_match_all('#/en/catalog/[a-z0-9-]+(?:/[a-z0-9-]+)?#i', (string) $home, $mm)) {
		foreach (array_unique($mm[0]) as $u) $urls[] = $u;
	}
	$map = [];            // productId => sección elegida
	$nCats = 0;
	foreach ($urls as $url) {
		$parts = explode('/', trim($url, '/'));         // en, catalog, <sec>, [<sub>]
		$section = $parts[2] ?? '';
		if ($section === '') continue;
		list($cc, $page) = jobeCurl(JOBE_BASE . $url);
		if ($cc !== 200 || !is_string($page)) continue;
		if (!preg_match('/value="(\d+)"\s+id="selected_category_id"/', $page, $m)) continue;
		$catId = (int) $m[1];
		list($pc, $pj) = jobeCurl(JOBE_BASE . '/api/product/get-products', [
			'category' => $catId, 'priceMin' => 0, 'priceMax' => 1000000, 'search' => '', 'spareparts' => 0,
		]);
		$d = json_decode((string) $pj, true);
		$ph = is_array($d) ? ($d['products'] ?? '') : '';
		if ($ph === '') continue;
		$nCats++;
		if (preg_match_all('/Product number:\s*(\d+)/', $ph, $mm)) {
			$isVirtual = in_array($section, JOBE_VIRTUAL_SECTIONS, true);
			foreach ($mm[1] as $pid) {
				// preferir sección real sobre virtual; no pisar una real ya asignada
				if (!isset($map[$pid])) { $map[$pid] = $section; }
				elseif (!$isVirtual && in_array($map[$pid], JOBE_VIRTUAL_SECTIONS, true)) { $map[$pid] = $section; }
			}
		}
	}
	logMsg('  → ' . $nCats . ' categorías crawleadas, ' . count($map) . ' productos mapeados');
	@file_put_contents($cacheFile, gzencode(json_encode($map, JSON_UNESCAPED_UNICODE), 6));
	return $map;
}

/* ----------------------------- BD: categorías ------------------------------ */
function findOrCreateCategory($nameEs, $nameEn, $parentId, $grandparentId) {
	$nameEsSafe = tep_db_input($nameEs);
	$q = tep_db_query("SELECT c.categories_id FROM categories c
		INNER JOIN categories_description cd ON cd.categories_id=c.categories_id AND cd.language_id=" . LANG_ID_ES . "
		WHERE c.parent_id=" . (int) $parentId . " AND cd.categories_name='" . $nameEsSafe . "' LIMIT 1");
	if ($row = tep_db_fetch_array($q)) return (int) $row['categories_id'];

	$nid = tep_db_query("SELECT IFNULL(MAX(categories_id),0)+1 AS nid FROM categories");
	$newId = (int) tep_db_fetch_array($nid)['nid'];
	$imgJson = '{"categoria":{"3":null,"1":null},"menu":{"3":null,"1":null}}';
	tep_db_query("INSERT INTO categories (categories_id, categories_image, parent_id, grandparent_id, sort_order, date_added, last_modified, categories_status, CODIG)
		VALUES ($newId, '" . tep_db_input($imgJson) . "', " . (int) $parentId . ", " . (int) $grandparentId . ", 10000, NOW(), NOW(), 0, $newId)");
	$slug = slugify($nameEs);
	tep_db_query("INSERT INTO categories_description (categories_id, language_id, categories_name, categories_seo_name, categories_seo_url) VALUES
		($newId, " . LANG_ID_ES . ", '" . tep_db_input($nameEs) . "', '" . tep_db_input($slug) . "', ''),
		($newId, " . LANG_ID_EN . ", '" . tep_db_input($nameEn) . "', '" . tep_db_input($slug) . "', '')");
	return $newId;
}

/* ----------------------------- BD: option values --------------------------- */
function findOrCreateOptionValue($name) {
	$nameSafe = tep_db_input($name);
	$q = tep_db_query("SELECT pov.products_options_values_id
		FROM products_options_values pov
		INNER JOIN products_options_values_to_products_options pov2po ON pov2po.products_options_values_id = pov.products_options_values_id
		WHERE pov2po.products_options_id = " . VARIANT_OPTION_ID . "
		  AND pov.language_id = " . LANG_ID_ES . "
		  AND pov.products_options_values_name = '$nameSafe' LIMIT 1");
	if ($row = tep_db_fetch_array($q)) return (int) $row['products_options_values_id'];

	$nid = tep_db_query("SELECT IFNULL(MAX(products_options_values_id),0)+1 AS nid FROM products_options_values");
	$newId = (int) tep_db_fetch_array($nid)['nid'];
	tep_db_query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($newId, " . LANG_ID_ES . ", '$nameSafe', '')");
	tep_db_query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($newId, " . LANG_ID_EN . ", '$nameSafe', '')");
	tep_db_query("INSERT INTO products_options_values_to_products_options (products_options_id, products_options_values_id) VALUES (" . VARIANT_OPTION_ID . ", $newId)");
	return $newId;
}

/* ----------------------------- BD: dedup ----------------------------------- */
/** productIds Jobe ya en BD (scoped a mfg 257 + origin jobe) + EAN global. */
function getExistingKeys() {
	$existing = [];
	$f = "(p.manufacturers_id = " . JOBE_MFG_ID . " OR p.products_import_origin LIKE 'jobe%')";
	$q = tep_db_query("SELECT LOWER(p.products_model) m FROM products p WHERE p.products_model<>'' AND $f");
	while ($r = tep_db_fetch_array($q)) $existing[$r['m']] = true;
	$q = tep_db_query("SELECT LOWER(pa.reference) m FROM products_attributes pa INNER JOIN products p ON p.products_id=pa.products_id WHERE pa.reference<>'' AND $f");
	while ($r = tep_db_fetch_array($q)) { $existing[$r['m']] = true; $existing[preg_replace('/-\d+[a-z\"]*$/i','',$r['m'])] = true; }
	// EAN global (GS1, namespace único)
	$q = tep_db_query("SELECT LOWER(product_ean) m FROM products WHERE product_ean<>''");
	while ($r = tep_db_fetch_array($q)) $existing[$r['m']] = true;
	$q = tep_db_query("SELECT LOWER(products_attributes_ean) m FROM products_attributes WHERE products_attributes_ean<>''");
	while ($r = tep_db_fetch_array($q)) $existing[$r['m']] = true;
	// Lista negra de reimportación: trata como "ya existentes" los códigos/EAN de productos borrados a propósito.
	require_once dirname(__FILE__) . '/includes/import_blacklist.php';
	$existing += fb_blacklist_keys();
	return $existing;
}

/* ----------------------------- Parseo del feed ----------------------------- */
/** Devuelve productId => [ ['size','ean','stock','retail'], ... ]. */
function parseJobeFeed($path) {
	$byId = [];
	if (!file_exists($path)) return $byId;
	$xml = @simplexml_load_file($path);
	if (!$xml) return $byId;
	foreach ($xml->product as $p) {
		$pid = trim((string) $p->productId);
		if ($pid === '') continue;
		$byId[$pid][] = [
			'size'   => trim((string) $p->size),
			'ean'    => trim((string) $p->ean),
			'stock'  => (int) ((string) $p->stock),
			'retail' => parseMoney((string) $p->retailPrice),
		];
	}
	return $byId;
}

/* ====================== Construcción de descripción ======================== */
/** Monta el HTML EN (párrafo + lista de specs). */
function buildDescEn($para, array $specs) {
	$html = '';
	if ($para !== '') $html .= '<p>' . htmlspecialchars($para, ENT_QUOTES, 'UTF-8') . '</p>';
	if ($specs) {
		$html .= '<ul>';
		foreach ($specs as $s) $html .= '<li>' . htmlspecialchars($s, ENT_QUOTES, 'UTF-8') . '</li>';
		$html .= '</ul>';
	}
	return $html;
}

/**
 * Extrae el peso (kg) de las specs/descripción de la ficha Jobe.
 * Las specs traen líneas tipo "Package weight: 10.5kg | 23lbs", "Board weight: 6kg".
 * Prioridad: package/shipping (peso de envío) > board/product/net/item > weight genérico.
 * EXCLUYE pesos de usuario/rider y líneas de tallaje de fijaciones ("fits", "<55kg", "Up to 80kg").
 * Devuelve kg (float) o 0 si no encuentra nada fiable.
 */
function extractWeightKg(array $specs, $para = '') {
	$cands = $specs;
	// también frases de la descripción que mencionen weight
	foreach (preg_split('/(?<=[.!?])\s+/', (string) $para) as $s) { $s = trim($s); if ($s !== '') $cands[] = $s; }
	$best = 0.0; $bestScore = -1;
	foreach ($cands as $line) {
		if (stripos($line, 'weight') === false && stripos($line, 'peso') === false) continue;
		// excluir pesos que NO son del producto (usuario/rider/carga máx/tallaje de fijación)
		if (preg_match('/\b(rider|user|person|recommended|maximum user|max user|capacity|load|fits|binding|suitable|up to)\b/i', $line)) continue;
		// primer valor en kg (preferido) o g
		$kg = 0.0;
		if (preg_match('/(\d+(?:[.,]\d+)?)\s*kg\b/i', $line, $m)) {
			$kg = (float) str_replace(',', '.', $m[1]);
		} elseif (preg_match('/(\d+(?:[.,]\d+)?)\s*g(?:r|ram|rams)?\b/i', $line, $m) && stripos($line, 'kg') === false) {
			$kg = ((float) str_replace(',', '.', $m[1])) / 1000.0;
		}
		if ($kg <= 0 || $kg > 200) continue;   // sanity
		$score = 1;
		if (preg_match('/\b(package|shipping|gross|parcel)\b/i', $line)) $score = 3;       // peso de envío
		elseif (preg_match('/\b(board|product|net|item|paddle|unit)\b/i', $line)) $score = 2;
		if ($score > $bestScore) { $bestScore = $score; $best = $kg; }
	}
	return round($best, 3);
}

$isAction = ($action === 'execute');
if ($isAction) {
	@header('X-Accel-Buffering: no');
	@header('Content-Type: text/html; charset=utf-8');
	while (ob_get_level() > 0) @ob_end_flush();
	@ob_implicit_flush(true);
}
?>
<?php require THEME . 'html/header.php'; ?>
<table style="width:100%;"><tr><td><div style="padding:20px;">
<h2>Importar nuevos productos de Jobe</h2>
<p>Detecta <code>productId</code> del feed <code>jobe.xml</code> que no están en tu BD y los da de alta en <strong>Jobe Nuevos</strong> (status 0) con <strong>status = 2</strong>, en subcategorías por sección del catálogo Jobe. Enriquece nombre/descripción/imágenes/precios desde el portal <code>joe.jobesports.com</code>.</p>

<?php if (!$isAction): ?>
<form method="post" style="background:#f5f5f5;padding:15px;border-radius:5px;">
	<p><strong>productId específicos</strong> (opcional, ignora "Límite"):<br>
		<textarea name="codes" rows="2" style="width:100%;font-family:monospace;" placeholder="Uno o varios productId separados por coma/espacio/salto."><?php echo htmlspecialchars($onlyCodesRaw); ?></textarea>
	</p>
	<p><strong>Familia / sección</strong> (opcional, importa solo esa familia del catálogo Jobe):<br>
		<select name="family" style="min-width:280px;">
			<option value="">— Todas las familias —</option>
			<?php foreach (JOBE_SECTIONS as $slug => $nm): ?>
			<option value="<?php echo htmlspecialchars($slug); ?>"<?php echo $family === $slug ? ' selected' : ''; ?>><?php echo htmlspecialchars($nm); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<p><label><input type="checkbox" name="dry_run" value="1" checked> Dry-run (solo lista los nuevos, no inserta nada)</label></p>
	<p><label><input type="checkbox" name="skip_format" value="1"> No maquetar descripción (solo traducir, más rápido)</label></p>
	<p><label><input type="checkbox" name="rebuild_catmap" value="1"> Reconstruir mapa de categorías (crawl del catálogo, ~1-2 min)</label></p>
	<p>Límite por ejecución (0 = sin límite): <input type="number" name="max" value="30" min="0" style="width:80px;"></p>
	<input type="hidden" name="action" value="execute">
	<button type="submit" class="xbutton small hv9 verde">Lanzar</button>
</form>
<p style="margin-top:20px;color:#888;font-size:12px;"><strong>Notas:</strong><br>
	- Variantes = TALLAS (opción <?php echo VARIANT_OPTION_ID; ?> "Talla"); un productId con &ge;2 tallas distintas → producto con variantes; si no, suelto.<br>
	- Imágenes del productId (compartidas por todas las tallas) → solo al producto padre.<br>
	- Coste = precio dealer del portal; PVP = retail con IVA / 1.21; G1 con tiers + piso 10%.<br>
	- Sin imagen descargable → SKIP. No se toca stock. status=2, check_stock=0.<br>
	- <strong>Output en streaming en tiempo real</strong>.</p>
</div></td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
<?php exit; endif; ?>
<p><a href="<?php echo tep_href_link('import-jobe-altas.php'); ?>" class="xbutton small hv9">← Volver</a></p>
<div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n";
@flush();

if ($isAction) {
	if (!is_dir(JOBE_TMP_DIR)) @mkdir(JOBE_TMP_DIR, 0775, true);
	logMsg('=== Inicio import-jobe-altas (' . ($dryRun ? 'DRY-RUN' : 'REAL') . ') ===');
	if ($max > 0) logMsg("Límite por ejecución: $max productos");

	// 1) Feed
	logMsg('Parseando feed jobe.xml...');
	$feed = parseJobeFeed(FEED_PATH);
	if (!$feed) { logMsg('ERROR: feed vacío o no encontrado en ' . FEED_PATH); goto end; }
	logMsg('  → ' . count($feed) . ' productId únicos (' . array_sum(array_map('count', $feed)) . ' filas)');

	// 2) Login portal
	logMsg('Login en el portal Jobe...');
	if (!jobeLogin()) { logMsg('ERROR: login Jobe falló'); goto end; }
	logMsg('  → sesión autenticada');

	// 3) Mapa de categorías
	$catMap = jobeBuildCategoryMap($rebuildCatmap);

	// 4) Dedup
	logMsg('Cargando claves existentes en BD...');
	$existing = getExistingKeys();
	logMsg('  → ' . count($existing) . ' claves ya en BD');

	// 5) Determinar nuevos
	if ($family !== '') logMsg('Filtro por familia/sección: ' . $family . ' (' . (JOBE_SECTIONS[$family] ?? $family) . ')');
	$newIds = [];
	$skippedFamily = 0;
	foreach ($feed as $pid => $rows) {
		if (!empty($onlyCodes) && !isset($onlyCodes[$pid])) continue;
		if ($family !== '' && ($catMap[$pid] ?? '') !== $family) { $skippedFamily++; continue; }
		if (isset($existing[strtolower($pid)])) continue;
		$eanHit = false;
		foreach ($rows as $r) { if ($r['ean'] !== '' && isset($existing[strtolower($r['ean'])])) { $eanHit = true; break; } }
		if ($eanHit) continue;
		$newIds[$pid] = $rows;
	}
	logMsg('=== ' . count($newIds) . ' productId NUEVOS ===' . ($family !== '' ? " (familia $family; " . $skippedFamily . ' fuera de la familia)' : ''));

	if ($dryRun) {
		$cnt = 0;
		foreach ($newIds as $pid => $rows) {
			$sizes = array_values(array_unique(array_map(fn($r) => $r['size'], $rows)));
			$nReal = count(array_filter($sizes, fn($s) => !isGenericSize($s)));
			$sec = $catMap[$pid] ?? '(sin sección)';
			logMsg(sprintf('  %s | sección=%s | %s | tallas: %s', $pid, $sec,
				($nReal >= 2 || count($sizes) >= 2 ? count($sizes) . ' variantes' : 'suelto'),
				implode(', ', array_slice($sizes, 0, 8))));
			if (++$cnt >= 40) { logMsg('  ... (' . (count($newIds) - 40) . ' más)'); break; }
		}
		logMsg('--- DRY-RUN, no se inserta nada. ---');
		goto end;
	}

	// 6) Categoría raíz "Jobe Nuevos"
	$jobeNuevosId = findOrCreateCategory('Jobe Nuevos', 'Jobe New', 0, 0);
	logMsg('Categoría "Jobe Nuevos" id=' . $jobeNuevosId);

	$nInserted = 0; $nWithVar = 0; $nErrors = 0; $processed = 0;
	$nFormatted = 0; $nFormatFail = 0; $nSkippedNoImg = 0; $nSkippedNoData = 0;

	foreach ($newIds as $pid => $rows) {
		if ($max > 0 && $processed >= $max) { logMsg("Alcanzado límite de $max, parando."); break; }
		$processed++;

		try {
			// --- Datos del portal ---
			$modal = jobeFetchModal($pid);
			if (!$modal || $modal['name'] === '') { logMsg("[$pid] SKIP: modal sin datos/nombre"); $nSkippedNoData++; continue; }
			$detail = jobeFetchDetail($pid);

			$nameEn = $modal['name'] !== '' ? $modal['name'] : ($detail['name'] ?? $pid);

			// --- Construir lista de tallas (feed = canónico) ---
			$variants = [];   // size => ['size_norm','ean','retail','dealer']
			foreach ($rows as $r) {
				$sz = $r['size'];
				$dealer = $modal['dealer'][$sz] ?? 0.0;
				if ($dealer <= 0) { foreach ($modal['dealer'] as $k => $v) { if (strcasecmp($k, $sz) === 0) { $dealer = $v; break; } } }
				$variants[$sz] = [
					'size'      => $sz,
					'size_norm' => normalizeSize($sz),
					'ean'       => $r['ean'],
					'retail'    => $r['retail'],
					'dealer'    => $dealer,
				];
			}
			$sizeTokens = array_keys($variants);
			$hasVariants = count($sizeTokens) >= 2;

			// --- Precio/coste base (variante más barata por coste dealer) ---
			$calcPvpNet = function($v) {
				$pvp = ($v['retail'] > 0) ? $v['retail'] / (1 + VAT_RATE) : ($v['dealer'] > 0 ? $v['dealer'] * 2 : 0);
				return roundPrice($pvp);   // PVP exacto de Jobe, sin redondeo a 0.05
			};
			uasort($variants, fn($a, $b) => ($a['dealer'] ?: $a['retail']) <=> ($b['dealer'] ?: $b['retail']));
			$baseKey  = array_key_first($variants);
			$baseVar  = $variants[$baseKey];
			$basePvp  = $calcPvpNet($baseVar);
			$baseCost = $baseVar['dealer'];
			if ($basePvp <= 0) { logMsg("[$pid] SKIP: sin precio (retail '-' y sin dealer)"); $nSkippedNoData++; continue; }

			// --- Imagen principal (obligatoria) ---
			$mainCode = $modal['main_img'] !== '' ? $modal['main_img'] : $pid;
			$titleEs  = translateEN($nameEn);
			if ($titleEs === '') $titleEs = $nameEn;
			$titleEs  = mb_substr($titleEs, 0, 80, 'UTF-8');
			$nameForSeo = slugify($titleEs) . '-' . strtolower($pid);

			$tmpImg = IMG_ABS_DIR . 'jobe-tmp-' . uniqid() . '.jpg';
			if (!jobeDownloadImage(JOBE_IMG_BASE . rawurlencode($mainCode) . '-big.jpg', $tmpImg)) {
				logMsg("[$pid] SKIP: imagen principal no descargable ($mainCode-big.jpg)");
				$nSkippedNoImg++;
				continue;
			}

			// --- Peso: de las specs de la ficha (Package/Board weight), fallback 1 kg ---
			$weightKg = $detail ? extractWeightKg($detail['specs'], $detail['para']) : 0.0;
			if ($weightKg <= 0) $weightKg = DEFAULT_WEIGHT_KG;

			// --- Descripción EN → ES + maquetado ---
			$descEn = $detail ? buildDescEn($detail['para'], $detail['specs']) : '';
			$descEs = '';
			if ($descEn !== '') {
				$descEs = translateEN($descEn);
				if ($descEs === '') $descEs = '[TRADUCIR]<br>' . $descEn;
				$_fs = 'none';
				$descEs = jobeMaquetar($descEs, $skipFormat, $_fs);
				if ($_fs === 'ok') $nFormatted++; elseif ($_fs === 'fail') $nFormatFail++;
			}

			// --- Sección/subcategoría --- breadcrumb de la ficha → catmap → palabra clave del nombre
			$section = ($detail['section'] ?? '') !== '' ? $detail['section'] : ($catMap[$pid] ?? '');
			if ($section === '') $section = jobeNameToSection($titleEs . ' ' . $nameEn);
			$catId = $jobeNuevosId;
			if ($section !== '') {
				$secEs = JOBE_SECTIONS[$section] ?? ucwords(str_replace('-', ' ', $section));
				$catId = findOrCreateCategory($secEs, $secEs, $jobeNuevosId, $jobeNuevosId);
			}

			tep_db_query('START TRANSACTION');

			$baseEan = $hasVariants ? '' : $baseVar['ean']; // sin master EAN si hay variantes
			tep_db_query("INSERT INTO products SET
				products_quantity      = 0,
				check_stock            = 0,
				products_model         = '" . tep_db_input($pid) . "',
				products_image         = '',
				products_price         = " . number_format($basePvp, 4, '.', '') . ",
				products_cost          = " . number_format($baseCost, 4, '.', '') . ",
				products_date_added    = NOW(),
				products_weight        = " . number_format($weightKg, 3, '.', '') . ",
				products_status        = 2,
				products_tax_class_id  = " . TAX_CLASS_IVA21 . ",
				manufacturers_id       = " . JOBE_MFG_ID . ",
				product_ean            = '" . tep_db_input($baseEan) . "',
				reference_prov         = '" . tep_db_input($pid) . "',
				products_import_origin = 'jobe-altas'");
			$productsId = (int) tep_db_insert_id();

			// EAN interno si quedó vacío/ inválido (solo sueltos; con variantes va en cada attr)
			if (!$hasVariants) {
				$cur = tep_db_fetch_array(tep_db_query("SELECT product_ean FROM products WHERE products_id=$productsId"));
				if (!isValidEan13($cur['product_ean'] ?? '')) {
					$gen = generateInternalEan13($productsId, EAN_INTERNAL_PREFIX);
					if ($gen !== '') tep_db_query("UPDATE products SET product_ean='" . tep_db_input($gen) . "' WHERE products_id=$productsId");
				}
			}

			// Descripción
			$nameEnSafe = tep_db_input(mb_substr($nameEn, 0, 80, 'UTF-8'));
			tep_db_query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_seo_name, products_seo_url) VALUES
				($productsId, " . LANG_ID_ES . ", '" . tep_db_input($titleEs) . "', '" . tep_db_input($descEs) . "', '" . tep_db_input($nameForSeo) . "', ''),
				($productsId, " . LANG_ID_EN . ", '$nameEnSafe', '" . tep_db_input($descEn) . "', '" . tep_db_input($nameForSeo) . "', '')");

			tep_db_query("INSERT INTO products_to_categories (products_id, categories_id) VALUES ($productsId, $catId)");

			// Imagen principal definitiva
			$localImg = $nameForSeo . '-' . $productsId . '.jpg';
			$imgInserted = false;
			if (@rename($tmpImg, IMG_ABS_DIR . $localImg)) {
				tep_db_query("UPDATE products SET products_image='" . tep_db_input($localImg) . "' WHERE products_id=$productsId");
				$imgInserted = true;
			} else { logMsg("[$pid] AVISO: rename imagen tmp falló"); }

			// Galería (imágenes -N del productId). Compartidas por todas las tallas → solo padre.
			if ($imgInserted && $detail && !empty($detail['gallery'])) {
				$seenH = []; if (file_exists(IMG_ABS_DIR . $localImg)) $seenH[md5_file(IMG_ABS_DIR . $localImg)] = true;
				$subs = []; $gi = 1;
				foreach ($detail['gallery'] as $gcode) {
					if ($gcode === $mainCode) continue;
					$gi++;
					$gtmp = IMG_ABS_DIR . 'jobe-gtmp-' . uniqid() . '.jpg';
					if (!jobeDownloadImage(JOBE_IMG_BASE . rawurlencode($gcode) . '-big.jpg', $gtmp)) continue;
					$h = md5_file($gtmp);
					if (isset($seenH[$h])) { @unlink($gtmp); continue; }
					$seenH[$h] = true;
					$gName = $nameForSeo . '-' . $gi . '-' . $productsId . '.jpg';
					if (@rename($gtmp, IMG_ABS_DIR . $gName)) $subs[] = $gName; else @unlink($gtmp);
				}
				if ($subs) tep_db_query("UPDATE products SET products_subimages='" . tep_db_input(json_encode($subs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . "' WHERE products_id=$productsId");
			}

			// --- Variantes (tallas) ---
			$variantsCreated = 0;
			if ($hasVariants) {
				$ovsUsados = [];
				foreach ($variants as $sz => $v) {
					$vPvp   = $calcPvpNet($v);
					$delta  = round($vPvp - $basePvp, 4);
					$prefix = $delta < 0 ? '-' : '+';
					$label  = mb_substr($v['size_norm'] !== '' ? $v['size_norm'] : $sz, 0, 64, 'UTF-8');
					$valueId = findOrCreateOptionValue($label);
					if (isset($ovsUsados[$valueId])) {
						$valueId = findOrCreateOptionValue(mb_substr($label . ' (' . $pid . ')', 0, 64, 'UTF-8'));
					}
					$ovsUsados[$valueId] = true;
					tep_db_query("INSERT INTO products_attributes SET
						products_id           = $productsId,
						options_id            = " . VARIANT_OPTION_ID . ",
						options_values_id     = $valueId,
						options_values_price  = " . number_format(abs($delta), 4, '.', '') . ",
						price_prefix          = '$prefix',
						reference             = '" . tep_db_input($pid . '-' . preg_replace('/[^0-9A-Za-z]/', '', $v['size_norm'])) . "',
						reference_prov        = '" . tep_db_input($pid) . "',
						products_attributes_ean = '" . tep_db_input($v['ean']) . "',
						options_values_weight = 0.000,
						weight_prefix         = '+'");
					$variantsCreated++;
				}
				if ($variantsCreated > 0) $nWithVar++;
			}

			// --- G1 (Profesionales) ---
			$g1Main = g1PriceNet($basePvp, $baseCost);
			tep_db_query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (1, $productsId, " . number_format($g1Main, 4, '.', '') . ", 1, 1)");
			if ($hasVariants) {
				$rPa = tep_db_query("SELECT products_attributes_id, options_values_price, price_prefix FROM products_attributes WHERE products_id=$productsId");
				while ($pa = tep_db_fetch_array($rPa)) {
					$absDelta = (float) $pa['options_values_price'];
					$sign = $pa['price_prefix'] === '-' ? -1 : 1;
					$varPvp = $basePvp + $sign * $absDelta;
					$g1Var = g1PriceNet($varPvp, $baseCost);
					$g1Delta = round($g1Var - $g1Main, 4);
					$g1Prefix = $g1Delta < 0 ? '-' : '+';
					tep_db_query("INSERT INTO products_attributes_groups (products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id, options_values_weight, weight_prefix) VALUES (" . (int) $pa['products_attributes_id'] . ", 1, " . number_format(abs($g1Delta), 4, '.', '') . ", '$g1Prefix', $productsId, 0, '+')");
				}
			}

			tep_db_query('COMMIT');
			$nInserted++;
			logMsg(sprintf('OK [%d] %s id=%d "%s" sec=%s %s w=%skg%s', $processed, $pid, $productsId, $titleEs,
				$section ?: '-', $imgInserted ? '[img]' : '[sin-img]', rtrim(rtrim(number_format($weightKg, 3, '.', ''), '0'), '.'),
				$hasVariants ? " [$variantsCreated tallas]" : ''));

		} catch (Exception $e) {
			tep_db_query('ROLLBACK');
			$nErrors++;
			logMsg("ERROR [$pid]: " . $e->getMessage());
		}
	}

	logMsg('=== RESUMEN ===');
	logMsg("Procesados : $processed");
	logMsg("Insertados : $nInserted");
	logMsg("Con variantes: $nWithVar");
	logMsg("Maquetadas : $nFormatted (fallidas: $nFormatFail)" . ($skipFormat ? ' [maquetado OFF]' : ''));
	logMsg("SKIP sin imagen: $nSkippedNoImg");
	logMsg("SKIP sin datos/precio: $nSkippedNoData");
	logMsg("Errores    : $nErrors");

	end:;
}
?>
</div>
<p style="margin-top:15px;"><a href="<?php echo tep_href_link('import-jobe-altas.php'); ?>" class="xbutton small hv9">← Volver</a></p>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
