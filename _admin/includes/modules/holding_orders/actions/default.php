<?php
// Filtro según parámetros GET
$conditions = [];

// Filtro texto general (nombre, email, teléfono, ID pedido)
if (!empty($_GET['search'])) {
	$search = tep_db_input(trim($_GET['search']));
	$conditions[] = "(
        o.customers_name LIKE '%$search%' OR
        o.customers_email_address LIKE '%$search%' OR
        o.customers_telephone LIKE '%$search%' OR
        o.orders_id = '" . (int)$search . "'
    )";
}

// Filtro por fecha
if (!empty($_GET['filter_date'])) {
	$filter_date = tep_db_input($_GET['filter_date']);
	$conditions[] = "DATE(o.date_purchased) = '$filter_date'";
}

// Filtro por Estado Redsys
if (!empty($_GET['filter_redsys_status'])) {
	switch ($_GET['filter_redsys_status']) {
		case 'authorized':
			$conditions[] = "(r.ds_response = '0000' AND r.ds_state = 'F')";
			break;

		case 'invalid':
			$conditions[] = "NOT (r.ds_response = '0000' AND r.ds_state = 'F')";
			break;
	}
}


$sWhere = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Consulta principal para obtener los datos
$sql_orders = "
    SELECT
        o.orders_id, o.customers_name, o.payment_method, o.date_purchased, o.last_modified,
        s.orders_status_name, ot.text as order_total,
        r.ds_order, r.ds_response, r.ds_response_msg, r.ds_transaction_type, r.ds_merchant_identifier, r.ds_state, r.ds_state_msg
    FROM " . TABLE_HOLDING_ORDERS . " o
    LEFT JOIN " . TABLE_HOLDING_ORDERS_TOTAL . " ot ON o.orders_id = ot.orders_id AND ot.class = 'ot_total'
    LEFT JOIN holding_orders_redsys r ON o.orders_id = r.orders_id
    JOIN " . TABLE_ORDERS_STATUS . " s ON o.orders_status = s.orders_status_id AND s.language_id = '" . (int)$languages_id . "'
    $sWhere
    GROUP BY o.orders_id
    ORDER BY o.orders_id DESC
";

// Consulta COUNT para paginación
$count_query_raw = "
    SELECT COUNT(DISTINCT o.orders_id) AS total
    FROM " . TABLE_HOLDING_ORDERS . " o
    LEFT JOIN " . TABLE_HOLDING_ORDERS_TOTAL . " ot ON o.orders_id = ot.orders_id AND ot.class = 'ot_total'
    LEFT JOIN holding_orders_redsys r ON o.orders_id = r.orders_id
    JOIN " . TABLE_ORDERS_STATUS . " s ON o.orders_status = s.orders_status_id AND s.language_id = '" . (int)$languages_id . "'
    $sWhere
";

// Obtener total de resultados
$count_result = tep_db_fetch_array(tep_db_query($count_query_raw));
$total_orders = (int)$count_result['total'];

// Instancia de paginación
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 50;

// Consulta principal y count_query_raw deben definirse antes
$orders_split = new splitPageResults($currentPage, $perPage, $sql_orders, $total_orders, $count_query_raw);
$orders_query = tep_db_query($sql_orders);

// Pasamos la variable a la plantilla
$sHtmlModule = includeTemplate($sPathModule . '/templates/order_list.php', [
	'orders_query' => $orders_query,
	'orders_split' => $orders_split,
	'sUrlPage' => $sUrlPage
]);
