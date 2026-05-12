<?php
/**
 * Who's Online Module v4.1
 * Optimized: batch queries for session data, product names, category names
 * PHP 8.5 compatible
 */

// Incluimos el application_top
require_once('includes/application_top.php');

require(DIR_WS_CLASSES . 'currencies.php');
$currencies = new currencies();

// Variables
$sUrlPage      = 'whos_online.php';
$sPathModule   = 'includes/modules/whos_online';
$sPathTemplate = $sPathModule . '/templates';
$sTitle        = HEADING_TITLE;
$sSubtitle     = '';
$aButtons      = [];

# Messagestack estilo
$messageStack->style = 'solenopsis';

// Configuraci\u00f3n
$time_format           = 24;
$active_time           = 300;   // Segundos que un visitante se considera "activo"
$track_time            = 900;   // Segundos antes de eliminar visitante
$referrer_wordwrap_chars = 150;

// GET parameters - PHP 8.x safe
$get_refresh = $_GET['refresh'] ?? '';
$get_show    = $_GET['show'] ?? '';
$get_bots    = $_GET['bots'] ?? '';
$get_info    = $_GET['info'] ?? '';

// Colores por tipo de usuario
$fg_color_bot     = '#800000';
$fg_color_admin   = '#00008B';
$fg_color_guest   = '#2E8B57';
$fg_color_account = '#0066CC';

// --- Funciones auxiliares ---

/**
 * Geolocalizaci\u00f3n batch de IPs via ip-api.com
 */
function updateIpsBatch() {
	$ips   = [];
	$query = tep_db_query("
		SELECT DISTINCT ip_address
		FROM " . TABLE_WHOS_ONLINE . "
		WHERE country_code IS NULL OR country_code = ''
		LIMIT 100
	");

	while ($row = tep_db_fetch_array($query)) {
		$ips[] = $row['ip_address'];
	}

	if (empty($ips)) return;

	$ch = curl_init('http://ip-api.com/batch?fields=status,message,country,countryCode,regionName,city,lat,lon,query');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($ips));
	curl_setopt($ch, CURLOPT_TIMEOUT, 5);

	$response = curl_exec($ch);
	unset($ch);

	$data = json_decode($response, true);
	if (!is_array($data)) return;

	// Batch UPDATE con los datos de geolocalizacion (sin gethostbyaddr que bloquea 2-3s por IP)
	foreach ($data as $entry) {
		if (($entry['status'] ?? '') !== 'success') continue;

		$ip = $entry['query'];

		tep_db_query("
			UPDATE " . TABLE_WHOS_ONLINE . " SET
				country_code = '" . tep_db_input($entry['countryCode']) . "',
				country_name = '" . tep_db_input($entry['country']) . "',
				region_name  = '" . tep_db_input($entry['regionName']) . "',
				city         = '" . tep_db_input($entry['city']) . "',
				latitude     = '" . tep_db_input((string)$entry['lat']) . "',
				longitude    = '" . tep_db_input((string)$entry['lon']) . "'
			WHERE ip_address = '" . tep_db_input($ip) . "'
		");
	}
}

/**
 * Determina tipo de visitante y devuelve datos de estado
 */
function getVisitorType(array $row, string $admin_ip): array {
	global $fg_color_bot, $fg_color_admin, $fg_color_guest, $fg_color_account;

	if ($row['customer_id'] < 0) {
		return ['type' => 'bot', 'color' => $fg_color_bot];
	} elseif ($row['ip_address'] === $admin_ip) {
		return ['type' => 'admin', 'color' => $fg_color_admin];
	} elseif ($row['customer_id'] == 0) {
		return ['type' => 'guest', 'color' => $fg_color_guest];
	} else {
		return ['type' => 'account', 'color' => $fg_color_account];
	}
}

/**
 * Determina si el visitante esta activo
 */
function isVisitorActive(int $last_click, int $active_time): bool {
	return $last_click >= (time() - $active_time);
}

/**
 * Obtiene nombre legible del bot a partir del user agent
 */
function getBotName(string $full_name): string {
	$tok = strtok($full_name, " ();/");
	$skip = ['mozilla', 'compatible', 'msie', 'windows'];
	while ($tok !== false) {
		$lower = strtolower($tok);
		if (strlen($lower) > 3) {
			$found = false;
			foreach ($skip as $s) {
				if (str_contains($lower, $s)) { $found = true; break; }
			}
			if (!$found) return htmlspecialchars($tok);
		}
		$tok = strtok(" ();/");
	}
	return 'Bot';
}

/**
 * Extrae product_id de una URL
 */
function extractProductId(string $url): string {
	if (strpos($url, 'product_info.php') !== false) {
		if (strpos($url, 'products_id=') !== false) {
			parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $params);
			return $params['products_id'] ?? '';
		} elseif (strpos($url, 'products_id/') !== false) {
			$temp = strstr($url, 'products_id');
			$parts = explode('/', $temp);
			return $parts[1] ?? '';
		}
	} elseif (preg_match('/^(.*)-p-(.*?)\.html/', $url, $matches)) {
		return $matches[2];
	}
	return '';
}

