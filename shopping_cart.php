<?php
/*
  $Id: shopping_cart.php 1739 2007-12-20 00:52:16Z hpdl $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2007 osCommerce

  Released under the GNU General Public License
*/

  require("includes/application_top.php");

  if ($cart->count_contents() > 0) {
    include(DIR_WS_CLASSES . 'payment.php');
    $payment_modules = new payment;
  }

/**
 * @author Daniel Lucia <daniel.lucia@denox.es>
 * Comprar de nuevo
 */
if( tep_session_is_registered('customer_id') && intval($_GET['buy_all']) > 0 ) { //Verificamos que esté logueado y que exista el id del pedido
	$sql = "SELECT customers_id FROM " . TABLE_ORDERS . " WHERE orders_id = '". intval($_GET['buy_all']) . "'";

	$customer_info_query = tep_db_query($sql);
	$customer_info = tep_db_fetch_array($customer_info_query);

	if( $customer_info['customers_id'] != $customer_id ) {
        tep_redirect(tep_href_link('shopping_cart.php', '', 'SSL')); //Si el id del pedido no pertenece al usuario, redireccionamos al shopping
    }

	$sql = sprintf('SELECT orders_products_id, products_id, products_quantity FROM orders_products WHERE orders_id = %d', $_GET['buy_all']);
    $products_query = tep_db_query($sql);
    while ($product = tep_db_fetch_array($products_query)) {
		$sql = sprintf('SELECT products_options_id, products_options_values_id, products_options, products_options_values, reference FROM orders_products_attributes WHERE orders_id = %d AND orders_products_id = %d', $_GET['buy_all'], $product['orders_products_id']);
        $attrributes_query = tep_db_query($sql);

		$attributes = '';
		if (tep_db_num_rows($attrributes_query) > 0) {
			$row = tep_db_fetch_array($attrributes_query);
			$opt_id = (int)$row['products_options_id'];
			$val_id = (int)$row['products_options_values_id'];
			// Fallback 1: histórico con IDs=0 — resolver por reference de variante (única por producto)
			if (($opt_id === 0 || $val_id === 0) && !empty($row['reference'])) {
				$rq = tep_db_query("SELECT options_id, options_values_id FROM " . TABLE_PRODUCTS_ATTRIBUTES . " WHERE products_id = '" . (int)$product['products_id'] . "' AND reference = '" . tep_db_input($row['reference']) . "' LIMIT 1");
				if (tep_db_num_rows($rq) > 0) {
					$rrow = tep_db_fetch_array($rq);
					$opt_id = (int)$rrow['options_id'];
					$val_id = (int)$rrow['options_values_id'];
				}
			}
			// Fallback 2: resolver por nombres textuales (option_name + value_name) contra el idioma del cliente
			if (($opt_id === 0 || $val_id === 0) && !empty($row['products_options']) && !empty($row['products_options_values'])) {
				$nq = tep_db_query("SELECT pa.options_id, pa.options_values_id FROM " . TABLE_PRODUCTS_ATTRIBUTES . " pa JOIN " . TABLE_PRODUCTS_OPTIONS . " po ON po.products_options_id = pa.options_id JOIN " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov ON pov.products_options_values_id = pa.options_values_id WHERE pa.products_id = '" . (int)$product['products_id'] . "' AND po.products_options_name = '" . tep_db_input($row['products_options']) . "' AND pov.products_options_values_name = '" . tep_db_input($row['products_options_values']) . "' AND po.language_id = '" . (int)$languages_id . "' AND pov.language_id = '" . (int)$languages_id . "' LIMIT 1");
				if (tep_db_num_rows($nq) > 0) {
					$nrow = tep_db_fetch_array($nq);
					$opt_id = (int)$nrow['options_id'];
					$val_id = (int)$nrow['options_values_id'];
				}
			}
			if ($opt_id > 0 && $val_id > 0) {
				$attributes = array($opt_id => $val_id);
			}
		}

        $cart->add_cart($product['products_id'], $product['products_quantity'], $attributes);
    }
    tep_redirect(tep_href_link('shopping_cart.php', '', 'SSL')); //Redireccionamos para quitar los parametros

}

require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_SHOPPING_CART);
$breadcrumb->add(NAVBAR_TITLE, tep_href_link(FILENAME_SHOPPING_CART));
?>

