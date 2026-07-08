<?php
require 'includes/application_top.php';

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);
error_reporting(E_ALL & ~E_DEPRECATED);   // PHP 8.5: silencia 'deprecated' (p.ej. imagedestroy) en el log de streaming

/* ============================================================================
 *  Importador de altas TECNOSEAL (anodos de sacrificio)
 *  Fuentes:
 *    - xlsx  /import/Tecnoseal/TARIFA PRECIOS DR.xlsx  (CODIGO|NOMBRE(ES)|DR=coste|PVP sin IVA)
 *    - ts_map.json (cache web tecnoseal-online-catalogue.it: en_name, foto, dibujo, kg, dims)
 *  Variantes: AGRUPADAS POR MATERIAL (zinc/aluminio/magnesio) por stem de codigo.
 *  Precio: cost=DR, price=roundToNickel(PVP) ; G1 tiers+piso. Descripcion PLANTILLA (sin LLM).
 * ========================================================================== */

const TS_DIR              = '/home/francobordo/public_html/import/Tecnoseal/';
const TS_XLSX             = TS_DIR . 'TARIFA PRECIOS DR.xlsx';
const TS_MAP_PATH         = TS_DIR . 'ts_map.json';
const TS_CACHE_IMG_DIR    = TS_DIR . 'cache/img/';
const TS_CACHE_PDF_DIR    = TS_DIR . 'cache/pdf/';
define('IMG_ABS_DIR',       dirname(dirname(__FILE__)) . '/images/productos/');
// Ficha PDF → campo NATIVO products_pdfupload + carpeta manuals/ (la tienda la sirve y la pinta como "Descargar Ficha PDF")
define('TS_MANUALS_DIR',    defined('DIR_FS_CATALOG_MANUALS') ? DIR_FS_CATALOG_MANUALS : (dirname(dirname(__FILE__)) . '/manuals/'));

const NEW_CATEGORY_NAME   = 'Tecnoseal Nuevos';
const TS_MANUFACTURER_ID  = 458;          // Tecnoseal (ya existe)
const TAX_CLASS_IVA21     = 1;
const LANG_ID_ES          = 3;
const LANG_ID_EN          = 1;
const G1_GROUP_ID         = 1;
const G1_FLOOR_FACTOR     = 1.10;
const PRODUCT_NAME_MAX    = 80;
const IMG_MIN_BYTES       = 500;          // PNG simples de anodos pueden ser pequenos
const ORIGIN_FLAG         = 'tecnoseal';
const EAN_INTERNAL_PREFIX = 28;           // rango in-store; cuerpo = products_id (sin colision)
const MATERIAL_OPTION_ID  = 417;          // opcion "Material" (ya existe)
const STOCK_SENTINEL      = -800;         // "bajo pedido"
const DEFAULT_WEIGHT      = 0.5;          // kg fallback si la web no trae peso
const VAT_RATE            = 0.21;
// LLM opcional (solo redacta el párrafo intro; specs/dibujo/PDF siguen deterministas)
const LLM_URL             = 'http://217.127.199.171:28001/v1/chat/completions';
const LLM_MODEL           = 'qwen36-sakamaki-nvfp4';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$max    = isset($_POST['max']) ? (int) $_POST['max'] : (isset($_GET['max']) ? (int) $_GET['max'] : 0);
$dryRun = isset($_POST['dry_run']) || isset($_GET['dry_run']) || $action === 'dry_run';
$skipImages    = isset($_POST['skip_images'])    || isset($_GET['skip_images']);
$allowNoImage  = isset($_POST['allow_no_image']) || isset($_GET['allow_no_image']);
$useLlm        = isset($_POST['llm']) || isset($_GET['llm']);
$onlyCodesRaw  = trim((string) ($_POST['codes'] ?? $_GET['codes'] ?? ''));
$selectedFamily = trim((string) ($_POST['family'] ?? $_GET['family'] ?? 'all'));
$onlyCodes = [];
if ($onlyCodesRaw !== '') {
    foreach (preg_split('/[\s,;]+/', $onlyCodesRaw) as $c) { $c = trim($c); if ($c !== '') $onlyCodes[strtolower($c)] = true; }
}

// ───────────────────────── helpers básicos ─────────────────────────
function logMsg($msg) {
    echo '<pre style="margin:0;padding:2px 8px;border-bottom:1px solid #eee;white-space:pre-wrap;overflow-wrap:break-word;word-break:break-word;font-family:monospace;font-size:12px;">' . htmlspecialchars('[' . date('H:i:s') . '] ' . $msg) . "</pre>";
    @flush();
}
function roundToNickel($net) {
    $withIva = ((float) $net) * (1 + VAT_RATE);
    $r = round($withIva * 20) / 20;
    return round($r / (1 + VAT_RATE), 4);
}
function calcG1Price($price, $cost) {
    $price = (float) $price; $cost = (float) $cost;
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
function ean13Checksum($p12){ if(strlen($p12)!==12||!ctype_digit($p12))return -1; $s=0; for($i=0;$i<12;$i++){$d=(int)$p12[$i]; $s+=($i%2===0)?$d:$d*3;} return (10-($s%10))%10; }
function isValidEan13($e){ $e=trim((string)$e); if(strlen($e)!==13||!ctype_digit($e))return false; return ean13Checksum(substr($e,0,12))===(int)$e[12]; }
function generateInternalEan13($pid,$prefix){ $pp=(int)$prefix; if($pp<20||$pp>29)return ''; if($pid<=0||$pid>9999999999)return ''; $payload=str_pad((string)$pp,2,'0',STR_PAD_LEFT).str_pad((string)$pid,10,'0',STR_PAD_LEFT); $c=ean13Checksum($payload); return $c<0?'':($payload.$c); }
function tsSlugify($text,$maxLen=50){ $t=trim((string)$text);
    // pre-mapa de acentos/símbolos (iconv TRANSLIT a veces los DROPEA en vez de transliterar)
    $t=strtr($t,['Á'=>'A','À'=>'A','Ä'=>'A','É'=>'E','È'=>'E','Ë'=>'E','Í'=>'I','Ï'=>'I','Ó'=>'O','Ò'=>'O','Ö'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N','Ç'=>'C','Ø'=>'O','á'=>'a','à'=>'a','ä'=>'a','é'=>'e','è'=>'e','ë'=>'e','í'=>'i','ï'=>'i','ó'=>'o','ò'=>'o','ö'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n','ç'=>'c','ø'=>'o','º'=>'','ª'=>'']);
    if(function_exists('iconv')){ $c=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$t); if($c!==false&&$c!=='')$t=$c; }
    $t=strtolower($t); $t=preg_replace('/[^a-z0-9]+/','-',$t); $t=trim($t,'-'); if(strlen($t)>$maxLen)$t=substr($t,0,$maxLen); return $t===''?'anodo':trim($t,'-'); }
function tsNum($v){ $v=trim((string)$v); if($v==='')return null; $v=str_replace(',','.',$v); return is_numeric($v)?(float)$v:null; }

// ───────── material / grouping (idéntico al validado) ─────────
function tsMaterial(string $code, string $name): string {
    $u = strtoupper($name);
    if (preg_match('/\bMAGNESIO\b|\bMAGNESIUM\b/u',$u)) return 'magnesio';
    if (preg_match('/\bAL{1,2}U?MINI{0,2}[O0]\b|\bALUMINIUM\b|\bALLUMINIO\b|\bALU\b/u',$u)) return 'aluminio';
    if (preg_match('/\bZINC\b|\bZINCO\b|\bZN\b/u',$u)) return 'zinc';
    if (preg_match('/MG$/i',$code)) return 'magnesio';
    if (preg_match('/AL$/i',$code))  return 'aluminio';
    return '';
}
function tsStem(string $code, string $material): string {
    $c = $code;
    if ($material==='aluminio' && preg_match('/AL$/i',$c)) $c = substr($c,0,-2);
    elseif ($material==='magnesio' && preg_match('/MG$/i',$c)) $c = substr($c,0,-2);
    return strtoupper($c);   // sin rtrim: idéntico al algoritmo validado (0 colisiones mismo-material)
}
/** Material efectivo para etiqueta de variante: '' (base) -> zinc. */
function tsMatEff(string $m): string { return $m === '' ? 'zinc' : $m; }
function tsMatLabel(string $m, string $lang): string {
    $map = [
        'zinc'      => ['es'=>'Zinc',     'en'=>'Zinc'],
        'aluminio'  => ['es'=>'Aluminio', 'en'=>'Aluminium'],
        'magnesio'  => ['es'=>'Magnesio', 'en'=>'Magnesium'],
    ];
    $m = tsMatEff($m);
    return $map[$m][$lang] ?? ucfirst($m);
}

