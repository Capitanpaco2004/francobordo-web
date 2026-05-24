<?php
/*
  $Id: password_forgotten.php 1739 2007-12-20 00:52:16Z hpdl $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
*/

  require('includes/application_top.php');
// BOF Anti Robot Registration v2.7
  if (ACCOUNT_VALIDATION == 'true' && ACCOUNT_PASSWORD_FORGOTTEN_VALIDATION == 'true') {
    require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_ACCOUNT_VALIDATION);
    include_once('includes/functions/' . FILENAME_ACCOUNT_VALIDATION);
    $antirobotreg = tep_db_prepare_input($_POST['antirobotreg']);
    }
// EOF Anti Robot Registration v2.7
  require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_PASSWORD_FORGOTTEN);

  if (isset($_GET['action']) && ($_GET['action'] == 'process')) {
// BOF Anti Robotic Registration v2.5
    if (ACCOUNT_VALIDATION == 'true' && ACCOUNT_PASSWORD_FORGOTTEN_VALIDATION == 'true') {
      $sql = "SELECT * FROM " . TABLE_ANTI_ROBOT_REGISTRATION . " WHERE session_id = '" . tep_session_id() . "' LIMIT 1";
      if( !$result = tep_db_query($sql) ) {
        $error = true;
        $entry_antirobotreg_error = true;
        $text_antirobotreg_error = ERROR_VALIDATION_1;
      } else {
        $entry_antirobotreg_error = false;
        $anti_robot_row = tep_db_fetch_array($result);
        if (( strtoupper($_POST['antirobotreg']) != $anti_robot_row['reg_key'] ) || ($anti_robot_row['reg_key'] == '') || (strlen($antirobotreg) != ENTRY_VALIDATION_LENGTH)) {
          $error = true;
          $entry_antirobotreg_error = true;
          $text_antirobotreg_error = ERROR_VALIDATION_2;
        } else {
          $sql = "DELETE FROM " . TABLE_ANTI_ROBOT_REGISTRATION . " WHERE session_id = '" . tep_session_id() . "'";
          if( !$result = tep_db_query($sql) ) {
            $error = true;
            $entry_antirobotreg_error = true;
            $text_antirobotreg_error = ERROR_VALIDATION_3;
          } else {
            $sql = "OPTIMIZE TABLE " . TABLE_ANTI_ROBOT_REGISTRATION . "";
            if( !$result = tep_db_query($sql) ) {
              $error = true;
              $entry_antirobotreg_error = true;
              $text_antirobotreg_error = ERROR_VALIDATION_4;
            } else {
              $entry_antirobotreg_error = false; 
            }
          }
        }
      }
      if ($entry_antirobotreg_error == true) $messageStack->add('password_forgotten', $text_antirobotreg_error);
    }
	
	if (!$entry_antirobotreg_error) {
// EOF Anti Robotic Registration v2.5		
    $email_address = tep_db_prepare_input( trim( $_POST['email_address'] ?? '' ) );

    $check_customer_query = tep_db_query("select customers_firstname, customers_lastname, customers_password, customers_id from " . TABLE_CUSTOMERS . " where LCASE( customers_email_address ) = '" . tep_db_input( strtolower( $email_address ) ) . "'");
    if (tep_db_num_rows($check_customer_query)) {
      $check_customer = tep_db_fetch_array($check_customer_query);

      $new_password = tep_create_random_value(ENTRY_PASSWORD_MIN_LENGTH);
      $crypted_password = tep_encrypt_password($new_password);

      tep_db_query("update " . TABLE_CUSTOMERS . " set customers_password = '" . tep_db_input($crypted_password) . "' where customers_id = '" . (int)$check_customer['customers_id'] . "'");

      tep_mail($check_customer['customers_firstname'] . ' ' . $check_customer['customers_lastname'], $email_address, EMAIL_PASSWORD_REMINDER_SUBJECT, sprintf(EMAIL_PASSWORD_REMINDER_BODY, $new_password), STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);


if(ULTIMATE_HTML_EMAIL_DEVELOPMENT_MODE === 'true'){
	//Save the contents of the generated html email to the harddrive in .htm file. This can be practical when developing a new layout.
	$TheFileName = 'Last_mail_from_password_forgotten.php.htm';
	$TheFileHandle = fopen($TheFileName, 'w') or die("can't open error log file");
	fwrite($TheFileHandle, $email_text);
	fclose($TheFileHandle);
}
//---  End of addition: Ultimate HTML Emails  ---//
      $messageStack->add_session('login', SUCCESS_PASSWORD_SENT, 'success');

      tep_redirect(tep_href_link(FILENAME_LOGIN, '', 'SSL'));
    } else {
      $messageStack->add('password_forgotten', TEXT_NO_EMAIL_ADDRESS_FOUND);
    }
  }
// BOF Anti Robotic Registration v2.5	
}
	header('cache-control: no-store, no-cache, must-revalidate');
  header("Pragma: no-cache");
