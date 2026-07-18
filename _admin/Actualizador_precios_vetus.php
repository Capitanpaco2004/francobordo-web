<?php
require 'includes/application_top.php';

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);

const VETUS_DIR          = '/home/francobordo/public_html/import/Vetus/';
const VETUS_PDF          = VETUS_DIR . 'Tarifa general VETUS.pdf';
const VETUS_VAT_RATE     = 0.21;
const G1_GROUP_ID        = 1;
const PRICE_THRESHOLD    = 0.005;     // 0.5%
const MAX_CHANGE_PCT_DEF = 30;
const MANUFACTURER_ID    = 421;       // Vetus
// PER-TIPO discounts. 'K' deliberately absent: SKIP. Unknown/empty tipo => SKIP.
const DIST_DISC = ['A'=>38, 'B'=>42, 'C'=>46, 'EP'=>25, 'ST'=>25, 'SV'=>40, 'VE'=>38, 'YV'=>35];
const PROF_DISC = ['A'=>25, 'B'=>28, 'C'=>33, 'VE'=>20, 'SV'=>25, 'ST'=>15, 'YV'=>20, 'EP'=>18];

// ─────────────────────────── HELPERS ───────────────────────────

function roundToNickel($net) {
    $withIva = ((float) $net) * (1 + VETUS_VAT_RATE);
    $roundedWithIva = round($withIva * 20) / 20;
    return round($roundedWithIva / (1 + VETUS_VAT_RATE), 4);
}

function fmt4($n) { return number_format((float) $n, 4, '.', ''); }

function priceDeltaPct($oldP, $newP) {
    $ref = max(abs((float) $oldP), 0.01);
    return abs((float) $newP - (float) $oldP) / $ref;
}

function vetusEuNum($s) {
    $s = trim((string) $s);
    if ($s === '') return 0.0;
    $s = str_replace('.', '', $s);   // separador de miles
    $s = str_replace(',', '.', $s);  // separador decimal
    $s = preg_replace('/[^0-9.\-]/', '', $s);
    return is_numeric($s) ? (float) $s : 0.0;
}

function vetusLogMsg($msg) {
    $line = '[' . date('H:i:s') . '] ' . $msg . "\n";
    echo '<pre style="margin:0;padding:2px 8px;border-bottom:1px solid #eee;white-space:pre-wrap;overflow-wrap:break-word;word-break:break-word;font-family:monospace;font-size:12px;">' . htmlspecialchars($line) . '</pre>';
    @flush();
}

/**
 * Per-SKU pricing from parsed PDF row.
 * Returns null if Tipo is K, unsupported, or RRP invalid.
 * @return array{tipo:string,rrp:float,price:float,cost:float,g1:float}|null
 */
function vetusComputePricing(array $row) {
    $tipo = strtoupper(trim((string) ($row['tipo'] ?? '')));
    $rrp  = (float) ($row['rrp'] ?? 0);
    if ($tipo === '' || $rrp <= 0) return null;
    if (!isset(DIST_DISC[$tipo]) || !isset(PROF_DISC[$tipo])) return null;
    $distPct = DIST_DISC[$tipo];
    $profPct = PROF_DISC[$tipo];
    $price = roundToNickel($rrp);                            // PVP (NET)
    $cost  = round($rrp * (1 - $distPct / 100.0), 4);        // cost (NET, NOT snapped)
    $g1    = roundToNickel($rrp * (1 - $profPct / 100.0));   // G1 PVP (NET), per-tipo
    return ['tipo' => $tipo, 'rrp' => $rrp, 'price' => $price, 'cost' => $cost, 'g1' => $g1];
}

/**
 * Parse the Vetus 2026 tariff PDF and return SKU=>{tipo,rrp,page,description}.
 * Uses pdftotext -layout (poppler-utils). Spanish numbers (1.310,50 -> 1310.50).
 * Skips section headers, "Precio bajo demanda" rows, blank/non-matching lines.
 *
 * Layout (columns): Codigo | Pag.(optional) | Descripcion | Tipo | Precio€ (sin IVA)
 * SKU: first token, uppercase alphanumeric (allows / . , - + _), >=2 chars, must contain a letter.
 * Tipo: one of A, B, C, EP, ST, SV, VE, YV, V, K (tail token before price). Anything else SKIP.
 *
 * @return array<string,array{tipo:string,rrp:float,page:?int,description:string}>
 */
/**
 * Ejecuta pdftotext -layout y devuelve stdout. Tolerante a las restricciones del SAPI web:
 * intenta proc_open primero (habilitado en la mayoría de pools cPanel), luego shell_exec.
 * Devuelve null si NINGUNA función de ejecución externa está disponible.
 */
