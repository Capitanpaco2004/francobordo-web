<?php

// Incluimos el application_top
require 'includes/application_top.php';

// Variables
$sUrlPage = 'zones.php';
$sTitle = ZONES_TITLE;
$sSubtitle = '';
$aButtons = array();
$sPostAction = array_key_exists('action', $_POST) ? tep_db_input($_POST['action']) : (array_key_exists('action', $_GET) ? tep_db_input($_GET['action']) : false);

$sGetPage = (isset($_GET['page']) ? tep_db_prepare_input($_GET['page']) : 1);
$sGetOrderby = (isset($_GET['orderby']) ? tep_db_prepare_input($_GET['orderby']) : 1);
$sGetSort = (isset($_GET['sort']) ? tep_db_prepare_input($_GET['sort']) : 1);
$sHtml = '';


# Messagestack estilo
$messageStack->style = 'solenopsis';

// Acciones
switch ($sPostAction) {
	case 'delete':
		// Variables
		$aGetId = tep_db_prepare_input( $_GET['id'] );
		$aPostId = tep_db_prepare_input( $_POST['id'] );
		$sIds = '';

		// Si nos envian por get creamos el array
		if( $aGetId != '' ) {
			$aPostId = array($aGetId);
		}

		// Recorremos los id
		foreach( $aPostId as $sId ) {
			$sIds .= $sId . ',';
		}

		// Si tenemos id eliminamos
		if( $sIds != '' ) {
			tep_db_query('delete from zones where zone_id IN(' . substr( $sIds, 0, -1 ) . ')' );
		}

		// Redireccionamos
		$messageStack->addSession( 'success', ZONES_DELETE_SUCCESS, 'success' );
		tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;

	case 'update':
	case 'add_form':
		// Variables
		$sGetId = array_key_exists( 'id', $_POST ) ? tep_db_input( $_POST['id'] ) : (array_key_exists( 'id', $_GET ) ? tep_db_input( $_GET['id'] ) : false);
		$aMessageError = array();
		$sSubtitle = ($sGetId != '' ? ZONES_EDIT_ZONE : ZONES_ADD_ZONE);
		$aButtons = array(
				array( 'title' => TEXT_BACK, 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-arrow-left' ),
				array( 'title' => TEXT_SAVE, 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde saveform-products' )
		);
		$aRecord = array();

		// Si estamos editando
		if( $sGetId != false )
		{
			// Obtenemos el registro
			$aRecords = tep_db_query( 'SELECT zone_id, zone_country_id, zone_name, zone_code
										  FROM zones
										  WHERE zone_id = "' . (int)$sGetId . '"' );

			// Si no existe
			if( tep_db_num_rows( $aRecords ) == 0 )
			{
				$messageStack->addSession( 'success', ZONES_NO_EXISTS, 'error' );
				tep_redirect( tep_href_link(  $sUrlPage ) );
			}

			$aRecord = tep_db_fetch_array( $aRecords );
		}

		// Insertar o actualizar
		if( $sPostAction == 'update' )
		{
			// Pasamos todos los post por tep_db_prepare_input
			array_walk( $_POST, function( $value, $key){ global $_POST, $aRecord; $_POST[$key] = tep_db_prepare_input($_POST[$key]);  $aRecord[$key] = $_POST[$key];} );

			// Comprobamos
			if( !array_key_exists( 'zone_name', $_POST ) || (array_key_exists( 'zone_name', $_POST ) && $_POST['zone_name'] == '') )
				$aMessageError['zone_name'] = $messageStack->show( array( 'text' => ZONES_ERROR_ZONE, 'class' => 'error' ) );

			if( !array_key_exists( 'zone_code', $_POST ) || (array_key_exists( 'zone_code', $_POST ) && $_POST['zone_code'] == '') )
				$aMessageError['zone_code'] = $messageStack->show( array( 'text' => ZONES_ERROR_CODE, 'class' => 'error' ) );

			// Si no existe errores actualizamos/insertamos
			if( count( $aMessageError ) == 0 )
			{
				// Array principal
				$aSql = array(
						'zone_name' => $_POST['zone_name'],
						'zone_code' => $_POST['zone_code'],
						'zone_country_id' => $_POST['zone_country_id']
				);

				if( $sGetId != false ) {
					tep_db_perform( 'zones', $aSql, 'update', 'zone_id = "' . (int)$sGetId . '"' );
				}
				else {
					tep_db_perform( 'zones', $aSql);
				}

				// Mensaje
				$messageStack->addSession( 'success', ($sGetId != false ? ZONES_EDIT_SUCCESS : ZONES_ADD_SUCCESS), 'success' );

				// Redireccionamos
				tep_redirect( tep_href_link(  $sUrlPage ) );
			}
		}

		// Formulario
		$sHtml .= '<form method="post" id="saveform-send" action="' . tep_href_link( $sUrlPage, 'action=update' ) . '">';
		$sHtml .= '<div class="oeBox column a12 row ax" style="margin-bottom: 20px;">';
		$sHtml .= '<div class="oeWrpr">';
		$sHtml .= '<div class="oeTitu"><i class="fa fa-pencil-alt"></i> ' . $sSubtitle . '</div>';
		$sHtml .= '<div class="oeCntd row ax xform xform-horizontal">';
		$sHtml .= $sGetId !== false ? '<input type="hidden" name="id" value="' . $sGetId . '" />' : '';
		$sHtml .= '<input type="submit" style="display: none;" />';

		$sHtml .= '<label for="zone_country_id" class="column a02 tright">' . ZONES_COUNTRY . '</label>';
		$sHtml .= '<div class="column a10">';
		$sHtml .= tep_draw_pull_down_menu('zone_country_id', tep_get_countries(), (array_key_exists( 'zone_country_id', $aRecord ) ? $aRecord['zone_country_id'] : (isset( $_POST['zone_country_id'] ) ? $_POST['zone_country_id'] : '')));
		$sHtml .= '<div class="DFhelp">' . ZONES_COUNTRY_HELP . '</div>';
		$sHtml .= '</div>';

		$sHtml .= '<div class="xline xline-dashed"></div>';
		$sHtml .= '<label for="zone_name" class="column a02 tright">' . ZONES_ZONE . '</label>';
		$sHtml .= '<div class="column a10">';
		$sHtml .= '<input type="text" name="zone_name" id="zone_name" value="' . (array_key_exists( 'zone_name', $aRecord ) ? $aRecord['zone_name'] : (isset( $_POST['zone_name'] ) ? $_POST['zone_name'] : '')) . '"/>';
		$sHtml .= '<div class="DFhelp">' . ZONES_ZONE_HELP . '</div>';
		$sHtml .= array_key_exists( 'zone_name', $aMessageError ) ? $aMessageError['zone_name'] : '';
		$sHtml .= '</div>';

		$sHtml .= '<div class="xline xline-dashed"></div>';

		$sHtml .= '<label for="zone_code" class="column a02 tright">' . ZONES_CODE . '</label>';
		$sHtml .= '<div class="column a10">';
		$sHtml .= '<input type="text" name="zone_code" id="zone_code" value="' . (array_key_exists( 'zone_code', $aRecord ) ? $aRecord['zone_code'] : (isset( $_POST['zone_code'] ) ? $_POST['zone_code'] : '')) . '"/>';
		$sHtml .= '<div class="DFhelp">' . ZONES_CODE_HELP . '</div>';
		$sHtml .= array_key_exists( 'zone_code', $aMessageError ) ? $aMessageError['zone_code'] : '';
		$sHtml .= '</div>';

		$sHtml .= '</div>';
		$sHtml .= '</div>';
		$sHtml .= '</div>';
		$sHtml .= '</form>';
		break;

	default:
		// Variables
		$sSubtitle = ZONES_SUBTITLE;
		$aButtons[] = array('title' => TEXT_ADD, 'href' => tep_href_link($sUrlPage, 'action=add_form'), 'icon' => 'fa-plus', 'anchor_class' => 'verde');

		// Html para el boton masivo
		$sHtmlActionMasivo = '<label class="column afluid">' . ZONES_APPLY_ACTION . ':&nbsp;&nbsp;</label>
			<div class="column afluid"><div class="drop masv xfselect">
				<div>' . ZONES_ACTIONS . '</div>
				<ul class="down drch">
					<li><a data-question="' . ZONES_DELETE_RECORDS_CONFIRM . '" data-error="' . ZONES_DELETE_ERROR . '" data-action="' . tep_href_link($sUrlPage, 'action=delete') . '" href="javascript:void(0);" class="hv"><i class="fa fa-trash"></i>' . ZONES_DELETE_RECORDS . '</a></li>
				</ul>
			</div></div>&nbsp; - &nbsp;';

		// Filtros
		$aFiler = array('search' => '');
		$aAuxFilter = array_key_exists('filter', $_GET) && is_array($_GET['filter']) ? $_GET['filter'] : (array_key_exists('filter', $_POST) && is_array($_POST['filter']) ? $_POST['filter'] : array());
		$sWhere = '';

		// Limpiamos variables get filter
		array_walk($aFiler, function ($value, $key) {
			global $aFiler, $aAuxFilter;
			$aFiler[$key] = tep_db_prepare_input(array_key_exists($key, $aAuxFilter) ? $aAuxFilter[$key] : $aFiler[$key]);
		});

		// Where
		if ($aFiler['search'] != '') {
			$sWhere = 'where ';
		}

		if ($aFiler['search'] != '') {
			$sWhere .= ($sWhere != 'where ' ? ' and' : '') . ' ((LOWER(countries_name) LIKE "%' . strtolower($aFiler['search']) . '%") OR (LOWER(zone_name) LIKE "%' . strtolower($aFiler['search']) . '%"))';
		}

		// Order by
		if ($sGetOrderby == 'countries_name') {
			$sOrderby = 'countries_name ' . $sGetSort;
		} elseif ($sGetOrderby == 'zone_name') {
			$sOrderby = 'zone_name ' . $sGetSort;
		} elseif ($sGetOrderby == 'zone_code') {
			$sOrderby = 'zone_code ' . $sGetSort;
		} else {
			$sOrderby = 'c.countries_name asc, z.zone_name asc';
		}

		// Sql
		$sSql = 'SELECT z.zone_id, c.countries_id, c.countries_name, z.zone_name, z.zone_code, z.zone_country_id
				 FROM zones z
				 INNER JOIN countries c ON(z.zone_country_id = c.countries_id)
				 ' . ($sWhere == '' ? 'where c.countries_status = 1 ' : $sWhere . ' and c.countries_status = 1') . ' ORDER BY ' . $sOrderby;

		// Le quitamos los tabuladores y saltos de linea para que splitpageesult funcione con el SQL
		$sSql = preg_replace('/[\r\n\t]+/', ' ', $sSql);

		// Sql para el count
		$sSqlCount = 'SELECT COUNT(table_aux.zone_id) as total FROM (' . $sSql . ') as table_aux';

		// Datos y paginacion
		$aDatoSplit = new splitPageResults($sGetPage, MAX_DISPLAY_SEARCH_RESULTS, $sSql, $nAux, $sSqlCount);
		$aDatos = tep_db_query($sSql);

		// Mensajes comprobamos si tenemos datos
		if (tep_db_num_rows($aDatos) <= 0) {
			if ($sWhere != '')
				$sHtml .= $messageStack->show(array('text' => ZONES_FILTER_NO_DATA, 'class' => 'warning'));
			else
				$sHtml .= $messageStack->show(array('text' => ZONES_NO_DATA, 'class' => 'warning'));
		}

		// Tabla
		$sHtml .= '<div class="oeBox oeTable column a12 row ax">';
		$sHtml .= '<div class="oeWrpr">';
		$sHtml .= '<div class="oeTitu"><i class="fa fa-eye"></i> ' . ZONES_SUBTITLE . '</div>';
		$sHtml .= '<form method="get" action="' . tep_href_link($sUrlPage) . '" class="oeCntd row ax">';
		$sHtml .= '<div class="oeBoxFltr column a12 ax row">';
		$sHtml .= '<div class="column a09 row ax amiddle input-search">';
		$sHtml .= '<label class="column">' . TEXT_SEARCH . ': </label> <div class="column"><input type="text" name="filter[search]" placeholder="' . TEXT_SEARCH_PLACEHOLDER . '" value="' . $aFiler['search'] . '" autofocus/> <i class="fa fa-search"></i></div>';
		$sHtml .= '</div>';
		$sHtml .= '<div class="column a03 tright">';
		$sHtml .= ($sWhere != '' ? '<a title="' . TEXT_CLEAN_FILTER . '" href="' . tep_href_link($sUrlPage, tep_get_all_get_params(array('filter'))) . '" class="xbutton hv9 rojo small"><i class="fa fa fa-close"></i></a> ' : '');
		$sHtml .= '<a href="#fltr-lstd" title="' . TEXT_FILTER . '" class="xbutton small hv9 verde-turquesa mgp-inln"><i class="fa fa-filter"></i></a>';
		$sHtml .= '</div>';
		$sHtml .= '</div>';

		$sHtml .= '<table class="xform">';
		$sHtml .= '<thead>';
		$sHtml .= '<tr>';
		$sHtml .= '<th width="17" class="chck"><input type="checkbox" id="all_check"/><label for="all_check"><span></span></label></th>';
		$sHtml .= '<th class="sort" width="150">' . tableSetSort('countries_name', ZONES_COUNTRY) . '</th>';
		$sHtml .= '<th class="sort">' . tableSetSort('zone_name', ZONES_ZONE) . '</th>';
		$sHtml .= '<th class="sort">' . tableSetSort('zone_code', ZONES_CODE) . '</th>';
		$sHtml .= '<th width="125">' . ZONES_ACTIONS . '</th>';
		$sHtml .= '</tr>';
		$sHtml .= '</thead>';
		$sHtml .= '<tbody>';

		while ($aDato = tep_db_fetch_array($aDatos)) {
			// Fila
			$sHtml .= '<tr data-dblclick="' . tep_href_link($sUrlPage, 'action=add_form&id=' . $aDato['zone_id']) . '">';
			$sHtml .= '<td class="chck" align="center"><input type="checkbox" id="id_' . $aDato['zone_id'] . '" name="id[]" value="' . $aDato['zone_id'] . '"/><label for="id_' . $aDato['zone_id'] . '"><span></span></label></td>';
			$sHtml .= '<td>' . $aDato['countries_name'] . '</td>';
			$sHtml .= '<td>' . $aDato['zone_name'] . '</td>';
			$sHtml .= '<td>' . $aDato['zone_code'] . '</td>';
			$sHtml .= '<td>';
			$sHtml .= '<div class="drop xfselect">';
			$sHtml .= '<div>' . ZONES_ACTIONS . '</div>';
			$sHtml .= '<ul class="down down-dngt">';
			$sHtml .= '<li><a href="' . tep_href_link($sUrlPage, 'action=add_form&id=' . $aDato['zone_id']) . '" class="hv"><i class="fa fa-pencil"></i>' . ZONES_EDIT_RECORD . '</a></li>';
			$sHtml .= '<li><a data-confirm="' . ZONES_DELETE_RECORD_CONFIRM . '" href="' . tep_href_link($sUrlPage, 'action=delete&id=' . $aDato['zone_id']) . '" class="hv"><i class="fa fa-trash"></i>' . ZONES_DELETE_RECORD . '</a></li>';
			$sHtml .= '</ul>';
			$sHtml .= '</div>';
			$sHtml .= '</td>';
			$sHtml .= '</tr>';
		}

		$sHtml .= '</tbody>';
		$sHtml .= '</table>';

		// Paginación
		$sHtml .= $aDatoSplit->showPaginateTable(tep_get_all_get_params(array('page')), 'page', $sHtmlActionMasivo, 'solenopsis');

		$sHtml .= '</div>';
		$sHtml .= '</form>';
		$sHtml .= '</div>';
		$sHtml .= '</div>';

		// Filtro
		$sHtml .= '<form action="' . tep_href_link($sUrlPage) . '" method="get" id="fltr-lstd" class="vntn-form mfp-hide zoom-anim-dialog oeBox mfp-white">';
		$sHtml .= '<div class="oeWrpr">';
		$sHtml .= '<div class="oeTitu"><i class="fa fa-eye"></i> ' . ZONES_SUBTITLE . '</div>';
		$sHtml .= '<div class="oeCntd row ax xform xform-horizontal">';
		$sHtml .= '<label for="search" class="column a02 tright">' . TEXT_SEARCH . ':</label>';
		$sHtml .= '<div class="column a10">';
		$sHtml .= '<input type="text" name="filter[search]" placeholder="' . TEXT_SEARCH_PLACEHOLDER . '" value="' . $aFiler['search'] . '"/> ';
		$sHtml .= '</div>';

		$sHtml .= '<div class="xline xline-none"></div>';
		$sHtml .= '<div class="column a12 tright">';
		$sHtml .= ($sWhere != '' ? '<a href="' . tep_href_link($sUrlPage, tep_get_all_get_params(array('filter'))) . '" class="xbutton hv9 rojo small"><i class="fa fa-close"></i> ' . TEXT_DELETE . '</a> ' : '');
		$sHtml .= '<div class="xbutton verde hv9 small"><input type="submit"/><span class="fa fa fa-filter"></span> ' . TEXT_FILTER . '</div>';
		$sHtml .= '</div>';
		$sHtml .= '</div>';
		$sHtml .= '</div>';
		$sHtml .= '</form>';

		break;
}

// Reemplazamos variable
$sHtmlModuleOe = $sHtml;

// MessageStack
$sMessageStack = $messageStack->output(false);
$messageStack->reset();

// Header
include('theme/solenopsis/html/header.php');

// Cabecera
echo '<div class="oeHead column a12 row ax amiddle aflex">';
echo '<div class="oeTitu column logo afixed" style="padding-left: 59px;"><b><i class="fas fa-globe-asia"></i> ' . $sTitle . '</b>' . ($sSubtitle ? '<small>' . $sSubtitle . '</small>' : '') . '</div>';
echo '<div class="oeButton column dtright">';
foreach ($aButtons as $aButton) {
	echo '<a class="xbutton hv8 small' . (array_key_exists('anchor_class', $aButton) ? ' ' . $aButton['anchor_class'] : '') . '" ' . (array_key_exists('extra', $aButton) ? $aButton['extra'] : '') . ' ' . (array_key_exists('title', $aButton) ? 'title="' . $aButton['title'] . '"' : '') . ' href="' . (array_key_exists('href', $aButton) ? $aButton['href'] : 'javascript:void(0);') . '"><i class="fa ' . $aButton['icon'] . '"></i>' . $aButton['title'] . '</a> ';
}
echo '</div>';
echo '</div>';

// Mensajes
echo $sMessageStack;

// Pintamos
echo $sHtmlModuleOe;

// Footer
include('theme/solenopsis/html/footer.php');
