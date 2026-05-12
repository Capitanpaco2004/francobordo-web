<div id="marcas">
	<div class="d-flex content flex-wrap align-items-center justify-content-center">
		<?php
			while( $aDato = tep_db_fetch_array( $aDatos ) )
				echo '<a href="' . tep_href_link( FILENAME_MANUFACTURERS, 'manufacturers_id=' . $aDato['manufacturers_id'] ) . '" title="' . $aDato['manufacturers_name'] . '">' . ($aDato['manufacturers_image'] != '' && file_exists( 'images/fabricantes/' . $aDato['manufacturers_image'] ) ? tep_image( 'images/fabricantes/' . $aDato['manufacturers_image'], $aDato['manufacturers_name'], 190, 59, '', false ) : $aDato['manufacturers_name']) . '</a>';
		?>
	</div>
	<a href="<?php echo tep_href_link( FILENAME_ALLMANUFACTURERS ); ?>" title="<?php echo TEXT_VER_MARCAS; ?>" class="more"><?php echo TEXT_VER_MARCAS; ?></a>
</div>