<?php
/**
 * Integración Correos Express en el módulo RMA del admin.
 *
 * Acciones (añadidas al switch de rmaSection en functions.php):
 *   - request-pickup   : genera envío+etiqueta o recogida sola contra CEX
 *   - cex-label        : descarga el PDF de etiqueta almacenado
 *   - cex-email-label  : envía el PDF de etiqueta por email al cliente
 *   - cex-cancel       : anula una recogida en CEX
 *
 * Render de la caja del operador: rmaCexRenderBox($rmaDetail) — invocado desde views/view.php.
 *
 * Doc/credenciales: memoria francobordo_correos_express_api.
 */

require_once DIR_FS_CATALOG . 'includes/classes/correos_express.php';

// Entorno activo: se guarda en la tabla cex_config (interruptor visible en el admin).
// Default 'test' si no hay fila. CEX_ENV se fija al incluir el módulo.
function cexGetEnv() {
    $q = tep_db_query("SELECT config_value FROM cex_config WHERE config_key = 'env'");
    if ($q && tep_db_num_rows($q)) {
        $v = tep_db_fetch_array($q);
        return ($v['config_value'] === 'pro') ? 'pro' : 'test';
    }
    return 'test';
}
function cexSetEnv($env) {
    $env = ($env === 'pro') ? 'pro' : 'test';
    tep_db_query("REPLACE INTO cex_config (config_key, config_value) VALUES ('env', '" . tep_db_input($env) . "')");
}
if (!defined('CEX_ENV')) define('CEX_ENV', cexGetEnv());

/* ====================================================================== *
 *  Helpers                                                               *
 * ====================================================================== */

/** Fila cruda de rma (para construir la petición a CEX). */
function cexGetRma($id_rma) {
    $q = tep_db_query('SELECT * FROM ' . TABLE_RMA . ' WHERE id_rma = ' . (int) $id_rma);
    return tep_db_fetch_array($q);
}

/** Envíos/recogidas CEX de un RMA, más reciente primero. */
function cexShipmentsFor($id_rma) {
    $rows = array();
    $q = tep_db_query('SELECT * FROM rma_cex_shipments WHERE id_rma = ' . (int) $id_rma . ' ORDER BY id DESC');
    while ($r = tep_db_fetch_array($q)) $rows[] = $r;
    return $rows;
}

/** Estado de tracking (cex_tracking) de una referencia (F{oid} o RMA{id}); null si no hay. */
function cexTrackEstado($ref) {
    $ref = trim((string) $ref);
    if ($ref === '') return null;
    $q = tep_db_query("SELECT estado_desc, entregado, last_checked FROM cex_tracking WHERE referencia = '" . tep_db_input($ref) . "' LIMIT 1");
    return tep_db_num_rows($q) ? tep_db_fetch_array($q) : null;
}

/** Identifica al operador logueado (best-effort, solo para traza). */
function cexOperator() {
    foreach (array('login_email_address', 'login_id', 'login_first_name') as $k) {
        if (!empty($_SESSION[$k])) return (string) $_SESSION[$k];
    }
    return '';
}

/** Anota una nota privada en el histórico del RMA (sin cambiar de estado ni notificar). */
function cexNote($id_rma, $texto) {
    $rma = cexGetRma($id_rma);
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

/** Guarda el PDF de etiqueta bajo images/rma/{id}/ con nombre aleatorio. Devuelve ruta relativa o null. */
function cexSaveLabel($id_rma, $b64) {
    $pdf = base64_decode($b64, true);
    if ($pdf === false || strncmp($pdf, '%PDF', 4) !== 0) return null;
    $dir = DIR_FS_CATALOG . 'images/rma/' . (int) $id_rma . '/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $name = 'cex_label_' . (int) $id_rma . '_' . substr(md5($b64 . microtime(true)), 0, 10) . '.pdf';
    if (@file_put_contents($dir . $name, $pdf) === false) return null;
    return (int) $id_rma . '/' . $name; // relativa bajo images/rma/
}

/** Convierte YYYY-MM-DD (input date) a DDMMYYYY (formato CEX). */
function cexDate($ymd) {
    $ymd = trim((string) $ymd);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) return '';
    return $m[3] . $m[2] . $m[1];
}

