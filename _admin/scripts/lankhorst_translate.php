<?php
/**
 * Script CLI para traducir y maquetar productos Lankhorst ya importados sin LLM.
 *
 * Detecta productos con `products_import_origin='lankhorst'` cuyo nombre o descripción
 * en ES es idéntica al EN (síntoma del importador con skip_translation=1) y aplica:
 *   1. Traducción del nombre EN→ES (con preservación del prefijo de marca)
 *   2. Traducción de la descripción EN→ES
 *   3. Maquetado HTML estilo Cressi/Garmin (EN y ES)
 *
 * Uso (CLI):
 *   php lankhorst_translate.php DRY              # solo lista candidatos
 *   php lankhorst_translate.php                  # aplica a todos
 *   php lankhorst_translate.php PID 361466 ...   # solo esos pids
 *   php lankhorst_translate.php LIMIT 5          # primeros 5
 *
 * Vía HTTP (con auth admin):
 *   ?dry=1, ?pid=361466,361467, ?limit=5
 */

// ---- Standalone (no application_top) ----
$confPath = '/home/francobordo/public_html/includes/configure.php';
$conf = file_get_contents($confPath);
preg_match("/'DB_SERVER',\s*'([^']+)'/", $conf, $m); $DB_HOST = $m[1] ?? 'localhost';
preg_match("/'DB_SERVER_USERNAME',\s*'([^']+)'/", $conf, $m); $DB_USER = $m[1];
preg_match("/'DB_SERVER_PASSWORD',\s*'([^']+)'/", $conf, $m); $DB_PASS = $m[1];
preg_match("/'DB_DATABASE',\s*'([^']+)'/", $conf, $m); $DB_NAME = $m[1];

set_time_limit(0);
ini_set('memory_limit', '-1');

$isCli = (php_sapi_name() === 'cli');
$dryRun = false;
$onlyPids = [];
$limit = 0;

if ($isCli) {
    $args = $argv;
    array_shift($args);
    while (!empty($args)) {
        $a = strtoupper(array_shift($args));
        if ($a === 'DRY') $dryRun = true;
        elseif ($a === 'LIMIT') $limit = (int) array_shift($args);
        elseif ($a === 'PID') {
            while (!empty($args) && ctype_digit($args[0])) $onlyPids[] = (int) array_shift($args);
        }
    }
} else {
    // HTTP mode (require admin login - check session var same as cPanel admin)
    require_once '/home/francobordo/public_html/_admin/includes/application_top.php';
    if (!tep_session_is_registered('admin')) { http_response_code(403); exit('Unauthorized'); }
    @header('Content-Type: text/plain; charset=utf-8');
    @header('X-Accel-Buffering: no');
    while (ob_get_level() > 0) @ob_end_flush();
    @ob_implicit_flush(true);
    if (session_status() === PHP_SESSION_ACTIVE) @session_write_close();
    $dryRun = !empty($_GET['dry']);
    if (!empty($_GET['pid'])) $onlyPids = array_map('intval', explode(',', $_GET['pid']));
    if (!empty($_GET['limit'])) $limit = (int) $_GET['limit'];
}

