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
 * SIN restricciones de stock ni de día/hora. Se ofrece en TODA España (península + Baleares +
 * Canarias + Ceuta/Melilla; la tarifa la marca la zona del CP). Campo admin
 * MODULE_SEUR48_CP_RESTRINGIDOS = CPs (separados por comas, admite prefijos) donde NO ofrecerlo.
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
    const FUEL = 1.1654;  // sobrecoste fuel SEUR 16,54% repercutido al cliente (2026-06-25)

    /** Codigos postales restringidos (campo admin MODULE_SEUR48_CP_RESTRINGIDOS, separados
     *  por comas). Admite prefijos: '29' restringe la provincia 29; '29620' solo ese CP. */
    public static function cpRestringido($cp)
    {
        if (!defined('MODULE_SEUR48_CP_RESTRINGIDOS')) return false;
        $cp = preg_replace('/[^0-9]/', '', (string) $cp);
        if ($cp === '') return false;
        foreach (explode(',', (string) MODULE_SEUR48_CP_RESTRINGIDOS) as $r) {
            $r = preg_replace('/[^0-9]/', '', $r);
            if ($r !== '' && strncmp($cp, $r, strlen($r)) === 0) return true;
        }
        return false;
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

    /** Tarifa B2C / Entrega Particular, sin IVA, por peso (kg) y ZONA del CP:
     *  'PEN' peninsula (tarifa anterior), 'BAL' Baleares, 'CAN' Canarias, 'CEU' Ceuta/Melilla
     *  (islas = columnas del contrato autorizado 2026). Sin tope de peso. */
    public static function tarifaB2C($kg, $zona = 'PEN')
    {
        $kg = (float) $kg;
        if ($kg <= 0) $kg = 1;
        $kg = (int) ceil($kg);
        // zona => array(tramos 'hasta N kg' => precio, peso del ultimo tramo, €/kg extra sobre el)
        $zonas = array(
            'PEN' => array(array(1=>4.20,2=>4.26,3=>4.46,4=>4.78,5=>5.22,7=>5.66,10=>5.66,15=>6.50,20=>7.47,25=>8.83,30=>10.20,40=>15.03,50=>18.12), 50, 0.36),
            'BAL' => array(array(1=>6.13,2=>7.16,3=>7.99,4=>8.58,5=>9.28,6=>10.24,7=>11.19,8=>12.26,9=>13.41,10=>14.53,15=>18.47,20=>23.06,25=>28.62,30=>34.77), 30, 0.74),
            'CAN' => array(array(1=>9.83,2=>13.51,3=>16.82,4=>19.99,5=>23.17,6=>26.78,7=>31.25,8=>36.43,9=>42.75,10=>50.92,15=>64.62,20=>89.06,25=>114.94,30=>148.64), 30, 3.48),
            'CEU' => array(array(1=>11.14,2=>12.99,3=>14.36,4=>15.62,5=>16.86,6=>20.27,7=>23.43,8=>24.06,9=>25.20,10=>26.60,15=>33.38,20=>43.00,25=>49.58,30=>62.74), 30, 1.36),
        );
        if (!isset($zonas[$zona])) $zona = 'PEN';
        list($tramos, $tope, $extra) = $zonas[$zona];
        foreach ($tramos as $max => $rate) {
            if ($kg <= $max) return $rate;
        }
        // por encima del ultimo tramo: precio del ultimo + €/kg extra por cada kg que lo pase.
        return end($tramos) + ($kg - $tope) * $extra;
    }

    public function quote($method = '')
    {
        global $order, $cart, $shipping_weight;

        if (!$this->enabled) return array();

        // Destino: toda Espana (ES). La zona/tarifa la marca el CP (Baleares 07, Canarias
        // 35/38, Ceuta 51, Melilla 52, resto peninsula). Mas los CPs del campo admin restringidos.
        $iso = strtoupper((string) ($order->delivery['country']['iso_code_2'] ?? ''));
        $cp  = preg_replace('/\s+/', '', (string) $order->delivery['postcode']);
        if ($iso !== 'ES' || self::cpRestringido($cp)) {
            $this->enabled = false;
            return array();
        }

        // Precio: tarifa B2C de la zona del CP + 10%; redondeo a 0,05 sobre el CON IVA.
        $kg   = (float) (isset($shipping_weight) ? $shipping_weight : $cart->show_weight());
        $zona = (strncmp($cp, '07', 2) === 0) ? 'BAL'
              : ((strncmp($cp, '35', 2) === 0 || strncmp($cp, '38', 2) === 0) ? 'CAN'
              : ((strncmp($cp, '51', 2) === 0 || strncmp($cp, '52', 2) === 0) ? 'CEU' : 'PEN'));
        $base = self::tarifaB2C($kg, $zona) * self::MARGEN * self::FUEL;

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
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('CPs restringidos', 'MODULE_SEUR48_CP_RESTRINGIDOS', '', 'Codigos postales separados por comas donde NO ofrecer SEUR 24. Admite prefijos: 29 = provincia, 29620 = ese CP.', '6', '0', now())");
    }

    public function remove()
    {
        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key like 'MODULE\_SEUR48\_%'");
    }

    public function keys()
    {
        return array('MODULE_SEUR48_STATUS', 'MODULE_SEUR48_TAX_CLASS', 'MODULE_SEUR48_SORT_ORDER', 'MODULE_SEUR48_CP_RESTRINGIDOS');
    }
}
