<?php
	require('includes/application_top.php');

	$oID = tep_db_prepare_input($_GET['oID']);
	$pad = "0000";
	$orders_query = tep_db_query("SELECT orders_id from " . TABLE_ORDERS . " where orders_id = '" . (int)$oID . "'");
	$order_data = tep_db_fetch_array($orders_query);

	$sql_invoice = "select invoice_number, invoice_serial, DATE_FORMAT(date_purchased, '%d/%m/%Y') as facturas_fecha from ".TABLE_ORDERS." where orders_id = '".(int)$oID."'";
	$act_invoice = tep_db_query($sql_invoice) or die($sql_invoice);
	$row_sql = tep_db_fetch_array($act_invoice);
	$factura = $row_sql['invoice_number'];
	$factura_serie = $row_sql['invoice_serial'];
	$fecha = $row_sql['facturas_fecha'];
	$abono = $row_sql['facturas_abono'];

	include(DIR_WS_CLASSES . 'order.php');
	$order = new order($oID);

	//get the date from the order table
	$date_resource = tep_db_query("select date_purchased from " . TABLE_ORDERS . " where orders_id = '" . (int)$oID . "'");
	//get the array from the result
	$date = tep_db_fetch_array($date_resource);
	//get the date as a string from the result
	$date_purchased = substr($date['date_purchased'], 8, 2) . '/' . substr($date['date_purchased'], 5, 2) . '/' . substr($date['date_purchased'], 0, 4);

	require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_INVOICE);

	$aTitleFactura = 'FACTURA';

	if($abono == 1)
		$aTitleFactura = '<font color="red">FACTURA RECTIFICATIVA</font>';

