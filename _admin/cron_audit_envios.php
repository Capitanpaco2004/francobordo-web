<?php
/**
 * Auditoría semanal de envíos "sensibles" — _admin/cron_audit_envios.php (2026-07-10)
 *
 * Manda por email a Francisco un informe de los últimos N días (def. 7) con:
 *   1. Envíos MANUALES (sin pedido ni RMA) de SEUR / Correos Express / Correos
 *      — el vector de un posible envío "para asunto propio" — con quién lo creó
 *      (operator), destino y si ese destino se ha visto antes en algún pedido.
 *   2. Regeneraciones SEUR (ref F...Rn) marcando si la dirección difiere del pedido.
 *   3. Anulaciones SEUR con quién las hizo (cancelled_by).
 *
 * Invocación: cron semanal vía curl HTTPS con token (patrón rgpd.php):
 *   /_admin/cron_audit_envios.php?token=...&dias=7
 * Ver memoria francobordo_seur_api (auditoría envíos).
 */
$_SERVER['PHP_SELF'] = 'login.php';
$_SERVER['SCRIPT_FILENAME'] = 'login.php';
include 'includes/application_top.php';

define('AUDIT_ENVIOS_TOKEN', 'audenv_9f3c1b7a2d854e60cc21');
if (($_GET['token'] ?? '') !== AUDIT_ENVIOS_TOKEN) { http_response_code(403); die('forbidden'); }

$dias = max(1, min(31, (int) ($_GET['dias'] ?? 7)));
$dest = 'f.rodriguez@francobordo.com';