// ───────── tipo de ánodo (subcategoría) desde el NOMBRE ES ─────────
function tsAnodeType(string $name): array {
    $u = ' ' . strtoupper($name) . ' ';
    // orden de prioridad: lo más específico primero
    $rules = [
        ['/\bKIT/',                         'Kits de ánodos',            'Anode kits'],
        ['/^\s(TAPON|TAPÓN|TAPONANODO)/',   'Tapones',                   'Plugs'],  // SOLO si el nombre empieza por TAPON (tapón suelto); "ANODO ... C/TAPON" no es un tapón
        ['/ANILLO\s+TOMA|TOMA\s+DE\s+MAR/', 'Anillos toma de mar',       'Seacock rings'],
        ['/\bANILLO/',                      'Anillos',                   'Rings'],
        ['/\bTIMON|TIMÓN/',                 'Ánodos de timón',           'Rudder anodes'],
        ['/\bHELICE|HÉLICE/',               'Ánodos de hélice',          'Propeller anodes'],
        ['/\bEJE\b/',                       'Ánodos de eje',             'Shaft anodes'],
        ['/\bBARRA/',                       'Ánodos de barra',           'Bar anodes'],
        ['/COLLARIN|COLLARÍN|\bCOLLAR/',    'Ánodos de collar',          'Collar anodes'],
        ['/\bMOTOR|FUERABORDA|FUERA\s+BORDA/','Ánodos de motor',         'Engine anodes'],
        ['/\bDISCO/',                       'Ánodos de disco',           'Disc anodes'],
        ['/ARANDELA/',                      'Ánodos arandela',           'Washer anodes'],
        ['/\bCUBO/',                        'Ánodos de cubo',            'Hub anodes'],
        ['/\bDADO|TUERCA/',                 'Ánodos dado/tuerca',        'Nut anodes'],
        ['/CILINDRO/',                      'Ánodos de cilindro',        'Cylinder anodes'],
        ['/TRANSOM|\bPOPA/',               'Ánodos de popa/transom',    'Stern/transom anodes'],
        ['/\bTRIM/',                        'Ánodos de trim',            'Trim anodes'],
        ['/\bPLACA/',                       'Ánodos de placa',           'Plate anodes'],
        ['/RECAMBIO/',                      'Recambios',                 'Spare parts'],
    ];
    foreach ($rules as $r) { if (preg_match($r[0], $u)) return ['es'=>$r[1], 'en'=>$r[2]]; }
    return ['es'=>'Otros ánodos', 'en'=>'Other anodes'];
}