// EOF Anti Robotic Registration v2.5	
  $breadcrumb->add(NAVBAR_TITLE_1, tep_href_link(FILENAME_LOGIN, '', 'SSL'));
  $breadcrumb->add(NAVBAR_TITLE_2, tep_href_link(FILENAME_PASSWORD_FORGOTTEN, '', 'SSL'));
?>

<?php require(DIR_THEME. 'html/header.php'); ?>
<!-- header_eof //-->

<!-- body //-->
<?php require(DIR_THEME. 'html/column_left.php'); ?>

<?php echo tep_draw_form('password_forgotten', tep_href_link(FILENAME_PASSWORD_FORGOTTEN, 'action=process', 'SSL'), 'post', 'class="xform"'); ?>
      
<?php
  if ($messageStack->size('password_forgotten') > 0) {
?>
<div class="mensaje"><?php echo $messageStack->output('password_forgotten'); ?></div>
      
<?php
  }
?>
	<p><?php echo TEXT_MAIN; ?></p>
	<p><?php echo '<strong>' . ENTRY_EMAIL_ADDRESS . '</strong> ' . tep_draw_input_field('email_address'); ?></p>
      
<!-- // BOF Anti Robot Registration v2.7-->
<?php
    if (ACCOUNT_VALIDATION == 'true' && strstr($PHP_SELF,'password_forgotten') &&  ACCOUNT_PASSWORD_FORGOTTEN_VALIDATION == 'true') {
?>
      <p><strong><?php echo CATEGORY_ANTIROBOTREG; ?></strong></p>
<?php
        if ($is_read_only == false || (strstr($PHP_SELF,'password_forgotten')) ) {
          $sql = "DELETE FROM " . TABLE_ANTI_ROBOT_REGISTRATION . " WHERE timestamp < '" . (time() - 3600) . "' OR session_id = '" . tep_session_id() . "'";
          if( !$result = tep_db_query($sql) ) { die('Could not delete validation key'); }
            $reg_key = gen_reg_key();
            $sql = "INSERT INTO ". TABLE_ANTI_ROBOT_REGISTRATION . " VALUES ('" . tep_session_id() . "', '" . $reg_key . "', '" . time() . "')";
            if( !$result = tep_db_query($sql) ) { die('Could not check registration information'); }
?>
<p>
<?php
              $check_anti_robotreg_query = tep_db_query("select session_id, reg_key, timestamp from anti_robotreg where session_id = '" . tep_session_id() . "'");
              $new_guery_anti_robotreg = tep_db_fetch_array($check_anti_robotreg_query);
							if (empty($new_guery_anti_robotreg['session_id'])) echo 'Error, unable to read session id.';
              $validation_images = tep_image_captcha('validation_png.php?rsid=' . $new_guery_anti_robotreg['session_id'] .'&csh='.uniqid(0), 'name="Campcha"');
              if ($entry_antirobotreg_error == true) {
?>
<span>
<?php
                echo $validation_images . ' <br /> ';
                echo tep_draw_input_field('antirobotreg', '', '', '', false) . ' <br /><strong><font color="red">' . ERROR_VALIDATION . ' ' . $text_antirobotreg_error . '</strong></font>';
              } else {
?>
<span>
<?php      
                echo $validation_images . ' <br /> ';
								echo tep_draw_input_field('antirobotreg', '', '', '', false) . ' ' . ENTRY_ANTIROBOTREG_TEXT;
              }
            }
?>
</span>
</p>
<?php } ?>

<!-- // EOF Anti Robot Registration v2.7-->						
<div class="botonera">
                <?php echo '<a href="' . tep_href_link(FILENAME_LOGIN, '', 'SSL') . '">' . tep_image_button('button_back.gif', IMAGE_BUTTON_BACK, 'rojo') . '</a>'; ?>
				<?php echo tep_image_submit('button_continue.gif', IMAGE_BUTTON_CONTINUE); ?>
</div>                </form>
<!-- right_navigation //-->
<?php require(DIR_THEME. 'html/column_right.php'); ?>
<!-- right_navigation_eof //-->

<!-- body_eof //-->


<?php require(DIR_THEME. 'html/footer.php'); ?>

<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
