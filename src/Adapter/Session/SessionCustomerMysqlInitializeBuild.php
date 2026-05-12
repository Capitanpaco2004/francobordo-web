<?php

namespace Oscdenox\Adapter\Session;

use Oscdenox\Core\Session\Application\Initialize\SessionCustomerMysqlInitialize;
use Oscdenox\Core\Session\Application\Initialize\SessionCustomerMysqlInitializeCommand;
use Oscdenox\Core\Session\Application\Initialize\SessionCustomerMysqlInitializeCommandHandler;
use Oscdenox\Core\Session\Domain\SessionHandler;
use Oscdenox\Core\Session\Infrastructure\Session\SessionCustomerInFileTemp;
use Oscdenox\Core\Session\Infrastructure\Session\SessionCustomerMysql;

final class SessionCustomerMysqlInitializeBuild
{
	public function __invoke(): SessionHandler
	{
		global $cookie;
		global $configurationAdapter;

		$sessionCustomerMysqlInitialize = new SessionCustomerMysqlInitialize();
		$sessionCustomer = isset($_GET['curl_oe']) ? new SessionCustomerInFileTemp($cookie) : new SessionCustomerMysql($cookie);

		$sessionCustomerMysqlInitializeCommandHandler = new SessionCustomerMysqlInitializeCommandHandler($sessionCustomerMysqlInitialize, $sessionCustomer, $configurationAdapter, $cookie);
		$sessionCustomerMysqlInitializeCommand = new SessionCustomerMysqlInitializeCommand('osCsid', $cookie->expire(), $cookie->token, $configurationAdapter->get('SESSION_BLOCK_SPIDERS'));

		return ($sessionCustomerMysqlInitializeCommandHandler)($sessionCustomerMysqlInitializeCommand);
	}
}
