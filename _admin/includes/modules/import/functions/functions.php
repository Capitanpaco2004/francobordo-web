<?php

function generateCategoriesMappedNulled()
{
    $nulleds = tep_db_query('SELECT * FROM import_categories WHERE import_categories_status = 1 AND (import_categories_mapped IS NULL OR import_categories_mapped = "") ORDER BY import_categories_parent_id ASC;');

    while ($nulled = tep_db_fetch_array($nulleds)) {
		$ids = '';

        $parent = tep_db_query('SELECT * FROM import_categories WHERE import_categories_status = 1 AND import_categories_id = "' . $nulled['import_categories_parent_id'] . '";');

        if (tep_db_num_rows($parent) > 0) {
            $parent = tep_db_fetch_array($parent);

            if ($parent['import_categories_mapped'] != '') {
                $parents = explode(',', $parent['import_categories_mapped']);
            } else {
                $parents = array(IMPORT_AZIMUT_SANDBOX_CATEGORY);
            }
        } else {
            $parents = array(IMPORT_AZIMUT_SANDBOX_CATEGORY);
        }

		foreach ($parents as $parent) {
			$insert = array();
			$insert['parent_id'] = $parent;
			tep_db_perform('categories', $insert);

			$id = tep_db_insert_id();
			$ids .= $id . ',';

			$insert = array();
			$insert['categories_id'] = $id;
			$insert['language_id'] = 3;
			$insert['categories_name'] = $nulled['import_categories_name'];
			tep_db_perform('categories_description', $insert);

			tep_db_query('UPDATE import_categories SET import_categories_mapped = "' . substr($ids, 0, -2) . '" WHERE import_categories_id = "' . $nulled['import_categories_id'] . '"');
		}
    }
}

function getAllCategoriesMapped($origin)
{
    $mappedAll = array();

    $auxs = tep_db_query('SELECT * FROM import_categories WHERE import_categories_origin = "' . $origin . '"');

    while ($aux = tep_db_fetch_array($auxs)) {
        $mappedAll[strtolower($aux['import_categories_name'])] = $aux;
    }

    return $mappedAll;
}

function getAllProductsByOrigin($origin)
{
    $productsAll = array();

    $auxs = tep_db_query('SELECT products_id, products_import_exclude, products_import_origin FROM products WHERE products_import_origin LIKE "' . strtoupper($origin) . '_%";');

    while ($aux = tep_db_fetch_array($auxs)) {
        $value = $aux['products_import_origin'];
        $value = preg_replace('/(' . strtoupper($origin) . '\_)/i', '', $value);

        $productsAll[$value] = $aux;
    }

    return $productsAll;
}

function addCategoryMapped($category, $parent, $origin, &$mappedAll)
{
    $category = trim($category);

	$aux = tep_db_query('SELECT * FROM import_categories WHERE import_categories_name = "' . $category . '" AND import_categories_parent_id = "' . $parent . '"');

	if (tep_db_num_rows($aux) > 0) {
		$aux = tep_db_fetch_array($aux);

		return $aux;
	}

	tep_db_query('INSERT INTO import_categories (import_categories_name, import_categories_parent_id, import_categories_origin) VALUES ("' . $category . '", "' . $parent . '", "' . $origin . '");');

	$categoryArray = array(
		'import_categories_id' => tep_db_insert_id(),
		'import_categories_name' => $category,
		'import_categories_parent_id' => $parent,
		'import_categories_status' => false,
		'import_categories_mapped' => null,
		'import_categories_origin' => $origin,
	);

	$mappedAll[strtolower($category)] = $categoryArray;

	return $categoryArray;
}

function changeStockAttribute($allAttributes, $ean, $quantity, $weight, $model, $stockLevel)
{
	$key = ($ean != '' && strlen($ean) > 0 ? $ean : $model);
	
	if(!isset($allAttributes[$key])) {
		return false;
	}
	
	$newProductsQuantity = false;
	$productsStockQuantity = $allAttributes[$key][0];
	$productsStockId = $allAttributes[$key][1];
	$hasInStock = $stockLevel == 'InStock';

	if ($hasInStock && $productsStockQuantity <= 0) {
		$newProductsQuantity = -100;
	}

	if (!$hasInStock && $productsStockQuantity <= 0) {
		$newProductsQuantity = -800;
	}

	if ($newProductsQuantity === false) {
		return false;
	}

	tep_db_perform('products_stock', ['products_stock_quantity' => $newProductsQuantity], 'update', 'products_stock_id = "' . (int)$productsStockId . '"');

	return true;
}

