<?php
/**
 * Regenera la descripción de uno o varios productos MB.
 * Útil cuando el LLM entra en bucle (ej. SKU 13303 generó "Seguridad alimentaria: Libre de BPA" ×8)
 * o cuando el bloque de specs tiene valores en idioma incorrecto (SKU 28423: POLIESTER en lugar de POLYESTER en EN).
 *
 * Reutiliza la lógica del importador: lee WC API cache → llama LLM con guard anti-bucle → aplica
 * el bloque de specs nuevo (con traducción ES→EN para valores) → reformat final.
 *
 * Uso:
 *   /scripts/mb_redo_description.php?pid=362079&dry=1            Dry-run sobre un pid
 *   /scripts/mb_redo_description.php?pid=362079,362067           Aplica
 *   /scripts/mb_redo_description.php?pid=362079&skip_format=1    Sin LLM (sólo reformat estético)
 */
chdir(dirname(__DIR__));
require 'includes/application_top.php';
require_once DIR_FS_DOCUMENT_ROOT . 'includes/vendor/autoload.php';
require_once dirname(__DIR__) . '/includes/mb_reformat_helpers.php';

// Cargamos las funciones del importador para reutilizar (mbWcFetch, llmCall, mbXlsxSpecsHtml,
// LLM_FORMAT_PROMPT_*, loadMbXlsx, etc.). El importador se ejecuta como library: con $action
// vacío entra en la rama "form" (no action), sale rápido sin tocar BD.
if (function_exists('opcache_invalidate')) {
    @opcache_invalidate(dirname(__DIR__) . '/import-marinebusiness-altas.php', true);
    @opcache_invalidate(dirname(__DIR__) . '/includes/mb_reformat_helpers.php', true);
}
// Trick: include el importador con ob para tragarse el HTML de su form
ob_start();
include_once dirname(__DIR__) . '/import-marinebusiness-altas.php';
ob_end_clean();

set_time_limit(0);
ini_set('memory_limit', '-1');

$pids = [];
if (!empty($_GET['pid'])) {
    foreach (explode(',', $_GET['pid']) as $p) {
        $p = trim($p);
        if (ctype_digit($p)) $pids[] = (int) $p;
    }
}
$dryRun = isset($_GET['dry']);
$skipFormat = isset($_GET['skip_format']);

@header('Content-Type: text/html; charset=utf-8');
while (ob_get_level() > 0) @ob_end_flush();
@ob_implicit_flush(true);
echo '<style>body{font-family:monospace;font-size:12px;margin:20px;} .row{border-bottom:1px solid #ccc;padding:10px 0;} .old{background:#fee;} .new{background:#efe;} pre{white-space:pre-wrap;word-break:break-word;}</style>';
echo '<h2>MB redo description (' . ($dryRun ? 'DRY-RUN' : 'EXECUTE') . ')' . ($skipFormat ? ' — sin LLM' : '') . '</h2>';

if (empty($pids)) {
    echo '<p style="color:red">Falta parámetro <code>?pid=N</code> (separados por coma).</p>';
    exit;
}

function pp($m) { echo '<div>' . htmlspecialchars($m) . "</div>\n"; @flush(); }

// Cargar xlsx una vez
$xlsx = findNewestXlsx(MB_DIR);
if (!$xlsx) { pp('ERROR: xlsx no encontrado en ' . MB_DIR); exit; }
pp('xlsx: ' . basename($xlsx));
$byCode = loadMbXlsx($xlsx);

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
$mysqli->set_charset('utf8');

