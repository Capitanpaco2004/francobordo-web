<?php
require 'includes/application_top.php';

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);

// ─── Configuración ──────────────────────────────────────────────────────────
const GARMIN_CSV          = '/home/francobordo/public_html/import/Garmin/priceList.csv';
const GARMIN_DEALER_ID    = '18723608';
const GARMIN_USER         = 'f.rodriguez@francobordo.com';
const GARMIN_PASS         = 'Garmin0908';
const GARMIN_SSO_LOGIN    = 'https://sso.garmin.com/sso/signin?service=https%3A%2F%2Fdealers.garmin.com%2Fdrc%2F&source=https%3A%2F%2Fdealers.garmin.com%2Fdrc%2F';
const GARMIN_CSV_URL      = 'https://dealers.garmin.com/drc/priceList/download/csv?selectedDealerId=' . GARMIN_DEALER_ID;
const GARMIN_COOKIE_FILE  = '/home/francobordo/public_html/import/Garmin/cookies.txt';

const ORIGIN_FLAG         = 'garmin';
const TAX_RATE            = 1.21;
const G1_GROUP_ID         = 1;
const G1_FACTOR           = 0.75;
const G1_FLOOR_FACTOR     = 1.12;
const PRICE_THRESHOLD     = 0.005;     // 0.5%
const MAX_CHANGE_PCT_DEF  = 30;        // tope superior default (configurable en form)
const SPECIAL_PVP_THRESHOLD = 100.0;
const SPECIAL_TIER_30_DISC  = 0.15;
const SPECIAL_TIER_24_DISC  = 0.10;
const SPECIAL_DURATION_DAYS = 30;

const GARMIN_MFG_IDS = [5, 449, 589, 590]; // Garmin, Fusion, JL Audio, Clarion

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$max    = isset($_POST['max']) ? (int) $_POST['max'] : (isset($_GET['max']) ? (int) $_GET['max'] : 0);
$confirmExec = isset($_POST['confirm_execute']) || isset($_GET['confirm_execute']); $dryRun = !($action === 'execute' && $confirmExec); // fix footgun: el botón plan nunca ejecuta
$skipDownload   = isset($_POST['skip_download']) || isset($_GET['skip_download']);
$updateSpecials = isset($_POST['update_specials']) || isset($_GET['update_specials']);
$scope = $_POST['scope'] ?? $_GET['scope'] ?? 'all'; // 'all' | 'no_stock'
$applyExtremes = isset($_POST['apply_extremes']) || isset($_GET['apply_extremes']);
$onlyExtremes = isset($_POST['only_extremes']) || isset($_GET['only_extremes']); // PLAN: mostrar SOLO los excluidos por extremos
$maxChangePct  = isset($_POST['max_change_pct']) ? (float) $_POST['max_change_pct'] : (isset($_GET['max_change_pct']) ? (float) $_GET['max_change_pct'] : MAX_CHANGE_PCT_DEF);
if ($maxChangePct < 0) $maxChangePct = 0;
$maxChangeRatio = $maxChangePct / 100.0;

function logMsg($msg) {
	if (!empty($GLOBALS['onlyExtremes'])) { static $lmSeen = false, $lmShow = true; if (strpos($msg, '====') !== false) { /* banner */ } elseif (strpos($msg, '--- ') !== false) { $lmSeen = true; $lmShow = (stripos($msg, 'EXTREMO') !== false); if (!$lmShow) return; } elseif ($lmSeen && !$lmShow) return; }
	$line = '[' . date('H:i:s') . '] ' . $msg . "\n";
	echo '<pre style="margin:0;padding:2px 8px;border-bottom:1px solid #eee;white-space:pre-wrap;overflow-wrap:break-word;word-break:break-word;font-family:monospace;font-size:12px;">' . htmlspecialchars($line) . '</pre>';
	@flush();
}
function fmt4($n) { return number_format((float) $n, 4, '.', ''); }
function priceDeltaPct($oldP, $newP) {
	$ref = max(abs((float) $oldP), 0.01);
	return abs((float) $newP - (float) $oldP) / $ref;
}

// ─── SSO + descarga CSV (idéntica al importador / stock sync) ────────────────
function garminCurl($url, $opts = []) {
	$ch = curl_init($url);
	$base = [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_COOKIEJAR      => GARMIN_COOKIE_FILE,
		CURLOPT_COOKIEFILE     => GARMIN_COOKIE_FILE,
		CURLOPT_TIMEOUT        => 60,
		CURLOPT_CONNECTTIMEOUT => 15,
		CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_POSTREDIR      => 7,
	];
	curl_setopt_array($ch, $base + $opts);
	$body = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$err  = curl_error($ch);
	unset($ch);
	return [$body, $code, $err];
}

