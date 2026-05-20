<?php
/**
 * Scraper guard:
 *   1) Allowlist hardcoded (LAN, self, casa, Redsys, Googlebot, Bingbot, Meta, Ahrefs, Petalbot)
 *   2) Allowlist DB (tabla scraper_allowlist) — añadible desde panel admin
 *   3) 403 inmediato si IP en scraper_blacklist (no expirada)
 *   4) Heurística: UAs del pool residencial (SM-G900P, Pixel 2, iPhone 11, Nexus 5) -> rate-limit
 *      Si la misma IP+UA-antiguo dispara >=5 hits en 60s -> auto-blacklist 24h + 403.
 *
 * Fail-open: si la BD falla no bloquea.
 * Requiere DB_SERVER definido (configure.php).
 */

$_sg_ip = $_SERVER["REMOTE_ADDR"] ?? "";
if ($_sg_ip === "" || !defined("DB_SERVER")) return;

$_sg_allow_exact = ["217.127.199.171", "20.71.1.14", "80.28.193.44", "127.0.0.1", "::1"];
$_sg_allow_cidr = [
    "195.76.9.0/24",     // Redsys TPV
    "66.249.64.0/19",    // Googlebot
    "66.249.80.0/20",    // Googlebot
    "216.239.32.0/19",   // Google
    "40.77.167.0/24",    // Bingbot
    "157.55.39.0/24",    // Bingbot
    "207.46.13.0/24",    // Bingbot
    "173.252.64.0/19",   // Meta
    "31.13.24.0/21",     // Meta
    "168.100.149.0/24",  // Ahrefs
    "176.31.139.0/24",   // Ahrefs
    "114.119.0.0/16",    // PetalBot/AspiegelBot Huawei
];

if (in_array($_sg_ip, $_sg_allow_exact, true)) return;

if (strpos($_sg_ip, ":") === false) {
    $_sg_ipl = ip2long($_sg_ip);
    if ($_sg_ipl !== false) {
        foreach ($_sg_allow_cidr as $_sg_c) {
            $_sg_p = strpos($_sg_c, "/");
            if ($_sg_p === false) continue;
            $_sg_sn = substr($_sg_c, 0, $_sg_p);
            $_sg_b = (int)substr($_sg_c, $_sg_p + 1);
            if (strpos($_sg_sn, ":") !== false) continue;
            $_sg_snl = ip2long($_sg_sn);
            if ($_sg_snl === false) continue;
            $_sg_mask = -1 << (32 - $_sg_b);
            if (($_sg_ipl & $_sg_mask) === ($_sg_snl & $_sg_mask)) return;
        }
    }
}

$_sg_link = @mysqli_connect(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if (!$_sg_link) return;
$_sg_ipe = mysqli_real_escape_string($_sg_link, $_sg_ip);

// --- (2) + (3): allowlist DB y blacklist en una sola query ---
$_sg_res = @mysqli_query($_sg_link, "SELECT
    EXISTS(SELECT 1 FROM scraper_allowlist WHERE ip = \"$_sg_ipe\") AS is_allow,
    EXISTS(SELECT 1 FROM scraper_blacklist WHERE ip = \"$_sg_ipe\" AND expires_at > NOW()) AS is_block");
$_sg_row = $_sg_res ? mysqli_fetch_assoc($_sg_res) : null;
if ($_sg_res) mysqli_free_result($_sg_res);

if ($_sg_row && (int)$_sg_row["is_allow"] === 1) {
    @mysqli_close($_sg_link);
    return; // allowlist DB siempre gana
}

if ($_sg_row && (int)$_sg_row["is_block"] === 1) {
    @mysqli_close($_sg_link);
    _sg_deny_403();
}

// --- (4) Heuristica UA antiguo + rate-limit ---
$_sg_ua = $_SERVER["HTTP_USER_AGENT"] ?? "";
$_sg_old_ua_regex = "#(SM-G900P Build|Pixel 2 Build/OPD3|Nexus 5 Build/MRA58N|iPhone OS 11_0.*Chrome|Android 7\.0;\) AppleWebKit/537\.36 \(HTML, like Gecko\))#";
$_sg_declared_bot_regex = "#(PetalBot|AspiegelBot|Googlebot|bingbot|YandexBot|DuckDuckBot|FacebookExternalHit|meta-externalagent|Amazonbot|AhrefsBot|Slurp|SemrushBot|MJ12bot|DotBot|Applebot)#i";

if ($_sg_ua === "" || !preg_match($_sg_old_ua_regex, $_sg_ua) || preg_match($_sg_declared_bot_regex, $_sg_ua)) {
    @mysqli_close($_sg_link);
    return;
}

// UA sospechoso. Rate-limit por IP en ventana 60s.
$_sg_uae = mysqli_real_escape_string($_sg_link, substr($_sg_ua, 0, 500));
$_sg_res = @mysqli_query($_sg_link, "SELECT hits, UNIX_TIMESTAMP(window_start) AS ws FROM scraper_observed WHERE ip = \"$_sg_ipe\" AND reason = \"oldua\" LIMIT 1");
$_sg_row = $_sg_res ? mysqli_fetch_assoc($_sg_res) : null;
if ($_sg_res) mysqli_free_result($_sg_res);

$_sg_now = time();
if (!$_sg_row) {
    @mysqli_query($_sg_link, "INSERT INTO scraper_observed (ip, reason, window_start, hits) VALUES (\"$_sg_ipe\", \"oldua\", NOW(), 1)");
    @mysqli_close($_sg_link);
    return;
}

if ($_sg_now - (int)$_sg_row["ws"] > 60) {
    @mysqli_query($_sg_link, "UPDATE scraper_observed SET window_start = NOW(), hits = 1 WHERE ip = \"$_sg_ipe\" AND reason = \"oldua\"");
    @mysqli_close($_sg_link);
    return;
}

$_sg_new_hits = (int)$_sg_row["hits"] + 1;
if ($_sg_new_hits < 5) {
    @mysqli_query($_sg_link, "UPDATE scraper_observed SET hits = $_sg_new_hits WHERE ip = \"$_sg_ipe\" AND reason = \"oldua\"");
    @mysqli_close($_sg_link);
    return;
}

// >=5 hits en 60s -> autoban 24h
@mysqli_query($_sg_link, "INSERT INTO scraper_blacklist (ip, ua, reason, expires_at)
    VALUES (\"$_sg_ipe\", \"$_sg_uae\", \"ratelimit_oldua\", DATE_ADD(NOW(), INTERVAL 24 HOUR))
    ON DUPLICATE KEY UPDATE last_seen = NOW(), hits = hits + 1,
        expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR),
        ua = VALUES(ua),
        reason = IF(reason LIKE \"%ratelimit_oldua%\", reason, CONCAT(reason, \"+ratelimit_oldua\"))");
@mysqli_query($_sg_link, "DELETE FROM scraper_observed WHERE ip = \"$_sg_ipe\" AND reason = \"oldua\"");
@mysqli_close($_sg_link);
_sg_deny_403();


function _sg_deny_403() {
    http_response_code(403);
    header("Content-Type: text/html; charset=utf-8");
    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Retry-After: 86400");
    header("X-Robots-Tag: noindex, nofollow");
    echo "<!doctype html><html lang=\"es\"><head><meta charset=\"utf-8\"><title>403 Forbidden</title><meta name=\"robots\" content=\"noindex,nofollow\"></head><body style=\"font-family:sans-serif;text-align:center;padding:50px\"><h1>403 Forbidden</h1><p>Acceso denegado.</p></body></html>";
    exit;
}
