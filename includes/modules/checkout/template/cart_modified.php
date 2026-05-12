<div class="row col a12 ax" id="cart_modified">
	<div class="titu1 col a12"><?php echo str_replace('%COUNT%', $cart->count_contents(), CHECKOUT_CART_TITLE_CONTENT); ?></div>
	<div class="col a12">
		<?php echo $messageStack->show('message_error'); ?>
		<?php echo $messageStack->show(array('text' => CHECKOUT_CART_MODIFIED_TEXT, 'class' => 'warning')); ?>

		<div class="csta">
			<div class="head row aflex ax mhide">
				<div class="col"><?php echo CHECKOUT_CART_TABLE_PRODUCT_PRODUCT; ?></div>
				<div class="col cntd afixed"><?php echo CHECKOUT_CART_TABLE_PRODUCT_QUANTITY; ?></div>
				<div class="col ttal afixed"><?php echo CHECKOUT_CART_TABLE_PRODUCT_TOTAL; ?></div>
			</div>

			<?php foreach ($products as $key => $product): ?>
				<div class="wrpr row aflex ax atop" id="prdt-<?php echo $product['id']; ?>" data-id="<?php echo $product['id']; ?>">
					<a href="<?php echo $product['href']; ?>" class="col imge">
						<?php echo tep_image(DIR_WS_IMAGES . 'productos/' . $product['image'], $product['name'], 103, 103, '', false, false); ?>
					</a>
					<div class="col mddle">
						<a href="<?php echo $product['href']; ?>" class="titl hv8"><span class="qty"><?php echo $product['quantity']; ?>x </span><?php echo $product['name']; ?></a>
						<span class="prco dhide thide"><?php echo $product['price_format']; ?></span>

						<div class="attb">
							<?php if ($product['model'] != ''): ?>
								<p class="ref">Ref: <b><?php echo $product['model']; ?></b></p>
							<?php endif;?>

							<table>
								<?php echo $product['html_attributes']; ?>
							</table>
						</div>
					</div>

					<div class="col cntd afixed">
						<div data-pid="<?php echo $product['id']; ?>" data-id="cart_quantity_<?php echo $product['id']; ?>" data-change="checkoutCartModifiedChangeQuantity" class="xfcant" data-type="increaseDecrease" data-name="cart_quantity" data-value="<?php echo $product['quantity']; ?>"></div>
					</div>
					<div class="col ttal afixed mhide">
						<span class="prco" data-price="<?php echo $product['price']; ?>" data-tax="<?php echo $product['tax_rate']; ?>" data-price-format="<?php echo $product['price_no_format']; ?>"><?php echo $product['price_format']; ?></span>
					</div>

					<div class="btom">
						<a href="javascript:void(0);" data-id="<?php echo $product['id']; ?>" class="hv7 chkc-dlet-prdt"><i class="ick-tt ick-tt-11"></i> Eliminar</a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<div class="fotr clearfix mt-3">
			<div id="cart_modified_confirm" class="xbutton verde mb-0 fright"><?php echo CHECKOUT_CART_MODIFIED_BUTTON; ?></div>
		</div>
	</div>

	<form action="<?php echo tep_href_link('checkout/cart/modified/'); ?>" method="post"><input type="hidden" name="json"/></form>
</div>
