<?php

/**
 * Obtiene los estados de los pedidos.
 *
 * @param int $languages_id ID del idioma
 * @return array            Array de estados de pedidos
 */
if (!function_exists('getOrdersStatuses')) {
function getOrdersStatuses($languages_id)
{
	$orders_statuses = [];
	$orders_status_array = [];

	$orders_status_query = tep_db_query("SELECT orders_status_id, orders_status_name FROM " . TABLE_ORDERS_STATUS . " WHERE language_id = '" . (int)$languages_id . "' ORDER BY sort_order ASC");

	while ($orders_status = tep_db_fetch_array($orders_status_query)) {
		$orders_statuses[] = ['id' => $orders_status['orders_status_id'], 'text' => $orders_status['orders_status_name']];
		$orders_status_array[$orders_status['orders_status_id']] = $orders_status['orders_status_name'];
	}

	return [
		'statuses' => $orders_statuses,
		'status_array' => $orders_status_array
	];
}
} // end function_exists getOrdersStatuses

/**
 * Determina si un color hex es oscuro o claro.
 * Se usa para decidir el color del texto sobre un fondo de color.
 *
 * @param string|null $color Color en formato hex (#RRGGBB)
 * @return bool              true si el color es oscuro
 */
function isDarkColor($color) {
	if (!empty($color)) {
		[$r, $g, $b] = sscanf($color, "#%02x%02x%02x");
		$brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
		return $brightness < 128;
	} else {
		return false;
	}
}
