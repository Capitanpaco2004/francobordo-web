<?php
/**
 * osculati_crosslink_refprov.php — CLI
 *
 * Cross-link de productos legacy ya existentes con el OrderCode Osculati,
 * usando colisión EAN como criterio de match.
 *
 * Recorre el feed Osculati (ItemPrice4Web.txt) y para cada OrderCode standalone
 * cuyo EAN ya está en BD bajo OTRO manufacturer/origin, escribe el OrderCode
 * Osculati en `reference_prov` del producto (o del atributo si el EAN está en
 * un products_attributes_ean) — siempre que el `reference_prov` esté vacío.
 *
 * - Si `reference_prov` ya tiene el OrderCode Osculati → no toca, registra como OK.
 * - Si tiene OTRO valor → no toca, registra como CONFLICTO para revisión manual.
 *
 * Asume que ItemPrice4Web.txt y Code2Ser.txt están descargados en
 * /home/francobordo/public_html/_admin/import-osculati/ (los descarga
 * el último dry-run de import-osculati-altas.php).
 *
 * Modos:
 *   php osculati_crosslink_refprov.php          → DRY-RUN (lista lo que haría)
 *   php osculati_crosslink_refprov.php APPLY    → aplica los UPDATE
 *
 * Backup: la columna afectada es solo `reference_prov`. Antes de APPLY
 * se hace un dump CSV con los valores previos en /tmp/osc_crosslink_backup_<stamp>.csv.
 */
$apply = in_array('APPLY', $argv ?? [], true);

require '/home/francobordo/public_html/includes/configure.php';
$m = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($m->connect_error) { fwrite(STDERR, "DB ERROR: " . $m->connect_error . PHP_EOL); exit(1); }
$m->set_charset('utf8');

$TMP = '/home/francobordo/public_html/_admin/import-osculati/';
$itemFile = $TMP . 'ItemPrice4Web.txt';
$code2ser = $TMP . 'Code2Ser.txt';
foreach ([$itemFile, $code2ser] as $f) {
    if (!file_exists($f)) { fwrite(STDERR, "Falta $f — lanza primero un dry-run del importador Osculati para descargarlo.\n"); exit(1); }
}

function readUtf16($path) {
    $raw = file_get_contents($path);
    $u8  = mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE');
    $u8  = ltrim($u8, "\xEF\xBB\xBF\xFF\xFE");
    return preg_split("/\r\n|\r|\n/", $u8);
}

$OSCULATI_MFG_ID = 259;
echo "Modo: " . ($apply ? "APPLY (modificará BD)" : "DRY-RUN") . "\n\n";

// 1) Cargar Osculati feed: byBase[bc][] = {order, ean}
$rows = readUtf16($itemFile);
$byBase = [];
foreach ($rows as $line) {
    if ($line === '') continue;
    $r = explode("\t", $line);
    if (count($r) < 14) continue;
    $bc = trim($r[1]);
    if ($bc === '') continue;
    $byBase[$bc][] = ['order' => trim($r[0]), 'ean' => trim($r[6])];
}

// 2) Series multi-BC (las saltamos — solo cross-linkamos standalone)
$lines2 = readUtf16($code2ser);
$basesBySerie = [];
foreach ($lines2 as $line) {
    if ($line === '') continue;
    $r = explode("\t", $line);
    if (count($r) < 2) continue;
    $bc  = trim($r[0]);
    $sid = (int) trim($r[1]);
    if ($bc === '' || $sid <= 0) continue;
    $basesBySerie[$sid][$bc] = true;
}
$bcInSerieMulti = [];
foreach ($basesBySerie as $sid => $bcs) {
    if (count($bcs) > 1) foreach (array_keys($bcs) as $bc) $bcInSerieMulti[$bc] = true;
}

// 3) BC existentes en BD scoped Osculati (excluimos: ya están)
$existingBc = [];
$r = $m->query("SELECT LOWER(products_model) c FROM products WHERE products_model<>'' AND (manufacturers_id=$OSCULATI_MFG_ID OR products_import_origin LIKE 'osculati%')");
while ($x = $r->fetch_assoc()) $existingBc[$x['c']] = true;
$r = $m->query("SELECT LOWER(reference_prov) c FROM products WHERE reference_prov<>'' AND (manufacturers_id=$OSCULATI_MFG_ID OR products_import_origin LIKE 'osculati%')");
while ($x = $r->fetch_assoc()) $existingBc[$x['c']] = true;
$r = $m->query("SELECT LOWER(pa.reference) c FROM products_attributes pa INNER JOIN products p ON p.products_id=pa.products_id WHERE pa.reference<>'' AND (p.manufacturers_id=$OSCULATI_MFG_ID OR p.products_import_origin LIKE 'osculati%')");
while ($x = $r->fetch_assoc()) $existingBc[$x['c']] = true;
$r = $m->query("SELECT LOWER(pa.reference_prov) c FROM products_attributes pa INNER JOIN products p ON p.products_id=pa.products_id WHERE pa.reference_prov<>'' AND (p.manufacturers_id=$OSCULATI_MFG_ID OR p.products_import_origin LIKE 'osculati%')");
while ($x = $r->fetch_assoc()) $existingBc[$x['c']] = true;

