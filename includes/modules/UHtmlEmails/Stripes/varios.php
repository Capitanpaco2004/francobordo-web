<?php

// Email utilizada para enviar cualquier texto

if (! function_exists('textLinks'))
{
	function textLinks($sText)
	{
		$sText = preg_replace( '#(script|about|applet|activex|chrome):#is', "\\1:", $sText );
		$sText = ' ' . $sText;
		$sText = preg_replace( "#(^|[\n ])([\w]+?://[\w\#$%&~/.\-;:=,?@\[\]+]*)#is", "\\1<a style=\"color:#135EAE;outline:medium none;\" href=\"\\2\" target=\"_blank\">\\2</a>", $sText );
		$sText = preg_replace( "#(^|[\n ])((www|ftp)\.[\w\#$%&~/.\-;:=,?@\[\]+]*)#is", "\\1<a style=\"color:#135EAE;outline:medium none;\" href=\"http://\\2\" target=\"_blank\">\\2</a>", $sText );
		$sText = preg_replace( "#(^|[\n ])([a-z0-9&\-_.]+?)@([\w\-]+\.([\w\-\.]+\.)*[\w]+)#i", "\\1<a style=\"color:#135EAE;outline:medium none;\" href=\"mailto:\\2@\\3\">\\2@\\3</a>", $sText );
		$sText = preg_replace( "#(^|[\n ])(\#)([\w\#$%&~/.\-;:=,?@\[\]+]*)#is", "\\1\\2<a href=\"http://search.twitter.com/search?q=%23\\3\" target=\"_blank\">\\3</a>", $sText );
		$sText = preg_replace( "#(^|[\n ])(\@)([\w\#$%&~/.\-;:=,?@\[\]+]*)#is", "\\1\\2<a href=\"http://twitter.com/\\3\" target=\"_blank\">\\3</a>", $sText );
		$sText = substr( $sText, 1 );

		return $sText;
	}
}

$url = HTTP_SERVER . DIR_WS_CATALOG ;
$ArrayLNTargets = array( "\r\n", "\n\r", "\n", "\r", "\t" );
$bg_sides_url = HTTP_SERVER . '/includes/modules/UHtmlEmails/'.ULTIMATE_HTML_EMAIL_LAYOUT.'/bg_red.jpg';

$sHtmlEmail = '
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
									<td colspan="2" style="padding: 35px 0px; font-size: 15px; font-family: Arial; letter-spacing: 0.2px; color: #5f5f5f; line-height: 14px;">
										' . $email . '
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

$sHtmlEmail = str_replace( $ArrayLNTargets, '', $sHtmlEmail );

?>