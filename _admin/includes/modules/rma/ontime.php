<?php
/**
 * Integración Ontime en el módulo RMA del admin (recogidas de devolución).
 *
 * Flujo Ontime (distinto de CEX): documentarEnvio crea la expedición con
 * remitente = cliente (etiqueta PDF) y entregaRecogedor la comunica y crea
 * la RECOGIDA del día en la dirección del remitente. El WS no admite
 * fecha/franja de recogida: la recogida se genera al pulsar el botón.
 *
 * Acciones (añadidas al switch de functions.php):
 *   - ontime-pickup      : envío + etiqueta + recogida contra Ontime
 *   - ontime-label       : descarga el PDF de etiqueta almacenado
 *   - ontime-email-label : envía el PDF por email al cliente
 *   - ontime-set-env     : interruptor global pre/pro (tabla ontime_config,
 *                          compartido con cron_ontime_tracking.php)
 *
 * Render de la caja del operador: rmaOntimeRenderBox($rmaDetail) — desde views/view.php.
 *
 * Doc/credenciales: memoria francobordo_ontime_api.
 */

require_once DIR_FS_CATALOG . 'includes/classes/ontime.php';

// Entorno activo: tabla ontime_config (clave 'env'), compartida con el cron
// de tracking. Default 'pre' si no hay fila.
function ontimeGetEnv() {
    $q = tep_db_query("SELECT config_value FROM ontime_config WHERE config_key = 'env'");
    if ($q && tep_db_num_rows($q)) {
        $v = tep_db_fetch_array($q);
        return ($v['config_value'] === 'pro') ? 'pro' : 'pre';
    }
    return 'pre';
}
function ontimeSetEnv($env) {
    $env = ($env === 'pro') ? 'pro' : 'pre';
    tep_db_query("REPLACE INTO ontime_config (config_key, config_value) VALUES ('env', '" . tep_db_input($env) . "')");
}
if (!defined('ONTIME_ENV')) define('ONTIME_ENV', ontimeGetEnv());

/* ====================================================================== *
 *  Helpers                                                               *
 * ====================================================================== */

/** Fila cruda de rma (para construir la petición a Ontime). */
function ontimeGetRma($id_rma) {
    $q = tep_db_query('SELECT * FROM ' . TABLE_RMA . ' WHERE id_rma = ' . (int) $id_rma);
    return tep_db_fetch_array($q);
}

/** Envíos Ontime de un RMA, más reciente primero. */
function ontimeShipmentsFor($id_rma) {
    $rows = array();
    $q = tep_db_query('SELECT * FROM rma_ontime_shipments WHERE id_rma = ' . (int) $id_rma . ' ORDER BY id DESC');
    while ($r = tep_db_fetch_array($q)) $rows[] = $r;
    return $rows;
}

/** Estado de tracking (ontime_tracking) de una referencia; null si no hay. */
function ontimeTrackEstado($ref) {
    $ref = trim((string) $ref);
    if ($ref === '') return null;
    $q = tep_db_query("SELECT estado_desc, entregado, last_checked FROM ontime_tracking WHERE referencia = '" . tep_db_input($ref) . "' LIMIT 1");
    return tep_db_num_rows($q) ? tep_db_fetch_array($q) : null;
}

/** Identifica al operador logueado (best-effort, solo para traza). */
function ontimeOperator() {
    foreach (array('login_email_address', 'login_id', 'login_first_name') as $k) {
        if (!empty($_SESSION[$k])) return (string) $_SESSION[$k];
    }
    return '';
}

/** Anota una nota privada en el histórico del RMA (sin cambiar estado ni notificar). */
function ontimeNote($id_rma, $texto) {
    $rma = ontimeGetRma($id_rma);
    $status = $rma ? (int) $rma['status'] : 0;
    tep_db_perform(TABLE_RMA_STATUS_HISTORY, array(
        'email_text'      => '',
        'notify'          => 0,
        'id_rma'          => (int) $id_rma,
        'id_status'       => $status,
        'message'         => '',
        'private_message' => $texto,
        'date_added'      => 'now()',
    ));
}

