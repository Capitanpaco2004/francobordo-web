<?php
/**
 * cron_stock_ram.php — actualizador de stock RAM Mounts (CLI, para cron)
 *
 * Fuente: /descargas/gamp/MSI.csv (lo sube RAM por FTP, ~04:09).
 * Reglas (usuario 2026-06-12):
 *   - Nuestro stock > 0            → NO se toca (stock real propio).
 *   - Nuestro stock <= 0 y RAM SÍ tiene stock → products_quantity = -100 (en proveedor).
 *   - Nuestro stock <= 0 y RAM NO tiene stock → products_quantity = -800 (bajo pedido).
 *   - Producto sin fila en el CSV y nuestro stock <= 0 → products_status = 2 (descatalogado)
 *     y se anota en la tabla ram_csv_descatalogados (regla usuario 2026-06-12).
 *   - Si un producto anotado REAPARECE en el CSV → products_status = 1 (resucita) y se desanota.
 *     Solo se reactivan los que desactivó ESTE cron (la tabla marcador evita activar
 *     altas recién importadas con status=2 pendientes de revisión).
 *   - status=0 (legacy) NUNCA se toca; solo se descataloga desde status=1.
 * Toca products_quantity y products_status. Misma lógica que _admin/Actualizador_stock_ram.php (web).
 *
 * Motivo del cron de las 21:45: la tarea programada de QFac de las 21:00 actualiza
 * el stock y PISA los sentinels de RAM → hay que re-aplicarlos después.
 *
 * Uso:
 *   php cron_stock_ram.php            # DRY: reporta sin tocar
 *   php cron_stock_ram.php --apply    # ejecuta (lo usa el cron)
 */

$apply = in_array('--apply', $argv ?? [], true);

const CSV_PATH = '/home/francobordo/public_html/descargas/gamp/MSI.csv';
const MFG_NAME = 'RAM Mounts';
const STOCK_PROVEEDOR  = -100;
const STOCK_BAJOPEDIDO = -800;

