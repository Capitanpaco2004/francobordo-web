<?php

global $currencies;

$data = getAffiliatesCustomers(
    intval($_GET['status']),
    strval($_GET['email']),
    strval($_GET['username'])
);

?>


<div class="row ax">
    <div class="oeBox column a12 row ax atop">
        <div class="oeWrpr">
            <div class="oeTitu">
                Afiliados

				<img class="cog" alt="" src="theme/web/images/icons/usual/icon-cog3.png">

            </div>
            <div class="oeCntd rows sp10 ax xform">
				<form method="get" action="<?php echo tep_href_link('affiliates.php'); ?>" style="width: 100%; <?php echo count($_GET) > 3 ? 'display: flex;' : 'display: none;'; ?>" class="filters">
					<p style="width: 100%; margin-right: 20px;">
						<?php echo tep_draw_pull_down_menu('status', getStatusAffiliates(), $_GET['status']); ?>
					</p>
					<p style="width: 100%; margin-right: 20px;">
						<input type="search" name="email" placeholder="E-mail" value="<?php echo tep_db_prepare_input($_GET['email']); ?>" />
					</p>
					<p style="width: 100%; margin-right: 20px;">
						<input type="search" name="username" placeholder="Username" value="<?php echo tep_db_prepare_input($_GET['username']); ?>" />
					</p>
					<p style="width: 100%; margin-right: 20px; max-width: 100px;">
						<input type="text" name="per_page" placeholder="Por página" value="<?php echo tep_db_prepare_input($_GET['per_page']); ?>" />
						<span style="font-size: 9px;">Elementos por página</span>
					</p>
					<p style="width: 100%; max-width: 100px;">
						<button type="submit" class="xbutton hv8 small" style="width: 100%;">Filtrar</button>
					</p>
					<?php if (count($_GET) > 3): ?>
					<p style="width: 100%; max-width: 100px;margin-left: 10px;">
						<a class="xbutton hv8 small rojo" style="width: 100%;" href="<?php echo tep_href_link('affiliates.php'); ?>">Limpiar filtro</a>
					</p>
					<?php endif; ?>
				</form>

				<?php if (!empty($data['customers'])): ?>

					<?php echo $data['paginate']->showPaginateTable(tep_get_all_get_params(array('page', 'info', 'x', 'y', 'cID'))); ?>

					<form method="POST" style="max-width: 100%; width: 100%;">

						<table class="table-orders" style="width: 100%" cellpadding="0" cellspacing="0">
							<thead>
								<tr style="font-size: 11px;">
									<td width="30"><input type="checkbox" id="select-all" style="display: block;" /></td>
									<td width="30"></td>
									<td>Datos</td>
									<td>Comisión</td>
									<td>Valor cupón</td>
									<td>Cupón</td>
									<td>Estado</td>
									<td>Fecha creación</td>
									<td>Fecha de activación</td>
									<td>Pendiente</td>
									<td width="140"></td>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($data['customers'] as $customer): ?>
									<tr class="row-product" style="font-size: 11px;">
										<td width="30">
											<input type="checkbox" name="id[<?php echo $customer['id']; ?>]" value="<?php echo $customer['coupon']; ?>" style="display: block;" />
										</td>
										<td width="30"><?php echo $customer['id']; ?></td>
										
										<td title="<?php echo $customer['username_social_networks']; ?>">
											<p style="display: flex;margin: 0;">
												<strong style="margin-right: 5px;">Username:</strong>
												<span class="ellipsis" title="<?php echo $customer['username_social_networks']; ?>"><?php echo $customer['username_social_networks']; ?></span>
											</p>
											<p style="display: flex;margin: 0;">
												<strong style="margin-right: 5px;">Nombre y apellidos:</strong>
												<span class="ellipsis" title="<?php echo $customer['customers_firstname']; ?> <?php echo $customer['customers_lastname']; ?>"><?php echo $customer['customers_firstname']; ?> <?php echo $customer['customers_lastname']; ?></span>
											</p>
											<p style="display: flex;margin: 0;">
												<strong style="margin-right: 5px;">E-Mail:</strong>
												<span class="ellipsis" title="<?php echo $customer['customers_email_address']; ?>"><?php echo $customer['customers_email_address']; ?></span>
											</p>
										</td>

										<td>
											General: <strong><?php echo sprintf('%01.2f %%', $customer['sales_comission']); ?></strong><br />
											EU: <strong><?php echo sprintf('%01.2f %%', $customer['sales_comission_eu']); ?></strong><!--<br />
											Tipo: <strong><?php echo $customer['type_comission']; ?></strong><br />-->
										</td>
										<td><?php echo $customer['coupon_value']; ?>%</td>
										<td><pre style="border: 1px solid #ccc; border-radius: 3px; padding: 3px; text-align: center; font-size: 11px;"><?php echo $customer['coupon']; ?></pre></td>
										<td width="45">
											<?php if ($customer['affiliate_active'] == 1): ?>
												<?php echo tep_image(DIR_WS_IMAGES . 'icon_status_green.png', IMAGE_ICON_STATUS_GREEN, 10, 10); ?>
												<a href="<?php echo tep_href_link('affiliates.php', 'action=set-status&id=' . $customer['id'] . '&status=0'); ?>"><?php echo tep_image(DIR_WS_IMAGES . 'icon_status_red_light.png', IMAGE_ICON_STATUS_GREEN, 10, 10); ?></a>
											<?php else: ?>
												<a href="<?php echo tep_href_link('affiliates.php', 'action=set-status&id=' . $customer['id'] . '&status=1'); ?>"><?php echo tep_image(DIR_WS_IMAGES . 'icon_status_green_light.png', IMAGE_ICON_STATUS_GREEN, 10, 10); ?></a>
												<?php echo tep_image(DIR_WS_IMAGES . 'icon_status_red.png', IMAGE_ICON_STATUS_GREEN, 10, 10); ?>
											<?php endif; ?>
										</td>
										<td><?php echo $customer['date_created']; ?></td>
										<td><?php echo $customer['activation_date']; ?></td>
										<td>
											<pre><?php echo $currencies->format(getOrdersTotalFromAffiliate($customer['id'], floatval($customer['sales_comission']), 'prepared')); ?></pre>
										</td>
										<td>
											

											<div style="display: inline-block; margin-bottom: -4px;" class="btn-group">
												<a href="#" data-toggle="dropdown" class="buttonS bDefault">Acciones<span class="caret"></span></a>
												<ul class="dropdown-menu">
													<li><a  href="<?php echo tep_href_link('affiliates.php', 'action=history&id=' . $customer['id']); ?>">Historial de pagos</a></li>
													<li><a  href="<?php echo tep_href_link('affiliates.php', 'action=orders&id=' . $customer['id'] . '&status='.intval($_GET['status'])); ?>">Detalles</a></li>
													<li><a href="<?php echo tep_href_link('affiliates.php', 'action=view&id=' . $customer['id'] . '&status='.intval($_GET['status'])); ?>">Editar</a></li>
													<li><a data-confirm="¿Realmente deseas eliminar el registro? Se borrarán todos los datos referentes al afiliado" href="<?php echo tep_href_link('affiliates.php', 'action=delete-affiliate&id=' . $customer['id'] .'&status='.intval($_GET['status'])); ?>">Borrar</a></li>
												</ul>
											</div>

										</td>
									</tr>

								<?php endforeach;?>
							</tbody>
						</table>
						<input type="hidden" name="action" value="bulk-save">

						<div class="toolbar" style="width: 100%; display: flex;">
							<!--<img alt="" src="theme/web/images/icons/usual/icon-cog3.png">-->
							<p style="width: 100%; margin-right: 20px;">
								<?php echo tep_draw_pull_down_menu('task', getActionsForAffiliates(), $_GET['status']); ?>
							</p>
							<p style="width: 100%; margin-right: 20px;">
								<input type="text" name="value" placeholder="Nuevo valor" value="<?php echo tep_db_prepare_input($_GET['email']); ?>" />
							</p>
							<p style="width: 100%; max-width: 240px;">
								<button type="submit" class="xbutton hv8 small" style="width: 100%;">Actualizar elementos seleccionados</button>
							</p>
						</div>

					</form>

					<?php echo $data['paginate']->showPaginateTable(tep_get_all_get_params(array('page', 'info', 'x', 'y', 'cID'))); ?>
                <?php else: ?>
                <div class="xmessage xmessage-warning">
					<div>
						<i class="fa fa-exclamation-triangle"></i>
						Vaya... no hay datos para mostrar
					</div>
				</div>
                <?php endif;?>
            </div>
        </div>
    </div>
</div>


<script>
	document.addEventListener("DOMContentLoaded", function(event) {
		jQuery(document).ready(function() {

			jQuery('#select-all').change(function() {
				jQuery('.row-product input[type=checkbox]').prop('checked', jQuery(this).prop('checked'))
			})

			jQuery('.oeTitu img').click(function() {
				if(!jQuery('.filters').is(':visible')) {
					jQuery(".filters").css({"display": "flex"});
				} else {
					jQuery(".filters").css({"display": "none"});
				};
			})

		})
	});
</script>
