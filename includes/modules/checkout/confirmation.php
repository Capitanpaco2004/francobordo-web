<?php
// Alias
namespace Checkout;

// Librerias
use Checkout\Cart;
use util\event;
use util\tools;
use util\HoldingOrder;


class Confirmation
{
    public $messageError;
    public $redirect;

    /**
     * Constructor de la clase, tendremos las comprobaciones de seguridad para el uso correcto de los méotodos de la clase
     */
    public function __construct()
    {
        // Variables
        global $navigation, $cart, $customer_default_address_id, $cartID;

        // Si no estamos logueados
        if (!tep_session_is_registered('customer_id')) {
            $navigation->set_snapshot();
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

        // Comprobamos el ZONE ID
        $aRow = pharaonix_queryOne('SELECT entry_zone_id, entry_city, entry_city_id, entry_country_id FROM address_book WHERE address_book_id = "' . (int) $customer_default_address_id . '"');

        // Si no tenemos ZONE ID redireccionamos a select zone
        if (($aRow->num_rows == 0) || ((($aRow->records['entry_zone_id'] == 0 || $aRow->records['entry_zone_id'] == '') && $aRow->records['entry_city'] == ''))) {
            $this->messageError = CHECKOUT_ERROR_SELECT_ZONE;
            $this->redirect = tep_href_link(FILENAME_CHECKOUT_SELECT_ZONE);
            return false;
        }

        // Evitar los intentos de pirateo durante el proceso de pago al verificar el carotID interno
        if (isset($cart->cartID) && tep_session_is_registered('cartID')) {
            if ($cart->cartID != $cartID) {
                $this->redirect = tep_href_link(FILENAME_CHECKOUT_SHIPPING);
                return false;
            }
        }

        // Si no se ha seleccionado ningún método de envío, redirija al cliente a la página de selección del método de envío
        if (!tep_session_is_registered('shipping') || empty($_SESSION['shipping'])) {
            $this->messageError = CHECKOUT_ERROR_SHIPPING;
            $this->redirect = tep_href_link(FILENAME_CHECKOUT_SHIPPING);
            return false;
        }

        // Si no se ha seleccionado ningún método de pago, redirija al cliente a la página de selección del método de pago
        if (!tep_session_is_registered('payment') || empty($_SESSION['payment'])) {
            $this->messageError = CHECKOUT_ERROR_PAYMENT;
            $this->redirect = tep_href_link(FILENAME_CHECKOUT_PAYMENT);
            return false;
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

        // Comprobamos si ha existido cambios en productos
        if ($cart->getHasModified()) {
            $this->redirect = tep_href_link('checkout/cart/modified/');
            return false;
        }

        if( array_key_exists('data_oe', $_POST) ) {
            if( array_key_exists( 'choose_insurance', $_POST['data_oe'] ) ) {
                $_SESSION['choose_insurance'] = 1;
            }
            else {
                unset($_SESSION['choose_insurance']);
            }
        }

    }

    /**
     * Pago exterior
     */
    public function paymentExt()
    {
        // Variables
        global $order, $formAction, $htmlInputHidden, $timeWait, $order_totals, $customer_id, $aOptionsInsertUser;
        $htmlInputHidden = '';
        $timeWait = CHECKOUT_PAYMENT_EXT_TIME * 1000;

        // Todas las variables accesibles
        extract($GLOBALS);

        if (array_key_exists('data_oe', $_POST)) {
            if (array_key_exists('choose_insurance', $_POST['data_oe'])) {
                $_SESSION['choose_insurance'] = 1;
            } else {
                unset($_SESSION['choose_insurance']);
            }

        }

        // Breadcrumb
        $breadcrumb->add(CHECKOUT_CONFIRMATION_BREADCRUMB, tep_href_link(CHECKOUT_CONFIRMATION_BREADCRUMB));

        // Metodo de pago
        require_once DIR_WS_CLASSES . 'payment.php';
        $payment_modules = new \payment($payment);

        // Comprobamos que todo es correcto para guardarla como global
        if (isset($GLOBALS[$payment]) && is_object($GLOBALS[$payment])) {
            ${$payment} = $GLOBALS[$payment];
        }

        // Clase order
        require_once DIR_WS_CLASSES . 'order.php';
        $order = new \order;

        // Actualizamos el estado de los metodos, parece ser que es por un error de la clase order se necesita volver añadir los metodos, mas info en su clase
        $payment_modules->update_status();

        // LLamada al metodo pre_confirmation_check
        if (is_array($payment_modules->modules)) {
            $payment_modules->pre_confirmation_check();
        }

        // Cargamos los modulos de envio
        require_once DIR_WS_CLASSES . 'shipping.php';
        $shipping_modules = new \shipping($shipping);

        // Cargamos la clase de totalización
        require_once DIR_WS_CLASSES . 'order_total.php';
        $order_total_modules = new \order_total;
        $order_totals = $order_total_modules->process();

        // Salvaguardados
		(new HoldingOrder)($order, $order_totals, $customer_id, $aOptionsInsertUser);

        // RGPD
        if (preg_match('/' . preg_replace('/\..+$/i', '', str_replace('www.', '', $_SERVER['HTTP_HOST'])) . '/', $_SERVER['HTTP_REFERER'])) {
            $termsAgree = $rgpd->postFormCheckTermsGeneral();

            // Politica de privacidad
            if ($termsAgree == '') {
                $this->messageError = ERROR_POLITICA;
                $this->redirect = tep_href_link(FILENAME_CHECKOUT_CONFIRMATION);
                return false;
            }
        }

        // Enlace del formulario
        $formAction = (isset($_POST['module_link']) && !empty($_POST['module_link'])) ? $_POST['module_link'] : tep_href_link(FILENAME_LOGOFF);

        // Input hidden
        foreach ($_POST as $key => $value) {
            $htmlInputHidden .= tep_draw_hidden_field($key, $value);
        }

        // Si tenemos inhabilitado la pagina de transición de pago
        if (CHECKOUT_PAYMENT_EXT == 'false') {
            $timeWait = 0;
        }

        // Template
        $html = tools::includeTemplate($sPathTemplate . '/confirmation_payment_ext.php');

        // Si tenemos inhabilitado la pagina de transición de pago
        if (CHECKOUT_PAYMENT_EXT == 'false') {
            echo $html;
        } else {
            return $html;
        }
    }

    /**
     * Confirmación del pedido
     */
    public function confirmation()
    {
        // Variables
        global $htmlInputHidden, $sUrlFormConfirmation, $sAddressShipping, $sAddressShippingTitle, $sAddressPayment, $sAddressPaymentTitle, $sPathModule, $order, $infoPaymentText;
        $infoPaymentText = '';
        $htmlInputHidden = '';

        // Url del formulario de confirmacion
        $sUrlFormConfirmation = tep_href_link(FILENAME_CHECKOUT_PROCESS);

        // Todas las variables accesibles
        extract($GLOBALS);

        // Libreria carrito
        require_once $sPathModule . '/cart.php';

        // Metodo de pago
        require_once DIR_WS_CLASSES . 'payment.php';
        $payment_modules = new \payment($payment);

        // Comprobamos que todo es correcto para guardarla como global
        if (isset($GLOBALS[$payment]) && is_object($GLOBALS[$payment])) {
            ${$payment} = $GLOBALS[$payment];
        }

        // Clase order
        require_once DIR_WS_CLASSES . 'order.php';
        $order = new \order;

        // Actualizamos el estado de los metodos, parece ser que es por un error de la clase order se necesita volver añadir los metodos, mas info en su clase
        $payment_modules->update_status();

        // Cargamos los modulos de envio
        require_once DIR_WS_CLASSES . 'shipping.php';
        $shipping_modules = new \shipping($shipping);

        // Cargamos la clase de totalización
        require_once DIR_WS_CLASSES . 'order_total.php';
        $order_total_modules = new \order_total;
        $order_total_modules->process();

        // Si tenemos que cambiar la url
        if (isset($$payment->form_action_url)) {
            $htmlInputHidden .= tep_draw_hidden_field('module_link', $$payment->form_action_url);
            $sUrlFormConfirmation = tep_href_link(FILENAME_CHECKOUT_PAYMENT_EXT, '');
        }

        // Dirección de envio
        $aAux = explode('%%%', tep_address_format($order->delivery['format_id'], $order->delivery, 1, ' ', '%%%'));
        $sAddressShippingTitle = $aAux[0];
        unset($aAux[0]);
        $sAddressShipping = implode('<br/>', $aAux);

        $addressStore = $this->_getAddressStore();
        if ($addressStore != '') {
            $sAddressShipping = $addressStore;
        }

        // Dirección de facturación
        $aAux = explode('%%%', tep_address_format($order->billing['format_id'], $order->billing, 1, ' ', '%%%'));
        $sAddressPaymentTitle = $aAux[0];
        unset($aAux[0]);
        $sAddressPayment = implode('<br/>', $aAux);

        // Campos hidden
        if (is_array($payment_modules->modules)) {
            $htmlInputHidden .= $payment_modules->process_button();
        }

        // Información del pago
        // Sampedro: Editor de pedidos, si mandamos por GET dicha variable, pasamos los confirmation
        if (!array_key_exists('curl_oe', $_GET)) {
            if (is_array($payment_modules->modules)) {
                if ($confirmation = $payment_modules->confirmation()) {
                    $infoPaymentText .= $confirmation['title'];
                    if (isset($confirmation['fields'])) {
                        for ($i = 0, $n = sizeof($confirmation['fields']); $i < $n; $i++) {
                            $infoPaymentText .= '<p><strong>' . $confirmation['fields'][$i]['title'] . '</strong>: ' . $confirmation['fields'][$i]['field'] . '</p>';
                        }
                    }
                }
            }
        }

        ##### Points/Rewards Module V2.1rc2a check for error BOF #######
        if ((USE_POINTS_SYSTEM == 'true') && (USE_REDEEM_SYSTEM == 'true')) {
            if (isset($_POST['customer_shopping_points_spending']) && is_numeric($_POST['customer_shopping_points_spending']) && ($_POST['customer_shopping_points_spending'] > 0)) {
                $customer_shopping_points_spending = false;

                if (tep_calc_shopping_pvalue($_POST['customer_shopping_points_spending']) < $order->info['total'] && !is_object($$payment) || (tep_get_shopping_points($customer_id) < $_POST['customer_shopping_points_spending'])) {
                    $customer_shopping_points_spending = false;
                    $this->redirect = tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode(REDEEM_SYSTEM_ERROR_POINTS_NOT), 'SSL');
                    return false;
                } else {
                    $customer_shopping_points_spending = $_POST['customer_shopping_points_spending'];
                    if (!tep_session_is_registered('customer_shopping_points_spending')) {
                        tep_session_register('customer_shopping_points_spending');
                    }

                }
            }

            if (tep_not_null(USE_REFERRAL_SYSTEM) && (tep_count_customer_orders() == 0)) {
                if (isset($_POST['customer_referred']) && tep_not_null($_POST['customer_referred'])) {
                    $customer_referral = false;
                    $check_mail = trim($_POST['customer_referred']);

                    if (tep_validate_email($check_mail) == false) {
                        $this->redirect = tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode(REFERRAL_ERROR_NOT_VALID), 'SSL');
                        return false;
                    } else {
                        $valid_referral_query = tep_db_query("select customers_id from " . TABLE_CUSTOMERS . " where customers_email_address = '" . $check_mail . "' limit 1");
                        $valid_referral = tep_db_fetch_array($valid_referral_query);

                        if (!tep_db_num_rows($valid_referral_query)) {
                            $this->redirect = tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode(REFERRAL_ERROR_NOT_FOUND), 'SSL');
                            return false;
                        }

                        if ($check_mail == $order->customer['email_address']) {
                            $this->redirect = tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode(REFERRAL_ERROR_SELF), 'SSL');
                            return false;
                        } else {
                            $customer_referral = $valid_referral['customers_id'];

                            if (!tep_session_is_registered('customer_referral')) {
                                tep_session_register('customer_referral');
                            }

                            if (KEEP_REFERRER_ID == 'true') {
                                tep_db_query("update " . TABLE_CUSTOMERS . " set customer_referral = '" . (int) $customer_referral . "' where customer_referral = '0' and customers_id = '" . (int) $customer_id . "'");
                            }

                        }
                    }
                }
            }
        }
    }

    /**
     * Inicio mismo metodo que el metodo confirmation
     */
    public function index()
    {
        // Variables
        global $aBoxes, $checkoutCart;

        if( array_key_exists('data_oe', $_POST) ) {
            if( array_key_exists( 'choose_insurance', $_POST['data_oe'] ) ) {
                $_SESSION['choose_insurance'] = 1;
            }
            else {
                unset($_SESSION['choose_insurance']);
            }
        }
        
        // Todas las variables accesibles
        extract($GLOBALS);

        // Breadcrumb
        $breadcrumb->add(CHECKOUT_CONFIRMATION_BREADCRUMB, tep_href_link(CHECKOUT_CONFIRMATION_BREADCRUMB));

        // Boxes para la columna derecha
        $aBoxes[] = $boxCheckout->total();
        $aBoxes[] = $boxCheckout->rgpd();
        $aBoxes[] = $boxCheckout->buttonContinue(CHECKOUT_CONFIRM);
        $aBoxes[] = $boxCheckout->iconSafeShopping();

        // LLamamos a su metodo
        $this->confirmation();

        // Carrito
        $checkoutCart = new Cart();

        // Retornamos el template
        return tools::includeTemplate($sPathTemplate . '/confirmation.php');
    }

    private function _getAddressStore() {

        global $sendto, $customer_id, $store_id, $shipping;

        $response = '';

        if ($shipping['id'] != 'retira_retira') {
            return $response;
        }

        if (tep_session_is_registered('customer_id')) {

            if ($store_id > 0 && $sendto > 0) {
                $aDatos = tep_db_query('SELECT ab.entry_telephone, z.zone_name, co.countries_name, ab.address_book_id, ab.entry_firstname as firstname, ab.entry_lastname as lastname, ab.entry_company as company, ab.entry_nif as nif, ab.entry_street_address as street_address, ab.entry_suburb as suburb, ab.entry_city AS city, ab.entry_postcode as postcode, ab.entry_state as state, ab.entry_zone_id as zone_id, ab.entry_country_id as country_id
                FROM address_book ab
                left join ' . TABLE_COUNTRIES . ' co on (ab.entry_country_id = co.countries_id)
                left join ' . TABLE_ZONES . ' z on (ab.entry_zone_id = z.zone_id)
                WHERE ab.customers_id = "' . (int) $customer_id . '" AND ab.address_book_id = ' . $sendto);

                $aDato = tep_db_fetch_array($aDatos);

                switch ((int) $store_id) {
                    case 1:

                        $response = 'Francobordo Artículos Náuticos' . '<br />';
                        $response .= $aDato['firstname'] . ' ' . $aDato['lastname'] . '<br />';
                        $response .= 'Calle San Rafael 8' . '<br />';
                        $response .= 'Alcobendas' . '<br />';
                        $response .= '28108 - Madrid, España' . '<br />';
                        $response .= $aDato['entry_telephone'] . '<br />';
                        $response .= 'N.I.F. ' . $aDato['nif'];
                    break;

                    case 2:

                        $response = 'Velas y Viento'. '<br />';
                        $response .= $aDato['firstname'] . ' ' . $aDato['lastname'] . '<br />';
                        $response .= 'Marina de Denia, Edif. H, Local 3' . '<br />';
                        $response .= 'Denia' . '<br />';
                        $response .= '03700 - Alicante, España' . '<br />';
                        $response .= $aDato['entry_telephone'] . '<br />';
                        $response .= 'N.I.F. ' . $aDato['nif'];

                    break;
                }

                //$response = tep_address_format(tep_get_address_format_id($aDato['country_id']), $aDato, true, ' ', ' ');
            }

            if ($store_id == 0 && $sendto > 0) {
                $response = strip_tags(tep_address_label($customer_id, $address_id, true, ' ', '<br>'), '<br>');
            }

        }

        return $response;
    }
}
