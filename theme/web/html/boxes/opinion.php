<a href="<?php echo tep_href_link( 'opiniones.php' ); ?>" id="box-opnion">
	<div class="opnion-box-titl">
		<b><?php echo round( ($nTotalPuntos * 5) / ($nCantidad * 5), 1 ); ?></b> / 5 de <br/> <b><?php echo $nCantidad; ?></b> opiniones
	</div>

	<div class="opnion-star-bg" style="height: <?php echo $nPorcentaje; ?>px"></div>
	<div class="opnion-star"></div>
</a>