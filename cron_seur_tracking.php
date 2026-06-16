<?php
/**
 * Tracking automático SEUR vía API PIC (tracking-services/extended).
 *
 * SEUR no tiene endpoint masivo por fechas (a diferencia de CEX/Ontime):
 * se consulta POR REFERENCIA, una llamada por envío. Fuentes:
 *   - seur_shipments (envíos y devoluciones creados por NUESTRA integración
 *     API: 2shop desde Vstock, devoluciones RMA). ref = F{orders_id} / RMA*.
 *   - refresco de los pendientes ya conocidos en seur_tracking.
 * ⚠️ Los envíos de la integración VIEJA de Vstock (ALBARANES_SEUR, agencias
 * SEUR 24/13:30/48/INTERNACIONAL) NO son visibles por esta API bajo nuestra
 * cuenta (verificado 2026-06-11: ref=10361825 → 204; sin filtros devuelve
 * envíos de OTROS clientes con la misma ref). Esos siguen dependiendo del
 * fichero de situaciones hasta que el almacén use las agencias API.
 *
 * - Upsert en seur_tracking (referencia PK). Entregado por descripción de
 *   situación (catálogo de eventCodes no publicado por SEUR; se guarda el
 *   histórico completo en eventos_json para auditar/afinar).
 * - Pedidos en "Enviado" (5) con envío ENTREGADO → importador::saveData()
 *   (status 3 + email + histórico + opiniones, mismo flujo que CEX/Ontime).
 *
 * Uso:  /cron_seur_tracking.php?token=XXX            (live)
 *       /cron_seur_tracking.php?token=XXX&dry=1      (dry-run: no escribe nada)
 *       &days=N     antigüedad máx. de envíos a vigilar (def. 60, máx 120)
 *       &refresh=N  máx. de llamadas API por pasada (def. 100, máx 300)
 *       &silent=1   regulariza backlog: status 3 SIN email
 *       &force=1    permite ejecutar aunque seur_config no sea 'pro'
 *
 * Ver memoria francobordo_seur_api.
 */

use util\tools as tools;
include('includes/classes/tools.php');

error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');
$br = php_sapi_name() === 'cli' ? "\n" : "<br>\n";

// --- Token de acceso ---
define('SEUR_TRACKING_TOKEN', 'seurtrk_9e4c1f7a2b');
if (($_GET['token'] ?? '') !== SEUR_TRACKING_TOKEN) {
    http_response_code(403);
    echo 'forbidden';
    exit;
}

$dry        = (($_GET['dry'] ?? '') === '1');
$silent     = (($_GET['silent'] ?? '') === '1');
$force      = (($_GET['force'] ?? '') === '1');
$days       = isset($_GET['days']) ? max(1, min(120, (int) $_GET['days'])) : 60;
$maxRefresh = isset($_GET['refresh']) ? max(1, min(300, (int) $_GET['refresh'])) : 100;

// --- Bootstrap admin (saltar login, igual que cron_cex_tracking.php) ---
$sAdminPath = tools::getPathAdmin();
$_SERVER['PHP_SELF']        = 'cron_seur_tracking.php';
$_SERVER['SCRIPT_FILENAME'] = 'cron_seur_tracking.php';
$_SERVER['SCRIPT_NAME']     = str_replace('cron_seur_tracking.php', '', $_SERVER['SCRIPT_NAME']) . $sAdminPath . '/cron_seur_tracking.php';
chdir($sAdminPath);
require('includes/application_top.php');
define('PATH_UPDATE_MASIVE_ORDERS', realpath(DIR_FS_ADMIN) . '/includes/modules/update_masive_orders/');
require_once(PATH_UPDATE_MASIVE_ORDERS . 'importador.php');
require_once(DIR_FS_CATALOG . 'includes/classes/seur.php');

echo 'SEUR tracking ' . ($dry ? '[DRY-RUN] ' : '') . date('d/m/Y H:i:s') . $br;

// --- Entorno (seur_config) ---
$envQ = tep_db_query("SELECT config_value FROM seur_config WHERE config_key = 'env'");
$env  = tep_db_num_rows($envQ) ? tep_db_fetch_array($envQ)['config_value'] : 'pre';
echo "Entorno SEUR: $env | vigilancia: $days días | tope llamadas: $maxRefresh" . $br;

