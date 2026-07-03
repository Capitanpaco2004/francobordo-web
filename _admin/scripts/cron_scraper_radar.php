<?php
/**
 * cron_scraper_radar.php — DETECCION PASIVA (NO banea). Creado 2026-07-02.
 * Alimenta la tabla scraper_radar para que el reporte diario avise de amenazas emergentes.
 * Corre cada hora (CLI). Procesa la cola reciente del access log (sin depender de rotacion).
 *
 * Detecta:
 *   - catalog_walker: IP NO-exenta que pagina /products_new.php sin tocar compra (agnostico al UA).
 *                     Caza la proxima mutacion de la flota aunque cambie de User-Agent.
 *   - spoofed_bot:    IP con UA de crawler declarado (Googlebot/bingbot/...) que FALLA FCrDNS
 *                     (forward-confirmed reverse DNS = metodo canonico Google/Bing, sin listas CIDR).
 *
 * NO modifica scraper_blacklist. Solo informa. Purga la hace el reporte diario.
 */
mysqli_report(MYSQLI_REPORT_OFF);

const LOG        = '/usr/local/apache/domlogs/francobordo/francobordo.com-ssl_log';
const WINDOW     = 40000;   // ~ultima hora de trafico (tail de lineas)
const CAT_THRESH = 8;       // hits a products_new en la ventana para marcar catalog_walker
const FCRDNS_CAP = 60;      // max verificaciones FCrDNS por run

