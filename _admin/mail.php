<?php
/*
  $Id: mail.php 1739 2007-12-20 00:52:16Z hpdl $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
*/

  require('includes/application_top.php');
// BOF Separate Pricing Per Customer
  $customers_group_query = tep_db_query("select customers_group_id, customers_group_name from " . TABLE_CUSTOMERS_GROUPS . " order by customers_group_id");
  $cg_array = array();
  while ($customers_group = tep_db_fetch_array($customers_group_query)) {
	  $cg_array[$customers_group['customers_group_id']] = array('id' => $customers_group['customers_group_id'], 'customers_group_name' => $customers_group['customers_group_name']);
  }
// EOF Separate Pricing Per Customer

  $action = (isset($_GET['action']) ? $_GET['action'] : '');

  if ( ($action == 'send_email_to_user') && isset($_POST['customers_email_address']) && !isset($_POST['back_x']) ) {
    switch ($_POST['customers_email_address']) {
// BOF Separate Pricing Per Customer
      case substr($_POST['customers_email_address'], 0, 3) == '***' :
      $email_all_array = explode('_', $_POST['customers_email_address']);
      if ($email_all_array[1] == '') { // all customers
        $mail_query = tep_db_query("select customers_firstname, customers_lastname, customers_email_address from " . TABLE_CUSTOMERS);
        $mail_sent_to = TEXT_ALL_CUSTOMERS;
      } else {
	$mail_query = tep_db_query("select customers_firstname, customers_lastname, customers_email_address from " . TABLE_CUSTOMERS . " where customers_group_id = '" . (int)$email_all_array[2] . "'");
        $mail_sent_to = TEXT_FOR_ALL . " " .$cg_array[(int)$email_all_array[2]]['customers_group_name'] . " " . TEXT_FOR_CUSTOMERS ;
      }
        break;
      case substr($_POST['customers_email_address'], 0, 3) == '**D':
      $email_all_array = explode('_', $_POST['customers_email_address']);
      if ($email_all_array[1] == '') { // all newsletter subscribers
        $mail_query = tep_db_query("select customers_firstname, customers_lastname, customers_email_address from " . TABLE_CUSTOMERS . " where customers_newsletter = '1'");
        $mail_sent_to = TEXT_NEWSLETTER_CUSTOMERS;
      } else {
	$mail_query = tep_db_query("select customers_firstname, customers_lastname, customers_email_address from " . TABLE_CUSTOMERS . " where customers_newsletter = '1' and customers_group_id = '" . (int)$email_all_array[2] . "'");
        $mail_sent_to = $cg_array[(int)$email_all_array[2]]['customers_group_name'] . " " . TEXT_FOR_CUSTOMERS . " " . TEXT_FOR_NEWSLETTER_SUBSCRIBERS ;
      }
// EOF Separate Pricing Per Customer
        break;
      default:
        $customers_email_address = tep_db_prepare_input($_POST['customers_email_address']);

        $mail_query = tep_db_query("select customers_firstname, customers_lastname, customers_email_address from " . TABLE_CUSTOMERS . " where customers_email_address = '" . tep_db_input($customers_email_address) . "'");
        $mail_sent_to = $_POST['customers_email_address'];
        break;
    }

    $from        = tep_db_prepare_input($_POST['from']);
$subject     = tep_db_prepare_input($_POST['subject']);
$message     = tep_db_prepare_input($_POST['message']);
$mimemessage = new email(array('X-Mailer: osCommerce'));

if (isset($GLOBALS['userfile']) && tep_not_null($GLOBALS['userfile']))
       {  $attachment_name   = $_POST['userfile']['name'];
          $attachment_type   = $_POST['userfile']['type'];
	  //$attachment_size = $_POST['userfile']['size']; //Just in case you want to check and limit the size
          new upload('userfile', DIR_FS_DOWNLOAD);
          $attachment_file   = DIR_FS_DOWNLOAD . $attachment_name;
          $attachments       = $mimemessage->get_file($attachment_file);
    	  $mimemessage->add_attachment($attachments, $attachment_name, $attachment_type);
       }

if (EMAIL_USE_HTML == 'true') {
  $mimemessage->add_html($message, $text);
} else {
  $mimemessage->add_text($text);
}

$mimemessage->build_message();
while ($mail = tep_db_fetch_array($mail_query)) {
$mimemessage->send($mail['customers_firstname'] . ' ' . $mail['customers_lastname'], $mail['customers_email_address'], '', $from, $subject);
		      	                        }

tep_redirect(tep_href_link(FILENAME_MAIL, 'mail_sent_to=' . urlencode($mail_sent_to)));
		      	                        }


  if ( ($action == 'preview') && !isset($_POST['customers_email_address']) ) {
    $messageStack->add(ERROR_NO_CUSTOMER_SELECTED, 'error');
  }

  if (isset($_GET['mail_sent_to'])) {
    $messageStack->add(sprintf(NOTICE_EMAIL_SENT_TO, $_GET['mail_sent_to']), 'success');
  }
