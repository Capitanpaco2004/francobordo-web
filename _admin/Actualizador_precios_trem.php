<?php
require 'includes/application_top.php';
require_once dirname(dirname(__FILE__)) . '/includes/vendor/autoload.php';

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);

const TREM_XLSX        = '/home/francobordo/public_html/import/Trem/listino-export.xlsx';
const ORIGIN_FLAG      = 'trem';
const G1_GROUP_ID      = 1;
const G1_FACTOR        = 0.75;          // Trem: G1 = price × 0.75 (sin tiers, sin piso)
const PRICE_THRESHOLD  = 0.005;         // 0.5%
const MAX_CHANGE_PCT_DEF = 30;          // tope superior default (configurable en form)
const IVA_ES           = 1.21;

/** Redondea un PVP NETO de modo que el precio CON IVA quede en múltiplo de 0.05. */
function roundToNickel($net) {
	$wi = ((float) $net) * IVA_ES;
	$r  = round($wi * 20) / 20;
	return round($r / IVA_ES, 4);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$max    = isset($_POST['max']) ? (int) $_POST['max'] : (isset($_GET['max']) ? (int) $_GET['max'] : 0);
$confirmExec = isset($_POST['confirm_execute']) || isset($_GET['confirm_execute']); $dryRun = !($action === 'execute' && $confirmExec); // fix footgun: el botón plan nunca ejecuta
$applyExtremes = isset($_POST['apply_extremes']) || isset($_GET['apply_extremes']);
$onlyExtremes = isset($_POST['only_extremes']) || isset($_GET['only_extremes']); // PLAN: mostrar SOLO los excluidos por extremos
$maxChangePct  = isset($_POST['max_change_pct']) ? (float) $_POST['max_change_pct'] : (isset($_GET['max_change_pct']) ? (float) $_GET['max_change_pct'] : MAX_CHANGE_PCT_DEF);
if ($maxChangePct < 0) $maxChangePct = 0;
$maxChangeRatio = $maxChangePct / 100.0;
$isAction = ($action === 'plan' || $action === 'execute');

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

/** G1 Trem = price × 0.75 (regla específica, sin tiers ni piso). */
function calcG1Price($price, $cost = 0) {
	return round((float) $price * G1_FACTOR, 4);
}

/**
 * Lee el xlsx Trem y devuelve mapa SKU (col C/ITEM) → ['cost', 'price'].
 * Reglas (idénticas al importador):
 *  - cost  = EXPORT price (col J, "export price" sin IVA wholesale)
 *  - price = roundToNickel(cost × 2)
 *  - skip filas con STATUS 'D'/'X'/'F' (descatalogadas) o NOT_SELL ≠ vacío
 *  - header en fila 4, datos desde fila 4 (ITEM en index 2 → col C; EXPORT en index 9 → col J)
 */
function loadTremPrices($path) {
	$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
	$reader->setReadDataOnly(true);
	$ss = $reader->load($path);
	$sh = $ss->getActiveSheet();
	$hRow = $sh->getHighestRow();
	$prices = [];
	for ($r = 4; $r <= $hRow; $r++) {
		$cells = $sh->rangeToArray("A$r:AD$r", null, true, false)[0];
		$item = trim((string) ($cells[2] ?? ''));
		if ($item === '') continue;
		$status   = strtoupper(trim((string) ($cells[4] ?? '')));
		$exportRaw = $cells[9] ?? '';
		$notSell  = trim((string) ($cells[17] ?? ''));
		if (in_array($status, ['D', 'X', 'F'], true)) continue;
		if ($notSell !== '') continue;
		$cost = is_numeric($exportRaw) ? (float) $exportRaw : (is_numeric(str_replace(',', '.', (string) $exportRaw)) ? (float) str_replace(',', '.', (string) $exportRaw) : null);
		if ($cost === null || $cost <= 0) continue;
		$cost = round($cost, 4);
		$price = roundToNickel($cost * 2.0);
		$prices[$item] = ['cost' => $cost, 'price' => $price];
	}
	return $prices;
}

if ($isAction) {
	@header('X-Accel-Buffering: no');
	@header('Content-Type: text/html; charset=utf-8');
	while (ob_get_level() > 0) @ob_end_flush();
	@ob_implicit_flush(true);
}
?>
<?php require THEME . 'html/header.php'; ?>
<table style="width:100%;"><tr><td><div style="padding:20px;">
<?php if ($isAction): ?>
	<h2>Actualizador precios Trem — <?php echo $dryRun ? 'PLAN (dry-run, sin cambios)' : 'EJECUCIÓN REAL'; ?></h2>
	<p><a href="<?php echo tep_href_link('Actualizador_precios_trem.php'); ?>" class="xbutton small hv9">← Volver</a></p>
	<div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n";
@flush();

logMsg("Modo: " . ($dryRun ? "PLAN (dry-run)" : "EXECUTE")
	. " | tope cambio=" . ($applyExtremes ? "OFF (aplicando también extremos)" : ("|Δ| ≤ " . rtrim(rtrim(number_format($maxChangePct, 1, '.', ''), '0'), '.') . "%"))
	. ($max > 0 ? " | max=$max cambios" : ""));

if (!file_exists(TREM_XLSX)) { logMsg("ERROR xlsx no encontrado: " . TREM_XLSX); goto end_action; }
logMsg("xlsx: " . basename(TREM_XLSX) . " (" . round(filesize(TREM_XLSX)/1024) . " KB, mtime " . date('Y-m-d H:i', filemtime(TREM_XLSX)) . ")");

logMsg("Cargando precios del xlsx (skip status D/X/F y NOT_SELL)…");
$xlsxPrices = loadTremPrices(TREM_XLSX);
logMsg("SKUs en xlsx con precio válido: " . count($xlsxPrices));

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_error) { logMsg("ERROR DB: " . $mysqli->connect_error); goto end_action; }
$mysqli->set_charset('utf8');