const LANG_ID_ES = 3;
const LANG_ID_EN = 1;
const PRODUCT_NAME_MAX = 80;
const LLM_URL = 'http://217.127.199.171:28001/v1/chat/completions';
const LLM_MODEL = 'qwen36-sakamaki-nvfp4';
const BRAND_NO_PREFIX = ['GENERICO'];
const BRAND_ALIASES = [
    'BEP'        => 'BEP Marine',
    'LEWMAR'     => 'Lewmar',
    'ENO'        => 'Eno',
    'Boss audio' => 'Boss Audio',
];
const LLM_PROMPT_NAME = 'Eres un traductor profesional de inglés a español especializado en productos náuticos, marinos, hardware y accesorios. Usa terminología técnica náutica precisa en español de España. Conserva marcas, modelos, códigos alfanuméricos y unidades (mm, cm, kg, V, W, L) sin traducir. Texto plano. Responde SOLO con la traducción, sin comentarios ni explicaciones, sin comillas.';
const LLM_PROMPT_DESC = 'Eres un traductor profesional de inglés a español especializado en productos náuticos, marinos, hardware y accesorios. Usa terminología técnica náutica precisa en español de España. Conserva marcas, modelos, códigos alfanuméricos y unidades sin traducir. Conserva los <br> y <p>/<ul>/<li>/<strong> si los hay. Responde SOLO con la traducción, sin comentarios ni explicaciones.';
const LLM_FORMAT_PROMPT_ES = "Eres un experto en maquetar fichas de producto náuticas/marinas. Recibes una descripción comercial en ESPAÑOL y la transformas en HTML legible y atractivo.\n\nREGLAS OBLIGATORIAS:\n\n1. PÁRRAFO INTRODUCTORIO: el primer <p> es la frase descriptiva más completa del producto.\n\n2. AGRUPACIÓN POR SECCIONES (OBLIGATORIO si el producto tiene >6 features): clasifica las features en secciones temáticas con <h3>. Ejemplos: \"Características principales\", \"Materiales\", \"Instalación y uso\", \"Especificaciones técnicas\", \"Compatibilidad\". Cada sección abre con <h3>...</h3> y debajo un <ul><li>...</li></ul>. Si el producto tiene <6 features, una sola lista sin h3.\n\n3. ÉNFASIS CON <strong>: en CADA <li>, identifica el concepto clave (1-4 palabras al inicio) y envuélvelo en <strong>. Sigue al concepto con dos puntos \":\" + el resto de la frase.\n\n4. PRESERVA enlaces <a href> y <sup> existentes sin tocarlos.\n\n5. NO resumas, NO parafrasees, NO inventes información: conserva TODO el texto original. Solo añades estructura HTML, secciones, bold y dos puntos.\n\n6. Si el texto de entrada es solo 1-2 frases cortas (descripción mínima), devuelve <p>texto</p> sin lista.\n\n7. Etiquetas permitidas: <h3>, <h4>, <p>, <ul>, <li>, <strong>, <a>, <sup>. Prohibidas: <h1>, <h2>, <br>, <div>, <span>.\n\n8. Salida: SOLO el HTML, sin markdown, sin comentarios, sin tres-back-ticks.";
const LLM_FORMAT_PROMPT_EN = "You are an expert in formatting nautical/marine product datasheets. You receive a commercial description in ENGLISH and transform it into clean, readable HTML.\n\nMANDATORY RULES:\n\n1. INTRODUCTORY PARAGRAPH: the first <p> is the most complete descriptive sentence about the product.\n\n2. SECTION GROUPING (MANDATORY if product has >6 features): classify features into thematic sections with <h3>. Examples: \"Key features\", \"Materials\", \"Installation & use\", \"Technical specs\", \"Compatibility\". Each section opens with <h3>...</h3> followed by <ul><li>...</li></ul>. If <6 features, one single list without h3.\n\n3. <strong> EMPHASIS: in EACH <li>, identify the key concept (1-4 words at start) and wrap it in <strong>. Follow with colon \":\" + rest of the sentence.\n\n4. PRESERVE existing <a href> and <sup> tags untouched.\n\n5. DO NOT summarize, DO NOT paraphrase, DO NOT invent information: keep ALL original text.\n\n6. If the input is only 1-2 short sentences (minimal description), return <p>text</p> without a list.\n\n7. Allowed tags: <h3>, <h4>, <p>, <ul>, <li>, <strong>, <a>, <sup>. Forbidden: <h1>, <h2>, <br>, <div>, <span>.\n\n8. Output: ONLY the HTML, no markdown, no comments, no triple-backticks.";