foreach ($pids as $pid) {
    pp("─── pid=$pid ───");
    $r = $mysqli->query("SELECT products_model, products_import_origin FROM products WHERE products_id=$pid");
    $row = $r->fetch_assoc();
    if (!$row) { pp("  pid=$pid no existe"); continue; }
    $sku = trim((string) $row['products_model']);
    if (stripos((string) $row['products_import_origin'], 'marinebusiness') !== 0) {
        pp("  pid=$pid no es MB (origin='" . $row['products_import_origin'] . "'), skip");
        continue;
    }
    pp("  sku=$sku");

    $xlsxRow = $byCode[$sku] ?? null;
    if (!$xlsxRow) { pp("  ERROR: SKU $sku no está en el xlsx"); continue; }

    // Fetch WC API (de cache si existe)
    $apiEs = mbWcFetch($sku, 'es');
    $apiEn = mbWcFetch($sku, 'en');
    if ($apiEs === null && $apiEn === null) {
        pp("  WARN: SKU $sku no está en WC API, usando texto del xlsx");
    }

    // Texto base (igual que en el importador, líneas ~603-611)
    $apiDescEs = html_entity_decode((string)($apiEs['description'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $apiDescEn = html_entity_decode((string)($apiEn['description'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $charsEs = trim((string)($xlsxRow['CHARS'] ?? ''));

    $rawDescEs = trim($apiDescEs);
    if ($rawDescEs === '' && !empty($xlsxRow['DESC_ES'])) $rawDescEs = '<p>' . htmlspecialchars($xlsxRow['DESC_ES']) . '</p>';
    if ($charsEs !== '') $rawDescEs .= "\n\n<p><strong>" . htmlspecialchars($charsEs) . "</strong></p>";

    $rawDescEn = trim($apiDescEn);
    if ($rawDescEn === '' && !empty($xlsxRow['DESC_EN'])) $rawDescEn = '<p>' . htmlspecialchars($xlsxRow['DESC_EN']) . '</p>';

    $descEs = $rawDescEs;
    $descEn = $rawDescEn;
    if (!$skipFormat) {
        if ($rawDescEs !== '') {
            $inLen = mb_strlen(strip_tags($rawDescEs), 'UTF-8');
            $fmt = llmCall(LLM_FORMAT_PROMPT_ES, $rawDescEs, 2500);
            if (mbFormatLooksValid($fmt, $inLen)) { $descEs = $fmt; pp('  LLM ES: ok'); }
            else pp('  LLM ES: rechazado por validator (probable bucle/short) → uso raw');
        }
        if ($rawDescEn !== '') {
            $inLen = mb_strlen(strip_tags($rawDescEn), 'UTF-8');
            $fmt = llmCall(LLM_FORMAT_PROMPT_EN, $rawDescEn, 2500);
            if (mbFormatLooksValid($fmt, $inLen)) { $descEn = $fmt; pp('  LLM EN: ok'); }
            else pp('  LLM EN: rechazado por validator → uso raw');
        }
    }

    // Pegar bloque de specs (con traducción ES→EN para inglés)
    $specsEs = mbXlsxSpecsHtml($xlsxRow, 'es');
    $specsEn = mbXlsxSpecsHtml($xlsxRow, 'en');
    if ($specsEs !== '') $descEs = trim($descEs) . "\n" . $specsEs;
    if ($specsEn !== '') $descEn = trim($descEn) . "\n" . $specsEn;

    // Reformat final (red de seguridad)
    $descEs = mbReformatDescription($descEs);
    $descEn = mbReformatDescription($descEn);

    // UPDATE
    foreach ([3 => $descEs, 1 => $descEn] as $lang => $newDesc) {
        $cur = $mysqli->query("SELECT products_description FROM products_description WHERE products_id=$pid AND language_id=$lang")->fetch_assoc()['products_description'] ?? '';
        if ($cur === $newDesc) { pp("  lang=$lang sin cambios"); continue; }

        echo '<div class="row"><strong>pid=' . $pid . ' lang=' . $lang . '</strong>';
        echo '<div class="old"><b>ANTES (' . strlen($cur) . ' ch):</b><pre>' . htmlspecialchars(mb_substr($cur, 0, 1500, 'UTF-8')) . (mb_strlen($cur, 'UTF-8') > 1500 ? '…' : '') . '</pre></div>';
        echo '<div class="new"><b>DESPUÉS (' . strlen($newDesc) . ' ch):</b><pre>' . htmlspecialchars(mb_substr($newDesc, 0, 2000, 'UTF-8')) . (mb_strlen($newDesc, 'UTF-8') > 2000 ? '…' : '') . '</pre></div>';
        echo '</div>';

        if (!$dryRun) {
            $q = $mysqli->real_escape_string($newDesc);
            $ok = $mysqli->query("UPDATE products_description SET products_description='$q' WHERE products_id=$pid AND language_id=$lang");
            pp("  UPDATE lang=$lang: " . ($ok ? 'OK' : ('FAIL: ' . $mysqli->error)));
        }
    }
}

pp('=== FIN ===');
