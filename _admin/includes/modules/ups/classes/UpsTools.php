<?php
class Tools {
	public static function getLanguageCode($id_lang){
		if(!$id_lang)
			return;
		 $lng = new language();
		 foreach($lng->catalog_languages as $code => $language){
		 	if($language['id'] == (int) $id_lang)
		 		return $code;
		 }
	}
	
	public static function getCountryCode($id_country){
		if(!$id_country)
			return;
		$country_query = tep_db_query("select countries_iso_code_2 from " . TABLE_COUNTRIES . " WHERE countries_id = ". (int) $id_country);
  		if (tep_db_num_rows($country_query)) {
      		$country = tep_db_fetch_array($country_query);
      		return $country['countries_iso_code_2'];
  		}
	}
	
	public static function getCountryCodeFromCountryName($country_name){
		if(!$country_name)
			return;
		$country_query = tep_db_query("select countries_iso_code_2 from " . TABLE_COUNTRIES . " WHERE countries_name = '". $country_name."'");
  		if (tep_db_num_rows($country_query)) {
      		$country = tep_db_fetch_array($country_query);
      		return $country['countries_iso_code_2'];
  		}
	}
	
	public static function getCountryFromCountryCode($country_code){
		if(!$country_code)
			return;
		$country_query = tep_db_query("select * from " . TABLE_COUNTRIES . " WHERE countries_iso_code_2 = '". $country_code."'");
  		if (tep_db_num_rows($country_query)) {
      		$country = tep_db_fetch_array($country_query);
      		return $country;
  		}
	}
	
	public static function getCountryId($country_code){
		if(!$country_code)
			return;
		$country_query = tep_db_query("select * from " . TABLE_COUNTRIES . " WHERE countries_iso_code_2 = '". $country_code."'");
  		if (tep_db_num_rows($country_query)) {
      		$country = tep_db_fetch_array($country_query);
      		return $country['countries_id'];
  		}
	}
	
	public static function getValue($key, $default_value = false)
	{
		if (!isset($key) || empty($key) || !is_string($key))
			return false;
		$ret = (isset($_POST[$key]) ? $_POST[$key] : (isset($_GET[$key]) ? $_GET[$key] : $default_value));

		if (is_string($ret) === true)
			$ret = urldecode(preg_replace('/((\%5C0+)|(\%00+))/i', '', urlencode($ret)));
		return !is_string($ret)? $ret : stripslashes($ret);
	}
	
	public static function getConfigValue($key)
	{
		if (!isset($key) || empty($key) || !is_string($key))
			return false;
			
		$query = tep_db_query('SELECT configuration_value FROM ' . TABLE_CONFIGURATION . ' WHERE `configuration_key` = "'.$key.'"');
		$value = tep_db_fetch_array($query);
		return stripslashes($value['configuration_value'] ?? '');
	}
	
	public static function updateConfigValue($key, $value)
	{
		if (!isset($value) || !isset($key) || empty($key) || !is_string($key))
			return false;
			
		tep_db_query("update " . TABLE_CONFIGURATION . "  SET configuration_value = '". $value ."' WHERE configuration_key = '".$key."'");
	}
	
	public static function isUpsShippingModuleInstalled(){
		if (defined('MODULE_SHIPPING_INSTALLED') && tep_not_null(MODULE_SHIPPING_INSTALLED)) {
			$modules = explode(';', MODULE_SHIPPING_INSTALLED);
			return in_array('upsshipping.php', $modules);
		}
        return false;       
	}
	
/**
	 * Convert an array to json string
	 *
	 * @param array $data
	 * @return string json
	 */
	public static function jsonEncode($data)
	{
		if (function_exists('json_encode'))
			return json_encode($data);
		else
		{
			include_once(_PS_TOOL_DIR_.'json/json.php');
			$pear_json = new Services_JSON();
			return $pear_json->encode($data);
		}
	}
	
	/**
	 * jsonDecode convert json string to php array / object
	 *
	 * @param string $json
	 * @param boolean $assoc  (since 1.4.2.4) if true, convert to associativ array
	 * @return array
	 */
	public static function jsonDecode($json, $assoc = false)
	{
		if (function_exists('json_decode'))
			return json_decode($json, $assoc);
		else
		{
			include_once(_PS_TOOL_DIR_.'json/json.php');
			$pear_json = new Services_JSON(($assoc) ? SERVICES_JSON_LOOSE_TYPE : 0);
			return $pear_json->decode($json);
		}
	}
}