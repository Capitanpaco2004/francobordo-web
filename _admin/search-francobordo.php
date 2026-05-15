<?php
/**
 * _admin/search-francobordo.php
 *
 * Dashboard del motor de búsqueda interno Francobordo (Meilisearch).
 * Muestra estado del índice, cron, test de búsqueda y operaciones de re-index.
 */
require 'includes/application_top.php';

// ---------- helpers ----------
function fb_env_path() {
    return '/home/francobordo/_search/.env';
}

function fb_load_env() {
    $env = ['MEILI_URL' => '', 'MEILI_KEY' => ''];
    $path = fb_env_path();
    if (!is_readable($path)) return $env;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos($line, '#') === 0) continue;
        if (!strpos($line, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $v = trim($v, "'\"");
        $env[$k] = $v;
    }
    return $env;
}

function fb_meili_call($method, $path, $body = null) {
    $env = fb_load_env();
    if (!$env['MEILI_URL'] || !$env['MEILI_KEY']) {
        return ['error' => 'MEILI_URL / MEILI_KEY no configurados en ' . fb_env_path(), 'code' => 0];
    }
    $url = rtrim($env['MEILI_URL'], '/') . $path;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $env['MEILI_KEY'],
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    if ($resp === false) return ['error' => $err, 'code' => 0];
    $j = json_decode($resp, true);
    return ['data' => $j, 'code' => $code, 'raw' => $resp];
}

function fb_format_ts($ts) {
    if (!$ts) return '—';
    if (is_string($ts)) {
        $ts = strtotime($ts);
    }
    if ($ts <= 0) return '—';
    $diff = time() - $ts;
    if ($diff < 60)    return 'hace ' . $diff . 's';
    if ($diff < 3600)  return 'hace ' . floor($diff / 60) . ' min';
    if ($diff < 86400) return 'hace ' . floor($diff / 3600) . ' h';
    return date('Y-m-d H:i', $ts);
}

function fb_read_log_tail($n = 30) {
    $log = '/home/francobordo/_search/logs/indexer.log';
    if (!is_readable($log)) return [];
    // Pure PHP tail: lee desde el final por bloques de 8KB
    $fp = @fopen($log, 'r');
    if (!$fp) return [];
    fseek($fp, 0, SEEK_END);
    $size = ftell($fp);
    if ($size === 0) { fclose($fp); return []; }
    $buf  = '';
    $line_count = 0;
    $pos  = $size;
    while ($pos > 0 && $line_count <= $n) {
        $read = min(8192, $pos);
        $pos -= $read;
        fseek($fp, $pos);
        $buf = fread($fp, $read) . $buf;
        $line_count = substr_count($buf, "\n");
    }
    fclose($fp);
    $lines = preg_split('/\r?\n/', trim($buf));
    return array_slice($lines, -$n);
}

function fb_trigger($which) {
    $base = '/home/francobordo/_search';
    $path = $base . '/.trigger-' . $which;
    return @file_put_contents($path, date('c') . "\n") !== false;
}

function fb_flag_toggle($flag_path, $enabled) {
    if ($enabled) {
        return !file_exists($flag_path) || @unlink($flag_path);
    } else {
        return @file_put_contents($flag_path, date('c') . "\n") !== false;
    }
}

function fb_learner_disabled_flag()    { return '/home/francobordo/_search/.learner-disabled'; }
function fb_learner_is_enabled()       { return !file_exists(fb_learner_disabled_flag()); }
function fb_learner_set_enabled($en)   { return fb_flag_toggle(fb_learner_disabled_flag(), $en); }

function fb_popularity_disabled_flag() { return '/home/francobordo/_search/.popularity-disabled'; }
function fb_popularity_is_enabled()    { return !file_exists(fb_popularity_disabled_flag()); }
function fb_popularity_set_enabled($en){ return fb_flag_toggle(fb_popularity_disabled_flag(), $en); }

function fb_read_log_tail_path($path, $n = 30) {
    if (!is_readable($path)) return [];
    $fp = @fopen($path, 'r');
    if (!$fp) return [];
    fseek($fp, 0, SEEK_END);
    $size = ftell($fp);
    if ($size === 0) { fclose($fp); return []; }
    $buf = ''; $line_count = 0; $pos = $size;
    while ($pos > 0 && $line_count <= $n) {
        $read = min(8192, $pos); $pos -= $read;
        fseek($fp, $pos);
        $buf = fread($fp, $read) . $buf;
        $line_count = substr_count($buf, "\n");
    }
    fclose($fp);
    return array_slice(preg_split('/\r?\n/', trim($buf)), -$n);
}

function fb_parse_last_run_marker($lines, $marker) {
    // Busca línea tipo "[YYYY-MM-DD HH:MM:SS] ======== marker start ========"
    $last = null;
    foreach (array_reverse($lines) as $line) {
        if (preg_match('#^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\].*' . preg_quote($marker, '#') . '\s+(start|end)#i', $line, $m)) {
            $last = ['ts' => $m[1], 'phase' => strtolower($m[2])];
            break;
        }
    }
    return $last;
}

