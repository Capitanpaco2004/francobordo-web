<?php
// Alias
namespace util;

/**
 * Clase HoldingOrderManager.
 *
 * Esta clase se encarga de manejar la transferencia de pedidos desde Holding Orders a Orders.
 *
 * @package util
 */
class HoldingOrderManager {
    /**
     * Manejador que se encarga de llamar a todas las funciones para transferir un pedido desde Holding Orders a Orders.
     *
     * @param int $ocID El ID del pedido en Holding Orders.
	 *
     * @return int|null El ID del pedido insertado en Orders, o null si ocurre un error.
     */
    public static function moveHoldingOrderManager($ocID) {
		if (!is_numeric($ocID) || (int)$ocID <= 0) {
			return null;
		}
		$ocID = (int)$ocID;

        $insert_id = self::moveOrderFromHoldingOrder($ocID);
        self::moveOrderTotalsFromHoldingOrderTotals($ocID, $insert_id);
        self::moveOrderProductsFromHoldingOrderProducts($ocID, $insert_id);
        self::moveOrderStatusHistoryFromHoldingOrderStatusHistory($ocID, $insert_id);
        self::discountOrderProductsStockFromHoldingOrderProducts($insert_id);
        self::removeOrderFromHoldingOrder($ocID);
        return $insert_id;
    }

    /**
     * Mueve un pedido desde Holding Orders a Orders.
     *
     * @param int $ocID El ID del pedido en Holding Orders.
     * @return int|null El ID del pedido insertado en Orders, o null si ocurre un error.
     */
    public static function moveOrderFromHoldingOrder(int $ocID) {
        $holding_orders_move_query = tep_db_query("SELECT * FROM " . TABLE_HOLDING_ORDERS . " WHERE orders_id = '" . $ocID . "'");
        $holding_orders_move = tep_db_fetch_array($holding_orders_move_query);
        unset($holding_orders_move['orders_id']); // Remove Holding Order ID
        unset($holding_orders_move['orders_move']); // Remove Holding Order move flag
        $insert_id = tep_db_perform(TABLE_ORDERS, $holding_orders_move);
        if ($insert_id) {
            $insert_id = tep_db_insert_id();
        }
        return $insert_id;
    }

    /**
     * Mueve los totales del pedido desde Holding Orders Totals a Orders Totals.
     *
     * @param int $ocID El ID del pedido en Holding Orders.
     * @param int $insert_id El ID del pedido insertado en Orders.
     * @return void
     */
    public static function moveOrderTotalsFromHoldingOrderTotals(int $ocID, int $insert_id) {
        $holding_orders_move_query = tep_db_query("SELECT * FROM " . TABLE_HOLDING_ORDERS_TOTAL . " WHERE orders_id = '" . $ocID . "'");
        while ($holding_orders_total_move = tep_db_fetch_array($holding_orders_move_query)) {
            $holding_orders_total_move = array(
                'orders_id' => $insert_id,
                'title' => $holding_orders_total_move['title'],
                'text' => $holding_orders_total_move['text'],
                'value' => $holding_orders_total_move['value'],
                'class' => $holding_orders_total_move['class'],
                'sort_order' => $holding_orders_total_move['sort_order']
            );
            tep_db_perform(TABLE_ORDERS_TOTAL, $holding_orders_total_move);
        }
    }

