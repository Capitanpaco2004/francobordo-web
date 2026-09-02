<?php
/**
 * fb_ai_attribution.php  (v2 2026-09-01; v1 2026-07-13)
 * Atribucion de origen IA (ChatGPT y cia) SIN depender de Google.
 *
 *  - fb_ai_capture()        se llama desde includes/application_top.php en cada pagina:
 *                           detecta el origen IA (utm_source o referrer) y lo persiste.
 *  - fb_ai_current_source() lo lee (cookie 30d -> fallback sesion).
 *  - fb_ai_record_order()   se llama desde checkout_process.php al crear el pedido:
 *                           persiste el origen en la tabla orders_ai_source.
 *
 * v2: ademas de la sesion, el origen se guarda en una COOKIE FIRST-PARTY de 30 dias
 * (`fb_ai_src` = "fuente|timestamp", p.ej. "chatgpt|1756740000"; sin ningun dato
 * personal). Asi el que descubre la tienda en ChatGPT y compra DIAS despues en otra
 * sesion tambien queda atribuido (la v1, solo-sesion, infracontaba: 1 pedido en 50
 * dias vs ~25/mes que veia GA4). Politica first-touch dentro de la ventana: una
 * fuente ya guardada no se pisa por otra distinta; re-tocar la MISMA fuente
 * refresca la ventana de 30 dias. La cookie no se borra al comprar (los pedidos
 * repetidos dentro de la ventana siguen siendo influencia del canal).
 *
 * DISENO BLINDADO: absolutamente nada aqui puede romper la tienda ni el checkout
 * (todo va en try/catch y con conexion propia aislada de osCommerce).
 */

if (!defined('FB_AI_COOKIE'))      define('FB_AI_COOKIE', 'fb_ai_src');
if (!defined('FB_AI_COOKIE_DAYS')) define('FB_AI_COOKIE_DAYS', 30);

