<?php
	require('includes/application_top.php');
	require( DIR_WS_FUNCTIONS.'coupons.php' );

	$action = (isset($_GET['action']) ? $_GET['action'] : '');
	$error = (isset($_GET['error']) ? $_GET['error'] : '');
	$message = (isset($_GET['message']) ? $_GET['message'] : '');
	$coupons_id = ( !empty( $_POST['coupons_id'] ) ? tep_db_input( $_POST['coupons_id'] ) : ( !empty( $_GET['cID'] ) ? tep_db_input( $_GET['cID'] ) : kgt_create_random_coupon() ) );

	if( tep_not_null( $error ) ) 
		$messageStack->add( $error, 'error' );

	if( tep_not_null( $message ) )
		$messageStack->add( $message, 'success' );

	if (tep_not_null($action))
	{
		switch($action) 
		{
			case 'status':
				// Variables
				$sGetId = tep_db_prepare_input( $_GET['id'] );
				$sGetStatus = tep_db_prepare_input( $_GET['status'] );
				$aSql = array(
					'coupons_status' => $sGetStatus
				);

				tep_db_perform( 'discount_coupons', $aSql, 'update', 'coupons_id = "' . $sGetId . '"' );
				exit();
			break;
		
			case 'insert':
				//some error checking:
				if( empty( $_POST['coupons_discount_amount'] ) )
				{
					$messageStack->add( ERROR_DISCOUNT_COUPONS_NO_AMOUNT, 'error' );
					$action = 'new';
				}
				else 
				{
					tep_db_query( $sql = "insert into " . TABLE_DISCOUNT_COUPONS . " values (
						'".$coupons_id."', 
						'".tep_db_input( $_POST['coupons_description'] )."', 
						'".tep_db_input( $_POST['coupons_discount_amount'] )."', 
						'" .tep_db_input( $_POST['coupons_discount_type'] )."', 
						".( !empty( $_POST['coupons_date_start'] ) ? '"'.kgt_parse_date( $_POST['coupons_date_start'], DATE_FORMAT_SHORT ).'"' : 'null' ).", 
						".( !empty( $_POST['coupons_date_end'] ) ? '"'.kgt_parse_date( $_POST['coupons_date_end'], DATE_FORMAT_SHORT ).'"' : 'null' ).", 
						".( !empty( $_POST['coupons_max_use'] ) ? (int)$_POST['coupons_max_use'] : 0 ).", 
						".( !empty( $_POST['coupons_min_order'] ) ? tep_db_input( $_POST['coupons_min_order'] ) : 0 ).", 
						".( !empty( $_POST['coupons_min_order'] ) ? "'".tep_db_input( $_POST['coupons_min_order_type'] )."'" : 'null' ).", 
						".( !empty( $_POST['coupons_number_available'] ) ? (int)$_POST['coupons_number_available'] : 0 ).") " );

					tep_redirect( tep_href_link( FILENAME_DISCOUNT_COUPONS, 'page=' . $_GET['page'] . '&cID=' . $coupons_id ) );
				}
			break;

			case 'update':
				tep_db_query($sql = "update " . TABLE_DISCOUNT_COUPONS . " set
					coupons_discount_amount = '".tep_db_input( $_POST['coupons_discount_amount'] )."',
					coupons_discount_type = '".tep_db_input( $_POST['coupons_discount_type'] )."',
					coupons_description = '" . tep_db_input( $_POST['coupons_description'] ) . "',
					coupons_date_start = " . ( !empty( $_POST['coupons_date_start'] ) ? '"'.kgt_parse_date( $_POST['coupons_date_start'], DATE_FORMAT_SHORT ).'"' : 'null' ) . ",
					coupons_date_end = " .( !empty( $_POST['coupons_date_end'] ) ? '"'.kgt_parse_date( $_POST['coupons_date_end'], DATE_FORMAT_SHORT ).'"' : 'null' ). ",
					coupons_max_use = " .( !empty( $_POST['coupons_max_use'] ) ? (int)$_POST['coupons_max_use'] : 0 ). ",
					coupons_min_order = ".( !empty( $_POST['coupons_min_order'] ) ? tep_db_input( $_POST['coupons_min_order'] ) : 0 ).",
					coupons_min_order_type = ".( !empty( $_POST['coupons_min_order'] ) ? "'".tep_db_input( $_POST['coupons_min_order_type'] )."'" : 'null' ).",
					coupons_number_available = " .( !empty( $_POST['coupons_number_available'] ) ? (int)$_POST['coupons_number_available'] : 0 ). "
					where coupons_id = '" . $coupons_id . "'");
				
				tep_redirect( tep_href_link( FILENAME_DISCOUNT_COUPONS, 'page=' . $_GET['page'] . '&cID=' . $coupons_id ) );
			break;

			case 'deleteconfirm':
				tep_db_query($sql = "delete from " . TABLE_DISCOUNT_COUPONS . " where coupons_id = '" .$coupons_id. "'");
				tep_db_query($sql = "delete from " . TABLE_DISCOUNT_COUPONS_TO_ORDERS . " where coupons_id = '" .$coupons_id. "'");

				tep_redirect(tep_href_link(FILENAME_DISCOUNT_COUPONS, 'page=' . $_GET['page']));
			break;
		}
	}
	
	require(DIR_WS_CLASSES . 'currencies.php');
	$currencies = new currencies();

	// Filtro y busqueda
	$sFilter = (isset( $_GET['filter'] ) && $_GET['filter'] != '' ? tep_db_prepare_input( $_GET['filter'] ) : false);
	$sSearch = (isset( $_GET['search'] ) && $_GET['search'] != '' ? trim( tep_db_prepare_input( $_GET['search'] ) ) : false);
?>

<?php require(THEME . 'html/header.php'); ?>
<table border="0" width="100%"><tr><td>

<div class="toolbarHead">
	<div class="hdr-tlbr">
		<h1 class="pageHeading" style="top: 12px;">Cupones descuento</h1>
		<div class="btn-right">
			<form action="<?php echo tep_href_link( FILENAME_DISCOUNT_COUPONS ); ?>" method="get">
				<div style="position: absolute; right: 120px; width: 268px; top: -6px; height: 75px;">
					<span style="font-size: 11px; color: rgb(99, 99, 99); position: absolute; top: 20px;">Ver:</span><select style="position: absolute; left: 49px; height: 22px; top: 12px; width: 104px;" name="filter"><option value="all">Todos</option><option value="enabled"<?php echo ($sFilter == 'enabled' ? ' selected="selected"' : ''); ?>>Solo activos</option><option value="disabled"<?php echo ($sFilter == 'disabled' ? ' selected="selected"' : ''); ?>>Solo inactivos</option></select>
					<input type="submit" value="Filtrar" style="background: none repeat scroll 0% 0% rgb(108, 108, 108); color: rgb(255, 255, 255); border: medium none; cursor: pointer; font-size: 10px; border-radius: 3px; text-transform: uppercase; text-align: center; position: absolute; margin-left: 20px; font-weight: bold; height: 21px; width: 109px; top: 12px; right: 0px;">
					<span style="position: absolute; font-size: 11px; color: rgb(99, 99, 99); top: 51px;">Buscar:</span><input type="text" style="position: absolute; top: 42px; width: 220px; right: 0px;" value="<?php echo $sSearch; ?>" name="search">
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
				<td width="100">Cantidad</td>
				<td width="75">Estado</td>
				<td width="195">Acciones</td>
			</tr>
		</thead>
		<tbody>
			<?php
				$coupons_query_raw = "select * from " . TABLE_DISCOUNT_COUPONS . " cd " . (( $sFilter !== false && $sFilter != 'all' ) || $sSearch !== false ? ' WHERE ' : '') . ($sFilter !== false && ( $sFilter == 'enabled' || $sFilter == 'disabled' ) ? 'coupons_status = ' . ($sFilter == 'enabled' ? '1' : '0') : '') . (( $sFilter !== false && $sFilter != 'all' ) && $sSearch !== false ? ' AND ' : '') . ($sSearch !== false ? ' coupons_id LIKE "%' . $sSearch . '%"' : '') . " order by cd.coupons_date_end, coupons_date_start";
				$coupons_split = new splitPageResults( $_GET['page'], 15, $coupons_query_raw, $coupons_query_numrows );
				$coupons_query = tep_db_query($coupons_query_raw);
			?>

			<?php while( $coupons = tep_db_fetch_array( $coupons_query ) ): ?>
				<tr>
					<td><?php echo $coupons['coupons_id'].' <small>'.( !empty( $coupons['coupons_description'] ) ? '( '.$coupons['coupons_description'].' )' : '' ) .'</small>'; ?></td>
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
						<?php echo ( $coupons['coupons_number_available'] != 0 ? $coupons['coupons_number_available'] : TEXT_DISPLAY_UNLIMITED ); ?>
					</td>
					<td align="center">
						<div style="cursor:pointer" class="stus" data-id="<?php echo $coupons['coupons_id']; ?>" data-status="<?php echo $coupons['coupons_status']; ?>">
							<img width="10" height="10" src="images/icon_status_green<?php echo ($coupons['coupons_status'] == 0 ? '_light' : ''); ?>.gif" />
							<img width="10" height="10" src="images/icon_status_red<?php echo ($coupons['coupons_status'] == 1 ? '_light' : ''); ?>.gif" />
						</div>
					<td align="center">
						<div style="display: inline-block; margin-bottom: -4px;" class="btn-group">
							<a href="#" data-toggle="dropdown" class="buttonS bDefault">Acciones<span class="caret"></span></a>
							<ul class="dropdown-menu">
								<li><a href="<?php echo tep_href_link('coupons_create.php', 'page=' . $_GET['page'] . '&coupon=' . $coupons['coupons_id']); ?>"><span class="icos-pencil"></span>Editar</a></li>							
								<li><a href="<?php echo tep_href_link(FILENAME_DISCOUNT_COUPONS, 'page=' . $_GET['page'] . '&cID=' . $coupons['coupons_id'] . '&action=deleteconfirm'); ?>" class="dlet"><span class="icos-trash"></span>Eliminar</a></li>
							</ul>
						</div>
					</td>
				</tr>
			<?php endwhile; ?>
		</tbody>
	</table>
	<div class="tableFooter">
		<div style="float: right; position: relative; top: <?php echo ($num_pages > 1 ? '-3px' : '4px'); ?>">
			<?php echo $coupons_split->display_links($coupons_query_numrows, 15, MAX_DISPLAY_PAGE_LINKS, $_GET['page']); ?>
		</div>
	</div>
</div>


<?php
	require(THEME . 'html/footer.php');
	
	echo '<script type="text/javascript">
		$(".stus").click(function()
		{
			var sStatus = $(this).data("status");
			var dmElmt = $(this);
			
			if( sStatus == 1 )
				sStatus = 0;
			else
				sStatus = 1;
		
			$(this).data( "status", sStatus );
		
			$.ajax({
				url: "coupons.php?action=status&id=" + $(this).data("id") + "&status=" + sStatus
			}).done(function()
			{
				if( sStatus == 1 )
					dmElmt.html( \'<img width="10" height="10" src="images/icon_status_green.gif"/> <img width="10" height="10" src="images/icon_status_red_light.gif"/>\');
				else
					dmElmt.html( \'<img width="10" height="10" src="images/icon_status_green_light.gif"/> <img width="10" height="10" src="images/icon_status_red.gif"/>\');
			});
		});
	
		$("#table").find(\'a[class="dlet"]\').click( function(e)
		{
			if(e)e.stopPropagation();

			if( confirm( "¿Realmente deseas borrar el cupón?" ) )
				return true;
			else
				return false;
		});
	</script>';
	require(DIR_WS_INCLUDES . 'application_bottom.php');
?>