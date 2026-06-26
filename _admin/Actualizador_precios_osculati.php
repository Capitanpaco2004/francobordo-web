<?php
require 'includes/application_top.php';
require_once dirname(__DIR__) . '/includes/vendor/autoload.php';

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);

const OSCULATI_MFG_ID       = 259;
const XLSX_DIR              = '/home/francobordo/public_html/import/Osculati/';
const PRICE_DIFF_THRESHOLD  = 0.005;
const MAX_CHANGE_PCT_DEF    = 30;       // tope superior default (configurable en URL)
const IVA_ES                = 1.21;
const PVP_BREAKPOINT        = 20.0;
const G1_GROUP_ID           = 1;

/** Redondea un PVP NETO de modo que el precio CON IVA quede en múltiplo de 0.05. */
function roundToNickel($net) {
	$wi = ((float) $net) * IVA_ES;
	$r  = round($wi * 20) / 20;
	return round($r / IVA_ES, 4);
}

$dryRun = !isset($_GET['execute']);
$applyExtremes = isset($_GET['apply_extremes']);
$onlyExtremes = isset($_GET['only_extremes']); // mostrar SOLO tablas de extremos
$maxChangePct  = isset($_GET['max_change_pct']) ? (float) $_GET['max_change_pct'] : MAX_CHANGE_PCT_DEF;
if ($maxChangePct < 0) $maxChangePct = 0;
$maxChangeRatio = $maxChangePct / 100.0;

/** delta% absoluto entre dos valores. */
function deltaPct($oldP, $newP) {
	$ref = max(abs((float) $oldP), 0.01);
	return abs((float) $newP - (float) $oldP) / $ref;
}

// ---------------- Xlsx ----------------

function findLatestXlsx($dir) {
	$files = glob($dir . '*.xlsx');
	if (!$files) return null;
	usort($files, function ($a, $b) { return filemtime($b) - filemtime($a); });
	return $files[0];
}

function parseXlsx($path) {
	$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
	$reader->setReadDataOnly(true);
	$spreadsheet = $reader->load($path);
	$sheet = $spreadsheet->getActiveSheet();
	$rows = [];
	$headerSeen = false;
	foreach ($sheet->getRowIterator() as $r) {
		$cellIt = $r->getCellIterator();
		$cellIt->setIterateOnlyExistingCells(false);
		$arr = [];
		foreach ($cellIt as $c) $arr[] = $c->getValue();
		if (!$headerSeen) {
			if (isset($arr[0]) && strtolower(trim((string)$arr[0])) === 'item') $headerSeen = true;
			continue;
		}
		$item = trim((string)($arr[0] ?? ''));
		if ($item === '') continue;
		$rows[$item] = [
			'item'      => $item,
			'um'        => trim((string)($arr[3] ?? '')),
			'net_price' => (float)($arr[5] ?? 0),
			'rrp'       => (float)($arr[12] ?? 0),
			'ean'       => trim((string)($arr[10] ?? '')),
		];
	}
	return $rows;
}

// ---------------- Helpers de precio ----------------

function isInScope($row) {
	if ($row['um'] === 'PZ') return true;
	if (preg_match('/^A[24]-/i', $row['item'])) return true;
	return false;
}

function calcUnitCost($row) { return (float)$row['net_price']; }

function calcPvpSinIva($row) {
	$cost = calcUnitCost($row);
	if ($cost <= 0) return 0.0;
	$candidate50ConIva = $cost * 2 * IVA_ES;
	if ($candidate50ConIva < PVP_BREAKPOINT) {
		return round($cost * 2, 4);
	}
	$rrp = (float)$row['rrp'];
	if ($rrp <= 0) return round($cost * 2, 4);
	return round($rrp / IVA_ES, 4);
}

function g1Multiplier($pvp, $cost) {
	if ($pvp <= 0) return 0.90;
	$m = ($pvp - $cost) / $pvp;
	if ($m >= 0.45) return 0.75;
	if ($m >= 0.40) return 0.80;
	if ($m >= 0.35) return 0.82;
	if ($m >= 0.30) return 0.85;
	return 0.90;
}

function calcG1Price($pvp, $cost) {
	// Tier según margen real, con piso: G1 nunca por debajo de cost x 1.10 (margen mínimo 10%).
	$tierPrice = $pvp * g1Multiplier($pvp, $cost);
	$floor     = (float) $cost * 1.10;
	return round(max($tierPrice, $floor), 4);
}

function aboveThreshold($newVal, $oldVal) {
	$o = (float)$oldVal; $n = (float)$newVal;
	if (abs($o) < 1e-6) return abs($n - $o) > 0.0001;
	return abs($n - $o) / abs($o) > PRICE_DIFF_THRESHOLD;
}

