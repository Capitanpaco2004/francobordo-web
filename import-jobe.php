<?php
include('includes/application_top.php');

// CONFIGURACIÓN ROBUSTA
ini_set('max_execution_time', 300);    // 5 minutos máximo
ini_set('memory_limit', '512M');       
set_time_limit(300);                   
error_reporting(E_ALL);                
ini_set('display_errors', 1);         

// Limpiar cualquier buffer de salida previo
if (ob_get_level()) {
    ob_end_clean();
}

// Constantes del feed y rutas
const FEED_URL        = 'https://joe.jobesports.com/beheer/cron/stock/stock_EUR.xml';
const FEED_PATH       = '/import/feed/jobe.xml';
const FEED_PERMISSION = 0777;

// Credenciales Basic Auth
const FEED_USER       = '1400717';
const FEED_PASSWORD   = 'ljAQakspVO';

// Opciones de notificación
const NOTIFICATION_EMAIL = 'f.rodriguez@francobordo.com';
const EMAIL_SUBJECT      = '[CORRECTO] Importador de stock Jobe a Francobordo';
const EMAIL_BODY         = 'Se ha actualizado con éxito la tienda Francobordo.';

// Función de log mejorada
function logMsg($message) {
    echo $message . "\n";
    error_log("[JOBE] " . $message);
    flush();
    if (ob_get_level()) ob_flush();
}

if (! function_exists('downloadFeed')) {
    function downloadFeed(string $url, string $path): bool
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER         => false,
            CURLOPT_USERPWD        => FEED_USER . ':' . FEED_PASSWORD,
            CURLOPT_HTTPAUTH       => CURLAUTH_ANY,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; FrancobordoBot/1.0)',
        ]);
        $xml = curl_exec($ch);
        if ($xml === false) {
            error_log('cURL error: ' . curl_error($ch));
            // curl_close($ch);
            return false;
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // curl_close($ch);
        if ($httpCode !== 200) {
            error_log("Error al descargar feed, HTTP status $httpCode");
            return false;
        }
        if (file_put_contents($path, $xml) === false) {
            error_log("No se pudo escribir el XML en $path");
            return false;
        }
        return true;
    }
}

function normalizeEan(string $raw): string {
    $ean = preg_replace('/\D+/', '', $raw);
    return ltrim($ean, '0');
}

header('Content-Type: text/plain; charset=UTF-8');

logMsg("=== IMPORTADOR JOBE - USANDO EAN COMO REFERENCIA ===");

/* ============================================================
 *  1) Descargar el feed
 * ============================================================ */
$serverDir    = dirname(__FILE__);
$feedFilePath = $serverDir . FEED_PATH;

if (! downloadFeed(FEED_URL, $feedFilePath)) {
    exit('Error al descargar el feed XML.');
}
chmod($feedFilePath, FEED_PERMISSION);
logMsg("✓ Feed descargado correctamente");

/* ============================================================
 *  2) Cargar datos de BD usando EAN
 * ============================================================ */

// Productos base: EAN → [id, qty]
$productsBase = [];
$qb = tep_db_query(
    "SELECT products_id, products_quantity, TRIM(product_ean) AS ean
       FROM products
      WHERE manufacturers_id = 257
        AND products_status IN (0,1)
        AND product_ean <> ''"
);

while ($row = tep_db_fetch_array($qb)) {
    $key = normalizeEan($row['ean']);
    if ($key !== '') {
        $productsBase[$key] = [
            'id'  => (int)$row['products_id'],
            'qty' => (int)$row['products_quantity'],
        ];
    }
}
logMsg("✓ Productos base cargados: " . count($productsBase));

// Atributos: EAN → [pid, oid, ovid]
$attrMap = [];
$qa = tep_db_query(
    "SELECT pa.products_id, pa.options_id, pa.options_values_id, TRIM(pa.products_attributes_ean) AS ean
       FROM products_attributes pa
 INNER JOIN products p ON pa.products_id = p.products_id
      WHERE p.manufacturers_id = 257
        AND p.products_status IN (0,1)
        AND pa.products_attributes_ean <> ''"
);

while ($row = tep_db_fetch_array($qa)) {
    $key = normalizeEan($row['ean']);
    if ($key !== '') {
        $attrMap[$key] = [
            'pid'  => (int)$row['products_id'],
            'oid'  => (int)$row['options_id'],
            'ovid' => (int)$row['options_values_id'],
        ];
    }
}
logMsg("✓ Atributos cargados: " . count($attrMap));

/* ============================================================
 *  3) Procesar XML
 * ============================================================ */
$xml = simplexml_load_file($feedFilePath);
if ($xml === false) {
    exit('❌ Error: No se pudo parsear el XML');
}

$processed = $updProd = $updAttr = $skip = $miss = 0;
$startTime = time();

logMsg("✓ Iniciando procesamiento de XML...");

// EANs específicos para diagnosticar
$targetEans = ['8718181308427', '8718181287951'];

