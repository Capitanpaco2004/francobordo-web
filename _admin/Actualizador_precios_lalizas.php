<?php
require 'includes/application_top.php';
require_once dirname(dirname(__FILE__)) . '/includes/vendor/autoload.php';

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);

const LALIZAS_DIR      = '/home/francobordo/public_html/import/Lalizas/';
const G1_GROUP_ID      = 1;
const G1_FLOOR_FACTOR  = 1.10;          // piso: G1 ≥ cost × 1.10
const PRICE_THRESHOLD  = 0.005;         // 0.5%
const MAX_CHANGE_PCT_DEF = 30;          // tope superior default (configurable en form)
const IVA_ES           = 1.21;
// Productos Lalizas en BD: importados (origin lalizas%) o legacy bajo estos manufacturers.
const SCOPE_SQL        = "(p.products_import_origin LIKE 'lalizas%' OR p.manufacturers_id IN (3,16,96,246,376))";

function roundToNickel($net) { $wi = ((float) $net) * IVA_ES; $r = round($wi * 20) / 20; return round($r / IVA_ES, 4); }

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$max    = isset($_POST['max']) ? (int) $_POST['max'] : (isset($_GET['max']) ? (int) $_GET['max'] : 0);
// Ejecuta SOLO con el botón "Ejecutar" (action=execute) Y la casilla marcada. El botón de plan siempre es dry-run.
$confirmExec = isset($_POST['confirm_execute']) || isset($_GET['confirm_execute']);
$dryRun = !($action === 'execute' && $confirmExec);
$applyExtremes = isset($_POST['apply_extremes']) || isset($_GET['apply_extremes']);
$onlyExtremes  = isset($_POST['only_extremes']) || isset($_GET['only_extremes']);  // PLAN: mostrar SOLO los excluidos por extremos
$maxChangePct  = isset($_POST['max_change_pct']) ? (float) $_POST['max_change_pct'] : (isset($_GET['max_change_pct']) ? (float) $_GET['max_change_pct'] : MAX_CHANGE_PCT_DEF);
if ($maxChangePct < 0) $maxChangePct = 0;
$maxChangeRatio = $maxChangePct / 100.0;
$isAction = ($action === 'plan' || $action === 'execute');

function logMsg($msg) {
	$line = '[' . date('H:i:s') . '] ' . $msg . "\n";
	echo '<pre style="margin:0;padding:2px 8px;border-bottom:1px solid #eee;white-space:pre-wrap;overflow-wrap:break-word;word-break:break-word;font-family:monospace;font-size:12px;">' . htmlspecialchars($line) . '</pre>';
	@flush();
}
function fmt4($n) { return number_format((float) $n, 4, '.', ''); }
function priceDeltaPct($oldP, $newP) { $ref = max(abs((float) $oldP), 0.01); return abs((float) $newP - (float) $oldP) / $ref; }
function lalParseNum($v) { $v = trim((string) $v); if ($v === '') return null; $v = str_replace(',', '.', $v); return is_numeric($v) ? (float) $v : null; }

/** G1 con tiers según margen real + piso cost × G1_FLOOR_FACTOR (idéntico al importador). */
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
	return round(max($price * $mult, $cost * G1_FLOOR_FACTOR), 4);
}

function findNewestXlsx($dir) {
	if (!is_dir($dir)) return null; $best = null; $bestT = 0;
	foreach (scandir($dir) as $f) { if (substr($f, -5) !== '.xlsx' || substr($f, 0, 1) === '~') continue; $t = filemtime($dir . $f); if ($t > $bestT) { $bestT = $t; $best = $dir . $f; } }
	return $best;
}

/**
 * Lee la hoja "precios" del xlsx y devuelve [byRef, byEan] → ['cost','price'].
 *  - cost  = col F (precio neto sin IVA)
 *  - price = roundToNickel(col G, PVP sin IVA); fallback cost × 2 si G vacío o < cost
 */
