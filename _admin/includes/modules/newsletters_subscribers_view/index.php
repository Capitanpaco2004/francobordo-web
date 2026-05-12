<?php
	// Tools
	use util\tools as tools;
	use util\date as date;

	// Incluimos el application_top
	require_once( 'includes/application_top.php' );
	require( 'includes/modules/newsletters_subscribers_view/includes/functions.php' );

	// Mostrar errores
	ini_set('display_errors', 1);
	error_reporting(E_ERROR | E_WARNING | E_PARSE);

	// Variables
	$sUrlPage =  'newsletters_subscribers_view.php';
	$sPathModule = 'includes/modules/newsletters_subscribers_view';
	$sPathTemplate = $sPathModule . '/template';
	$sTitle = NEWSLETTERS_SUBSCRIBERS_TITLE;
	$sSubtitle = '';
	$aButtons = [];
	$sPostAction = array_key_exists( 'action', $_POST ) ? tep_db_input( $_POST['action'] ) : (array_key_exists( 'action', $_GET ) ? tep_db_input( $_GET['action'] ) : false);
	$sGetPage = tep_db_prepare_input( $_GET['page'] ?? '' );
	$sGetOrderby = tep_db_prepare_input( $_GET['orderby'] ?? '' );
	$sGetSort = tep_db_prepare_input( $_GET['sort'] ?? '' );

	# Messagestack estilo
	$messageStack->style = 'solenopsis';

	// Acciones
	switch($sPostAction)
	{
		case 'delete':
			// Recolección de IDs desde GET o POST
			$aPostId = isset($_POST['id']) ? (array)tep_db_prepare_input($_POST['id']) : [];
			$aGetId = isset($_GET['id']) ? [tep_db_prepare_input($_GET['id'])] : [];

			// Unificamos los IDs en un solo array
			$aIds = array_merge($aPostId, $aGetId);

			// Filtramos y validamos los IDs para evitar inyecciones SQL
			$aValidIds = array_filter($aIds, 'is_numeric');

			if ($aValidIds !== []) {
				$sIds = implode(',', array_map('intval', $aValidIds)); // Convertimos a enteros y generamos la cadena
				tep_db_query('DELETE FROM subscribers WHERE subscribers_id IN (' . $sIds . ')');
				$messageStack->addSession('success', NEWSLETTERS_SUBSCRIBERS_DELETE_SUCCESS, 'success');
			} else {
				$messageStack->addSession('error', 'No se encontraron IDs válidos para eliminar.', 'error');
			}

			// Redireccionamos
			tep_redirect($_SERVER['HTTP_REFERER']);
			break;


		case 'crud':
			// Variables
			$sGetId = array_key_exists( 'id', $_POST ) ? tep_db_input( $_POST['id'] ) : (array_key_exists( 'id', $_GET ) ? tep_db_input( $_GET['id'] ) : false);
			$aMessageError = [];
			$sSubtitle = ($sGetId != '' ? TEXT_EDIT : TEXT_ADD) . ' ' . NEWSLETTERS_SUBSCRIBERS_NEW_EDIT_SUBSCRIBER;
			$aButtons = [
				[ 'title' => TEXT_BACK, 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-arrow-left' ],
				[ 'title' => TEXT_SAVE, 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' ]
			];
			$aRecord = [];

			// Si estamos editando
			if( $sGetId != false )
			{
				// Obtenemos el registro
				$aRecord = pharaonix_queryOne( 'SELECT * FROM subscribers WHERE subscribers_id = "' . (int)$sGetId . '"' );

				// Si no existe
				if( $aRecord->num_rows == 0 )
				{
					$messageStack->addSession( 'success', NEWSLETTERS_SUBSCRIBERS_EDIT_RECORD_NO_EXISTS, 'error' );
					tep_redirect( tep_href_link(  $sUrlPage ) );
				}

				// Registro
				$aRecord = $aRecord->records;
			}

			// Insertar o actualizar
			if( $_SERVER['REQUEST_METHOD'] === 'POST' )
			{
				// Pasamos todos los post por tep_db_prepare_input
				array_walk( $_POST, function( $value, $key){ global $_POST, $aRecord; $_POST[$key] = tep_db_prepare_input( $_POST[$key] ); $aRecord[$key] = $_POST[$key]; } );
				$aRecord['customers_newsletter'] = isset($_POST['customers_newsletter']) && $_POST['customers_newsletter'] == '1' ? '1' : '0';

				// Comprobamos que nos hayan enviado un nombre
				if (array_key_exists( 'subscribers_firstname', $_POST ) && $_POST['subscribers_firstname'] == '' || !array_key_exists( 'subscribers_firstname', $_POST )) {
                    $aMessageError['subscribers_firstname'] = $messageStack->show( [ 'text' => NEWSLETTERS_SUBSCRIBERS_ERROR_NAME, 'class' => 'error' ] );
                }

				// Comprobamos que nos hayan enviado un apellido
				if (array_key_exists( 'subscribers_lastname', $_POST ) && $_POST['subscribers_lastname'] == '' || !array_key_exists( 'subscribers_lastname', $_POST )) {
                    $aMessageError['subscribers_lastname'] = $messageStack->show( [ 'text' => NEWSLETTERS_SUBSCRIBERS_ERROR_SURNAME, 'class' => 'error' ] );
                }

				// Comprobamos que nos hayan enviado un apellido
				if (array_key_exists( 'subscribers_email_address', $_POST ) && $_POST['subscribers_email_address'] == '' || !array_key_exists( 'subscribers_email_address', $_POST )) {
                    $aMessageError['subscribers_email_address'] = $messageStack->show( [ 'text' => NEWSLETTERS_SUBSCRIBERS_ERROR_EMAIL, 'class' => 'error' ] );
                }

				// Si no existe errores actualizamos/insertamos
				if( count( $aMessageError ) == 0 )
				{
					$aSql = [
						'subscribers_firstname' => $_POST['subscribers_firstname'],
						'subscribers_lastname' => $_POST['subscribers_lastname'],
						'subscribers_email_address' => $_POST['subscribers_email_address'],
						'customers_newsletter' => isset($_POST['customers_newsletter']) && $_POST['customers_newsletter'] == '1' ? 1 : 0
					];

					if ($sGetId != false) {
                        tep_db_perform( 'subscribers', $aSql, 'update', 'subscribers_id = "' . (int)$sGetId . '"' );
                    } else {
                        tep_db_perform( 'subscribers', $aSql );
                    }

					// Mensaje
					$messageStack->addSession('success', ($sGetId != false ? NEWSLETTERS_SUBSCRIBERS_UPDATE_SUCCESS : NEWSLETTERS_SUBSCRIBERS_INSERT_SUCCESS), 'success');

					// Redireccionamos
					tep_redirect( tep_href_link(  $sUrlPage ) );
				}
			}

			// Template
			$sHtmlModule = includeTemplate( $sPathTemplate . '/crud.php' );
		break;

		case 'status':
			// Variables
			$sGetId = (int)tep_db_prepare_input( $_GET['id'] ?? 0 );
			$sGetStatus = isset($_GET['flag']) && $_GET['flag'] == 'true' ? '1' : '0';

			// Si no tenemos ID
			if ($sGetId == 0 || !in_array( $sGetStatus, [ '0', '1' ] )) {
                exit();
            }

			// Modificamos
			tep_db_perform( 'subscribers', [ 'customers_newsletter' => $sGetStatus ], 'update', 'subscribers_id = "' . (int)$sGetId . '"' );

			// Detenemos
			exit();

		case 'export_excel':
			exportSubscribersToExcel();
			break;

		default:
			// Variables
			$sSubtitle = NEWSLETTERS_SUBSCRIBERS_SUBTITLE;

			$aButtons = [
				[
					'title' => 'Exportar a Excel',
					'href' => tep_href_link($sUrlPage, 'action=export_excel'),
					'icon' => 'fa-file-excel',
					'anchor_class' => 'verde'
				]
			];


			// Html para el boton masivo
			$sHtmlActionMasivo = '<label class="column afluid">' . NEWSLETTERS_SUBSCRIBERS_TEXT_APPLY_ACTION . ':&nbsp;&nbsp;</label>
			<div class="column afluid"><div class="drop masv xfselect">
				<div>Acciones</div>
				<ul class="down drch">
					<li><a data-question="' . NEWSLETTERS_SUBSCRIBERS_TEXT_DELETE_RECORDS_CONFIRM . '" data-action="' . tep_href_link( $sUrlPage, 'action=delete' ) . '" href="javascript:void(0);" class="hv"><i class="fa fa-trash"></i>' . NEWSLETTERS_SUBSCRIBERS_TEXT_DELETE_RECORDS . '</a></li>
				</ul>
			</div></div>&nbsp; - &nbsp;';

			// Variables
			$aFiler = [ 'search' => '' ];
			$aAuxFilter = array_key_exists( 'filter', $_GET ) && is_array($_GET['filter']) ? $_GET['filter'] : (array_key_exists( 'filter', $_POST ) && is_array($_POST['filter']) ? $_POST['filter'] : []);
			$sWhere = '';

			// Limpiamos variables get filter
			array_walk( $aFiler, function( $value, $key){ global $aFiler, $aAuxFilter; $aFiler[$key] = tep_db_prepare_input( array_key_exists( $key, $aAuxFilter ) ? $aAuxFilter[$key] : $aFiler[$key] ); } );

			// Where
			if ($aFiler['search'] !== '') {
                $sWhere = 'where ';
            }

			if ($aFiler['search'] !== '') {
                $sWhere .= ( $sWhere !== 'where ' ? ' and' : '') . ' (LOWER(sub.subscribers_lastname) LIKE "%' . strtolower( $aFiler['search'] ) . '%" OR LOWER(sub.subscribers_firstname) LIKE "%' . strtolower( $aFiler['search'] ) . '%" OR LOWER(sub.subscribers_email_address) LIKE "%' . strtolower( $aFiler['search'] ) . '%")';
            }



			// Order by
			if ($sGetOrderby == 'subscribers_lastname') {
                $sOrderby = 'sub.subscribers_lastname ' . $sGetSort;
            } elseif ($sGetOrderby == 'subscribers_firstname') {
                $sOrderby = 'sub.subscribers_firstname ' . $sGetSort;
            } elseif ($sGetOrderby == 'subscribers_email_address') {
                $sOrderby = 'sub.subscribers_email_address ' . $sGetSort;
            } elseif ($sGetOrderby == 'customers_newsletter') {
                $sOrderby = 'sub.customers_newsletter ' . $sGetSort;
            } else {
                $sOrderby = 'sub.subscribers_id DESC';
            }

			// Sql
			$sSql = 'SELECT sub.subscribers_id, sub.subscribers_lastname, sub.subscribers_firstname, sub.subscribers_email_address, sub.customers_newsletter
					 FROM subscribers sub
					 ' . $sWhere . ' ORDER BY ' . $sOrderby;

			// Le quitamos los tabuladores y saltos de linea para que splitpageesult funcione con el SQL
			$sSql = preg_replace( '/[\r\n\t]+/', ' ', $sSql );

			// Sql para el count
			$sSqlCount = 'SELECT COUNT(table_aux.subscribers_id) as total FROM (' . $sSql . ') as table_aux';

			// Datos y paginacion
			$aRowsSplit = new splitPageResults( $sGetPage, MAX_DISPLAY_SEARCH_RESULTS, $sSql, $nAux, $sSqlCount );
			$aRows= tep_db_query( $sSql );

			// Modulo
			$sHtmlModule = includeTemplate( $sPathTemplate . '/default.php' );
		break;
	}

	// Pintamos
	echo includeTemplate( $sPathTemplate . '/base.php' );
?>
