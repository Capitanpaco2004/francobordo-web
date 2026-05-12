<?php
	$sScript = basename( $_SERVER['SCRIPT_NAME'] );

	$aBreadCrumbCheckout = array(
		array( 'TEXT' => CHECKOUT_BAR_PAYMENT, 'HREF' => tep_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL') ),
		array( 'TEXT' => CHECKOUT_BAR_CONFIRMATION, 'HREF' => tep_href_link(FILENAME_CHECKOUT_CONFIRMATION, '', 'SSL') ),
		array( 'TEXT' => CHECKOUT_BAR_FINISHED, 'HREF' => 'javascript:void(0);', 'ACTIVE' => ($sScript == 'checkout_success.php' ) )
	);
	
?>

<div id="brcb-chck">
	<?php foreach( $aBreadCrumbCheckout as $key => $value): ?><a class="<?php echo ( $key + 1 == count( $aBreadCrumbCheckout ) ? 'last ' : '' ) . ( ( preg_match( '/' . $sScript . '$/', $value['HREF'] ) || (array_key_exists( 'ACTIVE', $value ) && $value['ACTIVE']) ) ? 'actv' : ''); ?>" href="<?php echo $value['HREF']; ?>"><span><?php echo $key + 1; ?></span><?php echo $value['TEXT']; ?></a><?php endforeach; ?>
</div>