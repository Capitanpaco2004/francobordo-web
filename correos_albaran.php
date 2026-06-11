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
 * Parámetros (GET o POST):
 *   token   (obligatorio)
 *   oid     (obligatorio) orders_id del pedido web
 *   kilos   peso TOTAL en kg (def. 1)
 *   bultos  nº de bultos (def. 1; el peso se reparte)
 *   albaran id del albarán Vstock (ALB_ID) — para dedup e histórico
 *   type    ZPL | PDF | BOTH (def. BOTH)
 *   dry     1 = no crea nada, devuelve el payload que se enviaría
 *
 * Respuesta JSON: { ok, dedup, shipmentCode, packageCodes, tracking_url, zpl, pdf_b64, error }
 *
 * Ver memoria francobordo_correos_api. Patrón: seur_albaran.php.
 */
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');

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

if ($oid <= 0) out(array('ok' => false, 'error' => 'oid requerido'));

$db = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($db->connect_errno) out(array('ok' => false, 'error' => 'db: ' . $db->connect_error));
$db->set_charset('utf8');

/* ---- Dedup: si ya hay envío OK no anulado para este albarán/pedido, devolverlo ---- */
$st = $alb !== ''
    ? $db->prepare("SELECT * FROM correos_shipments WHERE albaran_id=? AND tipo='envio' AND ok=1 AND cancelled_at IS NULL ORDER BY id DESC LIMIT 1")
    : $db->prepare("SELECT * FROM correos_shipments WHERE orders_id=? AND tipo='envio' AND ok=1 AND cancelled_at IS NULL ORDER BY id DESC LIMIT 1");
if ($alb !== '') $st->bind_param('s', $alb); else $st->bind_param('i', $oid);
$st->execute();
$prev = $st->get_result()->fetch_assoc();
if ($prev) {
    $resp = array('ok' => true, 'dedup' => true, 'shipmentCode' => $prev['shipment_code'],
                  'packageCodes' => array($prev['package_code']), 'tracking_url' => $prev['tracking_url'],
                  'zpl' => null, 'pdf_b64' => null);
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

/* País → ISO-3 (Correos exige alfa-3: ESP...). orders.delivery_country guarda el NOMBRE. */
$iso3 = 'ESP';
$cty = trim((string) $o['delivery_country']);
if ($cty !== '' && strlen($cty) > 3) {
    $st = $db->prepare("SELECT countries_iso_code_3 FROM countries WHERE countries_name=? LIMIT 1");
    $st->bind_param('s', $cty);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) $iso3 = strtoupper($row['countries_iso_code_3']);
} elseif (strlen($cty) === 3) {
    $iso3 = strtoupper($cty);
}
if ($iso3 !== 'ESP') {
    // Salida internacional por Correos: requiere producto/aduanas distintos. De momento solo nacional.
    out(array('ok' => false, 'error' => "pedido $oid con destino $iso3: el envío Correos por API solo cubre nacional (ESP) de momento"));
}

$cp   = trim($o['delivery_postcode']);
$prov = preg_match('/^\d{5}$/', $cp) ? substr($cp, 0, 2) : '';
$gramos = (int) round($kilos * 1000);
$ref = 'F' . $oid;

/* Bultos: peso repartido */
$packages = array();
$porBulto = max(1, (int) floor($gramos / $bultos));
for ($i = 1; $i <= $bultos; $i++) {
    $packages[] = array('packageId' => (string) $i, 'packageWeightGrams' => (string) $porBulto);
}

$shipment = array(
    'product'        => 'PAFXB',          // Paq Estándar
    'deliveryMethod' => 'DOUAOF',         // entrega a domicilio
    'contractNumber' => correos::CONTRACT,
    'clientNumber'   => correos::CLIENT_NUMBER,
    'labellerCode'   => correos::LABELLER,
    'packagesNumber' => (string) $bultos,
    'totalWeight'    => (string) $gramos,
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
    'addressee' => array(
        'name'          => trim($o['delivery_name']) ?: trim($o['delivery_company']),
        'company'       => trim((string) $o['delivery_company']),
        'address'       => trim($o['delivery_street_address'] . ' ' . (string) $o['delivery_suburb']),
        'locality'      => trim($o['delivery_city']),
        'cp'            => $cp,
        'province'      => $prov,
        'country'       => 'ESP',
        'contactPerson' => trim($o['delivery_name']),
        'contactPhone'  => trim((string) $o['customers_telephone']),
        'email'         => trim((string) $o['customers_email_address']),
        'language'      => 'spa',
    ),
    'packages' => $packages,
);

