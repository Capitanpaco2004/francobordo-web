<?php
	// Tools
	use util\tools as tools;

	// Si nos mandan a instalar cambiamos el modulo por login para que forbidden no salte y podamos instalarlo
	if( array_key_exists( 'action', $_GET ) && in_array( $_GET['action'], array( 'install', 'cron_delete' ) ) )
	{
		$_SERVER['PHP_SELF'] = 'login.php';
		$_SERVER['SCRIPT_FILENAME'] = 'login.php';
	}
	
	// Librerias
	include( 'includes/application_top.php' );

	// Archivo
	define( 'FILENAME_LOG', 'log.php' );
	
	
	// Si nos mandan hacer cron cambiamos el modulo por login para que forbidden no salte y podamos ejecutarlo
	if( array_key_exists( 'action', $_GET ) && in_array( $_GET['action'], array( 'cron_delete' ) ) )
	{
		// Eliminamos
		tep_db_query( 'delete from log where DATE_FORMAT( log_date, "%Y-%m-%d" ) <= adddate( DATE_FORMAT( NOW(), "%Y-%m-%d" ), - ' . (365 * (int)LOG_DELETE_YEAR) . ')' );
		
		// Detenemos
		die();
	}
	
	// Si nos mandan hacer install cambiamos el modulo por login para que forbidden no salte y podamos ejecutarlo
	if( array_key_exists( 'action', $_GET ) && in_array( $_GET['action'], array( 'install' ) ) )
	{
		// Insertamos admin file
		tools::insertAdminFiles( 'log.php', 1 );

		// Insertamos el grupo de configuracion
		$aConfigGroup = tools::insertConfigurationGroup( 'Logs Modificaciones admin', '', 1 );
		
		// Insertamos el grupo de configuracion
		tools::insertConfiguration( 'Número de registros del log', 'LOG_RECORDS', '-1', '', $aConfigGroup->records['configuration_group_id'] );
		tools::insertConfiguration( 'Tiempo en años que se conserva los Logs. Eliminar los logs de administradores, por defecto 2 años', 'LOG_DELETE_YEAR', '2', '', $aConfigGroup->records['configuration_group_id'] );
		tools::insertConfiguration( 'Si deseas guardar logs de los administradores para saber que sea toca, pon 1 por el contrario 0', 'LOG_ACTIVE', '1', '', $aConfigGroup->records['configuration_group_id'] );
				
		// Creamos la tabla log
		tep_db_query( 'CREATE TABLE IF NOT EXISTS log(
			`log_id` INT NOT NULL AUTO_INCREMENT ,
			`admin` varchar(256) COLLATE utf8_spanish2_ci NOT NULL,
			`ip` varchar(20) COLLATE utf8_spanish2_ci NOT NULL,
			`log` text COLLATE utf8_spanish2_ci NOT NULL,
			`get` text COLLATE utf8_spanish2_ci DEFAULT NULL,
			`post` text COLLATE utf8_spanish2_ci DEFAULT NULL,
			`log_date` timestamp NOT NULL DEFAULT current_timestamp(),
			PRIMARY KEY (`log_id`)) ENGINE = InnoDB' );

		// Reset cache
		tools::createCacheFile();
			
		// Detenemos
		die();
	}
	
	
	// Función para darle formato a un array para las variables GET y POST
	function formatArray($aArray)
	{
		// Variables
		$sReturn = '';

		// Si tenemos valores
		if( $aArray != '' )
		{
			// Transformamos a array el JSON
			$aArray = json_decode( $aArray, true );

			// Recorremos el array
			foreach( $aArray as $sKey => $sValue )
			{
				// Si es un array
				if( is_array( $sValue ) )
				{
					// Formateamos el array recursivamente
					$sReturn .= formatArray( json_encode( $sValue ) );
					continue;
				}

				// Formateamos la clave/valor
				$sReturn .= '<b>' . $sKey . ':</b>&nbsp;' . $sValue . '<br />';
			}
		}

		// Retornamos
		return $sReturn;
	}

	// Variables
	global $language_id;

	// Si tenemos una petición GET //

	// Si estamos enviando una acción para vaciar el log
	if( isset( $_GET['action'] ) && $_GET['action'] == 'empty' )
	{
		// Vaciamos el log
		tep_db_query( 'TRUNCATE TABLE log;' );

		// Redireccionamos
		tep_redirect( tep_href_link( FILENAME_LOG, '', 'SSL' ) );
	}

	// Variables
	$sWhere = '';
	$sUser = false;
	$sIP = false;
	$sLog = false;
	$sGET = false;
	$sPOST = false;
	$sDateFrom = false;
	$sDateTo = false;
	$bFilter = false;

	// Si estamos filtrando por usuario
	if( isset( $_GET['user'] ) )
		$sUser = ($_GET['user'] != '-1' && $_GET['user'] != '' ? $_GET['user'] : false);

	// Si estamos filtrando por IP
	if( isset( $_GET['ip'] ) )
		$sIP = ($_GET['ip'] != '' ? $_GET['ip'] : false);

	// Si estamos filtrando por log
	if( isset( $_GET['log'] ) )
		$sLog = ($_GET['log'] != '' ? $_GET['log'] : false);

	// Si estamos filtrando por GET
	if( isset( $_GET['get'] ) )
		$sGET = ($_GET['get'] != '' ? $_GET['get'] : false);

	// Si estamos filtrando por POST
	if( isset( $_GET['post'] ) )
		$sPOST = ($_GET['post'] != '' ? $_GET['post'] : false);

	// Si estamos filtrando por Fecha inicio
	if( isset( $_GET['date_from'] ) )
		$sDateFrom = ($_GET['date_from'] != '' ? $_GET['date_from'] : false);

	// Si estamos filtrando por Fecha fin
	if( isset( $_GET['date_to'] ) )
		$sDateTo = ($_GET['date_to'] != '' ? $_GET['date_to'] : false);

	// Comprobamos si tenemos filtro
	$bFilter = ($sUser != false || $sIP != false || $sLog != false || $sGET != false || $sPOST != false || $sDateFrom != false || $sDateTo != false);

	// Si tenemos un filtro
	if( $bFilter )
	{
		// Filtros
		$sWhere .= ($sUser !== false ? ($sWhere != '' ? 'AND ' : '') . 'admin = "' . $sUser . '" ' : '');
		$sWhere .= ($sIP !== false ? ($sWhere != '' ? 'AND ' : '') . 'ip = "' . $sIP . '" ' : '');
		$sWhere .= ($sLog !== false ? ($sWhere != '' ? 'AND ' : '') . 'log LIKE "%' . $sLog . '%" ' : '');

		// Filtro GET
		if( $sGET !== false )
		{
			// Variable auxiliar
			$sAux = '';

			// Separamos la búsqueda GET
			$aGETs = explode( ' ', $sGET );

			// Recomponemos la búsqueda
			foreach( $aGETs as $aGET )
			{
				// Limpiamos
				$aGET = preg_replace( '/:$/i', '', $aGET );
				// Añadimos
				$sAux .= '%' . $aGET;
			}
			$sAux .= '%';

			// Añadimos el filtro
			$sWhere .= ($sWhere != '' ? 'AND ' : '') . '`get` LIKE "%' . $sAux . '%" ';
		}

		// Filtro POST
		if( $sPOST !== false )
		{
			// Variable auxiliar
			$sAux = '';

			// Separamos la búsqueda GET
			$aPOSTs = explode( ' ', $sPOST );

			// Recomponemos la búsqueda
			foreach( $aPOSTs as $aPOST )
			{
				// Limpiamos
				$aPOST = preg_replace( '/:$/i', '', $aPOST );
				// Añadimos
				$sAux .= '%' . $aPOST;
			}
			$sAux .= '%';

			// Añadimos el filtro
			$sWhere .= ($sWhere != '' ? 'AND ' : '') . '`post` LIKE "%' . $sAux . '%" ';
		}

		// Fecha inicio y fin iguales
		if( $sDateFrom !== false && $sDateTo !== false && $sDateFrom == $sDateTo )
		{
			// Variable auxiliar
			$sAux = $sDateFrom;

			// Convertimos a formato para comparar
			$sAux = explode( '/', $sAux );
			$sAux = $sAux[2] . '-' . $sAux[1] . '-' . $sAux[0];

			// Añadimos el filtro
			$sWhere .= ($sWhere != '' ? 'AND ' : '') . 'DATE( log_date ) = "' . $sAux . '" ';
		}
		// Fecha inicio y fin diferentes
		else
		{
			// Filtro fecha inicio
			if( $sDateFrom !== false )
			{
				// Variable auxiliar
				$sAux = $sDateFrom;

				// Convertimos a formato para comparar
				$sAux = explode( '/', $sAux );
				$sAux = $sAux[2] . '-' . $sAux[1] . '-' . $sAux[0];

				// Añadimos el filtro
				$sWhere .= ($sWhere != '' ? 'AND ' : '') . 'DATE( log_date ) >= "' . $sAux . '" ';
			}

			// Filtro fecha fin
			if( $sDateTo !== false )
			{
				// Variable auxiliar
				$sAux = $sDateTo;

				// Convertimos a formato para comparar
				$sAux = explode( '/', $sAux );
				$sAux = $sAux[2] . '-' . $sAux[1] . '-' . $sAux[0];

				// Añadimos el filtro
				$sWhere .= ($sWhere != '' ? 'AND ' : '') . 'DATE( log_date ) <= "' . $sAux . '" ';
			}
		}

		$sWhere = ' WHERE ' . $sWhere;
	}

	// Obtenemos a los administradores
	$aAdmins = tep_db_query( 'SELECT admin_id, admin_firstname, admin_lastname FROM admin ORDER BY admin_firstname ASC' );

	// Array de usuarios
	$aUsers = array();
	$aUsers[] = array( 'id' => -1, 'text' => 'Sin filtro' );
	$sUser = false;

	// Recorremos los administradores y los metemos en un array
	while( $aAdmin = tep_db_fetch_array( $aAdmins ) )
	{
		// Añadimos al array de usuarios
		$aUsers[] = array( 'id' => $aAdmin['admin_firstname'] . ' ' . $aAdmin['admin_lastname'], 'text' => $aAdmin['admin_firstname'] . ' ' . $aAdmin['admin_lastname'] );

		// Si obtenemos al usuario del filtro actual
		if( isset( $_GET['user'] ) && $aAdmin['admin_id'] == $_GET['user'] )
			$sUser = $aAdmin['admin_firstname'] . ' ' . $aAdmin['admin_lastname'];
	}

	// Header
	include( 'theme/web/html/header.php' );

	// Obtenemos el log completo
	$sSql = 'SELECT log_id, admin, ip, log, `get`, post, DATE_FORMAT( log_date, "%d/%m/%Y %H:%i:%s" ) AS log_date FROM log ' . $sWhere . ' ORDER BY log_id DESC';

	// Le quitamos los tabuladores y saltos de linea para que splitpageesult funcione con el SQL
	$sSql = preg_replace( '/[\r\n\t]+/', ' ', $sSql );
	$sGetPage = (isset( $_GET['page'] ) ? tep_db_prepare_input( $_GET['page'] ) : '1');
	$aDatoSplit = new splitPageResults( $sGetPage, 100, $sSql, $nAux, 'SELECT COUNT(log_id) AS total FROM log ' . $sWhere . ';' );
	$aLogs = tep_db_query( $sSql );
