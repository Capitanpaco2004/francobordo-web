<?php

include_once(DIR_FS_CATALOG . DIR_WS_LANGUAGES . $language . '/modules/UHtmlEmails/Standard/orders.php');
include_once(DIR_FS_CATALOG . DIR_WS_LANGUAGES . $language . '.php');

$url = HTTP_SERVER . DIR_WS_CATALOG ;
$ArrayLNTargets = array( "\r\n", "\n\r", "\n", "\r", "\t" );
$bg_sides_url = HTTP_SERVER . DIR_WS_CATALOG . DIR_WS_MODULES .'UHtmlEmails/'.ULTIMATE_HTML_EMAIL_LAYOUT.'/bg_red.jpg';

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
									<td colspan="2" style="padding: 35px 0 25px; font-size: 15px; font-family: Arial; letter-spacing: 0.2px; color: #5f5f5f; line-height: 24px;">
										<span style="padding: 0 30px; font-size: 18px; font-weight: bold; line-height: 14px; color: #1fa1d0;">' . UHE_TEXT_DEAR . ' ' . $check_status['customers_name'] . '</span>
										<br>
										<span style="padding: 0 30px; line-height: 37px;">' . UHE_MESSAGE_GREETING . '
										<br><br>
										<table style="font-family: Arial;" border="0" cellpadding="27" cellspacing="0">
											<tr style="color:#fff; font-weight:bold; font-size: 16px; line-height: 14px;">
												<td align="center" width="165" bgcolor="#2bb0e2" style="border-right: 1px solid #fff;">' . UHE_TEXT_STATUS . '</td>
												<td align="center" width="614" bgcolor="#58d972">' . $orders_status_array[$status] . '</td>
											</tr>
										</table>
										<br>
										<table width="100%" style="line-height: 14px; font-size: 15px; font-family: Arial; letter-spacing: 0.2px; color: #23a2d1;" border="0" cellspacing="0" cellpadding="0">
											<tr>
												<td width="50%" style="vertical-align: top; border-top: 1px solid #e4e4e4; border-bottom: 1px solid #e4e4e4; border-right: 1px solid #e4e4e4; padding: 13px 0 13px 30px; text-align: left;">
													' . UHE_TEXT_ORDER_NUMBER . ' &nbsp;<b style="color: #4f4f4f;">' . $oID . '</b>
												</td>
												<td style="padding: 13px 23px; border-top: 1px solid #e4e4e4; border-bottom: 1px solid #e4e4e4;">' . UHE_TEXT_DATE_ORDERED . ' &nbsp;<font style="color: #606060;">' . tep_date_long($check_status['date_purchased']) . '</font></td>
											</tr>
											<tr>
												<td colspan="2" style="vertical-align: top; padding: 13px 30px 13px 30px; text-align: left;">
													' . UHE_TEXT_INVOICE_URL . '  <a href="<a href="' . tep_catalog_href_link(FILENAME_CATALOG_ACCOUNT_HISTORY_INFO, 'order_id=' . $oID, 'SSL') .'" style="color: #606060;">' . tep_catalog_href_link(FILENAME_CATALOG_ACCOUNT_HISTORY_INFO, 'order_id=' . $oID, 'SSL') .'</a>
												</td>
											</tr>
										</table>';
										if ($comments != '')
										{
										$html_email .= '
										<table width="100%" style="line-height: 20px; font-size: 15px; font-family: Arial; letter-spacing: 0.2px; color: #23a2d1; padding: 12px 0 100px;" border="0" cellspacing="0" cellpadding="0">
											<tr>
												<td width="108" style="vertical-align: top; padding: 0 0 0 30px; text-align: left;">
													' . UHE_TEXT_COMMENTS . ' &nbsp;
												</td>
												<td>
													<i style="color: #606060;">' . ($cron_status == true ? $comments : str_replace( $ArrayLNTargets, '<br />', $comments ) ). '</i>
												</td>
											</tr>
										</table>';
										}
										$html_email .= '
										<br>
									</td>
								</tr>
								<tr>
									<td colspan="2" align="center" style="border-top: 1px solid #e4e4e4; padding: 25px 35px; font-family: Arial; font-size: 13px; font-style: italic; color: #908e8c; line-height: 18px;">
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

$html_email = str_replace( $ArrayLNTargets, '', $html_email );
?>
