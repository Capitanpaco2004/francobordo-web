<?php
/**
 * Panel de envíos SEUR (salida + devoluciones) — _admin/seur_envios.php
 *
 * Por cada envío:
 *   - Anular     : /shipments/cancel (salida) o /collections/cancel (devolución), VERIFICADO
 *   - Reimprimir : reencola el ZPL para la Zebra (cola que vacía el watcher .112)
 *   - Modificar  : "Anular y regenerar" (peso/bultos/punto/dirección). Anula VERIFICADO el
 *                  actual y crea uno NUEVO con ref nueva (regen=1; SEUR deduplica ref+fecha,
 *                  por eso no se puede reusar la misma el mismo día) vía seur_albaran.php.
 *
 * Seguridad: token CSRF de sesión en todas las acciones + patrón PRG (redirect tras POST)
 * para que F5/doble-click no reejecuten. Entorno SEUR según seur_config.
 * Ver memoria francobordo_seur_api.
 */

require 'includes/application_top.php';
require_once DIR_FS_CATALOG . 'includes/classes/seur.php';

set_time_limit(120);
define('SEUR_ALB_TOKEN', 'seuralb_9d2f51c7a3e8');

function seurEnvAdminEnv() {
    $q = tep_db_query("SELECT config_value FROM seur_config WHERE config_key = 'env'");
    if ($q && tep_db_num_rows($q)) { $v = tep_db_fetch_array($q); return ($v['config_value'] === 'pro') ? 'pro' : 'pre'; }
    return 'pre';
}
$env = seurEnvAdminEnv();

$msg = ''; $msgClass = '';

/* CSRF por sesión (sin él, un POST cross-site con sesión de admin abierta podría
 * anular/regenerar envíos reales). */
if (empty($_SESSION['seur_csrf'])) $_SESSION['seur_csrf'] = tep_create_random_value(32);
$csrf = $_SESSION['seur_csrf'];

/* Flash PRG: mensaje guardado por la acción previa antes de redirigir. */
if (!empty($_SESSION['seur_flash'])) {
    $msg = $_SESSION['seur_flash']['m']; $msgClass = $_SESSION['seur_flash']['c'];
    unset($_SESSION['seur_flash']);
}

/* Clasifica un error de anulación de SEUR como "suave" (ya anulado / no existe →
 * podemos seguir) vs "duro" (sigue vivo → NO marcar anulado, NO recrear). */
function seurCancelSoft($desc) {
    $d = mb_strtolower((string) $desc);
    return (strpos($d, 'no existe') !== false || strpos($d, 'ya anulad') !== false
         || strpos($d, 'inexist') !== false || strpos($d, 'not found') !== false);
}

/* Estado de seguimiento de un envío por su ref (seur_tracking, cron horario). */
function seurTrackRow($ref) {
    $ref = trim((string) $ref);
    if ($ref === '') return null;
    $q = tep_db_query("SELECT estado_desc, entregado FROM seur_tracking WHERE referencia = '" . tep_db_input($ref) . "' LIMIT 1");
    return tep_db_num_rows($q) ? tep_db_fetch_array($q) : null;
}

/* ¿El envío AÚN es modificable? Solo PRE-TRÁNSITO (no movido): así la anulación es
 * fiable y nunca se regenera un bulto que ya circula (cierra el doble-envío de raíz).
 * Conservador: sin estado = recién creado = OK; con estado, solo estados de origen. */
function seurModEligible($trk) {
    if (!$trk) return true;
    if ((int) $trk['entregado'] === 1) return false;
    return (bool) preg_match('/registrad|recibido en origen|pendiente|grabad/i', (string) $trk['estado_desc']);
}

/* ---- Acciones (POST con CSRF) ---- */
$action = $_POST['do'] ?? '';
$shipId = (int) ($_POST['ship'] ?? 0);

/** Identidad del admin conectado — para auditoría en seur_shipments.operator /
 *  cancelled_by (mismo criterio que cexOperator() del módulo RMA). */
function seurOperator() {
    foreach (array('login_email_address', 'login_id', 'login_first_name') as $k) {
        if (!empty($_SESSION[$k])) return substr((string) $_SESSION[$k], 0, 64);
    }
    return 'admin-desconocido';
}

