<?php

function request_uri() {
	return $_SERVER['REQUEST_URI'] ?? ($_SERVER['PHP_SELF'] . '?' . ($_SERVER['argv'][0] ?? $_SERVER['QUERY_STRING'] ?? ''));
}

function tep_update_whos_online() {
	global $customer_id, $spider_flag;

	$wo_ip_address    = tep_get_ip_address();
	$wo_last_page_url = htmlentities(request_uri());
	$current_time     = time();
	$wo_session_id    = tep_session_id() ?: md5($wo_ip_address . $current_time);
	$user_agent       = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

	// 🔹 Filtro: ignorar assets estáticos
	$ignore_ext = ['.css', '.js', '.map', '.jpg', '.jpeg', '.png', '.gif', '.svg', '.ico', '.woff', '.woff2', '.ttf'];
	foreach ($ignore_ext as $ext) {
		if (stripos($wo_last_page_url, $ext) !== false) {
			return; // 🚫 no insertamos en whos_online
		}
	}

	// 🔹 Detectar bots
	$is_bot = $spider_flag
		|| stripos($user_agent, 'bot') !== false
		|| stripos($user_agent, 'crawler') !== false
		|| stripos($user_agent, 'spider') !== false;

	if ($is_bot) {
		$customer_id = -1;
		preg_match('/([A-Za-z]+bot)/i', $user_agent, $m);
		$wo_full_name = $m[1] ?? 'Bot';
	} else {
		if ($customer_id > 0) {
			$c            = tep_db_fetch_array(
				tep_db_query("SELECT customers_firstname, customers_lastname
				               FROM " . TABLE_CUSTOMERS . "
				              WHERE customers_id=" . (int)$customer_id));
			$wo_full_name = $c['customers_firstname'] . ' ' . $c['customers_lastname'];
		} else {
			$customer_id  = 0;
			$wo_full_name = 'Cliente Anónimo';
		}
	}

	tep_db_query("
        INSERT INTO " . TABLE_WHOS_ONLINE . " (
            customer_id, full_name, session_id, ip_address, hostname,
            time_entry, time_last_click, last_page_url, http_referer, user_agent
        ) VALUES (
            '" . (int)$customer_id . "',
            '" . tep_db_input($wo_full_name) . "',
            '" . tep_db_input($wo_session_id) . "',
            '" . tep_db_input($wo_ip_address) . "',
            '', /* hostname se rellena luego */
            '$current_time',
            '$current_time',
            '" . tep_db_input($wo_last_page_url) . "',
            '" . tep_db_input($_SERVER['HTTP_REFERER'] ?? '') . "',
            '" . tep_db_input($user_agent) . "'
        )
        ON DUPLICATE KEY UPDATE
            customer_id      = VALUES(customer_id),
            full_name        = VALUES(full_name),
            ip_address       = VALUES(ip_address),
            time_last_click  = VALUES(time_last_click),
            last_page_url    = VALUES(last_page_url),
            http_referer     = VALUES(http_referer),
            user_agent       = VALUES(user_agent)
    ");
}

function tep_delete_expired_whos_online() {
	$threshold = time() - 900; // 15 minutos
	tep_db_query("DELETE FROM " . TABLE_WHOS_ONLINE . " WHERE time_last_click < '" . $threshold . "'");
}

function tep_whos_online_update_session_id($old_id, $new_id) {
	tep_db_query("UPDATE " . TABLE_WHOS_ONLINE . " SET session_id = '" . tep_db_input($new_id) . "' WHERE session_id = '" . tep_db_input($old_id) . "'");
}
