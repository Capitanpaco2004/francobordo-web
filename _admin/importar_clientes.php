<?php
/*
  $Id: customers_basic_import.php,v 1.03 2010/09/14 $

  Released under the GNU General Public License
*/

// Current EP Version
$curver = '3.01 Basic';

require('epcustomersconfigure.php');
include ('includes/functions/easypopulate_functions.php');
include (DIR_WS_LANGUAGES . $language . '/easypopulate_customers.php');

//  Start TIMER
//  -----------
$stimer = explode( ' ', microtime() );
$stimer = $stimer[1] + $stimer[0];
//  -----------


global $msg_output, $msg_epb, $msg_error;
// VJ product attributes begin


//*******************************
//*******************************
// E N D
// INITIALIZATION
//*******************************
//*******************************
 $action = (isset($_GET['action']) ? $_GET['action'] : '');
 $split = (isset($_GET['split']) ? $_GET['split'] : '');
 
if (tep_not_null($action)) {
  if ( (($action == 'upload') || ($action == 'local')) && ($split == 0) ) { 

//if ($localfile or (is_uploaded_file($usrfl) && $split==0)) {
  //*******************************
  //*******************************
  // UPLOAD AND INSERT FILE
  //*******************************
  //*******************************
//  check files name for EPA

      if (strstr($localfile, 'EPA')){
      $msg_error = EASY_ERROR_5 .  ' ' . EASY_ERROR_5a . '</a><br>';
     }else{
      }

if (strstr($usrfl_name, 'EPA')){
     $msg_error =  EASY_ERROR_5 .  ' ' . EASY_ERROR_5a . '</a><br>';
//   die();
     }else{
      }

  if ($action == 'upload'){
          // $_POST['usrfl']
        //  $usrfl=$_POST['usrfl'];
    // move the file to where we can work with it
    $file = tep_get_uploaded_file('usrfl');
    if (is_uploaded_file($file['tmp_name'])) {
      tep_copy_uploaded_file($file, DIR_FS_DOCUMENT_ROOT . $tempdir);
    }
      $msg_epb =  EASY_UPLOAD_FILE . '<br>' . EASY_UPLOAD_TEMP . $usrfl . '<br>' . EASY_UPLOAD_USER_FILE . $usrfl_name . '<br>' .  EASY_SIZE . $usrfl_size . '<br>';


    // get the entire file into an array
    $readed = file(DIR_FS_DOCUMENT_ROOT . $tempdir . $usrfl_name);
  }
  if ($action == 'local'){
    // move the file to where we can work with it
    $file = tep_get_uploaded_file('usrfl'); 
/*
            $attribute_options_query = "select distinct customers_id from " . TABLE_CUSTOMERS . " order by customers_id";
      $attribute_options_values = tep_db_query($attribute_options_query);
      $attribute_options_count = 1;
*/
      //while ($attribute_options = tep_db_fetch_array($attribute_options_values)){
    if (is_uploaded_file($file['tmp_name'])) {
      tep_copy_uploaded_file($file, DIR_FS_DOCUMENT_ROOT . $tempdir);
      }
          $msg_epb = EASY_LABEL_FILE_INSERT_LOCAL .  EASY_FILENAME . $localfile . '<br>';
    // get the entire file into an array
    $readed = file(DIR_FS_DOCUMENT_ROOT . $tempdir . $localfile);
  }

  // now we string the entire thing together in case there were carriage returns in the data
  $newreaded = "";
  foreach ($readed as $read){
    $newreaded .= $read;
  }

  // now newreaded has the entire file together without the carriage returns.
  // if for some reason excel put qoutes around our EOREOR, remove them then split into rows
  $newreaded = str_replace('"EOREOR"', 'EOREOR', $newreaded);
  $readed = explode( $separator . 'EOREOR',$newreaded);


  // Now we'll populate the filelayout based on the header row.
  $theheaders_array = explode( $separator, $readed[0] ); // explode the first row, it will be our filelayout
  $lll = 0;
  $filelayout = array();
  foreach( $theheaders_array as $header ){
    $cleanheader = str_replace( '"', '', $header);
  //  echo "Fileheader was $header<br><br><br>";
    $filelayout[ $cleanheader ] = $lll++; //
  }
  unset($readed[0]); //  we don't want to process the headers with the data

  // now we've got the array broken into parts by the expicit end-of-row marker.

//array_walk($readed, 'walk');
foreach ($readed as $readed_record) {
walk($readed_record);
}

}

//if is_uploaded_file($usrfl){
if ( (is_uploaded_file($usrfl)) && ($action == 'upload') && ($split == 1)) { 

  //*******************************
  //*******************************
  // UPLOAD AND SPLIT FILE
  //*******************************
  //*******************************

  //  check files name for EPA

        if (strstr($usrfl_name, 'EPA')){
    $msg_error =  EASY_ERROR_5 .  ' ' . EASY_ERROR_5a . '</a><br>';

  tep_redirect(customers_basic_import.php);
  // die();
       }else{
        }

  // move the file to where we can work with it
  $file = tep_get_uploaded_file('usrfl');
  //echo "Trying to move file...";
  if (is_uploaded_file($file['tmp_name'])) {
    tep_copy_uploaded_file($file, DIR_FS_DOCUMENT_ROOT . $tempdir);
  }

  $infp = fopen(DIR_FS_DOCUMENT_ROOT . $tempdir . $usrfl_name, "r");

  //toprow has the field headers
  $toprow = fgets($infp,32768);

  $filecount = 1;
  #$EXPORT_TIME=time();
  $EXPORT_TIME = date('YMd-Hi');
  

  $msg_epb = EASY_LABEL_FILE_COUNT_1A . $filecount . EASY_LABEL_FILE_COUNT_2;
  $tmpfname1 = HTTP_SERVER . DIR_WS_CATALOG . $tempdir . "EPB_Split" . $filecount . '_' . $EXPORT_TIME . ".txt";
  $tmpfname = DIR_FS_DOCUMENT_ROOT . $tempdir . "EPB_Split" . $filecount . '_' . $EXPORT_TIME . ".txt";
  $fp = fopen( $tmpfname, "w+");
  fwrite($fp, $toprow);

  $linecount = 0;
  $line = fgets($infp,32768);
  while ($line){
    // walking the entire file one row at a time
    // but a line is not necessarily a complete row, we need to split on rows that have "EOREOR" at the end
    $line = str_replace('"EOREOR"', 'EOREOR', $line);
    fwrite($fp, $line);
    if (strpos($line, 'EOREOR')){
      // we found the end of a line of data, store it
      $linecount++; // increment our line counter
      if ($linecount >= $maxrecs){
        $msg_epb = EASY_LABEL_LINE_COUNT_1 . $linecount . EASY_LABEL_LINE_COUNT_2 . '<Br>';
        $linecount = 0; // reset our line counter
        // close the existing file and open another;
        fclose($fp);
        // increment filecount
        $filecount++;
         $tmpfname1 = HTTP_SERVER . DIR_WS_CATALOG . $tempdir . "EPB_Split" . $filecount . '_' . $EXPORT_TIME . ".txt";
               $tmpfname = DIR_FS_DOCUMENT_ROOT . $tempdir . "EPB_Split" . $filecount . '_' . $EXPORT_TIME . ".txt";
        //Open next file name
        $fp = fopen( $tmpfname, "w+");
        fwrite($fp, $toprow);
      }
    }
    $line=fgets($infp,32768);
  }
  $msg_epb = EASY_LABEL_FILE_CLOSE_1 . $linecount . EASY_LABEL_FILE_CLOSE_2 . '<br>';
  fclose($fp);
  fclose($infp);
  $msg_epb = EASY_SPLIT_DOWN . $tmpfname1;
  }
//if is_uploaded_file($usrfl){
if ( ($action == 'local') && ($split == 1)) { 

  //*******************************
  //*******************************
  // server file splitSPLIT FILE
  //*******************************
  //*******************************
//  check files name for EPA
      if (strstr($localfile1, 'EP_CUSTOMERS')){   
     }else{
                $msg_error = EASY_ERROR_6 .  '<a href="' . tep_href_link(FILENAME_DATA_CUSTOMERS_IMPORT) . '">' . EASY_ERROR_6a . '</a><br>';
      // die();

      }
    $file = tep_get_uploaded_file('localfile1');  

    if (is_uploaded_file($file['tmp_name'])) {
      tep_copy_uploaded_file($file, DIR_FS_DOCUMENT_ROOT . $tempdir);
      }

  $infp = fopen(DIR_FS_DOCUMENT_ROOT . $tempdir . $file['tmp_name'], "r");

  //toprow has the field headers
  $toprow = fgets($infp,32768);

  $filecount = 1;
  #$EXPORT_TIME=time();
  $EXPORT_TIME = date('YMd-Hi');

  $msg_epa = EASY_LABEL_FILE_COUNT_1A . $filecount . EASY_LABEL_FILE_COUNT_2;
  $tmpfname1 = HTTP_SERVER . DIR_WS_CATALOG . $tempdir . "EPB_Split" . $filecount . '_' . $EXPORT_TIME . ".txt";
  $tmpfname = DIR_FS_DOCUMENT_ROOT . $tempdir . "EPB_Split" . $filecount . '_' . $EXPORT_TIME . ".txt";
  $fp = fopen( $tmpfname, "w+");
  fwrite($fp, $toprow);

  $linecount = 0;
  $line = fgets($infp,32768);
  while ($line){
    // walking the entire file one row at a time
    // but a line is not necessarily a complete row, we need to split on rows that have "EOREOR" at the end
    $line = str_replace('"EOREOR"', 'EOREOR', $line);
    fwrite($fp, $line);
    if (strpos($line, 'EOREOR')){
      // we found the end of a line of data, store it
      $linecount++; // increment our line counter
      if ($linecount >= $maxrecs){
        $msg_epa = EASY_LABEL_LINE_COUNT_1 . $linecount . EASY_LABEL_LINE_COUNT_2 . '<Br>';
        $linecount = 0; // reset our line counter
        // close the existing file and open another;
        fclose($fp);
        // increment filecount
        $filecount++;
         $tmpfname1 = HTTP_SERVER . DIR_WS_CATALOG . $tempdir . "EPB_Split" . $filecount . '_' . $EXPORT_TIME . ".txt";
               $tmpfname = DIR_FS_DOCUMENT_ROOT . $tempdir . "EPB_Split" . $filecount . '_' . $EXPORT_TIME . ".txt";
        //Open next file name
        $fp = fopen( $tmpfname, "w+");
        fwrite($fp, $toprow);
      }
    }
    $line=fgets($infp,32768);
  }
  $msg_epa = EASY_LABEL_FILE_CLOSE_1 . $linecount . EASY_LABEL_FILE_CLOSE_2 . '<br>';
  fclose($fp);
  fclose($infp);
  $msg_epa = EASY_SPLIT_DOWN . $tmpfname1;
  }
}


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
    <td valign="top" class="page-container"><table border="0" width="100%" cellspacing="0" cellpadding="0">
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo EASY_VERSION_B . EASY_VER_B . EASY_IMPORT; ?></td>
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
        <tr>
                <td>
 <?php echo tep_draw_form('localfile_insert', 'customers_basic_import.php', 'action=upload&split=0', 'post', 'ENCTYPE="multipart/form-data"'); ?>
             
 <?php ECHO '<b>' . EASY_UPLOAD_EP_FILE . '</b>';
       ECHO '' .  '&nbsp; &nbsp; <a href="javascript:popupWindow(\'' . tep_href_link(FILENAME_POPUP_EP_HELP,'action=ep_file_upload') . '\')">' . tep_image(DIR_WS_IMAGES . 'information.png', IMAGE_ICON_INFO) . '</a> ';
 ?>
                  </td>
                  </tr>
                  <tr>
                    <td>
                  <INPUT TYPE="hidden" name="MAX_FILE_SIZE" value="100000000">
     <?php echo  tep_draw_file_field('usrfl', '50') ;?>
     <?php echo tep_draw_separator('pixel_trans.gif', '5', '15') . '&nbsp;' . tep_image_submit('button_insert_into_db.gif', TEXT_INSERT_INTO_DB); ?>
              </form>
          </td>
          </tr>
          <tr >
          <td>