/* === Envio manual SEUR (sin pedido): crea un envio libre y encola su etiqueta para la Zebra === */
if (($_POST['do'] ?? '') === 'crear_manual') {
    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $_SESSION['seur_flash'] = array('m' => 'Token CSRF invalido.', 'c' => 'danger');
    } else {
        $g = function ($k) { return trim((string) ($_POST[$k] ?? '')); };
        $dname = $g('m_dname'); $dstreet = $g('m_dstreet'); $dcp = $g('m_dcp'); $dcity = $g('m_dcity');
        $dstate = $g('m_dstate'); $dcountry = $g('m_dcountry'); $dphone = $g('m_dphone'); $demail = $g('m_demail');
        $mkilos = str_replace(',', '.', $g('m_kilos')); if ((float) $mkilos <= 0) $mkilos = '1';
        $mbultos = max(1, (int) ($_POST['m_bultos'] ?? 1));
        $mref = $g('m_ref'); if ($mref === '') $mref = 'M-' . date('ymd-His');
        $msvc = $g('m_service');
        if ($dname === '' || $dstreet === '' || $dcp === '' || $dcity === '' || $dcountry === '') {
            $_SESSION['seur_flash'] = array('m' => 'Faltan campos obligatorios: destinatario, direccion, CP, ciudad y pais.', 'c' => 'danger');
        } else {
            $params = array('token' => SEUR_ALB_TOKEN, 'free' => '1', 'ref' => $mref,
                'dname' => $dname, 'dstreet' => $dstreet, 'dcp' => $dcp, 'dcity' => $dcity, 'dstate' => $dstate,
                'dcountry' => $dcountry, 'dphone' => $dphone, 'demail' => $demail,
                'kilos' => $mkilos, 'bultos' => $mbultos, 'type' => 'ZPL', 'op' => seurOperator());
            if ($msvc !== '' && strpos($msvc, '/') !== false) { list($sc, $pc) = explode('/', $msvc, 2); $params['svccode'] = preg_replace('/\D/', '', $sc); $params['prodcode'] = preg_replace('/\D/', '', $pc); }
            $ch = curl_init('https://www.francobordo.com/seur_albaran.php');
            curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 90, CURLOPT_POST => 1, CURLOPT_POSTFIELDS => http_build_query($params)));
            $raw = curl_exec($ch); $cerr = curl_error($ch);
            $resp = json_decode((string) $raw, true);
            if (is_array($resp) && !empty($resp['ok']) && !empty($resp['shipmentCode'])) {
                if (!empty($resp['zpl']))
                    tep_db_perform('seur_reprint_queue', array('shipment_id' => (int) ($resp['shipment_id'] ?? 0), 'orders_id' => 0, 'zpl' => $resp['zpl'], 'done' => 0, 'date_added' => 'now()'));
                $_SESSION['seur_flash'] = array('m' => 'Envio manual creado: codigo <strong>' . htmlspecialchars($resp['shipmentCode']) . '</strong> (ref ' . htmlspecialchars($resp['ref'] ?? $mref) . '). La etiqueta saldra por la Zebra en ~1 min.', 'c' => 'success');
            } else {
                $why = is_array($resp) ? ($resp['error'] ?? 'respuesta no concluyente') : ('sin respuesta' . ($cerr ? ': ' . $cerr : ''));
                $_SESSION['seur_flash'] = array('m' => 'No se pudo crear el envio manual: ' . htmlspecialchars($why), 'c' => 'danger');
            }
        }
    }
    tep_redirect(tep_href_link('seur_envios.php'));
}

