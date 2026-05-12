<?php

class SequraBuilder extends SequraBuilderOSC
{
    public $discount = null;
    public $dto = null;
    public function handlingItems()
    {
        $method = explode('_', $this->_current_order->info['shipping_method']);
        $module = $method[0];
        if (count($this->_shipped_ids) > 0) {
            $module = substr($GLOBALS['shipping']['id'], 0, strpos($GLOBALS['shipping']['id'], '_'));
        }
        $shipping_tax = tep_get_tax_rate(1, $this->_current_order->delivery['country']['id'], $this->_current_order->delivery['zone_id']);
        if (strpos(strtolower($module), 'kiala') !== false) {
            $shipping_tax = 0;
        }

        if (0 == $this->_current_order->info['shipping_cost']) {
            return array();
        }

        $handling = array(
            'type' => 'handling',
            'reference' => self::notNull($this->_current_order->info['shipping_method']),
            'name' => 'Gastos de envío',
            'tax_rate' => 0,
        );

        $handling['tax_rate'] = 0;
        $handling['total_without_tax'] = $handling['total_with_tax'] = self::integerPrice($this->_current_order->info['shipping_cost'] * (1 + $shipping_tax / 100));

        $items[] = $handling;
        return $items;
    }

    public function extraItems()
    {
        global $coupon;
        $data = parent::extraItems();
        $discount = $other_payments = null;

        //Puntos descuento
        /*
        include_once(DIR_WS_MODULES.'/order_total/ot_redemptions.php');
        $ot = new ot_redemptions();
        $ot->process();
        if(isset($GLOBALS['ot_redemptions']) && $GLOBALS['ot_redemptions']->output){
        $discount['value'] += $GLOBALS['ot_redemptions']->output['value'];
        }*/
        if (isset($GLOBALS['ot_redemptions']->output[0]['text_tax'])) {
            $other_payments = $GLOBALS['ot_redemptions']->output[0];
            $other_payments['value'] = (float) str_replace(',', '.', strip_tags($other_payments['text_tax']));
            $other_payments['tax'] = 0;
        }
        if (count($this->_shipped_ids) > 0) {
            $sql = 'select title,value from ' . TABLE_ORDERS_TOTAL . ' where class = "ot_redemptions" and orders_id=' . $this->_current_order_id;
            $query = tep_db_query($sql);
            $redemptions = tep_db_fetch_array($query);
            $other_payments['value'] = (-1) * $redemptions['value'];
            /* 1 es el IVA 21% Podría no estar bien en algún momento*/
            /* 195 es España*/
            //$other_payments['tax'] = tep_get_tax_rate(1, 195, $this->_current_order->delivery['zone_id']);
            $other_payments['tax'] = 0;
        }

        if ($other_payments && $other_payments['value'] < 0) {
            $item = array();
            $item["type"] = "other_payment";
            $item["reference"] = 'puntos';
            $item["name"] = 'Puntos canjeados';
            //$item["total_without_tax"] = self::integerPrice($discount['value']);
            $item["total_with_tax"] = self::integerPrice($other_payments['value'] * (1 + $other_payments['tax'] / 100));
            $data[] = $item;
        }

        //order discounts
        if (isset($GLOBALS['ot_discount_coupon']) && $GLOBALS['order']->coupon->applied_discount) {
            $discount = $GLOBALS['ot_discount_coupon']->output[0];
            $applied_discount = 0;
            foreach ($GLOBALS['order']->coupon->applied_discount as $dto) {
                $applied_discount += $dto;
            }
            $discount['value'] = -1 * $dto;
            $discount['tax'] = 0;
        }

        if (count($this->_shipped_ids) > 0) {
            $sql = 'select title,value from ' . TABLE_ORDERS_TOTAL . ' where class = "ot_discount_coupon" and orders_id=' . $this->_current_order_id;
            $query = tep_db_query($sql);
            $discount = tep_db_fetch_array($query);
            /* 1 es el IVA 21% Podría no estar bien en algún momento*/
            /* 195 es España*/
            $discount['tax'] = tep_get_tax_rate(1, 195, $this->_current_order->delivery['zone_id']);
        }

        if ($discount) {
            $item = array();
            $item["type"] = "discount";
            $item["reference"] = self::notNull($discount['title']);
            $item["name"] = 'Descuento';
			$item["total_without_tax"] = $item["total_with_tax"] = self::integerPrice('-'.$discount['value_tax'] * (1 + $discount['tax'] / 100)); // Cambio porque cuando tenia cupon descuento no estaba entrando correctamente en la verificacion de sequra
			$data[] = $item;
        }

        $this->dto = $discount;

        //ot_insurance
        $insurance = null;
        include_once DIR_WS_MODULES . '/order_total/ot_insurance.php';
        $ot = new ot_insurance();

        if (isset($_SESSION['choose_insurance'])) {
            $ot->process();
            $insurance = $GLOBALS['ot_insurance']->output[0];
        }
        if (count($this->_shipped_ids) > 0) {
            $sql = 'select title,value from ' . TABLE_ORDERS_TOTAL . ' where class = "ot_insurance" and orders_id=' . $this->_current_order_id;
            $query = tep_db_query($sql);
            $insurance = tep_db_fetch_array($query);
        }
        if ($insurance) {
            $tax = tep_get_tax_rate($ot->tax_class, $this->_current_order->delivery['country']['id'], $this->_current_order->delivery['zone_id']);

            $insurance['value'] = $insurance['value'] * (1 + $tax / 100);

            $item = array(
                'type' => 'handling',
                'total_with_tax' => self::integerPrice($insurance['value']),
                'tax_rate' => 0, //$this->taxRate(),
                'total_without_tax' => self::integerPrice($insurance['value']), //$this->withoutTax(),
                'reference' => $insurance['title'],
                'name' => 'Seguro',

            );
            $data[] = $item;
        }

        return $data;
    }

    public function merchant()
    {
        $ret = parent::merchant();
        if ('sequra' == $_SESSION['payment']) {
            unset($ret['approved_url']);
            $ret['approved_callback'] = 'shop_callback_sequra_approved';
        }
        return $ret;
    }
}
