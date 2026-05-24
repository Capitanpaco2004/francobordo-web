<?php
/*
 * Points & Rewards V2.1rc2a — Ben Zukrel (Deep Silver Accessories) 2008, osCommerce, GPL.
 *
 * Refactor 2026-05-15:
 * - Acciones (`confirm_all_points`, `confirm_points`, `cancel_points`, `adjustpoints`, `pe_rollback`, `delete_points`)
 *   reescritas para usar tep_send_points_notification() (helper en includes/functions/redemptions.php).
 *   Elimina ~600 líneas de HTML email duplicado entre acciones.
 * - Validaciones POST con (int)/cast y null-coalescing.
 * - `tep_session_register('viewedSort'/'page')` eliminados (PHP 8.x).
 * - Inicializadas $sort, $set_comment, $customer_balance, $points_expire_date.
 */

  require('includes/application_top.php');
  require_once($_SERVER['DOCUMENT_ROOT'] . '/' . DIR_WS_FUNCTIONS . 'redemptions.php');

  require(DIR_WS_CLASSES . 'currencies.php');
  $currencies = new currencies();

  $action = $_GET['action'] ?? '';

  if (tep_not_null($action)) {
    switch ($action) {

      case 'confirm_all_points': {
        // Confirma en masa todos los points_status=1 cuyo pedido esté entregado (5) o completado (3)
        $aPendings = tep_db_query(
            "SELECT pp.unique_id, pp.customer_id, pp.orders_id, pp.points_pending, "
          . "       o.date_purchased, os.orders_status_name, "
          . "       c.customers_gender, c.customers_lastname, c.customers_firstname, "
          . "       c.customers_email_address, c.customers_shopping_points, c.customers_points_expires "
          . " FROM " . TABLE_CUSTOMERS_POINTS_PENDING . " pp "
          . " INNER JOIN " . TABLE_ORDERS . " o ON pp.orders_id = o.orders_id "
          . " INNER JOIN " . TABLE_ORDERS_STATUS . " os ON o.orders_status = os.orders_status_id AND os.language_id = 3 "
          . " INNER JOIN " . TABLE_CUSTOMERS . " c ON pp.customer_id = c.customers_id "
          . " WHERE pp.points_status = 1 AND pp.points_pending > 0 "
          . "   AND o.orders_status IN (3, 5)"
        );

        while ($row = tep_db_fetch_array($aPendings)) {
            $customer_id    = (int) $row['customer_id'];
            $uID            = (int) $row['unique_id'];
            // Convención francobordo 2026-05-15: puntos sin decimales
            $points_pending = (int) round((float) $row['points_pending']);

            tep_db_query('START TRANSACTION');
            try {
                if (tep_not_null(POINTS_AUTO_EXPIRES)) {
                    $expire = date('Y-m-d', strtotime('+' . (int) POINTS_AUTO_EXPIRES . ' month'));
                    tep_db_query("update " . TABLE_CUSTOMERS . " set customers_shopping_points = customers_shopping_points + '" . $points_pending . "', customers_points_expires = '" . $expire . "' where customers_id = '" . $customer_id . "' limit 1");
                } else {
                    tep_db_query("update " . TABLE_CUSTOMERS . " set customers_shopping_points = customers_shopping_points + '" . $points_pending . "' where customers_id = '" . $customer_id . "' limit 1");
                }
                tep_db_query("update " . TABLE_CUSTOMERS_POINTS_PENDING . " set points_status = 2 where unique_id = '" . $uID . "' limit 1");
                tep_db_query('COMMIT');
            } catch (\Throwable $e) {
                tep_db_query('ROLLBACK');
                throw $e;
            }

            // Recalcular saldo nuevo + email
            $new_balance = ((float) $row['customers_shopping_points']) + $points_pending;
            $expire_msg  = tep_not_null(POINTS_AUTO_EXPIRES)
                ? sprintf(EMAIL_TEXT_EXPIRE, tep_date_short(isset($expire) ? $expire : $row['customers_points_expires']))
                : null;

            tep_send_points_notification([
                'customer_id'         => $customer_id,
                'customers_email'     => $row['customers_email_address'],
                'customers_gender'    => $row['customers_gender'],
                'customers_firstname' => $row['customers_firstname'],
                'customers_lastname'  => $row['customers_lastname'],
                'points_delta'        => $points_pending,
                'new_balance'         => $new_balance,
                'subject'             => EMAIL_TEXT_SUBJECT,
                'expire_msg'          => $expire_msg,
                'extra'               => [
                    'order_id'   => (int) $row['orders_id'],
                    'date_added' => $row['date_purchased'],
                ],
            ]);
        }

        $messageStack->add_session(SUCCESS_POINTS_UPDATED, 'success');
        tep_redirect(tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params(['uID', 'action'])));
        break;
      }

      case 'confirm_points': {
        $uID                     = (int) ($_GET['uID']  ?? 0);
        $customer_id             = (int) ($_POST['customers_id']        ?? 0);
        $order_id                = (int) ($_POST['orders_id']           ?? 0);
        $date_added              = $_POST['date_purchased']             ?? '';
        $points_pending          = (int) round((float) ($_POST['points_pending'] ?? 0));  // sin decimales
        $order_status            = $_POST['orders_status_name']         ?? '';
        $customers_email_address = tep_db_prepare_input($_POST['customers_email_address'] ?? '');

        if ($uID <= 0 || $customer_id <= 0) {
            tep_redirect(tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING));
        }

        tep_db_query('START TRANSACTION');
        try {
            if (tep_not_null(POINTS_AUTO_EXPIRES)) {
                $expire = date('Y-m-d', strtotime('+' . (int) POINTS_AUTO_EXPIRES . ' month'));
                tep_db_query("update " . TABLE_CUSTOMERS . " set customers_shopping_points = customers_shopping_points + '" . $points_pending . "', customers_points_expires = '" . $expire . "' where customers_id = '" . $customer_id . "' limit 1");
            } else {
                tep_db_query("update " . TABLE_CUSTOMERS . " set customers_shopping_points = customers_shopping_points + '" . $points_pending . "' where customers_id = '" . $customer_id . "' limit 1");
            }

            if (isset($_POST['queue_confirm'])) {
                tep_db_query("update " . TABLE_CUSTOMERS_POINTS_PENDING . " set points_status = 2 where unique_id = '" . $uID . "' limit 1");
            } else {
                tep_db_query("delete from " . TABLE_CUSTOMERS_POINTS_PENDING . " where unique_id = '" . $uID . "' limit 1");
                $messageStack->add_session(NOTICE_RECORED_REMOVED, 'warning');
            }
            tep_db_query('COMMIT');
        } catch (\Throwable $e) {
            tep_db_query('ROLLBACK');
            throw $e;
        }

        if (isset($_POST['notify_confirm']) && $_POST['notify_confirm'] == 'on') {
            $customer_query = tep_db_query("select customers_gender, customers_lastname, customers_firstname, customers_shopping_points, customers_points_expires from " . TABLE_CUSTOMERS . " where customers_id = '" . $customer_id . "' limit 1");
            $customer       = tep_db_fetch_array($customer_query);
            $expire_msg     = tep_not_null(POINTS_AUTO_EXPIRES)
                ? sprintf(EMAIL_TEXT_EXPIRE, tep_date_short($customer['customers_points_expires']))
                : null;

            tep_send_points_notification([
                'customer_id'         => $customer_id,
                'customers_email'     => $customers_email_address,
                'customers_gender'    => $customer['customers_gender'],
                'customers_firstname' => $customer['customers_firstname'],
                'customers_lastname'  => $customer['customers_lastname'],
                'points_delta'        => $points_pending,
                'new_balance'         => (float) $customer['customers_shopping_points'],
                'subject'             => EMAIL_TEXT_SUBJECT,
                'expire_msg'          => $expire_msg,
                'extra'               => ['order_id' => $order_id, 'date_added' => $date_added],
            ]);

            $name = trim($customer['customers_firstname'] . ' ' . $customer['customers_lastname']);
            $messageStack->add_session(sprintf(NOTICE_EMAIL_SENT_TO, $name . '(' . $customers_email_address . ').'), 'success');
        }

        $messageStack->add_session(SUCCESS_POINTS_UPDATED, 'success');
        tep_redirect(tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params(['uID', 'action'])));
        break;
      }

      case 'cancel_points': {
        $uID                     = (int) ($_GET['uID']  ?? 0);
        $comment_cancel          = tep_db_prepare_input($_POST['comment_cancel'] ?? '');
        $order_id                = (int) ($_POST['orders_id']        ?? 0);
        $customer_id             = (int) ($_POST['customers_id']     ?? 0);
        $date_added              = $_POST['date_purchased']          ?? '';
        $points_pending          = (int) round((float) ($_POST['points_pending'] ?? 0));  // sin decimales
        $customers_email_address = tep_db_prepare_input($_POST['customers_email_address'] ?? '');

        if (isset($_POST['notify_cancel']) && $_POST['notify_cancel'] == 'on' && $customer_id > 0) {
            $customer_query = tep_db_query("select customers_gender, customers_lastname, customers_firstname, customers_shopping_points, customers_points_expires from " . TABLE_CUSTOMERS . " where customers_id = '" . $customer_id . "'");
            $customer       = tep_db_fetch_array($customer_query);
            $expire_msg     = ($customer['customers_shopping_points'] > 0 && tep_not_null(POINTS_AUTO_EXPIRES))
                ? sprintf(EMAIL_TEXT_EXPIRE, tep_date_short($customer['customers_points_expires']))
                : null;

            tep_send_points_notification([
                'customer_id'         => $customer_id,
                'customers_email'     => $customers_email_address,
                'customers_gender'    => $customer['customers_gender'],
                'customers_firstname' => $customer['customers_firstname'],
                'customers_lastname'  => $customer['customers_lastname'],
                'points_delta'        => -$points_pending,
                'new_balance'         => (float) $customer['customers_shopping_points'],
                'subject'             => EMAIL_TEXT_SUBJECT,
                'comment'             => tep_not_null($comment_cancel) ? $comment_cancel : null,
                'expire_msg'          => $expire_msg,
                'extra'               => ['order_id' => $order_id, 'date_added' => $date_added],
            ]);

            $name = trim($customer['customers_firstname'] . ' ' . $customer['customers_lastname']);
            $messageStack->add_session(sprintf(NOTICE_EMAIL_SENT_TO, $name . '(' . $customers_email_address . ').'), 'success');
        }

        if (isset($_POST['queue_cancel'])) {
            $set_comment = tep_not_null($comment_cancel) ? ", points_comment = '" . $comment_cancel . "'" : '';
            tep_db_query("update " . TABLE_CUSTOMERS_POINTS_PENDING . " set points_status = 3 " . $set_comment . " where unique_id = '" . $uID . "' limit 1");
            $messageStack->add_session(SUCCESS_DATABASE_UPDATED, 'success');
        } else {
            tep_db_query("delete from " . TABLE_CUSTOMERS_POINTS_PENDING . " where unique_id = '" . $uID . "' limit 1");
        }

        tep_redirect(tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING));
        break;
      }

      case 'adjustpoints': {
        $uID    = (int) ($_GET['uID'] ?? 0);
        $adjust = $_POST['points_to_aj'] ?? '';

        if (tep_not_null($adjust) && is_numeric($adjust) && $uID > 0) {
            // Convención francobordo 2026-05-15: puntos sin decimales
            $adjust_int = (int) round($adjust);
            tep_db_query("update " . TABLE_CUSTOMERS_POINTS_PENDING . " set points_pending = '" . $adjust_int . "' where unique_id = '" . $uID . "' limit 1");
        } else {
            $messageStack->add_session(WARNING_DATABASE_NOT_UPDATED, 'warning');
        }
        tep_redirect(tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params(['cID', 'action'])));
        break;
      }

      case 'pe_rollback': {
        $uID                     = (int) ($_GET['uID']  ?? 0);
        $comment_roll            = tep_db_prepare_input($_POST['comment_roll'] ?? '');
        $order_id                = (int) ($_POST['orders_id']        ?? 0);
        $customer_id             = (int) ($_POST['customers_id']     ?? 0);
        $date_added              = $_POST['date_purchased']          ?? '';
        $points_pending          = (int) round((float) ($_POST['points_pending'] ?? 0));  // sin decimales
        $customers_email_address = tep_db_prepare_input($_POST['customers_email_address'] ?? '');

        tep_db_query('START TRANSACTION');
        try {
            tep_db_query("update " . TABLE_CUSTOMERS . " set customers_shopping_points = customers_shopping_points - '" . $points_pending . "' where customers_id = '" . $customer_id . "' limit 1");
            $set_comment = tep_not_null($comment_roll) ? ", points_comment = '" . $comment_roll . "'" : '';
            tep_db_query("update " . TABLE_CUSTOMERS_POINTS_PENDING . " set points_status = 1 " . $set_comment . " where unique_id = '" . $uID . "' limit 1");
            tep_db_query('COMMIT');
        } catch (\Throwable $e) {
            tep_db_query('ROLLBACK');
            throw $e;
        }

        if (isset($_POST['notify_roll']) && $_POST['notify_roll'] == 'on' && $customer_id > 0) {
            $customer_query = tep_db_query("select customers_gender, customers_lastname, customers_firstname, customers_shopping_points, customers_points_expires from " . TABLE_CUSTOMERS . " where customers_id = '" . $customer_id . "' limit 1");
            $customer       = tep_db_fetch_array($customer_query);
            $expire_msg     = ($customer['customers_shopping_points'] > 0 && tep_not_null(POINTS_AUTO_EXPIRES))
                ? sprintf(EMAIL_TEXT_EXPIRE, tep_date_short($customer['customers_points_expires']))
                : null;

            tep_send_points_notification([
                'customer_id'         => $customer_id,
                'customers_email'     => $customers_email_address,
                'customers_gender'    => $customer['customers_gender'],
                'customers_firstname' => $customer['customers_firstname'],
                'customers_lastname'  => $customer['customers_lastname'],
                'points_delta'        => $points_pending,
                'new_balance'         => (float) $customer['customers_shopping_points'],
                'subject'             => EMAIL_TEXT_SUBJECT,
                'comment'             => tep_not_null($comment_roll) ? $comment_roll : null,
                'expire_msg'          => $expire_msg,
                'extra'               => ['order_id' => $order_id, 'date_added' => $date_added],
            ]);

            $name = trim($customer['customers_firstname'] . ' ' . $customer['customers_lastname']);
            $messageStack->add_session(sprintf(NOTICE_EMAIL_SENT_TO, $name . '(' . $customers_email_address . ').'), 'success');
        }

        $messageStack->add_session(SUCCESS_POINTS_UPDATED, 'success');
        tep_redirect(tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING));
        break;
      }

      case 'delete_points': {
        $uID = (int) ($_GET['uID'] ?? 0);
        tep_db_query("delete from " . TABLE_CUSTOMERS_POINTS_PENDING . " where unique_id = '" . $uID . "' limit 1");
        $messageStack->add_session(NOTICE_RECORED_REMOVED, 'warning');
        tep_redirect(tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params(['uID', 'action'])));
        break;
      }
    }
  }

  include(DIR_WS_CLASSES . 'order.php');