function loadLalizasPrices($path) {
	$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
	$reader->setReadDataOnly(true);
	$sheet = $reader->load($path)->getSheetByName('precios');
	if (!$sheet) return [[], []];
	$hi = $sheet->getHighestRow();
	$byRef = []; $byEan = [];
	for ($r = 2; $r <= $hi; $r++) {
		$ref  = trim((string) $sheet->getCell('A' . $r)->getValue());
		$ean  = trim((string) $sheet->getCell('D' . $r)->getValue());
		$cost = lalParseNum($sheet->getCell('F' . $r)->getValue());
		$pvp  = lalParseNum($sheet->getCell('G' . $r)->getValue());
		if ($cost === null || $cost < 0) $cost = 0.0;
		if ($pvp === null || $pvp <= 0) { if ($cost > 0) $pvp = $cost * 2.0; else continue; }
		if ($cost > 0 && $pvp < $cost) $pvp = $cost * 2.0;
		$entry = ['cost' => round($cost, 4), 'price' => roundToNickel($pvp)];
		if ($ref !== '') $byRef[strtolower($ref)] = $entry;
		if ($ean !== '') $byEan[$ean] = $entry;
	}
	return [$byRef, $byEan];
}

/** Busca entrada por EAN (preferente) y luego por Ref/modelo. */
function matchEntry($byRef, $byEan, array $eanCands, array $refCands) {
	foreach ($eanCands as $e) { $e = trim((string) $e); if ($e !== '' && isset($byEan[$e])) return $byEan[$e]; }
	foreach ($refCands as $c) { $c = strtolower(trim((string) $c)); if ($c !== '' && isset($byRef[$c])) return $byRef[$c]; }
	return null;
}

if ($isAction) {
	@header('X-Accel-Buffering: no');
	@header('Content-Type: text/html; charset=utf-8');
	while (ob_get_level() > 0) @ob_end_flush();
	@ob_implicit_flush(true);
	if (session_status() === PHP_SESSION_ACTIVE) @session_write_close();
}
?>
<?php require THEME . 'html/header.php'; ?>
<table style="width:100%;"><tr><td><div style="padding:20px;">
<?php if ($isAction): ?>
	<h2>Actualizador precios Lalizas — <?php echo $dryRun ? 'PLAN (dry-run, sin cambios)' : 'EJECUCIÓN REAL'; ?></h2>
	<p><a href="<?php echo tep_href_link('Actualizador_precios_lalizas.php'); ?>" class="xbutton small hv9">← Volver</a></p>
	<div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n";
@flush();

logMsg("Modo: " . ($dryRun ? "PLAN (dry-run)" : "EXECUTE")
	. " | tope cambio=" . ($applyExtremes ? "OFF (aplicando también extremos)" : ("|Δ| ≤ " . rtrim(rtrim(number_format($maxChangePct, 1, '.', ''), '0'), '.') . "%"))
	. ($max > 0 ? " | max=$max cambios" : ""));

$xlsx = findNewestXlsx(LALIZAS_DIR);
if (!$xlsx) { logMsg("ERROR: no hay xlsx en " . LALIZAS_DIR); goto end_action; }
logMsg("xlsx: " . basename($xlsx) . " (" . round(filesize($xlsx)/1024) . " KB, mtime " . date('Y-m-d H:i', filemtime($xlsx)) . ")");

logMsg("Cargando precios del xlsx (hoja precios: F=coste, G=PVP)…");
list($byRef, $byEan) = loadLalizasPrices($xlsx);
logMsg("Entradas con precio: " . count($byRef) . " por Ref / " . count($byEan) . " por EAN");

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_error) { logMsg("ERROR DB: " . $mysqli->connect_error); goto end_action; }
$mysqli->set_charset('utf8');

