<?php
/*
  $Id: clean_code.php,v 1.0 Dec 11, 2010 9:03:39 PM Kymation Exp $
  $Loc: catalog/includes/modules/

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2010 osCommerce

  Released under the GNU General Public License
*/


////
// Filters out unwanted HTML tags, sanitizes links, then evals code
//   Allows raw HTML with unsafe links to be inserted in database description fields
//   Call this function where the data is displayed
  function tep_sanitize_html( $html_from_database ) {
  	global $languages_id;

  	$html_from_database = html_entity_decode( addslashes( $html_from_database ) );
    $filter = "(<!doctype([^>]+)>)|(<[/]?html>)|(<head>(.*)?</head>)|(<[/]?body([^>]+)?>)";
    $clean_html = stripslashes( preg_replace( '{' . $filter . '}', '', $html_from_database ) ); //Remove illegal HTML tags

    $strings_array = tep_return_substrings ($clean_html, 'href="', '>'); //Get the links

    $replacement_array = array();
    if (is_array ($strings_array) ) { //This should always be an array, but test it anyway
      foreach ($strings_array as $strings) {
        $cPath_new = 0;
        $products_id = 0;
        $query_string = '';
        $parts_array = parse_url ($strings);
        $hostname = str_replace( 'http://' , '', HTTP_SERVER );
        if( !isset( $parts_array['host'] ) ) $parts_array['host'] = $hostname;

        if( !isset( $parts_array['query'] ) || $parts_array['query'] == '' ) {
          $pattern_matches = array(); // Define it now so it can be passed by reference without the function complaining
          switch( true ) {
            case preg_match( '{(.*)-c([0-9_]*)-p([0-9]+)\.htm"$}', $strings, $pattern_matches ):
              $cPath_new = $pattern_matches['2'];
              $products_id = $pattern_matches['3'];
              $parts_array['path'] = FILENAME_PRODUCT_INFO;
              break;

            case preg_match( '{(.*)-c([0-9_]*)\.htm"$}', $strings, $pattern_matches ):
              $cPath_new = $pattern_matches['2'];
              $parts_array['path'] = FILENAME_DEFAULT;
              break;

            case preg_match( '{(.*)-p([0-9]+)\.htm"$}', $strings, $pattern_matches ):
              $products_id = $pattern_matches['2'];
              $parts_array['path'] = FILENAME_PRODUCT_INFO;
              break;

            case preg_match( '{(.*)-c([0-9_]*)-p([0-9]+)\.html"$}', $strings, $pattern_matches ):
              $cPath_new = $pattern_matches['2'];
              $products_id = $pattern_matches['3'];
              $parts_array['path'] = FILENAME_PRODUCT_INFO;
              break;

            case preg_match( '{(.*)-c([0-9_]*)\.html"$}', $strings, $pattern_matches ):
              $cPath_new = $pattern_matches['2'];
              $parts_array['path'] = FILENAME_DEFAULT;
              break;

            case preg_match( '{(.*)-p([0-9]+)\.html"$}', $strings, $pattern_matches ):
              $products_id = $pattern_matches['2'];
              $parts_array['path'] = FILENAME_PRODUCT_INFO;
              break;
          } // switch
        }

        $filename = basename ($parts_array['path']);
        $queries_array = explode ('&', $parts_array['query']);

        if (is_array ($queries_array) ) { // Explode _should_ always return an array, but test it anyway
          reset ($queries_array);
          foreach ($queries_array as $query) {
            $query_set = explode ('=', $query);
            $key = $query_set[0];
            $value = $query_set[1];
            $value = trim( $value, '" ' ); // Remove the double quotes and any spaces

            if( $key == 'products_id' ) {
              $products_id = ( int )$value;
            }

            if( $key == 'cPath' ) {
              $cPath_new = $value;
            }

            if ( tep_not_null( $value )
                 && ( $key != tep_session_name() )
                 && ( $key != 'error' )
                 && ( $key != 'action' )
                 && ( $key != 'x' )
                 && ( $key != 'y' )
                 && ( strpos( $query_string, $key ) === false )
                 ) {
              $query_string .= $key . '=' . rawurlencode (stripslashes ($value) ) . '&';
            } // if ( (strlen
          } //  foreach ($queries_array
        } // if (is_array

        $query_string = trim( $query_string, '&' ); // Remove the final &

        if( !isset( $parts_array['host'] ) || $parts_array['host'] == '' || $parts_array['host'] == $hostname || $parts_array['host'] == 'www.' . $hostname ) {
        	switch( $filename ) {
        	  case FILENAME_PRODUCT_INFO:
        	    if( $products_id > 0 ) {
                $data_array = tep_get_link_data( $products_id );
        	    	if( $cPath_new == 0 )  $cPath_new = $data_array['cPath'];
                $query_string = 'cPath=' . $cPath_new . '&products_id=' . $products_id;

        	      $replacement_array[] = tep_href_link ($filename, $query_string, 'NONSSL', true, true, $data_array['categories_name'], $data_array['products_name'] ) . '"'; // Add the corrected link to the array
        	    } else {

        	      $replacement_array[] = $strings; // Leave it as it is
        	    }
        	    break;

        	  case FILENAME_DEFAULT:
        	  case '':
        	    if( $cPath_new != 0 ) {
        	    	$categories_array = explode( '_', $cPath_new );
        	    	$count_last = count( $categories_array ) - 1;
        	    	$categories_id = $categories_array[$count_last];
                $categories_name = tep_get_category_name( $categories_id, $languages_id );
                $query_string = 'cPath=' . $cPath_new;

        	      $replacement_array[] = tep_href_link ($filename, $query_string, 'NONSSL', true, true, $categories_name ) . '"'; // Add the corrected link to the array
        	    } else {

        	      $replacement_array[] = $strings; // Leave it as it is
        	    }
        	    break;

        	  default:
        	    $replacement_array[] = tep_href_link ($filename, $query_string) . '"'; // Add the corrected link to the array
        	    break;
        	}

        } else { // External link
          $replacement_array[] = $strings; // Leave it as it is
        }
      } // foreach ($strings_array
    } // if (is_array

    reset ($strings_array);
    reset ($replacement_array);
// print '<pre>';
// print_r ($strings_array);
// print '</pre>';
// print '<pre>';
// print_r ($replacement_array);
// print '</pre>';

    $replaced_html = tep_str_replace ($strings_array, $replacement_array, $clean_html);

    eval ("\$evaluated_html = '$replaced_html';");
    $evaluated_html = str_replace( '\\r\\n', '', $evaluated_html );
    $evaluated_html = stripslashes( $evaluated_html );
    $evaluated_html = str_replace( "\r\n", '', $evaluated_html );
    $evaluated_html = stripslashes( $evaluated_html );

    return ($evaluated_html);
  } // function tep_sanitize_html


