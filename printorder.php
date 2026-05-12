<?php
/*
  $Id: printorder.php,v 1.1 2003/01 xaglo

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2002 osCommerce

  Released under the GNU General Public License
*/

  require('includes/application_top.php');

if (tep_session_is_registered('noaccount')){

 }else if (!tep_session_is_registered('customer_id')){
    $navigation->set_snapshot();
    tep_redirect(tep_href_link(FILENAME_LOGIN, '', 'SSL'));
  }

  $customer_number_query = tep_db_query("select customers_id from " . TABLE_ORDERS . " where orders_id = '". tep_db_input(tep_db_prepare_input($_GET['order_id'])) . "'");
  $customer_number = tep_db_fetch_array($customer_number_query);
  $invoice_serial_query = tep_db_query("select invoice_serial from " . TABLE_ORDERS . " where orders_id = '". tep_db_input(tep_db_prepare_input($_GET['order_id'])) . "'");
  $invoice_serial = tep_db_fetch_array($invoice_serial_query);
  $invoice_number_query = tep_db_query("select invoice_number from " . TABLE_ORDERS . " where orders_id = '". tep_db_input(tep_db_prepare_input($_GET['order_id'])) . "'");
  $invoice_number = tep_db_fetch_array($invoice_number_query);


  $payment_info_query = tep_db_query("select payment_info from " . TABLE_ORDERS . " where orders_id = '". tep_db_input(tep_db_prepare_input($_GET['order_id'])) . "'");
  $payment_info = tep_db_fetch_array($payment_info_query);
  $payment_info = $payment_info['payment_info'];

  require(DIR_WS_LANGUAGES . $language . '/' . 'printorder.php');

  require(DIR_WS_CLASSES . 'order.php');
  $order = new order($_GET['order_id']);

?>
<!doctype html public "-//W3C//DTD HTML 4.01 Transitional//EN">
<html <?php echo HTML_PARAMS; ?>>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>">
<title><?php echo ' A' . $_GET['order_id']; ?></title>
<base href="<?php echo (getenv('HTTPS') == 'on' ? HTTPS_SERVER : HTTP_SERVER) . DIR_WS_CATALOG; ?>">
<style>

a:link {
    color: #FF9900;
    font-family: Verdana,Arial,sans-serif;
    font-size: 10px;
    font-weight: normal;
}
a:hover {
    color: #0000FF;
    font-family: Verdana,Arial,sans-serif;
    font-size: 12px;
    font-weight: bold;
}
body {
    background-color: #FFFFFF;
    color: #000000;
    margin: 0;
}
.pageHeading {
    color: #727272;
    font-family: Verdana,Arial,sans-serif;
    font-size: 14px;
    font-weight: bold;
}
.dataTableHeadingRow {
    background-color: #C9C9C9;
}
.dataTableHeadingContent {
    color: #000000;
    font-family: Verdana,Arial,sans-serif;
    font-size: 10px;
    font-weight: bold;
}
.dataTableRow {
    background-color: #F0F1F1;
}
.dataTableRowSelected {
    background-color: #DEE4E8;
}
.dataTableRowOver {
    background-color: #FFFFFF;
}
.dataTableContent {
    color: #000000;
    font-family: Verdana,Arial,sans-serif;
    font-size: 10px;
}
.attributes-odd {
    background-color: #F4F7FD;
}
.attributes-even {
    background-color: #FFFFFF;
}
.specialPrice {
    color: #FF0000;
}
.oldPrice {
    text-decoration: line-through;
}
.fieldRequired {
    color: #FF0000;
    font-family: Verdana,Arial,sans-serif;
    font-size: 10px;
}
.smallText {
    font-family: Verdana,Arial,sans-serif;
    font-size: 10px;
}
.main {
    font-family: Verdana,Arial,sans-serif;
    font-size: 12px;
}
.titleHeading {
    color: #727272;
    font-family: Verdana,Arial,sans-serif;
    font-size: 18px;
    font-weight: bold;
}

</style>
</head>
<body marginwidth="10" marginheight="10" topmargin="10" bottommargin="10" leftmargin="10" rightmargin="10">


