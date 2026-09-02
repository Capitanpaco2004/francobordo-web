<?php
/**
 * Scraper guard:
 *   0) Allowlist por UA con token secreto (descargador de docs del RAG) -> return inmediato
 *   1) Allowlist hardcoded (LAN, self, casa, Redsys, Googlebot, Bingbot, Meta, Ahrefs, Petalbot)
 *   2) Allowlist DB (tabla scraper_allowlist) — añadible desde panel admin
 *   3) 403 inmediato si IP en scraper_blacklist (no expirada). Los bans con reason "scanner%"
 *      son AUTORITATIVOS: ganan a la exención por compra (is_customer), que es envenenable.
 *   3b) ANTI-SPOOF de bots verificables: si el UA declara un crawler que publica rangos de IP
 *      (OpenAI/Anthropic/Perplexity/Google/Bing) y la IP NO está en ellos, se anota en
 *      scraper_radar (kind spoofed_ai_bot) y —solo para las familias IA— pierde la exención
 *      de bot declarado. Fail-open si bot_ranges.inc.php falta o está rancio.
 *   4a) CONDUCTUAL (agnóstico al UA): rate-limit del firehose /products_new.php.
 *       >=15 hits / 10 min desde una IP -> auto-blacklist 24h (reason ratelimit_catalog).
 *       Es la defensa durable: NO depende del User-Agent, así que aguanta la rotación de UAs.
 *   4b) Heurística UA antiguo del pool residencial (SM-G900P, Pixel 2, iPhone 11, Nexus 5,
 *       + macOS 10_12/13/14, Win7) -> rate-limit 5 hits/60s -> blacklist 24h (ratelimit_oldua).
 *
 * Robustez:
 *   - Toda la capa BD va en try/catch -> fail-open real (mysqli_report STRICT lanza excepciones,
 *     el @ no basta). Si la BD falla, NO bloquea (preferimos scraping > tienda caída).
 *   - El rate-limit usa INSERT ... ON DUPLICATE KEY UPDATE atómico para evitar la race condition
 *     de requests concurrentes desde la misma IP.
 * Requiere DB_SERVER definido (configure.php).
 */

$_sg_ip = $_SERVER["REMOTE_ADDR"] ?? "";
if ($_sg_ip === "" || !defined("DB_SERVER")) return;

// (0) Allowlist por UA con token secreto: el descargador de PDFs tecnicos del RAG
//     nunca se bloquea, sea cual sea su IP (la IP de salida del RAG es dinamica).
//     El token va embebido en el User-Agent del pipeline ingest_product_docs.
$_sg_ua_token = "d81f85fd3b3d800275b8a91a5de48ae12e0f538202c1bc87";
if (strpos($_SERVER["HTTP_USER_AGENT"] ?? "", $_sg_ua_token) !== false) return;

