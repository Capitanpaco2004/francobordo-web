<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2010 osCommerce

  Released under the GNU General Public License
*/

  require('includes/application_top.php');

  require(DIR_WS_CLASSES . 'currencies.php');
  $currencies = new currencies();

  $orders_statuses = array();
  $orders_status_array = array();
  $orders_status_query = tep_db_query("select orders_status_id, orders_status_name from " . TABLE_ORDERS_STATUS . " where language_id = '" . (int)$languages_id . "'");
  while ($orders_status = tep_db_fetch_array($orders_status_query)) {
    $orders_statuses[] = array('id' => $orders_status['orders_status_id'],
                               'text' => $orders_status['orders_status_name']);
    $orders_status_array[$orders_status['orders_status_id']] = $orders_status['orders_status_name'];
  }

  $action = (isset($_GET['action']) ? $_GET['action'] : '');
  
  if (tep_not_null($action)) {
    switch ($action) {
      case 'update_order':
        $oID = tep_db_prepare_input($_GET['oID']);
        $status = tep_db_prepare_input($_POST['status']);
        $comments = tep_db_prepare_input($_POST['comments']);

        $order_updated = false;
        $check_status_query = tep_db_query("select customers_name, customers_email_address, orders_status, date_purchased from " . TABLE_ORDERS . " where orders_id = '" . (int)$oID . "'");
        $check_status = tep_db_fetch_array($check_status_query);

        if ( ($check_status['orders_status'] != $status) || tep_not_null($comments)) {
          tep_db_query("update " . TABLE_ORDERS . " set orders_status = '" . tep_db_input($status) . "', last_modified = now() where orders_id = '" . (int)$oID . "'");

          $customer_notified = '0';
          if (isset($_POST['notify']) && ($_POST['notify'] == 'on')) {
            $notify_comments = '';
            if (isset($_POST['notify_comments']) && ($_POST['notify_comments'] == 'on')) {
              $notify_comments = sprintf(EMAIL_TEXT_COMMENTS_UPDATE, $comments) . "\n\n";
            }

            $email = STORE_NAME . "\n" . EMAIL_SEPARATOR . "\n" . EMAIL_TEXT_ORDER_NUMBER . ' ' . $oID . "\n" . EMAIL_TEXT_INVOICE_URL . ' ' . tep_catalog_href_link(FILENAME_CATALOG_ACCOUNT_HISTORY_INFO, 'order_id=' . $oID, 'SSL') . "\n" . EMAIL_TEXT_DATE_ORDERED . ' ' . tep_date_long($check_status['date_purchased']) . "\n\n" . $notify_comments . sprintf(EMAIL_TEXT_STATUS_UPDATE, $orders_status_array[$status]);

            tep_mail($check_status['customers_name'], $check_status['customers_email_address'], EMAIL_TEXT_SUBJECT, $email, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);

            $customer_notified = '1';
          }

          tep_db_query("insert into " . TABLE_ORDERS_STATUS_HISTORY . " (orders_id, orders_status_id, date_added, customer_notified, comments) values ('" . (int)$oID . "', '" . tep_db_input($status) . "', now(), '" . tep_db_input($customer_notified) . "', '" . tep_db_input($comments)  . "')");

          $order_updated = true;
        }

        if ($order_updated == true) {
         $messageStack->add_session(SUCCESS_ORDER_UPDATED, 'success');
        } else {
          $messageStack->add_session(WARNING_ORDER_NOT_UPDATED, 'warning');
        }

        tep_redirect(tep_href_link('kiala_orders.php', tep_get_all_get_params(array('action')) . 'action=edit'));
        break;
      case 'deleteconfirm':
        $oID = tep_db_prepare_input($_GET['oID']);

        tep_remove_order($oID, $_POST['restock']);

        tep_redirect(tep_href_link('kiala_orders.php', tep_get_all_get_params(array('oID', 'action'))));
        break;
    }
  }

  if (($action == 'edit') && isset($_GET['oID'])) {
    $oID = tep_db_prepare_input($_GET['oID']);

    $orders_query = tep_db_query("select orders_id from " . TABLE_ORDERS . " where orders_id = '" . (int)$oID . "'");
    $order_exists = true;
    if (!tep_db_num_rows($orders_query)) {
      $order_exists = false;
      $messageStack->add(sprintf(ERROR_ORDER_DOES_NOT_EXIST, $oID), 'error');
    }
  }

  include(DIR_WS_CLASSES . 'order.php');
  
  include( THEME . 'html/header.php' );
?>

