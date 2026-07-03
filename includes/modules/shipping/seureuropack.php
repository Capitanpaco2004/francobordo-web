<?php
/*

  $osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  SEUR EURO PACK módulo envío (SHIPPING MODULE)
  
  Copyright (c) 2004 David Zarco Fernández
  
  Developed for Extremadura Productos
  http://www.extremaduraproductos.com
  
  Released under the GNU General Public License
  
  
  USO:
  Por defecto, el módulo está preestablecido para soportar una Zona de Impuestos. El número
  de zonas pueden cambiarse facilmente editando la siguiente línea
  $this->numzones = numero_zonas
  en el constructor de la clase (función seurnacional)
  
  Nota: si el módulo está instalado y necesita aumentar el número de zonas, debe editar este fichero
  y cambiar la línea this->numzones, para que los cambios surjan efecto, debe desinstalar y volver
  a instalar el módulo desde la zona de administración. Atención, al desinstalar el módulo perderá todas
  las tarifas de envío y demás parámetros del módulo.
  
  Nota 2:El peso máximo para Envíos con este módulo es de 31 Kg
  
  Puede aplicar un Tipo de Impuesto para este módulo, asñí como el orden de visualización del módulo.
  
  Una vez que ha determinado el número de zonas de impuestos, y el orden de visualización, deberá seleccionar
  las zonas y configurar los parámetros para cada zona, que son los siguientes:
  
  - Zona X Tabla Envíos: Permite definir la tabla de Envíos Peso:Importe para la zona X. Ejemplo:
    Si utilizamos la tabla 3:13.46,10:15.55,15:17.64,20:21,31:24.54 significa que
	Envíos >=0 y <3 kg 13.46 Euros
	Envíos >=3 y <10 kg 15.55 Euros
	Envíos >=10 y <15 kg 17.64 Euros
	Envíos >=15 y <20 kg 21.20 Euros
	Envíos >=20 y <31 kg 24,54 Euros
	Envíos >=31 Kg ERROR Situación imposible. Seur europack no permite Envíos mayores a 31 Kg.
      
  - Zona X Handling Fee: permite definir el importe por la manipulación del paquete para
   los Envíos a la zona X

  
  El gasto Total del envío será calculado de la siguiente forma:
  Gastos envío = Gastos Tabla Envíos + Handling Fee
*/

class seureuropack
{
  var $code, $title, $description, $enabled, $num_zones, $sort_order, $icon, $tax_class, $_check, $quotes;

  const FUEL = 1.1654;  // sobrecoste fuel SEUR 16,54% repercutido al cliente (2026-07-02)

