<?php
	// Librerias
	include( 'includes/application_top.php' );

	// Errores PHP //

	// Reportamos todos los errores PHP
	//error_reporting( E_ALL );
	//ini_set( 'display_errors', 'On' );

	// Variables
	$sAction = (array_key_exists( 'a', $_GET ) ? tep_db_prepare_input( $_GET['a'] ) : false);
	$sHtmlModule = '';

	// Actions
	switch( $sAction )
	{
		// Cargamos el calendario
		case 'calendar':
			// Variables
			$nMonth = (array_key_exists( 'month', $_GET ) ? tep_db_prepare_input( (int)$_GET['month'] ) : '');
			$nYear = (array_key_exists( 'year', $_GET ) ? tep_db_prepare_input( (int)$_GET['year'] ) : '');

			// Mes por defecto
			if( $nMonth == '' )
				$nMonth = date( 'm' );
			// Año por defecto
			if( $nYear == '' )
				$nYear = date( 'Y' );

			// Mostramos el calendario
			die( getCalendar( $nMonth, $nYear ) );
		break;

		// Obtenemos elemento calendario
		case 'get_holiday':
			// Variables
			$nID = (array_key_exists( 'id', $_GET ) && $_GET['id'] != '' ? tep_db_prepare_input( $_GET['id'] ) : false);

			// Si no tenemos ID
			if( $nID === false )
				die( 'false' );

			// Obtenemos el elemento del calendario
			$aCalendar = tep_db_query( 'SELECT * FROM shipping_prediction_calendar WHERE calendar_id = "' . $nID . '";' );

			// Si no obtenemos resultado
			if( tep_db_num_rows( $aCalendar ) == 0 )
				die( 'false' );

			// Registro
			$aCalendar = tep_db_fetch_array( $aCalendar );

			// Mostramos el dato en JSON
			die( json_encode( $aCalendar ) );
		break;

		// Obtenemos elemento cp
		case 'get_cp':
			// Variables
			$sCP = (array_key_exists( 'cp', $_GET ) && $_GET['cp'] != '' ? tep_db_prepare_input( $_GET['cp'] ) : false);

			// Si no tenemos CP
			if( $sCP === false )
				die( 'false' );

			// Obtenemos el elemento CP
			$aCP = tep_db_query( 'SELECT * FROM shipping_prediction_cp WHERE cp = "' . $sCP . '";' );

			// Si no obtenemos resultado
			if( tep_db_num_rows( $aCP ) == 0 )
				die( 'false' );

			// Registro
			$aCP = tep_db_fetch_array( $aCP );

			// Mostramos el dato en JSON
			die( json_encode( $aCP ) );
		break;

		// Obtenemos elemento cp
		case 'add_cp':
			// Variables
			$sCP = (isset( $_POST['cp'] ) && $_POST['cp'] != '' ? tep_db_prepare_input( $_POST['cp'] ) : false);
			$sPrediction = (isset( $_POST['prediction'] ) && $_POST['prediction'] != '' ? tep_db_prepare_input( $_POST['prediction'] ) : false);

			// Comprobamos si el CP existe
			$aAux = tep_db_query( 'SELECT cp FROM shipping_prediction_cp WHERE cp = "' . $sCP . '";' );

			// Si ya existe, editamos
			if( tep_db_num_rows( $aAux ) > 0 )
				tep_db_query( 'UPDATE shipping_prediction_cp SET prediction = "' . $sPrediction . '" WHERE cp = "' . $sCP . '";' );
			// Si NO existe, añadimos
			else
				tep_db_query( 'INSERT INTO shipping_prediction_cp VALUES ("' . $sCP . '", "' . $sPrediction . '");' );
		break;

		// Insertamos / editamos festivo
		case 'add_holiday':
			// Método POST
			if( $_SERVER['REQUEST_METHOD'] == 'POST' )
			{
				// Variables
				$nID = (array_key_exists( 'id', $_POST ) && $_POST['id'] != '' ? tep_db_prepare_input( $_POST['id'] ) : false);
				$sDate = tep_db_prepare_input( $_POST['date'] );
				$sDescription = tep_db_prepare_input( $_POST['description'] );
				$sProvince = tep_db_prepare_input( $_POST['province'] );
				$bYearly = tep_db_prepare_input( $_POST['repeat_year'] );
				$bMonthly = tep_db_prepare_input( $_POST['repeat_month'] );
				$sType = tep_db_prepare_input( $_POST['type'] );
				$sProvince = ($sType != 'national' && $sType != 'personal' && array_key_exists( 'province', $_POST ) && $_POST['province'] != '' ? tep_db_prepare_input( $_POST['province'] ) : false);
				$bError = false;

				// Obtenemos todas las fechas enviadas
				$aDates = explode( ';', $sDate );

				// Recorremos y añadimos para cada fecha
				foreach( $aDates as $sDate )
				{
					// Descomponemos la fecha
					$aDate = explode( '-', $sDate );

					// Si NO tenemos errores
					if( $bError === false )
					{
						// Preparamos datos
						$aInsert = array();
						$aInsert['calendar_day'] = (int)$aDate[0];
						if( ! $bMonthly ) $aInsert['calendar_month'] = (int)$aDate[1];
						else $aInsert['calendar_month'] = 'null';
						if( ! $bYearly ) $aInsert['calendar_year'] = (int)$aDate[2];
						else $aInsert['calendar_year'] = 'null';
						$aInsert['calendar_description'] = $sDescription;
						$aInsert['calendar_type'] = $sType;
						if( $sProvince !== false ) $aInsert['calendar_province'] = $sProvince;
						else $aInsert['calendar_province'] = 'null';

						// Si estamos insertando
						if( $nID === false )
							tep_db_perform( 'shipping_prediction_calendar', $aInsert );
						// Si estamos editando
						else
							tep_db_perform( 'shipping_prediction_calendar', $aInsert, 'update', 'calendar_id = "' . $nID . '"' );
					}
				}

				die( 'true' );
			}
		break;

		// Eliminar elemento del calendario
		case 'delete':
			// Variables
			$nID = (array_key_exists( 'id', $_GET ) ? $_GET['id'] : false);

			// Eliminamos el registro
			if( $nID !== false )
				tep_db_query( 'DELETE FROM shipping_prediction_calendar WHERE calendar_id = "' . $nID . '";' );
		break;

		// Ventana principal
		default:
			// Pintamos el formulario
			$sHtmlModule .= '<table border="0" width="100%"><tr><td>';
			$sHtmlModule .= '<div id="box-left">';
				$sHtmlModule .= '<ul class="nav">';
					$sHtmlModule .= '<li><a href="javascript:void(0);" data-id="1"' . (array_key_exists( 'cp', $_GET ) || array_key_exists( 'prediction', $_GET ) ? '' : ' class="active"') . '"><img src="' . DIR_WS_IMAGES . 'icons/icon_shipping_prediction_calendar.png" alt="Calendario"><span>Calendario</span></a></li>';
					$sHtmlModule .= '<li><a href="javascript:void(0);" data-id="2"' . (array_key_exists( 'cp', $_GET ) || array_key_exists( 'prediction', $_GET ) ? ' class="active"' : '') . '><img src="' . DIR_WS_IMAGES . 'icons/icon_shipping_prediction_cp.png" alt="Calendario"><span>Códigos postales</span></a></li>';
				$sHtmlModule .= '</ul>';
			$sHtmlModule .= '</div>';

			$sHtmlModule .= '<div id="box-right">';
				//$sHtmlModule .= tep_draw_form( 'predictions', FILENAME_SHIPPING_PREDICTION . '?a=' . (isset( $_GET['prediction'] ) ? 'edit' : 'insert') . (isset( $_GET['prediction'] ) ? '&prediction=' . $_GET['prediction'] : ''), '', 'post', 'id="predictions"', '' );
					$sHtmlModule .= '<table width="100%" cellspacing="0" border="0">';
						$sHtmlModule .= '<tbody>';
							$sHtmlModule .= '<tr>';
								$sHtmlModule .= '<td>';
									$sHtmlModule .= '<div>';
										$sHtmlModule .= '<div class="toolbarHead">';
											$sHtmlModule .= '<div class="hdr-tlbr">';
												$sHtmlModule .= '<h1 class="pageHeading">Calendario</h1>';
												$sHtmlModule .= '<h2 class="stitl">Calendario con los días sin envíos, festivos nacionales y festivos locales para el cálculo de las predicciones de envíos</h2>';
											$sHtmlModule .= '</div>';
										$sHtmlModule .= '</div>';
									$sHtmlModule .= '</div>';
								$sHtmlModule .= '</td>';
							$sHtmlModule .= '</tr>';

							$sHtmlModule .= '<!-- CALENDARIO -->';
							$sHtmlModule .= '<tr data-id="1" style="display: ' . (array_key_exists( 'cp', $_GET ) || array_key_exists( 'prediction', $_GET ) ? 'none' : 'block') . ';" class="tab-new">';
								$sHtmlModule .= '<td style="display:block;">';
									$sHtmlModule .= '<div class="fluid grid">';
										$sHtmlModule .= '<div class="box-tbl grid12">';

											// Pintamos el calendario del mes y año actual
											$sHtmlModule .= getCalendar( date( 'm' ), date( 'Y' ) );
									$sHtmlModule .= '</div>';
								$sHtmlModule .= '</td>';
							$sHtmlModule .= '</tr>';
							$sHtmlModule .= '<!-- FIN CALENDARIO -->';

							$sHtmlModule .= '<!-- CÓDIGOS POSTALES -->';
							$sHtmlModule .= '<tr data-id="2" style="display: ' . (array_key_exists( 'cp', $_GET ) || array_key_exists( 'prediction', $_GET ) ? 'block' : 'none') . ';" class="tab-new">';
								$sHtmlModule .= '<td style="display:block;">';
									$sHtmlModule .= '<div class="fluid grid">';
										$sHtmlModule .= '<div class="box-tbl grid12">';

											// Pintamos el listado de CP
											$sHtmlModule .= getCPs();
									$sHtmlModule .= '</div>';
								$sHtmlModule .= '</td>';
							$sHtmlModule .= '</tr>';
							$sHtmlModule .= '<!-- FIN CÓDIGOS POSTALES -->';

							$sHtmlModule .= '<!-- ACTUALIZADOR FESTIVOS -->';
							$sHtmlModule .= '<!-- FIN ACTUALIZADOR FESTIVOS -->';

						$sHtmlModule .= '</tbody>';
					$sHtmlModule .= '</table>';
				//$sHtmlModule .= '</form>';
			$sHtmlModule .= '</div>';
			$sHtmlModule .= '</table>';

		break;
	}
