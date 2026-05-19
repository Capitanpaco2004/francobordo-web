<?php
// Wrapper admin para disparar a demanda el actualizador de precios Minderest.
// Lanza el endpoint publico /actuprice_minderest.php (gated por UA cPanel-Cron)
// en background via curl loopback y muestra el listado de logs ya existentes.

require 'includes/application_top.php';

const ACTUPRICE_LOG_DIR  = '../temp/log_minderest';
const ACTUPRICE_LOCKFILE = '../temp/actuprice_minderest.lock';
const ACTUPRICE_URL      = 'https://www.francobordo.com/actuprice_minderest.php';

function actuprice_is_running(): bool {
    if (!file_exists(ACTUPRICE_LOCKFILE)) return false;
    $h = @fopen(ACTUPRICE_LOCKFILE, 'c');
    if (!$h) return false;
    $locked = flock($h, LOCK_EX | LOCK_NB);
    if ($locked) { flock($h, LOCK_UN); fclose($h); return false; }
    fclose($h);
    return true;
}

function actuprice_log_ok(string $name): bool {
    return (bool) preg_match('/^log_minderest_[\d_\-]+(_fin)?\.txt$/', $name);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$flash  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'run') {
    if (actuprice_is_running()) {
        $flash = ['error', 'Hay otra ejecucion en curso. Espera a que termine.'];
    } else {
        // Fire-and-forget: curl loopback con UA cPanel-Cron (el endpoint exige ese UA).
        // PHP-FPM en este host tiene exec/shell_exec deshabilitados por seguridad; usamos popen,
        // que tambien arranca un shell y permite '&' para desencajar el proceso.
        $cmd = '/usr/bin/nohup /usr/bin/curl -fsS -A ' . escapeshellarg('cPanel-Cron')
             . ' --max-time 7200 ' . escapeshellarg(ACTUPRICE_URL)
             . ' > /dev/null 2>&1 < /dev/null &';
        $ph = popen($cmd, 'r');
        if ($ph !== false) {
            pclose($ph);
            $flash = ['success', 'Lanzado. El cron suele tardar unos minutos; recarga la pagina para ver el nuevo log.'];
        } else {
            $flash = ['error', 'No se pudo lanzar el proceso (popen fallo).'];
        }
    }
}

if ($action === 'view' && isset($_GET['log']) && actuprice_log_ok($_GET['log'])) {
    $logPath = ACTUPRICE_LOG_DIR . '/' . $_GET['log'];
    if (is_file($logPath)) {
        $size     = filesize($logPath);
        $maxBytes = 500 * 1024;
        $content  = $size > $maxBytes
            ? '... (truncado: ultimos 500KB de ' . $size . ' bytes) ...' . "\n\n"
              . file_get_contents($logPath, false, null, max(0, $size - $maxBytes))
            : file_get_contents($logPath);
        require THEME . 'html/header.php';
        echo '<div style="padding:1em">';
        echo '<h2>' . htmlspecialchars($_GET['log']) . '</h2>';
        echo '<p><a href="actuprice_minderest_run.php">&larr; Volver</a></p>';
        echo '<pre style="background:#111;color:#eee;padding:1em;max-height:70vh;overflow:auto;white-space:pre-wrap;font-family:monospace;font-size:12px">';
        echo htmlspecialchars($content);
        echo '</pre>';
        echo '</div>';
        require THEME . 'html/footer.php';
        require DIR_WS_INCLUDES . 'application_bottom.php';
        exit;
    }
}

$logs = [];
if (is_dir(ACTUPRICE_LOG_DIR)) {
    $files = glob(ACTUPRICE_LOG_DIR . '/log_minderest_*.txt') ?: [];
    usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
    $files = array_slice($files, 0, 30);
    foreach ($files as $f) {
        $logs[] = [
            'name'     => basename($f),
            'mtime'    => filemtime($f),
            'size'     => filesize($f),
            'finished' => str_ends_with($f, '_fin.txt'),
        ];
    }
}

$running = actuprice_is_running();
?>
<?php require THEME . 'html/header.php'; ?>
<table border="0" width="100%" cellspacing="2" cellpadding="2">
  <tr>
    <td width="<?php echo BOX_WIDTH; ?>" valign="top"><table border="0" width="<?php echo BOX_WIDTH; ?>" cellspacing="1" cellpadding="1" class="columnLeft">
      <?php require DIR_WS_INCLUDES . 'column_left.php'; ?>
    </table></td>
    <td width="100%" valign="top">
      <h1 class="pageHeading" style="margin-top:0">Actualizador Minderest</h1>
      <p>Dispara a demanda <code>/actuprice_minderest.php</code> (el mismo endpoint que el cron de cPanel ejecuta a las <b>00:25</b> y <b>12:25</b> cada dia).</p>

      <?php if ($flash): ?>
        <div style="padding:0.6em 1em;margin:0.8em 0;border-radius:4px;background:<?php echo $flash[0] === 'success' ? '#dff0d8' : '#f2dede'; ?>;color:<?php echo $flash[0] === 'success' ? '#3c763d' : '#a94442'; ?>;border:1px solid <?php echo $flash[0] === 'success' ? '#d6e9c6' : '#ebccd1'; ?>">
          <?php echo htmlspecialchars($flash[1]); ?>
        </div>
      <?php endif; ?>

      <p><b>Estado:</b>
        <?php if ($running): ?>
          <span style="color:#a94442;font-weight:bold">En ejecucion (lockfile activo)</span>
        <?php else: ?>
          <span style="color:#3c763d;font-weight:bold">Idle</span>
        <?php endif; ?>
      </p>

      <form method="post" action="actuprice_minderest_run.php?action=run" onsubmit="return confirm('Lanzar el actualizador Minderest ahora?');">
        <button type="submit" <?php echo $running ? 'disabled' : ''; ?> style="padding:0.6em 1.4em;font-weight:bold;background:<?php echo $running ? '#aaa' : '#337ab7'; ?>;color:#fff;border:0;border-radius:4px;cursor:<?php echo $running ? 'not-allowed' : 'pointer'; ?>;font-size:14px">
          Ejecutar ahora
        </button>
      </form>

      <h3 style="margin-top:2em">Ultimas ejecuciones (30)</h3>
      <table cellpadding="6" cellspacing="0" border="0" style="border-collapse:collapse;width:100%;border:1px solid #ddd">
        <tr style="background:#f5f5f5;border-bottom:1px solid #ccc">
          <th align="left">Fichero</th>
          <th align="left">Fecha</th>
          <th align="right">Tamano</th>
          <th align="center">Estado</th>
          <th></th>
        </tr>
        <?php foreach ($logs as $log): ?>
          <tr style="border-bottom:1px solid #eee">
            <td style="font-family:monospace;font-size:12px"><?php echo htmlspecialchars($log['name']); ?></td>
            <td><?php echo date('Y-m-d H:i:s', $log['mtime']); ?></td>
            <td align="right"><?php echo number_format($log['size'] / 1024, 1) . ' KB'; ?></td>
            <td align="center">
              <?php if ($log['finished']): ?>
                <span style="color:#3c763d">finalizado</span>
              <?php else: ?>
                <span style="color:#a94442">en curso / interrumpido</span>
              <?php endif; ?>
            </td>
            <td><a href="actuprice_minderest_run.php?action=view&amp;log=<?php echo urlencode($log['name']); ?>">Ver</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($logs)): ?>
          <tr><td colspan="5" align="center" style="padding:1em;color:#999">No hay logs todavia.</td></tr>
        <?php endif; ?>
      </table>
    </td>
  </tr>
</table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
