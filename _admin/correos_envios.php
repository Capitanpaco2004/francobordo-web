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
    $raw = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    return array(json_decode((string) $raw, true), $err, $raw);
}

/* ---- Acciones (POST con CSRF) ---- */
$action = $_POST['do'] ?? '';
$shipId = (int) ($_POST['ship'] ?? 0);

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
                    $zpl = '';
                    if (!empty($s['label_zpl_path']) && is_file($s['label_zpl_path'])) $zpl = (string) file_get_contents($s['label_zpl_path']);
                    if ($zpl === '' && (int) $s['orders_id'] > 0) {
                        list($resp, , ) = correosEnvEndpoint(array('oid' => (int) $s['orders_id'], 'type' => 'ZPL'));
                        if (is_array($resp) && !empty($resp['zpl'])) $zpl = (string) $resp['zpl'];
                    }
                    if ($zpl !== '') {
                        tep_db_perform('correos_reprint_queue', array(
                            'shipment_id' => $shipId, 'orders_id' => (int) $s['orders_id'],
                            'zpl' => $zpl, 'done' => 0, 'date_added' => 'now()'));
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
                                if ($modo === 'oficina') {
                                    tep_db_query('DELETE FROM correos_oficina_orders WHERE orders_id = ' . $oidM);
                                    tep_db_perform('correos_oficina_orders', array(
                                        'orders_id' => $oidM, 'office_id' => $officeId,
                                        'name' => substr((string) ($_POST['ofi_name'] ?? ''), 0, 120),
                                        'address' => substr((string) ($_POST['ofi_addr'] ?? ''), 0, 255),
                                        'postcode' => substr(preg_replace('/\D/', '', (string) ($_POST['ofi_cp'] ?? '')), 0, 10),
                                        'city' => substr((string) ($_POST['ofi_city'] ?? ''), 0, 80),
                                        'date_added' => 'now()'));
                                    $params['mode'] = 'ofi';
                                } elseif ($modo === 'domicilio') {
                                    tep_db_query('DELETE FROM correos_oficina_orders WHERE orders_id = ' . $oidM);
                                    $params['mode'] = 'dom';
                                } else {
                                    $qo = tep_db_query('SELECT office_id FROM correos_oficina_orders WHERE orders_id = ' . $oidM . ' LIMIT 1');
                                    $params['mode'] = tep_db_num_rows($qo) ? 'ofi' : 'dom';
                                }
                                list($resp, $cerr, ) = correosEnvEndpoint($params);
                                $newCode = (is_array($resp) && !empty($resp['ok']) && empty($resp['dedup']) && !empty($resp['shipmentCode'])) ? $resp['shipmentCode'] : '';
                                if ($newCode !== '' && $newCode !== $s['shipment_code']) {
                                    if (!empty($resp['zpl']))
                                        tep_db_perform('correos_reprint_queue', array('shipment_id' => 0, 'orders_id' => $oidM, 'zpl' => $resp['zpl'], 'done' => 0, 'date_added' => 'now()'));
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
    $where[] = "(shipment_code LIKE '%$b%' OR ref LIKE '%$b%' OR orders_id = '" . (int) $fBuscar . "' OR id_rma = '" . (int) $fBuscar . "')";
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
    <input type="text" name="q" value="<?php echo htmlspecialchars($fBuscar); ?>" placeholder="Buscar: pedido, RMA, ref o shipmentCode">
    <button class="btn" type="submit">Filtrar</button>
    <a class="btn gris" href="?">Limpiar</a>
  </form>

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
        <select id="selOfi" style="margin-top:8px;max-width:100%"></select>
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
        var sel = document.getElementById('selOfi'); sel.innerHTML = '';
        if (!d.ok || !(d.oficinas || []).length) { m.textContent = 'Sin oficinas en ese CP'; return; }
        d.oficinas.forEach(function (o) {
          var op = document.createElement('option');
          op.value = o.id; op.textContent = o.name + ' — ' + o.address + ' (' + o.cp + ' ' + o.city + ')';
          op.dataset.name = o.name; op.dataset.addr = o.address; op.dataset.cp = o.cp; op.dataset.city = o.city;
          sel.appendChild(op);
        });
        fillOfi();
      }).catch(function () { btn.textContent = t0; btn.disabled = false; m.textContent = 'Error, reintenta'; });
    });
    function fillOfi() {
      var sel = document.getElementById('selOfi'); if (!sel) return; var o = sel.options[sel.selectedIndex]; if (!o) return;
      document.getElementById('hOfi').value = o.value;
      document.getElementById('hOfiName').value = o.dataset.name || '';
      document.getElementById('hOfiAddr').value = o.dataset.addr || '';
      document.getElementById('hOfiCp').value = o.dataset.cp || '';
      document.getElementById('hOfiCity').value = o.dataset.city || '';
    }
    var selO = document.getElementById('selOfi'); if (selO) selO.addEventListener('change', fillOfi);
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
        $ref = $s['orders_id'] ? ('Pedido ' . (int) $s['orders_id']) : ($s['id_rma'] ? 'RMA ' . (int) $s['id_rma'] : '—');
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
