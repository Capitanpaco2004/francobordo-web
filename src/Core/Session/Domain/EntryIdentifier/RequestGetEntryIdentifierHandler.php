<?php

namespace Oscdenox\Core\Session\Domain\EntryIdentifier;

use Oscdenox\Adapter\Configuration\Configuration;

class RequestGetEntryIdentifierHandler extends AbstractEntryIdentifierHandler
{
	private $sessionName;
	private $configuration;

	public function __construct(string $sessionName, Configuration $configuration)
	{
		$this->sessionName = $sessionName;
		$this->configuration = $configuration;
	}

	public function id(): ?string
	{
		if (isset($_GET[$this->sessionName]) && $this->configuration->get('request_type')) {
			return $_GET[$this->sessionName];
		}

		return parent::id();
	}

	public function sane(): ?bool
	{
		if (!preg_match('/checkout_|getcart|checkout|sequrapayment|ipn-pay-with-sequra|ipn-sequra|checkout_process_sequra/i', $_SERVER['SCRIPT_NAME']) && is_array($_GET) && isset($_GET[$this->sessionName]) && !isset($_GET['curl_oe'])) {
			return true;
		}

		return parent::sane();
	}
}
