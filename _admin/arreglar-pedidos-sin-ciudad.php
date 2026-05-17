<?php
/**
 * Arreglar pedidos sin ciudad
 *
 * Lista todos los pedidos 2024+ con customers_city vacío.
 * Por cada uno, ofrece dropdown de cities matcheadas por CP (priorizando coincidencia
 * de provincia) + campo libre. Al asignar, actualiza customers_city / delivery_city /
 * billing_city del pedido (solo las que estén vacías) y opcionalmente el address_book
 * del cliente para evitar futuras repeticiones.
 *
 * Complementa el trigger BD address_book_city_guard_* y el patch en checkout_select_zone.php.
 */

require 'includes/application_top.php';

// ====== POST handler =====================================================
$mensaje = null;
$mensaje_tipo = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $orders_id    = (int)($_POST['orders_id'] ?? 0);
    $city_id      = (int)($_POST['city_id'] ?? 0);
    $city_libre   = trim((string)($_POST['city_libre'] ?? ''));
    $sync_addr    = !empty($_POST['sync_address_book']);

    $city_name = '';
    if ($city_id > 0) {
        $r = tep_db_query('SELECT name FROM cities WHERE id = ' . $city_id . ' LIMIT 1');
        if ($row = tep_db_fetch_array($r)) $city_name = (string)$row['name'];
    } elseif ($city_libre !== '') {
        $city_name = $city_libre;
    }

    if ($orders_id <= 0 || $city_name === '') {
        $mensaje = 'Faltan datos: selecciona una ciudad del dropdown o escribe una manualmente.';
        $mensaje_tipo = 'error';
    } else {
        // Update solo en celdas vacías para no pisar puntos de recogida ya rellenos
        $q = "UPDATE orders SET
                customers_city = IF(customers_city='' OR customers_city IS NULL, '" . tep_db_input($city_name) . "', customers_city),
                billing_city   = IF(billing_city='' OR billing_city IS NULL,     '" . tep_db_input($city_name) . "', billing_city),
                delivery_city  = IF(delivery_city='' OR delivery_city IS NULL,   '" . tep_db_input($city_name) . "', delivery_city)
              WHERE orders_id = " . $orders_id;
        tep_db_query($q);

        $extra = '';
        if ($sync_addr) {
            // Resuelve el address_book default del cliente y actualiza si está vacío
            $cust = tep_db_query("SELECT cu.customers_default_address_id, ab.entry_city, ab.entry_city_id
                                  FROM orders o
                                  JOIN customers cu ON cu.customers_id = o.customers_id
                                  LEFT JOIN address_book ab ON ab.address_book_id = cu.customers_default_address_id
                                  WHERE o.orders_id = " . $orders_id);
            if ($crow = tep_db_fetch_array($cust)) {
                $abid = (int)$crow['customers_default_address_id'];
                if ($abid > 0 && ($crow['entry_city'] === '' || $crow['entry_city'] === null)) {
                    $upd = "UPDATE address_book SET entry_city = '" . tep_db_input($city_name) . "'";
                    if ($city_id > 0 && (int)$crow['entry_city_id'] === 0) {
                        $upd .= ", entry_city_id = " . $city_id;
                    }
                    $upd .= " WHERE address_book_id = " . $abid;
                    tep_db_query($upd);
                    $extra = ' Y también el address_book #' . $abid . ' del cliente.';
                }
            }
        }

        $mensaje = 'Pedido #' . $orders_id . ' actualizado a "' . htmlspecialchars($city_name, ENT_QUOTES, 'UTF-8') . '".' . $extra;
        $mensaje_tipo = 'ok';
    }
}