function downloadGarminCsv($logger = null) {
	if (!is_dir(dirname(GARMIN_CSV))) @mkdir(dirname(GARMIN_CSV), 0775, true);
	@unlink(GARMIN_COOKIE_FILE);
	if ($logger) $logger("SSO 1/3: GET signin (CSRF)…");
	[$html, $code,] = garminCurl(GARMIN_SSO_LOGIN);
	if ($code !== 200 || !$html) return ['ok' => false, 'err' => "signin code=$code"];
	if (!preg_match('/name="_csrf"\s+value="([^"]+)"/', $html, $m)) return ['ok' => false, 'err' => 'no _csrf'];
	$csrf = $m[1];
	if ($logger) $logger("SSO 2/3: POST credenciales…");
	[$resp, $code,] = garminCurl(GARMIN_SSO_LOGIN, [
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => http_build_query(['username' => GARMIN_USER, 'password' => GARMIN_PASS, 'embed' => 'false', '_csrf' => $csrf]),
		CURLOPT_HTTPHEADER => ['Origin: https://sso.garmin.com', 'Referer: ' . GARMIN_SSO_LOGIN],
	]);
	if ($code !== 200 || stripos($resp ?: '', '<title>Success</title>') === false) return ['ok' => false, 'err' => 'login fallido'];
	if (!preg_match('/response_url\s*=\s*"([^"]+)"/', $resp, $tm)) return ['ok' => false, 'err' => 'no response_url'];
	$ticketUrl = stripslashes($tm[1]);
	if ($logger) $logger("SSO 3/3: consumiendo ticket…");
	garminCurl($ticketUrl);
	$cookieOk = file_exists(GARMIN_COOKIE_FILE) && strpos(@file_get_contents(GARMIN_COOKIE_FILE), 'DRC_JWT') !== false;
	if (!$cookieOk) return ['ok' => false, 'err' => 'sin DRC_JWT'];
	if ($logger) $logger("Descargando priceList CSV…");
	[$csv, $code,] = garminCurl(GARMIN_CSV_URL, [CURLOPT_TIMEOUT => 120]);
	if ($code !== 200 || !$csv) return ['ok' => false, 'err' => "CSV code=$code"];
	if (strpos($csv, '<!doctype html') !== false || strpos($csv, '<html') === 0) return ['ok' => false, 'err' => 'CSV devolvió HTML'];
	$tmp = GARMIN_CSV . '.tmp.' . uniqid();
	file_put_contents($tmp, $csv);
	if (filesize($tmp) < 10000) { @unlink($tmp); return ['ok' => false, 'err' => 'CSV pequeño']; }
	@rename($tmp, GARMIN_CSV);
	return ['ok' => true, 'size' => filesize(GARMIN_CSV)];
}

// ─── Parser CSV Garmin ────────────────────────────────────────────────────────
/**
 * Parsea decimal europeo. Reglas:
 *   "1.234,56" → 1234.56;  "12,99" → 12.99;
 *   "1.049"    → 1049 (heurística miles); "0.06" → 0.06.
 */
function garminParseEuroNum($v) {
	$v = trim((string) $v);
	if ($v === '') return null;
	$v = preg_replace('/[^0-9,.\-]/', '', $v);
	if ($v === '' || $v === '-') return null;
	if (substr_count($v, ',') === 1 && substr_count($v, '.') >= 1) {
		$v = str_replace('.', '', $v);
		$v = str_replace(',', '.', $v);
	} elseif (strpos($v, ',') !== false) {
		$v = str_replace(',', '.', $v);
	} elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $v)) {
		$v = str_replace('.', '', $v);
	}
	return is_numeric($v) ? (float) $v : null;
}

