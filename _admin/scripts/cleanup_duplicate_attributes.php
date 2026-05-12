<?php
/**
 * cleanup_duplicate_attributes.php
 *
 * Resuelve los grupos donde (products_id, options_id, options_values_id)
 * tiene más de una fila en products_attributes.
 *
 * Clasificación automática:
 *   A. PURO     — perfil completo idéntico → DELETE los pa sobrantes (mantiene MIN(pa_id))
 *   B. EAN-solo — todo idéntico salvo EAN → DELETE el pa sin EAN (mantiene el de EAN poblado)
 *   C. REAL     — references distintas → CLONA el OV con sufijo " (<reference>)" y
 *                 reasigna cada pa secundario al nuevo OV; siembra su fila products_stock.
 *
 * Asume:
 *   - Single-option en el proyecto (sin claves stock combinadas).
 *   - Ningún pa duplicado referenciado en orders_products_attributes (verificado 2026-05-11).
 *   - Ninguna stock row asociada con qty!=0 ni cost>0 (verificado 2026-05-11).
 *
 * Uso:
 *   php cleanup_duplicate_attributes.php DRY    # plan, sin tocar BD
 *   php cleanup_duplicate_attributes.php        # aplica
 */

$confPath = '/home/francobordo/public_html/includes/configure.php';
$conf = file_get_contents($confPath);
preg_match("/'DB_SERVER',\s*'([^']+)'/", $conf, $m); $DB_HOST = $m[1] ?? 'localhost';
preg_match("/'DB_SERVER_USERNAME',\s*'([^']+)'/", $conf, $m); $DB_USER = $m[1];
preg_match("/'DB_SERVER_PASSWORD',\s*'([^']+)'/", $conf, $m); $DB_PASS = $m[1];
preg_match("/'DB_DATABASE',\s*'([^']+)'/", $conf, $m); $DB_NAME = $m[1];

$dryRun = false;
foreach (array_slice($argv, 1) as $a) {
    if (strtoupper($a) === 'DRY') $dryRun = true;
}

function logMsg($s) { echo '[' . date('H:i:s') . '] ' . $s . "\n"; }

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_error) { logMsg("ERROR DB: " . $mysqli->connect_error); exit(1); }
$mysqli->set_charset('utf8mb4');

logMsg("Modo: " . ($dryRun ? 'DRY-RUN' : 'EXECUTE'));

