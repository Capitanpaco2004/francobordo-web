<?php
// Configuración de errores y límites
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/temp/error.log');

ini_set('memory_limit', '-1');
set_time_limit(0);
ini_set('max_execution_time', 0);

// Establecer zona horaria
date_default_timezone_set('Europe/Madrid');

// Ruta absoluta del servidor
$sServer = __DIR__;

// Iniciar medición de tiempo
$startTime = microtime(true);

// Verificar y crear directorios necesarios
$tempDir = $sServer . '/temp';
$logDir  = $tempDir . '/log_minderest';
$downloadDir = $tempDir . '/downloads';

foreach ([$tempDir, $logDir, $downloadDir] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
// Archivo de log para este proceso
$sLogName = 'log_minderest_' . date('d-m-Y_H-i-s') . '.txt';
$logFilePath = $logDir . '/' . $sLogName;
$fLog = fopen($logFilePath, 'wb');
if (!$fLog) {
    error_log("No se pudo abrir el archivo de log en: $logFilePath");
    exit("Error al abrir el log.");
}

function writeLog($fLog, $message) {
    fwrite($fLog, '"' . date('d/m/Y H:i:s') . '" ' . $message . "\r\n");
}

// Log: Inicio del importador
writeLog($fLog, '"Iniciado actualizador precios Minderest"');

// Incluir archivos necesarios
require('includes/application_top.php');
include $sServer . '/import/class/import.class.php';

// URL remota del CSV a descargar
$url = 'https://francobordo:qIFMXc66@app.minderest.com/export/1519214578825/export-repricing.csv';

// Definir ruta local donde se guardará el fichero descargado
$localCsvFile = $downloadDir . '/export-repricing.csv';

// Descargar el archivo remoto
$csvContent = file_get_contents($url);
if ($csvContent === false) {
    writeLog($fLog, '"Error al descargar el fichero CSV desde Minderest"');
    exit("Error al descargar el fichero CSV.");
}

// Guardar el contenido descargado en un archivo local
if (file_put_contents($localCsvFile, $csvContent) === false) {
    writeLog($fLog, '"Error al guardar el fichero CSV en la ruta local"');
    exit("Error al guardar el fichero CSV.");
}

// Inicializar la clase de importación usando el fichero local
$aImport = new import($localCsvFile, 'csv');

// Cargar datos desde la base de datos

// 1. Relaciones atributos-producto
$aProductsAttributes = array();
$query = 'SELECT pa.products_attributes_id, pa.products_id, pa.options_values_price, pa.price_prefix, 
                 po.products_options_name, pov.products_options_values_name
          FROM products_attributes pa
          INNER JOIN products p ON pa.products_id = p.products_id
          INNER JOIN products_options po ON pa.options_id = po.products_options_id AND po.language_id = 3
          INNER JOIN products_options_values pov ON pa.options_values_id = pov.products_options_values_id AND pov.language_id = 3';
$result = tep_db_query($query);
while ($row = tep_db_fetch_array($result)) {
    $aProductsAttributes[$row['products_id']][$row['products_attributes_id']] = [
        'price'        => $row['options_values_price'],
        'prefix'       => $row['price_prefix'],
        'option'       => $row['products_options_name'],
        'option_value' => $row['products_options_values_name']
    ];
}

// 2. Ofertas
$aProductsSpecials = array();
$query = 'SELECT specials_id, products_id, specials_new_products_price, status 
          FROM specials WHERE customers_group_id = 0';
$result = tep_db_query($query);
while ($row = tep_db_fetch_array($result)) {
    $aProductsSpecials[$row['products_id']] = [
        'id'    => $row['specials_id'],
        'price' => $row['specials_new_products_price'],
        'status'=> $row['status']
    ];
}

// 3. Productos base
$aProductosMind = array();
$query = 'SELECT p.products_id, p.products_price, pd.products_name
          FROM products p
          INNER JOIN products_description pd ON p.products_id = pd.products_id AND pd.language_id = 3';
$result = tep_db_query($query);
while ($row = tep_db_fetch_array($result)) {
    $aProductosMind[$row['products_id']] = [
        'price' => $row['products_price'],
        'name'  => html_entity_decode($row['products_name'] ?? '')
    ];
}

// Log: Inicio del procesamiento de productos
writeLog($fLog, '"Comienza recorrido de productos"');

$nI = 0; // Contador de productos procesados
$firstRow = true; // Flag para omitir la primera línea (encabezado)

// Procesar cada línea del CSV descargado
while ($aImport->read()) {
    // Saltar la primera fila (encabezados)
    if ($firstRow) {
        $firstRow = false;
        continue;
    }

    $sId        = $aImport->get(0);
    $sPrice     = $aImport->get(1);
    $nIncreased = $aImport->get(17);
    $nAtriPrice = 0;

    if ($sId == '' || $sPrice == '') {
        continue;
    }

    // Separar ID en caso de que sea un producto-atributo
    $aId = explode('-', $sId);
    $productId = $aId[0];

    // Si es un atributo (tiene segundo valor y es mayor que 0)
    if (isset($aId[1]) && $aId[1] > 0) {
        $attributeId = $aId[1];
        $nAtriPrice = $sPrice - $aProductosMind[$productId]['price'];
        if ($aProductsAttributes[$productId][$attributeId]['price'] != abs(round($nAtriPrice, 4))) {
            $msg = '"Actualizado producto-atributo \'' . $aProductosMind[$productId]['name'] . ' (' .
                   $aProductsAttributes[$productId][$attributeId]['option'] . ' - ' .
                   $aProductsAttributes[$productId][$attributeId]['option_value'] . ')\'"; Precio base: \'' .
                   $aProductosMind[$productId]['price'] . '\'; Añadido anterior: \'' .
                   $aProductsAttributes[$productId][$attributeId]['price'] . '\'; Añadido nuevo: \'' .
                   $nAtriPrice . '\'';
            writeLog($fLog, $msg);

            tep_db_query('UPDATE products_attributes SET options_values_price = "' . abs($nAtriPrice) .
                         '", price_prefix = "' . ($nAtriPrice < 0 ? '-' : '+') .
                         '" WHERE products_attributes_id = ' . $attributeId);
            tep_db_query('UPDATE products SET products_last_modified = now() WHERE products_id = ' . $productId);
        }
    } else {
        // Producto normal
        $porcentajeOferta = round(100 - (($sPrice * 100) / $aProductosMind[$productId]['price'])) . '%';
        if ($nIncreased == 1 && $porcentajeOferta < 5) {
            // Si tiene oferta, eliminarla
            if (isset($aProductsSpecials[$productId])) {
                $msg = '"Eliminada oferta \'' . $aProductosMind[$productId]['name'] . '\'; Oferta: \'' .
                       $aProductsSpecials[$productId]['price'] . '\' "';
                writeLog($fLog, $msg);
                writeLog($fLog, 'Query lanzada "DELETE FROM specials WHERE specials_id = ' . $aProductsSpecials[$productId]['id'] . '"');
                tep_db_query('DELETE FROM specials WHERE specials_id = ' . $aProductsSpecials[$productId]['id']);
            }
            // Actualizar precio si ha subido
            if ($aProductosMind[$productId]['price'] != $sPrice) {
                $msg = '"Actualizado producto que ha subido de precio \'' . $aProductosMind[$productId]['name'] .
                       '\'; Precio anterior: \'' . $aProductosMind[$productId]['price'] .
                       '\'; Precio nuevo: \'' . $sPrice . '\' "';
                writeLog($fLog, $msg);
                tep_db_query('UPDATE products SET products_price = "' . $sPrice .
                             '", products_last_modified = now() WHERE products_id = ' . $productId);
            }
        } else {
            // Gestión de ofertas o actualización de precio para productos sin incremento
            if (isset($aProductsSpecials[$productId])) {
                if ($aProductsSpecials[$productId]['price'] != $sPrice) {
                    $msg = '"Actualizada Oferta \'' . $aProductosMind[$productId]['name'] .
                           '\'; Oferta anterior: \'' . $aProductsSpecials[$productId]['price'] .
                           '\'; Oferta nueva: \'' . $sPrice . '\' "';
                    writeLog($fLog, $msg);
                    tep_db_query('UPDATE specials SET specials_new_products_price = "' . $sPrice .
                                 '", specials_last_modified = now(), status = 1 WHERE specials_id = ' . $aProductsSpecials[$productId]['id']);
                    tep_db_query('UPDATE products SET products_last_modified = now() WHERE products_id = ' . $productId);
                }
            } else {
                if ($aProductosMind[$productId]['price'] > $sPrice && $porcentajeOferta > 5) {
                    $msg = '"Creada Oferta \'' . $aProductosMind[$productId]['name'] .
                           '\'; Precio base: \'' . $aProductosMind[$productId]['price'] .
                           '\'; Oferta nueva: \'' . $sPrice . '\' "';
                    writeLog($fLog, $msg);
                    tep_db_query('INSERT INTO specials (products_id, specials_new_products_price, specials_date_added, status)
                                  VALUES (' . $productId . ', "' . $sPrice . '", now(), 1)');
                } elseif ($aProductosMind[$productId]['price'] != $sPrice) {
                    $msg = '"Actualizado producto que ha bajado de precio \'' . $aProductosMind[$productId]['name'] .
                           '\'; Precio anterior: \'' . $aProductosMind[$productId]['price'] .
                           '\'; Precio nuevo: \'' . $sPrice . '\' "';
                    writeLog($fLog, $msg);
                    tep_db_query('UPDATE products SET products_price = "' . $sPrice .
                                 '", products_last_modified = now() WHERE products_id = ' . $productId);
                }
            }
        }
    }
    $nI++;
}

// Tiempo total de ejecución
$endTime = microtime(true);
$executionTime = round(($endTime - $startTime), 2);

// Log: Fin del importador
writeLog($fLog, '"Finalizado importador Minderest"');

// Renombrar el log para marcar la finalización
$finalLogName = preg_replace('/\.txt/i', '_fin.txt', $sLogName);
rename($logFilePath, $logDir . '/' . $finalLogName);
fclose($fLog);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      font-family: Arial, sans-serif;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      margin: 0;
      background-color: #f0f0f0;
    }
    .message {
      background-color: #28a745;
      color: white;
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
      text-align: center;
      max-width: 500px;
      width: 100%;
    }
    .message h1 {
      margin: 0 0 10px 0;
      font-size: 24px;
    }
    .message p {
      margin: 5px 0;
      font-size: 18px;
    }
  </style>
  <title>Importación Finalizada</title>
</head>
<body>
<div class="message">
  <h1>¡Proceso Finalizado!</h1>
  <p>El proceso ha concluido exitosamente.</p>
  <p><strong>Total de productos procesados:</strong> <?php echo $nI; ?></p>
  <p><strong>Tiempo de ejecución:</strong> <?php echo $executionTime; ?> segundos</p>
</div>
</body>
</html>