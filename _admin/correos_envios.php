<?php
/**
 * Panel de envíos Correos (salida + devoluciones) — _admin/correos_envios.php
 * Gemelo de seur_envios.php para la integración Correos de España.
 *
 *   - Anular     : annulment por packageCode (multibulto). Tolera "ya anulado" como éxito
 *                  idempotente (correosCancelSoft). Si falla en duro ENCOLA cancel_requested_at
 *                  (sin re-pisar el reloj) y el cron (cron_correos_tracking.php) reintenta.
 *   - Reimprimir : encola el ZPL (correos_reprint_queue) → el watcher .112 lo recoge por HTTP
 *                  (correos_reprint.php) y lo vuelca a la impresora del almacén.
 *   - Modificar  : "Anular y regenerar" (peso/bultos/destino). Solo pedidos WEB (los QFac-nativos
 *                  NO, porque requieren dirección de Vstock que el panel no maneja → se anularía
 *                  sin poder regenerar). Tras regenerar comprueba si SÍ se creó (anti bola-de-nieve).
 *
 * Seguridad: CSRF de sesión + PRG. Correos siempre 'pro'. Ver memoria francobordo_correos_api.
 */
require 'includes/application_top.php';
require_once DIR_FS_CATALOG . 'includes/classes/correos.php';

set_time_limit(120);
define('CORREOS_ALB_TOKEN', 'correosalb_e7c41f92b5');

$msg = ''; $msgClass = '';

if (empty($_SESSION['correos_csrf'])) $_SESSION['correos_csrf'] = tep_create_random_value(32);
$csrf = $_SESSION['correos_csrf'];

if (!empty($_SESSION['correos_flash'])) {
    $msg = $_SESSION['correos_flash']['m']; $msgClass = $_SESSION['correos_flash']['c'];
    unset($_SESSION['correos_flash']);
}

/* packageCodes de un envío (multibulto): del response_json o el package_code. */
function correosEnvPkgCodes($s) {
    $codes = array();
    if (!empty($s['response_json'])) {
        $j = json_decode($s['response_json'], true);
        $pk = $j['data']['shipments'][0]['packages'] ?? null;
        if (is_array($pk)) foreach ($pk as $p) if (!empty($p['packageCode'])) $codes[] = (string) $p['packageCode'];
    }
    if (!$codes && !empty($s['package_code'])) $codes = array((string) $s['package_code']);
    return $codes;
}

/* Error "suave" de anulación: 'ya anulado'/'no existe' = éxito idempotente (patrón seurCancelSoft). */
function correosCancelSoft($desc) {
    $d = mb_strtolower((string) $desc);
    return (strpos($d, 'ya anulad') !== false || strpos($d, 'no existe') !== false
         || strpos($d, 'inexist') !== false || strpos($d, 'not found') !== false
         || strpos($d, 'no encontrado') !== false);
}

/* Mensaje de la respuesta de ANULACIÓN (donde Correos dice 'ya anulado'), no el del preregistro. */
function correosAnnMsg($res) {
    if (is_array($res['data'] ?? null)) return (string) ($res['data']['message'] ?? ($res['data']['error'] ?? ''));
    return '';
}

/* Anula todos los packageCodes tolerando "ya anulado". Devuelve [bool ok, array anulados, str err, str failPc]. */
function correosCancelPkgs($c, $pkgs) {
    $ann = array(); $ok = !empty($pkgs); $err = $pkgs ? '' : 'sin packageCodes'; $failPc = '';
    foreach ($pkgs as $pc) {
        $res = $c->annulment($pc, 'spa', 3);
        $am  = correosAnnMsg($res);
        if (correos::annulmentOk($res) || correosCancelSoft($am)) { $ann[] = $pc; }
        else { $ok = false; $err = ($am !== '' ? $am : correos::primerError($res)); $failPc = $pc; break; }
    }
    return array($ok, $ann, $err, $failPc);
}

/* Estado de seguimiento por ref (correos_tracking, cron horario). */
function correosEnvTrackRow($ref) {
    $ref = trim((string) $ref);
    if ($ref === '') return null;
    $q = tep_db_query("SELECT estado_desc, entregado FROM correos_tracking WHERE referencia = '" . tep_db_input($ref) . "' LIMIT 1");
    return tep_db_num_rows($q) ? tep_db_fetch_array($q) : null;
}

/* ¿Modificable? Solo pre-tránsito. */
function correosEnvModEligible($trk) {
    if (!$trk) return true;
    if ((int) $trk['entregado'] === 1) return false;
    return !preg_match('/reparto|tr[aá]nsito|transito|en camino|entregad|devoluc/i', (string) $trk['estado_desc']);
}

/* ¿Es un pedido web (no QFac-nativo)? Los QFac-nativos (serie 26xxxxx) no se modifican aquí. */
function correosEsWeb($oid) { return ((int) $oid >= 10000000); }

function correosEnvEndpoint($params) {
    $params['token'] = CORREOS_ALB_TOKEN;
    $ch = curl_init('https://www.francobordo.com/correos_albaran.php?' . http_build_query($params));
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 120, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2));
    $raw = curl_exec($ch); $err = curl_error($ch); // sin curl_close(): deprecado en PHP 8.5
    return array(json_decode((string) $raw, true), $err, $raw);
}

/* ---- Acciones (POST con CSRF) ---- */
$action = $_POST['do'] ?? '';
$shipId = (int) ($_POST['ship'] ?? 0);

