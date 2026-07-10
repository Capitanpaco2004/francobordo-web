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

// Normalización de tallas para casar sufijos de products_model con <size> del feed
// (misma convención que sizeKey() de _admin/Actualizador_precios_jobe.php).
function jobeNormalizeSize(string $size): string {
    $s = trim($size);
    if (preg_match('/^(\d+(?:[.,]\d+)?)\s*INCH$/i', $s, $m)) return str_replace(',', '.', $m[1]) . '"';
    return $s;
}
function jobeSizeKey(string $size): string {
    return preg_replace('/[^0-9A-Za-z]/', '', jobeNormalizeSize($size));
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
 *  1b) Tabla marcador de descatalogados (patrón cron VDM):
 *      solo se resucita (status 2→1) lo que ESTE cron ocultó;
 *      las altas en staging (status 2 del importador) jamás se publican solas.
 * ============================================================ */
tep_db_query(
    "CREATE TABLE IF NOT EXISTS jobe_feed_descatalogados (
        products_id int(11) NOT NULL,
        products_model varchar(96) NOT NULL DEFAULT '',
        date_added datetime NOT NULL,
        PRIMARY KEY (products_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci"
);
$hiddenByCron = [];
$qh = tep_db_query("SELECT products_id FROM jobe_feed_descatalogados");
while ($row = tep_db_fetch_array($qh)) {
    $hiddenByCron[(int)$row['products_id']] = true;
}

/* ============================================================
 *  2) Cargar datos de BD usando EAN
 *     Scope status 0,1,2: el 2 entra para poder resucitar ocultados
 *     y mantener centinelas al día también en staging (el status
 *     solo se cambia 1→2 al ocultar y 2→1 con marcador).
 * ============================================================ */

// Productos base: EAN → [id, qty, status, model]
$productsBase = [];
$qb = tep_db_query(
    "SELECT products_id, products_quantity, products_status, products_model, TRIM(product_ean) AS ean
       FROM products
      WHERE manufacturers_id = 257
        AND products_status IN (0,1,2)
        AND product_ean <> ''"
);

while ($row = tep_db_fetch_array($qb)) {
    $key = normalizeEan($row['ean']);
    if ($key !== '') {
        $productsBase[$key] = [
            'id'     => (int)$row['products_id'],
            'qty'    => (int)$row['products_quantity'],
            'status' => (int)$row['products_status'],
            'model'  => $row['products_model'],
        ];
    }
}
logMsg("✓ Productos base cargados: " . count($productsBase));

// Atributos: EAN → [pid, oid, ovid, pstatus]
$attrMap = [];
$qa = tep_db_query(
    "SELECT pa.products_id, pa.options_id, pa.options_values_id, p.products_status, TRIM(pa.products_attributes_ean) AS ean
       FROM products_attributes pa
 INNER JOIN products p ON pa.products_id = p.products_id
      WHERE p.manufacturers_id = 257
        AND p.products_status IN (0,1,2)
        AND pa.products_attributes_ean <> ''"
);

while ($row = tep_db_fetch_array($qa)) {
    $key = normalizeEan($row['ean']);
    if ($key !== '') {
        $attrMap[$key] = [
            'pid'     => (int)$row['products_id'],
            'oid'     => (int)$row['options_id'],
            'ovid'    => (int)$row['options_values_id'],
            'pstatus' => (int)$row['products_status'],
        ];
    }
}
logMsg("✓ Atributos cargados: " . count($attrMap));

// Info por pid (status/qty/model) para los padres de variantes, que no llevan product_ean
$parentInfo = [];
$qp = tep_db_query(
    "SELECT products_id, products_status, products_quantity, products_model
       FROM products
      WHERE manufacturers_id = 257
        AND products_status IN (0,1,2)"
);
while ($row = tep_db_fetch_array($qp)) {
    $parentInfo[(int)$row['products_id']] = [
        'status' => (int)$row['products_status'],
        'qty'    => (int)$row['products_quantity'],
        'model'  => $row['products_model'],
    ];
}

/* ============================================================
 *  3) Procesar XML
 * ============================================================ */
$xml = simplexml_load_file($feedFilePath);
if ($xml === false) {
    exit('❌ Error: No se pudo parsear el XML');
}

$processed = $updProd = $updAttr = $skip = $miss = 0;
$resur = $hiddenNew = 0;
$startTime = time();
$feedSeen  = [];      // EANs presentes en el feed de esta pasada
$feedModel = [];      // productId => filas [size, ean, stock] (fallback por products_model, 3c)
$aborted   = false;   // true si el recorrido se corta por tiempo (no fiarse para marcar -900)

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
            $aborted = true;
            break;
        }
    }

    // El mapa por productId se llena aunque la fila no traiga EAN: una fila sin EAN
    // sigue probando que ese productId/talla está en el catálogo Jobe.
    if ($productId !== '') {
        $feedModel[$productId][] = [
            'size'  => trim((string)$node->size),
            'ean'   => $eanRaw,
            'stock' => $stock,
        ];
    }

    if ($ean === '') {
        $skip++;
        continue;
    }

    $feedSeen[$ean] = true;

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
        $pId = $productsBase[$ean]['id'];

        // Resurrección: Jobe vuelve a listar CON stock un producto que este cron ocultó
        if ($stock > 0 && $productsBase[$ean]['status'] === 2 && isset($hiddenByCron[$pId])) {
            tep_db_query("UPDATE products SET products_status = 1, products_quantity = -100, products_last_modified = NOW() WHERE products_id = $pId AND products_status = 2");
            tep_db_query("DELETE FROM jobe_feed_descatalogados WHERE products_id = $pId");
            unset($hiddenByCron[$pId]);
            $productsBase[$ean]['status'] = 1;
            $productsBase[$ean]['qty']    = -100;
            logMsg("[✓ RESUCITADO] EAN:$ean | pid $pId vuelve al feed con stock → status 1, qty -100");
            $resur++;
        }

        $current = $productsBase[$ean]['qty'];
        // -100 = Jobe tiene stock (pedible). -900 = Jobe a 0: el portal lo pone
        // "Not available" y NO deja pedirlo, así que la web tampoco debe venderlo.
        $new     = ($current <= 0) ? ($stock > 0 ? -100 : -900) : $current;

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

        // Resurrección del PADRE oculto si alguna de sus variantes vuelve al feed con stock
        if ($stock > 0 && $attr['pstatus'] === 2 && isset($hiddenByCron[$attr['pid']])) {
            tep_db_query("UPDATE products SET products_status = 1, products_quantity = -100, products_last_modified = NOW() WHERE products_id = {$attr['pid']} AND products_status = 2");
            tep_db_query("DELETE FROM jobe_feed_descatalogados WHERE products_id = {$attr['pid']}");
            unset($hiddenByCron[$attr['pid']]);
            if (isset($parentInfo[$attr['pid']])) {
                $parentInfo[$attr['pid']]['status'] = 1;
                $parentInfo[$attr['pid']]['qty']    = -100;
            }
            logMsg("[✓ RESUCITADO] pid {$attr['pid']} (variante EAN:$ean) vuelve al feed con stock → status 1");
            $resur++;
        }

        $stockAttributes = $attr['oid'] . '-' . $attr['ovid'];
        
        $stockQuery = "SELECT products_stock_quantity 
                      FROM products_stock 
                      WHERE products_id = {$attr['pid']} 
                        AND products_stock_attributes = '$stockAttributes'
                      LIMIT 1";
        
        $r = tep_db_query($stockQuery);
        if ($r && ($row = tep_db_fetch_array($r))) {
            $currentA = (int)$row['products_stock_quantity'];
            $newA     = ($currentA <= 0) ? ($stock > 0 ? -100 : -900) : $currentA;

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
 *  3b) Descatalogados: EANs de la BD que ya NO vienen en el feed
 *      (fuera de catálogo Jobe: ni se puede pedir ni aparece en el portal).
 *      - status 1 sin stock → qty -900 + OCULTAR (status 2) + marcador
 *        jobe_feed_descatalogados (para que la resurrección solo toque estos).
 *      - status 0/2 → solo qty -900 (el status no se toca jamás aquí).
 *      Solo si el feed se recorrió ENTERO y tiene tamaño plausible; un feed
 *      truncado o vacío ocultaría media tienda en falso.
 *      El stock real puesto a mano (>0) NUNCA se toca.
 * ============================================================ */
