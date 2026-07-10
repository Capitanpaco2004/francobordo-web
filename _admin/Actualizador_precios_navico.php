<?php
require 'includes/application_top.php';

set_time_limit(0);
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);

// ─── Configuración ──────────────────────────────────────────────────────────
// Master Price List del portal Navico (https://customerportal.navico.com/en/my-company/pricing-lists/)
// Hoja "MPL": Brand | Part Number | Barcode | ... | RRP (EUR) | ... | Obsolete | ...
const NAVICO_DIR          = '/home/francobordo/public_html/import/Navico/';
const NAVICO_FILE_BASE    = 'pricebook_EMEA';           // se busca .xls y .xlsx (gana el más reciente)

const TAX_RATE            = 1.21;
const G1_GROUP_ID         = 1;
const G1_DISCOUNT         = 0.15;      // G1 = RRP × (1 − 0.15)
const G1_FLOOR_MARGIN     = 0.10;      // piso: margen 10% sobre PVP → cost / (1 − 0.10)
const COST_DISCOUNT       = 0.25;      // cost = RRP × (1 − 0.25)
const PRICE_THRESHOLD     = 0.005;     // 0.5%
const MAX_CHANGE_PCT_DEF  = 30;        // tope superior default (configurable en form)
const SPECIAL_RRP_MIN     = 50.0;      // oferta solo si RRP (neto, EUR de la tarifa) > 50
const SPECIAL_DISC        = 0.10;      // -10% sobre el PVP
const SPECIAL_DURATION_DAYS = 14;      // + expires_repeat=1 → se autorenueva en ciclos de 14 días

const NAVICO_MFG_IDS = [184, 292, 588, 293]; // Lowrance, Simrad, B&G, B&amp;G (duplicado legacy)

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$max    = isset($_POST['max']) ? (int) $_POST['max'] : (isset($_GET['max']) ? (int) $_GET['max'] : 0);
$confirmExec = isset($_POST['confirm_execute']) || isset($_GET['confirm_execute']); $dryRun = !($action === 'execute' && $confirmExec); // el botón plan nunca ejecuta
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

/** Redondea un PVP NETO de modo que el precio CON IVA quede en múltiplo de 0.05. */
function roundToNickel($net) {
	$wi = ((float) $net) * TAX_RATE;
	$r  = round($wi * 20) / 20;
	return round($r / TAX_RATE, 4);
}

/** G1 = RRP − 15%, acotado por debajo al piso de margen 10% sobre PVP (cost / 0.90). */
function calcG1Price($rrp, $cost) {
	$rrp = (float) $rrp;
	if ($rrp <= 0) return 0.0;
	$g1 = $rrp * (1 - G1_DISCOUNT);
	if ((float) $cost > 0) $g1 = max($g1, (float) $cost / (1 - G1_FLOOR_MARGIN));
	return roundToNickel($g1);
}

/** Oferta -10% (neto) si el RRP de la tarifa supera SPECIAL_RRP_MIN. */
function calcSpecialPrice($rrp) {
	if ((float) $rrp <= SPECIAL_RRP_MIN) return null;
	return roundToNickel((float) $rrp * (1 - SPECIAL_DISC));
}

/** Localiza el pricebook en NAVICO_DIR: pricebook_EMEA.xls / .xlsx, gana el mtime más reciente. */
function navicoFindPricebook() {
	$best = null;
	foreach (['xls', 'xlsx'] as $ext) {
		$f = NAVICO_DIR . NAVICO_FILE_BASE . '.' . $ext;
		if (is_file($f) && ($best === null || filemtime($f) > filemtime($best))) $best = $f;
	}
	return $best;
}

/**
 * Carga la Master Price List (hoja MPL). Devuelve [sku_lower => ['rrp','brand','obsolete','barcode','sku']].
 * Filas sin Part Number o sin RRP > 0 se saltan.
 */
