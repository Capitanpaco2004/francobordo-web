<?php
require('includes/application_top.php');

// Si no estamos logueados
if (!$customerCore->hasLogin()) {
	tep_redirect(tep_href_link('account/account.php'));
}

  require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_LOGOFF);

  $breadcrumb->add(NAVBAR_TITLE);

  $customerCore->logoff();

  $cart->reset();
?>

  <?php require(DIR_THEME. 'html/header.php'); ?>

<?php require(DIR_THEME. 'html/column_left.php'); ?>

                <h1 class="pageHeading"><?php echo HEADING_TITLE; ?></h1>
<p><?php echo TEXT_MAIN; ?></p>
<div class="botonera"><?php echo '<a href="' . tep_href_link(FILENAME_DEFAULT) . '">' . tep_image_button('button_continue.gif', IMAGE_BUTTON_CONTINUE) . '</a>'; ?></div>

<?php require(DIR_THEME. 'html/column_right.php'); ?>

<!-- body_eof //-->

<!-- footer //-->
<?php require(DIR_THEME. 'html/footer.php'); ?>
<!-- footer_eof //-->

<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
