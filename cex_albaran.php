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
 * Modos de cola (watcher): reprints=1 / reprint_done=ids (cex_reprint_queue, ZPL del
 * panel) y retries=1 / retry_done=ids (cex_retry_queue, boton "Reintentar envío" para
 * albaranes RENUNCIO; el watcher los descongela de su estado local).
 *
 * Respuesta JSON: { ok, dedup, env, shipmentCode, ecb, ref, tracking_url, zpl,
 *                   recuperado_timeout, error }
 *
 * Anti-duplicados tras timeout: si grabacionEnvio expira (o 502/504), la petición pudo
 * haberse procesado en CEX (respuesta perdida). Antes de fallar -y antes de crear en un
 * reintento- se consulta seguimientoEnvio(ref); si existe un envío reciente no registrado
 * en cex_shipments, se recupera su etiqueta (apiRestEtiquetaTransporte) y se devuelve
 * ok=1 con recuperado_timeout=true en vez de crear un envío fantasma (corte API 20/07/2026).
 *
 * Ver memoria francobordo_correos_express_api.
 */
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING);
ini_set('display_errors', '0');

define('CEX_ALB_TOKEN', 'cexalb_8b3e6d1f4a92');
/* Tope sano de bultos por envío (máximo histórico real = 4; 20 deja margen de sobra).
 * Sin tope, un tecleo erróneo -el nº de pedido en el campo "Bultos", incidente 2026-07-16
 * ref 10364648- hacía que la clase construyese MILLONES de entradas en listaBultos (GB de
 * RAM) y que CEX se atragantase -> timeout de 60s y etiqueta perdida, con un mensaje
 * ("Operation timed out") que no delataba la causa real. */
define('CEX_MAX_BULTOS', 20);

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

/* ---- Modo REINTENTOS: peticiones "Reintentar envío" del panel admin (albaranes a los
 *      que el watcher renunció tras MAX_FALLS y cuyo dato ya corrigió el operario).
 *      El watcher las recoge en cada pasada, saca el albarán de su estado local
 *      processed/fails (misma lógica que cex_unfreeze.py) y ACKea con retry_done; el
 *      albarán se reintenta solo si sigue en su ventana de detección de 48h. ---- */
if (($in['retries'] ?? '') === '1') {
    $db = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
    if ($db->connect_errno) out(array('ok' => false, 'error' => 'db'));
    $db->set_charset('utf8mb4');
    $resq = array();
    $r = $db->query("SELECT id, albaran_id, orders_id FROM cex_retry_queue WHERE done = 0 ORDER BY id LIMIT 20");
    while ($row = $r->fetch_assoc()) {
        $resq[] = array('id' => (int) $row['id'], 'alb' => (string) $row['albaran_id'], 'oid' => (int) $row['orders_id']);
    }
    out(array('ok' => true, 'retries' => $resq));
}
/* ---- ACK del watcher: marca done los reintentos ya descongelados ---- */
if (($in['retry_done'] ?? '') !== '') {
    $db = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
    if ($db->connect_errno) out(array('ok' => false, 'error' => 'db'));
    $ids = array_values(array_filter(array_map('intval', explode(',', (string) $in['retry_done']))));
    if ($ids) {
        $in_ids = implode(',', $ids);
        $db->query("UPDATE cex_retry_queue SET done = 1, done_at = NOW() WHERE id IN ($in_ids)");
    }
    out(array('ok' => true, 'acked' => $ids));
}

$oid    = (int) ($in['oid'] ?? 0);
$kilos  = (float) str_replace(',', '.', (string) ($in['kilos'] ?? '1'));
if ($kilos <= 0) $kilos = 1;
$bultos = max(1, (int) ($in['bultos'] ?? 1));   // 0/ausente -> 1 (albaranes sin ALB_BULTOS)
/* Rechazo explícito por encima del tope: mejor un error claro AQUÍ que una petición
 * absurda que CEX deja expirar (ver CEX_MAX_BULTOS arriba). */
if ($bultos > CEX_MAX_BULTOS) {
    out(array('ok' => false, 'error' => 'bultos fuera de rango (1-' . CEX_MAX_BULTOS . '): ' . $bultos . '. ¿Has escrito el nº de pedido/referencia en el campo Bultos?'));
}
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

