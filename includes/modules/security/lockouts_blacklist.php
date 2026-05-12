<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="es-ES">
	<head>
		<title><?php echo TITLE; ?></title>
		<meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>">
		<meta name="language" content="es" />
		<link rel="stylesheet" type="text/css" href="includes/modules/security/css/lockouts_blacklist.css"/>
		<link href="https://use.fontawesome.com/a5201e176c.css" media="all" rel="stylesheet"/>
	</head>
	<body>
		<div id="titl" class="web-cntd">
			<h1>Lo sentimos, tu ip ha sido bloqueada</h1>
			<h2>No tienes acceso a <?php echo HTTP_SERVER; ?></h2>
		</div>
		<div id="image">
			<div class="web-cntd">
				<div class="bar">
					<div class="butn"></div>
					<div class="butn"></div>
					<div class="butn"></div>
					<div class="pstn"></div>
				</div>
				<div class="cntd">
					<i class="fa fa-times-circle"></i>
				</div>
			</div>
		</div>
		<div id="text" class="row ax web-cntd">
			<div class="column a06">
				<div class="titu">¿Por qué he sido bloqueado?</div>
				<?php echo $dxSecurity->configuration['SECURITY_GLOBAL_MESSAGE_LOCKOUT_SERVER']; ?>
			</div>
			<div class="column a06">
				<div class="titu">¿Qué puedo hacer para resolver esto?</div>
				<?php echo $dxSecurity->configuration['SECURITY_GLOBAL_MESSAGE_LOCKOUT_SERVER_RESOLVE']; ?>
			</div>
		</div>
		<div id="fotr" class="web-cntd">
			· <b>Tu IP:</b> <em><?php echo $dxSecurity->ip; ?></em> · Actuación de seguridad por <?php echo HTTP_SERVER; ?> ·
		</div>
	</body>
</html>
