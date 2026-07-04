<?php
	// Tools
	use util\tools as tools;
	use util\date as date;

	// Si nos mandan a instalar cambiamos el modulo por login para que forbidden no salte y podamos instalarlo
	if( array_key_exists( 'action', $_GET ) && $_GET['action'] == 'install' )
	{
		// FIX bypass sin auth: PHP_SELF='index.php' (FILENAME_DEFAULT) hace que tep_admin_check_login
		// salte SOLO el ACL de pagina. NO tocar SCRIPT_FILENAME: asi el login SIGUE exigiendose.
		$_SERVER['PHP_SELF'] = 'index.php';
	}

	// Incluimos el application_top
	require( 'includes/application_top.php' );
	include( 'includes/classes/currencies.php' );
	include( 'includes/modules/banners_destacados/includes/functions/functions.php' );

	// Variables
	$sUrlPage =  'banners_destacados.php';
	$sTitle = HEADING_TITLE;
	$sSubtitle = '';
	$aButtons = [];
	$sPostAction = array_key_exists( 'action', $_POST ) ? tep_db_input( $_POST['action'] ) : (array_key_exists( 'action', $_GET ) ? tep_db_input( $_GET['action'] ) : false);

	$sGetPage = (isset( $_GET['page'] ) ? tep_db_prepare_input( $_GET['page'] ) : 1);
	$sGetOrderby = (isset( $_GET['orderby'] ) ? tep_db_prepare_input( $_GET['orderby'] ) : 1);
	$sGetSort = (isset( $_GET['sort'] ) ? tep_db_prepare_input( $_GET['sort'] ) : 1);
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

			$sHtml = tools::parsedown( DIR_WS_MODULES . '/banners_destacados/readme.txt' );
		break;

		case 'install':
			// Insertamos admin file
			tools::insertAdminFiles( $sUrlPage, 1 );

			// Insertamos el grupo de configuracion
			$aConfigGroup = tools::insertConfigurationGroup( 'Banners destacados', 0 );

			// Insertamos la configuracion global
			tools::insertConfiguration( 'Activar banners destacados', 'BANNERS_DESTACADOS_ACTIVE', 'true', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Banners responsive', 'BANNERS_DESTACADOS_RESPONSIVE', 'false', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Web - Ancho máximo de imagen', 'BANNERS_DESTACADOS_WIDTH', '1021', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Tablet - Ancho máximo de imagen', 'BANNERS_DESTACADOS_WIDTH_TABLET', '748', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Móvil - Ancho máximo de imagen', 'BANNERS_DESTACADOS_WIDTH_MOBILE', '310', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Puntos en los Banners', 'BANNERS_DESTACADOS_PUNTOS', 'false', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Alto del punto', 'BANNERS_DESTACADOS_PUNTOS_HEIGHT', '58', '', $aConfigGroup->records['configuration_group_id'] );
			tools::insertConfiguration( 'Ancho del punto', 'BANNERS_DESTACADOS_PUNTOS_WIDTH', '182', '', $aConfigGroup->records['configuration_group_id'] );

			// Reset cache
			tools::createCacheFile();

			// Mensajes
			$messageStack->addSession( 'success', 'El módulo <em>' . $sTitle . '</em> se ha instalado correctamente.', 'success' );

			// Redireccionamos
			tep_redirect( $sUrlPage . '?action=readme' );
		break;

		case 'autocomplete':
			// Variables
			$currencies = new currencies();
			$sHtml = '';

			// Buscamos por producto
			$sSql = getSqlSearchProducts_bd( strtolower( array_key_exists( 'term', $_POST ) ? tep_db_prepare_input( $_POST['term'] ) : '' ) );

			// Lanzamos la consulta
			$aDatos = tep_db_query( $sSql );

			// Si encontramos productos
			if( tep_db_num_rows( $aDatos ) > 0 )
			{
				while( $aDato = tep_db_fetch_array( $aDatos ) )
				{
					$sDataLang = '';

					// Obtenemos todos los nombres del producto por idiomas
					$aLangProducts = tep_db_query( 'SELECT products_name, language_id FROM products_description WHERE language_id != ' . $languages_id . ' AND products_id = ' . $aDato['products_id'] );

					// Si tenemos nombre por idiomas
					if( tep_db_num_rows( $aLangProducts ) > 0 )
					{
						while( $aLangProduct = tep_db_fetch_array( $aLangProducts ) )
							$sDataLang .= ' data-lang' . $aLangProduct['language_id'] . '="' . str_replace( '"', '&#34;', $aLangProduct['products_name'] ) . '"';
					}

					$sHtml .= '<li data-id="' . $aDato['products_id'] . '"' . $sDataLang . ' data-price="' . str_replace( '.', '', $currencies->display_price( $aDato['products_price'], tep_get_tax_rate( $aDato['products_tax_class_id'] ) ) ) . '" >' . $aDato['products_name'] . '</li>';
				}
			}

			// Pintamos
			die( $sHtml !== '' ? '<ul>' . $sHtml . '</ul>' : '' );

		case 'setflag':
            // Variables
			$nId = tep_db_prepare_input( $_GET['id'] );
			$nFlag = tep_db_prepare_input( $_GET['flag'] );

			tep_db_query("update " . TABLE_BANNERS_DESTACADOS . " set estado = '" . (int)$nFlag . "' where banner_destacados_id = '" . $nId . "'");

            tep_redirect( $_SERVER['HTTP_REFERER'] );
        break;

		case 'delete':
			// Variables
			$aGetId = tep_db_prepare_input( $_GET['id'] );
			$aPostId = tep_db_prepare_input( $_POST['id'] );
			$sIds = '';

			// Si nos envian por get creamos el array
			if ($aGetId != '') {
                $aPostId = [ $aGetId ];
            }

			// Recorremos los id
			foreach( $aPostId as $sId )
			{
				$sIds .= $sId . ',';

				$aImagenes = glob( getcwd() . '/../images/banners_destacados/' . $sId . '_*' );
				$aImagenesThumb = glob( getcwd() . '/../images/banners_destacados/thumbnails/' . $sId . '_*' );

				foreach( $aImagenes as $sFile )
					@unlink( $sFile );

				foreach( $aImagenesThumb as $sFile )
					@unlink( $sFile );
			}

			// Si tenemos id eliminamos
			if( $sIds !== '' )
			{
				tep_db_query( 'DELETE FROM banners_destacados WHERE banner_destacados_id IN(' . substr( $sIds, 0, -1 ) . ')' );
			}

			// Redireccionamos
			$messageStack->addSession( 'success', BANNERS_DESTACADOS_DELETES_SUCCESS, 'success' );
			tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;

		case 'update':
		case 'add_form':
			// Variables
			$aLanguages = tep_get_languages();
			$currencies = new currencies();
			$sGetId = array_key_exists( 'id', $_POST ) ? tep_db_input( $_POST['id'] ) : (array_key_exists( 'id', $_GET ) ? tep_db_input( $_GET['id'] ) : false);
			$aMessageError = [];
			$sSubtitle = ($sGetId != '' ? BANNERS_DESTACADOS_TEXT_EDITED : BANNERS_DESTACADOS_TEXT_ADD) . ' ' . BANNERS_DESTACADOS_TITLE_ADD_EDIT;
			$aButtons = [
				[ 'title' => TEXT_BACK, 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-arrow-left' ],
				[ 'title' => TEXT_SAVE, 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' ]
			];
			$aRecord = [];

			// Si estamos editando
			if( $sGetId != false )
			{
				// Obtenemos el registro
				$aRecord = pharaonix_queryOne( 'SELECT * FROM banners_destacados WHERE banner_destacados_id = "' . (int)$sGetId . '"' );

				// Si no existe
				if( $aRecord->num_rows == 0 )
				{
					$messageStack->addSession( 'success', 'El registro que intentas editar no existe', 'error' );
					tep_redirect( tep_href_link(  $sUrlPage ) );
				}

				// Registro
				$aRecord = $aRecord->records;
			}

			// Insertar o actualizar
			if( $sPostAction == 'update' )
			{
				// Pasamos todos los post por tep_db_prepare_input
				array_walk( $_POST, function( $value, $key){ global $_POST; $_POST[$key] = tep_db_prepare_input( $_POST[$key] ); } );

				// Si no existe errores actualizamos/insertamos
				if( count( $aMessageError ) === 0 )
				{
					if ($_POST['fecha_inicio'] != '') {
                        $_POST['fecha_inicio'] = date::changeDate( $_POST['fecha_inicio'], 'espanol', 'y/m/d' );
                    }
					if ($_POST['fecha_fin'] != '') {
                        $_POST['fecha_fin'] = date::changeDate( $_POST['fecha_fin'], 'espanol', 'y/m/d' );
                    }

					$aSql = [
						'titulo' => $_POST['titulo'],
						'enlace' => $_POST['enlace'],
						'orden' => $_POST['orden'],
						'date_start' => ($_POST['fecha_inicio'] == '' ? '' : $_POST['fecha_inicio']),
						'date_end' => ($_POST['fecha_fin'] == '' ? '' : $_POST['fecha_fin'])
					];

					if ($sGetId != false) {
                        tep_db_perform( 'banners_destacados', $aSql, 'update', 'banner_destacados_id = "' . (int)$sGetId . '"' );
                    } else
					{
						tep_db_perform( 'banners_destacados', $aSql );
						$sGetId = tep_db_insert_id();
					}

					// Imagenes banners
					$aPostBpImages = (isset( $_POST['bp_image'] ) ? tep_db_prepare_input( $_POST['bp_image'] ) : []);

					// Si contenemos imagen subimos
					if( count( $aPostBpImages ) > 0 )
					{
						foreach( $aPostBpImages as $sResponsive => $aPostBpImage )
						{
							foreach( $aPostBpImage as $nIdLang => $sImage )
							{
								// Comprobamos si existe imagen si es asi eliminamos antes
								$aImagenes = glob( getcwd() . '/../images/banners_destacados/' . $sGetId . '_' . $nIdLang . '_' . $sResponsive . '*' );

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
									$sAux = $sGetId . '_' . $nIdLang . '_' . $sResponsive;
									$sImagenFilename = DIR_FS_CATALOG_IMAGES . '/banners_destacados/' . $sAux . $sExtension;
									file_put_contents( $sImagenFilename, base64_decode( $sImage ) );
									// Convertimos la imagen subida a WebP
									convertImageToWebP($sImagenFilename, DIR_FS_CATALOG_IMAGES . '/banners_destacados/' . $sAux . '.webp' );
								}
							}
						}
					}

					// Eliminamos todos los puntos del banner
					tep_db_query( 'delete from banners_points where banner_destacados_id = ' . (int)$sGetId );

					// Si existen puntos
					if( array_key_exists( 'bp_x', $_POST ) )
					{
						// Recorremos los puntos para insertarlos
						foreach( $_POST['bp_x'] as $key => $value )
						{
							$aSql = [
								'banner_destacados_id' => $sGetId,
								'products_id' => $_POST['bp_products_id'][$key],
								'responsive' => $_POST['bp_responsive'][$key],
								'titulo' => json_encode( $_POST['bp_titulo'][$key], JSON_UNESCAPED_UNICODE ),
								'precio' => $_POST['bp_precio'][$key],
								'enlace' => $_POST['bp_enlace'][$key],
								'x' => $_POST['bp_x'][$key],
								'y' => $_POST['bp_y'][$key]
							];

							tep_db_perform( 'banners_points', $aSql );
						}
					}

					// Mensaje
					$messageStack->addSession('success', ($sGetId != false ? BANNERS_DESTACADOS_EDIT_SUCCESS : BANNERS_DESTACADOS_INSERT_SUCCESS), 'success');

					// Redireccionamos
					tep_redirect( tep_href_link(  $sUrlPage ) );
				}
			}

			// Javascript y css
			echo '  <script type="text/javascript">
						var pAlto = ' . BANNERS_DESTACADOS_PUNTOS_HEIGHT . ';
						var pAncho = ' . BANNERS_DESTACADOS_PUNTOS_WIDTH . ';
						var aLanguages = ' . json_encode( $aLanguages ) . ';
					</script>';

			$aJs = [ 'includes/modules/banners_destacados/js/index.js?v=' . @filemtime( __DIR__ . '/js/index.js' ) ];
			$aStyle = [ 'includes/modules/banners_destacados/css/style.css?v=' . @filemtime( __DIR__ . '/css/style.css' ) ];

			// Formulario
			$sHtml .= '<div class="oeBox column a12 row ax">';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-shield"></i> ' . BANNERS_DESTACADOS_TEXT_CONFIGURATION . ' </div>';
					$sHtml .= '<form method="post" id="saveform-send" action="' . tep_href_link( $sUrlPage, 'action=update' ) . '" class="oeCntd row ax xform xform-horizontal">';
						$sHtml .= $sGetId !== false ? '<input type="hidden" name="id" value="' . $sGetId . '" />' : '';
						$sHtml .= '<input type="submit" style="display: none;" />';

						$sHtml .= '<label for="titulo" class="column a02 tright">' . BANNERS_DESTACADOS_TABLE_TITLE . ':</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="text" name="titulo" id="titulo" value="' . (array_key_exists( 'titulo', $aRecord ) ? $aRecord['titulo'] : '') . '"/>';
							$sHtml .= '<div class="DFhelp">' . BANNERS_DESTACADOS_TABLE_TITLE_HELP . '.</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="enlace" class="column a02 tright">' . BANNERS_DESTACADOS_TABLE_LINK . ':</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="text" name="enlace" id="enlace" value="' . (array_key_exists( 'enlace', $aRecord ) ? $aRecord['enlace'] : '') . '"/>';
							$sHtml .= '<div class="DFhelp">' . BANNERS_DESTACADOS_TABLE_LINK_HELP . '</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="fecha_inicio" class="column a02 tright">' . BANNERS_DESTACADOS_TABLE_START_DATE . ':</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="text" class="form-datetime-simple" readonly="readonly" id="fecha_inicio" name="fecha_inicio" autocomplete="off" data-autoupdate="true" value="' . (array_key_exists( 'date_start', $aRecord ) && $aRecord['date_start'] != '0000-00-00 00:00:00' && $aRecord['date_start'] != '' ? date::changeDate( $aRecord['date_start'], 'standar', 'd-m-y' ) : '') . '">';
							$sHtml .= '<div class="DFhelp">' . BANNERS_DESTACADOS_TABLE_START_DATE_HELP . '</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="fecha_fin" class="column a02 tright">' . BANNERS_DESTACADOS_TABLE_END_DATE . ':</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="text" class="form-datetime-simple" readonly="readonly" id="fecha_fin" name="fecha_fin" autocomplete="off" data-autoupdate="true" value="' . (array_key_exists( 'date_end', $aRecord ) && $aRecord['date_end'] != '0000-00-00 00:00:00' && $aRecord['date_end'] != '' ? date::changeDate( $aRecord['date_end'], 'standar', 'd-m-y' ) : '') . '">';
							$sHtml .= '<div class="DFhelp">' . BANNERS_DESTACADOS_TABLE_END_DATE_HELP . '</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="orden" class="column a02 tright">' . BANNERS_DESTACADOS_TABLE_ORDER . ':</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="text" name="orden" id="orden" value="' . (array_key_exists( 'orden', $aRecord ) ? $aRecord['orden'] : '') . '"/>';
							$sHtml .= '<div class="DFhelp">' . BANNERS_DESTACADOS_TABLE_ORDER_HELP . '</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						// Imagenes por idiomas
						$sHtmlPull = '';
						$sHtmlFake = '';
						$sHtmlInputWeb = '';
						$sHtmlInputTablet = '';
						$sHtmlInputMovil = '';
						$sHtmlInput = '';

						// Recorremos idiomas
						foreach( $aLanguages as $aLanguage )
						{
							$sHtmlLanguage = tep_image( DIR_WS_CATALOG_LANGUAGES . $aLanguage['directory'] . '/images/' . $aLanguage['image'], $aLanguage['name'], '', '', 'style="margin-top: 0px;width: 17px;margin-right: 3px;position: relative;top: -2px;"' ) . ' ' . $aLanguage['name'];

							if ($sHtmlFake === '') {
                                $sHtmlFake = '<div>' . $sHtmlLanguage . '</div>';
                            }

							$sHtmlPull .= '<li><a data-id="' . $aLanguage['id'] . '" href="javascript:void(0);" class="hv">' . $sHtmlLanguage . '</a></li>';

							$sHtmlInputWeb .= '<div data-id="' . $aLanguage['id'] . '" class="column image-lang" style="display: ' . ($aLanguage['id'] == $languages_id ? 'block' : 'none' ) . '">';
								if( $sGetId != false )
								{
									$sImgWeb = getImagenBannerDestacado($sGetId, $aLanguage['id'], '', false);
									if ($sImgWeb) {
                                        $sHtmlInputWeb .= '<img src="' . $sImgWeb . '" />';
                                    }
								}
							$sHtmlInputWeb .= '</div>';

							$sHtmlInputTablet .= '<div data-id="' . $aLanguage['id'] . '" class="column image-lang" style="display: ' . ($aLanguage['id'] == $languages_id ? 'block' : 'none' ) . '">';
								if( $sGetId != false )
								{
									$sImgTablet = getImagenBannerDestacado($sGetId, $aLanguage['id'], 'tablet', false);
									if (!$sImgTablet) $sImgTablet = getImagenBannerDestacado($sGetId, $aLanguage['id'], 't', false);
									if ($sImgTablet) {
                                        $sHtmlInputTablet .= '<img src="' . $sImgTablet . '" />';
                                    }
								}
							$sHtmlInputTablet .= '</div>';

							$sHtmlInputMovil .= '<div data-id="' . $aLanguage['id'] . '" class="column image-lang" style="display: ' . ($aLanguage['id'] == $languages_id ? 'block' : 'none' ) . '">';
								if( $sGetId != false )
								{
									$sImgMovil = getImagenBannerDestacado($sGetId, $aLanguage['id'], 'movil', false);
									if (!$sImgMovil) $sImgMovil = getImagenBannerDestacado($sGetId, $aLanguage['id'], 'm', false);
									if ($sImgMovil) {
                                        $sHtmlInputMovil .= '<img src="' . $sImgMovil . '" />';
                                    }
								}
							$sHtmlInputMovil .= '</div>';

							$sHtmlInput .= '<input data-id="' . $aLanguage['id'] . '" style="display: ' . ($sHtmlInput === '' ? 'block' : 'none' ) . '" class="pnt-ttle column input-language" type="text" name="titulo[' . $aLanguage['id'] . ']" value=""/>';
						}

						$sHtml .= '<label for="orden" class="column a02 tright">' . BANNERS_DESTACADOS_IMAGE_MAIN . ':</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<a class="banner-boton-eliminar-imagen xbutton hv8 small rojo" data-confirm="' . BANNERS_DESTACADOS_DELETE_IMAGE_CONFIRM . '" href="javascript:void(0);" style="position: relative; z-index: 0; margin-right: 5px;">' . BANNERS_DESTACADOS_DELETE_IMAGE . '</a>';
							$sHtml .= '<a class="banner-boton-upload-imagen xbutton hv8 small verde" href="javascript:void(0);" style="position: relative; z-index: 0; margin-right: 15px;">' . BANNERS_DESTACADOS_ADD_IMAGE . '</a>';
							if (BANNERS_DESTACADOS_PUNTOS == 'true') {
                                $sHtml .= '<span data-resp="web" class="addpoint xbutton small hv8 small verde-turquesa">' . BANNERS_DESTACADOS_ADD_POINT . '</span>';
                            }

							$sHtml .= '<div id="dpl-img" data-value-update="true" class="drop xfselect" style="margin-top: 10px; width: 328px">';
								$sHtml .= $sHtmlFake;

								$sHtml .= '<ul class="down">';
									$sHtml .= $sHtmlPull;
								$sHtml .= '</ul>';
							$sHtml .= '</div>';

							$sHtml .= '<div class="DFhelp">' . BANNERS_DESTACADOS_IMAGE_MAIN_HELP . ' ' . BANNERS_DESTACADOS_WIDTH . 'px.</div>';
							$sHtml .= '<div style="visibility: hidden; width: 1px; height: 1px; display: none; opacity: 0;"><input class="banner-input-upload-imagen" data-width="' . BANNERS_DESTACADOS_WIDTH . '" name="banner-input-upload-imagen" type="file" accept="image/*" /></div>';

							$sHtml .= '<div class="xline xline-dashed"></div>';

							$sHtml .= '<div class="banner-imagen vrsweb" style="position: relative;' . (BANNERS_DESTACADOS_PUNTOS == 'true' ? ' width: ' . BANNERS_DESTACADOS_WIDTH . 'px;' : '') . ' float: none; overflow:hidden;" class="grid12">';
								$sHtml .= '<div class="imge">';
										// Obtenemos los puntos del banner
										$sPuntosImagen = '';

										if( $sGetId != false )
										{
											$aDatos = tep_db_query( 'select bp.id_point, bp.responsive, bp.banner_destacados_id, bp.products_id, bp.x, bp.y, bp.titulo, bp.precio, bp.enlace, pd.products_name, p.products_price
																	from banners_points bp
																	left join products p on (p.products_id = bp.products_id)
																	left join products_description pd on (pd.products_id = bp.products_id and pd.language_id = ' . $languages_id . ')
																	where bp.responsive = "web" AND bp.banner_destacados_id = ' . $sGetId );

											$nCont = 1;
											while( $aDato = tep_db_fetch_array( $aDatos ) )
											{
												// Idiomas
												$aDato['titulo'] = json_decode( (string) $aDato['titulo'], true );

												$sPuntosImagen .= '<div style="left: ' . $aDato['x'] . 'px; top: ' . $aDato['y'] . 'px; height: ' . BANNERS_DESTACADOS_PUNTOS_HEIGHT . 'px; width: ' . BANNERS_DESTACADOS_PUNTOS_WIDTH . 'px;" class="pnto" data-id="' . $nCont . '">
																		<span>' . $aDato['titulo'][$languages_id] . '</span>
																		<span class="prco">' . $currencies->display_price( $aDato['precio'], 0 ) . '</span>
																		<a data-id="' . $nCont . '" data-resp="web" href="javascript:void(0);" class="pnt-pls">+</a>
																		<div class="bgc"></div>

																		<input type="hidden" name="bp_products_id[' . ($nCont) . ']" value="' . $aDato['products_id'] . '" />
																		<input type="hidden" name="bp_responsive[' . ($nCont) . ']" value="' . $aDato['responsive'] . '" />
																		<input type="hidden" name="bp_x[' . ($nCont) . ']" value="' . $aDato['x'] . '" />
																		<input type="hidden" name="bp_y[' . ($nCont) . ']" value="' . $aDato['y'] . '" />
																		<input type="hidden" name="bp_precio[' . ($nCont) . ']" value="' . $aDato['precio'] . '" />
																		<input type="hidden" name="bp_enlace[' . ($nCont) . ']" value="' . $aDato['enlace'] . '" />';

																		foreach( $aDato['titulo'] as $nLang => $sTitulo )
																			$sPuntosImagen .= '<input type="hidden" name="bp_titulo[' . ($nCont) . '][' . $nLang . ']" value="' . str_replace( '"', '&#34;', $aDato['titulo'][$nLang] ) . '" />';
												$sPuntosImagen .= '</div>';

												$nCont++;
											}
										}

										$sHtml .= $sHtmlInputWeb;
								$sHtml .= '</div>';

								$sHtml .= $sPuntosImagen;
							$sHtml .= '</div>';
						$sHtml .= '</div>';

						if( BANNERS_DESTACADOS_RESPONSIVE == 'true' )
						{
							$sHtml .= '<div class="xline xline-dashed"></div>';

							$sHtml .= '<label for="orden" class="column a02 tright">' . BANNERS_DESTACADOS_IMAGE_TABLET . ':</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= '<a class="banner-boton-eliminar-imagen xbutton hv8 small rojo" data-confirm="' . BANNERS_DESTACADOS_DELETE_IMAGE_CONFIRM . '" href="javascript:void(0);" style="position: relative; z-index: 0; margin-right: 5px;">' . BANNERS_DESTACADOS_DELETE_IMAGE . '</a>';
								$sHtml .= '<a class="banner-boton-upload-imagen xbutton hv8 small verde" href="javascript:void(0);" style="position: relative; z-index: 0; margin-right: 15px;">' . BANNERS_DESTACADOS_ADD_IMAGE . '</a>';

								if (BANNERS_DESTACADOS_PUNTOS == 'true') {
                                    $sHtml .= '<span data-resp="tablet" class="addpoint xbutton small hv8 small verde-turquesa">' . BANNERS_DESTACADOS_ADD_POINT . '</span>';
                                }

								$sHtml .= '<div class="DFhelp">' . BANNERS_DESTACADOS_IMAGE_TABLET_HELP . ' ' . BANNERS_DESTACADOS_WIDTH_TABLET . 'px.</div>';
								$sHtml .= '<div style="visibility: hidden; width: 1px; height: 1px; display: none; opacity: 0;"><input class="banner-input-upload-imagen" data-width="' . BANNERS_DESTACADOS_WIDTH_TABLET . '" name="banner-input-upload-imagen" type="file" accept="image/*" /></div>';

								$sHtml .= '<div class="xline xline-dashed"></div>';

								$sHtml .= '<div class="banner-imagen vrstablet" style="position: relative; width: ' . BANNERS_DESTACADOS_WIDTH_TABLET . 'px; float: none; overflow:hidden;" class="grid12">';
									$sHtml .= '<div class="imge">';
										// Obtenemos los puntos del banner
										$sPuntosImagen = '';

										if( $sGetId != false )
										{
											$aDatos = tep_db_query( 'select bp.id_point, bp.responsive, bp.banner_destacados_id, bp.products_id, bp.x, bp.y, bp.titulo, bp.precio, bp.enlace, pd.products_name, p.products_price
																	 from banners_points bp
																	 left join products p on (p.products_id = bp.products_id)
																	 left join products_description pd on (pd.products_id = bp.products_id and pd.language_id = ' . $languages_id . ')
																	 where bp.responsive = "tablet" AND bp.banner_destacados_id = ' . $sGetId );

											// $nCont = 1;
											while( $aDato = tep_db_fetch_array( $aDatos ) )
											{
												// Idiomas
												$aDato['titulo'] = json_decode( (string) $aDato['titulo'], true );

												$sPuntosImagen .= '<div style="left: ' . $aDato['x'] . 'px; top: ' . $aDato['y'] . 'px; height: ' . BANNERS_DESTACADOS_PUNTOS_HEIGHT . 'px; width: ' . BANNERS_DESTACADOS_PUNTOS_WIDTH . 'px;" class="pnto" data-id="' . $nCont . '">
																		<span>' . $aDato['titulo'][$languages_id] . '</span>
																		<span class="prco">' . $currencies->display_price( $aDato['precio'], 0 ) . '</span>
																		<a data-id="' . $nCont . '" data-resp="tablet" href="javascript:void(0);" class="pnt-pls">+</a>
																		<div class="bgc"></div>

																		<input type="hidden" name="bp_products_id[' . ($nCont) . ']" value="' . $aDato['products_id'] . '" />
																		<input type="hidden" name="bp_responsive[' . ($nCont) . ']" value="' . $aDato['responsive'] . '" />
																		<input type="hidden" name="bp_x[' . ($nCont) . ']" value="' . $aDato['x'] . '" />
																		<input type="hidden" name="bp_y[' . ($nCont) . ']" value="' . $aDato['y'] . '" />
																		<input type="hidden" name="bp_precio[' . ($nCont) . ']" value="' . $aDato['precio'] . '" />
																		<input type="hidden" name="bp_enlace[' . ($nCont) . ']" value="' . $aDato['enlace'] . '" />';

																		foreach( $aDato['titulo'] as $nLang => $sTitulo )
																			$sPuntosImagen .= '<input type="hidden" name="bp_titulo[' . ($nCont) . '][' . $nLang . ']" value="' . str_replace( '"', '&#34;', $aDato['titulo'][$nLang] ) . '" />';
												$sPuntosImagen .= '</div>';

												$nCont++;
											}
										}

										$sHtml .= $sHtmlInputTablet;
									$sHtml .= '</div>';
									$sHtml .= $sPuntosImagen;
								$sHtml .= '</div>';
							$sHtml .= '</div>';

							$sHtml .= '<div class="xline xline-dashed"></div>';

							$sHtml .= '<label for="orden" class="column a02 tright">' . BANNERS_DESTACADOS_IMAGE_MOBILE . ':</label>';
							$sHtml .= '<div class="column a10">';
								$sHtml .= '<a class="banner-boton-eliminar-imagen xbutton hv8 small rojo" data-confirm="' . BANNERS_DESTACADOS_DELETE_IMAGE_CONFIRM . '" href="javascript:void(0);" style="position: relative; z-index: 0; margin-right: 5px;">' . BANNERS_DESTACADOS_DELETE_IMAGE . '</a>';
								$sHtml .= '<a class="banner-boton-upload-imagen xbutton hv8 small verde" href="javascript:void(0);" style="position: relative; z-index: 0; margin-right: 15px;">' . BANNERS_DESTACADOS_ADD_IMAGE . '</a>';
								$sHtml .= '<div class="DFhelp">' . BANNERS_DESTACADOS_IMAGE_MOBILE_HELP . ' ' . BANNERS_DESTACADOS_WIDTH_MOBILE . 'px.</div>';
								$sHtml .= '<div style="visibility: hidden; width: 1px; height: 1px; display: none; opacity: 0;"><input class="banner-input-upload-imagen" data-width="' . BANNERS_DESTACADOS_WIDTH_MOBILE . '" name="banner-input-upload-imagen" type="file" accept="image/*" /></div>';

								$sHtml .= '<div class="xline xline-dashed"></div>';

								$sHtml .= '<div class="banner-imagen vrsmovil" style="position: relative; width: ' . BANNERS_DESTACADOS_WIDTH_MOBILE . 'px; float: none; overflow:hidden;" class="grid12">';
									$sHtml .= '<div class="imge">';
											$sHtml .= $sHtmlInputMovil;
									$sHtml .= '</div>';
								$sHtml .= '</div>';
							$sHtml .= '</div>';
						}

					$sHtml .= '</form>';
				$sHtml .= '</div>';
			$sHtml .= '</div>';

			// Añadir/editar punto
			$sHtml .= '<div id="dialog-products" title="' . BANNERS_DESTACADOS_POINT_TITLE . '">
				<form id="frm-point" method="post" class="rows ax xform sp10">
					<input type="hidden" id="pnt-prdid" name="products_id" value="0" />
					<input type="hidden" id="pnt-edt" name="point-edit" value="0" />
					<input type="hidden" id="pnt-rsp" name="point-resp" value="web" />
					<input type="hidden" id="pnt-x" name="point-x" value="0" />
					<input type="hidden" id="pnt-y" name="point-y" value="0" />
					<label class="column a12">' . TEXT_SEARCH . ':</label>
					<div class="column a12">
						<input id="autocomplete" autocomplete="off" type="text" placeholder="' . TEXT_SEARCH_PLACEHOLDER . '..." />
						<div class="DFhelp">' . BANNERS_DESTACADOS_POINT_SEARCH_HELP . '</div>
						<i id="dialog-products-loading" class="fa fa-spinner fa-spin fa-fw"></i>
					</div>
					<input type="hidden" readonly="readonly" action="autocomplete" />


					<label class="column a12">' . BANNERS_DESTACADOS_TABLE_TITLE . ':</label>
					<div class="column a12 ax row aflex">
						' . $sHtmlInput . '
						<div class="column afixed">
							<div data-value-update="true" class="drop xfselect">' .
								$sHtmlFake .
								'<ul class="down">' .
									$sHtmlPull .
								'</ul>
							</div>
						</div>
						<div class="column a12 DFhelp afixed">' . BANNERS_DESTACADOS_POINT_TITLE_HELP . '</div>
					</div>
					<label class="column a12">' . BANNERS_DESTACADOS_POINT_PRICE . ':</label>
					<div class="column a12">
						<input type="text" id="pnt-prce" name="precio" value="" />
						<div class="DFhelp">' . BANNERS_DESTACADOS_POINT_PRICE_HELP . '</div>
					</div>
					<label class="column a12">' . BANNERS_DESTACADOS_TABLE_LINK . ':</label>
					<div class="column a12">
						<input type="text" id="pnt-enlc" name="enlace" value="" />
						<div class="DFhelp">' . BANNERS_DESTACADOS_POINT_LINK_HELP . '</div>
					</div>
					<div class="xline xline-none"></div>
					<div class="column a12 tright">
						<a href="javascript:void(0);" class="dlt-pnt xbutton rojo hv9 small" style="display: none;"><span class="fa fa-close"></span> ' . BANNERS_DESTACADOS_TEXT_DELETE_SINGLE . '</a>
						<div class="xbutton verde hv9 small">
							<input type="submit" /><span class="fa fa-save"></span> ' . TEXT_SAVE . '
						</div>
					</div>
				</form>
				<div id="autocomplete-target"></div>
			</div>';
		break;

		case 'update_options':
			// Pasamos todos los post por tep_db_prepare_input
			array_walk( $_POST, function( $value, $key){ global $_POST; $_POST[$key] = tep_db_prepare_input( $_POST[$key] ); } );

			// Recorremos post en busca de los campos SECURITY para actualizar
			foreach( $_POST as $key => $value )
			{
				// Si es campo BANNERS_DESTACADOS_ actualizamos
				if (preg_match( '/^BANNERS_DESTACADOS_/', $key )) {
                    tep_db_query( 'UPDATE configuration SET configuration_value = "' . $value . '" WHERE configuration_key = "' . $key . '"' );
                }
			}

			// Si nos encontramos en BANNERS_DESTACADOS_ACTIVE y no existe en post es que hemos desactivado
			if (preg_match( '/^BANNERS_DESTACADOS/', (string) $key ) && !array_key_exists( 'BANNERS_DESTACADOS_ACTIVE', $_POST )) {
                tep_db_query( 'UPDATE configuration SET configuration_value = "false" WHERE configuration_key = "BANNERS_DESTACADOS_ACTIVE"' );
            }

			// Si nos encontramos en BANNERS_DESTACADOS_PUNTOS y no existe en post es que hemos desactivado
			if (preg_match( '/^BANNERS_DESTACADOS/', (string) $key ) && !array_key_exists( 'BANNERS_DESTACADOS_PUNTOS', $_POST )) {
                tep_db_query( 'UPDATE configuration SET configuration_value = "false" WHERE configuration_key = "BANNERS_DESTACADOS_PUNTOS"' );
            }

			// Si nos encontramos en BANNERS_DESTACADOS_RESPONSIVE y no existe en post es que hemos desactivado
			if (preg_match( '/^BANNERS_DESTACADOS/', (string) $key ) && !array_key_exists( 'BANNERS_DESTACADOS_RESPONSIVE', $_POST )) {
                tep_db_query( 'UPDATE configuration SET configuration_value = "false" WHERE configuration_key = "BANNERS_DESTACADOS_RESPONSIVE"' );
            }

			// Mensajes
			$messageStack->addSession( 'success', sprintf(BANNERS_DESTACADOS_MODULE_UPDATE_SUCCESS, $sTitle), 'success' );

			// Reset cache
			tools::createCacheFile();

			// Redireccionamos
			tep_redirect( $sUrlPage );
		break;

		case 'options':
			// Variables
			$aButtons = [
				[ 'title' => TEXT_BACK, 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-arrow-left' ],
				[ 'title' => TEXT_SAVE, 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' ]
			];
			$sSubtitle = 'Opciones';

			// Formulario
			$sHtml .= '<div class="oeBox column a12 row ax">';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-shield"></i> ' . BANNERS_DESTACADOS_TEXT_CONFIGURATION . ' </div>';
					$sHtml .= '<form method="post" id="saveform-send" action="' . tep_href_link( $sUrlPage, 'action=update_options' ) . '" class="oeCntd row ax xform xform-horizontal">';
						$sHtml .= '<label for="BANNERS_DESTACADOS_ACTIVE" class="column a02 tright inline">' . BANNERS_DESTACADOS_CONFIGURATION_ACTIVE . ':</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="checkbox" name="BANNERS_DESTACADOS_ACTIVE" id="BANNERS_DESTACADOS_ACTIVE" ' . (defined( 'BANNERS_DESTACADOS_ACTIVE' ) && BANNERS_DESTACADOS_ACTIVE == 'true' ? 'checked="checked"' : '') . ' value="true"/><label for="BANNERS_DESTACADOS_ACTIVE"><span></span></label>';
							$sHtml .= '<div class="DFhelp">' . BANNERS_DESTACADOS_CONFIGURATION_ACTIVE . '</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="BANNERS_DESTACADOS_RESPONSIVE" class="column a02 tright inline">' . BANNERS_DESTACADOS_CONFIGURATION_ACTIVE_RESPONSIVE . ':</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="checkbox" name="BANNERS_DESTACADOS_RESPONSIVE" id="BANNERS_DESTACADOS_RESPONSIVE" ' . (defined( 'BANNERS_DESTACADOS_RESPONSIVE' ) && BANNERS_DESTACADOS_RESPONSIVE == 'true' ? 'checked="checked"' : '') . ' value="true"/><label for="BANNERS_DESTACADOS_RESPONSIVE"><span></span></label>';
							$sHtml .= '<div class="DFhelp">' . BANNERS_DESTACADOS_CONFIGURATION_ACTIVE_RESPONSIVE_HELP . '</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="BANNERS_DESTACADOS_WIDTH" class="column a02 tright">' . BANNERS_DESTACADOS_CONFIGURATION_WEB_MAX_WIDTH . ':</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="text" name="BANNERS_DESTACADOS_WIDTH" id="BANNERS_DESTACADOS_WIDTH" value="' . (defined( 'BANNERS_DESTACADOS_WIDTH' ) &&  'BANNERS_DESTACADOS_WIDTH' !== '' ?  BANNERS_DESTACADOS_WIDTH  : '') . '"/>';
							$sHtml .= '<div class="DFhelp">' . BANNERS_DESTACADOS_CONFIGURATION_WEB_MAX_WIDTH_HELP . '</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="BANNERS_DESTACADOS_WIDTH_TABLET" class="column a02 tright">' . BANNERS_DESTACADOS_CONFIGURATION_TABLET_MAX_WIDTH . ':</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="text" name="BANNERS_DESTACADOS_WIDTH_TABLET" id="BANNERS_DESTACADOS_WIDTH_TABLET" value="' . (defined( 'BANNERS_DESTACADOS_WIDTH_TABLET' ) &&  'BANNERS_DESTACADOS_WIDTH_TABLET' !== '' ?  BANNERS_DESTACADOS_WIDTH_TABLET  : '') . '"/>';
							$sHtml .= '<div class="DFhelp">' . BANNERS_DESTACADOS_CONFIGURATION_TABLET_MAX_WIDTH_HELP . '</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="BANNERS_DESTACADOS_WIDTH_MOBILE" class="column a02 tright">' . BANNERS_DESTACADOS_CONFIGURATION_MOBILE_MAX_WIDTH . ':</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="text" name="BANNERS_DESTACADOS_WIDTH_MOBILE" id="BANNERS_DESTACADOS_WIDTH_MOBILE" value="' . (defined( 'BANNERS_DESTACADOS_WIDTH_MOBILE' ) &&  'BANNERS_DESTACADOS_WIDTH_MOBILE' !== '' ?  BANNERS_DESTACADOS_WIDTH_MOBILE  : '') . '"/>';
							$sHtml .= '<div class="DFhelp">' . BANNERS_DESTACADOS_CONFIGURATION_MOBILE_MAX_WIDTH_HELP . '</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="BANNERS_DESTACADOS_PUNTOS" class="column a02 tright inline">' . BANNERS_DESTACADOS_CONFIGURATION_ACTIVE_POINTS . ':</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="checkbox" name="BANNERS_DESTACADOS_PUNTOS" id="BANNERS_DESTACADOS_PUNTOS" ' . (defined( 'BANNERS_DESTACADOS_PUNTOS' ) && BANNERS_DESTACADOS_PUNTOS == 'true' ? 'checked="checked"' : '') . ' value="true"/><label for="BANNERS_DESTACADOS_PUNTOS"><span></span></label>';
							$sHtml .= '<div class="DFhelp">' . BANNERS_DESTACADOS_CONFIGURATION_ACTIVE_POINTS_HELP . '</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="BANNERS_DESTACADOS_PUNTOS_HEIGHT" class="column a02 tright">' . BANNERS_DESTACADOS_CONFIGURATION_POINTS_HEIGHT . ':</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="text" name="BANNERS_DESTACADOS_PUNTOS_HEIGHT" id="BANNERS_DESTACADOS_PUNTOS_HEIGHT" value="' . (defined( 'BANNERS_DESTACADOS_PUNTOS_HEIGHT' ) &&  'BANNERS_DESTACADOS_PUNTOS_HEIGHT' !== '' ?  BANNERS_DESTACADOS_PUNTOS_HEIGHT  : '') . '"/>';
							$sHtml .= '<div class="DFhelp">' . BANNERS_DESTACADOS_CONFIGURATION_POINTS_HEIGHT_HELP . '</div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-dashed"></div>';

						$sHtml .= '<label for="BANNERS_DESTACADOS_PUNTOS_WIDTH" class="column a02 tright">' . BANNERS_DESTACADOS_CONFIGURATION_POINTS_WIDTH . ':</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="text" name="BANNERS_DESTACADOS_PUNTOS_WIDTH" id="BANNERS_DESTACADOS_PUNTOS_WIDTH" value="' . (defined( 'BANNERS_DESTACADOS_PUNTOS_WIDTH' ) &&  'BANNERS_DESTACADOS_PUNTOS_WIDTH' !== '' ?  BANNERS_DESTACADOS_PUNTOS_WIDTH  : '') . '"/>';
							$sHtml .= '<div class="DFhelp">' . BANNERS_DESTACADOS_CONFIGURATION_POINTS_WIDTH_HELP . '</div>';
						$sHtml .= '</div>';

						$sHtml .= '<input type="submit" style="display: none;" />';
					$sHtml .= '</form>';
				$sHtml .= '</div>';
			$sHtml .= '</div>';
		break;

		default:
			// Variables
			$sSubtitle = HEADING_SUBTITLE;
			$aButtons[] = [ 'title' => BANNERS_DESTACADOS_TEXT_OPTIONS, 'href' => tep_href_link( $sUrlPage, 'action=options' ), 'icon' => 'fa-cog' ];
			$aButtons[] = [ 'title' => BANNERS_DESTACADOS_TEXT_ADD, 'href' => tep_href_link( $sUrlPage, 'action=add_form' ), 'icon' => 'fa-plus' ];

			// Html para el boton masivo
			$sHtmlActionMasivo = '<label class="column afluid">' . BANNERS_DESTACADOS_TEXT_APPLY_ACTION . ':&nbsp;&nbsp;</label>
			<div class="column afluid"><div class="drop masv xfselect">
				<div>' . BANNERS_DESTACADOS_TABLE_ACTIONS . '</div>
				<ul class="down drch">
					<li><a data-question="' . BANNERS_DESTACADOS_TEXT_DELETES_CONFIRM . '" data-error="' . BANNERS_DESTACADOS_TEXT_DELETE_ERROR . '" data-action="' . tep_href_link( $sUrlPage, 'action=delete' ) . '" href="javascript:void(0);" class="hv"><i class="fa fa-trash"></i>' . BANNERS_DESTACADOS_TEXT_DELETES . '</a></li>
				</ul>
			</div></div>&nbsp; - &nbsp;';

			// Filtros
			$aFiler = [ 'search' => '', 'search_date' => '' ];
			$aAuxFilter = array_key_exists( 'filter', $_GET ) && is_array($_GET['filter']) ? $_GET['filter'] : (array_key_exists( 'filter', $_POST ) && is_array($_POST['filter']) ? $_POST['filter'] : []);
			$sWhere = '';

			// Limpiamos variables get filter
			array_walk( $aFiler, function( $value, $key){ global $aFiler, $aAuxFilter; $aFiler[$key] = tep_db_prepare_input( array_key_exists( $key, $aAuxFilter ) ? $aAuxFilter[$key] : $aFiler[$key] ); } );

			// Where
			if ($aFiler['search'] !== '' || $aFiler['search_date'] !== '') {
                $sWhere = 'where ';
            }

			if ($aFiler['search'] !== '') {
                $sWhere .= ( $sWhere !== 'where ' ? ' and' : '') . ' (LOWER(titulo) LIKE "%' . strtolower( $aFiler['search'] ) . '%")';
            }

			if( $aFiler['search_date'] !== '' )
			{
				$aValue = explode( ' - ', $aFiler['search_date'] );
				$aValue[0] = date::changeDate( $aValue[0], 'espanol', 'y-m-d' );
				$aValue[1] = date::changeDate( $aValue[1], 'espanol', 'y-m-d' );

				$sWhere .= ( $sWhere !== 'where ' ? ' and' : '') . ' date_start >= "' . $aValue[0] . '" AND (date_end <= "' . $aValue[1] . '" OR date_end IS NULL OR date_end = "0000-00-00 00:00:00")';
			}

			// Order by
			if ($sGetOrderby == 'titulo') {
                $sOrderby = 'titulo ' . $sGetSort;
            } elseif ($sGetOrderby == 'orden') {
                $sOrderby = 'orden ' . $sGetSort;
            } elseif ($sGetOrderby == 'estado') {
                $sOrderby = 'estado ' . $sGetSort;
            } elseif ($sGetOrderby == 'date_start') {
                $sOrderby = 'date_start ' . $sGetSort;
            } elseif ($sGetOrderby == 'date_end') {
                $sOrderby = 'date_end ' . $sGetSort;
            } else {
                $sOrderby = 'titulo asc';
            }

			// Sql
			$sSql = 'SELECT banner_destacados_id, titulo, enlace, date_start, date_end, estado, orden
					 FROM banners_destacados
					 ' . $sWhere . ' ORDER BY ' . $sOrderby;

			// Le quitamos los tabuladores y saltos de linea para que splitpageesult funcione con el SQL
			$sSql = preg_replace( '/[\r\n\t]+/', ' ', $sSql );

			// Sql para el count
			$sSqlCount = 'SELECT COUNT(table_aux.banner_destacados_id) as total FROM (' . $sSql . ') as table_aux';

			// Datos y paginacion
			$aDatoSplit = new splitPageResults( $sGetPage, MAX_DISPLAY_SEARCH_RESULTS, $sSql, $nAux, $sSqlCount );
			$aDatos = tep_db_query( $sSql );

			// Mensajes comprobamos si tenemos datos
			if( tep_db_num_rows( $aDatos ) <= 0 )
			{
				if ($sWhere !== '') {
                    $sHtml .= $messageStack->show( [ 'text' => BANNERS_DESTACADOS_FILTER_NO_DATA, 'class' => 'warning' ] );
                } else {
                    $sHtml .= $messageStack->show( [ 'text' => BANNERS_DESTACADOS_NO_RECORDS, 'class' => 'warning' ] );
                }
			}

			// Tabla
			$sHtml .= '<div class="oeBox oeTable column a12 row ax">';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-eye"></i> ' . HEADING_SUBTITLE . '</div>';
					$sHtml .= '<form method="post" action="' . tep_href_link( $sUrlPage ) . '" class="oeCntd row ax">';
						$sHtml .= '<div class="oeBoxFltr column a12 ax row">';
							$sHtml .= '<div class="column a09 row ax amiddle input-search">';
								$sHtml .= '<label class="column">' . TEXT_SEARCH . ': </label> <div class="column"><input type="text" name="filter[search]" placeholder="' . TEXT_SEARCH_PLACEHOLDER . '" value="' . $aFiler['search'] . '" autofocus/> <i class="fa fa-search"></i></div>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="column a03 tright">';
								$sHtml .= ($sWhere !== '' ? '<a title="' . TEXT_CLEAN_FILTER . '" href="' . tep_href_link( $sUrlPage, tep_get_all_get_params( [ 'filter' ] ) ) . '" class="xbutton hv9 rojo small"><i class="fa fa fa-close"></i></a> ' : '');
								$sHtml .= '<a href="#fltr-lstd" title="' . TEXT_FILTER . '" class="xbutton small hv9 verde-turquesa mgp-inln"><i class="fa fa-filter"></i></a>';
							$sHtml .= '</div>';
						$sHtml .= '</div>';

						$sHtml .= '<table class="xform">';
							$sHtml .= '<thead>';
								$sHtml .= '<tr>';
									$sHtml .= '<th width="17" class="chck"><input type="checkbox" id="all_check"/><label for="all_check"><span></span></label></th>';
									$sHtml .= '<th class="sort" width="150">' . tableSetSort( 'titulo', BANNERS_DESTACADOS_TABLE_TITLE ) . '</th>';
									$sHtml .= '<th>' . BANNERS_DESTACADOS_TABLE_IMAGE . '</th>';
									$sHtml .= '<th>' . BANNERS_DESTACADOS_TABLE_LINK . '</th>';
									$sHtml .= '<th class="sort">' . tableSetSort( 'date_start', BANNERS_DESTACADOS_TABLE_START_DATE ) . '</th>';
									$sHtml .= '<th class="sort">' . tableSetSort( 'date_end', BANNERS_DESTACADOS_TABLE_END_DATE ) . '</th>';
									$sHtml .= '<th class="sort">' . tableSetSort( 'orden', BANNERS_DESTACADOS_TABLE_ORDER ) . '</th>';
									$sHtml .= '<th class="sort">' . tableSetSort( 'estado', BANNERS_DESTACADOS_TABLE_STATUS ) . '</th>';
									$sHtml .= '<th width="125">' . BANNERS_DESTACADOS_TABLE_ACTIONS . '</th>';
								$sHtml .= '</tr>';
							$sHtml .= '</thead>';
							$sHtml .= '<tbody>';

								while( $aDato = tep_db_fetch_array( $aDatos ) )
								{
									// Fila
									$sHtml .= '<tr data-dblclick="' . tep_href_link( $sUrlPage, 'action=add_form&id=' . $aDato['banner_destacados_id'] ) . '">';
										$sHtml .= '<td class="chck" align="center"><input type="checkbox" id="id_' . $aDato['banner_destacados_id'] . '" name="id[]" value="' . $aDato['banner_destacados_id'] . '"/><label for="id_' . $aDato['banner_destacados_id'] . '"><span></span></label></td>';
										$sHtml .= '<td>' . $aDato['titulo'] . '</td>';

										$sImagen = getImagenBannerDestacado($aDato['banner_destacados_id'], $languages_id);
										if ($sImagen === false) $sImagen = '';

										$sHtml .= '<td>' . $sImagen . '</td>';
										$sHtml .= '<td>' . $aDato['enlace'] . '</td>';
										$sHtml .= '<td>' . ($aDato['date_start'] != '' && $aDato['date_start'] != '0000-00-00 00:00:00' ? date( 'd-m-Y', strtotime( (string) $aDato['date_start'] ) ) : '-') . '</td>';
										$sHtml .= '<td>' . ($aDato['date_end'] != '' && $aDato['date_end'] != '0000-00-00 00:00:00' ? date( 'd-m-Y', strtotime( (string) $aDato['date_end'] ) ) : '-') . '</td>';
										$sHtml .= '<td>' . $aDato['orden'] . '</td>';
										$sHtml .= '<td>';
											if ($aDato['estado'] == '1') {
                                                $sHtml .= tep_image(DIR_WS_IMAGES . 'icon_status_green.png', IMAGE_ICON_STATUS_GREEN, 10, 10) . '&nbsp;&nbsp;<a href="' . tep_href_link($sUrlPage, 'action=setflag&flag=0&id=' . $aDato['banner_destacados_id'], 'NONSSL') . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_red_light.png', IMAGE_ICON_STATUS_RED_LIGHT, 10, 10) . '</a>';
                                            } else {
                                                $sHtml .= '<a href="' . tep_href_link($sUrlPage, 'action=setflag&flag=1&id=' . $aDato['banner_destacados_id'], 'NONSSL') . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_green_light.png', IMAGE_ICON_STATUS_GREEN_LIGHT, 10, 10) . '</a>&nbsp;&nbsp;' . tep_image(DIR_WS_IMAGES . 'icon_status_red.png', IMAGE_ICON_STATUS_RED, 10, 10);
                                            }
										$sHtml .= '</td>';
										$sHtml .= '<td>';
											$sHtml .= '<div class="drop xfselect">';
												$sHtml .= '<div>' . BANNERS_DESTACADOS_TABLE_ACTIONS . '</div>';
												$sHtml .= '<ul class="down down-dngt">';
													$sHtml .= '<li><a href="' . tep_href_link( $sUrlPage, 'action=add_form&id=' . $aDato['banner_destacados_id'] ) . '" class="hv"><i class="fa fa-pencil"></i>' . BANNERS_DESTACADOS_TEXT_EDIT . '</a></li>';
													$sHtml .= '<li><a data-confirm="' . BANNERS_DESTACADOS_TEXT_DELETE_CONFIRM . '" href="' . tep_href_link( $sUrlPage, 'action=delete&id=' . $aDato['banner_destacados_id'] ) . '" class="hv"><i class="fa fa-trash"></i>' . BANNERS_DESTACADOS_TEXT_DELETE . '</a></li>';
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

			// Filtro
			$sHtml .= '<form action="' . tep_href_link( $sUrlPage ) . '" method="get" id="fltr-lstd" class="vntn-form mfp-hide zoom-anim-dialog oeBox mfp-white">';
				$sHtml .= '<div class="oeWrpr">';
					$sHtml .= '<div class="oeTitu"><i class="fa fa-eye"></i> ' . HEADING_SUBTITLE . '</div>';
					$sHtml .= '<div class="oeCntd row ax xform xform-horizontal">';
						$sHtml .= '<label for="search" class="column a02 tright">' . TEXT_SEARCH . ':</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input type="text" name="filter[search]" placeholder="' . TEXT_SEARCH_PLACEHOLDER . '" value="' . $aFiler['search'] . '"/> ';
						$sHtml .= '</div>';
						$sHtml .= '<div class="xline xline-dashed"></div>';
						$sHtml .= '<label for="search" class="column a02 tright">' . BANNERS_DESTACADOS_TEXT_DATE . ':</label>';
						$sHtml .= '<div class="column a10">';
							$sHtml .= '<input value="' . $aFiler['search_date'] . '" data-autoupdate="true" autocomplete="off" name="filter[search_date]" readonly="readonly" class="form-datetime-range" type="text" />';
						$sHtml .= '</div>';

						$sHtml .= '<div class="xline xline-none"></div>';
						$sHtml .= '<div class="column a12 tright">';
							$sHtml .= ($sWhere !== '' ? '<a href="' . tep_href_link( $sUrlPage, tep_get_all_get_params( [ 'filter' ] ) ) . '" class="xbutton hv9 rojo small"><i class="fa fa fa-close"></i> ' . BANNERS_DESTACADOS_TEXT_DELETE_SINGLE . '</a> ' : '');
							$sHtml .= '<div class="xbutton verde hv9 small"><input type="submit"/><span class="fa fa fa-filter"></span> ' . TEXT_FILTER . '</div>';
						$sHtml .= '</div>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';
			$sHtml .= '</form>';
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
		echo '<div class="oeTitu column logo afixed" style="padding-left: 59px;"><b><i class="fa fa-image"></i> ' . $sTitle . '</b>' . ($sSubtitle !== '' && $sSubtitle !== '0' ? '<small>' . $sSubtitle . '</small>' : '') . '</div>';
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