// 1) Pre-flight checks (recheck en runtime, no asumimos)
$check1 = $mysqli->query("
  SELECT COUNT(*) AS n
  FROM products_attributes pa
  JOIN (
    SELECT products_id, options_id, options_values_id
    FROM products_attributes
    GROUP BY products_id, options_id, options_values_id
    HAVING COUNT(*) > 1
  ) g USING (products_id, options_id, options_values_id)
  JOIN orders_products_attributes opa ON opa.NIDATRIB = pa.products_attributes_id
")->fetch_assoc()['n'];
if ((int)$check1 > 0) {
    logMsg("ABORT: $check1 pa de grupos duplicados aparecen en orders_products_attributes (esperado 0)");
    exit(1);
}

$check2 = $mysqli->query("
  SELECT COUNT(*) AS n
  FROM products_stock ps
  JOIN (
    SELECT products_id, options_id, options_values_id
    FROM products_attributes
    GROUP BY products_id, options_id, options_values_id
    HAVING COUNT(*) > 1
  ) g
    ON g.products_id = ps.products_id
    AND ps.products_stock_attributes = CONCAT(g.options_id,'-',g.options_values_id)
  WHERE ps.products_stock_quantity <> 0 OR ps.products_stock_cost > 0
")->fetch_assoc()['n'];
if ((int)$check2 > 0) {
    logMsg("ABORT: $check2 stock rows asociadas con qty!=0 o cost>0 (esperado 0)");
    exit(1);
}

logMsg("Pre-flight OK (0 ventas, 0 stock con dato).");

// 2) Cargar todos los grupos con detalle
$groups = []; // key = pid/oid/ovid → ['ov_name'=>..., 'rows'=>[pa...]]
$r = $mysqli->query("
  SELECT pa.products_id, pa.options_id, pa.options_values_id,
         pa.products_attributes_id, pa.reference, pa.reference_prov,
         pa.products_attributes_ean, pa.options_values_price, pa.price_prefix,
         pa.options_values_weight, pa.weight_prefix, pa.products_options_sort_order,
         pa.attributes_hide_from_groups
  FROM products_attributes pa
  JOIN (
    SELECT products_id, options_id, options_values_id
    FROM products_attributes
    GROUP BY products_id, options_id, options_values_id
    HAVING COUNT(*) > 1
  ) g USING (products_id, options_id, options_values_id)
  ORDER BY pa.products_id, pa.options_id, pa.options_values_id, pa.products_attributes_id
");
while ($row = $r->fetch_assoc()) {
    $k = $row['products_id'].'/'.$row['options_id'].'/'.$row['options_values_id'];
    if (!isset($groups[$k])) {
        $groups[$k] = [
            'pid'  => (int)$row['products_id'],
            'oid'  => (int)$row['options_id'],
            'ovid' => (int)$row['options_values_id'],
            'rows' => [],
        ];
    }
    $groups[$k]['rows'][] = $row;
}

// Adjuntar ov_name por idioma
foreach ($groups as $k => &$g) {
    $r = $mysqli->query("SELECT language_id, products_options_values_name AS nm FROM products_options_values WHERE products_options_values_id={$g['ovid']}");
    $g['ov_names'] = [];
    while ($row = $r->fetch_assoc()) $g['ov_names'][(int)$row['language_id']] = $row['nm'];
}
unset($g);

// 3) Clasificar y planear acciones
$stats = ['puros'=>0, 'ean_solo'=>0, 'real'=>0];
$plan = ['deletes' => [], 'clones' => []];

foreach ($groups as $g) {
    $rows = $g['rows'];
    $refs = array_unique(array_map(fn($r)=>$r['reference'] ?? '', $rows));
    $eans = array_unique(array_map(fn($r)=>$r['products_attributes_ean'] ?? '', $rows));
    $profiles = array_unique(array_map(fn($r)=>implode('|',[
        $r['reference']??'',
        $r['reference_prov']??'',
        $r['options_values_price'],
        $r['price_prefix'],
        $r['options_values_weight'],
    ]), $rows));

    if (count($refs)==1 && count($eans)==1 && count($profiles)==1) {
        // PURO — keeper = min pa_id, borrar los demás
        $stats['puros']++;
        $keeper = (int)$rows[0]['products_attributes_id'];
        for ($i=1; $i<count($rows); $i++) {
            $plan['deletes'][] = ['pa_id'=>(int)$rows[$i]['products_attributes_id'], 'reason'=>'puro', 'group'=>$g, 'keeper'=>$keeper];
        }
    } elseif (count($refs)==1 && count($profiles)==1 && count($eans)>1) {
        // EAN-solo — keeper = pa con EAN no vacío
        $stats['ean_solo']++;
        $keeper = null;
        foreach ($rows as $r) {
            if (!empty($r['products_attributes_ean'])) { $keeper = (int)$r['products_attributes_id']; break; }
        }
        if ($keeper === null) $keeper = (int)$rows[0]['products_attributes_id']; // fallback
        foreach ($rows as $r) {
            if ((int)$r['products_attributes_id'] !== $keeper) {
                $plan['deletes'][] = ['pa_id'=>(int)$r['products_attributes_id'], 'reason'=>'ean-solo', 'group'=>$g, 'keeper'=>$keeper];
            }
        }
    } else {
        // REAL — refs distintas → CLONA OV para cada pa salvo el keeper (min pa_id)
        $stats['real']++;
        $keeper = (int)$rows[0]['products_attributes_id'];
        for ($i=1; $i<count($rows); $i++) {
            $plan['clones'][] = ['pa'=>$rows[$i], 'group'=>$g, 'keeper'=>$keeper];
        }
    }
}

logMsg(sprintf("Clasificación: puros=%d  ean_solo=%d  real=%d   (total grupos=%d)",
    $stats['puros'], $stats['ean_solo'], $stats['real'], count($groups)));
logMsg(sprintf("Acciones planeadas: DELETE=%d  CLONE_OV+REASSIGN=%d",
    count($plan['deletes']), count($plan['clones'])));

// 4) Mostrar el plan en detalle (en DRY o como cabecera del EXECUTE)
echo "\n--- DELETEs ---\n";
foreach ($plan['deletes'] as $d) {
    $g = $d['group'];
    echo sprintf("  pid=%d ov=%d (%s) keeper=pa%d → DELETE pa=%d [%s]\n",
        $g['pid'], $g['ovid'], $g['ov_names'][1] ?? '?', $d['keeper'], $d['pa_id'], $d['reason']);
}

echo "\n--- CLONE_OV + REASSIGN ---\n";
foreach ($plan['clones'] as $c) {
    $g = $c['group'];
    $newName1 = ($g['ov_names'][1] ?? '') . ' (' . trim($c['pa']['reference']) . ')';
    $newName3 = ($g['ov_names'][3] ?? '') . ' (' . trim($c['pa']['reference']) . ')';
    echo sprintf("  pid=%d ov=%d \"%s\" keeper=pa%d → pa=%d (ref=%s) ⇒ new OV lang1=\"%s\"  lang3=\"%s\"\n",
        $g['pid'], $g['ovid'], $g['ov_names'][1] ?? '?', $c['keeper'],
        $c['pa']['products_attributes_id'], $c['pa']['reference'], $newName1, $newName3);
}

if ($dryRun) {
    logMsg("DRY-RUN: no se ha tocado nada.");
    exit(0);
}

// 5) EXECUTE
$mysqli->begin_transaction();
try {
    // 5a) DELETEs
    $deleted = 0;
    foreach ($plan['deletes'] as $d) {
        $paId = (int)$d['pa_id'];
        if (!$mysqli->query("DELETE FROM products_attributes WHERE products_attributes_id=$paId")) {
            throw new Exception("DELETE pa=$paId falló: " . $mysqli->error);
        }
        if ($mysqli->affected_rows !== 1) {
            throw new Exception("DELETE pa=$paId no afectó 1 fila (afectó ".$mysqli->affected_rows.")");
        }
        $deleted++;
    }
    logMsg("DELETEs aplicados: $deleted");

    // 5b) CLONE_OV
    $cloned = 0;
    foreach ($plan['clones'] as $c) {
        $g = $c['group'];
        $pa = $c['pa'];
        $oid = (int)$g['oid'];
        $ref = $mysqli->real_escape_string(trim($pa['reference']));

        // products_options_values_id NO es AUTO_INCREMENT: calcular MAX+1 (mismo patrón que los importadores)
        $row = $mysqli->query("SELECT IFNULL(MAX(products_options_values_id), 0) + 1 AS nid FROM products_options_values")->fetch_assoc();
        $newOvId = (int) $row['nid'];

        // Sembrar OV en cada idioma que tenga el viejo (1 y 3); CCODIVAL es NOT NULL default ''
        foreach ($g['ov_names'] as $lang => $oldName) {
            $newName = $mysqli->real_escape_string(trim($oldName) . ' (' . trim($pa['reference']) . ')');
            if (!$mysqli->query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($newOvId, $lang, '$newName', '')")) {
                throw new Exception("INSERT OV id=$newOvId lang=$lang falló: " . $mysqli->error);
            }
        }

        // Link al options_id
        if (!$mysqli->query("INSERT INTO products_options_values_to_products_options (products_options_id, products_options_values_id) VALUES ($oid, $newOvId)")) {
            throw new Exception("INSERT link OV→options falló: " . $mysqli->error);
        }

        // Reasignar el pa al nuevo OV
        $paId = (int)$pa['products_attributes_id'];
        if (!$mysqli->query("UPDATE products_attributes SET options_values_id=$newOvId, attributes_last_modified=NOW() WHERE products_attributes_id=$paId")) {
            throw new Exception("UPDATE pa=$paId falló: " . $mysqli->error);
        }

        // Sembrar stock row para el nuevo par
        $key = "$oid-$newOvId";
        if (!$mysqli->query("INSERT INTO products_stock (products_id, products_stock_attributes, products_stock_quantity, products_stock_cost) VALUES ({$g['pid']}, '$key', 0, 0.0000)")) {
            throw new Exception("INSERT products_stock para $key falló: " . $mysqli->error);
        }

        $cloned++;
    }
    logMsg("Clones aplicados: $cloned");

    // 5c) Verificación post-acción
    $remaining = (int)$mysqli->query("
      SELECT COUNT(*) AS n FROM (
        SELECT 1 FROM products_attributes
        GROUP BY products_id, options_id, options_values_id
        HAVING COUNT(*) > 1
      ) t
    ")->fetch_assoc()['n'];
    if ($remaining !== 0) {
        throw new Exception("Sanity falla: tras la limpieza quedan $remaining grupos duplicados (esperado 0)");
    }

    $mysqli->commit();
    logMsg("COMMIT OK. Grupos duplicados restantes: 0");
} catch (Throwable $e) {
    $mysqli->rollback();
    logMsg("ROLLBACK: " . $e->getMessage());
    exit(1);
}
