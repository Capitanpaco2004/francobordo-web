<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2010 osCommerce

  Released under the GNU General Public License
*/

require('includes/application_top.php');

/* #FB-SEQURA-TRIGGER --------------------------------------------------------
   Gate de acceso. Antes NO habia ninguno (ni siquiera allowedIp()) y este
   endpoint marca pedidos como reportados a SeQura: sent_to_sequra=1 es
   IRREVERSIBLE (no se vuelven a reportar) y escribe historial 307.

   OJO, no lo llama ningun cron nuestro: `crontab -l` no menciona sequra.
   Lo invoca SEQURA a diario entre las 02:30 y 02:40, User-Agent "SeQura",
   desde 3 EIP fijas de AWS eu-west-1. La URL esta configurada en el panel de
   SeQura, no aqui, asi que exigir SOLO token mataria el reporte de envios
   (29 pedidos informados en 90 dias) y con el la facturacion a SeQura.
   Por eso: token (patron de la casa, hash_equals) O IP de la allowlist.
   FASE 2, cuando SeQura anada el token a la URL de su panel: borrar la rama
   de IP y dejar el token como unica via.

   NO usar MODULE_PAYMENT_SEQURA_IPS para esto: esa clave alimenta check() de
   los modulos de pago y rellenarla ESCONDE SeQura del checkout a todos los
   clientes (64.696 EUR/ano).
   ------------------------------------------------------------------------ */
$fb_trigger_ips = array('52.211.243.177', '34.253.159.179', '34.252.147.155');
/* Solo REMOTE_ADDR: X-Forwarded-For lo puede falsear el cliente. */
$fb_ip  = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
$fb_ok  = in_array($fb_ip, $fb_trigger_ips, true);

/* Token en fichero FUERA del docroot: el docroot se espeja a GitHub.
   Mismo sitio y mismos permisos (0600) que .api-tokens / .api-stock-sync-key. */
if (!$fb_ok) {
	$fb_token_file = '/home/francobordo/.sequra-trigger-key';
	if (is_readable($fb_token_file)) {
		$fb_expected = trim((string)file_get_contents($fb_token_file));
		$fb_given    = isset($_GET['token']) && is_string($_GET['token']) ? $_GET['token'] : '';
		if ($fb_expected !== '' && hash_equals($fb_expected, $fb_given)) {
			$fb_ok = true;
		}
	}
}

if (!$fb_ok) {
	error_log('SEQURA-TRIGGER: rechazado ip=' . $fb_ip . ' ua='
		. (isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 80) : '?'));
	http_response_code(403);
	exit('Forbidden');
}
require(DIR_WS_CLASSES . 'order.php');
require_once(DIR_FS_CATALOG.DIR_WS_CLASSES . 'payment.php');
if (!defined('DIR_FS_SEQURA')) {
	define('DIR_FS_SEQURA', DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/SeQura/');
}
$charset = strtolower(CHARSET);
define('ISUTF8', $charset == 'utf8' || $charset == 'utf-8');

include_once(DIR_FS_CATALOG . 'includes/compat/compatibility_functions.php');
require_once(DIR_FS_SEQURA . 'SequraHelper.php');

$builder = SequraHelper::getBuilder();
$builder->buildDeliveryReport();
$client = SequraHelper::getClient();
$client->sendDeliveryReport($builder->getDeliveryReport());
$status= $client->getStatus();
if ( $status == 204) {
	$builder->setOrdersAsShipped();
	die('ok');
} elseif ($status >= 200 && $status <= 299 || $status == 409) {
  http_response_code(599);
	$x = json_decode($client->result, true); // return array, not object
	die('ko');
}
require(DIR_WS_INCLUDES . 'application_bottom.php');

