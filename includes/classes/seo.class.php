<?php

use util\tools;

define('USE_SEO_REDIRECT_DEBUG', 'false');

/**
 * Ultimate SEO URLs Base Class
 *
 * Ultimate SEO URLs offers search engine optimized URLS for osCommerce
 * based applications. Other features include optimized performance and
 * automatic redirect script.
 * @package Ultimate-SEO-URLs
 */
class SEO_URL
{
	/**
	 * $cache is the per page data array that contains all of the previously stripped titles
	 * @var array
	 */
	var $cache;
	/**
	 * $languages_id contains the language_id for this instance
	 * @var integer
	 */
	var $languages_id;

	var $aAllCategories;
	/**
	 * $attributes array contains all the required settings for class
	 * @var array
	 */
	var $attributes = [];
	/**
	 * $base_url is the NONSSL URL for site
	 * @var string
	 */
	var $base_url;
	/**
	 * $base_url_ssl is the secure URL for the site
	 * @var string
	 */
	var $base_url_ssl;
	/**
	 * $performance array contains evaluation metric data
	 * @var array
	 */
	var $performance;
	/**
	 * $timestamp simply holds the temp variable for time calculations
	 * @var float
	 */
	var $timestamp;
	/**
	 * $reg_anchors holds the anchors used by the .htaccess rewrites
	 * @var array
	 */
	var $reg_anchors;
	/**
	 * $cache_query is the resource_id used for database cache logic
	 * @var resource
	 */
	var $cache_query;
	/**
	 * $cache_file is the basename of the cache database entry
	 * @var string
	 */
	var $cache_file;
	/**
	 * $data array contains all records retrieved from database cache
	 * @var array
	 */
	var $data;
	/**
	 * $need_redirect determines whether the URL needs to be redirected
	 * @var boolean
	 */
	var $need_redirect;
	/**
	 * $is_seopage holds value as to whether page is in allowed SEO pages
	 * @var boolean
	 */
	var $is_seopage;
	/**
	 * $uri contains the $_SERVER['REQUEST_URI'] value
	 * @var string
	 */
	var $uri;
	/**
	 * $real_uri contains the $_SERVER['SCRIPT_NAME'] . '?' . $_SERVER['QUERY_STRING'] value
	 * @var string
	 */
	var $real_uri;
	/**
	 * $uri_parsed contains the parsed uri value array
	 * @var array
	 */
	var $uri_parsed;
	/**
	 * $path_info contains the getenv('PATH_INFO') value
	 * @var string
	 */
	var $path_info;