/** Carga del CSV indexado por SKU lowercase. */
function loadGarminCsvPrices($path) {
	$f = fopen($path, 'r');
	if (!$f) return [];
	fgetcsv($f, 0, ',', '"', ''); // disclaimer línea 1
	$header = fgetcsv($f, 0, ',', '"', '');
	if (!$header) { fclose($f); return []; }
	$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
	$idxSku   = array_search('ItemNo/Part Number', $header, true);
	$idxRsp   = array_search('RSP Price Inc Vat', $header, true);
	$idxDealer = array_search('Dealer Price', $header, true);
	if ($idxSku === false || $idxRsp === false || $idxDealer === false) { fclose($f); return []; }
	$prices = [];
	while (($r = fgetcsv($f, 0, ',', '"', '')) !== false) {
		$sku = strtolower(trim($r[$idxSku] ?? ''));
		if ($sku === '') continue;
		$rspIVA = garminParseEuroNum($r[$idxRsp] ?? '');
		$dealer = garminParseEuroNum($r[$idxDealer] ?? '');
		if ($rspIVA === null || $rspIVA <= 0 || $dealer === null || $dealer <= 0) continue;
		$cost  = $dealer;
		$price = roundToNickel($rspIVA / TAX_RATE);
		$prices[$sku] = ['cost' => $cost, 'price' => $price, 'rsp_iva' => $rspIVA];
	}
	fclose($f);
	return $prices;
}

function calcG1Price($price, $cost) {
	$price = (float) $price;
	if ($price <= 0) return 0.0;
	return roundToNickel(max($price * G1_FACTOR, (float) $cost * G1_FLOOR_FACTOR));
}

/** Redondea un PVP NETO de modo que el precio CON IVA quede en múltiplo de 0.05. */
function roundToNickel($net) {
	$wi = ((float) $net) * TAX_RATE;
	$r  = round($wi * 20) / 20;
	return round($r / TAX_RATE, 4);
}

/** Devuelve el precio especial sin IVA (positivo) si aplica, o null. */
function calcSpecialPrice($priceNoIva, $cost, $rspWithIva) {
	if ($rspWithIva <= SPECIAL_PVP_THRESHOLD) return null;
	if ($priceNoIva <= 0 || $cost <= 0) return null;
	$margin = ($priceNoIva - $cost) / $priceNoIva;
	if      ($margin >= 0.30) $disc = SPECIAL_TIER_30_DISC;
	elseif  ($margin >  0.24) $disc = SPECIAL_TIER_24_DISC;
	else                       return null;
	return roundToNickel($priceNoIva * (1 - $disc));
}

$isAction = ($action === 'plan' || $action === 'execute');

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
	<h2>Actualizador precios Garmin — <?php echo $dryRun ? 'PLAN (dry-run, sin cambios)' : 'EJECUCIÓN REAL'; ?></h2>
	<p>
		<a href="<?php echo tep_href_link('Actualizador_precios_garmin.php'); ?>" class="xbutton small hv9">← Volver</a>
	</p>
	<div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n";
@flush();

logMsg("Modo: " . ($dryRun ? "PLAN (dry-run)" : "EXECUTE")
	. " | scope=" . ($scope === 'no_stock' ? 'solo sin stock (qty<=0)' : 'todos los productos Garmin')
	. " | tope cambio=" . ($applyExtremes ? "OFF (aplicando también extremos)" : ("|Δ| ≤ " . rtrim(rtrim(number_format($maxChangePct, 1, '.', ''), '0'), '.') . "%"))
	. ($updateSpecials ? " | regenerar specials=ON" : "")
	. ($skipDownload ? " | sin descargar CSV" : "")
	. ($max > 0 ? " | max=$max cambios" : ""));

if (!$skipDownload) {
	logMsg("Descargando priceList desde dealers.garmin.com (SSO)…");
	$dl = downloadGarminCsv(fn($m) => logMsg("  $m"));
	if (!$dl['ok']) {
		logMsg("ERROR descarga: " . $dl['err']);
		if (!file_exists(GARMIN_CSV)) { logMsg("ERROR: no hay copia local — aborto"); goto end_action; }
		logMsg("AVISO: usando copia local existente");
	} else {
		logMsg("  ✓ CSV descargado: " . round($dl['size'] / 1024) . " KB");
	}
}

if (!file_exists(GARMIN_CSV)) { logMsg("ERROR: CSV no encontrado"); goto end_action; }

logMsg("Cargando precios del CSV…");
$csvPrices = loadGarminCsvPrices(GARMIN_CSV);
logMsg("SKUs en CSV con precio válido: " . count($csvPrices));

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_error) { logMsg("ERROR DB: " . $mysqli->connect_error); goto end_action; }
$mysqli->set_charset('utf8');

// ─── Cargar productos en scope ────────────────────────────────────────────────
$mfgIds = implode(',', array_map('intval', GARMIN_MFG_IDS));
$securityFilter = "(p.products_import_origin LIKE 'garmin%' OR p.manufacturers_id IN ($mfgIds))";
$scopeFilter = $scope === 'no_stock' ? " AND p.products_quantity <= 0" : "";