/** Enlace a Google Maps desde geoposicionOficina "lat,lng". */
function cexMapsLink($geo) {
    $geo = trim((string) $geo);
    return preg_match('/^-?\d+(\.\d+)?,-?\d+(\.\d+)?$/', $geo)
        ? 'https://www.google.com/maps?q=' . rawurlencode($geo) : '';
}

/** Oficinas CEX que admiten depósito de devoluciones cerca de un CP (array, máx $limit). */
function cexOficinasDeposito($cp, $limit = 6) {
    if (trim((string) $cp) === '') return array();
    $cex = new correos_express(CEX_ENV);
    return array_slice(correos_express::oficinasDeposito($cex->consultarOficinas($cp)), 0, $limit);
}

/** Bloque HTML con las oficinas de depósito (para el email al cliente). */
function cexOficinasHtml(array $oficinas) {
    if (!$oficinas) return '';
    $h = '<p><strong>Puede depositar el paquete en una de estas oficinas de Correos Express:</strong></p><ul>';
    foreach ($oficinas as $o) {
        $h .= '<li><strong>' . htmlspecialchars($o['nombreOficina'] ?? '') . '</strong><br>'
            . htmlspecialchars(trim(($o['direccionOficina'] ?? '') . ', ' . ($o['codigoPostalOficina'] ?? '') . ' ' . ($o['poblacionOficina'] ?? '')))
            . '<br><em>' . htmlspecialchars($o['horarioOficina'] ?? '') . '</em></li>';
    }
    return $h . '</ul>';
}

/* ====================================================================== *
 *  Acción: generar envío de retorno o recogida                          *
 * ====================================================================== */