function loadNavicoPrices($path, $logger = null) {
	require_once DIR_FS_CATALOG . 'includes/vendor/autoload.php';
	$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
	$reader->setReadDataOnly(true);
	$ss = $reader->load($path);
	$sheet = $ss->getSheetByName('MPL');
	if ($sheet === null) $sheet = $ss->getSheet(0);
	if ($logger) $logger('Hoja: ' . $sheet->getTitle() . ' (' . $sheet->getHighestRow() . ' filas)');

	// mapear cabecera (fila 1) por nombre
	$maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());
	$colIdx = [];
	for ($c = 1; $c <= $maxCol; $c++) {
		$h = trim((string) $sheet->getCell([$c, 1])->getValue());
		if ($h !== '') $colIdx[$h] = $c;
	}
	$cSku = $colIdx['Part Number'] ?? null;
	$cRrp = $colIdx['RRP (EUR)'] ?? null;
	if ($cRrp === null) { // fallback por si cambian la etiqueta de moneda/año
		foreach ($colIdx as $h => $c) { if (preg_match('/^RRP\s*\(/i', $h)) { $cRrp = $c; break; } }
	}
	$cBrand = $colIdx['Brand'] ?? null;
	$cObs   = $colIdx['Obsolete'] ?? null;
	$cBar   = $colIdx['Barcode'] ?? null;
	if ($cSku === null || $cRrp === null) {
		if ($logger) $logger('ERROR: cabecera sin "Part Number" / "RRP (EUR)". Columnas vistas: ' . implode(' | ', array_keys($colIdx)));
		return [];
	}

	$prices = []; $dups = 0;
	$hr = $sheet->getHighestRow();
	for ($r = 2; $r <= $hr; $r++) {
		$sku = trim((string) $sheet->getCell([$cSku, $r])->getValue());
		if ($sku === '') continue;
		$rrpRaw = $sheet->getCell([$cRrp, $r])->getValue();
		$rrp = is_numeric($rrpRaw) ? (float) $rrpRaw : (float) str_replace(',', '', trim((string) $rrpRaw));
		if ($rrp <= 0) continue;
		$key = strtolower($sku);
		if (isset($prices[$key])) { $dups++; continue; } // primer valor gana
		$prices[$key] = [
			'sku'      => $sku,
			'rrp'      => $rrp,
			'brand'    => $cBrand ? trim((string) $sheet->getCell([$cBrand, $r])->getValue()) : '',
			'obsolete' => $cObs ? (strtoupper(trim((string) $sheet->getCell([$cObs, $r])->getValue())) === 'Y') : false,
			'barcode'  => $cBar ? trim((string) $sheet->getCell([$cBar, $r])->getValue()) : '',
		];
	}
	if ($dups && $logger) $logger("AVISO: $dups Part Number duplicados en la tarifa (se usa la primera fila)");
	$ss->disconnectWorksheets();
	return $prices;
}

