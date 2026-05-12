<?php

// Incluimos el application_top
require 'includes/application_top.php';

// Variables
$sUrlPage = 'tax_rates.php';
$sTitle = TAX_RATES_TITLE;
$sSubtitle = '';
$aButtons = array();
$sPostAction = array_key_exists('action', $_POST) ? tep_db_input($_POST['action']) : (array_key_exists('action', $_GET) ? tep_db_input($_GET['action']) : false);

$sGetPage = (isset($_GET['page']) ? tep_db_prepare_input($_GET['page']) : 1);
$sGetOrderby = (isset($_GET['orderby']) ? tep_db_prepare_input($_GET['orderby']) : '');
$sGetSort = (isset($_GET['sort']) ? tep_db_prepare_input($_GET['sort']) : '');
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
			tep_db_query('delete from ' . TABLE_TAX_RATES . ' where tax_rates_id IN(' . substr( $sIds, 0, -1 ) . ')' );
		}

		// Redireccionamos
		$messageStack->addSession( 'success', TAX_RATES_DELETE_SUCCESS, 'success' );
		tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;

	case 'update':
	case 'add_form':
		// Variables
		$sGetId = array_key_exists( 'id', $_POST ) ? tep_db_input( $_POST['id'] ) : (array_key_exists( 'id', $_GET ) ? tep_db_input( $_GET['id'] ) : false);
		$aMessageError = array();
		$sSubtitle = ($sGetId != '' ? TAX_RATES_EDIT_TAX_RATE : TAX_RATES_ADD_TAX_RATE);
		$aButtons = array(
				array( 'title' => IMAGE_BACK, 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-arrow-left' ),
				array( 'title' => IMAGE_SAVE, 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde saveform-products' )
		);
		$aRecord = array();

		// Si estamos editando
		if( $sGetId != false )
		{
			// Obtenemos el registro
			$aRecords = tep_db_query( 'SELECT tax_rates_id, tax_class_id, tax_zone_id, tax_priority, tax_rate, tax_recargo, tax_description
									   FROM ' . TABLE_TAX_RATES . '
									   WHERE tax_rates_id = "' . (int)$sGetId . '"' );

			// Si no existe
			if( tep_db_num_rows( $aRecords ) == 0 )
			{
				$messageStack->addSession( 'success', TAX_RATES_NO_EXISTS, 'error' );
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
			if( !array_key_exists( 'tax_rate', $_POST ) || (array_key_exists( 'tax_rate', $_POST ) && $_POST['tax_rate'] == '') )
				$aMessageError['tax_rate'] = $messageStack->show( array( 'text' => TAX_RATES_ERROR_PERCENT, 'class' => 'error' ) );

			if( !array_key_exists( 'tax_recargo', $_POST ) || (array_key_exists( 'tax_recargo', $_POST ) && $_POST['tax_recargo'] == '') )
				$aMessageError['tax_recargo'] = $messageStack->show( array( 'text' => TAX_RATES_ERROR_EQUIVALENCE, 'class' => 'error' ) );

			// Si no existe errores actualizamos/insertamos
			if( count( $aMessageError ) == 0 )
			{
				// Array principal
				$aSql = array(
						'tax_class_id' => $_POST['tax_class_id'],
						'tax_zone_id' => $_POST['tax_zone_id'],
						'tax_priority' => $_POST['tax_priority'],
						'tax_rate' => $_POST['tax_rate'],
						'tax_recargo' => $_POST['tax_recargo'],
						'tax_description' => $_POST['tax_description'],
						'last_modified' => 'now()'
				);

				if( $sGetId != false ) {
					tep_db_perform( TABLE_TAX_RATES, $aSql, 'update', 'tax_rates_id = "' . (int)$sGetId . '"' );
				}
				else {
					$aSql['date_added'] = 'now()';
					tep_db_perform( TABLE_TAX_RATES, $aSql);
				}

				// Mensaje
				$messageStack->addSession( 'success', ($sGetId != false ? TAX_RATES_EDIT_SUCCESS : TAX_RATES_ADD_SUCCESS), 'success' );

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

		$sHtml .= '<label for="tax_class_id" class="column a02 tright">' . TAX_RATES_TABLE_CLASS . '</label>';
		$sHtml .= '<div class="column a10">';
		$sHtml .= tep_tax_classes_pull_down('name="tax_class_id"', (array_key_exists( 'tax_class_id', $aRecord ) ? $aRecord['tax_class_id'] : (isset( $_POST['tax_class_id'] ) ? $_POST['tax_class_id'] : '')));
		$sHtml .= '</div>';

		$sHtml .= '<div class="xline xline-dashed"></div>';
		$sHtml .= '<label for="zone_name" class="column a02 tright">' . TAX_RATES_TABLE_ZONE . '</label>';
		$sHtml .= '<div class="column a10">';
		$sHtml .= tep_geo_zones_pull_down('name="tax_zone_id"', (array_key_exists( 'tax_zone_id', $aRecord ) ? $aRecord['tax_zone_id'] : (isset( $_POST['tax_zone_id'] ) ? $_POST['tax_zone_id'] : '')), 2);
		$sHtml .= '</div>';

		$sHtml .= '<div class="xline xline-dashed"></div>';

		$sHtml .= '<label for="tax_rate" class="column a02 tright">' . TAX_RATES_TABLE_PERCENTAGE . '</label>';
		$sHtml .= '<div class="column a10">';
		$sHtml .= '<input type="text" name="tax_rate" id="tax_rate" value="' . (array_key_exists( 'tax_rate', $aRecord ) ? $aRecord['tax_rate'] : (isset( $_POST['tax_rate'] ) ? $_POST['tax_rate'] : '')) . '"/>';
		$sHtml .= array_key_exists( 'tax_rate', $aMessageError ) ? $aMessageError['tax_rate'] : '';
		$sHtml .= '</div>';

		$sHtml .= '<div class="xline xline-dashed"></div>';

		$sHtml .= '<label for="tax_description" class="column a02 tright">' . TAX_RATES_TABLE_DESCRIPTION . '</label>';
		$sHtml .= '<div class="column a10">';
		$sHtml .= '<input type="text" name="tax_description" id="tax_description" value="' . (array_key_exists( 'tax_description', $aRecord ) ? $aRecord['tax_description'] : (isset( $_POST['tax_description'] ) ? $_POST['tax_description'] : '')) . '"/>';
		$sHtml .= '</div>';

		$sHtml .= '<div class="xline xline-dashed"></div>';

		$sHtml .= '<label for="tax_priority" class="column a02 tright">' . TAX_RATES_TABLE_PRIORITY . '</label>';
		$sHtml .= '<div class="column a10">';
		$sHtml .= '<input type="text" name="tax_priority" id="tax_priority" value="' . (array_key_exists( 'tax_priority', $aRecord ) ? $aRecord['tax_priority'] : (isset( $_POST['tax_priority'] ) ? $_POST['tax_priority'] : '')) . '"/>';
		$sHtml .= '<div class="DFhelp">' . TAX_RATES_TABLE_PRIORITY_HELP . '</div>';
		$sHtml .= '</div>';

		$sHtml .= '<div class="xline xline-dashed"></div>';

		$sHtml .= '<label for="tax_recargo" class="column a02 tright">' . TAX_RATES_TABLE_EQUIVALENCE . '</label>';
		$sHtml .= '<div class="column a10">';
		$sHtml .= '<input type="text" name="tax_recargo" id="tax_recargo" value="' . (array_key_exists( 'tax_recargo', $aRecord ) ? $aRecord['tax_recargo'] : (isset( $_POST['tax_recargo'] ) ? $_POST['tax_recargo'] : '')) . '"/>';
		$sHtml .= array_key_exists( 'tax_recargo', $aMessageError ) ? $aMessageError['tax_recargo'] : '';
		$sHtml .= '</div>';

		$sHtml .= '</div>';
		$sHtml .= '</div>';
		$sHtml .= '</div>';
		$sHtml .= '</form>';
		break;

	default:
		// Variables
		$sSubtitle = TAX_RATES_SUBTITLE;
		$aButtons[] = array('title' => TEXT_ADD, 'href' => tep_href_link($sUrlPage, 'action=add_form'), 'icon' => 'fa-plus', 'anchor_class' => 'verde');

		// Html para el boton masivo
		$sHtmlActionMasivo = '<label class="column afluid">' . TAX_RATES_APPLY_ACTION . ':&nbsp;&nbsp;</label>
			<div class="column afluid"><div class="drop masv xfselect">
				<div>' . TAX_RATES_ACTIONS . '</div>
				<ul class="down drch">
					<li><a data-question="' . TAX_RATES_DELETE_RECORDS_CONFIRM . '" data-error="' . TAX_RATES_DELETE_ERROR . '" data-action="' . tep_href_link($sUrlPage, 'action=delete') . '" href="javascript:void(0);" class="hv"><i class="fa fa-trash"></i>' . TAX_RATES_DELETE_RECORDS . '</a></li>
				</ul>
			</div></div>&nbsp; - &nbsp;';

		// Order by
		if ($sGetOrderby != '') {
			$sOrderby = $sGetOrderby . ' ' . $sGetSort;
			if( $sGetOrderby == 'tax_class_title' ) {
				$sOrderby = 'CAST(tax_class_title as unsigned) ' . $sGetSort . ', tax_rate ' . $sGetSort;
			}
		} else {
			$sOrderby = 'tax_priority asc, tax_class_title asc';
		}

		// Sql
		$sSql = 'SELECT tr.tax_rates_id, gz.geo_zone_name, tr.tax_priority, tc.tax_class_title, tr.tax_rate, tr.tax_recargo, tr.date_added, tr.last_modified
				 FROM ' . TABLE_TAX_RATES . ' tr
				 LEFT JOIN ' . TABLE_TAX_CLASS . ' tc ON(tr.tax_class_id = tc.tax_class_id)
				 INNER JOIN ' . TABLE_GEO_ZONES . ' gz ON(tr.tax_zone_id = gz.geo_zone_id)
				 ORDER BY ' . $sOrderby;

		// Le quitamos los tabuladores y saltos de linea para que splitpageesult funcione con el SQL
		$sSql = preg_replace('/[\r\n\t]+/', ' ', $sSql);

		// Sql para el count
		$sSqlCount = 'SELECT COUNT(table_aux.tax_rates_id) as total FROM (' . $sSql . ') as table_aux';

		// Datos y paginacion
		$aDatoSplit = new splitPageResults($sGetPage, MAX_DISPLAY_SEARCH_RESULTS, $sSql, $nAux, $sSqlCount);
		$aDatos = tep_db_query($sSql);

		// Mensajes comprobamos si tenemos datos
		if (tep_db_num_rows($aDatos) <= 0) {
			$sHtml .= $messageStack->show(array('text' => TEXT_INFO_NOT_EXIST, 'class' => 'warning'));
		}

		// Tabla
		$sHtml .= '<div class="oeBox oeTable column a12 row ax">';
		$sHtml .= '<div class="oeWrpr">';
		$sHtml .= '<div class="oeTitu"><i class="fa fa-eye"></i> ' . $sSubtitle . '</div>';
		$sHtml .= '<form method="get" action="' . tep_href_link($sUrlPage) . '" class="oeCntd row ax">';
		$sHtml .= '<table class="xform">';
		$sHtml .= '<thead>';
		$sHtml .= '<tr>';
		$sHtml .= '<th width="17" class="chck"><input type="checkbox" id="all_check"/><label for="all_check"><span></span></label></th>';
		$sHtml .= '<th class="sort" width="50">' . tableSetSort('tax_priority', TAX_RATES_TABLE_PRIORITY) . '</th>';
		$sHtml .= '<th class="sort">' . tableSetSort('tax_class_title', TAX_RATES_TABLE_DESCRIPTION) . '</th>';
		$sHtml .= '<th class="sort">' . tableSetSort('geo_zone_name', TAX_RATES_TABLE_ZONE) . '</th>';
		$sHtml .= '<th class="sort" width="50">' . tableSetSort('tax_rate', TAX_RATES_TABLE_PERCENTAGE) . '</th>';
		$sHtml .= '<th class="sort" width="180">' . tableSetSort('tax_recargo', TAX_RATES_TABLE_EQUIVALENCE) . '</th>';
		$sHtml .= '<th>' . TAX_RATES_TABLE_DATE_ADDED . '</th>';
		$sHtml .= '<th class="sort">' . tableSetSort('last_modified', TAX_RATES_TABLE_UPDATED_AT) . '</th>';
		$sHtml .= '<th width="125">' . TAX_RATES_ACTIONS . '</th>';
		$sHtml .= '</tr>';
		$sHtml .= '</thead>';
		$sHtml .= '<tbody>';

		while ($aDato = tep_db_fetch_array($aDatos)) {
			// Fila
			$sHtml .= '<tr data-dblclick="' . tep_href_link($sUrlPage, 'action=add_form&id=' . $aDato['tax_rates_id']) . '">';
			$sHtml .= '<td class="chck" align="center"><input type="checkbox" id="id_' . $aDato['tax_rates_id'] . '" name="id[]" value="' . $aDato['tax_rates_id'] . '"/><label for="id_' . $aDato['tax_rates_id'] . '"><span></span></label></td>';
			$sHtml .= '<td>' . $aDato['tax_priority'] . '</td>';
			$sHtml .= '<td>' . $aDato['tax_class_title'] . '</td>';
			$sHtml .= '<td>' . $aDato['geo_zone_name'] . '</td>';
			$sHtml .= '<td>' . tep_display_tax_value( $aDato['tax_rate'] ) . '%</td>';
			$sHtml .= '<td>' . $aDato['tax_recargo'] . '%</td>';
			$sHtml .= '<td>' . tep_date_short( $aDato['date_added'] ) . '</td>';
			$sHtml .= '<td>' . tep_date_short( $aDato['last_modified'] ) . '</td>';
			$sHtml .= '<td>';
			$sHtml .= '<div class="drop xfselect">';
			$sHtml .= '<div>' . TAX_RATES_ACTIONS . '</div>';
			$sHtml .= '<ul class="down down-dngt">';
			$sHtml .= '<li><a href="' . tep_href_link($sUrlPage, 'action=add_form&id=' . $aDato['tax_rates_id']) . '" class="hv"><i class="fa fa-pencil"></i>' . TAX_RATES_EDIT_RECORD . '</a></li>';
			$sHtml .= '<li><a data-confirm="' . TAX_RATES_DELETE_RECORD_CONFIRM . '" href="' . tep_href_link($sUrlPage, 'action=delete&id=' . $aDato['tax_rates_id']) . '" class="hv"><i class="fa fa-trash"></i>' . TAX_RATES_DELETE_RECORD . '</a></li>';
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
echo '<div class="oeTitu column logo afixed" style="padding-left: 59px;"><b><i class="fas fa-percentage"></i> ' . $sTitle . '</b>' . ($sSubtitle ? '<small>' . $sSubtitle . '</small>' : '') . '</div>';
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
