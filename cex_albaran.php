<?php
/**
 * Endpoint de integración Vstock -> Correos Express (salida de pedidos).
 *
 * Lo llama el detector de albaranes (cex_vstock_watcher en el .112) cuando el
 * operario finaliza un albaran en Vstock con la agencia "C.Express" (domicilio)
 * o "C.Express Punto" (punto de recogida). Por cada uno:
 *   1. Crea el envio en CEX via API (clase includes/classes/correos_express.php,
 *      grabacionEnvio) con etiqueta ZPL (tipoEtiqueta=2 -> campo etiqueta2).
 *   2. Guarda el registro en cex_shipments y devuelve la etiqueta ZPL.
 *   3. El watcher imprime el ZPL en la impresora CorreosExpressVSTK (puerto 9100).
 *
 * Autodeteccion (no hace falta que el watcher lo diga):
 *   - Si el pedido tiene fila en cex_pudo_orders  -> PUNTO: producto 18 + idPtoExterno.
 *   - Si no, DOMICILIO: producto 93 (ePaq24). Si orders.shipping_module es el de
 *     sabado (tipsawednesday_tipsawednesday) -> entrSabado=S.
 *   - svc=sabado / svc=punto fuerzan el comportamiento (override del watcher).
 *
 * Standalone (no pasa por application_top): configure.php + mysqli + clase CEX.
 * Protegido por token. Entorno CEX segun cex_config (test/pro), como el modulo RMA.
 *
 * Parametros (GET o POST):
 *   token   (obligatorio)
 *   oid     (obligatorio salvo free) orders_id del pedido web (o QFac con manual=1)
 *   kilos   peso TOTAL en kg (def. 1)
 *   bultos  nº de bultos (def. 1)
 *   albaran id del albaran Vstock (ALB_ID) — para dedup e historico
 *   type    ZPL | PDF | BOTH (def. BOTH) — CEX solo da un formato/llamada: damos ZPL
 *   svc     sabado | punto (override; normalmente se autodetecta)
 *   manual  1 = pedido QFac-nativo: direccion en dname/dstreet/dcp/dcity/dstate/dcountry/dphone/demail
 *   free    1 = envio manual sin pedido (ref propia, orders_id=0, sin dedup)
 *   dry     1 = no crea nada, devuelve el payload que se enviaria
 *
 * Respuesta JSON: { ok, dedup, env, shipmentCode, ecb, ref, tracking_url, zpl, error }
 * Ver memoria francobordo_correos_express_api.
 */
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING);
ini_set('display_errors', '0');

define('CEX_ALB_TOKEN', 'cexalb_8b3e6d1f4a92');

