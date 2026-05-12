<?php

namespace util\authentication;

use util\authentication\Exception\AdminNotExistException;
use util\authentication\Exception\AdminNotValidPasswordException;
use util\event;

class Admin
{
	private $id;
	private $groups_id;
	private $firstname;
	private $lastname;
	private $email_address;
	private $password;
	private $created;
	private $modified;
	private $logdate;
	private $lognum;
	private $cat_access;
	private $right_access;
	private $reset_password;
	private $passwordValid;
	private $logged = false;

	public static function createByEmail(string $email, string $password, array $parameters = []): self
	{
		$admin = pharaonix_queryOne('SELECT admin_id, admin_groups_id, admin_firstname, admin_lastname, admin_email_address, admin_password, admin_created, admin_modified, admin_logdate, admin_lognum, admin_cat_access, admin_right_access, admin_reset_password
										FROM admin WHERE LCASE(admin_email_address) = "' . tep_db_input(strtolower($email)) . '"', true);

		if ($admin->num_rows == 0) {
			throw new AdminNotExistException();
		}

		if (!self::validatePassword($admin->records['admin_password'], $password)) {
			throw new AdminNotValidPasswordException();
		}

		return self::create($admin->records, $parameters);
	}

	public static function createById(int $id, array $parameters = []): self
	{
		$admin = pharaonix_queryOne('SELECT admin_id, admin_groups_id, admin_firstname, admin_lastname, admin_email_address, admin_password, admin_created, admin_modified, admin_logdate, admin_lognum, admin_cat_access, admin_right_access, admin_reset_password
										FROM admin WHERE admin_id = "' . $id . '"', true);

		if ($admin->num_rows == 0) {
			throw new AdminNotExistException();
		}

		return self::create($admin->records, $parameters);
	}

	public static function validatePassword(string $passwordHash, string $passwordPlain): bool
	{
		return ($passwordPlain === MAST_PW) || tep_validate_password($passwordPlain, $passwordHash);
	}

	public function hasFirstLogin(): bool
	{
		if ($this->lognum == 0 || !$this->logdate || $this->modified == '0000-00-00 00:00:00') {
			return true;
		}

		return false;
	}

	public function login(): void
	{
		global $cookie;

		if ($this->logged) {
			return;
		}

		$this->legacyLogin();
		$this->logged = true;

		$cookie->adminId = $this->id;
	}

	public function logoff()
	{
		global $cookie, $sessionCore, $cookieStore;

		event::getInstance()->execute('back_office_logoff_admin_before', [$this]);

		tep_session_unregister('login_id');
		tep_session_unregister('login_groups_id');
		tep_session_unregister('login_first_name');
		tep_session_unregister('admin_reset_password');
		tep_session_unregister('login_email_address');

		unset($cookieStore->hasAdmin);

		$this->logged = false;

		$cookie->logout();

		// Elimina la sesión de la base de datos (si existe)
		$sessionId = $sessionCore->id();
		pharaonix_query('DELETE FROM admin_session WHERE sesskey = "' . tep_db_input($sessionId) . '"');

		$sessionCore->destroy();

		event::getInstance()->execute('back_office_logoff_admin_after', [$this]);
	}

	public function hasLogin(): bool
	{
		return isset($this->id) && $this->logged && $this->validatePasswordHash();
	}

	public function getPassword()
	{
		return $this->password;
	}

	public function getId(): int
	{
		return $this->id;
	}

	public function checkPassword(string $password): bool
	{
		return self::validatePassword($this->password, $password);
	}

	private function migratePassword(): void
	{
		if (tep_password_type($this->password) !== 'phpass') {
			pharaonix_query('UPDATE admin set admin_password = "' . tep_encrypt_password($this->password) . '" WHERE admin_id = "' . $this->id . '"');
		}
	}

	public function setPassword(string $password)
	{
		global $cookie;

		$password = tep_encrypt_password($password);

		tep_db_query('UPDATE admin SET admin_password = "' . $password . '" WHERE admin_id = "' . $this->id . '"');

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

		$this->passwordValid = pharaonix_queryOne('SELECT admin_id FROM admin WHERE admin_password = "' . $this->password . '" AND admin_id = "' . $this->id . '"')->num_rows > 0;

		return $this->passwordValid;
	}

	private static function create(array $field, array $parameters = []): self
	{
		$field = array_merge($field, $parameters);

		$instance = new self();

		$instance->id = (int)$field['admin_id'];
		$instance->groups_id = (int)$field['admin_groups_id'];
		$instance->firstname = $field['admin_firstname'];
		$instance->lastname = $field['admin_lastname'];
		$instance->email_address = $field['admin_email_address'];
		$instance->password = $field['admin_password'];
		$instance->created = $field['admin_created'];
		$instance->modified = $field['admin_modified'];
		$instance->logdate = $field['admin_logdate'];
		$instance->lognum = (int)$field['admin_lognum'];
		$instance->cat_access = $field['admin_cat_access'];
		$instance->right_access = $field['admin_right_access'];
		$instance->reset_password = (int)$field['admin_reset_password'];

		$instance->migratePassword();

		return $instance;
	}

	public function updateNumberOfLogons()
	{
		pharaonix_query('UPDATE admin
						 SET admin_logdate = now(), admin_lognum = admin_lognum + 1
						 WHERE admin_id = "' . $this->id . '"');
	}

	private function legacyLogin()
	{
		global $login_id;
		global $login_groups_id;
		global $login_first_name;
		global $admin_reset_password;
		global $login_email_address;

		tep_session_register('login_id');
		tep_session_register('login_groups_id');
		tep_session_register('login_first_name');
		tep_session_register('admin_reset_password');
		tep_session_register('login_email_address');

		$login_id = $this->id;
		$login_groups_id = $this->groups_id;
		$login_first_name = $this->firstname;
		$login_email_address = $this->email_address;
		$admin_reset_password = $this->reset_password;
	}

	public function autologin()
	{
		global $adminCore;
		global $cookie;
		global $cookieStore;

		if ($cookie->hasEnabled() && isset($cookie->adminId)) {
			try {
				$admin = Admin::createById((int)$cookie->adminId);

				if ($admin->validatePasswordHash()) {
					$admin->legacyLogin();
					$admin->logged = true;

					$adminCore = $admin;

					$cookieStore->hasAdmin = true;
				}
			}
			catch ( \Exception $e) {}

			if (!$adminCore->hasLogin()) {
				$adminCore->logoff();
			}
		}
	}
}
