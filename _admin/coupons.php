<?php
	require('includes/application_top.php');
	require( DIR_WS_FUNCTIONS.'coupons.php' );

	$action = (isset($_GET['action']) ? $_GET['action'] : '');
	$error = (isset($_GET['error']) && is_string($_GET['error'])) ? htmlspecialchars($_GET['error']) : '';
	$message = (isset($_GET['message']) && is_string($_GET['message'])) ? htmlspecialchars($_GET['message']) : '';
	$page = (isset($_GET['page']) && is_scalar($_GET['page']) && (int)$_GET['page'] > 0) ? (int)$_GET['page'] : 1;
	$coupons_id = ( !empty( $_POST['coupons_id'] ) && is_string( $_POST['coupons_id'] ) ? tep_db_input( $_POST['coupons_id'] ) : ( !empty( $_GET['cID'] ) && is_string( $_GET['cID'] ) ? tep_db_input( $_GET['cID'] ) : '' ) );

	if( tep_not_null( $error ) ) 
		$messageStack->add( $error, 'error' );

	if( tep_not_null( $message ) )
		$messageStack->add( $message, 'success' );

	if (tep_not_null($action))
	{
		switch($action)
		{
			case 'status':
				// Solo POST (anti-CSRF, mismo criterio que orders.php)
				if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit(); }

				$sPostId = (isset($_POST['id']) && is_string($_POST['id'])) ? $_POST['id'] : '';
				$iStatus = (isset($_POST['status']) && (int)$_POST['status'] === 1) ? 1 : 0;

				if ($sPostId !== '')
					tep_db_perform( 'discount_coupons', array( 'coupons_status' => $iStatus ), 'update', "coupons_id = '" . tep_db_input( $sPostId ) . "'" );
				exit();
			break;

			// insert/update eliminados 2026-07-10: el alta/edición real la hace coupons_create.php
			// (envía a sí mismo); estos cases eran inalcanzables desde la UI y el INSERT posicional
			// quedó desfasado (10 valores, 11 columnas desde que existe coupons_status).

			case 'deleteconfirm':
				// Solo POST (anti-CSRF): el borrado destruye también el histórico de usos
				if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST')
					tep_redirect(tep_href_link(FILENAME_DISCOUNT_COUPONS, 'page=' . $page));

				if ($coupons_id !== '') {
					tep_db_query($sql = "delete from " . TABLE_DISCOUNT_COUPONS . " where coupons_id = '" .$coupons_id. "'");
					tep_db_query($sql = "delete from " . TABLE_DISCOUNT_COUPONS_TO_ORDERS . " where coupons_id = '" .$coupons_id. "'");
				}

				tep_redirect(tep_href_link(FILENAME_DISCOUNT_COUPONS, 'page=' . $page));
			break;
		}
	}
	
	require(DIR_WS_CLASSES . 'currencies.php');
	$currencies = new currencies();

	// Filtro y busqueda
	$aFilterLabels = array(
		'vigentes'     => 'Vigentes',
		'caducados'    => 'Caducados',
		'agotados'     => 'Agotados',
		'desactivados' => 'Desactivados',
		'todos'        => 'Todos',
	);
	$sFilter = (isset( $_GET['filter'] ) && is_string( $_GET['filter'] )) ? $_GET['filter'] : '';
	if ($sFilter == 'disabled') $sFilter = 'desactivados';	// compat URLs antiguas
	if (!isset($aFilterLabels[$sFilter])) $sFilter = 'vigentes';

	$sSearch = (isset( $_GET['search'] ) && is_string( $_GET['search'] ) && trim( $_GET['search'] ) != '') ? trim( $_GET['search'] ) : false;
	if ($sSearch !== false) $sFilter = 'todos';	// la búsqueda abarca todos los cupones e ignora el filtro
?>

<?php require(THEME . 'html/header.php'); ?>
<table border="0" width="100%"><tr><td>

