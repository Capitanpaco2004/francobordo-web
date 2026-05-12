<?php
/*
  $Id: faq.php v.0.1 26.05.2002 
  povered by Adgrafics-Ukraine http://adgrafics.net 
  victor@zolochevsky.com

  The Exchange Project - Community Made Shopping!
  http://www.theexchangeproject.org 

  Copyright (c) 2000,2001 The Exchange Project

  Released under the GNU General Public License
*/

$faq_max_char = "1024";// Amount of symbols in textarea
$confirm=img("icons/confirm_red.gif",CONFIRM_FAQ);
$warning=img("icons/warning.gif",WARNING_FAQ);

tep_db_connect($server = DB_SERVER, $username = DB_SERVER_USERNAME, $password = DB_SERVER_PASSWORD) or die("Unable to connect to SQL server");

function href ($link, $text) {
	return "<a href=\"$link\">$text</a>";
}

function img ($src, $alt) {
$src_c="" . DIR_WS_IMAGES . "$src";
	return "<img border=0 src=\"$src_c\" alt=\"$alt\">";
}

function textbox ($type, $name, $size=0, $maxlength=0, $value="", $readonly=0) {
	$result ="<input type=\"$type\" ";
	$result.="name=\"$name\" ";
	$result.="size=\"$size\" ";
	$result.="maxlength=\"$maxlength\" ";
	if ($value) {$result.="value=\"$value\"";}
	if ($readonly==1) {$result.=" readonly";} else {$result.="";}
	$result.=">";
return $result;
}

function form ($action, $hidden, $validate="") {
	$result="<form action=\"$action\" method=post ";
	if ($validate) {$result.="onsubmit=\"return $validate\"";}
	$result.=">\n";
	if ($hidden) {
		foreach( $hidden as $key => $val ) {$result.="<input type=hidden name=\"$key\" value=\"$val\">\n";}
	}
return $result;
}

function faq_toc () {
	global $PHP_SELF;
	static $old_faq_id;
	if ($old_faq_id) {
		$exclude=explode("&", $old_faq_id);
		foreach( $exclude as $dummy => $old_id ) {
			if ($old_id) {$query.="faq_id != $old_id AND ";unset($old_id);}
		}
	}
	$result=tep_db_fetch_array(tep_db_db_query(DB_DATABASE, "SELECT faq_id, question FROM " . TABLE_FAQ . " WHERE $query visible='1' ORDER BY v_order"));
	if ($result[faq_id]) {$old_faq_id.="$result[faq_id]&";	$result[toc]=href("$PHP_SELF#$result[faq_id]", $result[question]);}
return $result;
}

function read_faq () {
	static $old_faq_id;
	if ($old_faq_id) {
		$exclude=explode("&", $old_faq_id);
		foreach( $exclude as $dummy => $old_id ) {
			if ($old_id) {$query.="faq_id != $old_id AND ";unset($old_id);}
		}
	}
	$result=tep_db_fetch_array(tep_db_db_query(DB_DATABASE, "SELECT faq_id, question, answer FROM " . TABLE_FAQ . " WHERE $query visible='1' ORDER BY v_order"));
	if ($result[faq_id]) {
		$old_faq_id.="$result[faq_id]&";
		$result[faq]="<b><a name=$result[faq_id]>$result[question]</a></b><br>$result[answer]";
	}
return $result;
}

function browse_faq () {
	$query="SELECT *, DATE_FORMAT(date, '%d.%m.%y') AS d FROM " . TABLE_FAQ . " ORDER BY v_order";
	$daftar = tep_db_db_query(DB_DATABASE, $query) or die("browse_faq: ".tep_db_error());$c=0;
	while ($buffer = tep_db_fetch_array($daftar)) {$result[$c]=$buffer;$c++;}
return $result;
}

function add_faq ($data) {
	$query ="INSERT INTO " . TABLE_FAQ . " VALUES(null, '$data[visible]', '$data[v_order]', '$data[question]', '$data[answer]', NOW(''))";
	tep_db_db_query(DB_DATABASE, $query) or die ("add_faq: ".tep_db_error());
}

function update_faq ($data) {
	tep_db_db_query(DB_DATABASE, "UPDATE " . TABLE_FAQ . " SET question='$data[question]', answer='$data[answer]', visible='$data[visible]', v_order=$data[v_order] WHERE faq_id=$data[faq_id]") or die ("update_faq: ".tep_db_error());
}

function read_data ($faq_id) {
	$result=tep_db_fetch_array(tep_db_db_query(DB_DATABASE, "SELECT * FROM " . TABLE_FAQ . " WHERE faq_id=$faq_id"));
return $result;
}

function delete_faq ($faq_id) {
	tep_db_db_query(DB_DATABASE, "DELETE FROM " . TABLE_FAQ . " WHERE faq_id=$faq_id");
}

function error_message($error) {
	global $warning;
	switch ($error) {
		case "20":return "<tr class=messageStackError><td>$warning ." . ERROR_20_FAQ . "</td></tr>";break;
		case "80":return "<tr class=messageStackError><td>$warning " . ERROR_80_FAQ . "</td></tr>";break;
		default:return $error;
	}
}

  function image_submit($image, $alt) {
    $image_submit = '<input type="image" src="' . DIR_WS_IMAGES . $image . '" border="0" title="' . htmlspecialchars($alt) . '">';
    return $image_submit;
  }
?>