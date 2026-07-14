<?php
/**
 * Email dedicado "Valoranos en Google" (post-entrega). 2026-07-13.
 * Lo incluye _admin/cron_mail_status.php (bloque "Invitaciones Google post-entrega")
 * ~1 dia despues de que el pedido pase a ENTREGADO (estado 3).
 * Entrada: $g_name (nombre cliente), $g_oid (pedido), $g_baja (URL de baja, '' si no hay).
 * Salida:  $g_html_email.
 * Diseno basado en el boceto del usuario (2026-07-13): "Tu opinion nos hace mejores".
 * Todo acentos en entidades HTML (a prueba de charset latin1/utf8).
 */

$g_url  = HTTP_SERVER . DIR_WS_CATALOG;
$g_logo = $g_url . 'theme/web/images/custom/15.png';
$g_ff   = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
$g_ln   = array( "\r\n", "\n\r", "\n", "\r", "\t" );

// Wordmark "Google" en sus colores de marca (HTML puro, sin imagenes)
$g_wordmark = '<span style="font-size:36px;font-weight:700;font-family:Arial,Helvetica,sans-serif;letter-spacing:-1px;">'
    . '<span style="color:#4285F4;">G</span>'
    . '<span style="color:#EA4335;">o</span>'
    . '<span style="color:#FBBC05;">o</span>'
    . '<span style="color:#4285F4;">g</span>'
    . '<span style="color:#34A853;">l</span>'
    . '<span style="color:#EA4335;">e</span></span>';

