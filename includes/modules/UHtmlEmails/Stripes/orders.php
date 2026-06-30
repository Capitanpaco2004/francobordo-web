<?php

include_once(DIR_FS_CATALOG . DIR_WS_LANGUAGES . $language . '/modules/UHtmlEmails/Standard/orders.php');
include_once(DIR_FS_CATALOG . DIR_WS_LANGUAGES . $language . '.php');

$url = HTTP_SERVER . DIR_WS_CATALOG;
$ArrayLNTargets = array( "\r\n", "\n\r", "\n", "\r", "\t" );
$logo = $url . 'theme/web/images/custom/15.png';

// Etiqueta y color del badge segun el estado del pedido
$status_label = isset($orders_status_array[$status]) ? $orders_status_array[$status] : '';
$badge_bg = '#00aff0';                                  // info (por defecto)
if ((int) $status == 5 || (int) $status == 3) { $badge_bg = '#2e9b5b'; }   // Enviado / Entregado -> verde
elseif ((int) $status == 1 || (int) $status == 2) { $badge_bg = '#e0992a'; } // Pendiente / Proceso -> ambar

$order_link  = tep_catalog_href_link(FILENAME_CATALOG_ACCOUNT_HISTORY_INFO, 'order_id=' . $oID, 'SSL');
$politica    = defined('EMAIL_POLITICA') ? EMAIL_POLITICA : '';
$pie_contact = defined('PIE_EMAIL') ? PIE_EMAIL : '';
$ff = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";

