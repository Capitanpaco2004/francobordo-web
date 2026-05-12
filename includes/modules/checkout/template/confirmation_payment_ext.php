<?php if (CHECKOUT_PAYMENT_EXT == 'true'): ?>
	<div class="chkc-rsmn payment-ext">
		<div class="col a12 t12 m12">
			<div class="chkc-titu2"><?php echo CHECKOUT_CONFIRMATION_PAYMENT_EXT_TITLE; ?></div>
			<div class="chkc-adrb-wrpr">
				<div class="chkc-adrb">
					<div class="infr">
						<?php echo CHECKOUT_CONFIRMATION_PAYMENT_EXT_TEXT; ?>
					</div>
				</div>
			</div>
		</div>
	</div>
<? endif; ?>

<form name="checkout_confirmation" action="<?php echo $formAction; ?>" method="post" style="display: none;">
	<?php echo $htmlInputHidden; ?>
</form>

<script language="JavaScript">setTimeout("document.checkout_confirmation.submit() ", <?php echo $timeWait; ?>);</script>
