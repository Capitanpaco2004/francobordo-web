<?php
use util\tools;

// Comprobamos si existe
if( !isset( $aOptionsInsertUser ) )
	$aOptionsInsertUser = array();

// Fecha estimada de entrega (lazy backfill si aún no está calculada)
$estimated_pretty = '';
if( isset( $insert_id ) && (int)$insert_id > 0 && file_exists( DIR_FS_CATALOG . DIR_WS_MODULES . 'delivery_estimate/delivery_estimate.php' ) ) {
	require_once( DIR_FS_CATALOG . DIR_WS_MODULES . 'delivery_estimate/delivery_estimate.php' );
	$de_row = \delivery_estimate::getCurrent( (int)$insert_id );
	if( $de_row && ! empty( $de_row['estimated_date'] ) && $de_row['estimated_date'] !== '0000-00-00' ) {
		$estimated_pretty = date( 'd/m/Y', strtotime( $de_row['estimated_date'] ) );
	}
}
?>

{% extends base.php %}

{% block content %}
<div style="text-align: left; color: #666;">
	<?php
	echo '<p style="font-size: 24px; line-height: 24px; padding: 0px; margin: 0px 0px 20px;">' . strip_tags( UHE_TEXT_DEAR ) . ' ' . $order->customer['firstname'] . ' ' . $order->customer['lastname'].'</p>';
	echo '<p style="font-size: 14px;line-height: 20px;padding: 0px;margin: 0px;">' . UHE_MESSAGE_GREETING . '</p>';
	?>
</div>

<br/>

<table align="center" bgcolor="#f5f5f5" cellpadding="0" cellspacing="0" style="border-collapse: collapse; border-radius: 5px; box-shadow: rgb(221, 221, 221) 0px 0px 5px;" width="100%">
	<tbody>
	<tr>
		<td align="center" height="10" style="color: transparent; font-size: 1px; height: 10px; line-height: 10px; padding: 0px 0px 0px 0px;"> &nbsp; </td>
	</tr>
	<tr>
		<td align="center" style="padding-bottom: 0px; padding-left: 0px; padding-right: 0px; padding-top: 0px;">
			<table width="100%" align="left" border="0" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
				<tbody>
				<tr>
					<td align="center" style="line-height: 100%; padding-bottom: 0px; padding-left: 20px; padding-right: 20px; padding-top: 0px;">
						<table width="100%" align="left" border="0" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
							<tr>
								<td>
									<table width="100%" align="left" border="0" cellpadding="0" cellspacing="0" >
										<tr>
											<td width="220"><font face="Arial,sans-serif" style="font-size: 14px;line-height: 20px;"><strong><?php echo UHE_TEXT_ORDER_NUMBER; ?></strong></font></td>
											<td><font face="Arial,sans-serif" style="font-size: 14px;line-height: 20px;"><?php echo $insert_id; ?>&nbsp;&nbsp;<a href="<?php echo tep_href_link(FILENAME_ACCOUNT_HISTORY_INFO, 'order_id=' . $insert_id, 'SSL', false); ?>" style="color: #2bb0e2; text-decoration: none; font-size: 13px;">(<?php echo UHE_TEXT_INVOICE_URL; ?>)</a></font></td>
										</tr>
										<tr>
											<td><font face="Arial,sans-serif" style="font-size: 14px;line-height: 20px;"><strong><?php echo UHE_TEXT_DATE_ORDERED; ?></strong></font></td>
											<td><font face="Arial,sans-serif" style="font-size: 14px;line-height: 20px;"><?php echo date('d/m/Y'); ?></font></td>
										</tr>
										<?php if( $estimated_pretty != '' ): ?>
										<tr>
											<td valign="middle" bgcolor="#e6f4fb" style="background: #e6f4fb; padding: 6px 6px 6px 6px;"><font face="Arial,sans-serif" style="font-size: 14px;line-height: 22px; color: #1c6f96;"><strong><?php echo UHE_TEXT_ESTIMATED_DELIVERY_TITLE; ?>:</strong></font></td>
											<td valign="middle" bgcolor="#e6f4fb" style="background: #e6f4fb; padding: 6px;"><font face="Arial,sans-serif" style="font-size: 15px;line-height: 22px; color: #1c6f96;"><strong><?php echo $estimated_pretty; ?></strong></font></td>
										</tr>
										<?php endif; ?>
									</table>
								</td>
							</tr>
						</table>
					</td>
				</tr>
				</tbody>
			</table>
		</td>
	</tr>
	<tr>
		<td align="center" height="10" style="color: transparent; font-size: 0px; height: 10px; line-height: 10px; padding: 0px 0px 0px 0px;"> &nbsp; </td>
	</tr>
	</tbody>