$mark900Prod = $mark900Attr = 0;

if ($aborted) {
    logMsg("⚠️  Recorrido del feed incompleto: se omite el barrido de descatalogados");
} elseif (count($feedSeen) < 800) {
    logMsg("⚠️  Feed sospechosamente pequeño (" . count($feedSeen) . " EANs): se omite el barrido de descatalogados");
} else {
    // ¿Qué pids tienen ALGÚN EAN (de base o de variante) todavía en el feed?
    // (legacy raro: producto con product_ean Y variantes; si algo suyo sigue
    //  en el feed, Jobe aún lo sirve y NO se oculta)
    $pidSigueEnFeed = [];
    foreach ($productsBase as $ean => $p) {
        if (isset($feedSeen[$ean])) $pidSigueEnFeed[$p['id']] = true;
    }
    $pidTieneAttrs = [];
    foreach ($attrMap as $ean => $attr) {
        $pidTieneAttrs[$attr['pid']] = true;
        if (isset($feedSeen[$ean])) $pidSigueEnFeed[$attr['pid']] = true;
    }

    // Productos base
    $missingQtyIds = [];   // status 0/2: solo centinela
    $missingHide   = [];   // status 1: ocultar (pid => model)
    foreach ($productsBase as $ean => $p) {
        if (isset($feedSeen[$ean]) || $p['qty'] > 0 || isset($pidSigueEnFeed[$p['id']])) continue;
        if ($p['status'] === 1) {
            $missingHide[$p['id']] = $p['model'];
        } elseif ($p['qty'] !== -900) {
            $missingQtyIds[] = $p['id'];
        }
    }
    foreach (array_chunk($missingQtyIds, 200) as $chunk) {
        tep_db_query(
            "UPDATE products SET products_quantity = -900
              WHERE products_id IN (" . implode(',', $chunk) . ")
                AND products_quantity <= 0 AND products_quantity <> -900"
        );
    }
    $mark900Prod = count($missingQtyIds);
    if ($mark900Prod > 0) {
        logMsg("[✓ -900 PROD] $mark900Prod productos fuera del feed (status 0/2) marcados no disponibles");
    }

    foreach (array_chunk($missingHide, 200, true) as $chunk) {
        tep_db_query(
            "UPDATE products SET products_quantity = -900, products_status = 2, products_last_modified = NOW()
              WHERE products_id IN (" . implode(',', array_keys($chunk)) . ")
                AND products_status = 1 AND products_quantity <= 0"
        );
        $vals = [];
        foreach ($chunk as $pid => $model) {
            $vals[] = "($pid, '" . tep_db_input($model) . "', NOW())";
            $hiddenByCron[$pid] = true;
        }
        tep_db_query("INSERT IGNORE INTO jobe_feed_descatalogados (products_id, products_model, date_added) VALUES " . implode(',', $vals));
    }
    $hiddenNew += count($missingHide);
    if (count($missingHide) > 0) {
        logMsg("[✓ OCULTADOS] " . count($missingHide) . " productos fuera del feed sin stock → status 2 + marcador");
    }

    // Variantes cuyo EAN ya no viene en el feed → centinela en products_stock
    foreach ($attrMap as $ean => $attr) {
        if (isset($feedSeen[$ean])) continue;
        $stockAttributes = $attr['oid'] . '-' . $attr['ovid'];
        $r = tep_db_query(
            "SELECT products_stock_quantity FROM products_stock
              WHERE products_id = {$attr['pid']}
                AND products_stock_attributes = '$stockAttributes' LIMIT 1"
        );
        if ($r && ($row = tep_db_fetch_array($r))) {
            $cur = (int)$row['products_stock_quantity'];
            if ($cur <= 0 && $cur !== -900) {
                tep_db_query(
                    "UPDATE products_stock SET products_stock_quantity = -900
                      WHERE products_id = {$attr['pid']}
                        AND products_stock_attributes = '$stockAttributes'"
                );
                $mark900Attr++;
            }
        }
    }
    if ($mark900Attr > 0) {
        logMsg("[✓ -900 ATTR] $mark900Attr variantes fuera del feed Jobe marcadas no disponibles");
    }

    // Padres de variantes: TODOS sus EAN fuera del feed y sin stock en ninguna
    // variante ni en el padre → ocultar igual que los sueltos
    foreach ($pidTieneAttrs as $pid => $unused) {
        if (isset($pidSigueEnFeed[$pid])) continue;
        if (!isset($parentInfo[$pid]) || $parentInfo[$pid]['status'] !== 1 || $parentInfo[$pid]['qty'] > 0) continue;
        $r = tep_db_query("SELECT MAX(products_stock_quantity) AS m FROM products_stock WHERE products_id = $pid");
        $row = tep_db_fetch_array($r);
        if ($row && (int)$row['m'] > 0) continue;   // alguna variante con stock real puesto a mano
        tep_db_query(
            "UPDATE products SET products_quantity = -900, products_status = 2, products_last_modified = NOW()
              WHERE products_id = $pid AND products_status = 1 AND products_quantity <= 0"
        );
        tep_db_query("INSERT IGNORE INTO jobe_feed_descatalogados (products_id, products_model, date_added) VALUES ($pid, '" . tep_db_input($parentInfo[$pid]['model']) . "', NOW())");
        $hiddenByCron[$pid] = true;
        $parentInfo[$pid]['status'] = 2;
        $hiddenNew++;
        logMsg("[✓ OCULTADO] pid $pid ({$parentInfo[$pid]['model']}): todas sus variantes fuera del feed, sin stock → status 2 + marcador");
    }
}