logMsg("Leyendo productos Lalizas en BD…");
$prods = [];
$r = $mysqli->query("SELECT p.products_id, p.products_model, p.reference_prov, p.product_ean, p.products_price, p.products_cost FROM products p WHERE " . SCOPE_SQL);
while ($row = $r->fetch_assoc()) $prods[(int) $row['products_id']] = $row;
logMsg("Productos Lalizas en BD: " . count($prods));
if (empty($prods)) { logMsg("Nada que hacer."); goto end_action; }

$ids = implode(',', array_keys($prods));
// Nombre del producto (ES, lang 3) para mostrar en el plan.
$names = [];
$r = $mysqli->query("SELECT products_id, products_name FROM products_description WHERE language_id=3 AND products_id IN ($ids)");
while ($row = $r->fetch_assoc()) $names[(int) $row['products_id']] = $row['products_name'];
$nm = function ($pid) use (&$names) { return mb_substr((string)($names[$pid] ?? ''), 0, 45, 'UTF-8'); };
$g1Cur = [];
$r = $mysqli->query("SELECT products_id, customers_group_price FROM products_groups WHERE customers_group_id=" . G1_GROUP_ID . " AND products_id IN ($ids)");
while ($row = $r->fetch_assoc()) $g1Cur[(int) $row['products_id']] = (float) $row['customers_group_price'];

$attrsByProd = [];
$r = $mysqli->query("SELECT products_attributes_id, products_id, options_values_id, reference, reference_prov, products_attributes_ean, options_values_price, price_prefix FROM products_attributes WHERE products_id IN ($ids)");
while ($row = $r->fetch_assoc()) $attrsByProd[(int) $row['products_id']][] = $row;

$paIds = [];
foreach ($attrsByProd as $arr) foreach ($arr as $a) $paIds[] = (int) $a['products_attributes_id'];
$g1AttrCur = [];
if (!empty($paIds)) {
	$paIn = implode(',', $paIds);
	$r = $mysqli->query("SELECT products_attributes_id, options_values_price, price_prefix FROM products_attributes_groups WHERE customers_group_id=" . G1_GROUP_ID . " AND products_attributes_id IN ($paIn)");
	while ($row = $r->fetch_assoc()) $g1AttrCur[(int) $row['products_attributes_id']] = $row;
}
logMsg("  → con variantes: " . count(array_filter($attrsByProd, fn($a) => !empty($a))) . " productos / " . count($paIds) . " atributos");

$updPriceMain = []; $updCostMain = []; $updG1Main = []; $insG1Main = [];
$updAttrPrice = []; $updAttrG1 = []; $insAttrG1 = [];
$extremesProds = []; $noMatch = []; $processed = 0;

