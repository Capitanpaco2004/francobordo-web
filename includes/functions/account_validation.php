<?php
/*
  $Id: account_validation.php,v 2.1a 2004/08/10 20:19:27 chaicka Exp $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2006 osCommerce

  Released under the GNU General Public License
*/

////
// This function validates the created profile
// search engine spiders will not know what to do here, so you will not have automatic profiles from them
  function gen_reg_key(){
	$key = '';
	$chars = array('a','b','c','d','e','f','g','h','j','k','l','m','n','p','q','r','s','t','u','v','w','x','y','z', '1', '2', '3', '4', '5', '6', '7', '8', '9', '@', '#', '?', '+', '=');
	$count = count($chars) - 1;
	
	srand((double)microtime()*1000000);
	for($i = 0; $i < ENTRY_VALIDATION_LENGTH; $i++){
	  $key .= $chars[rand(0, $count)];
	}
	$key = strtoupper($key);
    return($key);
  }

////
// The HTML image wrapper function
  function tep_image_captcha($src, $parameters = '') {
    if ( (empty($src) || ($src == DIR_WS_IMAGES)) && (IMAGE_REQUIRED == 'false') ) {
      return false;
    }

    $image = '<img src="' . tep_output_string($src) . '" border="0" alt=""';
		
		if (tep_not_null($parameters)) $image .= ' ' . $parameters;

    $image .= '>';

    return $image;
  }
	
?>