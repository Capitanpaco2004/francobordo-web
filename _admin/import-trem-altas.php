<?php
require 'includes/application_top.php';

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);

require_once DIR_FS_DOCUMENT_ROOT . 'includes/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

const TREM_XLSX        = '/home/francobordo/public_html/import/Trem/listino-export.xlsx';
const TREM_CATALOG_DIR = '/home/francobordo/public_html/import/Trem/catalogue/';
const TREM_CACHE_DIR   = '/home/francobordo/public_html/import/Trem/cache/';
define('IMG_ABS_DIR',     dirname(dirname(__FILE__)) . '/images/productos/');
const NEW_CATEGORY_ID   = 1729;
const TAX_CLASS_IVA21   = 1;
const LANG_ID_ES        = 3;
const LANG_ID_EN        = 1;
const G1_GROUP_ID       = 1;
const G1_FACTOR         = 0.75; // Trem: G1 = products_price × 0.75 (regla específica del proveedor)
const PRODUCT_NAME_MAX  = 80;
const WEB_HTTP_TIMEOUT  = 15;
const ORIGIN_FLAG       = 'trem';
const EAN_INTERNAL_PREFIX = 23;
const VARIANT_OPTION_ID = 3; // "Modelo" (mismo que Osculati)
const LCP_VARIANT_RATIO = 0.30; // umbral LCP/min(name) — familia legítima si supera, sino sueltos
const PRICE_DISPERSION_MAX = 10.0; // ratio max(price)/min(price) máximo aceptable en una familia
const VAT_RATE          = 0.21; // IVA 21% — usado para redondeo PVP a múltiplos de 0.05 con IVA

const TREM_SEARCH_URL  = 'https://www.trem.net/ricerca?search=';

const LLM_URL    = 'http://217.127.199.171:28001/v1/chat/completions';
const LLM_MODEL  = 'qwen36-sakamaki-nvfp4';
const LLM_PROMPT = 'Eres un traductor profesional de italiano a español especializado en productos náuticos, marinos y de pesca. Usa terminología técnica náutica precisa en español de España. Texto plano, conserva <br> si los hay como saltos de línea. Responde SOLO con la traducción, sin comentarios ni explicaciones.';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$max    = isset($_POST['max']) ? (int) $_POST['max'] : (isset($_GET['max']) ? (int) $_GET['max'] : 0);
$dryRun = isset($_POST['dry_run']) || isset($_GET['dry_run']) || $action === 'dry_run';
$skipTranslation = isset($_POST['skip_translation']) || isset($_GET['skip_translation']);
$skipWebEnrich   = isset($_POST['skip_web_enrich']) || isset($_GET['skip_web_enrich']);
$prefetchOnly    = isset($_POST['prefetch_only']) || isset($_GET['prefetch_only']);
$onlyCodesRaw    = trim((string) ($_POST['codes'] ?? $_GET['codes'] ?? ''));
$onlyCodes       = [];
if ($onlyCodesRaw !== '') {
	foreach (preg_split('/[\s,;]+/', $onlyCodesRaw) as $c) {
		$c = trim($c);
		if ($c !== '') $onlyCodes[$c] = true;
	}
}
$maxOverridden = 0;
if (!empty($onlyCodes) && $max > 0) { $maxOverridden = $max; $max = 0; }

/** Redondea un PVP NETO de modo que el precio CON IVA quede en múltiplo de 0.05.
 *  El cliente ve el precio con IVA; ése es el que debe acabar en .X0/.X5. */
function roundToNickel($net) {
	$wi = ((float) $net) * (1 + VAT_RATE);
	$r  = round($wi * 20) / 20;
	return round($r / (1 + VAT_RATE), 4);
}

function logMsg($msg) {
	$line = '[' . date('H:i:s') . '] ' . $msg . "\n";
	echo '<pre style="margin:0;padding:2px 8px;border-bottom:1px solid #eee;white-space:pre-wrap;overflow-wrap:break-word;word-break:break-word;font-family:monospace;font-size:12px;">' . htmlspecialchars($line) . '</pre>';
	@flush();
}

function tremNum($v) {
	if ($v === null) return null;
	if (is_numeric($v)) return (float) $v;
	$s = trim((string) $v);
	if ($s === '') return null;
	$s = str_replace(['.', ','], ['', '.'], $s);
	return is_numeric($s) ? (float) $s : null;
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
		$l = trim(preg_replace('/[ \t\xC2\xA0]+/', ' ', $l));
		if ($l === '') {
			if ($emptyStreak < 1 && !empty($out)) { $out[] = ''; }
			$emptyStreak++;
			continue;
		}
		$out[] = $l;
		$emptyStreak = 0;
	}
	return nl2br(trim(implode("\n", $out)), false);
}