// 4) EAN → [matches con su reference_prov actual]
$eanMatch = []; // ean → {pid, src, pa_id?, rp, mfg_id, origin, model, status}
$r = $m->query("SELECT p.product_ean ean, p.products_id pid, NULL pa_id, p.reference_prov rp,
                       p.manufacturers_id mfg_id, p.products_import_origin origin,
                       p.products_model model, p.products_status status, 'products' src
                FROM products p
                WHERE p.product_ean IS NOT NULL AND p.product_ean<>'' AND LENGTH(p.product_ean)=13");
while ($x = $r->fetch_assoc()) if (!isset($eanMatch[$x['ean']])) $eanMatch[$x['ean']] = $x;
$r = $m->query("SELECT pa.products_attributes_ean ean, p.products_id pid, pa.products_attributes_id pa_id,
                       pa.reference_prov rp, p.manufacturers_id mfg_id, p.products_import_origin origin,
                       p.products_model model, p.products_status status, 'attributes' src
                FROM products_attributes pa INNER JOIN products p ON p.products_id=pa.products_id
                WHERE pa.products_attributes_ean IS NOT NULL AND pa.products_attributes_ean<>'' AND LENGTH(pa.products_attributes_ean)=13");
while ($x = $r->fetch_assoc()) if (!isset($eanMatch[$x['ean']])) $eanMatch[$x['ean']] = $x;

// 5) Recorrer feed: identificar standalone cuyo EAN matchea, clasificar y aplicar
$plan = [
    'will_fill'      => [],  // rp vacío → escribir OSC BC
    'already_linked' => [],  // rp == OSC BC ya → no-op
    'conflict'       => [],  // rp != OSC BC → skip, revisión manual
    'no_match'       => 0,   // EAN no existe (no procede)
    'skipped_series' => 0,
    'skipped_oscbc'  => 0,
    'no_ean'         => 0,
];

foreach ($byBase as $bc => $orders) {
    if (isset($bcInSerieMulti[$bc])) { $plan['skipped_series']++; continue; }
    if (isset($existingBc[strtolower($bc)])) { $plan['skipped_oscbc']++; continue; }
    $matchedThisBc = false;
    foreach ($orders as $it) {
        $ean = $it['ean'];
        if ($ean === '' || strlen($ean) !== 13) continue;
        if (!isset($eanMatch[$ean])) continue;
        $best = $eanMatch[$ean];
        // Filtro de Clase C: que NO sea ya Osculati (mfg!=259 y origin no osculati%)
        $isOsc = ((int) $best['mfg_id'] === $OSCULATI_MFG_ID) || (stripos((string) $best['origin'], 'osculati') === 0);
        if ($isOsc) { $matchedThisBc = true; break; } // ya es Osculati: no procede
        $rp = trim((string) $best['rp']);
        // Guardar el BaseCode limpio (sin sufijo #PZ/#CF/etc) — matchea con lo que humanos pusieron a mano
        $oscBc = preg_replace('/#.*$/', '', (string) $it['order']);
        $entry = ['bc' => $bc, 'osc_order' => $oscBc, 'ean' => $ean] + $best;
        if ($rp === '') {
            $plan['will_fill'][] = $entry;
        } elseif (strcasecmp($rp, $oscBc) === 0 || strcasecmp($rp, $bc) === 0) {
            $plan['already_linked'][] = $entry;
        } else {
            $plan['conflict'][] = $entry;
        }
        $matchedThisBc = true;
        break;
    }
    if (!$matchedThisBc) $plan['no_match']++;
}

echo "=== RESUMEN ===\n";
echo sprintf("  Will fill (reference_prov vacío)  : %d\n", count($plan['will_fill']));
echo sprintf("  Already linked (rp == OSC BC)     : %d\n", count($plan['already_linked']));
echo sprintf("  Conflicto (rp distinto al OSC BC) : %d\n", count($plan['conflict']));
echo sprintf("  Saltados (en serie multi-BC)      : %d\n", $plan['skipped_series']);
echo sprintf("  Saltados (ya en BD como Osculati) : %d\n", $plan['skipped_oscbc']);
echo "\n";

// Conflictos: listar siempre (necesitan revisión manual)
if (!empty($plan['conflict'])) {
    echo "=== CONFLICTOS (NO se tocan, revisión manual) ===\n";
    foreach ($plan['conflict'] as $e) {
        echo sprintf("  pid=%-7d src=%-10s rp_actual='%s' OSC_BC=%s model=%s ean=%s\n",
            $e['pid'], $e['src'], $e['rp'], $e['osc_order'], $e['model'], $e['ean']);
    }
    echo "\n";
}

// Will fill: muestra 10
echo "=== WILL FILL (muestra 10 de " . count($plan['will_fill']) . ") ===\n";
foreach (array_slice($plan['will_fill'], 0, 10) as $e) {
    echo sprintf("  pid=%-7d src=%-10s status=%s model=%-15s OSC=%-12s ean=%s\n",
        $e['pid'], $e['src'], $e['status'], $e['model'], $e['osc_order'], $e['ean']);
}
echo "\n";

if (!$apply) {
    // Dump CSV completo con nombres para revisión humana
    $planCsv = '/tmp/osc_crosslink_plan.csv';
    $fh = fopen($planCsv, 'w');
    fputcsv($fh, ['pid', 'src', 'status', 'mfg', 'model', 'name_es', 'rp_before', 'osc_bc_new', 'ean', 'admin_url']);
    // Lookup nombres y manufacturers de todos los pids del will_fill
    $pids = array_unique(array_map(fn($e) => (int) $e['pid'], $plan['will_fill']));
    if (!empty($pids)) {
        $pidIn = implode(',', $pids);
        $nameLookup = $mfgLookup = [];
        $r = $m->query("SELECT products_id pid, products_name name FROM products_description WHERE language_id=3 AND products_id IN ($pidIn)");
        while ($x = $r->fetch_assoc()) $nameLookup[(int) $x['pid']] = $x['name'];
        $r = $m->query("SELECT p.products_id pid, m.manufacturers_name mn FROM products p LEFT JOIN manufacturers m ON m.manufacturers_id=p.manufacturers_id WHERE p.products_id IN ($pidIn)");
        while ($x = $r->fetch_assoc()) $mfgLookup[(int) $x['pid']] = $x['mn'];
    }
    foreach ($plan['will_fill'] as $e) {
        $pid = (int) $e['pid'];
        fputcsv($fh, [
            $pid, $e['src'], $e['status'],
            $mfgLookup[$pid] ?? '',
            $e['model'],
            $nameLookup[$pid] ?? '',
            $e['rp'],
            $e['osc_order'],
            $e['ean'],
            "https://www.francobordo.com/_admin/categories.php?action=new_product&pID=$pid",
        ]);
    }
    fclose($fh);
    echo "Plan completo en CSV: $planCsv (" . count($plan['will_fill']) . " filas)\n";
    echo "DRY-RUN. Para aplicar: php " . basename(__FILE__) . " APPLY\n";
    exit(0);
}

// APPLY: backup + transacción
$stamp = date('Ymd-His');
$bk = "/tmp/osc_crosslink_backup_$stamp.csv";
$fh = fopen($bk, 'w');
fputcsv($fh, ['pid', 'src', 'pa_id', 'reference_prov_antes', 'reference_prov_despues', 'osc_order', 'ean']);
$ok = $fail = 0;
$m->begin_transaction();
try {
    foreach ($plan['will_fill'] as $e) {
        $newRp = $m->real_escape_string(substr($e['osc_order'], 0, 32));
        if ($e['src'] === 'products') {
            $sql = "UPDATE products SET reference_prov='$newRp' WHERE products_id=" . (int) $e['pid'] . " AND (reference_prov IS NULL OR reference_prov='')";
        } else {
            $sql = "UPDATE products_attributes SET reference_prov='$newRp' WHERE products_attributes_id=" . (int) $e['pa_id'] . " AND (reference_prov IS NULL OR reference_prov='')";
        }
        if ($m->query($sql) && $m->affected_rows > 0) {
            $ok++;
            fputcsv($fh, [$e['pid'], $e['src'], $e['pa_id'] ?? '', $e['rp'], $e['osc_order'], $e['osc_order'], $e['ean']]);
        } else {
            $fail++;
        }
    }
    $m->commit();
} catch (Throwable $t) {
    $m->rollback();
    fwrite(STDERR, "Error: " . $t->getMessage() . PHP_EOL);
    fclose($fh);
    exit(2);
}
fclose($fh);
echo "APPLY OK. Filas actualizadas: $ok | sin efecto: $fail\n";
echo "Backup CSV: $bk\n";
