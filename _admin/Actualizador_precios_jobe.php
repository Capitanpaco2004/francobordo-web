<?php
require 'includes/application_top.php';

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);
error_reporting(error_reporting() & ~E_DEPRECATED);   // PHP 8.5: no ensuciar la salida con curl_close()

/* =============================================================================
 * Actualizador de precios Jobe — modelado sobre Actualizador_precios_osculati.php
 * Fuentes (igual que import-jobe-altas.php):
 *   - Feed   public_html/import/feed/jobe.xml   → retailPrice (PVP con IVA) + ean por talla
 *   - Portal joe.jobesports.com (modal)         → precio dealer (= coste) por talla
 * Para cada producto Jobe en BD cuyo products_model esté en el feed:
 *   coste  = dealer del portal
 *   PVP    = retailPrice / 1.21  (fallback dealer×2 si no hay retail)
 *   G1     = tiers de margen + piso coste×1.10
 * Match de variantes por products_attributes_ean (EAN del feed) → fallback sufijo
 * de reference (<productId>-<tallaNum>). Variante más barata = padre, resto delta.
 * NO toca stock. Tope de variación 30% (excluye producto entero). Threshold 0.5%.
 * ===========================================================================*/

const JOBE_MFG_ID          = 257;
const JOBE_USER            = 'f.rodriguez@francobordo.com';
const JOBE_PASS            = 'Jobe0908';
const JOBE_BASE            = 'https://joe.jobesports.com';
define('FEED_PATH',          dirname(dirname(__FILE__)) . '/import/feed/jobe.xml');
define('JOBE_TMP_DIR',       dirname(__FILE__) . '/import-jobe/');
const PRICE_DIFF_THRESHOLD = 0.005;
const MAX_CHANGE_PCT_DEF   = 30;       // tope superior default (configurable en URL)
const IVA_ES               = 1.21;
const G1_GROUP_ID          = 1;
const DEALER_CACHE_TTL     = 21600;    // 6h: reusar caché de precios dealer salvo refresh

$dryRun        = !isset($_GET['execute']);
$applyExtremes = isset($_GET['apply_extremes']);
$onlyExtremes  = isset($_GET['only_extremes']);
$refreshPortal = isset($_GET['refresh_portal']);
$maxChangePct  = isset($_GET['max_change_pct']) ? (float) $_GET['max_change_pct'] : MAX_CHANGE_PCT_DEF;
if ($maxChangePct < 0) $maxChangePct = 0;
$maxChangeRatio = $maxChangePct / 100.0;

/* ----------------------------- Precio helpers ------------------------------ */
function roundToNickel($net) {
	$wi = ((float) $net) * IVA_ES;
	$r  = round($wi * 20) / 20;
	return round($r / IVA_ES, 4);
}
function deltaPct($oldP, $newP) {
	$ref = max(abs((float) $oldP), 0.01);
	return abs((float) $newP - (float) $oldP) / $ref;
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
	$tierPrice = $pvp * g1Multiplier($pvp, $cost);
	$floor     = (float) $cost * 1.10;        // piso: margen mínimo 10%
	$g1 = max($tierPrice, $floor);
	if ($g1 > $pvp) $g1 = $pvp;
	return round($g1, 4);
}
function aboveThreshold($newVal, $oldVal) {
	$o = (float) $oldVal; $n = (float) $newVal;
	if (abs($o) < 1e-6) return abs($n - $o) > 0.0001;
	return abs($n - $o) / abs($o) > PRICE_DIFF_THRESHOLD;
}
function fmt4($v) { return number_format((float) $v, 4, '.', ''); }
function jobeLogMsg($msg) { echo '<div class="small" style="font-family:monospace">' . htmlspecialchars((string) $msg) . "</div>\n"; @flush(); }

/* ----------------------------- Bloque A: cómputo de specials a purgar ------ */
/** Política V3 (2026-07-17): si !applyExtremes, se borran solo las ofertas activas de
 *  productos cuyo PVP cambia en ESTE run (plan updates_product). La V2 borraba todas
 *  las del scope — incidente Osculati 2026-07-16, restaurado. */
function jobeComputeBadSpecials(array $prods, array $plan, $applyExtremes) {
	$badSpecials = [];
	if ($applyExtremes) return $badSpecials;
	if (empty($plan['updates_product'])) return $badSpecials;

	$effPrice = [];
	foreach ($plan['updates_product'] as $u) $effPrice[(int) $u['pid']] = (float) $u['new_price'];

	$ids = implode(',', array_map('intval', array_keys($effPrice)));
	try {
		$rs = tep_db_query("SELECT specials_id, products_id, specials_new_products_price, specials_date_added, expires_date, expires_repeat FROM specials WHERE status=1 AND products_id IN ($ids)");
	} catch (\Throwable $e) {
		jobeLogMsg("ERROR SELECT specials: " . $e->getMessage());
		return $badSpecials;
	}
	while ($s = tep_db_fetch_array($rs)) {
		$pid = (int) $s['products_id'];
		$eff = (float) ($effPrice[$pid] ?? 0);
		$sp  = (float) $s['specials_new_products_price'];
		$dtoPct = $eff > 0 ? (($eff - $sp) / $eff) * 100 : 0.0;
		$badSpecials[] = [
			'specials_id' => (int) $s['specials_id'],
			'pid'         => $pid,
			'ref'         => $prods[$pid]['model'] ?? '?',
			'eff_price'   => $eff,
			'sp_price'    => $sp,
			'dto_pct'     => $dtoPct,
			'reason'      => ($sp > $eff) ? 'NEGATIVO (special > PVP nuevo)' : (sprintf('dto %.1f%%', $dtoPct) . ' sobre PVP nuevo — PVP repreciado en este run'),
			'created'     => substr((string) $s['specials_date_added'], 0, 10),
			'expires'     => substr((string) $s['expires_date'], 0, 10),
		];
	}
	return $badSpecials;
}

