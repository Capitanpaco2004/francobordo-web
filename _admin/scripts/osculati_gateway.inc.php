<?php
/**
 * Descarga de ficheros de Osculati vía la PASARELA HTTPS (puerto 443).
 *
 * El FTP crudo (ftp://fw.osculati.it/, puerto 21) está caído desde ~2026-06-25.
 * La pasarela https://fw.osculati.it/ftp/?u=..&p=..&path=.. sigue operativa
 * (es la que usa el cron de stock import-osculati.php). Requiere seguir
 * redirecciones (302→200) + cookie de sesión + user-agent de navegador.
 *
 * Compartido por import-osculati-altas.php y los scripts de _admin/scripts/.
 * Usa las constantes OSC_USER / OSC_PASS si están definidas; si no, valores por defecto.
 */
if (!function_exists('osculatiGw')) {
    function osculatiGw($path, $localPath, $minBytes = 1) {
        $u = defined('OSC_USER') ? OSC_USER : 'C54293';
        $p = defined('OSC_PASS') ? OSC_PASS : '0XxBkWSb';
        $url = 'https://fw.osculati.it/ftp/?u=' . $u . '&p=' . $p . '&path=' . $path;
        $ck = sys_get_temp_dir() . '/osc_gw_cookie.txt';
        $fp = fopen($localPath, 'wb');
        if (!$fp) return false;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6',
            CURLOPT_COOKIEJAR      => $ck,
            CURLOPT_COOKIEFILE     => $ck,
            CURLOPT_COOKIESESSION  => true,
            CURLOPT_TIMEOUT        => 600,
            CURLOPT_CONNECTTIMEOUT => 20,
        ]);
        $ok = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // sin curl_close(): deprecado en PHP 8.5 (no-op desde 8.0)
        fclose($fp);
        $ok = $ok && $code === 200 && filesize($localPath) >= $minBytes;
        if (!$ok) @unlink($localPath);
        return $ok;
    }
    /** Variante que devuelve el contenido (para los que usaban CURLOPT_RETURNTRANSFER). */
    function osculatiGwGet($path) {
        $tmp = tempnam(sys_get_temp_dir(), 'oscgw_');
        if (!osculatiGw($path, $tmp, 1)) { @unlink($tmp); return false; }
        $d = file_get_contents($tmp);
        @unlink($tmp);
        return $d;
    }
}
