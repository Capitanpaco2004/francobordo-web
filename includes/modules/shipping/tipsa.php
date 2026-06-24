<?php
class tipsa
{
    public $code, $title, $description, $enabled, $num_zones, $sort_order, $icon, $tax_class, $_check, $quotes;

    // class constructor
    public function __construct()
    {
        $this->code = 'tipsa';
        $this->title = MODULE_TIPSA_TEXT_TITLE;
        $this->description = MODULE_TIPSA_TEXT_DESCRIPTION;
        $this->sort_order = MODULE_TIPSA_SORT_ORDER;
        $this->icon = DIR_WS_ICONS . 'gls.png';
        $this->tax_class = MODULE_TIPSA_TAX_CLASS;
        $this->enabled = ((MODULE_TIPSA_STATUS == 'True') ? true : false);

        // CONFIGURE ESTE PARÁMETRO PARA ESTABLECER EL NÚMERO DE ZONAS NECESARIAS
        $this->num_zones = 9;
    }

    // class methods
    public function quote($method = '')
    {
        global $order, $shipping_weight, $shipping_num_boxes;

        // SEUR 24 (La Linea 11300 / Torremolinos 29620) tiene prioridad: si SEUR 24 (modulo
        // seur48, ahora B2C 31/2, SIN tope de peso) cubre este destino, NO mostrar Mensajeria (CEX) ahi.
        if (defined('MODULE_SEUR48_STATUS') && MODULE_SEUR48_STATUS == 'True') {
            $cp48  = preg_replace('/\s+/', '', (string) ($order->delivery['postcode'] ?? ''));
            $iso48 = strtoupper((string) ($order->delivery['country']['iso_code_2'] ?? ''));
            if ($iso48 === 'ES' && in_array($cp48, array('29620', '11300'), true)) {
                return array();
            }
        }

        $dest_zone = 0;
        $error = false;
        $zones_weight_cost = 0;
        //si el peso del envío es menor o igual de 31 Kg intentar realizar el envío
        if ($shipping_weight < 500) {

            for ($i = 1; $i <= $this->num_zones; $i++) {
                $countries_table = constant('MODULE_TIPSA_COUNTRIES_' . $i);
                if (($this->enabled == true) && ((int) constant('MODULE_TIPSA_COUNTRIES_' . $i) > 0)) {
                    $check_query = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . $countries_table . "' and (zone_country_id = '" . $order->delivery['country']['id'] . "' or zone_country_id='0') order by zone_id");
                    while ($check = tep_db_fetch_array($check_query)) {
                        if (($check['zone_id'] < 1) || ($check['zone_id'] == $order->delivery['zone_id'])) {
                            $dest_zone = $i;
                            break;
                        }
                    }
                    if ($dest_zone > 0) {

                        $shipping = -1;
                        $zones_cost = constant('MODULE_TIPSA_COST_' . $dest_zone);
                        $zones_cost_table = preg_split("/[:,]/", $zones_cost);
                        $size = sizeof($zones_cost_table);
                        for ($j = 0; $j < $size; $j += 2) {
                            if ($shipping_weight < $zones_cost_table[$j]) {

                                $shipping = 1;
                                //obtener el precio de envío por kg para esa zona
                                $zones_weight_cost = $zones_cost_table[$j + 1];
                                $shipping_method = MODULE_TIPSA_TEXT_WAY . ' ' . $order->delivery['country']['title'];
                                break;
                            }
                        }
						//Si no esta dentro del rango es que hemos superado el ultimo valor, lo obtenemos y lo usamos como precio, mas adelante se calculara el extra
						if($shipping == -1){
							$zones_weight_cost = end($zones_cost_table);
							$shipping_method = MODULE_TIPSA_TEXT_WAY . ' ' . $order->delivery['country']['title'];
							$shipping = 1;
						}


                        /* Modulo Original

	                    $shipping_method = MODULE_TIPSA_TEXT_WAY . ' ' . $order->delivery['country']['title'] . ' : ' . ($shipping_num_boxes > 1 ? $shipping_num_boxes . " x " : '') . $shipping_weight . ' ' . MODULE_TIPSA_TEXT_UNITS;
	                    */

                        //Fin Forma 2
                        if ($shipping == -1) {
                            $shipping_cost = 0;
                            $shipping_method = MODULE_TIPSA_UNDEFINED_RATE;
                        } else {
                            $shipping_cost = $zones_weight_cost + (float)constant('MODULE_TIPSA_HANDLING_' . $dest_zone);

                            //Calculamos el kilo adicional
                            if (intval(constant('MODULE_TIPSA_KG_MAX_' . $dest_zone)) > 0 && $shipping_weight > intval(constant('MODULE_TIPSA_KG_MAX_' . $dest_zone))) {
                                $additional = round($shipping_weight - intval(constant('MODULE_TIPSA_KG_MAX_' . $dest_zone)));
                                $shipping_cost = $shipping_cost + ($additional * floatval(constant('MODULE_TIPSA_KG_ADICIONAL_' . $dest_zone)));
				$shipping_method = MODULE_TIPSA_TEXT_WAY . ' ' . $order->delivery['country']['title'];
                            }
                            break;
                        }
                    }
                }
            }

            if ($dest_zone == 0) {
                $error = true;
            }

            $this->quotes = array(
                'id' => $this->code,
                'module' => MODULE_TIPSA_TEXT_TITLE,
                'methods' => array(
                    array(
                        'id' => $this->code,
                        'title' => $shipping_method,
                        'cost' => $shipping_cost,
                    ),
                ),
            );

            if ($this->tax_class > 0) {
                $this->quotes['tax'] = tep_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);
            }