?>

<?php require(THEME . 'html/header.php'); ?>

<script src="includes/general.js"></script>
<script><!--
function validate(field) {
  var valid = "0123456789.";
  var ok = "yes";
  for (var i = 0; i < field.value.length; i++) {
    var temp = "" + field.value.substring(i, i+1);
    if (valid.indexOf(temp) == "-1") ok = "no";
  }
  if (ok == "no") {
    alert("<?php echo POINTS_ENTER_JS_ERROR; ?>");
    field.focus();
    field.value = "";
  }
}
//--></script>
</head>

<table border="0" width="100%" cellspacing="2" cellpadding="2">
  <tr>
<!-- body_text //-->
    <td width="100%" valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
<?php
  $orders_statuses = array();
  $orders_status_array = array();
  $orders_status_query = tep_db_query("select orders_status_id, orders_status_name from " . TABLE_ORDERS_STATUS . " where language_id = '" . (int)$languages_id . "'");
  while ($orders_status = tep_db_fetch_array($orders_status_query)) {
    $orders_statuses[] = array('id' => $orders_status['orders_status_id'],
                               'text' => $orders_status['orders_status_name']);
    $orders_status_array[$orders_status['orders_status_id']] = $orders_status['orders_status_name'];
  }

// drop-down filter array
  $filter_array = array( array('id' => '1', 'text' => TEXT_POINTS_PENDING),
                         array('id' => '2', 'text' => TEXT_POINTS_CONFIRMED),
                         array('id' => '3', 'text' => TEXT_POINTS_CANCELLED),
                         array('id' => '4', 'text' => TEXT_POINTS_REDEEMED),
                         array('id' => '5', 'text' => TEXT_SHOW_ALL));

     $point_or_points = ((POINTS_PER_AMOUNT_PURCHASE > 1) ? HEADING_POINTS : HEADING_POINT);
