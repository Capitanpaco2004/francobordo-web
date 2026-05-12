<?php
include('includes/application_top.php');

// Variables
$aProductos      = null;
$sTitular        = TITLE;
$nProductosTotal = 0;
$sSql            = null;
$aPaginador      = null;
$aDatos          = null;
$nLanding        = tep_db_prepare_input($_GET['landing_id']);

global $aAllPromotions;

// Comprobamos que nos envían un landing válido
if (!isset($nLanding) || !tep_not_null($nLanding)) {
	tep_redirect(FILENAME_DEFAULT);
}

// Obtenemos los datos del landing
$aLanding = tep_db_query(
	'SELECT *
     FROM promotions l
     INNER JOIN ' . TABLE_LANDINGS_DESCRIPTION . ' ld
       ON (l.promotion_id = ld.landing_id
           AND ld.language_id = "' . (int)$languages_id . '")
     WHERE l.promotion_id = "' . (int)$nLanding . '"
       AND DATE(NOW()) >= DATE(l.promotion_start)
       AND (DATE(NOW()) < DATE(l.promotion_end) OR l.promotion_end = "0000-00-00 00:00:00")
       AND l.promotion_status = 1;'
);

if (tep_db_num_rows($aLanding) <= 0) {
	tep_redirect(FILENAME_DEFAULT);
}

$aLanding = tep_db_fetch_array($aLanding);

// Ya tenemos $aAllPromotions gracias a PromotionManager en application_top.php
global $aAllPromotions;

// Verificamos si existen elementos para esta promoción específica
// Verificamos si existen elementos para esta promoción específica
if (isset($aAllPromotions[(int)$nLanding]['elements'])
	&& !empty($aAllPromotions[(int)$nLanding]['elements'])) {

	$aProductsIds      = [];
	$aCategoriesIds    = [];
	$aManufacturersIds = [];

	foreach ($aAllPromotions[(int)$nLanding]['elements'] as $aElement) {
		if (empty($aElement['element_operation'])
			|| empty($aElement['element_type'])
			|| empty($aElement['element_id'])) {
			continue;
		}

		// Solo mostramos los activadores (operation = 'p')
		if ($aElement['element_operation'] == 'p') {
			if ($aElement['element_type'] == 'p') {
				$aProductsIds[] = (int)$aElement['element_id'];
			} elseif ($aElement['element_type'] == 'c') {
				$aCategoriesIds[] = (int)$aElement['element_id'];
			} elseif ($aElement['element_type'] == 'm') {
				$aManufacturersIds[] = (int)$aElement['element_id'];
			}
		}
	}

	// Quitamos duplicados
	$aProductsIds      = array_unique($aProductsIds);
	$aCategoriesIds    = array_unique($aCategoriesIds);
	$aManufacturersIds = array_unique($aManufacturersIds);

	$conds = [];
	if (!empty($aProductsIds)) {
		$conds[] = 'p.products_id IN (' . implode(',', $aProductsIds) . ')';
	}
	if (!empty($aCategoriesIds)) {
		$conds[] = 'p2c.categories_id IN (' . implode(',', $aCategoriesIds) . ')';
	}
	if (!empty($aManufacturersIds)) {
		$conds[] = 'p.manufacturers_id IN (' . implode(',', $aManufacturersIds) . ')';
	}

	$sWhereLanding = '';
	if (!empty($conds)) {
		$sWhereLanding = 'AND (' . implode(' OR ', $conds) . ')';
	}

	if (!empty($sWhereLanding)) {
		// SQL base (sin specials, los mete changePriceCustomer)
		$sSql = 'SELECT ' . SQL_SELECT . '
                        p.products_model,
                        p.products_sort_order,
                        GROUP_CONCAT(CONCAT(p2c.categories_id, ", ") ORDER BY p2c.categories_id ASC SEPARATOR " ") grcats,
                        pd.products_description,
                        pd.products_name,
                        m.manufacturers_name,
                        p.products_quantity,
                        p.products_status,
                        p.products_image,
                        p.products_weight,
                        p.products_id,
                        p.manufacturers_id,
                        p.products_price,
                        p.products_tax_class_id,
                        p.products_ship_free
                 FROM ' . TABLE_PRODUCTS . ' p
                 LEFT JOIN ' . TABLE_MANUFACTURERS . ' m ON (p.manufacturers_id = m.manufacturers_id)
                 INNER JOIN ' . TABLE_PRODUCTS_DESCRIPTION . ' pd ON (p.products_id = pd.products_id)
                 INNER JOIN products_to_categories p2c ON (p.products_id = p2c.products_id)
                 ' . SQL_FROM . '
                 WHERE p.products_status = 1
                   AND pd.language_id = ' . (int)$languages_id . '
                 ' . $sWhereLanding . '
                 GROUP BY p.products_id
                 ORDER BY p.products_sort_order ASC';
	}
}


