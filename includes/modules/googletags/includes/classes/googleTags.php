<?php
	// Alias
	namespace denox;

	/**
	* Clase googleTags
	*/
	class googleTags
	{
		private $adsid;
		private $adslabel;

		/**
		* Constructor de la clase
		*/
		public function __construct()
		{
			global $languages_id;

			// Descomponemos Ads ID
			$aGoogleAdsID = json_decode( stripslashes( GOOGLETAG_ADS_ID ), true );

			// Sólo obtenemos ID si tenemos valor en el idioma actual o si no tenemos marcado que vaya por dominios, en cuyo caso obtendremos el ID general.
			$this->adsid = (isset( $aGoogleAdsID[$languages_id] ) && $aGoogleAdsID[$languages_id] != '' ? $aGoogleAdsID[$languages_id] : (GOOGLETAG_DOMAINS == 'true' ? '' : $aGoogleAdsID[3]));

			// Descomponemos Ads Label
			$aGoogleAdsLabel = json_decode( stripslashes( GOOGLETAG_ADS_CONVERSION_LABEL ), true );

			// Sólo obtenemos ID si tenemos valor en el idioma actual o si no tenemos marcado que vaya por dominios, en cuyo caso obtendremos el ID general.
			$this->adslabel = (isset( $aGoogleAdsLabel[$languages_id] ) && $aGoogleAdsLabel[$languages_id] != '' ? $aGoogleAdsLabel[$languages_id] : (GOOGLETAG_DOMAINS == 'true' ? '' : $aGoogleAdsLabel[3]));
		}

		/**
		* Google Tag Ads: Función para el seguimiento de conversiones
		*/
		public function AnalyticsTrackingGlobal()
		{
			// Si esta activo
			if( defined( 'GOOGLETAG_ANALYTICS_ID' ) && !empty( GOOGLETAG_ANALYTICS_ID ) )
			{
				global $customer_id, $languages_id;

				//Variables
				$gtag_ads = '';

				if( $this->adsid != '' ) {
					$gtag_ads = 'gtag("config", "' . $this->adsid . '");';
				}

				//Si tenemos User ID porque tenemos Login, lo añadimos al código para el seguimiento de conversiones
				if (isset($customer_id) && $this->adsid != '')
				{
					$gtag_userID = 'gtag("set", {"user_id": "' . $customer_id . '"});'; 'gtag("set", "' . $this->adsid . '")';
				}

				//Si es el Robot de Google Pagespeed no introduzca el codigo de seguimiento
				if (!isset($_SERVER['HTTP_USER_AGENT']) || stripos($_SERVER['HTTP_USER_AGENT'], 'Speed Insights') === false)
				{
					// Descomponemos Analytics ID
					$aGoogleAnalyticsID = json_decode( stripslashes( GOOGLETAG_ANALYTICS_ID ), true );

					// Sólo obtenemos ID si tenemos valor en el idioma actual o si no tenemos marcado que vaya por dominios, en cuyo caso obtendremos el ID general.
					$sAnalyticsID = (isset( $aGoogleAnalyticsID[$languages_id] ) && $aGoogleAnalyticsID[$languages_id] != '' ? $aGoogleAnalyticsID[$languages_id] : (GOOGLETAG_DOMAINS == 'true' ? '' : $aGoogleAnalyticsID[3]));

					// Si tenemos finalmente ID
					if( $sAnalyticsID != '' ) {
						echo '<!-- Global site tag (gtag.js) - Google Analytics -->
						<script async src="https://www.googletagmanager.com/gtag/js?id=' . $sAnalyticsID . '"></script>
						<script>
						window.dataLayer = window.dataLayer || [];
						function gtag(){dataLayer.push(arguments);}
						gtag("js", new Date());
						' . $gtag_userID . '
						gtag("config", "' . $sAnalyticsID . '");
						' . $gtag_ads . '
						</script>';
					}
				}
			}
			elseif( $this->adsid != '' )
			{
				//Si es el Robot de Google Pagespeed no introduzca el codigo de seguimiento
				if (!isset($_SERVER['HTTP_USER_AGENT']) || stripos($_SERVER['HTTP_USER_AGENT'], 'Speed Insights') === false)
				{
					echo '<!-- Global site tag (gtag.js) - Google Adwords -->
					<script async src="https://www.googletagmanager.com/gtag/js?id=' . $this->adsid . '"></script>
					<script>
					window.dataLayer = window.dataLayer || [];
					function gtag(){dataLayer.push(arguments);}
					gtag("js", new Date());

					gtag("config", "' . $this->adsid . '");
					</script>';
				}
			}
		}


		/**
		* Google Tag Analytics eCommerce: Función Para el Comercio Electrónico de Analytics
		*/
		public function AnalyticsEventPurchased()
		{
			// Si el cliente ha activado el módulo, entramos
			if( GOOGLETAG_ANALYTICS_ECOMMERCE_ENHACED == 'si')
			{
				global $customer_id, $languages_id;

				// Sacamos los detalles del pedido
				$orders_query = tep_db_query("select orders_id from " . TABLE_ORDERS . " where customers_id = '" . (int)$customer_id . "' order by date_purchased desc limit 1");
				$orders = tep_db_fetch_array($orders_query);
				$order_id = $orders['orders_id'];

				// Sacamos los Valores de Totalización (Total, Envio e IVA)
				$totals_query = tep_db_query("select value, class from " . TABLE_ORDERS_TOTAL . " where orders_id = '" . (int)$order_id . "' order by sort_order");

				while ($totals = tep_db_fetch_array($totals_query))
				{
					if ($totals['class'] == 'ot_total')
					{
					    $nTotalValue = number_format($totals['value'], 2, '.', '');
					}
					else if ($totals['class'] == 'ot_tax')
					{
					    $nTaxValue = number_format($totals['value'], 2, '.', '');
					}
					else if ($totals['class'] == 'ot_shipping')
					{
					    $nShippingValue = number_format($totals['value'], 2, '.', '');
					}
				}

				//Llamamnos a la funcion para sacar el array de productos
				$aProductsPurchased = $this->AnalyticsProductListOrder($order_id);

				echo "<script>
						gtag('event', 'purchase', {
						  'transaction_id': '" . $order_id . "',
						  'affiliation': '" . STORE_NAME . "',
						  'value': " . $nTotalValue . ",
						  'currency': 'EUR',
						  'tax': " . $nTaxValue . ",
						  'shipping': " . $nShippingValue . ",
						  'items': [
							  " . $aProductsPurchased . "
						  ]
						});
					</script>";
			}
		}

		/**
		* Google Tag Analytics eCommerce: Función Para Sacar el Array de Productos comprados para Comercio Electrónico de Analytics
		*/
		private function AnalyticsProductListOrder($order_id)
		{
			global $languages_id;

			$result = array();

			$products_query = tep_db_query("select products_id, products_model, products_name, final_price, products_tax, products_cost, products_quantity from " . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . $order_id . "' order by products_name");

			while ($product = tep_db_fetch_array($products_query))
			{
				$category_query = tep_db_query("select p2c.categories_id, cd.categories_name from " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c, " . TABLE_CATEGORIES_DESCRIPTION . " cd where p2c.products_id = '" . $product['products_id'] . "' AND cd.categories_id = p2c.categories_id AND cd.language_id = '" . (int)$languages_id . "'");
				$category = tep_db_fetch_array($category_query);

				//Sacamos Precio del Beneficio (Precio IVA Includo - Precio Coste)
				$products_price = number_format(($product['final_price']*(1+($product['products_tax']/100))), 2, '.', '');
				$precio_beneficio = number_format(number_format(($product['final_price']*(1+($product['products_tax']/100))), 2, '.', '')  - number_format($product['products_cost'], 2, '.', ''), 2, '.', '');

				//Quitamos comilla que tiene en algunos nombres de productos que rompen la cadena JS
				$product['products_name'] = str_replace('"', '', $product['products_name']);

				//Si no tiene Modelo, modifico el campo por el ID del producto
				if($product['products_model'] == '')
				{
					$product['products_model'] = $product['products_id'];
				}

			    array_push($result, "{
			        'id': '" . $product['products_id'] . "',
			        'name': '" . $product['products_name'] . "',
			        'category': '" . $category['categories_name'] ."',
			        'quantity': '" . $product['products_quantity'] . "',
			        'price': '" . $products_price . "'
			    }");
			}
			return implode(",", $result);
		}

		/**
		* Google Tag Analytics eCommerce: Función Para el Seguimiento de las Consultas de los Detalles del Producto
		*/
		public function AnalyticsEventViewProduct()
		{
			global $aProducto;

			//Formateo el precio del producto
			if (!empty($aProducto)) {
				$aProducto['PRECIO_ANALYTICS'] = str_replace( ',', '.', str_replace( '&euro;', '', $aProducto['PRECIO'] ));

				echo "<script>
						gtag('event', 'view_item', {
						'items': [
							{
							'id': '" . $aProducto['products_id'] . "',
							'name': '" . $aProducto['products_name'] . "',
							'brand': '" . $aProducto['manufacturers_name'] . "',
							'quantity': " . $aProducto['products_quantity'] . ",
							'price': '" . $aProducto['PRECIO_ANALYTICS'] . "'
							}
						]
						});
					</script>";
			}
			
		}

		/**
		* Google Tag Ads Remarketing Dynamic Tags: Función para recoger los datos de navegación para poder realizar Remarketing Dinámico de Shopping desde Adwords
		*/
		public function RemarketingDynamics()
		{
		if($_SERVER['REMOTE_ADDR'] == '2.154.140.91')
		{
			if( $this->adsid != '' )
			{
				global $aProducto, $sTitular;

				//Sacamos el nombre del archivo PHP para saber que valores necesitamos
				$sFileName  = ltrim(basename($_SERVER['SCRIPT_NAME']));

				//Según la página en la que nos encontremos, ajustamos los valores necesarios para el evento
				switch($sFileName)
				{
					//Página de Inicio
					case FILENAME_DEFAULT:
						$sPageType = 'home';
						break;
					//Categorías
					case FILENAME_CATEGORIES:
					case FILENAME_MANUFACTURERS:
						$sPageType = 'category';
						$sCategoryName = $sTitular;
						break;
					//Resultados de Búsqueda
					case FILENAME_SEARCH:
						$sPageType = 'searchresults';
						break;
					//Ficha del Producto
					case FILENAME_PRODUCT_INFO:
						$sPageType = 'product';
						$sProdId = $aProducto['products_id'];
						$sTotalValue = str_replace( ',', '.', str_replace( '&euro;', '', $aProducto['PRECIO'] ));
						break;
					//Carrito o proceso de compra
					case FILENAME_SHOPPING_CART:
					case FILENAME_CHECKOUT:
					case FILENAME_CHECKOUT_SHIPPING:
					case FILENAME_CHECKOUT_PAYMENT:
					case FILENAME_CHECKOUT_PAYMENT_EXT:
					case FILENAME_CHECKOUT_CONFIRMATION:
						$sPageType = 'cart';
						break;
					//Pedido Finalizado
					case FILENAME_CHECKOUT_SUCCESS:
						$sPageType = 'purchase';
						break;
					//Otras secciones de la web
					default:
						$sPageType = 'other';
				}

				echo "<script>
						gtag('event', 'page_view', {
							'send_to': '". $this->adsid . "';
							'ecomm_pagetype': '" . $sPageType . "',
							'ecomm_prodid': " . $sProdId . ",
							'ecomm_totalvalue': " . $sTotalValue . ",
							'ecomm_category': '" . $sTitular . "'
						});
					</script>";

					/* 	<script>
	  gtag('event', 'page_view', {
	    'send_to': 'AW-786664703',
	    'value': 'replace with value',
	    'items': [{
	      'id': 'replace with value',
	      'google_business_vertical': 'retail'
	    }]
	  });
	</script> */
			}
		}
		}

		/**
		* Google Tag Ads: Función para el seguimiento de conversiones
		*/
		public function AdsConversionTracking()
		{
			if( $this->adsid != '' )
			{
				global $customer_id;

				// Sacamos los detalles del pedido
				$orders_query = tep_db_query("select orders_id from " . TABLE_ORDERS . " where customers_id = '" . (int)$customer_id . "' order by date_purchased desc limit 1");
				$orders = tep_db_fetch_array($orders_query);
				$order_id = $orders['orders_id'];

				// Sacamos los valores para el Total del Valor de Conversion
				$totals_query = tep_db_query("select value from " . TABLE_ORDERS_TOTAL . " where orders_id = '" . (int)$order_id . "' and class = 'ot_total' order by sort_order");
				$total = tep_db_fetch_array($totals_query);
				$total_value = number_format($total['value'], 2);

			?>
				<script>
				  gtag('event', 'conversion', {
				      'send_to': '<?php echo $this->adsid; ?><?php echo ($this->adslabel != "" ? '/'. $this->adslabel : ''); ?>',
				      'value': <?php echo $total_value; ?>,
				      'currency': 'EUR',
				      'transaction_id': '<?php echo $order_id; ?>'
				  });
				</script>
			<?php
			}
		}

		/**
		* Google Search Console: Función para mostrar el código de la metaetiqueta para la verificación de la cuenta
		*/
		public function SearchConsoleVerification()
		{
			global $languages_id;

			// Descomponemos Search ID
			$aGoogleSearchID = json_decode( stripslashes( GOOGLETAG_SEARCHCONSOLE_ID ), true );

			// Sólo obtenemos ID si tenemos valor en el idioma actual o si no tenemos marcado que vaya por dominios, en cuyo caso obtendremos el ID general.
			$sSearchID = (isset( $aGoogleSearchID[$languages_id] ) && $aGoogleSearchID[$languages_id] != '' ? $aGoogleSearchID[$languages_id] : (GOOGLETAG_DOMAINS == 'true' ? '' : $aGoogleSearchID[3]));

			if( $sSearchID != '' ) {
				echo '<meta name="google-site-verification" content="' . $sSearchID . '" />'."\n";
			}
		}
	}
?>