function vetusRunPdftotext(string $pdfPath): ?string
{
    if (function_exists('proc_open')) {
        $desc = [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']];
        $proc = @proc_open(['/usr/bin/pdftotext','-layout',$pdfPath,'-'], $desc, $pipes);
        if (is_resource($proc)) {
            fclose($pipes[0]);
            $txt = stream_get_contents($pipes[1]); fclose($pipes[1]);
            stream_get_contents($pipes[2]); fclose($pipes[2]);
            $rc = proc_close($proc);
            if ($rc === 0 && is_string($txt) && $txt !== '') return $txt;
        }
    }
    if (function_exists('shell_exec')) {
        $cmd = '/usr/bin/pdftotext -layout ' . escapeshellarg($pdfPath) . ' -';
        $txt = @shell_exec($cmd);
        if (is_string($txt) && $txt !== '') return $txt;
    }
    return null;
}

function vetusParsePdfTariff(string $pdfPath): array
{
    if (!is_readable($pdfPath)) {
        throw new RuntimeException("Vetus PDF not readable: $pdfPath");
    }

    // CACHE: si existe un JSON parseado más nuevo que el PDF, lo usamos directamente.
    // Esto vale por sí solo cuando el SAPI web no puede ejecutar binarios (cPanel suele
    // capar shell_exec/proc_open en el pool del admin). Se regenera con:
    //   php /home/francobordo/public_html/_admin/cli/vetus_regen_tariff_cache.php
    $cachePath = sys_get_temp_dir() . '/vetus_tariff_' . md5($pdfPath) . '.json';
    if (is_readable($cachePath) && filemtime($cachePath) >= filemtime($pdfPath)) {
        $data = json_decode((string) file_get_contents($cachePath), true);
        if (is_array($data) && !empty($data)) return $data;
    }

    $txt = vetusRunPdftotext($pdfPath);
    if ($txt === null) {
        throw new RuntimeException(
            "No se puede ejecutar pdftotext desde el SAPI web (shell_exec/proc_open desactivados) "
          . "y la cache JSON está obsoleta o no existe. Regenera la cache vía CLI: "
          . "ssh root@nic1 \"/opt/cpanel/ea-php84/root/usr/bin/php /home/francobordo/public_html/_admin/cli/vetus_regen_tariff_cache.php\""
        );
    }

    // Whitelist of Tipos we accept. K is returned so callers can SKIP it
    // explicitly (we have no distributor discount data for K).
    $validTipo = ['A','B','C','EP','ST','SV','VE','YV','V','K'];
    $validSet  = array_flip($validTipo);

    $out = [];

    foreach (explode("\n", $txt) as $rawLine) {
        // Strip form-feed (page break) and other ASCII controls; trim CR/NL.
        // Without this, the first row of each PDF page starts with \f and the
        // SKU regex fails — costs ~70 rows otherwise.
        $line = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]+/', '', $rawLine);
        $line = rtrim($line, "\r\n");
        if ($line === '' || trim($line) === '') {
            continue;
        }

        // Tokenize on >=2 spaces (preserve descriptions containing single spaces).
        $cols = preg_split('/\s{2,}/', trim($line));
        if (count($cols) < 3) {
            continue;
        }

        // First column = SKU candidate. Uppercase alnum plus / . , - + _
        // (handles DTCAN7,5M, SA25/+, FTR140/13, BPCAB1HF, etc.).
        // Must contain a letter; reject pure-numeric or "Upgrade"-style text.
        $sku = $cols[0];
        if (!preg_match('/^[A-Z0-9][A-Z0-9\/.,\-+_]{1,}$/', $sku)) {
            continue;
        }
        if (!preg_match('/[A-Z]/', $sku)) {
            continue;
        }

        // Last column = price; penultimate = Tipo.
        $last = end($cols);
        $tipo = prev($cols);
        if ($tipo === false || $last === false) {
            continue;
        }

        // Tipo must be in the whitelist (strict). This naturally drops
        // "Precio bajo demanda" rows since the price column is non-numeric.
        if (!isset($validSet[$tipo])) {
            continue;
        }

        // Parse Spanish price "1.310,50" / "47,95" / "1310,50".
        $priceRaw = trim($last);
        if (!preg_match('/^[€\s]*([0-9]{1,3}(?:\.[0-9]{3})*|[0-9]+)(?:,([0-9]{1,2}))?$/u', $priceRaw, $pm)) {
            // Detectar "Precio bajo demanda" / "A consultar" para reportarlo bien
            // (sin esto se pierde el SKU y el caller lo confunde con "sin SKU en PDF").
            if (preg_match('/(bajo\s+demanda|a\s+consultar|consultar)/iu', $priceRaw)) {
                if (!isset($out[$sku])) {
                    $out[$sku] = ['tipo'=>$tipo, 'rrp'=>0.0, 'page'=>null, 'description'=>'', 'price_on_demand'=>true];
                }
            }
            continue;
        }
        $intPart = str_replace('.', '', $pm[1]);
        $decPart = $pm[2] ?? '00';
        $rrp = (float)($intPart . '.' . $decPart);
        if ($rrp <= 0.0) {
            continue;
        }

        // Optional page number: if the second column is a pure integer
        // it's the Pag. column (PDF only prints page once per page section).
        $page = null;
        if (isset($cols[1]) && preg_match('/^[0-9]{1,4}$/', $cols[1])) {
            $page = (int)$cols[1];
            $descCols = array_slice($cols, 2, count($cols) - 4);
        } else {
            $descCols = array_slice($cols, 1, count($cols) - 3);
        }
        $description = trim(implode(' ', $descCols));
        $description = preg_replace('/\s+/u', ' ', $description);

        // De-dup: keep first occurrence. The PDF reprints rows on continuation
        // pages (237 SKUs were verified to have identical tipo+price across reprints).
        if (isset($out[$sku])) {
            continue;
        }

        $out[$sku] = [
            'tipo'        => $tipo,
            'rrp'         => $rrp,
            'page'        => $page,
            'description' => $description,
        ];
    }

    // Persist cache para próximos PLAN sin necesidad de ejecutar pdftotext.
    @file_put_contents($cachePath, json_encode($out, JSON_UNESCAPED_UNICODE));
    return $out;
}

// ─────────────────────────── INPUT / GATES ───────────────────────────

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$max    = isset($_POST['max']) ? (int) $_POST['max'] : (isset($_GET['max']) ? (int) $_GET['max'] : 0);
// SEGURIDAD: ejecutar requiere POST. Una URL GET tipo ?action=execute&confirm_execute=1
// (bookmark/historial/CSRF/preload) NUNCA debe disparar writes. PLAN sí se permite por GET (idempotente).
$isPost      = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST');
$confirmExec = $isPost && isset($_POST['confirm_execute']);
$dryRun = !($action === 'execute' && $confirmExec && $isPost);
$applyExtremes = isset($_POST['apply_extremes']) || isset($_GET['apply_extremes']);
$onlyExtremes  = isset($_POST['only_extremes']) || isset($_GET['only_extremes']);
$maxChangePct  = isset($_POST['max_change_pct']) ? (float) $_POST['max_change_pct'] : (isset($_GET['max_change_pct']) ? (float) $_GET['max_change_pct'] : MAX_CHANGE_PCT_DEF);
if ($maxChangePct < 0) $maxChangePct = 0;
$maxChangeRatio = $maxChangePct / 100.0;
$codesFilterRaw = $_POST['codes_filter'] ?? $_GET['codes_filter'] ?? '';
$codesFilterSet = [];
if (trim((string) $codesFilterRaw) !== '') {
    $parts = preg_split('/[\s,;]+/', (string) $codesFilterRaw);
    foreach ($parts as $p) { $p = strtolower(trim($p)); if ($p !== '') $codesFilterSet[$p] = 1; }
}
$isAction = ($action === 'plan' || $action === 'execute');

if ($isAction) {
    @header('X-Accel-Buffering: no');
    @header('Content-Type: text/html; charset=utf-8');
    while (ob_get_level() > 0) @ob_end_flush();
    @ob_implicit_flush(true);
    if (session_status() === PHP_SESSION_ACTIVE) @session_write_close();
}
?>
<?php require THEME . 'html/header.php'; ?>
<table style="width:100%;"><tr><td><div style="padding:20px;">
<?php if ($isAction): ?>
    <h2>Actualizador precios Vetus — <?php echo $dryRun ? 'PLAN (dry-run, sin cambios)' : 'EJECUCIÓN REAL'; ?></h2>
    <p><a href="<?php echo tep_href_link('Actualizador_precios_vetus.php'); ?>" class="xbutton small hv9">← Volver</a></p>
    <div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n";
@flush();

vetusLogMsg("Modo: " . ($dryRun ? "PLAN (dry-run)" : "EXECUTE")
    . " | tope cambio=" . ($applyExtremes ? "OFF (aplicando también extremos)" : ("|Δ| ≤ " . rtrim(rtrim(number_format($maxChangePct, 1, '.', ''), '0'), '.') . "%"))
    . ($max > 0 ? " | max=$max cambios" : "")
    . (!empty($codesFilterSet) ? " | filtro SKUs=" . count($codesFilterSet) : ""));