logMsg("Leyendo productos Garmin (filtro de seguridad" . ($scope === 'no_stock' ? ' + scope sin stock' : '') . ")…");
$prods = [];
$r = $mysqli->query("SELECT p.products_id, p.products_model, p.reference_prov, p.products_price, p.products_cost, p.products_quantity FROM products p WHERE $securityFilter $scopeFilter AND p.products_model<>''");
while ($row = $r->fetch_assoc()) $prods[(int) $row['products_id']] = $row;
logMsg("Productos en scope: " . count($prods));
if (empty($prods)) { logMsg("Nada que hacer."); goto end_action; }

// G1 actual en bulk
$ids = implode(',', array_keys($prods));
$g1Cur = [];
$r = $mysqli->query("SELECT products_id, customers_group_price FROM products_groups WHERE customers_group_id=" . G1_GROUP_ID . " AND products_id IN ($ids)");
while ($row = $r->fetch_assoc()) $g1Cur[(int) $row['products_id']] = (float) $row['customers_group_price'];

// Specials actuales (customers_group_id=0, status=1) — solo si update_specials=ON
$spCur = [];
if ($updateSpecials) {
	$r = $mysqli->query("SELECT specials_id, products_id, specials_new_products_price FROM specials WHERE customers_group_id=0 AND status=1 AND products_id IN ($ids)");
	while ($row = $r->fetch_assoc()) {
		$spCur[(int) $row['products_id']] = ['id' => (int) $row['specials_id'], 'price' => (float) $row['specials_new_products_price']];
	}
}

$updPrice = []; $updCost = []; $updG1 = []; $insG1 = [];
$updSpecial = []; $insSpecial = []; $delSpecial = [];
$extremesPrice = []; $extremesCost = []; $extremesPids = [];
$noMatch = []; $noChange = 0;

foreach ($prods as $pid => $p) {
	$model = strtolower(trim((string) $p['products_model']));
	$ref   = strtolower(trim((string) $p['reference_prov']));
	$candidates = array_unique(array_filter([$model, $ref]));
	$entry = null;
	foreach ($candidates as $c) {
		if (isset($csvPrices[$c])) { $entry = $csvPrices[$c]; break; }
	}
	if ($entry === null) { $noMatch[] = $p; continue; }

	$newCost  = $entry['cost'];
	$newPrice = $entry['price'];
	$newG1    = calcG1Price($newPrice, $newCost);
	$newSpecial = calcSpecialPrice($newPrice, $newCost, $entry['rsp_iva']);

	$curPrice = (float) $p['products_price'];
	$curCost  = (float) $p['products_cost'];

	$deltaPrice = priceDeltaPct($curPrice, $newPrice);
	$deltaCost  = priceDeltaPct($curCost,  $newCost);
	$priceChanged = $deltaPrice > PRICE_THRESHOLD;
	$costChanged  = $deltaCost  > PRICE_THRESHOLD;
	$priceExtreme = $maxChangeRatio > 0 && $deltaPrice > $maxChangeRatio;
	$costExtreme  = $maxChangeRatio > 0 && $deltaCost  > $maxChangeRatio;

	if ($priceChanged) {
		$row = ['pid' => $pid, 'model' => $p['products_model'], 'old' => $curPrice, 'new' => $newPrice];
		if ($priceExtreme && !$applyExtremes) { $extremesPrice[] = $row; $extremesPids[$pid] = true; }
		else $updPrice[] = $row;
	}
	if ($costChanged) {
		$row = ['pid' => $pid, 'model' => $p['products_model'], 'old' => $curCost, 'new' => $newCost];
		if ($costExtreme && !$applyExtremes) { $extremesCost[] = $row; $extremesPids[$pid] = true; }
		else $updCost[] = $row;
	}

	// Si el pid está en extremos, no tocamos G1 ni specials (G1 y special se derivan del price/cost).
	if (!isset($extremesPids[$pid])) {
		if (isset($g1Cur[$pid])) {
			if (priceDeltaPct($g1Cur[$pid], $newG1) > PRICE_THRESHOLD) {
				$updG1[] = ['pid' => $pid, 'model' => $p['products_model'], 'old' => $g1Cur[$pid], 'new' => $newG1];
			}
		} else {
			$insG1[] = ['pid' => $pid, 'model' => $p['products_model'], 'new' => $newG1];
		}

		// Specials: solo si el usuario pidió regenerar
		if ($updateSpecials) {
			$existingSp = $spCur[$pid] ?? null;
			if ($newSpecial !== null) {
				if ($existingSp === null) {
					$insSpecial[] = ['pid' => $pid, 'model' => $p['products_model'], 'new' => $newSpecial, 'rsp' => $entry['rsp_iva']];
				} elseif (priceDeltaPct($existingSp['price'], $newSpecial) > PRICE_THRESHOLD) {
					$updSpecial[] = ['pid' => $pid, 'model' => $p['products_model'], 'sid' => $existingSp['id'], 'old' => $existingSp['price'], 'new' => $newSpecial];
				}
			} else {
				if ($existingSp !== null) {
					$delSpecial[] = ['pid' => $pid, 'model' => $p['products_model'], 'sid' => $existingSp['id'], 'old' => $existingSp['price']];
				}
			}
		}
	}

	if (!$priceChanged && !$costChanged && isset($g1Cur[$pid]) && priceDeltaPct($g1Cur[$pid], $newG1) <= PRICE_THRESHOLD) $noChange++;
}

