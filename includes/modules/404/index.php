<?php
	// Cambiamos de directorio principal
	if (!defined('DIR_FS_CATALOG')) {
		chdir( dirname(__DIR__) . '/../../' );
	}
    $pathRootProject = preg_replace('/includes\/.+$/', '', $_SERVER['SCRIPT_NAME']);
    $pathRootProject = $pathRootProject == '' ? '/' : trim($pathRootProject, '/');
	$_SERVER['SCRIPT_NAME'] = ($pathRootProject != '/' ? '/' . $pathRootProject : $pathRootProject) . '/error404.html';

	// Librerias
	require_once 'includes/application_top.php';

	// Idioma
	require( 'includes/modules/404/languages/' . $language . '.php' );

	// Cabecera 404
	header( "HTTP/1.0 404 Not Found" );

	// Security
	if( isset( $dxSecurity ) && $dxSecurity->configuration['SECURITY_DETECTION_404'] )
		$dxSecurity->error404();
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?php echo HTML_PARAMS; ?>>
	<head>
		<title><?php echo str_replace( '{WEB_TITLE}', TITLE, ERROR_404_TITLE ); ?></title>
		<base href="<?php echo ($request_type == 'SSL' ? HTTPS_SERVER : HTTP_SERVER) . DIR_WS_CATALOG; ?>">
		<meta name="robots" content="noindex, nofollow" />
		<link rel="stylesheet" type="text/css" href="includes/modules/404/css/style.css" />
	</head>
	<link href="https://fonts.googleapis.com/css?family=Work+Sans:400,700" rel="stylesheet">
	<body id="e404">
		<style type="text/css">
		<!--
			body{ background: <?php echo ERROR_404_COLOR_BACKGROUND; ?> !important; }
			body, body a{ color: <?php echo ERROR_404_COLOR_TEXT; ?> !important; }
			#srch .sbmt{ background: <?php echo ERROR_404_COLOR_SEARCH_BUTTON; ?> !important; }
		-->
		</style>
		<div class="web-cntd-e404">
			<?php if( ERROR_404_LOGO == 'true' ): ?>
				<div class="img"><img src="theme/web/logo-trans.png"></div>
			<?php endif; ?>
			<div class="e404">
				<div class="error"><?php echo ERROR_404_ERROR; ?></div>
				<div class="numb"><?php echo ERROR_404_404; ?></div>
				<div class="ups">
					<p><?php echo ERROR_404_UPS; ?></p>
					<small><?php echo ERROR_404_NOT_FOUND; ?></small>
				</div>
				<div class="clear"></div>
			</div>
			<div class="text-e404">
				<?php echo str_replace( '{NEW_PAGE}', tep_href_link( ERROR_404_LINK_NEW_PAGE ), ERROR_404_TEXT ); ?>
			</div>

			<?php if( ERROR_404_SEARCH == 'true' ): ?>
				<form id="srch" method="get" action="<?php echo tep_href_link( ERROR_404_LINK_SEARCH ); ?>">
					<input type="text" name="search" placeholder="<?php echo ERROR_404_SEARCH_PLACEHOLDER; ?>" class="inpt" />
					<input type="submit" class="sbmt" value="<?php echo ERROR_404_SEARCH_BUTTON; ?>" />
				</form>
			<?php endif; ?>
		</div>
    </body>
</html>

<?php exit(); ?>