  // class constructor
  function __construct()
  {
    global $order;
    $this->code = 'seureuropack';
    $this->title = MODULE_EUROPACK_SEUR_TEXT_TITLE;
    $this->description = MODULE_EUROPACK_SEUR_TEXT_DESCRIPTION;
    $this->sort_order = MODULE_EUROPACK_SEUR_SORT_ORDER;
    $this->icon = 'seur.png';
    $this->icon = DIR_WS_ICONS . 'seur.png';
    $this->tax_class = MODULE_EUROPACK_SEUR_TAX_CLASS;
    $this->enabled = ((MODULE_EUROPACK_SEUR_STATUS == 'True') ? true : false);

    // CONFIGURE ESTE PARÁMETRO PARA ESTABLECER EL número DE ZONAS NECESARIAS
    $this->num_zones = 9;
    //INICIO
    $dest_zone = 0;
    $aux = '0';
    for ($i = 1; $i <= $this->num_zones; $i++) {
      $countries_table = constant('MODULE_EUROPACK_SEUR_COUNTRIES_' . $i);
      if (($this->enabled == true) && is_object($order) && ((int)constant('MODULE_EUROPACK_SEUR_COUNTRIES_' . $i) > 0)) {
        $check_query = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . $countries_table . "' and (zone_country_id = '" . $order->delivery['country']['id'] . "' or zone_country_id='0') order by zone_id");
        while ($check = tep_db_fetch_array($check_query)) {
          if (($check['zone_id'] < 1) || ($check['zone_id'] == $order->delivery['zone_id'])) {
            $dest_zone = $i;
            break;
          }
        }
      }
    }
    if ($dest_zone == '0') {
      $this->enabled = false;
    } else {
      $this->enabled = true;
    }
    //FIN
  }

  // class methods
  function quote($method = '')
  {
    global $order, $shipping_weight, $shipping_num_boxes;

    $dest_zone = 0;
    $error = false;
    $zones_weight_cost = 0;
    //si el peso del envío es menor o igual de 31 Kg intentar realizar el envío
    if ($shipping_weight < 50) {
      for ($i = 1; $i <= $this->num_zones; $i++) {
        $countries_table = constant('MODULE_EUROPACK_SEUR_COUNTRIES_' . $i);
        if (($this->enabled == true) && ((int)constant('MODULE_EUROPACK_SEUR_COUNTRIES_' . $i) > 0)) {
          $check_query = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . $countries_table . "' and (zone_country_id = '" . $order->delivery['country']['id'] . "' or zone_country_id='0') order by zone_id");
          while ($check = tep_db_fetch_array($check_query)) {
            if (($check['zone_id'] < 1) || ($check['zone_id'] == $order->delivery['zone_id'])) {
              $dest_zone = $i;
              break;
            }
          }
          if ($dest_zone > 0) {
            $shipping = -1;
            $zones_cost = constant('MODULE_EUROPACK_SEUR_COST_' . $dest_zone);
            $zones_cost_table = preg_split("/[:,]/", $zones_cost);
            $size = sizeof($zones_cost_table);
            /*
				 Forma 1:
				 Formato  cadena pesos:coste=> 0:13.46,3:15.55,10:17.64,15:21,20:24.54,31:30
				 	Envíos >=0 y <3 kg 13.46 Euros
					Envíos >=3 y <10 kg 15.55 Euros
					Envíos >=10 y <15 kg 17.64 Euros
					Envíos >=15 y <20 kg 21.20 Euros
					Envíos >=20 y <31 kg 24,54 Euros
					Envíos >=31 Kg ERROR Situación imposible. Seur europack no permite Envíos mayores a 31 Kg
				*/
            /* 
				for ($j=0; $j<$size; $j+=2) {				  
				  if (($shipping_weight >= $zones_cost_table[$j]) && ($shipping_weight < $zones_cost_table[$j+2]))
				  {
					$shipping = 1;					
					//obtener el precio de envío por kg para esa zona
					$zones_weight_cost = $zones_cost_table[$j+1];
					$shipping_method = MODULE_EUROPACK_SEUR_TEXT_WAY . ' ' . $order->delivery['country']['title'] . ' : ' . ($shipping_num_boxes > 1 ? $shipping_num_boxes . " x " : '') . $shipping_weight . ' ' . MODULE_EUROPACK_SEUR_TEXT_UNITS;
					break;
				  }
				}
				//Fin Forma1
				*/
            /*
				Forma 2:
				 Formato cadena pesos:coste=> 3:13.46,10:15.55,15:17.64,20:21,31:24.54
				 	Envíos >=0 y <3 kg 13.46 Euros
					Envíos >=3 y <10 kg 15.55 Euros
					Envíos >=10 y <15 kg 17.64 Euros
					Envíos >=15 y <20 kg 21.20 Euros
					Envíos >=20 y <31 kg 24,54 Euros
					Envíos >=31 Kg ERROR Situación imposible. Seur europack no permite Envíos mayores a 31 Kg							
				*/
            for ($j = 0; $j < $size; $j += 2) {
              if ($shipping_weight < $zones_cost_table[$j]) {
                $shipping = 1;
                //obtener el precio de envío por kg para esa zona
                $zones_weight_cost = $zones_cost_table[$j + 1];
                $shipping_method = MODULE_EUROPACK_SEUR_TEXT_WAY . ' ' . $order->delivery['country']['title'] . ' : ' . ($shipping_num_boxes > 1 ? $shipping_num_boxes . " x " : '') . $shipping_weight . ' ' . MODULE_EUROPACK_SEUR_TEXT_UNITS;
                break;
              }
            }
            //Fin Forma 2
            if ($shipping == -1) {
              $shipping_cost = 0;
              $shipping_method = MODULE_EUROPACK_SEUR_UNDEFINED_RATE;
            } else {
              $shipping_cost = $zones_weight_cost + constant('MODULE_EUROPACK_SEUR_HANDLING_' . $dest_zone);

              //Calculamos el kilo adicional
              if (intval(constant('MODULE_EUROPACK_KG_MAX_' . $dest_zone)) > 0 && $shipping_weight > intval(constant('MODULE_EUROPACK_KG_MAX_' . $dest_zone))) {
                $additional = round($shipping_weight - intval(constant('MODULE_EUROPACK_KG_MAX_' . $dest_zone)));
                $shipping_cost = $shipping_cost + ($additional * floatval(constant('MODULE_EUROPACK_KG_ADICIONAL_' . $dest_zone)));
              }

              // Repercutir el suplemento de combustible SEUR (16,54%) sobre el coste calculado (porte + handling + kg adicional).
              $shipping_cost = $shipping_cost * self::FUEL;

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
        'module' => MODULE_EUROPACK_SEUR_TEXT_TITLE,
        'methods' => array(
          array(
            'id' => $this->code,
            'title' => $shipping_method,
            'cost' => $shipping_cost
          )
        )
      );

      if ($this->tax_class > 0) {
        $this->quotes['tax'] = tep_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);
      }

      if (tep_not_null($this->icon)) $this->quotes['icon'] = tep_image($this->icon, $this->title);

      if ($error == true) $this->quotes['error'] = MODULE_EUROPACK_SEUR_INVALID_ZONE;
    } else //el peso es mayor de 31 Kg
    {
      $error = true;
      $this->quotes['module'] = MODULE_EUROPACK_SEUR_TEXT_TITLE;
      $this->quotes['error'] = MODULE_EUROPACK_SEUR_OVER_WEIGHT;
    }

    return $this->quotes;
  }

  function check()
  {
    if (!isset($this->_check)) {
      $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_EUROPACK_SEUR_STATUS'");
      $this->_check = tep_db_num_rows($check_query);
    }
    return $this->_check;
  }

  function install()
  {
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Activar SEUR �Pack', 'MODULE_EUROPACK_SEUR_STATUS', 'True', '&iquest;Quiere activar el m&oacute;dulo de env&iacute;os SEUR EuroPack?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Tipo Impuesto', 'MODULE_EUROPACK_SEUR_TAX_CLASS', '0', 'Utilizar el siguiente tipo de impuesto para aplicar al env&iacute;o..', '6', '0', 'tep_get_tax_class_title', 'tep_cfg_pull_down_tax_classes(', now())");
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Orden Visualizaci&oacute;n', 'MODULE_EUROPACK_SEUR_SORT_ORDER', '0', 'El menor se visualiza primero.', '6', '0', now())");
    for ($i = 1; $i <= $this->num_zones; $i++) {
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Zona " . $i . "', 'MODULE_EUROPACK_SEUR_COUNTRIES_" . $i . "', '0', 'Debe seleccionar una Zona de Impuestos para activar el m&eacute;todo de env&iacute;o sobre esta zona" . $i . ".', '6', '0', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Zona " . $i . " Tabla Env&iacute;os', 'MODULE_EUROPACK_SEUR_COST_" . $i . "', '3:13.46,10:15.55,15:17.64,20:21.09,31:24.54', 'Tarifas Env&iacute;o para la zona " . $i . ". Precios basados por grupos de peso. Ejemplo: 3:13.46,10:15.55,... Pedidos con Peso < 3 tienen 13.46 Euros de gastos de env&iacute;o. Pedidos con Peso >= 3 y < 10 tienen 15.55 euros de gastos de env&iacute; para la Zona " . $i . ".', '6', '0', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Zona " . $i . " Handling Fee', 'MODULE_EUROPACK_SEUR_HANDLING_" . $i . "', '0', 'Handling Fee para esta zona', '6', '0', now())");
      tep_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Zona " . $i . " - Incremento Kg.', 'MODULE_EUROPACK_KG_ADICIONAL_" . $i . "', '0', 'Precio por Kg. adicional, que empezará a ser efectivo configurando el máximo de peso.<br />Los decimales son con un punto (.).', '7', '0', now())");
      tep_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Zona " . $i . " - Peso máximo incremento', 'MODULE_EUROPACK_KG_MAX_" . $i . "', '0', 'Peso máximo el cual empezará a sumar por Kg. adicionales.<br>Dejar en 0 para deshabilitar la opción de kg. adicional.', '8', '0', now())");
    }
  }

  function remove()
  {
    tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
  }

  function keys()
  {
    $keys = array('MODULE_EUROPACK_SEUR_STATUS', 'MODULE_EUROPACK_SEUR_TAX_CLASS', 'MODULE_EUROPACK_SEUR_SORT_ORDER');

    for ($i = 1; $i <= $this->num_zones; $i++) {
      $keys[] = 'MODULE_EUROPACK_SEUR_COUNTRIES_' . $i;
      $keys[] = 'MODULE_EUROPACK_SEUR_COST_' . $i;
      $keys[] = 'MODULE_EUROPACK_SEUR_HANDLING_' . $i;
      $keys[] = 'MODULE_EUROPACK_KG_ADICIONAL_' . $i;
      $keys[] = 'MODULE_EUROPACK_KG_MAX_' . $i;
    }

    return $keys;
  }
}
