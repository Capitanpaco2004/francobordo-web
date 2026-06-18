<?php

// Mensajes comprobamos si tenemos datos
if( tep_db_num_rows( $aRows ) <= 0 ) {
	echo $messageStack->show( array( 'text' => 'No existe ningun log.', 'class' => 'warning' ) );
}

?>

<div class="oeBox oeTable column a12 row ax">
	<div class="oeWrpr">
		<div class="oeTitu"><i class="fas fa-clock"></i> Logs</div>
		<form method="post" action="<?php echo tep_href_link( "log.php" ); ?>" class="oeCntd row ax">
			<table class="xform">
				<thead>
				<tr>
					<th>Nombre del cliente</th>
					<th>Fecha</th>
					<th>Numero de pedidos total</th>
					<th>Valor total de los pedidos</th>
				</tr>
				</thead>
				<tbody>

				<?php while( $aRow = tep_db_fetch_array( $aRows ) ): ?>
					<tr>
						<td><?php echo '<a href="'.tep_href_link("customers.php?page=1&cID={$aRow['customer_id']}&action=edit").'">'.$aRow['customers_firstname'].' '.$aRow['customers_lastname'].'</a>'; ?></td>
						<td><?php echo $aRow['date']; ?></td>
						<td><?php echo $aRow['numero_pedidos']; ?></td>
						<td><?php echo $aRow['total_values']??'-'; ?></td>
					</tr>
				<?php endwhile; ?>

				</tbody>
			</table>

			<?php echo $aRowsSplit->showPaginateTable( tep_get_all_get_params( array('page') ), 'page', $sHtmlActionMasivo ?? '', 'solenopsis' ); ?>
		</form>
	</div>
</div>
