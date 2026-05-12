<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html <?php echo HTML_PARAMS; ?>>
	<head>
		<title>Elija un grupo de cliente</title>
		<meta http-equiv="Content-Type" content="text/html; charset="<?php echo CHARSET; ?>">
		<base href="<?php echo $baseHref; ?>">
		<link rel="stylesheet" type="text/css" href="stylesheet.css">
	</head>
	<body bgcolor="#ffffff" style="margin:0">
		<table border="0" width="100%" height="100%">
			<tr><td height="25"></td></tr>
			<tr>
				<td style="vertical-align: middle" align="middle">
					<form name="login" action="<?php echo tep_href_link(FILENAME_LOGIN, 'action=process'); ?>" method="post">
						<table border="0" bgcolor="#f1f9fe" cellspacing="10" style="border: 1px solid #7b9ebd; padding: 20px;">
							<tr>
								<td class="main">
									<h1>Elija un grupo de cliente</h1>
								</td>
							</tr>
							<tr>
								<td class="main" align="center">
									<?php echo tep_draw_pull_down_menu('new_customers_group_id', $customersGroups, $customer->getGroupId(), 'style="width: 100%; height: 33px; font-size: 15px; margin-top: 18px;"'); ?>
									<input type="hidden" name="email_address" value="<?php echo $customer->getEmail(); ?>">
									<input type="hidden" name="skip" value="true">
									<input type="hidden" name="password" value="<?php echo $password; ?>">
								</td>
							</tr>
							<tr><td height="25"></td></tr>
							<tr>
								<td class="main" align="center">
									<?php echo tep_image_submit('button_continue.gif', IMAGE_BUTTON_CONTINUE); ?>
								</td>
							</tr>
						</table>
					</form>
				</td>
			</tr>
		</table>
	</body>
</html>
