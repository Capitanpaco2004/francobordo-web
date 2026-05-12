<?php
	require('includes/application_top.php');
	require( DIR_WS_LANGUAGES . $language . '/wishlist.php' );

	$breadcrumb->add( WISHLIST_TITULO, tep_href_link( 'favoritos.php' ) );

	// Obtenemos los productos
	$aInfoWishlist = $dxWishlist->getProductsWishlist();
	$aProductos = $aInfoWishlist['PRODUCTOS'];

	include(DIR_THEME. 'html/header.php');
	include(DIR_THEME. 'html/column_left.php');

	if( $aInfoWishlist['TOTAL'] > 0 ):
?>
	<table id="wlis-tble">
		<thead>
			<tr>
				<td class="imge" width="150"><?php echo WISHLIST_TABLE_IMAGEN; ?></td>
				<td class="name"><?php echo WISHLIST_TABLE_NOMBRE; ?></td>
				<td class="opct"><?php echo WISHLIST_TABLE_OPCION; ?></td>
				<td class="prdct-cant"><?php echo WISHLIST_TABLE_CANTIDAD; ?></td>
				<td class="prce"><?php echo WISHLIST_TABLE_PRECIO; ?></td>
				<td class="actn"><?php echo WISHLIST_TABLE_ACCION; ?></td>
			</tr>
		</thead>
		<tbody>
			<?php while( $aProducto = eachProducts() ): ?>
				<tr class="xprdt<?php echo (($nCont + 1) % 2 == 0 ? ' impr' : ''); ?>">
					<td class="imge img">
						<a href="<?php echo tep_href_link( 'product_info.php', 'products_id=' . $aProducto['products_id'] ); ?>">
							<?php echo tep_image( DIR_WS_IMAGES . 'productos/' . $aProducto['products_image'], $aProducto['products_name'], 130, 101, 'class="img"', false ); ?>
						</a>
					</td>
					<td class="name" data-text="<?php echo WISHLIST_TABLE_NOMBRE; ?>">
						<a href="<?php echo tep_href_link( 'product_info.php', 'products_id=' . $aProducto['products_id'] ); ?>">
							<?php echo $aProducto['products_name']; ?>
						</a>
					</td>
					<td class="opct" data-text="<?php echo WISHLIST_TABLE_OPCION; ?>">
						<?php
							if( $aProducto['atributo'] != '' )
							{
								foreach( $aProducto['atributo'] as $value )
									echo '<span class="attr">' . $value['key'] . ': ' . $value['value'] . '</span><br/>';
							}
							else
								echo '-';
						?>
					</td>
					<td class="cant" data-text="<?php echo WISHLIST_TABLE_CANTIDAD; ?>">
						<input type="text" value="1" data-min="1" name="cart_quantity" class="cart_quantity" />
					</td>
					<td class="prce" data-text="<?php echo WISHLIST_TABLE_PRECIO; ?>">
						<?php if( $aProducto['CLASS_OFERTA'] != '' ): ?>
							<s><?php echo $aProducto['PRECIO_ANTERIOR']; ?></s>
						<?php endif; ?>

						<?php echo $aProducto['PRECIO']; ?>
					</td>
					<td class="actn" data-text="<?php echo WISHLIST_TABLE_ACCION; ?>">
						<a href="javascript:void(0);" data-id="<?php echo $aProducto['products_id']; ?>" data-atributo='<?php echo json_encode( $aProducto['id_atributo'] ); ?>' class="icon icon-dlte"></a>
						<a href="javascript:void(0);" data-id="<?php echo $aProducto['products_id']; ?>" data-atributo='<?php echo json_encode( $aProducto['id_atributo'] ); ?>' class="icon icon-crrt"></a>
					</td>
				</tr>
			<?php endwhile; ?>
		</tbody>
	</table>
	<div class="botonera">
		<a id="bbuyall" class="bton-dflt" href="javascript:void(0);"><?php echo WISHLIST_BOTON_COMPRAR_TODO; ?></a>
	</div>
<?php endif; ?>
<?php
	echo '<div id="msje-whls"' . ($aInfoWishlist['TOTAL'] > 0 ? ' style="display: none"' : '') . '>' . $messageStack->show( array( 'class' => 'wrng', 'text' => WISHLIST_SIN_PRODUCTOS) ) . '</div>';

	include( DIR_THEME. 'html/column_right.php' );
	include( DIR_THEME. 'html/footer.php' );
	include( DIR_WS_INCLUDES . 'application_bottom.php' );
?>