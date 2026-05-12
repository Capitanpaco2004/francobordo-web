<?php if ($sHref != ''): ?>
	<a href="<?php echo $sHref; ?>" class="xbutton verde hv9 expand bton-cntn"><?php echo $sText; ?></a>
<?php else: ?>
	<div class="checkout_form_target xbutton verde hv9 expand bton-cntn"><?php echo $sText; ?><i class="fas fa-spinner fa-pulse load"></i></div>
<?php endif;?>