// ─── Upload del pricebook (form multipart, opcional) ────────────────────────
$uploadMsg = '';
if (!empty($_FILES['pricefile']['name']) && is_uploaded_file($_FILES['pricefile']['tmp_name'] ?? '')) {
	$ext = strtolower(pathinfo($_FILES['pricefile']['name'], PATHINFO_EXTENSION));
	if (!in_array($ext, ['xls', 'xlsx'], true)) {
		$uploadMsg = 'ERROR upload: solo .xls / .xlsx';
	} elseif ($_FILES['pricefile']['size'] > 30 * 1024 * 1024) {
		$uploadMsg = 'ERROR upload: fichero > 30 MB';
	} else {
		if (!is_dir(NAVICO_DIR)) @mkdir(NAVICO_DIR, 0775, true);
		$dest = NAVICO_DIR . NAVICO_FILE_BASE . '.' . $ext;
		$other = NAVICO_DIR . NAVICO_FILE_BASE . '.' . ($ext === 'xls' ? 'xlsx' : 'xls');
		if (move_uploaded_file($_FILES['pricefile']['tmp_name'], $dest)) {
			@unlink($other); // que no quede una copia vieja en el otro formato ganando por mtime
			$uploadMsg = 'Tarifa subida: ' . basename($dest) . ' (' . round(filesize($dest) / 1024) . ' KB)';
		} else {
			$uploadMsg = 'ERROR upload: no se pudo mover el fichero (permisos?)';
		}
	}
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
	<h2>Actualizador precios Navico (Lowrance / Simrad / B&amp;G) — <?php echo $dryRun ? 'PLAN (dry-run, sin cambios)' : 'EJECUCIÓN REAL'; ?></h2>
	<p>
		<a href="<?php echo tep_href_link('Actualizador_precios_navico.php'); ?>" class="xbutton small hv9">← Volver</a>
	</p>
	<div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n";
@flush();

if ($uploadMsg !== '') logMsg($uploadMsg);

logMsg("Modo: " . ($dryRun ? "PLAN (dry-run)" : "EXECUTE")
	. " | scope=" . ($scope === 'no_stock' ? 'solo sin stock (qty<=0)' : 'todos los productos Navico')
	. " | tope cambio=" . ($applyExtremes ? "OFF (aplicando también extremos)" : ("|Δ| ≤ " . rtrim(rtrim(number_format($maxChangePct, 1, '.', ''), '0'), '.') . "%"))
	. ($updateSpecials ? " | regenerar specials=ON" : "")
	. ($max > 0 ? " | max=$max cambios" : ""));

$pricebook = navicoFindPricebook();
if ($pricebook === null) { logMsg("ERROR: no hay tarifa en " . NAVICO_DIR . NAVICO_FILE_BASE . ".xls(x) — súbela desde el formulario"); goto end_action; }
logMsg("Tarifa: " . basename($pricebook) . " (" . round(filesize($pricebook) / 1024) . " KB, " . date('d/m/Y H:i', filemtime($pricebook)) . ")");

logMsg("Cargando Master Price List…");
$xlsPrices = loadNavicoPrices($pricebook, 'logMsg');
logMsg("SKUs en tarifa con RRP válido: " . count($xlsPrices));
if (empty($xlsPrices)) { logMsg("ERROR: tarifa vacía o cabecera no reconocida"); goto end_action; }

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_error) { logMsg("ERROR DB: " . $mysqli->connect_error); goto end_action; }
$mysqli->set_charset('utf8');

// ─── Cargar productos en scope ────────────────────────────────────────────────
$mfgIds = implode(',', array_map('intval', NAVICO_MFG_IDS));
$securityFilter = "(p.products_import_origin LIKE 'navico%' OR p.manufacturers_id IN ($mfgIds))";
$scopeFilter = $scope === 'no_stock' ? " AND p.products_quantity <= 0" : "";

logMsg("Leyendo productos Navico (filtro de seguridad" . ($scope === 'no_stock' ? ' + scope sin stock' : '') . ")…");
$prods = [];
$r = $mysqli->query("SELECT p.products_id, p.products_model, p.reference_prov, p.products_price, p.products_cost, p.products_quantity, p.products_status, pd.products_name FROM products p LEFT JOIN products_description pd ON pd.products_id=p.products_id AND pd.language_id=3 WHERE $securityFilter $scopeFilter AND p.products_model<>''");
while ($row = $r->fetch_assoc()) $prods[(int) $row['products_id']] = $row;
logMsg("Productos en scope: " . count($prods));
if (empty($prods)) { logMsg("Nada que hacer."); goto end_action; }

$ids = implode(',', array_keys($prods));

// G1 actual en bulk
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

// Productos con variantes (informativo: los deltas de atributo NO se tocan)
$hasAttrs = [];
$r = $mysqli->query("SELECT products_id, COUNT(*) c FROM products_attributes WHERE products_id IN ($ids) GROUP BY products_id");
while ($row = $r->fetch_assoc()) $hasAttrs[(int) $row['products_id']] = (int) $row['c'];

