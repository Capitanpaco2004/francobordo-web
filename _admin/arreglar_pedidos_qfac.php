<?php
/**
 * Arreglar pedidos QFac
 *
 * Detecta pedidos con orders.cfactur != 'S' que en realidad SI estan ya descargados
 * en QFacWin (EA15_COMAN.CCMDWEB) y permite marcarlos como descargado (CFACTUR=S)
 * manualmente desde el admin.
 *
 * Pipeline:
 *   1. SELECT orders donde COALESCE(cfactur,'') != 'S'
 *   2. Helper Python via Tailscale -> Firebird .5 -> SELECT CCMDWEB IN (...)
 *   3. Cruza: los que estan en Firebird son candidatos
 *   4. Admin marca checkboxes + boton -> UPDATE orders SET cfactur='S' + log
 */

require 'includes/application_top.php';

set_time_limit(120);

const QFAC_HELPER_PY  = '/home/francobordo/qfac_recovery/venv/bin/python';
const QFAC_HELPER_BIN = '/home/francobordo/qfac_recovery/check_qfac_orders.py';
const QFAC_HELPER_TIMEOUT_S = 30;

/**
 * Llama al helper Python pasandole la lista de orders_ids por stdin.
 * Devuelve array decoded de JSON: ['ok'=>bool, 'found'=>[...]] o ['ok'=>false, 'error'=>str].
 */
function qfac_helper_query(array $orders_ids): array {
    $payload = json_encode(['orders_ids' => array_values(array_map('intval', $orders_ids))]);
    $desc = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = proc_open([QFAC_HELPER_PY, QFAC_HELPER_BIN], $desc, $pipes);
    if (!is_resource($proc)) {
        return ['ok' => false, 'error' => 'No se pudo lanzar el helper Python (proc_open fallo)'];
    }
    fwrite($pipes[0], $payload);
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $stdout = '';
    $stderr = '';
    $deadline = microtime(true) + QFAC_HELPER_TIMEOUT_S;
    while (true) {
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        $status = proc_get_status($proc);
        if (!$status['running']) break;
        if (microtime(true) > $deadline) {
            proc_terminate($proc, 9);
            fclose($pipes[1]); fclose($pipes[2]);
            proc_close($proc);
            return ['ok' => false, 'error' => 'Timeout del helper Python (>' . QFAC_HELPER_TIMEOUT_S . 's). Revisar conexion Tailscale a Firebird.'];
        }
        usleep(50000);
    }
    $stdout .= stream_get_contents($pipes[1]);
    $stderr .= stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($proc);

    $decoded = json_decode($stdout, true);
    if (!is_array($decoded)) {
        $msg = 'Salida del helper no parseable.';
        if (trim($stderr) !== '') $msg .= ' stderr: ' . trim($stderr);
        if (trim($stdout) !== '') $msg .= ' stdout: ' . trim($stdout);
        $msg .= ' (exit=' . $exit . ')';
        return ['ok' => false, 'error' => $msg];
    }
    return $decoded;
}

/** SELECT orders con cfactur != 'S' ordenados por orders_id DESC. */
function qfac_load_pending(): array {
    $sql = "SELECT orders_id, COALESCE(cfactur,'') AS cfactur, date_purchased,
                   customers_name, customers_email_address, orders_status, payment_method
            FROM orders
            WHERE COALESCE(cfactur,'') <> 'S'
            ORDER BY orders_id DESC";
    $q = tep_db_query($sql);
    $rows = [];
    while ($r = tep_db_fetch_array($q)) {
        $rows[(int) $r['orders_id']] = $r;
    }
    return $rows;
}

