<?php
/**
 * Honeypot scraper trap.
 * Cualquier IP que pida este endpoint se autobanea 7d en scraper_blacklist,
 * salvo IPs/rangos de la allowlist (LAN, self, Redsys, Googlebot, Bingbot, Meta).
 * Se publica como Disallow en robots.txt — un crawler que respete las reglas no llegara aqui.
 */

$ip = $_SERVER["REMOTE_ADDR"] ?? "";
$ua = substr($_SERVER["HTTP_USER_AGENT"] ?? "", 0, 500);

$allow_exact = [
    "217.127.199.171", // LAN Francobordo
    "80.28.193.44",    // Casa f.rodriguez
    "20.71.1.14",      // self nic1
    "127.0.0.1",
];
$allow_cidr = [
    "195.76.9.0/24",     // Redsys TPV
    "66.249.64.0/19",    // Googlebot core
    "66.249.80.0/20",    // Googlebot extra
    "216.239.32.0/19",   // Google
    "40.77.167.0/24",    // Bingbot
    "157.55.39.0/24",    // Bingbot
    "207.46.13.0/24",    // Bingbot
    "173.252.64.0/19",   // Meta
    "31.13.24.0/21",     // Meta
    "168.100.149.0/24",  // AhrefsBot
    "176.31.139.0/24",   // AhrefsBot
];

function _hp_in_cidr($ip, $cidr) {
    if (strpos($cidr, "/") === false) return $ip === $cidr;
    [$subnet, $bits] = explode("/", $cidr);
    if (strpos($ip, ":") !== false || strpos($subnet, ":") !== false) return false;
    $ip_l = ip2long($ip);
    $sn_l = ip2long($subnet);
    if ($ip_l === false || $sn_l === false) return false;
    $mask = -1 << (32 - (int)$bits);
    return ($ip_l & $mask) === ($sn_l & $mask);
}

$is_allowlisted = in_array($ip, $allow_exact, true);
if (!$is_allowlisted) {
    foreach ($allow_cidr as $c) { if (_hp_in_cidr($ip, $c)) { $is_allowlisted = true; break; } }
}

if (!$is_allowlisted && $ip !== "") {
    require dirname(__DIR__) . "/includes/configure.php";
    $link = @mysqli_connect(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
    if ($link) {
        mysqli_set_charset($link, "utf8mb4");
        $ip_e = mysqli_real_escape_string($link, $ip);
        $ua_e = mysqli_real_escape_string($link, $ua);
        $sql = "INSERT INTO scraper_blacklist (ip, ua, reason, expires_at)
                VALUES (\"$ip_e\", \"$ua_e\", \"honeypot\", DATE_ADD(NOW(), INTERVAL 7 DAY))
                ON DUPLICATE KEY UPDATE
                    last_seen = NOW(),
                    hits = hits + 1,
                    expires_at = DATE_ADD(NOW(), INTERVAL 7 DAY),
                    ua = VALUES(ua),
                    reason = IF(reason = \"honeypot\", reason, CONCAT(\"honeypot+\", reason))";
        @mysqli_query($link, $sql);
        @mysqli_close($link);
    }
}

http_response_code(403);
header("Content-Type: image/gif");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("X-Robots-Tag: noindex, nofollow");
echo base64_decode("R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7");
exit;
