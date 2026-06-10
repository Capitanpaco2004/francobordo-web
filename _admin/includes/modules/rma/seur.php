<?php
/**
 * Integración SEUR en el módulo RMA del admin (réplica de cex.php).
 *
 * Acciones (añadidas al switch de rmaSection en functions.php):
 *   - seur-label-gen    : graba envío de devolución + genera etiqueta PDF contra SEUR
 *   - seur-label        : descarga el PDF de etiqueta almacenado
 *   - seur-email-label  : envía el PDF de etiqueta por email al cliente
 *   - seur-cancel       : anula un envío en SEUR
 *   - seur-set-env      : interruptor global entorno PRE/PRO
 *
 * Render de la caja del operador: rmaSeurRenderBox($rmaDetail) — invocado desde views/view.php.
 *
 * Doc/credenciales: memoria francobordo_seur_api.
 */

require_once DIR_FS_CATALOG . 'includes/classes/seur.php';

// Entorno activo: se guarda en la tabla seur_config. Default 'pre' (pruebas).
function seurGetEnv() {
    $q = tep_db_query("SELECT config_value FROM seur_config WHERE config_key = 'env'");
    if ($q && tep_db_num_rows($q)) {
        $v = tep_db_fetch_array($q);
        return ($v['config_value'] === 'pro') ? 'pro' : 'pre';
    }
    return 'pre';
}
function seurSetEnv($env) {
    $env = ($env === 'pro') ? 'pro' : 'pre';
    tep_db_query("REPLACE INTO seur_config (config_key, config_value) VALUES ('env', '" . tep_db_input($env) . "')");
}
if (!defined('SEUR_ENV')) define('SEUR_ENV', seurGetEnv());

/* ====================================================================== *
 *  Helpers                                                               *
 * ====================================================================== */

/** Fila cruda de rma. */
function seurGetRma($id_rma) {
    $q = tep_db_query('SELECT * FROM ' . TABLE_RMA . ' WHERE id_rma = ' . (int) $id_rma);
    return tep_db_fetch_array($q);
}

/** Envíos SEUR de un RMA, más reciente primero. */
function seurShipmentsFor($id_rma) {
    $rows = array();
    $q = tep_db_query('SELECT * FROM seur_shipments WHERE id_rma = ' . (int) $id_rma . ' ORDER BY id DESC');
    while ($r = tep_db_fetch_array($q)) $rows[] = $r;
    return $rows;
}

/** Operador logueado (best-effort, solo para traza). */
function seurOperator() {
    foreach (array('login_email_address', 'login_id', 'login_first_name') as $k) {
        if (!empty($_SESSION[$k])) return (string) $_SESSION[$k];
    }
    return '';
}

