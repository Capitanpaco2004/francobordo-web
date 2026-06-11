<?php
/**
 * Panel de envíos SEUR (salida + devoluciones) — _admin/seur_envios.php
 *
 * Lista la tabla seur_shipments con filtros y permite por cada envío:
 *   - Anular     : /shipments/cancel (salida) o /collections/cancel (devolución)
 *   - Reimprimir : reenvía el ZPL a la cola de impresión (buzón del watcher en el .112)
 *   - Etiqueta   : descarga el PDF guardado / lo regenera de la API
 *
 * Entorno SEUR según seur_config (pre/pro), como el resto de piezas.
 * Ver memoria francobordo_seur_api.
 */

require 'includes/application_top.php';
require_once DIR_FS_CATALOG . 'includes/classes/seur.php';

set_time_limit(120);

/* Entorno activo */
function seurEnvAdminEnv() {
    $q = tep_db_query("SELECT config_value FROM seur_config WHERE config_key = 'env'");
    if ($q && tep_db_num_rows($q)) { $v = tep_db_fetch_array($q); return ($v['config_value'] === 'pro') ? 'pro' : 'pre'; }
    return 'pre';
}
$env = seurEnvAdminEnv();

/* Buzón de impresión: el watcher del .112 escribe el ZPL en su carpeta local 'out'
 * y el relevo del .5 lo lleva a la Zebra. Desde la web (nic1) no alcanzamos esas
 * carpetas; la reimpresión se hace dejando el ZPL en una cola que el watcher
 * recoge por su API. Para el admin: reimprimir = recrear el .zpl en el "drop"
 * accesible. Aquí, al estar nic1 fuera de la LAN, la reimpresión la registramos
 * como pendiente y el watcher la materializa en su próxima pasada (campo reprint). */

$msg = ''; $msgClass = '';

/* ---- Acciones ---- */
$action = $_POST['do'] ?? '';
$shipId = (int) ($_POST['ship'] ?? 0);

if ($action !== '' && $shipId > 0) {
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
                $res = ($s['tipo'] === 'recogida')
                    ? $seur->cancelCollection($s['shipment_code'])
                    : $seur->cancelShipment($s['shipment_code']);
                $d = seur::payload($res);
                $desc = (is_array($d) && !empty($d[0]['description'])) ? $d[0]['description'] : seur::primerError($res);
                if ($res['ok']) {
                    tep_db_perform('seur_shipments', array('cancelled_at' => 'now()'), 'update', 'id = ' . $shipId);
                    $msg = 'Envío ' . htmlspecialchars($s['shipment_code']) . ' anulado en SEUR. ' . htmlspecialchars($desc);
                    $msgClass = 'success';
                } else {
                    // marcamos anulado localmente igualmente si la API dice que ya no existe / error suave
                    tep_db_perform('seur_shipments', array('cancelled_at' => 'now()'), 'update', 'id = ' . $shipId);
                    $msg = 'SEUR devolvió: ' . htmlspecialchars($desc) . '. Marcado como anulado localmente.';
                    $msgClass = 'warning';
                }
            }
        } elseif ($action === 'reprint') {
            // Regenera la etiqueta ZPL desde la API y la marca para reimpresión:
            // se vuelve a guardar el .zpl junto al envío y se inserta en la cola
            // 'seur_reprint_queue' que el watcher del .112 vacía hacia la impresora.
            $entity = ($s['tipo'] === 'recogida') ? 'COLLECTIONS' : 'SHIPMENTS';
            $lopts = array('entity' => $entity);
            if ($s['tipo'] !== 'recogida') $lopts['templateType'] = 'Z4_TWO_BODIES';
            if (!empty($s['pudo_id'])) $lopts['qr'] = true;
            $lab = $seur->getLabel($s['shipment_code'], ($s['tipo'] === 'recogida' ? 'PDF' : 'ZPL'), $lopts);
            $zpl = $lab['label'] ?? '';
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
        }
    }
}

/* ---- Filtros y listado ---- */
$fTipo  = $_GET['tipo']  ?? 'todos';      // todos | envio | recogida
$fEstado = $_GET['estado'] ?? 'todos';    // todos | ok | error | anulado
$fBuscar = trim($_GET['q'] ?? '');
$where = array('1=1');
if ($fTipo === 'envio')     $where[] = "tipo = 'envio'";
if ($fTipo === 'recogida')  $where[] = "tipo = 'recogida'";
if ($fEstado === 'ok')      $where[] = "ok = 1 AND cancelled_at IS NULL";
if ($fEstado === 'error')   $where[] = "ok = 0";
if ($fEstado === 'anulado') $where[] = "cancelled_at IS NOT NULL";
if ($fBuscar !== '') {
    $b = tep_db_input($fBuscar);
    $where[] = "(shipment_code LIKE '%$b%' OR ref LIKE '%$b%' OR orders_id = '" . (int) $fBuscar . "' OR id_rma = '" . (int) $fBuscar . "')";
}
$sql = 'SELECT * FROM seur_shipments WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 200';
$rows = array();
$q = tep_db_query($sql);
while ($r = tep_db_fetch_array($q)) $rows[] = $r;
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
.seur-adm .btn.rojo { background:#c0392b; } .seur-adm .btn.gris { background:#7f8c8d; }
.seur-adm code { font-size:11px; }
</style>

<div class="seur-adm">
  <h1>Envíos SEUR <span class="env <?php echo $env; ?>"><?php echo $env === 'pre' ? 'ENTORNO PRUEBAS' : 'PRODUCCIÓN'; ?></span></h1>
  <p class="muted">Gestión de los envíos y devoluciones generados por la integración SEUR (salida desde Vstock y devoluciones RMA). Anular usa la API de SEUR; reimprimir reenvía la etiqueta a la Zebra del almacén.</p>

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
        $ref = $s['orders_id'] ? ('Pedido ' . (int) $s['orders_id']) : ($s['id_rma'] ? 'RMA ' . (int) $s['id_rma'] : '—');
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
            <form method="post" style="display:inline" onsubmit="return confirm('¿Reimprimir la etiqueta del envío <?php echo htmlspecialchars($s['shipment_code']); ?>?');">
              <input type="hidden" name="do" value="reprint"><input type="hidden" name="ship" value="<?php echo (int) $s['id']; ?>">
              <button class="btn" type="submit">⎙ Reimprimir</button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('¿ANULAR en SEUR el envío <?php echo htmlspecialchars($s['shipment_code']); ?>? Esta acción no se puede deshacer.');">
              <input type="hidden" name="do" value="cancel"><input type="hidden" name="ship" value="<?php echo (int) $s['id']; ?>">
              <button class="btn rojo" type="submit">✕ Anular</button>
            </form>
          <?php elseif ($s['cancelled_at']): ?>
            <span style="color:#999">anulado <?php echo htmlspecialchars($s['cancelled_at']); ?></span>
          <?php else: ?>
            <span style="color:#999">—</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <p class="muted" style="font-size:12px;margin-top:8px">Mostrando los últimos <?php echo count($rows); ?> (máx 200). Para modificar un envío: anúlalo y vuelve a finalizar el albarán en Vstock (se genera uno nuevo).</p>
</div>

<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