// ───────── limpieza de nombre ES (de ALL-CAPS a legible, acentos náuticos) ─────────
function tsCleanNameEs(string $raw, string $material): string {
    $s = trim(preg_replace('/\s+/', ' ', $raw));
    // quita la palabra de material (cualquier grafía típica) — y un "in/di" italiano que la preceda — para el nombre del PADRE
    $matRe = 'ZINC|ZINCO|ALL?U?MINI{0,2}[O0]|ALLUMINIO|ALUMINIUM|ALU|AUMINIO|ALUMNIO|MAGNESIO|MAGNESIUM';
    $s = preg_replace('/\b(?:IN|DI)\s+(?:' . $matRe . ')\b/iu', '', $s);
    $s = preg_replace('/\b(?:' . $matRe . ')\b/iu', '', $s);
    $s = trim(preg_replace('/\s{2,}/', ' ', $s));
    // Title Case suave
    $s = mb_convert_case(mb_strtolower($s, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    // mapa de acentos/términos náuticos frecuentes (post Title-Case)
    $fix = [
        'Anodo'=>'Ánodo','Anodi'=>'Ánodo','Timon'=>'Timón','Helice'=>'Hélice','Collarin'=>'Collarín',
        'Tapon'=>'Tapón','Taponanodo'=>'Tapón Ánodo','Laton'=>'Latón','Diametro'=>'Diámetro',
        'Hexagonal'=>'Hexagonal','Conico'=>'Cónico','Conica'=>'Cónica','Roscado'=>'Roscado',
        'Pequena'=>'Pequeña','Pequeño'=>'Pequeño','Volkswagen'=>'Volkswagen','Plataforma'=>'Plataforma',
        'Recambio'=>'Recambio','Anillo'=>'Anillo','Arandela'=>'Arandela','Aliscafo'=>'Aliscafo',
        'Tappo'=>'tapón','Ottone'=>'latón','Testa'=>'cabeza','Insert'=>'inserto','Inserto'=>'inserto',
        'Completo'=>'completo','Completa'=>'completa','Pesado'=>'pesado','Doble'=>'doble',
        // italianismos residuales detectados en el xlsx (≈13 nombres)
        'Foro'=>'Agujero','Zincato'=>'Galvanizado','Destra'=>'Derecha','Sinistra'=>'Izquierda',
        'Vite'=>'Tornillo','Filetto'=>'Rosca','Filettato'=>'Roscado','Grezzo'=>'Bruto','Saldare'=>'Soldar',
    ];
    $s = preg_replace_callback('/\p{L}[\p{L}]*/u', function($m) use ($fix){ return $fix[$m[0]] ?? $m[0]; }, $s);
    $s = trim(preg_replace('/\s{2,}/', ' ', $s));
    return $s === '' ? 'Ánodo Tecnoseal' : $s;
}
function tsCleanNameEn(string $en): string {
    $s = trim(preg_replace('/\s+/', ' ', $en));
    // quita material si aparece en EN (raro)
    $s = preg_replace('/\b(zinc|aluminium|aluminum|magnesium)\b/iu', '', $s);
    return trim(preg_replace('/\s{2,}/', ' ', $s));
}
function tsBrandPrefix(string $name, string $brand = 'Tecnoseal'): string {
    return preg_match('/^\s*tecnoseal/i', $name) ? $name : ($brand . ' ' . $name);
}

// ───────── descripción templada (ES + EN) ─────────
/** Tabla de especificaciones estilo Tecnoseal: Código | kg | lbs | (mm/inch) | dim1 | dim2 …
 *  Una fila por código del grupo; mm e inch apilados en cada celda (igual que el catálogo web). */
function tsSpecTable(array $items, array $tsMap, string $lang): string {
    // columnas de dimensión = unión (en orden de aparición) de las claves de dims del grupo
    $dimCols = [];
    foreach ($items as $code => $_) {
        $d = $tsMap[$code]['dims'] ?? [];
        if (is_array($d)) foreach ($d as $k => $v) { if (!in_array($k, $dimCols, true)) $dimCols[] = $k; }
    }
    $anyKg = false;
    foreach ($items as $code => $_) { if (($tsMap[$code]['kg'] ?? null) !== null) { $anyKg = true; break; } }
    if (empty($dimCols) && !$anyKg) return '';

    // material por código (nombre/sufijo → chapa web); se muestra una columna "Material" si se conoce alguno
    $mats = []; $anyMat = false;
    foreach ($items as $code => $rr) {
        $cm = (($rr['_MAT'] ?? '') !== '') ? tsMatEff($rr['_MAT']) : (string)($tsMap[$code]['material'] ?? '');
        $mats[$code] = $cm; if ($cm !== '') $anyMat = true;
    }

    $th  = fn($t) => '<td style="background-color:#008cc6;text-align:center;padding:4px 12px;color:#fff;font-weight:bold;">' . htmlspecialchars((string)$t) . '</td>';
    $td  = fn($html) => '<td style="text-align:center;padding:4px 12px;">' . $html . '</td>';
    $stk = fn($a, $b) => '<div>' . htmlspecialchars((string)$a) . '</div><div style="color:#8a8a8a;font-size:90%;">' . htmlspecialchars((string)$b) . '</div>';
    $fmt = fn($n) => $n === null ? '-' : rtrim(rtrim(number_format((float)$n, 3, '.', ''), '0'), '.');
    $codLbl = $lang === 'es' ? 'Código' : 'Code';
    $unit   = $lang === 'es' ? 'pulg' : 'inch';

    $head = '<tr>' . $th($codLbl) . ($anyMat ? $th('Material') : '') . $th('kg') . $th('lbs') . $th('');
    foreach ($dimCols as $dc) $head .= $th($dc);
    $head .= '</tr>';

    $body = ''; $i = 0;
    foreach ($items as $code => $_) {
        $kg  = $tsMap[$code]['kg'] ?? null;
        $lbs = ($kg !== null) ? round((float)$kg * 2.20462, 2) : null;   // lbs computado de kg (consistente)
        $dims = $tsMap[$code]['dims'] ?? [];
        $bg = ($i++ % 2 === 0) ? ' style="background-color:#e2f2f9;"' : '';
        $matCell = $anyMat ? $td(htmlspecialchars($mats[$code] !== '' ? tsMatLabel($mats[$code], $lang) : '—')) : '';
        $row = '<tr' . $bg . '>' . $td(htmlspecialchars((string)$code)) . $matCell . $td($fmt($kg)) . $td($fmt($lbs)) . $td($stk('mm', $unit));
        foreach ($dimCols as $dc) {
            $v = $dims[$dc] ?? null;
            $row .= is_array($v) ? $td($stk($v['mm'] ?? '', $v['inch'] ?? '')) : $td('-');
        }
        $row .= '</tr>';
        $body .= $row;
    }
    return '<table style="border-collapse:collapse;border:1px solid #ced4d9;margin-top:6px;" cellspacing="0" cellpadding="0">' . $head . $body . '</table>';
}
function tsDrawingImg(string $file, string $alt): string {
    // Imágenes de la descripción: ancho máx 600px (preferencia usuario 2026-06-16)
    return '<p style="text-align:center;margin:10px 0;"><img src="/images/productos/' . htmlspecialchars($file, ENT_QUOTES)
        . '" alt="' . htmlspecialchars($alt, ENT_QUOTES) . '" style="max-width:600px;width:100%;height:auto;" /></p>';
}
/** Detecta marca/aplicación conocida en el nombre (para la frase intro). */
function tsApplication(string $name): string {
    $brands = ['VOLVO','VETUS','YAMAHA','YANMAR','MERCURY','MERCRUISER','SUZUKI','HONDA','TOHATSU','SELVA',
        'CATERPILLAR','CUMMINS','MAN','MTU','BMW','AIFO','ISOTTA','SLEIPNER','RADICE','GORI','MAX-PROP','MAX PROP',
        'FLEXOFOLD','BENETEAU','FAIRLINE','BAGLIETTO','ZF','TWIN DISC','QUICK','SIDE-POWER','SIDE POWER','VARIPROFILE'];
    $u = strtoupper($name);
    foreach ($brands as $b) { if (strpos($u, $b) !== false) return ucwords(strtolower($b)); }
    return '';
}
/** Llamada al LLM local (qwen, sin reasoning). Devuelve el texto o '' si falla. */
function llmCall($systemPrompt, $userText, $maxTokens = 700, $maxRetries = 2) {
    if (trim((string)$userText) === '') return '';
    $payload = json_encode([
        'model' => LLM_MODEL,
        'messages' => [['role'=>'system','content'=>$systemPrompt],['role'=>'user','content'=>$userText]],
        'temperature' => 0.4, 'max_tokens' => $maxTokens,
        'chat_template_kwargs' => ['enable_thinking' => false],
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    for ($i=0; $i<=$maxRetries; $i++) {
        $ch = curl_init(LLM_URL);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_POSTFIELDS=>$payload,
            CURLOPT_TIMEOUT=>60, CURLOPT_CONNECTTIMEOUT=>8]);
        $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($resp !== false && $code === 200) {
            $j = json_decode($resp, true);
            $c = $j['choices'][0]['message']['content'] ?? null;
            if (is_string($c) && trim($c) !== '') return trim($c);
        }
        usleep(400000);
    }
    return '';
}
/** Redacta el párrafo intro ES+EN con el LLM (1 llamada, JSON). Devuelve ['es'=>html,'en'=>html] o ['es'=>'','en'=>'']. */
function tsLlmIntros($nameEs, $nameEn, $typeEs, array $claimMaterials, $application) {
    $matEs = implode(', ', array_map(fn($m)=>tsMatLabel($m,'es'), $claimMaterials));
    $data = "Producto (ES): $nameEs\nTipo: $typeEs\nMaterial(es): " . ($matEs ?: 'no especificado')
          . ($application ? "\nCompatible con: $application" : '') . "\nNombre EN de referencia: $nameEn";
    $sys = 'Eres redactor de fichas de producto náuticas para una tienda online. A partir de los datos, escribe un párrafo comercial BREVE (2-3 frases) que describa este ánodo de sacrificio Tecnoseal: para qué sirve, material(es), aplicación/compatibilidad si se indica, y que protege de la corrosión galvánica las partes metálicas sumergidas. '
         . 'NO incluyas medidas numéricas concretas (diámetros, longitudes, pesos ni códigos): van en una tabla aparte; descríbelo de forma CUALITATIVA. Prosa natural, sin listas, sin títulos, sin emojis. '
         . 'Responde SOLO un objeto JSON válido: {"es":"<párrafo en español de España>","en":"<same paragraph in English>"} sin texto adicional ni markdown.';
    $out = llmCall($sys, $data, 700, 2);
    if ($out === '') return ['es'=>'','en'=>''];
    $out = preg_replace('/^```[a-z]*\s*|\s*```$/i', '', trim($out));
    if (preg_match('/\{.*\}/s', $out, $mm)) $out = $mm[0];
    $j = json_decode($out, true);
    if (!is_array($j)) return ['es'=>'','en'=>''];
    $wrap = function($t){ $t = trim(preg_replace('/\s+/', ' ', strip_tags((string)$t))); return ($t !== '' && mb_strlen($t,'UTF-8') >= 20) ? '<p>' . htmlspecialchars($t) . '</p>' : ''; };
    return ['es'=>$wrap($j['es'] ?? ''), 'en'=>$wrap($j['en'] ?? '')];
}

/** Descripción HTML completa para un idioma. $claimMaterials = materiales a mencionar (vacío = no mencionar).
 *  $introHtml = párrafo intro override (p.ej. del LLM); '' = usa la plantilla.
 *  La ficha PDF NO va aquí: usa el campo nativo products_pdfupload (la tienda la pinta como "Descargar Ficha PDF"). */
function tsBuildDesc(string $lang, string $nameClean, array $claimMaterials, string $typeLabel, string $application, array $items, array $tsMap, string $drawTag, string $introHtml = ''): string {
    $matNames = array_values(array_unique(array_map(fn($m)=>tsMatLabel($m,$lang), $claimMaterials)));
    $matPhrase = '';
    if (!empty($matNames)) {
        $glue = $lang === 'es' ? ' y ' : ' and ';
        $matStr = count($matNames) > 1 ? (implode(', ', array_slice($matNames,0,-1)) . $glue . end($matNames)) : $matNames[0];
        $matPhrase = ($lang === 'es' ? ', fabricado en ' : ', made of ') . mb_strtolower($matStr,'UTF-8');
    }
    if ($lang === 'es') {
        $intro = '<p>' . htmlspecialchars($nameClean) . ' es un ánodo de sacrificio Tecnoseal'
            . ($typeLabel ? ' (' . htmlspecialchars(mb_strtolower($typeLabel,'UTF-8')) . ')' : '')
            . htmlspecialchars($matPhrase) . '.'
            . ($application ? ' Compatible con ' . htmlspecialchars($application) . '.' : '')
            . ' Protege de la corrosión galvánica las partes metálicas sumergidas de la embarcación.</p>';
        $hSpec = '<h3>Especificaciones</h3>';
    } else {
        $intro = '<p>' . htmlspecialchars($nameClean) . ' is a Tecnoseal sacrificial anode'
            . ($typeLabel ? ' (' . htmlspecialchars(mb_strtolower($typeLabel,'UTF-8')) . ')' : '')
            . htmlspecialchars($matPhrase) . '.'
            . ($application ? ' Compatible with ' . htmlspecialchars($application) . '.' : '')
            . ' It protects the submerged metal parts of the boat from galvanic corrosion.</p>';
        $hSpec = '<h3>Specifications</h3>';
    }
    if ($introHtml !== '') $intro = $introHtml;   // párrafo LLM (override de la plantilla)
    $specT = tsSpecTable($items, $tsMap, $lang);
    $body = $intro;
    if ($drawTag !== '') $body .= "\n" . $drawTag;
    if ($specT !== '') $body .= "\n" . $hSpec . "\n" . $specT;
    return $body;
}

// ───────── DB helpers ─────────
function findOrCreateCategory($mysqli, $name, $parentId, $dryRun) {
    $qName = $mysqli->real_escape_string($name);
    $r = $mysqli->query("SELECT c.categories_id FROM categories c INNER JOIN categories_description cd ON cd.categories_id=c.categories_id WHERE c.parent_id=$parentId AND cd.language_id=" . LANG_ID_ES . " AND cd.categories_name='$qName' LIMIT 1");
    if ($r && $row = $r->fetch_assoc()) return (int) $row['categories_id'];
    if ($dryRun) return 0;
    $mysqli->query("INSERT INTO categories (categories_image, parent_id, sort_order, date_added, categories_status) VALUES ('', $parentId, 99, NOW(), 0)");
    $cid = (int) $mysqli->insert_id;
    $mysqli->query("INSERT INTO categories_description (categories_id, language_id, categories_name) VALUES ($cid, " . LANG_ID_ES . ", '$qName')");
    $enName = $mysqli->real_escape_string($name);
    $mysqli->query("INSERT INTO categories_description (categories_id, language_id, categories_name) VALUES ($cid, " . LANG_ID_EN . ", '$enName')");
    return $cid;
}
/** Subcategoría de tipo de ánodo: crea con nombre ES y EN distintos. */
function tsResolveSubcat($mysqli, array $type, $rootCatId, &$catCache, $dryRun) {
    $key = $type['es'];
    if (isset($catCache[$key])) return $catCache[$key];
    $qEs = $mysqli->real_escape_string($type['es']);
    $r = $mysqli->query("SELECT c.categories_id FROM categories c INNER JOIN categories_description cd ON cd.categories_id=c.categories_id WHERE c.parent_id=$rootCatId AND cd.language_id=" . LANG_ID_ES . " AND cd.categories_name='$qEs' LIMIT 1");
    if ($r && $row = $r->fetch_assoc()) { $catCache[$key] = (int)$row['categories_id']; return $catCache[$key]; }
    if ($dryRun) { $catCache[$key] = 0; return 0; }
    $mysqli->query("INSERT INTO categories (categories_image, parent_id, sort_order, date_added, categories_status) VALUES ('', $rootCatId, 99, NOW(), 1)");
    $cid = (int) $mysqli->insert_id;
    $mysqli->query("INSERT INTO categories_description (categories_id, language_id, categories_name) VALUES ($cid, " . LANG_ID_ES . ", '$qEs')");
    $qEn = $mysqli->real_escape_string($type['en']);
    $mysqli->query("INSERT INTO categories_description (categories_id, language_id, categories_name) VALUES ($cid, " . LANG_ID_EN . ", '$qEn')");
    $catCache[$key] = $cid;
    return $cid;
}
/** Value id del material (reutiliza/enlaza valores existentes a la opción 417). */
function tsMaterialValueId($mysqli, string $material, &$cache) {
    $material = tsMatEff($material);
    if (isset($cache[$material])) return $cache[$material];
    $es = tsMatLabel($material,'es'); $en = tsMatLabel($material,'en');
    $qes = $mysqli->real_escape_string($es);
    // 1) ¿ya hay un OV con ese nombre ES enlazado a la opción 417?
    $r = $mysqli->query("SELECT pov.products_options_values_id v FROM products_options_values pov INNER JOIN products_options_values_to_products_options l ON l.products_options_values_id=pov.products_options_values_id WHERE l.products_options_id=" . MATERIAL_OPTION_ID . " AND pov.language_id=" . LANG_ID_ES . " AND pov.products_options_values_name='$qes' LIMIT 1");
    if ($r && $row = $r->fetch_assoc()) { $cache[$material] = (int)$row['v']; return $cache[$material]; }
    // 2) ¿existe el OV (en cualquier opción)? -> enlázalo a 417 y reutiliza
    $r = $mysqli->query("SELECT products_options_values_id v FROM products_options_values WHERE language_id=" . LANG_ID_ES . " AND products_options_values_name='$qes' ORDER BY products_options_values_id LIMIT 1");
    if ($r && $row = $r->fetch_assoc()) {
        $vid = (int)$row['v'];
        $chk = $mysqli->query("SELECT 1 FROM products_options_values_to_products_options WHERE products_options_id=" . MATERIAL_OPTION_ID . " AND products_options_values_id=$vid LIMIT 1");
        if (!$chk || !$chk->fetch_row()) $mysqli->query("INSERT INTO products_options_values_to_products_options (products_options_id, products_options_values_id) VALUES (" . MATERIAL_OPTION_ID . ", $vid)");
        $cache[$material] = $vid; return $vid;
    }
    // 3) crear nuevo OV (ES + EN) y enlazar
    $nq = $mysqli->query("SELECT IFNULL(MAX(products_options_values_id),0)+1 nid FROM products_options_values");
    $vid = (int)$nq->fetch_assoc()['nid'];
    $qen = $mysqli->real_escape_string($en);
    $mysqli->query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($vid, " . LANG_ID_ES . ", '$qes', '')");
    $mysqli->query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($vid, " . LANG_ID_EN . ", '$qen', '')");
    $mysqli->query("INSERT INTO products_options_values_to_products_options (products_options_id, products_options_values_id) VALUES (" . MATERIAL_OPTION_ID . ", $vid)");
    $cache[$material] = $vid; return $vid;
}
/** Find-or-create de un valor de la opción 417 por NOMBRE exacto (para desambiguar colisiones). */
function tsValueIdByName($mysqli, string $es, string $en) {
    $qes = $mysqli->real_escape_string($es);
    $r = $mysqli->query("SELECT pov.products_options_values_id v FROM products_options_values pov INNER JOIN products_options_values_to_products_options l ON l.products_options_values_id=pov.products_options_values_id WHERE l.products_options_id=" . MATERIAL_OPTION_ID . " AND pov.language_id=" . LANG_ID_ES . " AND pov.products_options_values_name='$qes' LIMIT 1");
    if ($r && $row = $r->fetch_assoc()) return (int)$row['v'];
    $nq = $mysqli->query("SELECT IFNULL(MAX(products_options_values_id),0)+1 nid FROM products_options_values");
    $vid = (int)$nq->fetch_assoc()['nid'];
    $qen = $mysqli->real_escape_string($en);
    $mysqli->query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($vid, " . LANG_ID_ES . ", '$qes', '')");
    $mysqli->query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($vid, " . LANG_ID_EN . ", '$qen', '')");
    $mysqli->query("INSERT INTO products_options_values_to_products_options (products_options_id, products_options_values_id) VALUES (" . MATERIAL_OPTION_ID . ", $vid)");
    return $vid;
}
/** Mapa de existentes (scope mfg 458 / origin tecnoseal% + EAN global + lista negra). */
function buildExistingMap($mysqli) {
    $existing = [];
    $f = "(p.manufacturers_id=" . TS_MANUFACTURER_ID . " OR p.products_import_origin LIKE 'tecnoseal%')";
    foreach (['p.products_model','p.reference_prov'] as $col) {
        $r = $mysqli->query("SELECT LOWER($col) m FROM products p WHERE $col<>'' AND $col IS NOT NULL AND $f");
        while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
    }
    foreach (['pa.reference','pa.reference_prov'] as $col) {
        $r = $mysqli->query("SELECT LOWER($col) m FROM products_attributes pa INNER JOIN products p ON p.products_id=pa.products_id WHERE $col<>'' AND $col IS NOT NULL AND $f");
        while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
    }
    $r = $mysqli->query("SELECT LOWER(product_ean) m FROM products WHERE product_ean<>'' AND product_ean IS NOT NULL");
    while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
    $r = $mysqli->query("SELECT LOWER(products_attributes_ean) m FROM products_attributes WHERE products_attributes_ean<>'' AND products_attributes_ean IS NOT NULL");
    while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
    require_once dirname(__FILE__) . '/includes/import_blacklist.php';
    $existing += fb_blacklist_keys();
    return $existing;
}
function loadTsXlsx($path) {
    require_once dirname(dirname(__FILE__)) . '/includes/vendor/autoload.php';
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
    $reader->setReadDataOnly(true);
    $ss = $reader->load($path); $sheet = $ss->getActiveSheet(); $hi = $sheet->getHighestRow();
    $rows = [];
    for ($r = 2; $r <= $hi; $r++) {
        $code = trim((string) $sheet->getCell('A' . $r)->getValue());
        $name = trim((string) $sheet->getCell('B' . $r)->getValue());
        $dr   = tsNum($sheet->getCell('C' . $r)->getValue());
        $pvp  = tsNum($sheet->getCell('D' . $r)->getValue());
        if ($code === '' || $name === '' || $pvp === null || $pvp <= 0 || $dr === null || $dr < 0) continue;
        $rows[$code] = ['CODE'=>$code, 'NAME'=>$name, 'DR'=>round($dr,4), 'PVP'=>round($pvp,4)];
    }
    return $rows;
}
function tsNormCode($s){ return preg_replace('/[^a-z0-9]/','', strtolower($s)); }

// ───────────────────────── Acción ─────────────────────────
$isAction = ($action === 'execute' || $action === 'dry_run');
if ($isAction) {
    @header('X-Accel-Buffering: no'); @header('Content-Type: text/html; charset=utf-8');
    while (ob_get_level() > 0) @ob_end_flush(); @ob_implicit_flush(true);
    if (session_status() === PHP_SESSION_ACTIVE) @session_write_close();
}
?>
<?php require THEME . 'html/header.php'; ?>
<table style="width:100%;"><tr><td>
<div style="padding:20px;">
<?php if ($isAction): ?>
    <h2>Importador Tecnoseal — <?php echo $dryRun ? 'DRY-RUN (sin cambios)' : 'EJECUCIÓN REAL'; ?></h2>
    <p><a href="<?php echo tep_href_link('import-tecnoseal-altas.php'); ?>" class="xbutton small hv9">← Volver</a></p>
    <div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n"; @flush();

logMsg("Modo: " . ($dryRun ? "dry-run" : "EXECUTE")
    . ($useLlm ? " | LLM redacción" : "")
    . ($skipImages ? " | sin imágenes" : "") . ($allowNoImage ? " | permite sin imagen" : "")
    . (!empty($onlyCodes) ? " | codes=" . count($onlyCodes) : "")
    . ((strcasecmp($selectedFamily,'all')!==0 && $selectedFamily!=='') ? " | familia='$selectedFamily'" : "")
    . ($max > 0 ? " | max=$max" : ""));

if (!file_exists(TS_XLSX)) { logMsg("ERROR: no encuentro " . TS_XLSX); goto end_action; }
logMsg("xlsx: " . basename(TS_XLSX) . " (" . round(filesize(TS_XLSX)/1024) . " KB)");
$tsMap = file_exists(TS_MAP_PATH) ? (json_decode(file_get_contents(TS_MAP_PATH), true) ?: []) : [];
logMsg("ts_map.json: " . count($tsMap) . " códigos con datos web");

$xlsxRows = loadTsXlsx(TS_XLSX);
logMsg("Filas xlsx válidas: " . count($xlsxRows));

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_error) { logMsg("ERROR DB: " . $mysqli->connect_error); goto end_action; }
$mysqli->set_charset('utf8');
if (!$dryRun && !is_dir(IMG_ABS_DIR)) @mkdir(IMG_ABS_DIR, 0775, true);

$rootCatId = findOrCreateCategory($mysqli, NEW_CATEGORY_NAME, 0, $dryRun);
logMsg("Categoría raíz '" . NEW_CATEGORY_NAME . "' → id=$rootCatId" . ($rootCatId === 0 ? " (dry-run)" : ""));

$existing = buildExistingMap($mysqli);
logMsg("Referencias ya en BD (scope tecnoseal + EAN globales + lista negra): " . count($existing));

// ── Agrupación por material ──
$groups = [];   // stem => [code => row]
foreach ($xlsxRows as $code => $row) {
    $mat = tsMaterial($code, $row['NAME']);
    $stem = tsStem($code, $mat);
    $row['_MAT'] = $mat;
    $groups[$stem][$code] = $row;
}
logMsg("Agrupación por material: " . count($groups) . " productos (de " . count($xlsxRows) . " códigos)");

// ── Pre-filtrado ──
$skipExist=$skipCodes=$skipFamily=0; $candidates=[];
$familyFilter = ($selectedFamily!=='' && strcasecmp($selectedFamily,'all')!==0);
foreach ($groups as $stem => $items) {
    // filtro codes: incluir el grupo si ALGÚN código está en la lista
    if (!empty($onlyCodes)) {
        $hit = false; foreach ($items as $c=>$_) { if (isset($onlyCodes[strtolower($c)])) { $hit=true; break; } }
        if (!$hit) { $skipCodes++; continue; }
    }
    // dedup: si CUALQUIER código del grupo ya existe -> saltar grupo entero
    $dup = false;
    foreach ($items as $c=>$_) { if (isset($existing[strtolower($c)])) { $dup=true; break; } }
    if ($dup) { $skipExist++; continue; }
    // familia (tipo de ánodo del padre = variante más barata)
    if ($familyFilter) {
        $cheapName = '';
        $minPvp = PHP_FLOAT_MAX;
        foreach ($items as $c=>$rr) { if ($rr['PVP'] < $minPvp) { $minPvp = $rr['PVP']; $cheapName = $rr['NAME']; } }
        $t = tsAnodeType($cheapName);
        if (strcasecmp($t['es'], $selectedFamily) !== 0) { $skipFamily++; continue; }
    }
    $candidates[$stem] = $items;
}
logMsg("Candidatos: " . count($candidates) . " | ya-existentes=$skipExist | fuera-codes=$skipCodes" . ($familyFilter ? " | fuera-familia=$skipFamily" : ""));

$catCache = []; $matValCache = [];
// Pre-resuelve los valores de material (zinc/aluminio/magnesio) FUERA de toda transacción:
// así dentro del bucle tsMaterialValueId siempre acierta en caché y nunca inserta OV dentro de un tx que pueda hacer rollback.
if (!$dryRun) { foreach (['zinc','aluminio','magnesio'] as $mm) tsMaterialValueId($mysqli, $mm, $matValCache); }
$state = ['nInserted'=>0,'nFamilies'=>0,'nStandalone'=>0,'nWithImg'=>0,'skippedNoImg'=>0,'nVariants'=>0,'errors'=>0,'nDraw'=>0,'nPdf'=>0,'nLlm'=>0,'nSubImg'=>0];

function tsCopyCacheImg($file, $destAbs) {
    $src = TS_CACHE_IMG_DIR . $file;
    if ($file === '' || !is_file($src) || filesize($src) < IMG_MIN_BYTES) return false;
    if (!@copy($src, $destAbs)) return false;
    if (!@getimagesize($destAbs)) { @unlink($destAbs); return false; }
    return true;
}
/** Copia la ficha PDF de cache → manuals/ (campo nativo products_pdfupload). Valida cabecera %PDF. */
function tsCopyCachePdf($file, $destAbs) {
    $src = TS_CACHE_PDF_DIR . $file;
    if ($file === '' || !is_file($src) || filesize($src) < 1000) return false;
    if (!is_dir(TS_MANUALS_DIR)) @mkdir(TS_MANUALS_DIR, 0775, true);
    $fh = @fopen($src, 'rb'); $magic = $fh ? fread($fh, 5) : ''; if ($fh) fclose($fh);
    if (substr($magic, 0, 4) !== '%PDF') return false;
    return @copy($src, $destAbs);
}

/** Para los dibujos técnicos embebidos en la descripción: recorta el borde blanco (autocrop por
 *  color de esquina), deja un margen mínimo uniforme y redimensiona a ancho máx $maxW (solo
 *  downscale, aspecto preservado, fondo blanco). Preferencia usuario 2026-06-16. */
function tsTrimResizeDrawing($abs, $maxW = 600, $margin = 12) {
    if (!@getimagesize($abs)) return;
    $img = @imagecreatefrompng($abs);
    if (!$img) return;                                       // no PNG legible → se deja tal cual
    // 1) autocrop: usa el color de las 4 esquinas (blanco/fondo uniforme); fallback por umbral
    $cropped = @imagecropauto($img, IMG_CROP_SIDES);
    if (!($cropped instanceof \GdImage)) { $wcol = imagecolorallocate($img, 255,255,255); $cropped = @imagecropauto($img, IMG_CROP_THRESHOLD, 0.2, $wcol); }
    if ($cropped instanceof \GdImage && imagesx($cropped) >= 8 && imagesy($cropped) >= 8) { $img = $cropped; }
    $w = imagesx($img); $h = imagesy($img);
    if ($w < 1 || $h < 1) { return; }
    // 2) escala para caber en (maxW - 2*margen), solo downscale
    $inner = max(1, $maxW - 2*$margin);
    $scale = ($w > $inner) ? $inner / $w : 1.0;
    $dw = max(1, (int) round($w * $scale)); $dh = max(1, (int) round($h * $scale));
    // 3) lienzo blanco con margen uniforme + composición (transparencias → blanco)
    $canvas = imagecreatetruecolor($dw + 2*$margin, $dh + 2*$margin);
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
    imagealphablending($canvas, true);
    imagecopyresampled($canvas, $img, $margin, $margin, 0, 0, $dw, $dh, $w, $h);
    imagepng($canvas, $abs, 6);
    // (sin imagedestroy: no-op desde PHP 8.0 y deprecado en 8.5; los GdImage los libera el GC)
}

/** FOTO de producto: recorta el lienzo (fondo blanco/transparente) y deja un marco pequeño, cuadrado y
 *  centrado, para que el producto llene el frame (las fotos vienen muy descentradas con mucho blanco).
 *  Recorta el FONDO, no el producto. */
function tsTrimPhoto($abs, $marginPct = 0.05, $maxDim = 1000) {
    $info = @getimagesize($abs); if (!$info) return;
    $img = @imagecreatefrompng($abs); if (!$img) return;
    $w = imagesx($img); $h = imagesy($img);
    // bbox del producto: fondo = transparente (alpha>=100) o casi-blanco (r,g,b >= 248)
    $T = 248; $minx=$w; $miny=$h; $maxx=-1; $maxy=-1;
    $step = ($w*$h > 600000) ? 2 : 1;
    for ($y=0; $y<$h; $y+=$step) for ($x=0; $x<$w; $x+=$step) {
        $c = imagecolorat($img, $x, $y);
        if ((($c >> 24) & 0x7F) >= 100) continue;                 // transparente = fondo
        $r=($c>>16)&255; $g=($c>>8)&255; $b=$c&255;
        if ($r>=$T && $g>=$T && $b>=$T) continue;                 // casi-blanco = fondo
        if ($x<$minx)$minx=$x; if($x>$maxx)$maxx=$x; if($y<$miny)$miny=$y; if($y>$maxy)$maxy=$y;
    }
    if ($maxx < 0) return;                                        // imagen toda fondo → no tocar
    $minx=max(0,$minx-$step); $miny=max(0,$miny-$step); $maxx=min($w-1,$maxx+$step); $maxy=min($h-1,$maxy+$step);
    $cw=$maxx-$minx+1; $ch=$maxy-$miny+1;
    if ($cw<8 || $ch<8) return;                                   // bbox absurdo → no tocar
    if ($cw > $w*0.92 && $ch > $h*0.92) return;                   // apenas hay marco → ya está ajustada
    $side = max($cw,$ch); $margin = max(2,(int)round($side*$marginPct)); $canvasSide=$side+2*$margin;
    $scale = ($canvasSide > $maxDim) ? $maxDim/$canvasSide : 1.0;
    $outSide=max(1,(int)round($canvasSide*$scale)); $dcw=max(1,(int)round($cw*$scale)); $dch=max(1,(int)round($ch*$scale));
    $offx=(int)round(($outSide-$dcw)/2); $offy=(int)round(($outSide-$dch)/2);
    $canvas=imagecreatetruecolor($outSide,$outSide);
    imagefill($canvas,0,0,imagecolorallocate($canvas,255,255,255));
    imagealphablending($canvas,true);                            // aplana transparencia sobre blanco
    imagecopyresampled($canvas,$img,$offx,$offy,$minx,$miny,$dcw,$dch,$cw,$ch);
    imagepng($canvas,$abs,6);
}

function tsInsertGroup($mysqli, $ctx, array $items, $tsMap, $dryRun, &$state, &$catCache, &$matValCache) {
    // variante más barata = padre
    uasort($items, fn($a,$b) => $a['PVP'] <=> $b['PVP']);
    $cheapestCode = array_key_first($items);
    $cheap = $items[$cheapestCode];
    $isFamily = count($items) > 1;

    // datos web: preferimos el cheapest y rellenamos huecos con cualquier hermano (orden por precio asc)
    $pick = function($field) use ($items, $tsMap) {
        foreach ($items as $c => $_) {
            $val = $tsMap[$c][$field] ?? null;
            if (is_array($val)) { if (!empty($val)) return $val; }
            elseif (trim((string)$val) !== '') return $val;
        }
        return ($field === 'dims') ? [] : '';
    };
    $enRaw     = trim((string)$pick('en_name'));
    $photoFile = (string)$pick('photo_file');
    $photoFilesArr = $pick('photo_files'); if (!is_array($photoFilesArr) || empty($photoFilesArr)) $photoFilesArr = ($photoFile !== '' ? [$photoFile] : []);
    $drawFile  = (string)$pick('draw_file');
    $pdfFile   = (string)$pick('pdf_file');
    $dims      = $pick('dims'); if (!is_array($dims)) $dims = [];

    $nameEnClean = tsCleanNameEn($enRaw !== '' ? $enRaw : $cheap['NAME']);
    if ($nameEnClean === '') $nameEnClean = $cheap['NAME'];
    $nameEsClean = tsCleanNameEs($cheap['NAME'], $cheap['_MAT']);
    $titleEs = mb_substr(tsBrandPrefix($nameEsClean), 0, PRODUCT_NAME_MAX, 'UTF-8');
    $titleEn = mb_substr(tsBrandPrefix($nameEnClean), 0, PRODUCT_NAME_MAX, 'UTF-8');

    $type = tsAnodeType($cheap['NAME']);
    $application = trim((string)($tsMap[$cheapestCode]['brand'] ?? ''));   // marca real de la chapa web
    if ($application === '') $application = tsApplication($cheap['NAME']);  // fallback a keywords del nombre

    // material por código: nombre/sufijo → chapa de la web (parseWebMaterial) → '' (sin material: cepillo grafito, kits).
    // Así los SUELTOS también identifican su material (la web trae la chapa ZN/AL/MG aunque el nombre no lo diga).
    $materials = []; $codeMat = [];
    foreach ($items as $c=>$rr) {
        $cm = $rr['_MAT'] !== '' ? tsMatEff($rr['_MAT']) : (string)($tsMap[$c]['material'] ?? '');
        $codeMat[$c] = $cm;
        if ($cm !== '') $materials[] = $cm;
    }
    $materials = array_values(array_unique($materials));
    $claimMaterials = $materials;   // web-aware; vacío sólo si NINGÚN código tiene material (entonces no se afirma)

    // precios padre
    $padrePrice = roundToNickel($cheap['PVP']);
    $padreCost  = $cheap['DR'];
    $padreG1    = roundToNickel(calcG1Price($padrePrice, $padreCost));
    $padreKg    = $tsMap[$cheapestCode]['kg'] ?? null;
    $padreWeight = ($padreKg !== null && $padreKg > 0) ? (float)$padreKg : DEFAULT_WEIGHT;

    if ($dryRun) {
        logMsg(sprintf("  WOULD INSERT stem cheap=%s '%s' tipo='%s' mats=[%s] price=%.2f cost=%.2f g1=%.2f vars=%d img=%s",
            $cheapestCode, mb_substr($titleEs,0,42,'UTF-8'), $type['es'], implode('/',$materials),
            $padrePrice, $padreCost, $padreG1, count($items),
            $photoFile ?: 'NO'));
        $state['nInserted']++; if ($isFamily) $state['nFamilies']++; else $state['nStandalone']++;
        return true;
    }

    // imagen (copia desde cache) ANTES del insert
    $photoTmps = []; $drawTmp = null; $imgAssigned = false;
    if (!$ctx['skipImages']) {
        foreach ($photoFilesArr as $pf) {
            if ($pf === '') continue;
            $cand = IMG_ABS_DIR . 'ts-tmp-' . uniqid('', true) . '.png';
            if (tsCopyCacheImg($pf, $cand)) { tsTrimPhoto($cand); $photoTmps[] = $cand; }
        }
        if (empty($photoTmps) && !$ctx['allowNoImage']) {
            $state['skippedNoImg']++; logMsg("SKIP $cheapestCode: sin imagen en cache");
            return false;
        }
        $df = $drawFile;
        if ($df !== '') {
            $cand = IMG_ABS_DIR . 'ts-td-' . uniqid('', true) . '.png';
            if (tsCopyCacheImg($df, $cand)) { tsTrimResizeDrawing($cand, 600, 12); $drawTmp = $cand; }
        }
    }

    $subcatId = tsResolveSubcat($mysqli, $type, $ctx['rootCatId'], $catCache, false);

    $mysqli->begin_transaction();
    try {
        $qmodel = $mysqli->real_escape_string($cheapestCode);
        $sql = "INSERT INTO products (products_quantity, check_stock, products_model, products_image, products_price, products_cost, products_date_added, products_weight, products_status, products_tax_class_id, manufacturers_id, product_ean, reference_prov, products_import_origin)
            VALUES (" . STOCK_SENTINEL . ", 0, \"$qmodel\", \"\", " . number_format($padrePrice,4,'.','') . ", " . number_format($padreCost,4,'.','') . ", NOW(), " . number_format($padreWeight,3,'.','') . ", 2, " . TAX_CLASS_IVA21 . ", " . TS_MANUFACTURER_ID . ", \"\", \"$qmodel\", \"" . ORIGIN_FLAG . "\")";
        if (!$mysqli->query($sql)) throw new Exception("products: " . $mysqli->error);
        $pid = (int) $mysqli->insert_id;

        // dibujo técnico → fichero definitivo + tag inline
        $drawTag = '';
        if ($drawTmp !== null && file_exists($drawTmp)) {
            $tdFinal = tsSlugify($nameEsClean) . '-' . $pid . '-td.png';
            if (@rename($drawTmp, IMG_ABS_DIR . $tdFinal)) { $drawTag = tsDrawingImg($tdFinal, $titleEs); $drawTmp = null; $state['nDraw']++; }
            else { @unlink($drawTmp); $drawTmp = null; }
        }

        // ficha técnica PDF → carpeta manuals/ + campo NATIVO products_pdfupload (la tienda lo pinta solo)
        if ($pdfFile !== '') {
            $pdfName = mb_substr(tsSlugify($nameEsClean), 0, 45) . '-' . $pid . '.pdf';   // ≤64 (col varchar 64)
            if (tsCopyCachePdf($pdfFile, TS_MANUALS_DIR . $pdfName)) {
                $mysqli->query("UPDATE products SET products_pdfupload='" . $mysqli->real_escape_string($pdfName) . "' WHERE products_id=$pid");
                $state['nPdf']++;
            }
        }

        // Redacción LLM opcional del párrafo intro (specs/dibujo/PDF siguen deterministas)
        $llmIntros = ['es'=>'','en'=>''];
        if (!empty($ctx['llm'])) {
            $llmIntros = tsLlmIntros($nameEsClean, $nameEnClean, $type['es'], $claimMaterials, $application);
            if ($llmIntros['es'] !== '' || $llmIntros['en'] !== '') $state['nLlm']++;
        }
        $descEs = tsBuildDesc('es', $nameEsClean, $claimMaterials, $type['es'], $application, $items, $tsMap, $drawTag, $llmIntros['es']);
        $descEn = tsBuildDesc('en', $nameEnClean, $claimMaterials, $type['en'], $application, $items, $tsMap, $drawTag, $llmIntros['en']);

        $qNameEs=$mysqli->real_escape_string($titleEs); $qDescEs=$mysqli->real_escape_string($descEs);
        $qNameEn=$mysqli->real_escape_string($titleEn); $qDescEn=$mysqli->real_escape_string($descEn);
        if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_ES . ", \"$qNameEs\", \"$qDescEs\", 0)")) throw new Exception("desc ES: " . $mysqli->error);
        if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_EN . ", \"$qNameEn\", \"$qDescEn\", 0)")) throw new Exception("desc EN: " . $mysqli->error);
        if (!$mysqli->query("INSERT INTO products_to_categories (products_id, categories_id) VALUES ($pid, $subcatId)")) throw new Exception("p2c: " . $mysqli->error);
        if (!$mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . G1_GROUP_ID . ", $pid, " . number_format($padreG1,4,'.','') . ", 1, 1)")) throw new Exception("g1: " . $mysqli->error);

        // imagen principal + subimágenes (todas las fotos de la galería; dedup por contenido md5)
        if (!empty($photoTmps)) {
            $slugFs = tsSlugify($nameEsClean); $finalNames = []; $seenMd5 = [];
            foreach ($photoTmps as $tmp) {
                if (!file_exists($tmp)) continue;
                $md5 = @md5_file($tmp);
                if ($md5 !== false && isset($seenMd5[$md5])) { @unlink($tmp); continue; }   // imagen duplicada
                if ($md5 !== false) $seenMd5[$md5] = true;
                $suffix = empty($finalNames) ? '' : '-' . (count($finalNames) + 1);
                $fn = $slugFs . '-' . $pid . $suffix . '.png';
                if (@rename($tmp, IMG_ABS_DIR . $fn)) $finalNames[] = $fn; else @unlink($tmp);
            }
            $photoTmps = [];
            if (!empty($finalNames)) {
                $mainImg = array_shift($finalNames);
                $mysqli->query("UPDATE products SET products_image=\"" . $mysqli->real_escape_string($mainImg) . "\" WHERE products_id=$pid");
                $imgAssigned = true; $state['nWithImg']++;
                if (!empty($finalNames)) {
                    $subJson = json_encode(array_values($finalNames), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    $mysqli->query("UPDATE products SET products_subimages='" . $mysqli->real_escape_string($subJson) . "' WHERE products_id=$pid");
                    $state['nSubImg'] += count($finalNames);
                }
            }
        }

        if ($isFamily) {
            $usedVal = [];
            foreach ($items as $code => $v) {
                $vMat = $codeMat[$code] !== '' ? $codeMat[$code] : 'zinc';   // material web-aware (base sin marca = zinc)
                $valueId = tsMaterialValueId($mysqli, $vMat, $matValCache);
                if (isset($usedVal[$valueId])) {
                    // 2 códigos del mismo material en el grupo → valor desambiguado por código (NO perder la variante)
                    $valueId = tsValueIdByName($mysqli, tsMatLabel($vMat,'es') . ' (' . $code . ')', tsMatLabel($vMat,'en') . ' (' . $code . ')');
                    if (isset($usedVal[$valueId])) { logMsg("AVISO $code: colisión de valor irresoluble, omitido"); $state['errors']++; continue; }
                }
                $usedVal[$valueId] = true;
                $vPrice = roundToNickel($v['PVP']);
                $delta = round($vPrice - $padrePrice, 4); $prefix = $delta < 0 ? '-' : '+';
                $vKg = $tsMap[$code]['kg'] ?? null;
                $wDelta = ($vKg !== null) ? round((float)$vKg - $padreWeight, 4) : 0.0;
                $wPrefix = $wDelta < 0 ? '-' : '+';
                $qref = $mysqli->real_escape_string($code);
                if (!$mysqli->query("INSERT INTO products_attributes SET products_id=$pid, options_id=" . MATERIAL_OPTION_ID . ", options_values_id=$valueId, options_values_price=" . number_format(abs($delta),4,'.','') . ", price_prefix='$prefix', reference='$qref', reference_prov='$qref', products_attributes_ean='', options_values_weight=" . number_format(abs($wDelta),3,'.','') . ", weight_prefix='$wPrefix'")) throw new Exception("attr: " . $mysqli->error);
                $paId = (int) $mysqli->insert_id;
                // EAN real GS1 de la web Tecnoseal (por código de variante); si falta/ inválido → interno 28
                $vRealEan = trim((string)($tsMap[$code]['ean'] ?? ''));
                $vEan = isValidEan13($vRealEan) ? $vRealEan : generateInternalEan13($paId, EAN_INTERNAL_PREFIX);
                if ($vEan !== '') $mysqli->query("UPDATE products_attributes SET products_attributes_ean='" . $mysqli->real_escape_string($vEan) . "' WHERE products_attributes_id=$paId");
                $mysqli->query("INSERT INTO products_stock (products_id, products_stock_attributes, products_stock_quantity, products_stock_cost) VALUES ($pid, '" . MATERIAL_OPTION_ID . "-$valueId', " . STOCK_SENTINEL . ", 0.0000)");
                $vG1 = roundToNickel(calcG1Price($vPrice, $v['DR']));
                $g1Delta = round($vG1 - $padreG1, 4); $g1Prefix = $g1Delta < 0 ? '-' : '+';
                if (!$mysqli->query("INSERT INTO products_attributes_groups (products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id, options_values_weight, weight_prefix) VALUES ($paId, " . G1_GROUP_ID . ", " . number_format(abs($g1Delta),4,'.','') . ", '$g1Prefix', $pid, 0, '+')")) throw new Exception("attr_groups: " . $mysqli->error);
                $state['nVariants']++;
            }
            $mysqli->commit();
            $state['nFamilies']++;
        } else {
            $mysqli->commit();
            // EAN del suelto: real GS1 de la web si es válido; si no, interno 28
            $realEan = trim((string)($tsMap[$cheapestCode]['ean'] ?? ''));
            $genEan = isValidEan13($realEan) ? $realEan : generateInternalEan13($pid, EAN_INTERNAL_PREFIX);
            if ($genEan !== '') $mysqli->query("UPDATE products SET product_ean='" . $mysqli->real_escape_string($genEan) . "' WHERE products_id=$pid AND (product_ean IS NULL OR product_ean='')");
            $state['nStandalone']++;
        }

        foreach (array_merge($photoTmps, [$drawTmp]) as $t) { if ($t!==null && is_file($t)) @unlink($t); } // limpia tmp huérfanos (rename falló)
        $state['nInserted']++;
        logMsg(sprintf("OK pid=%d cheap=%s tipo='%s' mats=[%s] vars=%d price=%.2f cost=%.2f g1=%.2f%s%s",
            $pid, $cheapestCode, $type['es'], implode('/',$materials), count($items),
            $padrePrice, $padreCost, $padreG1, $imgAssigned?' +img':'', $drawTag!==''?' +dibujo':''));
        return true;
    } catch (Exception $e) {
        $mysqli->rollback();
        $state['errors']++;
        foreach (array_merge($photoTmps, [$drawTmp]) as $t) { if ($t!==null && file_exists($t)) @unlink($t); }
        logMsg("ERROR $cheapestCode: " . $e->getMessage());
        return false;
    }
}

