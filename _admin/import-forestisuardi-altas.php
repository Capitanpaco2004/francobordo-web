<?php
require 'includes/application_top.php';
require_once DIR_FS_DOCUMENT_ROOT . 'includes/vendor/autoload.php';
require_once dirname(__FILE__) . '/includes/mb_reformat_helpers.php';

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);

/* ──────────────────────────────────────────────────────────────────────────
 * Importador Foresti & Suardi (altas)
 *
 * Fuentes (pre-construidas offline, en /import/FS/):
 *   - fs_catalog.json   code → {pvp, pag, section, parent_key, type, variation_label, var_image}
 *                       (solo códigos del PDF que SÍ existen en el catálogo web)
 *   - fs_parents.json   parent_key → {name, short_desc, desc(IT), family, subcat, cats[], images[], codes[]}
 *   - fs_subcat_es.json nombre IT (familia o subcategoría) → nombre ES
 *
 * El precio viene del PDF Listino (PVP sugerido, IVA excl.). El resto (nombre,
 * descripción IT, imágenes, categoría) del catálogo WooCommerce. El enlace es por
 * SKU = código de artículo. Cada producto "variable" de WooCommerce agrupa varias
 * variaciones (cada una con su SKU/precio) → un producto francobordo con variantes.
 * ────────────────────────────────────────────────────────────────────────── */

const FS_DIR        = '/home/francobordo/public_html/import/FS/';
const FS_CATALOG    = FS_DIR . 'fs_catalog.json';
const FS_PARENTS    = FS_DIR . 'fs_parents.json';
const FS_PARENTS_EN = FS_DIR . 'fs_parents_en.json';   // inglés real del locale /en/ del catálogo
const FS_SUBCAT_ES  = FS_DIR . 'fs_subcat_es.json';
define('IMG_ABS_DIR', dirname(dirname(__FILE__)) . '/images/productos/');

const PARENT_CATEGORY_NAME_ES = 'Foresti & Suardi Nuevos';
const PARENT_CATEGORY_NAME_EN = 'Foresti & Suardi New';
const TAX_CLASS_IVA21 = 1;
const LANG_ID_ES      = 3;
const LANG_ID_EN      = 1;
const G1_GROUP_ID     = 1;
const VARIANT_OPTION_ID = 3;              // "Modelo"
const PRODUCT_NAME_MAX = 96;
const NAME_SUFFIX     = ' - Foresti & Suardi';
const MFG_NAME        = 'Foresti & Suardi';
const IMG_HTTP_TIMEOUT = 15;
const IMG_MIN_BYTES   = 3072;
const MAX_SUBIMAGES   = 6;
const ORIGIN_FLAG     = 'forestisuardi';
const STOCK_SENTINEL  = -800;             // "bajo pedido" (convención francobordo) — Foresti se vende bajo pedido
const EAN_INTERNAL_PREFIX = 29;           // 20-28 los usan otros importadores; 29 → "2900…" (nunca 299, que es de QFac)
const FS_VAT_RATE     = 0.21;
const COST_MULT       = 0.50;             // coste = PVP × 0,50 (descuento 50% de Foresti)
const G1_FLOOR_FACTOR = 1.10;
const DEFAULT_WEIGHT  = 1.0;

const LLM_URL   = 'http://217.127.199.171:28001/v1/chat/completions';
const LLM_MODEL = 'qwen36-sakamaki-nvfp4';

const LLM_NAME_PROMPT_ES = "Traduce este nombre de producto náutico del italiano al ESPAÑOL. Conserva nombres propios de modelo/línea (ASTERION, SEXTANS, PROMETEO, etc.), marcas, códigos y unidades. Glosario náutico OBLIGATORIO: Bitta a scomparsa=Bita escamoteable, Bitta=Bita, Galloccia=Cornamusa, Alzapagliolo/Alzapaglioli=Cierre de pañol, Pagliolo=Pañol, Boccola di scarico=Desagüe, Scarico=Desagüe (NO escape), Presa a mare=Toma de mar, Passacavo=Pasacables, Oblò=Portillo, Tappo=Tapón, Golfare=Cáncamo, Ottone=Latón, Acciaio inox=Acero inoxidable, Portacanna=Portacañas, Corrimano=Pasamanos. Responde SOLO con el nombre traducido, una línea, sin comillas.";
const LLM_NAME_PROMPT_EN = "Translate this nautical product name from Italian to ENGLISH. Keep proper model/line names (ASTERION, SEXTANS, PROMETEO, etc.), brands, codes and units. Reply with ONLY the translated name, one line, no quotes.";

const LLM_TRANSLATE_ES = "Traduce el siguiente texto descriptivo de producto náutico del ITALIANO al ESPAÑOL. Conserva ÍNTEGRAMENTE toda la información, números, medidas, unidades (Watt, V, VDC, mm, °K), voltajes, acrónimos (LED, IP, AISI) y nombres propios. Glosario náutico: ottone=latón, acciaio inox=acero inox, oblò=portillo, passo d'uomo=registro de cubierta, passacavo=pasacables, bitta=bita, tappo=tapón, golfare=cáncamo, plafoniera=plafón, faretto=foco, ghiera=aro, scarico a mare=desagüe al mar, presa a mare=toma de mar, corrimano=pasamanos, cerniera=bisagra, maniglia=manilla, fanale=luz de navegación. NO resumas ni inventes. Devuelve SOLO la traducción en español, sin comentarios.";
const LLM_TRANSLATE_EN = "Translate the following nautical product description from ITALIAN to ENGLISH. Keep ALL information intact: numbers, measurements, units (Watt, V, VDC, mm, °K), voltages, acronyms (LED, IP, AISI) and proper names. Nautical glossary: ottone=brass, acciaio inox=stainless steel, oblò=porthole, passo d'uomo=deck hatch, passacavo=fairlead, bitta=bollard, tappo=cap/plug, golfare=eyebolt, plafoniera=ceiling light, faretto=spotlight, ghiera=ring, scarico a mare=skin fitting/drain, presa a mare=seacock, corrimano=handrail, cerniera=hinge, maniglia=handle, fanale=navigation light. DO NOT summarize or invent. Return ONLY the English translation, no comments.";
const LLM_FORMAT_PROMPT_ES = "Eres un experto en maquetar fichas de producto náuticas. Recibes una descripción comercial YA EN ESPAÑOL y la transformas en HTML legible.\n\nREGLAS:\n1. Primer <p>: frase introductoria (máx 5 frases por <p>).\n2. Si hay >6 características, agrúpalas bajo títulos <p><strong>Título</strong></p> (nunca <h1>-<h6>); antes de cada título inserta <p>&nbsp;</p>.\n3. Cada característica en su propio <p>• texto</p> (nunca <ul>/<li>). Concepto clave (1-4 palabras) en <strong> + dos puntos.\n4. Elimina ™ ® ©. Palabras EN MAYÚSCULAS de 4+ letras → Title Case (conserva acrónimos: LED, IP, USB, VDC, RGB, AISI).\n5. NO traduzcas, NO resumas, NO inventes: conserva TODO el texto, solo añades estructura HTML.\n6. Etiquetas permitidas: <p>, <strong>, <a>. Prohibidas: <h1>-<h6>, <br>, <div>, <span>, <ul>, <li>, <table>, <ol>.\n7. Salida: SOLO el HTML, sin markdown ni comentarios.";
const LLM_FORMAT_PROMPT_EN = "You format nautical product datasheets. You receive a description ALREADY IN ENGLISH and turn it into clean HTML.\n\nRULES:\n1. First <p>: intro sentence (max 5 sentences per <p>).\n2. If >6 features, group under <p><strong>Title</strong></p> headings (never <h1>-<h6>); insert <p>&nbsp;</p> before each title.\n3. Each feature in its own <p>• text</p> (never <ul>/<li>). Key concept (1-4 words) in <strong> + colon.\n4. Remove ™ ® ©. ALL-CAPS words 4+ letters → Title Case (keep acronyms: LED, IP, USB, VDC, RGB, AISI).\n5. DO NOT translate, summarize or invent: keep ALL text, only add HTML structure.\n6. Allowed tags: <p>, <strong>, <a>. Forbidden: <h1>-<h6>, <br>, <div>, <span>, <ul>, <li>, <table>, <ol>.\n7. Output: ONLY the HTML, no markdown, no comments.";
const LLM_LABEL_PROMPT_ES = "Traduce esta etiqueta corta de variante de producto náutico del italiano al ESPAÑOL (ej. acabado, material, voltaje, temperatura de color). Conserva números, unidades, °K, voltajes y acrónimos. Responde SOLO con la traducción, una línea, sin comillas.";

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$max    = isset($_POST['max']) ? (int) $_POST['max'] : (isset($_GET['max']) ? (int) $_GET['max'] : 0);
$dryRun = isset($_POST['dry_run']) || isset($_GET['dry_run']) || $action === 'dry_run';
$skipTranslation = isset($_POST['skip_translation']) || isset($_GET['skip_translation']);
$selectedFamily = trim((string) ($_POST['family'] ?? $_GET['family'] ?? 'all'));
$codesParam = trim((string) ($_POST['codes'] ?? $_GET['codes'] ?? ''));

