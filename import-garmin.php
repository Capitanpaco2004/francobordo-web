<?php
/**
 * Sincronización de stock Garmin a Francobordo.
 * - Descarga el priceList.csv de dealers.garmin.com vía SSO (mismas credenciales que el importador).
 * - Lee la columna "Stock Level":
 *      "Sin stock."     → stockFeed = 0
 *      "Disponibles"    → stockFeed > 0 (positivo)
 *      <número>         → ese número
 * - Filtro de seguridad: solo procesa productos con `products_import_origin LIKE 'garmin%'`
 *   o `manufacturers_id IN (5, 449, 589, 590)` (Garmin, Fusion, JL Audio, Clarion).
 * - Regla 3-way de stock:
 *      stockBD  > 0           → no tocar (stock real propio)
 *      stockBD  ≤ 0 + Disponibles → -100
 *      stockBD  ≤ 0 + Sin stock → -900
 * - UPDATE masivo en lotes de 500 con CASE-WHEN.
 * - Modo dry-run: ?dry_run=1 (web).
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('memory_limit', '512M');
set_time_limit(0);

include('includes/application_top.php');

$timeStart = microtime(true);

// ─── Configuración ───────────────────────────────────────────────────────────
$sServer       = dirname(__FILE__);
const GARMIN_DEALER_ID    = '18723608';
const GARMIN_USER         = 'f.rodriguez@francobordo.com';
const GARMIN_PASS         = 'Garmin0908';
const GARMIN_SSO_LOGIN    = 'https://sso.garmin.com/sso/signin?service=https%3A%2F%2Fdealers.garmin.com%2Fdrc%2F&source=https%3A%2F%2Fdealers.garmin.com%2Fdrc%2F';
const GARMIN_CSV_URL      = 'https://dealers.garmin.com/drc/priceList/download/csv?selectedDealerId=' . GARMIN_DEALER_ID;
const GARMIN_CSV_PATH     = '/import/Garmin/priceList.csv';
const GARMIN_COOKIE_FILE  = '/home/francobordo/public_html/import/Garmin/cookies.txt';
$logFile      = $sServer . '/import/Garmin/import-garmin.log';
$batchSize    = 500;

// Reglas de stock 3-way
const STOCK_AVAILABLE  = -100;  // stockBD<0 + feed Disponibles
const STOCK_NO_STOCK   = -800;  // stockBD<0 + feed Sin stock

// Manufacturer IDs Garmin (para filtro de seguridad)
const GARMIN_MFG_IDS = [5, 449, 589, 590]; // Garmin, Fusion, JL Audio, Clarion

$dryRun = isset($_GET['dry_run']) || isset($_POST['dry_run']);

// ─── Log helper ──────────────────────────────────────────────────────────────
function logMsg($msg, $logFile) {
	$line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
	echo $line;
	@file_put_contents($logFile, $line, FILE_APPEND);
	@flush();
}

if (!is_dir(dirname($logFile))) @mkdir(dirname($logFile), 0775, true);
if ($dryRun) logMsg('=== MODO DRY-RUN — no se aplicarán cambios ===', $logFile);

// ─── 1. Descarga CSV vía SSO (mismo flujo que _admin/import-garmin-altas.php) ─
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

function downloadGarminCsv($localPath, $logFile) {
	if (!is_dir(dirname($localPath))) @mkdir(dirname($localPath), 0775, true);
	@unlink(GARMIN_COOKIE_FILE);

	logMsg('SSO 1/3: GET signin (CSRF)…', $logFile);
	[$html, $code, $err] = garminCurl(GARMIN_SSO_LOGIN);
	if ($code !== 200 || !$html) { logMsg("ERROR signin: code=$code err=$err", $logFile); return false; }
	if (!preg_match('/name="_csrf"\s+value="([^"]+)"/', $html, $m)) { logMsg('ERROR: no _csrf en login form', $logFile); return false; }
	$csrf = $m[1];

	logMsg('SSO 2/3: POST credenciales…', $logFile);
	[$resp, $code, $err] = garminCurl(GARMIN_SSO_LOGIN, [
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => http_build_query([
			'username' => GARMIN_USER,
			'password' => GARMIN_PASS,
			'embed'    => 'false',
			'_csrf'    => $csrf,
		]),
		CURLOPT_HTTPHEADER => ['Origin: https://sso.garmin.com', 'Referer: ' . GARMIN_SSO_LOGIN],
	]);
	if ($code !== 200 || !$resp || stripos($resp, '<title>Success</title>') === false) {
		logMsg("ERROR login: code=$code (sin <title>Success</title>)", $logFile);
		return false;
	}
	if (!preg_match('/response_url\s*=\s*"([^"]+)"/', $resp, $tm)) { logMsg('ERROR: no response_url en Success', $logFile); return false; }
	$ticketUrl = stripslashes($tm[1]);

	logMsg('SSO 3/3: consumiendo ticket…', $logFile);
	garminCurl($ticketUrl);
	$cookieOk = file_exists(GARMIN_COOKIE_FILE) && strpos(file_get_contents(GARMIN_COOKIE_FILE), 'DRC_JWT') !== false;
	if (!$cookieOk) { logMsg('ERROR: ticket no devolvió DRC_JWT', $logFile); return false; }

	logMsg('Descargando priceList CSV…', $logFile);
	[$csv, $code, $err] = garminCurl(GARMIN_CSV_URL, [CURLOPT_TIMEOUT => 120]);
	if ($code !== 200 || !$csv) { logMsg("ERROR CSV download: code=$code err=$err", $logFile); return false; }
	if (strpos($csv, '<!doctype html') !== false || strpos($csv, '<html') === 0) { logMsg('ERROR CSV download devolvió HTML (auth perdida)', $logFile); return false; }

	$tmp = $localPath . '.tmp.' . uniqid();
	file_put_contents($tmp, $csv);
	if (filesize($tmp) < 10000) { @unlink($tmp); logMsg('ERROR CSV demasiado pequeño (' . filesize($tmp) . ' B)', $logFile); return false; }
	@rename($tmp, $localPath);
	logMsg('CSV descargado: ' . round(filesize($localPath) / 1024) . ' KB', $logFile);
	return true;
}

$localCsv = $sServer . GARMIN_CSV_PATH;
if (!downloadGarminCsv($localCsv, $logFile)) {
	if (!file_exists($localCsv)) { logMsg('ERROR: no hay copia local y SSO falló — aborto', $logFile); exit(1); }
	logMsg('AVISO: usando copia local existente', $logFile);
}

// ─── 2. Cargar productos FNI con filtro de seguridad ─────────────────────────
$mfgIds = implode(',', array_map('intval', GARMIN_MFG_IDS));
$securityFilter = "(p.products_import_origin LIKE 'garmin%' OR p.manufacturers_id IN ($mfgIds))";

logMsg('Cargando productos Garmin de la BD (filtro de seguridad)…', $logFile);
$dbProducts = [];
$q = tep_db_query("
	SELECT p.products_id, p.products_quantity, LCASE(p.products_model) AS model
	FROM products p
	WHERE $securityFilter
	  AND p.products_model <> ''
");
while ($row = tep_db_fetch_array($q)) {
	$dbProducts[$row['model']] = ['id' => (int) $row['products_id'], 'stock' => (int) $row['products_quantity']];
}
logMsg('Productos en BD (filtrados): ' . count($dbProducts), $logFile);

// Atributos (por si en el futuro Garmin tuviera variantes; ahora suele ser 0)
$dbAttributes = [];
$q = tep_db_query("
	SELECT pa.products_id, pa.options_id, pa.options_values_id,
	       LCASE(pa.reference) AS reference,
	       ps.products_stock_quantity
	FROM products_attributes pa
	INNER JOIN products p ON pa.products_id = p.products_id
	LEFT JOIN products_stock ps ON ps.products_id = pa.products_id
	    AND ps.products_stock_attributes = CONCAT(pa.options_id, '-', pa.options_values_id)
	WHERE $securityFilter
");
while ($row = tep_db_fetch_array($q)) {
	$dbAttributes[$row['reference']] = [
		'products_id'       => (int) $row['products_id'],
		'options_id'        => (int) $row['options_id'],
		'options_values_id' => (int) $row['options_values_id'],
		'stock'             => (int) ($row['products_stock_quantity'] ?? 0),
	];
}
logMsg('Atributos en BD (filtrados): ' . count($dbAttributes), $logFile);

// ─── 3. Helpers parseo CSV Garmin ────────────────────────────────────────────
/**
 * Parsea el campo Stock Level del CSV.
 * Devuelve un entero >= 0:
 *   "Sin stock." (case-insensitive, con/sin punto)  → 0
 *   "Disponibles" o variantes truthy                 → 1 (positivo simbólico)
 *   <número>                                          → ese número
 *   vacío / desconocido                              → -1 (indica skip)
 */
