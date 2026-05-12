<div id="chkc-shpg-slct" class="wind-mdal zoom-anim-dialog mfp-hide">
	<div class="chkc-titu2"><?php echo $title; ?></div>
	<form method="post" action="<?php echo $urlAction; ?>" class="chkc-mthh-wrpr">
		<?php foreach ($addressBook as $aAddress): ?>
			<div data-submit="true" class="chkc-mthh ax mx row aflex mflex amiddle <?php echo $aAddress['active'] ? 'actv' : ''; ?>">
				<div class="inpt xform afixed">
					<?php echo tep_draw_radio_field('address', $aAddress['address_book_id'], $aAddress['active'], 'id="' . $aAddress['address_book_id'] . '"'); ?><label for="<?php echo $aAddress['address_book_id']; ?>"><span></span></label>
				</div>
				<div class="infr-wrp mt-0">
					<div class="titu mb-0">
						<?php echo $aAddress['address_format']; ?>
					</div>
				</div>
				<a href="<?php echo tep_href_link(FILENAME_ADDRESS_BOOK_PROCESS, 'edit=' . $aAddress['address_book_id']); ?>" class="edit afixed"><?php echo CHECKOUT_EDIT; ?></a>
			</div>
		<?php endforeach;?>
	</form>
</div>
