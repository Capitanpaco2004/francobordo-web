<?php
/**
 * #FB-VIES  Validacion VIES de Profesionales — _admin/vies.php
 *
 * Lista los clientes del grupo 1 (Profesionales), muestra/edita su VAT intracomunitario
 * (customers.entry_company_tax_id) y lo valida contra VIES. El resultado se guarda en
 * fb_vies_status / fb_vies_log (clase fb_vies). Reverse charge (0% IVA) se concede
 * automaticamente en checkout a los que quedan 'valid' + entrega UE!=ES (ver tep_get_tax_rate).
 *
 * Menu: Clientes > Validacion VIES (box customers.php, padre 5).
 */

require 'includes/application_top.php';

if (!class_exists('fb_vies')) {
    $catRoot = defined('DIR_FS_CATALOG') ? DIR_FS_CATALOG : (dirname(__DIR__) . '/');
    @require_once $catRoot . 'includes/classes/fb_vies.php';
}
fb_vies::ensureTables();

$msg = '';

/* ---------------- Acciones ---------------- */
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'savevat' && isset($_POST['cID'])) {
    $cID = (int) $_POST['cID'];
    $vat = trim((string) ($_POST['vat'] ?? ''));
    tep_db_query("update customers set entry_company_tax_id = '" . tep_db_input($vat) . "' where customers_id = '" . $cID . "'");
    $r = fb_vies::validateCustomer($cID, 'admin');
    tep_redirect(tep_href_link('vies.php', 'msg=' . rawurlencode('Cliente ' . $cID . ': VAT guardado y validado -> ' . ($r['applied_status'] ?? $r['status'] ?? '?'))));
}

if ($action === 'check' && isset($_GET['cID'])) {
    $cID = (int) $_GET['cID'];
    $r = fb_vies::validateCustomer($cID, 'admin');
    $st = $r['applied_status'] ?? ($r['status'] ?? '?');
    $extra = ($r['status'] === 'error') ? ' (VIES no disponible: ' . htmlspecialchars((string) $r['error']) . ' - se conserva el ultimo estado)' : '';
    tep_redirect(tep_href_link('vies.php', 'msg=' . rawurlencode('Cliente ' . $cID . ': ' . $st . $extra)));
}