function stripSuffix($code) {
	$p = strpos((string)$code, '#');
	return $p === false ? (string)$code : substr((string)$code, 0, $p);
}

function fmt4($v) { return number_format((float)$v, 4, '.', ''); }

// ---------------- Carga de datos ----------------

function loadProducts() {
	$rows = [];
	// Scope: manufacturer Osculati (259) + origin osculati% + cualquier producto cuya referencia
	// tenga formato de código Osculati NN.NNN.NN[+suffix], tanto en reference_prov como en products_model.
	// (decisión usuario 2026-06-24): muchos productos legacy de marcas que Osculati distribuye —Lewmar,
	// Solas, Clamcleat, Marinco, Raymarine…— llevan el código Osculati en products_model y no estaban en
	// el scope. El match real contra el xlsx solo actualiza los que tengan ese código en el listado actual,
	// así que no hay riesgo de pisar precios de marcas con codificación propia (Jobe/Dometic no colisionan).
	$sql = "SELECT products_id, products_model, reference_prov, products_price, products_cost
	        FROM products
	        WHERE manufacturers_id = " . OSCULATI_MFG_ID . "
	           OR products_import_origin LIKE 'osculati%'
	           OR (reference_prov IS NOT NULL AND reference_prov <> ''
	               AND reference_prov RLIKE '^[0-9]{2}\\.[0-9]{3}\\.[0-9]{2}')
	           OR (products_model IS NOT NULL AND products_model <> ''
	               AND products_model RLIKE '^[0-9]{2}\\.[0-9]{3}\\.[0-9]{2}')";
	$r = tep_db_query($sql);
	while ($p = tep_db_fetch_array($r)) {
		$pid = (int)$p['products_id'];
		$rows[$pid] = [
			'pid'      => $pid,
			'model'    => stripSuffix(trim((string)$p['products_model'])),
			'ref_prov' => stripSuffix(trim((string)$p['reference_prov'])),
			'price'    => (float)$p['products_price'],
			'cost'     => (float)$p['products_cost'],
			'attrs'    => [],
		];
	}
	return $rows;
}

function loadAttributes(array $pids) {
	$by = [];
	if (!$pids) return $by;
	$ids = implode(',', array_map('intval', $pids));
	$r = tep_db_query("SELECT products_attributes_id, products_id, options_id, options_values_id, reference, reference_prov, options_values_price, price_prefix FROM products_attributes WHERE products_id IN ($ids)");
	while ($a = tep_db_fetch_array($r)) {
		$code = trim((string)$a['reference']);
		if ($code === '') $code = trim((string)$a['reference_prov']);
		$by[(int)$a['products_id']][] = [
			'pa_id'      => (int)$a['products_attributes_id'],
			'options_id' => (int)$a['options_id'],
			'values_id'  => (int)$a['options_values_id'],
			'code'       => stripSuffix($code),
			'code_rp'    => stripSuffix(trim((string)$a['reference_prov'])),
			'cur_delta'  => (float)$a['options_values_price'],
			'cur_prefix' => $a['price_prefix'],
		];
	}
	return $by;
}

function loadG1Products(array $pids) {
	$by = [];
	if (!$pids) return $by;
	$ids = implode(',', array_map('intval', $pids));
	$r = tep_db_query("SELECT products_id, customers_group_price FROM products_groups WHERE customers_group_id = " . G1_GROUP_ID . " AND products_id IN ($ids)");
	while ($x = tep_db_fetch_array($r)) $by[(int)$x['products_id']] = (float)$x['customers_group_price'];
	return $by;
}

function loadG1Attrs(array $paIds) {
	$by = [];
	if (!$paIds) return $by;
	$ids = implode(',', array_map('intval', $paIds));
	$r = tep_db_query("SELECT products_attributes_id, options_values_price, price_prefix FROM products_attributes_groups WHERE customers_group_id = " . G1_GROUP_ID . " AND products_attributes_id IN ($ids)");
	while ($x = tep_db_fetch_array($r)) {
		$by[(int)$x['products_attributes_id']] = ['delta' => (float)$x['options_values_price'], 'prefix' => $x['price_prefix']];
	}
	return $by;
}

// ---------------- Plan ----------------