foreach ($prods as $pid => $p) {
	$variants = $attrsByProd[$pid] ?? [];

	if (empty($variants)) {
		// ── Producto SUELTO ──
		$entry = matchEntry($byRef, $byEan, [$p['product_ean']], [$p['products_model'], $p['reference_prov']]);
		if ($entry === null) { $noMatch[] = ['pid'=>$pid, 'ref'=>$p['products_model']]; continue; }
		$processed++;
		$newCost = $entry['cost']; $newPrice = $entry['price'];
		$newG1   = roundToNickel(calcG1Price($newPrice, $newCost));
		$curPrice = (float) $p['products_price']; $curCost = (float) $p['products_cost'];
		$dP = priceDeltaPct($curPrice, $newPrice); $dC = priceDeltaPct($curCost, $newCost);
		if (!$applyExtremes && $maxChangeRatio > 0 && (max($dP, $dC) > $maxChangeRatio)) {
			$extremesProds[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'why'=>'suelto', 'oldP'=>$curPrice, 'newP'=>$newPrice, 'oldC'=>$curCost, 'newC'=>$newCost]; continue;
		}
		if ($dP > PRICE_THRESHOLD) $updPriceMain[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'old'=>$curPrice, 'new'=>$newPrice];
		if ($dC > PRICE_THRESHOLD) $updCostMain[]  = ['pid'=>$pid, 'ref'=>$p['products_model'], 'old'=>$curCost,  'new'=>$newCost];
		if (isset($g1Cur[$pid])) { if (priceDeltaPct($g1Cur[$pid], $newG1) > PRICE_THRESHOLD) $updG1Main[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'old'=>$g1Cur[$pid], 'new'=>$newG1]; }
		else $insG1Main[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'new'=>$newG1];
		continue;
	}

	// ── Producto CON VARIANTES ──
	$variantPrices = []; $missing = [];
	foreach ($variants as $a) {
		$entry = matchEntry($byRef, $byEan, [$a['products_attributes_ean']], [$a['reference'], $a['reference_prov']]);
		if ($entry === null) $missing[] = ($a['reference'] ?: $a['products_attributes_ean']);
		else $variantPrices[(int) $a['products_attributes_id']] = ['cost'=>$entry['cost'], 'price'=>$entry['price'], 'g1'=>roundToNickel(calcG1Price($entry['price'], $entry['cost']))];
	}
	if (!empty($missing)) { $noMatch[] = ['pid'=>$pid, 'ref'=>$p['products_model'] . ' (variante ' . implode(',', array_slice($missing,0,3)) . ' sin match)']; continue; }
	$processed++;

	$cheapestPa = null; $cheapestPrice = PHP_FLOAT_MAX;
	foreach ($variantPrices as $paId => $vp) { if ($vp['price'] < $cheapestPrice) { $cheapestPrice = $vp['price']; $cheapestPa = $paId; } }
	$mainNew = $variantPrices[$cheapestPa];
	$newCost = $mainNew['cost']; $newPrice = $mainNew['price']; $newG1Main = $mainNew['g1'];
	$curPrice = (float) $p['products_price']; $curCost = (float) $p['products_cost'];
	$dP = priceDeltaPct($curPrice, $newPrice); $dC = priceDeltaPct($curCost, $newCost);
	if (!$applyExtremes && $maxChangeRatio > 0 && (max($dP, $dC) > $maxChangeRatio)) {
		$extremesProds[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'why'=>'con variantes ('.count($variants).')', 'oldP'=>$curPrice, 'newP'=>$newPrice, 'oldC'=>$curCost, 'newC'=>$newCost]; continue;
	}
	if ($dP > PRICE_THRESHOLD) $updPriceMain[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'old'=>$curPrice, 'new'=>$newPrice];
	if ($dC > PRICE_THRESHOLD) $updCostMain[]  = ['pid'=>$pid, 'ref'=>$p['products_model'], 'old'=>$curCost,  'new'=>$newCost];
	if (isset($g1Cur[$pid])) { if (priceDeltaPct($g1Cur[$pid], $newG1Main) > PRICE_THRESHOLD) $updG1Main[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'old'=>$g1Cur[$pid], 'new'=>$newG1Main]; }
	else $insG1Main[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'new'=>$newG1Main];

	foreach ($variants as $a) {
		$paId = (int) $a['products_attributes_id'];
		if (!isset($variantPrices[$paId])) continue;
		$vp = $variantPrices[$paId];
		$delta = round($vp['price'] - $newPrice, 4); $prefix = $delta < 0 ? '-' : '+'; $absDelta = abs($delta);
		$curAbs = (float) $a['options_values_price']; $curPref = $a['price_prefix'] ?: '+';
		$signedNew = ($prefix === '-' ? -$absDelta : $absDelta); $signedCur = ($curPref === '-' ? -$curAbs : $curAbs);
		if (priceDeltaPct($signedCur, $signedNew) > PRICE_THRESHOLD || ($absDelta > 0 && $curAbs == 0) || ($curPref !== $prefix && $absDelta > 0.0001))
			$updAttrPrice[] = ['paid'=>$paId, 'pid'=>$pid, 'ref'=>$a['reference'], 'absOld'=>$curAbs, 'prefOld'=>$curPref, 'absNew'=>$absDelta, 'prefNew'=>$prefix];
		$g1Delta = round($vp['g1'] - $newG1Main, 4); $g1Prefix = $g1Delta < 0 ? '-' : '+'; $g1Abs = abs($g1Delta);
		if (isset($g1AttrCur[$paId])) {
			$curG1Abs = (float) $g1AttrCur[$paId]['options_values_price']; $curG1Pref = $g1AttrCur[$paId]['price_prefix'] ?: '+';
			$signedNewG1 = ($g1Prefix === '-' ? -$g1Abs : $g1Abs); $signedCurG1 = ($curG1Pref === '-' ? -$curG1Abs : $curG1Abs);
			if (priceDeltaPct($signedCurG1, $signedNewG1) > PRICE_THRESHOLD || ($g1Abs > 0 && $curG1Abs == 0) || ($curG1Pref !== $g1Prefix && $g1Abs > 0.0001))
				$updAttrG1[] = ['paid'=>$paId, 'pid'=>$pid, 'absNew'=>$g1Abs, 'prefNew'=>$g1Prefix];
		} else $insAttrG1[] = ['paid'=>$paId, 'pid'=>$pid, 'absNew'=>$g1Abs, 'prefNew'=>$g1Prefix];
	}
}

