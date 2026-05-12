<?php
	require('includes/application_top.php');
	require(DIR_WS_CLASSES . 'currencies.php');
	include(DIR_WS_CLASSES . 'order.php');

	// Variables
	$logo = HTTP_SERVER . DIR_WS_CATALOG . DIR_THEME .'logo-trans.png';	
	$aIds = explode( ',', tep_db_prepare_input($_GET['oID']) );
	
	
?>

<!doctype html public "-//W3C//DTD HTML 4.01 Transitional//EN">
<html <?php echo HTML_PARAMS; ?>>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>">
		<title><?php echo TITLE; ?></title>
		<link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
		<style type="text/css">
		.etiquetaGrande {
			font-family: enviroD, Tahoma, Arial, Helvetica;
			font-size: 36px;
			color: #000000;
		}
		.etiquetaGrande2 {
			font-family: Tahoma, Arial, Helvetica;
			font-size: 36px;
			color: #000000;
		}
		.etiquetaTienda {
			font-family: enviroD, Tahoma, Arial, Helvetica;
			font-size: 18px;
			color: #000000;
		}
		</style>
	</head>

	<body marginwidth="0" marginheight="0" topmargin="0" bottommargin="0" leftmargin="0" rightmargin="0" bgcolor="#FFFFFF">
		<?php foreach( $aIds as $oID ): ?>
			<?php 
				$currencies = new currencies();
				$order = new order($oID);
			?>
			<table border="0" width="70%" align="center">
				<tr>
					<td>
						<table width="100%" border="0">
							<tr>
								<td width="350">
									<table width="100%" height="100%" border="0" cellpadding="0" cellspacing="0">
										<tr>
											<td align="left" valign="top" class="etiquetaTienda"><img src="<?php echo $logo;?>" border="0"></td>
										</tr>
									</table>
								</td>
								<td>&nbsp;</td>
								<td width="350" align="right" valign="top">&nbsp; </td>
							</tr>
						</table>
					</td>
				</tr>
				<tr>
					<td>
						<table width="100%" border="0" cellpadding="0" cellspacing="0">
						</table>
					</td>
				</tr>
				<tr>
					<td>
						<table width="650" border="0">
							<tr>
								<td width="650" align="right" valign="top">
									<table width="100%"  border="0" cellpadding="0" cellspacing="0" class="etiquetaGrande">
										<tr>
											<td width="11"> <img src="../images/borders/mainwhite_01.gif" width="11" height="16" alt=""></td>
											<td background="../images/borders/mainwhite_02.gif"> <img src="../images/borders/mainwhite_02.gif" width="24" height="16" alt=""></td>
											<td width="19"> <img src="../images/borders/mainwhite_03.gif" width="19" height="16" alt=""></td>
										</tr>
										<tr>
											<td background="../images/borders/mainwhite_04.gif"> <img src="../images/borders/mainwhite_04.gif" width="11" height="21" alt=""></td>
											<td align="center" bgcolor="#FFFFFF">
												<table width="100%"  border="0" cellpadding="0" cellspacing="0" class="etiquetaGrande">
													<tr>
														<td align="left" valign="top"><b><?php echo ENTRY_SHIP_TO; ?></b></td>
													</tr>
													<tr>
														<td align="left" valign="bottom">&nbsp;</td>
													</tr>
													<tr>
														<td align="left" valign="bottom"><?php echo tep_address_format($order->delivery['format_id'], $order->delivery, 1, '', '<br>'); ?></td>
													</tr>
													<tr>
														<td><?php echo ENTRY_TELEPHONE_NUMBER; ?><?php echo $order->customer['telephone']; ?></td>
													</tr>
												</table>
											</td>
											<td background="../images/borders/mainwhite_06.gif"> <img src="../images/borders/mainwhite_06.gif" width="19" height="21" alt=""></td>
										</tr>
										<tr>
											<td> <img src="../images/borders/mainwhite_07.gif" width="11" height="18" alt=""></td>
											<td background="../images/borders/mainwhite_08.gif"> <img src="../images/borders/mainwhite_08.gif" width="24" height="18" alt=""></td>
											<td> <img src="../images/borders/mainwhite_09.gif" width="19" height="18" alt=""></td>
										</tr>
									</table>
								</td>
							</tr>
						</table>
					</td>
				</tr>
				<tr>
					<td>
						<table width="650" border="0" >
							<tr>
								<td class="etiquetaGrande" colspan="2">
									<b><span class="main-payment"><?php echo ENTRY_PAYMENT_METHOD; ?></span></b>&nbsp;<?php echo $order->info['payment_method']; ?>
								</td>
							</tr>
							<tr>
								<td class="main-payment" bgcolor="c4c4c4">
									<b>N&uacute;m. Pedido</b> <?php echo ENTRY_INVOICE_NUMBER_PREFIX .  ENTRY_INVOICE_NUMBER_CENTER . tep_db_input($oID) . ENTRY_INVOICE_NUMBER_SUFFIX; ?>
									<td class="etiquetaGrande">Total Pedido</td>
									<?php
										 $n = sizeof($order->totals);
										  echo  "\n" .
											   '        <td align="right" class="etiquetaGrande2">' . $order->totals[$n - 1]['text'] . '</td>' . "\n" .
												 "\n";
										
									?>
								</td>
							</tr>
						</table>
					</td>
				</tr>
				<tr>
					<td>&nbsp;</td>
				</tr>
				<tr>
					<td>
						<!--<table border="0" width="650" cellspacing="0" cellpadding="2">
							<tr class="main-payment">
								<td class="main-payment" bgcolor="c4c4c4" colspan="3"><?php echo TABLE_HEADING_PRODUCTS; ?></td>
								<td class="main-payment"><?php echo TABLE_HEADING_PRODUCTS_MODEL; ?></td>
							</tr>
							<?php
							for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
							echo '      <tr class="dataTableRow">' . "\n" .
							'        <td class="dataTableContent" valign="top" align="right">' . $order->products[$i]['qty'] . '&nbsp;x</td>' . "\n" .
							'        <td class="dataTableContent" valign="top">' . $order->products[$i]['name'];

							if (isset($order->products[$i]['attributes']) && (sizeof($order->products[$i]['attributes']) > 0)) {
							for ($j=0, $k=sizeof($order->products[$i]['attributes']); $j<$k; $j++) {
							echo '<br><nobr><small>&nbsp;<i> - ' . $order->products[$i]['attributes'][$j]['option'] . ': ' . $order->products[$i]['attributes'][$j]['value'];
							echo '</i></small></nobr>';
							}
							}

							echo '        </td>' . "\n" .
							'        <td class="dataTableContent" valign="top">' . $order->products[$i]['model'] . '</td>' . "\n" .
							'      </tr>' . "\n";
							}

							?>
						</table>-->
					</td>
				</tr>
			</table>
			
			<div style="PAGE-BREAK-AFTER: always;"></div>
		<?php endforeach; ?>
	</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>