$g_html_email = '<!DOCTYPE html>
<html lang="es">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . STORE_NAME . '</title>
<style>
  body{margin:0;padding:0;background:#eef1f4;-webkit-text-size-adjust:100%;}
  table{border-collapse:collapse;}
  img{border:0;line-height:100%;}
  @media only screen and (max-width:600px){
    .container{width:100% !important;border-radius:0 !important;}
    .px{padding-left:24px !important;padding-right:24px !important;}
  }
</style>
</head>
<body style="margin:0;padding:0;background:#eef1f4;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;font-size:1px;color:#eef1f4;">Tu rese&ntilde;a ayuda a otros navegantes &#9973;</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef1f4;">
<tr><td align="center" style="padding:26px 12px;">

  <table role="presentation" class="container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;background:#ffffff;border-radius:14px;overflow:hidden;font-family:' . $g_ff . ';">

    <tr><td align="center" style="background:#00374d;padding:26px 24px 22px;">
      <a href="' . $g_url . '"><img src="' . $g_logo . '" width="205" alt="' . STORE_NAME . '" style="display:block;width:205px;max-width:62%;height:auto;"></a>
    </td></tr>
    <tr><td style="height:4px;background:#00aff0;font-size:0;line-height:0;">&nbsp;</td></tr>

    <tr><td class="px" align="center" style="padding:36px 44px 6px;">
      <h1 style="margin:0 0 12px;font-size:26px;line-height:32px;color:#00374d;font-weight:700;">Tu opini&oacute;n nos hace mejores</h1>
      <p style="margin:0;font-size:15px;line-height:23px;color:#5f6b72;">Hola ' . $g_name . ', tu pedido <b style="color:#00374d;">' . (int) $g_oid . '</b> ya te ha llegado. En Francobordo trabajamos cada d&iacute;a para ofrecerte el mejor servicio y productos n&aacute;uticos de calidad, y nos encantar&iacute;a que compartieras tu experiencia.</p>
    </td></tr>

    <tr><td class="px" style="padding:26px 44px 8px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eaf4fb;border-radius:12px;">
        <tr><td align="center" style="padding:30px 26px 32px;">
          ' . $g_wordmark . '<br>
          <span style="font-size:24px;color:#FBBC05;letter-spacing:5px;line-height:36px;">&#9733;&#9733;&#9733;&#9733;&#9733;</span>
          <div style="font-size:19px;font-weight:700;color:#00374d;margin:10px 0 8px;font-family:' . $g_ff . ';">Val&oacute;ranos en Google</div>
          <div style="font-size:14px;color:#41525b;line-height:22px;margin-bottom:20px;font-family:' . $g_ff . ';">Tu rese&ntilde;a ayuda a otros navegantes a conocernos<br>y nos motiva a seguir mejorando.</div>
          <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center"><tr>
            <td align="center" bgcolor="#00374d" style="border-radius:8px;">
              <a href="' . GOOGLE_REVIEW_URL . '" target="_blank" style="display:inline-block;padding:15px 34px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:8px;font-family:' . $g_ff . ';">&#9733; DEJAR MI OPINI&Oacute;N</a>
            </td>
          </tr></table>
          <div style="font-size:12px;color:#7a8a96;margin-top:14px;font-family:' . $g_ff . ';">El enlace te llevar&aacute; a nuestra ficha de Google.</div>
        </td></tr>
      </table>
    </td></tr>

    <tr><td class="px" align="center" style="padding:26px 44px 4px;">
      <div style="font-size:16px;font-weight:700;color:#00374d;margin-bottom:16px;">Tu opini&oacute;n es importante porque:</div>
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
          <td width="50%" align="center" style="padding:10px 8px;"><span style="font-size:26px;">&#127941;</span><br><span style="font-size:13px;color:#5f6b72;line-height:19px;">Nos ayuda a mejorar<br>nuestro servicio</span></td>
          <td width="50%" align="center" style="padding:10px 8px;"><span style="font-size:26px;">&#128101;</span><br><span style="font-size:13px;color:#5f6b72;line-height:19px;">Ayuda a otros clientes<br>a tomar decisiones</span></td>
        </tr>
        <tr>
          <td width="50%" align="center" style="padding:10px 8px;"><span style="font-size:26px;">&#128153;</span><br><span style="font-size:13px;color:#5f6b72;line-height:19px;">Reconoce el trabajo<br>de nuestro equipo</span></td>
          <td width="50%" align="center" style="padding:10px 8px;"><span style="font-size:26px;">&#9875;</span><br><span style="font-size:13px;color:#5f6b72;line-height:19px;">Fortalece la confianza<br>en nuestra tienda</span></td>
        </tr>
      </table>
    </td></tr>

    <tr><td class="px" style="padding:22px 44px 30px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eaf4fb;border-radius:12px;">
        <tr><td align="center" style="padding:18px 24px;">
          <div style="font-size:15px;font-weight:700;color:#00374d;">&iexcl;Gracias por confiar en Francobordo! &#9973;</div>
          <div style="font-size:13px;color:#5f6b72;margin-top:4px;">Seguiremos trabajando para que tu pr&oacute;xima navegaci&oacute;n sea a&uacute;n mejor.</div>
        </td></tr>
      </table>
    </td></tr>

    <tr><td align="center" style="background:#00374d;padding:24px 30px;">
      <img src="' . $g_logo . '" width="150" alt="' . STORE_NAME . '" style="display:block;width:150px;height:auto;margin:0 auto 12px;">
      <p style="margin:0 0 6px;font-size:12px;line-height:19px;color:#9fc0d2;font-family:' . $g_ff . ';">&#128230; Env&iacute;os r&aacute;pidos 24/48 h &nbsp;&middot;&nbsp; Productos de primeras marcas &nbsp;&middot;&nbsp; &#128222; 916 528 858</p>
      <p style="margin:0 0 10px;font-size:12px;line-height:19px;color:#9fc0d2;font-family:' . $g_ff . ';">info@francobordo.com &nbsp;&middot;&nbsp; www.francobordo.com &nbsp;&middot;&nbsp; Calle San Rafael n&ordm; 8, 28108 Alcobendas (Madrid)</p>
      <p style="margin:0;font-size:13px;font-style:italic;color:#7fb2c9;font-family:Georgia,\'Times New Roman\',serif;">Equipamiento y accesorios para disfrutar del mar</p>
    </td></tr>

  </table>

  <table role="presentation" class="container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;"><tr>
    <td align="center" style="padding:16px 24px 4px;font-size:11px;line-height:17px;color:#9aa4ab;font-family:' . $g_ff . ';">Has recibido este correo porque realizaste un pedido en ' . STORE_NAME . '.'
    . ($g_baja !== '' ? ' Si no deseas recibir m&aacute;s invitaciones a opinar, <a href="' . $g_baja . '" style="color:#9aa4ab;">date de baja aqu&iacute;</a>.' : '') . '</td>
  </tr></table>

</td></tr>
</table>
</body>
</html>';

$g_html_email = str_replace( $g_ln, '', $g_html_email );
?>
