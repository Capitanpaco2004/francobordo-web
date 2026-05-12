<?php
// Tools
use util\tools as tools;
use util\date as date;

// Incluimos el application_top
require_once( 'includes/application_top.php' );

// Variables
$sUrlPage =  'customers_notes_status.php';
$sPathModule = 'includes/modules/customers_notes_status';
$sPathTemplate = $sPathModule . '/templates';
$sTitle = CUSTOMERS_NOTES_STATUS_TITLE;
$sSubtitle = '';
$aButtons = [];
$sPostAction = array_key_exists( 'action', $_POST ) ? tep_db_input( $_POST['action'] ) : (array_key_exists( 'action', $_GET ) ? tep_db_input( $_GET['action'] ) : false);
$sGetPage = tep_db_prepare_input( $_GET['page'] ?? '' );
$sGetOrderby = tep_db_prepare_input( $_GET['orderby'] ?? '' );
$sGetSort = tep_db_prepare_input( $_GET['sort'] ?? '' );

# Messagestack estilo
$messageStack->style = 'solenopsis';

// Acciones
switch($sPostAction) {

	case 'delete':
		$aGetId = tep_db_prepare_input( $_GET['id'] );
		$aPostId = tep_db_prepare_input( $_POST['id'] );
		$sIds = '';

		if( $aGetId != '' ) {
			$aPostId = [$aGetId];
		}

		foreach( $aPostId as $sId ) {
			$sIds .= $sId . ',';
		}

		if( $sIds !== '' ) {
			tep_db_query( 'DELETE FROM customers_notes WHERE id_customers_notes_status IN(' . substr($sIds, 0, -1) . ')');
			tep_db_query( 'DELETE FROM customers_notes_status WHERE id_customers_notes_status IN(' . substr($sIds, 0, -1) . ')');
		}

		$messageStack->addSession( 'success', CUSTOMERS_NOTES_STATUS_DELETE_SUCCESS, 'success' );
		tep_redirect( tep_href_link(  $sUrlPage ) );
		break;

	case 'crud':
		$sGetId = array_key_exists( 'id', $_POST ) ? tep_db_input( $_POST['id'] ) : (array_key_exists( 'id', $_GET ) ? tep_db_input( $_GET['id'] ) : false);

		$aMessageError = [];
		$aButtons = [
			[ 'title' => TEXT_BACK, 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-arrow-left' ],
			[ 'title' => TEXT_SAVE, 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' ]
		];

		$aRecord = [];

		// Si estamos editando
		if( $sGetId != false ) {
			// Obtenemos el registro
			$aRecord = pharaonix_queryOne( 'SELECT * FROM customers_notes_status WHERE id_customers_notes_status = ' . $sGetId );

			// Si no existe
			if( $aRecord->num_rows == 0 )
			{
				tep_redirect( tep_href_link(  $sUrlPage ) );
			}

			// Registro
			$aRecord = $aRecord->records;
		}

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$sPostId = tep_db_prepare_input( $_POST['id'] );
			$sPostNotesStatus = tep_db_prepare_input( $_POST['customers_notes_status'] );
			$sPostNotesColor = tep_db_prepare_input( $_POST['customers_notes_color'] );

			$aSql = [
				'customers_notes_status' => $sPostNotesStatus,
				'customers_notes_color' => $sPostNotesColor
			];

			if ($sGetId == false) {
                tep_db_perform( 'customers_notes_status', $aSql );
            } else {
                tep_db_perform( 'customers_notes_status', $aSql, 'update', 'id_customers_notes_status = ' . $sPostId );
            }

			// Redireccionamos
			$messageStack->add_session(($sAction == 'update' ? CUSTOMERS_NOTES_STATUS_EDIT_CONFIRM : CUSTOMERS_NOTES_STATUS_INSERT_CONFIRM), 'success');
			tep_redirect( tep_href_link(  $sUrlPage ) );
		}

		// Modulo
		$sHtmlModule = includeTemplate( $sPathTemplate . '/crud.php' );
		break;

	default:
		$aButtons = [
			[ 'title' => CUSTOMERS_NOTES_STATUS_NEW . ' ' . CUSTOMERS_NOTES_STATUS_STATUS, 'href' => tep_href_link( $sUrlPage, 'action=crud' ), 'icon' => 'fa-plus' ]
		];

		$sHtmlActionMasivo = '<label class="column afluid">' . TEXT_APPLY_ACTION . ':&nbsp;&nbsp;</label>
			<div class="column afluid"><div class="drop masv xfselect">
				<div>' . TABLE_ACTIONS . '</div>
				<ul class="down drch">
					<li><a data-question="' . TEXT_DELETES_CONFIRM . '" data-error="' . TEXT_DELETE_ERROR . '" data-action="' . tep_href_link( $sUrlPage, 'action=delete' ) . '" href="javascript:void(0);" class="hv"><i class="fa fa-trash"></i>' . TEXT_DELETES . '</a></li>
				</ul>
			</div></div>&nbsp; - &nbsp;';

		// Sql
		$sSql = 'SELECT id_customers_notes_status, customers_notes_status, customers_notes_color
				 FROM customers_notes_status
				 ORDER BY id_customers_notes_status ASC';

		// Le quitamos los tabuladores y saltos de linea para que splitpageesult funcione con el SQL
		$sSql = preg_replace( '/[\r\n\t]+/', ' ', $sSql );

		// Sql para el count
		$sSqlCount = 'SELECT COUNT(*) as total FROM (' . $sSql . ') as table_aux';

		// Datos y paginacion
		$aRowsSplit = new splitPageResults( $sGetPage, MAX_DISPLAY_SEARCH_RESULTS, $sSql, $nAux, $sSqlCount );
		$aRows = tep_db_query( $sSql );

		// Modulo
		$sHtmlModule = includeTemplate( $sPathTemplate . '/index.php' );
		break;

}

// Pintamos
echo includeTemplate( $sPathTemplate . '/base.php' );
?>
