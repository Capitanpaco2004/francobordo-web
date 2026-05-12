<?php
/**
 * reactivar_descatalogados_con_stock.php — red de seguridad anti-descatalogación errónea
 *
 * Busca productos con products_status IN (0, 2) que tengan stock REAL
 * (products_stock_quantity > 0 AND != 2000 en alguna variante, o
 *  products_quantity > 0 AND != 2000 si no tienen variantes) y los reactiva:
 *   - products_status = 1
 *   - check_stock = 1
 *   - products_last_modified = NOW()
 *
 * Sentinels excluidos (NO son stock real): 2000, -100, -800, -900.
 *
 * EXCLUSIONES:
 *   - Productos cuyo nombre contenga la palabra "cabo" / "cabos" (decisión del
 *     usuario 2026-05-12: stock residual de cabos cortados/saldos no debe
 *     reactivarse automáticamente).
 *
 * Antes de cada ejecución guarda un backup SQL con el UPDATE inverso por pid,
 * por si hay que revertir.
 *
 * Uso:
 *   php reactivar_descatalogados_con_stock.php DRY   # reporta sin tocar
 *   php reactivar_descatalogados_con_stock.php       # ejecuta (default)
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

logMsg($dryRun ? '== DRY-RUN ==' : '== EJECUCIÓN ==');

$sqlCandidatos = "
SELECT p.products_id, p.products_status, p.check_stock, p.products_model,
       CASE WHEN pa.products_id IS NULL THEN p.products_quantity ELSE ps.tot_stock END AS stock_total,
       pd.products_name
FROM products p
LEFT JOIN (SELECT DISTINCT products_id FROM products_attributes) pa
       ON pa.products_id = p.products_id
LEFT JOIN (
    SELECT products_id,
           SUM(CASE WHEN products_stock_quantity > 0 AND products_stock_quantity != 2000
                    THEN products_stock_quantity ELSE 0 END) AS tot_stock
    FROM products_stock GROUP BY products_id
) ps ON ps.products_id = p.products_id
JOIN products_description pd
     ON pd.products_id = p.products_id AND pd.language_id = 3
WHERE p.products_status IN (0, 2)
  AND (
        (pa.products_id IS NULL  AND p.products_quantity > 0 AND p.products_quantity != 2000)
     OR (pa.products_id IS NOT NULL AND ps.tot_stock > 0)
  )
  AND pd.products_name NOT RLIKE '[[:<:]]cabos?[[:>:]]'
ORDER BY stock_total DESC, p.products_id
";

$res = $mysqli->query($sqlCandidatos);
if (!$res) { logMsg('FATAL query: ' . $mysqli->error); exit(1); }

$candidatos = [];
while ($row = $res->fetch_assoc()) $candidatos[] = $row;

logMsg('Candidatos a reactivar: ' . count($candidatos));

if (!$candidatos) {
    logMsg('Nada que hacer. Fin.');
    exit(0);
}

foreach ($candidatos as $c) {
    logMsg(sprintf('  pid=%-7s st=%s chk=%s stock=%-6s %s',
        $c['products_id'], $c['products_status'], $c['check_stock'],
        $c['stock_total'], $c['products_name']));
}

if ($dryRun) { logMsg('DRY-RUN: no se aplica UPDATE. Fin.'); exit(0); }

$pids = array_map(fn($c) => (int)$c['products_id'], $candidatos);
$inList = implode(',', $pids);

$bkDir = '/home/francobordo/backups';
if (!is_dir($bkDir)) @mkdir($bkDir, 0755, true);
$bk = $bkDir . '/reactivar_descatalogados_' . date('YmdHis') . '.sql';
$fh = fopen($bk, 'w');
fwrite($fh, "-- Backup pre-reactivación " . date('c') . "\n");
fwrite($fh, "-- Para revertir: mysql francobo_oscdenox < {$bk}\n");
$rb = $mysqli->query("SELECT products_id, products_status, check_stock FROM products WHERE products_id IN ($inList)");
while ($row = $rb->fetch_assoc()) {
    fwrite($fh, sprintf("UPDATE products SET products_status=%d, check_stock=%d WHERE products_id=%d;\n",
        $row['products_status'], $row['check_stock'], $row['products_id']));
}
fclose($fh);
logMsg("Backup: $bk (" . filesize($bk) . " bytes)");

$sqlUp = "UPDATE products
          SET products_status=1, check_stock=1, products_last_modified=NOW()
          WHERE products_id IN ($inList)";
if (!$mysqli->query($sqlUp)) {
    logMsg('FATAL UPDATE: ' . $mysqli->error);
    exit(1);
}
logMsg('UPDATE filas afectadas: ' . $mysqli->affected_rows);

$rv = $mysqli->query("SELECT COUNT(*) c FROM products
                      WHERE products_id IN ($inList)
                        AND (products_status != 1 OR check_stock != 1)");
$bad = (int)$rv->fetch_assoc()['c'];
if ($bad) { logMsg("ANOMALÍA: $bad productos no quedaron en status=1/check_stock=1"); exit(1); }

logMsg('OK. Fin.');
