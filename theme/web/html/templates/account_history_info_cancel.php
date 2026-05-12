<?php
echo '<div class="ccTitle">' . TITLE_HISTORY_INFO_CANCEL_TITLE . '</div>';
echo '<div class="ccCnt">';
	echo $messageStack->show( array( 'text' => TITLE_HISTORY_INFO_CANCEL_CONFIRM, 'class' => 'wrng' ) );
?>

<div class="orderHistoryContent orderFlex">
	<div class="orderHistoryColumn">
		<div class="orderHistoryBox orderHistoryBoxResumen">
			<h3>Resumen del pedido</h3>
		<table class="Table Products">
			<?php if( isset($order->info['tax_groups']) && sizeof( $order->info['tax_groups'] ) > 1 )
			{
				echo '<tr>
					<td colspan="2">' . HEADING_PRODUCTS . '</td>
					<td align="right>' . HEADING_TAX . '</td>
					<td align="right>' . HEADING_TOTAL . '</td>
				</tr>';
			}

			// Includes
			include_once($_SERVER['DOCUMENT_ROOT'] . '/' . DIR_WS_CLASSES . 'rma.php');

			for( $i = 0, $n = sizeof( $order->products ); $i < $n; $i++ )
			{
				// Nuevo RMA
				$rma = new rma($_GET['order_id'], $order->products[$i]['id']);
				$return_link = $rma->showReturnButton();

				echo '<tr>
					<td class="Quantity">' . $order->products[$i]['qty'] . ' x</td>
					<td class="Name">' . $order->products[$i]['name'] . ' <small>(' . $order->products[$i]['model'] . ')' . '&nbsp;&nbsp;';
						if( isset( $order->products[$i]['attributes'] ) && sizeof( $order->products[$i]['attributes'] ) > 0 ) {
							for( $j = 0, $n2 = sizeof( $order->products[$i]['attributes'] ); $j < $n2; $j++ )
								echo '<br/> <i> - ' . $order->products[$i]['attributes'][$j]['option'] . ': ' . $order->products[$i]['attributes'][$j]['value'] . '</i>';
						}

						echo $return_link;

					echo '</td>';

					if( sizeof( $order->info['tax_groups'] ) > 1 )
						echo '<td class="Tax">' . tep_display_tax_value( $order->products[$i]['tax'] ) . '%</td>';

					echo '<td class="totalProduct">' . $currencies->format( tep_add_tax( $order->products[$i]['final_price'], $order->products[$i]['tax']) * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value'] ) . '</td>';
				echo '</tr>';
			}
			?>
			</table>
			<table class="Table Totals">
				<?php
				for( $i = 0, $n = sizeof( $order->totals ); $i < $n; $i++ ) {
					echo '<tr>
						<td class="Label">' . str_replace(' :',':',$order->totals[$i]['title']) . '</td>
						<td class="Total">' . $order->totals[$i]['text'] . '</td>
					</tr>';
				}
				?>
			</table>
		</div>
	</div>
</div>

<?php
	echo '<div class="tright" style="margin-top: 10px;">';
		echo '<a href="' . tep_href_link( FILENAME_ACCOUNT_HISTORY_INFO, 'order_id=' . (int)$_GET['order_id'], 'SSL' ) . '" class="button small ccbutton"><i class="fa fa-arrow-left"></i> ' . IMAGE_BUTTON_BACK . '</a>&nbsp;';
		echo '<a href="' . tep_href_link( FILENAME_ACCOUNT_HISTORY_INFO, 'order_id=' . (int)$_GET['order_id'] . '&action=cancelconfirm', 'SSL' ) . '" class="button small rojo ccbutton"><i class="fa fa-trash"></i> ' . TEXT_CANCEL_ORDER . '</a>';
	echo '</div>';
echo '</div>'
?>