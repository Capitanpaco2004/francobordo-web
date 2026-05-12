<?php
/*
  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  SEUR NACIONAL MÓDULO ENVÍO (SHIPPING MODULE)
  
  Copyright (c) 2004 David Zarco Fernández
  
  Developed for Extremadura Productos
  http://www.extremaduraproductos.com
  
  Released under the GNU General Public License

  USO:
  Por defecto, el módulo está preestablecido para soportar una Zona de Impuestos. El número
  de zonas pueden cambiarse fácilmente editando la siguiente línea
  $this->numzones = numero_zonas
  en el constructor de la clase (función seurnacional)
  
  Nota: si el módulo está instalado y necesita aumentar el número de zonas, debe editar este fichero
  y cambiar la línea this->numzones, para que los cambios surjan efecto, debe desinstalar y volver
  a instalar el módulo desde la zona de administración. Atención, al desinstalar el módulo perderá todas
  las tarifas de envío y demás parámetros del módulo.
  
  Puede aplicar un Tipo de Impuesto para este módulo, así como el orden de visualización del módulo.
  
  Una vez que ha determinado el número de zonas de impuestos, y el orden de visualización, deberá seleccionar
  las zonas y configurar los parámetros para cada zona, que son los siguientes:
  
  - Zona X Gastos fijos por expedición: Indica el precio en euros de los gastos fijos por expedición que
  se aplicarán al envío.
  
  - Zona X Gastos Precio Envío 1 Kg: es el precio que cuesta enviar un kg a la zona X. El cálculo de estos
  gastos será el resultado de multiplicar el precio del kilo por la cantidad de kilos a enviar
      
  - Zona X Handling Fee: Gastos por Cuota de Manipulación del paquete, sería lo que viene a ser
  los Gastos fijos por expedición, por tanto, no lo vamos a incluir en el módulo
  
  El gasto Total del envío será calculado de la siguiente forma:
  Gastos Envío = Gastos Fijos Expedición + (Precio Kg * Número Kilos)

*/

class seurnacional
{
    var $code, $title, $description, $enabled, $num_zones, $sort_order, $icon, $tax_class, $check, $quotes;

    // class constructor
    function __construct()
    {

        $this->code = 'seurnacional';
        $this->title = MODULE_SEUR_NACIONAL_TEXT_TITLE;
        $this->description = MODULE_SEUR_NACIONAL_TEXT_DESCRIPTION;
        $this->sort_order = MODULE_SEUR_NACIONAL_SORT_ORDER;
        $this->icon = 'seur.png';
        $this->icon = DIR_WS_ICONS . 'seur.png';
        $this->tax_class = MODULE_SEUR_NACIONAL_TAX_CLASS;
        $this->enabled = ((MODULE_SEUR_NACIONAL_STATUS == 'True') ? true : false);

        // CUSTOMIZE THIS SETTING FOR THE NUMBER OF ZONES NEEDED
        $this->num_zones = 6;
    }

