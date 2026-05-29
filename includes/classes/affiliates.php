<?php

/**
 * Clase de afiliados usado tanto en el front office
 * como en el back
 * #XCC-313-91043
 * @author Daniel Lucia <daniel.lucia@denox.es>
 */
class Affiliates
{

    /**
     * Guarda las comisiones de categorias
     *
     * @param integer $categories_id
     * @param float $comission
     * @param float $comission_eu
     * @return boolean
     * @author Daniel Lucia <daniel.lucia@denox.es>
     */
    public static function adminSaveCategory(int $categories_id, float $comission, float $comission_eu): bool
    {
        $query = tep_db_query(
            sprintf(
                'SELECT id FROM affiliates_comission_categories WHERE categories_id = %d',
                $categories_id
            )
        );

        if (tep_db_num_rows($query)) {
            $sql = sprintf(
                'UPDATE affiliates_comission_categories SET comission = %.2f, comission_eu = %.2f WHERE categories_id = %d',
                $comission,
                $comission_eu,
                $categories_id
            );
        } else {
            $sql = sprintf(
                'INSERT INTO affiliates_comission_categories SET comission = %.2f, comission_eu = %.2f, categories_id = %d',
                $comission,
                $comission_eu,
                $categories_id
            );
        }

        tep_db_query($sql);

        return true;
    }

    /**
     * Obtiene las comisiones de las categorias
     *
     * @param integer $categories_id
     * @param string $field
     * @return float
     * @author Daniel Lucia <daniel.lucia@denox.es>
     */
    public static function adminGetComissionFromCategory(int $categories_id, string $field = 'comission'): float
    {

        $query = tep_db_query(
            sprintf(
                'SELECT * FROM affiliates_comission_categories WHERE categories_id = %d',
                $categories_id
            )
        );
        if (tep_db_num_rows($query)) {
            $data = tep_db_fetch_array($query);
            if (isset($data[$field])) {
                return floatval($data[$field]);
            }
        }

        if ($field == 'comission') {
            return floatval(constant('AFFILLIATES_SALES_COMISSION'));
        }

        return floatval(constant('AFFILLIATES_SALES_COMISSION_EU'));

    }

    /**
     * Obtiene los datos del afiliado
     *
     * @param integer $affiliate_id
     * @return array
     */
    public static function getAffiliateByID(int $affiliate_id): array
    {
        $sql = sprintf(
            'SELECT id, type_comission, customers_id, username_social_networks, coupon, nif, telephone, affiliate_active, sales_comission, sales_comission_eu, social_networks_list, coupon_value
		FROM affiliates
		WHERE id = %d',
            $affiliate_id
        );
        $sql = tep_db_query($sql);
        if (tep_db_num_rows($sql) > 0) {
            return tep_db_fetch_array($sql);
        }

        return [];

    }

/**
 * Calcula los befenicios de un pedido específico
 *
 * @param integer $orders_id
 * @param float $extra
 * @return float
 */
    public static function calculateOrderProfit(int $orders_id): float
    {
        if ($orders_id == 0) {
            return 0.00;
        }

        $profit = 0.00;
        $sql = sprintf('SELECT profit FROM orders_products WHERE orders_id = %d', $orders_id);
        $sql = tep_db_query($sql);

        while ($product = tep_db_fetch_array($sql)) {
            $profit += $product['profit'];
        }

        //Añadimos los gastos de envio
        /*$sql = sprintf('SELECT value FROM orders_total WHERE orders_id = %d AND class = "ot_shipping"', $orders_id);
        $sql = tep_db_query($sql);
        if (tep_db_num_rows($sql)) {
            $total = tep_db_fetch_array($sql);
            //$profit = $profit + $total['value'];
        }*/

        tep_db_perform(
            'orders',
            array(
                'profit' => $profit,
            ),
            'update',
            'orders_id = ' . (int)$orders_id
        );

        return $profit;
    }

    /**
     * Obtiene los datos del afiliado
     *
     * @param integer $customer_id
     * @return array
     */
    public static function getAffiliateCustomer(int $customer_id): array
    {
        $sql = sprintf(
            'SELECT id, influencer, customers_id, username_social_networks, coupon, nif, telephone, affiliate_active, sales_comission, social_networks_list, coupon_value, bio, networks_json, image
		FROM affiliates
		WHERE customers_id = %d',
            $customer_id
        );
        $sql = tep_db_query($sql);
        if (tep_db_num_rows($sql) > 0) {
            return tep_db_fetch_array($sql);
        }

        return [];

    }

