<?php
/**
 * Integración SEUR en el módulo RMA del admin (réplica de cex.php).
 *
 * DEVOLUCIONES vía /collections (email SEUR 2026-06-10: las devoluciones NO se
 * graban en /shipments — llevan recogida implícita):
 *   - 🏠 Recogida a DOMICILIO (31/86): el mensajero recoge en casa del cliente
 *     (siempre al día laborable siguiente con label=true). Enviamos la etiqueta
 *     PDF al cliente para que la imprima y pegue.
 *   - 🏤 Devolución desde PUNTO SEUR (31/88 + pickupCentreCode): el cliente
 *     deposita el paquete en el punto elegido mostrando el QR (o la etiqueta).
 *
 * Acciones (switch de rmaSection en functions.php):
 *   seur-label-gen (mode=domicilio|punto) · seur-label · seur-email-label ·
 *   seur-cancel · seur-set-env
 *
 * Render de la caja: rmaSeurRenderBox($rmaDetail) — desde views/view.php.
 * Doc/credenciales: memoria francobordo_seur_api.
 */

require_once DIR_FS_CATALOG . 'includes/classes/seur.php';

// Entorno activo (seur_config). Default 'pre' (pruebas).
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

function seurGetRma($id_rma) {
    $q = tep_db_query('SELECT * FROM ' . TABLE_RMA . ' WHERE id_rma = ' . (int) $id_rma);
    return tep_db_fetch_array($q);
}

function seurShipmentsFor($id_rma) {
    $rows = array();
    $q = tep_db_query('SELECT * FROM seur_shipments WHERE id_rma = ' . (int) $id_rma . ' ORDER BY id DESC');
    while ($r = tep_db_fetch_array($q)) $rows[] = $r;
    return $rows;
}

function seurOperator() {
    foreach (array('login_email_address', 'login_id', 'login_first_name') as $k) {
        if (!empty($_SESSION[$k])) return (string) $_SESSION[$k];
    }
    return '';
}

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

/** Guarda un binario bajo images/rma/{id}/ con nombre aleatorio. Ruta relativa o null. */
function seurSaveBin($id_rma, $bin, $prefix, $ext) {
    if (!is_string($bin) || $bin === '') return null;
    $dir = DIR_FS_CATALOG . 'images/rma/' . (int) $id_rma . '/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $name = $prefix . '_' . (int) $id_rma . '_' . substr(md5($bin . microtime(true)), 0, 10) . '.' . $ext;
    if (@file_put_contents($dir . $name, $bin) === false) return null;
    return (int) $id_rma . '/' . $name;
}

/** Estado de seguimiento de un envío/recogida SEUR (seur_tracking, cron horario). */
function seurTrackEstado($ref) {
    $ref = trim((string) $ref);
    if ($ref === '') return null;
    $q = tep_db_query("SELECT estado_desc, entregado, last_checked FROM seur_tracking WHERE referencia = '" . tep_db_input($ref) . "' LIMIT 1");
    return tep_db_num_rows($q) ? tep_db_fetch_array($q) : null;
}

/** Puntos SEUR cercanos a un CP (usa la caché del módulo de envío del catálogo). */
function seurPuntosCercanos($cp, $city = '') {
    if (!class_exists('seurpunto')) {
        $f = DIR_FS_CATALOG . 'includes/modules/shipping/seurpunto.php';
        if (is_file($f)) require_once $f;
    }
    if (!class_exists('seurpunto') || trim((string) $cp) === '') return array();
    return seurpunto::puntos($cp, $city, 'ES');
}

/* ====================================================================== *
 *  Acción: crear devolución (recogida domicilio / depósito en punto)     *
 * ====================================================================== */

