<?php

	$sTitle = ADMIN_MEMBERS_HEADING_TITLE_GROUPS;

	// Acciones groups
	switch( $sPostAction ) {
		case "groups_delete":
			$aGetId = tep_db_prepare_input( $_GET['id'] );
			$aPostId = tep_db_prepare_input( $_POST['id'] );
			$sIds = '';

			if( $aGetId != '' ) {
				$aPostId = [$aGetId];
			}

			foreach( $aPostId as $sId ) {
				if($sId != '1') {
					tep_db_query("DELETE FROM admin_groups WHERE admin_groups_id = '" . $sId . "'");
					tep_db_query("DELETE FROM admin WHERE admin_groups_id = '" . $sId . "'");
				}
			}

			$messageStack->addSession( 'success', ADMIN_MEMBERS_MEMBER_DELETE_SUCCESS, 'success' );
			tep_redirect( tep_href_link(  $sUrlPage, 'action=groups' ) );
			break;

		case "groups_crud":
			$sGetId = array_key_exists( 'id', $_POST ) ? tep_db_input( $_POST['id'] ) : (array_key_exists( 'id', $_GET ) ? tep_db_input( $_GET['id'] ) : false);

			$sSubtitle = ($sGetId != '' ? ADMIN_MEMBERS_TEXT_EDITED : ADMIN_MEMBERS_TEXT_ADD) . ' ' . ADMIN_MEMBERS_TITLE_ADD_EDIT_GROUP;

			$aMessageError = [];
			$aButtons = [
				[ 'title' => TEXT_BACK, 'href' => tep_href_link( $sUrlPage, 'action=groups' ), 'icon' => 'fa-arrow-left' ],
				[ 'title' => "Submodulos", 'href' => tep_href_link( $sUrlPage, 'action=submodules&id='.$sGetId ), 'icon' => 'fa-layer-group' ],
				[ 'title' => TEXT_SAVE, 'icon' => 'fa-save fa-floppy-o', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' ]
			];
			if(defined("SUBMODULES_FILES_SECURITY") && SUBMODULES_FILES_SECURITY != "true"){
				unset($aButtons[1]);
			}
			$aRecord = [];

			// Si estamos editando
			if( $sGetId != false ) {
				// Obtenemos el registro
				$aRecord = pharaonix_queryOne( 'SELECT * FROM admin_groups WHERE admin_groups_id = "' . (int)$sGetId . '"' );

				// Si no existe
				if( $aRecord->num_rows == 0 )
				{
					$messageStack->addSession( 'success', ADMIN_MEMBERS_MEMBER_NO_EXISTS, 'error' );
					tep_redirect( tep_href_link(  $sUrlPage ) );
				}

				// Registro
				$aRecord = $aRecord->records;
			}

			if ($_SERVER['REQUEST_METHOD'] === 'POST') {
				$admin_groups_name = ucwords(strtolower(tep_db_prepare_input($_POST['admin_groups_name'])));
				$name_replace = preg_replace("/ /", "%", $admin_groups_name);

				// Comprobar que el nombre no está vacío o de menos de 5 caracteres
				if (($admin_groups_name === '' || NULL) || (strlen($admin_groups_name) <= 2) ) {
					$messageStack->addSession( 'success', ADMIN_MEMBERS_TEXT_ERROR_GROUP_INPUT, 'error' );

					if( $sGetId != false ) {
						tep_redirect(tep_href_link($sUrlPage, 'action=groups_crud&id=' . $sGetId));
					} else {
						tep_redirect(tep_href_link($sUrlPage, 'action=groups_crud'));
					}
				} else {
					// Comprobar que no existe ningún grupo con el mismo nombre
					if ($sGetId != false) {
                        $check_groups_name_query = tep_db_query("SELECT admin_groups_name AS group_name_new FROM admin_groups WHERE admin_groups_id != " . $sGetId . " AND admin_groups_name = '" . $name_replace . "'");
                    } else {
                        $check_groups_name_query = tep_db_query("SELECT admin_groups_name AS group_name_new FROM admin_groups WHERE admin_groups_name = '" . $name_replace . "'");
                    }
					$check_duplicate = tep_db_num_rows($check_groups_name_query);

					if ($check_duplicate > 0) {
						$messageStack->addSession( 'success', ADMIN_MEMBERS_GROUP_ALREADY_EXISTS, 'error' );

						if ($sGetId != false) {
                            tep_redirect( tep_href_link(  $sUrlPage, 'action=groups_crud&id=' . $sGetId ) );
                        } else {
                            tep_redirect( tep_href_link(  $sUrlPage, 'action=groups_crud' ) );
                        }
					} else {
						$sql_data_array = ['admin_groups_name' => $admin_groups_name];

						if( $sGetId != false ) {
							$messageStack->addSession( 'success', ADMIN_MEMBERS_GROUP_EDIT_SUCCESS, 'success' );
							tep_db_perform("admin_groups", $sql_data_array, 'update', 'admin_groups_id = ' . (int) $sGetId);
							$gId = $sGetId;
						} else {
							$messageStack->addSession( 'success', ADMIN_MEMBERS_GROUP_ADD_SUCCESS, 'success' );
							tep_db_perform("admin_groups", $sql_data_array);
							$gId = tep_db_insert_id();
						}

						$selectedBoxes = $_POST['boxes_to'];
						$define_files_query = tep_db_query("SELECT admin_files_id, admin_files_name, admin_files_is_boxes, admin_files_to_boxes, admin_groups_id
								FROM admin_files
								ORDER BY admin_files_id");

						while ($define_files = tep_db_fetch_array($define_files_query)) {
							$fileId = $define_files['admin_files_id'];
							$selectedGroups = $define_files['admin_groups_id'];
							$groupsArray = explode(",", (string) $selectedGroups);

							if (in_array($fileId, $selectedBoxes) && !in_array($gId, $groupsArray)) {
                                $result = array_merge([$gId], $groupsArray);
                            } elseif (!in_array($fileId, $selectedBoxes) && in_array($gId, $groupsArray)) {
                                $result = array_diff($groupsArray, [$gId]);
                            } else {
								$result = $groupsArray;
							}

							sort($result);
							$result = implode(",", $result);

							if($result != $selectedGroups) {
								$sql_data_array = ['admin_groups_id' => tep_db_prepare_input($result)];
								tep_db_perform("admin_files", $sql_data_array, 'update', 'admin_files_id = "' . (int) $fileId . '"');
							}

						}

						tep_redirect( tep_href_link(  $sUrlPage, 'action=groups' ) );
					}
				}
			}

			$db_boxes_query = tep_db_query("SELECT admin_files_id as admin_boxes_id, admin_files_name as admin_boxes_name, admin_groups_id as boxes_group_id
					FROM admin_files
					WHERE admin_files_is_boxes = '1'
					ORDER BY admin_files_name"
			);

			$boxes = [];

			while ($group_boxes = tep_db_fetch_array($db_boxes_query)) {
				$group_box_name = substr_replace($group_boxes['admin_boxes_name'], '', -4);
				fetchBox($boxes, $group_boxes, $group_box_name, ucwords($group_box_name), 0, $group_boxes['admin_boxes_id'], $sGetId);
			}

			fetchBox($boxes, null, 'sin_categoria', 'Sin categoría', 0, 0, $sGetId);

			$permissions_files = ['selected' => [], 'no_selected' => []];

			$aJs = [ $sPathModule . '/js/default.js?v=1.2' ];
			$aStyle = [ $sPathModule . '/css/admin_members.css' ];

			// Modulo
			$sHtmlModule = includeTemplate( $sPathTemplate . '/groups/crud.php', [
				'boxes' => $boxes
			] );
			break;

		case "groups":
			$sSubtitle = ADMIN_MEMBERS_HEADING_SUBTITLE_GROUPS_LIST;
			$aButtons = [
				[ 'title' => ADMIN_MEMBERS_TEXT_INFO_HEADING_MEMBERS_LIST, 'href' => tep_href_link( $sUrlPage, 'action=members' ), 'icon' => 'fa-crown' ],
				[ 'title' => ADMIN_MEMBERS_TEXT_INFO_HEADING_GROUPS, 'href' => tep_href_link( $sUrlPage, 'action=groups_crud' ), 'icon' => 'fa-plus' ]
			];
			if($login_email_address == "info@denox.es"){
				$aButtons[] = [ 'title' => "Lista de submodulos", 'href' => tep_href_link( $sUrlPage, 'action=submodules_list' ), 'icon' => 'fa-list' ];
			}

			$sHtmlActionMasivo = '<label class="column afluid">' . ADMIN_MEMBERS_TEXT_APPLY_ACTION . ':&nbsp;&nbsp;</label>
			<div class="column afluid"><div class="drop masv xfselect">
				<div>' . ADMIN_MEMBERS_TABLE_ACTIONS . '</div>
				<ul class="down drch">
					<li><a data-question="' . ADMIN_MEMBERS_TEXT_DELETES_CONFIRM . '" data-error="' . ADMIN_MEMBERS_TEXT_DELETE_ERROR . '" data-action="' . tep_href_link( $sUrlPage, 'action=groups_delete' ) . '" href="javascript:void(0);" class="hv"><i class="fa fa-trash"></i>' . ADMIN_MEMBERS_TEXT_DELETES . '</a></li>
				</ul>
			</div></div>&nbsp; - &nbsp;';

			// Filtros
			$aFilter = (array_key_exists( 'filter', $_POST ) && is_array($_POST['filter']) ? $_POST['filter'] : []);
			$sWhere = '';

			// Limpiamos variables get filter
			array_walk( $aFilter, function( $value, $key){ global $aFilter; $aFilter[$key] = tep_db_prepare_input( $aFilter[$key] ); } );

			if( $aFilter['search'] != '' ) {
				$sWhere .= 'WHERE (LOWER(admin_groups_name) LIKE "%' . strtolower((string) $aFilter['search']) . '%")';
			}

			// Sql
			$sSql = 'SELECT * FROM admin_groups ' . $sWhere . 'ORDER BY admin_groups_id';

			// Le quitamos los tabuladores y saltos de linea para que splitpageesult funcione con el SQL
			$sSql = preg_replace( '/[\r\n\t]+/', ' ', $sSql );

			// Sql para el count
			$sSqlCount = 'SELECT COUNT(*) as total FROM (' . $sSql . ') as table_aux';

			// Datos y paginacion
			$aRowsSplit = new splitPageResults( $sGetPage, MAX_DISPLAY_SEARCH_RESULTS, $sSql, $nAux, $sSqlCount );
			$aRows = tep_db_query( $sSql );

			// Modulo
			$sHtmlModule = includeTemplate( $sPathTemplate . '/groups/index.php' );
			break;
	}

?>