/**
 * Extrae category path de una URL
 */
function extractCategoryPath(string $url): string {
	if (strpos($url, 'cPath') !== false) {
		if (strpos($url, 'cPath=') !== false) {
			parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $params);
			return $params['cPath'] ?? '';
		} elseif (strpos($url, 'cPath/') !== false) {
			$temp = strstr($url, 'cPath');
			$parts = explode('/', $temp);
			return $parts[1] ?? '';
		}
	} elseif (preg_match('/^(.*)-c-(.*?)\.html/', $url, $matches)) {
		return $matches[2];
	}
	return '';
}

/**
 * Resuelve URL usando caches batch de nombres de producto/categoria
 */
function resolveLastPageUrlCached(string $url, array &$product_names, array &$category_names): array {
	$display = $url;
	$link    = $url;

	// Limpiar osCsid
	$osCsid_pos = strpos($display, 'osCsid');
	if ($osCsid_pos !== false) $display = substr_replace($display, '', $osCsid_pos - 1);
	$osCsid_pos = strpos($link, 'osCsid');
	if ($osCsid_pos !== false) $link = substr_replace($link, '', $osCsid_pos - 1);

	// Limpiar session name
	if (preg_match('/^(.*)' . tep_session_name() . '=[a-f,0-9]+[&]*(.*)/', $url, $m)) {
		$display = $m[1] . $m[2];
	}

	$resolved_name = htmlspecialchars($display);

	// Producto
	$pid = extractProductId($link);
	if ($pid !== '') {
		$int_pid = (int)$pid;
		if (isset($product_names[$int_pid])) {
			$resolved_name = htmlspecialchars($product_names[$int_pid]) . ' <i>(Product)</i>';
		}
		return ['display' => $resolved_name, 'link' => $link];
	}

	// Categoria
	$cat = extractCategoryPath($link);
	if ($cat !== '') {
		$cat_ids = explode('_', $cat);
		$names = [];
		foreach ($cat_ids as $cid) {
			$int_cid = (int)$cid;
			if (isset($category_names[$int_cid])) {
				$names[] = $category_names[$int_cid];
			}
		}
		if (!empty($names)) {
			$resolved_name = htmlspecialchars(implode(' / ', $names)) . ' <i>(Category)</i>';
		}
	}

	return ['display' => $resolved_name, 'link' => $link];
}

/**
 * Lee los datos de sesion de un visitante.
 * Busca en customers_session + customers_session_storage (nuevo sistema).
 */
