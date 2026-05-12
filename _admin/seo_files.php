<?php
	include( 'includes/application_top.php' );

	// Variables
	$sPageHeading = '';
	$sAction = $_GET['a'];
	$sHtml = '';
	$sUrl = tep_href_link( 'seo_files.php' );

	switch( $sAction )
	{
		case 'delete':
			// Variables
			$sGetId = tep_db_prepare_input( $_GET['id'] );

			// Consulta
			$aDatos = tep_db_query( 'select alias from seo_files where id = ' . $sGetId . ' and language_id = 3' );
			$aDato = tep_db_fetch_array( $aDatos );

			// Eliminamos
			tep_db_query( 'delete from seo_files where id = ' . $sGetId );

			// Redireccionamos
			$messageStack->add_session( 'El archivo seo "' . $aDato['alias'] . '" se ha eliminado correctamente', 'success' );
			tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;
		case 'update':
		case 'add':
			// Variables
			$sPostId = tep_db_prepare_input( $_POST['id'] );
			$aDenegados = array( 'idioma', 'id', 'file', 'alias' );
			$aIdiomas = tep_get_languages();
			$aSeoIdioma = array();
			$sId = false;

			// Obtenemos todas los archivos seo que tenemos con el idioma
			$aDatos = tep_db_query( 'select language_id from seo_files where id = "' . $sPostId . '"' );
			while( $aDato = tep_db_fetch_array( $aDatos ) )
				$aSeoIdioma[] = $aDato['language_id'];

			// Recorremos idiomas
			foreach( $aIdiomas as $aIdioma )
			{
				// Reseteamos
				$aSql = array();
				$aSql['alias'] = tep_db_prepare_input( $_POST['alias'] );
				$aSql['file'] = tep_db_prepare_input( $_POST['file'] );

				// Recorremos post
				foreach( $_POST as $key => $value )
				{
					// Si el campo no es denegado
					if( !in_array( $key, $aDenegados ) )
					{
						// Añadimos el campo
						$aSql[$key] = tep_db_prepare_input( $value[$aIdioma['id']] );
					}
				}

				// Insertamos o actualizamos comprobando si el seo existe ya
				if( !in_array( $aIdioma['id'], $aSeoIdioma ) )
				{
					$aSql['language_id'] = $aIdioma['id'];

					// Si estamos actualizando pero es un idioma nuevo o si acabamos de insertar un idioma
					if( $sAction == 'update' || $sId !== false )
					{
						// Si tenemos un ID es que acabamos de insertar uno nuevo asi que cambiamos el valor
						if( $sId !== false )
							$sPostId = $sId;

						$aSql['id'] = $sPostId;
					}

					// Comprobamos si ya existe el registro para este id + idioma
					$existCheck = tep_db_query('select id from seo_files where id = ' . (int)($aSql['id'] ?? 0) . ' and language_id = ' . (int)$aIdioma['id']);
					if( tep_db_num_rows($existCheck) > 0 )
					{
						tep_db_perform( 'seo_files', $aSql, 'update', 'id = ' . (int)$sPostId . ' and language_id = ' . (int)$aIdioma['id'] );
					}
					else
					{
						tep_db_perform( 'seo_files', $aSql );
					}

					// Si estamos insertando y es el primer id guardamos el id
					if( $sAction == 'add' && $sId === false )
						$sId = tep_db_insert_id();
				}
				else
					tep_db_perform( 'seo_files', $aSql, 'update', 'id = ' . $sPostId . ' and language_id = ' . $aIdioma['id'] );
			}
			// Redireccionamos
			$messageStack->add_session( 'El archivo seo se ' . ( $sAction == 'update' ? 'actualizo' : 'inserto' ) . ' correctamente', 'success' );
			tep_redirect( $sUrl );
		break;

		case 'add_form':
		case 'update_form':
			// Variables
			$sGetId = tep_db_prepare_input( $_GET['id'] );
			$sHtml = '';
			$sTitulo = 'Nuevo archivo';
			$aIdiomas = tep_get_languages();
			$aComboIdiomas = array();

			// Obtenemos un array para el combobox de idiomas
			foreach( $aIdiomas as $aIdioma )
				$aComboIdiomas[] = array( 'id' => $aIdioma['id'], 'text' => $aIdioma['name'] );

			// Objeto info
			$oInfo = createObjectInfo( 'seo_files', array( 'IDIOMA' => true ) );

			// Si estamos editamos cargamos los valores
			if( $sAction == 'update_form' )
			{
				// Consulta
				$aDatos = tep_db_query( 'select id, file, title, description, alias, language_id
										 from seo_files
										 where id = ' . $sGetId );

				// Obtenemos los datos
				while( $aDato = tep_db_fetch_array( $aDatos ) )
					$oInfo->{$aDato['language_id']} = $aDato;

				// Titular
				$sTitulo = 'Editar SEO: ' . $oInfo->{3}['alias'];
			}

			$sHtml .= '<form name="seo_files" action="' . $sUrl . '?a=' . ($sAction == 'update_form' ? 'update' : 'add') . '" method="post">';
				// Si estamos actualizando añadimos el ID
				if( $sAction == 'update_form' )
					$sHtml .= '<input type="hidden" name="id" value="' . $sGetId . '" />';

				$sHtml .= '<div>
					<div class="toolbarHead">
						<div class="hdr-tlbr">
							<h1 class="pageHeading" style="top: 12px;">' . $sTitulo . '</h1>
							<div class="btn-right">
								<a href="javascript:void(0);" onclick="$(this).closest(\'form\').submit()"><img class="dx-hovr" src="images/icons/icon_save.png"></a>
								<a href="' . $sUrl . '"><img class="dx-hovr" src="images/icons/icon_back.png" title="Volver sin guardar"></a>
							</div>
						</div>
					</div>
				</div>';

				$sHtml .= '<div class="fluid grid">
					<div class="box-tbl grid12">
						<div class="box-head">
							<div class="grid9"><h6>Archivo</h6></div>
							<div class="clear"></div>
						</div>';

						$sHtml .= '<div class="formRow">';
							$sHtml .= '<div class="grid3" style="margin: 0px;">';
								$sHtml .= '<label>Titulo:</label>';
								$sHtml .= '<span class="note" style="display: block; width: 100%; float: left; margin-top: -8px; font-style: italic; white-space: normal;">Nombre para identificar a que archivo te refieres. Por ejemplo "pagina de contacto"</span>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="grid9">';
								$sHtml .= tep_draw_input_field( 'alias', $oInfo->{3}['alias'], 'style="width: 100%; margin: 0px;"' );
							$sHtml .= '</div>';
							$sHtml .= '<div class="clear"></div>';
						$sHtml .= '</div>';

						$sHtml .= '<div class="formRow">';
							$sHtml .= '<div class="grid3" style="margin: 0px;">';
								$sHtml .= '<label>Archivo:</label>';
								$sHtml .= '<span class="note" style="display: block; width: 100%; float: left; margin-top: -8px; font-style: italic; white-space: normal;">Nombre del archivo php que afectara al seo. Por ejemplo "contact_us.php".</span>';
							$sHtml .= '</div>';
							$sHtml .= '<div class="grid9">';
								$sHtml .= tep_draw_input_field( 'file', $oInfo->{3}['file'], 'style="width: 100%; margin: 0px;"' );
							$sHtml .= '</div>';
							$sHtml .= '<div class="clear"></div>';
						$sHtml .= '</div>';

					$sHtml .= '</div>';
				$sHtml .= '</div>';

				$sHtml .= '<div class="tab-new" data-id="2" style="display: block;">
					<div class="fluid grid">
						<div class="box-tbl grid12">
							<div class="box-head">
								<div class="grid9"><h6>Opciones SEO</h6></div>
								<div class="grid3">';
									$sHtml .= '<p style="position: relative; float: right; top: 3px; right: 43px;">';
										$sHtml .= '<label>Seleccionar idioma: </label>';
										$sHtml .=  tep_draw_pull_down_menu( 'idioma', $aComboIdiomas, '', 'class="change_idioma" data-id="2"');
									$sHtml .= '</p>';
								$sHtml .= '</div>
								<div class="clear"></div>
							</div>';

							// Campos del formulario
							$aCampos = array(
								array( 'title' => 'Título', 'text' => 'Título que se muestra en la cabecera del navegador.', 'row' => 'title' ),
								array( 'title' => 'Descripción', 'text' => 'Descripcion que nos ayudará a indicar cual es el contenido de nuestra página. Recomendamos que tenga entre 70 y 156 caracteres (incluyendo espacios).', 'row' => 'description' )
							);

							// Recorremos los idiomas
							foreach( $aIdiomas as $aIdioma )
							{
								$sHtml .= '<div style="display: none;" class="tab-change-idma-2" id="change-idma-2-' . $aIdioma['id'] . '">';
									$sHtml .= tep_image( DIR_WS_CATALOG_LANGUAGES . $aIdioma['directory'] . '/images/' . $aIdioma['image'], $aIdioma['name'], '', '', 'style="position: absolute; top: 10px; right: 10px;"' );
									// Recorremos los campos
									foreach( $aCampos as $nKeyCampo => $aCampo )
									{
										/*if( $nKeyCampo == 0 )
										{
											$sHtml .= '<div class="text-seo">';
												$sHtml .= '<span class="titl" data-row="{HTML_REPLACE_ROW_title}" data-max="' . CARACTERES_SEO_TITLE . '">{HTML_REPLACE_VALUE_title}</span>';
												$sHtml .= '<span class="url">' . tep_catalog_href_link( 'url_ejemplo.php' ) . '</span>';
												$sHtml .= '<span class="dscp" data-row="{HTML_REPLACE_ROW_description}" data-max="' . CARACTERES_SEO_DESCRIPTION . '">{HTML_REPLACE_VALUE_description}</span>';
											$sHtml .= '</div>';
										}*/

										$sHtml .= '<div class="formRow">';
											$sHtml .= '<div class="grid3" style="margin: 0px;">';
												$sHtml .= '<label>' . $aCampo['title'] . '</label>';
												$sHtml .= '<span class="note" style="display: block; width: 100%; float: left; margin-top: -8px; font-style: italic; white-space: normal;">' . $aCampo['text'] . '</span>';
											$sHtml .= '</div>';
											$sHtml .= '<div class="grid9">';

												switch( $aCampo['row'] )
												{
													case 'seo_description':

													default:
														$sHtml .= tep_draw_input_field( $aCampo['row'] . '[' . $aIdioma['id'] . ']', $oInfo->{$aIdioma['id']}[$aCampo['row']], 'style="width: 100%; margin: 0px 0px 10px;"' ) . '<br/>';
													break;
												}

											$sHtml .= '</div>';
											$sHtml .= '<div class="clear"></div>';
										$sHtml .= '</div>';

										// Replace Preview SEO
										$sHtml = str_replace( array(
											'{HTML_REPLACE_ROW_' . $aCampo['row'] . '}',
											'{HTML_REPLACE_VALUE_' . $aCampo['row'] . '}'
										),
										array(
											$aCampo['row'] . '[' . $aIdioma['id'] . ']',
											$aAux[$aIdioma['id']][$aCampo['row']]
										), $sHtml );
									}

								$sHtml .= '</div>';
							}
						$sHtml .= '</div>
					</div>
				</div>';
			$sHtml .= '</form>';
		break;

		default:
			// Titular
			$sPageHeading = 'Archivos SEO';

			// Consulta
			$aDatos = tep_db_query( 'SELECT id, file, title, description, alias, language_id
									 FROM seo_files
									 WHERE language_id = 3
									 ORDER BY file ASC' );

			$sHtml = '<div class="box-tbl" style="width: 100%">';
				$sHtml .= '<div class="box-head">
					<h6>Archivos seo</h6>
					<div class="clear"></div>
				</div>';

				$sHtml .= '<table id="table" class="tAlt wGeneral tDefault" width="100%" cellspacing="0" cellpadding="0">
					<thead>
						<tr>
							<td style="text-align: left;">Nombre</td>
							<td style="text-align: left;">Archivo</td>
							<td style="text-align: left;">SEO</td>
							<td width="195">Acciones</td>
						</tr>
					</thead>
					<tbody>';

						while( $aDato = tep_db_fetch_array( $aDatos ) )
						{
							$sHtml .= '<tr>';
								$sHtml .= '<td align="left">' . $aDato['alias'] . '</td>';
								$sHtml .= '<td  align="left">' . $aDato['file'] . '</td>';

								$aSeo = array();

								$sHtml .= '<td  align="left">' . (!empty($aSeo) ? implode(',', $aSeo) : '-')  . '</td>';
								$sHtml .= '<td align="center">
									<div class="btn-group" style="display: inline-block; margin-bottom: -4px;">
										<a class="buttonS bDefault" data-toggle="dropdown" href="#">Acciones<span class="caret"></span></a>
										<ul class="dropdown-menu">
											<li><a href="' . $sUrl . '?a=update_form&id=' . $aDato['id'] . '"><span class="icos-pencil"></span>Editar</a></li>
											<li><a class="dlet" href="' . $sUrl . '?a=delete&id=' . $aDato['id'] . '"><span class="icos-trash"></span>Borrar</a></li>
										</ul>
									</div>
								</td>';
							$sHtml .= '</tr>';
						}

				$sHtml .= '</tbody>
				</table>
			</div>';

			$sHtml .= '<div style="margin-top: 35px; text-align: right;" class="fluid grid">
				<div class="grid12">
					<a class="buttonS bGreen" href="' . $sUrl . '?a=add_form">Añadir nuevo archivo</a>
				</div>
			</div>';
		break;
	}

	include( THEME . 'html/header.php' );

	if( $sPageHeading != '' )
		echo '<h1 class="pageHeading" style="padding: 18px 0 0px">' . $sPageHeading . '</h1>';

	echo $sHtml;

	include( THEME . 'html/footer.php' );

	echo '<script type="text/javascript">
		$(".stus").click(function()
		{
			var sStatus = $(this).data("status");
			var dmElmt = $(this);

			if( sStatus == 1 )
				sStatus = 0;
			else
				sStatus = 1;

			$(this).data( "status", sStatus );

			$.ajax({
				url: "' . $sUrl . '?a=status&id=" + $(this).data("id") + "&status=" + sStatus
			}).done(function()
			{
				if( sStatus == 1 )
					dmElmt.html( \'<img width="10" height="10" src="images/icon_status_green.gif"/> <img width="10" height="10" src="images/icon_status_red_light.gif"/>\');
				else
					dmElmt.html( \'<img width="10" height="10" src="images/icon_status_green_light.gif"/> <img width="10" height="10" src="images/icon_status_red.gif"/>\');
			});
		});

		$("#table").find(\'a[class="dlet"]\').click( function(e)
		{
			if(e)e.stopPropagation();

			if( confirm( "¿Realmente deseas borrar el seo de este archivo?" ) )
				return true;
			else
				return false;
		});
	</script>';

	include(DIR_WS_INCLUDES . 'application_bottom.php' );
?>
