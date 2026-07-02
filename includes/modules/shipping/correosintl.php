<?php
/**
 * Envio internacional Correos - Paq Standard Internacional (S0410).
 * Addenda contrato 54002749-092, vigente 01-06-2026 a 31-12-2026.
 * Tarifas NETAS por pais (Alemania, Austria, Belgica, Suiza, Francia, Italia, Paises Bajos,
 * Reino Unido, EE.UU.) + zona EU2 de reserva para el resto de Europa. Sustituye al plano correosint.
 *   PVP (sin IVA) = neto x 1,106 (combustible) x 1,20 (margen).
 *   IVA POR PAIS DESTINO leido de la tabla OSS (tasa especifica del pais: DE 19, FR 20, IT 22, ES 21...).
 *   No-UE (Suiza, Reino Unido, EE.UU., etc.) = EXENTO (exportacion).
 *   OJO: tep_get_tax_rate esta roto en la tienda (suma una zona huerfana de 21% a todos) -> NO se usa;
 *   el IVA correcto se PLIEGA en el precio con tax_class=0 (total al cliente exacto). Cuando se arregle
 *   la zona huerfana, se puede pasar a tax_class=1 para itemizar el IVA del envio.
 *   Redondeo a 0,05 sobre el PVP con IVA. Peso volumetrico NO aplicado (solo peso real), como los demas.
 */
class correosintl {
    var $code, $title, $description, $icon, $enabled, $sort_order, $tax_class, $quotes, $_check;

    const MULT = 1.32720;   // 1,106 x 1,20

    const EU27 = array('AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE','IT',
                       'LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE');
    const ESPECIFICOS = array('DE','AT','BE','CH','FR','GB','IT','NL','US');
    const EUROPA = array('AL','AD','AM','BA','FO','GE','GI','IS','LI','MK','MD','MC','ME','NO','RS',
                         'SM','UA','VA','TR','BY','RU','XK','GL','BG','HR','CY','CZ','DK','EE','FI',
                         'GR','HU','IE','LV','LT','LU','MT','PL','PT','RO','SK','SI','SE');

    function __construct() {
        $this->code = 'correosintl';
        $this->title = MODULE_SHIPPING_CORREOSINTL_TEXT_TITLE;
        $this->description = MODULE_SHIPPING_CORREOSINTL_TEXT_DESCRIPTION;
        $this->sort_order = defined('MODULE_SHIPPING_CORREOSINTL_SORT_ORDER') ? MODULE_SHIPPING_CORREOSINTL_SORT_ORDER : 40;
        $this->tax_class = 0;
        $this->icon = DIR_WS_ICONS . 'shipping_correos.png';
        $this->enabled = (defined('MODULE_SHIPPING_CORREOSINTL_STATUS') && MODULE_SHIPPING_CORREOSINTL_STATUS == 'True');
    }