if ($action === 'checkall') {
    $due = tep_db_query("select c.customers_id
                           from customers c
                           left join " . fb_vies::T_STATUS . " s on s.customers_id = c.customers_id
                          where c.customers_group_id in (0, 1)
                            and trim(coalesce(c.entry_company_tax_id, '')) <> ''
                            and (s.customers_id is null or s.status in ('unchecked','error') or s.next_recheck < now())
                          limit 25");
    $n = 0; $ok = 0;
    while ($d = tep_db_fetch_array($due)) {
        $r = fb_vies::validateCustomer((int) $d['customers_id'], 'admin-bulk');
        $n++;
        if (($r['applied_status'] ?? $r['status'] ?? '') === 'valid') $ok++;
    }
    tep_redirect(tep_href_link('vies.php', 'msg=' . rawurlencode("Validados $n pendientes ($ok validos). Max 25/tanda; el cron re-valida el resto.")));
}

if (isset($_GET['msg'])) $msg = (string) $_GET['msg'];

/* ---------------- Datos: profesionales (grupo 1) ---------------- */
$sql = "select c.customers_id, c.customers_firstname, c.customers_lastname, c.entry_company_tax_id,
               ab.entry_company, ab.entry_NIF, co.countries_iso_code_2 as iso, co.countries_name as country,
               s.status, s.valid, s.country_code, s.trader_name, s.request_identifier, s.last_checked, s.last_success, s.next_recheck, s.last_error
          from customers c
          left join address_book ab on ab.address_book_id = c.customers_default_address_id
          left join countries co on co.countries_id = ab.entry_country_id
          left join " . fb_vies::T_STATUS . " s on s.customers_id = c.customers_id
         where (c.customers_group_id = 1
                or (c.customers_group_id = 0 and (trim(coalesce(c.entry_company_tax_id, '')) <> '' or trim(coalesce(ab.entry_company, '')) <> '')))
         order by (s.valid = 1) desc, s.status is null desc, c.customers_lastname asc
         limit 500";
$rows = array();
$rs = tep_db_query($sql);
while ($r = tep_db_fetch_array($rs)) $rows[] = $r;

$countValid = 0; $countPend = 0;
foreach ($rows as $r) {
    if (($r['status'] ?? '') === 'valid') $countValid++;
    elseif (empty($r['status']) || $r['status'] === 'unchecked' || $r['status'] === 'error') $countPend++;
}

/* ---------------- Log de un cliente (panel lateral) ---------------- */
$logRows = array(); $logCID = 0;
if ($action === 'log' && isset($_GET['cID'])) {
    $logCID = (int) $_GET['cID'];
    $lq = tep_db_query("select status, valid, country_code, vat_number, source, http_status, error_message, trader_name, checked_at
                          from " . fb_vies::T_LOG . " where customers_id = '" . $logCID . "' order by id desc limit 30");
    while ($l = tep_db_fetch_array($lq)) $logRows[] = $l;
}

function fb_vies_badge($status, $valid)
{
    $status = (string) $status;
    if ($status === 'valid') return '<span class="vb vb-ok">VALIDO</span>';
    if ($status === 'invalid') return '<span class="vb vb-no">INVALIDO</span>';
    if ($status === 'error') return '<span class="vb vb-err">ERROR VIES</span>';
    return '<span class="vb vb-un">sin comprobar</span>';
}
?>
<?php require THEME . 'html/header.php'; ?>

<style>
.viesp { padding: 0 1.5em 2em; }
.viesp h1 { margin-bottom: 0.2em; }
.viesp .crit { color: #666; margin: 0 0 1em; }
.viesp .toolbar { margin: 0 0 1.2em; }
.viesp .btn { background:#3598DB;color:#fff;padding:8px 14px;border-radius:3px;text-decoration:none;border:0;cursor:pointer;font-size:12px; }
.viesp .btn:hover { background:#2980b9; }
.viesp .msg { background:#eafaf1;border:1px solid #2ecc71;color:#1e7e45;padding:10px 12px;border-radius:3px;margin:0 0 1em; }
.viesp table { border-collapse: collapse; width: 100%; background:#fff; }
.viesp th, .viesp td { border:1px solid #ddd; padding:6px 8px; text-align:left; font-size:12px; vertical-align:middle; }
.viesp th { background:#3598DB; color:#fff; }
.viesp tr:hover td { background:#f3f8fc; }
.viesp .vb { padding:2px 7px; border-radius:10px; color:#fff; font-size:11px; font-weight:bold; white-space:nowrap; }
.viesp .vb-ok { background:#27ae60; } .viesp .vb-no { background:#c0392b; }
.viesp .vb-err{ background:#e67e22; } .viesp .vb-un { background:#95a5a6; }
.viesp input.vat { width:150px; font-family:monospace; padding:3px; }
.viesp .mini { font-size:11px; color:#777; }
.viesp .rc { color:#27ae60; font-weight:bold; }
.viesp .logbox { background:#fff; border:1px solid #ddd; padding:1em; margin:0 0 1.5em; }
</style>

<div class="viesp">
  <h1>Validaci&oacute;n VIES (empresas)</h1>
  <p class="crit"><strong>Profesionales (G1)</strong> y <strong>retail con empresa (G0)</strong>. Un VAT <strong>v&aacute;lido</strong> en VIES de otro pa&iacute;s UE (no ES/UK) concede autom&aacute;ticamente la <strong>inversi&oacute;n del sujeto pasivo (IVA 0%)</strong> en entregas intracomunitarias (UE excepto Espa&ntilde;a y Reino Unido). Si VIES no responde se conserva el &uacute;ltimo estado v&aacute;lido conocido.</p>

  <?php if ($msg !== '') { ?><div class="msg"><?php echo htmlspecialchars($msg); ?></div><?php } ?>

  <div class="toolbar">
    <a class="btn" href="<?php echo tep_href_link('vies.php', 'action=checkall'); ?>">&#128260; Validar pendientes (m&aacute;x 25)</a>
    &nbsp;&nbsp; <strong><?php echo count($rows); ?></strong> profesionales &middot;
    <span class="rc"><?php echo $countValid; ?> v&aacute;lidos</span> &middot;
    <strong><?php echo $countPend; ?></strong> por comprobar
  </div>

  <?php if ($action === 'log' && $logCID) { ?>
  <div class="logbox">
    <strong>Historial VIES del cliente #<?php echo $logCID; ?></strong> &middot; <a href="<?php echo tep_href_link('vies.php'); ?>">cerrar</a>
    <table style="margin-top:.6em">
      <tr><th>Fecha</th><th>Estado</th><th>Pais</th><th>VAT</th><th>Origen</th><th>HTTP</th><th>Error</th><th>Empresa</th></tr>
      <?php foreach ($logRows as $l) { ?>
      <tr>
        <td><?php echo htmlspecialchars((string) $l['checked_at']); ?></td>
        <td><?php echo fb_vies_badge($l['status'], $l['valid']); ?></td>
        <td><?php echo htmlspecialchars((string) $l['country_code']); ?></td>
        <td><?php echo htmlspecialchars((string) $l['vat_number']); ?></td>
        <td><?php echo htmlspecialchars((string) $l['source']); ?></td>
        <td><?php echo (int) $l['http_status']; ?></td>
        <td><?php echo htmlspecialchars((string) $l['error_message']); ?></td>
        <td><?php echo htmlspecialchars((string) $l['trader_name']); ?></td>
      </tr>
      <?php } if (!count($logRows)) echo '<tr><td colspan="8">Sin registros.</td></tr>'; ?>
    </table>
  </div>
  <?php } ?>

  <table>
    <tr>
      <th>Cliente</th><th>Empresa</th><th>Pa&iacute;s</th><th>VAT (editable)</th><th>Estado VIES</th>
      <th>Reverse charge</th><th>Raz&oacute;n social VIES</th><th>&Uacute;lt. comprob.</th><th>Acciones</th>
    </tr>
    <?php foreach ($rows as $r) {
        $cID = (int) $r['customers_id'];
        $iso = strtoupper((string) $r['iso']);
        $vat = trim((string) $r['entry_company_tax_id']);
        $vatcc = strtoupper((string) ($r['country_code'] ?? ''));
        $rc  = (($r['status'] ?? '') === 'valid' && !in_array($vatcc, array('ES', 'GB', 'XI', ''), true)) ? '<span class="rc">S&iacute; (UE)</span>'
             : ((($r['status'] ?? '') === 'valid' && in_array($vatcc, array('ES', 'GB', 'XI'), true)) ? '<span class="mini">No (nacional/UK 21%)</span>' : '&mdash;');
    ?>
    <tr>
      <td><a href="<?php echo tep_href_link(FILENAME_CUSTOMERS, 'cID=' . $cID . '&action=edit'); ?>" target="_blank">
          <?php echo htmlspecialchars(trim($r['customers_firstname'] . ' ' . $r['customers_lastname'])); ?></a>
          <div class="mini">#<?php echo $cID; ?></div></td>
      <td><?php echo htmlspecialchars((string) $r['entry_company']); ?></td>
      <td><?php echo htmlspecialchars($iso . ($r['country'] ? ' - ' . $r['country'] : '')); ?></td>
      <td>
        <form method="post" action="<?php echo tep_href_link('vies.php', 'action=savevat'); ?>" style="margin:0">
          <input type="hidden" name="cID" value="<?php echo $cID; ?>">
          <input class="vat" type="text" name="vat" value="<?php echo htmlspecialchars($vat); ?>" placeholder="<?php echo htmlspecialchars((string) $r['entry_NIF']); ?>">
          <button class="btn" type="submit" style="padding:3px 8px">Guardar+Validar</button>
        </form>
        <?php if ($vat === '' && trim((string) $r['entry_NIF']) !== '') echo '<div class="mini">NIF direcci&oacute;n: ' . htmlspecialchars((string) $r['entry_NIF']) . '</div>'; ?>
      </td>
      <td><?php echo fb_vies_badge($r['status'] ?? '', $r['valid'] ?? 0); ?>
          <?php if (($r['status'] ?? '') === 'error' && !empty($r['last_error'])) echo '<div class="mini">' . htmlspecialchars((string) $r['last_error']) . '</div>'; ?></td>
      <td><?php echo $rc; ?></td>
      <td><?php echo htmlspecialchars((string) $r['trader_name']); ?>
          <?php if (!empty($r['request_identifier'])) echo '<div class="mini">consulta: ' . htmlspecialchars((string) $r['request_identifier']) . '</div>'; ?></td>
      <td class="mini"><?php echo $r['last_checked'] ? htmlspecialchars((string) $r['last_checked']) : '&mdash;'; ?>
          <?php if ($r['next_recheck']) echo '<br>re-check: ' . htmlspecialchars((string) $r['next_recheck']); ?></td>
      <td style="white-space:nowrap">
        <a class="btn" style="padding:3px 8px" href="<?php echo tep_href_link('vies.php', 'action=check&cID=' . $cID); ?>">Validar</a>
        <a href="<?php echo tep_href_link('vies.php', 'action=log&cID=' . $cID); ?>" class="mini">log</a>
      </td>
    </tr>
    <?php } if (!count($rows)) echo '<tr><td colspan="9">No hay clientes en el grupo Profesionales.</td></tr>'; ?>
  </table>
</div>

<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
