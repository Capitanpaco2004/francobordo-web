<?php
	require('includes/application_top.php');
	require(DIR_WS_CLASSES . 'currencies.php');
	include(DIR_WS_CLASSES . 'order.php');
	
	
	$oID = tep_db_prepare_input($_GET['oID']);

	$orders_query = tep_db_query("SELECT orders_id, customers_id from " . TABLE_ORDERS . " where orders_id in (" . $oID . ")");

	// Variables
	$aTitleFactura = 'ALBARÁN';
	$logo = HTTP_SERVER . DIR_WS_CATALOG . DIR_THEME .'logo-trans.png';
?>

<!doctype html public "-//W3C//DTD HTML 4.01 Transitional//EN">
<html <?php echo HTML_PARAMS; ?>>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>">
		<title><?php echo TITLE; ?></title>
		<link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
	</head>
	<body marginwidth="0" marginheight="0" topmargin="0" bottommargin="0" leftmargin="0" rightmargin="0" bgcolor="#FFFFFF">
	
		<?php while( $order_data = tep_db_fetch_array($orders_query) ): ?>
			<?php
				$currencies = new currencies();
				$order = new order($order_data['orders_id']);
			  
				//get the date from the order table
				$date_resource = tep_db_query("select date_purchased from " . TABLE_ORDERS . " where orders_id = '" . (int)$order_data['orders_id'] . "'");
				//get the array from the result
				$date = tep_db_fetch_array($date_resource);
				//get the date as a string from the result
				$date_purchased = substr($date['date_purchased'], 8, 2) . '/' . substr($date['date_purchased'], 5, 2) . '/' . substr($date['date_purchased'], 0, 4);
				$fecha = $date_purchased;
			?>
			<table width="651" border="0" cellspacing="0" align="center">
				<tr>
					<td width="356">
						<?php echo tep_image($logo); ?>
					</td>
					<td width="291">
						<div align="center" class="texto2"><?php echo $aTitleFactura; ?></div>
					</td>
				</tr>
				<tr>
					<td valign="top" class="texto">
						<?php echo nl2br(STORE_NAME_ADDRESS); ?>
					</td>
					<td align="right" valign="top">
						<table width="200" border="0" cellspacing="0">
							<tr>
								<td width="67%">
									<div align="right" class="texto">
										<strong>FECHA PEDIDO</strong>
									</div>
								</td>
								<td width="33%">
									<div align="right" class="texto">
										<?php echo $fecha; ?>
									</div>
								</td>
							</tr>
						</table>
					</td>
				</tr>
				<tr>
					<td>&nbsp;</td>
				</tr>
				<tr>
					<td width="356" valign="top" class="texto">
						<strong>Dirección Envío:</strong>
						<br/>
						<?php echo tep_address_format($order->delivery['format_id'], $order->delivery, 1, '', '<br>', '', ''); ?>
						<br/>
						E-mail: <?php echo $order->customer['email_address']; ?>
						<br/>
						Teléfono: <?php echo $order->customer['telephone']; ?>
						<br/><br/>
						<strong>Método de pago:</strong> <?php echo $order->info['payment_method']; ?>
					</td>
					<td width="291" align="right" valign="top">
						<table width="200" border="0" align="right" cellspacing="0">
							<tr>
								<td width="74%">
									<div align="right" class="texto">
										<strong>N&ordm; de Cliente:</strong>
									</div>
								</td>
								<td width="26%" class="texto" align="right">
									<?php echo $order->customer['id']; ?>
								</td>
							</tr>
							<tr>
								<td width="74%">
									<div align="right" class="texto">
										<strong>N&ordm; de Pedido:</strong>
									</div>
								</td>
								<td width="26%" class="texto" align="right">
									<?php echo $order_data['orders_id']; ?>
								</td>
							</tr>
							
							<? if( $order->billing['nif'] != '' ): ?>
								<tr>
									<td><div align="right" class="texto"><strong>DNI/NIF:</stong></div></td>
									<td class="texto" align="right"><?php echo $order->billing['nif']; ?></td>
								</tr>
							<? endif; ?>
						</table>
					</td>
				</tr>
				<tr>
					<td>&nbsp;</td>
				</tr>
			</table>
			<table width="650" border="1" cellspacing="0" align="center" class="productos">
				<tr>
					<td class="tabla"><div align="center">Artículo</div></td>
					<td class="tabla"><div align="center">Modelo</div></td>
					<td class="tabla"><div align="center">Ubicación</div></td>
					<td class="tabla"><div align="center">Marca</div></td>
					<td class="tabla"><div align="center">Precio Unidad</div></td>
					<td class="tabla"><div align="center">Cantidad</div></td>
					<td class="tabla"><div align="center">Total</div></td>
				</tr>
				<?php
					for ($i = 0, $n = sizeof($order->products); $i < $n; $i++)
					{
						echo '<tr class="tabla2">' . "\n" .
							'<td class="tabla2" align="center" valign="top">' . $order->products[$i]['name'];

							if (isset($order->products[$i]['attributes']) && (($k = sizeof($order->products[$i]['attributes'])) > 0))
							{
								for ($j = 0; $j < $k; $j++)
								{
									echo '<br><nobr><small>&nbsp;<i> - ' . $order->products[$i]['attributes'][$j]['option'] . ': ' . $order->products[$i]['attributes'][$j]['value'];
									
									if ($order->products[$i]['attributes'][$j]['price'] != '0')
										echo ' (' . $order->products[$i]['attributes'][$j]['prefix'] . $currencies->format($order->products[$i]['attributes'][$j]['price'] * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']) . ')';
									
									echo '</i></small></nobr>';
								}
							}
							
							$sFabricante = getFabricanteName($order->products[$i]['products_id']);

							echo '</td>' . "\n" .
							'<td class="tabla2" align="center"valign="top">' . ($order->products[$i]['model'] ? $order->products[$i]['model'] : '-') . '</td>' . "\n" .
							'<td class="tabla2" align="center"valign="top">' . ($order->products[$i]['ubicacion'] ? $order->products[$i]['ubicacion'] : '-') . '</td>' . "\n" .
							'<td class="tabla2" valign="top" align="center">' . ($sFabricante ? $sFabricante : '-') . '</td>' . "\n";

							echo '<td class="tabla2" align="center" valign="top"><b>'.$signo.'' . $currencies->format($order->products[$i]['final_price'], true, $order->info['currency'], $order->info['currency_value']) . '</b></td>' . "\n" .
							'<td class="tabla2" valign="top" align="center">' . $order->products[$i]['qty'] . '</td>' . "\n" .
							'<td class="tabla2" align="center" valign="top"><b>'.$signo.'' . $currencies->format($order->products[$i]['final_price'] * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']) . '</b></td>' . "\n";
						echo '</tr>' . "\n";
					}
				?>
			</table>

			<table width="650" border="0" cellspacing="0" align="center">
				<tr>
					<td align="right">
						<table border="0" cellspacing="0" cellpadding="2">
							<?php
								for ($i = 0, $n = sizeof($order->totals); $i < $n; $i++) 
								{
									echo '<tr>' . "\n" .
										'<td align="right" class="texto">' . $order->totals[$i]['title'] . '</td>' . "\n" .
										'<td align="right" class="texto">'.$signo.'' . $order->totals[$i]['text'] . '</td>' . "\n" .
									'</tr>' . "\n";
								}
							?>
						</table>
					</td>
				</tr>
			</table>
			
			<div style="PAGE-BREAK-AFTER: always;"></div>
		<?php endwhile; ?>
	</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>