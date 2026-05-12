<?php


/*
  $Id: customers_basic_export.php,v 1.03 2010/09/14 $
  
*/

// Current EP Version
//$curver = '3.01 Basic';

require('epcustomersconfigure.php');
include ('includes/functions/easypopulate_functions.php');
include (DIR_WS_LANGUAGES . $language . '/easypopulate_customers.php');


//  Start TIMER
//  -----------
$stimer = explode( ' ', microtime() );
$stimer = $stimer[1] + $stimer[0];

global $filelayout, $filelayout_count, $filelayout_sql, $fileheaders;

$dltype = isset($_POST['dltype']) ? $_POST['dltype'] : '';
$download = isset($_POST['download']) ? $_POST['download'] : '';


//end intilization
// queries to pull data
if ($dltype != '' ){
  // if dltype is set, then create the filelayout.  Otherwise it gets read from the uploaded file

  global $GLOBALS, $filelayout, $filelayout_count, $filelayout_sql, $fileheaders;
  // depending on the type of the download the user wanted, create a file layout for it.
  $fieldmap = array(); // default to no mapping to change internal field names to external.
  switch( $dltype ){
  case 'full':
    $iii = 0;
    $filelayout = array(
//      'v_customers_id'    => $iii++,
      'v_customers_status'    => $iii++,
      'v_customers_gender'    => $iii++,
      'v_customers_firstname'    => $iii++,
      'v_customers_lastname'    => $iii++,
      'v_customers_dob'   => $iii++,
      'v_customers_email_address'   => $iii++,
      'v_customers_default_address_id'   => $iii++,
      'v_entry_street_address'   => $iii++,
      'v_entry_suburb'   => $iii++,
      'v_entry_postcode'   => $iii++,
      'v_entry_city'   => $iii++,
      'v_entry_country_id'   => $iii++,
//      'v_customers_password'   => $iii++,
      'v_entry_company'   => $iii++,
      'v_entry_company_tax_id'   => $iii++,
      'v_customers_telephone'   => $iii++,
      'v_customers_fax'   => $iii++
      );

     $header_array = array(
//      'v_customers_id'    => $iii++,
      'v_customers_status'    => $iii++,
      'v_customers_gender'    => $iii++,
      'v_customers_firstname'    => $iii++,
      'v_customers_lastname'    => $iii++,
      'v_customers_dob'   => $iii++,
      'v_customers_email_address'   => $iii++,
      'v_customers_default_address_id'   => $iii++,
      'v_entry_street_address'   => $iii++,
      'v_entry_suburb'   => $iii++,
      'v_entry_postcode'   => $iii++,
      'v_entry_city'   => $iii++,
      'v_entry_country_id'   => $iii++,
//      'v_customers_password'   => $iii++,
      'v_entry_company'   => $iii++,
      'v_entry_company_tax_id'   => $iii++,
      'v_customers_telephone'   => $iii++,
      'v_customers_fax'   => $iii++
      );



    $filelayout = array_merge($filelayout, $header_array);

    $filelayout_sql = "SELECT
      c.customers_id as v_customers_id,
      c.customers_status as v_customers_status,
      c.customers_gender as v_customers_gender,
      c.customers_firstname as v_customers_firstname,
      c.customers_lastname as v_customers_lastname,
      c.customers_dob as v_customers_dob,
      c.customers_email_address as v_customers_email_address,
      c.customers_default_address_id as v_customers_default_address_id,

      ab.entry_street_address as v_entry_street_address,
      ab.entry_suburb as v_entry_suburb,
      ab.entry_postcode as v_entry_postcode,
      ab.entry_city as v_entry_city,
      ab.entry_country_id as v_entry_country_id,

      c.entry_company as v_entry_company,
      c.entry_company_tax_id as v_entry_company_tax_id,
      c.customers_telephone as v_customers_telephone,
      c.customers_fax as v_customers_fax
      FROM
      ".TABLE_CUSTOMERS." as c,
      ".TABLE_ADDRESS_BOOK." as ab

      WHERE
	  c.customers_id = ab.customers_id
	  order by c.customers_lastname
      ";

    break;
//      c.customers_password as v_customers_password,


  case 'sppc':
    $iii = 0;
    $filelayout = array(
//      'v_customers_id'    => $iii++,
      'v_customers_status'    => $iii++,
      'v_customers_gender'    => $iii++,
      'v_customers_firstname'    => $iii++,
      'v_customers_lastname'    => $iii++,
      'v_customers_dob'   => $iii++,
      'v_customers_email_address'   => $iii++,
      'v_customers_default_address_id'   => $iii++,
      'v_entry_street_address'   => $iii++,
      'v_entry_suburb'   => $iii++,
      'v_entry_postcode'   => $iii++,
      'v_entry_city'   => $iii++,
      'v_entry_country_id'   => $iii++,
//      'v_customers_password'   => $iii++,
      'v_customers_group_id'   => $iii++,
      'v_entry_company'   => $iii++,
      'v_entry_company_tax_id'   => $iii++,
      'v_customers_telephone'   => $iii++,
      'v_customers_fax'   => $iii++
    );

     $header_array = array(
//      'v_customers_id'    => $iii++,
      'v_customers_status'    => $iii++,
      'v_customers_gender'    => $iii++,
      'v_customers_firstname'    => $iii++,
      'v_customers_lastname'    => $iii++,
      'v_customers_dob'   => $iii++,
      'v_customers_email_address'   => $iii++,
      'v_customers_default_address_id'   => $iii++,
      'v_entry_street_address'   => $iii++,
      'v_entry_suburb'   => $iii++,
      'v_entry_postcode'   => $iii++,
      'v_entry_city'   => $iii++,
      'v_countries_name'   => $iii++,
//      'v_customers_password'   => $iii++,
      'v_customers_group_name'   => $iii++,
      'v_entry_company'   => $iii++,
      'v_entry_company_tax_id'   => $iii++,
      'v_customers_telephone'   => $iii++,
      'v_customers_fax'   => $iii++
      );

    $filelayout_sql = "SELECT
      c.customers_id as v_customers_id,
      c.customers_status as v_customers_status,
      c.customers_gender as v_customers_gender,
      c.customers_firstname as v_customers_firstname,
      c.customers_lastname as v_customers_lastname,
      c.customers_dob as v_customers_dob,
      c.customers_email_address as v_customers_email_address,
      c.customers_default_address_id as v_customers_default_address_id,

      ab.entry_street_address as v_entry_street_address,
      ab.entry_suburb as v_entry_suburb,
      ab.entry_postcode as v_entry_postcode,
      ab.entry_city as v_entry_city,
      ab.entry_country_id as v_entry_country_id,

      c.customers_group_id as v_customers_group_id,
      c.entry_company as v_entry_company,
      c.entry_company_tax_id as v_entry_company_tax_id,
      c.customers_telephone as v_customers_telephone,
      c.customers_fax as v_customers_fax
      FROM
      ".TABLE_CUSTOMERS." as c,
      ".TABLE_CUSTOMERS_GROUPS." as cg,
      ".TABLE_ADDRESS_BOOK." as ab

      WHERE
      c.customers_group_id = cg.customers_group_id
	  and c.customers_id = ab.customers_id
	  order by c.customers_lastname
      ";
//      c.customers_password as v_customers_password,

    break;


  case 'mailing':
    $iii = 0;
    $filelayout = array(
      'v_customers_id'    => $iii++,
      'v_customers_firstname'    => $iii++,
      'v_customers_lastname'    => $iii++,
      'v_customers_email_address'   => $iii++
    );

     $header_array = array(
      'v_customers_id'    => $iii++,
      'v_customers_firstname'    => $iii++,
      'v_customers_lastname'    => $iii++,
      'v_customers_email_address'   => $iii++
      );

    $filelayout_sql = "SELECT
      c.customers_id as v_customers_id,
      c.customers_firstname as v_customers_firstname,
      c.customers_lastname as v_customers_lastname,
      c.customers_email_address as v_customers_email_address
      FROM
      ".TABLE_CUSTOMERS." as c
	  order by c.customers_lastname
      ";

    break;

  }
  $filelayout_count = count($filelayout);

//end output
}

