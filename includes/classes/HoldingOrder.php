<?php

namespace util;

use RedsysConsultasPHP\Client\Client as RedsysClient;
use util\RedsysTransactionManager;
use DateTime;

class HoldingOrder
{
	public function __invoke($order, $order_totals, $customer_id, $aOptionsInsertUser)
	{
		global $languages_id;

		// Eliminar holding orders previos del mismo cliente para evitar duplicados
		HoldingOrderManager::removeAllHoldingOrdersFromCustomer((int)$customer_id);

		// Consulta para obtener el valor máximo de orders_id de ambas tablas
		$max_orders_query = tep_db_query("
			SELECT MAX(orders_id) AS max_id
			FROM (
				SELECT orders_id FROM " . TABLE_ORDERS . "
				UNION ALL
				SELECT orders_id FROM " . TABLE_HOLDING_ORDERS . "
			) t
		");

		$max_orders = tep_db_fetch_array($max_orders_query);
		$insert_id = $max_orders["max_id"] + 1;

		$sql_data_array = array(
			'orders_id' => $insert_id,
			'customers_id' => $customer_id,
			'customers_name' => $order->customer['firstname'] . ' ' . $order->customer['lastname'],
			'customers_company' => $order->customer['company'],
			'customers_street_address' => $order->customer['street_address'],
                          	'customers_suburb' => $order->customer['suburb'],
			'customers_city' => $order->customer['city'],
			'customers_postcode' => $order->customer['postcode'],
			'customers_state' => $order->customer['state'],
			'customers_country' => $order->customer['country']['title'],
			'customers_telephone' => $order->customer['telephone'],
			'customers_email_address' => $order->customer['email_address'],
			'customers_address_format_id' => $order->customer['format_id'],

			'delivery_name' => $order->delivery['firstname'] . ' ' . $order->delivery['lastname'],
			'delivery_company' => $order->delivery['company'],
			'delivery_street_address' => $order->delivery['street_address'],
							'delivery_telephone' => $order->delivery['telephone'],
                          	'delivery_suburb' => $order->delivery['suburb'],
			'delivery_city' => $order->delivery['city'],
			'delivery_postcode' => $order->delivery['postcode'],
			'delivery_state' => $order->delivery['state'],
			'delivery_country' => $order->delivery['country']['title'],
			'delivery_address_format_id' => $order->delivery['format_id'],

			'billing_name' => $order->billing['firstname'] . ' ' . $order->billing['lastname'],
			'billing_company' => $order->billing['company'],
			'billing_nif' => $order->billing['nif'],
			'billing_street_address' => $order->billing['street_address'],
                          	'billing_suburb' => $order->billing['suburb'],
			'billing_city' => $order->billing['city'],
			'billing_postcode' => $order->billing['postcode'],
			'billing_state' => $order->billing['state'],
			'billing_country' => $order->billing['country']['title'],
			'billing_address_format_id' => $order->billing['format_id'],

			'payment_method' => $order->info['payment_method'],
			'shipping_module' => $GLOBALS['shipping']['id'] ?? null,
			'date_purchased' => 'now()',

			'orders_status' => $order->info['order_status'],
			'currency' => $order->info['currency'],
			'currency_value' => $order->info['currency_value']
		);

		tep_db_perform(TABLE_HOLDING_ORDERS, $sql_data_array);

		for ($i = 0, $n = count($order_totals); $i < $n; $i++) {
			$sql_data_array = array(
				'orders_id' => $insert_id,
				'title' => $order_totals[$i]['title'],
				'text' => $order_totals[$i]['text'],
				'value' => $order_totals[$i]['value'],
				'class' => $order_totals[$i]['code'],
				'sort_order' => $order_totals[$i]['sort_order']
			);
			tep_db_perform(TABLE_HOLDING_ORDERS_TOTAL, $sql_data_array);
		}

		$sql_data_array = array(
			'orders_id' => $insert_id,
			'orders_status_id' => $order->info['order_status'],
			'date_added' => 'now()',
			'customer_notified' => $customer_notification,
			'comments' => $order->info['comments']
		);
		tep_db_perform(TABLE_HOLDING_ORDERS_STATUS_HISTORY, $sql_data_array);

		for ($i = 0, $n = count($order->products); $i < $n; $i++) {
			// Calcular products_stock_attributes sin descontar stock
			// El stock se descuenta cuando el pedido se mueve a orders (HoldingOrderManager)
			$products_stock_attributes = null;

			if (isset($order->products[$i]['attributes']) && is_array($order->products[$i]['attributes'])) {
				$products_stock_attributes_array = array();
				foreach ($order->products[$i]['attributes'] as $attribute) {
					if (isset($attribute['track_stock']) && $attribute['track_stock'] == 1) {
						$products_stock_attributes_array[] = $attribute['option_id'] . "-" . $attribute['value_id'];
					}
				}
				if (!empty($products_stock_attributes_array)) {
					$products_stock_attributes = implode(",", $products_stock_attributes_array);
				}
			}

			$sql_data_array = array('orders_id' => $insert_id,
				'products_id' => tep_get_prid($order->products[$i]['id']),
				'products_model' => $order->products[$i]['model'],
				'product_ean' => $order->products[$i]['ean'],
				'products_ubicacion' => $order->products[$i]['ubicacion'],
				'products_name' => $order->products[$i]['name'],
				'products_price' => $order->products[$i]['price'],
				'final_price' => $order->products[$i]['final_price'],
				'products_tax' => $order->products[$i]['tax'],
				'products_quantity' => $order->products[$i]['qty'],
				'products_cost' => $order->products[$i]['cost'],
				'products_stock_attributes' => $products_stock_attributes);

			tep_db_perform(TABLE_HOLDING_ORDERS_PRODUCTS, $sql_data_array);
			$order_products_id = tep_db_insert_id();

			$products_ordered_attributes = '';
			if (isset($order->products[$i]['attributes'])) {
				for ($j = 0, $n2 = count($order->products[$i]['attributes']); $j < $n2; $j++) {
					$attributes = tep_db_query("select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix, pa.products_attributes_id, pa.reference, pa.products_attributes_ean
													from " . TABLE_PRODUCTS_OPTIONS . " popt,
													" . TABLE_PRODUCTS_OPTIONS_VALUES . " poval,
													" . TABLE_PRODUCTS_ATTRIBUTES . " pa
													where " . (!in_array((int)$order->products[$i]['attributes'][$j]['option_id'], $aOptionsInsertUser) ? "pa.options_values_id = '" . (int)$order->products[$i]['attributes'][$j]['value_id'] . "' and " : "") . "
													pa.products_id = '" . (int)$order->products[$i]['id'] . "' and pa.options_id = '" . (int)$order->products[$i]['attributes'][$j]['option_id'] . "' and pa.options_id = popt.products_options_id and pa.options_values_id = poval.products_options_values_id and popt.language_id = '" . (int)$languages_id . "' and poval.language_id = '" . (int)$languages_id . "'");

					$attributes_values = tep_db_fetch_array($attributes);

					// Sampedro: Inicio, Atributos por tipo //
					if (in_array((int)$order->products[$i]['attributes'][$j]['option_id'], $aOptionsInsertUser))
						$attributes_values['products_options_values_name'] = nl2br(urldecode($order->products[$i]['attributes'][$j]['value_id']));
					// Sampedro: Fin, Atributos por tipo //

					// BOF Separate Pricing Per Customer attribute_groups mod
					if (isset($_SESSION['sppc_customer_group_id']) && $_SESSION['sppc_customer_group_id'] != '0') {
						$attributes_group_query = tep_db_query("select pag.options_values_price, pag.price_prefix from " . TABLE_PRODUCTS_ATTRIBUTES . " pa left join " . TABLE_PRODUCTS_ATTRIBUTES_GROUPS . " pag using(products_attributes_id) where pa.products_id = '" . tep_get_prid($order->products[$i]['id']) . "' and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "' and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "' and pag.customers_group_id = '" . (int)$_SESSION['sppc_customer_group_id'] . "'");
						if ($attributes_group = tep_db_fetch_array($attributes_group_query)) {
							$attributes_values['options_values_price'] = $attributes_group['options_values_price'];
							$attributes_values['price_prefix'] = $attributes_group['price_prefix'];
						}
					}
					// EOF Separate Pricing Per Customer attribute_groups mod

					$sql_data_array = array('orders_id' => $insert_id,
						'orders_products_id' => $order_products_id,
						'products_options' => $attributes_values['products_options_name'],
						'products_options_values' => $attributes_values['products_options_values_name'],
						'options_values_price' => $attributes_values['options_values_price'],
						// qfacwin attributtes
						'NIDATRIB' => $attributes_values['products_attributes_id'],
						//eof qfacwin attributes
						'price_prefix' => $attributes_values['price_prefix']);

					tep_db_perform(TABLE_HOLDING_ORDERS_PRODUCTS_ATTRIBUTES, $sql_data_array);

					// Modificación para anidar la Referencia/Modelo en el pedido si contiene una distinta en los atributos
					if (isset($attributes_values['reference']) && $attributes_values['reference'] != '') {
						$order->products[$i]['model'] .= ' ' . $attributes_values['reference'];
						$order->products[$i]['model'] = str_replace(' ', '-', $order->products[$i]['model']);

						tep_db_query("update " . TABLE_HOLDING_ORDERS_PRODUCTS . " set products_model = '" . $order->products[$i]['model'] . "' where orders_products_id = '" . $order_products_id . "'");
					}

					//Modificación para cambiar el EAN si algún atributo lo contiene.
					if (isset($attributes_values['products_attributes_ean']) && $attributes_values['products_attributes_ean'] != '')
						tep_db_query("update " . TABLE_HOLDING_ORDERS_PRODUCTS . " set product_ean = '" . $attributes_values['products_attributes_ean'] . "' where orders_products_id = '" . $order_products_id . "'");

					$products_ordered_attributes .= "\n\t" . $attributes_values['products_options_name'] . ' ' . $attributes_values['products_options_values_name'];
				}
			}
		}
	}
	private function storeRedsysOrderReference($orders_id, $ds_merchant_order) {
		$sql_data_array = [
			'orders_id'  => (int)$orders_id,
			'ds_order'   => tep_db_input($ds_merchant_order),
			'created_at' => 'now()',
		];

		tep_db_perform('holding_orders_redsys', $sql_data_array);
	}

	public function matchRecentTransactions() {
		$url               = 'https://sis.redsys.es/apl02/services/SerClsWSConsulta';
		$merchant_password = MODULE_PAYMENT_REDSYS_ID_CLAVE256;
		$terminal          = MODULE_PAYMENT_REDSYS_TERMINAL;
		$merchant_code     = MODULE_PAYMENT_REDSYS_ID_COM;

		$redsysManager = new RedsysTransactionManager($url, $merchant_password);
		$transacciones = $redsysManager->getTransactionsByDays($terminal, $merchant_code, 5);

		foreach ($transacciones as $transaccion) {
			$fecha_raw = $transaccion->getDsDate() . ' ' . $transaccion->getDsHour();
			$fecha_obj = DateTime::createFromFormat('Y-m-d H:i:s', $fecha_raw);
			if ($fecha_obj === false) continue;

			$fecha_transaccion   = $fecha_obj->format('Y-m-d H:i:s');
			$importe_transaccion = ((float)$transaccion->getDsAmount()) / 100;

			// ESTA CONSULTA FALTABA Y ES FUNDAMENTAL
			$query = tep_db_query("
            SELECT ho.orders_id
            FROM holding_orders ho
            JOIN holding_orders_total hot ON ho.orders_id = hot.orders_id
            WHERE ho.date_purchased BETWEEN DATE_SUB('" . tep_db_input($fecha_transaccion) . "', INTERVAL 5 MINUTE)
                                        AND DATE_ADD('" . tep_db_input($fecha_transaccion) . "', INTERVAL 5 MINUTE)
              AND hot.class = 'ot_total'
              AND ROUND(hot.value, 2) = ROUND(" . tep_db_input($importe_transaccion) . ", 2)
            LIMIT 1
        ");

			if (tep_db_num_rows($query) == 1) {
				$row = tep_db_fetch_array($query);
				$check_existing = tep_db_query("SELECT 1 FROM holding_orders_redsys WHERE orders_id = '" . (int)$row['orders_id'] . "'");

				if (tep_db_num_rows($check_existing) == 0) {
					$mappedData = $redsysManager->mapTransactionData($transaccion);
					$sql_data_array = array_merge($mappedData, [
						'orders_id'       => (int)$row['orders_id'],
						'ds_processed_at' => 'now()',
						'created_at'      => 'now()',
					]);

					tep_db_perform('holding_orders_redsys', $sql_data_array);
				}
			}
		}
	}

	public function updateRedsysTransactions(?int $orders_id = null) {
		$url               = 'https://sis.redsys.es/apl02/services/SerClsWSConsulta';
		$merchant_password = MODULE_PAYMENT_REDSYS_ID_CLAVE256;
		$terminal          = MODULE_PAYMENT_REDSYS_TERMINAL;
		$merchant_code     = MODULE_PAYMENT_REDSYS_ID_COM;

		$client = new RedsysClient($url, $merchant_password);

		$condition = "";
		if ($orders_id !== null) {
			$condition = " AND orders_id = '" . (int)$orders_id . "'";
		}

		$query = tep_db_query("SELECT orders_id, ds_order FROM holding_orders_redsys WHERE ds_order IS NOT NULL " . $condition);

		while ($row = tep_db_fetch_array($query)) {
			try {
				$transaccion = $client->getTransaction($row['ds_order'], $terminal, $merchant_code);

				if ($transaccion instanceof \RedsysConsultasPHP\Model\Transaction) {

					$tData = $transaccion->toArray();

					$update_data_array = [
						'ds_response'            => isset($tData['Ds_Response'])
							? str_pad($tData['Ds_Response'], 4, '0', STR_PAD_LEFT)
							: null,
						'ds_transaction_type'    => $tData['Ds_TransactionType'] ?? null,
						'ds_authorisation_code'  => $tData['Ds_AuthorisationCode'] ?? null,
						'ds_card_brand'          => $tData['Ds_CardType'] ?? null,
						'ds_processed_at'        => 'now()',
						'ds_state'               => $tData['Ds_State'] ?? null,
						'ds_state_msg'           => isset($tData['Ds_State'])
							? $this->getRedsysStateMessage($tData['Ds_State'])
							: null,
						'ds_response_msg'        => isset($tData['Ds_Response'])
							? $this->getRedsysResponseMessage($tData['Ds_Response'])
							: null,
						'updated_at'             => 'now()',
					];

					tep_db_perform(
						'holding_orders_redsys',
						$update_data_array,
						'update',
						"orders_id = '" . (int)$row['orders_id'] . "'"
					);

					//error_log("[Redsys] Pedido {$row['orders_id']} actualizado correctamente ({$row['ds_order']})");

				} else {
					$tipo = is_object($transaccion) ? get_class($transaccion) : gettype($transaccion);
					//error_log("[Redsys] ❌ No se pudo procesar Ds_Order {$row['ds_order']} (tipo de respuesta: {$tipo})");
				}

			} catch (\Exception $e) {
				//error_log('[Redsys] Error al actualizar Ds_Order ' . $row['ds_order'] . ': ' . $e->getMessage());
			}
		}
	}

	/**
	 * Traduce Ds_State (estado Redsys) a texto legible.
	 */
	private function getRedsysStateMessage(?string $state): ?string {
		if (empty($state)) {
			return null;
		}

		switch ($state) {
			case 'F':
				return 'Finalizada correctamente';
			case 'A':
				return 'Autorizada pendiente';
			case 'S':
				return 'Pendiente de firma';
			default:
				return 'Estado desconocido (' . $state . ')';
		}
	}


	/**
	 * Traduce Ds_Response (código de respuesta Redsys) a texto legible.
	 */
	private function getRedsysResponseMessage(?string $code): ?string {
		if (empty($code)) {
			return null;
		}

		$map = [
			'0000' => 'Transacción autorizada',
			'0900' => 'Autorización recurrente o preautorización',
			'4000' => 'Transacción denegada',
			'9001' => 'Devolución automática',
			'9104' => 'Número de transacción repetido',
			'9124' => 'Error en formato del pedido',
			'9912' => 'Emisor no disponible',
			'9915' => 'Error en el sistema Redsys',
			'9999' => 'Error genérico en Redsys',
			'9601' => 'Error en el proceso de autenticacióin 3DSecure'
		];

		return $map[$code] ?? 'Respuesta desconocida (' . $code . ')';
	}

	/**
	 * Determina si un pedido en holding_orders puede ser considerado correcto
	 * basado en la información de Redsys (response y state).
	 *
	 * @param string|null $ds_response Código de respuesta Redsys.
	 * @param string|null $ds_state Estado de Redsys.
	 *
	 * @return bool True si parece un pedido correcto.
	 */
	public static function isPotentiallyValidRedsysOrder(?string $ds_response, ?string $ds_state): bool {
		return ($ds_response === '0000' && $ds_state === 'F');
	}


	/**
	 * Devuelve el color y texto asociado según la respuesta y el estado Redsys.
	 *
	 * @param string|null $ds_response
	 * @param string|null $ds_state
	 * @param string|null $ds_response_msg
	 * @param string|null $ds_state_msg
	 * @return array ['color' => string, 'message' => string]
	 */
	public static function getRedsysResponseDisplay(?string $ds_response, ?string $ds_state, ?string $ds_response_msg, ?string $ds_state_msg): array {
		if ($ds_response === '0000') {
			if ($ds_state === 'F') {
				return [
					'color' => 'green',
					'message' => 'Transacción autorizada (' . htmlspecialchars($ds_response) . ')'
				];
			} else {
				return [
					'color' => 'orange',
					'message' => 'Transacción autorizada pero pendiente (preautorización o pago en espera) (' . htmlspecialchars($ds_state_msg) . ')'
				];
			}
		}

		if (empty($ds_response) && $ds_state === 'A') {
			return [
				'color' => 'orange',
				'message' => 'Autorizada pendiente (preautorización sin captura)'
			];
		}


		if (empty($ds_response)) {
			return [
				'color' => 'blue',
				'message' => 'Sin información en Redsys'
			];
		}

		switch ($ds_response) {
			case '0900':
				$color = 'green';
				break;
			case '9912': case '0180': case '0102': case '9915': case '9142':
			case '9601': case '9999': case '0184': case '0190':
			$color = 'red';
			break;
			default:
				$color = 'orange';
				break;
		}

		return [
			'color' => $color,
			'message' => htmlspecialchars($ds_response_msg) . ' (' . htmlspecialchars($ds_response) . ')'
		];
	}
}
