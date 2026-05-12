<div class="ovrflw">
	<div class="titu titu-gris"><?php echo UPCOMING_PRODUCTS_TITLE; ?></div>
	<div class="list-products">
	<?php
		while( $aProducto = eachProducts() )
			echo _product( array( 'CLASS' => 'prdct-vrtl', 'VISTA' => false ) );
	?>
	</div>
</div>