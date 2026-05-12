  <tr align-"right>
        <td align-"right>
		<div style="margin-top: 10px;" class="pghd"><?php echo CHECKOUT_CESTA_COMPRA ?></div>
		<table border="0" width="100%" cellspacing="1" cellpadding="2" align-"center"><tr  class="infoBoxcontents">
<td><table border="0" width="100%" cellspacing="1" cellpadding="2" align-"center" >
         
              
<?php
for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
    echo '          <tr>' . "\n" .
         '            <td class="main" align="right" valign="top" width="30">' . $order->products[$i]['qty'] . '&nbsp;x</td>' . "\n" .
         '            <td class="main" valign="top">' . $order->products[$i]['name'];

    if (STOCK_CHECK == 'true') {
      echo tep_check_stock($order->products[$i]['id'], $order->products[$i]['qty']);
    }

    if ( (isset($order->products[$i]['attributes'])) && (sizeof($order->products[$i]['attributes']) > 0) ) {
      for ($j=0, $n2=sizeof($order->products[$i]['attributes']); $j<$n2; $j++) {
        echo '<br><nobr><small>&nbsp;<i> - ' . $order->products[$i]['attributes'][$j]['option'] . ': ' . $order->products[$i]['attributes'][$j]['value'] . '</i></small></nobr>';
      }
    }

    echo '</td>' . "\n";

    if (sizeof($order->info['tax_groups']) > 1) echo '            <td class="main" valign="top" align="right">' . tep_display_tax_value($order->products[$i]['tax']) . '%</td>' . "\n";

    echo '          <td class="main" align="right" valign="top">' . $currencies->display_price($order->products[$i]['final_price'], $order->products[$i]['tax'], $order->products[$i]['qty']) . '</td>' . "\n" .
         '          </tr>' . "\n";


  }

 $products = $cart->get_products();
   
  

  
?>
</b></td></tr>
 </table>
	<div id="chtl">
		<span>Total:</span><?php echo  $currencies->format($cart->show_total()); ?>
	</div>
	
 </td></tr>
 </table></td>
      </tr>