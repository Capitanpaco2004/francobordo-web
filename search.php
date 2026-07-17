<?php
// Includes
include 'includes/application_top.php';
include DIR_WS_LANGUAGES . $language . '/' . FILENAME_ADVANCED_SEARCH;

$sSearch = strtolower(array_key_exists('buscar', $_GET) ? tep_db_prepare_input($_GET['buscar']) : '');

if (!defined('SEARCH_EAN')) {
    define('SEARCH_EAN', 'Si');
}

if (!defined('SEARCH_ID')) {
    define('SEARCH_ID', 'Si');
}

if (!defined('SEARCH_SHOW_FILTERS')) {
    define('SEARCH_SHOW_FILTERS', 'false');
}

if (!defined('SEARCH_DESCRIPTION')) {
    define('SEARCH_DESCRIPTION', 'Si');
}

// Variables
$aSearchPlural = array();
$aSearchSingular = array();
$aWords = null;
$aOrders = array();
$sAction = (array_key_exists('a', $_GET) ? tep_db_prepare_input($_GET['a']) : false);
$sCategories = (array_key_exists('categories', $_GET) && is_numeric($_GET['categories']) ? tep_db_prepare_input((int) $_GET['categories']) : false);
$sManufacturers = (array_key_exists('filtro', $_GET) && is_numeric($_GET['filtro']) ? tep_db_prepare_input((int) $_GET['filtro']) : false);
$nOrder = (array_key_exists('order', $_GET) ? tep_db_prepare_input($_GET['order']) : false);
$sTags = '';
$bBuscarId = false;

$bAutocomplete = ($sAction === 'autocomplete' && isAjax());
$bSearchDescription = (SEARCH_DESCRIPTION == 'Si' && !$bAutocomplete);

$aWordDelete = array('a', 'ante', 'bajo', 'cabe', 'con', 'contra', 'de', 'desde', 'en', 'entre', 'hacia', 'hasta', 'para', 'por', 'según', 'segun', 'sin', 'so', 'sobre', 'tras', 'del', 'al', 'el', 'la', 'las', 'los', 'un', 'unos', 'un', 'una', 'unas', 'y', 'u', 'o', 'e');

// Eliminamos preposición, artículo o nexo
foreach ($aWordDelete as $sWord) {
    $sSearch = preg_replace('/\b' . $sWord . '\b/i', '', $sSearch);
}
$sSearch = trim(preg_replace('/\s+/', ' ', $sSearch));

$sCachePath = null;
if ($bAutocomplete && $sSearch !== '' && strlen($sSearch) >= 3) {
    $sCacheDir = DIR_FS_CATALOG . 'cache/denox_ac/';
    if (!is_dir($sCacheDir)) @mkdir($sCacheDir, 0755, true);
    $sCacheKey = md5($language . '|' . $sSearch . '|' . ($sCategories ?: '') . '|' . ($sManufacturers ?: '') . '|' . ($nOrder ?: ''));
    $sCachePath = $sCacheDir . $sCacheKey;
    if (is_file($sCachePath) && (time() - filemtime($sCachePath)) < 90) {
        readfile($sCachePath);
        exit;
    }
    // GC oportunista (1% de probabilidad): borra entradas > 1h
    if (mt_rand(0, 99) === 0) {
        foreach (glob($sCacheDir . '*') ?: array() as $sFile) {
            if (is_file($sFile) && (time() - filemtime($sFile)) > 3600) @unlink($sFile);
        }
    }
}

// Si no nos envían nada a buscar, mostramos mensaje de error
if (!isAjax() && ($sSearch == '' || strlen($sSearch) <= 1)) {
	// Breadcrumb
	$breadcrumb->add('<span>' . ADVANCED_SEARCH_SUB_BREADCRUMB . '</span>', tep_href_link(FILENAME_SEARCH, '', 'NONSSL', true, false));
	include DIR_THEME . 'html/header.php';
	include DIR_THEME . 'html/column_left.php';
    include DIR_THEME_ROOT . 'html/templates/search.php';
    include DIR_THEME . 'html/column_right.php';
    include DIR_THEME . 'html/footer.php';
    include DIR_WS_INCLUDES . 'application_bottom.php';

	exit;
}

