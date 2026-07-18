<?php
require 'includes/application_top.php';

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);

const AZIMUT_CSV       = '/home/francobordo/public_html/import/feed/Azimut/datos_nautica_francobordo.csv';
const ORIGIN_FLAG      = 'azimut';
const G1_GROUP_ID      = 1; // "Profesionales"
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

function loadCsvPrices($path) {
	$f = fopen($path, 'r');
	if (!$f) return [];
	stream_filter_append($f, 'convert.iconv.ISO-8859-1/UTF-8');
	$header = fgetcsv($f, 0, ',', '"', '');
	if (!$header) { fclose($f); return []; }
	$idx = array_flip($header);
	$prices = [];
	while (($r = fgetcsv($f, 0, ',', '"', '')) !== false) {
		if (count($r) < count($header)) continue;
		$pc = trim($r[$idx['ProductCode']] ?? '');
		$priceRaw = trim($r[$idx['Price']] ?? '');
		if ($pc === '' || !is_numeric($priceRaw)) continue;
		$price = (float) $priceRaw;
		if ($price < 0) continue;
		$prices[$pc] = $price;
		$pcNoSpace = str_replace(' ', '', $pc);
		if ($pcNoSpace !== $pc && !isset($prices[$pcNoSpace])) $prices[$pcNoSpace] = $price;
	}
	fclose($f);
	return $prices;
}