if ($env !== 'pro' && !$force) {
    echo 'Entorno != pro y sin &force=1 → no se hace nada (evita mezclar PRE con pedidos reales).' . $br;
    echo 'FIN' . $br;
    exit;
}

$seur = new seur($env);
$seur->setTimeout(20);

/** ¿La descripción de situación significa ENTREGADO? (catálogo no publicado:
 *  por texto, con guardas para "NO ENTREGADO"/"SIN ENTREGA"). */
function seurEsEntregado($desc) {
    $d = mb_strtoupper((string) $desc);
    if (strpos($d, 'ENTREGAD') === false) return false;
    if (preg_match('/\b(NO|SIN)\s+ENTREG/u', $d)) return false;
    return true;
}

/** ¿Anulado? (LK522 "ENVIO ANULADO" / GI646 recogida cancelada). */
function seurEsAnulado($code, $desc) {
    $d = mb_strtoupper((string) $desc);
    return (strpos($d, 'ANULAD') !== false || strpos($d, 'CANCELAD') !== false);
}

/** Upsert en seur_tracking desde una entrada ExtendedSituation. Devuelve referencia. */
function seurTrackUpsert($ref, $e, $dry) {
    $sits = is_array($e['situations'] ?? null) ? $e['situations'] : array();
    if (!$sits) return '';
    $last  = end($sits);
    $code  = (string) ($last['eventCode'] ?? '');
    $desc  = (string) ($last['description'] ?? '');
    $entregado = 0;
    $fent = '';
    $evs  = array();
    foreach ($sits as $sit) {
        $sd = (string) ($sit['description'] ?? '');
        $sf = substr(str_replace('T', ' ', (string) ($sit['situationDate'] ?? '')), 0, 19);
        if (seurEsEntregado($sd)) { $entregado = 1; $fent = $sf; }
        $evs[] = array('f' => substr($sf, 0, 16), 't' => (string) ($sit['eventCode'] ?? ''), 'd' => $sd);
    }
    $ecb = '';
    if (is_array($e['packs'] ?? null) && !empty($e['packs'][0]['ecb'])) $ecb = (string) $e['packs'][0]['ecb'];
    if ($dry) return $ref;
    $sql = "INSERT INTO seur_tracking (referencia, ecb, estado_code, estado_desc, entregado, fecha_entrega, eventos_json, last_checked) VALUES ("
         . "'" . tep_db_input($ref) . "', '" . tep_db_input($ecb) . "', '" . tep_db_input(substr($code, 0, 8)) . "', '" . tep_db_input(substr($desc, 0, 80)) . "', $entregado, "
         . ($fent !== '' ? "'" . tep_db_input($fent) . "'" : "NULL") . ", "
         . "'" . tep_db_input(json_encode($evs, JSON_UNESCAPED_UNICODE)) . "', now()) "
         . "ON DUPLICATE KEY UPDATE ecb=VALUES(ecb), estado_code=VALUES(estado_code), estado_desc=VALUES(estado_desc), entregado=VALUES(entregado), "
         . "fecha_entrega=COALESCE(VALUES(fecha_entrega), fecha_entrega), eventos_json=VALUES(eventos_json), last_checked=now()";
    tep_db_query($sql);
    return $ref;
}