if ($dry) out(array('ok' => true, 'dry' => true, 'payload' => $shipment));

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

if ($code === '' || !$pkgCodes) {
    $err = correos::primerError($pre);
    $st = $db->prepare("INSERT INTO correos_shipments (id_rma, orders_id, albaran_id, tipo, entorno, producto, ref, kilos,
                          http_code, mensaje_retorno, ok, request_json, response_json, operator, date_added)
                        VALUES (0,?,?,'envio','pro','PAFXB',?,?,?,?,0,?,?, 'vstock-watcher', NOW())");
    $http = (string) $pre['http'];
    $req  = json_encode($reqShip, JSON_UNESCAPED_UNICODE);
    $st->bind_param('issdssss', $oid, $alb, $ref, $kilos, $http, $err, $req, $pre['raw']);
    $st->execute();
    out(array('ok' => false, 'error' => $err, 'http' => $pre['http']));
}

/* ---- Etiquetas: ZPL (térmica) + PDF (respaldo). Labels usa el packageCode. ---- */
$zpl = null; $pdfBin = null;
if (in_array($type, array('ZPL', 'BOTH'))) {
    $labZ = $c->getLabel($pkgCodes, array('labelFormat' => 3));   // 3=ZPL, modo A4=1 (combo validado con 2 dio 500)
    if ($labZ['ok'] && !empty($labZ['data']['zpl'])) {
        $z = base64_decode($labZ['data']['zpl'], true);
        if ($z !== false && $z !== '') $zpl = $z;
    }
}
$labP = $c->getLabel($pkgCodes, array('labelFormat' => 2));
if ($labP['ok'] && !empty($labP['pdf_bin'])) $pdfBin = $labP['pdf_bin'];

/* Guardar etiquetas en dir privado (fuera de public_html) */
$dir = '/home/francobordo/correos_labels/' . $oid . '/';
if (!is_dir($dir)) @mkdir($dir, 0750, true);
$zplPath = null; $pdfPath = null;
$suf = substr(md5($code . microtime(true)), 0, 8);
if ($zpl !== null    && @file_put_contents($dir . "correos_{$oid}_{$suf}.zpl", $zpl)    !== false) $zplPath = $dir . "correos_{$oid}_{$suf}.zpl";
if ($pdfBin !== null && @file_put_contents($dir . "correos_{$oid}_{$suf}.pdf", $pdfBin) !== false) $pdfPath = $dir . "correos_{$oid}_{$suf}.pdf";

/* URL pública de seguimiento REAL para el cliente (localizador web, por código de bulto) */
$trackingUrl = 'https://www.correos.es/es/es/herramientas/localizador/envios/detalle?tracking-number=' . rawurlencode($pkgCodes[0]);

$st = $db->prepare("INSERT INTO correos_shipments (id_rma, orders_id, albaran_id, tipo, entorno, shipment_code, package_code,
                      producto, ref, kilos, label_format, label_path, label_zpl_path, tracking_url, http_code,
                      mensaje_retorno, ok, request_json, response_json, operator, date_added)
                    VALUES (0,?,?,'envio','pro',?,?,'PAFXB',?,?,?,?,?,?,?, '', 1, ?, ?, 'vstock-watcher', NOW())");
$fmt  = ($zplPath && $pdfPath) ? 'both' : ($zplPath ? 'zpl' : 'pdf');
$http = (string) $pre['http'];
$req  = json_encode($reqShip, JSON_UNESCAPED_UNICODE);
$raw = $pre['raw'];
$st->bind_param('issssdsssssss', $oid, $alb, $code, $pkgCodes[0], $ref, $kilos,
                $fmt, $pdfPath, $zplPath, $trackingUrl, $http, $req, $raw);
$st->execute();

out(array(
    'ok' => true, 'dedup' => false,
    'shipmentCode' => $code, 'packageCodes' => $pkgCodes,
    'tracking_url' => $trackingUrl,
    'zpl'     => in_array($type, array('ZPL', 'BOTH')) ? $zpl : null,
    'pdf_b64' => in_array($type, array('PDF', 'BOTH')) && $pdfBin !== null ? base64_encode($pdfBin) : null,
));