    /**
     * Comprueba si existe el cupón y el
     * afiliado está activo
     *
     * @param string $coupon
     * @return void
     */
    public static function saveCouponAffiliateIfExsists(string $coupon)
    {
        $sql = sprintf(
            'SELECT id FROM %s WHERE coupon = "%s" AND affiliate_active = 1 LIMIT 1',
            TABLE_AFFILIATES,
            $coupon
        );
        $sql = tep_db_query($sql);

        unset($_SESSION['id_affiliate']);
        unset($_SESSION['coupon']);

        if (tep_db_num_rows($sql) > 0) {
            $affiliate = tep_db_fetch_array($sql);
            $_SESSION['id_affiliate'] = $affiliate['id'];
            $_SESSION['coupon'] = $coupon;
        }
    }

    /**
     * Obtiene e listado completo de las comision
     * de un afiliado
     *
     * @param integer $affiliate_id
     * @param float $comission
     * @return array
     */
    public function getOrdersFromAffiliate(int $affiliate_id, float $comission, array $status): array
    {
        $data = [];

        if (empty($status)) {
            return $data;
        }

        $sql = sprintf(
            'SELECT af.orders_id, af.orders_total, af.status, af.date_created, af.date_processed, af.comission, af.date_order_completed, o.orders_status
		FROM affiliates_orders af
		LEFT JOIN orders o ON o.orders_id = af.orders_id
		WHERE af.affiliate_id = %d AND af.status IN ("%s") AND o.orders_status <> 6
		ORDER BY af.orders_id DESC',
            $affiliate_id,
            implode('","', $status)
        );
        $sql = tep_db_query($sql);

        if (tep_db_num_rows($sql) > 0) {
            while ($order = tep_db_fetch_array($sql)) {
                $data[$order['orders_id']] = [
                    //'comission_value' => ($order['orders_total'] * $comission) / 100,
                    'date_processed' => $order['date_processed'],
                    'date_created' => $order['date_created'],
                    'comission' => $order['comission'],
                    'status' => $order['status'],
                    'date_order_completed' => $order['date_order_completed'],
                ];
            }
        }

        return $data;
    }

    /**
     * Función para obtener el historial
     * de un afiliado
     *
     * @param integer $affiliate_id
     * @return void
     * @author Daniel Lucia <daniel.lucia@denox.es>
     */
    public function getHistoryFromAffiliate(int $affiliate_id)
    {
        $data = [];

        if ($affiliate_id == 0) {
            return $data;
        }

        $sql = sprintf(
            'SELECT ah.total, ah.date_created, ah.id, ah.status
            FROM affiliates_history ah
            LEFT JOIN affiliates a ON a.id = ah.affiliate_id
            WHERE a.id = %d
            ORDER BY ah.id DESC',
            $affiliate_id
        );

        $sql = tep_db_query($sql);

        if (tep_db_num_rows($sql) > 0) {
            while ($order = tep_db_fetch_array($sql)) {
                $data[] = $order;
            }
        }

        return $data;
    }

    /**
     * Calcula el beneficio real de un producto
     * partiendo de un precio
     *
     * @param float $shown_price
     * @param array $product
     * @return float
     */
    public static function calculateProductProfit(float $shown_price, array $product): float
    {
        $profit = 0.0;

        if ($product['tax'] > 0) {
            $profit = $shown_price / (1 + ($product['tax'] / 100));
        } else {
            $profit = $shown_price;
        }

        $profit = $profit - (float)$product['cost'];

        return (float)$profit;
    }

    /**
     * Calcula la comision de un producto
     * partiendo de un precio
     *
     * @param float $shown_price
     * @param array $product
     * @return float
     */
    public static function calculateProductComission(order $order, float $shown_price, array $product, int $affiliate_id = 0): float
    {

        $affiliate_id = intval($affiliate_id ?: ($_SESSION['id_affiliate'] ?? 0));
        $type = 'comission';
        $default_comission = floatval(constant('AFFILLIATES_SALES_COMISSION_EU'));
        if (intval($order->delivery['country']['id']) != 195) {
            $type = 'comission_eu';
            $default_comission = floatval(constant('AFFILLIATES_SALES_COMISSION'));
        }

        $sql = sprintf(
            'SELECT acc.%s, p2c.categories_id
            FROM affiliates_comission_categories acc
            INNER JOIN products_to_categories p2c ON p2c.categories_id = acc.categories_id AND p2c.products_id = %d',
            $type,
            $product['id']
        );

        $query = tep_db_query($sql);

        if (tep_db_num_rows($query)) {
            $data = tep_db_fetch_array($query);
            if (isset($data[$type])) {
                $default_comission = floatval($data[$type]);
            }
        } else {
            $affiliate = Affiliates::getAffiliateByID($affiliate_id);

            // getAffiliateByID puede devolver array vacio si no hay afiliado, y su SELECT
            // ademas no incluye `sales_comission_eu` (solo `sales_comission`), asi que las
            // dos lecturas necesitan fallback a 0 para evitar "Undefined array key" en PHP 8+.
            $default_comission = floatval($affiliate['sales_comission'] ?? 0);
            if (intval($order->delivery['country']['id']) != 195) {
                $default_comission = floatval($affiliate['sales_comission_eu'] ?? 0);
            }
        }

        if ($product['tax'] > 0) {
            $shown_price = $shown_price / (1 + ($product['tax'] / 100));
        } else {
            $shown_price = $shown_price;
        }

        $shown_price_comission = $shown_price * ($default_comission / 100);

        return floatval($shown_price_comission);
    }

