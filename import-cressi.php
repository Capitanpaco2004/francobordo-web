<?php
include( 'includes/application_top.php' );

// Ruta absoluta
$sServer = dirname(__FILE__);

// Cambiamos el límite de la memoria
ini_set( 'memory_limit', '-1' );
// Establecemos el tiempo de espera en infinito
set_time_limit( -1 );

$sCressiXlsx = $sServer . '/descargas/cressi.xlsx';
if ( !file_exists( $sCressiXlsx ) ) {
	exit( '<p style="color:red;font-weight:bold;">ERROR: feed no encontrado en ' . htmlspecialchars( $sCressiXlsx ) . '</p>' . PHP_EOL );
}


// Añadimos composer
require dirname(__FILE__) . '/includes/vendor/autoload.php';

// Cargo librerías de Excel
use PhpOffice\PhpSpreadsheet\IOFactory;

try {
	$aPHPobj = IOFactory::load( $sCressiXlsx );
} catch ( Exception $e ) {
	exit( '<p style="color:red;font-weight:bold;">ERROR al cargar el xlsx: ' . htmlspecialchars( $e->getMessage() ) . '</p>' );
}
$aProducts = $aPHPobj->getActiveSheet()->toArray(null, true, true, true);

// Array para almacenar los modelos del Excel
$modelosEnExcel = array();

// Recorremos el feed
$nCont = 0;
foreach ($aProducts as $aProduct) {
    if ($nCont == 0) {
        ++$nCont;
        continue; // Saltamos la primera fila (encabezados)
    }

    // CAMBIO PRINCIPAL: Procesamos correctamente el valor de la columna D
    $rawStockValue = trim((string)($aProduct['D'] ?? ''));
    $nStock = processStockValue($rawStockValue);
    
    // Extraemos el modelo del producto desde la columna A
    $sModelo = trim((string)($aProduct['A'] ?? ''));

    if ($sModelo == '') {
        continue;
    }

    // Guardamos el modelo para después
    $modelosEnExcel[] = strtolower($sModelo);

    addProduct(
        array(
            'products_quantity' => $nStock,
            'products_model'    => $sModelo
        ),
        $aDatabase
    );
}

// NUEVA FUNCIONALIDAD: Actualizar productos ausentes a -900
updateMissingProductsToNegative900($modelosEnExcel);

if (isset($_GET['action']) && $_GET['action'] == 'rel') {
    tep_redirect('_admin/importador.php?i=m&imp=1', '');
}

/**
 * NUEVA FUNCIÓN: Procesa correctamente el valor de stock del Excel
 */
function processStockValue($rawValue)
{
    $rawValue = trim($rawValue);
    
    // Si está vacío, retornamos 0
    if (empty($rawValue)) {
        return 0;
    }
    
    // Convertimos a minúsculas para comparación
    $lowerValue = strtolower($rawValue);
    
    // Si es "unidades" o contiene la palabra "unidades", consideramos que hay stock
    if ($lowerValue === 'unidades' || strpos($lowerValue, 'unidades') !== false) {
        return 1; // Valor mayor que 0 para indicar que hay stock
    }
    
    // Si empieza con "+" (como "+10"), extraemos el número
    if (strpos($rawValue, '+') === 0) {
        $numericValue = floatval(substr($rawValue, 1));
        return $numericValue > 0 ? $numericValue : 1;
    }
    
    // Si es un número directo, lo convertimos
    if (is_numeric($rawValue)) {
        return floatval($rawValue);
    }
    
    // Si contiene números, intentamos extraerlos
    if (preg_match('/\d+/', $rawValue, $matches)) {
        return floatval($matches[0]);
    }
    
    // Por defecto, si no podemos determinar, asumimos que no hay stock
    return 0;
}

/**
 * Función para añadir productos (CORREGIDA la lógica de stock)
 */
