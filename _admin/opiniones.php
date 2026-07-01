<?php
include( 'includes/application_top.php' );

// Motor de moderación IA (librería fuera de public_html). Define OAI_* y funciones.
require_once( dirname( dirname( __DIR__ ) ) . '/opiniones_ai_lib.php' );

// Etiquetas (fallback por si no están en el fichero de idioma, evita fatal PHP8)
if( ! defined( 'OPINIONES_AI_MODERATE' ) )  define( 'OPINIONES_AI_MODERATE', 'Moderar con IA' );
if( ! defined( 'OPINIONES_AI_COL' ) )       define( 'OPINIONES_AI_COL', 'IA' );

// Variables
$sAction = array_key_exists( 'a', $_POST ) ? tep_db_input( $_POST['a'] ) : (array_key_exists( 'a', $_GET ) ? tep_db_input( $_GET['a'] ) : false);
$sHtml = '';
$sUrl = 'opiniones.php';
$sGetPage = tep_db_prepare_input( $_GET['page'] );

// Funciones
// Modificamos el campo status
function show_row_opinion_estado($aDato)
{
	if( $aDato == 'true' )
		return OPINIONES_ANSWERED;
	else
		return OPINIONES_NOT_ANSWERED;
}

// Modificamos el campo valoracion
function show_row_opinion_general($aDato)
{
	switch( $aDato )
	{
		case 0: default: $sStyle = 'background-position: 0px 0px;'; break;
		case 1: $sStyle = 'background-position: 0px -16px;'; break;
		case 2: $sStyle = 'background-position: 0px -32px;'; break;
		case 3: $sStyle = 'background-position: 0px -48px;'; break;
		case 4: $sStyle = 'background-position: 0px -64px;'; break;
		case 5: $sStyle = 'background-position: 0px -80px;'; break;
	}

	return '<div rel="no" style="' . $sStyle . '" class="form-star-cntd"></div>';
}