?>

<?php require(THEME . 'html/header.php'); ?>

<link href="<?php echo (($request_type == 'SSL') ? HTTPS_SERVER : HTTP_SERVER) . DIR_WS_ADMIN; ?>ckfinder/sample.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="<?php echo (($request_type == 'SSL') ? HTTPS_SERVER : HTTP_SERVER) . DIR_WS_ADMIN; ?>ckeditor/ckeditor.js"></script>
<script type="text/javascript" src="<?php echo (($request_type == 'SSL') ? HTTPS_SERVER : HTTP_SERVER) . DIR_WS_ADMIN; ?>ckfinder/ckfinder.js"></script>

<table border="0" width="100%" cellspacing="2" cellpadding="2">
  <tr>
    <td width="<?php echo BOX_WIDTH; ?>" valign="top"><table border="0" width="<?php echo BOX_WIDTH; ?>" cellspacing="1" cellpadding="1" class="columnLeft">
<!-- left_navigation //-->
<?php require(DIR_WS_INCLUDES . 'column_left.php'); ?>
<!-- left_navigation_eof //-->
    </table></td>
<!-- body_text //-->
    <td width="100%" valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="0">
      <tr>
        <td width="100%"><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo HEADING_TITLE; ?></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
<?php
  if ( ($action == 'preview') && isset($_POST['customers_email_address']) ) {
    switch ($_POST['customers_email_address']) {
// BOF Separate Pricing Per Customer
      case substr($_POST['customers_email_address'], 0, 3) == '***' :
         $email_all_array = explode('_', $_POST['customers_email_address']);
            if ($email_all_array[1] == '') { // all customers
        $mail_sent_to = TEXT_ALL_CUSTOMERS;
      } else {
        $mail_sent_to = TEXT_FOR_ALL . " " . $cg_array[(int)$email_all_array[2]]['customers_group_name'] . " " . TEXT_FOR_CUSTOMERS ;
      }
        break;
      case substr($_POST['customers_email_address'], 0, 3) == '**D':
      $email_all_array = explode('_', $_POST['customers_email_address']);
      if ($email_all_array[1] == '') { // all newsletter subscribers
        $mail_sent_to = TEXT_NEWSLETTER_CUSTOMERS;
      } else {
        $mail_sent_to = $cg_array[(int)$email_all_array[2]]['customers_group_name'] . " " . TEXT_FOR_CUSTOMERS . " " . TEXT_FOR_NEWSLETTER_SUBSCRIBERS ;
      }
// EOF Separate Pricing Per Customer
        break;
      default:
        $mail_sent_to = $_POST['customers_email_address'];
        break;
    }
?>
          <tr><?php echo tep_draw_form('mail', FILENAME_MAIL, 'action=send_email_to_user','post','enctype="multipart/form-data"'); ?>
            <td><table border="0" width="100%" cellpadding="0" cellspacing="2">
              <tr>
                <td><?php echo tep_draw_separator('pixel_trans.png', '1', '10'); ?></td>
              </tr>
              <tr>
                <td class="smallText"><b><?php echo TEXT_CUSTOMER; ?></b><br><?php echo $mail_sent_to; ?></td>
              </tr>
              <tr>
                <td><?php echo tep_draw_separator('pixel_trans.png', '1', '10'); ?></td>
              </tr>
              <tr>
                <td class="smallText"><b><?php echo TEXT_FROM; ?></b><br><?php echo htmlspecialchars(stripslashes($_POST['from'])); ?></td>
              </tr>
              <tr>
                <td><?php echo tep_draw_separator('pixel_trans.png', '1', '10'); ?></td>
              </tr>
              <tr>
                <td class="smallText"><b><?php echo TEXT_SUBJECT; ?></b><br><?php echo htmlspecialchars(stripslashes($_POST['subject'])); ?></td>
              </tr>
              <tr>
                <td><?php echo tep_draw_separator('pixel_trans.png', '1', '10'); ?></td>
              </tr>
              <tr>
                <td class="smallText"><b><?php echo TEXT_MESSAGE; ?></b><br><?php echo nl2br(htmlspecialchars(stripslashes($_POST['message']))); ?></td>
              </tr>
              <tr>
                <td><?php echo tep_draw_separator('pixel_trans.png', '1', '10'); ?></td>
              </tr>
			 <?php /* No funciona y por ahora lo evito que no tiene control virus etc @Israel.Gavino <tr>
	            <td class="smallText"><b><?php echo TEXT_ATTACHMENT; ?></b><br><input name="userfile" type="file"></td>
              </tr>*/?>
			  <tr>
				<td colspan="2"><?php echo tep_draw_separator('pixel_trans.png', '1', '10'); ?></td>
			  </tr>
              <tr>
                <td>
<?php
/* Re-Post all POST'ed variables */
	foreach($_POST as $key => $value) {
      if (!is_array($_POST[$key])) {
        echo tep_draw_hidden_field($key, htmlspecialchars(stripslashes($value)));
      }
    }
?>
                <table border="0" width="100%" cellpadding="0" cellspacing="2">
                  <tr>
                    <td><?php echo tep_image_submit('button_back.png', IMAGE_BACK, 'name="back"'); ?></td>
                    <td align="right"><?php echo '<a href="' . tep_href_link(FILENAME_MAIL) . '">' . tep_image_button('button_cancel.png', IMAGE_CANCEL) . '</a> ' . tep_image_submit('button_send_mail.png', IMAGE_SEND_EMAIL); ?></td>
                  </tr>
                </table></td>
              </tr>
            </table></td>
          </form></tr>
<?php
  } else {
?>
          <tr><?php echo tep_draw_form('mail', FILENAME_MAIL, 'action=preview'); ?>
            <td><table border="0" cellpadding="0" cellspacing="2">
              <tr>
                <td colspan="2"><?php echo tep_draw_separator('pixel_trans.png', '1', '10'); ?></td>
              </tr>
<?php
    $customers = array();
    $customers[] = array('id' => '', 'text' => TEXT_SELECT_CUSTOMER);
    $customers[] = array('id' => '***', 'text' => TEXT_ALL_CUSTOMERS);
    // BOF Separate Pricing Per Customer
    foreach ($cg_array as $id_and_name) {
		    $customers[] = array('id' => '***_cg_' . $id_and_name['id'], 'text' => TEXT_FOR_ALL . " " . $id_and_name['customers_group_name'] . " " . TEXT_FOR_CUSTOMERS);
    } // end foreach $cg_array as $id_and_name
    $customers[] = array('id' => '**D', 'text' => TEXT_NEWSLETTER_CUSTOMERS);
    foreach ($cg_array as $id_and_name) {
		    $customers[] = array('id' => '**D_cg_' . $id_and_name['id'], 'text' => TEXT_FOR_TO . " " . $id_and_name['customers_group_name'] . " " . TEXT_FOR_CUSTOMERS . " " . TEXT_FOR_NEWSLETTER_SUBSCRIBERS);
    } // end foreach $cg_array as $id_and_name
    // EOF Separate Pricing Per Customer
    $mail_query = tep_db_query("select customers_email_address, customers_firstname, customers_lastname from " . TABLE_CUSTOMERS . " order by customers_lastname");
    while($customers_values = tep_db_fetch_array($mail_query)) {
      $customers[] = array('id' => $customers_values['customers_email_address'],
                           'text' => $customers_values['customers_lastname'] . ', ' . $customers_values['customers_firstname'] . ' (' . $customers_values['customers_email_address'] . ')');
    }
?>
              <tr>
                <td class="main"><?php echo TEXT_CUSTOMER; ?></td>
                <td><?php echo tep_draw_pull_down_menu('customers_email_address', $customers, (isset($_GET['customer']) ? $_GET['customer'] : ''));?></td>
              </tr>
              <tr>
                <td colspan="2"><?php echo tep_draw_separator('pixel_trans.png', '1', '10'); ?></td>
              </tr>
              <tr>
                <td class="main"><?php echo TEXT_FROM; ?></td>
                <td><?php echo tep_draw_input_field('from', EMAIL_FROM); ?></td>
              </tr>
              <tr>
                <td colspan="2"><?php echo tep_draw_separator('pixel_trans.png', '1', '10'); ?></td>
              </tr>
              <tr>
                <td class="main"><?php echo TEXT_SUBJECT; ?></td>
                <td><?php echo tep_draw_input_field('subject'); ?></td>
              </tr>
              <tr>
                <td colspan="2"><?php echo tep_draw_separator('pixel_trans.png', '1', '10'); ?></td>
              </tr>
              <tr>
                <td valign="top" class="main"><?php echo TEXT_MESSAGE; ?></td>
                <td><?php echo tep_draw_textarea_field_tinymce('message', 'soft', '70', '15', ''); ?></td>
              </tr>
              <tr>
                <td colspan="2"><?php echo tep_draw_separator('pixel_trans.png', '1', '10'); ?></td>
              </tr>
              <tr>
                <td colspan="2" align="right"><?php echo tep_image_submit('button_send_mail.png', IMAGE_SEND_EMAIL); ?></td>
              </tr>
            </table></td>
          </form></tr>
<?php
  }
?>
<!-- body_text_eof //-->
        </table></td>
      </tr>
    </table></td>
  </tr>
</table>

<?php require(THEME . 'html/footer.php'); ?>

<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