$updPrice = []; $updCost = []; $updG1 = []; $insG1 = [];
$updSpecial = []; $insSpecial = []; $delSpecial = [];
$extremesPrice = []; $extremesCost = []; $extremesPids = [];
$noMatch = []; $noChange = 0; $obsoleteActive = []; $withAttrsMatched = [];

foreach ($prods as $pid => $p) {
	$model = strtolower(trim((string) $p['products_model']));
	$ref   = strtolower(trim((string) $p['reference_prov']));
	$candidates = array_unique(array_filter([$model, $ref]));
	$entry = null;
	foreach ($candidates as $c) {
		if (isset($xlsPrices[$c])) { $entry = $xlsPrices[$c]; break; }
	}
	if ($entry === null) { $noMatch[] = $p; continue; }

	$rrp = $entry['rrp'];
	if ($entry['obsolete'] && (int) $p['products_status'] === 1) {
		$obsoleteActive[] = ['pid' => $pid, 'model' => $p['products_model'], 'name' => $p['products_name'] ?? '', 'rrp' => $rrp];
	}
	if (isset($hasAttrs[$pid])) {
		$withAttrsMatched[] = ['pid' => $pid, 'model' => $p['products_model'], 'n' => $hasAttrs[$pid]];
	}

	// Reglas Navico: PVP neto = RRP (el cliente ve RRP+IVA), cost = RRP−25%, G1 = RRP−15%, oferta −10% si RRP>50
	$newPrice = roundToNickel($rrp);
	$newCost  = round($rrp * (1 - COST_DISCOUNT), 4);
	$newG1    = calcG1Price($rrp, $newCost);
	$newSpecial = calcSpecialPrice($rrp);

	$curPrice = (float) $p['products_price'];
	$curCost  = (float) $p['products_cost'];

	$deltaPrice = priceDeltaPct($curPrice, $newPrice);
	$deltaCost  = priceDeltaPct($curCost,  $newCost);
	$priceChanged = $deltaPrice > PRICE_THRESHOLD;
	$costChanged  = $deltaCost  > PRICE_THRESHOLD;
	$priceExtreme = $maxChangeRatio > 0 && $deltaPrice > $maxChangeRatio;
	// Coste 0 en BD (dato ausente, p.ej. pisado por el sync QFacWin): NO dividir por ~0 para decidir
	// "extremo" (daría +XXXXX% falso). Referencia = curPrice × 0.75 (≡ RRP−25% si el price estaba bien).
	$deltaCostExtreme = ($curCost > 0 || $curPrice <= 0) ? $deltaCost : priceDeltaPct($curPrice * 0.75, $newCost);
	$costExtreme  = $maxChangeRatio > 0 && $deltaCostExtreme > $maxChangeRatio;

	if ($priceChanged) {
		$row = ['pid' => $pid, 'model' => $p['products_model'], 'name' => $p['products_name'] ?? '', 'old' => $curPrice, 'new' => $newPrice];
		if ($priceExtreme && !$applyExtremes) { $extremesPrice[] = $row; $extremesPids[$pid] = true; }
		else $updPrice[] = $row;
	}
	if ($costChanged) {
		$row = ['pid' => $pid, 'model' => $p['products_model'], 'name' => $p['products_name'] ?? '', 'old' => $curCost, 'new' => $newCost];
		if ($costExtreme && !$applyExtremes) { $extremesCost[] = $row; $extremesPids[$pid] = true; }
		else $updCost[] = $row;
	}

	// Si el pid está en extremos, no tocamos G1 ni specials (se derivan del price/cost).
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
					$insSpecial[] = ['pid' => $pid, 'model' => $p['products_model'], 'new' => $newSpecial, 'rrp' => $rrp];
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
	logMsg("DELETE specials (RRP ≤ " . (int) SPECIAL_RRP_MIN . "€)  : " . count($delSpecial));
}
if (!$applyExtremes && $maxChangeRatio > 0) {
	logMsg("⚠️  Extremos > {$maxChangePct}% EXCLUIDOS (revisar): price=" . count($extremesPrice) . " cost=" . count($extremesCost) . " (afecta a " . count($extremesPids) . " pids; sus G1 y specials tampoco se tocan)");
}
logMsg("Sin cambios significativos     : $noChange");
logMsg("Sin match en tarifa            : " . count($noMatch));
logMsg("Obsoletos Navico ACTIVOS web   : " . count($obsoleteActive) . " (informativo, NO se tocan)");
logMsg("Con variantes (deltas intactos): " . count($withAttrsMatched));

