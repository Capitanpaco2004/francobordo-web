<?php
/*
=========================================================================================
This module does one thing: It creates the contents of the html email in the variable $html_email.

If you would like to customize this html email you should first make sure you know a bit about html emails.
Here are some liks that might be interesting for you:

http://www.xavierfrenette.com/articles/css-support-in-webmail/
http://www.alistapart.com/articles/cssemail/
http://www.campaignmonitor.com/blog/archives/2006/03/a_guide_to_css_1.html
http://www.reachcustomersonline.com/content/2004/11/11/09.27.00/index.php
=========================================================================================
*/
$ArrayLNTargets = array("\r\n", "\n\r", "\n", "\r", "\t"); //This will be used for taking away linefeeds with str_replace() throughout the mail. Tabs is invisible so we take them away to
//Define the background images here
$bg_sides_url = HTTP_SERVER . DIR_WS_CATALOG . DIR_WS_MODULES .'UHtmlEmails/'.ULTIMATE_HTML_EMAIL_LAYOUT.'/bg_red.jpg';
$bg_tableheading_url = HTTP_SERVER . DIR_WS_CATALOG . DIR_WS_MODULES .'UHtmlEmails/'.ULTIMATE_HTML_EMAIL_LAYOUT.'/bg_black.jpg';


$logo = HTTP_SERVER . DIR_WS_CATALOG . DIR_THEME .'logo-trans.png';
$url = HTTP_SERVER . DIR_WS_CATALOG ;

$html_email = '
<html>
<head><meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1"></head>
<body style="margin:0; padding:0;">

<table width="100%"  border="0" cellpadding="0" cellspacing="0" style="border:black solid 0px; height:100%;">
<tr>
	<td background="'.$bg_sides_url.'">&nbsp;</td>
	<td width="600" align="center">
	<div align="left" style="font-size: 14px; width:600; padding:0 2em; border: black solid 0px; background-color:#FFFFFF; height:100%;">
		<font face="Times New Roman, Times, serif" style="font-size:14px;"><a href="'.$url.'"><img src="'.$logo.'" alt="'.$logo.'" border="0"></a><br />
			&nbsp;<br />

			'.$HTMLNewsletterContents.'
		</font>
		<br/><br/>
		<font face="Times New Roman, Times, serif" style="font-size:12px;"><i>' . 'EMAIL_POLITICA' . '<i></font>
	</div>
	</td>
	<td background="'.$bg_sides_url.'">&nbsp;</td>
</tr>
</table>

</body>
</html>';


$html_email = str_replace($ArrayLNTargets, '', $html_email);

?>