	/**
	 * SEO_URL class constructor
	 * @param integer $languages_id
	 * @version 1.1
	 */
	function __construct($languages_id, $attributesCustom = [])
	{
		global $SID, $sessionCore;

		$this->languages_id = (int)$languages_id;

		$sqlCmd = isset($this->attributes['USE_SEO_HEADER_TAGS']) && $this->attributes['USE_SEO_HEADER_TAGS'] == 'true' ? 'cd.categories_htc_title_tag ) as cName' : 'cd.categories_name ) as cName';

		$aDatos = tep_db_query('select LOWER(' . $sqlCmd . ', c.categories_id, c.parent_id
							 from categories c
							 inner join categories_description cd on(c.categories_id = cd.categories_id)
							 where cd.language_id = "' . (int)$this->languages_id . '" and c.categories_status = 1 and c.categories_id != 576
							 order by sort_order, cd.categories_name');

		while ($aDato = tep_db_fetch_array($aDatos))
			$aReturn[$aDato['categories_id']] = $aDato;

		$this->aAllCategories = $aReturn;

		$this->data = array();
		$this->turnOffBrokenUrls(); // Turn off experimental oscommerce search engine friendly urls

		$seo_pages = array(FILENAME_DEFAULT,
			FILENAME_CATEGORIES,
			FILENAME_MANUFACTURERS,
			FILENAME_PRODUCT_INFO,
			FILENAME_INFORMATION);
		if (defined('FILENAME_LANDINGS')) $seo_pages[] = FILENAME_LANDINGS;

//ojp USE_SEO_CACHE_LINKS
		$this->attributes = array('PHP_VERSION' => PHP_VERSION,
			'SESSION_STARTED' => $sessionCore->hasStarted(),
			'SID' => $SID,
			'SEO_ENABLED' => defined('SEO_ENABLED') ? SEO_ENABLED : 'false',
			'SEO_ADD_CID_TO_PRODUCT_URLS' => defined('SEO_ADD_CID_TO_PRODUCT_URLS') ? SEO_ADD_CID_TO_PRODUCT_URLS : 'false',
			'SEO_ADD_CPATH_TO_PRODUCT_URLS' => defined('SEO_ADD_CPATH_TO_PRODUCT_URLS') ? SEO_ADD_CPATH_TO_PRODUCT_URLS : 'false',
			'SEO_ADD_CAT_PARENT' => defined('SEO_ADD_CAT_PARENT') ? SEO_ADD_CAT_PARENT : 'true',
			'SEO_URLS_USE_W3C_VALID' => defined('SEO_URLS_USE_W3C_VALID') ? SEO_URLS_USE_W3C_VALID : 'true',
			'USE_SEO_CACHE_GLOBAL' => defined('USE_SEO_CACHE_GLOBAL') ? USE_SEO_CACHE_GLOBAL : 'false',
			'USE_SEO_CACHE_PRODUCTS' => defined('USE_SEO_CACHE_PRODUCTS') ? USE_SEO_CACHE_PRODUCTS : 'false',
			'USE_SEO_CACHE_CATEGORIES' => defined('USE_SEO_CACHE_CATEGORIES') ? USE_SEO_CACHE_CATEGORIES : 'false',
			'USE_SEO_CACHE_MANUFACTURERS' => defined('USE_SEO_CACHE_MANUFACTURERS') ? USE_SEO_CACHE_MANUFACTURERS : 'false',
			'USE_SEO_CACHE_LANDINGS' => defined('USE_SEO_CACHE_LANDINGS') ? USE_SEO_CACHE_LANDINGS : 'false',
			'USE_SEO_CACHE_INFO_PAGES' => defined('USE_SEO_CACHE_INFO_PAGES') ? USE_SEO_CACHE_INFO_PAGES : 'false',
			'USE_SEO_REDIRECT' => defined('USE_SEO_REDIRECT') ? USE_SEO_REDIRECT : 'false',
			'USE_SEO_HEADER_TAGS' => defined('USE_SEO_HEADER_TAGS') ? USE_SEO_HEADER_TAGS : 'false',
			'USE_SEO_PERFORMANCE_CHECK' => defined('USE_SEO_PERFORMANCE_CHECK') ? USE_SEO_PERFORMANCE_CHECK : 'false',
			'SEO_REWRITE_TYPE' => defined('SEO_REWRITE_TYPE') ? SEO_REWRITE_TYPE : 'false',
			'SEO_URLS_FILTER_SHORT_WORDS' => defined('SEO_URLS_FILTER_SHORT_WORDS') ? SEO_URLS_FILTER_SHORT_WORDS : 'false',
			'SEO_CHAR_CONVERT_SET' => defined('SEO_CHAR_CONVERT_SET') ? $this->expand(SEO_CHAR_CONVERT_SET) : 'false',
			'SEO_REMOVE_ALL_SPEC_CHARS' => defined('SEO_REMOVE_ALL_SPEC_CHARS') ? SEO_REMOVE_ALL_SPEC_CHARS : 'false',
			'SEO_PAGES' => $seo_pages);

		// Remplazamos configuraciones
		$this->attributes = array_replace($this->attributes, $attributesCustom);

		$this->base_url = HTTP_SERVER . DIR_WS_HTTP_CATALOG;
		$this->base_url_ssl = HTTPS_SERVER . DIR_WS_HTTPS_CATALOG;
		$this->cache = array();
		$this->timestamp = 0;

		$this->reg_anchors = array('products_id' => '-p-',
			'cPath' => '-c-',
			'manufacturers_id' => '-m-',
			'pID' => '-pi-',
			'info_id' => '-i-',
			'landing_id' => '-l-'
		);

		$this->performance = array('NUMBER_URLS_GENERATED' => 0,
			'NUMBER_QUERIES' => 0,
			'CACHE_QUERY_SAVINGS' => 0,
			'NUMBER_STANDARD_URLS_GENERATED' => 0,
			'TOTAL_CACHED_PER_PAGE_RECORDS' => 0,
			'TOTAL_TIME' => 0,
			'TIME_PER_URL' => 0,
			'QUERIES' => array()
		);

		if ($this->attributes['USE_SEO_CACHE_GLOBAL'] == 'true') {
			$this->cache_file = 'seo_urls_';
			$this->cache_gc();
			if ($this->attributes['USE_SEO_CACHE_PRODUCTS'] == 'true') $this->generate_products_cache();
			if ($this->attributes['USE_SEO_CACHE_CATEGORIES'] == 'true') $this->generate_categories_cache();
			if ($this->attributes['USE_SEO_CACHE_MANUFACTURERS'] == 'true') $this->generate_manufacturers_cache();
			if ($this->attributes['USE_SEO_CACHE_LANDINGS'] == 'true') $this->generate_landings_cache();
			if ($this->attributes['USE_SEO_CACHE_INFO_PAGES'] == 'true' && defined('TABLE_INFORMATION')) $this->generate_information_cache();
		} # end if

		if ($this->attributes['SEO_ENABLED'] == 'true' && $this->attributes['USE_SEO_REDIRECT'] == 'true') {
			$this->check_redirect();
		} # end if
	} # end constructor

	/**
	 * Function to return SEO URL link SEO'd with stock generattion for error fallback
	 * @param string $page Base script for URL
	 * @param string $parameters URL parameters
	 * @param string $connection NONSSL/SSL
	 * @param boolean $add_session_id Switch to add osCsid
	 * @return string Formed href link
	 * @version 1.0
	 */
	function href_link($page = '', $parameters = '', $connection = 'NONSSL', $add_session_id = true, $nLanguage = '')
	{
		// Si recibimos idioma, cambiamos variable de idioma de la clase
		if ($nLanguage != '')
			$this->languages_id = $nLanguage;

		$add_session_id = false;
		// Some sites have hardcoded &amp;
		$parameters = str_replace('&amp;', '&', $parameters ?? '');
		if ($this->attributes['USE_SEO_PERFORMANCE_CHECK'] == 'true') {
			$this->start($this->timestamp);
			$this->performance['NUMBER_URLS_GENERATED']++;
		}

		if (!in_array($page, $this->attributes['SEO_PAGES']) || $this->attributes['SEO_ENABLED'] == 'false') {
			return $this->stock_href_link($page, $parameters, $connection, $add_session_id);
		}

		$link = $connection == 'NONSSL' ? $this->base_url : $this->base_url_ssl;
		$separator = '?';

		if ($this->not_null($parameters)) {
			$link .= $this->parse_parameters($page, $parameters, $separator);
		} else {
			$link .= $page;
		}
		$link = $this->add_sid($link, $add_session_id, $connection, $separator);
		if ($this->attributes['USE_SEO_PERFORMANCE_CHECK'] == 'true') {
			$this->stop($this->timestamp, $time);
			$this->performance['TOTAL_TIME'] += $time;
		}

		switch ($this->attributes['SEO_URLS_USE_W3C_VALID']) {
			case ('true'):
				if (!isset($_SESSION['customer_id']) && defined('ENABLE_PAGE_CACHE') && ENABLE_PAGE_CACHE == 'true' && class_exists('page_cache')) {
					return $link;
				} else {
					return htmlspecialchars(mb_convert_encoding($link, 'UTF-8', 'ISO-8859-1'));
				}
				break;
			case ('false'):
				return $link;
				break;
		}
	} # end function

	/**
	 * Stock function, fallback use
	 */
	function stock_href_link($page = '', $parameters = '', $connection = 'NONSSL', $add_session_id = true, $search_engine_safe = true)
	{
		global $request_type, $SID, $sessionCore;
		if (!$this->not_null($page)) {
			die('<div class="mensaje">Unable to determine the page link!</div>');
		}
		if ($page == '/') $page = '';
		if ($connection == 'NONSSL') {
			$link = HTTP_SERVER . DIR_WS_HTTP_CATALOG;
		} elseif ($connection == 'SSL') {
			if (ENABLE_SSL == true) {
				$link = HTTPS_SERVER . DIR_WS_HTTPS_CATALOG;
			} else {
				$link = HTTP_SERVER . DIR_WS_HTTP_CATALOG;
			}
		} else {
			die('<div class="mensaje">Unable to determine connection method on a link!<br /><br />Known methods: NONSSL SSL</strong></div>');
		}
		if ($this->not_null($parameters)) {
			$link .= $page . '?' . $this->output_string($parameters);
			$separator = '&';
		} else {
			$link .= $page;
			$separator = '?';
		}
		while ((substr($link, -1) == '&') || (substr($link, -1) == '?')) $link = substr($link, 0, -1);
		if (($add_session_id == true) && $sessionCore->hasStarted() && (SESSION_FORCE_COOKIE_USE == 'False')) {
			if ($this->not_null($SID)) {
				$_sid = $SID;
			} elseif ((($request_type == 'NONSSL') && ($connection == 'SSL') && (ENABLE_SSL == true)) || (($request_type == 'SSL') && ($connection == 'NONSSL'))) {
				if (HTTP_COOKIE_DOMAIN != HTTPS_COOKIE_DOMAIN) {
					$_sid = $this->SessionName() . '=' . $this->SessionID();
				}
			}
		}
		if ((SEARCH_ENGINE_FRIENDLY_URLS == 'true') && ($search_engine_safe == true)) {
			while (strstr($link, '&&')) $link = str_replace('&&', '&', $link);
			$link = str_replace('?', '/', $link);
			$link = str_replace('&', '/', $link);
			$link = str_replace('=', '/', $link);
			$separator = '?';
		}
		switch (true) {
			case (!isset($_SESSION['customer_id']) && defined('ENABLE_PAGE_CACHE') && ENABLE_PAGE_CACHE == 'true' && class_exists('page_cache')):
				$page_cache = true;
				$return = $link . $separator . '<osCsid>';
				break;
			case (isset($_sid)):
				$page_cache = false;
				$return = $link . $separator . tep_output_string($_sid);
				break;
			default:
				$page_cache = false;
				$return = $link;
				break;
		} # end switch
		if ($this->attributes['USE_SEO_PERFORMANCE_CHECK'] == 'true') $this->performance['NUMBER_STANDARD_URLS_GENERATED']++;
		$this->cache['STANDARD_URLS'][] = $link;
		if ($this->attributes['USE_SEO_PERFORMANCE_CHECK'] == 'true') {
			$time = 0;
			$this->stop($this->timestamp, $time);
			$this->performance['TOTAL_TIME'] += $time;
		}
		switch (true) {
			case ($this->attributes['SEO_URLS_USE_W3C_VALID'] == 'true' && !$page_cache):
				return htmlspecialchars(mb_convert_encoding($return, 'UTF-8', 'ISO-8859-1'));
				break;
			default:
				return $return;
				break;
		}# end swtich
	} # end default tep_href function

	/**
	 * Function to append session ID if needed
	 * @param string $link
	 * @param boolean $add_session_id
	 * @param string $connection
	 * @param string $separator
	 * @return string
	 */
	function add_sid($link, $add_session_id, $connection, $separator)
	{
		global $request_type; // global variable
		if (($add_session_id) && ($this->attributes['SESSION_STARTED']) && (SESSION_FORCE_COOKIE_USE == 'False')) {
			if ($this->not_null($this->attributes['SID'])) {
				$_sid = $this->attributes['SID'];
			} elseif ((($request_type == 'NONSSL') && ($connection == 'SSL') && (ENABLE_SSL == true)) || (($request_type == 'SSL') && ($connection == 'NONSSL'))) {
				if (HTTP_COOKIE_DOMAIN != HTTPS_COOKIE_DOMAIN) {
					$_sid = $this->SessionName() . '=' . $this->SessionID();
				}
			}
		}
		switch (true) {
			case (!isset($_SESSION['customer_id']) && defined('ENABLE_PAGE_CACHE') && ENABLE_PAGE_CACHE == 'true' && class_exists('page_cache')):
				$return = $link . $separator . '<osCsid>';
				break;
			case (isset($_sid) && $this->not_null($_sid)):
				$return = $link . $separator . tep_output_string($_sid);
				break;
			default:
				$return = $link;
				break;
		} # end switch
		return $return;
	} # end function

	/**
	 * SFunction to parse the parameters into an SEO URL
	 * @param string $page
	 * @param string $params
	 * @param string $separator NOTE: passed by reference
	 * @return string
	 */
	function parse_parameters($page, $params, &$separator)
	{
		$p = @explode('&', $params);
		krsort($p);
		$container = array();
		foreach ($p as $index => $valuepair) {
			$p2 = @explode('=', $valuepair);
			switch ($p2[0]) {
				case 'products_id':
					switch (true) {
						case ($page == FILENAME_PRODUCT_INFO && !$this->is_attribute_string($p2[1])):
							$url = $this->make_url($page, $this->get_product_name($p2[1]), $p2[0], $p2[1], '.html');
							break;
						default:
							$container[$p2[0]] = $p2[1];
							break;
					} # end switch
					break;
				case 'cPath':
					switch (true) {
						case ($page == FILENAME_CATEGORIES):
							$url = $this->make_url($page, $this->get_category_name($p2[1]), $p2[0], $p2[1], '.html');
							break;
						case (!$this->is_product_string($params)):

							if ($this->attributes['SEO_ADD_CID_TO_PRODUCT_URLS'] == 'true') {
								$container[$p2[0]] = $p2[1];
							}
							break;
						default:
							$container[$p2[0]] = $p2[1];
							break;
					} # end switch
					break;
				case 'manufacturers_id':
					switch (true) {
						case ($page == FILENAME_MANUFACTURERS && !$this->is_cPath_string($params) && !$this->is_product_string($params)):
							$url = $this->make_url($page, $this->get_manufacturer_name($p2[1]), $p2[0], $p2[1], '.html');
							break;
						case ($page == FILENAME_PRODUCT_INFO):
							break;
						default:
							$container[$p2[0]] = $p2[1];
							break;
					} # end switch
					break;
				case 'landing_id':
					switch (true) {
						case ($page == FILENAME_LANDINGS && !$this->is_cPath_string($params) && !$this->is_product_string($params)):
							$url = $this->make_url($page, $this->get_landing_name($p2[1]), $p2[0], $p2[1], '.html');
							$url = preg_replace('/\-n\-/i', '-and-', $url);
							break;
						case ($page == FILENAME_PRODUCT_INFO):
							break;
						default:
							$container[$p2[0]] = $p2[1];
							break;
					} # end switch
					break;
				case 'pID':
					switch (true) {
						default:
							$container[$p2[0]] = $p2[1];
							break;
					} # end switch
					break;

				case 'info_id': //Information Pages
					switch (true) {
						case ($page == FILENAME_INFORMATION):
							$url = $this->make_url($page, $this->get_information_name($p2[1]), $p2[0], $p2[1], '.html');
							break;
						default:
							$container[$p2[0]] = $p2[1];
							break;
					} # end switch
					break;

				default:
					if (isset($p2[1])) $container[$p2[0]] = $p2[1];
					break;
			} # end switch
		} # end foreach $p
		$url = isset($url) ? $url : $page;
		if (count($container) > 0) {
			if ($imploded_params = $this->implode_assoc($container)) {
				$url .= $separator . $this->output_string($imploded_params);
				$separator = '&';
			}
		}

		return $url;
	} # end function

	/**
	 * Function to return the generated SEO URL
	 * @param string $page
	 * @param string $string Stripped, formed anchor
	 * @param string $anchor_type Parameter type (products_id, cPath, etc.)
	 * @param integer $id
	 * @param string $extension Default = .html
	 * @param string $separator NOTE: passed by reference -- NOTE: not used so removed
	 * @return string
	 */
	function make_url($page, $string, $anchor_type, $id, $extension = '.html')
	{
		// Right now there is but one rewrite method since cName was dropped
		// In the future there will be additional methods here in the switch
		switch ($this->attributes['SEO_REWRITE_TYPE']) {
			case 'Rewrite':
				return $string . $this->reg_anchors[$anchor_type] . $id . $extension;
				break;
			default:
				break;
		} # end switch
	} # end function

	/**
	 * Function to get the product name. Use evaluated cache, per page cache, or database query in that order of precedent
	 * @param integer $pID
	 * @return string Stripped anchor text
	 */
	function get_product_name($pID)
	{
		$result = array();
		$cName = '';
		if ($this->attributes['SEO_ADD_CPATH_TO_PRODUCT_URLS'] == 'true') {
			$cName = $this->get_all_category_parents($pID, $cName);
		}
		switch (true) {
			case ($this->attributes['USE_SEO_CACHE_GLOBAL'] == 'true' && defined('PRODUCT_NAME_' . $pID)):
				if ($this->attributes['USE_SEO_PERFORMANCE_CHECK'] == 'true') $this->performance['CACHE_QUERY_SAVINGS']++;
				$return = (tep_not_null($cName) ? $cName . '-' . constant('PRODUCT_NAME_' . $pID) : constant('PRODUCT_NAME_' . $pID));
				$this->cache['PRODUCTS'][$pID] = $return;
				break;
			case ($this->attributes['USE_SEO_CACHE_GLOBAL'] == 'true' && isset($this->cache['PRODUCTS'][$pID])):
				if ($this->attributes['USE_SEO_PERFORMANCE_CHECK'] == 'true') $this->performance['CACHE_QUERY_SAVINGS']++;
				$return = (tep_not_null($cName) ? $cName . '-' . $this->cache['PRODUCTS'][$pID] : $this->cache['PRODUCTS'][$pID]);
				break;
			default:
				if ($this->attributes['USE_SEO_PERFORMANCE_CHECK'] == 'true') $this->performance['NUMBER_QUERIES']++;
				$sqlCmd = $this->attributes['USE_SEO_HEADER_TAGS'] == 'true' ? 'products_name as pName' : 'products_name as pName';
				$sql = tep_db_query("SELECT " . $sqlCmd . "
                                          FROM " . TABLE_PRODUCTS_DESCRIPTION . "
                                          WHERE products_id='" . (int)$pID . "'
                                          AND language_id='" . (int)$this->languages_id . "'
							 LIMIT 1");
				$result = tep_db_fetch_array($sql);

				if (is_array($result) && array_key_exists('pName', $result)) {
					$pName = $this->strip($result['pName']);
				} else {
					$pName = '';
				}
				$this->cache['PRODUCTS'][$pID] = $pName;
				if ($this->attributes['USE_SEO_PERFORMANCE_CHECK'] == 'true') $this->performance['QUERIES']['PRODUCTS'][] = $sql;
				$return = (tep_not_null($cName) ? $cName . '-' . $pName : $pName);
				break;
		} # end switch
		return $return;
	} # end function

	/**
	 * Function to get all parent categories
	 * @param string $name
	 * @param string $method
	 * @return string
	 */
	function get_all_category_parents($pID, $cName)
	{
		$sqlCmd = $this->attributes['USE_SEO_HEADER_TAGS'] == 'true' ? 'cd.categories_htc_title_tag ) as cName' : 'cd.categories_name ) as cName';
		$sql = tep_db_query("SELECT LOWER(" . $sqlCmd . ", cd.categories_id
                     FROM " . TABLE_CATEGORIES_DESCRIPTION . " cd LEFT JOIN
                          " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c on cd.categories_id = p2c.categories_id
                     WHERE p2c.products_id = '" . (int)$pID . "' AND cd.language_id = '" . (int)$this->languages_id . "'
							 LIMIT 1");
		$result = tep_db_fetch_array($sql);
		$cName = $result['cName'];
		return $this->get_all_category_names($result['categories_id'], $cName);
	}

	/**
	 * Function to get names of all parent categories
	 * @param string $name
	 * @param string $method
	 * @return string
	 */
	function get_all_category_names($cID, $cName, &$sIds = false)
	{
		$parArray = array(); //get all of the parrents
		$this->GetParentCategories($parArray, $cID);

		foreach ($parArray as $parentID) {
			$cName = $this->aAllCategories[(int)$parentID]['cName'] . '-' . $cName; //build the new string
			$sIds .= $this->aAllCategories[(int)$parentID]['categories_id'];
		}
		return $this->strip(str_replace(" ", "-", $cName));
	}

	/**
	 * Function to get the category name. Use evaluated cache, per page cache, or database query in that order of precedent
	 * @param integer $cID NOTE: passed by reference
	 * @return string Stripped anchor text
	 */
	function get_category_name(&$cID)
	{
		$full_cPath = $this->get_full_cPath($cID, $single_cID); // full cPath needed for uniformity
		switch (true) {
			case ($this->attributes['USE_SEO_CACHE_GLOBAL'] == 'true' && defined('CATEGORY_NAME_' . $full_cPath)):
				if ($this->attributes['USE_SEO_PERFORMANCE_CHECK'] == 'true') $this->performance['CACHE_QUERY_SAVINGS']++;
				$return = constant('CATEGORY_NAME_' . $full_cPath);
				$this->cache['CATEGORIES'][$full_cPath] = $return;
				break;
			case ($this->attributes['USE_SEO_CACHE_GLOBAL'] == 'true' && isset($this->cache['CATEGORIES'][$full_cPath])):
				if ($this->attributes['USE_SEO_PERFORMANCE_CHECK'] == 'true') $this->performance['CACHE_QUERY_SAVINGS']++;
				$return = $this->cache['CATEGORIES'][$full_cPath];
				break;
			default:
				if ($this->attributes['USE_SEO_PERFORMANCE_CHECK'] == 'true') $this->performance['NUMBER_QUERIES']++;
				switch (true) {
					case ($this->attributes['SEO_ADD_CAT_PARENT'] == 'true'):
						$cName = (str_replace(" ", "-", $this->aAllCategories[(int)$single_cID]['cName']));
						$cName = $this->get_all_category_names($single_cID, $cName);
						break;
					default:
						$cName = $this->aAllCategories[(int)$single_cID]['cName'];
						break;
				}
				$cName = $this->strip($cName);
				$this->cache['CATEGORIES'][$full_cPath] = $cName;
				if ($this->attributes['USE_SEO_PERFORMANCE_CHECK'] == 'true') $this->performance['QUERIES']['CATEGORIES'][] = $sql;
				$return = $cName;
				break;
		} # end switch
		$cID = $full_cPath;
		return $return;
	} # end function

	/**
	 * Function to get the manufacturer name. Use evaluated cache, per page cache, or database query in that order of precedent.
	 * @param integer $mID
	 * @return string
	 */
	function get_manufacturer_name($mID)
	{
		switch (true) {
			case ($this->attributes['USE_SEO_CACHE_GLOBAL'] == 'true' && defined('MANUFACTURER_NAME_' . $mID)):
				if ($this->attributes['USE_SEO_PERFORMANCE_CHECK'] == 'true') $this->performance['CACHE_QUERY_SAVINGS']++;
				$return = constant('MANUFACTURER_NAME_' . $mID);
				$this->cache['MANUFACTURERS'][$mID] = $return;
				break;
			case ($this->attributes['USE_SEO_CACHE_GLOBAL'] == 'true' && isset($this->cache['MANUFACTURERS'][$mID])):
				if ($this->attributes['USE_SEO_PERFORMANCE_CHECK'] == 'true') $this->performance['CACHE_QUERY_SAVINGS']++;
				$return = $this->cache['MANUFACTURERS'][$mID];
				break;
			default:
				if ($this->attributes['USE_SEO_PERFORMANCE_CHECK'] == 'true') $this->performance['NUMBER_QUERIES']++;
				$sqlCmd = $this->attributes['USE_SEO_HEADER_TAGS'] == 'true' ? 'manufacturers_htc_title_tag as mName' : 'manufacturers_name as mName';
				$sqlTable = $this->attributes['USE_SEO_HEADER_TAGS'] == 'true' ? TABLE_MANUFACTURERS_INFO : TABLE_MANUFACTURERS;
				$sql = tep_db_query("SELECT " . $sqlCmd . "
                                                FROM " . $sqlTable . "
                                                WHERE manufacturers_id='" . (int)$mID . "'
                                                LIMIT 1");
				$result = tep_db_fetch_array($sql);
				$mName = $this->strip($result['mName']);
				$this->cache['MANUFACTURERS'][$mID] = $mName;
				if ($this->attributes['USE_SEO_PERFORMANCE_CHECK'] == 'true') $this->performance['QUERIES']['MANUFACTURERS'][] = $sql;
				$return = $mName;
				break;
		} # end switch
		return $return;
	} # end function

	function get_landing_name($lID)
	{
		switch (true) {
			case ($this->attributes['USE_SEO_CACHE_GLOBAL'] == 'true' && defined('LANDING_NAME_' . $lID)):
				if ($this->attributes['USE_SEO_PERFORMANCE_CHECK'] == 'true') $this->performance['CACHE_QUERY_SAVINGS']++;
				$return = constant('LANDING_NAME_' . $lID);
				$this->cache['LANDINGS'][$lID] = $return;
				break;
			case ($this->attributes['USE_SEO_CACHE_GLOBAL'] == 'true' && isset($this->cache['LANDINGS'][$lID])):
				if ($this->attributes['USE_SEO_PERFORMANCE_CHECK'] == 'true') $this->performance['CACHE_QUERY_SAVINGS']++;
				$return = $this->cache['LANDINGS'][$lID];
				break;
			default:
				if ($this->attributes['USE_SEO_PERFORMANCE_CHECK'] == 'true') $this->performance['NUMBER_QUERIES']++;
				$sql = tep_db_query("SELECT landing_title
													FROM " . TABLE_LANDINGS_DESCRIPTION . "
													WHERE landing_id ='" . (int)$lID . "' AND language_id = '" . (int)$this->languages_id . "'
										 LIMIT 1");
				$result = tep_db_fetch_array($sql);
				$mName = $this->strip($result['landing_title']);

				// Evitamos coincidencias con otras URLs
				$mName = preg_replace('/(\-n\-)/i', '_n_', $mName);

				$this->cache['LANDINGS'][$lID] = $mName;
				if ($this->attributes['USE_SEO_PERFORMANCE_CHECK'] == 'true') $this->performance['QUERIES']['LANDINGS'][] = $sql;
				$return = $mName;
				break;
		} # end switch
		return $return;
	} # end function


	/**
	 * Function to get the informatin name. Use evaluated cache, per page cache, or database query in that order of precedent.
	 * @param integer $iID
	 * @return string
	 */
	function get_information_name($iID)
	{
		switch (true) {
			case ($this->attributes['USE_SEO_CACHE_GLOBAL'] == 'true' && defined('INFO_NAME_' . $iID)):
				if ($this->attributes['USE_SEO_PERFORMANCE_CHECK'] == 'true') $this->performance['CACHE_QUERY_SAVINGS']++;
				$return = constant('INFO_NAME_' . $iID);
				$this->cache['INFO'][$iID] = $return;
				break;
			case ($this->attributes['USE_SEO_CACHE_GLOBAL'] == 'true' && isset($this->cache['INFO'][$iID])):
				if ($this->attributes['USE_SEO_PERFORMANCE_CHECK'] == 'true') $this->performance['CACHE_QUERY_SAVINGS']++;
				$return = $this->cache['INFO'][$iID];
				break;
			default:
				if ($this->attributes['USE_SEO_PERFORMANCE_CHECK'] == 'true') $this->performance['NUMBER_QUERIES']++;
				$sql = tep_db_query("SELECT information_title as iName
                                               FROM " . TABLE_INFORMATION . "
                                               WHERE information_id='" . (int)$iID . "'
                                               AND language_id='" . (int)$this->languages_id . "'
								     LIMIT 1");
				$result = tep_db_fetch_array($sql);
				$iName = $this->strip($result['iName']);
				$this->cache['INFO'][$iID] = $iName;
				if ($this->attributes['USE_SEO_PERFORMANCE_CHECK'] == 'true') $this->performance['QUERIES']['INFO'][] = $sql;
				$return = $iName;
				break;
		} # end switch
		return $return;
	} # end function

	/**
	 * Function to retrieve full cPath from category ID
	 * @param mixed $cID Could contain cPath or single category_id
	 * @param integer $original Single category_id passed back by reference
	 * @return string Full cPath string
	 * @version 1.1
	 */
	function get_full_cPath($cID, &$original)
	{
		if (is_numeric(strpos($cID, '_'))) {
			$temp = @explode('_', $cID);
			$original = $temp[count($temp) - 1];
			return $cID;
		} else {
			$c = array();
			$this->GetParentCategories($c, $cID);
			$c = array_reverse($c);
			$c[] = $cID;
			$original = $cID;
			$cID = count($c) > 1 ? implode('_', $c) : $cID;
			return $cID;
		}
	} # end function

	/**
	 * Recursion function to retrieve parent categories from category ID
	 * @param mixed $categories Passed by reference
	 * @param integer $categories_id
	 */
	function GetParentCategories(&$categories, $categories_id)
	{
		if (isset($this->aAllCategories[(int)$categories_id]['parent_id']) && $this->aAllCategories[(int)$categories_id]['parent_id'] == 0) return true;
		$categories[count($categories)] = $this->aAllCategories[(int)$categories_id]['parent_id'];
		if ($this->aAllCategories[(int)$categories_id]['parent_id'] != $categories_id) {
			$this->GetParentCategories($categories, $this->aAllCategories[(int)$categories_id]['parent_id']);
		}
	} # end function

	/**
	 * Function to check if a value is NULL as abstracted
	 * @param mixed $value
	 * @return boolean
	 */
	function not_null($value)
	{
		if (is_array($value)) {
			if (count($value) > 0) {
				return true;
			} else {
				return false;
			}
		} else {
			if (($value != '') && (strtolower($value) != 'null') && (strlen(trim($value)) > 0)) {
				return true;
			} else {
				return false;
			}
		}
	} # end function

	/**
	 * Function to check if the products_id contains an attribute
	 * @param integer $pID
	 * @return boolean
	 */
	function is_attribute_string($pID)
	{
		if (is_numeric(strpos($pID, '{'))) {
			return true;
		} else {
			return false;
		}
	} # end function

	/**
	 * Function to check if the params contains a products_id
	 * @param string $params
	 * @return boolean
	 */
	function is_product_string($params)
	{
		if (is_numeric(strpos('products_id', $params))) {
			return true;
		} else {
			return false;
		}
	} # end function

	/**
	 * Function to check if cPath is in the parameter string
	 * @param string $params
	 * @return boolean
	 */
	function is_cPath_string($params)
	{
		if (preg_match('/cPath/i', $params)) {
			return true;
		} else {
			return false;
		}
	} # end function

	/**
	 * Function used to output class profile
	 */
	function profile()
	{
		$this->calculate_performance();
		$this->PrintArray($this->attributes, 'Class Attributes');
		$this->PrintArray($this->cache, 'Cached Data');
	} # end function

	/**
	 * Function used to calculate and output the performance metrics of the class
	 * @return mixed Output of performance data wrapped in HTML pre tags
	 */
	function calculate_performance()
	{
		foreach ($this->cache as $type) {
			if ($this->attributes['USE_SEO_PERFORMANCE_CHECK'] == 'true') $this->performance['TOTAL_CACHED_PER_PAGE_RECORDS'] += count($type);
		}
		$this->performance['TIME_PER_URL'] = $this->performance['TOTAL_TIME'] / $this->performance['NUMBER_URLS_GENERATED'];
		return $this->PrintArray($this->performance, 'Performance Data');
	} # end function

	/**
	 * Function to strip the string of punctuation and white space
	 * @param string $string
	 * @return string Stripped text. Removes all non-alphanumeric characters.
	 */
	function strip($string)
	{
		if (!isset($string)) {
			return ''; // Return an empty string if $string is not set
		}

		if (defined('CHARSET') && CHARSET == 'utf-8') {
			$string = iconv("ISO-8859-1", "UTF-8//TRANSLIT", $string);
		}
		if (is_array($this->attributes['SEO_CHAR_CONVERT_SET'])) {
			$string = strtr($string, $this->attributes['SEO_CHAR_CONVERT_SET']);
		}

		$pattern = $this->attributes['SEO_REMOVE_ALL_SPEC_CHARS'] == 'true'

			? "([^[:alnum:]])"
			: "/[^a-z0-9- ]/i";

		$string = preg_replace('/((&#39))/', '-', strtolower($string)); //remove apostrophe - not caught by above
		$anchor = preg_replace($pattern, '', strtolower($string));
		$pattern = "([[:space:]]|[[:blank:]])";
		$anchor = preg_replace($pattern, '-', $anchor);
		return $this->short_name($anchor); // return the short filtered name
	} # end function

	/**
	 * Function to expand the SEO_CONVERT_SET group
	 * @param string $set
	 * @return mixed
	 */
	function expand($set)
	{
		$container = array();
		if ($this->not_null($set)) {
			if ($data = @explode(',', $set)) {
				foreach ($data as $index => $valuepair) {
					$p = @explode('=>', $valuepair);
					$container[trim($p[0])] = trim($p[1] ?? '');
				}
				return $container;
			} else {
				return 'false';
			}
		} else {
			return 'false';
		}
	} # end function

	/**
	 * Function to return the short word filtered string
	 * @param string $str
	 * @param integer $limit
	 * @return string Short word filtered
	 */
	function short_name($str, $limit = 3)
	{
		$container = array();
		if ($this->attributes['SEO_URLS_FILTER_SHORT_WORDS'] != 'false') $limit = (int)$this->attributes['SEO_URLS_FILTER_SHORT_WORDS'];
		$foo = @explode('-', $str);
		foreach ($foo as $index => $value) {
			switch (true) {
				case (strlen($value) <= $limit):
					break;
				default:
					$container[] = $value;
					break;
			}
		} # end foreach

		$container = (count($container) > 1 ? implode('-', $container) : (count($container) > 0 ? $container[0] : $str));
		return $container;
	}

	/**
	 * Function to implode an associative array
	 * @param array $array Associative data array
	 * @param string $inner_glue
	 * @param string $outer_glue
	 * @return string
	 */
	function implode_assoc($array, $inner_glue = '=', $outer_glue = '&')
	{
		$output = array();
		foreach ($array as $key => $item) {
			if ($this->not_null($key) && $this->not_null($item)) {
				$output[] = $key . $inner_glue . $item;
			}
		} # end foreach
		return @implode($outer_glue, $output);
	}

	/**
	 * Function to print an array within pre tags, debug use
	 * @param mixed $array
	 */
	function PrintArray($array, $heading = '')
	{
		echo '<fieldset style="border-style:solid; border-width:1px;">' . "\n";
		echo '<legend style="background-color:#FFFFCC; border-style:solid; border-width:1px;">' . $heading . '</legend>' . "\n";
		echo '<pre style="text-align:left;">' . "\n";
		print_r($array);
		echo '</pre>' . "\n";
		echo '</fieldset><br/>' . "\n";
	} # end function

	/**
	 * Function to start time for performance metric
	 * @param float $start_time
	 */
	function start(&$start_time)
	{
		$start_time = explode(' ', microtime());
	}

	/**
	 * Function to stop time for performance metric
	 * @param float $start
	 * @param float $time NOTE: passed by reference
	 */
	function stop($start, &$time)
	{
		$end = explode(' ', microtime());
		$time = number_format(array_sum($end) - array_sum($start), 8, '.', '');
	}

	/**
	 * Function to translate a string
	 * @param string $data String to be translated
	 * @param array $parse Array of tarnslation variables
	 * @return string
	 */
	function parse_input_field_data($data, $parse)
	{
		return strtr(trim($data), $parse);
	}

	/**
	 * Function to output a translated or sanitized string
	 * @param string $sting String to be output
	 * @param mixed $translate Array of translation characters
	 * @param boolean $protected Switch for htemlspecialchars processing
	 * @return string
	 */
	function output_string($string, $translate = false, $protected = false)
	{
		if ($protected == true) {
			return htmlspecialchars($string);
		} else {
			if ($translate == false) {
				return $this->parse_input_field_data($string, array('"' => '&quot;'));
			} else {
				return $this->parse_input_field_data($string, $translate);
			}
		}
	}

	/**
	 * Function to return the session ID
	 * @param string $sessid
	 * @return string
	 */
	function SessionID($sessid = '')
	{
		if (!empty($sessid)) {
			return session_id($sessid);
		} else {
			return session_id();
		}
	}

	/**
	 * Function to return the session name
	 * @param string $name
	 * @return string
	 */
	function SessionName($name = '')
	{
		if (!empty($name)) {
			return session_name($name);
		} else {
			return session_name();
		}
	}

	/**
	 * Function to generate products cache entries
	 */
	function generate_products_cache()
	{
		$this->is_cached($this->cache_file . 'PRODUCTS', $is_cached, $is_expired);
		if (!$is_cached || $is_expired) {
			$sqlCmd = $this->attributes['USE_SEO_HEADER_TAGS'] == 'true' ? 'pd.products_head_title_tag as name' : 'pd.products_name as name';
			$product_query = tep_db_query("SELECT p.products_id as id, " . $sqlCmd . "
                        FROM " . TABLE_PRODUCTS . " p
                                LEFT JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd
                                ON p.products_id=pd.products_id
                                AND pd.language_id='" . (int)$this->languages_id . "'
                                WHERE p.products_status='1'");
			$prod_cache = '';
			while ($product = tep_db_fetch_array($product_query)) {

				$define = 'if(!defined(\'PRODUCT_NAME_' . $product['id'] . '\')){define(\'PRODUCT_NAME_' . $product['id'] . '\', \'' . $this->strip($product['name']) . '\');}';
				$prod_cache .= $define . "\n";
				eval("$define");
			}
			if ($product_query != NULL)
				tep_db_free_result($product_query);
			$this->save_cache($this->cache_file . 'PRODUCTS', $prod_cache, 'EVAL', 1, 1);
			unset($prod_cache);
		} else {
			$this->get_cache($this->cache_file . 'PRODUCTS');
		}
	} # end function

	/**
	 * Function to generate manufacturers cache entries
	 */
	function generate_manufacturers_cache()
	{
		$this->is_cached($this->cache_file . 'MANUFACTURERS', $is_cached, $is_expired);
		if (!$is_cached || $is_expired) { // it's not cached so create it
			$sqlCmd = $this->attributes['USE_SEO_HEADER_TAGS'] == 'true' ? 'md.manufacturers_htc_title_tag as name' : 'm.manufacturers_name as name';
			$manufacturers_query = tep_db_query("SELECT m.manufacturers_id as id, " . $sqlCmd . "
                        FROM " . TABLE_MANUFACTURERS . " m
                                LEFT JOIN " . TABLE_MANUFACTURERS_INFO . " md
                                ON m.manufacturers_id=md.manufacturers_id
                                AND md.languages_id='" . (int)$this->languages_id . "'");
			$man_cache = '';
			while ($manufacturer = tep_db_fetch_array($manufacturers_query)) {
				$define = 'if(!defined(\'MANUFACTURER_NAME_' . $manufacturer['id'] . '\')){define(\'MANUFACTURER_NAME_' . $manufacturer['id'] . '\', \'' . $this->strip($manufacturer['name']) . '\');}';
				$man_cache .= $define . "\n";
				eval("$define");
			}
			if ($manufacturers_query != NULL)
				tep_db_free_result($manufacturers_query);
			$this->save_cache($this->cache_file . 'MANUFACTURERS', $man_cache, 'EVAL', 1, 1);
			unset($man_cache);
		} else {
			$this->get_cache($this->cache_file . 'MANUFACTURERS');
		}
	} # end function

	/**
	 * Function to generate landings cache entries
	 */
	function generate_landings_cache()
	{
		$this->is_cached($this->cache_file . 'LANDINGS', $is_cached, $is_expired);
		if (!$is_cached || $is_expired) { // it's not cached so create it
			$landings_query = tep_db_query("SELECT landing_id as id, landing_title AS name
                        FROM " . TABLE_LANDINGS_DESCRIPTION . " WHERE language_id='" . (int)$this->languages_id . "'");
			$lan_cache = '';
			while ($landing = tep_db_fetch_array($landings_query)) {
				$define = 'if(!defined(\'LANDING_NAME_' . $landing['id'] . '\')){define(\'LANDING_NAME_' . $landing['id'] . '\', \'' . $this->strip($landing['name']) . '\');}';
				$lan_cache .= $define . "\n";
				eval("$define");
			}
			if ($landings_query != NULL)
				tep_db_free_result($landings_query);
			$this->save_cache($this->cache_file . 'LANDINGS', $lan_cache, 'EVAL', 1, 1);
			unset($lan_cache);
		} else {
			$this->get_cache($this->cache_file . 'LANDINGS');
		}
	} # end function

	/**
	 * Function to generate categories cache entries
	 */
	function generate_categories_cache()
	{
		$this->is_cached($this->cache_file . 'CATEGORIES', $is_cached, $is_expired);
		if (!$is_cached || $is_expired) { // it's not cached so create it
			switch (true) {
				case ($this->attributes['SEO_ADD_CAT_PARENT'] == 'true'):
					$sqlCmd = $this->attributes['USE_SEO_HEADER_TAGS'] == 'true' ? 'cd.categories_htc_title_tag as cName, cd2.categories_htc_title_tag AS pName' : 'cd.categories_name as cName, cd2.categories_name AS pName';
					$sql = "SELECT c.categories_id as id, c.parent_id, " . $sqlCmd . "
                                                        FROM " . TABLE_CATEGORIES . " c
                                                        INNER JOIN  " . TABLE_CATEGORIES_DESCRIPTION . " cd ON c.categories_id=cd.categories_id  AND cd.language_id='" . (int)$this->languages_id . "'
                                                        LEFT JOIN " . TABLE_CATEGORIES_DESCRIPTION . " cd2
                                                        ON c.parent_id=cd2.categories_id AND cd2.language_id='" . (int)$this->languages_id . "'";
					break;
				default:
					$sqlCmd = $this->attributes['USE_SEO_HEADER_TAGS'] == 'true' ? 'categories_htc_title_tag as cName' : 'categories_name as cName';
					$sql = "SELECT categories_id as id, " . $sqlCmd . "
                                                        FROM " . TABLE_CATEGORIES_DESCRIPTION . "
                                                        WHERE language_id='" . (int)$this->languages_id . "'";
					break;
			} # end switch
			$category_query = tep_db_query($sql);
			$cat_cache = '';
			while ($category = tep_db_fetch_array($category_query)) {
				$id = $this->get_full_cPath($category['id'], $single_cID);
				$name = array_key_exists('pName', $category) && $category['pName'] != '' ? $category['pName'] . ' ' . $category['cName'] : $category['cName'];
				$define = 'if(!defined(\'CATEGORY_NAME_' . $id . '\')){define(\'CATEGORY_NAME_' . $id . '\', \'' . $this->strip($name) . '\');}';
				$cat_cache .= $define . "\n";
				eval("$define");
			}
			if ($category_query != NULL)
				tep_db_free_result($category_query);
			$this->save_cache($this->cache_file . 'CATEGORIES', $cat_cache, 'EVAL', 1, 1);
			unset($cat_cache);
		} else {
			$this->get_cache($this->cache_file . 'CATEGORIES');
		}
	} # end function

	/**
	 * Function to generate information cache entries
	 */
	function generate_information_cache()
	{
		$this->is_cached($this->cache_file . 'INFO', $is_cached, $is_expired);
		if (!$is_cached || $is_expired) { // it's not cached so create it
			$information_query = tep_db_query("SELECT information_id as id, information_title as name
                                  FROM " . TABLE_INFORMATION . "
                                  WHERE language_id='" . (int)$this->languages_id . "'");
			$information_cache = '';

			while ($information = tep_db_fetch_array($information_query)) {
				$define = 'if(!defined(\'INFO_NAME_' . $information['id'] . '\')){define(\'INFO_NAME_' . $information['id'] . '\', \'' . $this->strip($information['name']) . '\');}';
				$information_cache .= $define . "\n";
				eval("$define");
			}
			if ($information_query != NULL)
				tep_db_free_result($information_query);
			$this->save_cache($this->cache_file . 'INFO', $information_cache, 'EVAL', 1, 1);
			unset($information_cache);
		} else {
			$this->get_cache($this->cache_file . 'INFO');
		}
	} # end function


	/**
	 * Function to save the cache to database
	 * @param string $name Cache name
	 * @param mixed $value Can be array, string, PHP code, or just about anything
	 * @param string $method RETURN, ARRAY, EVAL
	 * @param integer $gzip Enables compression
	 * @param integer $global Sets whether cache record is global is scope
	 * @param string $expires Sets the expiration
	 */
	function save_cache($name, $value, $method = 'RETURN', $gzip = 1, $global = 0, $expires = '30/days')
	{
		$expires = $this->convert_time($expires);
		if ($method == 'ARRAY') $value = serialize($value);
		$value = ($gzip === 1 ? base64_encode(gzdeflate($value, 1)) : addslashes($value));
		$sql_data_array = array('cache_id' => md5($name),
			'cache_language_id' => (int)$this->languages_id,
			'cache_name' => $name,
			'cache_data' => $value,
			'cache_global' => (int)$global,
			'cache_gzip' => (int)$gzip,
			'cache_method' => $method,
			'cache_date' => @date("Y-m-d H:i:s"),
			'cache_expires' => $expires
		);
		$this->is_cached($name, $is_cached, $is_expired);
		$cache_check = ($is_cached ? 'true' : 'false');
		switch ($cache_check) {
			case 'true':
				tep_db_perform('cache', $sql_data_array, 'update', "cache_id='" . md5($name) . "'");
				break;
			case 'false':
				tep_db_perform('cache', $sql_data_array, 'insert');
				break;
			default:
				break;
		} # end switch ($cache check)
		# unset the variables...clean as we go
		unset($value, $expires, $sql_data_array);
	}# end function save_cache()

	/**
	 * Function to get cache entry
	 * @param string $name
	 * @param boolean $local_memory
	 * @return mixed
	 */
	function get_cache($name = 'GLOBAL', $local_memory = false)
	{
		$select_list = 'cache_id, cache_language_id, cache_name, cache_data, cache_global, cache_gzip, cache_method, cache_date, cache_expires';
		$global = ($name == 'GLOBAL' ? true : false); // was GLOBAL passed or is using the default?
		switch ($name) {
			case 'GLOBAL':
				$this->cache_query = tep_db_query("SELECT " . $select_list . " FROM cache WHERE cache_language_id='" . (int)$this->languages_id . "' AND cache_global='1'");
				break;
			default:
				$this->cache_query = tep_db_query("SELECT " . $select_list . " FROM cache WHERE cache_id='" . md5($name) . "' AND cache_language_id='" . (int)$this->languages_id . "'");
				break;
		} # end switch ($name)
		$num_rows = tep_db_num_rows($this->cache_query);
		if ($num_rows) {
			$container = array();
			while ($cache = tep_db_fetch_array($this->cache_query)) {
				$cache_name = $cache['cache_name'];
				if ($cache['cache_expires'] > @date("Y-m-d H:i:s")) {
					$cache_data = ($cache['cache_gzip'] == 1 ? gzinflate(base64_decode($cache['cache_data'])) : stripslashes($cache['cache_data']));
					switch ($cache['cache_method']) {
						case 'EVAL': // must be PHP code
							$defines = tools::defineExplode($cache_data);

							foreach (($defines ?? []) as $key => $value) {
								if (!defined($key)) {
									define($key, $value);
								}
							}
							break;
						case 'ARRAY':
							$cache_data = unserialize($cache_data);
						case 'RETURN':
						default:
							break;
					} # end switch ($cache['cache_method'])
					if ($global) $container['GLOBAL'][$cache_name] = $cache_data;
					else $container[$cache_name] = $cache_data; // not global
				} else { // cache is expired
					if ($global) $container['GLOBAL'][$cache_name] = false;
					else $container[$cache_name] = false;
				}# end if ( $cache['cache_expires'] > @date("Y-m-d H:i:s") )
				if ($local_memory) {
					if ($global) $this->data['GLOBAL'][$cache_name] = $container['GLOBAL'][$cache_name];
					else $this->data[$cache_name] = $container[$cache_name];
				}
			}
			unset($cache_data);
			tep_db_free_result($this->cache_query);
			switch (true) {
				case ($num_rows == 1):
					if ($global) {
						if ($container['GLOBAL'][$cache_name] == false || !isset($container['GLOBAL'][$cache_name])) return false;
						else return $container['GLOBAL'][$cache_name];
					} else {
						if ($container[$cache_name] == false || !isset($container[$cache_name])) return false;
						else return $container[$cache_name];
					}
				case ($num_rows > 1):
				default:
					return $container;
					break;
			}# end switch (true)
		} else {
			return false;
		}
	}

	/**
	 * Function to get cache from memory
	 * @param string $name
	 * @param string $method
	 * @return mixed
	 */
	function get_cache_memory($name, $method = 'RETURN')
	{
		$data = (isset($this->data['GLOBAL'][$name]) ? $this->data['GLOBAL'][$name] : $this->data[$name]);
		if (isset($data) && !empty($data) && $data != false) {
			switch ($method) {
				case 'EVAL': // data must be PHP
					eval("$data");
					return true;
					break;
				case 'ARRAY':
				case 'RETURN':
				default:
					return $data;
					break;
			} # end switch ($method)
		} else {
			return false;
		} # end if (isset($data) && !empty($data) && $data != false)
	} # end function get_cache_memory()

	/**
	 * Function to perform basic garbage collection for database cache system
	 * @version 1.0
	 */
	function cache_gc()
	{
		tep_db_query("DELETE FROM cache WHERE cache_expires <= '" . @date("Y-m-d H:i:s") . "'");
	}

	/**
	 * Function to convert time for cache methods
	 * @param string $expires
	 * @return string
	 */
	function convert_time($expires)
	{ //expires date interval must be spelled out and NOT abbreviated !!
		$expires = explode('/', $expires);
		switch (strtolower($expires[1])) {
			case 'seconds':
				$expires = mktime(@date("H"), @date("i"), @date("s") + (int)$expires[0], @date("m"), @date("d"), @date("Y"));
				break;
			case 'minutes':
				$expires = mktime(@date("H"), @date("i") + (int)$expires[0], @date("s"), @date("m"), @date("d"), @date("Y"));
				break;
			case 'hours':
				$expires = mktime(@date("H") + (int)$expires[0], @date("i"), @date("s"), @date("m"), @date("d"), @date("Y"));
				break;
			case 'days':
				$expires = mktime(@date("H"), @date("i"), @date("s"), @date("m"), @date("d") + (int)$expires[0], @date("Y"));
				break;
			case 'months':
				$expires = mktime(@date("H"), @date("i"), @date("s"), @date("m") + (int)$expires[0], @date("d"), @date("Y"));
				break;
			case 'years':
				$expires = mktime(@date("H"), @date("i"), @date("s"), @date("m"), @date("d"), @date("Y") + (int)$expires[0]);
				break;
			default: // if something fudged up then default to 1 month
				$expires = mktime(@date("H"), @date("i"), @date("s"), @date("m") + 1, @date("d"), @date("Y"));
				break;
		} # end switch( strtolower($expires[1]) )
		return @date("Y-m-d H:i:s", $expires);
	} # end function convert_time()

	/**
	 * Function to check if the cache is in the database and expired
	 * @param string $name
	 * @param boolean $is_cached NOTE: passed by reference
	 * @param boolean $is_expired NOTE: passed by reference
	 */
	function is_cached($name, &$is_cached, &$is_expired)
	{ // NOTE: $is_cached and $is_expired is passed by reference !!
		$this->cache_query = tep_db_query("SELECT cache_expires FROM cache WHERE cache_id='" . md5($name) . "' AND cache_language_id='" . (int)$this->languages_id . "' LIMIT 1");
		$is_cached = (tep_db_num_rows($this->cache_query) > 0 ? true : false);
		if ($is_cached) {
			$check = tep_db_fetch_array($this->cache_query);
			$is_expired = ($check['cache_expires'] <= @date("Y-m-d H:i:s") ? true : false);
			unset($check);
		}
		tep_db_free_result($this->cache_query);
	}# end function is_cached()

	/**
	 * Function to initialize the redirect logic
	 */
	function check_redirect()
	{
		$this->need_redirect = false;
		$this->path_info = is_numeric(strpos(ltrim(getenv('PATH_INFO'), '/'), '/')) ? ltrim(getenv('PATH_INFO'), '/') : NULL;
		$this->uri = ltrim(basename($_SERVER['REQUEST_URI']), '/');
		$this->real_uri = ltrim(basename($_SERVER['SCRIPT_NAME']) . '?' . $_SERVER['QUERY_STRING'], '/');
		$this->uri_parsed = $this->not_null($this->path_info)
			? parse_url(basename($_SERVER['SCRIPT_NAME']) . '?' . $this->parse_path($this->path_info))
			: parse_url(basename($_SERVER['REQUEST_URI']));
		$this->attributes['SEO_REDIRECT']['PATH_INFO'] = $this->path_info;
		$this->attributes['SEO_REDIRECT']['URI'] = $this->uri;
		$this->attributes['SEO_REDIRECT']['REAL_URI'] = $this->real_uri;
		$this->attributes['SEO_REDIRECT']['URI_PARSED'] = $this->uri_parsed;


		/**** redirect child path to full path - i.e., -c-3782.html to -c-28_3782.html, when applicable ****/
		if (array_key_exists('path', $this->attributes['SEO_REDIRECT']['URI_PARSED']) && strpos($this->attributes['SEO_REDIRECT']['URI_PARSED']['path'], '.html') !== FALSE) {
			$u1 = $this->attributes['SEO_REDIRECT']['URI_PARSED']['path'];

			// Modificación para las -c- duplicadas
			$sIdCateogoria = preg_match('/-c-(\d+)\.html/', $u1, $matches2) ? $matches2[1] : '';

			if ($sIdCateogoria != '' && ($pStart = strpos($u1, $sIdCateogoria) - 3) !== FALSE) {
				if (($pStop = strpos($u1, ".html")) !== FALSE) {
					$path = substr($u1, $pStart, $pStop);             //will be something like -c-34.html
					if (($pStart = strpos($path, "-")) !== FALSE) {   //isolate to the number
						if (($pStop = strpos($path, ".html")) !== FALSE) {
							/**** GET THE ID's AND PATH's ****/
							$actualID = substr($path, $pStart + 3, $pStop - 3); //will be something like 34
							$fullID = $this->get_full_cPath($actualID, $actualID); //will be something like 34 or 34_35
							$actualPath = $actualID . '.html';        //save a few instructions

							/**** REPLACE THE PARTIAL ID IN THE URL's WITH THE FULL ONE ****/
							$idPos = strpos($this->attributes['SEO_REDIRECT']['REAL_URI'], $actualID);
							$this->attributes['SEO_REDIRECT']['REAL_URI'] = substr_replace($this->attributes['SEO_REDIRECT']['REAL_URI'], $fullID, $idPos, strlen($idPos));
							$idPos = strpos($this->attributes['SEO_REDIRECT']['URI'], $actualID);
							$this->attributes['SEO_REDIRECT']['URI'] = substr_replace($this->attributes['SEO_REDIRECT']['URI'], $fullID, $idPos, strlen($idPos));

							if (strpos($this->attributes['SEO_REDIRECT']['URI_PARSED']['path'], '-c-' . $actualPath) !== FALSE) { //this is the actual url
								if ($fullID != $actualID && strpos($fullID . '.html', $actualPath) !== FALSE) { //enteed url is child of full path
									$url = $this->make_url($page, $this->get_category_name($actualID), 'cPath', $fullID, '.html');
									$this->uri_parsed['path'] = $url; //reset the url
									$this->need_redirect = true;
									$this->is_seopage = true;
									if ($this->need_redirect && $this->is_seopage && $this->attributes['USE_SEO_REDIRECT'] == 'true') $this->do_redirect();
								}
							}
						}
					}
				}
			}
		}


		/**** redirect for special case of cat ID = 0 ****/
		if (array_key_exists('path', $this->attributes['SEO_REDIRECT']['URI_PARSED']) && strpos($this->attributes['SEO_REDIRECT']['URI_PARSED']['path'], '.html') !== FALSE) {
			$u1 = $this->attributes['SEO_REDIRECT']['URI_PARSED']['path'];

			// Modificación para las -c- duplicadas
			$sIdCateogoria = preg_match('/-c-(\d+)\.html/', $u1, $matches2) ? $matches2[1] : '';

			if ($sIdCateogoria != '' && ($pStart = strpos($u1, $sIdCateogoria) - 3) !== FALSE) {
				if (($pStop = strpos($u1, ".html")) !== FALSE) {

					$path = substr($u1, $pStart, $pStop + 5);             //will be something like -c-34.html

					if (($pStart = strpos($path, "-")) !== FALSE) {   //isolate to the number
						if (($pStop = strpos($path, ".html")) !== FALSE) {

							/**** GET THE ID's AND PATH's ****/
							$actualID = substr($path, $pStart + 3, $pStop - 3); //will be something like 34
							if ($actualID == 0) {
								$actualPath = $actualID . '.html';        //save a few instructions

								/**** REPLACE THE PARTIAL ID IN THE URL's WITH THE FULL ONE ****/
								$this->attributes['SEO_REDIRECT']['REAL_URI'] = 'index.php';
								$this->attributes['SEO_REDIRECT']['URI'] = '';

								if (strpos($this->attributes['SEO_REDIRECT']['URI_PARSED']['path'], '-c-' . $actualPath) !== FALSE) { //this is the actual url
									if (0 == $actualID && strpos($actualID . '.html', $actualPath) !== FALSE) { //enteed url is child of full path
										$url = 'index.php';
										$this->uri_parsed['path'] = $url; //reset the url
										$this->need_redirect = true;
										$this->is_seopage = true;
										if ($this->need_redirect && $this->is_seopage && $this->attributes['USE_SEO_REDIRECT'] == 'true') {
											header("HTTP/1.0 404 not found");
											header("Location: $url"); // redirect...bye bye
										}
									}
								}
							}
						}
					}
				}
			}
		}

		$this->need_redirect();
		$this->check_seo_page();
		if ($this->need_redirect && $this->is_seopage && $this->attributes['USE_SEO_REDIRECT'] == 'true') $this->do_redirect();
	} # end function

	function turnOffBrokenUrls()
	{
		if (defined('SEARCH_ENGINE_FRIENDLY_URLS') && SEARCH_ENGINE_FRIENDLY_URLS == 'true') {
			$sql = "
            UPDATE " . TABLE_CONFIGURATION . "
            SET configuration_value = 'false'
            WHERE configuration_key = 'SEARCH_ENGINE_FRIENDLY_URLS'";
			tep_db_query($sql);
		}
	}

	/**
	 * Function to check if the URL needs to be redirected
	 */
	function need_redirect()
	{
		global $SID;

		foreach ($this->reg_anchors as $param => $value) {
			$pattern[] = $param;
		}

		switch (true) {
			case ($this->is_attribute_string($this->uri)):
				$this->need_redirect = false;
				break;
			case ($this->uri != $this->real_uri && !$this->not_null($this->path_info)):

				// Si no contiene path lo creamos para que no de error
				if (!array_key_exists('path', $this->uri_parsed))
					$this->uri_parsed['path'] = '';

				// Modificación para las -p- duplicadas
				$sIdProductos = preg_match('/-p-(\d+)\.html/', $this->uri_parsed['path'], $matches1) ? $matches1[1] : '';

				// Id categoria
				$sIdCateogoria = '';

				// Posibilidades hasta 5 niveles de subcategorias
				$aRegex = array('\d+', '\d+_\d+', '\d+_\d+_\d+', '\d+_\d+_\d+_\d+', '\d+_\d+_\d+_\d+_\d+', '\d+_\d+_\d+_\d+_\d+_\d+');

				// Recorremos las posibles subcategorias
				foreach( $aRegex as $sRegex )
				{
					// Regex para encontrar las subcategorias con 0 o más guiones bajos antes del número de categoría
					preg_match( '/-c-(_*)(' . $sRegex . ')(?:_c-(\d+))?\.html/', $this->uri_parsed['path'], $matches2 );

					// Si contiene
					if( isset($matches2[2]) )
					{
						$sIdCateogoria = $matches2[2];
						break;
					}
				}

				if ($sIdProductos != '' && ($pStart = strpos($this->uri_parsed['path'], '-p-' . $sIdProductos)) !== FALSE) {
					if (($pStop = strpos($this->uri_parsed['path'], ".html")) !== FALSE) {

						$forceRedirect = $this->VerifyLink($pStop, $pStart); //remove things that shouldn't be there

						if ($this->attributes['USE_SEO_PERFORMANCE_CHECK'] == 'true') $this->performance['NUMBER_QUERIES']++;

						$pID = substr($this->uri_parsed['path'], $pStart + 3, -(strlen($this->uri_parsed['path']) - $pStop));

						$sqlCmd = $this->attributes['USE_SEO_HEADER_TAGS'] == 'true' ? 'products_head_title_tag as pName' : 'products_name as pName';
						$sql = tep_db_query("SELECT " . $sqlCmd . "
													FROM " . TABLE_PRODUCTS_DESCRIPTION . "
													WHERE products_id='" . (int)$pID . "'
													AND language_id='" . (int)$this->languages_id . "'
													LIMIT 1");
						$result = tep_db_fetch_array($sql);

						$cName = '';
						if ($this->attributes['SEO_ADD_CPATH_TO_PRODUCT_URLS'] == 'true') {
							$cName = $this->get_all_category_parents($pID, $cName);
							$cName = str_replace(" ", "-", $cName) . '-';
						}

						$pName = $cName . $this->strip($result['pName']);

						//Comprobamos si el nombre de la URL corresponde con el del producto. También vemos que si es un producto que está su nombre vacio (producto eliminado) no haga redirección - @Israel.Gavino
						if ($forceRedirect || ($pName !== '' && ($pName !== substr($this->uri_parsed['path'], 0, $pStart)))) {
							$this->uri_parsed['path'] = $pName . "-p-" . $pID . ".html";
							$this->need_redirect = true;
							$this->do_redirect();
						}
					}
				}

				if ($sIdCateogoria != '' && ($pStart = strpos($this->uri_parsed['path'], $sIdCateogoria) - 3) !== FALSE) {
					if (($pStop = strpos($this->uri_parsed['path'], ".html")) !== FALSE) {
						$forceRedirect = $this->VerifyLink($pStop, $pStart); //remove things that shouldn't be there
						$cID = substr($this->uri_parsed['path'], $pStart + 3, -(strlen($this->uri_parsed['path']) - $pStop));
						$cIDRedirect = $cID;

						if ($this->attributes['SEO_ADD_CAT_PARENT'] != 'true') {
							if (strpos($cID, "_") !== FALSE) { //test for sub-category
								$parts = explode("_", $cID);
								$cID = $parts[count($parts) - 1];
							}

							if ($this->attributes['USE_SEO_PERFORMANCE_CHECK'] == 'true')
								$this->performance['NUMBER_QUERIES']++;

							$cName = $this->aAllCategories[(int)$cID]['cName'];
							$sIds = '';
							$cPathFullID = $this->get_full_cPath($cID, $single_cID);
						} else {
							$cID = $this->get_full_cPath($cID, $single_cID); // full cPath needed for uniformity
							$cPathFullID = $cID;

							$cName = $this->aAllCategories[(int)$single_cID]['cName'];

							$sIds = '';
							if ($this->attributes['SEO_ADD_CAT_PARENT'] == 'true')
								$cName = $this->get_all_category_names($single_cID, $cName, $sIds);
						}

						$cName = $this->strip($cName);
						$sIds .= ($sIds != '' ? '_' : '') . $single_cID;

						// Comprobamos que el nombre sea perfecto
						if ($this->uri_parsed['path'] != $cName . '-c-' . $cPathFullID . '.html') {
							$forceRedirect = true;
							$cIDRedirect = $cPathFullID;
						}

						if ($forceRedirect || ($cName !== substr($this->uri_parsed['path'], 0, $pStart))) {
							$this->uri_parsed['path'] = $cName . "-c-" . $cIDRedirect . ".html";
							$this->need_redirect = true;
							$this->do_redirect();
						}
					}
				}

				// Marcas
				// Obtgenemos el ID de fabricante
				$sIdManufacturers = (preg_match('/-m-(\d+)\.html/', $this->uri_parsed['path'], $matches1) ? $matches1[1] : '');

				// Si tenemos id
				if ($sIdManufacturers != '') {
					// Url buena
					$sUrlGood = str_replace($this->href_link('/'), '', $this->href_link('manufacturers.php', 'manufacturers_id=' . $sIdManufacturers));

					// Si no es una url buena redirección
					if (strstr($sUrlGood, $this->uri_parsed['path']) === FALSE) {
						$this->uri_parsed['path'] = $sUrlGood;
						$this->need_redirect = true;
						$this->do_redirect();
					}
				}

				// Lading
				// Obtgenemos el ID de lading
				$sIdLandings = (preg_match('/-l-(\d+)\.html/', $this->uri_parsed['path'], $matches1) ? $matches1[1] : '');

				// Si tenemos id
				if ($sIdLandings != '') {
					// Url buena
					$sUrlGood = str_replace($this->href_link('/'), '', $this->href_link('landings.php', 'landing_id=' . $sIdLandings));

					// Si no es una url buena redirección
					if (strstr($sUrlGood, $this->uri_parsed['path']) === FALSE) {
						$this->uri_parsed['path'] = $sUrlGood;
						$this->need_redirect = true;
						$this->do_redirect();
					}
				}

				// Noticias
				// Obtgenemos el ID de noticias
				$sIdNoticias = (preg_match('/-n-(\d+)\.html/', $this->uri_parsed['path'], $matches1) ? $matches1[1] : '');

				// Si tenemos id
				if ($sIdNoticias != '') {
					require_once($_SERVER['DOCUMENT_ROOT'] . '/' . DIR_THEME . 'functions/functions.php');

					$aRow = tep_db_query('select titulo from noticia where id_noticia = ' . $sIdNoticias . ' and id_idioma = "' . (int)$this->languages_id . '"');
					$aRow = tep_db_fetch_array($aRow);
					$sUrlGood = htmlentities(truncate($aRow['titulo'], array('SIZE' => 100)) . '-n-' . $sIdNoticias . '.html');
					$sUrlGood = preg_replace('/&([a-zA-Z])(uml|acute|grave|circ|tilde);/', '$1', $sUrlGood);
					$sUrlGood = html_entity_decode($sUrlGood);
					$sUrlGood = strtolower($sUrlGood);
					$sUrlGood = preg_replace('/\W/', ' ', $sUrlGood);
					$sUrlGood = preg_replace('/\ +/', '-', $sUrlGood);
					$sUrlGood = trim($sUrlGood, '-');
					$sUrlGood = preg_replace('/\-(pr|pri|c|m|pi|i|pm|po|nc|nri)\-/i', '-', $sUrlGood);

					// Si no es una url buena redirección
					if (strstr($sUrlGood, $this->uri_parsed['path']) === FALSE) {
						$this->uri_parsed['path'] = $sUrlGood;
						$this->need_redirect = true;
						$this->do_redirect();
					}
				}

				$this->need_redirect = false;
				break;
			case (is_numeric(strpos($this->uri, '.htm'))):
				$this->need_redirect = false;
				break;
			case (@preg_match("/(" . @implode('|', $pattern) . ")/i", $this->uri)):
				$this->need_redirect = true;
				break;
			case (@preg_match("/(" . @implode('|', $pattern) . ")/i", $this->path_info)):
				$this->need_redirect = true;
				break;
			default:
				break;
		} # end switch

		// Si es pagina de información
		if (preg_match('/-i-(\d+)\.html$/', $this->uri, $matches)) {
			// Id de informacion
			$sId = $matches[1];

			// Url correcta
			$sUrlCorrecta = $this->make_url('information.php', $this->get_information_name($sId), 'info_id', $sId, '.html');

			// Si es distinta realizamos redirect
			if ($sUrlCorrecta !== $this->uri) {
				$this->uri_parsed['path'] = $sUrlCorrecta;
				$this->need_redirect = true;
				$this->do_redirect();
			}
		}

		$this->attributes['SEO_REDIRECT']['NEED_REDIRECT'] = $this->need_redirect ? 'true' : 'false';
	} # end function set_seopage


	/**
	 * Function to check if the url is valid
	 */
	function VerifyLink(&$pStop, $pStart)
	{
		global $connection;
		$r1 = ($connection == 'NONSSL' ? $this->base_url : $this->base_url_ssl) . $this->uri_parsed['path'];
		$p1 = strpos($_SERVER['REQUEST_URI'], $this->attributes['SEO_REDIRECT']['URI_PARSED']['path']);
		$r2 = substr($_SERVER['REQUEST_URI'], 0, $p1);
		if (strpos($r1, $r2) === FALSE) {
			return true;
		}

		/*** begin check for characters at end of string before .html ***/
		$endStr = substr($this->uri_parsed['path'], $pStart + 3, $pStop - $pStart - 3);
		if (!preg_match("/^([0-9_]+)$/", $endStr)) {
			$parts = explode("_", $endStr);
			for ($p = 0; $p < count($parts); ++$p) {
				$parts[$p] = (int)$parts[$p];
			}
			$newStr = implode("_", $parts);
			$this->uri_parsed['path'] = str_replace($endStr, $newStr, $this->uri_parsed['path']);
			$pStop = strpos($this->uri_parsed['path'], ".html"); //recalculate the end
			return true;
		}

		return false;
	}

	/**
	 * Function to check if it's a valid redirect page
	 */
	function check_seo_page()
	{
		switch (true) {
			case (@in_array($this->uri_parsed['path'], $this->attributes['SEO_PAGES'])):
				$this->is_seopage = true;
				break;
			case ($this->attributes['SEO_ENABLED'] == 'false'):
			default:
				$this->is_seopage = false;
				break;
		} # end switch
		$this->attributes['SEO_REDIRECT']['IS_SEOPAGE'] = $this->is_seopage ? 'true' : 'false';
	} # end function check_seo_page

	/**
	 * Function to parse the path for old SEF URLs
	 * @param string $path_info
	 * @return array
	 */
	function parse_path($path_info)
	{
		$tmp = @explode('/', $path_info);
		if (count($tmp) > 2) {
			$container = array();
			for ($i = 0, $n = count($tmp); $i < $n; $i++) {
				$container[] = $tmp[$i] . '=' . $tmp[$i + 1];
				$i++;
			}
			return @implode('&', $container);
		} else {
			return @implode('=', $tmp);
		}
	} # end function parse_path

	/**
	 * Function to perform redirect
	 * @Modificada para añadir compatibilidad a SSL y que no haga 2 redirecciones (SEO On page)
	 */
	function do_redirect()
	{
		global $request_type;
		$p = @explode('&', $this->uri_parsed['query']);

		foreach ($p as $index => $value) {
			$tmp = @explode('=', $value);
			switch ($tmp[0]) {
				case 'products_id':
					if ($this->is_attribute_string($tmp[1])) {
						$pieces = @explode('{', $tmp[1]);
						$params[] = (tep_not_null($tmp[0]) ? $tmp[0] . '=' . $pieces[0] : '');
					} else {
						$params[] = (tep_not_null($tmp[0]) ? $tmp[0] . '=' . $tmp[1] : '');
					}
					break;
				default:
					$params[] = (tep_not_null($tmp[0]) ? $tmp[0] . '=' . $tmp[1] : '');
					break;
			}
		} # end foreach( $params as $var => $value )
		$params = (count($params) > 1 ? implode('&', $params) : $params[0]);
		$url = $this->href_link($this->uri_parsed['path'], $params, $request_type, false);

		switch (true) {
			case (defined('USE_SEO_REDIRECT_DEBUG') && USE_SEO_REDIRECT_DEBUG == 'true'):
				$this->attributes['SEO_REDIRECT']['REDIRECT_URL'] = $url;
				break;
			case ($this->attributes['USE_SEO_REDIRECT'] == 'true'):
				header("HTTP/1.0 301 Moved Permanently");
				$url = str_replace('&amp;', '&', $url);
				header("Location: $url"); // redirect...bye bye
				exit();
				break;
			default:
				$this->attributes['SEO_REDIRECT']['REDIRECT_URL'] = $url;
				break;
		}
	}
} # end class
