<?php

if (!function_exists('includeAdminView')) {
    /**
     * Función para incluir vistas
     * de manera mñas sencilla
     *
     * @author Daniel Lucia <daniel.lucia@denox.es>
     * @param string $file
     * @return void
     */
    function includeAdminView($file)
    {
        if (!file_exists($file)) {
            return false;
        }

        ob_start();
        include $file;
        return ob_get_clean();
    }
}

if (!function_exists('getAffiliatesCustomers')) {
    function getAffiliatesCustomers(int $status = 0, string $email = '', string $username = ''): array
    {
        $data = [
            'customers' => [],
            'paginate' => [],
        ];

        $where[] = '1=1';
        if ($status < 2) {
            $where[] = 'a.affiliate_active= ' . $status;
        }
        if ($email != '') {
            $where[] = 'c.customers_email_address LIKE "%' . $email . '%"';
        }
        if ($username != '') {
            $where[] = 'a.username_social_networks LIKE "%' . $username . '%"';
        }

        $sql = sprintf(
            'SELECT a.type_comission, a.id, a.coupon_value, a.customers_id, a.username_social_networks, a.coupon, a.nif, a.telephone, a.affiliate_active, a.sales_comission, a.sales_comission_eu, a.date_created, a.date_modified, a.activation_date, c.customers_firstname, c.customers_lastname, c.customers_email_address
			FROM affiliates a
			INNER JOIN customers c ON a.customers_id = c.customers_id
			WHERE %s
			ORDER BY id DESC',
            implode(' AND ', $where)
        );

        $sqlCount = sprintf('SELECT count(DISTINCT c.customers_id) AS total FROM affiliates a LEFT JOIN customers c ON a.customers_id = c.customers_id WHERE %s', implode(' AND ', $where));

        $sql = preg_replace("/[\r\n\t]+/", " ", $sql);
        $data['paginate'] = new splitPageResults(
            $_GET['page'],
            intval($_GET['per_page']),
            $sql,
            $sqlnumrows,
            $sqlCount
        );
        $customers_query = tep_db_query($sql);

        if (tep_db_num_rows($customers_query) > 0) {
            while ($customers = tep_db_fetch_array($customers_query)) {
                $data['customers'][] = $customers;
            }
        }

        return $data;
    }
}

if (!function_exists('setStatusAffiliate')) {
    /**
     * Modifica el estado de un afiliado
     *
     * @param integer $id
     * @param integer $status
     * @return void
     */
    function setStatusAffiliate(int $id, int $status)
    {

        $activation_date = $status == 1 ? date("Y-m-d H:i:s") : '0000-00-00 00:00:00';
        $affiliate = getAffiliateCustomer($id);

        if ($status == 1) {
            $response = createCouponAffiliate($affiliate['coupon'], floatval($affiliate['coupon_value']));
            if ($response == true) {

                tep_db_perform(
                    'affiliates',
                    [
                        'affiliate_active' => $status,
                        'activation_date' => $activation_date,
                    ],
                    'update',
                    'id = ' . $id
                );

                $htmlContent = '
				<p style="font-size: 25px">Estimado '.$affiliate['customers_firstname'].' '.$affiliate['customers_lastname'].'</p>
				<p>Acabamos de activar tu cuenta de afiliado en nuestra tienda.</p>
				<p>Recibirás una comisión del '.$affiliate['sales_comission']. '% por las compras que hagan tus seguidores.</p>
				<p>Para empezar a generar ingresos, puedes compartir este cupón:</p>
				<pre><b>'.$affiliate['coupon'].'</b></pre>
				<p>O este enlace:</p>
				<p><a href = "https://www.francobordo.com/index.php?ref-affiliate='.$affiliate['coupon'].'">https://www.francobordo.com/index.php?ref-affiliate='.$affiliate['coupon'].'</a></p>
				<p>Este cupón tiene un descuento de <strong>'.$affiliate['coupon_value'].'%</strong> para tus seguidores</p>
				<p>Si necesitas ayuda, no dudes en ponerte en contacto con nosotros.</p>';


                $mail = new util\mail();
                $mail->includeEmail(
                    'various.php',
                    array(
                        'content' => $htmlContent,
                    )
                );

                tep_mail(
                    $affiliate['customers_name'],
                    $affiliate['customers_email_address'],
                    'Tu cuenta de afiliado ha sido aprobada',
                    $mail->html,
                    STORE_OWNER,
                    STORE_OWNER_EMAIL_ADDRESS
                );

                return true;
            } else {
                return false;
            }

        } else {
            removeCouponAffiliate($affiliate['coupon']);

            tep_db_perform(
                'affiliates',
                [
                    'affiliate_active' => $status,
                    'activation_date' => $activation_date,
                ],
                'update',
                'id = ' . $id
            );

            return true;
        }
    }
}
if (!function_exists('createCouponAffiliate')) {
    function createCouponAffiliate(string $coupon, float $coupon_value)
    {
        $sql = sprintf(
            'SELECT coupons_id FROM discount_coupons WHERE coupons_id = "%s"',
            $coupon
        );
        $sql = tep_db_query($sql);
        if (tep_db_num_rows($sql) == 0) {
            tep_db_perform(
                'discount_coupons',
                array(
                    'coupons_id' => $coupon,
                    //'coupons_specials_exclude' => 1,
                    'coupons_description' => 'Cupón afiliado',
                    'coupons_discount_amount' => ($coupon_value / 100),
                    'coupons_discount_type' => 'percent',
                    'coupons_date_start' => date('Y-m-d'),
                )
            );

            return true;
        }

        return false;

    }
}

