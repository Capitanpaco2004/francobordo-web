<?php
/**
 * cancelar_pendientes_antiguos.php — cancela pedidos estancados en pago
 *
 * Pedidos en estado Pendiente (1) o Pendiente Pago (309) con mas de 3 meses
 * de antiguedad pasan a Cancelado (4), dejando linea en orders_status_history
 * con customer_notified=0 (NO se envia email al cliente).
 *
 * Antes de tocar nada escribe un SQL inverso (orders_id -> estado previo) en
 * /home/francobordo/backups_scripts/cancelar_pendientes_YYYYmmdd_HHiiss.sql
 *
 * Cron diario 05:20 (crontab de francobordo). Primera ejecucion 2026-06-11 =
 * regularizacion inicial (~1.485 pedidos historicos).
 *
 * El UPDATE lleva guard "AND orders_status = <previo>" por si un pedido
 * cambia de estado entre el SELECT y el UPDATE (p.ej. alguien lo cobra).
 *
 * Uso:
 *   php cancelar_pendientes_antiguos.php DRY   # reporta sin tocar nada
 *   php cancelar_pendientes_antiguos.php       # ejecuta
 */

$confPath = '/home/francobordo/public_html/includes/configure.php';
$conf = file_get_contents($confPath);
preg_match("/'DB_SERVER',\s*'([^']+)'/", $conf, $m); $DB_HOST = $m[1] ?? 'localhost';
preg_match("/'DB_SERVER_USERNAME',\s*'([^']+)'/", $conf, $m); $DB_USER = $m[1];
preg_match("/'DB_SERVER_PASSWORD',\s*'([^']+)'/", $conf, $m); $DB_PASS = $m[1];
preg_match("/'DB_DATABASE',\s*'([^']+)'/", $conf, $m); $DB_NAME = $m[1];

const STATUS_ORIGEN  = array(1, 309); // Pendiente, Pendiente Pago
const STATUS_DESTINO = 4;             // Cancelado
const MESES          = 3;
const COMENTARIO     = 'Cancelado automaticamente: pendiente de pago durante mas de 3 meses.';

$dryRun = false;
foreach (array_slice($argv, 1) as $a) {
    if (strtoupper($a) === 'DRY') $dryRun = true;
}

function logMsg($s) { echo '[' . date('Y-m-d H:i:s') . '] ' . $s . "\n"; }

$db = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if (!$db) { logMsg('ERROR conexion BD: ' . mysqli_connect_error()); exit(1); }
mysqli_set_charset($db, 'utf8');

$in = implode(',', STATUS_ORIGEN);
$where = "orders_status IN ($in) AND date_purchased < NOW() - INTERVAL " . MESES . " MONTH";

$rs = mysqli_query($db, "SELECT orders_id, orders_status, date_purchased FROM orders WHERE $where ORDER BY orders_id");
$afectados = array();
while ($r = mysqli_fetch_assoc($rs)) { $afectados[] = $r; }

logMsg(count($afectados) . " pedidos en estado ($in) con mas de " . MESES . ' meses' . ($dryRun ? ' [DRY RUN]' : ''));

if (!count($afectados)) { logMsg('Nada que hacer.'); exit(0); }

if ($dryRun) {
    foreach (array_slice($afectados, 0, 20) as $r) {
        logMsg("  #{$r['orders_id']} status={$r['orders_status']} fecha={$r['date_purchased']}");
    }
    if (count($afectados) > 20) logMsg('  ... y ' . (count($afectados) - 20) . ' mas');
    exit(0);
}

/* Backup con SQL inverso */
$bdir = '/home/francobordo/backups_scripts';
if (!is_dir($bdir)) mkdir($bdir, 0700, true);
$bfile = $bdir . '/cancelar_pendientes_' . date('Ymd_His') . '.sql';
$fh = fopen($bfile, 'w');
fwrite($fh, '-- Reverso cancelar_pendientes_antiguos ' . date('c') . ' (' . count($afectados) . " pedidos)\n");
foreach ($afectados as $r) {
    fwrite($fh, 'UPDATE orders SET orders_status = ' . (int)$r['orders_status'] . ' WHERE orders_id = ' . (int)$r['orders_id'] . ";\n");
}
fclose($fh);
logMsg('Backup reverso: ' . $bfile);

$ok = 0; $err = 0;
$comentarioSql = mysqli_real_escape_string($db, COMENTARIO);
foreach ($afectados as $r) {
    $oid = (int)$r['orders_id'];
    $prev = (int)$r['orders_status'];
    $u = mysqli_query($db, "UPDATE orders SET orders_status = " . STATUS_DESTINO . ", last_modified = NOW() WHERE orders_id = $oid AND orders_status = $prev");
    if ($u && mysqli_affected_rows($db) === 1) {
        mysqli_query($db, "INSERT INTO orders_status_history (orders_id, orders_status_id, date_added, customer_notified, comments) VALUES ($oid, " . STATUS_DESTINO . ", NOW(), 0, '$comentarioSql')");
        $ok++;
    } else {
        $err++;
        logMsg("  AVISO: no actualizado #$oid (cambio concurrente o error: " . mysqli_error($db) . ')');
    }
}
logMsg("Hecho: $ok pedidos cancelados, $err avisos.");
exit($err > 0 ? 1 : 0);