$totalChanges = count($updPrice) + count($updCost) + count($updG1) + count($insG1) + count($updSpecial) + count($insSpecial) + count($delSpecial);
if ($max > 0 && $totalChanges > $max) {
	logMsg("Plan supera max=$max cambios totales. Truncando proporcionalmente…");
	$keep = $max;
	$updPrice = array_slice($updPrice, 0, min($keep, count($updPrice))); $keep -= count($updPrice);
	$updCost  = array_slice($updCost,  0, min($keep, count($updCost)));  $keep -= count($updCost);
	$updG1    = array_slice($updG1,    0, min($keep, count($updG1)));    $keep -= count($updG1);
	$insG1    = array_slice($insG1,    0, min($keep, count($insG1)));    $keep -= count($insG1);
	$updSpecial = array_slice($updSpecial, 0, min($keep, count($updSpecial))); $keep -= count($updSpecial);
	$insSpecial = array_slice($insSpecial, 0, min($keep, count($insSpecial))); $keep -= count($insSpecial);
	$delSpecial = array_slice($delSpecial, 0, max(0, $keep));
}

logMsg("==================== PLAN ====================");
logMsg("UPDATE products.products_price : " . count($updPrice));
logMsg("UPDATE products.products_cost  : " . count($updCost));
logMsg("UPDATE products_groups (G1)    : " . count($updG1));
logMsg("INSERT products_groups (G1)    : " . count($insG1));
if ($updateSpecials) {
	logMsg("UPDATE specials                : " . count($updSpecial));
	logMsg("INSERT specials                : " . count($insSpecial));
	logMsg("DELETE specials (caducados)    : " . count($delSpecial));
}
if (!$applyExtremes && $maxChangeRatio > 0) {
	logMsg("⚠️  Extremos > {$maxChangePct}% EXCLUIDOS (revisar): price=" . count($extremesPrice) . " cost=" . count($extremesCost) . " (afecta a " . count($extremesPids) . " pids; sus G1 y specials tampoco se tocan)");
}
logMsg("Sin cambios significativos     : $noChange");
logMsg("Sin match en CSV               : " . count($noMatch));