/* ----------------------------- Bloque D: backup + DELETE ------------------- */
function jobePurgeSpecials(array $badSpecials) {
	if (empty($badSpecials)) return;
	$bakDir = '/home/francobordo/backups';
	@mkdir($bakDir, 0755, true);
	$bakPath = $bakDir . '/jobe_specials_purge_' . date('Ymd_His') . '.sql';
	$freeBytes = @disk_free_space($bakDir);
	if ($freeBytes !== false && $freeBytes < 100 * 1024 * 1024) {
		jobeLogMsg("WARN: poco espacio en $bakDir (" . round(($freeBytes ?: 0) / 1024 / 1024) . "MB libres, mínimo 100MB) — abortando DELETE de specials.");
		throw new Exception("disco insuficiente para backup specials");
	}
	$fh = @fopen($bakPath, 'w');
	if (!$fh) {
		jobeLogMsg("WARN: no pude crear backup en $bakDir — abortando DELETE de specials por seguridad.");
		throw new Exception("backup specials no escribible");
	}
	fwrite($fh, "-- Backup specials borrados por Actualizador_precios_jobe.php " . date('Y-m-d H:i:s') . "\n");
	fwrite($fh, "-- Política V3: !apply_extremes ⇒ borrar ofertas de productos repreciados en este run. Total: " . count($badSpecials) . " filas.\n\n");
	$idList = implode(',', array_map(fn($b) => (int) $b['specials_id'], $badSpecials));
	$rb = tep_db_query("SELECT * FROM specials WHERE specials_id IN ($idList)");
	if ($rb) while ($srow = tep_db_fetch_array($rb)) {
		$cols = array_keys($srow);
		$vals = array_map(function ($v) {
			if ($v === null) return 'NULL';
			return "'" . tep_db_input((string) $v) . "'";
		}, array_values($srow));
		fwrite($fh, "INSERT INTO specials (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n");
	}
	fclose($fh);
	$bakSize = @filesize($bakPath);
	if ($bakSize === false || $bakSize < 100) {
		@unlink($bakPath);
		jobeLogMsg("WARN: backup escrito vacío/truncado ($bakSize bytes) — abortando DELETE de specials.");
		throw new Exception("backup specials truncado o vacío");
	}
	jobeLogMsg("Backup specials borrados: $bakPath ($bakSize bytes)");

	$del = tep_db_query("DELETE FROM specials WHERE specials_id IN ($idList)");
	if ($del === false) throw new Exception("delete specials falló");
	$affected = (is_object($del) && method_exists($del, 'rowCount')) ? $del->rowCount() : count($badSpecials);
	jobeLogMsg("Specials borrados: " . $affected);

	// Bloque E (older sin $bumpedMain): bumpear products_last_modified de los pids afectados
	$pidsBump = implode(',', array_unique(array_map(fn($b) => (int) $b['pid'], $badSpecials)));
	if ($pidsBump !== '') tep_db_query("UPDATE products SET products_last_modified = NOW() WHERE products_id IN ($pidsBump)");
}

function parseMoney($s) {
	$s = trim((string) $s);
	if ($s === '' || $s === '-') return 0.0;
	$s = str_replace(['€', ' ', "\xc2\xa0"], '', $s);
	if (preg_match('/,\d{1,2}$/', $s)) { $s = str_replace('.', '', $s); $s = str_replace(',', '.', $s); }
	else { $s = str_replace(',', '', $s); }
	return (float) $s;
}
function normalizeSize($size) {
	$s = trim((string) $size);
	if (preg_match('/^(\d+(?:[.,]\d+)?)\s*INCH$/i', $s, $m)) return str_replace(',', '.', $m[1]) . '"';
	return $s;
}
function sizeKey($size) { return preg_replace('/[^0-9A-Za-z]/', '', normalizeSize($size)); }

/* ----------------------------- Feed ---------------------------------------- */
function parseJobeFeed($path) {
	$byId = [];
	if (!file_exists($path)) return $byId;
	$xml = @simplexml_load_file($path);
	if (!$xml) return $byId;
	foreach ($xml->product as $p) {
		$pid = trim((string) $p->productId);
		if ($pid === '') continue;
		$byId[$pid][] = [
			'size'   => trim((string) $p->size),
			'ean'    => trim((string) $p->ean),
			'retail' => parseMoney((string) $p->retailPrice),
		];
	}
	return $byId;
}

