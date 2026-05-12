<?php
/**
 * Script optimizado para descarga SFTP y actualización masiva de stock FNI.
 * - Descarga Stock.csv vía SFTP a import/feed/FNI/.
 * - Filtro de seguridad: solo procesa productos con products_import_origin LIKE 'fni%'
 *   o cuyo manufacturers_id está en la lista dinámica derivada del CSV
 *   Tracciato_master_10.csv (los brands que distribuye FNI).
 * - Lógica de stock 3-way (convención francobordo):
 *     stockBD > 0 (stock real propio) → no tocar
 *     stockBD ≤ 0 y stockFeed > 0 → -100 (disponible vía proveedor)
 *     stockBD ≤ 0 y stockFeed ≤ 0 → -800 (descatalogado / no disponible)
 * - UPDATE masivo en lotes de 500 con CASE-WHEN.
 * - Modo dry-run: ?dry_run=1 (web) o --dry-run (CLI).
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('memory_limit', '512M');
set_time_limit(0);

// Compatibilidad CLI
if (php_sapi_name() === 'cli') {
    $_SERVER['HTTP_HOST']   = 'www.francobordo.com';
    $_SERVER['REQUEST_URI'] = '/';
    $_SERVER['SCRIPT_NAME'] = '/import-fni.php';
    $_SERVER['HTTPS']       = 'on';
    chdir(dirname(__FILE__));
}

include('includes/application_top.php');
require_once dirname(__FILE__) . '/includes/vendor/autoload.php';

use phpseclib3\Net\SFTP;

$timeStart = microtime(true);

// ─── Configuración ───────────────────────────────────────────────────────────
$sServer       = dirname(__FILE__);
$sftpHost      = "sftpclienti.fni.it";
$sftpPort      = 2222;
$sftpUser      = getenv('FTP_USER') ?: "maxstore";
$sftpPass      = getenv('FTP_PASS') ?: "zoopa9Ai";
$remoteFile    = "Stock.csv";
$localFile     = $sServer . "/import/feed/FNI/Stock.csv";
$tracciatoFile = $sServer . "/import/feed/FNI/Tracciato_master_10.csv";
$logFile       = $sServer . "/import/feed/FNI/import-fni.log";
$batchSize     = 500;

// Modo dry-run
$dryRun = (php_sapi_name() === 'cli')
    ? in_array('--dry-run', $argv ?? [], true)
    : (isset($_GET['dry_run']) || isset($_POST['dry_run']));

// ─── Log helper ──────────────────────────────────────────────────────────────
function logMsg($msg, $logFile) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    echo $line;
    @file_put_contents($logFile, $line, FILE_APPEND);
}

if (!is_dir(dirname($localFile))) {
    mkdir(dirname($localFile), 0755, true);
}

if ($dryRun) logMsg("=== MODO DRY-RUN — no se aplicarán cambios ===", $logFile);

// ─── 1. Descargar Stock.csv por SFTP ─────────────────────────────────────────
if (!$dryRun || !file_exists($localFile)) {
    logMsg("Descargando Stock.csv vía SFTP...", $logFile);
    try {
        $sftp = new SFTP($sftpHost, $sftpPort);
        if (!$sftp->login($sftpUser, $sftpPass)) {
            throw new Exception("Autenticación SFTP fallida.");
        }
        if (!$sftp->get($remoteFile, $localFile)) {
            throw new Exception("No se pudo descargar: $remoteFile");
        }
        logMsg("Archivo descargado: " . round(filesize($localFile) / 1024) . " KB", $logFile);
    } catch (Exception $e) {
        logMsg("ERROR SFTP: " . $e->getMessage(), $logFile);
        exit(1);
    }
} else {
    logMsg("Dry-run: usando Stock.csv local existente: " . round(filesize($localFile) / 1024) . " KB", $logFile);
}

// ─── 2. Construir lista dinámica de manufacturers FNI ────────────────────────
function buildFniManufacturerList($tracciatoFile, $logFile) {
    if (!file_exists($tracciatoFile)) {
        logMsg("AVISO: $tracciatoFile no existe; uso fallback (manufacturers_id de productos ya marcados origin=fni)", $logFile);
        $ids = [];
        $q = tep_db_query("SELECT DISTINCT manufacturers_id FROM products WHERE products_import_origin LIKE 'fni%' AND manufacturers_id > 0");
        while ($row = tep_db_fetch_array($q)) $ids[] = (int) $row['manufacturers_id'];
        return $ids;
    }
    $brands = [];
    $f = @fopen($tracciatoFile, 'r');
    if ($f === false) return [];
    stream_filter_append($f, 'convert.iconv.CP1252/UTF-8');
    while (($r = fgetcsv($f, 0, ';', chr(34), '')) !== false) {
        if (count($r) < 8) continue;
        $b = strtoupper(trim($r[7] ?? ''));
        if ($b !== '') $brands[$b] = true;
    }
    fclose($f);

    $ids = [];
    foreach (array_keys($brands) as $b) {
        $bEsc = tep_db_input($b);
        $q = tep_db_query('SELECT manufacturers_id FROM manufacturers WHERE UPPER(TRIM(manufacturers_name)) = "' . $bEsc . '" LIMIT 1');
        if ($row = tep_db_fetch_array($q)) $ids[] = (int) $row['manufacturers_id'];
    }
    return array_values(array_unique($ids));
}

$mfgIds = buildFniManufacturerList($tracciatoFile, $logFile);
logMsg("Manufacturers FNI detectados (lista dinámica): " . count($mfgIds), $logFile);

// ─── 3. Cargar productos FNI en memoria con filtro de seguridad ──────────────
$mfgList = empty($mfgIds) ? '0' : implode(',', array_map('intval', $mfgIds));
$securityFilter = "(p.products_import_origin LIKE 'fni%' OR p.manufacturers_id IN ($mfgList))";

logMsg("Cargando productos de la BD (filtro FNI)...", $logFile);
$dbProducts = [];
$q = tep_db_query("
    SELECT p.products_id, p.products_quantity, LCASE(p.products_model) AS model
    FROM products p
    WHERE p.products_status = 1 AND $securityFilter");
while ($row = tep_db_fetch_array($q)) {
    $dbProducts[$row['model']] = [
        'id'    => $row['products_id'],
        'stock' => (int) $row['products_quantity'],
    ];
}
logMsg("Productos en BD (filtrados): " . count($dbProducts), $logFile);

$dbAttributes = [];
$q = tep_db_query("
    SELECT pa.products_id, pa.options_id, pa.options_values_id,
           LCASE(pa.reference) AS reference,
           ps.products_stock_quantity
    FROM products_attributes pa
    INNER JOIN products p ON pa.products_id = p.products_id
    LEFT JOIN products_stock ps ON ps.products_id = pa.products_id
        AND ps.products_stock_attributes = CONCAT(pa.options_id, '-', pa.options_values_id)
    WHERE p.products_status = 1 AND $securityFilter");
while ($row = tep_db_fetch_array($q)) {
    $dbAttributes[$row['reference']] = [
        'products_id'       => $row['products_id'],
        'options_id'        => $row['options_id'],
        'options_values_id' => $row['options_values_id'],
        'stock'             => (int) $row['products_stock_quantity'],
    ];
}
logMsg("Atributos en BD (filtrados): " . count($dbAttributes), $logFile);

// ─── 4. Procesar Stock.csv y calcular cambios ────────────────────────────────
logMsg("Procesando Stock.csv...", $logFile);

/** Regla 3-way de stock (convención francobordo). */
function computeNewStock($currentStock, $stockFeed) {
    if ($currentStock > 0) return $currentStock;          // stock real propio: no tocar
    if ($stockFeed > 0)    return -100;                   // disponible vía proveedor
    return -800;                                          // no disponible
}