<?php echo tep_draw_form('localfile_insert', 'customers_basic_import.php', '&action=upload&split=1', 'post', 'ENCTYPE="multipart/form-data"'); ?>
                <b> <?php echo EASY_SPLIT_EP_FILE . '</b>' ;
      echo '' .  '&nbsp; &nbsp; <a href="javascript:popupWindow(\'' . tep_href_link(FILENAME_POPUP_EP_HELP,'action=ep_file_upload_split') . '\')">' . tep_image(DIR_WS_IMAGES . 'information.png', IMAGE_ICON_INFO) . '</a> ';
?>       
             </td>
           </tr>
           <tr>
           <td>
                 <INPUT TYPE="hidden" name="MAX_FILE_SIZE" value="1000000000">
             <?php echo  tep_draw_file_field('usrfl', '50') ;?>
       <?php echo tep_draw_separator('pixel_trans.gif', '5', '15') . '&nbsp;' . tep_image_submit('button_split_file.gif', TEXT_SPLIT); ?>
             </form>

          </td>
          </tr>
          <tr>
          <td>
                        <b> <?php echo EASY_SPLIT_EP_LOCAL . '</b>' ;  
         echo tep_draw_form('localfile_split', 'customers_basic_import.php', '&action=local&split=1', 'post', 'ENCTYPE="multipart/form-data"'); 
      echo '' .  '&nbsp; &nbsp; <a href="javascript:popupWindow(\'' . tep_href_link(FILENAME_POPUP_EP_HELP,'action=ep_file_split') . '\')">' . tep_image(DIR_WS_IMAGES . 'information.png', IMAGE_ICON_INFO) . '</a> ';
     ?> 
           </td>
           </tr>
           <tr>
             <td>
    <?php
        $dir = dir(DIR_FS_CATALOG . $tempdir);
        $contents1 = array(array('id' => '', 'text' => TEXT_SELECT_TWO));
        while ($file1 = $dir->read()) {
          if ( ($file1 != '.') && ($file1 != 'CVS') && ($file1 != '..') && ($file1 != '.htaccess') && !(strstr($file1, 'EPA')) && !(strstr($file1, 'EPA_Split')) ) {
            $contents1[] = array('id' => $file1, 'text' => $file1);
          }
        }
        echo tep_draw_pull_down_menu('localfile1', $contents1, (isset($localfile1) ? $localfile1 : ''));