// --- 1) Lista de trabajo: envíos/devoluciones API pendientes, los menos consultados primero ---
$pendiente = "(t.referencia IS NULL OR (t.entregado = 0 AND t.estado_desc NOT LIKE '%ANULAD%' AND t.estado_desc NOT LIKE '%CANCELAD%'))";
$q = tep_db_query(
    "SELECT s.ref, s.tipo, MAX(o.delivery_postcode) AS cp, MAX(o.delivery_city) AS city FROM seur_shipments s " .
    "LEFT JOIN seur_tracking t ON t.referencia = s.ref " .
    "LEFT JOIN " . TABLE_ORDERS . " o ON o.orders_id = s.orders_id " .
    "WHERE s.ok = 1 AND s.cancelled_at IS NULL AND s.cancel_requested_at IS NULL AND s.entorno = '" . tep_db_input($env) . "' AND s.ref <> '' " .
    "AND s.date_added > NOW() - INTERVAL " . (int) $days . " DAY AND $pendiente " .
    "GROUP BY s.ref ORDER BY COALESCE(MAX(t.last_checked), '2000-01-01') ASC LIMIT " . (int) $maxRefresh
);
$worklist = array();
while ($row = tep_db_fetch_array($q)) $worklist[$row['ref']] = array('tipo' => $row['tipo'], 'cp' => (string) $row['cp'], 'city' => (string) $row['city']);
// Integracion VIEJA de Vstock (agencias SEUR 24/48/INTERNACIONAL): pedidos en
// "Enviado" cuyo comentario lleva la URL seur sin F. Su ref de red es el oid a
// pelo. Esto sustituye al fichero de situaciones tambien para ese canal.
$q = tep_db_query(
    "SELECT DISTINCT o.orders_id, o.delivery_postcode, o.delivery_city FROM " . TABLE_ORDERS_STATUS_HISTORY . " h " .
    "JOIN " . TABLE_ORDERS . " o ON o.orders_id = h.orders_id " .
    "LEFT JOIN seur_tracking t ON t.referencia = CAST(o.orders_id AS CHAR) " .
    "WHERE o.orders_status = 5 AND h.comments LIKE '%seur.com/seguimiento%' AND h.comments NOT LIKE '%seguimiento/F%' " .
    "AND h.date_added > NOW() - INTERVAL " . (int) $days . " DAY AND $pendiente LIMIT " . (int) $maxRefresh
);
while ($row = tep_db_fetch_array($q)) {
    if (count($worklist) >= $maxRefresh) break;
    $worklist[(string) $row['orders_id']] = array('tipo' => 'envio', 'cp' => (string) $row['delivery_postcode'], 'city' => (string) $row['delivery_city']);
}
// Filas ya conocidas en seur_tracking aun pendientes (p.ej. cacheadas por la
// consulta en vivo del admin antes de que el pedido tenga comentario status 5).
$q = tep_db_query(
    "SELECT t.referencia, o.delivery_postcode, o.delivery_city FROM seur_tracking t " .
    "JOIN " . TABLE_ORDERS . " o ON o.orders_id = CAST(IF(t.referencia LIKE 'F%', SUBSTRING(t.referencia, 2), t.referencia) AS UNSIGNED) " .
    "WHERE t.referencia REGEXP '^F?[0-9]+(R[0-9]+)?$' AND t.entregado = 0 " .
    "AND t.estado_desc NOT LIKE '%ANULAD%' AND t.estado_desc NOT LIKE '%CANCELAD%' " .
    "AND t.date_added > NOW() - INTERVAL " . (int) $days . " DAY LIMIT " . (int) $maxRefresh
);
while ($row = tep_db_fetch_array($q)) {
    if (count($worklist) >= $maxRefresh) break;
    if (isset($worklist[(string) $row['referencia']])) continue;
    $worklist[(string) $row['referencia']] = array('tipo' => 'envio', 'cp' => (string) $row['delivery_postcode'], 'city' => (string) $row['delivery_city']);
}
echo 'Referencias a consultar: ' . count($worklist) . $br;

