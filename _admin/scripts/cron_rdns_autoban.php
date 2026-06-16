<?php
/**
 * cron_rdns_autoban.php — Async rDNS-based catalog-scraper auto-ban.
 * Creado 2026-06-15. DRY-RUN por defecto (NO banea hasta pasar ENFORCE).
 *
 * Captura la cola LENTA-rotativa de la flota datacenter que el rate-limit sincrono
 * (regla 4a, 15/10min) no llega a cazar porque cada IP hace pocos hits antes de rotar.
 *
 * PREDICADO DE BAN (todas las condiciones, AND):
 *   valid_ipv4(ip)                                  # FILTER_FLAG_IPV4 (IPv6 NUNCA: carecer de PTR es normal RFC8501)
 *   AND observed[ip,'catalog'].hits >= NORDNS_MIN_HITS   # evidencia conductual (gate B)
 *   AND ip NO en allowlist hardcoded (exact+CIDR)
 *   AND ip NO en scraper_allowlist (DB)
 *   AND ip NO en scraper_blacklist activa             # idempotencia
 *   AND ip NO en LEGIT_NO_RDNS_CIDR                    # CF/Apple/MS/Zscaler/Fastly: legitimos sin PTR
 *   AND ip NO es cliente logueado activo (whos_online.customer_id>0)
 *   AND rdns_status(ip) == 'none'                      # DEFINITIVO: NXDOMAIN / NOERROR+0ans (NUNCA timeout/SERVFAIL)
 *   => ban 'nordns_catalog' BAN_HOURS (allowlist-safe, GREATEST: no pisa un ban mas largo)
 *
 * Hardening incorporado de la revision adversarial (workflow rdns-async-design 2026-06-15):
 *   - IPv4 only explicito (bug: FILTER_VALIDATE_IP sin flag acepta IPv6).
 *   - dig por RCODE (no +short, que confunde NXDOMAIN con SERVFAIL/timeout) + resolver explicito 1.1.1.1/8.8.8.8.
 *   - proc_open array-form (sin /bin/sh) -> sin inyeccion; ip re-validada por fila.
 *   - mysqli_report(OFF): PHP 8.1+ default STRICT abortaria el cron en cualquier error de query.
 *   - try/catch por candidato: el fallo de una IP no aborta el run.
 *   - writes parametrizados; ptr (controlado por DNS ajeno) tratado como no confiable.
 *   - cache con TTL corto para 'none' (auto-heal de PTR recien provisionado) y 'unknown'.
 *   - exencion comprador real via whos_online; exencion CDN/proxy via CIDR.
 *   - cap de lookups/run + presupuesto wall-clock.
 *
 * Uso:
 *   php cron_rdns_autoban.php          # DRY-RUN: registra WOULD-BAN, no banea
 *   php cron_rdns_autoban.php ENFORCE  # banea de verdad (solo tras revisar datos dry-run)
 */

mysqli_report(MYSQLI_REPORT_OFF); // CRITICO: opt-out del STRICT por defecto en PHP 8.1+

// ---------------- Config ----------------
const NORDNS_MIN_HITS   = 10;     // gate conductual (sync banea a 15; esto cubre lo que sync no llega)
const BAN_HOURS         = 12;
const PER_RUN_LOOKUP_CAP = 120;   // tope de resoluciones DNS nuevas por run
const RUN_BUDGET_SEC    = 240;    // presupuesto wall-clock por run
const CACHE_TTL_HAS     = 86400;  // 24h
const CACHE_TTL_NONE    = 10800;  // 3h (PTR recien creado se re-chequea pronto)
const CACHE_TTL_UNKNOWN = 3600;   // 1h
$RESOLVERS = ['1.1.1.1', '8.8.8.8'];

$ENFORCE = in_array('ENFORCE', array_map('strtoupper', array_slice($argv, 1)), true);
// Umbral conductual ajustable por argv numerico (para tuning en dry-run); default const.
$MIN_HITS = NORDNS_MIN_HITS;
foreach (array_slice($argv, 1) as $a) { if (is_numeric($a)) $MIN_HITS = max(2, (int)$a); }

// Allowlist hardcoded (espejo del guard)
$ALLOW_EXACT = ['217.127.199.171', '20.71.1.14', '80.28.193.44', '127.0.0.1'];
$ALLOW_CIDR = [
    '195.76.9.0/24', '66.249.64.0/19', '66.249.80.0/20', '216.239.32.0/19',
    '40.77.167.0/24', '157.55.39.0/24', '207.46.13.0/24', '173.252.64.0/19',
    '31.13.24.0/21', '168.100.149.0/24', '176.31.139.0/24', '114.119.0.0/16',
];