function buildPlan(array $prods, array $xlsx, array $g1prods, array $g1attrs, float $maxChangeRatio = 0.0, bool $applyExtremes = false) {
	$plan = [
		'updates_product'    => [],
		'updates_attr'       => [],
		'updates_g1_product' => [],
		'inserts_g1_product' => [],
		'updates_g1_attr'    => [],
		'inserts_g1_attr'    => [],
		'extremes'           => [], // productos enteros excluidos por delta > tope
		'skipped_no_match'   => 0,
		'skipped_out_scope'  => 0,
		'skipped_unchanged'  => 0,
		'skipped_no_cost'    => 0,
		'unmatched_codes'    => [],
	];

	foreach ($prods as $p) {
		$pid = $p['pid'];
		$hasAttrs = !empty($p['attrs']);

		if (!$hasAttrs) {
			$row = $xlsx[$p['model']] ?? null;
			// Fallback cross-link: si el model no matchea, probar reference_prov (OSC BC)
			if (!$row && !empty($p['ref_prov']) && $p['ref_prov'] !== $p['model']) {
				$row = $xlsx[$p['ref_prov']] ?? null;
			}
			if (!$row) {
				$plan['unmatched_codes'][] = $p['model'];
				$plan['skipped_no_match']++;
				continue;
			}
			if (!isInScope($row)) { $plan['skipped_out_scope']++; continue; }
			$newCost  = round(calcUnitCost($row), 4);
			if ($newCost <= 0) { $plan['skipped_no_cost']++; continue; }
			$newPrice = roundToNickel(calcPvpSinIva($row));
			$newG1    = roundToNickel(calcG1Price($newPrice, $newCost));

			// Tope: si price o cost cambian más del tope → excluir producto entero
			$dP = deltaPct($p['price'], $newPrice);
			$dC = deltaPct($p['cost'],  $newCost);
			if (!$applyExtremes && $maxChangeRatio > 0 && (max($dP, $dC) > $maxChangeRatio)) {
				$plan['extremes'][] = [
					'pid' => $pid, 'model' => $p['model'], 'why' => 'suelto',
					'old_price' => $p['price'], 'new_price' => $newPrice,
					'old_cost'  => $p['cost'],  'new_cost'  => $newCost,
				];
				continue;
			}

			$changed = false;
			if (aboveThreshold($newPrice, $p['price']) || aboveThreshold($newCost, $p['cost'])) {
				$plan['updates_product'][] = [
					'pid'       => $pid,
					'model'     => $p['model'],
					'old_price' => $p['price'],
					'new_price' => $newPrice,
					'old_cost'  => $p['cost'],
					'new_cost'  => $newCost,
				];
				$changed = true;
			}
			$oldG1 = $g1prods[$pid] ?? null;
			if ($oldG1 === null) {
				$plan['inserts_g1_product'][] = ['pid' => $pid, 'price' => $newG1];
				$changed = true;
			} elseif (aboveThreshold($newG1, $oldG1)) {
				$plan['updates_g1_product'][] = ['pid' => $pid, 'old' => $oldG1, 'new' => $newG1];
				$changed = true;
			}
			if (!$changed) $plan['skipped_unchanged']++;
			continue;
		}

		// Producto con variantes
		$variants = [];
		foreach ($p['attrs'] as $a) {
			$row = $xlsx[$a['code']] ?? null;
			// Fallback cross-link: si reference no matchea, probar reference_prov
			if (!$row && !empty($a['code_rp']) && $a['code_rp'] !== $a['code']) {
				$row = $xlsx[$a['code_rp']] ?? null;
			}
			if (!$row) {
				$plan['unmatched_codes'][] = $a['code'];
				continue;
			}
			if (!isInScope($row)) continue;
			$vCost = round(calcUnitCost($row), 4);
			if ($vCost <= 0) continue;
			$vPrice = roundToNickel(calcPvpSinIva($row));
			$variants[] = [
				'pa_id'      => $a['pa_id'],
				'code'       => $a['code'],
				'cost'       => $vCost,
				'price'      => $vPrice,
				'g1'         => roundToNickel(calcG1Price($vPrice, $vCost)),
				'cur_delta'  => $a['cur_delta'],
				'cur_prefix' => $a['cur_prefix'],
			];
		}

		if (!$variants) continue;

		usort($variants, function ($a, $b) { return $a['price'] <=> $b['price']; });
		$base = $variants[0];
		$newPrice = $base['price'];
		$newCost  = $base['cost'];
		$newG1    = $base['g1'];

		// Tope: si price o cost del padre (variante más barata) cambian más del tope → excluir producto entero
		$dP = deltaPct($p['price'], $newPrice);
		$dC = deltaPct($p['cost'],  $newCost);
		if (!$applyExtremes && $maxChangeRatio > 0 && (max($dP, $dC) > $maxChangeRatio)) {
			$plan['extremes'][] = [
				'pid' => $pid, 'model' => $p['model'], 'why' => 'con variantes (' . count($variants) . ')',
				'old_price' => $p['price'], 'new_price' => $newPrice,
				'old_cost'  => $p['cost'],  'new_cost'  => $newCost,
			];
			continue;
		}

		if (aboveThreshold($newPrice, $p['price']) || aboveThreshold($newCost, $p['cost'])) {
			$plan['updates_product'][] = [
				'pid'       => $pid,
				'model'     => $p['model'],
				'old_price' => $p['price'],
				'new_price' => $newPrice,
				'old_cost'  => $p['cost'],
				'new_cost'  => $newCost,
			];
		}
		$oldG1 = $g1prods[$pid] ?? null;
		if ($oldG1 === null) $plan['inserts_g1_product'][] = ['pid' => $pid, 'price' => $newG1];
		elseif (aboveThreshold($newG1, $oldG1)) $plan['updates_g1_product'][] = ['pid' => $pid, 'old' => $oldG1, 'new' => $newG1];

		foreach ($variants as $v) {
			$delta  = round($v['price'] - $newPrice, 4);
			$prefix = $delta < 0 ? '-' : '+';
			$absD   = abs($delta);
			$curSig = ($v['cur_prefix'] === '-' ? -1 : 1) * $v['cur_delta'];
			if (aboveThreshold($delta, $curSig) || ($prefix !== $v['cur_prefix'] && abs($delta - $curSig) > 0.0001)) {
				$plan['updates_attr'][] = [
					'pa_id'    => $v['pa_id'],
					'pid'      => $pid,
					'code'     => $v['code'],
					'old'      => $curSig,
					'new'      => $delta,
					'abs'      => $absD,
					'prefix'   => $prefix,
				];
			}

			$g1Delta  = round($v['g1'] - $newG1, 4);
			$g1Prefix = $g1Delta < 0 ? '-' : '+';
			$g1Abs    = abs($g1Delta);
			$cur      = $g1attrs[$v['pa_id']] ?? null;
			if ($cur === null) {
				$plan['inserts_g1_attr'][] = ['pa_id' => $v['pa_id'], 'pid' => $pid, 'abs' => $g1Abs, 'prefix' => $g1Prefix];
			} else {
				$curSigG1 = ($cur['prefix'] === '-' ? -1 : 1) * $cur['delta'];
				if (aboveThreshold($g1Delta, $curSigG1) || ($g1Prefix !== $cur['prefix'] && abs($g1Delta - $curSigG1) > 0.0001)) {
					$plan['updates_g1_attr'][] = [
						'pa_id'  => $v['pa_id'],
						'pid'    => $pid,
						'old'    => $curSigG1,
						'new'    => $g1Delta,
						'abs'    => $g1Abs,
						'prefix' => $g1Prefix,
					];
				}
			}
		}
	}

	return $plan;
}

