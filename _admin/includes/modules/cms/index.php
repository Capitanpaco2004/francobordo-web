<?php
	// Tools
	use util\tools as tools;
	use util\date as date;
	use util\strings;

	// Si nos mandan a instalar cambiamos el modulo por login para que forbidden no salte y podamos instalarlo
	if( array_key_exists( 'action', $_GET ) && $_GET['action'] == 'install' )
	{
		// FIX bypass sin auth: PHP_SELF='index.php' (FILENAME_DEFAULT) hace que tep_admin_check_login
		// salte SOLO el ACL de pagina. NO tocar SCRIPT_FILENAME: asi el login SIGUE exigiendose.
		$_SERVER['PHP_SELF'] = 'index.php';
	}

	// Incluimos el application_top (solo si no está ya incluido)
	if (!defined('DIR_WS_ADMIN')) {
		require_once( 'includes/application_top.php' );
	}

	// Fallback para constantes SEO que pueden no existir en esta instalación
	if (!defined('CARACTERES_SEO_TITLE')) define('CARACTERES_SEO_TITLE', 60);
	if (!defined('CARACTERES_SEO_DESCRIPTION')) define('CARACTERES_SEO_DESCRIPTION', 155);

	include( 'includes/modules/cms/includes/functions/functions.php' );

	// Cargamos el idioma del módulo (include_once para evitar duplicado con application_top)
	if (file_exists(DIR_WS_LANGUAGES . $language . '/information_manager.php')) {
		include_once(DIR_WS_LANGUAGES . $language . '/information_manager.php');
	} else {
		include_once(DIR_WS_LANGUAGES . 'espanol/information_manager.php');
	}

	// Variables
	$sUrlPage =  'information_manager.php';
	$sTitle = INFORMATION_MANAGER_TITLE;
	$sSubtitle = '';
	$aButtons = [];
	$sPostAction = array_key_exists( 'action', $_POST ) ? tep_db_input( $_POST['action'] ) : (array_key_exists( 'action', $_GET ) ? tep_db_input( $_GET['action'] ) : false);
	$sGetPage = (isset( $_GET['page'] ) ? tep_db_prepare_input( $_GET['page'] ) : 1);
	$sHtml = '';

	# Messagestack estilo
	$messageStack->style = 'solenopsis';

	// Acciones
	switch( $sPostAction )
	{
		case 'setflag':
            // Variables
			$nId = tep_db_prepare_input( $_GET['id'] );
			$nFlag = tep_db_prepare_input( $_GET['flag'] );

			tep_db_query('UPDATE ' . TABLE_INFORMATION . ' set visible = ' . (int)$nFlag . ' WHERE information_id = ' . (int)$nId );

            tep_redirect( $_SERVER['HTTP_REFERER'] );
        break;

		case 'delete':
			// Variables
			$aGetId = tep_db_prepare_input( $_GET['id'] );
			$aPostId = isset($_POST['id']) ? $_POST['id'] : [];
			$sIds = '';

			// Si nos envian por get creamos el array
			if ($aGetId != '') {
                $aPostId = [ $aGetId ];
            }

			// Recorremos los id
			foreach( $aPostId as $sId )
				$sIds .= (int)$sId . ',';

			// Si tenemos id eliminamos
			if ($sIds !== '') {
                tep_db_query( 'DELETE FROM ' . TABLE_INFORMATION . ' WHERE information_id IN(' . substr( $sIds, 0, -1 ) . ')' );
            }

			// Redireccionamos
			$messageStack->addSession( 'success', INFORMATION_MANAGER_DELETE_SUCCESS, 'success' );
			tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;

		case 'update':
		case 'add_form':
			// Javascript y css
			$aJs = [ 'includes/modules/cms/js/index.js' ];
			$aStyle = [ 'includes/modules/cms/css/style.css' ];

			// Variables
			$gID = tep_db_prepare_input( $_GET['gID'] );
			$sGetId = array_key_exists( 'id', $_POST ) ? tep_db_input( $_POST['id'] ) : (array_key_exists( 'id', $_GET ) ? tep_db_input( $_GET['id'] ) : false);
			$aMessageError = [];
			$sSubtitle = ($sGetId != '' ? TEXT_EDIT . ' ' : TEXT_ADD . ' ') . getInfoGroupTitleById( $gID );
			$aButtons = [
				[ 'title' => TEXT_BACK, 'href' => tep_href_link( $sUrlPage, 'gID=' . (int)$gID ), 'icon' => 'fa-arrow-left' ],
				[ 'title' => TEXT_SAVE, 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde saveform-products' ]
			];
			$aRecord = [];

			// Obtenemos idiomas
			$aLanguages = tep_get_languages();

			// Si estamos editando
			if( $sGetId != false )
			{
				// Obtenemos el registro
				$aRecords = tep_db_query( 'SELECT information_title, information_description, parent_id, sort_order, visible, language_id, information_seo_title, information_seo_description, noindex, nofollow FROM ' . TABLE_INFORMATION . ' WHERE information_id = "' . (int)$sGetId . '"' );

				// Si no existe
				if( tep_db_num_rows( $aRecords ) == 0 )
				{
					$messageStack->addSession( 'success', INFORMATION_MANAGER_RECORD_NO_EXISTS, 'error' );
					tep_redirect( tep_href_link(  $sUrlPage ) );
				}

				// Registro
				while( $aRow = tep_db_fetch_array( $aRecords ) )
				{
					$aRecord['information_title'][$aRow['language_id']] = $aRow['information_title'];
					$aRecord['information_description'][$aRow['language_id']] = $aRow['information_description'];
					$aRecord['parent_id'][$aRow['language_id']] = $aRow['parent_id'];
					$aRecord['sort_order'][$aRow['language_id']] = $aRow['sort_order'];
					$aRecord['visible'][$aRow['language_id']] = $aRow['visible'];
					$aRecord['information_seo_title'][$aRow['language_id']] = $aRow['information_seo_title'];
					$aRecord['information_seo_description'][$aRow['language_id']] = $aRow['information_seo_description'];
					$aRecord['noindex'][$aRow['language_id']] = $aRow['noindex'];
					$aRecord['nofollow'][$aRow['language_id']] = $aRow['nofollow'];
				}
			}

			// Insertar o actualizar
			if( $sPostAction == 'update' )
			{
				// Pasamos todos los post por tep_db_prepare_input
				array_walk( $_POST, function( $value, $key){ global $_POST; $_POST[$key] = tep_db_prepare_input( $_POST[$key] ); } );

				// Comprobamos que nos hayan enviado un título
				if (!array_key_exists( 'information_title', $_POST ) || (array_key_exists( 'information_title', $_POST ) && $_POST['information_title'][$languages_id] == '')) {
                    $aMessageError['titulo'] = $messageStack->show( [ 'text' => INFORMATION_MANAGER_TITLE_ERROR, 'class' => 'error' ] );
                }

				// Si no existe errores actualizamos/insertamos
				if( count( $aMessageError ) == 0 )
				{
					$nId = 0;

					// Recorremos idiomas
					foreach( $aLanguages as $aLanguage )
					{
						$aSql = [
							'information_group_id' => (int)$gID,
							'parent_id' => $_POST['parent_id'],
							'sort_order' => $_POST['sort_order'],
							'visible' => isset($_POST['visible']) ? $_POST['visible'] : 0,
							'noindex' => isset($_POST['noindex']) ? $_POST['noindex'] : 0,
							'nofollow' => isset($_POST['nofollow']) ? $_POST['nofollow'] : 0,
							'language_id' => $aLanguage['id'],
							'information_title' => (isset($_POST['information_title'][$aLanguage['id']]) && $_POST['information_title'][$aLanguage['id']] != '' ? $_POST['information_title'][$aLanguage['id']] : $_POST['information_title'][$languages_id]),
							'information_description' => (isset($_POST['information_description'][$aLanguage['id']]) && $_POST['information_description'][$aLanguage['id']] != '' ? $_POST['information_description'][$aLanguage['id']] : $_POST['information_description'][$languages_id]),
							'information_seo_title' => (isset($_POST['information_seo_title'][$aLanguage['id']]) && $_POST['information_seo_title'][$aLanguage['id']] != '' ? $_POST['information_seo_title'][$aLanguage['id']] : $_POST['information_seo_title'][$languages_id]),
							'information_seo_description' => (isset($_POST['information_seo_description'][$aLanguage['id']]) && $_POST['information_seo_description'][$aLanguage['id']] != '' ? $_POST['information_seo_description'][$aLanguage['id']] : $_POST['information_seo_description'][$languages_id])
						];

						// Limpiamos html
						$aSql['information_description'] = strings::cleanHTML($aSql['information_description']);

						if ($sGetId != false) {
                            tep_db_perform( TABLE_INFORMATION, $aSql, 'update', 'information_id = "' . (int)$sGetId . '" AND language_id = ' . $aLanguage['id'] );
                        } else
						{
							if ($nId > 0) {
                                $aSql = array_merge( $aSql, [ 'information_id' => $nId ] );
                            }

							tep_db_perform( TABLE_INFORMATION, $aSql, 'insert', '' );
							$nId = tep_db_insert_id();
						}
					}

					// Mensaje
					$messageStack->addSession( 'success', ($sGetId != false ? INFORMATION_MANAGER_EDIT_SUCCESS : INFORMATION_MANAGER_NEW_SUCCESS), 'success' );

					// Redireccionamos
					tep_redirect( tep_href_link(  $sUrlPage, 'gID=' . (int)$gID ) );
				}
			}

			// Creamos array con las páginas de información
			$aCMSs = [ [ 'id' => 0, 'text' => '-' ] ];

			$aAuxs = tep_db_query( 'SELECT information_id, information_title
									FROM ' . TABLE_INFORMATION . '
									WHERE information_group_id = ' . (int)$gID . '
									AND language_id = ' . $languages_id . '
									ORDER BY sort_order' );

			while( $aAux = tep_db_fetch_array( $aAuxs ) )
				$aCMSs[] = [ 'id' => $aAux['information_id'], 'text' => $aAux['information_title'] ];

			// Formulario
			$sHtml .= '<form method="post" id="saveform-send" action="' . tep_href_link( $sUrlPage, 'gID=' . (int)$gID . '&action=update' ) . '">';
				$sHtml .= '<div class="oeBox column a12 row ax" style="margin-bottom: 20px;">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa-shield"></i> ' . INFORMATION_MANAGER_TEXT_CONFIGURATION . ' </div>';
						$sHtml .= '<div class="oeCntd row ax xform xform-horizontal">';
							$sHtml .= $sGetId !== false ? '<input type="hidden" name="id" value="' . $sGetId . '" />' : '';
							$sHtml .= '<input type="submit" style="display: none;" />';

							$sHtml .= '<label for="estado" class="column a01 tright inline">' . INFORMATION_MANAGER_CONFIGURE_STATUS . ':</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= '<input type="checkbox" name="visible" id="visible" ' . ((array_key_exists( 'visible', $aRecord ) ? $aRecord['visible'][$languages_id] : ($_POST['visible'] ?? '')) ? 'checked="checked"' : '') . ' value="1"/><label for="visible"><span></span></label>';
								$sHtml .= '<div class="DFhelp">' . INFORMATION_MANAGER_CONFIGURE_STATUS_HELP . '</div>';
							$sHtml .= '</div>';

							$sHtml .= '<div class="xline xline-dashed"></div>';

							$sHtml .= '<label for="nofollow" class="column a01 tright inline">' . INFORMATION_MANAGER_CONFIGURE_MOTHER_PAGE . ':</label>';
							$sHtml .= '<div class="column a03">';
								$sHtml .= tep_draw_pull_down_menu( 'parent_id', $aCMSs, (array_key_exists( 'parent_id', $aRecord ) ? $aRecord['parent_id'][$languages_id] : ($_POST['parent_id'] ?? '')) );
								$sHtml .= '<div class="DFhelp">' . INFORMATION_MANAGER_CONFIGURE_MOTHER_PAGE_HELP . '</div>';
							$sHtml .= '</div>';

							$sHtml .= '<div class="xline xline-dashed"></div>';

							$sHtml .= '<label for="orden" class="column a01 tright">' . INFORMATION_MANAGER_CONFIGURE_ORDER . ':</label>';
							$sHtml .= '<div class="column a03">';
								$sHtml .= '<input type="text" name="sort_order" id="orden" value="' . (array_key_exists( 'sort_order', $aRecord ) ? $aRecord['sort_order'][$languages_id] : ($_POST['sort_order'] ?? '')) . '"/>';
								$sHtml .= '<div class="DFhelp">' . INFORMATION_MANAGER_CONFIGURE_ORDER_HELP . '</div>';
							$sHtml .= '</div>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';

					$sHtml .= '<div class="oeWrpr" style="margin-top: 20px;">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa-eye"></i> CMS </div>';
						$sHtml .= '<div class="oeCntd row ax xform xform-horizontal">';
							$sHtml .= tools::getInputLanguages( 'information_title', INFORMATION_MANAGER_CONFIGURE_TITLE . ':', $aRecord['information_title'] ?? [], INFORMATION_MANAGER_CONFIGURE_TITLE_HELP );
							$sHtml .= array_key_exists( 'titulo', $aMessageError ) ? $aMessageError['titulo'] : '';
							$sHtml .= '<div class="xline xline-dashed"></div>';

							$sHtml .= tools::getInputLanguages( 'information_description', INFORMATION_MANAGER_CONFIGURE_CONTENT . ':', $aRecord['information_description'] ?? [], INFORMATION_MANAGER_CONFIGURE_CONTENT_HELP, '', 10, false );
						$sHtml .= '</div>';
					$sHtml .= '</div>';

					$sHtml .= '<div class="oeWrpr" style="margin-top: 20px;">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa-eye"></i> ' . INFORMATION_MANAGER_CONFIGURE_SEO_OPTIONS . ' </div>';
						$sHtml .= '<div class="oeCntd row ax xform xform-horizontal">';

							foreach( $aLanguages as $aLanguage )
							{
								$sHtml .= '<div class="column a05" style="margin: 0 4%;">';
									$sHtml .= '<b style="display:block; margin-bottom: 7px;"><span style="position: relative; top: -5px; margin-right: 6px;">' . tep_image( DIR_WS_CATALOG_LANGUAGES . $aLanguage['directory'] . '/images/' . $aLanguage['image'], $aLanguage['name'], '', '', 'style="margin-top: 6px;"' ) . '</span>' . INFORMATION_MANAGER_CONFIGURE_PREVIEW_SEO . '</b>';
									$sHtml .= '<div class="seo-cms" style="margin-bottom: 20px;">';
										$sHtml .= '<span class="titl" data-row="information_seo_title[' . $aLanguage['id'] . ']" data-id="' . $aLanguage['id'] . '" data-max="' . CARACTERES_SEO_TITLE . '">' . ($aRecord['information_seo_title'][$aLanguage['id']] ?? '') . '</span>';
										$sHtml .= '<span class="url">' . tep_catalog_href_link( TEXT_URL_EXAMPLE . '.php' ) . '</span>';
										$sHtml .= '<span class="dscp" data-row="information_seo_description[' . $aLanguage['id'] . ']" data-id="' . $aLanguage['id'] . '" data-max="' . CARACTERES_SEO_DESCRIPTION . '">' . ($aRecord['information_seo_description'][$aLanguage['id']] ?? '') . '</span>';
									$sHtml .= '</div>';
								$sHtml .= '</div>';
							}

							$sHtml .= tools::getInputLanguages( 'information_seo_title', INFORMATION_MANAGER_CONFIGURE_SEO_TITLE . ':', $aRecord['information_seo_title'] ?? [], INFORMATION_MANAGER_CONFIGURE_SEO_TITLE_HELP );

							$sHtml .= '<div class="xline xline-dashed"></div>';

							$sHtml .= tools::getInputLanguages( 'information_seo_description', INFORMATION_MANAGER_CONFIGURE_SEO_DESCRIPTION . ':', $aRecord['information_seo_description'] ?? [], INFORMATION_MANAGER_CONFIGURE_SEO_DESCRIPTION_HELP, '', 10, false, true );

							$sHtml .= '<div class="xline xline-dashed"></div>';

							$sHtml .= '<label for="noindex" class="column a02 tright inline">' . INFORMATION_MANAGER_CONFIGURE_NO_INDEX . ':</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= '<input type="checkbox" name="noindex" id="noindex" ' . ((array_key_exists( 'noindex', $aRecord ) ? $aRecord['noindex'][$languages_id] : ($_POST['noindex'] ?? '')) ? 'checked="checked"' : '') . ' value="1"/><label for="noindex"><span></span></label>';
								$sHtml .= '<div class="DFhelp">' . INFORMATION_MANAGER_CONFIGURE_NO_INDEX_HELP . '</div>';
							$sHtml .= '</div>';

							$sHtml .= '<div class="xline xline-dashed"></div>';

							$sHtml .= '<label for="nofollow" class="column a02 tright inline">' . INFORMATION_MANAGER_CONFIGURE_NO_FOLLOW . ':</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= '<input type="checkbox" name="nofollow" id="nofollow" ' . ((array_key_exists( 'nofollow', $aRecord ) ? $aRecord['nofollow'][$languages_id] : ($_POST['nofollow'] ?? '')) ? 'checked="checked"' : '') . ' value="1"/><label for="nofollow"><span></span></label>';
								$sHtml .= '<div class="DFhelp">' . INFORMATION_MANAGER_CONFIGURE_NO_FOLLOW_HELP . '</div>';
							$sHtml .= '</div>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';
			$sHtml .= '</form>';
		break;

		default:
			// Variables
			$gID = tep_db_prepare_input( $_GET['gID'] );
			$sSubtitle = getInfoGroupTitleById( $gID );
			$aButtons[] = [ 'title' => INFORMATION_MANAGER_TEXT_NEW_CMS, 'href' => tep_href_link( $sUrlPage, 'gID=' . (int)$gID . '&action=add_form' ), 'icon' => 'fa-plus' ];

			// Html para el boton masivo
			$sHtmlActionMasivo = '<label class="column afluid">' . INFORMATION_MANAGER_TEXT_APPLY_ACTION . ':&nbsp;&nbsp;</label>
			<div class="column afluid"><div class="drop masv xfselect">
				<div>' . INFORMATION_MANAGER_TABLE_ACTIONS . '</div>
				<ul class="down drch">
					<li><a data-question="' . INFORMATION_MANAGER_TEXT_DELETE_RECORDS_CONFIRM . '" data-error="' . INFORMATION_MANAGER_DELETE_ERROR . '" data-action="' . tep_href_link( $sUrlPage, 'gID=' . (int)$gID . '&action=delete' ) . '" href="javascript:void(0);" class="hv"><i class="fas fa-trash-alt"></i>' . INFORMATION_MANAGER_TEXT_DELETE_RECORDS . '</a></li>
				</ul>
			</div></div>&nbsp; - &nbsp;';

			// Sql
			$sSql = 'SELECT information_id, information_title, parent_id, sort_order, visible FROM ' . TABLE_INFORMATION . ' WHERE language_id = "' . (int)$languages_id . '" AND information_group_id = "' . (int)$gID . '" ORDER BY sort_order';

			// Sql para el count
			$sSqlCount = 'SELECT COUNT(table_aux.information_id) as total FROM (' . $sSql . ') as table_aux';

			// Datos y paginacion
			$aDatoSplit = new splitPageResults( $sGetPage, 20, $sSql, $nAux, $sSqlCount );
			$aDatos = tep_db_query( $sSql );

			// Mensajes comprobamos si tenemos datos
			if (tep_db_num_rows( $aDatos ) <= 0) {
                $sHtml .= $messageStack->show( [ 'text' => INFORMATION_MANAGER_NO_RECORDS, 'class' => 'warning' ] );
            }

			// Tabla
			$sHtml .= '<div class="oeBox oeTable column a12 row ax">';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-eye"></i> ' . INFORMATION_MANAGER_TEXT_CMS_LIST . '</div>';
					$sHtml .= '<form method="post" action="' . tep_href_link( $sUrlPage, 'gID=' . (int)$gID ) . '" class="oeCntd row ax">';
						$sHtml .= '<table class="xform">';
							$sHtml .= '<thead>';
								$sHtml .= '<tr>';
									$sHtml .= '<th width="17" class="chck"><input type="checkbox" id="all_check"/><label for="all_check"><span></span></label></th>';
									$sHtml .= '<th width="50">' . INFORMATION_MANAGER_TABLE_ID . '</th>';
									$sHtml .= '<th>' . INFORMATION_MANAGER_TABLE_TITLE . '</th>';
									$sHtml .= '<th style="text-align: center;">' . INFORMATION_MANAGER_TABLE_MOTHER_WEB . '</th>';
									$sHtml .= '<th style="text-align: center;">' . INFORMATION_MANAGER_TABLE_VISIBLE . '</th>';
									$sHtml .= '<th style="text-align: center;">' . INFORMATION_MANAGER_TABLE_ORDER . '</th>';
									$sHtml .= '<th width="125">' . INFORMATION_MANAGER_TABLE_ACTIONS . '</th>';
								$sHtml .= '</tr>';
							$sHtml .= '</thead>';
							$sHtml .= '<tbody>';

								while( $aDato = tep_db_fetch_array( $aDatos ) )
								{
									// Fila
									$sHtml .= '<tr data-dblclick="' . tep_href_link( $sUrlPage, 'gID=' . (int)$gID . '&action=add_form&id=' . $aDato['information_id'] ) . '">';
										$sHtml .= '<td class="chck" align="center"><input type="checkbox" id="id_' . $aDato['information_id'] . '" name="id[]" value="' . $aDato['information_id'] . '"/><label for="id_' . $aDato['information_id'] . '"><span></span></label></td>';
										$sHtml .= '<td>' . $aDato['information_id'] . '</td>';
										$sHtml .= '<td>' . $aDato['information_title'] . '</td>';
										$sHtml .= '<td align="center">' . ($aDato['parent_id'] > 0 ? $aDato['parent_id'] : '') . '</td>';

										$sHtml .= '<td align="center">';
											if ($aDato['visible'] == '1') {
                                                $sHtml .= tep_image(DIR_WS_IMAGES . 'icon_status_green.png', IMAGE_ICON_STATUS_GREEN, 10, 10) . '&nbsp;&nbsp;<a href="' . tep_href_link($sUrlPage, 'action=setflag&flag=0&id=' . $aDato['information_id'], 'NONSSL') . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_red_light.png', IMAGE_ICON_STATUS_RED_LIGHT, 10, 10) . '</a>';
                                            } else {
                                                $sHtml .= '<a href="' . tep_href_link($sUrlPage, 'action=setflag&flag=1&id=' . $aDato['information_id'], 'NONSSL') . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_green_light.png', IMAGE_ICON_STATUS_GREEN_LIGHT, 10, 10) . '</a>&nbsp;&nbsp;' . tep_image(DIR_WS_IMAGES . 'icon_status_red.png', IMAGE_ICON_STATUS_RED, 10, 10);
                                            }
										$sHtml .= '</td>';

										$sHtml .= '<td align="center">' . $aDato['sort_order'] . '</td>';

										$sHtml .= '<td>';
											$sHtml .= '<div class="drop xfselect">';
												$sHtml .= '<div>Acciones</div>';
												$sHtml .= '<ul class="down down-dngt">';
													$sHtml .= '<li><a href="' . tep_href_link( $sUrlPage, 'gID=' . (int)$gID . '&action=add_form&id=' . $aDato['information_id'] ) . '" class="hv"><i class="fa fa-pencil"></i>' . INFORMATION_MANAGER_TEXT_EDIT_RECORD . '</a></li>';
													$sHtml .= '<li><a data-confirm="' . INFORMATION_MANAGER_TEXT_DELETE_RECORD_CONFIRM . '" href="' . tep_href_link( $sUrlPage, 'gID=' . (int)$gID . '&action=delete&id=' . $aDato['information_id'] ) . '" class="hv"><i class="fas fa-trash-alt"></i>' . INFORMATION_MANAGER_TEXT_DELETE_RECORD . '</a></li>';
												$sHtml .= '</ul>';
											$sHtml .= '</div>';
										$sHtml .= '</td>';
									$sHtml .= '</tr>';
								}

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
		echo '<div class="oeTitu column logo afixed" style="padding-left: 59px;"><b><i class="fa fa-clone"></i> ' . $sTitle . '</b>' . ($sSubtitle ? '<small>' . $sSubtitle . '</small>' : '') . '</div>';
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