    // class methods
    public function quote($method = '')
    {
        global $order, $shipping_weight, $shipping_num_boxes;

        $dest_zone = 0;
        $error = false;
        $shipping_method = '';
        $shipping_cost = 0;

        // Recorremos los productos del carrito
        for ($nCont = 0, $nQty = sizeof($order->products); $nCont < $nQty; $nCont++) {
            $aProduct = $order->products[$nCont];

            // Si no tenemos el valor de products_quantity
            if (!isset($aProduct['products_quantity'])) {
                // Obtenemos el ID del producto
                $nID = (isset($aProduct['products_id']) ? $aProduct['products_id'] : $aProduct['id']);
                $nID = (preg_match('/(\{)/i', $nID) ? preg_replace('/(\{)(.*)/i', '', $nID) : $nID);

                // Obtenemos la cantidad del producto
                $aAux = tep_db_query('SELECT products_quantity FROM ' . TABLE_PRODUCTS . ' WHERE products_id = "' . $nID . '";');
                $aAux = tep_db_fetch_array($aAux);
                $aProduct['products_quantity'] = $aAux['products_quantity'];
            }

            // Entre 8 y 13 días
            if ($aProduct['products_quantity'] <= 0 && $aProduct['products_quantity'] >= -799) {
                $this->enabled = false;
            }

            // Bajo pedido
            else if ($aProduct['products_quantity'] <= -800 && $aProduct['products_quantity'] >= -899) {
                $this->enabled = false;
            }

            // Agotado
            else if ($aProduct['products_quantity'] <= -900 && $aProduct['products_quantity'] >= -901) {
                $this->enabled = false;
            }
        }

        for ($i = 1; $i <= $this->num_zones; $i++) {
            $geo_zone_id = constant('MODULE_SEUR_NACIONAL_COUNTRIES_' . $i);
            if (($this->enabled == true) && ((int) constant('MODULE_SEUR_NACIONAL_COUNTRIES_' . $i) > 0)) {
                $query = "select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where (geo_zone_id = '" . $geo_zone_id . "') and (zone_country_id = '" . $order->delivery['country']['id'] . "' or zone_country_id='0') order by zone_id";
                $check_query = tep_db_query($query);
                while ($check = tep_db_fetch_array($check_query)) {
                    if (($check['zone_id'] < 1) || ($check['zone_id'] == $order->delivery['zone_id'])) {
                        $dest_zone = $i;
                        break;
                    }
                }
                if ($dest_zone > 0) {
                    $shipping = -1;
                    //obtener el gasto de expedición fijo para esa zona
                    $zones_expedition_cost = constant('MODULE_SEUR_NACIONAL_EXPEDITION_COST_' . $dest_zone);
                    //obtener el precio de envío por kg para esa zona
                    $zones_weight_cost = constant('MODULE_SEUR_NACIONAL_WEIGHT_COST_' . $dest_zone);
                    $check_geoquery = tep_db_query("select geo_zone_name from " . TABLE_GEO_ZONES . " where geo_zone_id=" . $geo_zone_id);
                    $check_georow = tep_db_fetch_array($check_geoquery);
                    $geo_zone_name = $check_georow['geo_zone_name'];

                    /**
                     * Comentado a petición de Marta para quitar el peso.
                     * También se ha editado el idioma.
                     * #THB-416-38558
                     * 18:55- 12/06/2019
                     * @author Daniel Lucia <daniel.lucia@denox.es>
                     */
                    //$shipping_method = MODULE_SEUR_NACIONAL_TEXT_WAY . ' ' . $geo_zone_name . ' un pedido de ' . $shipping_weight . ' ' . MODULE_SEUR_NACIONAL_TEXT_UNITS;
                    $shipping_method = MODULE_SEUR_NACIONAL_TEXT_WAY;
                    if ((!is_null($zones_weight_cost)) and (is_numeric($zones_weight_cost))) {
                        $shipping = 1;
                    }
                    if ($shipping == -1) {
                        $shipping_cost = 0;
                        $shipping_method = MODULE_SEUR_NACIONAL_UNDEFINED_RATE;
                    } else {
                        //redondear el peso por encima
                        $shipping_weight = ceil($shipping_weight);
                        $shipping_cost = ($zones_weight_cost * $shipping_weight) + $zones_expedition_cost;

                        //Calculamos el kilo adicional
                        if (intval(constant('MODULE_SEUR_NACIONAL_KG_MAX_' . $dest_zone)) > 0 && $shipping_weight > intval(constant('MODULE_SEUR_NACIONAL_KG_MAX_' . $dest_zone))) {
                            $additional = round($shipping_weight - intval(constant('MODULE_SEUR_NACIONAL_KG_MAX_' . $dest_zone)));
                            $shipping_cost = $shipping_cost + ($additional * floatval(constant('MODULE_SEUR_NACIONAL_KG_ADICIONAL_' . $dest_zone)));
                        }

                        //Añadir el Handling Fee
                        //$shipping_cost = ($zones_weight_cost * $shipping_weight) + $zones_expedition_cost + constant('MODULE_SEUR_NACIONAL_HANDLING_' . $dest_zone);
                        break;
                    }
                }
            } //for
        } //if this->enabled


        if ($dest_zone == 0) {
            $error = true;
        }

        $this->quotes = array(
            'id' => $this->code,
            'module' => MODULE_SEUR_NACIONAL_TEXT_TITLE,
            'methods' => array(
                array(
                    'id' => $this->code,
                    'title' => $shipping_method,
                    'cost' => $shipping_cost
                )
            )
        );

        //si impuestos, calcularlos
        if ($this->tax_class > 0) {
            $this->quotes['tax'] = tep_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);
        }

        if (tep_not_null($this->icon)) {
            $this->quotes['icon'] = tep_image($this->icon, $this->title);
        }