if (!is_readable(VETUS_PDF)) { vetusLogMsg("ERROR: PDF no legible: " . VETUS_PDF); goto end_action; }
vetusLogMsg("PDF: " . basename(VETUS_PDF) . " (" . round(filesize(VETUS_PDF)/1024) . " KB, mtime " . date('Y-m-d H:i', filemtime(VETUS_PDF)) . ")");

vetusLogMsg("Parseando PDF con pdftotext -layout…");
try {
    $parsed = vetusParsePdfTariff(VETUS_PDF);
} catch (Throwable $e) {
    vetusLogMsg("ERROR parseando PDF: " . $e->getMessage());
    goto end_action;
}
vetusLogMsg("SKUs parseados del PDF: " . count($parsed));
// Sanity floor: el PDF típico tiene ~3.600 SKUs. Si parsea <100, está roto/corrupto o pdftotext fallo silente.
if (count($parsed) < 100) {
    vetusLogMsg("ERROR: parseo anormalmente bajo (" . count($parsed) . " < 100). Abortando para evitar reportar 800 'sin match' espurios.");
    goto end_action;
}

// Index by lowercased SKU. Track duplicates (parser already de-dups by first; we log anyway).
$bySku = [];
foreach ($parsed as $sku => $row) {
    $key = strtolower(trim((string) $sku));
    if ($key === '') continue;
    if (isset($bySku[$key])) {
        vetusLogMsg("WARN SKU duplicado en PDF: $sku");
    }
    $bySku[$key] = $row;
}
vetusLogMsg("SKUs en índice (lowercased): " . count($bySku));

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_error) { vetusLogMsg("ERROR DB: " . $mysqli->connect_error); goto end_action; }
$mysqli->set_charset('utf8');

vetusLogMsg("Leyendo productos Vetus en BD (manufacturers_id=" . MANUFACTURER_ID . ")…");
$prods = [];
$r = $mysqli->query("SELECT p.products_id, p.products_model, p.reference_prov, p.product_ean, p.products_price, p.products_cost FROM products p WHERE p.manufacturers_id = " . MANUFACTURER_ID);
if (!$r) { vetusLogMsg("ERROR SELECT products: " . $mysqli->error); goto end_action; }
while ($row = $r->fetch_assoc()) $prods[(int) $row['products_id']] = $row;
vetusLogMsg("Productos Vetus en BD: " . count($prods));
if (empty($prods)) { vetusLogMsg("Nada que hacer."); goto end_action; }

$ids = implode(',', array_keys($prods));

// Nombre del producto (ES, lang 3) para mostrar en el plan.
$names = [];
$r = $mysqli->query("SELECT products_id, products_name FROM products_description WHERE language_id=3 AND products_id IN ($ids)");
if (!$r) { vetusLogMsg("ERROR SELECT products_description: " . $mysqli->error); goto end_action; }
while ($row = $r->fetch_assoc()) $names[(int) $row['products_id']] = $row['products_name'];
$nm = function ($pid) use (&$names) { return mb_substr((string)($names[$pid] ?? ''), 0, 45, 'UTF-8'); };

$g1Cur = [];
$r = $mysqli->query("SELECT products_id, customers_group_price FROM products_groups WHERE customers_group_id=" . G1_GROUP_ID . " AND products_id IN ($ids)");
if (!$r) { vetusLogMsg("ERROR SELECT products_groups: " . $mysqli->error); goto end_action; }
while ($row = $r->fetch_assoc()) $g1Cur[(int) $row['products_id']] = (float) $row['customers_group_price'];

$attrsByProd = [];
// ORDER BY garantiza orden determinista; sin esto, el "padre = más barata" puede flip-flop entre runs si hay empate.
$r = $mysqli->query("SELECT products_attributes_id, products_id, options_values_id, reference, reference_prov, products_attributes_ean, options_values_price, price_prefix FROM products_attributes WHERE products_id IN ($ids) ORDER BY products_id, products_attributes_id");
if (!$r) { vetusLogMsg("ERROR SELECT products_attributes: " . $mysqli->error); goto end_action; }
while ($row = $r->fetch_assoc()) $attrsByProd[(int) $row['products_id']][] = $row;

$paIds = [];
foreach ($attrsByProd as $arr) foreach ($arr as $a) $paIds[] = (int) $a['products_attributes_id'];
$g1AttrCur = [];
if (!empty($paIds)) {
    $paIn = implode(',', $paIds);
    $r = $mysqli->query("SELECT products_attributes_id, options_values_price, price_prefix FROM products_attributes_groups WHERE customers_group_id=" . G1_GROUP_ID . " AND products_attributes_id IN ($paIn)");
    if (!$r) { vetusLogMsg("ERROR SELECT products_attributes_groups: " . $mysqli->error); goto end_action; }
    while ($row = $r->fetch_assoc()) $g1AttrCur[(int) $row['products_attributes_id']] = $row;
}
vetusLogMsg("  → con variantes: " . count(array_filter($attrsByProd, fn($a) => !empty($a))) . " productos / " . count($paIds) . " atributos");

// Apply optional codes filter (intersect by products_model OR any variant ref).
if (!empty($codesFilterSet)) {
    $before = count($prods);
    foreach ($prods as $pid => $p) {
        $keep = false;
        $pm = strtolower(trim((string) $p['products_model']));
        if ($pm !== '' && isset($codesFilterSet[$pm])) $keep = true;
        if (!$keep && !empty($attrsByProd[$pid])) {
            foreach ($attrsByProd[$pid] as $a) {
                $r1 = strtolower(trim((string) $a['reference']));
                $r2 = strtolower(trim((string) $a['reference_prov']));
                if (($r1 !== '' && isset($codesFilterSet[$r1])) || ($r2 !== '' && isset($codesFilterSet[$r2]))) { $keep = true; break; }
            }
        }
        if (!$keep) unset($prods[$pid]);
    }
    vetusLogMsg("Filtro de SKUs aplicado: " . count($prods) . " / $before productos en scope");
    if (empty($prods)) { vetusLogMsg("Filtro deja 0 productos. Nada que hacer."); goto end_action; }
}

// ─────────────────────────── PLAN BUILD ───────────────────────────

$updPriceMain = []; $updCostMain = []; $updG1Main = []; $insG1Main = [];
$updAttrPrice = []; $updAttrG1 = []; $insAttrG1 = [];
$extremesProds = []; $noMatch = []; $skippedTipo = []; $skippedVariants = []; $processed = 0;