function priceDeltaPct($oldP, $newP) {
	$ref = max(abs((float) $oldP), 0.01);
	return abs((float) $newP - (float) $oldP) / $ref;
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
	<h2>Actualizador precios Azimut — <?php echo $dryRun ? 'PLAN (dry-run, sin cambios)' : 'EJECUCIÓN REAL'; ?></h2>
	<p>
		<a href="<?php echo tep_href_link('Actualizador_precios_azimut.php'); ?>" class="xbutton small hv9">← Volver</a>
	</p>
	<div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n";
@flush();

	logMsg("Modo: " . ($dryRun ? "PLAN (dry-run)" : "EXECUTE")
		. " | tope cambio=" . ($applyExtremes ? "OFF (aplicando también extremos)" : ("|Δ| ≤ " . rtrim(rtrim(number_format($maxChangePct, 1, '.', ''), '0'), '.') . "%"))
		. ($max>0 ? " | max=$max" : ""));

	if (!file_exists(AZIMUT_CSV)) { logMsg("ERROR CSV no encontrado: " . AZIMUT_CSV); goto end_action; }
	logMsg("Cargando precios del CSV…");
	$csvPrices = loadCsvPrices(AZIMUT_CSV);
	logMsg("Precios CSV cargados: " . count($csvPrices) . " entradas (incluye variantes con/sin espacios)");

	$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
	if ($mysqli->connect_error) { logMsg("ERROR DB: " . $mysqli->connect_error); goto end_action; }
	$mysqli->set_charset('utf8');

	logMsg("Leyendo productos con products_import_origin='" . ORIGIN_FLAG . "'…");
	$prods = [];
	$r = $mysqli->query("SELECT products_id, products_model, reference_prov, products_price FROM products WHERE products_import_origin='" . ORIGIN_FLAG . "'");
	while ($row = $r->fetch_assoc()) $prods[(int) $row['products_id']] = $row;
	logMsg("Productos Azimut en BD: " . count($prods));
	if (empty($prods)) { logMsg("Nada que hacer."); goto end_action; }

	// Cargar G1 actual en bulk
	$ids = implode(',', array_keys($prods));
	$g1Cur = [];
	$r = $mysqli->query("SELECT products_id, customers_group_price FROM products_groups WHERE customers_group_id=" . G1_GROUP_ID . " AND products_id IN ($ids)");
	while ($row = $r->fetch_assoc()) $g1Cur[(int) $row['products_id']] = (float) $row['customers_group_price'];

	// Construir plan
	$updPrice = [];
	$updG1    = [];
	$insG1    = [];
	$extremesPrice = []; $extremesPids = [];
	$noMatch  = [];
	$noChange = 0;
	$processed = 0;

	foreach ($prods as $pid => $p) {
		if ($max > 0 && $processed >= $max) break;
		$processed++;
		$model = trim((string) $p['products_model']);
		$ref   = trim((string) $p['reference_prov']);
		$candidates = array_unique(array_filter([$model, $ref, str_replace(' ', '', $model), str_replace(' ', '', $ref)]));
		$newPrice = null; $matchedKey = null;
		foreach ($candidates as $c) {
			if (isset($csvPrices[$c])) { $newPrice = $csvPrices[$c]; $matchedKey = $c; break; }
		}
		if ($newPrice === null) { $noMatch[] = $p; continue; }
		$newPrice = roundToNickel($newPrice);

		$cur = (float) $p['products_price'];
		$deltaPrice = priceDeltaPct($cur, $newPrice);
		$priceChanged = $deltaPrice > PRICE_THRESHOLD;
		$priceExtreme = $maxChangeRatio > 0 && $deltaPrice > $maxChangeRatio;
		if ($priceChanged) {
			$row = ['pid' => $pid, 'model' => $model, 'old' => $cur, 'new' => $newPrice];
			if ($priceExtreme && !$applyExtremes) { $extremesPrice[] = $row; $extremesPids[$pid] = true; }
			else $updPrice[] = $row;
		}

		// G1 (Profesionales) = products_price × 0.9. Redondeado a .05 con IVA.
		// Si el price es extremo, el G1 tampoco se toca (quedaría desalineado del price actual).
		$newG1 = roundToNickel($newPrice * 0.9);
		if (!isset($extremesPids[$pid])) {
			if (isset($g1Cur[$pid])) {
				$g1 = $g1Cur[$pid];
				if (priceDeltaPct($g1, $newG1) > PRICE_THRESHOLD) {
					$updG1[] = ['pid' => $pid, 'model' => $model, 'old' => $g1, 'new' => $newG1];
				}
			} else {
				$insG1[] = ['pid' => $pid, 'model' => $model, 'new' => $newG1];
			}
		}
		if (!$priceChanged && !empty($g1Cur[$pid]) && priceDeltaPct($g1Cur[$pid], $newG1) <= PRICE_THRESHOLD) $noChange++;
	}

	// ───────────────────── Bloque A: cómputo specials a borrar (V3: solo repreciados en ESTE run) ─────────────────────
	// Política V3 (2026-07-17): solo ofertas de productos cuyo PVP cambia en este run ($updPrice).
	// (La V2 borraba todas las del scope — incidente Osculati 2026-07-16, 383 ofertas purgadas y restauradas.)
	$badSpecials = [];
	if (!$applyExtremes && !empty($updPrice)) {
		$effPrice = [];
		foreach ($updPrice as $u) $effPrice[(int)$u['pid']] = (float) $u['new'];

		$idsRepriced = implode(',', array_map('intval', array_keys($effPrice)));
		$rs = $mysqli->query("SELECT specials_id, products_id, specials_new_products_price, specials_date_added, expires_date, expires_repeat FROM specials WHERE status=1 AND products_id IN ($idsRepriced)");
		if (!$rs) { logMsg("ERROR SELECT specials: " . $mysqli->error); goto end_action; }
		while ($s = $rs->fetch_assoc()) {
			$pid = (int) $s['products_id'];
			$eff = (float) ($effPrice[$pid] ?? 0);
			$sp  = (float) $s['specials_new_products_price'];
			$dtoPct = $eff > 0 ? (($eff - $sp) / $eff) * 100 : 0.0;
			$badSpecials[] = [
				'specials_id' => (int) $s['specials_id'],
				'pid' => $pid,
				'ref' => $prods[$pid]['products_model'] ?? '?',
				'eff_price' => $eff,
				'sp_price'  => $sp,
				'dto_pct'   => $dtoPct,
				'reason'    => ($sp > $eff) ? 'NEGATIVO (special > PVP nuevo)' : (sprintf('dto %.1f%% sobre PVP nuevo', $dtoPct) . ' — PVP repreciado en este run'),
				'created'   => substr((string)$s['specials_date_added'], 0, 10),
				'expires'   => substr((string)$s['expires_date'], 0, 10),
			];
		}
	}

	logMsg("==================== PLAN ====================");
	logMsg("Procesados: $processed");
	logMsg("UPDATE products.products_price : " . count($updPrice));
	logMsg("UPDATE products_groups (G1)    : " . count($updG1));
	logMsg("INSERT products_groups (G1)    : " . count($insG1));
	if (!$applyExtremes && $maxChangeRatio > 0) {
		logMsg("⚠️  Extremos > {$maxChangePct}% EXCLUIDOS (revisar): price=" . count($extremesPrice) . " (afecta a " . count($extremesPids) . " pids; sus G1 tampoco se tocan)");
	}
	logMsg("Sin cambios significativos      : $noChange");
	logMsg("Sin match en CSV                : " . count($noMatch));
	// ───────────────────── Bloque B: línea en el resumen de PLAN ─────────────────────
	if (!$applyExtremes) logMsg("🗑️  Specials a BORRAR (solo de productos repreciados en este run) : " . count($badSpecials) . (empty($badSpecials)?" (ninguno)":""));

	$showLimit = 25; if (!empty($onlyExtremes)) $showLimit = 1000000;
	if (!empty($updPrice)) {
		logMsg("--- UPDATE products_price (top $showLimit) ---");
		foreach (array_slice($updPrice, 0, $showLimit) as $u) {
			$pct = priceDeltaPct($u['old'], $u['new']) * 100;
			logMsg(sprintf("  pid=%d model=%s : %.4f → %.4f (%s%.1f%%)", $u['pid'], $u['model'], $u['old'], $u['new'], $u['new']>=$u['old']?'+':'-', $pct));
		}
		if (count($updPrice) > $showLimit) logMsg("  …y " . (count($updPrice) - $showLimit) . " más");
	}
	if (!empty($insG1)) {
		logMsg("--- INSERT G1 (top $showLimit) ---");
		foreach (array_slice($insG1, 0, $showLimit) as $u) {
			logMsg(sprintf("  pid=%d model=%s : (sin G1) → %.4f", $u['pid'], $u['model'], $u['new']));
		}
		if (count($insG1) > $showLimit) logMsg("  …y " . (count($insG1) - $showLimit) . " más");
	}
	if (!empty($updG1)) {
		logMsg("--- UPDATE G1 (top $showLimit) ---");
		foreach (array_slice($updG1, 0, $showLimit) as $u) {
			$pct = priceDeltaPct($u['old'], $u['new']) * 100;
			logMsg(sprintf("  pid=%d model=%s : G1 %.4f → %.4f (%s%.1f%%)", $u['pid'], $u['model'], $u['old'], $u['new'], $u['new']>=$u['old']?'+':'-', $pct));
		}
		if (count($updG1) > $showLimit) logMsg("  …y " . (count($updG1) - $showLimit) . " más");
	}
	if (!empty($extremesPrice)) {
		logMsg("--- ⚠️ EXTREMOS price (excluidos, top $showLimit) — probablemente pack-vs-unidad o error de feed ---");
		foreach (array_slice($extremesPrice, 0, $showLimit) as $u) {
			$pct = priceDeltaPct($u['old'], $u['new']) * 100;
			logMsg(sprintf("  pid=%d model=%s : %.4f → %.4f (%s%.1f%%)", $u['pid'], $u['model'], $u['old'], $u['new'], $u['new']>=$u['old']?'+':'-', $pct));
		}
		if (count($extremesPrice) > $showLimit) logMsg("  …y " . (count($extremesPrice) - $showLimit) . " más");
	}
	if (!empty($noMatch)) {
		logMsg("--- Sin match en CSV (top $showLimit, informativo, no se tocan) ---");
		foreach (array_slice($noMatch, 0, $showLimit) as $p) {
			logMsg(sprintf("  pid=%d model=%s ref=%s", (int)$p['products_id'], $p['products_model'], $p['reference_prov']));
		}
		if (count($noMatch) > $showLimit) logMsg("  …y " . (count($noMatch) - $showLimit) . " más");
	}
	// ───────────────────── Bloque C: listado detallado de specials a borrar ─────────────────────
	if (!empty($badSpecials)) {
		logMsg(sprintf("--- 🗑️ Specials a BORRAR (TODAS: %d) ---", count($badSpecials)));
		foreach ($badSpecials as $b) {
			logMsg(sprintf("  specials_id=%d pid=%d ref=%-14s PVP=%7.2f sp=%7.2f dto=%5.1f%% creado=%s expira=%s — %s",
				$b['specials_id'], $b['pid'], $b['ref'], $b['eff_price']*1.21, $b['sp_price']*1.21, $b['dto_pct'], $b['created'], $b['expires'], $b['reason']));
		}
	}

	if ($dryRun) {
		logMsg("=== Dry-run finalizado. No se ha tocado nada. ===");
		goto end_action;
	}

	// EXECUTE: aplicar en transacción única
	logMsg("Aplicando cambios en transacción única…");
	$mysqli->begin_transaction();
	try {
		foreach ($updPrice as $u) {
			$ok = $mysqli->query("UPDATE products SET products_price=" . fmt4($u['new']) . ", products_last_modified=NOW() WHERE products_id=" . (int)$u['pid']);
			if (!$ok) throw new Exception("UPDATE products pid=" . $u['pid'] . ": " . $mysqli->error);
		}
		foreach ($updG1 as $u) {
			$ok = $mysqli->query("UPDATE products_groups SET customers_group_price=" . fmt4($u['new']) . " WHERE products_id=" . (int)$u['pid'] . " AND customers_group_id=" . G1_GROUP_ID);
			if (!$ok) throw new Exception("UPDATE products_groups pid=" . $u['pid'] . ": " . $mysqli->error);
		}
		foreach ($insG1 as $u) {
			$ok = $mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . G1_GROUP_ID . ", " . (int)$u['pid'] . ", " . fmt4($u['new']) . ", 1, 1)");
			if (!$ok) throw new Exception("INSERT products_groups pid=" . $u['pid'] . ": " . $mysqli->error);
		}
		// ───────────────────── Bloque D: DELETE specials + backup ─────────────────────
		if (!empty($badSpecials)) {
			$bakDir = '/home/francobordo/backups';
			@mkdir($bakDir, 0755, true);
			$bakPath = $bakDir . '/azimut_specials_purge_' . date('Ymd_His') . '.sql';
			$freeBytes = @disk_free_space($bakDir);
			if ($freeBytes !== false && $freeBytes < 100 * 1024 * 1024) {
				logMsg("WARN: poco espacio en $bakDir (" . round(($freeBytes ?: 0) / 1024 / 1024) . "MB libres, mínimo 100MB) — abortando DELETE de specials.");
				throw new Exception("disco insuficiente para backup specials");
			}
			$fh = @fopen($bakPath, 'w');
			if ($fh) {
				fwrite($fh, "-- Backup specials borrados por Actualizador_precios_azimut.php " . date('Y-m-d H:i:s') . "\n");
				fwrite($fh, "-- Política V3: !apply_extremes ⇒ borrar ofertas de productos repreciados en este run. Total: " . count($badSpecials) . " filas.\n\n");
				$idList = implode(',', array_map(fn($b) => (int) $b['specials_id'], $badSpecials));
				$rb = $mysqli->query("SELECT * FROM specials WHERE specials_id IN ($idList)");
				if ($rb) while ($srow = $rb->fetch_assoc()) {
					$cols = array_keys($srow);
					$vals = array_map(function ($v) use ($mysqli) {
						if ($v === null) return 'NULL';
						return "'" . $mysqli->real_escape_string((string) $v) . "'";
					}, array_values($srow));
					fwrite($fh, "INSERT INTO specials (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n");
				}
				fclose($fh);
				$bakSize = @filesize($bakPath);
				if ($bakSize === false || $bakSize < 100) {
					@unlink($bakPath);
					logMsg("WARN: backup escrito vacío/truncado ($bakSize bytes) — abortando DELETE de specials.");
					throw new Exception("backup specials truncado o vacío");
				}
				logMsg("Backup specials borrados: $bakPath ($bakSize bytes)");
			} else {
				logMsg("WARN: no pude crear backup en $bakDir — abortando DELETE de specials por seguridad.");
				throw new Exception("backup specials no escribible");
			}
			$idList = implode(',', array_map(fn($b) => (int) $b['specials_id'], $badSpecials));
			if (!$mysqli->query("DELETE FROM specials WHERE specials_id IN ($idList)"))
				throw new Exception("delete specials: " . $mysqli->error);
			logMsg("Specials borrados: " . $mysqli->affected_rows);
			// ───────────────────── Bloque E (variante alternativa): bump products_last_modified ─────────────────────
			$pidsBumpList = implode(',', array_unique(array_map(fn($b)=>(int)$b['pid'], $badSpecials)));
			$mysqli->query("UPDATE products SET products_last_modified=NOW() WHERE products_id IN ($pidsBumpList)");
		}
		$mysqli->commit();
		logMsg("=== COMMIT OK ===");
		logMsg("UPDATE products_price aplicados: " . count($updPrice));
		logMsg("UPDATE G1 aplicados            : " . count($updG1));
		logMsg("INSERT G1 aplicados            : " . count($insG1));
	} catch (Exception $e) {
		$mysqli->rollback();
		logMsg("=== ROLLBACK por error: " . $e->getMessage() . " ===");
	}

	end_action:;