function rmaSeurGenerateLabel() {
    $id  = (int) ($_POST['id'] ?? 0);
    $rma = seurGetRma($id);
    if (!$rma) { tep_redirect(tep_href_link('rma.php', 'action=view&id=' . $id)); }

    $mode  = (($_POST['mode'] ?? '') === 'punto') ? 'punto' : 'domicilio';
    $kilos = str_replace(',', '.', trim((string) ($_POST['kilos'] ?? '1')));
    if ($kilos === '' || (float) $kilos <= 0) $kilos = '1';
    $fecha = trim((string) ($_POST['fecha'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $fecha = seur::proximoDiaLaborable();
    $pudo  = trim((string) ($_POST['seur_pudo'] ?? ''));
    $pudoName = ''; $pudoHours = '';

    if ($mode === 'punto') {
        if ($pudo === '') {
            seurNote($id, 'SEUR: no se generó la devolución desde punto — falta elegir el punto.');
            tep_redirect(tep_href_link('rma.php', 'action=view&id=' . $id));
        }
        foreach (seurPuntosCercanos($rma['customers_postcode'], $rma['customers_city']) as $p) {
            if ($p['id'] === $pudo) {
                $pudoName  = $p['name'] . ' - ' . $p['address'] . ' (' . $p['cp'] . ' ' . $p['city'] . ')';
                $pudoHours = (string) ($p['hours'] ?? '');
                break;
            }
        }
        // El punto pudo elegirse desde el self-service web con otro CP (el de entrega
        // del pedido): si no está entre los cercanos, usar el nombre registrado.
        if ($pudoName === '' && trim((string) ($rma['seur_pudo_id'] ?? '')) === $pudo) {
            $pudoName = trim((string) ($rma['seur_pudo_name'] ?? ''));
            // El cliente lo eligio buscando en OTRO CP: el horario se recupera de la
            // cache de puntos de ese CP (va embebido en el nombre como "(NNNNN ").
            if (preg_match('/\((\d{5}) /', $pudoName, $mCp)) {
                $pp = class_exists('seurpunto') ? seurpunto::puntoById($pudo, $mCp[1]) : false;
                if ($pp) $pudoHours = (string) ($pp['hours'] ?? '');
            }
        }
    }

    $seur = new seur(SEUR_ENV);
    $seur->setTimeout(60);

    $opts = array('tipo' => $mode, 'weight' => (float) $kilos, 'fecha' => $fecha, 'label' => true);
    if ($mode === 'punto') $opts['pickupCentreCode'] = $pudo;
    $c = seur::devolucionRecogidaDesdeRma($rma, $opts);

    $res = $seur->createCollection($c);
    $reqShip = $seur->lastRequest;
    $loc = seur::extraerCollectionRef($res);
    $d   = seur::payload($res);
    $ok  = ($res['ok'] && $loc);

    $labelPath = null; $qrPath = null;
    if ($ok) {
        $lab = $seur->getLabel($loc, 'PDF', array('entity' => 'COLLECTIONS', 'qr' => ($mode === 'punto')));
        if (!empty($lab['pdf_bin'])) $labelPath = seurSaveBin($id, $lab['pdf_bin'], 'seur_devol', 'pdf');
        if (!empty($lab['qr'])) {
            $qrBin = base64_decode($lab['qr'], true);
            if ($qrBin !== false) $qrPath = seurSaveBin($id, $qrBin, 'seur_qr', 'png');
        }
    }

    tep_db_perform('seur_shipments', array(
        'id_rma'          => $id,
        'tipo'            => 'recogida',
        'entorno'         => SEUR_ENV,
        'shipment_code'   => $loc,
        'ecb'             => $d['ecbs'][0] ?? null,
        'parcel_number'   => $d['parcelNumbers'][0] ?? null,
        'service_code'    => (string) $c['serviceCode'],
        'product_code'    => (string) $c['productCode'],
        'ref'             => $c['ref'],
        'kilos'           => (float) $kilos,
        'fecha_recogida'  => ($mode === 'domicilio') ? $fecha : null,
        'pudo_id'         => ($mode === 'punto') ? $pudo : null,
        'pudo_name'       => ($mode === 'punto') ? $pudoName : null,
        'pudo_hours'      => ($mode === 'punto' && $pudoHours !== '') ? $pudoHours : null,
        'label_format'    => 'pdf',
        'label_path'      => $labelPath ? (DIR_FS_CATALOG . 'images/rma/' . $labelPath) : null,
        'qr_path'         => $qrPath ? (DIR_FS_CATALOG . 'images/rma/' . $qrPath) : null,
        'http_code'       => (string) $res['http'],
        'mensaje_retorno' => $ok ? '' : seur::primerError($res),
        'ok'              => $ok ? 1 : 0,
        'request_json'    => json_encode($reqShip, JSON_UNESCAPED_UNICODE),
        'response_json'   => $res['raw'],
        'operator'        => seurOperator(),
        'date_added'      => 'now()',
    ));

    seurNote($id, $ok
        ? 'SEUR (' . SEUR_ENV . '): devolución ' . ($mode === 'punto' ? 'desde punto ' . $pudo . ($pudoName ? ' (' . $pudoName . ')' : '') : 'con recogida a domicilio el ' . $fecha) . ' — localizador ' . $loc . '.'
        : 'SEUR (' . SEUR_ENV . ') ERROR devolución: ' . seur::primerError($res));

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
    $path = $s['label_path'];
    if (!is_file($path)) { tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) $s['id_rma'])); }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="etiqueta_seur_rma' . (int) $s['id_rma'] . '.pdf"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
}

