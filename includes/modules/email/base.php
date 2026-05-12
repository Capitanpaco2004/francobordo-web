<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta  name="viewport" content="width=display-width, initial-scale=1.0, maximum-scale=1.0," />
		<title>Email <?php echo TITLE; ?></title>

		<link href='https://fonts.googleapis.com/css?family=Open+Sans:400,300,300italic,400italic,600,600italic,700,700italic,800,800italic' rel='stylesheet' type='text/css'/>

		<style type="text/css">
			html {
				width: 100%;
			}
			body {
				  margin:0; padding:0; width:100%; -webkit-text-size-adjust:none; -ms-text-size-adjust:none;
			}
			img {
				display: block !important; border:0; -ms-interpolation-mode:bicubic;
			}
			.MsoNormal {
				font-family:"Open Sans", Arial, Helvetica Neue, Helvetica, sans-serif !important;
			}
			.display-button td, .display-button a  {
				font-family: "Open Sans", Arial, Helvetica Neue, Helvetica, sans-serif !important;
			}
			.display-button a:hover {
				text-decoration:none !important;
			}
		</style>
	</head>
	<body>
		<table align="center" bgcolor="#ecedee" border="0" cellpadding="0" cellspacing="0" width="100%">
			<tr>
				<td align="center">
					<table align="center" border="0" cellpadding="0" cellspacing="0" width="680" class="display-width">
						<tr><td height="7"></td></tr>
						<tr>
							<td align="center" class="MsoNormal" style="padding: 0px 30px; color:#666666; font-family:Segoe UI, Helvetica Neue, Arial, Verdana, Trebuchet MS, sans-serif; font-size:12px; font-weight:600; line-height:22px; letter-spacing:1px; text-transform:uppercase;"></td>
						</tr>
						<tr><td height="7"></td></tr>
					</table>
				</td>
			</tr>
			<tr>
				<td align="center">
					<table align="center" bgcolor="#ffffff" border="0" cellpadding="0" cellspacing="0" width="680" class="display-width">
						<tr><td height="20"></td></tr>
						<tr>
							<td style="padding: 0px 30px;">
								<table align="left" border="0" cellpadding="0" cellspacing="0" class="display-width" style="border-collapse:collapse; mso-table-lspace:0pt; mso-table-rspace:0pt;">
									<tr>
										<td>
											<table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" class="display-width" style="border-collapse:collapse; mso-table-lspace:0pt; mso-table-rspace:0pt; width:auto !important;">
												<tr><td><img src="<?php echo $this->url; ?>/theme/web/logo-trans.png" alt="Logo"/></td></tr>
											</table>
										</td>
									</tr>
								</table>

								<table align="left" border="0" cellpadding="0" cellspacing="0" width="30" style=" border-collapse:collapse; mso-table-lspace:0pt; mso-table-rspace:0pt;"><tr><td height="15"></td></tr></table>

								<table align="right" border="0" cellpadding="0" cellspacing="0" class="display-width" style="border-collapse:collapse; mso-table-lspace:0pt; mso-table-rspace:0pt;">
									<tr>
										<td>
											<table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" class="display-width" style="border-collapse:collapse; mso-table-lspace:0pt; mso-table-rspace:0pt; width:auto !important;">
												<tr>
													<td></td>
													<td width="2"></td>
													<td></td>
													<td width="2"></td>
													<td></td>
													<td width="2"></td>
													<td></td>
												</tr>
											</table>
										</td>
									</tr>
								</table>
							</td>
						</tr>
						<tr><td height="35"></td></tr>
					</table>
				</td>
			</tr>
			<tr>
				<td align="center">
					<table align="center" bgcolor="#ffffff" border="0" cellpadding="0" cellspacing="0" width="680" class="display-width">
						<tr>
							<td align="left" class="MsoNormal" style="padding: 0px 30px; font-family:Segoe UI, Helvetica Neue, Arial, Verdana, Trebuchet MS, sans-serif; font-size:14px; color:#666666; line-height:24px;">
								{% block content %}{% endblock %}
							</td>
						</tr>
						<tr><td height="20"></td></tr>
					</table>
				</td>
			</tr>
			<tr>
				<td align="center">
					<table align="center" border="0" cellpadding="0" cellspacing="0" width="680" class="display-width">
						<tr><td height="7"></td></tr>
						<tr>
							<td align="center" class="MsoNormal" style="padding: 0px 30px;font-family: Segoe UI, Helvetica Neue, Arial, Verdana, Trebuchet MS, sans-serif;font-size: 11px;color: #666666;line-height: 22px;font-style: italic;">
								<?php
									global $language;
									include_once(DIR_FS_CATALOG . DIR_WS_LANGUAGES . $language . '.php');
									echo PIE_EMAIL;
								?>
							</td>
						</tr>
						<tr><td height="7"></td></tr>
						<tr>
							<td align="center"></td>
						</tr>
						<tr><td height="15"></td></tr>
					</table>
				</td>
			</tr>
		</table>
	</body>
</html>