echo tep_draw_separator('pixel_trans.gif', '5', '15') . '&nbsp;' . tep_image_submit('button_split_file.gif', TEXT_SPLIT); ?>

           </form>
                </td>
               </tr>          
          <tr>
          <td>
   <?php echo tep_draw_form('localfile_insert', 'customers_basic_import.php', '&action=local&split=0', 'post', 'ENCTYPE="multipart/form-data"'); ?>

      <b><?php echo sprintf(TEXT_IMPORT_TEMP, $tempdir) . '</b>';
      echo '' .  '&nbsp; &nbsp; <a href="javascript:popupWindow(\'' . tep_href_link(FILENAME_POPUP_EP_HELP,'action=ep_file_insert') . '\')">' . tep_image(DIR_WS_IMAGES . 'information.png', IMAGE_ICON_INFO) . '</a> ';
     ?> 
           </td>
           </tr>
           <tr>
             <td>
    <?php
        $dir = dir(DIR_FS_CATALOG . $tempdir);
        $contents = array(array('id' => '', 'text' => TEXT_SELECT_ONE));
        while ($file = $dir->read()) {
          if ( ($file != '.') && ($file != 'CVS') && ($file != '..') && !(strstr($file, 'EPA')) && ($file != '.htaccess')) {
            //$file_size = filesize(DIR_FS_CATALOG . $tempdir . $file);

            $contents[] = array('id' => $file, 'text' => $file);
          }
        }
        echo tep_draw_pull_down_menu('localfile', $contents, (isset($localfile) ? $localfile : ''));
