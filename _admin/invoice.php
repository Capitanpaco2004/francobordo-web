<?php
	require('includes/application_top.php');
	require(DIR_WS_CLASSES . 'currencies.php');
	include(DIR_WS_CLASSES . 'order.php');
	
	$currencies = new currencies();
	$logo = HTTP_SERVER . DIR_WS_CATALOG . DIR_THEME .'logo-trans.png';
	$aPedidosMostrados = array();
	// Variable usada para ver factura o abono
	$bVerAbono = array_key_exists( 'abono', $_GET ) ? '1' : '0';
	// Variable usada para ver una factura como factura o si es un abono saldra como abono
	$bVerTodo = array_key_exists( 'todo', $_GET ) ? true : false;	
	
	$oID = tep_db_prepare_input($_GET['oID']);
  
	$orders_query = tep_db_query("SELECT o.orders_id, f.facturas_id from " . TABLE_ORDERS . " o
								  inner join facturas f on (f.facturas_pedido_id = o.orders_id)
								  where o.orders_id in(" . $oID . ")");
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
				$oID = $order_data['orders_id'];
			
				/* Datos de las facturas*/
				$sql_invoice = "select * from facturas where facturas_pedido_id = '".(int)$oID."'";
				$act_invoice = tep_db_query($sql_invoice);

				while ($row_sql = tep_db_fetch_array($act_invoice))
				{
					if( $bVerTodo || $row_sql['facturas_abono'] == $bVerAbono )
					{
						$factura = $row_sql['facturas_serie'].'-'.$row_sql['facturas_numero'];
						$fecha = $row_sql['facturas_fecha'];
						$abono = $row_sql['facturas_abono'];
					}
				}
				
				// Si el pedido ya se ha mostrado
				if( in_array( $oID, $aPedidosMostrados ) )
					continue;

				// Añadimos el pedido a mostrado
				$aPedidosMostrados[] = $oID;
					
				/* Defino valores por defecto */
				$aTitleFactura = 'FACTURA';
				$signo = '';

				/* Si es un abono saco a que factura corresponde */
				if($abono == 1)
				{
					$factura_abonada_query = tep_db_query("SELECT * from " . TABLE_FACTURAS . " where facturas_pedido_id = '" . (int)$oID . "' and facturas_abono = 0");
					$factura_abonada = tep_db_fetch_array($factura_abonada_query);
					$factura_abonada = $factura_abonada['facturas_serie'].'-'.$factura_abonada['facturas_numero'];
					$aTitleFactura = '<font color="red">FACTURA RECTIFICATIVA</font>';
					$signo = '-';
				}

				//get the date from the order table
				$date_resource = tep_db_query("select date_purchased from " . TABLE_ORDERS . " where orders_id = '" . (int)$oID . "'");
				//get the array from the result
				$date = tep_db_fetch_array($date_resource);
				//get the date as a string from the result
				$date_purchased = substr($date['date_purchased'], 8, 2) . '/' . substr($date['date_purchased'], 5, 2) . '/' . substr($date['date_purchased'], 0, 4);
				$fecha = substr($fecha, 8, 2) . '/' . substr($fecha, 5, 2) . '/' . substr($fecha, 0, 4);

				$order = new order($oID);
			?>
			<table width="651" border="0" cellspacing="0" align="center">
				<tr>
					<td width="356"><?php echo tep_image($logo); ?></td>
					<?php if ($factura!=''): ?>
						<td width="291"><div align="center" class="texto2"><?php echo $aTitleFactura; ?></div></td>
					<?php endif; ?>
				</tr>
				<tr>
					<td valign="top" class="texto"><?php echo nl2br(STORE_NAME_ADDRESS); ?></td>
					<td align="right" valign="top">
						<table width="200" border="0" cellspacing="0">
							<tr>
								<td width="67%"><div align="right" class="texto"><strong>FECHA</strong></div></td>
								<td width="33%"><div align="right" class="texto"><?php echo $fecha; ?></div></td>
							</tr>
							<tr>
								<td><div align="right" class="texto"><strong>N&ordm; DE FACTURA:</strong></div></td>
								<td><div align="right" class="texto"><?php echo $factura;?></div></td>
							</tr>
							<?php if($abono == 1):?>
								<tr>
									<td><div align="right" class="texto"><strong>RECTIFICATIVA DE:</strong></div></td>
									<td><div align="right" class="texto"><?php echo $factura_abonada;?></div></td>
								</tr>
							<?php endif; ?>
						</table>
					</td>
				</tr>
				<tr>
					<td><?php echo tep_draw_separator('pixel_trans.png', '1', '15'); ?></td>
				</tr>
				<tr>
					<td width="356" valign="top" class="texto"><strong>Facturar a:</strong><br><?php echo tep_address_format($order->billing['format_id'], $order->billing, 1, '', '<br>', '', ''); ?><br><?php echo $order->customer['email_address'];?><br><br><strong>Método de pago:</strong> <?php echo $order->info['payment_method']; ?></td>
					<td width="291" align="right" valign="top">
						<table width="200" border="0" align="right" cellspacing="0">
							<tr>
								<td width="74%"><div align="right" class="texto"><strong>N&ordm; de Cliente:</strong></div></td>
								<td width="26%" class="texto" align="right"><?php echo $order->customer['id']; ?></td>
							</tr>
							<tr>
								<td width="74%"><div align="right" class="texto"><strong>N&ordm; de Pedido:</strong></div></td>
								<td width="26%" class="texto" align="right"><?php echo $oID; ?></td>
							</tr>
							<tr>
								<td><div align="right" class="texto"><strong>DNI/NIF:</stong></div></td>
								<td class="texto" align="right"><?php echo $order->billing['nif']; ?></td>
							</tr>
						</table>
					</td>
				</tr>
				<tr>
					<td><?php echo tep_draw_separator('pixel_trans.png', '1', '15'); ?></td>
				</tr>
			</table>
			<table width="650" border="1" cellspacing="0" align="center" class="productos">
				<tr>
					<td class="tabla"><div align="center">Artículo</div></td>
					<td class="tabla"><div align="center">Modelo</div></td>
					<td class="tabla"><div align="center">Marca</div></td>
					<td class="tabla"><div align="center">Precio Unidad</div></td>
					<td class="tabla"><div align="center">Cantidad</div></td>
					<td class="tabla"><div align="center">Total</div></td>
				</tr>
				<?php
					for ($i = 0, $n = sizeof($order->products); $i < $n; $i++)
					{
					  echo '      <tr class="tabla2">' . "\n" .
						   '        <td class="tabla2" align="center" valign="top">' . $order->products[$i]['name'];

					  if (isset($order->products[$i]['attributes']) && (($k = sizeof($order->products[$i]['attributes'])) > 0)) {
						for ($j = 0; $j < $k; $j++) {
						  echo '<br><nobr><small>&nbsp;<i> - ' . $order->products[$i]['attributes'][$j]['option'] . ': ' . $order->products[$i]['attributes'][$j]['value'];
						  if ($order->products[$i]['attributes'][$j]['price'] != '0') echo ' (' . $order->products[$i]['attributes'][$j]['prefix'] . $currencies->format($order->products[$i]['attributes'][$j]['price'] * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']) . ')';
						  echo '</i></small></nobr>';
						}
					  }

					  $sFabricante = getFabricanteName($order->products[$i]['products_id']);
					  
					  echo '        </td>' . "\n" .
						   '        <td class="tabla2" align="center"valign="top">' . ($order->products[$i]['model'] ? $order->products[$i]['model'] : '-' ) . '</td>' . "\n" .
						   ' 		<td class="tabla2" valign="top" align="center">' . ($sFabricante ? $sFabricante : '-') . '</td>' . "\n";
					  echo '        <td class="tabla2" align="center" valign="top"><b>'.$signo.'' . $currencies->format($order->products[$i]['final_price'], true, $order->info['currency'], $order->info['currency_value']) . '</b></td>' . "\n" .
						   '        <td class="tabla2" valign="top" align="center">' . $order->products[$i]['qty'] . '</td>' . "\n" .
						   '        <td class="tabla2" align="center" valign="top"><b>'.$signo.'' . $currencies->format($order->products[$i]['final_price'] * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']) . '</b></td>' . "\n";
					  echo '      </tr>' . "\n";
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
									echo '          <tr>' . "\n" .
									'            <td align="right" class="texto">' . $order->totals[$i]['title'] . '</td>' . "\n" .
									'            <td align="right" class="texto">'.$signo.'' . $order->totals[$i]['text'] . '</td>' . "\n" .
									'          </tr>' . "\n";
								}
							?>
						</table>
					</td>
				</tr>
			</table>
			<?php
				// #FB-VIES: nota legal de inversion del sujeto pasivo en facturas intracomunitarias exentas.
				// Heuristica: entrega a UE, distinta de Espana (195) y de UK (222, export post-Brexit), con
				// IVA total del pedido = 0 (lo realmente cobrado). Export fuera-UE falla fb_is_eu_vat_country;
				// nacional ES lleva IVA>0. Nunca rompe la factura.
				$fb_rc_cid = 0;
				if (isset($order->delivery['country']['id']) && (int) $order->delivery['country']['id'] > 0) $fb_rc_cid = (int) $order->delivery['country']['id'];
				elseif (isset($order->billing['country']['id'])) $fb_rc_cid = (int) $order->billing['country']['id'];
				$fb_rc_tax = isset($order->info['tax']) ? (float) $order->info['tax'] : 0.0;
				$fb_rc = ($fb_rc_cid > 0 && $fb_rc_cid !== 195 && $fb_rc_cid !== 222 && $fb_rc_tax == 0.0
						  && function_exists('fb_is_eu_vat_country') && fb_is_eu_vat_country($fb_rc_cid));
				if ($fb_rc) {
					$fb_rc_nif = isset($order->billing['nif']) ? trim((string) $order->billing['nif']) : '';
			?>
			<table width="650" border="0" cellspacing="0" align="center">
				<tr><td class="texto" style="padding:8px 4px;font-size:11px;border-top:1px solid #ccc;">
					<strong>Operaci&oacute;n exenta de IVA &mdash; inversi&oacute;n del sujeto pasivo</strong>
					(art.&nbsp;25 Ley&nbsp;37/1992; art.&nbsp;138 Directiva&nbsp;2006/112/CE). Entrega intracomunitaria a
					empresario/profesional identificado a efectos de IVA<?php echo ($fb_rc_nif !== '') ? ' (NIF-IVA: ' . htmlspecialchars($fb_rc_nif) . ')' : ''; ?>.
					<br><em>Reverse charge &mdash; VAT to be accounted for by the recipient.</em>
				</td></tr>
			</table>
			<?php } ?>
			<div style="PAGE-BREAK-AFTER: always;"></div>
		<?php endwhile; ?>
	</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>