$showLimit = 25; if (!empty($onlyExtremes)) $showLimit = 1000000;
if (isset($_GET["show_limit"]) || isset($_POST["show_limit"])) $showLimit = max(1, (int)($_GET["show_limit"] ?? $_POST["show_limit"]));
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
		foreach (array_slice($insSpecial, 0, $showLimit) as $u) logMsg(sprintf("  pid=%d sku=%s : NEW special %.4f (RRP %.2f)", $u['pid'], $u['model'], $u['new'], $u['rrp']));
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
		foreach (array_slice($delSpecial, 0, $showLimit) as $u) logMsg(sprintf("  pid=%d sku=%s : DELETE special %.4f (RRP ≤ %d€, ya no aplica)", $u['pid'], $u['model'], $u['old'], (int) SPECIAL_RRP_MIN));
		if (count($delSpecial) > $showLimit) logMsg("  …y " . (count($delSpecial) - $showLimit) . " más");
	}
}
if (!empty($extremesPrice)) {
	logMsg("--- ⚠️ EXTREMOS price (excluidos, top $showLimit) — probablemente pack-vs-unidad o error de tarifa ---");
	foreach (array_slice($extremesPrice, 0, $showLimit) as $u) {
		$pct = priceDeltaPct($u['old'], $u['new']) * 100;
		logMsg(sprintf("  pid=%d sku=%s «%s» : %.4f → %.4f (%s%.1f%%)", $u['pid'], $u['model'], mb_substr($u['name'] ?? '', 0, 60, 'UTF-8'), $u['old'], $u['new'], $u['new']>=$u['old']?'+':'-', $pct));
	}
	if (count($extremesPrice) > $showLimit) logMsg("  …y " . (count($extremesPrice) - $showLimit) . " más");
}
if (!empty($extremesCost)) {
	logMsg("--- ⚠️ EXTREMOS cost (excluidos, top $showLimit) ---");
	foreach (array_slice($extremesCost, 0, $showLimit) as $u) {
		$pct = priceDeltaPct($u['old'], $u['new']) * 100;
		logMsg(sprintf("  pid=%d sku=%s «%s» : cost %.4f → %.4f (%s%.1f%%)", $u['pid'], $u['model'], mb_substr($u['name'] ?? '', 0, 60, 'UTF-8'), $u['old'], $u['new'], $u['new']>=$u['old']?'+':'-', $pct));
	}
	if (count($extremesCost) > $showLimit) logMsg("  …y " . (count($extremesCost) - $showLimit) . " más");
}
if (!empty($obsoleteActive)) {
	logMsg("--- Obsoletos en tarifa Navico pero ACTIVOS en web (informativo, top $showLimit) ---");
	foreach (array_slice($obsoleteActive, 0, $showLimit) as $u) logMsg(sprintf("  pid=%d sku=%s «%s»", $u['pid'], $u['model'], mb_substr($u['name'] ?? '', 0, 60, 'UTF-8')));
	if (count($obsoleteActive) > $showLimit) logMsg("  …y " . (count($obsoleteActive) - $showLimit) . " más");
}
if (!empty($withAttrsMatched)) {
	logMsg("--- Con variantes — los deltas de atributo NO se tocan (top $showLimit) ---");
	foreach (array_slice($withAttrsMatched, 0, $showLimit) as $u) logMsg(sprintf("  pid=%d sku=%s (%d atributos)", $u['pid'], $u['model'], $u['n']));
	if (count($withAttrsMatched) > $showLimit) logMsg("  …y " . (count($withAttrsMatched) - $showLimit) . " más");
}
if (!empty($noMatch)) {
	logMsg("--- Sin match en tarifa (top $showLimit, informativo) ---");
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
			// start_date=NOW() + sync del resto de ofertas activas del producto: evita el gotcha
			// MAX(start_date) de PriceFormatterStore (si otra oferta del producto tiene start más
			// reciente, la cgid=0 se vuelve invisible).
			if (!$mysqli->query("UPDATE specials SET specials_new_products_price=" . fmt4($u['new']) . ", specials_last_modified=NOW(), start_date=NOW(), expires_date=DATE_ADD(NOW(), INTERVAL " . SPECIAL_DURATION_DAYS . " DAY), expires_repeat=1 WHERE specials_id=" . (int)$u['sid'])) throw new Exception("UPDATE special sid=" . $u['sid'] . ": " . $mysqli->error);
			$mysqli->query("UPDATE specials SET start_date=NOW() WHERE products_id=" . (int)$u['pid'] . " AND status=1");
		}
		foreach ($insSpecial as $u) {
			$expires = date('Y-m-d H:i:s', strtotime('+' . SPECIAL_DURATION_DAYS . ' days'));
			if (!$mysqli->query("INSERT INTO specials (products_id, specials_new_products_price, specials_date_added, specials_last_modified, expires_date, expires_repeat, status, customers_group_id, start_date) VALUES (" . (int)$u['pid'] . ", " . fmt4($u['new']) . ", NOW(), NOW(), '$expires', 1, 1, 0, NOW())")) throw new Exception("INSERT special pid=" . $u['pid'] . ": " . $mysqli->error);
			$mysqli->query("UPDATE specials SET start_date=NOW() WHERE products_id=" . (int)$u['pid'] . " AND status=1");
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
		<a href="<?php echo tep_href_link('Actualizador_precios_navico.php'); ?>" class="xbutton small hv9">← Volver</a>
	</p>
<?php else: ?>
	<h2>Actualizador de precios — Navico (Lowrance / Simrad / B&amp;G)</h2>
	<p>
		Lee la <strong>Master Price List EMEA</strong> del
		<a href="https://customerportal.navico.com/en/my-company/pricing-lists/" target="_blank" rel="noopener">portal Navico</a>
		(hoja <code>MPL</code>, columna <code>RRP (EUR)</code> — precio neto SIN IVA) y compara con
		<code>products_price</code> / <code>products_cost</code> / <code>products_groups</code> (G1) de la BD.
		Aplica cambios solo cuando la diferencia &gt; <strong><?php echo PRICE_THRESHOLD * 100; ?>%</strong>.
	</p>
	<?php if ($uploadMsg !== ''): ?>
		<p style="background:#fff3cd;border:1px solid #ffc107;padding:8px 12px;border-radius:4px;"><?php echo htmlspecialchars($uploadMsg); ?></p>
	<?php endif; ?>
	<?php $pb = navicoFindPricebook(); ?>
	<form method="post" enctype="multipart/form-data" style="background:#f5f5f5;padding:15px;border-radius:5px;">
		<p>
			<strong>Tarifa en servidor</strong>:
			<?php if ($pb): ?>
				<code><?php echo basename($pb); ?></code> — <?php echo round(filesize($pb) / 1024); ?> KB, subida el <?php echo date('d/m/Y H:i', filemtime($pb)); ?>
			<?php else: ?>
				<span style="color:#c00;">NO HAY TARIFA — sube el .xls antes de generar el plan</span>
			<?php endif; ?>
			<br>
			<label>Reemplazar tarifa (Master Price List .xls/.xlsx del portal):
				<input type="file" name="pricefile" accept=".xls,.xlsx">
			</label>
		</p>
		<p>
			<strong>Scope (qué productos actualizar)</strong>:<br>
			<label><input type="radio" name="scope" value="all" checked> Todos los productos Navico (manufacturer Lowrance / Simrad / B&amp;G, o origin navico%)</label><br>
			<label><input type="radio" name="scope" value="no_stock"> Solo productos sin stock real (<code>products_quantity ≤ 0</code>)</label>
		</p>
		<p>
			<label><input type="checkbox" name="update_specials" value="1"> Regenerar ofertas (specials) <strong>SOLO Retail/G0</strong>: <strong>−<?php echo (int)(SPECIAL_DISC*100); ?>%</strong> autorenovable si <code>RRP &gt; <?php echo (int) SPECIAL_RRP_MIN; ?>€</code></label><br>
			<small style="color:#888;">Si está marcado: crea la oferta donde aplique, actualiza las existentes al −<?php echo (int)(SPECIAL_DISC*100); ?>% del PVP nuevo y borra las de productos con RRP ≤ <?php echo (int) SPECIAL_RRP_MIN; ?>€. Solo toca specials con <code>customers_group_id=0</code> (ojo: pisa ofertas manuales Retail que difieran de la fórmula). <strong>El G1 (Profesionales) NO ve estas ofertas</strong> — la tienda filtra specials por grupo (SPPC); los Profesionales pagan siempre su G1 = RRP × <?php echo 1 - G1_DISCOUNT; ?>. Las ofertas llevan <code>expires_repeat=1</code> → se autorenuevan cada <?php echo (int) SPECIAL_DURATION_DAYS; ?> días.</small>
		</p>
		<p>
			<strong>Tope de variación</strong>:
			<label>excluir cambios &gt;
				<input type="number" name="max_change_pct" value="<?php echo (int) MAX_CHANGE_PCT_DEF; ?>" min="0" max="500" step="1" style="width:60px;">
				%
			</label>
			<small style="color:#888;display:block;margin-top:4px;">Los cambios cuyo |Δ| supere este porcentaje se listan aparte como "extremos" y NO se aplican. 0 = sin tope. Pids con price o cost extremo no actualizan G1 ni specials. Protege contra errores de tarifa o SKUs reasignados.</small>
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
		<strong>Reglas aplicadas</strong> (RRP = columna <code>RRP (EUR)</code> de la tarifa, neto sin IVA):<br>
		- <code>products_price</code> ← RRP (el cliente ve RRP + 21% IVA), redondeado para que el PVP con IVA acabe en múltiplo de 0,05.<br>
		- <code>products_cost</code> ← RRP × <?php echo 1 - COST_DISCOUNT; ?> (descuento del <?php echo (int)(COST_DISCOUNT*100); ?>% sobre RRP).<br>
		- <strong>Grupo 1 (Profesionales)</strong>: RRP × <?php echo 1 - G1_DISCOUNT; ?> (−<?php echo (int)(G1_DISCOUNT*100); ?>%), con piso de margen <?php echo (int)(G1_FLOOR_MARGIN*100); ?>% sobre PVP (<code>cost / <?php echo 1 - G1_FLOOR_MARGIN; ?></code>).<br>
		- <strong>Oferta −<?php echo (int)(SPECIAL_DISC*100); ?>% SOLO para G0/Retail</strong> (solo si "Regenerar ofertas" está marcado): si RRP &gt; <?php echo (int) SPECIAL_RRP_MIN; ?>€, autorenovable (<code>expires_repeat=1</code>, ciclos de <?php echo (int) SPECIAL_DURATION_DAYS; ?> días). El G1 no la ve (specials filtradas por grupo en PriceFormatter/SPPC).<br>
		- Threshold: solo aplica si <code>|nuevo − actual| / |actual| &gt; <?php echo PRICE_THRESHOLD; ?></code>. Aplica por separado a price, cost, G1 y specials.<br>
		- <strong>Stock, status, peso y EAN NO se tocan.</strong> Obsoletos de la tarifa: solo se listan.<br>
		- Productos sin match en la tarifa se listan informativamente y se dejan tal cual.<br>
		- Marcas cubiertas: manufacturers <?php echo implode(', ', NAVICO_MFG_IDS); ?> (Lowrance, Simrad, B&amp;G y B&amp;amp;G legacy).
	</p>
<?php endif; ?>
</div>
</td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
