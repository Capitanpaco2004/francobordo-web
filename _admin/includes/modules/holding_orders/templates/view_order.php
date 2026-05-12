<div class="header">
	<h2><i class="fa fa-shopping-cart"></i> Detalles del Pedido Salvaguardado #<?php echo $order->info['orders_id']; ?>
	</h2>
</div>
<div class="oeWrpr order-details-container">
	<div class="order-summary row">
		<div class="column a04">
			<h3>Cliente</h3>
			<div><?php echo tep_address_format($order->customer['format_id'], $order->customer, 1, '', '<br>'); ?></div>
			<div><strong>Email:</strong>
				<a href="mailto:<?php echo $order->customer['email_address']; ?>"><?php echo $order->customer['email_address']; ?></a>
			</div>
			<div><strong>Teléfono:</strong> <?php echo $order->customer['telephone']; ?></div>
		</div>

		<div class="column a04">
			<h3>Dirección Envío</h3>
			<div><?php echo tep_address_format($order->delivery['format_id'], $order->delivery, 1, '', '<br>'); ?></div>
		</div>

		<div class="column a04">
			<h3>Dirección Facturación</h3>
			<div><?php echo tep_address_format($order->billing['format_id'], $order->billing, 1, '', '<br>'); ?></div>
		</div>
	</div>

	<div class="payment-info">
		<strong>Método de Pago:</strong> <?php echo $order->info['payment_method']; ?>
	</div>

	<table class="xform products-table">
		<thead>
		<tr>
			<th>Producto</th>
			<th>Modelo</th>
			<th class="tright">Cantidad</th>
			<th class="tright">Precio sin IVA</th>
			<th class="tright">Precio con IVA</th>
			<th class="tright">Total con IVA</th>
		</tr>
		</thead>
		<tbody>
		<?php foreach ($order->products as $product): ?>
			<tr>
				<td>
					<?php echo $product['name']; ?>
					<?php if (!empty($product['attributes'])): ?>
						<small>
							<?php foreach ($product['attributes'] as $attr): ?>
								<br>&mdash; <?php echo $attr['option']; ?>: <?php echo $attr['value']; ?>
							<?php endforeach; ?>
						</small>
					<?php endif; ?>
				</td>
				<td><?php echo $product['model']; ?></td>
				<td class="tright"><?php echo $product['qty']; ?></td>
				<td class="tright"><?php echo $currencies->format($product['final_price'], true, $order->info['currency'], $order->info['currency_value']); ?></td>
				<td class="tright"><?php echo $currencies->format(tep_add_tax($product['final_price'], $product['tax']), true, $order->info['currency'], $order->info['currency_value']); ?></td>
				<td class="tright"><?php echo $currencies->format(tep_add_tax($product['final_price'], $product['tax']) * $product['qty'], true, $order->info['currency'], $order->info['currency_value']); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
		<tfoot>
		<?php foreach ($order->totals as $total): ?>
			<tr>
				<td colspan="5" class="tright"><?php echo $total['title']; ?></td>
				<td class="tright"><?php echo $total['text']; ?></td>
			</tr>
		<?php endforeach; ?>
		</tfoot>
	</table>
</div>

<?php if (!empty($order->redsys)): ?>
	<div class="oeWrpr order-details-container">
		<div class="redsys-info">
			<div class="header">
				<img class="redsys-logo" src="<?php echo $sPathModule; ?>/images/logo-redsys.svg" alt="Redsys"/>
			</div>
			<table class="xform">
				<tbody>
				<tr>
					<td><strong>Número Pedido Redsys</strong></td>
					<td><?php echo htmlspecialchars($order->redsys['ds_order']); ?></td>
				</tr>
				<?php if (!empty($order->redsys['ds_response']) || !empty($order->redsys['ds_state'])): ?>
					<tr>
						<td><strong>Respuesta</strong></td>
						<td><?php echo htmlspecialchars($order->redsys['ds_response_msg']) . ' (Código: ' . htmlspecialchars($order->redsys['ds_response']) . ')'; ?></td>
					</tr>
					<tr>
						<td><strong>Estado</strong></td>
						<td><?php echo htmlspecialchars($order->redsys['ds_state_msg']) . ' (Código: ' . htmlspecialchars($order->redsys['ds_state']) . ')'; ?></td>
					</tr>
					<tr>
						<td><strong>Procesado por Redsys</strong></td>
						<td><?php echo $order->redsys['ds_processed_at']; ?></td>
					</tr>
				<?php else: ?>
					<tr>
						<td colspan="2">No hay datos adicionales sobre la transacción.</td>
					</tr>
				<?php endif; ?>
				<tr>
					<td colspan="2">
						Recomendamos revisar en plataforma de Redsys:
						<a href="https://canales.redsys.es" target="_blank">https://canales.redsys.es</a>
					</td>
				</tr>
				</tbody>
			</table>
		</div>
	</div>
<?php endif; ?>