/* denia=1 (watcher, albaranes con agencia "Medios Propios"): política 2026-07-29 — los
 * pedidos "Recoger en Tienda DENIA" salen por Correos Express POR DEFECTO. Solo se crea
 * envío si el pedido es retira con CP 03700 (su dirección de entrega ya es la tienda,
 * Marina de Denia Edif. H Local 3); cualquier otro albarán de Medios Propios es reparto
 * propio real y se responde skip (el watcher lo marca procesado y no vuelve a mirarlo). */
if (($in['denia'] ?? '') === '1') {
    $esDenia = strncmp($shipMod, 'retira', 6) === 0
        && strncmp(preg_replace('/\D/', '', (string) ($o['delivery_postcode'] ?? '')), '03700', 5) === 0;
    if (!$esDenia) out(array('ok' => true, 'skip' => 1, 'reason' => 'no es recogida en tienda Denia'));
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
/* Salvaguarda Portugal: pedidos con el PAÍS mal grabado (p.ej. QFac pone "España" en
 * un pedido a Portugal) pero con CP portugués (formato NNNN-NNN). Sin esto se tratan
 * como nacional y CEX rechaza el CP (error 23 "CP NACIONAL FORMATO INCORRECTO").
 * El CP español es siempre 5 dígitos sin guion, así que este patrón es inequívoco. */
if ($iso === 'ES' && preg_match('/(?:^|\D)\d{4}-\d{3}(?:\D|$)/', (string) $o['delivery_postcode'])) $iso = 'PT';

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

/* CANARIAS (35/38), CEUTA (51) y MELILLA (52): BLOQUEADO (pedido del usuario 2026-07-20).
 * Los productos insulares/aduaneros de CEX (66-69) exigen DUA y no están contratados; el 93
 * hacia allí acaba re-facturado. El checkout ya no lo ofrece (MODULE_TIPSA_CP_RESTRINGIDOS);
 * esto corta el resto de caminos (PDA/watcher, panel, manuales). */
if (!$intl && preg_match('/^(35|38|51|52)/', (string) $cpNac) === 1) {
    out(array('ok' => false, 'error' => 'Correos Express NO disponible para Canarias/Ceuta/Melilla (CP ' . $cpNac . '): enviar por otro transportista'));
}
/* BALEARES (CP 07xxx): ePaq24 (93) NO cubre islas -> CEX lo re-factura como Paq 14 (62, caro,
 * ~15-31€). El producto correcto y más barato es "Islas Express" (26, ~6,74€/5kg), que es el
 * que usaba la integración VStock antigua. Solo domicilio (punto/sábado no aplican a islas). */
if ($producto === '93' && strncmp($cpNac, '07', 2) === 0) { $producto = '26'; $entrSabado = ''; }
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
    // Saneo teléfono: solo dígitos (CEX rechaza '?'/espacios/guiones, "TELEFONO NO VALIDO")
    // PERO conservando el '+' inicial si viene (formato internacional E.164, p.ej. +32...).
    'telefDest'     => ((strpos(ltrim((string) $o['customers_telephone']), '+') === 0) ? '+' : '')
                       . preg_replace('/\D/', '', (string) $o['customers_telephone']),
    'emailDest'     => trim((string) $o['customers_email_address']),
    'observac'      => $free ? ('Envio manual CEX / ' . $freeRef)
                             : ('Pedido ' . ($manual ? 'QFac ' : 'web ') . $oid . ($alb !== '' ? ' / Albaran ' . $alb : '')),
);
if ($entrSabado !== '') $opt['entrSabado'] = 'S';
if ($pudoId !== '')     $opt['idPtoExterno'] = $pudoId;

if ($dry) out(array('ok' => true, 'dry' => true, 'env' => $env, 'producto' => $producto,
                    'entrSabado' => $entrSabado, 'idPtoExterno' => $pudoId, 'ref' => $ref, 'payload' => $opt));

/* ---- Anti-duplicados tras TIMEOUT --------------------------------------------------
 * Un timeout de grabacionEnvio es AMBIGUO: la petición pudo procesarse en CEX y perderse
 * solo la respuesta. Reintentar a ciegas crea envíos fantasma (que CEX puede facturar):
 * en el corte API del 20/07/2026 los reintentos del watcher dejaron 3 envíos por pedido
 * en F10365421/F10365403. Antes de dar el alta por fallida se pregunta a CEX si la ref ya
 * tiene un envío que NO tengamos registrado (seguimientoEnvio devuelve el ÚLTIMO de la
 * ref); si existe y es reciente, se recupera su etiqueta (apiRestEtiquetaTransporte) y se
 * responde como si el alta hubiera ido bien. Solo para timeouts, nunca errores de negocio. */
