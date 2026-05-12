<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2010 osCommerce

  Released under the GNU General Public License


  IMPORTANT NOTES ABOUT THIS FILE:

  This file is called whenever database problems occur, both for connection and sql errors.
  It can report these errors silently by email while displaying a customer friendly page.
  It supports debug override to show full error information for trusted ip addresses.
  The exact behaviour is customizable through the settings in

      Admin -> Configuration Settings -> Database Error Mode

  When editing the functionality of this file be aware the database dependent data is most likely unavailable.
  Any database error will stop further PHP processing, so in the best scenario only partial data is available.
  Defines, classes, functions or even variables created by osCommerce might not in scope on this specific page.

  Another important thing to realize is the HTML output of this file might end up anywhere on a page that calls
  the database and encounters an error. This means it is likely to break a pagelayout if you make it a 'full blown'
  html page. Keep it small. Or build an onLoad CSS/JavaScript modal-type 'popup' that is cross-browser compatible.

  Also note this file makes use of the superglobal $_SERVER

*/

// check how far down application_top.php we have come
  $included = preg_replace("/\/.*\//", "", get_included_files());

  if (!in_array('header.php', $included)) {
    $html_output = true;


 // certain functionality is unavailable so we provide it
    if (!function_exists('tep_validate_email')) {
	   function tep_validate_email($email) {
	    $email = trim($email);

	    if ( strlen($email) > 255 ) {
	      $valid_address = false;
	    } elseif ( function_exists('filter_var') && defined('FILTER_VALIDATE_EMAIL') ) {
	     $valid_address = (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
	    } else {
	      if ( substr_count( $email, '@' ) > 1 ) {
	        $valid_address = false;
	      }

	      if ( preg_match("/[a-z0-9!#$%&'*+\/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+\/=?^_`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?/i", $email) ) {
	        $valid_address = true;
	      } else {
	        $valid_address = false;
	      }
	    }

	    if ($valid_address && ENTRY_EMAIL_ADDRESS_CHECK == 'true') {
	      $domain = explode('@', $email);

	      if ( !checkdnsrr($domain[1], "MX") && !checkdnsrr($domain[1], "A") ) {
	        $valid_address = false;
	      }
	    }

	    return $valid_address;
	  }
   }


   if (!function_exists('tep_get_ip_address')) {

    function tep_get_ip_address() {

      $ip_address = null;
      $ip_addresses = array();

      if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        foreach ( array_reverse(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])) as $x_ip ) {
          $x_ip = trim($x_ip);

          if (tep_validate_ip_address($x_ip)) {
            $ip_addresses[] = $x_ip;
          }
        }
      }

      if (isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip_addresses[] = $_SERVER['HTTP_CLIENT_IP'];
      }

      if (isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']) && !empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
        $ip_addresses[] = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
      }

      if (isset($_SERVER['HTTP_PROXY_USER']) && !empty($_SERVER['HTTP_PROXY_USER'])) {
        $ip_addresses[] = $_SERVER['HTTP_PROXY_USER'];
      }

      $ip_addresses[] = $_SERVER['REMOTE_ADDR'];

      foreach ( $ip_addresses as $ip ) {
        if (!empty($ip) && tep_validate_ip_address($ip)) {
          $ip_address = $ip;
          break;
        }
      }

      return $ip_address;
    }
   }
 }

 // wrapper for debug_backtrace()
 function tep_debug_backtrace($prefix = false, $postfix = false, $show_args = true) {
  $output = '';
      if ($prefix) $output = $prefix;
        $output .= "<br>" . ' -------------------' . "<br>";
        $output .= '  Debug Backtrace:'. "<br>";
        $output .= ' -------------------' . "<br>";
        $i = -1;
        foreach(debug_backtrace() as $trace) {
         $i++;
          if ($i == 0) { continue; } // do not include myself
           $output .= '  ' . '[' . $i . '] ';
            if(isset($trace['file'])) {
             $output .= basename($trace['file']) . ':' . $trace['line'];
            }else{
             $output .= '[PHP callback]';
            }

            $output .= ' -- ';

            if(isset($trace['class'])) $output .= $trace['class'] . $trace['type'];

            $output .= $trace['function'];

            if(isset($trace['args']) && sizeof($trace['args']) > 0) {
            	$output .= '(' . ( ($show_args) ? implode(', ', $trace['args']) : '...') . ')';
            }else{
            	$output .= '()';
            }

            $output .= "<br>";
        }
     if ($postfix) $output .= $postfix;
   return $output;
 }