function rmaRequestPickup() {
    $id   = (int) ($_POST['id'] ?? 0);
    // envio   = envío con etiqueta + recogida a domicilio (si hay fecha)
    // oficina = envío con etiqueta SIN recogida (el cliente lo deposita en oficina CEX)
    // recogida= solo recogida a domicilio (sin etiqueta)
    $mode = in_array(($_POST['mode'] ?? ''), array('envio', 'oficina', 'recogida'), true) ? $_POST['mode'] : 'recogida';
    $rma  = cexGetRma($id);
    if (!$rma) { tep_redirect(tep_href_link('rma.php', 'action=view&id=' . $id)); }

    $kilos   = str_replace(',', '.', trim((string) ($_POST['kilos'] ?? '1')));
    if ($kilos === '' || (float) $kilos <= 0) $kilos = '1';
    $latente = !empty($_POST['latente']);
    $fechaCex = $latente ? '' : cexDate($_POST['fecha'] ?? '');
    $hDesde  = trim((string) ($_POST['hora_desde'] ?? '10:00'));
    $hHasta  = trim((string) ($_POST['hora_hasta'] ?? '18:00'));

    $cex  = new correos_express(CEX_ENV);
    $opts = array('kilos' => $kilos, 'producto' => correos_express::PROD_PAQ24);

    if ($mode === 'envio' || $mode === 'oficina') {
        $deposito = ($mode === 'oficina');
        $payload = correos_express::envioRetornoDesdeRma($rma, $opts);
        $payload['etiqueta'] = '1'; // PDF base64 10x15
        // La recogida a domicilio solo se crea en modo 'envio' con fecha.
        // En modo 'oficina' nunca hay recogida (el cliente deposita el paquete).
        if (!$deposito && $fechaCex !== '') {
            $payload['recogida'] = array('fecha' => $fechaCex, 'horaDesde' => $hDesde, 'horaHasta' => $hHasta);
        }
        $res = $cex->grabacionEnvio($payload);
        $d   = is_array($res['data']) ? $res['data'] : array();
        $ok  = (isset($d['codigoRetorno']) && (int) $d['codigoRetorno'] === 0);
        $labelPath = $ok && !empty($d['etiqueta'][0]['etiqueta1']) ? cexSaveLabel($id, $d['etiqueta'][0]['etiqueta1']) : null;

        tep_db_perform('rma_cex_shipments', array(
            'id_rma'          => $id,
            'tipo'            => 'envio',
            'es_deposito'     => $deposito ? 1 : 0,
            'entorno'         => CEX_ENV,
            'num_envio'       => $d['datosResultado'] ?? null,
            'num_recogida'    => $d['numRecogida'] ?? null,
            'cod_unico'       => $d['listaBultos'][0]['codUnico'] ?? null,
            'producto'        => $payload['producto'],
            'ref'             => $payload['ref'],
            'kilos'           => (float) $kilos,
            'label_format'    => 'pdf',
            'label_path'      => $labelPath,
            'codigo_retorno'  => isset($d['codigoRetorno']) ? (string) $d['codigoRetorno'] : null,
            'mensaje_retorno' => ($d['mensajeRetorno'] ?? '') ?: $res['error'],
            'ok'              => $ok ? 1 : 0,
            'request_json'    => json_encode($cex->lastRequest, JSON_UNESCAPED_UNICODE),
            'response_json'   => $res['raw'],
            'operator'        => cexOperator(),
            'date_added'      => 'now()',
        ));
        if ($deposito) {
            cexNote($id, $ok
                ? 'Correos Express: etiqueta de devolución ' . ($d['datosResultado'] ?? '?') . ' generada para DEPÓSITO en oficina.'
                : 'Correos Express ERROR ' . ($d['codigoRetorno'] ?? '?') . ': ' . ($d['mensajeRetorno'] ?? $res['error']));
        } else {
            cexNote($id, $ok
                ? 'Correos Express: envío ' . ($d['datosResultado'] ?? '?') . ($d['numRecogida'] ? ' + recogida ' . $d['numRecogida'] : '') . ' generados (etiqueta disponible).'
                : 'Correos Express ERROR ' . ($d['codigoRetorno'] ?? '?') . ': ' . ($d['mensajeRetorno'] ?? $res['error']));
        }
    } else {
        $ropts = $opts;
        if ($fechaCex !== '') {
            $ropts['fechaRecogida'] = $fechaCex;
            $ropts['horaDesde1']    = $hDesde;
            $ropts['horaHasta1']    = $hHasta;
            $ropts['latente']       = '0';
        } else {
            $ropts['latente'] = '1';
        }
        $payload = correos_express::recogidaDesdeRma($rma, $ropts);
        $res = $cex->grabarRecogida($payload);
        $d   = is_array($res['data']) ? $res['data'] : array();
        $ok  = (isset($d['resultado']) && (string) $d['resultado'] === '1');

        tep_db_perform('rma_cex_shipments', array(
            'id_rma'          => $id,
            'tipo'            => 'recogida',
            'entorno'         => CEX_ENV,
            'num_recogida'    => $d['numRecogida'] ?? null,
            'producto'        => $payload['producto'],
            'ref'             => $payload['refRecogida'],
            'kilos'           => (float) $kilos,
            'codigo_retorno'  => isset($d['codigoRetorno']) ? (string) $d['codigoRetorno'] : null,
            'mensaje_retorno' => ($d['mensajeRetorno'] ?? '') ?: $res['error'],
            'ok'              => $ok ? 1 : 0,
            'request_json'    => json_encode($cex->lastRequest, JSON_UNESCAPED_UNICODE),
            'response_json'   => $res['raw'],
            'operator'        => cexOperator(),
            'date_added'      => 'now()',
        ));
        cexNote($id, $ok
            ? 'Correos Express: recogida ' . ($d['numRecogida'] ?? '?') . ' solicitada.'
            : 'Correos Express ERROR recogida ' . ($d['codigoRetorno'] ?? '?') . ': ' . ($d['mensajeRetorno'] ?? $res['error']));
    }

    tep_redirect(tep_href_link('rma.php', 'action=view&id=' . $id));
}

/* ====================================================================== *
 *  Acción: descargar etiqueta PDF                                       *
 * ====================================================================== */

function rmaCexDownloadLabel() {
    $shipId = (int) ($_GET['ship'] ?? 0);
    $q = tep_db_query('SELECT * FROM rma_cex_shipments WHERE id = ' . $shipId);
    $s = tep_db_fetch_array($q);
    if (!$s || empty($s['label_path'])) { tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) ($s['id_rma'] ?? 0))); }
    $path = DIR_FS_CATALOG . 'images/rma/' . $s['label_path'];
    if (!is_file($path)) { tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) $s['id_rma'])); }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="etiqueta_cex_rma' . (int) $s['id_rma'] . '.pdf"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
}

