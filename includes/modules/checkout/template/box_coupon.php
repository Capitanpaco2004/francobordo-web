<?php if (MODULE_ORDER_TOTAL_DISCOUNT_COUPON_STATUS == 'true'): ?>
	<?php if (!isset($coupon) || $coupon == ''): ?>
		<div class="box-cupn">
			<div class="titu"><?php echo CHECKOUT_BOX_COUPON_TITLE; ?></div>
			<div class="row ax aflex atop">
				<?php echo tep_draw_input_field('coupon', '', 'class="col" placeholder="' . CHECKOUT_BOX_COUPON_PLACEHOLDER . '"'); ?>
				<div class="col afixed xbutton verde chkc-add-cupn"><?php echo CHECKOUT_APPLY; ?></div>
			</div>
		</div>
	<?php else: ?>
		<div class="box-cupn dlte">
			<div class="titu">
				<?php echo str_replace('%COUPON%', (string)$coupon, CHECKOUT_BOX_COUPON_USE); ?>
			</div>
			<div class="xbutton verde hv9 expand chkc-dlte-cupn"><?php echo CHECKOUT_DELETE; ?></div>
			<input type="hidden" name="coupon" value=""/>
		</div>
	<?php endif;?>
<?php endif;?>