logMsg("==================== PLAN ====================");
logMsg("Procesados: $processed");
logMsg("UPDATE products.products_price : " . count($updPriceMain));
logMsg("UPDATE products.products_cost  : " . count($updCostMain));
logMsg("UPDATE products_groups (G1)    : " . count($updG1Main) . " | INSERT G1: " . count($insG1Main));
logMsg("UPDATE variantes price : " . count($updAttrPrice) . " | UPDATE G1 var: " . count($updAttrG1) . " | INSERT G1 var: " . count($insAttrG1));
if (!$applyExtremes && $maxChangeRatio > 0) logMsg("⚠️  Productos extremos > {$maxChangePct}% EXCLUIDOS: " . count($extremesProds) . " (padre + variantes no se tocan)");
logMsg("Sin match en xlsx : " . count($noMatch));

$showLimit = 25;
if ($onlyExtremes) logMsg("** Modo SOLO EXTREMOS: se omiten las listas de cambios y de sin-match **");
if (!$onlyExtremes) {
	foreach ([['UPDATE price principal', $updPriceMain], ['UPDATE cost principal', $updCostMain], ['INSERT G1 principal', $insG1Main], ['UPDATE G1 principal', $updG1Main]] as [$title, $arr]) {
		if (empty($arr)) continue;
		logMsg("--- $title (top $showLimit) ---");
		foreach (array_slice($arr, 0, $showLimit) as $u) {
			if (isset($u['old'])) { $pct = priceDeltaPct($u['old'], $u['new']) * 100; logMsg(sprintf("  pid=%d ref=%s [%s] : %.4f → %.4f (%s%.1f%%)", $u['pid'], $u['ref'], $nm($u['pid']), $u['old'], $u['new'], $u['new']>=$u['old']?'+':'-', $pct)); }
			else logMsg(sprintf("  pid=%d ref=%s [%s] : (sin G1) → %.4f", $u['pid'], $u['ref'], $nm($u['pid']), $u['new']));
		}
		if (count($arr) > $showLimit) logMsg("  …y " . (count($arr) - $showLimit) . " más");
	}
}
if (!empty($extremesProds)) {
	logMsg("--- ⚠️ EXTREMOS excluidos (TODOS: " . count($extremesProds) . ", >{$maxChangePct}%, NO se tocan) — posible pack-vs-unidad o error ---");
	foreach ($extremesProds as $u) { $pctP = priceDeltaPct($u['oldP'], $u['newP']) * 100; $pctC = priceDeltaPct($u['oldC'], $u['newC']) * 100; logMsg(sprintf("  pid=%d ref=%s [%s] (%s): price %.4f→%.4f (%.1f%%) cost %.4f→%.4f (%.1f%%)", $u['pid'], $u['ref'], $nm($u['pid']), $u['why'], $u['oldP'], $u['newP'], $pctP, $u['oldC'], $u['newC'], $pctC)); }
}
if (!$onlyExtremes && !empty($noMatch)) {
	logMsg("--- Sin match en xlsx (TODOS: " . count($noMatch) . ", no se tocan) ---");
	foreach ($noMatch as $u) logMsg(sprintf("  pid=%d ref=%s [%s]", $u['pid'], $u['ref'], $nm($u['pid'])));
}

