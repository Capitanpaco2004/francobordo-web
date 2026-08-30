<?php
/**
 * Cron diario anti-scraper: limpieza + reporte por email.
 * Invocar: curl --user-agent cPanel-Cron "https://www.francobordo.com/cron_scraper_report.php?token=XXX"
 *
 * Hace:
 *   1) Purga scraper_blacklist expirados + scraper_observed antiguos (>1h).
 *   2) Genera reporte HTML (últimas 24h) y lo envía a f.rodriguez@francobordo.com via tep_mail
 *      (que aplica el bypass REST de SendGrid para destinos @francobordo.com).
 * Creado 2026-05-20.
 */

require_once 'includes/application_top.php';

// --- Gate: token obligatorio ---
$_cron_token = '79a0a9da0b4371e6a49f9e9525801188';
if (!hash_equals($_cron_token, $_GET['token'] ?? '')) {
    http_response_code(403);
    exit('Forbidden');
}

header('Content-Type: text/plain; charset=utf-8');

// --- 1) LIMPIEZA ---
$_r1 = tep_db_query("DELETE FROM scraper_blacklist WHERE expires_at <= NOW()");
$del_bl = tep_db_affected_rows($_r1);
$_r2 = tep_db_query("DELETE FROM scraper_observed WHERE window_start < NOW() - INTERVAL 1 HOUR");
$del_obs = tep_db_affected_rows($_r2);
tep_db_query("DELETE FROM scraper_radar WHERE last_seen < NOW() - INTERVAL 7 DAY");

// --- RADAR (deteccion pasiva, NO baneada): amenazas emergentes detectadas por cron_scraper_radar.php ---
$radar = [];
$rr = tep_db_query("SELECT ip, kind, detail, hits, last_seen FROM scraper_radar WHERE last_seen > NOW() - INTERVAL 24 HOUR ORDER BY kind, hits DESC LIMIT 60");
while ($x = tep_db_fetch_array($rr)) $radar[] = $x;