logMsg("Leyendo productos con products_import_origin LIKE 'trem%'…");
$prods = [];
$r = $mysqli->query("SELECT products_id, products_model, reference_prov, products_price, products_cost FROM products WHERE products_import_origin LIKE 'trem%'");
while ($row = $r->fetch_assoc()) $prods[(int) $row['products_id']] = $row;
logMsg("Productos Trem en BD: " . count($prods));
if (empty($prods)) { logMsg("Nada que hacer."); goto end_action; }

// G1 actual del padre
$ids = implode(',', array_keys($prods));
$g1Cur = [];
$r = $mysqli->query("SELECT products_id, customers_group_price FROM products_groups WHERE customers_group_id=" . G1_GROUP_ID . " AND products_id IN ($ids)");
while ($row = $r->fetch_assoc()) $g1Cur[(int) $row['products_id']] = (float) $row['customers_group_price'];

// Variantes (products_attributes) — Trem tiene muchos productos con variantes
$attrsByProd = [];
$r = $mysqli->query("SELECT products_attributes_id, products_id, options_values_id, reference, reference_prov, options_values_price, price_prefix FROM products_attributes WHERE products_id IN ($ids)");
while ($row = $r->fetch_assoc()) $attrsByProd[(int) $row['products_id']][] = $row;

// G1 actual de variantes
$paIds = [];
foreach ($attrsByProd as $arr) foreach ($arr as $a) $paIds[] = (int) $a['products_attributes_id'];
$g1AttrCur = [];
if (!empty($paIds)) {
	$paIn = implode(',', $paIds);
	$r = $mysqli->query("SELECT products_attributes_id, options_values_price, price_prefix FROM products_attributes_groups WHERE customers_group_id=" . G1_GROUP_ID . " AND products_attributes_id IN ($paIn)");
	while ($row = $r->fetch_assoc()) $g1AttrCur[(int) $row['products_attributes_id']] = $row;
}
logMsg("  → con variantes: " . count(array_filter($attrsByProd, fn($a) => !empty($a))) . " productos / " . count($paIds) . " atributos");

// Plan
$updPriceMain = []; $updCostMain = []; $updG1Main = []; $insG1Main = [];
$updAttrPrice = []; $updAttrG1 = []; $insAttrG1 = [];
$extremesProds = [];
$noMatch = [];
$processed = 0;

