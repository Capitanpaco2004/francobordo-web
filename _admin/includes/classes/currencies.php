<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2007 osCommerce

  Released under the GNU General Public License
*/

////
// Class to handle currencies
// TABLES: currencies
  class currencies
  {
	  public $currencies = [];

// class constructor
	function __construct()
	{
		$currencies_query = tep_db_query("select code, title, symbol_left, symbol_right, decimal_point, thousands_point, decimal_places, value from " . TABLE_CURRENCIES);
		while ($currencies = tep_db_fetch_array($currencies_query)) {
			$this->currencies[$currencies['code']] = ['title' => $currencies['title'],
				'symbol_left' => $currencies['symbol_left'],
				'symbol_right' => $currencies['symbol_right'],
				'decimal_point' => $currencies['decimal_point'],
				'thousands_point' => $currencies['thousands_point'],
				'decimal_places' => $currencies['decimal_places'],
				'value' => $currencies['value']];
		}

		$this->currencies['EUR_POINT'] = $this->currencies['EUR'];
		$this->currencies['EUR_POINT']['decimal_point'] = '.';
		$this->currencies['EUR_POINT']['symbol_right'] = '';
	}

// class methods
	function format($number, $calculate_currency_value = true, $currency_type = '', $currency_value = '')
	{
		if (empty($currency_type)) {
            $currency_type = strtoupper(DEFAULT_CURRENCY);
        }


		if ($calculate_currency_value == true) {
			$rate = (tep_not_null($currency_value)) ? $currency_value : $this->currencies[$currency_type]['value'];

			$format_string = $this->currencies[$currency_type]['symbol_left'] . number_format(tep_round($number * $rate, $this->currencies[$currency_type]['decimal_places']), $this->currencies[$currency_type]['decimal_places'], $this->currencies[$currency_type]['decimal_point'], $this->currencies[$currency_type]['thousands_point']) . $this->currencies[$currency_type]['symbol_right'];
		} else {
			$format_string = $this->currencies[$currency_type]['symbol_left'] . number_format(tep_round($number, $this->currencies[$currency_type]['decimal_places']), $this->currencies[$currency_type]['decimal_places'], $this->currencies[$currency_type]['decimal_point'], $this->currencies[$currency_type]['thousands_point']) . $this->currencies[$currency_type]['symbol_right'];
		}

		return $format_string;
	}

	function get_value($code)
	{
		return $this->currencies[$code]['value'];
	}

	function calculate_price($products_price, $products_tax, $quantity = 1, $currency_type = DEFAULT_CURRENCY)
	{
		if (empty($currency_type)) {
            $currency_type = strtoupper(DEFAULT_CURRENCY);
        }

		return tep_round(tep_add_tax($products_price, $products_tax), $this->currencies[$currency_type]['decimal_places']) * $quantity;
	}

	function display_price($products_price, $products_tax, $quantity = 1, $currency_type = '')
	{
		return $this->format($this->calculate_price($products_price, $products_tax, $quantity, $currency_type), true, $currency_type);
	}


}