// load Database Error Mode configuration from cache --> Lo comento porque yo uso el archivo de cache/cahcefile.inc.php Creo que como este archivo no este, eto no furula.
// $cache_file = 'includes/logs/cfg_parameters.cache';

  if (file_exists($cache_file)) {
    $dbem = unserialize(join('', file($cache_file)));
   }else{
    $dbem = array('DB_ERROR_MODE' => 'friendly');
  }

  switch (DB_ERROR_MODE) {
  	case 'friendly':
  		$mail_report = false;
  		$show_debug = false;
  	break;

  	case 'friendly_with_silent_reporting':
  		$mail_report = true;
  		$show_debug = false;

  	  // parse the email recipients
  		$recipients = array();
  		$raw_recipients = explode('|', DB_ERROR_EMAIL_ADDRESS);

  		if (!empty($raw_recipients[0])) {
  		 foreach ($raw_recipients as $email) {
  		   $email = trim($email);
  		     if ( tep_validate_email($email) ) {
  		 	  $recipients[] = $email;
  		     }
  		 }
  		  if (sizeof($recipients) < 1) {
  		   	$mail_report = false; // admin input is botched, no valid address(es)
  		  }

  		}else{
  	      $mail_report = false;
  		}

  	break;

  	case 'debug':
  		$mail_report = false;
  		$show_debug = true;
  	break;
  	default:
  	    $mail_report = false;
  	    $show_debug = false;
  }

	$ipDebug=DB_ERROR_DEFAULT_DEBUG_IPS;

 if (!empty($ipDebug)){

  // parse the ip addresses that receive debug mode by default
  	 $default_debug = array();
  	 $raw_debug = explode('|', DB_ERROR_DEFAULT_DEBUG_IPS);

  	if (!empty($raw_debug[0])) {
  	   foreach ($raw_debug as $ip) {
  		 $default_debug[] = trim($ip);
  		}
  		 if (sizeof($default_debug) < 1) {
  		    $auto_debug = false;
  		 }else{
  		 	$remote_ip = tep_get_ip_address();
  		 	if (in_array($remote_ip, $default_debug)) {
  		 	  $auto_debug = true;
  		 	  $show_debug = true;
  		 	}
  		 }
  	  }
  }

    $report_data = ' Hora:' . "\t" . date("d.m.Y H:i:s") . "<br><br>" .
                   ' MySQL Error:' . "\t" . $errno . ' - ' . $error . "<br>" .
  	               ' MySQL Query:' . "\t" . $query . "<br><br>" .
  	               ' IP Remota:' . "\t" . $remote_ip . "<br>" .
  	               ' User-Agent:' . "\t" . $_SERVER['HTTP_USER_AGENT'] . "<br><br>" .
  	               ' Dominio:' . "\t" . $_SERVER['HTTP_HOST'] . "<br>" .
  	               ' Server IP:' . "\t" . $_SERVER['SERVER_ADDR'] . "<br>" .
  	               ' Server Puerto:' . "\t" . $_SERVER['SERVER_PORT'] . "<br>" .
  	               ' URL:' . "\t" . $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] . "<br>" .
  	               ' Fichero:' . "\t" . $_SERVER['SCRIPT_NAME'] . "<br>" .
                   ' Referido:' . "\t" . $_SERVER['HTTP_REFERER'] . "<br>" .
                   tep_debug_backtrace();

    $time_limit=DB_ERROR_EMAIL_REPORTING_LIMIT;

   // set the reporting limit from cache or force default
  	$reporting_limit = !empty($time_limit) ? DB_ERROR_EMAIL_REPORTING_LIMIT : '20';

   // report database errors on file level (otherwise the hash will be different each time)
  	$error_cache_file = $_SERVER['DOCUMENT_ROOT'] . '/includes/logs/err_' . md5($_SERVER['SCRIPT_NAME']) . '.cache';

  	   if (file_exists($error_cache_file)) {
           $timediff = (time() - filemtime($error_cache_file));
            if ($timediff > $reporting_limit) {

            // reporting limit has passed for this specific error, rewrite and mark mailout
              if ($f = fopen($error_cache_file, 'w+')) {
           	     fwrite ($f, $report_data, strlen($report_data));
                 fclose($f);
              }

              $mail_out = true;
            }
  	    }else{

  	   // initial write of the error
         if ($f = fopen($error_cache_file, 'w+')) {
           fwrite ($f, $report_data, strlen($report_data));
           fclose($f);
         }

         $mail_out = true;
       }

       $Subject = TITLE . ' - [ERROR BD] Archivo: ' . $_SERVER['SCRIPT_NAME'];

         // process mail
           if ( ($mail_out === true) && (DB_ERROR_MODE != 'debug') ) {
            foreach ($recipients as $email_address) {
            	tep_mail('', $email_address, $Subject, $report_data, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
            }
           }

  if ($html_output == true) {

  // set the header response
   @header('HTTP/1.1 503 Service Unavailable');
?>
<html>
 <head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <title><?php echo TITLE;?> - Error de BD</title>
  <base href="<?php echo HTTP_SERVER . DIR_WS_HTTP_CATALOG; ?>">
  <link rel="stylesheet" type="text/css" href="stylesheet.css">
<?php
  }
?>
<style type="text/css">
	#centrar {
		width:800px;
		height: 600px;
		position: absolute;
		top: 50%;
		left: 50%;
		margin: -300px auto auto -400px;
		display: block;
		text-align: center;
	}
	#centrar IMG{
		min-width: 200px;
		min-height: 50px;
		display: block;
		width: auto;
		height: auto;
		margin: 20px auto auto auto;
	}
	.vlvr-404{
		position: absolute;
		top:394px;
		left:164px;
		width:82px;
		height:16px;
		background-color: transparent;
	}
	#fltr{
		position: absolute;
		display: block;
		top:413px;
		left:164px;
		width:460px;
		height:33px;
	}
	#srch-crto input{
		border: none;
		background: transparent;
		position: absolute;
		height: 16px;
		padding: 8px 0px 8px 0px;
		width: 262px;
		left: 42px;
		top: 0px;
		outline: none;
		font-family: Tahoma, Arial;
		color: #aeaeae;
		font-size: 13px;
	}
	#fltr-srch {
		background-color: transparent;
		display: block;
		position: absolute;
		top: 0px;
		right: 0px;
		width: 130px;
		height: 30px;
		text-indent: -9999px;
		border: none;
		cursor: pointer;
	}

  .code-box-n,
  .code-box {
    font-family: 'Lucida Console', 'Bitstream Vera Sans Mono', 'Courier New', Monaco, Courier, monospace;
    white-space: pre;
    width: 100%;
    margin: 1em 0;
    border: 1px dashed #aaa8a8;
    padding: 0.5em 0 0.3em 0.5em;
    font-size: 90%;
    color: #999;
    overflow: auto;
  }

  .code-box-n {
    width: 33em;
    padding-left: 0.3em;
    border: 1px solid;
    border-color: #666 #999;
    background-color: #fff;
  }

  .code-box-n a { font-weight: normal; }
  .code-box-n a:focus,
  .code-box-n a:hover {
    border-bottom: 1px solid #c00;
  }
</style>

<?php  if ($html_output == true) echo '</head>' . "<br>" . '<body id="404">'; ?>

<?php if ($show_debug == false){ ?>
	<div id="centrar">
	<p><a href="/">
		<img src="theme/web/logo-trans.png">
	</a></p>
	<p style="font-family: Arial">
    Ha ocurrido un error en la conexión con la base de datos.<br /><br />
    Por favor, intente volver a cargar la página de nuevo, o pruebe dentro de unos minutos.<br /><br />
    Disculpen las molestias ocasionadas.
    </p>
	</div>
<?php }else{ ?>
	<h1 class="pageHeading">Herramienta Debug Errores MySQL:</h1>
	<div class="code-box"><?php echo $report_data; ?></div>
<?php } ?>
</div>

<?php  if ($html_output == true) echo '</body>'; ?>