switch( $sAction )
{
	case 'status':
		// Variables
		$sGetId = tep_db_prepare_input( $_GET['id'] );
		$sGetStatus = tep_db_prepare_input( $_GET['status'] );

		// Actualizamos
		tep_db_query( 'update opinion set status_aprobado = "' . $sGetStatus . '" where id_opinion = ' . $sGetId );

		exit();
		break;

	case 'status_massive':
		// Variables
		$aGetId = tep_db_prepare_input( $_GET['id'] );
		$aPostId = tep_db_prepare_input( $_POST['id'] );
		$sIds = '';

		// Si nos envian por get creamos el array
		if( $aGetId != '' )
			$aPostId = array( $aGetId );

		// Recorremos los id
		foreach( $aPostId as $sId ) {
			$sIds .= $sId . ',';
		}

		// Si tenemos id eliminamos
		if( $sIds != '' ) {
			tep_db_query( 'UPDATE opinion SET status_aprobado = "1" WHERE id_opinion IN(' . substr( $sIds, 0, -1 ) . ')' );
		}

		// Redireccionamos
		$messageStack->add_session( OPINIONES_APPROVED_SUCCESS, 'success' );
		tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;

	case 'delete':
		// Variables
		$aGetId = tep_db_prepare_input( $_GET['id'] );
		$aPostId = tep_db_prepare_input( $_POST['id'] );
		$sIds = '';

		// Si nos envian por get creamos el array
		if( $aGetId != '' )
			$aPostId = array( $aGetId );

		// Recorremos los id
		foreach( $aPostId as $sId ) {
			$sIds .= $sId . ',';
		}

		// Si tenemos id eliminamos
		if( $sIds != '' ) {
			tep_db_query( 'DELETE FROM opinion WHERE id_opinion IN(' . substr( $sIds, 0, -1 ) . ')' );
		}

		// Redireccionamos
		$messageStack->add_session( OPINIONES_DELETED_SUCCESS, 'success' );
		tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;

	case 'estadisticas':
		// Variables
		$sTitular = OPINIONES_TEXT_STATS;
		$sHtml = '';

		$sHtml = '<div class="toolbarHead">
				<div class="hdr-tlbr">
					<h1 class="pageHeading ftitl" style="top: 13px;">' . OPINIONES_TEXT_STATS . '</h1>
				</div>
			</div>';

		// Lenguaje
		require( getcwd() . '/../includes/languages/' . $language . '/escribir_opinion.php' );

		// Obtenemos cuantas opiniones hemos enviado
		$aDatos = tep_db_query( 'select count(id_opinion) as cantidad from opinion' );
		$aDatos = tep_db_fetch_array( $aDatos );
		$sHtml .= OPINIONES_STATS_TOTAL . $aDatos['cantidad'] . ' ' . OPINIONES_STATS_SENDED_OPINIONS . '.<br/>';

		// Obtenemos cuantas opiniones tenemos escritas
		$aDatos = tep_db_query( 'select count(id_opinion) as cantidad from opinion where status = "true"' );
		$aDatos = tep_db_fetch_array( $aDatos );
		$sHtml .= OPINIONES_STATS_TOTAL . $aDatos['cantidad'] . ' ' . OPINIONES_ANSWERED_OPINIONS . '.<br/>';
		$nMax = $aDatos['cantidad'] * 5;
		$nMax1 = $aDatos['cantidad'];

		// Consultamos los campos
		$aCampos = tep_db_query( 'SHOW COLUMNS FROM opinion' );

		while( $aCampo = tep_db_fetch_array( $aCampos ) )
		{
			// Pasamos de los campos que no se mostraran en el formulario
			if( in_array( $aCampo['Field'], array( 'id_opinion', 'customers_id', 'orders_id', 'fecha_envio', 'email_primero_enviado', 'email_posterior_enviado', 'status', 'status_aprobado' ) ) ) {
				continue;
			}

			// Generamos el nombre de la constante
			// Obtenemos el label (siempre y cuando las constantes estén definidas)
			$constanteName = 'ESCRIBIR_OPINION_LABEL_' . strtoupper($aCampo['Field']);
			if (defined($constanteName)) {
				$sAux = constant($constanteName);
			}

			switch( $aCampo['Type'] )
			{
				// Si es tipo entero mostramos estrellas
				case 'int(11)':
					$aDatos = tep_db_query( 'select sum(' . $aCampo['Field'] . ') as cantidad from opinion where status = "true"' );
					$aDatos = tep_db_fetch_array( $aDatos );

					$sHtml .= '<br/><br/><b>' . $sAux . '</b>';
					$sHtml .= '<br/> ' . OPINIONES_STATS_TOTAL . $aDatos['cantidad'] . ' / ' . $nMax;
					break;

				// Si es un tipo enum, creamos radio
				case (preg_match('/^enum\(/i', $aCampo['Type']) ? true : false):
					$aEnums = explode( "','", preg_replace( '/^enum\(\'|\'\)$/i', '', $aCampo['Type'] ) );

					$sHtml .= '<br/><br/><b>' . $sAux . '</b>';

					foreach( $aEnums as $aEnum )
					{
						$aDatos = tep_db_query( 'select count(id_opinion) as cantidad from opinion
													 where status = "true" and ' . $aCampo['Field'] . ' = "' . $aEnum . '"' );
						$aDatos = tep_db_fetch_array( $aDatos );

						$sHtml .= '<br/>&nbsp;&nbsp;&nbsp;' . $aEnum . ': ' . OPINIONES_STATS_TOTAL . $aDatos['cantidad'] . ' / ' . $nMax1;
					}
					break;

				// Si es tipo text y  empieza con "a_" de array, mostramos checkbox
				case $aCampo['Type'] == 'mediumtext' && (preg_match('/^a_/i', $aCampo['Field']) ? true : false):
					// Recorremos su array
					eval( '$aAuxs = ' . preg_replace( '/^a_/i', 'array_', $aCampo['Field'] ) . ';' );
					$aAuxs = explode( '[dxsepare]', $aAuxs );

					$sHtml .= '<br/><br/><b>' . $sAux . '</b>';

					foreach( $aAuxs as $aAux )
					{
						$aDatos = tep_db_query( 'select count(id_opinion) as cantidad from opinion
													 where status = "true" and ' . $aCampo['Field'] . ' = "' . $aAux . '"' );
						$aDatos = tep_db_fetch_array( $aDatos );
						$sHtml .= '<br/>&nbsp;&nbsp;&nbsp;' . $aAux . ': ' . OPINIONES_STATS_TOTAL . $aDatos['cantidad'] . ' / ' . $nMax1;
					}
					break;
			}
		}
		break;

	case 'update':
	case 'add':
		// Comprobamos el id
		if( $_POST['customers_id'] == '' )
		{
			$messageStack->add_session( 'La opinion debe tener un ID cliente.', 'error' );
			tep_redirect( $_SERVER['HTTP_REFERER'] );
		}

		// Comprobamos el orders_id
		if( $_POST['orders_id'] == '' )
		{
			$messageStack->add_session( 'La opinion debe permanecer a un pedido.', 'error' );
			tep_redirect( $_SERVER['HTTP_REFERER'] );
		}

		$aSql = array();

		// Recreamos la consulta por los campos POST
		foreach( $_POST as $key => $value )
		{
			if( in_array( $key, array( 'id_opinion', 'x', 'y', 'p' ) ) )
				continue;

			if( preg_match( '/^a_/i', $key ) )
				$value =  preg_replace( '/^,/i', '', implode( '[dxsepare]', tep_db_prepare_input( $value ) ) );

			$aSql[$key] = tep_db_prepare_input( $value );
		}

		// Actualizamos la opinion
		tep_db_perform( 'opinion', $aSql, 'update', 'id_opinion = ' . $_POST['id_opinion'] );

		// Mensaje
		$messageStack->add_session(sprintf(($sAction == 'update' ? OPINIONES_UPDATED_SUCCESS : OPINIONES_INSERTED_SUCCESS), $_POST['orders_id']), 'success');

		// Redireccionamos
		tep_redirect( tep_href_link( $sUrl, tep_get_all_get_params( array( 'a', 'id' ) ) . ($_POST['p'] ? '&p=' . $_POST['p'] : '') ) );
		break;

	case 'add_form':
	case 'update_form':
		// Variables
		$sGetId = tep_db_prepare_input( $_GET['id'] );
		$sHtml = '';
		$aSatus   = array( array( 'id' => 'false', 'text' => TEXT_NO ), array( 'id' => 'true', 'text' => TEXT_YES ) );
		$aVotos   = array( array( 'id' => '0', 'text' => '0' ), array( 'id' => '1', 'text' => '1' ), array( 'id' => '2', 'text' => '2' ), array( 'id' => '3', 'text' => '3' ), array( 'id' => '4', 'text' => '4' ), array( 'id' => '5', 'text' => '5' ) );

		// Objeto info
		$oInfo = createObjectInfo( 'opinion' );

		// Si estamos editamos cargamos los valores
		if( $sAction == 'update_form' )
		{
			// Consulta del centro
			$aDatos = tep_db_query( 'select *
										from opinion o
										inner join customers c on (c.customers_id = o.customers_id)
										where id_opinion = ' . $_GET['id'] );

			$aDato = tep_db_fetch_array( $aDatos );
			$oInfo->addProperties( $aDato );

			// Saltos, br
			$oInfo->opinion = str_replace( '<br />', chr(13), (string)( $oInfo->opinion ?? '' ) );
		}

		// Lenguaje
		require( getcwd() . '/../includes/languages/' . $language . '/escribir_opinion.php' );

		$sHtml = '<form action="' . tep_href_link( $sUrl, 'a=update&' . tep_get_all_get_params( array( 'a', 'id' ) ) ) . '" method="post">';
		$sHtml .= '<input id="id_opinion" type="hidden" value="' . $_GET['id'] . '" name="id_opinion"/><input id="orders_id" type="hidden" value="' . $oInfo->orders_id . '" name="orders_id"/>';
		$sHtml .= '<div><div class="toolbarHead">
				<div class="hdr-tlbr">
					<h1 class="pageHeading ftitl" style="top: 13px;">' . sprintf(OPINIONES_EDIT_TITLE, $oInfo->customers_firstname, $oInfo->orders_id) . '</h1>
					<div class="btn-right">
						<a onclick="$(this).closest(\'form\').submit()" href="javascript:void(0);" title="' . TEXT_SAVE . '"><img src="images/icons/icon_save' . ($language == 'espanol' ? '' : '_' . $language) . '.png" class="dx-hovr"></a>
						<a href="' . tep_href_link( $sUrl, tep_get_all_get_params( array( 'a', 'id' ) ) ) . '"><img title="' . TEXT_BACK . '" src="images/icons/icon_back' . ($language == 'espanol' ? '' : '_' . $language) . '.png" class="dx-hovr"></a>
					</div>
				</div>
			</div></div>';

		// Datos generales
		$sHtml .= '<div class="fluid grid">';
		$sHtml .= '<div class="box-tbl grid12">';
		$sHtml .= '<div class="box-head">';
		$sHtml .= '<h6>' . OPINIONES_GENERAL_DATA . '</h6>';
		$sHtml .= '<div class="clear"></div>';
		$sHtml .= '</div>';

		$sHtml .= '<div class="formRow">';
		$sHtml .= '<div class="grid2">';
		$sHtml .= '<label>' . OPINIONES_CUSTOMERS_ID . ':</label>';
		$sHtml .= '</div>';
		$sHtml .= '<div class="grid10">';
		$sHtml .= tep_draw_input_field( 'customers_id', $oInfo->customers_id, '' );
		$sHtml .= '</div>';
		$sHtml .= '<div class="clear"></div>';
		$sHtml .= '</div>';
		$sHtml .= '<div class="formRow">';
		$sHtml .= '<div class="grid2">';
		$sHtml .= '<label>' . OPINIONES_ORDER_NUMBER . ':</label>';
		$sHtml .= '</div>';
		$sHtml .= '<div class="grid10">';
		$sHtml .= tep_draw_input_field( 'orders_id', $oInfo->orders_id, '' );
		$sHtml .= '</div>';
		$sHtml .= '<div class="clear"></div>';
		$sHtml .= '</div>';
		$sHtml .= '<div class="formRow">';
		$sHtml .= '<div class="grid2">';
		$sHtml .= '<label>' . OPINIONES_ANSWERED . ':</label>';
		$sHtml .= '</div>';
		$sHtml .= '<div class="grid10">';
		$sHtml .= tep_draw_pull_down_menu( 'status', $aSatus, $oInfo->status );
		$sHtml .= '</div>';
		$sHtml .= '<div class="clear"></div>';
		$sHtml .= '</div>';
		$sHtml .= '<div class="formRow">';
		$sHtml .= '<div class="grid2">';
		$sHtml .= '<label>' . OPINIONES_OPINIONES_TEXT_APPROVED_SINGLE . ':</label>';
		$sHtml .= '</div>';
		$sHtml .= '<div class="grid10">';
		$sHtml .= tep_draw_pull_down_menu( 'status_aprobado', $aSatus, $oInfo->status_aprobado );
		$sHtml .= '</div>';
		$sHtml .= '<div class="clear"></div>';
		$sHtml .= '</div>';
		$sHtml .= '</div>';
		$sHtml .= '</div>';

		// Opinion
		$sHtml .= '<div class="fluid grid">';
		$sHtml .= '<div class="box-tbl grid12">';
		$sHtml .= '<div class="box-head">';
		$sHtml .= '<h6>Opinion</h6>';
		$sHtml .= '<div class="clear"></div>';
		$sHtml .= '</div>';
		# Inicio, creamos el form automatico
		$aCampos = tep_db_query( 'SHOW COLUMNS FROM opinion' );

		while( $aCampo = tep_db_fetch_array( $aCampos ) )
		{
			// Pasamos de los campos que no se mostraran en el formulario
			if( in_array( $aCampo['Field'], array( 'id_opinion', 'customers_id', 'orders_id', 'fecha_envio', 'email_primero_enviado', 'email_posterior_enviado', 'status', 'status_aprobado', 'uniqid' ) ) )
				continue;

			// Obtenemos el label
			eval( '$sAux = defined("ESCRIBIR_OPINION_LABEL_' . strtoupper( $aCampo['Field'] ) . '") ? ESCRIBIR_OPINION_LABEL_' . strtoupper( $aCampo['Field'] ) . ' : false;' );

			// Obtenemos si tiene alguna nota el campo
			eval( '$sNota = defined("ESCRIBIR_OPINION_NOTA_' . strtoupper( $aCampo['Field'] ) . '") ? ESCRIBIR_OPINION_NOTA_' . strtoupper( $aCampo['Field'] ) . ' : false;' );

			if( $sNota == 'ESCRIBIR_OPINION_NOTA_' . strtoupper( $aCampo['Field'] ) )
				$sNota = '';

			// Valor
			eval( '$sValue = $oInfo->' . $aCampo['Field'] . ';' );

			// Si no es un campo hidden creamos un bloque
			if( ! preg_match( '/^h_/i', $aCampo['Field'] ) )
			{
				$sHtml .= '<div class="formRow">';

				$sHtml .= '<div class="grid4">';
				$sHtml .= '<label for="' . $aCampo['Field'] . '">' . $sAux . '</label>';
				$sHtml .= '</div>';

				$sHtml .= '<div class="grid6">';
			}

			// Comprobamos el tipo de dato
			switch( $aCampo['Type'] )
			{
				// Si es tipo entero mostramos estrellas
				case 'int(11)':
					$sHtml .= '<div class="form-star">
									<div rel="' . tep_db_prepare_input( $sValue ) . '" class="form-star-cntd" style="background-position: 0px 0px;">' .
						tep_draw_hidden_field( $aCampo['Field'], $sValue ) . '
									</div>
								</div>';
					break;

				// Si es tipo text y no empieza con "a_" de array, mostramos un textarea
				case $aCampo['Type'] == 'mediumtext' ||  $aCampo['Type'] == 'text' && (preg_match('/^a_/i', $aCampo['Field']) ? false : true):
					$sHtml .= tep_draw_textarea_field( $aCampo['Field'], '', '0', '5', $sValue );
					break;

				// Si es tipo text y  empieza con "a_" de array, mostramos checkbox
				case $aCampo['Type'] == 'mediumtext' || $aCampo['Type'] == 'text' && (preg_match('/^a_/i', $aCampo['Field']) ? true : false):
					// Recorremos su array
					eval( '$aAuxs = ' . preg_replace( '/^a_/i', 'array_', $aCampo['Field'] ) . ';' );
					$aAuxs = explode( '[dxsepare]', $aAuxs );
					$sValue = explode( '[dxsepare]', $sValue );

					$sHtml .= '<div class="check othr">';
					foreach( $aAuxs as $aAux )
						$sHtml .= '<span class="checked"><input ' . (in_array($aAux, $sValue) ? 'checked="checked"' : '') . ' type="checkbox" name="' . $aCampo['Field'] . '[]" value="' . $aAux . '" />' . $aAux . '</span><br />';
					$sHtml .= '</div>';
					break;

				// Si es un tipo enum, creamos radio
				case (preg_match('/^enum\(/i', $aCampo['Type']) ? true : false):
					$aEnums = explode( "','", preg_replace( '/^enum\(\'|\'\)$/i', '', $aCampo['Type'] ) );
					$sValue = explode( '[dxsepare]', $sValue );

					$sHtml .= '<div class="rdos othr">';
					foreach( $aEnums as $aEnum )
						$sHtml .= '<input style="position: relative; top: -5px;" ' . (in_array($aEnum, $sValue) ? 'checked="checked"' : '') . ' type="radio" name="' . $aCampo['Field'] . '" value="' . $aEnum . '" />' . $aEnum . '<br />';
					$sHtml .= '</div>';
					break;

				// Si es un tipo varchar y es hidden
				case preg_match( '/^h_/i', $aCampo['Field'] ) && preg_match('/^varchar\(/i', $aCampo['Type']):
					$sHtml = preg_replace( '/<\/div>$/i', '<div class="form-pddg" style="display:none;"><div class="grid4" style="margin: 0px;"> </div><div class="grid6">' . tep_draw_input_field( $aCampo['Field'], tep_db_prepare_input( $sValue ), 'size="255"' ) . '</div><div class="clear"></div></div></div>', $sHtml );
					break;
			}

			if( ! preg_match( '/^h_/i', $aCampo['Field'] ) )
			{
				$sHtml .= '</div>';
				$sHtml .= '<div class="clear"></div>';
				$sHtml .= '</div>';
			}
		}
		# Fin, creamos el form automatico
		$sHtml .= '</div>';
		$sHtml .= '</div>';
		$sHtml .= '</form>';
		break;

	case 'ai_batch':
		// Moderación IA por lotes (AJAX, dentro de la sesión admin). Aprueba las seguras,
		// registra el veredicto de todas y deja pendientes las dudosas. Devuelve JSON.
		header( 'Content-Type: application/json; charset=utf-8' );
		$nLimit    = isset( $_GET['n'] ) ? max( 1, min( 25, (int)$_GET['n'] ) ) : 8;
		$nMinStars = isset( $_GET['min_stars'] ) ? (int)$_GET['min_stars'] : OAI_MIN_STARS;
		$oaiC = oai_connect();
		if( ! $oaiC ) { echo json_encode( array( 'error' => 'no_db' ) ); exit(); }
		oai_ensure_schema( $oaiC );
		$res = oai_process_batch( $oaiC, $nMinStars, $nLimit, true, 'admin' );
		$res['remaining'] = oai_count_remaining( $oaiC, $nMinStars );
		mysqli_close( $oaiC );
		echo json_encode( $res, JSON_UNESCAPED_UNICODE );
		exit();
		break;

	default:
		// Aseguramos la tabla de auditoría IA (para el LEFT JOIN y el botón).
		$oaiC = oai_connect();
		if( $oaiC ) { oai_ensure_schema( $oaiC ); mysqli_close( $oaiC ); }

		// Html para el boton masivo
		$sHtmlActionMasivo = '<div id="opciones_masivas" style="float: left; margin-top: -4px; margin-right: 13px;">
				<div class="btn-group">
					<a href="" data-toggle="dropdown" class="buttonS bLightBlue">' . OPINIONES_SELECT_MASIVE_OPTION . '<span class="caret"></span></a>
					<ul class="dropdown-menu">
						<li><a data-question="' . OPINIONES_APPROVE_OPINIONS_CONFIRM . '" data-action="' . tep_href_link( $sUrl ) . '?a=status_massive" href="javascript:void(0);" class="hv"><i class="icos-pencil"></i>' . OPINIONES_APPROVE_OPINIONS . '</a></li>
						<li><a data-question="' . OPINIONES_DELETE_OPINIONS_CONFIRM . '" data-action="' . tep_href_link( $sUrl ) . '?a=delete" href="javascript:void(0);" class="hv"><i class="icos-trash"></i>' . OPINIONES_DELETE_OPINIONS . '</a></li>
					</ul>
				</div>
			</div>
			<div id="ai_moderate_box" style="float: left; margin-top: -4px; margin-right: 13px;">
				<a href="javascript:void(0);" id="ai_moderate_btn" class="buttonS bGreen" style="cursor:pointer;"><i class="icos-cog"></i> ' . OPINIONES_AI_MODERATE . '</a>
				<span id="ai_moderate_status" style="margin-left: 10px; font-weight: bold; vertical-align: middle;"></span>
			</div>';

		// Busqueda
		$sGetSearchApr = tep_db_prepare_input( $_GET['search_apr'] );
		// Por defecto el listado muestra SOLO opiniones contestadas (status='true').
		// Si el usuario elige algo en el filtro Estado (incluido "Todas"), se respeta.
		$sGetSearchEst = isset( $_GET['search_est'] ) ? tep_db_prepare_input( $_GET['search_est'] ) : 'true';
		$sGetSearchIa = isset( $_GET['search_ia'] ) ? tep_db_prepare_input( $_GET['search_ia'] ) : '';
		$sGetFrom = (isset($_GET['from']) ? $_GET['from'] : '');
		$sGetTo = (isset($_GET['to']) ? $_GET['to'] : '');

		// ¿Filtro aplicado explícitamente por el usuario? Controla abrir el panel y mostrar el icono de limpiar
		// (el status='true' por defecto NO cuenta como filtro del usuario).
		$bUserFilter = ( $sGetSearchApr != '' || isset( $_GET['search_est'] ) || $sGetSearchIa != '' || $sGetFrom != '' || $sGetTo != '' );

		if( $sGetSearchApr != '' || $sGetSearchEst != '' || $sGetSearchIa != '' || $sGetFrom != '' || $sGetTo != '' )
			$search = 'where ';

		if( $sGetSearchApr != '' )
		{
			if( $search != 'where ' )
				$search .= ' and';

			$search .= ' o.status_aprobado = "' . $sGetSearchApr . '"';
		}

		if( $sGetSearchEst != '' )
		{
			if( $search != 'where ' )
				$search .= ' and';

			$search .= ' o.status = "' . $sGetSearchEst . '"';
		}

		if( $sGetSearchIa != '' )
		{
			if( $search != 'where ' )
				$search .= ' and';

			if( $sGetSearchIa == 'none' )
				$search .= ' m.id_opinion is null';
			else
				$search .= ' m.decision = "' . $sGetSearchIa . '"';
		}

		if( $sGetFrom != '' )
		{
			$sDate = explode( '/', $sGetFrom );
			$sDateFrom = $sDate[2] . '-' . $sDate[1] . '-' . $sDate[0];

			if( $search != 'where ' )
				$search .= ' and';

			$search .= ' o.fecha_envio >= "' . $sDateFrom . ' 00:00:00" ';
		}

		if( $sGetTo != '' )
		{
			$sDate = explode( '/', $sGetTo );
			$sDateTo = $sDate[2] . '-' . $sDate[1] . '-' . $sDate[0];

			if( $search != 'where ' )
				$search .= ' and';

			$search .= ' o.fecha_envio <= "' . $sDateTo . ' 23:59:59" ';
		}

		// Consulta
		$sSql = 'select o.id_opinion, o.orders_id, date_format(o.fecha_envio, "%d/%m/%Y") as fecha_envio, c.customers_firstname, cg.customers_group_name, o.general, o.comentario_general, o.status, o.status_aprobado, m.decision as ai_decision, m.categoria as ai_categoria, m.motivo as ai_motivo
					 from opinion o
					 inner join customers c on (c.customers_id = o.customers_id)
					 inner join customers_groups cg on(cg.customers_group_id = c.customers_group_id)
					 left join opinion_ai_moderation m on (m.id_opinion = o.id_opinion) ' .
			$search . '
					 order by o.id_opinion desc';

		// Le quitamos los tabuladores y saltos de linea para que splitpageesult funcione con el SQL
		$sSql = preg_replace( '/[\r\n\t]+/', ' ', $sSql );
		$aDatoSplit = new splitPageResults( $sGetPage, MAX_DISPLAY_SEARCH_RESULTS, $sSql, $nAux, 'SELECT COUNT(table_aux.id_opinion) as total FROM (' . $sSql . ') as table_aux' );
		$aDatos = tep_db_query( $sSql );

		$sHtml = '<div class="toolbarHead">
				<div class="hdr-tlbr">
					<h1 class="pageHeading ftitl" style="top: 13px;">' . OPINIONES_HEADING_TITLE . '</h1>
					<div class="btn-right">' .
			($bUserFilter ? '<a href="' . tep_href_link( $sUrl, tep_get_all_get_params( array( 'search_apr', 'search_est', 'search_ia', 'from', 'to', 'page', 'orderby', 'sort' ) ) ) . '" title="' . OPINIONES_CLEAN_FILTER . '"><img class="dx-hovr" src="images/icons/icon_clear_filter' . ($language == 'espanol' ? '' : '_' . $language) . '.png"></a>' : '') . '
					</div>
				</div>
			</div>';

		$sHtml .= $messageStack->output(false);

		$sHtml .= '<div class="box-tbl" style="width: 100%">';
		$sHtml .= '<div class="box-head">
					<h6>' . OPINIONES_HEADING_TITLE . '</h6>
					<a style="padding:8px 12px 0px;" title="' . OPINIONES_FILTER . '" data-id="1" href="javascript:void(0);" class="tOptions filter-togle"><img alt="" src="theme/web/images/icons/usual/icon-cog3.png"></a>
					<div class="clear"></div>
				</div>';

		$sHtml .= '
				<div data-id="1" class="fluid grid tablePars"' . ($bUserFilter ? ' style="display: block;"' : '') . '>
					<form method="get" action="' . tep_href_link( $sUrl ) . '">
						<div class="grid12">
							<div class="formRow" style="border: none; padding: 7px 16px;">
								<div class="grid2"><label>' . OPINIONES_FILTER . ':</label></div>
								<div class="grid10">' .
			tep_draw_pull_down_menu( 'search_apr', array( array( 'id' => '', 'text' => OPINIONES_TEXT_ALL ), array( 'id' => 'true', 'text' => OPINIONES_TEXT_APPROVED ), array( 'id' => 'false', 'text' => OPINIONES_TEXT_NOT_APPROVED ) ), $sGetSearchApr ) . '
								</div>
								<div class="clear"></div>
							</div>
							<div class="formRow" style="border: none; padding: 7px 16px;">
								<div class="grid2"><label>' . OPINIONES_STATUS . ':</label></div>
								<div class="grid10">' .
			tep_draw_pull_down_menu( 'search_est', array( array( 'id' => '', 'text' => OPINIONES_TEXT_ALL ), array( 'id' => 'true', 'text' => OPINIONES_ANSWERED ), array( 'id' => 'false', 'text' => OPINIONES_NOT_ANSWERED ) ), $sGetSearchEst ) . '
								</div>
								<div class="clear"></div>
							</div>
							<div class="formRow" style="border: none; padding: 7px 16px;">
								<div class="grid2"><label>' . OPINIONES_AI_COL . ':</label></div>
								<div class="grid10">' .
			tep_draw_pull_down_menu( 'search_ia', array( array( 'id' => '', 'text' => OPINIONES_TEXT_ALL ), array( 'id' => 'retener', 'text' => 'Retenidas por IA' ), array( 'id' => 'aprobar', 'text' => 'Aprobadas por IA' ), array( 'id' => 'none', 'text' => 'Sin moderar (IA)' ) ), $sGetSearchIa ) . '
								</div>
								<div class="clear"></div>
							</div>
							<div class="formRow" style="border: none; padding: 7px 16px;">
								<div class="grid2"><label>' . OPINIONES_SEND_DATE . ':</label></div>
								<div class="grid10">
									<p style="width:90px;display: inline-block;">' . tep_draw_input_field('from', tep_db_input($sGetFrom), ' class="dxdatepicker" autocomplete="off"') . '</p>
									<p style="width:90px;display: inline-block;">' . tep_draw_input_field('to', tep_db_input($sGetTo), ' class="dxdatepicker" autocomplete="off"') . '</p>
								</div>
								<div class="clear"></div>
							</div>
							<div class="formRow" style="border: none; padding: 7px 16px;">
								<div class="grid12">
									<input type="submit" value="' . OPINIONES_FILTER . '" class="buttonS bGreen" style="cursor: pointer;"/>
								</div>
								<div class="clear"></div>
							</div>
						</div>
					</form>
				</div>';

		$sHtml .= '
				<form method="post" action="' . tep_href_link( $sUrl ) . '">
				<table id="table" class="tAlt wGeneral tDefault" width="100%" cellspacing="0" cellpadding="0">
					<thead>
						<tr>
							<td width="17" class="chck"><input type="checkbox" id="all_check"/><label for="all_check"><span></span></label></td>
							<td width="75">' . OPINIONES_ORDER_NUMBER . '</td>
							<td style="text-align: left;">' . OPINIONES_SEND_DATE . '</td>
							<td style="text-align: left;">' . OPINIONES_CUSTOMER_NAME . '</td>
							<td style="text-align: left;">' . OPINIONES_CUSTOMER_TYPE . '</td>
							<td style="text-align: left;">' . OPINIONES_ASSESSMENT . '</td>
							<td style="text-align: left;">' . OPINIONES_COMMENT . '</td>
							<td style="text-align: left;">' . OPINIONES_STATUS . '</td>
							<td style="text-align: left;">' . OPINIONES_APPROVE . '</td>
							<td style="text-align: left;">' . OPINIONES_AI_COL . '</td>
							<td width="195">' . OPINIONES_ACTIONS . '</td>
						</tr>
					</thead>
					<tbody>';

		while( $aDato = tep_db_fetch_array( $aDatos ) )
		{
			$sHtml .= '<tr>';
			$sHtml .= '<td class="chck" align="center"><input type="checkbox" id="id_' . $aDato['id_opinion'] . '" name="id[]" value="' . $aDato['id_opinion'] . '"/><label for="id_' . $aDato['id_opinion'] . '"><span></span></label></td>';

			$sHtml .= '<td align="center"><a href="' . tep_href_link( 'orders.php', 'oID=' . $aDato['orders_id'] . '&action=edit' ) . '" target="_blank">' . $aDato['orders_id'] . '</a></td>';
			$sHtml .= '<td  align="left">' . $aDato['fecha_envio'] . '</td>';
			$sHtml .= '<td align="left">' . $aDato['customers_firstname'] . '</td>';
			$sHtml .= '<td align="left">' . $aDato['customers_group_name'] . '</td>';
			$sHtml .= '<td align="left">' . show_row_opinion_general( $aDato['general'] ) . '</td>';
			$sHtml .= '<td align="left">' . $aDato['comentario_general'] . '</td>';
			$sHtml .= '<td align="left">' . show_row_opinion_estado( $aDato['status'] ) . '</td>';
			$sHtml .= '<td align="center">
									<div style="cursor:pointer" class="stus" data-id=' . $aDato['id_opinion'] . ' data-status="' . $aDato['status_aprobado'] . '">
										<img width="10" height="10" src="images/icon_status_green' . ($aDato['status_aprobado'] == 'false' ? '_light' : '') . '.gif" />
										<img width="10" height="10" src="images/icon_status_red' . ($aDato['status_aprobado'] == 'true' ? '_light' : '') . '.gif" />
									</div>
								</td>';

			// Veredicto IA (columna informativa)
			$aiDec = $aDato['ai_decision'];
			if( $aiDec === 'aprobar' )
				$sAi = '<span style="color:#2e7d32;font-weight:bold;" title="' . htmlspecialchars( (string)$aDato['ai_motivo'], ENT_QUOTES ) . '">IA OK</span>';
			elseif( $aiDec === 'retener' )
				$sAi = '<span style="color:#c0392b;font-weight:bold;" title="' . htmlspecialchars( (string)$aDato['ai_motivo'], ENT_QUOTES ) . '">&#9888; ' . htmlspecialchars( (string)$aDato['ai_categoria'], ENT_QUOTES ) . '</span>';
			else
				$sAi = '<span style="color:#bbb;">&ndash;</span>';
			$sHtml .= '<td align="center">' . $sAi . '</td>';

			$sHtml .= '<td align="center">
									<div class="btn-group" style="display: inline-block; margin-bottom: -4px;">
										<a class="buttonS bDefault" data-toggle="dropdown" href="#">' . OPINIONES_ACTIONS . '<span class="caret"></span></a>
										<ul class="dropdown-menu">
											<li><a href="' . tep_href_link( $sUrl, 'a=update_form&id=' . $aDato['id_opinion'] . '&' . tep_get_all_get_params( array('a', 'id') ) ) . '"><span class="icos-pencil"></span>' . OPINIONES_TEXT_EDIT . '</a></li>
											<li><a class="dlet" href="' . tep_href_link( $sUrl ) . '?a=delete&id=' . $aDato['id_opinion'] . '"><span class="icos-trash"></span>' . OPINIONES_TEXT_DELETE . '</a></li>
										</ul>
									</div>
								</td>';
			$sHtml .= '</tr>';
		}

		$sHtml .= '</tbody>
				</table>
				</form>';

		$sHtml .= $aDatoSplit->showPaginateTable( tep_get_all_get_params( array('page') ), 'page', $sHtmlActionMasivo ) . '</div><br><br><br>';
		break;
}

include( THEME . 'html/header.php' );

echo '<div id="box-left">';
echo '<ul class="nav">';
echo '<li><a href="' . tep_href_link( $sUrl ) . '" ' . ($sAction != 'estadisticas' ? 'class="active"' : '') . ' data-id="1"><img src="images/icons/productos_datos_generales.png" alt=""><span>' . OPINIONES_TEXT_OPINIONS . '</span></a></li>
			<li><a href="' . tep_href_link( $sUrl ) . '?a=estadisticas" ' . ($sAction == 'estadisticas' ? 'class="active"' : '') . ' data-id="2"><img src="images/icons/productos_seo.png" alt=""><span>' . OPINIONES_TEXT_STATS . '</span></a></li>';
echo '</ul>';
echo '</div>';

echo '<div id="box-right">';
echo $sHtml;
echo '</div>';

include( THEME . 'html/footer.php' );

echo '<style>
		.form-star-cntd
		{
			background-image: url("../theme/web/images/general/comentarios.png");
			background-position: 0 0;
			display: inline-block;
			height: 16px;
			left: 5px;
			position: relative;
			top: 2px;
			width: 80px;
		}

		.rdos .radio
		{
			top: 0px;
			position: relative;
		}
	</style>';

$aDeleteTextos = array(
	OPINIONES_DELETE_FILTER,
	OPINIONES_DELETE_FILTERS
);

echo '<script type="text/javascript">
		$("body").on("change", "#table thead td.chck input", function(e)
		{
			// Si esta pulsado quitamos checkbox
			if( $(this).is(":checked") )
				$(this).closest("table").find("tbody input:not(:checked)").prop( "checked", true );
			else
				$(this).closest("table").find("tbody input:checked").prop( "checked", false );
		});

		// Seleccionar opcion masiva
		$("#opciones_masivas ul a").click(function()
		{
			var dmForm = $($("#table").closest("form"));
			console.log(dmForm);
			var aCheckboxs = dmForm.find("tbody input:checked");

			// Comprobamos si tenemos activo algun checkbox
			if( aCheckboxs.length == 0 )
			{
				alert( "' . OPINIONES_MASSIVE_ERROR . '" );
				return false;
			}

			// Si aceptamos la opcion modificamos el action del form y enviamos
			if( confirm( $(this).data("question") ) )
			{
				// Cambiamos la acción
				dmForm.attr( "action", $(this).data("action") );
				dmForm.attr( "method", "post" );

				// Enviamos el form
				dmForm.submit();
			}

			// Retornamos
			return false;
		});

		// Inicio votar estrellas
		var aPosicionBackground = ["", "0px -16px", "0px -32px", "0px -48px", "0px -64px", "0px -80px" ];
		$( ".form-star-cntd" ).click( function()
		{
			if( $(this).attr( "rel" ) == "no" )
				return;

			$(this).data( "click", $(this).css( "backgroundPosition" ) );
			$(this).find("input").val( $(this).attr( "rel" ) );
		}).mousemove( function(e)
		{
			if( $(this).attr( "rel" ) == "no" )
				return;

			nPosicion = e.pageX - $(this).offset().left;

			if( nPosicion >= 0 && nPosicion <= 16 )
				$(this).css( "backgroundPosition", aPosicionBackground[1] ).attr( "rel", 1 );

			if( nPosicion >= 16 && nPosicion <= 32 )
				$(this).css( "backgroundPosition", aPosicionBackground[2] ).attr( "rel", 2 );

			if( nPosicion >= 32 &&nPosicion <= 48 )
				$(this).css( "backgroundPosition", aPosicionBackground[3] ).attr( "rel", 3 );

			if( nPosicion >= 48 &&nPosicion <= 64 )
				$(this).css( "backgroundPosition", aPosicionBackground[4] ).attr( "rel", 4 );

			if( nPosicion >= 64 &&nPosicion <= 80 )
				$(this).css( "backgroundPosition", aPosicionBackground[5] ).attr( "rel", 5 );
		}).mouseleave(function()
		{
			if( $(this).attr( "rel" ) == "no" )
				return;

			if( ! $(this).data( "click" ) )
				$(this).css( "backgroundPosition", "0px 0px" );
			else
				$(this).css( "backgroundPosition", $(this).data( "click" ) );
		}).each(function(nIndex, elmt)
		{
			sAux = $(elmt).attr( "rel" );

			if( sAux && sAux != "no" )
			{
				$(elmt).css( "backgroundPosition", aPosicionBackground[sAux] );
				$(elmt).trigger( "click" );
			}
		});
		// Fin, votar estrellas

		// Inicio, votar campo hidden
		$(".grid input[type=\'checkbox\']").each(function(nIndex, elmt)
		{
			// Cuando hacemos click mostramos un imput oculto para escribir
			$(elmt).click(function()
			{
				var dmLast = $(this).closest(".othr").find("input").last();

				if( $(this).val() == dmLast.val() )
				{
					var dmElmt = $(this).closest(".formRow").find(".form-pddg");

					if( $(dmElmt).css( "display" ) == "none" )
						$(dmElmt).css( "display", "block" );
					else
					{
						$(dmElmt).css( "display", "none" );
						$(dmElmt).find( "input" ).val( "" );
					}
				}
			});

			// Obtenemos el ultimo elemento y comprobamos si esta activado
			var dmLast = $(this).closest(".othr").find("input").last();

			if( $(this).val() == dmLast.val() )
			{
				var dmElmt = $(this).closest(".formRow").find(".form-pddg");

				// Si esta activado mostramos el div oculto
				if( $(this).is(":checked") )
					dmElmt.css( "display", "block" );
			}
		});

		$(".grid input[type=\'radio\']").each(function(nIndex, elmt)
		{
			// Cuando hacemos click mostramos un imput oculto para escribir
			$(elmt).click(function()
			{
				var dmLast = $(this).closest(".othr").find("input").last();
				var dmElmt = $(this).closest(".formRow").find(".form-pddg");

				if( $(this).val() == dmLast.val() )
					$(dmElmt).css( "display", "block" );
				else
				{
					$(dmElmt).css( "display", "none" );
					$(dmElmt).find( "input" ).val( "" );
				}
			});

			// Obtenemos el ultimo elemento y comprobamos si esta activado
			var dmLast = $(this).closest(".othr").find("input").last();

			if( $(this).val() == dmLast.val() )
			{
				var dmElmt = $(this).closest(".formRow").find(".form-pddg");

				// Si esta activado mostramos el div oculto
				if( $(this).is(":checked") )
					dmElmt.css( "display", "block" );
			}
		});
		// Fin, votar campo hidden

		$(".stus").click(function()
		{
			var sStatus = $(this).data("status");
			var dmElmt = $(this);

			if( sStatus == true )
				sStatus = false;
			else
				sStatus = true;

			$(this).data( "status", sStatus );

			$.ajax({
				url: "' . tep_href_link( $sUrl ) . '?a=status&id=" + $(this).data("id") + "&status=" + sStatus
			}).done(function()
			{
				if( sStatus == true )
					dmElmt.html( \'<img width="10" height="10" src="images/icon_status_green.gif"/> <img width="10" height="10" src="images/icon_status_red_light.gif"/>\');
				else
					dmElmt.html( \'<img width="10" height="10" src="images/icon_status_green_light.gif"/> <img width="10" height="10" src="images/icon_status_red.gif"/>\');
			});
		});

		$("#table").find(\'a[class="dlet"]\').click( function(e)
		{
			if(e)e.stopPropagation();

			if( confirm( "' . OPINIONES_DELETE_CONFIRM . '" ) )
				return true;
			else
				return false;
		});

		$( window ).resize(function()
		{
			$("#box-right").attr( "style", "" );

			if( $("#box-right").height() < $("#box-left").height() )
				$("#box-right").height( $("#box-left").height() - 345 );
		});

		$( window ).trigger("resize");

		// Inicio, moderación con IA (botón -> lotes AJAX hasta agotar pendientes)
		(function()
		{
			var btn = $("#ai_moderate_btn");
			if( ! btn.length ) return;

			var statusEl = $("#ai_moderate_status");
			var base = location.origin + location.pathname; // absoluto: evita mixed-content por <base href> http
			var totals = { processed:0, approved:0, held:0, errors:0 };
			var running = false;

			function runBatch()
			{
				$.ajax({ url: base + "?a=ai_batch&n=8", dataType: "json", cache: false })
				.done(function(res)
				{
					if( ! res || res.error )
					{
						statusEl.css("color","#c0392b").text("Error: " + (res && res.error ? res.error : "respuesta no valida"));
						btn.removeClass("disabled"); running = false; return;
					}

					totals.processed += res.processed;
					totals.approved  += res.approved;
					totals.held      += res.held;
					totals.errors    += res.errors;

					statusEl.css("color","#333").text("Procesadas " + totals.processed + " | aprobadas " + totals.approved + " | a revisar " + totals.held + " | quedan " + res.remaining);

					if( res.processed > 0 && res.remaining > 0 )
						runBatch();
					else
					{
						statusEl.css("color","#2e7d32").text("Hecho. Aprobadas " + totals.approved + ", retenidas " + totals.held + ". Recargando...");
						setTimeout(function(){ location.reload(); }, 1500);
					}
				})
				.fail(function()
				{
					statusEl.css("color","#c0392b").text("Error de red, lote interrumpido. Procesadas " + totals.processed + ". Puedes reintentar.");
					btn.removeClass("disabled"); running = false;
				});
			}

			btn.click(function()
			{
				if( running ) return;
				if( ! confirm("¿Lanzar la moderación con IA de las opiniones pendientes (3 estrellas o más)?\n\nLas correctas se aprobarán automáticamente; las dudosas quedarán pendientes con su motivo en la columna IA.") ) return;
				running = true;
				btn.addClass("disabled");
				statusEl.css("color","#333").text("Procesando...");
				runBatch();
			});
		})();
		// Fin, moderación con IA
	</script>';

include(DIR_WS_INCLUDES . 'application_bottom.php' );
?>
