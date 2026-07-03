<?php
include('includes/application_top.php');

// CONFIGURACIÓN PARA NAVEGADORES
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);

if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no'); // Para Nginx
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Importador Touron</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            #progress { background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 5px; }
            .timestamp { color: #666; }
            .success { color: #28a745; font-weight: bold; }
            .warning { color: #ffc107; font-weight: bold; }
            .error { color: #dc3545; font-weight: bold; }
            .debug { color: #17a2b8; }
        </style>
    </head>
    <body>
    <h2>Importador de Stock Touron</h2>
    <div id="progress">';
    
    // Forzar envío de headers
    if (ob_get_level()) {
        ob_end_flush();
    }
    flush();
}

// Ruta absoluta
$sServer = dirname(__FILE__);

// Configuración optimizada
ini_set('memory_limit', '2G');
set_time_limit(-1);

// Función mejorada para mostrar progreso
function showProgressWeb($message, $type = 'info') {
    $timestamp = date('H:i:s');
    $class = '';
    $icon = '';
    
    switch($type) {
        case 'success': $class = 'success'; $icon = '✅'; break;
        case 'warning': $class = 'warning'; $icon = '⚠️'; break;
        case 'error': $class = 'error'; $icon = '❌'; break;
        case 'debug': $class = 'debug'; $icon = '🔍'; break;
        default: $icon = 'ℹ️'; break;
    }
    
    if (php_sapi_name() === 'cli') {
        echo "[$timestamp] $icon $message\n";
    } else {
        echo "<span class='timestamp'>[$timestamp]</span> <span class='$class'>$icon $message</span><br>\n";
        
        // Forzar envío inmediato al navegador
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
        
        // Pequeña pausa para dar tiempo al navegador
        usleep(1000); // 1ms
    }
}

showProgressWeb("Iniciando importación...");

try {
    // Configurar MySQL
    tep_db_query("SET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");
    tep_db_query("SET SESSION foreign_key_checks = 0");
    tep_db_query("SET SESSION unique_checks = 0");
    tep_db_query("SET SESSION autocommit = 0");
    
    showProgressWeb("Configuración MySQL aplicada");

    // Iniciar transacción
    $transactionResult = tep_db_query("START TRANSACTION");
    if (!$transactionResult) {
        throw new Exception("Error iniciando transacción");
    }
    showProgressWeb("Transacción iniciada");

    // Cargar archivo XML
    showProgressWeb("Cargando archivo XML...");
    $xmlPath = $sServer . '/import/feed/touron/FRNBC/stocks.xml';
    
    if (!file_exists($xmlPath)) {
        throw new Exception("Archivo XML no encontrado: $xmlPath");
    }
    
    $aXML = file_get_contents($xmlPath);
    if ($aXML === false) {
        throw new Exception("Error leyendo archivo XML");
    }
    
    libxml_use_internal_errors(true);
    $aProducts = new SimpleXMLElement($aXML);
    
    $totalProducts = count($aProducts);
    showProgressWeb("XML cargado correctamente. Total de productos: $totalProducts", 'success');

    // Cargar datos en memoria
    $startTime = microtime(true);
    showProgressWeb("Cargando datos en memoria...");
    
    $aAllProducts = loadAllProductsOptimizedWeb();
    $aAllAtris = loadAllAttributesOptimizedWeb();
    $aAllStockAtris = loadAllStockAttributesOptimizedWeb();
    
    $loadTime = round(microtime(true) - $startTime, 2);
    showProgressWeb("Datos cargados en $loadTime segundos", 'success');

    // Procesar en lotes más pequeños para mejor feedback
    $batchSize = 2000; // Reducido para más actualizaciones
    $productUpdates = array();
    $attributeUpdates = array();
    $eanUpdates = array();
    $productosEnXML = array();
    
    $processedCount = 0;
    $currentBatch = array();

    showProgressWeb("Iniciando procesamiento en lotes de $batchSize productos...");

    // Procesar XML
    foreach ($aProducts as $aProduct) {
        $nStock = isset($aProduct->Cantidad) ? intval(trim($aProduct->Cantidad)) : 0;
        $nStockInternacional = isset($aProduct->stock_internacional) ? intval(trim($aProduct->stock_internacional)) : 0;
        $sModelo = isset($aProduct->Product) ? trim($aProduct->Product) : '';
        $sProductEAN = isset($aProduct->ean) ? trim($aProduct->ean) : '';

        if ($sModelo == '') continue;

        $currentBatch[] = array(
            'modelo' => $sModelo,
            'stock' => $nStock,
            'stock_internacional' => $nStockInternacional,
            'ean' => $sProductEAN
        );

        $productosEnXML[] = strtolower($sModelo);

        // Procesar lote
        if (count($currentBatch) >= $batchSize) {
            $results = processBatchOptimizedWeb($currentBatch, $aAllProducts, $aAllAtris, $aAllStockAtris);
            $productUpdates = array_merge($productUpdates, $results['products']);
            $attributeUpdates = array_merge($attributeUpdates, $results['attributes']);
            $eanUpdates = array_merge($eanUpdates, $results['eans']);
            
            $processedCount += count($currentBatch);
            $percentage = round(($processedCount / $totalProducts) * 100, 1);
            showProgressWeb("Procesados: $processedCount/$totalProducts productos ($percentage%)");
            $currentBatch = array();
        }
    }

    // Procesar lote final
    if (!empty($currentBatch)) {
        $results = processBatchOptimizedWeb($currentBatch, $aAllProducts, $aAllAtris, $aAllStockAtris);
        $productUpdates = array_merge($productUpdates, $results['products']);
        $attributeUpdates = array_merge($attributeUpdates, $results['attributes']);
        $eanUpdates = array_merge($eanUpdates, $results['eans']);
        $processedCount += count($currentBatch);
        showProgressWeb("Procesamiento XML completado: $processedCount productos", 'success');
    }

    showProgressWeb("Aplicando actualizaciones masivas...");
    showProgressWeb("- Productos a actualizar: " . count($productUpdates));
    showProgressWeb("- Atributos a actualizar: " . count($attributeUpdates));

    // Ejecutar actualizaciones
    showProgressWeb("Ejecutando actualizaciones...");
    try {
        executeBatchUpdatesWeb($productUpdates, $attributeUpdates, $eanUpdates);
        showProgressWeb("Actualizaciones ejecutadas correctamente", 'success');
    } catch (Exception $e) {
        throw new Exception("Error en actualizaciones: " . $e->getMessage());
    }

    // Productos no en XML - reactivado 2026-05-16 tras meses comentado por debug.
    showProgressWeb("Procesando productos no presentes en XML...");
    try {
        updateProductsNotInXMLOptimizedWeb($productosEnXML, $aAllProducts, $aAllAtris, $aAllStockAtris);
        showProgressWeb("Productos no presentes procesados correctamente", 'success');
    } catch (Exception $e) {
        throw new Exception("Error procesando productos no presentes: " . $e->getMessage());
    }

    // Confirmar transacción
    $commitResult = tep_db_query("COMMIT");
    if (!$commitResult) {
        throw new Exception("Error ejecutando COMMIT");
    }
    showProgressWeb("Transacción confirmada", 'success');
    
    $totalTime = round(microtime(true) - $startTime, 2);
    showProgressWeb("COMPLETADO: $processedCount productos procesados en $totalTime segundos", 'success');

} catch (Exception $e) {
    showProgressWeb("❌ EXCEPCIÓN CAPTURADA: " . $e->getMessage(), 'error');
    
    tep_db_query("ROLLBACK");
    showProgressWeb("Transacción revertida debido al error", 'error');
    
    exit(1);
} finally {
    // Restaurar configuración MySQL
    tep_db_query("SET SESSION foreign_key_checks = 1");
    tep_db_query("SET SESSION unique_checks = 1");
    tep_db_query("SET SESSION autocommit = 1");
    showProgressWeb("Configuración MySQL restaurada");
}

if (isset($_GET['action']) && $_GET['action'] == 'rel') {
    tep_redirect('_admin/importador.php?i=m&imp=1', '');
}

if (php_sapi_name() !== 'cli') {
    echo '</div>';
    echo '<h3 style="color: green;">✅ Importación completada con éxito</h3>';
    echo '</body></html>';
}

// FUNCIONES OPTIMIZADAS CON MEJOR FEEDBACK

function loadAllProductsOptimizedWeb()
{
    $aAllProducts = array();
    
    $result = tep_db_query("
        SELECT products_id, products_quantity, product_ean, LOWER(products_model) as products_model
        FROM products
        WHERE manufacturers_id IN (126, 267, 299, 528)
        AND products_status = 1
        ORDER BY products_id
    ");

    $count = 0;
    
    while ($row = tep_db_fetch_array($result)) {
        if (!isset($aAllProducts[$row['products_model']])) {
            $aAllProducts[$row['products_model']] = $row;
            $count++;
        }
    }

    showProgressWeb("Productos únicos cargados: $count");
    return $aAllProducts;
}

function loadAllAttributesOptimizedWeb()
{
    $aAllAtris = array();
    
    $result = tep_db_query("
        SELECT pa.products_id, pa.options_id, pa.options_values_id, 
               pa.products_attributes_ean, LOWER(pa.reference) as preference
        FROM products_attributes pa
        INNER JOIN products p ON (pa.products_id = p.products_id)
        WHERE p.manufacturers_id IN (126, 267, 299, 528)
        AND p.products_status = 1
        ORDER BY pa.products_id
    ");

    $count = 0;
    while ($row = tep_db_fetch_array($result)) {
        if (!isset($aAllAtris[$row['preference']])) {
            $aAllAtris[$row['preference']] = $row;
            $count++;
        }
    }

    showProgressWeb("Atributos únicos cargados: $count");
    return $aAllAtris;
}

function loadAllStockAttributesOptimizedWeb()
{
    $aAllStockAtris = array();
    
    $result = tep_db_query("
        SELECT products_id, products_stock_quantity, products_stock_attributes
        FROM products_stock
        ORDER BY products_id
    ");

    $count = 0;
    while ($row = tep_db_fetch_array($result)) {
        $aAllStockAtris[$row['products_id']][$row['products_stock_attributes']] = $row['products_stock_quantity'];
        $count++;
    }

    showProgressWeb("Stocks de atributos cargados: $count");
    return $aAllStockAtris;
}

function processBatchOptimizedWeb($batch, $aAllProducts, $aAllAtris, $aAllStockAtris)
{
    $productUpdates = array();
    $attributeUpdates = array();
    $eanUpdates = array();

    foreach ($batch as $item) {
        $modelo = $item['modelo'];
        $nStock = $item['stock'];
        $nStockInternacional = $item['stock_internacional'];
        $sProductEAN = $item['ean'];
        $modeloLower = strtolower($modelo);

        // Procesar producto principal
        if (isset($aAllProducts[$modeloLower])) {
            $product = $aAllProducts[$modeloLower];
            $newQuantity = calculateNewProductQuantityWeb($product['products_quantity'], $nStock, $nStockInternacional);
            
            if ($newQuantity != $product['products_quantity']) {
                $productUpdates[] = array(
                    'id' => $product['products_id'],
                    'quantity' => $newQuantity,
                    'ean' => ($product['product_ean'] == '' && $sProductEAN != '') ? $sProductEAN : null,
                    'modelo' => $modelo
                );
            }
        }

        // Procesar atributos
        if (isset($aAllAtris[$modeloLower])) {
            $attribute = $aAllAtris[$modeloLower];
            $stockKey = $attribute['options_id'] . '-' . $attribute['options_values_id'];
            $currentStock = isset($aAllStockAtris[$attribute['products_id']][$stockKey]) ? 
                           $aAllStockAtris[$attribute['products_id']][$stockKey] : 0;
            
            $newStock = calculateNewAttributeStockWeb($currentStock, $nStock, $nStockInternacional);
            
            if ($newStock != $currentStock) {
                $attributeUpdates[] = array(
                    'products_id' => $attribute['products_id'],
                    'stock_attributes' => $stockKey,
                    'quantity' => $newStock
                );
            }
        }
    }

    return array(
        'products' => $productUpdates,
        'attributes' => $attributeUpdates,
        'eans' => $eanUpdates
    );
}

function executeBatchUpdatesWeb($productUpdates, $attributeUpdates, $eanUpdates)
{
    // Ejecutar actualizaciones en lotes
    $batchSize = 1000;
    $totalUpdated = 0;
    
    for ($i = 0; $i < count($productUpdates); $i += $batchSize) {
        $batch = array_slice($productUpdates, $i, $batchSize);
        
        if (!empty($batch)) {
            $cases = array();
            $ids = array();
            
            foreach ($batch as $update) {
                $ids[] = $update['id'];
                $cases[] = "WHEN {$update['id']} THEN {$update['quantity']}";
            }
            
            if (!empty($cases)) {
                $sql = "UPDATE products SET products_quantity = CASE products_id " . 
                       implode(' ', $cases) . " END WHERE products_id IN (" . implode(',', $ids) . ")";
                
                $result = tep_db_query($sql);
                if (!$result) {
                    throw new Exception("Fallo en UPDATE de products");
                }
                
                $totalUpdated += count($batch);
                showProgressWeb("Actualizados " . count($batch) . " productos (Total: $totalUpdated)");
            }
        }
    }

    // Actualizar atributos
    if (!empty($attributeUpdates)) {
        showProgressWeb("Actualizando " . count($attributeUpdates) . " atributos...");
        
        for ($i = 0; $i < count($attributeUpdates); $i += $batchSize) {
            $batch = array_slice($attributeUpdates, $i, $batchSize);
            
            if (!empty($batch)) {
                $values = array();
                
                foreach ($batch as $update) {
                    $values[] = "({$update['products_id']}, '{$update['stock_attributes']}', {$update['quantity']})";
                }
                
                if (!empty($values)) {
                    $sql = "INSERT INTO products_stock (products_id, products_stock_attributes, products_stock_quantity) VALUES " . 
                           implode(',', $values) . " ON DUPLICATE KEY UPDATE products_stock_quantity = VALUES(products_stock_quantity)";
                    $res = tep_db_query($sql);
                    if (!$res) {
                        throw new Exception("Fallo en UPSERT de products_stock");
                    }
                }
            }
        }
    }
}

function updateProductsNotInXMLOptimizedWeb($productosEnXML, $aAllProducts, $aAllAtris, $aAllStockAtris)
{
    showProgressWeb("Buscando productos no presentes en XML...");
    
    try {
        // MMEF (motores Mercury EFI): excluidos del marcado -900. Si NO estan en el XML de
        // Touron se dejan a stock 0 (no descatalogado). Resto de marcas: comportamiento -900.
        $mmefIds = array();
        foreach ($aAllProducts as $modeloKey => $prodKey) {
            if (strpos($modeloKey, 'mmef') === 0) {
                $mmefIds[$prodKey['products_id']] = true;
            }
        }

        $productosParaActualizar = array();  // -> -900 (fuera de catalogo del proveedor)
        $productosParaCero       = array();  // -> 0    (MMEF no presente en XML)
        $atributosParaActualizar = array();  // -> -900
        $atributosParaCero       = array();  // -> 0    (MMEF no presente en XML)

        // Encontrar productos a actualizar
        foreach ($aAllProducts as $modelo => $producto) {
            if (!in_array($modelo, $productosEnXML) && $producto['products_quantity'] <= 0) {
                if (strpos($modelo, 'mmef') === 0) {
                    $productosParaCero[] = $producto['products_id'];
                } else {
                    $productosParaActualizar[] = $producto['products_id'];
                }
            }
        }

        // Encontrar atributos a actualizar
        foreach ($aAllAtris as $referencia => $atributo) {
            if (!in_array($referencia, $productosEnXML)) {
                $stockKey = $atributo['options_id'] . '-' . $atributo['options_values_id'];
                $stockActual = isset($aAllStockAtris[$atributo['products_id']][$stockKey]) ?
                              $aAllStockAtris[$atributo['products_id']][$stockKey] : 0;

                if ($stockActual <= 0) {
                    $fila = array(
                        'products_id' => $atributo['products_id'],
                        'stock_attributes' => $stockKey
                    );
                    if (isset($mmefIds[$atributo['products_id']])) {
                        $atributosParaCero[] = $fila;
                    } else {
                        $atributosParaActualizar[] = $fila;
                    }
                }
            }
        }

        // Ejecutar actualizaciones masivas
        if (!empty($productosParaActualizar)) {
            $chunks = array_chunk($productosParaActualizar, 1000);
            foreach ($chunks as $chunk) {
                $ids = implode(',', $chunk);
                $result = tep_db_query("UPDATE products SET products_quantity = -900 WHERE products_id IN ($ids)");
                if (!$result) {
                    throw new Exception("Error actualizando productos a -900");
                }
            }
        }

        // MMEF no en XML -> stock 0 (excluidos del -900)
        if (!empty($productosParaCero)) {
            $chunks = array_chunk($productosParaCero, 1000);
            foreach ($chunks as $chunk) {
                $ids = implode(',', $chunk);
                $result = tep_db_query("UPDATE products SET products_quantity = 0 WHERE products_id IN ($ids)");
                if (!$result) {
                    throw new Exception("Error actualizando MMEF a 0");
                }
            }
        }

        if (!empty($atributosParaActualizar)) {
            foreach ($atributosParaActualizar as $attr) {
                $result = tep_db_query("UPDATE products_stock SET products_stock_quantity = -900
                                       WHERE products_id = {$attr['products_id']}
                                       AND products_stock_attributes = '{$attr['stock_attributes']}'");
                if (!$result) {
                    throw new Exception("Error actualizando atributo a -900");
                }
            }
        }

        // Atributos MMEF no en XML -> stock 0
        if (!empty($atributosParaCero)) {
            foreach ($atributosParaCero as $attr) {
                $result = tep_db_query("UPDATE products_stock SET products_stock_quantity = 0
                                       WHERE products_id = {$attr['products_id']}
                                       AND products_stock_attributes = '{$attr['stock_attributes']}'");
                if (!$result) {
                    throw new Exception("Error actualizando atributo MMEF a 0");
                }
            }
        }

        showProgressWeb("Productos no en XML: " . count($productosParaActualizar) . " a -900 y " . count($productosParaCero) . " MMEF a 0 (productos); " . count($atributosParaActualizar) . " a -900 y " . count($atributosParaCero) . " MMEF a 0 (atributos)");

    } catch (Exception $e) {
        throw new Exception("Error en updateProductsNotInXMLOptimizedWeb: " . $e->getMessage());
    }
}

function calculateNewProductQuantityWeb($currentQuantity, $stock, $stockInternacional)
{
    // Cambio solicitado: si stock > 0 => nueva cantidad = -100 (antes: = stock)
    if ($currentQuantity <= 0) {
        if ($stock > 0) {
            return -100; // <<<<<<<<<<<<<< CAMBIO
        } elseif ($stock <= 0 && $stockInternacional > 0) {
            return -800;
        } elseif ($stock <= 0 && $stockInternacional <= 0) {
            return -900;
        }
    }
    return $currentQuantity;
}

function calculateNewAttributeStockWeb($currentStock, $stock, $stockInternacional)
{
    // Aplicamos la misma regla a atributos para consistencia
    if ($currentStock <= 0) {
        if ($stock > 0) {
            return -100; // <<<<<<<<<<<<<< CAMBIO
        } elseif ($stock <= 0 && $stockInternacional > 0) {
            return -800;
        } elseif ($stock <= 0 && $stockInternacional <= 0) {
            return -900;
        }
    }
    return $currentStock;
}
?>