/* === Envio manual Correos (sin pedido): crea un envio libre y encola su etiqueta para la impresora === */
if (($_POST['do'] ?? '') === 'crear_manual') {
    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $_SESSION['correos_flash'] = array('m' => 'Token CSRF invalido.', 'c' => 'danger');
    } else {
        $g = function ($k) { return trim((string) ($_POST[$k] ?? '')); };
        $dname = $g('m_dname'); $dphone = $g('m_dphone'); $demail = $g('m_demail');
        $mkilos = str_replace(',', '.', $g('m_kilos')); if ((float) $mkilos <= 0) $mkilos = '1';
        $mbultos = max(1, min(10, (int) ($_POST['m_bultos'] ?? 1)));
        $mref = $g('m_ref'); if ($mref === '') $mref = 'M-' . date('ymd-His') . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
        $mdvalue = str_replace(',', '.', $g('m_dvalue')); $mddesc = $g('m_ddesc');
        $mddoi   = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $g('m_ddoi')));
        $mmode = ($g('m_mode') === 'ofi') ? 'ofi' : 'dom';
        // Pais destino ISO-3. Internacional (!= ESP) => Paq Estandar Internacional (PAAXI), SOLO domicilio.
        $mcountry = strtoupper(preg_replace('/[^A-Za-z]/', '', $g('m_dcountry')));
        if (strlen($mcountry) !== 3) $mcountry = 'ESP';
        if ($mcountry === 'ROM') $mcountry = 'ROU';   // `countries` guarda el alfa-3 antiguo de Rumania
        $mesES = ($mcountry === 'ESP');
        if (!$mesES) $mmode = 'dom';   // no hay recogida en oficina de Correos fuera de Espana
        $err = ''; $dest = null;
        if ($mmode === 'ofi') {
            // Entrega en OFICINA: la direccion del destinatario ES la oficina; chosenOffice = su codigo.
            // OFUAOF exige email del destinatario (Correos le avisa cuando el paquete llega a la oficina).
            $office = preg_replace('/[^0-9A-Za-z]/', '', $g('m_office'));
            $ocp    = preg_replace('/\D/', '', $g('m_office_cp'));
            if ($dname === '' || $office === '' || $ocp === '') {
                $err = 'Para entrega en oficina: indica el destinatario y elige una oficina de la lista (busca por CP).';
            } elseif ($demail === '') {
                $err = 'Para entrega en oficina, el email del destinatario es obligatorio (Correos le avisa cuando llega el paquete).';
            } else {
                $dest = array('mode' => 'ofi', 'oficina' => $office, 'dname' => $dname,
                    'dstreet' => $g('m_office_addr'), 'dcp' => $ocp, 'dcity' => $g('m_office_city'), 'dstate' => '');
            }
        } else {
            $dstreet = $g('m_dstreet'); $dcp = $g('m_dcp'); $dcity = $g('m_dcity'); $dstate = $g('m_dstate');
            if ($dname === '' || $dstreet === '' || $dcp === '' || $dcity === '') {
                $err = 'Faltan campos obligatorios: destinatario, direccion, CP y ciudad.';
            } else {
                $dest = array('mode' => 'dom', 'dname' => $dname, 'dstreet' => $dstreet, 'dcp' => $dcp, 'dcity' => $dcity, 'dstate' => $dstate);
            }
        }
        // Aduanas: islas espanolas (Canarias/Ceuta/Melilla, por CP) o destino fuera de la UE-27.
        // Mismo criterio que correos_albaran.php, que ademas exige un solo bulto (la DUA/CN23 va
        // completa en un paquete, asi que los bultos 2..N viajarian sin declaracion).
        if ($err === '' && $dest) {
            $ue27 = array('AUT','BEL','BGR','HRV','CYP','CZE','DNK','EST','FIN','FRA','DEU','GRC','HUN','IRL','ITA',
                          'LVA','LTU','LUX','MLT','NLD','POL','PRT','ROU','SVK','SVN','ESP','SWE');
            $cpd = preg_replace('/\D/', '', (string) $dest['dcp']);
            $aduanas = $mesES ? in_array(substr($cpd, 0, 2), array('35', '38', '51', '52'), true)
                              : !in_array($mcountry, $ue27, true);
            $doiOk = (bool) preg_match('/^(\d{8}[A-Z]|[XYZ]\d{7}[A-Z]|[A-HJ-NP-SUVW]\d{7}[0-9A-J])$/', $mddoi);
            if ($aduanas && ((float) $mdvalue <= 0 || $mddesc === ''))
                $err = 'Destino con aduanas (' . htmlspecialchars($mesES ? $cpd : $mcountry) . '): el valor declarado y el contenido son obligatorios (declaracion DUA/CN23).';
            elseif ($aduanas && $mesES && !$doiOk)
                $err = 'Destino Canarias/Ceuta/Melilla: Correos exige el DNI/NIF/NIE del destinatario para la DUA' . ($mddoi === '' ? '.' : ' ("' . htmlspecialchars($mddoi) . '" no tiene formato valido: 12345678Z, X1234567L o B12345678).');
            elseif ($aduanas && !$mesES && $mddoi !== '' && !$doiOk)
                $err = 'El documento del destinatario "' . htmlspecialchars($mddoi) . '" no tiene formato valido (DNI 12345678Z, NIE X1234567L o CIF B12345678); dejalo vacio si no lo tienes.';
            elseif ($aduanas && $mbultos > 1)
                $err = 'Destino con aduanas: usa un solo bulto (la declaracion DUA/CN23 debe ir completa en un paquete).';
        }
        if ($err !== '') {
            $_SESSION['correos_flash'] = array('m' => $err, 'c' => 'danger');
        } else {
            $params = array_merge(array('token' => CORREOS_ALB_TOKEN, 'free' => '1', 'ref' => $mref,
                'dcountry' => $mcountry, 'dphone' => $dphone, 'demail' => $demail,
                'kilos' => $mkilos, 'bultos' => $mbultos, 'type' => 'ZPL'), $dest);
            if ($mdvalue !== '' && (float) $mdvalue > 0) $params['dvalue'] = $mdvalue;
            if ($mddesc !== '') $params['ddesc'] = $mddesc;
            if ($mddoi !== '') $params['ddoi'] = $mddoi;
            $ch = curl_init('https://www.francobordo.com/correos_albaran.php');
            curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 120, CURLOPT_POST => 1,
                CURLOPT_POSTFIELDS => http_build_query($params), CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2));
            $raw = curl_exec($ch); $cerr = curl_error($ch); // sin curl_close(): deprecado en PHP 8.5
            $resp = json_decode((string) $raw, true);
            if (is_array($resp) && !empty($resp['ok']) && !empty($resp['shipmentCode'])) {
                if (!empty($resp['zpl']) || !empty($resp['cn23_pcl_b64'])) {
                    // cn23_pcl_b64 = documentos de exportacion (DUA/CN23) en PCL A4; sin el, un manual
                    // a Canarias imprimia la etiqueta pero NUNCA los papeles de aduana.
                    $qrow = array('shipment_id' => 0, 'orders_id' => 0, 'zpl' => (string) ($resp['zpl'] ?? ''), 'done' => 0, 'date_added' => 'now()');
                    if (!empty($resp['cn23_pcl_b64'])) $qrow['cn23_pcl'] = $resp['cn23_pcl_b64'];
                    tep_db_perform('correos_reprint_queue', $qrow);
                }
                $donde = ($mmode === 'ofi') ? ' (entrega en oficina)' : ($mesES ? '' : ' (internacional ' . htmlspecialchars($mcountry) . ')');
                $extra = empty($resp['zpl']) ? ' (sin ZPL: descarga el PDF / reimprime desde el listado).' : ' La etiqueta saldra por la impresora del almacen en ~1 min.';
                $_SESSION['correos_flash'] = array('m' => 'Envio manual creado' . $donde . ': <strong>' . htmlspecialchars($resp['shipmentCode']) . '</strong> (ref ' . htmlspecialchars($mref) . ').' . $extra, 'c' => 'success');
            } else {
                $why = is_array($resp) ? ($resp['error'] ?? 'respuesta no concluyente') : ('sin respuesta' . ($cerr ? ': ' . $cerr : ''));
                $_SESSION['correos_flash'] = array('m' => 'No se pudo crear el envio manual: ' . htmlspecialchars($why), 'c' => 'danger');
            }
        }
    }
    tep_redirect(tep_href_link('correos_envios.php'));
}

