<?php
$_SERVER['PHP_SELF'] = 'login.php';
$_SERVER['SCRIPT_FILENAME'] = 'login.php';
// Libreria oscommerce
include 'includes/application_top.php';
include DIR_WS_LANGUAGES . $language . '/orders_check.php';

$orders_status_array = array();
$orders_status_query = tep_db_query("select orders_status_id, orders_status_name from " . TABLE_ORDERS_STATUS . " where language_id = '" . (int) $languages_id . "'");

while ($orders_status = tep_db_fetch_array($orders_status_query)) {
    $orders_statuses[] = array('id' => $orders_status['orders_status_id'], 'text' => $orders_status['orders_status_name']);
    $orders_status_array[$orders_status['orders_status_id']] = $orders_status['orders_status_name'];
}
echo '<h4>Notificaciones por email</h4>';
$aDatosStatuses = tep_db_query('SELECT c.customers_group_id, osh.orders_status_history_id, o.orders_id, os.orders_status_name, o.orders_status, osh.orders_status_id, osh.comments, osh.date_added, c.customers_firstname, c.customers_email_address
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
                tep_mail($check_status['orders_status_name'], $check_status['customers_email_address'], EMAIL_TEXT_SUBJECT . ' (Nº de Pedido: ' . $oID . ')', $email, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
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
