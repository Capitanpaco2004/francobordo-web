<?php
// Tools
use util\tools as tools;
use util\date as date;

// Incluimos el application_top
require_once( 'includes/application_top.php' );

// Variables
$sUrlPage =  'orders_status.php';
$sPathModule = 'includes/modules/orders_status';
$sPathTemplate = $sPathModule . '/templates';
$sTitle = HEADING_TITLE;
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
		$sPostId = tep_db_prepare_input( $_GET['id'] );

		if( $sPostId != '' ) {

			$status_query = tep_db_query("SELECT count(*) AS count FROM " . TABLE_ORDERS . " WHERE orders_status = '" . (int)$sPostId . "'");
			$status = tep_db_fetch_array($status_query);

			$remove_status = true;
			if ($sPostId == DEFAULT_ORDERS_STATUS_ID) {
				$remove_status = false;
				$messageStack->addSession('error', ERROR_REMOVE_DEFAULT_ORDER_STATUS, 'error');
			} elseif ($status['count'] > 0) {
				$remove_status = false;
				$messageStack->addSession('error', ERROR_STATUS_USED_IN_ORDERS, 'error');
			} else {
				$history_query = tep_db_query("SELECT count(*) AS count FROM " . TABLE_ORDERS_STATUS_HISTORY . " WHERE orders_status_id = '" . (int)$sPostId . "'");
				$history = tep_db_fetch_array($history_query);
				if ($history['count'] > 0) {
					$remove_status = false;
					$messageStack->addSession('error', ERROR_STATUS_USED_IN_HISTORY, 'error');

				}
			}

			if(!$remove_status) {
				tep_redirect( tep_href_link(  $sUrlPage ) );
				die();
			}

			$orders_status_query = tep_db_query("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'DEFAULT_ORDERS_STATUS_ID'");
			$orders_status = tep_db_fetch_array($orders_status_query);

			if ($orders_status['configuration_value'] == $sPostId) {
				tep_db_query("update " . TABLE_CONFIGURATION . " set configuration_value = '' where configuration_key = 'DEFAULT_ORDERS_STATUS_ID'");
				require ('includes/configuration_cache.php');
			}

			tep_db_query("DELETE FROM " . TABLE_ORDERS_STATUS . " WHERE orders_status_id = '" . $sPostId . "'");

			$messageStack->addSession( 'success', TEXT_STATUS_DELETE_SUCCESS, 'success' );
			tep_redirect( tep_href_link(  $sUrlPage ) );
		}
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
			$aRecord = pharaonix_queryOne( 'SELECT * FROM orders_status WHERE orders_status_id = ' . $sGetId );

			// Si no existe
			if( $aRecord->num_rows == 0 )
			{
				tep_redirect( tep_href_link(  $sUrlPage ) );
			}

			// Registro
			$aRecord = $aRecord->records;
		}

		// Obtenemos idiomas
		$aLanguages = tep_get_languages();

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$orders_status_id = tep_db_prepare_input($_POST['id']);

			foreach ($aLanguages as $aLanguage) {

				$orders_status_name_array = $_POST['orders_status_name'];
				$language_id = $aLanguage['id'];

				$sql_data_array = [
					'orders_status_name' => tep_db_prepare_input($orders_status_name_array[$language_id]),
					'public_flag' => ((isset($_POST['public_flag']) && ($_POST['public_flag'] == '1')) ? '1' : '0'),
					'sort_order' => $_POST['sort_order'],
					'color' => $_POST['color'],
					'downloads_flag' => ((isset($_POST['downloads_flag']) && ($_POST['downloads_flag'] == '1')) ? '1' : '0')
				];

				if ($sGetId == false) {
					if (empty($orders_status_id)) {
						$next_id_query = tep_db_query("select max(orders_status_id) as orders_status_id from " . TABLE_ORDERS_STATUS . "");
						$next_id = tep_db_fetch_array($next_id_query);
						$orders_status_id = $next_id['orders_status_id'] + 1;
					}

					$insert_sql_data = [
						'orders_status_id' => $orders_status_id,
						'language_id' => $language_id
					];

					$sql_data_array = array_merge($sql_data_array, $insert_sql_data);

					tep_db_perform(TABLE_ORDERS_STATUS, $sql_data_array);
				} else {
					tep_db_perform(TABLE_ORDERS_STATUS, $sql_data_array, 'update', "orders_status_id = '" . (int)$orders_status_id . "' and language_id = '" . (int)$language_id . "'");
				}

			}

			if (isset($_POST['default']) && ($_POST['default'] == 'on')) {
				tep_db_query("update " . TABLE_CONFIGURATION . " set configuration_value = '" . tep_db_input($orders_status_id) . "' where configuration_key = 'DEFAULT_ORDERS_STATUS_ID'");
				require ('includes/configuration_cache.php');
			}

			// Redireccionamos
			$messageStack->add_session(($sGetId !== false ? TEXT_STATUS_EDIT_CONFIRM : TEXT_STATUS_INSERT_CONFIRM), 'success');
			tep_redirect( tep_href_link(  $sUrlPage ) );
		}

		$aStyle = [ $sPathModule . '/css/orders_status.css' ];

		$statusLanguages = [];
		if( $sGetId !== false ) {
			foreach ($aLanguages as $aLanguage) {
				$statusLanguages[$aLanguage['id']] = tep_get_orders_status_name($aRecord['orders_status_id'], $aLanguage['id']);
			}
		}

		// Modulo
		$sHtmlModule = includeTemplate( $sPathTemplate . '/crud.php' );
		break;

	default:
		$aButtons = [
			[ 'title' => TEXT_STATUS_NEW . ' ' . TEXT_STATUS_STATUS, 'href' => tep_href_link( $sUrlPage, 'action=crud' ), 'icon' => 'fa-plus', 'anchor_class' => 'verde' ]
		];

		// Sql
		$sSql = 'SELECT *
				 FROM orders_status
				 WHERE language_id = "' . (int)$languages_id . '"
				 ORDER BY sort_order ASC, orders_status_name ASC';

		// Le quitamos los tabuladores y saltos de linea para que splitpageresult funcione con el SQL
		$sSql = preg_replace( '/[\r\n\t]+/', ' ', $sSql );

		// Sql para el count
		$sSqlCount = 'SELECT COUNT(*) as total FROM (' . $sSql . ') as table_aux';

		// Datos y paginacion
		$aRowsSplit = new splitPageResults( $sGetPage, MAX_DISPLAY_SEARCH_RESULTS, $sSql, $nAux, $sSqlCount );
		$aRows = tep_db_query( $sSql );

		// Obtenemos idiomas
		$aLanguages = tep_get_languages();

		// Modulo
		$sHtmlModule = includeTemplate( $sPathTemplate . '/index.php' );
		break;

}

// Pintamos
echo includeTemplate( $sPathTemplate . '/base.php' );
?>