if ($action !== '' && $shipId > 0) {
    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $msg = 'Sesión caducada o petición no válida. Recarga la página e inténtalo de nuevo.'; $msgClass = 'danger';
    } else {
        $q = tep_db_query('SELECT * FROM correos_shipments WHERE id = ' . $shipId);
        $s = tep_db_fetch_array($q);
        if (!$s) {
            $msg = 'Envío no encontrado.'; $msgClass = 'danger';
        } else {
            $env  = ($s['entorno'] === 'pruebas') ? 'pruebas' : 'pro';
            $pkgs = correosEnvPkgCodes($s);

            /* ----- ANULAR ----- */
            if ($action === 'cancel') {
                if (!empty($s['cancelled_at'])) {
                    $msg = 'Ese envío ya estaba anulado.'; $msgClass = 'warning';
                } elseif (!empty($s['cancel_requested_at'])) {
                    // Ya encolado: NO re-llamar a la API ni re-pisar el reloj de 24 h. El cron reintenta.
                    $msg = 'La anulación ya está pendiente (encolada ' . htmlspecialchars($s['cancel_requested_at']) . '); el cron la reintenta cada hora.'; $msgClass = 'warning';
                } elseif (!$pkgs) {
                    $msg = 'No hay packageCodes que anular en este envío.'; $msgClass = 'danger';
                } else {
                    $c = new correos($env); $c->setTimeout(60);
                    list($allOk, $ann, $lastErr, $failPc) = correosCancelPkgs($c, $pkgs);
                    if ($allOk) {
                        tep_db_query("UPDATE correos_shipments SET cancelled_at = now(), cancel_requested_at = NULL WHERE id = " . $shipId);
                        $msg = 'Envío ' . htmlspecialchars($s['shipment_code']) . ' anulado en Correos (' . count($pkgs) . ' bulto(s)).'; $msgClass = 'success';
                    } else {
                        // Fallo (a veces parcial). Encolar SIN re-pisar el reloj + anotar qué bultos se anularon.
                        $det = 'Anulación parcial ' . count($ann) . '/' . count($pkgs) . ': OK [' . implode(',', $ann) . '] falló ' . $failPc . ' (' . $lastErr . ')';
                        tep_db_query("UPDATE correos_shipments SET cancel_requested_at = COALESCE(cancel_requested_at, now()), mensaje_retorno = '" . tep_db_input(substr($det, 0, 500)) . "' WHERE id = " . $shipId);
                        $msg = 'Anulados ' . count($ann) . ' de ' . count($pkgs) . ' bulto(s); el resto se ENCOLÓ y el cron lo reintenta (alerta si >24 h). ' . htmlspecialchars($lastErr); $msgClass = 'warning';
                    }
                }

            /* ----- REIMPRIMIR ----- */
            } elseif ($action === 'reprint') {
                if ($s['tipo'] === 'devolucion') {
                    $msg = 'Las devoluciones llevan etiqueta PDF: descárgala/reenvíala desde el RMA (no van por la impresora de etiquetas).'; $msgClass = 'warning';
                } elseif (empty($s['ok']) || !empty($s['cancelled_at'])) {
                    $msg = 'Solo se reimprimen envíos activos y con etiqueta correcta.'; $msgClass = 'warning';
                } else {
                    $zpl = ''; $cn23b64 = '';
                    if (!empty($s['label_zpl_path']) && is_file($s['label_zpl_path'])) $zpl = (string) file_get_contents($s['label_zpl_path']);
                    // CN23 (aduanas): el endpoint lo cachea junto al PDF de la etiqueta
                    if (!empty($s['label_path']) && is_file($s['label_path'] . '.cn23.pcl'))
                        $cn23b64 = base64_encode((string) file_get_contents($s['label_path'] . '.cn23.pcl'));
                    if ($zpl === '' && (int) $s['orders_id'] > 0) {
                        list($resp, , ) = correosEnvEndpoint(array('oid' => (int) $s['orders_id'], 'type' => 'ZPL'));
                        if (is_array($resp) && !empty($resp['zpl'])) $zpl = (string) $resp['zpl'];
                        if ($cn23b64 === '' && is_array($resp) && !empty($resp['cn23_pcl_b64'])) $cn23b64 = (string) $resp['cn23_pcl_b64'];
                    }
                    if ($zpl !== '') {
                        $qrow = array(
                            'shipment_id' => $shipId, 'orders_id' => (int) $s['orders_id'],
                            'zpl' => $zpl, 'done' => 0, 'date_added' => 'now()');
                        if ($cn23b64 !== '') $qrow['cn23_pcl'] = $cn23b64;
                        tep_db_perform('correos_reprint_queue', $qrow);
                        $msg = 'Reimpresión encolada para ' . htmlspecialchars($s['shipment_code']) . '. Saldrá por la impresora del almacén en ~1 min.'; $msgClass = 'success';
                    } else {
                        $msg = 'No se pudo obtener el ZPL para reimprimir (ni en disco ni regenerándolo).'; $msgClass = 'danger';
                    }
                }

            /* ----- MODIFICAR (anular y regenerar) ----- */
            } elseif ($action === 'modify') {
                $oidM = (int) $s['orders_id'];
                $err = '';
                if ($s['tipo'] !== 'envio')         $err = 'Solo se regeneran envíos de salida (las devoluciones se gestionan en el RMA).';
                elseif (!empty($s['cancelled_at']))  $err = 'Ese envío ya está anulado.';
                elseif (!correosEsWeb($oidM))        $err = 'Los pedidos QFac-nativos no se modifican desde aquí (requieren la dirección de Vstock). Anúlalo y regenéralo desde el flujo de Vstock/QFac.';
                if ($err === '') {
                    $trkE = correosEnvTrackRow($s['ref']);
                    if (!correosEnvModEligible($trkE))
                        $err = 'Este envío ya está en tránsito (' . htmlspecialchars($trkE['estado_desc'] ?? '') . '); no se puede modificar.';
                }
                if ($err !== '') { $msg = $err; $msgClass = 'warning'; }
                else {
                    $kilosM  = str_replace(',', '.', trim((string) ($_POST['kilos'] ?? '1')));
                    if ((float) $kilosM <= 0) $kilosM = '1';
                    $bultosM = max(1, min(10, (int) ($_POST['bultos'] ?? 1)));
                    $modo    = $_POST['destino'] ?? 'mantener';
                    $officeId = '';
                    if ($modo === 'oficina') {
                        $officeId = preg_replace('/[^0-9A-Za-z]/', '', (string) ($_POST['oficina'] ?? ''));
                        if ($officeId === '') $err = 'Elige una oficina de Correos de la lista.';
                    }
                    if ($err !== '') { $msg = $err; $msgClass = 'danger'; }
                    else {
                        $lockName = 'corrmod_' . $oidM;
                        $lk = tep_db_query("SELECT GET_LOCK('" . tep_db_input($lockName) . "', 0) AS l");
                        $lkr = tep_db_fetch_array($lk);
                        if ((int) ($lkr['l'] ?? 0) !== 1) {
                            $msg = 'Hay otra operación en curso para este pedido; espera unos segundos y reintenta.'; $msgClass = 'warning';
                        } else {
                            // 1) ANULAR el actual y VERIFICAR (tolerando "ya anulado").
                            $c = new correos($env); $c->setTimeout(60);
                            list($cOk, , $cErr, ) = correosCancelPkgs($c, $pkgs);
                            if (!$cOk) {
                                $msg = 'NO se ha regenerado: Correos no confirmó la anulación del envío actual (' . htmlspecialchars($cErr) . '). El envío original sigue ACTIVO. Reinténtalo en un momento.'; $msgClass = 'danger';
                                tep_db_query("SELECT RELEASE_LOCK('" . tep_db_input($lockName) . "')");
                            } else {
                                tep_db_query("UPDATE correos_shipments SET cancelled_at = now() WHERE id = " . $shipId);
                                $params = array('oid' => $oidM, 'kilos' => $kilosM, 'bultos' => $bultosM, 'type' => 'BOTH');
                                /* correos_albaran.php deduce el servicio del PEDIDO (fila espejo,
                                 * shipping_module o titulo de envio) e IGNORA 'mode' cuando hay oid.
                                 * Aqui solo se manda 'svc' cuando el operario FUERZA el servicio;
                                 * 'mantener' no manda nada y deja que el endpoint lo deduzca (asi
                                 * respeta los pedidos de modulos de oficina antiguos, sin fila espejo). */
                                if ($modo === 'oficina') {
                                    tep_db_query('DELETE FROM correos_oficina_orders WHERE orders_id = ' . $oidM);
                                    tep_db_perform('correos_oficina_orders', array(
                                        'orders_id' => $oidM, 'office_id' => $officeId,
                                        'name' => substr((string) ($_POST['ofi_name'] ?? ''), 0, 120),
                                        'address' => substr((string) ($_POST['ofi_addr'] ?? ''), 0, 255),
                                        'postcode' => substr(preg_replace('/\D/', '', (string) ($_POST['ofi_cp'] ?? '')), 0, 10),
                                        'city' => substr((string) ($_POST['ofi_city'] ?? ''), 0, 80),
                                        'date_added' => 'now()'));
                                    $params['svc'] = 'ofi';
                                } elseif ($modo === 'domicilio') {
                                    tep_db_query('DELETE FROM correos_oficina_orders WHERE orders_id = ' . $oidM);
                                    $params['svc'] = 'dom';   // fuerza domicilio aunque el modulo del pedido sea de oficina
                                }
                                list($resp, $cerr, ) = correosEnvEndpoint($params);
                                $newCode = (is_array($resp) && !empty($resp['ok']) && empty($resp['dedup']) && !empty($resp['shipmentCode'])) ? $resp['shipmentCode'] : '';
                                if ($newCode !== '' && $newCode !== $s['shipment_code']) {
                                    if (!empty($resp['zpl'])) {
                                        $qrow = array('shipment_id' => 0, 'orders_id' => $oidM, 'zpl' => $resp['zpl'], 'done' => 0, 'date_added' => 'now()');
                                        if (!empty($resp['cn23_pcl_b64'])) $qrow['cn23_pcl'] = $resp['cn23_pcl_b64'];
                                        tep_db_perform('correos_reprint_queue', $qrow);
                                    }
                                    $msg = 'Envío regenerado: nuevo código <strong>' . htmlspecialchars($newCode) . '</strong>. El anterior queda anulado; la nueva etiqueta saldrá por la impresora en ~1 min. <strong>Descarta la etiqueta anterior.</strong>'; $msgClass = 'success';
                                } else {
                                    // ¿Se creó pese a respuesta no clara? (persistencia temprana del endpoint) — evitar reintento que anularía el bueno.
                                    $chk = tep_db_query("SELECT shipment_code FROM correos_shipments WHERE orders_id = " . $oidM . " AND tipo = 'envio' AND ok = 1 AND cancelled_at IS NULL AND date_added > DATE_SUB(NOW(), INTERVAL 5 MINUTE) ORDER BY id DESC LIMIT 1");
                                    if (tep_db_num_rows($chk)) {
                                        $rk = tep_db_fetch_array($chk);
                                        $msg = 'El envío anterior se anuló y parece que SÍ se creó uno nuevo (<code>' . htmlspecialchars($rk['shipment_code']) . '</code>), pero la respuesta no fue clara. Revisa el listado antes de reintentar (un reintento podría anular el envío bueno).'; $msgClass = 'warning';
                                    } else {
                                        $why = is_array($resp) ? ($resp['error'] ?? (!empty($resp['dedup']) ? 'el endpoint devolvió un envío existente (dedup), no uno nuevo' : 'respuesta no concluyente')) : ('sin respuesta' . ($cerr ? ': ' . $cerr : ''));
                                        $msg = 'El envío anterior fue ANULADO pero la regeneración no se completó (' . htmlspecialchars($why) . '). Revisa el listado: si NO aparece un envío nuevo para este pedido, reintenta Modificar.'; $msgClass = 'danger';
                                    }
                                }
                                tep_db_query("SELECT RELEASE_LOCK('" . tep_db_input($lockName) . "')");
                            }
                        }
                    }
                }
            }
        }
    }

    $_SESSION['correos_flash'] = array('m' => $msg, 'c' => $msgClass);
    $qs = 'tipo=' . urlencode($_GET['tipo'] ?? 'todos') . '&estado=' . urlencode($_GET['estado'] ?? 'todos') . '&q=' . urlencode($_GET['q'] ?? '');
    tep_redirect(tep_href_link('correos_envios.php', $qs));
}