foreach ($prods as $pid => $p) {
    $variants = $attrsByProd[$pid] ?? [];

    if (empty($variants)) {
        // ── Producto SUELTO ──
        $key = strtolower(trim((string) $p['products_model']));
        $entry = ($key !== '' && isset($bySku[$key])) ? $bySku[$key] : null;
        if ($entry === null) {
            $noMatch[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'reason'=>'sin SKU en PDF'];
            continue;
        }
        $tipoRaw = strtoupper(trim((string) ($entry['tipo'] ?? '')));
        $rrp = (float) ($entry['rrp'] ?? 0);
        $pricing = vetusComputePricing($entry);
        if ($pricing === null) {
            if (!empty($entry['price_on_demand'])) {
                $skippedTipo[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'tipo'=>$tipoRaw, 'reason'=>'Precio bajo demanda en PDF'];
            } elseif ($rrp <= 0) {
                $skippedTipo[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'tipo'=>$tipoRaw, 'reason'=>'RRP no numérico / bajo demanda'];
            } elseif ($tipoRaw === 'K') {
                $skippedTipo[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'tipo'=>'K', 'reason'=>'Tipo K (a consultar — discount no definido)', 'is_k'=>true];
            } else {
                $skippedTipo[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'tipo'=>$tipoRaw, 'reason'=>"Tipo '$tipoRaw' no soportado"];
            }
            continue;
        }
        $processed++;
        $newCost = $pricing['cost']; $newPrice = $pricing['price']; $newG1 = $pricing['g1'];
        $curPrice = (float) $p['products_price']; $curCost = (float) $p['products_cost'];
        $dP = priceDeltaPct($curPrice, $newPrice); $dC = priceDeltaPct($curCost, $newCost);
        // First-time set: si el viejo era 0 (NULL), NO es un "extremo" — es una inicialización.
        // Sin esta excepción, los 369/810 productos Vetus con cost=0 caen TODOS en extremos por defecto.
        $effDP = ($curPrice <= 0) ? 0 : $dP;
        $effDC = ($curCost  <= 0) ? 0 : $dC;
        if (!$applyExtremes && $maxChangeRatio > 0 && (max($effDP, $effDC) > $maxChangeRatio)) {
            $extremesProds[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'tipo'=>$pricing['tipo'], 'why'=>'suelto', 'rrp'=>$pricing['rrp'], 'oldP'=>$curPrice, 'newP'=>$newPrice, 'oldC'=>$curCost, 'newC'=>$newCost];
            continue;
        }
        if ($dP > PRICE_THRESHOLD) $updPriceMain[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'tipo'=>$pricing['tipo'], 'rrp'=>$pricing['rrp'], 'old'=>$curPrice, 'new'=>$newPrice];
        if ($dC > PRICE_THRESHOLD) $updCostMain[]  = ['pid'=>$pid, 'ref'=>$p['products_model'], 'tipo'=>$pricing['tipo'], 'rrp'=>$pricing['rrp'], 'old'=>$curCost,  'new'=>$newCost];
        if (isset($g1Cur[$pid])) {
            if (priceDeltaPct($g1Cur[$pid], $newG1) > PRICE_THRESHOLD) $updG1Main[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'tipo'=>$pricing['tipo'], 'old'=>$g1Cur[$pid], 'new'=>$newG1];
        } else {
            $insG1Main[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'tipo'=>$pricing['tipo'], 'new'=>$newG1];
        }
        continue;
    }

    // ── Producto CON VARIANTES ──
    $variantPrices = []; $localSkipped = [];
    foreach ($variants as $a) {
        // Per-variant SKU lookup: reference first, then reference_prov.
        $cands = [];
        $r1 = trim((string) $a['reference']); if ($r1 !== '') $cands[] = $r1;
        $r2 = trim((string) $a['reference_prov']); if ($r2 !== '' && strcasecmp($r2, $r1) !== 0) $cands[] = $r2;
        $matched = null; $matchedRef = '';
        foreach ($cands as $c) {
            $k = strtolower($c);
            if (isset($bySku[$k])) { $matched = $bySku[$k]; $matchedRef = $c; break; }
        }
        if ($matched === null) {
            $localSkipped[] = ['paid'=>(int)$a['products_attributes_id'], 'ref'=>($a['reference'] ?: $a['reference_prov']), 'reason'=>'sin SKU en PDF', 'tipo'=>''];
            continue;
        }
        $tipoRaw = strtoupper(trim((string) ($matched['tipo'] ?? '')));
        $rrp = (float) ($matched['rrp'] ?? 0);
        $vp = vetusComputePricing($matched);
        if ($vp === null) {
            if (!empty($matched['price_on_demand'])) {
                $reason = 'Precio bajo demanda en PDF';
            } elseif ($rrp <= 0) {
                $reason = 'RRP no numérico / bajo demanda';
            } elseif ($tipoRaw === 'K') {
                $reason = 'Tipo K (a consultar — discount no definido)';
            } else {
                $reason = "Tipo '$tipoRaw' no soportado";
            }
            $localSkipped[] = ['paid'=>(int)$a['products_attributes_id'], 'ref'=>$matchedRef, 'reason'=>$reason, 'tipo'=>$tipoRaw];
            continue;
        }
        $variantPrices[(int) $a['products_attributes_id']] = $vp;
    }

    if (empty($variantPrices)) {
        // ALL variants skippable → product goes to noMatch.
        $reasons = [];
        foreach ($localSkipped as $ls) $reasons[] = $ls['ref'] . ':' . $ls['reason'];
        $noMatch[] = ['pid'=>$pid, 'ref'=>$p['products_model'] . ' (todas las variantes saltadas: ' . implode(' | ', array_slice($reasons, 0, 3)) . ')', 'reason'=>'todas variantes saltadas'];
        continue;
    }

    // Record skipped variants for the report (parent still updates).
    foreach ($localSkipped as $ls) {
        $skippedVariants[] = ['pid'=>$pid, 'ref_padre'=>$p['products_model'], 'paid'=>$ls['paid'], 'ref'=>$ls['ref'], 'tipo'=>$ls['tipo'], 'reason'=>$ls['reason']];
    }

    $processed++;
    $cheapestPa = null; $cheapestPrice = PHP_FLOAT_MAX;
    // Tiebreak por paid (menor): determinista pase lo que pase con el orden de fetch_assoc.
    ksort($variantPrices, SORT_NUMERIC);
    foreach ($variantPrices as $paId => $vp) {
        if ($vp['price'] < $cheapestPrice
            || ($cheapestPa !== null && $vp['price'] == $cheapestPrice && $paId < $cheapestPa)) {
            $cheapestPrice = $vp['price']; $cheapestPa = $paId;
        }
    }
    $mainNew = $variantPrices[$cheapestPa];
    $newCost = $mainNew['cost']; $newPrice = $mainNew['price']; $newG1Main = $mainNew['g1'];
    $curPrice = (float) $p['products_price']; $curCost = (float) $p['products_cost'];
    $dP = priceDeltaPct($curPrice, $newPrice); $dC = priceDeltaPct($curCost, $newCost);
    // First-time set: exenta old=0 del extremes gate (mismo razonamiento que en suelto).
    $effDP = ($curPrice <= 0) ? 0 : $dP;
    $effDC = ($curCost  <= 0) ? 0 : $dC;
    if (!$applyExtremes && $maxChangeRatio > 0 && (max($effDP, $effDC) > $maxChangeRatio)) {
        $extremesProds[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'tipo'=>$mainNew['tipo'], 'why'=>'con variantes ('.count($variants).')', 'rrp'=>$mainNew['rrp'], 'oldP'=>$curPrice, 'newP'=>$newPrice, 'oldC'=>$curCost, 'newC'=>$newCost];
        continue;
    }
    if ($dP > PRICE_THRESHOLD) $updPriceMain[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'tipo'=>$mainNew['tipo'], 'rrp'=>$mainNew['rrp'], 'old'=>$curPrice, 'new'=>$newPrice];
    if ($dC > PRICE_THRESHOLD) $updCostMain[]  = ['pid'=>$pid, 'ref'=>$p['products_model'], 'tipo'=>$mainNew['tipo'], 'rrp'=>$mainNew['rrp'], 'old'=>$curCost,  'new'=>$newCost];
    if (isset($g1Cur[$pid])) {
        if (priceDeltaPct($g1Cur[$pid], $newG1Main) > PRICE_THRESHOLD) $updG1Main[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'tipo'=>$mainNew['tipo'], 'old'=>$g1Cur[$pid], 'new'=>$newG1Main];
    } else {
        $insG1Main[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'tipo'=>$mainNew['tipo'], 'new'=>$newG1Main];
    }

    foreach ($variants as $a) {
        $paId = (int) $a['products_attributes_id'];
        if (!isset($variantPrices[$paId])) continue;   // skipped variant — leave DB row alone
        $vp = $variantPrices[$paId];
        $delta = round($vp['price'] - $newPrice, 4); $prefix = $delta < 0 ? '-' : '+'; $absDelta = abs($delta);
        $curAbs = (float) $a['options_values_price']; $curPref = $a['price_prefix'] ?: '+';
        $signedNew = ($prefix === '-' ? -$absDelta : $absDelta); $signedCur = ($curPref === '-' ? -$curAbs : $curAbs);
        if (priceDeltaPct($signedCur, $signedNew) > PRICE_THRESHOLD || ($absDelta > 0 && $curAbs == 0) || ($curPref !== $prefix && $absDelta > 0.0001))
            $updAttrPrice[] = ['paid'=>$paId, 'pid'=>$pid, 'ref'=>$a['reference'], 'absOld'=>$curAbs, 'prefOld'=>$curPref, 'absNew'=>$absDelta, 'prefNew'=>$prefix];
        $g1Delta = round($vp['g1'] - $newG1Main, 4); $g1Prefix = $g1Delta < 0 ? '-' : '+'; $g1Abs = abs($g1Delta);
        if (isset($g1AttrCur[$paId])) {
            $curG1Abs = (float) $g1AttrCur[$paId]['options_values_price']; $curG1Pref = $g1AttrCur[$paId]['price_prefix'] ?: '+';
            $signedNewG1 = ($g1Prefix === '-' ? -$g1Abs : $g1Abs); $signedCurG1 = ($curG1Pref === '-' ? -$curG1Abs : $curG1Abs);
            if (priceDeltaPct($signedCurG1, $signedNewG1) > PRICE_THRESHOLD || ($g1Abs > 0 && $curG1Abs == 0) || ($curG1Pref !== $g1Prefix && $g1Abs > 0.0001))
                $updAttrG1[] = ['paid'=>$paId, 'pid'=>$pid, 'absNew'=>$g1Abs, 'prefNew'=>$g1Prefix];
        } else {
            $insAttrG1[] = ['paid'=>$paId, 'pid'=>$pid, 'absNew'=>$g1Abs, 'prefNew'=>$g1Prefix];
        }
    }
}

