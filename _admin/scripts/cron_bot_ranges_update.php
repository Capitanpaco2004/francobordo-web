<?php
/**
 * cron_bot_ranges_update.php — Descarga los rangos de IP que los proveedores publican
 * para sus crawlers y genera includes/bot_ranges.inc.php (consumido por scraper_guard.php).
 *
 * MOTIVO (2026-08-29): se detecto una campana de escaneo de credenciales/SSRF que declara
 * User-Agents de crawlers IA (GPTBot, ClaudeBot, Claude-User, PerplexityBot, OAI-SearchBot,
 * GrokBot...) para colarse por la exencion `declared_bot_regex` del guard. Esos crawlers NO
 * tienen PTR fiable, asi que el FCrDNS del radar (cron_scraper_radar.php) no los cubre; los
 * proveedores publican en su lugar listas JSON de prefijos. Este cron las cachea en local.
 *
 * DISENO FP-SAFE (leccion del incidente 2026-06-25, ver memoria francobordo_anti_scraper):
 *   - Si un feed falla, se CONSERVAN los prefijos previos de esa familia (no se vacia nunca).
 *   - Si el fichero generado no existe o esta rancio (>7 dias), el guard hace FAIL-OPEN:
 *     vuelve a confiar en el UA como hasta ahora. Un fallo de red NUNCA marca bots como falsos.
 *   - Escritura atomica (tmp + rename) para que el guard no lea un fichero a medias.
 *
 * Uso:  php /home/francobordo/public_html/_admin/scripts/cron_bot_ranges_update.php [--dry-run]
 * Cron: 25 4 * * * /usr/bin/php /home/francobordo/public_html/_admin/scripts/cron_bot_ranges_update.php >> /home/francobordo/logs/bot_ranges.log 2>&1
 */

// OJO: en nic1 `/usr/bin/php` es el wrapper CGI (SAPI cgi-fcgi), NO el CLI real
// (`/usr/local/bin/php`). Una guarda `PHP_SAPI !== 'cli'` a secas rechazaba el cron: 3 dias
// sin actualizar rangos (2026-08-29 -> 09-01). Lo que hay que impedir es la ejecucion SERVIDA
// POR WEB, y eso se detecta por REQUEST_METHOD/REMOTE_ADDR, no por el SAPI.
if (!empty($_SERVER['REQUEST_METHOD']) || !empty($_SERVER['REMOTE_ADDR'])) {
    http_response_code(403);
    exit("Solo por linea de comandos
");
}

const OUT_FILE = '/home/francobordo/public_html/includes/bot_ranges.inc.php';
const LOCK_FILE = '/home/francobordo/tmp/bot_ranges.lock';
const HTTP_TIMEOUT = 25;
const UA = 'FrancobordoBotRangeUpdater/1.0 (+https://www.francobordo.com)';

$DRY = in_array('--dry-run', $argv, true);

/**
 * familia => lista de URLs con el JSON de prefijos.
 * Las 3 listas de Google se funden en una sola familia 'google': AdsBot/Storebot/Inspection
 * aparecen repartidos entre special-crawlers y user-triggered-fetchers, y separarlas solo
 * generaria falsos "spoof" cuando un crawler de Google sale por el rango de otro.
 */
$FEEDS = [
    'gptbot'          => ['https://openai.com/gptbot.json'],
    'oai-searchbot'   => ['https://openai.com/searchbot.json'],
    'chatgpt-user'    => ['https://openai.com/chatgpt-user.json'],
    'anthropic'       => ['https://claude.com/crawling/bots.json'],
    'perplexitybot'   => ['https://www.perplexity.ai/perplexitybot.json'],
    'perplexity-user' => ['https://www.perplexity.ai/perplexity-user.json'],
    'google'          => [
        'https://developers.google.com/search/apis/ipranges/googlebot.json',
        'https://developers.google.com/search/apis/ipranges/special-crawlers.json',
        'https://developers.google.com/search/apis/ipranges/user-triggered-fetchers.json',
    ],
    'bingbot'         => ['https://www.bing.com/toolbox/bingbot.json'],
];

function lm(string $m): void { echo '[' . date('Y-m-d H:i:s') . "] $m\n"; }

// ---- Lock (evita solapes si el cron se dispara dos veces) ----
@mkdir(dirname(LOCK_FILE), 0755, true);
$lock = @fopen(LOCK_FILE, 'c');
if ($lock && !flock($lock, LOCK_EX | LOCK_NB)) { lm('Otra instancia en marcha, salgo.'); exit(0); }

lm($DRY ? '== DRY-RUN (no escribe) ==' : '== Actualizando rangos de bots ==');

// ---- Carga lo previo (para conservar familias cuyo feed falle hoy) ----
$prev = [];
if (is_readable(OUT_FILE)) {
    $p = @include OUT_FILE;
    if (is_array($p) && isset($p['families']) && is_array($p['families'])) $prev = $p['families'];
}

function http_get(string $url): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,   // Google redirige /static/search/... -> /search/...
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => HTTP_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT      => UA,     // Anthropic devuelve 403 sin UA
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    // sin curl_close(): deprecado en PHP 8.5 (no-op desde 8.0)
    if ($body === false || $code !== 200) { lm("  WARN $url -> HTTP $code $err"); return null; }
    return $body;
}