<div class="toolbarHead">
	<div class="hdr-tlbr">
		<h1 class="pageHeading" style="top: 12px;">Cupones descuento</h1>
		<div class="btn-right">
			<form action="<?php echo tep_href_link( FILENAME_DISCOUNT_COUPONS ); ?>" method="get">
				<div style="position: absolute; right: 120px; width: 268px; top: -6px; height: 75px;">
					<span style="font-size: 11px; color: rgb(99, 99, 99); position: absolute; top: 20px;">Ver:</span><select style="position: absolute; left: 49px; height: 22px; top: 12px; width: 104px;" name="filter"><?php foreach ($aFilterLabels as $sKey => $sLabel) echo '<option value="' . $sKey . '"' . ($sFilter == $sKey ? ' selected="selected"' : '') . '>' . $sLabel . '</option>'; ?></select>
					<input type="submit" value="Filtrar" style="background: none repeat scroll 0% 0% rgb(108, 108, 108); color: rgb(255, 255, 255); border: medium none; cursor: pointer; font-size: 10px; border-radius: 3px; text-transform: uppercase; text-align: center; position: absolute; margin-left: 20px; font-weight: bold; height: 21px; width: 109px; top: 12px; right: 0px;">
					<span style="position: absolute; font-size: 11px; color: rgb(99, 99, 99); top: 51px;">Buscar:</span><input type="text" style="position: absolute; top: 42px; width: 220px; right: 0px;" value="<?php echo ($sSearch !== false ? htmlspecialchars($sSearch) : ''); ?>" name="search" placeholder="código o descripción">
				</div>
			</form>

			<a href="<?php echo tep_href_link('coupons_create.php'); ?>"><img class="dx-hovr" src="images/icons/icon_nuevo_cupon.png"></a>
		</div>
	</div>
</div>