// ─────────────────────────── SPECIALS A BORRAR ───────────────────────────
// Política (usuario 2026-06-25): cuando se ejecuta SIN marcar "Aplicar extremos",
// se borran las ofertas activas (status=1) SOLO de los productos repreciados en este run (V3 2026-07-17).
// Razonamiento: una oferta puesta sobre un PVP antiguo deja de tener sentido cuando
// el PVP se actualiza por la tarifa nueva — el motor auto_specials la recreará si toca.
// Política V3 (2026-07-17): solo ofertas de productos cuyo PVP cambia en ESTE run.
// (La V2 borraba todas las del scope — incidente Osculati 2026-07-16, 383 ofertas
// purgadas de 15+ marcas y restauradas desde backup.)
$badSpecials = [];
if (!$applyExtremes && !empty($updPriceMain)) {
    $effPrice = [];
    foreach ($updPriceMain as $u) $effPrice[(int)$u['pid']] = (float) $u['new'];
    $idsRepriced = implode(',', array_map('intval', array_keys($effPrice)));

    $rs = $mysqli->query("SELECT specials_id, products_id, specials_new_products_price, specials_date_added, expires_date, expires_repeat FROM specials WHERE status=1 AND products_id IN ($idsRepriced)");
    if (!$rs) { vetusLogMsg("ERROR SELECT specials: " . $mysqli->error); goto end_action; }
    while ($s = $rs->fetch_assoc()) {
        $pid = (int) $s['products_id'];
        $eff = (float) ($effPrice[$pid] ?? 0);
        $sp  = (float) $s['specials_new_products_price'];
        $dtoPct = $eff > 0 ? (($eff - $sp) / $eff) * 100 : 0.0;
        $badSpecials[] = [
            'specials_id' => (int) $s['specials_id'],
            'pid' => $pid,
            'ref' => $prods[$pid]['products_model'] ?? '?',
            'eff_price' => $eff,
            'sp_price'  => $sp,
            'dto_pct'   => $dtoPct,
            'reason'    => ($sp > $eff) ? 'NEGATIVO (special > PVP nuevo)' : (sprintf('dto %.1f%%', $dtoPct) . ' sobre PVP nuevo — PVP repreciado en este run'),
            'created'   => substr((string)$s['specials_date_added'], 0, 10),
            'expires'   => substr((string)$s['expires_date'], 0, 10),
        ];
    }
}

vetusLogMsg("==================== PLAN ====================");
vetusLogMsg("Procesados: $processed");
vetusLogMsg("UPDATE products.products_price : " . count($updPriceMain));
vetusLogMsg("UPDATE products.products_cost  : " . count($updCostMain));
vetusLogMsg("UPDATE products_groups (G1)    : " . count($updG1Main) . " | INSERT G1: " . count($insG1Main));
vetusLogMsg("UPDATE variantes price : " . count($updAttrPrice) . " | UPDATE G1 var: " . count($updAttrG1) . " | INSERT G1 var: " . count($insAttrG1));
if (!$applyExtremes && $maxChangeRatio > 0) vetusLogMsg("⚠️  Productos extremos > {$maxChangePct}% EXCLUIDOS: " . count($extremesProds) . " (padre + variantes no se tocan)");
vetusLogMsg("Sin match en PDF : " . count($noMatch));
vetusLogMsg("Saltados por Tipo/RRP : " . count($skippedTipo) . " (incluye Tipo K)");
vetusLogMsg("Variantes saltadas (padre SÍ se actualiza) : " . count($skippedVariants));
if (!$applyExtremes) vetusLogMsg("🗑️  Specials a BORRAR (negativos o > tope sobre PVP efectivo) : " . count($badSpecials) . (empty($badSpecials)?" (ninguno)":""));