/** Guarda un PDF (binario) bajo images/rma/{id}/ con nombre aleatorio. Ruta relativa o null. */
function ontimeSavePdf($id_rma, $bin, $prefix = 'ontime_label_') {
    if (!is_string($bin) || strncmp($bin, '%PDF', 4) !== 0) return null;
    $dir = DIR_FS_CATALOG . 'images/rma/' . (int) $id_rma . '/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $name = $prefix . (int) $id_rma . '_' . substr(md5($bin . microtime(true)), 0, 10) . '.pdf';
    if (@file_put_contents($dir . $name, $bin) === false) return null;
    return (int) $id_rma . '/' . $name; // relativa bajo images/rma/
}

/* ====================================================================== *
 *  Acción: envío + etiqueta + recogida                                  *
 * ====================================================================== */

function rmaOntimePickup() {
    $id  = (int) ($_POST['id'] ?? 0);
    $rma = ontimeGetRma($id);
    if (!$rma) { tep_redirect(tep_href_link('rma.php', 'action=view&id=' . $id)); }

    $kilos = str_replace(',', '.', trim((string) ($_POST['kilos'] ?? '1')));
    if ($kilos === '' || (float) $kilos <= 0) $kilos = '1';
    $bultos = max(1, (int) ($_POST['bultos'] ?? 1));
    $productosValidos = array(ontime::PROD_PAQ_IND, ontime::PROD_PALET_EXP, ontime::PROD_ECONOMY);
    $producto = in_array(($_POST['producto'] ?? ''), $productosValidos, true) ? $_POST['producto'] : ontime::PROD_DEVOLUCION;

    $ont  = new ontime(ONTIME_ENV);
    $opts = array(
        'KILOS'                    => $kilos,
        'NUMERO_BULTOS'            => $bultos,
        'CODIGO_PRODUCTO_SERVICIO' => $producto,
    );
    $res   = $ont->recogidaDevolucionRma($rma, $opts);
    $envio = is_array($res['envio']['data'] ?? null) ? $res['envio']['data'] : array();
    $reco  = is_array($res['recogida']['data'] ?? null) ? $res['recogida']['data'] : array();
    $ok    = !empty($res['ok']);
    $ref   = 'RMA' . str_pad((string) $id, 8, '0', STR_PAD_LEFT);

    $labelPath = null;
    if (!empty($envio['etiqueta_bin'])) {
        $labelPath = ontimeSavePdf($id, $envio['etiqueta_bin']);
    }

    // Localizador (para la URL pública): lo da el detalle una vez comunicada.
    $localizador = '';
    if (!empty($envio['numero_envio']) && $ok) {
        $det = $ont->detalleExpedicion($envio['numero_envio']);
        if (is_array($det) && !empty($det['localizador'])) $localizador = (string) $det['localizador'];
    }

    $codigoError = '';
    $mensaje     = '';
    if (!$ok) {
        $fase        = ($res['paso'] === 'documentarEnvio') ? $res['envio'] : $res['recogida'];
        $fdata       = is_array($fase['data'] ?? null) ? $fase['data'] : array();
        $codigoError = (string) ($fdata['codigo_error'] ?? '');
        $mensaje     = (string) (($fdata['mensaje'] ?? '') ?: ($fase['error'] ?? 'error desconocido'));
    } else {
        $mensaje = (string) ($reco['mensaje'] ?? '');
    }

    tep_db_perform('rma_ontime_shipments', array(
        'id_rma'        => $id,
        'entorno'       => ONTIME_ENV,
        'expe_numero'   => $envio['numero_envio'] ?? null,
        'recogida'      => $reco['recogida'] ?? null,
        'localizador'   => $localizador,
        'producto'      => $producto,
        'ref'           => $ref,
        'kilos'         => (float) $kilos,
        'bultos'        => $bultos,
        'label_path'    => $labelPath,
        'codigo_error'  => $codigoError,
        'mensaje'       => $mensaje,
        'ok'            => $ok ? 1 : 0,
        'request_json'  => json_encode($ont->lastRequest, JSON_UNESCAPED_UNICODE),
        'response_json' => json_encode(array('envio' => $envio, 'recogida' => $reco), JSON_UNESCAPED_UNICODE),
        'operator'      => ontimeOperator(),
        'date_added'    => 'now()',
    ));

    ontimeNote($id, $ok
        ? 'Ontime: expedición ' . ($envio['numero_envio'] ?? '?') . ' + recogida ' . ($reco['recogida'] ?? '?') . ' generadas (etiqueta disponible).'
        : 'Ontime ERROR en ' . $res['paso'] . ($codigoError !== '' ? ' (cód. ' . $codigoError . ')' : '') . ': ' . $mensaje);

    tep_redirect(tep_href_link('rma.php', 'action=view&id=' . $id));
}