<?php require(DIR_THEME. 'html/header.php'); ?>


<!-- BOE: ERSD.net AJAX Shopping Cart -->
		<script language="JavaScript" type="text/javascript">
			var sendReq = getXmlHttpRequestObject();
			var receiveReq = getXmlHttpRequestObject();
			var receiveReqInfoBox = getXmlHttpRequestObject();
			var file = 'getCart.php';
			var loc = 'span_cart';
			var tmp = '&nbsp;<img src=theme/<?php echo THEME; ?>/images/general/loading_sc.gif alt=loading>&nbsp;<?php echo MESSAGE_WAIT; ?><br/>';
			//Function for initializating the page.
			function startCart(key,file,sid,loc,tmp) {
				//Start Showing Cart.
				getCartText('',file,sid,loc,tmp);
			}
			//Gets the browser specific XmlHttpRequest Object
			function getXmlHttpRequestObject() {
				if (window.XMLHttpRequest) {
					<?php define('SHOW_NON_AJAX_CART',false); ?>
					return new XMLHttpRequest();
				} else if(window.ActiveXObject) {
				<?php define('SHOW_NON_AJAX_CART',false); ?>
					return new ActiveXObject("Microsoft.XMLHTTP");
				} else {
					document.getElementById('p_status').innerHTML = 'Status: Cound not create XmlHttpRequest Object.  Consider upgrading your browser.';
					<?php define('SHOW_NON_AJAX_CART',true); ?>
				}
			}

			//Gets the current cart
			function getCartText(key,file,sid,loc,tmp) {
				var url=file+"?"+sid+"&"+key;
				if (sid && key) {
					  url=file+"?"+sid+"&"+key;
				} else {
					if (sid) {
				    	url=file+"?"+sid;
				  	} else {
				    	if (key) {
				      		url=file+"?"+key;
				    	} else {
				      		url=file;
				    	}
				  	}
				}

				if( typeof vdxCart != 'undefined' )
					vdxCart.refreshCart();
				//if (tmp) {getObject(loc).innerHTML = tmp;}

				if (receiveReq.readyState == 4 || receiveReq.readyState == 0) {
					receiveReq.open("GET", url+'&Cart=1', true);
					receiveReq.onreadystatechange = handleReceiveCart;
					receiveReq.send(null);
				}
			}

			//Gets the current cart info box
			function getCartInfoBoxText(key,file,sid,loc,tmp) {
				var url=file+"?"+sid+"&"+key;
				if (sid && key) {
					  url=file+"?"+sid+"&"+key;
				} else {
					if (sid) {
				    	url=file+"?"+sid;
				  	} else {
				    	if (key) {
				      		url=file+"?"+key;
				    	} else {
				      		url=file;
				    	}
				  	}
				}

				// if (tmp) {getObject(loc).innerHTML = tmp;}

				// if (receiveReqInfoBox.readyState == 4 || receiveReqInfoBox.readyState == 0) {
					// receiveReqInfoBox.open("GET", url+'&Cart=1', true);
					// receiveReqInfoBox.onreadystatechange = handleReceiveCartInfoBox;
					// receiveReqInfoBox.send(null);
				// }
			}

			//send change of stock to Cart.
			function sendCartChangeQty(key,file,sid,loc,tmp,iElementId,product_id,qty) {

    			if (typeof iElementId == "string" && iElementId.length > 0) {
        			var element = document.getElementById(iElementId);
        			if (element) {
            			//alert("name=" + element.name + " - id=" + element.id);
						//return;
        			} else {
            			document.getElementById('p_status').innerHTML = 'Status: Could not find the requested element.';
						return;
        			}
    			}

				 //Verify entered quantity is number
				if (isNaN(element.value)) {
				document.getElementById('p_status').innerHTML = 'Debes de introducir un n�mero con la Cantidad que deseas.';
				getCartText(key,file,sid,loc,tmp);
				return;
				}
				else {
				document.getElementById('p_status').innerHTML = '';
				}
				// check if Quantity drops below 0
				// check if Quantity drops below 0
				if (Number(element.value) + Number(qty) <= 0) {
				//Call function to delete item
				sendCartRemoveItem(key,file,sid,loc,tmp,iElementId,product_id);
				return;
				}
				var url=file+"?"+sid+"&"+key;
				if (sid && key) {
					  url=file+"?"+sid+"&"+key;
				} else {
					if (sid) {
				    	url=file+"?"+sid;
				  	} else {
				    	if (key) {
				      		url=file+"?"+key;
				    	} else {
				      		url=file;
				    	}
				  	}
				}

				if (tmp) {getObject(loc).innerHTML = tmp;}

				if (sendReq.readyState == 4 || sendReq.readyState == 0) {
					sendReq.open("POST", url+'&Cart=1', true);
					sendReq.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
					sendReq.onreadystatechange = handleSendCart;

					var param = 'quantity=' + (Number(element.value) + Number(qty));
					param += '&products_id=' + product_id;
					sendReq.send(param);
				}
			}

			//send change of stock to Cart.
			function sendCartRemoveItem(key,file,sid,loc,tmp,iElementId,product_id) {

    			if (typeof iElementId == "string" && iElementId.length > 0) {
        			var element = document.getElementById(iElementId);
        			if (element) {
            			//alert("name=" + element.name + " - id=" + element.id);
						//return;
        			} else {
            			document.getElementById('p_status').innerHTML = 'Status: Could not find the requested element.';
						return;
        			}
    			}

				// check user wants to remove item
				//if (element.value != '') {
				// Are you sure you want to remove this item
				var fRet;
				fRet = confirm('¿Estás seguro de querer eliminar este producto de la cesta de la compra?');
				//alert(fRet);
				if (fRet == false) {
				getCartText(key,file,sid,loc,tmp);
				return;
				}
				var url=file+"?"+sid+"&"+key;
				if (sid && key) {
					  url=file+"?"+sid+"&"+key;
				} else {
					if (sid) {
				    	url=file+"?"+sid;
				  	} else {
				    	if (key) {
				      		url=file+"?"+key;
				    	} else {
				      		url=file;
				    	}
				  	}
				}

				if (tmp) {getObject(loc).innerHTML = tmp;}

				if (sendReq.readyState == 4 || sendReq.readyState == 0) {
					sendReq.open("POST", url+'&Cart=1', true);
					sendReq.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
					sendReq.onreadystatechange = handleSendCartRemoveItem;

					var param = '&products_id=' + product_id;
					param += '&cart_delete=Yes';
					sendReq.send(param);
				}
			}

			//When our stock change has been sent, update our page.
			function handleSendCart() {
			if (sendReq.readyState == 4 && sendReq.status == 200){
			getCartText('','getCart.php','<?php echo tep_session_name().'='.tep_session_id(); ?>','span_cart',tmp);
			getCartInfoBoxText('','getCartBox.php','<?php echo tep_session_name().'='.tep_session_id(); ?>','span_cart_box',tmp);
			}
			}
			//Function for handling the return of Cart text
			function handleReceiveCart() {
				//Check to see if the XmlHttpRequests state is finished.
				if (receiveReq.readyState == 4) {
					//Set the contents of our span element to the result of the asyncronous call.
					document.getElementById('span_cart').innerHTML = receiveReq.responseText;
				}
			}

			//Function for handling the return of Cart Info Box text
			function handleReceiveCartInfoBox() {
				//Check to see if the XmlHttpRequests state is finished.
				if (receiveReqInfoBox.readyState == 4) {
					//Set the contents of our span element to the result of the asyncronous call.
					document.getElementById('span_cart_box').innerHTML = receiveReqInfoBox.responseText;
				}
			}

			//When our stock change has been sent, update our page.
			function handleSendCartRemoveItem() {
			if (sendReq.readyState == 4 && sendReq.status == 200){
			getCartText('','getCart.php','<?php echo tep_session_name().'='.tep_session_id(); ?>',loc,tmp);
			getCartInfoBoxText('','getCartBox.php','<?php echo tep_session_name().'='.tep_session_id(); ?>','span_cart_box',tmp);
			}
			}
			function getObject(name) {
			   var ns4 = (document.layers) ? true : false;
			   var w3c = (document.getElementById) ? true : false;
			   var ie4 = (document.all) ? true : false;

			   if (ns4) return eval('document.' + name);
			   if (w3c) return document.getElementById(name);
			   if (ie4) return eval('document.all.' + name);
			   return false;
			}
		</script>
