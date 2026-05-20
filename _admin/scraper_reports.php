<?php
/**
 * Scraper reports — panel anti-scraper.
 * Lee de scraper_blacklist + scraper_observed + scraper_allowlist.
 * Acciones: unban, ban manual, allow, remove_allow, unban_and_allow, cleanup expiradas.
 * Creado 2026-05-19. Allowlist DB añadida 2026-05-19.
 */
require('includes/application_top.php');

$msg = '';
$msg_class = 'info';

// --- HELPER: quien hace la accion ---
function _sr_admin_email() {
    global $login_id;
    static $email = null;
    if ($email !== null) return $email;
    $aid = (int) ($login_id ?? 0);
    if ($aid <= 0) return $email = '';
    $q = tep_db_query("SELECT admin_email_address FROM admin WHERE admin_id = $aid LIMIT 1");
    $r = tep_db_fetch_array($q);
    return $email = ($r['admin_email_address'] ?? '');
}

// --- ACCIONES POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $ip_raw = trim($_POST['ip'] ?? '');

    if ($action === 'unban' && filter_var($ip_raw, FILTER_VALIDATE_IP)) {
        $ip = tep_db_input($ip_raw);
        tep_db_query("DELETE FROM scraper_blacklist WHERE ip = '$ip'");
        tep_db_query("DELETE FROM scraper_observed WHERE ip = '$ip'");
        tep_redirect('scraper_reports.php?msg=unbanned&ip=' . urlencode($ip_raw));
    }

    if ($action === 'unban_and_allow' && filter_var($ip_raw, FILTER_VALIDATE_IP)) {
        $ip = tep_db_input($ip_raw);
        $by = tep_db_input(_sr_admin_email());
        $reason = tep_db_input(substr(trim($_POST['reason'] ?? 'falso positivo'), 0, 128));
        if ($reason === '') $reason = 'falso positivo';
        tep_db_query("DELETE FROM scraper_blacklist WHERE ip = '$ip'");
        tep_db_query("DELETE FROM scraper_observed WHERE ip = '$ip'");
        tep_db_query("INSERT INTO scraper_allowlist (ip, reason, added_by) VALUES ('$ip', '$reason', '$by')
                      ON DUPLICATE KEY UPDATE reason = '$reason', added_by = '$by', added_at = NOW()");
        tep_redirect('scraper_reports.php?msg=unban_allow&ip=' . urlencode($ip_raw));
    }

    if ($action === 'ban' && filter_var($ip_raw, FILTER_VALIDATE_IP)) {
        $ip = tep_db_input($ip_raw);
        $reason = tep_db_input(substr(trim($_POST['reason'] ?? 'manual'), 0, 64));
        $hours = max(1, min(720, (int) ($_POST['hours'] ?? 24)));
        if ($reason === '') $reason = 'manual';
        tep_db_query("INSERT INTO scraper_blacklist (ip, ua, reason, expires_at)
                      VALUES ('$ip', 'manual_ban_via_admin', '$reason', DATE_ADD(NOW(), INTERVAL $hours HOUR))
                      ON DUPLICATE KEY UPDATE expires_at = DATE_ADD(NOW(), INTERVAL $hours HOUR), reason = '$reason', hits = hits + 1");
        tep_redirect('scraper_reports.php?msg=banned&ip=' . urlencode($ip_raw));
    }

    if ($action === 'allow_add' && filter_var($ip_raw, FILTER_VALIDATE_IP)) {
        $ip = tep_db_input($ip_raw);
        $by = tep_db_input(_sr_admin_email());
        $reason = tep_db_input(substr(trim($_POST['reason'] ?? 'manual allowlist'), 0, 128));
        $notes = tep_db_input(substr(trim($_POST['notes'] ?? ''), 0, 255));
        if ($reason === '') $reason = 'manual allowlist';
        tep_db_query("INSERT INTO scraper_allowlist (ip, reason, added_by, notes)
                      VALUES ('$ip', '$reason', '$by', " . ($notes !== '' ? "'$notes'" : "NULL") . ")
                      ON DUPLICATE KEY UPDATE reason = '$reason', added_by = '$by', notes = " . ($notes !== '' ? "'$notes'" : "NULL") . ", added_at = NOW()");
        // por si esta banneada/observada
        tep_db_query("DELETE FROM scraper_blacklist WHERE ip = '$ip'");
        tep_db_query("DELETE FROM scraper_observed WHERE ip = '$ip'");
        tep_redirect('scraper_reports.php?msg=allowed&ip=' . urlencode($ip_raw));
    }

    if ($action === 'allow_remove' && filter_var($ip_raw, FILTER_VALIDATE_IP)) {
        $ip = tep_db_input($ip_raw);
        tep_db_query("DELETE FROM scraper_allowlist WHERE ip = '$ip'");
        tep_redirect('scraper_reports.php?msg=allow_removed&ip=' . urlencode($ip_raw));
    }

    if ($action === 'cleanup_expired') {
        tep_db_query("DELETE FROM scraper_blacklist WHERE expires_at <= NOW()");
        tep_db_query("DELETE FROM scraper_observed WHERE window_start < NOW() - INTERVAL 1 HOUR");
        tep_redirect('scraper_reports.php?msg=cleaned');
    }
}

// --- MENSAJES FLASH ---
if (!empty($_GET['msg'])) {
    $g_ip = htmlspecialchars($_GET['ip'] ?? '', ENT_QUOTES);
    switch ($_GET['msg']) {
        case 'unbanned':      $msg = '&#x2705; IP ' . $g_ip . ' desbaneada.'; $msg_class = 'success'; break;
        case 'unban_allow':   $msg = '&#x2705; IP ' . $g_ip . ' desbaneada y a&ntilde;adida a allowlist.'; $msg_class = 'success'; break;
        case 'banned':        $msg = '&#x1F6AB; IP ' . $g_ip . ' baneada manualmente.'; $msg_class = 'warning'; break;
        case 'allowed':       $msg = '&#x1F7E2; IP ' . $g_ip . ' a&ntilde;adida a allowlist.'; $msg_class = 'success'; break;
        case 'allow_removed': $msg = '&#x26A0; IP ' . $g_ip . ' eliminada de allowlist.'; $msg_class = 'warning'; break;
        case 'cleaned':       $msg = '&#x1F9F9; Filas expiradas eliminadas.'; $msg_class = 'success'; break;
    }
}

// --- STATS ---
$stats_q = tep_db_query("SELECT
    (SELECT COUNT(*) FROM scraper_blacklist WHERE expires_at > NOW()) AS active_bans,
    (SELECT COUNT(*) FROM scraper_blacklist WHERE first_seen > NOW() - INTERVAL 24 HOUR) AS bans_24h,
    (SELECT COUNT(*) FROM scraper_blacklist WHERE first_seen > NOW() - INTERVAL 1 HOUR) AS bans_1h,
    (SELECT COUNT(*) FROM scraper_observed) AS observed_count,
    (SELECT COUNT(*) FROM scraper_allowlist) AS allow_count,
    (SELECT COALESCE(SUM(hits),0) FROM scraper_blacklist WHERE expires_at > NOW()) AS total_hits");
$stats = tep_db_fetch_array($stats_q);

// --- BREAKDOWN por razón ---
$reasons_q = tep_db_query("SELECT reason, COUNT(*) AS c FROM scraper_blacklist WHERE expires_at > NOW() GROUP BY reason ORDER BY c DESC");
$reasons = [];
while ($r = tep_db_fetch_array($reasons_q)) $reasons[] = $r;

// --- BLACKLIST activa (top 200) ---
$bl_q = tep_db_query("SELECT ip, ua, reason, first_seen, last_seen, hits, expires_at,
    TIMESTAMPDIFF(MINUTE, NOW(), expires_at) AS mins_left
    FROM scraper_blacklist WHERE expires_at > NOW()
    ORDER BY first_seen DESC LIMIT 200");

// --- OBSERVED ---
$obs_q = tep_db_query("SELECT ip, hits, window_start,
    TIMESTAMPDIFF(SECOND, window_start, NOW()) AS age_sec
    FROM scraper_observed
    WHERE window_start > NOW() - INTERVAL 5 MINUTE
    ORDER BY hits DESC, window_start DESC LIMIT 50");

// --- ALLOWLIST DB ---
$al_q = tep_db_query("SELECT ip, reason, added_by, added_at, notes
    FROM scraper_allowlist ORDER BY added_at DESC LIMIT 100");

require THEME . 'html/header.php';
?>
<div class="container-fluid" style="padding:20px;">
  <h1 style="margin-bottom:10px;">&#x1F6E1; Scraper Reports</h1>
  <p style="color:#666;margin-bottom:20px;">
    Panel del sistema anti-scraper.
    Backend en <code>includes/scraper_guard.php</code> + honeypot <code>/admin-panel-secret/</code>.
    Auto-ban por rate-limit de UAs antiguos (pool residencial).
  </p>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msg_class ?>" style="padding:10px 15px;border-radius:4px;background:#dff0d8;border:1px solid #ccc;margin-bottom:20px;"><?= $msg ?></div>
  <?php endif; ?>

  <!-- STAT CARDS -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin-bottom:30px;">
    <div style="border:1px solid #ddd;border-radius:6px;padding:15px;background:#fff;">
      <div style="color:#999;font-size:12px;text-transform:uppercase;">Bans activos</div>
      <div style="font-size:32px;font-weight:bold;color:#c00;"><?= (int)$stats['active_bans'] ?></div>
    </div>
    <div style="border:1px solid #ddd;border-radius:6px;padding:15px;background:#fff;">
      <div style="color:#999;font-size:12px;text-transform:uppercase;">Bans 24h</div>
      <div style="font-size:32px;font-weight:bold;"><?= (int)$stats['bans_24h'] ?></div>
    </div>
    <div style="border:1px solid #ddd;border-radius:6px;padding:15px;background:#fff;">
      <div style="color:#999;font-size:12px;text-transform:uppercase;">Bans 1h</div>
      <div style="font-size:32px;font-weight:bold;color:<?= $stats['bans_1h'] > 50 ? '#c00' : '#333' ?>;"><?= (int)$stats['bans_1h'] ?></div>
    </div>
    <div style="border:1px solid #ddd;border-radius:6px;padding:15px;background:#fff;">
      <div style="color:#999;font-size:12px;text-transform:uppercase;">En observaci&oacute;n</div>
      <div style="font-size:32px;font-weight:bold;color:#e80;"><?= (int)$stats['observed_count'] ?></div>
    </div>
    <div style="border:1px solid #ddd;border-radius:6px;padding:15px;background:#fff;">
      <div style="color:#999;font-size:12px;text-transform:uppercase;">Allowlist DB</div>
      <div style="font-size:32px;font-weight:bold;color:#2a7;"><?= (int)$stats['allow_count'] ?></div>
    </div>
    <div style="border:1px solid #ddd;border-radius:6px;padding:15px;background:#fff;">
      <div style="color:#999;font-size:12px;text-transform:uppercase;">Hits bloqueados</div>
      <div style="font-size:32px;font-weight:bold;"><?= (int)$stats['total_hits'] ?></div>
    </div>
  </div>

  <!-- BREAKDOWN razones -->
  <?php if ($reasons): ?>
  <div style="margin-bottom:25px;">
    <strong>Por raz&oacute;n de ban (activos):</strong>
    <?php foreach ($reasons as $r): ?>
      <span style="display:inline-block;margin:0 8px;padding:4px 10px;background:#f0f0f0;border-radius:12px;font-size:13px;">
        <code><?= htmlspecialchars($r['reason']) ?></code> = <strong><?= (int)$r['c'] ?></strong>
      </span>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- SECCIONES -->
  <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">

    <!-- BLACKLIST ACTIVA -->
    <div>
      <h3>&#x1F6AB; Blacklist activa (top 200, recientes primero)</h3>
      <div style="overflow-x:auto;max-height:500px;overflow-y:auto;border:1px solid #ddd;">
      <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead style="position:sticky;top:0;background:#f5f5f5;z-index:1;">
          <tr>
            <th style="padding:6px;text-align:left;border-bottom:1px solid #ddd;">IP</th>
            <th style="padding:6px;text-align:left;border-bottom:1px solid #ddd;">Raz&oacute;n</th>
            <th style="padding:6px;text-align:left;border-bottom:1px solid #ddd;">UA (50c)</th>
            <th style="padding:6px;text-align:right;border-bottom:1px solid #ddd;">Hits</th>
            <th style="padding:6px;text-align:right;border-bottom:1px solid #ddd;">Expira</th>
            <th style="padding:6px;border-bottom:1px solid #ddd;">Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php while ($row = tep_db_fetch_array($bl_q)):
            $mins = (int)$row['mins_left'];
            $expires_label = $mins < 60 ? $mins . 'm' : ($mins < 1440 ? round($mins/60,1) . 'h' : round($mins/1440,1) . 'd');
        ?>
          <tr style="border-bottom:1px solid #eee;">
            <td style="padding:6px;font-family:monospace;"><?= htmlspecialchars($row['ip']) ?></td>
            <td style="padding:6px;"><code style="background:#fee;padding:2px 6px;border-radius:3px;font-size:11px;"><?= htmlspecialchars($row['reason']) ?></code></td>
            <td style="padding:6px;font-size:11px;color:#666;" title="<?= htmlspecialchars($row['ua'] ?? '') ?>"><?= htmlspecialchars(substr($row['ua'] ?? '', 0, 50)) ?></td>
            <td style="padding:6px;text-align:right;"><?= (int)$row['hits'] ?></td>
            <td style="padding:6px;text-align:right;font-size:11px;color:#888;"><?= $expires_label ?></td>
            <td style="padding:6px;white-space:nowrap;">
              <form method="post" style="display:inline;" onsubmit="return confirm('Desbanear <?= htmlspecialchars($row['ip']) ?>?');">
                <input type="hidden" name="action" value="unban">
                <input type="hidden" name="ip" value="<?= htmlspecialchars($row['ip']) ?>">
                <button type="submit" style="padding:3px 7px;background:#5cb85c;color:white;border:0;border-radius:3px;cursor:pointer;font-size:10px;" title="Solo desbanear">Unban</button>
              </form>
              <form method="post" style="display:inline;" onsubmit="return confirm('Desbanear <?= htmlspecialchars($row['ip']) ?> y a&ntilde;adir a allowlist permanente?');">
                <input type="hidden" name="action" value="unban_and_allow">
                <input type="hidden" name="ip" value="<?= htmlspecialchars($row['ip']) ?>">
                <input type="hidden" name="reason" value="falso positivo">
                <button type="submit" style="padding:3px 7px;background:#2a7;color:white;border:0;border-radius:3px;cursor:pointer;font-size:10px;" title="Desbanear + a&ntilde;adir a allowlist permanente">+Allow</button>
              </form>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
      </div>

      <h3 style="margin-top:30px;">&#x1F7E2; Allowlist DB (gestionable desde aqu&iacute;)</h3>
      <p style="color:#666;font-size:12px;margin:5px 0 10px;">
        IPs en esta lista NUNCA se banean (ni siquiera por heur&iacute;stica).
        Adicional a la allowlist hardcoded en <code>scraper_guard.php</code>.
      </p>
      <div style="overflow-x:auto;max-height:300px;overflow-y:auto;border:1px solid #ddd;">
      <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead style="position:sticky;top:0;background:#e8f5e9;z-index:1;">
          <tr>
            <th style="padding:6px;text-align:left;border-bottom:1px solid #ddd;">IP</th>
            <th style="padding:6px;text-align:left;border-bottom:1px solid #ddd;">Raz&oacute;n</th>
            <th style="padding:6px;text-align:left;border-bottom:1px solid #ddd;">Notas</th>
            <th style="padding:6px;text-align:left;border-bottom:1px solid #ddd;">A&ntilde;adido por</th>
            <th style="padding:6px;text-align:left;border-bottom:1px solid #ddd;">Cu&aacute;ndo</th>
            <th style="padding:6px;border-bottom:1px solid #ddd;"></th>
          </tr>
        </thead>
        <tbody>
        <?php
        $allow_count = 0;
        while ($row = tep_db_fetch_array($al_q)):
            $allow_count++;
        ?>
          <tr style="border-bottom:1px solid #eee;background:#fafffa;">
            <td style="padding:6px;font-family:monospace;"><?= htmlspecialchars($row['ip']) ?></td>
            <td style="padding:6px;"><?= htmlspecialchars($row['reason']) ?></td>
            <td style="padding:6px;font-size:11px;color:#666;"><?= htmlspecialchars($row['notes'] ?? '') ?></td>
            <td style="padding:6px;font-size:11px;color:#888;"><?= htmlspecialchars($row['added_by'] ?? '') ?></td>
            <td style="padding:6px;font-size:11px;color:#888;"><?= htmlspecialchars(substr($row['added_at'], 5, 11)) ?></td>
            <td style="padding:6px;">
              <form method="post" style="display:inline;" onsubmit="return confirm('Eliminar <?= htmlspecialchars($row['ip']) ?> de allowlist? Volver&aacute; a poder ser baneada.');">
                <input type="hidden" name="action" value="allow_remove">
                <input type="hidden" name="ip" value="<?= htmlspecialchars($row['ip']) ?>">
                <button type="submit" style="padding:3px 7px;background:#777;color:white;border:0;border-radius:3px;cursor:pointer;font-size:10px;">Remove</button>
              </form>
            </td>
          </tr>
        <?php endwhile; ?>
        <?php if ($allow_count === 0): ?>
          <tr><td colspan="6" style="padding:15px;text-align:center;color:#999;">Allowlist DB vac&iacute;a (solo aplica la hardcoded)</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
      </div>
    </div>

    <!-- COLUMNA DERECHA -->
    <div>
      <h3>&#x1F441; En observaci&oacute;n (5 min)</h3>
      <div style="overflow-x:auto;max-height:300px;overflow-y:auto;border:1px solid #ddd;margin-bottom:20px;">
      <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead style="position:sticky;top:0;background:#f5f5f5;">
          <tr>
            <th style="padding:6px;text-align:left;border-bottom:1px solid #ddd;">IP</th>
            <th style="padding:6px;text-align:right;border-bottom:1px solid #ddd;">Hits</th>
            <th style="padding:6px;text-align:right;border-bottom:1px solid #ddd;">Edad</th>
            <th style="padding:6px;border-bottom:1px solid #ddd;"></th>
          </tr>
        </thead>
        <tbody>
        <?php
        $obs_count = 0;
        while ($row = tep_db_fetch_array($obs_q)):
            $obs_count++;
            $age = (int)$row['age_sec'];
            $age_label = $age < 60 ? $age . 's' : round($age/60) . 'm';
        ?>
          <tr style="border-bottom:1px solid #eee;">
            <td style="padding:6px;font-family:monospace;font-size:12px;"><?= htmlspecialchars($row['ip']) ?></td>
            <td style="padding:6px;text-align:right;font-weight:<?= $row['hits'] >= 3 ? 'bold' : 'normal' ?>;color:<?= $row['hits'] >= 3 ? '#e80' : 'inherit' ?>;"><?= (int)$row['hits'] ?>/5</td>
            <td style="padding:6px;text-align:right;font-size:11px;color:#888;"><?= $age_label ?></td>
            <td style="padding:6px;">
              <form method="post" style="display:inline;">
                <input type="hidden" name="action" value="ban">
                <input type="hidden" name="ip" value="<?= htmlspecialchars($row['ip']) ?>">
                <input type="hidden" name="reason" value="manual_from_observed">
                <input type="hidden" name="hours" value="24">
                <button type="submit" style="padding:2px 6px;background:#d9534f;color:white;border:0;border-radius:3px;cursor:pointer;font-size:10px;">Banear</button>
              </form>
            </td>
          </tr>
        <?php endwhile; ?>
        <?php if ($obs_count === 0): ?>
          <tr><td colspan="4" style="padding:15px;text-align:center;color:#999;">Sin actividad observada en 5 min</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
      </div>

      <h3>&#x2795; Ban manual</h3>
      <form method="post" style="border:1px solid #ddd;padding:15px;border-radius:6px;background:#fafafa;margin-bottom:20px;">
        <input type="hidden" name="action" value="ban">
        <div style="margin-bottom:8px;">
          <label style="display:block;font-size:12px;color:#666;">IP</label>
          <input type="text" name="ip" placeholder="192.0.2.1" required style="width:100%;padding:6px;border:1px solid #ccc;border-radius:3px;box-sizing:border-box;">
        </div>
        <div style="margin-bottom:8px;">
          <label style="display:block;font-size:12px;color:#666;">Raz&oacute;n (max 64c)</label>
          <input type="text" name="reason" placeholder="manual" maxlength="64" style="width:100%;padding:6px;border:1px solid #ccc;border-radius:3px;box-sizing:border-box;">
        </div>
        <div style="margin-bottom:12px;">
          <label style="display:block;font-size:12px;color:#666;">Duraci&oacute;n (horas, 1-720)</label>
          <input type="number" name="hours" value="24" min="1" max="720" style="width:100%;padding:6px;border:1px solid #ccc;border-radius:3px;box-sizing:border-box;">
        </div>
        <button type="submit" style="width:100%;padding:8px;background:#d9534f;color:white;border:0;border-radius:3px;cursor:pointer;">&#x1F6AB; Banear</button>
      </form>

      <h3>&#x1F7E2; A&ntilde;adir a allowlist</h3>
      <form method="post" style="border:1px solid #cea;padding:15px;border-radius:6px;background:#f0fff0;margin-bottom:20px;">
        <input type="hidden" name="action" value="allow_add">
        <div style="margin-bottom:8px;">
          <label style="display:block;font-size:12px;color:#666;">IP</label>
          <input type="text" name="ip" placeholder="192.0.2.1" required style="width:100%;padding:6px;border:1px solid #ccc;border-radius:3px;box-sizing:border-box;">
        </div>
        <div style="margin-bottom:8px;">
          <label style="display:block;font-size:12px;color:#666;">Raz&oacute;n (max 128c)</label>
          <input type="text" name="reason" placeholder="cliente VIP / falso positivo / etc" maxlength="128" style="width:100%;padding:6px;border:1px solid #ccc;border-radius:3px;box-sizing:border-box;">
        </div>
        <div style="margin-bottom:12px;">
          <label style="display:block;font-size:12px;color:#666;">Notas (opcional, max 255c)</label>
          <input type="text" name="notes" maxlength="255" style="width:100%;padding:6px;border:1px solid #ccc;border-radius:3px;box-sizing:border-box;">
        </div>
        <button type="submit" style="width:100%;padding:8px;background:#2a7;color:white;border:0;border-radius:3px;cursor:pointer;">&#x1F7E2; A&ntilde;adir a allowlist</button>
      </form>

      <h3>&#x1F9F9; Mantenimiento</h3>
      <form method="post" onsubmit="return confirm('Eliminar bans expirados y observed antiguos (>1h)?');">
        <input type="hidden" name="action" value="cleanup_expired">
        <button type="submit" style="width:100%;padding:8px;background:#777;color:white;border:0;border-radius:3px;cursor:pointer;">Limpiar expirados + observed antiguos</button>
      </form>
    </div>
  </div>

  <div style="margin-top:30px;padding:15px;background:#f9f9f9;border-left:4px solid #ccc;font-size:12px;color:#666;">
    <strong>Allowlist hardcoded</strong> (en <code>includes/scraper_guard.php</code>, no editable desde aqu&iacute;):<br>
    IPs: <code>217.127.199.171</code> (LAN), <code>20.71.1.14</code> (self), <code>80.28.193.44</code> (casa), <code>127.0.0.1</code>.<br>
    CIDR: Redsys <code>195.76.9.0/24</code>, Googlebot, Bingbot, Meta, Ahrefs, Petalbot.<br>
    <strong>Allowlist DB</strong> (gestionable desde esta p&aacute;gina): se consulta en cada request, prioritaria sobre el banlist.
  </div>
</div>
<?php
require THEME . 'html/footer.php';
require DIR_WS_INCLUDES . 'application_bottom.php';