function fb_apply_synonym($q_norm, $candidate) {
    // Lee sinónimos actuales de Meili, añade el par bidireccional, los reescribe.
    $cur = fb_meili_call('GET', '/indexes/products/settings/synonyms');
    $syns = is_array($cur['data'] ?? null) ? $cur['data'] : [];
    $syns[$q_norm]   = array_values(array_unique(array_merge($syns[$q_norm]   ?? [], [$candidate])));
    $syns[$candidate] = array_values(array_unique(array_merge($syns[$candidate] ?? [], [$q_norm])));
    $r = fb_meili_call('PUT', '/indexes/products/settings/synonyms', $syns);
    return $r['code'] >= 200 && $r['code'] < 300;
}

function fb_parse_last_run($log_lines) {
    // formato: "[2026-05-12 16:10:32] -------- delta start --------"
    $last = null;
    foreach (array_reverse($log_lines) as $line) {
        if (preg_match('#^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\].*(delta|FULL)\s+(start|end)#i', $line, $m)) {
            $last = ['ts' => $m[1], 'mode' => strtolower($m[2]), 'phase' => strtolower($m[3])];
            break;
        }
    }
    return $last;
}

// ---------- POST actions ----------
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'reindex_delta':
            $ok = fb_trigger('delta');
            $flash = $ok
                ? ['type' => 'success', 'msg' => 'Delta encolado. Lo recoge el cron en <60s. Mira el log abajo.']
                : ['type' => 'error',   'msg' => 'No se pudo crear el trigger file. Revisa permisos en /home/francobordo/_search/'];
            break;
        case 'reindex_full':
            $ok = fb_trigger('full');
            $flash = $ok
                ? ['type' => 'success', 'msg' => 'Reindex completo encolado. Lo recoge el cron en <60s. Tarda ~1 min.']
                : ['type' => 'error',   'msg' => 'No se pudo crear el trigger file. Revisa permisos en /home/francobordo/_search/'];
            break;
        case 'learner_toggle':
            $now_enabled = fb_learner_is_enabled();
            if (fb_learner_set_enabled(!$now_enabled)) {
                $flash = ['type' => 'success',
                    'msg' => $now_enabled
                        ? 'Aprendiz de sinónimos DESACTIVADO (no correrá el cron 03:50)'
                        : 'Aprendiz de sinónimos ACTIVADO'];
            } else {
                $flash = ['type' => 'error', 'msg' => 'No se pudo cambiar el flag'];
            }
            break;
        case 'popularity_toggle':
            $now_enabled = fb_popularity_is_enabled();
            if (fb_popularity_set_enabled(!$now_enabled)) {
                $flash = ['type' => 'success',
                    'msg' => $now_enabled
                        ? 'Scorer de popularidad DESACTIVADO (no correrá el cron 03:25; los clicks siguen logueándose)'
                        : 'Scorer de popularidad ACTIVADO'];
            } else {
                $flash = ['type' => 'error', 'msg' => 'No se pudo cambiar el flag'];
            }
            break;
        case 'suggestion_approve':
        case 'suggestion_reject':
            $sid = (int)($_POST['sid'] ?? 0);
            $is_ajax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
            $ajax_ok = false;
            $ajax_msg = '';
            if ($sid > 0) {
                $row = tep_db_fetch_array(tep_db_query(
                    "SELECT q_norm, candidate FROM synonym_suggestions WHERE id=" . $sid));
                if ($row) {
                    if ($_POST['action'] === 'suggestion_approve') {
                        if (fb_apply_synonym($row['q_norm'], $row['candidate'])) {
                            tep_db_query("UPDATE synonym_suggestions
                                SET status='approved', reviewed_at=NOW(),
                                    reviewed_by=" . (int)$_SESSION['admin']['id'] . "
                                WHERE id=" . $sid);
                            $ajax_ok = true;
                            $ajax_msg = "Sinónimo aplicado: {$row['q_norm']} ↔ {$row['candidate']}";
                        } else {
                            $ajax_msg = 'Error aplicando sinónimo a Meili';
                        }
                    } else {
                        tep_db_query("UPDATE synonym_suggestions
                            SET status='rejected', reviewed_at=NOW(),
                                reviewed_by=" . (int)$_SESSION['admin']['id'] . "
                            WHERE id=" . $sid);
                        $ajax_ok = true;
                        $ajax_msg = 'Rechazado';
                    }
                } else {
                    $ajax_msg = 'Sugerencia no encontrada';
                }
            }
            if ($is_ajax) {
                // Respuesta JSON — el JS quita el chip sin recargar la página
                header('Content-Type: application/json');
                echo json_encode(['ok' => $ajax_ok, 'msg' => $ajax_msg]);
                exit;
            }
            $flash = ['type' => $ajax_ok ? 'success' : 'error', 'msg' => $ajax_msg];
            break;
        case 'add_synonym':
            $key = strtolower(trim($_POST['syn_key']  ?? ''));
            $val = strtolower(trim($_POST['syn_val'] ?? ''));
            if ($key && $val) {
                $current = fb_meili_call('GET', '/indexes/products/settings/synonyms');
                $syns = is_array($current['data'] ?? null) ? $current['data'] : [];
                $syns[$key]    = array_unique(array_merge($syns[$key]    ?? [], [$val]));
                $syns[$val]    = array_unique(array_merge($syns[$val]    ?? [], [$key]));
                $r = fb_meili_call('PUT', '/indexes/products/settings/synonyms', $syns);
                if ($r['code'] >= 200 && $r['code'] < 300) {
                    $flash = ['type' => 'success', 'msg' => "Sinónimo añadido: $key ↔ $val"];
                } else {
                    $flash = ['type' => 'error', 'msg' => 'Error: ' . ($r['raw'] ?? json_encode($r))];
                }
            }
            break;
    }
}

