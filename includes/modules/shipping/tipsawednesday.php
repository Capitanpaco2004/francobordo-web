<?php
  class tipsawednesday {
    var $code, $title, $description, $enabled, $num_zones, $sort_order, $icon, $tax_class, $_check, $quotes;

// class constructor
    function __construct() {
      $this->code = 'tipsawednesday';
      $this->title = MODULE_TIPSA_WEDNESDAY_TEXT_TITLE;
      $this->description = MODULE_TIPSA_WEDNESDAY_TEXT_DESCRIPTION;
      $this->sort_order = MODULE_TIPSA_WEDNESDAY_SORT_ORDER;
      $this->icon = DIR_WS_ICONS . 'gls.png';
      $this->tax_class = MODULE_TIPSA_WEDNESDAY_TAX_CLASS;
      $this->enabled = ((MODULE_TIPSA_WEDNESDAY_STATUS == 'True') ? true : false);

	  // Si tenemos el módulo activo
	  if( $this->enabled )
	  {
		  // Solo activo el Viernes hasta las 16
		  $this->enabled = ( date( 'w' ) == 6 && date( 'G' ) < 16 );
	  }

      // CONFIGURE ESTE PARÁMETRO PARA ESTABLECER EL NÚMERO DE ZONAS NECESARIAS
      $this->num_zones = 9;
	}

// class methods
    function quote($method = '') {
     global $order, $shipping_weight, $shipping_num_boxes;

     $dest_zone = 0;
     $error = false;
	 $zones_weight_cost = 0;
	 $shipping_method = '';
	 $shipping_cost = 0;
     //si el peso del envío es menor o igual de 31 Kg intentar realizar el envío
	 if ($shipping_weight < 200)
	 { 
      for ($i=1; $i<=$this->num_zones; $i++) {
        $countries_table = constant('MODULE_TIPSA_WEDNESDAY_COUNTRIES_' . $i);
        if ( ($this->enabled == true) && ((int)constant('MODULE_TIPSA_WEDNESDAY_COUNTRIES_' . $i) > 0) ) {
			$check_query = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . $countries_table . "' and (zone_country_id = '" . $order->delivery['country']['id'] . "' or zone_country_id='0') order by zone_id");
			while ($check = tep_db_fetch_array($check_query)) {
			  if ( ($check['zone_id'] < 1) || ($check['zone_id'] == $order->delivery['zone_id']) ) {
			    $dest_zone = $i;
				break;
			  }
			}
	        if ($dest_zone > 0) {
				$shipping = -1;
				$zones_cost = constant('MODULE_TIPSA_WEDNESDAY_COST_' . $dest_zone);
				$zones_cost_table = preg_split("/[:,]/" , $zones_cost);
				$size = sizeof($zones_cost_table);
				for ($j=0;$j<$size; $j+=2) {				  
				  if ($shipping_weight < $zones_cost_table[$j])
				  {
					$shipping = 1;					
					//obtener el precio de envío por kg para esa zona
					$zones_weight_cost = $zones_cost_table[$j+1];
					$shipping_method = MODULE_TIPSA_WEDNESDAY_TEXT_WAY . ' ' . $order->delivery['country']['title'] . ' : ' . ($shipping_num_boxes > 1 ? $shipping_num_boxes . " x " : '') . $shipping_weight . ' ' . MODULE_TIPSA_WEDNESDAY_TEXT_UNITS;
					break;
				  }
				}
				//Fin Forma 2
				if ($shipping == -1) {
				  $shipping_cost = 0;
				  $shipping_method = MODULE_TIPSA_WEDNESDAY_UNDEFINED_RATE;
				} else {
				  $shipping_cost = $zones_weight_cost + constant('MODULE_TIPSA_WEDNESDAY_HANDLING_' . $dest_zone);
			      break;
				}

            }
		}
       }

       if ($dest_zone == 0) {
        $error = true;
       }

       $this->quotes = array('id' => $this->code,
                            'module' => MODULE_TIPSA_WEDNESDAY_TEXT_TITLE,
                            'methods' => array(array('id' => $this->code,
                                                     'title' => $shipping_method,
                                                     'cost' => $shipping_cost)));

       if ($this->tax_class > 0) {
        $this->quotes['tax'] = tep_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);
       }

       if (tep_not_null($this->icon)) $this->quotes['icon'] = 'shipping_furgoneta.png';
	   
	   if ($error == true) $this->quotes['error'] = MODULE_TIPSA_WEDNESDAY_INVALID_ZONE;
      }
	  else //el peso es mayor de 31 Kg
	  {
	   $error = true;
	   $this->quotes['module'] = MODULE_TIPSA_WEDNESDAY_TEXT_TITLE;
	   $this->quotes['error'] = MODULE_TIPSA_WEDNESDAY_OVER_WEIGHT;
	  }      

      return $this->quotes;
    }

    function check() {
      if (!isset($this->_check)) {
        $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_TIPSA_WEDNESDAY_STATUS'");
        $this->_check = tep_db_num_rows($check_query);
      }
      return $this->_check;
    }

    function install() {
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Activar Tisa (Sábados) €Pack', 'MODULE_TIPSA_WEDNESDAY_STATUS', 'True', '&iquest;Quiere activar el m&oacute;dulo de env&iacute;os Tipsa (Sábados) EuroPack?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Tipo Impuesto', 'MODULE_TIPSA_WEDNESDAY_TAX_CLASS', '0', 'Utilizar el siguiente tipo de impuesto para aplicar al env&iacute;o..', '6', '0', 'tep_get_tax_class_title', 'tep_cfg_pull_down_tax_classes(', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Orden Visualizaci&oacute;n', 'MODULE_TIPSA_WEDNESDAY_SORT_ORDER', '0', 'El menor se visualiza primero.', '6', '0', now())");
      for ($i = 1; $i <= $this->num_zones; $i++) {
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Zona " . $i ."', 'MODULE_TIPSA_WEDNESDAY_COUNTRIES_" . $i ."', '0', 'Debe seleccionar una Zona de Impuestos para activar el m&eacute;todo de env&iacute;o sobre esta zona" . $i . ".', '6', '0', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Zona " . $i ." Tabla Env&iacute;os', 'MODULE_TIPSA_WEDNESDAY_COST_" . $i ."', '3:13.46,10:15.55,15:17.64,20:21.09,31:24.54', 'Tarifas Env&iacute;o para la zona " . $i . ". Precios basados por grupos de peso. Ejemplo: 3:13.46,10:15.55,... Pedidos con Peso < 3 tienen 13.46 Euros de gastos de env&iacute;o. Pedidos con Peso >= 3 y < 10 tienen 15.55 euros de gastos de env&iacute; para la Zona " . $i . ".', '6', '0', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Zona " . $i ." Handling Fee', 'MODULE_TIPSA_WEDNESDAY_HANDLING_" . $i."', '0', 'Handling Fee para esta zona', '6', '0', now())");
      }
    }

    function remove() {
      tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys() {
      $keys = array('MODULE_TIPSA_WEDNESDAY_STATUS', 'MODULE_TIPSA_WEDNESDAY_TAX_CLASS', 'MODULE_TIPSA_WEDNESDAY_SORT_ORDER');

      for ($i=1; $i<=$this->num_zones; $i++) {
        $keys[] = 'MODULE_TIPSA_WEDNESDAY_COUNTRIES_' . $i;
        $keys[] = 'MODULE_TIPSA_WEDNESDAY_COST_' . $i;
        $keys[] = 'MODULE_TIPSA_WEDNESDAY_HANDLING_' . $i;
      }

      return $keys;
    }
  }
?>