function lankNormalizeManufacturer($name) {
    $name = trim(preg_replace('/\s+/', ' ', (string) $name));
    if ($name === '') return '';
    if (isset(BRAND_ALIASES[$name])) return BRAND_ALIASES[$name];
    $upKey = strtoupper($name);
    foreach (BRAND_ALIASES as $k => $v) { if (strtoupper($k) === $upKey) return $v; }
    $words = explode(' ', $name);
    $out = [];
    foreach ($words as $w) {
        if ($w === '') continue;
        $alpha = preg_replace('/[^A-Za-z]/', '', $w);
        if (strlen($alpha) > 0 && strtoupper($alpha) === $alpha && strlen($alpha) <= 4) $out[] = $w;
        else $out[] = mb_convert_case(mb_strtolower($w, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }
    return implode(' ', $out);
}

function brandPrefix($brand, $name) {
    $brand = trim((string) $brand);
    if ($brand === '' || in_array(strtoupper($brand), BRAND_NO_PREFIX, true)) return '';
    $brandNorm = lankNormalizeManufacturer($brand);
    $firstWord = strtoupper(trim(explode(' ', trim($name))[0] ?? ''));
    if ($firstWord !== '' && $firstWord === strtoupper($brandNorm)) return '';
    if (mb_stripos(trim($name), $brandNorm . ' ', 0, 'UTF-8') === 0) return '';
    return $brandNorm . ' ';
}

function stripBrandPrefix($name, $brand) {
    if ($brand === '') return $name;
    $brandNorm = lankNormalizeManufacturer($brand);
    if (mb_stripos(trim($name), $brandNorm . ' ', 0, 'UTF-8') === 0) {
        return trim(mb_substr(trim($name), mb_strlen($brandNorm) + 1, null, 'UTF-8'));
    }
    return $name;
}

function llmCall($systemPrompt, $userText, $maxTokens = 1500, $maxRetries = 2) {
    if (trim((string) $userText) === '') return '';
    $payload = json_encode([
        'model' => LLM_MODEL,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userText],
        ],
        'temperature' => 0.2, 'top_p' => 0.8, 'top_k' => 20,
        'max_tokens' => $maxTokens,
        'chat_template_kwargs' => ['enable_thinking' => false],
    ], JSON_UNESCAPED_UNICODE);
    for ($i = 0; $i <= $maxRetries; $i++) {
        $ch = curl_init(LLM_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);
        if ($resp !== false && $code === 200) {
            $j = json_decode($resp, true);
            $content = $j['choices'][0]['message']['content'] ?? null;
            if (is_string($content) && trim($content) !== '') return trim($content);
        }
        usleep(500000);
    }
    return '';
}

function formatLooksValid($html, $minLenInput) {
    if (!is_string($html) || trim($html) === '') return false;
    $low = strtolower($html);
    if (strpos($low, '<p>') === false && strpos($low, '<ul>') === false && strpos($low, '<h3>') === false) return false;
    $plainOut = mb_strlen(trim(strip_tags($html)), 'UTF-8');
    if ($minLenInput > 200 && $plainOut < $minLenInput * 0.4) return false;
    return true;
}

function logMsg($s) {
    global $isCli;
    echo '[' . date('H:i:s') . '] ' . $s . "\n";
    if (!$isCli) @flush();
}

// ---- Conexión BD ----
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_error) { logMsg("ERROR DB: " . $mysqli->connect_error); exit(1); }
$mysqli->set_charset('utf8');

// ---- Selección de candidatos ----
$where = "p.products_import_origin = 'lankhorst'";
if (!empty($onlyPids)) {
    $where .= " AND p.products_id IN (" . implode(',', $onlyPids) . ")";
}
$sql = "SELECT p.products_id, p.manufacturers_id, m.manufacturers_name AS brand,
               pd_es.products_name AS name_es, pd_en.products_name AS name_en,
               pd_es.products_description AS desc_es, pd_en.products_description AS desc_en
        FROM products p
        LEFT JOIN manufacturers m ON m.manufacturers_id = p.manufacturers_id
        LEFT JOIN products_description pd_es ON pd_es.products_id = p.products_id AND pd_es.language_id = " . LANG_ID_ES . "
        LEFT JOIN products_description pd_en ON pd_en.products_id = p.products_id AND pd_en.language_id = " . LANG_ID_EN . "
        WHERE $where
        ORDER BY p.products_id";
