<?php use util\tools;?>

<form id="checkout_form" action="<?php echo $sUrlFormConfirmation; ?>" method="post" class="row ax aflex col a12">
	<div class="col chkc-right">
		<?php echo $messageStack->show('message_error'); ?>

		<?php echo $htmlInputHidden; ?>
		<div class="bner-yllw keyb">
			<?php echo CHECKOUT_CONFIRMATION_BANNER_FINISH; ?>
		</div>

		<div id="chkt-bton" class="xbutton verde hv9 expand bton-cntn dhide thide"><?php echo CHECKOUT_CONFIRM; ?></div>

		<div class="row ax chkc-rsmn">
			<div class="col a06 t12 m12">
				<div class="chkc-titu2"><?php echo CHECKOUT_CONFIRMATION_SHIPPING_TITLE_ADDRESS; ?></div>
				<div class="chkc-adrb-wrpr">
					<div class="chkc-adrb">
						<b class="titu"><?php echo $sAddressShippingTitle; ?> <a href="<?php echo tep_href_link(FILENAME_ADDRESS_BOOK_PROCESS, 'edit=' . $sendto); ?>" class="edit">(<?php echo CHECKOUT_EDIT; ?>)</a></b>
						<div class="infr">
							<?php echo $sAddressShipping; ?>
						</div>
					</div>
				</div>
			</div>
			<div class="col a06 t12 m12">
				<div class="chkc-titu2"><?php echo CHECKOUT_CONFIRMATION_PAYMENT_TITLE_ADDRESS; ?></div>
				<div class="chkc-adrb-wrpr">
					<div class="chkc-adrb">
						<b class="titu"><?php echo $sAddressPaymentTitle; ?> <a href="<?php echo tep_href_link(FILENAME_ADDRESS_BOOK_PROCESS, 'edit=' . $billto); ?>" class="edit">(<?php echo CHECKOUT_EDIT; ?>)</a></b>
						<div class="infr">
							<?php echo $sAddressPayment; ?>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row ax chkc-rsmn">
			<div class="col a06 t12 m12">
				<div class="chkc-titu2"><?php echo CHECKOUT_CONFIRMATION_PAYMENT_TITLE; ?></div>
				<div class="chkc-mthh-wrpr">
					<div class="chkc-mthh actv ax mx row aflex mflex amiddle">
						<div class="imge afixed">
							<img src="<?php echo (file_exists($sPathModule . '/images/payment_' . $payment . '.png') ? $sPathModule . '/images/payment_' . $payment . '.png' : $sPathModule . '/images/payment_default.png'); ?>"/>
						</div>
						<div class="infr-wrp mt-0">
							<div class="titu mb-0">
								<b><?php echo $order->info['payment_method']; ?></b>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="col a06 t12 m12">
				<div class="chkc-titu2"><?php echo CHECKOUT_CONFIRMATION_SHIPPING_TITLE; ?></div>
				<div class="chkc-mthh-wrpr">
					<div class="chkc-mthh actv ax mx row aflex mflex amiddle">
						<div class="imge afixed">
							<img src="<?php echo (file_exists($sPathModule . '/images/shipping_' . $shipping['id'] . '.png') ? $sPathModule . '/images/shipping_' . $shipping['id'] . '.png' : $sPathModule . '/images/shipping_default.png'); ?>"/>
						</div>
						<div class="infr-wrp mt-0">
							<div class="titu mb-0">
								<b><?php echo is_array($order->info['shipping_method']) ? $order->info['shipping_method'][0] : $order->info['shipping_method']; ?></b>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="chkc-titu2"><?php echo CHECKOUT_CONFIRMATION_RESUMEN_TITLE; ?></div>
		<?php echo $checkoutCart->cart(array(
	    'button_delete_product' => false,
	    'button_delete_wishlist' => false,
	    'button_clean' => false,
	    'button_continue' => false,
		'title' => false)); ?>

		<?php if ($infoPaymentText): ?>
			<div class="chkc-rsmn">
				<div class="col a12 t12 m12">
					<div class="chkc-titu2"><?php echo CHECKOUT_CONFIRMATION_INFO_PAYMENT_TITLE; ?></div>
					<div class="chkc-adrb-wrpr">
						<div class="chkc-adrb">
							<div class="infr payment-text">
								<?php echo $infoPaymentText; ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<?php if (isset($comments) && $comments != ''): ?>
			<div class="chkc-rsmn">
				<div class="col a12 t12 m12">
					<div class="chkc-titu2"><?php echo CHECKOUT_SHIPPING_TITLE_COMMENTS_TRANSPORT; ?></div>
					<div class="chkc-adrb-wrpr">
						<div class="chkc-adrb">
							<div class="infr">
								<?php echo $comments; ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<?php echo tools::includeTemplate($sPathTemplate . '/column.php'); ?>
</form>