/** Nota privada en el histórico del RMA (sin cambiar estado ni notificar). */
function seurNote($id_rma, $texto) {
    $rma = seurGetRma($id_rma);
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

/** Guarda el PDF de etiqueta (binario) bajo images/rma/{id}/ con nombre aleatorio. Devuelve ruta relativa o null. */
function seurSaveLabelBin($id_rma, $pdfBin) {
    if (!is_string($pdfBin) || strncmp($pdfBin, '%PDF', 4) !== 0) return null;
    $dir = DIR_FS_CATALOG . 'images/rma/' . (int) $id_rma . '/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $name = 'seur_label_' . (int) $id_rma . '_' . substr(md5($pdfBin . microtime(true)), 0, 10) . '.pdf';
    if (@file_put_contents($dir . $name, $pdfBin) === false) return null;
    return (int) $id_rma . '/' . $name;
}

/* ====================================================================== *
 *  Acción: generar envío de devolución + etiqueta                       *
 * ====================================================================== */

function rmaSeurGenerateLabel() {
    $id  = (int) ($_POST['id'] ?? 0);
    $rma = seurGetRma($id);
    if (!$rma) { tep_redirect(tep_href_link('rma.php', 'action=view&id=' . $id)); }

    $kilos = str_replace(',', '.', trim((string) ($_POST['kilos'] ?? '1')));
    if ($kilos === '' || (float) $kilos <= 0) $kilos = '1';

    $seur = new seur(SEUR_ENV);
    $seur->setTimeout(60); // el endpoint de envíos de PRE es lento

    // Devolución: remitente = cliente, destinatario = Francobordo (servicio nacional 031/002).
    $shipment = seur::devolucionDesdeRma($rma, array('weight' => (float) $kilos));
    $out = $seur->grabarConEtiqueta($shipment, 'PDF');

    $labelPath = (!empty($out['pdf_bin'])) ? seurSaveLabelBin($id, $out['pdf_bin']) : null;
    $bultos    = seur::extraerBultos($out['shipment']);

    tep_db_perform('seur_shipments', array(
        'id_rma'          => $id,
        'tipo'            => 'envio',
        'entorno'         => SEUR_ENV,
        'shipment_code'   => $out['shipmentCode'],
        'ecb'             => $bultos['ecbs'][0] ?? null,
        'parcel_number'   => $bultos['parcelNumbers'][0] ?? null,
        'service_code'    => $shipment['serviceCode'],
        'product_code'    => $shipment['productCode'],
        'ref'             => $shipment['ref'],
        'kilos'           => (float) $kilos,
        'label_format'    => 'pdf',
        'label_path'      => $labelPath,
        'http_code'       => (string) ($out['shipment']['http'] ?? ''),
        'mensaje_retorno' => $out['ok'] ? '' : $out['error'],
        'ok'              => $out['ok'] ? 1 : 0,
        'request_json'    => json_encode($seur->lastRequest, JSON_UNESCAPED_UNICODE),
        'response_json'   => ($out['shipment']['raw'] ?? '') . "\n---LABEL---\n" . ($out['label_resp']['raw'] ?? ''),
        'operator'        => seurOperator(),
        'date_added'      => 'now()',
    ));

    seurNote($id, $out['ok']
        ? 'SEUR (' . SEUR_ENV . '): envío de devolución ' . $out['shipmentCode'] . ' generado (etiqueta disponible).'
        : 'SEUR (' . SEUR_ENV . ') ERROR: ' . $out['error']);

    tep_redirect(tep_href_link('rma.php', 'action=view&id=' . $id));
}

/* ====================================================================== *
 *  Acción: descargar etiqueta PDF                                       *
 * ====================================================================== */

function rmaSeurDownloadLabel() {
    $shipId = (int) ($_GET['ship'] ?? 0);
    $q = tep_db_query('SELECT * FROM seur_shipments WHERE id = ' . $shipId);
    $s = tep_db_fetch_array($q);
    if (!$s || empty($s['label_path'])) { tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) ($s['id_rma'] ?? 0))); }
    $path = DIR_FS_CATALOG . 'images/rma/' . $s['label_path'];
    if (!is_file($path)) { tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) $s['id_rma'])); }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="etiqueta_seur_rma' . (int) $s['id_rma'] . '.pdf"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
}

/* ====================================================================== *
 *  Acción: enviar etiqueta por email al cliente                         *
 * ====================================================================== */

