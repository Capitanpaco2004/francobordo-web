<?php
/**
 * gm_health_cron.php — Vigilancia diaria de productos desaprobados en Google
 * Merchant Center (cuenta 7605527). CLI puro, sin osCommerce (la clase
 * google_merchant es autónoma — los includes de application_top rompen en CLI).
 *
 * - Consulta product_view (Merchant API reports/v1) → desaprobados + motivos.
 * - Snapshot en /home/francobordo/gm_snapshots/{YYYYMMDD.json, latest.json}.
 * - Compara con el snapshot anterior y avisa por email si hay nuevos/resueltos.
 * - Log de una línea por ejecución en /home/francobordo/gm_health.log (vía cron >>).
 *
 * Cron: 25 7 * * * php /home/francobordo/public_html/_admin/scripts/gm_health_cron.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require '/home/francobordo/public_html/includes/classes/google_merchant.php';

const GM_SNAP_DIR = '/home/francobordo/gm_snapshots';
const GM_MAIL_TO  = 'f.rodriguez@francobordo.com';

/**
 * Envío por el SMTP de la tienda (MailPlus autenticado, igual que los emails del
 * checkout — el SPF del dominio no incluye nic1 y DMARC va en p=reject, así que
 * el mail() local sería rechazado). Fallback a mail() si algo no está.
 */
function gm_send_mail($subject, $body) {
    $cfg = array();
    if (is_file('/home/francobordo/public_html/includes/configure.php')) {
        include_once '/home/francobordo/public_html/includes/configure.php';
        $db = @new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
        if ($db && !$db->connect_errno) {
            $r = $db->query("SELECT configuration_key k, configuration_value v FROM configuration WHERE configuration_key IN ('SMTP_HOST','SMTP_PUERTO','SMTP_USER','SMTP_PASS','EMAIL_FROM')");
            while ($r && ($x = $r->fetch_assoc())) $cfg[$x['k']] = $x['v'];
        }
    }
    // Mismo camino que tep_mail() para destinos @francobordo.com: SMTP_PASS está
    // ENCRIPTADA en BD (util\tools::decrypt → API key SG.xxx) y se envía por la
    // REST API de SendGrid con bypass_list_management (el caché de bounces de
    // SendGrid se comía emails a internos; la REST con bypass garantiza entrega).
    $auto = '/home/francobordo/public_html/includes/vendor/autoload.php';
    if (!empty($cfg['SMTP_PASS']) && is_file($auto)) {
        require_once $auto;
        $key = '';
        try {
            if (class_exists('\util\tools')) $key = (string)\util\tools::decrypt($cfg['SMTP_PASS']);
        } catch (Throwable $e) { $key = ''; }
        if (strpos($key, 'SG.') === 0) {
            $from = !empty($cfg['EMAIL_FROM']) ? $cfg['EMAIL_FROM'] : 'info@francobordo.com';
            $payload = json_encode(array(
                'personalizations' => array(array('to' => array(array('email' => GM_MAIL_TO)))),
                'from'             => array('email' => $from, 'name' => 'GMC Watch'),
                'subject'          => $subject,
                'content'          => array(array('type' => 'text/plain', 'value' => $body)),
                'mail_settings'    => array('bypass_list_management' => array('enable' => true)),
            ), JSON_UNESCAPED_UNICODE);
            $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => array('Authorization: Bearer ' . $key, 'Content-Type: application/json'),
                CURLOPT_TIMEOUT        => 15,
            ));
            $resp = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            if ($code >= 200 && $code < 300) return true;
            echo date('c') . " MAIL-REST SendGrid HTTP $code: " . substr((string)$resp, 0, 200) . " (fallback a mail())\n";
        } else {
            echo date('c') . " MAIL: SMTP_PASS no descifra a una key SG. (fallback a mail())\n";
        }
    }
    return @mail(GM_MAIL_TO, $subject, $body,
        "From: GMC Watch <info@francobordo.com>\r\nContent-Type: text/plain; charset=UTF-8");
}

if (in_array('--testmail', (array)$argv, true)) {
    $ok = gm_send_mail('[GMC] Prueba del canal de avisos', "Si lees esto, los avisos de desaprobados llegan bien.\n" . date('c'));
    echo $ok ? "testmail enviado\n" : "testmail FALLO\n";
    exit($ok ? 0 : 1);
}

