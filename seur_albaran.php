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

$in = array_merge($_GET, $_POST);

function out($arr) { echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

if (($in['token'] ?? '') !== SEUR_ALB_TOKEN) {
    http_response_code(403);
    out(array('ok' => false, 'error' => 'forbidden'));
}

chdir(__DIR__);
include 'includes/configure.php';
require_once 'includes/classes/seur.php';

$oid    = (int) ($in['oid'] ?? 0);
$kilos  = (float) str_replace(',', '.', (string) ($in['kilos'] ?? '1'));
if ($kilos <= 0) $kilos = 1;
$bultos = max(1, (int) ($in['bultos'] ?? 1));
$alb    = trim((string) ($in['albaran'] ?? ''));
$type   = strtoupper(trim((string) ($in['type'] ?? 'BOTH')));
if (!in_array($type, array('ZPL', 'PDF', 'BOTH'), true)) $type = 'BOTH';
$dry    = (($in['dry'] ?? '') === '1');

if ($oid <= 0) out(array('ok' => false, 'error' => 'oid requerido'));

$db = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($db->connect_errno) out(array('ok' => false, 'error' => 'db: ' . $db->connect_error));
$db->set_charset('utf8');

/* Entorno SEUR (seur_config, igual que el módulo RMA del admin) */
$env = 'pre';
if ($r = $db->query("SELECT config_value FROM seur_config WHERE config_key='env'")) {
    if ($row = $r->fetch_assoc()) $env = ($row['config_value'] === 'pro') ? 'pro' : 'pre';
}

/* ---- Dedup: si ya hay envío OK no anulado para este albarán/pedido, devolver el existente ---- */
$st = $alb !== ''
    ? $db->prepare("SELECT * FROM seur_shipments WHERE albaran_id=? AND ok=1 AND cancelled_at IS NULL ORDER BY id DESC LIMIT 1")
    : $db->prepare("SELECT * FROM seur_shipments WHERE orders_id=? AND ok=1 AND cancelled_at IS NULL ORDER BY id DESC LIMIT 1");
if ($alb !== '') $st->bind_param('s', $alb); else $st->bind_param('i', $oid);
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

/* ---- Datos del pedido web (dirección de ENTREGA) ---- */
$st = $db->prepare("SELECT orders_id, delivery_name, delivery_company, delivery_street_address, delivery_suburb,
                           delivery_city, delivery_postcode, delivery_state, delivery_country,
                           customers_telephone, customers_email_address
                    FROM orders WHERE orders_id=?");
$st->bind_param('i', $oid);
$st->execute();
$o = $st->get_result()->fetch_assoc();
if (!$o) out(array('ok' => false, 'error' => "pedido $oid no encontrado en la web"));

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

/* Entrega en punto SEUR (2shop): si el pedido eligió punto en el checkout,
 * servicio 1/48 (nac) ó 77/48 (intl) + pickupCentreCode en el receiver.
 * La dirección de entrega del pedido YA es la del punto (checkout_process). */
$opts = array('ref' => 'F' . $oid, 'weight' => $kilos, 'bultos' => $bultos,
              'observations' => 'Pedido web ' . $oid . ($alb !== '' ? ' / Albaran Vstock ' . $alb : ''));
$st = $db->prepare("SELECT pudo_id, name FROM seur_pudo_orders WHERE orders_id=?");
$st->bind_param('i', $oid);
$st->execute();
if ($pudo = $st->get_result()->fetch_assoc()) {
    $dest['pickupCentreCode'] = $pudo['pudo_id'];
    $opts['service'] = ($iso === 'ES') ? seur::SVC_NAC_2SHOP : seur::SVC_INT_2SHOP;     // 1 / 77
    $opts['product'] = ($iso === 'ES') ? seur::PRD_NAC_2SHOP : seur::PRD_INT_2SHOP;     // 48
    $opts['observations'] .= ' / Punto SEUR ' . $pudo['pudo_id'];
}

$ref = 'F' . $oid;
$shipment = seur::envioDesdePedido($dest, $opts);

if ($dry) out(array('ok' => true, 'dry' => true, 'env' => $env, 'payload' => $shipment));

/* ---- Crear envío + etiquetas ---- */
$s = new seur($env);
$s->setTimeout(60);
$res  = $s->createShipment($shipment);
$code = seur::extraerShipmentCode($res);
$bul  = seur::extraerBultos($res);

/* Recuperación: SEUR deduplica por referencia+fecha. Si rechaza por "same reference
 * and date" PERO devuelve el shipmentCode del envío ya existente, lo reutilizamos
 * (mismo pedido grabado hoy: típicamente una reimpresión o un albarán repetido). */
$recovered = false;
if (!$res['ok'] && $code) {
    $recovered = true;
}

if (!$res['ok'] && !$code) {
    /* registrar el fallo para diagnóstico */
    $err = seur::primerError($res);
    $st = $db->prepare("INSERT INTO seur_shipments (id_rma, orders_id, albaran_id, tipo, entorno, service_code, product_code,
                          ref, kilos, http_code, mensaje_retorno, ok, request_json, response_json, operator, date_added)
                        VALUES (0,?,?,'envio',?,?,?,?,?,?,?,0,?,?, 'vstock-watcher', NOW())");
    $http = (string) $res['http'];
    $req  = json_encode($s->lastRequest, JSON_UNESCAPED_UNICODE);
    $raw = $res['raw'];
    $st->bind_param('isssssdssss', $oid, $alb, $env, $shipment['serviceCode'], $shipment['productCode'],
                    $ref, $kilos, $http, $err, $req, $raw);
    $st->execute();
    out(array('ok' => false, 'env' => $env, 'error' => $err, 'http' => $res['http']));
}

/* Etiquetas (ZPL para térmica; PDF como respaldo/reimpresión) */
$zpl = null; $pdfBin = null;
$labZ = $s->getLabel($code, 'ZPL');
if ($labZ['ok'] && !empty($labZ['labels'])) {
    $parts = array();
    foreach ($labZ['labels'] as $L) if (!empty($L['label'])) $parts[] = $L['label'];
    if ($parts) $zpl = implode("\n", $parts);
} elseif ($labZ['ok'] && !empty($labZ['label'])) {
    $zpl = $labZ['label'];
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

/* URL pública de seguimiento por referencia (mismo patrón que usa Vstock para SEUR) */
$trackingUrl = 'http://www.seur.com/seguimiento/' . rawurlencode($ref) . '/fecha/' . date('d-m-Y');

$ecb = $bul['ecbs'][0] ?? null;
$pn  = $bul['parcelNumbers'][0] ?? null;
$st = $db->prepare("INSERT INTO seur_shipments (id_rma, orders_id, albaran_id, tipo, entorno, shipment_code, ecb,
                      parcel_number, service_code, product_code, ref, kilos, label_format, label_path, label_zpl_path,
                      tracking_url, http_code, mensaje_retorno, ok, request_json, response_json, operator, date_added)
                    VALUES (0,?,?,'envio',?,?,?,?,?,?,?,?,?,?,?,?,?, '', 1, ?, ?, 'vstock-watcher', NOW())");
$fmt  = ($zplPath && $pdfPath) ? 'both' : ($zplPath ? 'zpl' : 'pdf');
$http = (string) $res['http'];
$req  = json_encode($s->lastRequest, JSON_UNESCAPED_UNICODE);
$st->bind_param('issssssssdsssssss', $oid, $alb, $env, $code, $ecb, $pn,
                $shipment['serviceCode'], $shipment['productCode'], $ref, $kilos,
                $fmt, $pdfPath, $zplPath, $trackingUrl, $http, $req, $res['raw']);
$st->execute();

out(array(
    'ok' => true, 'dedup' => false, 'recovered' => $recovered, 'env' => $env,
    'shipmentCode' => $code, 'ecb' => $ecb, 'parcelNumbers' => $bul['parcelNumbers'],
    'tracking_url' => $trackingUrl,
    'zpl'     => in_array($type, array('ZPL', 'BOTH')) ? $zpl : null,
    'pdf_b64' => in_array($type, array('PDF', 'BOTH')) && $pdfBin !== null ? base64_encode($pdfBin) : null,
));