// ---------- GET data ----------
$env    = fb_load_env();
$health = fb_meili_call('GET', '/health');
$stats  = fb_meili_call('GET', '/indexes/products/stats');
$syns   = fb_meili_call('GET', '/indexes/products/settings/synonyms');
$tasks  = fb_meili_call('GET', '/tasks?limit=5');

// ---------------- DATA: SINÓNIMOS ----------------
$suggestions_by_query = [];
$q_sug = tep_db_query("
    SELECT id, q_norm, candidate, confidence, occurrences, sample_pids, created_at
    FROM synonym_suggestions
    WHERE status='pending'
    ORDER BY occurrences DESC, q_norm, confidence DESC
    LIMIT 200");
while ($r = tep_db_fetch_array($q_sug)) {
    $suggestions_by_query[$r['q_norm']][] = $r;
}
$num_pending = array_sum(array_map('count', $suggestions_by_query));

$learner_log_lines = fb_read_log_tail_path('/home/francobordo/_search/logs/synonym_learner.log', 50);
$learner_last_run = fb_parse_last_run_marker($learner_log_lines, 'synonym learner');

// Aprobados y rechazados totales
$counts_sug = tep_db_fetch_array(tep_db_query("
    SELECT
      SUM(status='approved') AS approved,
      SUM(status='rejected') AS rejected,
      SUM(status='pending')  AS pending
    FROM synonym_suggestions"));

// ---------------- DATA: POPULARITY ----------------
$pop_log_lines = fb_read_log_tail_path('/home/francobordo/_search/logs/popularity.log', 50);
$pop_last_run = fb_parse_last_run_marker($pop_log_lines, 'popularity scorer');

$pop_stats = tep_db_fetch_array(tep_db_query("
    SELECT COUNT(*) AS productos_con_clicks, MAX(score) AS max_score, AVG(score) AS avg_score,
           SUM(clicks_7d) AS total_c7, SUM(clicks_30d) AS total_c30
    FROM product_popularity"));

$top_popular_query = tep_db_query("
    SELECT pp.pid, pp.score, pp.clicks_7d, pp.clicks_30d, pd.products_name
    FROM product_popularity pp
    LEFT JOIN products_description pd ON pd.products_id = pp.pid AND pd.language_id = 3
    WHERE pp.score > 0
    ORDER BY pp.score DESC
    LIMIT 10");
$top_popular = [];
while ($r = tep_db_fetch_array($top_popular_query)) $top_popular[] = $r;

// ---------------- DATA: MÉTRICAS DE BÚSQUEDA ----------------
$metrics = tep_db_fetch_array(tep_db_query("
    SELECT
      COUNT(*) AS total,
      SUM(ts >= DATE_SUB(NOW(), INTERVAL 1  DAY)) AS d1,
      SUM(ts >= DATE_SUB(NOW(), INTERVAL 7  DAY)) AS d7,
      SUM(ts >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS d30,
      SUM(top_score < 0.3 AND ts >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS low_score_7d
    FROM search_events"));

$top_queries_query = tep_db_query("
    SELECT q_norm, COUNT(*) AS n, ROUND(AVG(top_score), 2) AS avg_score, MAX(q) AS sample
    FROM search_events
    WHERE ts >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY q_norm
    ORDER BY n DESC
    LIMIT 10");
$top_queries = [];
while ($r = tep_db_fetch_array($top_queries_query)) $top_queries[] = $r;

$low_score_query = tep_db_query("
    SELECT q_norm, COUNT(*) AS n, ROUND(AVG(top_score), 2) AS avg_score, MAX(q) AS sample
    FROM search_events
    WHERE ts >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND top_score IS NOT NULL
    GROUP BY q_norm
    HAVING n >= 2 AND avg_score < 0.4
    ORDER BY n DESC
    LIMIT 10");
$low_score_queries = [];
while ($r = tep_db_fetch_array($low_score_query)) $low_score_queries[] = $r;

$clicks_total = tep_db_fetch_array(tep_db_query("
    SELECT
      COUNT(*) AS total,
      SUM(ts >= DATE_SUB(NOW(), INTERVAL 1 DAY)) AS d1,
      SUM(ts >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS d7
    FROM click_events"));

$log_lines = fb_read_log_tail(30);
$last_run  = fb_parse_last_run($log_lines);

$is_up    = ($health['code'] ?? 0) === 200 && ($health['data']['status'] ?? '') === 'available';
$num_docs = $stats['data']['numberOfDocuments'] ?? 0;
$indexing = $stats['data']['isIndexing'] ?? false;

// ---------- test search (AJAX-style same page) ----------
$test_q = $_GET['q'] ?? '';
$test_result = null;
if ($test_q !== '') {
    $test_result = fb_meili_call('POST', '/indexes/products/search', [
        'q' => $test_q, 'limit' => 10, 'facets' => ['brand', 'category_lvl0'],
    ]);
}
?>
<?php require THEME . 'html/header.php'; ?>

<style>
  .fb-wrap { max-width: 1200px; margin: 16px auto; padding: 0 16px; }
  .fb-brand { display: flex; align-items: center; gap: 16px; margin-bottom: 24px; padding: 16px; background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
  .fb-brand img { height: 48px; }
  .fb-brand h1 { margin: 0; font-size: 24px; color: #0084be; }
  .fb-brand h1 small { color: #6b7280; font-weight: 400; font-size: 14px; display: block; }

  .fb-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 24px; }
  .fb-card { background: white; border-radius: 8px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
  .fb-card h3 { margin: 0 0 12px; font-size: 13px; text-transform: uppercase; color: #6b7280; letter-spacing: .05em; }
  .fb-stat { font-size: 28px; font-weight: 700; color: #1f2937; }
  .fb-stat small { font-size: 13px; font-weight: 400; color: #6b7280; }

  .fb-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
  .fb-ok   { background: #d1fae5; color: #065f46; }
  .fb-down { background: #fee2e2; color: #991b1b; }
  .fb-warn { background: #fef3c7; color: #92400e; }

  .fb-section { background: white; border-radius: 8px; padding: 16px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
  .fb-section h2 { margin: 0 0 12px; font-size: 16px; color: #1f2937; border-bottom: 1px solid #e5e7eb; padding-bottom: 8px; }

  .fb-actions { display: flex; flex-wrap: wrap; gap: 8px; }
  .fb-btn { padding: 8px 16px; border: 0; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; }
  .fb-btn-primary { background: #0084be; color: white; }
  .fb-btn-primary:hover { background: #006fa0; }
  .fb-btn-ghost { background: white; border: 1px solid #d1d5db; color: #1f2937; }
  .fb-btn-warn { background: #f59e0b; color: white; }

  .fb-log { background: #1f2937; color: #e5e7eb; padding: 12px; border-radius: 6px; font-family: ui-monospace, monospace; font-size: 12px; max-height: 240px; overflow-y: auto; white-space: pre-wrap; }

  .fb-test { display: grid; grid-template-columns: 1fr auto; gap: 8px; margin-bottom: 12px; }
  .fb-test input { padding: 10px 14px; font-size: 16px; border: 1px solid #d1d5db; border-radius: 6px; }
  .fb-results { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; }
  .fb-result { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px; font-size: 13px; }
  .fb-result img { width: 100%; aspect-ratio: 1; object-fit: contain; background: white; }
  .fb-result .t { font-weight: 500; margin: 6px 0 4px; line-height: 1.3; min-height: 32px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  .fb-result .p { font-weight: 700; color: #0084be; }
  .fb-result .b { color: #6b7280; font-size: 11px; text-transform: uppercase; }

  .fb-flash { padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; }
  .fb-flash-success { background: #d1fae5; color: #065f46; }
  .fb-flash-error { background: #fee2e2; color: #991b1b; }

  .fb-syn-list { display: flex; flex-wrap: wrap; gap: 6px; }
  .fb-syn-chip { background: #f3f4f6; padding: 4px 10px; border-radius: 999px; font-size: 12px; }
  .fb-syn-chip b { color: #0084be; }

  .fb-syn-form { display: grid; grid-template-columns: 1fr 1fr auto; gap: 8px; margin-top: 12px; }
  .fb-syn-form input { padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 13px; }

  @media (max-width: 640px) {
    .fb-brand { flex-direction: column; align-items: flex-start; }
    .fb-brand h1 { font-size: 20px; }
    .fb-grid { grid-template-columns: 1fr; }
    .fb-stat { font-size: 22px; }
    .fb-syn-form { grid-template-columns: 1fr; }
  }
</style>

<div class="fb-wrap">

  <div class="fb-brand">
    <img src="/theme/web/logo-trans.png" alt="Francobordo">
    <div>
      <h1>Buscador Francobordo
        <small>Motor de búsqueda interno · Meilisearch en 192.168.1.112 · indexer en nic1</small>
      </h1>
    </div>
    <div style="margin-left: auto;">
      <?php if ($is_up): ?>
        <span class="fb-badge fb-ok">● Activo</span>
      <?php else: ?>
        <span class="fb-badge fb-down">● No responde</span>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="fb-flash fb-flash-<?= htmlspecialchars($flash['type']) ?>">
      <?= htmlspecialchars($flash['msg']) ?>
      <?php if (!empty($flash['log'])): ?>
        <pre class="fb-log" style="margin-top: 8px;"><?= htmlspecialchars($flash['log']) ?></pre>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- STATS GRID -->
  <div class="fb-grid">
    <div class="fb-card">
      <h3>Productos indexados</h3>
      <div class="fb-stat"><?= number_format($num_docs, 0, ',', '.') ?> <small>docs</small></div>
    </div>
    <div class="fb-card">
      <h3>Estado de indexación</h3>
      <div class="fb-stat">
        <?php if ($indexing): ?>
          <span class="fb-badge fb-warn">Indexando…</span>
        <?php else: ?>
          <span class="fb-badge fb-ok">En reposo</span>
        <?php endif; ?>
      </div>
    </div>
    <div class="fb-card">
      <h3>Último cron</h3>
      <div class="fb-stat">
        <?php if ($last_run): ?>
          <?= htmlspecialchars(strtoupper($last_run['mode'])) ?>
          <small><?= fb_format_ts($last_run['ts']) ?></small>
        <?php else: ?>
          —
        <?php endif; ?>
      </div>
    </div>
    <div class="fb-card">
      <h3>Cron schedule</h3>
      <div class="fb-stat" style="font-size: 16px;">
        Delta cada 5 min<br>
        <small>Full diario 03:30</small>
      </div>
    </div>
  </div>

  <!-- SUGERENCIAS DE SINÓNIMOS (Aprendiz nocturno) -->
  <?php $learner_on = fb_learner_is_enabled(); ?>
  <div class="fb-section">
    <h2>🧠 Aprendiz de sinónimos
      <?php if ($num_pending > 0): ?>
        · <?= $num_pending ?> pendientes
      <?php endif; ?>
      <span style="font-size:12px;font-weight:normal;color:var(--muted);"> · queries que tus clientes hicieron y no encontraron</span>
    </h2>

    <!-- Toggle + stats del aprendiz de sinónimos -->
    <div style="display:flex;align-items:center;gap:12px;background:#f9fafb;padding:10px 14px;border-radius:6px;margin-bottom:8px;">
      <span style="font-size:13px;">
        Cron 03:50:
        <?php if ($learner_on): ?>
          <span class="fb-badge fb-ok">● Activado</span>
        <?php else: ?>
          <span class="fb-badge fb-down">● Desactivado</span>
        <?php endif; ?>
      </span>
      <span style="font-size:12px;color:var(--muted);">
        última ejecución:
        <?php if ($learner_last_run): ?>
          <b><?= htmlspecialchars($learner_last_run['ts']) ?></b> (<?= $learner_last_run['phase'] ?>)
        <?php else: ?>
          — nunca
        <?php endif; ?>
      </span>
      <span style="font-size:12px;color:var(--muted);">
        · histórico: <b><?= (int)$counts_sug['approved'] ?></b> aprobadas, <b><?= (int)$counts_sug['rejected'] ?></b> rechazadas, <b><?= (int)$counts_sug['pending'] ?></b> pendientes
      </span>
      <form method="POST" style="margin-left:auto;">
        <input type="hidden" name="<?= tep_session_name() ?>" value="<?= tep_session_id() ?>">
        <button class="fb-btn fb-btn-ghost" name="action" value="learner_toggle" type="submit">
          <?= $learner_on ? 'Desactivar' : 'Activar' ?>
        </button>
      </form>
    </div>

    <?php if ($num_pending === 0): ?>
      <p style="color:var(--muted);font-size:13px;">
        <?php if ($learner_on): ?>
          No hay sugerencias pendientes ahora mismo. El cron analiza las búsquedas de los últimos 7 días cada noche.
        <?php else: ?>
          No habrá nuevas sugerencias hasta que reactives el cron.
        <?php endif; ?>
      </p>
    <?php endif; ?>
    <?php if ($num_pending > 0): ?>
      <details>
        <summary style="cursor:pointer;padding:10px 14px;background:#eef6fc;border-radius:6px;font-size:14px;font-weight:600;color:#0084be;user-select:none;">
          Ver <?= count($suggestions_by_query) ?> queries con <?= $num_pending ?> sugerencias de sinónimo
        </summary>
        <div style="margin-top:10px;">
          <?php foreach ($suggestions_by_query as $qnorm => $rows): ?>
            <details class="fb-sug-query" style="background:#f9fafb;border-radius:6px;margin-bottom:6px;">
              <summary style="cursor:pointer;padding:10px 12px;font-size:14px;user-select:none;">
                Cliente buscó: <b><?= htmlspecialchars($qnorm) ?></b>
                <span style="color:var(--muted);font-size:12px;">
                  (<?= (int)$rows[0]['occurrences'] ?>x · <span class="fb-sug-count"><?= count($rows) ?></span> candidatos)
                </span>
              </summary>
              <div class="fb-sug-chips" style="display:flex;flex-wrap:wrap;gap:8px;padding:0 12px 12px;">
                <?php foreach ($rows as $r): ?>
                  <form method="POST" class="fb-sug-form" style="display:inline;">
                    <input type="hidden" name="<?= tep_session_name() ?>" value="<?= tep_session_id() ?>">
                    <input type="hidden" name="sid" value="<?= (int)$r['id'] ?>">
                    <span style="display:inline-flex;align-items:center;gap:4px;background:white;border:1px solid var(--border);border-radius:999px;padding:4px 10px;transition:opacity .2s;">
                      <span><?= htmlspecialchars($qnorm) ?> ↔ <b><?= htmlspecialchars($r['candidate']) ?></b></span>
                      <span style="color:#9ca3af;font-size:11px;">(<?= round($r['confidence']*100) ?>%)</span>
                      <button name="action" value="suggestion_approve" type="submit"
                              title="Aplicar este sinónimo en Meili"
                              style="background:#10b981;color:white;border:0;border-radius:4px;padding:2px 8px;cursor:pointer;font-size:12px;">✓</button>
                      <button name="action" value="suggestion_reject" type="submit"
                              title="Descartar"
                              style="background:#ef4444;color:white;border:0;border-radius:4px;padding:2px 8px;cursor:pointer;font-size:12px;">✗</button>
                    </span>
                  </form>
                <?php endforeach; ?>
              </div>
            </details>
          <?php endforeach; ?>
        </div>
      </details>
      <p style="color:var(--muted);font-size:12px;margin-top:8px;">
        Aprobar añade el sinónimo bidireccional a Meili al instante. Rechazar marca la sugerencia como descartada (no volverá a aparecer).
      </p>
    <?php endif; ?>
  </div>

  <!-- 📊 SCORER DE POPULARIDAD (LTR Nivel 1) -->
  <?php $pop_on = fb_popularity_is_enabled(); ?>
  <div class="fb-section">
    <h2>📊 Scorer de popularidad
      <span style="font-size:12px;font-weight:normal;color:var(--muted);"> · clicks de clientes que dan boost a productos buenos en el ranking</span>
    </h2>
    <div style="display:flex;align-items:center;gap:12px;background:#f9fafb;padding:10px 14px;border-radius:6px;margin-bottom:14px;flex-wrap:wrap;">
      <span style="font-size:13px;">
        Cron 03:25:
        <?php if ($pop_on): ?>
          <span class="fb-badge fb-ok">● Activado</span>
        <?php else: ?>
          <span class="fb-badge fb-down">● Desactivado</span>
        <?php endif; ?>
      </span>
      <span style="font-size:12px;color:var(--muted);">
        última ejecución:
        <?php if ($pop_last_run): ?>
          <b><?= htmlspecialchars($pop_last_run['ts']) ?></b> (<?= $pop_last_run['phase'] ?>)
        <?php else: ?>
          — nunca
        <?php endif; ?>
      </span>
      <span style="font-size:12px;color:var(--muted);">
        · clicks totales: <b><?= number_format((int)$clicks_total['total'], 0, ',', '.') ?></b>
        (24h: <?= (int)$clicks_total['d1'] ?> · 7d: <?= (int)$clicks_total['d7'] ?>)
      </span>
      <span style="font-size:12px;color:var(--muted);">
        · productos con score: <b><?= (int)$pop_stats['productos_con_clicks'] ?></b>
      </span>
      <form method="POST" style="margin-left:auto;">
        <input type="hidden" name="<?= tep_session_name() ?>" value="<?= tep_session_id() ?>">
        <button class="fb-btn fb-btn-ghost" name="action" value="popularity_toggle" type="submit">
          <?= $pop_on ? 'Desactivar' : 'Activar' ?>
        </button>
      </form>
    </div>

    <?php if (!empty($top_popular)): ?>
      <h3 style="font-size:13px;text-transform:uppercase;color:var(--muted);margin:14px 0 8px;">Top 10 productos por popularidad</h3>
      <table style="width:100%;font-size:13px;border-collapse:collapse;">
        <thead>
          <tr style="text-align:left;color:var(--muted);border-bottom:1px solid var(--border);">
            <th style="padding:6px;">PID</th><th>Producto</th><th style="text-align:right;">Score</th><th style="text-align:right;">7d</th><th style="text-align:right;">30d</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($top_popular as $tp): ?>
            <tr style="border-bottom:1px solid #f3f4f6;">
              <td style="padding:6px;"><?= (int)$tp['pid'] ?></td>
              <td><?= htmlspecialchars(mb_substr($tp['products_name'] ?? '', 0, 70)) ?></td>
              <td style="text-align:right;"><b><?= number_format($tp['score'], 2, ',', '.') ?></b></td>
              <td style="text-align:right;"><?= (int)$tp['clicks_7d'] ?></td>
              <td style="text-align:right;"><?= (int)$tp['clicks_30d'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p style="color:var(--muted);font-size:13px;">Aún no hay clicks suficientes — esperando que clientes empiecen a usar el buscador y a hacer clic en productos.</p>
    <?php endif; ?>
  </div>

  <!-- 📈 MÉTRICAS DE BÚSQUEDA -->
  <div class="fb-section">
    <h2>📈 Métricas de búsqueda
      <span style="font-size:12px;font-weight:normal;color:var(--muted);"> · qué buscan tus clientes y qué tal se les responde</span>
    </h2>
    <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:14px;">
      <div style="background:#f9fafb;padding:10px 16px;border-radius:6px;font-size:13px;">
        Búsquedas hoy: <b><?= number_format((int)$metrics['d1'], 0, ',', '.') ?></b>
      </div>
      <div style="background:#f9fafb;padding:10px 16px;border-radius:6px;font-size:13px;">
        Búsquedas últimos 7 días: <b><?= number_format((int)$metrics['d7'], 0, ',', '.') ?></b>
      </div>
      <div style="background:#f9fafb;padding:10px 16px;border-radius:6px;font-size:13px;">
        Búsquedas últimos 30 días: <b><?= number_format((int)$metrics['d30'], 0, ',', '.') ?></b>
      </div>
      <div style="background:#fef3c7;padding:10px 16px;border-radius:6px;font-size:13px;">
        Con match débil (score<0.3, 7d): <b><?= number_format((int)$metrics['low_score_7d'], 0, ',', '.') ?></b>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
      <!-- Top búsquedas -->
      <div>
        <h3 style="font-size:13px;text-transform:uppercase;color:var(--muted);margin:0 0 8px;">🔥 Top búsquedas (7 días)</h3>
        <?php if ($top_queries): ?>
          <table style="width:100%;font-size:13px;border-collapse:collapse;">
            <thead>
              <tr style="text-align:left;color:var(--muted);border-bottom:1px solid var(--border);">
                <th style="padding:6px;">Query</th><th style="text-align:right;">Veces</th><th style="text-align:right;">Score</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($top_queries as $tq): ?>
                <tr style="border-bottom:1px solid #f3f4f6;">
                  <td style="padding:6px;"><?= htmlspecialchars(mb_substr($tq['sample'], 0, 40)) ?></td>
                  <td style="text-align:right;"><b><?= (int)$tq['n'] ?></b></td>
                  <td style="text-align:right;color:<?= $tq['avg_score'] >= 0.7 ? '#10b981' : ($tq['avg_score'] >= 0.4 ? '#f59e0b' : '#ef4444') ?>;">
                    <?= $tq['avg_score'] ?? '?' ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p style="color:var(--muted);font-size:13px;">Sin datos aún.</p>
        <?php endif; ?>
      </div>
      <!-- Queries con score bajo (oportunidades) -->
      <div>
        <h3 style="font-size:13px;text-transform:uppercase;color:var(--muted);margin:0 0 8px;">⚠ Queries con match débil (oportunidades)</h3>
        <?php if ($low_score_queries): ?>
          <table style="width:100%;font-size:13px;border-collapse:collapse;">
            <thead>
              <tr style="text-align:left;color:var(--muted);border-bottom:1px solid var(--border);">
                <th style="padding:6px;">Query</th><th style="text-align:right;">Veces</th><th style="text-align:right;">Score</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($low_score_queries as $lq): ?>
                <tr style="border-bottom:1px solid #f3f4f6;">
                  <td style="padding:6px;"><?= htmlspecialchars(mb_substr($lq['sample'], 0, 40)) ?></td>
                  <td style="text-align:right;"><b><?= (int)$lq['n'] ?></b></td>
                  <td style="text-align:right;color:#ef4444;"><?= $lq['avg_score'] ?? '?' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <p style="color:var(--muted);font-size:11px;margin-top:6px;">El aprendiz nocturno propondrá sinónimos para estas queries — si no aparecen, considera añadirlos a mano abajo.</p>
        <?php else: ?>
          <p style="color:var(--muted);font-size:13px;">Sin queries problemáticas ahora mismo. 🎉</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- TEST SEARCH -->
  <div class="fb-section">
    <h2>🔍 Probar búsqueda</h2>
    <form method="GET" class="fb-test">
      <input type="hidden" name="<?= tep_session_name() ?>" value="<?= tep_session_id() ?>">
      <input type="search" name="q" placeholder="Escribe una consulta… (ej: chaleco, ancla, motor honda)"
             value="<?= htmlspecialchars($test_q) ?>" autofocus>
      <button class="fb-btn fb-btn-primary" type="submit">Buscar</button>
    </form>

    <?php if ($test_result && isset($test_result['data']['hits'])): ?>
      <p style="color: #6b7280; font-size: 13px;">
        <b><?= number_format($test_result['data']['estimatedTotalHits'] ?? 0, 0, ',', '.') ?></b> resultados
        en <b><?= $test_result['data']['processingTimeMs'] ?? '?' ?>ms</b>
      </p>
      <div class="fb-results">
        <?php foreach ($test_result['data']['hits'] as $h): ?>
          <div class="fb-result">
            <?php if (!empty($h['image'])): ?>
              <img src="<?= htmlspecialchars($h['image']) ?>" onerror="this.style.display='none'">
            <?php endif; ?>
            <div class="b"><?= htmlspecialchars($h['brand'] ?? '') ?></div>
            <div class="t"><?= htmlspecialchars($h['title'] ?? '') ?></div>
            <div class="p"><?= number_format($h['price'] ?? 0, 2, ',', '.') ?> €</div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- OPERATIONS -->
  <div class="fb-section">
    <h2>⚙ Operaciones</h2>
    <form method="POST" class="fb-actions" onsubmit="return confirm('¿Confirmar?');">
      <input type="hidden" name="<?= tep_session_name() ?>" value="<?= tep_session_id() ?>">
      <button class="fb-btn fb-btn-primary" name="action" value="reindex_delta">
        Reindex delta (últimos 6 min)
      </button>
      <button class="fb-btn fb-btn-warn" name="action" value="reindex_full">
        Reindex completo (background)
      </button>
    </form>
  </div>

  <!-- SYNONYMS MANAGER -->
  <div class="fb-section">
    <h2>📚 Sinónimos</h2>
    <div class="fb-syn-list">
      <?php if (is_array($syns['data'] ?? null)):
        // Show as unique bidirectional pairs to avoid duplicates
        $seen = [];
        foreach ($syns['data'] as $k => $vs) {
          foreach ((array)$vs as $v) {
            $pair = $k < $v ? "$k|$v" : "$v|$k";
            if (isset($seen[$pair])) continue;
            $seen[$pair] = true; ?>
            <span class="fb-syn-chip"><b><?= htmlspecialchars($k) ?></b> ↔ <?= htmlspecialchars($v) ?></span>
      <?php } } else: ?>
        <i style="color: #6b7280;">No hay sinónimos configurados</i>
      <?php endif; ?>
    </div>
    <form method="POST" class="fb-syn-form">
      <input type="hidden" name="<?= tep_session_name() ?>" value="<?= tep_session_id() ?>">
      <input type="hidden" name="action" value="add_synonym">
      <input type="text" name="syn_key" placeholder="palabra 1 (ej: chaleco)" required>
      <input type="text" name="syn_val" placeholder="palabra 2 (ej: salvavidas)" required>
      <button class="fb-btn fb-btn-primary" type="submit">Añadir sinónimo</button>
    </form>
  </div>

  <!-- LOG -->
  <div class="fb-section">
    <h2>📜 Log del indexer (últimas 30 líneas)</h2>
    <?php if ($log_lines): ?>
      <pre class="fb-log"><?php
        foreach (array_slice($log_lines, -30) as $line) {
          echo htmlspecialchars($line) . "\n";
        }
      ?></pre>
    <?php else: ?>
      <i style="color: #6b7280;">No hay log todavía. El próximo cron lo creará.</i>
    <?php endif; ?>
  </div>

  <!-- RECENT TASKS -->
  <div class="fb-section">
    <h2>📋 Últimas 5 tareas Meilisearch</h2>
    <?php if (!empty($tasks['data']['results'])): ?>
      <table style="width: 100%; font-size: 13px; border-collapse: collapse;">
        <thead>
          <tr style="text-align: left; color: #6b7280; border-bottom: 1px solid #e5e7eb;">
            <th style="padding: 6px;">UID</th>
            <th>Tipo</th>
            <th>Estado</th>
            <th>Inicio</th>
            <th>Duración</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tasks['data']['results'] as $t): ?>
            <tr style="border-bottom: 1px solid #f3f4f6;">
              <td style="padding: 6px;"><?= $t['uid'] ?></td>
              <td><?= htmlspecialchars($t['type'] ?? '') ?></td>
              <td>
                <?php $st = $t['status'] ?? ''; ?>
                <span class="fb-badge fb-<?= $st === 'succeeded' ? 'ok' : ($st === 'failed' ? 'down' : 'warn') ?>">
                  <?= htmlspecialchars($st) ?>
                </span>
              </td>
              <td><?= fb_format_ts($t['startedAt'] ?? '') ?></td>
              <td><?= htmlspecialchars($t['duration'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <p style="font-size: 12px; color: #9ca3af; text-align: center; margin-top: 32px;">
    Meilisearch <?= htmlspecialchars($env['MEILI_URL'] ?? '') ?> ·
    Documentación interna: <code>memory/francobordo_search_meili.md</code>
  </p>

</div>

<script>
// AJAX para aprobar/rechazar sinónimos sin recargar la página.
(function () {
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.fb-sug-form button[name="action"]');
    if (!btn) return;
    e.preventDefault();

    var form  = btn.closest('.fb-sug-form');
    var chip  = btn.closest('span');
    var query = btn.closest('.fb-sug-query');
    var fd    = new FormData(form);
    fd.set('action', btn.value);   // qué botón se pulsó

    // Deshabilitar ambos botones del chip mientras va la petición
    form.querySelectorAll('button').forEach(function (b) { b.disabled = true; });

    fetch(window.location.href, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: fd,
    })
    .then(function (r) { return r.json(); })
    .then(function (j) {
      if (!j.ok) {
        alert('Error: ' + (j.msg || 'no se pudo procesar'));
        form.querySelectorAll('button').forEach(function (b) { b.disabled = false; });
        return;
      }
      // Fade out + quitar el chip
      chip.style.opacity = '0';
      setTimeout(function () {
        form.remove();
        // Actualizar contador de candidatos de esta query
        if (query) {
          var cntEl   = query.querySelector('.fb-sug-count');
          var remain  = query.querySelectorAll('.fb-sug-form').length;
          if (cntEl) cntEl.textContent = remain;
          // Si la query se queda sin candidatos, quitarla entera
          if (remain === 0) query.remove();
        }
      }, 200);
    })
    .catch(function () {
      alert('Error de red — reintenta');
      form.querySelectorAll('button').forEach(function (b) { b.disabled = false; });
    });
  });
})();
</script>

<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