/* ---- Filtros y listado ---- */
$fTipo   = $_GET['tipo']   ?? 'todos';
$fEstado = $_GET['estado'] ?? 'todos';
$fBuscar = trim($_GET['q'] ?? '');
$where = array('1=1');
if ($fTipo === 'envio')      $where[] = "tipo = 'envio'";
if ($fTipo === 'devolucion') $where[] = "tipo = 'devolucion'";
if ($fEstado === 'ok')       $where[] = "ok = 1 AND cancelled_at IS NULL AND cancel_requested_at IS NULL";
if ($fEstado === 'error')    $where[] = "ok = 0 AND cancelled_at IS NULL";
if ($fEstado === 'anulado')  $where[] = "cancelled_at IS NOT NULL";
if ($fEstado === 'encolado') $where[] = "cancel_requested_at IS NOT NULL AND cancelled_at IS NULL";
if ($fBuscar !== '') {
    $b = tep_db_input($fBuscar);
    // shipment_code (cod. envio, 16 dig.) y package_code (cod. localizacion, 23 dig., el de la
    // etiqueta y el tracking) son DISTINTOS: hay que mirar los dos. Los bultos 2..N del multibulto
    // solo guardan su package_code en response_json (el campo package_code = solo el 1er bulto).
    $clauses = array(
        "shipment_code LIKE '%$b%'",
        "package_code LIKE '%$b%'",
        "ref LIKE '%$b%'",
    );
    if (preg_match('/[A-Za-z]/', $fBuscar) && strlen($fBuscar) >= 8) {
        $clauses[] = "response_json LIKE '%$b%'";   // codigo de un bulto secundario (multibulto)
    }
    // Solo si es un numero: pedido / RMA exactos. Con texto NO se aplica (evita que id_rma=0,
    // comun a todos los envios, haga que la busqueda case con toda la tabla).
    if (ctype_digit($fBuscar)) {
        $clauses[] = "orders_id = " . (int) $fBuscar;
        $clauses[] = "id_rma = " . (int) $fBuscar;
    }
    $where[] = '(' . implode(' OR ', $clauses) . ')';
}
$sql = 'SELECT * FROM correos_shipments WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 200';
$rows = array();
$q = tep_db_query($sql);
while ($r = tep_db_fetch_array($q)) $rows[] = $r;