<!-- EOE: ERSD.net AJAX Shopping Cart -->
<script type="text/javascript">
on = "Actualizando";
off = " ";
function advisecustomer(advise_status) {
document.cart_quantity.advise.value = advise_status;
}
var loadCartOsc = function(){ startCart('','getCart.php','<?php echo tep_session_name().'='.tep_session_id(); ?>'); };
</script>


<?php require(DIR_THEME. 'html/column_left.php'); ?>

<?php echo tep_draw_form('cart_quantity', tep_href_link(FILENAME_SHOPPING_CART, 'action=update_product')); ?>

<?php
  if ($cart->count_contents() > 0) {
?>
<table style="width: 100%;">
      <tr>
        <td>
<!-- BOE: ERSD.net AJAX Shopping Cart -->
		<p id="p_status"></p>
		<!-- used to display the results of the asyncronous request -->
		<span id="span_cart" class="span_cart"></span>
<!-- EOE: ERSD.net AJAX Shopping Cart -->
<?php
if (SHOW_NON_AJAX_CART == true) {
    $info_box_contents = array();
    $info_box_contents[0][] = array('align' => 'center',
                                    'params' => 'class="productListing-heading"',
                                    'text' => TABLE_HEADING_REMOVE);

    $info_box_contents[0][] = array('params' => 'class="productListing-heading"',
                                    'text' => TABLE_HEADING_PRODUCTS);

    $info_box_contents[0][] = array('params' => 'class="productListing-heading"',
                                    'text' => TABLE_HEADING_MODEL);

    $info_box_contents[0][] = array('align' => 'center',
                                    'params' => 'class="productListing-heading"',
                                    'text' => TABLE_HEADING_QUANTITY);

    $info_box_contents[0][] = array('align' => 'right',
                                    'params' => 'class="productListing-heading"',
                                    'text' => TABLE_HEADING_TOTAL);

    $any_out_of_stock = 0;
    $products = $cart->get_products();
    for ($i=0, $n=sizeof($products); $i<$n; $i++) {
// Push all attributes information in an array
      if (isset($products[$i]['attributes']) && is_array($products[$i]['attributes'])) {
		foreach( $products[$i]['attributes'] as $option => $value ) {
          echo tep_draw_hidden_field('id[' . $products[$i]['id'] . '][' . $option . ']', $value);
          $attributes = tep_db_query("select popt.products_options_name, popt.products_options_track_stock, poval.products_options_values_name, pa.options_values_price, pa.price_prefix, pa.reference
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
			// modif reference_attributes
			$products[$i][$option]['reference'] = $attributes_values['reference'];
			// eof reference_attributes
		  $products[$i][$option]['track_stock'] = $attributes_values['products_options_track_stock'];
        }
      }
    }

	// begin Bundled Products
	if (STOCK_CHECK == 'true')
	{
		$bundle_contents = array();
		$bundle_values = array();
		$product_ids_in_bundles = array();
		$bundle_qty_ordered = array();

		for( $i=0, $n=sizeof($products); $i<$n; $i++ )
		{
			if( $products[$i]['bundle'] == "yes" )
			{
				$tmp = get_all_bundle_products($products[$i]['id']);
				$bundle_values[$products[$i]['id']] = $products[$i]['final_price'];
				$bundle_contents[$products[$i]['id']] = $tmp;
				$bundle_qty_ordered[$products[$i]['id']] = $products[$i]['quantity'];

				foreach ($tmp as $id => $qty)
				{
					if (!in_array($id, $product_ids_in_bundles))
						$product_ids_in_bundles[] = $id; // save unique ids
				}
			}
		}

		if (!empty($bundle_values))
		{ // if bundles exist in order
			arsort($bundle_values); // sort array so bundle ids with highest value come first
			$product_on_hand = array();
			$bundles_stock_check = array();

			foreach ($product_ids_in_bundles as $id)
			{
				// get quantity on hand for every product contained in bundles in this order
				$product_on_hand[$id] = tep_get_products_stock($id);
			}

			foreach ($bundle_values as $bid => $bprice)
			{
				$bundles_available = array();

				foreach ($bundle_contents[$bid] as $pid => $qty)
				{
					$bundles_available[] = intval($product_on_hand[$pid] / $qty);
				}

				$available = min($bundles_available); // max number of this bundle we can make with product on hand
				$bundles_stock_check[$bid] = '';

				if ($available <= 0)
				{
					$bundles_stock_check[$bid] = '<span class="markProductOutOfStock">' . STOCK_MARK_PRODUCT_OUT_OF_STOCK . '<br>' . STOCK_MARK_PRODUCT_OUT_OF_STOCK . TEXT_NOT_AVAILABLEINSTOCK . '</span>';
				}
				elseif ($available < $bundle_qty_ordered[$bid])
				{
					$bundles_stock_check[$bid] = '<span class="markProductOutOfStock">' . STOCK_MARK_PRODUCT_OUT_OF_STOCK . '<br>' . STOCK_MARK_PRODUCT_OUT_OF_STOCK . TEXT_ONLY_THIS_AVAILABLEINSTOCK1 . $available . TEXT_ONLY_THIS_AVAILABLEINSTOCK2 . '</span>';
				}
				$deduct = min($available, $bundle_qty_ordered[$bid]); // assume we sell as many of the bundle as possible

				foreach ($bundle_contents[$bid] as $pid => $qty)
				{
					// reduce product left on hand by number sold in this bundle before checking next less expensive bundle
					// also lets us know how many we have left to sell individually
					$product_on_hand[$pid] -= ($deduct * $qty);
				}
			}
		}
	}
	$any_bundle_only = false;
	// end Bundled Products

    for ($i=0, $n=sizeof($products); $i<$n; $i++) {
      if (($i/2) == floor($i/2)) {
        $info_box_contents[] = array('params' => 'class="productListing-even"');
      } else {
        $info_box_contents[] = array('params' => 'class="productListing-odd"');
      }

      $cur_row = sizeof($info_box_contents) - 1;

      $info_box_contents[$cur_row][] = array('align' => 'center',
                                             'params' => 'class="productListing-data" valign="top"',
                                             'text' => tep_draw_checkbox_field('cart_delete[]', $products[$i]['id']));

      $products_name = '<table style="width: 100%;">' .
                       '  <tr>' .
                       '    <td class="productListing-data" align="center"><a href="' . tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $products[$i]['id']) . '">' . tep_image(DIR_WS_IMAGES . $products[$i]['image'], $products[$i]['name'], SMALL_IMAGE_WIDTH, SMALL_IMAGE_HEIGHT) . '</a></td>' .
                       '    <td class="productListing-data" valign="top"><a href="' . tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $products[$i]['id']) . '"><strong>' . $products[$i]['name'] . '</strong></a>';

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

	if (STOCK_CHECK == 'true')
	{
		if ($products[$i]['bundle'] == "yes")
		{
			$stock_check = $bundles_stock_check[$products[$i]['id']];
        }
		elseif (in_array($products[$i]['id'], $product_ids_in_bundles))
		{
			// if ordering individually product that is also contained in a bundle in this order must be able to cover both quantities
			// check against product left on hand after bundles have been sold
			$stock_check = '';
			if ($product_on_hand[$products[$i]['id']] <= 0)
			{
				$stock_check = '<span class="markProductOutOfStock">' . STOCK_MARK_PRODUCT_OUT_OF_STOCK . '<br>' . STOCK_MARK_PRODUCT_OUT_OF_STOCK . TEXT_NOT_AVAILABLEINSTOCK . '</span>';
			}
			elseif ($product_on_hand[$products[$i]['id']] < $products[$i]['quantity'])
			{
				$stock_check = '<span class="markProductOutOfStock">' . STOCK_MARK_PRODUCT_OUT_OF_STOCK . '<br>' . STOCK_MARK_PRODUCT_OUT_OF_STOCK . TEXT_ONLY_THIS_AVAILABLEINSTOCK1 . $product_on_hand[$products[$i]['id']] . TEXT_ONLY_THIS_AVAILABLEINSTOCK2 . '</span>';
			}
		}
		elseif(isset($products[$i]['attributes']) && is_array($products[$i]['attributes']))
		{
			$stock_check = tep_check_stock($products[$i]['id'], $products[$i]['quantity'], $products[$i]['attributes']);
        }
		else
		{
			$stock_check = tep_check_stock($products[$i]['id'], $products[$i]['quantity']);
        }

        if (tep_not_null($stock_check))
		{
			$any_out_of_stock = 1;
			$products_name .= $stock_check;
        }
	}

	if ($products[$i]['sold_in_bundle_only'] == 'yes')
	{
        $products_name .= '<br><span class="markProductOutOfStock">' . TEXT_BUNDLE_ONLY . '</span>';
        $any_bundle_only = true;
	}

      if (isset($products[$i]['attributes']) && is_array($products[$i]['attributes'])) {
        reset($products[$i]['attributes']);

		$reference = ""; // initialisation de la variable pour chaque produit

		foreach( $products[$i]['attributes'] as $option => $value ) {
          // $products_name .= '<br /><small><i> - ' . $products[$i][$option]['products_options_name'] . ' ' . $products[$i][$option]['products_options_values_name'] . '</i></small>';
			$model = $products[$i][$option]['model'];
			$nom_option = $products[$i][$option]['products_options_name'];
			$valeur_option = $products[$i][$option]['products_options_values_name'];
			$reference .= "-" . $products[$i][$option]['reference'];
        }
      }

      $products_name .= '    </td>' .
                        '  </tr>' .
                        '</table>';

      $info_box_contents[$cur_row][] = array('params' => 'class="productListing-data"',
                                             'text' => $products_name);

// modif reference_attributes
	  $info_box_contents[$cur_row][] = array('params' => 'class="productListing-data" valign="top"',
												'text' => $model . $reference);
// eof reference_attributes

      $info_box_contents[$cur_row][] = array('align' => 'center',
                                             'params' => 'class="productListing-data" valign="top"',
                                             'text' => tep_draw_input_field('cart_quantity[]', $products[$i]['quantity'], 'size="4"') . tep_draw_hidden_field('products_id[]', $products[$i]['id']));

      $info_box_contents[$cur_row][] = array('align' => 'right',
                                             'params' => 'class="productListing-data" valign="top"',
                                             'text' => '<strong>' . $currencies->display_price($products[$i]['final_price'], tep_get_tax_rate($products[$i]['tax_class_id']), $products[$i]['quantity']) . '</strong>');
    }

    new productListingBox($info_box_contents);
	echo '
        </td>
      </tr>

      <tr>
  	  	<td><input type="text" name="advise" size="30" maxlength="100" style="width:340px; font-size:10px; background-color:white;color:red;font-weight:bold;border:0px;" value="" /></td>
        <td align="right" class="main"><strong>' . SUB_TITLE_SUB_TOTAL . $currencies->format($cart->show_total()) . '</strong></td>
      </tr>';

    if ($any_out_of_stock == 1) {
      if (STOCK_ALLOW_CHECKOUT == 'true') {
	     echo '
      <tr>
        <td class="stockWarning" align="center">' . OUT_OF_STOCK_CAN_CHECKOUT . '</td>
      </tr>';
      } else {
		 echo '
      <tr>
        <td class="stockWarning" align="center">' . OUT_OF_STOCK_CANT_CHECKOUT . '</td>
      </tr>';
      }
      }

    if ($any_bundle_only) {
      echo '<tr>
        <td class="stockWarning" align="center"><br>'. TEXT_NO_CHECKOUT_BUNDLE_ONLY . '</td>
      </tr>';
    }


    }
    if ($messageStack->size('cart_notice') > 0) {
?>
        <div class="mensaje"><?php echo $messageStack->output('cart_notice'); ?></div>
<?php
      }
