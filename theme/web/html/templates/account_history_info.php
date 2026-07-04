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
	$bMostrarEntrega = is_array( $aEntrega ) && ! empty( $aEntrega['estimated_date'] ) && $aEntrega['estimated_date'] !== '0000-00-00' && $max_status < 4 && ! $bRechazado;
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
			case 'on_demand': $sRuleText = defined('DELIVERY_ESTIMATE_RULE_BACKORDER') ? DELIVERY_ESTIMATE_RULE_BACKORDER : ''; break;
			case 'supplier':  $sRuleText = defined('DELIVERY_ESTIMATE_RULE_SUPPLIER')  ? DELIVERY_ESTIMATE_RULE_SUPPLIER  : ''; break;
			case 'unavailable': $sRuleText = defined('DELIVERY_ESTIMATE_RULE_UNAVAILABLE') ? DELIVERY_ESTIMATE_RULE_UNAVAILABLE : ''; break;
			case 'discontinued': $sRuleText = defined('DELIVERY_ESTIMATE_RULE_DISCONTINUED') ? DELIVERY_ESTIMATE_RULE_DISCONTINUED : ''; break;
			case 'manual':    $sRuleText = defined('DELIVERY_ESTIMATE_RULE_MANUAL')    ? DELIVERY_ESTIMATE_RULE_MANUAL    : ''; break;
			case 'seur1330_24h': $sRuleText = defined('DELIVERY_ESTIMATE_RULE_SEUR1330') ? DELIVERY_ESTIMATE_RULE_SEUR1330 : 'Envío urgente SEUR antes de las 13:30h (entrega en 24h)'; break;
			case 'seur10_24h':   $sRuleText = defined('DELIVERY_ESTIMATE_RULE_SEUR10')   ? DELIVERY_ESTIMATE_RULE_SEUR10   : 'Envío urgente SEUR antes de las 10h (entrega en 24h)'; break;
			case 'seursabado_sat': $sRuleText = 'Envío SEUR Sábado (entrega el sábado)'; break;
			case 'seur48_48h':
			case 'seur24_24h':     $sRuleText = 'Envío SEUR 24 (entrega en 24h)'; break;
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
					<?php
						$cexTrk = null;
						$cexTrkQ = tep_db_query("SELECT * FROM cex_tracking WHERE referencia = 'F" . (int) $_GET['order_id'] . "' LIMIT 1");
						if (tep_db_num_rows($cexTrkQ)) $cexTrk = tep_db_fetch_array($cexTrkQ);
						// Tracking Ontime (ontime_tracking, lo alimenta cron_ontime_tracking.php; referencia = orders_id).
						$ontTrk = null;
						$ontTrkQ = tep_db_query("SELECT * FROM ontime_tracking WHERE referencia = '" . (int) $_GET['order_id'] . "' LIMIT 1");
						if (tep_db_num_rows($ontTrkQ)) $ontTrk = tep_db_fetch_array($ontTrkQ);
						$corrTrk = null;
						$corrTrkQ = tep_db_query("SELECT * FROM correos_tracking WHERE referencia = 'F" . (int) $_GET['order_id'] . "' LIMIT 1");
						if (tep_db_num_rows($corrTrkQ)) $corrTrk = tep_db_fetch_array($corrTrkQ);
						// Tracking SEUR (seur_tracking, lo alimenta cron_seur_tracking.php).
						// Referencia 'F{oid}' (integración API) o '{oid}' a pelo (integración
						// vieja de Vstock) — se prueba la F primero.
						$seurTrk = null;
						// Resolver la ref SEUR vigente del pedido: la integración API guarda fila en
						// seur_shipments (tras regenerar, la ref lleva sufijo R{n}); la integración
						// vieja de Vstock no tiene fila y usa ref = oid / F+oid.
						$seurRefW = '';
						$qsrW = tep_db_query("SELECT ref FROM seur_shipments WHERE orders_id = " . (int) $_GET['order_id'] . " AND ref <> '' AND ok = 1 AND cancelled_at IS NULL ORDER BY id DESC LIMIT 1");
						if (tep_db_num_rows($qsrW)) { $rW = tep_db_fetch_array($qsrW); $seurRefW = $rW['ref']; }
						$seurTrkQ = ($seurRefW !== '')
							? tep_db_query("SELECT * FROM seur_tracking WHERE referencia = '" . tep_db_input($seurRefW) . "' LIMIT 1")
							: tep_db_query("SELECT * FROM seur_tracking WHERE referencia IN ('F" . (int) $_GET['order_id'] . "', '" . (int) $_GET['order_id'] . "') ORDER BY referencia = 'F" . (int) $_GET['order_id'] . "' DESC LIMIT 1");
						if (tep_db_num_rows($seurTrkQ)) $seurTrk = tep_db_fetch_array($seurTrkQ);
						$cexUrlSeg = !empty($aUrlEnvio[0][0]) ? $aUrlEnvio[0][0] : '';
						$cexCarrier = '';
						if ($cexTrk || stripos($cexUrlSeg, 'correosexpress') !== false) $cexCarrier = 'Correos Express';
						elseif ($ontTrk || stripos($cexUrlSeg, 'ontime') !== false || stripos($cexUrlSeg, 'alertran') !== false) $cexCarrier = 'Ontime';
						elseif ($seurTrk || stripos($cexUrlSeg, 'seur') !== false) $cexCarrier = 'SEUR';
						elseif (stripos($cexUrlSeg, 'dhl') !== false)  $cexCarrier = 'DHL';
						elseif (stripos($cexUrlSeg, 'tnt') !== false)  $cexCarrier = 'TNT';
						elseif ($corrTrk || stripos($cexUrlSeg, 'correos') !== false) $cexCarrier = 'Correos';
						?>
					<p><strong><?php echo $cexCarrier !== '' ? htmlspecialchars($cexCarrier) : $order->info['shipping_method']; ?></strong>
						<?php
						// SEUR: miSEUR SÍ localiza los envíos de la integración API (ECB nativo,
						// verificado 2026-06-11) pero NO los de la integración vieja de Vstock
						// (con ninguna clave). Botón: envío API (ref F+ecb) → página de SEUR
						// con code+cp+email_tlf; envío viejo → NUESTRO desplegable seurTimeline.
						if ($seurTrk && strpos((string) $seurTrk['referencia'], 'F') === 0 && !empty($seurTrk['ecb'])):
							$seurBtnUrl = 'https://www.seur.com/miseur/mis-envios?code=' . rawurlencode($seurTrk['ecb'])
							            . '&cp=' . rawurlencode(preg_replace('/\s+/', '', (string) ($order->delivery['postcode'] ?? '')))
							            . '&email_tlf=' . rawurlencode(preg_replace('/\s+/', '', (string) ($order->customer['telephone'] ?? '')));
						?>
							<small class="localizaEnvio">
								<a style="background: #ee7f00 !important;" class="Button" href="<?php echo $seurBtnUrl; ?>" target="_blank">
									<i style="margin-right: 5px" class="fa fa-truck-moving" ></i><?php echo $sTextLocalizaEnvio; ?>
								</a>
							</small>
						<?php elseif ($seurTrk): ?>
							<small class="localizaEnvio">
								<a style="background: #ee7f00 !important;" class="Button" href="javascript:void(0)"
								   onclick="var d=document.querySelector('.seurTimeline')||document.querySelector('.seurEstadoEnvio');if(d){if(d.tagName==='DETAILS')d.open=true;d.scrollIntoView({behavior:'smooth',block:'center'});}return false;">
									<i style="margin-right: 5px" class="fa fa-truck-moving" ></i><?php echo $sTextLocalizaEnvio; ?>
								</a>
							</small>
						<?php
						//Si tenemos un array y una url válida, motramos el banner.
						elseif (!empty($aUrlEnvio) && !empty($aUrlEnvio[0]) && filter_var($aUrlEnvio[0][0], FILTER_VALIDATE_URL)): ?>
							<small class="localizaEnvio">
								<a style="background: #ee7f00 !important;" class="Button" href="<?php echo $aUrlEnvio[0][0]; ?>" target="_blank">
									<i style="margin-right: 5px" class="fa fa-truck-moving" ></i><?php echo $sTextLocalizaEnvio; ?>
								</a>
							</small>
						<?php endif; ?>
					</p>
					<?php
					// Estado + histórico Correos (correos_tracking, cron API; referencia F-oid). Desplegable como el de CEX.
					if ($corrTrk):
						$esEspC  = ((int) $languages_id == 3);
						$corrEvs = !empty($corrTrk['eventos_json']) ? json_decode($corrTrk['eventos_json'], true) : null;
					?>
						<details class="correosTimeline" style="margin-top:6px;font-size:13px">
							<summary style="cursor:pointer"><i class="fa fa-circle" style="font-size:8px;margin-right:6px;color:<?php echo $corrTrk['entregado'] ? '#2e9e44' : '#ee7f00'; ?>"></i><?php
								if ($corrTrk['entregado'] && !empty($corrTrk['fecha_evento'])) {
									echo ($esEspC ? 'Entregado el ' : 'Delivered on ') . '<strong>' . date('d/m/Y H:i', strtotime($corrTrk['fecha_evento'])) . '</strong>';
								} else {
									echo ($esEspC ? 'Seguimiento del env&iacute;o: ' : 'Tracking: ') . '<strong>' . htmlspecialchars($corrTrk['estado_desc']) . '</strong>';
								}
							?></summary>
							<?php if (is_array($corrEvs) && count($corrEvs)): $corrEvs = array_reverse($corrEvs); ?>
							<ul style="list-style:none;margin:6px 0 0;padding:0 0 0 4px">
								<?php foreach ($corrEvs as $corrE):
									$corrOk  = (stripos((string) ($corrE['fase'] ?? ''), 'ENTREGAD') !== false);
									$corrBad = (stripos((string) ($corrE['fase'] ?? ''), 'DEVOLUC') !== false);
								?>
									<li style="padding:2px 0"><i class="fa fa-circle" style="font-size:8px;margin-right:6px;color:<?php echo $corrOk ? '#2e9e44' : ($corrBad ? '#c0392b' : '#9bb7cc'); ?>"></i><?php echo htmlspecialchars((string) ($corrE['txt'] ?? '')); ?><?php if (!empty($corrE['f'])): ?> <small style="color:#999">&mdash; <?php echo htmlspecialchars($corrE['f'] . (!empty($corrE['h']) ? ' ' . $corrE['h'] : '')); ?></small><?php endif; ?></li>
								<?php endforeach; ?>
							</ul>
							<?php endif; ?>
						</details>
					<?php endif; ?>
					<?php
					// Histórico de estados (solo envíos Correos Express). Detalle de la API cacheado en cex_tracking.
					if ($cexTrk):
						$cexTimeline = !empty($cexTrk['timeline_json']) ? json_decode($cexTrk['timeline_json'], true) : null;
						$cexFresh = !empty($cexTrk['timeline_at']) && (strtotime($cexTrk['timeline_at']) > time() - 3600);
						if (!is_array($cexTimeline) || (empty($cexTrk['entregado']) && !$cexFresh)) {
							require_once $_SERVER['DOCUMENT_ROOT'] . '/' . DIR_WS_CLASSES . 'correos_express.php';
							$cexEnvQ = tep_db_query("SELECT config_value FROM cex_config WHERE config_key = 'env'");
							$cexEnv  = tep_db_num_rows($cexEnvQ) ? tep_db_fetch_array($cexEnvQ)['config_value'] : 'test';
							$cexCli  = new correos_express($cexEnv); $cexCli->setTimeout(5);
							$cexRes  = $cexCli->seguimientoEnvio('F' . (int) $_GET['order_id']);
							if (!empty($cexRes['ok']) && !empty($cexRes['data']['estadoEnvios'])) {
								$cexTimeline = array();
								foreach ($cexRes['data']['estadoEnvios'] as $cexE) {
									$cexDesc = trim((string) ($cexE['descEstado'] ?? ''));
									if (strcasecmp($cexDesc, 'SIN RECEPCION') === 0) $cexDesc = 'Envío registrado';
									$cexTimeline[] = array('d' => $cexDesc, 'f' => $cexE['fechaEstado'] ?? '', 'h' => $cexE['horaEstado'] ?? '', 'i' => $cexE['descIncEstado'] ?? '', 'e' => (($cexE['codEstado'] ?? '') === '12') ? 1 : 0);
								}
								tep_db_query("UPDATE cex_tracking SET timeline_json = '" . tep_db_input(json_encode($cexTimeline, JSON_UNESCAPED_UNICODE)) . "', timeline_at = now() WHERE referencia = 'F" . (int) $_GET['order_id'] . "'");
							}
						}
						if (is_array($cexTimeline) && count($cexTimeline)):
					?>
						<?php $cexLast = end($cexTimeline); reset($cexTimeline); ?>
							<details class="cexTimeline" style="margin-top:6px;font-size:13px">
								<summary style="cursor:pointer"><i class="fa fa-circle" style="font-size:8px;margin-right:6px;color:<?php echo !empty($cexLast['e']) ? '#2e9e44' : '#ee7f00'; ?>"></i><?php echo ((int) $languages_id == 3 ? 'Seguimiento del env&iacute;o: ' : 'Tracking: '); ?><strong><?php echo htmlspecialchars(mb_convert_case(mb_strtolower($cexLast['d']), MB_CASE_TITLE)); ?></strong></summary>
								<ul style="list-style:none;margin:6px 0 0;padding:0 0 0 4px">
							<?php foreach ($cexTimeline as $cexT):
								$cexFmt = '';
								if (!empty($cexT['f']) && strlen($cexT['f']) === 8) $cexFmt = substr($cexT['f'], 0, 2) . '/' . substr($cexT['f'], 2, 2) . '/' . substr($cexT['f'], 4, 4);
								if (!empty($cexT['h']) && strlen($cexT['h']) >= 4) $cexFmt .= ' ' . substr($cexT['h'], 0, 2) . ':' . substr($cexT['h'], 2, 2);
							?>
								<li style="padding:2px 0">
									<i class="fa fa-circle" style="font-size:8px;margin-right:6px;color:<?php echo !empty($cexT['e']) ? '#2e9e44' : '#9bb7cc'; ?>"></i>
									<?php echo htmlspecialchars(mb_convert_case(mb_strtolower($cexT['d']), MB_CASE_TITLE)); ?>
									<?php if (!empty($cexT['i'])): ?><span style="color:#c0392b">(<?php echo htmlspecialchars(mb_convert_case(mb_strtolower($cexT['i']), MB_CASE_TITLE)); ?>)</span><?php endif; ?>
									<?php if ($cexFmt): ?><small style="color:#999"> — <?php echo $cexFmt; ?></small><?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
							</details>
					<?php endif; endif; ?>
					<?php
					// Histórico de estados (solo envíos Ontime). Eventos cacheados por el cron
					// en eventos_json; desplegable <details> como el de Correos Express.
					if ($ontTrk):
						$esEsp  = ((int) $languages_id == 3);
						$ontEvs = !empty($ontTrk['eventos_json']) ? json_decode($ontTrk['eventos_json'], true) : null;
						$ontEvs = (is_array($ontEvs) && count($ontEvs)) ? array_reverse($ontEvs) : array();
						$ontResumen = ($ontTrk['entregado'] && !empty($ontTrk['fecha_entrega']))
							? ($esEsp ? 'Entregado el ' : 'Delivered on ') . '<strong>' . date('d/m/Y H:i', strtotime($ontTrk['fecha_entrega'])) . '</strong>'
							: ($esEsp ? 'Estado del env&iacute;o: ' : 'Shipment status: ') . '<strong>' . htmlspecialchars($ontTrk['estado_desc']) . '</strong>' . ($ontEvs ? '' : ' <small style="color:#999">(' . date('d/m/Y H:i', strtotime($ontTrk['last_checked'])) . ')</small>');
					if ($ontEvs): ?>
						<details class="ontimeTimeline" style="margin-top:6px;font-size:13px">
							<summary style="cursor:pointer"><i class="fa fa-circle" style="font-size:8px;margin-right:6px;color:<?php echo $ontTrk['entregado'] ? '#2e9e44' : '#ee7f00'; ?>"></i><?php echo $ontResumen; ?></summary>
							<ul style="list-style:none;margin:6px 0 0;padding:0 0 0 4px">
							<?php foreach ($ontEvs as $ontE):
								$ontOk  = in_array(($ontE['t'] ?? ''), array('ENTR', 'EFEC', 'DIAE', 'EAGE'), true);
								$ontBad = in_array(($ontE['t'] ?? ''), array('NOEF', 'DIAN', 'FALT', 'DGEN', 'DEVU', 'ANUL'), true) || preg_match('/^\d{4}$/', (string) ($ontE['t'] ?? ''));
								$ontFmt = !empty($ontE['f']) ? date('d/m/Y H:i', strtotime($ontE['f'])) : '';
							?>
								<li style="padding:2px 0">
									<i class="fa fa-circle" style="font-size:8px;margin-right:6px;color:<?php echo $ontOk ? '#2e9e44' : ($ontBad ? '#c0392b' : '#9bb7cc'); ?>"></i>
									<?php echo htmlspecialchars(mb_convert_case(mb_strtolower((string) ($ontE['d'] ?? '')), MB_CASE_TITLE)); ?>
									<?php if (!empty($ontE['g'])): ?><small style="color:#777">· <?php echo htmlspecialchars(mb_convert_case(mb_strtolower($ontE['g']), MB_CASE_TITLE)); ?></small><?php endif; ?>
									<?php if ($ontFmt): ?><small style="color:#999"> — <?php echo $ontFmt; ?></small><?php endif; ?>
								</li>
							<?php endforeach; ?>
							</ul>
						</details>
					<?php else: ?>
						<span class="ontimeEstadoEnvio" style="display:block;margin-top:6px;font-size:13px">
							<i class="fa fa-circle" style="font-size:9px;margin-right:5px;color:<?php echo $ontTrk['entregado'] ? '#2e9e44' : '#ee7f00'; ?>"></i><?php echo $ontResumen; ?>
						</span>
					<?php endif; ?>
					<?php endif; ?>
					<?php
					// Histórico de estados (solo envíos SEUR vía API). Eventos cacheados por
					// cron_seur_tracking.php en eventos_json [{f,t,d}]; desplegable como los demás.
					if ($seurTrk):
						$esEspS  = ((int) $languages_id == 3);
						$seurEvs = !empty($seurTrk['eventos_json']) ? json_decode($seurTrk['eventos_json'], true) : null;
						$seurEvs = (is_array($seurEvs) && count($seurEvs)) ? array_reverse($seurEvs) : array();
						$seurResumen = ($seurTrk['entregado'] && !empty($seurTrk['fecha_entrega']))
							? ($esEspS ? 'Entregado el ' : 'Delivered on ') . '<strong>' . date('d/m/Y H:i', strtotime($seurTrk['fecha_entrega'])) . '</strong>'
							: ($esEspS ? 'Seguimiento del env&iacute;o: ' : 'Tracking: ') . '<strong>' . htmlspecialchars($seurTrk['estado_desc']) . '</strong>' . ($seurEvs ? '' : ' <small style="color:#999">(' . date('d/m/Y H:i', strtotime($seurTrk['last_checked'])) . ')</small>');
					if ($seurEvs): ?>
						<details class="seurTimeline" style="margin-top:6px;font-size:13px">
							<summary style="cursor:pointer"><i class="fa fa-circle" style="font-size:8px;margin-right:6px;color:<?php echo $seurTrk['entregado'] ? '#2e9e44' : '#ee7f00'; ?>"></i><?php echo $seurResumen; ?></summary>
							<ul style="list-style:none;margin:6px 0 0;padding:0 0 0 4px">
							<?php foreach ($seurEvs as $seurE):
								$seurD   = mb_strtoupper((string) ($seurE['d'] ?? ''));
								$seurOk  = (strpos($seurD, 'ENTREGAD') !== false && !preg_match('/\b(NO|SIN)\s+ENTREG/u', $seurD));
								$seurBad = (strpos($seurD, 'ANULAD') !== false || strpos($seurD, 'CANCELAD') !== false || strpos($seurD, 'DEVOL') !== false || strpos($seurD, 'DEVUELTO') !== false || preg_match('/\b(NO|SIN)\s+ENTREG/u', $seurD));
								$seurFmt = !empty($seurE['f']) ? date('d/m/Y H:i', strtotime($seurE['f'])) : '';
							?>
								<li style="padding:2px 0">
									<i class="fa fa-circle" style="font-size:8px;margin-right:6px;color:<?php echo $seurOk ? '#2e9e44' : ($seurBad ? '#c0392b' : '#9bb7cc'); ?>"></i>
									<?php echo htmlspecialchars(mb_convert_case(mb_strtolower((string) ($seurE['d'] ?? '')), MB_CASE_TITLE)); ?>
									<?php if ($seurFmt): ?><small style="color:#999"> — <?php echo $seurFmt; ?></small><?php endif; ?>
								</li>
							<?php endforeach; ?>
							</ul>
						</details>
					<?php else: ?>
						<span class="seurEstadoEnvio" style="display:block;margin-top:6px;font-size:13px">
							<i class="fa fa-circle" style="font-size:9px;margin-right:5px;color:<?php echo $seurTrk['entregado'] ? '#2e9e44' : '#ee7f00'; ?>"></i><?php echo $seurResumen; ?>
						</span>
					<?php endif; ?>
					<?php endif; ?>

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
				<?php
				// 2026-06-23: "Valorar" enlaza DIRECTO al formulario de opinion (escribir_opinion.php?o=uniqid)
				// en vez de disparar el email via AJAX (cron_opiniones), que fallaba para clientes sin
				// consentimiento de marketing o con la opinion ya marcada como enviada. Asi cualquier
				// cliente puede valorar su pedido desde la cuenta. (Sin clase buttonValorar -> no dispara el AJAX.)
				$rValorar = tep_db_query( 'select uniqid from opinion where orders_id = ' . (int)$_GET['order_id'] . ' limit 1' );
				if( tep_db_num_rows( $rValorar ) > 0 ):
					$aValorar = tep_db_fetch_array( $rValorar );
				?>
					<a href="<?php echo tep_href_link( 'escribir_opinion.php', 'o=' . $aValorar['uniqid'] ); ?>" class="Button buttonSecond">Valorar</a>
				<?php endif; ?>
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

