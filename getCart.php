<?php
/*
	This is the PHP backend file for the AJAX Driven shopping cart.

	You may use this code in your own projects as long as this copyright is left
	in place.  All code is provided AS-IS.
	This code is distributed in the hope that it will be useful,
 	but WITHOUT ANY WARRANTY; without even the implied warranty of
 	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

	Copyright 2005 Eliot Rayner / ersd.net.
*/

require("includes/application_top.php");
// make sure we set the right character set
header('Content-type: text/html; charset='.CHARSET);

@include(DIR_WS_LANGUAGES . $language . '/' . FILENAME_SHOPPING_CART);
if (!defined('HEADING_TITLE')) @include(DIR_WS_LANGUAGES . $language . '/shopping_cart.php');

//Check to ensure the user is in the Shoping Cart.
if(!isset($_GET['Cart'])) {
	echo "<b>Not in Cart session !! </b></br>";
} else {

// Check to see if item should be removed
if(isset($_POST['cart_delete']) && $_POST['cart_delete'] != '' && isset($_POST['products_id']) && $_POST['products_id'] != '') {
	// customer wants to remove the product from their shopping cart
	$cart->remove($_POST['products_id']);
}

//Check to see if a ChangeQty was sent.
if(isset($_POST['quantity']) && $_POST['quantity'] != '' && isset($_POST['products_id']) && $_POST['products_id'] != '') {
	// customer wants to update the product quantity in their shopping cart

	// customer wants to update the product quantity in their shopping cart
  // attributes are working now - update by Kavita Aggarwal
          $prid = $_POST['products_id'];
	      $attributes = explode('{', substr($prid, strpos($prid, '{')+1));

          for ($i=0, $n=sizeof($attributes); $i<$n; $i++) {
            $pair = explode('}', $attributes[$i]);

            if (is_numeric($pair[0]) && is_numeric($pair[1])) {
              $_POST['id'][$pair[0]] .= $pair[1];
            }
          }

	$cart->add_cart($_POST['products_id'], $_POST['quantity'], $_POST['id'], false);
	// attributes are working now - update by Kavita Aggarwal
}

    $info_box_contents = array();

	$info_box_contents[0][] = array('params' => 'class="imge"',
                                    'text' => TABLE_HEADING_IMAGE);

    $info_box_contents[0][] = array('params' => 'class="name"',
                                    'text' => TABLE_HEADING_PRODUCTS);

    $info_box_contents[0][] = array('params' => 'class="prdct-cant"',
                                    'text' => TABLE_HEADING_QUANTITY);

    $info_box_contents[0][] = array('params' => 'class="prce"',
                                    'text' => TABLE_HEADING_TOTAL);

	$info_box_contents[0][] = array('params' => 'class="actn"',
                                    'text' => TABLE_HEADING_REMOVE);

    $any_out_of_stock = 0;

	$products = $cart->get_products();

	for ($i=0, $n=sizeof($products); $i<$n; $i++) {
// Push all attributes information in an array
      if (isset($products[$i]['attributes']) && is_array($products[$i]['attributes'])) {
		foreach( $products[$i]['attributes'] as $option => $value ) {
          echo tep_draw_hidden_field('id[' . $products[$i]['id'] . '][' . $option . ']', $value);
          $attributes = tep_db_query("select pa.reference, popt.products_options_name, popt.products_options_track_stock, poval.products_options_values_name, pa.options_values_price, pa.price_prefix
                                      from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                                      where pa.products_id = '" . (int)$products[$i]['id'] . "'
                                       and pa.options_id = '" . (int)$option . "'
                                       and pa.options_id = popt.products_options_id
                                       and pa.options_values_id = '" . (int)$value . "'
                                       and pa.options_values_id = poval.products_options_values_id
                                       and popt.language_id = '" . (int)$languages_id . "'
                                       and poval.language_id = '" . (int)$languages_id . "'");
          $attributes_values = tep_db_fetch_array($attributes);

          $products[$i][$option]['products_options_name'] = $attributes_values['products_options_name'];
          $products[$i][$option]['options_values_id'] = $value;
          $products[$i][$option]['products_options_values_name'] = $attributes_values['products_options_values_name'];
          $products[$i][$option]['options_values_price'] = $attributes_values['options_values_price'];
          $products[$i][$option]['price_prefix'] = $attributes_values['price_prefix'];
		  $products[$i][$option]['track_stock'] = $attributes_values['products_options_track_stock'];
		  $products[$i][$option]['reference'] = $attributes_values['reference'];
        }
      }
    }

    for ($i=0, $n=sizeof($products); $i<$n; $i++) {
      $info_box_contents[] = array();

      $cur_row = sizeof($info_box_contents) - 1;

      $info_box_contents[$cur_row][] = array('params' => 'class="imge" width="150"',
                                             'text' => tep_image(DIR_WS_IMAGES . 'productos/' . $products[$i]['image'], $products[$i]['name'], 130, 101, '', false, false));

      $products_name = '<a href="' . tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $products[$i]['id']) . '"><strong>' . $products[$i]['name'] . '</strong></a><br />
	  ';

      if (STOCK_CHECK == 'true') {
        if (isset($products[$i]['attributes']) && is_array($products[$i]['attributes'])) {
          $stock_check = tep_check_stock($products[$i]['id'], $products[$i]['quantity'], $products[$i]['attributes']);
        }else{
		$stock_check = tep_check_stock($products[$i]['id'], $products[$i]['quantity']);
		}
        if (tep_not_null($stock_check)) {
          $any_out_of_stock = 1;

          $products_name .= $stock_check;
        }
      }

	  $referenciaAtriMostrada = false;
      if (isset($products[$i]['attributes']) && is_array($products[$i]['attributes'])) {
        foreach( $products[$i]['attributes'] as $option => $value ) {
			if ($products[$i][$option]['reference']!='') {
  			  $products_name .= '<i><strong>- Modelo:</strong> ' . $products[$i][$option]['reference'] . '</i><br />';
  			  $referenciaAtriMostrada = true;
  		  } else {
			$products_name .= '<i><strong>- ' . $products[$i][$option]['products_options_name'] . ':</strong> ' . $products[$i][$option]['products_options_values_name'] . '</i><br />';
		  }



        }
      }

	  if( $products[$i]['model'] != '' && $referenciaAtriMostrada == false )
		$products_name .= '<i><strong>- Modelo:</strong>' . $products[$i]['model'] . '</i><br>';


		// Mostramos las promociones aplicadas
		if( isset( $cart->contents[$products[$i]['id']] ) && isset( $cart->contents[$products[$i]['id']]['promotion'] ) )
		{
			$sPromo = '';
			foreach( $cart->contents[$products[$i]['id']]['promotion'] as $aPromotion )
			{
				if( isset( $aPromotion['qty'] ) && $aPromotion['qty'] > 0 )
					$sPromo .= $aPromotion['qty'] . ' ud' . ($aPromotion['qty'] == 1 ? '' : 's') . ($aPromotion['type'] == 'percent' ? '. al <b>' . $aPromotion['discount'] . '% dto.' : '. con <b>' . $aPromotion['discount'] . ' € de dto.') . '</b>, ';
			}

			if( $sPromo != '' )
				$products_name .= '<p>' . substr( $sPromo, 0, -2 ) . '</p>';
		}

		// Obtenemos la cantidad de productos del carrito y si queremos controlar el stock
		$aStock = tep_db_query( 'SELECT products_quantity, check_stock FROM ' . TABLE_PRODUCTS . ' WHERE products_id = "' . $products[$i]['id'] . '";' );
		$aStock = tep_db_fetch_array( $aStock );


		// Control de stock POR VARIANTE (OR con el global)
		if (!(int)$aStock['check_stock'] && isset($products[$i]['attributes']) && is_array($products[$i]['attributes']) && function_exists('fb_variant_check_stock'))
			$aStock['check_stock'] = fb_variant_check_stock($products[$i]['id'], $products[$i]['attributes'], 0);

		// Si NO queremos controlar el stock
		if( $aStock['check_stock'] == 0 )
		{
			// Si SI tenemos atributos
			if( isset( $products[$i]['attributes'] ) && is_array( $products[$i]['attributes'] ) && count( $products[$i]['attributes'] ) > 0 )
			{
				// Variables
				$sAttributes = '';

				// Obtenemos los atributos del producto
				foreach( $products[$i]['attributes'] as $nAttribute => $aAttribute )
					$sAttributes .= $nAttribute . '-' . $aAttribute . ',';
				$sAttributes = substr( $sAttributes, 0, -1 );

				// Obtenemos el stock del producto
				$aAux = tep_db_query( 'SELECT products_stock_quantity FROM products_stock WHERE products_id = "' . $products[$i]['id'] . '" AND products_stock_attributes = "' . $sAttributes . '";' );

				// Si tenemos registro
				if( tep_db_num_rows( $aAux ) > 0 )
				{
					// Registro
					$aAux = tep_db_fetch_array( $aAux );

					// Si superamos el número de stock
					if( $products[$i]['quantity'] > $aAux['products_stock_quantity'] )
					{
						// Obtenemos la cantidad del stock
						$nQty = $products[$i]['quantity'] - $aAux['products_stock_quantity'];

						// Añadimos la línea informativa
						if( $aAux['products_stock_quantity'] > 0 )
							$products_name .= '<i style="color: #e77200;">- <b>Usted ha solicitado ' . $products[$i]['quantity']  . ' unidad' . ($products[$i]['quantity'] == 1 ? '' : 'es') . ' y solo queda' . ($aAux['products_stock_quantity'] == 1 ? '' : 'n') . ' ' . $aAux['products_stock_quantity'] . ' en stock.<br />La' . ($nQty == 1 ? '' : 's') . ' ' . $nQty . ' unidad' . ($nQty == 1 ? '' : 'es') . ' que falta' . ($nQty == 1 ? '' : 'n') . ' estará' . ($nQty == 1 ? '' : 'n') . ' disponible' . ($nQty == 1 ? '' : 's') . '<br />en un plazo de 7 - 10 días laborables.</strong></b><br />';
					}
				}
			}
			// Si NO tenemos atributos
			else
			{
				// Si superamos el número de stock
				if( $products[$i]['quantity'] > $aStock['products_quantity'] )
				{
					// Obtenemos la cantidad del stock
					$nQty = $products[$i]['quantity'] - $aStock['products_quantity'];

					// Añadimos la línea informativa
					if( $aStock['products_quantity'] > 0 )
						$products_name .= '<i style="color: #e77200;">- <b>Usted ha solicitado ' . $products[$i]['quantity']  . ' unidad' . ($products[$i]['quantity'] == 1 ? '' : 'es') . ' y solo queda' . ($aStock['products_quantity'] == 1 ? '' : 'n') . ' ' . $aStock['products_quantity'] . ' en stock.<br />La' . ($nQty == 1 ? '' : 's') . ' ' . $nQty . ' unidad' . ($nQty == 1 ? '' : 'es') . ' que falta' . ($nQty == 1 ? '' : 'n') . ' estará' . ($nQty == 1 ? '' : 'n') . ' disponible' . ($nQty == 1 ? '' : 's') . '<br />en un plazo de 7 - 10 días laborables.</strong></b><br />';
				}
			}
		}


      $info_box_contents[$cur_row][] = array('params' => 'class="name"',
                                             'text' => $products_name,
											 'data-text' => TABLE_HEADING_PRODUCTS);

	 $info_box_contents[$cur_row][] = array('params' => 'class="cant"',
											 'text' => tep_draw_input_field('cart_quantity[]', $products[$i]['quantity'], 'size="4" onKeyPress="if((event.keyCode==10)||(event.keyCode==13)) this.blur();" onChange="sendCartChangeQty(\'' . $products[$i]['id'] . '\', \'getCart.php\',\'' . tep_session_name() . '=' . tep_session_id() . '\',\'span_cart\', \' <img src=theme/'.THEME.'/images/general/loading_sc.gif alt=loading> Por favor, espere...<br/>\', \'qty_' . $products[$i]['id'] . '\', \'' . $products[$i]['id'] . '\', 0);" id="qty_'.$products[$i]['id'].'"') . tep_draw_hidden_field('products_id[]', $products[$i]['id']),
											 'data-text' => TABLE_HEADING_QUANTITY);


      $info_box_contents[$cur_row][] = array('params' => 'class="prce"',
                                             'text' => '<b>' . $currencies->display_price($products[$i]['final_price'], tep_get_tax_rate($products[$i]['tax_class_id']), $products[$i]['quantity']) . '</b>',
											 'data-text' => TABLE_HEADING_TOTAL);
	
	  $info_box_contents[$cur_row][] = array('params' => 'class="actn"',
									'text' => '<img src="theme/'.THEME.'/images/general/borrar.gif" class="clicable" onClick="sendCartRemoveItem(\'' . $products[$i]['id'] . '\', \'getCart.php\',\'' . tep_session_name() . '=' . tep_session_id() . '\',\'span_cart\', \' <img src=theme/'.THEME.'/images/general/loading_sc.gif alt=loading> Por favor, espera...\', \'rem_' . $products[$i]['id'] . '\', \'' . $products[$i]['id'] . '\');"onclick="this.blur();" id="rem_'.$products[$i]['id'].'" />',
											 'data-text' => TABLE_HEADING_REMOVE);
	}

	$info_box_contents[] = array('align' => 'right',
								 'params' => 'colspan="5" class="carrito_total"',
								 'text' => SUB_TITLE_SUB_TOTAL . '<strong> '.$currencies->format($cart->show_total()).'</strong>');

	$nDescuentoPromo = 0;
	if( $customer_group_id == 0 )
	{
		$nDescuentoPromo = $cart->descuento_promo();
		if( $nDescuentoPromo > 0 )
		{
			$info_box_contents[] = array('align' => 'right',
										 'params' => 'colspan="5" class="carrito_total"',
										 'text' => 'Descuento Promoción:&nbsp; &nbsp; &nbsp; <strong> -' . $currencies->format( $nDescuentoPromo ) . '</strong>');
		}
	}

    if ($any_out_of_stock == 1) {
	  if (STOCK_ALLOW_CHECKOUT == 'true') {
		  $info_box_contents[] = array('align' => 'center',
									 'params' => 'colspan="5" class="stockWarning"',
									 'text' => '<b>' . OUT_OF_STOCK_CAN_CHECKOUT . '</b>');
      } else {
		  $info_box_contents[] = array('align' => 'center',
									 'params' => 'colspan="5" class="stockWarning"',
									 'text' => '<b>' . OUT_OF_STOCK_CANT_CHECKOUT . '</b>');
      }
    }

    new noborderBox($info_box_contents,true);
}
?>