function readSessionData(string $session_id): string {
	$result = tep_db_query("
		SELECT css.value
		FROM customers_session cs
		INNER JOIN customers_session_storage css ON css.token = cs.token
		WHERE cs.sesskey = '" . tep_db_input($session_id) . "'
	");
	$row = tep_db_fetch_array($result);
	if (!empty($row['value'])) {
		return trim($row['value']);
	}
	return '';
}

/**
 * Obtiene contenido del carrito para el visitante seleccionado.
 * Parsea la sesion PHP serializando el bloque del cart.
 */
function getCartContents(string $session_id, $currencies): array {
	$contents = [];

	$session_data = readSessionData($session_id);
	if (strlen($session_data) === 0) return $contents;

	// Extraer contents del carrito directamente con regex sobre datos serializados
	// Formato: contents";a:N:{...productos...}
	if (!preg_match('/contents";a:(\d+):\{/', $session_data, $match, PREG_OFFSET_CAPTURE)) {
		$contents[] = ['text' => '<i>' . TEXT_EMPTY . '</i>'];
		return $contents;
	}

	$num_products = (int)$match[1][0];
	if ($num_products === 0) {
		$contents[] = ['text' => '<i>' . TEXT_EMPTY . '</i>'];
		return $contents;
	}

	// Extraer el bloque del array contents: desde la { de apertura
	$start = $match[0][1] + strlen($match[0][0]);
	// Encontrar la } de cierre contando profundidad de llaves
	$depth = 1;
	$pos = $start;
	$len = strlen($session_data);
	while ($pos < $len && $depth > 0) {
		if ($session_data[$pos] === '{') $depth++;
		elseif ($session_data[$pos] === '}') $depth--;
		$pos++;
	}
	$contents_block = substr($session_data, $start, $pos - $start - 1);

	// Parsear pares: key (product_id) => array con qty
	// Keys pueden ser: s:5:"27552"; o i:27552;
	// Valores: a:1:{s:3:"qty";i:1;} o a:1:{s:3:"qty";d:2;}
	$cart_contents = [];
	preg_match_all('/(?:s:\d+:"([^"]+)"|i:(\d+));a:\d+:\{/', $contents_block, $key_matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

	foreach ($key_matches as $km) {
		$pid = ($km[1][0] !== '') ? $km[1][0] : $km[2][0];
		// Buscar qty despues de esta posicion
		$after = substr($contents_block, $km[0][1] + strlen($km[0][0]));
		$qty = 1;
		if (preg_match('/"qty";(?:i:(\d+)|d:([0-9.]+)|s:\d+:"(\d+)")/', $after, $qm)) {
			if ($qm[1] !== '') $qty = (int)$qm[1];
			elseif (isset($qm[2]) && $qm[2] !== '') $qty = (int)$qm[2];
			elseif (isset($qm[3]) && $qm[3] !== '') $qty = (int)$qm[3];
		}
		$cart_contents[$pid] = max(1, $qty);
	}

	if (empty($cart_contents)) {
		$contents[] = ['text' => $num_products . ' producto(s) en la cesta'];
		return $contents;
	}

	// Extraer currency de la sesion
	$currency = '';
	if (preg_match('/currency\|s:\d+:"([^"]+)"/', $session_data, $curr_match)) {
		$currency = $curr_match[1];
	}

	// Batch fetch de nombres y precios
	$product_ids = array_map('intval', array_keys($cart_contents));
	$products_data = [];
	if (!empty($product_ids)) {
		$lang_id = (int)($GLOBALS['languages_id'] ?? 1);
		$in = implode(',', $product_ids);
		$pq = tep_db_query("SELECT p.products_id, pd.products_name, p.products_price
			FROM " . TABLE_PRODUCTS . " p
			LEFT JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON (p.products_id = pd.products_id AND pd.language_id = '" . $lang_id . "')
			WHERE p.products_id IN ($in)");
		while ($prow = tep_db_fetch_array($pq)) {
			$products_data[(int)$prow['products_id']] = $prow;
		}
	}

	$total = 0;
	foreach ($cart_contents as $pid => $qty) {
		$int_pid = (int)$pid;
		if (isset($products_data[$int_pid])) {
			$product = $products_data[$int_pid];
			$line_price = (float)$product['products_price'] * $qty;
			$total += $line_price;
			$contents[] = ['text' => $qty . ' x ' . htmlspecialchars($product['products_name'])];
		}
	}

	if (count($contents) > 0) {
		$contents[] = ['text' => '<hr style="margin: 5px 0; border: 0; border-top: 1px solid #ddd;">'];
		$contents[] = ['align' => 'right', 'text' => '<b>' . TEXT_SHOPPING_CART_SUBTOTAL . ' ' . $currencies->format($total, true, $currency ?: null) . '</b>'];
	} else {
		$contents[] = ['text' => '<i>' . TEXT_EMPTY . '</i>'];
	}

	return $contents;
}

// --- Logica principal ---

// Limpiar entradas expiradas
$xx_mins_ago = (time() - $track_time);
tep_db_query("DELETE FROM " . TABLE_WHOS_ONLINE . " WHERE time_last_click < '" . $xx_mins_ago . "'");

// Geolocalizacion
updateIpsBatch();

// Auto-refresh
$sMetaRefresh = '';
if ($get_refresh !== '' && $get_refresh !== 'none' && is_numeric($get_refresh)) {
	$sMetaRefresh = '<meta http-equiv="refresh" content="' . (int)$get_refresh . ';URL=' . FILENAME_WHOS_ONLINE . '?' . htmlspecialchars($_SERVER["QUERY_STRING"]) . '">';
}

// Formato hora
$format_string = ($time_format == 12) ? 'h:i:s a' : 'H:i:s';

// Opciones de refresh y filtro
$refresh_values   = [];
$refresh_values[] = ['id' => 'none', 'text' => TEXT_NONE_];
$refresh_values[] = ['id' => '15',   'text' => '0:15'];
$refresh_values[] = ['id' => '30',   'text' => '0:30'];
$refresh_values[] = ['id' => '60',   'text' => '1:00'];
$refresh_values[] = ['id' => '120',  'text' => '2:00'];
$refresh_values[] = ['id' => '300',  'text' => '5:00'];
$refresh_values[] = ['id' => '600',  'text' => '10:00'];

$show_type   = [];
$show_type[] = ['id' => '',     'text' => TEXT_NONE_];
$show_type[] = ['id' => 'all',  'text' => TEXT_ALL];
$show_type[] = ['id' => 'bots', 'text' => TEXT_BOTS];
$show_type[] = ['id' => 'cust', 'text' => TEXT_CUSTOMERS];

// Query principal
$whos_online_query = tep_db_query("SELECT * FROM " . TABLE_WHOS_ONLINE . " ORDER BY time_last_click DESC");

// ============================================================
// PASO 1: Recoger todas las filas y extraer IDs para batch
// ============================================================
$all_rows = [];
$non_bot_session_ids = [];
$all_product_ids = [];
$all_category_ids = [];
$admin_ip = $_SERVER["REMOTE_ADDR"];

while ($row = tep_db_fetch_array($whos_online_query)) {
	$all_rows[] = $row;

	// Session IDs de no-bots para batch de carritos
	if ($row['customer_id'] >= 0 && $row['ip_address'] !== $admin_ip) {
		$non_bot_session_ids[] = $row['session_id'];
	}

	// Extraer product/category IDs de las URLs
	$url = $row['last_page_url'];
	$pid = extractProductId($url);
	if ($pid !== '') {
		$all_product_ids[] = (int)$pid;
	} else {
		$cat = extractCategoryPath($url);
		if ($cat !== '') {
			foreach (explode('_', $cat) as $cid) {
				$all_category_ids[] = (int)$cid;
			}
		}
	}
}

// ============================================================
// PASO 2: Batch fetch - datos de sesion para contar carritos
// Una sola query en vez de N queries individuales
// ============================================================
$session_cart_counts = [];
if (!empty($non_bot_session_ids)) {
	$unique_sessions = array_unique($non_bot_session_ids);
	$escaped = array_map(fn($id) => "'" . tep_db_input($id) . "'", $unique_sessions);
	foreach (array_chunk($escaped, 500) as $chunk) {
		$in = implode(',', $chunk);
		// Solo detectar si hay carrito con LOCATE en MySQL, sin traer el value completo
		$r = tep_db_query("
			SELECT cs.sesskey,
				CAST(SUBSTRING(css.value,
					LOCATE('contents\";a:', css.value) + 12,
					LOCATE(':', css.value, LOCATE('contents\";a:', css.value) + 12) - LOCATE('contents\";a:', css.value) - 12
				) AS UNSIGNED) AS cart_count
			FROM customers_session cs
			INNER JOIN customers_session_storage css ON css.token = cs.token
			WHERE cs.sesskey IN ($in)
			AND css.value LIKE '%contents\";a:%'
			AND css.value NOT LIKE '%contents\";a:0:%'
		");
		while ($srow = tep_db_fetch_array($r)) {
			$count = (int)($srow['cart_count'] ?? 0);
			if ($count > 0) {
				$session_cart_counts[$srow['sesskey']] = $count;
			}
		}
	}
}

// ============================================================
// PASO 3: Batch fetch - nombres de productos (1 query)
// ============================================================
$product_names_cache = [];
$all_product_ids = array_unique(array_filter($all_product_ids));
if (!empty($all_product_ids)) {
	$lang_id = (int)($languages_id ?? 1);
	foreach (array_chunk($all_product_ids, 500) as $chunk) {
		$in = implode(',', $chunk);
		$r = tep_db_query("SELECT products_id, products_name FROM " . TABLE_PRODUCTS_DESCRIPTION . " WHERE products_id IN ($in) AND language_id='$lang_id'");
		while ($prow = tep_db_fetch_array($r)) {
			$product_names_cache[(int)$prow['products_id']] = $prow['products_name'];
		}
	}
}

// ============================================================
// PASO 4: Batch fetch - nombres de categorias (1 query)
// ============================================================
$category_names_cache = [];
$all_category_ids = array_unique(array_filter($all_category_ids));
if (!empty($all_category_ids)) {
	$lang_id = (int)($languages_id ?? 1);
	foreach (array_chunk($all_category_ids, 500) as $chunk) {
		$in = implode(',', $chunk);
		$r = tep_db_query("SELECT categories_id, categories_name FROM " . TABLE_CATEGORIES_DESCRIPTION . " WHERE categories_id IN ($in) AND language_id='$lang_id'");
		while ($crow = tep_db_fetch_array($r)) {
			$category_names_cache[(int)$crow['categories_id']] = $crow['categories_name'];
		}
	}
}

// ============================================================
// PASO 5: Procesar filas usando datos pre-cargados (sin queries)
// ============================================================
$total_bots     = 0;
$total_admin    = 0;
$total_guests   = 0;
$total_loggedon = 0;
$total_dupes    = 0;
$ip_addrs_active = [];
$ip_addrs        = [];

$selected_info = ($get_info !== '') ? $get_info : null;
$http_referer_url = '';

$aRows = [];
foreach ($all_rows as $row) {
	$visitor       = getVisitorType($row, $admin_ip);
	$is_active     = isVisitorActive((int)$row['time_last_click'], $active_time);
	$time_online   = (int)$row['time_last_click'] - (int)$row['time_entry'];
	$cart_count    = 0;

	// Seleccionar primer registro si no hay info
	if ($selected_info === null) {
		$selected_info = $row['session_id'];
	}

	// Contadores
	switch ($visitor['type']) {
		case 'bot':   $total_bots++; break;
		case 'admin': $total_admin++; break;
		case 'guest': $total_guests++; break;
		case 'account': $total_loggedon++; break;
	}

	// Duplicados
	if (in_array($row['ip_address'], $ip_addrs)) $total_dupes++;
	$ip_addrs[] = $row['ip_address'];

	// Contar activos (excluyendo admin y bots)
	if ($is_active && $visitor['type'] !== 'bot' && $visitor['type'] !== 'admin') {
		if (!in_array($row['ip_address'], $ip_addrs_active)) {
			$ip_addrs_active[] = $row['ip_address'];
		}
	}

	// Carrito desde cache batch (0 queries adicionales)
	if ($visitor['type'] !== 'bot') {
		$cart_count = $session_cart_counts[$row['session_id']] ?? 0;
	}

	// URL resuelta usando caches batch (0 queries adicionales)
	$resolved_url = resolveLastPageUrlCached($row['last_page_url'], $product_names_cache, $category_names_cache);

	// Nombre a mostrar
	if ($visitor['type'] === 'bot') {
		$display_name = getBotName($row['full_name']);
	} elseif ($visitor['type'] === 'account') {
		$display_name = '<a href="customers.php?selected_box=customers&cID=' . (int)$row['customer_id'] . '&action=edit" style="color: ' . $visitor['color'] . ';">' . htmlspecialchars($row['full_name']) . '</a>';
	} else {
		$display_name = htmlspecialchars($row['full_name']);
	}

	// Referrer del seleccionado
	if ($row['session_id'] === $selected_info && $row['http_referer'] != '') {
		$http_referer_url = $row['http_referer'];
	}

	$aRows[] = [
		'raw'           => $row,
		'visitor'       => $visitor,
		'is_active'     => $is_active,
		'time_online'   => $time_online,
		'cart_count'    => $cart_count,
		'display_name'  => $display_name,
		'resolved_url'  => $resolved_url,
		'is_selected'   => ($row['session_id'] === $selected_info),
	];
}

$total_sess = count($aRows);
$total_cust = $total_sess - $total_dupes - $total_bots - min($total_admin, 1);

// Carrito del seleccionado (panel lateral) - unica query individual
$heading  = [['text' => '<b>' . TABLE_HEADING_SHOPPING_CART . '</b>']];
$contents = [];
if ($selected_info !== null) {
	$contents = getCartContents($selected_info, $currencies);
}

// Botones cabecera
$aButtons = [];

// Renderizar template
$sHtmlModule = includeTemplate($sPathTemplate . '/index.php');

// Pintar
echo includeTemplate($sPathTemplate . '/base.php');
?>
