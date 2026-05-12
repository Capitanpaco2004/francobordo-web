<?php

global $currencies;
$affiliate = getAffiliateCustomer(intval($_GET['id']));
$canProcess = false;
?>
<div class="rows">

	<form method="post" action="<?php echo tep_href_link('affiliates.php'); ?>" class="oeBox column a12 row ax atop aflex" id="pending">
		<div class="oeWrpr">
			<div class="oeTitu">
				Historial de pagos
			</div>

			<div class="oeCntd rows sp10 ax xform">
				<?php $history = getHistoryFromAffiliate(intval($_GET['id'])); ?>
				<?php if (!empty($history)): ?>
				<table class="table-orders" style="width: 100%" cellpadding="0" cellspacing="0">
					<thead>
						<tr>
							<td width="30"></td>
							<td style="text-align: center;">ID</td>
							<td style="text-align: center;">Total comisión</td>
							<td style="text-align: center;">Estado</td>
							<td style="text-align: center;">Fecha de solicitud</td>
							<td style="text-align: center;">Tipo</td>
							<td width="50"></td>
						</tr>
					</thead>
					<tbody>

					<?php foreach($history as $id => $order): ?>
						<tr>
							<td width="30">
								<?php if ($order['status'] != 'completed'): ?>
								<?php echo tep_draw_checkbox_field('id_history[]', $id, false, '', ' style="display: inline-block;" '); ?>
								<?php endif; ?>
							</td>
							<td style="text-align: center;"><strong>#<?php echo str_pad($order['id'], 5, "0", STR_PAD_LEFT); ?></td>
							<td style="text-align: center;"><?php echo $currencies->format($order['total']); ?></td>
							<td style="text-align: center;"><?php echo $order['status']; ?></td>
							<td style="text-align: center;"><?php echo date('d/m/Y H:i:s', strtotime($order['date_created'])); ?></td>
							<td style="text-align: center;">
								
									<?php 
									switch ($order['type']) {
										case 'invoice':
										echo '<span style="background-color: #104add;color: #fff;padding: 2px 8px;font-size: 11px;border-radius: 3px;">Factura</span>';
											break;
										
										default:
										echo '<span style="background-color: #167417;color: #fff;padding: 2px 8px;font-size: 11px;border-radius: 3px;">Puntos</span>';
											break;
									}
									
									?>
								
							</td>
							<td width="50">
								<?php if ($order['status'] != 'completed'): ?>
								<?php $canProcess = true; ?>
								<a class="xbutton hv8 small verde" href="<?php echo tep_href_link('affiliates.php', 'action=history-process&id_history=' . $id . '&status=completed&id=' . intval($_GET['id'])); ?>">Marcar completado</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ($canProcess): ?>
					<p style="width: 100%;">
						<button class="xbutton hv8 small verde">Marcar seleccionados como completados</button>
						<input type="hidden" name="action" value="history-process">
						<input type="hidden" name="status" value="completed">
						<input type="hidden" name="id" value="<?php echo intval($_GET['id']); ?>">
					</p>
				<?php endif; ?>
				<?php else: ?>
					<p>No se ha encontrado ningún pago pendiente</p>
				<?php endif; ?>
			</div>
		</div>
	</form>


</div>