// LEGIT_NO_RDNS_CIDR — redes que legitimamente carecen de PTR pero llevan usuarios reales.
// Fuente: workflow research 2026-06-15 (feeds autoritativos CF/Apple/MS/Zscaler/Fastly).
$LEGIT_NO_RDNS_CIDR = [
    // Cloudflare (CDN + WARP egress; cubre el FP 104.28.x)
    '173.245.48.0/20','103.21.244.0/22','103.22.200.0/22','103.31.4.0/22','141.101.64.0/18',
    '108.162.192.0/18','190.93.240.0/20','188.114.96.0/20','197.234.240.0/22','198.41.128.0/17',
    '162.158.0.0/15','104.16.0.0/13','104.24.0.0/14','172.64.0.0/13','131.0.72.0/22',
    // Apple iCloud Private Relay (bloques Apple-owned; el resto cae en CF/Fastly)
    '140.248.0.0/16','172.224.0.0/12',
    // Microsoft 365 Exchange/EOP/SafeLinks (cubre el FP 104.47.x)
    '104.47.0.0/17','13.107.6.152/31','13.107.18.10/31','13.107.128.0/22','23.103.160.0/20',
    '40.92.0.0/15','40.96.0.0/13','40.104.0.0/15','40.107.0.0/16','52.96.0.0/14','52.100.0.0/14',
    '132.245.0.0/16','150.171.32.0/22',
    // Zscaler ZIA zscaler.net (cubre FPs 136.226.x / 147.161.x)
    '136.226.0.0/16','147.161.128.0/17','104.129.192.0/20','137.83.128.0/18','165.225.0.0/17',
    '165.225.192.0/18','170.85.0.0/16','185.46.212.0/22','194.9.96.0/20','199.168.148.0/23',
    '205.220.0.0/17','209.55.128.0/18','209.55.192.0/19',
    // Fastly (anycast edge; tambien partner de Apple PR)
    '23.235.32.0/20','43.249.72.0/22','103.244.50.0/24','103.245.222.0/23','103.245.224.0/24',
    '104.156.80.0/20','146.75.0.0/16','151.101.0.0/16','157.52.64.0/18','167.82.0.0/17',
    '172.111.64.0/18','185.31.16.0/22','199.27.72.0/21','199.232.0.0/16',
];

// ---------------- DB connect (patron CLI de los crons hermanos) ----------------
$confPath = '/home/francobordo/public_html/includes/configure.php';
$conf = @file_get_contents($confPath);
if ($conf === false) { fwrite(STDERR, "FATAL: no configure.php\n"); exit(1); }
preg_match("/'DB_SERVER',\s*'([^']+)'/", $conf, $m);          $DB_HOST = $m[1] ?? 'localhost';
preg_match("/'DB_SERVER_USERNAME',\s*'([^']+)'/", $conf, $m); $DB_USER = $m[1] ?? '';
preg_match("/'DB_SERVER_PASSWORD',\s*'([^']+)'/", $conf, $m); $DB_PASS = $m[1] ?? '';
preg_match("/'DB_DATABASE',\s*'([^']+)'/", $conf, $m);        $DB_NAME = $m[1] ?? '';

function lm($s) { echo '[' . date('Y-m-d H:i:s') . '] ' . $s . "\n"; }

$mysqli = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) { lm('FATAL conn: ' . $mysqli->connect_error); exit(1); }
$mysqli->set_charset('utf8mb4');

lm($ENFORCE ? '== ENFORCE ==' : '== DRY-RUN (no banea) ==');

// ---------------- Helpers ----------------
function ip_in_cidr_list(string $ip, array $list): bool {
    $ipl = ip2long($ip);
    if ($ipl === false) return false;
    foreach ($list as $cidr) {
        $p = strpos($cidr, '/');
        if ($p === false) { if ($ip === $cidr) return true; continue; }
        $snl = ip2long(substr($cidr, 0, $p));
        if ($snl === false) continue;
        $bits = (int)substr($cidr, $p + 1);
        $mask = $bits === 0 ? 0 : (-1 << (32 - $bits));
        if (($ipl & $mask) === ($snl & $mask)) return true;
    }
    return false;
}