?>

<?php
	// Header
	include( THEME . 'html/header.php' );

	// Mensajes
	echo $messageStack->output();

	// Modulo
	echo $sHtmlModule;

	// Obtenemos las provincias //

	// Variables
	$aProvinces = array();

	// Obtenemos las provincias de BD
	$aAuxs = tep_db_query( 'SELECT zone_id, zone_name FROM ' . TABLE_ZONES . ' WHERE zone_country_id = "195" ORDER BY zone_name;' );

	// Rellenamos el array de provincias
	while( $aAux = tep_db_fetch_array( $aAuxs ) )
		$aProvinces[] = array( 'id' => $aAux['zone_name'], 'text' => $aAux['zone_name'] );

?>

<div id="form-fest" title="Gestionar calendario" style="overflow: hidden !important;">
	<form>
		<div class="msje msje-eror" name="error" style="display: none;"><div class="msje-icon"></div>La descripción del festivo es requerida.</div>

		<p name="cldr-type" style="margin-top: 15px; color: #f99393; font-weight: bold; font-size: 12pt;">Nuevo festivo nacional</p>

		<fieldset style="margin-top: 15px">
			<p>
				<label for="description"><b>Descripción:</b></label>
				<textarea type="text" name="description" id="description" class="text ui-widget-content ui-corner-all"></textarea>
			</p>
			<p style="display: block; margin-top: 10px;">
				<label for="province"><b>Provincia:</b></label><br />
				<?php echo tep_draw_pull_down_menu( 'province', $aProvinces, '' ); ?>
			</p>
			<p style="margin-top: 10px;">
				<input type="checkbox" name="repeat-year" id="repeat-year" class="text ui-widget-content ui-corner-all" style="position: relative; top: 2px;" />
				<label for="repeat-year">Repetir todos los años</label>
			</p>
			<p>
				<input type="checkbox" name="repeat-month" id="repeat-month" class="text ui-widget-content ui-corner-all" style="position: relative; top: 2px;" />
				<label for="repeat-month">Repetir todos los meses</label>
			</p>

			<strong style="position: relative; top: 15px;">Se añadirá para el/los día/s <span name="date-text" style="color: #f99393;"></span></strong>
			<input type="hidden" name="id" value="" />
			<input type="hidden" name="date" value="" />
			<input type="hidden" name="type" value="" />

			<input type="submit" tabindex="-1" style="position:absolute; top:-1000px;" />
		</fieldset>
	</form>
