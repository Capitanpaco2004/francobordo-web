<?php
// Tools
use util\tools as tools;
use util\date as date;

// Incluimos el application_top
require_once( 'includes/application_top.php' );

// Variables
$sUrlPage =  'customers_type.php';
$sPathModule = 'includes/modules/customers_type';
$sPathTemplate = $sPathModule . '/templates';
$sTitle = CUSTOMERS_TYPE_TITLE;
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
			tep_db_query( 'UPDATE customers SET id_customers_type = 0 WHERE id_customers_type IN(' . substr($sIds, 0, -1) . ')');
			tep_db_query( 'DELETE FROM customers_type WHERE id_customers_type IN(' . substr($sIds, 0, -1) . ')');
		}

		$messageStack->addSession( 'success', CUSTOMERS_TYPE_DELETE_SUCCESS, 'success' );
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
			$aRecord = pharaonix_queryOne( 'SELECT * FROM customers_type WHERE id_customers_type = ' . $sGetId );

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
			$sPostNombre = tep_db_prepare_input( $_POST['nombre'] );
			$sPostColor = tep_db_prepare_input( $_POST['color'] );

			$aSql = [
				'nombre' => $sPostNombre,
				'color' => $sPostColor
			];


			if ($sGetId == false) {
                tep_db_perform( 'customers_type', $aSql );
            } else {
                tep_db_perform( 'customers_type', $aSql, 'update', 'id_customers_type = ' . $sPostId );
            }

			// Redireccionamos
			$messageStack->add_session(($sAction == 'update' ? CUSTOMERS_TYPE_EDIT_CONFIRM : CUSTOMERS_TYPE_INSERT_CONFIRM), 'success');
			tep_redirect( tep_href_link(  $sUrlPage ) );
		}

		// Modulo
		$sHtmlModule = includeTemplate( $sPathTemplate . '/crud.php' );
		break;

	default:
		$aButtons = [
			[ 'title' => CUSTOMERS_TYPE_NEW . ' ' . CUSTOMERS_TYPE_TYPE, 'href' => tep_href_link( $sUrlPage, 'action=crud' ), 'icon' => 'fa-plus' ]
		];

		$sHtmlActionMasivo = '<label class="column afluid">' . TEXT_APPLY_ACTION . ':&nbsp;&nbsp;</label>
			<div class="column afluid"><div class="drop masv xfselect">
				<div>' . TABLE_ACTIONS . '</div>
				<ul class="down drch">
					<li><a data-question="' . TEXT_DELETES_CONFIRM . '" data-error="' . TEXT_DELETE_ERROR . '" data-action="' . tep_href_link( $sUrlPage, 'action=delete' ) . '" href="javascript:void(0);" class="hv"><i class="fa fa-trash"></i>' . TEXT_DELETES . '</a></li>
				</ul>
			</div></div>&nbsp; - &nbsp;';

		// Sql
		$sSql = 'SELECT id_customers_type, nombre, color
				 FROM customers_type
				 ORDER BY id_customers_type ASC';

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