$_sg_allow_exact = ["217.127.199.171", "20.71.1.14", "80.28.193.44", "127.0.0.1", "::1"];
$_sg_allow_cidr = [
    "195.76.9.0/24",     // Redsys TPV
    "66.249.64.0/19",    // Googlebot
    "66.249.80.0/20",    // Googlebot
    "216.239.32.0/19",   // Google
    "40.77.167.0/24",    // Bingbot
    "157.55.39.0/24",    // Bingbot
    "207.46.13.0/24",    // Bingbot
    "173.252.64.0/19",   // Meta
    "31.13.24.0/21",     // Meta
    "168.100.149.0/24",  // Ahrefs
    "176.31.139.0/24",   // Ahrefs
    "114.119.0.0/16",    // PetalBot/AspiegelBot Huawei
    // --- CDN/proxy que carecen de PTR pero llevan USUARIOS REALES (anadido 2026-06-25 tras FP) ---
    // Sin esto, la regla de catalogo baneaba clientes B2B/WARP (ej. Zscaler 136.226, WARP 104.28).
    "104.16.0.0/13",     // Cloudflare (cubre 104.16-104.23)
    "104.24.0.0/14",     // Cloudflare (104.24-104.27)
    "104.28.0.0/16",     // Cloudflare WARP / Apple Private Relay (FALTABA: 104.28 NO esta en 104.16/13)
    "172.64.0.0/13",     // Cloudflare
    "162.158.0.0/15",    // Cloudflare
    "141.101.64.0/18",   // Cloudflare
    "108.162.192.0/18",  // Cloudflare
    "173.245.48.0/20",   // Cloudflare
    "140.248.0.0/16",    // Apple Private Relay
    "172.224.0.0/12",    // Apple Private Relay
    "136.226.0.0/16",    // Zscaler ZIA (FP confirmado cliente B2B)
    "147.161.128.0/17",  // Zscaler ZIA
    "165.225.0.0/17",    // Zscaler ZIA
    "165.225.192.0/18",  // Zscaler ZIA
    "170.85.0.0/16",     // Zscaler ZIA
    "104.129.192.0/20",  // Zscaler ZIA
    "104.47.0.0/17",     // Microsoft O365/Exchange/SafeLinks
    "151.101.0.0/16",    // Fastly
    "146.75.0.0/16",     // Fastly (+ Apple PR partner)
    "199.232.0.0/16",    // Fastly
];

if (in_array($_sg_ip, $_sg_allow_exact, true)) return;

if (strpos($_sg_ip, ":") === false) {
    $_sg_ipl = ip2long($_sg_ip);
    if ($_sg_ipl !== false) {
        foreach ($_sg_allow_cidr as $_sg_c) {
            $_sg_p = strpos($_sg_c, "/");
            if ($_sg_p === false) continue;
            $_sg_sn = substr($_sg_c, 0, $_sg_p);
            $_sg_b = (int)substr($_sg_c, $_sg_p + 1);
            if (strpos($_sg_sn, ":") !== false) continue;
            $_sg_snl = ip2long($_sg_sn);
            if ($_sg_snl === false) continue;
            $_sg_mask = -1 << (32 - $_sg_b);
            if (($_sg_ipl & $_sg_mask) === ($_sg_snl & $_sg_mask)) return;
        }
    }
}

