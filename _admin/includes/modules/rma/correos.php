<?php
/**
 * Integración Correos (de España) en el módulo RMA del admin.
 *
 * Flujo: etiqueta de devolución (Preregister PAAZE/DOURUA + Labels PDF) que el
 * cliente deposita en CUALQUIER oficina de Correos. No hay recogida a domicilio
 * (la API "requests" no está suscrita en el Portal de Desarrolladores).
 *
 * Acciones (añadidas al switch de rmaSection en functions.php):
 *   - correos-label-gen   : preregistra la devolución + genera etiqueta PDF
 *   - correos-label       : descarga el PDF de etiqueta almacenado
 *   - correos-email-label : envía el PDF de etiqueta por email al cliente
 *   - correos-cancel      : anula el preregistro (annulment por packageCode)
 *
 * Render de la caja del operador: rmaCorreosRenderBox($rmaDetail) — desde views/view.php.
 *
 * Entorno: SIEMPRE 'pro' (la app del Portal de PRUEBAS solo tiene preregister,
 * Labels rechazaría sus cabeceras; el preregistro es anulable y no se factura
 * hasta que el paquete entra en la red). Sin interruptor test/pro.
 *
 * Doc/credenciales/gotchas: memoria francobordo_correos_api.
 */

require_once DIR_FS_CATALOG . 'includes/classes/correos.php';

/* ====================================================================== *
 *  Helpers                                                               *
 * ====================================================================== */

/** Fila cruda de rma (para construir la petición a Correos). */
function correosRmaGet($id_rma) {
    $q = tep_db_query('SELECT * FROM ' . TABLE_RMA . ' WHERE id_rma = ' . (int) $id_rma);
    return tep_db_fetch_array($q);
}

/** Envíos Correos de un RMA, más reciente primero. */
function correosShipmentsFor($id_rma) {
    $rows = array();
    $q = tep_db_query('SELECT * FROM correos_shipments WHERE id_rma = ' . (int) $id_rma . ' ORDER BY id DESC');
    while ($r = tep_db_fetch_array($q)) $rows[] = $r;
    return $rows;
}

/** Identifica al operador logueado (best-effort, solo para traza). */
function correosOperator() {
    foreach (array('login_email_address', 'login_id', 'login_first_name') as $k) {
        if (!empty($_SESSION[$k])) return (string) $_SESSION[$k];
    }
    return '';
}

