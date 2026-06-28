<?php
/**
 * Panel de envíos Correos Express (salida) — _admin/cex_envios.php
 *
 *  - Reimprimir : reencola el ZPL guardado para la impresora CorreosExpressVSTK
 *                 (cola cex_reprint_queue que vacía el watcher .112).
 *  - Anular     : marca el envío como anulado en LOCAL. (La clase CEX no expone
 *                 aún anular-envío de salida; la anulación real en CEX, si procede,
 *                 se gestiona aparte.)
 *
 * Seguridad: token CSRF de sesión + patrón PRG (redirect tras POST). Entorno CEX
 * según cex_config. Gemelo de seur_envios.php. Ver memoria francobordo_correos_express_api.
 */
require 'includes/application_top.php';
set_time_limit(60);

function cexEnvEnv() {
    $q = tep_db_query("SELECT config_value FROM cex_config WHERE config_key = 'env'");
    if ($q && tep_db_num_rows($q)) { $v = tep_db_fetch_array($q); return ($v['config_value'] === 'test') ? 'test' : 'pro'; }
    return 'pro';
}
$env = cexEnvEnv();
$msg = ''; $msgClass = '';

if (empty($_SESSION['cex_csrf'])) $_SESSION['cex_csrf'] = tep_create_random_value(32);
$csrf = $_SESSION['cex_csrf'];
if (!empty($_SESSION['cex_flash'])) {
    $msg = $_SESSION['cex_flash']['m']; $msgClass = $_SESSION['cex_flash']['c'];
    unset($_SESSION['cex_flash']);
}

/* ---- Manifiesto del día (PDF de CEX, apiRestGeneracionInformes). Antes del HTML
 *      para poder hacer la descarga. Recoge los nº de envío OK de la fecha. ---- */
