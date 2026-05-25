<?php
require 'includes/application_top.php';

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);

const FNI_CSV          = '/home/francobordo/public_html/import/feed/FNI/Tracciato_master_10.csv';
const ORIGIN_FLAG      = 'fni';
const G1_GROUP_ID      = 1; // "Profesionales"
const G1_FLOOR_FACTOR  = 1.10; // piso: G1 ≥ cost × 1.10
const PRICE_THRESHOLD  = 0.005; // 0.5%
const MAX_CHANGE_PCT_DEF = 30;  // tope superior default (configurable en form)
const VAT_RATE         = 0.21;  // IVA 21% — usado para redondeo PVP a múltiplos de 0.05 con IVA

/** Redondea un PVP NETO de modo que el precio CON IVA quede en múltiplo de 0.05. */
function roundToNickel($net) {
	$wi = ((float) $net) * (1 + VAT_RATE);
	$r  = round($wi * 20) / 20;
	return round($r / (1 + VAT_RATE), 4);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$max    = isset($_POST['max']) ? (int) $_POST['max'] : (isset($_GET['max']) ? (int) $_GET['max'] : 0);
$confirmExec = isset($_POST['confirm_execute']) || isset($_GET['confirm_execute']); $dryRun = !($action === 'execute' && $confirmExec); // fix footgun: el botón plan nunca ejecuta
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

/** Convierte "1,234" → 1.234 (decimal europeo). null si no es numérico. */
function fniParseNum($v) {
	$v = trim((string) $v);
	if ($v === '') return null;
	$v = str_replace(['.', ','], ['', '.'], $v);
	return is_numeric($v) ? (float) $v : null;
}

/** Carga del CSV: por SKU → ['cost'=>NetPrice, 'price'=>PublicPrice o cost*2 si vacía/inferior]. */
function loadCsvPrices($path) {
	$f = fopen($path, 'r');
	if (!$f) return [];
	stream_filter_append($f, 'convert.iconv.CP1252/UTF-8');
	$prices = [];
	while (($r = fgetcsv($f, 0, ';', chr(34), '')) !== false) {
		if (count($r) < 30) continue;
		$sku    = trim($r[1] ?? '');
		$net    = fniParseNum($r[18] ?? '');
		$public = fniParseNum($r[19] ?? '');
		if ($sku === '' || $net === null || $net < 0) continue;
		$cost  = $net;
		$price = ($public !== null && $public > $cost) ? $public : $cost * 2.0;
		$prices[$sku] = ['cost' => $cost, 'price' => $price];
		$skuNoSpace = str_replace(' ', '', $sku);
		if ($skuNoSpace !== $sku && !isset($prices[$skuNoSpace])) $prices[$skuNoSpace] = ['cost' => $cost, 'price' => $price];
	}
	fclose($f);
	return $prices;
}

function priceDeltaPct($oldP, $newP) {
	$ref = max(abs((float) $oldP), 0.01);
	return abs((float) $newP - (float) $oldP) / $ref;
}

/**
 * G1 según margen real con piso cost × G1_FLOOR_FACTOR.
 * Tiers: ≥45% ×0.75, 40–45% ×0.80, 35–40% ×0.82, 30–35% ×0.85, <30% ×0.90.
 */
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
	$tier  = $price * $mult;
	$floor = $cost * G1_FLOOR_FACTOR;
	return round(max($tier, $floor), 4);
}

$isAction = ($action === 'plan' || $action === 'execute');

if ($isAction) {
	@header('X-Accel-Buffering: no');
	@header('Content-Type: text/html; charset=utf-8');
	while (ob_get_level() > 0) @ob_end_flush();
	@ob_implicit_flush(true);
}
?>
<?php require THEME . 'html/header.php'; ?>
<table style="width:100%;"><tr><td>
<div style="padding:20px;">
<?php if ($isAction): ?>
	<h2>Actualizador precios FNI — <?php echo $dryRun ? 'PLAN (dry-run, sin cambios)' : 'EJECUCIÓN REAL'; ?></h2>
	<p>
		<a href="<?php echo tep_href_link('Actualizador_precios_fni.php'); ?>" class="xbutton small hv9">← Volver</a>
	</p>
	<div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n";
@flush();

