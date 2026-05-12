<?php
include( 'includes/application_top.php' );

// Ruta absoluta
$sServer = dirname(__FILE__);

// Cambiamos el limite de la memoria
ini_set( 'memory_limit', '-1' );
// Establecemos el tiempo de espera en infinito
set_time_limit( -1 );

// Salto de línea según contexto
$br = php_sapi_name() === 'cli' ? "\n" : "<br>\n";
echo "Inicio importación Osculati - " . date('d/m/Y H:i:s') . $br;

// Login en osculati
$cURL = curl_init();
curl_setopt( $cURL, CURLOPT_URL, 'https://fw.osculati.it/ftp/?u=C54293&p=0XxBkWSb&path=ItemPrice4Web.txt' );
curl_setopt( $cURL, CURLOPT_SSL_VERIFYPEER, false );
curl_setopt( $cURL, CURLOPT_HEADER, 0 );
curl_setopt( $cURL, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6' );
curl_setopt( $cURL, CURLOPT_TIMEOUT, 60 );
curl_setopt( $cURL, CURLOPT_FOLLOWLOCATION, 1 );
curl_setopt( $cURL, CURLOPT_RETURNTRANSFER, 1 );
curl_setopt( $cURL, CURLOPT_COOKIESESSION, true );
curl_setopt( $cURL, CURLOPT_COOKIEJAR, 'cookie.txt' );
curl_setopt( $cURL, CURLOPT_COOKIEFILE, 'cookie.txt' );
$sHTML = curl_exec( $cURL );
unset( $cURL );

// Descargamos el catálogo
$feedFileRaw  = $sServer . '/import/feed/osculati_raw.txt';
$feedFileUtf8 = $sServer . '/import/feed/osculati.txt';

$fXML = fopen( $feedFileRaw, 'wb' );
$cURL = curl_init();
curl_setopt( $cURL, CURLOPT_FILE, $fXML );
curl_setopt( $cURL, CURLOPT_HEADER, 0 );
curl_setopt( $cURL, CURLOPT_CONNECTTIMEOUT, 0 );
curl_setopt( $cURL, CURLOPT_TIMEOUT, 0 );
curl_setopt( $cURL, CURLOPT_COOKIEFILE, 'cookie.txt' );
curl_setopt( $cURL, CURLOPT_URL, 'https://fw.osculati.it/ftp/ItemPrice4Web.txt' );
curl_exec( $cURL );
unset( $cURL );
fclose( $fXML );
chmod( $feedFileRaw, 0777 );

// Convertir UTF-16 a UTF-8
$rawContent = file_get_contents($feedFileRaw);
$utf8Content = mb_convert_encoding($rawContent, 'UTF-8', 'UTF-16');
file_put_contents($feedFileUtf8, $utf8Content);
unset($rawContent, $utf8Content);

echo "Feed descargado y convertido a UTF-8." . $br;

// Cargar todos los productos activos en memoria (modelo → id, qty)
$aProductsMap = [];
$qProducts = tep_db_query('SELECT products_id, LOWER(products_model) as model, products_quantity FROM products WHERE products_status = 1 AND products_model != ""');
while ($row = tep_db_fetch_array($qProducts)) {
    $aProductsMap[$row['model']] = ['id' => $row['products_id'], 'qty' => $row['products_quantity']];
}

// Cargar todos los atributos con referencia
$aAttribMap = [];
$qAttrib = tep_db_query('SELECT pa.products_id, LOWER(pa.reference) as ref, pa.options_id, pa.options_values_id, ps.products_stock_quantity
    FROM products_attributes pa
    INNER JOIN products p ON (pa.products_id = p.products_id)
    LEFT JOIN products_stock ps ON (ps.products_id = pa.products_id AND ps.products_stock_attributes = CONCAT(pa.options_id, "-", pa.options_values_id))
    WHERE p.products_status = 1 AND pa.reference != ""');
while ($row = tep_db_fetch_array($qAttrib)) {
    $aAttribMap[$row['ref']] = $row;
}

echo "Productos cargados: " . count($aProductsMap) . " | Atributos: " . count($aAttribMap) . $br;

// Procesar feed
// Estructura (separado por tabs):
// [0] código#sufijo  [1] modelo  [2] qty_pack  [3] unidad  [4] flag  [5] num  [6] EAN  [7] flag2  [8] marca  [9] desc_it  ... [última] stock
$fFile = fopen($feedFileUtf8, "r");
$nUpdated = 0;
$nAttribUpdated = 0;
$nLines = 0;
$nMatches = 0;

while (!feof($fFile)) {
    $sLine = fgets($fFile);
    if ($sLine === false) break;

    $sLine = trim($sLine);
    if ($sLine === '') continue;

    $aCols = explode("\t", $sLine);

    // Necesitamos al menos 10 columnas
    if (count($aCols) < 10) continue;

    $sModelo = strtolower(trim($aCols[1]));  // col[1] = modelo limpio
    $nStock  = isset($aCols[4]) ? (int) trim($aCols[4]) : 0;  // col[4] = Dispo (1=en stock, 0=sin stock)

    if ($sModelo === '') continue;

    $nLines++;

    // Producto por modelo
    if (isset($aProductsMap[$sModelo])) {
        $prod    = $aProductsMap[$sModelo];
        $nNewQty = ($prod['qty'] <= 0 && $nStock <= 0) ? -900 : $prod['qty'];
        $nMatches++;

        if ($nNewQty != $prod['qty']) {
            tep_db_query('UPDATE products SET products_quantity = ' . (int)$nNewQty . ' WHERE products_id = ' . (int)$prod['id']);
            $nUpdated++;
        }
    }

    // Atributo por referencia
    if (isset($aAttribMap[$sModelo])) {
        $attr         = $aAttribMap[$sModelo];
        $nCurrentQty  = (int) $attr['products_stock_quantity'];
        $nNewQty      = ($nCurrentQty <= 0 && $nStock <= 0) ? -900 : $nCurrentQty;
        $nMatches++;

        if ($nNewQty != $nCurrentQty) {
            tep_db_query('UPDATE products_stock SET products_stock_quantity = ' . (int)$nNewQty . ' WHERE products_id = ' . (int)$attr['products_id'] . ' AND products_stock_attributes = "' . $attr['options_id'] . '-' . $attr['options_values_id'] . '"');
            $nAttribUpdated++;
        }
    }
}

fclose($fFile);

echo $br . "Líneas procesadas: $nLines" . $br;
echo "Coincidencias encontradas: $nMatches" . $br;
echo "Productos actualizados: $nUpdated" . $br;
echo "Atributos actualizados: $nAttribUpdated" . $br;
echo $br . "FIN - " . date('d/m/Y H:i:s') . $br;
?>
