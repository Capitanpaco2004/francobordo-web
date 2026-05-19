<?php
// Optimizacion 2026-05-18:
//  - Lectura CSV nativa (sin clase import), buffer ilimitado, header-based + validacion estricta
//    de cabecera (aborta 500 si Minderest reordena columnas).
//  - SELECTs scoped al CSV (IN(...)) en lugar de cargar 33k productos + 41k atributos a memoria.
//  - Comparaciones float con epsilon (evita UPDATEs espurios que invalidan caches).
//  - sprintf %F (locale-independent) para los precios en SQL.
//  - INSERTs/UPDATEs de specials con expires_date = NOW()+2 dias: si el cron deja de refrescar
//    una oferta (producto descontinuado en Minderest) caduca sola en 48h.
//  - HTTP 500 (no 200) si CSV no existe o header invalido.
//  - Retencion logs >90 dias al cierre.
//  - Resumen final estructurado en el log.
// Refactor 2026-05-16: SQL injection cerrado (casts), guardia User-Agent cPanel-Cron,
// lockfile para evitar solapamientos, display_errors=0 (solo a log).

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/temp/error.log');
ini_set('memory_limit', '-1');
set_time_limit(0);
ini_set('max_execution_time', 0);
ignore_user_abort(true);  // si el cliente HTTP cierra (p.ej. timeout curl del wrapper), no abortar
date_default_timezone_set('Europe/Madrid');

const FB_MINDEREST_PRICE_EPSILON        = 0.005;
const FB_MINDEREST_DRIFT_PCT            = 1.0;   // log warning si csv.previous_price diverge >1% de bd.products_price
const FB_MINDEREST_SPECIAL_EXPIRES_DAYS = 2;     // ofertas Minderest caducan si el cron deja de refrescarlas
const FB_MINDEREST_LOG_RETENTION_DAYS   = 90;
const FB_MINDEREST_REQUIRED_COLS = [
    0  => 'id',
    1  => 'price_recommended_tax_excluded',
    7  => 'previous_price_tax_excluded',
    17 => 'increased',
];

$startTime = microtime(true);

$tempDir     = __DIR__ . '/temp';
$logDir      = $tempDir . '/log_minderest';
$downloadDir = $tempDir . '/downloads';

foreach ([$tempDir, $logDir, $downloadDir] as $dir) {
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        http_response_code(500);
        exit('Error: no se pudo crear ' . $dir);
    }
}

// Guardia: solo invocaciones desde el cron (User-Agent cPanel-Cron).
if (($_SERVER['HTTP_USER_AGENT'] ?? '') !== 'cPanel-Cron') {
    http_response_code(403);
    exit('Forbidden');
}

// Lockfile: evita solapamientos del cron.
$lockFile   = $tempDir . '/actuprice_minderest.lock';
$lockHandle = fopen($lockFile, 'c+');
if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    http_response_code(423);
    exit('Locked: hay otra ejecucion del cron en curso');
}

$sLogName    = 'log_minderest_' . date('d-m-Y_H-i-s') . '.txt';
$logFilePath = $logDir . '/' . $sLogName;
$fLog        = fopen($logFilePath, 'wb');
if (!$fLog) {
    error_log("No se pudo abrir log: $logFilePath");
    http_response_code(500);
    exit("Error al abrir el log.");
}

$writeLog = static function (string $message) use ($fLog): void {
    fwrite($fLog, '"' . date('d/m/Y H:i:s') . '" ' . $message . "\r\n");
};

$writeLog('"Iniciado actualizador precios Minderest (optimizado)"');

require 'includes/application_top.php';

// CSV
$csvFile = $_SERVER['DOCUMENT_ROOT'] . '/temp/downloads/export-repricing-nuevo.csv';
if (!is_file($csvFile) || !is_readable($csvFile)) {
    $writeLog('"Error: CSV no encontrado en ' . $csvFile . '"');
    http_response_code(500);
    exit("Error: CSV no encontrado en $csvFile");
}

$fh = fopen($csvFile, 'r');
if (!$fh) {
    $writeLog('"Error: no se pudo abrir CSV"');
    http_response_code(500);
    exit("Error al abrir CSV");
}

