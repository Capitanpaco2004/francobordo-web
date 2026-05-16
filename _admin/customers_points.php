<?php
/*
 * Points & Rewards V2.1rc2a — Ben Zukrel (Deep Silver Accessories) 2008, osCommerce, GPL.
 *
 * Refactor 2026-05-15:
 * - Usa tep_send_points_notification() (helper en includes/functions/redemptions.php) para los emails.
 * - Validaciones POST con (int)/cast y null-coalescing.
 * - Inicializadas variables que daban warnings ($sort, $filter, $action).
 * - Fix de llaves en el case 'log' (el while no tenía bloque, $contents se ejecutaba una sola vez fuera).
 * - Cast (int) en $cID del case 'log' (era SQL injection en panel admin).
 * - Eliminadas las asignaciones muertas $sql = "OPTIMIZE TABLE ...".
 */

  require('includes/application_top.php');
  include(DIR_WS_LANGUAGES . $language . '/customers_points_pending.php');

  require(DIR_WS_CLASSES . 'currencies.php');
  $currencies = new currencies();

  $action = $_GET['action'] ?? '';

  if (tep_not_null($action)) {
    switch ($action) {
      case 'addconfirm': {
        $customers_id            = (int) ($_GET['cID'] ?? 0);
        $pointstoadd             = (int) ($_POST['points_to_add'] ?? 0);
        $comment                 = tep_db_prepare_input($_POST['comment'] ?? '');
        $customers_email_address = tep_db_prepare_input($_POST['customers_email_address'] ?? '');

        $points_added = false;
        if ($pointstoadd > 0 && $customers_id > 0) {
          $set_exp = (isset($_POST['set_exp']) && $_POST['set_exp'] == 'on');

          if ($set_exp) {
            $expire = date('Y-m-d', strtotime('+' . (int) POINTS_AUTO_EXPIRES . ' month'));
            tep_db_query("update " . TABLE_CUSTOMERS . " set customers_shopping_points = customers_shopping_points + '" . $pointstoadd . "', customers_points_expires = '" . $expire . "' where customers_id = '" . $customers_id . "'");
            $expire_date = sprintf(EMAIL_TEXT_EXPIRE, tep_date_short($expire));
          } else {
            tep_db_query("update " . TABLE_CUSTOMERS . " set customers_shopping_points = customers_shopping_points + '" . $pointstoadd . "' where customers_id = '" . $customers_id . "'");
            $expire_date = sprintf(EMAIL_TEXT_EXPIRE, tep_date_short($_POST['customers_points_expires'] ?? ''));
          }

          if (isset($_POST['notify']) && $_POST['notify'] == 'on') {
            $new_balance = ((float) ($_POST['customers_shopping_points'] ?? 0)) + $pointstoadd;
            $name        = trim(($_POST['customers_firstname'] ?? '') . ' ' . ($_POST['customers_lastname'] ?? ''));

            tep_send_points_notification([
                'customer_id'         => $customers_id,
                'customers_email'     => $customers_email_address,
                'customers_gender'    => $_POST['customers_gender']   ?? '',
                'customers_firstname' => $_POST['customers_firstname'] ?? '',
                'customers_lastname'  => $_POST['customers_lastname']  ?? '',
                'points_delta'        => $pointstoadd,
                'new_balance'         => $new_balance,
                'subject'             => EMAIL_TEXT_SUBJECT,
                'comment'             => tep_not_null($comment) ? $comment : null,
                'expire_msg'          => tep_not_null(POINTS_AUTO_EXPIRES) ? $expire_date : null,
            ]);

            $messageStack->add_session(sprintf(NOTICE_EMAIL_SENT_TO, $name . '(' . $customers_email_address . ').'), 'success');
          }

          if (isset($_POST['queue_add']) && $_POST['queue_add'] == 'on') {
            tep_db_perform(TABLE_CUSTOMERS_POINTS_PENDING, [
                'customer_id'    => $customers_id,
                'orders_id'      => 0,
                'points_comment' => $comment,
                'points_pending' => $pointstoadd,
                'date_added'     => 'now()',
                'points_status'  => 2,
            ]);
            $messageStack->add_session(SUCCESS_DATABASE_UPDATED, 'success');
          }

          $points_added = true;
        }

        $messageStack->add_session($points_added ? SUCCESS_POINTS_UPDATED : WARNING_DATABASE_NOT_UPDATED, $points_added ? 'success' : 'warning');
        tep_redirect(tep_href_link(FILENAME_CUSTOMERS_POINTS, tep_get_all_get_params(['oID', 'action'])));
        break;
      }

      case 'delconfirm': {
        $customers_id            = (int) ($_GET['cID'] ?? 0);
        $pointstodel             = (int) ($_POST['points_to_delete'] ?? 0);
        $comment                 = tep_db_prepare_input($_POST['comment'] ?? '');
        $balance                 = ((float) ($_POST['customers_shopping_points'] ?? 0)) - $pointstodel;
        $Cexpire_date            = tep_db_prepare_input($_POST['customers_points_expires'] ?? '');
        $customers_email_address = tep_db_prepare_input($_POST['customers_email_address'] ?? '');

        $points_deleted = false;
        if ($pointstodel > 0 && $customers_id > 0) {
          $set_exp = (isset($_POST['set_exp']) && $_POST['set_exp'] == 'on' && $balance > 0);

          if ($set_exp) {
            $expire = date('Y-m-d', strtotime('+' . (int) POINTS_AUTO_EXPIRES . ' month'));
            tep_db_query("update " . TABLE_CUSTOMERS . " set customers_shopping_points = customers_shopping_points - '" . $pointstodel . "', customers_points_expires = '" . $expire . "' where customers_id = '" . $customers_id . "'");
            $expire_date = sprintf(EMAIL_TEXT_EXPIRE, tep_date_short($expire));
          } else {
            tep_db_query("update " . TABLE_CUSTOMERS . " set customers_shopping_points = customers_shopping_points - '" . $pointstodel . "' where customers_id = '" . $customers_id . "'");
            $expire_date = sprintf(EMAIL_TEXT_EXPIRE, tep_date_short($_POST['customers_points_expires'] ?? ''));
          }

          if (isset($_POST['notify']) && $_POST['notify'] == 'on') {
            $name = trim(($_POST['customers_firstname'] ?? '') . ' ' . ($_POST['customers_lastname'] ?? ''));

            tep_send_points_notification([
                'customer_id'         => $customers_id,
                'customers_email'     => $customers_email_address,
                'customers_gender'    => $_POST['customers_gender']   ?? '',
                'customers_firstname' => $_POST['customers_firstname'] ?? '',
                'customers_lastname'  => $_POST['customers_lastname']  ?? '',
                'points_delta'        => -$pointstodel,
                'new_balance'         => max($balance, 0),
                'subject'             => EMAIL_TEXT_SUBJECT,
                'comment'             => tep_not_null($comment) ? $comment : null,
                'expire_msg'          => tep_not_null(POINTS_AUTO_EXPIRES) ? $expire_date : null,
            ]);

            $messageStack->add_session(sprintf(NOTICE_EMAIL_SENT_TO, $name . '(' . $customers_email_address . ').'), 'success');
          }

          if (isset($_POST['queue_delete']) && $_POST['queue_delete'] == 'on') {
            tep_db_perform(TABLE_CUSTOMERS_POINTS_PENDING, [
                'customer_id'    => $customers_id,
                'orders_id'      => 0,
                'points_comment' => $comment,
                'points_pending' => -$pointstodel,
                'date_added'     => 'now()',
                'points_status'  => 3,
            ]);
            $messageStack->add_session(SUCCESS_DATABASE_UPDATED, 'success');
          }

          $points_deleted = true;
        }

        $messageStack->add_session($points_deleted ? SUCCESS_POINTS_UPDATED : WARNING_DATABASE_NOT_UPDATED, $points_deleted ? 'success' : 'warning');
        tep_redirect(tep_href_link(FILENAME_CUSTOMERS_POINTS, tep_get_all_get_params(['oID', 'action'])));
        break;
      }

      case 'adjustpoints': {
        $customers_id = (int) ($_GET['cID'] ?? 0);
        $adjust       = $_POST['points_to_aj'] ?? '';

        if (tep_not_null($adjust) && is_numeric($adjust) && $adjust >= 0 && $customers_id > 0) {
          $adjust = (float) $adjust;
          if ($adjust != 0) {
            if (isset($_POST['set_exp']) && $_POST['set_exp'] == 'on' && tep_not_null(POINTS_AUTO_EXPIRES)) {
              $expire = date('Y-m-d', strtotime('+' . (int) POINTS_AUTO_EXPIRES . ' month'));
              tep_db_query("update " . TABLE_CUSTOMERS . " set customers_shopping_points = '" . $adjust . "', customers_points_expires = '" . $expire . "' where customers_id = '" . $customers_id . "'");
            } else {
              tep_db_query("update " . TABLE_CUSTOMERS . " set customers_shopping_points = '" . $adjust . "' where customers_id = '" . $customers_id . "'");
            }
          } else {
            tep_db_query("update " . TABLE_CUSTOMERS . " set customers_shopping_points = '0', customers_points_expires = null where customers_id = '" . $customers_id . "'");
          }
        }
        tep_redirect(tep_href_link(FILENAME_CUSTOMERS_POINTS, tep_get_all_get_params(['oID', 'action'])));
        break;
      }
    }
  }