$consultadas = 0; $conDatos = 0; $sinDatos = 0; $errores = 0;
foreach ($worklist as $ref => $info) {
    $ref = (string) $ref;
    $r = $seur->trackingExtended($ref);
    $consultadas++;
    // Los INTERNACIONALES no aparecen con filtros de cuenta (la red los re-registra
    // bajo la businessUnit de destino) -> reintento sin filtros y desambiguo por CP.
    if ((int) $r['http'] === 204) { $r = $seur->trackingExtended($ref, 'REFERENCE', false); }
    if ((int) $r['http'] === 204) { $sinDatos++; continue; }       // aún sin estados (o ref no en la red)
    if (!$r['ok']) {
        $errores++;
        echo "  $ref ERROR http={$r['http']}: " . substr((string) $r['raw'], 0, 120) . $br;
        continue;
    }
    $d = seur::payload($r);
    if (!is_array($d) || !$d) { $sinDatos++; continue; }
    // Puede haber varias entradas (Exp duplicadas con distintos hitos, Rec de la
    // recogida diaria, o colisiones de ref de OTROS clientes en consultas sin
    // filtros): filtro por tipo esperado + CP de destino del pedido, y FUSIONO
    // las situaciones de todas las entradas válidas.
    $tipoEsperado = ($info['tipo'] === 'recogida') ? 'Rec' : 'Exp';
    $cpPedido   = preg_replace('/[^0-9A-Za-z]/', '', (string) $info['cp']);
    $cityPedido = preg_replace('/[^0-9A-Za-z]/', '', (string) ($info['city'] ?? ''));
    $candidatas = array();
    foreach ($d as $e) {
        if (($e['type'] ?? '') !== $tipoEsperado) continue;
        // Destino de la entrada vs pedido: vale el CP o la poblacion (hay pedidos
        // con la ciudad metida en el campo CP, p.ej. "MUELHEIM").
        if ($cpPedido !== '' || $cityPedido !== '') {
            $cpEntrada   = preg_replace('/[^0-9A-Za-z]/', '', (string) ($e['postalCode'] ?? ''));
            $cityEntrada = preg_replace('/[^0-9A-Za-z]/', '', (string) ($e['cityName'] ?? ''));
            $okCp   = ($cpPedido !== '' && $cpEntrada !== '' && (stripos($cpPedido, $cpEntrada) !== false || stripos($cpEntrada, $cpPedido) !== false));
            $okCity = false;
            foreach (array($cityPedido, $cpPedido) as $pedTxt) {
                if ($pedTxt !== '' && $cityEntrada !== '' && (stripos($cityEntrada, $pedTxt) !== false || stripos($pedTxt, $cityEntrada) !== false)) $okCity = true;
            }
            if (!$okCp && !$okCity) continue;
        }
        $candidatas[] = $e;
    }
    if (!$candidatas) { $sinDatos++; continue; }
    $sits = array(); $vistos = array(); $packs = array();
    foreach ($candidatas as $e) {
        foreach ((array) ($e['situations'] ?? array()) as $sit) {
            $k = ($sit['eventCode'] ?? '') . '|' . ($sit['situationDate'] ?? '');
            if (isset($vistos[$k])) continue;
            $vistos[$k] = true;
            $sits[] = $sit;
        }
        if (!$packs && !empty($e['packs'])) $packs = $e['packs'];
    }
    usort($sits, function ($a, $b) { return strcmp((string) ($a['situationDate'] ?? ''), (string) ($b['situationDate'] ?? '')); });
    $fusionada = array('situations' => $sits, 'packs' => $packs);
    if (seurTrackUpsert($ref, $fusionada, $dry) !== '') $conDatos++;
}
echo "Consultadas: $consultadas | con estados: $conDatos | sin datos (204): $sinDatos | errores: $errores" . $br;

// --- 2) Auto-completar: pedidos en 'Enviado' (5) con envío ENTREGADO ---
// referencia 'F{orders_id}' → orders_id. Fecha del cambio = fecha real de entrega.
$content = array();
$q = tep_db_query(
    "SELECT t.referencia, t.fecha_entrega FROM seur_tracking t " .
    "INNER JOIN " . TABLE_ORDERS . " o ON o.orders_id = CAST(IF(t.referencia LIKE 'F%', SUBSTRING(t.referencia, 2), t.referencia) AS UNSIGNED) " .
    "WHERE t.referencia REGEXP '^F?[0-9]+(R[0-9]+)?$' AND t.entregado = 1 AND o.orders_status = 5 " .
    // No auto-completar (ni email) un pedido cuyo envío está pendiente de ANULAR (M5):
    "AND NOT EXISTS (SELECT 1 FROM seur_shipments ss WHERE ss.orders_id = o.orders_id AND ss.cancel_requested_at IS NOT NULL AND ss.cancelled_at IS NULL)"
);
while ($row = tep_db_fetch_array($q)) {
    $oid = (substr($row['referencia'], 0, 1) === 'F') ? (int) substr($row['referencia'], 1) : (int) $row['referencia'];
    $content[] = array('oID' => $oid, 'estado' => 'entregado', 'fecha' => (string) ($row['fecha_entrega'] ?? ''));
}
echo 'Pedidos en "Enviado" a completar: ' . count($content) . $br;
foreach ($content as $c) echo '  - pedido ' . $c['oID'] . $br;

