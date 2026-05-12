<?
/*
  $Id: support_functions.php,v 1.3 2003/02/05 12:55:51 puddled Exp $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2002 Puddled Computer Services
  Contributed by Puddled Computer services
  http://www.puddled.co.uk

  Author David Howarth
  Email dave@puddled.co.uk

  Released under the GNU General Public License
*/

function cutstr ($string, $len) {
$i = $len;
while ($i < strlen($string)) {
if ($string[$i] == " ") {
$string = substr($string, 0, $i)."...";
return $string;
}
$i++;
}
return $string;
}


  function tep_draw_faq_field($name, $wrap, $width, $height, $text = '', $parameters = 'maxlength=128', $reinsert_value = true) {
    $field = '<textarea name="' . tep_parse_input_field_data($name, array('"' => '&quot;')) . '" wrap="' . tep_parse_input_field_data($wrap, array('"' => '&quot;')) . '" cols="' . tep_parse_input_field_data($width, array('"' => '&quot;')) . '" rows="' . tep_parse_input_field_data($height, array('"' => '&quot;')) . '"';

    if (tep_not_null($parameters)) $field .= ' ' . $parameters;

    $field .= '>';

    if ( (isset($GLOBALS[$name])) && ($reinsert_value == true) ) {
      $field .= $GLOBALS[$name];
    } elseif (tep_not_null($text)) {
      $field .= $text;
    }

    $field .= '</textarea>';

    return $field;
  }

  function href ($link, $text) {
	return "<a href=\"$link\">$text</a>";
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
//	$result=tep_db_fetch_array(tep_db_db_query(DB_DATABASE, "SELECT faq_id, question FROM " . TABLE_FAQ . " WHERE $query visible='1' ORDER BY v_order"));
	$result=tep_db_fetch_array(tep_db_query("SELECT faq_id, question FROM " . TABLE_FAQ . " WHERE $query visible='1' ORDER BY v_order"));
	if ($result[faq_id]) {$old_faq_id.="$result[faq_id]&";	$result[toc]=href(tep_href_link(FILENAME_FAQ, '', 'NONSSL') . "#$result[faq_id]", '<span style="color:#0000ff">' . $result[question] . '</span>');}
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
// $result=tep_db_fetch_array(tep_db_db_query(DB_DATABASE, "SELECT faq_id, question, answer FROM " . TABLE_FAQ . " WHERE $query visible='1' ORDER BY v_order"));
 $result=tep_db_fetch_array(tep_db_query("SELECT faq_id, question, answer FROM " . TABLE_FAQ . " WHERE $query visible='1' ORDER BY v_order"));
	if ($result[faq_id]) {
		$old_faq_id.="$result[faq_id]&";
		$result[faq]="<b><a name=$result[faq_id]>$result[question]</a></b><br>$result[answer]";
	}
return $result;
}

function browse_faq() {
	$query="SELECT *, DATE_FORMAT(date, '%d.%m.%y') AS d FROM " . TABLE_FAQ . " ORDER BY v_order";
	$daftar = tep_db_query($query);$c=0;
	while ($buffer = tep_db_fetch_array($daftar)) {$result[$c]=$buffer;$c++;}
return $result;
}

function add_faq($data) {
	$query ="INSERT INTO " . TABLE_FAQ . " VALUES(null, '$data[visible]', '$data[v_order]', '$data[question]', '$data[answer]', NOW(''))";
	tep_db_query($query);
}

function update_faq($data) {
	tep_db_query("UPDATE " . TABLE_FAQ . " SET question='$data[question]', answer='$data[answer]', visible='$data[visible]', v_order=$data[v_order] WHERE faq_id=$data[faq_id]") or die ("update_faq: ".tep_db_error());
}

function read_data($faq_id) {
	$result=tep_db_fetch_array(tep_db_query("SELECT * FROM " . TABLE_FAQ . " WHERE faq_id=$faq_id"));
return $result;
}

function delete_faq($faq_id) {
	tep_db_query("DELETE FROM " . TABLE_FAQ . " WHERE faq_id=$faq_id");
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