function tremSlugify($text, $maxLen = 50) {
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

function tremNormalizeManufacturer($name) {
	$name = trim(preg_replace('/\s+/', ' ', (string) $name));
	if ($name === '') return '';
	$words = explode(' ', $name);
	$out = [];
	foreach ($words as $w) {
		if ($w === '') continue;
		$alpha = preg_replace('/[^A-Za-z]/', '', $w);
		if (strlen($alpha) > 0 && strtoupper($alpha) === $alpha && strlen($alpha) <= 4) {
			$out[] = $w;
		} else {
			$out[] = mb_convert_case(mb_strtolower($w, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
		}
	}
	return implode(' ', $out);
}

function resolveManufacturer($mysqli, $rawName, &$cache, &$createdLog, $dryRun) {
	$key = strtoupper(trim($rawName));
	if ($key === '') return null;
	if (isset($cache[$key])) return $cache[$key];
	$qkey = $mysqli->real_escape_string($key);
	$r = $mysqli->query("SELECT manufacturers_id FROM manufacturers WHERE UPPER(TRIM(manufacturers_name))=\"$qkey\" LIMIT 1");
	if ($r && $row = $r->fetch_assoc()) { $cache[$key] = (int) $row['manufacturers_id']; return $cache[$key]; }
	$display = tremNormalizeManufacturer($rawName);
	if ($dryRun) { $cache[$key] = 0; $createdLog[$key] = $display; return 0; }
	$qd = $mysqli->real_escape_string($display);
	$mysqli->query("INSERT INTO manufacturers (manufacturers_name, date_added) VALUES (\"$qd\", NOW())");
	$id = (int) $mysqli->insert_id;
	$mysqli->query("INSERT INTO manufacturers_info (manufacturers_id, languages_id, manufacturers_url) VALUES ($id, " . LANG_ID_ES . ", \"\")");
	$mysqli->query("INSERT INTO manufacturers_info (manufacturers_id, languages_id, manufacturers_url) VALUES ($id, " . LANG_ID_EN . ", \"\")");
	$cache[$key] = $id;
	$createdLog[$key] = $display . " (id=$id)";
	return $id;
}

/** Carga set de identificadores ya existentes (lowercase) — emula getExistingBaseCodes() de Osculati. */
/** Mapa de duplicados — FILTRADO por Trem (fix 2026-05-11).
 *  Trem es la marca dominante (mfg=189) pero también importa Olimpic Ropes, MOOR LINE, etc.
 *  Filtro: mfg=189 OR products_import_origin LIKE 'trem%'. EAN global (GS1). */
function buildExistingMap($mysqli) {
	$existing = [];
	$f = "(p.manufacturers_id = 189 OR p.products_import_origin LIKE 'trem%')";
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
	return $existing;
}

function llmTranslate($text, $maxRetries = 2) {
	if (trim((string) $text) === '') return '';
	$payload = json_encode([
		'model' => LLM_MODEL,
		'messages' => [
			['role' => 'system', 'content' => LLM_PROMPT],
			['role' => 'user',   'content' => $text],
		],
		'temperature' => 0.2,
		'max_tokens' => 1500,
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

function fetchTremPage($sku) {
	$cacheFile = TREM_CACHE_DIR . preg_replace('/[^A-Za-z0-9_-]/', '_', $sku) . '.html';
	if (file_exists($cacheFile) && filesize($cacheFile) > 100) {
		return @file_get_contents($cacheFile);
	}
	$url = TREM_SEARCH_URL . urlencode($sku);
	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_TIMEOUT => WEB_HTTP_TIMEOUT,
		CURLOPT_CONNECTTIMEOUT => 5,
		CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; FrancobordoImporter/1.0)',
		CURLOPT_SSL_VERIFYPEER => false,
	]);
	$html = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	unset($ch);
	if ($html === false || $code !== 200 || strlen($html) < 200) return false;
	@file_put_contents($cacheFile, $html);
	return $html;
}

/**
 * Devuelve [title_it, desc_it, image_code, family_slug, family_url, product_code]
 * Cualquier campo puede ser '' si no se ha podido extraer.
 */
function extractTremInfo($html, $sku) {
	$out = ['title_it' => '', 'desc_it' => '', 'image_code' => '', 'family_slug' => '', 'family_url' => '', 'product_code' => ''];
	if (!is_string($html) || $html === '') return $out;

	if (preg_match('#<title>(.*?)</title>#is', $html, $m)) {
		$t = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$t = preg_replace('/\s*\|\s*TREM\s+srl\s*$/i', '', $t);
		$out['title_it'] = trim($t);
	}

	$descMeta = '';
	if (preg_match('#<meta\s+name=["\']description["\']\s+content=["\']([^"\']*)#is', $html, $m)) {
		$descMeta = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
	$descLd = '';
	if (preg_match_all('#<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $allLd)) {
		foreach ($allLd[1] as $blob) {
			$j = json_decode(trim($blob), true);
			if (!is_array($j)) continue;
			$walk = function ($node) use (&$walk, &$descLd) {
				if (is_array($node)) {
					if (isset($node['description']) && is_string($node['description'])) {
						$d = trim(html_entity_decode($node['description'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
						if (mb_strlen($d, 'UTF-8') > mb_strlen($descLd, 'UTF-8')) $descLd = $d;
					}
					foreach ($node as $v) $walk($v);
				}
			};
			$walk($j);
		}
	}
	$out['desc_it'] = $descLd !== '' ? $descLd : $descMeta;

	$skuQ = preg_quote($sku, '#');
	// Acepta data-image= o src= (la página servida desde Azure usa src=)
	if (preg_match('#data-ar-codice=["\']' . $skuQ . '["\'][^>]*(?:data-image|src)=["\']crm/images/catalogue/([^"\'.]+)\.jpg#is', $html, $m)
		|| preg_match('#(?:data-image|src)=["\']crm/images/catalogue/([^"\'.]+)\.jpg["\'][^>]*data-ar-codice=["\']' . $skuQ . '["\']#is', $html, $m)) {
		$out['image_code'] = $m[1];
	} elseif (preg_match('#(?:data-image|src)=["\']crm/images/catalogue/([^"\'.]+)\.jpg#is', $html, $m)) {
		$out['image_code'] = $m[1];
	}

	// Canonical URL → familia (penúltimo segmento) + product_code (último segmento, código tipo STNH01032002)
	if (preg_match('#<link\s+rel=["\']canonical["\']\s+href=["\']([^"\']+)#is', $html, $m)) {
		$canon = $m[1];
		$path = parse_url($canon, PHP_URL_PATH);
		if (is_string($path) && $path !== '') {
			$segs = array_values(array_filter(explode('/', $path), fn($s) => $s !== ''));
			if (count($segs) >= 2 && stripos($path, '/ricerca') === false) {
				$out['family_slug'] = $segs[count($segs) - 2];
				$last = $segs[count($segs) - 1];
				$out['family_url'] = preg_replace('#/[^/]+$#', '', $canon);
				if (preg_match('/-([A-Z]{2,4}\d{6,12})$/i', $last, $mm)) {
					$out['product_code'] = $mm[1];
				}
			}
		}
	}
	return $out;
}

function extractMeasureFromTitle($title) {
	$title = (string) $title;
	// Diámetro: Ø 8 / Ø8 / diameter 8 / diam. 8 mm / diametro 8
	if (preg_match('/(?:Ø|diameter|diametro|diam\.?|Ø)\s*(\d+(?:[.,]\d+)?)\s*(mm|cm)?/iu', $title, $m)) {
		return 'Ø ' . $m[1] . ' ' . (isset($m[2]) && $m[2] !== '' ? $m[2] : 'mm');
	}
	// Longitud: width X / length X / largo X / ancho X
	if (preg_match('/(?:width|length|largo|ancho|larghezza|lunghezza|altezza|height)\s+(\d+(?:[.,]\d+)?)\s*(mm|cm|m|mt)?/iu', $title, $m)) {
		return $m[1] . ' ' . (isset($m[2]) && $m[2] !== '' ? $m[2] : 'mm');
	}
	// Rango "X-Y unidad"
	if (preg_match('/(\d+(?:[.,]\d+)?\s*-\s*\d+(?:[.,]\d+)?)\s*(kg|g|mm|cm|mt|m|l|ml|HP|W|V|A|Hz|°)\b/iu', $title, $m)) {
		return trim($m[1]) . ' ' . $m[2];
	}
	// Valor + unidad explícita
	if (preg_match('/(\d+(?:[.,]\d+)?)\s*(kg|g|mm|cm|mt|m|l|ml|in|inch|"|HP|W|V|A|Hz|°|fl\s*oz|oz)\b/iu', $title, $m)) {
		return $m[1] . ' ' . $m[2];
	}
	return '';
}

/**
 * Verifica que una familia detectada por URL canónica sea LEGÍTIMA (variantes del mismo producto).
 * Trem agrupa varios productos distintos bajo la misma URL de subcategoría — esta función filtra
 * los falsos positivos (e.g. "kit-ormeggio" con 15 productos heterogéneos cubriendo precios 1.5€-580€).
 *
 * Criterios (todos deben cumplirse):
 *   1. LCP del TITLE_EN ≥ LCP_VARIANT_RATIO del nombre más corto.
 *   2. Dispersión de precios max/min < PRICE_DISPERSION_MAX.
 *
 * Devuelve [ok, motivo].
 */
function isLegitimateTremFamily(array $items) {
	if (count($items) < 2) return [true, 'single'];

	// 1) LCP de TITLE_EN (xlsx)
	$names = array_map(fn($r) => $r['TITLE_EN'], $items);
	$lcp = longestCommonPrefix($names);
	$lcpLen = mb_strlen($lcp, 'UTF-8');
	$minLen = min(array_map(fn($s) => mb_strlen($s, 'UTF-8'), $names));
	if ($minLen <= 0) return [false, 'sin TITLE_EN'];
	$lcpRatio = $lcpLen / $minLen;
	if ($lcpRatio < LCP_VARIANT_RATIO) {
		return [false, sprintf('LCP %.0f%% < %.0f%%', $lcpRatio * 100, LCP_VARIANT_RATIO * 100)];
	}

	// 2) Dispersión de precios
	$prices = array_map(fn($r) => $r['_NET'], $items);
	$min = min($prices); $max = max($prices);
	if ($min > 0) {
		$ratio = $max / $min;
		if ($ratio > PRICE_DISPERSION_MAX) {
			return [false, sprintf('precio max/min %.1fx > %.0fx', $ratio, PRICE_DISPERSION_MAX)];
		}
	}
	return [true, 'ok'];
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

function findOrCreateOptionValue($mysqli, $name) {
	$nameSafe = $mysqli->real_escape_string($name);
	$q = $mysqli->query("SELECT pov.products_options_values_id
		FROM products_options_values pov
		INNER JOIN products_options_values_to_products_options pov2po ON pov2po.products_options_values_id = pov.products_options_values_id
		WHERE pov2po.products_options_id = " . VARIANT_OPTION_ID . "
		  AND pov.language_id = " . LANG_ID_ES . "
		  AND pov.products_options_values_name = '$nameSafe'
		LIMIT 1");
	if ($row = $q->fetch_assoc()) return (int) $row['products_options_values_id'];

	$nq = $mysqli->query("SELECT IFNULL(MAX(products_options_values_id), 0) + 1 AS nid FROM products_options_values");
	$nrow = $nq->fetch_assoc();
	$newId = (int) $nrow['nid'];

	$mysqli->query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($newId, " . LANG_ID_ES . ", '$nameSafe', '')");
	$mysqli->query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($newId, " . LANG_ID_EN . ", '$nameSafe', '')");
	$mysqli->query("INSERT INTO products_options_values_to_products_options (products_options_id, products_options_values_id) VALUES (" . VARIANT_OPTION_ID . ", $newId)");
	return $newId;
}

function calcG1Price($price, $cost) {
	$price = (float) $price;
	if ($price <= 0) return 0.0;
	return round($price * G1_FACTOR, 4);
}

function ean13Checksum($payload12) {
	if (strlen($payload12) !== 12 || !ctype_digit($payload12)) return -1;
	$sum = 0;
	for ($i = 0; $i < 12; $i++) {
		$d = (int) $payload12[$i];
		$sum += ($i % 2 === 0) ? $d : $d * 3;
	}
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

function loadXlsxRows($path) {
	$reader = IOFactory::createReaderForFile($path);
	$reader->setReadDataOnly(true);
	$ss = $reader->load($path);
	$sh = $ss->getActiveSheet();
	$hRow = $sh->getHighestRow();
	$rows = [];
	for ($r = 4; $r <= $hRow; $r++) {
		$cells = $sh->rangeToArray("A$r:AD$r", null, true, false)[0];
		$item = trim((string) ($cells[2] ?? ''));
		if ($item === '') continue;
		$rows[] = [
			'PAG'        => $cells[0] ?? '',
			'POS'        => $cells[1] ?? '',
			'ITEM'       => $item,
			'TITLE_EN'   => trim((string) ($cells[3] ?? '')),
			'STATUS'     => strtoupper(trim((string) ($cells[4] ?? ''))),
			'NEW'        => trim((string) ($cells[5] ?? '')),
			'SELL_UNIT'  => trim((string) ($cells[6] ?? '')),
			'QTY_SELL'   => $cells[7] ?? '',
			'EXPORT'     => $cells[9] ?? '',
			'EAN'        => trim((string) ($cells[14] ?? '')),
			'ORIGIN'     => trim((string) ($cells[16] ?? '')),
			'NOT_SELL'   => trim((string) ($cells[17] ?? '')),
			'TREM_LINE'  => trim((string) ($cells[18] ?? '')),
			'BRAND'      => trim((string) ($cells[19] ?? '')),
			'WEIGHT'     => $cells[23] ?? '',
		];
	}
	return $rows;
}

function copyImage($srcImg, $destAbs) {
	if (!file_exists($srcImg) || filesize($srcImg) <= 0) return false;
	return @copy($srcImg, $destAbs);
}

$isAction = ($action === 'execute' || $action === 'dry_run');

if ($isAction) {
	@header('X-Accel-Buffering: no');
	@header('Content-Type: text/html; charset=utf-8');
	while (ob_get_level() > 0) @ob_end_flush();
	@ob_implicit_flush(true);
	// Liberar la conexión PDO del handler de sesión: si el script tarda > wait_timeout MySQL,
	// el session_write_close() automático al final del request falla con "MySQL server has gone away".
	if (session_status() === PHP_SESSION_ACTIVE) @session_write_close();
}
?>
<?php require THEME . 'html/header.php'; ?>
<table style="width:100%;"><tr><td>
<div style="padding:20px;">
<?php if ($isAction): ?>
	<h2>Importador Trem — <?php echo $dryRun ? 'DRY-RUN (sin cambios)' : ($prefetchOnly ? 'PREFETCH (solo cache web)' : 'EJECUCIÓN REAL'); ?></h2>
	<p>
		<a href="<?php echo tep_href_link('import-trem-altas.php'); ?>" class="xbutton small hv9">← Volver</a>
	</p>
	<div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n";
@flush();

logMsg("Modo: " . ($dryRun ? "dry-run" : ($prefetchOnly ? "prefetch-only" : "EXECUTE"))
	. ($skipTranslation ? " (sin traducción LLM)" : "")
	. ($skipWebEnrich   ? " (sin enriquecimiento web)" : "")
	. ($max > 0 ? ", max=$max" : ""));

if (!file_exists(TREM_XLSX)) { logMsg("ERROR: xlsx no encontrado: " . TREM_XLSX); goto end_action; }
if (!is_dir(TREM_CACHE_DIR)) @mkdir(TREM_CACHE_DIR, 0775, true);

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_error) { logMsg("ERROR DB: " . $mysqli->connect_error); goto end_action; }
$mysqli->set_charset('utf8');

logMsg("Leyendo xlsx…");
$rows = loadXlsxRows(TREM_XLSX);
logMsg("Filas con SKU: " . count($rows));

logMsg("Cargando IDs existentes en BD…");
$existing = buildExistingMap($mysqli);
logMsg("  → " . count($existing) . " referencias ya en BD");

// Stage 1: pre-filtros sobre xlsx (sin tocar web).
$candidates = [];           // SKU => row
$skippedNoSku = $skippedNoName = $skippedBadPrice = $skippedStatus = $skippedExisting = 0;
foreach ($rows as $row) {
	$sku = $row['ITEM'];
	if ($sku === '') { $skippedNoSku++; continue; }
	if ($row['TITLE_EN'] === '') { $skippedNoName++; continue; }
	$inOnlyCodes = !empty($onlyCodes) && isset($onlyCodes[$sku]);
	// Si el usuario pide explícitamente el code, ignoramos filtros de status/not_sell
	if (!$inOnlyCodes && $row['STATUS'] !== '' && in_array($row['STATUS'], ['D', 'X', 'F', 'N', '0'], true)) { $skippedStatus++; continue; }
	if (!$inOnlyCodes && $row['NOT_SELL'] !== '' && in_array(strtoupper($row['NOT_SELL']), ['X', 'Y', 'YES', 'SI', 'TRUE', '1'], true)) { $skippedStatus++; continue; }
	if (isset($existing[strtolower($sku)])) { $skippedExisting++; continue; }
	if (!empty($row['EAN']) && isset($existing[strtolower($row['EAN'])])) { $skippedExisting++; continue; }
	$net = tremNum($row['EXPORT']);
	if ($net === null || $net <= 0) { $skippedBadPrice++; continue; }
	$row['_NET'] = $net;
	$candidates[$sku] = $row;
}
logMsg("Candidatos tras pre-filtro: " . count($candidates));
logMsg("  pre-skip: existentes=$skippedExisting | sin SKU=$skippedNoSku | sin nombre=$skippedNoName | precio=$skippedBadPrice | status=$skippedStatus");

// Si hay codes específicos, reducir candidates a solo los que comparten LCP de TITLE_EN
// con alguno de los codes pedidos (heurística rápida para no fetchear ~5000 productos web).
if (!empty($onlyCodes)) {
	$wantedTitles = [];
	foreach ($onlyCodes as $c => $_) {
		if (isset($candidates[$c])) $wantedTitles[] = $candidates[$c]['TITLE_EN'];
		else logMsg("  AVISO: code '$c' no en candidates (puede no existir o estar ya en BD).");
	}
	if (empty($wantedTitles)) {
		logMsg("AVISO: ningún code pedido en candidates. Saliendo.");
		goto end_action;
	}
	$lcpRatio = 0.30; // mismo que LCP_VARIANT_RATIO
	$filtered = [];
	foreach ($candidates as $sku => $row) {
		if (isset($onlyCodes[$sku])) { $filtered[$sku] = $row; continue; }
		$title = $row['TITLE_EN'];
		foreach ($wantedTitles as $wt) {
			// LCP entre $title y $wt
			$len = min(strlen($title), strlen($wt));
			$lcp = 0;
			for ($i = 0; $i < $len; $i++) {
				if ($title[$i] !== $wt[$i]) break;
				$lcp++;
			}
			$minLen = min(strlen($title), strlen($wt));
			if ($minLen > 0 && $lcp / $minLen >= $lcpRatio) {
				$filtered[$sku] = $row;
				break;
			}
		}
	}
	$candidates = $filtered;
	logMsg("Reducción por LCP con codes pedidos: " . count($candidates) . " candidatos posibles (titulos similares)");
}

// Stage 2: enriquecimiento web (cached). Aplicamos $max en candidatos para no fetchear todo en pruebas.
// Multiplicador alto (x50) para asegurar familias completas; con cache caliente la 2ª pasada es instantánea.
$maxFetch = ($max > 0) ? min(max($max * 50, 200), count($candidates)) : count($candidates);
$fetched = 0;
$webOk = $webFail = 0;
$candidateList = array_slice($candidates, 0, $maxFetch, true);
logMsg("Enriqueciendo " . count($candidateList) . " candidatos (cache + web)…");

if (!$skipWebEnrich) {
	foreach ($candidateList as $sku => &$row) {
		if (++$fetched % 200 === 0) logMsg("  fetched $fetched / " . count($candidateList) . " (ok=$webOk fail=$webFail)");
		$html = fetchTremPage($sku);
		if ($html === false) { $webFail++; $row['_INFO'] = ['title_it'=>'','desc_it'=>'','image_code'=>'','family_slug'=>'','family_url'=>'','product_code'=>'']; continue; }
		$webOk++;
		$row['_INFO'] = extractTremInfo($html, $sku);
	}
	unset($row);
} else {
	foreach ($candidateList as $sku => &$row) {
		$row['_INFO'] = ['title_it'=>'','desc_it'=>'','image_code'=>'','family_slug'=>'','family_url'=>'','product_code'=>''];
	}
	unset($row);
}
logMsg("Web: ok=$webOk fail=$webFail (de " . count($candidateList) . ")");

if ($prefetchOnly) {
	logMsg("PREFETCH terminado. No se inserta nada.");
	goto end_action;
}

// Stage 3: agrupar por familia.
$families = [];      // family_slug => [SKU => row]
$noFamily = [];      // SKU => row (sin familia detectada)
foreach ($candidateList as $sku => $row) {
	$fam = $row['_INFO']['family_slug'] ?? '';
	if ($fam === '') { $noFamily[$sku] = $row; continue; }
	$families[$fam][$sku] = $row;
}
logMsg("Familias detectadas: " . count($families) . " | sin familia: " . count($noFamily));

// Stage 4: split familias mixtas por marca (Osculati pattern)
$splitMixed = 0;
foreach ($families as $fam => $items) {
	$byBrand = [];
	foreach ($items as $sku => $row) {
		$brandKey = strtoupper(trim($row['BRAND'] !== '' ? $row['BRAND'] : ($row['TREM_LINE'] !== '' ? $row['TREM_LINE'] : 'TREM')));
		$byBrand[$brandKey][$sku] = $row;
	}
	if (count($byBrand) > 1) {
		$splitMixed++;
		unset($families[$fam]);
		foreach ($byBrand as $brandKey => $sub) {
			if (count($sub) === 1) {
				$sk = array_key_first($sub);
				$noFamily[$sk] = $sub[$sk];
			} else {
				$virtFam = $fam . '__' . preg_replace('/[^A-Z0-9]+/', '', $brandKey);
				$families[$virtFam] = $sub;
			}
		}
	}
}
if ($splitMixed > 0) logMsg("Familias mixtas (>1 marca) splitteadas: $splitMixed");

// Standalone si la familia tiene 1 solo SKU
foreach ($families as $fam => $items) {
	if (count($items) === 1) {
		$sk = array_key_first($items);
		$noFamily[$sk] = $items[$sk];
		unset($families[$fam]);
	}
}

// Validación de legitimidad: la URL canónica de Trem agrupa subcategorías enteras (varios
// productos distintos bajo el mismo slug). Filtramos las que no comparten LCP o tienen precios
// muy dispares y las pasamos a sueltos.
$splitFake = 0;
foreach ($families as $fam => $items) {
	[$ok, $reason] = isLegitimateTremFamily($items);
	if (!$ok) {
		$splitFake++;
		if ($splitFake <= 8) logMsg("  familia '$fam' descartada (" . count($items) . " SKUs): $reason");
		foreach ($items as $sku => $row) $noFamily[$sku] = $row;
		unset($families[$fam]);
	}
}
if ($splitFake > 0) logMsg("Familias falsas (heterogéneas) splitteadas en sueltos: $splitFake");

// Filtro por codes específicos: solo familias/sueltos que contienen al menos un SKU pedido
if (!empty($onlyCodes)) {
	$famF = [];
	foreach ($families as $fam => $items) {
		if (!empty(array_intersect_key($items, $onlyCodes))) $famF[$fam] = $items;
	}
	$nfF = [];
	foreach ($noFamily as $sku => $row) {
		if (isset($onlyCodes[$sku])) $nfF[$sku] = $row;
	}
	$families = $famF;
	$noFamily = $nfF;
	logMsg("Filtro por codes específicos: quedan " . count($families) . " familias + " . count($noFamily) . " sueltos");
}
logMsg("Tras consolidar: " . count($families) . " familias multi-variante + " . count($noFamily) . " sueltos");

if (count($candidates) > count($candidateList)) {
	logMsg("AVISO: hay " . (count($candidates) - count($candidateList)) . " candidatos no fetcheados (max alcanzado). Re-ejecuta con max mayor o sin max para procesar el resto.");
}

// Stage 5: insertar.
if (!$dryRun && !is_dir(IMG_ABS_DIR)) { @mkdir(IMG_ABS_DIR, 0775, true); }

$mfgCache = [];
$mfgCreated = [];
$nInserted = $nFamiliesIns = $nStandaloneIns = $nWithImg = $nWithVar = 0;
$imgFail = $errors = $translateFail = 0;

// 5a: Familias con N variantes
foreach ($families as $fam => $items) {
	if ($max > 0 && $nInserted >= $max) { logMsg("Alcanzado límite max=$max, parando."); break; }

	// Sort items by cost ascending
	uasort($items, fn($a, $b) => $a['_NET'] <=> $b['_NET']);
	$skuList = array_keys($items);
	$cheapestSku = $skuList[0];
	$cheap = $items[$cheapestSku];
	$cheapInfo = $cheap['_INFO'];

	$cost  = $cheap['_NET'];
	$price = roundToNickel($cost * 2.0);
	$g1    = roundToNickel(calcG1Price($price, $cost));

	$weight = tremNum($cheap['WEIGHT']);
	if ($weight === null || $weight <= 0) $weight = 1.0;

	$brand = $cheap['BRAND'] !== '' ? $cheap['BRAND'] : ($cheap['TREM_LINE'] !== '' ? $cheap['TREM_LINE'] : 'Trem');
	$mfgId = resolveManufacturer($mysqli, $brand, $mfgCache, $mfgCreated, $dryRun);

	$titleEn = $cheap['TITLE_EN'];
	$titleIt = $cheapInfo['title_it'] !== '' ? $cheapInfo['title_it'] : $titleEn;
	$descIt  = $cheapInfo['desc_it'];

	$nameEs = '';
	$descEs = '';
	if (!$skipTranslation && !$dryRun) {
		$tn = llmTranslate($titleIt);
		if ($tn !== '') $nameEs = mb_substr($tn, 0, PRODUCT_NAME_MAX, 'UTF-8'); else $translateFail++;
		if ($descIt !== '') {
			$td = llmTranslate(cleanHtmlAggressive($descIt));
			if ($td !== '') $descEs = $td; else $translateFail++;
		}
	}
	if ($nameEs === '') $nameEs = mb_substr($titleEn, 0, PRODUCT_NAME_MAX, 'UTF-8');
	if ($descEs === '') $descEs = cleanHtmlAggressive($descIt !== '' ? $descIt : $titleEn);
	$nameEnRaw = mb_substr($titleEn, 0, PRODUCT_NAME_MAX, 'UTF-8');
	// EN description: solo el TITLE_EN del xlsx (Trem no provee descripción EN; no copiar la IT cruda).
	$descEn    = cleanHtmlAggressive($titleEn);

	if ($dryRun) {
		$nInserted++; $nFamiliesIns++;
		if ($nFamiliesIns <= 8) logMsg(sprintf("  WOULD INSERT FAMILIA '%s' (%d variantes): cheap=%s %.2f€ name='%s'", $fam, count($items), $cheapestSku, $cost, mb_substr($nameEs ?: $nameEnRaw, 0, 50, 'UTF-8')));
		continue;
	}

	$mysqli->begin_transaction();
	try {
		$qmodel = $mysqli->real_escape_string($cheapestSku);
		$qean   = $mysqli->real_escape_string($cheap['EAN']);
		$qmfg   = (int) $mfgId;
		$sql = "INSERT INTO products (products_quantity, check_stock, products_model, products_image, products_price, products_cost, products_date_added, products_weight, products_status, products_tax_class_id, manufacturers_id, product_ean, reference_prov, products_import_origin)
			VALUES (0, 0, \"$qmodel\", \"\", " . number_format($price, 4, '.', '') . ", " . number_format($cost, 4, '.', '') . ", NOW(), $weight, 2, " . TAX_CLASS_IVA21 . ", $qmfg, \"$qean\", \"$qmodel\", \"" . ORIGIN_FLAG . "\")";
		if (!$mysqli->query($sql)) throw new Exception("products: " . $mysqli->error);
		$pid = (int) $mysqli->insert_id;

		$qNameEs = $mysqli->real_escape_string($nameEs);
		$qDescEs = $mysqli->real_escape_string($descEs);
		$qNameEn = $mysqli->real_escape_string($nameEnRaw);
		$qDescEn = $mysqli->real_escape_string($descEn);
		if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_ES . ", \"$qNameEs\", \"$qDescEs\", 0)")) throw new Exception("desc ES: " . $mysqli->error);
		if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_EN . ", \"$qNameEn\", \"$qDescEn\", 0)")) throw new Exception("desc EN: " . $mysqli->error);
		if (!$mysqli->query("INSERT INTO products_to_categories (products_id, categories_id) VALUES ($pid, " . NEW_CATEGORY_ID . ")")) throw new Exception("p2c: " . $mysqli->error);
		if (!$mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . G1_GROUP_ID . ", $pid, " . number_format($g1, 4, '.', '') . ", 1, 1)")) throw new Exception("g1: " . $mysqli->error);

		// Imagen del padre
		if ($cheapInfo['image_code'] !== '') {
			$srcImg = TREM_CATALOG_DIR . $cheapInfo['image_code'] . '.jpg';
			if (file_exists($srcImg)) {
				$slug = tremSlugify($nameEs ?: $nameEnRaw);
				$finalName = $slug . '-' . $pid . '.jpg';
				$finalAbs = IMG_ABS_DIR . $finalName;
				if (copyImage($srcImg, $finalAbs)) {
					$mysqli->query("UPDATE products SET products_image=\"" . $mysqli->real_escape_string($finalName) . "\" WHERE products_id=$pid");
					$nWithImg++;
				} else { $imgFail++; }
			} else { $imgFail++; }
		}

		// Variantes — etiqueta priorizando: medida en TITLE_EN xlsx (suele discriminar) → medida en title_it →
		// → resto del title_it tras prefijo común → SKU como último recurso.
		$enTitlesAll = array_map(fn($it) => $it['TITLE_EN'], $items);
		$itTitlesAll = array_map(fn($it) => $it['_INFO']['title_it'] !== '' ? $it['_INFO']['title_it'] : $it['TITLE_EN'], $items);
		$commonPrefixIt = rtrim(longestCommonPrefix($itTitlesAll), " -–·,");
		$commonPrefixEn = rtrim(longestCommonPrefix($enTitlesAll), " -–·,");
		$labels = [];
		$variantsCreated = 0;
		foreach ($items as $sku => $it) {
			$enTitle = $enTitlesAll[$sku];
			$itTitle = $itTitlesAll[$sku];
			$label = '';

			// 1) Medida del TITLE_EN (más fiable: tiene "diameter 8", etc.)
			$measure = extractMeasureFromTitle($enTitle);
			if ($measure !== '') $label = $measure;

			// 2) Medida del title_it (Ø, mm…)
			if ($label === '') {
				$measure = extractMeasureFromTitle($itTitle);
				if ($measure !== '') $label = $measure;
			}

			// 3) Resto del title_it tras prefijo común
			if ($label === '' && $commonPrefixIt !== '' && mb_strpos($itTitle, $commonPrefixIt) === 0) {
				$rest = trim(mb_substr($itTitle, mb_strlen($commonPrefixIt, 'UTF-8'), null, 'UTF-8'));
				$rest = ltrim($rest, " -–·,;");
				if ($rest !== '' && mb_strlen($rest, 'UTF-8') <= 64) $label = $rest;
			}

			// 4) Resto del TITLE_EN tras prefijo común
			if ($label === '' && $commonPrefixEn !== '' && mb_strpos($enTitle, $commonPrefixEn) === 0) {
				$rest = trim(mb_substr($enTitle, mb_strlen($commonPrefixEn, 'UTF-8'), null, 'UTF-8'));
				$rest = ltrim($rest, " -–·,;");
				if ($rest !== '' && mb_strlen($rest, 'UTF-8') <= 64) $label = $rest;
			}

			// 5) SKU como último recurso
			if ($label === '') $label = $sku;

			$labels[$sku] = mb_substr($label, 0, 64, 'UTF-8');
		}
		// Resolver collisions individuales: solo los SKUs cuyo label colisiona pasan a SKU
		$counts = array_count_values($labels);
		foreach ($labels as $sku => $lab) {
			if (($counts[$lab] ?? 0) > 1 && $lab !== $sku) $labels[$sku] = $sku;
		}

		$g1Main = $g1;
		// Track de OVs ya consumidos por este producto (guardia anti-colisión de labels)
		$ovsUsados = [];
		foreach ($items as $sku => $it) {
			$varCost  = $it['_NET'];
			$varPrice = roundToNickel($varCost * 2.0);
			$varG1    = roundToNickel(calcG1Price($varPrice, $varCost));
			$delta    = round($varPrice - $price, 4);
			$prefix   = $delta < 0 ? '-' : '+';

			$varWeight = tremNum($it['WEIGHT']);
			if ($varWeight === null || $varWeight <= 0) $varWeight = 1.0;

			$valueId = findOrCreateOptionValue($mysqli, $labels[$sku]);
			if (isset($ovsUsados[$valueId])) {
				$labelDis = mb_substr($labels[$sku] . ' (' . $sku . ')', 0, 64, 'UTF-8');
				$valueId = findOrCreateOptionValue($mysqli, $labelDis);
			}
			$ovsUsados[$valueId] = true;
			$qref    = $mysqli->real_escape_string($sku);
			$qveean  = $mysqli->real_escape_string($it['EAN']);

			// Peso variante = DELTA sobre el padre (shopping_cart.php suma con prefix +/-)
			$wDelta  = round($varWeight - (float) $weight, 3);
			$wPrefix = $wDelta < 0 ? '-' : '+';
			$wAbs    = abs($wDelta);

			if (!$mysqli->query("INSERT INTO products_attributes SET
				products_id=$pid, options_id=" . VARIANT_OPTION_ID . ", options_values_id=$valueId,
				options_values_price=" . number_format(abs($delta), 4, '.', '') . ", price_prefix='$prefix',
				reference='$qref', reference_prov='$qref', products_attributes_ean='$qveean',
				options_values_weight=" . number_format($wAbs, 3, '.', '') . ", weight_prefix='$wPrefix'"))
				throw new Exception("attr: " . $mysqli->error);
			$paId = (int) $mysqli->insert_id;
			$variantsCreated++;

			// G1 delta por variante
			$g1Delta = round($varG1 - $g1Main, 4);
			$g1Prefix = $g1Delta < 0 ? '-' : '+';
			if (!$mysqli->query("INSERT INTO products_attributes_groups (products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id, options_values_weight, weight_prefix) VALUES ($paId, " . G1_GROUP_ID . ", " . number_format(abs($g1Delta), 4, '.', '') . ", '$g1Prefix', $pid, 0, '+')"))
				throw new Exception("attr_groups: " . $mysqli->error);
		}
		if ($variantsCreated > 0) $nWithVar++;

		$mysqli->commit();
		if (!isValidEan13($cheap['EAN'])) {
			$genEan = generateInternalEan13($pid, EAN_INTERNAL_PREFIX);
			if ($genEan !== '') {
				$mysqli->query("UPDATE products SET product_ean='" . $mysqli->real_escape_string($genEan) . "' WHERE products_id=$pid AND (product_ean IS NULL OR product_ean='' OR LENGTH(product_ean)<>13)");
			}
		}

		$nInserted++; $nFamiliesIns++;
		logMsg(sprintf("OK FAMILIA '%s' pid=%d cheap=%s [%d variantes] price=%.2f cost=%.2f g1=%.2f", $fam, $pid, $cheapestSku, $variantsCreated, $price, $cost, $g1));
	} catch (Exception $e) {
		$mysqli->rollback();
		$errors++;
		logMsg("ERROR familia '$fam': " . $e->getMessage());
	}
}

// 5b: Sueltos (1 SKU)
foreach ($noFamily as $sku => $row) {
	if ($max > 0 && $nInserted >= $max) { logMsg("Alcanzado límite max=$max, parando."); break; }
	$info = $row['_INFO'];

	$cost  = $row['_NET'];
	$price = roundToNickel($cost * 2.0);
	$g1    = roundToNickel(calcG1Price($price, $cost));
	$weight = tremNum($row['WEIGHT']);
	if ($weight === null || $weight <= 0) $weight = 1.0;

	$brand = $row['BRAND'] !== '' ? $row['BRAND'] : ($row['TREM_LINE'] !== '' ? $row['TREM_LINE'] : 'Trem');
	$mfgId = resolveManufacturer($mysqli, $brand, $mfgCache, $mfgCreated, $dryRun);

	$titleEn = $row['TITLE_EN'];
	$titleIt = $info['title_it'] !== '' ? $info['title_it'] : $titleEn;
	$descIt  = $info['desc_it'];

	$nameEs = '';
	$descEs = '';
	if (!$skipTranslation && !$dryRun) {
		$tn = llmTranslate($titleIt);
		if ($tn !== '') $nameEs = mb_substr($tn, 0, PRODUCT_NAME_MAX, 'UTF-8'); else $translateFail++;
		if ($descIt !== '') {
			$td = llmTranslate(cleanHtmlAggressive($descIt));
			if ($td !== '') $descEs = $td; else $translateFail++;
		}
	}
	if ($nameEs === '') $nameEs = mb_substr($titleEn, 0, PRODUCT_NAME_MAX, 'UTF-8');
	if ($descEs === '') $descEs = cleanHtmlAggressive($descIt !== '' ? $descIt : $titleEn);
	$nameEnRaw = mb_substr($titleEn, 0, PRODUCT_NAME_MAX, 'UTF-8');
	// EN description: solo el TITLE_EN del xlsx (Trem no provee descripción EN; no copiar la IT cruda).
	$descEn    = cleanHtmlAggressive($titleEn);

	if ($dryRun) {
		$nInserted++; $nStandaloneIns++;
		if ($nStandaloneIns <= 8) logMsg("  WOULD INSERT SUELTO sku=$sku name='" . mb_substr($nameEs ?: $nameEnRaw, 0, 50, 'UTF-8') . "'");
		continue;
	}

	$mysqli->begin_transaction();
	try {
		$qmodel = $mysqli->real_escape_string($sku);
		$qean   = $mysqli->real_escape_string($row['EAN']);
		$qmfg   = (int) $mfgId;
		$sql = "INSERT INTO products (products_quantity, check_stock, products_model, products_image, products_price, products_cost, products_date_added, products_weight, products_status, products_tax_class_id, manufacturers_id, product_ean, reference_prov, products_import_origin)
			VALUES (0, 0, \"$qmodel\", \"\", " . number_format($price, 4, '.', '') . ", " . number_format($cost, 4, '.', '') . ", NOW(), $weight, 2, " . TAX_CLASS_IVA21 . ", $qmfg, \"$qean\", \"$qmodel\", \"" . ORIGIN_FLAG . "\")";
		if (!$mysqli->query($sql)) throw new Exception("products: " . $mysqli->error);
		$pid = (int) $mysqli->insert_id;

		$qNameEs = $mysqli->real_escape_string($nameEs);
		$qDescEs = $mysqli->real_escape_string($descEs);
		$qNameEn = $mysqli->real_escape_string($nameEnRaw);
		$qDescEn = $mysqli->real_escape_string($descEn);
		if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_ES . ", \"$qNameEs\", \"$qDescEs\", 0)")) throw new Exception("desc ES: " . $mysqli->error);
		if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_EN . ", \"$qNameEn\", \"$qDescEn\", 0)")) throw new Exception("desc EN: " . $mysqli->error);
		if (!$mysqli->query("INSERT INTO products_to_categories (products_id, categories_id) VALUES ($pid, " . NEW_CATEGORY_ID . ")")) throw new Exception("p2c: " . $mysqli->error);
		if (!$mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . G1_GROUP_ID . ", $pid, " . number_format($g1, 4, '.', '') . ", 1, 1)")) throw new Exception("g1: " . $mysqli->error);

		if ($info['image_code'] !== '') {
			$srcImg = TREM_CATALOG_DIR . $info['image_code'] . '.jpg';
			if (file_exists($srcImg)) {
				$slug = tremSlugify($nameEs ?: $nameEnRaw);
				$finalName = $slug . '-' . $pid . '.jpg';
				$finalAbs = IMG_ABS_DIR . $finalName;
				if (copyImage($srcImg, $finalAbs)) {
					$mysqli->query("UPDATE products SET products_image=\"" . $mysqli->real_escape_string($finalName) . "\" WHERE products_id=$pid");
					$nWithImg++;
				} else { $imgFail++; }
			} else { $imgFail++; }
		}

		$mysqli->commit();
		if (!isValidEan13($row['EAN'])) {
			$genEan = generateInternalEan13($pid, EAN_INTERNAL_PREFIX);
			if ($genEan !== '') {
				$mysqli->query("UPDATE products SET product_ean='" . $mysqli->real_escape_string($genEan) . "' WHERE products_id=$pid AND (product_ean IS NULL OR product_ean='' OR LENGTH(product_ean)<>13)");
			}
		}

		$nInserted++; $nStandaloneIns++;
		logMsg("OK SUELTO pid=$pid sku=$sku price=$price cost=$cost g1=$g1");
	} catch (Exception $e) {
		$mysqli->rollback();
		$errors++;
		logMsg("ERROR suelto $sku: " . $e->getMessage());
	}
}

logMsg("==================== RESUMEN ====================");
logMsg("Insertados: $nInserted (familias=$nFamiliesIns sueltos=$nStandaloneIns)");
logMsg("Con imagen: $nWithImg | Con variantes: $nWithVar");
logMsg("Web fetch: ok=$webOk fail=$webFail");
logMsg("Imágenes fail: $imgFail");
logMsg("Traducciones fallidas: $translateFail");
logMsg("Errores INSERT: $errors");
if (!empty($mfgCreated)) {
	logMsg("Manufacturers " . ($dryRun ? "que se crearían" : "creados") . " (" . count($mfgCreated) . "):");
	foreach ($mfgCreated as $k => $v) logMsg("  · $v");
}

end_action:
?>
	</div>
	<p style="margin-top:15px;">
		<a href="<?php echo tep_href_link('import-trem-altas.php'); ?>" class="xbutton small hv9">← Volver</a>
	</p>
<?php else: ?>
	<h2>Importador Trem (altas) — con detección de variantes</h2>
	<p>
		Lee <code><?php echo TREM_XLSX; ?></code>, agrupa SKUs por familia (vía URL canónica de trem.net) e inserta en categoría
		<strong><?php echo NEW_CATEGORY_ID; ?> (Trem Nuevos)</strong> los que aún no estén en BD.
		Familias con N SKUs → 1 producto + N atributos sobre opción <strong><?php echo VARIANT_OPTION_ID; ?> ("Modelo")</strong>.
		Status=2 (pendiente revisión), check_stock=0.
	</p>
	<form method="get" style="background:#f5f5f5;padding:15px;border-radius:5px;">
		<p>
			<strong>SKUs específicos</strong> (opcional, ignora "Inserts máximos"; si el SKU es variante, importa toda la familia):<br>
			<textarea name="codes" rows="3" style="width:100%;font-family:monospace;" placeholder="Uno o varios SKUs del xlsx separados por coma, espacio o salto de línea."><?php echo htmlspecialchars($onlyCodesRaw); ?></textarea>
		</p>
		<p>
			<label><input type="checkbox" name="dry_run" value="1" checked> Dry-run (sin cambios)</label>
		</p>
		<p>
			<label><input type="checkbox" name="skip_translation" value="1"> Saltar traducción LLM (mucho más rápido)</label>
		</p>
		<p>
			<label><input type="checkbox" name="skip_web_enrich" value="1"> Saltar enriquecimiento web (sin agrupar variantes ni descripciones; cada SKU se inserta como suelto)</label>
		</p>
		<p>
			<label><input type="checkbox" name="prefetch_only" value="1"> Solo prefetch (rellena cache web sin insertar)</label>
		</p>
		<p>
			Inserts máximos por ejecución (0 = sin límite):
			<input type="number" name="max" value="20" min="0" style="width:80px;">
		</p>
		<button type="submit" name="action" value="dry_run" class="xbutton small hv9">Dry-run</button>
		<button type="submit" name="action" value="execute" class="xbutton small hv9 verde" onclick="return confirm('¿Ejecutar de verdad? Insertará productos en la BD.');">Ejecutar</button>
	</form>
	<p style="margin-top:20px;color:#888;font-size:12px;">
		<strong>Reglas aplicadas:</strong><br>
		- Familia = penúltimo segmento de la URL canónica de trem.net (p.ej. <code>drizza-da-regata-auckland</code>).<br>
		- Familias mixtas (>1 marca) se splittean por marca; familias con 1 SKU pasan a "sueltos".<br>
		- Variante padre = SKU más barato; resto se guardan como atributos con delta de precio (option_id=<?php echo VARIANT_OPTION_ID; ?>).<br>
		- Etiqueta de variante: prefijo común eliminado del título IT, fallback a medida (Ø, mm, kg…), fallback al SKU.<br>
		- <code>products_cost</code> = "export price" del xlsx (sin IVA, neto wholesale).<br>
		- <code>products_price</code> = cost × 2 (precio neto de venta sin IVA).<br>
		- G1 = price × <?php echo G1_FACTOR; ?> (regla Trem; sin tiers ni piso).<br>
		- Idiomas: ES traducido del IT (web) vía LLM; EN del xlsx Title.<br>
		- Imágenes: zip pre-extraído en <code><?php echo TREM_CATALOG_DIR; ?></code>; mapeo SKU→fichero vía data-image en trem.net.<br>
		- Cache HTML web en <code><?php echo TREM_CACHE_DIR; ?></code>.<br>
		- Stock NO se toca. Status 'D'/'X'/'F' del xlsx → skip. NOT_SELL ≠ vacío → skip.<br>
		- EAN: si el xlsx no trae EAN-13 válido → genera EAN interno con prefijo 23 (Trem).<br>
		- Output en streaming en tiempo real.
	</p>
<?php endif; ?>
</div>
</td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
