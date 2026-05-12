<?php echo $messageStack->show('account_history_info'); ?>

<?php setlocale(LC_ALL,"es_ES"); ?>
<div class="orderHistoryInfo">
	<div class="orderHistoryHeader orderFlex">
		<h2><strong>Pedido:</strong> <?php echo (int)$_GET['order_id']; ?></h2>
	</div>

	<div class="orderHistoryDetail orderFlex">
		<p>nº <strong><?php echo (int)$_GET['order_id']; ?></strong> | <strong><?php echo count($order->products); ?></strong> articulo(s)</p>
		<p class="textRight">Realizado el <strong><?php echo tep_date_long( $order->info['date_purchased'] ); ?></strong></p>
	</div>

	<?php
	// Cargamos datos de fecha estimada (se pintan más abajo, tras los círculos de estado)
	$aEntrega = null;
	if( file_exists( DIR_FS_CATALOG . 'includes/modules/delivery_estimate/delivery_estimate.php' ) ) {
		require_once( DIR_FS_CATALOG . 'includes/modules/delivery_estimate/delivery_estimate.php' );
		$aEntrega = delivery_estimate::getCurrent( (int)$_GET['order_id'] );
	}
	?>
	<?php
	$sql = "select os.status_client, os.orders_status_name, osh.date_added, osh.comments from " . TABLE_ORDERS_STATUS_HISTORY . " osh inner join " . TABLE_ORDERS_STATUS . " os on( osh.orders_status_id = os.orders_status_id ) where osh.orders_id = '" . (int)$_GET['order_id'] . "' and os.language_id = '" . (int)$languages_id . "' and os.public_flag = '1' order by osh.date_added ASC";
	//echo '<pre>'.$sql.'</pre>';
	$statuses_query = tep_db_query($sql);
	$estados[1] = array('orders_status_name' => 'Pendiente');
	$estados[2] = array('orders_status_name' => 'En proceso');
	$estados[3] = array('orders_status_name' => 'En preparación');
	$estados[4] = array('orders_status_name' => 'Enviado');
	$estados[5] = array('orders_status_name' => 'Rechazado');
	$estados[6] = array('orders_status_name' => 'Completado');
	$max_status = 0;
	$bRechazado = false;
	$seguimiento = '';

	while( $statuses = tep_db_fetch_array($statuses_query) ) {
		$estados[$statuses['status_client']] = $statuses;
		if( $statuses['status_client'] == 5 )
			$bRechazado = true;

		$max_status = ($statuses['status_client'] > $max_status ? $statuses['status_client'] : $max_status);
		if ($statuses['status_client'] == 4) {
			//$seguimiento = preg_replace('@(http)?(s)?(://)?(([a-zA-Z])([-\w]+\.)+([^\s\.]+[^\s]*)+[^,.\s])@', '<a href="$0" target="_blank" title="$0">$0</a>', $statuses['comments']);
			$seguimiento = preg_replace("~[[:alpha:]]+://[^<>[:space:]]+[[:alnum:]/]~", "<a href=\"\\0\">\\0</a>", $statuses['comments']);
			//$seguimiento = $statuses['comments'];
		}
	}
	?>
	<ul class="orderHistoryBreadcrumb orderFlex">
		<?php foreach( $estados as $id => $estado ): if( ($bRechazado && $id == 2) || (!$bRechazado && $id == 5) ) continue; ?>
		<li class="<?php if ($id <= $max_status): ?>orderBreacrumbComplete<?php endif; ?>">
			<?php
			if ($estado['date_added'] != '') {
				$date_added = strtotime( $estado['date_added'] );
				$date = date( 'd/m/Y H:i:s', $date_added );
			} else {
				$date = '';
			}
			?>
			<p class="orderHistoryBreadcrumbDate"><?php echo $date; ?></p>
			<p class="orderHistoryBreadcrumbTitle"><?php echo $estado['orders_status_name']; ?></p>
		</li>
		<?php endforeach; ?>
	</ul>

	<?php
	// === Estado de almacén por línea (alimentado por sync_warehouse_to_web.py
	// cada 5 min desde VStock + QFacWin). Solo aplica en:
	//   2  = Proceso
	//   7  = Enviado Parcialmente
	//   13 = En preparación (QFacWin tip="P": ya en almacén, aún sin enviar)
	// En otros estados (Pendiente, Enviado total, Completado, Cancelado,
	// Rechazado) no es relevante.
	$nOrderStatus = (int)$order->info['orders_status_id'];
	$bMostrarWh   = in_array( $nOrderStatus, array( 2, 7, 13 ), true );
	$aWhMap       = array();
	$sResumenEnvioHtmlInfo = '';

	if ( $bMostrarWh ) {
		// Mapa por products_id. orders_warehouse_status.sku es el CCODIART (ej.
		// "A334415", "21606500"), distinto del products_model que muestra el
		// resumen entre paréntesis (ej. "72398", "14.197.21"). Hacemos el JOIN
		// con products.CCODIART para indexar por products_id, que sí tenemos
		// en $order->products[i]['id'].
		$qWh = tep_db_query(
			'select ws.sku, ws.status, ws.qty, ws.arrival_date, p.products_id'
			. ' from orders_warehouse_status ws'
			. ' left join ' . TABLE_PRODUCTS . ' p on p.CCODIART = ws.sku'
			. ' where ws.orders_id = "' . (int)$_GET['order_id'] . '"'
		);
		while ( $rWh = tep_db_fetch_array( $qWh ) ) {
			if ( ! empty( $rWh['products_id'] ) ) {
				$aWhMap[ (int)$rWh['products_id'] ] = $rWh;
			}
		}

		// Totales: unidades pedidas vs enviadas (suma de ALI_CANTIDAD via sync)
		$rTot = tep_db_fetch_array( tep_db_query(
			'select '
			. '(select coalesce(sum(op2.products_quantity),0) from ' . TABLE_ORDERS_PRODUCTS . ' op2 where op2.orders_id = "' . (int)$_GET['order_id'] . '") as total_ordered, '
			. '(select coalesce(sum(ws.qty),0) from orders_warehouse_status ws where ws.orders_id = "' . (int)$_GET['order_id'] . '" and ws.status = "enviado") as total_shipped'
		));
		$nTotalOrderedWh = (float)( $rTot['total_ordered'] ?? 0 );
		$nTotalShippedWh = (float)( $rTot['total_shipped'] ?? 0 );
		$fmtQtyWh = function( $q ) {
			return ( abs( $q - round( $q ) ) < 0.001 )
				? (string)(int)round( $q )
				: rtrim( rtrim( number_format( $q, 2, ',', '' ), '0' ), ',' );
		};
		if ( $nTotalOrderedWh > 0 ) {
			if ( $nTotalShippedWh <= 0 ) {
				$sResumenEnvioHtmlInfo = '⏳ Pendiente de envío';
			} elseif ( $nTotalShippedWh < $nTotalOrderedWh ) {
				$sResumenEnvioHtmlInfo = '📦 ' . $fmtQtyWh( $nTotalShippedWh ) . ' de ' . $fmtQtyWh( $nTotalOrderedWh ) . ' unidades enviadas';
			}
			// shipped >= ordered: no mostramos — debería estar ya en estado Enviado.
		}
	}

	// Genera badge HTML inline para una línea según su products_id. Mismos
	// colores que la lista de pedidos: verde=reservado, rojo=esperando,
	// azul=enviado. Devuelve '' si la línea no tiene estado de almacén.
	$fmtWhBadge = function( $pid ) use ( &$aWhMap ) {
		$pid = (int)$pid;
		if ( ! isset( $aWhMap[ $pid ] ) ) return '';
		$status = $aWhMap[ $pid ]['status'];
		$css = 'display:inline-block;font-size:10px;font-weight:600;padding:2px 8px;border-radius:9px;margin-left:6px;line-height:1.4;vertical-align:middle;color:#fff;white-space:nowrap;';
		if ( $status === 'reservado' ) {
			return '<span style="' . $css . 'background:#22a06b;" title="Producto reservado en almacén"><i class="fa fa-check" style="font-size:9px;margin-right:3px;"></i>Reservado</span>';
		}
		if ( $status === 'enviado' ) {
			return '<span style="' . $css . 'background:#3373c4;" title="Producto ya enviado">📦 Enviado</span>';
		}
		if ( $status === 'esperando' ) {
			return '<span style="' . $css . 'background:#d23a3a;" title="Esperando recepción del producto"><i class="fa fa-times" style="font-size:9px;margin-right:3px;"></i>Esperando</span>';
		}
		return '';
	};
	?>

	<?php if ( $sResumenEnvioHtmlInfo !== '' ): ?>
		<div class="whSummaryBox" style="margin:14px 0;padding:12px 18px;background:#f4f8fc;border-left:4px solid #3373c4;border-radius:4px;font-size:15px;color:#3373c4;line-height:1.4;font-weight:600;">
			<?php echo $sResumenEnvioHtmlInfo; ?>
		</div>
	<?php endif; ?>

	<?php
	// Bloque "Fecha estimada de entrega" (justo debajo de los círculos de estado).
	// Sólo se pinta si hay fecha calculada y el pedido aún no está enviado/completado/cancelado/rechazado.
	$bMostrarEntrega = is_array( $aEntrega ) && ! empty( $aEntrega['estimated_date'] ) && $max_status < 4 && ! $bRechazado;
	if( $bMostrarEntrega ):
		$tsEntrega = strtotime( (string)$aEntrega['estimated_date'] );
		$tsHoy     = strtotime( date('Y-m-d') );
		$diasRest  = ( $tsEntrega !== false ) ? (int)round( ($tsEntrega - $tsHoy) / 86400 ) : 0;
		$esManual  = ! empty( $aEntrega['is_manual'] );

		// Etiqueta descriptiva segun la regla aplicada (desde language files)
		$sRuleText = '';
		switch( $aEntrega['rule_applied'] ?? '' ) {
			case 'stock_ok':  $sRuleText = defined('DELIVERY_ESTIMATE_RULE_STOCK_OK')  ? DELIVERY_ESTIMATE_RULE_STOCK_OK  : ''; break;
			case 'no_stock':  $sRuleText = defined('DELIVERY_ESTIMATE_RULE_NO_STOCK')  ? DELIVERY_ESTIMATE_RULE_NO_STOCK  : ''; break;
			case 'backorder': $sRuleText = defined('DELIVERY_ESTIMATE_RULE_BACKORDER') ? DELIVERY_ESTIMATE_RULE_BACKORDER : ''; break;
			case 'manual':    $sRuleText = defined('DELIVERY_ESTIMATE_RULE_MANUAL')    ? DELIVERY_ESTIMATE_RULE_MANUAL    : ''; break;
		}

		// Texto relativo de días (desde language files)
		if( $diasRest > 1 )       $sDiasRest = sprintf( defined('DELIVERY_ESTIMATE_DAYS_REMAINING') ? DELIVERY_ESTIMATE_DAYS_REMAINING : 'En aproximadamente %d días', $diasRest );
		elseif( $diasRest == 1 )  $sDiasRest = defined('DELIVERY_ESTIMATE_TOMORROW') ? DELIVERY_ESTIMATE_TOMORROW : 'Mañana';
		elseif( $diasRest == 0 )  $sDiasRest = defined('DELIVERY_ESTIMATE_TODAY')    ? DELIVERY_ESTIMATE_TODAY    : 'Hoy';
		else                      $sDiasRest = defined('DELIVERY_ESTIMATE_DUE')      ? DELIVERY_ESTIMATE_DUE      : 'Prevista para hoy o antes';
	?>
		<div class="deliveryEstimateBox" style="margin:20px 0; background:#ffffff; border:1px solid #e4e4e4; border-left:4px solid #2bb0e2; border-radius:6px; padding:20px 24px; display:flex; flex-wrap:wrap; align-items:center; gap:20px; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
			<div style="flex:0 0 auto;">
				<i class="fa fa-truck" style="font-size:36px; color:#2bb0e2;"></i>
			</div>
			<div style="flex:1 1 auto; min-width:200px;">
				<div style="font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:#888; margin-bottom:4px;"><?php echo defined('DELIVERY_ESTIMATE_TITLE') ? DELIVERY_ESTIMATE_TITLE : 'Fecha estimada de entrega'; ?></div>
				<div style="font-size:22px; font-weight:bold; color:#333; line-height:1.2;">
					<?php echo ucfirst( tep_date_long( $aEntrega['estimated_date'] ) ); ?>
				</div>
				<?php if( $sRuleText !== '' ): ?>
					<div style="font-size:13px; color:#666; margin-top:6px;"><?php echo htmlspecialchars( $sRuleText, ENT_QUOTES, 'UTF-8' ); ?></div>
				<?php endif; ?>
			</div>
			<div style="flex:0 0 auto; text-align:right;">
				<?php if( $diasRest >= 0 ): ?>
					<div style="display:inline-block; background:#58d972; color:#ffffff; padding:10px 18px; border-radius:20px; font-size:14px; font-weight:bold;"><?php echo htmlspecialchars( $sDiasRest, ENT_QUOTES, 'UTF-8' ); ?></div>
				<?php endif; ?>
			</div>

			<?php if( ! empty( $aEntrega['comment'] ) ): ?>
				<div style="flex:1 1 100%; margin-top:4px; padding-top:14px; border-top:1px dashed #e4e4e4; font-size:13px; color:#555; line-height:1.5;">
					<strong style="color:#2bb0e2;"><?php echo defined('DELIVERY_ESTIMATE_COMMENT_LABEL') ? DELIVERY_ESTIMATE_COMMENT_LABEL : 'Comentario del equipo:'; ?></strong>
					<span style="font-style:italic;"><?php echo nl2br( htmlspecialchars( $aEntrega['comment'], ENT_QUOTES, 'UTF-8' ) ); ?></span>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php
	/*
	#TMJ-311-91024
	@daniel.lucia

	Mostramos un banner con la url de seguiemiento
	*/
	if ((int)$languages_id == 3) {
		$sTextLocalizaEnvio = 'Localiza tu envío';
		$sClassLocalizaEnvio = 'localizaEnvioEs';
	} else {
		$sTextLocalizaEnvio = 'Locate your order';
		$sClassLocalizaEnvio = 'localizaEnvioEn';
	}
	//Extraemos las urls del texto
	preg_match_all('#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $seguimiento, $aUrlEnvio);
	?>


	<div class="orderHistoryContent orderFlex">
		<div class="orderHistoryColumn">
			<div class="orderHistoryBox">
				<h3>Enviado a</h3>
				<p><?php echo tep_address_format( $order->delivery['format_id'], $order->delivery, 1, ' ', '<br/>' ); ?></p>
			</div>
			<div class="orderHistoryBox">
				<h3>Facturación</h3>
				<p><?php echo tep_address_format( $order->billing['format_id'], $order->billing, 1, ' ', '<br/>' ) ; ?></p>
			</div>
			<?php if ($order->info['payment_method']!=''): ?>
				<div class="orderHistoryBox">
					<h3>Método de pago</h3>
					<p><?php echo $order->info['payment_method']; ?></p>
				</div>
			<?php endif; ?>
			<?php if ($order->info['shipping_method']!='' && $max_status > 3): ?>
				<div class="orderHistoryBox">
					<h3>Enviado por</h3>
					<p><?php echo $order->info['shipping_method']; ?>
						<?php
						//Si tenemos un array y una url válida, motramos el banner.
						if (!empty($aUrlEnvio) && !empty($aUrlEnvio[0]) && filter_var($aUrlEnvio[0][0], FILTER_VALIDATE_URL)): ?>
							<small class="localizaEnvio">
								<a style="background: #ee7f00 !important;" class="Button" href="<?php echo $aUrlEnvio[0][0]; ?>" target="_blank">
									<i style="margin-right: 5px" class="fa fa-truck-moving" ></i><?php echo $sTextLocalizaEnvio; ?>
								</a>
							</small>
						<?php endif; ?>
					</p>

				</div>

			<?php endif; ?>
			<?php /*if ($seguimiento!=''): ?>
				<div class="orderHistoryBox">
					<h3>Número de seguimiento</h3>
					<p><?php echo $seguimiento; ?></p>
				</div>
			<?php endif;*/ ?>

		</div>
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
						<td class="Name">' . $order->products[$i]['name'] . ' <small>(' . $order->products[$i]['model'] . ')</small>' . $fmtWhBadge( $order->products[$i]['id'] ) . '<small>&nbsp;&nbsp;';
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
	<?php if ( ! empty( $qfac_invoices ) ): ?>
		<div class="orderHistoryBox orderHistoryInvoices" style="margin:20px 0;background:#ffffff;border:1px solid #e4e4e4;border-left:4px solid #58d972;border-radius:6px;padding:18px 22px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
			<h3 style="margin:0 0 12px 0;color:#333;"><i class="fa fa-file-pdf-o" style="color:#d23a3a;margin-right:6px;"></i>Facturas disponibles</h3>
			<ul style="list-style:none;margin:0;padding:0;">
				<?php foreach ( $qfac_invoices as $inv ): ?>
					<?php
					$sLabel = (int)$inv['is_rectificativa']
						? 'Factura rectificativa ' . htmlspecialchars( $inv['cnumfra'], ENT_QUOTES, 'UTF-8' )
						: 'Factura ' . htmlspecialchars( $inv['cnumfra'], ENT_QUOTES, 'UTF-8' );
					$sFecha = ! empty( $inv['fecha'] ) ? date( 'd/m/Y', strtotime( $inv['fecha'] ) ) : '';
					$sUrl   = tep_href_link( 'account_invoice_download.php', 'id=' . (int)$inv['id'], 'SSL' );
					?>
					<li style="padding:8px 0;border-bottom:1px dashed #e4e4e4;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
						<span style="color:#333;">
							<i class="fa fa-file-pdf-o" style="color:#d23a3a;margin-right:6px;"></i>
							<strong><?php echo $sLabel; ?></strong>
							<?php if ( $sFecha !== '' ): ?>
								<small style="color:#888;margin-left:8px;">· <?php echo $sFecha; ?></small>
							<?php endif; ?>
						</span>
						<a href="<?php echo $sUrl; ?>" class="Button buttonFirst" style="background:#3373c4;color:#fff;">
							<i class="fa fa-download" style="margin-right:5px;"></i>Descargar PDF
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<div class="orderHistoryCart">
		<ul>
			<?php
			for( $i = 0, $n = sizeof( $order->products ); $i < $n; $i++ ) {
				echo '<li>
						<p class="Image">'.tep_image(DIR_WS_IMAGES . 'productos/' .getImageProduct($order->products[$i]['id']), $order->products[$i]['name'], 100, 100, '', false).'</p>
						<p class="Name"><strong>' . $order->products[$i]['name'] . '</strong> <small>(' . $order->products[$i]['model'] . ')</small>' . $fmtWhBadge( $order->products[$i]['id'] ) ;
						if( isset( $order->products[$i]['attributes'] ) && sizeof( $order->products[$i]['attributes'] ) > 0 ) {
							for( $j = 0, $n2 = sizeof( $order->products[$i]['attributes'] ); $j < $n2; $j++ )
								echo '<br/> <i> - ' . $order->products[$i]['attributes'][$j]['option'] . ': ' . $order->products[$i]['attributes'][$j]['value'] . '</i>';
						}

					echo '</p>';
					echo '<p class="Prices"><strong>'.$currencies->format( tep_add_tax( $order->products[$i]['final_price'], $order->products[$i]['tax']) * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value'] ).'</strong> x ' .$order->products[$i]['qty'].' unidad(es)</p>';

				echo '</li>';
			}
			?>
		</ul>
	</div>
	<div class="orderHistoryButtons orderFlex">
		<p>
			<?php
		/*	$sql = 'select id_opinion, orders_id, uniqid from opinion
											  where DATE_FORMAT( fecha_envio, "%Y-%m-%d" ) <= adddate( DATE_FORMAT( NOW(), "%Y-%m-%d" ), -' . SISTEMA_OPINION_DIAS_PEDIDO . ')
											  and email_primero_enviado = "false" AND orders_id = ' . (int)$_GET['order_id'];

			$aDatosOpiniones = tep_db_query( $sql );*/?>
		  	<?php //if( tep_db_num_rows( $aDatosOpiniones ) > 0 ): ?>
				<a href="javascript:void(0);" data-id="<?php echo (int)$_GET['order_id']; ?>" class="Button buttonSecond buttonValorar">Valorar</a>
			<?php //endif; ?>
			<a href="<?php echo tep_href_link(FILENAME_SHOPPING_CART, 'buy_all=' . (int)$_GET['order_id']); ?>" class="Button">Comprar de nuevo</a>
			<!--<a href="" class="Button">Devolver</a>-->
			<?php if ($order->info['CFACTUR'] != 'S' && ! in_array($order->info['orders_status_id'], array(100))): ?>
			<a href="<?php echo tep_href_link('account_history_info.php', 'order_id=' . (int)$_GET['order_id'] . '&action=cancel'); ?>" class="Button buttonDanger buttonCancel rojo">Cancelar el pedido</a>
			<?php endif; ?>
		</p>
		<p class="textRight">
			<a target="_blank" href="<?php echo (HTTP_SERVER . DIR_WS_CATALOG . 'printorder.php') . '?' . (tep_get_all_get_params(array('order_id')) . 'order_id=' . $_GET['order_id']); ?>" class="Button buttonFirst">Imprimir pedido</a>
			<a href="<?php echo tep_href_link('account_history.php'); ?>" class="Button buttonFirst">Volver</a>
		</p>
	</div>
</div>