// Fallback si no se pudo construir la query
if (empty($sSql)) {
	$sSql = 'SELECT ' . SQL_SELECT . '
                    p.products_quantity,
                    p.products_status,
                    pd.products_description,
                    GROUP_CONCAT(CONCAT(ptc.categories_id, ", ") ORDER BY ptc.categories_id ASC SEPARATOR " ") grcats,
                    p.products_ship_free,
                    p.products_weight,
                    p.products_id,
                    pd.products_name,
                    p.products_tax_class_id,
                    p.products_image,
                    p.products_price
             FROM products p
             INNER JOIN products_description pd ON (p.products_id = pd.products_id)
             INNER JOIN products_to_categories ptc ON (p.products_id = ptc.products_id)
             INNER JOIN promotions_elements pe ON (
                 (pe.element_id = p.products_id AND pe.element_type = "p" AND pe.element_operation = "p") OR
                 (pe.element_id = p.manufacturers_id AND pe.element_type = "m" AND pe.element_operation = "p") OR
                 (pe.element_id = ptc.categories_id AND pe.element_type = "c" AND pe.element_operation = "p")
             )
             INNER JOIN promotions pr ON (pe.promotion_id = pr.promotion_id AND pr.promotion_id = ' . (int)$nLanding . ')
             ' . SQL_FROM . '
             WHERE p.products_status = 1
               AND pd.language_id = ' . (int)$languages_id . '
               AND pr.promotion_status = 1
               AND DATE(NOW()) >= DATE(pr.promotion_start)
               AND (DATE(NOW()) < DATE(pr.promotion_end) OR pr.promotion_end = "0000-00-00 00:00:00")
             GROUP BY p.products_id
             ORDER BY p.products_sort_order ASC';
}

// Incluimos la configuracion del theme (filtros y ordenacion)
include DIR_THEME . 'config_theme.php';

// Aplicamos filtros
changeFilter($sSql);

// Obtenemos productos con specials aplicados por changePriceCustomer
$aAux           = changePriceCustomer($sSql, ['COUNT_KEY' => 'p.products_id']);
$aProductos     = $aAux['PRODUCTOS'];
$aPaginador     = $aAux['PAGE_PRODUCTOS'];
$nProductosTotal= $aAux['TOTAL'];

// Render
// Paginación por scroll (igual que en categories.php)
$sPrevUrl = '';
$sNextUrl = '';

if ($aPaginador) {
	if ($aPaginador->number_of_pages > 1) {
		$nPage = (isset($_GET['page']) && (int)$_GET['page'] > 0) ? (int)$_GET['page'] : 1;

		$sUrlNext     = tep_href_link(basename($_SERVER['SCRIPT_FILENAME']), http_build_query(array_merge($_GET, array("page" => $nPage + 1))));
		$sUrlPrevious = tep_href_link(basename($_SERVER['SCRIPT_FILENAME']), http_build_query(array_merge($_GET, array("page" => $nPage - 1))));

		if ($nPage > 1 && $nPage <= $aPaginador->number_of_pages) {
			$sPrevUrl = html_entity_decode($sUrlPrevious);
		}

		if ($nPage < $aPaginador->number_of_pages) {
			$sNextUrl = html_entity_decode($sUrlNext);
		}
	}
}

// Render principal: mismo patrón que categories.php
if (!isAjax() || !isset($_GET['type']) || $_GET['type'] != 'json') {
	include(DIR_THEME . 'html/header.php');
	include(DIR_THEME . 'html/column_left.php');
}

// Aquí SIEMPRE cargamos la plantilla, tanto normal como AJAX/JSON
include(DIR_THEME_ROOT . 'html/templates/' . basename(__FILE__));

// Liberamos
unset($aFabricante, $aProductos, $sTitular, $nProductosTotal, $sSql, $aPaginador, $aDatos);

if (!isAjax() || !isset($_GET['type']) || $_GET['type'] != 'json') {
	include(DIR_THEME . 'html/column_right.php');
	include(DIR_THEME . 'html/footer.php');
	include(DIR_WS_INCLUDES . 'application_bottom.php');
}