// Si tenemos búsqueda por EAN / Modelo / ID — sólo si la entrada parece código
// (un único token alfanumérico con al menos un dígito y longitud >= 4).
// La colación latin1_swedish_ci ya es case-insensitive, por eso quitamos LCASE()
// (que inhabilitaba los índices BTREE de products_model / product_ean).
$bLooksLikeCode = (
    (SEARCH_EAN == 'Si' || SEARCH_ID == 'Si')
    && $sSearch !== ''
    && strlen($sSearch) >= 4
    && preg_match('/^[a-z0-9._\-\/]+$/i', $sSearch)
    && preg_match('/[0-9]/', $sSearch)
);
if ($bLooksLikeCode) {
    // Consulta de productos por EAN / Modelo / ID
    $sQuery = 'SELECT p.products_id, pd.products_name FROM products p
				   INNER JOIN products_description pd ON (p.products_id = pd.products_id)
				   LEFT OUTER JOIN products_attributes pa ON (p.products_id = pa.products_id)
				   WHERE ';

    // Si tenemos búsqueda por EAN / Modelo
    if (SEARCH_EAN == 'Si') {
        $sQuery .= 'p.products_model = "' . $sSearch . '" OR p.product_ean = "' . $sSearch . '" OR pa.products_attributes_ean = "' . $sSearch . '"';
    }

    // Añadimos el OR
    if (SEARCH_EAN == 'Si' && SEARCH_ID == 'Si') {
        $sQuery .= ' OR ';
    }

    // Si tenemos búsqueda por ID
    if (SEARCH_ID == 'Si') {
        $sQuery .= 'p.products_id = "' . $sSearch . '"';
    }

    // AÃ±adimos comprobación de estado
    $sQuery .= ' AND p.products_status = 1 GROUP BY p.products_id;';

    // Lanzamos la consulta
    $aSearch = tep_db_query($sQuery);

    // Si hemos encontrado un producto por su EAN / Modelo
    if (tep_db_num_rows($aSearch) > 0) {
        // Registro
        $aSearch = tep_db_fetch_array($aSearch);

        // Redireccionamos, si no es AJAX
        if (!isAjax()) {
            tep_redirect(tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $aSearch['products_id']));
        }

        // Mostramos el resultado en AJAX
        else {
            // 'AND ' final OBLIGATORIO: más abajo se hace $sWhere .= ' p.products_status = 1'
            // (igual que su gemela de la línea ~158). Sin él quedaba
            // 'p.products_id = "X"  p.products_status = 1' → SQL 1064 near '"X"'.
            $sWhere = ' p.products_id = "' . (int) $aSearch['products_id'] . '" AND ';
            $bBuscarId = true;
        }
    } else if (tep_db_num_rows($aSearch) == 0) {
        // Consulta de productos por EAN / Modelo / ID
        $sQuery = 'SELECT p.products_id, CONCAT( pd.products_name, " - ", pov.products_options_values_name) AS products_name FROM products p INNER JOIN products_description pd ON (p.products_id = pd.products_id) INNER JOIN products_attributes pa ON (p.products_id = pa.products_id) INNER JOIN products_options_values pov ON (pa.options_values_id = pov.products_options_values_id AND pov.language_id = "' . (int) $languages_id . '") WHERE ';

        // Where
        $sQuery .= 'pa.products_attributes_ean = "' . $sSearch . '" OR pa.reference = "' . $sSearch . '"';
    }
    // Añadimos comprobación de estado
    //$sQuery .= ' AND p.products_status = 1 group by p.products_id;';

    // Lanzamos la consulta
    $aSearch = tep_db_query($sQuery);

    // Si hemos encontrado un producto por su EAN / Modelo
    if (tep_db_num_rows($aSearch) > 0) {
        // Registro
        $aSearch = tep_db_fetch_array($aSearch);

        // Redireccionamos, si no es AJAX
        if (!isAjax()) {
            tep_redirect(tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $aSearch['products_id']));
        }

        // Mostramos el resultado en AJAX
        else {
            $sWhere = ' p.products_id = "' . (int) $aSearch['products_id'] . '" AND ';
            $bBuscarId = true;
        }
    }
}

// Variables
$aProductos = array();
$sFinalPrice = 'p.products_price';

// Variables SQL
$sSelect = '';
$sJoins = '';
if (!isset($sWhere)) {
    $sWhere = '';
}

$sWherePrecio = '';
$sOrder = '';

