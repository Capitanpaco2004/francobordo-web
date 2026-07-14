<?php
$_SERVER['PHP_SELF'] = 'login.php';
$_SERVER['SCRIPT_FILENAME'] = 'login.php';
// Libreria oscommerce
include 'includes/application_top.php';
include DIR_WS_LANGUAGES . $language . '/orders_check.php';

// Trustpilot AFS: direccion BCC para invitar a opinar (plan Free, sin API). Vacio = desactivado.
if (!defined('TRUSTPILOT_AFS_BCC')) define('TRUSTPILOT_AFS_BCC', 'francobordo.com+7c408a2e65@invite.trustpilot.com');
// Solo se invita a pedidos ENVIADOS (estado 5) dentro de este margen desde la compra.
if (!defined('TRUSTPILOT_MAX_SHIP_SECONDS')) define('TRUSTPILOT_MAX_SHIP_SECONDS', 86400); // 24h
// Tope de invitaciones Trustpilot por mes natural. Trustpilot amplio el cupo a 300 y reseteo su contador el 2026-07-01;
// nuestro contador va por mes natural (rueda el dia 1), asi que queda alineado con el reset de Trustpilot.
if (!defined('TRUSTPILOT_MONTHLY_CAP')) define('TRUSTPILOT_MONTHLY_CAP', 300);
// No reinvitar al mismo cliente (email) dentro de este periodo en dias. 0 = sin cooldown.
if (!defined('TRUSTPILOT_REINVITE_COOLDOWN_DAYS')) define('TRUSTPILOT_REINVITE_COOLDOWN_DAYS', 180);

// Fallback Google (ficha Google Business Profile, Alcobendas): cuando se AGOTA el cupo mensual de
// Trustpilot, los clientes con envio <=24h que se habrian invitado se ENCOLAN (queued_at) y un bloque
// al final de este cron les envia un EMAIL DEDICADO ~1 dia despues de la ENTREGA (estado 3), cuando ya
// tienen el producto (2026-07-13; antes iba un boton en el email de envio, mal momento para pedir opinion).
// Plantilla: UHtmlEmails/<layout>/google_review.php. Maps no tiene tope y es gratis. Vacio = desactivado.
if (!defined('GOOGLE_REVIEW_URL')) define('GOOGLE_REVIEW_URL', 'https://search.google.com/local/writereview?placeid=ChIJNSqEzhAtQg0Rn7xA5nBG9uw');
// No volver a pedir Google al mismo cliente (email) dentro de este periodo en dias. 0 = sin cooldown.
if (!defined('GOOGLE_REINVITE_COOLDOWN_DAYS')) define('GOOGLE_REINVITE_COOLDOWN_DAYS', 180);

