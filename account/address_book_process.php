<?php
	// Aplicacion
	include( 'includes/application.php' );

	// Cambiamos estilo
	$messageStack->style = 'solenopsis';
	
	// Breadcrumb
	$breadcrumb->add( NAVBAR_TITLE_2, tep_href_link( FILENAME_ADDRESS_BOOK, '', 'SSL' ) );

	// Variables
	$sHtmlAccount = '';
	$sGetDelete = array_key_exists( 'delete', $_GET ) && is_numeric( $_GET['delete'] ) ? (int)$_GET['delete'] : false;
	$sGetEdit = array_key_exists( 'edit', $_GET ) && is_numeric( $_GET['edit'] ) ? (int)$_GET['edit'] : false;
	$sPostAction = array_key_exists( 'action', $_POST ) ? tep_db_prepare_input( $_POST['action'] ) : false;

	// Combobox ajax de ciudades
	if( $sPostAction == 'getStates' )
	{
		echo trim( preg_replace( '/\*$|\&nbsp\;/i', '', strip_tags( ajax_get_zones_html2( tep_db_prepare_input( $_POST['country'] ), true, false ), '<input><select><option>' ) ) );

		// Detenemos
		exit();
	}

	// Si nos envian el formulario
	if( $_SERVER['REQUEST_METHOD'] == 'POST' )
	{
		// Variables
		$gender = ACCOUNT_GENDER == 'true' ? tep_db_prepare_input($_POST['gender']) : '';
		$company = ACCOUNT_COMPANY == 'true' ? tep_db_prepare_input($_POST['company']) : '';
		$nif = ACCOUNT_NIF == 'true' ? tep_db_prepare_input($_POST['nif'])  : '';
		$suburb = ACCOUNT_SUBURB == 'true' ? tep_db_prepare_input($_POST['suburb'])  : '';
		$firstname = tep_db_prepare_input($_POST['firstname']);
		$lastname = tep_db_prepare_input($_POST['lastname']);
		$telephone = tep_db_prepare_input($_POST['telephone']);
		$street_address = tep_db_prepare_input($_POST['street_address']);
		$postcode = tep_db_prepare_input($_POST['postcode']);
		$city = tep_db_prepare_input($_POST['city']);
		$country = tep_db_prepare_input($_POST['country']);
		$zone_id = ACCOUNT_STATE == 'true' && isset($_POST['zone_id']) ? tep_db_prepare_input($_POST['zone_id']) : false;
		$state = tep_db_prepare_input($_POST['state']);
		$city_id = (int)tep_db_prepare_input($_POST['city_id']);
		$sReferer = tep_db_prepare_input(isset($_POST['referer']) ? $_POST['referer'] : '');

		// Sexo
		if( ACCOUNT_GENDER == 'true' && $gender != 'm' && $gender != 'f' )
			$messageStack->add('addressbook', ENTRY_GENDER_ERROR, 'error', true);

		// DNI
		if( ACCOUNT_NIF == 'true' )
		{
			if( $nif == "" && ACCOUNT_NIF_REQ == 'true' )
				$messageStack->add('addressbook', ENTRY_NO_NIF_ERROR, 'error', true);
			else if( strlen($nif) < 5 && $nif != "" )
				$messageStack->add('addressbook', ENTRY_FORMATO_NIF_ERROR, 'error', true);
		}

		// Nombre
		if( strlen( $firstname ) < ENTRY_FIRST_NAME_MIN_LENGTH )
			$messageStack->add('addressbook', ENTRY_FIRST_NAME_ERROR, 'error', true);

		// Apellidos
		if( strlen($lastname) < ENTRY_LAST_NAME_MIN_LENGTH )
			$messageStack->add('addressbook', ENTRY_LAST_NAME_ERROR, 'error', true);

		if (strlen($telephone) < ENTRY_TELEPHONE_MIN_LENGTH)
			$messageStack->add('addressbook', ENTRY_TELEPHONE_NUMBER_ERROR, 'error', true);

		// Dirección
		if( strlen( $street_address ) < ENTRY_STREET_ADDRESS_MIN_LENGTH )
			$messageStack->add('addressbook', ENTRY_STREET_ADDRESS_ERROR, 'error', true);

		// Codigo postal
		if( strlen( $postcode ) < ENTRY_POSTCODE_MIN_LENGTH )
			$messageStack->add('addressbook', ENTRY_POST_CODE_ERROR, 'error', true);

		// Pais
		if( !is_numeric($country) )
			$messageStack->add('addressbook', ENTRY_COUNTRY_ERROR, 'error', true);

		// Provincia
		if( ACCOUNT_STATE == 'true' && $zone_id == 0 && strlen($state) < ENTRY_STATE_MIN_LENGTH )
			$messageStack->add('addressbook', ENTRY_STATE_ERROR, 'error', true);

		// Ciudad
		if ($city_id == 0 && $city == '') {
			$error = true;
	        $messageStack->add('addressbook', ENTRY_CITY_ID_ERROR);
		}
		// Si no contenemos errores
		if( $messageStack->check('addressbook') == false )
		{
			$sql_data_array = array( 'entry_firstname' => $firstname,
									 'entry_lastname' => $lastname,
									 'entry_telephone' => $telephone,
									 'entry_street_address' => $street_address,
									 'entry_postcode' => $postcode,
									 'entry_city' => $city,
									 'entry_country_id' => (int)$country,
								 	'entry_city_id' => $city_id );

			if( ACCOUNT_GENDER == 'true' )
				$sql_data_array['entry_gender'] = $gender;

			if( ACCOUNT_COMPANY == 'true' )
				$sql_data_array['entry_company'] = $company;

			if( ACCOUNT_NIF == 'true' )
				$sql_data_array['entry_nif'] = $nif;

			if( ACCOUNT_SUBURB == 'true' )
				$sql_data_array['entry_suburb'] = $suburb;

			if( ACCOUNT_STATE == 'true' )
			{
				if( $zone_id > 0 )
				{
					$sql_data_array['entry_zone_id'] = (int)$zone_id;
					$sql_data_array['entry_state'] = '';
				}
				else
				{
					$sql_data_array['entry_zone_id'] = '0';
					$sql_data_array['entry_state'] = $state;
				}
			}

			// Editar
			if( $sGetEdit !== false )
			{
				// Comprobamos si existe
				$aDatos = tep_db_query( 'select count(*) as total
										 from ' . TABLE_ADDRESS_BOOK . '
										 where address_book_id = "' . $sGetEdit . '" and customers_id = "' . (int)$customer_id . '"' );
				$aDato = tep_db_fetch_array( $aDatos );

				// Si no existe
				if( $aDato['total'] < 1 )
				{
					$messageStack->addSession( 'addressbook', ERROR_NONEXISTING_ADDRESS_BOOK_ENTRY );
					tep_redirect( tep_href_link( FILENAME_ADDRESS_BOOK, '', 'SSL' ) );
				}

				// Actualizamos
				tep_db_perform( TABLE_ADDRESS_BOOK, $sql_data_array, 'update', 'address_book_id = "' . $sGetEdit . '" and customers_id = "' . (int)$customer_id . '"' );

				// Quito la session del impuesto por si cambia que se recalcule
				tep_session_unregister( 'tax_rate' );

				// Reregister session variables
				if( (isset($_POST['primary']) && ($_POST['primary'] == 'on')) || ($sGetEdit == $customer_default_address_id) )
				{
					$customer_first_name = $firstname;
					$customer_country_id = $country;
					$customer_zone_id = (($zone_id > 0) ? (int)$zone_id : '0');
					$customer_default_address_id = $sGetEdit;

					$sql_data_array = array('customers_firstname' => $firstname,
											'customers_lastname' => $lastname,
											'customers_default_address_id' => $sGetEdit );

					if( ACCOUNT_GENDER == 'true' )
						$sql_data_array['customers_gender'] = $gender;

					// Actualizamos
					tep_db_perform( TABLE_CUSTOMERS, $sql_data_array, 'update', "customers_id = '" . (int)$customer_id . "'" );

					// Modificamos en sesión la dirección por defecto
					if( isset( $_POST['primary'] ) && $_POST['primary'] == 'on' )
						$_SESSION['sendto'] = (int)$sGetEdit;
				}

				// Mensaje
				$messageStack->addSession( 'addressbook', SUCCESS_ADDRESS_BOOK_ENTRY_UPDATED, 'success' );
			}
			else
			{
				// Comprobamos el maximo de direcciones
				if( tep_count_customer_address_book_entries() < MAX_ADDRESS_BOOK_ENTRIES)
				{
					$sql_data_array['customers_id'] = (int)$customer_id;

					// Insertamos
					tep_db_perform( TABLE_ADDRESS_BOOK, $sql_data_array );

					$new_address_book_id = tep_db_insert_id();

					// Si estabamos en el checkout
					if ($sReferer != '' && preg_match('/\/checkout\//', $sReferer)) {
						if (!tep_session_is_registered('sendto')){
							tep_session_register('sendto');
						}

						if (tep_session_is_registered('shipping')) {
							tep_session_unregister('shipping');
						}

						$sendto = $new_address_book_id;
					}

					// Reregister session variables
					if( isset($_POST['primary']) && ($_POST['primary'] == 'on'))
					{
						$customer_first_name = $firstname;
						$customer_country_id = $country;
						$customer_zone_id = $zone_id > 0 ? (int)$zone_id : '0';
						$customer_default_address_id = ( isset($_POST['primary'] ) && $_POST['primary'] == 'on') ? $new_address_book_id : $customer_default_address_id;
						$sql_data_array = array( 'customers_firstname' => $firstname, 'customers_lastname' => $lastname );

						if( ACCOUNT_GENDER == 'true' )
							$sql_data_array['customers_gender'] = $gender;

						if( isset($_POST['primary']) && ($_POST['primary'] == 'on') )
							$sql_data_array['customers_default_address_id'] = $new_address_book_id;

						// Actualizamos
						tep_db_perform( TABLE_CUSTOMERS, $sql_data_array, 'update', "customers_id = '" . (int)$customer_id . "'" );

						// Modificamos en sesión la dirección por defecto
						if( isset( $_POST['primary'] ) && $_POST['primary'] == 'on' )
							$_SESSION['sendto'] = $new_address_book_id;

						// Mensaje
						$messageStack->addSession( 'addressbook', SUCCESS_ADDRESS_BOOK_ENTRY_UPDATED, 'success' );
					}
				}
			}

			// Redireccionamos
			tep_redirect( $sReferer != '' ? $sReferer : tep_href_link( FILENAME_ADDRESS_BOOK, '' ) );
		}
	}

	// Funcion que pinta el formulario
	function formAddressBook($sAction, $aDato)
	{
		// Variables
		global $customer_default_address_id, $sGetEdit, $messageStack;
		$sReferer = tep_db_prepare_input(isset($_POST['referer']) ? $_POST['referer'] : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''));
		$sHtml = '';

		if( array_key_exists( 'zone_id', $_POST ) )
			$aDato['entry_zone_id'] = $_POST['zone_id'];
		
		// Mensajes
		$sHtml .= $messageStack->show( 'addressbook' );

		// Formulario
		$sHtml .= '<form class="rows dx xform Sp10 amiddle ccFormValid" name="addressbook" action="' . $sAction . '" method="post" data-parsley-validate="">';
			// Referer, por si se llama editar o crear direcciones poder volver a ese sitio una vez actualizado
			if ($sReferer != '' && preg_match('/\/checkout\//', $sReferer)) {
				$sHtml .= '<input type="hidden" name="referer" value="' . $sReferer . '"/>';
			}

			// Sexo
			if( ACCOUNT_GENDER == 'true' )
			{
				$male = $female = false;

				if( isset($gender) )
					$male = $gender == 'm' ? true : false;
				elseif( isset( $aDato['entry_gender'] ) )
					$male = $aDato['entry_gender'] == 'm' ? true : false;

				$female = !$male;

				$sHtml .= '<div class="column d03 m12 tright" style="padding-top: 4px;">' . ENTRY_GENDER . '</div>';
				$sHtml .= '<div class="column d09 m12" style="height: 31px;">';
					$sHtml .= tep_draw_radio_field('gender', 'm', $male, 'id="m"') . '<label for="m"><span></span>' . MALE . '</label>';
					$sHtml .= tep_draw_radio_field('gender', 'f', $female, 'id="f"') . '<label for="f"><span></span>' . FEMALE . '</label>';
					$sHtml .= (defined( 'ENTRY_GENDER_TEXT' ) && ENTRY_GENDER_TEXT != '*' && ENTRY_GENDER_TEXT != '' ) ? '<div class="DFhelp">' . ENTRY_GENDER_TEXT . '</div>' : '';
				$sHtml .= '</div>';
			}

			// Nombre
			$sHtml .= '<label class="column d03 m12 tright" for="firstname"><span class="trojo">*</span> ' . ENTRY_FIRST_NAME . '</label>';
			$sHtml .= '<div class="column d09 m12">';
				$sHtml .= tep_draw_input_field( 'firstname', $aDato['entry_firstname'], 'required="" type="text" data-parsley-minlength="' . ENTRY_FIRST_NAME_MIN_LENGTH . '" data-parsley-trigger="change"' );
				$sHtml .= (defined( 'ENTRY_FIRST_NAME_TEXT' ) && ENTRY_FIRST_NAME_TEXT != '*' && ENTRY_FIRST_NAME_TEXT != '' ) ? '<div class="DFhelp">' . ENTRY_FIRST_NAME_TEXT . '</div>' : '';
			$sHtml .= '</div>';

			// Apellidos
			$sHtml .= '<label class="column d03 m12 tright" for="lastname"><span class="trojo">*</span> ' . ENTRY_LAST_NAME . '</label>';
			$sHtml .= '<div class="column d09 m12">';
				$sHtml .= tep_draw_input_field( 'lastname', $aDato['entry_lastname'], 'required="" type="text" data-parsley-minlength="' . ENTRY_LAST_NAME_MIN_LENGTH . '" data-parsley-trigger="change"' );
				$sHtml .= (defined( 'ENTRY_LAST_NAME_TEXT' ) && ENTRY_LAST_NAME_TEXT != '*' && ENTRY_LAST_NAME_TEXT != '' ) ? '<div class="DFhelp">' . ENTRY_LAST_NAME_TEXT . '</div>' : '';
			$sHtml .= '</div>';

			// DNI
			if( ACCOUNT_NIF == 'true' )
			{
				$sHtml .= '<label class="column d03 m12 tright self-top" for="nif">' . (ACCOUNT_NIF_REQ == 'true' ? '<span class="trojo">*</span> ' : '') . ENTRY_NIF . '</label>';
				$sHtml .= '<div class="column d09 m12">';
					$sHtml .= tep_draw_input_field( 'nif', $aDato['entry_nif'] );
					$sHtml .= (defined( 'ENTRY_NIF_EXAMPLE' ) && ENTRY_NIF_EXAMPLE != '*' && ENTRY_NIF_EXAMPLE != '' ) ? '<div class="DFhelp">' . ENTRY_NIF_EXAMPLE . '</div>' : '';
				$sHtml .= '</div>';
			}

			// Compañia
			if( ACCOUNT_COMPANY == 'true' )
			{
				$sHtml .= '<label class="column d03 m12 tright" for="company">' . ENTRY_COMPANY . '</label>';
				$sHtml .= '<div class="column d09 m12">';
					$sHtml .= tep_draw_input_field( 'company', $aDato['entry_company'], ' type="text"' );
					$sHtml .= (defined( 'ENTRY_COMPANY_TEXT' ) && ENTRY_COMPANY_TEXT != '*' && ENTRY_COMPANY_TEXT != '' ) ? '<div class="DFhelp">' . ENTRY_COMPANY_TEXT . '</div>' : '';
				$sHtml .= '</div>';
			}

			// Teléfono
			$sHtml .= '<label class="column d03 m12 tright" for="telephone"><span class="trojo">*</span> ' . ENTRY_TELEPHONE_NUMBER . '</label>';
			$sHtml .= '<div class="column d09 m12">';
				$sHtml .= tep_draw_input_field( 'telephone', $aDato['entry_telephone'], 'required="" type="text" data-parsley-minlength="' . ENTRY_TELEPHONE_MIN_LENGTH . '" data-parsley-trigger="change"' );
				$sHtml .= (defined( 'ENTRY_TELEPHONE_NUMBER_TEXT' ) && ENTRY_TELEPHONE_NUMBER_TEXT != '*' && ENTRY_TELEPHONE_NUMBER_TEXT != '' ) ? '<div class="DFhelp">' . ENTRY_TELEPHONE_NUMBER_TEXT . '</div>' : '';
			$sHtml .= '</div>';

			// Dirección
			$sHtml .= '<label class="column d03 m12 tright" for="street_address"><span class="trojo">*</span> ' . ENTRY_STREET_ADDRESS . '</label>';
			$sHtml .= '<div class="column d09 m12">';
				$sHtml .= tep_draw_input_field( 'street_address', $aDato['entry_street_address'], 'required="" type="text" data-parsley-minlength="' . ENTRY_STREET_ADDRESS_MIN_LENGTH . '" data-parsley-trigger="change"' );
				$sHtml .= (defined( 'ENTRY_STREET_ADDRESS_TEXT' ) && ENTRY_STREET_ADDRESS_TEXT != '*' && ENTRY_STREET_ADDRESS_TEXT != '' ) ? '<div class="DFhelp">' . ENTRY_STREET_ADDRESS_TEXT . '</div>' : '';
			$sHtml .= '</div>';

			// Suburbio
			if( ACCOUNT_SUBURB == 'true' )
			{
				$sHtml .= '<label class="column d03 m12 tright" for="suburb">' . ENTRY_SUBURB . '</label>';
				$sHtml .= '<div class="column d09 m12">';
					$sHtml .= tep_draw_input_field( 'suburb', $aDato['entry_suburb'], ' type="text"' );
					$sHtml .= (defined( 'ENTRY_SUBURB_TEXT' ) && ENTRY_SUBURB_TEXT != '*' && ENTRY_SUBURB_TEXT != '' ) ? '<div class="DFhelp">' . ENTRY_SUBURB_TEXT . '</div>' : '';
				$sHtml .= '</div>';
			}

			// Codigo postal
			$sHtml .= '<label class="column d03 m12 tright" for="postcode"><span class="trojo">*</span> ' . ENTRY_POST_CODE . '</label>';
			$sHtml .= '<div class="column d09 m12 getCitiesFromCP">';
				$sHtml .= tep_draw_input_field( 'postcode', $aDato['entry_postcode'], 'required="" type="text" data-parsley-minlength="' . ENTRY_POSTCODE_MIN_LENGTH . '" data-parsley-trigger="change"' );
				$sHtml .= (defined( 'ENTRY_POST_CODE_TEXT' ) && ENTRY_POST_CODE_TEXT != '*' && ENTRY_POST_CODE_TEXT != '' ) ? '<div class="DFhelp">' . ENTRY_POST_CODE_TEXT . '</div>' : '';
			$sHtml .= '</div>';

			// Pais
			$sHtml .= '<label class="column d03 m12 tright" for="country"><span class="trojo">*</span> ' . ENTRY_COUNTRY . '</label>';
			$sHtml .= '<div class="column d09 m12">';
				$sHtml .= tep_get_country_list2( 'country', $aDato['entry_country_id'], 'data-ajax-states="states"' );
				$sHtml .= (defined( 'ENTRY_COUNTRY_TEXT' ) && ENTRY_COUNTRY_TEXT != '*' && ENTRY_COUNTRY_TEXT != '' ) ? '<div class="DFhelp">' . ENTRY_COUNTRY_TEXT . '</div>' : '';
			$sHtml .= '</div>';

			// Provincia
			if( ACCOUNT_STATE == 'true' )
			{
				$sHtml .= '<label class="column d03 m12 tright" for="city"><span class="trojo">*</span> ' . ENTRY_STATE . '</label>';
				$sHtml .= '<div class="column d09 m12 getCitiesFromZone" id="states">';
					$sHtml .= trim( preg_replace( '/\*$|\&nbsp\;/i', '', strip_tags( ajax_get_zones_html2( $aDato['entry_country_id'], ($aDato['entry_zone_id'] == 0 ? $aDato['entry_state'] : $aDato['entry_zone_id'] ), false ), '<input><select><option>' ) ) );
				$sHtml .= '</div>';
			}

			// Cíudad
			$sHtml .= '<label class="column d03 m12 tright" for="city"><span class="trojo">*</span> ' . ENTRY_CITY . '</label>';
			$sHtml .= '<div class="column d09 m12 city">';
				$sHtml .=  ajax_get_cities_html2($aDato['entry_country_id'], $aDato['entry_zone_id'], false, $aDato['entry_city_id'], true,'',$aDato['entry_city']);
				$sHtml .= (defined( 'ENTRY_CITY_TEXT' ) && ENTRY_CITY_TEXT != '*' && ENTRY_CITY_TEXT != '' ) ? '<div class="DFhelp">' . ENTRY_CITY_TEXT . '</div>' : '';
			$sHtml .= '</div>';

			// Dirección principal
			/* Comentado por petición de cliente (ticket KBC-129-95872)
			if( $customer_default_address_id != $sGetEdit )
			{
				$sHtml .= '<div class="column d03 m12 tright"></div>';
				$sHtml .= '<div class="column d09 m12" style="padding: 14px 0px 14px 14px; background: #fefabe; border: 1px solid #f0ea93; width: auto; font-size: 13px; color: #67686a; font-family: Verdana; margin-bottom: 14px;"><i class="fa fa-address-book-o"></i> ' . SET_AS_PRIMARY . ' ';
					$sHtml .= tep_draw_checkbox_field( 'primary', 'on', false, 'id="primary"' ) . '<label for="primary" style="padding: 0px; margin-left: 10px"><span></span></label>';
				$sHtml .= '</div>';
			}
			*/

			// Submit
			$sHtml .= '<div class="column d12 tright">';
				$sHtml .= '<a href="' . tep_href_link( FILENAME_ADDRESS_BOOK, '', 'SSL' ) . '" class="button ccbutton">' . IMAGE_BUTTON_BACK . '</a> ';
				$sHtml .= '<input class="button verde ccbutton" type="submit" value="' . IMAGE_BUTTON_UPDATE . '">';
			$sHtml .= '</div>';
		$sHtml .= '</form>';

		// Retornamos
		return $sHtml;
	}

	// Eliminar dirección
	if( $sGetDelete !== false )
	{
		// Si es la dirección principal
		if( $sGetDelete == $customer_default_address_id )
		{
			$messageStack->addSession( 'addressbook', WARNING_PRIMARY_ADDRESS_DELETION, 'error' );
			tep_redirect( tep_href_link( FILENAME_ADDRESS_BOOK, '', 'SSL' ) );
		}

		// Comprobamos si existe
		$aDatos = tep_db_query( 'select count(*) as total
								 from ' . TABLE_ADDRESS_BOOK . '
								 where address_book_id = "' . $sGetDelete . '" and customers_id = "' . (int)$customer_id . '"' );
		$aDato = tep_db_fetch_array( $aDatos );

		// Si no existe
		if( $aDato['total'] < 1 )
		{
			$messageStack->addSession( 'addressbook', ERROR_NONEXISTING_ADDRESS_BOOK_ENTRY );
			tep_redirect( tep_href_link( FILENAME_ADDRESS_BOOK, '', 'SSL' ) );
		}

		// Si confirmamos la eliminación
		if( array_key_exists( 'action', $_GET ) && $_GET['action'] == 'deleteconfirm' )
		{
			tep_db_query( 'delete from ' . TABLE_ADDRESS_BOOK . ' where address_book_id = "' . $sGetDelete . '" and customers_id = "' . (int)$customer_id . '"' );
			$messageStack->addSession( 'addressbook', SUCCESS_ADDRESS_BOOK_ENTRY_DELETED, 'success' );
			tep_redirect( tep_href_link( FILENAME_ADDRESS_BOOK, '', 'SSL' ) );
		}

		// Breadcrumb
		$breadcrumb->add( NAVBAR_TITLE_DELETE_ENTRY, tep_href_link( FILENAME_ADDRESS_BOOK_PROCESS, 'delete=' . $_GET['delete'], 'SSL' ) );

		// Html
		$sHtmlAccount .= '<div class="ccTitle">' . DELETE_ADDRESS_TITLE . '</div>';
		$sHtmlAccount .= '<div class="ccCnt">';
			$sHtmlAccount .= $messageStack->show( array( 'text' => DELETE_ADDRESS_DESCRIPTION, 'class' => 'wrng' ) );
			$sHtmlAccount .= '<div class="ccDir">' . tep_address_label( $customer_id, $sGetDelete, true, ' ', '<br />' ) . '</div>';
			$sHtmlAccount .= '<div class="tright" style="margin-top: 10px;">
				<a href="' . tep_href_link( FILENAME_ADDRESS_BOOK, '', 'SSL' ) . '" class="button small ccbutton"><i class="fa fa-arrow-left"></i> ' . IMAGE_BUTTON_BACK . '</a>
				<a href="' . tep_href_link( FILENAME_ADDRESS_BOOK_PROCESS, 'delete=' . $_GET['delete'] . '&action=deleteconfirm', 'SSL' ) . '" class="button small rojo ccbutton"><i class="fa fa-trash"></i> ' . IMAGE_BUTTON_DELETE . '</a>
			</div>';
		$sHtmlAccount .=  '</div>';
	}
	else
	{
		// Editar dirección
		if( $sGetEdit !== false )
		{
			// Consultamos
			$aDatos = tep_db_query( 'select entry_city_id, entry_gender, entry_company, entry_nif, entry_firstname, entry_lastname, entry_telephone, entry_street_address, entry_suburb, entry_postcode, entry_city, entry_state, entry_zone_id, entry_country_id
									 from ' . TABLE_ADDRESS_BOOK . '
									 where customers_id = "' . (int)$customer_id . '" and address_book_id = "' . $sGetEdit . '"');

			// Si no encontramos dirección
			if( !tep_db_num_rows( $aDatos ) )
			{
				$messageStack->addSession( 'addressbook', ERROR_NONEXISTING_ADDRESS_BOOK_ENTRY, 'error' );
				tep_redirect( tep_href_link( FILENAME_ADDRESS_BOOK, '', 'SSL' ) );
			}

			// Dirección
			$aDato = tep_db_fetch_array( $aDatos );

			// Breadcrumb
			$breadcrumb->add( NAVBAR_TITLE_MODIFY_ENTRY, tep_href_link( FILENAME_ADDRESS_BOOK_PROCESS, 'edit=' . $_GET['edit'], 'SSL' ) );

			// Html
			$sHtmlAccount .= '<div class="ccTitle">' . NEW_ADDRESS_TITLE . '<em class="trojo fright cclink">' . FORM_REQUIRED_INFORMATION . '</em></div>';
			$sHtmlAccount .= formAddressBook( tep_href_link( FILENAME_ADDRESS_BOOK_PROCESS, 'edit=' . $sGetEdit, 'SSL' ), $aDato );
		}
		else
		{
			// Si intentamos añadir mas direcciones de las permitidas
			if( tep_count_customer_address_book_entries() >= MAX_ADDRESS_BOOK_ENTRIES )
			{
				$messageStack->addSession( 'addressbook', ERROR_ADDRESS_BOOK_FULL, 'error' );
				tep_redirect(tep_href_link( FILENAME_ADDRESS_BOOK, '', 'SSL' ) );
			}

			// Pais por defecto
			$aDato = array();
			$aDato['entry_country_id'] = DEFAULT_COUNTRY;

			// Breadcrumb
			$breadcrumb->add( NAVBAR_TITLE_ADD_ENTRY, tep_href_link(FILENAME_ADDRESS_BOOK_PROCESS, '', 'SSL') );

			// Html
			$sHtmlAccount .= '<div class="ccTitle">' . NEW_ADDRESS_TITLE . '<em class="trojo fright cclink">' . FORM_REQUIRED_INFORMATION . '</em></div>';
			$sHtmlAccount .= formAddressBook( tep_href_link( FILENAME_ADDRESS_BOOK_PROCESS, 'edit=' . $sGetEdit, 'SSL' ), $aDato );
		}
	}

	// Header
	include( 'account/includes/header.php' );

	// Pintamos
	echo $sHtmlAccount;

	// Footer
	include( 'account/includes/footer.php' );
?>
