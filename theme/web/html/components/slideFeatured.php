<div id="dstc">
	<div id="dstc-top"></div>

	<div id="dstc-izqd"></div>
	<div id="dstc-drch"></div>

	<div id="dstc-lnea">
		<div id="dstc-cntd">
			<div id="dstc-slde">
				<?php while( $aProducto = eachProducts() ): ?>
					<div class="dstc-sldr-cntd <?php echo $aProducto['CLASS_ENVIO'] . ' ' . $aProducto['CLASS_OFERTA']; ?>">
						<?php 
							// Iconos envio y oferta
							echo ($aProducto['CLASS_OFERTA'] != '' ? '<div class="icon-ofrt"></div>' : ''); 
							echo ($aProducto['CLASS_ENVIO'] != '' ? '<div class="icon-envo"></div>' : '');
						?>

						<h3><a class="prdct-title" href="<?php echo $aProducto['HREF']; ?>" title="<?php echo $aProducto['TITLE']; ?>"><?php echo $aProducto['products_name']; ?></a></h3>
						
						<div class="prdct-dscp">
							<?php echo truncate( $aProducto['products_description'], array( 'SIZE' => '350', 'CLEAR' => true ) ); ?>
						</div>

						<a class="prdct-img" href="<?php echo $aProducto['HREF']; ?>" title="<?php echo $aProducto['TITLE']; ?>"><?php echo tep_image(DIR_WS_IMAGES . 'productos/' .$aProducto['products_image'], $aProducto['TITLE'], 210, 210 ); ?></a>

						<div class="prdct-prco">
							<div class="prco prco-b">
								<?php echo getPrecioImagen( $aProducto ) . ' <s>' . $aProducto['PRECIO_ANTERIOR'] . '</s>'; ?>	
							</div>
						</div>
						
						<a class="prdct-cmpr" title="Comprar <?php echo $aProducto['TITLE']; ?>" href="<?php echo tep_href_link( FILENAME_DEFAULT, tep_get_all_get_params( array('action') ) . 'action=buy_now&products_id=' . $aProducto["products_id"] ); ?>">Comprar <?php echo $aProducto['products_name']; ?></a>
					</div>
				<?php endwhile; ?>
			</div>
		</div>
	</div>
	<div id="dstc-botm"></div>
	<div id="dstc-pgnd"></div>
</div>