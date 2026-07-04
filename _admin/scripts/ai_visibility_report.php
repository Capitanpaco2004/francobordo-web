<?php
/**
 * ai_visibility_report.php — Visibilidad en asistentes IA (ChatGPT & cia) para francobordo.com
 *
 * Dos modos (CLI puro, sin osCommerce — los includes de application_top rompen en CLI):
 *   (sin args)  COLECTOR: parsea incrementalmente el access log (offset + hash anti-rotacion,
 *               cPanel DESCARTA el log al rotarlo, por eso hay que recolectar cada hora)
 *               y acumula contadores por dia en el estado JSON.
 *   --report    Recolecta y ademas envia el INFORME SEMANAL por email (ultimos 7 dias completos
 *               vs 7 anteriores). Pensado para cron de los lunes.
 *   --test      Con --report: la ventana incluye HOY (datos parciales) y el asunto lleva [TEST].
 *
 * Que mide:
 *   - ChatGPT-User  = consultas EN VIVO (ChatGPT leyendo la web para responder a un usuario)
 *   - clics utm     = personas que llegan desde respuestas de ChatGPT (utm_source=chatgpt.com)
 *   - OAI-SearchBot = indexacion para ChatGPT Search | GPTBot = crawl de entrenamiento
 *   - Perplexity, Claude/Anthropic, bingbot (Copilot)
 *
 * Crons:
 *   42 * * * *  php .../ai_visibility_report.php            (colector horario)
 *   5 8 * * 1   php .../ai_visibility_report.php --report   (informe semanal, lunes)
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

const AIV_LOG_FILE  = '/home/francobordo/access-logs/francobordo.com-ssl_log';
const AIV_STATE     = '/home/francobordo/logs/ai_visibility_state.json';
const AIV_MAIL_TO   = 'f.rodriguez@francobordo.com';
const AIV_KEEP_DAYS = 35;
const AIV_MAX_PAGES = 150;  // paginas distintas guardadas por dia y categoria
const AIV_MAX_IPS   = 500;  // IPs de clics guardadas por dia

// ---------------------------------------------------------------- estado
function aiv_load_state() {
    $s = @json_decode((string)@file_get_contents(AIV_STATE), true);
    if (!is_array($s)) $s = array();
    $s += array('offset' => 0, 'sig' => '', 'days' => array());
    return $s;
}
function aiv_save_state($s) {
    // poda de dias antiguos
    $cut = date('Y-m-d', strtotime('-' . AIV_KEEP_DAYS . ' days'));
    foreach (array_keys($s['days']) as $d) if ($d < $cut) unset($s['days'][$d]);
    $tmp = AIV_STATE . '.tmp';
    file_put_contents($tmp, json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    rename($tmp, AIV_STATE);
}
function aiv_day(&$s, $ymd) {
    if (!isset($s['days'][$ymd])) {
        $s['days'][$ymd] = array(
            'oai' => 0, 'cgu' => 0, 'gpt' => 0, 'pplx' => 0, 'claude' => 0, 'bing' => 0,
            'clicks' => 0, 'ref_clicks' => 0, 'click_ips' => array(),
            'cgu_pages' => array(), 'click_pages' => array(),
        );
    }
    return $ymd;
}

// ---------------------------------------------------------------- colector
function aiv_collect() {
    $s = aiv_load_state();
    if (!is_readable(AIV_LOG_FILE)) { echo date('c') . " ERROR: log ilegible\n"; return $s; }

    $fh = fopen(AIV_LOG_FILE, 'r');
    if (!$fh) { echo date('c') . " ERROR: no se pudo abrir el log\n"; return $s; }

    // firma anti-rotacion: md5 de los primeros 200 bytes; si cambia (o el fichero encogio), empezamos de 0
    $head = (string)fread($fh, 200);
    $sig  = md5($head);
    $size = filesize(AIV_LOG_FILE);
    if ($sig !== $s['sig'] || $size < $s['offset']) { $s['offset'] = 0; $s['sig'] = $sig; }
    fseek($fh, $s['offset']);

    $botRe   = '/bot|crawl|spider|slurp|OAI-SearchBot|ChatGPT-User|GPTBot|Perplexity|Claude|anthropic|python|curl|wget|libwww|Go-http|HeadlessChrome|facebookexternal/i';
    $assetRe = '/\.(js|css|png|jpe?g|gif|webp|svg|ico|woff2?|ttf|eot|map|txt|xml|pdf)(\?|$)/i';
    $lineRe  = '/^(\S+) \S+ \S+ \[([^:\]]+)[^\]]*\] "(?:\S+) (\S+)[^"]*" \d+ \S+ "([^"]*)" "([^"]*)"/';
    $dateMap = array(); // '03/Jul/2026' => '2026-07-03'
    $n = 0;

    while (($line = fgets($fh)) !== false) {
        // prefiltro barato: solo analizamos lineas que puedan interesar
        if (stripos($line, 'OAI-SearchBot') === false && stripos($line, 'ChatGPT-User') === false
            && stripos($line, 'GPTBot') === false && stripos($line, 'Perplexity') === false
            && stripos($line, 'Claude') === false && stripos($line, 'anthropic') === false
            && stripos($line, 'bingbot') === false && stripos($line, 'utm_source=chatgpt') === false
            && stripos($line, 'https://chatgpt.com') === false) continue;
        if (!preg_match($lineRe, $line, $m)) continue;

        list(, $ip, $dRaw, $path, $ref, $ua) = $m;
        if (!isset($dateMap[$dRaw])) {
            $dt = DateTime::createFromFormat('d/M/Y', $dRaw);
            $dateMap[$dRaw] = $dt ? $dt->format('Y-m-d') : date('Y-m-d');
        }
        $d = aiv_day($s, $dateMap[$dRaw]);
        $day = &$s['days'][$d];
        $pathClean = preg_replace('/\?.*$/', '', $path);
        $n++;

        // --- bots IA ---
        if (stripos($ua, 'OAI-SearchBot') !== false) { $day['oai']++; continue; }
        if (stripos($ua, 'ChatGPT-User') !== false) {
            $day['cgu']++;
            if (!preg_match($assetRe, $pathClean)) {
                if (isset($day['cgu_pages'][$pathClean])) $day['cgu_pages'][$pathClean]++;
                elseif (count($day['cgu_pages']) < AIV_MAX_PAGES) $day['cgu_pages'][$pathClean] = 1;
            }
            continue;
        }
        if (stripos($ua, 'GPTBot') !== false)   { $day['gpt']++; continue; }
        if (stripos($ua, 'Perplexity') !== false) { $day['pplx']++; continue; }
        if (stripos($ua, 'Claude') !== false || stripos($ua, 'anthropic') !== false) { $day['claude']++; continue; }
        if (stripos($ua, 'bingbot') !== false)  { $day['bing']++; continue; }

        // --- humanos llegando desde ChatGPT ---
        if (preg_match($botRe, $ua)) continue;              // por si algun bot raro llego aqui
        $isUtm = (stripos($path, 'utm_source=chatgpt') !== false)
              || (stripos($ref, 'utm_source=chatgpt') !== false);
        $isRef = (stripos($ref, 'https://chatgpt.com') === 0);
        if (!$isUtm && !$isRef) continue;
        if (preg_match($assetRe, $pathClean)) continue;     // solo paginas, no recursos

        if ($isUtm) $day['clicks']++;
        if ($isRef) $day['ref_clicks']++;
        if (count($day['click_ips']) < AIV_MAX_IPS) $day['click_ips'][$ip] = 1;
        if (isset($day['click_pages'][$pathClean])) $day['click_pages'][$pathClean]++;
        elseif (count($day['click_pages']) < AIV_MAX_PAGES) $day['click_pages'][$pathClean] = 1;
    }

    $s['offset'] = ftell($fh);
    fclose($fh);
    aiv_save_state($s);
    echo date('c') . " collect OK: $n lineas relevantes, offset=" . $s['offset'] . "\n";
    return $s;
}

// ---------------------------------------------------------------- informe
function aiv_window_sum($s, $from, $to) {
    $t = array('oai' => 0, 'cgu' => 0, 'gpt' => 0, 'pplx' => 0, 'claude' => 0, 'bing' => 0,
               'clicks' => 0, 'ref_clicks' => 0, 'ips' => array(), 'cgu_pages' => array(), 'click_pages' => array());
    for ($d = strtotime($from); $d <= strtotime($to); $d += 86400) {
        $k = date('Y-m-d', $d);
        if (!isset($s['days'][$k])) continue;
        $day = $s['days'][$k];
        foreach (array('oai','cgu','gpt','pplx','claude','bing','clicks','ref_clicks') as $f) $t[$f] += $day[$f];
        foreach ($day['click_ips'] as $ip => $x) $t['ips'][$ip] = 1;
        foreach ($day['cgu_pages'] as $p => $c) $t['cgu_pages'][$p] = ($t['cgu_pages'][$p] ?? 0) + $c;
        foreach ($day['click_pages'] as $p => $c) $t['click_pages'][$p] = ($t['click_pages'][$p] ?? 0) + $c;
    }
    return $t;
}
function aiv_delta($now, $prev) {
    if ($prev <= 0) return $now > 0 ? '(nuevo)' : '';
    $pct = round((($now - $prev) / $prev) * 100);
    return sprintf('(%s%d%% vs anterior: %s)', $pct >= 0 ? '+' : '', $pct, number_format($prev, 0, ',', '.'));
}
function aiv_top($arr, $n) {
    arsort($arr);
    $out = array();
    foreach (array_slice($arr, 0, $n, true) as $p => $c) $out[] = sprintf('  %5d x %s', $c, $p);
    return $out ? implode("\n", $out) : '  (sin datos)';
}

function aiv_report($test = false) {
    $s = aiv_load_state();
    $endCur   = $test ? date('Y-m-d') : date('Y-m-d', strtotime('-1 day'));
    $startCur = date('Y-m-d', strtotime($endCur . ' -6 days'));
    $endPrev  = date('Y-m-d', strtotime($startCur . ' -1 day'));
    $startPrev= date('Y-m-d', strtotime($endPrev . ' -6 days'));

    $cur  = aiv_window_sum($s, $startCur, $endCur);
    $prev = aiv_window_sum($s, $startPrev, $endPrev);
    $fmt  = function ($n) { return number_format($n, 0, ',', '.'); };

    $subject = ($test ? '[TEST] ' : '') . 'Visibilidad IA francobordo.com — semana ' .
               date('d/m', strtotime($startCur)) . '–' . date('d/m', strtotime($endCur));

    $b   = array();
    $b[] = 'INFORME SEMANAL DE VISIBILIDAD EN ASISTENTES IA — francobordo.com';
    $b[] = 'Ventana: ' . $startCur . ' a ' . $endCur . '  (comparado con ' . $startPrev . ' a ' . $endPrev . ')';
    if ($test) $b[] = '*** INFORME DE PRUEBA: la ventana incluye HOY (datos parciales) ***';
    $b[] = '';
    $b[] = '== CHATGPT ==';
    $b[] = 'Consultas EN VIVO (ChatGPT-User): ' . $fmt($cur['cgu']) . ' ' . aiv_delta($cur['cgu'], $prev['cgu']);
    $b[] = '  -> Cada una = ChatGPT leyendo nuestra web para responder a un usuario real.';
    $b[] = 'Clics humanos desde respuestas (utm_source=chatgpt): ' . $fmt($cur['clicks']) .
           ' de ' . $fmt(count($cur['ips'])) . ' visitantes unicos ' . aiv_delta($cur['clicks'], $prev['clicks']);
    $b[] = 'Clics con referrer chatgpt.com: ' . $fmt($cur['ref_clicks']) . ' ' . aiv_delta($cur['ref_clicks'], $prev['ref_clicks']);
    $b[] = 'Indexacion OAI-SearchBot: ' . $fmt($cur['oai']) . ' ' . aiv_delta($cur['oai'], $prev['oai']);
    $b[] = 'Entrenamiento GPTBot: ' . $fmt($cur['gpt']) . ' ' . aiv_delta($cur['gpt'], $prev['gpt']);
    $b[] = '';
    $b[] = '== OTROS ASISTENTES ==';
    $b[] = 'Perplexity: ' . $fmt($cur['pplx']) . ' ' . aiv_delta($cur['pplx'], $prev['pplx']) .
           '  |  Claude/Anthropic: ' . $fmt($cur['claude']) . ' ' . aiv_delta($cur['claude'], $prev['claude']) .
           '  |  bingbot (Copilot): ' . $fmt($cur['bing']) . ' ' . aiv_delta($cur['bing'], $prev['bing']);
    $b[] = '';
    $b[] = '== TOP 10 PAGINAS QUE CHATGPT CONSULTO EN VIVO ==';
    $b[] = aiv_top($cur['cgu_pages'], 10);
    $b[] = '';
    $b[] = '== TOP 10 PAGINAS CLICADAS DESDE CHATGPT ==';
    $b[] = aiv_top($cur['click_pages'], 10);
    $b[] = '';
    $b[] = 'Notas: los clics son INFRACUENTA (la app movil de ChatGPT no manda referrer ni utm).';
    $b[] = 'Complementos: GA4 (fuente=chatgpt.com), Bing Webmaster Tools > AI Performance (Copilot).';
    $b[] = 'Fuente: access log nic1 (colector horario; el log rota y se descarta, de ahi el colector).';

    $ok = aiv_send_mail($subject, implode("\n", $b));
    echo date('c') . ' report ' . ($ok ? 'ENVIADO' : 'FALLO ENVIO') . ": $subject\n";
}

// ---------------------------------------------------------------- email (patron gm_health_cron: SendGrid REST + fallback)
function aiv_send_mail($subject, $body) {
    $cfg = array();
    if (is_file('/home/francobordo/public_html/includes/configure.php')) {
        include_once '/home/francobordo/public_html/includes/configure.php';
        $db = @new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
        if ($db && !$db->connect_errno) {
            $r = $db->query("SELECT configuration_key k, configuration_value v FROM configuration WHERE configuration_key IN ('SMTP_PASS','EMAIL_FROM')");
            while ($r && ($x = $r->fetch_assoc())) $cfg[$x['k']] = $x['v'];
        }
    }
    $auto = '/home/francobordo/public_html/includes/vendor/autoload.php';
    if (!empty($cfg['SMTP_PASS']) && is_file($auto)) {
        require_once $auto;
        $key = '';
        try { if (class_exists('\util\tools')) $key = (string)\util\tools::decrypt($cfg['SMTP_PASS']); } catch (Throwable $e) { $key = ''; }
        if (strpos($key, 'SG.') === 0) {
            $from = !empty($cfg['EMAIL_FROM']) ? $cfg['EMAIL_FROM'] : 'info@francobordo.com';
            $payload = json_encode(array(
                'personalizations' => array(array('to' => array(array('email' => AIV_MAIL_TO)))),
                'from'             => array('email' => $from, 'name' => 'Visibilidad IA'),
                'subject'          => $subject,
                'content'          => array(array('type' => 'text/plain', 'value' => $body)),
                'mail_settings'    => array('bypass_list_management' => array('enable' => true)),
            ), JSON_UNESCAPED_UNICODE);
            $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $key, 'Content-Type: application/json'),
                CURLOPT_TIMEOUT => 15,
            ));
            $resp = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            if ($code >= 200 && $code < 300) return true;
            echo date('c') . " MAIL-REST SendGrid HTTP $code: " . substr((string)$resp, 0, 200) . " (fallback a mail())\n";
        }
    }
    return @mail(AIV_MAIL_TO, $subject, $body,
        "From: Visibilidad IA <info@francobordo.com>\r\nContent-Type: text/plain; charset=UTF-8");
}

// ---------------------------------------------------------------- main
$args = array_slice($argv, 1);
aiv_collect();
if (in_array('--report', $args, true)) aiv_report(in_array('--test', $args, true));
