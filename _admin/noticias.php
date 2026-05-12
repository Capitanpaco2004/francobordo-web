<?php
	include( 'includes/application_top.php' );

	// Variables
	$sPageHeading = '';
	$sAction = $_GET['a'];
	$sHtml = '';
	$sUrl = tep_href_link( 'noticias.php' );

	switch( $sAction )
	{
		case 'status':
			// Variables
			$sGetId = tep_db_prepare_input( $_GET['id'] );
			$sGetStatus = tep_db_prepare_input( $_GET['status'] );
			$aSql = array(
				'estado' => $sGetStatus
			);

			tep_db_perform( 'noticia', $aSql, 'update', 'id_noticia = ' . (int)$sGetId );
			exit();
		break;
		case 'delete':
			// Variables
			$sGetId = tep_db_prepare_input( $_GET['id'] );

			// Consulta
			$aDatos = tep_db_query( 'select titulo from noticia where id_noticia = ' . (int)$sGetId );
			$aDato = tep_db_fetch_array( $aDatos );

			// Eliminamos
			tep_db_query( 'delete from noticia where id_noticia = ' . (int)$sGetId );

			// Redireccionamos
			$messageStack->add_session( 'La noticia "' . $aDato['titulo'] . '" se ha eliminado correctamente', 'success' );
			tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;
		case 'update':
		case 'add':
			// Variables
			$sPostId = tep_db_prepare_input( $_POST['id'] );
			$aDenegados = array( 'idioma', 'id', 'estado' );
			$aIdiomas = tep_get_languages();
			$aNoticiasIdioma = array();
			$sId = false;

			// Obtenemos todas las noticias que tenemos con el idioma
			$aDatos = tep_db_query( 'select id_idioma from noticia where id_noticia = "' . (int)$sPostId . '"' );
			while( $aDato = tep_db_fetch_array( $aDatos ) )
				$aNoticiasIdioma[] = $aDato['id_idioma'];

			// Recorremos idiomas
			foreach( $aIdiomas as $aIdioma )
			{
				// Reseteamos
				$aSql = array();

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

				// Estado
				$aSql['estado'] = tep_db_prepare_input( $_POST['estado'] );

				// Fecha
				$aAux = explode( '/', tep_db_prepare_input( $_POST['fecha'] ) );
				$aSql['fecha'] = $aAux[2] . '-' . $aAux[1] . '-' . $aAux[0];

				// Insertamos o actualizamos comprobando si la noticia existe ya
				if( !in_array( $aIdioma['id'], $aNoticiasIdioma ) )
				{
					$aSql['id_idioma'] = $aIdioma['id'];

					// Si estamos actualizando pero es un idioma nuevo o si acabamos de insertar un ididioma
					if( $sAction == 'update' || $sId !== false )
					{
						// Si tenemos un ID esque acabamos de insertar una nueva noticia asi que cambiamos el valor
						if( $sId !== false )
							$sPostId = $sId;

						$aSql['id_noticia'] = $sPostId;
					}

					// Insertamos
					tep_db_perform( 'noticia', $aSql );

					// Si estamos insertando y es el primer id de noticia guardamos el id
					if( $sAction == 'add' && $sId === false )
						$sId = tep_db_insert_id();
				}
				else
					tep_db_perform( 'noticia', $aSql, 'update', 'id_noticia = ' . (int)$sPostId . ' and id_idioma = ' . (int)$aIdioma['id'] );
			}

			// Redireccionamos
			$messageStack->add_session( 'La noticia se ' . ( $sAction == 'update' ? 'actualizo' : 'inserto' ) . ' correctamente', 'success' );
			tep_redirect( $sUrl );
		break;

		case 'add_form':
		case 'update_form':
			// Variables
			$sGetId = tep_db_prepare_input( $_GET['id'] );
			$sHtml = '';
			$sTitulo = 'Nueva noticia';
			$aIdiomas = tep_get_languages();
			$aComboIdiomas = array();
			$aComboEstados = array(
				array( 'id' => 1, 'text' => 'Activado' ),
				array( 'id' => 0, 'text' => 'Desactivado' )
			);

			// Obtenemos un array para el combobox de idiomas
			foreach( $aIdiomas as $aIdioma )
				$aComboIdiomas[] = array( 'id' => $aIdioma['id'], 'text' => $aIdioma['name'] );

			// Objeto info
			$oInfo = createObjectInfo( 'noticia', array( 'IDIOMA' => true ) );

			// Si estamos editamos cargamos los valores
			if( $sAction == 'update_form' )
			{
				// Consulta noticia
				$aDatos = tep_db_query( 'select id_noticia, DATE_FORMAT(fecha, "%d/%m/%Y") as fecha, titulo, texto, id_idioma, estado, seo_title, seo_keywords, seo_description
										 from noticia
										 where id_noticia = ' . (int)$sGetId );

				// Obtenemos los datos
				while( $aDato = tep_db_fetch_array( $aDatos ) )
					$oInfo->{$aDato['id_idioma']} = $aDato;

				// Titular
				$sTitulo = 'Editar noticia ' . $oInfo->{3}['titulo'];
			}

			$sHtml .= '<form name="noticia" action="' . $sUrl . '?a=' . ($sAction == 'update_form' ? 'update' : 'add') . '" method="post">';
				// Si estamos actualizando añadimos el ID
				if( $sAction == 'update_form' )
					$sHtml .= '<input type="hidden" name="id" value="' . $sGetId . '" />';

				$sHtml .= '<div id="box-left">';
					$sHtml .= '<ul class="nav">';
						$sHtml .= '<li><a href="javascript:void(0);" class="active" data-id="1"><img src="images/icons/productos_datos_generales.png" alt=""><span>Noticia</span></a></li>
						<li><a href="javascript:void(0);" data-id="2"><img src="images/icons/productos_seo.png" alt=""><span>SEO</span></a></li>';
					$sHtml .= '</ul>';
				$sHtml .= '</div>';

				$sHtml .= '<div id="box-right">';
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

					$sHtml .= '<div class="tab-new" data-id="1" style="display: block;">
						<div class="fluid grid">
							<div class="box-tbl grid12">
								<div class="box-head">
									<div class="grid9"><h6>Noticia</h6></div>
									<div class="grid3">';
										$sHtml .= '<p style="position: relative; float: right; top: 3px; right: 43px;">';
											$sHtml .= '<label>Seleccionar idioma: </label>';
											$sHtml .=  tep_draw_pull_down_menu( 'idioma', $aComboIdiomas, '', 'class="change_idioma" data-id="1"');
										$sHtml .= '</p>';
									$sHtml .= '</div>
									<div class="clear"></div>
								</div>';

								// Recorremos los idiomas
								foreach( $aIdiomas as $aIdioma )
								{
									$sHtml .= '<div style="display: none;" class="tab-change-idma-1" id="change-idma-1-' . $aIdioma['id'] . '">';
										$sHtml .= tep_image( DIR_WS_CATALOG_LANGUAGES . $aIdioma['directory'] . '/images/' . $aIdioma['image'], $aIdioma['name'], '', '', 'style="position: absolute; top: 10px; right: 10px;"' );
										$sHtml .= '<div class="formRow">';
											$sHtml .= '<div class="grid3" style="margin: 0px;">';
												$sHtml .= '<label>Titulo:</label>';
											$sHtml .= '</div>';
											$sHtml .= '<div class="grid9">';
												$sHtml .= tep_draw_input_field( 'titulo[' . $aIdioma['id'] . ']', $oInfo->{$aIdioma['id']}['titulo'], 'style="width: 100%; margin: 0px;"' );
											$sHtml .= '</div>';
											$sHtml .= '<div class="clear"></div>';
										$sHtml .= '</div>';

										$sHtml .= '<div class="formRow">';
											$sHtml .= '<div class="grid3" style="margin: 0px;">';
												$sHtml .= '<label>Cuerpo de la noticia:</label>';
											$sHtml .= '</div>';
											$sHtml .= '<div class="grid9">';
												$sHtml .= tep_draw_textarea_field_tinymce( 'texto[' . $aIdioma['id'] . ']', 'soft', '70', '20', $oInfo->{$aIdioma['id']}['texto'] );
											$sHtml .= '</div>';
											$sHtml .= '<div class="clear"></div>';
										$sHtml .= '</div>';
									$sHtml .= '</div>';
								}

							$sHtml .= '</div>
						</div>';
						$sHtml .= '<div class="fluid grid">
							<div class="box-tbl grid12">
								<div class="box-head">
									<h6>Opciones de la noticia</h6>
									<div class="clear"></div>
								</div>
								<div class="formRow">
									<div style="margin: 0px;" class="grid3">
										<label>Estado:</label>
									</div>
									<div class="grid9">
										' . tep_draw_pull_down_menu( 'estado', $aComboEstados, ($oInfo->{3}['estado'] == '' ? 1 : $oInfo->{3}['estado']) ) . '
									</div>
									<div class="clear"></div>
								</div>
								<div class="formRow">
									<div class="grid3" style="margin: 0px;">
										<label>Fecha:</label>
									</div>
									<div class="grid9">
										' . tep_draw_input_field( 'fecha', ($oInfo->{3}['fecha'] == '' ? date('d/m/Y') : $oInfo->{3}['fecha']), 'size="2" style="width: 80px !important" maxlength="2" class="dxdatepicker cal-TextBox"' ) . '
									</div>
									<div class="clear"></div>
								</div>
							</div>
						</div>';
					$sHtml .= '</div>';

					$sHtml .= '<div class="tab-new" data-id="2" style="display: none;">
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
									array( 'title' => 'Título', 'text' => 'Título que se muestra en la cabecera del navegador.', 'row' => 'seo_title' ),
									array( 'title' => 'Palabras claves', 'text' => 'Palabras, frases clave o términos de búsqueda que los buscadores usaran para encontrar información.', 'row' => 'seo_keywords' ),
									array( 'title' => 'Descripción', 'text' => 'Descripcion que nos ayudará a indicar cúal es el contenido de nuestra página. Recomendamos que tenga entre 70 y 156 caracteres (incluyendo espacios).', 'row' => 'seo_description' )
								);

								// Recorremos los idiomas
								foreach( $aIdiomas as $aIdioma )
								{
									$sHtml .= '<div style="display: none;" class="tab-change-idma-2" id="change-idma-2-' . $aIdioma['id'] . '">';
										$sHtml .= tep_image( DIR_WS_CATALOG_LANGUAGES . $aIdioma['directory'] . '/images/' . $aIdioma['image'], $aIdioma['name'], '', '', 'style="position: absolute; top: 10px; right: 10px;"' );
										// Recorremos los campos
										foreach( $aCampos as $aCampo )
										{
											$sHtml .= '<div class="formRow">';
												$sHtml .= '<div class="grid3" style="margin: 0px;">';
													$sHtml .= '<label>' . $aCampo['title'] . '</label>';
													$sHtml .= '<span class="note" style="display: block; width: 100%; float: left; margin-top: -8px; font-style: italic; white-space: normal;">' . $aCampo['text'] . '</span>';
												$sHtml .= '</div>';
												$sHtml .= '<div class="grid9">';

													switch( $aCampo['row'] )
													{
														case 'seo_description':
														case 'seo_keywords':
															$sHtml .= tep_draw_textarea_field( $aCampo['row'] . '[' . $aIdioma['id'] . ']', '', 5, 5, $oInfo->{$aIdioma['id']}[$aCampo['row']], 'style="margin: 0px 0px 10px;"' );
														break;

														default:
															$sHtml .= tep_draw_input_field( $aCampo['row'] . '[' . $aIdioma['id'] . ']', $oInfo->{$aIdioma['id']}[$aCampo['row']], 'style="width: 100%; margin: 0px 0px 10px;"' ) . '<br/>';
														break;
													}

												$sHtml .= '</div>';
												$sHtml .= '<div class="clear"></div>';
											$sHtml .= '</div>';
										}

									$sHtml .= '</div>';
								}
							$sHtml .= '</div>
						</div>
					</div>';
				$sHtml .= '</div>';
			$sHtml .= '</form>';
		break;

		default:
			// Titular
			$sPageHeading = 'Historial de noticias';

			// Consulta
			$aDatos = tep_db_query( 'select id_noticia, titulo, estado, DATE_FORMAT(fecha, "%d/%m/%Y") as fecha
									 from noticia
									 where id_idioma = 3
									 order by id_noticia desc' );

			$sHtml = '<div class="box-tbl" style="width: 100%">';
				$sHtml .= '<div class="box-head">
					<h6>Historial de Noticias</h6>
					<div class="clear"></div>
				</div>';

				$sHtml .= '<table id="table" class="tAlt wGeneral tDefault" width="100%" cellspacing="0" cellpadding="0">
					<thead>
						<tr>
							<td width="15">ID</td>
							<td style="text-align: left;">Titulo</td>
							<td width="75">Fecha</td>
							<td width="75">Estado</td>
							<td width="195">Acciones</td>
						</tr>
					</thead>
					<tbody>';

						while( $aDato = tep_db_fetch_array( $aDatos ) )
						{
							$sHtml .= '<tr>';
								$sHtml .= '<td align="center">' . $aDato['id_noticia'] . '</td>';
								$sHtml .= '<td  align="left">' . $aDato['titulo'] . '</td>';
								$sHtml .= '<td align="center">' . $aDato['fecha'] . '</td>';
								$sHtml .= '<td align="center">
									<div style="cursor:pointer" class="stus" data-id=' . $aDato['id_noticia'] . ' data-status="' . $aDato['estado'] . '">
										<img width="10" height="10" src="images/icon_status_green' . ($aDato['estado'] == 0 ? '_light' : '') . '.gif" />
										<img width="10" height="10" src="images/icon_status_red' . ($aDato['estado'] == 1 ? '_light' : '') . '.gif" />
									</div>
								</td>';
								$sHtml .= '<td align="center">
									<div class="btn-group" style="display: inline-block; margin-bottom: -4px;">
										<a class="buttonS bDefault" data-toggle="dropdown" href="#">Acciones<span class="caret"></span></a>
										<ul class="dropdown-menu">
											<li><a href="' . $sUrl . '?a=update_form&id=' . $aDato['id_noticia'] . '"><span class="icos-pencil"></span>Editar</a></li>
											<li><a class="dlet" href="' . $sUrl . '?a=delete&id=' . $aDato['id_noticia'] . '"><span class="icos-trash"></span>Borrar</a></li>
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
					<a class="buttonS bGreen" href="' . $sUrl . '?a=add_form">Añadir nueva noticia</a>
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

			if( confirm( "¿Realmente deseas borrar la noticia?" ) )
				return true;
			else
				return false;
		});

		$( window ).resize(function()
		{
			$("#box-right").attr( "style", "" );

			if( $("#box-right").height() < $("#box-left").height() )
				$("#box-right").height( $("#box-left").height() - 365 );
		});
	</script>';

	include(DIR_WS_INCLUDES . 'application_bottom.php' );
?>
