<?php
include('includes/application_top.php');
include(DIR_WS_FUNCTIONS . 'products_specifications.php');

// Variables
$aProductos       = NULL;
$nCategoriasTotal = 0;
$nProductosTotal  = 0;
$aDatos           = NULL;
$sSql             = NULL;
$aPaginador       = NULL;
$bComparasion     = tep_has_spec_group($current_category_id, 'show_comparison');

// Comprobamos que nos esten enviando una categoria
if (isset($cPath) && tep_not_null($cPath)) {
	// Obtenemos los productos de la categoria si tuviese
	$sSql = 'select ' . SQL_SELECT . 'p.products_model, p.products_status, p.products_sort_order, GROUP_CONCAT( CONCAT( p2c.categories_id, ", ") ORDER BY p2c.categories_id ASC SEPARATOR " ") grcats, pd.products_description, pd.products_name, m.manufacturers_name, p.products_quantity, p.products_image, p.products_weight, p.products_id, p.manufacturers_id, p.products_price, p.products_tax_class_id, p.products_ship_free, IF(s.status, s.specials_new_products_price, NULL) as specials_new_products_price, IF(s.status, s.specials_new_products_price, p.products_price) as final_price
				 from products p
				 inner join products_description pd on (p.products_id = pd.products_id)
				 left join manufacturers m on (p.manufacturers_id = m.manufacturers_id)
				 inner join products_to_categories p2c on ( p.products_id = p2c.products_id)
				 ' . SQL_FROM . '
				 where p.products_status = 1
				 and pd.language_id = ' . $languages_id . '
				 and p2c.categories_id = ' . $current_category_id . '
				 group by p.products_id
				 order by p.products_status asc, p.products_sort_order asc';

	// Incluimos la configuracion del theme (filtros y ordenacion)
	include DIR_THEME . 'config_theme.php';

	// Cambiamos el SQL si existe un filtro
	changeFilter($sSql);

	// Obtenemos el paginador y los productos
	$aAux       = changePriceCustomer($sSql);
	$aProductos = $aAux['PRODUCTOS'];
	$aPaginador = $aAux['PAGE_PRODUCTOS'];

	// Numero total de categorias que existen en la categoria actual
	$nCategoriasTotal = (isset($_aAllCategorias[$current_category_id]) ? count($_aAllCategorias[$current_category_id]) : 0);
} // Si no nos envian una categoria mostramos todas
else {
	// Obtenemos todas las categorias
	$aCategorias = tep_db_query('select c.categories_id, cd.categories_name, c.categories_image
									  from categories c
									  inner join categories_description cd on (cd.categories_id = c.categories_id)
									  where c.parent_id = 0 and cd.language_id = ' . $languages_id . '
									  order by c.sort_order asc');

	// Numero total de categorias que existen en la categoria actual
	$nCategoriasTotal = tep_db_num_rows($aCategorias);
}

if (!isAjax() || !isset($_GET['type']) || $_GET['type'] != 'json') {
	// Cargamos la cabecera y la columna izquierda
	include(DIR_THEME . 'html/header.php');
	include(DIR_THEME . 'html/column_left.php');
}

// Paginación por scroll //
$sPrevUrl = '';
$sNextUrl = '';
if ($aPaginador) {
	if ($aPaginador->number_of_pages > 1) {
		$nPage        = intval($_GET['page'] ?? 0) > 0 ? intval($_GET['page'] ?? 0) : 1;
		$sUrlNext     = tep_href_link(basename($_SERVER['SCRIPT_FILENAME']), http_build_query(array_merge($_GET, ["page" => $nPage + 1])));
		$sUrlPrevious = tep_href_link(basename($_SERVER['SCRIPT_FILENAME']), http_build_query(array_merge($_GET, ["page" => $nPage - 1])));

		if ($nPage > 1 && $nPage <= $aPaginador->number_of_pages)
			$sPrevUrl = html_entity_decode($sUrlPrevious);

		if ($nPage < $aPaginador->number_of_pages)
			$sNextUrl = html_entity_decode($sUrlNext);
	}
}
// Paginación por scroll //

// Theme
include(DIR_THEME_ROOT . 'html/templates/' . basename(__FILE__));

// Liberamos
unset($aAux, $aProductos, $aPaginador, $sSql);

// Si no es ajax mostramos todo. Esto se usa para la paginación mediante ajax
if (!isAjax() || !isset($_GET['type']) || $_GET['type'] != 'json') {
	include(DIR_THEME . 'html/column_right.php');
	include(DIR_THEME . 'html/footer.php');
	include(DIR_WS_INCLUDES . 'application_bottom.php');
}
?>