if ($dryRun) { logMsg("=== Dry-run finalizado. No se ha tocado nada. ==="); goto end_action; }

logMsg("Aplicando cambios en transacción única…");
$mysqli->begin_transaction();
try {
	foreach ($updPriceMain as $u) { if (!$mysqli->query("UPDATE products SET products_price=" . fmt4($u['new']) . ", products_last_modified=NOW() WHERE products_id=" . (int) $u['pid'])) throw new Exception("price pid=" . $u['pid'] . ": " . $mysqli->error); }
	foreach ($updCostMain as $u) { if (!$mysqli->query("UPDATE products SET products_cost=" . fmt4($u['new']) . ", products_last_modified=NOW() WHERE products_id=" . (int) $u['pid'])) throw new Exception("cost pid=" . $u['pid'] . ": " . $mysqli->error); }
	foreach ($updG1Main as $u) { if (!$mysqli->query("UPDATE products_groups SET customers_group_price=" . fmt4($u['new']) . " WHERE products_id=" . (int) $u['pid'] . " AND customers_group_id=" . G1_GROUP_ID)) throw new Exception("g1 pid=" . $u['pid'] . ": " . $mysqli->error); }
	foreach ($insG1Main as $u) { if (!$mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . G1_GROUP_ID . ", " . (int) $u['pid'] . ", " . fmt4($u['new']) . ", 1, 1)")) throw new Exception("ins g1 pid=" . $u['pid'] . ": " . $mysqli->error); }
	foreach ($updAttrPrice as $u) { if (!$mysqli->query("UPDATE products_attributes SET options_values_price=" . fmt4($u['absNew']) . ", price_prefix='" . $u['prefNew'] . "' WHERE products_attributes_id=" . (int) $u['paid'])) throw new Exception("attr paid=" . $u['paid'] . ": " . $mysqli->error); }
	foreach ($updAttrG1 as $u) { if (!$mysqli->query("UPDATE products_attributes_groups SET options_values_price=" . fmt4($u['absNew']) . ", price_prefix='" . $u['prefNew'] . "' WHERE products_attributes_id=" . (int) $u['paid'] . " AND customers_group_id=" . G1_GROUP_ID)) throw new Exception("attr g1 paid=" . $u['paid'] . ": " . $mysqli->error); }
	foreach ($insAttrG1 as $u) { if (!$mysqli->query("INSERT INTO products_attributes_groups (products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id, options_values_weight, weight_prefix) VALUES (" . (int) $u['paid'] . ", " . G1_GROUP_ID . ", " . fmt4($u['absNew']) . ", '" . $u['prefNew'] . "', " . (int) $u['pid'] . ", 0, '+')")) throw new Exception("ins attr g1 paid=" . $u['paid'] . ": " . $mysqli->error); }
	$mysqli->commit();
	logMsg("=== COMMIT OK ===");
} catch (Exception $e) { $mysqli->rollback(); logMsg("=== ROLLBACK por error: " . $e->getMessage() . " ==="); }

