<?php
/**
 * Importador Universal — librería compartida.
 * ----------------------------------------------------------------------------
 * Da de alta UN producto en la tienda a partir de la URL de su ficha en cualquier
 * web (WooCommerce, Shopify o HTML genérico con JSON-LD/OpenGraph). El usuario
 * aporta lo que la web no sabe: PVP, precio profesional (G1), coste y EAN. El resto
 * (nombre, descripción, imágenes, variantes, especificaciones) se extrae de la web
 * y se traduce/maqueta al español e inglés con el LLM local.
 *
 * Esta librería NO depende de application_top.php: recibe un mysqli y un logger,
 * así que se ejecuta desde el admin (import-universal-altas.php) y desde CLI
 * (import/Universal/uv_cli.php) con el mismo código.
 *
 * Convenciones de la tienda que respeta:
 *  - products_status=2 (borrador) salvo que se pida activar; check_stock=0; stock NO se toca.
 *  - Categoría raíz "Universal" (parent 0, status 0 = oculta), como el resto de "X Nuevos".
 *  - products_price / G1 NETOS (el usuario teclea CON IVA). Variantes: padre = la más barata,
 *    el resto en deltas (options_values_price / products_attributes_groups). Pesos en DELTA.
 *  - Sin EAN máster cuando hay variantes; EAN interno prefijo 28 (id-based) si falta.
 *  - Dedup: SKU por fabricante/origin, EAN global, lista negra de reimportación, URL ya importada.
 *  - Descripción con las 8 reglas de formato (mb_reformat_helpers.php) + glosario náutico.
 *
 * Creado 2026-09-04.
 */

if (!function_exists('uvFetchProduct')) {

require_once dirname(__FILE__) . '/mb_reformat_helpers.php';

if (!defined('UV_DOCROOT'))       define('UV_DOCROOT', dirname(dirname(dirname(__FILE__))) . '/');   // .../public_html/
if (!defined('UV_DIR'))           define('UV_DIR', UV_DOCROOT . 'import/Universal/');
if (!defined('UV_CACHE_DIR'))     define('UV_CACHE_DIR', UV_DIR . 'cache/');
if (!defined('UV_IMG_ABS_DIR'))   define('UV_IMG_ABS_DIR', UV_DOCROOT . 'images/productos/');
if (!defined('UV_LANG_ES'))       define('UV_LANG_ES', 3);
if (!defined('UV_LANG_EN'))       define('UV_LANG_EN', 1);
if (!defined('UV_G1_GROUP'))      define('UV_G1_GROUP', 1);
if (!defined('UV_CATEGORY_ES'))   define('UV_CATEGORY_ES', 'Universal');
if (!defined('UV_CATEGORY_EN'))   define('UV_CATEGORY_EN', 'Universal');
if (!defined('UV_ORIGIN'))        define('UV_ORIGIN', 'universal');
if (!defined('UV_EAN_PREFIX'))    define('UV_EAN_PREFIX', 28);       // genérico para altas sin proveedor (id-based, sin colisión)
if (!defined('UV_NAME_MAX'))      define('UV_NAME_MAX', 80);         // products_description.products_name varchar(80)
if (!defined('UV_OV_MAX'))        define('UV_OV_MAX', 64);           // convención nombres de variante
if (!defined('UV_MODEL_MAX'))     define('UV_MODEL_MAX', 50);        // products.products_model varchar(50)
if (!defined('UV_REF_MAX'))       define('UV_REF_MAX', 32);          // reference_prov varchar(32)
if (!defined('UV_IMG_MIN_BYTES')) define('UV_IMG_MIN_BYTES', 3072);
if (!defined('UV_MAX_SUBIMAGES')) define('UV_MAX_SUBIMAGES', 6);
if (!defined('UV_HTTP_TIMEOUT'))  define('UV_HTTP_TIMEOUT', 40);
if (!defined('UV_LLM_BASE'))      define('UV_LLM_BASE', 'http://217.127.199.171:28001/v1');
if (!defined('UV_LLM_MODEL_DEFAULT')) define('UV_LLM_MODEL_DEFAULT', 'qwen38-27b-nvfp4');
if (!defined('UV_OPTION_DEFAULT')) define('UV_OPTION_DEFAULT', 3);   // "Modelo"
if (!defined('UV_G1_MARGIN_FLOOR')) define('UV_G1_MARGIN_FLOOR', 0.10); // piso G1 = margen 10% sobre PVP → cost/0,90

/* ═══════════════════════════════ utilidades ═══════════════════════════════ */

function uvUA() {
    return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36';
}

/** GET con curl (sigue redirecciones, gzip). Devuelve el cuerpo o false; $info trae code/type/url/error. */
function uvHttpGet($url, &$info = null, $accept = 'text/html,application/xhtml+xml,application/json;q=0.9,*/*;q=0.8', $timeout = UV_HTTP_TIMEOUT) {
    $url = trim((string) $url);
    $info = ['code' => 0, 'type' => '', 'url' => $url, 'error' => ''];
    if (!preg_match('#^https?://#i', $url)) { $info['error'] = 'URL no http(s)'; return false; }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 8,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_USERAGENT      => uvUA(),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING       => '',
        CURLOPT_HTTPHEADER     => ['Accept: ' . $accept, 'Accept-Language: es-ES,es;q=0.9,en;q=0.8,it;q=0.6,fr;q=0.5,de;q=0.5'],
    ]);
    $body = curl_exec($ch);
    $info['code']  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $info['type']  = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $info['url']   = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $info['error'] = (string) curl_error($ch);
    unset($ch);   // sin curl_close(): deprecated en PHP 8.5
    return $body === false ? false : $body;
}

