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

	// Incluimos el application_top
	require( 'includes/application_top.php' );

	// Variables
	$sUrlPage =  'manufacturers.php';
	$sTitle = TABLE_HEADING_MANUFACTURERS;
	$sSubtitle = '';
	$aButtons = [];
	$sPostAction = array_key_exists( 'action', $_POST ) ? tep_db_input( $_POST['action'] ) : (array_key_exists( 'action', $_GET ) ? tep_db_input( $_GET['action'] ) : false);

	$sGetPage = (isset( $_GET['page'] ) ? tep_db_prepare_input( $_GET['page'] ) : 1);
	$sGetOrderby = (isset( $_GET['orderby'] ) ? tep_db_prepare_input( $_GET['orderby'] ) : '');
	$sGetSort = (isset( $_GET['sort'] ) ? tep_db_prepare_input( $_GET['sort'] ) : '');
	$sHtml = '';

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

			$sHtml = tools::parsedown( DIR_WS_MODULES . '/manufacturers/readme.txt' );
		break;

		case 'setflag':
            // Variables
			$nId = tep_db_prepare_input( $_GET['id'] );
			$nFlag = tep_db_prepare_input( $_GET['flag'] );

			tep_db_query('UPDATE manufacturers set manufacturers_status = ' . (int)$nFlag . ' WHERE manufacturers_id = ' . (int)$nId );

            tep_redirect( $_SERVER['HTTP_REFERER'] );
        break;

		case 'delete':
			// Variables
			$aGetId = tep_db_prepare_input( $_GET['id'] );
			$aPostId = tep_db_prepare_input( $_POST['id'] );
			$sDelete = tep_db_prepare_input( $_GET['delete_products'] );
			$sIds = '';

			// Si nos envian por get creamos el array
			if ($aGetId != '') {
                $aPostId = [ $aGetId ];
            }

			// Recorremos los id
			foreach( $aPostId as $sId )
			{
				$sIds .= $sId . ',';

				$aImagenes = glob( getcwd() . '/../images/fabricantes/' . $sId . '-*' );
				$aImagenesThumb = glob( getcwd() . '/../images/fabricantes/thumbnails/' . $sId . '-*' );

				foreach( $aImagenes as $sFile )
					@unlink( $sFile );

				foreach( $aImagenesThumb as $sFile )
					@unlink( $sFile );
			}

			// Si tenemos id eliminamos
			if( $sIds !== '' )
			{
				// Eliminamos marcas
				tep_db_query( 'DELETE FROM manufacturers WHERE manufacturers_id IN(' . substr( $sIds, 0, -1 ) . ')' );

				// Si hemos indicado eliminar también los productos
				if( $sDelete )
				{
					// Obtenemos los productos de la marca
					$aAuxs = tep_db_query( 'SELECT products_id
											FROM ' . TABLE_PRODUCTS . '
											WHERE manufacturers_id IN(' . substr( $sIds, 0, -1 ) . ')' );

					// Recorremos y eliminamos
					while( $aAux = tep_db_fetch_array( $aAuxs ) )
						tep_remove_product( $aAux['products_id'] );
				}
				// Si no, actualizamos la marca a vacío
				else {
                    tep_db_query( 'UPDATE ' . TABLE_PRODUCTS . ' SET manufacturers_id = "" WHERE manufacturers_id = ' . (int)$manufacturers_id );
                }
			}

			// Redireccionamos
			$messageStack->addSession( 'success', TEXT_MANUFACTURERS_DELETE_OK, 'success' );
			tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;

		case 'update':
		case 'add_form':
			// Javascript y css
			$aJs = [ 'includes/modules/manufacturers/js/index.js' ];
			$aStyle = [ 'includes/modules/manufacturers/css/style.css' ];

			// Variables
			$sGetId = array_key_exists( 'id', $_POST ) ? tep_db_input( $_POST['id'] ) : (array_key_exists( 'id', $_GET ) ? tep_db_input( $_GET['id'] ) : false);
			$aMessageError = [];
			$sSubtitle = ($sGetId != '' ? TEXT_MANUFACTURERS_EDIT : TEXT_MANUFACTURERS_NEW) . TEXT_MANUFACTURERS_EDIT_NEW_MANUFACTURER;
			$aButtons = [
				[ 'title' => TEXT_MANUFACTURERS_BACK, 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-arrow-left' ],
				[ 'title' => TEXT_MANUFACTURERS_SAVE, 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde saveform-products' ]
			];
			$aRecord = [];

			// Obtenemos idiomas
			$aLanguages = tep_get_languages();

			// Si estamos editando
			if( $sGetId != false )
			{
				// Obtenemos el registro
				$aRecords = tep_db_query( 'SELECT m.manufacturers_id, m.manufacturers_name, m.orden,
										  mi.languages_id, mi.seo_title, mi.seo_description, mi.seo_text_landing_page
										  FROM manufacturers m
										  INNER JOIN manufacturers_info mi ON( m.manufacturers_id = mi.manufacturers_id )
										  WHERE m.manufacturers_id = "' . (int)$sGetId . '"' );

				// Si no existe
				if( tep_db_num_rows( $aRecords ) == 0 )
				{
					$messageStack->addSession( 'success', TEXT_MANUFACTURERS_EDIT_KO, 'error' );
					tep_redirect( tep_href_link(  $sUrlPage ) );
				}

				while( $aRow = tep_db_fetch_array( $aRecords ) )
				{
					$aRecord['manufacturers_name'][$aRow['languages_id']] = $aRow['manufacturers_name'];
					$aRecord['orden'][$aRow['languages_id']] = $aRow['orden'];
					$aRecord['seo_title'][$aRow['languages_id']] = $aRow['seo_title'];
					$aRecord['seo_description'][$aRow['languages_id']] = $aRow['seo_description'];
					$aRecord['seo_text_landing_page'][$aRow['languages_id']] = $aRow['seo_text_landing_page'];
				}
			}

			// Insertar o actualizar
			if( $sPostAction == 'update' )
			{
				// Pasamos todos los post por tep_db_prepare_input
				array_walk( $_POST, function( $value, $key){ global $_POST; $_POST[$key] = tep_db_prepare_input( $_POST[$key] ); } );

				// Comprobamos que nos hayan enviado un título
				if (!array_key_exists( 'titulo', $_POST ) || (array_key_exists( 'titulo', $_POST ) && $_POST['titulo'] == '')) {
                    $aMessageError['titulo'] = $messageStack->show( [ 'text' => TEXT_MANUFACTURERS_TITLE_ERROR, 'class' => 'error' ] );
                }

				// Si no existe errores actualizamos/insertamos
				if( count( $aMessageError ) == 0 )
				{
					// Array principal de marca
					$aSql = [
						'manufacturers_name' => $_POST['titulo'],
						'orden' => $_POST['orden']
					];

					if( $sGetId != false )
					{
						$aSql['last_modified'] = date( 'Y-m-d H:i:s' );
						tep_db_perform( 'manufacturers', $aSql, 'update', 'manufacturers_id = "' . (int)$sGetId . '"' );

						// Recorremos idiomas
						foreach( $aLanguages as $aLanguage )
						{
							// Limpiamos html
							$_POST['seo_text_landing_page'][$aLanguage['id']] = strings::cleanHTML($_POST['seo_text_landing_page'][$aLanguage['id']]);

							tep_db_perform( 'manufacturers_info', [
								'seo_title' => $_POST['seo_title'][$aLanguage['id']],
								'seo_description' => $_POST['seo_description'][$aLanguage['id']],
								'seo_text_landing_page' => $_POST['seo_text_landing_page'][$aLanguage['id']],
							], 'update', 'languages_id = "' . $aLanguage['id'] . '" and manufacturers_id = "' . $sGetId . '"' );
						}
					}
					else
					{
						$aSql['date_added'] = date( 'Y-m-d H:i:s' );
						tep_db_perform( 'manufacturers', $aSql );
						$sGetId = tep_db_insert_id();

						// Recorremos idiomas
						foreach( $aLanguages as $aLanguage )
						{
							// Limpiamos html
							$_POST['seo_text_landing_page'][$aLanguage['id']] = strings::cleanHTML($_POST['seo_text_landing_page'][$aLanguage['id']]);

							tep_db_perform( 'manufacturers_info', [
								'seo_title' => $_POST['seo_title'][$aLanguage['id']],
								'seo_description' => $_POST['seo_description'][$aLanguage['id']],
								'seo_text_landing_page' => $_POST['seo_text_landing_page'][$aLanguage['id']],
								'languages_id' => $aLanguage['id'],
								'manufacturers_id' => $sGetId
							], 'insert', '' );
						}
					}

					// Imagenes marca
					$aPostImages = (isset( $_POST['br_image'] ) ? tep_db_prepare_input( $_POST['br_image'] ) : []);

					// Si contenemos imagen subimos
					if( count( $aPostImages ) > 0 )
					{
						foreach( $aPostImages as $sImage )
						{
							// Comprobamos si existe imagen si es asi eliminamos antes
							$aImagenes = glob( getcwd() . '/../images/fabricantes/' . $sGetId . '-*' );

							// Si existe eliminamos
							if( count( $aImagenes ) > 0 )
							{
								@unlink( $aImagenes[0] );
								@unlink( $aImagenes[1] );
							}

							// Subimos la imagen
							if( $sImage != 'eliminar' )
							{
								$sExtension = '.' . preg_replace( '/\;base64\,.+$|data\:|image\//i', '', $sImage );
								$sImage = preg_replace( '/^.+\,/i', '', $sImage );
								$sAux = $sGetId . '-' .  getSlug($_POST['titulo']) . $sExtension;
								file_put_contents( getcwd() . '/../images/fabricantes/' . $sAux, base64_decode( $sImage ) );
								tep_db_perform( 'manufacturers', ['manufacturers_image' => $sAux], 'update', 'manufacturers_id = "' . $sGetId . '"' );
							}
						}
					}

					// Mensaje
					$messageStack->addSession( 'success', ($sGetId != false ? TEXT_MANUFACTURERS_EDIT_OK : TEXT_MANUFACTURERS_NEW_OK), 'success' );

					// Redireccionamos
					tep_redirect( tep_href_link(  $sUrlPage ) );
				}
			}

			// Formulario
			$sHtml .= '<form method="post" id="saveform-send" action="' . tep_href_link( $sUrlPage, 'action=update' ) . '">';
				$sHtml .= '<div class="oeBox column a12 row ax" style="margin-bottom: 20px;">';
					$sHtml .= '<div class="oeWrpr">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa-shield"></i> ' . TEXT_MANUFACTURERS_CONFIGURE . ' </div>';
						$sHtml .= '<div class="oeCntd row ax xform xform-horizontal">';
							$sHtml .= $sGetId !== false ? '<input type="hidden" name="id" value="' . $sGetId . '" />' : '';
							$sHtml .= '<input type="submit" style="display: none;" />';

							$sHtml .= '<label for="titulo" class="column a01 tright">' . TEXT_MANUFACTURERS_TABLE_TITLE . ':</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= '<input type="text" name="titulo" id="titulo" value="' . (array_key_exists( 'manufacturers_name', $aRecord ) ? $aRecord['manufacturers_name'][$languages_id] : ($_POST['titulo'] ?? '')) . '"/>';
								$sHtml .= '<div class="DFhelp">' . TEXT_MANUFACTURERS_TITLE_HELP . '</div>';
								$sHtml .= array_key_exists( 'titulo', $aMessageError ) ? $aMessageError['titulo'] : '';
							$sHtml .= '</div>';

							$sHtml .= '<div class="xline xline-dashed"></div>';

							$sHtml .= '<label for="orden" class="column a01 tright">' . TEXT_MANUFACTURERS_TABLE_IMAGE . ':</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= '<a class="brand-boton-eliminar-imagen xbutton hv8 small rojo" href="javascript:void(0);" style="position: relative; z-index: 0; margin-right: 5px;" data-confirm="' . TEXT_MANUFACTURERS_DELETE_IMAGE_CONFIRM . '">' . TEXT_MANUFACTURERS_DELETE_IMAGE . '</a>';
								$sHtml .= '<a class="brand-boton-upload-imagen xbutton hv8 small verde" href="javascript:void(0);" style="position: relative; z-index: 0; margin-right: 15px;">' . TEXT_MANUFACTURERS_ADD_IMAGE . '</a>';

								$sHtml .= '<span class="brand-imagen">';
								if( $sGetId != false )
								{
									$aImagenes = glob( getcwd() . '/../images/fabricantes/' . $sGetId . '-*' );

									if (count( $aImagenes ) > 0) {
                                        $sHtml .= '<img src="' . str_replace( getcwd() . '/', '', $aImagenes[0] ) . '" />';
                                    }
								}
								$sHtml .= '</span>';

								$sHtml .= '<div class="DFhelp">' . TEXT_MANUFACTURERS_IMAGE_HELP . '</div>';
								$sHtml .= '<div style="visibility: hidden; width: 1px; height: 1px; display: none; opacity: 0;"><input class="brand-input-upload-imagen" name="brand-input-upload-imagen" type="file" accept="image/*" /></div>';
							$sHtml .= '</div>';

							$sHtml .= '<div class="xline xline-dashed"></div>';

							$sHtml .= '<label for="orden" class="column a01 tright">' . TEXT_MANUFACTURERS_TABLE_ORDER . ':</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= '<input type="text" name="orden" id="orden" value="' . (array_key_exists( 'orden', $aRecord ) ? $aRecord['orden'][$languages_id] : ($_POST['orden'] ?? '')) . '"/>';
								$sHtml .= '<div class="DFhelp">' . TEXT_MANUFACTURERS_ORDER_HELP . '</div>';
							$sHtml .= '</div>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';

					$sHtml .= '<div class="oeWrpr" style="margin-top: 20px;">';
						$sHtml .= '<div class="oeTitu"><i class="fa fa-eye"></i> Opciones SEO </div>';
						$sHtml .= '<div class="oeCntd row ax xform xform-horizontal">';

							foreach( $aLanguages as $aLanguage )
							{
								$sHtml .= '<div class="column a05" style="margin: 0 4%;">';
									$sHtml .= '<b style="display:block; margin-bottom: 7px;"><span style="position: relative; top: -5px; margin-right: 6px;">' . tep_image( DIR_WS_CATALOG_LANGUAGES . $aLanguage['directory'] . '/images/' . $aLanguage['image'], $aLanguage['name'], '', '', 'style="margin-top: 6px;"' ) . '</span>Preview SEO</b>';
									$sHtml .= '<div class="seo-mrcs" style="margin-bottom: 20px;">';
										$sHtml .= '<span class="titl" data-row="seo_title[' . $aLanguage['id'] . ']" data-id="' . $aLanguage['id'] . '" data-max="' . CARACTERES_SEO_TITLE . '">' . $aRecord['seo_title'][$aLanguage['id']] . '</span>';
										$sHtml .= '<span class="url">' . tep_catalog_href_link( TEXT_URL_EXAMPLE . '.php' ) . '</span>';
										$sHtml .= '<span class="dscp" data-row="seo_description[' . $aLanguage['id'] . ']" data-id="' . $aLanguage['id'] . '" data-max="' . CARACTERES_SEO_DESCRIPTION . '">' . $aRecord['seo_description'][$aLanguage['id']] . '</span>';
									$sHtml .= '</div>';
								$sHtml .= '</div>';
							}

							$sHtml .= '<div class="xline xline-dashed"></div>';

							$sHtml .= tools::getInputLanguages( 'seo_title', 'SEO title:', $aRecord['seo_title'], TEXT_MANUFACTURERS_SEO_TITLE_HELP );

							$sHtml .= '<div class="xline xline-dashed"></div>';

							$sHtml .= tools::getInputLanguages( 'seo_description', 'SEO description:', $aRecord['seo_description'], TEXT_MANUFACTURERS_SEO_DESCRIPTION_HELP, '', 10, false, true );

							$sHtml .= '<div class="xline xline-dashed"></div>';

							$sHtml .= tools::getInputLanguages( 'seo_text_landing_page', TEXT_MANUFACTURERS_SEO_LANDING, $aRecord['seo_text_landing_page'], TEXT_MANUFACTURERS_SEO_LANDING_HELP, '', 10, false );
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';
			$sHtml .= '</form>';
		break;

		default:
			// Variables
			$sSubtitle = TEXT_MANUFACTURERS_SUBTITLE;
			$aButtons[] = [ 'title' => TEXT_HEADING_NEW_MANUFACTURER, 'href' => tep_href_link( $sUrlPage, 'action=add_form' ), 'icon' => 'fa-plus' ];

			// Html para el boton masivo
			$sHtmlActionMasivo = '<label class="column afluid">' . TEXT_MANUFACTURERS_MASSIVE_APPLY_ACTION . ':&nbsp;&nbsp;</label>
			<div class="column afluid"><div class="drop masv xfselect">
				<div>' . TEXT_MANUFACTURERS_TABLE_ACTIONS . '</div>
				<ul class="down drch" style="width: 230px;">
					<li><a data-question="' . TEXT_HEADING_DELETE_MANUFACTURERS_CONFIRM . '" data-error="' . TEXT_HEADING_DELETE_MANUFACTURERS_ERROR . '" data-action="' . tep_href_link( $sUrlPage, 'action=delete' ) . '" href="javascript:void(0);" class="hv"><i class="fa fa-trash"></i>' . TEXT_HEADING_DELETE_MANUFACTURERS . '</a></li>
					<li><a data-question="' . TEXT_HEADING_DELETE_MANUFACTURERS_AND_PRODUCTS_CONFIRM . '" data-error="' . TEXT_HEADING_DELETE_MANUFACTURERS_ERROR . '" data-action="' . tep_href_link( $sUrlPage, 'action=delete&delete_products=1' ) . '" href="javascript:void(0);" class="hv"><i class="fa fa-trash"></i>' . TEXT_HEADING_DELETE_MANUFACTURERS_AND_PRODUCTS . '</a></li>
				</ul>
			</div></div>&nbsp; - &nbsp;';

			// Filtros
			$aFilter = (array_key_exists( 'filter', $_POST ) && is_array($_POST['filter']) ? $_POST['filter'] : []);
			$sWhere = '';

			// Limpiamos variables get filter
			array_walk( $aFilter, function( $value, $key){ global $aFilter; $aFilter[$key] = tep_db_prepare_input( $aFilter[$key] ); } );

			// Where
			if ($aFilter['search'] != '') {
                $sWhere .= 'where (LOWER(manufacturers_name) LIKE "%' . strtolower( (string) $aFilter['search'] ) . '%")';
            }

			// Order by
			$sOrderby = $sGetOrderby != '' ? $sGetOrderby . ' ' . $sGetSort : 'manufacturers_name asc';

			// Sql
			$sSql = 'SELECT m.manufacturers_id, m.manufacturers_name, m.manufacturers_status, m.date_added, m.last_modified, m.orden, count( p.products_id ) as products_count
					 FROM manufacturers m
					 LEFT JOIN products p ON( m.manufacturers_id = p.manufacturers_id )
					 ' . $sWhere . ' GROUP BY m.manufacturers_id ORDER BY ' . $sOrderby;

			// Le quitamos los tabuladores y saltos de linea para que splitpageesult funcione con el SQL
			$sSql = preg_replace( '/[\r\n\t]+/', ' ', $sSql );

			// Sql para el count
			$sSqlCount = 'SELECT COUNT(table_aux.manufacturers_id) as total FROM (' . $sSql . ') as table_aux';

			// Datos y paginacion
			$aDatoSplit = new splitPageResults( $sGetPage, MAX_DISPLAY_SEARCH_RESULTS, $sSql, $nAux, $sSqlCount );
			$aDatos = tep_db_query( $sSql );

			// Mensajes comprobamos si tenemos datos
			if( tep_db_num_rows( $aDatos ) <= 0 )
			{
				if ($sWhere !== '') {
                    $sHtml .= $messageStack->show( [ 'text' => TEXT_MANUFACTURERS_SEARCH_FILTER_NO_RESULT, 'class' => 'warning' ] );
                } else {
                    $sHtml .= $messageStack->show( [ 'text' => TEXT_MANUFACTURERS_SEARCH_NO_RESULT, 'class' => 'warning' ] );
                }
			}

			// Tabla
			$sHtml .= '<div class="oeBox oeTable column a12 row ax">';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-eye"></i> ' . TEXT_MANUFACTURERS_SUBTITLE . '</div>';
					$sHtml .= '<form method="post" action="' . tep_href_link( $sUrlPage ) . '" class="oeCntd row ax">';
						$sHtml .= '<div class="oeBoxFltr column a12 ax row">';
							$sHtml .= '<div class="column a09 row ax amiddle input-search">';
								$sHtml .= '<label class="column">' . TEXT_MANUFACTURERS_SEARCH . ': </label> <div class="column"><input type="text" name="filter[search]" placeholder="' . TEXT_MANUFACTURERS_SEARCH_PLACEHOLDER . '" value="' . $aFilter['search'] . '" autofocus/> <i class="fa fa-search"></i></div>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="column a03 tright">';
								$sHtml .= ($sWhere !== '' ? '<a title="' . TEXT_MANUFACTURERS_SEARCH_REMOVE_FILTER . '" href="' . tep_href_link( $sUrlPage, tep_get_all_get_params( [ 'filter' ] ) ) . '" class="xbutton hv9 rojo small"><i class="fa fa fa-close"></i></a> ' : '');
							$sHtml .= '</div>';
						$sHtml .= '</div>';

						$sHtml .= '<table class="xform">';
							$sHtml .= '<thead>';
								$sHtml .= '<tr>';
									$sHtml .= '<th width="17" class="chck"><input type="checkbox" id="all_check"/><label for="all_check"><span></span></label></th>';
									$sHtml .= '<th width="100">' . TEXT_MANUFACTURERS_TABLE_IMAGE . '</th>';
									$sHtml .= '<th class="sort" width="150">' . tableSetSort( 'manufacturers_name', TEXT_MANUFACTURERS_TABLE_TITLE ) . '</th>';
									$sHtml .= '<th class="sort">' . tableSetSort( 'date_added', TEXT_MANUFACTURERS_TABLE_CREATED_AT ) . '</th>';
									$sHtml .= '<th class="sort">' . tableSetSort( 'last_modified', TEXT_MANUFACTURERS_TABLE_MODIFIED_AT ) . '</th>';
									$sHtml .= '<th class="sort">' . tableSetSort( 'orden', TEXT_MANUFACTURERS_TABLE_ORDER ) . '</th>';
									$sHtml .= '<th class="sort">' . tableSetSort( 'products_count', TEXT_MANUFACTURERS_TABLE_PRODUCTS ) . '</th>';
									$sHtml .= '<th style="text-align: center;">' . TEXT_MANUFACTURERS_TABLE_STATUS . '</th>';
									$sHtml .= '<th width="125">' . TEXT_MANUFACTURERS_TABLE_ACTIONS . '</th>';
								$sHtml .= '</tr>';
							$sHtml .= '</thead>';
							$sHtml .= '<tbody>';

								while( $aDato = tep_db_fetch_array( $aDatos ) )
								{
									// Fila
									$sHtml .= '<tr data-dblclick="' . tep_href_link( $sUrlPage, 'action=add_form&id=' . $aDato['manufacturers_id'] ) . '">';
										$sHtml .= '<td class="chck" align="center"><input type="checkbox" id="id_' . $aDato['manufacturers_id'] . '" name="id[]" value="' . $aDato['manufacturers_id'] . '"/><label for="id_' . $aDato['manufacturers_id'] . '"><span></span></label></td>';

										$aImagenes = glob( getcwd() . '/../images/fabricantes/' . $aDato['manufacturers_id'] . '-*' );
										$sImagen = '';
										if (count( $aImagenes ) > 0) {
                                            $sImagen = tep_image( str_replace( getcwd() . '/', '', $aImagenes[0] ), $aDato['manufacturers_name'], 90, 90 );
                                        }

										$sHtml .= '<td>' . $sImagen . '</td>';

										$sHtml .= '<td>' . $aDato['manufacturers_name'] . '</td>';

										$sHtml .= '<td>' . ($aDato['date_added'] != '' && $aDato['date_added'] != '0000-00-00 00:00:00' ? date( 'd-m-Y', strtotime( (string) $aDato['date_added'] ) ) : '-') . '</td>';
										$sHtml .= '<td>' . ($aDato['last_modified'] != '' && $aDato['last_modified'] != '0000-00-00 00:00:00' ? date( 'd-m-Y', strtotime( (string) $aDato['last_modified'] ) ) : '-') . '</td>';
										$sHtml .= '<td>' . $aDato['orden'] . '</td>';
										$sHtml .= '<td>' . $aDato['products_count'] . '</td>';

										$sHtml .= '<td align="center">';
											if ($aDato['manufacturers_status'] == '1') {
                                                $sHtml .= tep_image(DIR_WS_IMAGES . 'icon_status_green.png', IMAGE_ICON_STATUS_GREEN, 10, 10) . '&nbsp;&nbsp;<a href="' . tep_href_link($sUrlPage, 'action=setflag&flag=0&id=' . $aDato['manufacturers_id'], 'NONSSL') . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_red_light.png', IMAGE_ICON_STATUS_RED_LIGHT, 10, 10) . '</a>';
                                            } else {
                                                $sHtml .= '<a href="' . tep_href_link($sUrlPage, 'action=setflag&flag=1&id=' . $aDato['manufacturers_id'], 'NONSSL') . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_green_light.png', IMAGE_ICON_STATUS_GREEN_LIGHT, 10, 10) . '</a>&nbsp;&nbsp;' . tep_image(DIR_WS_IMAGES . 'icon_status_red.png', IMAGE_ICON_STATUS_RED, 10, 10);
                                            }
										$sHtml .= '</td>';
										$sHtml .= '<td>';
											$sHtml .= '<div class="drop xfselect">';
												$sHtml .= '<div>' . TEXT_MANUFACTURERS_TABLE_ACTIONS . '</div>';
												$sHtml .= '<ul class="down down-dngt" style="width: 217px;margin-left: -117px;">';
													$sHtml .= '<li><a href="' . tep_href_link( $sUrlPage, 'action=add_form&id=' . $aDato['manufacturers_id'] ) . '" class="hv"><i class="fa fa-pencil"></i>' . TEXT_HEADING_EDIT_MANUFACTURER . '</a></li>';
													$sHtml .= '<li><a data-confirm="' . TEXT_HEADING_DELETE_MANUFACTURERS_CONFIRM . '" data-error="' . TEXT_HEADING_DELETE_MANUFACTURERS_ERROR . '" href="' . tep_href_link( $sUrlPage, 'action=delete&id=' . $aDato['manufacturers_id'] ) . '" class="hv"><i class="fa fa-trash"></i>' . TEXT_HEADING_DELETE_MANUFACTURER . '</a></li>';
													$sHtml .= '<li><a data-confirm="' . TEXT_HEADING_DELETE_MANUFACTURERS_AND_PRODUCTS_CONFIRM . '" data-error="' . TEXT_HEADING_DELETE_MANUFACTURERS_ERROR . '" href="' . tep_href_link( $sUrlPage, 'action=delete&delete_products=1&id=' . $aDato['manufacturers_id'] ) . '" class="hv"><i class="fa fa-trash"></i>' . TEXT_HEADING_DELETE_MANUFACTURER_AND_PRODUCTS . '</a></li>';
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
		echo '<div class="oeTitu column logo afixed" style="padding-left: 59px;"><b><i class="fa fa-clone"></i> ' . $sTitle . '</b>' . ($sSubtitle !== '' && $sSubtitle !== '0' ? '<small>' . $sSubtitle . '</small>' : '') . '</div>';
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
