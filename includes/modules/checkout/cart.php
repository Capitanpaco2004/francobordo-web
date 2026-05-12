<?php
// Alias
namespace Checkout;

// Librerias
use util\tools;
use util\event;

class Cart {
	/**
	 * Mensaje de error obtenido
	 */
	public $messageError = false;

	/**
	 * Si necesita redirect
	 */
	public $redirect = false;

	/**
	 * Realiza una limpieza del carrito y redirecciona al carrito de nuevo
	 */
	public function clean() {
		// Variables
		global $cart;

		// Eliminamos todos los productos
		$cart->reset(true);

		// Redireccionamos
		$this->redirect = tep_href_link(FILENAME_SHOPPING_CART);
		return true;
	}

	/**
	 *
	 */
	public function modified() {
		// Libreria options
		require_once 'includes/classes/attributes/option.class.php';

		// Variables
		$products = [];

		// Todas las variables accesibles
		extract($GLOBALS);

		// Si el metodo es post y nos envian el json
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['json'])) {
			// Obtenemos el json
			$products = json_decode(tep_db_prepare_input($_POST['json']), true);

			// Recorremos
			foreach ($products as $id => $product) {
				// Si contenemos atributos
				if (isset($product['attributes']) && is_array($product['attributes']) && count($product['attributes']) > 0) {
					$attributes            = $product['attributes'];
					$product['attributes'] = [];

					foreach ($attributes as $attribute) {
						$product['attributes'][(int)filter_var($attribute['name'], FILTER_SANITIZE_NUMBER_INT)] = $attribute['value'];
					}
				} else { // Si no contenemos atributos dejamos un array vacio
					$product['attributes'] = [];
				}

				// Obtenemos la cantidad
				$quantity = $cart->get_quantity(tep_get_uprid((int)$id, $product['attributes'])) + $product['quantity'];

				// Eliminamos
				$cart->remove($id);

				// Añadimos al carrito
				$cart->add_cart((int)$id, $quantity, $product['attributes']);
			}

			// Redireccionamos
			$this->redirect = tep_href_link('checkout/shipping/');
			return true;
		}

		// Productos
		$cartProducts = $cart->get_products();

		// Si no existen productos
		if (!$cart->getHasModified()) {
			// Redireccionamos
			$this->redirect = tep_href_link(FILENAME_SHOPPING_CART);
			return false;
		}

		// Plantilla html atributos
		$aTemplate = [
			'OPTION_DEFAULT' => '<tr class="%REPLACE_OPTION_TYPE_CLASS%">
				<td style="width:1%; white-space:nowrap;">%REPLACE_OPTION_NAME%:</td>
				<td>%REPLACE_VALUE_HTML%</td>
			</tr><tr><td colspan="2" style="font-size:10px;">&nbsp;</td></tr>',
			'OPTION_CLASS'   => [
				'combobox' => 'cmbo',
				'image'    => 'rdio',
			],
			'OPTION_HTML'    => [
				'image'    => '<div class="row">%REPLACE_VALUE_INPUT%%REPLACE_VALUE_IMAGE%%REPLACE_VALUE_NAME%</div>',
				'combobox' => '<div class="xform oeAttr">%REPLACE_VALUE_SELECT%</div>',
				'text'     => '%REPLACE_TEXT%',
			],
		];

		// Opciones de atributos
		$aOptions = [
			'TEMPLATE'   => $aTemplate,
			'SHOW_PRICE' => true,
		];

		// Recorremos los productos del carrito para comprobar cuales han sido modificados
		foreach ($cartProducts as $product) {
			if ($product['has_modified']) {
				// Atributos
				$objOption                  = new \option();
				$product['html_attributes'] = str_replace(['array_option_stock'], ['array_option_stock_' . $product['id']], $objOption->getOptionHtml($product['id'], $aOptions));

				$products[] = $product;
			}
		}

		// Retornamos el template
		return tools::includeTemplate($sPathTemplate . '/cart_modified.php', [
			'messageStack' => $messageStack,
			'products'     => $products,
		]);
	}

	/**
	 * Repite la compra del pedido pasado
	 */
	public function repeatOrder($ordersId = false) {
		// Variables
		global $customer_id, $cart;
		$ordersId = tep_db_prepare_input($ordersId != false ? $ordersId : (isset($_GET['orders_id']) ? $_GET['orders_id'] : ''));

		// Obtenemos los productos del pedido
		$aOrderProducts = tep_db_query('SELECT o.customers_id, op.orders_products_id, op.products_id, op.products_quantity
										 FROM ' . TABLE_ORDERS . ' o
										 INNER JOIN orders_products op ON( o.orders_id = op.orders_id )
										 WHERE o.orders_id = "' . (int)$ordersId . '"
										 AND o.customers_id = ' . (int)$customer_id);

		// Si tenemos productos
		if (tep_db_num_rows($aOrderProducts) > 0) {
			// Recorremos productos
			while ($aProducto = tep_db_fetch_array($aOrderProducts)) {
				// Array de atributos
				$aAtributos = [];

				// Obtenemos atributos
				$aAttributesQuery = tep_db_query("SELECT po.products_options_id, pov.products_options_values_id
												   FROM orders_products_attributes opa
												   INNER JOIN products_options po ON( opa.products_options = po.products_options_name )
												   INNER JOIN products_options_values pov ON( opa.products_options_values = pov.products_options_values_name )
												   INNER JOIN products_attributes pa ON( po.products_options_id = pa.options_id AND pov.products_options_values_id = pa.options_values_id )
												   WHERE orders_id = '" . (int)$ordersId . "' AND orders_products_id = '" . (int)$aProducto['orders_products_id'] . "'");

				// Si es un producto con atributos
				if (tep_db_num_rows($aAttributesQuery) > 0) {
					// Recorremos atributos y guardamos en array
					while ($aAttribute = tep_db_fetch_array($aAttributesQuery)) {
						$aAtributos[$aAttribute['products_options_id']] = $aAttribute['products_options_values_id'];
					}

				}

				// Añadimos al carrito
				$cart->add_cart($aProducto['products_id'], $aProducto['products_quantity'], $aAtributos);
			}
		}

		// Redireccionamos
		$this->redirect = tep_href_link(FILENAME_SHOPPING_CART);
		return true;
	}

	/**
	 * Añade/elimina el cupon descuento
	 */
	public function coupon() {
		// Variables
		global $coupon, $messageStack, $cartID, $cart;
		$sCoupon = tep_db_input(isset($_POST['coupon']) ? $_POST['coupon'] : '');

		// Registramos el cupón
		if ($sCoupon != '' && $sCoupon != 'null') {
			$coupon = tep_db_input($_POST['coupon']);
			tep_session_register('coupon');

			// Order
			require_once DIR_WS_CLASSES . 'order.php';
			$order = new \order;

			// Comprobamos errores
			if (is_array($order->coupon->get_messages()) && count($order->coupon->get_messages()) > 0) {
				// Recorremos y mostramos mensajes de error
				foreach ($order->coupon->get_messages() as $aMessage) {
					$messageStack->add('message_error', trim($aMessage), 'error', true);
				}

				// Eliminamos cupón
				$coupon = '';
				tep_session_unregister('coupon');
			} else {
				\Affiliates::setSessionAffiliateID($_POST['coupon']);

			}

		} // Eliminamos cupón
		else {
			$coupon = '';
			tep_session_unregister('coupon');
			if (isset($_SESSION['id_affiliate'])) {
				unset($_SESSION['id_affiliate']);
			}
		}

		// Generamos
		$cartID = $cart->generate_cart_id();

		// Pintamos
		return $this->index();
	}

	/**
	 * Index
	 */
	public function index() {
		// Variables
		global $aFree, $aBoxes, $sHtmlCart, $checkoutDifferentPage, $messageMissingFree, $messageMissingFreeSuccess, $getShippingText, $order;

		$aBoxes                    = [];
		$messageMissingFree        = false;
		$messageMissingFreeSuccess = false;

		// Todas las variables accesibles
		extract($GLOBALS);

		// Hacemos que el checkout siempre sea visible el resto de la web
		$checkoutDifferentPage = false;

		$this->_buyAll();

		// Breadcrumb
		$breadcrumb->add(CHECKOUT_CART_BREADCRUMB, tep_href_link(FILENAME_SHOPPING_CART));

		// Boxes para la columna derecha
		if ($cart->count_contents()) {
			//$aBoxes[] = $boxCheckout->points();
			$aBoxes[] = $boxCheckout->total();
			$aBoxes[] = $boxCheckout->buttonContinue(CHECKOUT_BUTTON_FINISH, tep_href_link(FILENAME_CHECKOUT_SHIPPING));
			$aBoxes[] = $boxCheckout->iconSafeShopping();
			$aBoxes[] = $boxCheckout->coupon();
		}


		// Incluimos la clase de totalización ot_shipping
		require_once DIR_WS_MODULES . 'order_total/ot_shipping.php';

		// Instanciamos el objeto y obtenemos los valores de envio gratuito
		$ot_shipping = new \ReflectionClass('ot_shipping');
		$ot_shipping = $ot_shipping->newInstanceWithoutConstructor();
		$aFree       = $ot_shipping->getCashLeftFreeShipping();

		// Comprobamos el minimo para mostrar el mensaje de envio gratis
		if ($aFree !== false && $aFree['missing_price_float'] <= CHECKOUT_MESSAGE_FREE_SHIPPING_MISSING) {
			$messageMissingFree = true;
		}

		// Si tenemos envío gratis
		if ($aFree !== false && $aFree['missing_price_float'] == 0) {
			$messageMissingFreeSuccess = true;
			$messageMissingFree        = false;
		}

		// Carrito
		$sHtmlCart = $this->cart();

		$getShippingText = $this->getShippingText();

		// Retornamos el template
		return tools::includeTemplate($sPathTemplate . '/cart_index.php');
	}

	private function _buyAll() {
		global $customer_id, $cart, $languages_id;

		/**
		 * @author Daniel Lucia <daniel.lucia@denox.es>
		 * Comprar de nuevo
		 */
		if (tep_session_is_registered('customer_id') && intval($_GET['buy_all']) > 0) { //Verificamos que esté logueado y que exista el id del pedido
			$sql = "SELECT customers_id FROM " . TABLE_ORDERS . " WHERE orders_id = '" . intval($_GET['buy_all']) . "'";

			$customer_info_query = tep_db_query($sql);
			$customer_info       = tep_db_fetch_array($customer_info_query);

			if ($customer_info['customers_id'] != $customer_id) {
				$this->redirect = tep_href_link(FILENAME_SHOPPING_CART);
				return true;
			}

			$sql            = sprintf('SELECT orders_products_id, products_id, products_quantity FROM orders_products WHERE orders_id = %d', $_GET['buy_all']);
			$products_query = tep_db_query($sql);
			while ($product = tep_db_fetch_array($products_query)) {
				$sql               = sprintf('SELECT products_options_id, products_options_values_id, products_options, products_options_values, reference FROM orders_products_attributes WHERE orders_id = %d AND orders_products_id = %d', $_GET['buy_all'], $product['orders_products_id']);
				$attrributes_query = tep_db_query($sql);

				$attributes = '';
				if (tep_db_num_rows($attrributes_query) > 0) {
					$row    = tep_db_fetch_array($attrributes_query);
					$opt_id = (int)$row['products_options_id'];
					$val_id = (int)$row['products_options_values_id'];
					// Fallback 1: histórico con IDs=0 — resolver por reference de variante
					if (($opt_id === 0 || $val_id === 0) && !empty($row['reference'])) {
						$rq = tep_db_query("SELECT options_id, options_values_id FROM " . TABLE_PRODUCTS_ATTRIBUTES . " WHERE products_id = '" . (int)$product['products_id'] . "' AND reference = '" . tep_db_input($row['reference']) . "' LIMIT 1");
						if (tep_db_num_rows($rq) > 0) {
							$rrow   = tep_db_fetch_array($rq);
							$opt_id = (int)$rrow['options_id'];
							$val_id = (int)$rrow['options_values_id'];
						}
					}
					// Fallback 2: resolver por nombres textuales
					if (($opt_id === 0 || $val_id === 0) && !empty($row['products_options']) && !empty($row['products_options_values'])) {
						$nq = tep_db_query("SELECT pa.options_id, pa.options_values_id FROM " . TABLE_PRODUCTS_ATTRIBUTES . " pa JOIN " . TABLE_PRODUCTS_OPTIONS . " po ON po.products_options_id = pa.options_id JOIN " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov ON pov.products_options_values_id = pa.options_values_id WHERE pa.products_id = '" . (int)$product['products_id'] . "' AND po.products_options_name = '" . tep_db_input($row['products_options']) . "' AND pov.products_options_values_name = '" . tep_db_input($row['products_options_values']) . "' AND po.language_id = '" . (int)$languages_id . "' AND pov.language_id = '" . (int)$languages_id . "' LIMIT 1");
						if (tep_db_num_rows($nq) > 0) {
							$nrow   = tep_db_fetch_array($nq);
							$opt_id = (int)$nrow['options_id'];
							$val_id = (int)$nrow['options_values_id'];
						}
					}
					if ($opt_id > 0 && $val_id > 0) {
						$attributes = [$opt_id => $val_id];
					}
				}

				$cart->add_cart($product['products_id'], $product['products_quantity'], $attributes);
			}
			$this->redirect = tep_href_link(FILENAME_SHOPPING_CART);
			return true;

		}
	}

	/**
	 * Carrito de la compra
	 */
	public function cart($aArguments = []) {
		// Variables
		global $aProducts;
		$buttonDeleteProduct   = isset($aArguments['button_delete_product']) ? $aArguments['button_delete_product'] : true;
		$buttonWishlistProduct = isset($aArguments['button_delete_wishlist']) ? $aArguments['button_delete_wishlist'] : true;
		$buttonClean           = isset($aArguments['button_clean']) ? $aArguments['button_clean'] : true;
		$buttonContinue        = isset($aArguments['button_continue']) ? $aArguments['button_continue'] : true;
		$title                 = isset($aArguments['title']) ? $aArguments['title'] : true;

		// Todas las variables accesibles
		extract($GLOBALS);

		// Productos
		$aProducts = $cart->get_products();

		// Retornamos el template
		return tools::includeTemplate($sPathTemplate . '/cart.php', [
			'buttonDeleteProduct'   => $buttonDeleteProduct,
			'buttonWishlistProduct' => $buttonWishlistProduct,
			'buttonClean'           => $buttonClean,
			'buttonContinue'        => $buttonContinue,
			'title'                 => $title,
		]);
	}

	public function getShippingText() {

		global $cart;

		$nAdd1 = 0;
		$nAdd2 = 0;
		$bajoPedido = false;

		foreach ($cart->get_products() as $aProduct) {
			// Si no tenemos el valor de products_quantity
			if (!isset($aProduct['products_quantity'])) {
				// Obtenemos el ID del producto
				$nID = (isset($aProduct['products_id']) ? $aProduct['products_id'] : $aProduct['id']);
				$nID = (preg_match('/(\{)/i', $nID) ? preg_replace('/(\{)(.*)/i', '', $nID) : $nID);

				// Si el producto tiene atributo
				if (is_array($aProduct['attributes']) && count($aProduct['attributes']) > 0) {
					$nOption = key($aProduct['attributes']);

					// Obtenemos la cantidad del producto del atributo
					$aAux                          = tep_db_query('SELECT products_stock_quantity FROM products_stock WHERE products_id = "' . $nID . '" AND products_stock_attributes = "' . $nOption . '-' . $aProduct['attributes'][$nOption] . '";');
					$aAux                          = tep_db_fetch_array($aAux);
					$aProduct['products_quantity'] = $aAux['products_stock_quantity'];
				} else {
					// Obtenemos la cantidad del producto
					$aAux                          = tep_db_query('SELECT products_quantity FROM ' . TABLE_PRODUCTS . ' WHERE products_id = "' . $nID . '";');
					$aAux                          = tep_db_fetch_array($aAux);
					$aProduct['products_quantity'] = $aAux['products_quantity'];
				}
			}

			// Entre 2 y 6 días
			if ($aProduct['products_quantity'] <= -100 && $aProduct['products_quantity'] >= -150) {
				if ($nAdd1 <= (24 * 2)) {
					$nAdd1 = (24 * 2);
				}

				if ($nAdd2 <= (24 * 6)) {
					$nAdd2 = (24 * 6);
				}

			} // Entre 8 y 13 días
			else if ($aProduct['products_quantity'] <= 0 && $aProduct['products_quantity'] >= -799) {
				if ($nAdd1 <= (24 * 8)) {
					$nAdd1 = (24 * 8);
				}

				if ($nAdd2 <= (24 * 13)) {
					$nAdd2 = (24 * 13);
				}

			} // Bajo pedido
			else if ($aProduct['products_quantity'] <= -800 && $aProduct['products_quantity'] >= -899) {
				$nAdd1      = false;
				$nAdd2      = false;
				$bajoPedido = true;
				break;
			} // Agotado
			else if ($aProduct['products_quantity'] <= -900 && $aProduct['products_quantity'] >= -901) {
				$nAdd1 = false;
				$nAdd2 = false;
				break;
			}
		}

		// Si tenemos predicción
		if ($nAdd1 !== false && $bajoPedido == false) {
			// Obtenemos las dos estimaciones
			$aEstimate1 = getShippingEstimate(true, false, $nAdd1);
			$aEstimate2 = getShippingEstimate(true, false, $nAdd2);

			// Si las fechas son iguales, sumamos un día
			if ($aEstimate1['date'] == $aEstimate2['date']) {
				$aEstimate2 = addHoursToDate($aEstimate2['year'] . '-' . $aEstimate2['month'] . '-' . $aEstimate2['day'], 24);
			}

			// Mostramos el mensaje
			return '<div class="ship gtcrt"><div class="tt tt-6 icon"></div><div>' . str_replace(['%s1', '%s2'], [dateToSpanish(date('l j ' . SHIPPING_PREDICTION_FROM . ' F', strtotime($aEstimate1['year'] . '-' . $aEstimate1['month'] . '-' . $aEstimate1['day']))), dateToSpanish(date('l j ' . SHIPPING_PREDICTION_FROM . ' F', strtotime($aEstimate2['year'] . '-' . $aEstimate2['month'] . '-' . $aEstimate2['day'])))], SHIPPING_PREDICTION_BUY_NOW) . '. <a href="shipping_estimate_more_info.php" rel="nofollow" class="mgp-ajax" title="' . SHIPPING_PREDICTION_MORE_INFO . '" style="display: inline-block;">(+ info.)</a></div></div>';
		} // Si no podemos hacer predicción
		else {
			return '<p>' . SHIPPING_PREDICTION_NONE . '.</p>';
		}

	}

	/**
	 * Metodo con el código antiguo de oscommerce de getcart.php para cambiar cantidades de los productos
	 */
	public function changeQuantity() {
		// Variables
		global $cart;

		if (isset($_POST['quantity']) && $_POST['quantity'] != '' && isset($_POST['products_id']) && $_POST['products_id'] != '') {
			$prid       = $_POST['products_id'];
			$attributes = explode('{', substr($prid, strpos($prid, '{') + 1));

			for ($i = 0, $n = count($attributes); $i < $n; $i++) {
				$pair = explode('}', $attributes[$i]);

				if (is_numeric($pair[0]) && is_numeric($pair[1])) {
					$_POST['id'][$pair[0]] .= $pair[1];
				}
			}

			// Sampedro: Inicio, Atributos por tipo //
			$aAux = [];
			// Si contiene atributos
			if (strstr($prid, '{')) {
				// Obtenemos el id del producto
				$prid = (int)preg_replace('/\{.+$/i', '', $prid);
				// Obtenemos un array con los atributos
				$aAux = tep_get_array_uprid($_POST['products_id']);
			}
			// Sampedro: Fin, Atributos por tipo //

			// Compramos
			$cart->add_cart($prid, $_POST['quantity'], $aAux, false);
		}
	}
}