function uvDecode($s) {
    return html_entity_decode((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
function uvNormSpace($s) {
    $s = str_replace(["\xc2\xa0", "\xef\xbf\xbd"], [' ', ''], (string) $s);
    return trim(preg_replace('/\s+/u', ' ', $s));
}
/** Texto plano legible a partir de HTML (entrada del LLM y vista previa). */
function uvCleanHtmlToText($html) {
    if ($html === null || $html === '') return '';
    $html = (string) $html;
    $html = preg_replace('#<(style|script|noscript|iframe|svg|form|button|select)\b[^>]*>.*?</\1>#is', '', $html);
    $html = preg_replace('#<\s*br\s*/?\s*>#i', "\n", $html);
    $html = preg_replace('#</\s*(p|div|li|tr|h[1-6]|ul|ol|table|article|section|dd|dt|blockquote)\s*>#i', "\n", $html);
    $html = preg_replace('#<\s*li\b[^>]*>#i', "- ", $html);
    $html = preg_replace('#</\s*(td|th)\s*>#i', " | ", $html);
    $text = strip_tags($html);
    $text = uvDecode($text);
    $text = str_replace(["\xc2\xa0", "\xef\xbf\xbd"], [' ', ''], $text);
    $lines = preg_split("/\r\n|\r|\n/", $text);
    $out = []; $empty = 0;
    foreach ($lines as $l) {
        $l = trim(preg_replace('/[ \t\x{A0}]+/u', ' ', $l));
        $l = trim(preg_replace('/(\s*\|\s*)+$/', '', $l));
        if ($l === '' || $l === '-') { if ($empty < 1 && !empty($out)) $out[] = ''; $empty++; continue; }
        $out[] = $l; $empty = 0;
    }
    return trim(implode("\n", $out));
}
function uvSlugify($text, $maxLen = 50) {
    $t = trim((string) $text);
    if (function_exists('iconv')) { $c = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t); if ($c !== false && $c !== '') $t = $c; }
    $t = strtolower($t);
    $t = preg_replace('/[^a-z0-9]+/', '-', $t);
    $t = trim($t, '-');
    if (strlen($t) > $maxLen) $t = substr($t, 0, $maxLen);
    return trim($t, '-') ?: 'producto';
}
/** Recorta la BASE para que el sufijo sobreviva al límite (sql_mode no estricto trunca en silencio). */
function uvFitName($base, $suffix, $max) {
    $base = (string) $base; $suffix = (string) $suffix;
    if (mb_strlen($base . $suffix, 'UTF-8') <= $max) return $base . $suffix;
    return rtrim(mb_substr($base, 0, $max - mb_strlen($suffix, 'UTF-8'), 'UTF-8')) . $suffix;
}
function uvCut($s, $max) {
    $s = uvNormSpace($s);
    return mb_strlen($s, 'UTF-8') > $max ? rtrim(mb_substr($s, 0, $max, 'UTF-8')) : $s;
}
function uvAbsUrl($href, $base) {
    $href = trim((string) $href);
    if ($href === '' || stripos($href, 'data:') === 0 || stripos($href, 'javascript:') === 0) return '';
    if (preg_match('#^https?://#i', $href)) return $href;
    $p = parse_url($base);
    if (empty($p['host'])) return '';
    $scheme = $p['scheme'] ?? 'https';
    if (strpos($href, '//') === 0) return $scheme . ':' . $href;
    if ($href[0] === '/') return $scheme . '://' . $p['host'] . $href;
    $dir = preg_replace('#[^/]*$#', '', $p['path'] ?? '/');
    return $scheme . '://' . $p['host'] . $dir . $href;
}
function uvNormUrl($url) {
    $u = trim((string) $url);
    $u = preg_replace('/[#].*$/', '', $u);
    $u = preg_replace('/\?(utm_[^&]*&?)+$/i', '', $u);
    $p = parse_url($u);
    if (empty($p['host'])) return strtolower($u);
    $host = strtolower(preg_replace('/^www\./i', '', $p['host']));
    $path = rtrim($p['path'] ?? '/', '/');
    return $host . $path . (isset($p['query']) && $p['query'] !== '' ? '?' . $p['query'] : '');
}

/* ── EAN-13 ── */
function uvEan13Checksum($payload12) {
    if (strlen($payload12) !== 12 || !ctype_digit($payload12)) return -1;
    $sum = 0;
    for ($i = 0; $i < 12; $i++) { $d = (int) $payload12[$i]; $sum += ($i % 2 === 0) ? $d : $d * 3; }
    return (10 - ($sum % 10)) % 10;
}
function uvIsValidEan13($ean) {
    $ean = trim((string) $ean);
    if (strlen($ean) !== 13 || !ctype_digit($ean)) return false;
    return uvEan13Checksum(substr($ean, 0, 12)) === (int) $ean[12];
}
/** Normaliza un EAN tecleado/scrapeado: quita no-dígitos, UPC-A (12) → EAN-13, GTIN-14 con 0 → 13, valida checksum. '' si no vale. */
function uvNormalizeEan($raw) {
    $d = preg_replace('/\D+/', '', (string) $raw);
    if ($d === '') return '';
    if (strlen($d) === 12) $d = '0' . $d;
    if (strlen($d) === 14 && $d[0] === '0') $d = substr($d, 1);
    return uvIsValidEan13($d) ? $d : '';
}
function uvInternalEan13($id, $prefix = UV_EAN_PREFIX) {
    $pp = (int) $prefix; $id = (int) $id;
    if ($pp < 20 || $pp > 29 || $id <= 0 || $id > 9999999999) return '';
    $payload = str_pad((string) $pp, 2, '0', STR_PAD_LEFT) . str_pad((string) $id, 10, '0', STR_PAD_LEFT);
    if (strncmp($payload, '299', 3) === 0) return '';   // rango interno de QFacWin, no pisar
    $c = uvEan13Checksum($payload);
    return $c < 0 ? '' : ($payload . $c);
}

/* ── precios ── */
/** Neto tal que el precio CON IVA quede en múltiplo de 0,05. */
function uvRoundToNickel($net, $rate) {
    $wi = ((float) $net) * (1 + (float) $rate);
    $r  = round($wi * 20) / 20;
    return round($r / (1 + (float) $rate), 4);
}
/** Bruto (con IVA) al múltiplo de 0,05 más cercano. */
function uvNickelGross($gross) {
    return round(round(((float) $gross) * 20) / 20, 2);
}
function uvGrossToNet($gross, $rate) {
    return round(((float) $gross) / (1 + (float) $rate), 4);
}
/** G1 (Profesionales) por tiers de margen + piso = margen 10% sobre PVP (cost/0,90). Sin coste → sin piso. */
function uvCalcG1($price, $cost) {
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
    $g1 = $price * $mult;
    if ($cost > 0) $g1 = max($g1, $cost / (1.0 - UV_G1_MARGIN_FLOOR));
    return round($g1, 4);
}
function uvVatRateForTaxClass($taxClass) {
    switch ((int) $taxClass) { case 2: return 0.04; case 3: return 0.10; default: return 0.21; }
}

/** Idioma probable (en/es/it/de/fr/pt) por palabras vacías; '' si no se sabe. */
function uvDetectLang($text) {
    $t = ' ' . mb_strtolower(uvNormSpace(strip_tags((string) $text)), 'UTF-8') . ' ';
    if (mb_strlen($t, 'UTF-8') < 20) return '';
    $sets = [
        'en' => ['the','and','with','for','your','this','from','are','is','of'],
        'es' => ['el','la','los','las','para','con','una','del','que','es','por'],
        'it' => ['il','della','per','con','una','che','di','delle','gli','dei'],
        'de' => ['der','die','und','mit','für','das','ist','ein','eine','den'],
        'fr' => ['le','la','les','pour','avec','une','des','est','du','et'],
        'pt' => ['o','a','os','as','para','com','uma','que','é','do','da'],
    ];
    $best = ''; $bestN = 0;
    foreach ($sets as $lang => $words) {
        $n = 0;
        foreach ($words as $w) $n += preg_match_all('/\s' . preg_quote($w, '/') . '\s/u', $t);
        if ($n > $bestN) { $bestN = $n; $best = $lang; }
    }
    return $bestN >= 3 ? $best : '';
}

/** Nombre de atributo de la web → options_id de la tienda (Talla, Color, Longitud…); por defecto "Modelo" (3). */
function uvOptionIdForAttribute($name) {
    $n = mb_strtolower(trim((string) $name), 'UTF-8');
    $n = preg_replace('/^(pa_|attribute_)/', '', $n);
    $map = [
        267 => ['size','sizes','talla','tallas','taille','größe','groesse','taglia','tamaño','tamano'],
        184 => ['colour','color','colours','colors','couleur','farbe','colore'],
        44  => ['length','longitud','longueur','länge','laenge','lunghezza'],
        434 => ['diameter','diámetro','diametro','durchmesser','diamètre'],
        417 => ['material','materiale','matériau','materials'],
        46  => ['voltage','voltaje','tensión','tension','spannung'],
        98  => ['capacity','capacidad','capacité','kapazität','capacità'],
        133 => ['weight','peso','poids','gewicht'],
        266 => ['thread','rosca','filetage','gewinde','filettatura'],
    ];
    foreach ($map as $oid => $words) foreach ($words as $w) if ($n === $w) return $oid;
    return UV_OPTION_DEFAULT;
}
/** SKU limpio: descarta marcadores tipo "N/A", "-", "none", "SKU:" etc. */
function uvCleanSku($s) {
    $s = uvNormSpace(uvDecode($s));
    $s = preg_replace('/^(sku|ref\.?|referencia|art\.?|item|code|código|codigo)\s*[:#]?\s*/iu', '', $s);
    if ($s === '' || preg_match('/^(n\/?a|n\.a\.?|none|null|nil|no\s*sku|-+|—|–|0|\?)$/iu', $s)) return '';
    if (preg_match('/[#]/', $s)) $s = str_replace('#', '', $s);   // convención de la tienda: sin "#" en modelo/ref
    return uvCut($s, 50);
}
/** Peso en kg a partir de un valor crudo (unidad desconocida → kg) y/o un texto formateado "1.5 kg" / "350 g" / "2 lbs". */
function uvParseWeightKg($raw, $formatted = '') {
    $f = uvNormSpace(strip_tags((string) $formatted));
    if ($f !== '' && preg_match('/([\d.,]+)\s*(kg|kgs|g|gr|grams|gramos|lb|lbs|oz)\b/i', $f, $m)) {
        $n = (float) str_replace(',', '.', $m[1]); $u = strtolower($m[2]);
        if ($n <= 0) return null;
        if (in_array($u, ['g','gr','grams','gramos'], true)) return round($n / 1000, 3);
        if ($u === 'lb' || $u === 'lbs') return round($n * 0.45359237, 3);
        if ($u === 'oz') return round($n * 0.0283495, 3);
        return round($n, 3);
    }
    $r = str_replace(',', '.', uvNormSpace((string) $raw));
    if ($r !== '' && is_numeric($r) && (float) $r > 0) return round((float) $r, 3);
    return null;
}

/* ═══════════════════ extracción: WooCommerce / Shopify / genérico ═══════════════════ */

function uvEmptyProduct($url) {
    $host = parse_url($url, PHP_URL_HOST) ?: '';
    return [
        'source' => '', 'url' => $url, 'host' => preg_replace('/^www\./i', '', (string) $host),
        'name' => '', 'sku' => '', 'brand' => '', 'ean' => '', 'lang' => '',
        'description_html' => '', 'short_html' => '', 'text' => '',
        'images' => [], 'desc_images' => [],
        'weight_kg' => null, 'dimensions' => '', 'price' => null, 'currency' => '',
        'categories' => [], 'specs' => [], 'option_name' => '', 'variants' => [], 'diag' => [], 'source_id' => '', 'site_name' => '',
    ];
}

/** Punto de entrada: URL → producto normalizado, o ['error' => ...]. */
function uvFetchProduct($url, &$diag = null) {
    $diag = [];
    $url = trim((string) $url);
    if (!preg_match('#^https?://[^\s]+$#i', $url)) return ['error' => 'URL no válida (debe empezar por http:// o https://)'];
    $p = uvEmptyProduct($url);
    $info = null;
    $html = uvHttpGet($url, $info);
    $diag[] = 'GET ficha: http=' . $info['code'] . ' bytes=' . ($html === false ? 0 : strlen($html)) . ($info['error'] !== '' ? ' err=' . $info['error'] : '');
    if ($html === false) $html = '';
    if ($info['code'] >= 400) { $diag[] = 'La web respondió http ' . $info['code'] . ' (¿bloqueo anti-bot / Cloudflare?)'; }
    if (!empty($info['url']) && preg_match('#^https?://#i', $info['url'])) $p['url'] = $info['url'];
    $path = (string) parse_url($p['url'], PHP_URL_PATH);
    $isWoo = ($html !== '' && (stripos($html, 'woocommerce') !== false)) || preg_match('#/(product|produkt|producto|prodotto|produit)/#i', $path);
    $isShopify = ($html !== '' && (stripos($html, 'cdn.shopify.com') !== false || stripos($html, 'Shopify.theme') !== false)) || preg_match('#/products/[^/?\#]+/?(\?.*)?$#i', $p['url']);
    $ok = false;
    if ($isWoo) $ok = uvWooFetch($p, $html, $diag);
    if (!$ok && $isShopify) $ok = uvShopifyFetch($p, $html, $diag);
    if ($html !== '') uvParseGeneric($p, $html, $diag, !$ok);
    if ($p['name'] === '' && $html === '') return ['error' => 'No se pudo descargar la ficha (http ' . $info['code'] . ' ' . $info['error'] . ')', 'diag' => $diag];
    if ($p['name'] === '') return ['error' => 'No se encontró el nombre del producto en la página (¿es una ficha de producto?)', 'diag' => $diag];
    if ($p['source'] === '') $p['source'] = 'html';
    uvFinalizeProduct($p);
    $p['diag'] = $diag;
    return $p;
}

/* ── WooCommerce (Store API pública, fallback HTML) ── */
function uvWooApiBases($url) {
    $u = parse_url($url);
    $origin = ($u['scheme'] ?? 'https') . '://' . ($u['host'] ?? '');
    $bases = [];
    $segs = array_values(array_filter(explode('/', (string) ($u['path'] ?? ''))));
    if (!empty($segs) && preg_match('/^[a-z]{2}(-[a-z]{2})?$/i', $segs[0])) $bases[] = $origin . '/' . $segs[0] . '/wp-json/wc/store/v1';
    $bases[] = $origin . '/wp-json/wc/store/v1';
    $bases[] = $origin . '/?rest_route=/wc/store/v1';   // sin permalinks bonitos
    return $bases;
}
function uvWooSlug($url) {
    $path = (string) parse_url($url, PHP_URL_PATH);
    $segs = array_values(array_filter(explode('/', $path)));
    return empty($segs) ? '' : rawurldecode((string) end($segs));
}
function uvWooGetJson($base, $query, &$diag) {
    $url = (strpos($base, 'rest_route=') !== false) ? ($base . '/products&' . $query) : ($base . '/products?' . $query);
    $info = null;
    $body = uvHttpGet($url, $info, 'application/json');
    if ($body === false || $info['code'] !== 200) { $diag[] = 'WC Store API ' . $url . ' → http=' . $info['code']; return null; }
    $j = json_decode($body, true);
    if (!is_array($j)) { $diag[] = 'WC Store API: respuesta no JSON en ' . $url; return null; }
    return $j;
}
/** data-product_variations del HTML (WooCommerce) → [variation_id => datos]. */
function uvWooHtmlVariations($html) {
    $out = [];
    if ($html === '' || !preg_match('/data-product_variations="([^"]*)"/', $html, $m)) return $out;
    $j = json_decode(uvDecode($m[1]), true);
    if (!is_array($j)) return $out;
    foreach ($j as $v) if (isset($v['variation_id'])) $out[(int) $v['variation_id']] = $v;
    return $out;
}
function uvWooFetch(&$p, $html, &$diag) {
    $slug = uvWooSlug($p['url']);
    if ($slug === '') return false;
    $prod = null; $baseUsed = '';
    foreach (uvWooApiBases($p['url']) as $base) {
        $j = uvWooGetJson($base, 'slug=' . rawurlencode($slug), $diag);
        if (is_array($j) && !empty($j[0]['id']) && !empty($j[0]['name'])) { $prod = $j[0]; $baseUsed = $base; break; }
    }
    if ($prod === null) {
        $diag[] = 'WooCommerce: Store API no disponible → parseo del HTML';
        return uvWooParseHtml($p, $html, $diag);
    }
    $p['source'] = 'woocommerce';
    $p['source_id'] = (string) ($prod['id'] ?? '');
    $p['name'] = uvNormSpace(uvDecode($prod['name'] ?? ''));
    $p['sku']  = uvNormSpace($prod['sku'] ?? '');
    $p['short_html'] = (string) ($prod['short_description'] ?? '');
    $p['description_html'] = (string) ($prod['description'] ?? '');
    foreach (($prod['images'] ?? []) as $im) if (!empty($im['src'])) $p['images'][] = (string) $im['src'];
    foreach (($prod['categories'] ?? []) as $c) if (!empty($c['name'])) $p['categories'][] = uvNormSpace(uvDecode($c['name']));
    foreach (($prod['brands'] ?? []) as $b) if (!empty($b['name']) && $p['brand'] === '') $p['brand'] = uvNormSpace(uvDecode($b['name']));
    $minor = (int) ($prod['prices']['currency_minor_unit'] ?? 2);
    $div = pow(10, $minor);
    if (isset($prod['prices']['price']) && $prod['prices']['price'] !== '') $p['price'] = ((float) $prod['prices']['price']) / $div;
    $p['currency'] = (string) ($prod['prices']['currency_code'] ?? '');
    $p['weight_kg'] = uvParseWeightKg($prod['weight'] ?? '', $prod['formatted_weight'] ?? '');
    $dims = $prod['dimensions'] ?? [];
    if (is_array($dims) && ($dims['length'] ?? '') !== '') $p['dimensions'] = uvNormSpace(strip_tags((string) (($prod['formatted_dimensions'] ?? '') ?: ($dims['length'] . ' x ' . ($dims['width'] ?? '') . ' x ' . ($dims['height'] ?? '')))));
    $varAttrNames = [];
    foreach (($prod['attributes'] ?? []) as $a) {
        $an = uvNormSpace(uvDecode($a['name'] ?? ''));
        $terms = [];
        foreach (($a['terms'] ?? []) as $t) if (($t['name'] ?? '') !== '') $terms[] = uvNormSpace(uvDecode($t['name']));
        if (!empty($a['has_variations'])) { $varAttrNames[] = $an; continue; }
        if ($an !== '' && !empty($terms)) $p['specs'][] = [$an, implode(', ', $terms)];
    }
    $p['option_name'] = implode(' / ', $varAttrNames);
    $varAttrs = [];
    foreach (($prod['variations'] ?? []) as $v) {
        $attrs = [];
        foreach (($v['attributes'] ?? []) as $a) $attrs[uvNormSpace(uvDecode($a['name'] ?? ''))] = uvNormSpace(uvDecode($a['value'] ?? ''));
        $varAttrs[(int) ($v['id'] ?? 0)] = $attrs;
    }
    if (!empty($varAttrs)) {
        $list = uvWooGetJson($baseUsed, 'type=variation&parent=' . (int) $prod['id'] . '&per_page=100', $diag);
        $byId = [];
        if (is_array($list)) foreach ($list as $v) if (isset($v['id'])) $byId[(int) $v['id']] = $v;
        $htmlVars = uvWooHtmlVariations($html);
        foreach ($varAttrs as $vid => $attrs) {
            $v = $byId[$vid] ?? []; $hv = $htmlVars[$vid] ?? [];
            $price = null;
            if (isset($v['prices']['price']) && $v['prices']['price'] !== '') $price = ((float) $v['prices']['price']) / $div;
            elseif (isset($hv['display_price']) && is_numeric($hv['display_price'])) $price = round((float) $hv['display_price'], 2);
            $sku = uvNormSpace($v['sku'] ?? '');
            if ($sku === '') $sku = uvNormSpace($hv['sku'] ?? '');
            $img = (string) ($v['images'][0]['src'] ?? ($hv['image']['full_src'] ?? ($hv['image']['src'] ?? '')));
            $w = uvParseWeightKg($v['weight'] ?? ($hv['weight'] ?? ''), $v['formatted_weight'] ?? ($hv['weight_html'] ?? ''));
            if (empty($attrs) && !empty($hv['attributes']) && is_array($hv['attributes'])) foreach ($hv['attributes'] as $k => $val) $attrs[uvNormSpace(preg_replace('/^attribute_(pa_)?/', '', (string) $k))] = uvNormSpace(uvDecode($val));
            $ean = '';
            foreach (['gtin','ean','barcode','global_unique_id'] as $k) if (!empty($v[$k])) { $ean = uvNormalizeEan($v[$k]); if ($ean !== '') break; }
            $p['variants'][] = ['id' => $vid, 'sku' => $sku, 'label' => implode(' / ', array_values($attrs)), 'attrs' => $attrs,
                                'price' => $price, 'ean' => $ean, 'weight_kg' => $w, 'image' => $img];
        }
    }
    foreach (['gtin','ean','barcode','global_unique_id'] as $k) if (!empty($prod[$k]) && $p['ean'] === '') $p['ean'] = uvNormalizeEan($prod[$k]);
    $diag[] = 'WooCommerce Store API OK (' . $baseUsed . '): id=' . (int) $prod['id'] . ' tipo=' . ($prod['type'] ?? '?') . ' variaciones=' . count($p['variants']) . ' imágenes=' . count($p['images']);
    return true;
}
/** WooCommerce sin Store API: lo básico del HTML. */
function uvWooParseHtml(&$p, $html, &$diag) {
    if ($html === '') return false;
    $name = '';
    if (preg_match('#<h1[^>]*class="[^"]*product_title[^"]*"[^>]*>(.*?)</h1>#is', $html, $m)) $name = uvNormSpace(uvDecode(strip_tags($m[1])));
    if ($name === '') return false;
    $p['source'] = 'woocommerce-html';
    $p['name'] = $name;
    if (preg_match('#<span[^>]*class="sku"[^>]*>(.*?)</span>#is', $html, $m)) $p['sku'] = uvNormSpace(strip_tags($m[1]));
    $d = uvDomBlock($html, ['woocommerce-product-details__short-description']);
    if ($d !== '') $p['short_html'] = $d;
    $d = uvDomBlock($html, ['woocommerce-Tabs-panel--description', 'tab-description', 'product-description']);
    if ($d !== '') $p['description_html'] = $d;
    if (preg_match_all('/data-large_image="([^"]+)"/', $html, $mm)) foreach ($mm[1] as $u) $p['images'][] = uvAbsUrl(uvDecode($u), $p['url']);
    if (preg_match('#<span[^>]*class="posted_in"[^>]*>(.*?)</span>#is', $html, $m) && preg_match_all('#<a[^>]*>(.*?)</a>#is', $m[1], $ma)) foreach ($ma[1] as $c) $p['categories'][] = uvNormSpace(uvDecode(strip_tags($c)));
    $hv = uvWooHtmlVariations($html);
    $names = [];
    foreach ($hv as $vid => $v) {
        $attrs = [];
        foreach (($v['attributes'] ?? []) as $k => $val) { $an = uvNormSpace(str_replace(['attribute_pa_', 'attribute_', '-', '_'], ['', '', ' ', ' '], (string) $k)); $attrs[ucfirst($an)] = uvNormSpace(uvDecode($val)); $names[ucfirst($an)] = true; }
        $p['variants'][] = ['id' => (int) $vid, 'sku' => uvNormSpace($v['sku'] ?? ''), 'label' => implode(' / ', array_values($attrs)), 'attrs' => $attrs,
            'price' => (isset($v['display_price']) && is_numeric($v['display_price'])) ? round((float) $v['display_price'], 2) : null, 'ean' => '',
            'weight_kg' => uvParseWeightKg($v['weight'] ?? '', $v['weight_html'] ?? ''), 'image' => (string) ($v['image']['full_src'] ?? ($v['image']['src'] ?? ''))];
    }
    $p['option_name'] = implode(' / ', array_keys($names));
    $diag[] = 'WooCommerce HTML: nombre OK, variaciones=' . count($p['variants']) . ' imágenes=' . count($p['images']);
    return true;
}

/* ── Shopify (products/<handle>.js público) ── */
function uvShopifyFetch(&$p, $html, &$diag) {
    if (!preg_match('#^(https?://[^/]+)(?:/[a-z]{2}(?:-[a-z]{2})?)?(?:/collections/[^/]+)?/products/([^/?\#]+)#i', $p['url'], $m)) return false;
    $origin = $m[1]; $handle = $m[2];
    $info = null; $fromJs = true;
    $body = uvHttpGet($origin . '/products/' . $handle . '.js', $info, 'application/json');
    if ($body === false || $info['code'] !== 200 || stripos((string) $info['type'], 'json') === false) {
        $fromJs = false;
        $body = uvHttpGet($origin . '/products/' . $handle . '.json', $info, 'application/json');
        if ($body !== false && $info['code'] === 200) { $j = json_decode($body, true); $body = isset($j['product']) ? json_encode($j['product']) : false; }
    }
    if ($body === false || $info['code'] !== 200) { $diag[] = 'Shopify: products/' . $handle . '.js/.json no disponible (http ' . $info['code'] . ')'; return false; }
    $j = json_decode((string) $body, true);
    if (!is_array($j) || empty($j['title'])) return false;
    $p['source'] = 'shopify';
    $p['source_id'] = (string) ($j['id'] ?? '');
    $p['name'] = uvNormSpace(uvDecode($j['title']));
    $p['brand'] = uvNormSpace((string) ($j['vendor'] ?? ''));
    $p['description_html'] = (string) ($j['body_html'] ?? ($j['description'] ?? ''));
    foreach (($j['images'] ?? []) as $im) { $src = is_array($im) ? (string) ($im['src'] ?? '') : (string) $im; if ($src !== '') $p['images'][] = uvAbsUrl(preg_replace('#^//#', 'https://', $src), $origin); }
    $type = uvNormSpace((string) ($j['type'] ?? ($j['product_type'] ?? '')));
    if ($type !== '') $p['categories'][] = $type;
    if (preg_match('/Shopify\.currency\s*=\s*\{"active":"([A-Z]{3})"/', $html, $cm)) $p['currency'] = $cm[1];
    elseif (preg_match('/"currency"\s*:\s*"([A-Z]{3})"/', $html, $cm)) $p['currency'] = $cm[1];
    $optNames = [];
    foreach (($j['options'] ?? []) as $o) $optNames[] = is_array($o) ? uvNormSpace((string) ($o['name'] ?? '')) : uvNormSpace((string) $o);
    $minPrice = null;
    foreach (($j['variants'] ?? []) as $v) {
        $price = null;
        if (isset($v['price']) && $v['price'] !== '' && is_numeric($v['price'])) $price = $fromJs ? ((float) $v['price']) / 100 : (float) $v['price'];
        $attrs = [];
        for ($i = 1; $i <= 3; $i++) {
            $val = uvNormSpace((string) ($v['option' . $i] ?? '')); $nm = $optNames[$i - 1] ?? '';
            if ($val !== '' && strcasecmp($val, 'Default Title') !== 0) $attrs[($nm !== '' && strcasecmp($nm, 'Title') !== 0) ? $nm : ('Opción ' . $i)] = $val;
        }
        $label = uvNormSpace((string) ($v['title'] ?? '')); if (strcasecmp($label, 'Default Title') === 0) $label = '';
        $w = null;
        if (isset($v['grams']) && (float) $v['grams'] > 0) $w = round(((float) $v['grams']) / 1000, 3);
        elseif (isset($v['weight']) && (float) $v['weight'] > 0) $w = uvParseWeightKg($v['weight'], $v['weight'] . ' ' . ($v['weight_unit'] ?? 'kg'));
        $img = '';
        if (!empty($v['featured_image']['src'])) $img = uvAbsUrl(preg_replace('#^//#', 'https://', (string) $v['featured_image']['src']), $origin);
        $p['variants'][] = ['id' => (int) ($v['id'] ?? 0), 'sku' => uvNormSpace((string) ($v['sku'] ?? '')), 'label' => $label !== '' ? $label : implode(' / ', array_values($attrs)),
                            'attrs' => $attrs, 'price' => $price, 'ean' => uvNormalizeEan($v['barcode'] ?? ''), 'weight_kg' => $w, 'image' => $img];
        if ($price !== null && ($minPrice === null || $price < $minPrice)) $minPrice = $price;
    }
    if (count($p['variants']) === 1 && $p['variants'][0]['label'] === '') {   // mono-variante = producto suelto
        $only = $p['variants'][0];
        $p['sku'] = $only['sku']; $p['ean'] = $only['ean']; $p['weight_kg'] = $only['weight_kg']; $p['variants'] = [];
    }
    $p['price'] = $minPrice;
    $p['option_name'] = implode(' / ', array_filter($optNames, fn($n) => $n !== '' && strcasecmp($n, 'Title') !== 0));
    $diag[] = 'Shopify products/' . $handle . ($fromJs ? '.js' : '.json') . ' OK: variantes=' . count($p['variants']) . ' imágenes=' . count($p['images']);
    return true;
}

/* ── genérico: JSON-LD, OpenGraph, DOM ── */
function uvJsonLdProducts($html) {
    $found = [];
    if (!preg_match_all('#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $mm)) return $found;
    $walk = function ($node) use (&$walk, &$found) {
        if (!is_array($node)) return;
        $t = $node['@type'] ?? null;
        $types = is_array($t) ? $t : ($t !== null ? [$t] : []);
        foreach ($types as $tt) if (is_string($tt) && preg_match('/^(Product|ProductGroup|IndividualProduct|ProductModel)$/i', $tt)) { $found[] = $node; return; }
        foreach ($node as $k => $v) if (is_array($v) && $k !== 'offers') $walk($v);
    };
    foreach ($mm[1] as $raw) {
        $raw = trim($raw);
        $j = json_decode($raw, true);
        if (!is_array($j)) { $j = json_decode(preg_replace('/[\x00-\x1f]/', ' ', $raw), true); }
        if (!is_array($j)) continue;
        $walk($j);
    }
    return $found;
}
function uvLdImages($img) {
    $out = [];
    if (is_string($img)) $out[] = $img;
    elseif (is_array($img)) {
        if (isset($img['url']) || isset($img['contentUrl'])) $out[] = (string) ($img['url'] ?? $img['contentUrl']);
        else foreach ($img as $i) { if (is_string($i)) $out[] = $i; elseif (is_array($i) && (isset($i['url']) || isset($i['contentUrl']))) $out[] = (string) ($i['url'] ?? $i['contentUrl']); }
    }
    return array_values(array_filter(array_map('trim', $out)));
}
function uvLdFirstString($v) {
    if (is_string($v) || is_numeric($v)) return (string) $v;
    if (is_array($v)) { if (isset($v['name'])) return uvLdFirstString($v['name']); if (isset($v['@value'])) return (string) $v['@value']; foreach ($v as $x) { $s = uvLdFirstString($x); if ($s !== '') return $s; } }
    return '';
}
function uvMetaTags($html) {
    $out = [];
    if (preg_match_all('#<meta\s+[^>]*>#i', $html, $mm)) {
        foreach ($mm[0] as $tag) {
            $key = ''; $content = '';
            if (preg_match('/\b(?:property|name|itemprop)\s*=\s*["\']([^"\']+)["\']/i', $tag, $k)) $key = strtolower($k[1]);
            if (preg_match('/\bcontent\s*=\s*["\']([^"\']*)["\']/i', $tag, $c)) $content = uvDecode($c[1]);
            if ($key !== '' && $content !== '' && !isset($out[$key])) $out[$key] = $content;
        }
    }
    return $out;
}
function uvDom($html) {
    if (!class_exists('DOMDocument') || $html === '') return null;
    $doc = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $ok = $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors(); libxml_use_internal_errors($prev);
    return $ok ? $doc : null;
}
function uvInnerHtml($node) {
    $h = '';
    foreach ($node->childNodes as $c) $h .= $node->ownerDocument->saveHTML($c);
    return $h;
}
/** innerHTML del primer elemento cuya class/id contenga alguno de los tokens. */
function uvDomBlock($html, array $tokens) {
    $doc = uvDom($html);
    if (!$doc) return '';
    $xp = new DOMXPath($doc);
    foreach ($tokens as $t) {
        $q = '//*[contains(concat(" ", normalize-space(@class), " "), " ' . $t . ' ") or @id="' . $t . '"]';
        $n = $xp->query($q);
        if ($n && $n->length > 0) { $h = trim(uvInnerHtml($n->item(0))); if ($h !== '') return $h; }
    }
    return '';
}
/** Bloque descriptivo más largo (por id/class "description|details|descripcion|dettagli|beschreibung") excluyendo navegación. */
function uvBestDescriptionBlock($html) {
    $doc = uvDom($html);
    if (!$doc) return '';
    $xp = new DOMXPath($doc);
    $tokens = ['description', 'descripcion', 'descripción', 'product-details', 'product_details', 'productdetails', 'details', 'dettagli', 'beschreibung', 'rte', 'prose', 'tab-content', 'tab_content', 'long-desc', 'longdesc', 'caracteristicas', 'features', 'specifications', 'specs'];
    $best = ''; $bestLen = 0;
    $nodes = $xp->query('//div|//section|//article|//td');
    if (!$nodes) return '';
    foreach ($nodes as $n) {
        $id = strtolower((string) $n->getAttribute('id')); $cls = strtolower((string) $n->getAttribute('class'));
        $hit = false;
        foreach ($tokens as $t) if (($id !== '' && strpos($id, $t) !== false) || ($cls !== '' && strpos($cls, $t) !== false)) { $hit = true; break; }
        if (!$hit) continue;
        if (preg_match('/\b(nav|menu|footer|header|breadcrumb|review|comment|related|upsell|cross-sell|cart|sidebar)\b/', $id . ' ' . $cls)) continue;
        $txt = uvNormSpace($n->textContent);
        $len = mb_strlen($txt, 'UTF-8');
        if ($len < 40 || $len > 20000) continue;
        if ($len > $bestLen) { $bestLen = $len; $best = uvInnerHtml($n); }
    }
    return trim($best);
}
/** Imágenes de galería (genérico): <img>/<a> con pinta de foto de producto, en orden de aparición. */
function uvGalleryImages($html, $base) {
    $doc = uvDom($html);
    if (!$doc) return [];
    $xp = new DOMXPath($doc);
    $cands = []; $seen = [];
    $bad = '/(logo|icon|sprite|flag|payment|placeholder|loader|spinner|avatar|banner|badge|pixel|tracking|captcha|\.svg|\.gif|blank\.|data:)/i';
    $push = function ($u, $score) use (&$cands, &$seen, $base, $bad) {
        $u = uvAbsUrl(uvDecode((string) $u), $base);
        if ($u === '' || preg_match($bad, $u)) return;
        if (!preg_match('/\.(jpe?g|png|webp)(\?|$)/i', $u) && !preg_match('#/(image|img|media|upload|cdn|photo)#i', $u)) return;
        $key = preg_replace('/[-_]\d{2,4}x\d{2,4}(?=\.\w+(\?|$))/', '', preg_replace('/\?.*$/', '', $u));
        if (isset($seen[$key])) { if ($score > $seen[$key]) { $seen[$key] = $score; $cands[$key] = ['u' => $u, 's' => $score]; } return; }
        $seen[$key] = $score; $cands[$key] = ['u' => $u, 's' => $score];
    };
    $nodes = $xp->query('//img|//a[@href]|//source[@srcset]');
    if (!$nodes) return [];
    foreach ($nodes as $n) {
        $score = 1;
        $ctx = ''; $a = $n;
        for ($i = 0; $i < 5 && $a && $a instanceof DOMElement; $i++) { $ctx .= ' ' . strtolower((string) $a->getAttribute('class') . ' ' . (string) $a->getAttribute('id')); $a = $a->parentNode; }
        if (preg_match('/(gallery|product-image|product__media|product-media|swiper|slider|carousel|zoom|fotorama|lightbox|woocommerce-product-gallery|main-image|product-photo|images)/', $ctx)) $score += 5;
        if (preg_match('/(related|upsell|cross|recommend|footer|header|nav|menu|logo|brand-list|thumbnail-list|reviews)/', $ctx)) $score -= 4;
        if ($n->nodeName === 'img') {
            foreach (['data-large_image', 'data-zoom-image', 'data-zoom', 'data-large', 'data-src', 'data-original', 'src'] as $attr) { $v = (string) $n->getAttribute($attr); if ($v !== '') { $push($v, $score + ($attr === 'src' ? 0 : 1)); } }
            $w = (int) $n->getAttribute('width'); if ($w > 0 && $w < 120) continue;
        } elseif ($n->nodeName === 'a') {
            $href = (string) $n->getAttribute('href');
            if (preg_match('/\.(jpe?g|png|webp)(\?|$)/i', $href)) $push($href, $score + 1);
        } else {
            $ss = (string) $n->getAttribute('srcset');
            if ($ss !== '') { $parts = array_map('trim', explode(',', $ss)); $last = end($parts); $push(preg_replace('/\s+\S+$/', '', $last), $score); }
        }
    }
    $list = array_values(array_filter($cands, fn($c) => $c['s'] >= 2));
    usort($list, fn($a, $b) => $b['s'] <=> $a['s']);
    return array_slice(array_map(fn($c) => $c['u'], $list), 0, 10);
}
/** Variantes por <select> (genérico). Devuelve ['name'=>..., 'values'=>[...]] o []. */
function uvSelectVariants($html) {
    $doc = uvDom($html);
    if (!$doc) return [];
    $xp = new DOMXPath($doc);
    $sels = $xp->query('//select');
    if (!$sels) return [];
    $skipOpt = '/^(choose|select|elige|selecciona|seleccione|seleccionar|wählen|scegli|choisir|please|--|—|-|\.\.\.|0)/iu';
    foreach ($sels as $s) {
        $nm = strtolower((string) $s->getAttribute('name') . ' ' . (string) $s->getAttribute('id') . ' ' . (string) $s->getAttribute('class'));
        if (!preg_match('/(variant|attribute|option|size|talla|colou?r|model|modelo|version|versión|type|tipo|length|longitud|capacity|voltage)/', $nm)) continue;
        if (preg_match('/(qty|quantity|cantidad|sort|order|currency|country|language|shipping|per_page|limit)/', $nm)) continue;
        $vals = [];
        foreach ($xp->query('.//option', $s) as $o) {
            $t = uvNormSpace($o->textContent);
            $v = (string) $o->getAttribute('value');
            if ($t === '' || $v === '' || preg_match($skipOpt, $t) || $o->hasAttribute('disabled')) continue;
            $vals[] = $t;
        }
        $vals = array_values(array_unique($vals));
        if (count($vals) >= 2 && count($vals) <= 80) {
            $label = '';
            $id = (string) $s->getAttribute('id');
            if ($id !== '') { $l = $xp->query('//label[@for="' . $id . '"]'); if ($l && $l->length) $label = uvNormSpace($l->item(0)->textContent); }
            if ($label === '') { $label = ucfirst(trim(preg_replace('/^(attribute_pa_|attribute_|option\[?|options\[|variant[_-]?)/i', '', (string) $s->getAttribute('name')), '[]_- ')); }
            if ($label === '') $label = 'Modelo';
            return ['name' => uvCut($label, 40), 'values' => $vals];
        }
    }
    return [];
}
function uvParseGeneric(&$p, $html, &$diag, $primary) {
    $ld = uvJsonLdProducts($html);
    if (!empty($ld)) {
        $d = $ld[0];
        if ($primary && $p['source'] === '') $p['source'] = 'jsonld';
        if ($p['name'] === '' && !empty($d['name'])) $p['name'] = uvNormSpace(uvDecode(uvLdFirstString($d['name'])));
        // Yoast/WooCommerce ponen el id del post como "sku" cuando el producto no tiene SKU → ignorarlo
        if ($p['sku'] === '' && !empty($d['sku']) && uvNormSpace(uvLdFirstString($d['sku'])) !== (string) $p['source_id']) $p['sku'] = uvNormSpace(uvLdFirstString($d['sku']));
        if ($p['sku'] === '' && !empty($d['mpn'])) $p['sku'] = uvNormSpace(uvLdFirstString($d['mpn']));
        if ($p['brand'] === '' && !empty($d['brand'])) $p['brand'] = uvNormSpace(uvDecode(uvLdFirstString($d['brand'])));
        if ($p['ean'] === '') foreach (['gtin13','gtin','gtin12','gtin14','gtin8','ean','barcode'] as $k) if (!empty($d[$k])) { $e = uvNormalizeEan(uvLdFirstString($d[$k])); if ($e !== '') { $p['ean'] = $e; break; } }
        if ($p['description_html'] === '' && $p['short_html'] === '' && !empty($d['description'])) {
            $txt = uvDecode(uvLdFirstString($d['description']));
            $p['description_html'] = '<p>' . implode('</p><p>', array_map('htmlspecialchars', array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $txt)), fn($x) => $x !== ''))) . '</p>';
        }
        if (empty($p['images']) && !empty($d['image'])) foreach (uvLdImages($d['image']) as $im) { $u = uvAbsUrl($im, $p['url']); if ($u !== '') $p['images'][] = $u; }
        if ($p['price'] === null && !empty($d['offers'])) {
            $o = $d['offers'];
            if (isset($o['@type']) || isset($o['price']) || isset($o['lowPrice'])) $o = [$o];
            foreach ((array) $o as $off) {
                if (!is_array($off)) continue;
                $pr = $off['lowPrice'] ?? ($off['price'] ?? ($off['priceSpecification']['price'] ?? null));
                if ($pr !== null && $pr !== '') { $p['price'] = (float) str_replace(',', '.', (string) uvLdFirstString($pr)); $p['currency'] = (string) uvLdFirstString($off['priceCurrency'] ?? ($off['priceSpecification']['priceCurrency'] ?? $p['currency'])); break; }
            }
        }
        if ($p['weight_kg'] === null && !empty($d['weight']) && is_array($d['weight']) && isset($d['weight']['value'])) $p['weight_kg'] = uvParseWeightKg($d['weight']['value'], $d['weight']['value'] . ' ' . ($d['weight']['unitText'] ?? (($d['weight']['unitCode'] ?? 'KGM') === 'KGM' ? 'kg' : (($d['weight']['unitCode'] ?? '') === 'GRM' ? 'g' : 'kg'))));
        if (!empty($d['additionalProperty']) && is_array($d['additionalProperty'])) foreach ($d['additionalProperty'] as $ap) if (is_array($ap) && !empty($ap['name']) && isset($ap['value']) && $ap['value'] !== '') $p['specs'][] = [uvNormSpace(uvLdFirstString($ap['name'])), uvNormSpace(is_array($ap['value']) ? implode(', ', array_map('uvLdFirstString', $ap['value'])) : (string) $ap['value'])];
        if (empty($p['variants']) && !empty($d['hasVariant']) && is_array($d['hasVariant'])) {
            $i = 0;
            foreach ($d['hasVariant'] as $hv) {
                if (!is_array($hv)) continue;
                $i++;
                $off = $hv['offers'] ?? []; if (isset($off['@type']) || isset($off['price'])) $off = [$off];
                $pr = null; foreach ((array) $off as $o2) if (is_array($o2) && isset($o2['price'])) { $pr = (float) str_replace(',', '.', (string) uvLdFirstString($o2['price'])); break; }
                $label = uvNormSpace(uvDecode(uvLdFirstString($hv['name'] ?? '')));
                foreach (['size','color','material'] as $ak) if (!empty($hv[$ak])) $label = uvNormSpace(uvLdFirstString($hv[$ak])) . ($label !== '' && $label !== $p['name'] ? ' · ' . $label : '');
                if ($label === $p['name']) $label = '';
                $e = ''; foreach (['gtin13','gtin','gtin12','gtin14','gtin8'] as $k) if (!empty($hv[$k])) { $e = uvNormalizeEan(uvLdFirstString($hv[$k])); if ($e !== '') break; }
                $p['variants'][] = ['id' => $i, 'sku' => uvNormSpace(uvLdFirstString($hv['sku'] ?? '')), 'label' => $label, 'attrs' => ['Modelo' => $label], 'price' => $pr, 'ean' => $e, 'weight_kg' => null, 'image' => (string) (uvLdImages($hv['image'] ?? '')[0] ?? '')];
            }
            if (!empty($p['variants'])) $p['option_name'] = 'Modelo';
        }
        $diag[] = 'JSON-LD Product encontrado (' . count($ld) . ')';
    }
    $og = uvMetaTags($html);
    if ($p['name'] === '') {
        $t = (string) ($og['og:title'] ?? '');
        if ($t === '' && preg_match('#<h1[^>]*>(.*?)</h1>#is', $html, $m)) $t = strip_tags($m[1]);
        if ($t === '' && preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m)) $t = $m[1];
        $t = uvNormSpace(uvDecode($t));
        $t = preg_replace('/\s+[\-|–—]\s+[^\-|–—]{2,60}$/u', '', $t);   // "Producto - Tienda"
        $p['name'] = $t;
    }
    if ($p['site_name'] === '') { $sn = (string) ($og['og:site_name'] ?? ($og['application-name'] ?? '')); if ($sn !== '') $p['site_name'] = uvCut($sn, 60); }
    if ($p['lang'] === '' && preg_match('/<html[^>]*\slang=["\']?([a-zA-Z]{2})/i', $html, $m)) $p['lang'] = strtolower($m[1]);
    if ($p['brand'] === '') {
        if (!empty($og['product:brand'])) $p['brand'] = uvNormSpace($og['product:brand']);
        elseif (!empty($og['brand'])) $p['brand'] = uvNormSpace($og['brand']);
    }
    if ($p['sku'] === '' && preg_match('/itemprop=["\']sku["\'][^>]*>\s*([^<]{1,40}?)\s*</i', $html, $m)) $p['sku'] = uvNormSpace($m[1]);
    if ($p['sku'] === '' && preg_match('/class=["\'][^"\']*\bsku\b[^"\']*["\'][^>]*>\s*([^<]{1,40}?)\s*</i', $html, $m)) $p['sku'] = uvNormSpace($m[1]);
    if ($p['price'] === null && !empty($og['product:price:amount']) && is_numeric(str_replace(',', '.', $og['product:price:amount']))) { $p['price'] = (float) str_replace(',', '.', $og['product:price:amount']); $p['currency'] = (string) ($og['product:price:currency'] ?? $p['currency']); }
    if ($p['description_html'] === '' && $p['short_html'] === '') {
        $best = uvBestDescriptionBlock($html);
        if ($best !== '') { $p['description_html'] = $best; $diag[] = 'Descripción: bloque HTML (' . mb_strlen(uvNormSpace(strip_tags($best)), 'UTF-8') . ' chars)'; }
        elseif (!empty($og['og:description'])) { $p['description_html'] = '<p>' . htmlspecialchars(uvDecode($og['og:description'])) . '</p>'; $diag[] = 'Descripción: og:description'; }
    }
    if (empty($p['images']) && !empty($og['og:image'])) $p['images'][] = uvAbsUrl($og['og:image'], $p['url']);
    if ($primary && count($p['images']) <= 1) foreach (uvGalleryImages($html, $p['url']) as $im) if (!in_array($im, $p['images'], true)) $p['images'][] = $im;
    if ($primary && empty($p['variants'])) {
        $sel = uvSelectVariants($html);
        if (!empty($sel)) {
            $p['option_name'] = $sel['name'];
            foreach ($sel['values'] as $i => $val) $p['variants'][] = ['id' => $i + 1, 'sku' => '', 'label' => $val, 'attrs' => [$sel['name'] => $val], 'price' => null, 'ean' => '', 'weight_kg' => null, 'image' => ''];
            $diag[] = 'Variantes por <select> "' . $sel['name'] . '": ' . count($sel['values']);
        }
    }
}
/** Normaliza y completa el producto extraído. */
function uvFinalizeProduct(&$p) {
    // imágenes: absolutas, sin sufijos de tamaño de WordPress, sin duplicados, máx 10
    $imgs = []; $seen = [];
    foreach ($p['images'] as $u) {
        $u = uvAbsUrl($u, $p['url']);
        if ($u === '' || !preg_match('#^https?://#i', $u)) continue;
        $full = preg_replace('/-\d{2,4}x\d{2,4}(?=\.(jpe?g|png|webp)(\?|$))/i', '', $u);
        $key = strtolower(preg_replace('/\?.*$/', '', $full));
        if (isset($seen[$key])) continue;
        $seen[$key] = true; $imgs[] = $full;
        if (count($imgs) >= 10) break;
    }
    $p['images'] = $imgs;
    // imágenes incrustadas en la descripción → candidatas a galería; se quitan del HTML
    foreach (['short_html', 'description_html'] as $k) {
        if ($p[$k] === '') continue;
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $p[$k], $mm)) foreach ($mm[1] as $u) { $u = uvAbsUrl(uvDecode($u), $p['url']); if ($u !== '' && !preg_match('/\.(svg|gif)(\?|$)/i', $u)) $p['desc_images'][] = $u; }
        $p[$k] = preg_replace('/<img[^>]*>/i', '', $p[$k]);
    }
    $p['desc_images'] = array_values(array_unique(array_diff($p['desc_images'], $p['images'])));
    // texto plano combinado (corta + larga; sin duplicar si una contiene a la otra)
    $short = uvCleanHtmlToText($p['short_html']); $long = uvCleanHtmlToText($p['description_html']);
    if ($short !== '' && $long !== '' && mb_strpos($long, mb_substr($short, 0, 80, 'UTF-8'), 0, 'UTF-8') !== false) $short = '';
    $p['text'] = trim($short . ($short !== '' && $long !== '' ? "\n\n" : '') . $long);
    if ($p['lang'] === '' || !in_array($p['lang'], ['en','es','it','de','fr','pt'], true)) { $dl = uvDetectLang($p['name'] . ' ' . $p['text']); if ($dl !== '') $p['lang'] = $dl; }
    // variantes: limpiar, quitar vacías y etiquetas repetidas
    $vars = []; $labels = [];
    foreach ($p['variants'] as $v) {
        $v['label'] = uvCut(uvDecode($v['label'] ?? ''), 120);
        $v['sku'] = uvNormSpace($v['sku'] ?? '');
        if ($v['label'] === '' && $v['sku'] === '') continue;
        $vars[] = $v; $labels[$v['label']] = ($labels[$v['label']] ?? 0) + 1;
    }
    if (count($vars) === 1 && $vars[0]['label'] === '') $vars = [];
    $p['variants'] = $vars;
    if (!empty($vars) && $p['option_name'] === '') $p['option_name'] = implode(' / ', array_keys($vars[0]['attrs'] ?? [])) ?: 'Modelo';
    if ($p['price'] === null) { foreach ($vars as $v) if ($v['price'] !== null && ($p['price'] === null || $v['price'] < $p['price'])) $p['price'] = $v['price']; }
    // specs sin duplicados y sin las que ya son la opción de variante
    $sp = []; $seenN = [];
    foreach ($p['specs'] as $s) { $k = mb_strtolower(uvNormSpace($s[0]), 'UTF-8'); if ($k === '' || isset($seenN[$k]) || uvNormSpace($s[1]) === '') continue; $seenN[$k] = true; $sp[] = [uvNormSpace($s[0]), uvCut($s[1], 200)]; }
    $p['specs'] = $sp;
    $p['categories'] = array_values(array_unique(array_filter($p['categories'])));
    if ($p['sku'] !== '' && $p['source_id'] !== '' && $p['sku'] === $p['source_id']) $p['sku'] = '';   // id interno de la web, no es un SKU
    $p['sku'] = uvCleanSku($p['sku']);
    foreach ($p['variants'] as $i => $v) $p['variants'][$i]['sku'] = uvCleanSku($v['sku']);
    if ($p['brand'] !== '') $p['brand'] = uvCut(preg_replace('/[\x{2122}\x{00AE}\x{00A9}]/u', '', $p['brand']), 32);
}