</table>

<table cellpadding="0" cellspacing="0"><tr><td align="left" height="20" style="color: transparent; font-size: 1px; height: 20px; line-height: 20px; padding: 0px 0px 0px 0px;"> &nbsp; </td></tr></table>

<?php if( $order->info['comments'] != '' ): ?>
	<table align="left" bgcolor="#f5f5f5" cellpadding="0" cellspacing="0" style="border-collapse: collapse; border-radius: 5px; box-shadow: rgb(221, 221, 221) 0px 0px 5px;" width="100%">
		<tbody>
		<tr>
			<td align="left" height="10" style="color: transparent; font-size: 1px; height: 10px; line-height: 10px; padding: 0px 0px 0px 0px;"> &nbsp; </td>
		</tr>
		<tr>
			<td align="left" style="padding-bottom: 0px; padding-left: 0px; padding-right: 0px; padding-top: 0px;">
				<table width="100%" align="left" border="0" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
					<tbody>
					<tr>
						<td align="left" style="line-height: 100%; padding-bottom: 0px; padding-left: 20px; padding-right: 20px; padding-top: 0px;">
							<span style="font-family: Arial,sans-serif; font-size: 14px;line-height: 20px;"><strong><?php echo UHE_TEXT_COMMENTS; ?></strong></span>
							<table cellpadding="0" cellspacing="0"><tr><td align="left" height="10" style="color: transparent; font-size: 1px; height: 10px; line-height: 10px; padding: 0px 0px 0px 0px;"> &nbsp; </td></tr></table>
							<span style="font-family: Arial,sans-serif; font-size: 14px;line-height: 20px;"><?php echo str_replace( array("\r\n", "\n\r", "\n", "\r", "\t") , '<br />', preg_replace( '/\n$/', '', tep_db_output($order->info['comments']))); ?></span>
						</td>
					</tr>
					</tbody>
				</table>
			</td>
		</tr>
		<tr>
			<td align="left" height="10" style="color: transparent; font-size: 0px; height: 10px; line-height: 10px; padding: 0px 0px 0px 0px;"> &nbsp; </td>
		</tr>
		</tbody>
	</table>

	<table cellpadding="0" cellspacing="0"><tr><td align="left" height="20" style="color: transparent; font-size: 1px; height: 20px; line-height: 20px; padding: 0px 0px 0px 0px;"> &nbsp; </td></tr></table>
<?php endif; ?>

