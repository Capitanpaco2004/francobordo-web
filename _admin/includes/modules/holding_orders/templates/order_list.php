<?php

use util\HoldingOrder;

// Verificación de registros
if (tep_db_num_rows($orders_query) <= 0) {
	$filtersActive = [];

	if (!empty($_GET['search'])) {
		$filtersActive[] = 'la Búsqueda <strong>"' . htmlspecialchars($_GET['search']) . '"</strong>';
	}
	if (!empty($_GET['filter_date'])) {
		$date            = date('d/m/Y', strtotime($_GET['filter_date']));
		$filtersActive[] = 'la fecha <strong>' . htmlspecialchars($date) . '</strong>';
	}

	if (!empty($filtersActive)) {
		$filterText = implode(' y ', $filtersActive);
		echo $messageStack->show(['text' => 'No existen pedidos salvaguardados para mostrar para ' . $filterText . '.', 'class' => 'warning']);
	} else {
		echo $messageStack->show(['text' => 'No existen pedidos salvaguardados para mostrar.', 'class' => 'warning']);
	}
}

?>
<div class="oeBox oeTable column a12 row ax">
	<div class="oeWrpr">
		<div class="oeTitu"><i class="fas fa-list"></i> Listado de Pedidos Salvaguardados</div>
		<form action="<?php echo tep_href_link($sUrlPage); ?>" method="get" class="oeCntd oeFiltro row ax">
			<div class="oeBoxFltr column a12 ax row">
				<div class="column a10 row ax">
					<input type="text" name="search" placeholder="Buscar por Nombre, Email, Teléfono, ID pedido..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" class="input-search"  style="flex: 1; margin-right: 5px;"/>
					<input type="date" name="filter_date" value="<?php echo htmlspecialchars($_GET['filter_date'] ?? ''); ?>" class="input-date" style="flex: 0 0 160px; margin-right: 5px;"/>
					<div class="drop" style="margin-top: 6px;">
						<select name="filter_redsys_status" class="input-select">
							<option value="">-- Todos los estados Redsys --</option>
							<option value="authorized" <?php if (($_GET['filter_redsys_status'] ?? '') === 'authorized') echo 'selected'; ?>>
								✅ Autorizados
							</option>
							<option value="invalid" <?php if (($_GET['filter_redsys_status'] ?? '') === 'invalid') echo 'selected'; ?>>
								❌ Invalidados
							</option>
						</select>
					</div>
				</div>
				<div class="column a02 tleft">
					<button type="submit" class="xbutton small verde-turquesa" style="top: 7px">
						<i class="fa fa-filter"></i>
						Filtrar
					</button>
					<?php if (!empty($_GET['search']) || !empty($_GET['filter_date'])): ?>
						<a href="<?php echo tep_href_link($sUrlPage); ?>" class="xbutton small rojo" style="top: 7px"><i class="fa fa-times"></i>
							Reiniciar</a>
					<?php endif; ?>
				</div>
			</div>
		</form>

		<form method="post" action="<?php echo tep_href_link($sUrlPage); ?>" id="form_orders_list" class="oeCntd row ax">
			<table class="xform">
				<thead>
				<tr>
					<th width="20">
						<input type="checkbox" id="all_check"/><label for="all_check"><span></span></label>
					</th>
					<th>Núm.</th>
					<th>Cliente</th>
					<th class="tcenter">Total</th>
					<th class="tcenter">Fecha</th>
					<th class="tcenter">F. Pago</th>
					<th class="tcenter">Estado</th>
					<th class="tcenter">Estado Redsys</th>
					<th>Acciones</th>
				</tr>
				</thead>
				<tbody>
				<?php while ($order = tep_db_fetch_array($orders_query)): ?>
					<tr data-href="<?php echo tep_href_link($sUrlPage, 'action=view_order&ocID=' . $order['orders_id']); ?>">
						<td align="center">
							<input type="checkbox" name="ocID[]" id="check_<?php echo $order['orders_id']; ?>" value="<?php echo $order['orders_id']; ?>"/>
							<label for="check_<?php echo $order['orders_id']; ?>"><span></span></label>
						</td>
						<td align="center">#<?php echo $order['orders_id']; ?></td>
						<td><?php echo $order['customers_name']; ?></td>
						<td align="center"><?php echo strip_tags($order['order_total']); ?></td>
						<td align="center"><?php echo tep_datetime_short($order['date_purchased']); ?></td>
						<td align="center"><?php echo $order['payment_method']; ?></td>
						<td align="center"><?php echo $order['orders_status_name']; ?></td>
						<td align="center">
							<?php
							$redsysDisplay = HoldingOrder::getRedsysResponseDisplay($order['ds_response'], $order['ds_state'], $order['ds_response_msg'], $order['ds_state_msg']);

							$hasRedsysData = !empty($order['ds_response']) || !empty($order['ds_state']) || !empty($order['ds_processed_at']);

							// HTML del tooltip con validaciones
							$tooltipContent = "<strong>Información Transacción Redsys:</strong><br/>";
							$tooltipContent .= "Número Pedido Redsys: " . (!empty($order['ds_order']) ? htmlspecialchars($order['ds_order']) : '-') . "<br/>";

							if ($hasRedsysData) {
								if (!empty($order['ds_response']) || !empty($order['ds_response_msg'])) {
									$tooltipContent .= "Respuesta: " . htmlspecialchars($order['ds_response_msg'] ?? '-') . " (Código: " . htmlspecialchars($order['ds_response'] ?? '-') . ")<br/>";
								}

								if (!empty($order['ds_state']) || !empty($order['ds_state_msg'])) {
									$tooltipContent .= "Estado: " . htmlspecialchars($order['ds_state_msg'] ?? '-') . " (Código: " . htmlspecialchars($order['ds_state'] ?? '-') . ")<br/>";
								}

								if (!empty($order['ds_processed_at']) && $order['ds_processed_at'] !== '0000-00-00 00:00:00') {
									$tooltipContent .= "Procesado por Redsys: " . date('d/m/Y H:i:s', strtotime($order['ds_processed_at'])) . "<br/>";
								}
							} else {
								$tooltipContent .= "<em>No hay datos sobre el pedido en Redsys.</em><br/>";
							}

							$tooltipContent .= "<br/>Recomendamos revisar en plataforma de Redsys: <a href='https://canales.redsys.es/' target='_blank'>https://canales.redsys.es</a>";
							?>
							<span class="status-pill <?php echo $redsysDisplay['color']; ?>" data-tippy-content="<?php echo htmlspecialchars($tooltipContent); ?>">
						<?php echo $redsysDisplay['message']; ?>
					</span>
						</td>
						<td>
							<div class="drop xfselect">
								<div>Acciones</div>
								<ul class="down down-dngt">
									<li>
										<a href="<?php echo tep_href_link($sUrlPage, 'action=view_order&ocID=' . $order['orders_id']); ?>" class="hv"><i class="fas fa-eye"></i>Ver
											detalles</a></li>
									<li>
										<a data-confirm="¿Realmente deseas mover a Pedido el registro?" href="<?php echo tep_href_link($sUrlPage, 'action=move&&ocID=' . $order['orders_id']); ?>" class="hv"><i class="fas fa-cart-plus"></i>Mover
											a pedido</a></li>
									<li>
										<a data-confirm="¿Realmente deseas eliminar el registro?" href="<?php echo tep_href_link($sUrlPage, 'action=delete&&ocID=' . $order['orders_id']); ?>" class="hv"><i class="fas fa-trash-alt"></i>Eliminar
											registro</a></li>
								</ul>
							</div>
						</td>
					</tr>
				<?php endwhile; ?>
				</tbody>
			</table>
			<?php
			// Añadimos acciones masivas:
			$sHtmlActionMasivo = '
			<label class="column afluid">Acciones masivas:&nbsp;&nbsp;</label>
			<div class="column afluid">
				<div class="drop masv xfselect">
					<div>Seleccionar acción</div>
					<ul class="down drch">
						<li><a data-question="¿Mover los pedidos seleccionados a pedidos confirmados?" data-action="' . tep_href_link($sUrlPage, 'action=move') . '" href="javascript:void(0);" class="hv"><i class="fa fa-cart-plus"></i> Mover a pedidos</a></li>
						<li><a data-question="¿Eliminar los pedidos seleccionados?" data-action="' . tep_href_link($sUrlPage, 'action=delete') . '" href="javascript:void(0);" class="hv"><i class="fa fa-trash-alt"></i> Eliminar pedidos</a></li>
					</ul>
				</div>
			</div>&nbsp; - &nbsp;';

			echo $orders_split->showPaginateTable(tep_get_all_get_params(['page', 'action', 'ocID']), 'page', $sHtmlActionMasivo, 'solenopsis');
			?>
		</form>
	</div>
</div>