if ($action !== '' && $shipId > 0) {
    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $msg = 'Sesión caducada o petición no válida. Recarga la página e inténtalo de nuevo.'; $msgClass = 'danger';
    } else {
        $q = tep_db_query('SELECT * FROM seur_shipments WHERE id = ' . $shipId);
        $s = tep_db_fetch_array($q);
        if (!$s) {
            $msg = 'Envío no encontrado.'; $msgClass = 'danger';
        } else {
            $seur = new seur($s['entorno'] ?: $env);
            $seur->setTimeout(60);

            if ($action === 'cancel') {
                if (!empty($s['cancelled_at'])) {
                    $msg = 'Ese envío ya estaba anulado.'; $msgClass = 'warning';
                } else {
                    $res = ($s['tipo'] === 'recogida') ? $seur->cancelCollection($s['shipment_code']) : $seur->cancelShipment($s['shipment_code']);
                    $d = seur::payload($res);
                    $desc = (is_array($d) && !empty($d[0]['description'])) ? $d[0]['description'] : seur::primerError($res);
                    if ($res['ok'] || seurCancelSoft($desc)) {
                        tep_db_perform('seur_shipments', array('cancelled_at' => 'now()', 'cancelled_by' => seurOperator()), 'update', 'id = ' . $shipId);
                        $msg = 'Envío ' . htmlspecialchars($s['shipment_code']) . ' anulado en SEUR. ' . htmlspecialchars($desc);
                        $msgClass = 'success';
                    } else {
                        // Error DURO: el envío sigue VIVO → no marcar anulado (que siga visible y reanulable).
                        $msg = 'NO se ha podido anular en SEUR (el envío sigue activo): ' . htmlspecialchars($desc) . '. Reinténtalo o gestiónalo con SEUR.';
                        $msgClass = 'danger';
                    }
                }
            } elseif ($action === 'reprint') {
                $entity = ($s['tipo'] === 'recogida') ? 'COLLECTIONS' : 'SHIPMENTS';
                $lopts = array('entity' => $entity);
                if ($s['tipo'] !== 'recogida') $lopts['templateType'] = 'GEOLABEL';
                if (!empty($s['pudo_id'])) $lopts['qr'] = true;
                $lab = $seur->getLabel($s['shipment_code'], ($s['tipo'] === 'recogida' ? 'PDF' : 'ZPL'), $lopts);
                $zpl = $lab['label'] ?? '';
                // Centra la GEOLABEL en el rollo 10x15 (mismo ajuste que seur_albaran.php seurAjustarZpl: baja Y 32 dots = ~4mm a la derecha + renombra el formato para saltar el cache de la Zebra)
                if ($s['tipo'] !== 'recogida' && $zpl !== '') {
                    $gdy = 32;
                    $zpl = preg_replace_callback('/\^(F[OT])(\d+),(\d+)/', function ($m) use ($gdy) { $ny = (int) $m[3] - $gdy; if ($ny < 0) $ny = 0; return '^' . $m[1] . $m[2] . ',' . $ny; }, $zpl);
                    if (preg_match('/\^DF([A-Z0-9_]{1,8})\.(\d+)/i', $zpl, $mm)) $zpl = str_replace($mm[1] . '.' . $mm[2], 'FBGS' . $gdy . '.' . $mm[2], $zpl);
                }
                if ($lab['ok'] && $zpl !== '' && $s['tipo'] !== 'recogida') {
                    tep_db_perform('seur_reprint_queue', array(
                        'shipment_id' => $shipId, 'orders_id' => (int) $s['orders_id'],
                        'zpl' => $zpl, 'done' => 0, 'date_added' => 'now()',
                    ));
                    $msg = 'Reimpresión encolada para el envío ' . htmlspecialchars($s['shipment_code']) . '. Saldrá por la Zebra en ~1 min.';
                    $msgClass = 'success';
                } elseif ($lab['ok']) {
                    $msg = 'Etiqueta regenerada (formato PDF para devolución; usa el botón Etiqueta para descargarla).';
                    $msgClass = 'success';
                } else {
                    $msg = 'No se pudo regenerar la etiqueta: ' . htmlspecialchars(seur::primerError($lab));
                    $msgClass = 'danger';
                }
            } elseif ($action === 'modify') {
                $err = '';
                if ($s['tipo'] !== 'envio')         $err = 'Solo se regeneran envíos de salida desde aquí (las devoluciones se gestionan en el RMA).';
                elseif (!empty($s['cancelled_at']))  $err = 'Ese envío ya está anulado.';
                if ($err === '') {
                    // anti doble-submit: releer estado actual
                    $qf = tep_db_query('SELECT cancelled_at FROM seur_shipments WHERE id = ' . $shipId);
                    $fr = tep_db_fetch_array($qf);
                    if (!empty($fr['cancelled_at'])) $err = 'Ese envío ya fue anulado (¿envío doble del formulario?).';
                }
                if ($err === '') {
                    // GATE pre-tránsito: solo se modifica un envío que NO se ha movido.
                    $trkE = seurTrackRow($s['ref']);
                    if (!seurModEligible($trkE))
                        $err = 'Este envío ya está en tránsito (' . htmlspecialchars($trkE['estado_desc'] ?? '') . '); no se puede modificar. Si procede, anúlalo y gestiónalo con SEUR.';
                }
                if ($err !== '') { $msg = $err; $msgClass = 'warning'; }
                else {
                    $oidM    = (int) $s['orders_id'];
                    $kilosM  = str_replace(',', '.', trim((string) ($_POST['kilos'] ?? '1')));
                    if ((float) $kilosM <= 0) $kilosM = '1';
                    $bultosM = max(1, (int) ($_POST['bultos'] ?? 1));
                    $modo    = $_POST['destino'] ?? 'mantener';
                    $esQfac  = ($oidM > 0 && $oidM < 10000000);
                    $params  = array('oid' => $oidM, 'kilos' => $kilosM, 'bultos' => $bultosM, 'type' => 'BOTH', 'regen' => '1', 'op' => seurOperator());

                    if ($modo === 'punto') {
                        $pp      = preg_replace('/[^0-9A-Za-z]/', '', (string) ($_POST['pudo'] ?? ''));
                        $pstreet = trim((string) ($_POST['dstreet'] ?? ''));
                        $pcp     = trim((string) ($_POST['dcp'] ?? ''));
                        if ($pp === '' || $pstreet === '' || $pcp === '') {
                            $err = 'Elige un punto SEUR de la lista (no se capturó su dirección).';
                        } else {
                            $params['pudo']  = $pp;
                            $params['pname'] = trim((string) ($_POST['pname'] ?? ''));
                            foreach (array('dstreet', 'dcp', 'dcity') as $k)
                                if (trim((string) ($_POST[$k] ?? '')) !== '') $params[$k] = trim((string) $_POST[$k]);
                        }
                    } elseif ($modo === 'domicilio') {
                        $params['nopunto'] = '1';
                        foreach (array('dname', 'dstreet', 'dcp', 'dcity', 'dstate', 'dcountry', 'dphone', 'demail') as $k)
                            if (trim((string) ($_POST[$k . '_d'] ?? '')) !== '') $params[$k] = trim((string) $_POST[$k . '_d']);
                        if ($esQfac) $params['manual'] = '1';
                        if (empty($params['dname']) || empty($params['dstreet']) || empty($params['dcp']))
                            $err = 'Para domicilio: nombre, dirección y CP son obligatorios.';
                    } else { // mantener destino actual
                        if ($esQfac) $err = 'Los pedidos QFac requieren elegir "Domicilio" e introducir la dirección.';
                        elseif (!empty($s['pudo_id'])) { $params['pudo'] = $s['pudo_id']; $params['pname'] = (string) $s['pudo_name']; }
                        // preservar el servicio original (sin esto, el regen caería al default 31/2)
                        elseif ((string) $s['service_code'] === '9')  $params['svc'] = '1330';
                        elseif ((string) $s['service_code'] === '3')  $params['svc'] = '1000';
                        elseif ((string) $s['service_code'] === '57') $params['svc'] = 'sabado';
                    }

                    // Selector "Servicio SEUR" (2026-07-07): si se elige uno, manda sobre la
                    // preservación automática. No aplica a punto SEUR (punto = siempre 2shop 1/48).
                    $svcNew = trim((string) ($_POST['svc_new'] ?? ''));
                    if ($err === '' && $svcNew !== '' && strpos($svcNew, '/') !== false
                        && $modo !== 'punto' && empty($params['pudo'])) {
                        list($scN, $pcN) = explode('/', $svcNew, 2);
                        $scN = preg_replace('/\D/', '', $scN);
                        $pcN = preg_replace('/\D/', '', $pcN);
                        if ($scN !== '' && $pcN !== '') {
                            $params['svccode']  = $scN;
                            $params['prodcode'] = $pcN;
                            unset($params['svc']);
                        }
                    }

                    if ($err !== '') { $msg = $err; $msgClass = 'danger'; }
                    else {
                        // LOCK por pedido (no bloqueante): serializa cancel+regen; evita que dos
                        // POST concurrentes (2 pestañas, reintento de red) generen dos envíos.
                        $lockName = 'seurmod_' . $oidM;
                        $lk = tep_db_query("SELECT GET_LOCK('" . tep_db_input($lockName) . "', 0) AS l");
                        $lkr = tep_db_fetch_array($lk);
                        if ((int) ($lkr['l'] ?? 0) !== 1) {
                            $msg = 'Hay otra operación en curso para este pedido; espera unos segundos y reintenta.'; $msgClass = 'warning';
                        } else {
                        // 1) ANULAR el actual y VERIFICAR POR ÍTEM (el batch /cancel puede devolver
                        //    HTTP 200 con un rechazo por-ítem; no basta el HTTP ok).
                        $cRes   = ($s['tipo'] === 'recogida') ? $seur->cancelCollection($s['shipment_code']) : $seur->cancelShipment($s['shipment_code']);
                        $cItems = seur::payload($cRes);
                        $cDesc  = '';
                        if (is_array($cItems)) {
                            foreach ($cItems as $it) {
                                if (is_array($it) && (string) ($it['expeditionCode'] ?? ($it['collectionCode'] ?? '')) === (string) $s['shipment_code']) {
                                    $cDesc = (string) ($it['description'] ?? ''); break;
                                }
                            }
                            if ($cDesc === '' && !empty($cItems[0]['description'])) $cDesc = (string) $cItems[0]['description'];
                        }
                        if ($cDesc === '') $cDesc = seur::primerError($cRes);
                        // Rechazo DURO (sigue vivo) aunque el HTTP sea 200: en reparto/tránsito/entregado/no anulable.
                        $cHard = (bool) preg_match('/no se puede|no anulable|reparto|tr[aá]nsito|transito|en ruta|entregad|recogid|delegaci/i', $cDesc);
                        $cOk   = (($cRes['ok'] && !$cHard) || seurCancelSoft($cDesc));
                        if (!$cOk) {
                            $msg = 'NO se ha regenerado: SEUR no confirmó la anulación del envío actual (' . htmlspecialchars($cDesc !== '' ? $cDesc : ('HTTP ' . $cRes['http'])) . '). El envío original sigue ACTIVO.';
                            $msgClass = 'danger';
                            tep_db_query("SELECT RELEASE_LOCK('" . tep_db_input($lockName) . "')");
                        } else {
                            tep_db_perform('seur_shipments', array('cancelled_at' => 'now()', 'cancelled_by' => seurOperator()), 'update', 'id = ' . $shipId);
                            // 2) REGENERAR vía seur_albaran.php (POST, TLS verificado, regen=1 → ref nueva)
                            $params['token'] = SEUR_ALB_TOKEN;
                            $ch = curl_init('https://www.francobordo.com/seur_albaran.php');
                            curl_setopt_array($ch, array(
                                CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 90,
                                CURLOPT_POST => 1, CURLOPT_POSTFIELDS => http_build_query($params),
                            ));
                            $raw  = curl_exec($ch); $cerr = curl_error($ch); // curl_close() eliminado: deprecado en PHP 8.5 (no-op desde 8.0); imprimía un aviso que rompía el tep_redirect del PRG (display_errors=On)
                            $resp = json_decode((string) $raw, true);
                            $okNew = is_array($resp) && !empty($resp['ok']) && empty($resp['dedup']) && empty($resp['recovered'])
                                     && !empty($resp['shipmentCode']) && $resp['shipmentCode'] !== $s['shipment_code'];
                            if ($okNew) {
                                if (!empty($resp['zpl']))
                                    tep_db_perform('seur_reprint_queue', array(
                                        'shipment_id' => (int) ($resp['shipment_id'] ?? 0), 'orders_id' => $oidM,
                                        'zpl' => $resp['zpl'], 'done' => 0, 'date_added' => 'now()'));
                                // 3) sincronizar seur_pudo_orders con el destino nuevo
                                if ($modo === 'punto') {
                                    tep_db_query('DELETE FROM seur_pudo_orders WHERE orders_id = ' . $oidM);
                                    tep_db_perform('seur_pudo_orders', array(
                                        'orders_id' => $oidM, 'pudo_id' => $params['pudo'], 'name' => ($params['pname'] ?? ''),
                                        'address' => ($params['dstreet'] ?? ''), 'postcode' => ($params['dcp'] ?? ''),
                                        'city' => ($params['dcity'] ?? ''),
                                        'lat' => (float) ($_POST['plat'] ?? 0), 'lng' => (float) ($_POST['plng'] ?? 0),
                                        'date_added' => 'now()'));
                                } elseif ($modo === 'domicilio') {
                                    tep_db_query('DELETE FROM seur_pudo_orders WHERE orders_id = ' . $oidM);
                                }
                                $msg = 'Envío regenerado: nuevo código <strong>' . htmlspecialchars($resp['shipmentCode']) . '</strong> (ref ' . htmlspecialchars($resp['ref'] ?? '') . '). El anterior queda anulado; la nueva etiqueta saldrá por la Zebra en ~1 min. <strong>Descarta la etiqueta anterior.</strong>';
                                $msgClass = 'success';
                            } else {
                                // ¿Se creó pese a un fallo de red/timeout? Comprobar antes de afirmar.
                                $chk = tep_db_query("SELECT shipment_code FROM seur_shipments WHERE orders_id = " . $oidM . " AND entorno = '" . tep_db_input($env) . "' AND ok = 1 AND cancelled_at IS NULL AND date_added > DATE_SUB(NOW(), INTERVAL 5 MINUTE) ORDER BY id DESC LIMIT 1");
                                if (tep_db_num_rows($chk)) {
                                    $rk = tep_db_fetch_array($chk);
                                    $msg = 'El envío anterior se anuló y parece que SÍ se creó uno nuevo (' . htmlspecialchars($rk['shipment_code']) . '), pero la respuesta no fue clara. Revisa el listado antes de reintentar.';
                                    $msgClass = 'warning';
                                } else {
                                    $why = is_array($resp)
                                        ? ($resp['error'] ?? (!empty($resp['dedup']) ? 'SEUR devolvió un envío existente, no uno nuevo' : (!empty($resp['recovered']) ? 'SEUR reutilizó el envío anulado' : 'respuesta no concluyente')))
                                        : ('sin respuesta' . ($cerr ? ': ' . $cerr : ''));
                                    $msg = 'El envío anterior fue ANULADO pero la regeneración no se completó (' . htmlspecialchars($why) . '). No ha salido ningún bulto nuevo; vuelve a abrir Modificar y reintenta.';
                                    $msgClass = 'danger';
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

    /* PRG: redirigir tras procesar para que F5/doble-submit no reejecuten la acción. */
    $_SESSION['seur_flash'] = array('m' => $msg, 'c' => $msgClass);
    $qs = 'tipo=' . urlencode($_GET['tipo'] ?? $_POST['f_tipo'] ?? 'todos')
        . '&estado=' . urlencode($_GET['estado'] ?? $_POST['f_estado'] ?? 'todos')
        . '&q=' . urlencode($_GET['q'] ?? $_POST['f_q'] ?? '');
    tep_redirect(tep_href_link('seur_envios.php', $qs));
}

/* ---- Filtros y listado ---- */
$fTipo  = $_GET['tipo']  ?? 'todos';
$fEstado = $_GET['estado'] ?? 'todos';
$fBuscar = trim($_GET['q'] ?? '');
$where = array('1=1');
if ($fTipo === 'envio')     $where[] = "tipo = 'envio'";
if ($fTipo === 'recogida')  $where[] = "tipo = 'recogida'";
if ($fEstado === 'ok')      $where[] = "ok = 1 AND cancelled_at IS NULL";
if ($fEstado === 'error')   $where[] = "ok = 0";
if ($fEstado === 'anulado') $where[] = "cancelled_at IS NOT NULL";
if ($fBuscar !== '') {
    $b = tep_db_input($fBuscar);
    $cond = array("shipment_code LIKE '%$b%'", "ref LIKE '%$b%'");
    if (ctype_digit($fBuscar)) { $cond[] = "orders_id = '" . (int) $fBuscar . "'"; $cond[] = "id_rma = '" . (int) $fBuscar . "'"; }
    $where[] = '(' . implode(' OR ', $cond) . ')';
}
$sql = 'SELECT * FROM seur_shipments WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 200';
$rows = array();
$q = tep_db_query($sql);
while ($r = tep_db_fetch_array($q)) $rows[] = $r;

/* Fila en edición + dirección del pedido web para prefijar el formulario. */
$editRow = null; $editAddr = array();
if (isset($_GET['edit']) && (int) $_GET['edit'] > 0) {
    $qe = tep_db_query('SELECT * FROM seur_shipments WHERE id = ' . (int) $_GET['edit']);
    $editRow = tep_db_fetch_array($qe);
    if ($editRow && $editRow['tipo'] === 'envio' && empty($editRow['cancelled_at']) && (int) $editRow['orders_id'] >= 10000000) {
        $qa = tep_db_query("SELECT delivery_name, delivery_street_address, delivery_suburb, delivery_postcode, delivery_city, delivery_state, delivery_country, customers_telephone, customers_email_address FROM " . TABLE_ORDERS . " WHERE orders_id = " . (int) $editRow['orders_id']);
        $editAddr = tep_db_fetch_array($qa) ?: array();
    }
}
?>
<?php require THEME . 'html/header.php'; ?>

<style>
.seur-adm { font-family: system-ui, sans-serif; max-width: 1300px; margin: 0 auto; padding: 1em; }
.seur-adm h1 { margin-top: 0; }
.seur-adm .env { font-size: 13px; padding: 3px 10px; border-radius: 4px; }
.seur-adm .env.pre { background:#fdecea; color:#c0392b; } .seur-adm .env.pro { background:#e8f6ee; color:#2e7d32; }
.seur-adm .alert { padding: 10px 14px; border-radius: 4px; margin: 10px 0; }
.seur-adm .alert.success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
.seur-adm .alert.warning { background:#fff3cd; color:#856404; border:1px solid #ffeaa7; }
.seur-adm .alert.danger  { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
.seur-adm form.filtros { margin: 12px 0; display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.seur-adm input, .seur-adm select { padding:5px 8px; border:1px solid #aaa; border-radius:4px; }
.seur-adm table { border-collapse: collapse; width: 100%; margin-top: 1em; }
.seur-adm th, .seur-adm td { border:1px solid #ddd; padding:5px 8px; text-align:left; font-size:12px; }
.seur-adm th { background:#f0f0f0; }
.seur-adm tr.anulado { opacity:.5; }
.seur-adm tr.err { background:#fff0f0; }
.seur-adm .btn { display:inline-block; padding:4px 10px; background:#3273dc; color:#fff; border:0; border-radius:4px; cursor:pointer; font-size:12px; text-decoration:none; }
.seur-adm .btn.rojo { background:#c0392b; } .seur-adm .btn.gris { background:#7f8c8d; } .seur-adm .btn.verde { background:#16a085; }
.seur-adm code { font-size:11px; }
</style>

<div class="seur-adm">
  <h1>Envíos SEUR <span class="env <?php echo $env; ?>"><?php echo $env === 'pre' ? 'ENTORNO PRUEBAS' : 'PRODUCCIÓN'; ?></span></h1>
  <p class="muted">Gestión de los envíos y devoluciones de la integración SEUR. Anular y Modificar usan la API de SEUR; reimprimir reenvía la etiqueta a la Zebra del almacén.</p>

  <?php if ($msg): ?><div class="alert <?php echo $msgClass; ?>"><?php echo $msg; ?></div><?php endif; ?>

  <form class="filtros" method="get">
    <label>Tipo:
      <select name="tipo">
        <?php foreach (array('todos'=>'Todos','envio'=>'Salida','recogida'=>'Devolución') as $k=>$v): ?>
          <option value="<?php echo $k; ?>" <?php echo $fTipo===$k?'selected':''; ?>><?php echo $v; ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Estado:
      <select name="estado">
        <?php foreach (array('todos'=>'Todos','ok'=>'OK','error'=>'Con error','anulado'=>'Anulados') as $k=>$v): ?>
          <option value="<?php echo $k; ?>" <?php echo $fEstado===$k?'selected':''; ?>><?php echo $v; ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <input type="text" name="q" value="<?php echo htmlspecialchars($fBuscar); ?>" placeholder="Buscar: pedido, RMA, ref o shipmentCode">
    <button class="btn" type="submit">Filtrar</button>
    <a class="btn gris" href="?">Limpiar</a>
  </form>

  <details class="seur-manual" style="margin:12px 0;border:1px solid #cde;border-radius:6px;padding:6px 14px;background:#f7fbff;">
    <style>.seur-manual label{display:flex;flex-direction:column;font-size:12px;gap:3px;color:#333}.seur-manual input{width:100%}</style>
    <summary style="cursor:pointer;font-weight:600;color:#2e7d32;">&#10010; Nuevo env&iacute;o manual SEUR (sin pedido &mdash; RMA a proveedor, muestras, etc.)</summary>
    <form method="post" style="margin-top:10px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 14px;max-width:840px;">
      <input type="hidden" name="do" value="crear_manual">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
      <label>Destinatario *<input type="text" name="m_dname" required></label>
      <label>Direcci&oacute;n *<input type="text" name="m_dstreet" required></label>
      <label>CP *<input type="text" name="m_dcp" required></label>
      <label>Ciudad *<input type="text" name="m_dcity" required></label>
      <label>Provincia<input type="text" name="m_dstate"></label>
      <label>Pa&iacute;s * (ES = nacional; otro = internacional autom&aacute;tico)
        <select name="m_dcountry" required>
          <optgroup label="Frecuentes">
            <option value="ES" selected>Espa&ntilde;a</option>
            <option value="PT">Portugal</option>
            <option value="FR">Francia</option>
            <option value="DE">Alemania</option>
            <option value="IT">Italia</option>
            <option value="GB">Reino Unido</option>
            <option value="NL">Pa&iacute;ses Bajos</option>
            <option value="BE">B&eacute;lgica</option>
            <option value="CH">Suiza</option>
            <option value="AT">Austria</option>
          </optgroup>
          <optgroup label="Todos">
<?php
$qPaisM = tep_db_query("select countries_iso_code_2 iso, countries_name nom from countries order by countries_name");
while ($rPaisM = tep_db_fetch_array($qPaisM)) {
    echo '            <option value="' . htmlspecialchars($rPaisM['iso']) . '">' . htmlspecialchars($rPaisM['nom']) . ' (' . htmlspecialchars($rPaisM['iso']) . ')</option>' . "\n";
}
?>
          </optgroup>
        </select>
      </label>
      <label>Servicio SEUR<select name="m_service">
        <option value="">Autom&aacute;tico (por pa&iacute;s)</option>
        <option value="31/2">Nacional domicilio B2C (31/2)</option>
        <option value="9/2">SEUR 13:30 (9/2)</option>
        <option value="3/2">SEUR 10 (3/2)</option>
        <option value="57/2">SEUR S&aacute;bado (57/2)</option>
        <option value="77/104">Internacional (77/104)</option>
      </select></label>
      <label>Tel&eacute;fono<input type="text" name="m_dphone"></label>
      <label>Email<input type="text" name="m_demail"></label>
      <label>Peso (kg)<input type="text" name="m_kilos" value="1"></label>
      <label>Bultos<input type="number" name="m_bultos" value="1" min="1"></label>
      <label style="grid-column:1/3;">Referencia (sale en la columna Pedido/RMA y es buscable &mdash; p.ej. n&ordm; de RMA, pedido o proveedor)<input type="text" name="m_ref" placeholder="auto: M-aaaammdd-hhmmss"></label>
      <div style="grid-column:1/3;margin-top:4px;"><button class="btn verde" type="submit">Crear env&iacute;o y mandar etiqueta a la Zebra</button></div>
    </form>
  </details>

  <?php
  $editEligible = $editRow && $editRow['tipo'] === 'envio' && empty($editRow['cancelled_at']) && seurModEligible(seurTrackRow($editRow['ref'] ?? ''));
  if ($editRow && $editRow['tipo'] === 'envio' && empty($editRow['cancelled_at']) && !$editEligible): ?>
  <div class="alert warning" style="max-width:780px">Este envío <code><?php echo htmlspecialchars($editRow['shipment_code']); ?></code> ya está en tránsito: no se puede modificar (anular+regenerar implicaría doble envío). Si hay que cambiar algo, gestiónalo con SEUR. <a href="<?php echo tep_href_link('seur_envios.php'); ?>">Volver</a></div>
  <?php endif; ?>
  <?php if ($editEligible):
        $eQfac = ((int) $editRow['orders_id'] > 0 && (int) $editRow['orders_id'] < 10000000);
        $eRef  = $editRow['orders_id'] ? ('Pedido ' . (int) $editRow['orders_id']) : ('RMA ' . (int) $editRow['id_rma']);
  ?>
  <div style="border:1px solid #b9d7f5;background:#f4f9ff;border-radius:6px;padding:14px;margin:12px 0;max-width:780px">
    <h3 style="margin:0 0 4px">Regenerar envío <code><?php echo htmlspecialchars($editRow['shipment_code']); ?></code></h3>
    <p class="muted" style="font-size:12px;margin:0 0 12px"><?php echo $eRef; ?> · servicio actual <?php echo htmlspecialchars($editRow['service_code'].'/'.$editRow['product_code']); ?> · <strong>se anulará el actual y se creará uno nuevo</strong> (ref nueva; la etiqueta saldrá por la Zebra). Puedes cambiar peso, bultos, destino y <strong>servicio</strong>.</p>
    <form method="post" id="seurModForm">
      <input type="hidden" name="do" value="modify"><input type="hidden" name="ship" value="<?php echo (int) $editRow['id']; ?>">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
      <div style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:10px">
        <label>Peso (kg)<br><input type="number" step="0.1" min="0.1" name="kilos" value="<?php echo htmlspecialchars($editRow['kilos'] ?: '1'); ?>" style="width:90px"></label>
        <label>Nº bultos<br><input type="number" min="1" name="bultos" value="1" style="width:90px"></label>
        <label>Servicio SEUR<br>
          <select name="svc_new">
            <option value="">(mantener el actual: <?php echo htmlspecialchars($editRow['service_code'] . '/' . $editRow['product_code']); ?>)</option>
            <option value="31/2">SEUR 24 — domicilio (31/2)</option>
            <option value="9/2">SEUR 13:30 (9/2)</option>
            <option value="3/2">SEUR 10 — antes de las 10h (3/2)</option>
            <option value="57/2">SEUR Sábado (57/2)</option>
            <option value="77/104">Internacional Classic (77/104)</option>
          </select>
          <span class="muted" style="font-size:11px;display:block">Se ignora si el destino es punto SEUR (punto = 2shop).</span>
        </label>
      </div>
      <p style="margin:6px 0 4px"><strong>Destino:</strong></p>
      <?php if (!$eQfac): ?>
      <label style="display:block"><input type="radio" name="destino" value="mantener" checked> Mantener el destino actual</label>
      <label style="display:block"><input type="radio" name="destino" value="punto"> Cambiar a / de punto SEUR</label>
      <?php endif; ?>
      <label style="display:block"><input type="radio" name="destino" value="domicilio" <?php echo $eQfac ? 'checked' : ''; ?>> Entregar a domicilio (dirección)<?php echo $eQfac ? ' <span class="muted">(pedido QFac: obligatorio indicar dirección)</span>' : ''; ?></label>

      <?php if (!$eQfac): ?>
      <div id="boxPunto" style="display:none;margin:8px 0 0;padding:10px;background:#fff;border:1px solid #ddd;border-radius:4px">
        <input type="text" id="cpPunto" maxlength="5" placeholder="CP" style="width:90px">
        <button type="button" id="btnBuscarPunto" class="btn" data-searching="Buscando&hellip;">Buscar puntos</button>
        <span id="msgPunto" style="color:#a00;font-size:12px"></span><br>
        <select id="selPunto" style="margin-top:8px;max-width:100%"></select>
        <input type="hidden" name="pudo" id="hPudo"><input type="hidden" name="pname" id="hPName">
        <input type="hidden" name="dstreet" id="hPStreet"><input type="hidden" name="dcp" id="hPCp"><input type="hidden" name="dcity" id="hPCity">
        <input type="hidden" name="plat" id="hPLat"><input type="hidden" name="plng" id="hPLng">
      </div>
      <?php endif; ?>

      <div id="boxDomic" style="display:<?php echo $eQfac ? 'block' : 'none'; ?>;margin:8px 0 0;padding:10px;background:#fff;border:1px solid #ddd;border-radius:4px;max-width:540px">
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <label style="flex:1 1 100%">Nombre<br><input type="text" name="dname_d" value="<?php echo htmlspecialchars($editAddr['delivery_name'] ?? ''); ?>" style="width:100%"></label>
          <label style="flex:1 1 100%">Dirección<br><input type="text" name="dstreet_d" value="<?php echo htmlspecialchars(trim(($editAddr['delivery_street_address'] ?? '').' '.($editAddr['delivery_suburb'] ?? ''))); ?>" style="width:100%"></label>
          <label>CP<br><input type="text" name="dcp_d" value="<?php echo htmlspecialchars($editAddr['delivery_postcode'] ?? ''); ?>" style="width:90px"></label>
          <label>Población<br><input type="text" name="dcity_d" value="<?php echo htmlspecialchars($editAddr['delivery_city'] ?? ''); ?>"></label>
          <label>Provincia<br><input type="text" name="dstate_d" value="<?php echo htmlspecialchars($editAddr['delivery_state'] ?? ''); ?>"></label>
          <label>País<br><input type="text" name="dcountry_d" value="<?php echo htmlspecialchars($editAddr['delivery_country'] ?? 'España'); ?>"></label>
          <label>Teléfono<br><input type="text" name="dphone_d" value="<?php echo htmlspecialchars($editAddr['customers_telephone'] ?? ''); ?>"></label>
          <label style="flex:1 1 100%">Email<br><input type="text" name="demail_d" value="<?php echo htmlspecialchars($editAddr['customers_email_address'] ?? ''); ?>" style="width:100%"></label>
        </div>
      </div>

      <div style="margin-top:12px">
        <button class="btn verde" type="submit" id="btnRegen">♻ Anular y regenerar</button>
        <a class="btn gris" href="<?php echo tep_href_link('seur_envios.php'); ?>">Cancelar</a>
      </div>
    </form>
  </div>
  <script>
  (function () {
    var form = document.getElementById('seurModForm');
    var bp = document.getElementById('boxPunto'), bd = document.getElementById('boxDomic');
    function sync() {
      var v = (document.querySelector('input[name="destino"]:checked') || {}).value;
      if (bp) bp.style.display = (v === 'punto') ? 'block' : 'none';
      if (bd) bd.style.display = (v === 'domicilio') ? 'block' : 'none';
    }
    document.querySelectorAll('input[name="destino"]').forEach(function (r) { r.addEventListener('change', sync); });
    sync();
    // anti doble-submit + copia de campos _d a hidden d* para domicilio
    form.addEventListener('submit', function () {
      var v = (document.querySelector('input[name="destino"]:checked') || {}).value;
      if (v === 'domicilio') {
        ['name','street','cp','city','state','country','phone','email'].forEach(function (k) {
          var s = form.querySelector('[name="d' + k + '_d"]'); if (!s) return;
          var h = document.createElement('input'); h.type = 'hidden'; h.name = 'd' + k; h.value = s.value; form.appendChild(h);
        });
      }
      var b = document.getElementById('btnRegen'); if (b) { b.disabled = true; b.textContent = 'Procesando…'; }
    });
    var btn = document.getElementById('btnBuscarPunto');
    if (btn) btn.addEventListener('click', function () {
      var cp = (document.getElementById('cpPunto').value || '').replace(/\D/g, '');
      var msg = document.getElementById('msgPunto'); msg.textContent = '';
      if (cp.length !== 5) { msg.textContent = 'CP de 5 dígitos'; return; }
      var t0 = btn.textContent; btn.textContent = btn.getAttribute('data-searching'); btn.disabled = true;
      fetch('/seur_puntos.php?cp=' + cp).then(function (r) { return r.json(); }).then(function (d) {
        btn.textContent = t0; btn.disabled = false;
        var sel = document.getElementById('selPunto'); sel.innerHTML = '';
        if (!d.ok || !(d.puntos || []).length) { msg.textContent = 'Sin puntos en ese CP'; return; }
        d.puntos.forEach(function (p) {
          var o = document.createElement('option');
          o.value = p.id; o.textContent = p.name + ' — ' + p.address + ' (' + p.cp + ' ' + p.city + ')';
          o.dataset.name = p.name; o.dataset.street = p.address; o.dataset.cp = p.cp; o.dataset.city = p.city;
          o.dataset.lat = p.lat; o.dataset.lng = p.lng;
          sel.appendChild(o);
        });
        fillPunto();
      }).catch(function () { btn.textContent = t0; btn.disabled = false; msg.textContent = 'Error, reintenta'; });
    });
    function fillPunto() {
      var sel = document.getElementById('selPunto'); if (!sel) return; var o = sel.options[sel.selectedIndex]; if (!o) return;
      document.getElementById('hPudo').value = o.value;
      document.getElementById('hPName').value = o.dataset.name || '';
      document.getElementById('hPStreet').value = o.dataset.street || '';
      document.getElementById('hPCp').value = o.dataset.cp || '';
      document.getElementById('hPCity').value = o.dataset.city || '';
      document.getElementById('hPLat').value = o.dataset.lat || '';
      document.getElementById('hPLng').value = o.dataset.lng || '';
    }
    var selP = document.getElementById('selPunto'); if (selP) selP.addEventListener('change', fillPunto);
  })();
  </script>
  <?php endif; ?>

  <table>
    <tr>
      <th>ID</th><th>Tipo</th><th>Pedido/RMA</th><th>shipmentCode</th><th>Svc/Prod</th>
      <th>Punto / Recogida</th><th>Estado</th><th>Fecha</th><th>Acciones</th>
    </tr>
    <?php if (!$rows): ?>
      <tr><td colspan="9">Sin envíos que coincidan con el filtro.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $s): ?>
      <?php
        $cls = $s['cancelled_at'] ? 'anulado' : (!$s['ok'] ? 'err' : '');
        $ref = $s['orders_id'] ? ('Pedido ' . (int) $s['orders_id']) : ($s['id_rma'] ? 'RMA ' . (int) $s['id_rma'] : (trim((string) $s['ref']) !== '' ? '<span title="Referencia manual">' . htmlspecialchars($s['ref']) . '</span>' : '—'));
        if ($s['cancelled_at']) $estado = '<span style="color:#c0392b">Anulado</span>';
        elseif ($s['ok'])       $estado = '<span style="color:#2e7d32">OK</span>';
        else                    $estado = '<span style="color:#c0392b">Error</span>';
      ?>
      <tr class="<?php echo $cls; ?>">
        <td><?php echo (int) $s['id']; ?></td>
        <td><?php echo $s['tipo'] === 'recogida' ? ($s['pudo_id'] ? '🏤 Devol. punto' : '🏠 Devol. domic.') : '📦 Salida'; ?></td>
        <td><?php echo $ref; ?></td>
        <td><code><?php echo htmlspecialchars($s['shipment_code'] ?: '—'); ?></code></td>
        <td><?php echo htmlspecialchars($s['service_code'] . '/' . $s['product_code']); ?></td>
        <td><?php echo htmlspecialchars($s['pudo_name'] ? $s['pudo_name'] . ' (' . $s['pudo_id'] . ')' : ($s['fecha_recogida'] ?: '—')); ?></td>
        <td><?php echo $estado; ?><?php if (!$s['ok'] && $s['mensaje_retorno']): ?><br><span style="color:#999"><?php echo htmlspecialchars(substr($s['mensaje_retorno'],0,60)); ?></span><?php endif; ?></td>
        <td><?php echo htmlspecialchars($s['date_added']); ?><br><span style="color:#999"><?php echo htmlspecialchars($s['entorno']); ?></span></td>
        <td>
          <?php if ($s['ok'] && !$s['cancelled_at']): ?>
            <form method="post" style="display:inline" onsubmit="return confirm('¿Reimprimir la etiqueta de este envío?');">
              <input type="hidden" name="do" value="reprint"><input type="hidden" name="ship" value="<?php echo (int) $s['id']; ?>"><input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
              <button class="btn" type="submit">⎙ Reimprimir</button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('¿ANULAR en SEUR este envío? Esta acción no se puede deshacer.');">
              <input type="hidden" name="do" value="cancel"><input type="hidden" name="ship" value="<?php echo (int) $s['id']; ?>"><input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
              <button class="btn rojo" type="submit">✕ Anular</button>
            </form>
            <?php if ($s['tipo'] === 'envio'): ?>
              <a class="btn verde" href="<?php echo tep_href_link('seur_envios.php', 'edit=' . (int) $s['id']); ?>">✎ Modificar</a>
            <?php endif; ?>
          <?php elseif ($s['cancelled_at']): ?>
            <span style="color:#999">anulado <?php echo htmlspecialchars($s['cancelled_at']); ?></span>
          <?php else: ?>
            <span style="color:#999">—</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <p class="muted" style="font-size:12px;margin-top:8px">Mostrando los últimos <?php echo count($rows); ?> (máx 200). Para modificar un envío usa ✎ Modificar (anula el actual y regenera uno nuevo corregido con ref nueva).</p>
</div>

<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