        if ($error == true) {
            $this->quotes['error'] = MODULE_SEUR_NACIONAL_INVALID_ZONE;
        }

        return $this->quotes;
    }

    public function check()
    {

        if (!isset($this->check)) {
            $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_SEUR_NACIONAL_STATUS'");
            $this->check = tep_db_num_rows($check_query);
        }
        return $this->check;
    }

    public function install()
    {
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Activar M&eacute;todo Env&iacute;o SEUR Espa&ntilde;a y Portugal', 'MODULE_SEUR_NACIONAL_STATUS', 'True', '�Quiere activar este m&eacute;todo de env&iacute;o?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Tipo Impuesto', 'MODULE_SEUR_NACIONAL_TAX_CLASS', '0', 'Utilizar el siguiente tipo de impuesto para aplicar al env&iacute;o.', '6', '0', 'tep_get_tax_class_title', 'tep_cfg_pull_down_tax_classes(', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Orden Visualizaci&oacute;n', 'MODULE_SEUR_NACIONAL_SORT_ORDER', '0', 'El menor se visualiza primero.', '6', '0', now())");
        for ($i = 1; $i <= $this->num_zones; $i++) {
            tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Zona " . $i . "', 'MODULE_SEUR_NACIONAL_COUNTRIES_" . $i . "', '0', 'Debe seleccionar una Zona de Impuestos para activar el m&eacute;todo de env&iacute;o sobre esta zona" . $i . ".', '6', '0', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
            tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Zona " . $i . " Gastos Fijos Expedici&oacute;n', 'MODULE_SEUR_NACIONAL_EXPEDITION_COST_" . $i . "', '0', 'Precio expedici&ocute;n env&iacute;o a la zona " . $i . "Coste Fijo por expedici&oacute;n de env&iacute;o a la zona.<br>0 significa que se suman 5 � a los gastos de env&iacute;o a esa zona.<br>2.75 significa que se a&ntilde;aden 2.75 �', '6', '0', now())");
            tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Zona " . $i . " Precio Env&iacute;o 1 Kg', 'MODULE_SEUR_NACIONAL_WEIGHT_COST_" . $i . "', '0', 'Precio env&iacute;o de un Kg a la zona " . $i . ". Ejemplos:<br>0 significa que enviar un Kg a la zona cuesta 0 �(env&iacute;o gratu&iacute;to).<br>1 significa que enviar un Kg a la zona cuesta 1 �.<br>1.50 significa que enviar 2 Kg a la zona cuesta 3 � (2x1.50).', '6', '0', now())");
            //El Handling Fee, es como los Gastos de expedición, por eso lo desactualizamos
            //tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Zona " . $i ." Handling Fee', 'MODULE_SEUR_NACIONAL_HANDLING_" . $i."', '0', 'Handling Fee para esta Zona', '6', '0', now())");
            tep_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Zona " . $i . " - Incremento Kg.', 'MODULE_SEUR_NACIONAL_KG_ADICIONAL_" . $i . "', '0', 'Precio por Kg. adicional, que empezará a ser efectivo configurando el máximo de peso.<br />Los decimales son con un punto (.).', '7', '0', now())");
            tep_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Zona " . $i . " - Peso máximo incremento', 'MODULE_SEUR_NACIONAL_KG_MAX_" . $i . "', '0', 'Peso máximo el cual empezará a sumar por Kg. adicionales.<br>Dejar en 0 para deshabilitar la opción de kg. adicional.', '8', '0', now())");
        }
    }

    public function remove()
    {
        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    public function keys()
    {
        $keys = array('MODULE_SEUR_NACIONAL_STATUS', 'MODULE_SEUR_NACIONAL_TAX_CLASS', 'MODULE_SEUR_NACIONAL_SORT_ORDER');

        for ($i = 1; $i <= $this->num_zones; $i++) {
            $keys[] = 'MODULE_SEUR_NACIONAL_COUNTRIES_' . $i;
            $keys[] = 'MODULE_SEUR_NACIONAL_EXPEDITION_COST_' . $i;
            $keys[] = 'MODULE_SEUR_NACIONAL_WEIGHT_COST_' . $i;
            //$keys[] = 'MODULE_SEUR_NACIONAL_HANDLING_' . $i;
            $keys[] = 'MODULE_SEUR_NACIONAL_KG_ADICIONAL_' . $i;
            $keys[] = 'MODULE_SEUR_NACIONAL_KG_MAX_' . $i;
        }

        return $keys;
    }
}
