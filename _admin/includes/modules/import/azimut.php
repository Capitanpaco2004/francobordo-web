<?php

ini_set('memory_limit', '-1');
set_time_limit(-1);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (defined('IMPORT_AZIMUT_ACTIVE') && IMPORT_AZIMUT_ACTIVE != 'true') {
    $messageStack->addSession('error', 'El módulo está desactivado y no se ha realizado, por tanto, ninguna operación.', 'error');
    tep_redirect($url . '?module=azimut');
}

$importLog = new import_log();
$importLog->log('Descargando feed de datos desde Azimut.');
$aProductos = csvToArray('../import/feed/Azimut/datos_nautica_francobordo.csv');
$importLog->log('Feed de datos descargado con éxito desde Azimut.');

// Variables
$contCategories = 0;
$contProducts = 0;
$product_tax = tep_get_tax_rate(1);

// Generamos categorías mapeadas
generateCategoriesMappedNulled();

// Limpieza
if (IMPORT_AZIMUT_REMOVE_REMAPED == 1) {
	deleteProductsRemapped('azimut');
}

// Obtenemos todas las categorias y productos
$mappedAll = getAllCategoriesMapped('azimut');
$productsAll = getAllProductsByOrigin('azimut');

$importLog->log('Tareas de limpieza realizadas.');

$importLog->setTotal(count($aProductos));


// Obtenemos todos los atributos para obtener un array ean => [stock, products_stock_id]
$allRowsProductsStock = pharaonix_query('SELECT products_stock_id, products_id, products_stock_attributes, products_stock_quantity from products_stock');
$allProductsStock = [];

while($productStockRow = tep_db_fetch_array($allRowsProductsStock->records)) {
	if(!isset($allProductsStock[$productStockRow['products_id']])) {
		$allProductsStock[$productStockRow['products_id']] = [];
	}
	
	$allProductsStock[$productStockRow['products_id']][$productStockRow['products_stock_attributes']] = [$productStockRow['products_stock_quantity'], $productStockRow['products_stock_id']];
}

$allRowsAttributes = pharaonix_query('SELECT products_id, options_id, options_values_id, products_attributes_ean from products_attributes');
$allAttributes = [];

while($attributeRow = tep_db_fetch_array($allRowsAttributes->records)) {
	if(!isset($allProductsStock[$attributeRow['products_id']])) {
		continue;
	}
	
	foreach($allProductsStock[$attributeRow['products_id']] as $key => $value) {
		if ($key == $attributeRow['options_id'] . '-' . $attributeRow['options_values_id']) {
			$allAttributes[$attributeRow['products_attributes_ean']] = $value;
		}
	}
}

$allRowsProducts = pharaonix_query('SELECT products_model, product_ean, products_id, products_quantity from products');
$allProducts = [];

while($productRow = tep_db_fetch_array($allRowsProducts->records)) {
	$key = $productRow['product_ean'];
	$key = $key == '' || is_null($key) ? $productRow['products_model'] : '';

	if ($key == '' || is_null($key)) {
		continue;
	}

	$allProducts[$key] = $productRow;
}

foreach ($aProductos as $product) {

    //if (checkProductImport($product['Ean'] != '' ? (string) $product['Ean'] : (string) $product['ProductCode'], 'AZIMUT')) {
        //continue;
    //}

    $importLog->addRow();

	// Categoría //

    $parentID = 0;

    if (!isset($product['Category']) || $product['Category'] == '') {
        continue;
    }

    $categories = [
        'Importadores',
        'Azimut',
    ];

    $categories = array_merge($categories, explode(',', $product['Category']));

	// Recorremos categorías del producto
    foreach ($categories as $category) {
		$categoryMapped = false;

		reset($mappedAll);

		foreach ($mappedAll as $mapped) {
			// Si SI existe en import_categories
			if (getSlug($mapped['import_categories_name']) == getSlug($category) && $mapped['import_categories_parent_id'] == $parentID) {
				$categoryMapped = $mapped;
				++$contCategories;
				break;
			}
		}

		if ($categoryMapped === false) {
			$categoryMapped = addCategoryMapped($category, $parentID, 'azimut', $mappedAll);
		}

		$parentID = $categoryMapped['import_categories_id'];
    }

	// Si la categoría está inactiva, pasamos
	if ($categoryMapped['import_categories_status'] == 0) {
		continue;
	}

	// Producto //

    $price = tep_add_tax($product['Price'], $product_tax);

    if (defined('IMPORT_AZIMUT_MIN_PRICE') && IMPORT_AZIMUT_MIN_PRICE != '' && $price < IMPORT_AZIMUT_MIN_PRICE) {
        continue;
    }

	changeStockAttribute($allAttributes, $product['Ean'], '', $product['Weight'], $product['ProductCode'], $product['StockLevel']);

    $productID = addProduct(
		$allProducts,
        $product['ProductCode'],
        $product['ProductName'],
        $product['Description'],
        $product['Price'] + (defined('IMPORT_AZIMUT_INCREASE_PRICE') && IMPORT_AZIMUT_INCREASE_PRICE != '' && IMPORT_AZIMUT_INCREASE_PRICE > 0 ? (($product['Price'] * (float) IMPORT_AZIMUT_INCREASE_PRICE) / 100) : 0),
        $product_tax,
        $product['Price'],
        $product['Ean'],
        '',
        $product['Weight'],
        'AZIMUT_' . ($product['ProductCode'] != '' ? (string) $product['ProductCode'] : (string) $product['Ean']),
        1,
        'AZIMUT_' . ($product['Ean'] != '' ? (string) $product['Ean'] : (string) $product['ProductCode']),
        $product['Fabricante'],
        $productsAll,
        $product['StockLevel']
    );

	if ($productID == false) {
		continue;
	}

	// Producto - Categoría //

	$toCategories = array_map('trim', explode(',', $categoryMapped['import_categories_mapped']));

    addProductToCategories($productID, $toCategories, ($product['Ean'] != '' ? (string) $product['Ean'] : (string) $product['ProductCode']));

    $aImages = [$product['ImageUrl']];

    addImages($productID, $product['ProductName'], array($aImages));

    //echo '#' . $productID . ' - ' . $product['ProductName'] . chr(10) . chr(13);

    ++$contProducts;
}

$importLog->log('Importación finalizada con éxito.');
$importLog->log('Se ha procesado un total de <b>' . $contCategories . ' categoría(s)</b> y se ha importado <b>' . $contProducts . ' producto(s)</b>.');

// Limpieza
if (IMPORT_AZIMUT_REMOVE_REMAPED == 1) {
	deleteCategoriesEmpty(IMPORT_AZIMUT_SANDBOX_CATEGORY);
}

// Mensajes
$messageStack->addSession('success', 'Se ha procesado un total de <b>' . $contCategories . ' categoría(s)</b> y se ha importado <b>' . $contProducts . ' producto(s)</b>.', 'success');

tep_redirect('import.php?module=azimut');