/** Email del admin actual (best effort para log). */
function qfac_admin_email(int $login_id): string {
    if ($login_id <= 0) return '';
    $q = tep_db_query("SELECT admin_email_address FROM " . TABLE_ADMIN . " WHERE admin_id=" . (int) $login_id . " LIMIT 1");
    $r = tep_db_fetch_array($q);
    return $r ? (string) $r['admin_email_address'] : '';
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$state  = ['phase' => 'idle']; // idle | empty | error | candidates | done

if ($action === 'check' || $action === 'fix') {
    $pending = qfac_load_pending();
    if (empty($pending)) {
        $state = ['phase' => 'empty'];
    } else {
        $resp = qfac_helper_query(array_keys($pending));
        if (!$resp['ok']) {
            $state = ['phase' => 'error', 'error' => $resp['error'] ?? 'desconocido'];
        } else {
            $in_qfac = array_flip(array_map('intval', $resp['found']));

            if ($action === 'fix') {
                $selected = (isset($_POST['fix_orders']) && is_array($_POST['fix_orders']))
                    ? array_unique(array_map('intval', $_POST['fix_orders']))
                    : [];
                $to_fix = array_values(array_intersect($selected, array_keys($in_qfac)));
                $admin_email = qfac_admin_email((int) ($login_id ?? 0));
                $admin_id    = (int) ($login_id ?? 0);
                $fixed = [];
                $errors = [];
                foreach ($to_fix as $oid) {
                    $oid = (int) $oid;
                    $prev = isset($pending[$oid]) ? (string) $pending[$oid]['cfactur'] : '';
                    $upd = tep_db_query("UPDATE orders SET cfactur='S' WHERE orders_id=" . $oid . " LIMIT 1");
                    if ($upd) {
                        tep_db_query(
                            "INSERT INTO qfac_recovery_log "
                            . "(orders_id, prev_cfactur, ccmdweb_in_qfac, action, admin_id, admin_email) "
                            . "VALUES (" . $oid . ", '" . tep_db_input($prev) . "', 1, 'fixed', "
                            . ($admin_id > 0 ? $admin_id : 'NULL') . ", '" . tep_db_input($admin_email) . "')"
                        );
                        $fixed[] = $oid;
                    } else {
                        $errors[] = $oid;
                    }
                }
                // releer estado actual
                $pending = qfac_load_pending();
                $state = [
                    'phase'   => 'done',
                    'pending' => $pending,
                    'in_qfac' => $in_qfac,
                    'fixed'   => $fixed,
                    'errors'  => $errors,
                ];
            } else {
                $state = [
                    'phase'   => 'candidates',
                    'pending' => $pending,
                    'in_qfac' => $in_qfac,
                ];
            }
        }
    }
}
?>
<?php require THEME . 'html/header.php'; ?>

<style>
.qfac-rec { font-family: system-ui, sans-serif; max-width: 1200px; margin: 0 auto; padding: 1em; }
.qfac-rec h1 { margin-top: 0; }
.qfac-rec .alert { padding: 10px 14px; border-radius: 4px; margin: 10px 0; }
.qfac-rec .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.qfac-rec .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
.qfac-rec .alert-danger  { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.qfac-rec table { border-collapse: collapse; width: 100%; margin-top: 1em; }
.qfac-rec th, .qfac-rec td { border: 1px solid #ccc; padding: 6px 10px; text-align: left; font-size: 13px; }
.qfac-rec th { background: #f0f0f0; }
.qfac-rec tr.in-qfac    { background: #eaffea; }
.qfac-rec tr.not-in-qfac { background: #ffeaea; color: #777; }
.qfac-rec .btn { display: inline-block; padding: 8px 16px; background: #3273dc; color: #fff;
                 border: 0; border-radius: 4px; cursor: pointer; font-size: 14px; text-decoration: none; }
.qfac-rec .btn:hover { background: #2160b3; }
.qfac-rec .btn-danger { background: #c0392b; }
.qfac-rec .btn-danger:hover { background: #962d22; }
.qfac-rec .muted { color: #888; }
.qfac-rec .summary { font-size: 14px; margin: 1em 0; }
.qfac-rec pre { background: #f7f7f7; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
</style>

<div class="qfac-rec">
  <h1>Arreglar pedidos QFac</h1>

  <p class="muted">
    Cruza <code>orders.cfactur &lt;&gt; 'S'</code> contra <code>EA15_COMAN.CCMDWEB</code> de QFacWin.
    Si un pedido esta presente en QFac pero no marcado en la web, se puede marcar como descargado aqui.
    No toca QFac, solo la columna <code>cfactur</code> en MySQL.
  </p>

<?php if ($state['phase'] === 'idle'): ?>
  <form method="post">
    <input type="hidden" name="action" value="check">
    <button type="submit" class="btn">Buscar pedidos sin marcar</button>
  </form>

<?php elseif ($state['phase'] === 'empty'): ?>
  <div class="alert alert-success">
    <strong>Todo en orden.</strong> No hay pedidos con <code>cfactur &lt;&gt; 'S'</code>.
  </div>
  <a href="?" class="btn">Volver</a>

<?php elseif ($state['phase'] === 'error'): ?>
  <div class="alert alert-danger">
    <strong>Error al consultar QFacWin:</strong>
    <pre><?= htmlspecialchars($state['error']) ?></pre>
    <p>Comprueba que el subnet-routing de Tailscale en 192.168.1.112 esta activo y que Firebird en 192.168.1.5:3050 acepta conexiones.</p>
  </div>
  <a href="?" class="btn">Reintentar</a>

<?php elseif ($state['phase'] === 'candidates' || $state['phase'] === 'done'):
  $pending = $state['pending'] ?? [];
  $in_qfac = $state['in_qfac'] ?? [];
  $candidates  = array_intersect_key($pending, $in_qfac);
  $not_in_qfac = array_diff_key($pending, $in_qfac);
?>

  <?php if ($state['phase'] === 'done'):
    $fixed  = $state['fixed']  ?? [];
    $errors = $state['errors'] ?? []; ?>
    <?php if (!empty($fixed)): ?>
      <div class="alert alert-success">
        <strong>Marcados como CFACTUR=S:</strong> <?= count($fixed) ?> pedido(s) — IDs: <?= htmlspecialchars(implode(', ', $fixed)) ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <strong>Errores al actualizar:</strong> <?= htmlspecialchars(implode(', ', $errors)) ?>
      </div>
    <?php endif; ?>
    <?php if (empty($fixed) && empty($errors)): ?>
      <div class="alert alert-warning">Ningun pedido seleccionado o sin candidatos validos.</div>
    <?php endif; ?>
  <?php endif; ?>

  <div class="summary">
    <strong><?= count($pending) ?></strong> pedido(s) con <code>cfactur &lt;&gt; 'S'</code>.
    De ellos, <strong style="color:#155724"><?= count($candidates) ?></strong> SI estan en QFac (candidatos a fix)
    y <strong style="color:#721c24"><?= count($not_in_qfac) ?></strong> NO estan en QFac (se quedan como estan).
  </div>

  <?php if (!empty($pending)): ?>
    <form method="post" onsubmit="return confirm('Marcar los pedidos seleccionados como CFACTUR=S?');">
      <input type="hidden" name="action" value="fix">
      <table>
        <thead>
        <tr>
          <th style="width: 30px">
            <input type="checkbox" id="qfac-check-all"
                   onclick="document.querySelectorAll('input.qfac-fix-check[data-inqfac=\'1\']').forEach(function(c){c.checked=this.checked}.bind(this))">
          </th>
          <th>Order ID</th>
          <th>Fecha</th>
          <th>Cliente</th>
          <th>Email</th>
          <th>Status</th>
          <th>cfactur</th>
          <th>En QFac?</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($pending as $oid => $row):
          $in = isset($in_qfac[$oid]); ?>
          <tr class="<?= $in ? 'in-qfac' : 'not-in-qfac' ?>">
            <td>
              <?php if ($in): ?>
                <input type="checkbox" class="qfac-fix-check" data-inqfac="1" name="fix_orders[]" value="<?= (int) $oid ?>" checked>
              <?php else: ?>
                <input type="checkbox" disabled title="No esta en QFac, no se puede marcar">
              <?php endif; ?>
            </td>
            <td><?= (int) $oid ?></td>
            <td><?= htmlspecialchars($row['date_purchased']) ?></td>
            <td><?= htmlspecialchars($row['customers_name']) ?></td>
            <td><?= htmlspecialchars($row['customers_email_address']) ?></td>
            <td><?= (int) $row['orders_status'] ?></td>
            <td><?= htmlspecialchars($row['cfactur'] === '' ? '(vacio)' : $row['cfactur']) ?></td>
            <td><?= $in ? '<strong style="color:#155724">SI</strong>' : '<span class="muted">no</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <?php if (count($candidates) > 0): ?>
        <p style="margin-top:1em">
          <button type="submit" class="btn btn-danger">
            Marcar seleccionados como descargado (CFACTUR=S)
          </button>
          <a href="?" class="btn" style="background:#888">Cancelar</a>
        </p>
      <?php else: ?>
        <p style="margin-top:1em" class="muted">
          No hay candidatos (ningun pedido sin marcar esta en QFac). Nada que hacer.
        </p>
        <a href="?" class="btn">Volver</a>
      <?php endif; ?>
    </form>
  <?php endif; ?>

<?php endif; ?>

</div>

<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
