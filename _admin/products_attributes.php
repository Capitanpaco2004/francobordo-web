<?php
	// Comprobamos si hemos incluido el archivo anteriormente no volver a cargar algunas librerias, mostrar cabeceras, etc. Usado en el attributerManager para mostrar formularios, etc
	if( !empty( $bLoadFileProductsAttributes ) )
		$bLoadFileProductsAttributes = true;
	else
		$bLoadFileProductsAttributes = false;

	// Librerias //
	if( !$bLoadFileProductsAttributes )
	{
		include( 'includes/application_top.php' );
		include( getcwd() . '/../includes/classes/attributes/option.class.php' );
		include( getcwd() . '/../includes/classes/attributes/functions.php' );
	}

	// Variables //
	$sUrlPage = 'products_attributes.php';
	$sGetPage = tep_db_prepare_input( $_GET['page'] ?? 1 );
	$sGetPage2 = tep_db_prepare_input( $_GET['page2'] ?? 1 );
	$sGetPage3 = tep_db_prepare_input( $_GET['page3'] ?? 1 );
	$sGetAction = tep_db_prepare_input( $_GET['a'] ?? '' );
	$sGetOrderby = tep_db_prepare_input( $_GET['orderby'] ?? '' );
	$sGetSort = tep_db_prepare_input( $_GET['sort'] ?? '' );
	$sJavascript = '';
	$sHtmlModule = '';
	$sHeadTitle = '';
	$sHeadSubTitle = '';
	$aDeleteTextos = array(
		'¿Estás seguro que deseas eliminar el filtro selecciona?.',
		'¿Estás seguro que deseas eliminar el/los filtro/s seleccionada/s?.'
	);
	$aHeadIcons = array();
	$objOption = new option();
	$aIdiomas = tep_get_languages();
	$aComboIdiomas = admin_getComboIdiomas();
	$aComboYesNo = admin_getComboSiNo();

	// Si las paginas estan vacias sera 1
	$sGetPage = $sGetPage == '' ? 1 : $sGetPage;
	$sGetPage2 = $sGetPage2 == '' ? 1 : $sGetPage2;
	$sGetPage3 = $sGetPage3 == '' ? 1 : $sGetPage3;

	// Otenemos el combobox de types //
	// Recorremos los tipos
	foreach( $objOption->getOptionsTypes() as $sType )
		$aComboTypes[] = array( 'id' => $sType, 'text' => $sType );

	// Si el order by no es asc o desc sera asc por defecto
	if( !in_array( strtolower( (string)($sGetSort ?? '') ), array( 'asc', 'desc' ) ) )
		$sGetSort = 'asc';

	// Actions //
	switch( $sGetAction )
	{
		case 'setColors':
			// Variables
			$nCantidad = tep_db_prepare_input( (int)$_GET['num'] );

			for( $nCont = 0; $nCont < $nCantidad; ++$nCont )
				echo '<input type="hidden" value="#000000" name="products_options_hexcolor_' . $nCont . '" id="color_hex_scol_' . $nCont . '"><input type="color" value="#000000" id="scol_' . $nCont . '" name="color"> ';

			exit();
		break;

		// Mostrar productos //
		case 'show_products':
			// Variables
			$sGetId = tep_db_prepare_input( $_GET['id'] );
			$sGetIdo = tep_db_prepare_input( $_GET['ido'] );
			$aGetId = array();

			// Obtenemos la opcion //
			// Consultamos
			$aDatoOption = tep_db_query( 'SELECT products_options_name, products_options_type FROM products_options WHERE products_options_id = "' . (int)$sGetIdo . '" and language_id = 3' );

			// Si no existe devolvemos
			if( tep_db_num_rows( $aDatoOption ) == 0 )
			{
				// Redireccionamos
				$messageStack->add_session( 'La opción que intentas editar no existe', 'warning' );
				tep_redirect( $sUrlPage );
			}

			// Obtenemos la opcion
			$aDatoOption = tep_db_fetch_array( $aDatoOption );

			// Obtenemos el valor si nos lo envian
			if( $sGetId != '' )
			{
				// Obtenemos valor
				$aDatoValue = tep_db_query( 'select products_options_values_name from products_options_values where products_options_values_id = "' . (int)$sGetId . '" and language_id = 3' );

				// Si no existe devolvemos
				if( tep_db_num_rows( $aDatoValue ) == 0 )
				{
					// Redireccionamos
					$messageStack->add_session( 'La opción que intentas editar no existe', 'warning' );
					tep_redirect( $sUrlPage );
				}

				// Obtenemos valor
				$aDatoValue = tep_db_fetch_array( $aDatoValue );
			}

			// Titulos, iconos y menu //
			$sHeadTitle = 'Productos para la opción "' . $aDatoOption['products_options_name'] . '"' . ($sGetId != '' ? ' y valor "' . $aDatoValue['products_options_values_name'] . '"' : '');
			$sHeadSubTitle = 'Lista de productos';

			// Volver
			$aHeadIcons[] = array( 'HREF' => tep_href_link( $sUrlPage, tep_get_all_get_params( ($sGetId != '' ? array( 'a', 'page3', 'id' ) : array('a', 'page3', 'id', 'ido')) ) . ($sGetId != '' ? 'a=value_list' : '') ), 'SRC' => 'images/icons/icon_back.png', 'TITLE' => 'Volver', 'EXTRA' => 'id="hotkey-return"' );

			// Obtenemos los productos //
			$sSql = 'SELECT DISTINCT pd.products_id, pd.products_name
					 FROM products_description pd
					 INNER JOIN products_attributes pa ON (pd.products_id = pa.products_id)
					 WHERE pd.language_id = 3 and pa.options_id = "' . (int)$sGetIdo . '"' . ($sGetId != '' ? ' and pa.options_values_id = "' . (int)$sGetId . '"' : '') . '
					 ORDER BY pd.products_name asc';

			// Le quitamos los tabuladores y saltos de linea para que splitpageesult funcione con el SQL //
			$sSql = preg_replace( '/[\r\n\t]+/', ' ', $sSql );

			// Sql para el count //
			$sSqlCount = 'SELECT COUNT(table_aux.products_id) as total FROM (' . $sSql . ') as table_aux';

			// Datos y paginacion
			$aDatoSplit = new splitPageResults( $sGetPage3, 20, $sSql, $nAux, $sSqlCount );
			$aDatos = tep_db_query( $sSql );

			// Variable para saber si hemos encontrado datos o no
			$bFoundDatos = tep_db_num_rows( $aDatos ) > 0;

			// Si no encontramos ninguna lista
			if( !$bFoundDatos )
				$messageStack->add( 'No existen productos asignados', 'warning', 'true' );

			// Mostramos las opciones
			$sHtmlModule .= '<div class="box-tbl" style="width: 100%">';
				$sHtmlModule .= '<div class="box-head">';
					$sHtmlModule .= '<h6>Listas de productos</h6>';
					$sHtmlModule .= '<div class="clear"></div>';
				$sHtmlModule .= '</div>';

				// Tabla //
				$sHtmlModule .= '<form method="get">';
					$sHtmlModule .= '<table id="table" class="tAlt wGeneral tDefault" width="100%" cellspacing="0" cellpadding="0">';
						$sHtmlModule .= '<thead>';
							$sHtmlModule .= '<tr>';
								$sHtmlModule .= '<td style="text-align: left;">Producto</td>';
								$sHtmlModule .= '<td style="text-align: center;" width="125">Acciones</td>';
							$sHtmlModule .= '</tr>';
						$sHtmlModule .= '</thead>';
						$sHtmlModule .= '<tbody>';

							// Recorremos las listas obtenidas //
							while( $aDato = tep_db_fetch_array( $aDatos ) )
							{
								$sHtmlModule .= '<tr>';
									$sHtmlModule .= '<td>' . $aDato['products_name'] . '</td>';

									$sHtmlModule .= admin_setTableTdAction( array(
										array( 'HREF' => tep_href_link( 'categories.php', 'action=new_product&pID=' . $aDato['products_id'] ), 'ATTR_HREF' => 'target="_blank"', 'CLASS' => 'icos-pencil', 'TEXT' => 'Editar producto' ),
										array( 'HREF' => '../product_info.php?products_id=' . $aDato['products_id'], 'CLASS' => 'icos-preview', 'TEXT' => 'Ver producto en tienda', 'ATTR_HREF' => 'target="_blank"' )
									));
								$sHtmlModule .= '</tr>';
							}

						// Fin de tabla y paginacion //
						$sHtmlModule .= '</tbody>';
					$sHtmlModule .= '</table>';
				$sHtmlModule .= '</form>';
				$sHtmlModule .= $aDatoSplit->showPaginateTable( tep_get_all_get_params( array('page3') ), 'page3' );
			$sHtmlModule .= '</div>';
		break;

		// Eliminar value //
		case 'value_delete':
			// Variables
			$sGetId = tep_db_prepare_input( $_GET['id'] );
			$sGetIdo = tep_db_prepare_input( $_GET['ido'] );
			$aGetId = array();

			// Obtenemos la opcion //
			// Consultamos
			$aDatoOption = tep_db_query( 'SELECT products_options_name, products_options_type FROM products_options WHERE products_options_id = "' . (int)$sGetIdo . '" and language_id = 3' );

			// Si no existe devolvemos
			if( tep_db_num_rows( $aDatoOption ) == 0 )
			{
				// Redireccionamos
				if( isAjax() )
				{
					echo $messageStack->show( array( 'class' => 'wrng', 'text' => 'La opción que intentas editar no existe' ) );
					exit();
				}

				// Redireccionamos
				$messageStack->add_session( 'La opción que intentas editar no existe', 'warning' );
				tep_redirect( $sUrlPage );
			}

			// Obtenemos la opcion
			$aDatoOption = tep_db_fetch_array( $aDatoOption );

			// Iniciamos el tipo //
			include( getcwd() . '/../includes/classes/attributes/types/' . (is_numeric( $aDatoOption['products_options_type'] ) ? 'combobox' : $aDatoOption['products_options_type']) . '/' . (is_numeric( $aDatoOption['products_options_type'] ) ? 'combobox' : $aDatoOption['products_options_type']) . '.class.php' );
			eval( '$objValue = new option_' . (is_numeric( $aDatoOption['products_options_type'] ) ? 'combobox' : $aDatoOption['products_options_type']) . '();' );

			// Si no nos envian un array, pasamos el id al array
			if( !is_array( $sGetId ) )
				$aGetId[] = $sGetId;
			else
				$aGetId = $sGetId;

			// Recorremos los id para eliminar definitivamente
			foreach( $aGetId as $sId )
			{
				// Comprobamos que exista //
				// Obtenemos valor
				$aDatosValor = tep_db_query( 'SELECT products_options_values_name, language_id, products_options_values_id FROM products_options_values WHERE products_options_values_id = "' . (int)$sId . '"' );

				// Si no existe devolvemos
				if( tep_db_num_rows( $aDatosValor ) == 0 )
					continue;

				tep_db_query( 'DELETE FROM products_options_values WHERE products_options_values_id = "' . (int)$sId . '"' );
				tep_db_query( 'DELETE FROM products_options_values_to_products_options WHERE products_options_values_id = "' . (int)$sId . '"' );
				tep_db_query( 'DELETE FROM products_attributes WHERE options_values_id = "' . (int)$sId . '"' );

				// Evento al eliminar
				if( method_exists( $objValue, 'adminValueDelete' ) )
					$sHtmlModule .= $objValue->adminValueDelete($sId);
			}

			// Redireccionamos
			$messageStack->addSession( 'mensaje', 'El valor se ha eliminado correctamente', 'success' );
			tep_redirect( tep_href_link( $sUrlPage, tep_get_all_get_params( array( 'id', 'a' ) ) . 'a=value_list' ) );
		break;

		// Editar/Añadir valor //
		case 'value_add':
		case 'value_edit':
			// Si no es una petición POST
			if( $_SERVER['REQUEST_METHOD'] != 'POST' )
			{
				$messageStack->addSession( 'mensaje', 'Error intentelo de nuevo.', 'error' );
				tep_redirect( $_SERVER['HTTP_REFERER'] );
			}

			// Variables
			$sPostAttributeManager = tep_db_prepare_input( $_POST['attributermanager'] );
			$sPostId = tep_db_prepare_input( $_POST['id'] );
			$sGetIdo = tep_db_prepare_input( $_GET['ido'] );
			$aPermitidos = array( 'products_options_values_name' );
			$aDatosIdioma = array();
			$sId = false;

			// Obtenemos la opcion //
			// Consultamos
			$aDatoOption = tep_db_query( 'SELECT products_options_name, products_options_type FROM products_options WHERE products_options_id = "' . (int)$sGetIdo . '" and language_id = 3' );

			// Si no existe devolvemos
			if( tep_db_num_rows( $aDatoOption ) == 0 )
			{
				// Redireccionamos
				if( isAjax() )
				{
					echo $messageStack->show( array( 'class' => 'wrng', 'text' => 'La opción que intentas editar no existe' ) );
					exit();
				}

				// Redireccionamos
				$messageStack->add_session( 'La opción que intentas editar no existe', 'warning' );
				tep_redirect( $sUrlPage );
			}

			// Obtenemos la opcion
			$aDatoOption = tep_db_fetch_array( $aDatoOption );

			// Si estamos insertando añadimos ID
			if( $sGetAction == 'value_add' )
			{
				$aDatos = tep_db_query( 'select max(products_options_values_id) + 1 as next_id from products_options_values' );
				$aDatos = tep_db_fetch_array($aDatos);
				$sPostId = $aDatos['next_id'];
			}

			// Obtenemos todas los valores que tenemos con el idioma
			$aDatos = tep_db_query( 'select language_id from products_options_values where products_options_values_id = "' . (int)$sPostId . '"' );
			while( $aDato = tep_db_fetch_array( $aDatos ) )
				$aDatosIdioma[] = $aDato['language_id'];

			// Recorremos idiomas
			foreach( $aIdiomas as $aIdioma )
			{
				// Reseteamos
				$aSql = array();

				// Recorremos post
				foreach( $_POST as $key => $value )
				{
					// Si el campo es permitido
					if( in_array( $key, $aPermitidos ) )
					{
						// Añadimos el campo
						$aSql[$key] = tep_db_prepare_input( $value[$aIdioma['id']] );
					}
				}

				// Color //
				if( $_POST['num_color'] > 0 )
					for( $nCont = 0; $nCont < $_POST['num_color']; ++$nCont )
						eval( '$sHexs .= $_POST["products_options_hexcolor_' . $nCont . '"] . ", ";' );

				$aSql['products_options_hexcolor'] = tep_db_prepare_input( $_POST['products_options_hexcolor'] . ($sHexs != '' ? ', ' . substr( $sHexs, 0, -2 ) : '')  );
				// FIN; Color //

				// Insertamos o actualizamos comprobando si ya existe
				if( !in_array( $aIdioma['id'], $aDatosIdioma ) )
				{
					$aSql['language_id'] = $aIdioma['id'];
					$aSql['products_options_values_id'] = $sPostId;

					// Insertamos
					tep_db_perform( 'products_options_values', $aSql );

					// Si estamos insertando y es el primer id guardamos el id y asinamos la relacion opcion -> valor
					if( $sGetAction == 'value_add' && $sId === false )
					{
						$sId = tep_db_insert_id();

						// Insertamos la relacion opcion -> valor
						tep_db_perform( 'products_options_values_to_products_options', array( 'products_options_id' => $sGetIdo, 'products_options_values_id' => $sPostId ) );
					}
				}
				else
					tep_db_perform( 'products_options_values', $aSql, 'update', 'products_options_values_id = ' . $sPostId . ' and language_id = ' . $aIdioma['id'] );
			}

			// Iniciamos el tipo //
			include( getcwd() . '/../includes/classes/attributes/types/' . (is_numeric( $aDatoOption['products_options_type'] ) ? 'combobox' : $aDatoOption['products_options_type']) . '/' . (is_numeric( $aDatoOption['products_options_type'] ) ? 'combobox' : $aDatoOption['products_options_type']) . '.class.php' );
			eval( '$objValue = new option_' . (is_numeric( $aDatoOption['products_options_type'] ) ? 'combobox' : $aDatoOption['products_options_type']) . '();' );

			// Evento al insertar
			if( method_exists( $objValue, 'adminValueAdd' ) )
				$sHtmlModule .= $objValue->adminValueAdd($sPostId);

			// Mensaje //
			$sMensaje = 'El valor se ' . ( $sGetAction == 'value_edit' ? 'actualizo' : 'inserto' ) . ' correctamente';

			// Si lo hemos añadido desde el attributerManager
			if( $sPostAttributeManager != '' )
			{
				echo json_encode( array(
					'id' => $sPostId,
					'nombre' => $_POST['products_options_values_name'][3],
					'mensaje' => $messageStack->show( array( 'class' => 'crrt', 'text' => $sMensaje ) )
				) );

				exit();
			}

			// Redireccionamos
			if( isAjax() )
			{
				echo $messageStack->show( array( 'class' => 'crrt', 'text' => $sMensaje ) );
				exit();
			}

			// Redireccionamos
			$messageStack->add_session( $sMensaje, 'success' );
			tep_redirect( tep_href_link( $sUrlPage, tep_get_all_get_params( array( 'id', 'a' ) ) . 'a=value_list' ) );
		break;

		// Editar/Añadir valores formulario //
		case 'value_add_form':
		case 'value_edit_form':
			// Variables //
			$bAllowAdd = true;
			$sGetIdo = tep_db_prepare_input( $_GET['ido'] );
			$sGetId = tep_db_prepare_input( $_GET['id'] );
			$bEdit = ($sGetAction == 'value_edit_form' ? true : false);

			// Obtenemos la opcion //
			// Consultamos
			$aDatoOption = tep_db_query( 'SELECT products_options_name, products_options_type FROM products_options WHERE products_options_id = "' . (int)$sGetIdo . '" and language_id = 3' );

			// Si no existe devolvemos
			if( tep_db_num_rows( $aDatoOption ) == 0 )
			{
				$messageStack->addSession( 'mensaje', 'La opción que intentas editar no existe', 'warning' );
				tep_redirect( tep_href_link( $sUrlPage ) );
			}

			// Obtenemos la opcion
			$aDatoOption = tep_db_fetch_array( $aDatoOption );

			// Iniciamos el tipo //
			include( getcwd() . '/../includes/classes/attributes/types/' . (is_numeric( $aDatoOption['products_options_type'] ) ? 'combobox' : $aDatoOption['products_options_type']) . '/' . (is_numeric( $aDatoOption['products_options_type'] ) ? 'combobox' : $aDatoOption['products_options_type']) . '.class.php' );
			eval( '$objValue = new option_' . (is_numeric( $aDatoOption['products_options_type'] ) ? 'combobox' : $aDatoOption['products_options_type']) . '();' );

			// Si no se le permite valores comprobamos que solo exista una opción
			if( !$objValue->getAllowValues() )
			{
				// Comprobamos si tiene valor
				$aDatos = tep_db_query( 'SELECT products_options_values_to_products_options_id FROM products_options_values_to_products_options WHERE products_options_id = "' . (int)$sGetIdo . '"' );

				// Si contiene valor
				if( tep_db_num_rows( $aDatos ) > 0 )
				{
					$sHtmlModule .= $messageStack->show( array( 'class' => 'info', 'text' => 'Solo se le permite añadir un valor ha está opción ya que será introducida por el usuario.' ) );
					$bAllowAdd = false;
				}
			}

			// Comprobamos si puede insertarse valor
			if( $bAllowAdd )
			{
				// Objeto info
				$oInfo = createObjectInfo( 'products_options_values', array( 'IDIOMA' => true ) );

				// Si estamos editando
				if( $bEdit )
				{
					// Obtenemos valor
					$aDatosValor = tep_db_query( 'SELECT products_options_values_name, products_options_hexcolor, language_id, products_options_values_id FROM products_options_values WHERE products_options_values_id = "' . (int)$sGetId . '"' );

					// Si no existe devolvemos
					if( tep_db_num_rows( $aDatosValor ) == 0 )
					{
						$messageStack->addSession( 'mensaje', 'El valor que intentas editar no existe', 'warning' );
						tep_redirect( tep_href_link( $sUrlPage ) );
					}

					// Obtenemos los datos
					while( $aDato = tep_db_fetch_array( $aDatosValor ) )
						$oInfo->{$aDato['language_id']} = $aDato;
				}

				// Titulos, iconos y menu //
				$sHeadTitle = ($bEdit ? 'Editar valor "' . $oInfo->{3}['products_options_values_name'] . '"' : 'Insertar valor nuevo') . ' para la opción "' . $aDatoOption['products_options_name'] . '"';
				$sHeadSubTitle = $bEdit ? 'Editar valor' : 'Insertar valor';

				// Si estamos editando el boton de guardar por ajax
				if( $bEdit )
					$aHeadIcons[] = array( 'EXTRA' => 'id="save_ajax"', 'SRC' => 'images/icons/icon_save.png', 'TITLE' => 'Guardar' );

				// Guardar y volver
				$aHeadIcons[] = array( 'EXTRA' => 'onclick=\'$("#form").find("input[type=submit]").trigger("click")\'', 'SRC' => 'images/icons/icon_save_return.png', 'TITLE' => 'Guardar y volver' );
				$aHeadIcons[] = array( 'HREF' => tep_href_link( $sUrlPage, tep_get_all_get_params( array( 'id', 'a' ) ) . 'a=value_list' ), 'SRC' => 'images/icons/icon_back.png', 'TITLE' => 'Volver', 'EXTRA' => 'id="hotkey-return"' );

				// Obtenemos la url del form
				$sUrlForm = $sUrlPage . '?' . preg_replace( '/.+\.php\?/i', '', tep_href_link( $sUrlPage, tep_get_all_get_params( array( 'a' ) ) . 'a=' . str_replace( '_form', '', $sGetAction ) ) );

				// Html //
				$sHtmlModule .= tep_draw_form( 'form', $sUrlForm, '', 'post', 'id="form" class="autotab"' );
					// Si estamos editando añadimos el ID
					if( $bEdit )
						$sHtmlModule .= '<input type="hidden" name="id" value="' . $sGetId . '" />';

					// Si estamos llamandolo desde el attributerManager
					if( $bLoadFileProductsAttributes )
						$sHtmlModule .= '<input type="hidden" name="attributermanager" value="true" />';

					$sHtmlModule .= '<div class="fluid grid">
						<div class="box-tbl grid12">
							<div class="box-head">
								<div class="grid12"><h6>Valor</h6></div>
								<div class="clear"></div>
							</div>';

							// Recorremos los idiomas
							foreach( $aIdiomas as $aIdioma )
							{
								$sHtmlModule .= '<div class="tab-change-idma-1" id="change-idma-1-' . $aIdioma['id'] . '">';
									$sHtmlModule .= '<div class="formRow">';
										$sHtmlModule .= '<div class="grid2" style="margin: 0px; position: relative;">';
										$sHtmlModule .= tep_image( DIR_WS_CATALOG_LANGUAGES . $aIdioma['directory'] . '/images/' . $aIdioma['image'], $aIdioma['name'], '', '', 'style="position: absolute; top: 6px; left: 0px;"' );
											$sHtmlModule .= '<label style="padding-left: 30px;">Nombre:</label>';
										$sHtmlModule .= '</div>';
										$sHtmlModule .= '<div class="grid10">';
											$sHtmlModule .= tep_draw_input_field( 'products_options_values_name[' . $aIdioma['id'] . ']', $oInfo->{$aIdioma['id']}['products_options_values_name'], 'style="width: 100%; margin: 0px;" required' );
										$sHtmlModule .= '</div>';
										$sHtmlModule .= '<div class="clear"></div>';
									$sHtmlModule .= '</div>';
								$sHtmlModule .= '</div>';
							}

							$aHex = explode( ', ', $oInfo->{3}['products_options_hexcolor'] );
							$nNumHex = count( $aHex );

							$sHtmlModule .= '<div class="formRow">';
								$sHtmlModule .= '<div class="grid2" style="margin: 0px;">';
									$sHtmlModule .= '<label>
														Color/es:
														<p>Nº Colores Adicionales: <input type="text" name="num_color" id="setNum" value="' . ($nNumHex - 1) . '" style="margin: 0px; width: 29px;text-align: center;font-size: 10px;"></p>
													</label>';
								$sHtmlModule .= '</div>';

								$sHtmlModule .= '<div class="grid9">';

									$sHtmlModule .= '<input type="hidden" id="color_hex" name="products_options_hexcolor" value="' . $aHex[0] . '">';
									$sHtmlModule .= '<input name="color" type="color" value="' . $aHex[0] . '"/>';
									$sHtmlModule .= '<div id="setColor">';
										for( $nCont = 1; $nCont < $nNumHex; ++$nCont )
										{
											$sHtmlModule .= '<input type="hidden" id="color_hex_scol_' . ($nCont - 1) . '" name="products_options_hexcolor_' . ($nCont - 1) . '" value="' . $aHex[$nCont] . '">';
											$sHtmlModule .= '<input id="scol_' . ($nCont - 1) . '" name="color" type="color" value="' . $aHex[$nCont] . '"/>';
										}
									$sHtmlModule .= '</div>';

								$sHtmlModule .= '</div>';
								$sHtmlModule .= '<div class="clear"></div>';
							$sHtmlModule .= '</div>';
						$sHtmlModule .= '</div>
					</div>';

					// Obtenemos el formulario
					if( method_exists( $objValue, 'adminValueShowForm' ) )
						$sHtmlModule .= $objValue->adminValueShowForm($oInfo, $bEdit);

					// Cargamos Javascript
					if( method_exists( $objValue, 'adminLoadJavascript' ) )
						$sJavascript .= $objValue->adminLoadJavascript();

					$sHtmlModule .= '<div class="box-bton" style="display: none;"><input value="Aceptar" type="submit" href="javascript:void(0);" class="buttonS bGreen"/><div class="clear"></div></div>
				</form>';
			}
		break;

		// Lista de valores para una opcion //
		case 'value_list':
			// Variables //
			$sGetIdo = tep_db_prepare_input( $_GET['ido'] );
			$bAllowAdd = true;

			// Obtenemos la opcion //
			// Consultamos
			$aDatoOption = tep_db_query( 'SELECT products_options_name, products_options_type FROM products_options WHERE products_options_id = "' . (int)$sGetIdo . '" and language_id = 3' );

			// Si no existe devolvemos
			if( tep_db_num_rows( $aDatoOption ) == 0 )
			{
				$messageStack->addSession( 'mensaje', 'La opción que intentas editar no existe', 'warning' );
				tep_redirect( tep_href_link( $sUrlPage ) );
			}

			// Obtenemos la opcion
			$aDatoOption = tep_db_fetch_array( $aDatoOption );

			// Iniciamos el tipo //
			include( getcwd() . '/../includes/classes/attributes/types/' . (is_numeric( $aDatoOption['products_options_type'] ) ? 'combobox' : $aDatoOption['products_options_type']) . '/' . (is_numeric( $aDatoOption['products_options_type'] ) ? 'combobox' : $aDatoOption['products_options_type']) . '.class.php' );
			eval( '$objValue = new option_' . (is_numeric( $aDatoOption['products_options_type'] ) ? 'combobox' : $aDatoOption['products_options_type']) . '();' );

			// Si no se le permite valores comprobamos que solo exista una opción
			if( !$objValue->getAllowValues() )
			{
				// Comprobamos si tiene valor
				$aDatos = tep_db_query( 'SELECT products_options_values_to_products_options_id FROM products_options_values_to_products_options WHERE products_options_id = "' . (int)$sGetIdo . '"' );

				// Si contiene valor
				if( tep_db_num_rows( $aDatos ) > 0 )
					$bAllowAdd = false;
			}

			// Titulos, iconos y menu //
			$sHeadTitle = 'Valores para la opción ' . $aDatoOption['products_options_name'];
			$sHeadSubTitle = 'Lista de valores';

			// Nuevo y volver
			if( $bAllowAdd )
				$aHeadIcons[] = array( 'HREF' => tep_href_link( $sUrlPage, tep_get_all_get_params( array( 'a', 'orderby', 'sort', 'search_value' ) ) . 'a=value_add_form' ), 'SRC' => 'images/icons/icon_add.png', 'TITLE' => 'Añadir valor', 'EXTRA' => 'id="hotkey-new"' );

			$aHeadIcons[] = array( 'HREF' => tep_href_link( $sUrlPage, tep_get_all_get_params( array( 'ido', 'a', 'page2', 'orderby', 'sort', 'search_value' ) ) ), 'SRC' => 'images/icons/icon_back.png', 'TITLE' => 'Volver', 'EXTRA' => 'id="hotkey-return"' );

			// Html para el boton masivo
			$sHtmlActionMasivo = '<div id="opciones_masivas" style="float: left; margin-top: -6px; margin-right: 10px;">
				<div class="btn-group">
					<a data-toggle="dropdown" class="buttonS bLightBlue">Seleccione opción masiva<span class="caret"></span></a>
					<ul class="dropdown-menu">
						<li><a href="javascript:void(0);" data-action="' . tep_href_link( $sUrlPage, tep_get_all_get_params( array( 'orderby', 'sort', 'search_value', 'a', 'page2' ) ) . 'page2=' . $sGetPage2 . '&a=value_delete' ) . '" data-text="¿Deseas eliminar estos valores? Recuerda que si tiene asignado productos estos se perderán."><span class="icos-trash"></span>Eliminar</a></li>
					</ul>
				</div>
			</div>';

			// Variables //
			$sGetSearchValue = tep_db_prepare_input( $_GET['search_value'] );
			$sWhere = '';

			// Where //
			if( $sGetSearchValue != '' )
				$sWhere = 'where ';

			if( $sGetSearchValue != '' )
			{
				if( $sWhere != 'where ' )
					$sWhere .= ' and';

				$sWhere .= ' LOWER(pov.products_options_values_name) LIKE "%' . strtolower( $sGetSearchValue ) . '%"';
			}

			// Order by //
			if( $sGetOrderby == 'products_options_type' )
				$sOrderby = 'pov.products_options_values_name ' . $sGetSort;
			else
				$sOrderby = 'pov.products_options_values_name ASC';

			// Si estamos ordenando o filtrando, mostramos el boton de limpiar filtro //
			if( $sGetOrderby != '' || $sWhere != '' )
				$aHeadIcons[] = array( 'HREF' => tep_href_link( $sUrlPage, tep_get_all_get_params( array( 'page2', 'orderby', 'sort', 'search_value' ) ) ), 'SRC' => 'images/icons/icon_clear_filter.png', 'TITLE' => 'Limpiar filtro' );

			// Obtenemos los valores //
			$sSql = 'SELECT pov.products_options_values_id, pov.products_options_values_name, pov.products_options_hexcolor, count(pa.products_id) as cantidad_productos
					 FROM products_options_values pov
					 INNER JOIN products_options_values_to_products_options pov2po on (pov2po.products_options_values_id = pov.products_options_values_id AND pov2po.products_options_id = "' . (int)$sGetIdo . '")
					 LEFT JOIN products_attributes pa ON (pa.options_values_id = pov.products_options_values_id)
					 ' . ($sWhere == '' ? ' where ' : $sWhere . ' and ') . ' pov.language_id = 3
					 GROUP BY pov.products_options_values_id
					 ORDER BY ' . $sOrderby;

			// Le quitamos los tabuladores y saltos de linea para que splitpageesult funcione con el SQL //
			$sSql = preg_replace( '/[\r\n\t]+/', ' ', $sSql );

			// Sql para el count //
			$sSqlCount = 'SELECT COUNT(table_aux.products_options_values_id) as total FROM (' . $sSql . ') as table_aux';

			// Datos y paginacion
			$aDatoSplit = new splitPageResults( $sGetPage2, 20, $sSql, $nAux, $sSqlCount );
			$aDatos = tep_db_query( $sSql );

			// Variable para saber si hemos encontrado datos o no
			$bFoundDatos = tep_db_num_rows( $aDatos ) > 0;

			// Si no encontramos ninguna lista
			if( !$bFoundDatos )
				$messageStack->add( 'No existen valores con el filtro seleccionado o todavía no has insertado ningun valor', 'warning', 'true' );

			// Si no se le permite añadir
			if( !$bAllowAdd )
				$sHtmlModule .= $messageStack->show( array( 'class' => 'info', 'text' => 'Solo se le permite añadir un valor ha está opción ya que será introducida por el usuario.' ) );

			// Mostramos las opciones
			$sHtmlModule .= '<div class="box-tbl" style="width: 100%">';
				$sHtmlModule .= '<div class="box-head">';
					$sHtmlModule .= '<h6>Listas de valores</h6>';
					$sHtmlModule .= '<a title="Filtrar" data-id="1" href="javascript:void(0);" class="tOptions filter-togle"><img alt="" src="theme/web/images/icons/usual/icon-cog3.png"></a>';
					$sHtmlModule .= '<div class="clear"></div>';
				$sHtmlModule .= '</div>';

				// Filtro //
				$sHtmlModule .= '<div data-id="1" class="fluid grid tablePars">';
					$sHtmlModule .= tep_draw_form( 'form', $sUrlPage, '', 'get' );
						$sHtmlModule .= '<div class="grid12">';
							$sHtmlModule .= '<input type="hidden" name="a" value="value_list" />';
							$sHtmlModule .= '<input type="hidden" name="ido" value="' . $sGetIdo . '" />';
							$sHtmlModule .= '<input type="hidden" name="page" value="' . $sGetPage . '" />';
							$sHtmlModule .= '<div class="formRow" style="border: none; padding: 7px 16px;">';
								$sHtmlModule .= '<div class="grid2"><label>Valor:</label></div>';
								$sHtmlModule .= '<div class="grid10">';
										$sHtmlModule .= tep_draw_input_field( 'search_value', $sGetSearchValue, 'placeholder="Introduce valor..."' );
								$sHtmlModule .= '</div>';
								$sHtmlModule .= '<div class="clear"></div>';
							$sHtmlModule .= '</div>';

							$sHtmlModule .= '<div class="formRow" style="border: none; padding: 7px 16px;">';
								$sHtmlModule .= '<div class="grid12">';
									$sHtmlModule .= '<input type="submit" value="Filtrar" class="buttonS bGreen" style="cursor: pointer;"/>';
								$sHtmlModule .= '</div>';
								$sHtmlModule .= '<div class="clear"></div>';
							$sHtmlModule .= '</div>';
						$sHtmlModule .= '</div>';
					$sHtmlModule .= '</form>';
				$sHtmlModule .= '</div>';

				// Tabla //
				$sHtmlModule .= '<form method="get">';
					$sHtmlModule .= '<table id="table" class="tAlt wGeneral tDefault" width="100%" cellspacing="0" cellpadding="0">';
						$sHtmlModule .= '<thead>';
							$sHtmlModule .= '<tr>';
								$sHtmlModule .= '<td width="17" class="check negative"><input class="all-checked" type="checkbox"/></td>';
								$sHtmlModule .= '<td style="text-align: left;">' . admin_setTableTdSort( $sUrlPage, 'products_options_values_name', 'Nombre', array('page2', 'orderby', 'sort') ) . '</td>';

								// Mostramos valor tabla
								if( method_exists( $objValue, 'adminValueListTableHead' ) )
									$sHtmlModule .= $objValue->adminValueListTableHead();

								$sHtmlModule .= '<td style="text-align: center;" width="50">Productos</td>';
								$sHtmlModule .= '<td style="text-align: center;" width="125">Acciones</td>';
							$sHtmlModule .= '</tr>';
						$sHtmlModule .= '</thead>';
						$sHtmlModule .= '<tbody>';

							// Recorremos las listas obtenidas //
							while( $aDato = tep_db_fetch_array( $aDatos ) )
							{
								$sHtmlModule .= '<tr data-src="' . tep_href_link( $sUrlPage, tep_get_all_get_params( array( 'orderby', 'sort', 'search_value', 'a', 'page2' ) ) . 'page2=' . $sGetPage2 . '&a=value_edit_form&id=' . $aDato['products_options_values_id'] ) . '" class="dbclick">';
									$sHtmlModule .= '<td class="check negative" align="center"><input name="id[]" value="' . $aDato['products_options_values_id'] . '" type="checkbox"/></td>';
									$sHtmlModule .= '<td>';

										if( $aDato['products_options_hexcolor'] != '' )
										{
											$aHex = explode( ', ', $aDato['products_options_hexcolor'] );
											$sHtmlModule .= '<div style="float: left; overflow: hidden; margin-right: 10px; width: 18px; height: 18px;">';
											for( $nCont = 0; $nCont < count( $aHex ); ++$nCont )
												$sHtmlModule .= '<span style="background: ' . $aHex[$nCont] . '; display: block; float: left; height: 100%; width: ' . (100 / count( $aHex )) . '%;"></span>';
											$sHtmlModule .= '</div>';
										}

										$sHtmlModule .= $aDato['products_options_values_name'];
									$sHtmlModule .= '</td>';

									// Mostramos valor tabla
									if( method_exists( $objValue, 'adminValueListTableBody' ) )
										$sHtmlModule .= $objValue->adminValueListTableBody($aDato);

									// Mostramos la cantidad de productos que tienen asignada esta opcin
									$sHtmlModule .= '<td style="text-align: center;">' . $aDato['cantidad_productos'] . '</td>';

									$sHtmlModule .= admin_setTableTdAction( array(
										array( 'HREF' => tep_href_link( $sUrlPage, tep_get_all_get_params( array( 'a', 'page2' ) ) . 'page2=' . $sGetPage2 . '&a=value_edit_form&id=' . $aDato['products_options_values_id'] ), 'CLASS' => 'icos-pencil', 'TEXT' => 'Editar' ),
										array( 'HREF' => tep_href_link( $sUrlPage, tep_get_all_get_params( array( 'a', 'page2' ) ) . 'page2=' . $sGetPage . '&a=show_products&ido=' . $sGetIdo . '&id=' . $aDato['products_options_values_id'] ), 'CLASS' => 'icos-preview', 'TEXT' => 'Ver productos' ),
										array( 'HREF' => tep_href_link( $sUrlPage, tep_get_all_get_params( array( 'orderby', 'sort', 'search_value', 'a', 'page2' ) ) . 'page2=' . $sGetPage2 . '&a=value_delete&id=' . $aDato['products_options_values_id'] ), 'CLASS' => 'icos-trash', 'TEXT' => 'Eliminar', 'CLASS_HREF' => 'confirm', 'ATTR_HREF' => 'data-text="¿Deseas eliminar el valor? Recuerda que si tiene asignado productos estos se perderán."' )
									));
								$sHtmlModule .= '</tr>';
							}

						// Fin de tabla y paginacion //
						$sHtmlModule .= '</tbody>';
					$sHtmlModule .= '</table>';
				$sHtmlModule .= '</form>';
				$sHtmlModule .= $aDatoSplit->showPaginateTable( tep_get_all_get_params( array('page2') ), 'page2', $sHtmlActionMasivo );
			$sHtmlModule .= '</div>';
		break;

		// Eliminar opcion //
		case 'option_delete':
			// Variables
			$sGetId = tep_db_prepare_input( $_GET['id'] );
			$sPostId = tep_db_prepare_input( $_POST['id'] );

			// Si nos envian por get creamos el array
			if( $sGetId != '' )
				$sPostId = array( $sGetId );

			// Recorremos los id para eliminar definitivamente
			if( is_array( $sPostId ) )
			{
				foreach( $sPostId as $sId )
				{
					tep_db_query( 'DELETE pov.*
								   FROM products_options_values pov
								   INNER JOIN products_options_values_to_products_options pov2po ON (pov2po.products_options_values_id = pov.products_options_values_id)
								   WHERE pov2po.products_options_id = "' . (int)$sId . '"' );

					tep_db_query( 'DELETE FROM products_options_values_to_products_options WHERE products_options_id = "' . (int)$sId . '"' );

					tep_db_query( 'DELETE FROM products_options WHERE products_options_id = "' . (int)$sId . '"' );

					tep_db_query( 'DELETE FROM products_attributes WHERE options_id = "' . (int)$sId . '"' );
				}
			}

			// Redireccionamos
			$messageStack->addSession( 'mensaje', 'La opción se ha eliminado correctamente', 'success' );
			tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;


		// Editar/Añadir opcion //
		case 'option_add':
		case 'option_edit':
			// Si no es una petición POST
			if( $_SERVER['REQUEST_METHOD'] != 'POST' )
			{
				$messageStack->addSession( 'mensaje', 'Error intentelo de nuevo.', 'error' );
				tep_redirect( $_SERVER['HTTP_REFERER'] );
			}

			// Variables
			$sPostAttributeManager = tep_db_prepare_input( $_POST['attributermanager'] );
			$sPostId = tep_db_prepare_input( $_POST['id'] );
			$aDenegados = array( 'idioma', 'id', 'products_options_track_stock', 'products_options_type', 'attributermanager' );
			$aDatosIdioma = array();
			$sId = false;

			// Si estamos insertando añadimos ID
			if( $sGetAction == 'option_add' )
			{
				$aDatos = tep_db_query( 'select max(products_options_id) + 1 as next_id from products_options' );
				$aDatos = tep_db_fetch_array($aDatos);
				$sPostId = $aDatos['next_id'];
			}

			// Obtenemos todas las opciones que tenemos con el idioma
			$aDatos = tep_db_query( 'select language_id from products_options where products_options_id = "' . (int)$sPostId . '"' );
			while( $aDato = tep_db_fetch_array( $aDatos ) )
				$aDatosIdioma[] = $aDato['language_id'];

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

				// Stock
				$aSql['products_options_track_stock'] = tep_db_prepare_input( $_POST['products_options_track_stock'] ) == 'on' ? 1 : 0;

				// Type
				$aSql['products_options_type'] = tep_db_prepare_input( $_POST['products_options_type'] );

				// Insertamos o actualizamos comprobando si ya existe
				if( !in_array( $aIdioma['id'], $aDatosIdioma ) )
				{
					$aSql['language_id'] = $aIdioma['id'];
					$aSql['products_options_id'] = $sPostId;

					// Insertamos
					tep_db_perform( 'products_options', $aSql );
				}
				else
					tep_db_perform( 'products_options', $aSql, 'update', 'products_options_id = ' . $sPostId . ' and language_id = ' . $aIdioma['id'] );
			}

			// Mensaje //
			$sMensaje = 'La opcion se ' . ( $sGetAction == 'option_edit' ? 'actualizo' : 'inserto' ) . ' correctamente';

			// Si lo hemos añadido desde el attributerManager
			if( $sPostAttributeManager != '' )
			{
				echo json_encode( array(
					'id' => $sPostId,
					'nombre' => $_POST['products_options_name'][3],
					'mensaje' => $messageStack->show( array( 'class' => 'crrt', 'text' => $sMensaje ) )
				) );

				exit();
			}

			// Redireccionamos
			if( isAjax() )
			{
				echo $messageStack->show( array( 'class' => 'crrt', 'text' => $sMensaje ) );
				exit();
			}

			// Redireccionamos
			$messageStack->add_session( $sMensaje, 'success' );
			tep_redirect( $sUrlPage );

			exit();
		break;

		// Editar/Añadir opcion formulario //
		case 'option_add_form':
		case 'option_edit_form':
			// Variables
			$sGetId = tep_db_prepare_input( $_GET['id'] );
			$bEdit = ($sGetAction == 'option_edit_form' ? true : false);

			// Objeto info
			$oInfo = createObjectInfo( 'products_options', array( 'IDIOMA' => true ) );

			// Si estamos editando
			if( $bEdit )
			{
				// Obtenemos la opcion
				$aDatos = tep_db_query( 'SELECT language_id, products_options_name, products_options_track_stock, products_options_type FROM products_options WHERE products_options_id = "' . (int)$sGetId . '"' );

				// Si no existe devolvemos
				if( tep_db_num_rows( $aDatos ) == 0 )
				{
					$messageStack->addSession( 'mensaje', 'La opción que intentas editar no existe', 'warning' );
					tep_redirect( tep_href_link( $sUrlPage ) );
				}

				// Obtenemos los datos
				while( $aDato = tep_db_fetch_array( $aDatos ) )
					$oInfo->{$aDato['language_id']} = $aDato;
			}

			// Titulos, iconos y menu //
			$sHeadTitle = $bEdit ? 'Editar opción ' . $oInfo->{3}['products_options_name'] : 'Insertar opción nueva';
			$sHeadSubTitle = $bEdit ? 'Editar opcion' : 'Insertar opción';

			// Si estamos editando el boton de guardar por ajax
			if( $bEdit )
				$aHeadIcons[] = array( 'EXTRA' => 'id="save_ajax"', 'SRC' => 'images/icons/icon_save.png', 'TITLE' => 'Guardar' );

			// Guardar y volver
			$aHeadIcons[] = array( 'EXTRA' => 'onclick=\'$("#form").find("input[type=submit]").trigger("click")\'', 'SRC' => 'images/icons/icon_save_return.png', 'TITLE' => 'Guardar y volver' );
			$aHeadIcons[] = array( 'HREF' => tep_href_link( $sUrlPage, tep_get_all_get_params( array( 'id', 'a' ) ) ), 'SRC' => 'images/icons/icon_back.png', 'TITLE' => 'Volver', 'EXTRA' => 'id="hotkey-return"' );

			// Html //
			$sHtmlModule .= tep_draw_form( 'form', $sUrlPage . '?a=' . str_replace( '_form', '', $sGetAction ), '', 'post', 'id="form" class="autotab"' );
				// Si estamos editando añadimos el ID
				if( $bEdit )
					$sHtmlModule .= '<input type="hidden" name="id" value="' . $sGetId . '" />';

				// Si estamos llamandolo desde el attributerManager
				if( $bLoadFileProductsAttributes )
					$sHtmlModule .= '<input type="hidden" name="attributermanager" value="true" />';

				$sHtmlModule .= '<div class="fluid grid">
					<div class="box-tbl grid12">
						<div class="box-head">
							<div class="grid12"><h6>Opcion</h6></div>
							<div class="clear"></div>
						</div>';

						// Recorremos los idiomas
						foreach( $aIdiomas as $aIdioma )
						{
							$sHtmlModule .= '<div class="tab-change-idma-1" id="change-idma-1-' . $aIdioma['id'] . '">';
								$sHtmlModule .= '<div class="formRow">';
									$sHtmlModule .= '<div class="grid3" style="margin: 0px; position: relative;">';
										$sHtmlModule .= '<label style="padding-left:30px;">Nombre:</label>';
										$sHtmlModule .= tep_image( DIR_WS_CATALOG_LANGUAGES . $aIdioma['directory'] . '/images/' . $aIdioma['image'], $aIdioma['name'], '', '', 'style="position: absolute;left: 0px;top: 7px;"' );
									$sHtmlModule .= '</div>';
									$sHtmlModule .= '<div class="grid9">';
										$sHtmlModule .= tep_draw_input_field( 'products_options_name[' . $aIdioma['id'] . ']', $oInfo->{$aIdioma['id']}['products_options_name'], 'style="width: 100%; margin: 0px;" required' );
									$sHtmlModule .= '</div>';
									$sHtmlModule .= '<div class="clear"></div>';
								$sHtmlModule .= '</div>';
							$sHtmlModule .= '</div>';
						}

					$sHtmlModule .= '</div>
				</div>';

				$sHtmlModule .= '<div class="fluid grid">
					<div class="box-tbl grid12">
						<div class="box-head">
							<div class="grid12"><h6>Varios</h6></div>
							<div class="clear"></div>
						</div>';
						$sHtmlModule .= '<div class="formRow" style="display:none">';
							$sHtmlModule .= '<div class="grid3" style="margin: 0px;">';
								$sHtmlModule .= '<label>Tipo:</label>';
							$sHtmlModule .= '</div>';
							$sHtmlModule .= '<div class="grid9">';
								$sHtmlModule .= tep_draw_pull_down_menu( 'products_options_type', $aComboTypes, $oInfo->{3}['products_options_type'] );
							$sHtmlModule .= '</div>';
							$sHtmlModule .= '<div class="clear"></div>';
						$sHtmlModule .= '</div>';
						$sHtmlModule .= '<div class="formRow">';
							$sHtmlModule .= '<div class="grid3" style="margin: 0px;">';
								$sHtmlModule .= '<label>¿Controlar stock?:</label>';
							$sHtmlModule .= '</div>';
							$sHtmlModule .= '<div class="grid9">';
								$sHtmlModule .= '<input name="products_options_track_stock" ' . ($oInfo->{3}['products_options_track_stock'] ? 'checked="checked"' : '') . ' type="checkbox"/>';
							$sHtmlModule .= '</div>';
							$sHtmlModule .= '<div class="clear"></div>';
						$sHtmlModule .= '</div>';
					$sHtmlModule .= '</div>
				</div>

				<div class="box-bton" style="display: none;"><input value="Aceptar" type="submit" href="javascript:void(0);" class="buttonS bGreen"/><div class="clear"></div></div>
			</form>';
		break;


		// Mostrar lista de opciones //
		default:
			// Titulos, iconos y menu //
			$sHeadTitle = 'Opciones';
			$sHeadSubTitle = 'Todas las opciones';
			$aHeadIcons = array(
				array( 'HREF' => tep_href_link( $sUrlPage, 'a=option_add_form' ), 'SRC' => 'images/icons/icon_add.png', 'TITLE' => 'Añadir opción', 'EXTRA' => 'id="hotkey-new"' )
			);

			// Variables //
			$sGetSearchOption = tep_db_prepare_input( $_GET['search_option'] ?? '' );
			$sGetSearchType = tep_db_prepare_input( $_GET['search_type'] ?? '' );
			$sGetSearchStock = tep_db_prepare_input( $_GET['search_stock'] ?? '' );
			$sWhere = '';

			// Where //
			if( $sGetSearchOption != '' || $sGetSearchType != '' || $sGetSearchStock != '' )
				$sWhere = 'where ';

			if( $sGetSearchOption != '' )
			{
				if( $sWhere != 'where ' )
					$sWhere .= ' and';

				$sWhere .= ' LOWER(po.products_options_name) LIKE "%' . strtolower( $sGetSearchOption ) . '%"';
			}

			if( $sGetSearchType != '' )
			{
				if( $sWhere != 'where ' )
					$sWhere .= ' and';

				$sWhere .= ' po.products_options_type = "' . $sGetSearchType . '"';
			}

			if( $sGetSearchStock != '' )
			{
				if( $sWhere != 'where ' )
					$sWhere .= ' and';

				$sWhere .= ' LOWER(po.products_options_track_stock) = "' . $sGetSearchStock . '"';
			}

			// Order by //
			if( $sGetOrderby == 'products_options_type' )
				$sOrderby = 'po.products_options_type ' . $sGetSort;
			else if( $sGetOrderby == 'products_options_name' )
				$sOrderby = 'po.products_options_name ' . $sGetSort;
			else if( $sGetOrderby == 'products_options_id' )
				$sOrderby = 'po.products_options_name ' . $sGetSort;
			else if( $sGetOrderby == 'products_options_track_stock' )
				$sOrderby = 'po.products_options_track_stock ' . $sGetSort;
			else
				$sOrderby = 'po.products_options_name ASC';

			// Si estamos ordenando o filtrando, mostramos el boton de limpiar filtro //
			if( $sGetOrderby != '' || $sWhere != '' )
				$aHeadIcons[] = array( 'HREF' => tep_href_link( $sUrlPage ), 'SRC' => 'images/icons/icon_clear_filter.png', 'TITLE' => 'Limpiar filtro' );

			// Obtenemos las opciones //
			$sSql = 'SELECT po.products_options_id, po.products_options_track_stock, po.products_options_name, po.products_options_type, count(pov2po.products_options_values_to_products_options_id) as cantidad_valores
					 FROM products_options po
					 LEFT JOIN products_options_values_to_products_options pov2po ON (pov2po.products_options_id = po.products_options_id)
					 ' . ($sWhere == '' ? ' where ' : $sWhere . ' and ') . ' po.language_id = 3
					 GROUP BY po.products_options_id
					 ORDER BY ' . $sOrderby;

			// Le quitamos los tabuladores y saltos de linea para que splitpageesult funcione con el SQL //
			$sSql = preg_replace( '/[\r\n\t]+/', ' ', $sSql );

			// Sql para el count //
			$sSqlCount = 'SELECT COUNT(table_aux.products_options_id) as total FROM (' . $sSql . ') as table_aux';

			// Datos y paginacion
			$aDatoSplit = new splitPageResults( $sGetPage, 20, $sSql, $nAux, $sSqlCount );
			$aDatos = tep_db_query( $sSql );

			// Variable para saber si hemos encontrado datos o no
			$bFoundDatos = tep_db_num_rows( $aDatos ) > 0;

			// Si no encontramos ninguna lista
			if( !$bFoundDatos )
				$messageStack->add( 'No existen opciones con el filtro seleccionado', 'warning', 'true' );

			// Mostramos las opciones
			$sHtmlModule .= '<div class="box-tbl" style="width: 100%">';
				$sHtmlModule .= '<div class="box-head">';
					$sHtmlModule .= '<h6>Listas de opciones</h6>';
					$sHtmlModule .= '<a title="Filtrar" data-id="1" href="javascript:void(0);" class="tOptions filter-togle"><img alt="" src="theme/web/images/icons/usual/icon-cog3.png"></a>';
					$sHtmlModule .= '<div class="clear"></div>';
				$sHtmlModule .= '</div>';

				// Filtro //
				$sHtmlModule .= '<div data-id="1" class="fluid grid tablePars">';
					$sHtmlModule .= tep_draw_form( 'form', $sUrlPage, '', 'get' );
						$sHtmlModule .= '<div class="grid12">';
							$sHtmlModule .= '<div class="formRow" style="border: none; padding: 7px 16px;">';
								$sHtmlModule .= '<div class="grid2"><label>Opcion:</label></div>';
								$sHtmlModule .= '<div class="grid10">';
										$sHtmlModule .= tep_draw_input_field( 'search_option', $sGetSearchOption, 'placeholder="Introduce opcion..."' );
								$sHtmlModule .= '</div>';
								$sHtmlModule .= '<div class="clear"></div>';
							$sHtmlModule .= '</div>';

							$sHtmlModule .= '<div class="formRow" style="border: none; padding: 7px 16px;">';
								$sHtmlModule .= '<div class="grid2"><label>Control de stock:</label></div>';
								$sHtmlModule .= '<div class="grid10">';
										$sHtmlModule .= tep_draw_pull_down_menu( 'search_stock', $aComboYesNo, $sGetSearchStock );
								$sHtmlModule .= '</div>';
								$sHtmlModule .= '<div class="clear"></div>';
							$sHtmlModule .= '</div>';

							$sHtmlModule .= '<div class="formRow" style="border: none; padding: 7px 16px;display:none;">';
								$sHtmlModule .= '<div class="grid2"><label>Tipo:</label></div>';
								$sHtmlModule .= '<div class="grid10">';
									$sHtmlModule .= tep_draw_pull_down_menu( 'search_type', array_merge( array(array( 'id' => '', 'text' => 'Todos' )), $aComboTypes ), $sGetSearchType );
								$sHtmlModule .= '</div>';
								$sHtmlModule .= '<div class="clear"></div>';
							$sHtmlModule .= '</div>';

							$sHtmlModule .= '<div class="formRow" style="border: none; padding: 7px 16px;">';
								$sHtmlModule .= '<div class="grid12">';
									$sHtmlModule .= '<input type="submit" value="Filtrar" class="buttonS bGreen" style="cursor: pointer;"/>';
								$sHtmlModule .= '</div>';
								$sHtmlModule .= '<div class="clear"></div>';
							$sHtmlModule .= '</div>';
						$sHtmlModule .= '</div>';
					$sHtmlModule .= '</form>';
				$sHtmlModule .= '</div>';

				// Tabla //
				$sHtmlModule .= '<table id="table" class="tAlt wGeneral tDefault" width="100%" cellspacing="0" cellpadding="0">';
					$sHtmlModule .= '<thead>';
						$sHtmlModule .= '<tr>';
							$sHtmlModule .= '<td style="text-align: center; width: 50px;">' . admin_setTableTdSort( $sUrlPage, 'products_options_id', 'ID' ) . '</td>';
							$sHtmlModule .= '<td style="text-align: left;">' . admin_setTableTdSort( $sUrlPage, 'products_options_name', 'Opción' ) . '</td>';
							$sHtmlModule .= '<td style="text-align: left;" >' . admin_setTableTdSort( $sUrlPage,'products_options_type', 'Tipo' ) . '</td>';
							$sHtmlModule .= '<td width="120" style="text-align: center;" >' . admin_setTableTdSort( $sUrlPage,'products_options_track_stock', '¿Control de stock?' ) . '</td>';
							$sHtmlModule .= '<td width="50">Valores</td>';
							$sHtmlModule .= '<td style="text-align: center;" width="50">Productos</td>';
							$sHtmlModule .= '<td style="text-align: center;" width="125">Acciones</td>';
						$sHtmlModule .= '</tr>';
					$sHtmlModule .= '</thead>';
					$sHtmlModule .= '<tbody>';

						// Recorremos las listas obtenidas //
						while( $aDato = tep_db_fetch_array( $aDatos ) )
						{
							$sHtmlModule .= '<tr data-src="' . tep_href_link( $sUrlPage, tep_get_all_get_params( array( 'a', 'page', 'search_option', 'search_stock', 'search_type', 'orderby', 'sort' ) ) . 'page=' . $sGetPage . '&a=value_list&ido=' . $aDato['products_options_id'] ) . '" class="dbclick">';
								$sHtmlModule .= '<td style="text-align: center;">' . $aDato['products_options_id'] . '</td>';
								$sHtmlModule .= '<td>' . $aDato['products_options_name'] . '</td>';
								$sHtmlModule .= '<td>' . $aDato['products_options_type'] . '</td>';
								$sHtmlModule .= '<td style="text-align: center;">' . admin_getValueKeyArrayCombo( $aComboYesNo, 'id', 'text', $aDato['products_options_track_stock'] ) . '</td>';
								$sHtmlModule .= '<td style="text-align: center;">' . $aDato['cantidad_valores'] . '</td>';

								// Obtenemos la cantidad de productos que tiene asignado esta opcion //
								// Consultamos
								$aAux = tep_db_query( 'SELECT COUNT(DISTINCT products_id) AS cantidad_productos FROM products_attributes WHERE options_id = "' . (int)$aDato['products_options_id'] . '" GROUP BY options_id' );
								$aAux = tep_db_fetch_array( $aAux );

								// Mostramos la cantidad de productos que tienen asignada esta opcin
								$sHtmlModule .= '<td style="text-align: center;">' . (is_array($aAux) ? $aAux['cantidad_productos'] : 0) . '</td>';

								$sHtmlModule .= admin_setTableTdAction( array(
									array( 'HREF' => tep_href_link( $sUrlPage, tep_get_all_get_params( array( 'a', 'page' ) ) . 'page=' . $sGetPage . '&a=option_edit_form&id=' . $aDato['products_options_id'] ), 'CLASS' => 'icos-pencil', 'TEXT' => 'Editar' ),
									array( 'HREF' => tep_href_link( $sUrlPage, tep_get_all_get_params( array( 'a', 'page', 'search_option', 'search_stock', 'search_type', 'orderby', 'sort' ) ) . 'page=' . $sGetPage . '&a=value_list&ido=' . $aDato['products_options_id'] ), 'CLASS' => 'icos-add', 'TEXT' => 'Añadir valores' ),
									array( 'HREF' => tep_href_link( $sUrlPage, tep_get_all_get_params( array( 'a', 'page' ) ) . 'page=' . $sGetPage . '&a=show_products&ido=' . $aDato['products_options_id'] ), 'CLASS' => 'icos-preview', 'TEXT' => 'Ver productos' ),
									array( 'HREF' => tep_href_link( $sUrlPage, tep_get_all_get_params( array( 'a', 'page' ) ) . 'page=' . $sGetPage . '&a=option_delete&id=' . $aDato['products_options_id'] ), 'CLASS' => 'icos-trash', 'TEXT' => 'Eliminar', 'CLASS_HREF' => 'confirm', 'ATTR_HREF' => 'data-text="¿Deseas eliminar la opción? Recuerda que si tiene asignado valores y productos estos se perderán."' )
								));

							$sHtmlModule .= '</tr>';
						}

					// Fin de tabla y paginacion //
					$sHtmlModule .= '</tbody>';
				$sHtmlModule .= '</table>';
				$sHtmlModule .= $aDatoSplit->showPaginateTable( tep_get_all_get_params( array('page') ) );
			$sHtmlModule .= '</div>';
		break;
	}

	// MessageStack
	$sMensajeStack = $messageStack->output(false);
	$messageStack->reset();

	if( !$bLoadFileProductsAttributes )
	{
		// Header //
		require(THEME . 'html/header.php');

		// Titulos e iconos //
		echo '<div>';
			echo '<div class="toolbarHead">';
				echo '<div class="hdr-tlbr">';
					echo '<h1 class="pageHeading ftitl" style="top: 6px;">' . $sHeadTitle . '</h1>';
					echo '<h2 class="stitl" style="top: 13px;">' . $sHeadSubTitle . '</h2>';
					echo '<div class="btn-right">';
						foreach( $aHeadIcons as $aHeadIcon )
							echo '<a ' . (array_key_exists( 'EXTRA', $aHeadIcon ) ? $aHeadIcon['EXTRA'] : '') . ' ' . (array_key_exists( 'TITLE', $aHeadIcon ) ? 'title="' . $aHeadIcon['TITLE'] . '"' : '') . ' href="' . (array_key_exists( 'HREF', $aHeadIcon ) ? $aHeadIcon['HREF'] : 'javascript:void(0);') . '"><img src="' . $aHeadIcon['SRC'] . '" class="dx-hovr"></a>';
					echo '</div>';
				echo '</div>';
			echo '</div>';
		echo '</div>';

		echo $sMensajeStack;
		echo $sHtmlModule;

		echo '<br/><br/>' . $messageStack->show( array( 'class' => 'info', 'text' => '<b>Información:</b><br/>Puedes utilizar los siguientes atajos del teclado:<br/>* CTRL + ARRIBA: añadir nuevo<br/>* CTRL + ABAJO: volver atrás <br/>* CTRL + DERECHA O IZQUIERDA: cambiar entre idiomas<br/>* ENTER: Guardar formulario' ) );

		// Loadding //
		echo '<div id="dxload"></div><div id="dxbg"></div>
			  <div id="dxbg" style="display:none; position: fixed; top: 0px; width: 100%; background: none repeat scroll 0px 0px rgb(102, 102, 102); height: 100%; left: 0px; opacity: 0.3; z-index: 1;"></div>';

		// Javascript //
		$sJavascript .= '<script type="text/javascript" src="../includes/classes/attributes/functions.js"></script>';

		// Footer //
		require(THEME . 'html/footer.php');

		// Librerias //
		include( DIR_WS_INCLUDES . 'application_bottom.php' );
	}
	else
		echo $sHtmlModule;
?>
