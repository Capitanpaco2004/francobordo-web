<div class="box b3 box-slde">
    <a class="box-top" href="<?php echo tep_href_link( 'specials.php' ); ?>"><?php echo BOX_HEADING_SPECIALS; ?></a>
    <div class="box-cntd">
        <a id="ofts-sldr-drch" class="box-slde-drch" href="javascript:void(0);"></a>
        <a id="ofts-sldr-izqd" class="box-slde-izqd" href="javascript:void(0);"></a>
        <div class="box-slde-cntd">
            <div id="ofts-sldr" class="box-slde-slde">
				<?php while( $aProducto = eachProducts() ): ?>
					<?php echo _product_slide_box(); ?>
				<?php endwhile; ?>
            </div>
        </div>
    </div>
    <div class="box-fotr"></div>
</div>