    private static function tarifas() {
        return array(
            'DE' => array(250=>7.70, 500=>8.04, 1000=>8.72, 1500=>9.37, 2000=>10.03, 3000=>11.28, 4000=>12.50, 5000=>13.68, 6000=>14.89, 7000=>16.05, 8000=>17.18, 9000=>18.28, 10000=>19.43, 11000=>20.59, 12000=>21.74, 13000=>22.89, 14000=>24.02, 15000=>25.16, 16000=>26.29, 17000=>27.61, 18000=>28.93, 19000=>30.25, 20000=>31.57, 21000=>32.89, 22000=>34.06, 23000=>35.23, 24000=>36.40, 25000=>37.57, 26000=>38.74, 27000=>39.98, 28000=>41.22, 29000=>42.45, 30000=>43.68),
            'AT' => array(250=>8.06, 500=>8.58, 1000=>9.60, 1500=>10.87, 2000=>11.87, 3000=>14.10, 4000=>16.32, 5000=>18.20, 6000=>20.83, 7000=>22.69, 8000=>24.51, 9000=>26.31, 10000=>28.15, 11000=>30.82, 12000=>32.67, 13000=>34.50, 14000=>36.33, 15000=>38.16, 16000=>39.98, 17000=>42.00, 18000=>44.01, 19000=>46.03, 20000=>48.04, 21000=>50.94, 22000=>52.80, 23000=>54.67, 24000=>56.53, 25000=>58.40, 26000=>60.26, 27000=>62.19, 28000=>64.12, 29000=>66.05, 30000=>67.98),
            'BE' => array(250=>9.23, 500=>9.60, 1000=>10.33, 1500=>11.19, 2000=>11.91, 3000=>13.41, 4000=>15.04, 5000=>16.34, 6000=>18.24, 7000=>19.52, 8000=>20.76, 9000=>21.98, 10000=>23.24, 11000=>26.84, 12000=>28.10, 13000=>29.36, 14000=>30.61, 15000=>31.86, 16000=>33.11, 17000=>34.55, 18000=>35.98, 19000=>37.42, 20000=>38.85, 21000=>42.88, 22000=>44.17, 23000=>45.46, 24000=>46.74, 25000=>48.03, 26000=>49.31, 27000=>50.67, 28000=>52.02, 29000=>53.37, 30000=>54.72),
            'CH' => array(250=>15.26, 500=>15.63, 1000=>16.34, 1500=>17.04, 2000=>17.74, 3000=>20.23, 4000=>21.54, 5000=>22.81, 6000=>24.10, 7000=>25.35, 8000=>26.56, 9000=>27.75, 10000=>28.98, 11000=>35.01, 12000=>36.24, 13000=>37.47, 14000=>38.69, 15000=>39.91, 16000=>41.13, 17000=>42.54, 18000=>43.94, 19000=>45.35, 20000=>46.75, 21000=>48.15, 22000=>49.41, 23000=>50.67, 24000=>51.92, 25000=>53.18, 26000=>54.43, 27000=>55.76, 28000=>57.08, 29000=>58.40, 30000=>59.72),
            'FR' => array(250=>11.75, 500=>11.96, 1000=>12.36, 1500=>12.88, 2000=>13.26, 3000=>14.68, 4000=>16.08, 5000=>16.72, 6000=>18.82, 7000=>19.43, 8000=>20.01, 9000=>20.56, 10000=>21.16, 11000=>23.22, 12000=>23.82, 13000=>24.42, 14000=>25.01, 15000=>25.59, 16000=>26.17, 17000=>26.95, 18000=>27.72, 19000=>28.49, 20000=>29.26, 21000=>31.47, 22000=>32.09, 23000=>32.72, 24000=>33.34, 25000=>33.96, 26000=>34.58, 27000=>35.27, 28000=>35.96, 29000=>36.64, 30000=>37.33),
            'GB' => array(250=>15.91, 500=>16.29, 1000=>17.04, 1500=>17.77, 2000=>18.51, 3000=>20.45, 4000=>21.82, 5000=>23.16, 6000=>24.51, 7000=>25.83, 8000=>27.11, 9000=>28.37, 10000=>29.66, 11000=>30.98, 12000=>32.28, 13000=>33.58, 14000=>34.87, 15000=>36.15, 16000=>37.44, 17000=>38.91, 18000=>40.38, 19000=>41.85, 20000=>43.32, 21000=>44.79, 22000=>46.12, 23000=>47.44, 24000=>48.76, 25000=>50.09, 26000=>51.41, 27000=>52.80, 28000=>54.19, 29000=>55.58, 30000=>56.96),
            'IT' => array(250=>11.23, 500=>11.69, 1000=>12.60, 1500=>13.80, 2000=>14.70, 3000=>16.63, 4000=>18.64, 5000=>20.30, 6000=>24.51, 7000=>26.16, 8000=>27.76, 9000=>29.34, 10000=>30.96, 11000=>33.23, 12000=>34.86, 13000=>36.48, 14000=>38.09, 15000=>39.70, 16000=>41.31, 17000=>43.11, 18000=>44.91, 19000=>46.70, 20000=>48.50, 21000=>51.17, 22000=>52.82, 23000=>54.47, 24000=>56.11, 25000=>57.76, 26000=>59.41, 27000=>61.12, 28000=>62.84, 29000=>64.55, 30000=>66.26),
            'NL' => array(250=>9.18, 500=>9.59, 1000=>10.39, 1500=>11.18, 2000=>11.96, 3000=>13.85, 4000=>15.33, 5000=>16.78, 6000=>18.24, 7000=>19.67, 8000=>21.05, 9000=>22.41, 10000=>23.82, 11000=>35.73, 12000=>37.14, 13000=>38.55, 14000=>39.94, 15000=>41.34, 16000=>42.73, 17000=>44.31, 18000=>45.89, 19000=>47.47, 20000=>49.05, 21000=>50.63, 22000=>52.06, 23000=>53.49, 24000=>54.92, 25000=>56.35, 26000=>57.78, 27000=>59.28, 28000=>60.78, 29000=>62.27, 30000=>63.76),
            'US' => array(250=>13.46, 500=>15.11, 1000=>18.40, 1500=>21.67, 2000=>24.94, 3000=>31.41, 4000=>37.86, 5000=>44.27, 6000=>50.70, 7000=>57.09, 8000=>63.44, 9000=>69.77, 10000=>76.14, 11000=>82.53, 12000=>88.91, 13000=>95.28, 14000=>101.64, 15000=>108.00, 16000=>114.36, 17000=>120.91, 18000=>127.45, 19000=>134.00, 20000=>140.54, 21000=>147.08, 22000=>153.48, 23000=>159.88, 24000=>166.27, 25000=>172.67, 26000=>179.06, 27000=>185.53, 28000=>191.99, 29000=>198.45, 30000=>204.91),
            'EU2' => array(250=>27.35, 500=>28.05, 1000=>28.72, 1500=>30.81, 2000=>32.18, 3000=>36.20, 4000=>38.86, 5000=>41.47, 6000=>46.07, 7000=>51.22, 8000=>54.97, 9000=>58.72, 10000=>62.47, 11000=>66.22, 12000=>69.97, 13000=>73.72, 14000=>77.47, 15000=>81.22, 16000=>84.97, 17000=>88.72, 18000=>92.47, 19000=>96.22, 20000=>99.97, 21000=>103.72, 22000=>107.47, 23000=>111.22, 24000=>114.97, 25000=>118.72, 26000=>122.47, 27000=>126.22, 28000=>129.97, 29000=>133.72, 30000=>137.47),
        );
    }

