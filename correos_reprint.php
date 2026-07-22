<?php
/**
 * Cola de reimpresión Correos — endpoint que sondea el watcher de .112.
 * El panel _admin/correos_envios.php inserta ZPL en correos_reprint_queue; el watcher Correos
 * (.112) llama aquí por HTTP (no tiene acceso directo a la BD web), vuelca el ZPL a la impresora
 * (correos_out → relay .5 → PORTATIL22) y confirma.
 *
 * Estados (columna done): 0=pendiente · 2=reclamado/en vuelo · 1=impreso/confirmado · 3=abandonado.
 *   GET ?token=...            → RECLAMA atómicamente el job más antiguo pendiente y lo devuelve
 *                               {ok,job:{id,oid,zpl}} (o {ok,job:null}). Marcar al SERVIR evita
 *                               reimpresión duplicada si la confirmación posterior se pierde.
 *   GET ?token=...&done=<id>  → impreso OK (done=1).
 *   GET ?token=...&fail=<id>  → falló la impresión: libera para reintento (done=0) o, tras 5
 *                               intentos, abandona (done=3, {gaveup:true}) para no bloquear la cola.
 * Reclama también claims caducados (>10 min) por si el watcher murió a medias. Ver memoria.
 */
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');
mysqli_report(MYSQLI_REPORT_OFF);

define('CORREOS_REPRINT_TOKEN', 'correosrep_4a9d2f81c6');
$MAXA = 5;

$in = array_merge($_GET, $_POST);
function out($a) { echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }

if (!hash_equals(CORREOS_REPRINT_TOKEN, (string) ($in['token'] ?? ''))) { http_response_code(403); out(array('ok' => false, 'error' => 'forbidden')); }

chdir(__DIR__);
include 'includes/configure.php';
$db = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($db->connect_errno) { out(array('ok' => false, 'error' => 'db no disponible')); }
$db->set_charset('utf8');

$done = (int) ($in['done'] ?? 0);
$fail = (int) ($in['fail'] ?? 0);

if ($done > 0) {
    $db->query("UPDATE correos_reprint_queue SET done = 1, done_at = NOW() WHERE id = " . $done);
    out(array('ok' => true, 'marked' => $done));
}
if ($fail > 0) {
    $r = $db->query("SELECT attempts FROM correos_reprint_queue WHERE id = " . $fail);
    $a = ($r && ($x = $r->fetch_assoc())) ? (int) $x['attempts'] : 99;
    if ($a >= $MAXA) {
        $db->query("UPDATE correos_reprint_queue SET done = 3, done_at = NOW() WHERE id = " . $fail);
        out(array('ok' => true, 'gaveup' => true, 'id' => $fail));
    }
    $db->query("UPDATE correos_reprint_queue SET done = 0 WHERE id = " . $fail . " AND done = 2");
    out(array('ok' => true, 'retry' => true, 'id' => $fail));
}

/* SERVE: reclama atómicamente el más antiguo pendiente (o un claim caducado >10 min), attempts<MAX. */
$sel = $db->query("SELECT id FROM correos_reprint_queue
                   WHERE attempts < " . $MAXA . "
                     AND (done = 0 OR (done = 2 AND done_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)))
                   ORDER BY id ASC LIMIT 1");
$row0 = $sel ? $sel->fetch_assoc() : null;
if (!$row0) out(array('ok' => true, 'job' => null));
$id = (int) $row0['id'];
$db->query("UPDATE correos_reprint_queue SET done = 2, attempts = attempts + 1, done_at = NOW() WHERE id = " . $id . " AND done IN (0,2)");
if ($db->affected_rows < 1) out(array('ok' => true, 'job' => null));   // otro lo reclamó (carrera)
$r2 = $db->query("SELECT id, orders_id, zpl, cn23_pcl FROM correos_reprint_queue WHERE id = " . $id);
$row = $r2 ? $r2->fetch_assoc() : null;
if (!$row) out(array('ok' => true, 'job' => null));
/* cn23_pcl = CN23 de aduanas en PCL A4 (base64); el watcher lo imprime en la HP tras la etiqueta. */
out(array('ok' => true, 'job' => array('id' => (int) $row['id'], 'oid' => (int) $row['orders_id'],
                                       'zpl' => $row['zpl'], 'cn23_pcl' => (string) ($row['cn23_pcl'] ?? ''))));