$html_email = '<!DOCTYPE html>
<html lang="es">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>' . STORE_NAME . '</title>
<style>
  body{margin:0;padding:0;background:#eef1f4;-webkit-text-size-adjust:100%;}
  table{border-collapse:collapse;}
  img{border:0;line-height:100%;outline:none;text-decoration:none;}
  a{color:#0090c8;}
  @media only screen and (max-width:600px){
    .container{width:100% !important;border-radius:0 !important;}
    .px{padding-left:24px !important;padding-right:24px !important;}
    .cat{display:inline-block !important;margin:5px 9px !important;}
  }
</style>
</head>
<body style="margin:0;padding:0;background:#eef1f4;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;font-size:1px;line-height:1px;color:#eef1f4;">' . UHE_MESSAGE_GREETING . '</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef1f4;">
<tr><td align="center" style="padding:26px 12px;">

  <table role="presentation" class="container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;background:#ffffff;border-radius:14px;overflow:hidden;font-family:' . $ff . ';">

    <tr><td align="center" style="background:#00374d;padding:28px 24px 24px;">
      <a href="' . $url . '"><img src="' . $logo . '" width="205" alt="' . STORE_NAME . '" style="display:block;width:205px;max-width:62%;height:auto;"></a>
    </td></tr>
    <tr><td style="height:4px;background:#00aff0;font-size:0;line-height:0;">&nbsp;</td></tr>

    <tr><td class="px" style="padding:36px 44px 4px;">
      <h1 style="margin:0 0 7px;font-size:22px;line-height:28px;color:#00374d;font-weight:700;">' . UHE_TEXT_DEAR . ' ' . $check_status['customers_name'] . '</h1>
      <p style="margin:0;font-size:15px;line-height:22px;color:#5f6b72;">' . UHE_MESSAGE_GREETING . '</p>
    </td></tr>

    <tr><td class="px" align="center" style="padding:24px 44px 4px;">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center"><tr>
        <td style="font-size:12px;color:#8a949b;letter-spacing:.4px;text-transform:uppercase;padding-right:13px;font-family:' . $ff . ';">' . UHE_TEXT_STATUS . '</td>
        <td style="background:' . $badge_bg . ';border-radius:30px;padding:9px 24px;"><span style="font-size:16px;font-weight:700;color:#ffffff;font-family:' . $ff . ';">' . $status_label . '</span></td>
      </tr></table>
    </td></tr>

    <tr><td class="px" style="padding:22px 44px 0;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f7f9;border-radius:10px;">
        <tr><td style="padding:18px 22px 4px;">
          <span style="font-size:11px;color:#8a949b;letter-spacing:.5px;text-transform:uppercase;font-family:' . $ff . ';">' . UHE_TEXT_ORDER_NUMBER . '</span><br>
          <span style="font-size:18px;color:#00374d;font-weight:700;font-family:' . $ff . ';">' . $oID . '</span>
        </td></tr>
        <tr><td style="padding:8px 22px;">
          <span style="font-size:11px;color:#8a949b;letter-spacing:.5px;text-transform:uppercase;font-family:' . $ff . ';">' . UHE_TEXT_DATE_ORDERED . '</span><br>
          <span style="font-size:15px;color:#41525b;font-weight:600;font-family:' . $ff . ';">' . tep_date_long($check_status['date_purchased']) . '</span>
        </td></tr>';
        if ($comments != '')
        {
        $html_email .= '
        <tr><td style="padding:8px 22px 20px;">
          <div style="border-top:1px solid #e3e9ed;padding-top:14px;font-size:11px;color:#8a949b;letter-spacing:.5px;text-transform:uppercase;font-family:' . $ff . ';">' . UHE_TEXT_COMMENTS . '</div>
          <div style="font-size:14px;color:#41525b;line-height:21px;margin-top:5px;font-family:' . $ff . ';">' . ($cron_status == true ? $comments : str_replace( $ArrayLNTargets, '<br />', $comments ) ) . '</div>
        </td></tr>';
        }
        else
        {
        $html_email .= '<tr><td style="padding:0 0 4px;"></td></tr>';
        }
        $html_email .= '
      </table>
    </td></tr>

    <tr><td class="px" align="center" style="padding:26px 44px 6px;">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center"><tr>
        <td align="center" bgcolor="#00374d" style="border-radius:8px;">
          <a href="' . $order_link . '" target="_blank" style="display:inline-block;padding:14px 36px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:8px;font-family:' . $ff . ';">Ver mi pedido</a>
        </td>
      </tr></table>
    </td></tr>

    <!--GOOGLE_CTA-->

    <tr><td class="px" align="center" style="padding:30px 44px 4px;">
      <div style="border-top:1px solid #eaeef1;padding-top:22px;">
        <a class="cat" href="' . $url . 'nautica-c-482.html" style="color:#0090c8;font-size:14px;font-weight:600;text-decoration:none;margin:0 11px;font-family:' . $ff . ';">N&aacute;utica</a>
        <a class="cat" href="' . $url . 'pesca-c-56.html" style="color:#0090c8;font-size:14px;font-weight:600;text-decoration:none;margin:0 11px;font-family:' . $ff . ';">Pesca</a>
        <a class="cat" href="' . $url . 'tiempo-libre-c-373.html" style="color:#0090c8;font-size:14px;font-weight:600;text-decoration:none;margin:0 11px;font-family:' . $ff . ';">Tiempo libre</a>
        <a class="cat" href="' . $url . 'submarinismo-c-491.html" style="color:#0090c8;font-size:14px;font-weight:600;text-decoration:none;margin:0 11px;font-family:' . $ff . ';">Submarinismo</a>
      </div>
    </td></tr>

    <tr><td class="px" style="padding:16px 44px 26px;">
      <p style="margin:0;font-size:11px;line-height:17px;color:#aab3b9;text-align:center;font-family:' . $ff . ';">' . $politica . '</p>
    </td></tr>

    <tr><td align="center" style="background:#00374d;padding:24px 30px;">
      <img src="' . $logo . '" width="150" alt="' . STORE_NAME . '" style="display:block;width:150px;height:auto;margin:0 auto 12px;">
      <p style="margin:0;font-size:12px;line-height:19px;color:#9fc0d2;font-family:' . $ff . ';">' . $pie_contact . '</p>
    </td></tr>

  </table>

  <table role="presentation" class="container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;"><tr>
    <td align="center" style="padding:16px 24px 4px;font-size:11px;line-height:17px;color:#9aa4ab;font-family:' . $ff . ';">Has recibido este correo porque realizaste un pedido en ' . STORE_NAME . '.</td>
  </tr></table>

</td></tr>
</table>
</body>
</html>';

$html_email = str_replace( $ArrayLNTargets, '', $html_email );
?>