</div>

<div id="form-cp" title="Gestionar códigos postales" style="overflow: hidden !important;">
	<form>
		<div class="msje msje-eror" name="error" style="display: none;"><div class="msje-icon"></div>El código postal es requerido.</div>

		<p name="cp-type" style="margin-top: 15px; color: #f99393; font-weight: bold; font-size: 12pt;">Nuevo código postal</p>

		<fieldset style="margin-top: 15px">
			<p>
				<label for="cp"><b>Código postal:</b></label><br />
				<?php echo tep_draw_input_field( 'cp', '', 'id="cp" class="text ui-widget-content ui-corner-all"' ); ?>
			</p>
			<p style="display: block; margin-top: 10px;">
				<label for="prediction"><b>Predicción:</b></label><br />
				<?php echo tep_draw_pull_down_menu( 'prediction', array( array( 'id' => 'diary', 'text' => 'Entrega al día siguiente' ), array( 'id' => '+24', 'text' => 'Entrega de más de 24 horas' ), array ( 'id' => 'no-predict', 'text' => 'Sin predicción' ), array ( 'id' => 'consult', 'text' => 'Consultar' ) ), '' ); ?>
			</p>

			<input type="submit" tabindex="-1" style="position:absolute; top:-1000px;" />
		</fieldset>
	</form>
</div>

<style type="text/css">
#cldr-pred
{
	width: 100%;
}

#cldr-pred thead tr
{
	text-align: center;
}

#cldr-pred thead td
{
	height: 50px;
	vertical-align: middle;
	font-size: 15pt;
}

#cldr-pred tbody tr
{
	text-align: center;
}

#cldr-pred tbody td
{
	position: relative;
	width: 14.28%;
	height: 60px;
	vertical-align: middle;
	font-size: 12pt;
	font-weight: bold;
	border-style: dashed;
    border-width: 1px;
	border-color: #cdcdcd;
	cursor: pointer;
}

.cldr-fest-ncnl
{
	background-color: #f99393;
	color: #ffffff;
}

.cldr-fest-lcal
{
	background-color: #ff68ff;
	color: #ffffff;
}

.cldr-fest-atnm
{
	background-color: #519cef;
	color: #ffffff;
}

.cldr-fest-prsl
{
	background-color: #a3f49d;
	color: #ffffff;
}

.cldr-tday
{
	border-style: dashed !important;
	border-color: #fffc00 !important;
}

#cldr-pred tbody td img
{
    position: absolute;
	float: left;
    top: 7px;
    left: 6px;
}

.cldr-slct
{
	background-color: #ffd800 !important;
}

.cldr-updt
{
	position: relative;
	top: 3px;
	left: 6px;
}

.cldr-delt
{
	position: relative;
	top: 3px;
	left: 12px;
}

</style>

