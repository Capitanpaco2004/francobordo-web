<div class="box b3 box-slde">
    <a class="box-top" href="<?php echo tep_href_link( 'products_new.php' ); ?>"><?php echo BOX_HEADING_WHATS_NEW; ?></a>
    <div class="box-cntd">
        <a id="nvds-sldr-drch" class="box-slde-drch" href="javascript:void(0);"></a>
        <a id="nvds-sldr-izqd" class="box-slde-izqd" href="javascript:void(0);"></a>
        <div class="box-slde-cntd">
            <div id="nvds-sldr" class="box-slde-slde">
				<?php while( $aProducto = eachProducts() ): ?>
					<?php echo _product_slide_box(); ?>
				<?php endwhile; ?>
            </div>
        </div>
    </div>
    <div class="box-fotr"></div>
</div>