/* Sonda tolerante sobre el request_json (los esquemas difieren por agencia). */
function auditJson($raw, $rutas) {
    $j = json_decode((string) $raw, true);
    if (!is_array($j)) return '';
    $p = isset($j['payload']) && is_array($j['payload']) ? $j['payload'] : $j;
    foreach ($rutas as $ruta) {
        $v = $p;
        foreach (explode('.', $ruta) as $k) {
            if (!is_array($v) || !array_key_exists($k, $v)) { $v = null; break; }
            $v = $v[$k];
        }
        if (is_string($v) && trim($v) !== '') return trim($v);
    }
    return '';
}
function auditDestinoVisto($cp, $calle) {
    $cp = trim((string) $cp);
    if ($cp === '') return '?';
    $tok = preg_replace('/[^A-Za-z0-9]/', '', mb_substr(trim((string) $calle), 0, 8));
    $sql = "SELECT 1 FROM orders WHERE delivery_postcode = '" . tep_db_input($cp) . "'";
    if ($tok !== '') $sql .= " AND REPLACE(REPLACE(delivery_street_address,' ',''),'.','') LIKE '" . tep_db_input($tok) . "%'";
    $sql .= ' LIMIT 1';
    return tep_db_num_rows(tep_db_query($sql)) ? 'sí' : '<strong style="color:#c00">NO ⚠</strong>';
}
function auditEsc($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

$html = '<h2>Auditoría de envíos — últimos ' . $dias . ' días</h2>';
$nMan = 0; $nReg = 0; $nCan = 0;

/* ---- 1. MANUALES en las 3 agencias ---- */
$agencias = array(
    'SEUR'            => 'seur_shipments',
    'Correos Express' => 'cex_shipments',
    'Correos'         => 'correos_shipments',
);
$html .= '<h3>1. Envíos manuales (sin pedido ni RMA)</h3>';
$rows = '';
foreach ($agencias as $nombre => $tabla) {
    $q = tep_db_query("SELECT * FROM `" . $tabla . "` WHERE orders_id = 0 AND id_rma = 0 AND date_added >= (NOW() - INTERVAL " . (int) $dias . " DAY) ORDER BY id");
    while ($r = tep_db_fetch_array($q)) {
        $nMan++;
        $dnom = auditJson($r['request_json'], array('receiver.name', 'destinatario', 'nombreDestinatario', 'nombre'));
        $dciu = auditJson($r['request_json'], array('receiver.address.cityName', 'poblacion', 'localidad', 'ciudad'));
        $dcp  = auditJson($r['request_json'], array('receiver.address.postalCode', 'codigoPostalDestino', 'cpDestino', 'cp'));
        $dpais= auditJson($r['request_json'], array('receiver.address.country', 'pais', 'codPais'));
        $dcal = auditJson($r['request_json'], array('receiver.address.streetName', 'direccion', 'domicilio'));
        $rows .= '<tr><td>' . auditEsc($nombre) . '</td><td>' . auditEsc($r['date_added']) . '</td><td>' . auditEsc($r['ref'])
              . '</td><td>' . ((int) $r['ok'] === 1 ? 'OK' : '<span style="color:#c00">fallo</span>')
              . '</td><td>' . auditEsc($r['operator'] ?? '') . '</td><td>' . auditEsc($dnom) . '</td><td>'
              . auditEsc($dciu . ($dcp !== '' ? ' ' . $dcp : '') . ($dpais !== '' ? ' (' . $dpais . ')' : ''))
              . '</td><td>' . auditEsc($r['kilos']) . '</td><td>' . auditDestinoVisto($dcp, $dcal) . '</td></tr>';
    }
}
$html .= $rows === ''
    ? '<p style="color:#2a7">Sin envíos manuales en el periodo.</p>'
    : '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse;font:13px Arial"><tr style="background:#eee"><th>Agencia</th><th>Fecha</th><th>Ref</th><th>Estado</th><th>Quién</th><th>Destinatario</th><th>Destino</th><th>Kg</th><th>¿Destino visto en pedidos?</th></tr>' . $rows . '</table>';

/* ---- 2. Regeneraciones SEUR (posible desvío de dirección) ---- */
$html .= '<h3>2. Regeneraciones SEUR (anular y regenerar)</h3>';
$rows = '';
$q = tep_db_query("SELECT * FROM seur_shipments WHERE ref REGEXP 'R[0-9]+$' AND ok = 1 AND date_added >= (NOW() - INTERVAL " . (int) $dias . " DAY) ORDER BY id");
while ($r = tep_db_fetch_array($q)) {
    $nReg++;
    $cpNew  = auditJson($r['request_json'], array('receiver.address.postalCode'));
    $calNew = auditJson($r['request_json'], array('receiver.address.streetName'));
    $cambio = '';
    if ((int) $r['orders_id'] >= 10000000) {
        $qo = tep_db_query('SELECT delivery_postcode, delivery_street_address FROM orders WHERE orders_id = ' . (int) $r['orders_id']);
        if ($o = tep_db_fetch_array($qo)) {
            $mismoCp = (preg_replace('/\s+/', '', $o['delivery_postcode']) === preg_replace('/\s+/', '', $cpNew));
            $tokO = preg_replace('/[^A-Za-z0-9]/', '', mb_substr($o['delivery_street_address'], 0, 8));
            $tokN = preg_replace('/[^A-Za-z0-9]/', '', mb_substr($calNew, 0, 8));
            $mismaCalle = ($tokO !== '' && strcasecmp($tokO, $tokN) === 0);
            $cambio = ($mismoCp && $mismaCalle) ? 'no' : '<strong style="color:#c00">DIRECCIÓN CAMBIADA ⚠</strong>';
        }
    } else { $cambio = '(QFac/manual)'; }
    $rows .= '<tr><td>' . auditEsc($r['date_added']) . '</td><td>' . (int) $r['orders_id'] . '</td><td>' . auditEsc($r['ref'])
          . '</td><td>' . auditEsc($r['operator'] ?? '') . '</td><td>' . auditEsc($calNew . ', ' . $cpNew) . '</td><td>' . $cambio . '</td></tr>';
}
$html .= $rows === ''
    ? '<p style="color:#2a7">Sin regeneraciones en el periodo.</p>'
    : '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse;font:13px Arial"><tr style="background:#eee"><th>Fecha</th><th>Pedido</th><th>Ref</th><th>Quién</th><th>Destino nuevo</th><th>¿Cambió dirección?</th></tr>' . $rows . '</table>';

/* ---- 3. Anulaciones SEUR ---- */
$html .= '<h3>3. Anulaciones SEUR</h3>';
$rows = '';
$q = tep_db_query("SELECT id, orders_id, id_rma, ref, shipment_code, cancelled_at, cancelled_by FROM seur_shipments WHERE cancelled_at >= (NOW() - INTERVAL " . (int) $dias . " DAY) ORDER BY cancelled_at");
while ($r = tep_db_fetch_array($q)) {
    $nCan++;
    $rows .= '<tr><td>' . auditEsc($r['cancelled_at']) . '</td><td>' . (int) $r['orders_id'] . '</td><td>' . auditEsc($r['ref'])
          . '</td><td>' . auditEsc($r['shipment_code']) . '</td><td>' . auditEsc($r['cancelled_by'] !== null && $r['cancelled_by'] !== '' ? $r['cancelled_by'] : '(anterior al registro)') . '</td></tr>';
}
$html .= $rows === ''
    ? '<p style="color:#2a7">Sin anulaciones en el periodo.</p>'
    : '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse;font:13px Arial"><tr style="background:#eee"><th>Cuándo</th><th>Pedido</th><th>Ref</th><th>Envío</th><th>Quién</th></tr>' . $rows . '</table>';

$html .= '<p style="color:#888;font-size:12px">Generado automáticamente (cron lunes 07:30). Manuales = orders_id=0 e id_rma=0 en seur/cex/correos_shipments. "Quién" viene de operator (formulario/regen del panel; los envíos del almacén salen como vstock-watcher). Conciliar además la factura mensual de cada agencia contra estas tablas.</p>';

$subject = '[Auditoría envíos] ' . $nMan . ' manuales · ' . $nReg . ' regeneraciones · ' . $nCan . ' anulaciones (' . $dias . ' días)';
tep_mail('Francisco', $dest, $subject, $html, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
echo 'OK enviado: ' . auditEsc($subject) . "\n";