//build downlaod file
if ( $download == 'stream' or  $download == 'tempfile' ){
  //*******************************
  //*******************************
  // DOWNLOAD FILE
  //*******************************
  //*******************************
  $filestring = ""; // this holds the csv file we want to download


  $result = tep_db_query($filelayout_sql);
  $row =  tep_db_fetch_array($result);

  // Here we need to allow for the mapping of internal field names to external field names
  // default to all headers named like the internal ones
  // the field mapping array only needs to cover those fields that need to have their name changed
  if ( count($fileheaders) != 0 ){
    $filelayout_header = $fileheaders; // if they gave us fileheaders for the dl, then use them
  } else {
    $filelayout_header = $filelayout; // if no mapping was spec'd use the internal field names for header names
  }
  //We prepare the table heading with layout values
  foreach( $filelayout_header as $key => $value ){
    $filestring .= $key . $separator;
  }
  // now lop off the trailing tab
  $filestring = substr($filestring, 0, strlen($filestring)-1);

  // set the type
    $endofrow = $separator . 'EOREOR' . "\n";
  $filestring .= $endofrow;


  while ($row){

      if ($row['v_customers_group_id'] != ''){
        $sql2 = "SELECT customers_group_name
          FROM ".TABLE_CUSTOMERS_GROUPS."
          WHERE
          customers_group_id = " . $row['v_customers_group_id']
          ;
        $result2 = tep_db_query($sql2);
        $row2 =  tep_db_fetch_array($result2);
        $row['v_customers_group_id'] = $row2['customers_group_name'];
      }


      if ($row['v_entry_country_id'] != ''){
        $sql2 = "SELECT countries_name
          FROM ".TABLE_COUNTRIES."
          WHERE
          countries_id = " . $row['v_entry_country_id']
          ;
        $result2 = tep_db_query($sql2);
        $row2 =  tep_db_fetch_array($result2);
        $row['v_entry_country_id'] = $row2['countries_name'];
      }

/*
      if ($row['v_customers_password'] != ''){
        $sql2 = "SELECT customers_password
          FROM ".TABLE_CUSTOMERS."
          WHERE
          customers_id = " . $row['v_customers_id']
          ;
        $result2 = tep_db_query($sql2);
        $row2 =  tep_db_fetch_array($result2);
        $row['v_customers_password'] = $row2[tep_encrypt_password('customers_password')];
      }
*/
//        $row['v_customers_password'] = tep_encrypt_password('customers_password');


    // Now set the status to a word the user specd in the config vars
    if ($row['v_customers_status'] == '1'){
      $row['v_customers_status'] = $active;
    } else {
      $row['v_customers_status'] = $inactive;
    }

    // remove any bad things in the texts that could confuse EasyPopulate
    $therow = '';
    foreach( $filelayout as $key => $value ){

      $thetext = $row[$key];
      // kill the carriage returns and tabs in the descriptions, they're killing me!
      $thetext = str_replace("\r",' ',$thetext);
      $thetext = str_replace("\n",' ',$thetext);
      $thetext = str_replace("\t",' ',$thetext);
      // and put the text into the output separated by tabs
      $therow .= $thetext . $separator;
    }

    // lop off the trailing tab, then append the end of row indicator
    $therow = substr($therow,0,strlen($therow)-1) . $endofrow;

    $filestring .= $therow;
    // grab the next row from the db
    $row =  tep_db_fetch_array($result);
  }

//End of create download
  #$EXPORT_TIME=time();
  $EXPORT_TIME = date('YMd-Hi');
    $EXPORT_TIME = "EP_CUSTOMERS_" . $EXPORT_TIME;

  // now either stream it to them or put it in the temp directory for all files
  if ($download == 'stream'){
    //*******************************
    // STREAM FILE
    //*******************************
    header("Content-type: application/vnd.ms-excel");
    header("Content-disposition: attachment; filename=$EXPORT_TIME.txt");
    header("Pragma: no-cache");
    header("Expires: 0");
    echo $filestring;
    die();

  } else {
    //*******************************
    // PUT FILE IN TEMP DIR
    //*******************************
    $tmpfname = DIR_FS_DOCUMENT_ROOT . $tempdir . "$EXPORT_TIME.txt";
    //unlink($tmpfname);
    $fp = fopen( $tmpfname, "w+");
    fwrite($fp, $filestring);
    fclose($fp);
          tep_redirect(tep_href_link(FILENAME_DATA_CUSTOMERS_EXPORT, 'mesID=MSG1&name=' . $EXPORT_TIME));


    //die();
  }
}   // *** END *** download section
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html <?php echo HTML_PARAMS; ?>>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>">
<title><?php echo TITLE; ?></title>
<script type="text/javascript" src="includes/prototype.js"></script>
<link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
<!--[if IE]>
<link rel="stylesheet" type="text/css" href="includes/stylesheet-ie.css">
<![endif]-->
<script language="javascript" src="includes/general.js"></script>
<script language="javascript"><!--
function popupWindow(url) {
  window.open(url,'popupWindow','toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=no,resizable=yes,copyhistory=no,width=450,height=300%,screenX=150,screenY=150,top=150,left=150')
}
//--></script>