/* ====================================================================== *
 *  Acción: enviar etiqueta/QR por email al cliente                      *
 * ====================================================================== */

function rmaSeurEmailLabel() {
    $shipId = (int) ($_POST['ship'] ?? 0);
    $q = tep_db_query('SELECT * FROM seur_shipments WHERE id = ' . $shipId);
    $s = tep_db_fetch_array($q);
    if (!$s || empty($s['label_path'])) { tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) ($s['id_rma'] ?? 0))); }

    $rma  = seurGetRma($s['id_rma']);
    $path = $s['label_path'];
    $name = trim($rma['customers_name']) ?: 'Cliente';
    $mailTo = trim($rma['customers_email_address']);

    if ($mailTo !== '' && is_file($path)) {
        $idFmt = str_pad((string) $s['id_rma'], 10, '0', STR_PAD_LEFT);
        $instrucciones = '<p>Le rogamos que siga las siguientes instrucciones:</p>'
            . '<p>El producto debe enviarse correctamente. Le recordamos que el producto debe llegarnos en perfecto estado, '
            . 'con el embalaje original sin pintadas, roturas ni pegatinas, y que debe venir acompañado de cualquier accesorio o manual, si lo hubiera.<br>'
            . 'Nunca envíe el producto directamente en la caja original.<br>'
            . 'Usted dispone de <strong>7 días</strong> para hacernos llegar el producto.</p>'
            . '<p>Una vez que el producto llegue a nuestro almacén, será comprobado por nuestro personal, que verificará que no presente '
            . 'señales de uso, y se procederá al reembolso correspondiente mediante el método que haya solicitado.</p>';

        if (!empty($s['pudo_id'])) {
            // Devolución desde punto: QR incrustado + etiqueta adjunta
            $qrImg = '';
            if (!empty($s['qr_path']) && is_file($s['qr_path'])) {
                $qrImg = '<p style="text-align:center"><img alt="QR SEUR" style="width:220px" src="data:image/png;base64,' . base64_encode(file_get_contents($s['qr_path'])) . '"></p>';
            }
            $html = '<p>Hola ' . htmlspecialchars($name) . ',</p>'
                  . '<p>Para la devolución de su RMA <strong>' . $idFmt . '</strong>, lleve el paquete al punto SEUR:</p>'
                  . '<p><strong>' . htmlspecialchars($s['pudo_name'] ?: $s['pudo_id']) . '</strong></p>'
                  . (!empty($s['pudo_hours'])
                      ? '<p style="font-size:13px;color:#555">Horario: ' . htmlspecialchars(str_ireplace(
                            array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'),
                            array('lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'),
                            $s['pudo_hours'])) . '</p>'
                      : '')
                  . '<p>Muestre este código QR en el punto (no necesita imprimir nada):</p>'
                  . $qrImg
                  . '<p>También adjuntamos la etiqueta por si prefiere llevarla impresa y pegada en el paquete.</p>'
                  . $instrucciones
                  . '<p>Gracias,<br>' . STORE_NAME . '</p>';
        } else {
            $html = '<p>Hola ' . htmlspecialchars($name) . ',</p>'
                  . '<p>Adjuntamos la etiqueta de SEUR para la devolución de su RMA <strong>' . $idFmt . '</strong>.</p>'
                  . '<p>Por favor, imprímala y péguela en el paquete. El mensajero de SEUR pasará a recogerlo'
                  . ($s['fecha_recogida'] ? ' el día <strong>' . date('d/m/Y', strtotime($s['fecha_recogida'])) . '</strong>' : '') . '.</p>'
                  . $instrucciones
                  . '<p>Gracias,<br>' . STORE_NAME . '</p>';
        }
        // tep_mail() = PHPMailer (ruta de entrega real + adjunto). NO usar la clase email antigua.
        $attach = array(
            'tmp_name' => $path,
            'name'     => 'etiqueta_seur_rma' . (int) $s['id_rma'] . '.pdf',
        );
        tep_mail($name, $mailTo, 'Devolución SEUR - RMA ' . $idFmt, $html, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, $attach);

        tep_db_perform('seur_shipments', array('emailed_at' => 'now()'), 'update', 'id = ' . $shipId);
        seurNote($s['id_rma'], 'SEUR: etiqueta/QR de la devolución ' . ($s['shipment_code'] ?: '') . ' enviada por email a ' . $mailTo . '.');
    }
    tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) $s['id_rma']));
}