$showLimit = 25; if (!empty($onlyExtremes)) $showLimit = 1000000;
if (!empty($updPrice)) {
	logMsg("--- UPDATE products_price (top $showLimit) ---");
	foreach (array_slice($updPrice, 0, $showLimit) as $u) {
		$pct = priceDeltaPct($u['old'], $u['new']) * 100;
		logMsg(sprintf("  pid=%d sku=%s : %.4f → %.4f (%s%.1f%%)", $u['pid'], $u['model'], $u['old'], $u['new'], $u['new']>=$u['old']?'+':'-', $pct));
	}
	if (count($updPrice) > $showLimit) logMsg("  …y " . (count($updPrice) - $showLimit) . " más");
}
if (!empty($updCost)) {
	logMsg("--- UPDATE products_cost (top $showLimit) ---");
	foreach (array_slice($updCost, 0, $showLimit) as $u) {
		$pct = priceDeltaPct($u['old'], $u['new']) * 100;
		logMsg(sprintf("  pid=%d sku=%s : cost %.4f → %.4f (%s%.1f%%)", $u['pid'], $u['model'], $u['old'], $u['new'], $u['new']>=$u['old']?'+':'-', $pct));
	}
	if (count($updCost) > $showLimit) logMsg("  …y " . (count($updCost) - $showLimit) . " más");
}
if (!empty($insG1)) {
	logMsg("--- INSERT G1 (top $showLimit) ---");
	foreach (array_slice($insG1, 0, $showLimit) as $u) logMsg(sprintf("  pid=%d sku=%s : (sin G1) → %.4f", $u['pid'], $u['model'], $u['new']));
	if (count($insG1) > $showLimit) logMsg("  …y " . (count($insG1) - $showLimit) . " más");
}
if (!empty($updG1)) {
	logMsg("--- UPDATE G1 (top $showLimit) ---");
	foreach (array_slice($updG1, 0, $showLimit) as $u) {
		$pct = priceDeltaPct($u['old'], $u['new']) * 100;
		logMsg(sprintf("  pid=%d sku=%s : G1 %.4f → %.4f (%s%.1f%%)", $u['pid'], $u['model'], $u['old'], $u['new'], $u['new']>=$u['old']?'+':'-', $pct));
	}
	if (count($updG1) > $showLimit) logMsg("  …y " . (count($updG1) - $showLimit) . " más");
}
if ($updateSpecials) {
	if (!empty($insSpecial)) {
		logMsg("--- INSERT specials (top $showLimit) ---");
		foreach (array_slice($insSpecial, 0, $showLimit) as $u) logMsg(sprintf("  pid=%d sku=%s : NEW special %.4f (PVP IVA %.2f)", $u['pid'], $u['model'], $u['new'], $u['rsp']));
		if (count($insSpecial) > $showLimit) logMsg("  …y " . (count($insSpecial) - $showLimit) . " más");
	}
	if (!empty($updSpecial)) {
		logMsg("--- UPDATE specials (top $showLimit) ---");
		foreach (array_slice($updSpecial, 0, $showLimit) as $u) {
			$pct = priceDeltaPct($u['old'], $u['new']) * 100;
			logMsg(sprintf("  pid=%d sku=%s : sp %.4f → %.4f (%s%.1f%%)", $u['pid'], $u['model'], $u['old'], $u['new'], $u['new']>=$u['old']?'+':'-', $pct));
		}
		if (count($updSpecial) > $showLimit) logMsg("  …y " . (count($updSpecial) - $showLimit) . " más");
	}
	if (!empty($delSpecial)) {
		logMsg("--- DELETE specials (top $showLimit) ---");
		foreach (array_slice($delSpecial, 0, $showLimit) as $u) logMsg(sprintf("  pid=%d sku=%s : DELETE special %.4f (ya no aplica fórmula)", $u['pid'], $u['model'], $u['old']));
		if (count($delSpecial) > $showLimit) logMsg("  …y " . (count($delSpecial) - $showLimit) . " más");
	}
}
if (!empty($extremesPrice)) {
	logMsg("--- ⚠️ EXTREMOS price (excluidos, top $showLimit) — probablemente pack-vs-unidad o error de feed ---");
	foreach (array_slice($extremesPrice, 0, $showLimit) as $u) {
		$pct = priceDeltaPct($u['old'], $u['new']) * 100;
		logMsg(sprintf("  pid=%d sku=%s : %.4f → %.4f (%s%.1f%%)", $u['pid'], $u['model'], $u['old'], $u['new'], $u['new']>=$u['old']?'+':'-', $pct));
	}
	if (count($extremesPrice) > $showLimit) logMsg("  …y " . (count($extremesPrice) - $showLimit) . " más");
}
if (!empty($extremesCost)) {
	logMsg("--- ⚠️ EXTREMOS cost (excluidos, top $showLimit) ---");
	foreach (array_slice($extremesCost, 0, $showLimit) as $u) {
		$pct = priceDeltaPct($u['old'], $u['new']) * 100;
		logMsg(sprintf("  pid=%d sku=%s : cost %.4f → %.4f (%s%.1f%%)", $u['pid'], $u['model'], $u['old'], $u['new'], $u['new']>=$u['old']?'+':'-', $pct));
	}
	if (count($extremesCost) > $showLimit) logMsg("  …y " . (count($extremesCost) - $showLimit) . " más");
}
if (!empty($noMatch)) {
	logMsg("--- Sin match en CSV (top $showLimit, informativo) ---");
	foreach (array_slice($noMatch, 0, $showLimit) as $p) logMsg(sprintf("  pid=%d sku=%s", (int)$p['products_id'], $p['products_model']));
	if (count($noMatch) > $showLimit) logMsg("  …y " . (count($noMatch) - $showLimit) . " más");
}

