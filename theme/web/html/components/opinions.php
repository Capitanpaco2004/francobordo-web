<div class="bx_opin">
	<a class="opinions_link" href="<?php echo tep_href_link("opiniones.php")?> ">
		<div class="bx_info">
			<div class="google_img">
				<?php echo tep_image('theme/web/images/general/google_circle.png','','','');?>
			</div>
			<div class="star st<?php echo $nRating; ?>"><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i></div>

			<div class="bx_puntuacion">
				<div class="bx_exc"><?php echo OPINIONS_TEXT_EXCELENT ?></div>
				<div class="bx_nta"><?php echo $nPorcentaje; ?> / 5</div>
			</div>
			<div class="bx_cant"><?php echo sprintf(OPINIONS_TEXT_BASED_COMMENTS, $nCantidad) ?></div>
		</div>
	</a>
	<div class="bx_list slick-opinions">
		<?php
		while( $aSqlOpinion = tep_db_fetch_array( $aSqlOpinions ) ) {
			$stars = '<i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i></div>';
			echo '<div class="bx_cmnt">';
			echo '<div class="bx_name"><span>' . preg_replace('/(\s*)([^\s]*)(.*)/', '$2', $aSqlOpinion['customers_name'] ?? '') . '</span><div class="star st' . $aSqlOpinion['general'] . '">' .$stars. '</div>';
			echo '<p class="bx_text">' . truncate( $aSqlOpinion['comentario_general'], array( 'SIZE' => 180 ) ) . '</p>';
			echo '<div class="star star_mobile st' . $aSqlOpinion['general'] . '">' .$stars;
			echo '</div>';
		}
		?>
	</div>
</div>