$conf = @file_get_contents('/home/francobordo/public_html/includes/configure.php');
if ($conf === false) { fwrite(STDERR, "no configure\n"); exit(1); }
preg_match("/'DB_SERVER',\s*'([^']+)'/", $conf, $m);          $H = $m[1] ?? 'localhost';
preg_match("/'DB_SERVER_USERNAME',\s*'([^']+)'/", $conf, $m); $U = $m[1] ?? '';
preg_match("/'DB_SERVER_PASSWORD',\s*'([^']+)'/", $conf, $m); $P = $m[1] ?? '';
preg_match("/'DB_DATABASE',\s*'([^']+)'/", $conf, $m);        $D = $m[1] ?? '';
$db = @new mysqli($H, $U, $P, $D);
if ($db->connect_errno) { fwrite(STDERR, 'conn: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');
function lg($s) { echo '[' . date('Y-m-d H:i:s') . '] ' . $s . "\n"; }

// --- Exenciones (espejo del guard) ---
$ALLOW_EXACT = ['217.127.199.171','20.71.1.14','80.28.193.44','127.0.0.1'];
$ALLOW_CIDR = [
    '195.76.9.0/24','66.249.64.0/19','66.249.80.0/20','216.239.32.0/19','40.77.167.0/24',
    '157.55.39.0/24','207.46.13.0/24','173.252.64.0/19','31.13.24.0/21','168.100.149.0/24',
    '176.31.139.0/24','114.119.0.0/16',
    '104.16.0.0/13','104.24.0.0/14','104.28.0.0/16','172.64.0.0/13','162.158.0.0/15',
    '141.101.64.0/18','108.162.192.0/18','173.245.48.0/20','140.248.0.0/16','172.224.0.0/12',
    '136.226.0.0/16','147.161.128.0/17','165.225.0.0/17','165.225.192.0/18','170.85.0.0/16',
    '104.129.192.0/20','104.47.0.0/17','151.101.0.0/16','146.75.0.0/16','199.232.0.0/16',
];
function ip_in_cidr(string $ip, array $list): bool {
    $ipl = ip2long($ip); if ($ipl === false) return false;
    foreach ($list as $c) {
        $p = strpos($c, '/'); if ($p === false) { if ($ip === $c) return true; continue; }
        $snl = ip2long(substr($c, 0, $p)); if ($snl === false) continue;
        $b = (int)substr($c, $p + 1); $mask = $b === 0 ? 0 : (-1 << (32 - $b));
        if (($ipl & $mask) === ($snl & $mask)) return true;
    }
    return false;
}
// Familias de bot declarado -> dominios FCrDNS esperados (sufijo del PTR)
$BOT_DOMAINS = [
    'googlebot' => ['googlebot.com','google.com','googleusercontent.com'],
    'bingbot'   => ['search.msn.com','msn.com'],
    'applebot'  => ['applebot.apple.com','apple.com'],
    'yandex'    => ['yandex.com','yandex.net','yandex.ru'],
    'facebook'  => ['fbsv.net','facebook.com'],
];

function dig(array $args): string {
    $p = @proc_open(array_merge(['dig'], $args), [1=>['pipe','w'],2=>['pipe','w']], $pipes);
    if (!is_resource($p)) return '';
    $o = stream_get_contents($pipes[1]); fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
    return $o === false ? '' : trim($o);
}
/** FCrDNS: PTR de la IP debe terminar en un dominio esperado Y resolver de vuelta a la IP. */
function fcrdns_ok(string $ip, array $domains): bool {
    $ptr = strtolower(trim(dig(['+short','-x',$ip,'+time=2','+tries=1','@1.1.1.1'])));
    $ptr = trim(strtok($ptr, "\n"));
    if ($ptr === '') return false;
    $ptr = rtrim($ptr, '.');
    $suffix_ok = false;
    foreach ($domains as $d) { if ($ptr === $d || str_ends_with($ptr, '.' . $d)) { $suffix_ok = true; break; } }
    if (!$suffix_ok) return false;
    $fwd = dig(['+short', $ptr, '+time=2', '+tries=1', '@1.1.1.1']);
    foreach (explode("\n", $fwd) as $a) { if (trim($a) === $ip) return true; }
    return false;
}

$now = date('Y-m-d H:i:s');
$up = $db->prepare("INSERT INTO scraper_radar (ip,kind,detail,hits,first_seen,last_seen)
    VALUES (?,?,?,?,NOW(),NOW())
    ON DUPLICATE KEY UPDATE detail=VALUES(detail), hits=GREATEST(hits,VALUES(hits)), last_seen=NOW()");

// ============ 1) catalog_walker (agnostico al UA) ============
$awk_cat = 'tail -n ' . WINDOW . ' ' . escapeshellarg(LOG) . ' | awk \''
    . '$7 ~ /products_new\.php/ {pn[$1]++} '
    . '$7 ~ /checkout|shopping_cart|\/login|\/account|create_account|favoritos/ {buy[$1]=1} '
    . 'END{for(ip in pn) if(!(ip in buy)) print pn[ip]" "ip}\'';
$out = shell_exec($awk_cat) ?? '';
$cat_n = 0;
foreach (explode("\n", trim($out)) as $line) {
    if ($line === '') continue;
    [$hits, $ip] = array_pad(explode(' ', trim($line), 2), 2, '');
    $hits = (int)$hits;
    if ($hits < CAT_THRESH || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) continue;
    if (in_array($ip, $ALLOW_EXACT, true) || ip_in_cidr($ip, $ALLOW_CIDR)) continue;
    $ipe = $db->real_escape_string($ip);
    $r = $db->query("SELECT 1 FROM scraper_customer_ips WHERE ip='$ipe' AND last_seen > NOW()-INTERVAL 30 DAY LIMIT 1");
    if ($r && $r->num_rows) continue;                 // comprador reciente
    $r = $db->query("SELECT 1 FROM scraper_blacklist WHERE ip='$ipe' AND expires_at>NOW() LIMIT 1");
    if ($r && $r->num_rows) continue;                 // ya baneado (no es noticia)
    $detail = "pagina products_new x$hits/h sin comprar";
    $k = 'catalog_walker';
    $up->bind_param('sssi', $ip, $k, $detail, $hits);
    $up->execute(); $cat_n++;
}
lg("catalog_walker: $cat_n IPs marcadas");

// ============ 2) spoofed_bot (FCrDNS) ============
$awk_bot = 'tail -n ' . WINDOW . ' ' . escapeshellarg(LOG) . ' | awk -F\'"\' \'{'
    . 'ip=$1; sub(/ .*/,"",ip); ua=$6; '
    . 'if (ua ~ /Googlebot|Storebot-Google|AdsBot-Google|GoogleOther|Google-InspectionTool/) print "googlebot "ip; '
    . 'else if (ua ~ /bingbot|adidxbot|BingPreview|msnbot/) print "bingbot "ip; '
    . 'else if (ua ~ /Applebot/) print "applebot "ip; '
    . 'else if (ua ~ /YandexBot/) print "yandex "ip; '
    . 'else if (ua ~ /facebookexternalhit|meta-externalagent/) print "facebook "ip; '
    . '}\' | sort -u';
$out = shell_exec($awk_bot) ?? '';
$lookups = 0; $spoof_n = 0;
foreach (explode("\n", trim($out)) as $line) {
    if ($line === '' || $lookups >= FCRDNS_CAP) continue;
    [$fam, $ip] = array_pad(explode(' ', trim($line), 2), 2, '');
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) continue;
    if (!isset($BOT_DOMAINS[$fam])) continue;
    if (in_array($ip, $ALLOW_EXACT, true)) continue;
    $lookups++;
    if (fcrdns_ok($ip, $BOT_DOMAINS[$fam])) continue;  // bot legitimo verificado
    $detail = "UA dice '$fam' pero FCrDNS falla (posible spoof)";
    $k = 'spoofed_bot'; $one = 1;
    $up->bind_param('sssi', $ip, $k, $detail, $one);
    $up->execute(); $spoof_n++;
}
lg("spoofed_bot: $spoof_n IPs (de $lookups verificaciones FCrDNS)");
lg('Fin radar.');
$db->close();
