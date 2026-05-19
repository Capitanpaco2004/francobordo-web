<?php
global $HTTP_POST_VARS,$HTTP_ENV_VARS,$HTTP_GET_VARS,$HTTP_SESSION_VARS;

if ($HTTP_GET_VARS) {
  if (!isset($_GET))
  	$_GET = $HTTP_GET_VARS;
  foreach( $HTTP_GET_VARS as $key => $val ) {
    $$key = $val;
  }
}

if ($HTTP_POST_VARS) {
  if (!isset($_POST))
  	$_POST = $HTTP_POST_VARS;
  foreach( $HTTP_POST_VARS as $key => $val ) {
    $$key = $val;
  }
}
$_REQUEST = array_merge($_GET,$_POST);

if ($HTTP_SESSION_VARS) {
  #print "SESSION_VARS";
  if (!is_array($_SESSION))
	  $_SESSION = array();
  foreach( $HTTP_SESSION_VARS as $key => $val ) {
    $_SESSION[$key] = $val;
  }
}
?>
