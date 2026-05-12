<?php
/**
 * Template de email para el módulo delivery_estimate.
 *
 * Variables esperadas (inyectadas por delivery_estimate_admin::sendUpdateEmail):
 *   $oID                     — ID del pedido (int)
 *   $check_status            — array con customers_name, customers_email_address, date_purchased
 *   $delivery_subject        — asunto ya sustituido (no se pinta aquí, lo usa tep_mail fuera)
 *   $delivery_html_body      — cuerpo ya sustituido, en HTML listo para inyectar
 *   $delivery_estimated_date — fecha estimada en YYYY-MM-DD
 *
 * Composición:
 *   [Logo] → [Banner verde con fecha estimada] → [Info pedido] → [Cuerpo libre] → [Política] → [Footer image]
 *   Laterales en negro de lado a lado.
 */

$url          = HTTP_SERVER . DIR_WS_CATALOG;
$logoUrl      = HTTP_SERVER . DIR_WS_CATALOG . DIR_THEME . 'images/email/cbcr.jpg';
$footerUrl    = HTTP_SERVER . DIR_WS_CATALOG . DIR_THEME . 'images/email/footer1.jpg';

// Fecha compra formateada dd/mm/yyyy
$datePurchased = '';
if( isset( $check_status['date_purchased'] ) ) {
	$ts = strtotime( (string)$check_status['date_purchased'] );
	if( $ts !== false ) $datePurchased = date( 'd/m/Y', $ts );
}

// Fecha estimada formateada dd/mm/yyyy (la variable viene como Y-m-d)
$dateEstimated = '';
if( isset( $delivery_estimated_date ) ) {
	$ts = strtotime( (string)$delivery_estimated_date );
	if( $ts !== false ) $dateEstimated = date( 'd/m/Y', $ts );
}

$html_email = '
<html>
	<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>
	<body style="margin:0; padding:0; background:#ffffff;">
		<table width="100%" cellspacing="0" cellpadding="0" border="0" align="center" bgcolor="#ffffff">
			<tbody>
				<tr>
					<td>&nbsp;</td>
					<td bgcolor="#FFFFFF" width="780" valign="top" align="left" style="vertical-align:top; width: 780px;">
						<table width="100%" cellspacing="0" cellpadding="0" border="0" align="center">
							<tbody>

								<!-- Cabecera: logo -->
								<tr>
									<td bgcolor="#FFFFFF" align="center" valign="middle" style="padding:0;">
										<a href="' . $url . '">
											<img width="780" height="121" border="0" src="' . $logoUrl . '" style="display:block; border:0; max-width:100%; height:auto;" alt="">
										</a>
									</td>
								</tr>

								<!-- Banner: nueva fecha estimada -->
								<tr>
									<td style="padding:30px 30px 20px 30px;">
										<table width="100%" style="font-family:Arial,Helvetica,sans-serif;" border="0" cellpadding="18" cellspacing="0">
											<tr style="color:#ffffff; font-weight:bold; font-size:16px;">
												<td align="center" width="50%" bgcolor="#2bb0e2" style="border-right:2px solid #ffffff;">Nueva fecha estimada</td>
												<td align="center" width="50%" bgcolor="#58d972" style="font-size:20px;">' . htmlspecialchars( $dateEstimated, ENT_QUOTES, 'UTF-8' ) . '</td>
											</tr>
										</table>
									</td>
								</tr>

								<!-- Info del pedido -->
								<tr>
									<td style="padding:0 30px;">
										<table width="100%" style="font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#23a2d1;" border="0" cellspacing="0" cellpadding="0">
											<tr>
												<td style="vertical-align:middle; border-top:1px solid #e4e4e4; border-bottom:1px solid #e4e4e4; padding:13px 0; text-align:left;">
													Nº de pedido &nbsp;<b style="color:#4f4f4f;">' . (int)$oID . '</b>
												</td>
												<td style="vertical-align:middle; padding:13px 0; border-top:1px solid #e4e4e4; border-bottom:1px solid #e4e4e4; text-align:right;">
													Fecha compra &nbsp;<span style="color:#606060;">' . htmlspecialchars( $datePurchased, ENT_QUOTES, 'UTF-8' ) . '</span>
												</td>
											</tr>
										</table>
									</td>
								</tr>

								<!-- Cuerpo libre (configurado por el usuario) -->
								<tr>
									<td style="padding:30px 30px 30px 30px; font-size:15px; font-family:Arial,Helvetica,sans-serif; color:#4f4f4f; line-height:24px;">
										' . $delivery_html_body . '
									</td>
								</tr>

								<!-- Footer: imagen -->
								<tr>
									<td align="center" bgcolor="#34302b" style="padding:0;">
										<a href="' . $url . '">
											<img border="0" src="' . $footerUrl . '" style="display:block; border:0; max-width:100%; height:auto; margin:0 auto;" alt="">
										</a>
									</td>
								</tr>

								<!-- Política de email (debajo del footer) -->
								' . ( defined('EMAIL_POLITICA') && EMAIL_POLITICA !== '' ? '
								<tr>
									<td align="center" style="padding:20px 30px 25px 30px; font-family:Arial,Helvetica,sans-serif; font-size:11px; font-style:italic; color:#908e8c; line-height:16px;">
										' . EMAIL_POLITICA . '
									</td>
								</tr>' : '' ) . '

							</tbody>
						</table>
					</td>
					<td>&nbsp;</td>
				</tr>
			</tbody>
		</table>
	</body>
</html>';
?>
