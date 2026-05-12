<?php
	require('includes/application_top.php');

	// Variables
	$oID = tep_db_prepare_input( $_GET['order_id'] );
	$m = tep_db_prepare_input( $_GET['m'] );
	$nTotal = 0;


	// Comprobamos si estamos logeados
	if( tep_session_is_registered('customer_id') )
	{
		// Obtenemos datos del pedido como la fecha y el id del cliente
		$aDatos = tep_db_query("select date_purchased, customers_id from " . TABLE_ORDERS . " where orders_id = '" . (int)$oID . "'");
		$aDatos = tep_db_fetch_array($aDatos);

		// Comprobamos que el pedido sea nuestro
		if( $aDatos['customers_id'] != $customer_id )
			tep_redirect( 'account_history.php' );

		$date_purchased = substr($aDatos['date_purchased'], 8, 2) . '/' . substr($aDatos['date_purchased'], 5, 2) . '/' . substr($aDatos['date_purchased'], 0, 4);
	}
	else
		tep_redirect( 'index.php' );


	require(DIR_WS_LANGUAGES . $language . '/imprimir_pedido.php');

	include(DIR_WS_CLASSES . 'order.php');
	$currencies = new currencies();
	$order = new order($oID);

	$logo = HTTPS_SERVER . DIR_WS_CATALOG . DIR_THEME .'logo-trans.png';
?>