function logMsg($msg) {
    $line = '[' . date('H:i:s') . '] ' . $msg . "\n";
    echo '<pre style="margin:0;padding:2px 8px;border-bottom:1px solid #eee;white-space:pre-wrap;overflow-wrap:break-word;word-break:break-word;font-family:monospace;font-size:12px;">' . htmlspecialchars($line) . '</pre>';
    @flush();
}

/* ── EAN-13 ── */
function ean13Checksum($payload12) {
    if (strlen($payload12) !== 12 || !ctype_digit($payload12)) return -1;
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $d = (int) $payload12[$i];
        $sum += ($i % 2 === 0) ? $d : $d * 3;
    }
    return (10 - ($sum % 10)) % 10;
}
function isValidEan13($ean) {
    $ean = trim((string) $ean);
    if (strlen($ean) !== 13 || !ctype_digit($ean)) return false;
    return ean13Checksum(substr($ean, 0, 12)) === (int) $ean[12];
}
/** EAN interno único por id (pid para sueltos, products_attributes_id para variantes).
 *  Prefijo 29 → siempre "2900…" con los ids actuales; rechaza explícitamente "299…" (rango de QFac). */
function generateInternalEan13($id, $providerPrefix) {
    $pp = (int) $providerPrefix;
    if ($pp < 20 || $pp > 29) return '';
    if ($id <= 0 || $id > 9999999999) return '';
    $payload = str_pad((string) $pp, 2, '0', STR_PAD_LEFT) . str_pad((string) $id, 10, '0', STR_PAD_LEFT);
    if (strncmp($payload, '299', 3) === 0) return '';   // no pisar el rango interno de QFacWin
    $check = ean13Checksum($payload);
    return $check < 0 ? '' : ($payload . $check);
}

/** Redondea un PVP NETO de modo que el precio CON IVA quede en múltiplo de 0,05. */
function roundToNickel($net) {
    $withIva = ((float) $net) * (1 + FS_VAT_RATE);
    $rounded = round($withIva * 20) / 20;
    return round($rounded / (1 + FS_VAT_RATE), 4);
}

/** G1 (Profesionales) con tiers de margen + piso cost×1.10. */
function calcG1Price($price, $cost) {
    $price = (float) $price;
    $cost = (float) $cost;
    if ($price <= 0) return 0.0;
    $mult = 0.90;
    if ($cost > 0) {
        $margin = ($price - $cost) / $price;
        if      ($margin >= 0.45) $mult = 0.75;
        elseif  ($margin >= 0.40) $mult = 0.80;
        elseif  ($margin >= 0.35) $mult = 0.82;
        elseif  ($margin >= 0.30) $mult = 0.85;
    }
    return round(max($price * $mult, $cost * G1_FLOOR_FACTOR), 4);
}

function fsSlugify($text, $maxLen = 50) {
    $t = trim((string) $text);
    if (function_exists('iconv')) {
        $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t);
        if ($conv !== false && $conv !== '') $t = $conv;
    }
    $t = strtolower($t);
    $t = preg_replace('/[^a-z0-9]+/', '-', $t);
    $t = trim($t, '-');
    if (strlen($t) > $maxLen) $t = substr($t, 0, $maxLen);
    return trim($t, '-') ?: 'producto';
}

function fsStripSuffix($name) {
    $s = (string) $name;
    $suf = NAME_SUFFIX;
    $sufLen = strlen($suf);
    if (strlen($s) >= $sufLen && substr_compare($s, $suf, -$sufLen, $sufLen, true) === 0) {
        $s = rtrim(substr($s, 0, -$sufLen));
    }
    return $s;
}
function fsApplySuffix($name) {
    $name = html_entity_decode((string) $name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $name = fsStripSuffix($name);
    $name = trim(preg_replace('/\s+/u', ' ', $name));
    if ($name === '') $name = '(sin nombre)';
    $suf = NAME_SUFFIX;
    $maxBody = PRODUCT_NAME_MAX - mb_strlen($suf, 'UTF-8');
    if (mb_strlen($name, 'UTF-8') > $maxBody) $name = rtrim(mb_substr($name, 0, $maxBody, 'UTF-8'));
    return $name . $suf;
}

/** Limpia la descripción HTML (italiana) a texto plano para el LLM. */
function fsCleanHtmlToText($html) {
    if ($html === null || $html === '') return '';
    $html = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html);
    $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
    $html = preg_replace('#<\s*br\s*/?\s*>#i', "\n", $html);
    $html = preg_replace('#</\s*(p|div|li|tr|h[1-6]|ul|ol|table|article|section)\s*>#i', "\n", $html);
    $html = preg_replace('#<\s*li\b[^>]*>#i', "- ", $html);
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace("\xc2\xa0", ' ', $text);
    $text = str_replace("\xef\xbf\xbd", '', $text);   // U+FFFD residual del catálogo
    $lines = preg_split("/\r\n|\r|\n/", $text);
    $out = []; $empty = 0;
    foreach ($lines as $l) {
        $l = trim(preg_replace('/[ \t\x{A0}]+/u', ' ', $l));
        if ($l === '') { if ($empty < 1 && !empty($out)) $out[] = ''; $empty++; continue; }
        $out[] = $l; $empty = 0;
    }
    return trim(implode("\n", $out));
}

function fsBrowserUA() {
    return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';
}

const FS_AUX_SUBDIR = 'fs-content/';   // bajo /images/productos/
const FS_AUX_URL    = '/images/productos/fs-content/';

/** Extrae de la descripción fuente el bloque <table class="table-data-sheet"> (dibujo
 *  técnico + iconos de certificación + texto cert) y la lista de URLs de imágenes
 *  a las que apunta (típicamente newcat.forestiesuardi.it/...). Devuelve:
 *    ['prose'   => html sin la tabla de specs (lo que va al LLM)
 *     'media'   => HTML crudo de la tabla (con src= originales)
 *     'imgUrls' => URLs únicas a descargar localmente] */
