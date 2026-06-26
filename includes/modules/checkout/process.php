<?php
// Alias
namespace Checkout;

// Librerias
//use AddonDomainEvent\Order\Application\OrderCreator;
use util\mail;

class Process
{
    /**
     * Mensaje de error obtenido
     */
    public $messageError = false;

    /**
     * Si necesita redirect
     */
    public $redirect = false;

    /**
     * Constructor de la clase, tendremos las comprobaciones de seguridad
     */
    public function __construct()
    {
        // Variables
        global $navigation, $cart, $cartID, $rgpd;

        // Si no estamos logueados
        if (!tep_session_is_registered('customer_id')) {
            $navigation->set_snapshot(array('mode' => 'SSL', 'page' => FILENAME_CHECKOUT_PAYMENT));

            $this->messageError = CHECKOUT_ERROR_LOGIN;
            $this->redirect = tep_href_link(FILENAME_LOGIN);
            return false;
        }

        // Si no hay nada en el carrito, rediríjalos a la página del carrito de compras
        if ($cart->count_contents() < 1) {
            $this->messageError = CHECKOUT_ERROR_CART_CONTENT;
            $this->redirect = tep_href_link(FILENAME_SHOPPING_CART);
            return false;
        }

        // Si no tiene metodo de envio
        if (!tep_session_is_registered('shipping') || !tep_session_is_registered('sendto')) {
            $this->messageError = CHECKOUT_ERROR_SHIPPING;
            $this->redirect = tep_href_link(FILENAME_CHECKOUT_SHIPPING);
            return false;
        }

        // Si no tiene metodo de pago
        if ((tep_not_null(MODULE_PAYMENT_INSTALLED)) && (!tep_session_is_registered('payment'))) {
            $this->messageError = CHECKOUT_ERROR_PAYMENT;
            $this->redirect = tep_href_link(FILENAME_CHECKOUT_PAYMENT);
            return false;
        }

        // Evitar los intentos de pirateo durante el proceso de pago al verificar el carotID interno
        if (isset($cart->cartID) && tep_session_is_registered('cartID')) {
            if ($cart->cartID != $cartID) {
                $this->redirect = tep_href_link(FILENAME_CHECKOUT_SHIPPING);
                return false;
            }
        }

        // RGPD
        if (preg_match('/' . preg_replace('/\..+$/i', '', str_replace('www.', '', $_SERVER['HTTP_HOST'])) . '/', (string)($_SERVER['HTTP_REFERER'] ?? ''))) {
            $termsAgree = $rgpd->postFormCheckTermsGeneral();

            // Politica de privacidad
            if ($termsAgree == '') {
                $this->messageError = ERROR_POLITICA;
                $this->redirect = tep_href_link(FILENAME_CHECKOUT_CONFIRMATION);
                return false;
            }
        }

        // Comprobamos el stock de los productos
        if ((STOCK_CHECK == 'true') && (STOCK_ALLOW_CHECKOUT != 'true')) {
            $cart->get_products(); // Reseteamos el any_out_of_stock al método

            // Si encontramos productos sin stock, redireccionamos
            if ($cart->any_out_of_stock) {
                $this->redirect = tep_href_link(FILENAME_SHOPPING_CART);
                return false;
            }
        }
    }