/* ====================================================================== *
 *  Acción: enviar etiqueta por email al cliente                         *
 * ====================================================================== */

function rmaCexEmailLabel() {
    $shipId = (int) ($_POST['ship'] ?? 0);
    $q = tep_db_query('SELECT * FROM rma_cex_shipments WHERE id = ' . $shipId);
    $s = tep_db_fetch_array($q);
    if (!$s || empty($s['label_path'])) { tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) ($s['id_rma'] ?? 0))); }

    $rma  = cexGetRma($s['id_rma']);
    $path = DIR_FS_CATALOG . 'images/rma/' . $s['label_path'];
    $name = trim($rma['customers_name']) ?: 'Cliente';
    $mailTo = trim($rma['customers_email_address']);

    if ($mailTo !== '' && is_file($path)) {
        $idFmt = str_pad((string) $s['id_rma'], 10, '0', STR_PAD_LEFT);
        if (!empty($s['es_deposito'])) {
            // Devolución para DEPÓSITO en oficina: incluye las oficinas cercanas.
            $html = '<p>Hola ' . htmlspecialchars($name) . ',</p>'
                  . '<p>Adjuntamos la etiqueta de Correos Express para la devolución de su RMA <strong>' . $idFmt . '</strong>.</p>'
                  . '<p>Por favor, imprímala, péguela en el paquete y <strong>deposítelo en una oficina de Correos Express</strong>.</p>'
                  . cexOficinasHtml(cexOficinasDeposito($rma['customers_postcode']))
                  . '<p>Gracias,<br>' . STORE_NAME . '</p>';
        } else {
            $html = '<p>Hola ' . htmlspecialchars($name) . ',</p>'
                  . '<p>Adjuntamos la etiqueta de Correos Express para la devolución de su RMA <strong>' . $idFmt . '</strong>.</p>'
                  . '<p>Por favor, imprímala y péguela en el paquete. El mensajero pasará a recogerlo'
                  . ($s['num_recogida'] ? ' (nº de recogida ' . htmlspecialchars($s['num_recogida']) . ')' : '') . '.</p>'
                  . '<p>Gracias,<br>' . STORE_NAME . '</p>';
        }

        // Usar tep_mail() (PHPMailer = ruta de entrega real de la tienda, con
        // bypass de SendGrid para internos y soporte de adjunto). NO usar la
        // clase email antigua (mail()→smarthost), cuyo caché de bounces descarta
        // los correos a direcciones @francobordo.com.
        $attach = array(
            'tmp_name' => $path,
            'name'     => 'etiqueta_cex_rma' . (int) $s['id_rma'] . '.pdf',
        );
        tep_mail($name, $mailTo, 'Etiqueta de devolución Correos Express - RMA ' . $idFmt,
                 $html, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, $attach);

        tep_db_perform('rma_cex_shipments', array('emailed_at' => 'now()'), 'update', 'id = ' . $shipId);
        cexNote($s['id_rma'], 'Correos Express: etiqueta del envío ' . ($s['num_envio'] ?: '') . ' enviada por email a ' . $mailTo . '.');
    }
    tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) $s['id_rma']));
}

/* ====================================================================== *
 *  Acción: anular recogida                                              *
 * ====================================================================== */

function rmaCexCancel() {
    $shipId = (int) ($_POST['ship'] ?? 0);
    $q = tep_db_query('SELECT * FROM rma_cex_shipments WHERE id = ' . $shipId);
    $s = tep_db_fetch_array($q);
    if (!$s || empty($s['num_recogida']) || !empty($s['cancelled_at'])) { tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) ($s['id_rma'] ?? 0))); }

    $cex = new correos_express($s['entorno'] ?: CEX_ENV);
    $res = $cex->anularRecogida($s['num_recogida'], 'Anulada desde admin RMA ' . $s['id_rma']);
    $d   = is_array($res['data']) ? $res['data'] : array();
    // anularRecogida OK -> codError 0 (o mensaje de ya anulada). Marcamos siempre como anulada localmente.
    tep_db_perform('rma_cex_shipments', array('cancelled_at' => 'now()'), 'update', 'id = ' . $shipId);
    cexNote($s['id_rma'], 'Correos Express: recogida ' . $s['num_recogida'] . ' anulada (codError ' . ($d['codError'] ?? '?') . ').');
    tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) $s['id_rma']));
}

