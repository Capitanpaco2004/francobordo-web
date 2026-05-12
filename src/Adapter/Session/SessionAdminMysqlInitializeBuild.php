<?php

namespace Oscdenox\Adapter\Session;

use Oscdenox\Core\Session\Application\InitializeAdmin\SessionAdminMysqlInitialize;
use Oscdenox\Core\Session\Application\InitializeAdmin\SessionAdminMysqlInitializeCommand;
use Oscdenox\Core\Session\Application\InitializeAdmin\SessionAdminMysqlInitializeCommandHandler;
use Oscdenox\Core\Session\Domain\SessionHandler;
use Oscdenox\Core\Session\Infrastructure\Session\SessionAdminMysql;

class SessionAdminMysqlInitializeBuild
{
	public function __invoke(): SessionHandler
	{
		global $cookie;
		global $configurationAdapter;

		$sessionAdminMysqlInitialize = new SessionAdminMysqlInitialize();
		$sessionAdmin = new SessionAdminMysql($cookie);

		$sessionAdminMysqlInitializeCommandHandler = new SessionAdminMysqlInitializeCommandHandler($sessionAdminMysqlInitialize, $sessionAdmin, $configurationAdapter, $cookie);
		$sessionAdminMysqlInitializeCommand = new SessionAdminMysqlInitializeCommand('osCAdminID', $cookie->expire());

		return ($sessionAdminMysqlInitializeCommandHandler)($sessionAdminMysqlInitializeCommand);
	}
}
