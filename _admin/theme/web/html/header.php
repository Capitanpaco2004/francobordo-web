<?php
use util\event;
global $login_email_address;
$bBody = false;
if( in_array( basename( (string) $_SERVER['SCRIPT_NAME'] ), [ FILENAME_LOGIN, FILENAME_PASSWORD_FORGOTTEN, FILENAME_LOGOFF_ADMIN, 'login_2fa.php' ] ) ) {
	$bBody = true;
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="es-ES">
    <head>
        <title><?php echo TITLE; ?></title>
        <meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>">
        <meta name="language" content="es" />
        <link rel="stylesheet" type="text/css" href="<?php  echo THEME; ?>css/style.css"/>
        <link rel="stylesheet" type="text/css" href="<?php  echo THEME; ?>css/font.css"/>
        <link rel="stylesheet" type="text/css" href="<?php  echo THEME; ?>css/plugins.css"/>
        <link rel="stylesheet" type="text/css" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/themes/smoothness/jquery-ui.min.css"/>
        <?php if( in_array( basename( $_SERVER['SCRIPT_NAME'] ), array( 'rma.php', 'precios_etiquetas.php', 'precios_catalogo.php' ) ) ): ?>
            <link rel="stylesheet" type="text/css" href="includes/modules/order_edit/css/style.css?v=11"/>
            <link rel="stylesheet" type="text/css" href="theme/solenopsis/css/style_base.css"/>
            <link rel="stylesheet" type="text/css" href="includes/modules/rma/style.css?v=11"/>
            <link rel="stylesheet" type="text/css" href="theme/solenopsis/css/style.css"/>
        <?php endif; ?>
		<link rel="stylesheet" type="text/css" href="theme/solenopsis/css/grid.css"/>
        <script src="<?php echo THEME; ?>js/tabpane.js" type="text/javascript"></script>

		<?php echo implode('', event::getInstance()->execute('back_office_header_after_metas')); ?>
    </head>
    <body id="<?php echo $language; ?>" class="<?php echo str_replace( '.php', '', basename( (string) $_SERVER['SCRIPT_NAME'] ) ) . ' ' . $admin_menu_left; ?>">
        <?php
            $languages = tep_get_languages();
            $languages_array = [];
            $languages_selected = DEFAULT_LANGUAGE;
            for( $i = 0, $n = count($languages); $i < $n; $i++ )
            {
                $languages_array[] = [ 'id' => $languages[$i]['code'], 'text' => $languages[$i]['name'] ];

                if ($languages[$i]['directory'] == $language) {
                    $languages_selected = $languages[$i]['code'];
                }
            }

            if( $bBody ) {
				return;
			}

        ?>

		<div id="cbcr" class="ax row aflex">
			<div class="col main aself-middle afluid">
				<a href="<?php echo tep_href_link('index.php'); ?>"><img src="theme/solenopsis/css/logo.png"/></a>
				<a href="javascript:void(0);" id="icon-main"><span></span></a>
			</div>

			<div class="col head">
				<?php if( tep_admin_check_boxes( 'orders.php' ) ): ?>
					<a href="orders.php" class="ml-topbtn" title="Ir a Pedidos" style="display:inline-flex;align-items:center;gap:6px;height:36px;padding:0 14px;margin-right:8px;background:#3b82f6;color:#fff;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none;white-space:nowrap;"><span class="fa fa-shopping-cart"></span> Pedidos</a>
				<?php endif; ?>
				<?php if( tep_admin_check_boxes( 'customers.php' ) ): ?>
					<a href="customers.php" class="ml-topbtn" title="Ir a Clientes" style="display:inline-flex;align-items:center;gap:6px;height:36px;padding:0 14px;margin-right:14px;background:#3b82f6;color:#fff;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none;white-space:nowrap;"><span class="fa fa-users"></span> Clientes</a>
				<?php endif; ?>
				<span class="full fa fa-arrows-alt icon"></span>
				<a class="alrt mgp-inln" href="#alrt-modal"><span class="fa fa-bell"></span><span class="nmbr blink"></span></a>
				<?php
					$sInitials = strtoupper( mb_substr( (string) $aUser['admin_firstname'], 0, 1 ) . mb_substr( (string) $aUser['admin_lastname'], 0, 1 ) );
					if( trim( $sInitials ) === '' ) $sInitials = '?';
					$aGradients = [
						'linear-gradient(135deg, #667eea, #764ba2)',
						'linear-gradient(135deg, #f093fb, #f5576c)',
						'linear-gradient(135deg, #4facfe, #00f2fe)',
						'linear-gradient(135deg, #43e97b, #38f9d7)',
						'linear-gradient(135deg, #fa709a, #fee140)',
						'linear-gradient(135deg, #a18cd1, #fbc2eb)',
						'linear-gradient(135deg, #fccb90, #d57eeb)',
						'linear-gradient(135deg, #e0c3fc, #8ec5fc)',
						'linear-gradient(135deg, #f6d365, #fda085)',
						'linear-gradient(135deg, #89f7fe, #66a6ff)',
					];
					$nIdx = abs( crc32( (string) $login_email_address ) ) % count( $aGradients );
					$sGradient = $aGradients[ $nIdx ];
				?>
				<a href="#my-account" class="avtr mgp-inln">
					<img src="<?php echo "https://www.gravatar.com/avatar/" . md5( strtolower( trim( (string) $login_email_address ) ) ) . "?d=404&s=100";?>" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
					<span class="avtr-initials" style="display:none;background:<?php echo $sGradient; ?>;"><?php echo $sInitials; ?></span>
				</a>
			</div>
		</div>


		<div id="admin-cntd">
			<div id="admin-left">
				<div class="scll">
					<div id="admin-left-search">
						<i class="fas fa-search icon"></i>
						<input id="admin-left-search" type="text" placeholder="<?php echo BOX_SEARCH_PLACEHOLDER; ?>">
					</div>
					<ul class="menu">
						<li>
							<a href="<?php echo tep_href_link('index.php'); ?>"><i class="icon fa fa-chart-line"></i> <span>Dashboard</span></a>
						</li>
						<?php if( tep_admin_check_boxes( 'catalog.php' ) ): ?>
							<li>
								<a class="prnt" href="javascript:void(0);"><i class="icon fas fa-store-alt"></i> <span><?php echo BOX_HEADING_CATALOG; ?></span> <i class="fa fa-angle-right"></i></a>
								<div class="sbmn">
									<?php require(DIR_WS_BOXES . 'catalog.php'); ?>
								</div>
							</li>
						<?php endif; ?>

						<?php if( tep_admin_check_boxes( 'customers.php' ) ): ?>
							<li>
								<a class="prnt" href="javascript:void(0);"><i class="icon fa fa-users"></i> <span><?php echo BOX_HEADING_CUSTOMERS; ?></span> <i class="fa fa-angle-right"></i></a>
								<div class="sbmn">
									<?php require(DIR_WS_BOXES . 'customers.php'); ?>
								</div>
							</li>
						<?php endif; ?>

						<?php if( tep_admin_check_boxes( 'orders.php' ) ): ?>
							<li>
								<a class="prnt" href="javascript:void(0);"><i class="icon fa fa-shopping-basket"></i> <span><?php echo BOX_HEADING_ORDERS; ?></span> <i class="fa fa-angle-right"></i></a>
								<div class="sbmn">
									<?php require(DIR_WS_BOXES . 'orders.php'); ?>
								</div>
							</li>
						<?php endif; ?>

						<?php if( tep_admin_check_boxes( 'information.php' ) ): ?>
							<li>
								<a class="prnt" href="javascript:void(0);"><i class="icon fa fa-desktop"></i> <span>CMS</span> <i class="fa fa-angle-right"></i></a>
								<div class="sbmn">
									<?php require(DIR_WS_BOXES . 'information.php'); ?>
								</div>
							</li>
						<?php endif; ?>

						<?php if( tep_admin_check_boxes( 'modules.php' ) ): ?>
							<li>
								<a class="prnt" href="javascript:void(0);"><i class="icon fa fa-puzzle-piece"></i> <span><?php echo BOX_HEADING_MODULES; ?></span> <i class="fa fa-angle-right"></i></a>
								<div class="sbmn">
									<?php require(DIR_WS_BOXES . 'modules.php'); ?>
								</div>
							</li>
						<?php endif; ?>

						<?php if( tep_admin_check_boxes( 'taxes.php' ) ): ?>
							<li>
								<a class="prnt" href="javascript:void(0);"><i class="icon fa fa-globe"></i> <span><?php echo BOX_HEADING_LOCATION_AND_TAXES; ?></span> <i class="fa fa-angle-right"></i></a>
								<div class="sbmn">
									<?php require(DIR_WS_BOXES . 'taxes.php'); ?>
								</div>
							</li>
						<?php endif; ?>

						<?php if( tep_admin_check_boxes( 'reports.php' ) ): ?>
							<li>
								<a class="prnt" href="javascript:void(0);"><i class="icon fa fa-chart-bar"></i> <span><?php echo BOX_HEADING_STATS; ?></span> <i class="fa fa-angle-right"></i></a>
								<div class="sbmn">
									<?php require(DIR_WS_BOXES . 'reports.php'); ?>
								</div>
							</li>
						<?php endif; ?>

						<?php if( tep_admin_check_boxes( 'promotions.php' ) ): ?>
							<li>
								<a class="prnt" href="javascript:void(0);"><i class="icon fas fa-tachometer-alt"></i> <span><?php echo BOX_HEADING_SEO_PROMOTION; ?></span> <i class="fa fa-angle-right"></i></a>
								<div class="sbmn">
									<?php require(DIR_WS_BOXES . 'promotions.php'); ?>
								</div>
							</li>
						<?php endif; ?>

						<?php if( tep_admin_check_boxes( 'tools.php' ) ): ?>
							<li>
								<a class="prnt" href="javascript:void(0);"><i class="icon fa fa-wrench"></i> <span><?php echo BOX_HEADING_TOOLS; ?></span> <i class="fa fa-angle-right"></i></a>
								<div class="sbmn">
									<?php require(DIR_WS_BOXES . 'tools.php'); ?>
								</div>
							</li>
						<?php endif; ?>

						<?php if( tep_admin_check_boxes( 'marketing.php' ) ): ?>
							<li>
								<a class="prnt" href="javascript:void(0);"><i class="icon fa fa-bullhorn"></i> <span><?php echo BOX_HEADING_MARKETING; ?></span> <i class="fa fa-angle-right"></i></a>
								<div class="sbmn">
									<?php require(DIR_WS_BOXES . 'marketing.php'); ?>
								</div>
							</li>
						<?php endif; ?>

						<?php if( tep_admin_check_boxes( 'configuration.php' ) ): ?>
							<li>
								<a class="prnt" href="javascript:void(0);"><i class="icon fa fa-cog"></i> <span><?php echo BOX_HEADING_SYSTEM; ?></span> <i class="fa fa-angle-right"></i></a>
								<div class="sbmn">
									<?php require(DIR_WS_BOXES . 'configuration.php'); ?>
								</div>
							</li>
						<?php endif; ?>
						<?php if(is_dir('../blog/') ):?>
							<a class="prnt" href="/blog/wp-admin/" target="_blank"><i class="icon fas fa-newspaper"></i> <span>Blog</span></a>
						<?php endif; ?>
					</ul>
				</div>
			</div>
			<div id="admin-wrap">
				<div id="cntd">
					<?php
					// check if the configure.php file is writeable
					if ( (file_exists(dirname((string) $_SERVER['SCRIPT_FILENAME']) . '/includes/configure.php')) && (!is_writable(dirname((string) $_SERVER['SCRIPT_FILENAME']) . '/includes/configure.php')) ) {
						echo $messageStack->show(['class' => 'warning', 'text' => WARNING_CONFIG_FILE_WRITEABLE]);
					}

					//	 check if the session folder is writeable
					if (STORE_SESSIONS == '') {
						if (!is_dir(tep_session_save_path())) {
							echo $messageStack->show(['class' => 'warning', 'text' => WARNING_SESSION_DIRECTORY_NON_EXISTENT]);

						} elseif (!is_writable(tep_session_save_path())) {
							echo $messageStack->show(['class' => 'warning', 'text' => WARNING_SESSION_DIRECTORY_NOT_WRITEABLE]);
						}
					}

					if( $messageStack->size>0 && !in_array( basename( (string) $_SERVER['SCRIPT_NAME'] ), [ 'csv-products.php', 'opiniones.php', 'customers.php', 'categories.php' ] ) )
					{
						echo $messageStack->output();
					}