// EOF QPBPP for SPPC
?>

<?php
	// Recorremos los productos del carrito
	$bajoPedido = false;

	foreach( $cart->get_products() as $aProduct )
	{
		// Si no tenemos el valor de products_quantity
		if( ! isset ( $aProduct['products_quantity'] ) )
		{
			// Obtenemos el ID del producto
			$nID = (isset( $aProduct['products_id'] ) ? $aProduct['products_id'] : $aProduct['id']);
			$nID = (preg_match( '/(\{)/i', $nID ) ? preg_replace( '/(\{)(.*)/i', '', $nID ) : $nID);

			// Si el producto tiene atributo
			if( is_array( $aProduct['attributes'] ) && count( $aProduct['attributes'] ) > 0 )
			{
				$nOption = key( $aProduct['attributes'] );

				// Obtenemos la cantidad del producto del atributo
				$aAux = tep_db_query( 'SELECT products_stock_quantity FROM products_stock WHERE products_id = "' . $nID . '" AND products_stock_attributes = "' . $nOption . '-' . $aProduct['attributes'][$nOption] . '";' );
				$aAux = tep_db_fetch_array( $aAux );
				$aProduct['products_quantity'] = $aAux['products_stock_quantity'];
			}
			else
			{
				// Obtenemos la cantidad del producto
				$aAux = tep_db_query( 'SELECT products_quantity FROM ' . TABLE_PRODUCTS . ' WHERE products_id = "' . $nID . '";' );
				$aAux = tep_db_fetch_array( $aAux );
				$aProduct['products_quantity'] = $aAux['products_quantity'];
			}
		}

		// Entre 2 y 6 días
		if( $aProduct['products_quantity'] <= -100 && $aProduct['products_quantity'] >= -150 )
		{
			if( $nAdd1 <= ( 24 * 2 ) )
				$nAdd1 = ( 24 * 2 );
			if( $nAdd2 <= ( 24 * 6 ) )
					$nAdd2 = ( 24 * 6 );
			}
			// Entre 8 y 13 días
			else if( $aProduct['products_quantity'] <= 0 && $aProduct['products_quantity'] >= -799 )
			{
				if( $nAdd1 <= ( 24 * 8 ) )
					$nAdd1 = ( 24 * 8 );
				if( $nAdd2 <= ( 24 * 13 ) )
					$nAdd2 = ( 24 * 13 );
			}
			// Bajo pedido
			else if( $aProduct['products_quantity'] <= -800 && $aProduct['products_quantity'] >= -899 )
			{
				$nAdd1 = false;
				$nAdd2 = false;
				$bajoPedido = true;
				break;
			}
		// Agotado
		else if( $aProduct['products_quantity'] <= -900 && $aProduct['products_quantity'] >= -901 )
		{
			$nAdd1 = false;
			$nAdd2 = false;
			break;
		}
	}

	// Si tenemos predicción
	if( $nAdd1 !== false && $bajoPedido == false )
	{
		// Obtenemos las dos estimaciones
		$aEstimate1 = getShippingEstimate( true, false, $nAdd1 );
		$aEstimate2 = getShippingEstimate( true, false, $nAdd2 );

		// Si las fechas son iguales, sumamos un día
		if( $aEstimate1['date'] == $aEstimate2['date'] )
			$aEstimate2 = addHoursToDate( $aEstimate2['year'] . '-' . $aEstimate2['month'] . '-' . $aEstimate2['day'], 24 );

		// Mostramos el mensaje
		echo '<div class="ship gtcrt"><div class="tt tt-6 icon"></div><div>' . str_replace( array( '%s1', '%s2' ), array( dateToSpanish( date( 'l j ' . SHIPPING_PREDICTION_FROM . ' F', strtotime( $aEstimate1['year'] . '-' . $aEstimate1['month'] . '-' . $aEstimate1['day'] ) ) ), dateToSpanish( date( 'l j ' . SHIPPING_PREDICTION_FROM . ' F', strtotime( $aEstimate2['year'] . '-' . $aEstimate2['month'] . '-' . $aEstimate2['day'] ) ) ) ), SHIPPING_PREDICTION_BUY_NOW ) . '. <a href="shipping_estimate_more_info.php" rel="nofollow" class="mgp-ajax" title="' . SHIPPING_PREDICTION_MORE_INFO . '" style="display: inline-block;">(+ info.)</a></div></div>';
	}
	// Si no podemos hacer predicción
	else
		echo '<p>' . SHIPPING_PREDICTION_NONE . '.</p>';