<?php
	// Footer
	include( THEME . 'html/footer.php' );

	// Librerias
	include( DIR_WS_INCLUDES . 'application_bottom.php' );


	// Funciones //

	// Función para pintar el calendario
	function getCalendar($nMonth, $nYear)
	{
		// Variables
		$sReturn = '';
		$aCalendars = array();
		$nMonthDown = $nMonth;
		$nYearDown = $nYear;
		$nMonthUp = $nMonth;
		$nYearUp = $nYear;
		$nDayOfWeek = date( 'N', mktime( 0, 0, 0, $nMonth, 1, $nYear ) );
		$nNumberOfDays = cal_days_in_month( CAL_GREGORIAN, $nMonth, $nYear );
		$nCont = 1;
		$nJump = 1;
		$nWeeks = 0;

		// Obtenemos los días marcados en el calendario
		$aAuxs = tep_db_query( 'SELECT * FROM shipping_prediction_calendar WHERE calendar_month = "' . $nMonth . '" AND (calendar_year = "' . $nYear . '" OR calendar_year IS NULL OR calendar_year = "");' );

		// Pasamos a un array el calendario
		while( $aAux = tep_db_fetch_array( $aAuxs ) )
			$aCalendars[$aAux['calendar_day']][] = $aAux;

		// Calculamos mes anterior
		if( $nMonth == 1 ){ $nMonthDown = 12; $nYearDown = $nYear - 1; }
		else $nMonthDown = $nMonth - 1;

		// Calculamos mes siguiente
		if( $nMonth == 12 ){ $nMonthUp = 1; $nYearUp = $nYear + 1; }
		else $nMonthUp = $nMonth + 1;

		// Desplegable de meses
		$sSelectMonths = '<select name="month" id="slct-mnth">';
		for( $nCont = 1; $nCont <= 12; ++$nCont )
			$sSelectMonths .= '<option value="' . $nCont . '"' . ($nMonth == $nCont ? ' selected="selected"' : '') . '>' . monthToSpanish( $nCont ) . '</option>';
		$sSelectMonths .= '</select>';

		// Desplegable de años
		$sSelectYears = '<select name="year" id="slct-year">';
		for( $nCont = 1985; $nCont <= 2200; ++$nCont )
			$sSelectYears .= '<option value="' . $nCont . '"' . ($nYear == $nCont ? ' selected="selected"' : '') . '>' . $nCont . '</option>';
		$sSelectYears .= '</select>';

		// Contenedor
		$sReturn .= '<div id="cldr-cntd">';

		// Calendario
		$sReturn .= '<table id="cldr-pred" data-month="' . $nMonth . '" data-year="' . $nYear . '">';
			$sReturn .= '<thead>';
				$sReturn .= '<tr>';
					$sReturn .= '<td colspan="1"><a href="' . tep_href_link( FILENAME_SHIPPING_PREDICTION, 'a=calendar&month=' . $nMonthDown . '&year=' . $nYearDown ) . '" title="' . monthToSpanish( $nMonthDown ) . ' ' . $nYearDown . '" class="cldr-mdu"><<</a></td>';
					$sReturn .= '<td colspan="5"><b>' . $sSelectMonths . ' ' . $sSelectYears . '</b></td>';
					$sReturn .= '<td colspan="1"><a href="' . tep_href_link( FILENAME_SHIPPING_PREDICTION, 'a=calendar&month=' . $nMonthUp . '&year=' . $nYearUp ) . '" title="' . monthToSpanish( $nMonthUp ) . ' ' . $nYearUp . '" class="cldr-mdu">>></a></td>';
				$sReturn .= '</tr>';
			$sReturn .= '</thead>';
			$sReturn .= '<tbody>';
				$sReturn .= '<tr>';
					$sReturn .= '<td><b>Lunes</b></td>';
					$sReturn .= '<td><b>Martes</b></td>';
					$sReturn .= '<td><b>Miércoles</b></td>';
					$sReturn .= '<td><b>Jueves</b></td>';
					$sReturn .= '<td><b>Viernes</b></td>';
					$sReturn .= '<td><b>Sábado</b></td>';
					$sReturn .= '<td><b>Domingo</b></td>';
				$sReturn .= '</tr>';

				// Semana #1
				$sReturn .= '<tr>';

				// Recorremos el calendario y mostramos los días vacíos
				for( $nCont = 1; $nCont < $nDayOfWeek; ++$nCont, ++$nJump )
					$sReturn .= '<td></td>';

				// Recorremos el calendario y mostramos
				for( $nCont = 1; $nCont <= $nNumberOfDays; ++$nCont )
				{
					// Variables
					$sClass = '';
					$sTitle = '';
					$sIcons = '';

					// Obtenemos las clases del día //

					// Día actual
					if( $nCont == date( 'd' ) && $nMonth == date( 'm' ) && $nYear == date( 'Y' ) )
						$sClass .= ($sClass != '' ? ' ' : '') . 'cldr-tday';
					// Fin de semana (festivo nacional)
					if( $nJump == 6 || $nJump == 7 )
						$sClass .= ($sClass != '' ? ' ' : '') . 'cldr-fest-ncnl';

					// Comprobamos si tiene registro en el calendario de BD //
					if( array_key_exists( $nCont, $aCalendars ) )
					{
						// Recorremos los días añadidos
						foreach( $aCalendars[$nCont] as $aCalendar )
						{
							// Si es fiesta nacional
							if( $aCalendar['calendar_type'] == 'national' )
							{
								$sClass .= ($sClass != '' ? ' ' : '') . 'cldr-fest-ncnl';
								$sTitle .= 'Fiesta nacional: ' . $aCalendar['calendar_description'] . '<br />';
							}
							// Si es fiesta autonómica
							else if( $aCalendar['calendar_type'] == 'autonomic' )
							{
								$sClass .= ($sClass != '' ? ' ' : '') . 'cldr-fest-atnm';
								$sTitle .= 'Fiesta autonómica de ' . $aCalendar['calendar_province'] . ': ' . $aCalendar['calendar_description'] . '<br />';
							}
							// Si es fiesta local
							else if( $aCalendar['calendar_type'] == 'local' )
							{
								$sClass .= ($sClass != '' ? ' ' : '') . 'cldr-fest-lcal';
								$sTitle .= 'Fiesta local de ' . $aCalendar['calendar_province'] . ': ' . $aCalendar['calendar_description'] . '<br />';
							}
							// Si es personal
							else if( $aCalendar['calendar_type'] == 'personal' )
							{
								$sClass .= ($sClass != '' ? ' ' : '') . 'cldr-fest-prsl';
								$sTitle .= 'Sin envíos: ' . $aCalendar['calendar_description'] . '<br />';
							}
						}

						// Icono de información
						$sIcons .= '<img src="' . DIR_WS_IMAGES . 'icons/icon_info_calendar.png" alt="Información" />';
					}

					// Pintamos el día //

					// Pintamos
					$sReturn .= '<td' . ($sClass != '' ? ' class="' . $sClass . '"' : '') . ($sTitle != '' ? ' title="' . $sTitle . '"' : '') . '>' . $nCont . $sIcons . '<input type="checkbox" name="day-sel[]" value="' . $nCont . '" style="display: none;" /></td>';

					// Salto de semana
					++$nJump;

					// Si hay un salto de semana
					if( $nJump == 8 )
					{
						// Cerramos y abrimos línea
						$sReturn .= '</tr>';
						$sReturn .= '<tr>';

						// Reiniciamos salto de semana
						$nJump = 1;

						// Sumamos el número de semanas
						++$nWeeks;
					}
				}

				// Cerramos la semana
				if( $nJump < 8 )
				{
					// Pintamos los días que quedan
					for( $nJump; $nJump <= 7; ++$nJump )
						$sReturn .= '<td></td>';

					// Cerramos la fila
					$sReturn .= '</tr>';

					// Sumamos el número de semanas
					++$nWeeks;
				}

				// Semana #6
				if( $nWeeks < 6 )
				{
					// Abrimos la última semana
					$sReturn .= '<tr>';

					// Pintamos los días
					for( $nCont = 1; $nCont <= 7; ++$nCont )
						$sReturn .= '<td></td>';

					// Cerramos la fila
					$sReturn .= '</tr>';
				}

		// Cerramos el body
		$sReturn .= '</tbody>';

		// Cerramos tabla
		$sReturn .= '</table>';

		// Botón operaciones masivas
		$sReturn .= '<div class="btn-group" style="display: inline-block; float: left; margin-top: 10px; margin-bottom: 10px; margin-left: 10px;">
			<a class="buttonS bDefault" data-toggle="dropdown" href="#">Acciones para los elementos seleccionados<span class="caret"></span></a>
			<ul class="dropdown-menu" style="min-width: 170px !important;">
				<li><a href="javascript: void(0);" id="mssv-opts" class="new-fest" data-date="" data-type="personal"><span class="icos-pencil"></span>Nuevo día sin envío</a></li>
			</ul>
		</div>';

		// Tabla de fiestas
		$sReturn .= '<div class="box-tbl" style="width: 100%; margin-top: 70px;">';
			$sReturn .= '<div class="box-head">
				<h6>Días del mes</h6>
				<div class="clear"></div>
			</div>';

			$sReturn .= '<table id="table" class="tAlt wGeneral tDefault" width="100%" cellspacing="0" cellpadding="0">
				<thead>
					<tr>
						<td style="text-align: center; width: 20px;">#</td>
						<td style="text-align: center; width: 30px;">Día</td>
						<td style="text-align: center; width: 100px;">Mes</td>
						<td style="text-align: center;">Fiestas</td>
						<td style="text-align: center; width: 200px;">Acciones</td>
					</tr>
				</thead>
				<tbody>';

				// Recorremos los días del mes
				for( $nCont = 1; $nCont <= $nNumberOfDays; ++$nCont )
				{
					// Variables
					$sCalendar = '';

					// Obtenemos los eventos
					if( array_key_exists( $nCont, $aCalendars ) )
					{
						// Recorremos los días añadidos
						foreach( $aCalendars[$nCont] as $aCalendar )
						{
							// Abrimos línea
							$sCalendar .= '<p>';

							// Si es fiesta nacional
							if( $aCalendar['calendar_type'] == 'national' )
								$sCalendar .= '<b style="color: #f99393;">Fiesta nacional:</b> ' . $aCalendar['calendar_description'];
							// Si es fiesta autonómica
							else if( $aCalendar['calendar_type'] == 'autonomic' )
								$sCalendar .= '<b style="color: #519cef;">Fiesta autonómica de ' . $aCalendar['calendar_province'] . ':</b> ' . $aCalendar['calendar_description'];
							// Si es fiesta local
							else if( $aCalendar['calendar_type'] == 'local' )
								$sCalendar .= '<b style="color: #ff68ff;">Fiesta local de ' . $aCalendar['calendar_province'] . ':</b> ' . $aCalendar['calendar_description'];
							// Si es fiesta personal
							else if( $aCalendar['calendar_type'] == 'personal' )
								$sCalendar .= '<b style="color: #a3f49d;">Sin envíos:</b> ' . $aCalendar['calendar_description'];

							// Editar / Eliminar
							$sCalendar .= '&nbsp;<a href="javascript: void(0);" title="Editar" class="cldr-updt edit-fest" data-id="' . $aCalendar['calendar_id'] . '" data-date="' . ($nCont < 10 ? '0' : '') . $nCont . '-' . ($nMonth < 10 ? '0' : '') . $nMonth . '-' . $nYear . '" data-type="' . $aCalendar['calendar_type'] . '"><img src="theme/web/images/icons/icon-pencil.png" /></a>';
							$sCalendar .= '&nbsp;<a href="' . tep_href_link( FILENAME_SHIPPING_PREDICTION, 'a=delete&id=' . $aCalendar['calendar_id'] ) . '" title="Eliminar" class="cldr-delt" style="position: relative; top: 3px; left: 12px;"><img src="theme/web/images/icons/usual/icon-trash.png" /></a>';

							// Cerramos línea
							$sCalendar .= '</p>';
						}
					}

					// Pintamos la línea
					$sReturn .= '<tr>';
						$sReturn .= '<td align="center">#</td>';
						$sReturn .= '<td align="center"><b>' . $nCont . '</b></td>';
						$sReturn .= '<td align="center">' . monthToSpanish( $nMonth ) . '</td>';
						$sReturn .= '<td align="left">' . $sCalendar . '</div>';
						$sReturn .= '<td align="center">
							<div class="btn-group" style="display: inline-block; margin-bottom: -4px;">
								<a class="buttonS bDefault" data-toggle="dropdown" href="#">Acciones<span class="caret"></span></a>
								<ul class="dropdown-menu" style="min-width: 170px !important;">
									<li><a href="javascript: void(0);" class="new-fest" data-date="' . ($nCont < 10 ? '0' : '') . $nCont . '-' . ($nMonth < 10 ? '0' : '') . $nMonth . '-' . $nYear . '" data-type="personal"><span class="icos-pencil"></span>Nuevo día sin envío</a></li>
									<li><a href="javascript: void(0);" class="new-fest" data-date="' . ($nCont < 10 ? '0' : '') . $nCont . '-' . ($nMonth < 10 ? '0' : '') . $nMonth . '-' . $nYear . '" data-type="national"><span class="icos-pencil"></span>Nuevo festivo nacional</a></li>
									<li><a href="javascript: void(0);" class="new-fest" data-date="' . ($nCont < 10 ? '0' : '') . $nCont . '-' . ($nMonth < 10 ? '0' : '') . $nMonth . '-' . $nYear . '" data-type="autonomic"><span class="icos-pencil"></span>Nuevo festivo autonómico</a></li>
									<li><a href="javascript: void(0);" class="new-fest" data-date="' . ($nCont < 10 ? '0' : '') . $nCont . '-' . ($nMonth < 10 ? '0' : '') . $nMonth . '-' . $nYear . '" data-type="local"><span class="icos-pencil"></span>Nuevo festivo local</a></li>
								</ul>
							</div>
						</td>';
					$sReturn .= '</tr>';
				}

			$sReturn .= '</tbody>
			</table>
		</div>';

		// Cerramos contenedor
		$sReturn .= '</div>';

		// Retornamos el HTML
		return $sReturn;
	}

	// Función para pintar el listado de CPs
	function getCPs($sCP = false, $sPrediction = false)
	{
		// Variables
		$sReturn = '';
		$sCP = (array_key_exists( 'cp', $_GET ) && $_GET['cp'] != '' ? tep_db_prepare_input( $_GET['cp'] ) : false);
		$sPrediction = (array_key_exists( 'prediction', $_GET ) && $_GET['prediction'] != '0' ? tep_db_prepare_input( $_GET['prediction'] ) : false);
		$nPage = (array_key_exists( 'page', $_GET ) ? tep_db_prepare_input( $_GET['page'] ) : 1);

		// Obtenemos los CPs
		$sSql = 'select * from shipping_prediction_cp' . ($sCP !== false || $sPrediction !== false ? ' where ' : '') . ($sCP !== false ? 'cp = "' . $sCP . '" ' . ($sPrediction !== false ? 'AND ' : '') : '') . ($sPrediction !== false ? 'prediction = "' . $sPrediction . '"' : '') . ' order by cp asc';

		// Paginador
		$aPaginator = new splitPageResults( $nPage, 100, $sSql, $nAux );
		$aCPs = tep_db_query( $sSql );

		$sReturn .= '<a href="javascript: void(0);" class="buttonL bGreen new-cp" value="Nuevo CP" style="float: right; margin-top: 2px; margin-bottom: 10px; margin-right: 10px;">Nuevo CP</a>';

		// Pintamos cabeceras de la tabla
		$sReturn .= '<div class="box-tbl" style="width: 100%">';

				$sReturn .= '<div class="box-head">
					<h6>Listado de Códigos Postales</h6>
					<a title="Filtrar" data-id="1" href="javascript:void(0);" class="tOptions filter-togle"><img src="theme/web/images/icons/usual/icon-cog3.png" alt="Filtrar"></a>
					<div class="clear"></div>
				</div>';

				$sReturn .= '<div data-id="1" class="fluid grid tablePars" style="display: none;">
					<form id="filter_cp" name="filter_cp" action="' . tep_href_link( FILENAME_SHIPPING_PREDICTION ) . '" method="get">
						<div class="grid12">
							<div class="formRow" style="border: none; padding: 7px 16px;">
								<div class="grid2"><label>Buscar CP:</label></div>
								<div class="grid6"><input type="text" name="cp" value="' . ($sCP !== false ? $sCP : '') . '" placeholder="Introduce el código postal a buscar..."></div>
								<div class="clear"></div>
							</div>
							<div class="formRow" style="border: none; padding: 7px 16px;">
								<div class="grid2"><label>Estimación:</label></div>
								<div class="grid10">
									<select name="prediction">
										<option value="0">Seleccione</option>
										<option value="diary"' . ($sPrediction == 'diary' ? ' selected="selected"' : '') . '>Entrega al día siguiente</option>
										<option value="+24"' . ($sPrediction == '+24' ? ' selected="selected"' : '') . '>Entrega de más de 24 horas</option>
										<option value="no-predict"' . ($sPrediction == 'no-predict' ? ' selected="selected"' : '') . '>Sin predicción</option>
										<option value="consult"' . ($sPrediction == 'consult' ? ' selected="selected"' : '') . '>Consultar</option>
									</select>
								</div>
								<div class="clear"></div>
							</div>
							<div class="formRow" style="border: none; padding: 7px 16px;">
								<div class="grid12">
									<input type="submit" value="Filtrar" class="buttonS bGreen" style="cursor: pointer;">
								</div>
								<div class="clear"></div>
							</div>
						</div>
					</form>
				</div>';

				// $sReturn .= tep_draw_form( 'cps', FILENAME_SHIPPING_PREDICTION, '', 'post' );

				$sReturn .= '<table id="table" class="tAlt wGeneral tDefault" width="100%" cellspacing="0" cellpadding="0">';
					$sReturn .= '<thead>';
						$sReturn .= '<tr>';
							$sReturn .= '<td width="100" style="text-align: center;">CP</td>';
							$sReturn .= '<td style="text-align: center;" width="">Tipo</td>';
							$sReturn .= '<td width="125">Acciones</td>';
						$sReturn .= '</tr>';
					$sReturn .= '</thead>';
					$sReturn .= '<tbody>';

					// Recorremos popup para mostrar
					while( $aCP = tep_db_fetch_array( $aCPs ) )
					{
						// Obtenemos el tipo de predicción
						switch( $aCP['prediction'] )
						{
							// Entrega al día siguiente
							case 'diary':
								$sPrediction = '<b style="color: #f99393">Entrega al día siguiente</b>';
							break;
							// Más de 24 horas
							case '+24':
								$sPrediction = '<b style="color: #ff68ff">Entrega de más de 24 horas</b>';
							break;
							// Sin predicción
							case 'no-predict':
								$sPrediction = '<b style="color: #519cef">Sin predicción</b>';
							break;
							// Consultar
							case 'consult':
								$sPrediction = '<b style="color: #cdcdcd">Consultar</b>';
							break;
						}

						// Pintamos la fila
						$sReturn .= '<tr>';
							$sReturn .= '<td align="center">' . $aCP['cp'] . '</td>';
							$sReturn .= '<td align="left">' . $sPrediction . '</td>';
							$sReturn .= '<td align="center">';
								$sReturn .= '<div style="display: inline-block; margin-bottom: -7px;" class="btn-group">';
									$sReturn .= '<a href="#" data-toggle="dropdown" class="buttonS bDefault">Acciones<span class="caret"></span></a>';
									$sReturn .= '<ul class="dropdown-menu" style="left: -70px;">';
										$sReturn .= '<li><a href="javascript: void(0);" class="edit-cp" data-cp="' . $aCP['cp'] . '"><span style="padding-top: 1px;" class="icos-pencil"></span>Editar</a></li>';
										$sReturn .= '<li><a href="' . tep_href_link( FILENAME_SHIPPING_PREDICTION, 'a=delete_cp&cp=' . $aCP['cp'] ) . '" class="dlet" data-text="0" onclick="if( ! confirm(\'¿Eliminar código postal seleccionado?\') ) return false;"><span style="padding-top: 1px;" class="icos-trash"></span>Eliminar</a></li>';
									$sReturn .= '</ul>';
								$sReturn .= '</div>';
							$sReturn .= '</td>';
						$sReturn .= '</tr>';
					}

					// Fin de tabla y paginacion //
					$sReturn .= '</tbody>';
				$sReturn .= '</table>';
			//$sReturn .= '</form>';
			$sReturn .= $aPaginator->showPaginateTable( tep_get_all_get_params( array( 'page' ) ) );
		$sReturn .= '</div>';

		// Retornamos el HTML
		return $sReturn;
	}

	function monthToSpanish($nMonth)
	{
		// Según el mes retornamos el nombre en Español
		switch( $nMonth )
		{
			// Enero
			case 1:
				return 'Enero';
				break;
			// Febrero
			case 2:
				return 'Febrero';
				break;
			// Marzo
			case 3:
				return 'Marzo';
				break;
			// Abril
			case 4:
				return 'Abril';
				break;
			// Mayo
			case 5:
				return 'Mayo';
				break;
			// Junio
			case 6:
				return 'Junio';
				break;
			// Julio
			case 7:
				return 'Julio';
				break;
			// Agosto
			case 8:
				return 'Agosto';
				break;
			// Septiembre
			case 9:
				return 'Septiembre';
				break;
			// Octubre
			case 10:
				return 'Octubre';
				break;
			// Noviembre
			case 11:
				return 'Noviembre';
				break;
			// Diciembre
			case 12:
				return 'Diciembre';
				break;
		}
	}