logMsg("Modo: " . ($dryRun ? "PLAN (dry-run)" : "EXECUTE")
	. " | tope cambio=" . ($applyExtremes ? "OFF (aplicando también extremos)" : ("|Δ| ≤ " . rtrim(rtrim(number_format($maxChangePct, 1, '.', ''), '0'), '.') . "%"))
	. ($max > 0 ? " | max=$max cambios" : ""));

if (!file_exists(FNI_CSV)) { logMsg("ERROR CSV no encontrado: " . FNI_CSV); goto end_action; }
logMsg("Cargando precios del CSV…");
$csvPrices = loadCsvPrices(FNI_CSV);
logMsg("SKUs en CSV: " . count($csvPrices));

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_error) { logMsg("ERROR DB: " . $mysqli->connect_error); goto end_action; }
$mysqli->set_charset('utf8');

logMsg("Leyendo productos con products_import_origin='" . ORIGIN_FLAG . "'…");
$prods = [];
$r = $mysqli->query("SELECT products_id, products_model, reference_prov, products_price, products_cost FROM products WHERE products_import_origin='" . ORIGIN_FLAG . "'");
while ($row = $r->fetch_assoc()) $prods[(int) $row['products_id']] = $row;
logMsg("Productos FNI en BD: " . count($prods));
if (empty($prods)) { logMsg("Nada que hacer."); goto end_action; }

// G1 actual en bulk
$ids = implode(',', array_keys($prods));
$g1Cur = [];
$r = $mysqli->query("SELECT products_id, customers_group_price FROM products_groups WHERE customers_group_id=" . G1_GROUP_ID . " AND products_id IN ($ids)");
while ($row = $r->fetch_assoc()) $g1Cur[(int) $row['products_id']] = (float) $row['customers_group_price'];

// Variantes (products_attributes) — para productos FNI con variantes
$attrsByProd = [];
$r = $mysqli->query("SELECT products_attributes_id, products_id, options_values_id, reference, reference_prov, options_values_price, price_prefix FROM products_attributes WHERE products_id IN ($ids)");
while ($row = $r->fetch_assoc()) $attrsByProd[(int) $row['products_id']][] = $row;

// G1 actual de variantes (delta sobre el G1 padre)
$paIds = [];
foreach ($attrsByProd as $arr) foreach ($arr as $a) $paIds[] = (int) $a['products_attributes_id'];
$g1AttrCur = [];
if (!empty($paIds)) {
	$paIn = implode(',', $paIds);
	$r = $mysqli->query("SELECT products_attributes_id, options_values_price, price_prefix FROM products_attributes_groups WHERE customers_group_id=" . G1_GROUP_ID . " AND products_attributes_id IN ($paIn)");
	while ($row = $r->fetch_assoc()) $g1AttrCur[(int) $row['products_attributes_id']] = $row;
}
logMsg("  → con variantes: " . count(array_filter($attrsByProd, fn($a) => !empty($a))) . " productos / " . count($paIds) . " atributos");

$updPrice = []; $updCost = []; $updG1 = []; $insG1 = []; $noMatch = []; $noChange = 0;
$updAttrPrice = []; $updAttrG1 = []; $insAttrG1 = [];
$extremesPrice = []; $extremesCost = []; $extremesPids = [];