    /**
     * Función para obtener el cupón
     * automatico para afiliados
     *
     * @param string $user_name
     * @return void
     */
    public static function affiliatesGenerateCoupon(string $user_name): string
    {
        return strtoupper($user_name);
    }

    /**
     * Función para saber si un usuario
     * está dado de alta como afiliado y está
     * activado.
     *
     * @param integer $customer_id
     * @return boolean
     */
    public static function customerIsAffiliate(int $customer_id, $bStatus = true): bool
    {
        $sql = sprintf(
            'SELECT id FROM %s WHERE customers_id = %d' . ($bStatus ? ' AND affiliate_active = 1' : ''),
            TABLE_AFFILIATES,
            $customer_id
        );

        $sql = tep_db_query($sql);

        return tep_db_num_rows($sql) > 0;
    }

    /**
     * Método que crea el pedido
     *
     * @param order $order
     * @param integer $insert_id
     * @return void
     */
    public static function generateOrder(order $order, int $insert_id)
    {
        if (isset($_SESSION['id_affiliate']) && intval($_SESSION['id_affiliate']) > 0) {
            $affiliate = Affiliates::getAffiliateByID(intval($_SESSION['id_affiliate']));

            if (!empty($affiliate)) {

                if ($order->info['total_affiliate'] > 0) {
                    tep_db_perform(
                        'affiliates_orders',
                        [
                            'orders_id' => $insert_id,
                            'affiliate_id' => intval($_SESSION['id_affiliate']),
                            'status' => 'pending',
                            'comission' => $order->info['total_affiliate'],
                            'orders_total' => $order->info['subtotal'],
                            'date_created' => 'now()',
                        ]
                    );
                }

            }

        }
    }

    private static function calculateComission(float $total, float $comission): float
    {
        return ($total * $comission) / 100;
    }

    /**
     * Método usando en el editor de pedido
     * para actualizar las comisiones de los afiliados.
     *
     * @param integer $orders_id
     * @param array $products
     * @return void
     */
    public static function updateOrder(int $orders_id)
    {

        $affiliate_id = self::getAffiliateFromOrder($orders_id);
        $order = new order($orders_id);
        $total_comission = 0;
        foreach ($order->products as $product) {
            $total_comission += Affiliates::calculateProductComission($order, tep_add_tax($product['final_price'], $product['tax']), $product, $affiliate_id) * $product['qty'];
        }

        tep_db_perform(
            'affiliates_orders',
            [
                'comission' => $total_comission,
                'orders_total' => $order->info['subtotal'],
                'date_created' => 'now()',
            ],
            'update',
            sprintf('orders_id = %d', $orders_id)
        );
    }

    /**
     * Obtiene el ID de afiliado de un
     * pedido dado
     *
     * @param integer $order_id
     * @return integer
     */
    private static function getAffiliateFromOrder(int $order_id): int
    {
        $query = tep_db_query(
            sprintf(
                'SELECT affiliate_id FROM affiliates_orders WHERE orders_id = %d',
                $order_id
            )
        );

        if (tep_db_num_rows($query)) {
            $data = tep_db_fetch_array($query);
            return intval($data['affiliate_id']);
        }

        return 0;
    }

    /**
     * Retorna la comisión del un pedido si existe
     *
     * @param integer $order_id
     * @return float
     */
    public static function getComissionFromOrder(int $order_id): float
    {
        $query = tep_db_query(
            sprintf(
                'SELECT comission FROM affiliates_orders WHERE orders_id = %d',
                $order_id
            )
        );

        if (tep_db_num_rows($query)) {
            $data = tep_db_fetch_array($query);
            return floatval($data['comission']);
        }

        return 0.00;
    }

	public static function setSessionAffiliateID(string $coupon){
		$query = tep_db_query("SELECT id FROM affiliates WHERE coupon = \"{$coupon}\"");
		if(tep_db_num_rows($query)>0){
			$id = tep_db_fetch_array($query);
			$_SESSION['id_affiliate'] = $id['id'];
		}
	}
}
