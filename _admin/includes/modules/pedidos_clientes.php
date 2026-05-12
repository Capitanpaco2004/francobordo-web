      <tr>
        <td class="formAreaTitle">Pedidos del Cliente</td>
      </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td valign="top"><table border="0" width="400px" cellspacing="0" cellpadding="2">
              <tr class="dataTableHeadingRow">
		        <td class="dataTableHeadingContent">N&uacute;mero Pedido</td>
                <td class="dataTableHeadingContent" align="right">Importe&nbsp;&nbsp;&nbsp;&nbsp;</td>
                <td class="dataTableHeadingContent" align="center">Fecha</td>
              </tr>
			  
        
<?php
    if (isset($_GET['cID'])) {
	    // BOF Separate Pricing Per Customer
      $cID = tep_db_prepare_input($_GET['cID']);
      $orders_query_raw = "select o.orders_id, o.customers_name, o.customers_id, o.payment_method, o.date_purchased, o.last_modified, o.currency, o.currency_value, s.orders_status_name, ot.text as order_total, cg.customers_group_name from " . TABLE_ORDERS . " o left join " . TABLE_ORDERS_TOTAL . " ot on (o.orders_id = ot.orders_id) left join " . TABLE_CUSTOMERS . " c on c.customers_id = o.customers_id left join " . TABLE_CUSTOMERS_GROUPS . " cg using(customers_group_id), " . TABLE_ORDERS_STATUS . " s where o.customers_id = '" . (int)$cID . "' and o.orders_status = s.orders_status_id and s.language_id = '" . (int)$languages_id . "' and ot.class = 'ot_total' order by orders_id DESC";
    } elseif (isset($_GET['status'])) {
      $status = tep_db_prepare_input($_GET['status']);
      $orders_query_raw = "select o.orders_id, o.customers_name, o.payment_method, o.date_purchased, o.last_modified, o.currency, o.currency_value, s.orders_status_name, ot.text as order_total, cg.customers_group_name from " . TABLE_ORDERS . " o left join " . TABLE_ORDERS_TOTAL . " ot on (o.orders_id = ot.orders_id) left join " . TABLE_CUSTOMERS . " c on c.customers_id = o.customers_id left join " . TABLE_CUSTOMERS_GROUPS . " cg using(customers_group_id), " . TABLE_ORDERS_STATUS . " s where o.orders_status = s.orders_status_id and s.language_id = '" . (int)$languages_id . "' and s.orders_status_id = '" . (int)$status . "' and ot.class = 'ot_total' order by o.orders_id DESC";
    } else {
      $orders_query_raw = "select o.orders_id, o.customers_name, o.payment_method, o.date_purchased, o.last_modified, o.currency, o.currency_value, s.orders_status_name, ot.text as order_total, cg.customers_group_name from " . TABLE_ORDERS . " o left join " . TABLE_ORDERS_TOTAL . " ot on (o.orders_id = ot.orders_id) left join " . TABLE_CUSTOMERS . " c on c.customers_id = o.customers_id left join " . TABLE_CUSTOMERS_GROUPS . " cg using(customers_group_id), " . TABLE_ORDERS_STATUS . " s where o.orders_status = s.orders_status_id and s.language_id = '" . (int)$languages_id . "' and ot.class = 'ot_total' order by o.orders_id DESC";
      // EOF Separate Pricing Per Customer
    }
    $orders_split = new splitPageResults($_GET['page'], 100, $orders_query_raw, $orders_query_numrows);
    $orders_query = tep_db_query($orders_query_raw);
    while ($orders = tep_db_fetch_array($orders_query)) {
    if ((!isset($_GET['oID']) || (isset($_GET['oID']) && ($_GET['oID'] == $orders['orders_id']))) && !isset($oInfo)) {
        $oInfo = new objectInfo($orders);
      }

      if (isset($oInfo) && is_object($oInfo) && ($orders['orders_id'] == $oInfo->orders_id)) {
        echo '              <tr class="dataTableRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href=\'' . tep_href_link(FILENAME_ORDERS, tep_get_all_get_params(array('oID')) . 'oID=' . $orders['orders_id']) . '\'">' . "\n";
      } else {
        echo '              <tr class="dataTableRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href=\'' . tep_href_link(FILENAME_ORDERS, tep_get_all_get_params(array('oID')) . 'oID=' . $orders['orders_id']) . '\'">' . "\n";
      }
?>


                <td class="dataTableContent"><?php echo $orders['orders_id']; ?></td>
                <td class="dataTableContent" align="right"><?php echo strip_tags($orders['order_total']); ?>&nbsp;&nbsp;&nbsp;&nbsp;</td>
                <td class="dataTableContent" align="right"><?php echo tep_datetime_short($orders['date_purchased']); ?></td>
              </tr>




<?php
    }
?>
			</table>
		</td>
	 </tr>
</table></td></tr>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
      </tr>