$ctx = ['rootCatId'=>$rootCatId, 'skipImages'=>$skipImages, 'allowNoImage'=>$allowNoImage, 'llm'=>$useLlm];
foreach ($candidates as $stem => $items) {
    if ($max > 0 && $state['nInserted'] >= $max) { logMsg("Alcanzado max=$max, parando."); break; }
    tsInsertGroup($mysqli, $ctx, $items, $tsMap, $dryRun, $state, $catCache, $matValCache);
}

logMsg("==================== RESUMEN ====================");
logMsg(sprintf("INSERT productos: %d (con variantes=%d, sueltos=%d) | variantes: %d", $state['nInserted'], $state['nFamilies'], $state['nStandalone'], $state['nVariants']));
logMsg("Con imagen: " . $state['nWithImg'] . " | subimágenes: " . $state['nSubImg'] . " | con dibujo técnico: " . $state['nDraw'] . " | con ficha PDF: " . $state['nPdf'] . " | con redacción LLM: " . $state['nLlm'] . " | saltados sin imagen: " . $state['skippedNoImg']);
logMsg("Errores: " . $state['errors']);

end_action:
?>
    </div>
<?php else: ?>
<h2>Importador Tecnoseal</h2>
<p>Lee <code><?php echo TS_XLSX; ?></code> (precios) + <code>ts_map.json</code> (catálogo web: nombre EN, foto, dibujo, peso, cotas) y crea productos en "<strong><?php echo NEW_CATEGORY_NAME; ?></strong>" con status=2.</p>
<p>Variantes <strong>agrupadas por material</strong> (Zinc/Aluminio/Magnesio) · precio: <code>cost=DR, price=roundToNickel(PVP sin IVA)</code> · G1 tiers+piso · descripción plantilla (sin LLM).</p>
<?php
$tsMapF = file_exists(TS_MAP_PATH) ? (json_decode(file_get_contents(TS_MAP_PATH), true) ?: []) : [];
// familias (tipos de ánodo) con conteos a partir del xlsx, agrupando por material
$famCounts = [];
if (file_exists(TS_XLSX)) {
    $rowsF = loadTsXlsx(TS_XLSX);
    $grp = [];
    foreach ($rowsF as $code=>$row) { $m=tsMaterial($code,$row['NAME']); $grp[tsStem($code,$m)][$code]=$row; }
    foreach ($grp as $items) {
        $minPvp=PHP_FLOAT_MAX; $nm='';
        foreach ($items as $rr) { if ($rr['PVP']<$minPvp){$minPvp=$rr['PVP'];$nm=$rr['NAME'];} }
        $t=tsAnodeType($nm); $famCounts[$t['es']]=($famCounts[$t['es']]??0)+1;
    }
    uksort($famCounts,'strcasecmp');
}
function tsFamilySelect($famCounts){
    $h='<select name="family"><option value="all">— Todas las familias —</option>';
    foreach($famCounts as $f=>$n){ $h.='<option value="'.htmlspecialchars($f,ENT_QUOTES).'">'.htmlspecialchars($f).' ('.$n.' prod)</option>'; }
    return $h.'</select>';
}
?>
<table style="margin:10px 0;border:1px solid #ddd;border-collapse:collapse;">
    <tr><th>xlsx</th><td><?php echo file_exists(TS_XLSX) ? basename(TS_XLSX).' ('.round(filesize(TS_XLSX)/1024).' KB)' : '<span style="color:red">NO ENCONTRADO</span>'; ?></td></tr>
    <tr><th>ts_map.json</th><td><?php echo $tsMapF ? count($tsMapF).' códigos con datos web' : '<span style="color:red">NO ENCONTRADO — ejecuta ts_build_cache.php</span>'; ?></td></tr>
    <tr><th>Manufacturer</th><td>Tecnoseal (id=<?php echo TS_MANUFACTURER_ID; ?>)</td></tr>
    <tr><th>EAN interno</th><td>prefijo <?php echo EAN_INTERNAL_PREFIX; ?> · Opción variante: Material (id=<?php echo MATERIAL_OPTION_ID; ?>)</td></tr>
    <tr><th>Stock</th><td><?php echo STOCK_SENTINEL; ?> "bajo pedido", check_stock=0, status=2</td></tr>
