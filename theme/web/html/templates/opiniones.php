<h1 class="pageHeading">Opiniones</h1>

<?php while( $aOpinion = tep_db_fetch_array( $aOpiniones ) ): ?>
	<div class="cmtr">
		<span><?php echo tep_output_string_protected($aOpinion['customers_firstname']); ?> <small><?php echo tep_output_string_protected($aOpinion['fecha_envio']); ?></small></span>
		<div class="cmtr-txt">
			<div class="cmtr-ratg cr<?php echo (int)$aOpinion['general']; ?>"></div>
			<?php echo tep_output_string_protected($aOpinion['comentario_general']); ?>
		</div>
	</div>
<?php endwhile; ?>
<br/><br/>
<div class="pgnc"><?php echo $sPaginacion; ?></div>