/* ═══════════════════════════════ LLM ═══════════════════════════════ */

function uvLlmModel() {
    static $model = null;
    if ($model !== null) return $model;
    $model = UV_LLM_MODEL_DEFAULT;
    $info = null;
    $body = uvHttpGet(UV_LLM_BASE . '/models', $info, 'application/json', 10);
    if ($body !== false && $info['code'] === 200) { $j = json_decode($body, true); if (!empty($j['data'][0]['id'])) $model = (string) $j['data'][0]['id']; }
    return $model;
}
function uvLlmAlive() {
    $info = null;
    $b = uvHttpGet(UV_LLM_BASE . '/models', $info, 'application/json', 8);
    return $b !== false && $info['code'] === 200;
}
function uvLlmCall($system, $user, $maxTokens = 2500, $retries = 2, $temperature = 0.7) {
    if (trim((string) $user) === '') return '';
    // Muestreo recomendado por la tarjeta de Qwen3.8 para modo NO-thinking: temperature 0.7, top_p 0.8, top_k 20.
    // El servidor (SGLang --sampling-defaults openai) NO aplica esos valores por defecto: hay que enviarlos siempre.
    // OJO (verificado 2026-09-04): presence/frequency/repetition_penalty y min_p se IGNORAN en esta build (DFLASH) → no
    // sirven contra los bucles. Defensas reales: stop sequences, max_tokens acotado y validación + reintento (uvLlmFormatValidated).
    $payload = json_encode([
        'model' => uvLlmModel(),
        'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]],
        'temperature' => $temperature, 'max_tokens' => $maxTokens, 'top_p' => 0.8, 'top_k' => 20,
        'stop' => ['</think>', '<think>', '!!!!', '????'],
        'chat_template_kwargs' => ['enable_thinking' => false],
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    for ($i = 0; $i <= $retries; $i++) {
        $ch = curl_init(UV_LLM_BASE . '/chat/completions');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload, CURLOPT_TIMEOUT => 240, CURLOPT_CONNECTTIMEOUT => 10]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);
        if ($resp !== false && $code === 200) {
            $j = json_decode($resp, true);
            $c = $j['choices'][0]['message']['content'] ?? null;
            if (is_string($c) && trim($c) !== '') return trim($c);
        }
        usleep(500000);
    }
    return '';
}
/** Llamada que espera JSON (quita fences ```); null si no se pudo decodificar. */
function uvLlmJson($system, $user, $maxTokens = 1500) {
    $out = uvLlmCall($system, $user, $maxTokens);
    if ($out === '') return null;
    $out = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($out)));
    $j = json_decode($out, true);
    if (!is_array($j) && preg_match('/[\{\[].*[\}\]]/s', $out, $m)) $j = json_decode($m[0], true);
    return is_array($j) ? $j : null;
}
/** Salida degenerada del LLM (repeticiones "!!!!", restos de <think>, etiquetas rotas "<p!!"). */
function uvLlmLooksBroken($s) {
    $s = (string) $s;
    if ($s === '') return true;
    if (preg_match('/<\/?think>/i', $s)) return true;
    if (preg_match('/([^\s\w])\1{5,}/u', $s)) return true;          // "!!!!!!" y similares
    if (preg_match('/(\b\w+\b)(?:\s+\1\b){4,}/u', $s)) return true;   // misma palabra 5 veces seguidas
    if (preg_match('/<p(?![>\s])/i', $s)) return true;              // "<p!!!" etiqueta rota
    if (preg_match('/[\x{FFFD}]/u', $s)) return true;
    return false;
}
function uvFormatLooksValid($html, $minLenInput) {
    if (!is_string($html) || trim($html) === '') return false;
    if (uvLlmLooksBroken($html)) return false;
    if (stripos($html, '<p>') === false && stripos($html, '<strong>') === false) return false;
    if (preg_match('/<(html|body|head|script|style)\b/i', $html)) return false;
    if (preg_match_all('/<p\b[^>]*>/i', $html) !== preg_match_all('/<\/p>/i', $html)) return false;   // <p> sin cerrar = truncado
    if (!preg_match('/<\/p>\s*$/i', trim($html))) return false;                                          // debe acabar en </p>
    if (substr_count($html, '<strong>') !== substr_count($html, '</strong>')) return false;
    $plainOut = mb_strlen(trim(strip_tags($html)), 'UTF-8');
    if ($minLenInput > 200 && $plainOut < $minLenInput * 0.4) return false;
    if ($minLenInput > 0 && $plainOut > $minLenInput * 3 + 400) return false;
    if (preg_match_all('#<p>\s*•\s*(.*?)</p>#is', $html, $m) && count($m[1]) >= 3) {
        $items = array_map(fn($t) => mb_strtolower(trim(strip_tags($t)), 'UTF-8'), $m[1]);
        if (count($items) - count(array_unique($items)) >= max(2, count($items) * 0.4)) return false;
    }
    return true;
}

