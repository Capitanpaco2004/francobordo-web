<?php
	// Cambiamos de directorio principal
	$_SERVER['SCRIPT_NAME'] = str_replace( 'includes/modules/500/index.php', 'error500.html', $_SERVER['SCRIPT_NAME'] );

	// Si no tiene idioma sera por defecto español
	$language = isset( $language ) ? $language : 'espanol';

	// Idioma
	require( 'includes/modules/500/languages/' . $language . '.php' );

	// Cabecera 500
	header( $_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500 );
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?php echo HTML_PARAMS; ?>>
	<head>
		<title><?php echo str_replace( '{WEB_TITLE}', TITLE, ERROR_500_TITLE ); ?></title>
		<base href="<?php echo ($request_type == 'SSL' ? HTTPS_SERVER : HTTP_SERVER) . DIR_WS_CATALOG; ?>">
		<meta name="robots" content="noindex, nofollow" />
		<link rel="stylesheet" type="text/css" href="includes/modules/500/css/style.css" />
	</head>
	<link href="https://fonts.googleapis.com/css?family=Work+Sans:400,700" rel="stylesheet">
	<body id="e500">
		<style type="text/css">
		<!--
			body{ background: <?php echo ERROR_500_COLOR_BACKGROUND; ?> !important; }
			body, body a{ color: <?php echo ERROR_500_COLOR_TEXT; ?> !important; }
			#srch .sbmt{ background: <?php echo ERROR_500_COLOR_SEARCH_BUTTON; ?> !important; }
		-->
		</style>
		<div class="web-cntd-e500">
			<?php if( ERROR_500_LOGO == 'true' ): ?>
				<div class="img"><img src="theme/web/logo-trans.png"></div>
			<?php endif; ?>
			<div class="e500">
				<div class="error"><?php echo ERROR_500_ERROR; ?></div>
				<div class="numb"><?php echo ERROR_500_500; ?></div>
				<div class="ups">
					<p><?php echo ERROR_500_UPS; ?></p>
					<small><?php echo ERROR_500_NOT_FOUND; ?></small>
				</div>
				<div class="clear"></div>
			</div>
			<div class="text-e500">
				<?php echo str_replace( '{NEW_PAGE}', tep_href_link( ERROR_500_LINK_NEW_PAGE ), ERROR_500_TEXT ); ?>
			</div>

			<?php if( ERROR_500_SEARCH == 'true' ): ?>
				<form id="srch" method="get" action="<?php echo tep_href_link( ERROR_500_LINK_SEARCH ); ?>">
					<input type="text" name="search" placeholder="<?php echo ERROR_500_SEARCH_PLACEHOLDER; ?>" class="inpt" />
					<input type="submit" class="sbmt" value="<?php echo ERROR_500_SEARCH_BUTTON; ?>" />
				</form>
			<?php endif; ?>
		</div>
    </body>
</html>
