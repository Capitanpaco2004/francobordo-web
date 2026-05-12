<?php
use submodules_all;
use submodule_box_class;
use submodules_class;

$sTitle = ADMIN_MEMBERS_HEADING_TITLE_GROUPS;


	switch( $sPostAction ){
		case "submodules_installs":
			tep_db_query("CREATE TABLE `admin_files_submodules` (
				  `admin_files_submodule_id` int(11) NOT NULL AUTO_INCREMENT,
				  `admin_files_id` int(11) NOT NULL,
				  `admin_files_name` varchar(64) NOT NULL,
				  `admin_groups_id` set('1','2','3','4','5','6','7','8','9','10') NOT NULL,
				  PRIMARY KEY (`admin_files_submodule_id`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;");
			\util\tools::insertConfiguration("Activar ficheros por submodulos","SUBMODULES_FILES_SECURITY", "true","Indica si esta activado o no el limite de acceso a ficheros por submodulos",0);
			\util\tools::createCacheFile();
			break;
		case "submodules_delete":
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
				tep_db_query('DELETE FROM admin_files_submodules WHERE admin_files_submodule_id IN(' . substr($sIds, 0, -1) . ')');
			}

			$messageStack->addSession( 'success', ADMIN_MEMBERS_MEMBER_DELETE_SUCCESS, 'success' );
			tep_redirect( tep_href_link( $sUrlPage, 'action=submodules_list' ) );
			break;

		case "submodules_add":
			$messageStack->add("Si has llegado aqui y no sabes que estas haciendo, por favor no toques nada", "warning");
			$sGetId = array_key_exists( 'id', $_POST ) ? tep_db_input( $_POST['id'] ) : (array_key_exists( 'id', $_GET ) ? tep_db_input( $_GET['id'] ) : false);

			$sSubtitle = ($sGetId != '' ? ADMIN_MEMBERS_TEXT_EDITED : ADMIN_MEMBERS_TEXT_ADD) . ' ' . ADMIN_MEMBERS_SUBMODULE_TITLE_ADD_EDIT_MEMBER;

			$aMessageError = [];
			$aButtons = [
				[ 'title' => TEXT_BACK, 'href' => tep_href_link( $sUrlPage, 'action=submodules_list' ), 'icon' => 'fa-arrow-left' ],
				[ 'title' => TEXT_SAVE, 'icon' => 'fa-save fa-floppy-o', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' ]
			];

			$aRecord = [];
			$adminFileQuery = tep_db_query("SELECT admin_files_id, admin_files_name FROM admin_files");
			$dropdownArray = [];
			while ($adminFile = tep_db_fetch_array($adminFileQuery)){
				$dropdownArray[] = ["text"=>$adminFile['admin_files_name'], "id"=>$adminFile['admin_files_id']];
			}


			// Si estamos editando
			if( $sGetId != false ) {
				// Obtenemos el registro
				$aRecord = pharaonix_queryOne( 'SELECT * FROM admin_files_submodules WHERE admin_files_submodule_id = "' . (int)$sGetId . '"' );

				// Si no existe
				if( $aRecord->num_rows == 0 )
				{
					$messageStack->addSession( 'success', ADMIN_MEMBERS_MEMBER_NO_EXISTS, 'error' );
					tep_redirect( tep_href_link(  $sUrlPage ) );
				}


				// Registro
				$aRecord = $aRecord->records;
				$dropdownFiles = tep_draw_pull_down_menu("admin_file",$dropdownArray, $aRecord['admin_files_id']);
			}else{
				$dropdownFiles = tep_draw_pull_down_menu("admin_file",$dropdownArray);
			}

			if ($_SERVER['REQUEST_METHOD'] === 'POST') {
				if($_POST['submodule_arg'] == ""){
					$messageStack->addSession("error", "Es necesario añadir un argumento de modulo","error");
					tep_redirect(tep_href_link( $sUrlPage, 'action=submodules_add'));
				}
				$file = tep_db_prepare_input($_POST['admin_file']);
				$arg = tep_db_prepare_input($_POST['submodule_arg']);
				if( $sGetId != false ) {
					tep_db_query("UPDATE admin_files_submodules SET admin_files_id={$file}, admin_files_name = '{$arg}' WHERE admin_files_submodule_id = {$sGetId}");
					$messageStack->addSession( 'success', ADMIN_MEMBERS_GROUP_EDIT_SUCCESS, 'success' );
				} else {
					tep_db_query("INSERT INTO admin_files_submodules (admin_files_id, admin_files_name) VALUES ({$file}, '{$arg}')");
					$messageStack->addSession( 'success', ADMIN_MEMBERS_GROUP_ADD_SUCCESS, 'success' );
				}

				tep_redirect(tep_href_link( $sUrlPage, 'action=submodules_list' ));

			}

			// Obtener grupos de administrador
			$groups_array = [['id' => '0', 'text' => TEXT_NONE]];
			$groups_query = tep_db_query("SELECT admin_groups_id, admin_groups_name FROM admin_groups");
			while ($groups = tep_db_fetch_array($groups_query)) {
				$groups_array[] = ['id' => $groups['admin_groups_id'], 'text' => $groups['admin_groups_name']];
			}
			// Modulo
			$sHtmlModule = includeTemplate( $sPathTemplate . '/submodules/add.php' );
		break;
		case "submodules_list":
			$sSubtitle = ADMIN_MEMBERS_SUBMODULE_LIST_SUBTITLE;
			$aButtons = [
				[ 'title' => ADMIN_MEMBERS_TEXT_INFO_HEADING_GROUPS_LIST, 'href' => tep_href_link( $sUrlPage, 'action=groups' ), 'icon' => 'fa-user-group fa-users' ],
				[ 'title' => ADMIN_MEMBERS_SUBMODULE_LIST_NEW, 'href' => tep_href_link( $sUrlPage, 'action=submodules_add' ), 'icon' => 'fa-plus' ]
			];

			$sHtmlActionMasivo = '<label class="column afluid">' . ADMIN_MEMBERS_TEXT_APPLY_ACTION . ':&nbsp;&nbsp;</label>
			<div class="column afluid"><div class="drop masv xfselect">
				<div>' . ADMIN_MEMBERS_TABLE_ACTIONS . '</div>
				<ul class="down drch">
					<li><a data-question="' . ADMIN_MEMBERS_TEXT_DELETES_CONFIRM . '" data-error="' . ADMIN_MEMBERS_TEXT_DELETE_ERROR . '" data-action="' . tep_href_link( $sUrlPage, 'action=submodules_delete' ) . '" href="javascript:void(0);" class="hv"><i class="fa fa-trash"></i>' . ADMIN_MEMBERS_TEXT_DELETES . '</a></li>
				</ul>
			</div></div>&nbsp; - &nbsp;';

			// Filtros
			$aFilter = (array_key_exists( 'filter', $_POST ) && is_array($_POST['filter']) ? $_POST['filter'] : []);
			$sWhere = '';

			// Limpiamos variables get filter
			array_walk( $aFilter, function( $value, $key){ global $aFilter; $aFilter[$key] = tep_db_prepare_input( $aFilter[$key] ); } );

			if( $aFilter['search'] != '' ) {
				$sWhere .= 'WHERE (LOWER(admin_files_name) LIKE "%' . strtolower((string) $aFilter['search']) . '%")';
			}

			// Sql
			$sSql = 'SELECT admin_files_submodules.*, admin_files.admin_files_name as file
				FROM admin_files_submodules
				LEFT JOIN admin_files
				    ON admin_files_submodules.admin_files_id = admin_files.admin_files_id
				' . $sWhere ;

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
			$sHtmlModule = includeTemplate( $sPathTemplate . '/submodules/list.php' );
		break;
		case "submodules":
		default:
			$sGetId = array_key_exists( 'id', $_POST ) ? tep_db_input( $_POST['id'] ) : (array_key_exists( 'id', $_GET ) ? tep_db_input( $_GET['id'] ) : false);

			$sSubtitle = ADMIN_MEMBERS_SUBMODULE_TEXT_EDITED;
			$messageStack->add( "Para que aparezcan los submodulos de un fichero, el usuario tiene que tener primero acceso al fichero padre","warning");

			$aMessageError = [];
			$aButtons = [
				[ 'title' => TEXT_BACK, 'href' => tep_href_link($sUrlPage, 'action=groups_crud&id=' . $sGetId), 'icon' => 'fa-arrow-left' ],
				[ 'title' => TEXT_SAVE, 'icon' => 'fa-save fa-floppy-o', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' ]
			];

			$aRecord = [];

			// Si estamos editando
			if( $sGetId != false ) {
				// Obtenemos el registro
				$query = tep_db_query( "SELECT afs.admin_files_submodule_id, afs.admin_files_id, afs.admin_files_name, afs.admin_groups_id, af.admin_files_name as parent_name
					FROM admin_files_submodules afs INNER JOIN admin_files af ON af.admin_files_id = afs.admin_files_id WHERE FIND_IN_SET({$sGetId},af.admin_groups_id)>0"  );

				// Si no existe
				if( tep_db_num_rows($query) == 0)
				{
					$messageStack->addSession( 'error', ADMIN_MEMBERS_SUBMODULE_NO_SUBMODULES_EXIST, 'error' );
					tep_redirect(tep_href_link($sUrlPage, 'action=groups_crud&id=' . $sGetId));
				}

			}

			$boxes = new submodules_all();

			while ($record = tep_db_fetch_array($query)){
				try {
					$boxes->addBox($record);
				}catch (InvalidArgumentException){
					continue;
				}

			}

			if ($_SERVER['REQUEST_METHOD'] === 'POST') {

				$selectedBoxes = [];
				foreach ( $_POST['boxes_to'] as $box){
					$temp = explode("-", (string) $box);
					$selectedBoxes[$temp[0]][] = $temp[1];
				}

				$boxes->update_all($selectedBoxes);
				tep_redirect( tep_href_link(  $sUrlPage, 'action=groups_crud&id=' . $sGetId ) );
			}




			$aJs = [ $sPathModule . '/js/default.js?v=1.2' ];
			$aStyle = [ $sPathModule . '/css/admin_members.css' ];

			// Modulo
			$sHtmlModule = includeTemplate( $sPathTemplate . '/submodules/crud.php');
		break;
	}