function addProduct($aValues, $aDatabase)
{
    // Comprobamos si tenemos los valores mínimos
    if ( empty( $aValues['products_model'] ) )
        return false;

    $nStock = $aValues['products_quantity'];

    // Sanitización del SKU
    $sModeloEsc = tep_db_input( strtolower( $aValues['products_model'] ) );

    // Comprobamos si existe el registro en 'products'
    $aProducts = tep_db_query(
        'SELECT products_id, products_quantity FROM products
         WHERE LCASE(products_model) = "' . $sModeloEsc . '"
         AND manufacturers_id = 138 AND products_status = 1;'
    );

    // Si el registro existe
    if (tep_db_num_rows($aProducts) > 0) {
        $aProducts = tep_db_fetch_array($aProducts);

        // Lógica 3-way del stock (convención francobordo)
        if ($aProducts['products_quantity'] <= 0 && $nStock > 0)
            $aValues['products_quantity'] = -100;
        elseif ($aProducts['products_quantity'] <= 0 && $nStock <= 0)
            $aValues['products_quantity'] = -800;
        else
            $aValues['products_quantity'] = $aProducts['products_quantity'];

        if ($aValues['products_quantity'] != $aProducts['products_quantity'])
            update('products', $aValues, 'products_id = ' . (int) $aProducts['products_id'], $aDatabase);
    }

    // Comprobamos si existe el atributo del producto
    $aProducts = tep_db_query(
        'SELECT pa.products_id, pa.options_id, pa.options_values_id
         FROM products_attributes pa
         INNER JOIN products p ON (pa.products_id = p.products_id)
         WHERE LCASE(pa.reference) = "' . $sModeloEsc . '"
         AND p.manufacturers_id = 138 AND p.products_status = 1;'
    );

    if (tep_db_num_rows($aProducts) > 0) {
        $aProducts = tep_db_fetch_array($aProducts);
        $sAttrKey = (int) $aProducts['options_id'] . '-' . (int) $aProducts['options_values_id'];

        // Obtenemos stock del atributo
        $aStockAtri = tep_db_query(
            'SELECT products_stock_quantity FROM products_stock
             WHERE products_id = ' . (int) $aProducts['products_id'] . '
             AND products_stock_attributes = "' . tep_db_input( $sAttrKey ) . '"'
        );
        $aStockAtri = tep_db_fetch_array($aStockAtri);

        if ($aStockAtri['products_stock_quantity'] <= 0 && $nStock > 0)
            $nStock = -100;
        elseif ($aStockAtri['products_stock_quantity'] <= 0 && $nStock <= 0)
            $nStock = -800;
        else
            $nStock = $aStockAtri['products_stock_quantity'];

        if ($nStock != $aStockAtri['products_stock_quantity'])
            tep_db_query(
                'UPDATE products_stock SET products_stock_quantity = ' . (int) $nStock . '
                 WHERE products_id = ' . (int) $aProducts['products_id'] . '
                 AND products_stock_attributes = "' . tep_db_input( $sAttrKey ) . '"'
            );
    }

    return false;
}

/**
 * Función para ejecutar un update en la base de datos
 */
function update($sTable, $aValues, $sWhere, $aDatabase)
{
    // Preparamos la consulta de update
    $sInsert = 'UPDATE ' . $sTable . ' SET ';

    // Recorremos los campos y componemos el update (con escape)
    foreach ($aValues as $sKey => $aValue)
        $sInsert .= $sKey . ' = "' . tep_db_input( (string) $aValue ) . '", ';
    $sInsert = substr($sInsert, 0, -2);

    // Añadimos la cláusula WHERE
    $sInsert .= ' WHERE ' . $sWhere . ';';

    // Actualizamos el registro
    tep_db_query($sInsert);
}

/**
 * NUEVA FUNCIÓN: Actualiza productos ausentes del Excel a stock -900
 */
function updateMissingProductsToNegative900($modelosEnExcel)
{
    $contadorActualizados = 0;
    
    try {
        // Filtro: solo aplicamos -900 a productos que YA ESTABAN en -800 (no disponibles).
        // No tocamos -100 (disponibles vía proveedor): la falta puntual en el feed
        // puede ser ruido de Cressi y no debe convertirse en "fuera de catálogo".
        // Tampoco tocamos 0 ni positivos (stock real propio).
        $queryProductos = "SELECT products_id, products_model, products_quantity
                          FROM products
                          WHERE manufacturers_id = 138
                          AND products_status = 1
                          AND products_quantity = -800";

        $resultProductos = tep_db_query($queryProductos);

        // Procesamos cada producto encontrado
        while ($producto = tep_db_fetch_array($resultProductos)) {
            $modeloProducto = strtolower(trim($producto['products_model']));

            // Si el modelo NO está en el Excel, actualizamos a -900 (descatalogado definitivo)
            if (!in_array($modeloProducto, $modelosEnExcel)) {
                $queryUpdate = "UPDATE products
                               SET products_quantity = -900
                               WHERE products_id = " . (int) $producto['products_id'];
                tep_db_query($queryUpdate);
                $contadorActualizados++;
            }
        }

        // Mismo criterio para variantes: solo las que ya estaban en -800
        $queryAtributos = "SELECT ps.products_id, ps.products_stock_attributes, ps.products_stock_quantity, pa.reference
                          FROM products_stock ps
                          INNER JOIN products_attributes pa ON (
                              ps.products_id = pa.products_id
                              AND ps.products_stock_attributes = CONCAT(pa.options_id, '-', pa.options_values_id)
                          )
                          INNER JOIN products p ON (pa.products_id = p.products_id)
                          WHERE p.manufacturers_id = 138
                          AND p.products_status = 1
                          AND ps.products_stock_quantity = -800";

        $resultAtributos = tep_db_query($queryAtributos);
        $contadorAtributos = 0;

        while ($atributo = tep_db_fetch_array($resultAtributos)) {
            $referenciaAtributo = strtolower(trim($atributo['reference']));

            // Si la referencia NO está en el Excel, actualizamos SOLO ese atributo específico a -900
            if (!in_array($referenciaAtributo, $modelosEnExcel)) {
                $queryUpdateAtributo = "UPDATE products_stock
                                       SET products_stock_quantity = -900
                                       WHERE products_id = " . (int) $atributo['products_id'] . "
                                       AND products_stock_attributes = '" . tep_db_input( $atributo['products_stock_attributes'] ) . "'";
                tep_db_query($queryUpdateAtributo);
                $contadorAtributos++;
            }
        }

        // Log del resultado
        error_log("Actualizados a -900: $contadorActualizados productos principales, $contadorAtributos atributos");

    } catch (Exception $e) {
        error_log("Error actualizando productos ausentes: " . $e->getMessage());
    }
}
?>