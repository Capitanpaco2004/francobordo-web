<?php
if (!defined('_PS_VERSION_')) exit;

class SequraCrontab
{
	static public function calcNextExecutionTime()
	{
		return strtotime("tomorrow") + 3600 * Configuration::get('SEQURA_AUTOCRON_H') + 60 * Configuration::get('SEQURA_AUTOCRON_M');
	}

	static public function poolCron()
	{
		if (
			Configuration::get('SEQURA_AUTOCRON') &&
			strpos($_SERVER['REQUEST_URI'],'triggerreport')===false &&
			strtotime("now") > Configuration::get("SEQURA_AUTOCRON_NEXT")
		) {
			$url = self::getTriggerReportUrl();
			$client = new SequraClient();
			$client->callCron($url);
		}
	}

	static public function getTriggerReportUrl()
	{
		if (_PS_VERSION_ >= 1.5)
			return Context::getContext()->link->getModuleLink('sequrapayment', 'triggerreport');
		return 'http://' . $_SERVER['HTTP_HOST'] . __PS_BASE_URI__ . 'modules/sequrapayment/triggerreport.php';
	}

}
