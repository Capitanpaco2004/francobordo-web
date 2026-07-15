<?php
/**
 * Vinculación de tickets de Kayako a pedidos (caja "Tickets Kayako" de orders.php).
 *
 * Tabla local: orders_kayako_tickets (orders_id + ticket_mask únicos).
 * Lookup remoto: fb_ticket_lookup.php en soporte.francobordo.com (POST + token,
 * allowlist de IP en el propio endpoint). Si Kayako no responde (línea de la
 * oficina caída), la vinculación manual sigue funcionando.
 *
 * 2026-07-14
 */

define('FB_KAYAKO_LOOKUP_URL', 'https://soporte.francobordo.com/fb_ticket_lookup.php');
define('FB_KAYAKO_CREATE_URL', 'https://soporte.francobordo.com/fb_ticket_create.php');
define('FB_KAYAKO_LOOKUP_TOKEN', '07695f6ad31a105652e9ceb682d60aaddb456dd541d6ab5268ac70a9f29e1073');

// Departamentos seleccionables al crear ticket (swdepartments de Kayako, app tickets).
// Si se crean/renombran departamentos en Kayako hay que tocar esta lista a mano.
function fb_kayako_departments() {
	return array(
		1 => 'Atención al Cliente',
		3 => 'Soporte Técnico',
		4 => 'RMA',
		5 => 'Comercial',
		6 => 'Contabilidad',
		7 => 'Compras',
		9 => 'Tienda',
	);
}

function fb_kayako_valid_mask($sMask) {
	return (bool)preg_match('/^[A-Z]{3}-\d{3}-\d{4,6}$/', (string)$sMask);
}

function fb_kayako_staff_url($sMask) {
	return 'https://soporte.francobordo.com/staff/index.php?/Tickets/Ticket/View/' . rawurlencode((string)$sMask) . '/inbox/-1/-1/-1';
}

function fb_kayako_order_tickets($iOrderId) {
	$aOut = array();
	$oQuery = tep_db_query('select id, ticket_mask, subject, date_added, added_by from orders_kayako_tickets where orders_id = ' . (int)$iOrderId . ' order by date_added asc, id asc');
	while ($aRow = tep_db_fetch_array($oQuery)) {
		$aOut[] = $aRow;
	}
	return $aOut;
}

/**
 * Llama al lookup remoto. $aParams: array('email' => ...) o array('mask' => ...).
 * Devuelve el array JSON decodificado o null si no hubo respuesta válida.
 * Timeouts cortos: el admin no debe quedarse colgado si la oficina está caída.
 */
function fb_kayako_lookup($aParams) {
	$aParams['token'] = FB_KAYAKO_LOOKUP_TOKEN;
	$oCurl = curl_init(FB_KAYAKO_LOOKUP_URL);
	curl_setopt_array($oCurl, array(
		CURLOPT_POST           => true,
		CURLOPT_POSTFIELDS     => http_build_query($aParams),
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CONNECTTIMEOUT => 4,
		CURLOPT_TIMEOUT        => 8,
	));
	$sBody = curl_exec($oCurl);
	// Sin curl_close(): deprecado en PHP 8.5 (sin efecto desde 8.0) y el aviso
	// con display_errors rompe el JSON de kayako_ticket_search.
	if (!is_string($sBody) || $sBody === '') {
		return null;
	}
	$aJson = json_decode($sBody, true);
	return is_array($aJson) ? $aJson : null;
}

/**
 * Crea un ticket en Kayako (fb_ticket_create.php del .50) sin enviar email al
 * cliente. $aParams: email, fullname, subject, order_id, department_id,
 * staff_email. Devuelve el array JSON ({ok, mask, ticketid} | {ok:false,
 * error}) o null si no hubo respuesta. Timeout mayor que el lookup: el
 * bootstrap de SWIFT + creación tarda 1-3 s.
 */
function fb_kayako_create_ticket($aParams) {
	$aParams['token'] = FB_KAYAKO_LOOKUP_TOKEN;
	$oCurl = curl_init(FB_KAYAKO_CREATE_URL);
	curl_setopt_array($oCurl, array(
		CURLOPT_POST           => true,
		CURLOPT_POSTFIELDS     => http_build_query($aParams),
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CONNECTTIMEOUT => 5,
		CURLOPT_TIMEOUT        => 25,
	));
	$sBody = curl_exec($oCurl);
	if (!is_string($sBody) || $sBody === '') {
		return null;
	}
	$aJson = json_decode($sBody, true);
	return is_array($aJson) ? $aJson : null;
}