/* ----------------------------- Portal -------------------------------------- */
$GLOBALS['JOBE_CK'] = JOBE_TMP_DIR . 'cookies.txt';
function jobeCurl($url, $post = null) {
	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60,
		CURLOPT_COOKIEJAR => $GLOBALS['JOBE_CK'], CURLOPT_COOKIEFILE => $GLOBALS['JOBE_CK'],
		CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36',
		CURLOPT_HTTPHEADER => ['X-Requested-With: XMLHttpRequest'],
	]);
	if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
	$resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // sin curl_close(): deprecado en PHP 8.5
	return [$code, $resp];
}
function jobeLogin() {
	@unlink($GLOBALS['JOBE_CK']);
	jobeCurl(JOBE_BASE . '/');
	jobeCurl(JOBE_BASE . '/process.php?action=login', ['username' => JOBE_USER, 'password' => JOBE_PASS]);
	list($c, $h) = jobeCurl(JOBE_BASE . '/en/');
	return ($c === 200 && stripos((string) $h, 'Log Out') !== false);
}
/** Precio dealer por talla (= coste) desde el modal. Devuelve [sizeToken => dealer]. */
function jobeFetchDealer($productId) {
	list($code, $html) = jobeCurl(JOBE_BASE . '/api/product/get-product-modal', ['product' => $productId]);
	if ($code !== 200 || $html === false) return null;
	$d = json_decode($html, true);
	$h = is_array($d) ? ($d['html'] ?? '') : $html;
	if ($h === '') return null;
	$dealer = [];
	if (preg_match_all("/data-price='([^']+)'[^>]*data-size='([^']+)'/", $h, $mm, PREG_SET_ORDER)) {
		foreach ($mm as $r) $dealer[trim($r[2])] = (float) $r[1];
	}
	return $dealer;   // puede ser [] si el producto ya no está en el portal
}