/* ====================================================================== *
 *  Acción: anular                                                        *
 * ====================================================================== */

function rmaSeurCancel() {
    $shipId = (int) ($_POST['ship'] ?? 0);
    $q = tep_db_query('SELECT * FROM seur_shipments WHERE id = ' . $shipId);
    $s = tep_db_fetch_array($q);
    if (!$s || empty($s['shipment_code']) || !empty($s['cancelled_at'])) { tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) ($s['id_rma'] ?? 0))); }

    $seur = new seur($s['entorno'] ?: SEUR_ENV);
    $seur->setTimeout(20);   // intento rápido; si falla en DURO se ENCOLA y el cron reintenta
    $res = ($s['tipo'] === 'recogida')
        ? $seur->cancelCollection($s['shipment_code'])
        : $seur->cancelShipment($s['shipment_code']);
    $d = seur::payload($res);
    $desc = (is_array($d) && !empty($d[0]['description'])) ? $d[0]['description'] : seur::primerError($res);
    $tipoTxt = ($s['tipo'] === 'recogida') ? 'devolución' : 'envío';
    if (seur::cancelOk($res)) {
        // Éxito (o "ya anulado/no existe"): marcar anulado.
        tep_db_perform('seur_shipments', array('cancelled_at' => 'now()'), 'update', 'id = ' . $shipId);
        seurNote($s['id_rma'], 'SEUR: ' . $tipoTxt . ' ' . $s['shipment_code'] . ' anulado (' . $desc . ').');
    } else {
        // Error DURO: el envío SIGUE VIVO en SEUR → NO marcar anulado. Encolar para que el
        // cron de tracking reintente cada hora (antes se marcaba anulado a ciegas: bug).
        tep_db_perform('seur_shipments', array('cancel_requested_at' => 'now()'), 'update', 'id = ' . $shipId);
        seurNote($s['id_rma'], 'SEUR: la anulación de ' . $tipoTxt . ' ' . $s['shipment_code'] . ' NO se pudo completar ahora (' . $desc . '); el envío SIGUE ACTIVO. Se REINTENTARÁ automáticamente cada hora. Si urge, gestiónalo con SEUR.');
    }
    tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) $s['id_rma']));
}