// ---------------- Apply ----------------

function applyPlan(array $plan) {
	foreach ($plan['updates_product'] as $u) {
		tep_db_query("UPDATE products SET products_price = " . fmt4($u['new_price']) . ", products_cost = " . fmt4($u['new_cost']) . ", products_last_modified = NOW() WHERE products_id = " . (int)$u['pid']);
	}
	foreach ($plan['updates_attr'] as $u) {
		tep_db_query("UPDATE products_attributes SET options_values_price = " . fmt4($u['abs']) . ", price_prefix = '" . $u['prefix'] . "', attributes_last_modified = NOW() WHERE products_attributes_id = " . (int)$u['pa_id']);
	}
	foreach ($plan['updates_g1_product'] as $u) {
		tep_db_query("UPDATE products_groups SET customers_group_price = " . fmt4($u['new']) . " WHERE products_id = " . (int)$u['pid'] . " AND customers_group_id = " . G1_GROUP_ID);
	}
	foreach ($plan['inserts_g1_product'] as $u) {
		tep_db_query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . G1_GROUP_ID . ", " . (int)$u['pid'] . ", " . fmt4($u['price']) . ", 1, 1)");
	}
	foreach ($plan['updates_g1_attr'] as $u) {
		tep_db_query("UPDATE products_attributes_groups SET options_values_price = " . fmt4($u['abs']) . ", price_prefix = '" . $u['prefix'] . "' WHERE products_attributes_id = " . (int)$u['pa_id'] . " AND customers_group_id = " . G1_GROUP_ID);
	}
	foreach ($plan['inserts_g1_attr'] as $u) {
		tep_db_query("INSERT INTO products_attributes_groups (products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id, options_values_weight, weight_prefix) VALUES (" . (int)$u['pa_id'] . ", " . G1_GROUP_ID . ", " . fmt4($u['abs']) . ", '" . $u['prefix'] . "', " . (int)$u['pid'] . ", 0, '+')");
	}
}

// ---------------- Render ----------------