if ($dry) {
    echo $br . '[DRY-RUN] No se cambian estados ni se envían emails.' . $br;
} elseif (empty($content)) {
    echo $br . 'Nada que completar.' . $br;
} elseif ($silent) {
    // Regularización silenciosa del backlog: status 3 (Entregado) SIN email ni opiniones.
    foreach ($content as $c) {
        $oid   = (int) $c['oID'];
        $fecha = ($c['fecha'] !== '') ? "'" . tep_db_input($c['fecha']) . "'" : 'now()';
        tep_db_query("UPDATE " . TABLE_ORDERS . " SET orders_status = 3, last_modified = $fecha WHERE orders_id = $oid AND orders_status = 5");
        tep_db_query("INSERT INTO " . TABLE_ORDERS_STATUS_HISTORY . " SET orders_id = $oid, orders_status_id = 3, date_added = $fecha, customer_notified = 0, comments = 'Entregado (SEUR API — regularizacion backlog, sin notificar)'");
    }
    echo $br . 'Regularizados EN SILENCIO ' . count($content) . ' pedidos (sin email).' . $br;
} else {
    $imp = new importador();
    $imp->valor   = 'seur_api';
    $imp->content = $content;
    $imp->saveData(); // status 3 + email + histórico + opiniones (idéntico a CEX/Ontime)
    echo $br . 'Auto-completados ' . count($content) . ' pedidos (email enviado).' . $br;
}

/* --- M5: reintento de anulaciones SEUR ENCOLADAS (cancel_requested_at) ---
 * El módulo RMA / panel hace un intento y, si SEUR devuelve error DURO (el envío
 * sigue vivo), encola aquí en vez de marcar anulado a ciegas. Reintentamos cada
 * hora; alerta si lleva >24h sin lograrlo (p.ej. ya en tránsito → gestión manual). */
$cancelOk = 0; $cancelPend = 0; $cancelAlert = 0;
if (!$dry) {
    $seur->setTimeout(15);
    $cq = tep_db_query(
        "SELECT id, id_rma, orders_id, tipo, shipment_code, cancel_requested_at FROM seur_shipments " .
        "WHERE cancel_requested_at IS NOT NULL AND cancelled_at IS NULL AND ok = 1 AND entorno = '" . tep_db_input($env) . "'"
    );
    while ($cs = tep_db_fetch_array($cq)) {
        if (empty($cs['shipment_code'])) continue;
        $res = ($cs['tipo'] === 'recogida') ? $seur->cancelCollection($cs['shipment_code']) : $seur->cancelShipment($cs['shipment_code']);
        $tt  = ($cs['tipo'] === 'recogida') ? 'devolucion' : 'envio';
        if (seur::cancelOk($res)) {
            tep_db_query("UPDATE seur_shipments SET cancelled_at = now() WHERE id = " . (int) $cs['id']);
            $cancelOk++;
            echo '  ✔ anulacion SEUR completada (reintento): ' . $cs['shipment_code'] . $br;
            if ((int) $cs['id_rma'] > 0) {
                $rr = tep_db_fetch_array(tep_db_query('SELECT status FROM ' . TABLE_RMA . ' WHERE id_rma = ' . (int) $cs['id_rma']));
                tep_db_perform(TABLE_RMA_STATUS_HISTORY, array(
                    'email_text' => '', 'notify' => 0, 'id_rma' => (int) $cs['id_rma'],
                    'id_status' => $rr ? (int) $rr['status'] : 0, 'message' => '',
                    'private_message' => 'SEUR: ' . $tt . ' ' . $cs['shipment_code'] . ' anulado (reintento automatico).',
                    'date_added' => 'now()',
                ));
            } elseif ((int) $cs['orders_id'] > 0) {
                tep_db_query("INSERT INTO " . TABLE_ORDERS_STATUS_HISTORY . " SET orders_id = " . (int) $cs['orders_id'] . ", orders_status_id = 5, date_added = now(), customer_notified = 0, comments = 'SEUR: " . $tt . " " . tep_db_input($cs['shipment_code']) . " anulado (reintento automatico).'");
            }
        } else {
            $cancelPend++;
            $dd   = seur::payload($res);
            $derr = (is_array($dd) && !empty($dd[0]['description'])) ? $dd[0]['description'] : seur::primerError($res);
            if (strtotime($cs['cancel_requested_at']) < time() - 86400) {
                $cancelAlert++;
                echo '  !!! ALERTA anulacion SEUR: ' . $cs['shipment_code'] . ' lleva >24h sin poder anular (' . $derr . ') -> gestionar a mano' . $br;
            } else {
                echo '  · anulacion SEUR pendiente (se reintenta): ' . $cs['shipment_code'] . ' (' . $derr . ')' . $br;
            }
        }
    }
    if ($cancelOk || $cancelPend) echo 'Anulaciones SEUR: ' . $cancelOk . ' ok / ' . $cancelPend . ' pend' . ($cancelAlert ? ' / ' . $cancelAlert . ' ALERTA' : '') . $br;
}

echo $br . 'FIN - ' . date('d/m/Y H:i:s') . $br;
