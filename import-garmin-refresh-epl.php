<?php
/**
 * Refresco (regeneración) del priceList.csv de Garmin.
 *
 * Dispara la acción "#eplCSV" del portal de dealers (Solicitar lista de precios
 * electrónica CSV). Esa acción es ASÍNCRONA: Garmin regenera el CSV en segundo
 * plano y envía un email a la cuenta cuando está listo. El endpoint de descarga
 * de siempre (/drc/priceList/download/csv) sirve una copia CACHEADA que solo se
 * refresca tras disparar esto.
 *
 * Flujo: login SSO (mismo que import-garmin.php) -> GET del disparador -> se
 * comprueba que el 302 aterriza en support?eplSent=true (Garmin aceptó la
 * solicitud). NO descarga el CSV: de eso ya se encarga el cron de stock
 * import-garmin.php (22:00), que se baja el CSV ya fresco.
 *
 * SOLO CLI (no expuesto por web). Cron: 0 21 * * *  (regenera antes del 22:00).
 *
 * Endpoint descubierto 2026-07-08 (bundle dealer-support-pages):
 *   GET https://dealers.garmin.com/drc/dealer/priceList/csv
 *       ?selectedDealerId=<id>&redirectUrl=<support>
 *   -> 302  Location: .../es-ES/partner-portal/support?eplSent=true  (cuerpo vacío)
 */

if (PHP_SAPI !== 'cli') { header('HTTP/1.1 403 Forbidden'); exit("CLI only\n"); }

error_reporting(E_ALL);
ini_set('display_errors', 1);

const GARMIN_DEALER_ID   = '18723608';
const GARMIN_USER        = 'f.rodriguez@francobordo.com';
const GARMIN_PASS        = 'Garmin0908';
const GARMIN_SSO_LOGIN   = 'https://sso.garmin.com/sso/signin?service=https%3A%2F%2Fdealers.garmin.com%2Fdrc%2F&source=https%3A%2F%2Fdealers.garmin.com%2Fdrc%2F';
const GARMIN_COOKIE_FILE = '/home/francobordo/public_html/import/Garmin/refresh-cookies.txt';
const GARMIN_LOG_FILE    = '/home/francobordo/public_html/import/Garmin/refresh-epl.log';

// Disparador de regeneración (equivale a pulsar #eplCSV en el portal de soporte).
const GARMIN_EPL_TRIGGER = 'https://dealers.garmin.com/drc/dealer/priceList/csv?selectedDealerId=' . GARMIN_DEALER_ID
                         . '&redirectUrl=https%3A%2F%2Fdealers.garmin.com%2Fes-ES%2Fpartner-portal%2Fsupport%3FeplSent%3Dtrue';

function logMsg($msg) {
	$line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
	echo $line;
	@file_put_contents(GARMIN_LOG_FILE, $line, FILE_APPEND);
}

function garminCurl($url, $opts = []) {
	$ch = curl_init($url);
	$base = [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_COOKIEJAR      => GARMIN_COOKIE_FILE,
		CURLOPT_COOKIEFILE     => GARMIN_COOKIE_FILE,
		CURLOPT_TIMEOUT        => 60,
		CURLOPT_CONNECTTIMEOUT => 15,
		CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_POSTREDIR      => 7,
	];
	// array_replace (NO el operador +): así $opts sobrescribe claves de $base
	// como CURLOPT_FOLLOWLOCATION (el operador + conserva la clave de $base).
	curl_setopt_array($ch, array_replace($base, $opts));
	$body  = curl_exec($ch);
	$code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$eff   = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
	$redir = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
	$err   = curl_error($ch);
	return [$body, $code, $eff, $err, $redir];
}

function garminLoginAndTrigger() {
	@unlink(GARMIN_COOKIE_FILE);

	logMsg('SSO 1/3: GET signin (CSRF)...');
	[$html, $code, , $err] = garminCurl(GARMIN_SSO_LOGIN);
	if ($code !== 200 || !$html) { logMsg("ERROR signin: code=$code err=$err"); return false; }
	if (!preg_match('/name="_csrf"\s+value="([^"]+)"/', $html, $m)) { logMsg('ERROR: no _csrf en login form'); return false; }
	$csrf = $m[1];

	logMsg('SSO 2/3: POST credenciales...');
	[$resp, $code, , $err] = garminCurl(GARMIN_SSO_LOGIN, [
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => http_build_query([
			'username' => GARMIN_USER,
			'password' => GARMIN_PASS,
			'embed'    => 'false',
			'_csrf'    => $csrf,
		]),
		CURLOPT_HTTPHEADER => ['Origin: https://sso.garmin.com', 'Referer: ' . GARMIN_SSO_LOGIN],
	]);
	if ($code !== 200 || !$resp || stripos($resp, '<title>Success</title>') === false) {
		logMsg("ERROR login: code=$code (sin <title>Success</title>) - posible 429/bloqueo"); return false;
	}
	if (!preg_match('/response_url\s*=\s*"([^"]+)"/', $resp, $tm)) { logMsg('ERROR: no response_url en Success'); return false; }
	$ticketUrl = stripslashes($tm[1]);

	logMsg('SSO 3/3: consumiendo ticket...');
	garminCurl($ticketUrl);
	$cookieOk = file_exists(GARMIN_COOKIE_FILE) && strpos(file_get_contents(GARMIN_COOKIE_FILE), 'DRC_JWT') !== false;
	if (!$cookieOk) { logMsg('ERROR: ticket no devolvio DRC_JWT'); return false; }

	logMsg('Disparando regeneracion EPL (#eplCSV)...');
	// No seguimos el redirect: solo nos importa que el 302 apunte a eplSent=true
	// (= Garmin aceptó la solicitud de regeneración).
	[, $code, , $err, $redir] = garminCurl(GARMIN_EPL_TRIGGER, [CURLOPT_FOLLOWLOCATION => false]);
	if ($code !== 302 || strpos((string)$redir, 'eplSent=true') === false) {
		logMsg("ERROR trigger: code=$code redir=$redir err=$err (esperaba 302 -> eplSent=true)");
		return false;
	}
	logMsg("OK: solicitud EPL enviada (302 -> $redir). Garmin regenerara el CSV y avisara por email.");
	return true;
}

$t0 = microtime(true);
logMsg('=== Refresco EPL Garmin ===');
$ok = garminLoginAndTrigger();
@unlink(GARMIN_COOKIE_FILE); // no dejar sesion persistida en disco
logMsg(($ok ? 'OK' : 'FALLO') . ' - ' . round(microtime(true) - $t0, 2) . ' s');
exit($ok ? 0 : 1);