// --- Toda la capa BD en try/catch (fail-open real) ---
$_sg_link = null;
try {
    $_sg_link = @mysqli_connect(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
    if (!$_sg_link) return;
    $_sg_ipe = mysqli_real_escape_string($_sg_link, $_sg_ip);

    // (2)+(3): allowlist DB, blacklist y EXENCION POR COMPRA en una sola query.
    // is_customer = IP vista logueada o en el funnel de compra en los ultimos 30 dias
    // (tabla scraper_customer_ips, poblada desde whos_online por el cron rDNS). Anadido 2026-06-25
    // tras FP: clientes B2B/residenciales baneados por las reglas sincronas. Un comprador real
    // NUNCA debe ser bloqueado por el anti-scraper, aunque su sesion viva ya haya expirado.
    // is_hardblock = ban MANUAL/de escaneo (reason que empieza por "scanner"). Anadido 2026-08-29:
    // la exencion is_customer se puede ENVENENAR — un escaner que pide /login o /api/account entra
    // en whos_online, el cron lo copia a scraper_customer_ips y queda inmune 30 dias (caso real:
    // 136.117.214.122, escaner de credenciales, marcado source='funnel'). Los bans automaticos por
    // rate-limit SIGUEN cediendo ante is_customer (preserva el fix de FP del 2026-06-25), pero un
    // ban explicito de escaner es AUTORITATIVO y se comprueba antes que nada.
    $_sg_res = mysqli_query($_sg_link, "SELECT
        EXISTS(SELECT 1 FROM scraper_allowlist WHERE ip = \"$_sg_ipe\") AS is_allow,
        EXISTS(SELECT 1 FROM scraper_blacklist WHERE ip = \"$_sg_ipe\" AND expires_at > NOW()) AS is_block,
        EXISTS(SELECT 1 FROM scraper_blacklist WHERE ip = \"$_sg_ipe\" AND expires_at > NOW() AND reason LIKE \"scanner%\") AS is_hardblock,
        EXISTS(SELECT 1 FROM scraper_customer_ips WHERE ip = \"$_sg_ipe\" AND last_seen > NOW() - INTERVAL 30 DAY) AS is_customer");
    $_sg_row = $_sg_res ? mysqli_fetch_assoc($_sg_res) : null;
    if ($_sg_res) mysqli_free_result($_sg_res);

    // Ban de escaner: gana a is_customer (pero NO a la allowlist explicita, que sigue siendo la
    // valvula de escape manual desde el panel si algun dia hubiese un FP).
    if ($_sg_row && (int)$_sg_row["is_hardblock"] === 1 && (int)$_sg_row["is_allow"] !== 1) {
        mysqli_close($_sg_link);
        _sg_deny_403();
    }
    if ($_sg_row && ((int)$_sg_row["is_allow"] === 1 || (int)$_sg_row["is_customer"] === 1)) {
        mysqli_close($_sg_link);
        return; // allowlist DB o comprador reciente: nunca banear
    }
    if ($_sg_row && (int)$_sg_row["is_block"] === 1) {
        mysqli_close($_sg_link);
        _sg_deny_403();
    }

    // --- Señales para las heuristicas (UA + script destino) ---
    $_sg_ua = $_SERVER["HTTP_USER_AGENT"] ?? "";
    $_sg_uae = mysqli_real_escape_string($_sg_link, substr($_sg_ua, 0, 500));
    // Incluye crawlers IA DESEADOS (estrategia de visibilidad IA de francobordo): Anthropic/OpenAI/Perplexity.
    // + crawlers Google deseados (search/Shopping-Merchant/AdsBot/inspeccion) que NO contienen "Googlebot"
    //   y rastrean desde rangos fuera de la allowlist CIDR (74.125.x, 142.250.x) — 2026-06-15.
    $_sg_declared_bot_regex = "#(PetalBot|AspiegelBot|Googlebot|GoogleOther|Storebot-Google|AdsBot-Google|Google-InspectionTool|APIs-Google|FeedFetcher-Google|bingbot|BingPreview|adidxbot|msnbot|YandexBot|DuckDuckBot|FacebookExternalHit|facebookexternalhit|meta-externalagent|Twitterbot|WhatsApp|Pinterest|LinkedInBot|TelegramBot|Discordbot|Slackbot|Amazonbot|AhrefsBot|Slurp|SemrushBot|MJ12bot|DotBot|Applebot|idealo|ClaudeBot|Claude-Web|anthropic-ai|GPTBot|OAI-SearchBot|ChatGPT-User|PerplexityBot)#i";
    $_sg_is_declared = ($_sg_ua !== "" && preg_match($_sg_declared_bot_regex, $_sg_ua));
    $_sg_script = basename($_SERVER["SCRIPT_NAME"] ?? "");

    // (3b) ANTI-SPOOF de bots verificables — anadido 2026-08-29.
    //   El punto debil de $_sg_declared_bot_regex es que se FIA DEL UA. La campana de escaneo de
    //   credenciales/SSRF de agosto-2026 rotaba cientos de UAs de crawlers IA (GPTBot, ClaudeBot,
    //   Claude-User, PerplexityBot, OAI-SearchBot, GrokBot...) precisamente para caer en esa exencion.
    //   El FCrDNS del radar (cron_scraper_radar.php) no sirve para bots IA: no tienen PTR fiable.
    //   Los proveedores publican en su lugar listas JSON de prefijos, que cron_bot_ranges_update.php
    //   cachea en includes/bot_ranges.inc.php.
    //
    //   FAIL-OPEN por diseno (leccion FP 2026-06-25): si el fichero falta o esta rancio (>7 dias),
    //   o si la familia no esta en el, se confia en el UA igual que antes. Un fallo de red o un
    //   feed caido NUNCA puede convertir un crawler legitimo en "spoof".
    //
    //   $_sg_spoof_enforce: familias en las que un spoof PIERDE la exencion (queda sujeto a los
    //   rate-limits 4a/4b). HOY ESTA VACIO -> todo es observacion; ver el comentario de abajo.
    // 2026-09-01: VACIADO tras auditar 3 dias de datos. La verificacion queda en OBSERVACION PURA
    // (solo radar) para TODAS las familias. Motivo: las listas publicadas VAN CON RETRASO.
    // Evidencia: 81 IPs marcadas como 'oai-searchbot' falso (66 en rangos Azure), 183 hits; al
    // mirar QUE pedian resultaron ser OAI-SearchBot REAL - paginas de producto + JS + CSS + PNG
    // (renderizado completo), CERO rutas de escaneo, UA canonico. searchbot.json publica solo 35
    // prefijos y no cubre el pool de Azure que usan. Enforcing ahi = arriesgar la visibilidad IA
    // a cambio de casi nada: en 3 dias, 0 baneos derivados de esta regla.
    // El valor real de esta comprobacion es DETECTAR, no bloquear (misma leccion que el radar).
    // Para reactivar el enforcing de una familia, anadir su clave a este array.
    $_sg_spoof_enforce = [];
    if ($_sg_is_declared) {
        $_sg_fam = _sg_bot_family($_sg_ua);
        if ($_sg_fam !== null) {
            $_sg_ranges = _sg_bot_ranges();                       // null => fail-open
            if ($_sg_ranges !== null && isset($_sg_ranges[$_sg_fam])
                && !_sg_ip_in_ranges($_sg_ip, $_sg_ranges[$_sg_fam])) {
                $_sg_det = mysqli_real_escape_string($_sg_link,
                    "UA dice '$_sg_fam' pero la IP no esta en los rangos publicados por el proveedor");
                mysqli_query($_sg_link, "INSERT INTO scraper_radar (ip, kind, detail, hits)
                    VALUES (\"$_sg_ipe\", \"spoofed_ai_bot\", \"$_sg_det\", 1)
                    ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = NOW(), detail = VALUES(detail)");
                if (in_array($_sg_fam, $_sg_spoof_enforce, true)) $_sg_is_declared = false;
            }
        }
    }

    // (4a) CONDUCTUAL (agnostico al UA): rate-limit del firehose de catalogo.
    //      /products_new.php es el endpoint que la flota de scraping barre pagina a pagina
    //      (page=1..35). Un humano rara vez lista novedades >15 veces en 10 min; un scraper si.
    //      Esta regla NO depende del User-Agent -> aguanta la rotacion de UAs (la deteccion por
    //      UA es carrera perdida). Exenta de allowlist (ya filtrada arriba) + bots declarados.
    //      Umbral inicial: 15 hits / 10 min -> ban 24h. Ajustable (ver $_sg_cat_*).
    $_sg_cat_window = "10 MINUTE";
    $_sg_cat_thresh = 15;
    if (!$_sg_is_declared && $_sg_script === "products_new.php") {
        mysqli_query($_sg_link, "INSERT INTO scraper_observed (ip, reason, window_start, hits)
            VALUES (\"$_sg_ipe\", \"catalog\", NOW(), 1)
            ON DUPLICATE KEY UPDATE
                hits = IF(window_start < NOW() - INTERVAL $_sg_cat_window, 1, hits + 1),
                window_start = IF(window_start < NOW() - INTERVAL $_sg_cat_window, NOW(), window_start)");
        $_sg_res = mysqli_query($_sg_link, "SELECT hits FROM scraper_observed WHERE ip = \"$_sg_ipe\" AND reason = \"catalog\" LIMIT 1");
        $_sg_row = $_sg_res ? mysqli_fetch_assoc($_sg_res) : null;
        if ($_sg_res) mysqli_free_result($_sg_res);
        if ($_sg_row && (int)$_sg_row["hits"] >= $_sg_cat_thresh) {
            mysqli_query($_sg_link, "INSERT INTO scraper_blacklist (ip, ua, reason, expires_at)
                VALUES (\"$_sg_ipe\", \"$_sg_uae\", \"ratelimit_catalog\", DATE_ADD(NOW(), INTERVAL 24 HOUR))
                ON DUPLICATE KEY UPDATE last_seen = NOW(), hits = hits + 1,
                    expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR),
                    ua = VALUES(ua),
                    reason = IF(reason LIKE \"%ratelimit_catalog%\", reason, CONCAT(reason, \"+ratelimit_catalog\"))");
            mysqli_query($_sg_link, "DELETE FROM scraper_observed WHERE ip = \"$_sg_ipe\" AND reason = \"catalog\"");
            mysqli_close($_sg_link);
            _sg_deny_403();
        }
    }

    // (4b) Heuristica UA antiguo del pool residencial movil + rate-limit 5/60s.
    //      REVERTIDO 2026-06-25: se quitaron "Mac OS X 10_12/13/14" y "Windows NT 6.1" porque
    //      cazaban HUMANOS REALES (Firefox 109/Win7 residencial: orange.es, comunitel con 8 acciones
    //      de carrito). Win7 y macOS viejo son configs legitimas raras, no firma fiable de scraper.
    //      Solo UAs moviles del pool original (validados contra la flota en mayo).
    $_sg_old_ua_regex = "#(SM-G900P Build|Pixel 2 Build/OPD3|Nexus 5 Build/MRA58N|iPhone OS 11_0.*Chrome|Android 7\.0;\) AppleWebKit/537\.36 \(HTML, like Gecko\))#";

    if ($_sg_ua === "" || !preg_match($_sg_old_ua_regex, $_sg_ua) || $_sg_is_declared) {
        mysqli_close($_sg_link);
        return;
    }

    // Rate-limit atomico: INSERT ... ON DUPLICATE KEY UPDATE (sin race condition).
    // Si la ventana de 60s caduco, resetea hits=1 y window_start=NOW; si no, incrementa.
    mysqli_query($_sg_link, "INSERT INTO scraper_observed (ip, reason, window_start, hits)
        VALUES (\"$_sg_ipe\", \"oldua\", NOW(), 1)
        ON DUPLICATE KEY UPDATE
            hits = IF(window_start < NOW() - INTERVAL 60 SECOND, 1, hits + 1),
            window_start = IF(window_start < NOW() - INTERVAL 60 SECOND, NOW(), window_start)");

    $_sg_res = mysqli_query($_sg_link, "SELECT hits FROM scraper_observed WHERE ip = \"$_sg_ipe\" AND reason = \"oldua\" LIMIT 1");
    $_sg_row = $_sg_res ? mysqli_fetch_assoc($_sg_res) : null;
    if ($_sg_res) mysqli_free_result($_sg_res);
    $_sg_hits = $_sg_row ? (int)$_sg_row["hits"] : 0;

    if ($_sg_hits < 5) {
        mysqli_close($_sg_link);
        return;
    }

    // >=5 hits en 60s -> autoban 24h (idempotente)
    mysqli_query($_sg_link, "INSERT INTO scraper_blacklist (ip, ua, reason, expires_at)
        VALUES (\"$_sg_ipe\", \"$_sg_uae\", \"ratelimit_oldua\", DATE_ADD(NOW(), INTERVAL 24 HOUR))
        ON DUPLICATE KEY UPDATE last_seen = NOW(), hits = hits + 1,
            expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR),
            ua = VALUES(ua),
            reason = IF(reason LIKE \"%ratelimit_oldua%\", reason, CONCAT(reason, \"+ratelimit_oldua\"))");
    mysqli_query($_sg_link, "DELETE FROM scraper_observed WHERE ip = \"$_sg_ipe\" AND reason = \"oldua\"");
    mysqli_close($_sg_link);
    _sg_deny_403();

} catch (\Throwable $_sg_e) {
    // Fail-open: cualquier fallo de BD -> no bloquear, no romper la tienda.
    if ($_sg_link instanceof mysqli) { @mysqli_close($_sg_link); }
    return;
}


/**
 * Familia verificable que declara el UA, o null si el UA no declara ninguna de las que
 * publican rangos de IP. Solo estas se pueden comprobar; el resto (Applebot, Yandex, Meta,
 * LinkedIn, Telegram, Ahrefs...) sigue confiando en el UA como hasta ahora.
 */
function _sg_bot_family(string $ua): ?string {
    static $map = [
        "#ChatGPT-User#i"                                 => "chatgpt-user",
        "#OAI-SearchBot#i"                                => "oai-searchbot",
        "#GPTBot#i"                                       => "gptbot",
        "#(ClaudeBot|Claude-User|Claude-Web|Claude-SearchBot|anthropic-ai)#i" => "anthropic",
        "#PerplexityBot#i"                                => "perplexitybot",
        "#Perplexity-User#i"                              => "perplexity-user",
        "#(Googlebot|GoogleOther|Storebot-Google|AdsBot-Google|Google-InspectionTool|APIs-Google|FeedFetcher-Google)#i" => "google",
        "#(bingbot|BingPreview|adidxbot|msnbot)#i"        => "bingbot",
    ];
    foreach ($map as $re => $fam) if (preg_match($re, $ua)) return $fam;
    return null;
}

/**
 * Rangos publicados, cacheados en memoria por request. Devuelve null (=> fail-open) si el
 * fichero no existe, no se puede leer o tiene mas de 7 dias (feeds sin actualizar).
 */
function _sg_bot_ranges(): ?array {
    static $cache = false;
    if ($cache !== false) return $cache;
    $cache = null;
    $f = __DIR__ . "/bot_ranges.inc.php";
    if (is_readable($f)) {
        $d = @include $f;
        if (is_array($d) && !empty($d["families"]) && !empty($d["generated_at"])
            && (time() - (int)$d["generated_at"]) < 7 * 86400) {
            $cache = $d["families"];
        }
    }
    return $cache;
}

/** ¿Esta $ip dentro de los prefijos de la familia? IPv4 por busqueda binaria, IPv6 lineal. */
function _sg_ip_in_ranges(string $ip, array $fam): bool {
    if (strpos($ip, ":") === false) {
        $n = ip2long($ip);
        if ($n === false) return false;
        $n &= 0xFFFFFFFF;
        $v4 = $fam["v4"] ?? [];
        $lo = 0; $hi = count($v4) - 1;          // ordenado por inicio en el generador
        while ($lo <= $hi) {
            $mid = ($lo + $hi) >> 1;
            if ($n < $v4[$mid][0])      $hi = $mid - 1;
            elseif ($n > $v4[$mid][1])  $lo = $mid + 1;
            else                        return true;
        }
        return false;
    }
    $bin = @inet_pton($ip);
    if ($bin === false || strlen($bin) !== 16) return false;
    $hex = bin2hex($bin);
    foreach ($fam["v6"] ?? [] as $p) {
        $nib = intdiv((int)$p[1], 4);                       // nibbles completos que hay que comparar
        if ($nib > 0 && strncmp($hex, $p[0], $nib) !== 0) continue;
        $rest = (int)$p[1] % 4;
        if ($rest === 0) return true;
        $m = (0xF << (4 - $rest)) & 0xF;                    // nibble parcial
        if ((hexdec($hex[$nib]) & $m) === (hexdec($p[0][$nib]) & $m)) return true;
    }
    return false;
}

function _sg_deny_403() {
    http_response_code(403);
    header("Content-Type: text/html; charset=utf-8");
    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Retry-After: 86400");
    header("X-Robots-Tag: noindex, nofollow");
    echo "<!doctype html><html lang=\"es\"><head><meta charset=\"utf-8\"><title>403 Forbidden</title><meta name=\"robots\" content=\"noindex,nofollow\"></head><body style=\"font-family:sans-serif;text-align:center;padding:50px\"><h1>403 Forbidden</h1><p>Acceso denegado.</p></body></html>";
    exit;
}
