<?

  function tep_get_supports_status_name($support_status_id, $language_id = '') {
    global $languages_id;

    if (!$language_id) $language_id = $languages_id;
    $orders_status_query = tep_db_query("select support_status_name from " . TABLE_SUPPORT_STATUS . " where support_status_id = '" . $support_status_id . "' and language_id = '" . $language_id . "'");
    $orders_status = tep_db_fetch_array($orders_status_query);

    return $orders_status['orders_status_name'];
  }

  function tep_get_support_status() {
    global $languages_id;

    $orders_status_array = array();
    $orders_status_query = tep_db_query("select support_status_id, support_status_name from " . TABLE_SUPPORT_STATUS . " where language_id = '" . $languages_id . "' order by support_status_id");
    while ($orders_status = tep_db_fetch_array($orders_status_query)) {
      $orders_status_array[] = array('id' => $orders_status['support_status_id'],
                                     'text' => $orders_status['support_status_name']
                                    );
    }

    return $orders_status_array;
  }
  
    function tep_get_support_status_name($support_status_id, $language_id = '') {
    global $languages_id;

    if ($support_status_id < 1) return TEXT_DEFAULT;

    if (!is_numeric($language_id)) $language_id = $languages_id;

    $status_query = tep_db_query("select support_status_name from " . TABLE_SUPPORT_STATUS . " where support_status_id = '" . $support_status_id . "' and language_id = '" . $language_id . "'");
    $status = tep_db_fetch_array($status_query);

    return $status['support_status_name'];
  }


    function tep_get_support_priority() {
    global $languages_id;

    $orders_status_array = array();
    $orders_status_query = tep_db_query("select support_priority_id, support_priority_name from " . TABLE_SUPPORT_STATUS . " where language_id = '" . $languages_id . "' order by support_priority_id");
    while ($orders_status = tep_db_fetch_array($orders_status_query)) {
      $orders_status_array[] = array('id' => $orders_status['support_priority_id'],
                                     'text' => $orders_status['support_priority_name']
                                    );
    }

    return $orders_status_array;
  }

    function tep_get_support_priority_name($support_priority_id, $language_id = '') {
    global $languages_id;

    if ($support_priority_id < 1) return TEXT_DEFAULT;

    if (!is_numeric($language_id)) $language_id = $languages_id;

    $status_query = tep_db_query("select support_priority_name from " . TABLE_SUPPORT_PRIORITY . " where support_priority_id = '" . $support_priority_id . "' and language_id = '" . $language_id . "'");
    $status = tep_db_fetch_array($status_query);

    return $status['support_priority_name'];
  }


     function tep_get_support_department() {
    global $languages_id;

    $orders_status_array = array();
    $orders_status_query = tep_db_query("select support_department_id, support_department_name from " . TABLE_SUPPORT_DEPARTMENT . " where language_id = '" . $languages_id . "' order by support_department_id");
    while ($orders_status = tep_db_fetch_array($orders_status_query)) {
      $orders_status_array[] = array('id' => $orders_status['support_department_id'],
                                     'text' => $orders_status['support_department_name']
                                    );
    }

    return $orders_status_array;
  }

    function tep_get_support_department_name($support_department_id, $language_id = '') {
    global $languages_id;

    if ($support_department_id < 1) return TEXT_DEFAULT;

    if (!is_numeric($language_id)) $language_id = $languages_id;

    $status_query = tep_db_query("select support_department_name from " . TABLE_SUPPORT_DEPARTMENT . " where support_department_id = '" . $support_department_id . "' and language_id = '" . $language_id . "'");
    $status = tep_db_fetch_array($status_query);

    return $status['support_department_name'];
  }
  
       function tep_get_support_assign() {
    global $languages_id;

    $orders_status_array = array();
    $orders_status_query = tep_db_query("select support_assign_id, support_assign_name from " . TABLE_SUPPORT_ADMINS . " where language_id = '" . $languages_id . "' order by support_assign_id");
    while ($orders_status = tep_db_fetch_array($orders_status_query)) {
      $orders_status_array[] = array('id' => $orders_status['support_assign_id'],
                                     'text' => $orders_status['support_assign_name']
                                    );
    }

    return $orders_status_array;
  }

    function tep_get_support_assign_name($support_assign_id, $language_id = '') {
    global $languages_id;

    if ($support_assign_id < 1) return TEXT_DEFAULT;

    if (!is_numeric($language_id)) $language_id = $languages_id;

    $status_query = tep_db_query("select support_assign_name from " . TABLE_SUPPORT_ADMINS . " where support_assign_id = '" . $support_assign_id . "' and language_id = '" . $language_id . "'");
    $status = tep_db_fetch_array($status_query);

    return $status['support_assign_name'];
  }

 function tep_get_support_email() {
    global $languages_id;

    $orders_status_array = array();
    $orders_status_query = tep_db_query("select support_assign_id, support_assign_email from " . TABLE_SUPPORT_ADMINS . " where language_id = '" . $languages_id . "' order by support_assign_id");
    while ($orders_status = tep_db_fetch_array($orders_status_query)) {
      $orders_status_array[] = array('id' => $orders_status['support_assign_id'],
                                     'text' => $orders_status['support_assign_email']
                                    );
    }

    return $orders_status_array;
  }

    function tep_get_support_assign_email($support_assign_id, $language_id = '') {
    global $languages_id;

    if ($support_assign_id < 1) return TEXT_DEFAULT;

    if (!is_numeric($language_id)) $language_id = $languages_id;

    $status_query = tep_db_query("select support_assign_email from " . TABLE_SUPPORT_ADMINS . " where support_assign_id = '" . $support_assign_id . "' and language_id = '" . $language_id . "'");
    $status = tep_db_fetch_array($status_query);

    return $status['support_assign_email'];
  }

    function tep_remove_ticket($ticket_id) {


    tep_db_query("delete from " . TABLE_SUPPORT_TICKETS . " where ticket_id = '" . tep_db_input($ticket_id) . "'");
    tep_db_query("delete from " . TABLE_SUPPORT_TICKETS_HISTORY . " where ticket_id = '" . tep_db_input($ticket_id) . "'");
     }