/* ====================================================================== *
 *  Acción: descargar etiqueta PDF                                       *
 * ====================================================================== */

function rmaOntimeDownloadLabel() {
    $shipId = (int) ($_GET['ship'] ?? 0);
    $q = tep_db_query('SELECT * FROM rma_ontime_shipments WHERE id = ' . $shipId);
    $s = tep_db_fetch_array($q);
    if (!$s || empty($s['label_path'])) { tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) ($s['id_rma'] ?? 0))); }
    $path = DIR_FS_CATALOG . 'images/rma/' . $s['label_path'];
    if (!is_file($path)) { tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) $s['id_rma'])); }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="etiqueta_ontime_rma' . (int) $s['id_rma'] . '.pdf"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
}

/* ====================================================================== *
 *  Acción: enviar etiqueta por email al cliente                         *
 * ====================================================================== */

function rmaOntimeEmailLabel() {
    $shipId = (int) ($_POST['ship'] ?? 0);
    $q = tep_db_query('SELECT * FROM rma_ontime_shipments WHERE id = ' . $shipId);
    $s = tep_db_fetch_array($q);
    if (!$s || empty($s['label_path'])) { tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) ($s['id_rma'] ?? 0))); }

    $rma  = ontimeGetRma($s['id_rma']);
    $path = DIR_FS_CATALOG . 'images/rma/' . $s['label_path'];
    $name = trim($rma['customers_name']) ?: 'Cliente';
    $mailTo = trim($rma['customers_email_address']);

    if ($mailTo !== '' && is_file($path)) {
        $idFmt = str_pad((string) $s['id_rma'], 10, '0', STR_PAD_LEFT);
        $ont   = new ontime($s['entorno'] ?: ONTIME_ENV);
        $html  = '<p>Hola ' . htmlspecialchars($name) . ',</p>'
               . '<p>Adjuntamos la etiqueta de Ontime para la devolución de su RMA <strong>' . $idFmt . '</strong>.</p>'
               . '<p>Por favor, imprímala y péguela en el paquete. El transportista de Ontime pasará a recogerlo'
               . ($s['recogida'] ? ' (nº de recogida ' . htmlspecialchars($s['recogida']) . ')' : '') . '.</p>'
               . ($s['ref'] ? '<p>Puede seguir el estado de su devolución en <a href="' . htmlspecialchars($ont->publicTrackingUrl($s['ref'])) . '">este enlace</a>.</p>' : '')
               . '<p>Gracias,<br>' . STORE_NAME . '</p>';

        // tep_mail() = PHPMailer (ruta de entrega real); NO usar la clase email
        // antigua (su caché de bounces de SendGrid descarta correos). Igual que CEX.
        $attach = array(
            'tmp_name' => $path,
            'name'     => 'etiqueta_ontime_rma' . (int) $s['id_rma'] . '.pdf',
        );
        tep_mail($name, $mailTo, 'Etiqueta de devolución Ontime - RMA ' . $idFmt,
                 $html, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, $attach);

        tep_db_perform('rma_ontime_shipments', array('emailed_at' => 'now()'), 'update', 'id = ' . $shipId);
        ontimeNote($s['id_rma'], 'Ontime: etiqueta de la expedición ' . ($s['expe_numero'] ?: '') . ' enviada por email a ' . $mailTo . '.');
    }
    tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) $s['id_rma']));
}

