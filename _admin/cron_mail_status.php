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
// Tope de invitaciones Trustpilot por mes natural (plan Plus = 200, decision del usuario 2026-06-24 usar el maximo).
if (!defined('TRUSTPILOT_MONTHLY_CAP')) define('TRUSTPILOT_MONTHLY_CAP', 200);
// No reinvitar al mismo cliente (email) dentro de este periodo en dias. 0 = sin cooldown.
if (!defined('TRUSTPILOT_REINVITE_COOLDOWN_DAYS')) define('TRUSTPILOT_REINVITE_COOLDOWN_DAYS', 180);

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
                    }
                }
                // 2026-06-25: el nombre del destinatario debe ser el del CLIENTE (antes iba orders_status_name = "Enviado", que ensucia el "To" y puede confundir al AFS de Trustpilot que lee ese header).
                tep_mail($check_status['customers_firstname'], $check_status['customers_email_address'], EMAIL_TEXT_SUBJECT . ' (Nº de Pedido: ' . $oID . ')', $email, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, false, $tp_bcc);
            }
            $sql = 'UPDATE orders_status_history SET customer_notified = 1 WHERE orders_status_history_id = ' . $check_status['orders_status_history_id'];
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
