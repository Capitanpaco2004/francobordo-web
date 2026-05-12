<?php use util\tools;?>

<div class="col chkc-right">
	<?php echo $messageStack->show('message_error'); ?>

	<?php if ($messageMissingFree): ?>
		<div class="bner-yllw">
			<img class="bmbl thide mhide" src="<?php echo $sPathModule; ?>/images/1.png" />
			<?php echo str_replace(array('%MISSING_PRICE%', '%MAXIMUM_PRICE%'), array($aFree['missing_price'], $aFree['maximum_price']), CHECKOUT_CART_TEXT_FREE_SHIPPING); ?>
		</div>
	<?php endif; ?>

	<?php if ($getShippingText): ?>
		<?php echo $getShippingText; ?>
	<?php endif; ?>

	<?php if ($messageMissingFreeSuccess): ?>
		<div class="bner-yllw">
			<img class="bmbl thide mhide" src="<?php echo $sPathModule; ?>/images/5.png" />
			<?php echo CHECKOUT_CART_TEXT_FREE_SHIPPING_SUCCESS; ?>
		</div>
	<?php endif; ?>

	<a href="<?php echo tep_href_link(FILENAME_CHECKOUT_SHIPPING); ?>" id="chkt-bton" class="xbutton verde hv9 expand bton-cntn dhide thide"><?php echo CHECKOUT_BUTTON_FINISH; ?></a>
	<div class="titu1"><?php echo str_replace('%COUNT%', $cart->count_contents(), CHECKOUT_CART_TITLE_CONTENT); ?></div>

	<?php echo $sHtmlCart; ?>
	
	<?php require(DIR_WS_COMPONENTS . 'shipping_estimator.php'); ?>

</div>

<?php echo tools::includeTemplate($sPathTemplate . '/column.php'); ?>