?>
      <tr>
        <td width="100%"><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo HEADING_TITLE . '<br /><span class="smallText">' . HEADING_RATE . '&nbsp;&nbsp;&nbsp;' .  HEADING_AWARDS . $currencies->format(1) . ' = ' . number_format(POINTS_PER_AMOUNT_PURCHASE,POINTS_DECIMAL_PLACES) .'&nbsp;' . $point_or_points . '&nbsp;&nbsp;&nbsp;' . HEADING_REDEEM  .  number_format(POINTS_PER_AMOUNT_PURCHASE,POINTS_DECIMAL_PLACES) . '&nbsp;' . $point_or_points . ' = ' . $currencies->format(POINTS_PER_AMOUNT_PURCHASE * REDEEM_POINT_VALUE); ?></td>
            <td align="right"><table border="0" width="100%" cellspacing="0" cellpadding="0">
              <tr><?php echo tep_draw_form('orders', FILENAME_CUSTOMERS_POINTS_PENDING, '', 'get'); ?>
                <td class="smallText" align="right"><?php echo HEADING_TITLE_SEARCH . ' ' . tep_draw_input_field('search', '', 'size="12"'); ?></td>
              </form></tr>
              <tr><?php echo tep_draw_form('status', FILENAME_CUSTOMERS_POINTS_PENDING, '', 'get'); ?>
                <td class="smallText" align="right"><?php echo TABLE_HEADING_ORDERS_STATUS . ': ' .tep_draw_pull_down_menu('status', array_merge(array(array('id' => '', 'text' => TEXT_SHOW_ALL)), $orders_statuses)). '&nbsp;'. TABLE_HEADING_POINTS_STATUS . ':'. tep_draw_pull_down_menu('filter', $filter_array, '', 'onChange="this.form.submit();"'); ?></td>
              </form></tr>
			  <tr><td><a href="<?php echo tep_href_link( FILENAME_CUSTOMERS_POINTS_PENDING, 'action=confirm_all_points' ); ?>" class="buttonS bGreen mb10 mt5" style="float:right; margin-bottom: 10px;" onclick="if( ! confirm( '¿Deseas confirmar todos los puntos pendientes?' ) ) return false;">Confirmar todos los puntos pendientes</a></td></tr>
            </table></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr class="dataTableHeadingRow">
                <td class="dataTableHeadingContent"><?php echo '<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params() . 'viewedSort=c_name-asc') . '" title="' . TABLE_HEADING_SORT . TABLE_HEADING_CUSTOMERS . TABLE_HEADING_SORT_UA . '">+</a>&nbsp;' . TABLE_HEADING_CUSTOMERS . '&nbsp;<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params() . 'viewedSort=c_name-desc') . '" title="' . TABLE_HEADING_SORT . TABLE_HEADING_CUSTOMERS . TABLE_HEADING_SORT_DA; ?>">-</a></td>
                <td class="dataTableHeadingContent" align="right"><?php echo '<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params() . 'viewedSort=ot-asc') . '" title="' . TABLE_HEADING_SORT . TABLE_HEADING_ORDER_TOTAL . TABLE_HEADING_SORT_U1 . '">+</a>&nbsp;' . TABLE_HEADING_ORDER_TOTAL . '&nbsp;<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params() . 'viewedSort=ot-desc') . '" title="' . TABLE_HEADING_SORT . TABLE_HEADING_ORDER_TOTAL . TABLE_HEADING_SORT_D1; ?>">-</a></td>
                <td class="dataTableHeadingContent" align="right"><?php echo '<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params() . 'viewedSort=points-asc') . '" title="' . TABLE_HEADING_SORT . TABLE_HEADING_POINTS . TABLE_HEADING_SORT_U1 . '">+</a>&nbsp;' . TABLE_HEADING_POINTS . '&nbsp;<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params() . 'viewedSort=points-desc') . '" title="' . TABLE_HEADING_SORT . TABLE_HEADING_POINTS . TABLE_HEADING_SORT_D1; ?>">-</a></td>
                <td class="dataTableHeadingContent" align="right">&nbsp;&nbsp;&nbsp;<?php echo '<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params() . 'viewedSort=points-asc') . '" title="' . TABLE_HEADING_SORT . TABLE_HEADING_POINTS_VALUE . TABLE_HEADING_SORT_U1 . '">+</a>&nbsp;' . TABLE_HEADING_POINTS_VALUE . '&nbsp;<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params() . 'viewedSort=points-desc') . '" title="' . TABLE_HEADING_SORT . TABLE_HEADING_POINTS_VALUE . TABLE_HEADING_SORT_D1; ?>">-</a></td>
                <td class="dataTableHeadingContent" align="right"><?php echo '<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params() . 'viewedSort=o_status-asc') . '" title="' . TABLE_HEADING_SORT . TABLE_HEADING_ORDERS_STATUS . TABLE_HEADING_SORT_UA . '">+</a>&nbsp;' . TABLE_HEADING_ORDERS_STATUS . '&nbsp;<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params() . 'viewedSort=o_status-desc') . '" title="' . TABLE_HEADING_SORT . TABLE_HEADING_ORDERS_STATUS . TABLE_HEADING_SORT_DA; ?>">-</a></td>
                <td class="dataTableHeadingContent" align="right"><?php echo '<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params() . 'viewedSort=p_status-asc') . '" title="' . TABLE_HEADING_SORT . TABLE_HEADING_POINTS_STATUS . TABLE_HEADING_SORT_UA . '">+</a>&nbsp;' . TABLE_HEADING_POINTS_STATUS . '&nbsp;<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params() . 'viewedSort=p_status-desc') . '" title="' . TABLE_HEADING_SORT . TABLE_HEADING_POINTS_STATUS . TABLE_HEADING_SORT_DA; ?>">-</a></td>
                <td class="dataTableHeadingContent" align="right"><?php echo 'Cuenta'; ?>&nbsp;</td>
		<td class="dataTableHeadingContent" align="right"><?php echo 'Profesional'; ?>&nbsp;</td>
                <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_ACTION; ?>&nbsp;</td>
              </tr>