$in = array_merge($_GET, $_POST);
function out($arr) { echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

if (($in['token'] ?? '') !== CEX_ALB_TOKEN) {
    http_response_code(403);
    out(array('ok' => false, 'error' => 'forbidden'));
}

chdir(__DIR__);
include 'includes/configure.php';
require_once 'includes/classes/correos_express.php';

/* ---- Modo REIMPRESION: el watcher recoge los ZPL encolados desde el panel admin ---- */
if (($in['reprints'] ?? '') === '1') {
    $db = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
    if ($db->connect_errno) out(array('ok' => false, 'error' => 'db'));
    $db->set_charset('utf8mb4');
    $resq = array();
    $r = $db->query("SELECT id, orders_id, zpl FROM cex_reprint_queue WHERE done = 0 ORDER BY id LIMIT 20");
    while ($row = $r->fetch_assoc()) {
        $resq[] = array('id' => (int) $row['id'], 'oid' => (int) $row['orders_id'], 'zpl' => $row['zpl']);
    }
    out(array('ok' => true, 'reprints' => $resq));
}
/* ---- ACK del watcher: marca done los reprints ya soltados ---- */
if (($in['reprint_done'] ?? '') !== '') {
    $db = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
    if ($db->connect_errno) out(array('ok' => false, 'error' => 'db'));
    $ids = array_values(array_filter(array_map('intval', explode(',', (string) $in['reprint_done']))));
    if ($ids) {
        $in_ids = implode(',', $ids);
        $db->query("UPDATE cex_reprint_queue SET done = 1, done_at = NOW() WHERE id IN ($in_ids)");
    }
    out(array('ok' => true, 'acked' => $ids));
}

$oid    = (int) ($in['oid'] ?? 0);
$kilos  = (float) str_replace(',', '.', (string) ($in['kilos'] ?? '1'));
if ($kilos <= 0) $kilos = 1;
$bultos = max(1, (int) ($in['bultos'] ?? 1));
$alb    = trim((string) ($in['albaran'] ?? ''));
$type   = strtoupper(trim((string) ($in['type'] ?? 'BOTH')));
if (!in_array($type, array('ZPL', 'PDF', 'BOTH'), true)) $type = 'BOTH';
$dry    = (($in['dry'] ?? '') === '1');
$svc    = strtolower(trim((string) ($in['svc'] ?? '')));
$regen  = (($in['regen'] ?? '') === '1');  // "modificar envío" desde el panel: salta dedup + acepta overrides de dirección

$free    = (($in['free'] ?? '') === '1');
$freeRef = trim((string) ($in['ref'] ?? ''));
if (!$free && $oid <= 0) out(array('ok' => false, 'error' => 'oid requerido'));
if ($free && $freeRef === '') out(array('ok' => false, 'error' => 'free=1 requiere ref'));

$db = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($db->connect_errno) out(array('ok' => false, 'error' => 'db: ' . $db->connect_error));
$db->set_charset('utf8');

/* Entorno CEX (cex_config: test/pro) */
$env = 'pro';
if ($r = $db->query("SELECT config_value FROM cex_config WHERE config_key='env'")) {
    if ($row = $r->fetch_assoc()) $env = ($row['config_value'] === 'test') ? 'test' : 'pro';
}

/* ---- Dedup: envio OK no anulado para este albaran/pedido EN ESTE ENTORNO ----
 *      En "modificar" (regen=1) NO se deduplica: se fuerza un alta nueva. ---- */
if (!$free && !$regen) {
    $st = $alb !== ''
        ? $db->prepare("SELECT * FROM cex_shipments WHERE albaran_id=? AND entorno=? AND ok=1 AND cancelled_at IS NULL ORDER BY id DESC LIMIT 1")
        : $db->prepare("SELECT * FROM cex_shipments WHERE orders_id=? AND entorno=? AND ok=1 AND cancelled_at IS NULL ORDER BY id DESC LIMIT 1");
    if ($alb !== '') $st->bind_param('ss', $alb, $env); else $st->bind_param('is', $oid, $env);
    $st->execute();
    $prev = $st->get_result()->fetch_assoc();
    if ($prev) {
        $resp = array('ok' => true, 'dedup' => true, 'env' => $prev['entorno'],
                      'shipmentCode' => $prev['shipment_code'], 'ecb' => $prev['ecb'],
                      'ref' => $prev['ref'], 'tracking_url' => $prev['tracking_url'], 'zpl' => null);
        if (in_array($type, array('ZPL', 'BOTH')) && $prev['label_zpl_path'] && is_file($prev['label_zpl_path'])) {
            $resp['zpl'] = file_get_contents($prev['label_zpl_path']);
        }
        out($resp);
    }
}

/* ---- Datos del pedido (direccion de ENTREGA) ---- */
$manual = (($in['manual'] ?? '') === '1');
$shipMod = '';
if ($manual || $free) {
    $o = array(
        'delivery_name'           => trim((string) ($in['dname'] ?? '')),
        'delivery_street_address' => trim((string) ($in['dstreet'] ?? '')),
        'delivery_suburb'         => '',
        'delivery_city'           => trim((string) ($in['dcity'] ?? '')),
        'delivery_postcode'       => trim((string) ($in['dcp'] ?? '')),
        'delivery_state'          => trim((string) ($in['dstate'] ?? '')),
        'delivery_country'        => trim((string) ($in['dcountry'] ?? 'ES')),
        'customers_telephone'     => trim((string) ($in['dphone'] ?? '')),
        'customers_email_address' => trim((string) ($in['demail'] ?? '')),
    );
    if (!$free && ($o['delivery_name'] === '' || $o['delivery_street_address'] === '' || $o['delivery_postcode'] === '')) {
        out(array('ok' => false, 'error' => 'manual=1 requiere dname, dstreet y dcp'));
    }
} else {
    $st = $db->prepare("SELECT delivery_name, delivery_company, delivery_street_address, delivery_suburb,
                               delivery_city, delivery_postcode, delivery_state, delivery_country,
                               customers_telephone, customers_email_address, shipping_module
                        FROM orders WHERE orders_id=?");
    $st->bind_param('i', $oid);
    $st->execute();
    $o = $st->get_result()->fetch_assoc();
    if (!$o) out(array('ok' => false, 'error' => "pedido $oid no encontrado en la web"));
    $shipMod = (string) ($o['shipping_module'] ?? '');
}

/* Overrides de "modificar envío" (panel): pisan la dirección/datos del pedido.
 * En el flujo normal del watcher estos campos no llegan, así que no afectan. */
foreach (array('delivery_name' => 'dname', 'delivery_street_address' => 'dstreet', 'delivery_city' => 'dcity',
               'delivery_postcode' => 'dcp', 'delivery_state' => 'dstate', 'customers_telephone' => 'dphone',
               'customers_email_address' => 'demail') as $col => $param) {
    $ov = trim((string) ($in[$param] ?? ''));
    if ($ov !== '') $o[$col] = $ov;
}

/* Pais -> ISO-2 (orders.delivery_country guarda el NOMBRE) */
$iso = 'ES';
$cty = trim((string) $o['delivery_country']);
if (strlen($cty) === 2) {
    $iso = strtoupper($cty);
} elseif ($cty !== '') {
    $st = $db->prepare("SELECT countries_iso_code_2 FROM countries WHERE countries_name=? LIMIT 1");
    $st->bind_param('s', $cty);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) $iso = strtoupper($row['countries_iso_code_2']);
}

/* ---- Punto vs domicilio + sabado ----
 * Punto: fila en cex_pudo_orders (lo guardo el checkout cexpunto). producto 18 + idPtoExterno.
 * Domicilio: producto 93 (ePaq24). Sabado -> entrSabado=S si el modulo es el de sabado o svc=sabado. */
$pudoId = ''; $pudoName = '';
$pudoOver = preg_replace('/[^0-9A-Za-z]/', '', (string) ($in['pudo'] ?? ''));
if ($pudoOver !== '') {
    $pudoId = $pudoOver; $pudoName = trim((string) ($in['pname'] ?? ''));
} elseif (!$free && $oid > 0 && $svc !== 'domicilio') {
    $st = $db->prepare("SELECT pudo_id, name FROM cex_pudo_orders WHERE orders_id=? LIMIT 1");
    $st->bind_param('i', $oid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) { $pudoId = (string) $row['pudo_id']; $pudoName = (string) $row['name']; }
}

$intl = ($iso !== 'ES');   // PT, Andorra, etc.: envio internacional terrestre
$producto  = correos_express::PROD_PAQPUNTO; // se sobreescribe abajo
$entrSabado = '';
if ($pudoId !== '') {
    $producto = '18';  // Paq Punto (solo nacional)
} else {
    $producto = '93';  // ePaq24 domicilio
    if ($svc === 'sabado' || $shipMod === 'tipsawednesday_tipsawednesday') $entrSabado = 'S';
}
if ($intl) {
    // Internacional (PT/Andorra): PAQ24 terrestre iberico (63). OJO: el CP destino debe ir
    // en codPosIntDest; codPosNacDest valida formato espanol 99999 y rechaza 'NNNN-NNN'
    // (error 23 "CP NACIONAL DESTINATARIO: FORMATO INCORRECTO").
    $producto = correos_express::PROD_PAQ24;  // 63
    $entrSabado = '';                          // entrega sabado no aplica fuera de ES
}

/* Referencia (la sigue el cron de tracking: F{oid}). QFac-nativo -> Q{oid}. */
$ref = $free ? $freeRef : (($manual || ($oid > 0 && $oid < 10000000)) ? 'Q' . $oid : 'F' . $oid);

/* CP destino: el campo a veces viene sucio ("36201 VIGO", "08193 BELLATERRA").
 * Internacional -> conserva formato (PT 'NNNN-NNN', sin espacios). Nacional -> los 5 digitos. */
$cpRaw  = trim((string) $o['delivery_postcode']);
$cpInt  = preg_replace('/\s+/', '', $cpRaw);
$cpNac  = preg_match('/\d{5}/', $cpRaw, $mmcp) ? $mmcp[0] : preg_replace('/\D/', '', $cpInt);
$cpDest = $intl ? $cpInt : $cpNac;
/* CEX limita longitudes del destinatario: nomDest/contacDest 40, pobDest 30, dirDest 100.
 * (errores "NOMBRE DESTINATARIO: SUPERA LOS 40 CARACTERES", etc.) */
$nomDest = trim((string) $o['delivery_name']) ?: trim((string) ($o['delivery_company'] ?? ''));
$nomDest = mb_substr($nomDest, 0, 40);

/* ---- Payload grabacionEnvio ---- */
$opt = array(
    'ref'        => $ref,
    'refCliente' => $ref,
    'producto'   => $producto,
    'etiqueta'   => '2',   // ZPL -> campo etiqueta2
    'kilos'      => (string) $kilos,
    'numBultos'  => (string) $bultos,
    'portes'     => 'P',
    // Remitente = Francobordo
    'codRte'       => correos_express::CLIENTE,
    'nomRte'       => correos_express::FB_NOMBRE,
    'dirRte'       => correos_express::FB_DIR,
    'pobRte'       => correos_express::FB_POBL,
    'codPosNacRte' => correos_express::FB_CP,
    'paisISORte'   => 'ES',
    'contacRte'    => correos_express::FB_CONTACTO,
    'telefRte'     => correos_express::FB_TLFNO,
    'emailRte'     => correos_express::FB_EMAIL,
    // Destinatario = pedido
    'nomDest'       => $nomDest,
    'dirDest'       => mb_substr(trim((string) $o['delivery_street_address'] . ' ' . (string) ($o['delivery_suburb'] ?? '')), 0, 100),
    'pobDest'       => mb_substr(trim((string) $o['delivery_city']), 0, 30),
    'codPosNacDest' => $intl ? '' : $cpDest,
    'codPosIntDest' => $intl ? $cpDest : '',
    'paisISODest'   => $iso,
    'contacDest'    => $nomDest,
    'telefDest'     => preg_replace('/\D/', '', (string) $o['customers_telephone']),  // solo dígitos: CEX rechaza '+'/'?'/espacios ("TELEFONO NO VALIDO")
    'emailDest'     => trim((string) $o['customers_email_address']),
    'observac'      => $free ? ('Envio manual CEX / ' . $freeRef)
                             : ('Pedido ' . ($manual ? 'QFac ' : 'web ') . $oid . ($alb !== '' ? ' / Albaran ' . $alb : '')),
);
if ($entrSabado !== '') $opt['entrSabado'] = 'S';
if ($pudoId !== '')     $opt['idPtoExterno'] = $pudoId;

if ($dry) out(array('ok' => true, 'dry' => true, 'env' => $env, 'producto' => $producto,
                    'entrSabado' => $entrSabado, 'idPtoExterno' => $pudoId, 'ref' => $ref, 'payload' => $opt));

/* ---- Crear envio + etiqueta (una sola llamada) ---- */
$cex = new correos_express($env);
$cex->setTimeout(60);
$res = $cex->grabacionEnvio($opt);
$d   = $res['data'] ?? array();
$cod = isset($d['codigoRetorno']) ? (int) $d['codigoRetorno'] : -1;
$msg = (string) ($d['mensajeRetorno'] ?? ($res['error'] ?? 'sin respuesta'));

if (!$res['ok'] || $cod !== 0) {
    $st = $db->prepare("INSERT INTO cex_shipments (id_rma, orders_id, albaran_id, tipo, entorno, product_code, ref, kilos,
                          http_code, mensaje_retorno, ok, request_json, response_json, operator, date_added)
                        VALUES (0,?,?,'envio',?,?,?,?,?,?,0,?,?, 'vstock-watcher', NOW())");
    $http = (string) ($res['http'] ?? '');
    $reqj = json_encode($opt, JSON_UNESCAPED_UNICODE);
    $rawj = json_encode($d, JSON_UNESCAPED_UNICODE);
    $msg500 = substr($msg, 0, 500);
    $st->bind_param('issssdssss', $oid, $alb, $env, $producto, $ref, $kilos, $http, $msg500, $reqj, $rawj);
    $st->execute();
    out(array('ok' => false, 'env' => $env, 'error' => $msg, 'cod' => $cod, 'http' => $res['http'] ?? null));
}

/* numEnvio + codigo de barras del bulto + ZPL */
$numEnvio = (string) ($d['datosResultado'] ?? '');
$ecb = '';
if (!empty($d['listaBultos'][0]) && is_array($d['listaBultos'][0])) {
    foreach (array('codBarras', 'codUnico', 'codBulto', 'barcode') as $k) {
        if (!empty($d['listaBultos'][0][$k])) { $ecb = (string) $d['listaBultos'][0][$k]; break; }
    }
}
/* CEX devuelve UNA etiqueta por bulto en la lista 'etiqueta'; el ZPL va en TEXTO PLANO
 * en etiqueta2 (NO base64; solo etiqueta1/PDF es base64). Concatenamos TODOS los bultos
 * (cada uno un bloque ^XA..^XZ) para que la impresora saque las N etiquetas. Robusto:
 * si una entrada no parece ZPL, intentar base64 como respaldo. */
$zpl = '';
$labelsArr = (isset($d['etiqueta']) && is_array($d['etiqueta'])) ? $d['etiqueta'] : array();
foreach ($labelsArr as $lab) {
    $raw = is_array($lab) ? (string) ($lab['etiqueta2'] ?? '') : '';
    if ($raw === '') continue;
    if (strpos($raw, '^XA') === false) {
        $dec = base64_decode($raw, true);
        if ($dec !== false && strpos((string) $dec, '^XA') !== false) $raw = $dec;
    }
    $zpl .= $raw;
}
$nLabels = substr_count($zpl, '^XA');   // nº de etiquetas reales producidas

/* Guardar etiqueta ZPL en dir privado (fuera de public_html) */
$dir = '/home/francobordo/cex_labels/' . $oid . '/';
if (!is_dir($dir)) @mkdir($dir, 0750, true);
$suf = substr(md5($numEnvio . microtime(true)), 0, 8);
$zplPath = null;
if ($zpl !== '' && @file_put_contents($dir . "cex_{$oid}_{$suf}.zpl", $zpl) !== false) $zplPath = $dir . "cex_{$oid}_{$suf}.zpl";

/* URL publica de seguimiento CEX */
$trackingUrl = 'https://www.correosexpress.com/url/v?s=' . rawurlencode($ref) . '&cp=' . rawurlencode($cpDest);

$st = $db->prepare("INSERT INTO cex_shipments (id_rma, orders_id, albaran_id, tipo, entorno, shipment_code, ecb,
                      product_code, pudo_id, pudo_name, ref, kilos, label_format, label_zpl_path, tracking_url,
                      http_code, mensaje_retorno, ok, request_json, response_json, operator, date_added)
                    VALUES (0,?,?,'envio',?,?,?,?,?,?,?,?,?,?,?,?, '', 1, ?, ?, 'vstock-watcher', NOW())");
$fmt = $zplPath ? 'zpl' : '';
$http = (string) ($res['http'] ?? '');
$reqj = json_encode($opt, JSON_UNESCAPED_UNICODE);
$rawj = json_encode($d, JSON_UNESCAPED_UNICODE);
$pudoIdDb   = ($pudoId !== '') ? $pudoId : null;
$pudoNameDb = ($pudoName !== '') ? $pudoName : null;
$st->bind_param('issssssssdssssss', $oid, $alb, $env, $numEnvio, $ecb, $producto, $pudoIdDb, $pudoNameDb,
                $ref, $kilos, $fmt, $zplPath, $trackingUrl, $http, $reqj, $rawj);
$st->execute();
$newId = $db->insert_id;

out(array(
    'ok' => true, 'dedup' => false, 'env' => $env, 'shipment_id' => $newId,
    'shipmentCode' => $numEnvio, 'ecb' => $ecb, 'ref' => $ref, 'producto' => $producto,
    'bultos' => $bultos, 'labels' => $nLabels,
    'entrSabado' => $entrSabado, 'idPtoExterno' => $pudoId, 'tracking_url' => $trackingUrl,
    'zpl' => in_array($type, array('ZPL', 'BOTH')) ? $zpl : null,
));
