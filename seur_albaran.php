<?php
/**
 * Endpoint de integración Vstock → SEUR (salida de pedidos).
 *
 * Lo llama el detector de albaranes (watcher en la LAN) cuando el operador
 * finaliza un albarán en Vstock con la agencia "SEUR API": crea el envío en
 * SEUR vía API (clase includes/classes/seur.php), guarda el registro en
 * seur_shipments y devuelve la etiqueta (ZPL para impresora térmica y/o PDF).
 *
 * Standalone (no pasa por application_top): configure.php + mysqli + clase seur.
 * Protegido por token. Entorno SEUR según seur_config (pre/pro), como el módulo RMA.
 *
 * Parámetros (GET o POST):
 *   token   (obligatorio)
 *   oid     (obligatorio) orders_id del pedido web
 *   kilos   peso TOTAL en kg (def. 1)
 *   bultos  nº de bultos (def. 1; el peso se reparte)
 *   albaran id del albarán Vstock (ALB_ID) — para dedup e histórico
 *   type    ZPL | PDF | BOTH (def. BOTH)
 *   dry     1 = no crea nada, devuelve el payload que se enviaría
 *
 * Respuesta JSON: { ok, dedup, env, shipmentCode, ecb, parcelNumbers,
 *                   tracking_url, zpl, pdf_b64, error }
 *
 * Ver memoria francobordo_seur_api.
 */
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');

define('SEUR_ALB_TOKEN', 'seuralb_9d2f51c7a3e8');

/* Plantilla de la etiqueta ZPL. GEOLABEL es el formato oficial SEUR para el rollo
 * 10x15 de la Zebra del almacen. SEUR la entrega ~8 mm volcada a la izquierda (Y
 * alta); se centra con seurAjustarZpl() bajando SEUR_ZPL_DY dots la Y de los
 * campos. El PDF se mantiene en GEOLABEL (respaldo A4). */
define('SEUR_ZPL_TEMPLATE', 'GEOLABEL');
define('SEUR_ZPL_DY', 32);  // GEOLABEL rollo 10x15: baja la Y de los campos 32 dots (~4 mm) para centrar (validado impreso 2026-06-16; bajar Y = mover a la DERECHA)

$in = array_merge($_GET, $_POST);