/** Anota una nota privada en el histórico del RMA (sin cambiar de estado ni notificar). */
function correosNote($id_rma, $texto) {
    $rma = correosRmaGet($id_rma);
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

/** Guarda el PDF (binario) bajo images/rma/{id}/ con nombre aleatorio. Devuelve ruta relativa o null. */
function correosSaveLabelBin($id_rma, $pdfBin) {
    if (!is_string($pdfBin) || strncmp($pdfBin, '%PDF', 4) !== 0) return null;
    $dir = DIR_FS_CATALOG . 'images/rma/' . (int) $id_rma . '/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $name = 'correos_label_' . (int) $id_rma . '_' . substr(md5($pdfBin . microtime(true)), 0, 10) . '.pdf';
    if (@file_put_contents($dir . $name, $pdfBin) === false) return null;
    return (int) $id_rma . '/' . $name; // relativa bajo images/rma/
}

/** Código de provincia (Anexo V) desde un CP español de 5 dígitos = sus 2 primeros dígitos. */
function correosProvinciaFromCp($cp) {
    $cp = trim((string) $cp);
    return preg_match('/^\d{5}$/', $cp) ? substr($cp, 0, 2) : '';
}

/** Estado de tracking (correos_tracking, poblada por cron_correos_tracking) de una referencia RMA{id}; null si no hay. */
function correosTrackEstado($ref) {
    $ref = trim((string) $ref);
    if ($ref === '') return null;
    $q = tep_db_query("SELECT estado_desc, entregado, last_checked FROM correos_tracking WHERE referencia = '" . tep_db_input($ref) . "' LIMIT 1");
    return tep_db_num_rows($q) ? tep_db_fetch_array($q) : null;
}

/* ====================================================================== *
 *  Acción: preregistrar devolución + generar etiqueta                   *
 * ====================================================================== */

function rmaCorreosGenerateLabel() {
    $id  = (int) ($_POST['id'] ?? 0);
    $rma = correosRmaGet($id);
    if (!$rma) { tep_redirect(tep_href_link('rma.php', 'action=view&id=' . $id)); }

    $kilos = str_replace(',', '.', trim((string) ($_POST['kilos'] ?? '1')));
    if ($kilos === '' || (float) $kilos <= 0) $kilos = '1';
    $gramos = (int) round((float) $kilos * 1000);

    $c = new correos('pro');
    $c->setTimeout(60);

    // Devolución: remitente = cliente, destinatario = Francobordo (PAAZE + DOURUA).
    $out = $c->etiquetaDevolucionRma($rma, array(
        'weightGrams' => (string) $gramos,
        'province'    => correosProvinciaFromCp($rma['customers_postcode']),
    ));

    $labelPath = !empty($out['pdf_bin']) ? correosSaveLabelBin($id, $out['pdf_bin']) : null;

    tep_db_perform('correos_shipments', array(
        'id_rma'          => $id,
        'entorno'         => 'pro',
        'shipment_code'   => $out['shipmentCode'],
        'package_code'    => $out['packageCode'],
        'producto'        => correos::PROD_RETORNO,
        'ref'             => 'RMA' . str_pad((string) $id, 8, '0', STR_PAD_LEFT),
        'kilos'           => (float) $kilos,
        'label_format'    => 'pdf',
        'label_path'      => $labelPath,
        'http_code'       => (string) ($out['preregister']['http'] ?? ''),
        'mensaje_retorno' => $out['ok'] ? '' : $out['error'],
        'ok'              => $out['ok'] ? 1 : 0,
        'request_json'    => json_encode($c->lastRequest, JSON_UNESCAPED_UNICODE),
        'response_json'   => ($out['preregister']['raw'] ?? '') . "\n---LABEL---\n" . ($out['label']['raw'] ?? ''),
        'operator'        => correosOperator(),
        'date_added'      => 'now()',
    ));

    correosNote($id, $out['ok']
        ? 'Correos: devolución ' . $out['shipmentCode'] . ' preregistrada (etiqueta disponible, depósito en oficina).'
        : 'Correos ERROR: ' . $out['error']);

    tep_redirect(tep_href_link('rma.php', 'action=view&id=' . $id));
}

/* ====================================================================== *
 *  Acción: descargar etiqueta PDF                                       *
 * ====================================================================== */

function rmaCorreosDownloadLabel() {
    $shipId = (int) ($_GET['ship'] ?? 0);
    $q = tep_db_query('SELECT * FROM correos_shipments WHERE id = ' . $shipId);
    $s = tep_db_fetch_array($q);
    if (!$s || empty($s['label_path'])) { tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) ($s['id_rma'] ?? 0))); }
    $path = DIR_FS_CATALOG . 'images/rma/' . $s['label_path'];
    if (!is_file($path)) { tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) $s['id_rma'])); }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="etiqueta_correos_rma' . (int) $s['id_rma'] . '.pdf"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
}

/* ====================================================================== *
 *  Acción: enviar etiqueta por email al cliente                         *
 * ====================================================================== */

