<?php
	include( 'includes/application_top.php' );

	// Variables
	$sPageHeading = '';
	$sAction = $_GET['a'];
	$sHtml = '';
	$sUrl = tep_href_link( 'stores.php' );

	switch( $sAction )
	{
		case 'status':
			// Variables
			$sGetId = tep_db_prepare_input( $_GET['id'] );
			$sGetStatus = tep_db_prepare_input( $_GET['status'] );
			$aSql = array(
				'store_status' => $sGetStatus
			);

			tep_db_perform( 'store', $aSql, 'update', 'id_store = ' . $sGetId );
			exit();
		break;
		case 'delete':
			// Variables
			$sGetId = tep_db_prepare_input( $_GET['id'] );

			// Consulta
			$aDatos = tep_db_query( 'select store_name from store where id_store = ' . $sGetId );
			$aDato = tep_db_fetch_array( $aDatos );

			// Eliminamos
			tep_db_query( 'delete from store where id_store = ' . $sGetId );

			// Redireccionamos
			$messageStack->add_session( 'La tienda "' . $aDato['store_name'] . '" se ha eliminado correctamente', 'success' );
			tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;

		case 'update':
		case 'add':
			// Variables //
			$sPostId =  tep_db_prepare_input( $_POST['id'] );
			$sGetAction = tep_db_prepare_input( $_GET['a'] );
			$sPostStoreName = tep_db_prepare_input( $_POST['store_name'] );
			$sPostStoreStatus = tep_db_prepare_input( $_POST['store_status'] );
			$sPostStoreAddress = tep_db_prepare_input( $_POST['store_address'] );
			$sPostStoreCost = tep_db_prepare_input( $_POST['store_cost'] );

			// Campos
			$aSql = array(
				'store_name' => $sPostStoreName,
				'store_address' => $sPostStoreAddress,
				'store_cost' => $sPostStoreCost,
				'store_status' => $sPostStoreStatus
			);
			
			// Si es para insertar uno nuevo
			if( $sGetAction == 'add' )
			{
				$sTextSuccess = 'La tienda se ha insertado correctamente';
				tep_db_perform( 'store', $aSql );
			}
			// Editar
			else
			{
				$sTextSuccess = 'La tienda se ha editado correctamente';
				tep_db_perform( 'store', $aSql, 'update', 'id_store = ' . $sPostId );
			}

			$messageStack->addSession( 'mensaje', $sTextSuccess, 'success' );
			tep_redirect( $sUrl );
		break;
	
		case 'add_form':
		case 'update_form':
			// Variables
			$sGetId = tep_db_prepare_input( $_GET['id'] );
			$sHtml = '';
			$sTitulo = 'Nueva tienda';
			$aComboEstados = array(
				array( 'id' => 1, 'text' => 'Activado' ),
				array( 'id' => 0, 'text' => 'Desactivado' )
			);
		
			// Objeto info
			$oInfo = createObjectInfo( 'store' );

			// Si estamos editamos cargamos los valores
			if( $sAction == 'update_form' )
			{			
				// Consulta tienda
				$aDatos = tep_db_query( 'select id_store, store_name, store_address, store_cost, store_status
										 from store
										 where id_store = ' . $sGetId );				

				// Obtenemos los datos
				$aDato = tep_db_fetch_array( $aDatos );
				$oInfo->addProperties( $aDato );	

				// Titular
				$sTitulo = 'Editar tienda ' . $oInfo->store_name;
			}

			$sHtml .= '<form name="store" action="' . $sUrl . '?a=' . ($sAction == 'update_form' ? 'update' : 'add') . '" method="post">';
				// Si estamos actualizando añadimos el ID
				if( $sAction == 'update_form' )
					$sHtml .= '<input type="hidden" name="id" value="' . $sGetId . '" />';

				$sHtml .= '<div>
					<div class="toolbarHead">
						<div class="hdr-tlbr">
							<h1 class="pageHeading" style="top: 12px;">' . $sTitulo . '</h1>
							<div class="btn-right">
								<a href="javascript:void(0);" onclick="$(this).closest(\'form\').submit()"><img class="dx-hovr" src="images/icons/icon_save.png"></a>
								<a href="' . $sUrl . '"><img class="dx-hovr" src="images/icons/icon_back.png" title="Volver sin guardar"></a>
							</div>
						</div>
					</div>
				</div>';
			
				$sHtml .= '<div class="tab-new" data-id="1" style="display: block;">';
					
					$sHtml .= '<div class="fluid grid">
						<div class="box-tbl grid12">
							<div class="box-head">
								<h6>Tienda</h6>
								<div class="clear"></div>
							</div>
							<div class="formRow">
								<div style="margin: 0px;" class="grid3">
									<label>Nombre:</label>
								</div>
								<div class="grid9">
									' . tep_draw_input_field( 'store_name', $oInfo->store_name, '' ) . '
								</div>
								<div class="clear"></div>
							</div>
							<div class="formRow">
								<div style="margin: 0px;" class="grid3">
									<label>Dirección:</label>
								</div>
								<div class="grid9">
									' . tep_draw_input_field( 'store_address', $oInfo->store_address, '' ) . '
								</div>
								<div class="clear"></div>
							</div>
							<div class="formRow">
								<div style="margin: 0px;" class="grid3">
									<label>Coste (sin iva):</label>
								</div>
								<div class="grid9">
									' . tep_draw_input_field( 'store_cost', $oInfo->store_cost, '' ) . '
								</div>
								<div class="clear"></div>
							</div>
							<div class="formRow">
								<div style="margin: 0px;" class="grid3">
									<label>Estado:</label>
								</div>
								<div class="grid9">
									' . tep_draw_pull_down_menu( 'store_status', $aComboEstados, ($oInfo->store_status == '' ? 1 : $oInfo->store_status) ) . '
								</div>
								<div class="clear"></div>
							</div>
						</div>
					</div>';
				$sHtml .= '</div>';
			$sHtml .= '</form>';
		break;

		default:
			// Titular
			$sPageHeading = 'Tiendas';
		
			// Consulta
			$aDatos = tep_db_query( 'select id_store, store_name, store_address, store_cost, store_status
									 from store
									 order by store_name asc' );
			
			$sHtml = '<div class="box-tbl" style="width: 100%">';
				$sHtml .= '<div class="box-head">
					<h6>Tiendas</h6>
					<div class="clear"></div>
				</div>';
				
				$sHtml .= '<table id="table" class="tAlt wGeneral tDefault" width="100%" cellspacing="0" cellpadding="0">
					<thead>
						<tr>
							<td width="15">ID</td>
							<td style="text-align: left;">Tienda</td>
							<td style="text-align: left;">Dirección</td>
							<td style="text-align: left;">Coste</td>
							<td width="75">Estado</td>
							<td width="195">Acciones</td>
						</tr>
					</thead>
					<tbody>';		
					
						while( $aDato = tep_db_fetch_array( $aDatos ) )
						{		
							$sHtml .= '<tr>';
								$sHtml .= '<td align="center">' . $aDato['id_store'] . '</td>';
								$sHtml .= '<td align="left">' . $aDato['store_name'] . '</td>';
								$sHtml .= '<td  align="left">' . $aDato['store_address'] . '</td>';
								$sHtml .= '<td  align="left">' . $aDato['store_cost'] . '</td>';
								$sHtml .= '<td align="center">
									<div style="cursor:pointer" class="stus" data-id=' . $aDato['id_store'] . ' data-status="' . $aDato['store_status'] . '">
										<img width="10" height="10" src="images/icon_status_green' . ($aDato['store_status'] == 0 ? '_light' : '') . '.gif" />
										<img width="10" height="10" src="images/icon_status_red' . ($aDato['store_status'] == 1 ? '_light' : '') . '.gif" />
									</div>
								</td>';
								$sHtml .= '<td align="center">
									<div class="btn-group" style="display: inline-block; margin-bottom: -4px;">
										<a class="buttonS bDefault" data-toggle="dropdown" href="#">Acciones<span class="caret"></span></a>
										<ul class="dropdown-menu">
											<li><a href="' . $sUrl . '?a=update_form&id=' . $aDato['id_store'] . '"><span class="icos-pencil"></span>Editar</a></li>
											<li><a class="dlet" href="' . $sUrl . '?a=delete&id=' . $aDato['id_store'] . '"><span class="icos-trash"></span>Borrar</a></li>
										</ul>
									</div>
								</td>';
							$sHtml .= '</tr>';
						}
			
				$sHtml .= '</tbody>
				</table>
			</div>';

			$sHtml .= '<div style="margin-top: 35px; text-align: right;" class="fluid grid">
				<div class="grid12">
					<a class="buttonS bGreen" href="' . $sUrl . '?a=add_form">Añadir nueva tienda</a>
				</div>
			</div>';
		break;
	}

	include( THEME . 'html/header.php' );

	if( $sPageHeading != '' )
		echo '<h1 class="pageHeading" style="padding: 18px 0 0px">' . $sPageHeading . '</h1>';

	echo $sHtml;

	include( THEME . 'html/footer.php' );
	
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
				url: "' . $sUrl . '?a=status&id=" + $(this).data("id") + "&status=" + sStatus
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

			if( confirm( "¿Realmente deseas borrar la tienda?" ) )
				return true;
			else
				return false;
		});
				
		$( window ).resize(function()
		{
			$("#box-right").attr( "style", "" );
		
			if( $("#box-right").height() < $("#box-left").height() )
				$("#box-right").height( $("#box-left").height() - 365 );				
		});
	</script>';
	
	include(DIR_WS_INCLUDES . 'application_bottom.php' );
?>