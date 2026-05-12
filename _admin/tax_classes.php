<?php

// Incluimos el application_top
require 'includes/application_top.php';

define('DEFAULT_TAX_ID', '1');

// Variables
$sUrlPage = 'tax_classes.php';
$sTitle = TAX_CLASSES_TITLE;
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
			// Si el tipo de impuesto por defecto corresponde al ID, mensaje de error
			if( DEFAULT_TAX_ID == $sId ) {
				$messageStack->addSession( 'error', TAX_CLASSES_DELETE_ERROR_DEFAULT, 'error');
			}
			// Si no, guardamos ID a eliminar
			else {
				$sIds .= $sId . ',';
			}
		}

		// Si tenemos id eliminamos
		if( $sIds != '' ) {
			tep_db_query('delete from ' . TABLE_TAX_CLASS . ' where tax_class_id IN(' . substr( $sIds, 0, -1 ) . ')' );

			// Redireccionamos
			$messageStack->addSession( 'success', TAX_CLASSES_DELETE_SUCCESS, 'success' );
		}

		tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;

	case 'update':
	case 'add_form':
		// Variables
		$sGetId = array_key_exists( 'id', $_POST ) ? tep_db_input( $_POST['id'] ) : (array_key_exists( 'id', $_GET ) ? tep_db_input( $_GET['id'] ) : false);
		$aMessageError = array();
		$sSubtitle = ($sGetId != '' ? TAX_CLASSES_EDIT_TAX_CLASS : TAX_CLASSES_ADD_TAX_CLASS);
		$aButtons = array(
				array( 'title' => IMAGE_BACK, 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-arrow-left' ),
				array( 'title' => IMAGE_SAVE, 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde saveform-products' )
		);
		$aRecord = array();

		// Si estamos editando
		if( $sGetId != false )
		{
			// Obtenemos el registro
			$aRecords = tep_db_query( 'SELECT tax_class_id, tax_class_title, tax_class_description
									   FROM ' . TABLE_TAX_CLASS . '
									   WHERE tax_class_id = "' . (int)$sGetId . '"' );

			// Si no existe
			if( tep_db_num_rows( $aRecords ) == 0 )
			{
				$messageStack->addSession( 'success', TAX_CLASSES_NO_EXISTS, 'error' );
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
			if( !array_key_exists( 'tax_class_title', $_POST ) || (array_key_exists( 'tax_class_title', $_POST ) && $_POST['tax_class_title'] == '') )
				$aMessageError['tax_class_title'] = $messageStack->show( array( 'text' => TAX_CLASSES_ERROR_TITLE, 'class' => 'error' ) );

			// Si no existe errores actualizamos/insertamos
			if( count( $aMessageError ) == 0 )
			{
				// Array principal
				$aSql = array(
						'tax_class_title' => $_POST['tax_class_title'],
						'tax_class_description' => $_POST['tax_class_description'],
						'last_modified' => 'now()'
				);

				if( $sGetId != false ) {
					tep_db_perform( TABLE_TAX_CLASS, $aSql, 'update', 'tax_class_id = "' . (int)$sGetId . '"' );
					$sTaxDefault = (int)$sGetId;
				}
				else {
					$aSql['date_added'] = 'now()';
					tep_db_perform( TABLE_TAX_CLASS, $aSql);
					$sTaxDefault = tep_db_insert_id();
				}

				// Si hemos marcado como predeterminado
				if( isset( $_POST['default'] ) && $_POST['default'] == 'on' ) {
					tep_db_query( 'UPDATE ' . TABLE_CONFIGURATION . '
								   SET configuration_value = "' . (int)$sTaxDefault . '"
								   WHERE configuration_key = "DEFAULT_TAX_ID"' );
					require ('includes/configuration_cache.php');
				}

				// Mensaje
				$messageStack->addSession( 'success', ($sGetId != false ? TAX_CLASSES_EDIT_SUCCESS : TAX_CLASSES_ADD_SUCCESS), 'success' );

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

		$sHtml .= '<label for="tax_class_title" class="column a02 tright">' . TAX_CLASSES_TABLE_TITLE . '</label>';
		$sHtml .= '<div class="column a10">';
		$sHtml .= '<input type="text" name="tax_class_title" id="tax_class_title" value="' . (array_key_exists( 'tax_class_title', $aRecord ) ? $aRecord['tax_class_title'] : (isset( $_POST['tax_class_title'] ) ? $_POST['tax_class_title'] : '')) . '"/>';
		$sHtml .= array_key_exists( 'tax_class_title', $aMessageError ) ? $aMessageError['tax_class_title'] : '';
		$sHtml .= '</div>';

		$sHtml .= '<div class="xline xline-dashed"></div>';

		$sHtml .= '<label for="tax_class_description" class="column a02 tright">' . TAX_CLASSES_TABLE_DESCRIPTION . '</label>';
		$sHtml .= '<div class="column a10">';
		$sHtml .= '<input type="text" name="tax_class_description" id="tax_class_description" value="' . (array_key_exists( 'tax_class_description', $aRecord ) ? $aRecord['tax_class_description'] : (isset( $_POST['tax_class_description'] ) ? $_POST['tax_class_description'] : '')) . '"/>';
		$sHtml .= '</div>';

		if( $sGetId === false || ($sGetId !== false && DEFAULT_TAX_ID != $sGetId) ) {
			$contents[] = array('text' => '<br>' . tep_draw_checkbox_field('default') . ' ' . TAX_CLASSES_TEXT_DEFAULT);

			$sHtml .= '<div class="xline xline-dashed"></div>';

			$sHtml .= '<label for="default" class="column a02 tright inline">' . TAX_CLASSES_TEXT_DEFAULT . '</label>';
			$sHtml .= '<div class="column a10">';
			$sHtml .= tep_draw_checkbox_field( 'default', '', '', '', 'id="default"' ) . '<label style="margin-right: 10px;" for="default"><span></span></label>';

			$sHtml .= '</div>';
		}

		$sHtml .= '</div>';
		$sHtml .= '</div>';
		$sHtml .= '</div>';
		$sHtml .= '</form>';
		break;

	default:
		// Variables
		$sSubtitle = TAX_CLASSES_SUBTITLE;
		$aButtons[] = array('title' => TEXT_ADD, 'href' => tep_href_link($sUrlPage, 'action=add_form'), 'icon' => 'fa-plus', 'anchor_class' => 'verde');

		// Html para el boton masivo
		$sHtmlActionMasivo = '<label class="column afluid">' . TAX_CLASSES_APPLY_ACTION . ':&nbsp;&nbsp;</label>
			<div class="column afluid"><div class="drop masv xfselect">
				<div>' . TAX_CLASSES_ACTIONS . '</div>
				<ul class="down drch">
					<li><a data-question="' . TAX_CLASSES_DELETE_RECORDS_CONFIRM . '" data-error="' . TAX_CLASSES_DELETE_ERROR . '" data-action="' . tep_href_link($sUrlPage, 'action=delete') . '" href="javascript:void(0);" class="hv"><i class="fa fa-trash"></i>' . TAX_CLASSES_DELETE_RECORDS . '</a></li>
				</ul>
			</div></div>&nbsp; - &nbsp;';

		// Order by
		if ($sGetOrderby != '') {
			$sOrderby = $sGetOrderby . ' ' . $sGetSort;
		} else {
			$sOrderby = 'tax_class_id asc';
		}

		// Sql
		$sSql = 'SELECT tax_class_id, tax_class_title, tax_class_description, last_modified, date_added
				 FROM ' . TABLE_TAX_CLASS . '
				 ORDER BY ' . $sOrderby;

		// Le quitamos los tabuladores y saltos de linea para que splitpageesult funcione con el SQL
		$sSql = preg_replace('/[\r\n\t]+/', ' ', $sSql);

		// Sql para el count
		$sSqlCount = 'SELECT COUNT(table_aux.tax_class_id) as total FROM (' . $sSql . ') as table_aux';

		// Datos y paginacion
		$aDatoSplit = new splitPageResults($sGetPage, MAX_DISPLAY_SEARCH_RESULTS, $sSql, $nAux, $sSqlCount);
		$aDatos = tep_db_query($sSql);

		// Mensajes comprobamos si tenemos datos
		if (tep_db_num_rows($aDatos) <= 0) {
			$sHtml .= $messageStack->show(array('text' => TAX_CLASSES_NO_EXISTS, 'class' => 'warning'));
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
		$sHtml .= '<th class="sort">' . tableSetSort('tax_class_title', TAX_CLASSES_TABLE_TYPE) . '</th>';
		$sHtml .= '<th class="sort">' . tableSetSort('tax_class_description', TAX_CLASSES_TABLE_DESCRIPTION) . '</th>';
		$sHtml .= '<th>' . TAX_CLASSES_TABLE_DATE_ADDED . '</th>';
		$sHtml .= '<th class="sort">' . tableSetSort('last_modified', TAX_CLASSES_TABLE_UPDATED_AT) . '</th>';
		$sHtml .= '<th width="125">' . TAX_CLASSES_ACTIONS . '</th>';
		$sHtml .= '</tr>';
		$sHtml .= '</thead>';
		$sHtml .= '<tbody>';

		while ($aDato = tep_db_fetch_array($aDatos)) {
			// Fila
			$sHtml .= '<tr data-dblclick="' . tep_href_link($sUrlPage, 'action=add_form&id=' . $aDato['tax_class_id']) . '">';
			$sHtml .= '<td class="chck" align="center"><input type="checkbox" id="id_' . $aDato['tax_class_id'] . '" name="id[]" value="' . $aDato['tax_class_id'] . '"/><label for="id_' . $aDato['tax_class_id'] . '"><span></span></label></td>';
			$sHtml .= '<td>' . $aDato['tax_class_title'] . (DEFAULT_TAX_ID == $aDato['tax_class_id'] ? ' (' . TEXT_DEFAULT . ')' : '') . '</td>';
			$sHtml .= '<td>' . $aDato['tax_class_description'] . '</td>';
			$sHtml .= '<td>' . tep_date_short( $aDato['date_added'] ) . '</td>';
			$sHtml .= '<td>' . tep_date_short( $aDato['last_modified'] ) . '</td>';
			$sHtml .= '<td>';
			$sHtml .= '<div class="drop xfselect">';
			$sHtml .= '<div>' . TAX_CLASSES_ACTIONS . '</div>';
			$sHtml .= '<ul class="down down-dngt">';
			$sHtml .= '<li><a href="' . tep_href_link($sUrlPage, 'action=add_form&id=' . $aDato['tax_class_id']) . '" class="hv"><i class="fa fa-pencil"></i>' . TAX_CLASSES_EDIT_RECORD . '</a></li>';
			$sHtml .= '<li><a data-confirm="' . TAX_CLASSES_DELETE_RECORD_CONFIRM . '" href="' . tep_href_link($sUrlPage, 'action=delete&id=' . $aDato['tax_class_id']) . '" class="hv"><i class="fa fa-trash"></i>' . TAX_CLASSES_DELETE_RECORD . '</a></li>';
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