<?php
    $viewedSort = $_GET['viewedSort'] ?? '';
    $page       = (int) ($_GET['page'] ?? 1);
    if ($page < 1) $page = 1;

    $sort_map = [
        'c_name-asc'    => 'o.customers_name',
        'c_name-desc'   => 'o.customers_name desc',
        'ot-asc'        => 'ot.text',
        'ot-desc'       => 'ot.text desc',
        'points-asc'    => 'points_pending',
        'points-desc'   => 'points_pending desc',
        'o_status-asc'  => 'orders_status_name',
        'o_status-desc' => 'orders_status_name desc',
        'p_status-asc'  => 'points_status',
        'p_status-desc' => 'points_status desc',
    ];
    $sort = $sort_map[$viewedSort] ?? 'o.orders_id desc';

    if (isset($_GET['search']) && tep_not_null($_GET['search'])) {
        $sorders = (int) tep_db_input(tep_db_prepare_input($_GET['search']));
        $pending_points_query_raw = "select o.orders_id, o.customers_id, o.customers_name, o.customers_email_address, o.payment_method, o.date_purchased, o.orders_status, s.orders_status_name, ot.text as order_total, pp.unique_id, pp.points_pending, pp.points_comment, pp.points_status, pp.points_type from " . TABLE_ORDERS . " o left join " . TABLE_ORDERS_TOTAL . " ot on (o.orders_id = ot.orders_id), " . TABLE_ORDERS_STATUS . " s, " . TABLE_CUSTOMERS_POINTS_PENDING . " pp where o.orders_id = '" . $sorders . "' and o.orders_status = s.orders_status_id and s.language_id = '" . (int)$languages_id . "' and ot.class = 'ot_total' order by orders_id DESC";
    } elseif (isset($_GET['filter']) && is_numeric($_GET['filter']) && ($_GET['filter'] > 0)) {
        $filter = 'and pp.points_status = ' . (int) tep_db_prepare_input($_GET['filter']);
        if ($_GET['filter'] == '5') $filter = 'and pp.points_status != 0';
        if (isset($_GET['status']) && is_numeric($_GET['status']) && ($_GET['status'] > 0)) {
            $status = (int) tep_db_prepare_input($_GET['status']);
            $pending_points_query_raw = "select o.orders_id, o.customers_id, o.customers_name, o.customers_email_address, o.payment_method, o.date_purchased, o.orders_status, s.orders_status_name, ot.text as order_total, pp.unique_id, pp.points_pending, pp.points_comment, pp.points_status, pp.points_type from " . TABLE_ORDERS . " o left join " . TABLE_ORDERS_TOTAL . " ot on (o.orders_id = ot.orders_id), " . TABLE_ORDERS_STATUS . " s, " . TABLE_CUSTOMERS_POINTS_PENDING . " pp where o.orders_id = pp.orders_id and o.orders_status = '" . $status . "' " . $filter . " and o.orders_status = s.orders_status_id and s.language_id = '" . (int)$languages_id . "' and ot.class = 'ot_total' order by " . $sort;
        } else {
            $pending_points_query_raw = "select o.orders_id, o.customers_id, o.customers_name, o.customers_email_address, o.payment_method, o.date_purchased, o.orders_status, s.orders_status_name, ot.text as order_total, pp.unique_id, pp.points_pending, pp.points_comment, pp.points_status, pp.points_type from " . TABLE_ORDERS . " o left join " . TABLE_ORDERS_TOTAL . " ot on (o.orders_id = ot.orders_id), " . TABLE_ORDERS_STATUS . " s, " . TABLE_CUSTOMERS_POINTS_PENDING . " pp where points_type = 'SP' " . $filter . " and (pp.orders_id = o.orders_id and ot.class = 'ot_total' and o.orders_id = ot.orders_id and o.orders_status = s.orders_status_id) and s.language_id = '" . (int)$languages_id . "' order by " . $sort;
        }
    } else {
        $pending_points_query_raw = "select o.orders_id, o.customers_id, o.customers_name, o.customers_email_address, o.payment_method, o.date_purchased, o.orders_status, s.orders_status_name, ot.text as order_total, pp.unique_id, pp.points_pending, pp.points_comment, pp.points_status, pp.points_type from " . TABLE_ORDERS . " o left join " . TABLE_ORDERS_TOTAL . " ot on (o.orders_id = ot.orders_id), " . TABLE_ORDERS_STATUS . " s, " . TABLE_CUSTOMERS_POINTS_PENDING . " pp where points_type = 'SP' and pp.points_status = 1 and (pp.orders_id = o.orders_id and ot.class = 'ot_total' and o.orders_id = ot.orders_id and o.orders_status = s.orders_status_id) and s.language_id = '" . (int)$languages_id . "' order by " . $sort;
    }

    $page_ref = $_GET['page'] ?? null;  // splitPageResults toma el 1er arg por referencia
    $pending_points_split = new splitPageResults($page_ref, MAX_DISPLAY_SEARCH_RESULTS, $pending_points_query_raw, $pending_points_query_numrows);
    $pending_points_query = tep_db_query($pending_points_query_raw);

    if (isset($_GET['search']) && tep_not_null($_GET['search']) && tep_db_num_rows($pending_points_query) == 0) {
        $sorders  = strtolower(tep_db_input(tep_db_prepare_input($_GET['search'])));
        $sSelect  = 'select distinct o.orders_id, o.customers_id, o.orders_id, o.customers_name, o.customers_email_address, o.payment_method, o.date_purchased, o.orders_status, s.orders_status_name, ot.text as order_total, pp.unique_id, pp.points_pending, pp.points_comment, pp.points_status, pp.points_type ';
        $sSqlFrom = ' from orders o '
                  . 'left join orders_total ot on (o.orders_id = ot.orders_id) '
                  . 'inner join orders_status s on(o.orders_status = s.orders_status_id) '
                  . 'inner join customers_points_pending pp on(pp.customer_id = o.customers_id and pp.orders_id = o.orders_id) '
                  . 'where (LOWER(o.customers_id) LIKE "%' . $sorders . '%" or lower(o.customers_name) like "%' . $sorders . '%" or lower(o.customers_email_address) like "%' . $sorders . '%") and s.language_id = ' . (int) $languages_id . ' and ot.class = "ot_total"  order by o.orders_id DESC';
        $pending_points_query_raw = $sSelect . $sSqlFrom;
        $page_ref2                = $_GET['page'] ?? null;
        $pending_points_split     = new splitPageResults($page_ref2, MAX_DISPLAY_SEARCH_RESULTS, $pending_points_query_raw, $pending_points_query_numrows);
        $pending_points_query     = tep_db_query($pending_points_query_raw);
    }

    while ($pending_points = tep_db_fetch_array($pending_points_query)) {

    if ((!isset($_GET['uID']) || (isset($_GET['uID']) && ($_GET['uID'] == $pending_points['unique_id']))) && !isset($uInfo)) {
        $uInfo = new objectInfo($pending_points);
      }

      if (isset($uInfo) && is_object($uInfo) && ($pending_points['unique_id'] == $uInfo->unique_id)) {
        echo '<tr id="defaultSelected" class="dataTableRowSelected" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href=\'' . tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params(array('uID', 'action')) . 'uID=' . $uInfo->unique_id . '&action=edit') . '\'">' . "\n";
      } else {
        echo '<tr class="dataTableRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href=\'' . tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params(array('uID')) . 'uID=' . $pending_points['unique_id']) . '\'">' . "\n";
      }

	$points_status_name = '';
	if ($pending_points['points_status'] == 1) $points_status_name = TEXT_POINTS_PENDING;
	if ($pending_points['points_status'] == 2) $points_status_name = TEXT_POINTS_CONFIRMED;
	if ($pending_points['points_status'] == 3) $points_status_name = '<font color="FF0000">' . TEXT_POINTS_CANCELLED . '</font>';
	if ($pending_points['points_status'] == 4) $points_status_name = '<font color="0000FF">' . TEXT_POINTS_REDEEMED . '</font>';

	$display_points = ($pending_points['points_pending'] >0) ? number_format($pending_points['points_pending'],POINTS_DECIMAL_PLACES) : str_replace('-', '', number_format($pending_points['points_pending'],POINTS_DECIMAL_PLACES));

   $customers_query = tep_db_query ("select customers_group_id from " . TABLE_CUSTOMERS . " where customers_id = " . (int) $pending_points['customers_id']);
   $customers = tep_db_fetch_array($customers_query);
	   $createaccount='Si';
 	if ($customers['customers_group_id']==0) {
	   $customers_group_id='No';
	}else{
	   $customers_group_id='Si';
	}