if (!defined('UV_GLOSSARY_EN_ES')) define('UV_GLOSSARY_EN_ES', 'rope=cabo, line=cabo, shackle=grillete, cleat=cornamusa, fairlead=pasacable, block=polea, winch=winche, halyard=driza, sheet=escota, mast=mástil, masthead=tope de mástil, wind vane=veleta, anemometer=anemómetro, cup=cazoleta, transducer=transductor, depth sounder=sonda, log=corredera, hull=casco, deck=cubierta, hatch=escotilla, stanchion=candelero, pulpit=púlpito, tiller=caña del timón, rudder=timón, keel=quilla, outboard=fueraborda, propeller=hélice, thruster=hélice de maniobra, through-hull=pasacascos, seacock=grifo de fondo, bilge=sentina, bilge pump=bomba de achique, fender=defensa, mooring=amarre, anchor=ancla, chain=cadena, buoy=boya, lifejacket=chaleco salvavidas, harness=arnés, tether=línea de vida, flare=bengala, spare part=repuesto, fitting=herraje, bracket=soporte, mount=soporte, display=pantalla, wiring=cableado, wire=cable, wireless=inalámbrico, battery=batería, charger=cargador, fuse=fusible, switch=interruptor, gauge=indicador, hose=manguera, clamp=abrazadera, valve=válvula, pump=bomba, tank=depósito, cover=funda, cushion=cojín, ladder=escalera, rail=pasamanos');

