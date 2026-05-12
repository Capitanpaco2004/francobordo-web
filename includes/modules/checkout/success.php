<?php
// Alias
namespace Checkout;

// Librerias
use util\tools;
use util\HoldingOrderManager;

class Success
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
        // Si no estamos logueados
        if (!tep_session_is_registered('customer_id')) {
            $this->messageError = CHECKOUT_ERROR_LOGIN;
            $this->redirect = tep_href_link(FILENAME_LOGIN);
            return false;
        }
    }

    /*
     * Procesa el pedido
     */
    public function index()
    {
    	// Variables
    	global $ordersId;

        // Todas las variables accesibles
        extract($GLOBALS);

        // Breadcrumb
        $breadcrumb->add(CHECKOUT_SUCCESS_BREADCRUMB, tep_href_link(FILENAME_CHECKOUT_SUCCESS));

		// Saco la ID del último pedido del cliente
        $ordersId = pharaonix_queryOne("select orders_id from " . TABLE_ORDERS . " where customers_id = '" . (int) $customer_id . "' order by date_purchased desc limit 1")->records['orders_id'];

        // Saco los productos
        $products_array = array();
        $products_query = tep_db_query("select products_id, products_name, product_ean from " . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . (int) $ordersId . "' order by products_name");
        while ($products = tep_db_fetch_array($products_query)) {
            $products_array[] = array('id' => $products['products_id'],
                'text' => $products['products_name'],
				'ean' => $products['product_ean']);
        }

		$aVars['products'] = $products_array;

        // Saco el valor del total
        $totals_query = tep_db_query("select value, class from " . TABLE_ORDERS_TOTAL . " where orders_id = '" . (int) $ordersId . "' order by sort_order");

		// Limpieza de salvaguardados por el cliente
		HoldingOrderManager::removeAllHoldingOrdersFromCustomer($customer_id);

        // Retornamos el template
        return tools::includeTemplate($sPathTemplate . '/success.php', $aVars);
    }
}
