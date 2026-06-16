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
    "SELECT s.id, s.id_rma, s.orders_id, s.tipo, s.ref, s.shipment_code, s.package_code, s.response_json, s.date_added, t.entregado AS ya_entregado
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
    // Todos los bultos del envío (multibulto): packageCodes de response_json o el package_code.
    $pkgCodes = array();
    $rj = json_decode((string) ($p['response_json'] ?? ''), true);
    $pk = $rj['data']['shipments'][0]['packages'] ?? null;
    if (is_array($pk)) foreach ($pk as $pp) if (!empty($pp['packageCode'])) $pkgCodes[] = (string) $pp['packageCode'];
    if (!$pkgCodes && !empty($p['package_code'])) $pkgCodes = array((string) $p['package_code']);
    $pkgCodes = array_values(array_unique($pkgCodes));
    if (!$pkgCodes) { echo '  · ' . $p['ref'] . ': sin packageCodes' . $br; continue; }

    // Consultar CADA bulto. Entregado = TODOS entregados; devolución = ALGUNO en fase DEVOLUCIÓN.
    // El evento MOSTRADO (dispEv) = el más reciente entre los bultos con traza (NO atado al bulto[0],
    // para que un bulto[0] sin traza no bloquee toda la fila).
    $dispRes = null; $dispEv = null; $allDelivered = true; $anyReturn = false;
    $anyCheckFail = false; $primaryErr = ''; $deliveredCount = 0; $multiCount = count($pkgCodes);
    foreach ($pkgCodes as $pc) {
        $res = $c->seguimiento($pc);
        if (!$res['ok']) {
            $allDelivered = false; $anyCheckFail = true;
            if ($primaryErr === '') $primaryErr = 'HTTP ' . $res['http'] . ' ' . $res['error'];
            continue;
        }
        $env = $res['data'][0] ?? array();
        if (!empty($env['error']['codError']) && $env['error']['codError'] !== '0') {
            $allDelivered = false; $anyCheckFail = true;
            if ($primaryErr === '') $primaryErr = (string) $env['error']['desError'];
            continue;
        }
        $ev = correos::ultimoEvento($res);
        if (!$ev) { $allDelivered = false; continue; }
        if (correos::algunEntregado($res)) $deliveredCount++; else $allDelivered = false;
        if (correos::huboDevolucion($res)) $anyReturn = true;
        if ($dispEv === null || correos::eventoTs($ev) > correos::eventoTs($dispEv)) { $dispRes = $res; $dispEv = $ev; }
    }

    if ($dispEv === null) {
        // Ningún bulto tiene trazabilidad todavía (o error). Saltar; se reintenta la próxima pasada.
        echo '  · ' . $p['ref'] . ': ' . ($primaryErr !== '' ? $primaryErr : 'sin eventos') . $br;
        if ($anyCheckFail) $errores++;
        continue;
    }

    $code = (string) ($dispEv['codEvento'] ?? '');
    $fase = (string) ($dispEv['desFase'] ?? '');
    $desc = trim($fase . ' — ' . (string) ($dispEv['desTextoResumen'] ?? ''), ' —');
    // En multibulto con entrega PARCIAL no mostrar 'ENTREGADO' a secas (confundiría al cliente).
    if ($multiCount > 1 && !$allDelivered) {
        $desc = 'En curso (' . $deliveredCount . '/' . $multiCount . ' bultos entregados)';
    }
    // ENTREGADO por TIPO: envío → al CLIENTE (todos los bultos Y sin devolución al remitente);
    // devolución RMA → recibida en FRANCOBORDO (todos los bultos; la devolución NO penaliza).
    if ($p['tipo'] === 'devolucion') {
        $entregado = $allDelivered ? 1 : 0;
    } else {
        $entregado = ($allDelivered && !$anyReturn) ? 1 : 0;
    }
    $fevento = null;
    if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', (string) ($dispEv['fecEvento'] ?? ''), $m)) {
        $fevento = $m[3] . '-' . $m[2] . '-' . $m[1] . ' ' . (preg_match('/^\d{2}:\d{2}/', (string) ($dispEv['horEvento'] ?? '')) ? $dispEv['horEvento'] : '00:00:00');
    }
    // Histórico (CRONOLÓGICO, compacto) para la web "Localiza tu envío".
    $evJson = json_encode(array_map(function ($e) {
        return array('f' => $e['fecEvento'] ?? '', 'h' => substr((string) ($e['horEvento'] ?? ''), 0, 5),
                     'fase' => $e['desFase'] ?? '', 'txt' => $e['desTextoResumen'] ?? '');
    }, array_slice(correos::eventosOrdenados($dispRes), -12)), JSON_UNESCAPED_UNICODE);

    $multi = $multiCount > 1 ? ' (' . $multiCount . ' bultos)' : '';
    echo '  - [' . $p['tipo'] . '] ' . $p['ref'] . $multi . ': ' . $desc
        . ($anyReturn ? '  ↩ DEVOLUCIÓN' : ($entregado ? '  ✅ ENTREGADO' : '')) . $br;
    if ($anyCheckFail) echo '    ⚠ ' . $p['ref'] . ': algún bulto sin verificar (' . $primaryErr . ') → se reintenta' . $br;
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
        // Salida: la DEVOLUCIÓN gana sobre ENTREGADO (un return-to-sender cierra en
        // ENTREGADO de vuelta a Francobordo → NO completar el pedido del cliente).
        if ($anyReturn && (int) $p['orders_id'] > 0) {
            // Nota ÚNICA (idempotente): no reinsertar cada hora si ya está anotado este envío.
            $yaNota = tep_db_query("SELECT 1 FROM " . TABLE_ORDERS_STATUS_HISTORY . " WHERE orders_id = " . (int) $p['orders_id'] . " AND comments LIKE 'Correos: el envío " . tep_db_input($p['shipment_code']) . "%DEVOLUCIÓN al remitente%' LIMIT 1");
            if (!tep_db_fetch_array($yaNota)) {
                $devueltos++;
                tep_db_query("INSERT INTO " . TABLE_ORDERS_STATUS_HISTORY . " SET orders_id = " . (int) $p['orders_id'] . ", orders_status_id = 5, date_added = now(), customer_notified = 0, comments = 'Correos: el envío " . tep_db_input($p['shipment_code']) . " figura EN DEVOLUCIÓN al remitente. NO se completa; revisar.'");
            }
        } elseif ($entregado && (int) $p['orders_id'] > 0) {
            // Guard anti-fecha-absurda: no completar si la entrega es ANTERIOR a la
            // creación del envío (evento stale / código reusado).
            $okFecha = true;
            if ($fevento && !empty($p['date_added']) && strtotime($fevento) < strtotime($p['date_added']) - 86400) {
                $okFecha = false;
                echo '    ⚠ ' . $p['ref'] . ': ENTREGADO con fecha ' . $fevento . ' anterior al envío ' . $p['date_added'] . ' → NO se completa (revisar)' . $br;
            }
            if ($okFecha) $completar[] = array('oID' => (int) $p['orders_id'], 'estado' => 'entregado', 'fecha' => (string) $fevento);
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

/* ---- M5: reintento de anulaciones ENCOLADAS (cancel_requested_at) ----
 * El módulo RMA hace un intento rápido y, si el endpoint inestable falla, encola
 * aquí. Reintentamos cada hora hasta lograrlo; alerta si lleva >24h sin éxito. */
$cancelOk = 0; $cancelPend = 0; $cancelAlert = 0;
if (!$dry) {
    $c->setTimeout(15);   // anulación: timeout corto (los fallos del endpoint son rápidos) → acota el peor caso
    $cq = tep_db_query(
        "SELECT id, id_rma, orders_id, tipo, shipment_code, package_code, response_json, cancel_requested_at
           FROM correos_shipments
          WHERE cancel_requested_at IS NOT NULL AND cancelled_at IS NULL AND ok = 1"
    );
    while ($cs = tep_db_fetch_array($cq)) {
        $cpkgs = array();
        $crj = json_decode((string) ($cs['response_json'] ?? ''), true);
        $cpk = $crj['data']['shipments'][0]['packages'] ?? null;
        if (is_array($cpk)) foreach ($cpk as $cpp) if (!empty($cpp['packageCode'])) $cpkgs[] = (string) $cpp['packageCode'];
        if (!$cpkgs && !empty($cs['package_code'])) $cpkgs = array((string) $cs['package_code']);
        $cpkgs = array_values(array_unique($cpkgs));
        if (!$cpkgs) continue;

        // 4 intentos por pasada con timeout corto (15s): el endpoint de anulación es
        // inestable pero falla RÁPIDO, así que varios intentos por pasada son baratos y
        // resuelven en ~1-2h en vez de ~8h; el peor caso (timeouts) queda acotado a ~66s
        // y los envíos encolados son raros (solo cancelaciones RMA fallidas). NOTA:
        // multibulto-parcial (un bulto anulado, otro no) no es alcanzable hoy (cancel
        // solo se expone en RMA = 1 bulto) → quedaría en ALERTA >24h, no roto en silencio.
        $allAnn = true;
        foreach ($cpkgs as $cpc) { if (!correos::annulmentOk($c->annulment($cpc, 'spa', 4))) $allAnn = false; }

        if ($allAnn) {
            tep_db_query("UPDATE correos_shipments SET cancelled_at = now() WHERE id = " . (int) $cs['id']);
            $cancelOk++;
            echo '  ✔ anulación completada (reintento): ' . $cs['shipment_code'] . $br;
            if ((int) $cs['id_rma'] > 0) {   // discriminante robusto (no depende del default de 'tipo')
                $rr = tep_db_fetch_array(tep_db_query('SELECT status FROM ' . TABLE_RMA . ' WHERE id_rma = ' . (int) $cs['id_rma']));
                tep_db_perform(TABLE_RMA_STATUS_HISTORY, array(
                    'email_text' => '', 'notify' => 0, 'id_rma' => (int) $cs['id_rma'],
                    'id_status' => $rr ? (int) $rr['status'] : 0, 'message' => '',
                    'private_message' => 'Correos: devolución ' . $cs['shipment_code'] . ' anulada (reintento automático).',
                    'date_added' => 'now()',
                ));
            } elseif ((int) $cs['orders_id'] > 0) {
                tep_db_query("INSERT INTO " . TABLE_ORDERS_STATUS_HISTORY . " SET orders_id = " . (int) $cs['orders_id'] . ", orders_status_id = 5, date_added = now(), customer_notified = 0, comments = 'Correos: envío " . tep_db_input($cs['shipment_code']) . " anulado (reintento automático).'");
            }
        } else {
            $cancelPend++;
            if (strtotime($cs['cancel_requested_at']) < time() - 86400) {
                $cancelAlert++;
                echo '  !!! ALERTA anulación: ' . $cs['shipment_code'] . ' lleva >24h sin poder anular → anular a mano' . $br;
            } else {
                echo '  · anulación pendiente (se reintenta): ' . $cs['shipment_code'] . $br;
            }
        }
    }
}

echo $br . "correos_tracking: $upserts filas · RMA entregados: $entregasRma · pedidos completados: " . count($content) . " · en devolución: $devueltos · anulaciones: $cancelOk ok/$cancelPend pend" . ($cancelAlert ? "/$cancelAlert ALERTA" : "") . " · errores: $errores" . $br;
echo 'FIN - ' . date('d/m/Y H:i:s') . $br;
