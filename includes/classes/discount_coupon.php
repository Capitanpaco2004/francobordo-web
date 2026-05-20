<?php

class discount_coupon {
	public $messages, $coupon, $filters, $applied_discount, $cart_info;
	public $discount_tax = [];

	function __construct($code, $delivery)
	{
		$this->messages = array();
		$this->coupon = array();
		$this->applied_discount = array();
		$this->filters = array( 'categories' => array(), 'products' => array(), 'customers' => array(), 'manufacturers' => array(), 'groups' => array() );

		$this->cart_info = array('valid_products' => array('count' => 0, 'line_items' => 0, 'total' => 0),
			'total_products' => array('count' => 0, 'line_items' => 0, 'total' => 0),
			'exclusions' => array());
		$this->get_coupon($code, $delivery);
		//get the module configuration values for debugging
		if (MODULE_ORDER_TOTAL_DISCOUNT_COUPON_DEBUG == 'true') {
			$check_values_query = tep_db_query($sql = "SELECT configuration_key, configuration_value
                                                    FROM " . TABLE_CONFIGURATION . "
                                                    WHERE configuration_key LIKE 'MODULE_ORDER_TOTAL_DISCOUNT_COUPON%'
                                                      OR configuration_key = 'DISPLAY_PRICE_WITH_TAX'
                                                      OR configuration_key = 'MODULE_SHIPPING_TABLE_STATUS'
                                                      OR configuration_key = 'MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING'");
			while ($row = tep_db_fetch_array($check_values_query)) {
				$this->message('INFO: ' . $row['configuration_key'] . ' = ' . $row['configuration_value'], 'debug');
			}
		}

		// Comprobamos si el grupo de cliente tiene permitidos cupones descuentos //
		$bCoupon = true;
		$customer_group_id = '0';

		if (isset($_SESSION['sppc_customer_group_id']) && $_SESSION['sppc_customer_group_id'] != '0')
			$customer_group_id = $_SESSION['sppc_customer_group_id'];

		// Consulta para obtener las totalizaciones configuradas
		$customer_ot_query = tep_db_query( "select IF(c.customers_order_total_allowed <> '', c.customers_order_total_allowed, cg.group_order_total_allowed) as order_total_allowed from " . TABLE_CUSTOMERS . " c, " . TABLE_CUSTOMERS_GROUPS . " cg where c.customers_id = '" . $customer_id . "' and cg.customers_group_id =  '" . $customer_group_id . "'" );

		// Si hemos obtenido resultado
		if( tep_db_num_rows( $customer_ot_query ) > 0 )
		{
			$customer_ot = tep_db_fetch_array( $customer_ot_query );

			// Si tenemos modulos configurados
			if (tep_not_null($customer_ot['order_total_allowed']) )
			{
				// Separamos en array
				$aAllowed = explode( ';', $customer_ot['order_total_allowed'] );

				// Si no tenemos permitido cupones
				if( ! in_array( 'ot_discount_coupon.php', $aAllowed ) )
					$bCoupon = false;
			}
		}

		if( ! $bCoupon )
		{
			$this->message( ENTRY_DISCOUNT_COUPON_ERROR2 );
			return false;
		}
		else
		{
			// Consultamos para obtener el cupón
			$aAux = tep_db_query( 'SELECT * FROM ' . TABLE_DISCOUNT_COUPONS . ' WHERE coupons_id = "' . tep_db_input( $code ) . '" AND (coupons_date_start <= CURDATE() OR coupons_date_start IS NULL) AND (coupons_date_end >= CURDATE() OR coupons_date_end IS NULL);' );

			// Si SI hemos obtenido un cupón
			if( tep_db_num_rows( $aAux ) > 0 )
			{
				// Registro
				$aAux = tep_db_fetch_array( $aAux );

				// Comprobamos si está activo
				if( $aAux['coupons_status'] == 0 )
				{
					// Mensaje de error
					$this->message( ENTRY_DISCOUNT_COUPON_AVAILABLE_ERROR );
					return false;
				}

				// Obtenemos los filtros del cupón //

				// Obtenemos los filtros
				$aFilters = tep_db_query( 'SELECT * FROM discount_coupons_filters WHERE coupons_id = "' . tep_db_input( $code ) . '";' );

				// Vamos rellenando el array de filtros
				while( $aFilter = tep_db_fetch_array( $aFilters ) )
					$this->filters[$aFilter['filter_type']][] = $aFilter['filter_id'];

				// De cada categoría sacamos sus subcategorías
				foreach( $this->filters['categories'] as $aCategory )
					tep_get_subcategories( $this->filters['categories'], $aCategory );
				$this->filters['categories'] = array_unique( $this->filters['categories'] );

				// Comprobamos el USUARIO del cupón //
				// Si tenemos filtro de usuario
				if( count( $this->filters['customers'] ) > 0 )
				{
					// Comprobamos el filtro de usuario, si no existe mensaje de error
					if( ! in_array( $customer_id, $this->filters['customers'] ) )
						$this->message( ENTRY_DISCOUNT_COUPON_ERROR );
				}

				// Grupos de clientes //
				// Si tenemos filtro de grupo de clientes
				if( count( $this->filters['groups'] ) > 0 )
				{
					// Si no tenemos el grupo de cliente en el filtro retornamos false
					if( ! in_array( getCustomerGroupId(), $this->filters['groups'] ) )
						$this->message( ENTRY_DISCOUNT_COUPON_ERROR );
				}

				// Obtenemos el cupón finalmente
				$this->coupon = $aAux;
			}
			// Si NO hemos obtenido un cupón, mensaje de error
			else
				$this->message( ENTRY_DISCOUNT_COUPON_ERROR );
		}
	}

	function verify_code()
	{
		global $messageStack;

		if (array_key_exists('curl_oe', $_GET)) {
			return true;
		}

		// Comprobamos que el cupón no supere el subtotal
		if (
			isset($this->coupon['coupons_discount_type']) &&
			isset($this->coupon['coupons_discount_amount']) &&
			$this->coupon['coupons_discount_type'] == 'fixed' &&
			$this->coupon['coupons_discount_amount'] > $this->cart_info['valid_products']['total']
		) {
			// Mensaje de error
			$this->message(sprintf(
				ENTRY_DISCOUNT_COUPON_ERROR_MAX_ORDER,
				number_format($this->coupon['coupons_discount_amount'], 2, ',', '.'),
				number_format($this->cart_info['valid_products']['total'], 2, ',', '.')
			));
			return false;
		}

		// Comprobamos el número de veces que se ha usado el cupón si no supera el máximo
		$number_available = $this->coupon['coupons_number_available'] ?? '';
		if ($number_available != 0 && $number_available !== '') {
			return $this->check_num_available();
		}

		// Comprobamos el número de veces que se ha usado el cupón por cliente si no supera el máximo
		$max_use = $this->coupon['coupons_max_use'] ?? '';
		if ($max_use != 0 && $max_use !== '') {
			return $this->check_coupons_max_use();
		}
	}

	function get_coupon($code, $delivery)
	{
		global $customer_id; //needed for customer_exclusions
		// Obtenemos el cup�n //

		// Comprobamos si el grupo de cliente tiene permitidos cupones descuentos //
		$bCoupon = true;
		$customer_group_id = '0';

		if (isset($_SESSION['sppc_customer_group_id']) && $_SESSION['sppc_customer_group_id'] != '0')
			$customer_group_id = $_SESSION['sppc_customer_group_id'];

		// Consulta para obtener las totalizaciones configuradas
		$customer_ot_query = tep_db_query("select IF(c.customers_order_total_allowed <> '', c.customers_order_total_allowed, cg.group_order_total_allowed) as order_total_allowed from " . TABLE_CUSTOMERS . " c, " . TABLE_CUSTOMERS_GROUPS . " cg where c.customers_id = '" . $customer_id . "' and cg.customers_group_id =  '" . $customer_group_id . "'");

		if ($customer_ot = tep_db_fetch_array($customer_ot_query)) {
			if (tep_not_null($customer_ot['order_total_allowed'])) {
				$temp_ot_array = explode(';', $customer_ot['order_total_allowed']);
				if (in_array('ot_discount_coupon.php', $temp_ot_array))
					$bCoupon = true;
			} else
				$bCoupon = true;
		} else
			$bCoupon = true;


		$check_code_query = tep_db_query($sql = "SELECT dc.*
                                                FROM " . TABLE_DISCOUNT_COUPONS . " dc
                                                WHERE coupons_id = '" . tep_db_input($code) . "'
                                                  AND ( coupons_date_start <= CURDATE() OR coupons_date_start IS NULL )
                                                  AND ( coupons_date_end >= CURDATE() OR coupons_date_end IS NULL )");
		if (tep_db_num_rows($check_code_query) != 1) { //if no rows are returned, then they haven't entered a valid code
			$this->message(ENTRY_DISCOUNT_COUPON_ERROR); //display the error message
		} elseif (!$bCoupon)
			$this->message(ENTRY_DISCOUNT_COUPON_ERROR2); //display the error message
		else {
			//customer_exclusions
			$check_user_query = tep_db_query($sql = 'SELECT dc2u.customers_id
                                                  FROM ' . TABLE_DISCOUNT_COUPONS_TO_CUSTOMERS . ' dc2u
                                                  WHERE customers_id=' . (int)$customer_id . '
                                                    AND coupons_id="' . tep_db_input($code) . '"');
			if (tep_db_num_rows($check_user_query) > 0) {
				$this->message(ENTRY_DISCOUNT_COUPON_ERROR); //display the error message
				//use this to debug exclusions:
				//$this->message( 'Customer exclusion check failed' );
			} elseif (!$bCoupon)
				$this->message(ENTRY_DISCOUNT_COUPON_ERROR2); //display the error message
			//shipping zone exclusions

			// Comprobamos si el grupo de cliente tiene permitidos cupones descuentos
			$bCoupon = false;

			if (isset($_SESSION['sppc_customer_group_id']) && $_SESSION['sppc_customer_group_id'] != '0')
				$customer_group_id = $_SESSION['sppc_customer_group_id'];
			else
				$customer_group_id = '0';

			$customer_ot_query = tep_db_query("select IF(c.customers_order_total_allowed <> '', c.customers_order_total_allowed, cg.group_order_total_allowed) as order_total_allowed from " . TABLE_CUSTOMERS . " c, " . TABLE_CUSTOMERS_GROUPS . " cg where c.customers_id = '" . $customer_id . "' and cg.customers_group_id =  '" . $customer_group_id . "'");

			if ($customer_ot = tep_db_fetch_array($customer_ot_query)) {
				if (tep_not_null($customer_ot['order_total_allowed'])) {
					$temp_ot_array = explode(';', $customer_ot['order_total_allowed']);
					if (in_array('ot_discount_coupon.php', $temp_ot_array))
						$bCoupon = true;
				} else
					$bCoupon = true;
			} else
				$bCoupon = true;

			$check_user_query = tep_db_query($sql = 'SELECT dc2z.geo_zone_id
                                                  FROM ' . TABLE_DISCOUNT_COUPONS_TO_ZONES . ' dc2z
                                                  LEFT JOIN ' . TABLE_ZONES_TO_GEO_ZONES . ' z2g
                                                    USING( geo_zone_id )
                                                  WHERE ( z2g.zone_id=' . (int)$delivery['zone_id'] . ' or z2g.zone_id = 0 or z2g.zone_id IS NULL )
                                                    AND ( z2g.zone_country_id=' . (int)$delivery['country_id'] . ' or z2g.zone_country_id = 0 )
                                                    AND dc2z.coupons_id="' . tep_db_input($code) . '"');

			if (tep_db_num_rows($check_user_query) > 0) {
				$this->message(ENTRY_DISCOUNT_COUPON_ERROR); //display the error message
				//use this to debug exclusions:
				//$this->message( 'Shipping Zones exclusion check failed' );
			} elseif (!$bCoupon)
				$this->message(ENTRY_DISCOUNT_COUPON_ERROR2); //display the error message

			//end shipping zone exclusions
			$row = tep_db_fetch_array($check_code_query); //since there is one record, we have a valid code
			if ($row['coupons_status'] == 0) {
				$this->message(ENTRY_DISCOUNT_COUPON_AVAILABLE_ERROR);

			}
			$this->coupon = $row;
		}
	}

	function check_coupons_min_order()
	{
		switch ($this->coupon['coupons_min_order_type']) {
			//minimum number of products:
			case 'quantity':
				global $cart;
				$total = $this->cart_info['valid_products']['count'];
				if ($this->coupon['coupons_min_order'] > $total) { //make sure there are enough products in the cart
					$this->message(sprintf(ENTRY_DISCOUNT_COUPON_MIN_QUANTITY_ERROR, $this->coupon['coupons_min_order']));
					if (MODULE_ORDER_TOTAL_DISCOUNT_COUPON_DEBUG == 'true') $this->message('INFO: Failed to pass check_coupons_min_order(): $total=' . $total, 'debug');
					return false;
				}
				break;
			//minimum price:
			case 'price':
			default:
				global $order, $currencies;
				$total = $this->cart_info['valid_products']['total'];
				//if we display the subtotal without the discount applied, then just compare the subtotal to the minimum order
				if (MODULE_ORDER_TOTAL_DISCOUNT_COUPON_DISPLAY_SUBTOTAL == 'false' && $this->coupon['coupons_min_order'] > $total) {
					$this->message(sprintf(ENTRY_DISCOUNT_COUPON_MIN_PRICE_ERROR, $currencies->format($this->coupon['coupons_min_order'], true, $order->info['currency'], $order->info['currency_value'])) . '.');
					if (MODULE_ORDER_TOTAL_DISCOUNT_COUPON_DEBUG == 'true') $this->message('INFO: Failed to pass check_coupons_min_order(): $total=' . $total, 'debug');
					return false;
					//if we display the subtotal with the discount applied, then we need to compare the subtotal with the discount added back in to the minimum order
				} else if (MODULE_ORDER_TOTAL_DISCOUNT_COUPON_DISPLAY_SUBTOTAL == 'true') {
					$subtotal = $total;
					foreach ($this->applied_discount as $discount) {
						$subtotal += $discount;
					}
					if ($this->coupon['coupons_min_order'] > $subtotal) {
						$this->message(sprintf(ENTRY_DISCOUNT_COUPON_MIN_PRICE_ERROR, $currencies->format($this->coupon['coupons_min_order'], true, $order->info['currency'], $order->info['currency_value'])) . '.');
						if (MODULE_ORDER_TOTAL_DISCOUNT_COUPON_DEBUG == 'true') $this->message('INFO: Failed to pass check_coupons_min_order(): $subtotal=' . $subtotal, 'debug');
						return false;
					}
				}
				break;
		}
		return true;
	}

	function check_coupons_max_use()
	{
		global $customer_id;
		$check_use_query = tep_db_query($sql = "SELECT COUNT(*) AS cnt
                                              FROM " . TABLE_ORDERS . " AS o
                                              INNER JOIN " . TABLE_DISCOUNT_COUPONS_TO_ORDERS . " dc2o
                                                ON dc2o.orders_id=o.orders_id
                                                AND o.customers_id = '" . (int)$customer_id . "'
                                                AND dc2o.coupons_id='" . tep_db_input($this->coupon['coupons_id']) . "'");
		$use = tep_db_fetch_array($check_use_query);
		//show error message if coupons_max_use is equal to the number of times this customer has used the code
		if ($this->coupon['coupons_max_use'] <= $use['cnt']) {
			$this->message(sprintf(ENTRY_DISCOUNT_COUPON_USE_ERROR, $use['cnt'], $this->coupon['coupons_max_use'])); //display the error message for number of times used
			return false;
		}
		return true;
	}

	function check_num_available()
	{
		// Contamos la cantidad de veces que se ha usado el cup�n
		$aCantidad = tep_db_query('SELECT COUNT(*) AS quantity FROM ' . TABLE_DISCOUNT_COUPONS_TO_ORDERS . ' WHERE coupons_id = "' . tep_db_input($this->coupon['coupons_id']) . '";');
		$aCantidad = tep_db_fetch_array($aCantidad);

		// Comprobamos si se ha usado menos del tope
		if ($this->coupon['coupons_number_available'] <= $aCantidad['quantity']) {
			// Mensaje de error
			$this->message(ENTRY_DISCOUNT_COUPON_AVAILABLE_ERROR);
			return false;
		}
		return true;
	}

	function is_recalc_shipping()
	{
		global $order, $language;

		//calculate the order total:
		$order_total = $order->info['total'] - $order->info['shipping_cost'];
		if (DISPLAY_PRICE_WITH_TAX != 'true') $order_total -= $order->info['tax'];

		//check if there is free shipping
		if (strtolower(MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING) == 'true') {
			include(DIR_WS_LANGUAGES . $language . '/modules/order_total/ot_shipping.php');
			//if free shipping is enabled, make sure the discount does not bring the order total below free shipping limit
			if ($order->info['shipping_method'] == FREE_SHIPPING_TITLE) { //if free shipping is the selected shipping method
				if ($order_total < MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER) { //if the discount lowers the total below the free shipping limit
					return true;
				}
			}
		}

		//check if table rate shipping has changed
		if (strtolower(MODULE_SHIPPING_TABLE_STATUS) == 'true') {
			include(DIR_WS_LANGUAGES . $language . '/modules/shipping/table.php');
			if (substr($order->info['shipping_method'], 0, strlen(MODULE_SHIPPING_TABLE_TEXT_TITLE)) == MODULE_SHIPPING_TABLE_TEXT_TITLE && MODULE_SHIPPING_TABLE_MODE == 'price') {
				$table_cost = preg_split("/[:,]/", MODULE_SHIPPING_TABLE_COST);
				for ($i = 0; $i < count($table_cost); $i += 2) {
					if ($order_total <= $table_cost[$i]) {
						$shipping = $table_cost[$i + 1];
						break;
					}
				}
				if ($order->info['shipping_cost'] != $shipping) { //if the discount lowers the total below the table rate
					return true;
				}
			}
		}

		return false;
	}

	function is_allowed_product($product_id)
	{
		// Productos en oferta //

		// Si queremos excluir los productos en oferta
		if ($this->coupon['coupons_specials_exclude']) {
			// Comprobamos si el producto est� en oferta
			$aSpecial = tep_db_query('SELECT specials_id FROM specials WHERE products_id = "' . $product_id . '" AND NOW() < expires_date AND status = 1;');

			// Si tenemos el producto en oferta, no es v�lido
			if (tep_db_num_rows($aSpecial) > 0)
				return false;
		}

		//category exclusion
		if (!is_array($this->cart_info['exclusions']['categories'])) { //only create the array when we need to and only once
			//check to see if the product is in one of the limited categories
			$check_category_query = tep_db_query($sql = 'SELECT categories_id
                                                       FROM ' . TABLE_DISCOUNT_COUPONS_TO_CATEGORIES . '
                                                       WHERE coupons_id="' . tep_db_input($this->coupon['coupons_id']) . '"');
			$this->cart_info['exclusions']['categories'] = array();
			if (tep_db_num_rows($check_category_query) > 0) {
				//for each category, get all the child categories
				while ($categories = tep_db_fetch_array($check_category_query)) {
					$this->cart_info['exclusions']['categories'][] = $categories['categories_id'];
					tep_get_subcategories($this->cart_info['exclusions']['categories'], $categories['categories_id']);
				}
				//$this->excluded_categories are all categories and subcategories excluded from use with the coupon code
				$this->cart_info['exclusions']['categories'] = array_unique($this->cart_info['exclusions']['categories']);
			}
		}
		if (count($this->cart_info['exclusions']['categories']) > 0) {
			$c_path = tep_get_product_path($product_id); //get the product's cPath
			$this_products_catgeory_array = tep_parse_category_path($c_path); //convert the product's cPath into an array
			//if the product's cPath and the excluded categories array have elements in common, then the product is excluded
			$intersection = array_intersect($this_products_catgeory_array, $this->cart_info['exclusions']['categories']);
			if (is_array($intersection) && count($intersection) > 0) {
				//use this to debug exclusions:
				if (MODULE_ORDER_TOTAL_DISCOUNT_COUPON_DEBUG == 'true') $this->message('INFO: Product ' . $category . ' failed manufacturer exclusion check', 'debug');
				return false;
			}
		}
		//end category exclusion

		//product exclusion
		if (!is_array($this->cart_info['exclusions']['products'])) { //only create the array when we need to and only once
			//check to see if the product is in one of the limited categories
			$check_product_query = tep_db_query($sql = 'SELECT products_id
                                                     FROM ' . TABLE_DISCOUNT_COUPONS_TO_PRODUCTS . '
                                                     WHERE coupons_id="' . tep_db_input($this->coupon['coupons_id']) . '"');
			$this->cart_info['exclusions']['products'] = array();
			if (tep_db_num_rows($check_product_query) > 0) {
				while ($products = tep_db_fetch_array($check_product_query)) {
					$this->cart_info['exclusions']['products'][] = $products['products_id'];
				}
			}
		}
		if (count($this->cart_info['exclusions']['products']) > 0) {
			if (in_array($product_id, $this->cart_info['exclusions']['products'])) {
				//use this to debug exclusions:
				if (MODULE_ORDER_TOTAL_DISCOUNT_COUPON_DEBUG == 'true') $this->message('INFO: Product ' . $product_id . ' failed product exclusion check', 'debug');
				return false;
			}
		}
		//end product exclusion

		//end manufacturer exclusion
		//specials exclusion
		if (MODULE_ORDER_TOTAL_DISCOUNT_COUPON_EXCLUDE_SPECIALS == 'true') {
			if (($special_price = tep_get_products_special_price($product_id)) !== null && $special_price !== false) {
				$this->cart_info['exclusions']['products'][] = $product_id;
				if (MODULE_ORDER_TOTAL_DISCOUNT_COUPON_DEBUG == 'true') $this->message('INFO: Product ' . $product_id . ' failed specials exclusion check.  Adding product to excluded products array.', 'debug');
				return false;
			}
		}
		//end specials exclusion

		// Excluir ofertas //

		// Si el cupón excluye ofertas
		$exclude_specials_products = $this->coupon['coupons_specials_exclude'] ?? '';
		if (!empty($exclude_specials_products)) {
			// Comprobamos si el producto está en oferta
			$aSpecial = tep_db_query( 'SELECT products_id FROM ' . TABLE_SPECIALS . ' WHERE products_id = "' . $product_id . '" AND status = "1" AND customers_group_id = "' . getCustomerGroupId() . '";' );

			// Si el producto está en oferta, excluimos
			if( tep_db_num_rows( $aSpecial ) > 0 ) {
				return false;
			}
		}

		// Categorias //

		// Si tenemos filtro de categorias
		if( count( $this->filters['categories'] ) > 0 )
		{
			// Variables
			$bReturn = false;

			// Obtenemos las categorias del producto
			$aCategories = tep_db_query( 'SELECT * FROM ' . TABLE_PRODUCTS_TO_CATEGORIES . ' WHERE products_id = ' . $product_id . ';' );

			// Recorremos las categorias
			while( $aCategory = tep_db_fetch_array( $aCategories ) )
				if( in_array( $aCategory['categories_id'], $this->filters['categories'] ) )
					$bReturn = true;

			// Retornamos el resultado si es falso
			if( ! $bReturn ) return false;
		}

		// Productos //
		// Si tenemos filtro de productos
		if( count( $this->filters['products'] ) > 0 )
		{
			// Si no tenemos el producto en el filtro retornamos false
			if( ! in_array( $product_id, $this->filters['products'] ) )
				return false;
		}

		// Fabricantes //

		// Si tenemos filtro de fabricantes
		if( count( $this->filters['manufacturers'] ) > 0 )
		{
			// Obtenemos el fabricante del producto
			$aManufacturer = tep_db_query( 'SELECT manufacturers_id FROM ' . TABLE_PRODUCTS . ' WHERE products_id = ' . $product_id . ';' );
			$aManufacturer = tep_db_fetch_array( $aManufacturer );

			// Si no tenemos el fabricante en el filtro retornamos false
			if( ! in_array( $aManufacturer['manufacturers_id'], $this->filters['manufacturers'] ) )
				return false;
		}



		return true;
	}

	function is_exists_exclusions()
	{
		if ($this->cart_info['valid_products']['total'] != $this->cart_info['total_products']['total']) return true;
		if ($this->cart_info['valid_products']['count'] != $this->cart_info['total_products']['count']) return true;
		return false;
	}

	//this function is for tracking the product totals and count so that we can correctly calculate the discount
	function total_valid_products($products = array())
	{
		global $cart;
		for ($i = 0; $i < count($products); $i++) {
			if (DISPLAY_PRICE_WITH_TAX == "true") {
				$product_tax = tep_get_tax_rate($products[$i]['tax_class_id'], $tax_address['entry_country_id'], $tax_address['entry_zone_id']);
				$price = (tep_add_tax($products[$i]['price'], $product_tax) + $cart->attributes_price($products[$i]['id'])) * $products[$i]['quantity'];

			} else {
				$price = ($products[$i]['price'] + $cart->attributes_price($products[$i]['id'])) * $products[$i]['quantity'];
			}
			$this->cart_info['total_products']['total'] += $price;
			$this->cart_info['total_products']['count'] += $products[$i]['quantity'];
			$this->cart_info['total_products']['line_items']++;
			if ($this->is_allowed_product(tep_get_prid($products[$i]['id']))) { //not an excluded product
				$this->cart_info['valid_products']['count'] += $products[$i]['quantity'];
				$this->cart_info['valid_products']['total'] += $price;
				$this->cart_info['valid_products']['line_items']++;
			}
		}
	}

	function calculate_discount($product = array(), $current_product = 0)
	{
		if (!$this->is_allowed_product(tep_get_prid($product['id']))) { //check that the product isn't excluded
			$applied_discount = 0; //don't apply a discount
			if (MODULE_ORDER_TOTAL_DISCOUNT_COUPON_DEBUG == 'true') $this->message('INFO: Excluded product ' . $product['id'] . '. Discount of ' . $applied_discount . ' not applied.', 'debug');
		} else {
			switch ($this->coupon['coupons_discount_type']) {
				case 'shipping':
					$applied_discount = 0;
					break;
				case 'fixed':
					// Obtenemos el porcentaje
					$percent_product = ((($this->apply_tax($product['final_price'], $product['tax'], false, true) * 100) * $product['qty']) / $this->cart_info['valid_products']['total']);

					// Porcentaje del cup�n
					// Convencion Francobordo: coupons_discount_amount viene con IVA incluido.
					// Convertimos a base imponible para que apply_tax/finalize_discount sumen el IVA y obtengan el valor original con IVA.
					$coupon_amount_base = $this->coupon['coupons_discount_amount'] / (1 + ($product['tax'] / 100));

					// Porcentaje del cupon (sobre la base imponible)
					$applied_discount = (($percent_product * $coupon_amount_base) / 100);
					//$applied_discount = $applied_discount / ( ( $product['tax'] / 100 ) + 1 );


					/*
					$percentage_applied = $this->coupon['coupons_discount_amount'] / $this->cart_info['valid_products']['total'];

					$applied_discount = $product['final_price'] - $percentage_applied;

					if( $this->cart_info['valid_products']['line_items'] == ( $current_product + 1 ) )
					{
						$difference = $this->coupon['coupons_discount_amount'] - ( array_sum( $this->applied_discount ) + $applied_discount );

						if( $difference != 0 )
							$applied_discount += $difference;
					}
					*/
					break;
				case 'percent':
					$applied_discount = $product['final_price'] * $this->coupon['coupons_discount_amount'] * $product['qty'];
					break;
			}
			if (MODULE_ORDER_TOTAL_DISCOUNT_COUPON_DEBUG == 'true') $this->message('INFO: Product ' . $product['id'] . ' passed exclusion check.  Discount ' . $applied_discount . ' applied. (' . $this->coupon['coupons_discount_type'] . ')', 'debug');
		}

		//now determine how we need to handle tax:
		if (MODULE_ORDER_TOTAL_DISCOUNT_COUPON_DISPLAY_TAX != 'None') {
			$discount_tax = $this->apply_tax($applied_discount, $product['tax'], false, true) - $applied_discount;
			if (MODULE_ORDER_TOTAL_DISCOUNT_COUPON_DEBUG == 'true') $this->message('INFO: Tax not applied to product ' . $product['id'] . ': ' . $discount_tax, 'debug');
		} else $discount_tax = 0;

		//tally the discount tax amount for each tax group
		if (isset($this->discount_tax[$product['tax_description']])) $this->discount_tax[$product['tax_description']] += $discount_tax;
		else $this->discount_tax[$product['tax_description']] = $discount_tax;

		//tally the discount amount for each tax group
		if (isset($this->applied_discount[$product['tax_description']])) $this->applied_discount[$product['tax_description']] += $applied_discount;
		else $this->applied_discount[$product['tax_description']] = $applied_discount;

		$discount = array('applied_discount' => $applied_discount, 'discount_tax' => $discount_tax);
		return $discount;
	}

	function calculate_shown_price($discount, $product)
	{
		$actual_shown_price = null;
		if (MODULE_ORDER_TOTAL_DISCOUNT_COUPON_DEBUG == 'true') $this->message('INFO: Discount of ' . ($discount['applied_discount'] + $discount['discount_tax']) . ' applied to product ' . $product['id'] . ' ($' . $product['final_price'] * $product['qty'] . ').', 'debug');
		if (MODULE_ORDER_TOTAL_DISCOUNT_COUPON_DISPLAY_SUBTOTAL == 'false') {
			//we don't want to display the subtotal with the discount applied, so apply the discount then set the applied_discount variable to zero so that it's not added into the order subtotal, but is still used to correctly calculate tax
			@$actual_shown_price = ($this->apply_tax($product['final_price'], $product['tax']) * $product['qty']) - ($discount['applied_discount'] + $discount['discount_tax']);
			$applied_discount = 0;
			$shown_price = $this->apply_tax($product['final_price'], $product['tax']) * $product['qty']; //$product['final_price'] * $product['qty'];
		} else {
			$shown_price = ($this->apply_tax($product['final_price'], $product['tax']) * $product['qty']) - ($discount['applied_discount'] + $discount['discount_tax']);
		}
		//if we need to display the subtotal without the discount applied, then add the shown price to the subtotal, then change shown price to the price with the applied discount in order to properly calculate taxes
		if (!isset($actual_shown_price)) $actual_shown_price = $shown_price;
		if (MODULE_ORDER_TOTAL_DISCOUNT_COUPON_DEBUG == 'true') $this->message('INFO: Calculating tax on ' . $actual_shown_price . '.  Displayed price ' . $shown_price . '.', 'debug');

		$shown_price_array = array('shown_price' => $shown_price, 'actual_shown_price' => $actual_shown_price);
		return $shown_price_array;
	}

	function finalize_discount($info)
	{
		//make sure we meet the order minimum
		if (!$this->check_coupons_min_order()) {
			$this->applied_discount = array();

			//add on to the min_order error message since we have excluded items
			if ($this->is_exists_exclusions()) {
				$this->message(ENTRY_DISCOUNT_COUPON_EXCLUSION_ERROR);
			}
		}
		if (!$this->is_errors()) { //if there are no errors, we can apply the discount
			if ($this->coupon['coupons_discount_type'] == 'shipping') { //discount shipping if the coupon type is shipping
				if ($this->cart_info['valid_products']['count'] > 0) {
					$this->applied_discount['shipping'] = $info['shipping_cost'] * $this->coupon['coupons_discount_amount'];
					$this->applied_discount['shipping'] = $this->applied_discount['shipping'];
					$info['total'] -= $this->applied_discount['shipping'];
					if (MODULE_ORDER_TOTAL_DISCOUNT_COUPON_DEBUG == 'true') {
						$this->message('INFO: Shipping Discount of ' . $this->applied_discount['shipping'] . ' applied.', 'debug');
					}
				}
			} else if (MODULE_ORDER_TOTAL_DISCOUNT_COUPON_DISPLAY_SUBTOTAL != 'true') {
				//subtract the discount from the order total only if the subtotal does NOT already include it
				foreach ($this->applied_discount as $sTax => $discount) {
					// Obtenemos el iva
					$sTaxAux = preg_replace('/[^0-9]/', '', $sTax);
					$sTaxAux = 1 + ($sTaxAux / 100);

					$info['total'] -= $discount * $sTaxAux;
					if (MODULE_ORDER_TOTAL_DISCOUNT_COUPON_DEBUG == 'true') {
						$this->message('INFO: Discount of ' . $discount . ' applied to order total.', 'debug');
					}
				}
			}
			if (MODULE_ORDER_TOTAL_DISCOUNT_COUPON_ALLOW_NEGATIVE == 'false' && $info['total'] < 0) {
				$info['total'] = 0;
			}
		}
		return $info['total'];
	}

	function format_display($tax_group = '') {
		// Variables
		global $order, $currencies;

		// Obtenemos el tipo de muestreo
		$display = (MODULE_ORDER_TOTAL_DISCOUNT_COUPON_USE_LANGUAGE_FILE == 'true' ? MODULE_ORDER_TOTAL_DISCOUNT_COUPON_DISPLAY_FILE : MODULE_ORDER_TOTAL_DISCOUNT_COUPON_DISPLAY_CONFIG);

		// Según el tipo de cupón
		switch ($this->coupon['coupons_discount_type']) {
			// Envío
			case 'shipping':
				$discount_amount = $this->coupon['coupons_discount_amount'] . '% ' . MODULE_ORDER_TOTAL_DISCOUNT_COUPON_TEXT_SHIPPING_DISCOUNT;
				break;
			// Porcentual
			case 'percent':
				$discount_amount = $this->coupon['coupons_discount_amount'] . '%';
				break;
			// Fijo
			case 'fixed':
				$discount_amount = $currencies->format($this->coupon['coupons_discount_amount']);
				break;
		}

		// Pedido mínimo
		$min_order = ($this->coupon['coupons_min_order'] != 0 ? ($this->coupon['coupons_min_order_type'] == 'price' ? $currencies->format($this->coupon['coupons_min_order']) : (int)$this->coupon['coupons_min_order']) : '');

		// Reemplazamos las variables con su valor correcto
		$display = str_replace('[code]', $this->coupon['coupons_id'], $display);
		$display = str_replace('[discount_amount]', $discount_amount, $display);
		$display = str_replace('[coupon_desc]', $this->coupon['coupons_description'], $display);
		$display = str_replace('[min_order]', $min_order, $display);
		$display = str_replace('[number_available]', $this->coupon['coupons_number_available'] ?? '', $display);
		$display = str_replace('[tax_desc]', $tax_group, $display);
		return $display;
	}

	function apply_tax($price, $tax, $round = false, $force = false)
	{
		// Si queremos mostrar los impuestos o est� forzado
		if (DISPLAY_PRICE_WITH_TAX == 'true' || $force) {
			// A�adimos los impuestos
			if ($tax != 0)
				$price = tep_add_tax($price, $tax);
		}
		// Si queremos redondear
		if ($round) {
			// Redondeamos el valor
			global $currencies;
			$price = tep_round($price, $currencies->currencies[DEFAULT_CURRENCY]['decimal_places']);
		}
		return $price;
	}

	function is_errors()
	{
		// Retornamos si tenemos errores
		if (is_array($this->messages['error']))
			return count($this->messages['error']) > 0;
	}

	function message($message, $error_level = 'error')
	{
		// A�adimos el mensaje de error
		$this->messages[$error_level][] = $message;
	}

	function get_messages($error_level = 'error')
	{
		// Retornamos los mensajes de error
		return $this->messages[$error_level];
	}
}

?>