foreach ($prods as $pid => $p) {
	$variants = $attrsByProd[$pid] ?? [];

	if (empty($variants)) {
		// ── Producto SUELTO ──
		$candidates = array_unique(array_filter([$p['products_model'], $p['reference_prov']]));
		$entry = null;
		foreach ($candidates as $c) {
			if (isset($xlsxPrices[$c])) { $entry = $xlsxPrices[$c]; break; }
		}
		if ($entry === null) { $noMatch[] = ['pid'=>$pid, 'ref'=>$p['products_model']]; continue; }

		$processed++;
		$newCost  = $entry['cost'];
		$newPrice = $entry['price'];
		$newG1    = roundToNickel(calcG1Price($newPrice));
		$curPrice = (float) $p['products_price']; $curCost = (float) $p['products_cost'];

		$dP = priceDeltaPct($curPrice, $newPrice);
		$dC = priceDeltaPct($curCost,  $newCost);
		if (!$applyExtremes && $maxChangeRatio > 0 && (max($dP, $dC) > $maxChangeRatio)) {
			$extremesProds[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'why'=>'suelto', 'oldP'=>$curPrice, 'newP'=>$newPrice, 'oldC'=>$curCost, 'newC'=>$newCost];
			continue;
		}

		if ($dP > PRICE_THRESHOLD) $updPriceMain[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'old'=>$curPrice, 'new'=>$newPrice];
		if ($dC > PRICE_THRESHOLD) $updCostMain[]  = ['pid'=>$pid, 'ref'=>$p['products_model'], 'old'=>$curCost,  'new'=>$newCost];
		if (isset($g1Cur[$pid])) {
			if (priceDeltaPct($g1Cur[$pid], $newG1) > PRICE_THRESHOLD) $updG1Main[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'old'=>$g1Cur[$pid], 'new'=>$newG1];
		} else {
			$insG1Main[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'new'=>$newG1];
		}
		continue;
	}

	// ── Producto CON VARIANTES ──
	$variantPrices = [];
	$missing = [];
	foreach ($variants as $a) {
		$candidates = array_unique(array_filter([$a['reference'], $a['reference_prov']]));
		$entry = null;
		foreach ($candidates as $c) {
			if (isset($xlsxPrices[$c])) { $entry = $xlsxPrices[$c]; break; }
		}
		if ($entry === null) $missing[] = $a['reference'];
		else $variantPrices[(int) $a['products_attributes_id']] = [
			'cost'  => $entry['cost'],
			'price' => $entry['price'],
			'g1'    => roundToNickel(calcG1Price($entry['price'])),
		];
	}
	if (!empty($missing)) {
		$noMatch[] = ['pid'=>$pid, 'ref'=>$p['products_model'] . ' (variante ' . implode(',', $missing) . ' sin match)'];
		continue;
	}
	$processed++;

	$cheapestPa = null; $cheapestPrice = PHP_FLOAT_MAX;
	foreach ($variantPrices as $paId => $vp) {
		if ($vp['price'] < $cheapestPrice) { $cheapestPrice = $vp['price']; $cheapestPa = $paId; }
	}
	$mainNew = $variantPrices[$cheapestPa];
	$newCost = $mainNew['cost']; $newPrice = $mainNew['price']; $newG1Main = $mainNew['g1'];
	$curPrice = (float) $p['products_price']; $curCost = (float) $p['products_cost'];

	$dP = priceDeltaPct($curPrice, $newPrice);
	$dC = priceDeltaPct($curCost,  $newCost);
	if (!$applyExtremes && $maxChangeRatio > 0 && (max($dP, $dC) > $maxChangeRatio)) {
		$extremesProds[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'why'=>'con variantes ('.count($variants).')', 'oldP'=>$curPrice, 'newP'=>$newPrice, 'oldC'=>$curCost, 'newC'=>$newCost];
		continue;
	}

	if ($dP > PRICE_THRESHOLD) $updPriceMain[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'old'=>$curPrice, 'new'=>$newPrice];
	if ($dC > PRICE_THRESHOLD) $updCostMain[]  = ['pid'=>$pid, 'ref'=>$p['products_model'], 'old'=>$curCost,  'new'=>$newCost];
	if (isset($g1Cur[$pid])) {
		if (priceDeltaPct($g1Cur[$pid], $newG1Main) > PRICE_THRESHOLD) $updG1Main[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'old'=>$g1Cur[$pid], 'new'=>$newG1Main];
	} else {
		$insG1Main[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'new'=>$newG1Main];
	}

	foreach ($variants as $a) {
		$paId = (int) $a['products_attributes_id'];
		if (!isset($variantPrices[$paId])) continue;
		$vp = $variantPrices[$paId];

		$delta = round($vp['price'] - $newPrice, 4);
		$prefix = $delta < 0 ? '-' : '+';
		$absDelta = abs($delta);
		$curAbs = (float) $a['options_values_price'];
		$curPref = $a['price_prefix'] ?: '+';
		$signedNew = ($prefix === '-' ? -$absDelta : $absDelta);
		$signedCur = ($curPref === '-' ? -$curAbs : $curAbs);
		if (priceDeltaPct($signedCur, $signedNew) > PRICE_THRESHOLD || ($absDelta > 0 && $curAbs == 0) || ($curPref !== $prefix && $absDelta > 0.0001)) {
			$updAttrPrice[] = ['paid'=>$paId, 'pid'=>$pid, 'ref'=>$a['reference'], 'absOld'=>$curAbs, 'prefOld'=>$curPref, 'absNew'=>$absDelta, 'prefNew'=>$prefix];
		}

		$g1Delta = round($vp['g1'] - $newG1Main, 4);
		$g1Prefix = $g1Delta < 0 ? '-' : '+';
		$g1Abs = abs($g1Delta);
		if (isset($g1AttrCur[$paId])) {
			$curG1Abs = (float) $g1AttrCur[$paId]['options_values_price'];
			$curG1Pref = $g1AttrCur[$paId]['price_prefix'] ?: '+';
			$signedNewG1 = ($g1Prefix === '-' ? -$g1Abs : $g1Abs);
			$signedCurG1 = ($curG1Pref === '-' ? -$curG1Abs : $curG1Abs);
			if (priceDeltaPct($signedCurG1, $signedNewG1) > PRICE_THRESHOLD || ($g1Abs > 0 && $curG1Abs == 0) || ($curG1Pref !== $g1Prefix && $g1Abs > 0.0001)) {
				$updAttrG1[] = ['paid'=>$paId, 'pid'=>$pid, 'absNew'=>$g1Abs, 'prefNew'=>$g1Prefix];
			}
		} else {
			$insAttrG1[] = ['paid'=>$paId, 'pid'=>$pid, 'absNew'=>$g1Abs, 'prefNew'=>$g1Prefix];
		}
	}
}

