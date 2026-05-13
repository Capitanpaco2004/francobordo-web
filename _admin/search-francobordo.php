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

<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