function rmaSeurEmailLabel() {
    $shipId = (int) ($_POST['ship'] ?? 0);
    $q = tep_db_query('SELECT * FROM seur_shipments WHERE id = ' . $shipId);
    $s = tep_db_fetch_array($q);
    if (!$s || empty($s['label_path'])) { tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) ($s['id_rma'] ?? 0))); }

    $rma  = seurGetRma($s['id_rma']);
    $path = DIR_FS_CATALOG . 'images/rma/' . $s['label_path'];
    $name = trim($rma['customers_name']) ?: 'Cliente';
    $mailTo = trim($rma['customers_email_address']);

    if ($mailTo !== '' && is_file($path)) {
        $idFmt = str_pad((string) $s['id_rma'], 10, '0', STR_PAD_LEFT);
        $html = '<p>Hola ' . htmlspecialchars($name) . ',</p>'
              . '<p>Adjuntamos la etiqueta de SEUR para la devolución de su RMA <strong>' . $idFmt . '</strong>.</p>'
              . '<p>Por favor, imprímala y péguela en el paquete.</p>'
              . '<p>Gracias,<br>' . STORE_NAME . '</p>';
        // tep_mail() = PHPMailer (ruta de entrega real, con bypass SendGrid para internos
        // y soporte de adjunto). NO usar la clase email antigua (caché de bounces).
        $attach = array(
            'tmp_name' => $path,
            'name'     => 'etiqueta_seur_rma' . (int) $s['id_rma'] . '.pdf',
        );
        tep_mail($name, $mailTo, 'Etiqueta de devolución SEUR - RMA ' . $idFmt,
                 $html, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, $attach);

        tep_db_perform('seur_shipments', array('emailed_at' => 'now()'), 'update', 'id = ' . $shipId);
        seurNote($s['id_rma'], 'SEUR: etiqueta del envío ' . ($s['shipment_code'] ?: '') . ' enviada por email a ' . $mailTo . '.');
    }
    tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) $s['id_rma']));
}

/* ====================================================================== *
 *  Acción: anular envío                                                 *
 * ====================================================================== */

function rmaSeurCancel() {
    $shipId = (int) ($_POST['ship'] ?? 0);
    $q = tep_db_query('SELECT * FROM seur_shipments WHERE id = ' . $shipId);
    $s = tep_db_fetch_array($q);
    if (!$s || empty($s['shipment_code']) || !empty($s['cancelled_at'])) { tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) ($s['id_rma'] ?? 0))); }

    $seur = new seur($s['entorno'] ?: SEUR_ENV);
    $seur->setTimeout(60);
    $res = $seur->cancelShipment($s['shipment_code']);
    $d   = seur::payload($res);
    $desc = (is_array($d) && !empty($d[0]['description'])) ? $d[0]['description'] : (seur::primerError($res));
    tep_db_perform('seur_shipments', array('cancelled_at' => 'now()'), 'update', 'id = ' . $shipId);
    seurNote($s['id_rma'], 'SEUR: envío ' . $s['shipment_code'] . ' anulado (' . $desc . ').');
    tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) $s['id_rma']));
}

/* ====================================================================== *
 *  Acción: cambiar entorno PRE/PRO (interruptor global)                 *
 * ====================================================================== */

function rmaSeurSetEnv() {
    $env = (($_POST['env'] ?? '') === 'pro') ? 'pro' : 'pre';
    seurSetEnv($env);
    tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) ($_POST['id'] ?? 0)));
}

/* ====================================================================== *
 *  Render: caja del operador en views/view.php                          *
 * ====================================================================== */

