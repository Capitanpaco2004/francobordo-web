<?php
/**
 * /api/track-click.php
 *
 * Recibe pings de click del widget de búsqueda (vía navigator.sendBeacon).
 * Es fire-and-forget: el cliente NO espera respuesta significativa.
 * Escribe una línea por click a un log diario. El cron nocturno los importa
 * a `click_events` y de ahí calcula `product_popularity` para LTR.
 *
 * Body esperado (JSON):
 *   { "q": "chaleco", "pid": "p4970-a35659", "position": 3 }
 */
declare(strict_types=1);

// CORS para que sendBeacon desde www.francobordo.com no se queje
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (preg_match('#^https://(www\.)?francobordo\.com$#', $origin)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); exit; }

// Liberar al cliente inmediatamente (sendBeacon no espera respuesta de todas formas)
http_response_code(204);
if (function_exists('fastcgi_finish_request')) { @fastcgi_finish_request(); }

try {
    $raw = file_get_contents('php://input', false, null, 0, 4096);   // cap 4KB
    if (!$raw) return;
    $j = json_decode($raw, true);
    if (!is_array($j)) return;

    $q   = trim((string)($j['q'] ?? ''));
    $pid = trim((string)($j['pid'] ?? ''));
    $pos = (int)($j['position'] ?? 0);
    if ($q === '' || $pid === '') return;
    if (mb_strlen($q) > 255) $q = mb_substr($q, 0, 255);

    // Normalizar pid: aceptamos "p1048" o "p4970-a35659"; extraemos el pid del padre
    if (preg_match('/^p?(\d+)(?:-a\d+)?$/', $pid, $m)) {
        $pid_num = (int)$m[1];
    } else {
        return;
    }

    // q_norm: igual normalización que search-proxy
    $qn = mb_strtolower($q, 'UTF-8');
    $qn = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $qn);
    $qn = preg_replace('/\s+/', ' ', trim($qn));

    $logDir = '/home/francobordo/_search/logs';
    @mkdir($logDir, 0755, true);
    $logFile = $logDir . '/click_events_' . date('Y-m-d') . '.log';
    $line = sprintf("%s\t%d\t%d\t%s\t%s\n",
        date('c'), $pid_num, $pos, $qn, $q);
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
} catch (Throwable $e) {
    // silencioso
}