// --- 2) STATS ---
$q = tep_db_query("SELECT
    (SELECT COUNT(*) FROM scraper_blacklist WHERE expires_at > NOW()) AS active_bans,
    (SELECT COUNT(*) FROM scraper_blacklist WHERE first_seen > NOW() - INTERVAL 24 HOUR) AS bans_24h,
    (SELECT COUNT(*) FROM scraper_observed) AS observed_count,
    (SELECT COUNT(*) FROM scraper_allowlist) AS allow_count,
    (SELECT COALESCE(SUM(hits),0) FROM scraper_blacklist WHERE expires_at > NOW()) AS total_hits");
$s = tep_db_fetch_array($q);

// Breakdown por razón (activos)
$reasons = [];
$rq = tep_db_query("SELECT reason, COUNT(*) AS c FROM scraper_blacklist WHERE expires_at > NOW() GROUP BY reason ORDER BY c DESC");
while ($r = tep_db_fetch_array($rq)) $reasons[] = $r;

// Top 20 IPs nuevas 24h
$top = [];
$tq = tep_db_query("SELECT ip, hits, reason, LEFT(ua,55) AS ua, first_seen
    FROM scraper_blacklist
    WHERE first_seen > NOW() - INTERVAL 24 HOUR
    ORDER BY first_seen DESC LIMIT 20");
while ($r = tep_db_fetch_array($tq)) $top[] = $r;

// Allowlist DB actual
$allow = [];
$aq = tep_db_query("SELECT ip, reason, added_by, added_at FROM scraper_allowlist ORDER BY added_at DESC LIMIT 20");
while ($r = tep_db_fetch_array($aq)) $allow[] = $r;

// --- 3) CONSTRUIR HTML ---
$fecha = date('d/m/Y H:i');
$h = '<div style="font-family:Arial,sans-serif;max-width:700px;margin:0 auto;color:#333;">';
$h .= '<h2 style="border-bottom:2px solid #c00;padding-bottom:8px;">&#x1F6E1; Reporte anti-scraper &mdash; ' . $fecha . '</h2>';

$h .= '<table style="width:100%;border-collapse:collapse;margin:20px 0;">';
$h .= '<tr>';
$h .= '<td style="padding:12px;background:#fef0f0;border:1px solid #eee;text-align:center;"><div style="font-size:26px;font-weight:bold;color:#c00;">' . (int)$s['active_bans'] . '</div><div style="font-size:11px;color:#888;">BANS ACTIVOS</div></td>';
$h .= '<td style="padding:12px;background:#f8f8f8;border:1px solid #eee;text-align:center;"><div style="font-size:26px;font-weight:bold;">' . (int)$s['bans_24h'] . '</div><div style="font-size:11px;color:#888;">NUEVOS 24H</div></td>';
$h .= '<td style="padding:12px;background:#fff8f0;border:1px solid #eee;text-align:center;"><div style="font-size:26px;font-weight:bold;color:#e80;">' . (int)$s['observed_count'] . '</div><div style="font-size:11px;color:#888;">EN OBSERVACI&Oacute;N</div></td>';
$h .= '<td style="padding:12px;background:#f0fff0;border:1px solid #eee;text-align:center;"><div style="font-size:26px;font-weight:bold;color:#2a7;">' . (int)$s['allow_count'] . '</div><div style="font-size:11px;color:#888;">ALLOWLIST</div></td>';
$h .= '</tr></table>';

// Limpieza
$h .= '<p style="background:#f5f5f5;padding:10px;border-left:4px solid #999;font-size:13px;">';
$h .= '&#x1F9F9; <strong>Limpieza:</strong> ' . (int)$del_bl . ' bans expirados y ' . (int)$del_obs . ' observados antiguos eliminados.</p>';

// Breakdown
if ($reasons) {
    $h .= '<p style="font-size:14px;"><strong>Bans activos por raz&oacute;n:</strong> ';
    foreach ($reasons as $r) {
        $h .= '<span style="display:inline-block;margin:2px 6px;padding:3px 9px;background:#eee;border-radius:10px;font-size:12px;">' . htmlspecialchars($r['reason']) . ': <strong>' . (int)$r['c'] . '</strong></span>';
    }
    $h .= '</p>';
}

// Top IPs
$h .= '<h3 style="margin-top:25px;">&#x1F6AB; &Uacute;ltimas 20 IPs banneadas (24h)</h3>';
if ($top) {
    $h .= '<table style="width:100%;border-collapse:collapse;font-size:12px;">';
    $h .= '<tr style="background:#f5f5f5;"><th style="padding:6px;text-align:left;border-bottom:1px solid #ddd;">IP</th><th style="padding:6px;text-align:left;border-bottom:1px solid #ddd;">Raz&oacute;n</th><th style="padding:6px;text-align:left;border-bottom:1px solid #ddd;">UA</th><th style="padding:6px;text-align:left;border-bottom:1px solid #ddd;">Cu&aacute;ndo</th></tr>';
    foreach ($top as $r) {
        $h .= '<tr style="border-bottom:1px solid #eee;">';
        $h .= '<td style="padding:6px;font-family:monospace;">' . htmlspecialchars($r['ip']) . '</td>';
        $h .= '<td style="padding:6px;">' . htmlspecialchars($r['reason']) . '</td>';
        $h .= '<td style="padding:6px;color:#777;">' . htmlspecialchars($r['ua']) . '</td>';
        $h .= '<td style="padding:6px;color:#999;">' . htmlspecialchars(substr($r['first_seen'], 5, 11)) . '</td>';
        $h .= '</tr>';
    }
    $h .= '</table>';
} else {
    $h .= '<p style="color:#999;">Sin bans nuevos en 24h.</p>';
}

// Allowlist
if ($allow) {
    $h .= '<h3 style="margin-top:25px;">&#x1F7E2; Allowlist DB actual</h3>';
    $h .= '<table style="width:100%;border-collapse:collapse;font-size:12px;">';
    $h .= '<tr style="background:#e8f5e9;"><th style="padding:6px;text-align:left;border-bottom:1px solid #ddd;">IP</th><th style="padding:6px;text-align:left;border-bottom:1px solid #ddd;">Raz&oacute;n</th><th style="padding:6px;text-align:left;border-bottom:1px solid #ddd;">A&ntilde;adido por</th></tr>';
    foreach ($allow as $r) {
        $h .= '<tr style="border-bottom:1px solid #eee;">';
        $h .= '<td style="padding:6px;font-family:monospace;">' . htmlspecialchars($r['ip']) . '</td>';
        $h .= '<td style="padding:6px;">' . htmlspecialchars($r['reason']) . '</td>';
        $h .= '<td style="padding:6px;color:#777;">' . htmlspecialchars($r['added_by'] ?? '') . '</td>';
        $h .= '</tr>';
    }
    $h .= '</table>';
}

// --- RADAR: amenazas emergentes (NO baneadas, solo detectadas) ---
$radar_spoof = array_filter($radar, fn($r) => $r['kind'] === 'spoofed_bot');
$radar_walk  = array_filter($radar, fn($r) => $r['kind'] === 'catalog_walker');
// spoofed_ai_bot: lo marca el guard sincrono comparando la IP contra los rangos que publican
// OpenAI/Anthropic/Perplexity/Google/Bing (includes/bot_ranges.inc.php). Anadido 2026-08-29
// tras la campana de escaneo de credenciales que rotaba UAs de crawlers IA.
$radar_ai    = array_filter($radar, fn($r) => $r['kind'] === 'spoofed_ai_bot');
$h .= '<h3 style="margin-top:25px;">&#x1F4E1; Radar (detecci&oacute;n pasiva, NO baneado &mdash; revisi&oacute;n manual)</h3>';
if (!$radar) {
    $h .= '<p style="color:#2a7;font-size:13px;">&#x2705; Sin amenazas emergentes en 24h (ni bots/crawlers IA falsificados ni paginadores de cat&aacute;logo no cazados).</p>';
} else {
    if ($radar_walk) {
        $h .= '<p style="font-size:13px;margin:8px 0 4px;"><strong>&#x1F577; Paginadores de cat&aacute;logo NO cazados</strong> (IPs que barren products_new sin comprar y que las reglas por-IP no atrapan &mdash; posible flota nueva):</p>';
        $h .= '<table style="width:100%;border-collapse:collapse;font-size:12px;"><tr style="background:#fff3f0;"><th style="padding:5px;text-align:left;border-bottom:1px solid #ddd;">IP</th><th style="padding:5px;text-align:left;border-bottom:1px solid #ddd;">Detalle</th><th style="padding:5px;text-align:right;border-bottom:1px solid #ddd;">Hits</th></tr>';
        foreach ($radar_walk as $r) {
            $h .= '<tr style="border-bottom:1px solid #eee;"><td style="padding:5px;font-family:monospace;">' . htmlspecialchars($r['ip']) . '</td><td style="padding:5px;color:#777;">' . htmlspecialchars($r['detail']) . '</td><td style="padding:5px;text-align:right;">' . (int)$r['hits'] . '</td></tr>';
        }
        $h .= '</table>';
    }
    if ($radar_spoof) {
        $h .= '<p style="font-size:13px;margin:14px 0 4px;"><strong>&#x1F3AD; Bots falsificados</strong> (UA dice ser buscador pero FCrDNS falla &mdash; scraper disfraz&aacute;ndose de crawler):</p>';
        $h .= '<table style="width:100%;border-collapse:collapse;font-size:12px;"><tr style="background:#fff3f0;"><th style="padding:5px;text-align:left;border-bottom:1px solid #ddd;">IP</th><th style="padding:5px;text-align:left;border-bottom:1px solid #ddd;">Detalle</th></tr>';
        foreach ($radar_spoof as $r) {
            $h .= '<tr style="border-bottom:1px solid #eee;"><td style="padding:5px;font-family:monospace;">' . htmlspecialchars($r['ip']) . '</td><td style="padding:5px;color:#777;">' . htmlspecialchars($r['detail']) . '</td></tr>';
        }
        $h .= '</table>';
    }
    if ($radar_ai) {
        $h .= '<p style="font-size:13px;margin:14px 0 4px;"><strong>&#x1F916; Crawlers IA falsificados</strong> (UA dice GPTBot/ClaudeBot/Perplexity/Googlebot/bingbot pero la IP NO est&aacute; en los rangos que publica el proveedor):</p>';
        $h .= '<table style="width:100%;border-collapse:collapse;font-size:12px;"><tr style="background:#fff3f0;"><th style="padding:5px;text-align:left;border-bottom:1px solid #ddd;">IP</th><th style="padding:5px;text-align:left;border-bottom:1px solid #ddd;">Detalle</th><th style="padding:5px;text-align:right;border-bottom:1px solid #ddd;">Hits</th></tr>';
        foreach ($radar_ai as $r) {
            $h .= '<tr style="border-bottom:1px solid #eee;"><td style="padding:5px;font-family:monospace;">' . htmlspecialchars($r['ip']) . '</td><td style="padding:5px;color:#777;">' . htmlspecialchars($r['detail']) . '</td><td style="padding:5px;text-align:right;">' . (int)$r['hits'] . '</td></tr>';
        }
        $h .= '</table>';
    }
    $h .= '<p style="font-size:11px;color:#999;margin-top:6px;">Si confirmas que alguna es scraper, ba&ntilde;eala desde el panel; si es leg&iacute;tima, a&ntilde;&aacute;dela a la allowlist.</p>';
}

$h .= '<p style="margin-top:25px;font-size:12px;color:#999;">Panel completo: <a href="https://www.francobordo.com/_admin/scraper_reports.php">scraper_reports.php</a> (Herramientas &rarr; Scraper Reports)</p>';
$h .= '</div>';

// --- 4) ENVIAR ---
$radar_tag = $radar ? (' | radar:' . count($radar)) : '';
$asunto = 'Reporte anti-scraper: ' . (int)$s['active_bans'] . ' bans activos, +' . (int)$s['bans_24h'] . ' en 24h' . $radar_tag;
$sent = tep_mail('Francisco', 'f.rodriguez@francobordo.com', $asunto, $h, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);

echo "OK cleanup: -$del_bl blacklist, -$del_obs observed\n";
echo "Stats: active=" . (int)$s['active_bans'] . " new24h=" . (int)$s['bans_24h'] . " observed=" . (int)$s['observed_count'] . " allow=" . (int)$s['allow_count'] . "\n";
echo "Email a f.rodriguez@francobordo.com: " . ($sent !== false ? "ENVIADO" : "FALLO") . "\n";
