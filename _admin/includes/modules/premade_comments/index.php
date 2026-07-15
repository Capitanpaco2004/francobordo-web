<?php
	// Tools
	use util\tools as tools;
	use util\date as date;

	// Incluimos el application_top
	require_once( 'includes/application_top.php' );

	// Mostrar errores
	// ini_set('display_errors', 1); // OFF 2026-07-14: no pisar display_errors=Off del admin (avisos impresos rompen AJAX/redirects)
	error_reporting(E_ERROR | E_WARNING | E_PARSE);

	// Function tep_href_link_pc para saber si viene orders_id
	function tep_href_link_pc($page,  $parameters = '')
	{
		// Variables
		$ordersId = $_GET['orders_id'] ?? false;

		if($ordersId != false){
			$parameters = 'orders_id=' . $ordersId . '&' . $parameters;
		}

		return tep_href_link($page, $parameters);
	}

	// Variables
	$sUrlPage =  'premade_comments.php';
	$sPathModule = 'includes/modules/premade_comments';
	$sPathTemplate = $sPathModule . '/template';
	$sTitle = PREMADE_COMMENTS_TITLE;
	$sSubtitle = '';
	$aButtons = [];
	$sPostAction = array_key_exists( 'action', $_POST ) ? tep_db_input( $_POST['action'] ) : (array_key_exists( 'action', $_GET ) ? tep_db_input( $_GET['action'] ) : false);
	$sGetPage = tep_db_prepare_input( $_GET['page'] ?? '' );
	$sGetOrderby = tep_db_prepare_input( $_GET['orderby'] ?? '' );
	$sGetSort = tep_db_prepare_input( $_GET['sort'] ?? '' );

	# Messagestack estilo
	$messageStack->style = 'solenopsis';

	// Acciones
	switch(true)
	{
		case ($sPostAction == 'delete'):
			// Variables
			if (isset($_GET['id'])) {
				$aGetId = tep_db_prepare_input($_GET['id']);
			}

			if (isset($_POST['id'])) {
				$aPostId = tep_db_prepare_input($_POST['id']);
			}

			$sIds = '';

			// Si nos envian por get creamos el array
			if ($aGetId != '') {
                $aPostId = [ $aGetId ];
            }

			// Recorremos los id
			foreach( $aPostId as $sId )
				$sIds .= $sId . ',';

			// Si tenemos id eliminamos
			if ($sIds !== '') {
                tep_db_query( 'DELETE FROM orders_premade_comments where id in(' . substr( $sIds, 0, -1 ) . ')' );
            }

			// Redireccionamos
			$messageStack->addSession( 'success', PREMADE_COMMENTS_DELETE_SUCCESS, 'success' );
			tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;

		case ($sPostAction == 'crud'):
			// Variables
			$sGetId = array_key_exists( 'id', $_POST ) ? tep_db_input( $_POST['id'] ) : (array_key_exists( 'id', $_GET ) ? tep_db_input( $_GET['id'] ) : false);
			$aMessageError = [];
			$sSubtitle = ($sGetId != '' ? TEXT_EDIT : TEXT_ADD) . ' ' . strtolower(PREMADE_COMMENTS_PREMADE_COMMENTS);
			$aButtons = [
				[ 'title' => TEXT_BACK, 'href' => tep_href_link_pc( $sUrlPage ), 'icon' => 'fa-arrow-left' ],
				[ 'title' => TEXT_SAVE, 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' ]
			];
			$aRecord = [];

			// Si estamos editando
			if( $sGetId != false )
			{
				// Obtenemos el registro
				$aRecord = pharaonix_queryOne( 'SELECT * FROM orders_premade_comments WHERE id = "' . (int)$sGetId . '"' );

				// Si no existe
				if( $aRecord->num_rows == 0 )
				{
					$messageStack->addSession( 'success', PREMADE_COMMENTS_RECORD_NO_EXISTS, 'error' );
					tep_redirect( tep_href_link_pc(  $sUrlPage, 'action=redirect' ) );
				}

				// Registro
				$aRecord = $aRecord->records;
			}

			// Insertar o actualizar
			if( $_SERVER['REQUEST_METHOD'] === 'POST' )
			{
				// Pasamos todos los post por tep_db_prepare_input
				array_walk( $_POST, function( $value, $key){ global $_POST, $aRecord; $_POST[$key] = tep_db_prepare_input( $_POST[$key] ); $aRecord[$key] = $_POST[$key]; } );
				$aRecord['status'] = isset($_POST['status']) && $_POST['status'] == '1' ? '1' : '0';

				// Comprobamos que nos hayan enviado un titulo
				if (array_key_exists( 'title', $_POST ) && $_POST['title'] == '' || !array_key_exists( 'title', $_POST )) {
                    $aMessageError['title'] = $messageStack->show( [ 'text' => PREMADE_COMMENTS_TITLE_ERROR, 'class' => 'error' ] );
                }

				// Comprobamos que nos hayan enviado un texto
				if (array_key_exists( 'text', $_POST ) && $_POST['text'] == '' || !array_key_exists( 'text', $_POST )) {
                    $aMessageError['text'] = $messageStack->show( [ 'text' => PREMADE_COMMENTS_TEXT_ERROR, 'class' => 'error' ] );
                }

				// Si no existe errores actualizamos/insertamos
				if( count( $aMessageError ) == 0 )
				{
					$aSql = [
						'title' => $_POST['title'],
						'text' => $_POST['text']
					];

					if ($sGetId != false) {
                        tep_db_perform( 'orders_premade_comments', $aSql, 'update', 'id = "' . (int)$sGetId . '"' );
                    } else {
                        tep_db_perform( 'orders_premade_comments', $aSql );
                    }

					// Mensaje
					$messageStack->addSession( 'success', ($sGetId != false ? PREMADE_COMMENTS_EDIT_SUCCESS : PREMADE_COMMENTS_INSERT_SUCCESS), 'success' );

					// Redireccionamos
					tep_redirect( tep_href_link_pc( $sUrlPage ) );
				}
			}

			// Template
			$sHtmlModule = includeTemplate( $sPathTemplate . '/crud.php' );
		break;

		default:
			// Variables
			$sSubtitle = PREMADE_COMMENTS_SUBTITLE;
			$aButtons = [];

			// Si tenemos orders_id añadimos volver
			if(isset($_GET['orders_id'])){
				$aButtons[] = [ 'title' => TEXT_BACK, 'href' => tep_href_link( 'orders.php', 'action=edit&oID=' . (int)$_GET['orders_id'] ), 'icon' => 'fa-arrow-left' ];
			}

			// Añadimos boton de crear
			$aButtons[] = [ 'title' => TEXT_ADD, 'href' => tep_href_link_pc( $sUrlPage, 'action=crud' ), 'icon' => 'fa-plus', 'anchor_class' => 'verde' ];

			// Html para el boton masivo
			$sHtmlActionMasivo = '<label class="column afluid">' . PREMADE_COMMENTS_TEXT_APPLY_ACTION . ':&nbsp;&nbsp;</label>
			<div class="column afluid"><div class="drop masv xfselect">
				<div>' . PREMADE_COMMENTS_TEXT_ACTIONS . '</div>
				<ul class="down drch">
					<li><a data-question="' . PREMADE_COMMENTS_TEXT_DELETE_RECORDS_CONFIRM . '" data-error="' . PREMADE_COMMENTS_DELETE_ERROR . '" data-action="' . tep_href_link_pc( $sUrlPage, 'action=delete' ) . '" href="javascript:void(0);" class="hv"><i class="fa fa-trash"></i>' . PREMADE_COMMENTS_TEXT_DELETE_RECORDS . '</a></li>
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
                $sWhere .= ( $sWhere !== 'where ' ? ' and' : '') . ' (LOWER(title) LIKE "%' . strtolower( $aFiler['search'] ) . '%" OR LOWER(text) LIKE "%' . strtolower( $aFiler['search'] ) . '%")';
            }

			// Order by
			$sOrderby = $sGetOrderby == 'title' ? 'title ' . $sGetSort : 'title ASC';

			// Sql
			$sSql = 'SELECT id, title
					 FROM orders_premade_comments
					 ' . $sWhere . ' ORDER BY ' . $sOrderby;

			// Le quitamos los tabuladores y saltos de linea para que splitpageesult funcione con el SQL
			$sSql = preg_replace( '/[\r\n\t]+/', ' ', $sSql );

			// Sql para el count
			$sSqlCount = 'SELECT COUNT(table_aux.id) as total FROM (' . $sSql . ') as table_aux';


			// Datos y paginacion
			$aRowsSplit = new splitPageResults( $sGetPage, MAX_DISPLAY_SEARCH_RESULTS, $sSql, $nAux, $sSqlCount );
			$aRows= tep_db_query( $sSql );

			// Modulo
			$sHtmlModule = includeTemplate( $sPathTemplate . '/index.php' );
		break;
	}

	// Pintamos
	echo includeTemplate( $sPathTemplate . '/base.php' );
?>
