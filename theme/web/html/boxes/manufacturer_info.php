<div class="box b4">
    <div class="box-top"><?php echo BOX_HEADING_MANUFACTURER_INFO; ?></div>
    <div class="box-cntd">
        <?php while( $aDato = tep_db_fetch_array( $aDatos ) ): ?>
            <a href="<?php echo tep_href_link( FILENAME_MANUFACTURERS, 'manufacturers_id=' . $aDato['manufacturers_id'] ); ?>" title="<?php echo $aDato['manufacturers_name']; ?>'">
				<?php echo tep_image( 'images/fabricantes/' . $aDato['manufacturers_image'], $aDato['manufacturers_name'], 64, 64, 'class="box-marca"' ); ?>
            </a>
        <?php endwhile; ?>
    </div>
    <div class="box-fotr"></div>
</div>