<div class="box-tbl" style="width: 100%">
	<div class="box-head">
		<h6>Cupones descuento</h6>
		<div class="clear"></div>
	</div>
	<table id="table" class="tAlt wGeneral tDefault" width="100%" cellspacing="0" cellpadding="0">
		<thead>
			<tr>
				<td style="text-align: left">Código Descuento</td>
				<td width="100">Descuento</td>
				<td width="100">Comienza</td>
				<td width="100">Finaliza</td>
				<td width="100">Máximo de usos</td>
				<td width="100">Min. Pedido</td>
				<td width="100">Usos / Disp.</td>
				<td width="75">Estado</td>
				<td width="195">Acciones</td>
			</tr>
		</thead>
		<tbody>
			<?php
				$sZero = "'0000-00-00 00:00:00'";
				$sExpiredExpr = "(cd.coupons_date_end is not null and cd.coupons_date_end <> $sZero and cd.coupons_date_end < now())";
				$sStartedExpr = "(cd.coupons_date_start is null or cd.coupons_date_start = $sZero or cd.coupons_date_start <= now())";

				$sWhere = ''; $sHaving = '';
				// por defecto (todos / búsqueda): no caducados primero (sin caducidad arriba, luego por vencimiento próximo), caducados al final los más recientes primero
				$sOrder = " order by $sExpiredExpr, (case when $sExpiredExpr then cd.coupons_date_end end) desc, cd.coupons_date_end is null desc, cd.coupons_date_end, cd.coupons_date_start, cd.coupons_id";

				if ($sSearch !== false) {
					$sSearchSql = tep_db_input($sSearch);
					$sWhere = " where (cd.coupons_id like '%" . $sSearchSql . "%' or cd.coupons_description like '%" . $sSearchSql . "%')";
				} else {
					switch ($sFilter) {
						case 'vigentes':
							$sWhere = " where cd.coupons_status = 1 and $sStartedExpr and not $sExpiredExpr";
							// sin caducidad (permanentes) primero, después por vencimiento más próximo
							$sOrder = " order by cd.coupons_date_end is null desc, cd.coupons_date_end, cd.coupons_date_start, cd.coupons_id";
							break;
						case 'caducados':
							$sWhere = " where $sExpiredExpr";
							$sOrder = " order by cd.coupons_date_end desc";
							break;
						case 'agotados':
							$sWhere = " where cd.coupons_number_available > 0";
							$sHaving = " having count(dcto.coupons_id) >= cd.coupons_number_available";
							break;
						case 'desactivados':
							$sWhere = " where cd.coupons_status = 0";
							break;
					}
				}

				$coupons_query_raw = "select cd.*, count(dcto.coupons_id) as coupons_use_count from " . TABLE_DISCOUNT_COUPONS . " cd left join " . TABLE_DISCOUNT_COUPONS_TO_ORDERS . " dcto on dcto.coupons_id = cd.coupons_id" . $sWhere . " group by cd.coupons_id" . $sHaving . $sOrder;
				// conteo explícito: el genérico de splitPageResults contaría filas del join, no cupones
				if ($sHaving != '')
					$sCountSql = "select count(*) as total from (select cd.coupons_id, cd.coupons_number_available from " . TABLE_DISCOUNT_COUPONS . " cd left join " . TABLE_DISCOUNT_COUPONS_TO_ORDERS . " dcto on dcto.coupons_id = cd.coupons_id" . $sWhere . " group by cd.coupons_id" . $sHaving . ") x";
				else
					$sCountSql = "select count(*) as total from " . TABLE_DISCOUNT_COUPONS . " cd" . $sWhere;
				$coupons_split = new splitPageResults( $page, 15, $coupons_query_raw, $coupons_query_numrows, $sCountSql );
				$coupons_query = tep_db_query($coupons_query_raw);
			?>

			<?php while( $coupons = tep_db_fetch_array( $coupons_query ) ):
				$iAvail = (int)$coupons['coupons_number_available'];
				$iUses = (int)$coupons['coupons_use_count'];
				$bExpired = ( !empty( $coupons['coupons_date_end'] ) && $coupons['coupons_date_end'] != '0000-00-00 00:00:00' && strtotime( $coupons['coupons_date_end'] ) < time() );
				$bFuture = ( !empty( $coupons['coupons_date_start'] ) && $coupons['coupons_date_start'] != '0000-00-00 00:00:00' && strtotime( $coupons['coupons_date_start'] ) > time() );
				$bExhausted = ( $iAvail > 0 && $iUses >= $iAvail );
				$bOff = ( $coupons['coupons_status'] == 0 );
			?>
				<tr<?php echo (($bExpired || $bOff) ? ' style="opacity:.55"' : ''); ?>>
					<td><?php
						echo htmlspecialchars( $coupons['coupons_id'] ).' <small>'.( !empty( $coupons['coupons_description'] ) ? '( '.htmlspecialchars( $coupons['coupons_description'] ).' )' : '' ) .'</small>';
						if ($bExpired) echo ' <span style="background:#c0392b;color:#fff;padding:1px 5px;border-radius:3px;font-size:10px;white-space:nowrap">Caducado</span>';
						if ($bExhausted) echo ' <span style="background:#e67e22;color:#fff;padding:1px 5px;border-radius:3px;font-size:10px;white-space:nowrap">Agotado</span>';
						if (!$bExpired && $bFuture) echo ' <span style="background:#2980b9;color:#fff;padding:1px 5px;border-radius:3px;font-size:10px;white-space:nowrap">Programado</span>';
					?></td>
					<td align="center">
						<?php 
							switch( $coupons['coupons_discount_type'] )
							{
								case 'shipping':
									echo ( $coupons['coupons_discount_amount'] * 100 ).'% '.TEXT_DISPLAY_SHIPPING_DISCOUNT;
								break;

								case 'percent':
									echo ( $coupons['coupons_discount_amount'] * 100 ).'%';
								break;

								case 'fixed':
									echo $currencies->format( $coupons['coupons_discount_amount'] );
								break;
							}
						?>
					</td>

					<td align="center">
						<?php echo !empty( $coupons['coupons_date_start'] ) ? tep_date_short( $coupons['coupons_date_start'] ) : TEXT_DISPLAY_UNLIMITED; ?>
					</td>
					<td align="center">
						<?php echo !empty( $coupons['coupons_date_end'] ) ? tep_date_short( $coupons['coupons_date_end'] ) : TEXT_DISPLAY_UNLIMITED; ?>
					</td>
					<td align="center">
						<?php echo ( $coupons['coupons_max_use'] != 0 ? $coupons['coupons_max_use'] : TEXT_DISPLAY_UNLIMITED ); ?>
					</td>
					<td align="center">
						<?php echo ( $coupons['coupons_min_order'] != 0 ? ( $coupons['coupons_min_order_type'] == 'price' ? $currencies->format( $coupons['coupons_min_order'] ) : (int)$coupons['coupons_min_order'] ) : TEXT_DISPLAY_UNLIMITED ); ?>
					</td>
					<td align="center">
						<?php
							$sUses = $iUses . ' / ' . ( $iAvail != 0 ? $iAvail : '&infin;' );
							if ($iUses > 0)
								echo '<a href="' . tep_href_link( FILENAME_STATS_DISCOUNT_COUPONS, 'cID=' . rawurlencode( $coupons['coupons_id'] ) ) . '" title="Ver quién lo ha usado">' . $sUses . '</a>';
							else
								echo $sUses;
						?>
					</td>
					<td align="center">
						<div style="cursor:pointer" class="stus" data-id="<?php echo htmlspecialchars( $coupons['coupons_id'] ); ?>" data-status="<?php echo (int)$coupons['coupons_status']; ?>">
							<img width="10" height="10" src="images/icon_status_green<?php echo ($coupons['coupons_status'] == 0 ? '_light' : ''); ?>.gif" />
							<img width="10" height="10" src="images/icon_status_red<?php echo ($coupons['coupons_status'] == 1 ? '_light' : ''); ?>.gif" />
						</div>
					<td align="center">
						<div style="display: inline-block; margin-bottom: -4px;" class="btn-group">
							<a href="#" data-toggle="dropdown" class="buttonS bDefault">Acciones<span class="caret"></span></a>
							<ul class="dropdown-menu">
								<li><a href="<?php echo tep_href_link('coupons_create.php', 'page=' . $page . '&coupon=' . rawurlencode($coupons['coupons_id'])); ?>"><span class="icos-pencil"></span>Editar</a></li>
								<li><a href="<?php echo tep_href_link(FILENAME_STATS_DISCOUNT_COUPONS, 'cID=' . rawurlencode($coupons['coupons_id'])); ?>"><span class="icos-chart"></span>Estadísticas</a></li>
								<li><a href="#" class="dlet" data-cid="<?php echo htmlspecialchars($coupons['coupons_id']); ?>"><span class="icos-trash"></span>Eliminar</a></li>
							</ul>
						</div>
					</td>
				</tr>
			<?php endwhile; ?>
		</tbody>
	</table>
	<div class="tableFooter">
		<div style="float: left; padding: 6px 8px; font-size: 11px; color: #666;">
			<?php
				$iFrom = ($coupons_query_numrows > 0 ? (($page - 1) * 15) + 1 : 0);
				$iTo = min($page * 15, $coupons_query_numrows);
				echo 'Mostrando ' . $iFrom . ' a ' . $iTo . ' de ' . $coupons_query_numrows . ' cupones (' . $aFilterLabels[$sFilter] . ($sSearch !== false ? ': "' . htmlspecialchars($sSearch) . '"' : '') . ')';
			?>
		</div>
		<div style="float: right; position: relative; top: <?php echo ($coupons_query_numrows > 15 ? '-3px' : '4px'); ?>">
			<?php echo $coupons_split->display_links($coupons_query_numrows, 15, MAX_DISPLAY_PAGE_LINKS, $page, tep_get_all_get_params(array('page'))); ?>
		</div>
	</div>
