<tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
          <tr>
            <td class="main"><div class="pghd"><?php echo TABLE_HEADING_PAYMENT_METHOD; ?></div></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="1" cellpadding="2" class="">
          <tr class="infoBoxContents">
            <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
<?php
  $selection = $payment_modules->selection();

  if (sizeof($selection) > 1) {
?>
              <tr>
                <td class="main" valign="top">
					<b><?php echo TEXT_SELECT_PAYMENT_METHOD; ?></b>
				</td>
              </tr>
<?php
  } else {
?>
              <tr>
                <td class="main" width="100%" colspan="2"><?php echo TEXT_ENTER_PAYMENT_INFORMATION; ?></td>
              </tr>
<?php
  }

  echo '<tr><td>';
$radio_buttons = 0;

	if (!$ie){ 
	  echo '<div class="pagos">';
	}else{
		echo '<div class="pagos_ie">';
  }

	// Paypal VZero
	if( class_exists( 'paypal_vzero' ) )
		$aPaypal = new paypal_vzero();

	for( $i = 0, $n = sizeof( $selection ); $i < $n; $i++ )
	{
		//if( $selection[$i]['id'] == 'redsys' && $customer_default_card_id != '' )
			//continue;

		if( $selection[$i]['id'] == 'redsys1' && $customer_default_card_id == '' )
			continue;

		if( ( $selection[$i]['id'] == $payment ) || ( $n == 1 ) )
			echo '<div id="defaultSelected" class="moduleRowSelected" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="selectRowEffect(this, ' . $radio_buttons . ')">' . "\n";
		else
			echo '<div class="moduleRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="selectRowEffect(this, ' . $radio_buttons . ')">' . "\n";
		
		echo '<span id="' . $selection[$i]['id'] . '">';
		echo '<strong>' . $selection[$i]['module'] . '</strong>';

		// Mostramos Vault de PayPal VZero
		if( $selection[$i]['id'] == 'paypal_vzero' )
			$aPaypal->showVault( true );

		if( $selection[$i]['id'] == 'redsys1' && $customer_default_card_id != '' )
		{
			// Obtenemos las tarjetas del cliente
			$sCards = tep_db_query( 'SELECT * FROM customers_cards WHERE customers_id = ' . (int)$customer_id . ' ORDER BY customers_cards_id' );

			echo '<select name="redsysc">';
			while( $aCard = tep_db_fetch_array( $sCards ) )
				echo '<option value="' . $aCard['customers_cards_id'] . '"' . ($aCard['customers_cards_id'] == $customer_default_card_id ? ' selected="selected"' : '') . '>' . ($aCard['customers_cards_name'] != '' ? $aCard['customers_cards_name'] : 'Tarjeta F.E: ' . substr( $aCard['customers_cards_expire'], 2) . '/' . substr( $aCard['customers_cards_expire'], 0, 2)) . '</option>';

				echo '<option value="-1">Añadir más tarjetas</option>';
			echo '</select>';

			//echo '<a style="display: block; font-weight: bold; color: rgb(0, 122, 181);" target="_blank" href="' . tep_href_link( FILENAME_INFORMATION, 'info_id=33' ) . '">+info</a>';
		}

		if( sizeof( $selection ) > 1 )
			echo tep_draw_radio_field( 'payment', $selection[$i]['id'], ($selection[$i]['id'] == $payment));
		else
			echo tep_draw_hidden_field('payment', $selection[$i]['id']);

		echo '</span>';
		
		if( isset( $selection[$i]['error'] ) )
			echo '<div class="mensaje">' . $selection[$i]['error'] . '</div>';
		elseif( isset( $selection[$i]['fields'] ) && is_array( $selection[$i]['fields'] ) ) 
			for( $j = 0, $n2 = sizeof( $selection[$i]['fields'] ); $j < $n2; $j++ )
				echo '<p>' . $selection[$i]['fields'][$j]['field'] . $selection[$i]['fields'][$j]['title'] . '</p>';
				
		$radio_buttons++;
		echo '</div>';
    }
  echo '</div>';

	// Mostramos Vault de PayPal VZero
	if( class_exists( 'paypal_vzero' ) )
		$aPaypal->showVault();

  echo '</td></tr>';

 /* $radio_buttons = 0;
  for ($i=0, $n=sizeof($selection); $i<$n; $i++) {
?>
              <tr>
                <td><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                <td colspan="2"><table border="0" width="100%" cellspacing="0" cellpadding="2">
<?php
    if ( ($selection[$i]['id'] == $payment) || ($n == 1) ) {
      echo '                  <tr id="defaultSelected" class="moduleRowSelected" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="selectRowEffect(this, ' . $radio_buttons . ')">' . "\n";
    } else {
      echo '                  <tr class="moduleRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="selectRowEffect(this, ' . $radio_buttons . ')">' . "\n";
    }
?>
                    <td width="10"><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                    <td class="main" colspan="3"><b><?php echo $selection[$i]['module']; ?></b></td>
                    <td class="main" align="right">
<?php
    if (sizeof($selection) > 1) {
      echo tep_draw_radio_field('payment', $selection[$i]['id']);
    } else {
      echo tep_draw_hidden_field('payment', $selection[$i]['id']);
    }
?>
                    </td>
                    <td width="10"><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                  </tr>
<?php
    if (isset($selection[$i]['error'])) {
?>
                  <tr>
                    <td width="10"><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                    <td class="main" colspan="4"><?php echo $selection[$i]['error']; ?></td>
                    <td width="10"><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                  </tr>
<?php
    } elseif (isset($selection[$i]['fields']) && is_array($selection[$i]['fields'])) {
?>
                  <tr>
                    <td width="10"><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                    <td colspan="4"><table border="0" cellspacing="0" cellpadding="2">
<?php
      for ($j=0, $n2=sizeof($selection[$i]['fields']); $j<$n2; $j++) {
?>
                      <tr>
                        <td width="10"><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                        <td class="main"><?php echo $selection[$i]['fields'][$j]['title']; ?></td>
                        <td><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                        <td class="main"><?php echo $selection[$i]['fields'][$j]['field']; ?></td>
                        <td width="10"><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                      </tr>
<?php
      }
?>
                    </table></td>
                    <td width="10"><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                  </tr>
<?php
    }
?>
                </table></td>
                <td><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
              </tr>
<?php
    $radio_buttons++;
  }*/
?>
            </table></td>
          </tr>
        </table></td>
      </tr>

       <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '100%', '10'); ?></td>
      </tr>