if (!function_exists('removeCouponAffiliate')) {
    function removeCouponAffiliate(string $coupon)
    {
        $sql = sprintf(
            'DELETE FROM discount_coupons WHERE coupons_id = "%s"',
            $coupon
        );
        tep_db_query($sql);
    }
}

if (!function_exists('getOrdersFromAffiliate')) {
    function getOrdersFromAffiliate(int $affiliate_id, string $status): array
    {
        $data = [];

        $sql = sprintf(
            'SELECT af.orders_id, af.orders_total, af.status, af.comission
			FROM affiliates_orders af
			LEFT JOIN orders o ON o.orders_id = af.orders_id
			WHERE af.affiliate_id = %d AND af.status = "%s" AND o.orders_status <> 6 ORDER BY orders_id DESC',
            $affiliate_id,
            $status
        );

        $sql = tep_db_query($sql);

        if (tep_db_num_rows($sql) > 0) {
            while ($order = tep_db_fetch_array($sql)) {
                $data[$order['orders_id']] = array("comision"=>$order['comission'], "total"=> $order['orders_total']);
            }
        }

        return $data;
    }
}

if (!function_exists('getTotalOrderWithExcluded')) {
    function getTotalOrderWithExcluded($orders_id)
    {
        $sql = sprintf(
            'SELECT products_price, products_quantity, products_tax
			FROM orders_products
			WHERE orders_id = %d',
            $orders_id
        );

        $sql = tep_db_query($sql);
        $total = [];

        if (tep_db_num_rows($sql) > 0) {
            while ($order = tep_db_fetch_array($sql)) {
                if ($order['products_tax'] > 0) {
                    $total[] = ($order['products_price'] * $order['products_quantity']) * (1 + ($order['products_tax'] / 100));
                } else {
                    $total[] = ($order['products_price'] * $order['products_quantity']);
                }

            }
        }

        $sql = sprintf(
            'SELECT value FROM orders_total WHERE `orders_id` = %d AND class IN ("ot_shipping")',
            $orders_id
        );

        $sql = tep_db_query($sql);

        if (tep_db_num_rows($sql) > 0) {
            while ($order = tep_db_fetch_array($sql)) {
                $total[] = $order['value'];
            }
        }

        $total = array_sum($total);

        $sql = sprintf(
            'SELECT dco.coupons_id, dc.coupons_discount_type, dc.coupons_discount_amount
			FROM discount_coupons_to_orders dco
			LEFT JOIN discount_coupons dc ON dc.coupons_id = dco.coupons_id
			WHERE dco.orders_id = %d LIMIT 1',
            $orders_id
        );

        $sql = tep_db_query($sql);

        if (tep_db_num_rows($sql) > 0) {
            $order = tep_db_fetch_array($sql);
            switch ($order['coupons_discount_type']) {
                case 'fixed':

                    break;
                case 'percent':

                    break;
            }
        }

        return $total;
    }
}

if (!function_exists('getOrdersTotalFromAffiliate')) {
    function getOrdersTotalFromAffiliate(int $affiliate_id, float $comission, string $status): float
    {
        if ($comission == 0 || $affiliate_id == 0) {
            return 0.00;
        }

        setlocale(LC_MONETARY, 'es_ES');
        $orders = getOrdersFromAffiliate($affiliate_id, $status);

        //$total = calculateComission(array_sum($orders), $comission);
        $total = array_sum($orders);
        return floatval($total);
    }
}

