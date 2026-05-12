<?php
/*
  $Id: stats_products_orders.php,v 1 06 sept 2009 

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2005 osCommerce

  originally developed by paddybl
  Released under the GNU General Public License
*/

  require('includes/application_top.php');

  ini_set('memory_limit', -1);
  //ini_set('display_errors', 1);
  //ini_set('display_startup_errors', 1);
  //error_reporting(E_ALL);
// activation du popup image
$active_popup=true; //placer sur false si vous voulez le désactiver

//nom du répertoire où les fichiers de statistique excel seront créé.
//attention le répertoire doit ètre accessible en lecture/écriture.
//pensez à créeer et personnaliser se répertoire!!!

define('DIR_STATS_DIRECTORY','temp');

require("../".DIR_WS_LANGUAGES . $language .'/modules/order_total/ot_total.php');


  require(DIR_WS_CLASSES . 'currencies.php');
require(DIR_WS_CLASSES . 'order.php');
  $currencies = new currencies();

    $_GET['inc_subcat'] = '1';
    if ($_GET['inc_subcat'] == '1') {
      $subcategories_array = array();
	  $subcategories_sql = array();
	  $option_val_id='';
	  $option_on=false;
	  $add_product_options=array();
	  $level='';
	  $level2=array();
	  
  function download_file($file)
	{
		if (!is_file($file)) 
			   			{
			   				 die("<b>404 File not found!</b>"); 
			   			}

		
			  //Gather relevent info about file
			$len = filesize($file);
			$filename = basename($file);
			$file_extension = strtolower(substr(strrchr($filename,"."),1));

			//This will set the Content-Type to the appropriate setting for the file
			switch( $file_extension )
				 {
 				case "pdf": $ctype="application/pdf"; break;
					case "exe": $ctype="application/octet-stream"; break;
					case "zip": $ctype="application/zip"; break;
					case "doc": $ctype="application/msword"; break;
					case "xls": $ctype="application/vnd.ms-excel"; break;
					case "ppt": $ctype="application/vnd.ms-powerpoint"; break;
					case "gif": $ctype="image/gif"; break;
					case "png": $ctype="image/png"; break;
					case "jpeg":
					case "jpg": $ctype="image/jpg"; break;
					case "mp3": $ctype="audio/mpeg"; break;
					case "wav": $ctype="audio/x-wav"; break;
					case "mpeg":
					case "mpg":
					case "mpe": $ctype="video/mpeg"; break;
					case "mov": $ctype="video/quicktime"; break;	
					case "avi": $ctype="video/x-msvideo"; break;

					//The following are for extensions that shouldn't be downloaded (sensitive stuff, like php files)
					case "php":
					case "htm":
					case "html":
					case "txt":die("<b>Cannot be used for ". $file_extension ." files!</b>"); break; 

					default: $ctype="application/force-download";
				}
				ob_clean();
				//Begin writing headers
				header("Pragma: public");
				header("Expires: 0");
				header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
				header("Cache-Control: public"); 
				header("Content-Description: File Transfer");

				//Use the switch-generated Content-Type
				header("Content-Type: $ctype");

				//Force the download
				$header="Content-Disposition: attachment; filename=".$filename.";";
				header($header );
				header("Content-Transfer-Encoding: binary");
				header("Content-Length: ".$len);
				@readfile($file);
				exit;
		}	  
	  
    function html_no_quote($string) {
  $string=str_replace('&#39;', '', $string);
  $string=str_replace("'", "", $string);
  $string=str_replace('"', '', $string);
  $string=preg_replace("/\\r\\n|\\n|\\r/", "<BR>", $string); 
  return $string;
	
  } 
  function display_full_price($value){
  global $active_popup;
  if ($active_popup==false)return false;
    $currencies = new currencies();
    $heading = array();
  $contents = array();
          $products_query = tep_db_query("select * from " . TABLE_PRODUCTS . " where products_id = '" . (int)$value['product_id'] . "'");
        $products = tep_db_fetch_array($products_query);
		          $special_query = tep_db_query("select * from " . TABLE_SPECIALS . " where products_id = '" . (int)$value['product_id'] . "'");
        $special = tep_db_fetch_array($special_query);
       if(is_array($products)) $sInfo_array = array_merge($value, $products);
	if(is_array($special))$sInfo_array = array_merge($sInfo_array,$special);
      if(is_array($sInfo_array))  $sInfo = new objectInfo($sInfo_array);
   
        if (is_object($sInfo)) {
				$pourcent=($sInfo->products_price!=0)?number_format(100 - (($sInfo->specials_new_products_price / $sInfo->products_price) * 100)):TEXT_GRATUIT;

        $contents[] = array('text' => '<table border="0" width="100%" cellspacing="0" cellpadding="2">
  <tr><td style="background-color:red;color:white"><b>' . $sInfo->product_name . '<div align=right>Réf: ' . $sInfo->products_model . '</div></b></td></tr><tr><td style="background-color:#77AADD;">'.TEXT_INFO_DATE_ADDED . ' ' . tep_date_short($sInfo->products_date_added). '</nobr><br><nobr>' . TEXT_INFO_LAST_MODIFIED . ' ' . tep_date_short($sInfo->products_last_modified).'</nobr><br><table><tr><td>' . tep_info_image($sInfo->products_image, $sInfo->products_name, SMALL_IMAGE_WIDTH, SMALL_IMAGE_HEIGHT). '</td><td style="color:white">'.substr(strip_tags(str_replace('<br >',' ',$sInfo->products_description ?? ''),'<p></p>'),0,150) .'...</td></table><nobr>' . TEXT_INFO_ORIGINAL_PRICE . ' ' . $currencies->format($sInfo->products_price).' HT ('.$currencies->display_price($sInfo->products_price,tep_get_tax_rate($sInfo->products_tax_class_id)). ' TTC)</nobr><br><nobr>' . TEXT_INFO_NEW_PRICE . ' ' . $currencies->format($sInfo->specials_new_products_price).' HT ('.$currencies->display_price($sInfo->specials_new_products_price,tep_get_tax_rate($sInfo->products_tax_class_id)). ' TTC)</nobr><br><nobr>' . TEXT_INFO_COST . ' ' . $currencies->format($sInfo->products_cost).' HT ('.$currencies->display_price($sInfo->products_cost,tep_get_tax_rate($sInfo->products_tax_class_id)). ' TTC)</nobr><br><nobr>' . TEXT_INFO_PERCENTAGE . ' ' . $pourcent . '%'. '</nobr><br>'.TEXT_SPECIAL_INFO_DATE_ADDED . ' ' . tep_date_short($sInfo->specials_date_added). '</nobr><br><nobr>' . TEXT_SPECIAL_INFO_LAST_MODIFIED . ' ' . tep_date_short($sInfo->specials_last_modified).'</nobr><br><nobr>' . TEXT_INFO_EXPIRES_DATE . ' <b>' . tep_date_short($sInfo->expires_date) . '</b>'. '</nobr><br>' . TEXT_INFO_STATUS_CHANGE . ' ' . tep_date_short($sInfo->date_status_change).'</td></tr></table>');
      }
  if (tep_not_null($contents)) {
    $box = new box;
    return $box->tableBlock($contents);
  }	  
  }

  class splitPageResults2 {
    var $sql_query, $number_of_rows, $current_page_number, $number_of_pages, $number_of_rows_per_page, $page_name;

/* class constructor */
    function __construct($query, $max_rows, $count_key = '*', $page_holder = 'page') {
      global $_GET;

      $this->sql_query = $query;
      $this->page_name = $page_holder;

      if (isset($_GET[$page_holder])) {
        $page = $_GET[$page_holder];
      } elseif (isset($_POST[$page_holder])) {
        $page = $_POST[$page_holder];
      } else {
        $page = '';
      }

      if (empty($page) || !is_numeric($page)) $page = 1;
      $this->current_page_number = $page;

      $this->number_of_rows_per_page = $max_rows;

      $pos_to = strlen($this->sql_query);
      $pos_from = strpos($this->sql_query, ' from', 0);

      $pos_group_by = strpos($this->sql_query, ' group by', $pos_from);
      if (($pos_group_by < $pos_to) && ($pos_group_by != false)) $pos_to = $pos_group_by;

      $pos_having = strpos($this->sql_query, ' having', $pos_from);
      if (($pos_having < $pos_to) && ($pos_having != false)) $pos_to = $pos_having;

      $pos_order_by = strpos($this->sql_query, ' order by', $pos_from);
      if (($pos_order_by < $pos_to) && ($pos_order_by != false)) $pos_to = $pos_order_by;

      if (strpos($this->sql_query, 'distinct') || strpos($this->sql_query, 'group by')) {
        $count_string = 'distinct ' . tep_db_input($count_key);
      } else {
        $count_string = tep_db_input($count_key);
      }

      $count_query = tep_db_query("select count(" . $count_string . ") as total " . substr($this->sql_query, $pos_from, ($pos_to - $pos_from)));
      $count = tep_db_fetch_array($count_query);

      $this->number_of_rows = $count['total'];
      if ($this->number_of_rows_per_page <= '0'){
         $this->number_of_rows_per_page = '1';
         }
      $this->number_of_pages = ceil($this->number_of_rows / $this->number_of_rows_per_page);

      if ($this->current_page_number > $this->number_of_pages) {
        $this->current_page_number = $this->number_of_pages;
      }
      if ($offset < '0'){
         $offset = '1';
         }
      $offset = ($this->number_of_rows_per_page * ($this->current_page_number - 1));

      $this->sql_query .= " limit " . max($offset, 0) . ", " . $this->number_of_rows_per_page;
    }

// display split-page-number-links
    function display_links2($max_page_links, $parameters = '') {
      global $PHP_SELF, $request_type;
	  $request_type='NONSSL';

      $display_links_string = '';

      $class = 'class="pageResults"';

      if (tep_not_null($parameters) && (substr($parameters, -1) != '&')) $parameters .= '&';

// previous button - not displayed on first page
      if ($this->current_page_number > 1) $display_links_string .= '<a href="' . tep_href_link(basename($PHP_SELF), $parameters . $this->page_name . '=' . ($this->current_page_number - 1), $request_type) . '" class="pageResults" title=" ' . PREVNEXT_TITLE_PREVIOUS_PAGE . ' "><u>' . PREVNEXT_BUTTON_PREV . '</u></a>&nbsp;&nbsp;';

// check if number_of_pages > $max_page_links
      $cur_window_num = intval($this->current_page_number / $max_page_links);
      if ($this->current_page_number % $max_page_links) $cur_window_num++;

      $max_window_num = intval($this->number_of_pages / $max_page_links);
      if ($this->number_of_pages % $max_page_links) $max_window_num++;

// previous window of pages
      if ($cur_window_num > 1) $display_links_string .= '<a href="' . tep_href_link(basename($PHP_SELF), $parameters . $this->page_name . '=' . (($cur_window_num - 1) * $max_page_links), $request_type) . '" class="pageResults" title=" ' . sprintf(PREVNEXT_TITLE_PREV_SET_OF_NO_PAGE, $max_page_links) . ' ">...</a>';

// page nn button
      for ($jump_to_page = 1 + (($cur_window_num - 1) * $max_page_links); ($jump_to_page <= ($cur_window_num * $max_page_links)) && ($jump_to_page <= $this->number_of_pages); $jump_to_page++) {
        if ($jump_to_page == $this->current_page_number) {
          $display_links_string .= '&nbsp;<b>' . $jump_to_page . '</b>&nbsp;';
        } else {
          $display_links_string .= '&nbsp;<a href="' . tep_href_link(basename($PHP_SELF), $parameters . $this->page_name . '=' . $jump_to_page, $request_type) . '" class="pageResults" title=" ' . sprintf(PREVNEXT_TITLE_PAGE_NO, $jump_to_page) . ' "><u>' . $jump_to_page . '</u></a>&nbsp;';
        }
      }

// next window of pages
      if ($cur_window_num < $max_window_num) $display_links_string .= '<a href="' . tep_href_link(basename($PHP_SELF), $parameters . $this->page_name . '=' . (($cur_window_num) * $max_page_links + 1), $request_type) . '" class="pageResults" title=" ' . sprintf(PREVNEXT_TITLE_NEXT_SET_OF_NO_PAGE, $max_page_links) . ' ">...</a>&nbsp;';

// next button
      if (($this->current_page_number < $this->number_of_pages) && ($this->number_of_pages != 1)) $display_links_string .= '&nbsp;<a href="' . tep_href_link(basename($PHP_SELF), $parameters . 'page=' . ($this->current_page_number + 1), $request_type) . '" class="pageResults" title=" ' . PREVNEXT_TITLE_NEXT_PAGE . ' "><u>' . PREVNEXT_BUTTON_NEXT . '</u></a>&nbsp;';

      return $display_links_string;
    }

    function display_count2($text_output) {
      $to_num = ($this->number_of_rows_per_page * $this->current_page_number);
      if ($to_num > $this->number_of_rows) $to_num = $this->number_of_rows;

      $from_num = ($this->number_of_rows_per_page * ($this->current_page_number - 1));

      if ($to_num == 0) {
        $from_num = 0;
      } else {
        $from_num++;
      }

      return sprintf($text_output, $from_num, $to_num, $this->number_of_rows);
    }
  }
  	  function tep_create_sort_heading($sortby, $colnum, $heading) {
    global $PHP_SELF;

    $sort_prefix = '';
    $sort_suffix = '';
if (isset($_GET['add_product_options'])) {
          foreach($_GET['add_product_options'] as $option_id => $option_value_id) {
		  $heading_array.='&add_product_options['.$option_id.']='.$option_value_id;}}
    if ($sortby) {
      $sort_prefix = '<a href="' . tep_href_link(basename($PHP_SELF), tep_get_all_get_params(array('page', 'add_product_options', 'sort')) . 'page=1&sort=' . $colnum . ($sortby == $colnum . 'a' ? 'd' : 'a')) . $heading_array.'" title="' . tep_output_string(TEXT_SORT_PRODUCTS . ($sortby == $colnum . 'd' || substr($sortby, 0, 1) != $colnum ? TEXT_ASCENDINGLY : TEXT_DESCENDINGLY) . TEXT_BY . $heading) . '"  style="color:#FFFFFF;text-decoration:none;font-weight:bold" onmouseover="this.style.textDecoration=\'underline\'" onmouseout="this.style.textDecoration=\'none\'">' ;
      $sort_suffix = (substr($sortby, 0, 1) == $colnum ? (substr($sortby, 1, 1) == 'a' ? '(+)' : '(-)') : '') . '</a>';
    }

    return $sort_prefix .$heading .$sort_suffix;
  }  
/*	    function tep_get_subcategories(&$subcategories_array, $parent_id = 0) {
    $subcategories_query = tep_db_query("select distinct categories_id from " . TABLE_CATEGORIES . " where parent_id = '" . (int)$parent_id . "'");
    while ($subcategories = tep_db_fetch_array($subcategories_query)) {
      $subcategories_array[sizeof($subcategories_array)] = $subcategories['categories_id'];
      if ($subcategories['categories_id'] != $parent_id) {
        tep_get_subcategories($subcategories_array, $subcategories['categories_id']);
      }
    }
  }*/
  	    function tep_get_subcategories_sql(&$subcategories_sql, $parent_id = 0) {
		global $level;
    $subcategories_query = tep_db_query("select distinct categories_id from " . TABLE_CATEGORIES . " where parent_id = '" . (int)$parent_id . "'");

    while ($subcategories = tep_db_fetch_array($subcategories_query)) {
    $subcategories_sql[sizeof($subcategories_sql)] = $subcategories['categories_id'];
 $level.=$subcategories['categories_id'].',';
      if ($subcategories['categories_id'] != $parent_id) {
  $subcategories_requete.= $subcategories['categories_id'].',';
        tep_get_subcategories($subcategories_sql, $subcategories['categories_id']);
      }
    }

if ($parent_id!='ALL')$level.=$parent_id.',';
	return $subcategories_requete. $parent_id;
  }
  
  function tep_get_subcat_mysql4($value){
  $subcategories_query = tep_db_query("SELECT p2c.products_id FROM ".TABLE_PRODUCTS_TO_CATEGORIES." p2c WHERE p2c.categories_id in (".$value.")");
  while ($subcategories = tep_db_fetch_array($subcategories_query)) {
  $subcategories_requete.= $subcategories['products_id'].',';}
 return substr($subcategories_requete,0,strlen($subcategories_requete)-1);
  }
  
   function tep_get_manufacturer_products_id($manufacturer='0'){
   $products_query = tep_db_query("select distinct p.products_id from " . TABLE_PRODUCTS . " p join " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c on p.products_id=p2c.products_id join " . TABLE_CATEGORIES . " c on  p2c.categories_id=c.categories_id where p.manufacturers_id='".(int)$manufacturer."'");
   if (tep_db_num_rows($products_query)>0){
  while ($manufacturer_products = tep_db_fetch_array($products_query)) {
  $manufacturer_products_requete.= $manufacturer_products['products_id'].',';}
 return substr($manufacturer_products_requete,0,strlen($manufacturer_products_requete)-1);}else{return 0;}
  }
 
  function tep_get_manufacturer_products($manufacturer,$categorie){
  if ($manufacturer=='ALL') return true;
$found=false;
   $products_query = tep_db_query("select distinct p.manufacturers_id,p2c.categories_id,c.parent_id from " . TABLE_PRODUCTS . " p join " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c on p.products_id=p2c.products_id join " . TABLE_CATEGORIES . " c on  p2c.categories_id=c.categories_id where p.manufacturers_id='".(int)$manufacturer."'");
   
    while ($test_categories =tep_db_fetch_array($products_query)){
	if ($test_categories['categories_id']==$categorie ||  $test_categories['parent_id']==$categorie)$found=true;}

	return  $found;
	}
  
  
  function tep_get_category_manufacturer($parent_id = '0', $spacing = '', $exclude = '',$manufacturer = '', $category_tree_array = '', $include_itself = false) {
    global $languages_id;
if ($exclude=='ALL')$exclude=0;
    if (!is_array($category_tree_array)) $category_tree_array = array();
    if ( (sizeof($category_tree_array) < 1) && ($exclude != '0') ) $category_tree_array[] = array('id' => '0', 'text' => TEXT_TOP);

    if ($include_itself) {
      $category_query = tep_db_query("select cd.categories_name from " . TABLE_CATEGORIES_DESCRIPTION . " cd where cd.language_id = '" . (int)$languages_id . "' and cd.categories_id = '" . (int)$parent_id . "'");
      $category = tep_db_fetch_array($category_query);
      $category_tree_array[] = array('id' => $parent_id, 'text' => $category['categories_name']);
    }

    $categories_query = tep_db_query("select c.categories_id, cd.categories_name, c.parent_id from " . TABLE_CATEGORIES . " c, " . TABLE_CATEGORIES_DESCRIPTION . " cd where c.categories_id = cd.categories_id and cd.language_id = '" . (int)$languages_id . "' and c.parent_id = '" . (int)$parent_id . "' order by c.sort_order, cd.categories_name");
    while ($categories = tep_db_fetch_array($categories_query)) {
      if ($exclude != $categories['categories_id'])
	  if (tep_get_manufacturer_products($manufacturer,$categories['categories_id'])==true){
	   $category_tree_array[] = array('id' => $categories['categories_id'], 'text' => $spacing . $categories['categories_name']);}
      $category_tree_array = tep_get_category_manufacturer($categories['categories_id'], $spacing . '&nbsp;&nbsp;&nbsp;', $exclude,$manufacturer, $category_tree_array);
    }

    return $category_tree_array;
  }  

// detecte les constantes ou initialise
  $today = getdate();
  if ($_GET['year']) $year = tep_db_prepare_input($_GET['year']); 
  else $year = $today['year']; //  else $year = 'ALL';
  if ($_GET['month']) $month = tep_db_prepare_input($_GET['month']); 
  else $month = $today['mon']; //  else $month = 'ALL';
  if ($_GET['day']) $day = tep_db_prepare_input($_GET['day']); 
  else $day = 'ALL'; //  else $day = 'ALL'; 
    if ($_GET['yearE']&&$day != 'ALL' && $_GET['yearE']!= 'ALL') $yearE = tep_db_prepare_input($_GET['yearE']); 
  else $yearE = $year; //  else $year = 'ALL';
  if ($_GET['monthE']&&$day != 'ALL'&& $_GET['monthE']!= 'ALL') $monthE = tep_db_prepare_input($_GET['monthE']); 
  else $monthE = $month; //  else $month = 'ALL';
  if ($_GET['dayE']&&$day != 'ALL'&& $_GET['dayE']!= 'ALL') $dayE = tep_db_prepare_input($_GET['dayE']); 
  else $dayE = $day; //  else $day = 'ALL'; 
       if ($_GET['customer_selected']) $customer_selected = tep_db_prepare_input($_GET['customer_selected']); 
  else $customer_selected = 'ALL'; 
      if ($_GET['manufacturer_selected']) $manufacturer_selected = tep_db_prepare_input($_GET['manufacturer_selected']); 
  else $manufacturer_selected = 'ALL'; 
      if ($_GET['categorie_selected']) $categorie_selected = tep_db_prepare_input($_GET['categorie_selected']); 
  else $categorie_selected = 'ALL';
    if ($_GET['product_selected']) $product_select = tep_db_prepare_input($_GET['product_selected']); 
  else $product_selected = 'ALL';
  if ($_GET['no_status']) $no_status = tep_db_prepare_input($_GET['no_status']); 
  if ($_GET['status']) $status = tep_db_prepare_input($_GET['status']); 
       if ($_GET['reference_selected']) $reference_select = tep_db_prepare_input($_GET['reference_selected']); 
  else $reference_selected = 'ALL'; 
  
  //  get list of day for dropdown selection
	$resetdate=0;
		if($day != 'ALL'){
  $datestart=mktime(0, 0, 0, date($month), date($day), date($year));
  $datestartreset=mktime(0, 0, 0, date($month), date($day+1), date($year));
  $dateend=mktime(0, 0, 0, date($monthE), date($dayE+1), date($yearE));
    $datetest=mktime(0, 0, 0, date($monthE), date($dayE), date($yearE));

	if($datestart>$datetest){$yearE = $year;$monthE = $month;$dayE = $day;$dateend=$datestartreset;$resetdate=1;}
}
  //manufacturers
      $manufacturers_array = array(array('id' => '', 'text' => TEXT_SELECT_MANUFACTURER),
	  							   array('id' => '0', 'text' => TEXT_ALL_MANUFACTURERS));
    $manufacturers_query = tep_db_query("select manufacturers_id, manufacturers_name from " . TABLE_MANUFACTURERS . " order by manufacturers_name");
    while ($manufacturers = tep_db_fetch_array($manufacturers_query)) {
      $manufacturers_array[] = array('id' => $manufacturers['manufacturers_id'],
                                     'text' => $manufacturers['manufacturers_name']);
    }
  //customers
      $customers_array = array(array('id' => '', 'text' => TEXT_SELECT_CUSTOMER),
	  							   array('id' => '0', 'text' => TEXT_ALL_CUSTOMERS));
    $customers_query = tep_db_query("select customers_id, customers_firstname, customers_lastname from " . TABLE_CUSTOMERS . " order by customers_lastname");
    while ($customers = tep_db_fetch_array($customers_query)) {
      $customers_array[] = array('id' => $customers['customers_id'],
                                     'text' => $customers['customers_lastname'].' '.$customers['customers_firstname']);
    }
  
$optionIn=true;

if ($month!= 'ALL' && $year!= 'ALL'){
      $mois = mktime( 0, 0, 0, 2, 1, $year ); 
      setlocale(LC_ALL, 'fr_FR');

 $nombreDeJours = intval(date("t",$mois)); 

 if($nombreDeJours==29){
  $num_day = array(31,29,31,30,31,30,31,31,30,31,30,31);}else{
  $num_day = array(31,28,31,30,31,30,31,31,30,31,30,31);}}else{$num_day=31;}	
  $list_day = array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12' ,'13' , '14' , '15' , '16' , '17' , '18' , '19' , '20' , '21' , '22' , '23' , '24' , '25' , '26' , '27' , '28' , '29' , '30' , '31');

  if (is_array($num_day)){$num_day=$num_day[$month-1];}
  for ($i = 0, $n = $num_day; $i < $n; $i++) {
    $list_day_array[] = array('id' => $i+1,
                                'text' => $list_day[$i]);
}
//listing du nombre de jour de fin
if ($day!= 'ALL' && $month!= 'ALL' && $year!= 'ALL' && $monthE!= 'ALL' && $yearE!= 'ALL'){
      $moisE = mktime( 0, 0, 0, 2, 1, $yearE ); 
 $nombreDeJoursE = intval(date("t",$moisE)); 

 if($nombreDeJoursE==29){
  $num_dayE = array(31,29,31,30,31,30,31,31,30,31,30,31);}else{
  $num_dayE = array(31,28,31,30,31,30,31,31,30,31,30,31);}}else{$num_dayE=31;}	
  $list_dayE = array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12' ,'13' , '14' , '15' , '16' , '17' , '18' , '19' , '20' , '21' , '22' , '23' , '24' , '25' , '26' , '27' , '28' , '29' , '30' , '31');
  if (is_array($num_dayE)){$num_dayE=$num_dayE[$monthE-1];}
  for ($i = 0, $n = $num_dayE; $i < $n; $i++) {
    $list_day_arrayE[] = array('id' => $i+1,
                                'text' => $list_dayE[$i]);
}

        if (isset($_GET['add_product_options']) && $optionIn==true) {
          foreach($_GET['add_product_options'] as $option_id => $option_value_id) {

            $result = tep_db_query("SELECT * FROM " . TABLE_PRODUCTS_ATTRIBUTES . " pa INNER JOIN " . TABLE_PRODUCTS_OPTIONS . " po ON (po.products_options_id = pa.options_id and po.language_id = '" . $languages_id . "') INNER JOIN " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov on (pov.products_options_values_id = pa.options_values_id and pov.language_id = '" . $languages_id . "') WHERE products_id = '" . $product_select . "' and options_id = '" . $option_id . "' and options_values_id = '" . $option_value_id . "'");
            $row = tep_db_fetch_array($result);

			if($opt_options_id!=0)$option_on=true;
			
          $option_value_details[$option_id][$option_value_id] = array (
			"options_id" => ($opt_options_id==0)?'ALL':$opt_options_id,
					"options_values_price" => $opt_options_values_price,
					"price_prefix" => $opt_price_prefix);
			$option_val_id.= ($option_value_id==0)?'0,':$opt_options_id.','.$option_value_id.'|';	
            $option_names[$option_id] = $opt_products_options_name;
            $option_values_names[$option_value_id] = $opt_products_options_values_name;
			 }

			 }

//recherche produit
    $product_search = " where p.products_status = '1' and p.products_id = pd.products_id and pd.language_id = '" . $languages_id . "' and p.products_id = p2c.products_id and p2c.categories_id = c.categories_id ";
    

      tep_get_subcategories($subcategories_array, $categorie_selected);
      $product_search .= " and p2c.products_id = p.products_id and p2c.products_id = pd.products_id and (p2c.categories_id = '" . (int)$categorie_selected . "'";
      for ($i=0, $n=sizeof($subcategories_array); $i<$n; $i++ ) {
        $product_search .= " or p2c.categories_id = '" . $subcategories_array[$i] . "'";
      }
      $product_search .= ")";
    } else {
      $product_search .= " and p2c.products_id = p.products_id and p2c.products_id = pd.products_id and pd.language_id = '" . $languages_id . "' and p2c.categories_id = '" . (int)$categorie_selected . "'";
    }
if ($manufacturer_selected != 'ALL') {
    $product_search .= " and p.manufacturers_id='".(int)$manufacturer_selected."'";	
}	
if ($reference_selected != 'ALL') {
    $product_search .= " and p.products_model like '".$reference_select."'";	
}	

 $products_query = tep_db_query("select distinct p.products_id, p.products_price, p.products_model, pd.products_name from " . TABLE_PRODUCTS . " p join " . TABLE_PRODUCTS_DESCRIPTION . " pd join " . TABLE_CATEGORIES . " c join " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c " . $product_search . " order by pd.products_name");
 $not_found = ((tep_db_num_rows($products_query)) ? false : true);
  

  $category_array = array(array('id' => '', 'text' => TEXT_SELECT_CATEGORY),
                          array('id' => 0, 'text' => TEXT_ALL_CATEGORIES));
  

    $product_array = array(array('id' => '', 'text' => TEXT_SELECT_PRODUCT),
						   array('id' => 0, 'text' => TEXT_ALL_PRODUCTS));
	
	$reference_array =array();
							   
  while($products = tep_db_fetch_array($products_query)) {

      $product_array[] = array('id' => $products['products_id'],
                               'text' => $products['products_name'] . ' (' . $products['products_model'] . ')' . ':&nbsp;' . $currencies->format($products['products_price']));}
							   
$reference_query_raw ="select distinct p.products_model from " . TABLE_PRODUCTS . " p join " . TABLE_PRODUCTS_DESCRIPTION . " pd join " . TABLE_CATEGORIES . " c join " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c " . $product_search  ;
if ($reference_select != 'ALL') {
   $reference_query_raw .= " and  p.products_model like '".$reference_select."'";	
}
if ($manufacturer_selected != 'ALL') {
    $reference_query_raw .= " and manufacturers_id='".(int)$manufacturer_selected."'";	
}
if ($product_selected!='ALL'){
$reference_query_raw .= " and  p.products_id = '".$product_select."'";}
$reference_query_raw .= " order by products_model";
$reference_query = tep_db_query($reference_query_raw);
$display_name=(tep_db_num_rows( $reference_query)>1)?true:false;


    while($reference = tep_db_fetch_array($reference_query)) {

$reference_array[] = array('id' => $reference['products_model'],
                           'text' => $reference['products_model']);
						   }						   
   
  

 // $reference_array=array_unique($reference_array);
  sort($reference_array);
 $reference_array=array_merge( array(array('id' => '', 'text' => TEXT_SELECT_REFERENCE),
						   array('id' => 0, 'text' => TEXT_ALL_REFERENCE)),$reference_array);
  $has_attributes = false;
  $products_attributes_query = tep_db_query("select count(*) as total from " . TABLE_PRODUCTS_OPTIONS . " popt INNER join " . TABLE_PRODUCTS_ATTRIBUTES . " patrib where patrib.products_id='" . (int)$product_selected . "' and patrib.options_id = popt.products_options_id and popt.language_id = '" . $languages_id . "'");
  $products_attributes = tep_db_fetch_array($products_attributes_query);
  if ($products_attributes['total'] > 0) $has_attributes = true; 


//* customers product
//creation de la liste des catégories ss-catégorie,options
 $firstlevel=tep_get_subcategories_sql($subcategories_sql,$categorie_selected);
  $level2=explode(',',$firstlevel);
 for($i=0;$i<sizeof($level2);$i++){
 tep_get_subcategories_sql($subcategories_sql,$level2[$i]);
}

 $level=substr($level,0,strlen($level)-1);

		$order_product_query_raw = "select o.date_purchased as date, o.orders_id as id,";
$order_product_query_raw .= " cu.customers_lastname, o.customers_name as customer,";
$order_product_query_raw .= " o.customers_street_address as street,";
$order_product_query_raw .= " o.customers_city as address,o.customers_email_address as email,";
$order_product_query_raw .= " o.customers_telephone as telephone,o.payment_method as payment,o.customers_postcode as postcode,";
$order_product_query_raw .= " op.products_quantity as quantity, op.products_name as product_name,";
$order_product_query_raw .= " op.products_id as product_id,op.products_id as product_id,op.products_tax as taxe,";
$order_product_query_raw .= " os.orders_status_name as status,op.final_price  as final_price";
$order_product_query_raw .= " from ".TABLE_ORDERS." o";
$order_product_query_raw .= " left join ".TABLE_ORDERS_PRODUCTS." op on o.orders_id = op.orders_id";
$order_product_query_raw .= " left join ".TABLE_ORDERS_STATUS." os on os.orders_status_id = o.orders_status and os.language_id = '".$languages_id."'";
$order_product_query_raw .= " left join ".TABLE_CUSTOMERS." cu on o.customers_id = cu.customers_id left join " . TABLE_PRODUCTS . " p on (p.products_id = op.products_id) ";
$order_product_query_raw .= " where ";
//
    $order_product_query_raw .=($product_selected != 'ALL') ? " op.products_id=".(int)$product_selected:"op.products_id>0";
if ($customer_selected != 'ALL') {
    $order_product_query_raw .= " and o.customers_id='".$customer_selected."'";	
}
if ($categorie_selected != 'ALL') {
    $order_product_query_raw .= " and op.products_id IN (".tep_get_subcat_mysql4($level).")";	
}
if ($manufacturer_selected !='ALL'){
    $order_product_query_raw .= " and op.products_id IN (".tep_get_manufacturer_products_id($manufacturer_selected).")";	
}

if ($reference_select != 'ALL') {
    $order_product_query_raw .= " and p.products_model like '".$reference_select."'";	
}

         if (isset($_GET['add_product_options'])&&$has_attributes ==true  && $optionIn==true) {
          foreach($_GET['add_product_options'] as $option_id => $option_value_id) {

	          $result_query_raw = "SELECT distinct(op.orders_products_id) FROM " . TABLE_ORDERS_PRODUCTS . " op  INNER JOIN  ".TABLE_ORDERS_PRODUCTS_ATTRIBUTES." pa on pa.orders_products_id=op.orders_products_id WHERE ";
 $result_query_raw .=((int)$option_value_id!=0)? " pa.products_options_id='".(int)$option_id."' and pa.products_options_values_id='".(int)$option_value_id."' and op.products_id='".(int)$product_selected."'":"1=1";
   $result_query=tep_db_query($result_query_raw);
if(tep_db_num_rows($result_query)>0) {
$sql='';
while ($sql_result = tep_db_fetch_array($result_query)) {
$sql.=$sql_result['orders_products_id'].',';
}
$sql=substr($sql,0,strlen($sql)-1);
$order_product_query_raw .=" and op.orders_products_id IN (".$sql.")";
}elseif((int)$option_value_id!=0){$order_product_query_raw .=" and op.orders_products_id IN (0)";}
			 }

			 }
	if($day!= 'ALL' && $datestart<$dateend){$order_product_query_raw .=" AND o.date_purchased >= '" . tep_db_input(date("Y-m-d\TH:i:s", $datestart)) . "' AND o.date_purchased < '" . tep_db_input(date("Y-m-d\TH:i:s", $dateend)) . "'";}else{		 
  if ($day != 'ALL') $order_product_query_raw .= " and DAYOFMONTH(o.date_purchased) = " . $day ;			 
  if ($month != 'ALL') $order_product_query_raw .= " and MONTH(o.date_purchased) = " . $month ;
  if ($year != 'ALL') $order_product_query_raw .= " and YEAR(o.date_purchased) = " . $year;	 }
  if ($no_status) $order_product_query_raw .= " and o.orders_status <> " . $no_status;
  if ($status) $order_product_query_raw .= " and o.orders_status = " . $status;

    $define_list = array('DATE_ORDERS' => '1',
                         'ORDER_ID' => '2',
                         'ORDER_CUSTOMER' => '3',
						 'ORDER_ADDRESS' => '4',
						 'ORDER_PRODUCT' => '5',
						 'ORDER_QUANTITY' => '6',
                         'ORDER_STATUS' => '7',
                         'ORDER_TOTAL' => '8');

    asort($define_list);

    $column_list = array();
    reset($define_list);
	foreach( $define_list as $key => $value ) {
      if ($value > 0) $column_list[] = $key;
    }
    if ( (!isset($_GET['sort'])) || (!preg_match('/[1-8][ad]/i', $_GET['sort'])) || (substr($_GET['sort'], 0, 1) > sizeof($column_list)) ) {
      for ($i=0, $n=sizeof($column_list); $i<$n; $i++) {
        if ($column_list[$i] == 'DATE_ORDERS') {
          $_GET['sort'] = ($i+1) . 'a';
          $order_product_query_raw .= " order by o.date_purchased desc";
          break;
        }
      }
    } else {
      $sort_col = substr($_GET['sort'], 0 , 1);
      $sort_order = substr($_GET['sort'], 1);
      $order_product_query_raw .= ' order by ';
      switch ($column_list[$sort_col-1]) {
        case 'DATE_ORDERS':
          $order_product_query_raw .= "o.date_purchased " . ($sort_order == 'd' ? 'desc' : '');
          break;
        case 'ORDER_ID':
          $order_product_query_raw .= "o.orders_id " . ($sort_order == 'd' ? 'desc' : '');
          break;
        case 'ORDER_CUSTOMER':
          $order_product_query_raw .= "cu.customers_lastname " . ($sort_order == 'd' ? 'desc' : '');
          break;
		case 'ORDER_ADDRESS':
          $order_product_query_raw .= "o.customers_city " . ($sort_order == 'd' ? 'desc' : '');
          break; 
		case 'ORDER_PRODUCT':
          $order_product_query_raw .= "op.products_name " . ($sort_order == 'd' ? 'desc' : '');
          break;		   
        case 'ORDER_QUANTITY':
          $order_product_query_raw .= "op.products_quantity " . ($sort_order == 'd' ? 'desc' : '');
          break;
        case 'ORDER_STATUS':
          $order_product_query_raw .= "os.orders_status_name " . ($sort_order == 'd' ? 'desc' : '');
          break;		  
        case 'ORDER_TOTAL':
          $order_product_query_raw .= "(op.final_price*op.products_quantity) " . ($sort_order == 'd' ? 'desc' : '');
          break;
      }
    }


//  get list of years for dropdown selection
$year_begin_query = tep_db_query(" select startdate from ".TABLE_COUNTER);
$year_begin = tep_db_fetch_array($year_begin_query);
$year_begin = substr($year_begin['startdate'], 0, 4);
$current_year = $year_begin;
while ($current_year != $today['year'] + 1) {
  $list_year_array[] = array('id' => $current_year,
                              'text' => $current_year);
$current_year++;
}


//  get list of month for dropdown selection
  $list_month = array(JAN, FEV, MAR, AVR, MAI, JUN, JUI, AOU, SEP, OCT, NOV, DEC);
  for ($i = 0, $n = sizeof($list_month); $i < $n; $i++) {
    $list_month_array[] = array('id' => $i+1,
                                'text' => $list_month[$i]);
}


// get list of orders_status names for dropdown selection
  $orders_statuses = array();
  $orders_status_array = array();
  $orders_status_query = tep_db_query("select orders_status_id, orders_status_name from " . TABLE_ORDERS_STATUS . " where language_id = '" . $languages_id . "'");
  while ($orders_status = tep_db_fetch_array($orders_status_query)) {
    $orders_statuses[] = array('id' => $orders_status['orders_status_id'],
                 'text' => $orders_status['orders_status_name']);
    $orders_status_array[$orders_status['orders_status_id']] = $orders_status['orders_status_name'];
     };

// Total new_customers
  $new_customers_query_raw = "select count(ci.customers_info_id) as tot_new_customers from ". TABLE_ORDERS . " o left join ".TABLE_ORDERS_PRODUCTS." op on o.orders_id=op.orders_id left join ".TABLE_ORDERS_STATUS." os on os.orders_status_id=o.orders_status and os.language_id = '" . $languages_id . "' left join ".TABLE_CUSTOMERS." cu on o.customers_id=cu.customers_id left join " . TABLE_CUSTOMERS_INFO . " ci on ci.customers_info_id=o.customers_id  left join " . TABLE_PRODUCTS . " p on (p.products_id = op.products_id)  where ";

    $new_customers_query_raw .=($product_selected != 'ALL') ? " op.products_id=".(int)$product_selected:"op.products_id>0";
if ($categorie_selected != 'ALL') {
    $new_customers_query_raw .= " and op.products_id IN (".tep_get_subcat_mysql4($level).")";
}
if ($customer_selected != 'ALL') {
    $new_customers_query_raw .= " and o.customers_id='".$customer_selected."'";	
}
if ($manufacturer_selected !='ALL'){
    $new_customers_query_raw .= " and op.products_id IN (".tep_get_manufacturer_products_id($manufacturer_selected).")";	
}
if ($reference_select != 'ALL') {
    $new_customers_query_raw .= " and p.products_model like '".$reference_select."'";	
}
         if (isset($_GET['add_product_options'])&&$has_attributes ==true  && $optionIn==true) {
          foreach($_GET['add_product_options'] as $option_id => $option_value_id) {

	          $result_query_raw = "SELECT distinct(op.orders_products_id) FROM " . TABLE_ORDERS_PRODUCTS . " op  left JOIN  ".TABLE_ORDERS_PRODUCTS_ATTRIBUTES." pa on pa.orders_products_id=op.orders_products_id WHERE ";
 $result_query_raw .=((int)$option_value_id!=0)? " pa.products_options_id='".(int)$option_id."' and pa.products_options_values_id='".(int)$option_value_id."' and op.products_id='".(int)$product_selected."'":"1=1";
   $result_query=tep_db_query($result_query_raw);
if(tep_db_num_rows($result_query)>0) {
$sql='';
while ($sql_result = tep_db_fetch_array($result_query)) {
$sql.=$sql_result['orders_products_id'].',';
}
$sql=substr($sql,0,strlen($sql)-1);
$new_customers_query_raw .=" and op.orders_products_id IN (".$sql.")";
}elseif((int)$option_value_id!=0){$new_customers_query_raw .=" and op.orders_products_id IN (0)";}
			 }

			 }
			 if($day!= 'ALL' && $datestart<$dateend){$new_customers_query_raw .=" AND ci.customers_info_date_account_created >= '" . tep_db_input(date("Y-m-d\TH:i:s", $datestart)) . "' AND ci.customers_info_date_account_created < '" . tep_db_input(date("Y-m-d\TH:i:s", $dateend)) . "'";}else{	
  if ($day != 'ALL') $new_customers_query_raw .= " and DAYOFMONTH(ci.customers_info_date_account_created) = " . $day ;			 
  if ($month != 'ALL') $new_customers_query_raw .= " and MONTH(ci.customers_info_date_account_created) = " . $month ;
  if ($year != 'ALL') $new_customers_query_raw .= " and YEAR(ci.customers_info_date_account_created) = " . $year;}
  if ($no_status) $new_customers_query_raw .= " and o.orders_status <> " . $no_status;
  if ($status) $new_customers_query_raw .= " and o.orders_status = " . $status;  
  $new_customers_query = tep_db_query($new_customers_query_raw);
  $new_customers = tep_db_fetch_array($new_customers_query);
  $new_customers_count = $new_customers['tot_new_customers'];

//* Total distinct customers
  $customers_query_raw = "select distinct(customers_id) from " . TABLE_ORDERS . " o left join ".TABLE_ORDERS_PRODUCTS." op on o.orders_id=op.orders_id left join ".TABLE_ORDERS_STATUS." os on os.orders_status_id=o.orders_status and os.language_id = '" . $languages_id . "'  left join " . TABLE_PRODUCTS . " p on (p.products_id = op.products_id)  where ";

    $customers_query_raw .=($product_selected != 'ALL') ? " op.products_id=".(int)$product_selected:"op.products_id>0";
if ($categorie_selected != 'ALL') {
    $customers_query_raw .= " and op.products_id IN (".tep_get_subcat_mysql4($level).")";
}
if ($customer_selected != 'ALL') {
    $customers_query_raw .= " and o.customers_id='".$customer_selected."'";	
}
if ($manufacturer_selected !='ALL'){
    $customers_query_raw .= " and op.products_id IN (".tep_get_manufacturer_products_id($manufacturer_selected).")";	
}
if ($reference_select != 'ALL') {
    $customers_query_raw .= " and p.products_model like '".$reference_select."'";	
}
         if (isset($_GET['add_product_options'])&&$has_attributes ==true  && $optionIn==true) {
          foreach($_GET['add_product_options'] as $option_id => $option_value_id) {

	          $result_query_raw = "SELECT distinct(op.orders_products_id) FROM " . TABLE_ORDERS_PRODUCTS . " op  left JOIN  ".TABLE_ORDERS_PRODUCTS_ATTRIBUTES." pa on pa.orders_products_id=op.orders_products_id WHERE ";
 $result_query_raw .=((int)$option_value_id!=0)? " pa.products_options_id='".(int)$option_id."' and pa.products_options_values_id='".(int)$option_value_id."' and op.products_id='".(int)$product_selected."'":"1=1";
   $result_query=tep_db_query($result_query_raw);
if(tep_db_num_rows($result_query)>0) {
$sql='';
while ($sql_result = tep_db_fetch_array($result_query)) {
$sql.=$sql_result['orders_products_id'].',';
}
$sql=substr($sql,0,strlen($sql)-1);
$customers_query_raw .=" and op.orders_products_id IN (".$sql.")";
}elseif((int)$option_value_id!=0){$customers_query_raw .=" and op.orders_products_id IN (0)";}
			 }

			 }
			 if($day!= 'ALL' && $datestart<$dateend){$customers_query_raw .=" AND o.date_purchased >= '" . tep_db_input(date("Y-m-d\TH:i:s", $datestart)) . "' AND o.date_purchased < '" . tep_db_input(date("Y-m-d\TH:i:s", $dateend)) . "'";}else{	
  if ($day != 'ALL') $customers_query_raw .= " and DAYOFMONTH(o.date_purchased) = " . $day ;			 
  if ($month != 'ALL') $customers_query_raw .= " and MONTH(o.date_purchased) = " . $month ;
  if ($year != 'ALL') $customers_query_raw .= " and YEAR(o.date_purchased) = " . $year;}
  if ($no_status) $customers_query_raw .= " and o.orders_status <> " . $no_status;
  if ($status) $customers_query_raw .= " and o.orders_status = " . $status;

  $customers_query = tep_db_query($customers_query_raw);
  $customers_id_array = array();
  while ($customers = tep_db_fetch_array($customers_query)) {
    $customers_id_array[] = $customers['customers_id']; 
  }
  $customers_count = sizeof($customers_id_array);

//* Total new_customers_bought
  $new_customers_bought_query_raw = "select distinct(o.customers_id) from " . TABLE_ORDERS . " o join " . TABLE_CUSTOMERS_INFO . " ci  left join ".TABLE_ORDERS_PRODUCTS." op on o.orders_id=op.orders_id left join ".TABLE_ORDERS_STATUS." os on os.orders_status_id=o.orders_status and os.language_id = '" . $languages_id . "'  left join " . TABLE_PRODUCTS . " p on (p.products_id = op.products_id)  where ci.customers_info_id = o.customers_id and ";

    $new_customers_bought_query_raw .=($product_selected != 'ALL') ? " op.products_id=".(int)$product_selected:"op.products_id>0";
if ($categorie_selected != 'ALL') {
    $new_customers_bought_query_raw .= " and op.products_id IN (".tep_get_subcat_mysql4($level).")";
}
if ($customer_selected != 'ALL') {
    $new_customers_bought_query_raw .= " and o.customers_id='".$customer_selected."'";	
}
if ($manufacturer_selected !='ALL'){
    $new_customers_bought_query_raw .= " and op.products_id IN (".tep_get_manufacturer_products_id($manufacturer_selected).")";	
}
if ($reference_select != 'ALL') {
    $new_customers_bought_query_raw .= " and p.products_model like '".$reference_select."'";	
}
         if (isset($_GET['add_product_options'])&&$has_attributes ==true  && $optionIn==true) {
          foreach($_GET['add_product_options'] as $option_id => $option_value_id) {

	          $result_query_raw = "SELECT distinct(op.orders_products_id) FROM " . TABLE_ORDERS_PRODUCTS . " op  left JOIN  ".TABLE_ORDERS_PRODUCTS_ATTRIBUTES." pa on pa.orders_products_id=op.orders_products_id WHERE ";
 $result_query_raw .=((int)$option_value_id!=0)? " pa.products_options_id='".(int)$option_id."' and pa.products_options_values_id='".(int)$option_value_id."' and op.products_id='".(int)$product_selected."'":"1=1";
   $result_query=tep_db_query($result_query_raw);
if(tep_db_num_rows($result_query)>0) {
$sql='';
while ($sql_result = tep_db_fetch_array($result_query)) {
$sql.=$sql_result['orders_products_id'].',';
}
$sql=substr($sql,0,strlen($sql)-1);
$new_customers_bought_query_raw .=" and op.orders_products_id IN (".$sql.")";
}elseif((int)$option_value_id!=0){$new_customers_bought_query_raw .=" and op.orders_products_id IN (0)";}
			 }

			 }
			 if($day!= 'ALL' && $datestart<$dateend){$new_customers_bought_query_raw .=" AND o.date_purchased >= '" . tep_db_input(date("Y-m-d\TH:i:s", $datestart)) . "' AND o.date_purchased < '" . tep_db_input(date("Y-m-d\TH:i:s", $dateend)) . "' And ci.customers_info_date_account_created>= '" . tep_db_input(date("Y-m-d\TH:i:s", $datestart)) . "' AND ci.customers_info_date_account_created < '" . tep_db_input(date("Y-m-d\TH:i:s", $dateend)) . "'";}else{	
  if ($day != 'ALL') $new_customers_bought_query_raw .= " and DAYOFMONTH(o.date_purchased) = " . $day ." and DAYOFMONTH(ci.customers_info_date_account_created) = " . $day ;			 
  if ($month != 'ALL') $new_customers_bought_query_raw .= " and MONTH(o.date_purchased) = " . $month . " and MONTH(ci.customers_info_date_account_created) = " . $month ;
  if ($year != 'ALL') $new_customers_bought_query_raw .= " and YEAR(o.date_purchased) = " . $year .  " and YEAR(ci.customers_info_date_account_created) = " . $year;}
  if ($no_status) $new_customers_bought_query_raw .= " and o.orders_status <> " . $no_status;
  if ($status) $new_customers_bought_query_raw .= " and o.orders_status = " . $status;
  $new_customers_bought_query = tep_db_query($new_customers_bought_query_raw);
  $new_customers_bought_id_array = array();
  while ($new_customers_bought = tep_db_fetch_array($new_customers_bought_query)) {
    $new_customers_bought_id_array[] = $new_customers_bought['customers_id']; 
  }
  $new_customers_bought_count = sizeof($new_customers_bought_id_array);
  if ($new_customers_bought_count > 0) $new_customers_bought_percent = tep_round($new_customers_bought_count/$new_customers_count*100, 0);



//* Total orders
  $orders_query_raw = "select count(*) as total from " . TABLE_ORDERS . " o left join ".TABLE_ORDERS_PRODUCTS." op on o.orders_id=op.orders_id left join ".TABLE_ORDERS_STATUS." os on os.orders_status_id=o.orders_status and os.language_id = '" . $languages_id . "'  left join " . TABLE_PRODUCTS . " p on (p.products_id = op.products_id) where ";

    $orders_query_raw .=($product_selected != 'ALL') ? " op.products_id=".(int)$product_selected:"op.products_id>0";
if ($categorie_selected != 'ALL') {
    $orders_query_raw .= " and op.products_id IN (".tep_get_subcat_mysql4($level).")";
}
if ($customer_selected != 'ALL') {
    $orders_query_raw .= " and o.customers_id='".$customer_selected."'";	
}
if ($manufacturer_selected !='ALL'){
    $orders_query_raw .= " and op.products_id IN (".tep_get_manufacturer_products_id($manufacturer_selected).")";	
}
if ($reference_select != 'ALL') {
    $orders_query_raw .= " and p.products_model like '".$reference_select."'";	
}
         if (isset($_GET['add_product_options'])&&$has_attributes ==true  && $optionIn==true) {
          foreach($_GET['add_product_options'] as $option_id => $option_value_id) {

	          $result_query_raw = "SELECT distinct(op.orders_products_id) FROM " . TABLE_ORDERS_PRODUCTS . " op  INNER JOIN  ".TABLE_ORDERS_PRODUCTS_ATTRIBUTES." pa on pa.orders_products_id=op.orders_products_id WHERE ";
 $result_query_raw .=((int)$option_value_id!=0)? " pa.products_options_id='".(int)$option_id."' and pa.products_options_values_id='".(int)$option_value_id."' and op.products_id='".(int)$product_selected."'":"1=1";
   $result_query=tep_db_query($result_query_raw);
if(tep_db_num_rows($result_query)>0) {
$sql='';
while ($sql_result = tep_db_fetch_array($result_query)) {
$sql.=$sql_result['orders_products_id'].',';
}
$sql=substr($sql,0,strlen($sql)-1);
$orders_query_raw .=" and op.orders_products_id IN (".$sql.")";
}elseif((int)$option_value_id!=0){$orders_query_raw .=" and op.orders_products_id IN (0)";}
			 }

			 }
			 if($day!= 'ALL' && $datestart<$dateend){$orders_query_raw .=" AND o.date_purchased >= '" . tep_db_input(date("Y-m-d\TH:i:s", $datestart)) . "' AND o.date_purchased < '" . tep_db_input(date("Y-m-d\TH:i:s", $dateend)) . "'";}else{	
  if ($day != 'ALL') $orders_query_raw .= " and DAYOFMONTH(o.date_purchased) = " . $day ;			 
  if ($month != 'ALL') $orders_query_raw .= " and MONTH(o.date_purchased) = " . $month ;
  if ($year != 'ALL') $orders_query_raw .= " and YEAR(o.date_purchased) = " . $year;}
  if ($no_status) $orders_query_raw .= " and o.orders_status <> " . $no_status;
  if ($status) $orders_query_raw .= " and o.orders_status = " . $status;
  $orders_query = tep_db_query($orders_query_raw);
  $orders = tep_db_fetch_array($orders_query);
  $count_orders = $orders['total'];


?>

<!doctype html public "-//W3C//DTD HTML 4.01 Transitional//EN">
<html <?php echo HTML_PARAMS; ?>>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>">
<title><?php echo TITLE; ?></title>
<link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
	<script language="javascript" src="includes/general.js"></script>
	<script language="javascript" src="includes/menu.js"></script>
	<style type="text/css">	.error{background-color:#FF0000; font-weight:bold; color:#FFFFFF;}</style>
</head>
<body marginwidth="0" marginheight="0" topmargin="0" bottommargin="0" leftmargin="0" rightmargin="0" bgcolor="#FFFFFF"  onLoad="SetFocus();">
<div id="dhtmltooltip"></div>

<script type="text/javascript">
var offsetxpoint=80; //Customize x offset of tooltip
var offsetypoint=-200; //Customize y offset of tooltip
var ie=document.all;
var ns6=document.getElementById && !document.all;
var enabletip=false;
if (ie||ns6){
var tipobj=document.all? document.all["dhtmltooltip"] : document.getElementById? document.getElementById("dhtmltooltip") : "";}

function ietruebody(){
return (document.compatMode && document.compatMode!="BackCompat")? document.documentElement : document.body;
}

function ddrivetip(thetext, thecolor, thewidth){
if (ns6||ie){
if (typeof thewidth!="undefined"){ tipobj.style.width=thewidth+"px";}
if (typeof thecolor!="undefined" && thecolor!=""){ tipobj.style.backgroundColor=thecolor;}
tipobj.innerHTML=thetext;
<?php echo ($active_popup==true)?'enabletip=true;':'enabletip=false;';?>

return false;
}
}

function positiontip(e){
if (enabletip){
var curX=(ns6)?e.pageX : event.clientX+ietruebody().scrollLeft;
var curY=(ns6)?e.pageY : event.clientY+ietruebody().scrollTop;
//Find out how close the mouse is to the corner of the window
var rightedge=ie&&!window.opera? ietruebody().clientWidth-event.clientX-offsetxpoint-150 : window.innerWidth-e.clientX-offsetxpoint-150;
var bottomedge=ie&&!window.opera? ietruebody().clientHeight-event.clientY-offsetypoint : window.innerHeight-e.clientY-offsetypoint-20;

var leftedge=(offsetxpoint<0)? offsetxpoint*(-1) : -1000;

//if the horizontal distance isn't enough to accomodate the width of the context menu
if (rightedge<tipobj.offsetWidth){
//move the horizontal position of the menu to the left by it's width
tipobj.style.left=ie? ietruebody().scrollLeft+event.clientX-tipobj.offsetWidth-50+"px" : window.pageXOffset+e.clientX-tipobj.offsetWidth-320+"px";}
else if (curX<leftedge){
tipobj.style.left="5px";}
else{
//position the horizontal position of the menu where the mouse is positioned
tipobj.style.left=curX+offsetxpoint+"px";}

//same concept with the vertical position
if (bottomedge<tipobj.offsetHeight){
tipobj.style.top=ie? ietruebody().scrollTop+event.clientY-tipobj.offsetHeight-offsetypoint+"px" : window.pageYOffset+e.clientY-tipobj.offsetHeight-offsetypoint+"px";}
else{
tipobj.style.top=curY+offsetypoint+"px";}
tipobj.style.visibility="visible";
}
}

function hideddrivetip(){
if (ns6||ie){
enabletip=false;
tipobj.style.visibility="hidden";
tipobj.style.left="-1000px";
//tipobj.style.backgroundColor='white';
tipobj.style.width='300';
}
}

document.onmousemove=positiontip;
</script>
<style type="text/css"><!-- 
   #dhtmltooltip {
   position: absolute;
   width: 300px;
   padding: 0px;
   visibility: hidden;
   z-index: 100000;
   }
     .hidden
  {
  position: absolute;
  left: -1500em;
  }
  --></style>	
<?php
// set printer-friendly toggle
(tep_db_prepare_input($_GET['print']=='yes')) ? $print=true : $print=false;
?>
<!-- header //-->
<?php if(!$print) include( THEME . 'html/header.php' ); 
 if($print){?>
 <table align="center" width="100%" border="0" cellspacing="0" cellpadding="5">
      <tr>
        <td valign="top" align="left" class="main"><script type="text/javascript">
  if (window.print) {
    document.write('<a href="javascript:;" onClick="javascript:window.print()" onMouseOut=document.imprim.src="<?php echo (DIR_WS_IMAGES . 'printimage.gif'); ?>" onMouseOver=document.imprim.src="<?php echo (DIR_WS_IMAGES . 'printimage_over.gif'); ?>"><img src="<?php echo (DIR_WS_IMAGES . 'printimage.gif'); ?>" width="43" height="28" align="absbottom" border="0" name="imprim">' + '<?php echo IMAGE_BUTTON_PRINT; ?></a></center>');
  }
  else document.write ('<h2><?php echo IMAGE_BUTTON_PRINT; ?></h2>')
        </script></td>
        <td align="right" valign="bottom" class="main"><p align="right" class="main"><a href="javascript:window.close();"><img src='images/close_window.jpg' border=0></a></p></td>
      </tr>
    </table><?php }?>
<!-- header_eof //-->

<!-- body //-->
<table border="0" width="100%" cellspacing="2" cellpadding="2">
  <tr>
<?php 
	if(!$print) {?>
	<td width="<?php echo BOX_WIDTH; ?>" valign="top"><table border="0" width="<?php echo BOX_WIDTH; ?>" cellspacing="1" cellpadding="1" class="columnLeft">
	<!-- left_navigation //-->
	<?php require(DIR_WS_INCLUDES . 'column_left.php'); ?>
	<!-- left_navigation_eof //-->
        </table></td>
<?php	};	?>
<!-- body_text //-->
    <td width="100%" valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="0">
<?php if ($print) {
	echo "<tr><td class=\"pageHeading\">" . STORE_NAME ."</td></tr>";
	};
?>
      <tr>
	  <?php if (!$print &&  $optionIn==false) {;?><td class="error"><?php echo HEADING_NO_OPTIONS; ?></td></tr><tr><?php }?>
        <td class="pageHeading"><?php echo HEADING_TITLE; ?></td></tr>
		<?php if (!$print) {;?><tr align="right">
						<td class="smallText"><a href="<?php  
				echo $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING'] . "&print=yes";
				?>" target="print" title="<?php echo TEXT_BUTTON_REPORT_PRINT_DESC . "\">" . TEXT_BUTTON_REPORT_PRINT; ?></a>
				</td>
      </tr><?php };?>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.png', '1', '10'); ?></td>
      </tr>
      <tr>
        <td><table border="0" cellspacing="5" cellpadding="0"><?php echo tep_draw_form('search', FILENAME_STATS_PRODUCTS_ORDERS, '', 'get'); 
          if (isset($_GET[tep_session_name()])) {
            echo tep_draw_hidden_field(tep_session_name(), $_GET[tep_session_name()]);
          }
		   $customer_select=($customer_selected=='ALL')?0:$customer_selected;
		   $manufacturers_select=($_GET['manufacturers_selected']=='' )?0:$manufacturers_selected;
		   $categorie_select=($_GET['categorie_selected']=='' )?0:$categorie_selected;
		   $product_select=($_GET['product_selected']=='' )?0:$product_selected;
		   $reference_select=($_GET['reference_selected']=='' )?0:$reference_select;
		   $day_select=($_GET['day']=='' )?0:$_GET['day'];
		   $month_select=($_GET['month']=='' )?0:$_GET['month'];
		   $year_select=($_GET['year']=='' )?0:$_GET['year'];
		   $day_select_end=($_GET['dayE']==0 || $resetdate==1)?$day_select:$_GET['dayE'];
		   $month_select_end=($_GET['monthE']==0 || $resetdate==1 )?$month_select:$_GET['monthE'];
		   $year_select_end=($_GET['yearE']==0 || $resetdate==1 )?$year_select:$_GET['yearE'];		   
		   $no_status_select=($_GET['no_status']=='' )?0:$_GET['no_status'];
		   $status_select=($_GET['status']=='' )?0:$_GET['status'];
        ?>
 		<tr><td class="dataTableContent" align="left" colspan="5"><?php if (!$print) {echo HEADING_CUSTOMER . '&nbsp;' . tep_draw_pull_down_menu('customer_selected', $customers_array, $customer_selected,'style="width:300px;" onchange="this.form.submit();"');}else{
foreach($customers_array as $cle=>$value){
if ($value['id']==$customer_select){$customer=($customer_select==0)?TEXT_ALL_CUSTOMERS:$value['text'];break;}}
$message.=HEADING_CSV_CUSTOMER ."\t".str_replace('&nbsp;','',$customer)."\n";
echo '<div class="dataTableRow">'.HEADING_CSV_CUSTOMER . '&nbsp;' .$customer.'</div>';}		
		 ?></td></tr>       
		<tr><td class="dataTableContent" align="left" colspan="5"><?php  if (!$print) {echo HEADING_MANUFACTURER . '&nbsp;' . tep_draw_pull_down_menu('manufacturer_selected', $manufacturers_array, $manufacturer_selected,'style="width:300px;" onchange="this.form.submit();"');}else{
foreach($manufacturers_array as $cle=>$value){
if ($value['id']==$manufacturers_select){$manufacturers=($manufacturers_select==0)?TEXT_ALL_MANUFACTURERS:$value['text'];break;}}
$message.=HEADING_CSV_MANUFACTURER ."\t".str_replace('&nbsp;','',$manufacturers)."\n";
echo '<div class="dataTableRow">'.HEADING_CSV_MANUFACTURER . '&nbsp;' .$manufacturers.'</div>';}			
		 ?></td></tr>
		<tr><td class="dataTableContent" align="left" colspan="5"><?php if (!$print) {echo HEADING_CATEGORIES. '&nbsp;' . tep_draw_pull_down_menu('categorie_selected', tep_get_category_manufacturer('0', '','0',$manufacturer_selected, $category_array), $categorie_selected,'style="width:300px;" onchange="this.form.submit();"');}else{
foreach(tep_get_category_manufacturer('0', '','0',$manufacturer_selected, $category_array) as $cle=>$value){
if ($value['id']==$categorie_select){$categorie=($categorie_select==0)?TEXT_ALL_CATEGORIES:trim($value['text']);break;}}
$message.=HEADING_CSV_CATEGORIES ."\t".str_replace('&nbsp;','',$categorie)."\n";
echo '<div class="dataTableRow">'.HEADING_CSV_CATEGORIES . '&nbsp;' .$categorie.'</div>';}		
		 ?></td></tr>
		<tr><td class="dataTableContent" align="left" colspan="5"><?php if (!$print) {echo HEADING_PRODUCTS. '&nbsp;' . tep_draw_pull_down_menu('product_selected', $product_array, $product_selected, 'style="width:300px;" onchange="this.form.submit();"');}else{
foreach($product_array as $cle=>$value){
if ($value['id']==$product_select){$product=($product_select==0)?TEXT_ALL_PRODUCTS:$value['text'];break;}}	
$message.=HEADING_CSV_PRODUCTS ."\t".str_replace('&nbsp;','',$product)."\n";
echo '<div class="dataTableRow">'.HEADING_CSV_PRODUCTS . '&nbsp;' .$product.'</div>';}	
		?>
			</td>
			<?php
	
    if ($has_attributes  && $optionIn==true) {
      $i=1;
      $products_options_name_query = tep_db_query("select distinct popt.products_options_id, popt.products_options_name from " . TABLE_PRODUCTS_OPTIONS . " popt left JOIN " . TABLE_PRODUCTS_ATTRIBUTES . " patrib on (patrib.options_id = popt.products_options_id) where patrib.products_id='" . (int)$product_selected . "' and popt.language_id = '" . $languages_id . "'");
      while ($products_options_name = tep_db_fetch_array($products_options_name_query)) {
        $selected = 0;
        $products_options_array = array();

        $products_options_query = tep_db_query("select pov.products_options_values_id, pov.products_options_values_name, pa.options_values_price, pa.price_prefix from " . TABLE_PRODUCTS_ATTRIBUTES . " pa left join " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov on(pa.options_values_id = pov.products_options_values_id ) where pa.products_id = '" . (int)$product_selected . "' and pa.options_id = '" . $products_options_name['products_options_id'] . "' and pov.language_id = '" . $languages_id . "'");
		$products_options_array[] =array('id' => '0', 'text' => TEXT_ALL_OPTIONS.'&nbsp;'.$i);
        while ($products_options = tep_db_fetch_array($products_options_query)) {
          $products_options_array[] = array('id' => $products_options['products_options_values_id'], 'text' => $products_options_name['products_options_name'] . ' - ' . $products_options['products_options_values_name']);
          if ($products_options['options_values_price'] != '0') {
            $products_options_array[sizeof($products_options_array)-1]['text'] .= ' (' . $products_options['price_prefix'] . $currencies->format($products_options['options_values_price']) .')';
          }
        }
		
		if(isset($_GET['add_product_options'])) {
        $selected_attribute = $_GET['add_product_options'][$products_options_name['products_options_id']];
        } else {
          $selected_attribute = false;
        }
		
       if (!$print) { echo   '           </tr><tr> <td class="dataTableContent" valign="top"  colspan="5">' .HEADING_ATTRIBUTES.'&nbsp;'.$i.':&nbsp;'. tep_draw_pull_down_menu('add_product_options[' . $products_options_name['products_options_id'] . ']', $products_options_array, $selected_attribute,'onchange="this.form.submit();"') . '</td></tr>' . "\n" ;}else{
foreach($products_options_array as $cle=>$value){
if ($value['id']==$selected_attribute){$attribut=($selected_attribute==0)?TEXT_ALL_OPTIONS:$value['text'];break;}}
			$j=0;
$message.=HEADING_CSV_OPTIONS . ' '.$i.':' ."\t".str_replace('&nbsp;','',$attribut)."\n";
echo  '           </tr><tr> <td class="dataTableContent" valign="top"  colspan="5">' .'<div class="dataTableRow">'.HEADING_CSV_OPTIONS . '&nbsp;'.$i.': ' .$attribut.'</div>'. '</td></tr>' ;}
        $i++;
      }
  	}
			?>
				<tr><td class="dataTableContent"align="left" colspan="2"><?php if (!$print) {echo HEADING_REFERENCE; ?>&nbsp;<?php echo tep_draw_pull_down_menu('reference_selected', $reference_array, $reference_select,'style="width:300px;z-index:1;" onchange="this.form.submit();"');}else{
foreach($reference_array as $cle=>$value){
if ($value['id']==$reference_select){$reference=($reference_select==0)?TEXT_ALL_REFERENCE:$value['text'];break;}}
$message.=HEADING_CSV_REFERENCE ."\t".str_replace('&nbsp;','',$reference)."\n";
echo '<div class="dataTableRow">'.HEADING_CSV_REFERENCE . '&nbsp;' .$reference.'</div>';}					
				 ?></td></tr>				
          <tr><?php if(sizeof($list_day_array)>0 && $month!='ALL'){;?>
		  <td class="dataTableContent" align="center"><?php echo HEADING_DAY; ?>&nbsp;</td><?php };?>
            <td class="dataTableContent" align="center"><?php echo HEADING_MONTH; ?>&nbsp;</td>
            <td class="dataTableContent" align="center"><?php echo HEADING_YEAR; ?>&nbsp;</td>
<?php if ($status == '') { ?>
            <td class="dataTableContent" align="center"><?php echo HEADING_TITLE_NO_STATUS; ?>&nbsp;</td>
<?php } if ($no_status == '') { ?>
            <td class="dataTableContent" align="center"><?php echo HEADING_TITLE_STATUS; ?></td>
<?php } ?>
          </tr>
          <tr><?php if(sizeof($list_day_array)>0 && $month!='ALL'){;?>
<td class="main" align="center"><?php 
$list_day_array=array_merge(array(array('id' => 'ALL', 'text' => TEXT_ALL_DAYS)), $list_day_array);
if (!$print) {if($day!='ALL')echo HEADING_DAY_START;echo tep_draw_pull_down_menu('day', $list_day_array, $day_select, 'onChange="this.form.submit();"');
if(sizeof($list_day_arrayE)>0 && $day!='ALL'){
$list_day_arrayE=array_merge(array(array('id' => 'ALL', 'text' => TEXT_ALL_DAYS)), $list_day_arrayE);	
	echo '<br>'.HEADING_DAY_END.tep_draw_pull_down_menu('dayE', $list_day_arrayE, $day_select_end, 'onChange="this.form.submit();"');}
}else{
foreach($list_day_array as $cle=>$value){
if ($value['id']==$day){$day_select=($day==0)?TEXT_ALL_DAYS:$value['text'];break;}}
foreach($list_day_array as $cle=>$value){
if ($value['id']==$dayE){$day_select_end=($dayE==0)?TEXT_ALL_DAYS:$value['text'];break;}}
$message.=HEADING_DAY_START."\t".str_replace('&nbsp;','',$day_select)."\n";
echo '<div class="dataTableRow">'.$day_select.'</div>';
if(sizeof($list_day_arrayE)>0  && $day!='ALL' && $dayE!='ALL'){
	$message.=HEADING_DAY_END."\t".str_replace('&nbsp;','',$day_select_end)."\n";
echo '<div class="dataTableRow">'.$day_select_end.'</div>';}
}
?>&nbsp;</td><?php };?>		  
            <td class="main" align="center"><?php 
$list_month_array=array_merge(array(array('id' => 'ALL', 'text' => TEXT_ALL_MOIS)), $list_month_array);			
if (!$print) {if($day!='ALL')echo HEADING_MONTH_START;echo tep_draw_pull_down_menu('month',$list_month_array, $month_select, 'onChange="this.form.submit();"');
if(sizeof($list_month_array)>0 && $month!='ALL'&& $day!='ALL'){echo '<br>'.HEADING_MONTH_END.tep_draw_pull_down_menu('monthE',$list_month_array, $month_select_end, 'onChange="this.form.submit();"');}
}else{
foreach($list_month_array as $cle=>$value){
if ($value['id']==$month){$month_select=($month==0)?TEXT_ALL_MOIS:$value['text'];break;}}
foreach($list_month_array as $cle=>$value){
if ($value['id']==$monthE){$month_select_end=($monthE==0)?TEXT_ALL_MOIS:$value['text'];break;}}
$message.=HEADING_MONTH_START."\t".str_replace('&nbsp;','',$month_select)."\n";
echo '<div class="dataTableRow">'.$month_select.'</div>';
if(sizeof($list_month_array)>0  && $month!='ALL' && $monthE!='ALL'){
	$message.=HEADING_MONTH_END."\t".str_replace('&nbsp;','',$month_select_end)."\n";
echo '<div class="dataTableRow">'.$month_select_end.'</div>';}
}
?>&nbsp;</td>
            <td class="main" align="center"><?php 
$list_year_array=array_merge(array(array('id' => 'ALL', 'text' => TEXT_ALL_ANNEE)), $list_year_array);
if (!$print) {if($day!='ALL')echo HEADING_YEAR_START;echo tep_draw_pull_down_menu('year',$list_year_array, $year_select, 'onChange="this.form.submit();"');
if(sizeof($list_year_array)>0 && $year!='ALL'&& $day!='ALL'){echo '<br>'.HEADING_YEAR_END.tep_draw_pull_down_menu('yearE',$list_year_array, $year_select_end, 'onChange="this.form.submit();"');}
}else{
foreach($list_year_array as $cle=>$value){
if ($value['id']==$year){$year_select=($year==0)?TEXT_ALL_ANNEE:$value['text'];break;}}
foreach($list_year_array as $cle=>$value){
if ($value['id']==$yearE){$year_select_end=($yearE==0)?TEXT_ALL_ANNEE:$value['text'];break;}}
$message.=HEADING_YEAR_START."\t".str_replace('&nbsp;','',$year_select)."\n";
echo '<div class="dataTableRow">'.$year_select.'</div>';
if(sizeof($list_year_array)>0  && $year!='ALL' && $yearE!='ALL'){
	$message.=HEADING_YEAR_END."\t".str_replace('&nbsp;','',$year_select_end)."\n";
echo '<div class="dataTableRow">'.$year_select_end.'</div>';}
}
 ?>&nbsp;</td>
<?php if ($status == '') { ?>
            <td class="main" align="center"><?php 
$orders_statuses= array_merge(array(array('id' => '', 'text' => TEXT_NO_ORDERS)), $orders_statuses);
if (!$print) {echo tep_draw_pull_down_menu('no_status',$orders_statuses, $no_status_select, 'onChange="this.form.submit();"');}else{
foreach($orders_statuses as $cle=>$value){
if ($value['id']==$no_status){$no_status_select=($no_status==0)?TEXT_NO_ORDERS:$value['text'];break;}}
$message.=CSV_NO_STATUS."\t".str_replace('&nbsp;','',$no_status_select)."\n";
echo '<div class="dataTableRow">'.$no_status_select.'</div>';}
 ?>&nbsp;</td>
<?php } if ($no_status == '') { ?>
            <td class="main" align="center"><?php 
$orders_statuses=array_merge(array(array('id' => '', 'text' => TEXT_ALL_ORDERS)), $orders_statuses);
if (!$print) {echo tep_draw_pull_down_menu('status',$orders_statuses, $status_select, 'onChange="this.form.submit();"');}else{
foreach($orders_statuses as $cle=>$value){
if ($value['id']==$status){$status_select=($status==0)?TEXT_NO_ORDERS:$value['text'];break;}}
$message.=CSV_STATUS."\t".str_replace('&nbsp;','',$status_select)."\n";
echo '<div class="dataTableRow">'.$status_select.'</div>';}
 ?></td>
<?php } ?>
          </tr>
        </form></table></td>
      </tr>
      <tr>
<td><?php echo tep_draw_separator('pixel_trans.png', '10', '25'); 

  if (!$print) {$num_line=MAX_DISPLAY_SEARCH_RESULTS;}else{$num_line=tep_db_num_rows(tep_db_query($order_product_query_raw));
     			 $filename=DIR_FS_DOCUMENT_ROOT.DIR_STATS_DIRECTORY."/listing-".date("Md-Y-his").".xls";
				 
			$fp = fopen($filename, "w");
			if(!file_exists($filename))echo "<font color=red>impossible d'écrire dans le répertoire ".DIR_FS_DOCUMENT_ROOT."temp</font>";

			fputs($fp,$message."\n");
			$filename2=DIR_FS_DOCUMENT_ROOT.DIR_STATS_DIRECTORY."/result-".date("Md-Y-his").".xls";
			$fp2 = fopen($filename2, "w");
			fputs($fp2,$message."\n");
			echo '<tr><td align="right">'.'<a href="'.HTTP_SERVER.DIR_WS_CATALOG.DIR_STATS_DIRECTORY.'/listing-'.date("Md-Y-his").'.xls" target="_blank">'.ORDERS_CSV_PRINT_LISTING.'</a>'.'&nbsp;&nbsp;|&nbsp;&nbsp;<a href="'.HTTP_SERVER.DIR_WS_CATALOG.DIR_STATS_DIRECTORY.'/result-'.date("Md-Y-his").'.xls" target="_blank">'.ORDERS_CSV_PRINT_RESULT.'</a>'.'<br><br></td></tr>';	
  }
  $listing_split = new splitPageResults2($order_product_query_raw, $num_line);

  if (($listing_split->number_of_rows > 0) && ( (PREV_NEXT_BAR_LOCATION == '1') || (PREV_NEXT_BAR_LOCATION == '3') ) ) {

?>
<table border="0" width="100%" cellspacing="0" cellpadding="2">
  <tr>
    <td class="smallText"><?php echo $listing_split->display_count2(TEXT_DISPLAY_NUMBER_OF_PRODUCTS); ?></td>
    <td class="smallText" align="right"><?php echo TEXT_RESULT_PAGES . ' ' . $listing_split->display_links2(MAX_DISPLAY_PAGE_LINKS, tep_get_all_get_params(array('info', 'x', 'y'))); ?></td>
  </tr>
</table>
<?php
  };?>
   
<table border="0" cellspacing="0" cellpadding="5" width="100%">
          <tr class="dataTableHeadingRow">
            <td align="center" class="dataTableHeadingContent"><?php echo tep_create_sort_heading($_GET['sort'], 1,DATE_ORDERS); ?></td>
			<td align="center" class="dataTableHeadingContent"><?php echo tep_create_sort_heading($_GET['sort'], 2,ORDER_ID); ?></td>
			<td align="center" class="dataTableHeadingContent"><?php echo tep_create_sort_heading($_GET['sort'], 3,ORDER_CUSTOMER); ?></td>
			<td align="center" class="dataTableHeadingContent"><?php echo tep_create_sort_heading($_GET['sort'], 4,ORDER_ADDRESS); ?></td>	
	<?php if($product_selected == 'ALL'||$has_attributes==true){?>
			<td align="right" class="dataTableHeadingContent" width="150"><?php echo tep_create_sort_heading($_GET['sort'], 5,ORDER_PRODUCT); ?></td>	<?php }?>					
			<td align="center" class="dataTableHeadingContent"><?php echo tep_create_sort_heading($_GET['sort'], 6,ORDER_QUANTITY); ?></td>
			<td align="center" class="dataTableHeadingContent" width="100"><?php echo tep_create_sort_heading($_GET['sort'], 7,ORDER_STATUS); ?></td>
			<td align="right" class="dataTableHeadingContent"><?php echo tep_create_sort_heading($_GET['sort'], 8,ORDER_TOTAL); ?></td>
			</tr>
			<?php 
if ($print) {			 	fputs($fp,ORDERS_CSV_DATE."\t");
 	fputs($fp,ORDER_CSV_ID."\t");
 	fputs($fp,ORDER_CSV_CUSTOMER."\t");
 	fputs($fp,ORDER_CSV_EMAIL."\t");	
 	fputs($fp,ORDER_CSV_PHONE."\t");	
 	fputs($fp,ORDER_CSV_STREET."\t");
 	fputs($fp,ORDER_CSV_POSTCODE."\t");	
 	fputs($fp,ORDER_CSV_ADDRESS."\t");		
 	fputs($fp,ORDER_CSV_PRODUCTS_ID."\t");	
 	fputs($fp,ORDER_CSV_PRODUCT."\t");
 	fputs($fp,ORDER_CSV_PRODUCT_OPTION."\t");	
 	fputs($fp,ORDER_CSV_QUANTITY."\t");
 	fputs($fp,ORDER_CSV_STATUS."\t");
 	fputs($fp,ORDER_CSV_TOTAL_HT."\t");
 	fputs($fp,ORDER_CSV_TOTAL_TTC."\t");
 	fputs($fp,ORDER_CSV_PAYMENT."\t");		
 	fputs($fp,"\n");}
	
    $quantity=0;
	$total_ht=0;
	$total_ttc=0;
	$total_taxe=0;
	$total_query=tep_db_query($order_product_query_raw);
  while ($total_product = tep_db_fetch_array($total_query)) {
	$quantity+=$total_product['quantity'];
	$final_price= (floor((($total_product['final_price']*$total_product['quantity']) * 100) + 0.5)) / 100;
			$total_ht+=$final_price;
$total_ttc+=(floor(((($total_product['final_price']+$total_product['final_price']*$total_product['taxe']/100)*$total_product['quantity'])* 100) + 0.5)) / 100;
			$total_taxe+=(floor(((($total_product['final_price']*$total_product['taxe']/100)*$total_product['quantity'])* 100) + 0.5)) / 100;
							 
  }
  	
$order_split = new splitPageResults2($order_product_query_raw, $num_line);
$order_product_query = tep_db_query($order_split->sql_query);
if ($order_split->number_of_rows > 0) {
    while ($order_product = tep_db_fetch_array($order_product_query)) {
     
	 
?>
          <tr class="dataTableRow" onMouseOver="this.className='dataTableRowOver';this.style.cursor='hand'" onMouseOut="this.className='dataTableRow'">
            <td align="center" class="dataTableContent"><?php echo tep_date_short($order_product['date']); ?></td>			
			<td align="center" class="dataTableContent"><?php echo '<a href="'. tep_href_link(FILENAME_ORDERS, 'oID=' . $order_product['id'].'&action=edit').'" onmouseover="this.style.textDecoration=\'underline\'" onmouseout="this.style.textDecoration=\'none\' " style="color:purple" title="'.TEXT_EDIT_ORDER.'">'.$order_product['id'].'</a>'; ?></td>
			<td align="center" class="dataTableContent"><?php echo '<b>'.$order_product['customer'].'</b><br><a href="mailto:'.$order_product['email'].'" style="color:blue;" title="'.TEXT_MAILTO.'">'.$order_product['email'].'</a>';if($order_product['telephone']!='')echo '<br><font style="color:green; font-weight:bold">'.TEXT_PHONE.$order_product['telephone'].'</font>';?></td>
			<td align="left" class="dataTableContent"><?php echo $order_product['street'].'<br>'.$order_product['postcode'].'<br>'.$order_product['address'];?></td>
			<?php if($product_selected == 'ALL'||$has_attributes==true){?><td align="right" class="dataTableContent"  width="150"><?php 
			$products_query = tep_db_query("select distinct m.manufacturers_name,mi.manufacturers_url from " . TABLE_PRODUCTS . " p join " . TABLE_MANUFACTURERS . " m on p.manufacturers_id=m.manufacturers_id join " . TABLE_MANUFACTURERS_INFO . " mi on m.manufacturers_id=mi.manufacturers_id where p.products_id='".$order_product['product_id']."'");
   if (tep_db_num_rows($products_query)>0){
$manufacturer_products = tep_db_fetch_array($products_query);
  $manufacturer_name= $manufacturer_products['manufacturers_name'];
  $manufacturer_name= '<a href="' . $manufacturer_products['manufacturers_url'].'" target="_blank"><b style="color:red">'.$manufacturer_name.'</b></a>';}else{$manufacturer_url='';}

			echo '[<a href="' .  tep_href_link(FILENAME_STATS_PRODUCTS_ORDERS, 'product_selected='.$order_product['product_id'].'&manufacturer_selected='.$manufacturer_selected.'&categorie_selected='.$categorie_selected.'&year='.$year.'&month='.$month.'&day='.$day, 'NONSSL') . '" style="color:red" title="'.TEXT_CHOOSE_PRODUCT.'">'.$order_product['product_id'].'</a>] <b><a href="' .  tep_catalog_href_link('product_info.php', 'products_id='.$order_product['product_id'], 'NONSSL') . '" target="_blank" title="'.TEXT_VIEW_PRODUCT.'"  onMouseover="ddrivetip(\''.html_no_quote(display_full_price($order_product)).'\')" onMouseout="hideddrivetip()">'.strip_tags($order_product['product_name']).'</a></b><br>'.strip_tags($manufacturer_name); ?>
			<?php	    $result_query_raw = "SELECT distinct(pa.products_options),pa.products_options_values FROM  ".TABLE_ORDERS_PRODUCTS_ATTRIBUTES." pa INNER JOIN " . TABLE_ORDERS_PRODUCTS . " op  on pa.orders_products_id=op.orders_products_id WHERE op.orders_products_id='".(int)$order_product['orders_products_id']."' and op.orders_id='".(int)$order_product['id']."'";

   $result_query=tep_db_query($result_query_raw);
if(tep_db_num_rows($result_query)>0) {
while ($sql_result = tep_db_fetch_array($result_query)) {
echo '<br>'.$sql_result['products_options'].' : '.$sql_result['products_options_values'];
$fputs_options.=$sql_result['products_options'].' : '.$sql_result['products_options_values'].'<br>';
}
}
			 }?></td>
			<td align="center" class="dataTableContent"><?php echo $order_product['quantity']; ?></td>
            <td align="center" class="dataTableContent" width="100"><?php echo $order_product['status']; ?></td>
			<td align="right" class="dataTableContent"><?php 
			$final_price= (floor(($order_product['final_price']* $order_product['quantity']* 100) + 0.5)) / 100;
			$ttc_price= (floor((((($order_product['final_price']+$order_product['final_price']*$order_product['taxe']/100))*$order_product['quantity']) * 100) + 0.5)) / 100;			
			echo '<b>'.$currencies->format($final_price).' HT</b><br>('.$currencies->format($ttc_price).' TTC)';
			if ($order_product['payment']!='')echo '<p>'.$order_product['payment'].'</p>'; ?></td></tr><?php  $rows++;
			
if ($print) {	
	fputs($fp,strip_tags(tep_date_short((substr($order_product['date'],0,10))))."\t");
    fputs($fp,strip_tags($order_product['id'])."\t");
    fputs($fp,strip_tags($order_product['customer'])."\t");
    fputs($fp,strip_tags($order_product['email'])."\t");
    fputs($fp,strip_tags($order_product['telephone'])."\t");
 	fputs($fp,strip_tags($order_product['street'])."\t");
 	fputs($fp,strip_tags($order_product['postcode'])."\t");
 	fputs($fp,strip_tags($order_product['address'])."\t");
 	fputs($fp,strip_tags($order_product['product_id'])."\t");
 	fputs($fp,strip_tags($order_product['product_name'])."\t");
 	fputs($fp,strip_tags($fputs_options)."\t");
 	fputs($fp,strip_tags($order_product['quantity'])."\t");
 	fputs($fp,strip_tags($order_product['status'])."\t");
	fputs($fp,strip_tags($final_price)."\t");
	fputs($fp,strip_tags($ttc_price)."\t");	
 	fputs($fp,strip_tags($order_product['payment'])."\t");	
 	fputs($fp,"\n");}
						
			}
			}else{echo '<tr><td align="center" class="dataTableContent" colspan="8">'.TEXT_NO_PRODUCTS.'</td></tr>';}?>
			</table>
<?php  if ( ($listing_split->number_of_rows > 0) && ((PREV_NEXT_BAR_LOCATION == '2') || (PREV_NEXT_BAR_LOCATION == '3')) ) {
?>
<table border="0" width="100%" cellspacing="0" cellpadding="2">
  <tr>
    <td class="smallText"><?php echo $listing_split->display_count2(TEXT_DISPLAY_NUMBER_OF_PRODUCTS); ?></td>
    <td class="smallText" align="right"><?php echo TEXT_RESULT_PAGES . ' ' . $listing_split->display_links2(MAX_DISPLAY_PAGE_LINKS, tep_get_all_get_params(array('info', 'x', 'y'))); ?></td>
  </tr>
</table><?php
  }

?>
				  
	  </td></tr><tr>
        <td><table border="0" cellspacing="0" cellpadding="5">
          <tr>
            <td colspan="2"><?php echo tep_draw_separator('pixel_trans.png', '1', '10'); ?></td>
          </tr>
          <tr class="dataTableHeadingRow">
            <td align="right" class="dataTableHeadingContent"><?php echo NEW_CUSTOMERS; ?></td>
            <td class="dataTableHeadingContent"><?php echo $new_customers_count; ?></td>
          </tr>
          <tr class="dataTableRow" onMouseOver="this.className='dataTableRowOver';this.style.cursor='hand'" onMouseOut="this.className='dataTableRow'">
            <td align="right" class="dataTableContent"><?php echo QUANTITY_PRODUCTS; ?></td>
            <td class="dataTableContent"><?php echo $quantity; ?></td>
          </tr>
          <tr class="dataTableRow" onMouseOver="this.className='dataTableRowOver';this.style.cursor='hand'" onMouseOut="this.className='dataTableRow'">
            <td align="right" class="dataTableContent"><?php echo CUSTOMERS_BOUGHT; ?></td>
            <td class="dataTableContent"><?php echo $customers_count; ?></td>
          </tr>
          <tr class="dataTableRow" onMouseOver="this.className='dataTableRowOver';this.style.cursor='hand'" onMouseOut="this.className='dataTableRow'">
            <td align="right" class="dataTableContent"><?php echo NEW_CUSTOMERS_BOUGHT; ?></td>
            <td class="dataTableContent"><?php echo $new_customers_bought_count . ' (' . $new_customers_bought_percent . "%)"; ?></td>
          </tr>
          <tr class="dataTableHeadingRow">
            <td align="right" class="dataTableHeadingContent"><?php echo NUMBER_ORDER; ?></td>
            <td class="dataTableHeadingContent"><?php echo $count_orders; ?></td>
          </tr>
          <tr class="dataTableRow" onMouseOver="this.className='dataTableRowOver';this.style.cursor='hand'" onMouseOut="this.className='dataTableRow'">
            <td align="right" class="dataTableContent"><?php echo TOTAL_HT; ?></td>
            <td align ="right"  class="dataTableContent"><?php echo $currencies->format($total_ht); ?></td>
          </tr>		  
          <tr class="dataTableRow" onMouseOver="this.className='dataTableRowOver';this.style.cursor='hand'" onMouseOut="this.className='dataTableRow'">
            <td align="right" class="dataTableContent"><?php echo TOTAL_TTC; ?></td>
            <td align ="right" class="dataTableContent"><?php echo $currencies->format($total_ttc); ?></td>
          </tr>
          <tr class="dataTableRow" onMouseOver="this.className='dataTableRowOver';this.style.cursor='hand'" onMouseOut="this.className='dataTableRow'">
            <td align="right" class="dataTableContent"><?php echo TOTAL_TAX; ?></td>
            <td align ="right"  class="dataTableContent"><?php echo $currencies->format($total_taxe); ?></td>
          </tr>
          <tr class="dataTableRow" onMouseOver="this.className='dataTableRowOver';this.style.cursor='hand'" onMouseOut="this.className='dataTableRow'">
            <td align="right" class="dataTableContent"><?php echo BASKET_TTC; ?></td>
            <td align ="right"  class="dataTableContent"><?php if ($count_orders > 0) {$tot_basket= (floor(($total_ttc/$count_orders * 100) + 0.5)) / 100;echo $currencies->format($tot_basket);} ?></td>
          </tr>
          <tr class="dataTableHeadingRow">
            <td align="right" class="dataTableHeadingContent"><?php echo BASKET_HT; ?></td>
            <td align ="right"  class="dataTableHeadingContent"><?php if ($count_orders > 0) {$tot_basket_HT= (floor(($total_ht/$count_orders * 100) + 0.5)) / 100;echo $currencies->format($tot_basket_HT);} 
if ($print) {
 	fputs($fp2,ORDER_CSV_NEW_CUSTOMERS."\t");
 	fputs($fp2,ORDER_CSV_QUANTITY_PRODUCTS."\t");
 	fputs($fp2,ORDER_CSV_CUSTOMERS_BOUGHT."\t");	
 	fputs($fp2,ORDER_CSV_NEW_CUSTOMERS_BOUGHT."\t");		
 	fputs($fp2,ORDER_CSV_NUMBER_ORDER."\t");	
 	fputs($fp2,ORDER_CSV_TOTAL_HT."\t");
 	fputs($fp2,ORDER_CSV_TOTAL_TTC."\t");		
 	fputs($fp2,ORDER_CSV_TOTAL_TAX."\t");	

 	fputs($fp2,ORDER_CSV_BASKET_TTC."\t");
 	fputs($fp2,ORDER_CSV_BASKET_HT."\t");
 	fputs($fp2,"\n");

    fputs($fp2,strip_tags($new_customers_count)."\t");
 	fputs($fp2,strip_tags($quantity)."\t");
 	fputs($fp2,strip_tags($customers_count)."\t");		
 	fputs($fp2,strip_tags($new_customers_bought_count . ' (' . $new_customers_bought_percent . "%)")."\t");
 	fputs($fp2,strip_tags($count_orders)."\t");
 	fputs($fp2,strip_tags($total_ht)."\t");

 	fputs($fp2,strip_tags($total_ttc)."\t");
 	fputs($fp2,strip_tags($total_taxe)."\t");

	fputs($fp2,strip_tags($tot_basket)."\t");
	fputs($fp2,strip_tags($tot_basket_HT)."\t");	
 	fputs($fp2,"\n");		
	     fclose($fp);fclose($fp2);}
			?></td>
          </tr>
        </table></td>
      </tr>
    </table></td>
<!-- body_text_eof //-->
  </tr>
</table>
<!-- body_eof //-->

<!-- footer //-->
<?php require( THEME . 'html/footer.php'); ?>
<!-- footer_eof //-->

<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>