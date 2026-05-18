<?php
// Alias
namespace Checkout;

// Librerias
use util\tools;

class Shipping
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
     * Objeto free shipping, al no tener un método de envio como tal
     */
    public $free;

    /**
     * Constructor de la clase, tendremos las comprobaciones de seguridad para el uso correcto de los métodos de envío
     */
    public function __construct()
    {
        // Variables
        global $navigation, $cart, $customer_default_address_id, $customer_id, $sendto, $messageStack;

        if (isset($_SESSION['estimator_sendto'])) {
            $sendto = $_SESSION['estimator_sendto'];
        }

        // Objeto free shipping al no tener un método de envío como tal, creamos uno ficticio
        $this->free = new class

        {
            public $code = 'free';
            public $title = FREE_SHIPPING_TITLE;
            public $description = FREE_SHIPPING_DESCRIPTION;
            public $icon = 'free.png';
            public $enabled = 1;
            public $_check = 1;
            public $sort_order = 1;
            public $tax_class = 0;
            public $quotes = array(
                'id' => 'free',
                'module' => FREE_SHIPPING_TITLE,
                'tax' => 0,
                'icon' => 'free.png',
                'methods' => array(
                    array(
                        'id' => 'free',
                        'title' => FREE_SHIPPING_DESCRIPTION,
                        'cost' => 0,
                    ),
                ),
            );

            public function quote()
            {
                return $this->quotes;
            }
        };

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

        // Si no tenemos ZONE ID detenemos
        if (basename($_SERVER['SCRIPT_NAME']) != 'checkout_shipping_select_zone.php' && (($aRow->num_rows == 0) || ((($aRow->records['entry_zone_id'] == 0 || $aRow->records['entry_zone_id'] == '') && $aRow->records['entry_city'] == '')))) {
            $this->messageError = CHECKOUT_ERROR_SELECT_ZONE;
            $this->redirect = tep_href_link(FILENAME_CHECKOUT_SELECT_ZONE);
            return false;
        }

        // Si no se seleccionó una dirección de destino de envío, use la propia dirección predeterminada
        if (!tep_session_is_registered('sendto')) {
            tep_session_register('sendto');
            $sendto = $customer_default_address_id;
        }

        // Verificamos la dirección de envío seleccionada, si no eliminamos y redireccionamos al carrito para empezar de 0
        if (pharaonix_queryOne('SELECT address_book_id FROM address_book WHERE customers_id = "' . (int) $customer_id . '" AND address_book_id = "' . (int) $sendto . '"')->num_rows == 0) {
            if (tep_session_is_registered('shipping')) {
                tep_session_unregister('shipping');
            }

            if (tep_session_is_registered('sendto')) {
                tep_session_unregister('sendto');
            }

            // Detenemos
            $this->messageError = CHECKOUT_ERROR_ADDRESS;
            $this->redirect = tep_href_link(FILENAME_SHOPPING_CART);
            return false;
        }

        // Comprobamos el stock de los productos
        if ((STOCK_CHECK == 'true') && (STOCK_ALLOW_CHECKOUT != 'true')) {
            $cart->get_products(); // Reseteamos el any_out_of_stock al método

            // Si encontramos productos sin stock, detenemos
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
     * Se ejecuta cuando es procesado el formulario de seleccion de metodo de envio
     */
    public function process($sPostShipping = false, $comments = false)
    {
        // Variables
        global $shipping, $comments, $total_count, $total_weight, $order;
        $sPostShipping = tep_db_prepare_input($sPostShipping != false ? $sPostShipping : (isset($_POST['shipping']) ? $_POST['shipping'] : ''));

        // Todas las variables accesibles
        extract($GLOBALS);

        $this->_selectStore();

        $this->_chooseInsurance();

        $this->_customerShoppingPoints();

        // Si no tenemos session de comentario de envio lo registramos
        if (!tep_session_is_registered('comments')) {
            tep_session_register('comments');
        }

        // Si no tenemos session de envio lo registramos
        if (!tep_session_is_registered('shipping')) {
            tep_session_register('shipping');
        }

        // Comentario de envio
        $comments = tep_db_prepare_input(isset($_POST['comments']) ? $_POST['comments'] : ($comments != false ? $comments : ''));

        // Si nos han enviado envio
        if ($sPostShipping != '' && strpos($sPostShipping, '_')) {
            // Explotamos para obtener modulo y metodo
            list($module, $method) = explode('_', $sPostShipping);

            // Order
            require_once DIR_WS_CLASSES . 'order.php';
            $order = new \order;

            // Peso y total de la cesta
            $total_weight = $cart->show_weight();
            $total_count = $cart->count_contents();

            // Cargamos los modulos de envio
            require_once DIR_WS_CLASSES . 'shipping.php';
            $shipping_modules = new \shipping;

            // Si existe
            if ((isset($GLOBALS[$module]) && is_object($GLOBALS[$module])) || $module == 'free') {
                // Comprobamos si es gratis
                $hasFree = isset($_GET['curl_oe']) ? true : tools::hasFreeShipping();

                // Si es envio gratis "falsificamos el modulo"
                if ($module == 'free' && $hasFree) {
                    $shipping_modules->modules[] = 'free.php';

                    // Metodo de envio gratuito
                    $GLOBALS['free'] = $this->free;
                }

                // Obtenemos modulo
                $quote = $shipping_modules->quote($method, $module);

                // Si no contiene error
                if (!isset($quote[0]['error']) && (isset($quote[0]['methods'][0]['title'])) && (isset($quote[0]['methods'][0]['cost']))) {
                    // Guardamos shipping
                    $shipping = array(
                        'id' => $module . '_' . $quote[0]['methods'][0]['id'],
                        'title' => $quote[0]['module'] . ' (' . $quote[0]['methods'][0]['title'] . ')',
                        'cost' => $quote[0]['methods'][0]['cost'],
                    );

                    $_SESSION['shipping_backup'] = $shipping;

                    list($module, $method) = explode('_', $shipping['id']);
                    $_SESSION['module_shipping_estimator'] = $module;

                    // Redireccionamos
                    $this->redirect = tep_href_link(FILENAME_CHECKOUT_PAYMENT);

                    return true;
                }
            }
        }

        // Si hemos llegado aqui es que existen errores por ello detenemos
        tep_session_unregister('shipping');
        $this->messageError = isset($quote[0]['error']) ? $quote[0]['error'] : TEXT_ERROR_SHIPPING;
        $this->redirect = tep_href_link(FILENAME_CHECKOUT_SHIPPING);
        return false;
    }

    private function _selectStore()
    {

        if (!empty($_POST)) {
            if (isset($_POST['store_id'])) {
                $nStoreId = intval($_POST['store_id']);

                // Guardia servidor: la tienda de Denia (CP 03700) requiere que el cliente
                // tenga al menos una direccion en ese CP. Si no, se descarta su seleccion.
                if ($nStoreId > 0) {
                    $rStore = tep_db_query('select store_address from store where id_store = ' . $nStoreId);
                    $aStore = tep_db_fetch_array($rStore);
                    if ($aStore && strpos($aStore['store_address'], '03700') !== false) {
                        $bDeniaAllowed = false;
                        if (isset($_SESSION['customer_id']) && (int) $_SESSION['customer_id'] > 0) {
                            $rDenia = tep_db_query("select 1 from " . TABLE_ADDRESS_BOOK . " where customers_id = '" . (int) $_SESSION['customer_id'] . "' and entry_postcode like '03700%' limit 1");
                            $bDeniaAllowed = (tep_db_num_rows($rDenia) > 0);
                        }
                        if (!$bDeniaAllowed) {
                            $nStoreId = 0;
                        }
                    }
                }

                $_SESSION['store_id'] = $nStoreId;
                $_SESSION['store_cost'] = 0;

                if ($nStoreId > 0) {
                    // Consultamos las tiendas
                    $aDato = tep_db_query('select store_cost from store where id_store = ' . (int) $_SESSION['store_id']);
                    $aDato = tep_db_fetch_array($aDato);
                    $shipping['cost'] = $aDato['store_cost'];
                    $_SESSION['store_cost'] = $aDato['store_cost'];
                }
            }
        }

    }

    private function _chooseInsurance()
    {
        if (!empty($_POST)) {
            if (isset($_POST['choose_insurance'])) {
                $_SESSION['choose_insurance'] = 1;
            } else {
                unset($_SESSION['choose_insurance']);
            }
        }

    }

    private function _customerShoppingPoints()
    {
        //Puntos por compra
        if (isset($_POST['customer_shopping_points_spending']) && is_numeric($_POST['customer_shopping_points_spending']) && ($_POST['customer_shopping_points_spending'] > 0)) {
            $customer_shopping_points_spending = false;

            if (tep_calc_shopping_pvalue($_POST['customer_shopping_points_spending']) < $order->info['total'] && !is_object($$payment) || (tep_get_shopping_points($customer_id) < $_POST['customer_shopping_points_spending'])) {
                $customer_shopping_points_spending = false;
                tep_redirect(tep_href_link(FILENAME_CHECKOUT_SHIPPING, 'error_message=' . urlencode(REDEEM_SYSTEM_ERROR_POINTS_NOT), 'SSL'));
            } else {
                $customer_shopping_points_spending = $_POST['customer_shopping_points_spending'];
                if (!tep_session_is_registered('customer_shopping_points_spending')) {
                    tep_session_register('customer_shopping_points_spending');
                }

            }
        }
    }
    
    public function getAddressText()
    {

        global $customer_id, $sendto;

        $response = '';

        if (tep_session_is_registered('customer_id')) {

            $store_id = intval($_POST['store_id']);

            if ($store_id > 0 && $sendto > 0 && $_POST['shipping'] == 'retira_retira') {
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

                        $response = 'Velas y Viento' . '<br />';
                        $response .= $aDato['firstname'] . ' ' . $aDato['lastname'] . '<br />';
                        $response .= 'Marina de Denia, Edif. H, Local 3' . '<br />';
                        $response .= 'Denia' . '<br />';
                        $response .= '03700 - Alicante, España' . '<br />';
                        $response .= $aDato['entry_telephone'] . '<br />';
                        $response .= 'N.I.F. ' . $aDato['nif'];

                        break;
                }

                die($response);

            }

            $response = strip_tags(tep_address_label($customer_id, $sendto, true, ' ', '<br>'), '<br>');

            die($response);

        }

        
    }

    /**
     * Seleccionar zona de envío ya que no se ha podido obtener por alguna razón
     */
    public function selectZone()
    {
        // Todas las variables accesibles
        extract($GLOBALS);

        // Si estamos enviando el ZONE ID por POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['zone_id'])) {
            if ((int) $_POST['city_id'] == 0) {
                tep_db_perform('cities', array(
                    'id_country' => (int) $_POST['country'],
                    'id_zone' => intval($_POST['zone_id']),
                    'name' => tep_db_prepare_input($_POST['city']),
                    'cp' => tep_db_prepare_input($_POST['postcode']),
                    'custom' => 1,
                    'id_customer' => $customer_id,
                ));
                $_POST['city_id'] = tep_db_insert_id();
            }

            // Actualizamos la ZONE ID
            tep_db_query('UPDATE address_book SET entry_city_id = ' . (int) $_POST['city_id'] . ', entry_postcode = ' . $_POST['postcode'] . ', entry_zone_id = ' . (int) $_POST['zone_id'] . ' WHERE address_book_id = ' . $customer_default_address_id . ';');

            // Redireccionamos
            $this->redirect = tep_href_link(FILENAME_CHECKOUT_SHIPPING);
            return true;
        }

        // Breadcrumb
        $breadcrumb->add(CHECKOUT_SELECT_ZONE_BREADCRUMB, tep_href_link(FILENAME_CHECKOUT_SELECT_ZONE));

        // Retornamos el template
        return tools::includeTemplate($sPathTemplate . '/shipping_select_zone.php');
    }

    /**
     * Seleccionar dirección de envio
     */
    public function selectAddressShipping()
    {
        // Variables
        global $customer_id, $sendto;
        $sPostAddress = (int) tep_db_prepare_input(isset($_POST['address']) ? $_POST['address'] : '');

        // Si no existe sendto lo creamos
        if (!tep_session_is_registered('sendto')) {
            tep_session_register('sendto');
        }

        // Reiniciamos shipping si existiese
        if (tep_session_is_registered('shipping')) {
            tep_session_unregister('shipping');
        }

        // Comprobamos si existe
        if (pharaonix_queryOne('SELECT address_book_id FROM address_book WHERE customers_id = "' . (int) $customer_id . '" AND address_book_id = "' . $sPostAddress . '"')->num_rows > 0) {
            $sendto = $sPostAddress;
        } else {
            $sendto = $customer_default_address_id;
        }

        if (isset($_SESSION['estimator_sendto'])) {
            unset($_SESSION['estimator_sendto']);
        }

        // Redireccionamos
        $this->redirect = tep_href_link(FILENAME_CHECKOUT_SHIPPING);
        return true;
    }

    /**
     * Realiza toda la lógica del checkout shipping
     */
    public function shipping()
    {
        // Variables
        global $order, $total_weight, $total_count, $shipping, $quotes, $sAddress, $sAddressTitle, $aAddressBook, $freeShippingForce;
        $aAddressBook = array();

        // Todas las variables accesibles
        extract($GLOBALS);

        // Variable de control para poner envío gratis automatico.
        // Esto es debido a que cuando salta envío gratis se ponga automaticamente
        // pero que esto no influya si por ejemplo tiendas que tienes envío gratis pero
        // tambien puedes seleccionar otro tipo de envío no se cambie
        if (!tep_session_is_registered('freeShippingForce')) {
            $freeShippingForce = false;
            tep_session_register('freeShippingForce');
        }

        // Clase order
        require_once DIR_WS_CLASSES . 'order.php';
        $order = new \order;

        // Peso y total de la cesta
        $total_weight = $cart->show_weight();
        $total_count = $cart->count_contents();

        // Cargamos los modulos de envio
        require_once DIR_WS_CLASSES . 'shipping.php';
        $shipping_modules = new \shipping;

        // Si tenemos envío gratuito
        $bFreeShipping = tools::hasFreeShipping();

        // Si tenemos envio gratuito
        if ($bFreeShipping) {
            $shipping_modules->modules[] = 'free.php';

            // Metodo de envio gratuito
            $GLOBALS['free'] = $this->free;

            // Seleccionamos el método como gratis
            $shipping = !$freeShippingForce ? $GLOBALS['free']->quotes['methods'][0] : $shipping;
            $freeShippingForce = true;

            // Obtener metodos de envio, en este caso solo el free
            if (CHECKOUT_ONLY_METHOD_FREE_SHIPPING == 'false') {
                $quotes = $shipping_modules->quote();
            } else {
                $quotes = $shipping_modules->quote('', 'free');
            }
        } else {
            // Si no tenemos envío gratis cambiamos
            $freeShippingForce = false;

            // Obtener todas los envío disponibles
            $quotes = $shipping_modules->quote();
        }

        // Migracion preseleccion v2: sesiones existentes pueden tener shipping=retira_retira
        // heredado de la antigua preseleccion automatica (cheapest = "Recoger en tienda").
        // Si el cliente no esta enviando ahora mismo el formulario de envio, reseteamos una vez
        // para que la nueva preseleccion (TIPSA por mensajeria) se aplique.
        if (
            !isset($_SESSION['shipping_preselect_v2'])
            && tep_session_is_registered('shipping')
            && is_array($shipping)
            && isset($shipping['id'])
            && $shipping['id'] === 'retira_retira'
            && !(isset($_POST['action']) && $_POST['action'] === 'process')
        ) {
            tep_session_unregister('shipping');
            $shipping = false;
        }
        $_SESSION['shipping_preselect_v2'] = 1;

        // Si no se ha seleccionado ningún método de envío, seleccione automáticamente el método más barato.
        // si el estado de los módulos se cambió cuando no había ninguno disponible, para ahorrar en la implementación
        // un método de selección de fuerza javascript, también selecciona automáticamente el envío más barato
        // método si hay más de un módulo habilitado
        if (!tep_session_is_registered('shipping') || (tep_session_is_registered('shipping') && ($shipping == false) && (tep_count_shipping_modules() > 1))) {
            // preselected() en lugar de cheapest() para preferir TIPSA y excluir "Recoger en tienda"
            $shipping = $shipping_modules->preselected();
        }

        // Saltar la seleccion de metodo de envio por tener solo 1
        if (!array_key_exists('curl_oe', $_GET) && SKIP_SHIPPING_METHOD_ONLY_ONE == 'true' && is_array($quotes) && count($quotes) == 1) {
            // Si no tenemos session de envio lo registramos
            if (!tep_session_is_registered('shipping')) {
                tep_session_register('shipping');
            }

            // Redireccionamos
            $this->redirect = tep_href_link(FILENAME_CHECKOUT_PAYMENT);
            return true;
        }

        // Dirección de envio
        $aAux = explode('%%%', tep_address_format($order->delivery['format_id'], $order->delivery, 1, ' ', '%%%'));
        $sAddressTitle = $aAux[0];
        unset($aAux[0]);
        $sAddress = implode('<br/>', $aAux);

        // Retornamos
        return true;
    }

    /**
     * Muestra la pagina de metodos de envio
     */
    public function index()
    {
        // Variables
        global $aBoxes, $cartID;

        // Todas las variables accesibles
        extract($GLOBALS);

        // Registra una ID aleatoria en la sesión para verificar todo el proceso de pago, contra alteraciones en los contenidos del carrito de compras.
        if (!tep_session_is_registered('cartID')) {
            tep_session_register('cartID');
        } elseif (($cartID != $cart->cartID) && tep_session_is_registered('shipping')) {
            tep_session_unregister('shipping');
        }

        // Generamos
        $cartID = $cart->cartID = $cart->generate_cart_id();

        if (isset($_SESSION['module_shipping_estimator'])) {
            require_once DIR_WS_CLASSES . 'shipping.php';
            $shipping_modules = new \shipping;
            $shipping = $shipping_modules->select($_SESSION['module_shipping_estimator']);
        }

        // Breadcrumb
        $breadcrumb->add(CHECKOUT_SHIPPING_BREADCRUMB, tep_href_link(FILENAME_CHECKOUT_SHIPPING));

        require_once DIR_WS_CLASSES . 'order.php';
        $order = new \order;

        // Boxes para la columna derecha
        $aBoxes[] = $boxCheckout->insurance($order);
        $aBoxes[] = $boxCheckout->total();
        $aBoxes[] = $boxCheckout->buttonContinue(CHECKOUT_CONTINUE);
        $aBoxes[] = $boxCheckout->iconSafeShopping();

        // LLamamos a su metodo
        $this->shipping();

        // Retornamos el template
        return tools::includeTemplate($sPathTemplate . '/shipping.php');
    }
}