// faq - support integration system

$faq_max_char = "1024";// Amount of symbols in textarea
$confirm=img("icons/confirm_red.gif", 'confirm' );
$warning=img("icons/warning.gif",'warning');

//mysql_connect($server = DB_SERVER, $username = DB_SERVER_USERNAME, $password = DB_SERVER_PASSWORD) or die("Unable to connect to SQL server");

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
//	$result=mysql_fetch_array(mysql_db_query(DB_DATABASE, "SELECT faq_id, question FROM " . TABLE_FAQ . " WHERE $query visible='1' ORDER BY v_order"));
	$result=tep_db_fetch_array(tep_db_query("SELECT faq_id, question FROM " . TABLE_FAQ . " WHERE $query visible='1' ORDER BY v_order"));
	if ($result['faq_id']) {$old_faq_id.="$result[faq_id]&";	$result['toc']=href(tep_href_link(FILENAME_FAQ, '', 'NONSSL') . "#$result[faq_id]", $result['question']);}
return $result;
}

function read_faq() {
	static $old_faq_id;
	if ($old_faq_id) {
		$exclude=explode("&", $old_faq_id);
		foreach( $exclude as $dummy => $old_id ) {
			if ($old_id) {$query.="faq_id != $old_id AND ";unset($old_id);}
		}
	}
	$result=tep_db_fetch_array(tep_db_query("SELECT faq_id, question, answer FROM " . TABLE_FAQ . " WHERE $query visible='1' ORDER BY v_order"));
	if ($result['faq_id']) {
		$old_faq_id.="$result[faq_id]&";
		$result['faq']="<b><a name=$result[faq_id]>$result[question]</a></b><br>$result[answer]";
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

if( !function_exists('error_message') )
{
	function error_message($error) {
		global $warning;
		switch ($error) {
			case "20":return "<tr class=messageStackError><td>$warning ." . ERROR_20_FAQ . "</td></tr>";break;
			case "80":return "<tr class=messageStackError><td>$warning " . ERROR_80_FAQ . "</td></tr>";break;
			default:return $error;
		}
	}
}
  function image_submit($image, $alt) {
    $image_submit = '<input type="image" src="' . DIR_WS_IMAGES . $image . '" border="0" title="' . htmlspecialchars($alt) . '">';
    return $image_submit;
  }
?>