$updatesProducts   = [];
$updatesAttributes = [];
$lineCount         = 0;
$matchCount        = 0;

$fFile = fopen($localFile, 'r');
if (!$fFile) {
    logMsg("ERROR: No se pudo abrir $localFile", $logFile);
    exit(1);
}

while (($line = fgets($fFile)) !== false) {
    $lineCount++;
    $fields = explode(';', $line);

    if (count($fields) < 3 || empty(trim($fields[0]))) continue;

    $model    = strtolower(trim($fields[0]));
    $stockRaw = explode(',', $fields[2])[0];

    if (!is_numeric($stockRaw)) continue;

    $stockFeed = (int) $stockRaw;

    // Producto simple
    if (isset($dbProducts[$model])) {
        $currentStock = $dbProducts[$model]['stock'];
        $newStock     = computeNewStock($currentStock, $stockFeed);
        if ($newStock !== $currentStock) {
            $updatesProducts[$dbProducts[$model]['id']] = $newStock;
        }
        $matchCount++;
    }

    // Atributo
    if (isset($dbAttributes[$model])) {
        $attr         = $dbAttributes[$model];
        $currentStock = $attr['stock'];
        $newStock     = computeNewStock($currentStock, $stockFeed);
        if ($newStock !== $currentStock) {
            $key = $attr['products_id'] . '-' . $attr['options_id'] . '-' . $attr['options_values_id'];
            $updatesAttributes[$key] = [
                'products_id'       => $attr['products_id'],
                'options_id'        => $attr['options_id'],
                'options_values_id' => $attr['options_values_id'],
                'stock'             => $newStock,
            ];
        }
        $matchCount++;
    }
}
fclose($fFile);

