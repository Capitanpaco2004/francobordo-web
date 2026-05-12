<h1 class="pageHeading">Opiniones</h1>

<?php while( $aOpinion = tep_db_fetch_array( $aOpiniones ) ): ?>
	<div class="cmtr">
		<span><?php echo $aOpinion['customers_firstname']; ?> <small><?php echo $aOpinion['fecha_envio']; ?></small></span>
		<div class="cmtr-txt">
			<div class="cmtr-ratg cr<?php echo $aOpinion['general']; ?>"></div>
			<?php echo $aOpinion['comentario_general']; ?>
		</div>
	</div>
<?php endwhile; ?>
<br/><br/>
<div class="pgnc"><?php echo $sPaginacion; ?></div>