/** dig -x via proc_open array-form (sin shell). Devuelve ['class'=>has|none|unknown,'ptr'=>?]. */
function dig_one(string $ip, string $resolver): array {
    $cmd = ['dig', '-x', $ip, '+time=2', '+tries=1', '@' . $resolver];
    $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = @proc_open($cmd, $desc, $pipes);
    if (!is_resource($proc)) return ['class' => 'unknown', 'ptr' => null];
    $out = stream_get_contents($pipes[1]);
    fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($proc);
    if ($exit !== 0 || $out === '' || $out === false) return ['class' => 'unknown', 'ptr' => null];
    if (preg_match('/connection timed out|no servers could be reached|communications error/i', $out))
        return ['class' => 'unknown', 'ptr' => null];
    if (!preg_match('/status:\s*([A-Z]+)/', $out, $sm)) return ['class' => 'unknown', 'ptr' => null];
    $status = $sm[1];
    if ($status === 'NXDOMAIN') return ['class' => 'none', 'ptr' => null];
    if ($status === 'NOERROR') {
        $ans = 0;
        if (preg_match('/ANSWER:\s*(\d+)/', $out, $am)) $ans = (int)$am[1];
        if ($ans === 0) return ['class' => 'none', 'ptr' => null];
        if (preg_match('/\sIN\s+PTR\s+(\S+)/', $out, $pm)) return ['class' => 'has', 'ptr' => rtrim($pm[1], '.')];
        return ['class' => 'has', 'ptr' => null];
    }
    return ['class' => 'unknown', 'ptr' => null]; // SERVFAIL/REFUSED/...
}

/** Resuelve con varios resolvers: devuelve en cuanto uno da definitivo (none/has). */
function rdns_status(string $ip, array $resolvers): array {
    foreach ($resolvers as $r) {
        $res = dig_one($ip, $r);
        if ($res['class'] === 'none' || $res['class'] === 'has') return $res;
    }
    return ['class' => 'unknown', 'ptr' => null];
}

// ---------------- Cargar candidatos ----------------
$candidates = [];
$q = $mysqli->query("SELECT ip, hits FROM scraper_observed WHERE reason='catalog' AND hits >= " . (int)$MIN_HITS . " ORDER BY hits DESC LIMIT 500");
if ($q) { while ($r = $q->fetch_assoc()) $candidates[] = $r; $q->free(); }
lm('Candidatos (catalog hits>=' . (int)$MIN_HITS . '): ' . count($candidates));
if (!$candidates) {
    // Aviso si la regla 4a no esta poblando (deteccion de mal cableado)
    $chk = $mysqli->query("SELECT COUNT(*) c FROM scraper_observed WHERE reason='catalog'");
    $cn = $chk ? (int)($chk->fetch_assoc()['c']) : 0;
    if ($cn === 0) lm('WARN: 0 filas reason=catalog — ¿regla 4a no puebla? feature inerte.');
    lm('Nada que hacer.'); exit(0);
}

