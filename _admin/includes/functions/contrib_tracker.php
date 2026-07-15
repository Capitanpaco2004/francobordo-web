<?php
/*
  $Id: contrib_tracker.php,v1.7.12 2008/11/08 11:25:32 stevedallas Exp $

  07 Nov 2008 GCH 1.7.12 contrib_check now returns support topic ID as $contrib_support_topic

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
_________________________________________________________________

Contribution Tracker Functions for osC Admin
By Admin of www.silvermoon-jewelry.com
Based on:
		Admin_notes: Original Code By: Robert Hellemans of www.RuddlesMills.com
		RSS News for OSC
These are LIVE SHOPS - So please, no TEST accounts etc...
We will report you to your ISP if you abuse our websites!
*/

  function vWritePageToFile($sHTMLpage, $sTxtfile ) {
		$sh=curl_init($sHTMLpage);
		$hFile=FOpen($sTxtfile, 'w');
		curl_setopt($sh, CURLOPT_FILE, $hFile);
		curl_setopt($sh, CURLOPT_HEADER, 0);
		curl_exec($sh);
		$aCURLinfo = curl_getInfo($sh);
		// sin curl_close(): deprecado en PHP 8.5 (no-op desde 8.0)
		FClose($hFile);
	}

  function tep_set_contrib_query_status($contr_id, $status,$contr_last_modified) {
// IF THERE IS NO LAST MODIFIED DATE USE NOW ELSE USE THE $contr_last_modified (LAST TIME THE CONTRIB APPEARED IN THE RSS FEED)
    if ($contr_last_modified == NULL){
      $last_update_date= date('Y-m-d H:i:s',time());
    }else{
      $last_update_date= $contr_last_modified;
    }

    if ($status == '0') {
      return tep_db_query("update " . TABLE_CONTRIB_TRACKER . " set status = '0' where contr_id = '" . $contr_id . "'");
    } elseif ($status == '1') {
      return tep_db_query("update " . TABLE_CONTRIB_TRACKER . " set status = '1',last_update='" .$contr_last_modified. "' where contr_id = '" . $contr_id . "'");
    } elseif ($status == '2') {
      return tep_db_query("update " . TABLE_CONTRIB_TRACKER . " set status = '3', last_update='" .$contr_last_modified. "' where contr_id = '" . $contr_id . "'");
    } else {
      return -1;
    }
  }

function curl_get_osc_contents($OSCURL) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_URL, $OSCURL) ;
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$data = curl_exec($ch);
	// sin curl_close(): deprecado en PHP 8.5 (no-op desde 8.0)
	return $data ;
}

function contrib_check($contrib_id) {
  global $contrib_name, $contribsURL, $contrib_ischecked, $last_osc_update, $contrib_support_topic /*, $updates, $contrib_date*/ ; 
  if(!empty($contrib_id)){
    $contrib_ischecked= 1;
    $var = curl_get_osc_contents($contribsURL . (int)$contrib_id);
    preg_match_all('/[0-9]+ [A-Za-z]{3} *[0-9]{4}/', $var, $matches) ;
    preg_match('/<h2>.*<\/a>/i', $var, $matches2) ;
 
    $contrib_name = str_replace('<br />', ' ', strip_tags($matches2[0], "<br>")) ;

    preg_match('/\Qshowtopic=\E([0-9]*)/', $var, $matches3);
    if ($matches3[1]) {
      $contrib_support_topic = $matches3[1];
    }
	
    if ($matches[0]){
      $format = '%Y-%m-%d %H:%M:%S';
      $last_osc_update=strtotime($matches[0][0]);
      $last_osc_update = date('Y-m-d H:i:s', $last_osc_update);
    }
  }
}

function old_to_new_url($URL){
  global $messageStack;
  //convert old links to new ones
  $findme="www.oscommerce.com/community/contributions";
  $pos = strpos($URL, $findme);
  if ($pos){
    $new_URL = str_replace("http://www.oscommerce.com/community/contributions,", "http://addons.oscommerce.com/info/", $URL);
    $messageStack->add_session(MESSAGE_LINKCHANGE_SUCCESS, 'success');
  }else{
    $new_URL=$URL;
  }
  return $new_URL;
}

function get_contrib_id($URL, $contrib_id){
  //get the contrib id from the url
  
  //only get id from oscommerce urls 
  
  $findme="http://addons.oscommerce.com/info";
  $pos = strpos($URL, $findme);

  if ($pos && empty($contrib_id) && !empty($URL)){
    $contrib_id = substr($URL , strrpos($URL , '/') +1);
    if (!is_numeric($contrib_id)){
      $contrib_id='';
    }
  }
  return $contrib_id;
}

?>