<?php
global $currencies;
$affiliate = getAffiliateCustomer(intval($_GET['id']));
?>
<div class="rows">

	<form method="post" enctype="multipart/form-data" id="saveform-send" action="<?php echo tep_href_link('affiliates.php'); ?>" class="oeBox column a12 row ax atop aflex">
		<div class="oeWrpr">
			<div class="oeTitu">
				Edición de usuario
			</div>

			<div class="oeCntd rows sp10 ax xform">

				<p style="width: 100%;" class="row">
					<label>Username</label>
					<?php echo tep_draw_input_field('username_social_networks', $affiliate['username_social_networks'], ' required '); ?>
				</p>

				<p style="width: 100%; display: none;" class="row">
					<label><?php echo tep_draw_checkbox_field('influencer', 1, $affiliate['influencer'] == 1, '', ' style="display: inline-block" '); ?> Influencer (mostrar en Inicio SI o NO)</label>
				</p>

				<p style="width: 100%; display: none;" class="row">
					<?php if ($affiliate['image'] != ''): ?>
						<img style="height: auto; width: 50px;" src="<?php echo '../images/influencers/'.$affiliate['image']; ?>" alt="<?php echo $affiliate['username_social_networks']; ?>">
					<?php endif; ?>

					<label>Imagen</label>
					<?php echo tep_draw_file_field('image'); ?>
				</p>

				<p style="width: 100%;" class="row">
					<label>Comisión</label>
					<?php echo tep_draw_input_field('sales_comission', $affiliate['sales_comission'], ' required '); ?>
					<small style="display: block; margin-top: 10px; opacity: .6;">% de comisión</small>
				</p>

				<p style="width: 100%;" class="row">
					<label>Comisión (EU)</label>
					<?php echo tep_draw_input_field('sales_comission_eu', $affiliate['sales_comission_eu'], ' required '); ?>
					<small style="display: block; margin-top: 10px; opacity: .6;">% de comisión</small>
				</p>

				<p style="width: 100%; display: none;" class="row">
					<label>Tipo de comisión</label>
					<?php echo tep_draw_pull_down_menu('type_comission', getTypeComissionValues(), $affiliate['type_comission']);  ?>
					<small style="display: block; margin-top: 10px; opacity: .6;">% de comisión</small>
				</p>

				<p style="width: 100%;" class="row">
					<label>Cupón</label>
					<?php echo tep_draw_input_field('coupon', $affiliate['coupon'], ' required '); ?>
				</p>
				<p style="width: 100%;" class="row">
					<label>Valor del cupón</label>
					<?php echo tep_draw_input_field('coupon_value', $affiliate['coupon_value'], ' required '); ?>
					<small style="display: block; margin-top: 10px; opacity: .6;">% de descuento</small>
				</p>

				<p style="width: 100%;" class="row">
					<label>NIF</label>
					<?php echo tep_draw_input_field('nif', $affiliate['nif'], ' required '); ?>
				</p>

				<p style="width: 100%;" class="row">
					<label>Teléfono</label>
					<?php echo tep_draw_input_field('telephone', $affiliate['telephone'], ' required '); ?>
				</p>

				<p style="width: 100%;" class="row">
					<label>Enlaces sociales</label>
					<?php echo tep_draw_textarea_field('social_networks_list', 'soft', '60', '5', $affiliate['social_networks_list']); ?>
				</p>

				<p style="width: 100%;">
					<input type="hidden" name="action" value="save-affiliate">
					<input type="hidden" name="id" value="<?php echo intval($_GET['id']); ?>">
				</p>
			</div>
		</div>
	</form>

</div>
