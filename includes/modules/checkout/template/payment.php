<?php use util\tools;?>

<style>
	/* Icono Apple Pay / Google Pay (redsys_xpay): la imagen es apaisada (240x112, dos
	   logos). La regla por defecto pone width:60px → solo ~28px de alto, se ve pequeno.
	   Le damos mas ancho para que iguale la altura (~56px) del resto de iconos. */
	.chkc-mthh.redsys_xpay .imge { width: 155px !important; }
	.chkc-mthh.redsys_xpay .imge img { height: 40px !important; width: auto !important; max-width: 150px !important; }
	/* Separacion entre los logos y el texto "Apple Pay / Google Pay" */
	.chkc-mthh.redsys_xpay .infr-wrp { margin-left: 16px !important; }
</style>

<form id="checkout_form" action="<?php echo tep_href_link(FILENAME_CHECKOUT_PAYMENT . 'process/'); ?>" method="post" class="col chkc-right">
	<div id="chkt-bton" class="checkout_form_target xbutton verde hv9 expand bton-cntn dhide thide"><?php echo CHECKOUT_CONTINUE; ?></div>

	<?php if (isset($_GET['payment_error']) && is_object(${$_GET['payment_error']}) && ($error = ${$_GET['payment_error']}->get_error())): ?>
		<?php echo $messageStack->show(array('class' => 'eror', 'text' => tep_output_string_protected($error['title']) . '<br/>' . tep_output_string_protected($error['error']))); ?>
	<?php endif;?>

	<?php echo $messageStack->show('message_error'); ?>

	<?php if (is_array($quotes) && sizeof($quotes) > 0 && sizeof($quotes[0]) > 0): ?>
		<div class="chkc-titu2"><?php echo CHECKOUT_PAYMENT_TITLE_SELECT; ?></div>

		<div class="chkc-mthh-wrpr chkc-mthh-wrpr-pymt" data-payment-method>
			<?php foreach ($quotes as $aQuote): ?>
				<?php if (isset($aQuote['error'])): ?>
					<?php echo $messageStack->show(array('class' => 'eror', 'text' => $aQuote['error'])); ?>
				<?php endif;?>

				<?php $checked = (($aQuote['id'] == $payment) ? true : false);?>

				<div class="chkc-mthh ax mx row aflex mflex amiddle <?php echo $aQuote['id'] . ($checked ? ' actv' : ''); ?>" data-method>
					<div class="inpt xform afixed">
						<?php echo tep_draw_radio_field('payment', $aQuote['id'], $checked, 'id="' . $aQuote['id'] . '"'); ?><label for="<?php echo $aQuote['id']; ?>"><span></span></label>
					</div>
					<div class="imge afixed">
						<img src="<?php echo (file_exists($sPathModule . '/images/payment_' . $aQuote['id'] . '.png') ? $sPathModule . '/images/payment_' . $aQuote['id'] . '.png' : $sPathModule . '/images/payment_default.png'); ?>"/>
					</div>
					<div class="infr-wrp mt-0">
						<div class="titu mb-0">
							<b data-title><?php echo $aQuote['module']; ?></b><br/>
						</div>
					</div>
				</div>
			<?php endforeach;?>

			<?php echo tep_draw_checkbox_field('customer_shopping_points_spending', $customer_shopping_points_spending, false, ' id="customer_shopping_points_spending" style="display: none;" ');  ?>
		</div>

	<?php else: ?>
		<?php echo $messageStack->show(array('class' => 'warning', 'text' => CHECKOUT_ERROR_PAYMENT_MESSAGE)); ?>
	<?php endif;?>

	<div class="chkc-titu2"><?php echo CHECKOUT_PAYMENT_TITLE_ADDRESS; ?> <a href="#chkc-shpg-slct" class="fright mgp-inln"><i class="dhide fas fa-sync-alt chkc-chge"></i><span class="thide mhide"><?php echo CHECKOUT_PAYMENT_SELECT_ADDRESS; ?></span></a></div>
	<div class="chkc-adrb-wrpr">
		<div class="chkc-adrb">
			<b class="titu"><?php echo $sAddressTitle; ?> <a href="<?php echo tep_href_link(FILENAME_ADDRESS_BOOK_PROCESS, 'edit=' . $billto); ?>" class="edit">(<?php echo CHECKOUT_EDIT; ?>)</a></b>
			<div class="infr">
				<?php echo $sAddress; ?>
			</div>
		</div>
		<div class="fotr tright">
			<a <?php echo (tep_count_customer_address_book_entries() >= MAX_ADDRESS_BOOK_ENTRIES ? 'data-alert="' . str_replace('%MAX%', MAX_ADDRESS_BOOK_ENTRIES, CHECKOUT_MAX_ADDRESS) . '" data-alert-icon="warning"' : ''); ?> href="<?php echo tep_href_link(FILENAME_ADDRESS_BOOK_PROCESS); ?>" class="addb"><?php echo CHECKOUT_ADD_ADDRESS; ?><i class="fa fa-plus"></i></a>
		</div>
	</div>

	<?php echo $sHtmlPoint; ?>
</form>

<?php echo $boxCheckout->addressBook($billto, CHECKOUT_PAYMENT_SELECT_ADDRESS, tep_href_link(FILENAME_CHECKOUT_PAYMENT . 'select_address/')); ?>

<?php echo $payment_modules->javascript_validation(); ?>

<?php echo tools::includeTemplate($sPathTemplate . '/column.php'); ?>
