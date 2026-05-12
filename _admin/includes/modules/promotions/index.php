<?php
include('includes/application_top.php');

// Variables
$sUrlPage           = FILENAME_PROMOTIONS;
$sGetPage           = (isset($_GET['page']) ? tep_db_prepare_input($_GET['page']) : 1);
$sGetAction         = (isset($_GET['a']) ? tep_db_prepare_input($_GET['a']) : false);
$sHtmlModule        = '';
$sHeadTitle         = '';
$sHeadSubTitle      = '';
$aElementsPromotion = [];
$aElementsDiscount  = [];

// Obtenemos los idiomas
$languages = tep_get_languages();

// Actions
switch ($sGetAction) {
	// Obtener productos autocomplete
	case 'get_products':
		// Si enviamos un texto largo
		if (strlen($_GET['text']) > 3) {
			// Obtenemos los productos según el texto enviado
			$aElements = tep_db_query('(SELECT categories_id as id, categories_name as name, "c" as type FROM categories_description WHERE LCASE( categories_name ) LIKE "%' . strtolower(str_replace(' ', '%', $_GET['text'])) . '%" AND language_id = 3) UNION
										(SELECT manufacturers_id as id, manufacturers_name as name, "m" as type FROM manufacturers WHERE LCASE( manufacturers_name ) LIKE "%' . strtolower(str_replace(' ', '%', $_GET['text'])) . '%") UNION
										(SELECT p.products_id as id, pd.products_name as name, "p" as type FROM products p INNER JOIN products_description pd ON (p.products_id = pd.products_id) WHERE ( LCASE( pd.products_name ) LIKE "%' . strtolower(str_replace(' ', '%', $_GET['text'])) . '%" OR LCASE( p.products_model ) LIKE "%' . strtolower($_GET['text']) . '%" OR LCASE( p.product_ean ) LIKE "%' . strtolower($_GET['text']) . '%" ) AND language_id = 3 AND p.products_status = 1);');

			// Rellenamos de elementos
			$sElements = '';
			while ($aElement = tep_db_fetch_array($aElements)) {
				// Obtenemos el tipo
				$sType = ($aElement['type'] == 'p' ? '<b style="position: absolute; right: 30px; color: #4fc22b;">Producto</b>' : ($aElement['type'] == 'c' ? '<b style="position: absolute; right: 30px; color: #881111;">Categoría</b>' : ($aElement['type'] == 'm' ? '<b style="position: absolute; right: 30px; color: #2b78c2;">Marca</b>' : ($aElement['type'] == 't' ? '<b style="position: absolute; right: 30px; color: #000;">Tipo de producto</b>' : ''))));

				// Elemento
				$sElements .= '<li class="ui-draggable" id="' . $aElement['id'] . '">' . ($aElement['type'] == 'c' ? tep_output_generated_category_path($aElement['id']) : $aElement['name']) . $sType . '</li>';
			}

			// Pintamos el resultado
			die($sElements);
		}

		die('');
		break;

	// Añadir todos los productos al listado
	case 'add_all_products':
		$aRows      = (!empty($_POST['rows']) ? explode(';', tep_db_prepare_input($_POST['rows'])) : []);
		$aAdds      = (!empty($_POST['add']) ? explode(';', tep_db_prepare_input($_POST['add'])) : []);
		$aTypesRows = (!empty($_POST['types_rows']) ? explode(';', tep_db_prepare_input($_POST['types_rows'])) : []);
		$aTypesAdd  = (!empty($_POST['types_add']) ? explode(';', tep_db_prepare_input($_POST['types_add'])) : []);
		$sBox       = (!empty($_POST['box']) ? tep_db_prepare_input($_POST['box']) : '');

		$aCategories    = [];
		$aManufacturers = [];
		$aProducts      = [];
		$sHTML          = '';

		// Categorías existentes y añadidas
		foreach ($aRows as $nIndex => $aRow)
			if (isset($aTypesRows[$nIndex]) && $aTypesRows[$nIndex] == 'c')
				$aCategories[] = $aRow;
		foreach ($aAdds as $nIndex => $aAdd)
			if (isset($aTypesAdd[$nIndex]) && $aTypesAdd[$nIndex] == 'c')
				$aCategories[] = $aAdd;

		// Marcas existentes y añadidas
		foreach ($aRows as $nIndex => $aRow)
			if (isset($aTypesRows[$nIndex]) && $aTypesRows[$nIndex] == 'm')
				$aManufacturers[] = $aRow;
		foreach ($aAdds as $nIndex => $aAdd)
			if (isset($aTypesAdd[$nIndex]) && $aTypesAdd[$nIndex] == 'm')
				$aManufacturers[] = $aAdd;

		// Productos existentes y añadidos
		foreach ($aRows as $nIndex => $aRow)
			if (isset($aTypesRows[$nIndex]) && $aTypesRows[$nIndex] == 'p')
				$aProducts[] = $aRow;
		foreach ($aAdds as $nIndex => $aAdd)
			if (isset($aTypesAdd[$nIndex]) && $aTypesAdd[$nIndex] == 'p')
				$aProducts[] = $aAdd;

		// Mostrar Categorías
		if (count($aCategories) > 0) {
			$aResults = tep_db_query('SELECT categories_id, categories_name FROM ' . TABLE_CATEGORIES_DESCRIPTION . ' WHERE categories_id IN (' . implode(',', $aCategories) . ') AND language_id = "3"');
			while ($aResult = tep_db_fetch_array($aResults)) {
				$sHTML .= '<li data-drop="true" data-id="' . $aResult['categories_id'] . '">' . $aResult['categories_name'] . '<b style="position: absolute; right: 30px; color: #881111;">Categoría</b>
            <input type="hidden" value="' . $aResult['categories_id'] . '" name="' . ($sBox == 2 ? 'row-prd2' : 'row-prd') . '[]">
            <input type="hidden" value="c" name="' . ($sBox == 2 ? 'row-prd2' : 'row-prd') . '-type[]"></li>';
			}
		}

		// Mostrar Marcas
		if (count($aManufacturers) > 0) {
			$aResults = tep_db_query('SELECT manufacturers_id, manufacturers_name FROM ' . TABLE_MANUFACTURERS . ' WHERE manufacturers_id IN (' . implode(',', $aManufacturers) . ')');
			while ($aResult = tep_db_fetch_array($aResults)) {
				$sHTML .= '<li data-drop="true" data-id="' . $aResult['manufacturers_id'] . '">' . $aResult['manufacturers_name'] . '<b style="position: absolute; right: 30px; color: #2b78c2;">Marca</b>
            <input type="hidden" value="' . $aResult['manufacturers_id'] . '" name="' . ($sBox == 2 ? 'row-prd2' : 'row-prd') . '[]">
            <input type="hidden" value="m" name="' . ($sBox == 2 ? 'row-prd2' : 'row-prd') . '-type[]"></li>';
			}
		}

		// Mostrar Productos
		if (count($aProducts) > 0) {
			$aResults = tep_db_query('SELECT products_id, products_name FROM ' . TABLE_PRODUCTS_DESCRIPTION . ' WHERE products_id IN (' . implode(',', $aProducts) . ') AND language_id = "3"');
			while ($aResult = tep_db_fetch_array($aResults)) {
				$sHTML .= '<li data-drop="true" data-id="' . $aResult['products_id'] . '">' . $aResult['products_name'] . '<b style="position: absolute; right: 30px; color: #4fc22b;">Producto</b>
            <input type="hidden" value="' . $aResult['products_id'] . '" name="' . ($sBox == 2 ? 'row-prd2' : 'row-prd') . '[]">
            <input type="hidden" value="p" name="' . ($sBox == 2 ? 'row-prd2' : 'row-prd') . '-type[]"></li>';
			}
		}

		die($sHTML);

	case 'status':
		// Variables
		$sGetId     = tep_db_prepare_input($_GET['id']);
		$sGetStatus = tep_db_prepare_input($_GET['status']);
		$aSql       = [
			'promotion_status' => $sGetStatus,
		];

		// Actualizamos
		tep_db_perform(TABLE_PROMOTIONS, $aSql, 'update', 'promotion_id = ' . $sGetId);
		exit();

	case 'banner_status':
		// Variables
		$sGetId     = tep_db_prepare_input($_GET['id']);
		$sGetStatus = tep_db_prepare_input($_GET['status']);
		$aSql       = [
			'promotion_banner' => $sGetStatus,
		];

		// Actualizamos
		tep_db_perform(TABLE_PROMOTIONS, $aSql, 'update', 'promotion_id = ' . $sGetId);
		exit();

	case 'delete':
		// Eliminamos el registro
		tep_db_query('DELETE FROM ' . TABLE_PROMOTIONS . ' WHERE promotion_id = "' . (int)$_GET['promotion'] . '";');
		tep_db_query('DELETE FROM ' . TABLE_PROMOTIONS_ELEMENTS . ' WHERE promotion_id = "' . (int)$_GET['promotion'] . '";');
		tep_db_query('DELETE FROM ' . TABLE_LANDINGS_DESCRIPTION . ' WHERE landing_id = "' . (int)$_GET['promotion'] . '";');

		// Mensaje de confirmación
		$messageStack->add_session('Promoción eliminada correctamente.', 'success');

		// Redireccionamos
		tep_redirect(FILENAME_PROMOTIONS);
		break;

	case 'insert':
	case 'edit':
		// Variables
		$sName      = false;
		$nPercent   = false;
		$nQuantity  = false;
		$nToAll     = false;
		$nSpecial   = false;
		$sIcon      = false;
		$dDateStart = false;
		$dDateEnd   = false;

		$sImage       = [];
		$sDescription = [];
		$sVideo       = [];

		$bError         = false;
		$sErrorName     = false;
		$sErrorPercent  = false;
		$sErrorQuantity = false;

		$sErrorTitle       = [];
		$sErrorImage       = false;
		$sErrorDescription = false;
		$sErrorVideo       = false;

		// Método POST
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			// Recogemos las variables POST
			$sName      = tep_db_prepare_input($_POST['promotion_name']);
			$nPercent   = tep_db_prepare_input($_POST['promotion_discount_percent']);
			$sType      = tep_db_prepare_input($_POST['promotion_discount_type']);
			$nQuantity  = tep_db_prepare_input($_POST['promotion_quantity']);
			$nToAll     = tep_db_prepare_input((int)$_POST['promotion_all']);
			$nExtend    = tep_db_prepare_input((int)$_POST['promotion_extend']);
			$nSpecial   = tep_db_prepare_input((int)$_POST['promotion_special']);
			$sIcon      = tep_db_prepare_input($_FILES['promotion_icon']);
			$dDateStart = tep_db_prepare_input($_POST['promotion_start']);
			$dDateEnd   = tep_db_prepare_input($_POST['promotion_end']);

			$sTitle       = tep_db_prepare_input($_POST['landing_title']);
			$sImage       = tep_db_prepare_input($_FILES['landing_image']);
			$sDescription = tep_db_prepare_input($_POST['landing_description']);
			$sVideo       = tep_db_prepare_input($_POST['landing_video']);

			// Comprobamos campos requeridos
			for ($nCont = 0; $nCont < sizeof($languages); ++$nCont) {
				// Título
				if ($sTitle[$languages[$nCont]['id']] == '') {
					// Error
					$sErrorTitle[$languages[$nCont]['id']] = 'El título en el idioma ' . $languages[$nCont]['name'] . ' es un campo requerido.';
					$bError                                = true;
				}
			}

			if ($nSpecial == 0) {
				// Nombre
				if ($sName == '') {
					// Error
					$sErrorName = 'El nombre de la promoción es un campo requerido.';
					$bError     = true;
				}

				// Porcentaje de descuento
				if ($nPercent == '' || $nPercent <= 0) {
					// Error
					$sErrorPercent = 'El % de descuento de la promoción es un campo requerido.';
					$bError        = true;
				}

				// Cantidad
				if ($nQuantity == '' || $nQuantity <= 0) {
					// Error
					$sErrorQuantity = 'La cantidad de productos es un campo requerido.';
					$bError         = true;
				}
			}

			// Si NO tenemos errores
			if ($bError === false) {
				// Array a insertar / editar
				$aInsert                               = [];
				$aInsert['promotion_name']             = ($sName != '' ? $sName : $sTitle[3]);
				$aInsert['promotion_discount_percent'] = $nPercent;
				$aInsert['promotion_discount_type']    = $sType;
				$aInsert['promotion_quantity']         = $nQuantity;
				$aInsert['promotion_special']          = $nSpecial;
				$aInsert['promotion_all']              = $nToAll;
				$aInsert['promotion_extend']           = $nExtend;
				$aInsert['promotion_start']            = !empty($dDateStart) ? date('Y-m-d H:i:s', strtotime($dDateStart)) : '0000-00-00 00:00:00';
				$aInsert['promotion_end']              = !empty($dDateEnd) ? date('Y-m-d H:i:s', strtotime($dDateEnd)) : '0000-00-00 00:00:00';

				// Movemos la imagen
				if ($sIcon['name'] != '') {
					$sIconName = getSlug($sTitle[3] . '-icon') . '.' . pathinfo($sIcon['name'], PATHINFO_EXTENSION);
					move_uploaded_file($sIcon['tmp_name'], getCwd() . '/../images/landings/' . $sIconName);

					$aInsert['promotion_icon'] = $sIconName;
				}


				// Insertamos
				if ($sGetAction == 'insert') {
					// Promocion //
					// Estado
					$aInsert['promotion_status'] = 1;
					$aInsert['promotion_banner'] = 1;

					// Insertamos en la tabla
					tep_db_perform(TABLE_PROMOTIONS, $aInsert);

					// Obtenemos el ID
					$nID = tep_db_insert_id();

					// Promocion //


					// Landing //

					// Insertamos para cada idioma
					for ($nCont = 0; $nCont < sizeof($languages); ++$nCont) {
						// Movemos la imagen
						$sImageName = '';

						if ($sImage['name'][$languages[$nCont]['id']] != '') {
							$sImageName = getSlug($sTitle[$languages[$nCont]['id']] . '-' . $languages[$nCont]['name']) . '.' . pathinfo($sImage['name'][$languages[$nCont]['id']], PATHINFO_EXTENSION);
							move_uploaded_file($sImage['tmp_name'][$languages[$nCont]['id']], getCwd() . '/../images/landings/' . $sImageName);
						}

						// Preparamos el registro para insertar
						$aInsertLand                        = [];
						$aInsertLand['landing_id']          = $nID;
						$aInsertLand['language_id']         = $languages[$nCont]['id'];
						$aInsertLand['landing_title']       = $sTitle[$languages[$nCont]['id']];
						$aInsertLand['landing_image']       = $sImageName;
						$aInsertLand['landing_description'] = $sDescription[$languages[$nCont]['id']];
						if (isset($languages[$nCont]['video']) && array_key_exists($languages[$nCont]['video'], $sVideo))
							$aInsertLand['landing_video'] = $sVideo[$languages[$nCont]['video']];

						// Insertamos el registro
						tep_db_perform(TABLE_LANDINGS_DESCRIPTION, $aInsertLand);
					}
					// Landing //
				} else if ($sGetAction == 'edit') {
					// Promocion //
					// Actualizamos el registro
					tep_db_perform(TABLE_PROMOTIONS, $aInsert, 'update', 'promotion_id = "' . (int)$_GET['promotion'] . '"');

					// Obtenemos el ID
					$nID = (int)$_GET['promotion'];

					// Eliminamos los productos asociados
					tep_db_query('DELETE FROM ' . TABLE_PROMOTIONS_ELEMENTS . ' WHERE promotion_id = "' . $nID . '";');

					// Landing //
					// Editamos para cada idioma
					for ($nCont = 0; $nCont < sizeof($languages); ++$nCont) {
						// Imagen
						$sImageName = false;

						// Si añadimos nueva imagen
						if (isset($sImage['tmp_name'][$languages[$nCont]['id']]) && $sImage['tmp_name'][$languages[$nCont]['id']] != '') {
							// Movemos la imagen
							$sImageName = getSlug($sTitle[$languages[$nCont]['id']] . '-' . $languages[$nCont]['name']) . '.' . pathinfo($sImage['name'][$languages[$nCont]['id']], PATHINFO_EXTENSION);
							move_uploaded_file($sImage['tmp_name'][$languages[$nCont]['id']], getCwd() . '/../images/landings/' . $sImageName);
						}

						// Preparamos el registro para editar
						$aInsert                  = [];
						$aInsert['landing_title'] = $sTitle[$languages[$nCont]['id']];
						if ($sImageName !== false)
							$aInsert['landing_image'] = $sImageName;
						$aInsert['landing_description'] = $sDescription[$languages[$nCont]['id']];
						if (array_key_exists($languages[$nCont]['id'], $sVideo))
							$aInsert['landing_video'] = $sVideo[$languages[$nCont]['id']];

						// Actualizamos el registro
						tep_db_perform(TABLE_LANDINGS_DESCRIPTION, $aInsert, 'update', 'landing_id = "' . (int)$_GET['promotion'] . '" AND language_id = "' . $languages[$nCont]['id'] . '"');
					}
					// Landing //
				}

				// Productos asociados promoción
				if (!empty($_POST['row-prd'])) {
					foreach ($_POST['row-prd'] as $nKey => $nProduct) {
						$elementId   = (int)$nProduct;
						$elementType = tep_db_prepare_input($_POST['row-prd-type'][$nKey] ?? 'p');

						tep_db_query("INSERT INTO " . TABLE_PROMOTIONS_ELEMENTS . "
                                        (promotion_id, element_id, element_type, element_operation)
                                        VALUES (" . (int)$nID . ", " . $elementId . ", '" . tep_db_input($elementType) . "', 'p')
                                    ");
					}
				}

				// Productos asociados promoción descuento
				if (!empty($_POST['row-prd2'])) {
					foreach ($_POST['row-prd2'] as $nKey => $nProduct) {
						$elementId   = (int)$nProduct;
						$elementType = tep_db_prepare_input($_POST['row-prd2-type'][$nKey] ?? 'p');

						tep_db_query("INSERT INTO " . TABLE_PROMOTIONS_ELEMENTS . "
                                        (promotion_id, element_id, element_type, element_operation)
                                        VALUES (" . (int)$nID . ", " . $elementId . ", '" . tep_db_input($elementType) . "', 'd')
                                    ");
					}
				}


				// Mensaje de confirmación
				$messageStack->add_session('Promoción "' . $sName . '" insertada/editada correctamente.', 'success');

				// Redireccionamos
				tep_redirect($sUrlPage);
			}

			// Rellenamos el array de filtros por POST //

			// Productos de la promoción
			if (isset($_POST['row-prd'])) {
				// Recorremos
				foreach ($_POST['row-prd'] as $nKey => $aProduct) {
					// Texto que se muestra según el tipo
					$sDisplay = $_POST['row-prd-name'][$nKey];
					if ($_POST['row-prd-type'][$nKey] == 'p')
						$sDisplay = preg_replace('/(Producto)$/i', '<b style="position: absolute; right: 30px; color: #4fc22b;">Producto</b>', $sDisplay);
					if ($_POST['row-prd-type'][$nKey] == 'c')
						$sDisplay = preg_replace('/(Categoría)$/i', '<b style="position: absolute; right: 30px; color: #881111;">Categoría</b>', $sDisplay);
					if ($_POST['row-prd-type'][$nKey] == 'm')
						$sDisplay = preg_replace('/(Marca)$/i', '<b style="position: absolute; right: 30px; color: #2b78c2;">Marca</b>', $sDisplay);
					if ($_POST['row-prd-type'][$nKey] == 't')
						$sDisplay = preg_replace('/(Tipo de producto)$/i', '<b style="position: absolute; right: 30px; color: #000;">Tipo de producto</b>', $sDisplay);

					// Añadimos al array
					$aElementsPromotion[] = ['id' => $aProduct, 'name' => $_POST['row-prd-name'][$nKey], 'display' => $sDisplay, 'type' => $_POST['row-prd-type'][$nKey]];
				}
			}

			// Productos de la promoción descuento
			if (isset($_POST['row-prd2'])) {
				// Recorremos
				foreach ($_POST['row-prd2'] as $nKey => $aProduct) {
					// Texto que se muestra según el tipo
					$sDisplay = $_POST['row-prd2-name'][$nKey];
					if ($_POST['row-prd2-type'][$nKey] == 'p')
						$sDisplay = preg_replace('/(Producto)$/i', '<b style="position: absolute; right: 30px; color: #4fc22b;">Producto</b>', $sDisplay);
					if ($_POST['row-prd2-type'][$nKey] == 'c')
						$sDisplay = preg_replace('/(Categoría)$/i', '<b style="position: absolute; right: 30px; color: #881111;">Categoría</b>', $sDisplay);
					if ($_POST['row-prd2-type'][$nKey] == 'm')
						$sDisplay = preg_replace('/(Marca)$/i', '<b style="position: absolute; right: 30px; color: #2b78c2;">Marca</b>', $sDisplay);
					if ($_POST['row-prd2-type'][$nKey] == 't')
						$sDisplay = preg_replace('/(Tipo de producto)$/i', '<b style="position: absolute; right: 30px; color: #000;">Tipo de producto</b>', $sDisplay);

					// Añadimos al array
					$aElementsDiscount[] = ['id' => $aProduct, 'name' => $_POST['row-prd2-name'][$nKey], 'display' => $sDisplay, 'type' => $_POST['row-prd2-type'][$nKey]];
				}
			}
		} // Si es una petición GET
		else if ($_SERVER['REQUEST_METHOD'] == 'GET') {
			// Si estamos editando
			if (isset($_GET['promotion'])) {
				// Obtenemos el registro
				$aRecords = tep_db_query('SELECT * FROM ' . TABLE_LANDINGS_DESCRIPTION . ' WHERE landing_id = "' . (int)$_GET['promotion'] . '";');

				// Registros en los idiomas
				while ($aRecord = tep_db_fetch_array($aRecords)) {
					// Recogemos las variables de BD
					$sTitle[$aRecord['language_id']]       = $aRecord['landing_title'];
					$sImage[$aRecord['language_id']]       = $aRecord['landing_image'];
					$sDescription[$aRecord['language_id']] = $aRecord['landing_description'];
					$sVideo[$aRecord['language_id']]       = $aRecord['landing_video'];
				}

				// Obtenemos el registro
				$aRecord = tep_db_query('SELECT promotion_name, promotion_start, promotion_end, promotion_discount_percent, promotion_discount_type, promotion_quantity, promotion_icon, promotion_extend, promotion_all, promotion_special FROM ' . TABLE_PROMOTIONS . ' WHERE promotion_id = "' . (int)$_GET['promotion'] . '";');
				$aRecord = tep_db_fetch_array($aRecord);

				// Recogemos las variables de BD
				$sName      = $aRecord['promotion_name'];
				$nPercent   = isset($aRecord['promotion_discount_percent']) ? $aRecord['promotion_discount_percent'] : '';
				$sType      = isset($aRecord['promotion_discount_type']) ? $aRecord['promotion_discount_type'] : '';
				$nQuantity  = isset($aRecord['promotion_quantity']) ? $aRecord['promotion_quantity'] : '';
				$nSpecial   = $aRecord['promotion_special'];
				$nToAll     = $aRecord['promotion_all'];
				$nExtend    = $aRecord['promotion_extend'];
				$sIcon      = $aRecord['promotion_icon'];
				$dDateStart = !empty($aRecord['promotion_start'])
					? date('Y-m-d\TH:i', strtotime($aRecord['promotion_start']))
					: '';

				$dDateEnd = !empty($aRecord['promotion_end'])
					? date('Y-m-d\TH:i', strtotime($aRecord['promotion_end']))
					: '';


				// Obtenemos los elementos de la promoción
				$aAuxs = tep_db_query('SELECT pe.*, m.manufacturers_name, cd.categories_name, pd.products_name FROM promotions_elements pe LEFT OUTER JOIN categories_description cd ON (pe.element_id = cd.categories_id AND cd.language_id = 3) LEFT OUTER JOIN manufacturers m ON (pe.element_id = m.manufacturers_id) LEFT OUTER JOIN products_description pd ON (pe.element_id = pd.products_id AND pd.language_id = 3) WHERE pe.promotion_id = "' . (int)$_GET['promotion'] . '";');

				// Rellenamos el array de elementos
				while ($aAux = tep_db_fetch_array($aAuxs)) {
					// Tipo
					$sElementType = $aAux['element_type'];

					// Según el tipo, producto
					if ($sElementType == 'p') {
						$sElementName    = $aAux['products_name'];
						$sElementDisplay = $aAux['products_name'] . '<b style="position: absolute; right: 30px; color: #4fc22b;">Producto</b>';
					} // Categoría
					else if ($sElementType == 'c') {
						$sElementName    = $aAux['categories_name'];
						$sElementDisplay = $aAux['categories_name'] . '<b style="position: absolute; right: 30px; color: #881111;">Categoría</b>';
					} // Fabricante
					else if ($sElementType == 'm') {
						$sElementName    = $aAux['manufacturers_name'];
						$sElementDisplay = $aAux['manufacturers_name'] . '<b style="position: absolute; right: 30px; color: #2b78c2;">Marca</b>';
					}

					// Añadimos al array
					$aElement = ['id' => $aAux['element_id'], 'name' => $sElementName, 'display' => $sElementDisplay, 'type' => $sElementType];

					// Si es para productos en promoción
					if ($aAux['element_operation'] == 'p')
						$aElementsPromotion[] = $aElement;
					// Si es para productos en descuento
					else if ($aAux['element_operation'] == 'd')
						$aElementsDiscount[] = $aElement;
				}
			}
		}

		require('includes/modules/promotions/templates/form.php');
		break;

	// Mostrar listado //
	default:
		// Titulos e iconos
		$sHeadTitle    = 'Promociones';
		$sHeadSubTitle = 'Listado de promociones de productos';

		// Obtenemos las promociones
		$sSql = 'select * from ' . TABLE_PROMOTIONS . ' ORDER BY promotion_status DESC, promotion_id DESC';

		// Le quitamos los tabuladores y saltos de linea para que splitpageesult funcione con el SQL
		$sSql       = preg_replace('/[\r\n\t]+/', ' ', $sSql);
		$aDatoSplit = new splitPageResults($sGetPage, MAX_DISPLAY_SEARCH_RESULTS, $sSql, $nAux);
		$aDatos     = tep_db_query($sSql);

		// Si no hay promociones
		if (tep_db_num_rows($aDatos) == 0)
			$sHtmlModule .= $messageStack->show(['class' => 'wrng', 'text' => 'No existe ninguna promoción.']);
		// Pintamos las promociones
		else {
			require('includes/modules/promotions/templates/index.php');
		}

		break;
}
?>

<?php
// Header
include(THEME . 'html/header.php');
?>
<link rel="stylesheet" type="text/css" href="includes/modules/promotions/css/style.css"/>
<?php
// Cabecera theme
if (!isset($_GET['a'])) {
	echo '<div>';
	echo '<div class="toolbarHead">';
	echo '<div class="hdr-tlbr">';
	echo '<h1 class="pageHeading ftitl" style="top: 6px;">' . $sHeadTitle . '</h1>';
	echo '<h2 class="stitl" style="top: 13px;">' . $sHeadSubTitle . '</h2>';
	echo '<div class="btn-right">';
	echo '<a href="' . tep_href_link(FILENAME_PROMOTIONS) . '?a=insert" title="Nueva promoción" style="position: relative; top: -11px;"><img src="images/icons/icon_new_promotion.png" alt="Nueva promoción" class="dx-hovr"></a>';
	echo '</div>';
	echo '</div>';
	echo '</div>';
	echo '</div>';
}

// Mensajes
//echo $messageStack->output();

// Modulo
echo $sHtmlModule;
?>
<script>
    var ajx = undefined;
    var tmSearch;
    var sUrlPage = "<?php echo $sUrlPage; ?>";
</script>
<?php
include(THEME . 'html/footer.php');
include(DIR_WS_INCLUDES . 'application_bottom.php');
?>
<script src="includes/modules/promotions/js/script.js"></script>