            if (tep_not_null($this->icon)) {
                $this->quotes['icon'] = 'shipping_furgoneta.png';
            }

            if ($error == true) {
                $this->quotes['error'] = MODULE_TIPSA_INVALID_ZONE;
            }
        } else { //el peso es mayor de 31 Kg
            $error = true;
            $this->quotes['module'] = MODULE_TIPSA_TEXT_TITLE;
            $this->quotes['error'] = MODULE_TIPSA_OVER_WEIGHT;
        }

        return $this->quotes;
    }

    public function check()
    {
        if (!isset($this->_check)) {
            $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_TIPSA_STATUS'");
            $this->_check = tep_db_num_rows($check_query);
        }
        return $this->_check;
    }

    public function install()
    {
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Activar TIPSA �Pack', 'MODULE_TIPSA_STATUS', 'True', '&iquest;Quiere activar el m&oacute;dulo de env&iacute;os TIPSA EuroPack?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Tipo Impuesto', 'MODULE_TIPSA_TAX_CLASS', '0', 'Utilizar el siguiente tipo de impuesto para aplicar al env&iacute;o..', '6', '0', 'tep_get_tax_class_title', 'tep_cfg_pull_down_tax_classes(', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Orden Visualizaci&oacute;n', 'MODULE_TIPSA_SORT_ORDER', '0', 'El menor se visualiza primero.', '6', '0', now())");
        for ($i = 1; $i <= $this->num_zones; $i++) {
            tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Zona " . $i . "', 'MODULE_TIPSA_COUNTRIES_" . $i . "', '0', 'Debe seleccionar una Zona de Impuestos para activar el m&eacute;todo de env&iacute;o sobre esta zona" . $i . ".', '6', '0', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
            tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Zona " . $i . " Tabla Env&iacute;os', 'MODULE_TIPSA_COST_" . $i . "', '3:13.46,10:15.55,15:17.64,20:21.09,31:24.54', 'Tarifas Env&iacute;o para la zona " . $i . ". Precios basados por grupos de peso. Ejemplo: 3:13.46,10:15.55,... Pedidos con Peso < 3 tienen 13.46 Euros de gastos de env&iacute;o. Pedidos con Peso >= 3 y < 10 tienen 15.55 euros de gastos de env&iacute; para la Zona " . $i . ".', '6', '0', now())");
            tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Zona " . $i . " Handling Fee', 'MODULE_TIPSA_HANDLING_" . $i . "', '0', 'Handling Fee para esta zona', '6', '0', now())");
            tep_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Zona " . $i . " - Incremento Kg.', 'MODULE_TIPSA_KG_ADICIONAL_" . $i . "', '0', 'Precio por Kg. adicional, que empezará a ser efectivo configurando el máximo de peso.<br />Los decimales son con un punto (.).', '7', '0', now())");
            tep_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Zona " . $i . " - Peso máximo incremento', 'MODULE_TIPSA_KG_MAX_" . $i . "', '0', 'Peso máximo el cual empezará a sumar por Kg. adicionales.<br>Dejar en 0 para deshabilitar la opción de kg. adicional.', '8', '0', now())");
        }
    }

    public function remove()
    {
        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    public function keys()
    {
        $keys = array('MODULE_TIPSA_STATUS', 'MODULE_TIPSA_TAX_CLASS', 'MODULE_TIPSA_SORT_ORDER');

        for ($i = 1; $i <= $this->num_zones; $i++) {
            $keys[] = 'MODULE_TIPSA_COUNTRIES_' . $i;
            $keys[] = 'MODULE_TIPSA_COST_' . $i;
            $keys[] = 'MODULE_TIPSA_HANDLING_' . $i;
            $keys[] = 'MODULE_TIPSA_KG_ADICIONAL_' . $i;
            $keys[] = 'MODULE_TIPSA_KG_MAX_' . $i;
        }

        return $keys;
    }
}
