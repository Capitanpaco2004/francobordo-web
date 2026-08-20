<?php
	// Aplicacion
	include( 'includes/application.php' );

	// Breadcrumb
	$breadcrumb->add( NAVBAR_TITLE_2, tep_href_link( FILENAME_ACCOUNT_HISTORY, '', 'SSL' ) );

	// Header
	include( 'account/includes/header.php' );

	// Cargamos helper de fecha estimada si existe
	if( file_exists( DIR_FS_CATALOG . 'includes/modules/delivery_estimate/delivery_estimate.php' ) )
		require_once( DIR_FS_CATALOG . 'includes/modules/delivery_estimate/delivery_estimate.php' );

	// Filtros
	$aFilter = isset( $_GET['filter'] ) && is_array( $_GET['filter'] ) ? $_GET['filter'] : array();
	$nFilterYear   = isset( $aFilter['year'] )   ? (int)$aFilter['year']   : 0;
	$nFilterMonth  = isset( $aFilter['month'] )  ? (int)$aFilter['month']  : 0;
	$sFilterSearch = isset( $aFilter['search'] ) ? trim( tep_db_prepare_input( $aFilter['search'] ) ) : '';

	// Construimos el WHERE adicional de filtros
	$sWhereExtra = '';
	if( $nFilterYear > 0 )
		$sWhereExtra .= ' and YEAR(o.date_purchased) = "' . $nFilterYear . '"';
	if( $nFilterMonth > 0 )
		$sWhereExtra .= ' and MONTH(o.date_purchased) = "' . $nFilterMonth . '"';
	if( $sFilterSearch !== '' ) {
		$s = tep_db_input( $sFilterSearch );
		// Búsqueda: nº de pedido (si es numérico), nombre de envío o cualquier producto del pedido (via EXISTS)
		$sExistsProduct = ' or exists( select 1 from ' . TABLE_ORDERS_PRODUCTS . ' opx where opx.orders_id = o.orders_id and opx.products_name like "%' . $s . '%" )';
		if( ctype_digit( $sFilterSearch ) )
			$sWhereExtra .= ' and (o.orders_id = "' . (int)$sFilterSearch . '" or o.delivery_name like "%' . $s . '%"' . $sExistsProduct . ')';
		else
			$sWhereExtra .= ' and (o.delivery_name like "%' . $s . '%"' . $sExistsProduct . ')';
	}

	// Años disponibles para el select
	$qYears = tep_db_query( 'select distinct YEAR(date_purchased) as y from ' . TABLE_ORDERS . ' where customers_id = "' . (int)$customer_id . '" order by y desc' );
	$aYears = array( array( 'id' => '', 'text' => 'Todos los años' ) );
	while( $r = tep_db_fetch_array( $qYears ) )
		$aYears[] = array( 'id' => $r['y'], 'text' => $r['y'] );

	$aMonths = array(
		array( 'id' => '',  'text' => 'Todos los meses' ),
		array( 'id' => '1', 'text' => 'Enero' ),    array( 'id' => '2',  'text' => 'Febrero' ),
		array( 'id' => '3', 'text' => 'Marzo' ),    array( 'id' => '4',  'text' => 'Abril' ),
		array( 'id' => '5', 'text' => 'Mayo' ),     array( 'id' => '6',  'text' => 'Junio' ),
		array( 'id' => '7', 'text' => 'Julio' ),    array( 'id' => '8',  'text' => 'Agosto' ),
		array( 'id' => '9', 'text' => 'Septiembre' ),array( 'id' => '10', 'text' => 'Octubre' ),
		array( 'id' => '11','text' => 'Noviembre' ),array( 'id' => '12', 'text' => 'Diciembre' ),
	);

	// Total de pedidos (antes de la paginación)
	$sSqlCount = 'select count(*) as total
				  from ' . TABLE_ORDERS . ' o
				  inner join ' . TABLE_ORDERS_STATUS . ' s on ( o.orders_status = s.orders_status_id )
				  where o.customers_id = "' . (int)$customer_id . '" and s.language_id = "' . (int)$languages_id . '"' . $sWhereExtra;
	$qCount = tep_db_query( $sSqlCount );
	$nOrdersTotal = (int)tep_db_fetch_array( $qCount )['total'];

	// Listado con paginación. splitPageResults transforma internamente a COUNT(*) — por eso la SELECT debe ser sencilla.
	$sSql = 'select o.orders_id, o.orders_status, o.date_purchased, o.delivery_name, o.delivery_street_address, o.delivery_city, o.billing_name, ot.text as order_total,
					s.orders_status_name, s.color as orders_status_color
			 from ' . TABLE_ORDERS . ' o
			 inner join ' . TABLE_ORDERS_TOTAL . ' ot on( o.orders_id = ot.orders_id )
			 inner join ' . TABLE_ORDERS_STATUS . ' s on ( o.orders_status = s.orders_status_id )
			 where o.customers_id = "' . (int)$customer_id . '" and ot.class = "ot_total" and s.language_id = "' . (int)$languages_id . '"' . $sWhereExtra . '
			 order by o.orders_id desc';
	$aDatosSplit = new splitPageResults( $sSql, MAX_DISPLAY_ORDER_HISTORY );
	$aDatos = tep_db_query( $aDatosSplit->sql_query );