function fsExtractMedia($html) {
    $html = (string) $html;
    // (1) Captura TODAS las tablas table-data-sheet (Starlight: dibujo + iconos cert).
    $blocks = [];
    if (preg_match_all('#<table[^>]*class=["\']?table-data-sheet[^>]*>.*?</table>#is', $html, $mm)) {
        foreach ($mm[0] as $b) $blocks[] = $b;
    }
    $prose = preg_replace('#<table[^>]*class=["\']?table-data-sheet[^>]*>.*?</table>#is', '', $html);
    // (2) Los productos Yachting/INOX traen el dibujo de cotas como <img> SUELTO en la prosa
    //     (ej. 9105dwg.jpg, 9320DWG.jpg). Se extraen, se quitan de la prosa y se añaden al
    //     bloque de medios para no perderlos (fsCleanHtmlToText quitaría todo el HTML).
    if (preg_match_all('#<img\b[^>]*>#i', $prose, $mi)) {
        foreach ($mi[0] as $imgTag) {
            if (preg_match('#src=["\']([^"\']+)["\']#i', $imgTag, $ms)) {
                $u = $ms[1];
                if (stripos($u, 'forestiesuardi.it') !== false) {
                    $blocks[] = '<p style="text-align:center;margin:8px 0;">' . $imgTag . '</p>';
                    $prose = str_replace($imgTag, '', $prose);
                }
            }
        }
    }
    $prose = preg_replace('#<hr\s*/?>#i', '', $prose);
    $media = $blocks ? implode("\n<p>&nbsp;</p>\n", $blocks) : '';
    $urls = [];
    if (preg_match_all('#src=["\']([^"\']+)["\']#i', $media, $mu)) {
        foreach ($mu[1] as $u) {
            if (stripos($u, 'forestiesuardi.it') !== false) {
                $urls[] = html_entity_decode($u, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }
        $urls = array_values(array_unique($urls));
    }
    return ['prose' => $prose, 'media' => $media, 'imgUrls' => $urls];
}

/** Descarga una imagen auxiliar (drawing/icono) a /images/productos/fs-content/<basename>.
 *  Idempotente: si ya existe (>0 bytes) la reutiliza. Devuelve el filename relativo o ''. */
function fsDownloadAuxImage($url) {
    // El basename del catálogo puede traer ESPACIOS u otros caracteres ("sextans s 1.jpg")
    // o venir ya %-encodeado. Se decodifica para detectar la extensión y se sanea para el
    // nombre LOCAL (sin espacios), y se %-encodea la URL para que curl la acepte.
    $rawBase = rawurldecode(basename((string) parse_url($url, PHP_URL_PATH)));
    if ($rawBase === '' || !preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $rawBase)) return '';
    $base = preg_replace('/-+/', '-', preg_replace('/[^A-Za-z0-9._-]+/', '-', $rawBase));
    $dir = IMG_ABS_DIR . FS_AUX_SUBDIR;
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $dest = $dir . $base;
    if (file_exists($dest) && filesize($dest) > 200) return $base;
    $urlEnc = str_replace(' ', '%20', $url);   // curl no acepta espacios crudos en la URL
    // descarga directa (sin el filtro IMG_MIN_BYTES de las fotos, los iconos miden ~2 KB)
    $ch = curl_init($urlEnc);
    $fp = fopen($dest, 'wb');
    if (!$fp) return '';
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => IMG_HTTP_TIMEOUT, CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => fsBrowserUA(), CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => ['Referer: https://catalogue.forestiesuardi.it/'],
    ]);
    $ok = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);
    fclose($fp);
    if (!$ok || $code !== 200 || filesize($dest) < 200) { @unlink($dest); return ''; }
    return $base;
}

/** Reescribe los src=URL del HTML para apuntar a /images/productos/fs-content/<basename>
 *  según el mapa $urlMap (url_original => filename_local). Las URLs sin local se quitan
 *  para no dejar imágenes rotas en la ficha. */
function fsRewriteMedia($html, array $urlMap) {
    foreach ($urlMap as $orig => $local) {
        $newSrc = $local !== '' ? (FS_AUX_URL . $local) : '';
        // src exact match (entidades posibles)
        $variants = [$orig, htmlspecialchars($orig, ENT_QUOTES)];
        foreach ($variants as $v) {
            if ($newSrc !== '') {
                $html = str_replace('src="' . $v . '"', 'src="' . $newSrc . '"', $html);
                $html = str_replace("src='" . $v . "'", "src='" . $newSrc . "'", $html);
            } else {
                // imagen sin descarga válida: borrar el <img> entero
                $html = preg_replace('#<img[^>]*src=["\']' . preg_quote($v, '#') . '["\'][^>]*>#i', '', $html);
            }
        }
    }
    return $html;
}

/** Tabla Osculati-style con las especificaciones de cada variante (1 fila por SKU). */
function fsBuildVariantTable(array $items, $lang) {
    if (count($items) < 1) return '';
    $hdrMap = [
        'es' => ['Input' => 'Entrada', 'Power' => 'Potencia', 'Altezza' => 'Altura',
                 'Finitura' => 'Acabado', 'Temperatura Colore' => 'Temperatura color',
                 'Colore LED' => 'Color LED', 'Tipologia' => 'Tipo', 'Volt' => 'Voltaje',
                 'Larghezza' => 'Ancho', 'Lunghezza' => 'Largo', 'Diametro' => 'Diámetro',
                 'Spessore' => 'Espesor', 'Base' => 'Base', 'Peso' => 'Peso',
                 'Lampadina' => 'Bombilla', 'Portata' => 'Caudal'],
        'en' => ['Input' => 'Input', 'Power' => 'Power', 'Altezza' => 'Height',
                 'Finitura' => 'Finish', 'Temperatura Colore' => 'Colour Temp.',
                 'Colore LED' => 'LED Colour', 'Tipologia' => 'Typology', 'Volt' => 'Voltage',
                 'Larghezza' => 'Width', 'Lunghezza' => 'Length', 'Diametro' => 'Diameter',
                 'Spessore' => 'Thickness', 'Base' => 'Base', 'Peso' => 'Weight',
                 'Lampadina' => 'Bulb', 'Portata' => 'Flow'],
    ];
    $valMap = [
        'es' => ['Cromato' => 'Cromado', 'Bianco' => 'Blanco', 'Alogena' => 'Halógena',
                 'Nichel' => 'Níquel', 'Ottone' => 'Latón', 'Acciaio Inox' => 'Acero Inox',
                 'Bianco caldo' => 'Blanco cálido', 'Bianco freddo' => 'Blanco frío',
                 'Sì' => 'Sí', 'No' => 'No'],
        'en' => ['Cromato' => 'Chromed', 'Bianco' => 'White', 'Alogena' => 'Halogen',
                 'Nichel' => 'Nickel', 'Ottone' => 'Brass', 'Acciaio Inox' => 'Stainless Steel'],
    ];
    $hdrTr = $hdrMap[$lang] ?? [];
    $valTr = $valMap[$lang] ?? [];
    $parsed = []; $keysOrder = [];
    foreach ($items as $code => $it) {
        $pairs = [];
        $vlabel = html_entity_decode((string) $it['vlabel'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        foreach (preg_split('/,\s*(?=[^,:]+:)/u', $vlabel) as $seg) {
            if (strpos($seg, ':') === false) continue;
            [$k, $v] = explode(':', $seg, 2);
            $k = trim($k);
            $v = ltrim(trim($v), '.');
            $v = str_replace("\xef\xbf\xbd", '', $v);
            $v = trim(preg_replace('/\s+/u', ' ', $v));
            // prefijo de 1-2 letras código + palabra real (ej "C Cromato" → "Cromato")
            if (preg_match('/^[A-Z]{1,2}\s+(\p{Lu}\p{Ll}+.*)$/u', $v, $mm)) $v = $mm[1];
            if ($k === '' || $v === '') continue;
            // traducción exacta si la hay; si no, por palabras (substring word-boundary)
            if (isset($valTr[$v])) {
                $v = $valTr[$v];
            } else {
                foreach ($valTr as $it => $tr) {
                    $v = preg_replace('/\b' . preg_quote($it, '/') . '\b/u', $tr, $v);
                }
            }
            $pairs[$k] = $v;
            if (!in_array($k, $keysOrder, true)) $keysOrder[] = $k;
        }
        $parsed[$code] = $pairs;
    }
    if (empty($keysOrder)) return '';
    $skuHdr = ($lang === 'es') ? 'Referencia' : 'SKU';
    $title  = ($lang === 'es') ? 'Tabla de especificaciones' : 'Specifications table';
    $FONT = 'font-family: tahoma, arial, helvetica, sans-serif; font-size: 10pt;';
    $open = '<table class="fs-spec-table" style="border-collapse: collapse; border: 1px solid rgb(206, 212, 217); margin: 10px 0;" border="1" cellspacing="3" cellpadding="3"><tbody>';
    $hCell = fn($t) => '<td style="background-color: #008cc6; text-align: center; padding: 4px;"><span style="' . $FONT . ' color: #ffffff; font-weight: bold;">' . htmlspecialchars($t) . '</span></td>';
    $dCell = fn($t) => '<td style="text-align: center; padding: 4px;"><span style="' . $FONT . '">' . htmlspecialchars($t) . '</span></td>';
    $h = "\n<p>&nbsp;</p>\n<p><strong>" . htmlspecialchars($title) . "</strong></p>\n" . $open . '<tr>' . $hCell($skuHdr);
    foreach ($keysOrder as $k) $h .= $hCell($hdrTr[$k] ?? $k);
    $h .= '</tr>';
    $i = 0;
    foreach ($items as $code => $it) {
        $i++;
        $bg = ($i % 2 === 0) ? ' style="background-color: #e2f2f9;"' : '';
        $h .= '<tr' . $bg . '>' . $dCell($code);
        foreach ($keysOrder as $k) $h .= $dCell($parsed[$code][$k] ?? '');
        $h .= '</tr>';
    }
    return $h . '</tbody></table>';
}

const LLM_CERT_PROMPT_ES = "Traduce este texto técnico de marcado de producto del italiano al español. Conserva números, normas (EN 62471), siglas (IP, LED). Devuelve SOLO la traducción, una línea, sin comillas.";

/** Traduce el texto plano dentro de la table-data-sheet a ES. Caché por texto.
 *  Reemplaza los nodos de texto traducibles (regex sobre los text nodes >5 chars). */
function fsTranslateMediaTextEs($mediaHtml, &$cache) {
    if (trim($mediaHtml) === '') return $mediaHtml;
    // Captura nodos de texto entre tags (excluye atributos)
    return preg_replace_callback('#>([^<>]{6,})<#u', function ($m) use (&$cache) {
        $t = trim($m[1]);
        if ($t === '' || preg_match('#^[\s\d.,\-/&;]+$#u', $t)) return $m[0];
        if (!isset($cache[$t])) {
            $tr = fsLlmLine(LLM_CERT_PROMPT_ES, $t);
            $cache[$t] = ($tr !== '') ? $tr : $t;
        }
        return '>' . str_replace($t, $cache[$t], $m[1]) . '<';
    }, $mediaHtml);
}

function downloadImage($url, $destAbs) {
    if (empty($url)) return false;
    $url = trim($url);
    if (strpos($url, ' ') !== false) return false;
    if (!preg_match('#^https?://#i', $url)) return false;
    $ch = curl_init($url);
    $fp = fopen($destAbs, 'wb');
    if (!$fp) return false;
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => IMG_HTTP_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => fsBrowserUA(),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
            'Referer: https://catalogue.forestiesuardi.it/',
        ],
    ]);
    $ok = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);
    fclose($fp);
    $ok = $ok && $code === 200 && filesize($destAbs) >= IMG_MIN_BYTES;
    if (!$ok) @unlink($destAbs);
    return $ok;
}
function downloadImagesToTmp(array $urls, $maxImages) {
    $seen = []; $tmpFiles = [];
    foreach ($urls as $url) {
        if (count($tmpFiles) >= $maxImages) break;
        $url = trim($url);
        if ($url === '' || isset($seen[$url])) continue;
        $seen[$url] = true;
        $tmpAbs = IMG_ABS_DIR . 'fs-tmp-' . uniqid('', true) . '.jpg';
        if (downloadImage($url, $tmpAbs)) $tmpFiles[] = $tmpAbs;
    }
    return $tmpFiles;
}