function addProduct($allProducts, $id, $name, $description, $price, $tax, $cost, $ean, $quantity, $weight, $model, $status, $origin, $manufacturer, $productsAll, $stockLevel)
{
    $inserted = false;
    $module = tep_db_prepare_input($_GET['module']);
    $products_quantity = false;

    if (($ean != '' && strlen($ean) > 0) || ($model != '' && strlen($model) > 0)) {
		$key = ($ean != '' && strlen($ean) > 0 ? $ean : $model);
        
		if (isset($allProducts[$key])) {
			$products_quantity = (int)$allProducts[$key]['products_quantity'];
			$inserted = true;
			$id = (int)$allProducts[$key]['products_id'];
		}
    }
	
    $tax_rates = array('21' => 1, '10' => 2, '4' => 3);
    $tax_class = $tax_rates[$tax];

    $insert = array();
    $insert['products_quantity'] = $quantity;
    $insert['products_model'] = $model;
    $insert['products_price'] = (float) $price;
    $insert['products_cost'] = $cost;
    $insert['product_ean'] = $ean;
    $insert['products_weight'] = $weight;
    //$insert['products_dropship'] = 1;
    $insert['products_tax_class_id'] = $tax_class;
    //$insert['products_google_shopping'] = 0;
    $insert['products_last_modified'] = 'now()';
    $insert['exclude_feedmachine'] = 0;
    $insert['manufacturers_id'] = getManufacturerIdFromName((string) $manufacturer);

    /**
     * Marcas que controlan el stock.
     */

    if ($stockLevel == 'OutStock') {
        $insert['products_quantity'] = -800;
    } else {
        $insert['products_quantity'] = -100;
    }

    /**
     * Marcas definidas por el cliente
     * en el ticket
     * @author Daniel Lucia <daniel.lucia@denox.es>
     */
    if (!in_array($insert['manufacturers_id'], [12, 348, 483])) {
        unset($insert['products_quantity']);

        if ($stockLevel == 'OutStock' && $products_quantity === 0) {
            $insert['products_quantity'] = 0;
        }
        
        if ($stockLevel != 'OutStock' && $products_quantity === 0) {
            $insert['products_quantity'] = -100;
        } 

    }

    if (!$inserted) {
        if (defined('IMPORT_' . strtoupper($module) . '_DATE_ADDED') && constant('IMPORT_' . strtoupper($module) . '_DATE_ADDED') != '') {
            $date = explode('/', constant('IMPORT_' . strtoupper($module) . '_DATE_ADDED'));
            $date = date('Y-m-d 00:00:00', strtotime($date[2] . '-' . $date[1] . '-' . $date[0]));
        } else {
            $date = date('Y-m-d H:i:s');
        }

        $insert['products_date_added'] = $date;
        //$insert['products_import_exclude'] = 1;
        $insert['products_import_origin'] = $origin;
        $insert['products_status'] = 0;

        tep_db_perform('products', $insert);

        $id = tep_db_insert_id();
    } else {
        tep_db_perform('products', $insert, 'update', 'products_id = "' . $id . '"');
    }

    $insert = array();
    $insert['products_id'] = $id;

    $insert['products_name'] = $name;
    $insert['products_description'] = $description;

    if (!$inserted) {
        $languages = tep_get_languages();

        foreach ($languages as $language) {
            $insert['language_id'] = $language['id'];
            tep_db_perform('products_description', $insert);
        }
    } else {
        if (!getProductsImportExclude($id)) {
            $languages = tep_get_languages();
            foreach ($languages as $language) {
                $insert['language_id'] = $language['id'];
                tep_db_perform('products_description', $insert, 'update', 'products_id = "' . $id . '" AND language_id = ' . $language['id']);
            }
        }
    }

    return $id;
}

/**
 * Obtiene si un producto es exlucido de la importación
 *
 * @param integer $id
 * @return boolean
 */
function getProductsImportExclude(int $id): bool
{

    $sql = sprintf(
        'SELECT products_import_exclude FROM products WHERE products_id = "%d"',
        $id
    );

    $datos = tep_db_query($sql);
    if (tep_db_num_rows($datos) > 0) {
        $dato = tep_db_fetch_array($datos);
        return intval($dato['products_import_exclude']) == 1;
    } else {
        return false;
    }
}

/**
 * Obtiene el ID de una marca.
 * Si no existe la crea
 *
 * @param string $name
 * @return integer
 */
function getManufacturerIdFromName(string $name): int
{

    $name = trim($name);

    if ($name == '') {
        return 0;
    }

    $sql = sprintf(
        'SELECT manufacturers_id FROM manufacturers WHERE manufacturers_name = "%s" LIMIT 1',
        $name
    );

    $datos = tep_db_query($sql);
    if (tep_db_num_rows($datos) > 0) {
        $dato = tep_db_fetch_array($datos);
        return intval($dato['manufacturers_id']);
    } else {

        $data = [
            'manufacturers_name' => $name,
            'date_added' => 'now()',
            'last_modified' => 'now()',
            'manufacturers_status' => 0,
        ];

        tep_db_perform(
            'manufacturers',
            $data
        );

        return intval(tep_db_insert_id());
    }
}