?>
                <td class="dataTableContent"><?php echo '<a href="' . tep_href_link(FILENAME_ORDERS . '?oID=' . $uInfo->orders_id . '&action=edit') . '">' . tep_image(DIR_WS_ICONS . 'preview.png', ICON_PREVIEW_EDIT) . '</a>&nbsp;' . $pending_points['customers_name']; ?></td>
                <td class="dataTableContent" align="right"><?php echo strip_tags($pending_points['order_total']); ?></td>
                <td class="dataTableContent" align="right"><?php echo str_replace('-', '', number_format($pending_points['points_pending'],POINTS_DECIMAL_PLACES)); ?></td>
                <td class="dataTableContent" align="right"><?php echo str_replace('-', '', $currencies->format($pending_points['points_pending'] * REDEEM_POINT_VALUE)); ?></td>
                <td class="dataTableContent" align="center"><?php echo $pending_points['orders_status_name']; ?></td>
                <td class="dataTableContent" align="center"><?php echo $points_status_name; ?></td>
				<?php if  ($createaccount=='No') { ?>
					<td class="dataTableContent" align="center"><font color=FF0000><?php echo $createaccount; ?></td>
				<?php } else { ?>
					<td class="dataTableContent" align="center"><?php echo $createaccount; ?></td>
				<?php } ?>
		        <td class="dataTableContent" align="center" ><font color=FF0000><?php echo $customers_group_id; ?></td>

                <td class="dataTableContent" align="right"><?php if (isset($uInfo) && is_object($uInfo) && ($pending_points['unique_id'] == $uInfo->unique_id)) { echo tep_image(DIR_WS_IMAGES . 'icon_arrow_right.png', ''); } else { echo '<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params(array('uID')) . 'uID=' . $pending_points['unique_id']) . '">' . tep_image(DIR_WS_IMAGES . 'icon_info.gif', IMAGE_ICON_INFO) . '</a>'; } ?>&nbsp;</td>
              </tr>
<?php
    }
