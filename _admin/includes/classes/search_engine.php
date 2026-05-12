<?php
/*
  Search Engine by pbor1234

  Inspired by Sphider http://www.sphider.eu/

  Released under the GNU General Public License

  @todo add search-suggest (using ajax)
  @todo query-log for search-suggests
  @todo search statistics (present top searches)
  @todo hoe bepalen of we in 'search' mode zitten of niet? Denk zolang zoekterm in de url zit, filter-linkjes geven zoekterm opnieuw door.
  @todo filters (results) cachen in oscCache; zodat doorklikken sneller gaat; eerst meten!
  @todo LIKE ipv match; werkt niet goed omdat 'test' en 'tes' in andere subtables komen...
*/

  require_once(DIR_WS_CLASSES . '/' . 'table_block.php');
  require_once(DIR_WS_CLASSES . '/' . 'box.php');

  define('SE_DELIM', '~');
  define('SE_FACET_KEYWORD', 'fk');
  define('SE_FACET_CATEGORY', 'fc');
  define('SE_FACET_MANUFACTURER', 'fm');
  define('SE_FACET_PRICE', 'fp');
  define('SE_FACET_RATING', 'fr');
  define('SE_FACET_OPTIONS', 'fo');
  define('SE_FACET_EXTRA_FIELDS', 'fe');

  $entities = array(
    "&amp" => "&",
    "&apos" => "'",
    "&THORN;"  => "Þ",
    "&szlig;"  => "ß",
    "&agrave;" => "à",
    "&aacute;" => "á",
    "&acirc;"  => "â",
    "&atilde;" => "ã",
    "&auml;"   => "ä",
    "&aring;"  => "å",
    "&aelig;"  => "æ",
    "&ccedil;" => "ç",
    "&egrave;" => "è",
    "&eacute;" => "é",
    "&ecirc;"  => "ê",
    "&euml;"   => "ë",
    "&igrave;" => "ì",
    "&iacute;" => "í",
    "&icirc;"  => "î",
    "&iuml;"   => "ï",
    "&eth;"    => "ð",
    "&ntilde;" => "ñ",
    "&ograve;" => "ò",
    "&oacute;" => "ó",
    "&ocirc;"  => "ô",
    "&otilde;" => "õ",
    "&ouml;"   => "ö",
    "&oslash;" => "ø",
    "&ugrave;" => "ù",
    "&uacute;" => "ú",
    "&ucirc;"  => "û",
    "&uuml;"   => "ü",
    "&yacute;" => "ý",
    "&thorn;"  => "þ",
    "&yuml;"   => "ÿ",
    "&THORN;"  => "Þ",
    "&szlig;"  => "ß",
    "&Agrave;" => "à",
    "&Aacute;" => "á",
    "&Acirc;"  => "â",
    "&Atilde;" => "ã",
    "&Auml;"   => "ä",
    "&Aring;"  => "å",
    "&Aelig;"  => "æ",
    "&Ccedil;" => "ç",
    "&Egrave;" => "è",
    "&Eacute;" => "é",
    "&Ecirc;"  => "ê",
    "&Euml;"   => "ë",
    "&Igrave;" => "ì",
    "&Iacute;" => "í",
    "&Icirc;"  => "î",
    "&Iuml;"   => "ï",
    "&ETH;"    => "ð",
    "&Ntilde;" => "ñ",
    "&Ograve;" => "ò",
    "&Oacute;" => "ó",
    "&Ocirc;"  => "ô",
    "&Otilde;" => "õ",
    "&Ouml;"   => "ö",
    "&Oslash;" => "ø",
    "&Ugrave;" => "ù",
    "&Uacute;" => "ú",
    "&Ucirc;"  => "û",
    "&Uuml;"   => "ü",
    "&Yacute;" => "ý",
    "&Yhorn;"  => "þ",
    "&Yuml;"   => "ÿ"
  );
  
  function cmpDelimKey($a, $b)
  {
    $a = preg_replace('/' . SE_DELIM . '[0-9]*' . SE_DELIM . '/', '', $a);
    $b = preg_replace('/' . SE_DELIM . '[0-9]*' . SE_DELIM . '/', '', $b);
    $matches_a = array();
    $matches_b = array();
    if(preg_match('/([0-9]+).*/', $a, $matches_a) && preg_match('/([0-9]+).*/', $b, $matches_b)) {
      return (float)$matches_a[1] > (float)$matches_b[1];
    } else {
      return strcasecmp($a, $b);
    }
  }          
  
  /**
   * Business logic around the search-engine function is isolated to this class
   *
   * Class is used by admin/search_engine to present the functions on an admin page and by a cronjob
   */
  class search_engine {
    function __construct() {
      
      $this->facets = array(); //the items for faceted search
      $this->catalog_query = array(); //the raw query (parts) used for the product query itself
      
      $this->infoBoxHeading = array();
      $this->infoBoxContents = array();

      $this->init_db();
      
      $this->ignoreWords = array();
      if(file_exists(SE_STOPWORDS_FILE)) {
        $stopwords = @file(SE_STOPWORDS_FILE);
        foreach($stopwords as $word) {
          $this->ignoreWords[trim($word)] = 0;
        }
      }
    }
    
    /**
     * Function to initialize or reset configuration items related to search-engine function
     *
     * @param unknown_type $force
     */
    function init_db($force = false) {
      global $language;
      
      $this->infoBoxHeading = array();
      $this->infoBoxContents = array();
      
      //Configuration
      $query = tep_db_query("SELECT configuration_group_id FROM " . TABLE_CONFIGURATION_GROUP . " WHERE configuration_group_title = 'Search Engine'");
      if ((tep_db_num_rows($query) != 0) && (true === $force)) {
        while ($row = tep_db_fetch_array($query)) {
          tep_db_query("delete from configuration where configuration_group_id = '" . (int)$row['configuration_group_id'] . "'");
          tep_db_query("delete from configuration_group where configuration_group_id = '" . (int)$row['configuration_group_id'] . "'");
        }
      }
      
      if ((tep_db_num_rows($query) == 0) || (true === $force)) {
        $configuration_group_add = array(
          "configuration_group_title" => "Search Engine",
          "configuration_group_description" => "Configuration of the search engine",
          "visible" => 1);
          
        tep_db_perform("configuration_group", $configuration_group_add);
        $configuration_group_id = tep_db_insert_id();
        tep_db_query("UPDATE configuration_group SET sort_order = '" . $configuration_group_id . "' WHERE configuration_group_id = '" . $configuration_group_id . "'");
        
        $sort_order = 0;
        $add_value = array(
          "configuration_title" => "Stopwords file",
          "configuration_key" => "SE_STOPWORDS_FILE",
          "configuration_value" => DIR_FS_CATALOG . DIR_WS_LANGUAGES . $language . '/stopwords.txt',
          "configuration_description" => "Point to a file containing stopwords",
          "configuration_group_id" => $configuration_group_id,
          "sort_order" => $sort_order,
          "date_added" => 'now()',
          "use_function" => "",
          "set_function" => "");
        tep_db_perform("configuration", $add_value);
        $sort_order++;
        
        $add_value = array(
          "configuration_title" => "Minimal word length",
          "configuration_key" => "SE_MIN_WORD_LENGTH",
          "configuration_value" => 3,
          "configuration_description" => "The minimal length a word should have to become part of the index",
          "configuration_group_id" => $configuration_group_id,
          "sort_order" => $sort_order,
          "date_added" => 'now()',
          "use_function" => NULL,
          "set_function" => NULL);
        tep_db_perform("configuration", $add_value);
        $sort_order++;
        
        $add_value = array(
          "configuration_title" => "Maximum word count",
          "configuration_key" => "SE_MAX_WORD_COUNT",
          "configuration_value" => 10,
          "configuration_description" => "The number of occurances of a word is a factor to the weight of the keyword, this configuration ceils the maximum count on a per-word-basis",
          "configuration_group_id" => $configuration_group_id,
          "sort_order" => $sort_order,
          "date_added" => 'now()',
          "use_function" => NULL,
          "set_function" => NULL);
        tep_db_perform("configuration", $add_value);
        $sort_order++;
        
        $add_value = array(
          "configuration_title" => "Index numerical values",
          "configuration_key" => "SE_INDEX_NUMBERS",
          "configuration_value" => 1,
          "configuration_description" => "Determines whether numbers are indexed",
          "configuration_group_id" => $configuration_group_id,
          "sort_order" => $sort_order,
          "date_added" => 'now()',
          "use_function" => NULL,
          "set_function" => 'tep_cfg_select_option(array(\'true\', \'false\'), ');
        tep_db_perform("configuration", $add_value);
        $sort_order++;

        $add_value = array(
          "configuration_title" => "Relative weight of words in the product model",
          "configuration_key" => "SE_WEIGHT_PM",
          "configuration_value" => 30,
          "configuration_description" => "The value is relative to the other weight entries",
          "configuration_group_id" => $configuration_group_id,
          "sort_order" => $sort_order,
          "date_added" => 'now()',
          "use_function" => NULL,
          "set_function" => NULL);
        tep_db_perform("configuration", $add_value);
        $sort_order++;

        $add_value = array(
          "configuration_title" => "Relative weight of words in the productname",
          "configuration_key" => "SE_WEIGHT_PN",
          "configuration_value" => 60,
          "configuration_description" => "The value is relative to the other weight entries",
          "configuration_group_id" => $configuration_group_id,
          "sort_order" => $sort_order,
          "date_added" => 'now()',
          "use_function" => NULL,
          "set_function" => NULL);
        tep_db_perform("configuration", $add_value);
        $sort_order++;
        
        $add_value = array(
          "configuration_title" => "Relative weight of words in the product description",
          "configuration_key" => "SE_WEIGHT_PD",
          "configuration_value" => 40,
          "configuration_description" => "The value is relative to the other weight entries",
          "configuration_group_id" => $configuration_group_id,
          "sort_order" => $sort_order,
          "date_added" => 'now()',
          "use_function" => NULL,
          "set_function" => NULL);
        tep_db_perform("configuration", $add_value);
        $sort_order++;

        $add_value = array(
          "configuration_title" => "Relative weight of words in the manufacturer name",
          "configuration_key" => "SE_WEIGHT_MN",
          "configuration_value" => 30,
          "configuration_description" => "The value is relative to the other weight entries",
          "configuration_group_id" => $configuration_group_id,
          "sort_order" => $sort_order,
          "date_added" => 'now()',
          "use_function" => NULL,
          "set_function" => NULL);
        tep_db_perform("configuration", $add_value);
        $sort_order++;

        $add_value = array(
          "configuration_title" => "Relative weight of words in the category tree",
          "configuration_key" => "SE_WEIGHT_CT",
          "configuration_value" => 20,
          "configuration_description" => "The value is relative to the other weight entries",
          "configuration_group_id" => $configuration_group_id,
          "sort_order" => $sort_order,
          "date_added" => 'now()',
          "use_function" => NULL,
          "set_function" => NULL);
        tep_db_perform("configuration", $add_value);
        $sort_order++;
        
        $add_value = array(
          "configuration_title" => "Suggest search words when results are less than amount entered here",
          "configuration_key" => "SE_DID_YOU_MEAN_SEARCH_RESULT_THRESHOLD",
          "configuration_value" => 25,
          "configuration_description" => "Did you mean... suggests are given as part of the search-result.",
          "configuration_group_id" => $configuration_group_id,
          "sort_order" => $sort_order,
          "date_added" => 'now()',
          "use_function" => NULL,
          "set_function" => NULL);
        tep_db_perform("configuration", $add_value);
        $sort_order++;
        
        $add_value = array(
          "configuration_title" => "Select the method of keyword suggestion",
          "configuration_key" => "SE_DID_YOU_MEAN_METHOD",
          "configuration_value" => 'mysql (soundex)', //aspell/pspell',
          "configuration_description" => "Suggestions for keywords are found be either querying the database for it (the mysql option) or through aspell/pspell integration.",
          "configuration_group_id" => $configuration_group_id,
          "sort_order" => $sort_order,
          "date_added" => 'now()',
          "use_function" => NULL,
          "set_function" => 'tep_cfg_select_option(array(\'aspell/pspell\', \'mysql (soundex)\'), ');
        tep_db_perform("configuration", $add_value);
        $sort_order++;

        $add_value = array(
          "configuration_title" => "Location of aspell data and dictionaries",
          "configuration_key" => "SE_ASPELL_PATH",
          "configuration_value" => DIR_FS_CATALOG . DIR_WS_LANGUAGES . $language . '/aspell/',
          "configuration_description" => "",
          "configuration_group_id" => $configuration_group_id,
          "sort_order" => $sort_order,
          "date_added" => 'now()',
          "use_function" => NULL,
          "set_function" => NULL);
        tep_db_perform("configuration", $add_value);
        $sort_order++;

        $add_value = array(
          "configuration_title" => "Facet price range",
          "configuration_key" => "SE_FACET_PRICE_STEPS",
          "configuration_value" => '5;10;25;50;100',
          "configuration_description" => "Defines the price-ranges that build up the price facet. For example \"5;10;25;50;100\" will produce 6 ranges: <5 5>10 10>25 25>50 50>100 and >100",
          "configuration_group_id" => $configuration_group_id,
          "sort_order" => $sort_order,
          "date_added" => 'now()',
          "use_function" => NULL,
          "set_function" => NULL);
        tep_db_perform("configuration", $add_value);
        $sort_order++;

        $add_value = array(
          "configuration_title" => "Facet product options",
          "configuration_key" => "SE_FACET_PRODUCT_OPTIONS",
          "configuration_value" => '',//a:2:{i:0;s:1:"3";i:1;s:1:"1";}',
          "configuration_description" => "Select the product options that should be visible as a facet",
          "configuration_group_id" => $configuration_group_id,
          "sort_order" => $sort_order,
          "date_added" => 'now()',
          "use_function" => 'tep_get_multiple_product_options_names',
          "set_function" => 'tep_cfg_pull_down_multiple_product_options(');
        tep_db_perform("configuration", $add_value);
        $sort_order++;
        
        $add_value = array(
          "configuration_title" => "Facet product options (extra fields)",
          "configuration_key" => "SE_FACET_PRODUCT_EXTRA_FIELDS",
          "configuration_value" => '',//a:1:{i:0;s:1:"2";}',
          "configuration_description" => "Select the extra field options that should be visible as a facet; only applicable when extra-fields contribution is installed",
          "configuration_group_id" => $configuration_group_id,
          "sort_order" => $sort_order,
          "date_added" => 'now()',
          "use_function" => 'tep_get_multiple_product_extra_fields_names',
          "set_function" => 'tep_cfg_pull_down_multiple_product_extra_fields(');
        tep_db_perform("configuration", $add_value);
        $sort_order++;
        
        $this->infoBoxContents[] = array(
          0 => array(
            'params' => 'class="infoBoxHeading" align="left" nowrap',
            'text' => 'Configuration reinitialized'));
      }   
    }
    
    /**
     * Removes all index'ed data from the database
     *
     */
    function clean() {
      tep_db_query("TRUNCATE " . TABLE_SE_KEYWORDS); 
      for ($i = 0; $i <= 15; $i++) {
        $char = dechex($i);
        tep_db_query("TRUNCATE " . sprintf(TABLE_SE_LINK_KEYWORD, $char));
      } 
      tep_db_query("TRUNCATE " . TABLE_SE_LINKS);
      //tep_db_query("TRUNCATE " . TABLE_SE_QUERY_LOG); 
    }
    
    /**
     * Remove indexed keywords from the database that are not related to a product
     *
     */
    function cleanUnrelatedKeywords() {
      $keyword_ids = array();
      $query = tep_db_query($this->getUnrelatedKeywordsQuery());
      while($row = tep_db_fetch_array($query)) {
        $keyword_ids[] = $row['keyword_id'];
      }
      
      if(0 != count($keyword_ids)) {
        tep_db_query("DELETE FROM " . TABLE_SE_KEYWORDS . " WHERE keyword_id IN (". implode(",", $keyword_ids) . ")"); 
        for ($i = 0; $i <= 15; $i++) {
          $char = dechex($i);
          tep_db_query("DELETE FROM " . sprintf(TABLE_SE_LINK_KEYWORD, $char) . " WHERE keyword_id IN (". implode(",", $keyword_ids) . ")");
        }
      } 
    }
    
    /**
     * Helper function to compose a query
     *
     * @return unknown
     */
    function getUnrelatedKeywordsQuery() {
      $query_raw = "
        SELECT 
          sek.keyword_id
        FROM 
          " . TABLE_SE_KEYWORDS . " sek";
      for ($i = 0; $i <= 15; $i++) {
        $selk_table_name = sprintf(TABLE_SE_LINK_KEYWORD, dechex($i));
        $selk_name = "selk" . dechex($i);
        $query_raw .= " LEFT JOIN " . $selk_table_name . " " . $selk_name . " ON (sek.keyword_id = " . $selk_name . ".keyword_id)"; 
      }
      $query_raw .= " WHERE ";
      for ($i = 0; $i <= 15; $i++) {
        $selk_name = "selk" . dechex($i);
        $query_raw .= $selk_name . ".keyword_id IS NULL AND ";
      }
      $query_raw = substr($query_raw, 0, count($query_raw) - 5);
      //die(tep_sql_pretty_print($query_raw));
      return $query_raw;
    }
    
    /**
     * Retrieve logging in html format
     *
     * @return unknown
     */
    function getInfoBox() {
      $box = new box;
      return $box->infoBox($this->infoBoxHeading, $this->infoBoxContents);
    }

    /**
     * Retrieves some statistical information on the search engine
     *
     * @return unknown
     */
    function getStatistics() {
      $results = array();
      
      $query = tep_db_query("SELECT count(*) as count FROM " . TABLE_SE_KEYWORDS . " sek WHERE 1");
      $row = tep_db_fetch_array($query);
      $results['num_indexed_keywords'] = $row['count'];
      
      $query = tep_db_query("SELECT count(*) as count FROM " . TABLE_PRODUCTS . " p RIGHT JOIN " . TABLE_SE_LINKS . " sel ON (sel.products_id = p.products_id) WHERE p.products_status = '1'");
      $row = tep_db_fetch_array($query);
      $results['num_indexed_products'] = $row['count'];
      
      $query = tep_db_query("SELECT count(*) as count FROM " . TABLE_PRODUCTS . " p WHERE p.products_status = '1'");
      $row = tep_db_fetch_array($query);
      $results['num_products'] = $row['count'];

      $query = tep_db_query($this->getUnrelatedKeywordsQuery());
      $results['num_unlinked_keywords'] = tep_db_num_rows($query);
      
      return $results;
    }
    
    /**
     * Format a flat array of values into an unordered list
     *
     * @param unknown_type $flat
     */
    function flatToUl($vars, &$flat) {
      $output = '';
      if(is_array($flat)) {
        $output .= '<ul class="se_facet">';
        foreach($flat as $key => $value) {
          $matches = array();
          if(preg_match('/' . SE_DELIM . '([0-9_]+)' . SE_DELIM . '(.*)/', $key, $matches)) {
            $output .= '<li><a href="' . tep_href_link(FILENAME_SEARCH_ENGINE, $vars . $matches[1]) . '">' . $matches[2] . ' (' . $value . ')</a></li>';
          }
        }
        $output .= '</ul>';
      }
      return $output;
    }
    
    /**
     * Format a hyrarchical array of values into an unordered list
     * 
     * @return unknown
     */
    function hierarchyToUl($vars, &$hierarchy, &$products_count = 0) {
      $output = '';
      if(is_array($hierarchy)) {
        $output .= '<ul class="se_facet">';
        foreach($hierarchy as $key => $value) {
          $matches = array();
          if(preg_match('/' . SE_DELIM . '([0-9]+)' . SE_DELIM . '(.*)/', $key, $matches)) {
            //its a sub-category
            $products_subcat_count = 0;
            $output_subcat = $this->hierarchyToUl($vars, $value, $products_subcat_count);
            $output .= '<li><a href="' . tep_href_link(FILENAME_SEARCH_ENGINE, $vars . $matches[1]) . '">' . $matches[2] . ' (' . $products_subcat_count . ')</a>' . $output_subcat . '</li>';
            $products_count += $products_subcat_count;
          } else {
            //its a product
            $products_count++;
          }
        }
        $output .= '</ul>';
      }
      return $output;
    }
    
    /**
     * Copied from sphider, to remove accents from a string
     *
     * @param unknown_type $string
     * @return unknown
     */
    function remove_accents($string) {
      return (strtr($string, 
        "ÀÁÂÃÄÅÆàáâãäåæÒÓÔÕÕÖØòóôõöøÈÉÊËèéêëðÇçÐÌÍÎÏìíîïÙÚÛÜùúûüÑñÞßÿý",
        "aaaaaaaaaaaaaaoooooooooooooeeeeeeeeecceiiiiiiiiuuuuuuuunntsyy"));
    }
    
    /**
     * Extracts keywords from given text
     * 
     */
    function getKeywords(&$text, $applyStopwords = true) {
      global $entities;
      $tmp = preg_replace('~&#x([0-9a-f]+);~ei', 'chr(hexdec("\\1"))', strtolower($text));
      $tmp = preg_replace('~&#([0-9]+);~e', 'chr("\\1")', $tmp);
      reset($entities);
      foreach ($entities as $char) {
        $tmp = preg_replace("/".$char[0]."/i", $char[1], $tmp);
      }
      $tmp = preg_replace("/&[a-z]{1,6};/", " ", $tmp);
      $tmp = preg_replace("/[\*\^\+\?\\\.\[\]\^\$\|\{\)\(\}~!\"\/@#£$%&=`´;><:,]+/", " ", $tmp);
      $tmp = preg_replace("/\s+/", " ", $tmp);
      $words = explode(" ", $tmp);
      //$words = explode(" ", preg_replace("/[^[:alnum:]-]+/i", " ", $tmp));
      sort($words);

      if (SE_INDEX_NUMBERS == 1) {
        $pattern = "/[a-z0-9]+/";
      } else {
        $pattern = "/[a-z]+/";
      }
    
      //filter on length, pattern and stopwords
      $keywords = array();
      foreach($words as $word) {
        if(array_key_exists($word, $keywords)) {
          $keywords[$word] = min($keywords[$word] + 1, SE_MAX_WORD_COUNT);
        } else {
          if (
            (strlen($word) >= SE_MIN_WORD_LENGTH) && 
            (preg_match($pattern, $this->remove_accents($word))) && 
            (false === $applyStopwords || FALSE == array_key_exists($word, $this->ignoreWords))) {
            $keywords[$word] = 1;
          }
        }
      }
      
      return $keywords;
    }
    
    /**
     * Extracts keywords from given query
     * @todo Filter invalid input
     * @todo bool operators, quotes, ...
     */
    function getSearchKeywords(&$query) {
      $keywords = preg_split('/[[:space:]]+/', trim(strtolower($query)));
      return $keywords;
    }
    
    /**
     * @todo Performance is TERRIBLE of such linear functions but the array_merge cannot be used since it doesn't sum the value for equal keys
     * 
     * There must be a better way of doing this; i wish this was c++ ;)
     *
     * @param unknown_type $kw1
     * @param unknown_type $kw2
     */
    function MergeKeywordCollections(&$kw1, &$kw2) {
      $duplicates = array_intersect_key($kw1, $kw2);
      foreach($duplicates as $word => &$occurance) {
        $occurance += $kw2[$word];
      }
      $kw1 = array_merge($kw1, $kw2, $duplicates);
    }
    
    /**
     * Compose collection of keywords with weight
     *
     * @param unknown_type $row
     * @param unknown_type $se_product_path_tree
     * @param unknown_type $se_data
     */
    function getKeywordsArray(&$product_data, &$se_data) {
      $total_weight = SE_WEIGHT_PN + SE_WEIGHT_PD + SE_WEIGHT_MN;
      
      $keywords_products_model = $this->getKeywords($product_data['products_model']);
      $keywords_products_name = $this->getKeywords($product_data['products_name']);
      $keywords_products_description = $this->getKeywords($product_data['products_description']);
      $keywords_manufacturers_name = $this->getKeywords($product_data['manufacturers_name']);
      $keywords_category_tree = $this->getKeywords(implode(" ", tep_getTextFromTree($product_data['product_path_tree'])));
      
      $allkeywords = array();
      $this->MergeKeywordCollections($allkeywords, $keywords_products_model);
      $this->MergeKeywordCollections($allkeywords, $keywords_products_name);
      $this->MergeKeywordCollections($allkeywords, $keywords_products_description);
      $this->MergeKeywordCollections($allkeywords, $keywords_manufacturers_name);
      $this->MergeKeywordCollections($allkeywords, $keywords_category_tree);
      
      //calculate weight and format
      foreach($allkeywords as $word => $occurance) {
        $occurance_pm = ((TRUE == array_key_exists($word, $keywords_products_model)) ? $keywords_products_model[$word] : 0);
        $occurance_pn = ((TRUE == array_key_exists($word, $keywords_products_name)) ? $keywords_products_name[$word] : 0);
        $occurance_pd = ((TRUE == array_key_exists($word, $keywords_products_description)) ? $keywords_products_description[$word] : 0);
        $occurance_mn = ((TRUE == array_key_exists($word, $keywords_manufacturers_name)) ? $keywords_manufacturers_name[$word] : 0);
        $occurance_ct = ((TRUE == array_key_exists($word, $keywords_category_tree)) ? $keywords_category_tree[$word] : 0);
        
        $weight = (int)((
          ((SE_WEIGHT_PM * $occurance_pm) / $total_weight) +
          ((SE_WEIGHT_PN * $occurance_pn) / $total_weight) +
          ((SE_WEIGHT_PD * $occurance_pd) / $total_weight) +
          ((SE_WEIGHT_MN * $occurance_mn) / $total_weight) +
          ((SE_WEIGHT_CT * $occurance_ct) / $total_weight)) * 100);
          
        $se_data[$word] = $weight;
      }
    }
    
    /**
     * Content of the product has changes, remove the links, then invoke insert to recreate them
     *
     * @param unknown_type $products_id
     * @param unknown_type $se_data
     */
    function keywordsUpdate($products_id, $language_id, &$se_data) {
      for ($i = 0; $i <= 15; $i++) {
        tep_db_query("DELETE FROM " . sprintf(TABLE_SE_LINK_KEYWORD, dechex($i)) . " WHERE link_id = '" . (int)$products_id . "' and sek.language_id = '" . (int)$language_id . "'");
      }
      $this->keywordsInsert($products_id, $language_id, $se_data);
    }
    
    /**
     * Insert (new) keywords into the database
     *
     * @param unknown_type $products_id
     * @param unknown_type $se_data
     */
    function keywordsInsert($products_id, $language_id, &$se_data) {
      foreach($se_data as $word => $weight) {
        if (strlen($word)<= 30) {
          //Keywords
          $query_raw = "SELECT sek.keyword_id FROM " . TABLE_SE_KEYWORDS . " sek WHERE sek.keyword = '" . tep_db_input($word) . "' and sek.language_id = '" . (int)$language_id . "'";
          $query = tep_db_query($query_raw);
          if(0 != tep_db_num_rows($query)) {
            $row = tep_db_fetch_array($query);
            $keyword_id = $row['keyword_id'];
          } else {
            $se_sql_data_array = array('language_id' => (int)$language_id, 'keyword' => $word);
            tep_db_perform(TABLE_SE_KEYWORDS, $se_sql_data_array);
            $keyword_id = tep_db_insert_id();
          }
          
          //LinkKeyword
          $se_sql_data_array = array(
            'link_id' => (int)$products_id, 
            'keyword_id' => (int)$keyword_id, 
            'weight' => (int)$weight);
          tep_db_perform(sprintf(TABLE_SE_LINK_KEYWORD, substr(md5($word), 0, 1)), $se_sql_data_array);
        }
      }
    }
    
    /**
     * Function to generate a list of stopwords based on occurances
     *
     * @todo multi-language
     */
    function generateStopwords($maxamount, $filename, $addOccurance = false) {
      global $logger;
      if (!is_object($logger)) $logger = new logger;
      
      $this->infoBoxContents[] = array(0 => array('params' => '', 'text' => 'generateStopwords (start) ' . $logger->timer_stop('true')));
      
      $allkeywords = array();
      $products_data = $this->retrieveProductData();
      foreach($products_data as $product_data) {
        $keywords_products_model = $this->getKeywords($product_data['products_model'], false);
        $keywords_products_name = $this->getKeywords($product_data['products_name'], false);
        $keywords_products_description = $this->getKeywords($product_data['products_description'], false);
        $keywords_manufacturers_name = $this->getKeywords($product_data['manufacturers_name'], false);
        $keywords_category_tree = $this->getKeywords(implode(" ", tep_getTextFromTree($product_data['product_path_tree'])), false);
        
        $this->MergeKeywordCollections($allkeywords, $keywords_products_model);
        $this->MergeKeywordCollections($allkeywords, $keywords_products_name);
        $this->MergeKeywordCollections($allkeywords, $keywords_products_description);
        $this->MergeKeywordCollections($allkeywords, $keywords_manufacturers_name);
        $this->MergeKeywordCollections($allkeywords, $keywords_category_tree);
      }
      $this->infoBoxContents[] = array(0 => array('params' => '', 'text' => 'generateStopwords (data collected) ' . $logger->timer_stop('true')));
      arsort($allkeywords);
      $this->infoBoxContents[] = array(0 => array('params' => '', 'text' => 'generateStopwords (data sorted) ' . $logger->timer_stop('true')));
      
      $handle = fopen($filename, "w");
      $count = 0;
      foreach($allkeywords as $word => $occurance) {
        fwrite($handle, $word . ((true === $addOccurance) ? ' ' . $occurance : '') . "\r\n");
        if(++$count >= $maxamount) {
          break;
        }
      }
      fclose($handle);
      $this->infoBoxContents[] = array(0 => array('params' => '', 'text' => 'generateStopwords (end) ' . $logger->timer_stop('true')));
    }
    
    /*function generateAspellDictionary() {
      if(file_exists(SE_ASPELL_PATH . 'custom.pws')) {
        unlink(SE_ASPELL_PATH . 'custom.pws');
      }
      $pspell_config = pspell_config_create("nl");
      pspell_config_data_dir($pspell_config, SE_ASPELL_PATH . 'data');
      pspell_config_dict_dir($pspell_config, SE_ASPELL_PATH . 'dict');
      pspell_config_personal($pspell_config, SE_ASPELL_PATH . 'custom.pws');
      $pspell_link = pspell_new_config($pspell_config);
      
      $query_raw = "SELECT sek.keyword FROM " . TABLE_SE_KEYWORDS . " sek WHERE 1";
      $query = tep_db_query($query_raw);
      while($row = tep_db_fetch_array($query)) {
        pspell_add_to_personal($pspell_link, $row['keyword']);
      }      
      pspell_save_wordlist($pspell_link);
      
      $this->infoBoxContents[] = array(0 => array('params' => '', 'text' => 'Generated ' . tep_db_num_rows($query) . ' words'));
    }*/
    
    function retrieveProductData($products_id = '') {
      global $languages_id;
      
      $productData = array();
      $product_options = unserialize(SE_FACET_PRODUCT_OPTIONS);
      $product_extra_fields = unserialize(SE_FACET_PRODUCT_EXTRA_FIELDS);
      
      //collect the data items
      $query_raw = "
        select
          p.products_id,
          p.products_model,
          pd.products_name, 
          pd.products_description,
          m.manufacturers_name
          " . (is_array($product_extra_fields) && 0 != count($product_extra_fields) ? ", GROUP_CONCAT(pef.products_extra_fields_id, '" . SE_DELIM . "', pef.products_extra_fields_name, '" . SE_DELIM . "', pefv.products_extra_fields_values_id, '" . SE_DELIM . "', pefv.products_extra_fields_values_name) as products_extra_fields" : "") . "
        FROM 
          " . TABLE_PRODUCTS . " p 
          LEFT JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON (p.products_id = pd.products_id and pd.language_id = '" . (int)$languages_id . "') 
          LEFT JOIN " . TABLE_MANUFACTURERS . " m ON (p.manufacturers_id = m.manufacturers_id)
          " . (is_array($product_extra_fields) && 0 != count($product_extra_fields) ? "
            LEFT JOIN " . TABLE_PRODUCTS_TO_PRODUCTS_EXTRA_FIELDS . " p2pef on (p.products_id = p2pef.products_id AND p2pef.products_extra_fields_id IN (" . implode(',', $product_extra_fields) . "))
            LEFT JOIN " . TABLE_PRODUCTS_EXTRA_FIELDS . " pef on (p2pef.products_extra_fields_id = pef.products_extra_fields_id and pef.language_id = '" . (int)$languages_id . "')
            LEFT JOIN " . TABLE_PRODUCTS_EXTRA_FIELDS_VALUES . " pefv on (p2pef.products_extra_fields_values_id = pefv.products_extra_fields_values_id and pefv.language_id = '" . (int)$languages_id . "')" : "") . "
        WHERE 
          p.products_status = '1'" . (tep_not_null($products_id) ? " and p.products_id = '" . (int)$products_id . "'" : "") . "
        GROUP BY
          p.products_id";
      //die(tep_sql_pretty_print($query_raw));
      $query = tep_db_query($query_raw);
      if(0 != tep_db_num_rows($query)) {
        while($row = tep_db_fetch_array($query)) {
          $productData[$row['products_id']] = array();
          $productData[$row['products_id']] = $row;
          
          //translate in desired format
          if(isset($row['products_extra_fields'])) {
            foreach(explode(',', $row['products_extra_fields']) as $concat_value) {
              if(tep_not_null($concat_value)) {
                list($extra_fields_id, $extra_fields_name, $extra_fields_values_id, $extra_fields_values_name) = explode(SE_DELIM, $concat_value);
                $key1 = SE_DELIM . $extra_fields_id . SE_DELIM . $extra_fields_name;
                $key2 = SE_DELIM . $extra_fields_values_id . SE_DELIM . $extra_fields_values_name;
                if(!is_array($productData[$row['products_id']]['products_extra_fields'])) {
                  $productData[$row['products_id']]['products_extra_fields'] = array($key1 => array());
                }
                if(!is_array($productData[$row['products_id']]['products_extra_fields'][$key1])) {
                  $productData[$row['products_id']]['products_extra_fields'][$key1] = array($key2 => array());
                }
                $productData[$row['products_id']]['products_extra_fields'][$key1][$key2][] = (int)$row['products_id'];
              }
            }
          }
          
          
          //Product options
          if(is_array($product_options) && 0 != count($product_options)) { 
            $query_raw2 = "
              select
                po.products_options_id,
                po.products_options_name,
                pov.products_options_values_id,
                pov.products_options_values_name
              FROM 
                " . TABLE_PRODUCTS . " p 
                RIGHT JOIN " . TABLE_PRODUCTS_ATTRIBUTES . " pa ON (p.products_id = pa.products_id AND pa.options_id IN (" . implode(',', $product_options) . "))
                RIGHT JOIN " . TABLE_PRODUCTS_OPTIONS . " po ON (pa.options_id = po.products_options_id and po.language_id = '" . (int)$languages_id . "')
                RIGHT JOIN " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov ON (pa.options_values_id = pov.products_options_values_id and pov.language_id = '" . (int)$languages_id . "')
              WHERE 
                p.products_id = '" . (int)$row['products_id'] . "'";
            //die(tep_sql_pretty_print($query_raw2));
            $query2 = tep_db_query($query_raw2);
            if(0 != tep_db_num_rows($query2)) {
              while($row2 = tep_db_fetch_array($query2)) {
                $key1 = SE_DELIM . $row2['products_options_id'] . SE_DELIM . $row2['products_options_name'];
                $key2 = SE_DELIM . $row2['products_options_values_id'] . SE_DELIM . $row2['products_options_values_name'];
                if(!is_array($productData[$row['products_id']]['products_options'])) {
                  $productData[$row['products_id']]['products_options'] = array($key1 => array());
                }
                if(!is_array($productData[$row['products_id']]['products_options'][$key1])) {
                  $productData[$row['products_id']]['products_options'][$key1] = array($key2 => array());
                }
                $productData[$row['products_id']]['products_options'][$key1][$key2][] = (int)$row['products_id'];
              }
            }
          }
        
          //Category path and descriptions
          $se_product_path_tree = tep_get_product_path_tree($products_id, SE_DELIM);
          $productData[$row['products_id']]['product_path_tree'] = $se_product_path_tree;
        }
      }
      
      return $productData;
    }
    
    /**
     * Index a single product
     *
     * @param unknown_type $products_id
     * @return unknown
     * 
     * @todo multi-language
     */
    function index($products_id) {
      global $languages_id;
      
      $se_product_content = '';
      $indexed = false;

      $heading = array();
      $contents = array();
      $contents[] = array(
        0 => array('params' => 'colspan="5" class="infoBoxHeading"', 'text' => 'Index product ' . $products_id));
      $contents[] = array(
        0 => array('params' => 'class="infoBoxHeading" nowrap', 'text' => 'products id'),
        1 => array('params' => 'class="infoBoxHeading" nowrap', 'text' => 'products name'),
        2 => array('params' => 'class="infoBoxHeading" nowrap', 'text' => 'words indexed'),
        3 => array('params' => 'class="infoBoxHeading" nowrap', 'text' => '&nbsp;'),
        4 => array('params' => 'class="infoBoxHeading" nowrap', 'text' => '&nbsp;'));

      $product_data = $this->retrieveProductData($products_id);
      //die('<pre>' . print_r($product_data, TRUE) . '</pre>');
      if(0 != count($product_data)) {
        $params = array(
          SE_FACET_CATEGORY => $product_data[$products_id]['product_path_tree'],
          SE_FACET_EXTRA_FIELDS => (isset($product_data[$products_id]['products_extra_fields']) ? $product_data[$products_id]['products_extra_fields'] : array()),
          SE_FACET_OPTIONS => (isset($product_data[$products_id]['products_options']) ? $product_data[$products_id]['products_options'] : array()));
        
        $se_product_content .= print_r($product_data[$products_id], TRUE);
      
        //compare the md5
        $se_md5 = md5($se_product_content);
        $query_raw = "
          select
            sel.md5sum 
          FROM 
            " . TABLE_SE_LINKS . " sel
          WHERE 
            sel.products_id = '" . (int)$products_id . "' and sel.language_id = '" . (int)$languages_id . "'";
        //die(tep_sql_pretty_print($query_raw));
        $query_se_links = tep_db_query($query_raw);
        if(0 != tep_db_num_rows($query_se_links)) {
          $row_se_links = tep_db_fetch_array($query_se_links);
          if($row_se_links['md5sum'] !== $se_md5) {
            $se_data = array();
            $this->getKeywordsArray($product_data[$products_id], $se_data);
            arsort($se_data);
            $this->keywordsUpdate($products_id, $languages_id, $se_data);
            $se_sql_data_array = array(
              'language_id' => (int)$languages_id,
              'params' => serialize($params), 
              'md5sum' => $se_md5, 
              'indexdate' => 'now()');
            tep_db_perform(TABLE_SE_LINKS, $se_sql_data_array, 'update', "products_id = '" . (int)$products_id . "'");
            $indexed = true;
            $contents[] = array(
              0 => array('params' => 'class="infoBoxContent" valign="top" nowrap', 'text' => (int)$products_id),
              1 => array('params' => 'class="infoBoxContent" valign="top" nowrap', 'text' => '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'pID=' . (int)$products_id . '&action=new_product') . '" TARGET="_blank">' . $product_data[$products_id]['products_name'] . '</a>&nbsp;'),
              2 => array('params' => 'class="infoBoxContent" valign="top" nowrap', 'text' => count($se_data)),
              3 => array('params' => 'class="infoBoxContent" valign="top" nowrap', 'text' => '<pre>' . print_r($se_data, TRUE) . '<pre>'),
              4 => array('params' => 'class="infoBoxContent" valign="top" nowrap', 'text' => '&nbsp;'));
          } else {
            $contents[] = array(
              0 => array('params' => 'colspan="5" class="infoBoxContent" valign="top" nowrap', 'text' => 'No changes found'));
          }
        } else {
          $se_data = array();
          $this->getKeywordsArray($product_data[$products_id], $se_data);
          arsort($se_data);
          $this->keywordsInsert($products_id, $languages_id, $se_data);
          $se_sql_data_array = array(
            'products_id' => (int)$products_id,
            'language_id' => (int)$languages_id, 
            'params' => serialize($params), 
            'md5sum' => $se_md5, 
            'indexdate' => 'now()');
          tep_db_perform(TABLE_SE_LINKS, $se_sql_data_array);
          $indexed = true;
          $contents[] = array(
            0 => array('params' => 'class="infoBoxContent" valign="top" nowrap', 'text' => (int)$products_id),
            1 => array('params' => 'class="infoBoxContent" valign="top" nowrap', 'text' => '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'pID=' . (int)$products_id . '&action=new_product') . '" TARGET="_blank">' . $product_data[$products_id]['products_name'] . '</a>&nbsp;'),
            2 => array('params' => 'class="infoBoxContent" valign="top" nowrap', 'text' => count($se_data)),
            3 => array('params' => 'class="infoBoxContent" valign="top" nowrap', 'text' => '<pre>' . print_r($se_data, TRUE) . '<pre>'),
            4 => array('params' => 'class="infoBoxContent" valign="top" nowrap', 'text' => '&nbsp;'));
        }
      }
      
      $box = new box;
      $this->infoBoxContents[] = array(0 => array('params' => '', 'text' => $box->infoBox($heading, $contents)));
      return $indexed;
    }
    
    /**
     * Index many products
     *
     * @param unknown_type $limit
     */
    function indexall($limit = 0) {
      $query_raw = "
        SELECT
          p.products_id
        FROM 
          " . TABLE_PRODUCTS . " p 
        WHERE 
          p.products_status = '1'";
      //die(tep_sql_pretty_print($query_raw));
      $query = tep_db_query($query_raw);
      $count = 0;
      while($row = tep_db_fetch_array($query)) {
        if(true == $this->index($row['products_id'])) {
          if(0 != $limit && ++$count >= $limit) {
            break;
          }
        }
      }      
    }
    
    /**
     * initialize is the function where it all happens:
     * 1 - Compose the query for products based on the provided filter and provide the raw-query for the content to process it in pages output 
     * 2 - Create the facets by running the query based on keywords only
     * 3 - When the query (on keywords only) returns < SE_DID_YOU_MEAN_SEARCH_RESULT_THRESHOLD results, alternatives are proposed as a keyword-facet
     */
    function initialize($filters = array()) {
      global $languages_id;
      
      $start = tep_getMicrotime();
      
      $heading = array();
      $contents = array();

      $contents[] = array(
        0 => array(
          'params' => 'colspan="5" class="infoBoxHeading"',
          'text' => '<b>Build facets based on keywords and optionally prepare \'did you mean...\'</b>'));
      
      if(isset($filters[SE_FACET_KEYWORD])) {
        $contents[] = array(
          0 => array('params' => 'colspan="5" class="infoBoxContent"', 'text' => 'Search results for terms: <b>' . $filters[SE_FACET_KEYWORD] . '</b>'));
        
        $words = $this->getSearchKeywords($filters[SE_FACET_KEYWORD]);
        
        $contents[] = array(
          0 => array(
            'params' => 'colspan="5" class="infoBoxContent"',
            'text' => 'Used keywords: ' . '<pre>' . print_r($words, TRUE) . '</pre>'));
        
        if(0 != count($words)) {
          
          /**
           * Notice the items are typically right-join'ed when using it as a filter (for the products query). While
           * they are left-join'ed for composing the facets. The keywords are an exception because the we want to 
           * compose facets over all products that match the keyword-search.
           */
          
          //1. Keywords
          $keyword_query = "RIGHT JOIN " . TABLE_SE_LINKS . " sel ON (p.products_id = sel.products_id and sel.language_id = '" . (int)$languages_id . "')";
          $i = 0;
          foreach($words as $word) {
            $table_postfix = substr(md5($word), 0, 1);
            $selk_table_name = sprintf(TABLE_SE_LINK_KEYWORD, $table_postfix);
            $selk_name = "selk" . $table_postfix . $i;
            $sek_name = "sek" . $table_postfix . $i;
            
            $keyword_query .= "
              RIGHT JOIN " . $selk_table_name . " " . $selk_name . " ON (" . $selk_name . ".link_id = sel.products_id)
              RIGHT JOIN " . TABLE_SE_KEYWORDS . " " . $sek_name . " ON (" . $sek_name . ".keyword_id = " . $selk_name . ".keyword_id AND " . $sek_name . ".keyword = '" . $word . "' and " . $sek_name . ".language_id = '" . (int)$languages_id . "')";
            $i++;
          }
          
          //2. Categories
          $category_query = '';
          if(isset($filters[SE_FACET_CATEGORY]) && tep_not_null($filters[SE_FACET_CATEGORY])) {
            $category_query = "
              RIGHT JOIN (" . TABLE_PRODUCTS_TO_CATEGORIES . " p2c) on (p.products_id = p2c.products_id and p2c.categories_id IN (" . implode(',', array_merge(array((int)$filters[SE_FACET_CATEGORY]), tep_get_subcategories((int)$filters[SE_FACET_CATEGORY]))) . "))";
          }
          
          //3. Manufacturer
          $manufacturer_query = '';
          if(isset($filters[SE_FACET_MANUFACTURER]) && tep_not_null($filters[SE_FACET_MANUFACTURER])) {
            $manufacturer_query = "
              RIGHT JOIN (" . TABLE_MANUFACTURERS . " m) ON (p.manufacturers_id = m.manufacturers_id AND m.manufacturers_id = '" . $filters[SE_FACET_MANUFACTURER] . "')";
          }
          
          //4. Price
          $price_query = array('select' => '', 'from' => '', 'where' => '');
          if(isset($filters[SE_FACET_PRICE]) && tep_not_null($filters[SE_FACET_PRICE])) {
            list($pfrom, $pto) = explode('_', preg_replace("/,/", ".", $filters[SE_FACET_PRICE]));
            if(tep_not_null($pfrom) && is_numeric($pfrom) && tep_not_null($pto) && is_numeric($pto)) {
              $price_query['select'] .= ', SUM(tr.tax_rate) as tax_rate';
              $price_query['from'] .= "LEFT JOIN (" . TABLE_TAX_RATES . " tr) ON (p.products_tax_class_id = tr.tax_class_id)";
            }
            if(tep_not_null($pfrom) && is_numeric($pfrom)) {
              $price_query['where'] .= " AND (IF(s.status, s.specials_new_products_price, p.products_price) * (1 + (tr.tax_rate / 100) ) >= " . (double)$pfrom . ")";
            }
            if(tep_not_null($pto) && is_numeric($pto)) {
              $price_query['where'] .= " AND (IF(s.status, s.specials_new_products_price, p.products_price) * (1 + (tr.tax_rate / 100) ) <= " . (double)$pto . ")";
            }
          }
          
          //5. Review
          $review_query = '';
          if(isset($filters[SE_FACET_RATING]) && tep_not_null($filters[SE_FACET_RATING])) {
            $review_query = "
              RIGHT JOIN (" . TABLE_REVIEWS . " r) ON (p.products_id = r.products_id AND r.reviews_rating IS NOT NULL AND r.reviews_rating >= '" . $filters[SE_FACET_RATING] . "')";
          }
          
          //6. Product options
          $options_query = '';
          if(isset($filters[SE_FACET_OPTIONS]) && tep_not_null($filters[SE_FACET_OPTIONS])) {
            list($optionid, $valueid) = explode('_', $filters[SE_FACET_OPTIONS]);
            if(tep_not_null($optionid) && is_numeric($optionid) && tep_not_null($valueid) && is_numeric($valueid)) {
              $options_query = "
                RIGHT JOIN " . TABLE_PRODUCTS_ATTRIBUTES . " pa ON (p.products_id = pa.products_id AND pa.options_id = '" . (int)$optionid . "' AND pa.options_values_id = '" . (int)$valueid . "')";
            }
          }
          
          //7. Product extra fields
          $extra_fields_query = '';
          if(isset($filters[SE_FACET_EXTRA_FIELDS]) && tep_not_null($filters[SE_FACET_EXTRA_FIELDS])) {
            list($extrafieldid, $valueid) = explode('_', $filters[SE_FACET_EXTRA_FIELDS]);
            if(tep_not_null($extrafieldid) && is_numeric($extrafieldid) && tep_not_null($valueid) && is_numeric($valueid)) {
              $extra_fields_query = "
                RIGHT JOIN " . TABLE_PRODUCTS_TO_PRODUCTS_EXTRA_FIELDS . " p2pef ON (p.products_id = p2pef.products_id AND p2pef.products_extra_fields_id = '" . (int)$extrafieldid . "' AND p2pef.products_extra_fields_values_id = '" . (int)$valueid . "')";
            }
          }
          
          //Assign the catalog query (to display products)
          $this->catalog_query['select'] = $price_query['select'];
          $this->catalog_query['from'] = $keyword_query . " " . $category_query . " " . $manufacturer_query . " " . $price_query['from'] . " " . $review_query . " " . $options_query . " " . $extra_fields_query;
          $this->catalog_query['where'] = $price_query['where'];
          
          //run the query to compose the facets
          //@todo caching
          $query_raw = "
            SELECT
              p.products_id,
              IF(s.status, s.specials_new_products_price, p.products_price) as final_price,
              p.products_tax_class_id,
              sel.params,
              avg(r.reviews_rating) as avg_reviews_rating,
              m.manufacturers_id,
              m.manufacturers_name
            FROM 
              " . TABLE_PRODUCTS . " p
              " . $keyword_query . "
              LEFT JOIN (" . TABLE_SPECIALS . " s) ON (p.products_id = s.products_id)
              LEFT JOIN (" . TABLE_MANUFACTURERS . " m) ON (p.manufacturers_id = m.manufacturers_id)
              LEFT JOIN (" . TABLE_REVIEWS . " r) ON (p.products_id = r.products_id)
            WHERE 
              p.products_status = '1'
            GROUP BY
              p.products_id";
          //die(tep_sql_pretty_print($query_raw));
          $query = tep_db_query($query_raw);
          
          $contents[] = array(
            0 => array(
              'params' => 'colspan="5" class="infoBoxContent"', 
              'text' => 'Query used for generating filters:' . tep_sql_pretty_print($query_raw)));
          
          $contents[] = array(
            0 => array(
              'params' => 'colspan="5" class="infoBoxContent"', 
              'text' => 'Items found: ' . tep_db_num_rows($query)));
          
          /**
           * Build the facets
           */
          $this->facets[SE_FACET_KEYWORD] = array();
          $this->facets[SE_FACET_CATEGORY] = array();
          $this->facets[SE_FACET_MANUFACTURER] = array();
          $this->facets[SE_FACET_PRICE] = array();
          foreach(explode(';', SE_FACET_PRICE_STEPS) as $value) {
            if(!isset($prev_price)) {
              $prev_price = $value;
              $this->facets[SE_FACET_PRICE][SE_DELIM . '0_' . $value . SE_DELIM . sprintf(TEXT_PRICE_TO, $value)] = array('min' => (float)0, 'max' => (float)$value, 'count' => 0);
            } else {
              $this->facets[SE_FACET_PRICE][SE_DELIM . $prev_price . '_' . $value . SE_DELIM . sprintf(TEXT_PRICE_FROM_TO, $prev_price, $value)] = array('min' => (float)$prev_price, 'max' => (float)$value, 'count' => 0);
              $prev_price = $value;
            }
          }
          $this->facets[SE_FACET_PRICE][SE_DELIM . $prev_price . '_' . SE_DELIM . sprintf(TEXT_PRICE_FROM, $prev_price)] = array('min' => (float)$prev_price, 'count' => 0);
          $this->facets[SE_FACET_RATING] = array();
          $this->facets[SE_FACET_OPTIONS] = array();
          $this->facets[SE_FACET_EXTRA_FIELDS] = array();
          while($row = tep_db_fetch_array($query)) {
            $params = unserialize($row['params']);
            
            //1. Keyword
            //
            
            //2. Categories
            $this->facets[SE_FACET_CATEGORY] = array_merge_recursive($this->facets[SE_FACET_CATEGORY], $params[SE_FACET_CATEGORY]);
                    
            //3. Manufacturer
            $key = SE_DELIM . $row['manufacturers_id'] . SE_DELIM . $row['manufacturers_name'];
            if(isset($this->facets[SE_FACET_MANUFACTURER][$key])) {
              $this->facets[SE_FACET_MANUFACTURER][$key]++;
            } else {
              $this->facets[SE_FACET_MANUFACTURER][$key] = 1;
            }

            //4. Price
            $price = tep_round(tep_add_tax($row['final_price'], tep_get_tax_rate($row['products_tax_class_id'])), 2);
            foreach($this->facets[SE_FACET_PRICE] as $key => &$value) {
              if((float)$value['min'] < $price && (!isset($value['max']) || (float)$value['max'] >= $price)) {
                $value['count']++;
                break;
              }
            }
            
            //5. Review
            if(tep_not_null($row['avg_reviews_rating'])) {
              $review_rating = round($row['avg_reviews_rating']);
              $key = SE_DELIM . $review_rating . SE_DELIM . tep_image(DIR_WS_CATALOG_IMAGES . 'ratingstars' . sprintf("%02d", 5 * round(2 * $review_rating)) . '.gif');
              if(isset($this->facets[SE_FACET_RATING][$key])) {
                $this->facets[SE_FACET_RATING][$key]++;
              } else {
                $this->facets[SE_FACET_RATING][$key] = 1;
              }
            }
            
            //6. Product options
            $this->facets[SE_FACET_OPTIONS] = array_merge_recursive($this->facets[SE_FACET_OPTIONS], $params[SE_FACET_OPTIONS]);
            
            //7. Product extra fields
            $this->facets[SE_FACET_EXTRA_FIELDS] = array_merge_recursive($this->facets[SE_FACET_EXTRA_FIELDS], $params[SE_FACET_EXTRA_FIELDS]);
          }

          uksort($this->facets[SE_FACET_KEYWORD], "cmpDelimKey");
          uksort($this->facets[SE_FACET_CATEGORY], "cmpDelimKey");
          uksort($this->facets[SE_FACET_MANUFACTURER], "cmpDelimKey");
          //uksort($this->facets[SE_FACET_PRICE], "cmpDelimKey");
          uksort($this->facets[SE_FACET_RATING], "cmpDelimKey");
          uksort($this->facets[SE_FACET_OPTIONS], "cmpDelimKey");
          uksort($this->facets[SE_FACET_EXTRA_FIELDS], "cmpDelimKey");
                                        
          foreach($this->facets[SE_FACET_PRICE] as $key => &$value) {
            $value = $value['count'];
          }
          
          $contents[] = array(
            0 => array(
              'params' => 'colspan="5" class="infoBoxContent"', 
              'text' => 'Search performance: ' . tep_getParsetime($start)));
          
          //did you mean...
          if(SE_DID_YOU_MEAN_SEARCH_RESULT_THRESHOLD >= tep_db_num_rows($query)) {
            $this->facets[SE_FACET_KEYWORD] = array();
            if(SE_DID_YOU_MEAN_METHOD == 'aspell/pspell') {
              $pspell_config = pspell_config_create("nl");
              pspell_config_data_dir($pspell_config, SE_ASPELL_PATH . 'data');
              pspell_config_dict_dir($pspell_config, SE_ASPELL_PATH . 'dict');
              //pspell_config_personal($pspell_config, SE_ASPELL_PATH . 'custom.pws');
              $pspell_link = pspell_new_config($pspell_config);
              foreach($words as $word) {
                if (!pspell_check($pspell_link, $word)) {
                  $this->facets[SE_FACET_KEYWORD][$word] = pspell_suggest($pspell_link, $word);
                }
              }            
            } else {
              foreach($words as $word) {
                $query_raw = "
                  SELECT
                    sek.keyword
                  FROM
                    " . TABLE_SE_KEYWORDS . " sek
                  WHERE
                    soundex(sek.keyword) = soundex('" . $word . "') and sek.language_id = '" . (int)$languages_id . "'";
                //die(tep_sql_pretty_print($query_raw));
                $query = tep_db_query($query_raw);
                
                $max_distance = 100;
                $near_word = "";
                while($row = tep_db_fetch_array($query)) {
                  $distance = levenshtein($row['keyword'], $word);
                  if ($distance < $max_distance && $distance < 4) {
                    $max_distance = $distance;
                    $near_word = $row['keyword'];
                  }
                }
        
                if ($near_word != "" && $word != $near_word) {
                  $this->facets[SE_FACET_KEYWORD][$word] = $near_word;
                }
              }
            }
          }
        }
      }
      
      $box = new box;
      $this->infoBoxContents[] = array(0 => array('params' => '', 'text' => $box->infoBox($heading, $contents)));
    }
    
    /**
     * Provides the available search filters such as price, category, ...
     *
     */
    function getFacets() {
      return $this->facets;
    }
    
    /**
     * Provides the query parts that are used to query for products
     *
     */
    function getSearchQuery() {
      return $this->catalog_query;
    }
    
    /**
     * Admin function to 'touch' the different functions. Catalog side would normally not use this function
     *
     */
    function search() {
      global $languages_id;
      
      $heading = array();
      $contents = array();
      
      $contents[] = array(
        0 => array(
          'params' => 'colspan="5" class="infoBoxHeading"',
          'text' => '<b>Results: Available facets</b>'));
      
      $start = tep_getMicrotime();
            
      $facets = $this->getFacets();
      $catalog_query = $this->getSearchQuery();
      
      /*$contents[] = array(
        0 => array(
          'params' => 'colspan="3" class="infoBoxContent"', 
          'text' => '<pre>' . print_r($facets, TRUE) . '</pre>'));*/
      
      //Facets
      $vars = tep_get_all_get_params(array(SE_FACET_KEYWORD, SE_FACET_CATEGORY, SE_FACET_MANUFACTURER, SE_FACET_PRICE, SE_FACET_RATING, SE_FACET_OPTIONS, SE_FACET_EXTRA_FIELDS));
      if(isset($facets[SE_FACET_KEYWORD])) {
        $contents[] = array(
          0 => array(
            'params' => 'colspan="3" class="infoBoxContent"', 
            'text' => 'Keyword facet:' . $this->flatToUl($vars . SE_FACET_KEYWORD . '=', $facets[SE_FACET_KEYWORD])));
          //@todo speciale presentatie van suggests...
      }
      if(isset($facets[SE_FACET_KEYWORD])) {
        $contents[] = array(
          0 => array(
            'params' => 'colspan="3" class="infoBoxContent"', 
            'text' => 'Keyword facet:' . '<pre>' . print_r($facets[SE_FACET_KEYWORD], TRUE) . '</pre>'));
      }
      
      if(isset($facets[SE_FACET_CATEGORY])) {
        $contents[] = array(
          0 => array(
            'params' => 'colspan="3" class="infoBoxContent"', 
            'text' => 'Category facet:' . $this->hierarchyToUl($vars . SE_FACET_CATEGORY . '=', $facets[SE_FACET_CATEGORY])));
      }

      if(isset($facets[SE_FACET_MANUFACTURER])) {
        $contents[] = array(
          0 => array(
            'params' => 'colspan="3" class="infoBoxContent"', 
            'text' => 'Manufacturer facet:' . $this->flatToUl($vars . SE_FACET_MANUFACTURER . '=', $facets[SE_FACET_MANUFACTURER])));
      }
      
      if(isset($facets[SE_FACET_PRICE])) {
        $contents[] = array(
          0 => array(
            'params' => 'colspan="3" class="infoBoxContent"', 
            'text' => 'Price facet:' . $this->flatToUl($vars . SE_FACET_PRICE . '=', $facets[SE_FACET_PRICE])));
      }
      
      if(isset($facets[SE_FACET_RATING])) {
        $contents[] = array(
          0 => array(
            'params' => 'colspan="3" class="infoBoxContent"', 
            'text' => 'Rating facet:' . $this->flatToUl($vars . SE_FACET_RATING . '=', $facets[SE_FACET_RATING])));
      }
      
      if(isset($facets[SE_FACET_OPTIONS])) {
        foreach($facets[SE_FACET_OPTIONS] as $key => &$value) {
          $matches = array();
          if(preg_match('/\\' . SE_DELIM . '([0-9_]+)\\' . SE_DELIM . '(.*)/', $key, $matches)) {
            uksort($value, "cmpDelimKey");
            $contents[] = array(
              0 => array(
                'params' => 'colspan="3" class="infoBoxContent"', 
                'text' => 'Option ' . $matches[2] . ' facet:' . $this->hierarchyToUl($vars . SE_FACET_OPTIONS . '=' . $matches[1] . '_', $value)));
          }
        }
      }
      
      if(isset($facets[SE_FACET_EXTRA_FIELDS])) {
        foreach($facets[SE_FACET_EXTRA_FIELDS] as $key => &$value) {
          $matches = array();
          if(preg_match('/\\' . SE_DELIM . '([0-9_]+)\\' . SE_DELIM . '(.*)/', $key, $matches)) {
            uksort($value, "cmpDelimKey");
            $contents[] = array(
              0 => array(
                'params' => 'colspan="3" class="infoBoxContent"', 
                'text' => 'Option ' . $matches[2] . ' facet:' . $this->hierarchyToUl($vars . SE_FACET_EXTRA_FIELDS . '=' . $matches[1] . '_', $value)));
          }
        }
      }

      $contents[] = array(
        0 => array(
          'params' => 'colspan="5" class="infoBoxHeading"',
          'text' => '<b>Results: Run the (filtered) product query</b>'));
      
      //Search      
      $query_raw = "
        SELECT
          p.products_id,
          pd.products_name, 
          pd.products_description,
          IF(s.status, s.specials_new_products_price, p.products_price) as final_price,
          p.products_tax_class_id
          " . $catalog_query['select'] . "
        FROM 
          " . TABLE_PRODUCTS . " p 
          LEFT JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON (p.products_id = pd.products_id and pd.language_id = '" . (int)$languages_id . "')
          LEFT JOIN (" . TABLE_SPECIALS . " s) ON (p.products_id = s.products_id)
          " . $catalog_query['from'] . "
        WHERE 
          p.products_status = '1'
          " . $catalog_query['where'] . "
        GROUP BY
          p.products_id";
      //die(tep_sql_pretty_print($query_raw));
      $query = tep_db_query($query_raw);
      
      $contents[] = array(
        0 => array(
          'params' => 'colspan="3" class="infoBoxContent"', 
          'text' => 'Query used for searching products:' . tep_sql_pretty_print($query_raw)));
      
      $contents[] = array(
        0 => array(
          'params' => 'colspan="3" class="infoBoxContent"', 
          'text' => 'Items found: ' . tep_db_num_rows($query)));
      
      $contents[] = array(
        0 => array(
          'params' => 'colspan="3" class="infoBoxContent"', 
          'text' => 'Search performance: ' . tep_getParsetime($start)));
        
      $contents[] = array(
        0 => array('params' => 'class="infoBoxHeading" nowrap', 'text' => 'products id'),
        1 => array('params' => 'class="infoBoxHeading" nowrap', 'text' => 'products name'),
        2 => array('params' => 'class="infoBoxHeading" nowrap', 'text' => 'price'),
        3 => array('params' => 'class="infoBoxHeading" nowrap', 'text' => 'product description'));
        
      while($row = tep_db_fetch_array($query)) {
        $contents[] = array(
          0 => array('params' => 'class="infoBoxContent" valign="top" nowrap', 'text' => $row['products_id']),
          1 => array('params' => 'class="infoBoxContent" valign="top" nowrap', 'text' => '<a href="' . tep_catalog_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . (int)$row['products_id']) . '" TARGET="_blank">' . $row['products_name'] . '</a>&nbsp;'),
          2 => array('params' => 'class="infoBoxContent" valign="top"', 'text' => number_format(tep_round(tep_add_tax($row['final_price'], tep_get_tax_rate($row['products_tax_class_id'])), 2), 2)),
          3 => array('params' => 'class="infoBoxContent" valign="top"', 'text' => $row['products_description'] . '&nbsp;'));
      }
      
      $box = new box;
      $this->infoBoxContents[] = array(0 => array('params' => '', 'text' => $box->infoBox($heading, $contents)));
    }
    
    function searchOld($query) {
      global $languages_id;
      
      $start = tep_getMicrotime();
      
      $heading = array();
      $contents = array();
      
      $contents[] = array(
        0 => array('params' => 'colspan="5" class="infoBoxHeading"', 'text' => '<b>Search results (the old way) for terms: ' . $query . '</b>'));

      $words = $this->getSearchKeywords($query);
      
      $contents[] = array(
        0 => array(
          'params' => 'colspan="5" class="infoBoxContent"', 
          'text' => 'Used keywords: ' . '<pre>' . print_r($words, TRUE) . '</pre>'));
      
      if(0 != count($words)) {
        $keyword_query = '(';
        foreach($words as $word) {
          $keyword_query .= "(pd.products_name like '%" . $word . "%' or p.products_model like '%" . $word . "%' or m.manufacturers_name like '%" . $word . "%' or pd.products_description like '%" . $word . "%') and ";
        }
        $keyword_query = substr($keyword_query, 0, count($keyword_query) - 6) . ')';
        
        $query_raw = "
          SELECT
            p.products_id,
            pd.products_name, 
            pd.products_description
          FROM
            products p 
            left join (manufacturers m) using(manufacturers_id) 
            left join (specials s) on (p.products_id = s.products_id) 
            right join (products_description pd) on (p.products_id = pd.products_id and pd.language_id = '" . (int)$languages_id . "') 
            right join (products_to_categories p2c) on (p.products_id = p2c.products_id) 
            right join (categories c) on (c.categories_id = p2c.categories_id) 
          where 
            p.products_status = '1' and
            " . $keyword_query . " 
          group by 
            p.products_id order by p.products_ordered desc, p.products_date_added desc";
        //die(tep_sql_pretty_print($query_raw));
        $query = tep_db_query($query_raw);
        
        $contents[] = array(
          0 => array(
            'params' => 'colspan="5" class="infoBoxContent"', 
            'text' => tep_sql_pretty_print($query_raw)));
        
        $contents[] = array(
          0 => array(
            'params' => 'colspan="5" class="infoBoxContent"', 
            'text' => 'Items found: ' . tep_db_num_rows($query)));
        
        $contents[] = array(
          0 => array(
            'params' => '', 
            'text' => 'Search performance: ' . tep_getParsetime($start)));
          
        $contents[] = array(
          0 => array('params' => 'class="infoBoxHeading" nowrap', 'text' => 'products id'),
          1 => array('params' => 'class="infoBoxHeading" nowrap', 'text' => 'products name'),
          2 => array('params' => 'class="infoBoxHeading" nowrap', 'text' => 'product description'),
          3 => array('params' => 'class="infoBoxHeading" nowrap', 'text' => 'params'),
          4 => array('params' => 'class="infoBoxHeading" nowrap', 'text' => '&nbsp;'));
          
        while($row = tep_db_fetch_array($query)) {
          $contents[] = array(
            0 => array('params' => 'class="infoBoxContent" valign="top" nowrap', 'text' => $row['products_id']),
            1 => array('params' => 'class="infoBoxContent" valign="top" nowrap', 'text' => '<a href="' . tep_catalog_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . (int)$row['products_id']) . '" TARGET="_blank">' . $row['products_name'] . '</a>&nbsp;'),
            2 => array('params' => 'class="infoBoxContent" valign="top"', 'text' => $row['products_description']),
            3 => array('params' => 'class="infoBoxContent" valign="top" nowrap', 'text' => '<pre>' . print_r(unserialize($row['params']), TRUE) . '</pre>'),
            4 => array('params' => 'class="infoBoxContent" valign="top" nowrap', 'text' => '&nbsp;'));
        }
      }
      
      $box = new box;
      return $box->infoBox($heading, $contents);
    }
  }
?>