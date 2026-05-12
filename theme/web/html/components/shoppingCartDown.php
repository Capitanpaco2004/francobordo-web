<?php
	echo '<div id="crrt" class="position-relative csta' . ($nCantidad > 0 ? ' fllc' : '') . '">';
		echo '<div class="icon"><i class="tt tt-33"></i><span class="nmbr">' . $nCantidad . '</span></div>';
		echo '<div class="cntd position-absolute">';
			echo '<div class="scrl">';
				foreach( $aDatos as $aDato )
				{
					echo '<div class="row d-flex align-items-center">';
						echo '<a href="' . tep_href_link( FILENAME_PRODUCT_INFO, 'products_id=' . $aDato['id'] ) . '" class="img">' . tep_image( DIR_WS_IMAGES . 'productos/' . $aDato['image'], $aDato['name'], 65, 65, '', false ) . '</a>';
						echo '<a class="titu" href="' . tep_href_link( FILENAME_PRODUCT_INFO, 'products_id=' . $aDato['id'] ) . '" title="' . $aDato['name'] . '" alt="' . $aDato['name'] . '">' . $aDato['name'] . ' <span>' . $currencies->display_price( $aDato['final_price'], tep_get_tax_rate( $aDato['tax_class_id'] ) ) . '</span></a>';
						echo '<input type="text" value="' . $aDato['quantity'] . '" data-id="' . $aDato['id'] . '" data-buy="1" data-atribute="" data-qty="' . $aDato['quantity'] . '"' . (preg_match( '/checkout|shopping_cart/i', $_SERVER['REQUEST_URI'] ) ? ' readonly="readonly"' : '') . ' />';
						echo (! preg_match( '/checkout|shopping_cart/i', $_SERVER['REQUEST_URI'] ) ? '<i class="fal fa-trash-alt dlte" data-id="' .  $aDato['id'] . '"></i>' : '');
					echo '</div>';
				}
			echo '</div>';

			echo '<div class="bton">';
				echo '<div class="totl"><span>SUBTOTAL:</span> <b>' . $nTotal . '</b></div>';
				echo '<a href="' . tep_href_link( FILENAME_SHOPPING_CART ) . '" title="' . HEADER_TITLE_CART_CONTENTS . '" class="buy">' . HEADER_TITLE_CART_CONTENTS . '</a>';

				$free_shipping = false;

				require_once(DIR_WS_CLASSES . 'order.php');
				$order = new order;
				if( (intval($order->delivery['country_id']) == intval(STORE_COUNTRY) || $order->delivery['country_id'] == 171) && $order->info['subtotal'] >= (getCustomerGroupId() == 0 ? MODULE_SHIPPING_FREEAMOUNT_AMOUNT : MODULE_SHIPPING_FREEAMOUNT_AMOUNT_DISTRI) && MODULE_SHIPPING_FREEAMOUNT_DISPLAY == 'True' && $cart->show_weight() < MODULE_SHIPPING_FREEAMOUNT_WEIGHT_MAX && getProductFreeShippingByGeoZone() )
				{
					if( ($order->delivery['state'] != 'Las Palmas') && ($order->delivery['state'] != 'Ceuta') && ($order->delivery['state'] != 'Melilla') && ($order->delivery['state'] != 'Santa Cruz de Tenerife') )
					{
						$products_ship_free = true;
						$free_shipping = true;
					} else {
						$free_shipping = false;
					}
				}

				if( $free_shipping )
					echo '<div class="text tcenter">' . TEXT_PORTES_CARRITO . '</div>';
			echo '</div>';
		echo '</div>';
	echo '</div>';
?>