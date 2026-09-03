<?php
/**
 * openai_feed_build.php — Genera fm-feeds/francobordo_openai.txt a partir del
 * feed Google (francobordo.txt) con el dialecto de OpenAI (developers.openai.com
 * /commerce/specs/file-upload/products):
 *   - availability con guion bajo: in_stock / out_of_stock (Google usa "in stock")
 *   - columnas extra obligatorias: is_eligible_search=true, is_eligible_checkout=false
 *     (Instant Checkout = solo EEUU/ACP) e is_ads_eligible=true
 *   - gtin solo GS1 real (mismo criterio que jsonld_seo_patch: 13 dig + checksum +
 *     fuera de 2x/02/04/05/98x/99x); si no, vacío y queda mpn+brand
 *   - title <=150 y description <=5000 (limites duros de la spec)
 *   - columnas lean: solo las canonicas de la spec (fuera shipping_weight/label)
 * Cron: tras cada regeneracion de feedmachine (09:40 / 21:40).
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

const SRC = '/home/francobordo/public_html/fm-feeds/francobordo.txt';
const DST = '/home/francobordo/public_html/fm-feeds/francobordo_openai.txt';

function oa_is_gs1_ean13($ean) {
    $ean = trim((string)$ean);
    if (!preg_match('/^[0-9]{13}$/', $ean)) return false;
    if (preg_match('/^(2|0[245]|9[89])/', $ean)) return false;
    $sum = 0;
    for ($i = 0; $i < 12; $i++) { $d = (int)$ean[$i]; $sum += ($i % 2 === 0) ? $d : $d * 3; }
    return ((10 - ($sum % 10)) % 10) === (int)$ean[12];
}

$in = fopen(SRC, 'r');
if (!$in) { fwrite(STDERR, "no puedo leer " . SRC . "\n"); exit(1); }
$tmp = DST . '.tmp';
$out = fopen($tmp, 'w');
if (!$out) { fwrite(STDERR, "no puedo escribir $tmp\n"); exit(1); }

$header = fgets($in);
if ($header === false) { fwrite(STDERR, "feed origen vacio\n"); exit(1); }
$cols = array_flip(array_map('trim', explode("\t", rtrim($header, "\r\n"))));
foreach (array('id', 'title', 'price', 'link', 'image_link', 'description', 'brand', 'availability') as $req) {
    if (!isset($cols[$req])) { fwrite(STDERR, "falta columna $req en el feed origen\n"); exit(1); }
}

$OUT_COLS = array('id', 'title', 'description', 'link', 'image_link', 'price', 'availability',
                  'brand', 'gtin', 'mpn', 'condition', 'product_type', 'google_product_category',
                  'is_eligible_search', 'is_eligible_checkout', 'is_ads_eligible');
fwrite($out, implode("\t", $OUT_COLS) . "\n");

$n = 0; $sinGtin = 0; $sinPrecio = 0; $brandFallback = 0; $descFallback = 0;
while (($line = fgets($in)) !== false) {
    $f = explode("\t", rtrim($line, "\r\n"));
    if (count($f) < 2) continue;
    $get = function ($name) use ($f, $cols) { return isset($cols[$name], $f[$cols[$name]]) ? $f[$cols[$name]] : ''; };

    $av = strtolower(trim($get('availability')));
    $av = ($av === 'in stock' || $av === 'in_stock') ? 'in_stock' : 'out_of_stock';

    $gtin = trim($get('gtin'));
    if (!oa_is_gs1_ean13($gtin)) { $gtin = ''; $sinGtin++; }

    // la spec pide texto plano: decodificar entidades HTML que el feed Google arrastra (&aacute; &nbsp;...)
    $title = mb_substr(trim(html_entity_decode($get('title'), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 0, 150);
    $desc  = str_replace("\xC2\xA0", ' ', html_entity_decode($get('description'), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $desc  = mb_substr(trim(preg_replace('/[ \t]+/', ' ', $desc)), 0, 5000);

    // precio <=0: producto no anunciable, fuera del feed (la validacion de OpenAI lo rechaza)
    $price = trim($get('price'));
    $pv = (float) str_replace(',', '.', preg_replace('/[^0-9.,]/', '', $price));
    if ($pv <= 0) { $sinPrecio++; continue; }

    // brand y description son obligatorias en la spec: fallbacks para no perder la fila
    $brand = trim($get('brand'));
    if ($brand === '') { $brand = 'Francobordo'; $brandFallback++; }
    if ($desc === '')  { $desc = $title; $descFallback++; }

    $row = array(
        $get('id'), $title, $desc, $get('link'), $get('image_link'), $price, $av,
        $brand, $gtin, $get('mpn'), ($get('condition') !== '' ? $get('condition') : 'new'),
        $get('product_type'), $get('google_product_category'),
        'true', 'false', 'true',
    );
    fwrite($out, implode("\t", $row) . "\n");
    $n++;
}
fclose($in);
fclose($out);
rename($tmp, DST);
echo date('c') . " OpenAI feed: $n ofertas (" . round(filesize(DST) / 1048576, 1) . " MB), $sinGtin sin gtin GS1 (van con mpn), $sinPrecio excluidas por precio<=0, $brandFallback brand->Francobordo, $descFallback desc->titulo\n";