?>

	<div class="ccTitle"><?php echo MY_ORDERS_TITLE; ?></div>

	<div class="ccCnt">

		<?php if( isset( $_GET['m'] ) && $_GET['m'] == 'success' ): ?>
			<?php echo $messageStack->show( array( 'text' => TEXT_CANCEL_ORDER_SUCCESS, 'class' => 'crrt' ) ); ?>
		<?php endif; ?>

		<!-- Filtros (año / mes / búsqueda) -->
		<style>
			.OrdersFilter select,
			.OrdersFilter input[type="text"] {
				height: 40px;
				padding: 0 12px;
				font-size: 14px;
				border: 1px solid #d0d0d0;
				border-radius: 4px;
				background: #ffffff;
				box-sizing: border-box;
				vertical-align: middle;
			}
			.OrdersFilter select {
				-webkit-appearance: none;
				-moz-appearance: none;
				appearance: none;
				padding-right: 38px;
				background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%23666' d='M6 8L0 0h12z'/%3E%3C/svg%3E");
				background-repeat: no-repeat;
				background-position: right 14px center;
			}
			.OrdersFilter .Button { height: 40px; line-height: 40px; padding: 0 18px; box-sizing: border-box; }

			/* Miniaturas: patrón oscdenox82 (todos los productos como tiles, JS oculta los que no caben y añade +N al último visible) */
			.OrdersProducts { position: relative; }
			.OrdersProducts ul { display: flex; flex-wrap: wrap; gap: 10px; list-style: none; padding: 0; margin: 0; }
			.OrdersProducts ul li { position: relative; width: 110px; flex: 0 0 auto; }
			.OrdersProducts ul li.invisible { display: none; }
			.OrdersProducts .Image {
				display: block;
				width: 110px;
				height: 140px;
				border-radius: 4px;
				overflow: hidden;
				background: #ffffff;
				position: relative;
				text-decoration: none;
				color: #fff;
				transition: all .1s ease-in;
			}
			.OrdersProducts .Image img {
				width: 100%;
				height: 100%;
				object-fit: contain;
				display: block;
			}
			.OrdersProducts .Image span {
				position: absolute;
				bottom: 0; left: 0; right: 0;
				font-size: 12px;
				padding: 8px;
				color: #fff;
				background-color: rgba(0, 0, 0, .55);
				opacity: 0;
				transform: translate(0, 10px);
				transition: all .15s ease-in;
				line-height: 1.2;
				max-height: 60%;
				overflow: hidden;
			}
			.OrdersProducts .Image:hover span { opacity: 1; transform: translate(0, 0); }
			.OrdersProducts .viewMore {
				position: absolute;
				top: 0; left: 0; right: 0; bottom: 0;
				background-color: rgba(0, 0, 0, .55);
				display: flex;
				justify-content: center;
				align-items: center;
				color: #fff;
				font-size: 28px;
				font-weight: bold;
				text-decoration: none;
				z-index: 2;
				border-radius: 4px;
				transition: background-color .1s ease-in;
			}
			.OrdersProducts .viewMore:hover { background-color: rgba(0, 0, 0, .75); }
			@media only screen and (max-width: 768px) {
				.OrdersProducts ul li, .OrdersProducts .Image { width: 90px; }
				.OrdersProducts .Image { height: 110px; }
				.OrdersProducts .viewMore { font-size: 22px; }
			}
		</style>
		<form class="OrdersFilter" method="get" style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-bottom:20px;">
			<p style="margin:0;"><strong><?php echo $nOrdersTotal; ?></strong> pedido<?php echo ( $nOrdersTotal != 1 ? 's' : '' ); ?></p>
			<p class="OrdersInputContainer OrdersSelectContainer" style="margin:0;">
				<?php echo tep_draw_pull_down_menu( 'filter[year]', $aYears, $nFilterYear ); ?>
			</p>
			<p class="OrdersInputContainer OrdersSelectContainer" style="margin:0;">
				<?php echo tep_draw_pull_down_menu( 'filter[month]', $aMonths, $nFilterMonth ); ?>
			</p>
			<p class="OrdersInputContainer" style="margin:0;flex:1 1 200px;">
				<input type="text" name="filter[search]" value="<?php echo htmlspecialchars( $sFilterSearch, ENT_QUOTES, 'UTF-8' ); ?>" placeholder="Buscar por nº pedido, nombre o producto…" style="width:100%;"/>
			</p>
			<p class="OrdersSubmitContainer" style="margin:0;">
				<button type="submit" class="Button buttonFirst">Filtrar</button>
				<?php if( $nFilterYear || $nFilterMonth || $sFilterSearch ): ?>
					<a href="<?php echo tep_href_link( FILENAME_ACCOUNT_HISTORY ); ?>" class="Button" style="background:#888;">Limpiar</a>
				<?php endif; ?>
			</p>
		</form>

		<?php if( $nOrdersTotal > 0 ): ?>

			<ul class="OrdersList" style="list-style:none;padding:0;margin:0;">
			<?php
			// Estados en los que mostramos el resumen "X de Y unidades enviadas"
			// y los badges por producto (reservado/esperando/enviado):
			//   2  = Proceso
			//   7  = Enviado Parcialmente (transición automática desde el sync VStock)
			//   13 = En preparación (QFacWin tip="P": pedido remitido al almacén,
			//        aún no salió hacia el cliente — los productos pueden seguir
			//        en estado mixto reservado/esperando hasta el envío real)
			$aEstadosConResumenEnvio = array( 2, 7, 13 );
			while( $aDato = tep_db_fetch_array( $aDatos ) ):
				$oID = (int)$aDato['orders_id'];
				$nOrderStatus = (int)$aDato['orders_status'];
				$bEnProceso = ( $nOrderStatus === 2 );
				$bMostrarResumenEnvio = in_array( $nOrderStatus, $aEstadosConResumenEnvio, true );

				// Productos del pedido (todos, luego se muestran los primeros 5 + "+N" expandible).
				// En pedidos "Proceso" (estado 2) traemos también el estado de almacén
				// (reservado / esperando) por SKU desde orders_warehouse_status, vía
				// products.CCODIART. La tabla la alimenta el sync VStock cada 5 min.
				// Además agregamos los atributos de la línea (Longitud, Color, ...) con
				// GROUP_CONCAT para que el nombre mostrado incluya la variante exacta.
				$aProductos = array();
				if ( $bMostrarResumenEnvio ) {
					$qP = tep_db_query(
						'select op.orders_products_id, op.products_id, op.products_name,'
						. '  group_concat(distinct opa.products_options_values separator " · ") as variant_text,'
						. '  ws.status as warehouse_status'
						. ' from ' . TABLE_ORDERS_PRODUCTS . ' op'
						. ' left join orders_products_attributes opa on opa.orders_products_id = op.orders_products_id'
						. ' left join ' . TABLE_PRODUCTS . ' p on p.products_id = op.products_id'
						. ' left join orders_warehouse_status ws on ws.orders_id = op.orders_id'
						. '   and ws.sku = p.CCODIART'
						. "   and ws.variante = COALESCE(NULLIF(("
						. "     select group_concat(distinct pov2.CCODIVAL order by opa2.products_options_id separator ' / ')"
						. '     from orders_products_attributes opa2'
						. '     left join products_options_values pov2 on pov2.products_options_values_id = opa2.products_options_values_id'
						. '     where opa2.orders_products_id = op.orders_products_id'
						. "   ), ''), ("
						. "     select group_concat(distinct pov3.CCODIVAL order by pa3.options_id separator ' / ')"
						. '     from orders_products_attributes opa3'
						. '     join products_attributes pa3 on pa3.products_id = op.products_id'
						. '     join products_options_values pov3 on pov3.products_options_values_id = pa3.options_values_id'
						. '                                       and pov3.products_options_values_name = opa3.products_options_values'
						. '     where opa3.orders_products_id = op.orders_products_id'
						. "   ), '')"
						. ' where op.orders_id = "' . $oID . '"'
						. ' group by op.orders_products_id, op.products_id, op.products_name, ws.status'
					);
				} else {
					$qP = tep_db_query(
						'select op.orders_products_id, op.products_id, op.products_name,'
						. '  group_concat(distinct opa.products_options_values separator " · ") as variant_text,'
						. '  NULL as warehouse_status'
						. ' from ' . TABLE_ORDERS_PRODUCTS . ' op'
						. ' left join orders_products_attributes opa on opa.orders_products_id = op.orders_products_id'
						. ' where op.orders_id = "' . $oID . '"'
						. ' group by op.orders_products_id, op.products_id, op.products_name'
					);
				}
				while( $rp = tep_db_fetch_array( $qP ) )
					$aProductos[] = $rp;
				$nProductosVisibles = 5;
				$nProductosOcultos  = max( 0, count( $aProductos ) - $nProductosVisibles );

				// Resumen "X de Y unidades enviadas / Pendiente de envío" para
				// pedidos en Proceso (y futuro Envío Parcial). Total ordered =
				// SUM(orders_products.products_quantity); total enviado = SUM(qty)
				// de orders_warehouse_status para status='enviado'.
				$sResumenEnvioHtml = '';
				if ( $bMostrarResumenEnvio ) {
					$qResumen = tep_db_query(
						'select '
						. '(select coalesce(sum(op2.products_quantity),0) from ' . TABLE_ORDERS_PRODUCTS . ' op2 where op2.orders_id = "' . $oID . '") as total_ordered, '
						. '(select coalesce(sum(ws.qty),0) from orders_warehouse_status ws where ws.orders_id = "' . $oID . '" and ws.status = "enviado") as total_shipped'
					);
					$rResumen = tep_db_fetch_array( $qResumen );
					$nTotalOrdered = (float)( $rResumen['total_ordered'] ?? 0 );
					$nTotalShipped = (float)( $rResumen['total_shipped'] ?? 0 );
					$fmtQty = function( $q ) {
						return ( abs( $q - round( $q ) ) < 0.001 )
							? (string)(int)round( $q )
							: rtrim( rtrim( number_format( $q, 2, ',', '' ), '0' ), ',' );
					};
					if ( $nTotalOrdered > 0 ) {
						if ( $nTotalShipped <= 0 ) {
							$sResumenEnvioHtml = '⏳ Pendiente de envío';
						} elseif ( $nTotalShipped < $nTotalOrdered ) {
							$sResumenEnvioHtml = '📦 ' . $fmtQty( $nTotalShipped ) . ' de ' . $fmtQty( $nTotalOrdered ) . ' unidades enviadas';
						}
						// shipped >= ordered: no mostrar — orders_status pasará a Enviado.
					}
				}

				// Fecha estimada de entrega ('0000-00-00' = sin plazo definido: no se pinta fecha)
				$sEntregaEstimada = '';
				if( class_exists( 'delivery_estimate' ) ) {
					$aEntrega = delivery_estimate::getCurrent( $oID );
					if( is_array( $aEntrega ) && ! empty( $aEntrega['estimated_date'] ) && $aEntrega['estimated_date'] !== '0000-00-00' )
						$sEntregaEstimada = (string)tep_date_short( $aEntrega['estimated_date'] );
				}

				$sColor = ! empty( $aDato['orders_status_color'] ) ? $aDato['orders_status_color'] : '#5f5f5f';
			?>
				<li style="background:#ffffff;border:1px solid #e4e4e4;border-radius:6px;padding:20px 24px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
					<!-- Cabecera: nº pedido + menú desplegable -->
					<div class="OrdersTitle OrdersFlex" style="display:flex;justify-content:space-between;align-items:center;gap:14px;border-bottom:1px solid #f0f0f0;padding-bottom:14px;margin-bottom:14px;">
						<h3 style="margin:0;font-size:18px;">
							<a href="<?php echo tep_href_link( FILENAME_ACCOUNT_HISTORY_INFO, 'order_id=' . $oID, 'SSL' ); ?>" style="color:#2bb0e2;text-decoration:none;">Pedido nº <?php echo $oID; ?></a>
						</h3>
						<details style="position:relative;">
							<summary style="cursor:pointer;list-style:none;padding:6px 14px;background:#f6f6f6;border-radius:20px;font-size:13px;color:#333;user-select:none;">
								<span>Ver pedido</span> <i class="fa fa-chevron-down" style="margin-left:4px;"></i>
							</summary>
							<ul style="position:absolute;right:0;top:calc(100% + 4px);background:#ffffff;border:1px solid #e4e4e4;border-radius:4px;box-shadow:0 4px 12px rgba(0,0,0,0.08);min-width:200px;list-style:none;padding:6px 0;margin:0;z-index:10;">
								<li><a href="<?php echo tep_href_link( FILENAME_ACCOUNT_HISTORY_INFO, 'order_id=' . $oID, 'SSL' ); ?>" style="display:block;padding:8px 16px;color:#333;text-decoration:none;"><i class="fa fa-eye" style="width:18px;"></i> Ver detalles</a></li>
								<li><a href="<?php echo tep_href_link( FILENAME_SHOPPING_CART, 'buy_all=' . $oID ); ?>" style="display:block;padding:8px 16px;color:#333;text-decoration:none;"><i class="fa fa-repeat" style="width:18px;"></i> Repetir pedido</a></li>
								<li><a target="_blank" href="<?php echo HTTP_SERVER . DIR_WS_CATALOG . 'printorder.php?order_id=' . $oID; ?>" style="display:block;padding:8px 16px;color:#333;text-decoration:none;"><i class="fa fa-print" style="width:18px;"></i> Imprimir</a></li>
							</ul>
						</details>
					</div>

					<!-- Detalles: fecha, total, estado, envío -->
					<div class="OrdersDetails OrdersFlex" style="display:flex;flex-wrap:wrap;gap:24px;margin-bottom:14px;">
						<p style="margin:0;flex:1 1 140px;">
							<em style="display:block;font-style:normal;font-size:11px;color:#999;text-transform:uppercase;letter-spacing:0.5px;">Fecha pedido</em>
							<strong style="font-size:14px;color:#333;"><?php echo tep_date_short( $aDato['date_purchased'] ); ?></strong>
						</p>
						<p style="margin:0;flex:1 1 120px;">
							<em style="display:block;font-style:normal;font-size:11px;color:#999;text-transform:uppercase;letter-spacing:0.5px;">Total</em>
							<strong style="font-size:14px;color:#333;"><?php echo $aDato['order_total']; ?></strong>
						</p>
						<p style="margin:0;flex:1 1 140px;">
							<em style="display:block;font-style:normal;font-size:11px;color:#999;text-transform:uppercase;letter-spacing:0.5px;">Estado</em>
							<strong style="display:inline-block;background-color:<?php echo htmlspecialchars( $sColor, ENT_QUOTES, 'UTF-8' ); ?>;color:#ffffff;padding:4px 12px;border-radius:12px;font-size:12px;"><?php echo htmlspecialchars( $aDato['orders_status_name'], ENT_QUOTES, 'UTF-8' ); ?></strong>
						</p>
						<?php if( $sEntregaEstimada !== '' ): ?>
							<p style="margin:0;flex:1 1 160px;">
								<em style="display:block;font-style:normal;font-size:11px;color:#999;text-transform:uppercase;letter-spacing:0.5px;"><i class="fa fa-truck" style="margin-right:3px;"></i>Entrega estimada</em>
								<strong style="font-size:14px;color:#58d972;"><?php echo $sEntregaEstimada; ?></strong>
							</p>
						<?php endif; ?>
						<p style="margin:0;flex:2 1 220px;">
							<em style="display:block;font-style:normal;font-size:11px;color:#999;text-transform:uppercase;letter-spacing:0.5px;">Enviado a</em>
							<strong style="font-size:14px;color:#333;"><?php echo htmlspecialchars( trim( (string)$aDato['delivery_street_address'] . ( ! empty( $aDato['delivery_city'] ) ? ', ' . $aDato['delivery_city'] : '' ) ), ENT_QUOTES, 'UTF-8' ); ?></strong>
						</p>
					</div>

					<!-- Resumen de envío (solo en Proceso / Envío Parcial, cuando NO está todo enviado) -->
					<?php if ( $sResumenEnvioHtml !== '' ): ?>
						<p style="margin:0 0 14px 0;font-size:13px;color:#3373c4;line-height:1.4;">
							<?php echo $sResumenEnvioHtml; ?>
						</p>
					<?php endif; ?>

					<!-- Miniaturas de productos (todos se renderizan; JS oculta los que no caben y pone +N) -->
					<?php if( ! empty( $aProductos ) ): ?>
					<div class="OrdersProducts">
						<ul>
						<?php foreach( $aProductos as $prod ):
							$sImg = function_exists('getImageProduct') ? getImageProduct( $prod['products_id'] ) : '';
							$sWh  = isset($prod['warehouse_status']) ? (string)$prod['warehouse_status'] : '';
							$sVariant = isset($prod['variant_text']) ? trim( (string)$prod['variant_text'] ) : '';
							$sFullName = $prod['products_name'];
							if ( $sVariant !== '' ) {
								$sFullName .= ' — ' . $sVariant;
							}
							$sBadgeHtml = '';
							if ( $bMostrarResumenEnvio && $sWh === 'reservado' ) {
								$sBadgeHtml = '<span class="WhBadge WhBadgeOk" title="Producto reservado en almacén" '
									. 'style="position:absolute;top:6px;left:6px;background:#22a06b;color:#fff;'
									. 'font-size:11px;font-weight:600;padding:3px 8px;border-radius:10px;line-height:1;'
									. 'box-shadow:0 1px 2px rgba(0,0,0,0.15);z-index:2;display:inline-flex;align-items:center;gap:4px;">'
									. '<i class="fa fa-check" style="font-size:10px;"></i> reservado</span>';
							} elseif ( $bMostrarResumenEnvio && $sWh === 'enviado' ) {
								$sBadgeHtml = '<span class="WhBadge WhBadgeShipped" title="Producto ya enviado al cliente" '
									. 'style="position:absolute;top:6px;left:6px;background:#3373c4;color:#fff;'
									. 'font-size:11px;font-weight:600;padding:3px 8px;border-radius:10px;line-height:1;'
									. 'box-shadow:0 1px 2px rgba(0,0,0,0.15);z-index:2;display:inline-flex;align-items:center;gap:4px;">'
									. '📦 enviado</span>';
							} elseif ( $bMostrarResumenEnvio && $sWh === 'esperando' ) {
								$sBadgeHtml = '<span class="WhBadge WhBadgeWait" title="Esperando recepción del producto" '
									. 'style="position:absolute;top:6px;left:6px;background:#d23a3a;color:#fff;'
									. 'font-size:11px;font-weight:600;padding:3px 8px;border-radius:10px;line-height:1;'
									. 'box-shadow:0 1px 2px rgba(0,0,0,0.15);z-index:2;display:inline-flex;align-items:center;gap:4px;">'
									. '<i class="fa fa-times" style="font-size:10px;"></i> esperando</span>';
							}
						?>
							<li style="position:relative;">
								<?php echo $sBadgeHtml; ?>
								<a class="Image" href="<?php echo tep_href_link( 'product_info.php', 'products_id=' . (int)$prod['products_id'] ); ?>" title="<?php echo htmlspecialchars( $sFullName, ENT_QUOTES, 'UTF-8' ); ?>">
									<?php if( $sImg !== '' ): ?>
										<?php echo tep_image( DIR_WS_IMAGES . 'productos/' . $sImg, $sFullName, 110, 140, '', false ); ?>
									<?php else: ?>
										<i class="fa fa-image" style="font-size:24px;color:#ccc;display:flex;align-items:center;justify-content:center;height:100%;"></i>
									<?php endif; ?>
									<span><?php echo htmlspecialchars( $sFullName, ENT_QUOTES, 'UTF-8' ); ?></span>
								</a>
							</li>
						<?php endforeach; ?>
						</ul>
					</div>
					<?php endif; ?>

				</li>
			<?php endwhile; ?>
			</ul>

			<!-- Paginación -->
			<div class="pgnc"><?php echo str_replace( basename( FILENAME_ACCOUNT_HISTORY ), FILENAME_ACCOUNT_HISTORY, $aDatosSplit->display_links( MAX_DISPLAY_PAGE_LINKS, tep_get_all_get_params( array( 'page', 'info', 'x', 'y' ) ) ) ); ?></div>

		<?php else: ?>
			<?php echo $messageStack->show( array( 'text' => TEXT_NO_PURCHASES, 'class' => 'wrng' ) ); ?>
		<?php endif; ?>

	</div>

	<script type="text/javascript">
	(function(){
		// Ajusta los tiles visibles según cuántos caben en una fila real (medido del DOM).
		// Los que sobran se ocultan (invisible) y en el último visible se pone un overlay "+N".

		function layoutOrdersProducts( ul ) {
			var items = Array.prototype.slice.call( ul.querySelectorAll( ':scope > li' ) );
			// Reset: quitar invisibles y viewMore previos ANTES de medir
			items.forEach(function( li ){ li.classList.remove('invisible'); });
			Array.prototype.slice.call( ul.querySelectorAll('.viewMore') ).forEach(function( a ){ a.parentNode.removeChild(a); });

			var total = items.length;
			if( total === 0 ) return;

			// Ancho real del contenedor (sin paddings)
			var ulRect = ul.getBoundingClientRect();
			var ulWidth = ulRect.width;
			if( ulWidth <= 0 ) return;

			// Gap horizontal entre tiles
			var style = window.getComputedStyle( ul );
			var gap = parseFloat( style.columnGap || style.gap || 0 ) || 0;

			// Detectamos cuántos tiles caben en la primera fila usando offsetTop
			// (los que tengan el mismo offsetTop que el primero caben en la misma fila)
			var firstTop = items[0].offsetTop;
			var fits = 0;
			for( var i = 0; i < total; i++ ) {
				if( items[i].offsetTop === firstTop ) fits++;
				else break;
			}
			if( fits < 1 ) fits = 1;

			if( total > fits ) {
				var hidden = total - fits;
				for( var j = fits; j < total; j++ ) items[j].classList.add('invisible');
				var last = items[ fits - 1 ];
				var a = document.createElement('a');
				a.href = '#';
				a.className = 'viewMore';
				a.title = 'Ver todos los productos';
				a.textContent = '+' + hidden;
				last.appendChild( a );
			}
		}

		function layoutAll() {
			var uls = document.querySelectorAll('.OrdersProducts ul');
			for( var i = 0; i < uls.length; i++ ) layoutOrdersProducts( uls[i] );
		}

		function bindClicks() {
			document.addEventListener('click', function( e ){
				var a = e.target.closest ? e.target.closest('.OrdersProducts .viewMore') : null;
				if( ! a ) return;
				e.preventDefault();
				var ul = a.closest('ul');
				if( ! ul ) return;
				Array.prototype.slice.call( ul.querySelectorAll('li') ).forEach(function( li ){ li.classList.remove('invisible'); });
				Array.prototype.slice.call( ul.querySelectorAll('.viewMore') ).forEach(function( x ){ x.parentNode.removeChild(x); });
			});
		}

		function init() {
			layoutAll();
			bindClicks();
		}

		if( document.readyState === 'loading' ) {
			document.addEventListener('DOMContentLoaded', init);
		} else {
			init();
		}

		// Recalcular al redimensionar (debounced)
		var resizeTimer;
		window.addEventListener('resize', function(){
			clearTimeout( resizeTimer );
			resizeTimer = setTimeout( layoutAll, 120 );
		});
	})();
	</script>

<?php
	// Footer
	include( 'account/includes/footer.php' );
?>