<!-- body_text //-->
<table width="600" border="0" align="center" cellpadding="2" cellspacing="0">
  <tr>
    <td align="center" class="main"><table align="center" width="100%" border="0" cellspacing="0" cellpadding="5">
      <tr>
        <td valign="top" align="left" class="main">
  </td>
        <td align="right" valign="bottom" class="main"><script language="JavaScript">
if (window.print) {
    document.write('<a href="javascript:;" onClick="javascript:window.print()"><?php echo tep_image_button('button_print.gif', 'Imprimir pedido'); ?></a></center>');
  }
  else document.write ('<h2><?php echo IMAGE_BUTTON_PRINT; ?></h2>')
        </script>
		</td>
      </tr>
    </table></td>
  </tr>
  <tr align="left">
    <td class="titleHeading"><?php echo tep_draw_separator('pixel_trans.gif', '1', '25'); ?></td>
  </tr>
  <tr>
    <td><table border="0" align="center" width="100%" cellspacing="0" cellpadding="0">
      <tr>
        <td><table border="0" align="center" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="main"><?php echo '<b>' . nl2br(STORE_NAME_ADDRESS). '</b> '; ?></td>
            <td class="pageHeading" align="right"><img src="theme/web/logo-trans.png"/></td>
          </tr>
          <tr>
            <td colspan="2" align="center" class="titleHeading"><b><?php echo TITLE_PRINT_ORDER . (int)$_GET['order_id']; ?></b></td>
          </tr>
          <tr align="left">
            <td colspan="2" class="titleHeading"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
          </tr>
        </table></td>
      </tr>
    </table></td>
  </tr>