function addProductToCategories($product, $categories, $key)
{
/*
    if ($key != '' && strlen($key) > 0) {
        $sql = 'SELECT products_id, products_quantity FROM products WHERE (product_ean = "' . $key . '" OR products_model = "' . $key . '")';
        $aux = tep_db_query($sql);

        if (tep_db_num_rows($aux) > 0) {
            return false;
        }
    }
*/
    tep_db_query('DELETE FROM products_to_categories WHERE products_id = "' . $product . '";');

    foreach ($categories as $category) {
        tep_db_query('INSERT INTO products_to_categories VALUES ("' . $product . '", "' . $category . '");');
    }
}

function addImages($id, $name, $images)
{
    $productsImage = '';
    $productsSubimages = array();

    if (!is_array($images)) {
        $images[0] = $images;
    }

    foreach ($images as $index => $image) {
        $index += 1;

        if (is_array($image)) {
            $image = $image[0];
        }

        if ($image == '') {
            continue;
        }

        $image = preg_replace('/\s+/i', '%20', $image);

        if (file_exists(getCwd() . '/../images/productos/' . getSlug($name) . '-' . $index . '-' . $id . '.jpg')) {
            unlink(getCwd() . '/../images/productos/' . getSlug($name) . '-' . $index . '-' . $id . '.jpg');
        }

        $image = downloadImage($image, getCwd() . '/../images/productos/' . getSlug($name) . '-' . $index . '-' . $id . '.jpg');

        if ($index == 1) {
            $productsImage = $image;
        } else {
            $productsSubimages[] = $image;
        }
    }

    tep_db_query('UPDATE products SET products_image = "' . $productsImage . '", products_subimages = "' . (count($productsSubimages) > 0 ? preg_replace('/(\")/i', '\"', json_encode($productsSubimages)) : 'NULL') . '" WHERE products_id = "' . $id . '";');
}

function downloadImage($url, $path)
{
    $cURL = curl_init($url);
    $file = fopen($path, 'wb');
    curl_setopt($cURL, CURLOPT_FILE, $file);
    curl_setopt($cURL, CURLOPT_HEADER, 0);
    curl_exec($cURL);
    // sin curl_close(): deprecado en PHP 8.5 (no-op desde 8.0)
    fclose($file);

    return basename($path);
}

// Obtenemos recursivamente array de categorias
if (!function_exists('getRecursiveIdCategories')) {
    function getRecursiveIdCategories($aCategories, $nSearch, &$aReturn, $sSpace = '')
    {
        // Si existe el ID padre a buscar en el array de categorias
        if (isset($aCategories[$nSearch])) {
            // Recorremos las categorias del ID padre
            foreach ($aCategories[$nSearch] as $aCategory) {
                // Guardamos categoria
                $aReturn[] = array('id' => $aCategory['categories_id'], 'text' => $sSpace . $aCategory['categories_name']);
                // Llamada recursiva para obtener las categorías hijas
                getRecursiveIdCategories($aCategories, $aCategory['categories_id'], $aReturn, '&nbsp;&nbsp;&nbsp;');
            }
        }
    }
}

