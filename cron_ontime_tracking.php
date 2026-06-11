<?php
/**
 * Tracking automático Ontime vía API (WS detalleExpediciones de GTS/Alertran).
 *
 * - Barre por rango de fechas (ventanas de 7 días, paginadas de 150 en 150)
 *   todas las expediciones del cliente → upsert en ontime_tracking.
 *   La referencia de cliente de los envíos de salida ES el orders_id de la
 *   web (Vstock manda el nº de pedido como referencia).
 * - Refresca individualmente (por EXPE_NUMERO) las expediciones pendientes
 *   que ya salieron de la ventana de fechas.
 * - Para pedidos en "Enviado" (5) cuya expedición está ENTREGADA, reutiliza
 *   importador::saveData() → pasa el pedido a "Entregado" (3) + email al
 *   cliente + histórico + sistema de opiniones (mismo flujo que CEX).
 *
 * Endpoint público (como cron_cex_tracking.php). Protegido por token.
 * Uso:  /cron_ontime_tracking.php?token=XXX            (live)
 *       /cron_ontime_tracking.php?token=XXX&dry=1      (dry-run)
 *       &days=N    rango de días a barrer (def. 7, máx 90; se trocea en ventanas de 7)
 *       &silent=1  regulariza backlog: status 3 SIN email
 *       &force=1   permite ejecutar aunque el entorno no sea 'pro'
 *
 * El entorno se lee de ontime_config (clave 'env', def. 'pre'). Mientras no
 * haya credenciales PRO el cron sale sin hacer nada salvo con &force=1
 * (para no machacar PRE ni tocar pedidos con datos de prueba).
 *
 * Ver memoria francobordo_ontime_api.
 */

use util\tools as tools;
include('includes/classes/tools.php');

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');
$br = php_sapi_name() === 'cli' ? "\n" : "<br>\n";

// --- Token de acceso ---
define('ONTIME_TRACKING_TOKEN', 'onttrk_4b9e2d17c5a3');
if (($_GET['token'] ?? '') !== ONTIME_TRACKING_TOKEN) {
    http_response_code(403);
    echo 'forbidden';
    exit;
}

$dry    = (($_GET['dry'] ?? '') === '1');
$silent = (($_GET['silent'] ?? '') === '1');
$force  = (($_GET['force'] ?? '') === '1');
$days   = isset($_GET['days']) ? max(1, min(90, (int) $_GET['days'])) : 7;
$maxRefresh = isset($_GET['refresh']) ? max(0, min(500, (int) $_GET['refresh'])) : 100;

// --- Bootstrap admin (saltar login, igual que cron_cex_tracking.php) ---
$sAdminPath = tools::getPathAdmin();
$_SERVER['PHP_SELF']        = 'cron_ontime_tracking.php';
$_SERVER['SCRIPT_FILENAME'] = 'cron_ontime_tracking.php';
$_SERVER['SCRIPT_NAME']     = str_replace('cron_ontime_tracking.php', '', $_SERVER['SCRIPT_NAME']) . $sAdminPath . '/cron_ontime_tracking.php';
chdir($sAdminPath);
require('includes/application_top.php');
define('PATH_UPDATE_MASIVE_ORDERS', realpath(DIR_FS_ADMIN) . '/includes/modules/update_masive_orders/');
require_once(PATH_UPDATE_MASIVE_ORDERS . 'importador.php');
require_once(DIR_FS_CATALOG . 'includes/classes/ontime.php');

echo 'Ontime tracking ' . ($dry ? '[DRY-RUN] ' : '') . date('d/m/Y H:i:s') . $br;

// --- Entorno (ontime_config) ---
$envQ = tep_db_query("SELECT config_value FROM ontime_config WHERE config_key = 'env'");
$env  = tep_db_num_rows($envQ) ? tep_db_fetch_array($envQ)['config_value'] : 'pre';
$ont  = new ontime($env);
echo "Entorno Ontime: $env | cliente: " . $ont->getCliente() . " | rango: $days días" . $br;

