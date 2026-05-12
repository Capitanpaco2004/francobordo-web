<?php
	require('includes/application_top.php');

	// Sin limite de memoria y tiempo
	ini_set( 'memory_limit', '-1' );
	ini_set( 'max_execution_time', -1 );
	set_time_limit( -1 );

	// Variables //
	$sUrlPage = 'import_log.php';
	$sGetAction = tep_db_prepare_input( $_GET['a'] ?? '' );

	// Actions //
	switch( $sGetAction )
	{
		// Cargar Javascript //
		case 'load_javascript':
			echo '
			function goOnLoad(){};

			// Botones eliminar
			$(".delete").click(function(e)
			{
				e.stopPropagation();

				return confirm( "¿Deseas eliminar el log?" );
			});

			$(".file_delete_all").click(function(e)
			{
				e.stopPropagation();

				return confirm( "¿Deseas eliminar todos los logs?" );
			});

			// Seleccionador de fechas
			$(".date-range").click(function(e)
			{
				// Obtenemos los elementos
				var dmFrom = $(this).parent().find(".from");
				var dmTo = $(this).parent().find(".to");

				// Obtenemos las 2 fechas que estamos filtrando
				var sFrom = dmFrom.val();
				var sTo = dmTo.val();

				// Limpiamos fechas
				dmFrom.val("");
				dmTo.val("");

				// Desde....
				dmFrom.datepicker({
					dateFormat: "dd/mm/yy",
					changeMonth: true,
					changeYear: true,
					yearRange: "-100:+0",
					onClose: function( selectedDate )
					{
						// Si al cerrar tenemos seleccionada alguna fecha mostramos hasta....
						if( dmFrom.val() != "" )
							dmTo.datepicker("show");
						else // Si no ponemos las que teniamos
						{
							dmFrom.val( sFrom );
							dmTo.val( sTo );
						}
					}
				});

				// Hasta..
				dmTo.datepicker({
					dateFormat: "dd/mm/yy",
					changeMonth: true,
					changeYear: true,
					yearRange: "-100:+0",
					beforeShow: function()
					{
						// Antes de mostrar cargamos la restricción de fecha
						var aFecha = dmFrom.val().split( "/" );
						var minDate = new Date( parseInt( aFecha[2] ), parseInt( aFecha[1] ) - 1, parseInt( aFecha[0] ) );

						return { minDate: minDate };
					},
					onClose: function( selectedDate )
					{
						// Si contenemos una fecha enviamos el form
						if( dmTo.val() != "" )
						{}//$("#date-range-form").submit();
						else // Si no ponemos las que teniamos
						{
							dmFrom.val( sFrom );
							dmTo.val( sTo );
						}
					}
				});

				// Al hacer click siempre mostramos antes el desde...
				dmFrom.datepicker("show");
			});
			';

			exit();
		break;

		case 'file_delete_all':
			// Variables
			$sFechaInicio = tep_db_prepare_input( $_GET['fi'] );
			$sFechaFin = tep_db_prepare_input( $_GET['ff'] );

			// Archivos
			$dir_delete = getcwd() .  '/../temp/log_minderest/';
			$aFiles = is_dir($dir_delete) ? scandir($dir_delete) : [];

			// Recorremos los archivos
			foreach( $aFiles as $sFile )
			{
				// Denegamos
				if( in_array( $sFile, array( '.', '..' ) ) )
					continue;

				preg_match( '/(?<fecha>\d+-\d+-\d+_\d+-\d+-\d+)/i', $sFile, $aMatch );
				$aFecha = explode( '-', str_replace( '_', '-', $aMatch['fecha'] ) );
				$sFecha = $aFecha[0] . '/' . $aFecha[1] . '/' . $aFecha[2] . ' ' . $aFecha[3] . ':' . $aFecha[4] . ':' . $aFecha[5];

				// Si tenemos fechas, mostramos solo los logs comprendidos entre estas
				if( $sFechaInicio != '' && $sFechaFin != '' )
				{
					if( strtotime( $sFechaInicio ) > strtotime( $aFecha[2] . '-' . $aFecha[1] . '-' . $aFecha[0] ) || strtotime( $sFechaFin ) < strtotime( $aFecha[2] . '-' . $aFecha[1] . '-' . $aFecha[0] ) )
						continue;
				}

				// Eliminamos
				unlink( getcwd() . '/../temp/log_minderest/' . $sFile );
			}

			// Redireccionamos
			$messageStack->add_session( 'Se han eliminado todos los logs de Minderest correctamente', 'success' );
			tep_redirect( tep_href_link( $sUrlPage ) );
		break;

		case 'file_delete':
			// Variables
			$sGetFile = tep_db_prepare_input( $_GET['file'] );

			// Eliminamos
			if( file_exists( getcwd() . '/../temp/log_minderest/' . $sGetFile ) )
				unlink( getcwd() . '/../temp/log_minderest/' . $sGetFile );

			// Redireccionamos
			$messageStack->add_session( 'El log  de Minderest se ha eliminado correctamente', 'success' );
			tep_redirect( tep_href_link( $sUrlPage ) );
		break;

		// Mostrar lista de opciones //
		default:
			// Titulos, iconos y menu //
			$sHeadTitle = 'Logs Minderest';
			$sHeadSubTitle = 'Lista y visualización de logs';

			// Variables
			$sHtmlLog = '';
			$sFechaInicio = '';
			$sFechaFin = '';
			$bFoundLog = false;

			// Archivos
			$aFiles = scandir( getcwd() .  '/../temp/log_minderest/' );

			// Ordenar archivos por fecha
			$logFiles = [];
			foreach ((array)$aFiles as $sFile) {
				// Ignorar '.' y '..'
				if (in_array($sFile, array('.', '..'))) {
					continue;
				}

				// Extraer fecha del nombre del archivo
				if (preg_match('/(?<fecha>\d+-\d+-\d+_\d+-\d+-\d+)/', $sFile, $aMatch)) {
					// Convertir el formato de fecha a timestamp
					$timestamp = DateTime::createFromFormat('d-m-Y_H-i-s', $aMatch['fecha'])->getTimestamp();
					$logFiles[$timestamp] = $sFile;
				}
			}

			// Ordenar los logs por timestamp en orden descendente (de más reciente a más antiguo)
			krsort($logFiles);

			// Si nos envian el formulario
			if ($_SERVER['REQUEST_METHOD'] == 'POST') {
				// Capturar fechas de filtro del formulario
				$sFechaInicio = tep_db_prepare_input($_POST['fecha_inicio']);
				$sFechaFin = tep_db_prepare_input($_POST['fecha_fin']);
				$aFechaInicio = explode('/', $sFechaInicio);
				$aFechaFin = explode('/', $sFechaFin);
			}

			if (count($logFiles) > 0) {
				foreach ($logFiles as $timestamp => $sFile) {
					$sFecha = date('d/m/Y H:i:s', $timestamp);

					// Filtrar según fechas seleccionadas
					if ($sFechaInicio != '' && $sFechaFin != '') {
						$timestampInicio = strtotime($aFechaInicio[2] . '-' . $aFechaInicio[1] . '-' . $aFechaInicio[0]);
						$timestampFin = strtotime($aFechaFin[2] . '-' . $aFechaFin[1] . '-' . $aFechaFin[0]);

						if ($timestamp < $timestampInicio || $timestamp > $timestampFin) {
							continue;
						}
					}

					// Generar HTML para cada log
					$sHtmlLog .= '<tr>';
					$sHtmlLog .= '<td style="text-align: left;">' . $sFile . '</td>';
					$sHtmlLog .= '<td style="text-align: left;">' . $sFecha . '</td>';
					$sHtmlLog .= '<td style="text-align: center;">' . (preg_match('/\_fin/i', $sFile) ? 'Si' : 'No') . '</td>';
					$sHtmlLog .= '<td align="center">';
					$sHtmlLog .= '<div style="display: inline-block; margin-bottom: -7px;" class="btn-group">';
					$sHtmlLog .= '<a href="#" data-toggle="dropdown" class="buttonS bDefault">Acciones<span class="caret"></span></a>';
					$sHtmlLog .= '<ul class="dropdown-menu" style="left: -70px;">';
					$sHtmlLog .= '<li><a target="_blank" href="../temp/log_minderest/' . $sFile . '"><span style="padding-top: 1px;" class="icos-inbox"></span>Ver</a></li>';
					$sHtmlLog .= '<li><a class="delete" href="' . tep_href_link($sUrlPage, 'a=file_delete&file=' . urlencode($sFile)) . '"><span style="padding-top: 1px;" class="icos-trash"></span>Eliminar</a></li>';
					$sHtmlLog .= '</ul>';
					$sHtmlLog .= '</div>';
					$sHtmlLog .= '</td>';
					$sHtmlLog .= '</tr>';
				}


				if( $sFechaInicio != '' && $sFechaFin != '' )
				{
					// Hemos encontrado log
					$bFoundLog = true;

					// Cambiamos el subtitulo
					$sHeadSubTitle = '<b>Listado de logs desde el '. $sFechaInicio . ' hasta el ' . $sFechaFin . '</b>';
				}

				if( $sHtmlLog != '' )
				{
					$sHtmlModule .= '<div class="box-tbl" style="width: 100%">';
						$sHtmlModule .= '<div class="box-head">';
							$sHtmlModule .= '<h6>Logs de Minderest generados</h6>';
							$sHtmlModule .= '<a href="' . tep_href_link( $sUrlPage, 'a=file_delete_all&fi=' . $aFechaInicio[2] . '-' . $aFechaInicio[1] . '-' . $aFechaInicio[0] . '&ff=' . $aFechaFin[2] . '-' . $aFechaFin[1] . '-' . $aFechaFin[0] ) . '" style="padding: 3px 10px 2px; position: absolute; right: 10px; top: 6px;" class="buttonS bRed file_delete_all">Eliminar todos los logs</a>';
							$sHtmlModule .= '<div class="clear"></div>';
						$sHtmlModule .= '</div>';
						$sHtmlModule .= '<table id="table" class="tAlt wGeneral tDefault" width="100%" cellspacing="0" cellpadding="0">';
							$sHtmlModule .= '<thead>';
								$sHtmlModule .= '<tr>';
									$sHtmlModule .= '<td style="text-align: left;">Log</td>';
									$sHtmlModule .= '<td style="text-align: left;">Fecha Lanzamiento</td>';
									$sHtmlModule .= '<td style="text-align: center;">Finalizado</td>';
									$sHtmlModule .= '<td width="125">Acciones</td>';
								$sHtmlModule .= '</tr>';
							$sHtmlModule .= '</thead>';
							$sHtmlModule .= '<tbody>';

							$sHtmlModule .= $sHtmlLog;

							$sHtmlModule .= '</tbody>';
						$sHtmlModule .= '</table>';
					$sHtmlModule .= '</div>';
				}
				else
					$sHtmlModule = $messageStack->show( array( 'class' => 'wrng', 'text' => 'No existen logs entre las fechas seleccionadas' ) );
			}
			else
				$sHtmlModule = $messageStack->show( array( 'class' => 'wrng', 'text' => 'No existen logs de Minderest actualmente.' ) );
		break;
	}

	// MessageStack
	$sMensajeStack = $messageStack->output(false);
	$messageStack->reset();

	// Header //
	require(THEME . 'html/header.php');

	// Titulos e iconos //
	echo '<div>';
		echo '<div class="toolbarHead">';
			echo '<div class="hdr-tlbr">';
				echo '<h1 class="pageHeading ftitl" style="top: 6px;">' . $sHeadTitle . '</h1>';
				echo '<h2 class="stitl" style="top: 13px;">' . $sHeadSubTitle . '</h2>';
				echo '<div class="btn-right" style="top: 16px;">';
					echo '<form method="post" action="' . tep_href_link( $sUrlPage ) . '" enctype="multipart/form-data" style="margin-right: ' . ($bFoundLog ? '110px' : '0') . ';">';
						echo '<div class="formRow" style="border: medium none; padding: 0px; width: 250px;">';
							echo '<div class="grid2"><label style="line-height: 12px; padding-right: 10px; padding-top: 12px;">Fecha:</label></div>';
							echo '<div class="grid10" style="margin-bottom: 8px; position: relative;">';
								echo '<div class="date-range" style="cursor: pointer; width: 248px; top: 5px; left: 0px; position: absolute; height: 28px;"></div>';
								echo '<input value="' .  $sFechaInicio . '" type="text" class="from" name="fecha_inicio" style="font-size:12px; width: 95px; text-align: center;" /> - <input type="text" value="' . $sFechaFin . '" class="to" name="fecha_fin" style="font-size:12px; width: 95px; text-align: center;" />';
								echo '<div class="clear"></div>';
							echo '</div>';
							echo '<div class="clear"></div>';
						echo '</div>';
						echo '<input value="Filtrar" class="buttonS bGreen" style="cursor: pointer;" type="submit">';
					echo '</form>';
				echo '</div>';

				if( $bFoundLog )
					echo '<a style="position: absolute; right: 10px; top: 8px;" href="' . tep_href_link( $sUrlPage ) . '" title="Limpiar filtro"><img class="dx-hovr" src="images/icons/icon_clear_filter.png"></a>';
			echo '</div>';
		echo '</div>';
	echo '</div>';

	echo $sMensajeStack;
	echo $sHtmlModule;

	// Javascript //
	$sJavascript = '<script type="text/javascript" src="' . tep_href_link( $sUrlPage, 'a=load_javascript' ) . '"></script>';
	$sJavascript .= '<link rel="stylesheet" href="css/datepicker.css" type="text/css" />';
	$sJavascript .= '<script type="text/javascript" src="js/datepicker.js"></script>';
	$sJavascript .= '<style>.ui-progressbar-value{background: #7ce !important;}</style>';

	// Footer //
	require(THEME . 'html/footer.php');

	// Librerias //
	include( DIR_WS_INCLUDES . 'application_bottom.php' );
?>
