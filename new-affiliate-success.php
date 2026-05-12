<?php
/*
  $Id: new-affiliate-success.php 1739 2007-12-20 00:52:16Z hpdl $
  #XCC-313-91043
  @author Daniel Lucia <daniel.lucia@denox.es>

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
*/

  require('includes/application_top.php');

  require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_CREATE_AFFILIATE_SUCCESS);

  $breadcrumb->add(NAVBAR_TITLE_1);
  $breadcrumb->add(NAVBAR_TITLE_2);

  if (sizeof($navigation->snapshot) > 0) {
    $origin_href = tep_href_link($navigation->snapshot['page'], tep_array_to_string($navigation->snapshot['get'], array(tep_session_name())), $navigation->snapshot['mode']);
    $navigation->clear_snapshot();
  } else {
    $origin_href = tep_href_link(FILENAME_DEFAULT);
  }
?>

<?php require(DIR_THEME. 'html/header.php'); ?>
<?php require(DIR_THEME. 'html/column_left.php'); ?>

<h1 class="pageHeading"><?php echo HEADING_TITLE; ?></h1>
<p><?php echo TEXT_ACCOUNT_CREATED; ?></p>
<?php if(isset($_GET['exist-account'])) : ?>
  <p style="margin-top: 20px"><strong><?php echo TEXT_ACCOUNT_CREATED_EXISTS; ?></strong></p>
<?php endif; ?>
</tr>
<!-- Points/Rewards Module V2.1rc2a bof-->
<?php
   if ((USE_POINTS_SYSTEM == 'true') && (NEW_SIGNUP_POINT_AMOUNT > 0)) {
?>
<p class="informacion"><?php echo sprintf(TEXT_WELCOME_POINTS_TITLE, '<a href="' . tep_href_link(FILENAME_MY_POINTS, '', 'SSL') . '" title="' . TEXT_POINTS_BALANCE . '">' . TEXT_POINTS_BALANCE . '</a>', number_format(NEW_SIGNUP_POINT_AMOUNT,POINTS_DECIMAL_PLACES), $currencies->format(tep_calc_shopping_pvalue(NEW_SIGNUP_POINT_AMOUNT))); ?>.</p>
<p class="informacion"><?php echo sprintf(TEXT_WELCOME_POINTS_LINK, '<a href="' . tep_href_link(FILENAME_MY_POINTS_HELP,'faq_item=13', 'NONSSL') . '" title="' . BOX_INFORMATION_MY_POINTS_HELP . '">' . BOX_INFORMATION_MY_POINTS_HELP . '</a>'); ?></p>
<?php
   }
?>
<!-- Points/Rewards Module V2.1rc2a eof-->
<div class="botonera">
<?php echo '<a href="' . $origin_href . '">' . tep_image_button('button_continue.gif', IMAGE_BUTTON_CONTINUE) . '</a>'; ?>
</div>
<!-- body_text_eof //-->

<!-- right_navigation //-->
<?php require(DIR_THEME. 'html/column_right.php'); ?>
<!-- right_navigation_eof //-->
<!-- body_eof //-->

<!-- footer //-->
<?php require(DIR_THEME. 'html/footer.php'); ?>
<!-- footer_eof //-->
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