if ($env !== 'pro' && !$force) {
    echo 'Entorno != pro y sin &force=1 → no se hace nada (evita mezclar datos de PRE con pedidos reales).' . $br;
    echo 'FIN' . $br;
    exit;
}
if (!$ont->hasCredentials()) {
    echo 'SIN CREDENCIALES para el entorno ' . $env . ' → abortando.' . $br;
    echo 'FIN' . $br;
    exit;
}

/** Upsert de una expedición en ontime_tracking. Devuelve referencia o ''. */
function ontimeTrackUpsert($e, $dry, $br) {
    $ref = trim((string) ($e['referenciaCliente'] ?? ''));
    if ($ref === '') return '';
    $expe   = (string) ($e['expeNumero'] ?? '');
    $loc    = (string) ($e['localizador'] ?? '');
    $code   = strtoupper(trim((string) ($e['estado'] ?? '')));
    $desc   = ontime::estadoDescripcion($code);
    $entregado = ontime::esEntregado($code) ? 1 : 0;
    $fexp   = substr((string) ($e['fechaExpedicion'] ?? ''), 0, 19); // AAAA-MM-DD HH:MM:SS.0 → sin .0
    // Fecha REAL de entrega: del último evento de tipo entregado (ENTR es
    // el definitivo y llega el último; DIAE/EAGE/EFEC valen de respaldo).
    // De paso, guardamos el histórico de eventos para el timeline del
    // cliente (sin CMAI, que son avisos de email internos de Ontime).
    $fent = '';
    $evs  = array();
    foreach (ontime::normLista($e['listaEventos'] ?? null) as $ev) {
        $tipo = strtoupper(trim((string) ($ev['tipo'] ?? '')));
        $f19  = substr((string) ($ev['fecha_evento'] ?? ''), 0, 19);
        if (in_array($tipo, ontime::ESTADOS_ENTREGADO, true) && $f19 !== '') {
            $fent = $f19;
        }
        if ($tipo === 'CMAI') continue;
        $evs[] = array(
            'f' => substr($f19, 0, 16),
            't' => $tipo,
            'd' => (string) ($ev['descripcion'] ?? ''),
            'g' => (string) ($ev['delegacion'] ?? ''),
        );
    }
    $evJson = $evs ? json_encode($evs, JSON_UNESCAPED_UNICODE) : '';
    if ($dry) return $ref;
    $sql = "INSERT INTO ontime_tracking (referencia, expe_numero, localizador, estado_code, estado_desc, entregado, fecha_expedicion, fecha_entrega, eventos_json, last_checked) VALUES ("
         . "'" . tep_db_input($ref) . "', '" . tep_db_input($expe) . "', '" . tep_db_input($loc) . "', '" . tep_db_input($code) . "', '" . tep_db_input($desc) . "', $entregado, "
         . ($fexp !== '' ? "'" . tep_db_input($fexp) . "'" : "NULL") . ", "
         . ($fent !== '' ? "'" . tep_db_input($fent) . "'" : "NULL") . ", "
         . ($evJson !== '' ? "'" . tep_db_input($evJson) . "'" : "NULL") . ", now()) "
         . "ON DUPLICATE KEY UPDATE expe_numero=VALUES(expe_numero), localizador=VALUES(localizador), estado_code=VALUES(estado_code), estado_desc=VALUES(estado_desc), entregado=VALUES(entregado), fecha_expedicion=COALESCE(VALUES(fecha_expedicion), fecha_expedicion), fecha_entrega=COALESCE(VALUES(fecha_entrega), fecha_entrega), eventos_json=COALESCE(VALUES(eventos_json), eventos_json), last_checked=now()";
    tep_db_query($sql);
    return $ref;
}