?>

<script type="text/javascript">
	// Navegación calendario
	cldr_nav();

	// Función para navegación del calendario
	function cldr_nav()
	{
		// Mensaje al pasar el puntero por el calendario
		$("#cldr-pred").tooltip({
			content:function(){
			return this.getAttribute("title");
			},
			track: true
		});

		// Función para cambiar mes por AJAX
		function change_month(url)
		{
			// Petición AJAX
			$.ajax(
			{
				url: url,
				cache: false
			}
			).done(function(r)
			{
				// Cargamos el resulado
				$("#cldr-cntd").html(r);

				// Estilo a los select
				$("#slct-mnth").uniform();
				$("#slct-year").uniform();

				// Navegación calendario
				cldr_nav();
			});
		}

		// Click mes arriba, mes abajo
		$(".cldr-mdu").click(function(e)
		{
			// Evitamos eventos padre
			e.stopPropagation();

			// Cambiamos el mes
			change_month( $(e.currentTarget).attr("href") );

			return false;
		});

		// Cambiamos el desplegable de mes y año
		$("#slct-mnth").change( select_month_year_change );
		$("#slct-year").change( select_month_year_change );

		// Función cuando seleccionamos mes o año en el calendario
		function select_month_year_change()
		{
			// Cambiamos el mes
			change_month( "<?php echo tep_href_link( FILENAME_SHIPPING_PREDICTION, 'a=calendar' ); ?>&month=" + $("#slct-mnth").find(":selected").val() + "&year=" + $("#slct-year").find(":selected").val() );
		}

		// Click elemento del calendario
		$("#cldr-pred").on("click", "td", function()
		{
			// Comprobamos si es un integer
			if( $(this).text() != "" && $(this).text() % 1 === 0 )
			{
				// Variable
				var dt = "";

				// Seleccionamos
				if( ! $(this).hasClass("cldr-slct") )
				{
					$(this).addClass("cldr-slct");
					$(this).find("input").prop("checked", true);
				}
				// Deseleccionamos
				else
				{
					$(this).removeClass("cldr-slct");
					$(this).find("input").prop("checked", false);
				}

				// Añadimos a la opción masiva las fechas
				$(this).parent().parent().find("[class=cldr-slct]").each(function(e)
				{
					// Generamos la fecha
					var d = $(this).text();
					var m = $("#cldr-pred").attr("data-month");
					var y = $("#cldr-pred").attr("data-year");
					dt = dt + (d < 10 ? "0" : "") + d + "-" + (m < 10 ? "0" : "") + m + "-" + y + ";";
				});

				// Quitamos el último ;
				if( dt != "" )
					dt = dt.substring( 0, dt.length - 1 );

				// Indicamos la fecha en el botón
				$("#mssv-opts").attr("data-date", dt);
			}
		});










		// Ventana de nuevo festivo
		dh = $( "#form-fest" ).dialog(
		{
			autoOpen: false,
			height: 450,
			width: 450,
			modal: true,
			buttons:
			{
				"Añadir": addHoliday,
				"Cancelar": function(){ dh.dialog("close"); }
			},
			close: function()
			{
				// Variables
				var f = $("#form-fest");

				// Limpiamos
				f.find("[name=\"id\"]").val("");
				f.find("[name=\"description\"]").val("");
				f.find("[name=\"repeat-year\"]").prop("checked", false);
				f.find("[name=\"repeat-month\"]").prop("checked", false);
				f.find("[name=\"date\"]").val("");
				f.find("[name=\"type\"]").val("");
				f.find("[class*=\"msje-eror\"]").css("display","none");
			}
		});

		// Click nuevo festivo
		$(".new-fest").click(function(e)
		{
			// Evitamos eventos padre
			e.stopPropagation();

			// Comprobamos que tengamos días seleccionados
			if( $(e.currentTarget).attr("data-date") == "" )
			{
				// Mensaje
				alert("Debes de seleccionar al menos un día en el calendario.");
				return false;
			}

			// Variables
			var f = $("#form-fest");
			var p = $(e.currentTarget).attr("data-type");
			var t = "";
			var c = "";

			// Generamos el título y el color
			if( p == "personal" )
			{
				t = "Nuevo día sin envío";
				c = "#a3f49d";
			}
			else if( p == "national" )
			{
				t = "Nuevo festivo nacional";
				c = "#f99393";
			}
			else if( p == "autonomic" )
			{
				t = "Nuevo festivo autonómico";
				c = "#519cef";
			}
			else if( p == "local" )
			{
				t = "Nuevo festivo local";
				c = "#ff68ff";
			}

			// Título y color de la ventana
			f.find("[name=\"cldr-type\"]").html(t).css("color", c);

			// Mostramos / ocultamos provincias
			f.find("[name=\"province\"]").parent().css("display",(p == "national" || p == "personal" ? "none" : "block"));

			// Añadimos la fecha y el tipo
			f.find("[name=\"date-text\"]").html($(e.currentTarget).attr("data-date").replace(/-/g,"/").replace(/;/g, ", "));
			f.find("[name=\"date\"]").val($(e.currentTarget).attr("data-date"));
			f.find("[name=\"type\"]").val(p);

			// Abrimos la ventana del festivo
			dh.dialog("open");

			return false;
		});

		// Click editar festivo
		$(".edit-fest").click(function(e)
		{
			// Evitamos eventos padre
			e.stopPropagation();

			// Variables
			var f = $("#form-fest");
			var p = $(e.currentTarget).attr("data-type");
			var t = "";
			var c = "";

			// Generamos el título y el color
			if( p == "personal" )
			{
				t = "Editar día sin envío";
				c = "#a3f49d";
			}
			else if( p == "national" )
			{
				t = "Editar festivo nacional";
				c = "#f99393";
			}
			else if( p == "autonomic" )
			{
				t = "Editar festivo autonómico";
				c = "#519cef";
			}
			else if( p == "local" )
			{
				t = "Editar festivo local";
				c = "#ff68ff";
			}

			// Título y color de la ventana
			f.find("[name=\"cldr-type\"]").html(t).css("color", c);

			// Mostramos / ocultamos provincias
			f.find("[name=\"province\"]").parent().css("display",(p == "national" || p == "personal" ? "none" : "block"));

			// Petición AJAX para obtener los datos
			$.ajax(
			{
				url: "<?php echo tep_href_link( FILENAME_SHIPPING_PREDICTION, 'a=get_holiday&id=' ); ?>" + $(e.currentTarget).attr("data-id"),
				cache: false
			}
			).done(function(r)
			{
				// Si hemos obtenido el JSON
				if( r != "false" )
				{
					// Añadimos la fecha y el tipo
					f.find("[name=\"date-text\"]").html($(e.currentTarget).attr("data-date").replace("-","/").replace("-","/"));
					f.find("[name=\"date\"]").val($(e.currentTarget).attr("data-date"));
					f.find("[name=\"type\"]").val(p);

					// Descomponemos el JSON
					j = jQuery.parseJSON( r );

					// Introducimos datos a editar
					f.find("[name=\"id\"]").val(j.calendar_id);
					f.find("[name=\"description\"]").val(j.calendar_description);
					f.find("[name=\"province\"]").val(j.calendar_province).change();
					f.find("[name=\"repeat-year\"]").prop("checked", j.calendar_year == "" || j.calendar_year == null);
					f.find("[name=\"repeat-month\"]").prop("checked", j.calendar_month == "" || j.calendar_month == null);
					f.find("[class*=\"msje-eror\"]").css("display","none");

					// Abrimos la ventana del festivo
					dh.dialog("open");
				}
				// Mensaje de error
				else
					alert("Ha ocurrido un error al intentar obtener los datos. Por favor, vuelva a intentarlo y si el error persiste contacte con nosotros.");
			});

			return false;
		});

		// Función para añadir festivo
		function addHoliday()
		{
			// Variables
			var f = $("#form-fest");
			var i = f.find("[name=\"id\"]").val();
			var d = f.find("[name=\"description\"]").val();
			var p = f.find("[name=\"province\"]").val();
			var y = f.find("[name=\"repeat-year\"]").prop("checked");
			var m = f.find("[name=\"repeat-month\"]").prop("checked");
			var c = f.find("[name=\"date\"]").val();
			var t = f.find("[name=\"type\"]").val();
			var e = false;

			// Ocultamos mensaje de error
			f.find("[class*=\"msje-eror\"]").css("display","none");

			// Si no enviamos descripción
			if( d == "" )
			{
				// Mensaje de error
				f.find("[class*=\"msje-eror\"]").css("display","block");
				// Error
				e = true;
			}

			// Si no tenemos error
			if( ! e )
			{
				// Petición AJAX
				$.ajax(
				{
					url: "<?php echo tep_href_link( FILENAME_SHIPPING_PREDICTION, 'a=add_holiday' ); ?>",
					type: "POST",
					data: "id=" + i + "&date=" + c + "&description=" + d + "&province=" + p + "&repeat_year=" + (y ? "1" : "0") + "&repeat_month=" + (m ? "1" : "0") + "&type=" + t,
					cache: false
				}
				).done(function(r)
				{
					// Actualizamos calendario
					change_month( "<?php echo tep_href_link( FILENAME_SHIPPING_PREDICTION, 'a=calendar' ); ?>" + "&year=" + $("#cldr-pred").attr("data-year") + "&month=" + $("#cldr-pred").attr("data-month") );

					// Cerramos ventana de diálogo
					dh.dialog("close");
				});
			}
		}





		// Ventana de nuevo CP
		dcp = $( "#form-cp" ).dialog(
		{
			autoOpen: false,
			height: 350,
			width: 450,
			modal: true,
			buttons:
			{
				"Añadir": addCP,
				"Cancelar": function(){ dcp.dialog("close"); }
			},
			close: function()
			{
				// Variables
				var f = $("#form-cp");

				// Limpiamos
				f.find("[name=\"cp\"]").val("");
				f.find("[name=\"prediction\"]").val("");
				f.find("[class*=\"msje-eror\"]").css("display","none");
			}
		});

		// Click nuevo CP
		$(".new-cp").click(function(e)
		{
			// Evitamos eventos padre
			e.stopPropagation();

			// Variables
			var f = $("#form-cp");

			// Título y color de la ventana
			f.find("[name=\"cp-type\"]").html("Nuevo código postal");

			// Abrimos la ventana del festivo
			dcp.dialog("open");

			return false;
		});

		// Click editar cp
		$(".edit-cp").click(function(e)
		{
			// Evitamos eventos padre
			e.stopPropagation();

			// Variables
			var f = $("#form-cp");
			var p = $(e.currentTarget).attr("data-type");
			var t = "";
			var c = "";

			// Título y color de la ventana
			f.find("[name=\"cldr-type\"]").html("Editar código postal");

			// Petición AJAX para obtener los datos
			$.ajax(
			{
				url: "<?php echo tep_href_link( FILENAME_SHIPPING_PREDICTION, 'a=get_cp&cp=' ); ?>" + $(e.currentTarget).attr("data-cp"),
				cache: false
			}
			).done(function(r)
			{
				// Si hemos obtenido el JSON
				if( r != "false" )
				{
					// Descomponemos el JSON
					j = jQuery.parseJSON( r );

					// Introducimos datos a editar
					f.find("[name=\"cp\"]").val(j.cp);
					f.find("[name=\"prediction\"]").val(j.prediction).change();

					// Abrimos la ventana del cp
					dcp.dialog("open");
				}
				// Mensaje de error
				else
					alert("Ha ocurrido un error al intentar obtener los datos. Por favor, vuelva a intentarlo y si el error persiste contacte con nosotros.");
			});

			return false;
		});

		// Función para añadir CP
		function addCP()
		{
			// Variables
			var f = $("#form-cp");
			var c = f.find("[name=\"cp\"]").val();
			var p = f.find("[name=\"prediction\"]").val();
			var e = false;

			// Ocultamos mensaje de error
			f.find("[class*=\"msje-eror\"]").css("display","none");

			// Si no enviamos CP
			if( c == "" )
			{
				// Mensaje de error
				f.find("[class*=\"msje-eror\"]").css("display","block");
				// Error
				e = true;
			}

			// Si no tenemos error
			if( ! e )
			{
				// Petición AJAX
				$.ajax(
				{
					url: "<?php echo tep_href_link( FILENAME_SHIPPING_PREDICTION, 'a=add_cp' ); ?>",
					type: "POST",
					data: "cp=" + c + "&prediction=" + p,
					cache: false
				}
				).done(function(r)
				{
					// Cerramos ventana de diálogo
					dh.dialog("close");

					// Recargamos
					document.location.href = document.location.href;
				});
			}
		}


		// Click eliminar evento calendario
		$(".cldr-delt").click(function(e)
		{
			// Evitamos eventos padre
			e.stopPropagation();

			// Solicitamos confirmación
			if( confirm("¿Deseas eliminar el registro seleccionado del calendario?") )
			{
				// Petición AJAX
				$.ajax(
				{
					url: $(e.currentTarget).attr("href"),
					cache: false
				}
				).done(function(r)
				{
					// Actualizamos calendario
					change_month( "<?php echo tep_href_link( FILENAME_SHIPPING_PREDICTION, 'a=calendar' ); ?>" + "&year=" + $("#cldr-pred").attr("data-year") + "&month=" + $("#cldr-pred").attr("data-month") );

				});
			}

			return false;
		});
	}
</script>