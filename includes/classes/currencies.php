<?php

class currencies
{
	var $currencies;

	function __construct()
	{
		$this->currencies = array();
		$currencies_query = tep_db_query("select code, title, symbol_left, symbol_right, decimal_point, thousands_point, decimal_places, value from " . TABLE_CURRENCIES);
		while ($currencies = tep_db_fetch_array($currencies_query)) {
			$this->currencies[$currencies['code']] = array('title' => $currencies['title'],
				'symbol_left' => $currencies['symbol_left'],
				'symbol_right' => $currencies['symbol_right'],
				'decimal_point' => $currencies['decimal_point'],
				'thousands_point' => $currencies['thousands_point'],
				'decimal_places' => (int)$currencies['decimal_places'],
				'value' => $currencies['value']);
		}

		$this->currencies['EUR_POINT'] = $this->currencies['EUR'];
		$this->currencies['EUR_POINT']['decimal_point'] = '.';
		$this->currencies['EUR_POINT']['symbol_right'] = '';
	}

	function format($number, $calculate_currency_value = true, $currency_type = '', $currency_value = '')
	{
		global $currency;

		if (empty($currency_type)) $currency_type = strtoupper($currency);

		if ($calculate_currency_value == true) {
			$rate = (tep_not_null($currency_value)) ? $currency_value : $this->currencies[$currency_type]['value'];
			$format_string = $this->currencies[$currency_type]['symbol_left'] . number_format(tep_round($number * $rate, $this->currencies[$currency_type]['decimal_places']), $this->currencies[$currency_type]['decimal_places'], $this->currencies[$currency_type]['decimal_point'], $this->currencies[$currency_type]['thousands_point']) . $this->currencies[$currency_type]['symbol_right'];
		} else {
			$format_string = $this->currencies[$currency_type]['symbol_left'] . number_format(tep_round($number, $this->currencies[$currency_type]['decimal_places']), $this->currencies[$currency_type]['decimal_places'], $this->currencies[$currency_type]['decimal_point'], $this->currencies[$currency_type]['thousands_point']) . $this->currencies[$currency_type]['symbol_right'];
		}

		return $format_string;
	}

	function calculate_price($products_price, $products_tax, $quantity = 1)
	{
		global $currency;

		$price = (float)tep_round(tep_add_tax((float)$products_price, (float)$products_tax), $this->currencies[$currency]['decimal_places']);
		return $price * (float)$quantity;
	}

	function is_set($code)
	{
		if (isset($this->currencies[$code]) && tep_not_null($this->currencies[$code])) {
			return true;
		}

		return false;
	}

	function get_decimal_places($code)
	{
		return $this->currencies[$code]['decimal_places'];
	}

	function display_price($products_price, $products_tax, $quantity = 1, $currency_type = '', $returnZero = false)
	{
	
			return $this->format($this->calculate_price($products_price, $products_tax, $quantity), true, $currency_type);

	}
}

?>
