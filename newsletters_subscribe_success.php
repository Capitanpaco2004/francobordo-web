<?php
  require('includes/application_top.php');
  require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_NEWSLETTERS);
  $location = ' &raquo; <a href="' . tep_href_link(FILENAME_NEWSLETTERS_SUBSCRIBE_SUCCESS, '', 'NONSSL') . '" class="headerNavigation">' . NAVBAR_TITLE . '</a>';
?>
<?php require(DIR_THEME. 'content/html/header.php'); ?>
<?php require(DIR_THEME. 'content/html/column_left.php'); ?>
<div align="center">
<h1 class="pageHeading"><?php echo mb_convert_encoding(HEADING_TITLE ?? '', 'UTF-8', 'ISO-8859-1'); ?></h1>
	<p class="main"><?php echo mb_convert_encoding(TEXT_INFORMATION ?? '', 'UTF-8', 'ISO-8859-1'); ?></p>
	<p align="right" class="main"><?php echo '<a href="' . tep_href_link(FILENAME_DEFAULT, '', 'NONSSL') . '">' . tep_image_button('button_continue.gif', IMAGE_BUTTON_CONTINUE) . '</a>'; ?></p>
</div>     
</form>
<?php require(DIR_THEME. 'content/html/column_right.php'); ?>

<!-- body_eof //-->

<!-- footer //-->
<?php require(DIR_THEME. 'content/html/footer.php'); ?>
<!-- footer_eof //-->

</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>