/* ====================================================================== *
 *  Acción: cambiar entorno PRE/PRO (interruptor global)                 *
 * ====================================================================== */

function rmaOntimeSetEnv() {
    $env = (($_POST['env'] ?? '') === 'pro') ? 'pro' : 'pre';
    ontimeSetEnv($env);
    tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) ($_POST['id'] ?? 0)));
}

/* ====================================================================== *
 *  Render: caja del operador en views/view.php                          *
 * ====================================================================== */

function rmaOntimeRenderBox($rmaDetail) {
    $id   = (int) $rmaDetail['id_rma'];
    $rows = ontimeShipmentsFor($id);
    $env  = ONTIME_ENV;
    $proSinCreds = ($env === 'pro' && !(new ontime('pro'))->hasCredentials());
    ?>
    <div class="rows sp10 column a12" style="margin-top:8px;padding:10px;background:#fff4ec;border:1px solid #f3c9a8;border-radius:4px">
        <div class="column a12" style="font-weight:bold;margin-bottom:6px;display:flex;align-items:center;justify-content:space-between">
            <span>
                <i class="fa fa-truck"></i> Ontime
                <?php if ($env === 'pre'): ?>
                    <span style="color:#c0392b;font-size:11px">&nbsp;(ENTORNO PRUEBAS)</span>
                <?php else: ?>
                    <span style="color:#2e7d32;font-size:11px">&nbsp;(PRODUCCIÓN)</span>
                <?php endif; ?>
            </span>
            <form method="post" action="<?php echo tep_href_link('rma.php', 'action=ontime-set-env'); ?>" style="margin:0"
                  onsubmit="return confirm(<?php echo $env === 'pre' ? "'Vas a ACTIVAR PRODUCCIÓN: los botones generarán expediciones y recogidas REALES con Ontime. ¿Continuar?'" : "'Volver a ENTORNO DE PRUEBAS (no se generan envíos reales). ¿Continuar?'"; ?>);">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <input type="hidden" name="env" value="<?php echo $env === 'pre' ? 'pro' : 'pre'; ?>">
                <button class="xbutton" style="font-size:10px;padding:1px 6px;font-weight:normal" type="submit">⇄ <?php echo $env === 'pre' ? 'Activar PRODUCCIÓN' : 'Volver a pruebas'; ?></button>
            </form>
        </div>

        <?php if ($proSinCreds): ?>
            <div class="column a12" style="margin-bottom:8px;padding:6px 8px;background:#fdecea;border:1px solid #f5b7b1;border-radius:4px;font-size:12px">
                ⚠ <strong>Sin credenciales de PRODUCCIÓN.</strong> Ontime aún no ha facilitado usuario/contraseña de PRO (consts PRO_* en includes/classes/ontime.php).
            </div>
        <?php endif; ?>

        <?php if ($rows): ?>
            <div class="column a12" style="margin-bottom:8px">
                <?php foreach ($rows as $s): ?>
                    <div style="font-size:12px;padding:5px 0;border-bottom:1px solid #f3d9c2">
                        <strong>Expedición</strong>
                        <?php echo $s['ok'] ? '<span style="color:#2e7d32">✔</span>' : '<span style="color:#c0392b">✖ ' . htmlspecialchars($s['codigo_error']) . '</span>'; ?>
                        <?php if ($s['expe_numero']): ?> · exp. <code><?php echo htmlspecialchars($s['expe_numero']); ?></code><?php endif; ?>
                        <?php if ($s['recogida']): ?> · recog. <code><?php echo htmlspecialchars($s['recogida']); ?></code><?php endif; ?>
                        <?php if ($s['entorno'] === 'pre'): ?> · <em style="color:#c0392b">pruebas</em><?php endif; ?>
                        <?php $trk = ontimeTrackEstado($s['ref']); if ($trk): ?> · <strong style="color:<?php echo $trk['entregado'] ? '#2e7d32' : '#0a6ebd'; ?>">📍 <?php echo htmlspecialchars($trk['estado_desc']); ?></strong><span style="color:#999;font-size:11px"> (<?php echo date('d/m H:i', strtotime($trk['last_checked'])); ?>)</span><?php endif; ?>
                        <br><span style="color:#777"><?php echo htmlspecialchars($s['date_added']); ?><?php if (!$s['ok'] && $s['mensaje']): ?> — <?php echo htmlspecialchars($s['mensaje']); ?><?php endif; ?></span>
                        <?php if ($s['ok']): ?>
                            <div style="margin-top:3px">
                                <?php if ($s['label_path']): ?>
                                    <a class="xbutton" style="font-size:11px;padding:2px 8px" href="<?php echo tep_href_link('rma.php', 'action=ontime-label&ship=' . (int) $s['id']); ?>" target="_blank">⬇ Etiqueta</a>
                                    <form method="post" action="<?php echo tep_href_link('rma.php', 'action=ontime-email-label'); ?>" style="display:inline">
                                        <input type="hidden" name="ship" value="<?php echo (int) $s['id']; ?>">
                                        <button class="xbutton verde" style="font-size:11px;padding:2px 8px" type="submit">✉ <?php echo $s['emailed_at'] ? 'Reenviar al cliente' : 'Enviar al cliente'; ?></button>
                                    </form>
                                    <?php if ($s['emailed_at']): ?><span style="color:#777;font-size:11px">enviada <?php echo htmlspecialchars($s['emailed_at']); ?></span><?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo tep_href_link('rma.php', 'action=ontime-pickup'); ?>" class="column a12" style="font-size:12px"
              onsubmit="return confirm('Se generará la expedición Y la recogida de HOY en la dirección del cliente. ¿Continuar?');">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <div style="margin-bottom:5px">
                Recoger en: <strong><?php echo htmlspecialchars(trim($rmaDetail['customers_name'])); ?></strong>,
                <?php echo htmlspecialchars(trim($rmaDetail['customers_street_address'] . ' ' . $rmaDetail['customers_suburb'])); ?>,
                <?php echo htmlspecialchars(trim($rmaDetail['customers_postcode'] . ' ' . $rmaDetail['customers_city'])); ?>
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-bottom:6px">
                <label>Peso (kg): <input type="number" name="kilos" step="0.1" min="0.1" value="1" style="width:60px;padding:3px;border:1px solid #aaa;border-radius:3px"></label>
                <label>Bultos: <input type="number" name="bultos" step="1" min="1" value="1" style="width:50px;padding:3px;border:1px solid #aaa;border-radius:3px"></label>
                <label>Servicio:
                    <select name="producto" style="padding:3px;border:1px solid #aaa;border-radius:3px">
                        <option value="<?php echo ontime::PROD_PAQ_IND; ?>">Paquetería industrial (70)</option>
                        <option value="<?php echo ontime::PROD_PALET_EXP; ?>">Palet Express (26)</option>
                        <option value="<?php echo ontime::PROD_ECONOMY; ?>">Economy 24-48h (48)</option>
                    </select>
                </label>
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                <button class="xbutton verde" type="submit" <?php echo $proSinCreds ? 'disabled' : ''; ?>>📦 Recogida Ontime (envío + etiqueta + recogida)</button>
                <span style="color:#777;font-size:11px">La recogida se crea para HOY (el WS de Ontime no admite fecha/franja).</span>
            </div>
        </form>
    </div>
    <?php
}
