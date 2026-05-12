<?php
	// Aplicacion
	include( 'includes/application.php' );
	
	// Idioma
	require( DIR_WS_LANGUAGES . $language . '/account_delete_success.php' );
	
	// Eliminar cuenta
	if( array_key_exists( 'delete', $_GET ) && $_GET['delete'] = 'true' )
	{
		if (!$customerCore->hasLogin()) {
			tep_redirect(tep_href_link(FILENAME_LOGIN, '', 'SSL'));
		}

		$customers_id = tep_db_prepare_input( $customer_id );

		// Eliminar la cuenta
		echo $rgpd->accountDeleteExecute();
		
		// Eliminamos
		tep_db_query( 'delete from ' . TABLE_ADDRESS_BOOK . ' where customers_id = "' . tep_db_input($customers_id) . '"' );
		tep_db_query( 'delete from ' . TABLE_CUSTOMERS . ' where customers_id = "' . tep_db_input($customers_id) . '"' );
		tep_db_query( 'delete from ' . TABLE_CUSTOMERS_INFO . ' where customers_info_id = "' . tep_db_input($customers_id) . '"' );
		tep_db_query( 'delete from ' . TABLE_CUSTOMERS_BASKET . ' where customers_id = "' . tep_db_input($customers_id) . '"' );
		tep_db_query( 'delete from ' . TABLE_CUSTOMERS_BASKET_ATTRIBUTES . ' where customers_id = "' . tep_db_input($customers_id) . '"' );
		tep_db_query( 'delete from ' . TABLE_WHOS_ONLINE . ' where customer_id = "' . tep_db_input($customers_id) . '"' );
		
		/**
		 * #XCC-313-91043
		 * @author Daniel Lucia <daniel.lucia@denox.es>
		 */
		$sql = sprintf(
	            'DELETE FROM affiliates WHERE customers_id = %d',
	            $customers_id
	        );
	        tep_db_query($sql);
	
		$customerCore->logoff();

		$cart->reset();

		// Redirect
		tep_redirect( tep_href_link( FILENAME_ACCOUNT_DELETE, 'delete_confirm_account=true', 'SSL' ) );
	}
	
	// Breadcrumb
	$breadcrumb->add(NAVBAR_TITLE_2, tep_href_link(FILENAME_ADDRESS_BOOK, '', 'SSL'));

	// Header
	include( 'account/includes/header.php' );
	
	// Mensaje cuenta eliminada
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
			echo $rgpd->accountDeleteShowText();
		echo '</div>';
	}

	// Footer
	include( 'account/includes/footer.php' );	
?>