function rmaSeurRenderBox($rmaDetail) {
    $id   = (int) $rmaDetail['id_rma'];
    $rows = seurShipmentsFor($id);
    $env  = SEUR_ENV;
    ?>
    <div class="rows sp10 column a12" style="margin-top:8px;padding:10px;background:#fff3e9;border:1px solid #f3c79b;border-radius:4px">
        <div class="column a12" style="font-weight:bold;margin-bottom:6px;display:flex;align-items:center;justify-content:space-between">
            <span>
                <i class="fa fa-truck"></i> SEUR
                <?php if ($env === 'pre'): ?>
                    <span style="color:#c0392b;font-size:11px">&nbsp;(ENTORNO PRUEBAS)</span>
                <?php else: ?>
                    <span style="color:#2e7d32;font-size:11px">&nbsp;(PRODUCCIÓN)</span>
                <?php endif; ?>
            </span>
            <form method="post" action="<?php echo tep_href_link('rma.php', 'action=seur-set-env'); ?>" style="margin:0"
                  onsubmit="return confirm(<?php echo $env === 'pre' ? "'Vas a ACTIVAR PRODUCCIÓN: los envíos serán REALES. ¿Continuar?'" : "'Volver a ENTORNO DE PRUEBAS (PRE). ¿Continuar?'"; ?>);">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <input type="hidden" name="env" value="<?php echo $env === 'pre' ? 'pro' : 'pre'; ?>">
                <button class="xbutton" style="font-size:10px;padding:1px 6px;font-weight:normal" type="submit">⇄ <?php echo $env === 'pre' ? 'Activar PRODUCCIÓN' : 'Volver a pruebas'; ?></button>
            </form>
        </div>

        <?php if ($rows): ?>
            <div class="column a12" style="margin-bottom:8px">
                <?php foreach ($rows as $s): ?>
                    <div style="font-size:12px;padding:5px 0;border-bottom:1px solid #f3d9c0;<?php echo $s['cancelled_at'] ? 'opacity:.5' : ''; ?>">
                        <strong>Envío</strong>
                        <?php echo $s['ok'] ? '<span style="color:#2e7d32">✔</span>' : '<span style="color:#c0392b">✖ HTTP ' . htmlspecialchars($s['http_code']) . '</span>'; ?>
                        <?php if ($s['shipment_code']): ?> · <code><?php echo htmlspecialchars($s['shipment_code']); ?></code><?php endif; ?>
                        <?php if ($s['ecb']): ?> · ECB <code><?php echo htmlspecialchars($s['ecb']); ?></code><?php endif; ?>
                        <?php if ($s['cancelled_at']): ?> · <em>anulado</em><?php endif; ?>
                        · <span style="color:#999;font-size:11px"><?php echo htmlspecialchars($s['entorno']); ?> · <?php echo htmlspecialchars($s['service_code'] . '/' . $s['product_code']); ?></span>
                        <br><span style="color:#777"><?php echo htmlspecialchars($s['date_added']); ?><?php if (!$s['ok'] && $s['mensaje_retorno']): ?> — <?php echo htmlspecialchars($s['mensaje_retorno']); ?><?php endif; ?></span>
                        <?php if ($s['ok']): ?>
                            <div style="margin-top:3px">
                                <?php if ($s['label_path']): ?>
                                    <a class="xbutton" style="font-size:11px;padding:2px 8px" href="<?php echo tep_href_link('rma.php', 'action=seur-label&ship=' . (int) $s['id']); ?>" target="_blank">⬇ Etiqueta</a>
                                    <form method="post" action="<?php echo tep_href_link('rma.php', 'action=seur-email-label'); ?>" style="display:inline">
                                        <input type="hidden" name="ship" value="<?php echo (int) $s['id']; ?>">
                                        <button class="xbutton verde" style="font-size:11px;padding:2px 8px" type="submit">✉ <?php echo $s['emailed_at'] ? 'Reenviar al cliente' : 'Enviar al cliente'; ?></button>
                                    </form>
                                    <?php if ($s['emailed_at']): ?><span style="color:#777;font-size:11px">enviada <?php echo htmlspecialchars($s['emailed_at']); ?></span><?php endif; ?>
                                <?php endif; ?>
                                <?php if (!$s['cancelled_at']): ?>
                                    <form method="post" action="<?php echo tep_href_link('rma.php', 'action=seur-cancel'); ?>" style="display:inline" onsubmit="return confirm('¿Anular el envío <?php echo htmlspecialchars($s['shipment_code']); ?>?');">
                                        <input type="hidden" name="ship" value="<?php echo (int) $s['id']; ?>">
                                        <button class="xbutton rojo" style="font-size:11px;padding:2px 8px" type="submit">✕ Anular envío</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo tep_href_link('rma.php', 'action=seur-label-gen'); ?>" class="column a12" style="font-size:12px">
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
                <button class="xbutton verde" type="submit" name="mode" value="envio">📦 Generar etiqueta de devolución SEUR</button>
            </div>
        </form>
    </div>
    <?php
}
