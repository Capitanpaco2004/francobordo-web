<?php

use util\authentication\Exception\CustomerNotApprovedException;
use util\authentication\Exception\CustomerNotExistException;
use util\authentication\Exception\CustomerNotValidPasswordException;
use util\authentication\Sessions\Sessions;
use util\authentication\Customer;
use util\event;
use util\template;

include 'includes/application_top.php';
include DIR_WS_LANGUAGES . $language . '/' . FILENAME_LOGIN;

// "Conectar como cliente" desde el admin: forzamos logoff previo para que
// siempre logue al cliente del POST actual, no al que hubiese en sesión.
// Evita que hacer 2 clicks seguidos en distintos clientes conecte al primero.
if (isset($_POST['mast_connect']) && $_POST['mast_connect'] == '1' && $customerCore->hasLogin()) {
	$customerCore->logoff();
	$cart->reset();
}
// Si estamos logueados
if ($customerCore->hasLogin()) {
	tep_redirect(tep_href_link('account/account.php'));
}

// Redirigir al cliente a una página amigable que debe habilitar las cookies si las cookies están deshabilitadas o la sesión no ha comenzado
if (!$sessionCore->hasStarted()) {
	tep_redirect(tep_href_link(FILENAME_COOKIE_USAGE));
}

$breadcrumb->add(NAVBAR_TITLE, tep_href_link(FILENAME_LOGIN, ''));

if (isset($_GET['action']) && $_GET['action'] == 'process') {
	try {
		$requestPostEmailAddress = tep_db_prepare_input($_POST['email_address'] ?? '');
		$requestPostPassword = tep_db_prepare_input($_POST['password'] ?? '');
		$requestPostSkip = tep_db_prepare_input($_POST['skip'] ?? '');
		$customerParameters = [];

		if (($requestPostNewCustomersGroupId = tep_db_prepare_input($_POST['new_customers_group_id'] ?? false)) !== false){
			// A2 (escalada de privilegios): solo se permite forzar el grupo de cliente en el
			// login SPPC (cuenta selector SPPC_TOGGLE_LOGIN_PASSWORD = info@denox.es) o cuando
			// el valor es 0 (retail, que nunca es una escalada; lo usa el "conectar como
			// cliente" de order_edit). Antes, CUALQUIER cliente podia enviar
			// new_customers_group_id=1 y loguearse como Profesionales con sus precios/IVA.
			if ((int)$requestPostNewCustomersGroupId === 0
				|| strtolower($requestPostEmailAddress) === strtolower(SPPC_TOGGLE_LOGIN_PASSWORD)) {
				$customerParameters['customers_group_id'] = (int)$requestPostNewCustomersGroupId;
			}
		}

		$customer = Customer::createByEmail($requestPostEmailAddress, $requestPostPassword, $customerParameters);

		// Comprobamos si el cliente esta desactivado
		if ($customer->getStatusDisabled() == 1) {
			if (!array_key_exists('skip_disabled', $_POST)) {
				echo $rgpd->loginExecute();
			}

			// Si hemos llegado aqui entonces actualizamos log y restauramos el cliente
			$rgpd->restoreCustomer($customer->getId());
		}

		// Login especial para especificar grupo de cliente
		if ($customer->getEmail() == SPPC_TOGGLE_LOGIN_PASSWORD && $requestPostSkip !== 'true') {
			$customersGroups = pharaonix_getArrayAssociativeSql('SELECT customers_group_id, customers_group_name FROM customers_groups ORDER BY customers_group_id', 'customers_group_id', 'customers_group_name');

			echo template::getInstance()->includeTemplate('theme/web/html/templates/login_choose_customer_group.php', [
					'customersGroups' => $customersGroups,
					'baseHref' => (($request_type == 'SSL') ? HTTPS_SERVER : HTTP_SERVER) . DIR_WS_CATALOG,
					'customer' => $customer,
					'password' => $requestPostPassword
			]);

			exit();
		}

		$customer->login();
		$customer->updateNumberOfLogons();
		$cookie->password = $customer->getPassword();

		// --- SalesManago — set smclient cookie (sync, before redirect) ---
		try {
			if (defined('SALESMANAGO_STATUS') && SALESMANAGO_STATUS === 'true') {
				require_once DIR_FS_CATALOG . 'includes/classes/SalesManagoQueue.php';
				SalesManagoQueue::setIdentityCookie((int) $customer->getId());
			}
		} catch (\Throwable $_smE) { @error_log('SM login cookie: ' . $_smE->getMessage()); }

		tep_session_register('loginSuccessEvent');
		$redirectUrl = $cart->count_contents() < 1 ? $navigation->get_last_page() : FILENAME_CHECKOUT_SHIPPING;

		if (sizeof($navigation->snapshot) > 0) {
			$redirectUrl = tep_href_link($navigation->snapshot['page'], tep_array_to_string($navigation->snapshot['get'], array(tep_session_name())), $navigation->snapshot['mode']);
			$navigation->clear_snapshot();
		}

		// "Conectar como cliente" desde el admin: ignoramos snapshot/last_page
		// (que podían apuntar al admin) y forzamos ir a la cuenta del cliente.
		if (isset($_POST['mast_connect']) && $_POST['mast_connect'] == '1') {
			$redirectUrl = tep_href_link('account/account.php');
		}
		tep_redirect($redirectUrl);
	} catch (CustomerNotValidPasswordException | CustomerNotExistException $e) {
		$messageStack->add('login', TEXT_LOGIN_ERROR, 'error', true);
	} catch (CustomerNotApprovedException $e) {
		$messageStack->add('login', TEXT_NOT_APPROVED, 'error', true);
	}
}

include DIR_THEME_ROOT . 'html/templates/' . basename(__FILE__);

