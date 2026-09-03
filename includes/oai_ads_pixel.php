<?php
/**
 * oai_ads_pixel.php — OpenAI Ads Measurement Pixel para francobordo.com (2026-09-01)
 *
 * Doc oficial: https://developers.openai.com/ads/measurement-pixel
 * Pixel ID: MWixSgLG4dzfc1QVcU4gj3 (fuente: paquete Francobordo-OpenAI-Ads-Pixel/config.json)
 *
 * Se registra en el evento del theme `front_office_footer_after_scripts` (el mismo bus
 * que usa el addon google-tags para gtag; se ejecuta en theme/web/scripts/scripts_footer.php)
 * y devuelve, para el footer de CADA pagina:
 *   1. El stub oaiq + oaiq("consent",false) inicial + init  (RGPD-safe: no mide sin CMP).
 *   2. El pegamento con el CMP (addon-cookie-advise-blocker): consentimiento = categoria
 *      Marketing aceptada (GOOGLETAG_COOKIE_CATEGORY_MARKETING, como hace el propio
 *      addon google-tags para Google Ads). Se re-evalua en cada cambio via
 *      cookieAdviseBlockerPopup.subscribeEventAccept — ese callback dispara tambien al
 *      rechazar o guardar ajustes, asi que cubre la REVOCACION.
 *   3. Los eventos de la pagina:
 *        contents_viewed  -> product_info.php
 *        items_added      -> delta del carrito vs snapshot en sesion (server-confirmed:
 *                            solo lo que el carrito YA contiene; cubre add_product,
 *                            buy_now, oct8ne y repetir pedido)
 *        checkout_started -> checkout_shipping.php / checkout_one_page (1 vez por carrito)
 *        order_created    -> checkout_success.php (1 vez por pedido; event_id order_<id>)
 *
 * BLINDADO: todo en try/catch — un fallo aqui NUNCA rompe la tienda ni el checkout.
 * Sin PII: solo ids/nombres de producto, cantidades e importes en centimos EUR.
 */

if (!defined('FB_OAI_PIXEL_ID')) define('FB_OAI_PIXEL_ID', 'MWixSgLG4dzfc1QVcU4gj3');