<?php
if ($customer_number['customers_id'] == $customer_id) {
?>
  <tr>
    <td align="left" class="main"><table width="100%" border="0" cellspacing="0" cellpadding="2">
      <tr>
        <td class="main"><?php echo '<b>' . ENTRY_PAYMENT_METHOD . '</b> ' . $order->info['payment_method']; ?></td>
      </tr>
      <tr>
        <td class="main">
		<?php
			{
				$sPayment = '';
			
				switch( $order->info['payment_method'] )
				{
					case 'Ingreso o Transferencia Bancaria':
						$sPayment = 'transferencia';
					break;
					
					case 'PayPal (+3%)':
						$sPayment = 'paypal_ipn';
					break;

					case 'Tarjeta de crédito. (+2%)':
						$sPayment = 'bbva';
					break;
				}
			
				if( $sPayment != '' )
				{
					require(DIR_WS_CLASSES . 'payment.php');
					$payment_modules = new payment($sPayment);

					if( is_array( $payment_modules->modules ) )
					{
						echo '<div style="border: 1px solid rgb(201, 201, 201); background: none repeat scroll 0% 0% rgb(240, 241, 241); margin: 10px 0px; font-style: italic; line-height: 24px; padding: 10px;">';
							if( $confirmation = $payment_modules->confirmation() )
							{
								echo $confirmation['title'];
								for( $i=0, $n=sizeof($confirmation['fields']); $i<$n; $i++ )
								{
									echo '<p><strong>' . $confirmation['fields'][$i]['title'] . '</strong></p>';
									echo '<p>' . $confirmation['fields'][$i]['field'] . '</p>';
								}
							}
						echo '</div>';
					}
				}
			}
		?>
		</td>
      </tr>
    </table></td>
  </tr>
  <tr>
    <td class="main"><?php echo '<b>' . ENTRY_DATE_PURCHASED . '</b> ' . $order->info['date_purchased']; ?></td>
  </tr>
  <tr>
    <td align="center"><table align="center" width="100%" border="0" cellspacing="0" cellpadding="2">
      <tr>
        <td align="center" valign="top"><table align="center" width="100%" border="0" cellspacing="0" cellpadding="1" >
          <tr>
            <td align="center" valign="top"><table align="center" width="100%" border="0" cellspacing="0" cellpadding="2">
              <tr class="dataTableHeadingRow">
                <td class="dataTableHeadingContent"><b><?php echo ENTRY_SOLD_TO; ?></b></td>
              </tr>
              <tr class="dataTableRow">
                <td class="dataTableContent"><?php echo tep_address_format($order->customer['format_id'], $order->customer, 1, '&nbsp;', '<br>'); ?></td>
              </tr>
            </table></td>
          </tr>
        </table></td>
        <td align="center" valign="top"><table align="center" width="100%" border="0" cellspacing="0" cellpadding="1" >
          <tr>
            <td align="center" valign="top"><table align="center" width="100%" border="0" cellspacing="0" cellpadding="2">
              <tr class="dataTableHeadingRow">
                <td class="dataTableHeadingContent"><b><?php echo ENTRY_SHIP_TO; ?></b></td>
              </tr>
              <tr class="dataTableRow">
                <td class="dataTableContent"><?php echo tep_address_format($order->delivery['format_id'], $order->delivery, 1, '&nbsp;', '<br>'); ?></td>
              </tr>
            </table></td>
          </tr>
        </table></td>
      </tr>
    </table></td>
  </tr>
  <tr>
    <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
  </tr>
  <tr>
    <td><table border="0" width="100%" cellspacing="0" cellpadding="1" >
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
          <tr class="dataTableHeadingRow">
            <td class="dataTableHeadingContent" colspan="2"><?php echo TABLE_HEADING_PRODUCTS; ?></td>
            <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_PRODUCTS_MODEL; ?></td>
            <td class="dataTableHeadingContent" align="right"><?php ?></td>
            <td class="dataTableHeadingContent" align="right">Unidad</td>
            <td class="dataTableHeadingContent" align="right">Total</td>
            <td class="dataTableHeadingContent" align="right">Total</td>
          </tr>
        <?php
    for ($i = 0, $n = sizeof($order->products); $i < $n; $i++) {
      echo '      <tr class="dataTableRow">' . "\n" .
           '        <td class="dataTableContent" valign="top" align="right">' . $order->products[$i]['qty'] . '&nbsp;x</td>' . "\n" .
           '        <td class="dataTableContent" valign="top">' . $order->products[$i]['name'] . '<br>';

    if ( (isset($order->products[$i]['attributes'])) && (sizeof($order->products[$i]['attributes']) > 0) ) {
      for ($j=0, $n2=sizeof($order->products[$i]['attributes']); $j<$n2; $j++) {
        echo '<nobr><small>&nbsp;<i> - ' . $order->products[$i]['attributes'][$j]['option'] . ': ' . $order->products[$i]['attributes'][$j]['value'] . '</i><br></small></nobr>';
      }
    }

      echo '        </td>' . "\n" .
           '        <td class="dataTableContent" valign="top">' . $order->products[$i]['model'] . '</td>' . "\n";
      echo '        <td class="dataTableContent" align="right" valign="top">' . ' </td>' . "\n" .
           '        <td class="dataTableContent" align="right" valign="top"><b>' . $currencies->format($order->products[$i]['final_price'], true, $order->info['currency'], $order->info['currency_value']) . '</b></td>' . "\n" .
           '        <td class="dataTableContent" align="right" valign="top"><b>' . $currencies->format($order->products[$i]['final_price'] * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']) . '</b></td>' . "\n" .
           '        <td class="dataTableContent" align="right" valign="top"><b>' . $currencies->format(tep_add_tax($order->products[$i]['final_price'], $order->products[$i]['tax']) * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']) . '</b></td>' . "\n";
      echo '      </tr>' . "\n";
    }
?>
        </table></td>
      </tr>
    </table></td>
  </tr>
  <tr>
    <td align="right" colspan="7"><table border="0" cellspacing="0" cellpadding="2">
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <?php
  for ($i = 0, $n = sizeof($order->totals); $i < $n; $i++) {
    echo '          <tr>' . "\n" .
         '            <td align="right" class="smallText">' . $order->totals[$i]['title'] . '</td>' . "\n" .
         '            <td align="right" class="smallText">' . $order->totals[$i]['text'] . '</td>' . "\n" .
         '          </tr>' . "\n";
  }
?>
        </table></td>
      </tr>
    </table></td>
  </tr>
<?php
} else {
?>
  <tr>
    <td align="left" class="main"><?php echo ENTRY_ACCESS_ERROR; ?></td>
  </tr>
<?php
}
?>
</table>
<!-- body_text_eof //-->
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