/* ============================================================
 *  3c) Fallback para productos SIN EAN maestro (invisibles al matching por EAN
 *      de 2/3/3b, que solo alcanza product_ean y products_attributes_ean).
 *      SOLO toca products_quantity (jamás el status: eso es cosa de 3b, con
 *      evidencia EAN). Mismo guard anti-feed-truncado. Dos vías:
 *      - CON EANs de atributo: la vía attr ya mantiene products_stock, así que
 *        aquí solo se ALINEA products_quantity con el agregado de sus variantes
 *        en casos inequívocos (todas -900 → -900; mejor caso proveedor → -100).
 *        NO se usa el products_model: un model legacy podría contradecir
 *        variantes vivas del feed.
 *      - SIN ningún EAN (ni maestro ni de atributos): fallback por products_model
 *        contra productId del feed (model completo, o prefijo antes del primer
 *        guion; la talla del sufijo se casa con <size> vía jobeSizeKey). Fuera del
 *        feed o talla desaparecida → -900; en el feed → -100/-900 según stock.
 *      El stock real (>0) NUNCA se toca; los agregados ambiguos (0, -800, 2000,
 *      stock real de variante) se dejan en paz. Si hay fila exacta del feed,
 *      el producto no tiene atributos y el EAN está libre en TODA la BD, se
 *      backfillea product_ean para que el matching principal lo cubra en adelante.
 * ============================================================ */