</div>


<?php require(THEME . 'html/footer.php'); ?>
<script type="text/javascript">
	$(".stus").click(function()
	{
		var sStatus = $(this).data("status");
		var dmElmt = $(this);

		if( sStatus == 1 )
			sStatus = 0;
		else
			sStatus = 1;

		$(this).data( "status", sStatus );

		// POST (anti-CSRF); attr() y no data(): un código numérico con ceros a la izquierda se corrompería
		$.ajax({
			url: "coupons.php?action=status",
			type: "POST",
			data: { id: dmElmt.attr("data-id"), status: sStatus }
		}).done(function()
		{
			if( sStatus == 1 )
				dmElmt.html( '<img width="10" height="10" src="images/icon_status_green.gif"/> <img width="10" height="10" src="images/icon_status_red_light.gif"/>' );
			else
				dmElmt.html( '<img width="10" height="10" src="images/icon_status_green_light.gif"/> <img width="10" height="10" src="images/icon_status_red.gif"/>' );
		});
	});

	$("#table").find('a.dlet').click( function(e)
	{
		if(e) { e.preventDefault(); e.stopPropagation(); }

		if( !confirm( "¿Realmente deseas borrar el cupón?\n\nOJO: se borrará también su histórico de usos (dejará de salir en estadísticas). Si solo quieres retirarlo, desactívalo o déjalo caducar." ) )
			return false;

		var dmForm = $('<form method="post"></form>')
			.attr('action', 'coupons.php?action=deleteconfirm&page=<?php echo $page; ?>')
			.append($('<input type="hidden" name="coupons_id">').val($(this).attr('data-cid')));
		$('body').append(dmForm);
		dmForm.submit();
		return false;
	});
</script>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>