<?php
require('includes/application_top.php');
require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_NEWSLETTERS);
$email_to_unsubscribe=$_GET['email'];

$breadcrumb->add(NAVBAR_TITLE, tep_href_link(FILENAME_NEWSLETTERS, '', 'NONSSL'));
$cus_subscribe_raw = "SELECT 1 FROM subscribers WHERE customers_newsletter = '1' AND subscribers_email_address = '" . $email_to_unsubscribe . "'";
$cus_subscribe_query = tep_db_query($cus_subscribe_raw);
$cus_subscribe = tep_db_fetch_array($cus_subscribe_query);
?>
<?php require(DIR_THEME. 'content/html/header.php'); ?>
<?php require(DIR_THEME. 'content/html/column_left.php'); ?>
<div align="center">
<h1 class="pageHeading"><?php echo mb_convert_encoding(HEADING_TITLE ?? '', 'UTF-8', 'ISO-8859-1'); ?></h1>
<?php
            // If we found the customers email address, and they currently subscribe
            if ($cus_subscribe) {
              // Unsubscribe them
              tep_db_query("UPDATE customers SET customers_newsletter = '0' WHERE customers_email_address = '" .$email_to_unsubscribe . "'");
              tep_db_query("UPDATE subscribers SET customers_newsletter = '0' WHERE subscribers_email_address = '" .$email_to_unsubscribe . "'");
//              tep_db_query("DELETE from subscribers WHERE subscribers_email_address = '" .$email_to_unsubscribe . "'");

           ?>
           <?php echo UNSUBSCRIBE_DONE_TEXT_INFORMATION . $email_to_unsubscribe; ?>
           <?php 
             // Otherwise, we want to display an error message (This should never occur, unless they try to unsubscribe twice)
             } else {
           ?>
               <?php echo UNSUBSCRIBE_ERROR_INFORMATION . $email_to_unsubscribe; ?>
           <?php
             }
           ?>
</div> <?php require(DIR_THEME. 'content/html/column_right.php'); ?>

<!-- body_eof //-->

<!-- footer //-->
<?php require(DIR_THEME. 'content/html/footer.php'); ?>
<!-- footer_eof //-->

</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>