echo tep_draw_separator('pixel_trans.gif', '5', '15') . '&nbsp;' . tep_image_submit('button_insert_into_db.gif', TEXT_INSERT_INTO_DB); ?>

           </form>
                </td>
               </tr>
               
                <tr>
<?php // echo error
 if ($msg_error != ''){
    echo  '<td><p class="smallText"><font color=\'red\'>' . $msg_error . '</font></p></td></tr>';
 }    
 ?>  
 
 <?php // echo epa message
  if ($msg_epb != ''){
     echo  '<td><p class="smallText">' . $msg_epb . '</p></td></tr>';
 }    ?>  
 
 <?php // echo line by line results
  if ($msg_output != ''){
     echo  '<td><p class="smallText">' . $msg_output . '</p></td></tr>';
 }    ?>  
      <td>
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
  </td></tr>
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
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); 


function walk( $item1 ) {
  global $GLOBALS, $filelayout, $filelayout_count;
  global $active, $inactive, $default_these;
        global $replace_quotes, $v_customers_id1;
  global $separator, $msg_error, $msg_epa, $msg_output ;
  // first we clean up the row of data

  // chop blanks from each end
  $item1 = ltrim(rtrim($item1));

  // blow it into an array, splitting on the tabs
  $items = explode($separator, $item1);

  // make sure all non-set things are set to '';
  // and strip the quotes from the start and end of the stings.
  // escape any special chars for the database.
  foreach( $filelayout as $key=> $value){
    $i = $filelayout[$key];
    if (isset($items[$i]) == false) {
      $items[$i]='';
    } else {
      // Check to see if either of the magic_quotes are turned on or off;
      // And apply filtering accordingly.
      if (function_exists('ini_get')) {
        //echo "Getting ready to check magic quotes<br>";
        if (ini_get('magic_quotes_runtime') == 1){
          // The magic_quotes_runtime are on, so lets account for them
          // check if the last character is a quote;
          // if it is, chop off the quotes.
          if (substr($items[$i],-1) == '"'){
            $items[$i] = substr($items[$i],2,strlen($items[$i])-4);
          }
          // now any remaining doubled double quotes should be converted to one doublequote
          $items[$i] = str_replace('\"\"',"\"",$items[$i]);
        } else { // no magic_quotes are on
          // check if the last character is a quote;
          // if it is, chop off the 1st and last character of the string.
          if (substr($items[$i],-1) == '"'){
            $items[$i] = substr($items[$i],1,strlen($items[$i])-2);
          }
          // now any remaining doubled double quotes should be converted to one doublequote
          $items[$i] = str_replace('""',"\"",$items[$i]);
          if ($replace_quotes){
            $items[$i] = str_replace('"',"\"",$items[$i]);
            $items[$i] = str_replace("'","\'",$items[$i]);
          }
        }
      }
    }
  }

  // now do a query to get the record's current contents
  $sql = "SELECT
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

      ab.entry_company as v_entry_company,
      ab.entry_company_tax_id as v_entry_company_tax_id,
      ab.entry_telephone as v_customers_telephone,
      ab.entry_fax as v_customers_fax,

      c.customers_password as v_customers_password,
      c.customers_group_id as v_customers_group_id,
      c.entry_company as v_entry_company,
      c.entry_company_tax_id as v_entry_company_tax_id,
      c.customers_telephone as v_customers_telephone,
      c.customers_fax as v_customers_fax,

    ci.customers_info_date_account_created  as v_customers_info_date_account_created,
    ci.customers_info_date_account_last_modified  as v_customers_info_date_account_last_modified,

      cg.customers_group_name as v_customers_group_name

      FROM
      ".TABLE_CUSTOMERS." as c,
      ".TABLE_CUSTOMERS_GROUPS." as cg,
      ".TABLE_ADDRESS_BOOK." as ab,
      ".TABLE_CUSTOMERS_INFO." as ci
	  
      WHERE
      c.customers_group_id = cg.customers_group_id and
      c.customers_id = ab.customers_id and
      c.customers_id = ci.customers_info_id
    ";

  $result = tep_db_query($sql);
  $row =  tep_db_fetch_array($result);


  while ($row){
    // OK, since we got a row, the item already exists.
    // Let's get all the data we need and fill in all the fields that need to be defaulted to the current values
      $sql2 = "SELECT *
        FROM ".TABLE_CUSTOMERS."
        WHERE
          customers_id = " . $row['v_customers_id'] . "
        ";
      $result2 = tep_db_query($sql2);
      $row2 =  tep_db_fetch_array($result2);
      $row['v_customers_firstname']    = $row2['customers_firstname'];
      $row['v_customers_lastname']    = $row2['customers_lastname'];


      if ($v_customers_group_id != ''){
        $sql2 = "SELECT customers_group_id, customers_group_name
          FROM ".TABLE_CUSTOMERS_GROUPS."
          WHERE
          customers_group_name = '" . $v_customers_group_id ."'"
          ;
        $result2 = tep_db_query($sql2);
        $row2 =  tep_db_fetch_array($result2);
        $v_customers_group_id = $row2['customers_group_id'];
      }


      if ($v_customers_default_address_id != ''){
        $sql2 = "SELECT address_book_id 
          FROM ".TABLE_ADDRESS_BOOK."
          WHERE
          address_book_id = '" . $v_customers_default_address_id ."'"
          ;
        $result2 = tep_db_query($sql2);
        $row2 =  tep_db_fetch_array($result2);
        $v_customers_default_address_id = $row2['address_book_id'];
      }

    // now create the internal variables that will be used
    // the $$thisvar is on purpose: it creates a variable named what ever was in $thisvar and sets the value
    foreach ($default_these as $thisvar){
      $$thisvar = $row[$thisvar];
    }

    $row =  tep_db_fetch_array($result);
  }
// Begin writting new data to current data

  // this is an important loop.  What it does is go thru all the fields in the incoming file and set the internal vars.
  // Internal vars not set here are either set in the loop above for existing records, or not set at all (null values)
  // the array values are handled separatly, although they will set variables in this loop, we won't use them.
  foreach( $filelayout as $key => $value ){
    $$key = $items[ $value ];
  }



    if ($v_customers_status == 'Active' ){
      $v_customers_status = '1';
    } else {
      $v_customers_status = '0';
    }


/////////////////

      if ($v_entry_country_id != ''){
        $sql2 = "SELECT countries_id, countries_name
          FROM ".TABLE_COUNTRIES."
          WHERE
          countries_name = '" . $v_entry_country_id ."'"
          ;
        $result2 = tep_db_query($sql2);
        $row2 =  tep_db_fetch_array($result2);
        $v_entry_country_id = $row2['countries_id'];
      }else{
    	$v_entry_country_id="France";
	  }


//////////////////

  if ($v_customers_group_id==''){
    $v_customers_group_id="Default";
  }


  // OK, we need to convert the manufacturer's name into id's for the database
  if ( isset($v_customers_group_id) && $v_customers_group_id != '' ){
    $sql = "SELECT man.customers_group_id
      FROM ".TABLE_CUSTOMERS_GROUPS." as man
      WHERE
        man.customers_group_name = '" . $v_customers_group_id . "'";
    $result = tep_db_query($sql);
    $row =  tep_db_fetch_array($result);
    if ( $row != '' ){
      foreach( $row as $item ){
        $v_customers_group_id = $item;
      }
    } else {
      // to add, we need to put stuff in categories and categories_description
      $sql = "SELECT MAX(customers_group_id) max FROM ".TABLE_CUSTOMERS_GROUPS;
      $result = tep_db_query($sql);
      $row =  tep_db_fetch_array($result);
      $max_mfg_id = $row['max']+1;
      // default the id if there are no manufacturers yet
      if (!is_numeric($max_mfg_id) ){
        $max_mfg_id=1;
      }

        $sql = "INSERT INTO ".TABLE_CUSTOMERS_GROUPS."(
        customers_group_id,
        customers_group_name,
		color_bar,
		customers_group_template
        ) VALUES (
        $max_mfg_id,
        '$v_customers_group_id',
		'#009900',
		'Original_ats'
        )";
      $result = tep_db_query($sql);
      $v_customers_group_id = $max_mfg_id;
    }
  }