$showLimit = 25;
if ($onlyExtremes) vetusLogMsg("** Modo SOLO EXTREMOS: se omiten las listas de cambios, sin-match y saltados por Tipo **");
if (!$onlyExtremes) {
    foreach ([['UPDATE price principal', $updPriceMain], ['UPDATE cost principal', $updCostMain], ['INSERT G1 principal', $insG1Main], ['UPDATE G1 principal', $updG1Main]] as [$title, $arr]) {
        if (empty($arr)) continue;
        vetusLogMsg("--- $title (top $showLimit) ---");
        foreach (array_slice($arr, 0, $showLimit) as $u) {
            $tipoStr = isset($u['tipo']) ? "tipo={$u['tipo']}" : '';
            $rrpStr  = isset($u['rrp']) ? sprintf(" rrp=%.4f", $u['rrp']) : '';
            if (isset($u['old'])) {
                $pct = priceDeltaPct($u['old'], $u['new']) * 100;
                vetusLogMsg(sprintf("  pid=%d ref=%s %s%s [%s] : %.4f → %.4f (%s%.1f%%)", $u['pid'], $u['ref'], $tipoStr, $rrpStr, $nm($u['pid']), $u['old'], $u['new'], $u['new']>=$u['old']?'+':'-', $pct));
            } else {
                vetusLogMsg(sprintf("  pid=%d ref=%s %s [%s] : (sin G1) → %.4f", $u['pid'], $u['ref'], $tipoStr, $nm($u['pid']), $u['new']));
            }
        }
        if (count($arr) > $showLimit) vetusLogMsg("  …y " . (count($arr) - $showLimit) . " más");
    }
}

if (!empty($extremesProds)) {
    vetusLogMsg("--- ⚠️ EXTREMOS excluidos (TODOS: " . count($extremesProds) . ", >{$maxChangePct}%, NO se tocan) — posible pack-vs-unidad o error ---");
    foreach ($extremesProds as $u) {
        $pctP = priceDeltaPct($u['oldP'], $u['newP']) * 100;
        $pctC = priceDeltaPct($u['oldC'], $u['newC']) * 100;
        vetusLogMsg(sprintf("  pid=%d ref=%s tipo=%s rrp=%.4f [%s] (%s): price %.4f→%.4f (%.1f%%) cost %.4f→%.4f (%.1f%%)",
            $u['pid'], $u['ref'], $u['tipo'] ?? '', $u['rrp'] ?? 0, $nm($u['pid']), $u['why'], $u['oldP'], $u['newP'], $pctP, $u['oldC'], $u['newC'], $pctC));
    }
}

if (!empty($badSpecials)) {
    vetusLogMsg(sprintf("--- 🗑️ Specials a BORRAR (repreciados en este run: %d, sobre PVP nuevo) ---", count($badSpecials)));
    foreach ($badSpecials as $b) {
        vetusLogMsg(sprintf("  specials_id=%d pid=%d ref=%-14s PVP=%7.2f sp=%7.2f dto=%5.1f%% creado=%s expira=%s — %s",
            $b['specials_id'], $b['pid'], $b['ref'],
            $b['eff_price']*1.21, $b['sp_price']*1.21, $b['dto_pct'],
            $b['created'], $b['expires'], $b['reason']));
    }
}

if (!$onlyExtremes && !empty($noMatch)) {
    vetusLogMsg("--- Sin match en PDF (TODOS: " . count($noMatch) . ", no se tocan) ---");
    foreach ($noMatch as $u) vetusLogMsg(sprintf("  pid=%d ref=%s [%s]", $u['pid'], $u['ref'], $nm($u['pid'])));
}

if (!$onlyExtremes && !empty($skippedTipo)) {
    // Split into Tipo K (a CONSULTAR) and "no soportado / RRP inválido".
    $kRows = []; $otherRows = [];
    foreach ($skippedTipo as $s) {
        if (!empty($s['is_k']) || strtoupper((string) $s['tipo']) === 'K') $kRows[] = $s;
        else $otherRows[] = $s;
    }
    if (!empty($kRows)) {
        vetusLogMsg("--- Tipo K — A CONSULTAR (TODOS: " . count($kRows) . ", no se tocan) ---");
        foreach ($kRows as $s) vetusLogMsg(sprintf("  pid=%d ref=%s tipo=K [%s] — %s", $s['pid'], $s['ref'], $nm($s['pid']), $s['reason']));
    }
    if (!empty($otherRows)) {
        vetusLogMsg("--- Tipo no soportado / RRP inválido (TODOS: " . count($otherRows) . ", no se tocan) ---");
        foreach ($otherRows as $s) vetusLogMsg(sprintf("  pid=%d ref=%s tipo=%s [%s] — %s", $s['pid'], $s['ref'], $s['tipo'], $nm($s['pid']), $s['reason']));
    }
}

if (!$onlyExtremes && !empty($skippedVariants)) {
    vetusLogMsg("--- Variantes saltadas (RRP/Tipo, padre SÍ se actualiza) — TODOS: " . count($skippedVariants) . " ---");
    foreach ($skippedVariants as $s) {
        vetusLogMsg(sprintf("  pid=%d padre=%s paid=%d ref=%s tipo=%s [%s] — %s",
            $s['pid'], $s['ref_padre'], $s['paid'], $s['ref'], $s['tipo'] ?: '?', $nm($s['pid']), $s['reason']));
    }
}

if ($dryRun) { vetusLogMsg("=== Dry-run finalizado. No se ha tocado nada. ==="); goto end_action; }

// ─────────────────────────── EXECUTE ───────────────────────────

// Optional cap on total changes per run — atómico por producto.
// (Antes cortaba lista-a-lista y podía updatear price pero no cost del mismo pid → commit inconsistente.)
if ($max > 0) {
    $pidOrder = [];   // preserva el orden de aparición del primer write por pid
    $allLists = [
        &$updPriceMain, &$updCostMain, &$updG1Main, &$insG1Main,
        &$updAttrPrice, &$updAttrG1,  &$insAttrG1,
    ];
    foreach ($allLists as $arrRef) {
        foreach ($arrRef as $u) {
            $pid = (int) ($u['pid'] ?? 0);
            if ($pid > 0 && !isset($pidOrder[$pid])) $pidOrder[$pid] = true;
        }
    }
    if (count($pidOrder) > $max) {
        $keepSet = array_flip(array_slice(array_keys($pidOrder), 0, $max));
        $filter  = function (array $arr) use ($keepSet) {
            return array_values(array_filter($arr, fn($u) => isset($keepSet[(int) ($u['pid'] ?? 0)])));
        };
        $updPriceMain = $filter($updPriceMain);
        $updCostMain  = $filter($updCostMain);
        $updG1Main    = $filter($updG1Main);
        $insG1Main    = $filter($insG1Main);
        $updAttrPrice = $filter($updAttrPrice);
        $updAttrG1    = $filter($updAttrG1);
        $insAttrG1    = $filter($insAttrG1);
        vetusLogMsg("Tope max=$max PRODUCTOS aplicado. " . count($keepSet) . " productos completos serán escritos (atómico por pid).");
    } else {
        vetusLogMsg("Tope max=$max productos NO necesario (productos a tocar=" . count($pidOrder) . ").");
    }
}

