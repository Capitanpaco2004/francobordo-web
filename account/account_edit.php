<?php
	use util\date as date;

	// Aplicacion
	include( 'includes/application.php' );

	// Si nos envian el formulario
	if( $_SERVER['REQUEST_METHOD'] == 'POST' )
	{
		// Fecha nacimiento
		if(isset($_POST['dob_ind']) && isset($_POST['dob_inm']) && isset($_POST['dob_inY']))
			$_POST['dob'] = $_POST['dob_ind'].'/'.$_POST['dob_inm'].'/'.$_POST['dob_inY'];

		// Variables
		$gender = ACCOUNT_GENDER == 'true' ? tep_db_prepare_input($_POST['gender']) : '';
		$dob = ACCOUNT_DOB == 'true' ? tep_db_prepare_input($_POST['dob']) : '';
		$nif = ACCOUNT_NIF == 'true' ? tep_db_prepare_input($_POST['nif'])  : '';
		$firstname = tep_db_prepare_input($_POST['firstname']);
		$lastname = tep_db_prepare_input($_POST['lastname']);
		$email_address = tep_db_prepare_input($_POST['email_address']);
		$telephone = tep_db_prepare_input($_POST['telephone']);
		$fax = tep_db_prepare_input($_POST['fax']);

		// Sexo
		if( ACCOUNT_GENDER == 'true' && $gender != 'm' && $gender != 'f' )
			$messageStack->add('account_edit', ENTRY_GENDER_ERROR, 'error', true);

		// DNI
		if( ACCOUNT_NIF == 'true' )
		{
			if( $nif == "" && ACCOUNT_NIF_REQ == 'true' )
				$messageStack->add('account_edit', ENTRY_NO_NIF_ERROR, 'error', true);
			else if( strlen($nif) != 9 && $nif != "" )
				$messageStack->add('account_edit', ENTRY_FORMATO_NIF_ERROR, 'error', true);
		}

		// Nombre
		if( strlen( $firstname ) < ENTRY_FIRST_NAME_MIN_LENGTH )
			$messageStack->add('account_edit', ENTRY_FIRST_NAME_ERROR, 'error', true);

		// Apellidos
		if( strlen($lastname) < ENTRY_LAST_NAME_MIN_LENGTH )
			$messageStack->add('account_edit', ENTRY_LAST_NAME_ERROR, 'error', true);

		// Fecha de cumpleaños
		if( RGPD_ACCOUNT_DELETE_DOB != '1' && ACCOUNT_DOB == 'true' && strlen($dob) < ENTRY_DOB_MIN_LENGTH || (!empty($dob) && (!is_numeric(tep_date_raw($dob)) || !@checkdate(substr(tep_date_raw($dob), 4, 2), substr(tep_date_raw($dob), 6, 2), substr(tep_date_raw($dob), 0, 4)))) )
			$messageStack->add('account_edit', ENTRY_DATE_OF_BIRTH_ERROR, 'error', true);

		// Comprobamos la mayoria de edad
		if( RGPD_ACCOUNT_DELETE_DOB != '1' && ACCOUNT_DOB == 'true' && !date::greaterDate( $_POST['dob_inY'] .'/' . $_POST['dob_inm'] .'/' . $_POST['dob_ind'], 16 ) )
			$messageStack->add('account_edit', ENTRY_DATE_OF_BIRTH_OLD_ERROR, 'error', true);


		// Email
		if( strlen( $email_address ) < ENTRY_EMAIL_ADDRESS_MIN_LENGTH )
			$messageStack->add('account_edit', ENTRY_EMAIL_ADDRESS_ERROR, 'error', true);

		// Email
		if( !tep_validate_email( $email_address ) )
			$messageStack->add('account_edit', ENTRY_EMAIL_ADDRESS_CHECK_ERROR, 'error', true);

		// Email
		$check_email_query = tep_db_query( "select count(*) as total from " . TABLE_CUSTOMERS . " where LOWER(customers_email_address) = '" . strtolower(tep_db_input($email_address)) . "' and customers_id != '" . (int)$customer_id . "'"  );
		$check_email = tep_db_fetch_array( $check_email_query );

		if( $check_email['total'] > 0 )
			$messageStack->add('account_edit', ENTRY_EMAIL_ADDRESS_ERROR_EXISTS, 'error', true);

		// Telefono
		if( strlen( $telephone ) < ENTRY_TELEPHONE_MIN_LENGTH )
			$messageStack->add('account_edit', ENTRY_TELEPHONE_NUMBER_ERROR, 'error', true);

		// Si no contenemos errores
		if( $messageStack->check('account_edit') == false )
		{
			$check_entry_company_tax_id_query = tep_db_query("select entry_company_tax_id from " . TABLE_CUSTOMERS . " where customers_id = '" . (int)$customer_id . "'");
			$check_entry_company_tax_id = tep_db_fetch_array($check_entry_company_tax_id_query);

			$sql_data_array = array(
				'customers_firstname' => $firstname,
				'customers_lastname' => $lastname,
				'customers_email_address' => $email_address,
				'customers_telephone' => $telephone,
				'customers_fax' => $fax
			);

			if( ACCOUNT_GENDER == 'true' )
				$sql_data_array['customers_gender'] = $gender;

			if( ACCOUNT_DOB == 'true' )
				$sql_data_array['customers_dob'] = tep_date_raw( $dob );

			if( ACCOUNT_COMPANY == 'true' )
			{
				if( isset($_POST['company_tax_id']) && tep_not_null($_POST['company_tax_id']) && !tep_not_null($check_entry_company_tax_id['entry_company_tax_id']) )
					$sql_data_array['entry_company_tax_id'] = tep_db_prepare_input($_POST['company_tax_id']);
			}

			tep_db_perform( TABLE_CUSTOMERS, $sql_data_array, 'update', "customers_id = '" . (int)$customer_id . "'" );
			tep_db_query( "update " . TABLE_CUSTOMERS_INFO . " set customers_info_date_account_last_modified = now() where customers_info_id = '" . (int)$customer_id . "'" );

			$sql_data_array = array( 'entry_firstname' => $firstname, 'entry_lastname' => $lastname );

			if( ACCOUNT_NIF == 'true' )
				$sql_data_array['entry_nif'] = $nif;

			$sql_data_array['entry_gender'] = $gender;

			tep_db_perform( TABLE_ADDRESS_BOOK, $sql_data_array, 'update', "customers_id = '" . (int)$customer_id . "' and address_book_id = '" . (int)$customer_default_address_id . "'" );

			// Autologon
			if( tep_not_null($_COOKIE['email_address']) )
			{
				$cookie_url_array = parse_url((ENABLE_SSL == true ? HTTPS_SERVER : HTTP_SERVER) . substr(DIR_WS_CATALOG, 0, -1));
				$cookie_path = $cookie_url_array['path'];
				setcookie('email_address', $email_address, time()+ (365 * 24 * 3600), $cookie_path, '', ((getenv('HTTPS') == 'on') ? 1 : 0));
			}

			// Reiniciamos variables de session
			$customer_first_name = $firstname;

			// --- SalesManago — async data sync + sync smclient cookie ---
			try {
				if (defined('SALESMANAGO_STATUS') && SALESMANAGO_STATUS === 'true') {
					require_once DIR_FS_CATALOG . 'includes/classes/SalesManagoQueue.php';
					SalesManagoQueue::setIdentityCookie((int) $customer_id);        // cookie (sync, before redirect)
					SalesManagoQueue::emitContactUpsert((int) $customer_id, false); // data sync (async)
				}
			} catch (\Throwable $_smE) { @error_log('SM emit edit: ' . $_smE->getMessage()); }

			// Redirect
			$messageStack->addSession( 'account_edit', SUCCESS_ACCOUNT_UPDATED, 'success' );
			tep_redirect( tep_href_link( FILENAME_ACCOUNT_EDIT, '', 'SSL' ) );
		}
	}

	// Breadcrumb
	$breadcrumb->add(NAVBAR_TITLE_2, tep_href_link(FILENAME_ACCOUNT_EDIT, '', 'SSL'));

	// Header
	include( 'account/includes/header.php' );

	// Obtenemos el registro
	$aDatos = tep_db_query( 'select c.customers_gender, c.customers_firstname, c.customers_lastname, c.customers_dob, c.customers_email_address, c.customers_telephone, c.customers_fax, entry_company_tax_id, a.entry_nif
							 from ' . TABLE_CUSTOMERS . ' c
							 left join ' . TABLE_ADDRESS_BOOK . ' a on c.customers_default_address_id = a.address_book_id
							 where a.customers_id = c.customers_id and c.customers_id = "' . (int)$customer_id . '"' );
	$aDato = tep_db_fetch_array( $aDatos );

	// Fecha nacimiento
	$day = substr($aDato['customers_dob'], 8, 2);
	$month = substr($aDato['customers_dob'], 5, 2);
	$year = substr($aDato['customers_dob'], 0, 4);

	// Titulo
	echo '<div class="ccTitle">' . MY_ACCOUNT_INFORMATION . '<em class="trojo fright cclink" style="font-size: 11px; padding-top: 10px; font-weight: normal; color: #da3610 !important;">' . FORM_REQUIRED_INFORMATION . '</em></div>';

	// Mensajes
	echo $messageStack->show( 'account_edit' );

	// Formulario
	echo '<form class="rows dx xform Sp10 amiddle ccFormValid" name="account_edit" action="' . tep_href_link( FILENAME_ACCOUNT_EDIT, '', 'SSL' ) . '" method="post" data-parsley-validate="">';
		// Sexo
		if( ACCOUNT_GENDER == 'true' )
		{
			if( isset($gender) )
				$male = $gender == 'm' ? true : false;
			else
				$male = $aDato['customers_gender'] == 'm' ? true : false;

			$female = !$male;

			echo '<div class="column d03 m12 tright" style="padding-top: 4px;">' . ENTRY_GENDER . '</div>';
			echo '<div class="column d09 m12" style="height: 31px;">';
				echo tep_draw_radio_field('gender', 'm', $male, 'id="m"') . '<label for="m"><span></span>' . MALE . '</label>';
				echo tep_draw_radio_field('gender', 'f', $female, 'id="f"') . '<label for="f"><span></span>' . FEMALE . '</label>';
				echo (defined( 'ENTRY_GENDER_TEXT' ) && ENTRY_GENDER_TEXT != '*' && ENTRY_GENDER_TEXT != '' ) ? '<div class="DFhelp">' . ENTRY_GENDER_TEXT . '</div>' : '';
			echo '</div>';
		}

		// Nombre
		echo '<label class="column d03 m12 tright" for="firstname"><span class="trojo">*</span> ' . ENTRY_FIRST_NAME . '</label>';
		echo '<div class="column d09 m12">';
			echo tep_draw_input_field( 'firstname', $aDato['customers_firstname'], 'required="" data-parsley-minlength="' . ENTRY_FIRST_NAME_MIN_LENGTH . '" data-parsley-trigger="change"' );
			echo (defined( 'ENTRY_FIRST_NAME_TEXT' ) && ENTRY_FIRST_NAME_TEXT != '*' && ENTRY_FIRST_NAME_TEXT != '' ) ? '<div class="DFhelp">' . ENTRY_FIRST_NAME_TEXT . '</div>' : '';
		echo '</div>';

		// Apellidos
		echo '<label class="column d03 m12 tright" for="lastname"><span class="trojo">*</span> ' . ENTRY_LAST_NAME . '</label>';
		echo '<div class="column d09 m12">';
			echo tep_draw_input_field( 'lastname', $aDato['customers_lastname'], 'required="" data-parsley-minlength="' . ENTRY_LAST_NAME_MIN_LENGTH . '" data-parsley-trigger="change"' );
			echo (defined( 'ENTRY_LAST_NAME_TEXT' ) && ENTRY_LAST_NAME_TEXT != '*' && ENTRY_LAST_NAME_TEXT != '' ) ? '<div class="DFhelp">' . ENTRY_LAST_NAME_TEXT . '</div>' : '';
		echo '</div>';

		// DNI
		if( ACCOUNT_NIF == 'true' )
		{
			echo '<label class="column d03 m12 tright self-top" for="nif">' . (ACCOUNT_NIF_REQ == 'true' ? '<span class="trojo">*</span> ' : '') . ENTRY_NIF . '</label>';
			echo '<div class="column d09 m12">';
				echo tep_draw_input_field( 'nif', $aDato['entry_nif'] );
				echo (defined( 'ENTRY_NIF_EXAMPLE' ) && ENTRY_NIF_EXAMPLE != '*' && ENTRY_NIF_EXAMPLE != '' ) ? '<div class="DFhelp">' . ENTRY_NIF_EXAMPLE . '</div>' : '';
			echo '</div>';
		}

		// Fecha de cumpleaños
		if( RGPD_ACCOUNT_DELETE_DOB == '2' )
		{
			echo '<label class="column d03 m12 tright" for="dob">' . ENTRY_DATE_OF_BIRTH . '</label>';
			echo '<div class="column d09 m12 aflex row ax amiddle">';
				echo tep_draw_pull_down_date('dob_in', $day, $month, $year, false, true, date('Y') - 120);
			echo '</div>';
		}

		// Email
		echo '<label class="column d03 m12 tright" for="email_address"><span class="trojo">*</span> ' . ENTRY_EMAIL_ADDRESS . '</label>';
		echo '<div class="column d09 m12">';
			echo tep_draw_input_field( 'email_address', $aDato['customers_email_address'], 'required="" data-parsley-trigger="change"', false, 'email' );
			echo (defined( 'ENTRY_EMAIL_ADDRESS_TEXT' ) && ENTRY_EMAIL_ADDRESS_TEXT != '*' && ENTRY_EMAIL_ADDRESS_TEXT != '' ) ? '<div class="DFhelp">' . ENTRY_EMAIL_ADDRESS_TEXT . '</div>' : '';
		echo '</div>';

		// Telefono
		echo '<label class="column d03 m12 tright" for="telephone"><span class="trojo">*</span> ' . ENTRY_TELEPHONE_NUMBER . '</label>';
		echo '<div class="column d09 m12">';
			echo tep_draw_input_field( 'telephone', $aDato['customers_telephone'], 'required="" data-parsley-minlength="' . ENTRY_TELEPHONE_MIN_LENGTH . '" data-parsley-trigger="change"', false, 'number' );
			echo (defined( 'ENTRY_TELEPHONE_NUMBER_TEXT' ) && ENTRY_TELEPHONE_NUMBER_TEXT != '*' && ENTRY_TELEPHONE_NUMBER_TEXT != '' ) ? '<div class="DFhelp">' . ENTRY_TELEPHONE_NUMBER_TEXT . '</div>' : '';
		echo '</div>';

		// Fax
		/*echo '<label class="column d03 m12 tright" for="fax">' . ENTRY_FAX_NUMBER . '</label>';
		echo '<div class="column d09 m12">';
			echo tep_draw_input_field( 'fax', $aDato['customers_fax'] );
			echo (defined( 'ENTRY_FAX_NUMBER_TEXT' ) && ENTRY_FAX_NUMBER_TEXT != '*' && ENTRY_FAX_NUMBER_TEXT != '' ) ? '<div class="DFhelp">' . ENTRY_FAX_NUMBER_TEXT . '</div>' : '';
		echo '</div>';*/

		// Submit
		echo '<div class="column d12 m12 tright">';
			echo '<a href="' . tep_href_link( FILENAME_ACCOUNT, '', 'SSL' ) . '" class="button ccbutton">' . IMAGE_BUTTON_BACK . '</a> ';
			echo '<input class="button verde ccbutton" type="submit" value="' . IMAGE_BUTTON_UPDATE . '">';
		echo '</div>';
	echo '</form>';

	// Footer
	include( 'account/includes/footer.php' );
?>