</head>
<body marginwidth="0" marginheight="0" topmargin="0" bottommargin="0" leftmargin="0" rightmargin="0" bgcolor="#FFFFFF">
<?php require(DIR_WS_INCLUDES . 'header.php'); ?>

<div id="body">
<table border="0" width="100%" cellspacing="0" cellpadding="0" class="body-table">
<tr>
<?php require(DIR_WS_INCLUDES . 'column_left.php');?>
<?php
//$title = ' ';
?>

    <td valign="top" class="page-container"><table border="0" width="100%" cellspacing="0" cellpadding="0">
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo EASY_VERSION_B . EASY_VER_B . EASY_EXPORT; ?></td>
            <td class="pageHeading" align="right"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
          </tr>
        </table></td>

<?php
if (isset($_GET['mesID']) && $_GET['mesID'] == 'MSG1'){
       echo '<tr class="epa_msg"><td>' . EASY_FILE_LOCATE . $tempdir .  $name . ".txt" . '</td></tr>';
       
}

if (isset($_GET['mesID']) && $_GET['mesID'] == 'MSG2'){
       echo '<tr><td>' . EASY_FILE_LOCATE2 .  $name . ".txt" . '</td></tr>';
}
?>
               </tr>
        <tr class="dataTableHeadingRow">
                <td class="dataTableHeadingContent">
