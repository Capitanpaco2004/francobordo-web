<?php

// Incluimos el application_top
require 'includes/application_top.php';

// Variables
$sUrlPage = 'countries.php';
$sTitle = COUNTRIES_TITLE;
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
	case 'setflag_all':
		// Variables
		$nId = tep_db_prepare_input( $_GET['id'] );
		$nFlag = tep_db_prepare_input( $_GET['flag'] );
		tep_db_query('UPDATE countries set countries_status = "' . (int)$nFlag . '"' );

		tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;

	case 'setflag':
		// Variables
		$nId = tep_db_prepare_input( $_GET['id'] );
		$nFlag = tep_db_prepare_input( $_GET['flag'] );

		tep_db_query('UPDATE countries set countries_status = ' . (int)$nFlag . ' WHERE countries_id = ' . (int)$nId );

		tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;

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
			tep_db_query('delete from countries where countries_id IN(' . substr( $sIds, 0, -1 ) . ')' );
		}

		// Redireccionamos
		$messageStack->addSession( 'success', COUNTRIES_DELETE_RECORDS_SUCCESS, 'success' );
		tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;

	case 'update':
	case 'add_form':
		// Variables
		$sGetId = array_key_exists( 'id', $_POST ) ? tep_db_input( $_POST['id'] ) : (array_key_exists( 'id', $_GET ) ? tep_db_input( $_GET['id'] ) : false);
		$aMessageError = array();
		$sSubtitle = ($sGetId != '' ? COUNTRIES_TITLE_EDIT_COUNTRY : COUNTRIES_TITLE_NEW_COUNTRY);
		$aButtons = array(
				array( 'title' => TEXT_BACK, 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-arrow-left' ),
				array( 'title' => TEXT_SAVE, 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde saveform-products' )
		);
		$aRecord = array();

		// Si estamos editando
		if( $sGetId != false )
		{
			// Obtenemos el registro
			$aRecords = tep_db_query( 'SELECT countries_id, countries_name, countries_iso_code_2, countries_iso_code_3, address_format_id
										  FROM countries
										  WHERE countries_id = "' . (int)$sGetId . '"' );

			// Si no existe
			if( tep_db_num_rows( $aRecords ) == 0 )
			{
				$messageStack->addSession( 'success', COUNTRIES_COUNTRY_NO_EXISTS, 'error' );
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
			if( !array_key_exists( 'countries_name', $_POST ) || (array_key_exists( 'countries_name', $_POST ) && $_POST['countries_name'] == '') )
				$aMessageError['countries_name'] = $messageStack->show( array( 'text' => COUNTRIES_ERROR_COUNTRY, 'class' => 'error' ) );

			if( !array_key_exists( 'countries_iso_code_2', $_POST ) || (array_key_exists( 'countries_iso_code_2', $_POST ) && $_POST['countries_iso_code_2'] == '') )
				$aMessageError['countries_iso_code_2'] = $messageStack->show( array( 'text' => COUNTRIES_ERROR_ISO_CODE_2, 'class' => 'error' ) );

			if( !array_key_exists( 'countries_iso_code_3', $_POST ) || (array_key_exists( 'countries_iso_code_3', $_POST ) && $_POST['countries_iso_code_3'] == '') )
				$aMessageError['countries_iso_code_3'] = $messageStack->show( array( 'text' => COUNTRIES_ERROR_ISO_CODE_3, 'class' => 'error' ) );

			// Si no existe errores actualizamos/insertamos
			if( count( $aMessageError ) == 0 )
			{
				// Array principal
				$aSql = array(
					'countries_name' => $_POST['countries_name'],
					'countries_iso_code_2' => $_POST['countries_iso_code_2'],
					'countries_iso_code_3' => $_POST['countries_iso_code_3'],
					'address_format_id' => $_POST['address_format_id']
				);

				if( $sGetId != false ) {
					tep_db_perform( 'countries', $aSql, 'update', 'countries_id = "' . (int)$sGetId . '"' );
				}
				else {
					tep_db_perform( 'countries', $aSql);
				}

				// Mensaje
				$messageStack->addSession( 'success', ($sGetId != false ? COUNTRIES_EDIT_SUCCESS : COUNTRIES_CREATED_SUCCESS), 'success' );

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

		$sHtml .= '<label for="countries_name" class="column a02 tright">' . COUNTRIES_TABLE_COUNTRY . ':</label>';
		$sHtml .= '<div class="column a10">';
			$sHtml .= '<input type="text" name="countries_name" id="countries_name" value="' . (array_key_exists( 'countries_name', $aRecord ) ? $aRecord['countries_name'] : (isset( $_POST['countries_name'] ) ? $_POST['countries_name'] : '')) . '"/>';
			$sHtml .= '<div class="DFhelp">' . COUNTRIES_COUNTRY_NAME_HELP . '</div>';
			$sHtml .= array_key_exists( 'countries_name', $aMessageError ) ? $aMessageError['countries_name'] : '';
		$sHtml .= '</div>';

		$sHtml .= '<div class="xline xline-dashed"></div>';

		$sHtml .= '<label for="countries_iso_code_2" class="column a02 tright">' . COUNTRIES_TABLE_ISO_CODE . ' (2):</label>';
		$sHtml .= '<div class="column a10">';
			$sHtml .= '<input type="text" name="countries_iso_code_2" id="countries_iso_code_2" value="' . (array_key_exists( 'countries_iso_code_2', $aRecord ) ? $aRecord['countries_iso_code_2'] : (isset( $_POST['countries_iso_code_2'] ) ? $_POST['countries_iso_code_2'] : '')) . '"/>';
			$sHtml .= '<div class="DFhelp">' . COUNTRIES_ISO_CODE_HELP . ' 2.</div>';
			$sHtml .= array_key_exists( 'countries_iso_code_2', $aMessageError ) ? $aMessageError['countries_iso_code_2'] : '';
		$sHtml .= '</div>';

		$sHtml .= '<div class="xline xline-dashed"></div>';

		$sHtml .= '<label for="countries_iso_code_3" class="column a02 tright">' . COUNTRIES_TABLE_ISO_CODE . ' (3):</label>';
		$sHtml .= '<div class="column a10">';
			$sHtml .= '<input type="text" name="countries_iso_code_3" id="countries_iso_code_3" value="' . (array_key_exists( 'countries_iso_code_3', $aRecord ) ? $aRecord['countries_iso_code_3'] : (isset( $_POST['countries_iso_code_3'] ) ? $_POST['countries_iso_code_3'] : '')) . '"/>';
			$sHtml .= '<div class="DFhelp">' . COUNTRIES_ISO_CODE_HELP . ' 3.</div>';
			$sHtml .= array_key_exists( 'countries_iso_code_3', $aMessageError ) ? $aMessageError['countries_iso_code_3'] : '';
		$sHtml .= '</div>';

		$sHtml .= '<div class="xline xline-dashed"></div>';

		$sHtml .= '<label for="address_format_id" class="column a02 tright">' . COUNTRIES_ADDRESS_FORMAT . ':</label>';
		$sHtml .= '<div class="column a10">';
			$sHtml .= tep_draw_pull_down_menu('address_format_id', tep_get_address_formats(), (array_key_exists( 'address_format_id', $aRecord ) ? $aRecord['address_format_id'] : (isset( $_POST['address_format_id'] ) ? $_POST['address_format_id'] : '')));
			$sHtml .= '<div class="DFhelp">' . COUNTRIES_ADDRESS_FORMAT_HELP . '</div>';
		$sHtml .= '</div>';

		$sHtml .= '</div>';
		$sHtml .= '</div>';
		$sHtml .= '</div>';
		$sHtml .= '</form>';
		break;

	default:
		// Variables
		$sSubtitle = COUNTRIES_SUBTITLE;
		$aButtons[] = array('title' => COUNTRIES_BUTTON_DISABLE_ALL, 'href' => tep_href_link($sUrlPage, 'action=setflag_all&flag=0'), 'extra' => 'data-confirm="' . COUNTRIES_BUTTON_DISABLE_ALL_CONFIRM . '"', 'icon' => 'fa-times');
		$aButtons[] = array('title' => COUNTRIES_BUTTON_ENABLE_ALL, 'href' => tep_href_link($sUrlPage, 'action=setflag_all&flag=1'), 'extra' => 'data-confirm="' . COUNTRIES_BUTTON_ENABLE_ALL_CONFIRM . '"', 'icon' => 'fa-check');
		$aButtons[] = array('title' => COUNTRIES_BUTTON_ADD, 'href' => tep_href_link($sUrlPage, 'action=add_form'), 'icon' => 'fa-plus', 'anchor_class' => 'verde');

		// Html para el boton masivo
		$sHtmlActionMasivo = '<label class="column afluid">' . COUNTRIES_TEXT_APPLY_ACTION . ':&nbsp;&nbsp;</label>
			<div class="column afluid"><div class="drop masv xfselect">
				<div>' . COUNTRIES_TEXT_ACTIONS . '</div>
				<ul class="down drch">
					<li><a data-question="' . COUNTRIES_DELETE_RECORDS_CONFIRM . '" data-confirm="' . COUNTRIES_DELETE_ERROR . '" data-action="' . tep_href_link($sUrlPage, 'action=delete') . '" href="javascript:void(0);" class="hv"><i class="fa fa-trash"></i>' . COUNTRIES_DELETE_RECORDS . '</a></li>
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
			$sWhere .= ($sWhere != 'where ' ? ' and' : '') . ' (LOWER(countries_name) LIKE "%' . strtolower($aFiler['search']) . '%")';
		}

		// Order by
		if ($sGetOrderby == 'countries_name') {
			$sOrderby = 'countries_name ' . $sGetSort;
		} elseif ($sGetOrderby == 'countries_iso_code_2') {
			$sOrderby = 'countries_iso_code_2 ' . $sGetSort;
		} elseif ($sGetOrderby == 'countries_iso_code_3') {
			$sOrderby = 'countries_iso_code_3 ' . $sGetSort;
		} elseif ($sGetOrderby == 'countries_status') {
			$sOrderby = 'countries_status ' . $sGetSort;
		} else {
			$sOrderby = 'countries_name asc';
		}

		// Sql
		$sSql = 'SELECT countries_id, countries_name, countries_iso_code_2, countries_iso_code_3, address_format_id, countries_status
				 FROM countries
				 ' . $sWhere . ' ORDER BY ' . $sOrderby;

		// Le quitamos los tabuladores y saltos de linea para que splitpageesult funcione con el SQL
		$sSql = preg_replace('/[\r\n\t]+/', ' ', $sSql);

		// Sql para el count
		$sSqlCount = 'SELECT COUNT(table_aux.countries_id) as total FROM (' . $sSql . ') as table_aux';

		// Datos y paginacion
		$aDatoSplit = new splitPageResults($sGetPage, MAX_DISPLAY_SEARCH_RESULTS, $sSql, $nAux, $sSqlCount);
		$aDatos = tep_db_query($sSql);

		// Mensajes comprobamos si tenemos datos
		if (tep_db_num_rows($aDatos) <= 0) {
			if ($sWhere != '')
				$sHtml .= $messageStack->show(array('text' => COUNTRIES_FILTER_NO_RECORDS, 'class' => 'warning'));
			else
				$sHtml .= $messageStack->show(array('text' => COUNTRIES_NO_RECORDS, 'class' => 'warning'));
		}

		// Tabla
		$sHtml .= '<div class="oeBox oeTable column a12 row ax">';
		$sHtml .= '<div class="oeWrpr">';
		$sHtml .= '<div class="oeTitu"><i class="fa fa-eye"></i> ' . COUNTRIES_TEXT_COUNTRIES_LIST . '</div>';
		$sHtml .= '<form method="get" action="' . tep_href_link($sUrlPage) . '" class="oeCntd row ax">';
		$sHtml .= '<div class="oeBoxFltr column a12 ax row">';
		$sHtml .= '<div class="column a09 row ax amiddle input-search">';
		$sHtml .= '<label class="column">' . TEXT_SEARCH . ': </label> <div class="column"><input type="text" name="filter[search]" placeholder="' . TEXT_SEARCH_PLACEHOLDER . '" value="' . $aFiler['search'] . '" autofocus/> <i class="fa fa-search"></i></div>';
		$sHtml .= '</div>';
		$sHtml .= '<div class="column a03 tright">';
		$sHtml .= ($sWhere != '' ? '<a title="' . COUNTRIES_TEXT_REMOVE_FILTER . '" href="' . tep_href_link($sUrlPage, tep_get_all_get_params(array('filter'))) . '" class="xbutton hv9 rojo small"><i class="fa fa fa-close"></i></a> ' : '');
		$sHtml .= '<a href="#fltr-lstd" title="' . COUNTRIES_TEXT_FILTER_RECORDS . '" class="xbutton small hv9 verde-turquesa mgp-inln"><i class="fa fa-filter"></i></a>';
		$sHtml .= '</div>';
		$sHtml .= '</div>';

		$sHtml .= '<table class="xform">';
		$sHtml .= '<thead>';
		$sHtml .= '<tr>';
		$sHtml .= '<th width="17" class="chck"><input type="checkbox" id="all_check"/><label for="all_check"><span></span></label></th>';
		$sHtml .= '<th class="sort" width="150">' . tableSetSort('countries_name', COUNTRIES_TABLE_COUNTRY) . '</th>';
		$sHtml .= '<th class="sort">' . tableSetSort('countries_iso_code_2', COUNTRIES_TABLE_ISO_CODE) . '</th>';
		$sHtml .= '<th class="sort">' . tableSetSort('countries_iso_code_3', COUNTRIES_TABLE_ISO_CODE) . '</th>';
		$sHtml .= '<th class="sort">' . tableSetSort('countries_status', COUNTRIES_TABLE_STATUS) . '</th>';
		$sHtml .= '<th width="125">' . COUNTRIES_TEXT_ACTIONS .'</th>';
		$sHtml .= '</tr>';
		$sHtml .= '</thead>';
		$sHtml .= '<tbody>';

		while ($aDato = tep_db_fetch_array($aDatos)) {
			// Fila
			$sHtml .= '<tr data-dblclick="' . tep_href_link($sUrlPage, 'action=add_form&id=' . $aDato['countries_id']) . '">';
			$sHtml .= '<td class="chck" align="center"><input type="checkbox" id="id_' . $aDato['countries_id'] . '" name="id[]" value="' . $aDato['countries_id'] . '"/><label for="id_' . $aDato['countries_id'] . '"><span></span></label></td>';
			$sHtml .= '<td>' . $aDato['countries_name'] . '</td>';
			$sHtml .= '<td>' . $aDato['countries_iso_code_2'] . '</td>';
			$sHtml .= '<td>' . $aDato['countries_iso_code_3'] . '</td>';
			$sHtml .= '<td>';
			if ($aDato['countries_status'] == '1') {
				$sHtml .= tep_image(DIR_WS_IMAGES . 'icon_status_green.png', IMAGE_ICON_STATUS_GREEN, 10, 10) . '&nbsp;&nbsp;<a href="' . tep_href_link($sUrlPage, 'action=setflag&flag=0&id=' . $aDato['countries_id'], 'NONSSL') . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_red_light.png', IMAGE_ICON_STATUS_RED_LIGHT, 10, 10) . '</a>';
			}
			else {
				$sHtml .= '<a href="' . tep_href_link($sUrlPage, 'action=setflag&flag=1&id=' . $aDato['countries_id'], 'NONSSL') . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_green_light.png', IMAGE_ICON_STATUS_GREEN_LIGHT, 10, 10) . '</a>&nbsp;&nbsp;' . tep_image(DIR_WS_IMAGES . 'icon_status_red.png', IMAGE_ICON_STATUS_RED, 10, 10);
			}
			$sHtml .= '</td>';
			$sHtml .= '<td>';
			$sHtml .= '<div class="drop xfselect">';
			$sHtml .= '<div>' . COUNTRIES_TEXT_ACTIONS .'</div>';
			$sHtml .= '<ul class="down down-dngt">';
			$sHtml .= '<li><a href="' . tep_href_link($sUrlPage, 'action=add_form&id=' . $aDato['countries_id']) . '" class="hv"><i class="fa fa-pencil"></i>' . COUNTRIES_TABLE_EDIT_RECORD .'</a></li>';
			$sHtml .= '<li><a data-confirm="' . COUNTRIES_TABLE_DELETE_RECORD_CONFIRM .'" href="' . tep_href_link($sUrlPage, 'action=delete&id=' . $aDato['countries_id']) . '" class="hv"><i class="fa fa-trash"></i>' . COUNTRIES_TABLE_DELETE_RECORD .'</a></li>';
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
		$sHtml .= '<div class="oeTitu"><i class="fa fa-eye"></i> ' . COUNTRIES_TEXT_COUNTRIES_LIST . '</div>';
		$sHtml .= '<div class="oeCntd row ax xform xform-horizontal">';
		$sHtml .= '<label for="search" class="column a02 tright">' . TEXT_SEARCH . ':</label>';
		$sHtml .= '<div class="column a10">';
		$sHtml .= '<input type="text" name="filter[search]" placeholder="' . COUNTRIES_SEARCH_PLACEHOLDER . '" value="' . $aFiler['search'] . '"/> ';
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
