<?php
/**
 * cron_products_liquidacion.php — descatalogación automática de liquidación sin stock
 *
 * Para cada producto con products_liquidacion=1 y products_status=1:
 *   - Sin variantes (sin filas en products_attributes):
 *       stock_real = products_quantity > 0 AND products_quantity != 2000
 *   - Con variantes (al menos 1 fila en products_attributes):
 *       stock_real = EXISTE alguna fila products_stock con quantity > 0 AND quantity != 2000
 *   - Si NO hay stock_real:
 *       UPDATE products SET products_status=2, products_last_modified=NOW()
 *
 * Sentinels NO son stock real: 2000, -100, -800, -900.
 *
 * Backup SQL reversible previo en /home/francobordo/backups/cron_products_liquidacion_YYYYMMDDHHMMSS.sql.
 *
 * Uso:
 *   php cron_products_liquidacion.php DRY   # reporta sin tocar
 *   php cron_products_liquidacion.php       # ejecuta (default)
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
if ($mysqli->connect_error) {
    logMsg('FATAL: ' . $mysqli->connect_error);
    exit(1);
}
$mysqli->set_charset('utf8mb4');

logMsg($dryRun ? '== DRY-RUN ==' : '== EJECUCION ==');

$sqlCandidatos = "
SELECT p.products_id,
       p.products_quantity,
       p.check_stock,
       p.products_model,
       CASE WHEN pa.products_id IS NULL THEN 0 ELSE 1 END AS has_variants,
       COALESCE(ps.tot_stock_real, 0) AS tot_stock_real,
       pd.products_name
FROM products p
LEFT JOIN (SELECT DISTINCT products_id FROM products_attributes) pa
       ON pa.products_id = p.products_id
LEFT JOIN (
    SELECT products_id,
           SUM(CASE WHEN products_stock_quantity > 0 AND products_stock_quantity != 2000
                    THEN products_stock_quantity ELSE 0 END) AS tot_stock_real
    FROM products_stock GROUP BY products_id
) ps ON ps.products_id = p.products_id
JOIN products_description pd
     ON pd.products_id = p.products_id AND pd.language_id = 3
WHERE p.products_liquidacion = 1
  AND p.products_status = 1
  AND (
        (pa.products_id IS NULL     AND (p.products_quantity <= 0 OR p.products_quantity = 2000))
     OR (pa.products_id IS NOT NULL AND COALESCE(ps.tot_stock_real, 0) = 0)
  )
ORDER BY has_variants DESC, p.products_id
";

$res = $mysqli->query($sqlCandidatos);
if (!$res) { logMsg('FATAL query: ' . $mysqli->error); exit(1); }

$candidatos = [];
while ($row = $res->fetch_assoc()) $candidatos[] = $row;

logMsg('Candidatos a descatalogar (status 1->2): ' . count($candidatos));

if (!$candidatos) {
    logMsg('Nada que hacer. Fin.');
    exit(0);
}

foreach ($candidatos as $c) {
    logMsg(sprintf('  pid=%-7s var=%s qty_padre=%-6s stock_real_variantes=%-6s %s',
        $c['products_id'], $c['has_variants'],
        $c['products_quantity'], $c['tot_stock_real'],
        $c['products_name']));
}

if ($dryRun) { logMsg('DRY-RUN: no se aplica UPDATE. Fin.'); exit(0); }

$pids = array_map(fn($c) => (int)$c['products_id'], $candidatos);
$inList = implode(',', $pids);

$bkDir = '/home/francobordo/backups';
if (!is_dir($bkDir)) @mkdir($bkDir, 0755, true);
$bk = $bkDir . '/cron_products_liquidacion_' . date('YmdHis') . '.sql';
$fh = fopen($bk, 'w');
fwrite($fh, "-- Backup pre-descatalogacion liquidacion " . date('c') . "\n");
fwrite($fh, "-- Para revertir: mysql {$DB_NAME} < {$bk}\n");
$rb = $mysqli->query("SELECT products_id, products_status FROM products WHERE products_id IN ($inList)");
while ($row = $rb->fetch_assoc()) {
    fwrite($fh, sprintf("UPDATE products SET products_status=%d WHERE products_id=%d;\n",
        $row['products_status'], $row['products_id']));
}
fclose($fh);
logMsg("Backup: $bk (" . filesize($bk) . " bytes)");

$sqlUp = "UPDATE products
          SET products_status=2, products_last_modified=NOW()
          WHERE products_id IN ($inList)
            AND products_liquidacion=1
            AND products_status=1";
if (!$mysqli->query($sqlUp)) {
    logMsg('FATAL UPDATE: ' . $mysqli->error);
    exit(1);
}
logMsg('UPDATE filas afectadas: ' . $mysqli->affected_rows);

$rv = $mysqli->query("SELECT COUNT(*) c FROM products
                      WHERE products_id IN ($inList)
                        AND products_status != 2");
$bad = (int)$rv->fetch_assoc()['c'];
if ($bad) { logMsg("ANOMALIA: $bad productos no quedaron en status=2"); exit(1); }

logMsg('OK. Fin.');