/////////////////////////

  if ($v_customers_lastname != "") {
    array_walk($items, 'print_el');

    $result = tep_db_query("SELECT customers_id FROM ".TABLE_CUSTOMERS." WHERE (customers_lastname = '". $v_customers_lastname . "')");

    if (tep_db_num_rows($result) == 0)  {

      $sql = "SHOW TABLE STATUS LIKE '".TABLE_CUSTOMERS."'";
      $result = tep_db_query($sql);
      $row =  tep_db_fetch_array($result);

      $max_customers_id = $row['Auto_increment'];
      if (!is_numeric($max_customers_id) ){
        $max_customers_id=1;
      }
      $v_customers_id = $max_customers_id;
/////////
      $v_customers_default_address_id = $v_customers_id;

/////////

      $msg_output .=  EASY_LABEL_NEW_PRODUCT ;



      $query = "INSERT INTO ".TABLE_CUSTOMERS." (
      customers_status,
      customers_gender,
      customers_firstname,
      customers_lastname,
      customers_dob,
      customers_email_address,
      customers_default_address_id,
      customers_password,
      customers_group_id,
      entry_company,
      entry_company_tax_id,
      customers_telephone,
      customers_fax
)
            VALUES (
                '$v_customers_status',
                '$v_customers_gender',
                '$v_customers_firstname',
                '$v_customers_lastname',
                '$v_customers_dob',
                '$v_customers_email_address',
                '$v_customers_default_address_id',
                '$v_customers_password',
                '$v_customers_group_id',
                '$v_entry_company',
                '$v_entry_company_tax_id',
                '$v_customers_telephone',
                '$v_customers_fax')
              ";
        $result = tep_db_query($query);

      $sql2 = "SHOW TABLE STATUS LIKE '".TABLE_ADDRESS_BOOK."'";
      $result2 = tep_db_query($sql2);
      $row2 =  tep_db_fetch_array($result2);


      $max_address_id = $row2['Auto_increment'];
      if (!is_numeric($max_address_id) ){
        $max_address_id=1;
      }
      $v_address_book_id = $max_address_id;

      $query2 = "INSERT INTO ".TABLE_ADDRESS_BOOK." (
	  customers_id,
      entry_gender,
      entry_company,
      entry_company_tax_id,
      entry_firstname,
      entry_lastname,
	  entry_street_address,
	  entry_suburb,
	  entry_postcode,
	  entry_city,
	  entry_state,
	  entry_country_id,
	  entry_telephone,
	  entry_fax,
      entry_email_address
)
            VALUES (
                '$v_customers_id',
                '$v_customers_gender',			
                '$v_entry_company',
                '$v_entry_company_tax_id',
                '$v_customers_firstname',
                '$v_customers_lastname',
                '$v_entry_street_address',
                '$v_entry_suburb',
                '$v_entry_postcode',
                '$v_entry_city',
                '$v_entry_state',
                '$v_entry_country_id',
                '$v_customers_telephone',
                '$v_customers_fax',
                '$v_customers_email_address'
				)
              ";
        $result2 = tep_db_query($query2);


     $query3 = "INSERT INTO ".TABLE_CUSTOMERS_INFO." (
	  customers_info_id,
      customers_info_date_account_created 
)
            VALUES (
                '$v_customers_id',
                CURRENT_TIMESTAMP
				)
              ";
        $result3 = tep_db_query($query3);


    } else {
      // and update data
      $row =  tep_db_fetch_array($result);
      $v_customers_id = $row['customers_id'];
      $msg_output .= EASY_LABEL_UPDATED;
      $row =  tep_db_fetch_array($result);

      $query = 'UPDATE '.TABLE_CUSTOMERS.'
          SET
         customers_status="'.$v_customers_status.
          '" ,customers_gender="'.$v_customers_gender.
          '" ,customers_firstname="'.$v_customers_firstname.
          '" ,customers_lastname="'.$v_customers_lastname.
          '" ,customers_dob="'.$v_customers_dob.
          '" ,customers_email_address="'.$v_customers_email_address.
          '" ,customers_default_address_id="'.$v_customers_default_address_id.
//          '" ,customers_password="'.$v_customers_password.
          '" ,customers_group_id="'.$v_customers_group_id.
          '" ,entry_company="'.$v_entry_company.
          '" ,entry_company_tax_id="'.$v_entry_company_tax_id.
          '" ,customers_telephone="'.$v_customers_telephone.
          '" ,customers_fax="'.$v_customers_fax.'"
          WHERE
            (customers_id = "'. $v_customers_id . '")';


      $result = tep_db_query($query);

      $query2 = 'UPDATE '.TABLE_ADDRESS_BOOK.'
          SET
          customers_id="'.$v_customers_id.
          '" ,entry_gender="'.$v_customers_gender.
          '" ,entry_street_address="'.$v_entry_street_address.
          '" ,entry_suburb="'.$v_entry_suburb.
          '" ,entry_postcode="'.$v_entry_postcode.
          '" ,entry_city="'.$v_entry_city.
          '" ,entry_country_id="'.$v_entry_country_id.
          '" ,entry_company="'.$v_entry_company.
          '" ,entry_company_tax_id="'.$v_entry_company_tax_id.
          '" ,entry_email_address="'.$v_customers_email_address.
          '" ,entry_telephone="'.$v_customers_telephone.
          '" ,entry_fax="'.$v_customers_fax.'"
          WHERE
            (customers_id = "'. $v_customers_id . '") 
            and (address_book_id = "'. $v_customers_default_address_id . '") 
			';


      $result2 = tep_db_query($query2);

      $query3 = 'UPDATE '.TABLE_CUSTOMERS_INFO.'
          SET
          customers_info_date_account_last_modified = CURRENT_TIMESTAMP
          WHERE
            (customers_info_id = "'. $v_customers_id . '")';

      $result3 = tep_db_query($query3);

    }

    // the following is common in both the updating an existing customer and creating a new customer
                if ( isset($v_customers_firstname)){
//      foreach( $v_customers_firstname as $key => $firstname){
              if ($name!=''){
          $sql = "SELECT * FROM ".TABLE_CUSTOMERS." WHERE
              customers_id = $v_customers_id ";
          $result = tep_db_query($sql);
          if (tep_db_num_rows($result) == 0) {
            $result = tep_db_query($sql);
            $sql =

             "INSERT INTO ".TABLE_CUSTOMERS."
                (customers_id,
                customers_firstname,
                customers_lastname
				)
                VALUES (
                  '" . $v_customers_id . "',
                  '" . $v_customers_firstname . "',
                  '". $v_customers_lastname . "'
                  )";
            $result = tep_db_query($sql);
          } else {
            $sql =
              "UPDATE ".TABLE_CUSTOMERS." SET
                customers_firstname='".$v_customers_firstname . "',
                customers_lastname='".$v_customers_lastname . "'
              WHERE
                customers_id = '$v_customers_id'";
            $result = tep_db_query($sql);
          }
        }
//      }
    }
      } else {
    array_walk($items, 'print_el');
    $msg_output .= EASY_ERROR_7 ;
  }
// end of row insertion code
}


require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