logMsg("Líneas CSV procesadas: $lineCount | Coincidencias: $matchCount", $logFile);
logMsg("Productos a actualizar: " . count($updatesProducts), $logFile);
logMsg("Atributos a actualizar: " . count($updatesAttributes), $logFile);

// ─── 5. DRY-RUN o aplicar UPDATEs ────────────────────────────────────────────
if ($dryRun) {
    logMsg("=== DRY-RUN: no se aplican cambios ===", $logFile);
    $i = 0;
    foreach ($updatesProducts as $id => $stock) {
        if ($i++ >= 15) break;
        logMsg("  WOULD pid=$id stock=$stock", $logFile);
    }
    if (count($updatesProducts) > 15) logMsg("  …y " . (count($updatesProducts) - 15) . " más", $logFile);
    $elapsed = round(microtime(true) - $timeStart, 2);
    logMsg("✓ Dry-run completado en {$elapsed} segundos.", $logFile);
    exit(0);
}

if (count($updatesProducts) > 0) {
    logMsg("Actualizando productos en lotes de $batchSize...", $logFile);
    $chunks = array_chunk($updatesProducts, $batchSize, true);
    foreach ($chunks as $chunk) {
        $sql = 'UPDATE products SET products_quantity = CASE products_id ';
        foreach ($chunk as $id => $stock) {
            $sql .= 'WHEN ' . (int)$id . ' THEN ' . (int)$stock . ' ';
        }
        $sql .= 'END WHERE products_id IN (' . implode(',', array_map('intval', array_keys($chunk))) . ')';
        tep_db_query($sql);
    }
    logMsg("Productos actualizados: " . count($updatesProducts), $logFile);
}

if (count($updatesAttributes) > 0) {
    logMsg("Actualizando atributos...", $logFile);
    foreach ($updatesAttributes as $attr) {
        // NOTA: si la fila no existe en products_stock (LEFT JOIN devolvió NULL),
        // este UPDATE no afectará nada. Considerar INSERT...ON DUPLICATE KEY UPDATE
        // si en producción se detectan atributos que no se actualizan.
        tep_db_query(
            'UPDATE products_stock SET products_stock_quantity = ' . (int)$attr['stock'] . '
             WHERE products_id = ' . (int)$attr['products_id'] . '
               AND products_stock_attributes = "' . (int)$attr['options_id'] . '-' . (int)$attr['options_values_id'] . '"'
        );
    }
    logMsg("Atributos actualizados: " . count($updatesAttributes), $logFile);
}

// ─── 6. Resumen ──────────────────────────────────────────────────────────────
$elapsed = round(microtime(true) - $timeStart, 2);
logMsg("✓ Proceso completado en {$elapsed} segundos.", $logFile);
?>