logMsg("==================== PLAN ====================");
logMsg("Procesados: $processed");
logMsg("UPDATE products.products_price : " . count($updPriceMain));
logMsg("UPDATE products.products_cost  : " . count($updCostMain));
logMsg("UPDATE products_groups (G1)    : " . count($updG1Main));
logMsg("INSERT products_groups (G1)    : " . count($insG1Main));
logMsg("UPDATE products_attributes (variantes price) : " . count($updAttrPrice));
logMsg("UPDATE products_attributes_groups (G1 var)   : " . count($updAttrG1));
logMsg("INSERT products_attributes_groups (G1 var)   : " . count($insAttrG1));
if (!$applyExtremes && $maxChangeRatio > 0) {
	logMsg("⚠️  Productos extremos > {$maxChangePct}% EXCLUIDOS (revisar): " . count($extremesProds) . " (padre + sus variantes no se tocan)");
}
logMsg("Sin match en xlsx                : " . count($noMatch));

$showLimit = 25; if (!empty($onlyExtremes)) $showLimit = 1000000;
foreach ([
	['UPDATE price principal', $updPriceMain],
	['UPDATE cost principal',  $updCostMain],
	['INSERT G1 principal',    $insG1Main],
	['UPDATE G1 principal',    $updG1Main],
] as [$title, $arr]) {
	if (empty($arr)) continue;
	logMsg("--- $title (top $showLimit) ---");
	foreach (array_slice($arr, 0, $showLimit) as $u) {
		if (isset($u['old'])) {
			$pct = priceDeltaPct($u['old'], $u['new']) * 100;
			logMsg(sprintf("  pid=%d ref=%s : %.4f → %.4f (%s%.1f%%)", $u['pid'], $u['ref'], $u['old'], $u['new'], $u['new']>=$u['old']?'+':'-', $pct));
		} else {
			logMsg(sprintf("  pid=%d ref=%s : (sin G1) → %.4f", $u['pid'], $u['ref'], $u['new']));
		}
	}
	if (count($arr) > $showLimit) logMsg("  …y " . (count($arr) - $showLimit) . " más");
}
if (!empty($updAttrPrice)) {
	logMsg("--- UPDATE variantes price (top $showLimit) ---");
	foreach (array_slice($updAttrPrice, 0, $showLimit) as $u) {
		logMsg(sprintf("  paid=%d pid=%d ref=%s : %s%.4f → %s%.4f", $u['paid'], $u['pid'], $u['ref'], $u['prefOld'], $u['absOld'], $u['prefNew'], $u['absNew']));
	}
	if (count($updAttrPrice) > $showLimit) logMsg("  …y " . (count($updAttrPrice) - $showLimit) . " más");
}
if (!empty($extremesProds)) {
	logMsg("--- ⚠️ EXTREMOS productos completos (excluidos, top $showLimit) — probablemente pack-vs-unidad o error de feed ---");
	foreach (array_slice($extremesProds, 0, $showLimit) as $u) {
		$pctP = priceDeltaPct($u['oldP'], $u['newP']) * 100;
		$pctC = priceDeltaPct($u['oldC'], $u['newC']) * 100;
		logMsg(sprintf("  pid=%d ref=%s (%s) : price %.4f→%.4f (%.1f%%) cost %.4f→%.4f (%.1f%%)",
			$u['pid'], $u['ref'], $u['why'], $u['oldP'], $u['newP'], $pctP, $u['oldC'], $u['newC'], $pctC));
	}
	if (count($extremesProds) > $showLimit) logMsg("  …y " . (count($extremesProds) - $showLimit) . " más");
}
if (!empty($noMatch)) {
	logMsg("--- Sin match (top $showLimit, no se tocan) ---");
	foreach (array_slice($noMatch, 0, $showLimit) as $u) logMsg(sprintf("  pid=%d ref=%s", $u['pid'], $u['ref']));
	if (count($noMatch) > $showLimit) logMsg("  …y " . (count($noMatch) - $showLimit) . " más");
}