foreach ($xml->product as $node) {
    $eanRaw = trim((string)$node->ean);
    $ean    = normalizeEan($eanRaw);
    $stock  = (int)trim((string)$node->stock);
    $productId = trim((string)$node->productId);
    
    $processed++;
    
    // Mostrar progreso y verificar tiempo
    if ($processed % 500 == 0) {
        $elapsed = time() - $startTime;
        logMsg("  → Progreso: $processed productos procesados en {$elapsed}s...");
        
        // Parar si lleva más de 4 minutos
        if ($elapsed > 240) {
            logMsg("⚠️  Parando por límite de tiempo (4min) - Procesados: $processed");
            break;
        }
    }
    
    if ($ean === '') {
        $skip++;
        continue;
    }

    // Diagnóstico para EANs específicos
    if (in_array($eanRaw, $targetEans)) {
        logMsg("");
        logMsg("=== ★ PROCESANDO EAN ESPECÍFICO: $eanRaw ===");
        logMsg("ProductId: $productId | Stock XML: $stock");
        logMsg("¿En productos base? " . (isset($productsBase[$ean]) ? 'SÍ' : 'NO'));
        logMsg("¿En atributos? " . (isset($attrMap[$ean]) ? 'SÍ' : 'NO'));
        
        if (isset($productsBase[$ean])) {
            logMsg("Datos producto: ID={$productsBase[$ean]['id']}, Stock actual={$productsBase[$ean]['qty']}");
        }
        if (isset($attrMap[$ean])) {
            $stockAttr = $attrMap[$ean]['oid'] . '-' . $attrMap[$ean]['ovid'];
            logMsg("Datos atributo: PID={$attrMap[$ean]['pid']}, StockAttr=$stockAttr");
        }
    }

    /* ——— PRODUCTO BASE ——— */
    if (isset($productsBase[$ean])) {
        $pId     = $productsBase[$ean]['id'];
        $current = $productsBase[$ean]['qty'];
        $new     = ($current <= 0) ? ($stock > 0 ? -100 : -800) : $current;

        if ($new !== $current) {
            $updateQuery = "UPDATE products SET products_quantity = $new WHERE products_id = $pId";
            $result = tep_db_query($updateQuery);
            if ($result) {
                logMsg("[✓ UPDATE PROD] EAN:$ean | $current → $new (pid $pId)");
                $updProd++;
                // Actualizar en memoria
                $productsBase[$ean]['qty'] = $new;
            } else {
                logMsg("[❌ ERROR PROD] EAN:$ean | Error en UPDATE");
            }
        } else {
            if (in_array($eanRaw, $targetEans)) {
                logMsg("[SKIP PROD] EAN:$ean | Sin cambios (current: $current)");
            }
            $skip++;
        }
    }

    /* ——— ATRIBUTO ——— */
    if (isset($attrMap[$ean])) {
        $attr = $attrMap[$ean];
        $stockAttributes = $attr['oid'] . '-' . $attr['ovid'];
        
        $stockQuery = "SELECT products_stock_quantity 
                      FROM products_stock 
                      WHERE products_id = {$attr['pid']} 
                        AND products_stock_attributes = '$stockAttributes'
                      LIMIT 1";
        
        $r = tep_db_query($stockQuery);
        if ($r && ($row = tep_db_fetch_array($r))) {
            $currentA = (int)$row['products_stock_quantity'];
            $newA     = ($currentA <= 0) ? ($stock > 0 ? -100 : -800) : $currentA;

            if ($newA !== $currentA) {
                $updateQuery = "UPDATE products_stock 
                               SET products_stock_quantity = $newA 
                               WHERE products_id = {$attr['pid']} 
                                 AND products_stock_attributes = '$stockAttributes'";
                
                $result = tep_db_query($updateQuery);
                if ($result) {
                    logMsg("[✓ UPDATE ATTR] EAN:$ean | $currentA → $newA (pid {$attr['pid']}, attr $stockAttributes)");
                    $updAttr++;
                } else {
                    logMsg("[❌ ERROR ATTR] EAN:$ean | Error en UPDATE");
                }
            } else {
                if (in_array($eanRaw, $targetEans)) {
                    logMsg("[SKIP ATTR] EAN:$ean | Sin cambios (current: $currentA)");
                }
                $skip++;
            }
        } else {
            if (in_array($eanRaw, $targetEans)) {
                logMsg("[❌ NO STOCK] EAN:$ean | No existe en products_stock con attr $stockAttributes");
            }
        }
    }

    /* ——— EAN NO ENCONTRADO ——— */
    if (!isset($productsBase[$ean]) && !isset($attrMap[$ean])) {
        $miss++;
    }

    // Cerrar diagnóstico para EANs específicos
    if (in_array($eanRaw, $targetEans)) {
        logMsg("=== FIN DIAGNÓSTICO EAN: $eanRaw ===");
        logMsg("");
    }
}

/* ============================================================
 *  4) Resumen
 * ============================================================ */
$totalTime = time() - $startTime;
logMsg("");
logMsg("=== RESUMEN FINAL ===");
logMsg("Total productos en XML: " . count($xml->product));
logMsg("Procesados: $processed");
logMsg("Actualizados productos: $updProd");
logMsg("Actualizados atributos: $updAttr");
logMsg("Sin cambios: $skip");
logMsg("No encontrados: $miss");
logMsg("Tiempo total: {$totalTime}s");

/* ============================================================
 *  5) Notificación
 * ============================================================ */
if (isset($_GET['action']) && $_GET['action'] === 'rel') {
    tep_redirect('_admin/importador.php?i=m&imp=1', '');
}

$hdrs = [
    'MIME-Version: 1.0',
    'Content-type: text/plain; charset=UTF-8',
    'From: info@francobordo.com'
];
mail(NOTIFICATION_EMAIL, EMAIL_SUBJECT, EMAIL_BODY, implode("\r\n", $hdrs));

logMsg("=== IMPORTACIÓN COMPLETADA ===");
?>