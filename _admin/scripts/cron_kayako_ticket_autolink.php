<?php
/**
 * cron_kayako_ticket_autolink.php — auto-vincula tickets de Kayako a pedidos.
 *
 * Pide a soporte.francobordo.com (fb_ticket_lookup.php, POST + token) los
 * tickets de los últimos N días cuyo asunto contiene un número de pedido
 * (\b10\d{6}\b, p. ej. "Actualizacion de pedido (Nº de Pedido: 10364109)")
 * y los inserta en orders_kayako_tickets — la caja "Tickets Kayako" de
 * _admin/orders.php.
 *
 * Guardas:
 *   - el número extraído debe ser un pedido existente,
 *   - el email del pedido debe estar entre los emails del ticket (email,
 *     replyto o emails del usuario de Kayako) → evita vincular pedidos de
 *     otro cliente por un número tecleado mal en el asunto,
 *   - UNIQUE (orders_id, ticket_mask) + INSERT IGNORE → idempotente.
 *
 * Uso:
 *   php cron_kayako_ticket_autolink.php DRY        # reporta sin tocar
 *   php cron_kayako_ticket_autolink.php            # ejecuta
 *   php cron_kayako_ticket_autolink.php --days=7   # ventana (def. 3, máx 30)
 *
 * Crontab: 40 * * * * → logs/kayako_autolink.log
 * 2026-07-14
 */

$confPath = '/home/francobordo/public_html/includes/configure.php';
$conf = file_get_contents($confPath);
preg_match("/'DB_SERVER',\s*'([^']+)'/", $conf, $m); $DB_HOST = $m[1] ?? 'localhost';
preg_match("/'DB_SERVER_USERNAME',\s*'([^']+)'/", $conf, $m); $DB_USER = $m[1];
preg_match("/'DB_SERVER_PASSWORD',\s*'([^']+)'/", $conf, $m); $DB_PASS = $m[1];
preg_match("/'DB_DATABASE',\s*'([^']+)'/", $conf, $m); $DB_NAME = $m[1];

$LOOKUP_URL   = 'https://soporte.francobordo.com/fb_ticket_lookup.php';
$LOOKUP_TOKEN = '07695f6ad31a105652e9ceb682d60aaddb456dd541d6ab5268ac70a9f29e1073';

$dryRun = false;
$days = 3;
foreach (array_slice($argv, 1) as $a) {
    if (strtoupper($a) === 'DRY' || $a === '--dry-run') $dryRun = true;
    if (preg_match('/^--days=(\d+)$/', $a, $m2)) $days = max(1, min(30, (int)$m2[1]));
}

function logMsg($s) { echo '[' . date('Y-m-d H:i:s') . '] ' . $s . "\n"; }

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_error) { logMsg('ERROR conexion: ' . $mysqli->connect_error); exit(1); }
$mysqli->set_charset('utf8');

logMsg('Modo: ' . ($dryRun ? 'DRY-RUN' : 'EXECUTE') . " (ultimos $days dias)");

// 1. Tickets recientes con numero de pedido en el asunto
$ch = curl_init($LOOKUP_URL);
curl_setopt_array($ch, array(
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query(array('token' => $LOOKUP_TOKEN, 'recent_days' => $days)),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 20,
));
$body = curl_exec($ch);
$json = is_string($body) ? json_decode($body, true) : null;
if (!is_array($json) || empty($json['ok'])) {
    logMsg('ERROR: Kayako no responde o respuesta invalida: ' . substr((string)$body, 0, 150));
    exit(1);
}
logMsg('Tickets candidatos: ' . count($json['tickets']));

// 2. Extraer numeros de pedido del asunto, verificar y vincular
$nLinked = 0; $nDup = 0; $nSkipEmail = 0; $nNoOrder = 0;
foreach ($json['tickets'] as $t) {
    $mask    = strtoupper(trim((string)($t['mask'] ?? '')));
    $subject = (string)($t['subject'] ?? '');
    if (!preg_match('/^[A-Z]{3}-\d{3}-\d{4,6}$/', $mask)) continue;
    if (!preg_match_all('/\b(10\d{6})\b/', $subject, $mm)) continue;
    $emails = array_map('strtolower', array_map('strval', (array)($t['emails'] ?? array())));

    foreach (array_unique($mm[1]) as $orderId) {
        $orderId = (int)$orderId;
        $r = $mysqli->query("SELECT LOWER(customers_email_address) AS email FROM orders WHERE orders_id = $orderId");
        if (!$r || !$r->num_rows) { $nNoOrder++; continue; } // numero que no es un pedido real
        $orderEmail = strtolower(trim((string)$r->fetch_assoc()['email']));
        if ($orderEmail === '' || !in_array($orderEmail, $emails, true)) {
            logMsg("SKIP $mask -> pedido $orderId: el email del pedido no esta entre los del ticket");
            $nSkipEmail++;
            continue;
        }
        $maskEsc = $mysqli->real_escape_string($mask);
        $r2 = $mysqli->query("SELECT id FROM orders_kayako_tickets WHERE orders_id = $orderId AND ticket_mask = '$maskEsc'");
        if ($r2 && $r2->num_rows) { $nDup++; continue; }

        logMsg(($dryRun ? '[DRY] ' : '') . "VINCULA $mask -> pedido $orderId | $subject");
        if ($dryRun) { $nLinked++; continue; }
        // utf8mb3: fuera caracteres de 4 bytes (emojis) del asunto
        $subj = (string)preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $subject);
        $subj = $mysqli->real_escape_string(mb_substr($subj, 0, 255));
        $ok = $mysqli->query("INSERT IGNORE INTO orders_kayako_tickets (orders_id, ticket_mask, subject, date_added, added_by) VALUES ($orderId, '$maskEsc', '$subj', NOW(), 'cron-auto')");
        if (!$ok) { logMsg('ERROR INSERT: ' . $mysqli->error); continue; }
        if ($mysqli->affected_rows > 0) $nLinked++;
    }
}

logMsg("Resumen: vinculados=$nLinked, ya-existian=$nDup, email-no-coincide=$nSkipEmail, numero-sin-pedido=$nNoOrder");