<script>
/* Mapa de puntos SEUR del modal RMA. El modal llega por AJAX (sus <script> no se
 * ejecutan), así que el mapa se monta desde aquí con listeners delegados: al marcar
 * "punto SEUR" se carga Leaflet (perezoso, CDN) y se pinta #seurRmaMap con los puntos
 * del select. Elegir en el mapa selecciona en el select y viceversa. */
(function () {
	function ensureLeaflet(cb) {
		if (window.L) { cb(); return; }
		if (!document.getElementById('seurLeafletCss')) {
			var l = document.createElement('link');
			l.id = 'seurLeafletCss'; l.rel = 'stylesheet';
			l.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
			document.head.appendChild(l);
		}
		var s = document.getElementById('seurLeafletJs');
		if (s) { s.addEventListener('load', function () { if (window.L) cb(); }); return; }
		s = document.createElement('script');
		s.id = 'seurLeafletJs';
		s.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
		s.onload = cb;
		document.head.appendChild(s);
	}
	function initSeurRmaMap() {
		var div = document.getElementById('seurRmaMap');
		var sel = document.getElementById('seurRmaSelect');
		if (!div || !sel || div.dataset.seurInit) return;
		div.dataset.seurInit = '1';
		div.style.display = 'block';
		var map = L.map(div);
		div._seurMap = map;
		L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
		var bounds = [], markers = {};
		Array.prototype.forEach.call(sel.options, function (o) {
			var lat = parseFloat(o.getAttribute('data-lat')), lng = parseFloat(o.getAttribute('data-lng'));
			if (!isFinite(lat) || !isFinite(lng)) return;
			var m = L.marker([lat, lng]).addTo(map);
			m.bindPopup('<strong>' + (o.getAttribute('data-name') || '') + '</strong><br>' +
				(o.getAttribute('data-addr') || '') + '<br>' +
				'<a href="javascript:void(0)" onclick="seurRmaChoose(\'' + o.value + '\')">&#10004; Elegir este punto</a>');
			markers[o.value] = m;
			bounds.push([lat, lng]);
		});
		if (bounds.length) map.fitBounds(bounds, { padding: [25, 25] });
		window.seurRmaChoose = function (id) {
			sel.value = id;
			if (markers[id]) markers[id].openPopup();
		};
		sel.addEventListener('change', function () {
			var m = markers[sel.value];
			if (m) { map.panTo(m.getLatLng()); m.openPopup(); }
		});
		setTimeout(function () { map.invalidateSize(); }, 150);
	}
	function renderSeurPoints(data) {
		var sel = document.getElementById('seurRmaSelect');
		var div = document.getElementById('seurRmaMap');
		if (!sel || !div) return;
		sel.innerHTML = '';
		(data.puntos || []).forEach(function (p) {
			var o = document.createElement('option');
			o.value = p.id;
			o.setAttribute('data-lat', p.lat); o.setAttribute('data-lng', p.lng);
			o.setAttribute('data-name', p.name);
			o.setAttribute('data-addr', p.address + ' (' + p.cp + ' ' + p.city + ')');
			o.textContent = p.name + ' \u2014 ' + p.address + ' (' + p.cp + ' ' + p.city + ')';
			sel.appendChild(o);
		});
		if (div._seurMap) { div._seurMap.remove(); div._seurMap = null; }
		delete div.dataset.seurInit;
		div.innerHTML = '';
		ensureLeaflet(initSeurRmaMap);
	}
	document.addEventListener('click', function (e) {
		if (!e.target || e.target.id !== 'seurRmaCpBtn') return;
		var btn = e.target;
		var inp = document.getElementById('seurRmaCpInput');
		var msg = document.getElementById('seurRmaMsg');
		var hid = document.getElementById('seurRmaCpHidden');
		var cp = ((inp && inp.value) || '').replace(/\D/g, '');
		if (msg) msg.textContent = '';
		if (cp.length !== 5) { if (msg) msg.textContent = 'Pon un CP de 5 d\u00edgitos'; return; }
		var t0 = btn.textContent;
		btn.textContent = btn.getAttribute('data-searching') || '...';
		btn.disabled = true;
		fetch('/seur_puntos.php?cp=' + cp).then(function (r) { return r.json(); }).then(function (d) {
			btn.textContent = t0; btn.disabled = false;
			if (!d.ok || !(d.puntos || []).length) { if (msg) msg.textContent = 'No hay puntos SEUR en ese CP'; return; }
			if (hid) hid.value = cp;
			renderSeurPoints(d);
		}).catch(function () {
			btn.textContent = t0; btn.disabled = false;
			if (msg) msg.textContent = 'Error buscando, prueba de nuevo';
		});
	});
	function maybeInit() {
		var r = document.querySelector('input[name="cex_metodo"][value="seurpunto"]');
		if (r && r.checked) ensureLeaflet(initSeurRmaMap);
	}
	document.addEventListener('change', function (e) {
		if (e.target && e.target.name === 'cex_metodo') maybeInit();
	});
	document.addEventListener('click', maybeInit);
})();
</script>
