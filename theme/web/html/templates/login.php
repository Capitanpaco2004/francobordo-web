<?php require(DIR_THEME . 'html/header.php'); ?>
<?php require(DIR_THEME . 'html/column_left.php'); ?>

<?php echo tep_draw_form('login', tep_href_link(FILENAME_LOGIN, 'action=process'), 'post', 'class="xform"'); ?>
	<h1 class="pageHeading"><?php echo HEADING_TITLE; ?></h1>
	<?php echo $messageStack->show('login'); ?>

	<div class="dx tx row dflex tflex" id="login">
		<div class="col t12 m12">
			<h4><?php echo HEADING_NEW_CUSTOMER; ?></h4>
			<p><i><?php echo TEXT_NEW_CUSTOMER_INTRODUCTION; ?></i></p>
			<div class="botonera">
				<?php echo '<a href="' . tep_href_link(FILENAME_CREATE_ACCOUNT, '') . '">' . tep_image_button('button_continue.gif', IMAGE_BUTTON_CONTINUE) . '</a>'; ?>
			</div>
		</div>

		<div class="dfixed tfixed sepa m12 mfixed col"></div>

		<div class="col t12 m12">
			<h4><?php echo HEADING_RETURNING_CUSTOMER; ?></h4>
			<div class="campo"><?php echo tep_draw_input_field('email_address', '', 'placeholder="' . ENTRY_EMAIL_ADDRESS . '"'); ?></div>
			<div class="campo"><?php echo tep_draw_password_field('password', '', 'placeholder="' . ENTRY_PASSWORD . '"'); ?></div>

			<a href="password_forgotten.php" title="Recuperar Contrase?a">¿Olvidó su contraseña?</a>
			<div class="botonera">
				<?php echo tep_image_submit('button_login.gif', IMAGE_BUTTON_LOGIN); ?>
			</div>
		</div>
	</div>

</form>

<?php require(DIR_THEME . 'html/column_right.php'); ?>
<?php require(DIR_THEME . 'html/footer.php'); ?>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