foreach ($prods as $pid => $p) {
	$variants = $attrsByProd[$pid] ?? [];

	if (empty($variants)) {
		// ── Producto SUELTO ─────────────────────────────────────────────
		$model = trim((string) $p['products_model']);
		$ref   = trim((string) $p['reference_prov']);
		$candidates = array_unique(array_filter([$model, $ref, str_replace(' ', '', $model), str_replace(' ', '', $ref)]));
		$entry = null;
		foreach ($candidates as $c) {
			if (isset($csvPrices[$c])) { $entry = $csvPrices[$c]; break; }
		}
		if ($entry === null) { $noMatch[] = $p; continue; }

		$newCost  = $entry['cost'];
		$newPrice = roundToNickel($entry['price']);
		$newG1    = roundToNickel(calcG1Price($newPrice, $newCost));

		$curPrice = (float) $p['products_price'];
		$curCost  = (float) $p['products_cost'];

		$deltaPrice = priceDeltaPct($curPrice, $newPrice);
		$deltaCost  = priceDeltaPct($curCost,  $newCost);
		$priceChanged = $deltaPrice > PRICE_THRESHOLD;
		$costChanged  = $deltaCost  > PRICE_THRESHOLD;
		$priceExtreme = $maxChangeRatio > 0 && $deltaPrice > $maxChangeRatio;
		$costExtreme  = $maxChangeRatio > 0 && $deltaCost  > $maxChangeRatio;

		if ($priceChanged) {
			$row = ['pid' => $pid, 'model' => $model, 'old' => $curPrice, 'new' => $newPrice];
			if ($priceExtreme && !$applyExtremes) { $extremesPrice[] = $row; $extremesPids[$pid] = true; }
			else $updPrice[] = $row;
		}
		if ($costChanged) {
			$row = ['pid' => $pid, 'model' => $model, 'old' => $curCost, 'new' => $newCost];
			if ($costExtreme && !$applyExtremes) { $extremesCost[] = $row; $extremesPids[$pid] = true; }
			else $updCost[] = $row;
		}

		if (!isset($extremesPids[$pid])) {
			if (isset($g1Cur[$pid])) {
				if (priceDeltaPct($g1Cur[$pid], $newG1) > PRICE_THRESHOLD) {
					$updG1[] = ['pid' => $pid, 'model' => $model, 'old' => $g1Cur[$pid], 'new' => $newG1];
				}
			} else {
				$insG1[] = ['pid' => $pid, 'model' => $model, 'new' => $newG1];
			}
		}

		if (!$priceChanged && !$costChanged && isset($g1Cur[$pid]) && priceDeltaPct($g1Cur[$pid], $newG1) <= PRICE_THRESHOLD) $noChange++;
		continue;
	}

	// ── Producto CON VARIANTES ───────────────────────────────────────────
	// 1) Buscar precio de cada variante en CSV
	$variantPrices = [];
	$missing = [];
	foreach ($variants as $a) {
		$candidates = array_unique(array_filter([$a['reference'], $a['reference_prov'], str_replace(' ', '', $a['reference']), str_replace(' ', '', $a['reference_prov'])]));
		$entry = null;
		foreach ($candidates as $c) {
			if (isset($csvPrices[$c])) { $entry = $csvPrices[$c]; break; }
		}
		if ($entry === null) $missing[] = $a['reference'];
		else $variantPrices[(int) $a['products_attributes_id']] = [
			'cost'  => $entry['cost'],
			'price' => roundToNickel($entry['price']),
			'g1'    => roundToNickel(calcG1Price(roundToNickel($entry['price']), $entry['cost'])),
		];
	}
	if (!empty($missing)) {
		$noMatch[] = ['products_id' => $pid, 'products_model' => $p['products_model'] . ' (variante ' . implode(',', $missing) . ' sin match)', 'reference_prov' => ''];
		continue;
	}

	// 2) Variante más barata = padre
	usort($variants, fn($a, $b) => 0); // dummy keep order
	$cheapestPa = null; $cheapestPrice = PHP_FLOAT_MAX;
	foreach ($variantPrices as $paId => $vp) {
		if ($vp['price'] < $cheapestPrice) { $cheapestPrice = $vp['price']; $cheapestPa = $paId; }
	}
	$mainNew = $variantPrices[$cheapestPa];
	$newCost = $mainNew['cost']; $newPrice = $mainNew['price']; $newG1Main = $mainNew['g1'];

	$curPrice = (float) $p['products_price']; $curCost = (float) $p['products_cost'];
	$dP = priceDeltaPct($curPrice, $newPrice);
	$dC = priceDeltaPct($curCost,  $newCost);

	// Tope: si price o cost del padre cambian más del tope → excluir producto ENTERO (padre + variantes)
	if (!$applyExtremes && $maxChangeRatio > 0 && (max($dP, $dC) > $maxChangeRatio)) {
		if ($dP > PRICE_THRESHOLD) $extremesPrice[] = ['pid' => $pid, 'model' => $p['products_model'], 'old' => $curPrice, 'new' => $newPrice];
		if ($dC > PRICE_THRESHOLD) $extremesCost[]  = ['pid' => $pid, 'model' => $p['products_model'], 'old' => $curCost,  'new' => $newCost];
		$extremesPids[$pid] = true;
		continue;
	}

	if ($dP > PRICE_THRESHOLD) $updPrice[] = ['pid' => $pid, 'model' => $p['products_model'], 'old' => $curPrice, 'new' => $newPrice];
	if ($dC > PRICE_THRESHOLD) $updCost[]  = ['pid' => $pid, 'model' => $p['products_model'], 'old' => $curCost,  'new' => $newCost];

	// G1 padre
	if (isset($g1Cur[$pid])) {
		if (priceDeltaPct($g1Cur[$pid], $newG1Main) > PRICE_THRESHOLD) $updG1[] = ['pid' => $pid, 'model' => $p['products_model'], 'old' => $g1Cur[$pid], 'new' => $newG1Main];
	} else {
		$insG1[] = ['pid' => $pid, 'model' => $p['products_model'], 'new' => $newG1Main];
	}

	// 3) Cada variante: delta de price y G1 vs padre
	foreach ($variants as $a) {
		$paId = (int) $a['products_attributes_id'];
		if (!isset($variantPrices[$paId])) continue;
		$vp = $variantPrices[$paId];

		// delta price
		$delta = round($vp['price'] - $newPrice, 4);
		$prefix = $delta < 0 ? '-' : '+';
		$absDelta = abs($delta);
		$curAbs = (float) $a['options_values_price'];
		$curPref = $a['price_prefix'] ?: '+';
		$signedNew = ($prefix === '-' ? -$absDelta : $absDelta);
		$signedCur = ($curPref === '-' ? -$curAbs : $curAbs);
		if (priceDeltaPct($signedCur, $signedNew) > PRICE_THRESHOLD || ($absDelta > 0 && $curAbs == 0) || ($curPref !== $prefix && $absDelta > 0.0001)) {
			$updAttrPrice[] = ['paid' => $paId, 'pid' => $pid, 'ref' => $a['reference'], 'absOld' => $curAbs, 'prefOld' => $curPref, 'absNew' => $absDelta, 'prefNew' => $prefix];
		}

		// delta G1
		$g1Delta = round($vp['g1'] - $newG1Main, 4);
		$g1Prefix = $g1Delta < 0 ? '-' : '+';
		$g1Abs = abs($g1Delta);
		if (isset($g1AttrCur[$paId])) {
			$curG1Abs = (float) $g1AttrCur[$paId]['options_values_price'];
			$curG1Pref = $g1AttrCur[$paId]['price_prefix'] ?: '+';
			$signedNewG1 = ($g1Prefix === '-' ? -$g1Abs : $g1Abs);
			$signedCurG1 = ($curG1Pref === '-' ? -$curG1Abs : $curG1Abs);
			if (priceDeltaPct($signedCurG1, $signedNewG1) > PRICE_THRESHOLD || ($g1Abs > 0 && $curG1Abs == 0) || ($curG1Pref !== $g1Prefix && $g1Abs > 0.0001)) {
				$updAttrG1[] = ['paid' => $paId, 'pid' => $pid, 'absNew' => $g1Abs, 'prefNew' => $g1Prefix];
			}
		} else {
			$insAttrG1[] = ['paid' => $paId, 'pid' => $pid, 'absNew' => $g1Abs, 'prefNew' => $g1Prefix];
		}
	}
}