<b><?php echo EASY_LABEL_CREATE . '</b>' ;
         echo '' .  '&nbsp; &nbsp; <a href="javascript:popupWindow(\'' . tep_href_link(FILENAME_POPUP_EP_HELP,'action=ep_file_export') . '\')">' . tep_image(DIR_WS_IMAGES . 'information.png', IMAGE_ICON_INFO) . '</a> ';
?>                 </td>
               </tr>
               <tr>
               <td>
 <?php echo tep_draw_form('localfile_export', 'customers_basic_export.php', 'action=export', 'post', 'enctype="multipart/form-data"'); ?>
                 </td>
               </tr>
               <tr>
                 <td>
                 <b><?php echo EASY_LABEL_CREATE_SELECT. '</b>' ;
         echo '' .  '&nbsp; &nbsp; <a href="javascript:popupWindow(\'' . tep_href_link(FILENAME_POPUP_EP_HELP,'action=ep_select_method') . '\')">' . tep_image(DIR_WS_IMAGES . 'information.png', IMAGE_ICON_INFO) . '</a> ';
         echo '&nbsp;';?>
      <select name="download">
      <option selected value ="stream" size="10"><?php echo EASY_LABEL_DOWNLOAD . '<b> ';?>
      <option value="tempfile" size="10"><?php echo EASY_LABEL_CREATE_SAVE;?>
      </select>
                   </td>
      </tr>
      <tr>
       <td>

      
 <b><?php echo EASY_LABEL_SELECT_DOWN . '</b>';
  echo '' .  '&nbsp; &nbsp; <a href="javascript:popupWindow(\'' . tep_href_link(FILENAME_POPUP_EP_HELP,'action=ep_select_down') . '\')">' . tep_image(DIR_WS_IMAGES . 'information.png', IMAGE_ICON_INFO) . '</a> ';
        echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'?>
      <select name="dltype">
      <option selected value ="full" size="10"><?php echo EASY_LABEL_COMPLETE //FULL;?>
      <option value="sppc" size="10"><?php echo EASY_LABEL_GROUP //SPPC;?>
      <option value="mailing" size="10"><?php echo EASY_LABEL_MAILING //SPPC;?>

<?php
 ;?>
      </select>
       </td>
      </tr>
      <tr>
       <td>
                <?php echo tep_draw_separator('pixel_trans.gif', '5', '15') . '&nbsp;' . tep_image_submit('button_start_file_creation.gif', EASY_LABEL_PRODUCT_START); ?>
        </form>
        </td>
                 </tr>
                 <tr>
                 <td>
<?php
//  End TIMER
//  ---------
$etimer = explode( ' ', microtime() );
$etimer = $etimer[1] + $etimer[0];
echo '<p style="margin:auto; text-align:center">';
printf( TEXT_INFO_TIMER . " <b>%f</b> "  . TEXT_INFO_SECOND, ($etimer-$stimer) );
echo '</p>';
//  ---------
 ?>                
               
                 </td>
                 </tr>
        </table></td>
      </tr>
    </table></td>
<!-- body_text_eof //-->
  </tr>
</table>
</div>
<!-- body_eof //-->

<!-- footer //-->
<?php require(DIR_WS_INCLUDES . 'footer.php'); ?>
<!-- footer_eof //-->
<br>
</body>
</html>

<?php

require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