if ($dryRun) {
	logMsg("=== Dry-run finalizado. No se ha tocado nada. ===");
	goto end_action;
}

logMsg("Aplicando cambios en transacción única…");
$mysqli->begin_transaction();
try {
	foreach ($updPrice as $u) {
		if (!$mysqli->query("UPDATE products SET products_price=" . fmt4($u['new']) . ", products_last_modified=NOW() WHERE products_id=" . (int)$u['pid'])) throw new Exception("UPDATE price pid=" . $u['pid'] . ": " . $mysqli->error);
	}
	foreach ($updCost as $u) {
		if (!$mysqli->query("UPDATE products SET products_cost=" . fmt4($u['new']) . ", products_last_modified=NOW() WHERE products_id=" . (int)$u['pid'])) throw new Exception("UPDATE cost pid=" . $u['pid'] . ": " . $mysqli->error);
	}
	foreach ($updG1 as $u) {
		if (!$mysqli->query("UPDATE products_groups SET customers_group_price=" . fmt4($u['new']) . " WHERE products_id=" . (int)$u['pid'] . " AND customers_group_id=" . G1_GROUP_ID)) throw new Exception("UPDATE g1 pid=" . $u['pid'] . ": " . $mysqli->error);
	}
	foreach ($insG1 as $u) {
		if (!$mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . G1_GROUP_ID . ", " . (int)$u['pid'] . ", " . fmt4($u['new']) . ", 1, 1)")) throw new Exception("INSERT g1 pid=" . $u['pid'] . ": " . $mysqli->error);
	}
	if ($updateSpecials) {
		foreach ($updSpecial as $u) {
			if (!$mysqli->query("UPDATE specials SET specials_new_products_price=" . fmt4($u['new']) . ", specials_last_modified=NOW(), expires_date=DATE_ADD(NOW(), INTERVAL " . SPECIAL_DURATION_DAYS . " DAY) WHERE specials_id=" . (int)$u['sid'])) throw new Exception("UPDATE special sid=" . $u['sid'] . ": " . $mysqli->error);
		}
		foreach ($insSpecial as $u) {
			$expires = date('Y-m-d H:i:s', strtotime('+' . SPECIAL_DURATION_DAYS . ' days'));
			if (!$mysqli->query("INSERT INTO specials (products_id, specials_new_products_price, specials_date_added, specials_last_modified, expires_date, expires_repeat, status, customers_group_id, start_date) VALUES (" . (int)$u['pid'] . ", " . fmt4($u['new']) . ", NOW(), NULL, '$expires', 1, 1, 0, NOW())")) throw new Exception("INSERT special pid=" . $u['pid'] . ": " . $mysqli->error);
		}
		foreach ($delSpecial as $u) {
			if (!$mysqli->query("DELETE FROM specials WHERE specials_id=" . (int)$u['sid'])) throw new Exception("DELETE special sid=" . $u['sid'] . ": " . $mysqli->error);
		}
	}
	$mysqli->commit();
	logMsg("=== COMMIT OK ===");
	logMsg("UPDATE products_price aplicados : " . count($updPrice));
	logMsg("UPDATE products_cost  aplicados : " . count($updCost));
	logMsg("UPDATE G1 aplicados             : " . count($updG1));
	logMsg("INSERT G1 aplicados             : " . count($insG1));
	if ($updateSpecials) {
		logMsg("UPDATE specials aplicados       : " . count($updSpecial));
		logMsg("INSERT specials aplicados       : " . count($insSpecial));
		logMsg("DELETE specials aplicados       : " . count($delSpecial));
	}
} catch (Exception $e) {
	$mysqli->rollback();
	logMsg("=== ROLLBACK por error: " . $e->getMessage() . " ===");
}

end_action:
?>
	</div>
	<p style="margin-top:15px;">
		<a href="<?php echo tep_href_link('Actualizador_precios_garmin.php'); ?>" class="xbutton small hv9">← Volver</a>
	</p>
