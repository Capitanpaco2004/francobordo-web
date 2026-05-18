<?php

use util\tools;

if (isset($_SESSION['module_shipping_estimator'])) {
    require_once DIR_WS_CLASSES . 'shipping.php';
    $shipping_modules = new \shipping;
    $shipping = $shipping_modules->select($_SESSION['module_shipping_estimator']);
}

?>
<form id="checkout_form" action="<?php echo tep_href_link(FILENAME_CHECKOUT_SHIPPING . 'process/'); ?>" method="post" class="col chkc-right">
	<div id="chkt-bton" class="checkout_form_target xbutton verde hv9 expand bton-cntn dhide thide"><?php echo CHECKOUT_CONTINUE; ?></div>

	<?php echo $messageStack->show('message_error'); ?>

	<?php if (is_array($quotes) && sizeof($quotes) > 0 && sizeof($quotes[0]) > 0): ?>

		<div class="chkc-titu2"><?php echo CHECKOUT_SHIPPING_TITLE_SELECT; ?></div>

		<div class="chkc-mthh-wrpr" data-shipping-method>
			<?php foreach ($quotes as $aQuote): ?>
				<?php foreach ($aQuote['methods'] as $aMethod): ?>
					<?php $checked = (($aQuote['id'] . '_' . $aMethod['id'] == $shipping['id'] || $aQuote['id'] == $shipping['id']) ? true : false);?>
					<?php $sId = $aQuote['id'] . '_' . $aMethod['id'];?>
					<div class="chkc-mthh ax mx row aflex mflex <?php echo ($checked ? 'actv' : ''); ?>" data-method>
						<?php if (!isset($aQuote['error'])): ?>
							<div class="prco afixed" data-price><?php echo $currencies->format(tep_add_tax($aMethod['cost'], isset($aQuote['tax']) ? $aQuote['tax'] : 0)); ?></div>
							<div class="inpt xform afixed">
								<?php echo tep_draw_radio_field('shipping', $sId, $checked, 'id="' . $sId . '"'); ?><label for="<?php echo $sId; ?>"><span></span></label>
							</div>
						<?php endif; ?>
						<div class="imge afixed">
							<img src="<?php echo (isset($aQuote['icon']) && file_exists($sPathModule . '/images/' . $aQuote['icon']) ? $sPathModule . '/images/' . $aQuote['icon'] : $sPathModule . '/images/shipping_default.png'); ?>"/>
						</div>
						<div class="infr-wrp mfixed <?php echo $aMethod['id']  == 'retira' ? 'zindex' : ''; ?>">
							<div class="titu">
								<b data-title><?php echo $aQuote['module']; ?></b><br/>
							</div>
							<div data-info class="infr">
								<?php if (!isset($aQuote['error'])): ?>
									<?php echo $aMethod['title']; ?>
								<?php else: ?>
									<?php echo $messageStack->show(array('class' => 'eror', 'text' => $aQuote['error'])); ?>
								<?php endif; ?>

								<?php 
									// Inicio, select con las tiendas
									if ($aMethod['id']  == 'retira') {
															
										echo '<div class="selct">';
										echo '<label>' . TEXT_SELECT_STORE . '</label>';

										// La tienda de Denia (CP 03700) solo se ofrece si el cliente tiene
										// alguna direccion en ese CP (gate por libreta de direcciones).
										$bDeniaAllowed = false;
										if (isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] > 0) {
											$rDenia = tep_db_query("SELECT 1 FROM " . TABLE_ADDRESS_BOOK . " WHERE customers_id = '" . (int)$_SESSION['customer_id'] . "' AND entry_postcode LIKE '03700%' LIMIT 1");
											if (tep_db_num_rows($rDenia) > 0) {
												$bDeniaAllowed = true;
											}
										}

										// Consultamos las tiendas (excluyendo Denia si no procede)
										$sStoreSql = 'SELECT id_store, store_name, store_address, store_cost FROM store WHERE store_status = 1';
										if (!$bDeniaAllowed) {
											$sStoreSql .= " AND store_address NOT LIKE '%03700%'";
										}
										$sStoreSql .= ' ORDER BY store_name DESC';
										$aDatos = tep_db_query($sStoreSql);

                                        echo '<select name="store_id" class="shipping-change-store">';

										while ($aDato = tep_db_fetch_array($aDatos)) {
                                            $store_cost = $currencies->format(tep_add_tax($aDato['store_cost'], tep_get_tax_rate(1)));
                                            echo '<option data-price="' . $store_cost . '" value="' . $aDato['id_store'] . '" '. ($aDato['id_store'] == $store_id ? ' selected ' : '') . '>'  . $aDato['store_name'] . ' (' . $aDato['store_address'] . ')</option>';
										}

                                        echo '</select>';
                                        echo '</div>';

									}
								?>
							</div>
						</div>
					</div>
				<?php endforeach;?>
			<?php endforeach;?>
		</div>

		<?php $checked = !isset($_SESSION['choose_insurance']) || (isset($_SESSION['choose_insurance']) && (int)$_SESSION['choose_insurance'] == 1); ?>
		<?php echo tep_draw_checkbox_field('choose_insurance', "1",  $checked, ' id="choose_insurance" style="display: none;" '); ?>

	<?php else: ?>
		<?php echo $messageStack->show(array('class' => 'warning', 'text' => CHECKOUT_ERROR_SHIPPING_MESSAGE)); ?>
	<?php endif;?>

	<div class="chkc-titu2"><?php echo CHECKOUT_SHIPPING_TITLE_ADDRESS; ?> <a href="#chkc-shpg-slct" class="fright mgp-inln"><i class="dhide fas fa-sync-alt chkc-chge"></i><span class="thide mhide"><?php echo CHECKOUT_SHIPPING_SELECT_ADDRESS; ?></span></a></div>
	<div class="chkc-adrb-wrpr">
		<div class="chkc-adrb">
			<b class="titu"><?php echo $sAddressTitle; ?> <a href="<?php echo tep_href_link(FILENAME_ADDRESS_BOOK_PROCESS, 'edit=' . $sendto); ?>" class="edit">(<?php echo CHECKOUT_EDIT; ?>)</a></b>
			<div class="infr" id="address-shipping-text">
				<?php echo $sAddress; ?>
			</div>
		</div>
		<div class="fotr tright">
			<a <?php echo (tep_count_customer_address_book_entries() >= MAX_ADDRESS_BOOK_ENTRIES ? 'data-alert="' . str_replace('%MAX%', MAX_ADDRESS_BOOK_ENTRIES, CHECKOUT_MAX_ADDRESS) . '" data-alert-icon="warning"' : ''); ?> href="<?php echo tep_href_link(FILENAME_ADDRESS_BOOK_PROCESS); ?>" class="addb"><?php echo CHECKOUT_ADD_ADDRESS; ?> <i class="fa fa-plus"></i></a>
		</div>
	</div>

	<ul class="xaccordion chkc-text" data-accordion>
		<li class="xaccordion-item <?php echo (isset($comments) && $comments != '' ? 'actv' : ''); ?>" data-accordion-item>
			<div class="xaccordion-title" data-accordion-link>3. <?php echo CHECKOUT_SHIPPING_TITLE_COMMENTS_TRANSPORT; ?></div>
			<div class="xaccordion-content" data-accordion-content>
				<div class="text"><?php echo CHECKOUT_SHIPPING_TITLE_COMMENTS_TRANSPORT_TEXT; ?></div>
				<div class="xform"><?php echo tep_draw_textarea_field('comments', 'soft', '60', '5', isset($comments) ? $comments : ''); ?></div>
			</div>
		</li>
	</ul>
</form>


<?php echo $boxCheckout->addressBook($sendto, CHECKOUT_SHIPPING_SELECT_ADDRESS, tep_href_link(FILENAME_CHECKOUT_SHIPPING . 'select_address/')); ?>

<?php echo tools::includeTemplate($sPathTemplate . '/column.php'); ?>