function out($msg) { echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n"; }

function ean13Checksum($p) { if (strlen($p) !== 12 || !ctype_digit($p)) return -1; $s = 0; for ($i = 0; $i < 12; $i++) { $d = (int) $p[$i]; $s += ($i % 2 === 0) ? $d : $d * 3; } return (10 - ($s % 10)) % 10; }
function isValidEan13($e) { $e = trim((string) $e); if (strlen($e) !== 13 || !ctype_digit($e)) return false; return ean13Checksum(substr($e, 0, 12)) === (int) $e[12]; }
function ramUpcToEan13($raw) { $d = preg_replace('/\D/', '', (string) $raw); if ($d === '') return ''; if (strlen($d) === 11) $d = '0' . $d; if (strlen($d) === 12) { $e = '0' . $d; return isValidEan13($e) ? $e : ''; } if (strlen($d) === 13) return isValidEan13($d) ? $d : ''; return ''; }

// ── conexión BD (configure.php por regex — application_top rompe en CLI) ──
$conf = file_get_contents('/home/francobordo/public_html/includes/configure.php');
preg_match("/'DB_SERVER',\s*'([^']+)'/", $conf, $m);          $DB_HOST = $m[1] ?? 'localhost';
preg_match("/'DB_SERVER_USERNAME',\s*'([^']+)'/", $conf, $m); $DB_USER = $m[1] ?? '';
preg_match("/'DB_SERVER_PASSWORD',\s*'([^']+)'/", $conf, $m); $DB_PASS = $m[1] ?? '';
preg_match("/'DB_DATABASE',\s*'([^']+)'/", $conf, $m);        $DB_NAME = $m[1] ?? '';
$db = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($db->connect_error) { out('ERROR BD: ' . $db->connect_error); exit(1); }
$db->set_charset('utf8');

// ── CSV ──
if (!file_exists(CSV_PATH)) { out('ERROR: no existe ' . CSV_PATH); exit(1); }
$age = time() - filemtime(CSV_PATH);
if ($age > 86400 * 3) out('AVISO: el CSV tiene ' . round($age / 86400, 1) . ' días (¿FTP de RAM parado?)');
$fh = fopen(CSV_PATH, 'r');
if (!$fh) { out('ERROR: no se pudo abrir ' . CSV_PATH); exit(1); }
$bySku = []; $byEan = [];
while (($row = fgetcsv($fh, 0, ';', '"', '\\')) !== false) {
    $sku = strtoupper(trim((string) ($row[1] ?? '')));
    if ($sku === '' || $sku === 'COD ARTICULO') continue;
    if (!ctype_digit(trim((string) ($row[0] ?? '')))) continue;
    $stock = (int) str_replace(',', '.', trim((string) ($row[6] ?? '0')));
    if (!isset($bySku[$sku]) || $stock > $bySku[$sku]) $bySku[$sku] = $stock;
    $ean = ramUpcToEan13($row[3] ?? '');
    if ($ean !== '' && (!isset($byEan[$ean]) || $stock > $byEan[$ean])) $byEan[$ean] = $stock;
}
fclose($fh);
if (empty($bySku)) { out('ERROR: CSV vacío o ilegible'); exit(1); }

// ── tabla marcador de descatalogados por este cron ──
$db->query("CREATE TABLE IF NOT EXISTS ram_csv_descatalogados (
    products_id INT NOT NULL PRIMARY KEY,
    products_model VARCHAR(96) NOT NULL DEFAULT '',
    date_added DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8");
$marker = [];
$r = $db->query("SELECT products_id FROM ram_csv_descatalogados");
if ($r) while ($row = $r->fetch_assoc()) $marker[(int) $row['products_id']] = true;

// ── productos RAM ──
$r = $db->query("SELECT manufacturers_id FROM manufacturers WHERE manufacturers_name='" . $db->real_escape_string(MFG_NAME) . "' LIMIT 1");
$mfgId = ($r && ($row = $r->fetch_assoc())) ? (int) $row['manufacturers_id'] : 0;
$f = $mfgId > 0 ? "(manufacturers_id = $mfgId OR products_import_origin LIKE 'ram%')" : "(products_import_origin LIKE 'ram%')";
$res = $db->query("SELECT products_id, products_model, product_ean, products_quantity, products_status FROM products WHERE $f");
if (!$res) { out('ERROR query productos: ' . $db->error); exit(1); }

$nProds = 0; $ownStock = 0; $unchanged = 0; $noMatch = 0; $toProv = []; $toBp = [];
$toDeact = []; $toResur = []; $markerStale = [];
while ($p = $res->fetch_assoc()) {
    $nProds++;
    $pid = (int) $p['products_id'];
    $qty = (int) $p['products_quantity'];
    $status = (int) $p['products_status'];
    $model = strtoupper(trim((string) $p['products_model']));
    $ramStock = $bySku[$model] ?? null;
    if ($ramStock === null) { $ean = trim((string) $p['product_ean']); if ($ean !== '') $ramStock = $byEan[$ean] ?? null; }
    $inCsv = ($ramStock !== null);

    // resurrección: lo desactivó este cron y ha reaparecido en el CSV → status 2→1
    if (isset($marker[$pid])) {
        if ($status !== 2) $markerStale[] = $pid;                 // alguien lo reactivó/tocó a mano → desanotar
        elseif ($inCsv) $toResur[] = ['pid' => $pid, 'model' => $model];
    }
    // descatalogar: sin fila en CSV + sin stock propio + activo (solo desde status=1; el 0 legacy NUNCA)
    if (!$inCsv && $qty <= 0 && $status === 1 && !isset($marker[$pid])) {
        $toDeact[] = ['pid' => $pid, 'model' => $model];
    }

    // stock
    if ($qty > 0) { $ownStock++; continue; }
    if (!$inCsv) { $noMatch++; continue; }
    $target = $ramStock > 0 ? STOCK_PROVEEDOR : STOCK_BAJOPEDIDO;
    if ($qty === $target) { $unchanged++; continue; }
    if ($target === STOCK_PROVEEDOR) $toProv[] = $pid; else $toBp[] = $pid;
}

$mode = $apply ? 'APPLY' : 'DRY';
out("[$mode] CSV: " . count($bySku) . " SKUs | BD RAM: $nProds productos (mfg=$mfgId) | marcador: " . count($marker));
out("[$mode] Stock: " . count($toProv) . " → " . STOCK_PROVEEDOR . " (en proveedor), " . count($toBp) . " → " . STOCK_BAJOPEDIDO . " (bajo pedido)");
out("[$mode] Intocables stock>0: $ownStock | ya correctos: $unchanged | sin fila CSV: $noMatch");
out("[$mode] Status: descatalogar (sin CSV+sin stock, 1→2): " . count($toDeact) . " | resucitar (reaparece en CSV, 2→1): " . count($toResur) . " | marcador obsoleto: " . count($markerStale));
foreach (array_slice($toDeact, 0, 20) as $d) out("  DESCATALOGAR pid={$d['pid']} {$d['model']}");
foreach (array_slice($toResur, 0, 20) as $d) out("  RESUCITAR   pid={$d['pid']} {$d['model']}");

$total = count($toProv) + count($toBp) + count($toDeact) + count($toResur) + count($markerStale);
if ($apply && $total > 0) {
    $db->begin_transaction();
    try {
        // guard products_quantity <= 0: nunca pisar stock real que haya aparecido entre el plan y el update
        foreach ([STOCK_PROVEEDOR => $toProv, STOCK_BAJOPEDIDO => $toBp] as $target => $ids) {
            foreach (array_chunk($ids, 500) as $chunk) {
                if (!$db->query("UPDATE products SET products_quantity = " . (int) $target . ", products_last_modified = NOW() WHERE products_id IN (" . implode(',', $chunk) . ") AND products_quantity <= 0"))
                    throw new Exception($db->error);
            }
        }
        // descatalogar 1→2 + anotar en marcador (guards re-verifican estado)
        foreach ($toDeact as $d) {
            if (!$db->query("UPDATE products SET products_status = 2, products_last_modified = NOW() WHERE products_id = {$d['pid']} AND products_status = 1 AND products_quantity <= 0"))
                throw new Exception($db->error);
            if ($db->affected_rows > 0) {
                if (!$db->query("INSERT IGNORE INTO ram_csv_descatalogados (products_id, products_model, date_added) VALUES ({$d['pid']}, '" . $db->real_escape_string($d['model']) . "', NOW())"))
                    throw new Exception($db->error);
            }
        }
        // resucitar 2→1 + desanotar
        foreach ($toResur as $d) {
            if (!$db->query("UPDATE products SET products_status = 1, products_last_modified = NOW() WHERE products_id = {$d['pid']} AND products_status = 2"))
                throw new Exception($db->error);
            if (!$db->query("DELETE FROM ram_csv_descatalogados WHERE products_id = {$d['pid']}"))
                throw new Exception($db->error);
        }
        // limpiar marcadores de productos tocados a mano (ya no están en status=2)
        if (!empty($markerStale)) {
            if (!$db->query("DELETE FROM ram_csv_descatalogados WHERE products_id IN (" . implode(',', array_map('intval', $markerStale)) . ")"))
                throw new Exception($db->error);
        }
        $db->commit();
        out("[APPLY] OK: stock " . (count($toProv) + count($toBp)) . " | descatalogados " . count($toDeact) . " | resucitados " . count($toResur) . " | marcadores limpiados " . count($markerStale) . ".");
    } catch (Throwable $e) {
        $db->rollback();
        out("[APPLY] ERROR: " . $e->getMessage() . " — ROLLBACK");
        exit(1);
    }
} elseif (!$apply) {
    out("[DRY] No se ha tocado nada (usa --apply para ejecutar).");
}
exit(0);