// --- Auto-chequeo anti-clobber del BCC de Trustpilot (2026-07-02) ---
// El fix del BCC en el admin includes/functions/general.php se ha revertido 2 veces al subir copias viejas.
// Si tep_mail pierde el 8o parametro ($bcc), Trustpilot deja de recibir invitaciones EN SILENCIO
// (PHP ignora el arg extra sin error). Este bloque lo detecta y avisa por email (throttle 6h).
try {
    $tp_ref = new ReflectionFunction('tep_mail');
    if ($tp_ref->getNumberOfParameters() < 8) {
        $tp_alert_file = '/home/francobordo/logs/tp_bcc_alert.last';
        $tp_last = (is_file($tp_alert_file) ? (int) @file_get_contents($tp_alert_file) : 0);
        if (time() - $tp_last > 21600) {
            @file_put_contents($tp_alert_file, (string) time());
            @tep_mail('Francisco', 'f.rodriguez@francobordo.com', '[' . STORE_NAME . '] ALERTA: BCC de Trustpilot roto',
                '<p>El admin <b>includes/functions/general.php</b> ha perdido el soporte de BCC en <code>tep_mail()</code> (firma con menos de 8 parametros). '
                . 'Trustpilot ha dejado de recibir invitaciones <b>en silencio</b>. Suele ocurrir al subir una copia vieja del fichero. '
                . 'Hay que re-aplicar el fix (parametro <code>$bcc</code> + <code>$mail-&gt;AddBCC($bcc)</code>). Ver memoria francobordo_trustpilot.</p>',
                STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
        }
        echo '<pre style="color:#c00">ALERTA: tep_mail SIN soporte BCC — Trustpilot no invitara hasta re-aplicar el fix.</pre>';
    }
} catch (Throwable $e) { /* el chequeo nunca debe romper el cron */ }

/**
 * Tracking real del pedido desde las tablas de la web (SEUR / Correos Express /
 * Correos), para rellenar el hueco "...número de seguimiento es:" del email de
 * ENVIADO. QFac manda ese tracking VACÍO en los envíos por API (el label lo crea
 * el watcher, no QFac). Devuelve un <a> clicable con el código, o '' si no hay
 * envío activo. 2026-07-08. Ver memoria francobordo_qfacwin_status / francobordo_seur_api.
 */
function resolverTrackingWeb($oid) {
    $oid = (int) $oid;
    if ($oid <= 0) return '';
    // (tabla, columna con el código de barras / localizador que ve el cliente)
    $fuentes = array(
        array('seur_shipments',    'ecb'),
        array('cex_shipments',     'ecb'),
        array('correos_shipments', 'package_code'),
    );
    foreach ($fuentes as $f) {
        list($tabla, $colCod) = $f;
        $res = tep_db_query("SELECT `" . $colCod . "` AS codigo, shipment_code, tracking_url FROM `" . $tabla . "` WHERE orders_id = " . $oid . " AND ok = 1 AND cancelled_at IS NULL AND tipo = 'envio' ORDER BY id DESC LIMIT 1");
        if ($res && ($r = tep_db_fetch_array($res))) {
            $codigo = trim((string) (($r['codigo'] !== '' && $r['codigo'] !== null) ? $r['codigo'] : $r['shipment_code']));
            $url    = trim((string) $r['tracking_url']);
            if ($codigo === '' && $url === '') continue;
            if ($url !== '') {
                return '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '" target="_blank" style="color:#00aff0;font-weight:bold;">' . htmlspecialchars($codigo !== '' ? $codigo : 'Localiza tu envío', ENT_QUOTES) . '</a>';
            }
            return htmlspecialchars($codigo, ENT_QUOTES);
        }
    }
    return '';
}

$orders_status_array = array();
$orders_status_query = tep_db_query("select orders_status_id, orders_status_name from " . TABLE_ORDERS_STATUS . " where language_id = '" . (int) $languages_id . "'");

while ($orders_status = tep_db_fetch_array($orders_status_query)) {
    $orders_statuses[] = array('id' => $orders_status['orders_status_id'], 'text' => $orders_status['orders_status_name']);
    $orders_status_array[$orders_status['orders_status_id']] = $orders_status['orders_status_name'];
}
echo '<h4>Notificaciones por email</h4>';
$aDatosStatuses = tep_db_query('SELECT c.customers_group_id, osh.orders_status_history_id, o.orders_id, os.orders_status_name, o.orders_status, osh.orders_status_id, osh.comments, osh.date_added, o.date_purchased, c.customers_firstname, c.customers_email_address
		FROM orders_status_history osh
		LEFT JOIN orders o ON o.orders_id = osh.orders_id
		LEFT JOIN customers c ON c.customers_id = o.customers_id
		LEFT JOIN orders_status os ON osh.orders_status_id = os.orders_status_id
		WHERE osh.customer_notified = 0 AND os.language_id = 3 AND os.public_flag = 1 AND osh.date_added >= ( CURDATE() - INTERVAL 3 DAY )
		ORDER BY osh.date_added DESC
		LIMIT 20 ');

if (tep_db_num_rows($aDatosStatuses) > 0) {
    $pedidos = array();
    while ($check_status = tep_db_fetch_array($aDatosStatuses)) {

        if (!is_array($pedidos[$check_status['orders_id']])) {
            //echo '<pre>'.print_r($check_status, 1).'</pre>';
            if ((int) $check_status['orders_status'] == 5 || (int) $check_status['orders_status'] == 13) {
                $pedidos[$check_status['orders_id']] = $check_status;
            }

        }

    }

    $n = 0;

    foreach ($pedidos as $oID => $check_status) {
        if ($check_status['comments'] != '') {
            $date_added = strtotime($check_status['date_added']);
            $date = date('d/m/Y H:i:s', $date_added);
            $comments = $check_status['comments'];
            // Rellena "número de seguimiento es:" con el tracking real de la web (QFac lo
            // manda vacío en envíos por API). Se reescribe el hueco y se persiste abajo
            // (para el email Y la ficha del cliente).
            $comments_orig = $comments;
            if (strpos($comments, 'seguimiento es:') !== false) {
                $track = resolverTrackingWeb($oID);
                if ($track !== '') {
                    $comments = preg_replace_callback(
                        '#(seguimiento es:\s*</p>\s*<p>)(.*?)(<br\s*/?>\s*</p>)#is',
                        function ($m) use ($track) { return $m[1] . $track . $m[3]; },
                        $comments, 1);
                }
            }
            $status = $check_status['orders_status'];

            /*echo '<p>E-mail: <strong>'.$check_status['customers_email_address'].'</strong>
            <br />Estado: <strong>'.$check_status['orders_status_name'].'</strong>
            <br />Fecha: <strong style="color: red;">'.$date.'</strong>
            <br />Pedido: <strong style="color: red;">'.$oID.'</strong>
            <br />Order status: <strong style="color: red;">'.$status.'</strong>
            <br />Order orders_status_history_id: <strong style="color: red;">'.$check_status['orders_status_history_id'].'</strong>
            </p>';*/
            //echo '<div>'.$check_status['comments'].'</div>';
            echo '<hr />';
            if ($check_status['customers_group_id'] != 2) {
                $cron_status = true;
                $notify_comments = sprintf(EMAIL_TEXT_COMMENTS_UPDATE, $comments) . "\n\n";

                require DIR_FS_CATALOG_MODULES . 'UHtmlEmails/' . ULTIMATE_HTML_EMAIL_LAYOUT . '/orders.php';
                $email = $html_email;

                // Trustpilot: BCC (invitacion a opinar) SOLO si este cambio es "Enviado" (estado 5),
                // el pedido se envio en <= TRUSTPILOT_MAX_SHIP_SECONDS desde la compra, queda cupo mensual
                // (plan Free 50) y el cliente no fue invitado dentro del cooldown. Se registra en trustpilot_invites
                // (registro propio: trazabilidad + tope + dedup por cliente). El cliente no ve nada distinto.
                $tp_bcc = '';
                if ((int) $check_status['orders_status_id'] === 5
                    && defined('TRUSTPILOT_AFS_BCC') && TRUSTPILOT_AFS_BCC !== ''
                    && !empty($check_status['date_purchased'])
                    && (strtotime($check_status['date_added']) - strtotime($check_status['date_purchased'])) <= TRUSTPILOT_MAX_SHIP_SECONDS) {

                    $tp_email = trim((string) $check_status['customers_email_address']);
                    // Cupo usado este mes natural (de nuestro registro)
                    $tp_cnt   = tep_db_fetch_array(tep_db_query("SELECT COUNT(*) AS c FROM trustpilot_invites WHERE sent_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"));
                    $tp_usados = (int) $tp_cnt['c'];
                    // ¿Cliente ya invitado dentro del cooldown?
                    $tp_dup = ((int) TRUSTPILOT_REINVITE_COOLDOWN_DAYS > 0 && $tp_email !== '')
                        ? tep_db_num_rows(tep_db_query("SELECT 1 FROM trustpilot_invites WHERE customers_email_address = '" . tep_db_input($tp_email) . "' AND sent_at >= ( NOW() - INTERVAL " . (int) TRUSTPILOT_REINVITE_COOLDOWN_DAYS . " DAY ) LIMIT 1"))
                        : 0;

                    if ($tp_email !== '' && $tp_usados < (int) TRUSTPILOT_MONTHLY_CAP && $tp_dup == 0) {
                        $tp_bcc = TRUSTPILOT_AFS_BCC;
                        tep_db_query("INSERT IGNORE INTO trustpilot_invites (orders_id, customers_email_address, sent_at) VALUES ('" . (int) $oID . "', '" . tep_db_input($tp_email) . "', NOW())");
                        echo '<pre>Trustpilot: invitado pedido ' . (int) $oID . ' (' . ($tp_usados + 1) . '/' . (int) TRUSTPILOT_MONTHLY_CAP . ' este mes)</pre>';
                    } else {
                        echo '<pre>Trustpilot: NO invitado pedido ' . (int) $oID . ' (' . ($tp_dup ? 'cliente ya invitado en cooldown' : ('tope mensual ' . $tp_usados . '/' . (int) TRUSTPILOT_MONTHLY_CAP)) . ')</pre>';

                        // Fallback Google: solo si NO se invito por haberse AGOTADO el cupo (no por cooldown),
                        // el email es valido y el cliente no fue invitado recientemente a Trustpilot. Se ENCOLA
                        // (queued_at, sent_at NULL) y el bloque "Invitaciones Google post-entrega" del final de
                        // este cron le enviara un email dedicado ~1 dia despues de la ENTREGA (estado 3).
                        if ($tp_email !== '' && $tp_dup == 0 && $tp_usados >= (int) TRUSTPILOT_MONTHLY_CAP
                            && defined('GOOGLE_REVIEW_URL') && GOOGLE_REVIEW_URL !== '') {

                            // Cooldown propio de Google (cuenta tambien las aun en cola): no repetir cliente.
                            $g_dup = ((int) GOOGLE_REINVITE_COOLDOWN_DAYS > 0)
                                ? tep_db_num_rows(tep_db_query("SELECT 1 FROM google_review_invites WHERE customers_email_address = '" . tep_db_input($tp_email) . "' AND COALESCE(sent_at, queued_at) >= ( NOW() - INTERVAL " . (int) GOOGLE_REINVITE_COOLDOWN_DAYS . " DAY ) LIMIT 1"))
                                : 0;

                            if ($g_dup == 0) {
                                tep_db_query("INSERT IGNORE INTO google_review_invites (orders_id, customers_email_address, queued_at) VALUES ('" . (int) $oID . "', '" . tep_db_input($tp_email) . "', NOW())");
                                echo '<pre>Google: encolado pedido ' . (int) $oID . ' (email dedicado ~1 dia tras la entrega)</pre>';
                            } else {
                                echo '<pre>Google: NO encolado pedido ' . (int) $oID . ' (cliente ya invitado a Google en cooldown)</pre>';
                            }
                        }
                    }
                }
                // Quita el marcador <!--GOOGLE_CTA--> de la plantilla (desde 2026-07-13 ya no se inyecta nada
                // en el email de envio; la invitacion de Google va en email dedicado post-entrega).
                if (strpos($email, '<!--GOOGLE_CTA-->') !== false) { $email = str_replace('<!--GOOGLE_CTA-->', '', $email); }
                // 2026-06-25: el nombre del destinatario debe ser el del CLIENTE (antes iba orders_status_name = "Enviado", que ensucia el "To" y puede confundir al AFS de Trustpilot que lee ese header).
                tep_mail($check_status['customers_firstname'], $check_status['customers_email_address'], EMAIL_TEXT_SUBJECT . ' (Nº de Pedido: ' . $oID . ')', $email, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, false, $tp_bcc);
            }
            // Persiste el comentario enriquecido con el tracking (si cambió) para que la
            // ficha del cliente y el admin muestren también el número de seguimiento.
            if (isset($comments_orig) && $comments !== $comments_orig) {
                $sql = "UPDATE orders_status_history SET customer_notified = 1, comments = '" . tep_db_input($comments) . "' WHERE orders_status_history_id = " . (int) $check_status['orders_status_history_id'];
            } else {
                $sql = 'UPDATE orders_status_history SET customer_notified = 1 WHERE orders_status_history_id = ' . (int) $check_status['orders_status_history_id'];
            }
            tep_db_query($sql);
            echo '<pre>' . $sql . '</pre>';

            ++$n;
        }
    }
}

/*
Completar pedidos
 */

echo '<h4>Cambios de estado de pedidos enviados hace más de 5 dias</h4>';
$aDatosStatuses = tep_db_query('SELECT os.orders_status_id, c.customers_group_id, osh.orders_status_history_id, o.orders_id, os.orders_status_name, o.orders_status, osh.orders_status_id, osh.comments, osh.date_added, c.customers_firstname, c.customers_email_address
 			FROM orders_status_history osh
 			LEFT JOIN orders o ON o.orders_id = osh.orders_id
 			LEFT JOIN customers c ON c.customers_id = o.customers_id
 			LEFT JOIN orders_status os ON osh.orders_status_id = os.orders_status_id
 			WHERE osh.customer_notified = 0 AND os.language_id = 3 AND os.public_flag = 1 AND osh.date_added < ( CURDATE() - INTERVAL 5 DAY ) AND os.orders_status_id = 5 AND o.orders_status = 5
 			ORDER BY osh.date_added DESC');
$stado_entregado = 3;

if (tep_db_num_rows($aDatosStatuses) > 0) {
    while ($check_status = tep_db_fetch_array($aDatosStatuses)) {
        $sql = "update " . TABLE_ORDERS . " set orders_status = '" . $stado_entregado . "', last_modified = now() where orders_id = '" . $check_status['orders_id'] . "'";
        echo '<pre>' . $sql . '</pre>';
        tep_db_query($sql);
        $sql = "insert into " . TABLE_ORDERS_STATUS_HISTORY . " (orders_id, orders_status_id, date_added, customer_notified) values ('" . $check_status['orders_id'] . "', '" . $stado_entregado . "', now(), 1)";
        echo '<pre>' . $sql . '</pre>';
        tep_db_query($sql);
    }

}

/*
 Invitaciones Google post-entrega (2026-07-13)
 Envia el email dedicado "Valoranos en Google" a los encolados (google_review_invites.sent_at NULL)
 cuyo pedido lleva >= 1 dia en estado ENTREGADO (3, por tracking o por el autocompletado de arriba).
 Respeta las bajas del canal de opiniones (opinion_optout). Plantilla: UHtmlEmails/<layout>/google_review.php.
 */
echo '<h4>Invitaciones Google (post-entrega)</h4>';
if (defined('GOOGLE_REVIEW_URL') && GOOGLE_REVIEW_URL !== '') {
    $g_optout = array();
    $g_oq = tep_db_query('SELECT email FROM opinion_optout');
    while ($g_or = tep_db_fetch_array($g_oq)) { $g_optout[] = strtolower(trim((string) $g_or['email'])); }

    $g_pend = tep_db_query("SELECT gri.id, gri.orders_id, gri.customers_email_address, c.customers_firstname,
            (SELECT MAX(osh.date_added) FROM orders_status_history osh WHERE osh.orders_id = gri.orders_id AND osh.orders_status_id = 3) AS entregado
        FROM google_review_invites gri
        JOIN orders o ON o.orders_id = gri.orders_id AND o.orders_status = 3
        LEFT JOIN customers c ON c.customers_id = o.customers_id
        WHERE gri.sent_at IS NULL
        HAVING entregado IS NOT NULL AND entregado <= ( NOW() - INTERVAL 1 DAY )
        ORDER BY entregado
        LIMIT 40");
    while ($g_fila = tep_db_fetch_array($g_pend)) {
        $g_id    = (int) $g_fila['id'];
        $g_oid   = (int) $g_fila['orders_id'];
        $g_email = trim((string) $g_fila['customers_email_address']);
        $g_name  = trim((string) ($g_fila['customers_firstname'] ?? ''));
        if ($g_name === '') { $g_name = 'cliente'; }

        if ($g_email === '' || !filter_var($g_email, FILTER_VALIDATE_EMAIL) || in_array(strtolower($g_email), $g_optout)) {
            // Se marca como tratada para no reintentarla en cada pasada.
            tep_db_query("UPDATE google_review_invites SET sent_at = NOW() WHERE id = " . $g_id);
            echo '<pre>Google: descartado pedido ' . $g_oid . ' (baja del canal de opiniones o email invalido)</pre>';
            continue;
        }

        // Enlace de baja del canal de opiniones (mismo mecanismo que cron_opiniones), si hay fila en opinion.
        $g_baja = '';
        $g_uq = tep_db_query("SELECT uniqid FROM opinion WHERE orders_id = '" . $g_oid . "' LIMIT 1");
        if (tep_db_num_rows($g_uq)) {
            $g_u = tep_db_fetch_array($g_uq);
            $g_baja = 'https://www.francobordo.com/baja_opiniones.php?o=' . $g_u['uniqid'];
        }

        require DIR_FS_CATALOG_MODULES . 'UHtmlEmails/' . ULTIMATE_HTML_EMAIL_LAYOUT . '/google_review.php'; // define $g_html_email

        // Asunto en bytes UTF-8 explicitos (PHPMailer CharSet=utf-8; a prueba del charset del fichero).
        $g_subject = "Tu opini\xC3\xB3n nos hace mejores \xE2\xAD\x90 Val\xC3\xB3ranos en Google";
        tep_mail($g_name, $g_email, $g_subject, $g_html_email, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, false, '');
        tep_db_query("UPDATE google_review_invites SET sent_at = NOW() WHERE id = " . $g_id);
        echo '<pre>Google: enviado email de resena del pedido ' . $g_oid . ' (entregado ' . $g_fila['entregado'] . ')</pre>';
    }
}