function llmCall($systemPrompt, $userText, $maxTokens = 2500, $maxRetries = 2) {
    if (trim((string) $userText) === '') return '';
    $payload = json_encode([
        'model' => LLM_MODEL,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userText],
        ],
        'temperature' => 0.2,
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
        usleep(400000);
    }
    return '';
}
function fsLlmLine($systemPrompt, $text) {
    $out = llmCall($systemPrompt, $text, 120);
    $out = trim(preg_replace('/\s+/u', ' ', strip_tags($out)));
    $out = trim($out, " \t\"'");
    return $out;
}
function fsFormatLooksValid($html, $minLenInput) {
    if (!is_string($html) || trim($html) === '') return false;
    if (stripos($html, '<p>') === false && stripos($html, '<strong>') === false) return false;
    $plainOut = mb_strlen(trim(strip_tags($html)), 'UTF-8');
    if ($minLenInput > 200 && $plainOut < $minLenInput * 0.4) return false;
    // anti-bucle: bullets repetidos
    if (preg_match_all('#<p>\s*•\s*(.*?)</p>#is', $html, $m) && count($m[1]) >= 3) {
        $items = array_map(fn($t) => mb_strtolower(trim(strip_tags($t)), 'UTF-8'), $m[1]);
        if (count($items) - count(array_unique($items)) >= max(2, count($items) * 0.4)) return false;
    }
    return true;
}

/** Etiquetas de variante: parsea "K: V, K2: V2" y conserva solo los valores de los
 *  atributos que DIFIEREN entre las variaciones del mismo padre. */