</table>
<?php foreach (['dry_run'=>'1. Dry-run (sin cambios)', 'execute'=>'2. EJECUTAR (INSERT real)'] as $act=>$legend): ?>
<form method="post" action="<?php echo tep_href_link('import-tecnoseal-altas.php', 'action='.$act); ?>" style="margin:10px 0;" <?php echo $act==='execute' ? "onsubmit=\"return confirm('Ejecutar inserciones REALES en BD?');\"" : ''; ?>>
    <fieldset><legend><?php echo $legend; ?></legend>
        <label>familia: <?php echo tsFamilySelect($famCounts); ?></label>
        <label>max productos: <input name="max" type="number" min="0" value="0" style="width:80px"></label>
        <label>codes (csv): <input name="codes" type="text" size="36" placeholder="00100,00218"></label>
        <label><input type="checkbox" name="skip_images"> sin imágenes</label>
        <label><input type="checkbox" name="allow_no_image"> importar aunque no haya imagen</label>
        <label title="Redacta el párrafo intro con el LLM local (más lento; la tabla de specs no se toca)"><input type="checkbox" name="llm"> redacción LLM (párrafo comercial)</label>
        <button type="submit" <?php echo $act==='execute' ? 'style="background:#c30;color:#fff;"' : ''; ?>><?php echo $act==='execute' ? 'EXECUTE' : 'Dry-run'; ?></button>
    </fieldset>
</form>
<?php endforeach; ?>
<?php endif; ?>
</div>
</td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