vetusLogMsg("Aplicando cambios en transacción única…");
$mysqli->begin_transaction();
try {
    foreach ($updPriceMain as $u) {
        if (!$mysqli->query("UPDATE products SET products_price=" . fmt4($u['new']) . ", products_last_modified=NOW() WHERE products_id=" . (int) $u['pid'] . " AND manufacturers_id=" . MANUFACTURER_ID))
            throw new Exception("price pid=" . $u['pid'] . ": " . $mysqli->error);
    }
    foreach ($updCostMain as $u) {
        if (!$mysqli->query("UPDATE products SET products_cost=" . fmt4($u['new']) . ", products_last_modified=NOW() WHERE products_id=" . (int) $u['pid'] . " AND manufacturers_id=" . MANUFACTURER_ID))
            throw new Exception("cost pid=" . $u['pid'] . ": " . $mysqli->error);
    }
    foreach ($updG1Main as $u) {
        if (!$mysqli->query("UPDATE products_groups SET customers_group_price=" . fmt4($u['new']) . " WHERE products_id=" . (int) $u['pid'] . " AND customers_group_id=" . G1_GROUP_ID))
            throw new Exception("g1 pid=" . $u['pid'] . ": " . $mysqli->error);
    }
    foreach ($insG1Main as $u) {
        if (!$mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . G1_GROUP_ID . ", " . (int) $u['pid'] . ", " . fmt4($u['new']) . ", 1, 1)"))
            throw new Exception("ins g1 pid=" . $u['pid'] . ": " . $mysqli->error);
    }
    foreach ($updAttrPrice as $u) {
        if (!$mysqli->query("UPDATE products_attributes SET options_values_price=" . fmt4($u['absNew']) . ", price_prefix='" . $u['prefNew'] . "' WHERE products_attributes_id=" . (int) $u['paid']))
            throw new Exception("attr paid=" . $u['paid'] . ": " . $mysqli->error);
    }
    foreach ($updAttrG1 as $u) {
        if (!$mysqli->query("UPDATE products_attributes_groups SET options_values_price=" . fmt4($u['absNew']) . ", price_prefix='" . $u['prefNew'] . "' WHERE products_attributes_id=" . (int) $u['paid'] . " AND customers_group_id=" . G1_GROUP_ID))
            throw new Exception("attr g1 paid=" . $u['paid'] . ": " . $mysqli->error);
    }
    foreach ($insAttrG1 as $u) {
        if (!$mysqli->query("INSERT INTO products_attributes_groups (products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id, options_values_weight, weight_prefix) VALUES (" . (int) $u['paid'] . ", " . G1_GROUP_ID . ", " . fmt4($u['absNew']) . ", '" . $u['prefNew'] . "', " . (int) $u['pid'] . ", 0, '+')"))
            throw new Exception("ins attr g1 paid=" . $u['paid'] . ": " . $mysqli->error);
    }

    // Borrar specials descolgados (solo si !applyExtremes; coincide con la política del modo cauto).
    // Backup SQL completo antes de borrar — revertible con: mysql … < backup.sql
    if (!empty($badSpecials)) {
        $bakDir = '/home/francobordo/backups';
        @mkdir($bakDir, 0755, true);
        $bakPath = $bakDir . '/vetus_specials_purge_' . date('Ymd_His') . '.sql';
        // Pre-flight: espacio en disco. Si <100MB libres, abortamos antes de tocar nada
        // (la transacción hace rollback y los specials NO se borran sin backup completo).
        $freeBytes = @disk_free_space($bakDir);
        if ($freeBytes !== false && $freeBytes < 100 * 1024 * 1024) {
            vetusLogMsg("WARN: poco espacio en $bakDir (" . round(($freeBytes ?: 0) / 1024 / 1024) . "MB libres, mínimo 100MB) — abortando DELETE de specials.");
            throw new Exception("disco insuficiente para backup specials");
        }
        $fh = @fopen($bakPath, 'w');
        if ($fh) {
            fwrite($fh, "-- Backup specials borrados por Actualizador_precios_vetus.php " . date('Y-m-d H:i:s') . "\n");
            fwrite($fh, "-- Política V3: !apply_extremes ⇒ borrar ofertas de productos repreciados en este run. Total: " . count($badSpecials) . " filas.\n\n");
            $idList = implode(',', array_map(fn($b) => (int) $b['specials_id'], $badSpecials));
            $rb = $mysqli->query("SELECT * FROM specials WHERE specials_id IN ($idList)");
            if ($rb) while ($srow = $rb->fetch_assoc()) {
                $cols = array_keys($srow);
                $vals = array_map(function ($v) use ($mysqli) {
                    if ($v === null) return 'NULL';
                    return "'" . $mysqli->real_escape_string((string) $v) . "'";
                }, array_values($srow));
                fwrite($fh, "INSERT INTO specials (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n");
            }
            fclose($fh);
            // Post-write sanity: si el .sql quedó vacío/truncado (disco lleno a mitad, fclose silencioso),
            // borramos el fichero parcial y abortamos antes del DELETE de specials.
            $bakSize = @filesize($bakPath);
            if ($bakSize === false || $bakSize < 100) {
                @unlink($bakPath);
                vetusLogMsg("WARN: backup escrito vacío/truncado ($bakSize bytes) — abortando DELETE de specials.");
                throw new Exception("backup specials truncado o vacío");
            }
            vetusLogMsg("Backup specials borrados: $bakPath ($bakSize bytes)");
        } else {
            vetusLogMsg("WARN: no pude crear backup en $bakDir — abortando DELETE de specials por seguridad.");
            throw new Exception("backup specials no escribible");
        }
        $idList = implode(',', array_map(fn($b) => (int) $b['specials_id'], $badSpecials));
        if (!$mysqli->query("DELETE FROM specials WHERE specials_id IN ($idList)"))
            throw new Exception("delete specials: " . $mysqli->error);
        vetusLogMsg("Specials borrados: " . $mysqli->affected_rows);
    }

    // Bump products_last_modified para productos tocados SOLO por variantes (los tocados por price/cost
    // ya lo tienen actualizado). Sin esto, cachés/feeds (Google Shopping, RAG, etc.) se quedan stale.
    $bumpedMain = [];
    foreach ($updPriceMain as $u) $bumpedMain[(int)$u['pid']] = true;
    foreach ($updCostMain  as $u) $bumpedMain[(int)$u['pid']] = true;
    $needBump = [];
    foreach ($updAttrPrice as $u) if (!isset($bumpedMain[(int)$u['pid']])) $needBump[(int)$u['pid']] = true;
    foreach ($updAttrG1    as $u) if (!isset($bumpedMain[(int)$u['pid']])) $needBump[(int)$u['pid']] = true;
    foreach ($insAttrG1    as $u) if (!isset($bumpedMain[(int)$u['pid']])) $needBump[(int)$u['pid']] = true;
    // El borrado de un special cambia el precio efectivo que ve el cliente → bump también.
    foreach ($badSpecials as $b) if (!isset($bumpedMain[(int)$b['pid']])) $needBump[(int)$b['pid']] = true;
    if (!empty($needBump)) {
        $bumpList = implode(',', array_map('intval', array_keys($needBump)));
        if (!$mysqli->query("UPDATE products SET products_last_modified=NOW() WHERE products_id IN ($bumpList) AND manufacturers_id=" . MANUFACTURER_ID))
            throw new Exception("bump last_modified: " . $mysqli->error);
        vetusLogMsg("products_last_modified bumped en " . count($needBump) . " productos (solo variantes cambiaron).");
    }
    $mysqli->commit();
    vetusLogMsg("=== COMMIT OK ===");
} catch (Exception $e) {
    $mysqli->rollback();
    vetusLogMsg("=== ROLLBACK por error: " . $e->getMessage() . " ===");
}

