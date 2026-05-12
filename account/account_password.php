<?php
	// Aplicacion
	include( 'includes/application.php' );

	// Cambiamos estilo
	$messageStack->style = 'solenopsis';
	
	// Si nos envian el formulario
	if( $_SERVER['REQUEST_METHOD'] == 'POST' )
	{
		// Variables
		$password_current = tep_db_prepare_input( $_POST['password_current'] );
		$password_new = tep_db_prepare_input( $_POST['password_new'] );
		$password_confirmation = tep_db_prepare_input( $_POST['password_confirmation'] );

		if( strlen($password_current) < ENTRY_PASSWORD_MIN_LENGTH )
			$messageStack->add( 'account_password', ENTRY_PASSWORD_CURRENT_ERROR, 'error', true);

		if( strlen($password_new) < ENTRY_PASSWORD_MIN_LENGTH )
		  $messageStack->add( 'account_password', ENTRY_PASSWORD_NEW_ERROR, 'error', true);

		if( $password_new != $password_confirmation )
			$messageStack->add( 'account_password', ENTRY_PASSWORD_NEW_ERROR_NOT_MATCHING, 'error', true);

		// Si no contenemos errores
		if( $messageStack->check('account_password') == false )
		{
			if( $customerCore->checkPassword($password_current) )
			{
				$customerCore->setPassword($password_new);

				// Mensaje y redireccion
				$messageStack->addSession( 'account_password', SUCCESS_PASSWORD_UPDATED, 'success' );
				tep_redirect(tep_href_link(FILENAME_ACCOUNT_PASSWORD, '', 'SSL'));
			}
			else
				$messageStack->add( 'account_password', ERROR_CURRENT_PASSWORD_NOT_MATCHING, 'error', true );
		}
	}

	// Breadcrumb
	$breadcrumb->add(NAVBAR_TITLE_2, tep_href_link(FILENAME_ACCOUNT_PASSWORD, '', 'SSL'));

	// Header
	include( 'account/includes/header.php' );

	// Titulo
	echo '<div class="ccTitle">' . MY_PASSWORD_TITLE . '<em class="trojo fright cclink">' . FORM_REQUIRED_INFORMATION . '</em></div>';

	// Mensajes
	echo $messageStack->show( 'account_password' );

	// Formulario
	echo '<form class="rows dx xform Sp10 amiddle ccFormValid" name="account_edit" action="' . tep_href_link( FILENAME_ACCOUNT_PASSWORD, '', 'SSL' ) . '" method="post" data-parsley-validate="">';	
		// Password actual
		echo '<label class="column d03 m12 tright" for="password_current"><span class="trojo">*</span> ' . ENTRY_PASSWORD_CURRENT . '</label>';
		echo '<div class="column d09 m12">';
			echo tep_draw_input_field( 'password_current', '', 'required="" data-parsley-minlength="' . ENTRY_PASSWORD_MIN_LENGTH . '" data-parsley-trigger="change"', false, 'password' );
			echo (defined( 'ENTRY_PASSWORD_CURRENT_TEXT' ) && ENTRY_PASSWORD_CURRENT_TEXT != '*' && ENTRY_PASSWORD_CURRENT_TEXT != '' ) ? '<div class="DFhelp">' . ENTRY_PASSWORD_CURRENT_TEXT . '</div>' : '';
		echo '</div>';

		// Password nuevo
		echo '<label class="column d03 m12 tright" for="password_new"><span class="trojo">*</span> ' . ENTRY_PASSWORD_NEW . '</label>';
		echo '<div class="column d09 m12">';
			echo tep_draw_input_field( 'password_new', '', 'required="" data-parsley-minlength="' . ENTRY_PASSWORD_MIN_LENGTH . '" data-parsley-trigger="change"', false, 'password' );
			echo (defined( 'ENTRY_PASSWORD_NEW_TEXT' ) && ENTRY_PASSWORD_NEW_TEXT != '*' && ENTRY_PASSWORD_NEW_TEXT != '' ) ? '<div class="DFhelp">' . ENTRY_PASSWORD_NEW_TEXT . '</div>' : '';
		echo '</div>';

		// Password nuevo repetir
		echo '<label class="column d03 m12 tright" for="password_confirmation"><span class="trojo">*</span> ' . ENTRY_PASSWORD_CONFIRMATION . '</label>';
		echo '<div class="column d09 m12">';
			echo tep_draw_input_field( 'password_confirmation', '', 'required="" data-parsley-minlength="' . ENTRY_PASSWORD_MIN_LENGTH . '" data-parsley-trigger="change"', false, 'password' );
			echo (defined( 'ENTRY_PASSWORD_CONFIRMATION_TEXT' ) && ENTRY_PASSWORD_CONFIRMATION_TEXT != '*' && ENTRY_PASSWORD_CONFIRMATION_TEXT != '' ) ? '<div class="DFhelp">' . ENTRY_PASSWORD_CONFIRMATION_TEXT . '</div>' : '';
		echo '</div>';

		// Submit
		echo '<div class="column d12 tright">';
			echo '<a href="' . tep_href_link( FILENAME_ACCOUNT, '', 'SSL' ) . '" class="button ccbutton">' . IMAGE_BUTTON_BACK . '</a> ';
			echo '<input class="button ccbutton verde" type="submit" value="' . IMAGE_BUTTON_UPDATE . '">';
		echo '</div>';
	echo '</form>';

	// Footer
	include( 'account/includes/footer.php' );
?>