if (!function_exists('calculateComission')) {
    function calculateComission(float $total, float $comission): float
    {
        return ($total * $comission) / 100;
    }
}
if (!function_exists('getAffiliateCustomer')) {
    function getAffiliateCustomer(int $id): array
    {
        $sql = sprintf(
            'SELECT a.type_comission, a.influencer, a.image, a.id, a.customers_id, a.username_social_networks, a.coupon, a.nif, a.telephone, a.affiliate_active, a.sales_comission, a.sales_comission_eu, a.social_networks_list, a.coupon_value, c.customers_firstname, c.customers_lastname, c.customers_email_address
			FROM affiliates a
			LEFT JOIN customers c ON a.customers_id = c.customers_id
			WHERE a.id = %d',
            $id
        );

        $sql = tep_db_query($sql);
        if (tep_db_num_rows($sql) > 0) {
            return tep_db_fetch_array($sql);
        }

        return [];

    }

}

if (!function_exists('processOrderComission')) {
    function processOrderComission(int $orders_id, string $status)
    {
        $date_processed = $status == 'prepared' ? date("Y-m-d H:i:s") : '0000-00-00 00:00:00';

        return tep_db_perform(
            'affiliates_orders',
            [
                'status' => $status,
                'date_processed' => $date_processed,
            ],
            'update',
            'orders_id = ' . $orders_id
        );
    }
}

if (!function_exists('processHistory')) {
    function processHistory(int $history_id, string $status, int $affiliate_id)
    {
        $affiliate = getAffiliateCustomer($affiliate_id);
        $mail = new util\mail();
        $mail->includeEmail(
            'various.php',
            array(
                'content' => 'El proceso de pago se ha marcado como completado. Muchas gracias!',
            )
        );

        tep_mail(
            $affiliate['customers_name'],
            $affiliate['customers_email_address'],
            'Su factura ha sido pagada. Muchas gracias!',
            $mail->html,
            STORE_OWNER,
            SEND_EXTRA_ORDER_EMAILS_TO
        );

        return tep_db_perform(
            'affiliates_history',
            [
                'status' => $status,
            ],
            'update',
            'id = ' . $history_id
        );
    }
}

if (!function_exists('processOrderComissionBulk')) {
    function processOrderComissionBulk(array $orders_id, string $status)
    {
        $date_processed = $status == 'prepared' ? date("Y-m-d H:i:s") : '0000-00-00 00:00:00';

        return tep_db_perform(
            'affiliates_orders',
            [
                'status' => $status,
                'date_processed' => $date_processed,
            ],
            'update',
            'orders_id IN (' . implode(',', $orders_id) . ')'
        );
    }
}

if (!function_exists('processHistoryBulk')) {
    function processHistoryBulk(array $history_id, string $status)
    {
        return tep_db_perform(
            'affiliates_history',
            [
                'status' => $status,
            ],
            'update',
            'id IN (' . implode(',', $history_id) . ')'
        );
    }
}

if (!function_exists('updateDataAffiliate')) {
    function updateDataAffiliate(array $data, int $id)
    {
        $image = '';
        $affiliate = getAffiliateCustomer($id);
        if (!empty($affiliate)) {
            $image = $affiliate['image'];
        }

        $upload = new upload('image');
        $upload->set_destination(DIR_FS_CATALOG_IMAGES . 'influencers/');
        if ($upload->parse() && $upload->save()) {
            $image = md5($id) . '.jpg';
            rename(DIR_FS_CATALOG_IMAGES . 'influencers/' . $upload->filename, DIR_FS_CATALOG_IMAGES . 'influencers/' . $image);
        }

        $affiliateData = [
            'username_social_networks' => $data['username_social_networks'],
            'sales_comission' => $data['sales_comission'],
            'sales_comission_eu' => $data['sales_comission_eu'],
            'coupon' => $data['coupon'],
            'nif' => $data['nif'],
            'telephone' => $data['telephone'],
            'social_networks_list' => $data['social_networks_list'],
            'coupon_value' => $data['coupon_value'],
            'image' => $image,
            'influencer' => intval($data['influencer']),
            'type_comission' => strval($data['type_comission']),
        ];

        return tep_db_perform(
            'affiliates',
            $affiliateData,
            'update',
            'id = ' . $id
        );
    }
}
if (!function_exists('getStatusAffiliates')) {
    function getStatusAffiliates()
    {
        return array(
            array(
                'id' => '1',
                'text' => 'Activo',
            ),
            array(
                'id' => '0',
                'text' => 'Inactivo',
            ),
            array(
                'id' => '2',
                'text' => 'Todos',
            ),
        );
    }
}

