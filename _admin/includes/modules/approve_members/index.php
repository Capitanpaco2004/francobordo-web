<?php
// Tools
use util\tools as tools;
use util\date as date;

// Incluimos el application_top
require( 'includes/application_top.php' );

// Variables
$sUrlPage =  'members.php';
$sPathModule = 'includes/modules/approve_members';
$sPathTemplate = $sPathModule . '/templates';
$sTitle = MEMBERS_HEADING_TITLE;
$sSubtitle = MEMBERS_HEADING_SUBTITLE;
$aButtons = [];
$sPostAction = array_key_exists( 'action', $_POST ) ? tep_db_input( $_POST['action'] ) : (array_key_exists( 'action', $_GET ) ? tep_db_input( $_GET['action'] ) : false);
$sGetPage = tep_db_prepare_input( $_GET['page'] ?? '' );
$sGetOrderby = tep_db_prepare_input( $_GET['orderby'] ?? '' );
$sGetSort = tep_db_prepare_input( $_GET['sort'] ?? '' );

# Messagestack estilo
$messageStack->style = 'solenopsis';

// Acciones
switch($sPostAction) {

	case 'confirm':
	case 'accept':
		$aGetId = tep_db_prepare_input( $_GET['id'] );
		$aPostId = tep_db_prepare_input( $_POST['id'] );
		$sIds = '';

		if( $aGetId != '' ) {
			$aPostId = [$aGetId];
		}

		foreach ($aPostId as $customers_id) {

			if ($sPostAction == 'accept') {
                tep_db_query("update " . TABLE_CUSTOMERS . " set member_level = '1' where customers_id = '" . (int)$customers_id . "'");
            } elseif ($sPostAction == 'confirm') {
                tep_db_query("update " . TABLE_CUSTOMERS . " set member_level = '1', proveedor = '0', customers_group_id = '0' where customers_id = '" . (int)$customers_id . "'");
            }

			// set the language for the email to the customer language
			$customer_language_id_qry = tep_db_query('select customers_language_id from ' . TABLE_CUSTOMERS . ' where customers_id = ' . $customers_id);
			$customer_language_id_res = tep_db_fetch_array($customer_language_id_qry);
			$bLang = true;

			if( $customer_language_id_res['customers_language_id'] != 0 ) {
				$languages = tep_get_languages();
				for ($i=0, $n=count($languages); $i<$n; $i++) {
					if( $languages[$i]['id'] == $customer_language_id_res['customers_language_id'] ) {
						$bLang = false;
						require(DIR_WS_LANGUAGES . $languages[$i]['directory'] . '/' . 'members_mail.php');
						break;
					}
				}

				if ($bLang) {
                    require(DIR_WS_LANGUAGES . $language . '/' . 'members_mail.php');
                }
			} else {
                require(DIR_WS_LANGUAGES . $language . '/' . 'members_mail.php');
            }

			$sCustomer = tep_db_query( 'SELECT * FROM customers WHERE customers_id = ' . tep_db_prepare_input( $customers_id ) );
			$aCustomer = tep_db_fetch_array( $sCustomer );

			// Email
			$mail = new util\mail();

			$sNombre = '<span style="font-size: 24px;">' . $aCustomer['customers_firstname'] . ' ' . $aCustomer['customers_lastname'] . '</span><br/><br/>';

			if ($sPostAction == 'accept') {
				// Importe mínimo de compra anual del grupo Profesionales (1); fallback 250 (mismo valor que usa el cron)
				$minRow = tep_db_fetch_array(tep_db_query("SELECT customers_group_min_annual FROM customers_groups WHERE customers_group_id = 1"));
				$minAnnual = (float)($minRow['customers_group_min_annual'] ?? 0);
				if ($minAnnual <= 0) { $minAnnual = 250; }
				// El cuerpo de aprobación es autocontenido (incluye contacto, nota y despedida)
				$email = $sNombre . sprintf(EMAIL_TEXT_CONFIRM_M, number_format($minAnnual, 0, ',', '.'));
			} else {
				// El cuerpo de rechazo es autocontenido (incluye contacto, nota y despedida)
				$email = $sNombre . EMAIL_TEXT_CANCEL_M;
			}

			$mail->includeEmail('various.php', ['content' => nl2br($email)]);

			tep_mail($aCustomer['customers_name'], $aCustomer['customers_email_address'], ($sPostAction == 'accept' ? EMAIL_TEXT_SUBJECT_M : EMAIL_TEXT_SUBJECT_CANCEL_M), $mail->html, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, '');

			// --- SalesManago — sincronizar el cambio de grupo tras aprobar/denegar ---
			try {
				if (defined('SALESMANAGO_STATUS') && SALESMANAGO_STATUS === 'true') {
					require_once DIR_FS_CATALOG . 'includes/classes/SalesManagoQueue.php';
					SalesManagoQueue::emitContactUpsert((int) $customers_id, false);
				}
			} catch (\Throwable $_smE) { @error_log('SM emit approve: ' . $_smE->getMessage()); }
		}

		tep_redirect(tep_href_link(FILENAME_MEMBERS, tep_get_all_get_params(['id', 'action'])));
		break;

	default:
		$sHtmlActionMasivo = '<label class="column afluid">' . TEXT_APPLY_ACTION . ':&nbsp;&nbsp;</label>
			<div class="column afluid"><div class="drop masv xfselect">
				<div>' . TABLE_HEADING_ACTION . '</div>
				<ul class="down drch">
					<li><a data-question="' . TEXT_ACTIVATE_CONFIRM . '" data-error="' . TEXT_ACTIVATE_ERROR . '" data-action="' . tep_href_link( $sUrlPage, 'action=accept' ) . '" href="javascript:void(0);" class="hv"><i class="fa fa-check-circle"></i>' . TEXT_ACTIVATE . '</a></li>
					<li><a data-question="' . TEXT_CONFIRM_CONFIRM . '" data-error="' . TEXT_CONFIRM_ERROR . '" data-action="' . tep_href_link( $sUrlPage, 'action=confirm' ) . '" href="javascript:void(0);" class="hv"><i class="fa fa-circle-xmark"></i>' . TEXT_CONFIRM . '</a></li>
				</ul>
			</div></div>&nbsp; - &nbsp;';

		// Filtros
		$aFilter = (array_key_exists( 'filter', $_POST ) && is_array($_POST['filter']) ? $_POST['filter'] : []);
		$sWhere = '';

		// Limpiamos variables get filter
		array_walk( $aFilter, function( $value, $key){ global $aFilter; $aFilter[$key] = tep_db_prepare_input( $aFilter[$key] ); } );

		$searchTerm = $aFilter['search'] ?? ''; // Si $aFilter['search'] es null, se usa una cadena vacía
		$keywords = strtolower(tep_db_input(tep_db_prepare_input($searchTerm)));

		if( $aFilter['search'] != '' ) {
			$sWhere .= "WHERE (member_level = '0' and c.customers_lastname LIKE '%" . $keywords . "%' OR c.customers_firstname LIKE '%" . $keywords . "%' OR c.customers_email_address LIKE '%" . $keywords . "')";
		}

		$sWhere .= ( $aFilter['search'] != '' ? ' AND' : 'WHERE') . " member_level = '0'";

		$sSql = "SELECT c.customers_id, c.customers_lastname, c.customers_firstname, c.customers_email_address, c.proveedor_iae, c.customers_telephone,  a.entry_country_id, ci.customers_info_date_account_created
				FROM " . TABLE_CUSTOMERS . " c
				LEFT JOIN " . TABLE_CUSTOMERS_INFO . " ci on(c.customers_id = ci.customers_info_id)
					LEFT JOIN " . TABLE_ADDRESS_BOOK . " a on c.customers_id = a.customers_id and c.customers_default_address_id = a.address_book_id
				" . $sWhere . "
				ORDER BY ci.customers_info_date_account_created DESC";

		if( $aFilter['search'] == '' ) {
			$sWhere = '';
		}

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