////
// Replacement for the buggy PHP str_replace()
//   This one doesn't replace multiple times if the replacement contains the search string
//   Usage is the same as str_replace()
//   Modified from the PHP manual contributions
  function tep_str_replace ($search, $replace, $subject){
    if (!is_array ($search) ) {
      $search = array ($search);
    }

    if (!is_array ($replace) ) {
      $replace = array ($replace);
    }

    // Add unique strings to the front and back of each replace string
    // Use something that will never occur in the actual string
    $replace_prefix = 'aeiouxyz';
    $replace_suffix = 'zxyuoiea';

    foreach ($search as $key => $value) {
      $subject = str_replace ($value, $replace_prefix . $key . $replace_suffix, $subject);
    }

    foreach ($search as $key => $value) {
      $subject = str_replace ($replace_prefix . $key . $replace_suffix, $replace[$key], $subject);
    }

    return ($subject);
  } // function stru_replace


////
// Function parses a string and returns an array of strings enclosed by specified delimiters
//   Function adapted from the PHP manual
//   $text is the string to parse
//   $sopener is the starting delimiter
//   $scloser is the ending delimiter
  function tep_return_substrings ($text, $sopener, $scloser) {
    $result = array();

    $noresult = substr_count ($text, $sopener);
    $ncresult = substr_count ($text, $scloser);

    if ($noresult < $ncresult) {
      $nresult = $noresult;
    } else {
      $nresult = $ncresult;
    }

    for ($i=0; $i<$nresult; $i++) {
      $pos = strpos ($text, $sopener) + strlen ($sopener);
      $text = substr ($text, $pos, strlen ($text) );
      $pos = strpos ($text, $scloser);
      $result[$i] = substr ($text, 0, $pos);
      $text = substr ($text, $pos + strlen ($scloser), strlen ($text));
    }

    return $result;
  } // function tep_return_substrings

?>
