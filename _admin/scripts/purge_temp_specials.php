<?php
/**
 * purge_temp_specials.php - borra ofertas TEMPORALES caducadas (is_temp = 1).
 *
 * Las ofertas temporales se crean desde el boton "+" de _admin/specials_avanzado.php
 * con is_temp=1, start_date/expires_date y expires_repeat=0. Al caducar,
 * tep_expire_specials() (application_top) las desactiva (status=0) y reaparece la
 * oferta principal del producto (que tiene expires_repeat=1 y se renueva sola).
 * Este cron las elimina definitivamente de la BD.
 *
 * Solo borra filas con is_temp=1 -> nunca toca ofertas no-temporales.
 * Equivale al boton "Borrar temporales caducadas" del modulo, pero automatico.
 *
 * Uso:
 *   php purge_temp_specials.php DRY   # reporta cuantas borraria, sin tocar
 *   php purge_temp_specials.php       # ejecuta (default)
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

$WHERE = "is_temp = '1' AND expires_date > 0 AND expires_date < NOW()";

$res = $mysqli->query("SELECT COUNT(*) AS n FROM specials WHERE $WHERE");
$n = ($res && ($row = $res->fetch_assoc())) ? (int)$row['n'] : 0;

if ($n === 0) { logMsg("Sin ofertas temporales caducadas. Nada que borrar."); exit(0); }

if ($dryRun) { logMsg("DRY: se borrarian $n ofertas temporales caducadas."); exit(0); }

if ($mysqli->query("DELETE FROM specials WHERE $WHERE")) {
    logMsg("Borradas " . $mysqli->affected_rows . " ofertas temporales caducadas.");
} else {
    logMsg("ERROR DELETE: " . $mysqli->error);
    exit(1);
}
