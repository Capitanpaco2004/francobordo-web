<?php
/**
 * Proxy del ChatBot Pedro (pruebas internas).
 *
 * Reenvia /chatbot/* al backend FastAPI del .112 via Tailscale.
 * Acceso restringido por IP (oficina). Solo superficie publica del widget:
 * el panel de operador y /api/operator/* NUNCA se exponen por aqui.
 */

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
set_time_limit(150);

const CHATBOT_UPSTREAM   = 'http://100.82.226.46:3002';
const CHATBOT_PUBLIC_URL = 'https://www.francobordo.com/chatbot';
const ALLOWED_IPS        = array('217.127.199.171');
const PROXY_LOG          = '/home/francobordo/logs/chatbot_proxy.log';

function chatbot_deny(int $code = 404): void
{
	http_response_code($code);
	header('Content-Type: text/plain; charset=utf-8');
	echo "Not Found\n";
	exit;
}

function chatbot_log(array $row): void
{
	$row['ts'] = date('c');
	@file_put_contents(PROXY_LOG, json_encode($row) . "\n", FILE_APPEND | LOCK_EX);
}

$remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remoteIp, ALLOWED_IPS, true)) {
	chatbot_deny(404);
}

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (strpos($requestPath, '/chatbot') === 0) {
	$requestPath = substr($requestPath, strlen('/chatbot'));
}
if ($requestPath === '' || $requestPath === false) {
	$requestPath = '/';
}
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Allowlist estricta de rutas publicas del widget.
$allowed = false;
if ($method === 'GET' && $requestPath === '/embed.js') {
	$allowed = true;
} elseif ($method === 'GET' && preg_match('#^/assets/[A-Za-z0-9._-]+$#', $requestPath)) {
	$allowed = true;
} elseif ($method === 'POST' && in_array($requestPath, array('/api/public/session', '/api/public/presence', '/api/public/chat'), true)) {
	$allowed = true;
} elseif ($method === 'GET' && preg_match('#^/api/public/conversations/[0-9a-fA-F-]{36}$#', $requestPath)) {
	$allowed = true;
}
if (!$allowed) {
	chatbot_log(array('ip' => $remoteIp, 'method' => $method, 'path' => $requestPath, 'status' => 'blocked'));
	chatbot_deny(404);
}

$query = $_SERVER['QUERY_STRING'] ?? '';
$upstreamUrl = CHATBOT_UPSTREAM . $requestPath . ($query !== '' ? '?' . $query : '');

$headers = array('X-Forwarded-For: ' . $remoteIp);
if (!empty($_SERVER['CONTENT_TYPE'])) {
	$headers[] = 'Content-Type: ' . $_SERVER['CONTENT_TYPE'];
}
if (!empty($_SERVER['HTTP_X_VISITOR_TOKEN'])) {
	$headers[] = 'X-Visitor-Token: ' . $_SERVER['HTTP_X_VISITOR_TOKEN'];
}

$started = microtime(true);
$ch = curl_init($upstreamUrl);
curl_setopt_array($ch, array(
	CURLOPT_CUSTOMREQUEST  => $method,
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_HEADER         => false,
	CURLOPT_HTTPHEADER     => $headers,
	CURLOPT_CONNECTTIMEOUT => 5,
	CURLOPT_TIMEOUT        => 120,
	CURLOPT_ENCODING       => '',
	CURLOPT_FOLLOWLOCATION => false,
));
if ($method === 'POST') {
	curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
}

$responseContentType = '';
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $headerLine) use (&$responseContentType) {
	if (stripos($headerLine, 'Content-Type:') === 0) {
		$responseContentType = trim(substr($headerLine, 13));
	}
	return strlen($headerLine);
});

$body = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$curlError = curl_error($ch);

if ($body === false || $status === 0) {
	chatbot_log(array('ip' => $remoteIp, 'method' => $method, 'path' => $requestPath, 'status' => 'upstream_down', 'error' => $curlError));
	http_response_code(502);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(array('detail' => 'chatbot_upstream_unavailable'));
	exit;
}

// El embed.js contiene URLs absolutas generadas por el backend: se reescriben al dominio publico.
if ($requestPath === '/embed.js' || stripos($responseContentType, 'javascript') !== false || stripos($responseContentType, 'text/css') !== false) {
	$body = str_replace(CHATBOT_UPSTREAM, CHATBOT_PUBLIC_URL, $body);
}

http_response_code($status);
header('Content-Type: ' . ($responseContentType !== '' ? $responseContentType : 'application/octet-stream'));
if (strpos($requestPath, '/assets/') === 0) {
	header('Cache-Control: public, max-age=86400');
} else {
	header('Cache-Control: no-store');
}
header('X-Robots-Tag: noindex, nofollow');
echo $body;

chatbot_log(array(
	'ip'     => $remoteIp,
	'method' => $method,
	'path'   => $requestPath,
	'status' => $status,
	'ms'     => (int) round((microtime(true) - $started) * 1000),
));
