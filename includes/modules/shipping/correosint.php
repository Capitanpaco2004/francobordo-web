<?php
/*
  $Id: correosint.php, v1.00 2003/11/29 12:00:00 jmtorne Exp $
  Copyright (c) 2008 Alfonso Gonzalez
  This program is free software; you can redistribute it and/or modify it under the terms
  of the GNU General Public License as published by the Free Software Foundation; either
  version 2 of the License, or (at your option) any later version.
  This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
  without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
  See the GNU General Public License for more details.
  You should have received a copy of the GNU General Public License along with this program;
  If not, you may obtain one by writing to and requesting one from
	The Free Software Foundation, Inc.,
	59 Temple Place, Suite 330,
	Boston, MA 02111-1307 USA
*/
  class correosint {
    var $code, $title, $description, $icon, $enabled, $types, $sort_order, $tax_class, $_check, $quotes;
// class constructor
    function __construct() {
      global $order;
      $this->code = 'correosint';
      $this->icon = DIR_WS_ICONS . 'correos.png';
      $this->title = MODULE_SHIPPING_CORREOSINT_TEXT_TITLE;
      $this->description = MODULE_SHIPPING_CORREOSINT_TEXT_DESCRIPTION;
      $this->sort_order = MODULE_SHIPPING_CORREOSINT_SORT_ORDER;
      $this->tax_class = MODULE_SHIPPING_CORREOSINT_TAX_CLASS;
      $this->enabled = ((MODULE_SHIPPING_CORREOSINT_STATUS == 'True') ? true : false);
      $this->types = array('Normal' => MODULE_SHIPPING_CORREOSINT_TEXT_WAY);
      if ( ($this->enabled == true) && ((int)MODULE_SHIPPING_CORREOSINT_ZONE > 0) && is_object($order) ) {
        $check_flag = false;
        $check_query = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_SHIPPING_CORREOSINT_ZONE . "' and zone_country_id = '" . $order->delivery['country']['id'] . "' order by zone_id");
        while ($check = tep_db_fetch_array($check_query)) {
          if ($check['zone_id'] < 1) {
            $check_flag = true;
            break;
          } elseif ($check['zone_id'] == $order->delivery['zone_id']) {
            $check_flag = true;
            break;
          }
        }
        if ($check_flag == false) {
          $this->enabled = false;
        }
      }
    }
// class methods
    function quote($method = '') {
	global $order, $cart, $shipping_weight, $shipping_num_boxes;
	// Por Correos, siempre calculamos según el peso
	$order_total = $shipping_weight;
	//Normal
	$table_cost = preg_split("/[:,]/" , MODULE_SHIPPING_CORREOSINT_COST);
	$size = sizeof($table_cost);
	for ($i=0, $n=$size; $i<$n; $i+=2) {
		if ($order_total <= $table_cost[$i]) {
		$shipping1 = $table_cost[$i+1];
		break;
		}
	}
	$shipping['Normal'] = $shipping1 * $shipping_num_boxes;
        $this->quotes = array('id' => $this->code,
                            'module' => MODULE_SHIPPING_CORREOSINT_TEXT_TITLE );
	if($method)
	{
		$this->quotes['methods'][]=array('id' =>  $method,
						'title' => $this->types[$method],
						'cost' => $shipping[$method] + MODULE_SHIPPING_CORREOSINT_HANDLING);
	}else{
		foreach($this->types as $type=>$txtType)
		{
		$this->quotes['methods'][]=array('id' =>  $type,
						'title' => $txtType,
						'cost' => $shipping[$type] + MODULE_SHIPPING_CORREOSINT_HANDLING);
		}
	}
	if ($this->tax_class > 0) {
		$this->quotes['tax'] = tep_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);
	}
	if (tep_not_null($this->icon)) $this->quotes['icon'] = 'shipping_correos.png';
	return $this->quotes;
    }
    function check() {
      if (!isset($this->_check)) {
        $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_SHIPPING_CORREOSINT_STATUS'");
        $this->_check = tep_db_num_rows($check_query);
      }
      return $this->_check;
    }
    function install() {
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Habilitar Correos Internacional?', 'MODULE_SHIPPING_CORREOSINT_STATUS', 'True', 'Ofrecer esta forma de envío?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Tarifas Correos Internacional zona Euro', 'MODULE_SHIPPING_CORREOSINT_COST', '0.100:4.50,0.200:6.40,0.350:9.65,0.500:12.40,1:14.10,1.5:20.00,2:22.60', 'Tarifas de Correos', '6', '0', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Gastos de manipulación', 'MODULE_SHIPPING_CORREOSINT_HANDLING', '0.50', 'Coste del embalado y la manipulación', '6', '0', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Tipo de impuesto indirecto', 'MODULE_SHIPPING_CORREOSINT_TAX_CLASS', '0', 'Use el IVA como tipo de impuesto o bien no desglose impuestos en los envios', '6', '0', 'tep_get_tax_class_title', 'tep_cfg_pull_down_tax_classes(', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Zona de envío', 'MODULE_SHIPPING_CORREOSINT_ZONE', '0', '.', '6', '0', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Orden', 'MODULE_SHIPPING_CORREOSINT_SORT_ORDER', '0', 'Orden de aparición', '6', '0', now())");
    }
    function remove() {
      tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }
    function keys() {
      return array('MODULE_SHIPPING_CORREOSINT_STATUS', 'MODULE_SHIPPING_CORREOSINT_COST', 'MODULE_SHIPPING_CORREOSINT_HANDLING', 'MODULE_SHIPPING_CORREOSINT_TAX_CLASS', 'MODULE_SHIPPING_CORREOSINT_ZONE', 'MODULE_SHIPPING_CORREOSINT_SORT_ORDER');
    }
  }
?>