if (!function_exists('fb_oai_pixel_footer')) {

    function fb_oai_cents($eur) { return (int) round(((float) $eur) * 100); }

    function fb_oai_json($data) {
        $s = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_PARTIAL_OUTPUT_ON_ERROR);
        return ($s === false) ? 'null' : $s;
    }

    /** Precio unitario CON IVA de una linea del carrito (0.0 si no calculable). */
    function fb_oai_unit_price($p) {
        try {
            $price = isset($p['final_price']) ? (float) $p['final_price'] : 0.0;
            if ($price <= 0) return 0.0;
            if (function_exists('tep_add_tax') && function_exists('tep_get_tax_rate') && isset($p['tax_class_id'])) {
                return (float) tep_add_tax($price, tep_get_tax_rate($p['tax_class_id']));
            }
            return $price;
        } catch (\Throwable $e) { return 0.0; }
    }

    /** Lineas actuales del carrito como mapa uprid => {pid,name,qty,unit}. */
    function fb_oai_cart_lines() {
        global $cart;
        $out = array();
        if (!is_object($cart) || !method_exists($cart, 'get_products')) return $out;
        foreach ((array) $cart->get_products() as $p) {
            if (!isset($p['id'])) continue;
            $uprid = (string) $p['id'];
            $out[$uprid] = array(
                'pid'  => function_exists('tep_get_prid') ? (string) (int) tep_get_prid($uprid) : preg_replace('/[^0-9].*$/', '', $uprid),
                'name' => isset($p['name']) ? (string) $p['name'] : '',
                'qty'  => isset($p['quantity']) ? max(1, (int) $p['quantity']) : 1,
                'unit' => fb_oai_unit_price($p),
            );
        }
        return $out;
    }

    /**
     * items_added por delta del carrito vs snapshot de sesion. Devuelve payload o null.
     * Actualiza SIEMPRE el snapshot. Si cambia el cliente (merge de login) resincroniza
     * sin emitir, para no atribuir el carrito recuperado como una adicion.
     */
    function fb_oai_cart_delta() {
        $lines    = fb_oai_cart_lines();
        $snap     = (isset($_SESSION['oai_cart_snap']) && is_array($_SESSION['oai_cart_snap'])) ? $_SESSION['oai_cart_snap'] : null;
        $cust     = isset($_SESSION['customer_id']) ? (int) $_SESSION['customer_id'] : 0;
        $snapCust = isset($_SESSION['oai_cart_cust']) ? (int) $_SESSION['oai_cart_cust'] : -1;

        $newSnap = array();
        foreach ($lines as $uprid => $l) $newSnap[$uprid] = $l['qty'];
        $_SESSION['oai_cart_snap'] = $newSnap;
        $_SESSION['oai_cart_cust'] = $cust;

        // primer render de la sesion, o cambio de cliente: solo resincronizar
        if ($snap === null || $snapCust !== $cust) return null;

        $contents = array();
        $amount   = 0.0;
        foreach ($lines as $uprid => $l) {
            $prev  = isset($snap[$uprid]) ? (int) $snap[$uprid] : 0;
            $delta = $l['qty'] - $prev;
            if ($delta <= 0) continue;
            $c = array('id' => $l['pid'], 'name' => $l['name'], 'content_type' => 'product', 'quantity' => $delta);
            if ($l['unit'] > 0) { $c['amount'] = fb_oai_cents($l['unit']); $c['currency'] = 'EUR'; }
            $contents[] = $c;
            $amount += $l['unit'] * $delta;
        }
        if (empty($contents)) return null;
        return array('type' => 'contents', 'amount' => fb_oai_cents($amount), 'currency' => 'EUR', 'contents' => $contents);
    }

    /** contents_viewed en la ficha de producto. */
    function fb_oai_product_view() {
        if (empty($_GET['products_id']) || !function_exists('tep_db_query')) return null;
        $pid = function_exists('tep_get_prid') ? (int) tep_get_prid((string) $_GET['products_id']) : (int) $_GET['products_id'];
        if ($pid <= 0) return null;
        global $languages_id;
        $lang = isset($languages_id) ? (int) $languages_id : 3;
        $tp  = defined('TABLE_PRODUCTS') ? TABLE_PRODUCTS : 'products';
        $tpd = defined('TABLE_PRODUCTS_DESCRIPTION') ? TABLE_PRODUCTS_DESCRIPTION : 'products_description';
        $q = tep_db_query("select pd.products_name from " . $tp . " p join " . $tpd . " pd on pd.products_id = p.products_id and pd.language_id = '" . (int) $lang . "' where p.products_id = '" . (int) $pid . "' and p.products_status = 1 limit 1");
        if (!$q) return null;
        $r = tep_db_fetch_array($q);
        if (!$r || $r['products_name'] == '') return null;
        return array('type' => 'contents', 'contents' => array(array('id' => (string) $pid, 'name' => (string) $r['products_name'], 'content_type' => 'product')));
    }

    /** checkout_started, 1 vez por composicion de carrito (firma en sesion). */
    function fb_oai_checkout_started() {
        $lines = fb_oai_cart_lines();
        if (empty($lines)) return null;
        $contents = array();
        $amount   = 0.0;
        foreach ($lines as $l) {
            $contents[] = array('id' => $l['pid'], 'name' => $l['name'], 'content_type' => 'product', 'quantity' => $l['qty']);
            $amount += $l['unit'] * $l['qty'];
        }
        $payload = array('type' => 'contents', 'amount' => fb_oai_cents($amount), 'currency' => 'EUR', 'contents' => $contents);
        $sig = md5(fb_oai_json($payload));
        if (isset($_SESSION['oai_ck_sig']) && $_SESSION['oai_ck_sig'] === $sig) return null;   // re-render/recarga: no repetir
        $_SESSION['oai_ck_sig'] = $sig;
        return $payload;
    }

    /** order_created en checkout_success (mismo criterio que la propia pagina: ultimo pedido del cliente). */
    function fb_oai_order_created(&$eventId) {
        if (!function_exists('tep_db_query')) return null;
        global $customer_id;
        $cid = isset($customer_id) ? (int) $customer_id : (isset($_SESSION['customer_id']) ? (int) $_SESSION['customer_id'] : 0);
        if ($cid <= 0) return null;
        $to  = defined('TABLE_ORDERS') ? TABLE_ORDERS : 'orders';
        $tt  = defined('TABLE_ORDERS_TOTAL') ? TABLE_ORDERS_TOTAL : 'orders_total';
        $tp  = defined('TABLE_ORDERS_PRODUCTS') ? TABLE_ORDERS_PRODUCTS : 'orders_products';
        $q = tep_db_query("select orders_id from " . $to . " where customers_id = '" . (int) $cid . "' order by date_purchased desc limit 1");
        if (!$q) return null;
        $r = tep_db_fetch_array($q);
        if (!$r) return null;
        $oid = (int) $r['orders_id'];
        if ($oid <= 0) return null;
        if (!isset($_SESSION['oai_ord_done']) || !is_array($_SESSION['oai_ord_done'])) $_SESSION['oai_ord_done'] = array();
        if (isset($_SESSION['oai_ord_done'][$oid])) return null;      // recarga de la confirmacion: no duplicar
        $amount = 0;
        $q = tep_db_query("select value from " . $tt . " where orders_id = '" . $oid . "' and class = 'ot_total' limit 1");
        if ($q && ($r = tep_db_fetch_array($q))) $amount = fb_oai_cents($r['value']);
        $contents = array();
        $q = tep_db_query("select products_id, products_name, products_quantity from " . $tp . " where orders_id = '" . $oid . "'");
        while ($q && ($r = tep_db_fetch_array($q))) {
            $contents[] = array('id' => (string) (int) $r['products_id'], 'name' => (string) $r['products_name'], 'content_type' => 'product', 'quantity' => max(1, (int) $r['products_quantity']));
        }
        if ($amount <= 0 && empty($contents)) return null;
        $_SESSION['oai_ord_done'][$oid] = 1;
        $eventId = 'order_' . $oid;
        return array('type' => 'contents', 'amount' => $amount, 'currency' => 'EUR', 'contents' => $contents);
    }

    /** Bloque completo del footer: stub + consent + init + pegamento CMP + eventos de la pagina. */
    function fb_oai_pixel_footer() {
        if (PHP_SAPI === 'cli') return '';
        // paridad con el addon google-tags: no medir a PageSpeed
        if (isset($_SERVER['HTTP_USER_AGENT']) && stripos($_SERVER['HTTP_USER_AGENT'], 'Speed Insights') !== false) return '';

        $sf = isset($_SERVER['SCRIPT_FILENAME']) ? (string) $_SERVER['SCRIPT_FILENAME'] : '';
        $script = basename($sf);
        if (strpos($sf, 'checkout_one_page') !== false) $script = 'checkout.php';   // mismo criterio que google-tags

        $calls = array();

        $delta = fb_oai_cart_delta();
        if ($delta) $calls[] = 'FrancobordoOpenAIAdsGate.measure("items_added",' . fb_oai_json($delta) . ');';

        switch ($script) {
            case 'product_info.php':
                if ($p = fb_oai_product_view()) $calls[] = 'FrancobordoOpenAIAdsGate.measure("contents_viewed",' . fb_oai_json($p) . ');';
                break;
            case 'checkout_shipping.php':
            case 'checkout.php':
                if ($p = fb_oai_checkout_started()) $calls[] = 'FrancobordoOpenAIAdsGate.measure("checkout_started",' . fb_oai_json($p) . ');';
                break;
            case 'checkout_success.php':
                $eid = '';
                if ($p = fb_oai_order_created($eid)) $calls[] = 'FrancobordoOpenAIAdsGate.measure("order_created",' . fb_oai_json($p) . ',' . fb_oai_json(array('event_id' => $eid)) . ');';
                break;
        }

        $cat = defined('GOOGLETAG_COOKIE_CATEGORY_MARKETING') ? (string) (int) GOOGLETAG_COOKIE_CATEGORY_MARKETING : '3';
        $cmpActive = defined('ADDON_COOKIE_ADVISE_BLOCKER_ACTIVE') && ADDON_COOKIE_ADVISE_BLOCKER_ACTIVE == 1;

        $js  = "\n<!-- OpenAI Ads Measurement Pixel (anadido 2026-09-01) -->\n<script>\n";
        $js .= '(function(w,d,s,u){if(w.oaiq)return;var q=function(){q.q.push(arguments);};q.q=[];w.oaiq=q;var j=d.createElement(s);j.async=true;j.src=u;var f=d.getElementsByTagName(s)[0];f.parentNode.insertBefore(j,f);})(window,document,"script","https://bzrcdn.openai.com/sdk/oaiq.min.js");' . "\n";
        $js .= 'oaiq("consent",false);' . "\n";
        $js .= 'oaiq("init",{pixelId:"' . FB_OAI_PIXEL_ID . '",debug:false});' . "\n";
        // GATE de consentimiento (correccion 2026-09-01): el SDK DESCARTA los measure
        // recibidos con consent=false y NO los reproduce al concederse (literal en
        // oaiq.min.js: "event dropped because consent is not granted"). El gate retiene
        // los eventos en cola local hasta la PRIMERA decision del CMP: concedido ->
        // consent(true) + vaciado; rechazado -> descarte local (nada sale del navegador).
        $js .= '(function(w){"use strict";var pend=[],res=false,ok=false;'
             . 'function flush(){if(!ok)return;while(pend.length>0){try{w.oaiq.apply(w,pend.shift());}catch(e){}}}'
             . 'w.FrancobordoOpenAIAdsGate={'
             . 'setConsent:function(g){try{if(typeof w.oaiq!=="function")return;res=true;ok=!!g;w.oaiq("consent",ok);if(ok){flush();}else{pend.length=0;}}catch(e){}},'
             . 'measure:function(n,d,o){try{if(typeof w.oaiq!=="function")return;var a=["measure",n,d];if(o!=null)a.push(o);'
             . 'if(ok){w.oaiq.apply(w,a);return;}if(!res&&pend.length<20){pend.push(a);}}catch(e){}}'
             . '};})(window);' . "\n";
        if ($cmpActive) {
            // Pegamento CMP: como GoogleTagsConsentModeListener pero para oaiq. subscribeEventAccept
            // se dispara en CUALQUIER guardado del banner (aceptar, rechazar, guardar ajustes).
            $js .= '(function(){var CAT="' . $cat . '";'
                 . 'function ap(){try{'
                 . 'if(document.cookie.indexOf("advise_blocker=")===-1)return;'   // sin decision del banner aun -> gate PENDIENTE (no confundir con rechazo)
                 . 'var c=window.cookieAdviseBlocker;if(!c||!Array.isArray(c.categories))return;'
                 . 'FrancobordoOpenAIAdsGate.setConsent(c.categories.some(function(x){return String(x)===CAT;}));}catch(e){}}'
                 . 'function rd(){return window.cookieAdviseBlockerPopup&&window.cookieAdviseBlocker;}'
                 . 'function arm(){ap();try{cookieAdviseBlockerPopup.subscribeEventAccept(ap);}catch(e){}}'
                 . 'if(rd()){arm();}else{var n=0,t=setInterval(function(){n++;'
                 . 'if(rd()){clearInterval(t);arm();}else if(n>120){clearInterval(t);}},500);}})();' . "\n";
        }
        foreach ($calls as $c) $js .= $c . "\n";
        $js .= "</script>\n";
        return $js;
    }
}

// Registro en el bus de eventos del theme (misma instancia util\event que usa scripts_footer.php).
try {
    if (class_exists('util\\event')) {
        \util\event::getInstance()->add('front_office_footer_after_scripts', function () {
            try { return fb_oai_pixel_footer(); } catch (\Throwable $e) { return ''; }
        });
    }
} catch (\Throwable $e) { /* nunca romper el bootstrap */ }
