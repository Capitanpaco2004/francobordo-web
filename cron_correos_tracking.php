<?php
/**
 * Tracking automático Correos (de España): devoluciones RMA + envíos de salida.
 *
 * - Recorre los envíos activos de correos_shipments (ok=1, no anulados, <90 días,
 *   no entregados) y consulta la trazabilidad canónica por packageCode.
 * - Actualiza correos_tracking (estado actual + eventos_json con el histórico).
 * - Devoluciones (tipo devolucion): al pasar a ENTREGADO → nota privada en el RMA.
 * - Salida (tipo envio): al pasar a ENTREGADO → completa el pedido (status 3 +
 *   email + opiniones, vía importador::saveData, igual que el cron CEX). Si cae
 *   en fase DEVOLUCIÓN → nota en el histórico del pedido (sin cambiar estado).
 *
 * Uso:  /cron_correos_tracking.php?token=XXX          (live)
 *       &dry=1 (no escribe nada) · &days=N (ventana, def. 90)
 * Ver memoria francobordo_correos_api.
 */

use util\tools as tools;
include('includes/classes/tools.php');

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');
$br = php_sapi_name() === 'cli' ? "\n" : "<br>\n";

define('CORREOS_TRACKING_TOKEN', 'corrtrk_b83f5a1c9d');
if (($_GET['token'] ?? '') !== CORREOS_TRACKING_TOKEN) {
    http_response_code(403);
    echo 'forbidden';
    exit;
}

$dry  = (($_GET['dry'] ?? '') === '1');
$days = isset($_GET['days']) ? max(1, min(180, (int) $_GET['days'])) : 90;

// --- Bootstrap admin (saltar login, igual que cron_cex_tracking.php) ---
$sAdminPath = tools::getPathAdmin();
$_SERVER['PHP_SELF']        = 'cron_correos_tracking.php';
$_SERVER['SCRIPT_FILENAME'] = 'cron_correos_tracking.php';
$_SERVER['SCRIPT_NAME']     = str_replace('cron_correos_tracking.php', '', $_SERVER['SCRIPT_NAME']) . $sAdminPath . '/cron_correos_tracking.php';
chdir($sAdminPath);
require('includes/application_top.php');
define('PATH_UPDATE_MASIVE_ORDERS', realpath(DIR_FS_ADMIN) . '/includes/modules/update_masive_orders/');
require_once(PATH_UPDATE_MASIVE_ORDERS . 'importador.php');
require_once(DIR_FS_CATALOG . 'includes/classes/correos.php');

echo 'Correos tracking ' . ($dry ? '[DRY-RUN] ' : '') . date('d/m/Y H:i:s') . $br;

$q = tep_db_query(
    "SELECT s.id, s.id_rma, s.orders_id, s.tipo, s.ref, s.shipment_code, s.package_code, t.entregado AS ya_entregado
       FROM correos_shipments s
       LEFT JOIN correos_tracking t ON t.referencia = s.ref
      WHERE s.ok = 1
        AND s.cancelled_at IS NULL
        AND s.package_code IS NOT NULL AND s.package_code <> ''
        AND s.date_added > DATE_SUB(NOW(), INTERVAL " . (int) $days . " DAY)
        AND (t.entregado IS NULL OR t.entregado = 0)
      ORDER BY s.id"
);
$pendientes = array();
while ($r = tep_db_fetch_array($q)) $pendientes[] = $r;
echo 'Envíos a consultar: ' . count($pendientes) . $br;
if (!$pendientes) { echo 'Nada que hacer. FIN' . $br; exit; }

$c = new correos('pro');
$c->setTimeout(30);