// ====== Datos =============================================================
$q = tep_db_query("
    SELECT o.orders_id, o.date_purchased, o.customers_name, o.customers_email_address,
           o.customers_street_address, o.customers_postcode, o.customers_state,
           o.customers_country, o.delivery_city, o.billing_city
    FROM orders o
    WHERE o.date_purchased >= '2024-01-01'
      AND (o.customers_city = '' OR o.customers_city IS NULL)
    ORDER BY o.date_purchased DESC
");
$pendientes = [];
while ($row = tep_db_fetch_array($q)) $pendientes[] = $row;
$total_pendientes = count($pendientes);

// Precarga opciones de cities para cada CP encontrado, agrupadas por zona
$cps = array_values(array_unique(array_filter(array_map(static function ($r) {
    return preg_match('/^\d{5}$/', $r['customers_postcode'] ?? '') ? $r['customers_postcode'] : null;
}, $pendientes))));

$opciones_por_cp = [];
if (!empty($cps)) {
    $in = implode(',', array_map(static function ($cp) { return "'" . tep_db_input($cp) . "'"; }, $cps));
    $r = tep_db_query("
        SELECT c.cp, c.id, c.name, c.id_zone, z.zone_name
        FROM cities c
        LEFT JOIN zones z ON z.zone_id = c.id_zone
        WHERE c.cp IN ($in)
        ORDER BY c.cp, z.zone_name, c.name
    ");
    while ($row = tep_db_fetch_array($r)) {
        $opciones_por_cp[$row['cp']][] = $row;
    }
}

?>
<?php require THEME . 'html/header.php'; ?>

<style>
.fixcity { font-family: system-ui, sans-serif; max-width: 1400px; margin: 0 auto; padding: 1em; }
.fixcity h1 { margin-top: 0; }
.fixcity .alert { padding: 10px 14px; border-radius: 4px; margin: 10px 0; font-size: 14px; }
.fixcity .alert-ok    { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.fixcity .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.fixcity .summary { font-size: 14px; margin: 1em 0; color: #555; }
.fixcity table { border-collapse: collapse; width: 100%; margin-top: 1em; }
.fixcity th, .fixcity td { border: 1px solid #ddd; padding: 8px 10px; text-align: left;
                           font-size: 13px; vertical-align: top; }
.fixcity th { background: #f0f0f0; font-weight: 600; }
.fixcity .num { text-align: right; font-family: monospace; }
.fixcity select, .fixcity input[type=text] { width: 100%; padding: 4px; box-sizing: border-box;
                                              font-size: 13px; }
.fixcity .btn { padding: 6px 12px; background: #3273dc; color: #fff; border: 0; border-radius: 4px;
                cursor: pointer; font-size: 13px; }
.fixcity .btn:hover { background: #2160b3; }
.fixcity .muted { color: #888; font-size: 12px; }
.fixcity .cp-flag { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 11px;
                    margin-left: 6px; }
.fixcity .cp-flag.ok    { background: #d4edda; color: #155724; }
.fixcity .cp-flag.noopt { background: #f8d7da; color: #721c24; }
.fixcity .cp-flag.amb   { background: #fff3cd; color: #856404; }
.fixcity .delivery-info { font-size: 11px; color: #666; margin-top: 4px; }
.fixcity .delivery-info b { color: #333; }
.fixcity .checkbox-row { font-size: 11px; margin-top: 4px; }
</style>

<div class="fixcity">
  <h1>Arreglar pedidos sin ciudad</h1>

  <p class="summary">
    Lista de pedidos 2024+ con <code>customers_city</code> vacío. Asigna manualmente la ciudad por dropdown
    (filtrado por CP) o escribiéndola a mano. Solo actualiza las columnas vacías &mdash; no pisa puntos de
    recogida ya rellenos en <code>delivery_city</code>.
  </p>

<?php if ($mensaje): ?>
  <div class="alert alert-<?php echo $mensaje_tipo; ?>"><?php echo $mensaje; ?></div>
<?php endif; ?>

<?php if ($total_pendientes === 0): ?>
  <div class="alert alert-ok"><strong>No hay pedidos pendientes.</strong> Todos los pedidos 2024+ tienen ciudad asignada.</div>
<?php else: ?>

  <p class="summary"><strong><?php echo $total_pendientes; ?></strong> pedido(s) pendiente(s).</p>

  <table>
    <thead>
      <tr>
        <th>Pedido</th>
        <th>Fecha</th>
        <th>Cliente</th>
        <th>Dirección</th>
        <th>CP / Provincia / País</th>
        <th>Asignar ciudad</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($pendientes as $p):
      $cp = $p['customers_postcode'];
      $opts = $opciones_por_cp[$cp] ?? [];
      // Match heurístico de zone por nombre de provincia (sin acentos, case-insensitive)
      $prov = trim((string)$p['customers_state']);
      $prov_norm = mb_strtolower(strtr($prov, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u']));
      $opts_filtrados = [];
      $opts_resto = [];
      foreach ($opts as $o) {
        $zn = mb_strtolower(strtr((string)$o['zone_name'], ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u']));
        if ($prov_norm !== '' && $zn === $prov_norm) $opts_filtrados[] = $o;
        else $opts_resto[] = $o;
      }
      $cuantas = count($opts);
      $flag_class = $cuantas === 0 ? 'noopt' : ($cuantas === 1 ? 'ok' : 'amb');
      $flag_text  = $cuantas === 0 ? 'sin opciones cities' : ($cuantas === 1 ? '1 opción' : ($cuantas . ' opciones'));
    ?>
      <tr>
        <td><a href="<?php echo tep_href_link(FILENAME_ORDERS, 'oID=' . $p['orders_id'] . '&action=edit'); ?>" target="_blank">#<?php echo $p['orders_id']; ?></a></td>
        <td><?php echo htmlspecialchars(substr($p['date_purchased'], 0, 16), ENT_QUOTES, 'UTF-8'); ?></td>
        <td>
          <?php echo htmlspecialchars($p['customers_name'], ENT_QUOTES, 'UTF-8'); ?>
          <div class="muted"><?php echo htmlspecialchars($p['customers_email_address'], ENT_QUOTES, 'UTF-8'); ?></div>
        </td>
        <td><?php echo htmlspecialchars($p['customers_street_address'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td>
          <code><?php echo htmlspecialchars($cp, ENT_QUOTES, 'UTF-8'); ?></code>
          <span class="cp-flag <?php echo $flag_class; ?>"><?php echo $flag_text; ?></span>
          <div class="muted">
            <?php echo htmlspecialchars($prov ?: '(sin provincia)', ENT_QUOTES, 'UTF-8'); ?>
            &middot;
            <?php echo htmlspecialchars($p['customers_country'], ENT_QUOTES, 'UTF-8'); ?>
          </div>
          <?php if (!empty($p['delivery_city']) || !empty($p['billing_city'])): ?>
            <div class="delivery-info">
              <?php if (!empty($p['delivery_city'])): ?><b>Envío:</b> <?php echo htmlspecialchars($p['delivery_city'], ENT_QUOTES, 'UTF-8'); ?><br><?php endif; ?>
              <?php if (!empty($p['billing_city'])): ?><b>Factura:</b> <?php echo htmlspecialchars($p['billing_city'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
            </div>
          <?php endif; ?>
        </td>
        <td>
          <form method="post">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="orders_id" value="<?php echo $p['orders_id']; ?>">
            <?php if ($cuantas > 0): ?>
              <select name="city_id">
                <option value="0">&mdash; elige &mdash;</option>
                <?php if (!empty($opts_filtrados)): ?>
                  <optgroup label="Misma provincia (<?php echo htmlspecialchars($prov, ENT_QUOTES, 'UTF-8'); ?>)">
                    <?php foreach ($opts_filtrados as $o): ?>
                      <option value="<?php echo (int)$o['id']; ?>"><?php echo htmlspecialchars($o['name'] . ' — ' . $o['zone_name'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endif; ?>
                <?php if (!empty($opts_resto)): ?>
                  <optgroup label="Otras provincias">
                    <?php foreach ($opts_resto as $o): ?>
                      <option value="<?php echo (int)$o['id']; ?>"><?php echo htmlspecialchars($o['name'] . ' — ' . ($o['zone_name'] ?: '(sin zona)'), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endif; ?>
              </select>
            <?php endif; ?>
            <input type="text" name="city_libre" placeholder="<?php echo $cuantas === 0 ? 'Escribe la ciudad' : 'O ciudad libre'; ?>" style="margin-top:4px;">
            <label class="checkbox-row">
              <input type="checkbox" name="sync_address_book" checked>
              También arreglar libreta del cliente si está vacía
            </label>
          </form>
        </td>
        <td>
          <button type="submit" form="" class="btn" onclick="this.closest('tr').querySelector('form').submit();">Aplicar</button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

</div>

<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
