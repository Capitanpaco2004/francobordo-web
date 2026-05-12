<div class="prdt-bner d-flex">
	<div class="web-cntd">
		<div class="wrpr flex-grow-1">
			<div class="titu1 d-flex align-items-center">
				<div class="titu"><?php echo TEXT_NEWS; ?></div>
				<a class="stitu ml-auto" href="<?php echo tep_href_link( FILENAME_PRODUCTS_NEW ); ?>" title="<?php echo TEXT_NEWS; ?>"><?php echo TEXT_VER_NOVEDADES; ?></a>
				<div class="xarrow d-flex"></div>
			</div>

			<div class="prdt-sldr-cntd d-flex" data-column="3">
				<?php
				while( $aProducto = eachProducts() )
					echo _product( array( 'VISTA' => false ) );
				?>
			</div>
		</div>
		<div class="flot flot-new-products">
			<div class="layer"></div>
			<img src="theme/web/images/custom/7.jpg">
		</div>
	</div>
</div>