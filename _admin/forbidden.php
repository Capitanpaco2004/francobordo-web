<?php require('includes/application_top.php'); ?>
<?php require(THEME . 'html/header.php'); ?>

	<div class="pageHeading"><?php echo HEADING_TITLE; ?></div>
<?php
if(isset($_GET['extraerror']) && $_GET['extraerror'] == "submodule" ){
	echo '<div class="msje msje-eror" style="width: 50%; margin: 0 auto;"><div class="msje-icon"></div>Los submodulos de permisos no estan bien configurados, por favor comuniqueselo a un administrador<br><br></div>';
}
?>
    <div class="msje msje-eror" style="width: 50%; margin: 0 auto;"><div class="msje-icon"></div><?php echo TEXT_MAIN; ?><br><br><?php echo '<a href="' . tep_href_link(FILENAME_DEFAULT) . '">' . tep_image_button('button_back.png', IMAGE_BACK) . '</a>'; ?></div>

<?php require(THEME . 'html/footer.php'); ?>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
