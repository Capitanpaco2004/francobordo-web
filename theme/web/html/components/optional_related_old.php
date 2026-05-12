<?php if( $nProductosTotal > 0 ): ?>
	<div class="cntd">
		<div class="titl actv"><span><?php echo TEXT_OPTIONAL_RELATED; ?></span></div>
		<ul>
			<?php 
				while( $aProducto = eachProducts(false, array(), true) )
				{
					echo '<li>';
						echo '<a class="imge" rel="nofollow" title="' . $aProducto['TITLE'] . '" href="' . $aProducto['HREF'] . '">' . tep_image(DIR_WS_IMAGES . 'productos/' .$aProducto['products_image'], $aProducto['TITLE'], 110, 110, '', false ) . '</a>';
						echo '<div class="cntd-info">';
							echo '<div class="dscrp">' . $aProducto['products_name'] . '</div>';
							echo '<span class="prco">' . $aProducto['ARRAY_PRECIO'][0] . '<span>,' . $aProducto['ARRAY_PRECIO'][1] . '</span></span>';
						echo '</div>';
					echo '</li>';
				}
			?>
		</ul>
	</div>
<?php endif; ?>