// Singular y plural
$sSearch = getPluralSingular( $sSearch );

// Saneado para FULLTEXT BOOLEAN MODE: neutraliza los operadores especiales ( + - < > ( ) ~ * " @ ' )
// que, sin balancear (típico de bots de SQLi contra el buscador), rompen la sintaxis del operador
// MATCH...AGAINST con error 1064. La búsqueda por palabras se conserva (FULLTEXT tokeniza igual).
$sSearchSafe = str_replace( array('"', "'", '(', ')', '@', '+', '-', '~', '<', '>', '*'), ' ', $sSearch );

// Construimos los campos select
$sSelect = SQL_SELECT . ' IF (products_quantity>0, 1, 0) as disponibilidad, /*IF (pov.products_options_values_name is not null, 1, 0) as relevance,*/
	MATCH(pd.products_name) AGAINST ("' . $sSearchSafe . '" IN BOOLEAN MODE) AS relevance2,
	' . ($bSearchDescription ? 'MATCH(pd.products_description) AGAINST ("' . $sSearchSafe . '" IN BOOLEAN MODE) AS relevance3, ' : '') . '
    p.products_id, p.products_model, pd.products_description, p.products_price, p.products_tax_class_id, pd.products_name, p.products_quantity, p.products_image, p.products_date_available,
    IF(s.status, s.specials_new_products_price, NULL) as specials_new_products_price, IF(s.status, s.specials_new_products_price, p.products_price) as final_price';

// Construimos los joins
$sJoins = TABLE_PRODUCTS . ' p ';
$sJoins .= 'INNER JOIN ' . TABLE_PRODUCTS_DESCRIPTION . ' pd ON (p.products_id = pd.products_id AND pd.language_id = "' . (int) $languages_id . '") ';

// Si tenemos filtro
if (SEARCH_SHOW_FILTERS == 'Si') {
    // Añadimos al select las categorías y fabricantes
    $sSelect .= ', p2c.categories_id, cd.categories_name, m.manufacturers_id, m.manufacturers_name, m.manufacturers_image';

    // Añadimos en los joins las categorías y fabricantes
    $sJoins .= 'LEFT OUTER JOIN ' . TABLE_PRODUCTS_TO_CATEGORIES . ' p2c ON (p.products_id = p2c.products_id) ';
    $sJoins .= 'LEFT OUTER JOIN ' . TABLE_CATEGORIES_DESCRIPTION . ' cd ON (p2c.categories_id = cd.categories_id AND cd.language_id = "' . (int) $languages_id . '") ';
    $sJoins .= 'LEFT OUTER JOIN ' . TABLE_MANUFACTURERS . ' m ON (p.manufacturers_id = m.manufacturers_id) ';
}

// JOIN a products_attributes eliminado: no se referenciaba ninguna pa.* y la
// unión multiplicaba filas (~5x) que luego se aplastaban con GROUP BY p.products_id.

// Where
$sWhere .= ' p.products_status = 1';

// Si ya lo hemos encontrado por su ID no hacemos nada
if ($bBuscarId === false) {
    // $sSearchSafe ya está saneado arriba (neutraliza operadores BOOLEAN MODE).

    // Buscamos por nombre
    $sWhere .= ' AND (MATCH(pd.products_name) AGAINST ("' . $sSearchSafe . '" IN BOOLEAN MODE)';

    // Buscar por descripción (no se evalúa en autocomplete: añade tiempo y ruido a 3 chars)
    if ($bSearchDescription) {
        $sWhere .= ' OR MATCH(products_description) AGAINST ("' . $sSearchSafe . '" IN BOOLEAN MODE)';
    }

    // Cerramos parentesis
    $sWhere .= ')';
}

// Categoría
if ($sCategories !== false && $sCategories != '') {
    // Obtenemos las categorias hijo
    $aCategories = getIdCategoriasHijasRecursivoByIdCategoriaPadre($sCategories);
    $aCategories = array_filter(array_map('intval', explode(',', $aCategories)));
    $aCategories[] = (int) $sCategories;
    $aCategories = array_filter(array_unique($aCategories)); // sin 0s, sin duplicados

    if (!empty($aCategories)) {
        $sWhere .= ' AND p2c.categories_id IN (' . implode(',', $aCategories) . ') ';
    }
}