function fsVariantLabels(array $items) {
    $parsed = [];   // code => [key=>value]
    foreach ($items as $code => $it) {
        $pairs = [];
        $vlabel = html_entity_decode((string) $it['vlabel'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // separar por comas que preceden a "Clave:" (no por la coma decimal de "2,5 Watt")
        foreach (preg_split('/,\s*(?=[^,:]+:)/u', $vlabel) as $seg) {
            if (strpos($seg, ':') === false) continue;
            [$k, $v] = explode(':', $seg, 2);
            $k = trim($k);
            $v = ltrim(trim($v), '.');                 // ".C Cromato" → "C Cromato"
            $v = str_replace("\xef\xbf\xbd", '', $v);   // ".4000 �K" → "4000 K"
            $v = trim(preg_replace('/\s+/u', ' ', $v));
            if ($k !== '' && $v !== '') $pairs[$k] = $v;
        }
        $parsed[$code] = $pairs;
    }
    // claves cuyo valor varía
    $allKeys = [];
    foreach ($parsed as $p) foreach ($p as $k => $v) $allKeys[$k] = true;
    $diffKeys = [];
    foreach (array_keys($allKeys) as $k) {
        $vals = [];
        foreach ($parsed as $p) $vals[] = $p[$k] ?? '';
        if (count(array_unique($vals)) > 1) $diffKeys[] = $k;
    }
    $base = [];
    foreach ($items as $code => $it) {
        $vals = [];
        foreach ($diffKeys as $k) if (!empty($parsed[$code][$k])) $vals[] = $parsed[$code][$k];
        $base[$code] = implode(' / ', $vals);
    }
    // Devuelve, por código: la parte de atributos en italiano ('attr', traducible) y si
    // hay que añadir el código para desambiguar (cuando la parte de atributos se repite o está vacía).
    $counts = array_count_values($base);
    $out = [];
    foreach ($items as $code => $it) {
        $b = $base[$code];
        $out[$code] = ['attr' => $b, 'needs_code' => ($b === '' || ($counts[$b] ?? 0) > 1)];
    }
    return $out;
}

/* ── Dedup map (igual criterio que MB: SKU scoped por fabricante, EAN global) ── */
function buildExistingMap($mysqli, $mfgId) {
    $existing = [];
    $f = "(p.manufacturers_id = " . (int)$mfgId . " OR p.products_import_origin LIKE 'forestisuardi%')";
    foreach ([
        "SELECT LOWER(p.products_model) m FROM products p WHERE p.products_model<>'' AND $f",
        "SELECT LOWER(p.reference_prov) m FROM products p WHERE p.reference_prov<>'' AND $f",
        "SELECT LOWER(pa.reference) m FROM products_attributes pa INNER JOIN products p ON p.products_id=pa.products_id WHERE pa.reference<>'' AND $f",
        "SELECT LOWER(pa.reference_prov) m FROM products_attributes pa INNER JOIN products p ON p.products_id=pa.products_id WHERE pa.reference_prov<>'' AND $f",
    ] as $sql) {
        $r = $mysqli->query($sql);
        if ($r) while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
    }
    foreach ([
        "SELECT LOWER(product_ean) m FROM products WHERE product_ean<>'' AND product_ean IS NOT NULL",
        "SELECT LOWER(products_attributes_ean) m FROM products_attributes WHERE products_attributes_ean<>'' AND products_attributes_ean IS NOT NULL",
    ] as $sql) {
        $r = $mysqli->query($sql);
        if ($r) while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
    }
    return $existing;
}

function ensureManufacturer($mysqli, $name, $dryRun, &$createdLog) {
    $q = $mysqli->real_escape_string($name);
    $r = $mysqli->query("SELECT manufacturers_id FROM manufacturers WHERE manufacturers_name='$q' LIMIT 1");
    if ($r && $row = $r->fetch_assoc()) return (int) $row['manufacturers_id'];
    if ($dryRun) { $createdLog[] = "fabricante '$name' (dry-run, no creado)"; return 0; }
    $mysqli->query("INSERT INTO manufacturers (manufacturers_name, date_added) VALUES ('$q', NOW())");
    $id = (int) $mysqli->insert_id;
    $createdLog[] = "fabricante '$name' (id=$id)";
    return $id;
}

/** Categoría por nombre bajo $parentId; crea si falta. $status: 0 padre raíz, 1 hijas. */
function getOrCreateCategory($mysqli, $name, $parentId, $status, $dryRun, &$cache, &$createdLog) {
    $nm = trim(preg_replace('/\s+/u', ' ', (string) $name));
    if ($nm === '') $nm = 'VARIOS';
    $key = $parentId . '|' . mb_strtoupper($nm, 'UTF-8');
    if (isset($cache[$key])) return $cache[$key];
    $qName = $mysqli->real_escape_string($nm);
    $parentId = (int) $parentId;
    $r = $mysqli->query("SELECT c.categories_id FROM categories c
        INNER JOIN categories_description cd ON cd.categories_id=c.categories_id AND cd.language_id=" . LANG_ID_ES . "
        WHERE c.parent_id=$parentId AND UPPER(TRIM(cd.categories_name))=UPPER('$qName') LIMIT 1");
    if ($r && $row = $r->fetch_assoc()) { $cache[$key] = (int) $row['categories_id']; return $cache[$key]; }
    if ($dryRun) { $cache[$key] = 0; $createdLog[] = "categoría '$nm' bajo $parentId (dry-run)"; return 0; }
    $r = $mysqli->query("SELECT IFNULL(MAX(sort_order),0)+1 nso FROM categories WHERE parent_id=$parentId");
    $nso = (int) ($r->fetch_assoc()['nso'] ?? 1);
    $mysqli->query("INSERT INTO categories (parent_id, sort_order, date_added, last_modified, categories_status) VALUES ($parentId, $nso, NOW(), NOW(), " . (int)$status . ")");
    $newId = (int) $mysqli->insert_id;
    $mysqli->query("INSERT INTO categories_description (categories_id, language_id, categories_name) VALUES ($newId, " . LANG_ID_ES . ", '$qName')");
    $mysqli->query("INSERT INTO categories_description (categories_id, language_id, categories_name) VALUES ($newId, " . LANG_ID_EN . ", '$qName')");
    $cache[$key] = $newId;
    $createdLog[] = "categoría '$nm' (id=$newId) bajo $parentId";
    return $newId;
}

function findOrCreateOptionValue($mysqli, $nameEs, $nameEn = null) {
    if ($nameEn === null) $nameEn = $nameEs;
    $nameEsSafe = $mysqli->real_escape_string($nameEs);
    $q = $mysqli->query("SELECT pov.products_options_values_id FROM products_options_values pov
        INNER JOIN products_options_values_to_products_options pov2po ON pov2po.products_options_values_id = pov.products_options_values_id
        WHERE pov2po.products_options_id = " . VARIANT_OPTION_ID . " AND pov.language_id = " . LANG_ID_ES . "
          AND pov.products_options_values_name = '$nameEsSafe' LIMIT 1");
    if ($q && $row = $q->fetch_assoc()) return (int) $row['products_options_values_id'];
    $nq = $mysqli->query("SELECT IFNULL(MAX(products_options_values_id),0)+1 nid FROM products_options_values");
    $newId = (int) ($nq->fetch_assoc()['nid'] ?? 1);
    $nameEnSafe = $mysqli->real_escape_string($nameEn);
    $mysqli->query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($newId, " . LANG_ID_ES . ", '$nameEsSafe', '')");
    $mysqli->query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($newId, " . LANG_ID_EN . ", '$nameEnSafe', '')");
    $mysqli->query("INSERT INTO products_options_values_to_products_options (products_options_id, products_options_values_id) VALUES (" . VARIANT_OPTION_ID . ", $newId)");
    return $newId;
}

/** Recorta el nombre base para que el sufijo de desambiguación sobreviva el límite de 64
 *  (sql_mode no estricto trunca en silencio el final, llevándose el sufijo). */
function fsFitName($base, $suffix, $max = 64) {
    if (mb_strlen($base . $suffix, 'UTF-8') <= $max) return $base . $suffix;
    return mb_substr($base, 0, $max - mb_strlen($suffix, 'UTF-8'), 'UTF-8') . $suffix;
}

function loadJson($path) {
    if (!file_exists($path)) return null;
    $j = json_decode((string) file_get_contents($path), true);
    return is_array($j) ? $j : null;
}

/** Agrupa los códigos del catálogo por parent_key. Devuelve [parent_key => [data + items[]]]. */
function buildParentGroups($catalog, $parents, $parentsEn = []) {
    $groups = [];
    foreach ($catalog as $code => $e) {
        $pk = (string) $e['parent_key'];
        if (!isset($parents[$pk])) continue;
        if (!isset($groups[$pk])) {
            $p = $parents[$pk];
            $en = $parentsEn[$pk] ?? [];
            $groups[$pk] = [
                'name'    => $p['name'] ?? '',
                'desc'    => $p['desc'] ?? ($p['short_desc'] ?? ''),
                'name_en' => $en['name'] ?? '',                          // inglés real de la web (si existe)
                'desc_en' => $en['desc'] ?? ($en['short_desc'] ?? ''),
                'family' => html_entity_decode($p['family'] ?? 'VARIOS', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'subcat' => str_replace("\xef\xbf\xbd", '', html_entity_decode($p['subcat'] ?? 'VARIOS', ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                'images' => $p['images'] ?? [],
                'items'  => [],
            ];
        }
        $pvp = (float) $e['pvp'];
        $cost = round($pvp * COST_MULT, 4);
        $price = roundToNickel($pvp);
        $g1 = roundToNickel(calcG1Price($price, $cost));
        $groups[$pk]['items'][$code] = [
            'code' => $code, '_PVP' => $pvp, '_COST' => $cost, '_PRICE' => $price, '_G1' => $g1,
            'vlabel' => (string) ($e['variation_label'] ?? ''), 'var_image' => (string) ($e['var_image'] ?? ''),
        ];
    }
    return $groups;
}

$isAction = ($action === 'execute' || $action === 'dry_run');

if ($isAction) {
    @header('X-Accel-Buffering: no');
    @header('Content-Type: text/html; charset=utf-8');
    while (ob_get_level() > 0) @ob_end_flush();
    @ob_implicit_flush(true);
    if (session_status() === PHP_SESSION_ACTIVE) @session_write_close();
}
?>
<?php require THEME . 'html/header.php'; ?>
<table style="width:100%;"><tr><td>
<div style="padding:20px;">
<?php if ($isAction): ?>
    <h2>Importador Foresti &amp; Suardi — <?php echo $dryRun ? 'DRY-RUN (sin cambios)' : 'EJECUCIÓN REAL'; ?></h2>
    <p><a href="<?php echo tep_href_link('import-forestisuardi-altas.php'); ?>" class="xbutton small hv9">← Volver</a></p>
    <div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n";
@flush();

logMsg("Modo: " . ($dryRun ? "dry-run" : "EXECUTE")
    . " | familia=" . ($selectedFamily === 'all' ? 'TODAS' : $selectedFamily)
    . ($codesParam !== '' ? " | codes=$codesParam" : "")
    . ($skipTranslation ? " | sin LLM" : "")
    . ($max > 0 ? " | max=$max" : ""));

$catalog = loadJson(FS_CATALOG);
$parents = loadJson(FS_PARENTS);
$parentsEn = loadJson(FS_PARENTS_EN) ?: [];
$subcatEs = loadJson(FS_SUBCAT_ES) ?: [];
if (!$catalog || !$parents) { logMsg("ERROR: faltan fs_catalog.json / fs_parents.json en " . FS_DIR); goto end_action; }
logMsg("Catálogo: " . count($catalog) . " códigos importables | " . count($parents) . " padres | " . count($parentsEn) . " padres EN web | " . count($subcatEs) . " trad. categorías");

$groups = buildParentGroups($catalog, $parents, $parentsEn);
logMsg("Grupos (productos): " . count($groups));

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_error) { logMsg("ERROR DB: " . $mysqli->connect_error); goto end_action; }
$mysqli->set_charset('utf8');
if (!is_dir(IMG_ABS_DIR)) @mkdir(IMG_ABS_DIR, 0775, true);

$createdLog = [];
$mfgId = ensureManufacturer($mysqli, MFG_NAME, $dryRun, $createdLog);
$rootCatId = getOrCreateCategory($mysqli, PARENT_CATEGORY_NAME_ES, 0, 0, $dryRun, $catCache, $createdLog);

logMsg("Cargando IDs existentes en BD…");
$existing = buildExistingMap($mysqli, $mfgId);
logMsg("  → " . count($existing) . " referencias ya en BD");

// Filtro por familia / codes
$wantCodes = [];
if ($codesParam !== '') {
    foreach (preg_split('/[\s,;]+/', $codesParam) as $c) { $c = trim($c); if ($c !== '') $wantCodes[$c] = true; }
}
$catCache = $catCache ?? [];
$labelTransCache = [];
$mediaTransCache = [];

$nInserted = $nVarFamilies = $nSingles = $nVariants = $skipExist = $skipNoImg = $errors = $formatFail = 0;
$ts0 = microtime(true);

foreach ($groups as $pk => $g) {
    if ($max > 0 && $nInserted >= $max) { logMsg("Alcanzado max=$max, parando."); break; }

    // filtro de familia
    if ($selectedFamily !== 'all' && $codesParam === '') {
        if (mb_strtolower($g['family'], 'UTF-8') !== mb_strtolower($selectedFamily, 'UTF-8')) continue;
    }
    // filtro de codes (si el grupo contiene alguno de los pedidos, se importa entero)
    if ($codesParam !== '') {
        $hit = false;
        foreach ($g['items'] as $code => $it) if (isset($wantCodes[$code])) { $hit = true; break; }
        if (!$hit) continue;
    }

    // dedup: si CUALQUIER código del grupo ya existe en BD → saltar el grupo entero
    $already = false;
    foreach ($g['items'] as $code => $it) {
        if (isset($existing[strtolower($code)])) { $already = true; break; }
        // Código base: el catálogo histórico de Foresti (mfg 451) tiene a veces el código
        // SIN sufijo de variante (ej. "9320" ↔ feed "9320A.I", "9321" ↔ "9321B.I"). Si el
        // stem numérico del código del feed existe tal cual como modelo → es el mismo producto.
        if (preg_match('/^(\d{3,})/', $code, $sm) && isset($existing[$sm[1]])) { $already = true; break; }
    }
    if ($already) { $skipExist++; continue; }

    // imágenes (del padre); skip si no hay
    $imageUrls = array_values(array_filter((array) $g['images']));
    if (empty($imageUrls)) { $skipNoImg++; logMsg("SKIP parent=$pk '" . mb_substr($g['name'],0,40,'UTF-8') . "': sin imagen"); continue; }

    // orden de items por precio (padre = más barato)
    $items = $g['items'];
    uasort($items, fn($a, $b) => $a['_PRICE'] <=> $b['_PRICE']);
    $codesOrdered = array_keys($items);
    $cheapestCode = $codesOrdered[0];
    $cheap = $items[$cheapestCode];
    $isFamily = count($items) > 1;

    // nombre + descripción. EN = inglés REAL del catálogo web (solo maquetar); ES = LLM IT→ES.
    $nameItalian = $g['name'];
    $nameWebEn   = trim((string) $g['name_en']);                 // inglés de la web (si existe)
    // Extracción media+prose por idioma (preserva tabla de specs e imágenes embebidas)
    $extIt = fsExtractMedia((string) $g['desc']);
    $extEn = fsExtractMedia((string) $g['desc_en']);
    $descIt    = fsCleanHtmlToText($extIt['prose']);
    $descWebEn = fsCleanHtmlToText($extEn['prose']);
    // Descarga única (deduplicada) de imágenes auxiliares de AMBOS idiomas
    $allAuxUrls = array_values(array_unique(array_merge($extIt['imgUrls'], $extEn['imgUrls'])));
    $auxMap = [];
    foreach ($allAuxUrls as $u) $auxMap[$u] = fsDownloadAuxImage($u);
    $mediaEs = fsRewriteMedia($extIt['media'] !== '' ? $extIt['media'] : $extEn['media'], $auxMap);
    $mediaEn = fsRewriteMedia($extEn['media'] !== '' ? $extEn['media'] : $extIt['media'], $auxMap);
    if ($skipTranslation || $dryRun) {
        $nameEs = fsApplySuffix($nameItalian);
        $nameEn = fsApplySuffix($nameWebEn !== '' ? $nameWebEn : $nameItalian);
        $descEs = $descIt !== '' ? '<p>' . htmlspecialchars($descIt) . '</p>' : '';
        $descEn = $descWebEn !== '' ? '<p>' . htmlspecialchars($descWebEn) . '</p>'
                  : ($descIt !== '' ? '<p>' . htmlspecialchars($descIt) . '</p>' : '');
    } else {
        // Nombre ES: LLM IT→ES.  Nombre EN: el de la web si existe, si no LLM IT→EN.
        $trEs = fsLlmLine(LLM_NAME_PROMPT_ES, $nameItalian);
        $nameEs = fsApplySuffix($trEs !== '' ? $trEs : $nameItalian);
        if ($nameWebEn !== '') $nameEn = fsApplySuffix($nameWebEn);
        else { $trEn = fsLlmLine(LLM_NAME_PROMPT_EN, $nameItalian); $nameEn = fsApplySuffix($trEn !== '' ? $trEn : $nameItalian); }

        // Descripción ES: traducir IT→ES + maquetar.
        $descEs = '';
        if ($descIt !== '') {
            $inLen = mb_strlen($descIt, 'UTF-8');
            $esText = llmCall(LLM_TRANSLATE_ES, $descIt, 2000);
            if (trim($esText) === '') $esText = $descIt;
            $fEs = llmCall(LLM_FORMAT_PROMPT_ES, $esText, 2500);
            $descEs = fsFormatLooksValid($fEs, $inLen) ? $fEs : ('<p>' . htmlspecialchars($esText) . '</p>');
            if (!fsFormatLooksValid($fEs, $inLen)) $formatFail++;
        }
        // Descripción EN: la de la web (solo maquetar, SIN traducir). Fallback LLM IT→EN.
        $descEn = '';
        if ($descWebEn !== '') {
            $inLen = mb_strlen($descWebEn, 'UTF-8');
            $fEn = llmCall(LLM_FORMAT_PROMPT_EN, $descWebEn, 2500);
            $descEn = fsFormatLooksValid($fEn, $inLen) ? $fEn : ('<p>' . htmlspecialchars($descWebEn) . '</p>');
            if (!fsFormatLooksValid($fEn, $inLen)) $formatFail++;
        } elseif ($descIt !== '') {
            $inLen = mb_strlen($descIt, 'UTF-8');
            $enText = llmCall(LLM_TRANSLATE_EN, $descIt, 2000);
            if (trim($enText) === '') $enText = $descIt;
            $fEn = llmCall(LLM_FORMAT_PROMPT_EN, $enText, 2500);
            $descEn = fsFormatLooksValid($fEn, $inLen) ? $fEn : ('<p>' . htmlspecialchars($enText) . '</p>');
            if (!fsFormatLooksValid($fEn, $inLen)) $formatFail++;
        }
    }
    $descEs = mbReformatDescription($descEs);
    $descEn = mbReformatDescription($descEn);

    // Apéndices (DESPUÉS del LLM, como Bluewave, para que no los toque). Orden FIJO:
    //  1) tabla de especificaciones de variantes (Osculati-style)
    //  2) bloque de medios = table-data-sheet con dibujos técnicos + iconos de certificación
    //     (imágenes ya descargadas localmente y src reescrito) — SIEMPRE debajo de la tabla
    // Tabla de especificaciones SIEMPRE (sueltos incluidos: Foresti publica la tabla
    // de cotas SKU|Acabado|A|B|C|D también para productos de una sola referencia).
    $tblEs = fsBuildVariantTable($items, 'es');
    $tblEn = fsBuildVariantTable($items, 'en');
    if ($tblEs !== '') $descEs = trim($descEs) . $tblEs;
    if ($tblEn !== '') $descEn = trim($descEn) . $tblEn;
    if ($mediaEs !== '') {
        $needTranslate = ($extIt['media'] !== '');   // mediaEs vino del italiano → traducir texto cert
        if ($needTranslate && !$skipTranslation && !$dryRun) {
            $mediaEs = fsTranslateMediaTextEs($mediaEs, $mediaTransCache);
        }
        $descEs = trim($descEs) . "\n<p>&nbsp;</p>\n" . $mediaEs;
    }
    if ($mediaEn !== '') $descEn = trim($descEn) . "\n<p>&nbsp;</p>\n" . $mediaEn;

    // categorías: raíz > familia > subcat (traducidas)
    $familyEs = $subcatEs[$g['family']] ?? $g['family'];
    $subEs    = $subcatEs[$g['subcat']] ?? $g['subcat'];
    $famCatId = getOrCreateCategory($mysqli, $familyEs, $rootCatId ?: 0, 1, $dryRun, $catCache, $createdLog);
    $subCatId = getOrCreateCategory($mysqli, $subEs, $famCatId ?: ($rootCatId ?: 0), 1, $dryRun, $catCache, $createdLog);
    $targetCat = $subCatId ?: ($famCatId ?: $rootCatId);

    if ($dryRun) {
        $nInserted++;
        if ($nInserted <= 15) {
            logMsg(sprintf("  WOULD INSERT parent=%s '%s' fam='%s'>'%s' %s price=%.2f cost=%.2f g1=%.2f imgs=%d",
                $pk, mb_substr($nameEs,0,40,'UTF-8'), $familyEs, $subEs,
                $isFamily ? (count($items).' variantes') : 'suelto',
                $cheap['_PRICE'], $cheap['_COST'], $cheap['_G1'], count($imageUrls)));
        }
        continue;
    }

    $tmpFiles = downloadImagesToTmp($imageUrls, MAX_SUBIMAGES + 1);
    if (empty($tmpFiles)) { $skipNoImg++; logMsg("SKIP parent=$pk: imágenes no descargables"); continue; }

    $mysqli->begin_transaction();
    try {
        $qmodel = $mysqli->real_escape_string($cheapestCode);
        $price  = number_format($cheap['_PRICE'], 4, '.', '');
        $cost   = number_format($cheap['_COST'], 4, '.', '');
        $weight = number_format(DEFAULT_WEIGHT, 3, '.', '');
        // products_quantity = STOCK_SENTINEL (-800 "bajo pedido") — Foresti se vende bajo pedido
        $sql = "INSERT INTO products (products_quantity, check_stock, products_model, products_image, products_price, products_cost, products_date_added, products_weight, products_status, products_tax_class_id, manufacturers_id, product_ean, reference_prov, products_import_origin)
            VALUES (" . STOCK_SENTINEL . ", 0, \"$qmodel\", \"\", $price, $cost, NOW(), $weight, 2, " . TAX_CLASS_IVA21 . ", " . (int)$mfgId . ", \"\", \"$qmodel\", \"" . ORIGIN_FLAG . "\")";
        if (!$mysqli->query($sql)) throw new Exception("products: " . $mysqli->error);
        $pid = (int) $mysqli->insert_id;

        $qNameEs = $mysqli->real_escape_string($nameEs);
        $qDescEs = $mysqli->real_escape_string($descEs);
        $qNameEn = $mysqli->real_escape_string($nameEn);
        $qDescEn = $mysqli->real_escape_string($descEn);
        if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_ES . ", \"$qNameEs\", \"$qDescEs\", 0)")) throw new Exception("desc ES: " . $mysqli->error);
        if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_EN . ", \"$qNameEn\", \"$qDescEn\", 0)")) throw new Exception("desc EN: " . $mysqli->error);

        if (!$mysqli->query("INSERT INTO products_to_categories (products_id, categories_id) VALUES ($pid, " . (int)$targetCat . ")")) throw new Exception("p2c: " . $mysqli->error);
        if (!$mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . G1_GROUP_ID . ", $pid, " . number_format($cheap['_G1'], 4, '.', '') . ", 1, 1)")) throw new Exception("g1: " . $mysqli->error);

        // imágenes
        $slug = fsSlugify(fsStripSuffix($nameEs));
        $imgFinal = [];
        foreach ($tmpFiles as $i => $tmpAbs) {
            $suffix = ($i === 0) ? '' : ('-' . ($i + 1));
            $finalName = $slug . '-' . $pid . $suffix . '.jpg';
            if (@rename($tmpAbs, IMG_ABS_DIR . $finalName)) $imgFinal[] = $finalName;
            else @unlink($tmpAbs);
        }
        if (empty($imgFinal)) throw new Exception("rename imágenes falló");
        $mainImg = array_shift($imgFinal);
        $mysqli->query("UPDATE products SET products_image=\"" . $mysqli->real_escape_string($mainImg) . "\" WHERE products_id=$pid");
        if (!empty($imgFinal)) {
            $subJson = json_encode($imgFinal, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $mysqli->query("UPDATE products SET products_subimages='" . $mysqli->real_escape_string($subJson) . "' WHERE products_id=$pid");
        }

        if ($isFamily) {
            // etiquetas de variante: parte de atributos (italiano) + código si hace falta desambiguar
            $rawLabels = fsVariantLabels($items);
            $labels = [];
            foreach ($rawLabels as $code => $info) {
                $attrIt = $info['attr'];
                $attrEs = $attrIt;
                // traducir por segmento ' / ', SOLO los que son palabras sin dígitos
                // (así "2,5 Watt", "3200 °K", "12/24V" quedan intactos; "Bianco"→"Blanco")
                if (!$skipTranslation && $attrIt !== '') {
                    $outSegs = [];
                    foreach (explode(' / ', $attrIt) as $seg) {
                        $seg = trim($seg);
                        if ($seg === '') continue;
                        if (preg_match('/\d/', $seg) || !preg_match('/\p{L}{3,}/u', $seg) || strlen($seg) <= 3) {
                            $outSegs[] = $seg;   // número/unidad/acrónimo → intacto
                        } else {
                            if (!isset($labelTransCache[$seg])) {
                                $t = fsLlmLine(LLM_LABEL_PROMPT_ES, $seg);
                                $labelTransCache[$seg] = ($t !== '') ? $t : $seg;
                            }
                            $outSegs[] = $labelTransCache[$seg];
                        }
                    }
                    $attrEs = implode(' / ', $outSegs);
                }
                $buildLab = function ($attr) use ($info, $code) {
                    if ($attr === '') return (string) $code;
                    return $info['needs_code'] ? ($attr . ' · ' . $code) : $attr;
                };
                $labels[$code] = [
                    'it' => mb_substr($buildLab($attrIt), 0, 64, 'UTF-8'),
                    'es' => mb_substr($buildLab($attrEs), 0, 64, 'UTF-8'),
                ];
            }
            $ovsUsados = [];   // guardia anti-colisión pa-dupe por producto (ver francobordo_pa_duplicates)
            foreach ($items as $code => $it) {
                $delta  = round($it['_PRICE'] - $cheap['_PRICE'], 4);
                $prefix = $delta < 0 ? '-' : '+';
                $valueId = findOrCreateOptionValue($mysqli, $labels[$code]['es'], $labels[$code]['it']);
                if (isset($ovsUsados[$valueId])) {
                    // este OV ya se usó para otra variante de ESTE producto -> forzar OV desambiguado por código
                    $valueId = findOrCreateOptionValue($mysqli, fsFitName($labels[$code]['es'], ' · ' . $code), fsFitName($labels[$code]['it'], ' · ' . $code));
                }
                $ovsUsados[$valueId] = true;
                $qprovv = $mysqli->real_escape_string($code);
                if (!$mysqli->query("INSERT INTO products_attributes SET
                    products_id=$pid, options_id=" . VARIANT_OPTION_ID . ", options_values_id=$valueId,
                    options_values_price=" . number_format(abs($delta), 4, '.', '') . ", price_prefix='$prefix',
                    reference='$qprovv', reference_prov='$qprovv', products_attributes_ean='',
                    options_values_weight=0.000, weight_prefix='+'"))
                    throw new Exception("attr: " . $mysqli->error);
                $paId = (int) $mysqli->insert_id;
                // EAN interno por variante (único por products_attributes_id)
                $vEan = generateInternalEan13($paId, EAN_INTERNAL_PREFIX);
                if ($vEan !== '') $mysqli->query("UPDATE products_attributes SET products_attributes_ean='" . $mysqli->real_escape_string($vEan) . "' WHERE products_attributes_id=$paId");
                // Stock por variante = -800 "bajo pedido" (el cron sync_products_stock solo
                // siembra filas que faltan con qty=0, no pisa estas; REVERSE no las borra).
                $mysqli->query("INSERT INTO products_stock (products_id, products_stock_attributes, products_stock_quantity, products_stock_cost) VALUES ($pid, '" . VARIANT_OPTION_ID . "-$valueId', " . STOCK_SENTINEL . ", 0.0000)");
                $g1Delta = round($it['_G1'] - $cheap['_G1'], 4);
                $g1Prefix = $g1Delta < 0 ? '-' : '+';
                if (!$mysqli->query("INSERT INTO products_attributes_groups (products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id, options_values_weight, weight_prefix) VALUES ($paId, " . G1_GROUP_ID . ", " . number_format(abs($g1Delta), 4, '.', '') . ", '$g1Prefix', $pid, 0, '+')"))
                    throw new Exception("attr_groups: " . $mysqli->error);
                $nVariants++;
            }
        }

        $mysqli->commit();

        // EAN interno SOLO para productos sueltos (las familias llevan EAN por variante; sin máster — ver convención)
        if (!$isFamily) {
            $genEan = generateInternalEan13($pid, EAN_INTERNAL_PREFIX);
            if ($genEan !== '') $mysqli->query("UPDATE products SET product_ean='" . $mysqli->real_escape_string($genEan) . "' WHERE products_id=$pid AND (product_ean IS NULL OR product_ean='')");
        }

        $nInserted++;
        if ($isFamily) $nVarFamilies++; else $nSingles++;
        logMsg(sprintf("OK pid=%d %s cheap=%s [%s] fam='%s'>'%s' price=%.2f cost=%.2f g1=%.2f imgs=%d name='%s'",
            $pid, $isFamily ? 'FAMILIA' : 'SUELTO', $cheapestCode,
            $isFamily ? (count($items).'v') : '1', $familyEs, $subEs,
            $cheap['_PRICE'], $cheap['_COST'], $cheap['_G1'], 1 + count($imgFinal),
            mb_substr($nameEs, 0, 45, 'UTF-8')));
    } catch (Exception $e) {
        $mysqli->rollback();
        $errors++;
        foreach ($tmpFiles as $t) if (file_exists($t)) @unlink($t);
        logMsg("ERROR parent=$pk: " . $e->getMessage());
    }
}

$elapsed = microtime(true) - $ts0;
logMsg("==================== RESUMEN ====================");
logMsg(sprintf("Insertados: %d (%d familias + %d sueltos, %d variantes) en %.1fs", $nInserted, $nVarFamilies, $nSingles, $nVariants, $elapsed));
logMsg("Skip existentes: $skipExist | skip sin imagen: $skipNoImg | maquetados fallidos: $formatFail | errores: $errors");
if (!empty($createdLog)) {
    logMsg(($dryRun ? "Se crearían" : "Creadas") . " " . count($createdLog) . " entidades:");
    foreach ($createdLog as $c) logMsg("  · $c");
}

end_action:
?>
    </div>
    <p style="margin-top:15px;"><a href="<?php echo tep_href_link('import-forestisuardi-altas.php'); ?>" class="xbutton small hv9">← Volver</a></p>
<?php else: ?>
    <h2>Importador Foresti &amp; Suardi (altas)</h2>
    <?php
        $catalog = loadJson(FS_CATALOG);
        $parents = loadJson(FS_PARENTS);
        if (!$catalog || !$parents) {
            echo '<p style="color:red;">Faltan <code>fs_catalog.json</code> / <code>fs_parents.json</code> en ' . FS_DIR . '</p>';
        } else {
            $groups = buildParentGroups($catalog, $parents);
            $famCount = [];
            foreach ($groups as $g) { $f = $g['family']; $famCount[$f] = ($famCount[$f] ?? 0) + 1; }
            arsort($famCount);
            $subcatEs = loadJson(FS_SUBCAT_ES) ?: [];
    ?>
    <p style="color:#666;font-size:13px;">
        Fuente precios: <code>fs_prices.csv</code> (PDF Listino, PVP IVA excl.). Enriquecimiento:
        catálogo WooCommerce <code>catalogue.forestiesuardi.it</code> (nombre, descripción IT, imágenes, categorías)
        enlazado por SKU = código de artículo. <strong><?php echo count($catalog); ?></strong> códigos importables
        agrupados en <strong><?php echo count($groups); ?></strong> productos.
    </p>
    <p style="background:#fffbe6;border:1px solid #ffd700;padding:10px;border-radius:4px;font-size:13px;">
        <strong>Reglas siempre aplicadas</strong>:<br>
        • Fabricante: <code><?php echo MFG_NAME; ?></code> (se crea si no existe).<br>
        • <code>products_cost</code> = PVP × <?php echo COST_MULT; ?> (descuento 50%) &nbsp;|&nbsp;
          <code>products_price</code> = roundToNickel(PVP) &nbsp;|&nbsp;
          <code>G1</code> = roundToNickel(tiers de margen + piso cost×<?php echo G1_FLOOR_FACTOR; ?>) — netos, PVP con IVA en múltiplos de 0,05€.<br>
        • Variantes agrupadas por producto padre WooCommerce (options_id=3 "Modelo"); padre = variante más barata.<br>
        • Categorías: <strong><?php echo PARENT_CATEGORY_NAME_ES; ?></strong> &gt; Familia &gt; Subcategoría (traducidas a ES).<br>
        • Descripción y nombre traducidos IT→ES e IT→EN vía LLM (salvo "sin LLM").<br>
        • <strong>Sin imagen → no se importa.</strong> Imágenes del CDN del catálogo (1 principal + hasta <?php echo MAX_SUBIMAGES; ?> sub).<br>
        • Sin EAN del proveedor: EAN interno prefijo <?php echo EAN_INTERNAL_PREFIX; ?> por variante (familias) o por producto (sueltos); nunca <code>299…</code>.<br>
        • <code>products_status=2</code> (revisión), <code>check_stock=0</code>, <code>stock=-800</code> "bajo pedido" (master + cada variante en products_stock).<br>
        • Skip si el código ya existe en BD (SKU por fabricante; EAN global).
    </p>
    <form method="get" style="background:#f5f5f5;padding:15px;border-radius:5px;">
        <p><strong>Familia a importar</strong>:
            <select name="family" style="min-width:320px;">
                <option value="all">— Todas las familias —</option>
                <?php foreach ($famCount as $fam => $n) {
                    $famEs = $subcatEs[$fam] ?? $fam;
                    $sel = (mb_strtolower($selectedFamily,'UTF-8') === mb_strtolower($fam,'UTF-8')) ? ' selected' : '';
                    echo '<option value="' . htmlspecialchars($fam) . '"' . $sel . '>' . htmlspecialchars($famEs) . ' (' . $n . ' productos)</option>';
                } ?>
            </select>
        </p>
        <p>O <strong>códigos concretos</strong> (coma/espacio; importa la familia completa de cada uno):<br>
            <textarea name="codes" rows="2" style="width:100%;" placeholder="5110.C, 8510.C, 5182.C.4000.9M"><?php echo htmlspecialchars($codesParam); ?></textarea>
        </p>
        <p><label><input type="checkbox" name="dry_run" value="1" checked> Dry-run (sin cambios)</label></p>
        <p><label><input type="checkbox" name="skip_translation" value="1"> Saltar LLM (nombre/descripción quedan en italiano, mucho más rápido)</label></p>
        <p>Inserts máximos por ejecución (0 = sin límite): <input type="number" name="max" value="5" min="0" style="width:80px;"></p>
        <button type="submit" name="action" value="dry_run" class="xbutton small hv9">Dry-run</button>
        <button type="submit" name="action" value="execute" class="xbutton small hv9 verde" onclick="return confirm('¿Ejecutar de verdad? Insertará productos en la BD.');">Ejecutar</button>
    </form>
    <p style="margin-top:20px;color:#888;font-size:12px;">
        <strong>Detalles</strong>: el LLM (qwen) traduce y maqueta; ~4 llamadas por producto (lento — usa "sin LLM" para una pasada rápida y retraduce luego).
        Los datos JSON se regeneran offline cuando llega un nuevo Listino (parser PDF + cache builder).
    </p>
    <?php } ?>
<?php endif; ?>
</div>
</td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