<!doctype html public "-//W3C//DTD HTML 4.01 Transitional//EN">
<html <?php echo HTML_PARAMS; ?>>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>">
		<title><?php echo TITLE; ?></title>
		<style type="text/css">
			html,body,div,span,applet,object,iframe,h1,h2,h3,h4,h5,h6,p,blockquote,pre,a,abbr,acronym,address,big,cite,code,del,dfn,em,font,img,ins,kbd,q,s,samp,small,strike,strong,sub,sup,tt,var,dl,dt,dd,ol,ul,li,fieldset,form,label,legend,caption{margin:0;padding:0;border:0;outline:0px;font-weight:inherit;font-style:inherit;font-size:100%;font-family:inherit;vertical-align:baseline}:focus{outline:0px}body{margin:0px;padding:0px;font-family:"Lucida Grande",Arial,Helvetica,sans-serif;font-size:12px;-x-system-font:none;font-size-adjust:none;font-stretch:normal;font-style:normal;font-variant:normal;font-weight:normal;line-height:18px;}ol,ul{list-style:none}table{border-collapse:separate;border-spacing:0}caption,th,td{font-weight:normal}blockquote:before,blockquote:after,q:before,q:after{content:""}blockquote,q{quotes:"" ""}
			body
			{
				margin: 25px;
			}

			#cbcr
			{
				position: relative;
				border-bottom: 1px solid #CCC;
				padding-bottom: 15px;
				margin-bottom: 20px;
			}

			.cbcr-info
			{
				font-size: 1.6em;
				color:#666666;
			}

			#logo
			{
				position: absolute;
				top: 0px;
				right: 0px;
			}

			h1
			{
				font-weight: bold;
				color: #000;
			}

			#body span
			{
				color: #000;
			}

			#table
			{
				margin-top: 30px;
				font-size: 1em;
			}

			#table table
			{
				font-size: 1em;
			}

			#table .dataTableHeadingContent
			{
				background: #666666;
				color: #FFF;
			}

			/*#impr
			{
				position: absolute;
				background: #f37d00;
				padding: 5px;
				display: block;
				right: 31px;
				top: 221px;
				color: #FFF;
				text-decoration: none;
			}

			#impr:hover
			{
				background: #f79a38;
			}*/
			#impr
			{
				position: absolute;
				background: #F37D00 4px 6px;
				text-align: right;
				width: 195px;
				padding: 5px;
				display: block;
				right: 31px;
				top: 221px;
				color: #FFF;
				text-decoration: none;
				border-radius: 5px;
				-moz-border-radius: 5px;
				-ms-border-radius: 5px;
				-webkit-border-radius: 5px;
				border-bottom: 1px solid #CCC;
			}
			#impr:hover
			{
				background-color: #f79a38;
			}

			.jump-page
			{
				PAGE-BREAK-AFTER: always;
				margin-top: 60px;
			}

			.table1
			{
				color: #666666;
				font-size: 12px;
			}

			.table1 span
			{
				font-weight:bold;
			}

			#table-pago
			{
				border: 1px solid #CCC;
				font-size: 13px;
				background: #e8f4eb;
			}

			#table-pago td
			{
				border-bottom: 1px solid #CCC;
				padding: 5px;
			}
		</style>

		<style type="text/css" media="print">
			#impr
			{
				visibility:hidden;
			}

			.jump-page
			{
				PAGE-BREAK-AFTER: always;
			}
		</style>
	</head>
	<body>
		<div id="cbcr">
			<div class="cbcr-info">
				<br/><?php echo nl2br(STORE_NAME_ADDRESS); ?><br/><br/><br/><br/>
				<?php echo ENTRY_ORDER_DATE; ?> <?php echo $date_purchased; ?>
			</div>
			<div id="logo">
				<?php echo tep_image( $logo, '', '', '', '', true, false ); ?>
				<br/><br/><br/><br/>
				<label style="text-align: right;display: block;font-size: 30px;color: #008cc6;line-height: 22px;margin-top: -7px;">Pedido: <?php echo (int)$oID; ?></label>
			</div>
		</div>

		<table class="table1" width="100%">
			<tr>
				<td width="50%">
					<h1><?php echo ENTRY_SOLD_TO; ?></h1><br/>
					<?php echo tep_address_format($order->billing['format_id'], $order->billing, 1, '', '<br/>'); ?>

					<br/><br/>

					<span><?php echo ENTRY_TELEFONO; ?></span> <?php echo $order->customer['telephone']; ?><br/>
					<span><?php echo ENTRY_EMAIL; ?></span> <?php echo $order->customer['email_address']; ?><br/><br/><br/>
					<span><?php echo ENTRY_PAYMENT_METHOD; ?></span>
					<?php echo $order->info['payment_method']; ?>
				</td>
				<td valign="top">
					<a id="impr" href="javascript:window.print()">IMPRIMIR RECIBO DE COMPRA</a>
					<h1><?php echo ENTRY_SHIP_TO; ?></h1><br/>
					<?php echo tep_address_format($order->delivery['format_id'], $order->delivery, 1, '', '<br>', false); ?>
				</td>
			</tr>
		</table>


		<table id="table" border="0" width="100%" cellspacing="0" cellpadding="2">
			<tr class="dataTableHeadingRow">
				<td class="dataTableHeadingContent" colspan="2"><?php echo TABLE_HEADING_PRODUCTS; ?></td>
				<td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_TAX; ?></td>
				<td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_PRICE_EXCLUDING_TAX; ?></td>
				<td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_TOTAL_EXCLUDING_TAX; ?></td>
			</tr>
			<tr style="height: 15px;"><td colspan="4"></td></tr>
			<?php
				for( $i = 0, $n = sizeof($order->products); $i < $n; $i++ )
				{
					echo '<tr class="dataTableRow">' . "\n" .
						 '    <td class="dataTableContent" valign="top" align="right">' . $order->products[$i]['qty'] . '&nbsp;x</td>' . "\n" .
						 '    <td class="dataTableContent" valign="top">' . $order->products[$i]['name'];

					if( isset($order->products[$i]['attributes']) && (($k = sizeof($order->products[$i]['attributes'])) > 0))
					{
						for( $j = 0; $j < $k; $j++ )
						{
							echo '<br><nobr><small>&nbsp;<i> - ' . $order->products[$i]['attributes'][$j]['option'] . ': ' . $order->products[$i]['attributes'][$j]['value'];

							if( $order->products[$i]['attributes'][$j]['price'] != '0' )
								echo ' (' . $order->products[$i]['attributes'][$j]['prefix'] . $currencies->format($order->products[$i]['attributes'][$j]['price'] * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']) . ')';

							echo '</i></small></nobr>';
						}
					}

					echo '        </td>' . "\n";
					echo '        <td class="dataTableContent" align="right" valign="top">' . tep_display_tax_value($order->products[$i]['tax']) . '%</td>' . "\n" .
					   '        <td class="dataTableContent" align="right" valign="top"><b>' . $currencies->format($order->products[$i]['final_price'], true, $order->info['currency'], $order->info['currency_value']) . '</b></td>' . "\n" .
					   '        <td class="dataTableContent" align="right" valign="top"><b>' . $currencies->format($order->products[$i]['final_price'] * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']) . '</b></td>' . "\n";
					echo '      </tr>' . "\n";
				}
			?>
			<tr style="height: 15px;"><td colspan="4"></td></tr>
			<tr>
				<td align="right" colspan="8">
					<table border="0" cellspacing="0" cellpadding="2">
						<?php
							for( $i = 0, $n = sizeof($order->totals); $i < $n; $i++ )
							{
								// if( preg_match( '/i\.v\.a|iva|tax/i', $order->totals[$i]['title'] ) )
									// continue;

								echo '          <tr>' . "\n" .
									 '            <td align="right" class="smallText">' . $order->totals[$i]['title'] . '</td>' . "\n" .
									 '            <td align="right" class="smallText">' . $order->totals[$i]['text'] . '</td>' . "\n" .
									 '          </tr>' . "\n";
								if( $order->totals[$i]['title'] = 'Total:' )
									$nTotal = $order->totals[$i]['text'];
							}
						?>
					</table>
				</td>
			</tr>
		</table>

		<?php if( $m && in_array( trim($order->info['payment_method']), array( 'Ingreso o Transferencia Bancaria', 'Contado efectivo (solo en punto' ) ) ): ?>
			<h1 class="jump-page"></h1>
			<div class="cbcr-info">INFORMACIÓN SOBRE EL MÉTODO DE PAGO</div>
			<br/>
			<?php if( trim($order->info['payment_method']) == 'Ingreso o Transferencia Bancaria' ): ?>
				Detalles sobre el método de pago:
				<table id="table-pago">
					<tr><td><b>Entidad Bancaria:</b>        BBVA</td></tr>
					<tr><td><b>Titular de la Cuenta:</b>   SOLUCIONES TÉCNICAS 2000 SL (COMPRAGYM.ES)</td></tr>
					<tr><td><b>Nº Cuenta:</b>  0182 2324 69 0201508458</td></tr>
					<tr><td><b>Importe:</b> <?php echo $nTotal; ?></td></tr>
					<tr><td style="border-bottom: none;"><b>Concepto:</b> Pedido  <?php echo $oID; ?> - <?php echo $customer_first_name; ?></td></tr>
				</table>

				<br/>
				Para agilizar el proceso, es importante que indique en el concepto de la transferencia tal y como se especifica más arriba. <br/>
				Dispone de 72h. para realizar el pago del pedido(sin contar sábados, domingos ni festivos), en caso contrario será eliminado automáticamente. <br/>
				El pedido será enviado cuando se reciba el pago en nuestra cuenta bancaria. Tenga en cuenta que si realiza una transferencia desde otra entidad bancaria pueden pasar entre 24-72 horas  hasta que se vea reflejado en nuestra cuenta.<br/>
			<?php else: ?>
				En el momento en el que el pedido esté preparado para ser recogido, le enviaremos un email para avisarle. A partir de la recepción de ese correo dispone de 72h. (sin contar sábados, domingos, ni festivos) para venir a pagarlo y recogerlo. Pasado ese plazo, el pedido será eliminado automáticamente.
			<?php endif; ?>
		<?php endif; ?>

	</body>
</html>

<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>