// Header + validacion estricta
$header = fgetcsv($fh, 0, ';', '"', '\\');
if (!$header) {
    $writeLog('"Error: CSV vacio o sin header"');
    http_response_code(500);
    exit("CSV vacio");
}
$header[0] = ltrim($header[0], "\xef\xbb\xbf"); // BOM UTF-8

foreach (FB_MINDEREST_REQUIRED_COLS as $idx => $expected) {
    $got = $header[$idx] ?? 'NULL';
    if ($got !== $expected) {
        $msg = sprintf('Schema mismatch: col[%d] esperado="%s" recibido="%s". Aborto sin tocar nada.',
            $idx, $expected, $got);
        $writeLog('"' . $msg . '"');
        http_response_code(500);
        exit($msg);
    }
}
$writeLog('"Header CSV validado: ' . count($header) . ' columnas"');

// Pasada 1: leer CSV y recolectar IDs
$rows         = [];
$productIds   = [];
$attributeIds = [];

while (($row = fgetcsv($fh, 0, ';', '"', '\\')) !== false) {
    $sId       = (string) ($row[0] ?? '');
    $price     = (float)  ($row[1] ?? 0);
    $prevPrice = (float)  ($row[7] ?? 0);
    $increased = (int)    ($row[17] ?? 0);

    if ($sId === '' || $price <= 0) continue;

    $parts       = explode('-', $sId);
    $productId   = (int) $parts[0];
    $attributeId = (isset($parts[1]) && (int) $parts[1] > 0) ? (int) $parts[1] : 0;

    if ($productId <= 0) continue;

    $rows[] = compact('productId', 'attributeId', 'price', 'prevPrice', 'increased');
    $productIds[$productId] = true;
    if ($attributeId > 0) $attributeIds[$attributeId] = true;
}
fclose($fh);

$writeLog(sprintf('"CSV leido: %d filas validas, %d productos unicos, %d atributos"',
    count($rows), count($productIds), count($attributeIds)));

$stats = [
    'procesados'     => 0,
    'skipUnknown'    => 0,
    'driftWarnings'  => 0,
    'attrUpdated'    => 0,
    'priceUpdated'   => 0,
    'specialCreated' => 0,
    'specialUpdated' => 0,
    'specialRefreshed' => 0,
    'specialDeleted' => 0,
];

