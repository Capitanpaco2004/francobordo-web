<?php
namespace util\configurations;

class ConfigurationsDataProvider
{
	public function fetchConfigurationData()
	{
		$configurationData = [];
		$configurationQuery = tep_db_query('SELECT configuration_key AS cfgKey, configuration_value AS cfgValue FROM ' . TABLE_CONFIGURATION);

		while ($configuration = tep_db_fetch_array($configurationQuery)) {
			$configValue = $this->is_json($configuration['cfgValue']) ? $configuration['cfgValue'] : addslashes($configuration['cfgValue']);
			$configurationData[] = ['key' => $configuration['cfgKey'], 'value' => $configValue];
		}

		return $configurationData;
	}

	private function is_json($string)
	{
		if ($string == '' || $string == null || $string == NULL || $string == 'null' || $string == 'NULL' || is_numeric($string))
			return false;

		json_decode($string);
		return (json_last_error() == JSON_ERROR_NONE);
	}
}