/* ====================================================================== *
 *  Acción: cambiar entorno TEST/PRO (interruptor global)                *
 * ====================================================================== */

function rmaCexSetEnv() {
    $env = (($_POST['env'] ?? '') === 'pro') ? 'pro' : 'test';
    cexSetEnv($env);
    tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) ($_POST['id'] ?? 0)));
}

/* ====================================================================== *
 *  Render: caja del operador en views/view.php                          *
 * ====================================================================== */

function rmaCexRenderBox($rmaDetail) {
    $id   = (int) $rmaDetail['id_rma'];
    $rows = cexShipmentsFor($id);
    $env  = CEX_ENV;
    $tomorrow = date('Y-m-d', strtotime('+1 weekday'));

    // Petición del cliente (self-service web): cex_metodo + date_return/schedule_return.
    $reqMetodo = $rmaDetail['cex_metodo'] ?? '';
    $reqFecha  = (!empty($rmaDetail['date_return']) && $rmaDetail['date_return'] !== '0000-00-00')
                 ? date('Y-m-d', strtotime($rmaDetail['date_return'])) : $tomorrow;
    $reqFranja = (int) ($rmaDetail['schedule_return'] ?? 0);
    $reqDesde  = ($reqFranja === 2) ? '16:00' : '10:00';
    $reqHasta  = ($reqFranja === 2) ? '20:00' : '14:00';
    // Defaults del formulario: si el cliente pidió domicilio, precargamos su fecha/franja.
    $formFecha = ($reqMetodo === 'domicilio') ? $reqFecha : $tomorrow;
    $formDesde = ($reqMetodo === 'domicilio') ? $reqDesde : '10:00';
    $formHasta = ($reqMetodo === 'domicilio') ? $reqHasta : '18:00';
    ?>
    <div class="rows sp10 column a12" style="margin-top:8px;padding:10px;background:#eef6ff;border:1px solid #b9d7f5;border-radius:4px">
        <div class="column a12" style="font-weight:bold;margin-bottom:6px;display:flex;align-items:center;justify-content:space-between">
            <span>
                <i class="fa fa-truck"></i> Correos Express
                <?php if ($env === 'test'): ?>
                    <span style="color:#c0392b;font-size:11px">&nbsp;(ENTORNO PRUEBAS)</span>
                <?php else: ?>
                    <span style="color:#2e7d32;font-size:11px">&nbsp;(PRODUCCIÓN)</span>
                <?php endif; ?>
            </span>
            <form method="post" action="<?php echo tep_href_link('rma.php', 'action=cex-set-env'); ?>" style="margin:0"
                  onsubmit="return confirm(<?php echo $env === 'test' ? "'Vas a ACTIVAR PRODUCCIÓN: los botones generarán envíos y recogidas REALES con mensajero. ¿Continuar?'" : "'Volver a ENTORNO DE PRUEBAS (no se generan envíos reales). ¿Continuar?'"; ?>);">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <input type="hidden" name="env" value="<?php echo $env === 'test' ? 'pro' : 'test'; ?>">
                <button class="xbutton" style="font-size:10px;padding:1px 6px;font-weight:normal" type="submit">⇄ <?php echo $env === 'test' ? 'Activar PRODUCCIÓN' : 'Volver a pruebas'; ?></button>
            </form>
        </div>

        <?php if ($reqMetodo): ?>
            <div class="column a12" style="margin-bottom:8px;padding:6px 8px;background:#fff3cd;border:1px solid #ffe08a;border-radius:4px;font-size:12px">
                📨 <strong>El cliente solicitó:</strong>
                <?php if ($reqMetodo === 'domicilio'): ?>
                    Recogida a <strong>domicilio</strong><?php if (!empty($rmaDetail['date_return']) && $rmaDetail['date_return'] !== '0000-00-00'): ?> el <strong><?php echo date('d/m/Y', strtotime($rmaDetail['date_return'])); ?></strong><?php endif; ?> (franja <?php echo $reqFranja === 2 ? 'tarde' : 'mañana'; ?>) — usa 📦 o 🚚 abajo (fecha y franja ya precargadas).
                <?php else: ?>
                    <strong>Llevar a una oficina de Correos</strong> — genera 🏤 "Etiqueta para oficina (depósito)".
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($rows): ?>
            <div class="column a12" style="margin-bottom:8px">
                <?php foreach ($rows as $s): ?>
                    <div style="font-size:12px;padding:5px 0;border-bottom:1px solid #d6e6f7;<?php echo $s['cancelled_at'] ? 'opacity:.5' : ''; ?>">
                        <strong><?php echo $s['tipo'] === 'envio' ? 'Envío' : 'Recogida'; ?></strong>
                        <?php echo $s['ok'] ? '<span style="color:#2e7d32">✔</span>' : '<span style="color:#c0392b">✖ ' . htmlspecialchars($s['codigo_retorno']) . '</span>'; ?>
                        <?php if ($s['num_envio']): ?> · env. <code><?php echo htmlspecialchars($s['num_envio']); ?></code><?php endif; ?>
                        <?php if ($s['num_recogida']): ?> · recog. <code><?php echo htmlspecialchars($s['num_recogida']); ?></code><?php endif; ?>
                        <?php if ($s['cancelled_at']): ?> · <em>anulada</em><?php endif; ?>
                        <?php $trk = cexTrackEstado($s['ref']); if ($trk): ?> · <strong style="color:<?php echo $trk['entregado'] ? '#2e7d32' : '#0a6ebd'; ?>">📍 <?php echo htmlspecialchars($trk['estado_desc']); ?></strong><span style="color:#999;font-size:11px"> (<?php echo date('d/m H:i', strtotime($trk['last_checked'])); ?>)</span><?php endif; ?>
                        <br><span style="color:#777"><?php echo htmlspecialchars($s['date_added']); ?><?php if (!$s['ok'] && $s['mensaje_retorno']): ?> — <?php echo htmlspecialchars($s['mensaje_retorno']); ?><?php endif; ?></span>
                        <?php if ($s['ok']): ?>
                            <div style="margin-top:3px">
                                <?php if ($s['label_path']): ?>
                                    <a class="xbutton" style="font-size:11px;padding:2px 8px" href="<?php echo tep_href_link('rma.php', 'action=cex-label&ship=' . (int) $s['id']); ?>" target="_blank">⬇ Etiqueta</a>
                                    <form method="post" action="<?php echo tep_href_link('rma.php', 'action=cex-email-label'); ?>" style="display:inline">
                                        <input type="hidden" name="ship" value="<?php echo (int) $s['id']; ?>">
                                        <button class="xbutton verde" style="font-size:11px;padding:2px 8px" type="submit">✉ <?php echo $s['emailed_at'] ? 'Reenviar al cliente' : 'Enviar al cliente'; ?></button>
                                    </form>
                                    <?php if ($s['emailed_at']): ?><span style="color:#777;font-size:11px">enviada <?php echo htmlspecialchars($s['emailed_at']); ?></span><?php endif; ?>
                                <?php endif; ?>
                                <?php if ($s['num_recogida'] && !$s['cancelled_at']): ?>
                                    <form method="post" action="<?php echo tep_href_link('rma.php', 'action=cex-cancel'); ?>" style="display:inline" onsubmit="return confirm('¿Anular la recogida <?php echo htmlspecialchars($s['num_recogida']); ?>?');">
                                        <input type="hidden" name="ship" value="<?php echo (int) $s['id']; ?>">
                                        <button class="xbutton rojo" style="font-size:11px;padding:2px 8px" type="submit">✕ Anular recogida</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo tep_href_link('rma.php', 'action=request-pickup'); ?>" class="column a12" style="font-size:12px">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <div style="margin-bottom:5px">
                Recoger en: <strong><?php echo htmlspecialchars(trim($rmaDetail['customers_name'])); ?></strong>,
                <?php echo htmlspecialchars(trim($rmaDetail['customers_street_address'] . ' ' . $rmaDetail['customers_suburb'])); ?>,
                <?php echo htmlspecialchars(trim($rmaDetail['customers_postcode'] . ' ' . $rmaDetail['customers_city'])); ?>
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-bottom:6px">
                <label>Peso (kg): <input type="number" name="kilos" step="0.1" min="0.1" value="1" style="width:60px;padding:3px;border:1px solid #aaa;border-radius:3px"></label>
                <label>Fecha recogida: <input type="date" name="fecha" value="<?php echo $formFecha; ?>" style="padding:3px;border:1px solid #aaa;border-radius:3px"></label>
                <label>De <input type="time" name="hora_desde" value="<?php echo $formDesde; ?>" style="padding:3px;border:1px solid #aaa;border-radius:3px"></label>
                <label>a <input type="time" name="hora_hasta" value="<?php echo $formHasta; ?>" style="padding:3px;border:1px solid #aaa;border-radius:3px"></label>
                <label><input type="checkbox" name="latente" value="1"> Sin fecha fija (latente)</label>
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
                <button class="xbutton verde" type="submit" name="mode" value="envio">📦 Envío + recogida (con etiqueta)</button>
                <button class="xbutton azul" type="submit" name="mode" value="recogida">🚚 Solo recogida (a domicilio)</button>
                <button class="xbutton" style="background:#8e44ad;color:#fff" type="submit" name="mode" value="oficina"
                        title="Genera la etiqueta sin recogida; el cliente deposita el paquete en una oficina CEX">🏤 Etiqueta para oficina (depósito)</button>
            </div>
        </form>

        <?php /* Buscador de oficinas CEX que admiten depósito (lazy: solo al pulsar) */ ?>
        <div class="column a12" style="margin-top:8px;border-top:1px dashed #b9d7f5;padding-top:6px;font-size:12px">
            <?php $showOff = (isset($_GET['cex_offices']) && $_GET['cex_offices'] == '1'); $cpCli = trim($rmaDetail['customers_postcode']); ?>
            <?php if (!$showOff): ?>
                <a class="xbutton" style="font-size:11px;padding:2px 8px;background:#8e44ad;color:#fff"
                   href="<?php echo tep_href_link('rma.php', 'action=view&id=' . $id . '&cex_offices=1'); ?>#cexofi">🏤 Ver oficinas de depósito cercanas (CP <?php echo htmlspecialchars($cpCli); ?>)</a>
            <?php else: ?>
                <a id="cexofi"></a>
                <strong>Oficinas CEX que admiten depósito cerca de <?php echo htmlspecialchars($cpCli); ?>:</strong>
                <?php $ofis = cexOficinasDeposito($cpCli); ?>
                <?php if (!$ofis): ?>
                    <div><em>No se han encontrado oficinas para este código postal.</em></div>
                <?php else: foreach ($ofis as $o): $map = cexMapsLink($o['geoposicionOficina'] ?? ''); ?>
                    <div style="padding:4px 0;border-bottom:1px solid #d6e6f7">
                        <strong><?php echo htmlspecialchars($o['nombreOficina'] ?? ''); ?></strong>
                        <?php if ($map): ?> · <a href="<?php echo $map; ?>" target="_blank">mapa</a><?php endif; ?><br>
                        <?php echo htmlspecialchars(trim(($o['direccionOficina'] ?? '') . ', ' . ($o['codigoPostalOficina'] ?? '') . ' ' . ($o['poblacionOficina'] ?? ''))); ?>
                        <?php if (!empty($o['telefonoOficina'])): ?> · ☎ <?php echo htmlspecialchars($o['telefonoOficina']); ?><?php endif; ?><br>
                        <span style="color:#777"><?php echo htmlspecialchars($o['horarioOficina'] ?? ''); ?></span>
                    </div>
                <?php endforeach; endif; ?>
                <a style="font-size:11px" href="<?php echo tep_href_link('rma.php', 'action=view&id=' . $id); ?>">ocultar</a>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
