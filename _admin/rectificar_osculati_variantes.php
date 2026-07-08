<?php
require 'includes/application_top.php';

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);

const OSC_FEED_DIR  = __DIR__ . '/import-osculati/';
const ITEM_FEED     = OSC_FEED_DIR . 'ItemPrice4Web.txt';
const VARIANT_OPTION_ID = 3;
const TAX_CLASS_IVA21 = 1;
const NEW_CATEGORY_ID = 1699;
const LANG_ID_ES      = 3;
const LANG_ID_EN      = 1;

$action  = $_POST['action'] ?? $_GET['action'] ?? '';
$dryRun  = !isset($_POST['confirm_execute']) && !isset($_GET['confirm_execute']);
$isAction = ($action === 'plan' || $action === 'execute');

function logMsg($msg) {
	$line = '[' . date('H:i:s') . '] ' . $msg . "\n";
	echo '<pre style="margin:0;padding:2px 8px;border-bottom:1px solid #eee;white-space:pre-wrap;overflow-wrap:break-word;word-break:break-word;font-family:monospace;font-size:12px;">' . htmlspecialchars($line) . '</pre>';
	@flush();
}

function extractMeasureFromTitle($title) {
	$title = (string) $title;
	if (preg_match('/(\d+(?:[.,]\d+)?\s*-\s*\d+(?:[.,]\d+)?)\s*(kg|g|mm|cm|mt|m|l|ml|HP|W|V|A|Hz|°)\b/iu', $title, $m)) {
		return trim($m[1]) . ' ' . $m[2];
	}
	if (preg_match('/(\d+(?:[.,]\d+)?)\s*(kg|g|mm|cm|mt|m|l|ml|in|inch|"|HP|W|V|A|Hz|°|fl\s*oz|oz)\b/iu', $title, $m)) {
		return $m[1] . ' ' . $m[2];
	}
	return '';
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

function loadFeedItemMap($path) {
	if (!file_exists($path)) return [];
	$raw = file_get_contents($path);
	$utf8 = mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE');
	$utf8 = ltrim($utf8, "\xEF\xBB\xBF\xFF\xFE");
	$lines = preg_split("/\r\n|\r|\n/", $utf8);
	$map = [];
	foreach ($lines as $line) {
		if ($line === '') continue;
		$r = explode("\t", $line);
		if (count($r) < 14) continue;
		$orderCode = trim($r[0]);
		if ($orderCode === '') continue;
		$map[$orderCode] = [
			'order_code' => $orderCode,
			'base_code'  => trim($r[1]),
			'brand'      => trim($r[8] ?? ''),
			'desc_it'    => trim($r[9] ?? ''),
			'desc_en'    => trim($r[10] ?? ''),
			'ean'        => trim($r[6] ?? ''),
			'street_price' => (int) ($r[13] ?? 0),
			'list_price' => (int) ($r[14] ?? 0),
			'base_qty'   => (int) ($r[2] ?? 1),
			'weight_g'   => (int) ($r[15] ?? 0),
		];
	}
	return $map;
}

function findOrCreateOptionValue($mysqli, $label) {
	$qLabel = $mysqli->real_escape_string($label);
	$r = $mysqli->query("SELECT pov.products_options_values_id FROM products_options_values pov
		JOIN products_options_values_to_products_options pv2po ON pv2po.products_options_values_id = pov.products_options_values_id
		WHERE pov.products_options_values_name = '$qLabel' AND pov.language_id = " . LANG_ID_ES . "
		  AND pv2po.products_options_id = " . VARIANT_OPTION_ID . " LIMIT 1");
	if ($row = $r->fetch_assoc()) return (int) $row['products_options_values_id'];

	// products_options_values_id NO es AUTO_INCREMENT en este esquema → calcular MAX+1 manualmente
	$rid = $mysqli->query("SELECT IFNULL(MAX(products_options_values_id), 0) + 1 AS nid FROM products_options_values");
	$newId = (int) ($rid->fetch_assoc()['nid'] ?? 1);

	$mysqli->query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($newId, " . LANG_ID_ES . ", '$qLabel', '')");
	$mysqli->query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($newId, " . LANG_ID_EN . ", '$qLabel', '')");
	$mysqli->query("INSERT INTO products_options_values_to_products_options (products_options_id, products_options_values_id) VALUES (" . VARIANT_OPTION_ID . ", $newId)");
	return $newId;
}

/**
 * Calcula el nuevo label para una variante.
 * Orden de preferencia:
 *  1. Medida extraída del título IT (kg, mm, cm, V, HP, ...). Lo más fiable.
 *  2. Sufijo tras el prefijo común (si no es código y no empieza con caracteres residuos típicos).
 *  3. Fallback (código del producto).
 */
function computeNewLabel($titleIt, $commonPrefix, $fallback) {
	// 1) Medida directa
	$measure = extractMeasureFromTitle($titleIt);
	if ($measure !== '') return mb_substr($measure, 0, 64, 'UTF-8');

	// 2) Sufijo tras prefijo común
	if ($commonPrefix !== '' && mb_strpos($titleIt, $commonPrefix) === 0) {
		$cand = trim(mb_substr($titleIt, mb_strlen($commonPrefix, 'UTF-8'), null, 'UTF-8'));
		// Aceptar solo si parece "limpio": empieza por dígito o palabra completa, no por sílaba residuo
		if ($cand !== '' && !preg_match('/^\d+\.\d+\.\d+/', $cand) && preg_match('/^[A-Z0-9]/iu', $cand)) {
			return mb_substr($cand, 0, 64, 'UTF-8');
		}
	}

	// 3) Fallback
	return mb_substr($fallback, 0, 64, 'UTF-8');
}

/** Renombra labels de un grupo de variantes (mismo producto, mismo brand). Detecta colisiones (varias variantes que iban al mismo label) y las salta. */
function renameLabels($mysqli, $pid, $variants, $dryRun) {
	$itTitles = [];
	foreach ($variants as $v) if ($v['feed']) $itTitles[] = $v['feed']['desc_it'];
	$commonPrefix = preg_replace('/[\s\-–·,]+$/u', '', longestCommonPrefix($itTitles));

	// Calcular labels propuestos
	$proposed = [];
	foreach ($variants as $v) {
		if (!$v['feed']) continue;
		$proposed[$v['pa_id']] = computeNewLabel($v['feed']['desc_it'], $commonPrefix, $v['feed']['base_code']);
	}
	$usage = array_count_values($proposed);

	$count = 0; $skippedCol = 0;
	foreach ($variants as $v) {
		if (!$v['feed']) continue;
		$newLabel = $proposed[$v['pa_id']] ?? '';
		if ($usage[$newLabel] > 1) {
			$skippedCol++;
			continue; // colisión: conservar lo que tenía
		}
		if ($newLabel === '' || $newLabel === $v['label']) continue;
		logMsg("      {$v['reference']}  '{$v['label']}' → '$newLabel'");
		if (!$dryRun) {
			$newOvId = findOrCreateOptionValue($mysqli, $newLabel);
			$mysqli->query("UPDATE products_attributes SET options_values_id=$newOvId WHERE products_attributes_id={$v['pa_id']}");
			$mysqli->query("UPDATE products_stock SET products_stock_attributes='" . VARIANT_OPTION_ID . "-$newOvId' WHERE products_id=$pid AND products_stock_attributes='" . VARIANT_OPTION_ID . "-{$v['ov_id']}'");
		}
		$count++;
	}
	if ($skippedCol > 0) logMsg("      ($skippedCol variantes saltadas por colisión de label — conservan código)");
	return $count;
}

/** Saca las variantes de un brand a un producto NUEVO (split). */
function splitOffBrandIntoNewProduct($mysqli, $oldPid, $oldRow, $brand, $varsBrand, $dryRun) {
	$brandDisplay = mb_convert_case(mb_strtolower($brand, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
	$first = $varsBrand[0]['feed'];
	$baseQty = max(1, (int) $first['base_qty']);
	$baseCost  = (float) $first['list_price'] / 100 / $baseQty;
	$basePrice = (float) $first['street_price'] / 100 / $baseQty / 1.21;
	$baseWeight = max(0.001, $first['weight_g'] / 1000.0 / $baseQty);
	$titleIt = $first['desc_it'];
	$titleEn = $first['desc_en'];
	$nameEs = mb_substr($titleEn !== '' ? $titleEn : $titleIt, 0, 80, 'UTF-8');

	if ($dryRun) {
		logMsg("      WOULD CREATE producto '$brandDisplay' (mfg) con " . count($varsBrand) . " variantes, ej. nombre ES='$nameEs'");
		$itTitles = array_filter(array_map(fn($v)=>$v['feed']['desc_it'] ?? '', $varsBrand));
		$commonPrefix = preg_replace('/[\s\-–·,]+$/u', '', longestCommonPrefix(array_values($itTitles)));
		foreach ($varsBrand as $v) {
			if (!$v['feed']) continue;
			$nl = computeNewLabel($v['feed']['desc_it'], $commonPrefix, $v['feed']['base_code']);
			logMsg("        WOULD MOVE {$v['reference']} → label='$nl'");
		}
		return;
	}

	// Resolver/crear manufacturer
	$qb = $mysqli->real_escape_string($brand);
	$rb = $mysqli->query("SELECT manufacturers_id FROM manufacturers WHERE UPPER(TRIM(manufacturers_name)) = '$qb' LIMIT 1");
	if ($row = $rb->fetch_assoc()) {
		$mfgId = (int) $row['manufacturers_id'];
	} else {
		$qbd = $mysqli->real_escape_string($brandDisplay);
		$mysqli->query("INSERT INTO manufacturers (manufacturers_name, date_added) VALUES ('$qbd', NOW())");
		$mfgId = (int) $mysqli->insert_id;
		$mysqli->query("INSERT INTO manufacturers_info (manufacturers_id, languages_id, manufacturers_url) VALUES ($mfgId, " . LANG_ID_ES . ", '')");
		$mysqli->query("INSERT INTO manufacturers_info (manufacturers_id, languages_id, manufacturers_url) VALUES ($mfgId, " . LANG_ID_EN . ", '')");
		logMsg("        manufacturer creado: $brandDisplay (id=$mfgId)");
	}

	$qmodel = $mysqli->real_escape_string($first['base_code']);
	$qean = $mysqli->real_escape_string($first['ean'] ?? '');
	$mysqli->query("INSERT INTO products
		(products_quantity, check_stock, products_model, products_image, products_price, products_cost,
		 products_date_added, products_weight, products_status, products_tax_class_id, manufacturers_id,
		 product_ean, reference_prov, products_import_origin)
		VALUES (0, 0, '$qmodel', '', " . number_format($basePrice, 4, '.', '') . ", " . number_format($baseCost, 4, '.', '') . ",
		 NOW(), " . number_format($baseWeight, 3, '.', '') . ", 2, " . TAX_CLASS_IVA21 . ", $mfgId,
		 '$qean', '$qmodel', 'osculati-altas-split')");
	$newPid = (int) $mysqli->insert_id;

	$qNameEs = $mysqli->real_escape_string($nameEs);
	$qNameEn = $mysqli->real_escape_string($nameEs);
	$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($newPid, " . LANG_ID_ES . ", '$qNameEs', '', 0)");
	$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($newPid, " . LANG_ID_EN . ", '$qNameEn', '', 0)");
	$mysqli->query("INSERT INTO products_to_categories (products_id, categories_id) VALUES ($newPid, " . NEW_CATEGORY_ID . ")");
	logMsg("        producto creado pid=$newPid '$nameEs'");

	$itTitles = array_filter(array_map(fn($v)=>$v['feed']['desc_it'] ?? '', $varsBrand));
	$commonPrefix = preg_replace('/[\s\-–·,]+$/u', '', longestCommonPrefix(array_values($itTitles)));
	foreach ($varsBrand as $v) {
		if (!$v['feed']) continue;
		$newLabel = computeNewLabel($v['feed']['desc_it'], $commonPrefix, $v['feed']['base_code']);
		$newOvId = findOrCreateOptionValue($mysqli, $newLabel);
		$mysqli->query("UPDATE products_attributes SET products_id=$newPid, options_values_id=$newOvId WHERE products_attributes_id={$v['pa_id']}");
		$mysqli->query("UPDATE products_stock SET products_id=$newPid, products_stock_attributes='" . VARIANT_OPTION_ID . "-$newOvId' WHERE products_id=$oldPid AND products_stock_attributes='" . VARIANT_OPTION_ID . "-{$v['ov_id']}'");
		logMsg("        MOVED {$v['reference']} → pid=$newPid label='$newLabel'");
	}
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
<h2>Rectificar variantes Osculati con label = código</h2>
<p>
	Detecta productos Osculati cuyas variantes tienen como nombre el código Osculati (<code>NN.NNN.NN</code>).
	Para cada uno: si la serie tiene <strong>marcas distintas</strong>, split en productos separados (uno por marca).
	Si no, recalcula la etiqueta extrayendo medida del título IT (<code>3,2 kg</code>, <code>685 mm</code>…).
</p>

<?php if (!$isAction): ?>
<form method="post" style="background:#f5f5f5;padding:15px;border-radius:5px;">
	<p><label><input type="checkbox" name="confirm_execute" value="1"> Aplicar cambios (sin marcar = solo PLAN/dry-run)</label></p>
	<input type="hidden" name="action" value="plan">
	<button type="submit" name="action" value="plan" class="xbutton small hv9">Generar plan (dry-run)</button>
	<button type="submit" name="action" value="execute" class="xbutton small hv9 verde" onclick="return this.form.confirm_execute.checked || (alert('Marca la casilla \'Aplicar cambios\' antes de ejecutar.'), false);">Ejecutar</button>
</form>
</div></td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
<?php exit; ?>
<?php endif; ?>

<p><a href="<?php echo tep_href_link('rectificar_osculati_variantes.php'); ?>" class="xbutton small hv9">← Volver</a></p>
<div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n";
@flush();

logMsg("Modo: " . ($dryRun ? "PLAN (dry-run)" : "EXECUTE"));

if (!file_exists(ITEM_FEED)) {
	logMsg("ERROR: feed Osculati no encontrado en " . ITEM_FEED);
	logMsg("Solución: descárgalo primero ejecutando el importador (incluso un dry-run con max=0).");
	goto end_action;
}

logMsg("Cargando feed Osculati ItemPrice4Web.txt…");
$feedMap = loadFeedItemMap(ITEM_FEED);
logMsg("OrderCodes en feed: " . count($feedMap));

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
$mysqli->set_charset('utf8');

// Detectar dos síntomas: (a) label parece código Osculati; (b) options_values_id=0 (legado de un bug previo).
$r = $mysqli->query("
	SELECT DISTINCT p.products_id, p.manufacturers_id, p.products_image, p.products_price, p.products_cost,
	       MAX(pd.products_name) AS nombre_es, p.products_import_origin
	FROM products p
	JOIN products_attributes pa ON pa.products_id = p.products_id
	LEFT JOIN products_options_values pov ON pa.options_values_id = pov.products_options_values_id AND pov.language_id = " . LANG_ID_ES . "
	LEFT JOIN products_description pd ON pd.products_id = p.products_id AND pd.language_id = " . LANG_ID_ES . "
	WHERE p.products_import_origin LIKE 'osculati%'
	  AND ( pov.products_options_values_name LIKE '__.___.__%' OR pa.options_values_id = 0 )
	GROUP BY p.products_id");
$prods = [];
while ($row = $r->fetch_assoc()) $prods[(int) $row['products_id']] = $row;
logMsg("Productos afectados: " . count($prods));

$totalSplit = 0; $totalRename = 0; $totalRenameValues = 0;

foreach ($prods as $pid => $p) {
	logMsg("");
	logMsg("=== pid=$pid : " . $p['nombre_es'] . " ===");

	$r2 = $mysqli->query("
		SELECT pa.products_attributes_id, pa.options_values_id, pa.reference, pov.products_options_values_name AS label
		FROM products_attributes pa
		LEFT JOIN products_options_values pov ON pa.options_values_id = pov.products_options_values_id AND pov.language_id = " . LANG_ID_ES . "
		WHERE pa.products_id = $pid
		ORDER BY pa.products_attributes_id");
	$variants = [];
	while ($vr = $r2->fetch_assoc()) {
		$variants[] = [
			'pa_id' => (int) $vr['products_attributes_id'],
			'ov_id' => (int) $vr['options_values_id'],
			'reference' => $vr['reference'],
			'label' => $vr['label'] ?? '',
			'feed' => $feedMap[$vr['reference']] ?? null,
		];
	}
	logMsg("  Variantes: " . count($variants));

	$brands = [];
	foreach ($variants as $v) {
		if (!$v['feed']) continue;
		$b = strtoupper(trim($v['feed']['brand']));
		if ($b !== '') $brands[$b][] = $v;
	}
	$missingFeed = count($variants) - array_sum(array_map('count', $brands));
	if ($missingFeed > 0) logMsg("  AVISO: $missingFeed variantes sin datos en feed.");
	if (empty($brands)) { logMsg("  SKIP: ninguna variante tiene info en el feed."); continue; }

	if (count($brands) === 1) {
		$brand = array_key_first($brands);
		logMsg("  Brand único: $brand. Recalculando labels.");
		$cnt = renameLabels($mysqli, $pid, $brands[$brand], $dryRun);
		$totalRenameValues += $cnt;
		if ($cnt > 0) $totalRename++;
	} else {
		$totalSplit++;
		$counts = array_map('count', $brands);
		arsort($counts);
		$mainBrand = array_key_first($counts);
		logMsg("  Serie MIXTA con " . count($brands) . " marcas: " . implode(', ', array_keys($brands)) . ". Brand mayoritario que se queda en pid=$pid: $mainBrand");

		foreach ($brands as $brand => $varsBrand) {
			if ($brand === $mainBrand) {
				logMsg("    [pid=$pid] $brand · " . count($varsBrand) . " variantes (rename labels)");
				$totalRenameValues += renameLabels($mysqli, $pid, $varsBrand, $dryRun);
			} else {
				logMsg("    [NUEVO] $brand · " . count($varsBrand) . " variantes (crear producto nuevo y mover)");
				splitOffBrandIntoNewProduct($mysqli, $pid, $p, $brand, $varsBrand, $dryRun);
			}
		}
	}
}

logMsg("");
logMsg("==================== RESUMEN ====================");
logMsg("Productos splitteados (serie mixta): $totalSplit");
logMsg("Productos con labels renombrados: $totalRename");
logMsg("Total variantes renombradas: $totalRenameValues");
if ($dryRun) logMsg("=== Dry-run finalizado. No se ha tocado nada. ===");

end_action:
?>
</div>
<p style="margin-top:15px;"><a href="<?php echo tep_href_link('rectificar_osculati_variantes.php'); ?>" class="xbutton small hv9">← Volver</a></p>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