function rmaCorreosEmailLabel() {
    $shipId = (int) ($_POST['ship'] ?? 0);
    $q = tep_db_query('SELECT * FROM correos_shipments WHERE id = ' . $shipId);
    $s = tep_db_fetch_array($q);
    if (!$s || empty($s['label_path'])) { tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) ($s['id_rma'] ?? 0))); }

    $rma  = correosRmaGet($s['id_rma']);
    $path = DIR_FS_CATALOG . 'images/rma/' . $s['label_path'];
    $name = trim($rma['customers_name']) ?: 'Cliente';
    $mailTo = trim($rma['customers_email_address']);

    if ($mailTo !== '' && is_file($path)) {
        $idFmt = str_pad((string) $s['id_rma'], 10, '0', STR_PAD_LEFT);
        $html = '<p>Hola ' . htmlspecialchars($name) . ',</p>'
              . '<p>Adjuntamos la etiqueta de Correos para la devolución de su RMA <strong>' . $idFmt . '</strong>.</p>'
              . '<p>Por favor, imprímala, péguela en el paquete y <strong>deposítelo en cualquier oficina de Correos</strong>.</p>'
              . '<p>Puede localizar su oficina más cercana aquí: '
              . '<a href="https://www.correos.es/es/es/herramientas/oficinas-buzones-citypaq">Buscador de oficinas de Correos</a></p>'
              . '<p>Gracias,<br>' . STORE_NAME . '</p>';
        // tep_mail() = PHPMailer (ruta de entrega real, con bypass SendGrid para internos
        // y soporte de adjunto). NO usar la clase email antigua (caché de bounces).
        $attach = array(
            'tmp_name' => $path,
            'name'     => 'etiqueta_correos_rma' . (int) $s['id_rma'] . '.pdf',
        );
        tep_mail($name, $mailTo, 'Etiqueta de devolución Correos - RMA ' . $idFmt,
                 $html, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, $attach);

        tep_db_perform('correos_shipments', array('emailed_at' => 'now()'), 'update', 'id = ' . $shipId);
        correosNote($s['id_rma'], 'Correos: etiqueta de la devolución ' . ($s['shipment_code'] ?: '') . ' enviada por email a ' . $mailTo . '.');
    }
    tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) $s['id_rma']));
}

/* ====================================================================== *
 *  Acción: anular preregistro                                           *
 * ====================================================================== */

function rmaCorreosCancel() {
    $shipId = (int) ($_POST['ship'] ?? 0);
    $q = tep_db_query('SELECT * FROM correos_shipments WHERE id = ' . $shipId);
    $s = tep_db_fetch_array($q);
    if (!$s || empty($s['package_code']) || !empty($s['cancelled_at'])) { tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) ($s['id_rma'] ?? 0))); }

    $c = new correos($s['entorno'] ?: 'pro');
    $c->setTimeout(60);
    $res = $c->annulment($s['package_code']);   // reintenta internamente (endpoint inestable)

    if (correos::annulmentOk($res)) {
        tep_db_perform('correos_shipments', array('cancelled_at' => 'now()'), 'update', 'id = ' . $shipId);
        correosNote($s['id_rma'], 'Correos: devolución ' . $s['shipment_code'] . ' anulada.');
    } else {
        $msg = is_array($res['data'] ?? null) ? (string) ($res['data']['message'] ?? ($res['data']['error'] ?? '')) : ('HTTP ' . ($res['http'] ?? '?'));
        correosNote($s['id_rma'], 'Correos: ERROR al anular la devolución ' . $s['shipment_code'] . ' (' . $msg . '). Reintentar más tarde.');
    }
    tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) $s['id_rma']));
}

/* ====================================================================== *
 *  Render: caja del operador en views/view.php                          *
 * ====================================================================== */