// Fabricante
if ($sManufacturers != '' && (int) $sManufacturers > 0) {
    $sWhere .= ' AND p.manufacturers_id = ' . $sManufacturers . ' ';
}

// Ocultar la propia marca al buscar su nombre: al buscar "seaflo" NO mostramos
// productos cuyo fabricante sea Seaflo (sí salen productos de OTRAS marcas que
// mencionan Seaflo en nombre/descripción). Mismo criterio que el proxy Meili.
// Mapa: token de búsqueda => manufacturers_id a excluir. Extensible.
$aHideBrandWhenSearched = array('seaflo' => 307);
foreach ($aHideBrandWhenSearched as $sBrandTok => $nBrandMid) {
    if (preg_match('/\b' . preg_quote($sBrandTok, '/') . '/i', $sSearch)) {
        $sWhere .= ' AND p.manufacturers_id <> ' . (int) $nBrandMid . ' ';
    }
}

// Generamos el orden
$sOrder = 'ORDER BY relevance2 DESC, ' . ($bSearchDescription ? 'relevance3 DESC, ' : '') . ' /*relevance DESC,*/ disponibilidad DESC,';

// Según tipo de búsqueda
switch ($nOrder) {
    case 3:
        $sOrder .= 'products_name DESC';
        break;

    case 4:
        $sOrder .= 'products_price ASC';
        break;

    case 5:
        $sOrder .= 'products_price DESC';
        break;

    case 2:
    default:
        $sOrder .= 'products_name ASC';
        break;
}

// Construimos la consulta SQL
$sSql = 'SELECT ' . $sSelect . ' FROM ' . $sJoins . ' WHERE ' . $sWhere . $sWherePrecio . ' GROUP BY p.products_id ' . $sOrder;


// Indicamos que la consulta de conteo se regenere
$sSqlCount = false;

// Autocomplete: saltamos el COUNT(*) sobre el FULLTEXT completo y devolvemos un
// total fijo (suficiente para que splitPageResults aplique LIMIT). La cabecera
// "Mostrando N de M" mostrará el LIMIT como total, lo cual es aceptable en
// autocomplete y elimina una query cara por keystroke.
if ($bAutocomplete) {
    $sNumeroAutocomplete = isset($_GET['numero']) && is_numeric($_GET['numero']) ? (int) $_GET['numero'] : 5;
    if ($sNumeroAutocomplete <= 0) { $sNumeroAutocomplete = 5; }
    $sSqlCount = 'SELECT ' . $sNumeroAutocomplete . ' AS total';
}

// Incluimos la configuracion del theme
include DIR_THEME . 'config_theme.php';

// Cambiamos el SQL si existe un filtro
changeFilter($sSql);

// Cambiamos filtro de categorias
// if( FILTERS_ACTIVE == 'Si' )
changeFilterCategorie($sSql, array('RECORDAR_SQL' => true));

// Obtenemos el paginador y los productos
$aAux = changePriceCustomer($sSql, array('COUNT_KEY' => 'p.products_id', 'QUERY_COUNT' => $sSqlCount, 'RECORDAR_SQL' => true));
$aProductos = $aAux['PRODUCTOS'];
$aPaginador = $aAux['PAGE_PRODUCTOS'];
$nProductosTotal = $aAux['TOTAL'];

// Si solo hemos encontrado un producto, redireccionamos
if ($sSearch != '' && $nProductosTotal == 1 && !isset($_GET['page']) && !isAjax()) {
    $aProducto = eachProducts();
    tep_redirect(tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $aProducto['products_id']));
}

