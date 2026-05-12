<?php
/**
 * Reformatea descripciones de productos Marine Business según la guía estética acordada
 * (2026-05-11). Para detalles de las reglas, ver mb_reformat_helpers.php.
 *
 * Uso:
 *   /scripts/mb_reformat.php?dry=1                  Lista candidatos sin tocar BD (web)
 *   /scripts/mb_reformat.php?dry=1&pid=362072       Solo ese pid, dry
 *   /scripts/mb_reformat.php                        Aplica a todos (HTTP)
 *   /scripts/mb_reformat.php?pid=362069,362070      Solo esos pids
 *   php mb_reformat.php DRY                         CLI dry-run
 *   php mb_reformat.php                             CLI execute
 *   php mb_reformat.php PID 362069 362070           CLI sólo esos pids
 */
chdir(dirname(__DIR__));
require 'includes/application_top.php';
// Helper compartido con el importador (fuente única de verdad — NO duplicar funciones aquí).
require_once dirname(__DIR__) . '/includes/mb_reformat_helpers.php';
// Invalidar opcache del helper por si cambió entre requests.
if (function_exists('opcache_invalidate')) {
    @opcache_invalidate(dirname(__DIR__) . '/includes/mb_reformat_helpers.php', true);
}

set_time_limit(0);
ini_set('memory_limit', '-1');

$isCli = (php_sapi_name() === 'cli');

// ─── Selección de productos ────────────────────────────────────────────────

function loadMbDescriptions($mysqli, array $onlyPids = []) {
    $where = "p.products_import_origin LIKE 'marinebusiness%'";
    if (!empty($onlyPids)) {
        $ids = implode(',', array_map('intval', $onlyPids));
        $where = "p.products_id IN ($ids) AND " . $where;
    }
    $rows = [];
    $r = $mysqli->query("SELECT pd.products_id, pd.language_id, pd.products_name, pd.products_description
        FROM products_description pd
        INNER JOIN products p ON p.products_id = pd.products_id
        WHERE $where");
    while ($row = $r->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

// ─── Parámetros ────────────────────────────────────────────────────────────

$dryRun = false;
$onlyPids = [];

if ($isCli) {
    $argv = $_SERVER['argv'] ?? [];
    array_shift($argv);
    foreach ($argv as $i => $arg) {
        $up = strtoupper($arg);
        if ($up === 'DRY') $dryRun = true;
        elseif ($up === 'PID') {
            for ($j = $i + 1; $j < count($_SERVER['argv']); $j++) {
                $v = trim($_SERVER['argv'][$j]);
                if (ctype_digit($v)) $onlyPids[] = (int) $v;
            }
            break;
        }
    }
} else {
    $dryRun = isset($_GET['dry']);
    if (!empty($_GET['pid'])) {
        foreach (explode(',', $_GET['pid']) as $p) {
            $p = trim($p);
            if (ctype_digit($p)) $onlyPids[] = (int) $p;
        }
    }
}

// ─── Output helpers ─────────────────────────────────────────────────────────

if (!$isCli) {
    @header('Content-Type: text/html; charset=utf-8');
    while (ob_get_level() > 0) @ob_end_flush();
    @ob_implicit_flush(true);
    echo '<style>body{font-family:monospace;font-size:12px;margin:20px;} .row{border-bottom:1px solid #ccc;padding:10px 0;} .old{background:#fee;} .new{background:#efe;} pre{white-space:pre-wrap;word-break:break-word;}</style>';
    echo '<h2>Marine Business — reformat de descripciones (' . ($dryRun ? 'DRY-RUN' : 'EJECUCIÓN REAL') . ')</h2>';
}

function pp($msg) {
    global $isCli;
    if ($isCli) echo $msg . "\n";
    else echo '<div>' . htmlspecialchars($msg) . '</div>';
    @flush();
}
function ppDiff($pid, $lang, $name, $oldHtml, $newHtml) {
    global $isCli;
    if ($isCli) {
        echo "─── pid=$pid lang=$lang ─── $name\n";
        echo "[OLD] " . mb_substr(preg_replace('/\s+/u', ' ', $oldHtml), 0, 240, 'UTF-8') . "\n";
        echo "[NEW] " . mb_substr(preg_replace('/\s+/u', ' ', $newHtml), 0, 240, 'UTF-8') . "\n\n";
    } else {
        echo '<div class="row"><strong>pid=' . $pid . ' lang=' . $lang . '</strong> — ' . htmlspecialchars($name) . '<br>';
        echo '<div class="old"><b>ANTES:</b><pre>' . htmlspecialchars($oldHtml) . '</pre></div>';
        echo '<div class="new"><b>DESPUÉS:</b><pre>' . htmlspecialchars($newHtml) . '</pre></div>';
        echo '</div>';
    }
    @flush();
}

// ─── Ejecución ─────────────────────────────────────────────────────────────

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_error) { pp("ERROR DB: " . $mysqli->connect_error); exit; }
$mysqli->set_charset('utf8');

$rows = loadMbDescriptions($mysqli, $onlyPids);
pp("Filas a procesar: " . count($rows) . (empty($onlyPids) ? "" : " (filtro pids: " . implode(',', $onlyPids) . ")"));

$changed = 0; $unchanged = 0; $errors = 0;

foreach ($rows as $row) {
    $pid = (int) $row['products_id'];
    $lang = (int) $row['language_id'];
    $name = $row['products_name'];
    $oldHtml = (string) $row['products_description'];
    try {
        $newHtml = mbReformatDescription($oldHtml);
    } catch (\Throwable $e) {
        $errors++; pp("ERROR pid=$pid lang=$lang: " . $e->getMessage()); continue;
    }

    if ($newHtml === $oldHtml) { $unchanged++; continue; }

    ppDiff($pid, $lang, $name, $oldHtml, $newHtml);
    if (!$dryRun) {
        $qNew = $mysqli->real_escape_string($newHtml);
        $ok = $mysqli->query("UPDATE products_description SET products_description='$qNew' WHERE products_id=$pid AND language_id=$lang");
        if (!$ok) { $errors++; pp("UPDATE FAIL pid=$pid lang=$lang: " . $mysqli->error); }
    }
    $changed++;
}

pp("==================== RESUMEN ====================");
pp(($dryRun ? "Cambiarían: " : "Cambiados: ") . $changed);
pp("Sin cambios: " . $unchanged);
pp("Errores: " . $errors);
if ($dryRun) pp("(dry-run, no se ha tocado nada)");