if (!empty($rows)) {

    $productIdList   = implode(',', array_map('intval', array_keys($productIds)));
    $attributeIdList = $attributeIds ? implode(',', array_map('intval', array_keys($attributeIds))) : '';

    // SELECTs scoped al CSV
    $aProductsAttributes = [];
    if ($attributeIdList !== '') {
        $q = "SELECT pa.products_attributes_id, pa.products_id, pa.options_values_price, pa.price_prefix,
                     po.products_options_name, pov.products_options_values_name
              FROM products_attributes pa
              INNER JOIN products_options po
                ON pa.options_id = po.products_options_id AND po.language_id = 3
              INNER JOIN products_options_values pov
                ON pa.options_values_id = pov.products_options_values_id AND pov.language_id = 3
              WHERE pa.products_attributes_id IN ($attributeIdList)";
        $r = tep_db_query($q);
        while ($a = tep_db_fetch_array($r)) {
            $aProductsAttributes[$a['products_id']][$a['products_attributes_id']] = [
                'price'        => $a['options_values_price'],
                'prefix'       => $a['price_prefix'],
                'option'       => $a['products_options_name'],
                'option_value' => $a['products_options_values_name'],
            ];
        }
    }

    $aProductsSpecials = [];
    $r = tep_db_query("SELECT specials_id, products_id, specials_new_products_price, status
                       FROM specials
                       WHERE customers_group_id = 0 AND products_id IN ($productIdList)");
    while ($a = tep_db_fetch_array($r)) {
        $aProductsSpecials[$a['products_id']] = [
            'id'     => $a['specials_id'],
            'price'  => $a['specials_new_products_price'],
            'status' => $a['status'],
        ];
    }

    $aProductosMind = [];
    $r = tep_db_query("SELECT p.products_id, p.products_price, pd.products_name
                       FROM products p
                       INNER JOIN products_description pd
                         ON p.products_id = pd.products_id AND pd.language_id = 3
                       WHERE p.products_id IN ($productIdList)");
    while ($a = tep_db_fetch_array($r)) {
        $aProductosMind[$a['products_id']] = [
            'price' => $a['products_price'],
            'name'  => html_entity_decode($a['products_name'] ?? ''),
        ];
    }

    $writeLog('"Comienza recorrido de productos"');

    $specialExpires = date('Y-m-d H:i:s', strtotime('+' . FB_MINDEREST_SPECIAL_EXPIRES_DAYS . ' days'));
    $epsilon = FB_MINDEREST_PRICE_EPSILON;
    $priceEq = static fn(float $a, float $b): bool => abs($a - $b) <= $epsilon;

    foreach ($rows as $r) {
        $stats['procesados']++;
        $productId   = $r['productId'];
        $attributeId = $r['attributeId'];
        $sPrice      = $r['price'];
        $prevPrice   = $r['prevPrice'];
        $nIncreased  = $r['increased'];

        if (!isset($aProductosMind[$productId])) {
            $stats['skipUnknown']++;
            $writeLog('"Saltado: product_id no existe: ' . $productId . '"');
            continue;
        }

        $productBasePrice = (float) $aProductosMind[$productId]['price'];
        $productName      = $aProductosMind[$productId]['name'];

        // Sanity: csv.previous_price vs bd.products_price (solo para producto base, no atributo)
        if ($attributeId === 0 && $prevPrice > 0) {
            $driftPct = abs(($productBasePrice - $prevPrice) / $prevPrice) * 100.0;
            if ($driftPct > FB_MINDEREST_DRIFT_PCT) {
                $stats['driftWarnings']++;
                $writeLog(sprintf('"Drift externo pid=%d (%s): csv_previous=%.4f bd_actual=%.4f drift=%.2f%%; aplicando precio Minderest igual"',
                    $productId, $productName, $prevPrice, $productBasePrice, $driftPct));
            }
        }

        // --- Atributo ---
        if ($attributeId > 0) {
            if (!isset($aProductsAttributes[$productId][$attributeId])) {
                $stats['skipUnknown']++;
                $writeLog('"Saltado atributo desconocido: ' . $productId . '-' . $attributeId . '"');
                continue;
            }
            $nAtriPrice  = $sPrice - $productBasePrice;
            $attrCurrent = (float) $aProductsAttributes[$productId][$attributeId]['price'];
            $attrNew     = abs(round($nAtriPrice, 4));

            if (!$priceEq($attrCurrent, $attrNew)) {
                $writeLog(sprintf('"Actualizado atributo \'%s (%s - %s)\'; PrecioBase: %.4f; AnadidoAnterior: %.4f; AnadidoNuevo: %+.4f"',
                    $productName,
                    $aProductsAttributes[$productId][$attributeId]['option'],
                    $aProductsAttributes[$productId][$attributeId]['option_value'],
                    $productBasePrice, $attrCurrent, $nAtriPrice));

                tep_db_query(sprintf(
                    'UPDATE products_attributes SET options_values_price = "%F", price_prefix = "%s" WHERE products_attributes_id = %d',
                    $attrNew, ($nAtriPrice < 0 ? '-' : '+'), $attributeId
                ));
                tep_db_query("UPDATE products SET products_last_modified = NOW() WHERE products_id = $productId");
                $stats['attrUpdated']++;
            }
            continue;
        }

        // --- Producto base ---
        $porcentajeOferta = (float) round(100 - (($sPrice * 100) / $productBasePrice));

        if ($nIncreased === 1 && $porcentajeOferta < 5) {
            // Sube precio: quita oferta si la hubiera y refresca precio base
            if (isset($aProductsSpecials[$productId])) {
                $specialId = (int) $aProductsSpecials[$productId]['id'];
                $writeLog(sprintf('"Eliminada oferta \'%s\'; Oferta: %.4f"',
                    $productName, (float) $aProductsSpecials[$productId]['price']));
                tep_db_query("DELETE FROM specials WHERE specials_id = $specialId");
                $stats['specialDeleted']++;
            }
            if (!$priceEq($productBasePrice, $sPrice)) {
                $writeLog(sprintf('"Subido precio \'%s\'; Anterior: %.4f; Nuevo: %.4f"',
                    $productName, $productBasePrice, $sPrice));
                tep_db_query(sprintf('UPDATE products SET products_price = "%F", products_last_modified = NOW() WHERE products_id = %d',
                    $sPrice, $productId));
                $stats['priceUpdated']++;
            }
        } else {
            if (isset($aProductsSpecials[$productId])) {
                $specialId    = (int) $aProductsSpecials[$productId]['id'];
                $specialPrice = (float) $aProductsSpecials[$productId]['price'];
                if (!$priceEq($specialPrice, $sPrice)) {
                    $writeLog(sprintf('"Actualizada oferta \'%s\'; Anterior: %.4f; Nueva: %.4f (expira %s)"',
                        $productName, $specialPrice, $sPrice, $specialExpires));
                    tep_db_query(sprintf(
                        'UPDATE specials SET specials_new_products_price = "%F", specials_last_modified = NOW(), expires_date = "%s", status = 1 WHERE specials_id = %d',
                        $sPrice, $specialExpires, $specialId
                    ));
                    tep_db_query("UPDATE products SET products_last_modified = NOW() WHERE products_id = $productId");
                    $stats['specialUpdated']++;
                } else {
                    // Mismo precio: solo refrescar expires_date para mantener viva la oferta
                    tep_db_query(sprintf(
                        'UPDATE specials SET expires_date = "%s" WHERE specials_id = %d',
                        $specialExpires, $specialId
                    ));
                    $stats['specialRefreshed']++;
                }
            } else {
                if ($productBasePrice > $sPrice && $porcentajeOferta > 5) {
                    $writeLog(sprintf('"Creada oferta \'%s\'; PrecioBase: %.4f; OfertaNueva: %.4f (expira %s)"',
                        $productName, $productBasePrice, $sPrice, $specialExpires));
                    tep_db_query(sprintf(
                        'INSERT INTO specials (products_id, specials_new_products_price, specials_date_added, expires_date, status, customers_group_id)
                         VALUES (%d, "%F", NOW(), "%s", 1, 0)',
                        $productId, $sPrice, $specialExpires
                    ));
                    $stats['specialCreated']++;
                } elseif (!$priceEq($productBasePrice, $sPrice)) {
                    $writeLog(sprintf('"Bajado precio \'%s\'; Anterior: %.4f; Nuevo: %.4f"',
                        $productName, $productBasePrice, $sPrice));
                    tep_db_query(sprintf('UPDATE products SET products_price = "%F", products_last_modified = NOW() WHERE products_id = %d',
                        $sPrice, $productId));
                    $stats['priceUpdated']++;
                }
            }
        }
    }
} // end if (!empty($rows))

$executionTime = round(microtime(true) - $startTime, 2);

$writeLog(sprintf(
    '"RESUMEN procesadas=%d skipUnknown=%d driftWarnings=%d attrUpdated=%d priceUpdated=%d specialCreated=%d specialUpdated=%d specialRefreshed=%d specialDeleted=%d tiempo=%.2fs"',
    $stats['procesados'], $stats['skipUnknown'], $stats['driftWarnings'],
    $stats['attrUpdated'], $stats['priceUpdated'],
    $stats['specialCreated'], $stats['specialUpdated'], $stats['specialRefreshed'], $stats['specialDeleted'],
    $executionTime
));
$writeLog('"Finalizado actualizador Minderest"');

// Retencion logs >90 dias
$cutoff = time() - FB_MINDEREST_LOG_RETENTION_DAYS * 86400;
$purged = 0;
foreach (glob($logDir . '/log_minderest_*.txt') ?: [] as $oldLog) {
    if (@filemtime($oldLog) < $cutoff && @unlink($oldLog)) $purged++;
}
if ($purged > 0) {
    $writeLog(sprintf('"Purgados %d logs antiguos (>%d dias)"', $purged, FB_MINDEREST_LOG_RETENTION_DAYS));
}

fclose($fLog);

// Renombrar log con sufijo _fin
$finalLogPath = $logDir . '/' . substr($sLogName, 0, -4) . '_fin.txt';
@rename($logFilePath, $finalLogPath);

// Liberar lockfile
flock($lockHandle, LOCK_UN);
fclose($lockHandle);

echo "OK procesadas={$stats['procesados']} tiempo={$executionTime}s\n";
