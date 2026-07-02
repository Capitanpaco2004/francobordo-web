<?php
  require('includes/application_top.php');
?>
<?php include( THEME . '/html/header.php' ); ?>

<?php
  // ===== Contador de invitaciones a resenas (Google fallback + Trustpilot) =====
  $tp_cap = defined('TRUSTPILOT_MONTHLY_CAP') ? (int) TRUSTPILOT_MONTHLY_CAP : 300;

  $_ri = function ($sql) {
      $r = tep_db_query($sql);
      $x = tep_db_fetch_array($r);
      return (int) $x['c'];
  };

  $g_total  = $_ri("SELECT COUNT(*) c FROM google_review_invites");
  $g_mes    = $_ri("SELECT COUNT(*) c FROM google_review_invites WHERE sent_at >= DATE_FORMAT(NOW(),'%Y-%m-01')");
  $tp_total = $_ri("SELECT COUNT(*) c FROM trustpilot_invites");
  $tp_mes   = $_ri("SELECT COUNT(*) c FROM trustpilot_invites WHERE sent_at >= DATE_FORMAT(NOW(),'%Y-%m-01')");

  $card = 'border-radius:8px;padding:16px;text-align:center;';
  $cg   = 'background:#eef7f0;border:1px solid #cfe6d6;';
  $ct   = 'background:#eaf3fb;border:1px solid #cadff2;';
  $lbl  = 'font-size:12px;color:#5a6b60;text-transform:uppercase;letter-spacing:.4px;';
  $numG = 'font-size:42px;font-weight:bold;color:#2e7d32;line-height:1.05;margin-top:6px;';
  $numT = 'font-size:42px;font-weight:bold;color:#00699a;line-height:1.05;margin-top:6px;';
?>

<div class="toolbarHead">
  <div class="hdr-tlbr">
    <h1 class="pageHeading ftitl">Invitaciones a rese&ntilde;as</h1>
  </div>
</div>

<table border="0" width="100%" cellspacing="0" cellpadding="7"><tr valign="top">
  <td width="25%"><div style="<?php echo $card . $cg; ?>">
    <div style="<?php echo $lbl; ?>">Google &middot; este mes</div>
    <div style="<?php echo $numG; ?>"><?php echo $g_mes; ?></div>
  </div></td>
  <td width="25%"><div style="<?php echo $card . $cg; ?>">
    <div style="<?php echo $lbl; ?>">Google &middot; total</div>
    <div style="<?php echo $numG; ?>"><?php echo $g_total; ?></div>
  </div></td>
  <td width="25%"><div style="<?php echo $card . $ct; ?>">
    <div style="<?php echo $lbl; ?>">Trustpilot &middot; este mes</div>
    <div style="<?php echo $numT; ?>"><?php echo $tp_mes; ?><span style="font-size:18px;color:#7a8a96;font-weight:normal;"> / <?php echo $tp_cap; ?></span></div>
  </div></td>
  <td width="25%"><div style="<?php echo $card . $ct; ?>">
    <div style="<?php echo $lbl; ?>">Trustpilot &middot; total</div>
    <div style="<?php echo $numT; ?>"><?php echo $tp_total; ?></div>
  </div></td>
</tr></table>

<p class="smallText" style="margin:12px 4px 20px;color:#777;line-height:18px;">
  El <b>fallback de Google</b> se a&ntilde;ade autom&aacute;ticamente al email de env&iacute;o del cliente (pedidos enviados &le;24h) <b>cuando se agota el cupo mensual de Trustpilot</b> (<?php echo $tp_cap; ?>/mes). As&iacute; ning&uacute;n cliente con env&iacute;o r&aacute;pido se queda sin que le pidamos opini&oacute;n. Los contadores &laquo;este mes&raquo; van por mes natural (rue­dan el d&iacute;a 1).
</p>

<h2 class="pageHeading" style="font-size:15px;">Google &middot; por d&iacute;a <span style="font-weight:normal;color:#999;font-size:12px;">(&uacute;ltimos 14 con env&iacute;os)</span></h2>
<table border="0" width="340" cellspacing="0" cellpadding="3">
  <tr class="dataTableHeadingRow">
    <td class="dataTableHeadingContent">D&iacute;a</td>
    <td class="dataTableHeadingContent" align="right">Enviados</td>
  </tr>
<?php
  $r = tep_db_query("SELECT DATE(sent_at) d, COUNT(*) c FROM google_review_invites GROUP BY DATE(sent_at) ORDER BY d DESC LIMIT 14");
  if (tep_db_num_rows($r) == 0) {
      echo '<tr class="dataTableRow"><td class="dataTableContent" colspan="2">Sin env&iacute;os todav&iacute;a.</td></tr>';
  }
  while ($row = tep_db_fetch_array($r)) {
      echo '<tr class="dataTableRow"><td class="dataTableContent">' . $row['d'] . '</td><td class="dataTableContent" align="right">' . (int) $row['c'] . '</td></tr>';
  }
?>
</table>

<h2 class="pageHeading" style="font-size:15px;margin-top:24px;">&Uacute;ltimas invitaciones de Google</h2>
<table border="0" width="100%" cellspacing="0" cellpadding="3">
  <tr class="dataTableHeadingRow">
    <td class="dataTableHeadingContent">Pedido</td>
    <td class="dataTableHeadingContent">Email del cliente</td>
    <td class="dataTableHeadingContent">Enviado</td>
  </tr>
<?php
  $r = tep_db_query("SELECT orders_id, customers_email_address, sent_at FROM google_review_invites ORDER BY sent_at DESC, id DESC LIMIT 30");
  if (tep_db_num_rows($r) == 0) {
      echo '<tr class="dataTableRow"><td class="dataTableContent" colspan="3">Sin invitaciones todav&iacute;a.</td></tr>';
  }
  while ($row = tep_db_fetch_array($r)) {
      $oid   = (int) $row['orders_id'];
      $olink = defined('FILENAME_ORDERS') ? tep_href_link(FILENAME_ORDERS, 'oID=' . $oid . '&action=edit') : '';
      $ocell = $olink ? ('<a href="' . $olink . '" class="blacklink">' . $oid . '</a>') : $oid;
      echo '<tr class="dataTableRow">'
         . '<td class="dataTableContent">' . $ocell . '</td>'
         . '<td class="dataTableContent">' . htmlspecialchars($row['customers_email_address']) . '</td>'
         . '<td class="dataTableContent">' . $row['sent_at'] . '</td>'
         . '</tr>';
  }
?>
</table>

<br>

<?php include( THEME . '/html/footer.php' ); ?>

<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