<?php else: ?>
	<h2>Actualizador de precios — Garmin</h2>
	<p>
		Descarga vía SSO el priceList CSV de <code>dealers.garmin.com</code> y compara <code>RSP/1.21</code> y <code>Dealer Price</code>
		con los <code>products_price</code> / <code>products_cost</code> y <code>products_groups</code> (G1) de la BD.
		Aplica cambios solo cuando la diferencia &gt; <strong><?php echo PRICE_THRESHOLD * 100; ?>%</strong>.
	</p>
	<form method="post" style="background:#f5f5f5;padding:15px;border-radius:5px;">
		<p>
			<strong>Scope (qué productos actualizar)</strong>:<br>
			<label><input type="radio" name="scope" value="all" checked> Todos los productos Garmin (origin garmin% o manufacturer Garmin/Fusion/JL Audio/Clarion)</label><br>
			<label><input type="radio" name="scope" value="no_stock"> Solo productos sin stock real (<code>products_quantity ≤ 0</code>)</label>
		</p>
		<p>
			<label><input type="checkbox" name="update_specials" value="1"> Regenerar ofertas (specials) según fórmula -10%/-15% para PVP &gt; 100€</label><br>
			<small style="color:#888;">Si está marcado: crea specials nuevos donde aplique, actualiza los existentes y borra los que ya no cumplen la fórmula. Solo afecta a specials con <code>customers_group_id=0</code>. Si no está marcado, no toca la tabla <code>specials</code>.</small>
		</p>
		<p>
			<label><input type="checkbox" name="skip_download" value="1"> No descargar CSV del SSO (usar copia local)</label>
		</p>
		<p>
			<strong>Tope de variación</strong>:
			<label>excluir cambios &gt;
				<input type="number" name="max_change_pct" value="<?php echo (int) MAX_CHANGE_PCT_DEF; ?>" min="0" max="500" step="1" style="width:60px;">
				%
			</label>
			<small style="color:#888;display:block;margin-top:4px;">Los cambios cuyo |Δ| supere este porcentaje se listan aparte como "extremos" y NO se aplican. 0 = sin tope. Pids con price o cost extremo no actualizan G1 ni specials. Protege contra errores de feed o SKUs reasignados.</small>
		</p>
		<p>
			<label><input type="checkbox" name="apply_extremes" value="1"> Aplicar también los extremos (desactiva el tope)</label>
		</p>
		<p>
			<label>Cambios máximos por ejecución (0 = sin límite):
				<input type="number" name="max" value="0" min="0" style="width:80px;">
			</label>
		</p>
		<p>
			<label><input type="checkbox" name="confirm_execute" value="1"> Aplicar cambios (sin marcar = solo PLAN/dry-run)</label>
		</p>
		<p><label><input type="checkbox" name="only_extremes" value="1"> <strong>Ver SOLO los productos saltados por extremos</strong> (en el plan, oculta el resto)</label></p>
		<button type="submit" name="action" value="plan" class="xbutton small hv9">Generar plan (dry-run)</button>
		<button type="submit" name="action" value="execute" class="xbutton small hv9 verde" onclick="return this.form.confirm_execute.checked || (alert('Marca la casilla \'Aplicar cambios\' antes de ejecutar.'), false);">Ejecutar</button>
	</form>
	<p style="margin-top:20px;color:#888;font-size:12px;">
		<strong>Reglas aplicadas:</strong><br>
		- <code>products_cost</code> ← <code>Dealer Price</code> (sin IVA).<br>
		- <code>products_price</code> ← <code>RSP / 1.21</code> (sin IVA, derivado del PVP con IVA 21%).<br>
		- <strong>Grupo 1 (Profesionales)</strong>: <code>max(price × <?php echo G1_FACTOR; ?>, cost × <?php echo G1_FLOOR_FACTOR; ?>)</code> — margen mínimo 12% sobre coste.<br>
		- <strong>Specials</strong> (solo si "Regenerar ofertas" está marcado): si <code>RSP CON IVA &gt; <?php echo SPECIAL_PVP_THRESHOLD; ?>€</code>:<br>
		&nbsp;&nbsp;· margen ≥ 30% → <strong>-<?php echo (int)(SPECIAL_TIER_30_DISC*100); ?>%</strong>; &gt; 24% y &lt; 30% → <strong>-<?php echo (int)(SPECIAL_TIER_24_DISC*100); ?>%</strong>; duración <?php echo SPECIAL_DURATION_DAYS; ?> días.<br>
		- Threshold: solo aplica si <code>|nuevo − actual| / |actual| &gt; <?php echo PRICE_THRESHOLD; ?></code>. Aplica por separado a price, cost, G1 y specials.<br>
		- <strong>Stock NO se toca</strong> — esto solo actualiza precios.<br>
		- Productos sin match en el CSV se listan informativamente y se dejan tal cual.<br>
		- Output en streaming en tiempo real.
	</p>
<?php endif; ?>
</div>
</td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
