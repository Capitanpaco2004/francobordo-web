<?php

use util\ShippingEstimator;

include('includes/application_top.php');

include(DIR_WS_INCLUDES . 'functions/shortcodes.php');
include(DIR_THEME_ROOT . 'functions/shortcodes.php');
include(DIR_WS_LANGUAGES . $language . '/' . FILENAME_PRODUCT_INFO);

// Si no hay products_id en GET o POST, o viene vacío, redirigimos
$products_id = $_GET['products_id'] ?? ($_POST['products_id'] ?? NULL);

if (empty($products_id)) {
	tep_redirect(tep_href_link('index.php'));
}


// Proceso ajax
if (isAjax()) {
	// Variables
	$sAction = $_GET['a'];

	switch ($sAction) {
		case 'chng_image':
			// Variables
			$sHtml      = '';
			$sHtmlThumb = '';
			$aImages    = $_POST['images'];

			$first = true;
			// Recorremos imagenes del atributo
			foreach ($aImages as $sImage) {
				$sHtmlThumb .= '<div><span>' . tep_image(DIR_WS_IMAGES . 'atributos/' . $sImage, '', 122, 122, '', false, false, false) . '</span></div>';

				$sHtml .= '<div class="item ' . ($first ? ' actv ' : '') . ' imge" data-src="' . DIR_WS_IMAGES . 'atributos/' . $aImages[0] . '">';
				$sHtml .= tep_image(DIR_WS_IMAGES . 'atributos/' . $sImage, '', 555, 589, '', false, false, false);
				$sHtml .= '</div>';

				$first = false;
			}

			echo json_encode(['html' => $sHtml, 'htmlthumb' => $sHtmlThumb]);
			break;

		case 'get-shipping-text':
			$sGetProductsId = (int)$_POST['products_id'];
			$oid            = (int)$_POST['option'];
			$value          = (int)$_POST['value'];

			if ($sGetProductsId == 0 || $oid == 0 || $value == 0) {
				echo json_encode([
									 'text'    => '',
									 'classes' => $aCheck['products_status'] == 1
										 ? claseBotonComprar($aCheck['products_quantity'], $aCheck['check_stock'])
										 : '',
								 ]);
				die();
			}


			// Obtenemos si hay que chequear stock
			$aCheck = tep_db_query('SELECT products_quantity, products_status, check_stock
                            FROM products
                            WHERE products_id = "' . $sGetProductsId . '";');
			$aCheck = tep_db_fetch_array($aCheck);

			// Calculamos stock real del atributo
			$nStock = stock_en_atributos($oid, $value, $sGetProductsId);
			$sClass = trim(claseBotonComprar($nStock, $aCheck['check_stock']));

			// Texto de estimación usando ShippingEstimator
			$sEstimate = ShippingEstimator::buildText($sClass);

			// Botón
			$button         = TEXT_SIN_STOCK;
			$buttonDisabled = true;

			// Si hay estimación válida => habilitamos botón
			if (!empty($sEstimate) && !preg_match('/sin\s*stock/i', $sEstimate)) {
				$button = IMAGE_BUTTON_RP_BUY_NOW;
				if (isset($_POST['products_together'])) {
					$button = TEXT_ANADIR;
				}
				$buttonDisabled = false;
			}

			// Color de atributo (igual que antes)
			$color                  = '';
			$products_options_query = tep_db_query("
        SELECT pov.products_options_values_id, pov.products_options_values_name, pov.products_options_hexcolor,
               pa.options_values_price, pa.reference, pa.price_prefix, pa.products_attributes_id,
               pa.options_values_weight, pa.weight_prefix
        FROM " . TABLE_PRODUCTS_ATTRIBUTES . " pa
        INNER JOIN " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov
            ON pa.options_values_id = pov.products_options_values_id
        WHERE pa.products_id = '" . (int)$sGetProductsId . "'
          AND pa.options_id = '" . $oid . "'
          AND pov.products_options_values_id = '" . $value . "'
          AND pov.language_id = '" . (int)$languages_id . "'
          AND find_in_set('" . $customer_group_id . "', attributes_hide_from_groups) = 0
        ORDER BY pa.products_options_sort_order
    ");
			$products_options_array = tep_db_fetch_array($products_options_query);

			if ($products_options_array['products_options_hexcolor'] != '') {
				$aHex           = explode(', ', $products_options_array['products_options_hexcolor']);
				$nPorcentColors = 100 / count($aHex);
				$nPorcent       = 0;
				$sColor         = '';

				foreach ($aHex as $hex) {
					$sColor   .= $hex . ' ' . $nPorcent . '%,';
					$nPorcent += $nPorcentColors;
					$sColor   .= $hex . ' ' . $nPorcent . '%,';
				}

				[$sRed, $sGreen, $sBlue] = sscanf($aHex[0], "#%02x%02x%02x");
				$color = '<div class="crcl-clor" style="background: '
					. (count($aHex) > 1 ? 'linear-gradient(90deg, ' . substr($sColor, 0, -1) . ')' : $aHex[0]
						. ($sRed >= 190 && $sGreen >= 190 && $sBlue >= 190 ? '; color: #000' : ''))
					. ';"></div>';
			}

			$response = [
				'text'           => $sEstimate,
				'button'         => $button,
				'buttonDisabled' => $buttonDisabled,
				'check'          => $aCheck,
				'color'          => $color,
				'classes'        => $sClass,
			];

			echo json_encode($response);
			die();
			break;
	}
	exit;
}

// Comprobamos que el producto existe
$product_check_query = tep_db_query("select count(*) as total, p.products_status from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd where p.products_id = '" . (int)$_GET['products_id'] . "' and pd.products_id = p.products_id and pd.language_id = '" . (int)$languages_id . "'");
$product_check       = tep_db_fetch_array($product_check_query);

//Comprobaciones del producto para dar el código de Error Correcto a Google
if ($product_check['total'] == '0') {
	header('HTTP/1.1 410 Not Found');
	header('Status: 410 Not Found');
} else if ($product_check['total'] == '1' && $product_check['products_status'] == '0') {
	header('HTTP/1.1 404 Not Found');
	header('Status: 404 Not Found');
}

// Start Products Specifications
require_once(DIR_WS_FUNCTIONS . 'products_specifications.php');

// Variables
$sGetProductsId   = preg_replace('/\{.+$/i', '', $_GET['products_id']);
$nCustomerGroupId = getCustomerGroupId();
$sHtmlAtributos   = '';
$aProductInfoAux  = [];


// Consulta SQL para obtener el producto
$sSql = 'SELECT ' . SQL_SELECT . ' p.products_status, p.products_min_order_qty, s.expires_date, GROUP_CONCAT( CONCAT( p2c.categories_id, ", ") ORDER BY p2c.categories_id ASC SEPARATOR " ") grcats, m.manufacturers_image, m.manufacturers_name, p.products_weight, p.products_pdfupload, p.products_id, pd.products_tab_1, pd.products_tab_2, pd.products_tab_3, pd.products_tab_4, pd.products_tab_5, pd.products_tab_6, pd.products_name, pd.products_description, p.products_model, p.products_youtube, p.products_quantity, p.check_stock, p.products_image, p.products_subimages, pd.products_url, p.products_price, p.products_tax_class_id, p.products_date_added, p.products_date_available, p.manufacturers_id, p.products_ship_free, p.products_bundle, p.products_youtube, p. sold_in_bundle_only
			 FROM products p
			 INNER JOIN products_description pd on (pd.products_id = p.products_id)
			 LEFT JOIN manufacturers m on (m.manufacturers_id = p.manufacturers_id)
			 LEFT JOIN products_to_categories p2c on (p2c.products_id = p.products_id)
			 ' . SQL_FROM . '
			 where p.products_status != "0" and p.products_id = "' . (int)$sGetProductsId . '" and pd.language_id = "' . (int)$languages_id . '"
			 group by p.products_id';


// Obtenemos el producto cambiando el precio segun tipo de cliente
$aAux = changePriceCustomer($sSql, ['PAGINAR' => false]);

// Si existe el producto
if ($aAux['TOTAL'] > 0) {
	// Producto
	$aProductInfoAux = $aAux['PRODUCTOS'];
	$aProductInfoAux = eachProducts($aProductInfoAux, ['RANTING' => true]);

	// Imagen principal
	$aProductInfoAux['imagenes'] = [$aProductInfoAux['products_image']];

	// Obtenemos las subimagenes
	if (is_string($aProductInfoAux['products_subimages']) && is_json($aProductInfoAux['products_subimages']))
		$aProductInfoAux['imagenes'] = array_merge($aProductInfoAux['imagenes'], json_decode($aProductInfoAux['products_subimages']));

	//++++ QT Pro: BOF
	$products_attributes_query = tep_db_query("select count(*) as total from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_ATTRIBUTES . " patrib where patrib.products_id='" . (int)$sGetProductsId . "' and patrib.options_id = popt.products_options_id and popt.language_id = '" . (int)$languages_id . "'");
	$products_attributes       = tep_db_fetch_array($products_attributes_query);

	if ($products_attributes['total'] > 0) {
		$products_id = (preg_match("/^\d{1,10}(\{\d{1,10}\}\d{1,10})*$/", $_GET['products_id']) ? $_GET['products_id'] : (int)$_GET['products_id']);
		require(DIR_WS_CLASSES . 'pad_' . PRODINFO_ATTRIBUTE_PLUGIN . '.php');

		$class             = 'pad_' . PRODINFO_ATTRIBUTE_PLUGIN;
		$pad               = new $class($sGetProductsId);
		$aProductAttribute = $pad->draw();
	}
	//++++ QT Pro: EOF
}

if (count($aProductInfoAux) == 0) {
	$breadcrumb->add(TEXT_HEADER_ITEM_NOT_FOUND);
}

// Theme
include(DIR_THEME . 'html/header.php');
include(DIR_THEME . 'html/column_left.php');

if ($product_check['total'] < 1 || $product_check['products_status'] == '0') {
	$hide_product = true; // needed for column_right
	echo '<div class="web-cntd prdt-cntd">';
	echo '<h1 class="pageHeading" style="width:680px;">' . TEXT_HEADER_ITEM_NOT_FOUND . '</h1>';
	echo '<div class="mensaje">' . TEXT_PRODUCT_NOT_FOUND . '</div>';
	include(DIR_WS_COMPONENTS . 'optional_related_products.php');
	echo '<div class="general-buttons__wrapper"><a class="xbutton verde" href="' . tep_href_link(FILENAME_DEFAULT) . '">' . IMAGE_BUTTON_CONTINUE . '</a></div>';
	echo '</div>';
} else {
	// Creamos la variable aProductoInfo
	$aProductoInfo = $aProductInfoAux;

	// Obtenemos la descripcion del producto
	$sDescripcionProducto = do_shortcode($aProductoInfo['products_description']);

	$aComentarios = tep_db_query('SELECT COALESCE(SUM(r.reviews_rating),0) as total_stars, COUNT(r.reviews_id) as total_count
									   FROM reviews r
									   INNER JOIN reviews_description rd ON(rd.reviews_id = r.reviews_id)
									   WHERE approved = 1 and products_id="' . (int)preg_replace('/{.+$/i', '', (int)$sGetProductsId) . '" ORDER BY r.date_added DESC');

	if (tep_db_num_rows($aComentarios) > 0) {
		$coment = tep_db_fetch_array($aComentarios);

		$aProductoInfo['review_count'] = $coment['total_count'];
		if ($coment['total_count'] == 0) {
			$aProductoInfo['review_rating'] = 0;
		} else {
			$aProductoInfo['review_rating'] = (int)$coment['total_stars'] / (int)$coment['total_count'];
		}

	}

	include 'includes/classes/attributes/option.class.php';

	// Inicializamos atributos
	// Plantilla html
	$aTemplate = [
		'OPTION_DEFAULT'    => '<div class="box-attr attr-wrpr %REPLACE_OPTION_TYPE_CLASS%">
            <aside>
            <div class="atitu">%REPLACE_OPTION_NAME%: <div class="color"></div></div>
            <div class="cntd d-flex">%REPLACE_VALUE_HTML%</div>
            </aside>
        </div>',
		'OPTION_CLASS'      => [
			'combobox' => 'cmbo',
			'box'      => 'cmbo',
		],
		'OPTION_HTML'       => [
			'pack'     => '%REPLACE_TEXT%',
			'box'      => '%REPLACE_HTML_EXTRA%<div style="display: none;" class="box-attr">%REPLACE_VALUE_SELECT%</div>',
			'combobox' => '%REPLACE_VALUE_SELECT%',
		],
		'OPTION_HTML_EXTRA' => [
			'combobox' => '%REPLACE_VALUE%',
			'box'      => '%REPLACE_VALUE%',
		],
	];

	// Opciones
	$aOptions = [
		'TEMPLATE'   => $aTemplate,
		'SHOW_PRICE' => true,
	];

	$objOption   = new option();
	// Liquidación: ocultar variantes sin stock real cuando el producto está marcado en liquidación
	$objOption->setFilterLiquidacionNoStock(true);
	$aHtmlOption = $objOption->getOptionHtml($_GET['products_id'], $aOptions);

	// Points/Rewards system V2.1rc2a BOF
	if ((USE_POINTS_SYSTEM == 'true') && (DISPLAY_POINTS_INFO == 'true')) {
		if ($new_price = tep_get_products_special_price($aProductoInfo['products_id']))
			$products_price_points = tep_display_points($new_price, tep_get_tax_rate($aProductoInfo['products_tax_class_id']));
		else
			$products_price_points = tep_display_points($aProductoInfo['products_price'], tep_get_tax_rate($aProductoInfo['products_tax_class_id']));

		$products_points       = tep_calc_products_price_points($products_price_points);
		$products_points_value = tep_calc_price_pvalue($products_points);
	}


	// Theme
	include(DIR_THEME_ROOT . 'html/templates/' . basename(__FILE__));
	//Asiganamos la variable para gtag

	$aProducto = $aProductoInfo;
}

// Theme
include(DIR_THEME . 'html/column_right.php');
include(DIR_THEME . 'html/footer.php');
include(DIR_WS_INCLUDES . 'application_bottom.php');