// --- 1) Barrido por ventanas de fechas (máx 7 días por consulta del WS) ---
// Rango: últimos $days días, o explícito con &from=YYYY-MM-DD&to=YYYY-MM-DD (backfill).
$vistas  = array();   // referencias vistas en este barrido
$total   = 0;
$hoy     = strtotime(date('Y-m-d'));
$inicio  = strtotime("-" . ($days - 1) . " days");
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '')) $inicio = strtotime($_GET['from']);
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? ''))   $hoy    = min($hoy, strtotime($_GET['to']));
$cursor  = $inicio;
while ($cursor <= $hoy) {
    $finVentana = min($hoy, strtotime('+6 days', $cursor));
    $desde = date('d/m/Y', $cursor);
    $hasta = date('d/m/Y', $finVentana);
    $res = $ont->detallePorFechas($desde, $hasta);
    if (!$res['ok']) {
        echo "  ventana $desde-$hasta ERROR: " . $res['error'] . $br;
    } else {
        echo "  ventana $desde-$hasta: " . count($res['expediciones']) . ' expediciones' . $br;
        foreach ($res['expediciones'] as $e) {
            $ref = ontimeTrackUpsert($e, $dry, $br);
            if ($ref !== '') { $vistas[$ref] = true; $total++; }
        }
    }
    $cursor = strtotime('+7 days', $cursor);
}
echo "Barrido: $total expediciones (" . count($vistas) . " referencias)" . $br;

// --- 2) Refresco individual de pendientes fuera de ventana ---
$refrescadas = 0;
if ($maxRefresh > 0) {
    $q = tep_db_query("SELECT referencia, expe_numero FROM ontime_tracking WHERE entregado = 0 AND estado_code <> 'ANUL' AND expe_numero <> '' ORDER BY last_checked ASC LIMIT " . (int) $maxRefresh);
    while ($row = tep_db_fetch_array($q)) {
        if (isset($vistas[$row['referencia']])) continue; // ya actualizada en el barrido
        $e = $ont->detalleExpedicion($row['expe_numero']);
        if (is_array($e) && !empty($e['expeNumero']) && empty($e['codigo_error'])) {
            ontimeTrackUpsert($e, $dry, $br);
            $refrescadas++;
        }
    }
}
echo "Refrescadas individualmente: $refrescadas pendientes" . $br;

// --- 3) Auto-completar: pedidos en 'Enviado' (5) con expedición ENTREGADA ---
// La fecha del cambio de estado = fecha REAL de entrega de Ontime (si consta),
// para que el histórico y la línea de tiempo del cliente reflejen la entrega.
$content = array();
$q = tep_db_query("SELECT t.referencia, t.fecha_entrega FROM ontime_tracking t INNER JOIN " . TABLE_ORDERS . " o ON o.orders_id = CAST(t.referencia AS UNSIGNED) WHERE t.entregado = 1 AND t.referencia REGEXP '^[0-9]+$' AND o.orders_status = 5");
while ($row = tep_db_fetch_array($q)) {
    $content[] = array('oID' => (int) $row['referencia'], 'estado' => 'entregado', 'fecha' => (string) ($row['fecha_entrega'] ?? ''));
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
        tep_db_query("INSERT INTO " . TABLE_ORDERS_STATUS_HISTORY . " SET orders_id = $oid, orders_status_id = 3, date_added = $fecha, customer_notified = 0, comments = 'Entregado (Ontime API — regularizacion backlog, sin notificar)'");
    }
    echo $br . 'Regularizados EN SILENCIO ' . count($content) . ' pedidos (sin email).' . $br;
} else {
    $imp = new importador();
    $imp->valor   = 'ontime_api';
    $imp->content = $content;
    $imp->saveData(); // status 3 + email + histórico + opiniones (idéntico a CEX)
    echo $br . 'Auto-completados ' . count($content) . ' pedidos (email enviado).' . $br;
}

echo $br . 'FIN - ' . date('d/m/Y H:i:s') . $br;