// Aplicar topes de max si están definidos (sobre el plan, no sobre procesados)
$totalChanges = count($updPrice) + count($updCost) + count($updG1) + count($insG1);
if ($max > 0 && $totalChanges > $max) {
	logMsg("Plan supera max=$max cambios totales. Truncando para esta ejecución…");
	// Repartir proporcionalmente: priorizar updates de price/cost, luego G1
	$keep = $max;
	$updPrice = array_slice($updPrice, 0, min($keep, count($updPrice))); $keep -= count($updPrice);
	$updCost  = array_slice($updCost,  0, min($keep, count($updCost)));  $keep -= count($updCost);
	$updG1    = array_slice($updG1,    0, min($keep, count($updG1)));    $keep -= count($updG1);
	$insG1    = array_slice($insG1,    0, max(0, $keep));
}

logMsg("==================== PLAN ====================");
logMsg("UPDATE products.products_price : " . count($updPrice));
logMsg("UPDATE products.products_cost  : " . count($updCost));
logMsg("UPDATE products_groups (G1)    : " . count($updG1));
logMsg("INSERT products_groups (G1)    : " . count($insG1));
logMsg("UPDATE products_attributes (variantes price) : " . count($updAttrPrice));
logMsg("UPDATE products_attributes_groups (G1 var)   : " . count($updAttrG1));
logMsg("INSERT products_attributes_groups (G1 var)   : " . count($insAttrG1));
if (!$applyExtremes && $maxChangeRatio > 0) {
	logMsg("⚠️  Extremos > {$maxChangePct}% EXCLUIDOS (revisar): price=" . count($extremesPrice) . " cost=" . count($extremesCost) . " (afecta a " . count($extremesPids) . " pids; sus G1 y variantes tampoco se tocan)");
}
logMsg("Sin cambios significativos      : $noChange");
logMsg("Sin match en CSV                : " . count($noMatch));

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
	foreach (array_slice($insG1, 0, $showLimit) as $u) {
		logMsg(sprintf("  pid=%d sku=%s : (sin G1) → %.4f", $u['pid'], $u['model'], $u['new']));
	}
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
	logMsg("--- Sin match en CSV (top $showLimit, informativo, no se tocan) ---");
	foreach (array_slice($noMatch, 0, $showLimit) as $p) {
		logMsg(sprintf("  pid=%d sku=%s ref=%s", (int)$p['products_id'], $p['products_model'], $p['reference_prov']));
	}
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
		$ok = $mysqli->query("UPDATE products SET products_price=" . fmt4($u['new']) . ", products_last_modified=NOW() WHERE products_id=" . (int)$u['pid']);
		if (!$ok) throw new Exception("UPDATE price pid=" . $u['pid'] . ": " . $mysqli->error);
	}
	foreach ($updCost as $u) {
		$ok = $mysqli->query("UPDATE products SET products_cost=" . fmt4($u['new']) . ", products_last_modified=NOW() WHERE products_id=" . (int)$u['pid']);
		if (!$ok) throw new Exception("UPDATE cost pid=" . $u['pid'] . ": " . $mysqli->error);
	}
	foreach ($updG1 as $u) {
		$ok = $mysqli->query("UPDATE products_groups SET customers_group_price=" . fmt4($u['new']) . " WHERE products_id=" . (int)$u['pid'] . " AND customers_group_id=" . G1_GROUP_ID);
		if (!$ok) throw new Exception("UPDATE g1 pid=" . $u['pid'] . ": " . $mysqli->error);
	}
	foreach ($insG1 as $u) {
		$ok = $mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . G1_GROUP_ID . ", " . (int)$u['pid'] . ", " . fmt4($u['new']) . ", 1, 1)");
		if (!$ok) throw new Exception("INSERT g1 pid=" . $u['pid'] . ": " . $mysqli->error);
	}
	foreach ($updAttrPrice as $u) {
		$ok = $mysqli->query("UPDATE products_attributes SET options_values_price=" . fmt4($u['absNew']) . ", price_prefix='" . $u['prefNew'] . "' WHERE products_attributes_id=" . (int)$u['paid']);
		if (!$ok) throw new Exception("UPDATE attr paid=" . $u['paid'] . ": " . $mysqli->error);
	}
	foreach ($updAttrG1 as $u) {
		$ok = $mysqli->query("UPDATE products_attributes_groups SET options_values_price=" . fmt4($u['absNew']) . ", price_prefix='" . $u['prefNew'] . "' WHERE products_attributes_id=" . (int)$u['paid'] . " AND customers_group_id=" . G1_GROUP_ID);
		if (!$ok) throw new Exception("UPDATE attr g1 paid=" . $u['paid'] . ": " . $mysqli->error);
	}
	foreach ($insAttrG1 as $u) {
		$ok = $mysqli->query("INSERT INTO products_attributes_groups (products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id, options_values_weight, weight_prefix) VALUES (" . (int)$u['paid'] . ", " . G1_GROUP_ID . ", " . fmt4($u['absNew']) . ", '" . $u['prefNew'] . "', " . (int)$u['pid'] . ", 0, '+')");
		if (!$ok) throw new Exception("INSERT attr g1 paid=" . $u['paid'] . ": " . $mysqli->error);
	}
	$mysqli->commit();
	logMsg("=== COMMIT OK ===");
	logMsg("UPDATE products_price aplicados: " . count($updPrice));
	logMsg("UPDATE products_cost  aplicados: " . count($updCost));
	logMsg("UPDATE G1 aplicados            : " . count($updG1));
	logMsg("INSERT G1 aplicados            : " . count($insG1));
	logMsg("UPDATE attr price aplicados    : " . count($updAttrPrice));
	logMsg("UPDATE attr G1 aplicados       : " . count($updAttrG1));
	logMsg("INSERT attr G1 aplicados       : " . count($insAttrG1));
} catch (Exception $e) {
	$mysqli->rollback();
	logMsg("=== ROLLBACK por error: " . $e->getMessage() . " ===");
}