$fbAgg = $fbModel = $fbBackfill = 0;

if (!$aborted && count($feedSeen) >= 800) {
    $qf = tep_db_query(
        "SELECT p.products_id, p.products_quantity, TRIM(p.products_model) AS model,
                (SELECT COUNT(*) FROM products_attributes pa
                  WHERE pa.products_id = p.products_id) AS n_attrs,
                (SELECT COUNT(*) FROM products_attributes pa2
                  WHERE pa2.products_id = p.products_id
                    AND pa2.products_attributes_ean IS NOT NULL
                    AND TRIM(pa2.products_attributes_ean) <> '') AS n_attr_eans,
                (SELECT MAX(ps.products_stock_quantity) FROM products_stock ps
                  WHERE ps.products_id = p.products_id) AS stock_agg
           FROM products p
          WHERE p.manufacturers_id = 257
            AND p.products_status IN (0,1)
            AND (p.product_ean IS NULL OR TRIM(p.product_ean) = '')"
    );

    $bfClaimed = [];  // EANs ya backfilleados en esta pasada (evita duplicar)

    while ($p = tep_db_fetch_array($qf)) {
        $pId     = (int)$p['products_id'];
        $qty     = (int)$p['products_quantity'];
        $model   = (string)$p['model'];
        $target  = null;
        $why     = '';
        $kind    = '';
        $feedRow = null;   // fila exacta del feed (habilita backfill)

        if ((int)$p['n_attr_eans'] > 0) {
            /* Variantes con EAN: alinear la qty base solo en casos inequívocos */
            $kind = 'AGG';
            $agg  = ($p['stock_agg'] === null) ? null : (int)$p['stock_agg'];
            if ($agg !== null && $agg <= -900) {
                $target = -900; $why = "todas las variantes a -900";
            } elseif ($agg !== null && $agg >= -150 && $agg <= -100) {
                $target = -100; $why = "mejor variante en proveedor ($agg)";
            }
        } elseif ($model !== '') {
            /* Sin ningún EAN: fallback por products_model */
            $kind = 'MODEL';
            $fPid = ''; $suffix = '';
            if (isset($feedModel[$model])) {
                $fPid = $model;
            } elseif (strpos($model, '-') !== false) {
                $pre = trim(substr($model, 0, strpos($model, '-')));
                if (isset($feedModel[$pre])) {
                    $fPid   = $pre;
                    $suffix = substr($model, strrpos($model, '-') + 1);
                }
            }
            if ($fPid === '') {
                $target = -900; $why = "model fuera del feed";
            } else {
                $frs = $feedModel[$fPid];
                $sk  = preg_replace('/[^0-9A-Za-z]/', '', $suffix);
                if ($sk !== '') {
                    foreach ($frs as $fr) {
                        if (strcasecmp(jobeSizeKey($fr['size']), $sk) === 0) { $feedRow = $fr; break; }
                    }
                    if ($feedRow !== null) {
                        $target = ($feedRow['stock'] > 0) ? -100 : -900;
                        $why    = "talla '{$feedRow['size']}' stock {$feedRow['stock']}";
                    } else {
                        $target = -900; $why = "talla '$sk' ya no está en el feed";
                    }
                } elseif (count($frs) === 1) {
                    $feedRow = $frs[0];
                    $target  = ($feedRow['stock'] > 0) ? -100 : -900;
                    $why     = "fila única stock {$feedRow['stock']}";
                } else {
                    $best = $frs[0]['stock'];
                    foreach ($frs as $fr) $best = max($best, $fr['stock']);
                    $target = ($best > 0) ? -100 : -900;
                    $why    = count($frs) . " tallas en feed, mejor stock $best";
                }
            }
        } else {
            continue; // sin EANs y sin model: no hay con qué matchear
        }

        if ($target !== null && $qty <= 0 && $qty !== $target) {
            tep_db_query(
                "UPDATE products SET products_quantity = $target
                  WHERE products_id = $pId
                    AND products_quantity <= 0 AND products_quantity <> $target"
            );
            logMsg("[✓ FALLBACK $kind] pid $pId '$model': $qty → $target ($why)");
            if ($kind === 'AGG') $fbAgg++; else $fbModel++;
        }

        /* Backfill de product_ean: fila exacta + sin atributos + EAN libre en toda la BD */
        if ($feedRow !== null && (int)$p['n_attrs'] === 0) {
            $eanRaw  = trim($feedRow['ean']);
            $eanNorm = normalizeEan($eanRaw);
            if ($eanNorm !== '' && !isset($bfClaimed[$eanNorm])
                && !isset($productsBase[$eanNorm]) && !isset($attrMap[$eanNorm])) {
                $chk = tep_db_query(
                    "SELECT (SELECT COUNT(*) FROM products
                              WHERE TRIM(product_ean) = '" . tep_db_input($eanRaw) . "')
                          + (SELECT COUNT(*) FROM products_attributes
                              WHERE TRIM(products_attributes_ean) = '" . tep_db_input($eanRaw) . "') AS n"
                );
                $chk = tep_db_fetch_array($chk);
                if ($chk && (int)$chk['n'] === 0) {
                    tep_db_query(
                        "UPDATE products SET product_ean = '" . tep_db_input($eanRaw) . "'
                          WHERE products_id = $pId
                            AND (product_ean IS NULL OR TRIM(product_ean) = '')"
                    );
                    $bfClaimed[$eanNorm] = true;
                    logMsg("[✓ BACKFILL EAN] pid $pId '$model' ← $eanRaw (size '{$feedRow['size']}')");
                    $fbBackfill++;
                }
            }
        }
    }
} else {
    logMsg("⚠️  Fallback sin-EAN (3c) omitido: mismo guard que el barrido de descatalogados");
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
logMsg("Marcados -900 (fuera de catálogo Jobe): $mark900Prod productos, $mark900Attr variantes");
logMsg("Ocultados (fuera de feed sin stock, status 1→2): $hiddenNew");
logMsg("Resucitados (reaparecen en feed con stock, status 2→1): $resur");
logMsg("Fallback sin-EAN (3c): $fbAgg qty base por agregado de variantes, $fbModel por products_model, $fbBackfill EANs backfilleados");
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
$body = EMAIL_BODY . "\n\nResumen: $updProd productos y $updAttr variantes actualizados; "
      . "$mark900Prod productos y $mark900Attr variantes marcados NO DISPONIBLES (-900, fuera del feed Jobe); "
      . "$hiddenNew productos OCULTADOS (fuera del feed sin stock, status 1→2, marcador jobe_feed_descatalogados); "
      . "$resur resucitados (reaparecen en el feed con stock, status 2→1); "
      . "$miss EANs del feed sin correspondencia en la web.\n"
      . "Fallback sin-EAN maestro (3c): $fbAgg qty base alineadas con sus variantes, "
      . "$fbModel por products_model, $fbBackfill EANs backfilleados.";
mail(NOTIFICATION_EMAIL, EMAIL_SUBJECT, $body, implode("\r\n", $hdrs));

logMsg("=== IMPORTACIÓN COMPLETADA ===");
?>