if (!function_exists('fb_ai_capture')) {

    /** Fuentes validas (allowlist: la cookie es entrada del cliente y se valida SIEMPRE). */
    function fb_ai_sources() {
        return array('chatgpt', 'perplexity', 'gemini', 'copilot', 'claude');
    }

    /** Lee y valida la cookie. Devuelve array(src, ts) o null. */
    function fb_ai_cookie_read() {
        try {
            if (empty($_COOKIE[FB_AI_COOKIE]) || !is_string($_COOKIE[FB_AI_COOKIE])) return null;
            if (!preg_match('/^([a-z]+)\|([0-9]{9,12})$/', $_COOKIE[FB_AI_COOKIE], $m)) return null;
            if (!in_array($m[1], fb_ai_sources(), true)) return null;
            $ts = (int) $m[2];
            if ($ts <= 0 || $ts > time() + 86400 || time() - $ts > FB_AI_COOKIE_DAYS * 86400) return null;
            return array('src' => $m[1], 'ts' => $ts);
        } catch (\Throwable $e) { return null; }
    }

    /** Escribe/refresca la cookie de atribucion (30 dias, first-party, sin PII). */
    function fb_ai_cookie_write($src) {
        try {
            if (headers_sent()) return;
            $host = isset($_SERVER['HTTP_HOST']) ? strtolower((string) $_SERVER['HTTP_HOST']) : '';
            $host = preg_replace('/:[0-9]+$/', '', $host);          // sin puerto
            $host = preg_replace('/^www\./', '', $host);
            $domain = (strpos($host, '.') !== false && !filter_var($host, FILTER_VALIDATE_IP)) ? '.' . $host : '';
            $val = $src . '|' . time();
            setcookie(FB_AI_COOKIE, $val, array(
                'expires'  => time() + FB_AI_COOKIE_DAYS * 86400,
                'path'     => '/',
                'domain'   => $domain,
                'secure'   => !empty($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ));
            $_COOKIE[FB_AI_COOKIE] = $val;   // visible ya en esta misma peticion
        } catch (\Throwable $e) { /* nunca interrumpir */ }
    }

    /** Detecta el origen IA (utm_source o referrer por HOST) y lo persiste. First-touch 30d. */
    function fb_ai_capture() {
        try {
            $src = '';
            // 1) utm_source explicito (lo que ChatGPT&cia ponen en el enlace). Solo string.
            if (!empty($_GET['utm_source']) && is_string($_GET['utm_source'])) {
                $u = strtolower($_GET['utm_source']);
                if (strpos($u, 'chatgpt') !== false || strpos($u, 'openai') !== false)      $src = 'chatgpt';
                elseif (strpos($u, 'perplexity') !== false)                                  $src = 'perplexity';
                elseif (strpos($u, 'gemini') !== false || strpos($u, 'bard') !== false)      $src = 'gemini';
                elseif (strpos($u, 'copilot') !== false)                                     $src = 'copilot';
                elseif (strpos($u, 'claude') !== false || strpos($u, 'anthropic') !== false) $src = 'claude';
            }
            // 2) referrer: comparar por HOST (no substring) para no marcar falsos positivos
            //    (p.ej. https://foro.com/algo-de-chatgpt.com no debe contar como ChatGPT).
            if ($src === '' && !empty($_SERVER['HTTP_REFERER'])) {
                $h = strtolower((string) parse_url((string) $_SERVER['HTTP_REFERER'], PHP_URL_HOST));
                $hostIs = function ($host, $dom) {
                    return $host === $dom || substr($host, -(strlen($dom) + 1)) === '.' . $dom;
                };
                if ($h !== '') {
                    if ($hostIs($h, 'chatgpt.com') || $hostIs($h, 'chat.openai.com')) $src = 'chatgpt';
                    elseif ($hostIs($h, 'perplexity.ai'))          $src = 'perplexity';
                    elseif ($hostIs($h, 'gemini.google.com'))      $src = 'gemini';
                    elseif ($hostIs($h, 'copilot.microsoft.com'))  $src = 'copilot';
                    elseif ($hostIs($h, 'claude.ai'))              $src = 'claude';
                }
            }
            if ($src === '') return;

            // Persistencia first-touch: cookie ausente -> escribir; misma fuente -> refrescar
            // ventana; fuente DISTINTA ya guardada -> respetar la primera (no pisar).
            $existing = fb_ai_cookie_read();
            if ($existing === null || $existing['src'] === $src) {
                fb_ai_cookie_write($src);
            }

            // Espejo en sesion (fallback si el navegador rechaza cookies persistentes)
            if (session_status() === PHP_SESSION_ACTIVE && empty($_SESSION['fb_ai_src'])) {
                $_SESSION['fb_ai_src']    = ($existing !== null) ? $existing['src'] : $src;
                $_SESSION['fb_ai_src_ts'] = time();
            }
        } catch (\Throwable $e) { /* jamas interrumpir la carga de pagina */ }
    }

    /** Origen IA atribuible ahora: cookie 30d (persistente) -> sesion (fallback) -> ''. */
    function fb_ai_current_source() {
        try {
            $c = fb_ai_cookie_read();
            if ($c !== null) return $c['src'];
            if (isset($_SESSION['fb_ai_src']) && is_string($_SESSION['fb_ai_src'])
                && in_array($_SESSION['fb_ai_src'], fb_ai_sources(), true)) {
                return $_SESSION['fb_ai_src'];
            }
        } catch (\Throwable $e) { }
        return '';
    }

    /** Persiste la atribucion del pedido en orders_ai_source. BLINDADO: nunca lanza ni rompe el checkout. */
    function fb_ai_record_order($orders_id, $source) {
        try {
            $orders_id = (int) $orders_id;
            $source    = (string) $source;
            if ($orders_id <= 0 || $source === '') return;
            if (!defined('DB_SERVER') || !defined('DB_DATABASE')) return;
            $db = @new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
            if (!$db || $db->connect_errno) return;
            $src = $db->real_escape_string(substr($source, 0, 32));
            @$db->query("insert ignore into orders_ai_source (orders_id, source, created) values ("
                . $orders_id . ", '" . $src . "', now())");
            if ($db->errno) @error_log('fb_ai_record_order errno ' . $db->errno . ': ' . $db->error);
            @$db->close();
        } catch (\Throwable $e) { /* la atribucion nunca puede tumbar un pedido */ }
    }
}
