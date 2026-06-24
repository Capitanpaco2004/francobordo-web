<?php
/**
 * product_reviews_feed.php (2026-06-23)
 * Genera el feed de RESENAS DE PRODUCTO para Google Merchant Center (esquema Product Reviews 2.3)
 * a partir de las resenas NATIVAS (tablas reviews + reviews_description). Objetivo: estrellas de
 * producto en Google Shopping / fichas gratuitas, SIN pagar Trustpilot Premium.
 *
 * Lanzar por cron via curl HTTPS (web SAPI -> tep_href_link da URLs canonicas SEO):
 *   curl -s "https://www.francobordo.com/product_reviews_feed.php?token=prfeed_5c8e1a3f"
 * Salida publica: https://www.francobordo.com/fm-feeds/product_reviews.xml
 * Esa URL es la que se da de alta en Merchant Center (programa Product Reviews).
 */

require 'includes/application_top.php';

const PRFEED_TOKEN     = 'prfeed_5c8e1a3f';
const PRFEED_LANG_ID   = 3;                              // espanol
const PRFEED_PUBLISHER = 'Francobordo';
const PRFEED_OUT       = 'fm-feeds/product_reviews.xml'; // relativo a DIR_FS_CATALOG (publico)

header('Content-Type: text/plain; charset=utf-8');
if (($_GET['token'] ?? '') !== PRFEED_TOKEN) { http_response_code(403); echo "forbidden\n"; exit; }

// EAN-13 GS1 real (mismo criterio que jsonld_seo_patch.php: excluye internos 2x/02/04/05 + valida digito)
function prfeed_is_gs1_ean13($ean) {
    $ean = trim((string)$ean);
    if (!preg_match('/^[0-9]{13}$/', $ean)) return false;
    if (preg_match('/^(2|0[245])/', $ean)) return false;
    $sum = 0;
    for ($i = 0; $i < 12; $i++) { $d = (int)$ean[$i]; $sum += ($i % 2 === 0) ? $d : $d * 3; }
    return ((10 - ($sum % 10)) % 10) === (int)$ean[12];
}
// UTF-8 valido + sin caracteres de control + escape XML
function prfeed_xml($s) {
    $s = (string)$s;
    $s = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s);
    return htmlspecialchars($s, ENT_XML1, 'UTF-8');
}

$lang = (int)PRFEED_LANG_ID;
$sql = "select r.reviews_id, r.products_id, r.customers_name, r.reviews_rating, r.date_added,
               rd.reviews_text,
               p.products_model, p.product_ean, p.manufacturers_id,
               pd.products_name,
               m.manufacturers_name
        from reviews r
        inner join reviews_description rd on (rd.reviews_id = r.reviews_id and rd.languages_id = $lang)
        inner join products p on (p.products_id = r.products_id)
        inner join products_description pd on (pd.products_id = p.products_id and pd.language_id = $lang)
        left join manufacturers m on (m.manufacturers_id = p.manufacturers_id)
        where r.approved = 1
          and p.products_status = 1
          and trim(coalesce(rd.reviews_text, '')) <> ''
        order by r.products_id, r.date_added desc";
$res = tep_db_query($sql);

$urlCache  = [];
$nReviews  = 0;
$nProducts = [];
$nSkipNoId = 0;

$tmp = DIR_FS_CATALOG . PRFEED_OUT . '.tmp';
$fh  = fopen($tmp, 'w');
fwrite($fh, '<?xml version="1.0" encoding="UTF-8"?>' . "\n");
fwrite($fh, '<feed xmlns:vc="http://www.w3.org/2007/XMLSchema-versioning" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="http://www.google.com/shopping/reviews/schema/product/2.3/product_reviews.xsd">' . "\n");
fwrite($fh, '  <version>2.3</version>' . "\n");
fwrite($fh, '  <publisher><name>' . prfeed_xml(PRFEED_PUBLISHER) . '</name></publisher>' . "\n");
fwrite($fh, '  <reviews>' . "\n");

while ($row = tep_db_fetch_array($res)) {
    $pid = (int)$row['products_id'];
    if (!isset($urlCache[$pid])) {
        $urlCache[$pid] = tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $pid);
    }
    $purl = $urlCache[$pid];

    $rid    = (int)$row['reviews_id'];
    $name   = trim((string)$row['customers_name']); if ($name === '') $name = 'Cliente';
    $rating = max(1, min(5, (int)$row['reviews_rating']));
    $ts     = date('c', strtotime((string)$row['date_added']));
    $text   = trim((string)$row['reviews_text']);
    $pname  = trim((string)$row['products_name']);
    $brand  = trim((string)$row['manufacturers_name']);
    $sku    = trim((string)$row['products_model']);
    $ean    = trim((string)$row['product_ean']);

    $ids = '';
    if (prfeed_is_gs1_ean13($ean)) $ids .= '<gtins><gtin>' . prfeed_xml($ean) . '</gtin></gtins>';
    if ($sku !== '')   $ids .= '<skus><sku>' . prfeed_xml($sku) . '</sku></skus>';
    if ($sku !== '')   $ids .= '<mpns><mpn>' . prfeed_xml($sku) . '</mpn></mpns>';
    if ($brand !== '') $ids .= '<brands><brand>' . prfeed_xml($brand) . '</brand></brands>';
    if ($ids === '') { $nSkipNoId++; continue; } // sin identificador no se puede emparejar con el feed de productos

    $r  = '    <review>' . "\n";
    $r .= '      <review_id>' . $rid . '</review_id>' . "\n";
    $r .= '      <reviewer><name>' . prfeed_xml($name) . '</name></reviewer>' . "\n";
    $r .= '      <review_timestamp>' . prfeed_xml($ts) . '</review_timestamp>' . "\n";
    $r .= '      <content>' . prfeed_xml($text) . '</content>' . "\n";
    $r .= '      <review_url type="group">' . prfeed_xml($purl) . '</review_url>' . "\n";
    $r .= '      <ratings><overall min="1" max="5">' . $rating . '</overall></ratings>' . "\n";
    $r .= '      <products><product>' . "\n";
    $r .= '        <product_ids>' . $ids . '</product_ids>' . "\n";
    if ($pname !== '') $r .= '        <product_name>' . prfeed_xml($pname) . '</product_name>' . "\n";
    $r .= '        <product_url>' . prfeed_xml($purl) . '</product_url>' . "\n";
    $r .= '      </product></products>' . "\n";
    $r .= '      <collection_method>post_fulfillment</collection_method>' . "\n";
    $r .= '    </review>' . "\n";
    fwrite($fh, $r);

    $nReviews++;
    $nProducts[$pid] = 1;
}

fwrite($fh, '  </reviews>' . "\n");
fwrite($fh, '</feed>' . "\n");
fclose($fh);
@rename($tmp, DIR_FS_CATALOG . PRFEED_OUT);

echo "OK: $nReviews resenas sobre " . count($nProducts) . " productos en el feed. Saltadas sin identificador: $nSkipNoId.\n";
echo "Feed: https://www.francobordo.com/" . PRFEED_OUT . "\n";