// ---------------- Prepared statements ----------------
$st_allow = $mysqli->prepare("SELECT 1 FROM scraper_allowlist WHERE ip=? LIMIT 1");
$st_ban   = $mysqli->prepare("SELECT 1 FROM scraper_blacklist WHERE ip=? AND expires_at>NOW() LIMIT 1");
$st_cust  = $mysqli->prepare("SELECT 1 FROM whos_online WHERE ip_address=? AND customer_id>0 LIMIT 1");
$st_cget  = $mysqli->prepare("SELECT status, UNIX_TIMESTAMP(checked_at) ts FROM scraper_rdns_cache WHERE ip=? LIMIT 1");
$st_cput  = $mysqli->prepare("INSERT INTO scraper_rdns_cache (ip,status,ptr,checked_at) VALUES (?,?,?,NOW())
                              ON DUPLICATE KEY UPDATE status=VALUES(status), ptr=VALUES(ptr), checked_at=NOW()");
// ban allowlist-safe + GREATEST (no degrada un ban mas largo); reason append idempotente
$st_insban = $mysqli->prepare(
    "INSERT INTO scraper_blacklist (ip, ua, reason, expires_at)
     SELECT ?, ?, 'nordns_catalog', DATE_ADD(NOW(), INTERVAL " . BAN_HOURS . " HOUR)
     FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM scraper_allowlist WHERE ip=?)
     ON DUPLICATE KEY UPDATE
        last_seen=NOW(), hits=hits+1,
        expires_at=GREATEST(expires_at, DATE_ADD(NOW(), INTERVAL " . BAN_HOURS . " HOUR)),
        reason=IF(reason LIKE '%nordns_catalog%', reason, CONCAT(reason, '+nordns_catalog'))");
$st_obsdel = $mysqli->prepare("DELETE FROM scraper_observed WHERE ip=? AND reason='catalog'");

function cache_get($st, string $ip): ?array {
    $st->bind_param('s', $ip);
    if (!$st->execute()) return null;
    $r = $st->get_result()->fetch_assoc();
    return $r ?: null;
}

// ---------------- Bucle principal ----------------
$t0 = time();
$lookups = 0;
$stats = ['exempt_hardcoded'=>0,'exempt_cidr'=>0,'exempt_allowdb'=>0,'already_banned'=>0,
          'exempt_customer'=>0,'not_ipv4'=>0,'has_rdns'=>0,'unknown'=>0,'would_ban'=>0,'banned'=>0,'skipped_budget'=>0];

foreach ($candidates as $row) {
    $ip = trim($row['ip']);
    $hits = (int)$row['hits'];
    try {
        // 1. IPv4 estricto (tambien blinda el shell/SQL)
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) { $stats['not_ipv4']++; continue; }
        // 2. exenciones baratas primero
        if (in_array($ip, $GLOBALS['ALLOW_EXACT'], true) || ip_in_cidr_list($ip, $GLOBALS['ALLOW_CIDR'])) { $stats['exempt_hardcoded']++; continue; }
        if (ip_in_cidr_list($ip, $GLOBALS['LEGIT_NO_RDNS_CIDR'])) { $stats['exempt_cidr']++; continue; }
        if (cache_get($st_allow, $ip)) { $stats['exempt_allowdb']++; continue; }
        if (cache_get($st_ban, $ip))  { $stats['already_banned']++; continue; }
        if (cache_get($st_cust, $ip)) { $stats['exempt_customer']++; lm("EXENTO comprador-logueado $ip (hits=$hits)"); continue; }

        // 3. rDNS (cache con TTL por estado)
        $now = time();
        $cached = cache_get($st_cget, $ip);
        $status = null; $ptr = null;
        if ($cached) {
            $age = $now - (int)$cached['ts'];
            $ttl = $cached['status']==='has' ? CACHE_TTL_HAS : ($cached['status']==='none' ? CACHE_TTL_NONE : CACHE_TTL_UNKNOWN);
            if ($age < $ttl) $status = $cached['status'];
        }
        if ($status === null) {
            if ($lookups >= PER_RUN_LOOKUP_CAP || (time() - $t0) > RUN_BUDGET_SEC) { $stats['skipped_budget']++; continue; }
            $res = rdns_status($ip, $GLOBALS['RESOLVERS']);
            $lookups++;
            $status = $res['class']; $ptr = $res['ptr'];
            $pv = $ptr; $st_cput->bind_param('sss', $ip, $status, $pv); @$st_cput->execute();
        }

        if ($status === 'has')     { $stats['has_rdns']++; continue; }
        if ($status === 'unknown') { $stats['unknown']++; continue; } // NUNCA banear en incertidumbre

        // status === 'none' -> definitivo sin PTR -> candidato a ban
        if (!$ENFORCE) {
            $stats['would_ban']++;
            lm("WOULD-BAN $ip hits=$hits rcode=NXDOMAIN ptr=none");
            continue;
        }
        $uaTag = 'nordns_autoban';
        $st_insban->bind_param('sss', $ip, $uaTag, $ip);
        if ($st_insban->execute()) {
            $stats['banned']++;
            $st_obsdel->bind_param('s', $ip); @$st_obsdel->execute();
            lm("BAN $ip hits=$hits 'nordns_catalog' " . BAN_HOURS . "h");
        } else {
            lm("ERR insban $ip: " . $mysqli->error);
        }
    } catch (\Throwable $e) {
        lm("ERR candidato $ip: " . $e->getMessage()); // aislado: no aborta el run
        continue;
    }
}

// Purga autocontenida de caché rDNS antigua (evita crecimiento ilimitado; usa idx_checked).
@$mysqli->query("DELETE FROM scraper_rdns_cache WHERE checked_at < NOW() - INTERVAL 7 DAY");

lm(sprintf('Fin. lookups=%d %s', $lookups, json_encode($stats)));
$mysqli->close();