<link rel="stylesheet" type="text/css" href="../ext/jquery/ui/redmond/jquery-ui-1.8.22.css">
<script type="text/javascript" src="../ext/jquery/jquery-1.8.0.min.js"></script>
<script type="text/javascript" src="../ext/jquery/ui/jquery-ui-1.8.22.min.js"></script>

<script type="text/javascript">
// fix jQuery 1.8.0 and jQuery UI 1.8.22 bug with dialog buttons; http://bugs.jqueryui.com/ticket/8484
if ( $.attrFn ) { $.attrFn.text = true; }
</script>


<script type="text/javascript" src="../ext/flot/jquery.flot.js"></script>
<link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
<script type="text/javascript" src="includes/general.js"></script>

<script language="javascript" src="../ext/kialajs/shadowbox/shadowbox.js"></script>
<link rel="stylesheet" type="text/css" href="../ext/kialajs/shadowbox/shadowbox.css">
		<script type="text/javascript">
		<!--
			Shadowbox.init();
			function show_info(dest_country, kp_id) {
				var url = 'http://locateandselect.kiala.com/details?countryid=' + dest_country + '&language=' + dest_country + '&map=on&align=left&shortID=' + kp_id;
				Shadowbox.open({
					content:    "<iframe border='0' scrolling='no' width='630' height='450' src='" + url + "'></iframe>",
					player:     "html",
					title:      "Info",
					height:     455,
					width:      635
				});

			};

		-->
		</script>
<table border="0" width="100%" cellspacing="2" cellpadding="2">
  <tr>
    <td width="100%" valign="top">
    <table border="0" width="100%" cellspacing="0" cellpadding="2">
