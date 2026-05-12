<?php
	// Aplicacion
	include( 'includes/application.php' );
	
	// Desactivamos cuenta
	if( array_key_exists( 'delete', $_GET ) && $_GET['delete'] = 'true' )
	{
		if (!$customerCore->hasLogin()) { 
			tep_redirect(tep_href_link(FILENAME_LOGIN, '', 'SSL'));
		}

		$customers_id = tep_db_prepare_input( $customer_id );

		// Desactivamos la cuenta
		echo $rgpd->accountDisableExecute();

		// Sessiones
		$customerCore->logoff(); 
		$cart->reset();
 
		// Redirect
		tep_redirect( tep_href_link( 'account_disable.php', 'delete_confirm_account=true', 'SSL' ) );
	}
	
	// Breadcrumb
	$breadcrumb->add(NAVBAR_TITLE_2, tep_href_link(FILENAME_ADDRESS_BOOK, '', 'SSL'));

	// Header
	include( 'account/includes/header.php' );
	
	// Mensaje cuenta desactivada
	if( array_key_exists( 'delete_confirm_account', $_GET ) && $_GET['delete_confirm_account'] = 'true' )
	{
		echo '<div class="ccTitle">' . NAVBAR_TITLE_2 . '</div>';
		echo '<div class="ccCnt">';
			echo $messageStack->show( array( 'text' => TEXT_MAIN, 'class' => 'crrt' ) );
			echo '<div class="tright" style="margin-top: 10px;">
				<a href="' . tep_href_link( FILENAME_DEFAULT, '', 'SSL' ) . '" class="button small verde"><i class="fa fa-check"></i> ' . IMAGE_BUTTON_CONTINUE . '</a> 
			</div>';
		echo '</div>';
	}
	else // Mensaje eliminar cuenta
	{
		echo '<div class="ccTitle">' . NAVBAR_TITLE_2 . '</div>';
		echo '<div class="ccCnt">';
			// Mensaje para eliminar la cuenta
			echo $rgpd->accountDeleteShowText( false );
		echo '</div>';
	}

	// Footer
	include( 'account/includes/footer.php' );	
?>