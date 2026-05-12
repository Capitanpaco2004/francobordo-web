<?php

require(DIR_FS_CATALOG . DIR_WS_LANGUAGES . $language . '.php');

$ArrayLNTargets = array("\r\n", "\n\r", "\n", "\r", "\t"); //This will be used for taking away linefeeds with str_replace() throughout the mail. Tabs is invisible so we take them away to

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

//Define the background images here
$bg_sides_url = HTTP_SERVER . '/includes/modules/UHtmlEmails/'.ULTIMATE_HTML_EMAIL_LAYOUT.'/bg_red.jpg';

$html_email_sp = '
<html>
<head><meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1"></head>
<body style="margin:0; padding:0;">

<table width="100%" align="center"  border="0" cellpadding="0" cellspacing="0" style="border:black solid 0px; height:100%;">
<tr>
	<td background="'.$bg_sides_url.'">&nbsp;</td>
	<td width="600" align="left" valign="top">
		<table>
			<tr>
				<td width="20"></td>
				<td width="560">
					<img border="0" src="' . HTTP_SERVER . '/theme/logo-trans.png">
					<br/>
					Estimada/o {NOMBRE_CLIENTE},<br /><br/>Le informamos de que nuestro siguiente artículo ya está disponible en nuestra tienda online:<br><br><b>{NOMBRE_PRODUCTO}</b>{ATRIBUTOS_PRODUCTO}<br/>Acceda al detalle del mismo a través del siguiente enlace: <a href="{ENLACE_PRODUCTO}">{ENLACE_PRODUCTO}</a><br /><br />Atentamente,<br>' . STORE_NAME . '
				</td>
				<td width="20"></td>
			</tr>
		</table>
	<td background="'.$bg_sides_url.'">&nbsp;</td>
</tr>
</table>

</body>
</html>';

$html_email_en = '
<html>
<head><meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1"></head>
<body style="margin:0; padding:0;">

<table width="100%" align="center"  border="0" cellpadding="0" cellspacing="0" style="border:black solid 0px; height:100%;">
<tr>
	<td background="'.$bg_sides_url.'">&nbsp;</td>
	<td width="600" align="left" valign="top">
		<table>
			<tr>
				<td width="20"></td>
				<td width="560">
					<img border="0" src="' . HTTP_SERVER . '/theme/web/logo-trans.png">
					<br/>
					Dear {NOMBRE_CLIENTE},<br /><br/>Le informamos de que nuestro siguiente artículo ya está disponible en nuestra tienda online:<br><br><b>{NOMBRE_PRODUCTO}</b>{ATRIBUTOS_PRODUCTO}<br/>View the details through the following link: <a href="{ENLACE_PRODUCTO}">{ENLACE_PRODUCTO}</a><br /><br />Sincerely,<br>' . STORE_NAME . '
					<br/><br/>
					<font face="Times New Roman, Times, serif" style="font-size:12px;"><i>' . EMAIL_POLITICA . '<i></font>
				</td>
				<td width="20"></td>
			</tr>
		</table>
	<td background="'.$bg_sides_url.'">&nbsp;</td>
</tr>
</table>

</body>
</html>';

$html_email_sp = str_replace($ArrayLNTargets, '', $html_email_sp);
$html_email_en = str_replace($ArrayLNTargets, '', $html_email_en);

?>