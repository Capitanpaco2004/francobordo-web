<?php
	use PhpOffice\PhpSpreadsheet\Spreadsheet;
	use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

	// Si nos mandan a instalar cambiamos el modulo por login para que forbidden no salte y podamos instalarlo
	if( array_key_exists( 'action', $_GET ) && $_GET['action'] == 'install' )
	{
		// FIX bypass sin auth: PHP_SELF='index.php' (FILENAME_DEFAULT) hace que tep_admin_check_login
		// salte SOLO el ACL de pagina. NO tocar SCRIPT_FILENAME: asi el login SIGUE exigiendose.
		$_SERVER['PHP_SELF'] = 'index.php';
	}

	require('includes/application_top.php');

	// Variables
	$sUrlPage =  'export_orders.php';
	$sTitle = EXPORT_ORDERS_TITLE;
	$sSubtitle = '';
	$aButtons = [];
	$sPostAction = array_key_exists( 'action', $_POST ) ? tep_db_input( $_POST['action'] ) : (array_key_exists( 'action', $_GET ) ? tep_db_input( $_GET['action'] ) : false);
	$sHtml = '';
	$sHtmlTable = '';
	$fields = ['orders_id' => ['field' => 'oc.orders_id', 'label' => EXPORT_ORDERS_FIELDS_ORDERS_ID],
			   'name' => ['field' => 'oc.customers_name', 'label' => EXPORT_ORDERS_FIELDS_CUSTOMERS_NAME],
			   'email' => ['field' => 'oc.customers_email_address', 'label' => EXPORT_ORDERS_FIELDS_EMAIL],
			   'state' => ['field' => 'oc.delivery_state', 'label' => EXPORT_ORDERS_FIELDS_STATE],
			   'city' => ['field' => 'oc.delivery_city', 'label' => EXPORT_ORDERS_FIELDS_CITY],
			   'country' => ['field' => 'oc.delivery_country', 'label' => EXPORT_ORDERS_FIELDS_COUNTRY],
			   'customer_group' => ['field' => 'cg.customers_group_name', 'label' => EXPORT_ORDERS_FIELDS_GRUPD_CLIENT],
			   'payment_method' => ['field' => 'oc.payment_method', 'label' => EXPORT_ORDERS_FIELDS_PAYMENT_METHOD],
			   'date_purchased' => ['field' => 'oc.date_purchased', 'label' => EXPORT_ORDERS_FIELDS_DATE_PURCHASED],
			   'status' => ['field' => 'os.orders_status_name', 'label' => EXPORT_ORDERS_FIELDS_STATUS],
			   'total' => ['field' => 'ot.text', 'label' => EXPORT_ORDERS_FIELDS_TOTAL],			   
	];

	# Messagestack estilo
	$messageStack->style = 'solenopsis';

	// Acciones
	switch( $sPostAction )
	{
		case 'readme':
			// Variables
			$sSubtitle = 'Readme de instalación';
			$aButtons = [
				[ 'title' => 'Ver módulo', 'icon' => 'fa-arrow-right', 'href' => $sUrlPage ]
			];

			$sHtml = tools::parsedown( DIR_WS_MODULES . '/export_orders/readme.txt' );
		break;

		case 'install':
			// Insertamos admin file
			tools::insertAdminFiles($sUrlPage, 1);

			// Mensajes
			$messageStack->addSession( 'success', 'El módulo <em>' . $sTitle . '</em> se ha instalado correctamente.', 'success' );

			// Redireccionamos
			tep_redirect( $sUrlPage . '?action=readme' );
		break;

		case 'delete':
			// Variables
			$aGetId = tep_db_prepare_input($_GET['file']);
			$aPostId = tep_db_prepare_input($_POST['file']);
			$ids = [];

			// Si nos envian por get creamos el array
			if ($aGetId != '') {
                $aPostId = [$aGetId];
            }

			// Recorremos los id
			foreach( $aPostId as $sId )
				$ids[] = $sId;

			// Si tenemos id eliminamos
			foreach ($ids as $id) {
				unlink(getCwd() . '/../temp/orders/' . $id);
			}

			// Redireccionamos
			$messageStack->addSession( 'success', EXPORT_ORDERS_DELETE_SUCCESS, 'success' );
			tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;

		case 'export':
			$sql = '';
			$from = '';
			$where = '';
			$column = 0;
			$row = 2;
			$alphabet = 'ABCDEFGHIJKLMOPQRSTUVWXYZ';

			array_walk($_POST, function( $value, $key){ global $_POST; $_POST[$key] = tep_db_prepare_input( $_POST[$key] ); });

			foreach ($fields as $id => $field) {
				if (! isset($_POST['fields'][$id])) {
					unset($fields[$id]);
				} else {
					$from .= $field['field'] . ', ';
				}
			}

			if ($_POST['date_start'] != '' && $_POST['date_end'] != '') {
                $date = explode('/', (string) $_POST['date_start']);
                $_POST['date_start'] = $date[2] . '-' . $date[1] . '-' . $date[0] . ' 00:00:00';
                $date = explode('/', (string) $_POST['date_end']);
                $_POST['date_end'] = $date[2] . '-' . $date[1] . '-' . $date[0] . ' 23:59:59';
                $sql_orders = '(SELECT * FROM orders WHERE date_purchased BETWEEN "' . $_POST['date_start'] . '" AND "' . $_POST['date_end'] . '") AS oc';
            } elseif ($_POST['order_start'] != '' && $_POST['order_end'] != '') {
                $sql_orders = '(SELECT * FROM orders WHERE orders_id BETWEEN "' . $_POST['order_start'] . '" AND "' . $_POST['order_end'] . '") AS oc';
            } else {
				$sql_orders = 'orders oc';
			}

			$sql = 'SELECT ' . substr($from, 0, -2) . ' FROM ' . $sql_orders;

			if (isset($fields['status'])) {
				$sql .= ' INNER JOIN orders_status os ON (oc.orders_status = os.orders_status_id AND os.language_id = "' . (int) $languages_id . '")';
			}

			if (isset($fields['total'])) {
				$sql .= ' INNER JOIN orders_total ot ON (oc.orders_id = ot.orders_id AND ot.class = "ot_total")';
			}

			if (isset($fields['customer_group'])) {
				$sql .= ' LEFT JOIN customers c ON c.customers_id = oc.customers_id';
				$sql .= ' LEFT JOIN customers_groups cg USING(customers_group_id)';
			}

			if ($_POST['coupon'] != '') {
				$sql .= ' INNER JOIN orders_total otdc ON (oc.orders_id = otdc.orders_id AND otdc.class = "ot_discount_coupon" AND otdc.title LIKE "% ' . $_POST['coupon'] . ' %")';
			}

			if ($_POST['status'] != '' && $_POST['status'] != '-1') {
				$where .= 'oc.orders_status = "' . (int) $_POST['status'] . '"';
			}

			if ($where !== '') {
				$sql = $sql . ' WHERE ' . $where;
			}

			$orders = tep_db_query($sql);


			$spreadsheet = new Spreadsheet();
			$sheet = $spreadsheet->getActiveSheet();

			foreach ($fields as $field) {
				$sheet->setCellValue($alphabet[$column] . '1', $field['label']);
				$sheet->getColumnDimension($alphabet[$column])->setAutoSize(true);
				$sheet->getStyle($alphabet[$column] . '1')->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
				$sheet->getStyle($alphabet[$column] . '1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('333333');
				++$column;
			}

			$column = 0;

			while ($order = tep_db_fetch_array($orders)) {
				foreach ($fields as $id => $field) {
					$field['field'] = preg_replace('/([A-z]*\.)/i', '', $field['field']);

					if ($id === 'total') {
						$order[$field['field']] = preg_replace('/(\&euro\;)/i', '€', (string) $order[$field['field']]);
						$order[$field['field']] = preg_replace('/(\<strong\>)/i', '', (string) $order[$field['field']]);
						$order[$field['field']] = preg_replace('/(\<\/strong\>)/i', '', (string) $order[$field['field']]);
					}

					$sheet->setCellValue($alphabet[$column] . $row, $order[$field['field']]);
					$sheet->getColumnDimension($alphabet[$column])->setAutoSize(true);

					++$column;
				}

				++$row;
				$column = 0;
			}

			$path = getCwd() . '/../temp/orders/';
			$file = 'export_orders_' . date( 'd_m_Y_H-i-s' ) . '.xlsx';

			if (! is_dir($path)) {
				mkdir($path);
			}

			$writer = new Xlsx($spreadsheet);
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment;filename="' . $file . '"');
			header('Cache-Control: max-age=0');
			header('Pragma: public');
			$writer->save($path . $file);

			// Mensajes
			$messageStack->addSession('success', sprintf(EXPORT_ORDERS_EXPORT_SUCCESS, '/temp/orders/' . $file), 'success');

			// Redireccionamos
			tep_redirect($sUrlPage);
		break;

		default:	
			// Variables
			$sSubtitle = EXPORT_ORDERS_SUBTITLE;
			$aButtons[] = [ 'title' => TEXT_BACK, 'href' => tep_href_link(FILENAME_ORDERS), 'icon' => 'fa-arrow-left' ];
			$aButtons[] = [ 'title' => EXPORT_ORDERS_TEXT_EXPORT, 'href' => 'javascript: void(0);', 'icon' => 'fa-file-export', 'anchor_class' => 'ext-frm-btn' ];

			$aJs = ['includes/modules/export_orders/js/index.js'];
			$aStyle = ['includes/modules/export_orders/css/style.css'];

			// Estado de Pedidos
			$statusList = [['id' => -1, 'text' => TEXT_ALLS]];
			$statuses = tep_db_query('SELECT orders_status_id, orders_status_name FROM ' . TABLE_ORDERS_STATUS . ' WHERE language_id = "' . (int)$languages_id . '"');
			while ($status = tep_db_fetch_array($statuses)) {
				$statusList[] = ['id' => $status['orders_status_id'], 'text' => $status['orders_status_name']];
			}

			// Html para el boton masivo
			$sHtmlActionMasivo = '<label class="column afluid">' . EXPORT_ORDERS_TEXT_APPLY_ACTION . ':&nbsp;&nbsp;</label>
			<div class="column afluid"><div class="drop masv xfselect">
				<div>' . EXPORT_ORDERS_TEXT_ACTIONS . '</div>
				<ul class="down drch">
					<li><a data-question="' . EXPORT_ORDERS_TEXT_DELETE_RECORDS_CONFIRM . '" data-error="' . EXPORT_ORDERS_TEXT_DELETE_RECORDS_ERROR . '" data-action="' . tep_href_link( $sUrlPage, 'action=delete' ) . '" href="javascript:void(0);" class="hv"><i class="fa fa-trash"></i>' . EXPORT_ORDERS_TEXT_DELETE_RECORDS . '</a></li>
				</ul>
			</div></div>&nbsp; - &nbsp;';

			$files = 0;
			$excels = [];

			if (! is_dir(getCwd() . '/../temp/orders/')) {
				mkdir(getCwd() . '/../temp/orders/', 0777, true);
			}

			foreach (new DirectoryIterator(getCwd() . '/../temp/orders/') as $fileInfo) {
				if ($fileInfo->isDot()) {
					continue;
				}

				$excels[$fileInfo->getMTime()] = $fileInfo->getFilename();
			}

			ksort($excels);
			$excels = array_reverse($excels);

			foreach ($excels as $excel) {
				$sHtmlTable .= '<tr>';
					$sHtmlTable .= '<td class="chck" align="center"><input type="checkbox" id="' . $excel . '" name="file[]" value="' . $excel . '"/><label for="' . $excel . '"><span></span></label></td>';
					$sHtmlTable .= '<td><a href="/temp/orders/' . $excel . '" target="_blank">' . $excel . '</a></td>';
					$sHtmlTable .= '<td>' . date('d/m/Y H:i:s', filemtime(getCwd() . '/../temp/orders/' . $excel)) . '</td>';
					$sHtmlTable .= '<td>';
						$sHtmlTable .= '<div class="drop xfselect">';
							$sHtmlTable .= '<div>' . EXPORT_ORDERS_TEXT_ACTIONS . '</div>';
							$sHtmlTable .= '<ul class="down down-dngt">';
								$sHtmlTable .= '<li><a href="/temp/orders/' . $excel . '" class="hv" target="_blank"><i class="fa fa-download"></i>' . EXPORT_ORDERS_TABLE_DOWNLOAD . '</a></li>';
								$sHtmlTable .= '<li><a data-confirm="' . EXPORT_ORDERS_TABLE_DELETE_RECORD_CONFIRM . '" href="' . tep_href_link($sUrlPage, 'action=delete&file=' . $excel) . '" class="hv"><i class="fa fa-trash"></i>' . EXPORT_ORDERS_TABLE_DELETE_RECORD . '</a></li>';
							$sHtmlTable .= '</ul>';
						$sHtmlTable .= '</div>';
					$sHtmlTable .= '</td>';
				$sHtmlTable .= '</tr>';

				++$files;
			}

			// Mensajes comprobamos si tenemos datos
			if($files <= 0) {
				$sHtml .= $messageStack->show( [ 'text' => EXPORT_ORDERS_NO_DATA, 'class' => 'warning' ] );
			}

			// Sql para el count
			$sql = 'SELECT 1';
			$sSqlCount = 'SELECT ' . $files . ' as total';

			// Datos y paginacion
			$aDatoSplit = new splitPageResults($sGetPage, MAX_DISPLAY_SEARCH_RESULTS, $sSql, $nAux, $sSqlCount);

			// Tabla
			$sHtml .= '<div class="oeBox oeTable column a12 row ax">';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-eye"></i> ' . EXPORT_ORDERS_SUBTITLE . '</div>';
					$sHtml .= '<form method="post" action="' . tep_href_link($sUrlPage) . '?action=export" class="oeCntd row ax ext-frm">';	

						$sHtml .= '<div class="oeBoxFltr column a12 ax row no-brdr">';
							$sHtml .= '<div class="column a01 row ax amiddle">';
								$sHtml .= '<label class="column">' . EXPORT_ORDERS_FILTER_DATES . ':</label>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="column a010 row ax amiddle">';
								$sHtml .= '<div class="column"><div class="date-range"><input type="text" class="from" name="date_start" autocomplete="off" /> - <input type="text" class="to" name="date_end" autocomplete="off" /></div></div>';
							$sHtml .= '</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="oeBoxFltr column a12 ax row no-brdr">';
							$sHtml .= '<div class="column a01 row ax amiddle">';
								$sHtml .= '<label class="column">' . EXPORT_ORDERS_FILTER_ORDERS . ':</label>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="column a010 row ax amiddle">';
								$sHtml .= '<div class="column"><input type="text" name="order_start" placeholder="' . EXPORT_ORDERS_FILTER_ORDERS_FROM . '..." autocomplete="off" /> - <input type="text" name="order_end" placeholder="' . EXPORT_ORDERS_FILTER_ORDERS_TO . '..." autocomplete="off" /></div>';
							$sHtml .= '</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="oeBoxFltr column a12 ax row no-brdr">';
							$sHtml .= '<div class="column a01 row ax amiddle">';
								$sHtml .= '<label class="column">' . EXPORT_ORDERS_FILTER_STATUS . ':</label>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="column a010 row ax amiddle">';
								$sHtml .= '<div class="column">' . tep_draw_pull_down_menu('status', $statusList, null ) . '</div>';
							$sHtml .= '</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="oeBoxFltr column a12 ax row no-brdr">';
							$sHtml .= '<div class="column a01 row ax amiddle">';
								$sHtml .= '<label class="column">' . EXPORT_ORDERS_FILTER_COUPON . ':</label>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="column a010 row ax amiddle">';
								$sHtml .= '<div class="column"><input type="text" name="coupon" autocomplete="off" /></div>';
							$sHtml .= '</div>';
						$sHtml .= '</div>';

						$index = 0;

						$sHtml .= '<div class="oeBoxFltr column a12 ax row no-brdr">';
						foreach ($fields as $id => $field) {
							if ($index == 0) {
                                $sHtml .= '<div class="column a01 row ax amiddle">';
                                $sHtml .= '<label class="column">' . EXPORT_ORDERS_FILTER_FIELDS . ':</label>';
                                $sHtml .= '</div>';
                            } elseif ($index % 5 == 0) {
                                $sHtml .= '</div>';
                                $sHtml .= '<div class="oeBoxFltr column a12 ax row no-brdr">';
                                $sHtml .= '<div class="column a01 row ax amiddle">';
                                $sHtml .= '<label class="column">&nbsp;</label>';
                                $sHtml .= '</div>';
                            }

							$sHtml .= '<div class="column a02 row ax amiddle">';
								$sHtml .= '<div class="column"><span class="check">' . tep_draw_checkbox_field('fields[' . $id . ']', '', 1) . ' ' . $field['label'] . '</span></div>';
							$sHtml .= '</div>';

							++$index;
						}
							$sHtml .= '<div class="oeBoxFltr column a12 ax row tleft">';
								$sHtml .= '<div class="xbutton verde hv9 small"><input type="submit"><span class="fa fa-file-export"></span> ' . EXPORT_ORDERS_TEXT_EXPORT . '</div>';
							$sHtml .= '</div>';
						$sHtml .= '</div>';

						$sHtml .= '<table class="xform">';
							$sHtml .= '<thead>';
								$sHtml .= '<tr>';
									$sHtml .= '<th width="17" class="chck"><input type="checkbox" id="all_check"/><label for="all_check"><span></span></label></th>';
									$sHtml .= '<th>' . EXPORT_ORDERS_TABLE_FILE . '</th>';
									$sHtml .= '<th width="150">' . EXPORT_ORDERS_TABLE_DATE . '</th>';
									$sHtml .= '<th width="125">' . EXPORT_ORDERS_TEXT_ACTIONS . '</th>';
								$sHtml .= '</tr>';
							$sHtml .= '</thead>';
							$sHtml .= '<tbody>';

							$sHtml .= $sHtmlTable;

							$sHtml .= '</tbody>';
						$sHtml .= '</table>';

						// Paginación
						$sHtml .= $aDatoSplit->showPaginateTable( tep_get_all_get_params( ['page'] ), 'page', $sHtmlActionMasivo, 'solenopsis' );

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
	include( 'theme/solenopsis/html/header.php' );

	// Cabecera
	echo '<div class="oeHead column a12 row ax amiddle aflex">';
		echo '<div class="oeTitu column logo afixed" style="padding-left: 59px;"><b><i class="fa fa-file-excel"></i> ' . $sTitle . '</b>' . ($sSubtitle !== '' && $sSubtitle !== '0' ? '<small>' . $sSubtitle . '</small>' : '') . '</div>';
		echo '<div class="oeButton column dtright">';
			foreach( $aButtons as $aButton )
				echo '<a class="xbutton hv8 small' . (array_key_exists( 'anchor_class', $aButton ) ? ' ' . $aButton['anchor_class'] : '') . '" ' . (array_key_exists( 'extra', $aButton ) ? $aButton['extra'] : '') . ' ' . (array_key_exists( 'title', $aButton ) ? 'title="' . $aButton['title'] . '"' : '') . ' href="' . (array_key_exists( 'href', $aButton ) ? $aButton['href'] : 'javascript:void(0);') . '"><i class="fa ' . $aButton['icon'] . '"></i>' . $aButton['title'] . '</a> ';
		echo '</div>';
	echo '</div>';
	
	// Mensajes
	echo $sMessageStack;
	
	// Pintamos
	echo $sHtmlModuleOe;
	
	// Footer
	include( 'theme/solenopsis/html/footer.php' );
?>