function cexTimedOut(array $res) {
    $http = (int) ($res['http'] ?? 0);
    if (in_array($http, array(502, 504), true)) return true;   // gateway sin respuesta del backend: igual de ambiguo
    return $http === 0 && preg_match('/timed?\s*out/i', (string) ($res['error'] ?? ''));
}

/** Si la ref ya tiene en CEX un envío reciente que no está en cex_shipments, recupera su
 *  etiqueta y devuelve un pseudo-$d con la forma de la respuesta de grabacionEnvio.
 *  Devuelve null si la ref no existe en CEX (reintentar es seguro: error 100 "NO
 *  ENCONTRADO" / 999 "BUSCAR_EXPEDICION") o si no se pudo concluir (mejor fallar). */
function cexRecuperarEnvioPerdido(correos_express $cex, mysqli $db, $ref, $env) {
    $seg = $cex->seguimientoEnvio($ref);
    $sd  = is_array($seg['data'] ?? null) ? $seg['data'] : array();
    if (!$seg['ok'] || (int) ($sd['error'] ?? -1) !== 0) return null;
    $numEnvio = trim((string) ($sd['numEnvio'] ?? ''));
    if ($numEnvio === '') return null;

    /* Si ese numEnvio ya está registrado aquí, NO lo creó la petición perdida (es un envío
     * anterior legítimo de la misma ref: otro albarán del pedido, un "modificar" viejo...). */
    $st = $db->prepare("SELECT id FROM cex_shipments WHERE shipment_code=? AND entorno=? LIMIT 1");
    $st->bind_param('ss', $numEnvio, $env);
    $st->execute();
    if ($st->get_result()->fetch_assoc()) return null;

    /* Solo envíos recientes (fecha DD/MM/AA, <=7 días): un desconocido antiguo no es nuestro. */
    $ts = false;
    if (preg_match('#^(\d{2})/(\d{2})/(\d{2})$#', trim((string) ($sd['fecha'] ?? '')), $m)) {
        $ts = mktime(12, 0, 0, (int) $m[2], (int) $m[1], 2000 + (int) $m[3]);
    }
    if ($ts === false || $ts < time() - 7 * 86400) return null;

    $et = $cex->etiquetaTransporte($numEnvio, '2');
    $ed = is_array($et['data'] ?? null) ? $et['data'] : array();
    if (!$et['ok'] || (int) ($ed['codErr'] ?? -1) !== 0
        || empty($ed['listaEtiquetas']) || !is_array($ed['listaEtiquetas'])) return null;

    $labels = array(); $zplAll = '';
    foreach ($ed['listaEtiquetas'] as $z) {
        $z = (string) $z;
        if ($z === '') continue;
        $labels[] = array('etiqueta2' => $z);   // misma forma que grabacionEnvio (ZPL texto plano)
        $zplAll  .= $z;
    }
    if (!$labels) return null;
    /* ECB (código de barras del bulto) desde el propio ZPL; ojo: no es el numEnvio literal. */
    $ecb = preg_match('/\^FD(\d{18,30})\^FS/', $zplAll, $m) ? $m[1] : '';

    return array(
        'codigoRetorno'  => 0,
        'mensajeRetorno' => '',
        'datosResultado' => $numEnvio,
        'listaBultos'    => $ecb !== '' ? array(array('codUnico' => $ecb)) : array(),
        'etiqueta'       => $labels,
        'recuperadoTrasTimeout' => 1,   // marca de evidencia en response_json
    );
}

/* ---- Crear envio + etiqueta (una sola llamada) ---- */
$cex = new correos_express($env);
$cex->setTimeout(60);

/* Pre-chequeo: si el intento ANTERIOR para este mismo albarán/pedido murió por timeout,
 * la petición pudo llegar a CEX -> preguntar ANTES de crear otro (mata el fantasma del
 * reintento incluso si la recuperación en caliente falló durante el corte). Solo en el
 * flujo normal: en regen el operario puede cambiar datos entre intentos y en free la
 * ref es nueva en cada intento. */