end_action:
?>
	</div>
	<p style="margin-top:15px;">
		<a href="<?php echo tep_href_link('Actualizador_precios_fni.php'); ?>" class="xbutton small hv9">← Volver</a>
	</p>
<?php else: ?>
	<h2>Actualizador de precios — FNI</h2>
	<p>
		Compara <code>NetPrice</code>/<code>PublicPrice</code> del CSV con <code>products.products_cost</code>, <code>products_price</code> y <code>products_groups</code> (Grupo 1)
		para los productos con <code>products_import_origin='<?php echo ORIGIN_FLAG; ?>'</code>.
		Aplica cambios solo cuando la diferencia &gt; <strong><?php echo PRICE_THRESHOLD * 100; ?>%</strong>.
	</p>
	<form method="post" style="background:#f5f5f5;padding:15px;border-radius:5px;">
		<p>
			<strong>Tope de variación</strong>:
			<label>excluir cambios &gt;
				<input type="number" name="max_change_pct" value="<?php echo (int) MAX_CHANGE_PCT_DEF; ?>" min="0" max="500" step="1" style="width:60px;">
				%
			</label>
			<small style="color:#888;display:block;margin-top:4px;">Los cambios cuyo |Δ| supere este porcentaje se listan aparte como "extremos" y NO se aplican. 0 = sin tope. Útil para pack-vs-unidad o errores de feed.</small>
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
		<input type="hidden" name="action" value="plan">
		<p><label><input type="checkbox" name="only_extremes" value="1"> <strong>Ver SOLO los productos saltados por extremos</strong> (en el plan, oculta el resto)</label></p>
		<button type="submit" name="action" value="plan" class="xbutton small hv9">Generar plan (dry-run)</button>
		<button type="submit" name="action" value="execute" class="xbutton small hv9 verde" onclick="return this.form.confirm_execute.checked || (alert('Marca la casilla \'Aplicar cambios\' antes de ejecutar.'), false);">Ejecutar</button>
	</form>
	<p style="margin-top:20px;color:#888;font-size:12px;">
		<strong>Reglas aplicadas:</strong><br>
		- <code>products_cost</code> ← <code>NetPrice</code> del CSV (sin IVA).<br>
		- <code>products_price</code> ← <code>PublicPrice</code> si &gt; cost, si no <code>cost × 2</code> (margen 50% mínimo).<br>
		- <strong>Grupo 1</strong> con tiers según margen real: ≥45%→×0.75, 40–45%→×0.80, 35–40%→×0.82, 30–35%→×0.85, &lt;30%→×0.90.<br>
		&nbsp;&nbsp;Piso de seguridad: <code>G1 ≥ cost × <?php echo G1_FLOOR_FACTOR; ?></code> (margen mínimo 10% sobre coste).<br>
		- Threshold: solo aplica si <code>|nuevo − actual| / |actual| &gt; <?php echo PRICE_THRESHOLD; ?></code>. Aplica por separado a price, cost y G1.<br>
		- Stock: <strong>NO se toca</strong> (regla operativa).<br>
		- Productos sin match en el CSV se listan informativamente y se dejan tal cual.<br>
		- Solo procesa productos con <code>products_import_origin='<?php echo ORIGIN_FLAG; ?>'</code>.<br>
		- Decimal coma del CSV se convierte a punto al parsear.<br>
		- Output en streaming en tiempo real.
	</p>
<?php endif; ?>
</div>
</td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
