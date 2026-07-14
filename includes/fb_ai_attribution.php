<?php
/**
 * fb_ai_attribution.php  (2026-07-13)
 * Atribucion de origen IA (ChatGPT y cia) SIN depender de Google.
 *
 *  - fb_ai_capture()        se llama desde includes/application_top.php en cada pagina:
 *                           guarda en la sesion el origen IA (utm_source o referrer). First-touch.
 *  - fb_ai_current_source() lo lee.
 *  - fb_ai_record_order()   se llama desde checkout_process.php al crear el pedido:
 *                           persiste el origen en la tabla orders_ai_source.
 *
 * DISENO BLINDADO: absolutamente nada aqui puede romper la tienda ni el checkout
 * (todo va en try/catch y con conexion propia aislada de osCommerce).
 */
if (!function_exists('fb_ai_capture')) {

    /** Detecta el origen IA (utm_source o referrer) y lo fija en la sesion. No sobrescribe (first-touch). */
    function fb_ai_capture() {
        try {
            if (session_status() !== PHP_SESSION_ACTIVE) return;
            if (!empty($_SESSION['fb_ai_src'])) return;                 // ya capturado en esta sesion
            $src = '';
            // 1) utm_source explicito (lo que ChatGPT&cia ponen en el enlace). Guarda de tipo: solo string.
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
            if ($src !== '') {
                $_SESSION['fb_ai_src']    = $src;
                $_SESSION['fb_ai_src_ts'] = time();
            }
        } catch (\Throwable $e) { /* jamas interrumpir la carga de pagina */ }
    }

    /** Origen IA capturado en la sesion actual, o '' si ninguno. */
    function fb_ai_current_source() {
        return (isset($_SESSION['fb_ai_src']) && is_string($_SESSION['fb_ai_src'])) ? $_SESSION['fb_ai_src'] : '';
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
