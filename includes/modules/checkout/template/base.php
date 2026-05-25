<?php if ($checkoutDifferentPage): ?>
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml" <?php echo HTML_PARAMS; ?>>
		<head>
			<?php getHeader();?>
			<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
			<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0" />
		</head>
		<body id="<?php echo $language; ?>">
<?php else: ?>
	<?php include DIR_THEME . 'html/header.php';?>
	<?php include DIR_THEME . 'html/column_left.php';?>
<?php endif;?>

<div id="checkout" class="<?php echo $router->controller; ?>">
	<div class="row bar aflex ax web-cntd">
		<a href="<?php echo tep_href_link(FILENAME_SHOPPING_CART); ?>" class="col actv afixed a03 <?php echo ($nStep >= STEP_BAR_CART ? 'actv' : '') . ($nStep == STEP_BAR_CART ? 'here' : ''); ?>"><i class="mhide ick-tt ick-tt-17"></i><?php echo CHECKOUT_BAR_CART; ?></a>
		<a href="<?php echo tep_href_link(FILENAME_CHECKOUT_SHIPPING); ?>" class="col afixed a02 <?php echo ($nStep >= STEP_BAR_SHIPPING ? 'actv' : '') . ($nStep == STEP_BAR_SHIPPING ? ' here' : ''); ?>"><i class="mhide ick-tt ick-tt-17"></i><?php echo CHECKOUT_BAR_SHIPPING; ?></a>
		<a href="<?php echo tep_href_link(FILENAME_CHECKOUT_PAYMENT); ?>" class="col <?php echo ($nStep >= STEP_BAR_PAYMENT ? 'actv' : '') . ($nStep == STEP_BAR_PAYMENT ? ' here' : ''); ?>"><i class="mhide ick-tt ick-tt-17"></i><?php echo CHECKOUT_BAR_PAYMENT; ?></a>
		<a href="<?php echo tep_href_link(FILENAME_CHECKOUT_CONFIRMATION); ?>" class="col afixed a03 <?php echo ($nStep >= STEP_BAR_CONFIRMATION ? 'actv' : '') . ($nStep == STEP_BAR_CONFIRMATION ? ' here' : ''); ?>"><i class="mhide ick-tt ick-tt-17"></i><?php echo CHECKOUT_BAR_CONFIRMATION; ?></a>
		<div class="col afixed a02 <?php echo ($nStep >= STEP_BAR_SUCCESS ? 'actv' : '') . ($nStep == STEP_BAR_SUCCESS ? ' here' : ''); ?>"><i class="mhide ick-tt ick-tt-17" style="display: inline-block;"></i><?php echo CHECKOUT_BAR_SUCCESS; ?></div>
	</div>

	<?php if( isset($_GET['error_message']) && tep_not_null($_GET['error_message']) ): ?>
		<?php echo $messageStack->show( array( 'text' => htmlspecialchars(stripslashes(urldecode($_GET['error_message']))), 'class' => 'eror' ) ); ?>
	<?php endif; ?>

	<div id="chkc-trgt" class="web-cntd row ax aflex">
		<?php echo $htmlCheckout; ?>
	</div>
</div>

<script type="text/javascript">
	const FILENAME_CHECKOUT_SHIPPING = "<?php echo FILENAME_CHECKOUT_SHIPPING; ?>";
	const FILENAME_CHECKOUT_PAYMENT = "<?php echo FILENAME_CHECKOUT_PAYMENT; ?>";
	const FILENAME_CHECKOUT_CONFIRMATION = "<?php echo FILENAME_CHECKOUT_CONFIRMATION; ?>";
	const FILENAME_CHECKOUT_PROCESS = "<?php echo FILENAME_CHECKOUT_PROCESS; ?>";
	const FILENAME_CHECKOUT_SUCCESS = "<?php echo FILENAME_CHECKOUT_SUCCESS; ?>";
	const FILENAME_SHOPPING_CART = "<?php echo FILENAME_SHOPPING_CART; ?>";
</script>

<?php if ($checkoutDifferentPage): ?>
	<?php include DIR_THEME . 'scripts/scripts_footer.php';?>
	</body></html>
<?php else: ?>
	<?php include DIR_THEME . 'html/column_right.php';?>
	<?php include DIR_THEME . 'html/footer.php';?>
<?php endif;?>
