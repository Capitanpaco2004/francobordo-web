<?php
/**
 * Tracking automático Correos Express vía API (sustituye el import manual del xlsx).
 *
 * - Consulta seguimientoEnviosFechas (1 llamada → todos los envíos recientes con su estado).
 * - Actualiza la tabla cex_tracking (para mostrar estados en web/RMA).
 * - Para pedidos en estado "Enviado" (5) cuyo envío F{oid} está ENTREGADO (12), reutiliza
 *   importador::saveData() → pasa el pedido a "Entregado" (3) + email al cliente + histórico
 *   + sistema de opiniones (idéntico al import manual).
 *
 * Endpoint público (como cron_transportistas.php). Protegido por token.
 * Uso:  /cron_cex_tracking.php?token=XXX           (live)
 *       /cron_cex_tracking.php?token=XXX&dry=1      (dry-run: no cambia estados ni envía emails)
 *       &days=N  (rango de días a consultar, def. 40, máx 90)
 *
 * Ver memoria francobordo_correos_express_api.
 */

use util\tools as tools;
include('includes/classes/tools.php');

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');
$br = php_sapi_name() === 'cli' ? "\n" : "<br>\n";

// --- Token de acceso ---
define('CEX_TRACKING_TOKEN', 'cextrk_7f3a9c21b8e4');
if (($_GET['token'] ?? '') !== CEX_TRACKING_TOKEN) {
    http_response_code(403);
    echo 'forbidden';
    exit;
}

$dry    = (($_GET['dry'] ?? '') === '1');
$silent = (($_GET['silent'] ?? '') === '1'); // regularización: completa SIN enviar email
$days   = isset($_GET['days']) ? max(1, min(90, (int) $_GET['days'])) : 40;

// --- Bootstrap admin (saltar login, igual que cron_transportistas.php) ---
$sAdminPath = tools::getPathAdmin();
$_SERVER['PHP_SELF']        = 'cron_cex_tracking.php';
$_SERVER['SCRIPT_FILENAME'] = 'cron_cex_tracking.php';
$_SERVER['SCRIPT_NAME']     = str_replace('cron_cex_tracking.php', '', $_SERVER['SCRIPT_NAME']) . $sAdminPath . '/cron_cex_tracking.php';
chdir($sAdminPath);
require('includes/application_top.php');
define('PATH_UPDATE_MASIVE_ORDERS', realpath(DIR_FS_ADMIN) . '/includes/modules/update_masive_orders/');
require_once(PATH_UPDATE_MASIVE_ORDERS . 'importador.php');
require_once(DIR_FS_CATALOG . 'includes/classes/correos_express.php');

echo 'CEX tracking ' . ($dry ? '[DRY-RUN] ' : '') . date('d/m/Y H:i:s') . $br;

// --- Entorno CEX (cex_config) ---
$envQ = tep_db_query("SELECT config_value FROM cex_config WHERE config_key = 'env'");
$env  = tep_db_num_rows($envQ) ? tep_db_fetch_array($envQ)['config_value'] : 'test';
$cex  = new correos_express($env);
echo "Entorno CEX: $env | rango: $days días" . $br;

// --- 1) Pull masivo de estados ---
$res = $cex->seguimientoEnviosFechas(date('Y-m-d', strtotime("-$days days")), date('Y-m-d'));
if (empty($res['ok']) || empty($res['data']['seguimientoEnvioFecha'])) {
    echo 'Sin datos de seguimiento (error=' . ($res['data']['error'] ?? $res['error']) . ')' . $br;
    echo 'FIN' . $br;
    exit;
}
$lista = $res['data']['seguimientoEnvioFecha'];
echo 'Envíos recibidos: ' . count($lista) . $br;

// --- 2) Upsert cex_tracking + recolectar entregados F{oid} ---
$entregadosOid = array(); // oID => true
$upserts = 0;
foreach ($lista as $e) {
    $ref   = trim((string) ($e['referencia'] ?? ''));
    if ($ref === '') continue;
    $code  = (string) ($e['estadoEnvio'] ?? '');
    $num   = (string) ($e['numEnvio'] ?? '');
    $desc  = correos_express::estadoDescripcion($code);
    $entregado = ($code === correos_express::ESTADO_ENTREGADO) ? 1 : 0;

    $sql = "INSERT INTO cex_tracking (referencia, num_envio, estado_code, estado_desc, entregado, last_checked) VALUES ("
         . "'" . tep_db_input($ref) . "', '" . tep_db_input($num) . "', '" . tep_db_input($code) . "', '" . tep_db_input($desc) . "', $entregado, now()) "
         . "ON DUPLICATE KEY UPDATE num_envio=VALUES(num_envio), estado_code=VALUES(estado_code), estado_desc=VALUES(estado_desc), entregado=VALUES(entregado), last_checked=now()";
    tep_db_query($sql);
    $upserts++;

    // Referencia de pedido de salida: F{digits}
    if ($entregado && preg_match('/^F(\d+)$/', $ref, $mm)) {
        $entregadosOid[(int) $mm[1]] = true;
    }
}
echo "cex_tracking actualizado: $upserts filas. Pedidos F entregados: " . count($entregadosOid) . $br;

// --- 3) Auto-completar: pedidos en 'Enviado' (5) cuyo envío está ENTREGADO ---
$content = array();
if (!empty($entregadosOid)) {
    $ids = implode(',', array_map('intval', array_keys($entregadosOid)));
    $q = tep_db_query("SELECT orders_id FROM " . TABLE_ORDERS . " WHERE orders_status = 5 AND orders_id IN ($ids)");
    while ($o = tep_db_fetch_array($q)) {
        $content[] = array('oID' => (int) $o['orders_id'], 'estado' => 'entregado', 'fecha' => '');
    }
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
        $oid = (int) $c['oID'];
        tep_db_query("UPDATE " . TABLE_ORDERS . " SET orders_status = 3, last_modified = now() WHERE orders_id = $oid AND orders_status = 5");
        tep_db_query("INSERT INTO " . TABLE_ORDERS_STATUS_HISTORY . " SET orders_id = $oid, orders_status_id = 3, date_added = now(), customer_notified = 0, comments = 'Entregado (Correos Express API — regularizacion backlog, sin notificar)'");
    }
    echo $br . 'Regularizados EN SILENCIO ' . count($content) . ' pedidos (sin email).' . $br;
} else {
    $imp = new importador();
    $imp->valor   = 'correos_express_api';
    $imp->content = $content;
    $imp->saveData(); // status 3 + email + histórico + opiniones (idéntico al import manual)
    echo $br . 'Auto-completados ' . count($content) . ' pedidos (email enviado).' . $br;
}

echo $br . 'FIN - ' . date('d/m/Y H:i:s') . $br;
