<?php
require(DIR_WS_CLASSES . 'shipping.php');
$cShip = new shipping;

require(DIR_WS_CLASSES . 'payment.php');
$cPay = new payment;

switch ($_GET['action'] ?? '') {
	case 's2p_status':
		// Variables
		$sGetId     = (int)tep_db_prepare_input($_GET['id'] ?? 0);
		$sGetStatus = isset($_GET['flag']) && $_GET['flag'] == 'true' ? '1' : '0';

		// Si no tenemos ID
		if ($sGetId == 0 || !in_array($sGetStatus, ['0', '1'])) {
			exit();
		}

		// Modificamos
		tep_db_perform(TABLE_SHIP2PAY, ['status' => $sGetStatus], 'update', 's2p_id = "' . (int)$sGetId . '"');

		// Detenemos
		exit();

	case 's2p_delete':
		// Variables
		$aGetId  = tep_db_prepare_input($_GET['id']);
		$aPostId = tep_db_prepare_input($_POST['id']);
		$sIds    = '';

		// Si nos envian por get creamos el array
		if ($aGetId != '') {
			$aPostId = [$aGetId];
		}

		// Recorremos los id
		foreach ($aPostId as $sId) {
			$sIds .= $sId . ',';
		}

		// Si tenemos id eliminamos
		if ($sIds !== '') {
			tep_db_query('DELETE FROM ' . TABLE_SHIP2PAY . ' where s2p_id in(' . substr($sIds, 0, -1) . ')');
		}

		// Redireccionamos
		$messageStack->addSession('success', SHIP_TO_PAY_DELETE_SUCCESS, 'success');
		tep_redirect($_SERVER['HTTP_REFERER']);
		break;


	case "s2p_crud":
		global $language;

		$sGetId    = array_key_exists('id', $_POST) ? tep_db_input($_POST['id']) : (array_key_exists('id', $_GET) ? tep_db_input($_GET['id']) : false);
		$sSubtitle = ($sGetId != '' ? SHIP_TO_PAY_TEXT_EDITED : SHIP_TO_PAY_TEXT_ADD);

		$aMessageError = [];
		$aButtons      = [
			['title' => TEXT_BACK, 'href' => tep_href_link($sUrlPage, 'action=list'), 'icon' => 'fa-arrow-left'],
			['title' => TEXT_SAVE, 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde']
		];

		$aRecord = [];

		// Si estamos editando
		if ($sGetId != false) {
			// Obtenemos el registro
			$aRecord = pharaonix_queryOne('SELECT * FROM ' . TABLE_SHIP2PAY . ' WHERE s2p_id = "' . (int)$sGetId . '"');

			// Si no existe
			if ($aRecord->num_rows == 0) {
				$messageStack->addSession('success', SHIP_TO_PAY_REGISTER_NO_EXISTS, 'error');
				tep_redirect(tep_href_link($sUrlPage));
			}

			// Registro
			$aRecord = $aRecord->records;
		}
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			if (!isset($_POST['shp_id']) || empty($_POST['shp_id'])) {
				$messageStack->addSession('success', SHIP_TO_PAY_ERROR_SHIP, 'error');

				if ($sGetId != false) {
					tep_redirect(tep_href_link($sUrlPage, 'action=s2p_crud&id=' . $sGetId));
				} else {
					tep_redirect(tep_href_link($sUrlPage, 'action=s2p_crud'));
				}
			} else {
				$selectedBoxes = $_POST['boxes_to'];

				$paymentMethods = (empty($selectedBoxes)) ? '' : implode(";", $selectedBoxes);
				$sql_data_array = ['shipment' => $_POST['shp_id'], 'payments_allowed' => $paymentMethods, 'status' => $_POST['admin_ship_status']];
				if ($sGetId != false) {
					$messageStack->addSession('success', SHIP_TO_PAY_EDIT_SUCCESS, 'success');
					tep_db_perform(TABLE_SHIP2PAY, $sql_data_array, 'update', 's2p_id = ' . (int)$sGetId);
					$gId = $sGetId;
				} else {
					$messageStack->addSession('success', SHIP_TO_PAY_ADD_SUCCESS, 'success');
					tep_db_perform(TABLE_SHIP2PAY, $sql_data_array);
					$gId = tep_db_insert_id();
				}

				tep_redirect(tep_href_link($sUrlPage, 'action=list'));
			}
		}

		$cPay                  = new payment;
		$statusMethod          = 1;
		$shippingMethod        = '';
		$alreadySelectedShipps = [];
		$auxSelectedMethods    = [];

		if ($sGetId != false) {
			$auxSelectedMethods = pharaonix_queryOne('SELECT payments_allowed, status, shipment
					FROM ' . TABLE_SHIP2PAY . '
					WHERE s2p_id = "' . $sGetId . '"',
			);
			$statusMethod       = $auxSelectedMethods->records['status'];
			$shippingMethod     = $auxSelectedMethods->records['shipment'];
			$auxSelectedMethods = explode(";", (string)$auxSelectedMethods->records['payments_allowed']);

		}

		//Cambiar la consulta sql
		$alreadySelectedShippsSql = pharaonix_query('SELECT  shipment FROM ' . TABLE_SHIP2PAY, true);
		foreach ($alreadySelectedShippsSql->records as $record) {
			$alreadySelectedShipps[] = $record['shipment'];
		}

		$aPayMethods = $cPay->getPayMethod($auxSelectedMethods, $language);

		if ($sGetId == false) {
			$aPayMethods['selected']   = $aPayMethods['noSelected'];
			$aPayMethods['noSelected'] = [];
		}
		$boxes = [SHIP_TO_PAY_TABLE_METODOS_DE_PAGO =>
					  [
						  'group'     => [
							  'id'             => 0,
							  'name'           => SHIP_TO_PAY_TABLE_METODOS_DE_PAGO,
							  'name_formatted' => SHIP_TO_PAY_TABLE_METODOS_DE_PAGO,
						  ],
						  'subgroups' => [
							  'selected'    => $aPayMethods['selected'],
							  'no_selected' => $aPayMethods['noSelected'],
						  ],
					  ]];

		//Obtenemos un shipping para saber los metodos de envio
		$cShip = new shipping;

		$aJs    = [$sPathModule . '/js/default.js'];
		$aStyle = [$sPathModule . '/css/ship2pay.css'];

		// Modulo
		$sHtmlModule = includeTemplate($sPathTemplate . '/crud.php', ['boxes' => $boxes]);
		break;

	case 'list':
	default:
		$sSubtitle = SHIP_TO_PAY_LIST_HEADING_SUBTITLE;
		$aButtons  = [
			['title' => SHIP_TO_TEXT_INFO_HEADING_ADD_METHOD, 'href' => tep_href_link($sUrlPage, 'action=s2p_crud'), 'icon' => 'fa-plus'],
		];

		$sHtmlActionMasivo = '<label class="column afluid">' . SHIP_TO_PAY_TEXT_APPLY_ACTION . ':&nbsp;&nbsp;</label>
		<div class="column afluid"><div class="drop masv xfselect">
			<div>' . SHIP_TO_PAY_TABLE_ACTIONS . '</div>
			<ul class="down drch">
				<li><a data-question="' . SHIP_TO_PAY_TEXT_DELETES_CONFIRM . '" data-error="' . SHIP_TO_PAY_TEXT_DELETE_ERROR . '" data-action="' . tep_href_link($sUrlPage, 'action=s2p_delete') . '" href="javascript:void(0);" class="hv"><i class="fa fa-trash"></i>' . SHIP_TO_PAY_TEXT_DELETES . '</a></li>
			</ul>
		</div></div>&nbsp; - &nbsp;';

		// Filtros
		$aFilter = (array_key_exists('filter', $_POST) && is_array($_POST['filter']) ? $_POST['filter'] : []);
		$sWhere  = '';

		// Limpiamos variables get filter
		array_walk($aFilter, function ($value, $key) {
			global $aFilter;
			$aFilter[$key] = tep_db_prepare_input($aFilter[$key]);
		});

		// Sql
		$sSql = 'select s2p_id, shipment, payments_allowed, status from  ' . TABLE_SHIP2PAY;

		// Le quitamos los tabuladores y saltos de linea para que splitpageesult funcione con el SQL
		$sSql = preg_replace('/[\r\n\t]+/', ' ', $sSql);

		// Sql para el count
		$sSqlCount = 'SELECT COUNT(*) as total FROM (' . $sSql . ') as table_aux';

		// Datos y paginacion
		$aRowsSplit = new splitPageResults($sGetPage, MAX_DISPLAY_SEARCH_RESULTS, $sSql, $nAux, $sSqlCount);
		$aRows      = tep_db_query($sSql);

		$payment               = new Payment();
		$payMethodsDictionary  = $payment->getClassNameDictionary($language);
		$ship                  = new Shipping();
		$shipMethodsDictionary = $ship->shippingClassNameDictionary($language);

		// Modulo
		$sHtmlModule = includeTemplate($sPathTemplate . '/list.php');
		break;
}

