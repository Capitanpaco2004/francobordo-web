<?php

global $currencies;

$brands = [];
$allBrands = getBrandsForExclude();
if (isset($_GET['search'])) {
    $brands = getBrandsForExclude($_GET['search']);
}

$brandsExcluded = getBrandsExcluded();

?>
<div class="rows">

	<div class="oeBox column a12 row ax atop aflex">
		<div class="oeWrpr">
			<div class="oeTitu">
				Excluir marcas para cupones
			</div>

			<div class="oeCntd rows sp10 ax xform">

				<form method="get" action="<?php echo tep_href_link('affiliates.php'); ?>" style="width: 100%; display: flex;" class="filters">

					<p style="width: 100%; margin-right: 20px;">
						<input type="search" name="search" placeholder="Buscar marca" value="<?php echo tep_db_prepare_input($_GET['search']); ?>" />
					</p>

					<p style="width: 100%; max-width: 100px;">
						<button type="submit" class="xbutton hv8 small" style="width: 100%;">Filtrar</button>
						<input type="hidden" name="action" value="brands">
					</p>
				</form>

				<?php if (!empty($brands)): ?>
					<table class="table-orders" style="width: 100%" cellpadding="0" cellspacing="0">
						<thead>
							<tr>
								<td style="text-align: center;">Marca</td>
								<td width="100"></td>
							</tr>
						</thead>
						<tbody>
						<?php foreach ($brands as $id => $name): ?>

							<tr>
								<td style="text-align: left;"><?php echo $name; ?></td>
								<td width="100">
									<?php if (in_array($id, $brandsExcluded)): ?>
										<a class="xbutton hv8 small rojo" href="<?php echo tep_href_link('affiliates.php', 'action=save-brands&type=remove&id=' . $id); ?>">Quitar</a>
									<?php else: ?>
										<a class="xbutton hv8 small verde" href="<?php echo tep_href_link('affiliates.php', 'action=save-brands&type=add&id=' . $id); ?>">Añadir</a>
									<?php endif;?>
								</td>
							</tr>

						<?php endforeach;?>
						</tbody>
					</table>
				<?php endif;?>

			</div>
		</div>
	</div>

	<?php if (!empty($brandsExcluded)): ?>
	<div class="oeBox column a12 row ax atop aflex">
		<div class="oeWrpr">
			<div class="oeTitu">
				Marcas excluidas
			</div>

			<div class="oeCntd rows sp10 ax xform">
				<table class="table-orders" style="width: 100%" cellpadding="0" cellspacing="0">
					<thead>
						<tr>
							<td style="text-align: center;">Marca</td>
							<td width="100"></td>
						</tr>
					</thead>
					<tbody>
					<?php foreach ($brandsExcluded as $id): ?>

						<tr>
							<td style="text-align: left;"><?php echo $allBrands[$id]; ?></td>
							<td width="100">
								<a class="xbutton hv8 small rojo" href="<?php echo tep_href_link('affiliates.php', 'action=save-brands&type=remove&id=' . $id); ?>">Quitar</a>
							</td>
						</tr>

					<?php endforeach;?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
	<?php endif;?>

</div>
