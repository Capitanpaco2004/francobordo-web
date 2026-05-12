<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2014 osCommerce

  Released under the GNU General Public License
*/
  // session.bug_compat_* eliminadas en PHP 5.4+

  if (STORE_SESSIONS == 'mysql') {

if ( SESSION_LIFETIME_CUSTOM<=0 ){
	$sess_life_fix_query 	= tep_db_query("update " . TABLE_CONFIGURATION . " set configuration_value='24' where configuration_key = 'SESSION_LIFETIME_CUSTOM'");
	$sess_life_calculation 	= 24 * 60;
} else {
	$sess_life_calculation 	= SESSION_LIFETIME_CUSTOM * 60;
}
$SESS_LIFE = $sess_life_calculation;


    function _sess_open($save_path, $session_name) {
      return true;
    }

    function _sess_close() {
      return true;
    }

    function _sess_read($key) {
      $value_query = tep_db_query("select value from " . TABLE_SESSIONS . " where sesskey = '" . tep_db_input($key) . "'");
      $value = tep_db_fetch_array($value_query);

      if (isset($value['value'])) {
        return $value['value'];
      }

      return '';
    }

	function _sess_write($key, $val) {
		  global $SESS_LIFE;
		  $expiry = time() + $SESS_LIFE;
		  $value = $val;

		  tep_db_query("INSERT into " . TABLE_SESSIONS . " VALUES ('" . tep_db_input($key) . "', '" . tep_db_input($expiry) . "', '" . tep_db_input($value) . "') ON DUPLICATE KEY UPDATE expiry=VALUES(expiry), value=VALUES(value)");

		  return true;
	}

    function _sess_destroy($key) {
      $result = tep_db_query("delete from " . TABLE_SESSIONS . " where sesskey = '" . tep_db_input($key) . "'");

      return $result !== false;
    }

    function _sess_gc($maxlifetime) {
      $result = tep_db_query("delete from " . TABLE_SESSIONS . " where expiry < '" . (time() - $maxlifetime) . "'");

      return $result !== false;
    }

    @session_set_save_handler('_sess_open', '_sess_close', '_sess_read', '_sess_write', '_sess_destroy', '_sess_gc');
  }



function tep_session_start() {
  global $_GET, $_POST, $_COOKIE;

  $sane_session_id = true;

  if (isset($_GET[tep_session_name()])) {
    if (preg_match('/^[a-zA-Z0-9,-]+$/', $_GET[tep_session_name()]) == false) {
      unset($_GET[tep_session_name()]);

      $sane_session_id = false;
    }
  } elseif (isset($_POST[tep_session_name()])) {
    if (preg_match('/^[a-zA-Z0-9,-]+$/', $_POST[tep_session_name()]) == false) {
      unset($_POST[tep_session_name()]);

      $sane_session_id = false;
    }
  } elseif (isset($_COOKIE[tep_session_name()])) {
    if (preg_match('/^[a-zA-Z0-9,-]+$/', $_COOKIE[tep_session_name()]) == false) {
      $session_data = session_get_cookie_params();

      setcookie(tep_session_name(), '', time()-42000, $session_data['path'], $session_data['domain']);

      $sane_session_id = false;
    }
  }

  if ($sane_session_id == false) {
    tep_redirect(tep_href_link(FILENAME_DEFAULT, '', 'NONSSL', false));
  }

  register_shutdown_function('session_write_close');

  return session_start();
}

function tep_session_register($variable) {
    if (!isset($GLOBALS[$variable])) {
      $GLOBALS[$variable] = null;
    }

    $_SESSION[$variable] =& $GLOBALS[$variable];

  return false;
}

  function tep_session_is_registered($variable) {
    if(isset($_SESSION[$variable])) {
      return true;
    } else {
      return false;
    }
  }

  function tep_session_unregister($variable) {
    unset($_SESSION[$variable]);
  } 

  function tep_session_id($sessid = '') {
    if (!empty($sessid)) {
      return session_id($sessid);
    } else {
      return session_id();
    }
  }

  function tep_session_name($name = '') {
    if (!empty($name)) {
      return session_name($name);
    } else {
      return session_name();
    }
  }

 // Register Globals MOD - http://www.magic-seo-url.com
  function tep_session_close() {
    foreach($_SESSION as $key => $value) {
      global $$key;
      $_SESSION[$key] = $$key;
    }

  }

  function tep_session_destroy() {
    if ( isset($_COOKIE[tep_session_name()]) ) {
      $session_data = session_get_cookie_params();

      setcookie(tep_session_name(), '', time()-42000, $session_data['path'], $session_data['domain']);
      unset($_COOKIE[tep_session_name()]);
    }

    return session_destroy();
  }

  function tep_session_save_path($path = '') {
	if (STORE_SESSIONS != 'mysql') { // added this line to turn off this checking if storing session info in db
    if (!empty($path)) {
			return session_save_path($path);
		} else {
			return session_save_path();
		}
	}
  } 
?>