if (!function_exists('getActionsForAffiliates')) {
    function getActionsForAffiliates()
    {
        return array(
            array(
                'id' => '',
                'text' => 'No hacer nada',
            ),
            array(
                'id' => 'coupon_value',
                'text' => 'Cambiar valor cupón',
            ),
        );
    }
}

if (!function_exists('getEnumStatusValues')) {
    function getEnumStatusValues(): array
    {
        $sql = 'SHOW COLUMNS FROM affiliates_orders WHERE Field = "status"';
        $sql = tep_db_query($sql);

        if (tep_db_num_rows($sql) > 0) {
            $field = tep_db_fetch_array($sql);
            preg_match("/^enum\(\'(.*)\'\)$/", $field['Type'], $matches);
            $enums = explode("','", $matches[1]);

            $types = array(array('id' => '', 'text' => 'Todos'));
            foreach ($enums as $enum) {
                $types[] = array(
                    'id' => $enum,
                    'text' => ucfirst($enum),
                );
            }

            return $types;
        }

        return [];
    }
}

if (!function_exists('getHistoryFromAffiliate')) {
    function getHistoryFromAffiliate(int $affiliate_id): array
    {
        $data = [];

        if ($affiliate_id == 0) {
            return $data;
        }

        $sql = sprintf(
            'SELECT ah.total, ah.date_created, ah.id, ah.status, ah.id, ah.type
			FROM affiliates_history ah
			LEFT JOIN affiliates a ON a.id = ah.affiliate_id
			WHERE a.id = %d
			ORDER BY ah.id DESC',
            $affiliate_id
        );
        $sql = tep_db_query($sql);

        if (tep_db_num_rows($sql) > 0) {
            while ($order = tep_db_fetch_array($sql)) {
                $data[$order['id']] = $order;
            }
        }

        return $data;
    }
}

if (!function_exists('deleteAfilliate')) {
    /**
     * Función que borra por completo a un afiliado
     *
     * @param integer $affiliate_id
     * @return boolean
     */
    function deleteAfilliate(int $affiliate_id): bool
    {
        $affiliate = getAffiliateCustomer($affiliate_id);
        removeCouponAffiliate($affiliate['coupon']);
        
        $sql = sprintf(
            'DELETE FROM affiliates WHERE id = %d',
            $affiliate_id
        );
        tep_db_query($sql);

        $sql = sprintf(
            'DELETE FROM affiliates_orders WHERE affiliate_id = %d',
            $affiliate_id
        );
        tep_db_query($sql);

        $sql = sprintf(
            'DELETE FROM affiliates_history WHERE id = %d',
            $affiliate_id
        );
        tep_db_query($sql);

        return true;
    }
}

if (!function_exists('getBrandsForExclude')) {
    function getBrandsForExclude(string $search = ''): array
    {
        $data = [];

        $sql = 'SELECT manufacturers_id, manufacturers_name FROM manufacturers ORDER BY manufacturers_name';
        if ($search != '') {
            $sql = 'SELECT manufacturers_id, manufacturers_name FROM manufacturers WHERE LOWER(manufacturers_name) LIKE "%' . strtolower(tep_db_prepare_input($search)) . '%" ORDER BY manufacturers_name';
        }

        $sql = tep_db_query($sql);

        if (tep_db_num_rows($sql) > 0) {
            while ($brand = tep_db_fetch_array($sql)) {
                $data[$brand['manufacturers_id']] = $brand['manufacturers_name'];
            }
        }

        return $data;
    }
}

if (!function_exists('getBrandsExcluded')) {
    function getBrandsExcluded(): array
    {
        $sql = 'SELECT configuration_value FROM configuration WHERE configuration_key="AFFILLIATES_BRANDS_EXCLUDES"';
        $sql = tep_db_query($sql);
        $brands = tep_db_fetch_array($sql);
        if ($brands['configuration_value'] != '') {
            return json_decode($brands['configuration_value'], true);
        }

        return [];
    }
}