function out($arr) { echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

if (($in['token'] ?? '') !== SEUR_ALB_TOKEN) {
    http_response_code(403);
    out(array('ok' => false, 'error' => 'forbidden'));
}

chdir(__DIR__);
include 'includes/configure.php';
require_once 'includes/classes/seur.php';

/* ---- Modo REIMPRESIÓN: el watcher (.112) recoge los ZPL encolados desde el
 * panel admin y los deja en su cola de impresión hacia la Zebra ---- */
if (($in['reprints'] ?? '') === '1') {
    $db = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
    if ($db->connect_errno) out(array('ok' => false, 'error' => 'db'));
    $db->set_charset('utf8mb4');
    $out = array();
    $r = $db->query("SELECT id, orders_id, zpl FROM seur_reprint_queue WHERE done = 0 ORDER BY id LIMIT 20");
    while ($row = $r->fetch_assoc()) {
        $out[] = array('id' => (int) $row['id'], 'oid' => (int) $row['orders_id'], 'zpl' => $row['zpl']);
    }
    // NO marcamos done aqui: el watcher confirma con ?reprint_done DESPUES de soltar
    // el ZPL, asi un fallo de transporte .112->nic1 no pierde el reprint (se reintenta).
    out(array('ok' => true, 'reprints' => $out));
}

/* ---- ACK del watcher: marca done los reprints que YA solto a la cola de impresion ---- */
if (($in['reprint_done'] ?? '') !== '') {
    $db = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
    if ($db->connect_errno) out(array('ok' => false, 'error' => 'db'));
    $ids = array_values(array_filter(array_map('intval', explode(',', (string) $in['reprint_done']))));
    if ($ids) {
        $in_ids = implode(',', $ids);
        $db->query("UPDATE seur_reprint_queue SET done = 1, done_at = NOW() WHERE id IN ($in_ids)");
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
$regen  = (($in['regen'] ?? '') === '1');  // regeneración desde el panel: ref nueva + sin reuse

$free    = (($in['free'] ?? '') === '1');   // ENVIO MANUAL sin pedido: ref propia, orders_id=0, sin dedup
$freeRef = trim((string) ($in['ref'] ?? ''));
if (!$free && $oid <= 0) out(array('ok' => false, 'error' => 'oid requerido'));
if ($free && $freeRef === '') out(array('ok' => false, 'error' => 'free=1 requiere ref'));

$db = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($db->connect_errno) out(array('ok' => false, 'error' => 'db: ' . $db->connect_error));
$db->set_charset('utf8');

/* Entorno SEUR (seur_config, igual que el módulo RMA del admin) */
$env = 'pre';
if ($r = $db->query("SELECT config_value FROM seur_config WHERE config_key='env'")) {
    if ($row = $r->fetch_assoc()) $env = ($row['config_value'] === 'pro') ? 'pro' : 'pre';
}

/* ---- Dedup: si ya hay envío OK no anulado para este albarán/pedido EN ESTE ENTORNO,
 *      devolver el existente (un envío de PRE no satisface el dedup en PRO).
 *      En regeneración (regen=1) NO se deduplica: se fuerza un alta nueva con ref nueva. ---- */
if (!$regen && !$free) {
$st = $alb !== ''
    ? $db->prepare("SELECT * FROM seur_shipments WHERE albaran_id=? AND entorno=? AND ok=1 AND cancelled_at IS NULL ORDER BY id DESC LIMIT 1")
    : $db->prepare("SELECT * FROM seur_shipments WHERE orders_id=? AND entorno=? AND ok=1 AND cancelled_at IS NULL ORDER BY id DESC LIMIT 1");
if ($alb !== '') $st->bind_param('ss', $alb, $env); else $st->bind_param('is', $oid, $env);
$st->execute();
$prev = $st->get_result()->fetch_assoc();
if ($prev) {
    $resp = array('ok' => true, 'dedup' => true, 'env' => $prev['entorno'],
                  'shipmentCode' => $prev['shipment_code'], 'ecb' => $prev['ecb'],
                  'tracking_url' => $prev['tracking_url'], 'zpl' => null, 'pdf_b64' => null);
    if (in_array($type, array('ZPL', 'BOTH')) && $prev['label_zpl_path'] && is_file($prev['label_zpl_path'])) {
        $resp['zpl'] = file_get_contents($prev['label_zpl_path']);
    }
    if (in_array($type, array('PDF', 'BOTH')) && $prev['label_path'] && is_file($prev['label_path'])) {
        $resp['pdf_b64'] = base64_encode(file_get_contents($prev['label_path']));
    }
    out($resp);
}
}

/* ---- Datos del pedido web (dirección de ENTREGA) ----
 * MODO MANUAL (pedidos QFac-nativos serie 26xxxxx, NO están en `orders`):
 * el watcher pasa manual=1 + la dirección leída de Vstock PEDIDOS_CLIENTES
 * (dname/dstreet/dcp/dcity/dstate/dcountry/dphone/demail). ref = Q{oid}. */
$manual = (($in['manual'] ?? '') === '1');   // $in = GET+POST: el panel regenera por POST
if ($manual || $free) {
    $o = array(
        'delivery_name'           => trim((string) ($in['dname'] ?? '')),
        'delivery_company'        => '',
        'delivery_street_address' => trim((string) ($in['dstreet'] ?? '')),
        'delivery_suburb'         => '',
        'delivery_city'           => trim((string) ($in['dcity'] ?? '')),
        'delivery_postcode'       => trim((string) ($in['dcp'] ?? '')),
        'delivery_state'          => trim((string) ($in['dstate'] ?? '')),
        'delivery_country'        => trim((string) ($in['dcountry'] ?? 'ES')),
        'customers_telephone'     => trim((string) ($in['dphone'] ?? '')),
        'customers_email_address' => trim((string) ($in['demail'] ?? '')),
    );
    if ($o['delivery_name'] === '' || $o['delivery_street_address'] === '' || $o['delivery_postcode'] === '') {
        out(array('ok' => false, 'error' => 'manual=1 requiere dname, dstreet y dcp'));
    }
} else {
    $st = $db->prepare("SELECT orders_id, delivery_name, delivery_company, delivery_street_address, delivery_suburb,
                               delivery_city, delivery_postcode, delivery_state, delivery_country,
                               customers_telephone, customers_email_address
                        FROM orders WHERE orders_id=?");
    $st->bind_param('i', $oid);
    $st->execute();
    $o = $st->get_result()->fetch_assoc();
    if (!$o) out(array('ok' => false, 'error' => "pedido $oid no encontrado en la web"));
}

/* País → ISO-2: orders.delivery_country guarda el NOMBRE; resolver contra countries. */
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

$dest = array(
    'name'        => trim($o['delivery_name']) ?: trim($o['delivery_company']),
    'phone'       => trim((string) $o['customers_telephone']),
    'contactName' => trim($o['delivery_name']),
    'email'       => trim((string) $o['customers_email_address']),
    'streetName'  => trim($o['delivery_street_address'] . ' ' . (string) $o['delivery_suburb']),
    'cityName'    => trim($o['delivery_city']),
    'postalCode'  => trim($o['delivery_postcode']),
    'country'     => $iso,
);

/* OVERRIDES opcionales (regeneración "Anular y regenerar" desde _admin/seur_envios.php).
 * Inertes en el flujo del watcher (no los envía). dcountry re-resuelve ISO. */
if (($v = trim((string) ($in['dname']  ?? ''))) !== '') { $dest['name'] = $v; $dest['contactName'] = $v; }
if (($v = trim((string) ($in['dstreet']?? ''))) !== '')   $dest['streetName'] = $v;
if (($v = trim((string) ($in['dcity']  ?? ''))) !== '')   $dest['cityName']   = $v;
if (($v = trim((string) ($in['dcp']    ?? ''))) !== '')   $dest['postalCode'] = $v;
if (($v = trim((string) ($in['dphone'] ?? ''))) !== '')   $dest['phone']      = $v;
if (($v = trim((string) ($in['demail'] ?? ''))) !== '')   $dest['email']      = $v;
if (($v = trim((string) ($in['dcountry'] ?? ''))) !== '') {
    if (strlen($v) === 2) { $iso = strtoupper($v); }
    else {
        $stc = $db->prepare("SELECT countries_iso_code_2 FROM countries WHERE countries_name=? LIMIT 1");
        $stc->bind_param('s', $v); $stc->execute();
        if ($rowc = $stc->get_result()->fetch_assoc()) $iso = strtoupper($rowc['countries_iso_code_2']);
    }
    $dest['country'] = $iso;
}

/* FALLBACK ciudad vacía (pedido con delivery_city='' — p.ej. address_book viejo sin
 * blindar, caso 10363995): SEUR EXIGE cityName y sin él el alta devuelve un 400
 * ENGAÑOSO "Invalid service-product". Se resuelve la población oficial del CP con
 * la API cities() (mismo patrón que seurpunto::puntos); último recurso: provincia. */
if (trim((string) $dest['cityName']) === '' && trim((string) $dest['postalCode']) !== '') {
    $sCity = new seur($env);
    $sCity->setTimeout(10);
    $rcty = $sCity->cities(preg_replace('/\s+/', '', (string) $dest['postalCode']), $dest['country']);
    $dcty = seur::payload($rcty);
    if (is_array($dcty) && !empty($dcty[0]['cityName'])) {
        $dest['cityName'] = (string) $dcty[0]['cityName'];
    } elseif (trim((string) ($o['delivery_state'] ?? '')) !== '') {
        $dest['cityName'] = trim((string) $o['delivery_state']);
    }
}

/* Entrega en punto SEUR (2shop): si el pedido eligió punto en el checkout,
 * servicio 1/48 (nac) ó 77/48 (intl) + pickupCentreCode en el receiver.
 * La dirección de entrega del pedido YA es la del punto (checkout_process). */
$opts = array('ref' => $free ? $freeRef : (($manual ? 'Q' : 'F') . $oid), 'weight' => $kilos, 'bultos' => $bultos,
              'observations' => $free ? ('Envio manual SEUR / ' . $freeRef) : ('Pedido ' . ($manual ? 'QFac ' : 'web ') . $oid . ($alb !== '' ? ' / Albaran Vstock ' . $alb : '')));
$pudoId = ''; $pudoName = '';
$pudoOver = preg_replace('/[^0-9A-Za-z]/', '', (string) ($in['pudo'] ?? ''));
$noPunto  = (($in['nopunto'] ?? '') === '1');
if ($pudoOver !== '') {                       // override explícito del panel
    $pudoId   = $pudoOver;
    $pudoName = trim((string) ($in['pname'] ?? ''));
} elseif (!$noPunto) {                          // comportamiento normal: punto del checkout
    $st = $db->prepare("SELECT pudo_id, name FROM seur_pudo_orders WHERE orders_id=?");
    $st->bind_param('i', $oid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) { $pudoId = (string) $row['pudo_id']; $pudoName = (string) $row['name']; }
}
if ($pudoId !== '') {
    $dest['pickupCentreCode'] = $pudoId;
    $opts['service'] = ($iso === 'ES') ? seur::SVC_NAC_2SHOP : seur::SVC_INT_2SHOP;     // 1 / 77
    $opts['product'] = ($iso === 'ES') ? seur::PRD_NAC_2SHOP : seur::PRD_INT_2SHOP;     // 48
    $opts['observations'] .= ' / Punto SEUR ' . $pudoId;
}

/* SEUR 13:30 (servicio 9/2, solo nacional a domicilio): el watcher lo pide con
 * svc=1330 cuando el albaran va con la agencia Vstock 'SEUR 13:30'. */
if (!isset($dest['pickupCentreCode']) && (($in['svc'] ?? '') === '1330') && $iso === 'ES') {
    $opts['service'] = '9';
    $opts['product'] = '2';
    $opts['observations'] .= ' / SEUR 13:30';
}

/* SEUR 10 / antes de las 10h (servicio 3/2, ES peninsular + Portugal continental):
 * el watcher lo pide con svc=1000 cuando el albaran va con la agencia Vstock 'SEUR 10'. */
if (!isset($dest['pickupCentreCode']) && (($in['svc'] ?? '') === '1000') && ($iso === 'ES' || $iso === 'PT')) {
    $opts['service'] = '3';
    $opts['product'] = '2';
    $opts['observations'] .= ' / SEUR 10';
}

/* SEUR Sábado (servicio 57/2, solo nacional ES peninsular): el watcher lo pide
 * con svc=sabado cuando el albaran va con la agencia Vstock 'SEUR SABADO'. */
if (!isset($dest['pickupCentreCode']) && (($in['svc'] ?? '') === 'sabado') && $iso === 'ES') {
    $opts['service'] = '57';
    $opts['product'] = '2';
    $opts['observations'] .= ' / SEUR Sabado';
}

/* SEUR 48h (servicio 15/130, nacional a domicilio): el watcher lo pide con svc=48
 * cuando el albaran va con la agencia Vstock 'SEUR 48'. Producto 130 (alt. 128 si
 * SEUR devolviera "Unknown Service/Product"). */
if (!isset($dest['pickupCentreCode']) && (($in['svc'] ?? '') === '48') && $iso === 'ES') {
    // DEPRECADO 2026-06-23: SEUR 48 (15/130, capado a 20 kg) -> B2C 31/2 (SEUR 24, sin tope).
    $opts['service'] = '31';
    $opts['product'] = '2';
    $opts['observations'] .= ' / SEUR 24 (B2C)';
}

/* SERVICIO/PRODUCTO explicito (envio manual O selector de "Anular y regenerar" del
 * panel, 2026-07-07): svccode/prodcode mandan sobre svc= y sobre el default por pais.
 * Si el servicio elegido NO es 2shop (producto 48), se quita el pickupCentreCode:
 * ese campo solo vale con 2shop y SEUR rechazaria el alta. */
if (($free || $regen) && trim((string) ($in['svccode'] ?? '')) !== '') {
    $opts['service'] = preg_replace('/\D/', '', (string) $in['svccode']);
    $pc = preg_replace('/\D/', '', (string) ($in['prodcode'] ?? ''));
    if ($pc !== '') $opts['product'] = $pc;
    if (($opts['product'] ?? '') !== '48' && isset($dest['pickupCentreCode'])) {
        unset($dest['pickupCentreCode']);
    }
}

if ($regen) {
    // ref nueva por regeneración: F{oid}R{n}/Q{oid}R{n} (n monotónico = nº envíos del pedido + 1).
    $rc = $db->prepare("SELECT COUNT(*) c FROM seur_shipments WHERE orders_id=? AND tipo='envio'");
    $rc->bind_param('i', $oid); $rc->execute();
    $n = (int) ($rc->get_result()->fetch_assoc()['c'] ?? 0) + 1;
    $opts['ref'] = $opts['ref'] . 'R' . $n;
}
$ref = $opts['ref'];  // 'F{oid}'/'Q{oid}' (+ 'R{n}' si regen) — DEBE coincidir con SEUR (el cron rastrea por esta ref)
$shipment = seur::envioDesdePedido($dest, $opts);

if ($dry) out(array('ok' => true, 'dry' => true, 'env' => $env, 'payload' => $shipment));

/* ---- Crear envío + etiquetas ---- */
$s = new seur($env);
$s->setTimeout(60);
$res  = $s->createShipment($shipment);
$reqShip = $s->lastRequest; // capturar AHORA: las llamadas de etiqueta lo sobrescriben
$code = seur::extraerShipmentCode($res);
$bul  = seur::extraerBultos($res);

/* Recuperación: SEUR deduplica por referencia+fecha. Si rechaza por "same reference
 * and date" PERO devuelve el shipmentCode del envío ya existente, lo reutilizamos
 * (mismo pedido grabado hoy: típicamente una reimpresión o un albarán repetido). */
$recovered = false;
if (!$res['ok'] && $code && !$regen) {
    $recovered = true;
}

if (!$res['ok'] && (!$code || $regen)) {
    /* registrar el fallo para diagnóstico */
    $err = seur::primerError($res);
    $st = $db->prepare("INSERT INTO seur_shipments (id_rma, orders_id, albaran_id, tipo, entorno, service_code, product_code,
                          ref, kilos, http_code, mensaje_retorno, ok, request_json, response_json, operator, date_added)
                        VALUES (0,?,?,'envio',?,?,?,?,?,?,?,0,?,?, 'vstock-watcher', NOW())");
    $http = (string) $res['http'];
    $req  = json_encode($reqShip, JSON_UNESCAPED_UNICODE);
    $raw = $res['raw'];
    $st->bind_param('isssssdssss', $oid, $alb, $env, $shipment['serviceCode'], $shipment['productCode'],
                    $ref, $kilos, $http, $err, $req, $raw);
    $st->execute();
    out(array('ok' => false, 'env' => $env, 'error' => $err, 'http' => $res['http']));
}

/* Etiquetas (ZPL para térmica; PDF como respaldo/reimpresión) */
$zpl = null; $pdfBin = null;
/* Centra la GEOLABEL en el rollo 10x15. SEUR la entrega ~8 mm volcada a la
 * izquierda (Y alta); bajamos la Y de cada ^FO/^FT (clamp 0) para moverla a la
 * DERECHA, y RENOMBRAMOS el formato almacenado (^DF/^XF): la Zebra cachea el
 * formato por nombre y, si no, reusaria la version sin centrar. */
function seurAjustarZpl($zpl) {
    $dy = (int) SEUR_ZPL_DY;
    if ($dy <= 0) return $zpl;
    $zpl = preg_replace_callback('/\^(F[OT])(\d+),(\d+)/', function ($m) use ($dy) {
        $ny = (int) $m[3] - $dy; if ($ny < 0) $ny = 0;
        return '^' . $m[1] . $m[2] . ',' . $ny;
    }, $zpl);
    if (preg_match('/\^DF([A-Z0-9_]{1,8})\.(\d+)/i', $zpl, $m)) {
        $zpl = str_replace($m[1] . '.' . $m[2], 'FBGS' . $dy . '.' . $m[2], $zpl);
    }
    return $zpl;
}

$labZ = $s->getLabel($code, 'ZPL', array('templateType' => SEUR_ZPL_TEMPLATE));
if ($labZ['ok'] && !empty($labZ['labels'])) {
    $parts = array();
    foreach ($labZ['labels'] as $L) if (!empty($L['label'])) $parts[] = seurAjustarZpl($L['label']);
    if ($parts) $zpl = implode("\n", $parts);
} elseif ($labZ['ok'] && !empty($labZ['label'])) {
    $zpl = seurAjustarZpl($labZ['label']);
}
$labP = $s->getLabel($code, 'PDF');
if ($labP['ok'] && !empty($labP['pdf_bin'])) $pdfBin = $labP['pdf_bin'];

/* Guardar etiquetas en dir privado (fuera de public_html) */
$dir = '/home/francobordo/seur_labels/' . $oid . '/';
if (!is_dir($dir)) @mkdir($dir, 0750, true);
$zplPath = null; $pdfPath = null;
$suf = substr(md5($code . microtime(true)), 0, 8);
if ($zpl !== null   && @file_put_contents($dir . "seur_{$oid}_{$suf}.zpl", $zpl)   !== false) $zplPath = $dir . "seur_{$oid}_{$suf}.zpl";
if ($pdfBin !== null && @file_put_contents($dir . "seur_{$oid}_{$suf}.pdf", $pdfBin) !== false) $pdfPath = $dir . "seur_{$oid}_{$suf}.pdf";

$ecb = $bul['ecbs'][0] ?? null;
$pn  = $bul['parcelNumbers'][0] ?? null;

/* URL pública de seguimiento. El formato viejo seur.com/seguimiento/{ref}/fecha/{f}
 * fue RETIRADO por SEUR (404, 2026-06-11): el vivo es miseur/mis-envios, que lee
 * code + cp (+email_tlf) de la query — con ambos abre el detalle sin pedir datos
 * (la página exige CP destino "por seguridad", como el enlace de CEX s=...&cp=).
 * code: preferimos el ECB del bulto (código de barras estándar); si no, la ref. */
$trackingUrl = 'https://www.seur.com/miseur/mis-envios?code=' . rawurlencode($ecb !== null && $ecb !== '' ? $ecb : $ref)
             . '&cp=' . rawurlencode(preg_replace('/\s+/', '', (string) ($dest['postalCode'] ?? '')))
             . '&email_tlf=' . rawurlencode(preg_replace('/\s+/', '', (string) ($dest['phone'] ?? '')));
$st = $db->prepare("INSERT INTO seur_shipments (id_rma, orders_id, albaran_id, tipo, entorno, shipment_code, ecb,
                      parcel_number, service_code, product_code, pudo_id, pudo_name, ref, kilos, label_format, label_path, label_zpl_path,
                      tracking_url, http_code, mensaje_retorno, ok, request_json, response_json, operator, date_added)
                    VALUES (0,?,?,'envio',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, '', 1, ?, ?, 'vstock-watcher', NOW())");
$fmt  = ($zplPath && $pdfPath) ? 'both' : ($zplPath ? 'zpl' : 'pdf');
$http = (string) $res['http'];
$req  = json_encode($reqShip, JSON_UNESCAPED_UNICODE);
$pudoIdDb   = ($pudoId !== '') ? $pudoId : null;
$pudoNameDb = ($pudoName !== '') ? $pudoName : null;
$st->bind_param('issssssssssdsssssss', $oid, $alb, $env, $code, $ecb, $pn,
                $shipment['serviceCode'], $shipment['productCode'], $pudoIdDb, $pudoNameDb, $ref, $kilos,
                $fmt, $pdfPath, $zplPath, $trackingUrl, $http, $req, $res['raw']);
$st->execute();
$newShipId = $db->insert_id;

out(array(
    'ok' => true, 'dedup' => false, 'recovered' => $recovered, 'env' => $env,
    'shipment_id' => $newShipId, 'ref' => $ref,
    'shipmentCode' => $code, 'ecb' => $ecb, 'parcelNumbers' => $bul['parcelNumbers'],
    'tracking_url' => $trackingUrl,
    'zpl'     => in_array($type, array('ZPL', 'BOTH')) ? $zpl : null,
    'pdf_b64' => in_array($type, array('PDF', 'BOTH')) && $pdfBin !== null ? base64_encode($pdfBin) : null,
));