if (!defined('UV_PROMPT_NAME')) define('UV_PROMPT_NAME', "Eres un traductor especializado en productos náuticos. Recibes el NOMBRE de un producto (puede venir en cualquier idioma) y devuelves SOLO un JSON con dos claves: \"es\" (nombre en español) y \"en\" (nombre en inglés). Máximo 70 caracteres cada uno. Conserva marca, modelos, códigos, medidas y unidades tal cual. Usa terminología náutica precisa (glosario EN→ES: " . UV_GLOSSARY_EN_ES . "). No añadas la marca si no aparece en el nombre. Sin comillas tipográficas, sin comentarios, sin markdown: SOLO el JSON.");

if (!defined('UV_PROMPT_DESC_ES')) define('UV_PROMPT_DESC_ES', "Eres un redactor de fichas de producto de una tienda náutica. Recibes el texto de la ficha original (en cualquier idioma) y devuelves la descripción EN ESPAÑOL como HTML.\n\nREGLAS:\n1. Traduce al español si no lo está (terminología náutica precisa; glosario EN→ES: " . UV_GLOSSARY_EN_ES . "). Conserva TODA la información: números, medidas, unidades, modelos, compatibilidades y avisos. NO resumas, NO inventes nada.\n2. Primer <p>: párrafo introductorio (máx 5 frases por <p>).\n3. Cada característica en su propio <p>• texto</p> (nunca <ul>/<li>); el concepto clave (1-4 palabras) en <strong> seguido de dos puntos.\n4. Si hay más de 6 características, agrúpalas bajo títulos <p><strong>Título</strong></p> (nunca <h1>-<h6>) precedidos de <p>&nbsp;</p>.\n5. Elimina ™ ® ©, eslóganes, textos de envío/devolución/cookies, llamadas a comprar y referencias a 'esta web'.\n6. Etiquetas permitidas: <p>, <strong>, <a>. Prohibidas: <h1>-<h6>, <br>, <div>, <span>, <ul>, <li>, <table>, <ol>, <img>.\n7. Salida: SOLO el HTML, sin markdown ni comentarios.");

if (!defined('UV_PROMPT_DESC_EN')) define('UV_PROMPT_DESC_EN', "You write product datasheets for a nautical store. You receive the original product text (in any language) and return the description IN ENGLISH as HTML.\n\nRULES:\n1. Translate into English if needed (precise nautical terminology). Keep ALL information: numbers, measures, units, models, compatibility notes and warnings. DO NOT summarize, DO NOT invent.\n2. First <p>: intro paragraph (max 5 sentences per <p>).\n3. Each feature in its own <p>• text</p> (never <ul>/<li>); key concept (1-4 words) in <strong> followed by a colon.\n4. If more than 6 features, group them under <p><strong>Title</strong></p> headings (never <h1>-<h6>) preceded by <p>&nbsp;</p>.\n5. Remove ™ ® ©, slogans, shipping/returns/cookie texts, calls to buy and references to 'this website'.\n6. Allowed tags: <p>, <strong>, <a>. Forbidden: <h1>-<h6>, <br>, <div>, <span>, <ul>, <li>, <table>, <ol>, <img>.\n7. Output: ONLY the HTML, no markdown, no comments.");

if (!defined('UV_PROMPT_LABEL_ES')) define('UV_PROMPT_LABEL_ES', "Traduce al ESPAÑOL esta etiqueta corta de variante de un producto náutico (talla, color, modelo, versión, tipo de conexión…). Conserva números, códigos, unidades y nombres de modelo/marca; traduce solo las palabras comunes (wire=cable, wireless=inalámbrico, version=versión, type=tipo, left=izquierda, right=derecha, short=corto, long=largo, black=negro, white=blanco, blue=azul, red=rojo, grey=gris). Responde SOLO con la etiqueta traducida, en una línea, sin comillas ni comentarios.");
if (!defined('UV_PROMPT_LABEL_EN')) define('UV_PROMPT_LABEL_EN', "Translate this short product variant label (size, colour, model, version, connection type…) into ENGLISH. Keep numbers, codes, units and model/brand names; translate only common words. Answer ONLY with the translated label, one line, no quotes, no comments.");
if (!defined('UV_PROMPT_LABELS')) define('UV_PROMPT_LABELS', "Recibes un JSON array con etiquetas de variante de un producto náutico (talla, color, modelo, versión, tipo de conexión…). Devuelve SOLO un JSON array del MISMO tamaño y en el MISMO orden; cada elemento es un objeto {\"es\": \"...\", \"en\": \"...\"}. Conserva números, códigos, unidades, nombres de modelo y de marca; traduce solo palabras comunes (colores, materiales, wire=cable/hilos, wireless=inalámbrico, version=versión, type=tipo, left=izquierda, right=derecha, short=corto, long=largo). Máximo 60 caracteres por etiqueta. Sin markdown ni comentarios.");

/** Diccionario para las claves del bloque de especificaciones (EN → ES). */
function uvSpecKeyEs($k) {
    $map = ['model' => 'Modelo', 'size' => 'Talla', 'colour' => 'Color', 'color' => 'Color', 'weight' => 'Peso', 'length' => 'Longitud', 'width' => 'Anchura', 'height' => 'Altura',
        'material' => 'Material', 'voltage' => 'Voltaje', 'capacity' => 'Capacidad', 'thread' => 'Rosca', 'diameter' => 'Diámetro', 'type' => 'Tipo', 'version' => 'Versión',
        'finish' => 'Acabado', 'power' => 'Potencia', 'brand' => 'Marca', 'dimensions' => 'Dimensiones', 'reference' => 'Referencia', 'connection' => 'Conexión',
        'cable length' => 'Longitud de cable', 'range' => 'Alcance', 'frequency' => 'Frecuencia', 'display' => 'Pantalla', 'mounting' => 'Montaje', 'compatibility' => 'Compatibilidad',
        'warranty' => 'Garantía', 'origin' => 'Origen', 'pack' => 'Pack', 'quantity' => 'Cantidad', 'units' => 'Unidades'];
    $lk = mb_strtolower(trim((string) $k), 'UTF-8');
    return $map[$lk] ?? $k;
}
/** Bloque "Especificaciones" determinista al final de la descripción (formato MB). */
function uvSpecsBlock(array $p, $lang, array $extra = []) {
    $rows = [];
    if (!empty($extra['brand'])) $rows[] = [$lang === 'es' ? 'Marca' : 'Brand', $extra['brand']];
    if (!empty($extra['sku'])) $rows[] = [$lang === 'es' ? 'Referencia' : 'Reference', $extra['sku']];
    foreach ($p['specs'] as $s) $rows[] = [$lang === 'es' ? uvSpecKeyEs($s[0]) : $s[0], $s[1]];
    if (!empty($extra['weight_kg']) && (float) $extra['weight_kg'] > 0 && empty($extra['weight_is_default'])) $rows[] = [$lang === 'es' ? 'Peso' : 'Weight', rtrim(rtrim(number_format((float) $extra['weight_kg'], 3, '.', ''), '0'), '.') . ' kg'];
    if (!empty($p['dimensions'])) $rows[] = [$lang === 'es' ? 'Dimensiones' : 'Dimensions', $p['dimensions']];
    if (empty($rows)) return '';
    $h = '<p>&nbsp;</p><p><strong>' . ($lang === 'es' ? 'Especificaciones' : 'Specifications') . '</strong></p>';
    foreach ($rows as $r) $h .= '<p>• <strong>' . htmlspecialchars(uvNormSpace($r[0])) . ':</strong> ' . htmlspecialchars(uvNormSpace($r[1])) . '</p>';
    return $h;
}
/** Maquetado con validación: hasta 2 intentos (temperatura 0,2 y 0,0). '' si ninguno es válido. */
function uvLlmFormatValidated($prompt, $text, $inLen, &$calls) {
    // tope de tokens proporcional a la entrada (~3 chars/token, ×2,2 por HTML+traducción, +300) → acota el daño si el modelo se embucla
    $maxTok = (int) min(3500, max(500, ceil($inLen / 3.0) * 2.2 + 300));
    foreach ([0.7, 0.5] as $temp) {   // 1º el recomendado por Qwen; el reintento algo más conservador (nunca greedy: bucles)
        $out = uvLlmCall($prompt, $text, $maxTok, 1, $temp); $calls++;
        if (uvFormatLooksValid($out, $inLen)) return $out;
    }
    return '';
}
/** Respuesta de una línea (etiquetas, nombres cortos). '' si está vacía o rota. */
function uvLlmLine($prompt, $text, &$calls) {
    $out = uvLlmCall($prompt, $text, 120, 1, 0.7); $calls++;
    $out = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $out)));
    $out = trim($out, " \t\"'“”«»");
    if ($out === '' || uvLlmLooksBroken($out) || mb_strlen($out, 'UTF-8') > 100) return '';
    return $out;
}
function uvTextToHtml($text) {
    $paras = array_filter(array_map('trim', preg_split("/\n\s*\n/", (string) $text)), fn($x) => $x !== '');
    $h = '';
    foreach ($paras as $pa) {
        $lines = array_filter(array_map('trim', explode("\n", $pa)), fn($x) => $x !== '');
        foreach ($lines as $l) $h .= (preg_match('/^[-•*]\s*/', $l) ? '<p>• ' . htmlspecialchars(preg_replace('/^[-•*]\s*/', '', $l)) . '</p>' : '<p>' . htmlspecialchars($l) . '</p>');
    }
    return $h;
}
function uvApplyBrand($name, $brand, $prefix) {
    $name = uvNormSpace(preg_replace('/[\x{2122}\x{00AE}\x{00A9}]/u', '', uvDecode($name)));
    $brand = uvNormSpace($brand);
    if ($prefix && $brand !== '' && mb_stripos($name, $brand, 0, 'UTF-8') === false) $name = $brand . ' ' . $name;
    return uvCut($name, UV_NAME_MAX);
}
/**
 * Nombres, descripciones y etiquetas de variante (con o sin LLM).
 * Devuelve ['name_es','name_en','desc_es','desc_en','labels'=>[i=>['es','en']],'llm_calls','warnings'=>[]].
 */
