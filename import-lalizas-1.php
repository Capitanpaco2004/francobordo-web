<?php
include('includes/application_top.php');

// Constantes
const FEED_URL = 'http://www.lalizasmedia.com/eshop_xml/SPAIN_33.xml';
const FEED_PATH = '/import/feed/lalizas.xml';
const FEED_PERMISSION = 0777;
const NOTIFICATION_EMAIL = 'f.rodriguez@francobordo.com';
const EMAIL_SUBJECT = '[CORRECTO] Importador de stock lalizas a Francobordo';
const EMAIL_BODY = 'Se ha actualizado con exito la tienda Francobordo.';

// Ruta absoluta
$sServer = dirname(__FILE__);
$feedFilePath = $sServer . FEED_PATH;

// Descargar el feed
if (!downloadFeed(FEED_URL, $feedFilePath)) {
    exit('Error al descargar el feed.');
}
chmod($feedFilePath, FEED_PERMISSION);

// Obtener productos de la base de datos
$aAllProducts = fetchProducts();
$aAllAtriProducts = fetchAttributeProducts();

// Procesar XML
$aXML = file_get_contents($feedFilePath);
$aProducts = new SimpleXMLElement($aXML);

// Procesar productos del feed
foreach ($aProducts as $aProduct) {
    processProduct($aProduct, $aAllProducts, $aAllAtriProducts);
}

// Redirección si se solicita
if (isset($_GET['action']) && $_GET['action'] === 'rel') {
    tep_redirect('_admin/importador.php?i=m&imp=1', '');
}

// Notificación por correo
sendNotification(NOTIFICATION_EMAIL, EMAIL_SUBJECT, EMAIL_BODY);

/**
 * Descargar el feed XML
 */
function downloadFeed($url, $destination)
{
    $fXML = fopen($destination, 'wb');
    if (!$fXML) {
        return false;
    }

    $cURL = curl_init();
    curl_setopt($cURL, CURLOPT_FILE, $fXML);
    curl_setopt($cURL, CURLOPT_HEADER, 0);
    curl_setopt($cURL, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($cURL, CURLOPT_TIMEOUT, 30);
    curl_setopt($cURL, CURLOPT_URL, $url);

    $success = curl_exec($cURL);
    // curl_close($cURL);
    fclose($fXML);

    return $success;
}

/**
 * Obtener productos activos de la base de datos
 */
function fetchProducts()
{
    $result = tep_db_query(
        'SELECT products_id, products_quantity, LCASE(products_model) AS products_model 
         FROM products 
         WHERE manufacturers_id IN (3, 96, 16, 246) AND products_status = 1;'
    );

    $products = [];
    while ($row = tep_db_fetch_array($result)) {
        $products[$row['products_model']] = $row;
    }

    return $products;
}

/**
 * Obtener atributos de productos activos
 */
function fetchAttributeProducts()
{
    $result = tep_db_query(
        'SELECT pa.products_id, pa.options_id, pa.options_values_id, LCASE(pa.reference) AS reference 
         FROM products_attributes pa 
         INNER JOIN products p ON pa.products_id = p.products_id 
         WHERE p.manufacturers_id IN (3, 96, 16, 246) AND p.products_status = 1;'
    );

    $products = [];
    while ($row = tep_db_fetch_array($result)) {
        $products[$row['reference']] = $row;
    }

    return $products;
}

/**
 * Procesar producto del feed
 */
function processProduct($aProduct, $aAllProducts, $aAllAtriProducts)
{
    $nStock = isset($aProduct->Stock) ? (int) trim($aProduct->Stock) : 0;
    $sModelo = isset($aProduct->Code) ? strtolower(trim($aProduct->Code)) : '';

    if (empty($sModelo)) {
        return;
    }

    addProduct(['products_quantity' => $nStock, 'products_model' => $sModelo], $aAllProducts, $aAllAtriProducts);
}

/**
 * Añadir producto o actualizar stock
 */
function addProduct($productData, $allProducts, $allAtriProducts)
{
    $model = $productData['products_model'];
    $stock = $productData['products_quantity'];

    // Actualizar producto existente
    if (isset($allProducts[$model])) {
        updateProductStock($model, $stock, $allProducts);
    }

    // Actualizar stock de atributos
    if (isset($allAtriProducts[$model])) {
        updateAttributeStock($model, $stock, $allAtriProducts);
    }
}

/**
 * Actualizar stock del producto
 */
function updateProductStock($model, $stock, $allProducts)
{
    $product = $allProducts[$model];
    $currentStock = $product['products_quantity'];

    $newStock = ($currentStock <= 0) ? ($stock > 0 ? -100 : -800) : $currentStock;

    if ($newStock !== $currentStock) {
        tep_db_query(
            'UPDATE products SET products_quantity = ' . (int) $newStock . ' WHERE products_id = ' . (int) $product['products_id']
        );
    }
}

/**
 * Actualizar stock de atributos
 */
function updateAttributeStock($model, $stock, $allAtriProducts)
{
    $attribute = $allAtriProducts[$model];
    $query = 'SELECT products_stock_quantity FROM products_stock 
              WHERE products_id = ' . (int) $attribute['products_id'] . ' 
                AND products_stock_attributes = "' . $attribute['options_id'] . '-' . $attribute['options_values_id'] . '"';

    $result = tep_db_query($query);
    $currentStock = (int) tep_db_fetch_array($result)['products_stock_quantity'];

    $newStock = ($currentStock <= 0) ? ($stock > 0 ? -100 : -800) : $currentStock;

    if ($newStock !== $currentStock) {
        tep_db_query(
            'UPDATE products_stock SET products_stock_quantity = ' . (int) $newStock . ' 
             WHERE products_id = ' . (int) $attribute['products_id'] . ' 
               AND products_stock_attributes = "' . $attribute['options_id'] . '-' . $attribute['options_values_id'] . '"'
        );
    }
}

/**
 * Enviar notificación por correo
 */
function sendNotification($to, $subject, $message)
{
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/plain; charset=UTF-8',
        'From: info@francobordo.com'
    ];

    mail($to, $subject, $message, implode("\r\n", $headers));
}
?>