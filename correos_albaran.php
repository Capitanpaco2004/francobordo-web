<?php
/**
 * Endpoint de integración Vstock → Correos (salida de pedidos).
 *
 * Lo llama el watcher de albaranes (.112) cuando el operador finaliza un albarán
 * en Vstock con la agencia de Correos: preregistra el envío vía API (clase
 * includes/classes/correos.php), guarda el registro en correos_shipments (tipo
 * 'envio') y devuelve la etiqueta (ZPL térmica y/o PDF) + la URL de tracking REAL
 * para el cliente (los códigos del rango antiguo de QFac no eran rastreables).
 *
 * Standalone (no pasa por application_top): configure.php + mysqli + clase correos.
 * Protegido por token. Entorno: siempre 'pro' (no hay sandbox con labels).
 *
 * Diseño anti-doble-facturación (revisión 2026-06-15):
 *   1) GET_LOCK por pedido → serializa peticiones concurrentes del mismo oid.
 *   2) Dedup por orders_id SIEMPRE (1 pedido web = 1 albarán = 1 preregistro
 *      N-bultos). Excluye envíos anulados (cancelled_at) para permitir reenvío.
 *   3) Persistencia TEMPRANA: en cuanto el preregistro crea el envío en Correos,
 *      se inserta la fila (ok=0) con shipment_code y el JSON del preregistro
 *      (que lleva TODOS los packageCodes) ANTES de pedir etiquetas. Si el script
 *      muere luego, el dedup encuentra esa fila y NO vuelve a preregistrar
 *      (reintento idempotente: solo re-pide la etiqueta sobre los mismos códigos).
 *   4) ok=1 SOLO si hay etiqueta efectiva. Sin etiqueta → ok=0 + ok:false para
 *      que el watcher reintente.
 *
 * Parámetros (GET o POST):
 *   token   (obligatorio)
 *   oid     (obligatorio) orders_id del pedido web
 *   kilos   peso TOTAL en kg (def. 1)
 *   bultos  nº de bultos (def. 1; el peso se reparte, el último absorbe el resto)
 *   albaran id del albarán Vstock (ALB_ID) — histórico
 *   type    ZPL | PDF | BOTH (def. BOTH)
 *   mode    dom | ofi (def. dom). ofi requiere parámetro 'oficina'
 *   oficina código de oficina (obligatorio si mode=ofi)
 *   dry     1 = no crea nada, devuelve el payload (con PII redactada)
 *
 * Respuesta JSON: { ok, dedup, shipmentCode, packageCodes, tracking_url, zpl, pdf_b64, error }
 *
 * Ver memoria francobordo_correos_api. Patrón: seur_albaran.php.
 */
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');
@ini_set('max_execution_time', '200');   // > suma de timeouts curl (preregistro + 2 etiquetas)
mysqli_report(MYSQLI_REPORT_OFF);         // PHP 8.1+: no lanzar excepciones; comprobamos retornos a mano

define('CORREOS_ALB_TOKEN', 'correosalb_e7c41f92b5');

$in = array_merge($_GET, $_POST);
function out($arr) { echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

if (($in['token'] ?? '') !== CORREOS_ALB_TOKEN) {
    http_response_code(403);
    out(array('ok' => false, 'error' => 'forbidden'));
}

chdir(__DIR__);
include 'includes/configure.php';
require_once 'includes/classes/correos.php';

$oid    = (int) ($in['oid'] ?? 0);
$kilos  = (float) str_replace(',', '.', (string) ($in['kilos'] ?? '1'));
if ($kilos <= 0) $kilos = 1;
$bultos = max(1, min(10, (int) ($in['bultos'] ?? 1)));
$alb    = trim((string) ($in['albaran'] ?? ''));
$type   = strtoupper(trim((string) ($in['type'] ?? 'BOTH')));
if (!in_array($type, array('ZPL', 'PDF', 'BOTH'), true)) $type = 'BOTH';
$dry    = (($in['dry'] ?? '') === '1');
/* El SERVICIO se resuelve DESPUES de cargar el pedido (bloque "SERVICIO", mas abajo).
 * 'mode'  = legacy: solo lo usan los envios manuales del panel (free=1, sin pedido).
 * 'svc'   = override explicito del panel admin ('dom'|'ofi'); el watcher NO manda servicio. */
$modeIn = strtolower(trim((string) ($in['mode'] ?? '')));
$svcIn  = strtolower(trim((string) ($in['svc'] ?? '')));
$mode   = 'dom';
$oficina = trim((string) ($in['oficina'] ?? ''));
$manual = (($in['manual'] ?? '') === '1');   // pedido QFac-nativo (26xxxxx): direccion en params, no en 'orders'
$free    = (($in['free'] ?? '') === '1');     // ENVIO MANUAL sin pedido: ref propia, orders_id=0, dedup por ref
$freeRef = trim((string) ($in['ref'] ?? ''));
$operator = $free ? 'admin-manual' : 'vstock-watcher';

if (!$free && $oid <= 0) out(array('ok' => false, 'error' => 'oid requerido'));
if ($free && $freeRef === '') out(array('ok' => false, 'error' => 'free=1 requiere ref'));
// La oficina concreta: parametro 'oficina' > la que eligio el cliente (correos_oficina_orders)
// > la mas cercana al CP de destino (localizador publico).

/* =========================================================================
 * Helpers
 * ========================================================================= */

/* Oficina que el cliente eligio en el checkout ('correosoficina' escribe la fila). '' si no hay. */
function correosOficinaElegida($db, $oid) {
    $st = $db->prepare("SELECT office_id FROM correos_oficina_orders WHERE orders_id=? LIMIT 1");
    if (!$st) return '';
    $st->bind_param('i', $oid);
    $st->execute();
    $res = $st->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) $res->free();
    $st->close();
    return $row ? trim((string) $row['office_id']) : '';
}

/* Titulo del envio del pedido (orders_total.ot_shipping). '' si no hay. */
function correosTituloEnvio($db, $oid) {
    $st = $db->prepare("SELECT title FROM orders_total WHERE orders_id=? AND class='ot_shipping' LIMIT 1");
    if (!$st) return '';
    $st->bind_param('i', $oid);
    $st->execute();
    $res = $st->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) $res->free();
    $st->close();
    return $row ? (string) $row['title'] : '';
}