/* ----------------------------- BD: carga ----------------------------------- */
function loadProducts() {
	$rows = [];
	$sql = "SELECT products_id, products_model, product_ean, products_price, products_cost
	        FROM products
	        WHERE manufacturers_id = " . JOBE_MFG_ID . " OR products_import_origin LIKE 'jobe%'";
	$r = tep_db_query($sql);
	while ($p = tep_db_fetch_array($r)) {
		$pid = (int) $p['products_id'];
		$rows[$pid] = [
			'pid'   => $pid,
			'model' => trim((string) $p['products_model']),
			'ean'   => trim((string) $p['product_ean']),
			'price' => (float) $p['products_price'],
			'cost'  => (float) $p['products_cost'],
			'attrs' => [],
		];
	}
	return $rows;
}
function loadAttributes(array $pids) {
	$by = [];
	if (!$pids) return $by;
	$ids = implode(',', array_map('intval', $pids));
	$r = tep_db_query("SELECT products_attributes_id, products_id, options_id, options_values_id, reference, products_attributes_ean, options_values_price, price_prefix FROM products_attributes WHERE products_id IN ($ids)");
	while ($a = tep_db_fetch_array($r)) {
		$ref = trim((string) $a['reference']);
		$suffix = (strpos($ref, '-') !== false) ? substr($ref, strrpos($ref, '-') + 1) : '';
		$by[(int) $a['products_id']][] = [
			'pa_id'      => (int) $a['products_attributes_id'],
			'ean'        => trim((string) $a['products_attributes_ean']),
			'ref_suffix' => preg_replace('/[^0-9A-Za-z]/', '', $suffix),
			'cur_delta'  => (float) $a['options_values_price'],
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
	while ($x = tep_db_fetch_array($r)) $by[(int) $x['products_id']] = (float) $x['customers_group_price'];
	return $by;
}
function loadG1Attrs(array $paIds) {
	$by = [];
	if (!$paIds) return $by;
	$ids = implode(',', array_map('intval', $paIds));
	$r = tep_db_query("SELECT products_attributes_id, options_values_price, price_prefix FROM products_attributes_groups WHERE customers_group_id = " . G1_GROUP_ID . " AND products_attributes_id IN ($ids)");
	while ($x = tep_db_fetch_array($r)) $by[(int) $x['products_attributes_id']] = ['delta' => (float) $x['options_values_price'], 'prefix' => $x['price_prefix']];
	return $by;
}

/* ----------------------------- Precio por talla ---------------------------- */
/** Calcula [cost, pvp, g1] para una fila de feed + mapa dealer del portal. */
function priceForRow($feedRow, $dealerMap) {
	$size = $feedRow['size'];
	$dealer = $dealerMap[$size] ?? 0.0;
	if ($dealer <= 0) { foreach ($dealerMap as $k => $v) { if (strcasecmp($k, $size) === 0) { $dealer = $v; break; } } }
	$cost = round((float) $dealer, 4);
	$pvp  = ($feedRow['retail'] > 0) ? $feedRow['retail'] / IVA_ES : ($dealer > 0 ? $dealer * 2 : 0);
	$pvp  = round($pvp, 4);   // PVP exacto de Jobe, sin redondeo a 0.05
	return [$cost, $pvp, calcG1Price($pvp, $cost)];
}

/* ----------------------------- Plan ---------------------------------------- */
function buildPlan(array $prods, array $feed, array $dealers, array $g1prods, array $g1attrs, float $maxChangeRatio, bool $applyExtremes) {
	$plan = [
		'updates_product' => [], 'updates_attr' => [], 'updates_g1_product' => [],
		'inserts_g1_product' => [], 'updates_g1_attr' => [], 'inserts_g1_attr' => [],
		'extremes' => [], 'skipped_no_match' => 0, 'skipped_no_cost' => 0,
		'skipped_unchanged' => 0, 'unmatched' => [],
	];

	foreach ($prods as $p) {
		$pid = $p['pid'];
		$model = $p['model'];
		$feedRows = $feed[$model] ?? null;
		if (!$feedRows) { $plan['skipped_no_match']++; $plan['unmatched'][] = $model; continue; }
		$dealerMap = $dealers[$model] ?? [];

		if (empty($p['attrs'])) {
			// Suelto: elegir fila (por product_ean si válido, si no la más barata por dealer)
			$row = null;
			if ($p['ean'] !== '') { foreach ($feedRows as $fr) if (strcasecmp($fr['ean'], $p['ean']) === 0) { $row = $fr; break; } }
			if (!$row) {
				$best = null; $bestCost = PHP_FLOAT_MAX;
				foreach ($feedRows as $fr) { list($c) = priceForRow($fr, $dealerMap); $cc = $c > 0 ? $c : $fr['retail']; if ($cc < $bestCost) { $bestCost = $cc; $best = $fr; } }
				$row = $best ?: $feedRows[0];
			}
			list($newCost, $newPrice, $newG1) = priceForRow($row, $dealerMap);
			if ($newPrice <= 0) { $plan['skipped_no_cost']++; continue; }
			// Sin dealer en el portal → NO pisar el coste con 0: conservar el actual.
			if ($newCost <= 0) { $newCost = $p['cost']; $newG1 = calcG1Price($newPrice, $newCost); }

			$dP = deltaPct($p['price'], $newPrice);
			$dC = ($p['cost'] > 0 && $newCost > 0) ? deltaPct($p['cost'], $newCost) : 0;
			if (!$applyExtremes && $maxChangeRatio > 0 && max($dP, $dC) > $maxChangeRatio) {
				$plan['extremes'][] = ['pid' => $pid, 'model' => $model, 'why' => 'suelto',
					'old_price' => $p['price'], 'new_price' => $newPrice, 'old_cost' => $p['cost'], 'new_cost' => $newCost];
				continue;
			}
			$changed = false;
			if (aboveThreshold($newPrice, $p['price']) || ($newCost > 0 && aboveThreshold($newCost, $p['cost']))) {
				$plan['updates_product'][] = ['pid' => $pid, 'model' => $model, 'old_price' => $p['price'], 'new_price' => $newPrice, 'old_cost' => $p['cost'], 'new_cost' => $newCost];
				$changed = true;
			}
			$oldG1 = $g1prods[$pid] ?? null;
			if ($oldG1 === null) { $plan['inserts_g1_product'][] = ['pid' => $pid, 'price' => $newG1]; $changed = true; }
			elseif (aboveThreshold($newG1, $oldG1)) { $plan['updates_g1_product'][] = ['pid' => $pid, 'old' => $oldG1, 'new' => $newG1]; $changed = true; }
			if (!$changed) $plan['skipped_unchanged']++;
			continue;
		}

		// Con variantes: match cada attr a una fila del feed (por EAN, fallback sufijo ref)
		$variants = [];
		foreach ($p['attrs'] as $a) {
			$row = null;
			if ($a['ean'] !== '') { foreach ($feedRows as $fr) if (strcasecmp($fr['ean'], $a['ean']) === 0) { $row = $fr; break; } }
			if (!$row && $a['ref_suffix'] !== '') { foreach ($feedRows as $fr) if (strcasecmp(sizeKey($fr['size']), $a['ref_suffix']) === 0) { $row = $fr; break; } }
			if (!$row) { $plan['unmatched'][] = $model . ' (pa ' . $a['pa_id'] . ')'; continue; }
			list($vCost, $vPrice, $vG1) = priceForRow($row, $dealerMap);
			if ($vPrice <= 0) continue;
			$variants[] = ['pa_id' => $a['pa_id'], 'cost' => $vCost, 'price' => $vPrice, 'g1' => $vG1, 'cur_delta' => $a['cur_delta'], 'cur_prefix' => $a['cur_prefix']];
		}
		if (!$variants) { $plan['skipped_no_cost']++; continue; }

		usort($variants, fn($a, $b) => $a['price'] <=> $b['price']);
		$base = $variants[0];
		$newPrice = $base['price']; $newCost = $base['cost']; $newG1 = $base['g1'];
		// Sin dealer en el portal → NO pisar el coste con 0: conservar el actual.
		if ($newCost <= 0) { $newCost = $p['cost']; $newG1 = calcG1Price($newPrice, $newCost); }

		$dP = deltaPct($p['price'], $newPrice);
		$dC = ($p['cost'] > 0 && $newCost > 0) ? deltaPct($p['cost'], $newCost) : 0;
		if (!$applyExtremes && $maxChangeRatio > 0 && max($dP, $dC) > $maxChangeRatio) {
			$plan['extremes'][] = ['pid' => $pid, 'model' => $model, 'why' => 'variantes (' . count($variants) . ')',
				'old_price' => $p['price'], 'new_price' => $newPrice, 'old_cost' => $p['cost'], 'new_cost' => $newCost];
			continue;
		}

		if (aboveThreshold($newPrice, $p['price']) || ($newCost > 0 && aboveThreshold($newCost, $p['cost']))) {
			$plan['updates_product'][] = ['pid' => $pid, 'model' => $model, 'old_price' => $p['price'], 'new_price' => $newPrice, 'old_cost' => $p['cost'], 'new_cost' => $newCost];
		}
		$oldG1 = $g1prods[$pid] ?? null;
		if ($oldG1 === null) $plan['inserts_g1_product'][] = ['pid' => $pid, 'price' => $newG1];
		elseif (aboveThreshold($newG1, $oldG1)) $plan['updates_g1_product'][] = ['pid' => $pid, 'old' => $oldG1, 'new' => $newG1];

		foreach ($variants as $v) {
			$delta  = round($v['price'] - $newPrice, 4);
			$prefix = $delta < 0 ? '-' : '+';
			$curSig = ($v['cur_prefix'] === '-' ? -1 : 1) * $v['cur_delta'];
			if (aboveThreshold($delta, $curSig) || ($prefix !== $v['cur_prefix'] && abs($delta - $curSig) > 0.0001)) {
				$plan['updates_attr'][] = ['pa_id' => $v['pa_id'], 'pid' => $pid, 'old' => $curSig, 'new' => $delta, 'abs' => abs($delta), 'prefix' => $prefix];
			}
			$g1Delta  = round($v['g1'] - $newG1, 4);
			$g1Prefix = $g1Delta < 0 ? '-' : '+';
			$cur = $g1attrs[$v['pa_id']] ?? null;
			if ($cur === null) {
				$plan['inserts_g1_attr'][] = ['pa_id' => $v['pa_id'], 'pid' => $pid, 'abs' => abs($g1Delta), 'prefix' => $g1Prefix];
			} else {
				$curSigG1 = ($cur['prefix'] === '-' ? -1 : 1) * $cur['delta'];
				if (aboveThreshold($g1Delta, $curSigG1) || ($g1Prefix !== $cur['prefix'] && abs($g1Delta - $curSigG1) > 0.0001)) {
					$plan['updates_g1_attr'][] = ['pa_id' => $v['pa_id'], 'pid' => $pid, 'old' => $curSigG1, 'new' => $g1Delta, 'abs' => abs($g1Delta), 'prefix' => $g1Prefix];
				}
			}
		}
	}
	return $plan;
}

function applyPlan(array $plan) {
	foreach ($plan['updates_product'] as $u)
		tep_db_query("UPDATE products SET products_price = " . fmt4($u['new_price']) . ", products_cost = " . fmt4($u['new_cost']) . ", products_last_modified = NOW() WHERE products_id = " . (int) $u['pid']);
	foreach ($plan['updates_attr'] as $u)
		tep_db_query("UPDATE products_attributes SET options_values_price = " . fmt4($u['abs']) . ", price_prefix = '" . $u['prefix'] . "', attributes_last_modified = NOW() WHERE products_attributes_id = " . (int) $u['pa_id']);
	foreach ($plan['updates_g1_product'] as $u)
		tep_db_query("UPDATE products_groups SET customers_group_price = " . fmt4($u['new']) . " WHERE products_id = " . (int) $u['pid'] . " AND customers_group_id = " . G1_GROUP_ID);
	foreach ($plan['inserts_g1_product'] as $u)
		tep_db_query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . G1_GROUP_ID . ", " . (int) $u['pid'] . ", " . fmt4($u['price']) . ", 1, 1)");
	foreach ($plan['updates_g1_attr'] as $u)
		tep_db_query("UPDATE products_attributes_groups SET options_values_price = " . fmt4($u['abs']) . ", price_prefix = '" . $u['prefix'] . "' WHERE products_attributes_id = " . (int) $u['pa_id'] . " AND customers_group_id = " . G1_GROUP_ID);
	foreach ($plan['inserts_g1_attr'] as $u)
		tep_db_query("INSERT INTO products_attributes_groups (products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id, options_values_weight, weight_prefix) VALUES (" . (int) $u['pa_id'] . ", " . G1_GROUP_ID . ", " . fmt4($u['abs']) . ", '" . $u['prefix'] . "', " . (int) $u['pid'] . ", 0, '+')");
}

/* ----------------------------- Caché dealer -------------------------------- */
/** Devuelve [productId => [size => dealer]] para los modelos dados; cachea en gzip. */
function fetchDealers(array $models, $refresh) {
	$cacheFile = JOBE_TMP_DIR . 'dealers.json.gz';
	$cache = [];
	if (!$refresh && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < DEALER_CACHE_TTL) {
		$raw = @gzdecode(file_get_contents($cacheFile));
		$cache = $raw ? (json_decode($raw, true) ?: []) : [];
		echo '<p class="small">Caché dealer: ' . count($cache) . ' modelos (' . date('H:i', filemtime($cacheFile)) . '). Marca "Refrescar" para re-consultar el portal.</p>';
		@flush();
		// completar modelos que falten en caché
		$missing = array_values(array_diff($models, array_keys($cache)));
		if (!$missing) return $cache;
		$models = $missing;
	}
	if (!jobeLogin()) { echo '<p style="color:#c33"><strong>Login Jobe falló</strong> — no se pueden refrescar precios dealer. Se usa lo que haya en caché.</p>'; @flush(); return $cache; }
	$n = 0; $total = count($models);
	foreach ($models as $m) {
		$d = jobeFetchDealer($m);
		if ($d !== null) $cache[$m] = $d;
		if (++$n % 25 === 0) { echo '<p class="small">Portal dealer: ' . $n . '/' . $total . '...</p>'; @flush(); }
	}
	@file_put_contents($cacheFile, gzencode(json_encode($cache, JSON_UNESCAPED_UNICODE), 6));
	echo '<p class="small">Portal dealer: ' . $total . ' consultados, ' . count($cache) . ' en caché.</p>'; @flush();
	return $cache;
}

/* ----------------------------- Render -------------------------------------- */
if (!is_dir(JOBE_TMP_DIR)) @mkdir(JOBE_TMP_DIR, 0775, true);
?>
<?php require THEME . 'html/header.php'; ?>
<style>
.jb-wrap { padding: 10px 20px; font-family: Arial, sans-serif; font-size: 13px; }
.jb-wrap h1 { font-size: 22px; margin: 8px 0 14px; }
.jb-wrap h2 { font-size: 16px; margin: 18px 0 8px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
.jb-wrap table { border-collapse: collapse; width: 100%; margin: 6px 0 14px; }
.jb-wrap th, .jb-wrap td { border: 1px solid #ddd; padding: 4px 8px; text-align: left; vertical-align: top; }
.jb-wrap th { background: #f0f0f0; }
.jb-wrap td.num { text-align: right; font-family: monospace; }
.jb-wrap .summary li { margin: 2px 0; }
.jb-wrap .btn { display: inline-block; padding: 8px 16px; border-radius: 4px; text-decoration: none; color: #fff; }
.jb-wrap .btn-run { background: #c33; } .jb-wrap .btn-back { background: #888; }
.jb-wrap .badge-dry { background: #fa3; color: #000; padding: 3px 8px; border-radius: 3px; font-weight: bold; }
.jb-wrap .badge-exec { background: #393; color: #fff; padding: 3px 8px; border-radius: 3px; font-weight: bold; }
.jb-wrap details summary { cursor: pointer; padding: 4px 0; font-weight: bold; }
.jb-wrap .small { color: #666; font-size: 11px; }
</style>
<div class="jb-wrap">
<h1>Actualizador de precios Jobe</h1>
<p>Modo: <span class="<?php echo $dryRun ? 'badge-dry' : 'badge-exec'; ?>"><?php echo $dryRun ? 'DRY RUN (no escribe)' : 'EJECUTANDO'; ?></span></p>
<?php
echo str_pad('<!-- pad -->', 4096) . "\n"; @flush();

$feed = parseJobeFeed(FEED_PATH);
if (!$feed) {
	echo '<p style="color:red"><strong>Feed jobe.xml no encontrado / vacío en ' . htmlspecialchars(FEED_PATH) . '</strong></p></div>';
	require THEME . 'html/footer.php'; require DIR_WS_INCLUDES . 'application_bottom.php'; exit;
}
echo '<p>Feed: <strong>' . count($feed) . '</strong> productId. <span class="small">' . htmlspecialchars(basename(FEED_PATH)) . ' (' . date('Y-m-d H:i', filemtime(FEED_PATH)) . ')</span></p>'; @flush();

$prods = loadProducts();
$attrs = loadAttributes(array_keys($prods));
foreach ($attrs as $pid => $arr) if (isset($prods[$pid])) $prods[$pid]['attrs'] = $arr;

// modelos a consultar en el portal = los que están en BD Y en el feed
$matchModels = [];
foreach ($prods as $p) if ($p['model'] !== '' && isset($feed[$p['model']])) $matchModels[$p['model']] = true;
$matchModels = array_keys($matchModels);
$nWith = 0; $nWithout = 0; foreach ($prods as $p) { if ($p['attrs']) $nWith++; else $nWithout++; }
echo '<p>Productos Jobe en BD: <strong>' . count($prods) . '</strong> (' . $nWithout . ' sueltos, ' . $nWith . ' con variantes). Con match en feed: <strong>' . count($matchModels) . '</strong>.</p>'; @flush();

$dealers = fetchDealers($matchModels, $refreshPortal);

$g1prods = loadG1Products(array_keys($prods));
$paIds = []; foreach ($attrs as $arr) foreach ($arr as $a) $paIds[] = $a['pa_id'];
$g1attrs = loadG1Attrs($paIds);

$t0 = microtime(true);
$plan = buildPlan($prods, $feed, $dealers, $g1prods, $g1attrs, $maxChangeRatio, $applyExtremes);
echo '<p>Plan calculado en ' . round(microtime(true) - $t0, 2) . 's.</p>';

// Bloque A: cómputo de specials a purgar (política V3 2026-07-17)
// Reformateamos $prods en formato esperado por jobeComputeBadSpecials (pid=>['price','model'])
$prodsForSpecials = [];
foreach ($prods as $pid => $p) $prodsForSpecials[(int) $pid] = ['price' => (float) $p['price'], 'model' => $p['model']];
$badSpecials = jobeComputeBadSpecials($prodsForSpecials, $plan, $applyExtremes);

if ($dryRun) {
	echo '<form method="get" style="background:#f5f5f5;padding:10px;border-radius:4px;margin-bottom:15px;">';
	echo '<strong>Tope de variación</strong>: excluir cambios &gt; <input type="number" name="max_change_pct" value="' . (int) $maxChangePct . '" min="0" max="500" step="1" style="width:60px;"> %';
	echo ' &nbsp; <label><input type="checkbox" name="apply_extremes" value="1"' . ($applyExtremes ? ' checked' : '') . '> Aplicar también extremos</label>';
	echo ' &nbsp; <label><input type="checkbox" name="only_extremes" value="1"' . ($onlyExtremes ? ' checked' : '') . '> Ver SOLO extremos</label>';
	echo ' &nbsp; <label><input type="checkbox" name="refresh_portal" value="1"' . ($refreshPortal ? ' checked' : '') . '> Refrescar precios dealer del portal</label>';
	echo ' &nbsp; <button type="submit" class="btn btn-back" style="background:#36c;">Recalcular plan</button>';
	echo '<br><span class="small">Si el price o cost del padre cambia más del %, el producto ENTERO se excluye. 0 = sin tope. Protege contra pack-vs-unidad y errores de feed/portal.</span>';
	echo '</form>';
}
?>
<h2>Resumen</h2>
<ul class="summary">
	<li>UPDATE <code>products</code> precio/coste: <strong><?php echo count($plan['updates_product']); ?></strong></li>
	<li>UPDATE <code>products_attributes</code> delta: <strong><?php echo count($plan['updates_attr']); ?></strong></li>
	<li>UPDATE / INSERT Grupo 1 (productos): <strong><?php echo count($plan['updates_g1_product']); ?></strong> / <strong><?php echo count($plan['inserts_g1_product']); ?></strong></li>
	<li>UPDATE / INSERT Grupo 1 (variantes): <strong><?php echo count($plan['updates_g1_attr']); ?></strong> / <strong><?php echo count($plan['inserts_g1_attr']); ?></strong></li>
	<?php if (!$applyExtremes && $maxChangeRatio > 0): ?>
	<li>⚠️ Productos EXTREMOS excluidos (&gt; <?php echo $maxChangePct; ?>%): <strong><?php echo count($plan['extremes']); ?></strong></li>
	<?php endif; ?>
	<?php if (!$applyExtremes): ?>
	<li>🗑️ Specials a BORRAR (solo de productos repreciados en este run): <strong><?php echo count($badSpecials); ?></strong><?php echo empty($badSpecials) ? ' (ninguno)' : ''; ?></li>
	<?php endif; ?>
	<li class="small">Sin match en feed (no se toca): <?php echo $plan['skipped_no_match']; ?> | sin coste/precio válido: <?php echo $plan['skipped_no_cost']; ?> | sin cambio significativo: <?php echo $plan['skipped_unchanged']; ?> | variantes sin match: <?php echo count($plan['unmatched']); ?></li>
</ul>
<?php
$GLOBALS['onlyExtremes'] = $onlyExtremes;
function renderTable($title, $rows, $cols, $limit = 200) {
	$n = count($rows);
	if ($n === 0) return;
	if (!empty($GLOBALS['onlyExtremes']) && stripos($title, 'EXTREMO') === false) return;
	if (!empty($GLOBALS['onlyExtremes'])) $limit = 1000000;
	echo '<details><summary>' . htmlspecialchars($title) . ' (' . $n . ($n > $limit ? ', primeros ' . $limit : '') . ')</summary>';
	echo '<table><tr>';
	foreach ($cols as $h) echo '<th>' . htmlspecialchars($h[0]) . '</th>';
	echo '</tr>';
	$i = 0;
	foreach ($rows as $r) {
		if ($i++ >= $limit) break;
		echo '<tr>';
		foreach ($cols as $c) {
			$cls = isset($c[2]) && $c[2] === 'num' ? ' class="num"' : '';
			echo '<td' . $cls . '>' . htmlspecialchars((string) $c[1]($r)) . '</td>';
		}
		echo '</tr>';
	}
	echo '</table></details>';
}

if (!empty($plan['extremes'])) {
	renderTable('⚠️ EXTREMOS — productos completos excluidos (pack-vs-unidad o error de feed/portal)', $plan['extremes'], [
		['pid', fn($r) => $r['pid']], ['model', fn($r) => $r['model']], ['tipo', fn($r) => $r['why']],
		['old_price', fn($r) => number_format($r['old_price'], 4), 'num'], ['new_price', fn($r) => number_format($r['new_price'], 4), 'num'],
		['Δ price %', fn($r) => number_format(deltaPct($r['old_price'], $r['new_price']) * 100, 1), 'num'],
		['old_cost', fn($r) => number_format($r['old_cost'], 4), 'num'], ['new_cost', fn($r) => number_format($r['new_cost'], 4), 'num'],
		['Δ cost %', fn($r) => number_format(deltaPct($r['old_cost'], $r['new_cost']) * 100, 1), 'num'],
	]);
}
if (!empty($badSpecials)) {
	jobeLogMsg(sprintf("--- 🗑️ Specials a BORRAR (repreciados en este run: %d) ---", count($badSpecials)));
	foreach ($badSpecials as $b) {
		jobeLogMsg(sprintf("  specials_id=%d pid=%d ref=%-14s PVP=%7.2f sp=%7.2f dto=%5.1f%% creado=%s expira=%s — %s",
			$b['specials_id'], $b['pid'], $b['ref'], $b['eff_price'] * 1.21, $b['sp_price'] * 1.21, $b['dto_pct'], $b['created'], $b['expires'], $b['reason']));
	}
	renderTable('🗑️ Specials a BORRAR (política V3: solo repreciados en este run)', $badSpecials, [
		['specials_id', fn($r) => $r['specials_id']],
		['pid', fn($r) => $r['pid']],
		['ref', fn($r) => $r['ref']],
		['PVP (IVA inc)', fn($r) => number_format($r['eff_price'] * 1.21, 2), 'num'],
		['sp (IVA inc)', fn($r) => number_format($r['sp_price'] * 1.21, 2), 'num'],
		['dto %', fn($r) => number_format($r['dto_pct'], 1), 'num'],
		['creado', fn($r) => $r['created']],
		['expira', fn($r) => $r['expires']],
		['motivo', fn($r) => $r['reason']],
	]);
}

renderTable('Cambios precio/coste de producto', $plan['updates_product'], [
	['pid', fn($r) => $r['pid']], ['model', fn($r) => $r['model']],
	['old_price', fn($r) => number_format($r['old_price'], 4), 'num'], ['new_price', fn($r) => number_format($r['new_price'], 4), 'num'],
	['old_cost', fn($r) => number_format($r['old_cost'], 4), 'num'], ['new_cost', fn($r) => number_format($r['new_cost'], 4), 'num'],
]);
renderTable('Cambios delta de variantes', $plan['updates_attr'], [
	['pa_id', fn($r) => $r['pa_id']], ['pid', fn($r) => $r['pid']],
	['old_delta', fn($r) => number_format($r['old'], 4), 'num'], ['new_delta', fn($r) => number_format($r['new'], 4), 'num'],
]);
renderTable('UPDATEs Grupo 1 (productos)', $plan['updates_g1_product'], [
	['pid', fn($r) => $r['pid']], ['old', fn($r) => number_format($r['old'], 4), 'num'], ['new', fn($r) => number_format($r['new'], 4), 'num'],
]);
renderTable('INSERTs Grupo 1 (productos)', $plan['inserts_g1_product'], [
	['pid', fn($r) => $r['pid']], ['price', fn($r) => number_format($r['price'], 4), 'num'],
]);
renderTable('UPDATEs Grupo 1 (variantes)', $plan['updates_g1_attr'], [
	['pa_id', fn($r) => $r['pa_id']], ['pid', fn($r) => $r['pid']], ['old', fn($r) => number_format($r['old'], 4), 'num'], ['new', fn($r) => number_format($r['new'], 4), 'num'],
]);
renderTable('INSERTs Grupo 1 (variantes)', $plan['inserts_g1_attr'], [
	['pa_id', fn($r) => $r['pa_id']], ['pid', fn($r) => $r['pid']], ['abs', fn($r) => number_format($r['abs'], 4), 'num'], ['prefix', fn($r) => $r['prefix']],
]);

if (!$dryRun) {
	$t0 = microtime(true);
	tep_db_query('START TRANSACTION');
	try {
		applyPlan($plan);
		// Bloque D: purga V3 de specials (solo de productos repreciados en este run cuando !apply_extremes)
		jobePurgeSpecials($badSpecials);
		tep_db_query('COMMIT');
		echo '<p style="color:#393;font-weight:bold">✔ Cambios aplicados en ' . round(microtime(true) - $t0, 2) . 's.</p>';
	} catch (\Throwable $e) {
		tep_db_query('ROLLBACK');
		echo '<p style="color:#c33;font-weight:bold">✘ Error: ' . htmlspecialchars($e->getMessage()) . ' — ROLLBACK.</p>';
	}
	echo '<p><a class="btn btn-back" href="' . tep_href_link('Actualizador_precios_jobe.php') . '">Volver a dry-run</a></p>';
} else {
	$total = count($plan['updates_product']) + count($plan['updates_attr']) + count($plan['updates_g1_product']) + count($plan['inserts_g1_product']) + count($plan['updates_g1_attr']) + count($plan['inserts_g1_attr']);
	if ($total > 0) {
		$execParams = 'execute=1&max_change_pct=' . (int) $maxChangePct . ($applyExtremes ? '&apply_extremes=1' : '');
		echo '<p><a class="btn btn-run" href="' . tep_href_link('Actualizador_precios_jobe.php', $execParams) . '" onclick="return confirm(\'¿Aplicar ' . $total . ' cambios a la base de datos?\')">Ejecutar ' . $total . ' cambios</a></p>';
	} else {
		echo '<p><em>No hay cambios que aplicar.</em></p>';
	}
}
?>
</div>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