if (!function_exists('getAffiliatesStats')) {
    /**
     * Retorna estadísticas relacionadas
     * con los afiliados y los cupones
     * usados
     *
     * @return array
     */
    function getAffiliatesStats(): array
    {

        $stats = [];

        $date_from = (isset($_GET['date_from']) && $_GET['date_from'] != '' ? formatDateForStats($_GET['date_from']) : false);
        $date_to = (isset($_GET['date_to']) && $_GET['date_to'] != '' ? formatDateForStats($_GET['date_to']) : false);
        $minimum_order = (isset($_GET['minimum_order']) ? intval($_GET['minimum_order']) : 0);

        if (isset($_GET['affiliate']) && $_GET['affiliate'] != '') {
            $sql = 'SELECT a.id, a.username_social_networks, a.sales_comission, c.customers_email_address FROM affiliates a LEFT JOIN customers c ON c.customers_id = a.customers_id WHERE a.affiliate_active = 1 AND (a.username_social_networks = "' . tep_db_prepare_input($_GET['affiliate']) . '" OR c.customers_email_address = "' . tep_db_prepare_input($_GET['affiliate']) . '") ORDER BY id ASC';
        } else {
            $sql = 'SELECT a.id, a.username_social_networks, a.sales_comission, c.customers_email_address FROM affiliates a LEFT JOIN customers c ON c.customers_id = a.customers_id WHERE a.affiliate_active = 1 ORDER BY id ASC';
        }

        $customers_query = tep_db_query($sql);

        if (tep_db_num_rows($customers_query) > 0) {
            while ($customer = tep_db_fetch_array($customers_query)) {

                $sql = sprintf(
                    'SELECT o.delivery_country as country, SUM(af.orders_total) as total, SUM(af.comission) as comission
					FROM affiliates_orders af
					LEFT JOIN orders o ON o.orders_id = af.orders_id
					WHERE af.affiliate_id = %d %s %s
					GROUP BY o.delivery_country',
                    $customer['id'],
                    ($date_from !== false ? ' AND DATE( o.date_purchased ) >= "' . $date_from . '" ' : ''),
                    ($date_to !== false ? ' AND DATE( o.date_purchased ) <= "' . $date_to . '" ' : '')
                );

                $total_query = tep_db_query($sql);
                if (tep_db_num_rows($total_query) > 0) {
                    while ($total = tep_db_fetch_array($total_query)) {

                        $country = $total['country'];
                        if ($country == '') {
                            $country = 'n/a';
                        }
                        unset($total['country']);
                        $total['total'] = abs($total['total']);
                        //$total['profit'] = $total['profit'];
                        $total['comission'] = $total['comission'];
                        $customer['total'][$country] = $total;
                        //$customer['total_orders'] = floatval($customer['total_orders']) + floatval($total['total']);

                        $stats['countries'][] = $country;
                    }
                }

                if ($customer['total_orders'] >= $minimum_order) {
                    $stats['customers'][] = $customer;
                }

            }

            $stats['countries'] = array_unique($stats['countries']);
        }

        return $stats;
    }
}

if (!function_exists('formatDateForStats')) {
    function formatDateForStats($date)
    {
        $sAux = explode('/', $date);
        return $sAux[2] . '-' . $sAux[1] . '-' . $sAux[0];
    }
}

if (!function_exists('getAffiliatesList')) {
    /**
     * Retorna un listado de afiliados
     *
     * @return array
     */
    function getAffiliatesList(): array
    {

        $affiliates = [];

        $sql = 'SELECT a.username_social_networks, c.customers_email_address
		FROM affiliates a
		INNER JOIN customers c ON a.customers_id = c.customers_id
		WHERE a.affiliate_active = 1 ORDER BY a.username_social_networks ASC';

        $customers_query = tep_db_query($sql);

        if (tep_db_num_rows($customers_query) > 0) {
            while ($customer = tep_db_fetch_array($customers_query)) {
                $affiliates[] = $customer['username_social_networks'];
                $affiliates[] = $customer['customers_email_address'];
            }
        }

        return $affiliates;
    }
}

if (!function_exists('getTypeComissionValues')) {
    function getTypeComissionValues()
    {
        return [
            [
                'id' => 'subtotal',
                'text' => 'Subtotal',
            ],
            [
                'id' => 'profit',
                'text' => 'Beneficios',
            ],
        ];
    }
}
