<?php
include( 'includes/application_top.php' );

$start = microtime(true);

// Ruta absoluta
$sServer = dirname(__FILE__);

// Descargamos el catálogo
$fXML = fopen($sServer . '/import/feed/touron.xml', 'wb');

$cURL = curl_init();
curl_setopt($cURL, CURLOPT_FILE, $fXML);
curl_setopt($cURL, CURLOPT_HEADER, 0);
curl_setopt($cURL, CURLOPT_CONNECTTIMEOUT, 0);
curl_setopt($cURL, CURLOPT_TIMEOUT, 0);
curl_setopt($cURL, CURLOPT_URL, 'https://www.touronsa.es/files/stocks.xml');
curl_exec($cURL);
// curl_close($cURL);
chmod($sServer . '/import/feed/touron.xml', 0777);


$end = microtime(true);
$time = $end - $start;
$filesize = filesize($sServer . '/import/feed/touron.xml');

echo "execute time: {$time}.<br>filesize: {$filesize}";
