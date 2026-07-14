<?php
/**
 * cron_bajo_pedido_marcas.php — check "bajo pedido sin stock" POR MARCA
 * (manufacturers.manufacturers_bajo_pedido = 1; sustituye al check por
 * proveedor de QFac tras cortar el sync de stock).
 *
 * Para los productos ACTIVOS (status=1) de las marcas marcadas, convierte el
 * stock "sin existencias" al sentinela BAJO PEDIDO (-800), tanto a nivel de
 * producto (products.products_quantity) como de variante (products_stock):
 *   rango convertido: 0 y negativos pequeños hasta -99 (reservas/oversell).
 *
 * NO toca:
 *   - stock real positivo,
 *   - productos de fabricación (products_fabricacion=1 → sentinela 2000),
 *   - sentinels existentes: 2000, -100..-150 (proveedor), -800.. (ya bajo
 *     pedido; las ventas lo decrementan dentro del rango), -900 (descat.),
 *   - productos con status != 1.
 *
 * Uso:
 *   php cron_bajo_pedido_marcas.php DRY    # reporta sin tocar
 *   php cron_bajo_pedido_marcas.php        # ejecuta (backup SQL reversible)
 *
 * Crontab: 35 4 * * * (antes del cron de liquidación de las 04:45).
 * Backups: /home/francobordo/backups/cron_bajo_pedido_marcas_*.sql
 */

$confPath = '/home/francobordo/public_html/includes/configure.php';
$conf = file_get_contents($confPath);
preg_match("/'DB_SERVER',\s*'([^']+)'/", $conf, $m); $DB_HOST = $m[1] ?? 'localhost';
preg_match("/'DB_SERVER_USERNAME',\s*'([^']+)'/", $conf, $m); $DB_USER = $m[1];
preg_match("/'DB_SERVER_PASSWORD',\s*'([^']+)'/", $conf, $m); $DB_PASS = $m[1];
preg_match("/'DB_DATABASE',\s*'([^']+)'/", $conf, $m); $DB_NAME = $m[1];

$dryRun = false;
foreach (array_slice($argv, 1) as $a) {
    if (strtoupper($a) === 'DRY') $dryRun = true;
}

function logMsg($s) { echo '[' . date('Y-m-d H:i:s') . '] ' . $s . "\n"; }

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_error) { logMsg('ERROR conexion: ' . $mysqli->connect_error); exit(1); }
$mysqli->set_charset('utf8');

logMsg('Modo: ' . ($dryRun ? 'DRY-RUN' : 'EXECUTE'));

// Marcas con el check activo
$mids = array();
$r = $mysqli->query("SELECT manufacturers_id, manufacturers_name FROM manufacturers WHERE manufacturers_bajo_pedido = 1") or die($mysqli->error);
while ($x = $r->fetch_assoc()) {
    $mids[] = (int)$x['manufacturers_id'];
    logMsg('  marca: #' . $x['manufacturers_id'] . ' ' . $x['manufacturers_name']);
}
if (count($mids) === 0) { logMsg('Ninguna marca con manufacturers_bajo_pedido=1. Fin.'); exit(0); }
$IN = implode(',', $mids);

$WHERE_P = "p.manufacturers_id IN ($IN) AND p.products_status = 1 AND p.products_fabricacion = 0";

// Candidatos
$rP = $mysqli->query("SELECT p.products_id, p.products_quantity FROM products p
                      WHERE $WHERE_P AND p.products_quantity <= 0 AND p.products_quantity > -100") or die($mysqli->error);
$rS = $mysqli->query("SELECT ps.products_stock_id, ps.products_stock_quantity
                      FROM products_stock ps JOIN products p ON p.products_id = ps.products_id
                      WHERE $WHERE_P AND ps.products_stock_quantity <= 0 AND ps.products_stock_quantity > -100") or die($mysqli->error);
logMsg('Productos a convertir a -800: ' . $rP->num_rows);
logMsg('Variantes a convertir a -800: ' . $rS->num_rows);

if ($dryRun) {
    $n = 0;
    while (($x = $rP->fetch_assoc()) && $n < 10) { logMsg('   ej. producto #' . $x['products_id'] . ' qty=' . $x['products_quantity']); $n++; }
    logMsg('DRY-RUN: sin cambios.');
    exit(0);
}

if ($rP->num_rows === 0 && $rS->num_rows === 0) { logMsg('Nada que convertir. Fin.'); exit(0); }

// Backup reversible
$bdir = '/home/francobordo/backups';
if (!is_dir($bdir)) mkdir($bdir, 0755, true);
$bfile = $bdir . '/cron_bajo_pedido_marcas_' . date('Ymd_His') . '.sql';
$fh = fopen($bfile, 'w') or die('no puedo escribir backup');
fwrite($fh, "-- restore cron_bajo_pedido_marcas " . date('c') . " (marcas: $IN)\n");
while ($x = $rP->fetch_assoc())
    fwrite($fh, "UPDATE products SET products_quantity = " . (int)$x['products_quantity'] . " WHERE products_id = " . (int)$x['products_id'] . ";\n");
$stockIds = array();
while ($x = $rS->fetch_assoc()) {
    fwrite($fh, "UPDATE products_stock SET products_stock_quantity = " . (int)$x['products_stock_quantity'] . " WHERE products_stock_id = " . (int)$x['products_stock_id'] . ";\n");
    $stockIds[] = (int)$x['products_stock_id'];
}
fclose($fh);
logMsg('Backup reversible: ' . $bfile);

// Ejecutar
$mysqli->query("UPDATE products p SET p.products_quantity = -800
                WHERE $WHERE_P AND p.products_quantity <= 0 AND p.products_quantity > -100") or die($mysqli->error);
logMsg('products actualizados: ' . $mysqli->affected_rows);

// Variantes por LOTES de products_stock_id, SIN join a products: los triggers
// AFTER UPDATE de products_stock actualizan products.products_last_modified y
// MySQL prohibe que el trigger toque una tabla usada por la sentencia (error
// "Can't update table 'products' in stored function/trigger").
$nS = 0;
foreach (array_chunk($stockIds, 500) as $chunk) {
    $mysqli->query("UPDATE products_stock SET products_stock_quantity = -800 WHERE products_stock_id IN (" . implode(',', $chunk) . ")") or die($mysqli->error);
    $nS += $mysqli->affected_rows;
}
logMsg('products_stock actualizados: ' . $nS);

logMsg('OK.');