<table align="center" bgcolor="#ffffff" border="0" cellpadding="0" cellspacing="0" style="border-collapse: collapse; min-width: 280px; max-width: 100%;" width="100%">
	<tbody>
	<tr>
		<td align="center" style="padding-bottom: 0px; padding-top: 0px;">
			<table align="center" bgcolor="#f5f5f5" cellpadding="0" cellspacing="0" style="border-collapse: collapse; border-radius: 5px; box-shadow: rgb(221, 221, 221) 0px 0px 5px;" width="100%">
				<tbody>
				<tr>
					<td align="center" height="10" style="color: transparent; font-size: 1px; height: 10px; line-height: 10px; padding: 0px 0px 0px 0px;"> &nbsp; </td>
				</tr>
				<?php
				$nProductosTotal = count( $order->products );

				foreach( $order->products as $nCont => $aProducto )
				{
					// Variables
					$sUrlProduct = tep_href_link( 'product_info.php', 'products_id=' . strtok($aProducto['id'], '{') );

					echo '<tr>';
					echo '<td align="center" style="padding-bottom: 0px; padding-left: 0px; padding-right: 0px; padding-top: 0px;">';
					echo '<table width="100%" align="left" border="0" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">';
					echo '<tbody>';
					echo '<tr>';
					echo '<td align="center" style="line-height: 100%; padding-bottom: 0px; padding-left: 20px; padding-right: 20px; padding-top: 0px;">';
					echo '<table width="100%" align="left" border="0" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">';
					echo '<tbody>';
					echo '<tr>';
					echo '<td width="100" align="center" valign="top" bgcolor="#FFFFFF" style="background-color: #FFFFFF;"><a style="display: block; background-color: #FFFFFF;" href="' . $sUrlProduct . '"><img alt="" border="0" height="auto" src="' . $this->url . '/product_thumb.php?img=' . DIR_WS_IMAGES . 'productos/' . $aProducto['image'] . '&amp;w=100&amp;h=100&amp;bg=white" style="display: block; max-width: 150px; min-width: 100px; background-color: #FFFFFF;" width="100"/></a></td>';
					echo '<td width="20"></td>';
					echo '<td valign="top">';
					echo '<table width="100%" align="left" border="0" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">';
					echo '<tbody>';
					echo '<tr>';
					echo '<td align="left" style="padding-bottom: 10px; padding-left: 0px; padding-right: 10px; padding-top: 0px;">';
					echo '<table align="left" border="0" cellpadding="0" cellspacing="0" style="border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt;">';
					echo '<tbody>';
					echo '<tr>';
					echo '<td align="left" style="color: #333333; font-size: 13px; line-height: 16px; padding-bottom: 0px; padding-left: 0px; padding-right: 0px; padding-top: 0px;"><a href="' . $sUrlProduct . '" style="font-family: Arial,sans-serif; font-size: 14px;line-height: 20px; color: #333; text-decoration: none;" text-decoration: none;">';
					echo $aProducto['name'];

					if( $aProducto['model'] != '' )
						echo ' <small style="background-color: #CCC; color: #575757; padding-left: 5px; padding-right: 5px;">' . $aProducto['model'] . '</small>';

					if( isset( $aProducto['attributes'] ) )
					{

						foreach( $aProducto['attributes'] as $aAttr )
						{
							$aDatos = tep_db_query( 'select popt.products_options_name, poval.products_options_values_name
																																			 from products_options popt
																																			 inner join  products_attributes pa ON(pa.options_id = popt.products_options_id)
																																			 inner join products_options_values poval ON(pa.options_values_id = poval.products_options_values_id)
																																			 where ' . (!in_array( (int)$aAttr['option_id'], $aOptionsInsertUser ) ? "pa.options_values_id = '" . (int)$aAttr['value_id'] . "' and " : "") . ' pa.products_id = "' . $aProducto['id'] . '"
																																			 and pa.options_id = "' . $aAttr['option_id'] . '" and popt.language_id = "' . $languages_id . '" and poval.language_id = "' . $languages_id . '"' );


							$aDato = tep_db_fetch_array( $aDatos );

							// Sampedro: Inicio, Atributos por tipo //
							if( in_array( (int)$aAttr['option_id'], $aOptionsInsertUser ) )
								$aDato['products_options_values_name'] = nl2br( urldecode($aAttr['option_id']['value_id']) );
							// Sampedro: Fin, Atributos por tipo //

							echo '<br /><em style="font-size:12px; color: #777777;">&nbsp;&nbsp;&nbsp; - '. $aDato['products_options_name'] . ':&nbsp;' . $aDato['products_options_values_name'] .'</em>';
						}
					}
					echo '</a></td>';
					echo '</tr>';
					echo '</tbody>';
					echo '</table>';
					echo '</td>';
					echo '</tr>';
					echo '<tr>';
					echo '<td align="left" style="color: rgb(119, 119, 119); font-family: Arial,sans-serif; font-size: 13px; line-height: 18px; padding: 0px 10px 5px 0px;"><font color="#777777" style="font-size: 13px;">' . UHE_TEXT_PRODUCTS_PRICE . ': ' . $currencies->display_price($aProducto['final_price'], $aProducto['tax'], 1) . '</font></td>';
					echo '</tr>';
					echo '<tr>';
					echo '<td align="left" style="color: rgb(119, 119, 119); font-family: Arial,sans-serif; font-size: 13px; line-height: 18px; padding: 0px 10px 5px 0px;"><font color="#777777" style="font-size: 13px;">' . UHE_TEXT_PRODUCTS_QTY . ': ' . $aProducto['qty'] . '</font></td>';
					echo '</tr>';
					echo '<tr>';
					echo '<td align="left" style="color: rgb(119, 119, 119); font-family: Arial,sans-serif; font-size: 13px; line-height: 18px; padding: 0px 10px 5px 0px;"><font color="#777777" style="font-size: 13px;">' . UHE_TEXT_PRODUCTS_TOTAL . ': ' . $currencies->display_price($aProducto['final_price'], $aProducto['tax'], $aProducto['qty']) . '</font></td>';
					echo '</tr>';
					echo '</tbody>';
					echo '</table>';
					echo '</td>';
					echo '</tr>';
					echo '</tbody>';
					echo '</table>';
					echo '</td>';
					echo '</tr>';
					echo '</tbody>';
					echo '</table>';
					echo '</td>';
					echo '</tr>';

					$nCont++;

					echo '<tr>';
					echo '<td align="center" height="10" style="color: transparent; font-size: 0px; height: 10px; line-height: 10px; padding: 0px 0px 0px 0px;"> &nbsp; </td>';
					echo '</tr>';

					if( $nCont >= $nProductosTotal )
						continue;

					echo '<tr>';
					echo '<td align="center" bgcolor="#eaeaea" height="1" style="color: transparent; font-size: 0px; height: 1px; line-height: 1px; padding: 0px 0px 0px 0px;"> &nbsp; </td>';
					echo '</tr>';
					echo '<tr>';
					echo '<td align="center" height="10" style="color: transparent; font-size: 0px; height: 10px; line-height: 10px; padding: 0px 0px 0px 0px;"> &nbsp; </td>';
					echo '</tr>';
				}
				?>
				<tr>
					<td align="center" bgcolor="#fafafa" height="20" style="background-color: #fafafa; color: transparent; font-size: 1px; height: 20px; line-height: 20px; padding: 0px 0px 0px 0px;"> &nbsp; </td>
				</tr>
				<tr>
					<td align="center" bgcolor="#fafafa" style="background-color: #fafafa; padding-bottom: 0px; padding-left: 20px; padding-right: 20px; padding-top: 0px;">
						<table align="right" border="0" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
							<tbody>

							<?php
							$nTotal = count( $order_totals );

							// Oneline Modulo
							$aOneline = array();
							$aAuxTotals = $order_totals;

							foreach( $order_totals as $nCont => $aTotal )
							{
								// Si ya se ha mostrado el total en una línea, saltamos
								if( in_array( $aTotal['code'], $aOneline ) )
									continue;

								// Si el módulo está marcado para mostrar en una línea
								if( isset( $aTotal['oneline']['active'] ) && $aTotal['oneline']['active'] == 'true' )
								{
									$nTotalOneLine = 0;

									// Añadimos el código al array para solo mostrar la misma clase ésta única vez
									$aOneline[] = $aTotal['code'];

									// Cambiamos nombre del título por el indicado
									$aTotal['title'] = $aTotal['oneline']['title'];

									// Recorremos los módulos que sean de la misma clase y sumamos valores
									foreach( $aAuxTotals as $aAux )
									{
										// Si el code es el mismo que estamos actualmente, sumamos el valor al total
										if( $aAux['code'] == $aTotal['code'] )
											$nTotalOneLine += $aAux['value'];
									}

									// Cambiamos el texto por el total formateado
									$aTotal['text'] = $currencies->format( $nTotalOneLine );
								}

								$temp_title = trim( strip_tags( $aTotal['title'] ) );

								if( substr( $temp_title, -1 ) == ':' )
									$temp_title = substr($temp_title, 0, -1);

								echo '<tr>';
								echo '<td align="center" style="line-height: 100%; padding: 0px 10px 0px 0px;" valign="middle">';
								echo '<table align="left" bgcolor="#cccccc" cellpadding="10" cellspacing="0" style="background-color: #cccccc; border-collapse: collapse; border-radius: 5px; mso-table-lspace: 0pt; mso-table-rspace: 0pt;">';
								echo '<tbody>';
								echo '<tr>';
								echo '<td align="center" style="color: #ffffff; font-size: 10px; line-height: 12px; padding: 3px 8px 3px 8px;"><span style="color: #FFF; font-family: Arial,sans-serif; text-size-adjust: 100%; font-size: 13px; line-height: 13px;"> ' . $temp_title . ' </span></td>';
								echo '</tr>';
								echo '</tbody>';
								echo '</table>';
								echo '</td>';
								echo '<td align="right" style="color: rgb(51, 51, 51); font-size: 16px; line-height: 20px; padding: 0px;"><font color="#333333" size="2" style="font-family: Arial,sans-serif; text-size-adjust: 100%;font-size: 13px; line-height: 13px;"> ' . strip_tags( $aTotal['text'] ) . ' </font></td>';
								echo '</tr>';
								echo '<tr>';
								echo '<td align="center" bgcolor="#fafafa" height="5" style="background-color: #fafafa; color: transparent; font-size: 0px; height: 5px; line-height: 5px; padding: 0px 0px 0px 0px;"> &nbsp; </td>';
								echo '</tr>';

								if( $nCont == $nTotal - 2 )
								{
									echo '<tr>';
									echo '<td align="center" bgcolor="#fafafa" height="10" style="color: transparent; font-size: 0px; height: 10px; line-height: 10px; padding: 0px 0px 0px 0px;"> &nbsp; </td>';
									echo '</tr>';

									echo '<tr>';
									echo '<td align="center" bgcolor="#dddddd" height="1" style="color: transparent; font-size: 0px; height: 1px; line-height: 1px; padding: 0px 0px 0px 0px;"> &nbsp; </td>';
									echo '<td align="center" bgcolor="#dddddd" height="1" style="color: transparent; font-size: 0px; height: 1px; line-height: 1px; padding: 0px 0px 0px 0px;"> &nbsp; </td>';
									echo '</tr>';
									echo '<tr>';
									echo '<td align="center" bgcolor="#fafafa" height="10" style="color: transparent; font-size: 0px; height: 10px; line-height: 10px; padding: 0px 0px 0px 0px;"> &nbsp; </td>';
									echo '</tr>';
								}
							}
							?>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<td align="center" bgcolor="#fafafa" height="20" style="background-color: #fafafa; color: transparent; font-size: 1px; height: 20px; line-height: 20px; padding: 0px 0px 0px 0px;"> &nbsp; </td>
				</tr>
				</tbody>
			</table>
		</td>
	</tr>
	</tbody>
</table>

<table cellpadding="0" cellspacing="0"><tr><td align="left" height="20" style="color: transparent; font-size: 1px; height: 20px; line-height: 20px; padding: 0px 0px 0px 0px;"> &nbsp; </td></tr></table>

<?php if( is_object( $payment ) ): ?>
	<table align="left" bgcolor="#f5f5f5" cellpadding="0" cellspacing="0" style="border-collapse: collapse; border-radius: 5px; box-shadow: rgb(221, 221, 221) 0px 0px 5px;" width="100%">
		<tbody>
		<tr>
			<td align="left" height="10" style="color: transparent; font-size: 1px; height: 10px; line-height: 10px; padding: 0px 0px 0px 0px;"> &nbsp; </td>
		</tr>
		<tr>
			<td align="left" style="padding-bottom: 0px; padding-left: 0px; padding-right: 0px; padding-top: 0px;">
				<table width="100%" align="left" border="0" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
					<tbody>
					<tr>
						<td align="left" style="line-height: 100%; padding-bottom: 0px; padding-left: 20px; padding-right: 20px; padding-top: 0px;">
							<span style="font-family: Arial,sans-serif; font-size: 14px;line-height: 20px;"><strong><?php echo UHE_TEXT_PAYMENT_METHOD; ?>:</strong> <?php echo $order->info['payment_method']; ?></span>
							<?php
							$payment_class = $payment;

							if( $payment_class->email_footer )
							{
								echo '<table cellpadding="0" cellspacing="0"><tr><td align="left" height="10" style="color: transparent; font-size: 1px; height: 10px; line-height: 10px; padding: 0px 0px 0px 0px;"> &nbsp; </td></tr></table>';
								echo '<span style="font-family: Arial,sans-serif; font-size: 14px;line-height: 20px;">' . str_replace( array("\r\n", "\n\r", "\n", "\r", "\t"), '<br />', preg_replace( '/\n$/', '', $payment_class->email_footer ) ) . '</span>';
							}
							?>
						</td>
					</tr>
					</tbody>
				</table>
			</td>
		</tr>
		<tr>
			<td align="left" height="10" style="color: transparent; font-size: 0px; height: 10px; line-height: 10px; padding: 0px 0px 0px 0px;"> &nbsp; </td>
		</tr>
		</tbody>
	</table>

	<table cellpadding="0" cellspacing="0"><tr><td align="left" height="20" style="color: transparent; font-size: 1px; height: 20px; line-height: 20px; padding: 0px 0px 0px 0px;"> &nbsp; </td></tr></table>
<?php endif; ?>

<table width="100%" align="left" border="0" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
	<tr>
		<td width="47.5%" style="width: 47.5%;">
			<table align="left" bgcolor="#f5f5f5" cellpadding="0" cellspacing="0" style="border-collapse: collapse; border-radius: 5px; box-shadow: rgb(221, 221, 221) 0px 0px 5px;" width="100%">
				<tbody>
				<tr>
					<td align="left" height="10" style="color: transparent; font-size: 1px; height: 10px; line-height: 10px; padding: 0px 0px 0px 0px;"> &nbsp; </td>
				</tr>
				<tr>
					<td align="left" style="padding-bottom: 0px; padding-left: 0px; padding-right: 0px; padding-top: 0px;">
						<table width="100%" align="left" border="0" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
							<tbody>
							<tr>
								<td align="left" style="line-height: 100%; padding-bottom: 0px; padding-left: 20px; padding-right: 20px; padding-top: 0px;">
									<span style="font-family: Arial,sans-serif; font-size: 14px;line-height: 20px;"><strong><?php echo UHE_TEXT_DELIVERY_ADDRESS; ?></strong></span>
									<br/>
									<span style="font-family: Arial,sans-serif; font-size: 13px;line-height: 18px;"><?php echo tep_address_label($customer_id, $sendto, 0, '', '<br />'); ?></span>
								</td>
							</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<td align="left" height="10" style="color: transparent; font-size: 0px; height: 10px; line-height: 10px; padding: 0px 0px 0px 0px;"> &nbsp; </td>
				</tr>
				</tbody>
			</table>
		</td>
		<td width="10%" style="width: 5%;"></td>
		<td width="47.5%" style="width: 47.5%;">
			<table align="left" bgcolor="#f5f5f5" cellpadding="0" cellspacing="0" style="border-collapse: collapse; border-radius: 5px; box-shadow: rgb(221, 221, 221) 0px 0px 5px;" width="100%">
				<tbody>
				<tr>
					<td align="left" height="10" style="color: transparent; font-size: 1px; height: 10px; line-height: 10px; padding: 0px 0px 0px 0px;"> &nbsp; </td>
				</tr>
				<tr>
					<td align="left" style="padding-bottom: 0px; padding-left: 0px; padding-right: 0px; padding-top: 0px;">
						<table width="100%" align="left" border="0" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
							<tbody>
							<tr>
								<td align="left" style="line-height: 100%; padding-bottom: 0px; padding-left: 20px; padding-right: 20px; padding-top: 0px;">
									<span style="font-family: Arial,sans-serif; font-size: 14px;line-height: 20px;"><strong><?php echo UHE_TEXT_BILLING_ADDRESS; ?></strong></span>
									<br/>
									<span style="font-family: Arial,sans-serif; font-size: 13px;line-height: 18px;"><?php echo tep_address_label($customer_id, $billto, 0, '', '<br />'); ?></span>
								</td>
							</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<td align="left" height="10" style="color: transparent; font-size: 0px; height: 10px; line-height: 10px; padding: 0px 0px 0px 0px;"> &nbsp; </td>
				</tr>
				</tbody>
			</table>
		</td>
	</tr>
</table>

<table cellpadding="0" cellspacing="0"><tr><td align="left" height="20" style="color: transparent; font-size: 1px; height: 20px; line-height: 20px; padding: 0px 0px 0px 0px;"> &nbsp; </td></tr></table>

<!-- Bloque sostenibilidad: compromiso medioambiental Francobordo -->
<table align="center" cellpadding="0" cellspacing="0" style="border-collapse: collapse;" width="100%">
	<tbody>
		<tr>
			<td style="padding: 22px 22px 22px; background: #f3f8ed; border-left: 3px solid #6fbf3d;">
				<table cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse: collapse;">
					<tr>
						<td valign="middle" width="42" style="padding-right: 14px;">
							<table cellpadding="0" cellspacing="0" border="0" style="background: #cae9b6; border-radius: 50%;" width="42" height="42">
								<tr><td align="center" valign="middle" width="42" height="42" style="font-family: Arial,sans-serif; font-size: 22px; line-height: 42px; color: #2f5e1b;">&#9851;</td></tr>
							</table>
						</td>
						<td valign="middle">
							<font face="Arial,sans-serif" style="font-size: 16px; line-height: 22px; color: #2f5e1b;"><strong><?php echo UHE_TEXT_ENVIRONMENT_TITLE; ?></strong></font>
						</td>
					</tr>
					<tr>
						<td colspan="2" style="padding-top: 14px;">
							<font face="Arial,sans-serif" style="font-size: 13px; line-height: 21px; color: #4a4a4a;"><?php echo UHE_TEXT_ENVIRONMENT_BODY; ?></font>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</tbody>
</table>

{% endblock %}