//drop-down filter array
  $filter_array = [
      ['id' => '1', 'text' => TEXT_SHOW_ALL],
      ['id' => '2', 'text' => TEXT_SORT_POINTS],
      ['id' => '3', 'text' => TEXT_SORT_NO_POINTS],
      ['id' => '4', 'text' => TEXT_SORT_BIRTH],
      ['id' => '5', 'text' => TEXT_SORT_BIRTH_NEXT],
      ['id' => '6', 'text' => TEXT_SORT_EXPIRE],
      ['id' => '7', 'text' => TEXT_SORT_EXPIRE_NEXT],
      ['id' => '8', 'text' => TEXT_SORT_EXPIRE_WIN],
  ];

  $point_or_points = (POINTS_PER_AMOUNT_PURCHASE > 1) ? HEADING_POINTS : HEADING_POINT;
?>

<?php require(THEME . 'html/header.php'); ?>

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

<table border="0" width="100%" cellspacing="2" cellpadding="2">
  <tr>
<!-- body_text //-->
    <td width="100%" valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
      <tr>
        <td width="100%"><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo HEADING_TITLE . '<br /><span class="smallText">' . HEADING_RATE . '&nbsp;&nbsp;&nbsp;' .  HEADING_AWARDS . $currencies->format(1) . ' = ' . number_format(POINTS_PER_AMOUNT_PURCHASE,POINTS_DECIMAL_PLACES) .'&nbsp;' . $point_or_points . '&nbsp;&nbsp;&nbsp;' . HEADING_REDEEM  .  number_format(POINTS_PER_AMOUNT_PURCHASE,POINTS_DECIMAL_PLACES) . '&nbsp;' . $point_or_points . ' = ' . $currencies->format(POINTS_PER_AMOUNT_PURCHASE * REDEEM_POINT_VALUE); ?></td>
            <td align="right"><table border="0" width="100%" cellspacing="0" cellpadding="0"><?php echo tep_draw_form('orders', FILENAME_CUSTOMERS_POINTS, '', 'get'); ?>
            <td class="smallText" align="right"><?php echo HEADING_TITLE_SEARCH . ' ' . tep_draw_input_field('search'); ?></td>
              </form>
              <tr><?php echo tep_draw_form('status', FILENAME_CUSTOMERS_POINTS, '', 'get'); ?>
                <td class="smallText" align="right"><?php echo '&nbsp;'. TEXT_SORT_CUSTOMERS . ':&nbsp;'. tep_draw_pull_down_menu('filter', $filter_array, '', 'onChange="this.form.submit();"'); ?></td>
              </form></tr>
            </table></td>
          </tr>
        </table></td>
      </tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr class="dataTableHeadingRow">
                <td class="dataTableHeadingContent"><?php echo '<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS, tep_get_all_get_params() . 'viewedSort=lastname-asc') . '" title="' . TABLE_HEADING_SORT . TABLE_HEADING_LASTNAME . TABLE_HEADING_SORT_UA . '">+</a>&nbsp;' . TABLE_HEADING_LASTNAME . '&nbsp;<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS, tep_get_all_get_params() . 'viewedSort=lastname-desc') . '" title="' . TABLE_HEADING_SORT . TABLE_HEADING_LASTNAME . TABLE_HEADING_SORT_DA; ?>">-</a></td>
                <td class="dataTableHeadingContent"><?php echo '<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS, tep_get_all_get_params() . 'viewedSort=firstname-asc') . '" title="' . TABLE_HEADING_SORT . TABLE_HEADING_FIRSTNAME . TABLE_HEADING_SORT_UA . '">+</a>&nbsp;' . TABLE_HEADING_FIRSTNAME . '&nbsp;<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS, tep_get_all_get_params() . 'viewedSort=firstname-desc') . '" title="' . TABLE_HEADING_SORT . TABLE_HEADING_FIRSTNAME . TABLE_HEADING_SORT_DA; ?>">-</a></td>
                <td class="dataTableHeadingContent" align="center"><?php echo '<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS, tep_get_all_get_params() . 'viewedSort=date-asc') . '" title="' . TABLE_HEADING_SORT . TABLE_HEADING_DOB . TABLE_HEADING_SORT_U1 . '">+</a>&nbsp;' . TABLE_HEADING_DOB . '&nbsp;<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS, tep_get_all_get_params() . 'viewedSort=date-desc') . '" title="' . TABLE_HEADING_SORT . TABLE_HEADING_DOB . TABLE_HEADING_SORT_D1; ?>">-</a></td>
                <td class="dataTableHeadingContent" align="right"><?php echo '<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS, tep_get_all_get_params() . 'viewedSort=points-asc') . '" title="' . TABLE_HEADING_SORT . TABLE_HEADING_POINTS . TABLE_HEADING_SORT_U1 . '">+</a>&nbsp;' . TABLE_HEADING_POINTS . '&nbsp;<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS, tep_get_all_get_params() . 'viewedSort=points-desc') . '" title="' . TABLE_HEADING_SORT . TABLE_HEADING_POINTS . TABLE_HEADING_SORT_D1; ?>">-</a></td>
                <td class="dataTableHeadingContent" align="right"><?php echo '<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS, tep_get_all_get_params() . 'viewedSort=points-asc') . '" title="' . TABLE_HEADING_SORT . TABLE_HEADING_POINTS_VALUE . TABLE_HEADING_SORT_U1 . '">+</a>&nbsp;' . TABLE_HEADING_POINTS_VALUE . '&nbsp;<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS, tep_get_all_get_params() . 'viewedSort=points-desc') . '" title="' . TABLE_HEADING_SORT . TABLE_HEADING_POINTS_VALUE . TABLE_HEADING_SORT_D1; ?>">-</a></td>
                <td class="dataTableHeadingContent" align="right"><?php echo '<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS, tep_get_all_get_params() . 'viewedSort=expires-asc') . '" title="' . TABLE_HEADING_SORT . TABLE_HEADING_POINTS_EXPIRES . TABLE_HEADING_SORT_U1 . '">+</a>&nbsp;' . TABLE_HEADING_POINTS_EXPIRES . '&nbsp;<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS, tep_get_all_get_params() . 'viewedSort=expires-desc') . '" title="' . TABLE_HEADING_SORT . TABLE_HEADING_POINTS_EXPIRES . TABLE_HEADING_SORT_D1; ?>">-</a></td>
                <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_ACTION; ?>&nbsp;</td>
              </tr>