/* ====================================================================== *
 *  Acción: cambiar entorno PRE/PRO                                       *
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
    $manana = seur::proximoDiaLaborable();
    ?>
    <div class="rows sp10 column a12" style="margin-top:8px;padding:10px;background:#fff3e9;border:1px solid #f3c79b;border-radius:4px">
        <div class="column a12" style="font-weight:bold;margin-bottom:6px;display:flex;align-items:center;justify-content:space-between">
            <span>
                <i class="fa fa-truck"></i> SEUR — Devolución
                <?php if ($env === 'pre'): ?>
                    <span style="color:#c0392b;font-size:11px">&nbsp;(ENTORNO PRUEBAS)</span>
                <?php else: ?>
                    <span style="color:#2e7d32;font-size:11px">&nbsp;(PRODUCCIÓN)</span>
                <?php endif; ?>
            </span>
            <form method="post" action="<?php echo tep_href_link('rma.php', 'action=seur-set-env'); ?>" style="margin:0"
                  onsubmit="return confirm(<?php echo $env === 'pre' ? "'Vas a ACTIVAR PRODUCCIÓN: las devoluciones serán REALES. ¿Continuar?'" : "'Volver a ENTORNO DE PRUEBAS (PRE). ¿Continuar?'"; ?>);">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <input type="hidden" name="env" value="<?php echo $env === 'pre' ? 'pro' : 'pre'; ?>">
                <button class="xbutton" style="font-size:10px;padding:1px 6px;font-weight:normal" type="submit">⇄ <?php echo $env === 'pre' ? 'Activar PRODUCCIÓN' : 'Volver a pruebas'; ?></button>
            </form>
        </div>

        <?php $cliPudoId = trim((string) ($rmaDetail['seur_pudo_id'] ?? '')); ?>
        <?php if (($rmaDetail['cex_metodo'] ?? '') === 'seurpunto'): ?>
            <div class="column a12" style="margin-bottom:8px;padding:6px 8px;background:#fff3cd;border:1px solid #ffe08a;border-radius:4px;font-size:12px">
                📨 <strong>El cliente solicitó:</strong> entregar en <strong>punto SEUR</strong>
                <?php if ($cliPudoId): ?>
                    — <strong><?php echo htmlspecialchars($rmaDetail['seur_pudo_name'] !== '' ? $rmaDetail['seur_pudo_name'] : $cliPudoId); ?></strong> (precargado abajo) — genera 🏤 "Devolución en punto SEUR (QR)".
                <?php else: ?>
                    (no quedó registrado el punto — elígelo abajo con el cliente y genera 🏤).
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($rows): ?>
            <div class="column a12" style="margin-bottom:8px">
                <?php foreach ($rows as $s): ?>
                    <div style="font-size:12px;padding:5px 0;border-bottom:1px solid #f3d9c0;<?php echo $s['cancelled_at'] ? 'opacity:.5' : ''; ?>">
                        <strong><?php echo $s['tipo'] === 'recogida' ? ($s['pudo_id'] ? '🏤 Devolución en punto' : '🏠 Recogida a domicilio') : 'Envío'; ?></strong>
                        <?php echo $s['ok'] ? '<span style="color:#2e7d32">✔</span>' : '<span style="color:#c0392b">✖ HTTP ' . htmlspecialchars($s['http_code']) . '</span>'; ?>
                        <?php if ($s['shipment_code']): ?> · <code><?php echo htmlspecialchars($s['shipment_code']); ?></code><?php endif; ?>
                        <?php if ($s['pudo_id']): ?> · punto <code><?php echo htmlspecialchars($s['pudo_id']); ?></code> <?php echo htmlspecialchars($s['pudo_name'] ?? ''); ?><?php endif; ?>
                        <?php if ($s['fecha_recogida']): ?> · recogida <?php echo date('d/m/Y', strtotime($s['fecha_recogida'])); ?><?php endif; ?>
                        <?php if ($s['cancelled_at']): ?> · <em>anulada</em><?php endif; ?>
                        <?php $trk = seurTrackEstado($s['ref']); if ($trk): ?> · <strong style="color:<?php echo $trk['entregado'] ? '#2e7d32' : '#0a6ebd'; ?>">📍 <?php echo htmlspecialchars($trk['estado_desc']); ?></strong><span style="color:#999;font-size:11px"> (<?php echo date('d/m H:i', strtotime($trk['last_checked'])); ?>)</span><?php endif; ?>
                        · <span style="color:#999;font-size:11px"><?php echo htmlspecialchars($s['entorno']); ?> · <?php echo htmlspecialchars($s['service_code'] . '/' . $s['product_code']); ?></span>
                        <br><span style="color:#777"><?php echo htmlspecialchars($s['date_added']); ?><?php if (!$s['ok'] && $s['mensaje_retorno']): ?> — <?php echo htmlspecialchars($s['mensaje_retorno']); ?><?php endif; ?></span>
                        <?php if ($s['ok']): ?>
                            <div style="margin-top:3px">
                                <?php if ($s['label_path']): ?>
                                    <a class="xbutton" style="font-size:11px;padding:2px 8px" href="<?php echo tep_href_link('rma.php', 'action=seur-label&ship=' . (int) $s['id']); ?>" target="_blank">⬇ Etiqueta</a>
                                    <form method="post" action="<?php echo tep_href_link('rma.php', 'action=seur-email-label'); ?>" style="display:inline">
                                        <input type="hidden" name="ship" value="<?php echo (int) $s['id']; ?>">
                                        <button class="xbutton verde" style="font-size:11px;padding:2px 8px" type="submit">✉ <?php echo $s['emailed_at'] ? 'Reenviar al cliente' : 'Enviar al cliente'; ?><?php echo $s['pudo_id'] ? ' (QR)' : ''; ?></button>
                                    </form>
                                    <?php if ($s['emailed_at']): ?><span style="color:#777;font-size:11px">enviada <?php echo htmlspecialchars($s['emailed_at']); ?></span><?php endif; ?>
                                <?php endif; ?>
                                <?php if (!$s['cancelled_at']): ?>
                                    <form method="post" action="<?php echo tep_href_link('rma.php', 'action=seur-cancel'); ?>" style="display:inline" onsubmit="return confirm('¿Anular <?php echo htmlspecialchars($s['shipment_code']); ?>?');">
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
                <label>Fecha recogida (domicilio): <input type="date" name="fecha" value="<?php echo $manana; ?>" min="<?php echo $manana; ?>" style="padding:3px;border:1px solid #aaa;border-radius:3px"></label>
            </div>
            <div style="margin-bottom:6px">
                <label>Punto SEUR (para devolución en punto):
                <select name="seur_pudo" style="max-width:420px;padding:3px;border:1px solid #aaa;border-radius:3px">
                    <option value="">-- elegir punto cercano al cliente --</option>
                    <?php $cliPudoListed = false; foreach (seurPuntosCercanos($rmaDetail['customers_postcode'], $rmaDetail['customers_city']) as $p): ?>
                        <option value="<?php echo htmlspecialchars($p['id']); ?>"<?php if ($cliPudoId !== '' && $p['id'] === $cliPudoId) { echo ' selected'; $cliPudoListed = true; } ?>><?php echo htmlspecialchars($p['name'] . ' - ' . $p['address'] . ' (' . $p['cp'] . ' ' . $p['city'] . ')'); ?></option>
                    <?php endforeach; ?>
                    <?php if ($cliPudoId !== '' && !$cliPudoListed): ?>
                        <option value="<?php echo htmlspecialchars($cliPudoId); ?>" selected><?php echo htmlspecialchars(($rmaDetail['seur_pudo_name'] !== '' ? $rmaDetail['seur_pudo_name'] : $cliPudoId) . ' (elegido por el cliente)'); ?></option>
                    <?php endif; ?>
                </select></label>
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
                <button class="xbutton verde" type="submit" name="mode" value="domicilio"
                        title="El mensajero recoge en la dirección del cliente (día laborable siguiente). Se envía la etiqueta al cliente para que la imprima.">🏠 Recogida a domicilio</button>
                <button class="xbutton" style="background:#8e44ad;color:#fff" type="submit" name="mode" value="punto"
                        title="El cliente lleva el paquete a un punto SEUR mostrando el QR (sin imprimir). Requiere elegir punto.">🏤 Devolución en punto SEUR (QR)</button>
            </div>
        </form>
    </div>
    <?php
}
