<div class="box b3">
    <div class="box-top"><?php echo BOX_HEADING_BESTSELLERS; ?></div>
    <div class="box-cntd">
        <ul class="lsta-top">
			<?php
				while( $aProducto = eachProducts() )
					echo '<li ' . (($aProducto['INDEX'] + 1) % 2 == 0 ? 'class="bgg"' : '') . '><i>' . ($aProducto['INDEX'] + 1) . '.</i> <a href="' . tep_href_link( FILENAME_PRODUCT_INFO, 'products_id=' . $aProducto['products_id'] ) . '">' . truncate( $aProducto['products_name'], array( 'SIZE' => 21 ) ) . '</a></li>';
			?>
        </ul>
    </div>
    <div class="box-fotr"></div>
</div>