// Obtiene todas las categorias y devuelve un array en una sola consulta
if (!function_exists('getAllCategoryArray')) {
    function getAllCategoryArray()
    {
        // Variables
        global $languages_id;
        $aReturn = array();

        $aDatos = tep_db_query('select c.categories_id, cd.categories_name, c.parent_id, c.categories_image
								 from categories c
								 inner join categories_description cd on(c.categories_id = cd.categories_id)
								 where cd.language_id = "' . (int) $languages_id . '"
								 order by sort_order, cd.categories_name');

        while ($aDato = tep_db_fetch_array($aDatos)) {
            $aReturn[$aDato['parent_id']][] = $aDato;
        }

        ksort($aReturn);

        return $aReturn;
    }
}

function csvToArray($csvFile)
{
    $file_to_read = fopen($csvFile, 'r');

    while (!feof($file_to_read)) {
        $lines[] = fgetcsv($file_to_read, 1000, ',');
    }

    fclose($file_to_read);

    $keys = array();
    $parsed = array();
    foreach ($lines as $lineno => $fragments) {

        if (intval($lineno) === 0) {
            $keys = $fragments;
            continue;
        }

        $current = array();
        if (is_array($fragments)) {
            foreach ($fragments as $fragment_number => $value) {
                $current[$keys[$fragment_number]] = mb_convert_encoding($value ?? '', 'UTF-8', 'ISO-8859-1');
            }
        }

        $parsed[] = $current;
    }

    return $parsed;
}

/**
 * Obtiene el ID de una categoría a partir del
 * nombre y su padre
 *
 * @param string $name
 * @param integer $parent
 * @param integer $language
 * @return int
 */
function getCategoryIDFromName(string $name, int $parent, int $language = 3): int
{
    $name = trim($name);

    $sql = sprintf(
        'SELECT c.categories_id
		FROM categories c
		LEFT JOIN categories_description cd ON cd.categories_id = c.categories_id
		WHERE cd.categories_name = "%s" AND c.parent_id = %d AND cd.language_id = %d
		LIMIT 1',
        $name,
        $parent,
        $language
    );

    $datos = tep_db_query($sql);
    if (tep_db_num_rows($datos) > 0) {
        $dato = tep_db_fetch_array($datos);
        return $dato['categories_id'];
    } else {

        $data = [
            'parent_id' => $parent,
            'date_added' => 'now()',
            'last_modified' => 'now()',
            'categories_status' => 0,
        ];

        tep_db_perform(
            'categories',
            $data
        );

        $categories_id = tep_db_insert_id();
        $idiomas = tep_get_languages();
        foreach ($idiomas as $idioma) {
            $data = [
                'categories_id' => $categories_id,
                'language_id' => $idioma['id'],
                'categories_name' => $name,
                'categories_seo_name' => $name,
            ];

            tep_db_perform(
                'categories_description',
                $data
            );
        }

        return $categories_id;
    }
}

/**
 * Añade la linea a la tabla de productos importados
 *
 * @param string $product
 * @param string $supplier
 * @return bool
 */
function checkProductImport(string $product, string $supplier): bool
{
    $product = trim($product);
    $supplier = trim($supplier);

    $sql = sprintf(
        'SELECT id FROM products_import WHERE product = "%s" AND supplier = "%s"',
        $product,
        $supplier
    );

    $datos = tep_db_query($sql);
    if (tep_db_num_rows($datos) == 0) {
        tep_db_perform(
            'products_import',
            [
                'product' => $product,
                'supplier' => $supplier,
            ]
        );

        return false;
    } else {
        $sql = sprintf(
            'SELECT products_id FROM products WHERE UPPER(products_import_origin) LIKE "%s"',
            strtoupper($supplier . '_' . $product)
        );

        $datos = tep_db_query($sql);
        if (tep_db_num_rows($datos) == 0) {
            return true;
        }
    }

    return false;
}

function hasSubcategoryMapped(int $categoryID)
{
	if (! function_exists('subHasSubcategoryMapped')) {
		function subHasSubcategoryMapped(int $categoryID) {
			$categories = tep_db_query('SELECT import_categories_id, import_categories_mapped FROM import_categories WHERE import_categories_parent_id = "' . $categoryID . '"');

			if (tep_db_num_rows($categories) > 0) {
				while ($category = tep_db_fetch_array($categories)) {
					if ($category['import_categories_mapped'] != '') {
						return true;
					}

					subHasSubcategoryMapped($category['import_categories_id']);
				}
			}
		}
	}

	return subHasSubcategoryMapped($categoryID);
}

function deleteProductsRemapped($origin)
{
	$products = tep_db_query('SELECT p.products_id, p.products_import_origin, ic.import_categories_id, ptc.categories_id FROM products p INNER JOIN products_to_categories ptc ON (p.products_id = ptc.products_id) LEFT OUTER JOIN import_categories ic ON ((ptc.categories_id = ic.import_categories_mapped OR ptc.categories_id LIKE CONCAT(ic.import_categories_mapped, ",%") OR ptc.categories_id LIKE CONCAT("%,", ic.import_categories_mapped) OR ptc.categories_id LIKE CONCAT("%,", ic.import_categories_mapped, ",%")) AND ic.import_categories_status = 1) WHERE p.products_import_origin LIKE "%' . $origin . '%" AND (ic.import_categories_id IS NULL OR ic.import_categories_id = "")');

	while ($product = tep_db_fetch_array($products)) {
		tep_remove_product($product['products_id']);
	}
}

function deleteCategoriesEmpty($categoryID, $main = true)
{
	$categories = tep_db_query('SELECT categories_id FROM categories WHERE parent_id = "' . $categoryID . '";');

	if (tep_db_num_rows($categories) > 0) {
		while ($category = tep_db_fetch_array($categories)) {
			deleteCategoriesEmpty($category['categories_id'], false);
		}
	}

	$categoriesCount = tep_db_query('SELECT COUNT(ptc.products_id) AS qty, c.categories_id FROM products_to_categories ptc INNER JOIN categories c ON (c.parent_id = ptc.categories_id) WHERE ptc.categories_id = "' . $categoryID . '";');
	$categoriesCount = tep_db_fetch_array($categoriesCount);

	if ($categoriesCount['qty'] == 0 && $categoriesCount['categories_id'] == '') {
		if (! $main) {
			tep_remove_category($categoryID);
		}
	}
}