<?php
/**
 * Profesionales Pendientes — _admin/profesionales_pendientes.php
 *
 * Listado de pedidos de clientes del grupo 1 (Profesionales) en estado
 * "Proceso" (orders_status = 2) con antigüedad entre 1 día y 1 mes,
 * ordenados del más antiguo al más reciente, con descarga CSV (separador
 * ';' + BOM UTF-8 para que Excel es-ES lo abra directo).
 *
 * Menú: Pedidos > Profesionales Pendientes (box orders.php, padre 294).
 */

require 'includes/application_top.php';

$sql = "SELECT o.orders_id, o.date_purchased,
               TIMESTAMPDIFF(DAY, o.date_purchased, NOW()) AS dias,
               o.customers_id, o.customers_name, o.customers_company,
               o.customers_email_address, o.customers_telephone,
               o.payment_method, o.shipping_module,
               ot.value AS total
        FROM orders o
        JOIN customers c ON c.customers_id = o.customers_id
        LEFT JOIN orders_total ot ON ot.orders_id = o.orders_id AND ot.class = 'ot_total'
        WHERE c.customers_group_id = 1
          AND o.orders_status = 2
          AND o.date_purchased < NOW() - INTERVAL 1 DAY
          AND o.date_purchased >= NOW() - INTERVAL 1 MONTH
        ORDER BY o.date_purchased ASC";

$rows = array();
$rs = tep_db_query($sql);
while ($r = tep_db_fetch_array($rs)) {
    $rows[] = $r;
}

/* ---- Descarga CSV (Excel es-ES: ';' + BOM UTF-8) ---- */
if (isset($_GET['csv'])) {
    $fileName = 'profesionales_pendientes_' . date('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $csvField = function ($v) {
        $v = (string)$v;
        return (strpbrk($v, ";\"\n\r") !== false) ? '"' . str_replace('"', '""', $v) . '"' : $v;
    };

    echo "\xEF\xBB\xBF";
    $cols = array('Pedido', 'Fecha', 'Dias', 'Cliente ID', 'Cliente', 'Empresa', 'Email', 'Telefono', 'Total EUR', 'Pago', 'Envio');
    echo implode(';', array_map($csvField, $cols)) . "\r\n";
    foreach ($rows as $r) {
        $linea = array(
            $r['orders_id'],
            !empty($r['date_purchased']) ? date('d/m/Y H:i', strtotime($r['date_purchased'])) : '',
            $r['dias'],
            $r['customers_id'],
            $r['customers_name'],
            (string)$r['customers_company'],
            $r['customers_email_address'],
            (string)$r['customers_telephone'],
            ($r['total'] !== null) ? number_format((float)$r['total'], 2, ',', '') : '',
            $r['payment_method'],
            (string)$r['shipping_module'],
        );
        echo implode(';', array_map($csvField, $linea)) . "\r\n";
    }
    exit;
}

$totalSum = 0.0;
foreach ($rows as $r) {
    $totalSum += (float)$r['total'];
}
?>
<?php require THEME . 'html/header.php'; ?>

<style>
.profpend { padding: 0 1.5em 2em; }
.profpend h1 { margin-bottom: 0.2em; }
.profpend .crit { color: #666; margin: 0 0 1em; }
.profpend .toolbar { margin: 0 0 1.2em; }
.profpend .toolbar a.btn-csv { background: #3598DB; color: #fff; padding: 8px 14px; border-radius: 3px; text-decoration: none; }
.profpend .toolbar a.btn-csv:hover { background: #2980b9; }
.profpend table { border-collapse: collapse; width: 100%; background: #fff; }
.profpend th, .profpend td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; font-size: 12px; }
.profpend th { background: #3598DB; color: #fff; }
.profpend tr:hover td { background: #f3f8fc; }
.profpend td.num { text-align: right; white-space: nowrap; }
.profpend .vacio { padding: 2em; background: #fff; border: 1px solid #ddd; }
</style>

<div class="profpend">
  <h1>Profesionales Pendientes</h1>
  <p class="crit">Pedidos de clientes del grupo <strong>Profesionales</strong> en estado <strong>Proceso</strong> con antig&uuml;edad entre 1 d&iacute;a y 1 mes &middot; m&aacute;s antiguos primero.</p>
  <div class="toolbar">
    <a class="btn-csv" href="<?php echo tep_href_link('profesionales_pendientes.php', 'csv=1'); ?>">&#11015; Descargar CSV</a>
    &nbsp;&nbsp; <strong><?php echo count($rows); ?></strong> pedidos &middot; <strong><?php echo number_format($totalSum, 2, ',', '.'); ?> &euro;</strong> en total
  </div>
<?php if (count($rows) === 0) { ?>
  <div class="vacio">No hay pedidos de Profesionales en estado Proceso entre 1 d&iacute;a y 1 mes de antig&uuml;edad. &#127881;</div>
<?php } else { ?>
  <table>
    <tr>
      <th>Pedido</th><th>Fecha</th><th>D&iacute;as</th><th>Cliente</th><th>Empresa</th><th>Email</th><th>Tel&eacute;fono</th><th>Total</th><th>Pago</th><th>Env&iacute;o</th>
    </tr>
    <?php foreach ($rows as $r) { ?>
    <tr>
      <td><a href="<?php echo tep_href_link(FILENAME_ORDERS, 'oID=' . (int)$r['orders_id'] . '&action=edit'); ?>" target="_blank">#<?php echo (int)$r['orders_id']; ?></a></td>
      <td><?php echo !empty($r['date_purchased']) ? date('d/m/Y H:i', strtotime($r['date_purchased'])) : '&mdash;'; ?></td>
      <td class="num"><?php echo (int)$r['dias']; ?></td>
      <td><a href="<?php echo tep_href_link(FILENAME_CUSTOMERS, 'cID=' . (int)$r['customers_id'] . '&action=edit'); ?>" target="_blank"><?php echo htmlspecialchars($r['customers_name']); ?></a></td>
      <td><?php echo htmlspecialchars((string)$r['customers_company']); ?></td>
      <td><?php echo htmlspecialchars($r['customers_email_address']); ?></td>
      <td><?php echo htmlspecialchars((string)$r['customers_telephone']); ?></td>
      <td class="num"><?php echo ($r['total'] !== null) ? number_format((float)$r['total'], 2, ',', '.') . ' &euro;' : '&mdash;'; ?></td>
      <td><?php echo htmlspecialchars($r['payment_method']); ?></td>
      <td><?php echo htmlspecialchars((string)$r['shipping_module']); ?></td>
    </tr>
    <?php } ?>
  </table>
<?php } ?>
</div>

<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