?>
              <tr>
                <td colspan="7"><table border="0" width="100%" cellspacing="0" cellpadding="2">
                  <tr>
                    <td class="smallText" valign="top"><?php echo $pending_points_split->display_count($pending_points_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, $_GET['page'] ?? 1, TEXT_DISPLAY_NUMBER_OF_ORDERS); ?></td>
                    <td class="smallText" align="right"><?php echo $pending_points_split->display_links($pending_points_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, MAX_DISPLAY_PAGE_LINKS, $_GET['page'] ?? 1, tep_get_all_get_params(array('page', 'uID', 'action'))); ?></td>
                  </tr>
<?php
    if (isset($_GET['search']) && tep_not_null($_GET['search'])) {
?>
                  <tr>
                    <td align="right" colspan="2"><?php echo '<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING) . '">' . tep_image_button('button_reset.gif', IMAGE_RESET) . '</a>'; ?></td>
                  </tr>
<?php
    }
?>
                </table></td>
              </tr>
            </table></td>
<?php
  $heading = array();
  $contents = array();

  switch ($action) {
    case 'confirm':
      $heading[] = array('text' => '<b>' . TEXT_CONFIRM_POINTS . '</b>');

      $contents = array('form' => tep_draw_form('points_confirm', FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params(array('uID', 'action')) . 'uID=' . $uInfo->unique_id . '&action=confirm_points'));
      $value_field = TEXT_CONFIRM_POINTS_LONG. '<br>';
      $contents[] = array('text' => $value_field);
      $contents[] = array('text' => tep_draw_checkbox_field('notify_confirm', '', true) . ' ' . TEXT_NOTIFY_CUSTOMER);
      $contents[] = array('text' => tep_draw_checkbox_field('queue_confirm', '', true) . ' ' . TEXT_QUEUE_POINTS_TABLE);
      $contents[] = array('text' => tep_draw_hidden_field('orders_id', $uInfo->orders_id) . tep_draw_hidden_field('customers_id', $uInfo->customers_id) . tep_draw_hidden_field('customers_email_address', $uInfo->customers_email_address) . tep_draw_hidden_field('date_purchased', $uInfo->date_purchased) . tep_draw_hidden_field('orders_status_name', $uInfo->orders_status_name) . tep_draw_hidden_field('points_pending', $uInfo->points_pending));

      $contents[] = array('align' => 'center', 'text' => '<br>' . tep_image_submit('button_confirm_points.gif', BUTTON_TEXT_CONFIRM_PENDING_POINTS) . ' <a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params(array('uID', 'action')) . 'uID=' . $uInfo->unique_id) . '">' . tep_image_button('button_cancel.gif', IMAGE_CANCEL) . '</a>');
      break;
    case 'cancel':
      $heading[] = array('text' => '<b>' . TEXT_CANCEL_POINTS . '</b>');

      $contents = array('form' => tep_draw_form('points_cancel', FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params(array('uID', 'action')) . 'uID=' . $uInfo->unique_id . '&action=cancel_points'));
      $contents[] = array('text' => TEXT_CANCEL_POINTS_LONG);
      $value_field = TEXT_CANCELLATION_REASON .'<br>'. tep_draw_input_field('comment_cancel', 0);
      $contents[] = array('text' => $value_field);
      $contents[] = array('text' => tep_draw_checkbox_field('notify_cancel', '', true) . ' ' . TEXT_NOTIFY_CUSTOMER);
      $contents[] = array('text' => tep_draw_checkbox_field('queue_cancel', '', true) . ' ' . TEXT_QUEUE_POINTS_TABLE);
      $contents[] = array('text' => tep_draw_hidden_field('orders_id', $uInfo->orders_id) . tep_draw_hidden_field('customers_id', $uInfo->customers_id) . tep_draw_hidden_field('customers_email_address', $uInfo->customers_email_address) . tep_draw_hidden_field('date_purchased', $uInfo->date_purchased) . tep_draw_hidden_field('orders_status_name', $uInfo->orders_status_name) . tep_draw_hidden_field('points_pending', $uInfo->points_pending));

      $contents[] = array('align' => 'center', 'text' => '<br>' . tep_image_submit('button_cancel_points.gif', BUTTON_TEXT_CANCEL_PENDING_POINTS) . ' <a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params(array('uID', 'action')) . 'uID=' . $uInfo->unique_id) . '">' . tep_image_button('button_cancel.gif', IMAGE_CANCEL) . '</a>');
      break;
    case 'adjust':
      $heading[] = array('text' => '<b>' . TEXT_INFO_HEADING_ADJUST_POINTS . '</b>');

      $contents = array('form' => tep_draw_form('points_adjust', FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params(array('uID', 'action')) . 'uID=' . $uInfo->unique_id . '&action=adjustpoints'));
      $contents[] = array('text' => '<b>'. TEXT_INFO_HEADING_ADJUST_POINTS . '</b><br>');
      $value_field = TEXT_ADJUST_INTRO . '<br><br>' . TEXT_POINTS_TO_ADJUST . '<br>'. tep_draw_input_field('points_to_aj', '' , 'onkeyup="validate(this)"');
      $contents[] = array('text' => $value_field);

      $contents[] = array('align' => 'center', 'text' => '<br>' . tep_image_submit('button_adjust_points.gif', BUTTON_TEXT_ADJUST_POINTS) . ' <a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params(array('uID', 'action')) . 'uID=' . $uInfo->unique_id) . '">' . tep_image_button('button_cancel.gif', IMAGE_CANCEL) . '</a>');
      break;
    case 'rollback':
      $heading[] = array('text' => '<b>' . TEXT_ROLL_POINTS . '</b>');

      $contents = array('form' => tep_draw_form('points_roll', FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params(array('uID', 'action')) . 'uID=' . $uInfo->unique_id . '&action=pe_rollback'));
      $contents[] = array('text' => '<b>'. TEXT_ROLL_POINTS . '</b><br>');
      $value_field = TEXT_ROLL_POINTS_LONG. '<br>';
      $contents[] = array('text' => $value_field);
      $value_field = TEXT_ROLL_REASON .'<br>'. tep_draw_input_field('comment_roll', 0);
      $contents[] = array('text' => $value_field);
      $contents[] = array('text' => tep_draw_checkbox_field('notify_roll', '', true) . ' ' . TEXT_NOTIFY_CUSTOMER);
      $contents[] = array('text' => tep_draw_hidden_field('orders_id', $uInfo->orders_id) . tep_draw_hidden_field('customers_id', $uInfo->customers_id) . tep_draw_hidden_field('customers_email_address', $uInfo->customers_email_address) . tep_draw_hidden_field('date_purchased', $uInfo->date_purchased) . tep_draw_hidden_field('orders_status_name', $uInfo->orders_status_name) . tep_draw_hidden_field('points_pending', $uInfo->points_pending));

      $contents[] = array('align' => 'center', 'text' => '<br>' . tep_image_submit('button_rollback_points.gif', BUTTON_TEXT_ROLL_POINTS) . ' <a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params(array('uID', 'action')) . 'uID=' . $uInfo->unique_id) . '">' . tep_image_button('button_cancel.gif', IMAGE_CANCEL) . '</a>');
      break;
    case 'delete':
      $heading[] = array('text' => '<b>' . TEXT_INFO_HEADING_DELETE_RECORD . '</b>');

      $contents = array('form' => tep_draw_form('points_delete', FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params(array('uID', 'action')) . 'uID=' . $uInfo->unique_id . '&action=delete_points'));
      $contents[] = array('text' => TEXT_DELETE_INTRO );

      $contents[] = array('align' => 'center', 'text' => '<br>' . tep_image_submit('button_delete.gif', BUTTON_TEXT_REMOVE_RECORD) . ' <a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params(array('uID', 'action')) . 'uID=' . $uInfo->unique_id) . '">' . tep_image_button('button_cancel.gif', IMAGE_CANCEL) . '</a>');
      break;

    default:
      if (isset($uInfo) && is_object($uInfo)) {
		$heading[] = array('text' => TEXT_INFO_HEADING_PENDING_NO .'<b>' . $uInfo->orders_id . '</b>');

		if ($uInfo->points_status == 1) {
		  $contents[] = array('align' => 'center', 'text' => '<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params(array('uID', 'action')) . 'uID=' . $uInfo->unique_id . '&action=confirm') . '">' . tep_image_button('button_confirm_points.gif', BUTTON_TEXT_CONFIRM_PENDING_POINTS) . '</a> <a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params(array('uID', 'action')) . 'uID=' . $uInfo->unique_id . '&action=cancel') . '">' . tep_image_button('button_cancel_points.gif', BUTTON_TEXT_CANCEL_PENDING_POINTS) . '</a> <a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params(array('uID', 'action')) . 'uID=' . $uInfo->unique_id . '&action=adjust') . '">' . tep_image_button('button_adjust_points.gif', BUTTON_TEXT_ADJUST_POINTS) . '</a> <a href="' . tep_href_link(FILENAME_MAIL, 'selected_box=tools&customer=' . $uInfo->customers_email_address) . '">' . tep_image_button('button_email.gif', IMAGE_EMAIL) . '</a> <a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params(array('uID', 'action')) . 'uID=' . $uInfo->unique_id . '&action=delete') . '">' . tep_image_button('button_delete.gif', BUTTON_TEXT_REMOVE_RECORD) . '</a>');
		}
		if ($uInfo->points_status == 2) {
		  $contents[] = array('align' => 'center', 'text' => '<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params(array('action')) . 'uID=' . $uInfo->unique_id . '&action=rollback') . '">' . tep_image_button('button_rollback_points.gif', BUTTON_TEXT_ROLL_POINTS) . '</a> <a href="' . tep_href_link(FILENAME_MAIL, 'selected_box=tools&customer=' . $uInfo->customers_email_address) . '">' . tep_image_button('button_email.gif', IMAGE_EMAIL) . '</a> <a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params(array('uID', 'action')) . 'uID=' . $uInfo->unique_id . '&action=delete') . '">' . tep_image_button('button_delete.gif', BUTTON_TEXT_REMOVE_RECORD) . '</a>');
		}
		if ($uInfo->points_status == 3) {
		  $contents[] = array('text' => '<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params(array('uID', 'action')) . 'uID=' . $uInfo->unique_id . '&action=confirm') . '">' . tep_image_button('button_confirm_points.gif', BUTTON_TEXT_CONFIRM_PENDING_POINTS) . '</a> <a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params(array('uID', 'action')) . 'uID=' . $uInfo->unique_id . '&action=adjust') . '">' . tep_image_button('button_adjust_points.gif', BUTTON_TEXT_ADJUST_POINTS) . '</a> <a href="' . tep_href_link(FILENAME_ORDERS . '?oID=' . $uInfo->orders_id . '&action=edit') . '">' . tep_image_button('button_details.gif', IMAGE_DETAILS) . '</a> <a href="' . tep_href_link(FILENAME_MAIL, 'selected_box=tools&customer=' . $uInfo->customers_email_address) . '">' . tep_image_button('button_email.gif', IMAGE_EMAIL) . '</a> <a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params(array('uID', 'action')) . 'uID=' . $uInfo->unique_id . '&action=delete') . '">' . tep_image_button('button_delete.gif', BUTTON_TEXT_REMOVE_RECORD) . '</a>');
		}
		if ($uInfo->points_status == 4) {
		  $contents[] = array('align' => 'center', 'text' => '<a href="' . tep_href_link(FILENAME_ORDERS . '?oID=' . $uInfo->orders_id . '&action=edit') . '">' . tep_image_button('button_details.gif', IMAGE_DETAILS) . '</a> <a href="' . tep_href_link(FILENAME_MAIL, 'selected_box=tools&customer=' . $uInfo->customers_email_address) . '">' . tep_image_button('button_email.gif', IMAGE_EMAIL) . '</a> <a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS_PENDING, tep_get_all_get_params(array('uID', 'action')) . 'uID=' . $uInfo->unique_id . '&action=delete') . '">' . tep_image_button('button_delete.gif', BUTTON_TEXT_REMOVE_RECORD) . '</a>');
		}

		if ($uInfo->points_comment == 'TEXT_DEFAULT_COMMENT') {
		   $uInfo->points_comment = TEXT_DEFAULT_COMMENT;
		}

		if ($uInfo->points_comment == 'TEXT_DEFAULT_REDEEMED') {
		   $uInfo->points_comment = TEXT_DEFAULT_REDEEMED;
		}

		if ($uInfo->points_comment == 'TEXT_DEFAULT_REFERRAL') {
		   $uInfo->points_comment = TEXT_DEFAULT_REFERRAL;
		}

		$contents[] = array('text' => '<br><b>' . TEXT_INFO_POINTS_COMMENT . '</b><br>' . $uInfo->points_comment);
		$contents[] = array('text' => '<br>' . EMAIL_TEXT_DATE_ORDERED . ' ' . tep_date_short($uInfo->date_purchased));
		$contents[] = array('text' => '<br><b>' . TEXT_INFO_PAYMENT_METHOD . '</b><br>' . $uInfo->payment_method);
      }
      break;
  }

  if ( (tep_not_null($heading)) && (tep_not_null($contents)) ) {
    echo '<td width="25%" valign="top">' . "\n";

    $box = new box;
    echo $box->infoBox($heading, $contents);

    echo '</td>' . "\n";
  }
?>
          </tr>
        </table></td>
      </tr>
    </table></td>
<!-- body_text_eof //-->
  </tr>
</table>


<?php require(THEME . 'html/footer.php'); ?>