function rmaCorreosRenderBox($rmaDetail) {
    $id   = (int) $rmaDetail['id_rma'];
    $rows = correosShipmentsFor($id);
    ?>
    <div class="rows sp10 column a12" style="margin-top:8px;padding:10px;background:#fffbe6;border:1px solid #e8d27a;border-radius:4px">
        <div class="column a12" style="font-weight:bold;margin-bottom:6px;display:flex;align-items:center;justify-content:space-between">
            <span>
                <i class="fa fa-envelope"></i> Correos
                <span style="color:#2e7d32;font-size:11px" title="No hay entorno de pruebas con etiquetas; el preregistro es anulable y no se factura hasta que el paquete entra en la red">&nbsp;(PRODUCCIÓN)</span>
            </span>
            <span style="font-size:11px;color:#8a6d1a;font-weight:normal">depósito en cualquier oficina de Correos</span>
        </div>

        <?php if ($rows): ?>
            <div class="column a12" style="margin-bottom:8px">
                <?php foreach ($rows as $s): ?>
                    <div style="font-size:12px;padding:5px 0;border-bottom:1px solid #ecdfa8;<?php echo $s['cancelled_at'] ? 'opacity:.5' : ''; ?>">
                        <strong>Devolución</strong>
                        <?php echo $s['ok'] ? '<span style="color:#2e7d32">✔</span>' : '<span style="color:#c0392b">✖ HTTP ' . htmlspecialchars($s['http_code']) . '</span>'; ?>
                        <?php if ($s['shipment_code']): ?> · env. <code><?php echo htmlspecialchars($s['shipment_code']); ?></code><?php endif; ?>
                        <?php if ($s['package_code']): ?> · bulto <code><?php echo htmlspecialchars($s['package_code']); ?></code><?php endif; ?>
                        <?php if ($s['cancelled_at']): ?> · <em>anulada</em><?php endif; ?>
                        <?php $trk = correosTrackEstado($s['ref']); if ($trk): ?> · <strong style="color:<?php echo $trk['entregado'] ? '#2e7d32' : '#0a6ebd'; ?>">📍 <?php echo htmlspecialchars($trk['estado_desc']); ?></strong><span style="color:#999;font-size:11px"> (<?php echo date('d/m H:i', strtotime($trk['last_checked'])); ?>)</span><?php endif; ?>
                        <br><span style="color:#777"><?php echo htmlspecialchars($s['date_added']); ?><?php if (!$s['ok'] && $s['mensaje_retorno']): ?> — <?php echo htmlspecialchars($s['mensaje_retorno']); ?><?php endif; ?></span>
                        <?php if ($s['ok']): ?>
                            <div style="margin-top:3px">
                                <?php if ($s['label_path']): ?>
                                    <a class="xbutton" style="font-size:11px;padding:2px 8px" href="<?php echo tep_href_link('rma.php', 'action=correos-label&ship=' . (int) $s['id']); ?>" target="_blank">⬇ Etiqueta</a>
                                    <form method="post" action="<?php echo tep_href_link('rma.php', 'action=correos-email-label'); ?>" style="display:inline">
                                        <input type="hidden" name="ship" value="<?php echo (int) $s['id']; ?>">
                                        <button class="xbutton verde" style="font-size:11px;padding:2px 8px" type="submit">✉ <?php echo $s['emailed_at'] ? 'Reenviar al cliente' : 'Enviar al cliente'; ?></button>
                                    </form>
                                    <?php if ($s['emailed_at']): ?><span style="color:#777;font-size:11px">enviada <?php echo htmlspecialchars($s['emailed_at']); ?></span><?php endif; ?>
                                <?php endif; ?>
                                <?php if (!$s['cancelled_at']): ?>
                                    <form method="post" action="<?php echo tep_href_link('rma.php', 'action=correos-cancel'); ?>" style="display:inline" onsubmit="return confirm('¿Anular la devolución <?php echo htmlspecialchars($s['shipment_code']); ?>?');">
                                        <input type="hidden" name="ship" value="<?php echo (int) $s['id']; ?>">
                                        <button class="xbutton rojo" style="font-size:11px;padding:2px 8px" type="submit">✕ Anular</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo tep_href_link('rma.php', 'action=correos-label-gen'); ?>" class="column a12" style="font-size:12px">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <div style="margin-bottom:5px">
                Devolución desde: <strong><?php echo htmlspecialchars(trim($rmaDetail['customers_name'])); ?></strong>,
                <?php echo htmlspecialchars(trim($rmaDetail['customers_street_address'] . ' ' . $rmaDetail['customers_suburb'])); ?>,
                <?php echo htmlspecialchars(trim($rmaDetail['customers_postcode'] . ' ' . $rmaDetail['customers_city'])); ?>
                → Francobordo (Alcobendas)
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-bottom:6px">
                <label>Peso (kg): <input type="number" name="kilos" step="0.1" min="0.1" value="1" style="width:60px;padding:3px;border:1px solid #aaa;border-radius:3px"></label>
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
                <button class="xbutton" style="background:#f2c200;color:#333" type="submit"
                        title="Preregistra la devolución y genera la etiqueta; el cliente deposita el paquete en cualquier oficina de Correos">🏤 Generar etiqueta de devolución Correos</button>
            </div>
        </form>
    </div>
    <?php
}
