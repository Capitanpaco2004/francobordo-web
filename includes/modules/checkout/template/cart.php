<?php if ($cart->count_contents() > 0): ?>
	<?php if ($cart->any_out_of_stock): ?>
		<?php echo $messageStack->show(array('class' => 'eror', 'text' => str_replace('%MARK_OUT_OF_STOCK%', STOCK_MARK_PRODUCT_OUT_OF_STOCK, STOCK_ALLOW_CHECKOUT == 'true' ? OUT_OF_STOCK_CAN_CHECKOUT : OUT_OF_STOCK_CANT_CHECKOUT))); ?>
	<?php endif;?>

	<div class="csta">
		<?php if ($title): ?>
			<div class="head row aflex ax mhide">
				<div class="col"><?php echo CHECKOUT_CART_TABLE_PRODUCT_PRODUCT; ?></div>
				<div class="col cntd afixed"><?php echo CHECKOUT_CART_TABLE_PRODUCT_QUANTITY; ?></div>
				<div class="col ttal afixed"><?php echo CHECKOUT_CART_TABLE_PRODUCT_TOTAL; ?></div>
			</div>
		<?php endif;?>
		<?php foreach ($aProducts as $aProduct): ?>
			<div class="wrpr row aflex ax atop">
				<a href="<?php echo $aProduct['href']; ?>" class="col imge">
					<?php echo tep_image(DIR_WS_IMAGES . 'productos/' . $aProduct['image'], $aProduct['name'], 103, 103, '', false); ?>
				</a>
				<div class="col mddle">
					<a href="<?php echo $aProduct['href']; ?>" class="titl hv8"><span class="qty"><?php echo $aProduct['quantity']; ?>x </span><?php echo $aProduct['name']; ?></a>
					<span class="prco dhide thide"><?php echo $aProduct['price_format']; ?></span>

					<div class="attb">
						<?php if ($aProduct['any_out_of_stock']): ?>
							<p><b class="trojo"><?php echo STOCK_MARK_PRODUCT_OUT_OF_STOCK . ' ' . OUT_OF_STOCK_TEXT; ?></b></p>
						<?php endif;?>

						<?php if ($aProduct['model'] != ''): ?>
							<p>Ref: <b><?php echo $aProduct['model']; ?></b></p>
						<?php endif;?>
					</div>

					<?php $bMore = count($aProduct['attributes_info']) > 3 ? true : false;?>
					<div class="<?php echo $bMore ? 'xmore' : ''; ?> attb">
						<?php echo $bMore ? '<input type="checkbox"/>' : ''; ?>
						<div>
							<?php foreach ($aProduct['attributes_info'] as $aAttribute): ?>
								<?php echo '<p>' . $aAttribute['products_options_name'] . ': <b> ' . $aAttribute['products_options_values_name'] . '</b></p>'; ?>
							<?php endforeach;?>
						</div>
						<?php echo $bMore ? '<span><span>' . CHECKOUT_SEE_MORE . '</span><span>' . CHECKOUT_SEE_LESS . '</span></span>' : ''; ?>
					</div>
				</div>
				<div class="col cntd afixed">
					<div data-pid="<?php echo $aProduct['id']; ?>" data-id="qty_<?php echo $aProduct['id']; ?>" data-change="checkoutCartChangeQuantity" class="xfcant" data-type="increaseDecrease" data-name="cart_quantity" data-value="<?php echo $aProduct['quantity']; ?>"></div>
				</div>
				<div class="col ttal afixed mhide">
					<span class="prco"><?php echo $aProduct['price_format']; ?></span>
				</div>
				<?php if ($buttonWishlistProduct || $buttonDeleteProduct): ?>
					<div class="btom">
						<?php if ($buttonWishlistProduct): ?>
							<a href="javascript:void(0);" <?php echo (isset($aProduct['wishlist_attributes']) ? 'data-info="true" data-combination="' . $aProduct['wishlist_attributes'] . '"' : ''); ?> data-id="<?php echo $aProduct['id']; ?>" class="chkc-add-whlt hv7 <?php echo $aProduct['wishlist'] ? 'actv' : ''; ?>"><i class="ick-tt ick-tt-7"></i> <?php echo '<span>' . CHECKOUT_CART_REMOVE_WISHLIST . '</span><span>' . CHECKOUT_CART_ADD_WISHLIST . '</span>' ?></a>
							<span class="line"></span>
						<?php endif;?>
						<?php if ($buttonDeleteProduct): ?>
							<a href="javascript:void(0);" data-id="<?php echo $aProduct['id']; ?>" class="hv7 chkc-dlet-prdt"><i class="ick-tt ick-tt-11"></i> Eliminar</a>
						<?php endif;?>
					</div>
				<?php endif;?>
			</div>
		<?php endforeach;?>
	</div>

	<?php if ($buttonClean || $buttonContinue): ?>
		<div id="chkc-fotr" class="col a12 afixed mhide">
			<?php if ($buttonClean): ?>
				<p class="clcs"><a class="hv8" href="<?php echo tep_href_link(FILENAME_SHOPPING_CART . 'clean/'); ?>" data-confirm="<?php echo CHECKOUT_CART_CLEAN_QUESTION; ?>"><i class="ick-tt ick-tt-11"></i> <?php echo CHECKOUT_CART_CLEAN; ?></a></p>
			<?php endif;?>
			<?php if ($buttonContinue): ?>
				<p class="cntn"><a class="hv8" href="<?php echo $navigation->get_last_page(); ?>"><i class="ick-tt ick-tt-12"></i> <?php echo CHECKOUT_CART_CONTINUE_BUY; ?></a></p>
			<?php endif;?>
		</div>
	<?php endif;?>
<?php else: ?>
	<?php echo $messageStack->show(array('class' => 'wrng', 'text' => CHECKOUT_CART_EMPTY)); ?>
	<div class="tright">
		<a href="<?php echo tep_href_link(FILENAME_DEFAULT); ?>" class="xbutton verde hv9"><?php echo IMAGE_BUTTON_CONTINUE; ?></a>
	</div>
<?php endif;?>
