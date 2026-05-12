<?php
	$sHtmlEmail = '
<html>
	<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>
	<body style="margin:0; padding:0;">
		<table width="100%"  border="0" cellpadding="0" cellspacing="0" style="border:black solid 0px; height:100%;">
			<tr>
				<td width="600" align="center">
					<div align="left" style="font-size: 14px; width:600; padding:0 2em; border: black solid 0px; background-color:#FFFFFF; height:100%;">
						<br/>
						<a href="' . HTTP_SERVER . DIR_WS_CATALOG . '"><img src="' . HTTP_SERVER . DIR_WS_CATALOG . 'theme/web/logo-trans.png"/></a><br/><br/><br/>
						<font face="Times New Roman, Times, serif" style="font-size:14px;">
							<span style="font-size: 24px;">' . $customersName .',</span><br/><br/>
						</font>
						<p>'. $message .'</p>
						</div>
				</td>
			</tr>
			<tr>
				<td colspan="2" align="center" style="border-top: 1px solid #e4e4e4; padding: 25px 35px; font-family: Arial; font-size: 13px; font-style: italic; color: #908e8c; line-height: 18px;">
					' . EMAIL_POLITICA . '
				</td>
			</tr>
		</table>
	</body>
</html>';
?>