    /**
     * Procesa el pedido
     */
    public function process()
    {
        // Variables
        global $order, $shipping, $payment, $insert_id, $order_total_modules;

        // Todas las variables accesibles
        extract($GLOBALS);

        /**
         * @author Daniel Lucia <daniel.lucia@denox.es>
         * Revisamos que tenga title la forma de envio.
         * En algún punto de la tienda, pierde este valor
         * con lo que luego no lo tiene en cuenta en la
         * totalización.
         *
         * #TCU-804-51322
         */

        if (!empty($_SESSION['shipping'])) {
            if ($_SESSION['shipping']['title'] == '') {
                $_SESSION['shipping']['title'] = 'Gastos de envío';
            }
        }

        // Domain event order
        $orderCreatorData = [
            'orders' => [],
            'orders_total' => [],
            'orders_status_history' => [],
            'discount_coupons_to_orders' => [],
            'orders_products' => [],
            'orders_products_attributes' => [],
            'cart' => [
                0 => $cart->get_products(),
                'uuid' => $cart->getUuid(),
            ],
        ];

        // Metodo de pago
        require_once DIR_WS_CLASSES . 'payment.php';
        $payment_modules = new \payment($payment);

        // Comprobamos que todo es correcto para guardarla como global
        if (isset($GLOBALS[$payment]) && is_object($GLOBALS[$payment])) {
            ${$payment} = $GLOBALS[$payment];
        }

        // Metodo de envio
        require_once DIR_WS_CLASSES . 'shipping.php';
        $shipping_modules = new \shipping($shipping);

        // Clase order
        require_once DIR_WS_CLASSES . 'order.php';
        $order = new \order;

        // Actualizamos el estado de los metodos, parece ser que es por un error de la clase order se necesita volver añadir los metodos, mas info en su clase
        $payment_modules->update_status();

        // Comprobamos que el payment sea correcto
        if (($payment_modules->selected_module != $payment) || (is_array($payment_modules->modules) && (sizeof($payment_modules->modules) > 1) && !is_object($$payment)) || (is_object($$payment) && ($$payment->enabled == false))) {
            $this->messageError = CHECKOUT_ERROR_PAYMENT;
            $this->redirect = tep_href_link(FILENAME_CHECKOUT_PAYMENT);
            return false;
        }

        // Cargamos la clase de totalización
        require_once DIR_WS_CLASSES . 'order_total.php';
        $order_total_modules = new \order_total;
        $order_totals = $order_total_modules->process();

        // Ultimo ID de pedido
        $_oders_max_query = tep_db_query("select max(orders_id) as max_id from " . TABLE_ORDERS . "");
        $_oders_max = tep_db_fetch_array($_oders_max_query);
        $_orders_id = $_oders_max["max_id"];
        $insert_id = $_orders_id + 1;

        // Llamamos a before process
        $payment_modules->before_process();

        // Array para orders
        $sql_data_array = array(
            'orders_id' => $insert_id,
            'customers_id' => $customer_id,
            'customers_name' => $order->customer['firstname'] . ' ' . $order->customer['lastname'],
            'customers_company' => $order->customer['company'],
            'customers_street_address' => $order->customer['street_address'],
            'customers_city' => $order->customer['city'],
            'customers_postcode' => $order->customer['postcode'],
            'customers_state' => $order->customer['state'],
            'customers_country' => $order->customer['country']['title'],
            'customers_telephone' => $order->customer['telephone'],
            'customers_email_address' => $order->customer['email_address'],
            'customers_address_format_id' => $order->customer['format_id'],
            'customers_language_id' => $languages_id,
            'delivery_name' => trim($order->delivery['firstname'] . ' ' . $order->delivery['lastname']),
            'delivery_company' => $order->delivery['company'],
            'billing_nif' => $order->billing['nif'],
            'delivery_street_address' => $order->delivery['street_address'],
            'delivery_city' => $order->delivery['city'],
            'delivery_postcode' => $order->delivery['postcode'],
            'delivery_state' => $order->delivery['state'],
            'delivery_country' => $order->delivery['country']['title'],
            'delivery_address_format_id' => $order->delivery['format_id'],
            'billing_name' => $order->billing['firstname'] . ' ' . $order->billing['lastname'],
            'billing_company' => $order->billing['company'],
            'billing_street_address' => $order->billing['street_address'],
            'billing_city' => $order->billing['city'],
            'billing_postcode' => $order->billing['postcode'],
            'billing_state' => $order->billing['state'],
            'billing_country' => $order->billing['country']['title'],
            'billing_address_format_id' => $order->billing['format_id'],
            'payment_method' => $order->info['payment_method'],
            'shipping_module' => $shipping['id'],
            'payment_module' => $order->info['payment_module'],
            'date_purchased' => 'now()',
            'orders_status' => $order->info['order_status'],
            'currency' => $order->info['currency'],
            'currency_value' => $order->info['currency_value'],
			'delivery_telephone' => $order->customer['telephone']
        );


        // Inicio, tiendas
        if (tep_session_is_registered('store_id')) {
            $sql_data_array['id_store'] = $store_id;

            /**
             * Cambiamos la dirección del cliente
             * #IQA-149-71728
             * @author Daniel Lucia <daniel.lucia@denox.es>
             */

            if ($shipping['id'] == 'retira_retira') {
                switch ((int) $store_id) {
                    case 1:
                        $sql_data_array['delivery_street_address'] = 'Calle San Rafael 8';
                        $sql_data_array['delivery_postcode'] = '28108';
                        $sql_data_array['delivery_city'] = 'Alcobendas';
                        $sql_data_array['delivery_state'] = 'Madrid';
                        $sql_data_array['delivery_company'] = 'Francobordo Artículos Náuticos';
                        break;

                    case 2:
                        $sql_data_array['delivery_street_address'] = 'Marina de Denia, Edif. H, Local 3';
                        $sql_data_array['delivery_postcode'] = '03700 ';
                        $sql_data_array['delivery_city'] = 'Denia';
                        $sql_data_array['delivery_state'] = 'Alicante';
                        $sql_data_array['delivery_company'] = 'Velas y Viento';
                        break;
                }

                $aDatos = tep_db_query('select store_name, store_address from store where id_store = "' . (int) $store_id . '"');

                if (tep_db_num_rows($aDatos) > 0) {
                    $aDato = tep_db_fetch_array($aDatos);
                    $comments .= '<br>Recogida en tienda: (' . $aDato['store_name'] . ', ' . $aDato['store_address'] . ')';
                }
            }
        }

        // Inicio, punto de recogida SEUR: la entrega va al punto elegido (como retira en tienda)
        if ($shipping['id'] == 'seurpunto_seurpunto' && !empty($_SESSION['seur_pudo_sel']['id'])) {
            $aSeurPudo = $_SESSION['seur_pudo_sel'];
            // delivery_company es varchar(32): prefijo corto para que quepa el nombre del punto
            $sql_data_array['delivery_company']        = substr('Pto SEUR: ' . $aSeurPudo['name'], 0, 32);
            $sql_data_array['delivery_street_address'] = $aSeurPudo['address'];
            $sql_data_array['delivery_postcode']       = $aSeurPudo['cp'];
            $sql_data_array['delivery_city']           = $aSeurPudo['city'];
            $sSeurComment = 'Entrega en punto SEUR: ' . $aSeurPudo['id'] . ' - ' . $aSeurPudo['name'] . ' (' . $aSeurPudo['address'] . ', ' . $aSeurPudo['cp'] . ' ' . $aSeurPudo['city'] . ')';
            $comments .= '<br>' . $sSeurComment;
            // el historial del pedido se graba desde $order->info['comments'] (no desde $comments)
            $order->info['comments'] = trim((string) $order->info['comments'] . ($order->info['comments'] != '' ? '<br>' : '') . $sSeurComment);
        }
        // Fin, punto de recogida SEUR

        // Inicio, recogida en oficina de Correos: la entrega va a la OFICINA elegida (como SEUR
        // punto), conservando el NOMBRE y contacto del cliente (quien recoge / addressee OFUAOF).
        if ($shipping['id'] == 'correosoficina_correosoficina' && !empty($_SESSION['correos_oficina_sel']['id'])) {
            $aCorOfi = $_SESSION['correos_oficina_sel'];
            $sql_data_array['delivery_company']        = substr('Oficina Correos: ' . $aCorOfi['name'], 0, 32);
            $sql_data_array['delivery_street_address'] = $aCorOfi['address'];
            $sql_data_array['delivery_suburb']         = '';
            $sql_data_array['delivery_postcode']       = $aCorOfi['cp'];
            $sql_data_array['delivery_city']           = $aCorOfi['city'];
            $sql_data_array['delivery_state']          = '';
            $sCorOfiComment = 'Recoger en oficina de Correos: ' . $aCorOfi['id'] . ' - ' . $aCorOfi['name'] . ' (' . $aCorOfi['address'] . ', ' . $aCorOfi['cp'] . ' ' . $aCorOfi['city'] . ')';
            $comments .= '<br>' . $sCorOfiComment;
            $order->info['comments'] = trim((string) $order->info['comments'] . ($order->info['comments'] != '' ? '<br>' : '') . $sCorOfiComment);
        }
        // Fin, recogida en oficina de Correos

        // Inicio, punto de recogida Correos Express (Paq Punto): la entrega va al PUNTO elegido.
        if ($shipping['id'] == 'cexpunto_cexpunto' && !empty($_SESSION['cex_pudo_sel']['id'])) {
            $aCexPudo = $_SESSION['cex_pudo_sel'];
            $sql_data_array['delivery_company']        = substr('Pto CEX: ' . $aCexPudo['name'], 0, 32);
            $sql_data_array['delivery_street_address'] = $aCexPudo['address'];
            $sql_data_array['delivery_suburb']         = '';
            $sql_data_array['delivery_postcode']       = $aCexPudo['cp'];
            $sql_data_array['delivery_city']           = $aCexPudo['city'];
            $sql_data_array['delivery_state']          = '';
            $sCexComment = 'Entrega en punto Correos Express (Paq Punto): ' . $aCexPudo['id'] . ' - ' . $aCexPudo['name'] . ' (' . $aCexPudo['address'] . ', ' . $aCexPudo['cp'] . ' ' . $aCexPudo['city'] . ')';
            $comments .= '<br>' . $sCexComment;
            $order->info['comments'] = trim((string) $order->info['comments'] . ($order->info['comments'] != '' ? '<br>' : '') . $sCexComment);
        }
        // Fin, punto de recogida Correos Express

        // Isertamos orders — con reintento ante carrera de orders_id.
        // El id se genera con MAX(orders_id)+1 (línea ~168); si dos checkouts entran a
        // la vez leen el mismo MAX y colisionan en la PRIMARY KEY (Duplicate entry).
        // Regeneramos el id y reintentamos: es seguro porque el nº de pedido del banco
        // (Ds_Order de Redsys, etc.) es independiente del orders_id interno, y $insert_id
        // es global → los pasos posteriores y el after_process de la pasarela ven el nuevo.
        $nOrderRetries = 0;
        while (true) {
            try {
                tep_db_perform(TABLE_ORDERS, $sql_data_array);
                break;
            } catch (\PDOException $e) {
                $bDup = ($e->getCode() == '23000' && stripos($e->getMessage(), 'Duplicate entry') !== false);
                if ($bDup && $nOrderRetries < 5) {
                    $nOrderRetries++;
                    $_oders_max_query = tep_db_query("select max(orders_id) as max_id from " . TABLE_ORDERS . "");
                    $_oders_max = tep_db_fetch_array($_oders_max_query);
                    $insert_id = (int) $_oders_max["max_id"] + 1;          // $insert_id es global
                    $sql_data_array['orders_id'] = $insert_id;
                    @error_log('checkout: colisión orders_id, reintento ' . $nOrderRetries . ' -> nuevo id ' . $insert_id);
                    continue;
                }
                throw $e;
            }
        }

        /**
         * XCC-313-91043
         * @author Daniel Lucia <daniel.lucia@denox.es>
         */
        \Affiliates::generateOrder($order, intval($insert_id));

        // Inicio, punto de recogida SEUR: persistir el punto para la API (pickupCentreCode)
        if ($shipping['id'] == 'seurpunto_seurpunto' && !empty($_SESSION['seur_pudo_sel']['id'])) {
            $aSeurPudo = $_SESSION['seur_pudo_sel'];
            tep_db_perform('seur_pudo_orders', array(
                'orders_id'  => (int) $insert_id,
                'pudo_id'    => $aSeurPudo['id'],
                'name'       => $aSeurPudo['name'],
                'address'    => $aSeurPudo['address'],
                'postcode'   => $aSeurPudo['cp'],
                'city'       => $aSeurPudo['city'],
                'lat'        => (float) $aSeurPudo['lat'],
                'lng'        => (float) $aSeurPudo['lng'],
                'date_added' => 'now()',
            ));
            unset($_SESSION['seur_pudo_sel']);
        }
        // Fin, punto de recogida SEUR

        // Inicio, recogida en oficina de Correos: persistir la oficina para la API (chosenOffice)
        if ($shipping['id'] == 'correosoficina_correosoficina' && !empty($_SESSION['correos_oficina_sel']['id'])) {
            $aCorOfi = $_SESSION['correos_oficina_sel'];
            $corOfiNo4b = function ($s) { return preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', (string) $s); };  // tabla utf8mb3: sin 4-byte
            tep_db_perform('correos_oficina_orders', array(
                'orders_id'  => (int) $insert_id,
                'office_id'  => $aCorOfi['id'],
                'name'       => $corOfiNo4b($aCorOfi['name']),
                'address'    => $corOfiNo4b($aCorOfi['address']),
                'postcode'   => $aCorOfi['cp'],
                'city'       => $corOfiNo4b($aCorOfi['city']),
                'lat'        => (float) $aCorOfi['lat'],
                'lng'        => (float) $aCorOfi['lng'],
                'date_added' => 'now()',
            ));
            unset($_SESSION['correos_oficina_sel']);
        }
        // Fin, recogida en oficina de Correos

        // Inicio, punto de recogida Correos Express (Paq Punto): persistir el punto para la API (idPtoExterno = producto 18)
        if ($shipping['id'] == 'cexpunto_cexpunto' && !empty($_SESSION['cex_pudo_sel']['id'])) {
            $aCexPudo = $_SESSION['cex_pudo_sel'];
            tep_db_perform('cex_pudo_orders', array(
                'orders_id'  => (int) $insert_id,
                'pudo_id'    => $aCexPudo['id'],
                'name'       => $aCexPudo['name'],
                'address'    => $aCexPudo['address'],
                'postcode'   => $aCexPudo['cp'],
                'city'       => $aCexPudo['city'],
                'lat'        => (float) $aCexPudo['lat'],
                'lng'        => (float) $aCexPudo['lng'],
                'date_added' => 'now()',
            ));
            unset($_SESSION['cex_pudo_sel']);
        }
        // Fin, punto de recogida Correos Express

        if (preg_match('/UPS \(UPS\)/i', $order->info['shipping_method'])) {
            $upsshipping->update_order($insert_id, $cartID);
        }

        // Domain event order
        $orderCreatorData['orders'][] = $sql_data_array;

        // Métodos de pago
        foreach ($order_totals as $orderTotal) {
            // Array para orders_total
            $sql_data_array = array(
                'orders_id' => $insert_id,
                'title' => $orderTotal['title'],
                'text' => $orderTotal['text'],
                'value' => $orderTotal['value'],
                'class' => $orderTotal['code'],
                'sort_order' => $orderTotal['sort_order'],
            );

            // Añadimos
            tep_db_perform(TABLE_ORDERS_TOTAL, $sql_data_array);

            // Domain event order
            $orderCreatorData['orders_total'][] = $sql_data_array;
        }

        // Hiostorial del pedido
        $sql_data_array = array(
            'orders_id' => $insert_id,
            'orders_status_id' => $order->info['order_status'],
            'date_added' => 'now()',
            'customer_notified' => 1,
            'comments' => $order->info['comments'],
        );
        tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

        // Domain event order
        $orderCreatorData['orders_status_history'][] = $sql_data_array;

        // Cupon de descuento
        if (tep_session_is_registered('coupon') && is_object($order->coupon)) {
            $sql_data_array = array(
                'coupons_id' => $order->coupon->coupon['coupons_id'],
                'orders_id' => $insert_id,
            );
            tep_db_perform(TABLE_DISCOUNT_COUPONS_TO_ORDERS, $sql_data_array);

            // Domain event order
            $orderCreatorData['discount_coupons_to_orders'][] = $sql_data_array;
        }

        #### Points/Rewards Module V2.1rc2a balance customer points BOF ####
        if ((USE_POINTS_SYSTEM == 'true') && (USE_REDEEM_SYSTEM == 'true') && ($customer_group_id == '0')) {
            // customer pending points added
            if ($order->info['total'] > 0) {
                $points_toadd = get_points_toadd($order);
                $points_comment = 'TEXT_DEFAULT_COMMENT';
                $points_type = 'SP';
                if ((get_redemption_awards($customer_shopping_points_spending) == true) && ($points_toadd > 0)) {
                    tep_add_pending_points($customer_id, $insert_id, $points_toadd, $points_comment, $points_type);
                }
            }
            // customer referral points added
            if ((tep_session_is_registered('customer_referral')) && (tep_not_null(USE_REFERRAL_SYSTEM))) {
                $referral_twice_query = tep_db_query("select unique_id from " . TABLE_CUSTOMERS_POINTS_PENDING . " where orders_id = '" . (int) $insert_id . "' and points_type = 'RF' limit 1");
                if (!tep_db_num_rows($referral_twice_query)) {
                    $points_toadd = USE_REFERRAL_SYSTEM;
                    $points_comment = 'TEXT_DEFAULT_REFERRAL';
                    $points_type = 'RF';
                    tep_add_pending_points($customer_referral, $insert_id, $points_toadd, $points_comment, $points_type);
                }
            }
            // customer shoppping points account balanced
            if ($customer_shopping_points_spending) {
                tep_redeemed_points($customer_id, $insert_id, $customer_shopping_points_spending);
            }
        }

        // Variables
        $products_ordered = '';
        $subtotal = 0;
        $total_tax = 0;

        // Recorremos productos
        for ($i = 0, $n = sizeof($order->products); $i < $n; $i++) {
            $products_stock_attributes = null;
            if (STOCK_LIMITED == 'true') {
                $products_attributes = $order->products[$i]['attributes'];

                $stock_query_raw = "SELECT products_quantity, pad.products_attributes_filename
								FROM " . TABLE_PRODUCTS . " p
								LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES . " pa
								ON p.products_id=pa.products_id
								LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
								ON pa.products_attributes_id=pad.products_attributes_id
								WHERE p.products_id = '" . tep_get_prid($order->products[$i]['id']) . "'";

                if (is_array($products_attributes)) {
                    $stock_query_raw .= " AND pa.options_id = '" . (int) $products_attributes[0]['option_id'] . "' AND pa.options_values_id = '" . (int) $products_attributes[0]['value_id'] . "'";
                }
                $stock_query = tep_db_query($stock_query_raw);
            } else {
                $stock_query = tep_db_query("select products_quantity from " . TABLE_PRODUCTS . " where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
            }

            if (tep_db_num_rows($stock_query) > 0) {
                $stock_values = tep_db_fetch_array($stock_query);
                $actual_stock_bought = $order->products[$i]['qty'];
                $download_selected = false;

                if ((DOWNLOAD_ENABLED == 'true') && isset($stock_values['products_attributes_filename']) && tep_not_null($stock_values['products_attributes_filename'])) {
                    $download_selected = true;
                    $products_stock_attributes = '$$DOWNLOAD$$';
                }

                // If not downloadable and attributes present, adjust attribute stock
                if (!$download_selected && is_array($products_attributes)) {
                    $all_nonstocked = true;
                    $products_stock_attributes_array = array();

                    foreach ($products_attributes as $attribute) {
                        if ($attribute['track_stock'] == 1) {
                            $products_stock_attributes_array[] = $attribute['option_id'] . "-" . $attribute['value_id'];
                            $all_nonstocked = false;
                        }
                    }

                    if ($all_nonstocked) {
                        $actual_stock_bought = $order->products[$i]['qty'];
                    } else {
                        $products_stock_attributes = implode(",", $products_stock_attributes_array);
                        $attributes_stock_query = tep_db_query("select products_stock_quantity from " . TABLE_PRODUCTS_STOCK . " where products_stock_attributes = '$products_stock_attributes' AND products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");

                        if (tep_db_num_rows($attributes_stock_query) > 0) {
                            $attributes_stock_values = tep_db_fetch_array($attributes_stock_query);
                            $attributes_stock_left = $attributes_stock_values['products_stock_quantity'] - $order->products[$i]['qty'];
                            tep_db_query("update " . TABLE_PRODUCTS_STOCK . " set products_stock_quantity = '" . $attributes_stock_left . "' where products_stock_attributes = '$products_stock_attributes' AND products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
                            $actual_stock_bought = ($attributes_stock_left < 1) ? $attributes_stock_values['products_stock_quantity'] : $order->products[$i]['qty'];
                        } else {
                            $attributes_stock_left = 0 - $order->products[$i]['qty'];
                            tep_db_query("insert into " . TABLE_PRODUCTS_STOCK . " (products_id, products_stock_attributes, products_stock_quantity) values ('" . tep_get_prid($order->products[$i]['id']) . "', '" . $products_stock_attributes . "', '" . $attributes_stock_left . "')");
                            $actual_stock_bought = 0;
                        }
                    }
                }

                if (!$download_selected) {
                    $stock_left = $stock_values['products_quantity'] - $actual_stock_bought;
                    tep_db_query("UPDATE " . TABLE_PRODUCTS . " SET products_quantity = products_quantity - '" . $actual_stock_bought . "', products_ordered = products_ordered + " . sprintf('%d', $order->products[$i]['qty']) . " WHERE products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
                }
            }

            if (!isset($products_stock_attributes)) {
                $products_stock_attributes = null;
            }

            // Insertamos el producto al pedido
            $sql_data_array = array(
                'orders_id' => $insert_id,
                'products_id' => tep_get_prid($order->products[$i]['id']),
                'products_model' => $order->products[$i]['model'],
                'product_ean' => $order->products[$i]['ean'],
                'products_ubicacion' => $order->products[$i]['ubicacion'],
                'products_name' => $order->products[$i]['name'],
                'products_price' => $order->products[$i]['price'],
                'products_cost' => $order->products[$i]['cost'],
                'final_price' => $order->products[$i]['final_price'],
                'products_tax' => $order->products[$i]['tax'],
                'products_quantity' => $order->products[$i]['qty'],
                'products_stock_attributes' => $products_stock_attributes,
            );
            tep_db_perform(TABLE_ORDERS_PRODUCTS, $sql_data_array);
            $order_products_id = tep_db_insert_id();

            // Domain event order
            $orderCreatorData['orders_products'][] = $sql_data_array;

            $attributes_exist = '0';
            $products_ordered_attributes = '';

            if (isset($order->products[$i]['attributes'])) {
                $attributes_exist = '1';
                for ($j = 0, $n2 = sizeof($order->products[$i]['attributes']); $j < $n2; $j++) {
                    if (DOWNLOAD_ENABLED == 'true') {
                        $attributes_query = "select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix, pa.options_values_weight, pa.weight_prefix, pad.products_attributes_maxdays, pad.products_attributes_maxcount , pad.products_attributes_filename, pa.reference
											 from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa
											 left join " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
											 on pa.products_attributes_id=pad.products_attributes_id
											 where pa.products_id = '" . (int) $order->products[$i]['id'] . "'
											 and pa.options_id = '" . (int) $order->products[$i]['attributes'][$j]['option_id'] . "'
											 and pa.options_id = popt.products_options_id
											 and pa.options_values_id = '" . (int) $order->products[$i]['attributes'][$j]['value_id'] . "'
											 and pa.options_values_id = poval.products_options_values_id
											 and popt.language_id = '" . (int) $languages_id . "'
											 and poval.language_id = '" . (int) $languages_id . "'";
                        $attributes = tep_db_query($attributes_query);
                    } else {
                        $attributes = tep_db_query("select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix, pa.reference, pa.products_attributes_ean, pa.products_attributes_id,  pa.options_values_weight, pa.weight_prefix
													from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa
													where " . (!in_array((int) $order->products[$i]['attributes'][$j]['option_id'], $aOptionsInsertUser) ? "pa.options_values_id = '" . (int) $order->products[$i]['attributes'][$j]['value_id'] . "' and " : "") . "
													pa.products_id = '" . (int) $order->products[$i]['id'] . "' and pa.options_id = '" . (int) $order->products[$i]['attributes'][$j]['option_id'] . "' and pa.options_id = popt.products_options_id and pa.options_values_id = poval.products_options_values_id and popt.language_id = '" . (int) $languages_id . "' and poval.language_id = '" . (int) $languages_id . "'");
                    }
                    $attributes_values = tep_db_fetch_array($attributes);

                    // BOF Separate Pricing Per Customer attribute_groups mod
                    if (isset($_SESSION['sppc_customer_group_id']) && $_SESSION['sppc_customer_group_id'] != '0') {
                        $attributes_group_query = tep_db_query("select pag.options_values_price, pag.price_prefix from " . TABLE_PRODUCTS_ATTRIBUTES . " pa left join " . TABLE_PRODUCTS_ATTRIBUTES_GROUPS . " pag using(products_attributes_id) where pa.products_id = '" . tep_get_prid($order->products[$i]['id']) . "' and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "' and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "' and pag.customers_group_id = '" . (int) $_SESSION['sppc_customer_group_id'] . "'");
                        if ($attributes_group = tep_db_fetch_array($attributes_group_query)) {
                            $attributes_values['options_values_price'] = $attributes_group['options_values_price'];
                            $attributes_values['price_prefix'] = $attributes_group['price_prefix'];
                        }
                    }
                    // EOF Separate Pricing Per Customer attribute_groups mod

                    // Sampedro: Inicio, Atributos por tipo //
                    if (in_array((int) $order->products[$i]['attributes'][$j]['option_id'], $aOptionsInsertUser)) {
                        $attributes_values['products_options_values_name'] = nl2br(urldecode($order->products[$i]['attributes'][$j]['value_id']));
                    }
                    // Sampedro: Fin, Atributos por tipo //

                    $attr_name = $attributes_values['products_options_name'];

                    if ($attributes_values['products_options_id'] == 'PRODUCTS_OPTIONS_VALUE_TEXT_ID') {
                        $attr_name_sql_raw = 'SELECT po.products_options_name FROM ' .
                            TABLE_PRODUCTS_OPTIONS . ' po, ' .
                            TABLE_PRODUCTS_ATTRIBUTES . ' pa WHERE ' .
                            ' pa.products_id="' . tep_get_prid($order->products[$i]['id']) . '" AND ' .
                            ' pa.options_id="' . $order->products[$i]['attributes'][$j]['option_id'] . '" AND ' .
                            ' pa.options_id=po.products_options_id AND ' .
                            ' po.language_id="' . $languages_id . '" ';
                        $attr_name_sql = tep_db_query($attr_name_sql_raw);
                        if ($arr = tep_db_fetch_array($attr_name_sql)) {
                            $attr_name = $arr['products_options_name'];
                        }
                    }

                    // PARCHE DEFENSIVO 2026-05-12: rellenar IDs/reference si el cart los pasó vacíos
                    $_orig_opt_id        = (int)$order->products[$i]['attributes'][$j]['option_id'];
                    $_orig_val_id        = (int)$order->products[$i]['attributes'][$j]['value_id'];
                    $_resolved_opt_id    = $_orig_opt_id;
                    $_resolved_val_id    = $_orig_val_id;
                    $_resolved_reference = isset($attributes_values['reference']) ? $attributes_values['reference'] : '';
                    $_resolved_ean       = isset($attributes_values['products_attributes_ean']) ? $attributes_values['products_attributes_ean'] : '';
                    $_resolved_attr_id   = isset($attributes_values['products_attributes_id']) ? $attributes_values['products_attributes_id'] : 0;
                    $_resolved_weight    = isset($attributes_values['options_values_weight']) ? $attributes_values['options_values_weight'] : 0;
                    $_opt_name_text      = $attr_name;
                    $_val_name_text      = $attributes_values['products_options_values_name'];
                    if (($_resolved_opt_id === 0 || $_resolved_val_id === 0) && tep_not_null($_opt_name_text) && tep_not_null($_val_name_text)) {
                        $_resolve_q = tep_db_query("SELECT pa.options_id, pa.options_values_id, pa.products_attributes_id, pa.reference, pa.products_attributes_ean, pa.options_values_weight FROM " . TABLE_PRODUCTS_ATTRIBUTES . " pa JOIN " . TABLE_PRODUCTS_OPTIONS . " po ON po.products_options_id = pa.options_id JOIN " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov ON pov.products_options_values_id = pa.options_values_id WHERE pa.products_id = '" . (int)tep_get_prid($order->products[$i]['id']) . "' AND po.products_options_name = '" . tep_db_input($_opt_name_text) . "' AND pov.products_options_values_name = '" . tep_db_input($_val_name_text) . "' AND po.language_id = '" . (int)$languages_id . "' AND pov.language_id = '" . (int)$languages_id . "' LIMIT 1");
                        if ($_resolved = tep_db_fetch_array($_resolve_q)) {
                            $_resolved_opt_id  = (int)$_resolved['options_id'];
                            $_resolved_val_id  = (int)$_resolved['options_values_id'];
                            if (empty($_resolved_reference)) $_resolved_reference = $_resolved['reference'];
                            if (empty($_resolved_ean))       $_resolved_ean       = $_resolved['products_attributes_ean'];
                            if (empty($_resolved_attr_id))   $_resolved_attr_id   = $_resolved['products_attributes_id'];
                            if (empty($_resolved_weight))    $_resolved_weight    = $_resolved['options_values_weight'];
                        }
                    }
                    // LOG fallback: cuando el cart pasó IDs=0, dejar traza para diagnosticar el flujo origen
                    if ($_orig_opt_id === 0 || $_orig_val_id === 0) {
                        $_log_status = ($_resolved_opt_id > 0 && $_resolved_val_id > 0) ? 'RESOLVED' : 'UNRESOLVED';
                        $_log_line = sprintf(
                            "[%s] src=modules_process order=%d op_id=%d pid=%d opt=%s val=%s orig=%d/%d resolved=%d/%d status=%s referer=%s ua=%s\n",
                            date('Y-m-d H:i:s'), (int)$insert_id, (int)$order_products_id, (int)tep_get_prid($order->products[$i]['id']),
                            json_encode((string)$_opt_name_text, JSON_UNESCAPED_UNICODE),
                            json_encode((string)$_val_name_text, JSON_UNESCAPED_UNICODE),
                            $_orig_opt_id, $_orig_val_id, $_resolved_opt_id, $_resolved_val_id, $_log_status,
                            json_encode((string)($_SERVER['HTTP_REFERER'] ?? ''), JSON_UNESCAPED_UNICODE),
                            json_encode(substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120), JSON_UNESCAPED_UNICODE)
                        );
                        @file_put_contents(DIR_FS_DOCUMENT_ROOT . 'logs/opa_fallback.log', $_log_line, FILE_APPEND | LOCK_EX);
                    }

                    // Añadimos atributos a los productos
                    $sql_data_array = array(
                        'orders_id' => $insert_id,
                        'orders_products_id' => $order_products_id,
                        'products_options' => $attr_name,
                        'products_options_values' => $attributes_values['products_options_values_name'],
                        // IDs reales de la combinación — necesarios para lookup en products_stock
                        // (el módulo delivery_estimate y otros consumen esta pareja)
                        'products_options_id' => $_resolved_opt_id,
                        'products_options_values_id' => $_resolved_val_id,
                        'options_values_price' => $attributes_values['options_values_price'],
                        // qfacwin attributtes
                        'NIDATRIB' => $_resolved_attr_id,
                        //eof qfacwin attributes
                        'price_prefix' => $attributes_values['price_prefix'],
                        'reference' => $_resolved_reference,
                        'products_attributes_ean' => $_resolved_ean,
                        'options_values_weight' => $_resolved_weight,
                        'weight_prefix' => $attributes_values['weight_prefix'],
                    );

                    tep_db_perform(TABLE_ORDERS_PRODUCTS_ATTRIBUTES, $sql_data_array);

                    // Domain event order
                    $orderCreatorData['orders_products_attributes'][] = $sql_data_array;

                    // Modificación para anidar la Referencia/Modelo en el pedido si contiene una distinta en los atributos
                    if (isset($attributes_values['reference']) && $attributes_values['reference'] != '') {
                        $order->products[$i]['model'] .= ' ' . $attributes_values['reference'];
                        $order->products[$i]['model'] = str_replace(' ', '-', $order->products[$i]['model']);
                        tep_db_query("update " . TABLE_ORDERS_PRODUCTS . " set products_model = '" . $order->products[$i]['model'] . "' where orders_products_id = '" . $order_products_id . "'");
                    }

                    // Modificación para pisar el EAN.
                    if (isset($attributes_values['products_attributes_ean']) && $attributes_values['products_attributes_ean'] != '') {
                        $order->products[$i]['ean'] = $attributes_values['products_attributes_ean'];
                        tep_db_query("update " . TABLE_ORDERS_PRODUCTS . " set product_ean = '" . $order->products[$i]['ean'] . "' where orders_products_id = '" . $order_products_id . "'");
                    }

                    if ((DOWNLOAD_ENABLED == 'true') && isset($attributes_values['products_attributes_filename']) && tep_not_null($attributes_values['products_attributes_filename'])) {
                        $sql_data_array = array(
                            'orders_id' => $insert_id,
                            'orders_products_id' => $order_products_id,
                            'orders_products_filename' => $attributes_values['products_attributes_filename'],
                            'download_maxdays' => $attributes_values['products_attributes_maxdays'],
                            'download_count' => $attributes_values['products_attributes_maxcount'],
                        );
                        tep_db_perform(TABLE_ORDERS_PRODUCTS_DOWNLOAD, $sql_data_array);
                    }

                    $products_ordered_attributes .= "\n\t" . $attributes_values['products_options_name'] . ' ' . $attributes_values['products_options_values_name'];
                }
            }

            $total_weight += ($order->products[$i]['qty'] * $order->products[$i]['weight']);
            $total_tax += tep_calculate_tax($total_products_price, $products_tax) * $order->products[$i]['qty'];
            $total_cost += $total_products_price;
            $products_ordered .= $order->products[$i]['qty'] . ' x ' . $order->products[$i]['name'] . ' (' . $order->products[$i]['model'] . ') = ' . $currencies->display_price($order->products[$i]['final_price'], $order->products[$i]['tax'], $order->products[$i]['qty']) . $products_ordered_attributes . "\n";
        }

        // Marcamos como descatalogados los productos que estén en liquidación y no tengan stock
        tep_db_query('UPDATE products SET products_status = 2 WHERE products_liquidacion = 1 AND products_quantity = 0');

        /**
         * XCC-313-91043
         * Recalculamos beneficios del pedido
         * @author Daniel Lucia <daniel.lucia@denox.es>
         */
        \Affiliates::calculateOrderProfit(intval($insert_id));

        // Idioma email
        include DIR_WS_LANGUAGES . $language . '/modules/email/checkout_process.php';

        // Email
        $mail = new mail();

        // Html del email
        $mail->includeEmail('checkout_process.php', array(
            'currencies' => $currencies,
            'order' => $order,
            'insert_id' => $insert_id,
            'order_totals' => $order_totals,
            'payment' => $$payment,
            'sendto' => $sendto,
            'billto' => $billto,
            'customer_id' => $customer_id,
            'languages_id' => $languages_id,
            'aOptionsInsertUser' => $aOptionsInsertUser,
        ));

        // Enviamos email
       tep_mail($order->customer['firstname'] . ' ' . $order->customer['lastname'], $order->customer['email_address'], EMAIL_TEXT_SUBJECT . $insert_id, $mail->html, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
        // send emails to other people
        if (SEND_EXTRA_ORDER_EMAILS_TO != '') {
            tep_mail('', SEND_EXTRA_ORDER_EMAILS_TO, EMAIL_TEXT_SUBJECT . $insert_id, $mail->html, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
        }

        // load the after_process function from the payment modules
        $payment_modules->after_process();

        // Domain event order
        //(new OrderCreator())($orderCreatorData);

        // Eliminamos
        $cart->reset(true);
        tep_session_unregister('sendto');
        tep_session_unregister('billto');
        tep_session_unregister('shipping');
        tep_session_unregister('payment');
        tep_session_unregister('comments');
        tep_session_unregister('store_id');
        tep_session_unregister('store_cost');
        tep_session_unregister('coupon');
		if(isset($_SESSION['id_affiliate'])){
			unset($_SESSION['id_affiliate']);
		}
        if (tep_session_is_registered('customer_shopping_points')) {
            tep_session_unregister('customer_shopping_points');
        }

        if (tep_session_is_registered('customer_shopping_points_spending')) {
            tep_session_unregister('customer_shopping_points_spending');
        }

        if (tep_session_is_registered('customer_referral')) {
            tep_session_unregister('customer_referral');
        }

        if (tep_session_is_registered('recalc')) {
            tep_session_unregister('recalc');
        }

        // Redireccionamos
        $this->redirect = tep_href_link(FILENAME_CHECKOUT_SUCCESS);
        return true;
    }

    /*
     * Procesa el pedido
     */
    public function index()
    {
        return $this->process();
    }
}
