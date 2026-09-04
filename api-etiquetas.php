<?php
/**
 * CSV de precios para las etiquetas electronicas (ESL Hanshow / Profimax).
 *
 * Lo consume la tarea "ESL Etiquetas Fetch" de PROFIMAX (192.168.1.52), que lo
 * deja en \\192.168.1.51\importFiles\etiquetas.csv para que lo recoja el
 * ImportAdapterService. Sustituye a la descarga manual desde
 * _admin/precios_etiquetas.php (que sigue funcionando igual).
 *
 * Uso:
 *   GET /api-etiquetas.php               -> catalogo completo
 *   GET /api-etiquetas.php?fabricante=12 -> solo ese fabricante
 * Auth:
 *   Authorization: Bearer <key>   (etiqueta PROFIMAX en /home/francobordo/.api-tokens)
 * Allowlist:
 *   Solo IPs en $ALLOWED_IPS (la oficina sale por 217.127.199.171)
 *
 * La generacion vive en includes/etiquetas_export.php, COMPARTIDA con el admin:
 * no duplicar la logica de precios aqui.
 */
declare(strict_types=1);

// 1) HTTPS only
$_isHttps = !empty($_SERVER['HTTPS']) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
if (!$_isHttps) {
    http_response_code(403);
    exit('https only');
}

// 2) IP allowlist
$ALLOWED_IPS = ['217.127.199.171']; // oficina (PROFIMAX .52 sale por aqui)
$_remoteIP = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($_remoteIP, $ALLOWED_IPS, true)) {
    http_response_code(403);
    exit('forbidden');
}

// 3) Bearer auth
require_once '/home/francobordo/api_auth.php';
$_tokens = fb_api_load_tokens('/home/francobordo/.api-tokens');
if (empty($_tokens)) {
    http_response_code(500);
    exit('config');
}
$_authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if (fb_api_authorize($_authHeader, $_tokens) === null) {
    http_response_code(401);
    exit('unauthorized');
}

// 4) Generacion
set_time_limit(0);
ini_set('memory_limit', '-1');

require '/home/francobordo/public_html/includes/configure.php';
require '/home/francobordo/public_html/includes/etiquetas_export.php';

$manufacturerId = isset($_GET['fabricante']) ? (int) $_GET['fabricante'] : 0;

try {
    $pdo = fb_etq_pdo();
} catch (Throwable $e) {
    http_response_code(500);
    exit('db');
}

// A fichero temporal primero: si algo revienta a medias, NO se sirve un CSV
// truncado (un CSV incompleto haria que Profimax marcase como retirados todos
// los productos que faltasen).
$tmp = tempnam(sys_get_temp_dir(), 'etq');
$fp = fopen($tmp, 'w');
if ($fp === false) {
    http_response_code(500);
    exit('tmp');
}

try {
    $total = fb_etq_generar($pdo, $manufacturerId, $fp);
} catch (Throwable $e) {
    fclose($fp);
    @unlink($tmp);
    http_response_code(500);
    exit('generation failed');
}
fclose($fp);

if ($total < 1) {
    @unlink($tmp);
    http_response_code(500);
    exit('empty');
}

header('Content-Type: text/csv; charset=ISO-8859-1');
header('Content-Disposition: attachment; filename="etiquetas.csv"');
header('Content-Length: ' . (string) filesize($tmp));
header('X-Etiquetas-Lineas: ' . (string) $total);
header('Cache-Control: no-store');
readfile($tmp);
@unlink($tmp);