$xlsxPath = findLatestXlsx(XLSX_DIR);
?>
<?php require THEME . 'html/header.php'; ?>
<style>
.osc-wrap { padding: 10px 20px; font-family: Arial, sans-serif; font-size: 13px; }
.osc-wrap h1 { font-size: 22px; margin: 8px 0 14px; }
.osc-wrap h2 { font-size: 16px; margin: 18px 0 8px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
.osc-wrap table { border-collapse: collapse; width: 100%; margin: 6px 0 14px; }
.osc-wrap th, .osc-wrap td { border: 1px solid #ddd; padding: 4px 8px; text-align: left; vertical-align: top; }
.osc-wrap th { background: #f0f0f0; }
.osc-wrap td.num { text-align: right; font-family: monospace; }
.osc-wrap .summary li { margin: 2px 0; }
.osc-wrap .btn { display: inline-block; padding: 8px 16px; border-radius: 4px; text-decoration: none; color: #fff; }
.osc-wrap .btn-run { background: #c33; }
.osc-wrap .btn-back { background: #888; }
.osc-wrap .badge-dry { background: #fa3; color: #000; padding: 3px 8px; border-radius: 3px; font-weight: bold; }
.osc-wrap .badge-exec { background: #393; color: #fff; padding: 3px 8px; border-radius: 3px; font-weight: bold; }
.osc-wrap details summary { cursor: pointer; padding: 4px 0; font-weight: bold; }
.osc-wrap .small { color: #666; font-size: 11px; }
</style>
<div class="osc-wrap">
<h1>Actualizador de precios Osculati</h1>
<p>Modo: <span class="<?php echo $dryRun ? 'badge-dry' : 'badge-exec'; ?>"><?php echo $dryRun ? 'DRY RUN (no escribe)' : 'EJECUTANDO'; ?></span></p>
<?php
if (!$xlsxPath) {
	echo '<p style="color:red"><strong>No hay ningún .xlsx en ' . htmlspecialchars(XLSX_DIR) . '</strong></p>';
	echo '</div>';
	require THEME . 'html/footer.php';
	require DIR_WS_INCLUDES . 'application_bottom.php';
	exit;
}

echo '<p>Fichero: <code>' . htmlspecialchars(basename($xlsxPath)) . '</code> <span class="small">(modificado ' . date('Y-m-d H:i', filemtime($xlsxPath)) . ')</span></p>';

$t0 = microtime(true);
$xlsx = parseXlsx($xlsxPath);
echo '<p>Filas xlsx leídas: <strong>' . count($xlsx) . '</strong> <span class="small">(' . round(microtime(true) - $t0, 2) . 's)</span></p>';

$t0 = microtime(true);
$prods = loadProducts();
$attrs = loadAttributes(array_keys($prods));
foreach ($attrs as $pid => $arr) if (isset($prods[$pid])) $prods[$pid]['attrs'] = $arr;
$g1prods = loadG1Products(array_keys($prods));
$paIds = [];
foreach ($attrs as $arr) foreach ($arr as $a) $paIds[] = $a['pa_id'];
$g1attrs = loadG1Attrs($paIds);

$nWith = 0; $nWithout = 0;
foreach ($prods as $p) { if ($p['attrs']) $nWith++; else $nWithout++; }
echo '<p>Productos Osculati: <strong>' . count($prods) . '</strong> (' . $nWithout . ' sueltos, ' . $nWith . ' con variantes; ' . count($paIds) . ' variantes) <span class="small">(' . round(microtime(true) - $t0, 2) . 's)</span></p>';

$t0 = microtime(true);
$plan = buildPlan($prods, $xlsx, $g1prods, $g1attrs, $maxChangeRatio, $applyExtremes);
echo '<p>Plan calculado en ' . round(microtime(true) - $t0, 2) . 's.</p>';

// ─── Bloque A: cómputo de specials a borrar ───
// Política V2 (2026-06-25): cuando se ejecuta SIN "Aplicar extremos" (apply_extremes=false), borramos
// TODAS las ofertas activas (specials.status=1) de los productos en scope. Razón: las ofertas se
// pusieron sobre PVP antiguo; al cambiar el PVP dejan de tener sentido. Si quieres conservarlas,
// marca "Aplicar también los extremos".
$badSpecials = [];
if (!$applyExtremes && !empty($prods)) {
	// PVP efectivo post-run = nuevo si hay update, si no el actual.
	$effPrice = [];
	foreach ($prods as $pid => $p) $effPrice[$pid] = (float) $p['price'];
	foreach ($plan['updates_product'] as $u) $effPrice[(int)$u['pid']] = (float) $u['new_price'];

	$ids = implode(',', array_map('intval', array_keys($prods)));
	$rs = tep_db_query("SELECT specials_id, products_id, specials_new_products_price, specials_date_added, expires_date, expires_repeat FROM specials WHERE status=1 AND products_id IN ($ids)");
	while ($s = tep_db_fetch_array($rs)) {
		$pid = (int) $s['products_id'];
		$eff = (float) ($effPrice[$pid] ?? 0);
		$sp  = (float) $s['specials_new_products_price'];
		$dtoPct = $eff > 0 ? (($eff - $sp) / $eff) * 100 : 0.0;
		$badSpecials[] = [
			'specials_id' => (int) $s['specials_id'],
			'pid' => $pid,
			'ref' => $prods[$pid]['model'] ?? '?',
			'eff_price' => $eff,
			'sp_price'  => $sp,
			'dto_pct'   => $dtoPct,
			'reason'    => ($sp > $eff) ? 'NEGATIVO (special > PVP)' : (sprintf('dto %.1f%%', $dtoPct) . ' — política: borrar todas en run sin extremos'),
			'created'   => substr((string)$s['specials_date_added'], 0, 10),
			'expires'   => substr((string)$s['expires_date'], 0, 10),
		];
	}
}

// Form para ajustar el tope y apply_extremes (solo en dry-run, no rompe la URL ?execute=1)
if ($dryRun) {
	echo '<form method="get" style="background:#f5f5f5;padding:10px;border-radius:4px;margin-bottom:15px;">';
	echo '<strong>Tope de variación</strong>: excluir cambios &gt; <input type="number" name="max_change_pct" value="' . (int) $maxChangePct . '" min="0" max="500" step="1" style="width:60px;"> %';
	echo ' &nbsp; <label><input type="checkbox" name="apply_extremes" value="1"' . ($applyExtremes ? ' checked' : '') . '> Aplicar también los extremos (desactiva el tope)</label>';
	echo ' &nbsp; <label><input type="checkbox" name="only_extremes" value="1"' . ($onlyExtremes ? ' checked' : '') . '> <strong>Ver SOLO los extremos</strong></label>';
	echo ' &nbsp; <button type="submit" class="btn btn-back" style="background:#36c;">Recalcular plan</button>';
	echo '<br><span class="small">Si el price o cost del producto (padre, en caso de variantes) cambia más del porcentaje, el producto ENTERO se excluye. 0 = sin tope. Protege contra pack-vs-unidad y errores de feed.</span>';
	echo '</form>';
}

?>
<h2>Resumen</h2>
<ul class="summary">
	<li>UPDATE <code>products</code> precio/coste: <strong><?php echo count($plan['updates_product']); ?></strong></li>
	<li>UPDATE <code>products_attributes</code> delta: <strong><?php echo count($plan['updates_attr']); ?></strong></li>
	<li>UPDATE <code>products_groups</code> Grupo 1: <strong><?php echo count($plan['updates_g1_product']); ?></strong></li>
	<li>INSERT <code>products_groups</code> Grupo 1: <strong><?php echo count($plan['inserts_g1_product']); ?></strong></li>
	<li>UPDATE <code>products_attributes_groups</code> Grupo 1: <strong><?php echo count($plan['updates_g1_attr']); ?></strong></li>
	<li>INSERT <code>products_attributes_groups</code> Grupo 1: <strong><?php echo count($plan['inserts_g1_attr']); ?></strong></li>
	<?php if (!$applyExtremes && $maxChangeRatio > 0): ?>
	<li>⚠️ Productos EXTREMOS excluidos (price o cost del padre supera <?php echo $maxChangePct; ?>%): <strong><?php echo count($plan['extremes']); ?></strong></li>
	<?php endif; ?>
	<?php if (!$applyExtremes): ?>
	<li>🗑️ Specials a BORRAR (TODAS las ofertas activas en scope): <strong><?php echo count($badSpecials); ?></strong><?php if (empty($badSpecials)) echo ' (ninguno)'; ?></li>
	<?php endif; ?>
	<li class="small">Sin match en xlsx (no se toca): <?php echo $plan['skipped_no_match']; ?> + <?php echo count($plan['unmatched_codes']) - $plan['skipped_no_match']; ?> variantes | fuera de scope (U.M. ≠ PZ y no A2-/A4-): <?php echo $plan['skipped_out_scope']; ?> | sin coste válido: <?php echo $plan['skipped_no_cost']; ?> | sin cambio significativo: <?php echo $plan['skipped_unchanged']; ?></li>
</ul>

<?php
function renderTable($title, $rows, $cols, $limit = 200) {
	$n = count($rows);
	if ($n === 0) return;
	if (!empty($GLOBALS['onlyExtremes']) && stripos($title, 'EXTREMO') === false) return;
	if (!empty($GLOBALS['onlyExtremes'])) $limit = 1000000;
	echo '<details><summary>' . htmlspecialchars($title) . ' (' . $n . ($n > $limit ? ', mostrando primeros ' . $limit : '') . ')</summary>';
	echo '<table><tr>';
	foreach ($cols as $h) echo '<th>' . htmlspecialchars($h[0]) . '</th>';
	echo '</tr>';
	$i = 0;
	foreach ($rows as $r) {
		if ($i++ >= $limit) break;
		echo '<tr>';
		foreach ($cols as $c) {
			$cls = isset($c[2]) && $c[2] === 'num' ? ' class="num"' : '';
			$val = $c[1]($r);
			echo '<td' . $cls . '>' . htmlspecialchars((string)$val) . '</td>';
		}
		echo '</tr>';
	}
	echo '</table></details>';
}

if (!empty($plan['extremes'])) {
	renderTable('⚠️ EXTREMOS — productos completos excluidos (probablemente pack-vs-unidad o error de feed)', $plan['extremes'], [
		['pid', function ($r) { return $r['pid']; }],
		['model', function ($r) { return $r['model']; }],
		['tipo', function ($r) { return $r['why']; }],
		['old_price', function ($r) { return number_format($r['old_price'], 4); }, 'num'],
		['new_price', function ($r) { return number_format($r['new_price'], 4); }, 'num'],
		['Δ price %', function ($r) { return number_format(deltaPct($r['old_price'], $r['new_price']) * 100, 1); }, 'num'],
		['old_cost', function ($r) { return number_format($r['old_cost'], 4); }, 'num'],
		['new_cost', function ($r) { return number_format($r['new_cost'], 4); }, 'num'],
		['Δ cost %', function ($r) { return number_format(deltaPct($r['old_cost'], $r['new_cost']) * 100, 1); }, 'num'],
	]);
}

// ─── Bloque C: listado detallado de specials a borrar ───
if (!empty($badSpecials)) {
	renderTable('🗑️ Specials a BORRAR (TODAS las ofertas activas en scope — política V2)', $badSpecials, [
		['specials_id', function ($r) { return $r['specials_id']; }],
		['pid', function ($r) { return $r['pid']; }],
		['ref', function ($r) { return $r['ref']; }],
		['PVP (IVA inc)', function ($r) { return number_format($r['eff_price'] * 1.21, 2); }, 'num'],
		['sp (IVA inc)', function ($r) { return number_format($r['sp_price'] * 1.21, 2); }, 'num'],
		['dto %', function ($r) { return number_format($r['dto_pct'], 1); }, 'num'],
		['creado', function ($r) { return $r['created']; }],
		['expira', function ($r) { return $r['expires']; }],
		['motivo', function ($r) { return $r['reason']; }],
	]);
}

renderTable('Cambios precio/coste de producto', $plan['updates_product'], [
	['pid', function ($r) { return $r['pid']; }],
	['model', function ($r) { return $r['model']; }],
	['old_price', function ($r) { return number_format($r['old_price'], 4); }, 'num'],
	['new_price', function ($r) { return number_format($r['new_price'], 4); }, 'num'],
	['old_cost', function ($r) { return number_format($r['old_cost'], 4); }, 'num'],
	['new_cost', function ($r) { return number_format($r['new_cost'], 4); }, 'num'],
]);

renderTable('Cambios delta de variantes', $plan['updates_attr'], [
	['pa_id', function ($r) { return $r['pa_id']; }],
	['pid', function ($r) { return $r['pid']; }],
	['code', function ($r) { return $r['code']; }],
	['old_delta', function ($r) { return number_format($r['old'], 4); }, 'num'],
	['new_delta', function ($r) { return number_format($r['new'], 4); }, 'num'],
]);

renderTable('UPDATEs Grupo 1 (productos)', $plan['updates_g1_product'], [
	['pid', function ($r) { return $r['pid']; }],
	['old', function ($r) { return number_format($r['old'], 4); }, 'num'],
	['new', function ($r) { return number_format($r['new'], 4); }, 'num'],
]);
renderTable('INSERTs Grupo 1 (productos)', $plan['inserts_g1_product'], [
	['pid', function ($r) { return $r['pid']; }],
	['price', function ($r) { return number_format($r['price'], 4); }, 'num'],
]);
renderTable('UPDATEs Grupo 1 (variantes)', $plan['updates_g1_attr'], [
	['pa_id', function ($r) { return $r['pa_id']; }],
	['pid', function ($r) { return $r['pid']; }],
	['old', function ($r) { return number_format($r['old'], 4); }, 'num'],
	['new', function ($r) { return number_format($r['new'], 4); }, 'num'],
]);
renderTable('INSERTs Grupo 1 (variantes)', $plan['inserts_g1_attr'], [
	['pa_id', function ($r) { return $r['pa_id']; }],
	['pid', function ($r) { return $r['pid']; }],
	['abs', function ($r) { return number_format($r['abs'], 4); }, 'num'],
	['prefix', function ($r) { return $r['prefix']; }],
]);
$unmatchedRows = array_map(function ($c) { return ['code' => $c]; }, array_values(array_unique($plan['unmatched_codes'])));
renderTable('Códigos no encontrados en xlsx (informativo, no se modifica nada)', $unmatchedRows, [
	['code', function ($r) { return $r['code']; }],
]);

if (!$dryRun) {
	$t0 = microtime(true);
	tep_db_query('START TRANSACTION');
	try {
		applyPlan($plan);

		// ─── Bloque D: backup + DELETE de specials (política V2) ───
		// Va dentro del try: si falla, ROLLBACK revierte applyPlan también.
		if (!empty($badSpecials)) {
			$bakDir = '/home/francobordo/backups';
			@mkdir($bakDir, 0755, true);
			$bakPath = $bakDir . '/osculati_specials_purge_' . date('Ymd_His') . '.sql';
			$fh = @fopen($bakPath, 'w');
			if ($fh) {
				fwrite($fh, "-- Backup specials borrados por Actualizador_precios_osculati.php " . date('Y-m-d H:i:s') . "\n");
				fwrite($fh, "-- Política V2: !apply_extremes ⇒ borrar TODAS las ofertas activas en scope. Total: " . count($badSpecials) . " filas.\n\n");
				$idList = implode(',', array_map(fn($b) => (int) $b['specials_id'], $badSpecials));
				$rb = tep_db_query("SELECT * FROM specials WHERE specials_id IN ($idList)");
				while ($srow = tep_db_fetch_array($rb)) {
					$cols = array_keys($srow);
					$vals = array_map(function ($v) {
						if ($v === null) return 'NULL';
						return "'" . tep_db_input((string) $v) . "'";
					}, array_values($srow));
					fwrite($fh, "INSERT INTO specials (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n");
				}
				fclose($fh);
				echo '<p style="color:#393">Backup specials borrados: <code>' . htmlspecialchars($bakPath) . '</code> (' . (@filesize($bakPath) ?: 0) . ' bytes)</p>';
			} else {
				echo '<p style="color:#c33;font-weight:bold">WARN: no pude crear backup en ' . htmlspecialchars($bakDir) . ' — abortando DELETE de specials por seguridad.</p>';
				throw new Exception('backup specials no escribible');
			}
			$idList = implode(',', array_map(fn($b) => (int) $b['specials_id'], $badSpecials));
			$delRes = tep_db_query("DELETE FROM specials WHERE specials_id IN ($idList)");
			if (!$delRes) throw new Exception('delete specials');
			$affected = tep_db_affected_rows($delRes);
			echo '<p style="color:#393">Specials borrados: <strong>' . (int)$affected . '</strong></p>';

			// ─── Bloque E: bump products_last_modified de los pids cuyos specials se borraron ───
			$pidsBump = array_unique(array_map(fn($b) => (int) $b['pid'], $badSpecials));
			// Evitar duplicar el bump que applyPlan ya hace para los productos con UPDATE.
			$alreadyBumped = [];
			foreach ($plan['updates_product'] as $u) $alreadyBumped[(int)$u['pid']] = true;
			$pidsBump = array_values(array_filter($pidsBump, fn($pid) => !isset($alreadyBumped[$pid])));
			if (!empty($pidsBump)) {
				$pidsList = implode(',', $pidsBump);
				tep_db_query("UPDATE products SET products_last_modified=NOW() WHERE products_id IN ($pidsList)");
				echo '<p style="color:#393">products_last_modified bumpeado para ' . count($pidsBump) . ' pids adicionales.</p>';
			}
		}

		tep_db_query('COMMIT');
		echo '<p style="color:#393;font-weight:bold">✔ Cambios aplicados en ' . round(microtime(true) - $t0, 2) . 's.</p>';
	} catch (\Throwable $e) {
		tep_db_query('ROLLBACK');
		echo '<p style="color:#c33;font-weight:bold">✘ Error: ' . htmlspecialchars($e->getMessage()) . ' — ROLLBACK.</p>';
	}
	echo '<p><a class="btn btn-back" href="' . tep_href_link('Actualizador_precios_osculati.php') . '">Volver a dry-run</a></p>';
} else {
	$total = count($plan['updates_product']) + count($plan['updates_attr']) + count($plan['updates_g1_product']) + count($plan['inserts_g1_product']) + count($plan['updates_g1_attr']) + count($plan['inserts_g1_attr']);
	if ($total > 0) {
		$execParams = 'execute=1&max_change_pct=' . (int) $maxChangePct . ($applyExtremes ? '&apply_extremes=1' : '');
		echo '<p><a class="btn btn-run" href="' . tep_href_link('Actualizador_precios_osculati.php', $execParams) . '" onclick="return confirm(\'¿Aplicar ' . $total . ' cambios a la base de datos?\')">Ejecutar ' . $total . ' cambios</a></p>';
	} else {
		echo '<p><em>No hay cambios que aplicar.</em></p>';
	}
}
?>
</div>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