function uvBuildTexts(array $p, array $plan, $useLlm, $log = null) {
    $L = is_callable($log) ? $log : function ($m) {};
    $out = ['name_es' => '', 'name_en' => '', 'desc_es' => '', 'desc_en' => '', 'labels' => [], 'llm_calls' => 0, 'warnings' => []];
    $in = $plan['input'];
    $srcName = $in['name'] !== '' ? $in['name'] : $p['name'];
    $brand = $in['brand'];
    $lang = $p['lang'];
    $nameEs = $srcName; $nameEn = $srcName;
    if ($useLlm) {
        $j = uvLlmJson(UV_PROMPT_NAME, $srcName, 200); $out['llm_calls']++;
        if (is_array($j) && !empty($j['es']) && !empty($j['en'])) { $nameEs = uvNormSpace($j['es']); $nameEn = uvNormSpace($j['en']); }
        else $out['warnings'][] = 'El LLM no devolvió el nombre traducido; se usa el original en ambos idiomas.';
    }
    $out['name_es'] = uvApplyBrand($nameEs, $brand, $in['brand_prefix']);
    $out['name_en'] = uvApplyBrand($nameEn, $brand, $in['brand_prefix']);
    $L('Nombre ES: ' . $out['name_es'] . ' | EN: ' . $out['name_en']);

    $text = $p['text'];
    $extra = ['brand' => $brand, 'sku' => $plan['sku_display'] ?? '', 'weight_kg' => $plan['weight_kg'] ?? 0, 'weight_is_default' => !empty($plan['weight_is_default'])];
    if ($text === '') {
        $out['warnings'][] = 'La ficha no trae texto descriptivo: la descripción queda solo con el bloque de especificaciones.';
        $out['desc_es'] = mbReformatDescription(uvSpecsBlock($p, 'es', $extra));
        $out['desc_en'] = mbReformatDescription(uvSpecsBlock($p, 'en', $extra));
    } else {
        $inLen = mb_strlen($text, 'UTF-8');
        $fallback = uvTextToHtml($text);
        if ($useLlm) {
            $es = uvLlmFormatValidated(UV_PROMPT_DESC_ES, $text, $inLen, $out['llm_calls']);
            if ($es === '') { $out['warnings'][] = 'Maquetado ES del LLM no válido tras 2 intentos → texto original sin traducir.'; $es = $fallback; }
            $en = uvLlmFormatValidated(UV_PROMPT_DESC_EN, $text, $inLen, $out['llm_calls']);
            if ($en === '') { $out['warnings'][] = 'Maquetado EN del LLM no válido tras 2 intentos → texto original.'; $en = $fallback; }
        } else {
            $es = $fallback; $en = $fallback;
            if ($lang !== 'es') $out['warnings'][] = 'Sin LLM: la descripción ES queda en el idioma original (' . ($lang ?: '?') . ').';
        }
        // reformat ANTES de añadir las specs: el bloque ya va en formato MB y así la marca conserva sus mayúsculas (NASA, no Nasa)
        $out['desc_es'] = mbReformatDescription($es) . uvSpecsBlock($p, 'es', $extra);
        $out['desc_en'] = mbReformatDescription($en) . uvSpecsBlock($p, 'en', $extra);
        $L('Descripción ES: ' . mb_strlen(strip_tags($out['desc_es']), 'UTF-8') . ' chars | EN: ' . mb_strlen(strip_tags($out['desc_en']), 'UTF-8') . ' chars');
    }
    // etiquetas de variante
    $labels = [];
    foreach ($plan['variants'] as $i => $v) if (!empty($v['include'])) $labels[$i] = $v['label'];
    if (!empty($labels)) {
        $tr = null;
        if ($useLlm) {
            $tr = uvLlmJson(UV_PROMPT_LABELS, json_encode(array_values($labels), JSON_UNESCAPED_UNICODE), (int) min(1800, 120 + 60 * count($labels))); $out['llm_calls']++;
            if (is_array($tr)) {
                $tr = array_values($tr);
                foreach ($tr as $t) if (!is_array($t) || !isset($t['es'], $t['en']) || uvLlmLooksBroken($t['es']) || uvLlmLooksBroken($t['en'])) { $tr = null; break; }
            }
            if (!is_array($tr) || count($tr) !== count($labels)) {
                // el lote JSON no vino bien → una llamada corta por etiqueta e idioma
                $tr = [];
                foreach (array_values($labels) as $lab) {
                    $es = uvLlmLine(UV_PROMPT_LABEL_ES, $lab, $out['llm_calls']);
                    $en = ($lang === 'en') ? $lab : uvLlmLine(UV_PROMPT_LABEL_EN, $lab, $out['llm_calls']);
                    $tr[] = ['es' => $es !== '' ? $es : $lab, 'en' => $en !== '' ? $en : $lab];
                }
                $out['warnings'][] = 'Etiquetas de variante traducidas una a una (el lote JSON del LLM no fue válido).';
            }
        }
        $k = 0;
        foreach ($labels as $i => $lab) {
            $t = $tr[$k++] ?? null;
            $es = (is_array($t) && !empty($t['es'])) ? uvNormSpace($t['es']) : $lab;
            $en = (is_array($t) && !empty($t['en'])) ? uvNormSpace($t['en']) : $lab;
            if (preg_match('/\d/', $lab) && (!preg_match('/\d/', $es) || !preg_match('/\d/', $en))) { $es = $lab; $en = $lab; }   // el LLM perdió números → original
            $out['labels'][$i] = ['es' => uvCut($es, UV_OV_MAX), 'en' => uvCut($en, UV_OV_MAX)];
        }
    }
    return $out;
}

/* ═══════════════════════════════ BD ═══════════════════════════════ */