    /**
     * Mueve los productos del pedido desde Holding Orders Products a Orders Products.
     *
     * @param int $ocID El ID del pedido en Holding Orders.
     * @param int $insert_id El ID del pedido insertado en Orders.
     * @param bool $checkStock Si es true, verifica el stock antes de mover el producto.
     * @return void
     */
    public static function moveOrderProductsFromHoldingOrderProducts(int $ocID, int $insert_id, bool $checkStock = false) {
        $holdind_orders_products_move_query = tep_db_query("SELECT hop.*,hopa.orders_products_attributes_id, hopa.products_options,hopa.products_options_values,hopa.reference,hopa.products_attributes_ean,hopa.weight_prefix,hopa.options_values_weight FROM " . TABLE_HOLDING_ORDERS_PRODUCTS . " hop LEFT JOIN holding_orders_products_attributes hopa ON (hopa.orders_products_id = hop.orders_products_id) WHERE hop.orders_id = '" . $ocID . "'");

        while ($holdind_orders_products_move = tep_db_fetch_array($holdind_orders_products_move_query)) {

            $aStock = tep_db_query('SELECT products_quantity FROM products WHERE products_id = "' . $holdind_orders_products_move['products_id'] . '";');
            $aStock = tep_db_fetch_array($aStock);

            $stockActual = isset($aStock['products_quantity']) ? $aStock['products_quantity'] : 0;

            if (!$checkStock || $stockActual >= $holdind_orders_products_move['products_quantity']) {
                // Mover el producto a orders_products
                $holdind_orders_products_move_out = array(
                    'orders_id' => $insert_id,
                    'products_id' => $holdind_orders_products_move['products_id'],
                    'products_model' => $holdind_orders_products_move['products_model'],
                    'product_ean' => $holdind_orders_products_move['product_ean'],
                    'products_ubicacion' => $holdind_orders_products_move['products_ubicacion'],
                    'products_name' => $holdind_orders_products_move['products_name'],
                    'products_price' => $holdind_orders_products_move['products_price'],
		    'final_price' => $holdind_orders_products_move['final_price'],
                    'products_tax' => $holdind_orders_products_move['products_tax'],
                    'products_quantity' => $holdind_orders_products_move['products_quantity'],
                    'products_cost' => $holdind_orders_products_move['products_cost'],
                    'products_stock_attributes' => $holdind_orders_products_move['products_stock_attributes']
                );
                tep_db_perform(TABLE_ORDERS_PRODUCTS, $holdind_orders_products_move_out);
                $order_product_id = tep_db_insert_id();

                // Si tiene ID de atributo, insertar en orders_products_attributes
                if(!is_null($holdind_orders_products_move['orders_products_attributes_id'])){
                    $holdind_orders_products_move_out = array(
                        'orders_id' => $insert_id,
                        'orders_products_id' => $order_product_id,
                        'products_options' => $holdind_orders_products_move['products_options'],
                        'products_options_values' => $holdind_orders_products_move['products_options_values'],
                        'reference' => $holdind_orders_products_move['reference'],
                        'products_attributes_ean' => $holdind_orders_products_move['products_attributes_ean'],
                        'weight_prefix' => $holdind_orders_products_move['weight_prefix'],
                        'options_values_weight' => $holdind_orders_products_move['options_values_weight'],
                    );
                    tep_db_perform(TABLE_ORDERS_PRODUCTS_ATTRIBUTES, $holdind_orders_products_move_out);
                }

                // Actualizar el stock en products_stock si hay atributos
                if (!empty($holdind_orders_products_move['products_stock_attributes'])) {
                    tep_db_query('UPDATE products_stock SET products_stock_quantity = products_stock_quantity - ' . $holdind_orders_products_move['products_quantity'] . ' WHERE products_id = "' . $holdind_orders_products_move['products_id'] . '" AND products_stock_attributes = "' . tep_db_input($holdind_orders_products_move['products_stock_attributes']) . '";');
                }

				// Actualizar el stock, incluso si se vuelve negativo
                tep_db_query('UPDATE products SET products_quantity = ' . $stockActual . ' WHERE products_id = "' . $holdind_orders_products_move['products_id'] . '";');
            }
        }
    }