/* Es un pedido web de RECOGIDA EN OFICINA? Tres senales, de mas a menos fiable:
 *   a) fila en correos_oficina_orders  -> el cliente eligio una oficina concreta;
 *   b) orders.shipping_module de un modulo de oficina;
 *   c) el titulo del envio dice "oficina"/"post office" (red de seguridad para pedidos
 *      antiguos cuyo modulo ya no existe o tiene el nombre cambiado).
 * Los nombres de los modulos ENGANAN: 'correosint' es OFICINA nacional; 'correoscert', DOMICILIO. */
function correosPedidoEsOficina($db, $oid, array $o, array $modulos) {
    if (correosOficinaElegida($db, $oid) !== '') return true;
    $mod  = strtolower(trim((string) ($o['shipping_module'] ?? '')));
    $pref = (strpos($mod, '_') !== false) ? substr($mod, 0, strpos($mod, '_')) : $mod;
    if ($pref !== '' && in_array($pref, $modulos, true)) return true;
    $t = correosTituloEnvio($db, $oid);
    return ($t !== '' && preg_match('/oficina|post office/i', $t) && !preg_match('/express/i', $t));
}

/** Quita secuencias UTF-8 de 4 bytes (emoji…) para que el INSERT no falle en
 *  columnas utf8 de 3 bytes bajo el modo STRICT de MySQL/MariaDB. */
function correos_no4b($s) {
    return preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', (string) $s);
}

/** Extrae TODOS los packageCodes del JSON de preregistro guardado en la fila. */
function correos_pkgcodes_from_row(array $row) {
    $codes = array();
    if (!empty($row['response_json'])) {
        $j = json_decode($row['response_json'], true);
        $pk = $j['data']['shipments'][0]['packages'] ?? null;
        if (is_array($pk)) foreach ($pk as $p) if (!empty($p['packageCode'])) $codes[] = (string) $p['packageCode'];
    }
    if (!$codes && !empty($row['package_code'])) $codes = array($row['package_code']);
    return $codes;
}

/** Pide etiquetas (ZPL térmica + PDF respaldo) sobre packageCodes ya creados y
 *  las guarda en disco privado. Idempotente: no crea envíos, solo etiquetas. */
function correos_build_labels($c, array $pkgCodes, $oid, $type) {
    $zpl = null; $pdfBin = null; $errs = array();
    if (in_array($type, array('ZPL', 'BOTH'), true)) {
        $labZ = $c->getLabel($pkgCodes, array('labelFormat' => 3, 'labelPrintMode' => 2));  // 3=ZPL, modo 2=etiquetadora 14,5x10
        if ($labZ['ok'] && !empty($labZ['data']['zpl'])) {
            $z = base64_decode($labZ['data']['zpl'], true);
            if ($z !== false && $z !== '') $zpl = $z;
        } else {
            $errs[] = 'zpl:' . correos::primerError($labZ);
        }
    }
    // PDF SIEMPRE como respaldo de reimpresión (aunque type=ZPL).
    $labP = $c->getLabel($pkgCodes, array('labelFormat' => 2));
    if ($labP['ok'] && !empty($labP['pdf_bin'])) $pdfBin = $labP['pdf_bin'];
    else $errs[] = 'pdf:' . correos::primerError($labP);

    $dir = '/home/francobordo/correos_labels/' . $oid . '/';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $suf = substr(md5($oid . '_' . implode('', $pkgCodes) . '_' . microtime(true)), 0, 8);
    $zplPath = null; $pdfPath = null;
    if ($zpl !== null    && @file_put_contents($dir . "correos_{$oid}_{$suf}.zpl", $zpl)    !== false) $zplPath = $dir . "correos_{$oid}_{$suf}.zpl";
    if ($pdfBin !== null && @file_put_contents($dir . "correos_{$oid}_{$suf}.pdf", $pdfBin) !== false) $pdfPath = $dir . "correos_{$oid}_{$suf}.pdf";
    return array($zplPath, $pdfPath, $zpl, $pdfBin, implode('; ', $errs));
}

/** Ejecuta gs con los argumentos dados. proc_open con array (sin shell): el FPM del web
 *  tiene disable_functions=exec,shell_exec,... pero proc_open SÍ está permitido. */