end_action:
?>
	</div>
	<p style="margin-top:15px;"><a href="<?php echo tep_href_link('Actualizador_precios_lalizas.php'); ?>" class="xbutton small hv9">← Volver</a></p>
<?php else: ?>
	<h2>Actualizador de precios — Lalizas</h2>
	<?php $xlsx = findNewestXlsx(LALIZAS_DIR); if (!$xlsx) echo '<p style="color:red;">No hay xlsx en ' . LALIZAS_DIR . '</p>'; else echo '<p style="color:#666;font-size:13px;">xlsx: <code>' . htmlspecialchars(basename($xlsx)) . '</code> (' . round(filesize($xlsx)/1024) . ' KB, mtime ' . date('Y-m-d H:i', filemtime($xlsx)) . ')</p>'; ?>
	<p>
		Compara <code>coste</code> (col F) y <code>PVP</code> (col G) de la hoja <code>precios</code> con
		<code>products_cost</code>, <code>products_price</code> y Grupo 1, para los productos Lalizas
		(<code>origin lalizas%</code> o manufacturers 3/16/96/246/376). Casa por <strong>EAN</strong> y, si no, por modelo/ref.
		Maneja productos con variantes (la más barata define el precio del padre y el resto el delta).
		Aplica solo cuando la diferencia &gt; <strong><?php echo PRICE_THRESHOLD * 100; ?>%</strong>.
	</p>
	<form method="post" style="background:#f5f5f5;padding:15px;border-radius:5px;">
		<p><strong>Tope de variación</strong>: <label>excluir cambios &gt; <input type="number" name="max_change_pct" value="<?php echo (int) MAX_CHANGE_PCT_DEF; ?>" min="0" max="500" step="1" style="width:60px;"> %</label>
			<small style="color:#888;display:block;margin-top:4px;">Si el price o cost del producto (padre, si hay variantes) cambia más de este %, se excluye el producto entero. 0 = sin tope. Protege contra pack-vs-unidad o errores.</small></p>
		<p><label><input type="checkbox" name="apply_extremes" value="1"> Aplicar también los extremos (desactiva el tope)</label></p>
		<p><label><input type="checkbox" name="only_extremes" value="1"> <strong>Ver SOLO los productos saltados por extremos</strong> (oculta el resto del plan)</label></p>
		<p><label>Cambios máximos por ejecución (0 = sin límite): <input type="number" name="max" value="0" min="0" style="width:80px;"></label></p>
		<p><label><input type="checkbox" name="confirm_execute" value="1"> Aplicar cambios (sin marcar = solo PLAN/dry-run)</label></p>
		<input type="hidden" name="action" value="plan">
		<button type="submit" name="action" value="plan" class="xbutton small hv9">Generar plan (dry-run)</button>
		<button type="submit" name="action" value="execute" class="xbutton small hv9 verde" onclick="return this.form.confirm_execute.checked || (alert('Marca la casilla Aplicar cambios antes de ejecutar.'), false);">Ejecutar</button>
	</form>
	<p style="margin-top:20px;color:#888;font-size:12px;">
		<strong>Reglas</strong> (idénticas al importador Lalizas):<br>
		- <code>products_cost</code> ← col F (precio neto sin IVA).<br>
		- <code>products_price</code> ← <code>roundToNickel(col G, PVP sin IVA)</code>; fallback <code>cost × 2</code>.<br>
		- <strong>Grupo 1</strong>: tiers según margen (≥45% ×0.75, 40 ×0.80, 35 ×0.82, 30 ×0.85, &lt;30% ×0.90) + piso cost×<?php echo G1_FLOOR_FACTOR; ?>.<br>
		- Match por EAN (col D) y, si no, por modelo/ref (col A). Variantes: padre = más barata, resto delta.<br>
		- Scope: productos Lalizas (origin lalizas% o manufacturers 3/16/96/246/376). Stock NO se toca. Sin match → se listan, no se tocan.
	</p>
<?php endif; ?>
</div></td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
