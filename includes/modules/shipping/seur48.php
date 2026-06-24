<?php
/**
 * Módulo de envío "SEUR 24" (servicio B2C / ENTREGA PARTICULAR, código API 31/2).
 *
 * NOTA: el id interno del módulo sigue siendo "seur48" (clase, claves MODULE_SEUR48_*,
 * entrada en MODULE_SHIPPING_INSTALLED) para no romper la integración existente; lo que
 * el CLIENTE ve es "SEUR 24". Antes este módulo usaba el servicio SEUR 48 (15/130), que
 * SEUR capa a 20 kg (ship-methods maxExpeditionKg=20). Cambiado a B2C 31/2 (sin tope de
 * peso) por decisión del usuario (2026-06-23) para poder enviar cualquier peso.
 *
 * Tarifa B2C / Entrega Particular — España Peninsular (contrato 2026), coste sin IVA:
 *   1kg 4,20 · 2kg 4,26 · 3kg 4,46 · 4kg 4,78 · 5kg 5,22 · 7-10kg 5,66 · 15kg 6,50
 *   20kg 7,47 · 25kg 8,83 · 30kg 10,20 · 40kg 15,03 · 50kg 18,12 · +0,36 €/kg sobre 50.
 * MARGEN +10% (decisión usuario). Redondeo a 0,05 sobre el precio CON IVA. SIN tope de peso.
 *
 * SIN restricciones de stock ni de día/hora. Solo se ofrece, DE MOMENTO, a las zonas de
 * Torremolinos (Málaga, CP 29620) y La Línea de la Concepción (Cádiz, CP 11300).
 *
 * Fulfillment: el almacén expide estos pedidos con la agencia Vstock "SEUR 24" (TRA 4),
 * que el watcher manda SIN svc -> el endpoint usa el servicio por defecto 31/2 (B2C).
 * Ver memoria francobordo_seur_api.
 */
class seur48
{
    var $code, $title, $description, $icon, $enabled, $sort_order, $tax_class, $quotes, $_check;

    /* Margen sobre el coste SEUR (1.10 = +10%). */
    const MARGEN = 1.10;

    /* Zonas habilitadas (de momento): Torremolinos (29620), La Línea (11300). */
    public static function cpsPermitidos()
    {
        return array('29620', '11300');
    }

    public function __construct()
    {
        $this->code        = 'seur48';
        $this->title       = MODULE_SEUR48_TEXT_TITLE;
        $this->description = MODULE_SEUR48_TEXT_DESCRIPTION;
        $this->sort_order  = MODULE_SEUR48_SORT_ORDER;
        $this->icon        = DIR_WS_ICONS . 'seur.png';
        $this->tax_class   = MODULE_SEUR48_TAX_CLASS;
        $this->enabled     = (defined('MODULE_SEUR48_STATUS') && MODULE_SEUR48_STATUS == 'True');
    }

    /** Tarifa B2C / Entrega Particular, España Peninsular, sin IVA, por peso (kg). Sin tope. */
    public static function tarifaB2C($kg)
    {
        $kg = (float) $kg;
        if ($kg <= 0) $kg = 1;
        $kg = (int) ceil($kg);
        $tramos = array(1 => 4.20, 2 => 4.26, 3 => 4.46, 4 => 4.78, 5 => 5.22,
                        7 => 5.66, 10 => 5.66, 15 => 6.50, 20 => 7.47,
                        25 => 8.83, 30 => 10.20, 40 => 15.03, 50 => 18.12);
        foreach ($tramos as $max => $rate) {
            if ($kg <= $max) return $rate;
        }
        // > 50 kg: tarifa del tramo de 50 kg + 0,36 €/kg por cada kg que pase de 50.
        return 18.12 + ($kg - 50) * 0.36;
    }

    public function quote($method = '')
    {
        global $order, $cart, $shipping_weight;

        if (!$this->enabled) return array();

        // Destino: solo CPs habilitados (Torremolinos / La Línea), España.
        $iso = strtoupper((string) ($order->delivery['country']['iso_code_2'] ?? ''));
        $cp  = preg_replace('/\s+/', '', (string) $order->delivery['postcode']);
        if ($iso !== 'ES' || !in_array($cp, self::cpsPermitidos(), true)) {
            $this->enabled = false;
            return array();
        }

        // Precio: tarifa B2C + 10%; redondeo a 0,05 sobre el CON IVA. (B2C no tiene tope de peso.)
        $kg   = (float) (isset($shipping_weight) ? $shipping_weight : $cart->show_weight());
        $base = self::tarifaB2C($kg) * self::MARGEN;

        $iva = ($this->tax_class > 0)
            ? tep_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id'])
            : 0;
        $conIva      = $base * (1 + $iva / 100);
        $conIvaRound = round($conIva / 0.05) * 0.05;
        $cost = ($iva > 0) ? ($conIvaRound / (1 + $iva / 100)) : $conIvaRound;

        $this->quotes = array(
            'id'      => $this->code,
            'module'  => $this->title,
            'methods' => array(array(
                'id'    => $this->code,
                'title' => MODULE_SEUR48_TEXT_WAY,
                'cost'  => round($cost, 4),
            )),
        );
        if ($iva > 0) $this->quotes['tax'] = $iva;
        $this->quotes['icon'] = 'shipping_seur.png';

        return $this->quotes;
    }

    public function check()
    {
        if (!isset($this->_check)) {
            $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_SEUR48_STATUS'");
            $this->_check = tep_db_num_rows($check_query);
        }
        return $this->_check;
    }

    public function install()
    {
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Activar SEUR 24', 'MODULE_SEUR48_STATUS', 'False', '¿Ofrecer SEUR 24 (B2C, solo zonas habilitadas)?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Tipo de impuesto', 'MODULE_SEUR48_TAX_CLASS', '1', 'IVA aplicado al envío.', '6', '0', 'tep_get_tax_class_title', 'tep_cfg_pull_down_tax_classes(', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Orden', 'MODULE_SEUR48_SORT_ORDER', '11', 'Orden de aparición.', '6', '0', now())");
    }

    public function remove()
    {
        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key like 'MODULE\_SEUR48\_%'");
    }

    public function keys()
    {
        return array('MODULE_SEUR48_STATUS', 'MODULE_SEUR48_TAX_CLASS', 'MODULE_SEUR48_SORT_ORDER');
    }
}