if ($dryRun) { logMsg("=== Dry-run finalizado. No se ha tocado nada. ==="); goto end_action; }

logMsg("Aplicando cambios en transacción única…");
$mysqli->begin_transaction();
try {
	foreach ($updPriceMain as $u) {
		$ok = $mysqli->query("UPDATE products SET products_price=" . fmt4($u['new']) . ", products_last_modified=NOW() WHERE products_id=" . (int) $u['pid']);
		if (!$ok) throw new Exception("UPDATE price pid=" . $u['pid'] . ": " . $mysqli->error);
	}
	foreach ($updCostMain as $u) {
		$ok = $mysqli->query("UPDATE products SET products_cost=" . fmt4($u['new']) . ", products_last_modified=NOW() WHERE products_id=" . (int) $u['pid']);
		if (!$ok) throw new Exception("UPDATE cost pid=" . $u['pid'] . ": " . $mysqli->error);
	}
	foreach ($updG1Main as $u) {
		$ok = $mysqli->query("UPDATE products_groups SET customers_group_price=" . fmt4($u['new']) . " WHERE products_id=" . (int) $u['pid'] . " AND customers_group_id=" . G1_GROUP_ID);
		if (!$ok) throw new Exception("UPDATE g1 pid=" . $u['pid'] . ": " . $mysqli->error);
	}
	foreach ($insG1Main as $u) {
		$ok = $mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . G1_GROUP_ID . ", " . (int) $u['pid'] . ", " . fmt4($u['new']) . ", 1, 1)");
		if (!$ok) throw new Exception("INSERT g1 pid=" . $u['pid'] . ": " . $mysqli->error);
	}
	foreach ($updAttrPrice as $u) {
		$ok = $mysqli->query("UPDATE products_attributes SET options_values_price=" . fmt4($u['absNew']) . ", price_prefix='" . $u['prefNew'] . "' WHERE products_attributes_id=" . (int) $u['paid']);
		if (!$ok) throw new Exception("UPDATE attr paid=" . $u['paid'] . ": " . $mysqli->error);
	}
	foreach ($updAttrG1 as $u) {
		$ok = $mysqli->query("UPDATE products_attributes_groups SET options_values_price=" . fmt4($u['absNew']) . ", price_prefix='" . $u['prefNew'] . "' WHERE products_attributes_id=" . (int) $u['paid'] . " AND customers_group_id=" . G1_GROUP_ID);
		if (!$ok) throw new Exception("UPDATE attr g1 paid=" . $u['paid'] . ": " . $mysqli->error);
	}
	foreach ($insAttrG1 as $u) {
		$ok = $mysqli->query("INSERT INTO products_attributes_groups (products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id, options_values_weight, weight_prefix) VALUES (" . (int) $u['paid'] . ", " . G1_GROUP_ID . ", " . fmt4($u['absNew']) . ", '" . $u['prefNew'] . "', " . (int) $u['pid'] . ", 0, '+')");
		if (!$ok) throw new Exception("INSERT attr g1 paid=" . $u['paid'] . ": " . $mysqli->error);
	}
	$mysqli->commit();
	logMsg("=== COMMIT OK ===");
} catch (Exception $e) {
	$mysqli->rollback();
	logMsg("=== ROLLBACK por error: " . $e->getMessage() . " ===");
}