$gm = new google_merchant();
if (!$gm->configured()) { echo date('c') . " CONFIG: " . $gm->error() . "\n"; exit(1); }

$q = "SELECT offer_id, id, title, brand, aggregated_reporting_context_status, item_issues "
   . "FROM product_view WHERE aggregated_reporting_context_status = 'NOT_ELIGIBLE_OR_DISAPPROVED'";
$r = $gm->reportSearch($q, 50000);
if ($r['code'] !== 200) { echo date('c') . " API: " . $r['error'] . "\n"; exit(1); }

$offers = array();   // offerId => array(titulo, codigos)
$porIssue = array(); // code => count
foreach ((array)$r['data']['results'] as $row) {
    if (!isset($row['productView'])) continue;
    $pv = $row['productView'];
    $oid = (string)($pv['offerId'] ?? '');
    if ($oid === '') continue;
    $codes = array();
    foreach ((array)($pv['itemIssues'] ?? array()) as $ii) {
        $c = $ii['type']['code'] ?? 'desconocido';
        $codes[] = $c;
        $porIssue[$c] = ($porIssue[$c] ?? 0) + 1;
    }
    $offers[$oid] = array('t' => (string)($pv['title'] ?? ''), 'c' => array_values(array_unique($codes)));
}
arsort($porIssue);
$total = count($offers);

if (!is_dir(GM_SNAP_DIR)) { mkdir(GM_SNAP_DIR, 0700, true); }

$prev = null;
$latestFile = GM_SNAP_DIR . '/latest.json';
if (is_file($latestFile)) $prev = json_decode((string)@file_get_contents($latestFile), true);

$prevOffers = is_array($prev) && isset($prev['offers']) ? $prev['offers'] : null;
$nuevos = $resueltos = array();
if (is_array($prevOffers)) {
    foreach ($offers as $oid => $d) if (!isset($prevOffers[$oid])) $nuevos[$oid] = $d;
    foreach ($prevOffers as $oid => $d) if (!isset($offers[$oid])) $resueltos[$oid] = $d;
}

$snap = array(
    'fecha'     => date('Y-m-d H:i'),
    'total'     => $total,
    'nuevos'    => count($nuevos),
    'resueltos' => count($resueltos),
    'issues'    => $porIssue,
    'offers'    => $offers,
);
@file_put_contents(GM_SNAP_DIR . '/' . date('Ymd') . '.json', json_encode($snap, JSON_UNESCAPED_UNICODE));
@file_put_contents($latestFile, json_encode($snap, JSON_UNESCAPED_UNICODE));

// retención: borrar snapshots fechados > 60 días
foreach ((array)glob(GM_SNAP_DIR . '/20*.json') as $f) {
    if (filemtime($f) < time() - 60 * 86400) @unlink($f);
}

echo date('c') . " total=$total nuevos=" . count($nuevos) . " resueltos=" . count($resueltos) . "\n";

// email: primera ejecución (línea base) o cuando hay cambios
$esBaseline = !is_array($prevOffers);
if ($esBaseline || count($nuevos) || count($resueltos)) {
    $subject = '[GMC] ' . $total . ' productos desaprobados'
             . ($esBaseline ? ' (línea base)' : ' (+' . count($nuevos) . ' / -' . count($resueltos) . ')');
    $body = "Vigilancia Google Merchant Center — " . date('d/m/Y H:i') . "\n\n";
    $body .= "Total desaprobados: $total\n\n";
    $body .= "Por motivo:\n";
    $i = 0;
    foreach ($porIssue as $c => $n) { $body .= sprintf("  %-45s %d\n", $c, $n); if (++$i >= 12) break; }
    if (count($nuevos)) {
        $body .= "\nNUEVOS desde ayer (" . count($nuevos) . "):\n";
        $i = 0;
        foreach ($nuevos as $oid => $d) {
            $body .= "  #$oid — {$d['t']} [" . implode(', ', $d['c']) . "]\n";
            if (++$i >= 30) { $body .= "  … y " . (count($nuevos) - 30) . " más\n"; break; }
        }
    }
    if (count($resueltos)) $body .= "\nResueltos desde ayer: " . count($resueltos) . "\n";
    $body .= "\nDetalle: https://www.francobordo.com/_admin/google_merchant_catalogo.php\n";

    gm_send_mail($subject, $body);
}