<?php
    $search = '';
    if (isset($_GET['search']) && tep_not_null($_GET['search'])) {
      $keywords = strtolower(tep_db_input(tep_db_prepare_input($_GET['search'])));
      $search = "where LOWER(customers_id) LIKE '%" . $keywords . "%' or lower(customers_lastname) like '%" . $keywords . "%' or lower(customers_firstname) LIKE '%" . $keywords . "%' or lower(customers_email_address) LIKE '%" . $keywords . "%'";
    }

    $filter = $_GET['filter'] ?? '1';
    switch ($filter) {
      case '2': $filter = "where customers_shopping_points > 0"; break;
      case '3': $filter = "where customers_shopping_points = 0"; break;
      case '4': $filter = "where MONTH(customers_dob) = MONTH(DATE_ADD(NOW(),INTERVAL 0 MONTH))"; break;
      case '5': $filter = "where MONTH(customers_dob) = MONTH(DATE_ADD(NOW(),INTERVAL 1 MONTH))"; break;
      case '6': $filter = "where customers_points_expires like '%" . date('Y-m') . "%'"; break;
      case '7': $filter = "where customers_points_expires like '%" . date('Y-m', strtotime('+ 1 month')) . "%'"; break;
      case '8': $filter = "where customers_points_expires = DATE_ADD(NOW(),INTERVAL 1 MONTH)"; break;
      case '1':
      default:  $filter = ''; break;
    }

    $viewedSort = $_GET['viewedSort'] ?? 'customers_lastname';
    $sort_map = [
        'lastname-asc'   => 'customers_lastname',
        'lastname-desc'  => 'customers_lastname DESC',
        'firstname-asc'  => 'customers_firstname',
        'firstname-desc' => 'customers_firstname DESC',
        'date-asc'       => 'customers_dob',
        'date-desc'      => 'customers_dob DESC',
        'points-asc'     => 'customers_shopping_points',
        'points-desc'    => 'customers_shopping_points DESC',
        'expires-asc'    => 'customers_points_expires',
        'expires-desc'   => 'customers_points_expires DESC',
    ];
    $sort = $sort_map[$viewedSort] ?? 'customers_lastname';

   $customers_query_raw = "select customers_id, customers_gender, customers_lastname, customers_firstname, customers_dob, customers_email_address, customers_shopping_points, customers_points_expires from " . TABLE_CUSTOMERS . " " . $search . " " . $filter . " order by " . $sort;
   $page = $_GET['page'] ?? null;  // splitPageResults toma el 1er arg por referencia, no acepta expresiones
   $customers_split = new splitPageResults($page, MAX_DISPLAY_SEARCH_RESULTS, $customers_query_raw, $customers_query_numrows);
   $customers_query = tep_db_query($customers_query_raw);

   while ($customers = tep_db_fetch_array($customers_query)) {
     $info_query = tep_db_query("select sum(op.products_quantity * op.final_price) as ordersum from " . TABLE_ORDERS_PRODUCTS . " op, " . TABLE_ORDERS . " o where customers_id = '" . (int)$customers['customers_id'] . "' and o.orders_id = op.orders_id group by customers_id ");
     $info = tep_db_fetch_array($info_query);

     if ((!isset($_GET['cID']) || (isset($_GET['cID']) && ($_GET['cID'] == $customers['customers_id']))) && !isset($cInfo)) {
       $pending_query = tep_db_query("select sum(points_pending) as pending_total from " . TABLE_CUSTOMERS_POINTS_PENDING . " where points_status = 1 and customer_id = '" . (int)$customers['customers_id'] . "'");
       $pending = tep_db_fetch_array($pending_query);

       $cInfo_array = is_array($info) ? array_merge($customers, $pending, $info) : array_merge($customers, $pending);
       $cInfo = new objectInfo($cInfo_array);
      }

      if (isset($cInfo) && is_object($cInfo) && ($customers['customers_id'] == $cInfo->customers_id)) {
        echo '<tr id="defaultSelected" class="dataTableRowSelected" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href=\'' . tep_href_link(FILENAME_CUSTOMERS_POINTS, tep_get_all_get_params(array('cID', 'action')) . 'cID=' . $cInfo->customers_id . '&action=edit') . '\'">' . "\n";
      } else {
        echo '<tr class="dataTableRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href=\'' . tep_href_link(FILENAME_CUSTOMERS_POINTS, tep_get_all_get_params(array('cID')) . 'cID=' . $customers['customers_id']) . '\'">' . "\n";
      }
?>
                <td class="dataTableContent"><?php echo '<a href="' . tep_href_link(FILENAME_ORDERS, 'cID=' . $cInfo->customers_id) . '">' . tep_image(DIR_WS_ICONS . 'preview.png', ICON_PREVIEW) . '</a>&nbsp;' . $customers['customers_lastname']; ?></td>
                <td class="dataTableContent"><?php echo $customers['customers_firstname']; ?></td>
                <td class="dataTableContent" align="center"><?php echo tep_date_short($customers['customers_dob']); ?></td>
                <td class="dataTableContent" align="right"><?php echo number_format($customers['customers_shopping_points'],POINTS_DECIMAL_PLACES); ?></td>
                <td class="dataTableContent" align="right"><?php if ($customers['customers_shopping_points'] > 0) echo $currencies->format($customers['customers_shopping_points'] * REDEEM_POINT_VALUE); ?></td>
                <td class="dataTableContent" align="right"><?php if ($customers['customers_points_expires'] > 0) echo tep_date_short($customers['customers_points_expires']); ?></td>
                <td class="dataTableContent" align="right"><?php if (isset($cInfo) && is_object($cInfo) && ($customers['customers_id'] == $cInfo->customers_id)) { echo tep_image(DIR_WS_IMAGES . 'icon_arrow_right.png', ''); } else { echo '<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS, tep_get_all_get_params(array('cID')) . 'cID=' . $customers['customers_id']) . '">' . tep_image(DIR_WS_IMAGES . 'icon_info.png', IMAGE_ICON_INFO) . '</a>'; } ?>&nbsp;</td>
              </tr>
<?php
    }
?>
              <tr>
                <td colspan="7"><table class="table-page" border="0" width="100%" cellspacing="0" cellpadding="2">
                  <tr>
                    <td class="smallText" valign="top"><?php echo $customers_split->display_count($customers_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, $_GET['page'] ?? 1, TEXT_DISPLAY_NUMBER_OF_CUSTOMERS); ?></td>
                    <td class="smallText" align="right"><?php echo $customers_split->display_links($customers_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, MAX_DISPLAY_PAGE_LINKS, $_GET['page'] ?? 1, tep_get_all_get_params(array('page', 'info', 'x', 'y', 'cID'))); ?></td>
                  </tr>
<?php
    if (isset($_GET['search']) && tep_not_null($_GET['search'])) {
?>
                  <tr>
                    <td align="right" colspan="2"><?php echo '<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS) . '">' . tep_image_button('button_reset.png', IMAGE_RESET) . '</a>'; ?></td>
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
    case 'addpoints':
      $heading[] = array('text' => '<b>' . $cInfo->customers_firstname . ' ' . $cInfo->customers_lastname . '</b>');

      $contents = array('form' => tep_draw_form('customers', FILENAME_CUSTOMERS_POINTS, tep_get_all_get_params() . 'cID=' . $cInfo->customers_id . '&action=addconfirm'));
      $value_field = '<b>'. TEXT_ADD_POINTS . '</b><br>'. TEXT_ADD_POINTS_LONG . '<br><br>' . TEXT_POINTS_TO_ADD . '<br>'. tep_draw_input_field('points_to_add', '' , 'onBlur="validate(this)"');
      $contents[] = array('text' => $value_field);
      $value_field = TEXT_COMMENT. '<br>'. tep_draw_input_field('comment', 0);
      $contents[] = array('text' => $value_field);
      $contents[] = array('text' => tep_draw_checkbox_field('notify', '', true) . ' ' . TEXT_NOTIFY_CUSTOMER);
      if (tep_not_null(POINTS_AUTO_EXPIRES)){
        $contents[] = array('text' => tep_draw_checkbox_field('set_exp', '', true) . ' ' . TEXT_SET_EXPIRE);
      }
      $contents[] = array('text' => tep_draw_checkbox_field('queue_add', '', true) . ' ' . TEXT_QUEUE_POINTS_TABLE);
      $contents[] = array('text' => tep_draw_hidden_field('customers_firstname', $cInfo->customers_firstname) . tep_draw_hidden_field('customers_lastname', $cInfo->customers_lastname) . tep_draw_hidden_field('customers_gender', $cInfo->customers_gender) . tep_draw_hidden_field('customers_email_address', $cInfo->customers_email_address) . tep_draw_hidden_field('customers_shopping_points', $cInfo->customers_shopping_points) . tep_draw_hidden_field('customers_points_expires', $cInfo->customers_points_expires));

      $contents[] = array('align' => 'center', 'text' => '<br>' . tep_image_submit('button_add_points.png', BUTTON_TEXT_ADD_POINTS) . ' <a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS, tep_get_all_get_params(array('cID', 'action')) . 'cID=' . $cInfo->customers_id) . '">' . tep_image_button('button_cancel.png', IMAGE_CANCEL) . '</a>');
      break;
    case 'adjust':
      $heading[] = array('text' => '<b>Ajustar puntos</b>');

      $contents = array('form' => tep_draw_form('points', FILENAME_CUSTOMERS_POINTS, tep_get_all_get_params(array('oID', 'action')) . 'cID=' . $cInfo->customers_id . '&action=adjustpoints'));
      $contents[] = array('text' => '<b>Ajustar puntos</b><br>');
      $value_field = TEXT_ADJUST_INTRO . '<br><br>Ajustar puntos:<br>'. tep_draw_input_field('points_to_aj', '' , 'onkeyup="validate(this)"');
      $contents[] = array('text' => $value_field);
      if (tep_not_null(POINTS_AUTO_EXPIRES)){
        $contents[] = array('text' => tep_draw_checkbox_field('set_exp', '', false) . ' ' . TEXT_SET_EXPIRE);
      }
      $contents[] = array('text' => tep_draw_hidden_field('customers_points_expires', $cInfo->customers_points_expires));

      $contents[] = array('align' => 'center', 'text' => '<br>' . tep_image_submit('button_adjust_points.png', BUTTON_TEXT_ADJUST_POINTS) . ' <a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS, tep_get_all_get_params(array('oID', 'action')) . 'cID=' . $cInfo->customers_id) . '">' . tep_image_button('button_cancel.png', IMAGE_CANCEL) . '</a>');
      break;
    case 'deletepoints':
      $heading[] = array('text' => '<b>' . $cInfo->customers_firstname . ' ' . $cInfo->customers_lastname . '</b>');

      $contents = array('form' => tep_draw_form('customers', FILENAME_CUSTOMERS_POINTS, tep_get_all_get_params(array('cID', 'action')) . 'cID=' . $cInfo->customers_id . '&action=delconfirm'));
      $value_field = '<b>'. TEXT_DELETE_POINTS . '</b><br>'. TEXT_DELETE_POINTS_LONG . '<br><br>' . TEXT_POINTS_TO_DELETE . '<br>'. tep_draw_input_field('points_to_delete', '' , 'onBlur="validate(this)"');
      $contents[] = array('text' => $value_field);
      $value_field = TEXT_COMMENT. '<br>'. tep_draw_input_field('comment', 0);
      $contents[] = array('text' => $value_field);
      $contents[] = array('text' => tep_draw_checkbox_field('queue_delete', '', true) . ' ' . TEXT_QUEUE_POINTS_TABLE);
      $contents[] = array('text' => tep_draw_checkbox_field('notify', '', true) . ' ' . TEXT_NOTIFY_CUSTOMER);
      if (tep_not_null(POINTS_AUTO_EXPIRES)){
        $contents[] = array('text' => tep_draw_checkbox_field('set_exp', '', true) . ' ' . TEXT_SET_EXPIRE);
      }
      $contents[] = array('text' => tep_draw_hidden_field('customers_firstname', $cInfo->customers_firstname) . tep_draw_hidden_field('customers_lastname', $cInfo->customers_lastname) . tep_draw_hidden_field('customers_gender', $cInfo->customers_gender) . tep_draw_hidden_field('customers_email_address', $cInfo->customers_email_address) . tep_draw_hidden_field('customers_shopping_points', $cInfo->customers_shopping_points) . tep_draw_hidden_field('customers_points_expires', $cInfo->customers_points_expires));

      $contents[] = array('align' => 'center', 'text' => '<br>' . tep_image_submit('button_delete_points.png', BUTTON_TEXT_DELETE_POINTS) . ' <a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS, tep_get_all_get_params(array('cID', 'action')) . 'cID=' . $cInfo->customers_id) . '">' . tep_image_button('button_cancel.png', IMAGE_CANCEL) . '</a>');
      break;
    case 'log': {
      $cID = (int) ($_GET['cID'] ?? 0);
      $heading[] = ['text' => '<b>Log de puntos</b>'];

      // Fix histórico: el while no tenía llaves, por lo que $contents[] se ejecutaba UNA sola vez
      //  fuera del bucle. Con llaves correctas se loguean todas las filas.
      $aLogs = tep_db_query('SELECT * FROM ' . TABLE_CUSTOMERS_POINTS_PENDING . ' WHERE customer_id = "' . $cID . '" ORDER BY date_added DESC LIMIT 200');
      while ($aLog = tep_db_fetch_array($aLogs)) {
          $sComment   = defined($aLog['points_comment']) ? constant($aLog['points_comment']) : $aLog['points_comment'];
          $contents[] = ['text' => 'Añadidos ' . $aLog['points_pending'] . ' punto/s del pedido <a href="' . tep_href_link(FILENAME_ORDERS, 'oID=' . (int) $aLog['orders_id'] . '&action=edit') . '">' . (int) $aLog['orders_id'] . '</a>. Comentario: ' . htmlspecialchars((string) $sComment)];
      }
      break;
    }
    default:
      if (isset($cInfo) && is_object($cInfo)) {
        $heading[] = array('text' => '<b>' . $cInfo->customers_firstname . ' ' . $cInfo->customers_lastname . '</b>');

    if ($cInfo->customers_shopping_points > 0) {
        $contents[] = array('align' => 'center', 'text' => '<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS, tep_get_all_get_params(array('cID', 'action')) . 'cID=' . $cInfo->customers_id . '&action=addpoints') . '">' . tep_image_button('button_add_points.png', BUTTON_TEXT_ADD_POINTS) . '</a> <a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS, tep_get_all_get_params(array('cID', 'action')) . 'cID=' . $cInfo->customers_id . '&action=deletepoints') . '">' . tep_image_button('button_delete_points.png', BUTTON_TEXT_DELETE_POINTS) . '</a> <a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS, tep_get_all_get_params(array('cID', 'action')) . 'cID=' . $cInfo->customers_id . '&action=adjust') . '">' . tep_image_button('button_adjust_points.png', BUTTON_TEXT_ADJUST_POINTS) . '</a> <a href="' . tep_href_link(FILENAME_ORDERS, 'cID=' . $cInfo->customers_id) . '">' . tep_image_button('button_orders.png', IMAGE_ORDERS) . '</a> <a href="' . tep_href_link(FILENAME_MAIL, 'selected_box=tools&customer=' . $cInfo->customers_email_address) . '">' . tep_image_button('button_email.png', IMAGE_EMAIL) . '</a> <a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS, tep_get_all_get_params(array('cID', 'action')) . 'cID=' . $cInfo->customers_id . '&action=log') . '">' . tep_image_button('button_log.png', 'Log') . '</a>');
     } else {
        $contents[] = array('text' => '<a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS, tep_get_all_get_params(array('cID', 'action')) . 'cID=' . $cInfo->customers_id . '&action=addpoints') . '">' . tep_image_button('button_add_points.png', BUTTON_TEXT_ADD_POINTS) . '</a> <a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS, tep_get_all_get_params(array('cID', 'action')) . 'cID=' . $cInfo->customers_id . '&action=adjust') . '">' . tep_image_button('button_adjust_points.png', BUTTON_TEXT_ADJUST_POINTS) . '</a> <a href="' . tep_href_link(FILENAME_CUSTOMERS, tep_get_all_get_params(array('cID', 'action')) . 'cID=' . $cInfo->customers_id . '&action=edit') . '">' . tep_image_button('button_edit.png', IMAGE_EDIT) . '</a> <a href="' . tep_href_link(FILENAME_ORDERS, 'cID=' . $cInfo->customers_id) . '">' . tep_image_button('button_orders.png', IMAGE_ORDERS) . '</a> <a href="' . tep_href_link(FILENAME_MAIL, 'selected_box=tools&customer=' . $cInfo->customers_email_address) . '">' . tep_image_button('button_email.png', IMAGE_EMAIL) . '</a> <a href="' . tep_href_link(FILENAME_CUSTOMERS_POINTS, tep_get_all_get_params(array('cID', 'action')) . 'cID=' . $cInfo->customers_id . '&action=log') . '">' . tep_image_button('button_log.png', 'Log') . '</a>');
       }
        $contents[] = array('text' => '<br>' . TEXT_INFO_NUMBER_OF_ORDERS . ' ' . $currencies->format($cInfo->ordersum));
        $contents[] = array('text' => TEXT_INFO_NUMBER_OF_PENDING . ' ' . number_format($cInfo->pending_total ?? 0, POINTS_DECIMAL_PLACES));
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

<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