function uvEnsureLogTable($mysqli) {
    static $done = false;
    if ($done) return;
    $mysqli->query("CREATE TABLE IF NOT EXISTS import_universal_log (
        id int NOT NULL AUTO_INCREMENT,
        products_id int NOT NULL,
        source_url varchar(500) NOT NULL,
        source_norm varchar(300) NOT NULL,
        source_host varchar(120) DEFAULT NULL,
        source_type varchar(32) DEFAULT NULL,
        products_name varchar(120) DEFAULT NULL,
        variants int NOT NULL DEFAULT 0,
        admin_name varchar(96) DEFAULT NULL,
        date_added datetime DEFAULT NULL,
        PRIMARY KEY (id), KEY idx_pid (products_id), KEY idx_norm (source_norm(191))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done = true;
}
function uvRecentImports($mysqli, $limit = 30) {
    uvEnsureLogTable($mysqli);
    $rows = [];
    $r = $mysqli->query("SELECT l.*, p.products_status, p.products_price FROM import_universal_log l LEFT JOIN products p ON p.products_id = l.products_id ORDER BY l.id DESC LIMIT " . (int) $limit);
    if ($r) while ($x = $r->fetch_assoc()) $rows[] = $x;
    return $rows;
}
function uvUrlImported($mysqli, $url) {
    uvEnsureLogTable($mysqli);
    $n = $mysqli->real_escape_string(uvNormUrl($url));
    $r = $mysqli->query("SELECT l.products_id, l.products_name, l.date_added, p.products_status FROM import_universal_log l LEFT JOIN products p ON p.products_id=l.products_id WHERE l.source_norm='$n' ORDER BY l.id DESC LIMIT 1");
    return ($r && ($x = $r->fetch_assoc())) ? $x : null;
}
function uvListManufacturers($mysqli) {
    $out = [];
    $r = $mysqli->query("SELECT manufacturers_id, manufacturers_name FROM manufacturers ORDER BY manufacturers_name");
    if ($r) while ($x = $r->fetch_assoc()) $out[(int) $x['manufacturers_id']] = $x['manufacturers_name'];
    return $out;
}
function uvFindManufacturer($mysqli, $name) {
    $q = $mysqli->real_escape_string(uvNormSpace($name));
    if ($q === '') return 0;
    $r = $mysqli->query("SELECT manufacturers_id FROM manufacturers WHERE LOWER(TRIM(manufacturers_name)) = LOWER('$q') LIMIT 1");
    return ($r && ($x = $r->fetch_assoc())) ? (int) $x['manufacturers_id'] : 0;
}
function uvEnsureManufacturer($mysqli, $name, $dry, &$log) {
    $name = uvCut($name, 32);
    if ($name === '') return 0;
    $id = uvFindManufacturer($mysqli, $name);
    if ($id > 0) return $id;
    if ($dry) { $log[] = "fabricante '$name' (simulación, no creado)"; return 0; }
    $q = $mysqli->real_escape_string($name);
    $mysqli->query("INSERT INTO manufacturers (manufacturers_name, date_added) VALUES ('$q', NOW())");
    $id = (int) $mysqli->insert_id;
    if ($id > 0) {
        foreach ([UV_LANG_ES, UV_LANG_EN] as $lid) $mysqli->query("INSERT IGNORE INTO manufacturers_info (manufacturers_id, languages_id, manufacturers_url) VALUES ($id, $lid, '')");
        $log[] = "fabricante '$name' creado (id=$id)";
    }
    return $id;
}
function uvEnsureCategory($mysqli, $nameEs, $nameEn, $parentId, $status, $dry, &$log) {
    $nm = uvCut($nameEs, 32); $en = uvCut($nameEn ?: $nameEs, 32);
    $parentId = (int) $parentId;
    $qName = $mysqli->real_escape_string($nm);
    $r = $mysqli->query("SELECT c.categories_id FROM categories c INNER JOIN categories_description cd ON cd.categories_id=c.categories_id AND cd.language_id=" . UV_LANG_ES . "
                         WHERE c.parent_id=$parentId AND UPPER(TRIM(cd.categories_name))=UPPER('$qName') LIMIT 1");
    if ($r && ($x = $r->fetch_assoc())) return (int) $x['categories_id'];
    if ($dry) { $log[] = "categoría '$nm' (simulación, no creada)"; return 0; }
    $r = $mysqli->query("SELECT IFNULL(MAX(sort_order),0)+1 nso FROM categories WHERE parent_id=$parentId");
    $nso = (int) (($r && ($x = $r->fetch_assoc())) ? $x['nso'] : 1);
    $mysqli->query("INSERT INTO categories (parent_id, sort_order, date_added, last_modified, categories_status) VALUES ($parentId, $nso, NOW(), NOW(), " . (int) $status . ")");
    $newId = (int) $mysqli->insert_id;
    $mysqli->query("INSERT INTO categories_description (categories_id, language_id, categories_name) VALUES ($newId, " . UV_LANG_ES . ", '$qName')");
    $mysqli->query("INSERT INTO categories_description (categories_id, language_id, categories_name) VALUES ($newId, " . UV_LANG_EN . ", '" . $mysqli->real_escape_string($en) . "')");
    $log[] = "categoría '$nm' creada (id=$newId, parent=$parentId, status=$status)";
    return $newId;
}
function uvFindOrCreateOptionValue($mysqli, $optionId, $nameEs, $nameEn) {
    $optionId = (int) $optionId;
    $esSafe = $mysqli->real_escape_string($nameEs);
    $q = $mysqli->query("SELECT pov.products_options_values_id FROM products_options_values pov
        INNER JOIN products_options_values_to_products_options l ON l.products_options_values_id = pov.products_options_values_id
        WHERE l.products_options_id = $optionId AND pov.language_id = " . UV_LANG_ES . " AND pov.products_options_values_name = '$esSafe' LIMIT 1");
    if ($q && ($row = $q->fetch_assoc())) return (int) $row['products_options_values_id'];
    $nq = $mysqli->query("SELECT IFNULL(MAX(products_options_values_id),0)+1 nid FROM products_options_values");
    $newId = (int) (($nq && ($x = $nq->fetch_assoc())) ? $x['nid'] : 1);
    $enSafe = $mysqli->real_escape_string($nameEn !== '' ? $nameEn : $nameEs);
    $mysqli->query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($newId, " . UV_LANG_ES . ", '$esSafe', '')");
    $mysqli->query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($newId, " . UV_LANG_EN . ", '$enSafe', '')");
    $mysqli->query("INSERT INTO products_options_values_to_products_options (products_options_id, products_options_values_id) VALUES ($optionId, $newId)");
    return $newId;
}
/** Conflictos de duplicado: SKU (por fabricante/origin universal), EAN (global), lista negra, URL ya importada. */
function uvConflicts($mysqli, array $skus, array $eans, $mfgId, $url) {
    $c = [];
    $skus = array_values(array_unique(array_filter(array_map(fn($s) => strtolower(trim((string) $s)), $skus))));
    $eans = array_values(array_unique(array_filter(array_map(fn($s) => trim((string) $s), $eans))));
    $mfgId = (int) $mfgId;
    if (!empty($skus)) {
        $in = "'" . implode("','", array_map([$mysqli, 'real_escape_string'], $skus)) . "'";
        $f = $mfgId > 0 ? "(p.manufacturers_id = $mfgId OR p.products_import_origin LIKE '" . UV_ORIGIN . "%')" : "(p.products_import_origin LIKE '" . UV_ORIGIN . "%')";
        $r = $mysqli->query("SELECT p.products_id, LOWER(p.products_model) k FROM products p WHERE LOWER(p.products_model) IN ($in) AND $f
                             UNION SELECT p.products_id, LOWER(p.reference_prov) FROM products p WHERE LOWER(p.reference_prov) IN ($in) AND $f
                             UNION SELECT pa.products_id, LOWER(pa.reference) FROM products_attributes pa INNER JOIN products p ON p.products_id=pa.products_id WHERE LOWER(pa.reference) IN ($in) AND $f
                             UNION SELECT pa.products_id, LOWER(pa.reference_prov) FROM products_attributes pa INNER JOIN products p ON p.products_id=pa.products_id WHERE LOWER(pa.reference_prov) IN ($in) AND $f");
        if ($r) while ($x = $r->fetch_assoc()) $c[] = "SKU '" . $x['k'] . "' ya existe en el producto #" . $x['products_id'] . " (mismo fabricante/origen universal)";
    }
    if (!empty($eans)) {
        $in = "'" . implode("','", array_map([$mysqli, 'real_escape_string'], $eans)) . "'";
        $r = $mysqli->query("SELECT products_id, product_ean k FROM products WHERE product_ean IN ($in)
                             UNION SELECT products_id, products_attributes_ean FROM products_attributes WHERE products_attributes_ean IN ($in)");
        if ($r) while ($x = $r->fetch_assoc()) $c[] = "EAN " . $x['k'] . " ya existe en el producto #" . $x['products_id'];
    }
    if (function_exists('fb_blacklist_has')) {
        foreach (array_merge($skus, $eans) as $k) if (fb_blacklist_has($k)) $c[] = "'" . $k . "' está en la lista negra de reimportación (borrado a propósito)";
    }
    $prev = uvUrlImported($mysqli, $url);
    if ($prev) $c[] = "Esta URL ya se importó el " . $prev['date_added'] . " → producto #" . $prev['products_id'] . " (" . ($prev['products_name'] ?? '') . ", status=" . var_export($prev['products_status'], true) . ")";
    return array_values(array_unique($c));
}

/* ── imágenes ── */
function uvDownloadImage($url, $destAbs, $referer = '') {
    $url = trim((string) $url);
    if ($url === '' || !preg_match('#^https?://#i', $url)) return false;
    $url = str_replace(' ', '%20', $url);
    $fp = fopen($destAbs, 'wb');
    if (!$fp) return false;
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_FILE => $fp, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_USERAGENT => uvUA(), CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => ['Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8'] + ($referer !== '' ? [1 => 'Referer: ' . $referer] : [])]);
    $ok = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);
    fclose($fp);
    $ok = $ok && $code === 200 && filesize($destAbs) >= UV_IMG_MIN_BYTES && uvToJpeg($destAbs);
    if (!$ok) @unlink($destAbs);
    return $ok;
}
/** Deja el fichero como JPEG (convierte PNG/WebP/GIF con GD sobre fondo blanco). false si no es una imagen. */
function uvToJpeg($path) {
    $info = @getimagesize($path);
    if ($info === false || $info[0] < 40 || $info[1] < 40) return false;
    if ($info[2] === IMAGETYPE_JPEG) return true;
    if (!function_exists('imagecreatefromstring')) return true;
    $src = @imagecreatefromstring((string) file_get_contents($path));
    if (!$src) return false;
    $w = imagesx($src); $h = imagesy($src);
    $dst = imagecreatetruecolor($w, $h);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);
    imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
    $ok = imagejpeg($dst, $path, 90);
    return (bool) $ok;
}
function uvDownloadImagesToTmp(array $urls, $max, $referer = '') {
    $tmp = []; $seen = [];
    foreach ($urls as $u) {
        if (count($tmp) >= $max) break;
        $u = trim((string) $u);
        if ($u === '' || isset($seen[$u])) continue;
        $seen[$u] = true;
        $abs = UV_IMG_ABS_DIR . 'uv-tmp-' . uniqid('', true) . '.jpg';
        if (uvDownloadImage($u, $abs, $referer)) $tmp[] = $abs;
    }
    return $tmp;
}

/* ═══════════════════════════════ plan ═══════════════════════════════ */

/** Normaliza la entrada del formulario. */
function uvNormalizeInput(array $raw) {
    $num = function ($v) { $v = str_replace(',', '.', trim((string) $v)); return ($v !== '' && is_numeric($v)) ? round((float) $v, 4) : 0.0; };
    return [
        'pvp_gross'    => $num($raw['pvp_gross'] ?? 0),
        'g1_gross'     => $num($raw['g1_gross'] ?? 0),
        'cost'         => $num($raw['cost'] ?? 0),
        'ean'          => uvNormSpace($raw['ean'] ?? ''),
        'brand'        => uvCut($raw['brand'] ?? '', 32),
        'sku'          => uvCut($raw['sku'] ?? '', UV_REF_MAX),
        'weight'       => $num($raw['weight'] ?? 0),
        'tax_class'    => in_array((int) ($raw['tax_class'] ?? 1), [1, 2, 3], true) ? (int) $raw['tax_class'] : 1,
        'brand_prefix' => !empty($raw['brand_prefix']) ? 1 : 0,
        'use_llm'      => !empty($raw['use_llm']) ? 1 : 0,
        'activate'     => !empty($raw['activate']) ? 1 : 0,
        'with_variants'=> !empty($raw['with_variants']) ? 1 : 0,
        'name'         => uvCut($raw['name'] ?? '', UV_NAME_MAX),
    ];
}
/** Producto extraído + entrada del usuario → plan editable (precios por variante, imágenes, opción). */
function uvBuildPlan(array $product, array $input) {
    $in = uvNormalizeInput($input);
    if ($in['brand'] === '') $in['brand'] = $product['brand'];
    if ($in['sku'] === '') $in['sku'] = uvCut($product['sku'], UV_REF_MAX);
    if ($in['ean'] === '') $in['ean'] = $product['ean'];
    $vat = uvVatRateForTaxClass($in['tax_class']);
    $plan = ['token' => bin2hex(random_bytes(8)), 'created' => date('Y-m-d H:i:s'), 'url' => $product['url'], 'product' => $product, 'input' => $in, 'vat' => $vat];
    $weight = $in['weight'] > 0 ? $in['weight'] : (($product['weight_kg'] ?? null) > 0 ? (float) $product['weight_kg'] : 1.0);
    $plan['weight_kg'] = round($weight, 3);
    $plan['weight_is_default'] = ($in['weight'] <= 0 && !(($product['weight_kg'] ?? null) > 0));
    $plan['sku_display'] = $in['sku'];
    $imgs = [];
    foreach ($product['images'] as $u) $imgs[] = ['url' => $u, 'include' => 1, 'kind' => 'gallery'];
    foreach ($product['desc_images'] as $u) $imgs[] = ['url' => $u, 'include' => 1, 'kind' => 'descripcion'];
    $plan['images'] = $imgs;
    // variantes: PVP de cada una = PVP tecleado × (precio web variante / precio web más barato), bruto en múltiplos de 0,05
    $vars = [];
    if ($in['with_variants'] && count($product['variants']) >= 2) {
        $base = null;
        foreach ($product['variants'] as $v) if ($v['price'] !== null && $v['price'] > 0 && ($base === null || $v['price'] < $base)) $base = $v['price'];
        foreach ($product['variants'] as $i => $v) {
            $ratio = ($base !== null && $v['price'] !== null && $v['price'] > 0) ? ($v['price'] / $base) : 1.0;
            $pvp = ($ratio === 1.0) ? $in['pvp_gross'] : uvNickelGross($in['pvp_gross'] * $ratio);
            $g1  = $in['g1_gross'] > 0 ? (($ratio === 1.0) ? $in['g1_gross'] : uvNickelGross($in['g1_gross'] * $ratio)) : 0.0;
            $vars[] = ['idx' => $i, 'include' => 1, 'label' => $v['label'] !== '' ? $v['label'] : $v['sku'], 'sku' => uvCut($v['sku'], UV_REF_MAX), 'src_price' => $v['price'],
                       'ratio' => round($ratio, 4), 'pvp_gross' => round($pvp, 2), 'g1_gross' => round($g1, 2), 'ean' => $v['ean'],
                       'weight' => ($v['weight_kg'] ?? null) > 0 ? (float) $v['weight_kg'] : $plan['weight_kg'], 'attrs' => $v['attrs'], 'image' => $v['image']];
        }
    }
    $plan['variants'] = $vars;
    $attrNames = [];
    foreach ($vars as $v) foreach (array_keys($v['attrs'] ?? []) as $an) $attrNames[$an] = true;
    $plan['option_name'] = $product['option_name'] ?: implode(' / ', array_keys($attrNames));
    $plan['option_id'] = (count($attrNames) === 1) ? uvOptionIdForAttribute(array_key_first($attrNames)) : UV_OPTION_DEFAULT;
    return $plan;
}
/** Aplica al plan lo editado en el formulario de revisión (imágenes, variantes, entrada). */
function uvApplyReview(array $plan, array $post) {
    if (isset($post['input']) && is_array($post['input'])) {
        $merged = array_merge($plan['input'], $post['input']);
        foreach (['brand_prefix', 'use_llm', 'activate', 'with_variants'] as $k) $merged[$k] = !empty($post['input'][$k]) ? 1 : 0;
        $plan['input'] = uvNormalizeInput($merged);
        $plan['vat'] = uvVatRateForTaxClass($plan['input']['tax_class']);
        if ($plan['input']['weight'] > 0) { $plan['weight_kg'] = $plan['input']['weight']; $plan['weight_is_default'] = false; }
        $plan['sku_display'] = $plan['input']['sku'];
    }
    $inc = isset($post['img']) && is_array($post['img']) ? $post['img'] : [];
    foreach ($plan['images'] as $i => $im) $plan['images'][$i]['include'] = isset($post['img_submitted']) ? (isset($inc[$i]) ? 1 : 0) : $im['include'];
    if (isset($post['img_order']) && is_array($post['img_order'])) {
        $order = array_map('intval', $post['img_order']);
        $new = [];
        foreach ($order as $i) if (isset($plan['images'][$i])) $new[] = $plan['images'][$i];
        foreach ($plan['images'] as $i => $im) if (!in_array($i, $order, true)) $new[] = $im;
        $plan['images'] = $new;
    }
    if (isset($post['var']) && is_array($post['var'])) {
        foreach ($plan['variants'] as $i => $v) {
            $pv = $post['var'][$i] ?? null;
            if (!is_array($pv)) continue;
            $plan['variants'][$i]['include'] = !empty($pv['include']) ? 1 : 0;
            if (isset($pv['label'])) $plan['variants'][$i]['label'] = uvCut($pv['label'], 120);
            if (isset($pv['sku'])) $plan['variants'][$i]['sku'] = uvCut($pv['sku'], UV_REF_MAX);
            if (isset($pv['ean'])) $plan['variants'][$i]['ean'] = uvNormSpace($pv['ean']);
            foreach (['pvp_gross', 'g1_gross', 'weight'] as $k) if (isset($pv[$k])) { $x = str_replace(',', '.', trim((string) $pv[$k])); $plan['variants'][$i][$k] = ($x !== '' && is_numeric($x)) ? round((float) $x, 3) : 0.0; }
        }
    }
    if (!$plan['input']['with_variants']) foreach ($plan['variants'] as $i => $v) $plan['variants'][$i]['include'] = 0;
    return $plan;
}
function uvSavePlan(array $plan) {
    if (!is_dir(UV_CACHE_DIR)) @mkdir(UV_CACHE_DIR, 0775, true);
    foreach (glob(UV_CACHE_DIR . 'plan_*.json') ?: [] as $old) if (filemtime($old) < time() - 7 * 86400) @unlink($old);   // planes de más de 7 días
    $f = UV_CACHE_DIR . 'plan_' . preg_replace('/[^a-f0-9]/', '', $plan['token']) . '.json';
    return @file_put_contents($f, json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false;
}
function uvLoadPlan($token) {
    $t = preg_replace('/[^a-f0-9]/', '', (string) $token);
    if ($t === '') return null;
    $f = UV_CACHE_DIR . 'plan_' . $t . '.json';
    if (!file_exists($f)) return null;
    $j = json_decode((string) file_get_contents($f), true);
    return is_array($j) ? $j : null;
}
/** Validación previa: errores bloqueantes (sin PVP, EAN inválido, sin imagen…). */
function uvValidatePlan(array $plan) {
    $e = [];
    $in = $plan['input'];
    if ($in['pvp_gross'] <= 0) $e[] = 'Falta el PVP (con IVA).';
    if ($in['ean'] !== '' && uvNormalizeEan($in['ean']) === '') $e[] = 'El EAN "' . $in['ean'] . '" no es válido (13 dígitos con checksum correcto, o UPC-A de 12).';
    $nImg = 0; foreach ($plan['images'] as $im) if (!empty($im['include'])) $nImg++;
    if ($nImg === 0) $e[] = 'No hay ninguna imagen seleccionada (regla de la tienda: sin imagen no se importa).';
    $inc = array_values(array_filter($plan['variants'], fn($v) => !empty($v['include'])));
    foreach ($inc as $v) {
        if ($v['pvp_gross'] <= 0) $e[] = 'La variante "' . $v['label'] . '" no tiene PVP.';
        if ($v['ean'] !== '' && uvNormalizeEan($v['ean']) === '') $e[] = 'EAN inválido en la variante "' . $v['label'] . '".';
    }
    $labels = array_map(fn($v) => mb_strtolower(uvNormSpace($v['label']), 'UTF-8'), $inc);
    if (count($labels) !== count(array_unique($labels))) $e[] = 'Hay variantes con la misma etiqueta: desambigúalas antes de importar.';
    $skus = array_filter(array_map(fn($v) => strtolower($v['sku']), $inc));
    if (count($skus) !== count(array_unique($skus))) $e[] = 'Hay variantes con el mismo SKU.';
    $eans = array_filter(array_map(fn($v) => uvNormalizeEan($v['ean']), $inc));
    if (count($eans) !== count(array_unique($eans))) $e[] = 'Hay variantes con el mismo EAN.';
    return $e;
}

/* ═══════════════════════════════ ejecución ═══════════════════════════════ */

/**
 * Ejecuta el plan. $opts: simulate (no toca BD ni descarga), force (ignora conflictos), admin (nombre).
 * Devuelve ['ok'=>bool,'pid'=>int,'errors'=>[],'conflicts'=>[],'texts'=>[...],'created'=>[]].
 */
function uvExecutePlan($mysqli, array $plan, array $opts, $log = null) {
    $L = is_callable($log) ? $log : function ($m) {};
    $res = ['ok' => false, 'pid' => 0, 'errors' => [], 'conflicts' => [], 'texts' => null, 'created' => []];
    $simulate = !empty($opts['simulate']);
    $p = $plan['product']; $in = $plan['input']; $vat = (float) $plan['vat'];
    $errs = uvValidatePlan($plan);
    if (!empty($errs)) { $res['errors'] = $errs; foreach ($errs as $e) $L('ERROR: ' . $e); return $res; }

    $inc = array_values(array_filter($plan['variants'], fn($v) => !empty($v['include'])));
    $isFamily = count($inc) >= 2;
    if (count($inc) === 1) {   // una sola variante marcada → producto suelto con sus datos
        $only = $inc[0];
        if ($only['sku'] !== '') $in['sku'] = $only['sku'];
        if ($only['ean'] !== '') $in['ean'] = $only['ean'];
        if ($only['pvp_gross'] > 0) $in['pvp_gross'] = $only['pvp_gross'];
        if ($only['g1_gross'] > 0) $in['g1_gross'] = $only['g1_gross'];
        $plan['input'] = $in;
        $inc = [];
    }
    $skus = [$in['sku']]; $eans = [uvNormalizeEan($in['ean'])];
    foreach ($inc as $v) { $skus[] = $v['sku']; $eans[] = uvNormalizeEan($v['ean']); }
    $mfgId = uvFindManufacturer($mysqli, $in['brand']);
    $conf = uvConflicts($mysqli, $skus, $eans, $mfgId, $plan['url']);
    if (!empty($conf)) {
        $res['conflicts'] = $conf;
        foreach ($conf as $c) $L(($opts['force'] ?? false ? 'AVISO' : 'CONFLICTO') . ': ' . $c);
        if (empty($opts['force'])) { $L('Importación detenida por duplicados. Marca "forzar" si aun así quieres importarlo.'); return $res; }
    }

    // textos (LLM)
    $useLlm = !empty($in['use_llm']);
    if ($useLlm && !uvLlmAlive()) { $L('AVISO: el LLM (' . UV_LLM_BASE . ') no responde → se importa SIN traducir ni maquetar.'); $useLlm = false; }
    if ($useLlm) $L('LLM: ' . uvLlmModel());
    $t0 = microtime(true);
    $texts = uvBuildTexts($p, $plan, $useLlm, $L);
    foreach ($texts['warnings'] as $w) $L('AVISO: ' . $w);
    $res['texts'] = $texts;
    $L(sprintf('Textos listos en %.1fs (%d llamadas LLM)', microtime(true) - $t0, $texts['llm_calls']));

    // precios
    $price = uvGrossToNet($in['pvp_gross'], $vat);
    $cost  = $in['cost'] > 0 ? round($in['cost'], 4) : 0.0;
    $g1    = $in['g1_gross'] > 0 ? uvGrossToNet($in['g1_gross'], $vat) : ($cost > 0 ? uvRoundToNickel(uvCalcG1($price, $cost), $vat) : 0.0);
    $L(sprintf('Precio: PVP %.2f€ IVA inc → neto %.4f | coste %.4f | G1 %s', $in['pvp_gross'], $price, $cost, $g1 > 0 ? sprintf('%.4f neto (%.2f€ IVA inc)', $g1, $g1 * (1 + $vat)) : 'sin tarifa (verá el PVP)'));
    $famRows = [];
    if ($isFamily) {
        usort($inc, fn($a, $b) => $a['pvp_gross'] <=> $b['pvp_gross']);
        foreach ($inc as $k => $v) {
            $vp = uvGrossToNet($v['pvp_gross'], $vat);
            $vc = ($cost > 0 && !empty($v['ratio'])) ? round($cost * (float) $v['ratio'], 4) : $cost;
            $vg = $v['g1_gross'] > 0 ? uvGrossToNet($v['g1_gross'], $vat) : ($vc > 0 ? uvRoundToNickel(uvCalcG1($vp, $vc), $vat) : 0.0);
            $famRows[] = $v + ['_price' => $vp, '_cost' => $vc, '_g1' => $vg, '_ean' => uvNormalizeEan($v['ean']), '_weight' => ($v['weight'] > 0 ? (float) $v['weight'] : (float) $plan['weight_kg'])];
        }
        $price = $famRows[0]['_price']; $cost = $famRows[0]['_cost']; $g1 = $famRows[0]['_g1'];
        foreach ($famRows as $r) $L(sprintf('  variante "%s" sku=%s PVP %.2f€ (neto %.4f, Δ%+.4f) G1 %s EAN %s', $r['label'], $r['sku'] ?: '-', $r['pvp_gross'], $r['_price'], $r['_price'] - $price, $r['_g1'] > 0 ? sprintf('%.4f', $r['_g1']) : '-', $r['_ean'] ?: '(interno)'));
    }
    $parentWeight = $isFamily ? (float) $famRows[0]['_weight'] : (float) $plan['weight_kg'];
    $parentSku = $isFamily ? ($famRows[0]['sku'] !== '' ? $famRows[0]['sku'] : $in['sku']) : $in['sku'];
    $masterEan = $isFamily ? '' : uvNormalizeEan($in['ean']);
    $imgUrls = []; foreach ($plan['images'] as $im) if (!empty($im['include'])) $imgUrls[] = $im['url'];
    if ($isFamily) foreach ($famRows as $r) if (!empty($r['image']) && !in_array($r['image'], $imgUrls, true)) $imgUrls[] = $r['image'];
    $status = !empty($in['activate']) ? 1 : 2;
    $L('Categoría: ' . UV_CATEGORY_ES . ' | fabricante: ' . ($in['brand'] !== '' ? $in['brand'] . ($mfgId ? " (id=$mfgId)" : ' (se creará)') : 'sin marca') . ' | status=' . $status . ' | peso ' . $parentWeight . ' kg | imágenes ' . count($imgUrls));

    if ($simulate) {
        $L('SIMULACIÓN: no se escribe en BD ni se descargan imágenes.');
        $res['ok'] = true;
        return $res;
    }

    if (!is_dir(UV_IMG_ABS_DIR)) @mkdir(UV_IMG_ABS_DIR, 0775, true);
    $tmpFiles = uvDownloadImagesToTmp($imgUrls, UV_MAX_SUBIMAGES + 1, $plan['url']);
    if (empty($tmpFiles)) { $res['errors'][] = 'Ninguna imagen se pudo descargar (la tienda no importa productos sin imagen).'; $L('ERROR: ' . end($res['errors'])); return $res; }
    $L('Imágenes descargadas: ' . count($tmpFiles) . ' de ' . count($imgUrls));

    $created = [];
    $mysqli->begin_transaction();
    try {
        if ($mfgId <= 0 && $in['brand'] !== '') $mfgId = uvEnsureManufacturer($mysqli, $in['brand'], false, $created);
        $catId = uvEnsureCategory($mysqli, UV_CATEGORY_ES, UV_CATEGORY_EN, 0, 0, false, $created);
        if ($catId <= 0) throw new Exception('no se pudo obtener/crear la categoría ' . UV_CATEGORY_ES);
        $qModel = $mysqli->real_escape_string(uvCut($parentSku, UV_MODEL_MAX));
        $qRef   = $mysqli->real_escape_string(uvCut($parentSku, UV_REF_MAX));
        $qEan   = $mysqli->real_escape_string($masterEan);
        $sql = "INSERT INTO products (products_quantity, check_stock, products_model, products_image, products_price, products_cost, products_date_added, products_weight, products_status, products_tax_class_id, manufacturers_id, product_ean, reference_prov, products_import_origin)
                VALUES (0, 0, '$qModel', '', " . number_format($price, 4, '.', '') . ", " . number_format($cost, 4, '.', '') . ", NOW(), " . number_format($parentWeight, 3, '.', '') . ", $status, " . (int) $in['tax_class'] . ", " . (int) $mfgId . ", '$qEan', '$qRef', '" . UV_ORIGIN . "')";
        if (!$mysqli->query($sql)) throw new Exception('products: ' . $mysqli->error);
        $pid = (int) $mysqli->insert_id;
        if ($parentSku === '') { $gen = 'UV' . $pid; $mysqli->query("UPDATE products SET products_model='$gen', reference_prov='$gen' WHERE products_id=$pid"); $parentSku = $gen; }
        foreach ([[UV_LANG_ES, $texts['name_es'], $texts['desc_es']], [UV_LANG_EN, $texts['name_en'], $texts['desc_en']]] as $d) {
            if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . (int) $d[0] . ", '" . $mysqli->real_escape_string($d[1]) . "', '" . $mysqli->real_escape_string($d[2]) . "', 0)")) throw new Exception('products_description: ' . $mysqli->error);
        }
        if (!$mysqli->query("INSERT INTO products_to_categories (products_id, categories_id) VALUES ($pid, $catId)")) throw new Exception('p2c: ' . $mysqli->error);
        if ($g1 > 0 && !$mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . UV_G1_GROUP . ", $pid, " . number_format($g1, 4, '.', '') . ", 1, 1)")) throw new Exception('products_groups: ' . $mysqli->error);

        // imágenes definitivas
        $slug = uvSlugify($in['brand'] !== '' ? preg_replace('/^' . preg_quote($in['brand'], '/') . '\s+/iu', '', $texts['name_es']) : $texts['name_es']);
        $final = [];
        foreach ($tmpFiles as $i => $tmp) {
            $name = $slug . '-' . $pid . ($i === 0 ? '' : '-' . ($i + 1)) . '.jpg';
            if (@rename($tmp, UV_IMG_ABS_DIR . $name)) $final[] = $name; else @unlink($tmp);
        }
        if (empty($final)) throw new Exception('no se pudieron guardar las imágenes');
        $main = array_shift($final);
        $mysqli->query("UPDATE products SET products_image='" . $mysqli->real_escape_string($main) . "'" . (!empty($final) ? ", products_subimages='" . $mysqli->real_escape_string(json_encode($final, JSON_UNESCAPED_SLASHES)) . "'" : '') . " WHERE products_id=$pid");

        $nVar = 0;
        if ($isFamily) {
            $oid = (int) $plan['option_id'];
            $ovUsed = [];
            foreach ($famRows as $k => $r) {
                $lab = $texts['labels'][$r['idx']] ?? ['es' => $r['label'], 'en' => $r['label']];
                $es = uvCut($lab['es'], UV_OV_MAX); $en = uvCut($lab['en'], UV_OV_MAX);
                $vsku = $r['sku'] !== '' ? $r['sku'] : ($parentSku . '-' . ($k + 1));
                $ovId = uvFindOrCreateOptionValue($mysqli, $oid, $es, $en);
                if (isset($ovUsed[$ovId])) { $es = uvFitName($es, ' · ' . $vsku, UV_OV_MAX); $en = uvFitName($en, ' · ' . $vsku, UV_OV_MAX); $ovId = uvFindOrCreateOptionValue($mysqli, $oid, $es, $en); }
                $ovUsed[$ovId] = true;
                $delta = round($r['_price'] - $price, 4); $pfx = $delta < 0 ? '-' : '+';
                $wd = round($r['_weight'] - $parentWeight, 3); $wpfx = $wd < 0 ? '-' : '+';
                $qv = $mysqli->real_escape_string(uvCut($vsku, UV_REF_MAX));
                if (!$mysqli->query("INSERT INTO products_attributes SET products_id=$pid, options_id=$oid, options_values_id=$ovId,
                        options_values_price=" . number_format(abs($delta), 4, '.', '') . ", price_prefix='$pfx', reference='$qv', reference_prov='$qv',
                        products_attributes_ean='" . $mysqli->real_escape_string($r['_ean']) . "', options_values_weight=" . number_format(abs($wd), 3, '.', '') . ", weight_prefix='$wpfx', products_options_sort_order=" . ($k + 1)))
                    throw new Exception('products_attributes: ' . $mysqli->error);
                $paId = (int) $mysqli->insert_id;
                if ($r['_ean'] === '') { $ie = uvInternalEan13($paId); if ($ie !== '') $mysqli->query("UPDATE products_attributes SET products_attributes_ean='$ie' WHERE products_attributes_id=$paId"); }
                if ($g1 > 0 && $r['_g1'] > 0) {
                    $gd = round($r['_g1'] - $g1, 4); $gpfx = $gd < 0 ? '-' : '+';
                    if (!$mysqli->query("INSERT INTO products_attributes_groups (products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id, options_values_weight, weight_prefix) VALUES ($paId, " . UV_G1_GROUP . ", " . number_format(abs($gd), 4, '.', '') . ", '$gpfx', $pid, 0, '+')")) throw new Exception('products_attributes_groups: ' . $mysqli->error);
                }
                $mysqli->query("INSERT INTO products_stock (products_id, products_stock_attributes, products_stock_quantity, products_stock_cost) VALUES ($pid, '$oid-$ovId', 0, " . number_format($r['_cost'], 4, '.', '') . ")");
                $nVar++;
            }
        }
        $mysqli->commit();
        if (!$isFamily && $masterEan === '') { $ie = uvInternalEan13($pid); if ($ie !== '') $mysqli->query("UPDATE products SET product_ean='$ie' WHERE products_id=$pid AND (product_ean IS NULL OR product_ean='')"); }
        uvEnsureLogTable($mysqli);
        $mysqli->query("INSERT INTO import_universal_log (products_id, source_url, source_norm, source_host, source_type, products_name, variants, admin_name, date_added) VALUES ($pid, '" . $mysqli->real_escape_string(uvCut($plan['url'], 500)) . "', '" . $mysqli->real_escape_string(uvNormUrl($plan['url'])) . "', '" . $mysqli->real_escape_string(uvCut($p['host'], 120)) . "', '" . $mysqli->real_escape_string($p['source']) . "', '" . $mysqli->real_escape_string(uvCut($texts['name_es'], 120)) . "', $nVar, '" . $mysqli->real_escape_string(uvCut($opts['admin'] ?? '', 96)) . "', NOW())");
        $res['ok'] = true; $res['pid'] = $pid; $res['created'] = $created;
        foreach ($created as $c) $L('Creado: ' . $c);
        $L(sprintf('OK pid=%d %s "%s" imágenes=%d status=%d', $pid, $isFamily ? "FAMILIA ($nVar variantes)" : 'SUELTO', $texts['name_es'], 1 + count($final), $status));
    } catch (Throwable $e) {
        $mysqli->rollback();
        foreach ($tmpFiles as $t) if (file_exists($t)) @unlink($t);
        $res['errors'][] = $e->getMessage();
        $L('ERROR (rollback): ' . $e->getMessage());
    }
    return $res;
}

} // if !function_exists