$recovered = false;
$d = array();
$res = array('ok' => true, 'http' => 200, 'error' => '');
if (!$regen && !$free) {
    $stPT = $alb !== ''
        ? $db->prepare("SELECT id FROM cex_shipments WHERE albaran_id=? AND entorno=? AND ok=0
                          AND (mensaje_retorno LIKE '%timed out%' OR http_code IN ('502','504'))
                          AND date_added > DATE_SUB(NOW(), INTERVAL 7 DAY) LIMIT 1")
        : $db->prepare("SELECT id FROM cex_shipments WHERE orders_id=? AND entorno=? AND ok=0
                          AND (mensaje_retorno LIKE '%timed out%' OR http_code IN ('502','504'))
                          AND date_added > DATE_SUB(NOW(), INTERVAL 7 DAY) LIMIT 1");
    if ($alb !== '') $stPT->bind_param('ss', $alb, $env); else $stPT->bind_param('is', $oid, $env);
    $stPT->execute();
    if ($stPT->get_result()->fetch_assoc()) {
        $dRec = cexRecuperarEnvioPerdido($cex, $db, $ref, $env);
        if ($dRec !== null) { $d = $dRec; $recovered = true; }
    }
}

if (!$recovered) {
    $res = $cex->grabacionEnvio($opt);
    $d   = $res['data'] ?? array();
    $cod = isset($d['codigoRetorno']) ? (int) $d['codigoRetorno'] : -1;
    $msg = (string) ($d['mensajeRetorno'] ?? ($res['error'] ?? 'sin respuesta'));

    /* Timeout ambiguo: ¿llegó a crearse? Si CEX ya lo tiene, recuperar en vez de fallar
     * (el reintento del watcher crearía OTRO envío). */
    if ((!$res['ok'] || $cod !== 0) && cexTimedOut($res)) {
        $dRec = cexRecuperarEnvioPerdido($cex, $db, $ref, $env);
        if ($dRec !== null) {
            $d = $dRec; $recovered = true;
            $res = array('ok' => true, 'http' => 200, 'error' => '');
        }
    }

    if (!$recovered && (!$res['ok'] || $cod !== 0)) {
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
                    VALUES (0,?,?,'envio',?,?,?,?,?,?,?,?,?,?,?,?, ?, 1, ?, ?, 'vstock-watcher', NOW())");
$fmt = $zplPath ? 'zpl' : '';
$http = (string) ($res['http'] ?? '');
$msgOk = $recovered ? 'Recuperado tras timeout: el envio ya existia en CEX (etiquetaTransporte)' : '';
$reqj = json_encode($opt, JSON_UNESCAPED_UNICODE);
$rawj = json_encode($d, JSON_UNESCAPED_UNICODE);
$pudoIdDb   = ($pudoId !== '') ? $pudoId : null;
$pudoNameDb = ($pudoName !== '') ? $pudoName : null;
$st->bind_param('issssssssdsssssss', $oid, $alb, $env, $numEnvio, $ecb, $producto, $pudoIdDb, $pudoNameDb,
                $ref, $kilos, $fmt, $zplPath, $trackingUrl, $http, $msgOk, $reqj, $rawj);
$st->execute();
$newId = $db->insert_id;

/* Tracking visible AL INSTANTE (cliente "Envío registrado" + caja admin), sin esperar
 * al cron horario. El cron irá pisando estado/eventos según CEX escanee. En regen
 * (modificar) resetea el timeline: el envío es nuevo. */
$st2 = $db->prepare("INSERT INTO cex_tracking (referencia, num_envio, estado_code, estado_desc, entregado, last_checked)
                     VALUES (?, ?, '1', 'Envío registrado', 0, NOW())
                     ON DUPLICATE KEY UPDATE num_envio=VALUES(num_envio), estado_code='1',
                       estado_desc=VALUES(estado_desc), entregado=0, last_checked=NOW(),
                       timeline_json=NULL, timeline_at=NULL");
$st2->bind_param('ss', $ref, $numEnvio);
$st2->execute();

out(array(
    'ok' => true, 'dedup' => false, 'env' => $env, 'shipment_id' => $newId,
    'shipmentCode' => $numEnvio, 'ecb' => $ecb, 'ref' => $ref, 'producto' => $producto,
    'bultos' => $bultos, 'labels' => $nLabels,
    'recuperado_timeout' => $recovered,   // true = etiqueta de un envío ya existente (no se creó otro)
    'entrSabado' => $entrSabado, 'idPtoExterno' => $pudoId, 'tracking_url' => $trackingUrl,
    'zpl' => in_array($type, array('ZPL', 'BOTH')) ? $zpl : null,
));
