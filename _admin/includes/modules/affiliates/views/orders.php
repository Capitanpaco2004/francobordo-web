<?php

global $currencies;
$affiliate = getAffiliateCustomer(intval($_GET['id']));
?>
<div class="rows">

	<form method="post" action="<?php echo tep_href_link('affiliates.php'); ?>" class="oeBox column a12 row ax atop aflex" id="pending">
		<div class="oeWrpr">
			<div class="oeTitu">
				Gestión de pedidos sin procesar
			</div>

			<div class="oeCntd rows sp10 ax xform">
				<?php $orders = getOrdersFromAffiliate(intval($_GET['id']), 'pending'); ?>
				<?php if (!empty($orders)): ?>
				<table class="table-orders" style="width: 100%" cellpadding="0" cellspacing="0">
					<thead>
						<tr>
							<td width="30"><input style="display: inline-block;" type="checkbox" class="aff-chk-all" /></td>
							<td style="text-align: center;">ID Pedido</td>
							<td style="text-align: center;">Total pedido</td>
							<td style="text-align: center;">Total comisión</td>
							<td width="50"></td>
						</tr>
					</thead>
					<tbody>

					<?php
						$allTotal = 0;
						$allTotalComission = 0;
					?>

					<?php foreach($orders as $orders_id => $total): ?>
						<tr>
							<td width="30"><?php echo tep_draw_checkbox_field('orders_id[]', $orders_id, false, '', ' style="display: inline-block;" '); ?></td>
							<td style="text-align: center;"><strong><a target="_blank" href="<?php echo tep_href_link('orders.php', 'oID='.$orders_id.'&action=edit'); ?>"><?php echo $orders_id; ?></a></td>
							<td style="text-align: center;">
								<?php
								echo $currencies->format($total["total"]);
								$allTotal += $total["total"]; ?>
							</td>
							<td style="text-align: center;">
								<?php
								$comission = floatval($total["comision"]);
								$allTotalComission += $comission;
								echo $currencies->format($comission);
								?>
							</td>
							<td width="50"><a class="xbutton hv8 small verde" href="<?php echo tep_href_link('affiliates.php', 'action=order-process&orders_id=' . $orders_id . '&status=prepared&id=' . intval($_GET['id'])); ?>">Procesar</a></td>
						</tr>
					<?php endforeach; ?>
					</tbody>

					<tr>
						<td width="30"></td>
						<td style="text-align: center;"><strong>Total:</strong></td>
						<td style="text-align: center;"><strong><?php echo $currencies->format($allTotal); ?></strong></td>
						<td style="text-align: center;"><strong><?php echo $currencies->format($allTotalComission); ?></strong></td>
						<td width="50"></td>
					</tr>

				</table>
				<p style="width: 100%;">
					<button class="xbutton hv8 small verde">Procesar pedidos</button>
					<input type="hidden" name="action" value="order-process">
					<input type="hidden" name="status" value="prepared">
					<input type="hidden" name="id" value="<?php echo intval($_GET['id']); ?>">
				</p>
				<?php else: ?>
					<p>No hay pedidos para procesar</p>
				<?php endif; ?>
			</div>
		</div>
	</form>


	<form method="post" action="<?php echo tep_href_link('affiliates.php'); ?>" class="oeBox column a12 row ax atop aflex" id="prepared">
		<div class="oeWrpr">
			<div class="oeTitu">
				Gestión de pedidos procesados
			</div>

			<div class="oeCntd rows sp10 ax xform">

				<?php
					$allTotal = 0;
					$allTotalComission = 0;
				?>

				<?php $orders = getOrdersFromAffiliate(intval($_GET['id']), 'prepared'); ?>
				<?php if (!empty($orders)): ?>
				<table class="table-orders" style="width: 100%" cellpadding="0" cellspacing="0">
					<thead>
						<tr>
							<td width="30"><input style="display: inline-block;" type="checkbox" class="aff-chk-all" /></td>
							<td style="text-align: center;">ID Pedido</td>
							<td style="text-align: center;">Total pedido</td>
							<td style="text-align: center;">Total comisión</td>
							<td width="50"></td>
						</tr>
					</thead>
					<tbody>

					<?php foreach($orders as $orders_id => $total): ?>
						<tr>
							<td width="30"><?php echo tep_draw_checkbox_field('orders_id[]', $orders_id, false, '', ' style="display: inline-block;" '); ?></td>
							<td style="text-align: center;"><strong><a target="_blank" href="<?php echo tep_href_link('orders.php', 'oID='.$orders_id.'&action=edit'); ?>"><?php echo $orders_id; ?></a></td>
							<td style="text-align: center;">
								<?php
								echo $currencies->format($total["total"]);
								$allTotal += $total["total"]; ?>
							</td>
							<td style="text-align: center;">
								<?php
								$comission = floatval($total["comision"]);
								$allTotalComission += $comission;
								echo $currencies->format($comission);
								?>
							</td>
							<td width="50"><a class="xbutton hv8 small rojo" href="<?php echo tep_href_link('affiliates.php', 'action=order-process&orders_id=' . $orders_id . '&status=pending&id=' . intval($_GET['id'])); ?>">Quitar</a></td>
						</tr>
					<?php endforeach; ?>

					<tr>
						<td width="30"></td>
						<td style="text-align: center;"><strong>Total:</strong></td>
						<td style="text-align: center;"><strong><?php echo $currencies->format($allTotal); ?></strong></td>
						<td style="text-align: center;"><strong><?php echo $currencies->format($allTotalComission); ?></strong></td>
						<td width="50"></td>
					</tr>

					</tbody>
				</table>

				<p style="width: 100%;">
					<button class="xbutton hv8 small rojo">Quitar pedidos</button>
					<input type="hidden" name="action" value="order-process">
					<input type="hidden" name="status" value="pending">
					<input type="hidden" name="id" value="<?php echo intval($_GET['id']); ?>">
				</p>
				<?php else: ?>
					<p>No hay pedidos para procesar</p>
				<?php endif; ?>
			</div>
		</div>
	</form>

</div>