$upserts = 0; $entregasRma = 0; $errores = 0; $completar = array(); $devueltos = 0;
foreach ($pendientes as $p) {
    $res = $c->seguimiento($p['package_code']);
    if (!$res['ok']) {
        $errores++;
        echo '  ✖ ' . $p['ref'] . ': HTTP ' . $res['http'] . ' ' . $res['error'] . $br;
        continue;
    }
    $env = $res['data'][0] ?? array();
    if (!empty($env['error']['codError']) && $env['error']['codError'] !== '0') {
        echo '  · ' . $p['ref'] . ': ' . $env['error']['desError'] . $br;   // p.ej. aún sin trazabilidad
        continue;
    }
    $ev = correos::ultimoEvento($res);
    if (!$ev) { echo '  · ' . $p['ref'] . ': sin eventos' . $br; continue; }

    $code = (string) ($ev['codEvento'] ?? '');
    $fase = (string) ($ev['desFase'] ?? '');
    $desc = trim($fase . ' — ' . (string) ($ev['desTextoResumen'] ?? ''), ' —');
    $entregado = correos::esEntregado($ev) ? 1 : 0;
    $esDevolucion = (stripos($fase, 'DEVOLUC') !== false);
    $fevento = null;
    if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', (string) ($ev['fecEvento'] ?? ''), $m)) {
        $fevento = $m[3] . '-' . $m[2] . '-' . $m[1] . ' ' . (preg_match('/^\d{2}:\d{2}/', (string) ($ev['horEvento'] ?? '')) ? $ev['horEvento'] : '00:00:00');
    }
    // Histórico de eventos (compacto) para la web "Localiza tu envío".
    $evJson = json_encode(array_map(function ($e) {
        return array('f' => $e['fecEvento'] ?? '', 'h' => substr((string) ($e['horEvento'] ?? ''), 0, 5),
                     'fase' => $e['desFase'] ?? '', 'txt' => $e['desTextoResumen'] ?? '');
    }, array_slice($env['eventos'] ?? array(), -12)), JSON_UNESCAPED_UNICODE);

    echo '  - [' . $p['tipo'] . '] ' . $p['ref'] . ': ' . $desc . ($entregado ? '  ✅ ENTREGADO' : '') . $br;
    if ($dry) continue;

    tep_db_query(
        "INSERT INTO correos_tracking (referencia, shipment_code, estado_code, estado_desc, fecha_evento, entregado, eventos_json, last_checked) VALUES ("
        . "'" . tep_db_input($p['ref']) . "', '" . tep_db_input($p['shipment_code']) . "', '" . tep_db_input($code) . "', '" . tep_db_input($desc) . "', "
        . ($fevento ? "'" . tep_db_input($fevento) . "'" : "NULL") . ", $entregado, '" . tep_db_input($evJson) . "', now()) "
        . "ON DUPLICATE KEY UPDATE shipment_code=VALUES(shipment_code), estado_code=VALUES(estado_code), estado_desc=VALUES(estado_desc), "
        . "fecha_evento=VALUES(fecha_evento), entregado=VALUES(entregado), eventos_json=VALUES(eventos_json), last_checked=now()"
    );
    $upserts++;

    if ($p['tipo'] === 'devolucion') {
        // Devolución RMA recién entregada en Francobordo → nota privada en el RMA.
        if ($entregado && !(int) $p['ya_entregado'] && (int) $p['id_rma'] > 0) {
            $entregasRma++;
            $rmaQ = tep_db_query('SELECT status FROM ' . TABLE_RMA . ' WHERE id_rma = ' . (int) $p['id_rma']);
            $rmaR = tep_db_fetch_array($rmaQ);
            tep_db_perform(TABLE_RMA_STATUS_HISTORY, array(
                'email_text' => '', 'notify' => 0, 'id_rma' => (int) $p['id_rma'],
                'id_status' => $rmaR ? (int) $rmaR['status'] : 0, 'message' => '',
                'private_message' => 'Correos: la devolución ' . $p['shipment_code'] . ' figura ENTREGADA (recibida en Francobordo). Revisar y procesar el RMA.',
                'date_added' => 'now()',
            ));
        }
    } else {
        // Salida: completar pedido al entregar; nota si entra en devolución.
        if ($entregado && (int) $p['orders_id'] > 0) {
            $completar[] = array('oID' => (int) $p['orders_id'], 'estado' => 'entregado', 'fecha' => (string) $fevento);
        } elseif ($esDevolucion && (int) $p['orders_id'] > 0) {
            $devueltos++;
            tep_db_query("INSERT INTO " . TABLE_ORDERS_STATUS_HISTORY . " SET orders_id = " . (int) $p['orders_id'] . ", orders_status_id = 5, date_added = now(), customer_notified = 0, comments = 'Correos: el envío " . tep_db_input($p['shipment_code']) . " figura EN DEVOLUCIÓN (" . tep_db_input($desc) . "). Revisar.'");
        }
    }
}

// Completar pedidos entregados (solo los que siguen en "Enviado" = 5).
$content = array();
foreach ($completar as $cpl) {
    $oQ = tep_db_query("SELECT orders_status FROM " . TABLE_ORDERS . " WHERE orders_id = " . (int) $cpl['oID']);
    $oR = tep_db_fetch_array($oQ);
    if ($oR && (int) $oR['orders_status'] === 5) $content[] = $cpl;
}
if (!$dry && $content) {
    $imp = new importador();
    $imp->valor   = 'correos_api';
    $imp->content = $content;
    $imp->saveData();   // status 3 + email + histórico + opiniones
}

echo $br . "correos_tracking: $upserts filas · RMA entregados: $entregasRma · pedidos completados: " . count($content) . " · en devolución: $devueltos · errores: $errores" . $br;
echo 'FIN - ' . date('d/m/Y H:i:s') . $br;
