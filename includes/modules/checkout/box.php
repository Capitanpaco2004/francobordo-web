<?php
// Alias
namespace Checkout;

// Librerias
use Checkout\Shipping;
use util\tools;

class Box
{
    /**
     * Muestra la ventana de nuestra libreta de direcciones
     */
    public function addressBook($default, $title, $urlAction)
    {
        // Variables
        global $sPathTemplate, $customer_id;
        $addressBook = array();

        // Obtenemos direcciones
        $aRows = pharaonix_query('SELECT c.name as city, entry_city_id, address_book_id, entry_firstname as firstname, entry_lastname as lastname, entry_company as company, entry_nif as nif, entry_street_address as street_address, entry_city as city, entry_postcode as postcode, entry_state as state, entry_zone_id as zone_id, entry_country_id as country_id
								  FROM ' . TABLE_ADDRESS_BOOK . ' a
								  LEFT JOIN cities c ON c.id = a.entry_city_id
								  WHERE customers_id = "' . (int) $customer_id . '"
								  ORDER BY firstname, lastname');

        while ($aRow = tep_db_fetch_array($aRows->records)) {
            $aRow['active'] = false;
            $nFormatId = tep_get_address_format_id($aRow['country_id']);

            if ($aRow['address_book_id'] == $default) {
                $aRow['active'] = true;
            }

            $aRow['address_format'] = '<b>' . preg_replace('/ /', '</b>', tep_address_format($nFormatId, $aRow, true, ' ', ' '), 1);
            $addressBook[] = $aRow;
        }

        // Retornamos el template
        return tools::includeTemplate($sPathTemplate . '/box_addressBook.php', array(
            'title' => $title,
            'urlAction' => $urlAction,
            'addressBook' => $addressBook,
        ));
    }

    /**
     * Muestra una caja para aceptar la RGPD
     */
    public function rgpd()
    {
        // Variables
        global $sPathTemplate, $htmlRgpd, $rgpd;

        // Rgpd
        $htmlRgpd = $rgpd->formCheckTermsGeneral();

        // Retornamos el template
        return $htmlRgpd == '' ? false : tools::includeTemplate($sPathTemplate . '/box_rgpd.php');
    }

    /**
     * Muestra una caja para introducir el cupon
     */
    public function coupon()
    {
        // Variables
        global $sPathTemplate;

        // Retornamos el template
        return tools::includeTemplate($sPathTemplate . '/box_coupon.php');
    }

    public function points()
    {
        // Variables
        global $sPathTemplate;

        // Retornamos el template
        return tools::includeTemplate($sPathTemplate . '/box_points.php');
    }

    public function insurance($order)
    {
        // Variables
        global $sPathTemplate, $currencies;

        $rawAmount = $this->getInsuranceAmount($order);
        if ($rawAmount <= 0) {
            return '';
        }

        // Retornamos el template
        return tools::includeTemplate(
            $sPathTemplate . '/box_choose_insurance.php',
            [
                'insurance_amount' => ($currencies->format($rawAmount))
            ]
        );
    }

    private function getInsuranceAmount($order) : float {
        global $currencies, $bActiveCheckoutOnePage;

        $tax_amount = 0;
        $this_amount = 0;

        if (MODULE_ORDER_TOTAL_INSURANCE_STATUS == 'true') {
            switch (MODULE_ORDER_TOTAL_INSURANCE_DESTINATION) {
                case 'national':
                    if ($order->delivery['country_id'] == STORE_COUNTRY) {
                        $pass = true;
                    }

                    break;
                case 'international':
                    if ($order->delivery['country_id'] != STORE_COUNTRY) {
                        $pass = true;
                    }

                    break;
                case 'both':
                    $pass = true;
                    break;
                default:
                    $pass = false;
                    break;
            }
        }

        // Added in by Juan Velez to stop any negative amount
        if ($order->info['total'] < MODULE_ORDER_TOTAL_INSURANCE_OVER) {
            $pass = false;
        }
        
        // End of add by Juan Velez
        if ($pass == true) {

            //variable $how_often is the amount of times to multiply the insurance rate.

            $insurance_amount = 0;
            if ($_SESSION['insurance_amount']) {
                $insurance_amount = (float)$_SESSION['insurance_amount'];
            }

            $how_often = ceil(($order->info['total'] - $order->info['tax'] - MODULE_ORDER_TOTAL_INSURANCE_OVER - $insurance_amount) / MODULE_ORDER_TOTAL_INSURANCE_INCREMENT);

            //variable $this_amount becomes the total insurance fee once multiplied by $how_often below.
            $this_amount = MODULE_ORDER_TOTAL_INSURANCE_FEE * $how_often;
            if ($this_amount < MODULE_ORDER_TOTAL_INSURANCE_MIN_CHARGE) {
                $this_amount = MODULE_ORDER_TOTAL_INSURANCE_MIN_CHARGE;
            }

            // If international shipment, multiply insurance charge by multiplier
            if ($order->delivery['country_id'] != STORE_COUNTRY) {
                $this_amount *= $this->multiplier;
            }

            // @Victor.DENOX Debido al ticket #BJU-123-41792 he obtenido el IVA del envío para saber si hay que calcular el IVA del seguro en el editor de pedidos

            $module = substr((string) $GLOBALS['shipping']['id'], 0, strpos((string) $GLOBALS['shipping']['id'], '_'));

            if ((MODULE_ORDER_TOTAL_INSURANCE_TAX_CLASS > 0 && $GLOBALS[$module]->tax_class > 0) || $module == 'freeamount') {
                $tax = tep_get_tax_rate(MODULE_ORDER_TOTAL_INSURANCE_TAX_CLASS, $order->delivery['country']['id'], $order->delivery['zone_id']);
                $tax_amount = tep_calculate_tax(($this_amount), $tax);
            }
            
        }

        $this_amount = (float)$this_amount + (float)$tax_amount;
        $_SESSION['insurance_amount'] = $this_amount;

        return $this_amount;
    }
    
    /**
     * Muestra los iconos de compra segura
     */
    public function iconSafeShopping()
    {
        // Variables
        global $sPathTemplate;

        // Retornamos el template
        return tools::includeTemplate($sPathTemplate . '/box_iconSafeShopping.php');
    }

    /**
     * Muestra la totalización de la compra
     */
    public function total()
    {
        // Variables
        global $sPathTemplate, $sPathModule, $totalizations, $showTax, $totalText, $totalValue, $shipping, $order, $customer_group_id;
        $showTax = false;
        $errorShipping = false;

        // Todas las variables accesibles
        extract($GLOBALS);

        // Libreria checkout/shipping para que pase siempre por el checkout_shipping y asi obtener totales correctamente
        require_once $sPathModule . '/shipping.php';
        $checkoutShipping = new Shipping();

        // Si shipping ha dado algun error, reiniciamos
        if (!$checkoutShipping->shipping()) {
            //return $this->total();
        }

        // Si hemos obtenido shipping al realizar el metodo shipping del checkout, procesamos. Si diera algun problema...
        if (!(isset($shipping) && is_array($shipping) && $checkoutShipping->process($shipping['id']))) {
            $errorShipping = true;
        }

        // Cargamos la clase de totalización
        require_once DIR_WS_CLASSES . 'order_total.php';
        $order_total_modules = new \order_total;
        //$order_total_modules->process();

        // Si el cliente tiene mostrar los precios con Impuestos SI y No tiene Recargo
        /*if ($sppc_customer_group_show_tax == '1' && tiene_recargo($customer_id) != 1) {
            $showTax = false;
        } else {
            // No muestra los precios con IVA o Tiene Recargo de Equivalencia
            $showTax = true;
        }
        */
        // Sampedro: Editor de pedidos, si mandamos por GET forzamos para que muestre iva
        if (array_key_exists('curl_oe', $_GET)) {
            $showTax = true;
        }

        // Obtenemos las totalizaciones en forma de array

		//$totalizations = $order_total_modules->process($showTax);
        $totalizations = $order_total_modules->output($showTax, false);

		// Eliminamos el total para mostrarlo de diferente forma
		if (isset($totalizations['ot_total'])) {
			$totalText = $totalizations['ot_total'][0]['text'];
			$totalValue = $totalizations['ot_total'][0]['value'];
			unset($totalizations['ot_total']);
		} else {
			// Manejar el caso en que la clave no existe
			$totalText = '';
			$totalValue = '';
		}
		unset($totalizations['ot_total']);

        //unset($totalizations['ot_tax']);

        // Si a dado error mostramos el total como el subtotal
        if ($errorShipping) {
            //$totalText = $totalizations['ot_subtotal'][0]['text_tax'];
            //$totalValue = $totalizations['ot_subtotal'][0]['value'];
        }

        // Retornamos el template
        return tools::includeTemplate($sPathTemplate . '/box_total.php');
    }

    /**
     * Muestra el botón para seguir, este puede ser un un DIV que hara evento con el form checkout submit o un href
     */
    public function buttonContinue($sText, $sHref = '')
    {
        // Variables
        global $sPathTemplate;

        // Retornamos el template
        return tools::includeTemplate($sPathTemplate . '/box_buttonContinue.php', array(
            'sHref' => $sHref,
            'sText' => $sText,
        ));
    }
}
