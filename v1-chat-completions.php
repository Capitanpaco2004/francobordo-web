<?php
/**
 * Proxy OpenAI-compatible para que el bot de Oct8ne (u otros consumidores)
 * llame a nuestro RAG en LAN. Recibe POST /v1/chat/completions y reenvia el
 * payload tal cual al RAG via Tailscale (http://100.82.226.46:8002), devolviendo
 * la respuesta del modelo en formato OpenAI.
 *
 * Auth:
 *   Authorization: Bearer <token>  (consumidores en /home/francobordo/.api-tokens)
 * Allowlist:
 *   Solo IPs en $ALLOWED_IPS (Oct8ne sale por 40.115.11.160)
 * Log:
 *   Cada peticion (request + response + timing + consumidor) se anota en
 *   /home/francobordo/logs/oct8ne_chat.log para inspeccionar el contrato
 *   real que envia el conector durante el bring-up.
 */
declare(strict_types=1);

// El warning de PHP corrompe la respuesta JSON; silenciamos display_errors.
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

ini_set('serialize_precision', '14');

// 1) HTTPS only
$_isHttps = !empty($_SERVER['HTTPS']) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
if (!$_isHttps) {
    http_response_code(403);
    header('Content-Type: application/json');
    exit('{"error":{"message":"https only","type":"transport_error"}}');
}

// 2) Solo POST (OpenAI estandar)
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: application/json');
    exit('{"error":{"message":"method not allowed","type":"transport_error"}}');
}

// 3) IP allowlist (Oct8ne 40.115.11.160 + IP del LAN/RAG por si llega via RAG)
$ALLOWED_IPS = [
    // Oct8ne - lista oficial de IPs de egress (facilitada 2026-05-22)
    '13.69.68.1', '20.229.254.234', '20.229.255.27', '20.8.248.227', '20.8.249.201',
    '20.8.250.250', '20.8.251.168', '104.47.161.32', '104.47.142.248', '40.115.59.104',
    '104.47.144.212', '52.233.203.135', '104.47.161.137', '104.47.143.150', '104.47.161.68',
    '20.160.240.24', '20.160.241.96', '20.160.242.123', '20.160.242.214', '20.160.242.218',
    '20.160.242.244',
    // LAN/RAG egress para pruebas internas
    '217.127.199.171',
];
$_remoteIP = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($_remoteIP, $ALLOWED_IPS, true)) {
    http_response_code(403);
    header('Content-Type: application/json');
    exit('{"error":{"message":"forbidden","type":"transport_error"}}');
}

// 4) Bearer auth (multi-consumidor)
require_once '/home/francobordo/api_auth.php';
$_tokens = fb_api_load_tokens('/home/francobordo/.api-tokens');
if (empty($_tokens)) {
    http_response_code(500);
    header('Content-Type: application/json');
    exit('{"error":{"message":"config","type":"server_error"}}');
}
$_authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
$_apiConsumer = fb_api_authorize($_authHeader, $_tokens);
if ($_apiConsumer === null) {
    http_response_code(401);
    header('Content-Type: application/json');
    exit('{"error":{"message":"unauthorized","type":"transport_error"}}');
}

// 5) Leer body crudo
$body = file_get_contents('php://input');
if ($body === false || $body === '') {
    http_response_code(400);
    header('Content-Type: application/json');
    exit('{"error":{"message":"empty body","type":"invalid_request_error"}}');
}

// 6) Proxy al RAG por Tailscale
$rag_url = 'http://100.82.226.46:8002/v1/chat/completions';
$t0 = microtime(true);
$ch = curl_init($rag_url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: ' . $_authHeader,
    ],
    CURLOPT_TIMEOUT        => 120,
    CURLOPT_CONNECTTIMEOUT => 10,
]);
$resp = curl_exec($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
// sin curl_close(): deprecado en PHP 8.5 (no-op desde 8.0)
$elapsedMs = (int)round((microtime(true) - $t0) * 1000);

// 7) Log (request + response truncados a 8 KB)
$logDir = '/home/francobordo/logs';
if (!is_dir($logDir)) { @mkdir($logDir, 0750, true); }
$logEntry = [
    'ts'       => date('c'),
    'consumer' => $_apiConsumer,
    'ip'       => $_remoteIP,
    'status'   => (int)$httpStatus,
    'ms'       => $elapsedMs,
    'req'      => mb_substr($body, 0, 8192),
    'resp'     => $resp === false ? null : mb_substr((string)$resp, 0, 8192),
    'curl_err' => $curlErr ?: null,
];
@file_put_contents(
    $logDir . '/oct8ne_chat.log',
    json_encode($logEntry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
    FILE_APPEND | LOCK_EX
);

// 8) Devolver al cliente
header('Content-Type: application/json; charset=utf-8');
if ($resp === false) {
    http_response_code(502);
    echo json_encode([
        'error' => [
            'message' => 'bad gateway: ' . $curlErr,
            'type'    => 'upstream_error',
        ],
    ]);
    exit;
}

http_response_code($httpStatus ?: 502);
echo $resp;