    /**
     * Mueve el historial de estado del pedido desde Holding Orders Status History a Orders Status History
     *
     * @param int $ocID El ID del pedido en Holding Orders.
     * @param int $insert_id El ID del pedido insertado en Orders.
     * @return void
     */
    public static function moveOrderStatusHistoryFromHoldingOrderStatusHistory(int $ocID, int $insert_id) {
        $holding_orders_status_move_query = tep_db_query("SELECT * FROM " . TABLE_HOLDING_ORDERS_STATUS_HISTORY . " WHERE orders_id = '" . $ocID . "'");
        $holding_orders_status_move = tep_db_fetch_array($holding_orders_status_move_query);
        $holding_orders_status_move['orders_id'] = $insert_id;
        $holding_orders_status_move['orders_status_history_id'] = '';
        if (tep_not_null($holding_orders_status_move)) {
            tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $holding_orders_status_move);
        }
    }

    /**
     * Descuenta el stock de los productos movidos del pedido desde Holding Orders.
     *
     * @param int $insert_id El ID del pedido insertado en Orders.
     * @return void
     */
    public static function discountOrderProductsStockFromHoldingOrderProducts(int $insert_id) {
        $order_stock_query = tep_db_query("SELECT op.products_id, op.products_quantity
                                           FROM " . TABLE_ORDERS_PRODUCTS . " op
                                           LEFT JOIN " . TABLE_PRODUCTS . " p ON (op.products_id = p.products_id)
                                           WHERE op.orders_id = '" . $insert_id . "'");
        while ($order_stock = tep_db_fetch_array($order_stock_query)) {
            tep_db_query("UPDATE " . TABLE_PRODUCTS . "
                          SET products_quantity = products_quantity - " . $order_stock['products_quantity'] . ",
                              products_ordered = products_ordered + " . $order_stock['products_quantity'] . "
                          WHERE products_id = '" . $order_stock['products_id'] . "'");
        }
    }

    /**
     * Elimina todos los pedidos en espera (Holding Orders) asociados a un cliente específico
     * si el cliente ha tenido pedidos hoy (en las últimas 2 horas).
     *
     * @param int $customer_id El ID del cliente del que se desean eliminar los pedidos en espera.
     *
     * @return void
     */
    public static function removeAllHoldingOrdersFromCustomer(int $customer_id) {
        if ($customer_id <= 0) {
            return; // Salida temprana si el customer_id no es válido
        }

        // Verificar si el cliente ha tenido pedidos hoy (en las últimas 2 horas)
        $checkIfCustomerHaveOrderInLast2Hours = self::checkIfCustomerHaveOrderInLastHour($customer_id);

        // Si el cliente ha tenido pedidos hoy, eliminar los pedidos en espera
        if ($checkIfCustomerHaveOrderInLast2Hours) {
            $customerHoldingOrderIDs = self::getCustomerHoldingOrderIDs($customer_id);

            if (!empty($customerHoldingOrderIDs)) {
                foreach ($customerHoldingOrderIDs as $HoldingorderID) {
                    self::removeOrderFromHoldingOrder($HoldingorderID);
                }
            }
        }
    }

    /**
     * Obtiene todos los orders_id de holding_orders para un cliente específico.
     *
     * @param int $customer_id El ID del cliente.
     * @return array Una lista de orders_id relacionados con el cliente en holding_orders.
     */
    public static function getCustomerHoldingOrderIDs(int $customer_id): array {
        $orderIDs = [];

        $holdingOrders = tep_db_query('SELECT orders_id FROM holding_orders WHERE customers_id = "' . (int)$customer_id . '";');

        if (tep_db_num_rows($holdingOrders) > 0) {
            while ($holdingOrder = tep_db_fetch_array($holdingOrders)) {
                $orderIDs[] = $holdingOrder['orders_id'];
            }
        }

        return $orderIDs;
    }

    /**
     * Verifica si un cliente ha realizado pedidos en la última hora.
     *
     * @param int $customer_id El ID del cliente.
     *
     * @return bool true si el cliente ha realizado pedidos en la última hora, false en caso contrario.
     */
    public static function checkIfCustomerHaveOrderInLastHour(int $customer_id) {
        // Calcula la fecha y hora hace 2 horas desde la hora actual.
        $twoHoursAgo = date('Y-m-d H:i:s', strtotime('-1 hours'));

        // Query SQL para verificar si el cliente ha realizado pedidos en las últimas 2 horas.
        $sql = 'SELECT COUNT(*) FROM orders WHERE customers_id = "' . (int)$customer_id . '" AND date_purchased >= "' . $twoHoursAgo . '"';

        $result = tep_db_query($sql);
        $rowCount = tep_db_fetch_array($result);

        return $rowCount['COUNT(*)'] > 0;
    }


	/**
	 * Elimina un pedido de Holding Orders.
	 *
	 * @param int $order_id El ID del pedido en Holding Orders.
	 *
	 * @return void
	 */
	public static function removeOrderFromHoldingOrder(int $order_id) {
		tep_db_query("DELETE FROM " . TABLE_HOLDING_ORDERS . " WHERE orders_id = '" . $order_id . "'");
		}

	/**
	 * Unifica múltiples pedidos duplicados en holding_orders generados por idas y venidas al TPV.
	 * Mantiene el pedido más reciente con transacción confirmada en Redsys (si la hay),
	 * o el pedido más reciente si no hay ninguno confirmado.
	 * Elimina los otros pedidos.
	 */
	public static function unifyHoldingOrdersDuplicates() {
		// Buscar posibles duplicados en los últimos 30 días
		$duplicatesQuery = tep_db_query("
            SELECT ho.customers_email_address, DATE(ho.date_purchased) as fecha, hot.value, COUNT(*) as qty
            FROM " . TABLE_HOLDING_ORDERS . " ho
            INNER JOIN " . TABLE_HOLDING_ORDERS_TOTAL . " hot ON ho.orders_id = hot.orders_id AND hot.class = 'ot_total'
            WHERE ho.date_purchased >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY ho.customers_email_address, hot.value, DATE(ho.date_purchased)
            HAVING qty > 1
        ");

		while ($dupGroup = tep_db_fetch_array($duplicatesQuery)) {
			$email = tep_db_input($dupGroup['customers_email_address']);
			$value = (float)$dupGroup['value'];
			$fecha = $dupGroup['fecha'];

			// Obtener todos los pedidos duplicados
			$ordersToUnifyQuery = tep_db_query("
                SELECT ho.orders_id, ho.date_purchased, hor.ds_response
                FROM " . TABLE_HOLDING_ORDERS . " ho
                LEFT JOIN holding_orders_redsys hor ON hor.orders_id = ho.orders_id
                INNER JOIN " . TABLE_HOLDING_ORDERS_TOTAL . " hot ON ho.orders_id = hot.orders_id AND hot.class = 'ot_total'
                WHERE ho.customers_email_address = '$email'
                  AND ABS(hot.value - $value) < 0.01
                  AND DATE(ho.date_purchased) = '$fecha'
                ORDER BY
                  CASE WHEN hor.ds_response = '0000' THEN 1 ELSE 2 END,
                  ho.date_purchased DESC
            ");

			$ordersToKeep   = null;
			$ordersToDelete = [];

			while ($order = tep_db_fetch_array($ordersToUnifyQuery)) {
				if (!$ordersToKeep) {
					// Mantener el primero (más reciente confirmado o simplemente más reciente)
					$ordersToKeep = $order['orders_id'];
				} else {
					$ordersToDelete[] = $order['orders_id'];
				}
			}

			// Eliminar duplicados innecesarios
			foreach ($ordersToDelete as $orderID) {
				self::removeOrderFromHoldingOrder($orderID);
			}
		}
	}

	/**
	 * Borra todos los pedidos en holding_orders con más de 3 meses de antigüedad.
	 */
	public static function removeOldHoldingOrders() {
		$threeMonthsAgo = date('Y-m-d H:i:s', strtotime('-3 months'));

		$oldOrdersQuery = tep_db_query("SELECT orders_id FROM " . TABLE_HOLDING_ORDERS . " WHERE date_purchased < '" . $threeMonthsAgo . "'");

		while ($oldOrder = tep_db_fetch_array($oldOrdersQuery)) {
			self::removeOrderFromHoldingOrder($oldOrder['orders_id']);
		}
	}

	public function removeDuplicateHoldingOrdersClearDate() {
		// ⚙️ Búsqueda puntual: pedidos reales del 18 de septiembre de 2025
		$recentOrdersQuery = tep_db_query("
        SELECT o.orders_id, o.customers_id, o.customers_email_address, o.date_purchased, ot.value
        FROM " . TABLE_ORDERS . " o
        INNER JOIN " . TABLE_ORDERS_TOTAL . " ot
            ON ot.orders_id = o.orders_id AND ot.class = 'ot_total'
        WHERE DATE(o.date_purchased) = '2025-09-18'
    ");

		while ($recentOrder = tep_db_fetch_array($recentOrdersQuery)) {
			$customerId = (int)$recentOrder['customers_id'];
			$customerEmail = tep_db_input($recentOrder['customers_email_address']);
			$orderValue = (float)$recentOrder['value'];
			$orderDate = tep_db_input($recentOrder['date_purchased']);

			// Buscar duplicados en holding_orders
			$duplicateHoldingOrdersQuery = tep_db_query("
			SELECT ho.orders_id
			FROM " . TABLE_HOLDING_ORDERS . " ho
			INNER JOIN " . TABLE_HOLDING_ORDERS_TOTAL . " hot
				ON hot.orders_id = ho.orders_id AND hot.class = 'ot_total'
			WHERE
				(ho.customers_id = '{$customerId}' OR ho.customers_email_address = '{$customerEmail}')
				AND ABS(hot.value - {$orderValue}) < 0.05
				AND ho.date_purchased BETWEEN DATE_SUB('{$orderDate}', INTERVAL 1 HOUR)
										  AND DATE_ADD('{$orderDate}', INTERVAL 1 HOUR)
		");

			while ($duplicateHoldingOrder = tep_db_fetch_array($duplicateHoldingOrdersQuery)) {
				$this->removeOrderFromHoldingOrder($duplicateHoldingOrder['orders_id']);
				error_log("[HoldingOrders] 🧹 Eliminado duplicado {$duplicateHoldingOrder['orders_id']} (pedido real {$recentOrder['orders_id']})");
			}
		}
	}

	/**
	 * Elimina pedidos duplicados de holding_orders que ya existan en orders.
	 * Compara por cliente, importe y fecha aproximada (±10 minutos).
	 */
	public function removeDuplicateHoldingOrders() {
		// Buscar pedidos en orders de las últimas 48 horas
		$recentOrdersQuery = tep_db_query("
            SELECT o.orders_id, o.customers_id, o.date_purchased, ot.value
            FROM " . TABLE_ORDERS . " o
            INNER JOIN " . TABLE_ORDERS_TOTAL . " ot ON ot.orders_id = o.orders_id AND ot.class = 'ot_total'
            WHERE o.date_purchased >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");

		while ($recentOrder = tep_db_fetch_array($recentOrdersQuery)) {
			// Buscar pedidos duplicados en holding_orders
			$duplicateHoldingOrdersQuery = tep_db_query("
                SELECT ho.orders_id
                FROM " . TABLE_HOLDING_ORDERS . " ho
                INNER JOIN " . TABLE_HOLDING_ORDERS_TOTAL . " hot ON hot.orders_id = ho.orders_id AND hot.class = 'ot_total'
                WHERE
					(ho.customers_id = '" . (int)$recentOrder['customers_id'] . "' OR ho.customers_email_address = '" . tep_db_input($recentOrder['customers_email_address']) . "')
                    AND ABS(hot.value - " . (float)$recentOrder['value'] . ") < 0.01
                    AND ho.date_purchased BETWEEN DATE_SUB('" . $recentOrder['date_purchased'] . "', INTERVAL 10 MINUTE)
                                              AND DATE_ADD('" . $recentOrder['date_purchased'] . "', INTERVAL 10 MINUTE)
            ");

			// Eliminar duplicados encontrados
			while ($duplicateHoldingOrder = tep_db_fetch_array($duplicateHoldingOrdersQuery)) {
				$this->removeOrderFromHoldingOrder($duplicateHoldingOrder['orders_id']);
			}
		}
	}


}
