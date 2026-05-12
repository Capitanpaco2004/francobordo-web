<?php
require(DIR_WS_LANGUAGES . $language . '/modules/UHtmlEmails/Standard/password_forgotten.php');

if (ACCOUNT_GENDER == 'true')
{
	if ($gender == 'm')
		$HTMLGreet = sprintf(trim(UHE_GREET_MR), $check_customer['customers_lastname']);
	else
		$HTMLGreet = sprintf(trim(UHE_GREET_MS), $check_customer['customers_lastname']);
}
else
	$HTMLGreet = sprintf(trim(UHE_GREET_NONE), $check_customer['customers_firstname'].' '.$check_customer['customers_lastname']);

$ArrayLNTargets = array("\r\n", "\n\r", "\n", "\r", "\t");
$bg_sides_url = HTTP_SERVER . DIR_WS_CATALOG . DIR_WS_MODULES .'UHtmlEmails/'.ULTIMATE_HTML_EMAIL_LAYOUT.'/bg_red.jpg';
$url = HTTP_SERVER . DIR_WS_CATALOG ;

$html_email = '
<html>
<head><meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1"></head>
	<body style="margin:0; padding:0;">
		<table width="100%" cellspacing="0" cellpadding="0" border="0" align="center">
			<tbody>
				<tr>
					<td background="'.$bg_sides_url.'">&nbsp;</td>
					<td bgcolor="#FFFFFF" width="780" valign="top" align="left" style="vertical-align:top; width: 728px;">
						<table width="100%" cellspacing="0" cellpadding="0" border="0" align="center">
							<tbody>
								<tr>
									<td colspan="2" bgcolor="#FFFFFF" align="center" height="121" valign="middle">
										<a href="' . $url . '">
											<img width="780" height="121" border="0" src="' . HTTP_SERVER . DIR_WS_CATALOG . DIR_THEME . 'images/email/cbcr.jpg" style="display:block; border: 0;">
										</a>
									</td>
								</tr>
								<tr>
									<td colspan="2" style="padding: 35px 30px; font-size: 15px; font-family: Arial; letter-spacing: 0.2px; color: #5f5f5f; line-height: 24px;">
										<span style="font-size: 18px; font-weight: bold; line-height: 14px; color: #1fa1d0;">' . $HTMLGreet . '</span>
										<br><br>
										' . str_replace( $ArrayLNTargets, '<br />', UHE_PASSWORD_REMINDER_BODY ) . '
										<br><br>
										<table width="79%" align="center" style="line-height: 44px; font-size: 15px; font-family: Arial; letter-spacing: 0.2px; color: #5f5f5f	;" border="0" cellspacing="0" cellpadding="0">
											<tr>
												<td width="50%" align="center">' . UHE_USER . '</td>
												<td align="center">' . UHE_PASS . '</td>
											</tr>
											<tr>
												<td width="50%" align="center">
													<span style="border-radius: 12px; background: #b5b8b9; display: block; font-size: 22px; line-height: 68px; font-weight: bold; height: 72px; width: 248px; color: #fff;">' . $email_address . '</span>
												</td>
												<td align="center">
													<span style="border-radius: 12px; background: #2bb0e2; display: block; font-size: 22px; line-height: 68px; font-weight: bold; height: 72px; width: 248px; color: #fff;">' . $new_password . '</span>
												</td>
											</tr>
										</table>
										<br><br>
										' . UHE_CONTACT . '
										<br>
									</td>
								</tr>
								<tr>
									<td colspan="2" align="center" style="padding: 25px 35px; font-family: Arial; font-size: 13px; font-style: italic; color: #908e8c; line-height: 18px;">
										' . EMAIL_POLITICA . '
									</td>
								</tr>
								<tr>
									<td align="center" height="176">
										<a href="' . $url . '">
											<img width="321" height="176" border="0" src="' . HTTP_SERVER . DIR_WS_CATALOG . DIR_THEME . 'images/email/footer1.jpg" style="display:block; border: 0;">
										</a>
									</td>
									<td align="center" height="176">
										<table width="100%" align="center"  border="0" cellpadding="0" cellspacing="0">
											<tr>
												<td>
													<a href="' . $url . 'nautica-c-482.html">
														<img width="137" height="93" border="0" src="' . HTTP_SERVER . DIR_WS_CATALOG . DIR_THEME . 'images/email/nautica.jpg" style="display:block; border: 0;">
													</a>
												</td>
												<td>
													<a href="' . $url . 'pesca-c-56.html">
														<img width="98" height="93" border="0" src="' . HTTP_SERVER . DIR_WS_CATALOG . DIR_THEME . 'images/email/pesca.jpg" style="display:block; border: 0;">
													</a>
												</td>
												<td>
													<a href="' . $url . 'tiempo-libre-c-373.html">
														<img width="98" height="93" border="0" src="' . HTTP_SERVER . DIR_WS_CATALOG . DIR_THEME . 'images/email/tiempo.jpg" style="display:block; border: 0;">
													</a>
												</td>
												<td>
													<a href="' . $url . 'submarinismo-c-491.html">
														<img width="126" height="93" border="0" src="' . HTTP_SERVER . DIR_WS_CATALOG . DIR_THEME . 'images/email/submarinismo.jpg" style="display:block; border: 0;">
													</a>
												</td>
											</tr>
											<tr>
												<td colspan="4" align="right" width="459" height="83" background="' . HTTP_SERVER . DIR_WS_CATALOG . DIR_THEME . 'images/email/fotr-ctct.jpg" style="position: relative;">
													<span style="font-size: 13px; color: rgb(150, 147, 146); font-family: arial; letter-spacing: -0.1px; display: block; padding-right: 29px;">
													' . PIE_EMAIL . '
													</span>
												</td>
											</tr>
										</table>
									</td>
								</tr>
							</tbody>
						</table>
					</td>
					<td background="'.$bg_sides_url.'">&nbsp;</td>
				</tr>
			</tbody>
		</table>
	</body>
</html>';


$html_email = str_replace($ArrayLNTargets, '', $html_email);

?>