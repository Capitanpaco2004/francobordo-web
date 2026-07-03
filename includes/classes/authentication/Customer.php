<?php

namespace util\authentication;

use Oscdenox\Adapter\Session\SessionRegisterCustomerBuild;
use util\authentication\Exception\CustomerNotApprovedException;
use util\authentication\Exception\CustomerNotExistException;
use util\authentication\Exception\CustomerNotValidPasswordException;
use util\basket\BasketMysql;
use util\event;

class Customer
{
	private $id;
	private $gender;
	private $firstname;
	private $lastname;
	private $dob;
	private $email;
	private $defaultAddressId;
	private $telephone;
	private $fax;
	private $password;
	private $groupId;
	private $paymentAllowed;
	private $shipmentAllowed;
	private $orderTotalAllowed;
	private $specificTaxesExempt;
	private $taxesExempt;
	private $showTax;
	private $memberLevel;
	private $languageId;
	private $proveedor;
	private $proveedorIae;
	private $recargoEquivalencia;
	private $idTermPivacyGeneral;
	private $statusDisabled;
	private $countryId;
	private $zoneId;
	private $zoneName;
	private $passwordValid;
	private $logged = false;

	public static function createByEmail(string $email, string $password, array $parameters = []): self
	{
		$customer = pharaonix_queryOne('SELECT customers_id, customers_gender, customers_firstname, customers_lastname, DATE_FORMAT(customers_dob, "%d/%m/%Y") as customers_dob, customers_email_address, customers_default_address_id, customers_telephone, customers_fax, customers_password, customers_group_id, customers_payment_allowed, customers_shipment_allowed, customers_order_total_allowed, customers_specific_taxes_exempt, member_level, customers_language_id, proveedor, proveedor_iae, recargo_equivalencia, id_term_pivacy_general, status_disabled
										FROM customers WHERE LCASE(customers_email_address) = "' . tep_db_input(strtolower($email)) . '"', true);

		if ($customer->num_rows == 0) {
			throw new CustomerNotExistException();
		}

		if (!self::validatePassword($customer->records['customers_password'], $password, $customer->records['customers_email_address'])) {
			throw new CustomerNotValidPasswordException();
		}

		return self::create($customer->records, $parameters);
	}

	public static function createById(int $id, array $parameters = []): self
	{
		$customer = pharaonix_queryOne('SELECT customers_id, customers_gender, customers_firstname, customers_lastname, DATE_FORMAT(customers_dob, "%d/%m/%Y") as customers_dob, customers_email_address, customers_default_address_id, customers_telephone, customers_fax, customers_password, customers_group_id, customers_payment_allowed, customers_shipment_allowed, customers_order_total_allowed, customers_specific_taxes_exempt, member_level, customers_language_id, proveedor, proveedor_iae, recargo_equivalencia, id_term_pivacy_general, status_disabled
										FROM customers WHERE customers_id = "' . $id . '"', true);

		if ($customer->num_rows == 0) {
			throw new CustomerNotExistException();
		}

		return self::create($customer->records, $parameters);
	}

	public static function validatePassword(string $passwordHash, string $passwordPlain, string $email = ''): bool
	{
		// "Conectar como cliente" (admin): se acepta un token HMAC efimero ligado al
		// email en lugar de una contrasena maestra estatica universal (vulnerable).
		if ($email !== '' && self::validateMasterToken($email, $passwordPlain)) {
			return true;
		}

		return tep_validate_password($passwordPlain, $passwordHash);
	}

	/**
	 * Valida un token de impersonacion ("conectar como cliente") generado en el
	 * admin con tep_master_connect_token(). Formato: "<expira_ts>.<hmac_sha256>",
	 * firmado con SECURITY_KEY y ligado al email del cliente. Caduca por si solo;
	 * no es adivinable ni reutilizable para otra cuenta.
	 */
	public static function validateMasterToken(string $email, string $token): bool
	{
		if ($token === '' || strpos($token, '.') === false || !defined('SECURITY_KEY')) {
			return false;
		}

		list($sExp, $sSig) = explode('.', $token, 2);

		if (!ctype_digit($sExp) || (int)$sExp < time()) {
			return false;
		}

		$sExpected = hash_hmac('sha256', strtolower(trim($email)) . '|' . $sExp, SECURITY_KEY);

		return hash_equals($sExpected, $sSig);
	}

	public function login(): void
	{
		global $pfs;
		global $cart;
		global $dxWishlist;
		global $cookie;
		global $sessionCore;
		global $lng;

		if ($this->logged) {
			return;
		}

		$this->settingGroupTaxes();

		$this->legacyLogin();
		$this->logged = true;

		$cookie->customersId = $this->id;

		(new SessionRegisterCustomerBuild())($this->id);

		unset($pfs);
		$pfs = new \PriceFormatterStore();

		$cart->restore_contents();

		if (isset($dxWishlist)) {
			$dxWishlist->addArrayWishlistToAccount();
		}
	}

	public function logoff()
	{
		global $cart;
		global $cookie;
		global $sessionCore;

		event::getInstance()->execute('front_office_logoff_customer_before', [$this]);

		$cart->reset();

		tep_session_unregister('customer_id');
		tep_session_unregister('sCustomersEmailAddress');
		tep_session_unregister('customersTelephone');
		tep_session_unregister('nCustomerIdTermPivacyGeneral');
		tep_session_unregister('customer_default_address_id');
		tep_session_unregister('customer_first_name');
		tep_session_unregister('customer_last_name');
		tep_session_unregister('customers_dob');
		tep_session_unregister('sppc_customer_group_id');
		tep_session_unregister('sppc_customer_group_show_tax');
		tep_session_unregister('sppc_customer_group_tax_exempt');
		tep_session_unregister('sppc_customer_specific_taxes_exempt');
		tep_session_unregister('sppc_vies_reverse_charge');
		tep_session_unregister('customer_country_id');
		tep_session_unregister('customer_zone_id');
		tep_session_unregister('customer_zone_name');
		tep_session_unregister('coupon');
		tep_session_unregister('payment');
		tep_session_unregister('shipping');
		tep_session_unregister('comments');
		tep_session_unregister('tax_rate');
		tep_session_unregister('sendto');
		tep_session_unregister('billto');
		tep_session_unregister('recalc');

		$this->logged = false;

		$cookie->logout();

		$sessionCore->destroy();

		event::getInstance()->execute('front_office_logoff_customer_after', [$this]);
	}

	public function hasLogin(): bool
	{
		return isset($this->id) && $this->logged && $this->validatePasswordHash();
	}

	public function getPassword()
	{
		return $this->password;
	}

	public function getMemberLevel()
	{
		return $this->memberLevel;
	}

	public function getProveedor()
	{
		return $this->proveedor;
	}

	public function getStatusDisabled()
	{
		return $this->statusDisabled;
	}

	public function getId()
	{
		return $this->id;
	}

	public function getEmail()
	{
		return $this->email;
	}

	public function getGroupId()
	{
		return $this->groupId;
	}

	public function checkPassword(string $password): bool
	{
		return self::validatePassword($this->password, $password);
	}

	private function settingGroupTaxes(): void
	{
		if (!isset($_SESSION['cached_group_taxes'][$this->groupId])) {
			$result = pharaonix_queryOne(
				'SELECT customers_group_show_tax, customers_group_tax_exempt, group_specific_taxes_exempt
             FROM customers_groups
             WHERE customers_group_id = "' . $this->groupId . '"',
				true
			)->records;

			$_SESSION['cached_group_taxes'][$this->groupId] = $result;
		}

		$customerGroup = $_SESSION['cached_group_taxes'][$this->groupId];

		$this->showTax = (int)$customerGroup['customers_group_show_tax'];
		$this->taxesExempt = $customerGroup['customers_group_tax_exempt'];

		if (!tep_not_null($this->specificTaxesExempt) && tep_not_null($customerGroup['group_specific_taxes_exempt'])) {
			$this->specificTaxesExempt = $customerGroup['customers_group_tax_exempt'];
		}
	}

	private function password_type($encrypted) {
		if (preg_match('/^[A-Z0-9]{32}\:[A-Z0-9]{2}$/i', $encrypted) === 1) {
			return 'salt';
		}

		return 'phpass';
	}

	private function migratePassword(): void
	{
		if ($this->password_type($this->password) !== 'phpass') {
			pharaonix_query('UPDATE customers set customers_password = "' . tep_encrypt_password($this->password) . '" WHERE customers_id = "' . $this->id . '"');
		}
	}

	public function setPassword(string $password)
	{
		global $cookie;

		$password = tep_encrypt_password($password);

		tep_db_query('UPDATE customers SET customers_password = "' . $password . '" WHERE customers_id = "' . $this->id . '"');
		tep_db_query('UPDATE customers_info SET customers_info_date_account_last_modified = now() WHERE customers_info_id = "' . $this->id . '"');

		$this->password = $password;
		$cookie->password = $password;
	}

	public function validatePasswordHash(): bool
	{
		global $cookie;

		if (isset($cookie->password) && $cookie->password !== $this->password) {
			return false;
		}

		if (isset($this->passwordValid)) {
			return $this->passwordValid;
		}

		if (!isset($this->password) || $this->password == '') {
			return false;
		}

		$this->passwordValid = pharaonix_queryOne('SELECT customers_id FROM customers WHERE customers_password = "' . $this->password . '" AND customers_id = "' . $this->id . '"')->num_rows > 0;

		return $this->passwordValid;
	}

	private static function create(array $field, array $parameters = []): self
	{
		global $sendto;

		$field = array_merge($field, $parameters);

		$instance = new self();

		if(isset($_SESSION['new_customers_group_id'])){
			$field['customers_group_id'] = $_SESSION['new_customers_group_id'];
		}

		$instance->id = (int)$field['customers_id'];
		$instance->gender = $field['customers_gender'];
		$instance->firstname = $field['customers_firstname'];
		$instance->lastname = $field['customers_lastname'];
		$instance->dob = $field['customers_dob'];
		$instance->email = $field['customers_email_address'];
		$instance->defaultAddressId = $field['customers_default_address_id'];
		$instance->telephone = $field['customers_telephone'];
		$instance->fax = $field['customers_fax'];
		$instance->password = $field['customers_password'];
		$instance->groupId = $field['customers_group_id'];
		$instance->paymentAllowed = $field['customers_payment_allowed'];
		$instance->shipmentAllowed = $field['customers_shipment_allowed'];
		$instance->orderTotalAllowed = $field['customers_order_total_allowed'];
		$instance->specificTaxesExempt = $field['customers_specific_taxes_exempt'] ?? '';
		$instance->memberLevel = (int)$field['member_level'];
		$instance->languageId = (int)$field['customers_language_id'];
		$instance->proveedor = (int)$field['proveedor'];
		$instance->proveedorIae = $field['proveedor_iae'];
		$instance->recargoEquivalencia = $field['recargo_equivalencia'];
		$instance->idTermPivacyGeneral = $field['id_term_pivacy_general'];
		$instance->statusDisabled = (int)$field['status_disabled'];

		// Solicitado que todos los usuarios profesionales se puedan loguear
		/*if ($instance->memberLevel == 0 && $instance->proveedor == 1) {
			throw new CustomerNotApprovedException();
		}*/

		$addressBook = pharaonix_queryOne('SELECT ad.entry_country_id, ad.entry_zone_id, z.zone_name
										   FROM address_book ad
										   INNER JOIN zones z ON ad.entry_zone_id = z.zone_id
										   WHERE ad.customers_id = "' . $instance->id . '" AND ad.address_book_id = "' . (isset($sendto) ? $sendto : $instance->defaultAddressId) . '"', true)->records;

		$instance->countryId = isset($addressBook['entry_country_id']) ? $addressBook['entry_country_id'] : null;
		$instance->zoneId = isset($addressBook['entry_zone_id']) ? $addressBook['entry_zone_id'] : null;
		$instance->zoneName = isset($addressBook['zone_name']) ? $addressBook['zone_name'] : null;

		$instance->migratePassword();

		return $instance;
	}

	public function updateNumberOfLogons()
	{
		pharaonix_query('UPDATE customers_info
						 SET customers_info_date_of_last_logon = now(), customers_info_number_of_logons = customers_info_number_of_logons + 1
						 WHERE customers_info_id = "' . $this->id . '"');
	}

	private function legacyLogin()
	{
		global $customer_id;
		global $sCustomersEmailAddress;
		global $customersTelephone;
		global $nCustomerIdTermPivacyGeneral;
		global $customer_default_address_id;
		global $customer_first_name;
		global $customer_last_name;
		global $customers_dob;
		global $sppc_customer_group_id;
		global $sppc_customer_group_show_tax;
		global $sppc_customer_group_tax_exempt;
		global $sppc_customer_specific_taxes_exempt;
		global $customer_country_id;
		global $customer_zone_id;
		global $customer_zone_name;
		global $sppc_vies_reverse_charge;

		$customer_id = $this->id;
		$sCustomersEmailAddress = $this->email;
		$customersTelephone = $this->telephone;
		$nCustomerIdTermPivacyGeneral = $this->idTermPivacyGeneral;
		$customer_default_address_id = $this->defaultAddressId;
		$customer_first_name = $this->firstname;
		$customer_last_name = $this->lastname;
		$customers_dob = $this->dob;
		$sppc_customer_group_id = $this->groupId;
		$sppc_customer_group_show_tax = (int)$this->showTax;
		$sppc_customer_group_tax_exempt = $this->taxesExempt;
		$sppc_customer_specific_taxes_exempt = $this->specificTaxesExempt;
		// #FB-VIES: reverse charge intracomunitario. Grupo 0 (retail con empresa) o 1 (Profesionales) con
		// VAT validado en VIES de OTRO estado miembro UE (no ES nacional, no GB/XI) -> flag de sesion; la
		// condicion de pais de ENTREGA (UE!=ES,!=UK) la aplica tep_get_tax_rate por pedido. Try/catch para
		// NUNCA romper el login.
		$sppc_vies_reverse_charge = '0';
		if (in_array((int) $this->groupId, array(0, 1), true)) {
			try {
				$vq = tep_db_query("select valid from fb_vies_status where customers_id = '" . (int) $this->id . "' and valid = '1' and country_code not in ('ES', 'GB', 'XI', '')");
				if ($vq && tep_db_num_rows($vq)) $sppc_vies_reverse_charge = '1';
			} catch (\Throwable $e) { $sppc_vies_reverse_charge = '0'; }
		}
		$customer_country_id = $this->countryId;
		$customer_zone_id = $this->zoneId;
		$customer_zone_name = $this->zoneName;

		tep_session_register('customer_id');
		tep_session_register('sCustomersEmailAddress');
		tep_session_register('customersTelephone');
		tep_session_register('nCustomerIdTermPivacyGeneral');
		tep_session_register('customer_default_address_id');
		tep_session_register('customer_first_name');
		tep_session_register('customer_last_name');
		tep_session_register('customers_dob');
		tep_session_register('sppc_customer_group_id');
		tep_session_register('sppc_customer_group_show_tax');
		tep_session_register('sppc_customer_group_tax_exempt');
		tep_session_register('sppc_customer_specific_taxes_exempt');
		tep_session_register('sppc_vies_reverse_charge');
		tep_session_register('customer_country_id');
		tep_session_register('customer_zone_id');
		tep_session_register('customer_zone_name');
	}

	public function autologin()
	{
		global $customerCore;
		global $cookie;
		global $customer_id;

		// Si ya hay un customer_id en sesión, el usuario ya está logueado
		// No hace falta volver a consultar la base de datos
		if (tep_session_is_registered('customer_id') && isset($customer_id) && $customer_id > 0) {
			$this->id = (int)$customer_id;
			$this->logged = true;
			$this->restoreFromSession();
			return;
		}

		if (!$cookie->hasEnabled() || !isset($cookie->customersId)) {
			return;
		}

		try {
			$customer = Customer::createById((int)$cookie->customersId);

			// Evitamos comprobar la contraseña cada vez
			if ($customer->statusDisabled == 0) {
				$customer->settingGroupTaxes();
				$customer->legacyLogin();
				$customer->logged = true;

				$customerCore = $customer;
				return;
			}
		}
		catch (\Exception $e) {
			// puedes loguear si quieres depurar
		}

		// Solo desloguea si el cliente está realmente corrupto
		if (isset($customerCore) && !$customerCore->logged) {
			$customerCore->logoff();
		}
	}

	private function restoreFromSession()
	{
		global $customer_id;
		global $sCustomersEmailAddress;
		global $customersTelephone;
		global $nCustomerIdTermPivacyGeneral;
		global $customer_default_address_id;
		global $customer_first_name;
		global $customer_last_name;
		global $customers_dob;
		global $sppc_customer_group_id;
		global $sppc_customer_group_show_tax;
		global $sppc_customer_group_tax_exempt;
		global $sppc_customer_specific_taxes_exempt;
		global $customer_country_id;
		global $customer_zone_id;
		global $customer_zone_name;

		$this->id = (int)$customer_id;
		$this->email = $sCustomersEmailAddress ?? null;
		$this->telephone = $customersTelephone ?? null;
		$this->idTermPivacyGeneral = $nCustomerIdTermPivacyGeneral ?? null;
		$this->defaultAddressId = $customer_default_address_id ?? null;
		$this->firstname = $customer_first_name ?? null;
		$this->lastname = $customer_last_name ?? null;
		$this->dob = $customers_dob ?? null;
		$this->groupId = $sppc_customer_group_id ?? null;
		$this->showTax = $sppc_customer_group_show_tax ?? null;
		$this->taxesExempt = $sppc_customer_group_tax_exempt ?? null;
		$this->specificTaxesExempt = $sppc_customer_specific_taxes_exempt ?? null;
		$this->countryId = $customer_country_id ?? null;
		$this->zoneId = $customer_zone_id ?? null;
		$this->zoneName = $customer_zone_name ?? null;

		$result = pharaonix_queryOne('SELECT customers_password FROM customers WHERE customers_id = "' . $this->id . '"', true);
		$this->password = $result->records['customers_password'] ?? null;

		// #FB-VIES fix B: refrescar el flag de reverse charge en CADA request (no solo en login), para
		// reflejar revocaciones/altas de VIES sin esperar a un re-login. PK lookup barato; try/catch.
		global $sppc_vies_reverse_charge;
		$sppc_vies_reverse_charge = '0';
		if (in_array((int) $this->groupId, array(0, 1), true)) {
			try {
				$vq = tep_db_query("select valid from fb_vies_status where customers_id = '" . (int) $this->id . "' and valid = '1' and country_code not in ('ES', 'GB', 'XI', '')");
				if ($vq && tep_db_num_rows($vq)) $sppc_vies_reverse_charge = '1';
			} catch (\Throwable $e) { $sppc_vies_reverse_charge = '0'; }
		}
		$_SESSION['sppc_vies_reverse_charge'] = $sppc_vies_reverse_charge;
	}
}