end_action:
?>
    </div>
    <p style="margin-top:15px;"><a href="<?php echo tep_href_link('Actualizador_precios_vetus.php'); ?>" class="xbutton small hv9">← Volver</a></p>
<?php else: ?>
    <h2>Actualizador de precios — Vetus</h2>
    <?php
        if (!is_readable(VETUS_PDF)) echo '<p style="color:red;">No se encuentra/lee el PDF: <code>' . htmlspecialchars(VETUS_PDF) . '</code></p>';
        else echo '<p style="color:#666;font-size:13px;">PDF: <code>' . htmlspecialchars(basename(VETUS_PDF)) . '</code> (' . round(filesize(VETUS_PDF)/1024) . ' KB, mtime ' . date('Y-m-d H:i', filemtime(VETUS_PDF)) . ')</p>';
    ?>
    <p>
        Parsea la <strong>Tarifa general VETUS</strong> (PDF, precios sin IVA) y compara con
        <code>products_cost</code>, <code>products_price</code> y Grupo 1 (Profesional) para los productos
        Vetus (<code>manufacturers_id=421</code>: legacy + origin vetus).
        Casa <strong>por SKU = products_model</strong> (PDF no trae EAN).
        Cada variante se busca por su propio <code>reference</code> / <code>reference_prov</code>.
        Aplica solo cuando la diferencia &gt; <strong><?php echo PRICE_THRESHOLD * 100; ?>%</strong>.
    </p>
    <p style="background:#fff8dc;border:1px solid #e0c060;padding:8px 12px;border-radius:4px;font-size:13px;">
        <strong>Tipos del PDF y descuentos aplicados:</strong><br>
        Distribuidor (cost = RRP × (1 − pct/100)): A=38, B=42, C=46, EP=25, ST=25, SV=40, VE=38, YV=35.<br>
        Profesional G1 (banda 500-5000 €): A=25, B=28, C=33, VE=20, SV=25, ST=15, YV=20, EP=18.<br>
        <strong>Tipo K = A CONSULTAR — NO se actualiza.</strong> Tipos no listados (incluido <code>V</code> raw) también se saltan.
    </p>
    <form method="post" style="background:#f5f5f5;padding:15px;border-radius:5px;">
        <p><strong>Tope de variación</strong>: <label>excluir cambios &gt; <input type="number" name="max_change_pct" value="<?php echo (int) MAX_CHANGE_PCT_DEF; ?>" min="0" max="500" step="1" style="width:60px;"> %</label>
            <small style="color:#888;display:block;margin-top:4px;">Si el price o cost del producto (padre, si hay variantes) cambia más de este %, se excluye el producto entero. 0 = sin tope. Protege contra pack-vs-unidad o errores.</small></p>
        <p><label><input type="checkbox" name="apply_extremes" value="1"> Aplicar también los extremos (desactiva el tope)</label></p>
        <p><label><input type="checkbox" name="only_extremes" value="1"> <strong>Ver SOLO los productos saltados por extremos</strong> (oculta el resto del plan)</label></p>
        <p><label>Productos máximos por ejecución (0 = sin límite): <input type="number" name="max" value="0" min="0" style="width:80px;"></label>
            <small style="color:#888;display:block;margin-top:4px;">El tope cuenta productos completos (no campos sueltos): cada producto se aplica entero o no se aplica, evitando commits inconsistentes.</small></p>
        <p><label>SKUs a procesar (vacío = todos los Vetus). Uno por línea o separados por coma/espacio:<br>
            <textarea name="codes_filter" rows="3" style="width:100%;font-family:monospace;" placeholder="STM6804&#10;CT41020&#10;…"></textarea></label></p>
        <p><label><input type="checkbox" name="confirm_execute" value="1"> Aplicar cambios (sin marcar = solo PLAN/dry-run)</label></p>
        <input type="hidden" name="action" value="plan">
        <button type="submit" name="action" value="plan" class="xbutton small hv9">Generar plan (dry-run)</button>
        <button type="submit" name="action" value="execute" class="xbutton small hv9 verde" onclick="return this.form.confirm_execute.checked || (alert('Marca la casilla Aplicar cambios antes de ejecutar.'), false);">Ejecutar</button>
    </form>
    <p style="margin-top:20px;color:#888;font-size:12px;">
        <strong>Reglas</strong>:<br>
        - <code>products_price</code> ← <code>roundToNickel(RRP)</code> (PVP sin IVA, snap a múltiplo de 0,05 € con IVA).<br>
        - <code>products_cost</code> ← <code>RRP × (1 − dist[tipo]/100)</code>, redondeo a 4 decimales (sin snap nickel — igual que importador).<br>
        - <strong>Grupo 1</strong> (per-Tipo): <code>roundToNickel(RRP × (1 − prof[tipo]/100))</code>. Es la diferencia respecto al importador (×0,80 plano).<br>
        - Match por SKU = <code>products_model</code> (PDF no trae EAN). Variantes: padre = más barata, resto delta.<br>
        - Scope: <code>manufacturers_id=421</code> (Vetus). Stock NO se toca. <code>products_status</code> NO se toca. Sin match / Tipo K / Tipo no soportado → se listan, no se tocan.<br>
        - <strong>Cuando NO se aplican extremos</strong>: el script <strong>borra las ofertas activas solo de los productos repreciados en este run</strong> (una oferta sobre un PVP que cambia deja de tener sentido; las de productos cuyo PVP no se mueve se conservan). Se hace backup SQL automático en <code>/home/francobordo/backups/vetus_specials_purge_*.sql</code> (revertible). Si quieres conservar todas las ofertas, marca «Aplicar también los extremos» (desactiva la limpieza).
    </p>
<?php endif; ?>
</div></td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
