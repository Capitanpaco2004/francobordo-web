<?php

global $currencies;
$data = getAffiliatesStats();

foreach ($data['countries'] as $country) {
    $countries[] = array('id' => $country, 'text' => $country);
}

if (!isset($_GET['country'])) {
	$_GET['country'] = 'España';
}

$totals = [];

?>


<div class="row ax">
    <div class="oeBox column a12 row ax atop">
        <div class="oeWrpr">
            <div class="oeTitu">
                Estadísticas
				<a href="javascript:void(0);" class="show-options"><i class="fa fa-cog" aria-hidden="true"></i></a>
            </div>
            <div class="oeCntd rows sp10 ax xform">
				<div class="options">
					<form method="get" action="<?php echo tep_href_link('affiliates.php'); ?>" style="width: 100%; display: flex;" autocomplete="off">
						<p style="width: 100%; margin-right: 20px;">
							<input type="search" name="affiliate" placeholder="Afiliado" value="<?php echo tep_db_prepare_input($_GET['affiliate']); ?>" list="affiliates-autocomplete" />
							<datalist id="affiliates-autocomplete">
								<?php foreach (getAffiliatesList() as $affiliate): ?>
								<option><?php echo $affiliate; ?></option>
								<?php endforeach; ?>
							</datalist>
						</p>
						<p style="width: 100%; margin-right: 20px;">
							<input type="text" name="date_from" placeholder="Desde" value="<?php echo tep_db_prepare_input($_GET['date_from']); ?>" class="dxdatepicker" />
						</p>

						<p style="width: 100%; margin-right: 20px;">
							<input type="text" name="date_to" placeholder="Hasta" value="<?php echo tep_db_prepare_input($_GET['date_to']); ?>" class="dxdatepicker" />
						</p>

						<p style="width: 100%; margin-right: 20px;">
							<input type="text" name="minimum_order" placeholder="Mínimo" value="<?php echo tep_db_prepare_input($_GET['minimum_order']); ?>" />
						</p>

						<!--<p style="width: 100%; margin-right: 20px;">
							<?php echo tep_draw_pull_down_menu('country', $countries, $_GET['country']); ?>
						</p>-->

						<p style="width: 100%; max-width: 100px;">
							<button type="submit" class="xbutton hv8 small verde" style="width: 100%;">Filtrar</button>
						</p>
						<input type="hidden" name="action" value="stats">

						<p style="width: 100%; max-width: 100px; margin-left: 20px;">
							<?php
							$params = $_GET;
							unset($params['action']);
							$params['action'] = 'stats-download';
							$query_string = http_build_query($params);
							?>
							<a class="xbutton hv8 small"  style="width: 100%;" href="<?php echo tep_href_link('affiliates.php', $query_string); ?>">Descargar</a>
						</p>

					</form>
				</div>


				<?php if (!empty($data)): ?>

					<table class="table-orders" style="width: 100%" cellpadding="0" cellspacing="0">
						<thead>
							<tr style="font-size: 11px;">
								<td>Afiliado</td>
								<?php foreach ($data['countries'] as $country): ?>
									<?php //if ($country == $_GET['country']): ?>
										<td>Total <?php echo $country; ?></td>
									<?php //endif;?>
								<?php endforeach;?>
								<td>Comisión</td>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($data['customers'] as $customer): ?>
								<?php if (!empty($customer['total'])): ?>

									<?php
										$total = 0;
									?>

									<tr class="row-product" style="font-size: 11px;">
										<td class="ellipsis">
											<?php echo $customer['username_social_networks']; ?><br />
											<small><?php echo $customer['customers_email_address']; ?></small>
										</td>
										<?php foreach ($data['countries'] as $country): ?>
											<?php //if ($country == $_GET['country']): ?>

												<td style="text-align: right;">
													<span class="hover">Total:</span>
													<?php if (floatval($customer['total'][$country]['total']) > 0): ?>
														<?php echo $currencies->format(floatval($customer['total'][$country]['total'])); ?><br/>
														<?php $totals[$country]['total'] = $totals[$country]['total'] + floatval($customer['total'][$country]['total']); ?>
													<?php else: ?>
														<br/>
													<?php endif;?>
												</td>

												<?php $total += $customer['total'][$country]['total'];?>

											<?php //endif;?>
										<?php endforeach;?>

										<td style="text-align: right;">
											<span class="hover">Comisión:</span>
											<?php echo $currencies->format(floatval($customer['total'][$country]['comission'])); ?>
											<?php $totals['comission'] = $totals['comission'] + floatval($customer['total'][$country]['comission']); ?>
										</td>

									</tr>
								<?php endif;?>
							<?php endforeach;?>
							<tr class="row-product" style="font-size: 11px;">
								<td class="ellipsis"></td>
								<?php foreach ($data['countries'] as $country): ?>
									<?php //if ($country == $_GET['country']): ?>

										<td style="text-align: right;">
											<span class="hover">Total:</span>
											<strong><?php echo $currencies->format($totals[$country]['total']); ?></strong>
										</td>

									<?php //endif;?>
								<?php endforeach;?>

								<td style="text-align: right;">
									<span class="hover">Comisión:</span>
									<strong><?php echo $currencies->format($totals['comission']); ?></strong>
								</td>
							</tr>
						</tbody>
					</table>

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
		jQuery(document).ready(function()
		{
			jQuery('.show-options').click(function() {
				jQuery('.options').slideToggle(200)
			})
		})
	});
</script>