/** '1.2.3.0/24' => [inicio, fin] como enteros sin signo. null si no es IPv4 valido. */
function v4_range(string $cidr): ?array {
    $p = strpos($cidr, '/');
    if ($p === false) return null;
    $net = substr($cidr, 0, $p);
    $bits = (int)substr($cidr, $p + 1);
    if ($bits < 0 || $bits > 32) return null;
    $n = ip2long($net);
    if ($n === false) return null;
    $n &= 0xFFFFFFFF;
    $mask = $bits === 0 ? 0 : ((-1 << (32 - $bits)) & 0xFFFFFFFF);
    $start = $n & $mask;
    $end   = $start | (~$mask & 0xFFFFFFFF);
    return [$start, $end];
}

/** '2001:db8::/32' => [prefijo binario en hex, bits]. null si no es IPv6 valido. */
function v6_prefix(string $cidr): ?array {
    $p = strpos($cidr, '/');
    if ($p === false) return null;
    $net = substr($cidr, 0, $p);
    $bits = (int)substr($cidr, $p + 1);
    if ($bits < 0 || $bits > 128) return null;
    $bin = @inet_pton($net);
    if ($bin === false || strlen($bin) !== 16) return null;
    return [bin2hex($bin), $bits];
}

$families = [];
$stats = [];
foreach ($FEEDS as $fam => $urls) {
    $v4 = []; $v6 = []; $ok = 0;
    foreach ($urls as $url) {
        $body = http_get($url);
        if ($body === null) continue;
        $d = json_decode($body, true);
        if (!is_array($d) || empty($d['prefixes']) || !is_array($d['prefixes'])) {
            lm("  WARN $url -> JSON sin 'prefixes'");
            continue;
        }
        foreach ($d['prefixes'] as $pref) {
            if (!empty($pref['ipv4Prefix'])) { $r = v4_range($pref['ipv4Prefix']); if ($r) $v4[] = $r; }
            elseif (!empty($pref['ipv6Prefix'])) { $r = v6_prefix($pref['ipv6Prefix']); if ($r) $v6[] = $r; }
        }
        $ok++;
    }

    // Ningun feed de la familia respondio -> conservar lo anterior (nunca vaciar).
    if ($ok === 0) {
        if (isset($prev[$fam])) {
            $families[$fam] = $prev[$fam];
            $stats[$fam] = 'FALLO feed -> conservados ' . count($prev[$fam]['v4'] ?? []) . ' v4 previos';
        } else {
            $stats[$fam] = 'FALLO feed y sin datos previos -> familia AUSENTE (guard hara fail-open)';
        }
        continue;
    }

    usort($v4, static fn($a, $b) => $a[0] <=> $b[0]);   // ordenado para busqueda binaria en el guard
    $families[$fam] = ['v4' => $v4, 'v6' => $v6];
    $stats[$fam] = count($v4) . ' v4 / ' . count($v6) . ' v6 (' . $ok . '/' . count($urls) . ' feeds)';
}

foreach ($stats as $fam => $s) lm(sprintf('  %-16s %s', $fam, $s));

// Salvaguarda: si TODO fallo y no hay nada previo, no toques el fichero.
$total_v4 = array_sum(array_map(static fn($f) => count($f['v4'] ?? []), $families));
if ($total_v4 === 0) { lm('ERROR: 0 prefijos IPv4 en total, NO se reescribe el fichero.'); exit(1); }

$payload = ['generated_at' => time(), 'families' => $families];
$php = "<?php\n"
     . "// GENERADO AUTOMATICAMENTE por _admin/scripts/cron_bot_ranges_update.php — NO EDITAR A MANO.\n"
     . '// ' . date('Y-m-d H:i:s') . " — " . $total_v4 . " prefijos IPv4 de " . count($families) . " familias.\n"
     . 'return ' . var_export($payload, true) . ";\n";

if ($DRY) { lm('DRY-RUN: ' . strlen($php) . ' bytes que se habrian escrito en ' . OUT_FILE); exit(0); }

$tmp = OUT_FILE . '.tmp' . getmypid();
if (file_put_contents($tmp, $php, LOCK_EX) === false) { lm('ERROR escribiendo ' . $tmp); exit(1); }
// Valida sintaxis antes de publicar: un fichero roto tumbaria toda la tienda.
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $out, $rc);
if ($rc !== 0) { lm('ERROR php -l sobre el temporal: ' . implode(' ', $out)); @unlink($tmp); exit(1); }
@chmod($tmp, 0644);
if (!rename($tmp, OUT_FILE)) { lm('ERROR renombrando a ' . OUT_FILE); @unlink($tmp); exit(1); }

// El guard lo sirve el SAPI web -> invalidar OPcache (bytecode stale conocido en nic1).
if (function_exists('opcache_invalidate')) @opcache_invalidate(OUT_FILE, true);

lm('OK -> ' . OUT_FILE . ' (' . $total_v4 . ' prefijos IPv4, ' . strlen($php) . ' bytes)');
exit(0);
