<div id="crrt">
	<ul>
		<?php for( $i=0, $n=sizeof($aDatos); $i<$n; $i++ ): ?>
            <li>
                <span><?php echo $aDatos[$i]['quantity'] ?>x</span>
                <a title="<?php echo $aDatos[$i]['name'] ?>" href="<?php echo tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $aDatos[$i]['id']) ?>" class="title"><?php echo $aDatos[$i]['name'] ?></a>
                <a href="<?php echo tep_href_link( 'borrar_carrito.php', 'pId=' . $aDatos[$i]['id'] ); ?>" title="Quitar producto" class="delete">x</a>
            </li>	
        <?php endfor; ?>
    </ul>
    <div id="crrt-ttal">
        <b>TOTAL:</b>
        <span><?php echo $currencies->format($cart->show_total()) ?></span>
    </div>
	<a href="<?php echo tep_href_link(FILENAME_CHECKOUT_SHIPPING) ?>" id="crrt-rlza"><?php echo HEADER_TITLE_CART_CONTENTS; ?></a>
</div>