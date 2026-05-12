<?php

	$sTitle = ADMIN_MEMBERS_HEADING_TITLE_MEMBERS;

	// Acciones members
	switch( $sPostAction ) {
		case 'members_delete':
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
				tep_db_query('DELETE FROM admin WHERE admin_id IN(' . substr($sIds, 0, -1) . ')');
			}

			$messageStack->addSession( 'success', ADMIN_MEMBERS_MEMBER_DELETE_SUCCESS, 'success' );
			tep_redirect( $_SERVER['HTTP_REFERER'] );
			break;

		case 'members_2fa_reset':
			require __DIR__ . '/../2fa-admin/actions/members_reset.php';
			break;

		case "members_password":
			if($login_groups_id != 1) {
				tep_redirect( tep_href_link( $sUrlPage ) );
			}

			$sGetId = array_key_exists( 'id', $_POST ) ? tep_db_input( $_POST['id'] ) : (array_key_exists( 'id', $_GET ) ? tep_db_input( $_GET['id'] ) : false);

			$sSubtitle = ADMIN_MEMBERS_TEXT_INFO_HEADING_MEMBER_CHANGE_PASSWORD;

			$aMessageError = [];
			$aButtons = [
				[ 'title' => TEXT_BACK, 'href' => tep_href_link( $sUrlPage, 'action=members' ), 'icon' => 'fa-arrow-left' ],
				[ 'title' => TEXT_SAVE, 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' ]
			];

			if ($_SERVER['REQUEST_METHOD'] === 'POST') {

				$admin_id = tep_db_prepare_input($_POST['id_info']);

				$password = tep_db_prepare_input($_POST['password']);
				$password_repeat = tep_db_prepare_input($_POST['password_repeat']);

				if($password != '' && $password_repeat != '' && $password != $password_repeat) {
					$messageStack->addSession('success', ADMIN_MEMBERS_TEXT_ERROR_PASSWORD_CONFIRM, 'error');
					tep_redirect(tep_href_link($sUrlPage, 'action=members_password&id=' . $sGetId));
				}

				// Libreria hash
				include(DIR_WS_CLASSES . 'passwordhash.php');

				// Obtenemos la contraseña en la base de datos
				$aDatos = tep_db_query(
					'SELECT admin_password
							FROM admin
							WHERE admin_id = "' . (int)$admin_id . '"' );
				$aDato = tep_db_fetch_array( $aDatos );

				$encrypted = $aDato['admin_password'];

				// Encriptamos la nueva contraseña para poder comprobarla con la antigua guardada
				$hasher = new PasswordHash(10, true);
				$hash = $hasher->crypt_private( $plain, $encrypted );
				if ($hash[0] == '*') {
                    $hash = crypt( (string) $plain, (string) $encrypted );
                }

				// Si la contraseña es identica
				if( $hash == $encrypted ) {
					$messageStack->addSession('success', ADMIN_MEMBERS_TEXT_ERROR_PASSWORD_SAME, 'error');
					tep_redirect(tep_href_link($sUrlPage, 'action=members_password&id=' . $sGetId));
				}

				// Si la contraseña no es alfanumerica
				if( !preg_match( '/^(?=.*\d)(?=.*[@#\-_$%^&+=ยง!\?])(?=.*[a-z])(?=.*[A-Z])[0-9A-Za-z@#\-_$%^&+=ยง!\?]{8,20}$/', $password )) {
					$messageStack->addSession('success', ADMIN_MEMBERS_TEXT_ERROR_PASSWORD_REGEX, 'error');
					tep_redirect(tep_href_link($sUrlPage, 'action=members_password&id=' . $sGetId));
				}

				// Eliminamos session de modificar contraseña
				tep_db_query( 'UPDATE admin SET admin_reset_password = 0 WHERE admin_id = "' . (int) $admin_id . '"' );

				$sql_data_array = [
					'admin_password' => tep_encrypt_password($password),
					'admin_modified' => 'now()'
				];

				$account_query = tep_db_query (
					"SELECT a.admin_id, a.admin_firstname, a.admin_lastname, a.admin_email_address, a.admin_created, a.admin_modified, a.admin_logdate, a.admin_lognum, g.admin_groups_id, g.admin_groups_name
				FROM admin a, admin_groups g WHERE a.admin_id= " . $admin_id
				);
				$account = tep_db_fetch_array($account_query);

				$admin_firstname = tep_db_prepare_input($account['admin_firstname']);
				$admin_lastname = tep_db_prepare_input($account['admin_lastname']);
				$admin_email_address = tep_db_prepare_input($account['admin_email_address']);

				tep_mail($admin_firstname . ' ' . $admin_lastname, $admin_email_address, sprintf(ADMIN_EMAIL_SUBJECT, STORE_NAME, $admin_firstname, $admin_lastname), sprintf(ADMIN_EMAIL_TEXT, $admin_firstname, HTTP_SERVER . DIR_WS_ADMIN, $admin_email_address, $password, STORE_OWNER), STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);

				tep_db_perform("admin", $sql_data_array, 'update', 'admin_id = \'' . $admin_id . '\'');

				$messageStack->addSession('success', ADMIN_MEMBERS_TEXT_SUCCESS_PASSWORD, 'success');

				tep_redirect( tep_href_link(  $sUrlPage ) );
			}

			// Modulo
			$sHtmlModule = includeTemplate( $sPathTemplate . '/members/password.php' );
			break;

		case "members_crud":
			$sGetId = array_key_exists( 'id', $_POST ) ? tep_db_input( $_POST['id'] ) : (array_key_exists( 'id', $_GET ) ? tep_db_input( $_GET['id'] ) : false);

			$sSubtitle = ($sGetId != '' ? ADMIN_MEMBERS_TEXT_EDITED : ADMIN_MEMBERS_TEXT_ADD) . ' ' . ADMIN_MEMBERS_TITLE_ADD_EDIT_MEMBER;

			$aMessageError = [];
			$aButtons = [
				[ 'title' => TEXT_BACK, 'href' => tep_href_link( $sUrlPage, 'action=members' ), 'icon' => 'fa-arrow-left' ],
				[ 'title' => TEXT_SAVE, 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' ]
			];

			$aRecord = [];

			// Si estamos editando
			if( $sGetId != false ) {
				// Obtenemos el registro
				$aRecord = pharaonix_queryOne( 'SELECT * FROM admin WHERE admin_id = "' . (int)$sGetId . '"' );

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
				$admin_cat_access = isset($_POST['cat_permissions_to']) ? implode(',', $_POST['cat_permissions_to']) : '';
				$admin_right_access = isset($_POST['rights_permissions_to']) ? implode(',', $_POST['rights_permissions_to']) : '';

				if ($_POST['admin_groups_id'] == 1 || $admin_right_access === 'CNEW,CEDIT,CMOVE,CDELETE,PNEW,PEDIT,PMOVE,PCOPY,PDELETE,') {
					$admin_right_access = "";
					$admin_cat_access = "ALL";
				} else {
                }

				if($sGetId != false) {
					$check_email_query = tep_db_query("SELECT admin_email_address
						FROM admin
						WHERE admin_id <> " . (int)$sGetId . "");
				} else {
					$check_email_query = tep_db_query("SELECT admin_email_address FROM admin");
				}

				while ($check_email = tep_db_fetch_array($check_email_query)) {
					$stored_email[] = $check_email['admin_email_address'];
				}

				if(tep_db_prepare_input($_POST['admin_firstname']) == '' || tep_db_prepare_input($_POST['admin_lastname']) == '' || tep_db_prepare_input($_POST['admin_email_address']) == '') {
					$messageStack->addSession( 'success', ADMIN_MEMBERS_TEXT_ERROR_INPUTS, 'error' );
					if ($sGetId != false) {
                        tep_redirect( tep_href_link(  $sUrlPage, 'action=members_crud&id=' . $sGetId ) );
                    } else {
                        tep_redirect( tep_href_link(  $sUrlPage, 'action=members_crud' ) );
                    }
				}

				// EDITANDO MIEMBRO
				if( $sGetId != false ) {
					if (in_array($_POST['admin_email_address'], $stored_email)) {
						$messageStack->addSession( 'success', ADMIN_MEMBERS_TEXT_ERROR_EMAIL_IN_USE, 'error' );
						tep_redirect(tep_href_link( $sUrlPage, 'action=members_crud&id=' . $sGetId));
						break;
					}

					$hiddenPassword = '-hidden-';

					$sql_data_array = [
						'admin_groups_id' => tep_db_prepare_input($_POST['admin_groups_id']),
						'admin_firstname' => tep_db_prepare_input($_POST['admin_firstname']),
						'admin_lastname' => tep_db_prepare_input($_POST['admin_lastname']),
						'admin_email_address' => tep_db_prepare_input($_POST['admin_email_address']),
						'admin_right_access' => tep_db_prepare_input($admin_right_access),
						'admin_cat_access' => tep_db_prepare_input($admin_cat_access),
						'admin_modified' => 'now()'
					];

					tep_mail($_POST['admin_firstname'] . ' ' . $_POST['admin_lastname'], $_POST['admin_email_address'], sprintf(ADMIN_EMAIL_EDIT_SUBJECT, STORE_NAME, $_POST['admin_firstname'], $_POST['admin_lastname']), sprintf(ADMIN_EMAIL_EDIT_TEXT, $_POST['admin_firstname'], (ENABLE_SSL == false ? (empty($_SERVER['HTTPS']) ? HTTP_SERVER : HTTPS_SERVER) : HTTPS_SERVER) . DIR_WS_ADMIN, $_POST['admin_email_address'], $hiddenPassword, STORE_OWNER), STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
					tep_mail(STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, sprintf(ADMIN_EMAIL_EDIT_SUBJECT, STORE_NAME, $_POST['admin_firstname'], $_POST['admin_lastname']), sprintf(ADMIN_EMAIL_EDIT_TEXT, $_POST['admin_firstname'], (ENABLE_SSL == false ? (empty($_SERVER['HTTPS']) ? HTTP_SERVER : HTTPS_SERVER) : HTTPS_SERVER) . DIR_WS_ADMIN, $_POST['admin_email_address'], $hiddenPassword, STORE_OWNER), STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);

					$messageStack->addSession( 'success', ADMIN_MEMBERS_MEMBER_EDIT_SUCCESS, 'success' );
					tep_db_perform("admin", $sql_data_array, 'update', 'admin_id = ' . (int) $sGetId);
				//AÑADIENDO MIEMRBO
				} else {
					if (in_array($_POST['admin_email_address'], $stored_email)) {
						tep_redirect(tep_href_link( $sUrlPage, 'action=members_crud'));
						break;
					}

					function randomize() {
						include(DIR_WS_CLASSES . 'passwordhash.php');
						$hasher = new PasswordHash(2, true);
						return $hasher->generate_password( 8, true, false );
					}
					$makePassword = randomize();

					$sql_data_array = [
						'admin_groups_id' => tep_db_prepare_input($_POST['admin_groups_id']),
						'admin_firstname' => tep_db_prepare_input($_POST['admin_firstname']),
						'admin_lastname' => tep_db_prepare_input($_POST['admin_lastname']),
						'admin_email_address' => tep_db_prepare_input($_POST['admin_email_address']),
						'admin_password' => tep_encrypt_password($makePassword),
						'admin_right_access' => tep_db_prepare_input($admin_right_access),
						'admin_cat_access' => tep_db_prepare_input($admin_cat_access),
						'admin_created' => 'now()'
					];

					$messageStack->addSession( 'success', ADMIN_MEMBERS_MEMBER_ADD_SUCCESS, 'success' );
					tep_db_perform("admin", $sql_data_array);

					tep_mail($_POST['admin_firstname'] . ' ' . $_POST['admin_lastname'], $_POST['admin_email_address'], sprintf(ADMIN_EMAIL_SUBJECT, STORE_NAME, $_POST['admin_firstname'], $_POST['admin_lastname']), sprintf(ADMIN_EMAIL_TEXT, $_POST['admin_firstname'], (ENABLE_SSL == false ? (empty($_SERVER['HTTPS']) ? HTTP_SERVER : HTTPS_SERVER) : HTTPS_SERVER) . DIR_WS_ADMIN, $_POST['admin_email_address'], $makePassword, STORE_OWNER), STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
				}

				tep_redirect(tep_href_link( $sUrlPage, 'action=members' ));

			}

			// Obtener grupos de administrador
			$groups_array = [['id' => '0', 'text' => TEXT_NONE]];
			$groups_query = tep_db_query("SELECT admin_groups_id, admin_groups_name FROM admin_groups");
			while ($groups = tep_db_fetch_array($groups_query)) {
				$groups_array[] = ['id' => $groups['admin_groups_id'], 'text' => $groups['admin_groups_name']];
			}

			// Obtener listado de permisos de acceso
			$permissions_access = getAdminAccessPermissions($aRecord);

			// Obtener listado de permisos de categoría
			$permissions_categories = getAdminCategoriesPermissions($aRecord);

			$aJs = [ $sPathModule . '/js/default.js' ];
			$aStyle = [ $sPathModule . '/css/admin_members.css' ];

			// Modulo
			$sHtmlModule = includeTemplate( $sPathTemplate . '/members/crud.php' );
			break;

		default:
		case "members":
			$sSubtitle = ADMIN_MEMBERS_HEADING_SUBTITLE_MEMBERS_LIST;
			$aButtons = [
				[ 'title' => ADMIN_MEMBERS_TEXT_INFO_HEADING_GROUPS_LIST, 'href' => tep_href_link( $sUrlPage, 'action=groups' ), 'icon' => 'fa-user-group' ],
				[ 'title' => ADMIN_MEMBERS_TEXT_INFO_HEADING_MEMBERS, 'href' => tep_href_link( $sUrlPage, 'action=members_crud' ), 'icon' => 'fa-plus' ]
			];

			$sHtmlActionMasivo = '<label class="column afluid">' . ADMIN_MEMBERS_TEXT_APPLY_ACTION . ':&nbsp;&nbsp;</label>
			<div class="column afluid"><div class="drop masv xfselect">
				<div>' . ADMIN_MEMBERS_TABLE_ACTIONS . '</div>
				<ul class="down drch">
					<li><a data-question="' . ADMIN_MEMBERS_TEXT_DELETES_CONFIRM . '" data-error="' . ADMIN_MEMBERS_TEXT_DELETE_ERROR . '" data-action="' . tep_href_link( $sUrlPage, 'action=members_delete' ) . '" href="javascript:void(0);" class="hv"><i class="fa fa-trash"></i>' . ADMIN_MEMBERS_TEXT_DELETES . '</a></li>
				</ul>
			</div></div>&nbsp; - &nbsp;';

			// Filtros
			$aFilter = (array_key_exists( 'filter', $_POST ) && is_array($_POST['filter']) ? $_POST['filter'] : []);
			$sWhere = '';

			// Limpiamos variables get filter
			array_walk( $aFilter, function( $value, $key){ global $aFilter; $aFilter[$key] = tep_db_prepare_input( $aFilter[$key] ); } );

			if( $aFilter['search'] != '' ) {
				$sWhere .= 'WHERE (LOWER(CONCAT(admin_firstname, " ", admin_lastname, " ", admin_email_address)) LIKE "%' . strtolower((string) $aFilter['search']) . '%")';
			}

			$sWhere .= ( $aFilter['search'] != '' ? ' AND' : 'WHERE') . " admin.admin_email_address != 'info@denox.es'";

			// Sql
			$sSql = 'SELECT admin.*, admin_groups.admin_groups_name
				FROM admin
				LEFT JOIN admin_groups
				    ON admin_groups.admin_groups_id = admin.admin_groups_id
				' . $sWhere . '
				ORDER BY admin.admin_firstname
			';

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
			$sHtmlModule = includeTemplate( $sPathTemplate . '/members/index.php' );
			break;
	}

?>