function correos_gs_run(array $args) {
    if (!function_exists('proc_open') || !is_file('/usr/bin/gs')) return -1;
    $pipes = array();
    $p = @proc_open(array_merge(array('/usr/bin/gs', '-q'), $args),
                    array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    if (!is_resource($p)) return -1;
    @stream_get_contents($pipes[1]); @fclose($pipes[1]);
    @stream_get_contents($pipes[2]); @fclose($pipes[2]);
    return (int) proc_close($p);
}

/**
 * CN23 imprimible en la HP A4 del almacén: extrae la PÁGINA de aduanas del PDF de
 * etiquetas y la convierte a PCL-XL (pxlmono, papel A4) con Ghostscript. La página CN23
 * se detecta por TAMAÑO (595×421, media altura), no por contarlas: un multibulto
 * peninsular tiene N páginas de etiqueta (595×842) y ninguna CN23. Cachea el resultado
 * en <pdf>.cn23.pcl (los reintentos/dedup lo re-sirven sin regenerar). Devuelve la ruta
 * del .pcl o null (sin aduanas / fallo — best-effort, nunca rompe la respuesta).
 */
function correos_cn23_pcl($pdfPath) {
    if (empty($pdfPath) || !is_file($pdfPath)) return null;
    $pcl = $pdfPath . '.cn23.pcl';
    if (is_file($pcl) && filesize($pcl) > 500) return $pcl;
    // ¿La 1ª página es un CN23? (altura < 500pt; las etiquetas miden 842pt). Se extrae la
    // pág.1 con CompatibilityLevel=1.4 (sin object streams → el /MediaBox queda en texto
    // plano) y se busca en el probe entero.
    $probe = $pdfPath . '.probe.pdf';
    if (correos_gs_run(array('-o', $probe, '-sDEVICE=pdfwrite', '-dCompatibilityLevel=1.4', '-dFirstPage=1', '-dLastPage=1', $pdfPath)) !== 0) { @unlink($probe); return null; }
    $head = (string) @file_get_contents($probe);
    @unlink($probe);
    if (!preg_match('/MediaBox\s*\[\s*[\d.]+\s+[\d.]+\s+[\d.]+\s+([\d.]+)/', $head, $m) || (float) $m[1] >= 500) {
        return null;   // sin página CN23 (envío sin aduanas)
    }
    $tmp = $pcl . '.tmp';
    $rc = correos_gs_run(array('-o', $tmp, '-sDEVICE=pxlmono', '-sPAPERSIZE=a4', '-dFIXEDMEDIA', '-dFitPage',
                               '-dFirstPage=1', '-dLastPage=1', $pdfPath));
    if ($rc !== 0 || !is_file($tmp) || filesize($tmp) < 500) { @unlink($tmp); return null; }
    @rename($tmp, $pcl);
    return $pcl;
}

/** Construye la respuesta de éxito a partir de una fila de correos_shipments. */
function correos_resp_from_row(array $row, $type) {
    $resp = array(
        'ok' => true, 'dedup' => true,
        'shipmentCode' => $row['shipment_code'],
        'packageCodes' => correos_pkgcodes_from_row($row),
        'tracking_url' => $row['tracking_url'],
        'zpl' => null, 'pdf_b64' => null, 'cn23_pcl_b64' => null,
    );
    if (in_array($type, array('ZPL', 'BOTH'), true) && !empty($row['label_zpl_path']) && is_file($row['label_zpl_path'])) {
        $resp['zpl'] = file_get_contents($row['label_zpl_path']);
    }
    if (in_array($type, array('PDF', 'BOTH'), true) && !empty($row['label_path']) && is_file($row['label_path'])) {
        $resp['pdf_b64'] = base64_encode(file_get_contents($row['label_path']));
    }
    // CN23 en PCL para la impresora A4 del almacén (solo envíos con aduanas).
    $pcl = correos_cn23_pcl(!empty($row['label_path']) ? $row['label_path'] : null);
    if ($pcl !== null) $resp['cn23_pcl_b64'] = base64_encode(file_get_contents($pcl));
    return $resp;
}

/* =========================================================================
 * Conexión BD
 * ========================================================================= */
$db = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($db->connect_errno) { error_log('correos_albaran db: ' . $db->connect_error); out(array('ok' => false, 'error' => 'db no disponible')); }
$db->set_charset('utf8');

/* ---- Lock por pedido: serializa peticiones concurrentes del mismo oid ---- */
$lockName = $free ? ('corralbF_' . substr(md5($freeRef), 0, 20)) : ('corralb_' . $oid);
$lockEsc  = $db->real_escape_string($lockName);
$lr = $db->query("SELECT GET_LOCK('$lockEsc', 30) AS g");
$got = ($lr && ($lrow = $lr->fetch_assoc()) && (int) $lrow['g'] === 1);
if (!$got) out(array('ok' => false, 'error' => 'otra peticion en curso para este pedido; reintentar'));
register_shutdown_function(function () use ($db, $lockEsc) { @$db->query("SELECT RELEASE_LOCK('$lockEsc')"); });

/* ---- Dedup por orders_id SIEMPRE: 1 pedido = 1 preregistro N-bultos.
 *      Considera filas con shipment_code (ok=1 ya etiquetada, u ok=0 a medio
 *      etiquetar) que NO estén anuladas. ---- */
$prev = null;
if ($free) {
    // dedup por ref (orders_id=0): un re-submit con la misma ref devuelve el envio ya creado.
    $st = $db->prepare("SELECT * FROM correos_shipments
                        WHERE tipo='envio' AND orders_id=0 AND ref=? AND cancelled_at IS NULL
                          AND shipment_code IS NOT NULL AND shipment_code<>''
                        ORDER BY id DESC LIMIT 1");
    $st->bind_param('s', $freeRef);
    $st->execute();
    $prev = $st->get_result()->fetch_assoc();
} else {
    $st = $db->prepare("SELECT * FROM correos_shipments
                        WHERE orders_id=? AND tipo='envio' AND cancelled_at IS NULL
                          AND shipment_code IS NOT NULL AND shipment_code<>''
                        ORDER BY id DESC LIMIT 1");
    $st->bind_param('i', $oid);
    $st->execute();
    $prev = $st->get_result()->fetch_assoc();
}

if ($prev) {
    // Ya existe el envío en Correos. NUNCA re-preregistrar.
    $haveZpl = !empty($prev['label_zpl_path']) && is_file($prev['label_zpl_path']);
    $havePdf = !empty($prev['label_path'])     && is_file($prev['label_path']);
    $needZpl = in_array($type, array('ZPL', 'BOTH'), true) && !$haveZpl;
    $needPdf = in_array($type, array('PDF', 'BOTH'), true) && !$havePdf;

    if (!$needZpl && !$needPdf) {
        out(correos_resp_from_row($prev, $type));   // etiqueta(s) ya disponible(s)
    }

    // Reintento idempotente: regenerar SOLO etiquetas sobre los packageCodes ya creados.
    $pkgCodes = correos_pkgcodes_from_row($prev);
    if (!$pkgCodes) out(array('ok' => false, 'dedup' => true, 'error' => 'fila previa sin packageCodes recuperables', 'shipmentCode' => $prev['shipment_code']));
    $c = new correos('pro');
    $c->setTimeout(60);
    list($zplPath, $pdfPath, $zpl, $pdfBin, $labErr) = correos_build_labels($c, $pkgCodes, $oid, $type);
    // No machacar un path bueno previo con NULL.
    $newZpl = $zplPath ?: ($haveZpl ? $prev['label_zpl_path'] : null);
    $newPdf = $pdfPath ?: ($havePdf ? $prev['label_path'] : null);
    $labelOk = ($newZpl || $newPdf);
    $fmt = ($newZpl && $newPdf) ? 'both' : ($newZpl ? 'zpl' : ($newPdf ? 'pdf' : ''));
    $okVal = $labelOk ? 1 : (int) $prev['ok'];
    $msg = correos_no4b(mb_substr('relabel; ' . $labErr, 0, 480));
    $up = $db->prepare("UPDATE correos_shipments SET label_zpl_path=?, label_path=?, label_format=?, ok=?, mensaje_retorno=? WHERE id=?");
    $rowId = (int) $prev['id'];
    $up->bind_param('sssisi', $newZpl, $newPdf, $fmt, $okVal, $msg, $rowId);
    $up->execute();

    if ($labelOk) {
        $row2 = $prev;
        $row2['label_zpl_path'] = $newZpl; $row2['label_path'] = $newPdf;
        out(correos_resp_from_row($row2, $type));
    }
    out(array('ok' => false, 'dedup' => true, 'error' => 'envio ya creado pero etiqueta falla; reintentar', 'labelError' => $labErr,
              'shipmentCode' => $prev['shipment_code']));
}

/* ---- Datos de ENTREGA ----
 * MODO MANUAL (pedidos QFac-nativos serie 26xxxxx, NO están en `orders`): el watcher
 * pasa manual=1 + la dirección leída de Vstock PEDIDOS_CLIENTES
 * (dname/dstreet/dcp/dcity/dstate/dcountry/dphone/demail). ref = Q{oid}. Patrón SEUR. */
if ($manual || $free) {
    $o = array(
        'orders_id'               => $oid,
        'delivery_name'           => trim((string) ($in['dname'] ?? '')),
        'delivery_company'        => '',
        'delivery_street_address' => trim((string) ($in['dstreet'] ?? '')),
        'delivery_suburb'         => '',
        'delivery_city'           => trim((string) ($in['dcity'] ?? '')),
        'delivery_postcode'       => trim((string) ($in['dcp'] ?? '')),
        'delivery_state'          => trim((string) ($in['dstate'] ?? '')),
        'delivery_country'        => trim((string) ($in['dcountry'] ?? 'ESP')),
        'customers_telephone'     => trim((string) ($in['dphone'] ?? '')),
        'customers_email_address' => trim((string) ($in['demail'] ?? '')),
    );
    if ($o['delivery_name'] === '' || $o['delivery_street_address'] === '' || $o['delivery_postcode'] === '') {
        out(array('ok' => false, 'error' => ($free ? 'free=1' : 'manual=1') . ' requiere dname, dstreet y dcp'));
    }
} else {
    $st = $db->prepare("SELECT orders_id, delivery_name, delivery_company, delivery_street_address, delivery_suburb,
                               delivery_city, delivery_postcode, delivery_state, delivery_country,
                               customers_telephone, customers_email_address, shipping_module
                        FROM orders WHERE orders_id=?");
    $st->bind_param('i', $oid);
    $st->execute();
    $o = $st->get_result()->fetch_assoc();
    if (!$o) out(array('ok' => false, 'error' => "pedido $oid no encontrado en la web"));
}

/* País → ISO-3 (Correos exige alfa-3: ESP...). orders.delivery_country guarda el NOMBRE;
 * QFac/manual pueden mandar ya el alfa-3 ('ITA') o el alfa-2 ('IT'). Sin país => nacional.
 * NUNCA se adivina: un país que no resuelve ABORTA. Con el 'ESP' por defecto de antes, un
 * nombre no reconocido creaba una etiqueta NACIONAL con dirección extranjera (ya facturada). */
/* Normaliza un nombre de pais: mayusculas, sin acentos ni separadores. 'Países Bajos' => 'PAISESBAJOS' */
function fb_norm_pais($s) {
    $s = mb_strtoupper(trim((string) $s), 'UTF-8');
    $s = strtr($s, array('Á'=>'A','À'=>'A','Ä'=>'A','Â'=>'A','Ã'=>'A','É'=>'E','È'=>'E','Ë'=>'E','Ê'=>'E',
                         'Í'=>'I','Ì'=>'I','Ï'=>'I','Î'=>'I','Ó'=>'O','Ò'=>'O','Ö'=>'O','Ô'=>'O','Õ'=>'O',
                         'Ú'=>'U','Ù'=>'U','Ü'=>'U','Û'=>'U','Ñ'=>'N','Ç'=>'C'));
    return preg_replace('/[^A-Z]/', '', $s);
}
/* Una sola fila; cierra el stmt (mezclar prepare() vivo con query() da 'Commands out of sync'). */
function fb_pais_col($db, $sql, $val) {
    $st = $db->prepare($sql);
    if (!$st) return '';
    $st->bind_param('s', $val);
    $st->execute();
    $res = $st->get_result();
    $row = $res ? $res->fetch_row() : null;
    if ($res) $res->free();
    $st->close();
    return $row ? (string) $row[0] : '';
}
/* `countries` arrastra alfa-3 anteriores a 2002; Correos exige el vigente. Antiguo => vigente. */
$ISO3_LEGACY = array('ROM' => 'ROU');
/* Existe este alfa-3 vigente en `countries`? Acepta tambien su equivalente antiguo. */
function fb_iso3_existe($db, $iso3, $legacy) {
    $sql = "SELECT countries_iso_code_3 FROM countries WHERE countries_iso_code_3=? LIMIT 1";
    if (fb_pais_col($db, $sql, $iso3) !== '') return true;
    $viejo = array_search($iso3, $legacy, true);   // 'ROU' => 'ROM'
    return $viejo !== false && fb_pais_col($db, $sql, $viejo) !== '';
}
/* Alias castellano -> ISO-3 para los nombres que QFac escribe a mano (PCL_PAIS) y que no estan
 * en `countries`. Clave = nombre normalizado. Solo se consulta si fallan ISO-3/ISO-2/nombre. */
$PAIS_ALIAS = array(
    'ESPANA'=>'ESP','ESPAA'=>'ESP','ALEMANIA'=>'DEU','ITALIA'=>'ITA','FRANCIA'=>'FRA','PORTUGAL'=>'PRT',
    'REINOUNIDO'=>'GBR','GRANBRETANA'=>'GBR','INGLATERRA'=>'GBR','UK'=>'GBR',
    'PAISESBAJOS'=>'NLD','HOLANDA'=>'NLD','BELGICA'=>'BEL','IRLANDA'=>'IRL','FINLANDIA'=>'FIN',
    'CROACIA'=>'HRV','ESLOVENIA'=>'SVN','ESLOVAQUIA'=>'SVK','LUXEMBURGO'=>'LUX','HUNGRIA'=>'HUN',
    'RUMANIA'=>'ROU','SUECIA'=>'SWE','POLONIA'=>'POL','LITUANIA'=>'LTU','LETONIA'=>'LVA',
    'ESTONIA'=>'EST','DINAMARCA'=>'DNK','GRECIA'=>'GRC','CHIPRE'=>'CYP','BULGARIA'=>'BGR','MALTA'=>'MLT',
    'REPUBLICACHECA'=>'CZE','CHEQUIA'=>'CZE','AUSTRIA'=>'AUT','ANDORRA'=>'AND','MONACO'=>'MCO',
    'SUIZA'=>'CHE','NORUEGA'=>'NOR','ISLANDIA'=>'ISL','TURQUIA'=>'TUR','UCRANIA'=>'UKR','RUSIA'=>'RUS',
    'SERBIA'=>'SRB','BOSNIAYHERZEGOVINA'=>'BIH','ALBANIA'=>'ALB','MACEDONIA'=>'MKD','MONTENEGRO'=>'MNE',
    'ESTADOSUNIDOS'=>'USA','EEUU'=>'USA','CANADA'=>'CAN','MEXICO'=>'MEX','BRASIL'=>'BRA','PERU'=>'PER',
    'PANAMA'=>'PAN','REPUBLICADOMINICANA'=>'DOM','COLOMBIA'=>'COL','ARGENTINA'=>'ARG','CHILE'=>'CHL',
    'SUDAFRICA'=>'ZAF','MARRUECOS'=>'MAR','TANZANIA'=>'TZA','MAURICIO'=>'MUS','CABOVERDE'=>'CPV',
    'MALDIVAS'=>'MDV','REPUBLICOFMALDIVES'=>'MDV','JAPON'=>'JPN','CHINA'=>'CHN','AUSTRALIA'=>'AUS',
    'NUEVAZELANDA'=>'NZL','EMIRATOSARABESUNIDOS'=>'ARE','ARABIASAUDI'=>'SAU','EGIPTO'=>'EGY',
    'TUNEZ'=>'TUN','ARGELIA'=>'DZA','ISRAEL'=>'ISR','INDIA'=>'IND','TAILANDIA'=>'THA',
);

$cty  = trim((string) $o['delivery_country']);
$iso3 = '';
if ($cty === '') {
    $iso3 = 'ESP';                                       // sin pais => nacional
} elseif (preg_match('/^[A-Za-z]{3}$/', $cty)) {
    $up = strtoupper($cty);
    if (isset($ISO3_LEGACY[$up])) $up = $ISO3_LEGACY[$up];     // entrada con alfa-3 antiguo ('ROM')
    if (fb_iso3_existe($db, $up, $ISO3_LEGACY)) $iso3 = $up;
} elseif (preg_match('/^[A-Za-z]{2}$/', $cty)) {
    $iso3 = strtoupper(fb_pais_col($db, "SELECT countries_iso_code_3 FROM countries WHERE countries_iso_code_2=? LIMIT 1", $cty));
} else {
    $iso3 = strtoupper(fb_pais_col($db, "SELECT countries_iso_code_3 FROM countries WHERE countries_name=? LIMIT 1", $cty));
}
if ($iso3 === '' && $cty !== '') {
    $norm = fb_norm_pais($cty);
    if ($norm !== '') {
        // Nombre de la tienda comparado en normalizado (tolera mayusculas/acentos: 'ITALY', 'ESPAÑA').
        if ($res = $db->query("SELECT countries_name, countries_iso_code_3 FROM countries WHERE countries_iso_code_3 <> ''")) {
            while ($row = $res->fetch_assoc()) {
                if (fb_norm_pais($row['countries_name']) === $norm) { $iso3 = strtoupper($row['countries_iso_code_3']); break; }
            }
            $res->free();
        }
        // Alias en castellano (QFac). Se valida contra `countries` para no colar un ISO-3 inventado.
        if ($iso3 === '' && isset($PAIS_ALIAS[$norm])) {
            $cand = $PAIS_ALIAS[$norm];
            if (fb_iso3_existe($db, $cand, $ISO3_LEGACY)) $iso3 = $cand;
        }
    }
}
if (isset($ISO3_LEGACY[$iso3])) $iso3 = $ISO3_LEGACY[$iso3];   // 'ROM' (tabla) => 'ROU' (Correos)
if (!preg_match('/^[A-Z]{3}$/', $iso3)) {
    out(array('ok' => false, 'error' => "pedido $oid: pais destino no reconocido ('$cty'); usa el nombre exacto de la tienda o el codigo ISO (ESP, DEU, IT...)"));
}
/* Nacional vs INTERNACIONAL. Internacional = Paq Estandar Internacional (PAAXI, S0410 del Anexo I),
 * SOLO entrega a domicilio (DOUAOF): no hay recogida en oficina fuera de Espana. */
$esES     = ($iso3 === 'ESP');
$producto = $esES ? 'PAFXB' : 'PAAXI';
if (!$esES) $oficina = '';   // internacional: solo domicilio (se fija en el bloque SERVICIO)

/* Nombre/contacto de destino con fallback simétrico (M8) */
$destName = trim((string) $o['delivery_name']);
if ($destName === '') $destName = trim((string) $o['delivery_company']);
if ($destName === '') out(array('ok' => false, 'error' => "pedido $oid sin nombre ni empresa de entrega"));

$cp   = trim($o['delivery_postcode']);
$prov = preg_match('/^\d{5}$/', $cp) ? substr($cp, 0, 2) : '';
if (!$esES) $prov = '';   // provincia = codigo Anexo V espanol; no aplica a destinos internacionales
$gramos = (int) round($kilos * 1000);
$ref = $free ? $freeRef : (($manual ? 'Q' : 'F') . $oid);   // free=ref propia, Q{oid}=QFac, F{oid}=web

/* =========================================================================
 * SERVICIO: domicilio (DOUAOF) vs recogida en oficina (OFUAOF).
 * Lo decide el PEDIDO, no la agencia que el operario elige en el PDA de Vstock
 * (mismo criterio que cex_albaran.php con cex_pudo_orders). Por eso el 'mode' que
 * mandaba el watcher se IGNORA en cuanto hay pedido: la agencia ya no decide nada.
 * ========================================================================= */
$OFICINA_MODULES = array('correosoficina', 'correos', 'correosint',
                         'correospaespbal', 'correospaespceutamel', 'correosmad');
$ofiExplicita = false;   // la oficina la pidio un humano (panel), no se dedujo del pedido
$svcDegradado = '';
if (!$esES) {
    $mode = 'dom'; $oficina = '';                            // internacional: solo domicilio
} elseif ($oficina !== '') {
    $mode = 'ofi'; $ofiExplicita = true;                     // oficina explicita (panel)
} elseif ($svcIn === 'ofi' || $svcIn === 'dom') {
    $mode = $svcIn; $ofiExplicita = ($svcIn === 'ofi');      // override explicito del panel admin
} elseif ($free) {
    $mode = ($modeIn === 'ofi') ? 'ofi' : 'dom';             // envio manual sin pedido: manda el operario
    $ofiExplicita = ($mode === 'ofi');
} elseif (!$manual && $oid > 0) {
    $mode = correosPedidoEsOficina($db, $oid, $o, $OFICINA_MODULES) ? 'ofi' : 'dom';
} else {
    $mode = 'dom';                                           // QFac-nativo: no hay pedido web que consultar
}

/* Que oficina: la que eligio el cliente y, si el pedido es de un modulo antiguo sin
 * selector (o el panel pidio oficina sin darla), la mas cercana al CP de destino. */
if ($mode === 'ofi' && $oficina === '' && !$free && !$manual && $oid > 0) {
    $oficina = correosOficinaElegida($db, $oid);
}
if ($mode === 'ofi' && $oficina === '' && preg_match('/^\d{5}$/', $cp)) {
    require_once __DIR__ . '/includes/modules/shipping/correosoficina.php';
    $aOfis = correosoficina::oficinas($cp, trim((string) $o['delivery_city']), 1);
    if (!empty($aOfis[0]['id'])) $oficina = (string) $aOfis[0]['id'];
}
if ($mode === 'ofi' && $oficina === '') {
    if ($ofiExplicita) {
        // Alguien pidio oficina a proposito: no se cambia el servicio a su espalda.
        out(array('ok' => false, 'error' => "servicio=oficina: no se pudo determinar la oficina del pedido $oid (ni parametro, ni correos_oficina_orders, ni auto por CP $cp)"));
    }
    /* Servicio DEDUCIDO del pedido (modulo/titulo antiguos, sin oficina elegida) y el
     * localizador no da ninguna para el CP. Abortar dejaria el albaran atascado hasta el
     * GIVEUP del watcher y ya no existe la valvula de elegir la otra agencia en el PDA.
     * Se etiqueta a DOMICILIO (el paquete llega igual) y se avisa en la respuesta. */
    $mode = 'dom';
    $svcDegradado = "pedido $oid: es de recogida en oficina pero no hay oficina resoluble para el CP $cp; se etiqueta a DOMICILIO";
    error_log('correos_albaran: ' . $svcDegradado);
}
$deliveryMethod = ($mode === 'ofi') ? 'OFUAOF' : 'DOUAOF';   // servicio deducido del pedido

/* Bultos: peso repartido; el último absorbe el resto para que la suma == total (M4) */
$packages = array();
$base = intdiv($gramos, $bultos);
if ($base < 1) $base = 1;
$acumulado = 0;
for ($i = 1; $i <= $bultos; $i++) {
    $w = ($i < $bultos) ? $base : max(1, $gramos - $acumulado);
    $acumulado += $w;
    $packages[] = array('packageId' => (string) $i, 'packageWeightGrams' => (string) $w);
}
$totalWeight = array_sum(array_map(function ($p) { return (int) $p['packageWeightGrams']; }, $packages));

/* ADUANAS: Canarias (35/38), Ceuta (51), Melilla (52) exigen packageContents (DUA) o el
 * preregistro falla con 6069. Construimos los items (customsData) de los productos del pedido. */
$prov2 = ($prov !== '') ? $prov : substr($cp, 0, 2);
/* Aduanas: territorios espanoles fuera de la union aduanera (Canarias 35/38, Ceuta 51, Melilla 52)
 * O destino internacional FUERA de la UE-27 (Suiza, Reino Unido, EE.UU...). Intra-UE no lleva aduanas. */
$UE27_ISO3 = array('AUT','BEL','BGR','HRV','CYP','CZE','DNK','EST','FIN','FRA','DEU','GRC','HUN','IRL','ITA',
                   'LVA','LTU','LUX','MLT','NLD','POL','PRT','ROU','SVK','SVN','ESP','SWE');
$necesitaAduanas = $esES ? in_array($prov2, array('35', '38', '51', '52'), true)
                         : !in_array($iso3, $UE27_ISO3, true);
if ($necesitaAduanas) {
    /* La declaracion se adjunta al bulto 0 (packageContents): con varios bultos, los 2..N viajarian
     * SIN declaracion. Se corta antes de crear la etiqueta, que ya seria facturable. Aplica a todas
     * las rutas (pedido web, free y manual), no solo a free como hasta ahora. */
    if ($bultos > 1) {
        out(array('ok' => false, 'error' => "destino con aduanas ($iso3 $cp): usa un solo bulto (la declaracion DUA/CN23 debe ir completa en un paquete)"));
    }
    if ($manual) {
        out(array('ok' => false, 'error' => "destino aduanas ($cp) en pedido QFac-nativo $oid: requiere declaracion de aduanas con valor de mercancia (no disponible en modo manual); etiquetar a mano"));
    }
    $items = array(); $totVal = 0.0;
    if ($free) {
        // Envio manual sin pedido: declaracion DUA con el valor + contenido del formulario.
        $rawDval = trim((string) ($in['dvalue'] ?? ''));
        if (!preg_match('/^\d{1,9}([.,]\d{1,2})?$/', $rawDval)) out(array('ok' => false, 'error' => "destino aduanas ($cp): 'dvalue' invalido; usa formato 1234.56 (sin separador de miles)"));
        $val = round((float) str_replace(',', '.', $rawDval), 2);
        if ($val <= 0) out(array('ok' => false, 'error' => "destino aduanas ($cp): el envio manual requiere 'dvalue' (valor declarado EUR, >0) para la declaracion DUA"));
        $dsc = mb_substr(correos_no4b(trim((string) ($in['ddesc'] ?? ''))), 0, 60);
        if ($dsc === '') $dsc = 'Articulos nauticos';
        $items[] = array('quantity' => '1', 'description' => $dsc, 'netWeight' => (string) max(1, $gramos), 'netValue' => number_format($val, 2, '.', ''), 'tariffNumber' => correos::TARIFA_ADUANA_DEF);
        $totVal = $val;
    } else {
    /* El peso viene del maestro `products` (orders_products NO tiene products_weight; la
     * query antigua fallaba en silencio y SIEMPRE se declaraba el item generico de respaldo). */
    $rp = $db->query("SELECT op.products_name, op.products_quantity, op.products_price, op.final_price, p.products_weight
                      FROM orders_products op LEFT JOIN products p ON p.products_id = op.products_id
                      WHERE op.orders_id=" . (int) $oid);
    while ($rp && ($p = $rp->fetch_assoc())) {
        $qty  = max(1, (int) $p['products_quantity']);
        $unit = ((float) $p['products_price'] > 0) ? (float) $p['products_price'] : (float) $p['final_price'];
        $val  = round($unit * $qty, 2);
        $nw   = max(1, (int) round(((float) $p['products_weight']) * 1000 * $qty));
        $desc = mb_substr(correos_no4b(trim((string) $p['products_name'])), 0, 60);
        if ($desc === '') $desc = 'Articulo';
        $items[] = array('quantity' => (string) $qty, 'description' => $desc, 'netWeight' => (string) $nw, 'netValue' => number_format($val, 2, '.', ''), 'tariffNumber' => correos::TARIFA_ADUANA_DEF);
        $totVal += $val;
    }
    if (!$items) {
        $tot = 0.0;
        $rt = $db->query("SELECT value FROM orders_total WHERE orders_id=" . (int) $oid . " AND class='ot_total' LIMIT 1");
        if ($rt && ($row = $rt->fetch_assoc())) $tot = (float) $row['value'];
        if ($tot <= 0) $tot = 1.0;
        $items[] = array('quantity' => '1', 'description' => 'Articulos nauticos', 'netWeight' => (string) max(1, $gramos), 'netValue' => number_format($tot, 2, '.', ''), 'tariffNumber' => correos::TARIFA_ADUANA_DEF);
        $totVal = $tot;
    }
    }   // fin else (no free)
    /* 6015 "El peso de los articulos es mayor que el peso total del envio": el neto declarado
     * no puede superar el peso del envio (el watcher declara 1 kg por defecto y los items
     * llevan peso real x cantidad). Si la suma se pasa, se reescala a prorrata. */
    $sumNw = 0;
    foreach ($items as $it) $sumNw += (int) $it['netWeight'];
    if ($sumNw > $gramos) {
        foreach ($items as $k => $it) {
            $items[$k]['netWeight'] = (string) max(1, (int) floor(((int) $it['netWeight']) * $gramos / $sumNw));
        }
    }
    // packageContents es por paquete; declaramos todo el contenido en el primer bulto.
    $packages[0]['packageContents'] = array(
        'shipmentType'            => '2',   // Mercancias
        'indDangerousGoods'       => 'N',
        'indCommercialDelivery'   => 'S',
        'indInvoiceExceedsAmount' => ($totVal > 500 ? 'S' : 'N'),
        'indDUAwithCorreos'       => 'S',   // Correos gestiona el DUA de exportacion
        'customsData'             => $items,
    );
}

$addressee = array(
    'name'          => $destName,
    'company'       => trim((string) $o['delivery_company']),
    'address'       => trim($o['delivery_street_address'] . ' ' . (string) $o['delivery_suburb']),
    'locality'      => trim($o['delivery_city']),
    'cp'            => $cp,
    'province'      => $prov,
    'country'       => $iso3,
    'contactPerson' => $destName,
    'contactPhone'  => trim((string) $o['customers_telephone']),
    'email'         => trim((string) $o['customers_email_address']),
    'language'      => 'spa',
);
if ($mode === 'ofi') $addressee['chosenOffice'] = $oficina;

$shipment = array(
    'product'        => $producto,        // PAFXB nacional / PAAXI internacional (Paq Estandar Int.)
    'deliveryMethod' => $deliveryMethod,  // dom=DOUAOF / ofi=OFUAOF (param mode)
    'contractNumber' => correos::CONTRACT,
    'clientNumber'   => correos::CLIENT_NUMBER,
    'labellerCode'   => correos::LABELLER,
    'packagesNumber' => (string) $bultos,
    'totalWeight'    => (string) $totalWeight,
    'shipmentReference1' => $ref,
    'sender' => array(
        'name'          => correos::FB_NOMBRE,
        'company'       => correos::FB_NOMBRE,
        'address'       => correos::FB_DIR,
        'locality'      => correos::FB_POBL,
        'cp'            => correos::FB_CP,
        'province'      => correos::FB_PROV,
        'country'       => correos::FB_PAIS_ISO,
        'contactPerson' => correos::FB_CONTACTO,
        'contactPhone'  => correos::FB_TLFNO,
        'email'         => correos::FB_EMAIL,
        'doiType'       => '10',
        'doiNumber'     => correos::FB_NIF,
        'language'      => 'spa',
    ),
    'addressee' => $addressee,
    'packages'  => $packages,
);

if ($dry) {
    // No volcar PII en pruebas (el watcher --dry solo necesita la estructura).
    $safe = $shipment;
    foreach (array('name', 'company', 'address', 'contactPerson', 'contactPhone', 'email') as $k) {
        if (!empty($safe['addressee'][$k])) $safe['addressee'][$k] = '***';
    }
    out(array('ok' => true, 'dry' => true, 'servicio' => ($mode === 'ofi' ? 'oficina' : 'domicilio'),
              'oficina' => ($mode === 'ofi' ? $oficina : null),
              'aviso' => ($svcDegradado !== '' ? $svcDegradado : null), 'payload' => $safe));
}

/* ---- Preregistro ---- */
$c = new correos('pro');
$c->setTimeout(60);
$pre = $c->preregister(array($shipment));
$reqShip = $c->lastRequest;

$sh = $pre['data']['shipments'][0] ?? null;
$code = ($pre['ok'] && $sh && empty($sh['error'])) ? (string) ($sh['shipmentCode'] ?? '') : '';
$pkgCodes = array();
if ($code !== '' && !empty($sh['packages'])) {
    foreach ($sh['packages'] as $p) if (!empty($p['packageCode'])) $pkgCodes[] = (string) $p['packageCode'];
}
if ($code === '' && $sh && !empty($sh['shipmentCode'])) {
    error_log('correos_albaran oid=' . $oid . ' preregistro con error parcial, shipmentCode=' . $sh['shipmentCode'] . ' err=' . json_encode($sh['error'] ?? null));
}

if ($code === '' || !$pkgCodes) {
    $err = correos::primerError($pre);
    $st = $db->prepare("INSERT INTO correos_shipments (id_rma, orders_id, albaran_id, tipo, entorno, producto, ref, kilos,
                          http_code, mensaje_retorno, ok, request_json, response_json, operator, date_added)
                        VALUES (0,?,?,'envio','pro','" . $producto . "',?,?,?,?,0,?,?, '" . $operator . "', NOW())");
    $http   = (string) $pre['http'];
    $req    = correos_no4b(json_encode($reqShip, JSON_UNESCAPED_UNICODE));
    $errCut = correos_no4b(mb_substr((string) $err, 0, 480));
    $raw    = correos_no4b((string) $pre['raw']);
    $st->bind_param('issdssss', $oid, $alb, $ref, $kilos, $http, $errCut, $req, $raw);
    $st->execute();
    out(array('ok' => false, 'error' => $err, 'http' => $pre['http']));
}

/* ---- PERSISTENCIA TEMPRANA (B1): el envío YA existe en Correos. Guardar la fila
 *      AHORA (ok=0, sin etiqueta) para que cualquier muerte posterior no provoque
 *      un segundo preregistro. response_json lleva todos los packageCodes. ---- */
$trackingUrl = 'https://www.correos.es/es/es/herramientas/localizador/envios/detalle?tracking-number=' . rawurlencode($pkgCodes[0]);
$http = (string) $pre['http'];
$req  = correos_no4b(json_encode($reqShip, JSON_UNESCAPED_UNICODE));
$raw  = correos_no4b((string) $pre['raw']);
$pkg0 = $pkgCodes[0];
$ins = $db->prepare("INSERT INTO correos_shipments (id_rma, orders_id, albaran_id, tipo, entorno, shipment_code, package_code,
                       producto, ref, kilos, tracking_url, http_code, mensaje_retorno, ok, request_json, response_json, operator, date_added)
                     VALUES (0,?,?,'envio','pro',?,?,'" . $producto . "',?,?,?,?,'',0,?,?, '" . $operator . "', NOW())");
$ins->bind_param('issssdssss', $oid, $alb, $code, $pkg0, $ref, $kilos, $trackingUrl, $http, $req, $raw);
if (!$ins->execute()) {
    error_log('correos_albaran oid=' . $oid . ' INSERT temprano fallo: ' . $db->error . ' shipmentCode=' . $code);
    out(array('ok' => false, 'error' => 'preregistro creado pero no se pudo persistir (revisar manualmente, shipmentCode=' . $code . ')',
              'shipmentCode' => $code, 'packageCodes' => $pkgCodes));
}
$rowId = (int) $db->insert_id;

/* ---- Etiquetas: ZPL (térmica) + PDF (respaldo). ---- */
list($zplPath, $pdfPath, $zpl, $pdfBin, $labErr) = correos_build_labels($c, $pkgCodes, $oid, $type);
$labelOk = ($zplPath || $pdfPath);
$fmt = ($zplPath && $pdfPath) ? 'both' : ($zplPath ? 'zpl' : ($pdfPath ? 'pdf' : ''));
$okVal = $labelOk ? 1 : 0;
$msg = correos_no4b(mb_substr((string) $labErr, 0, 480));
$up = $db->prepare("UPDATE correos_shipments SET label_zpl_path=?, label_path=?, label_format=?, ok=?, mensaje_retorno=? WHERE id=?");
$up->bind_param('sssisi', $zplPath, $pdfPath, $fmt, $okVal, $msg, $rowId);
$up->execute();

if (!$labelOk) {
    out(array('ok' => false, 'dedup' => false, 'error' => 'preregistro OK pero la etiqueta falla; reintentar (idempotente)',
              'labelError' => $labErr, 'shipmentCode' => $code, 'packageCodes' => $pkgCodes, 'tracking_url' => $trackingUrl));
}

$cn23 = correos_cn23_pcl($pdfPath);   // CN23→PCL para la HP A4 del almacén (null si no hay aduanas)
out(array(
    'ok' => true, 'dedup' => false,
    'shipmentCode' => $code, 'packageCodes' => $pkgCodes,
    'tracking_url' => $trackingUrl,
    'servicio' => ($mode === 'ofi' ? 'oficina' : 'domicilio'),
    'aviso'    => ($svcDegradado !== '' ? $svcDegradado : null),
    'zpl'     => in_array($type, array('ZPL', 'BOTH'), true) ? $zpl : null,
    'pdf_b64' => in_array($type, array('PDF', 'BOTH'), true) && $pdfBin !== null ? base64_encode($pdfBin) : null,
    'cn23_pcl_b64' => ($cn23 !== null ? base64_encode(file_get_contents($cn23)) : null),
));
