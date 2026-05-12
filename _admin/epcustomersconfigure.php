<?php
/*
  $Id: epcustomersconfigure.php,v 1.0 2010/08/14 $
*/

//
//*******************************
//*******************************
// C O N F I G U R A T I O N
// V A R I A B L E S
//*******************************
//*******************************

// **** Temp directory ****
// if you changed your directory structure from stock and do not have /catalog/temp/, then you'll need to change this accordingly.
//
$tempdir = "temp/";
$tempdir2 = "/temp/";

//**** File Splitting Configuration ****
// we attempt to set the timeout limit longer for this script to avoid having to split the files
// NOTE:  If your server is running in safe mode, this setting cannot override the timeout set in php.ini
// uncomment this if you are not on a safe mode server and you are getting timeouts
// set_time_limit(330);

// if you are splitting files, this will set the maximum number of records to put in each file.
// if you set your php.ini to a long time, you can make this number bigger
global $maxrecs;
$maxrecs = 1500; // default, seems to work for most people.  Reduce if you hit timeouts
//$maxrecs = 4; // for testing


//**** Status Field Setting ****
global $active, $inactive;
$active = 'Activo';
$inactive = 'Inactivo';

// **** Quote -> Escape character conversion ****
// If you have extensive html in your descriptions and it's getting mangled on upload, turn this off
// set to true = replace quotes with escape characters
// set to false = no quote replacement
global $replace_quotes;
$replace_quotes = true;

// **** Field Separator ****
// change this if you can't use the default of tabs
// Tab is the default, comma and semicolon are commonly supported by various progs
// Remember, if your descriptions contain this character, you will confuse EP!
global $separator;
$separator = "\t"; // tab is default
//$separator = ","; // comma
//$separator = ";"; // semi-colon
//$separator = "~"; // tilde
//$separator = "-"; // dash
//$separator = "*"; // splat

// **** File extension ****
global $file_extension;
$file_extension = "txt"; // .txt is default
//$file_extension = "csv"; // .txt is default


require('includes/application_top.php');

global $filelayout, $filelayout_count, $filelayout_sql, $fileheaders;

// these are the fields that will be defaulted to the current values in the database if they are not found in the incoming file
global $default_these;
$default_these = array(
      'v_customers_id',
      'v_customers_status',
      'v_customers_gender',
      'v_customers_firstname',
      'v_customers_lastname',
      'v_customers_dob',
      'v_customers_email_address',
      'v_customers_default_address_id',
      'v_entry_street_address',
      'v_entry_suburb',
      'v_entry_postcode',
      'v_entry_city',
      'v_entry_country_id',
      'v_customers_password',
      'v_customers_group_id',
      'v_entry_company',
      'v_customers_telephone',
      'v_customers_fax'
  );

//*******************************
//*******************************
// E N D
// C O N F I G U R A T I O N
// V A R I A B L E S
//*******************************
//*******************************



?>