function parseGarminStockLevel($raw) {
	$v = trim((string) $raw);
	if ($v === '') return -1;
	$lower = mb_strtolower($v, 'UTF-8');
	if (strpos($lower, 'sin stock') === 0 || $lower === 'sin stock' || $lower === 'sin stock.') return 0;
	if (strpos($lower, 'disponibles') === 0 || strpos($lower, 'disponible') === 0 || strpos($lower, 'en existencias') === 0) return 1;
	// número directo (raro)
	$num = preg_replace('/[^0-9\-]/', '', $v);
	if ($num !== '' && is_numeric($num)) return (int) $num;
	return -1;
}

// ─── 4. Procesar CSV y calcular cambios ──────────────────────────────────────
logMsg('Procesando priceList.csv…', $logFile);

$updatesProducts   = []; // products_id => newStock
$updatesAttributes = []; // key => [products_id, options_id, options_values_id, stock]
$lineCount = 0;
$matchProd = 0; $matchAttr = 0; $skippedFeedNeg = 0;

$f = fopen($localCsv, 'r');
if (!$f) { logMsg('ERROR: no se pudo abrir ' . $localCsv, $logFile); exit(1); }
fgetcsv($f, 0, ',', '"', ''); // disclaimer línea 1
$header = fgetcsv($f, 0, ',', '"', '');
if (!$header) { fclose($f); logMsg('ERROR: cabecera CSV no leída', $logFile); exit(1); }
$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]); // strip BOM
$idxSku   = array_search('ItemNo/Part Number', $header, true);
$idxStock = array_search('Stock Level', $header, true);
if ($idxSku === false || $idxStock === false) {
	logMsg('ERROR: columnas requeridas no encontradas (ItemNo/Part Number y Stock Level)', $logFile);
	fclose($f); exit(1);
}