if (isset($_GET['manifiesto'])) {
    $fp = trim((string) $_GET['manifiesto']);
    $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fp) ? $fp : date('Y-m-d');
    $envs = array();
    $rq = tep_db_query("SELECT shipment_code FROM cex_shipments WHERE tipo='envio' AND ok=1 AND cancelled_at IS NULL AND entorno='" . tep_db_input($env) . "' AND shipment_code <> '' AND DATE(date_added) = '" . tep_db_input($fecha) . "' ORDER BY id");
    while ($x = tep_db_fetch_array($rq)) $envs[] = $x['shipment_code'];
    if (!$envs) {
        $_SESSION['cex_flash'] = array('m' => 'No hay envíos Correos Express el ' . htmlspecialchars($fecha) . ' para generar el manifiesto.', 'c' => 'warning');
        tep_redirect(tep_href_link('cex_envios.php'));
    }
    require_once DIR_FS_CATALOG . 'includes/classes/correos_express.php';
    $cexM = new correos_express($env); $cexM->setTimeout(45);
    $resM = $cexM->generarInforme(date('d-m-Y', strtotime($fecha)), $envs);
    $dM = $resM['data'] ?? array();
    $pdf = base64_decode((string) ($dM['result'] ?? ''), true);
    if (empty($resM['ok']) || (int) ($dM['codErr'] ?? -1) !== 0 || $pdf === false || strncmp((string) $pdf, '%PDF', 4) !== 0) {
        $det = (string) ($dM['desErr'] ?? ('HTTP ' . ($resM['http'] ?? '?')));
        $_SESSION['cex_flash'] = array('m' => 'No se pudo generar el manifiesto (' . count($envs) . ' envíos): ' . htmlspecialchars($det), 'c' => 'error');
        tep_redirect(tep_href_link('cex_envios.php'));
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="manifiesto_cex_' . $fecha . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

/* Estado de tracking por referencia (cex_tracking, cron horario). */
function cexEnvTrack($ref) {
    $ref = trim((string) $ref);
    if ($ref === '') return null;
    $q = tep_db_query("SELECT estado_desc, entregado FROM cex_tracking WHERE referencia = '" . tep_db_input($ref) . "' LIMIT 1");
    return tep_db_num_rows($q) ? tep_db_fetch_array($q) : null;
}

/* ---- Acciones (POST con CSRF) ---- */
$action = $_POST['do'] ?? '';
$shipId = (int) ($_POST['ship'] ?? 0);
if ($action !== '' && $shipId > 0) {
    if (($_POST['csrf'] ?? '') !== $csrf) {
        $msg = 'Token CSRF inválido. Recarga la página.'; $msgClass = 'error';
    } else {
        $row = tep_db_fetch_array(tep_db_query('SELECT * FROM cex_shipments WHERE id = ' . $shipId));
        if (!$row) { $msg = 'Envío no encontrado.'; $msgClass = 'error'; }
        elseif ($action === 'reprint') {
            $zpl = ($row['label_zpl_path'] && is_file($row['label_zpl_path'])) ? file_get_contents($row['label_zpl_path']) : '';
            if (trim($zpl) === '') { $msg = 'No hay etiqueta ZPL guardada para reimprimir (envío ' . (int) $row['id'] . ').'; $msgClass = 'error'; }
            else {
                tep_db_perform('cex_reprint_queue', array(
                    'shipment_id' => (int) $row['id'], 'orders_id' => (int) $row['orders_id'],
                    'zpl' => $zpl, 'done' => 0, 'date_added' => 'now()'));
                $msg = 'Etiqueta reencolada para impresión (envío ' . htmlspecialchars((string) $row['shipment_code']) . ').'; $msgClass = 'success';
            }
        } elseif ($action === 'cancel') {
            if ($row['cancelled_at']) { $msg = 'El envío ya estaba anulado.'; $msgClass = 'warning'; }
            else {
                tep_db_query('UPDATE cex_shipments SET cancelled_at = NOW() WHERE id = ' . (int) $row['id']);
                $msg = 'Envío ' . htmlspecialchars((string) $row['shipment_code']) . ' marcado como ANULADO (local).'; $msgClass = 'success';
            }
        }
    }
    $_SESSION['cex_flash'] = array('m' => $msg, 'c' => $msgClass);
    tep_redirect(tep_href_link('cex_envios.php', 'estado=' . urlencode($_GET['estado'] ?? $_POST['f_estado'] ?? 'todos') . '&q=' . urlencode($_GET['q'] ?? $_POST['f_q'] ?? '')));
}

/* ---- Filtros y listado ---- */
$fEstado = $_GET['estado'] ?? 'todos';
$fBuscar = trim($_GET['q'] ?? '');
$where = array("tipo = 'envio'");
if ($fEstado === 'ok')      $where[] = "ok = 1 AND cancelled_at IS NULL";
if ($fEstado === 'error')   $where[] = "ok = 0";
if ($fEstado === 'anulado') $where[] = "cancelled_at IS NOT NULL";
if ($fBuscar !== '') {
    $b = tep_db_input($fBuscar);
    $cond = array("shipment_code LIKE '%$b%'", "ref LIKE '%$b%'");
    if (ctype_digit($fBuscar)) $cond[] = "orders_id = '" . (int) $fBuscar . "'";
    $where[] = '(' . implode(' OR ', $cond) . ')';
}
$rows = array();
$q = tep_db_query('SELECT * FROM cex_shipments WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 200');
while ($r = tep_db_fetch_array($q)) $rows[] = $r;

/* nº pendientes en cola de reimpresión */
$qp = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) c FROM cex_reprint_queue WHERE done = 0'));
$pendImpr = (int) ($qp['c'] ?? 0);
?>
<?php require THEME . 'html/header.php'; ?>
<style>
  .cexw { padding:14px 18px; }
  .cexw h1 { font-size:20px; margin:0 0 4px; }
  .cexw .badge { display:inline-block; padding:2px 8px; border-radius:3px; color:#fff; font-size:11px; font-weight:bold; }
  .cexw table { width:100%; border-collapse:collapse; font-size:13px; margin-top:10px; }
  .cexw th, .cexw td { border-bottom:1px solid #e4e4e4; padding:6px 8px; text-align:left; vertical-align:top; }
  .cexw th { background:#f5f5f5; }
  .cexw .btn { display:inline-block; padding:4px 9px; border:0; border-radius:3px; color:#fff; cursor:pointer; font-size:12px; text-decoration:none; }
  .cexw .azul { background:#27a5d2; } .cexw .rojo { background:#c0392b; }
  .cexw .flash { padding:8px 12px; border-radius:4px; margin:8px 0; }
  .cexw .flash.success { background:#e3f6e3; color:#1f7a1f; } .cexw .flash.error { background:#fde8e8; color:#a12; }
  .cexw .flash.warning { background:#fff7e0; color:#8a6d1a; }
  .cexw .muted { color:#888; } .cexw a { color:#27a5d2; }
  .cexw .filtros { margin:8px 0; } .cexw .filtros input, .cexw .filtros select { padding:4px 6px; }
</style>
<div class="cexw">
  <h1>📦 Envíos Correos Express
    <span class="badge" style="background:<?php echo $env === 'pro' ? '#2e9e44' : '#c0392b'; ?>"><?php echo $env === 'pro' ? 'PRODUCCIÓN' : 'PRUEBAS (TEST)'; ?></span>
  </h1>
  <p class="muted" style="font-size:12px;margin:2px 0 0">Salida por API (agencias Vstock "Correos Express" / "C.Express Punto"). Cola de impresión pendiente: <strong><?php echo $pendImpr; ?></strong>.</p>

  <div style="margin:8px 0">
    <a class="btn azul" href="<?php echo tep_href_link('cex_envios.php', 'manifiesto=' . date('Y-m-d')); ?>" style="font-size:13px">📄 Manifiesto de hoy (PDF)</a>
    <span class="muted" style="font-size:12px">&nbsp;·&nbsp; otra fecha:
      <form method="get" style="display:inline" action="<?php echo tep_href_link('cex_envios.php'); ?>">
        <input type="date" name="manifiesto" value="<?php echo date('Y-m-d'); ?>" style="padding:3px">
        <button class="btn azul" type="submit" style="font-size:12px">Generar</button>
      </form>
    </span>
  </div>

  <?php if ($msg !== ''): ?><div class="flash <?php echo htmlspecialchars($msgClass); ?>"><?php echo $msg; ?></div><?php endif; ?>

  <form method="get" class="filtros">
    Estado:
    <select name="estado" onchange="this.form.submit()">
      <?php foreach (array('todos'=>'Todos','ok'=>'OK','error'=>'Con error','anulado'=>'Anulados') as $k=>$v): ?>
        <option value="<?php echo $k; ?>" <?php echo $fEstado === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
      <?php endforeach; ?>
    </select>
    &nbsp; <input type="text" name="q" value="<?php echo htmlspecialchars($fBuscar); ?>" placeholder="pedido / nº envío / ref">
    <button class="btn azul" type="submit">Buscar</button>
  </form>

  <table>
    <tr><th>ID</th><th>Pedido</th><th>Ref</th><th>Nº Envío CEX</th><th>Prod</th><th>Punto</th><th>Estado</th><th>Fecha</th><th>Seguimiento</th><th>Acciones</th></tr>
    <?php foreach ($rows as $s): $trk = cexEnvTrack((string) $s['ref']); ?>
      <tr>
        <td><?php echo (int) $s['id']; ?></td>
        <td><?php echo $s['orders_id'] ? '<a href="' . tep_href_link('orders.php', 'oID=' . (int) $s['orders_id'] . '&action=edit') . '">' . (int) $s['orders_id'] . '</a>' : '—'; ?></td>
        <td><?php echo htmlspecialchars((string) $s['ref']); ?></td>
        <td><?php echo htmlspecialchars((string) $s['shipment_code']); ?><?php if ($s['ecb']): ?><br><small class="muted"><?php echo htmlspecialchars((string) $s['ecb']); ?></small><?php endif; ?></td>
        <td><?php echo $s['product_code'] == '18' ? 'Punto' : 'ePaq24'; ?></td>
        <td><?php echo $s['pudo_id'] ? htmlspecialchars((string) $s['pudo_name'] ?: $s['pudo_id']) : '—'; ?></td>
        <td><?php
            if (!$s['ok']) echo '<span style="color:#c0392b">ERROR</span><br><small class="muted">' . htmlspecialchars(substr((string) $s['mensaje_retorno'], 0, 60)) . '</small>';
            elseif ($s['cancelled_at']) echo '<span class="muted">anulado</span>';
            elseif ($trk) echo htmlspecialchars((string) $trk['estado_desc']) . ((int) $trk['entregado'] === 1 ? ' ✅' : '');
            else echo '<span class="muted">en curso</span>';
        ?></td>
        <td><small><?php echo htmlspecialchars((string) $s['date_added']); ?></small></td>
        <td><?php echo $s['tracking_url'] ? '<a href="' . htmlspecialchars((string) $s['tracking_url']) . '" target="_blank">ver</a>' : '—'; ?></td>
        <td>
          <?php if ($s['ok'] && !$s['cancelled_at']): ?>
            <form method="post" style="display:inline" onsubmit="return confirm('¿Reimprimir la etiqueta?');">
              <input type="hidden" name="do" value="reprint"><input type="hidden" name="ship" value="<?php echo (int) $s['id']; ?>"><input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
              <button class="btn azul" type="submit">🖨 Reimprimir</button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('¿Marcar este envío como ANULADO (local)?');">
              <input type="hidden" name="do" value="cancel"><input type="hidden" name="ship" value="<?php echo (int) $s['id']; ?>"><input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
              <button class="btn rojo" type="submit">✕ Anular</button>
            </form>
          <?php elseif ($s['cancelled_at']): ?>
            <span class="muted">anulado <?php echo htmlspecialchars((string) $s['cancelled_at']); ?></span>
          <?php else: ?><span class="muted">—</span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="10" class="muted">Sin envíos.</td></tr><?php endif; ?>
  </table>
  <p class="muted" style="font-size:12px;margin-top:8px">Mostrando los últimos <?php echo count($rows); ?> (máx 200). "Reimprimir" reencola el ZPL para la impresora CorreosExpressVSTK (lo recoge el watcher).</p>
</div>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