end_action:
?>
	</div>
	<p style="margin-top:15px;"><a href="<?php echo tep_href_link('Actualizador_precios_trem.php'); ?>" class="xbutton small hv9">← Volver</a></p>
<?php else: ?>
	<h2>Actualizador de precios — Trem</h2>
	<?php
		$xlsxOk = file_exists(TREM_XLSX);
		if (!$xlsxOk) {
			echo '<p style="color:red;">No se encuentra xlsx en ' . TREM_XLSX . '</p>';
		} else {
			echo '<p style="color:#666;font-size:13px;">xlsx: <code>' . htmlspecialchars(basename(TREM_XLSX)) . '</code> (' . round(filesize(TREM_XLSX)/1024) . ' KB, mtime ' . date('Y-m-d H:i', filemtime(TREM_XLSX)) . ')</p>';
		}
	?>
	<p>
		Compara <code>EXPORT price</code> del xlsx Trem (sin IVA, wholesale) con
		<code>products.products_cost</code>, <code>products_price</code> y <code>products_groups</code> (Grupo 1)
		para los productos con <code>products_import_origin LIKE 'trem%'</code>.
		Maneja productos con variantes: la variante más barata define el precio del padre y el resto guardan el delta.
		Aplica cambios solo cuando la diferencia &gt; <strong><?php echo PRICE_THRESHOLD * 100; ?>%</strong>.
	</p>
	<form method="post" style="background:#f5f5f5;padding:15px;border-radius:5px;">
		<p>
			<strong>Tope de variación</strong>:
			<label>excluir cambios &gt;
				<input type="number" name="max_change_pct" value="<?php echo (int) MAX_CHANGE_PCT_DEF; ?>" min="0" max="500" step="1" style="width:60px;">
				%
			</label>
			<small style="color:#888;display:block;margin-top:4px;">Si el price o cost del producto (padre, en caso de variantes) cambia más de este porcentaje, el producto ENTERO se excluye. 0 = sin tope. Protege contra pack-vs-unidad o errores de feed.</small>
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
		<strong>Reglas aplicadas</strong> (idénticas al importador Trem):<br>
		- <code>products_cost</code>  ← EXPORT price del xlsx (col J, sin IVA).<br>
		- <code>products_price</code> ← <code>roundToNickel(cost × 2)</code> (margen 100% fijo).<br>
		- <strong>Grupo 1</strong>: <code>roundToNickel(price × <?php echo G1_FACTOR; ?>)</code> (regla Trem; sin tiers ni piso, diferente de FNI/Cressi/Lankhorst).<br>
		- Skip xlsx filas con status D/X/F o NOT_SELL ≠ vacío. Variantes: padre = variante más barata, resto con delta.<br>
		- Stock <strong>NO se toca</strong>. Productos sin match en xlsx se listan informativamente.
	</p>
<?php endif; ?>
</div></td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