?>

      <tr>
        <td><table style="width: 100%;">
          <tr class="infoBoxContents">
            <td><table border="0" style="width: 100%;">
              <tr>
                <td class="main"><?php if (SHOW_NON_AJAX_CART == true) { echo tep_image_submit('button_update_cart.gif', IMAGE_BUTTON_UPDATE_CART, 'onclick="advisecustomer(off)"');}; ?></td>
                <td align="right" class="main" style="text-align: right;"><?php echo '<a href="' . $_SERVER['HTTP_REFERER'] . '">' . tep_image_button('button_continue_shopping.gif', IMAGE_BUTTON_CONTINUE_SHOPPING, 'rojo') . '</a>'; ?> <?php echo '<a href="' . tep_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL') . '">' . tep_image_button('button_realizar_pedido.gif', IMAGE_BUTTON_CHECKOUT) . '</a>'; ?></td>
              </tr>
            </table></td>
          </tr>
        </table></td>
      </tr>
<?php
    $initialize_checkout_methods = $payment_modules->checkout_initialization_method();

    if (!empty($initialize_checkout_methods)) {
?>

      <tr>
        <td align="right" class="main" style="padding-right: 50px;"><?php echo TEXT_ALTERNATIVE_CHECKOUT_METHODS; ?></td>
      </tr>
<?php
	  foreach( $initialize_checkout_methods as $value ) {
?>

      <tr>
      		<tr><td align="right" class="main"><?php echo $value; ?></td></tr>


<?php
      }
    }
  } else {
?>
      <tr>
        <td align="center" class="main"><div class="mensaje"><?php echo TEXT_CART_EMPTY; ?></div></td>
      </tr>

      <tr>
        <td><div class="botonera"><?php echo '<a href="' . tep_href_link(FILENAME_DEFAULT) . '">' . tep_image_button('button_continue.gif', IMAGE_BUTTON_CONTINUE) . '</a>'; ?></div></td>
          </tr>
        </table></td>
      </tr>
<?php
  }
?>
<tr>
        <td></form></td>
      </tr>
    </table>
    <?php require(DIR_WS_COMPONENTS . 'shipping_estimator.php'); ?>
<?php require(DIR_THEME. 'html/column_right.php'); ?>
<!-- body_eof //-->

<!-- footer //-->
<?php require(DIR_THEME. 'html/footer.php'); ?>
<!-- footer_eof //-->

<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
