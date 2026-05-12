<?php
// Tools
use util\tools as tools;

// Acciones
switch( $sPostAction )
{
	case 'group_delete':
		// Variables
		$aGetId = isset($_GET['id']) ? tep_db_prepare_input($_GET['id']) : null;
		$aPostId = isset($_POST['id']) ? tep_db_prepare_input($_POST['id']) : null;
		$sIds = '';

		// Si nos envian por get creamos el array
		if ($aGetId != '') {
            $aPostId = [ $aGetId ];
        }

		// Recorremos los id
		foreach( $aPostId as $sId )
			$sIds .= $sId . ',';

		// Si tenemos id eliminamos
		if( $sIds !== '' ){
			tep_db_query( 'DELETE FROM configuration_group where configuration_group_id in(' . substr( $sIds, 0, -1 ) . ')' );
		}

		// Redireccionamos
		$messageStack->addSession( 'success', CONFIGURATION_DELETE_SUCCESS, 'success' );
		tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;

	case 'group_crud':
		// Variables
		$sGetId = array_key_exists( 'id', $_POST ) ? tep_db_input( $_POST['id'] ) : (array_key_exists( 'id', $_GET ) ? tep_db_input( $_GET['id'] ) : false);
		$aMessageError = [];
		$sSubtitle = ($sGetId != '' ? CONFIGURATION_TEXT_EDIT : CONFIGURATION_TEXT_ADD) . ' ' . CONFIGURATION_TEXT_CONFIGURATION_GROUP;
		$aButtons = [
			[ 'title' => TEXT_BACK, 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-arrow-left' ],
			[ 'title' => TEXT_SAVE, 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' ]
		];
		$aRecord = [
			'configuration_group_title' => '',
			'configuration_group_description' => '',
			'sort_order' => ''
		];

		// Si estamos editando
		if( $sGetId != false )
		{
			// Obtenemos el registro
			$aRecord = pharaonix_queryOne( 'SELECT * FROM configuration_group WHERE configuration_group_id = "' . (int)$sGetId . '"' );

			// Si no existe
			if( $aRecord->num_rows == 0 )
			{
				$messageStack->addSession( 'success', CONFIGURATION_RECORD_NOT_EXISTS, 'error' );
				tep_redirect( tep_href_link(  $sUrlPage, 'action=group' ) );
			}

			// Registro
			$aRecord = $aRecord->records;
		}

		// Insertar o actualizar
		if( $_SERVER['REQUEST_METHOD'] === 'POST' )
		{
			// Pasamos todos los post por tep_db_prepare_input
			array_walk( $_POST, function( $value, $key){ global $_POST, $aRecord; $_POST[$key] = tep_db_prepare_input( $_POST[$key] ); $aRecord[$key] = $_POST[$key]; } );
			$aRecord['active'] = isset($_POST['active']) && $_POST['active'] == '1' ? '1' : '0';

			// Comprobamos que nos hayan enviado
			if (array_key_exists( 'configuration_group_title', $_POST ) && $_POST['configuration_group_title'] == '' || !array_key_exists( 'configuration_group_title', $_POST )) {
                $aMessageError['configuration_group_title'] = $messageStack->show( [ 'text' => CONFIGURATION_ERROR_TITLE, 'class' => 'error' ] );
            }

			// Comprobamos que nos hayan enviado
			if (array_key_exists( 'configuration_group_description', $_POST ) && $_POST['configuration_group_description'] == '' || !array_key_exists( 'configuration_group_description', $_POST )) {
                $aMessageError['configuration_group_description'] = $messageStack->show( [ 'text' => CONFIGURATION_ERROR_DESCRIPTION, 'class' => 'error' ] );
            }

			// Comprobamos que nos hayan enviado
			if (array_key_exists( 'sort_order', $_POST ) && $_POST['sort_order'] == '' || !array_key_exists( 'sort_order', $_POST )) {
                $aMessageError['sort_order'] = $messageStack->show( [ 'text' => CONFIGURATION_ERROR_ORDER, 'class' => 'error' ] );
            }

			// Si no existe errores actualizamos/insertamos
			if( count( $aMessageError ) == 0 )
			{
				$aSql = [
					'configuration_group_title' => $_POST['configuration_group_title'],
					'configuration_group_description' => $_POST['configuration_group_description'],
					'sort_order' => $_POST['sort_order'],
					'visible' => 1
				];

				if ($sGetId != false) {
                    tep_db_perform( 'configuration_group', $aSql, 'update', 'configuration_group_id = "' . (int)$sGetId . '"' );
                } else {
                    tep_db_perform( 'configuration_group', $aSql );
                }

				// Mensaje
				$messageStack->addSession( 'success', ($sGetId != false ? CONFIGURATION_EDIT_SUCCESS : CONFIGURATION_ADD_SUCCESS), 'success' );

				// Redireccionamos
				tep_redirect( tep_href_link(  $sUrlPage . '?action=group' ) );
			}
		}

		// Template
		$sHtmlModule = includeTemplate( $sPathTemplate . '/group_crud.php' );
		break;
	default:
	case 'group':
		// Variables
		$sSubtitle = CONFIGURATION_SUBTITLE;
		$aButtons = [
			[ 'title' => CONFIGURATION_MENU_RECORD, 'href' => tep_href_link( $sUrlPage, 'action=history' ), 'icon' => 'fa-history', 'anchor_class' => '' ],
			[ 'title' => CONFIGURATION_MENU_ADD, 'href' => tep_href_link( $sUrlPage, 'action=group_crud' ), 'icon' => 'fa-plus', 'anchor_class' => 'verde' ]
		];

		// Html para el boton masivo
		$sHtmlActionMasivo = '<label class="column afluid">' . CONFIGURATION_APPLY_ACTION . ':&nbsp;&nbsp;</label>
			<div class="column afluid"><div class="drop masv xfselect">
				<div>' . CONFIGURATION_TEXT_ACTIONS . '</div>
				<ul class="down drch">
					<li><a data-question="' . CONFIGURATION_DELETE_RECORDS_CONFIRM . '" data-error="' . CONFIGURATION_MASSIVE_ERROR . '" data-action="' . tep_href_link( $sUrlPage, 'action=group_delete' ) . '" href="javascript:void(0);" class="hv"><i class="fa fa-trash"></i>' . CONFIGURATION_DELETE_RECORDS . '</a></li>
				</ul>
			</div></div>&nbsp; - &nbsp;';

		// Variables
		$aFiler = [ 'search' => '' ];
		$aAuxFilter = array_key_exists( 'filter', $_GET ) && is_array($_GET['filter']) ? $_GET['filter'] : (array_key_exists( 'filter', $_POST ) && is_array($_POST['filter']) ? $_POST['filter'] : []);
		$sWhere = '';

		// Limpiamos variables get filter
		array_walk( $aFiler, function( $value, $key){ global $aFiler, $aAuxFilter; $aFiler[$key] = tep_db_prepare_input( array_key_exists( $key, $aAuxFilter ) ? $aAuxFilter[$key] : $aFiler[$key] ); } );

		if( $aFiler['search'] !== '' ){
			// Vemos si es un key
			$configureKey = pharaonix_queryOne('SELECT configuration_group_id FROM configuration WHERE configuration_key = "' . $aFiler['search'] . '"');

			if ($configureKey->num_rows == 1) {
				tep_redirect(tep_href_link('configuration.php', 'action=options&id=' . $configureKey->records['configuration_group_id']));
			}

			$sWhere .= ' and (LOWER(configuration_group_title) LIKE "%' . strtolower( $aFiler['search'] ) . '%") or (LOWER(configuration_group_description) LIKE "%' . strtolower( $aFiler['search'] ) . '%")';
		}

		// Order by
		if ($sGetOrderby == 'configuration_group_title') {
            $sOrderby = 'configuration_group_title ' . $sGetSort;
        } elseif ($sGetOrderby == 'configuration_group_description') {
            $sOrderby = 'configuration_group_description ' . $sGetSort;
        } elseif ($sGetOrderby == 'sort_order') {
            $sOrderby = 'sort_order ' . $sGetSort;
        } else {
            $sOrderby = '`sort_order` ASC';
        }

		// Sql
		$sSql = 'SELECT configuration_group_id, configuration_group_title, configuration_group_description, sort_order
					 FROM configuration_group
					 WHERE visible = 1
					 ' . $sWhere . ' ORDER BY ' . $sOrderby;

		// Le quitamos los tabuladores y saltos de linea para que splitpageesult funcione con el SQL
		$sSql = preg_replace( '/[\r\n\t]+/', ' ', $sSql );

		// Sql para el count
		$sSqlCount = 'SELECT COUNT(table_aux.configuration_group_id) as total FROM (' . $sSql . ') as table_aux';

		// Datos y paginacion
		$aRowsSplit = new splitPageResults( $sGetPage, MAX_DISPLAY_SEARCH_RESULTS, $sSql, $nAux, $sSqlCount );
		$aRows= tep_db_query( $sSql );

		// Modulo
		$sHtmlModule = includeTemplate( $sPathTemplate . '/group.php' );
		break;
}
?>