<?php
  if (($action == 'edit') && ($order_exists == true)) {
    $order = new order($oID);
?>
      <tr>
        <td width="100%"><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading">
				<table border="0" width="100%" cellspacing="0" cellpadding="0">
					<tr>
						<td class="pageHeading" align="left"><?php echo HEADING_TITLE; ?></td>
						<td align="right"><div id="dataExportCsv"></div></td>
					</tr>
				</table>
			</td>
            <td class="smallText" align="right"><?php echo tep_draw_button(IMAGE_ORDERS_INVOICE, 'document', tep_href_link(FILENAME_ORDERS_INVOICE, 'oID=' . $_GET['oID']), null, array('newwindow' => true)) . tep_draw_button(IMAGE_ORDERS_PACKINGSLIP, 'document', tep_href_link(FILENAME_ORDERS_PACKINGSLIP, 'oID=' . $_GET['oID']), null, array('newwindow' => true)) . tep_draw_button(IMAGE_BACK, 'triangle-1-w', tep_href_link('kiala_orders.php', tep_get_all_get_params(array('action')))); ?></td>
			<td class="smallText" align="right">Export CSV</td>
		  </tr>
        </table></td>
      </tr>
      <tr>
        <td><table width="100%" border="0" cellspacing="0" cellpadding="2">
          <tr>
            <td colspan="3"><?php echo tep_draw_separator(); ?></td>
          </tr>
          <tr>
            <td valign="top"><table width="100%" border="0" cellspacing="0" cellpadding="2">
              <tr>
                <td class="main" valign="top"><strong><?php echo ENTRY_CUSTOMER; ?></strong></td>
                <td class="main"><?php echo tep_address_format($order->customer['format_id'], $order->customer, 1, '', '<br />'); ?></td>
              </tr>
              <tr>
                <td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
              </tr>
              <tr>
                <td class="main"><strong><?php echo ENTRY_TELEPHONE_NUMBER; ?></strong></td>
                <td class="main"><?php echo $order->customer['telephone']; ?></td>
              </tr>
              <tr>
                <td class="main"><strong><?php echo ENTRY_EMAIL_ADDRESS; ?></strong></td>
                <td class="main"><?php echo '<a href="mailto:' . $order->customer['email_address'] . '"><u>' . $order->customer['email_address'] . '</u></a>'; ?></td>
              </tr>
            </table></td>
            <td valign="top"><table width="100%" border="0" cellspacing="0" cellpadding="2">
              <tr>
                <td class="main" valign="top"><strong><?php echo ENTRY_SHIPPING_ADDRESS; ?></strong></td>
                <td class="main"><?php echo tep_address_format($order->delivery['format_id'], $order->delivery, 1, '', '<br />'); ?></td>
              </tr>
            </table></td>
            <td valign="top"><table width="100%" border="0" cellspacing="0" cellpadding="2">
              <tr>
                <td class="main" valign="top"><strong><?php echo ENTRY_BILLING_ADDRESS; ?></strong></td>
                <td class="main"><?php echo tep_address_format($order->billing['format_id'], $order->billing, 1, '', '<br />'); ?></td>
              </tr>
            </table></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
      </tr>
      <tr>
        <td><table border="0" cellspacing="0" cellpadding="2">
          <tr>
            <td class="main"><strong><?php echo ENTRY_PAYMENT_METHOD; ?></strong></td>
            <td class="main"><?php echo $order->info['payment_method']; ?></td>
          </tr>
<?php
    if (tep_not_null($order->info['cc_type']) || tep_not_null($order->info['cc_owner']) || tep_not_null($order->info['cc_number'])) {
?>
          <tr>
            <td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
          </tr>
          <tr>
            <td class="main"><?php echo ENTRY_CREDIT_CARD_TYPE; ?></td>
            <td class="main"><?php echo $order->info['cc_type']; ?></td>
          </tr>
          <tr>
            <td class="main"><?php echo ENTRY_CREDIT_CARD_OWNER; ?></td>
            <td class="main"><?php echo $order->info['cc_owner']; ?></td>
          </tr>
          <tr>
            <td class="main"><?php echo ENTRY_CREDIT_CARD_NUMBER; ?></td>
            <td class="main"><?php echo $order->info['cc_number']; ?></td>
          </tr>
          <tr>
            <td class="main"><?php echo ENTRY_CREDIT_CARD_EXPIRES; ?></td>
            <td class="main"><?php echo $order->info['cc_expires']; ?></td>
          </tr>
<?php
    }
?>
        </table></td>
      </tr>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
      </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
          <tr class="dataTableHeadingRow">
            <td class="dataTableHeadingContent" colspan="2"><?php echo TABLE_HEADING_PRODUCTS; ?></td>
            <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_PRODUCTS_MODEL; ?></td>
            <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_TAX; ?></td>
            <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_PRICE_EXCLUDING_TAX; ?></td>
            <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_PRICE_INCLUDING_TAX; ?></td>
            <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_TOTAL_EXCLUDING_TAX; ?></td>
            <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_TOTAL_INCLUDING_TAX; ?></td>
          </tr>
<?php
    for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
      echo '          <tr class="dataTableRow">' . "\n" .
           '            <td class="dataTableContent" valign="top" align="right">' . $order->products[$i]['qty'] . '&nbsp;x</td>' . "\n" .
           '            <td class="dataTableContent" valign="top">' . $order->products[$i]['name'];

      if (isset($order->products[$i]['attributes']) && (sizeof($order->products[$i]['attributes']) > 0)) {
        for ($j = 0, $k = sizeof($order->products[$i]['attributes']); $j < $k; $j++) {
          echo '<br /><nobr><small>&nbsp;<i> - ' . $order->products[$i]['attributes'][$j]['option'] . ': ' . $order->products[$i]['attributes'][$j]['value'];
          if ($order->products[$i]['attributes'][$j]['price'] != '0') echo ' (' . $order->products[$i]['attributes'][$j]['prefix'] . $currencies->format($order->products[$i]['attributes'][$j]['price'] * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']) . ')';
          echo '</i></small></nobr>';
        }
      }

      echo '            </td>' . "\n" .
           '            <td class="dataTableContent" valign="top">' . $order->products[$i]['model'] . '</td>' . "\n" .
           '            <td class="dataTableContent" align="right" valign="top">' . tep_display_tax_value($order->products[$i]['tax']) . '%</td>' . "\n" .
           '            <td class="dataTableContent" align="right" valign="top"><strong>' . $currencies->format($order->products[$i]['final_price'], true, $order->info['currency'], $order->info['currency_value']) . '</strong></td>' . "\n" .
           '            <td class="dataTableContent" align="right" valign="top"><strong>' . $currencies->format(tep_add_tax($order->products[$i]['final_price'], $order->products[$i]['tax'], true), true, $order->info['currency'], $order->info['currency_value']) . '</strong></td>' . "\n" .
           '            <td class="dataTableContent" align="right" valign="top"><strong>' . $currencies->format($order->products[$i]['final_price'] * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']) . '</strong></td>' . "\n" .
           '            <td class="dataTableContent" align="right" valign="top"><strong>' . $currencies->format(tep_add_tax($order->products[$i]['final_price'], $order->products[$i]['tax'], true) * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']) . '</strong></td>' . "\n";
      echo '          </tr>' . "\n";
    }
?>
          <tr>
            <td align="right" colspan="8"><table border="0" cellspacing="0" cellpadding="2">
<?php
    for ($i = 0, $n = sizeof($order->totals); $i < $n; $i++) {
      echo '              <tr>' . "\n" .
           '                <td align="right" class="smallText">' . $order->totals[$i]['title'] . '</td>' . "\n" .
           '                <td align="right" class="smallText">' . $order->totals[$i]['text'] . '</td>' . "\n" .
           '              </tr>' . "\n";
    }
?>
            </table></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
      </tr>
      <tr>
        <td class="main"><table border="1" cellspacing="0" cellpadding="5">
          <tr>
            <td class="smallText" align="center"><strong><?php echo TABLE_HEADING_DATE_ADDED; ?></strong></td>
            <td class="smallText" align="center"><strong><?php echo TABLE_HEADING_CUSTOMER_NOTIFIED; ?></strong></td>
            <td class="smallText" align="center"><strong><?php echo TABLE_HEADING_STATUS; ?></strong></td>
            <td class="smallText" align="center"><strong><?php echo TABLE_HEADING_COMMENTS; ?></strong></td>
          </tr>
<?php
    $orders_history_query = tep_db_query("select orders_status_id, date_added, customer_notified, comments from " . TABLE_ORDERS_STATUS_HISTORY . " where orders_id = '" . tep_db_input($oID) . "' order by date_added");
    if (tep_db_num_rows($orders_history_query)) {
      while ($orders_history = tep_db_fetch_array($orders_history_query)) {
        echo '          <tr>' . "\n" .
             '            <td class="smallText" align="center">' . tep_datetime_short($orders_history['date_added']) . '</td>' . "\n" .
             '            <td class="smallText" align="center">';
        if ($orders_history['customer_notified'] == '1') {
          echo tep_image(DIR_WS_ICONS . 'tick.gif', ICON_TICK) . "</td>\n";
        } else {
          echo tep_image(DIR_WS_ICONS . 'cross.gif', ICON_CROSS) . "</td>\n";
        }
        echo '            <td class="smallText">' . $orders_status_array[$orders_history['orders_status_id']] . '</td>' . "\n" .
             '            <td class="smallText">' . nl2br(tep_db_output($orders_history['comments'])) . '&nbsp;</td>' . "\n" .
             '          </tr>' . "\n";
      }
    } else {
        echo '          <tr>' . "\n" .
             '            <td class="smallText" colspan="5">' . TEXT_NO_ORDER_HISTORY . '</td>' . "\n" .
             '          </tr>' . "\n";
    }
?>
        </table></td>
      </tr>
      <tr>
        <td class="main"><br /><strong><?php echo TABLE_HEADING_COMMENTS; ?></strong></td>
      </tr>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
      </tr>
      <tr><?php echo tep_draw_form('status', 'kiala_orders.php', tep_get_all_get_params(array('action')) . 'action=update_order'); ?>
        <td class="main"><?php echo tep_draw_textarea_field('comments', 'soft', '60', '5'); ?></td>
      </tr>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
      </tr>
      <tr>
        <td><table border="0" cellspacing="0" cellpadding="2">
          <tr>
            <td><table border="0" cellspacing="0" cellpadding="2">
              <tr>
                <td class="main"><strong><?php echo ENTRY_STATUS; ?></strong> <?php echo tep_draw_pull_down_menu('status', $orders_statuses, $order->info['orders_status']); ?></td>
              </tr>
              <tr>
                <td class="main"><strong><?php echo ENTRY_NOTIFY_CUSTOMER; ?></strong> <?php echo tep_draw_checkbox_field('notify', '', true); ?></td>
                <td class="main"><strong><?php echo ENTRY_NOTIFY_COMMENTS; ?></strong> <?php echo tep_draw_checkbox_field('notify_comments', '', true); ?></td>
              </tr>
            </table></td>
            <td class="smallText" valign="top"><?php echo tep_draw_button(IMAGE_UPDATE, 'disk', null, 'primary'); ?></td>
          </tr>
        </table></td>
      </form></tr>
<?php
  } else {
?>
      <tr>
        <td width="100%"><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading">
				<table border="0" width="100%" cellspacing="0" cellpadding="0">
					<tr>
						<td class="pageHeading" align="left"><?php echo HEADING_TITLE; ?></td>
						<td align="right"><div id="dataExportCsv"></div></td>
					</tr>
				</table>
			</td>
            <td align="right"><table border="0" width="100%" cellspacing="0" cellpadding="0">
              <tr><?php echo tep_draw_form('orders', 'kiala_orders.php', '', 'get'); ?>
                <td class="smallText" align="right"><?php echo HEADING_TITLE_SEARCH . ' ' . tep_draw_input_field('oID', '', 'size="12"') . tep_draw_hidden_field('action', 'edit'); ?></td>
              <?php echo tep_hide_session_id(); ?></form></tr>
              <tr><?php echo tep_draw_form('status', 'kiala_orders.php', '', 'get'); ?>
                <td class="smallText" align="right"><?php echo HEADING_TITLE_STATUS . ' ' . tep_draw_pull_down_menu('status', array_merge(array(array('id' => '', 'text' => TEXT_ALL_ORDERS)), $orders_statuses), '', 'onchange="this.form.submit();"'); ?></td>
              <?php echo tep_hide_session_id(); ?></form></tr>
            </table></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td>
		<?php
			echo tep_draw_button(CALL_WS, 'document');
			echo tep_draw_button(IMAGE_EDIT, 'document');
			echo tep_draw_button(IMAGE_ORDERS_INVOICE, 'document');
			echo tep_draw_button(IMAGE_ORDERS_PACKINGSLIP, 'document');
			echo tep_draw_button(IMAGE_DELETE, 'trash');
			
			echo tep_draw_button(CSV_EXPORT, 'document');
			echo tep_draw_button(UPS_EXPORT, 'document');
		?>
		</td>

		<table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr class="dataTableHeadingRow">
                <td class="dataTableHeadingContent"><input id="allcheckbox" type="checkbox"><?php echo TABLE_HEADING_CUSTOMERS; ?></td>
				<td class="dataTableHeadingContent"><?php echo TABLE_HEADING_KIALA_WS_CALL_STATUS; ?></td>
                <td class="dataTableHeadingContent" ><?php echo TABLE_HEADING_ORDER_TOTAL; ?></td>
                <td class="dataTableHeadingContent" ><?php echo TABLE_HEADING_DATE_PURCHASED; ?></td>
				<td class="dataTableHeadingContent" ><?php echo K_ID; ?></td>
                <td class="dataTableHeadingContent" ><?php echo TABLE_HEADING_STATUS; ?></td>
				<td class="dataTableHeadingContent" >osC order ID</td>
              </tr>
<?php
    if (isset($_GET['cID'])) {
      $cID = tep_db_prepare_input($_GET['cID']);
      $orders_query_raw = "select o.orders_id, o.customers_name, o.customers_id, o.delivery_name, o.delivery_country, o.payment_method, o.date_purchased, o.last_modified, o.currency, o.currency_value, s.orders_status_name, ot.text as order_total from " . TABLE_ORDERS . " o left join " . TABLE_ORDERS_TOTAL . " ot on (o.orders_id = ot.orders_id), " . TABLE_ORDERS_STATUS . " s where o.customers_id = '" . (int)$cID . "' and o.orders_status = s.orders_status_id and s.language_id = '" . (int)$languages_id . "' and ot.class = 'ot_total' and delivery_name like \"KIALAPOINT%\" order by orders_id DESC";
    } elseif (isset($_GET['status']) && is_numeric($_GET['status']) && ($_GET['status'] > 0)) {
      $status = tep_db_prepare_input($_GET['status']);
      $orders_query_raw = "select o.orders_id, o.customers_name, o.delivery_name, o.delivery_country, o.payment_method, o.date_purchased, o.last_modified, o.currency, o.currency_value, s.orders_status_name, ot.text as order_total from " . TABLE_ORDERS . " o left join " . TABLE_ORDERS_TOTAL . " ot on (o.orders_id = ot.orders_id), " . TABLE_ORDERS_STATUS . " s where o.orders_status = s.orders_status_id and s.language_id = '" . (int)$languages_id . "' and s.orders_status_id = '" . (int)$status . "' and ot.class = 'ot_total' and delivery_name like \"KIALAPOINT%\" order by o.orders_id DESC";
    } else {
      $orders_query_raw = "select o.orders_id, o.customers_name, o.delivery_name, o.delivery_country, o.payment_method, o.date_purchased, o.last_modified, o.currency, o.currency_value, s.orders_status_name, ot.text as order_total from " . TABLE_ORDERS . " o left join " . TABLE_ORDERS_TOTAL . " ot on (o.orders_id = ot.orders_id), " . TABLE_ORDERS_STATUS . " s where o.orders_status = s.orders_status_id and s.language_id = '" . (int)$languages_id . "' and ot.class = 'ot_total' and delivery_name like \"KIALAPOINT%\" order by o.orders_id DESC";
    }
    $orders_split = new splitPageResults($_GET['page'], MAX_DISPLAY_SEARCH_RESULTS, $orders_query_raw, $orders_query_numrows);
    $orders_query = tep_db_query($orders_query_raw);
    while ($orders = tep_db_fetch_array($orders_query)) {
    if ((!isset($_GET['oID']) || (isset($_GET['oID']) && ($_GET['oID'] == $orders['orders_id']))) && !isset($oInfo)) {
        $oInfo = new objectInfo($orders);
      }
	  
      echo '              <tr class="dataTableRow">' . "\n";
?>
                <td class="dataTableContent"><?php echo '<input type="checkbox" id="'.$orders['orders_id'].'" class="kiala_order"/><in<a href="' . tep_href_link('kiala_orders.php', tep_get_all_get_params(array('oID', 'action')) . 'oID=' . $orders['orders_id'] . '&action=edit') . '">' . tep_image(DIR_WS_ICONS . 'preview.gif', ICON_PREVIEW) . '</a>&nbsp;' . $orders['customers_name']; ?></td>
                <?php
					$select_qry = tep_db_query('select status from kiala_orders_status where id='.$orders["orders_id"]);
					$kiala_status = tep_db_fetch_array($select_qry);
					$country_array = array( 'Belgium' => 'BE' , 'Luxembourg' => 'LU', 'France' => 'FR' , 'Spain' => 'ES' , 'Netherlands' => 'NL');
					$country = $country_array[$orders['delivery_country']];
					if ($kiala_status['status']!='') {
						switch (MODULE_SHIPPING_KIALAPOINT_SENDER_COUNTRY) {
							case "BE" :
								$select_qry_dspid = tep_db_query('SELECT configuration_value FROM ' . TABLE_CONFIGURATION . ' WHERE `configuration_key` = "MODULE_SHIPPING_KIALAPOINT_DSPID_BE"');
							break;
							case "LU" :
								$select_qry_dspid = tep_db_query('SELECT configuration_value FROM ' . TABLE_CONFIGURATION . ' WHERE `configuration_key` = "MODULE_SHIPPING_KIALAPOINT_DSPID_LU"');
							break;
							case "FR" :
								$select_qry_dspid = tep_db_query('SELECT configuration_value FROM ' . TABLE_CONFIGURATION . ' WHERE `configuration_key` = "MODULE_SHIPPING_KIALAPOINT_DSPID_FR"');
							break;
							case "NL" :
								$select_qry_dspid = tep_db_query('SELECT configuration_value FROM ' . TABLE_CONFIGURATION . ' WHERE `configuration_key` = "MODULE_SHIPPING_KIALAPOINT_DSPID_NL"');
							break;
							case "ES" :
								$select_qry_dspid = tep_db_query('SELECT configuration_value FROM ' . TABLE_CONFIGURATION . ' WHERE `configuration_key` = "MODULE_SHIPPING_KIALAPOINT_DSPID_ES"');
							break;
						}
						$value_dspid = tep_db_fetch_array($select_qry_dspid);
						$dspid = $value_dspid['configuration_value'];
						
						$tabstatus = explode (':', $kiala_status['status']);
						$kiala_status['status'] = rtrim($tabstatus[0]) . ' : <a href="http://trackandtrace.kiala.com/search?countryid=' .$country. '&language=' .$country. '&dspid=' . $dspid. '&dspparcelid='. ltrim($tabstatus[1]) . '" target="_blank">' . ltrim($tabstatus[1]) . '</a>'; 
					}
				?>				
				<td class="dataTableContent" id="kialaws<?php echo $orders['orders_id'] ; ?>" > <?php echo $kiala_status['status'];?> </td>
				<td class="dataTableContent" ><?php echo strip_tags($orders['order_total']); ?></td>
                <td class="dataTableContent" ><?php echo tep_datetime_short($orders['date_purchased']); ?></td>
<?php
	$country_array = array( 'Belgium' => 'BE' , 'Luxembourg' => 'LU', 'France' => 'FR' , 'Spain' => 'ES' , 'Netherlands' => 'NL');
	$delivery_country_prefix = $country_array[$orders['delivery_country']];

	$kp_name = $orders['delivery_name'];
	if ($delivery_country_prefix != 'FR') {
		preg_match ( '#\((.*)\)#', $kp_name, $extract );
		$kp_id = $extract[1];
	} else {
		$tab_kp_name = explode(',', $kp_name);
		$kp_id = substr($tab_kp_name[0],11);
	}
?>
				<td class="dataTableContent" align="center"><a rel="shadowbox" onclick="show_info('<?php echo $delivery_country_prefix ."','". $kp_id; ?>');" onmouseover="JavaScript:this.style.cursor='pointer';"><?php echo $kp_id; ?></a></td>
                <td class="dataTableContent" id="status<?php echo $orders['orders_id'] ; ?>" ><?php echo $orders['orders_status_name']; ?></td>
				<td class="dataTableContent" align="center"><?php echo $orders['orders_id'] ; ?></td>
			</tr>
<?php
    }
?>
              <tr>
                <td colspan="7"><table border="0" width="100%" cellspacing="0" cellpadding="2">
                  <tr>
                    <td class="smallText" valign="top"><?php echo $orders_split->display_count($orders_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, $_GET['page'], TEXT_DISPLAY_NUMBER_OF_ORDERS); ?></td>
                    <td class="smallText" align="right"><?php echo $orders_split->display_links($orders_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, MAX_DISPLAY_PAGE_LINKS, $_GET['page'], tep_get_all_get_params(array('page', 'oID', 'action'))); ?></td>
                  </tr>
                </table></td>
              </tr>
            </table></td>
<?php
  $heading = array();
  $contents = array();

  if ( (tep_not_null($heading)) && (tep_not_null($contents)) ) {
    echo '            <td width="25%" valign="top">' . "\n";

    $box = new box;
    echo $box->infoBox($heading, $contents);

    echo '            </td>' . "\n";
  }
?>
          </tr>
        </table></td>
      </tr>
<?php
  }
?>
    </table>

<script type="text/javascript">
	$.removeFromArray = function(value, arr) {
		return jQuery.grep(arr, function(elem, index) {
			return elem !== value;
		});
	};
	
	function confirmDelete(n) {
		return conf = confirm("Are you sure you wish to delete this entry? ("+n+" entrie(s))")
	}

	$('document').ready(function() {
		//the array containg the chosen orders - checkboxes
		var selected_checkboxes = new Array();
		
		// disable all buttons at the startup of the page
		if (selected_checkboxes.length == 0) {
			$('#tdb1, #tdb2, #tdb3, #tdb4, #tdb5, #tdb6, #tdb7').attr('disabled', 'true');
		}
		
		function addCheckboxToArray(element){
			if ($(element).is(':checked')) {
				var bol = jQuery.grep(selected_checkboxes, function(e){return e == $(element).attr('id');});
				if (bol == "") {
					selected_checkboxes.push($(element).attr('id'));
				}
			} else {
				selected_checkboxes = $.removeFromArray($(element).attr('id'),selected_checkboxes);
			}
			
			// disable/enable some buttons at checkbox events
			if (selected_checkboxes.length == 0) {
				$('#tdb1, #tdb2, #tdb3, #tdb4, #tdb5, #tdb6, #tdb7').attr('disabled', 'true');
				$('#tdb1, #tdb2, #tdb3, #tdb4, #tdb5, #tdb6, #tdb7').css('color', '');
			} else if (selected_checkboxes.length == 1) {
				$('#tdb1, #tdb2, #tdb3, #tdb4, #tdb5, #tdb6, #tdb7').removeAttr('disabled');
				$('#tdb1, #tdb2, #tdb3, #tdb4, #tdb5, #tdb6, #tdb7').css('color', 'red');
			} else {
				$('#tdb2, #tdb3, #tdb4').attr('disabled', 'true');
				$('#tdb2, #tdb3, #tdb4').css('color', '');
			}
		}
		
		//check-uncheck all checkboxes
		$('#allcheckbox').click(function() { 
			var cases = $('input[class=kiala_order]'); 
			if(this.checked){
				cases.attr('checked', true);
				 $(cases).each(function(){
					addCheckboxToArray(this);
				 });
			} else {
				cases.attr('checked', false);
				$(cases).each(function(){
					addCheckboxToArray(this);
				 });
			}   
		});
		
		//checkboxes click events
		$('.kiala_order').bind('click',function() {
			addCheckboxToArray(this);
			$('#allcheckbox').attr('checked', false);
		});
		
		<?php
			$protocol = strtolower(substr($_SERVER["SERVER_PROTOCOL"],0,5))=='https'?'https':'http';
			$host = $protocol.'://'.$_SERVER["SERVER_NAME"].':'.$_SERVER["SERVER_PORT"].DIR_WS_CATALOG;
		?>
		
		//call kiala webservice button
		$('#tdb1').click(function(){
			$(selected_checkboxes).each(function(value){
				url = '<?php echo $host; ?>admin/kiala_ws.php?oID='+selected_checkboxes[value];
				$('.dataTableContent[id=kialaws'+selected_checkboxes[value]+']').html('<img src="<?php echo DIR_WS_CATALOG;?>/admin/images/kiala_loading.gif"> Sending order to Kiala...');
				$.ajax({
					type: "POST",
					url: url,
					async: false,
					cache: false,
				error:function(msg){
					$('.dataTableContent[id=kialaws'+selected_checkboxes[value]+']').html('<font color="red"><b>'+msg+'</b></font>');
				},
				success:function(response){
					var errorNotfound = response.indexOf('error') == -1;
					var bigErrorNotFound = response.indexOf('ERROR') == -1;
					if (errorNotfound && bigErrorNotFound) {
						url2 = '<?php echo $host; ?>admin/kiala_update_status.php?oID='+selected_checkboxes[value]+'&tnumber='+response;
						$.ajax({
							type: "GET",
							url: url2,
							async: false,
							cache: false,
							error:function(msg){
								$('.dataTableContent[id=status'+selected_checkboxes[value]+']').html('<font color="red"><b>'+msg+'</b></font>');
							},
							success:function(response){				
							}
						});
						$('.dataTableContent[id=kialaws'+selected_checkboxes[value]+']').html(response);
					} else {
						$('.dataTableContent[id=kialaws'+selected_checkboxes[value]+']').html("<font color='red'><b>"+response+"</b></font>");
					}
				}});
			});
		});
		//edit button
		$('#tdb2').click(function(){
			window.location.replace('<?php echo $host; ?>admin/kiala_orders.php?oID='+selected_checkboxes[0]+'&action=edit');
		});
		//invoice button
		$('#tdb3').click(function(){
			window.location.replace('<?php echo $host; ?>admin/kiala_invoice.php?oID='+selected_checkboxes[0]);
		});
		//packing slip button
		$('#tdb4').click(function(){
			window.location.replace('<?php echo $host; ?>admin/kiala_packingslip.php?oID='+selected_checkboxes[0]);
		});
		//delete button
		$('#tdb5').click(function(){
			if (confirmDelete(selected_checkboxes.length)) {
				$(selected_checkboxes).each(function(value){
					url = '<?php echo $host; ?>admin/kiala_orders.php?oID='+selected_checkboxes[value]+'&action=deleteconfirm';
					$('.dataTableContent[id=status'+selected_checkboxes[value]+']').html('<img src="<?php echo DIR_WS_CATALOG;?>/admin/images/kiala_loading.gif"> Deleting order...');
					$.ajax({
						type: "POST",
						url: url,
						async: false,
						cache: false,
				    error:function(msg){
				    },
				    success:function(response){
						$('.dataTableContent[id=status'+selected_checkboxes[value]+']').html('<font color="red"><b>Deleted</b></font>');
				    }});
				});
			window.location.replace('<?php echo $host; ?>admin/kiala_orders.php');
			}
		});
		// export CSV button
		$('#tdb6').click(function(){
			var oIDs = '';
			$(selected_checkboxes).each(function(value){
				oIDs = oIDs + selected_checkboxes[value] + ',';
			});
			url = '<?php echo $host; ?>admin/kiala_export_csv.php?oID='+oIDs;
			$.ajax({
				type: "POST",
				url: url,
				async: false,
				cache: false,
			error:function(msg){
			},
			success:function(response){
				$("#dataExportCsv").html('<b><?php echo K_FILE; ?></b> : <a href="csvKiala/'+response+'">'+response+'</a>');
			}});
		});
		// UPS WorldShip
		$('#tdb7').click(function(){
			var oIDs = '';
			$(selected_checkboxes).each(function(value){
				oIDs = oIDs + selected_checkboxes[value] + ',';
			});
			url = '<?php echo $host; ?>admin/kiala_ups_worldship.php?oID='+oIDs;
			$.ajax({
				type: "POST",
				url: url,
				async: false,
				cache: false,
			error:function(msg){
			},
			success:function(response){
				$("#dataExportCsv").html('<b><?php echo K_FILE; ?></b> : <a href="csvKiala/'+response+'">'+response+'</a>');
			}});
		});
	});
</script>

</td>
<!-- body_text_eof //-->
  </tr>
</table>

<?php require(THEME . 'html/footer.php'); ?>
<script>
  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
  m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
  })(window,document,'script','//www.google-analytics.com/analytics.js','ga');
  ga('create', 'UA-41006271-3', 'kiala.com');
  ga('send', 'pageview');
</script>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>