while (($row = fgetcsv($f, 0, ',', '"', '')) !== false) {
	$lineCount++;
	$sku = strtolower(trim($row[$idxSku] ?? ''));
	if ($sku === '') continue;
	$stockFeed = parseGarminStockLevel($row[$idxStock] ?? '');
	if ($stockFeed < 0) { $skippedFeedNeg++; continue; }

	// Producto suelto
	if (isset($dbProducts[$sku])) {
		$cur = $dbProducts[$sku]['stock'];
		// Regla 3-way: solo aplicar si stockBD < 0 (estricto). >0 o =0 → no tocar.
		if ($cur <= 0) {
			$new = ($stockFeed > 0) ? STOCK_AVAILABLE : STOCK_NO_STOCK;
			if ($new !== $cur) $updatesProducts[$dbProducts[$sku]['id']] = $new;
		}
		$matchProd++;
	}

	// Atributo
	if (isset($dbAttributes[$sku])) {
		$attr = $dbAttributes[$sku];
		$cur = $attr['stock'];
		if ($cur <= 0) {
			$new = ($stockFeed > 0) ? STOCK_AVAILABLE : STOCK_NO_STOCK;
			if ($new !== $cur) {
				$key = $attr['products_id'] . '-' . $attr['options_id'] . '-' . $attr['options_values_id'];
				$updatesAttributes[$key] = [
					'products_id' => $attr['products_id'],
					'options_id'  => $attr['options_id'],
					'options_values_id' => $attr['options_values_id'],
					'stock' => $new,
				];
			}
		}
		$matchAttr++;
	}
}
fclose($f);

logMsg("Líneas CSV: $lineCount | match productos: $matchProd | match atributos: $matchAttr | feed sin valor: $skippedFeedNeg", $logFile);
logMsg('Productos a actualizar: ' . count($updatesProducts), $logFile);
logMsg('Atributos a actualizar: ' . count($updatesAttributes), $logFile);

// ─── 5. DRY-RUN o aplicar UPDATEs ────────────────────────────────────────────
if ($dryRun) {
	logMsg('=== DRY-RUN: no se aplican cambios ===', $logFile);
	$cnt = 0;
	foreach ($updatesProducts as $pid => $st) {
		if ($cnt++ >= 15) break;
		logMsg("  WOULD UPDATE pid=$pid → stock=$st", $logFile);
	}
	if (count($updatesProducts) > 15) logMsg('  …y ' . (count($updatesProducts) - 15) . ' productos más', $logFile);
	logMsg('✓ Dry-run completado en ' . round(microtime(true) - $timeStart, 2) . ' s', $logFile);
	exit(0);
}

if (count($updatesProducts) > 0) {
	logMsg("Actualizando productos en lotes de $batchSize…", $logFile);
	$chunks = array_chunk($updatesProducts, $batchSize, true);
	foreach ($chunks as $chunk) {
		$sql = 'UPDATE products SET products_quantity = CASE products_id ';
		foreach ($chunk as $id => $st) $sql .= 'WHEN ' . (int) $id . ' THEN ' . (int) $st . ' ';
		$sql .= 'END WHERE products_id IN (' . implode(',', array_map('intval', array_keys($chunk))) . ')';
		tep_db_query($sql);
	}
	logMsg('Productos actualizados: ' . count($updatesProducts), $logFile);
}

if (count($updatesAttributes) > 0) {
	logMsg('Actualizando atributos…', $logFile);
	foreach ($updatesAttributes as $attr) {
		tep_db_query(
			'UPDATE products_stock SET products_stock_quantity = ' . (int) $attr['stock'] . '
			 WHERE products_id = ' . (int) $attr['products_id'] . '
			   AND products_stock_attributes = "' . (int) $attr['options_id'] . '-' . (int) $attr['options_values_id'] . '"'
		);
	}
	logMsg('Atributos actualizados: ' . count($updatesAttributes), $logFile);
}

logMsg('✓ Proceso completado en ' . round(microtime(true) - $timeStart, 2) . ' s', $logFile);
?>