?>

<div class="wrapper" style="margin: 0px 30px;">
	<div class="toolbarHead">
		<div class="hdr-tlbr">
			<h1 class="pageHeading ftitl" style="top: 6px;">Log</h1>

			<h2 class="stitl" style="top: 13px;">Log de administradores</h2>

			<div class="btn-right">
				<a href="<?php echo tep_href_link( FILENAME_LOG, 'action=empty' ); ?>" title="Vaciar log" onclick="if( ! confirm( '¿Seguro que deseas vaciar el log?' ) ) return false;"><img src="images/icons/icon_empty.png" class="dx-hovr"></a>
				<a href="javascript:void(0);" title="Imprimir log" onclick="click_print();"><img src="images/icons/icon_print.png" class="dx-hovr"></a>

				<?php if( $bFilter ): ?>
				<a href="<?php echo FILENAME_LOG; ?>" title="Limpiar filtro"><img src="images/icons/icon_clear_filter.png" class="dx-hovr"></a>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="box-tbl to_prnt" style="width: 100%">
		<div class="box-head">
			<h6>Lista de logs</h6>
			<a title="Filtrar" data-id="1" href="javascript:void(0);" class="tOptions filter-togle"><img alt="" src="theme/web/images/icons/usual/icon-cog3.png"></a>

			<div class="clear"></div>
		</div>

		<div data-id="1" class="fluid grid tablePars" style="display: <?php echo ($bFilter ? 'block' : 'none'); ?>;">
			<form name="newsletter_list" action="<?php echo FILENAME_LOG; ?>" method="get">
				<div class="grid12">
					<div style="border: none; padding: 7px 16px;" class="formRow">
						<div class="grid2"><label style="color: #5f5f5f;">Usuario:</label></div>

						<div class="grid10">
							<div style="position:relative;" class="smallText">
								<?php echo tep_draw_pull_down_menu( 'user', $aUsers, (isset( $_GET['user'] ) ? $_GET['user'] : '') ); ?>
							</div>
						</div>

						<div class="clear"></div>
					</div>
					<div style="border: none; padding: 7px 16px;" class="formRow">
						<div class="grid2"><label style="color: #5f5f5f;">IP:</label></div>

						<div class="grid2">
							<div style="position:relative;" class="smallText">
								<?php echo tep_draw_input_field( 'ip', (isset( $_GET['ip'] ) ? $_GET['ip'] : '') ); ?>
							</div>
						</div>

						<div class="clear"></div>
					</div>
					<div style="border: none; padding: 7px 16px;" class="formRow">
						<div class="grid2"><label style="color: #5f5f5f;">Log:</label></div>

						<div class="grid6">
							<div style="position:relative;" class="smallText">
								<?php echo tep_draw_input_field( 'log', (isset( $_GET['log'] ) ? $_GET['log'] : '') ); ?>
							</div>
						</div>

						<div class="clear"></div>
					</div>
					<div style="border: none; padding: 7px 16px;" class="formRow">
						<div class="grid2"><label style="color: #5f5f5f;">GET:</label></div>

						<div class="grid6">
							<div style="position:relative;" class="smallText">
								<?php echo tep_draw_input_field( 'get', (isset( $_GET['get'] ) ? $_GET['get'] : '') ); ?>
							</div>
						</div>

						<div class="clear"></div>
					</div>
					<div style="border: none; padding: 7px 16px;" class="formRow">
						<div class="grid2"><label style="color: #5f5f5f;">POST:</label></div>

						<div class="grid6">
							<div style="position:relative;" class="smallText">
								<?php echo tep_draw_input_field( 'post', (isset( $_GET['post'] ) ? $_GET['post'] : '') ); ?>
							</div>
						</div>

						<div class="clear"></div>
					</div>
					<div style="border: none; padding: 7px 16px;" class="formRow">
						<div class="grid2"><label style="color: #5f5f5f;">Fecha:</label></div>

						<div class="grid1">
							<div style="position:relative" class="smallText">
								<?php echo tep_draw_input_field( 'date_from', (isset( $_GET['date_from'] ) ? $_GET['date_from'] : ''), 'class="dxdatepicker"' ); ?>
							</div>
						</div>
						<div class="grid1">
							<div style="position:relative" class="smallText">
								<?php echo tep_draw_input_field( 'date_to', (isset( $_GET['date_to'] ) ? $_GET['date_to'] : ''), 'class="dxdatepicker"' ); ?>
							</div>
						</div>

						<div class="clear"></div>
					</div>

					<div style="border: none; padding: 7px 16px;" class="formRow noBorderB"><div class="grid12"><input type="submit" style="cursor: pointer;" class="buttonS bGreen" value="Filtrar"></div><div class="clear"></div></div>
				</div>
			</form>
		</div>

		<form name="newsletter_list" action="newsletter_lista.php" method="post">
			<table id="table" class="tAlt wGeneral tDefault" width="100%" cellspacing="0" cellpadding="0">
				<thead>
					<tr>
						<td width="80">Nº de log</td>
						<td>Usuario</td>
						<td>IP</td>
						<td>Log</td>
						<td>GET</td>
						<td>POST</td>
						<td>Fecha</td>
					</tr>
				</thead>

				<tbody>
				<?php while( $aLog = tep_db_fetch_array( $aLogs ) ): ?>
					<tr style="color: #5f5f5f;">
						<td><?php echo '#' . $aLog['log_id']; ?></td>
						<td><?php echo $aLog['admin']; ?></td>
						<td><?php echo $aLog['ip']; ?></td>
						<td><?php echo $aLog['log']; ?></td>
						<td><?php echo formatArray( $aLog['get'] ); ?></td>
						<td><?php echo formatArray( $aLog['post'] ); ?></td>
						<td style="text-align: center;"><?php echo $aLog['log_date']; ?></td>
					</tr>
				<?php endwhile; ?>
				</tbody>
			</table>
		</form>

		<?php echo $aDatoSplit->showPaginateTable( tep_get_all_get_params( array('page') ) ); ?>
	</div>

	<?php require(THEME . 'html/footer.php'); ?>
</div>
<script>
	function click_print()
	{
		window.print();
		return false;
	};
</script>

<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>