$res = $mysqli->query($sql);

$candidates = [];
while ($row = $res->fetch_assoc()) {
    $needsName = ($row['name_es'] !== null && $row['name_es'] === $row['name_en']);
    $needsDesc = ($row['desc_es'] !== null && $row['desc_es'] === $row['desc_en']);
    if (!$needsName && !$needsDesc) continue;
    $row['needs_name'] = $needsName;
    $row['needs_desc'] = $needsDesc;
    $candidates[] = $row;
    if ($limit > 0 && count($candidates) >= $limit) break;
}

logMsg("Candidatos: " . count($candidates) . ($dryRun ? " (dry-run, no toca BD)" : ""));

if (empty($candidates)) { logMsg("Nada que hacer."); exit(0); }

if ($dryRun) {
    foreach ($candidates as $c) {
        $what = [];
        if ($c['needs_name']) $what[] = 'name';
        if ($c['needs_desc']) $what[] = 'desc';
        logMsg(sprintf("  pid=%d brand=%s [%s] name=\"%s\"",
            $c['products_id'], $c['brand'] ?? '?', implode('+', $what),
            mb_substr($c['name_en'] ?? '', 0, 60, 'UTF-8')));
    }
    exit(0);
}

$processed = $errs = 0;
foreach ($candidates as $c) {
    $pid = (int) $c['products_id'];
    $brand = (string) ($c['brand'] ?? '');
    $nameEnRaw = stripBrandPrefix((string) $c['name_en'], $brand);
    $prefix = brandPrefix($brand, $nameEnRaw);

    $nameEsNew = $c['name_es'];
    if ($c['needs_name']) {
        $tn = llmCall(LLM_PROMPT_NAME, $nameEnRaw, 200);
        if ($tn !== '') {
            $nameEsNew = mb_substr($prefix . $tn, 0, PRODUCT_NAME_MAX, 'UTF-8');
        }
    }

    $descEnNew = (string) $c['desc_en'];
    $descEsNew = (string) $c['desc_es'];
    if ($c['needs_desc']) {
        // Traducir EN→ES
        $td = llmCall(LLM_PROMPT_DESC, $descEnNew, 1500);
        if ($td !== '') $descEsNew = $td;
        // Maquetar EN
        $inLenEn = mb_strlen(strip_tags($descEnNew), 'UTF-8');
        $fmtEn = llmCall(LLM_FORMAT_PROMPT_EN, $descEnNew, 2500);
        if (formatLooksValid($fmtEn, $inLenEn)) $descEnNew = $fmtEn;
        // Maquetar ES
        $inLenEs = mb_strlen(strip_tags($descEsNew), 'UTF-8');
        $fmtEs = llmCall(LLM_FORMAT_PROMPT_ES, $descEsNew, 2500);
        if (formatLooksValid($fmtEs, $inLenEs)) $descEsNew = $fmtEs;
    }

    $qNameEs = $mysqli->real_escape_string($nameEsNew);
    $qDescEs = $mysqli->real_escape_string($descEsNew);
    $qDescEn = $mysqli->real_escape_string($descEnNew);
    $okEs = $mysqli->query("UPDATE products_description SET products_name='$qNameEs', products_description='$qDescEs' WHERE products_id=$pid AND language_id=" . LANG_ID_ES);
    $okEn = true;
    if ($c['needs_desc']) {
        $okEn = $mysqli->query("UPDATE products_description SET products_description='$qDescEn' WHERE products_id=$pid AND language_id=" . LANG_ID_EN);
    }

    if ($okEs && $okEn) {
        $processed++;
        logMsg(sprintf("OK pid=%d brand=%s name='%s'",
            $pid, $brand, mb_substr($nameEsNew, 0, 60, 'UTF-8')));
    } else {
        $errs++;
        logMsg("ERROR pid=$pid: " . $mysqli->error);
    }
}

logMsg("==================== RESUMEN ====================");
logMsg("Procesados: $processed | Errores: $errs");