?>
	</div>
	<p style="margin-top:15px;">
		<a href="<?php echo tep_href_link('Actualizador_precios_azimut.php'); ?>" class="xbutton small hv9">← Volver</a>
	</p>
<?php else: ?>
	<h2>Actualizador de precios — Azimut</h2>
	<p>
		Compara el <code>Price</code> del CSV con <code>products.products_price</code> y <code>products_groups</code> (Grupo 1)
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
			<small style="color:#888;display:block;margin-top:4px;">Los cambios cuyo |Δ| supere este porcentaje se listan aparte como "extremos" y NO se aplican. 0 = sin tope. Protege contra errores del feed o productos con SKU reasignado.</small>
		</p>
		<p>
			<label><input type="checkbox" name="apply_extremes" value="1"> Aplicar también los extremos (desactiva el tope)</label>
		</p>
		<p>
			<label>Límite por ejecución (0 = sin límite):
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
		- <code>products_price</code> ← <code>Price</code> del CSV (PVP de Azimut sin IVA, tal cual).<br>
		- G1 (<code>products_groups.customers_group_id=<?php echo G1_GROUP_ID; ?></code>) ← <code>products_price × 0.9</code> (10% descuento sobre PVP sin IVA, sin coste real). INSERT si no existe.<br>
		- Threshold: solo aplica si <code>|nuevo − actual| / |actual| &gt; <?php echo PRICE_THRESHOLD; ?></code>.<br>
		- Stock: <strong>NO se toca</strong> (regla operativa).<br>
		- Productos sin match en el CSV se listan informativamente y se dejan tal cual.<br>
		- Solo se procesan productos con <code>products_import_origin='<?php echo ORIGIN_FLAG; ?>'</code>.<br>
		- <strong>Output en streaming en tiempo real</strong>: ya no hay 504 timeout en batches largos.
	</p>
<?php endif; ?>
</div>
</td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