$editRow = null;
if (isset($_GET['edit']) && (int) $_GET['edit'] > 0) {
    $qe = tep_db_query('SELECT * FROM correos_shipments WHERE id = ' . (int) $_GET['edit']);
    $editRow = tep_db_fetch_array($qe);
}
?>
<?php require THEME . 'html/header.php'; ?>

<style>
.cor-adm { font-family: system-ui, sans-serif; max-width: 1300px; margin: 0 auto; padding: 1em; }
.cor-adm h1 { margin-top: 0; }
.cor-adm .env { font-size: 13px; padding: 3px 10px; border-radius: 4px; background:#e8f6ee; color:#2e7d32; }
.cor-adm .alert { padding: 10px 14px; border-radius: 4px; margin: 10px 0; }
.cor-adm .alert.success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
.cor-adm .alert.warning { background:#fff3cd; color:#856404; border:1px solid #ffeaa7; }
.cor-adm .alert.danger  { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
.cor-adm form.filtros { margin: 12px 0; display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.cor-adm input, .cor-adm select { padding:5px 8px; border:1px solid #aaa; border-radius:4px; }
.cor-adm table { border-collapse: collapse; width: 100%; margin-top: 1em; }
.cor-adm th, .cor-adm td { border:1px solid #ddd; padding:5px 8px; text-align:left; font-size:12px; }
.cor-adm th { background:#f0f0f0; }
.cor-adm tr.anulado { opacity:.5; } .cor-adm tr.err { background:#fff0f0; } .cor-adm tr.cola { background:#fff8e6; }
.cor-adm .btn { display:inline-block; padding:4px 10px; background:#3273dc; color:#fff; border:0; border-radius:4px; cursor:pointer; font-size:12px; text-decoration:none; }
.cor-adm .btn.rojo { background:#c0392b; } .cor-adm .btn.gris { background:#7f8c8d; } .cor-adm .btn.verde { background:#16a085; }
.cor-adm code { font-size:11px; }
</style>

<div class="cor-adm">
  <h1>Envíos Correos <span class="env">PRODUCCIÓN</span></h1>
  <p class="muted">Gestión de los envíos y devoluciones de Correos. Anular y Modificar usan la API de Correos; reimprimir reenvía la etiqueta a la impresora del almacén.</p>

  <?php if ($msg): ?><div class="alert <?php echo $msgClass; ?>"><?php echo $msg; ?></div><?php endif; ?>

  <form class="filtros" method="get">
    <label>Tipo:
      <select name="tipo">
        <?php foreach (array('todos'=>'Todos','envio'=>'Salida','devolucion'=>'Devolución') as $k=>$v): ?>
          <option value="<?php echo $k; ?>" <?php echo $fTipo===$k?'selected':''; ?>><?php echo $v; ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Estado:
      <select name="estado">
        <?php foreach (array('todos'=>'Todos','ok'=>'OK','error'=>'Con error','anulado'=>'Anulados','encolado'=>'Anulación pendiente') as $k=>$v): ?>
          <option value="<?php echo $k; ?>" <?php echo $fEstado===$k?'selected':''; ?>><?php echo $v; ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <input type="text" name="q" value="<?php echo htmlspecialchars($fBuscar); ?>" placeholder="Buscar: pedido, RMA, ref, cód. envío o localización">
    <button class="btn" type="submit">Filtrar</button>
    <a class="btn gris" href="?">Limpiar</a>
  </form>

  <details style="margin:12px 0;border:1px solid #cde;border-radius:6px;padding:6px 14px;background:#f7fbff;">
    <style>.cor-manual label{display:flex;flex-direction:column;font-size:12px;gap:3px;color:#333}.cor-manual input{width:100%}</style>
    <summary style="cursor:pointer;font-weight:600;color:#2e7d32;">&#10010; Nuevo env&iacute;o manual Correos (sin pedido &mdash; RMA a proveedor, muestras, etc.)</summary>
    <form id="corManForm" class="cor-manual" method="post" style="margin-top:10px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 14px;max-width:840px;">
      <input type="hidden" name="do" value="crear_manual">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
      <label style="grid-column:1/3;flex-direction:row;align-items:center;gap:10px;flex-wrap:wrap">Entrega:
        <label style="flex-direction:row;align-items:center;gap:4px;font-weight:normal"><input type="radio" name="m_mode" value="dom" checked> Domicilio</label>
        <label style="flex-direction:row;align-items:center;gap:4px;font-weight:normal"><input type="radio" name="m_mode" value="ofi"> Oficina de Correos</label>
      </label>
      <label>Destinatario *<input type="text" name="m_dname" required></label>
      <label>Email <span style="color:#999;font-weight:normal">(oblig. para oficina)</span><input type="email" name="m_demail"></label>
      <label>Tel&eacute;fono<input type="text" name="m_dphone"></label>
      <label>Peso (kg)<input type="text" name="m_kilos" value="1"></label>
      <label>Bultos<input type="number" name="m_bultos" value="1" min="1" max="10"></label>
      <label style="grid-column:1/3">Pa&iacute;s destino
        <select name="m_dcountry" id="mCountry">
          <?php /* CASE: `countries` guarda 'ROM' (alfa-3 de Rumania anterior a 2002); Correos exige 'ROU'. */ ?>
          <?php $qPais = tep_db_query("SELECT countries_name, CASE countries_iso_code_3 WHEN 'ROM' THEN 'ROU' ELSE countries_iso_code_3 END AS iso3 FROM countries WHERE countries_iso_code_3 <> '' ORDER BY countries_name"); ?>
          <?php while ($rPais = tep_db_fetch_array($qPais)): ?>
            <option value="<?php echo htmlspecialchars($rPais['iso3']); ?>" <?php echo $rPais['iso3'] === 'ESP' ? 'selected' : ''; ?>><?php echo htmlspecialchars($rPais['countries_name']); ?></option>
          <?php endwhile; ?>
        </select>
      </label>
      <div id="mDomBox" style="grid-column:1/3;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 14px">
        <label>Direcci&oacute;n *<input type="text" name="m_dstreet"></label>
        <label>CP *<input type="text" name="m_dcp"></label>
        <label>Ciudad *<input type="text" name="m_dcity"></label>
        <label>Provincia / Estado<input type="text" name="m_dstate"></label>
      </div>
      <div id="mOfiBox" style="grid-column:1/3;display:none;padding:10px;background:#fff;border:1px solid #ddd;border-radius:4px">
        <div style="font-size:12px;color:#333;margin-bottom:6px">Oficina de Correos donde recoger&aacute; el destinatario (busca por CP de la zona):</div>
        <input type="text" id="mCpOfi" maxlength="5" placeholder="CP" style="width:110px;padding:5px 8px;border:1px solid #aaa;border-radius:4px">
        <button type="button" id="mBtnBuscarOfi" class="btn" data-searching="Buscando&hellip;">Buscar oficinas</button>
        <span id="mMsgOfi" style="color:#a00;font-size:12px"></span><br>
        <div id="mSelOfiSel" style="margin-top:8px;margin-bottom:4px;font-size:12px;color:#2e7d32;font-weight:600"></div>
        <div id="mSelOfi" style="margin-top:0;max-height:200px;overflow:auto;border:1px solid #ddd;border-radius:4px;display:none;background:#fff"></div>
        <input type="hidden" name="m_office" id="mHOfi"><input type="hidden" name="m_office_name" id="mHOfiName">
        <input type="hidden" name="m_office_addr" id="mHOfiAddr"><input type="hidden" name="m_office_cp" id="mHOfiCp"><input type="hidden" name="m_office_city" id="mHOfiCity">
      </div>
      <label style="grid-column:1/3;">Referencia / concepto <span style="color:#999;font-weight:normal">(aparece en la columna Pedido/RMA del listado y es buscable; d&eacute;jalo vac&iacute;o para auto)</span><input type="text" name="m_ref" placeholder="p.ej. RMA proveedor 123, n&ordm; factura, pedido relacionado&hellip;"></label>
      <label>Valor declarado &euro; <span style="color:#999">(islas / fuera UE)</span><input type="text" name="m_dvalue" placeholder="p.ej. 1234.56"></label>
      <label>Contenido <span style="color:#999">(islas / fuera UE)</span><input type="text" name="m_ddesc" placeholder="p.ej. recambios nauticos"></label>
      <label>DNI/NIF destinatario <span style="color:#999">(obligatorio en islas)</span><input type="text" name="m_ddoi" maxlength="12" placeholder="12345678Z / X1234567L / B12345678"></label>
      <div id="mAvisoAduanas" style="grid-column:1/3;display:none;margin-top:2px;padding:6px 9px;background:#fff8e1;border:1px solid #ffe082;border-radius:4px;color:#7a5900;font-size:11px;">Destino <strong>con aduanas</strong> (Canarias/Ceuta/Melilla o fuera de la UE): el <strong>valor declarado y el contenido</strong> son obligatorios y el env&iacute;o debe ir en <strong>un solo bulto</strong> (la declaraci&oacute;n DUA/CN23 va completa en un paquete). En Canarias/Ceuta/Melilla Correos exige adem&aacute;s el <strong>DNI/NIF del destinatario</strong>.</div>
      <div style="grid-column:1/3;margin-top:2px;color:#777;font-size:11px;">Espa&ntilde;a: Paq Est&aacute;ndar, domicilio o recogida en oficina. Otros pa&iacute;ses: <strong>Paq Est&aacute;ndar Internacional</strong>, solo domicilio. Rellena <strong>valor y contenido</strong> para Canarias/Ceuta/Melilla y para destinos <strong>fuera de la UE</strong> (declaraci&oacute;n DUA/CN23 obligatoria).</div>
      <div style="grid-column:1/3;margin-top:4px;"><button class="btn verde" type="submit">Crear env&iacute;o y mandar etiqueta a la impresora</button></div>
    </form>
    <script>
    (function () {
      var f = document.getElementById('corManForm'); if (!f) return;
      var domBox = document.getElementById('mDomBox'), ofiBox = document.getElementById('mOfiBox');
      function mode() { var r = f.querySelector('input[name="m_mode"]:checked'); return r ? r.value : 'dom'; }
      function sync() {
        var ofi = mode() === 'ofi';
        domBox.style.display = ofi ? 'none' : 'grid'; ofiBox.style.display = ofi ? 'block' : 'none';
        ['m_dstreet', 'm_dcp', 'm_dcity'].forEach(function (n) { var el = f.querySelector('[name="' + n + '"]'); if (el) el.required = !ofi; });
        var em = f.querySelector('[name="m_demail"]'); if (em) em.required = ofi;
      }
      f.querySelectorAll('input[name="m_mode"]').forEach(function (r) { r.addEventListener('change', sync); });
      sync();
      // Pais destino: internacional => solo domicilio; fuera de la UE-27 => avisa de aduanas.
      var UE27 = ['AUT','BEL','BGR','HRV','CYP','CZE','DNK','EST','FIN','FRA','DEU','GRC','HUN','IRL','ITA',
                  'LVA','LTU','LUX','MLT','NLD','POL','PRT','ROU','SVK','SVN','ESP','SWE'];
      var cty = document.getElementById('mCountry');
      var rOfi = f.querySelector('input[name="m_mode"][value="ofi"]');
      var rDom = f.querySelector('input[name="m_mode"][value="dom"]');
      var aviso = document.getElementById('mAvisoAduanas');
      var ISLAS = ['35', '38', '51', '52'];
      var elCp = f.querySelector('[name="m_dcp"]'), elBul = f.querySelector('[name="m_bultos"]');
      var elVal = f.querySelector('[name="m_dvalue"]'), elDsc = f.querySelector('[name="m_ddesc"]');
      var elDoi = f.querySelector('[name="m_ddoi"]');
      // Mismo criterio que el endpoint: islas espanolas (por CP) o destino fuera de la UE-27.
      function conAduanas(iso) {
        if (iso !== 'ESP') return UE27.indexOf(iso) < 0;
        var el = (mode() === 'ofi') ? document.getElementById('mHOfiCp') : elCp;
        var cp = ((el && el.value) || '').replace(/\D/g, '');
        return ISLAS.indexOf(cp.substring(0, 2)) >= 0;
      }
      function syncPais() {
        var iso = cty ? cty.value : 'ESP', intl = (iso !== 'ESP');
        if (rOfi) {
          if (intl && rOfi.checked && rDom) rDom.checked = true;
          rOfi.disabled = intl;
          rOfi.parentNode.style.opacity = intl ? '.45' : '';
          rOfi.parentNode.title = intl ? 'La recogida en oficina de Correos solo existe en Espana' : '';
          sync();
        }
        var adu = conAduanas(iso);
        if (aviso) aviso.style.display = adu ? '' : 'none';
        // El endpoint rechaza multibulto con aduanas: la DUA/CN23 va completa en un bulto.
        if (elBul) { elBul.max = adu ? 1 : 10; if (adu) elBul.value = 1; }
        if (elVal) elVal.required = adu;
        if (elDsc) elDsc.required = adu;
        // El DNI del destinatario solo es obligatorio en islas espanolas (el endpoint no lo exige fuera de la UE).
        if (elDoi) elDoi.required = (adu && iso === 'ESP');
      }
      if (cty) cty.addEventListener('change', syncPais);
      if (elCp) elCp.addEventListener('input', syncPais);
      f.querySelectorAll('input[name="m_mode"]').forEach(function (r) { r.addEventListener('change', syncPais); });
      syncPais();
      var btn = document.getElementById('mBtnBuscarOfi');
      btn.addEventListener('click', function () {
        var cp = (document.getElementById('mCpOfi').value || '').replace(/\D/g, '');
        var mm = document.getElementById('mMsgOfi'); mm.textContent = '';
        if (cp.length !== 5) { mm.textContent = 'CP de 5 digitos'; return; }
        ['mHOfi', 'mHOfiName', 'mHOfiAddr', 'mHOfiCp', 'mHOfiCity'].forEach(function (id) { document.getElementById(id).value = ''; });
        var t0 = btn.textContent; btn.textContent = btn.getAttribute('data-searching'); btn.disabled = true;
        fetch('/correos_oficinas.php?cp=' + cp).then(function (r) { return r.json(); }).then(function (d) {
          btn.textContent = t0; btn.disabled = false;
          var sel = document.getElementById('mSelOfi'); sel.innerHTML = ''; sel.style.display = 'none';
          document.getElementById('mSelOfiSel').textContent = '';
          if (!d.ok || !(d.oficinas || []).length) { mm.textContent = 'Sin oficinas en ese CP'; return; }
          sel.style.display = 'block';
          d.oficinas.forEach(function (o) {
            var it = document.createElement('div');
            it.textContent = o.name + ' — ' + o.address + ' (' + o.cp + ' ' + o.city + ')';
            it.style.cssText = 'padding:6px 10px;cursor:pointer;border-bottom:1px solid #eee;font-size:12px';
            it.dataset.id = o.id; it.dataset.name = o.name; it.dataset.addr = o.address; it.dataset.cp = o.cp; it.dataset.city = o.city;
            it.addEventListener('mouseover', function () { if (it.dataset.sel !== '1') it.style.background = '#f3f7ff'; });
            it.addEventListener('mouseout', function () { if (it.dataset.sel !== '1') it.style.background = ''; });
            it.addEventListener('click', function () { pick(it); });
            sel.appendChild(it);
          });
        }).catch(function () { btn.textContent = t0; btn.disabled = false; mm.textContent = 'Error, reintenta'; });
      });
      function pick(it) {
        var sel = document.getElementById('mSelOfi');
        Array.prototype.forEach.call(sel.children, function (c) { c.style.background = ''; c.dataset.sel = ''; });
        it.style.background = '#e8f6ee'; it.dataset.sel = '1';
        document.getElementById('mHOfi').value = it.dataset.id || '';
        document.getElementById('mHOfiName').value = it.dataset.name || '';
        document.getElementById('mHOfiAddr').value = it.dataset.addr || '';
        document.getElementById('mHOfiCp').value = it.dataset.cp || '';
        document.getElementById('mHOfiCity').value = it.dataset.city || '';
        document.getElementById('mSelOfiSel').textContent = '✓ Oficina elegida: ' + (it.dataset.name || '') + ' — ' + (it.dataset.addr || '') + ' (' + (it.dataset.cp || '') + ')';
      }
      f.addEventListener('submit', function (e) {
        if (mode() === 'ofi') {
          if (!document.getElementById('mHOfi').value) { e.preventDefault(); alert('Elige una oficina de Correos de la lista (busca por CP).'); return; }
          if (!f.querySelector('[name="m_demail"]').value.trim()) { e.preventDefault(); alert('Para entrega en oficina, el email del destinatario es obligatorio.'); return; }
        }
        var b = f.querySelector('button[type=submit]'); if (b) { b.disabled = true; b.textContent = 'Creando…'; }
      });
    })();
    </script>
  </details>

  <?php
  $editEligible = $editRow && $editRow['tipo'] === 'envio' && empty($editRow['cancelled_at']) && $editRow['ok'] && correosEsWeb($editRow['orders_id']) && correosEnvModEligible(correosEnvTrackRow($editRow['ref'] ?? ''));
  if ($editRow && $editRow['tipo'] === 'envio' && empty($editRow['cancelled_at']) && !$editEligible): ?>
  <div class="alert warning" style="max-width:780px">El envío <code><?php echo htmlspecialchars($editRow['shipment_code']); ?></code> no es modificable aquí (en tránsito, sin pedido web, o pedido QFac-nativo). <a href="<?php echo tep_href_link('correos_envios.php'); ?>">Volver</a></div>
  <?php endif; ?>
  <?php if ($editEligible): ?>
  <div style="border:1px solid #b9d7f5;background:#f4f9ff;border-radius:6px;padding:14px;margin:12px 0;max-width:780px">
    <h3 style="margin:0 0 4px">Regenerar envío <code><?php echo htmlspecialchars($editRow['shipment_code']); ?></code></h3>
    <p class="muted" style="font-size:12px;margin:0 0 12px">Pedido <?php echo (int) $editRow['orders_id']; ?> · <strong>se anulará el actual y se creará uno nuevo</strong> (la etiqueta saldrá por la impresora).</p>
    <form method="post" id="corModForm">
      <input type="hidden" name="do" value="modify"><input type="hidden" name="ship" value="<?php echo (int) $editRow['id']; ?>">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
      <div style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:10px">
        <label>Peso (kg)<br><input type="number" step="0.1" min="0.1" max="40" name="kilos" value="<?php echo htmlspecialchars($editRow['kilos'] ?: '1'); ?>" style="width:90px"></label>
        <label>Nº bultos<br><input type="number" min="1" max="10" name="bultos" value="1" style="width:90px"></label>
      </div>
      <p style="margin:6px 0 4px"><strong>Destino:</strong></p>
      <label style="display:block"><input type="radio" name="destino" value="mantener" checked> Mantener el destino actual</label>
      <label style="display:block"><input type="radio" name="destino" value="oficina"> Recoger en oficina de Correos</label>
      <label style="display:block"><input type="radio" name="destino" value="domicilio"> Entregar a domicilio del pedido</label>

      <div id="boxOfi" style="display:none;margin:8px 0 0;padding:10px;background:#fff;border:1px solid #ddd;border-radius:4px">
        <input type="text" id="cpOfi" maxlength="5" placeholder="CP" style="width:90px">
        <button type="button" id="btnBuscarOfi" class="btn" data-searching="Buscando&hellip;">Buscar oficinas</button>
        <span id="msgOfi" style="color:#a00;font-size:12px"></span><br>
        <div id="selOfiSel" style="margin-top:8px;margin-bottom:4px;font-size:12px;color:#2e7d32;font-weight:600"></div>
        <div id="selOfi" style="margin-top:0;max-height:200px;overflow:auto;border:1px solid #ddd;border-radius:4px;display:none;background:#fff"></div>
        <input type="hidden" name="oficina" id="hOfi"><input type="hidden" name="ofi_name" id="hOfiName">
        <input type="hidden" name="ofi_addr" id="hOfiAddr"><input type="hidden" name="ofi_cp" id="hOfiCp"><input type="hidden" name="ofi_city" id="hOfiCity">
      </div>

      <div style="margin-top:12px">
        <button class="btn verde" type="submit" id="btnRegen">♻ Anular y regenerar</button>
        <a class="btn gris" href="<?php echo tep_href_link('correos_envios.php'); ?>">Cancelar</a>
      </div>
    </form>
  </div>
  <script>
  (function () {
    var form = document.getElementById('corModForm');
    var bo = document.getElementById('boxOfi');
    function sync() {
      var v = (document.querySelector('input[name="destino"]:checked') || {}).value;
      if (bo) bo.style.display = (v === 'oficina') ? 'block' : 'none';
    }
    document.querySelectorAll('input[name="destino"]').forEach(function (r) { r.addEventListener('change', sync); });
    sync();
    form.addEventListener('submit', function (e) {
      var v = (document.querySelector('input[name="destino"]:checked') || {}).value;
      if (v === 'oficina' && !document.getElementById('hOfi').value) { e.preventDefault(); alert('Elige una oficina de Correos de la lista.'); return; }
      var b = document.getElementById('btnRegen'); if (b) { b.disabled = true; b.textContent = 'Procesando…'; }
    });
    var btn = document.getElementById('btnBuscarOfi');
    if (btn) btn.addEventListener('click', function () {
      var cp = (document.getElementById('cpOfi').value || '').replace(/\D/g, '');
      var m = document.getElementById('msgOfi'); m.textContent = '';
      if (cp.length !== 5) { m.textContent = 'CP de 5 dígitos'; return; }
      var t0 = btn.textContent; btn.textContent = btn.getAttribute('data-searching'); btn.disabled = true;
      fetch('/correos_oficinas.php?cp=' + cp).then(function (r) { return r.json(); }).then(function (d) {
        btn.textContent = t0; btn.disabled = false;
        var sel = document.getElementById('selOfi'); sel.innerHTML = ''; sel.style.display = 'none';
        document.getElementById('selOfiSel').textContent = '';
        if (!d.ok || !(d.oficinas || []).length) { m.textContent = 'Sin oficinas en ese CP'; return; }
        sel.style.display = 'block';
        d.oficinas.forEach(function (o) {
          var it = document.createElement('div');
          it.textContent = o.name + ' — ' + o.address + ' (' + o.cp + ' ' + o.city + ')';
          it.style.cssText = 'padding:6px 10px;cursor:pointer;border-bottom:1px solid #eee;font-size:12px';
          it.dataset.id = o.id; it.dataset.name = o.name; it.dataset.addr = o.address; it.dataset.cp = o.cp; it.dataset.city = o.city;
          it.addEventListener('mouseover', function () { if (it.dataset.sel !== '1') it.style.background = '#f3f7ff'; });
          it.addEventListener('mouseout', function () { if (it.dataset.sel !== '1') it.style.background = ''; });
          it.addEventListener('click', function () { pickOfi(it); });
          sel.appendChild(it);
        });
      }).catch(function () { btn.textContent = t0; btn.disabled = false; m.textContent = 'Error, reintenta'; });
    });
    function pickOfi(it) {
      var sel = document.getElementById('selOfi');
      Array.prototype.forEach.call(sel.children, function (c) { c.style.background = ''; c.dataset.sel = ''; });
      it.style.background = '#e8f6ee'; it.dataset.sel = '1';
      document.getElementById('hOfi').value = it.dataset.id || '';
      document.getElementById('hOfiName').value = it.dataset.name || '';
      document.getElementById('hOfiAddr').value = it.dataset.addr || '';
      document.getElementById('hOfiCp').value = it.dataset.cp || '';
      document.getElementById('hOfiCity').value = it.dataset.city || '';
      document.getElementById('selOfiSel').textContent = '✓ Oficina elegida: ' + (it.dataset.name || '') + ' — ' + (it.dataset.addr || '') + ' (' + (it.dataset.cp || '') + ')';
    }
  })();
  </script>
  <?php endif; ?>

  <table>
    <tr>
      <th>ID</th><th>Tipo</th><th>Pedido/RMA</th><th>shipmentCode</th><th>Prod</th>
      <th>Seguimiento</th><th>Estado</th><th>Fecha</th><th>Acciones</th>
    </tr>
    <?php if (!$rows): ?>
      <tr><td colspan="9">Sin envíos que coincidan con el filtro.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $s): ?>
      <?php
        $encolado = !$s['cancelled_at'] && !empty($s['cancel_requested_at']);
        $hasPkg   = !empty($s['shipment_code']) || !empty($s['package_code']);   // hay algo que anular en Correos
        $cls = $s['cancelled_at'] ? 'anulado' : ($encolado ? 'cola' : (!$s['ok'] ? 'err' : ''));
        $ref = $s['orders_id'] ? ('Pedido ' . (int) $s['orders_id']) : ($s['id_rma'] ? 'RMA ' . (int) $s['id_rma'] : (trim((string) $s['ref']) !== '' ? 'Manual <code>' . htmlspecialchars($s['ref']) . '</code>' : '—'));
        $trk = correosEnvTrackRow($s['ref']);
        if ($s['cancelled_at'])   $estado = '<span style="color:#c0392b">Anulado</span>';
        elseif ($encolado)        $estado = '<span style="color:#b9770e">Anulación pendiente</span>';
        elseif ($s['ok'])         $estado = '<span style="color:#2e7d32">OK</span>';
        else                      $estado = '<span style="color:#c0392b">Error (preregistro sin etiqueta)</span>';
      ?>
      <tr class="<?php echo $cls; ?>">
        <td><?php echo (int) $s['id']; ?></td>
        <td><?php echo $s['tipo'] === 'devolucion' ? '↩ Devolución' : '📦 Salida'; ?></td>
        <td><?php echo $ref; ?></td>
        <td><code><?php echo htmlspecialchars($s['shipment_code'] ?: '—'); ?></code></td>
        <td><?php echo htmlspecialchars($s['producto'] ?: '—'); ?></td>
        <td><?php echo $trk ? htmlspecialchars(substr($trk['estado_desc'] ?? '', 0, 40)) . ((int) $trk['entregado'] === 1 ? ' ✅' : '') : '<span style="color:#bbb">—</span>'; ?></td>
        <td><?php echo $estado; ?><?php if ((!$s['ok'] || $encolado) && $s['mensaje_retorno']): ?><br><span style="color:#999"><?php echo htmlspecialchars(substr($s['mensaje_retorno'],0,70)); ?></span><?php endif; ?></td>
        <td><?php echo htmlspecialchars($s['date_added']); ?></td>
        <td>
          <?php if ($s['cancelled_at']): ?>
            <span style="color:#999">anulado <?php echo htmlspecialchars($s['cancelled_at']); ?></span>
          <?php elseif ($encolado): ?>
            <span style="color:#b9770e">anulación encolada (<?php echo htmlspecialchars($s['cancel_requested_at']); ?>) · el cron reintenta</span>
          <?php else: ?>
            <?php if ($s['ok'] && $s['tipo'] === 'envio'): ?>
            <form method="post" style="display:inline" onsubmit="return confirm('¿Reimprimir la etiqueta de este envío?');">
              <input type="hidden" name="do" value="reprint"><input type="hidden" name="ship" value="<?php echo (int) $s['id']; ?>"><input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
              <button class="btn" type="submit">⎙ Reimprimir</button>
            </form>
            <?php endif; ?>
            <?php if ($hasPkg): ?>
            <form method="post" style="display:inline" onsubmit="return confirm('¿ANULAR en Correos este envío?');">
              <input type="hidden" name="do" value="cancel"><input type="hidden" name="ship" value="<?php echo (int) $s['id']; ?>"><input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
              <button class="btn rojo" type="submit">✕ Anular</button>
            </form>
            <?php endif; ?>
            <?php if ($s['ok'] && $s['tipo'] === 'envio' && correosEsWeb($s['orders_id'])): ?>
              <a class="btn verde" href="<?php echo tep_href_link('correos_envios.php', 'edit=' . (int) $s['id']); ?>">✎ Modificar</a>
            <?php endif; ?>
            <?php if (!$hasPkg && !$s['ok']): ?><span style="color:#999">—</span><?php endif; ?>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <p class="muted" style="font-size:12px;margin-top:8px">Mostrando los últimos <?php echo count($rows); ?> (máx 200). Modificar anula el actual y regenera uno nuevo (solo pedidos web).</p>
</div>

<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
