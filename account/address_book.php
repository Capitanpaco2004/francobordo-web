<?php
	// Aplicacion
	include( 'includes/application.php' );

	// Breadcrumb
	$breadcrumb->add(NAVBAR_TITLE_2, tep_href_link(FILENAME_ADDRESS_BOOK, '', 'SSL'));

	// Header
	include( 'account/includes/header.php' );

	// Dirección  principal
	echo '<div class="ccTitle">' . PRIMARY_ADDRESS_TITLE . '</div>';
	echo '<div class="ccCnt">';
		// Mensajes
		echo $messageStack->show( 'addressbook' );
		echo $messageStack->show( array( 'text' => strip_tags( PRIMARY_ADDRESS_DESCRIPTION ), 'class' => 'wrng' ) );

		$aDatos = tep_db_query( 'select c.name as city, entry_city_id, address_book_id, entry_firstname as firstname, entry_lastname as lastname, entry_telephone as telephone, entry_company as company, entry_nif as nif, entry_street_address as street_address, entry_suburb as suburb, entry_city as city, entry_postcode as postcode, entry_state as state, entry_zone_id as zone_id, entry_country_id as country_id
								from ' . TABLE_ADDRESS_BOOK . ' a
								LEFT JOIN cities c ON c.id = a.entry_city_id
								where customers_id = "' . (int)$customer_id . '" and address_book_id = "' . (int)$customer_default_address_id . '"
								order by firstname, lastname' );
		$aDato = tep_db_fetch_array( $aDatos );
		
		echo '<div class="ccCnt ccInfo3">';
			echo '<div class="column">';
				$format_id = tep_get_address_format_id( $aDato['country_id'] );
				
				echo '<strong>';
						echo '<i class="fa fa-star"></i>';

					echo tep_output_string_protected($aDato['firstname'] . ' ' . $aDato['lastname']);
				echo '</strong>';
				
				echo '<div class="ccText">' . tep_address_format( $format_id, $aDato, true, ' ', ' ' ) . '</div>';
				
				echo '<a class="button small" style="text-transform: uppercase; margin-bottom: 0px;" href="' . tep_href_link(FILENAME_ADDRESS_BOOK_PROCESS, 'edit=' . $aDato['address_book_id'], 'SSL') . '">' . SMALL_IMAGE_BUTTON_VIEW . '</a>';
			echo '</div>';
		echo '</div>';
	echo '</div>';

	// Direcciones
	echo '<div class="ccTitle">' . ADDRESS_BOOK_TITLE . '</div>';

	// Consulta
	$aDatos = tep_db_query( 'select c.name as city, entry_city_id, address_book_id, entry_firstname as firstname, entry_lastname as lastname, entry_telephone as telephone, entry_company as company, entry_nif as nif, entry_street_address as street_address, entry_suburb as suburb, entry_city as city, entry_postcode as postcode, entry_state as state, entry_zone_id as zone_id, entry_country_id as country_id
							from ' . TABLE_ADDRESS_BOOK . ' a
							LEFT JOIN cities c ON c.id = a.entry_city_id
							where customers_id = "' . (int)$customer_id . '"
							order by firstname, lastname' );

	echo $messageStack->show( array( 'text' => sprintf(TEXT_MAXIMUM_ENTRIES, MAX_ADDRESS_BOOK_ENTRIES), 'class' => 'wrng' ) );
	echo '<div class="ccCnt ccInfo3">';
		while( $aDato = tep_db_fetch_array( $aDatos ) )
		{
			echo '<div class="column delete">';
				$format_id = tep_get_address_format_id( $aDato['country_id'] );
				
				echo '<strong>';
					if( $aDato['address_book_id'] == $customer_default_address_id )
						echo '<i class="fa fa-star"></i>';

					echo tep_output_string_protected($aDato['firstname'] . ' ' . $aDato['lastname']);
				echo '</strong>';
				
				echo '<div class="ccText">' . tep_address_format( $format_id, $aDato, true, ' ', ' ' ) . '</div>';

				echo '<a href="' . tep_href_link(FILENAME_ADDRESS_BOOK_PROCESS, 'delete=' . $aDato['address_book_id'], 'SSL') . '" class="button small rojo tblanco"><i class="fa fa-trash"></i> ' . SMALL_IMAGE_BUTTON_DELETE . '</a>';				
				echo '<a class="button small" style="text-transform: uppercase; margin-bottom: 0px;" href="' . tep_href_link(FILENAME_ADDRESS_BOOK_PROCESS, 'edit=' . $aDato['address_book_id'], 'SSL') . '">' . SMALL_IMAGE_BUTTON_VIEW . '</a>';
			echo '</div>';
		}

		// Boton añadir dirección
		if( tep_count_customer_address_book_entries() < MAX_ADDRESS_BOOK_ENTRIES )
			echo '<div class="tleft"><a href="' . tep_href_link( FILENAME_ADDRESS_BOOK_PROCESS, '', 'SSL' ) . '" class="ccaddadr"><i>+</i> ' . IMAGE_BUTTON_ADD_ADDRESS . '</a></div>';
	echo '</div>';

	// Footer
	include( 'account/includes/footer.php' );
?>