// Si tenemos filtro
if (SEARCH_SHOW_FILTERS == 'Si') {
    // Consulta para obtener los filtros
    $aFilters = tep_db_query($sSqlRecordar);

    // Variables
    $aElements = array();
    $aElements['categories'] = array();
    $aElements['manufacturers'] = array();

    // Si tenemos categorías / fabricantes
    if (tep_db_num_rows($aFilters) > 0) {
        // Recorremos las categorías / fabricantes
        while ($aFilter = tep_db_fetch_array($aFilters)) {
            // Si tenemos categoría
            if ($aFilter['categories_id'] != '') {
                // Si NO existe la categoría
                if (!key_exists($aFilter['categories_id'], $aElements['categories'])) {
                    // Añadimos al array
                    $aElements['categories'][$aFilter['categories_id']] = array('name' => $aFilter['categories_name'], 'qty' => 1);

                    // Añadimos a los tags
                    if ($aFilter['categories_id'] == $sCategories) {
                        $sTags .= '<a href="' . tep_href_link('search.php', tep_get_all_get_params(array('categories')), 'NONSSL', true, false) . '" rel="categories">' . $aFilter['categories_name'] . '</a>';
                    }

                }
                // Si SI existe la categoría
                else {
                    $aElements['categories'][$aFilter['categories_id']]['qty'] += 1;
                }

            }

            // Si tenemos fabricante
            if ($aFilter['manufacturers_id'] != '') {
                // Si NO existe el fabricante
                if (!key_exists($aFilter['manufacturers_id'], $aElements['manufacturers'])) {
                    // Añadimos al array
                    $aElements['manufacturers'][$aFilter['manufacturers_id']] = array('name' => $aFilter['manufacturers_name'], 'image' => $aFilter['manufacturers_image'], 'qty' => 1);

                    // Añadimos a los tags
                    if ($aFilter['manufacturers_id'] == $sManufacturers) {
                        $sTags .= '<a href="' . tep_href_link('search.php', tep_get_all_get_params(array('manufacturers')), 'NONSSL', true, false) . '" rel="manufacturers">' . $aFilter['manufacturers_name'] . '</a>';
                    }

                }
                // Si SI existe la categoría
                else {
                    $aElements['manufacturers'][$aFilter['manufacturers_id']]['qty'] += 1;
                }

            }
        }
    }

    // Guardamos en variable de filtros
    $aFilters = $aElements;
}

// Theme
if ($sAction == 'autocomplete' && isAjax()) {
    ob_start();
    include DIR_THEME_ROOT . 'html/templates/search_autocomplete.php';
    $sAutocompleteOutput = ob_get_clean();
    if (!empty($sCachePath)) {
        @file_put_contents($sCachePath, $sAutocompleteOutput, LOCK_EX);
    }
    echo $sAutocompleteOutput;
}
elseif (isAjax() && $sAction == 'filter')
    include DIR_THEME_ROOT . 'html/templates/search_filter.php';
else
{
	if( ! isAjax() || ! isset( $_GET['type'] ) || $_GET['type'] != 'json' ) {
		// Breadcrumb
		$breadcrumb->add('<span> ' . ADVANCED_SEARCH_SUB_BREADCRUMB . '</span><span class="sepa">:</span> ' . $sSearch, tep_href_link(FILENAME_SEARCH, tep_get_all_get_params(), 'NONSSL', true, false));

		include DIR_THEME . 'html/header.php';
		include DIR_THEME . 'html/column_left.php';
	}
	
	// Paginación por scroll //
	$sPrevUrl = '';
	$sNextUrl = '';
	if ($aPaginador)
	{
		if ($aPaginador->number_of_pages > 1)
		{
			$nPage = intval($_GET['page']) > 0 ? intval($_GET['page']) : 1;
			$sUrlNext = tep_href_link(basename($_SERVER['SCRIPT_FILENAME']), http_build_query(array_merge($_GET, array("page" => $nPage + 1))));
			$sUrlPrevious = tep_href_link(basename($_SERVER['SCRIPT_FILENAME']), http_build_query(array_merge($_GET, array("page" => $nPage - 1))));
			
			if ( $nPage > 1 && $nPage <= $aPaginador->number_of_pages )
				$sPrevUrl = html_entity_decode( $sUrlPrevious );
			
			if ( $nPage < $aPaginador->number_of_pages )
				$sNextUrl = html_entity_decode( $sUrlNext );
		}
	}
	// Paginación por scroll //

    include DIR_THEME_ROOT . 'html/templates/search.php';

	if( ! isAjax() || ! isset( $_GET['type'] ) || $_GET['type'] != 'json' ) {
		// Pie y columna, si es una peticon ajax no mostramos
		include DIR_THEME . 'html/column_right.php';
		include DIR_THEME . 'html/footer.php';
		include DIR_WS_INCLUDES . 'application_bottom.php';
	}
}
/*else
{
	echo '<div class="contentScroll ax rows" data-url="' . tep_href_link( basename($PHP_SELF), '' . tep_get_all_get_params( array('type', 'info', 'x', 'y' ) ) ) . '">';
		while( $aProducto = eachProducts() )
			echo _product();
	echo '</div>';
}*/