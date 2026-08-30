<?php
// Alias
namespace Checkout;

// Librerias
use util\tools;

class Payment
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
     * Constructor de la clase, tendremos las comprobaciones de seguridad para el uso correcto de los métodos de pago
     */
    public function __construct()
    {
        // Variables
        global $navigation, $cart, $customer_default_address_id, $messageStack, $cartID, $billto, $customer_id;

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

        // Eliminamos recalc
        if (tep_session_is_registered('recalc')) {
            tep_session_unregister('recalc');
        }

        // Comprobamos el ZONE ID
        $aRow = pharaonix_queryOne('SELECT entry_zone_id, entry_city, entry_city_id, entry_country_id FROM address_book WHERE address_book_id = "' . (int) $customer_default_address_id . '"');

        // Si no tenemos ZONE ID redireccionamos a select zone
        if (($aRow->num_rows == 0) || ((($aRow->records['entry_zone_id'] == 0 || $aRow->records['entry_zone_id'] == '') && $aRow->records['entry_city'] == ''))) {
            $this->messageError = CHECKOUT_ERROR_SELECT_ZONE;
            $this->redirect = tep_href_link(FILENAME_CHECKOUT_SELECT_ZONE);
            return false;
        }

        // Si no se ha seleccionado ningún método de envío, redirija al cliente a la página de selección del método de envío
        if (!tep_session_is_registered('shipping') || empty($_SESSION['shipping'])) {
            $this->messageError = CHECKOUT_ERROR_SHIPPING;
            $this->redirect = tep_href_link(FILENAME_CHECKOUT_SHIPPING);
            return false;
        }

        // Evitar los intentos de pirateo durante el proceso de pago al verificar el carotID interno
        if (isset($cart->cartID) && tep_session_is_registered('cartID')) {
            if ($cart->cartID != $cartID) {
                $this->redirect = tep_href_link(FILENAME_CHECKOUT_SHIPPING);
                return false;
            }
        }

        // Si no se seleccionó una dirección de facturación, use la propia dirección predeterminada
        if (!tep_session_is_registered('billto')) {
            tep_session_register('billto');
            $billto = $customer_default_address_id;
        }

        // Verificamos la dirección de facturación seleccionada, si no eliminamos y redireccionamos al carrito para empezar de 0
        if (pharaonix_queryOne('SELECT address_book_id FROM address_book WHERE customers_id = "' . (int) $customer_id . '" AND address_book_id = "' . (int) $billto . '"')->num_rows == 0) {
            if (tep_session_is_registered('payment')) {
                tep_session_unregister('payment');
            }

            if (tep_session_is_registered('billto')) {
                tep_session_unregister('billto');
            }

            $this->messageError = CHECKOUT_ERROR_ADDRESS;
            $this->redirect = tep_href_link(FILENAME_SHOPPING_CART);
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
    }

    /**
     * Seleccionar dirección de facturación
     */
    public function selectAddressPayment()
    {
        // Variables
        global $customer_id, $billto, $customer_default_address_id;
        $sPostAddress = (int) tep_db_prepare_input(isset($_POST['address']) ? $_POST['address'] : '');

        // Si no existe billto lo creamos
        if (!tep_session_is_registered('billto')) {
            tep_session_register('billto');
        }

        // Comprobamos si existe
        if (pharaonix_queryOne('SELECT address_book_id FROM address_book WHERE customers_id = "' . (int) $customer_id . '" AND address_book_id = "' . $sPostAddress . '"')->num_rows > 0) {
            $billto = $sPostAddress;
        } else {
            $billto = $customer_default_address_id;
        }

        // Redireccionamos
        $this->redirect = tep_href_link(FILENAME_CHECKOUT_PAYMENT);
        return true;
    }

    /**
     * Se ejecuta cuando es procesado el formulario de seleccion de metodo de pago
     */
    public function process()
    {
        // Variables
        global $payment, $order, $customer_id, $messageStack;
        $sPostPayment = tep_db_prepare_input(isset($_POST['payment']) ? $_POST['payment'] : '');

        // Si nos han enviado pago
        if ($sPostPayment != '') {
            // Si no tenemos session de pago lo registramos
            if (!tep_session_is_registered('payment')) {
                tep_session_register('payment');
            }

            // Guardamos
            $payment = $sPostPayment;

            // Cargamos librerias
            require_once DIR_WS_CLASSES . 'payment.php';
            require_once DIR_WS_CLASSES . 'order.php';

            // Metodos de pago
            $payment_modules = new \payment($payment);

            // Comprobamos que todo es correcto para guardarla como global
            if (isset($GLOBALS[$payment]) && is_object($GLOBALS[$payment])) {
                ${$payment} = $GLOBALS[$payment];
            }

            // Pedido
            $order = new \order;

            // Actualizamos el estado de los metodos, parece ser que es por un error de la clase order se necesita volver añadir los metodos, mas info en su clase
            $payment_modules->update_status();

            // Comprobamos que este todo correcto
            if ($payment_modules->selected_module != $payment || (is_array($payment_modules->modules) && (sizeof($payment_modules->modules) > 1) && !is_object($$payment)) || (is_object($$payment) && ($$payment->enabled == false))) {
                $this->messageError = CHECKOUT_ERROR_PAYMENT;
                $this->redirect = tep_href_link(FILENAME_CHECKOUT_PAYMENT);
                return false;
            }

            // Sampedro: Editor de pedidos, si mandamos por GET dicha variable, pasamos los pre confirmation
            if (!array_key_exists('curl_oe', $_GET)) {
                if (is_array($payment_modules->modules)) {
                    $payment_modules->pre_confirmation_check();
                }
            }

            ##### Points/Rewards Module V2.1rc2a check for error BOF #######
            if ((USE_POINTS_SYSTEM == 'true') && (USE_REDEEM_SYSTEM == 'true')) {
                
                if (isset($_POST['customer_shopping_points_spending']) && is_numeric($_POST['customer_shopping_points_spending']) && ($_POST['customer_shopping_points_spending'] > 0)) {
                    $customer_shopping_points_spending = false;

                    // #FB-PUNTOS-TOPE (2026-08-29): esta es la UNICA de las tres validaciones de
                    // checkout que persiste el valor en sesion. La condicion original era
                    // (A && B) || C y se atravesaba entera pidiendo MAS puntos que el total: con
                    // pvalue >= total la parte A es falsa y C solo mira el saldo. Se conserva tal
                    // cual (con parentesis explicitos) para no rechazar nada que hoy se acepte,
                    // pero el valor que se GUARDA ya no es el del POST: se recorta al saldo REAL
                    // de BD. El tope por importe del pedido lo aplica ot_redemptions, que es el
                    // unico punto donde $order->info['total'] es el total definitivo (aqui aun no
                    // han pasado seguro/comisiones, y capar con este total recortaria canjes
                    // legitimos de quien paga el pedido entero con puntos).
                    $nSaldoRealPuntos = (int) tep_get_shopping_points($customer_id);

                    if (((tep_calc_shopping_pvalue($_POST['customer_shopping_points_spending']) < $order->info['total']) && !is_object($$payment)) || ($nSaldoRealPuntos < $_POST['customer_shopping_points_spending'])) {
                        $customer_shopping_points_spending = false;
                        $this->redirect = tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode(REDEEM_SYSTEM_ERROR_POINTS_NOT), 'SSL');
                        return false;
                    } else {
                        $customer_shopping_points_spending = max(0, min((int) floor((float) $_POST['customer_shopping_points_spending']), $nSaldoRealPuntos));
                        $_SESSION['customer_shopping_points_spending'] = $customer_shopping_points_spending;
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

            // LLegados aqui todo es correcto redireccionamos al confirmation
            $this->redirect = tep_href_link(FILENAME_CHECKOUT_CONFIRMATION);
            return true;
        } else {
            $this->messageError = CHECKOUT_ERROR_PAYMENT;
            $this->redirect = tep_href_link(FILENAME_CHECKOUT_PAYMENT);
            return false;
        }

    }

    /**
     * Realiza toda la lógica del checkout payment
     */
    public function payment()
    {
        // Variables
        global $order, $total_weight, $total_count, $sAddress, $sAddressTitle, $quotes, $payment_modules, $payment;

        // Todas las variables accesibles
        extract($GLOBALS);

        // Breadcrumb
        $breadcrumb->add(CHECKOUT_PAYMENT_BREADCRUMB, tep_href_link(FILENAME_CHECKOUT_PAYMENT));

        // Clase order
        require_once DIR_WS_CLASSES . 'order.php';
        $order = new \order;

        // Peso y total de la cesta
        $total_weight = $cart->show_weight();
        $total_count = $cart->count_contents();

        // Modulos de pago
        require_once DIR_WS_CLASSES . 'payment.php';
        $payment_modules = new \payment;

        // Dirección de facturación
        $aAux = explode('%%%', tep_address_format($order->billing['format_id'], $order->billing, 1, ' ', '%%%'));
        $sAddressTitle = $aAux[0];
        unset($aAux[0]);
        $sAddress = implode('<br/>', $aAux);

        // Metodos de pago
        $quotes = $payment_modules->selection();

        // Si no se ha seleccionado ningún método de pago, seleccione automáticamente el primero.
        if (!tep_session_is_registered('payment') || (tep_session_is_registered('payment') && ($payment == false) && count($quotes) > 0)) {
            // Si no tenemos session de pago lo registramos
            if (!tep_session_is_registered('payment')) {
                tep_session_register('payment');
            }

            $payment = $quotes[0]['id'];
        }
    }

    /**
     * Muestra la pagina de metodos de pago
     */
    public function index()
    {
        // Variables
        global $aBoxes;

        // Todas las variables accesibles
        extract($GLOBALS);

        // Boxes para la columna derecha
        $aBoxes[] = $boxCheckout->points();
        $aBoxes[] = $boxCheckout->total();
        $aBoxes[] = $boxCheckout->buttonContinue(CHECKOUT_CONTINUE);
        $aBoxes[] = $boxCheckout->iconSafeShopping();

        // LLamamos a su metodo
        $this->payment();

        // Retornamos el template
        return tools::includeTemplate($sPathTemplate . '/payment.php');
    }
}