?>
	<style type="text/css">
		img
		{
			border: none;
			display: block;
		}

		.lh
		{
			line-height: 27px;
		}

		.titl
		{
			color: #D8E4E8;
			font-family: "Arial Black";
			font-size: 30px;
			font-weight: bold;
			text-align: right;
		}

		.txt
		{
			line-height: 20px;
			font-family: Arial;
			font-size: 12px;
		}

		.right
		{
			text-align: right;
		}

		.thead
		{
			color: #FFFFFF;
			font-family: Tahoma;
			font-size: 12px;
			font-weight: bold;
			background: #000000;
			text-align: center;
		}

		.tbody
		{
			color: #000;
			font-family: Tahoma;
			font-size: 12px;
			text-align: center;
			vertical-align: middle;
		}
	</style>

	<table width="100%" cellspacing="0" border="0">
		<tr>
			<td width="20" > </td>
			<td width="741">
				<table  width="741" height="20" cellspacing="0" border="0"><tr><td height="20" width="739"> </td></tr></table>

				<table width="741" cellspacing="0" border="0">
					<tr>
						<td width="269">
							<img width="269" height="102" border="0" alt="" src="theme/web/logo-trans.png">
						</td>
						<td width="466" class="titl">
							<?php echo $aTitleFactura; ?>
						</td>
					</tr>
				</table>

				<table  width="741" cellspacing="0" border="0"><tr><td width="739"> </td></tr></table>

				<table  width="741" cellspacing="0" border="0">
					<tr>
						<td valign="top" width="400" class="txt">
							<?php echo nl2br(STORE_NAME_ADDRESS); ?>
						</td>
						<td valign="middle" width="335" class="txt">
							<table width="335" border="0" cellspacing="0">
								<tr>
									<td class="right lh" width="164">
										<strong>FECHA:</strong>
									</td>
									<td class="right lh" width="165" >
										<?php echo $fecha; ?>
									</td>
								</tr>

								<tr>
									<td class="right lh" width="164">
										<strong>Nº DE FACTURA:</strong>
									</td>
									<td class="right lh" width="165" >
										<?php echo $factura_serie.$factura; ?>
									</td>
								</tr>

									<?php if($abono == 1):?>
										<tr>
											<td class="right lh" width="164">
												<strong>RECTIFICATIVA DE:</strong>
											</td>
											<td class="right lh" width="165" >
												<?php echo $factura_abonada; ?>
											</td>
										</tr>
									<?php endif; ?>
							</table>
						</td>
					</tr>
				</table>

				<table  width="741" cellspacing="0" border="0"><tr><td width="739"> </td></tr></table>

				<table  width="741" cellspacing="0" border="0">
					<tr>
						<td valign="top" width="200" class="txt">
							<strong>Facturar a:</strong> <br/>
							<?php echo tep_address_format($order->billing['format_id'], $order->billing, 1, '', '<br>', '', ''); ?><br><?php echo $order->customer['email_address'];?>
						</td>
						<td valign="top" width="200" class="txt">
							<strong>Enviado a:</strong> <br/>
							<?php echo tep_address_format($order->delivery['format_id'], $order->delivery, 1, '', '<br>', '', ''); ?>
						</td>
						<td valign="middle" width="335" class="txt">
							<table width="335" border="0" cellspacing="0">
								<tr>
									<td class="right lh" width="164">
										<strong>Nº de Cliente:</strong>
									</td>
									<td class="right lh" width="165" >
										<?php echo $order->customer['id']; ?>
									</td>
								</tr>

								<tr>
									<td class="right lh" width="164">
										<strong>Nº de Pedido:</strong>
									</td>
									<td class="right lh" width="165" >
										<?php echo $oID; ?>
									</td>
								</tr>

								<tr>
									<td class="right lh" width="164">
										<strong>DNI/NIF:</strong>
									</td>
									<td class="right lh" width="165" >
										<?php echo $order->billing['nif']; ?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>

				<table  width="741" cellspacing="0" border="0"><tr><td width="739"> </td></tr></table>

				<table  class="prdt" width="741" cellspacing="0" border="0">
					<tr>
						<td width="219" class="thead">Artículo</td>
						<td width="100"  class="thead">Modelo</td>
						<td width="100" class="thead">Marca</td>
						<td width="100" class="thead">Precio Unidad</td>
						<td width="100"  class="thead">Cantidad</td>
						<td width="100" class="thead">Total</td>
					</tr>

					<?php
						for( $i = 0, $n = sizeof($order->products); $i < $n; $i++ )
						{
							echo '<tr>';
							echo '<td width="219" class="tbody">' . $order->products[$i]['name'];

								if( isset($order->products[$i]['attributes']) && (($k = sizeof($order->products[$i]['attributes'])) > 0) )
								{
									for ($j = 0; $j < $k; $j++)
									{
										echo '<br><nobr><small>&nbsp;<i> - ' . $order->products[$i]['attributes'][$j]['option'] . ': ' . $order->products[$i]['attributes'][$j]['value'];

										if( $order->products[$i]['attributes'][$j]['price'] != '0')
											echo ' (' . $order->products[$i]['attributes'][$j]['prefix'] . $currencies->format($order->products[$i]['attributes'][$j]['price'] * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']) . ')';

										echo '</i></small></nobr>';
									}
								}

								echo '</td>';

							   echo '<td width="100" class="tbody">' . $order->products[$i]['model'] . '</td>';
							   echo '<td width="100" class="tbody">' . getFabricanteName($order->products[$i]['products_id']) . '</td>';

								echo '<td width="100" class="tbody"><b>'.$signo.'' . $currencies->format($order->products[$i]['final_price'], true, $order->info['currency'], $order->info['currency_value']) . '</b></td>';
								echo '<td width="100" class="tbody">' . $order->products[$i]['qty'] . '</td>';
								echo '<td width="100" class="tbody"><b>'.$signo.'' . $currencies->format($order->products[$i]['final_price'] * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']) . '</b></td>';
							echo '</tr>' . "\n";
						}
					?>
				</table>

				<table  width="741" cellspacing="0" border="0">
						<?php
							for ($i = 0, $n = sizeof($order->totals); $i < $n; $i++)
							{
								echo '<tr>';
									echo '<td width="600" align="right" class="text">' . $order->totals[$i]['title'] . '</td>';
									echo '<td width="135" align="right" class="text">'.$signo.'' . $order->totals[$i]['text'] . '</td>';
								echo '</tr>';
							}
						?>
				</table>
			</td>
			<td width="20" > </td>
		</tr>
	</table>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
