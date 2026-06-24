<?php
	$sHtmlEmail = '
<html>
	<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>
	<body style="margin:0; padding:0;">
		<table width="100%"  border="0" cellpadding="0" cellspacing="0" style="border:black solid 0px; height:100%;">
			<tr>
				<td background="' . HTTP_SERVER . DIR_WS_CATALOG . DIR_WS_MODULES . 'UHtmlEmails/Stripes/bg_red.jpg">&nbsp;</td>
				<td width="600" align="center">
					<div align="left" style="font-size: 14px; width:600; padding:0 2em; border: black solid 0px; background-color:#FFFFFF; height:100%;">
						<br/>
						<a href="' . HTTP_SERVER . DIR_WS_CATALOG . '"><img src="' . HTTP_SERVER . DIR_WS_CATALOG . 'theme/web/logo-trans.png"/></a><br/><br/><br/>
						<font face="Times New Roman, Times, serif" style="font-size:14px;">
							<span style="font-size: 24px;">' . sprintf( OPINION_EMAIL_SALUDO, $aDatoOrder['customers_name'] ) .',</span><br/><br/>
						</font>';

						$sHtmlEmail .= OPINION_EMAIL_ENCABEZADO;
						$sHtmlEmail .= sprintf( OPINION_EMAIL_CUERPO, tep_href_link( 'escribir_opinion.php', 'o=' . $aDatoOpinion['uniqid'], 'NONSSL', false ) );

						$sHtmlEmail .= OPINION_EMAIL_PIE;
						$sHtmlEmail .= '<div style="font-size:11px; color:#999; margin-top:18px;">Si no deseas recibir m&aacute;s invitaciones a opinar, <a href="' . tep_href_link( 'baja_opiniones.php', 'o=' . $aDatoOpinion['uniqid'], 'NONSSL', false ) . '" style="color:#999;">date de baja aqu&iacute;</a>.</div>';

					$sHtmlEmail .='</div>
				</td>
				<td background="' . HTTP_SERVER . DIR_WS_CATALOG . DIR_WS_MODULES . 'UHtmlEmails/Stripes/bg_red.jpg">&nbsp;</td>
			</tr>
		</table>
	</body>
</html>';
?>