    private static function tablaPara($iso) {
        if (in_array($iso, self::ESPECIFICOS, true)) return $iso;
        if (in_array($iso, self::EUROPA, true)) return 'EU2';
        return null;
    }

    private static function netoPorPeso($tabla, $g) {
        $t = self::tarifas();
        if (!isset($t[$tabla])) return null;
        $ult = null;
        foreach ($t[$tabla] as $max => $precio) { $ult = $precio; if ($g <= $max) return $precio; }
        return $ult;
    }

    private static function ivaPais($iso, $country_id) {
        if (!in_array($iso, self::EU27, true)) return 0.0;
        $q = tep_db_query("SELECT MAX(tr.tax_rate) AS rate FROM " . TABLE_TAX_RATES . " tr
                           JOIN " . TABLE_ZONES_TO_GEO_ZONES . " za ON tr.tax_zone_id = za.geo_zone_id
                           WHERE tr.tax_class_id = 1 AND za.zone_country_id = '" . (int)$country_id . "'");
        $r = tep_db_fetch_array($q);
        return ($r && $r['rate'] !== null) ? (float)$r['rate'] : 0.0;
    }

    function quote($method = '') {
        global $order, $cart, $shipping_weight;
        if (!$this->enabled) return array();
        $iso = strtoupper((string)($order->delivery['country']['iso_code_2'] ?? ''));
        $tabla = self::tablaPara($iso);
        if ($tabla === null) return array();

        $kg = (float)(isset($shipping_weight) ? $shipping_weight : $cart->show_weight());
        if ($kg <= 0) $kg = 1;
        $g = max(1, (int)round($kg * 1000));
        $net = self::netoPorPeso($tabla, $g);
        if ($net === null) return array();

        $base = $net * self::MULT;
        $iva  = self::ivaPais($iso, (int)($order->delivery['country']['id'] ?? 0));
        $conIva = $base * (1 + $iva / 100);
        $cost = round($conIva / 0.05) * 0.05;

        $this->quotes = array(
            'id' => $this->code,
            'module' => $this->title,
            'methods' => array(array('id' => $this->code, 'title' => MODULE_SHIPPING_CORREOSINTL_TEXT_WAY, 'cost' => $cost)),
            'icon' => 'shipping_correos.png',
        );
        return $this->quotes;
    }

    function check() {
        if (!isset($this->_check)) {
            $q = tep_db_query("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_SHIPPING_CORREOSINTL_STATUS'");
            $this->_check = tep_db_num_rows($q);
        }
        return $this->_check;
    }
    function install() {
        tep_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Activar Correos Internacional (por pais)', 'MODULE_SHIPPING_CORREOSINTL_STATUS', 'False', 'Ofrecer este metodo de envio?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
        tep_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Prioridad', 'MODULE_SHIPPING_CORREOSINTL_SORT_ORDER', '40', 'Orden de aparicion.', '6', '0', now())");
    }
    function remove() { tep_db_query("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key IN ('" . implode("', '", $this->keys()) . "')"); }
    function keys() { return array('MODULE_SHIPPING_CORREOSINTL_STATUS', 'MODULE_SHIPPING_CORREOSINTL_SORT_ORDER'); }
}
