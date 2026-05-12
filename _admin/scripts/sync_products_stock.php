<?php
/**
 * sync_products_stock.php — mantenimiento de integridad products_stock ⇄ products_attributes
 *
 * FASE 1 — REVERSE (limpieza):
 *   Borra filas de products_stock cuya clave simple (oid-ovid) no se corresponde
 *   con ningún products_attributes para ese products_id. Incluye:
 *     - filas de productos borrados (sin entrada en products);
 *     - filas de atributos borrados (atributo eliminado, stock huérfano);
 *     - filas con clave malformada (sin guion).
 *   NO toca filas con clave combinada ('a-b,c-d') — legacy de productos multi-option
 *   (que ya no se crean en el proyecto).
 *
 * FASE 2 — FORWARD (siembra):
 *   Para cada (products_id, options_id-options_values_id) presente en
 *   products_attributes sin su fila correspondiente en products_stock,
 *   inserta una con quantity=0 y cost=0.0000.
 *   GROUP BY de-duplica el origen para tolerar pa con (pid,oid,ovid) repetidos
 *   (UNIQUE idx_products_stock_attributes obliga a una sola fila stock por key).
 *
 * Asume single-option (convención del proyecto desde hace años).
 *
 * Uso:
 *   php sync_products_stock.php DRY    # reporta sin tocar
 *   php sync_products_stock.php        # ejecuta (default)
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
if ($mysqli->connect_error) { logMsg("ERROR DB: " . $mysqli->connect_error); exit(1); }
$mysqli->set_charset('utf8mb4');

// REVERSE: filas stock con clave simple sin atributo correspondiente
$SQL_REVERSE_COUNT =
"SELECT COUNT(*) AS n
 FROM products_stock ps
 LEFT JOIN products_attributes pa
   ON pa.products_id = ps.products_id
   AND ps.products_stock_attributes = CONCAT(pa.options_id, '-', pa.options_values_id)
 WHERE ps.products_stock_attributes NOT LIKE '%,%'
   AND pa.products_attributes_id IS NULL";

$SQL_REVERSE_DELETE =
"DELETE ps FROM products_stock ps
 LEFT JOIN products_attributes pa
   ON pa.products_id = ps.products_id
   AND ps.products_stock_attributes = CONCAT(pa.options_id, '-', pa.options_values_id)
 WHERE ps.products_stock_attributes NOT LIKE '%,%'
   AND pa.products_attributes_id IS NULL";

// FORWARD: atributos sin fila stock
$SQL_FORWARD_COUNT =
"SELECT COUNT(*) AS n
 FROM products_attributes AS pa
 LEFT JOIN products_stock AS ps
   ON pa.products_id = ps.products_id
   AND ps.products_stock_attributes = CONCAT(pa.options_id, '-', pa.options_values_id)
 WHERE ps.products_stock_id IS NULL";

$SQL_FORWARD_INSERT =
"INSERT INTO products_stock (products_id, products_stock_attributes, products_stock_quantity, products_stock_cost)
 SELECT pa.products_id, CONCAT(pa.options_id, '-', pa.options_values_id), 0, 0.0000
 FROM products_attributes AS pa
 LEFT JOIN products_stock AS ps
   ON pa.products_id = ps.products_id
   AND ps.products_stock_attributes = CONCAT(pa.options_id, '-', pa.options_values_id)
 WHERE ps.products_stock_id IS NULL
 GROUP BY pa.products_id, pa.options_id, pa.options_values_id";

logMsg("Modo: " . ($dryRun ? 'DRY-RUN' : 'EXECUTE'));

$rev_before = (int) $mysqli->query($SQL_REVERSE_COUNT)->fetch_assoc()['n'];
$fwd_before = (int) $mysqli->query($SQL_FORWARD_COUNT)->fetch_assoc()['n'];
logMsg("REVERSE — stock rows huérfanas (clave simple): $rev_before");
logMsg("FORWARD — atributos huérfanos sin stock      : $fwd_before");

if ($rev_before === 0 && $fwd_before === 0) {
    logMsg("Nada que hacer. Salida limpia.");
    exit(0);
}

if ($dryRun) {
    if ($rev_before > 0) {
        $r = $mysqli->query("
          SELECT ps.products_stock_id, ps.products_id, ps.products_stock_attributes, ps.products_stock_quantity, ps.products_stock_cost
          FROM products_stock ps
          LEFT JOIN products_attributes pa
            ON pa.products_id = ps.products_id
            AND ps.products_stock_attributes = CONCAT(pa.options_id, '-', pa.options_values_id)
          WHERE ps.products_stock_attributes NOT LIKE '%,%'
            AND pa.products_attributes_id IS NULL
          ORDER BY ps.products_stock_id DESC LIMIT 10
        ");
        logMsg("REVERSE — últimas 10 que se borrarían:");
        while ($row = $r->fetch_assoc()) {
            logMsg(sprintf("  ps_id=%s pid=%s key=%s qty=%s cost=%s",
                $row['products_stock_id'], $row['products_id'], $row['products_stock_attributes'],
                $row['products_stock_quantity'], $row['products_stock_cost']));
        }
    }
    if ($fwd_before > 0) {
        $r = $mysqli->query(
            "SELECT pa.products_id, pa.options_id, pa.options_values_id
             FROM products_attributes AS pa
             LEFT JOIN products_stock AS ps
               ON pa.products_id = ps.products_id
               AND ps.products_stock_attributes = CONCAT(pa.options_id, '-', pa.options_values_id)
             WHERE ps.products_stock_id IS NULL
             GROUP BY pa.products_id, pa.options_id, pa.options_values_id
             LIMIT 10");
        logMsg("FORWARD — primeras 10 keys que se insertarían:");
        while ($row = $r->fetch_assoc()) {
            logMsg(sprintf("  pid=%s key=%s-%s",
                $row['products_id'], $row['options_id'], $row['options_values_id']));
        }
    }
    logMsg("DRY-RUN: no se ha tocado nada.");
    exit(0);
}

$mysqli->begin_transaction();
try {
    // FASE 1: REVERSE
    $deleted = 0;
    if ($rev_before > 0) {
        $t0 = microtime(true);
        if (!$mysqli->query($SQL_REVERSE_DELETE)) throw new Exception("DELETE reverse falló: " . $mysqli->error);
        $deleted = $mysqli->affected_rows;
        $dt = round(microtime(true) - $t0, 2);
        logMsg("REVERSE — filas borradas: $deleted (${dt}s)");

        $rev_after = (int) $mysqli->query($SQL_REVERSE_COUNT)->fetch_assoc()['n'];
        if ($rev_after !== 0) throw new Exception("Sanity REVERSE: tras DELETE siguen $rev_after huérfanos (esperado 0)");
    }

    // FASE 2: FORWARD
    $inserted = 0;
    if ($fwd_before > 0) {
        $t0 = microtime(true);
        if (!$mysqli->query($SQL_FORWARD_INSERT)) throw new Exception("INSERT forward falló: " . $mysqli->error);
        $inserted = $mysqli->affected_rows;
        $dt = round(microtime(true) - $t0, 2);
        logMsg("FORWARD — filas insertadas: $inserted (${dt}s)");

        $fwd_after = (int) $mysqli->query($SQL_FORWARD_COUNT)->fetch_assoc()['n'];
        if ($fwd_after !== 0) throw new Exception("Sanity FORWARD: tras INSERT siguen $fwd_after huérfanos (esperado 0)");
    }

    $mysqli->commit();
    logMsg("COMMIT OK. Reverse=$deleted  Forward=$inserted  Huérfanos restantes: 0");
} catch (Throwable $e) {
    $mysqli->rollback();
    logMsg("ROLLBACK: " . $e->getMessage());
    exit(1);
}
