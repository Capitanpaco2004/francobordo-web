<?php

use util\authentication\Customer;
use util\authentication\Exception\CustomerNotApprovedException;
use util\basket\BasketMysql;
use util\tools as tools;
use util\date as date;


require('includes/application_top.php');
include_once('includes/functions/' . FILENAME_ACCOUNT_WORD_CLEANER);

// Distribuidores
$bDistribuidor = array_key_exists('action', $_GET) && $_GET['action'] == 'dist' ? true : false;

// Si estamos logueados
if (tep_session_is_registered('customer_id'))
	tep_redirect(tep_href_link('account/account.php'));

// Idioma
require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_CREATE_ACCOUNT);

$process = false;
if (isset($_POST['action']) && ($_POST['action'] == 'process')) {

	$process = true;

	$city = '';
	if ($_POST['city_id'] != '' && !is_numeric($_POST['city_id'])) {
		$_POST['city'] = $_POST['city_id'];
	}

	// Fecha nacimiento
	$_POST['dob'] = $_POST['dob_ind'] . '/' . $_POST['dob_inm'] . '/' . $_POST['dob_inY'];

	if (ACCOUNT_GENDER == 'true') {
		if (isset($_POST['gender'])) {
			$gender = tep_db_prepare_input($_POST['gender']);
		} else {
			$gender = false;
		}
	}
	$firstname            = tep_db_prepare_input($_POST['firstname']);
	$recargo_equivalencia = tep_db_prepare_input($_POST['recargo_equivalencia']);
	$lastname             = tep_db_prepare_input($_POST['lastname']);
	if (ACCOUNT_DOB == 'true')
		$dob = tep_db_prepare_input($_POST['dob']);
	$email_address = tep_db_prepare_input($_POST['email_address']);
	if (defined('MODULE_ORDER_TOTAL_SPONSORSHIP_STATUS') && MODULE_ORDER_TOTAL_SPONSORSHIP_STATUS == 'true') {
		$sponsorship_email = tep_db_prepare_input($_POST['sponsorship_email']);
	}
	// BOF Separate Pricing Per Customer, added: field for tax id number
	if (ACCOUNT_COMPANY == 'true') {
		$company        = tep_db_prepare_input($_POST['company']);
		$company_tax_id = tep_db_prepare_input($_POST['company_tax_id']);
	}
	// EOF Separate Pricing Per Customer, added: field for tax id number
	//NIF start
	if (ACCOUNT_NIF == 'true')
		if ($_POST['cif'] != '')
			$nif = tep_db_prepare_input($_POST['cif']);
		else
			$nif = tep_db_prepare_input($_POST['nif']);
	//NIF end
	$street_address = tep_db_prepare_input($_POST['street_address']);
	if (ACCOUNT_SUBURB == 'true') $suburb = tep_db_prepare_input($_POST['suburb']);
	$postcode = tep_db_prepare_input($_POST['postcode']);
	$city     = tep_db_prepare_input($_POST['city']);
	$city_id  = (int)tep_db_prepare_input($_POST['city_id']);

	if (ACCOUNT_STATE == 'true') {
		$state = tep_db_prepare_input($_POST['state']);
		if (isset($_POST['zone_id'])) {
			$zone_id = tep_db_prepare_input($_POST['zone_id']);
		} else {
			$zone_id = false;
		}
	}
	$country = tep_db_prepare_input($_POST['country']);

	$firstname = preg_replace('/\'/i', '', RemoveShouting($firstname, true));
	$lastname  = preg_replace('/\'/i', '', RemoveShoutingLN($lastname, true));

	if (RGPD_ACCOUNT_DELETE_DOB == '2')
		$dob = tep_db_prepare_input($_POST['dob']);

	$email_address   = strtolower($email_address);
	$company         = RemoveShoutingCN($company);
	$street_address  = RemoveShouting($street_address);
	$street_address2 = RemoveShouting($street_address2);
	$suburb          = RemoveShouting($suburb);
	$postcode        = strtoupper($postcode);
	$city            = RemoveShouting($city);
	$state           = RemoveShouting($state);
	$nif             = strtoupper($nif);

	$telephone = tep_db_prepare_input($_POST['telephone']);
	$fax       = tep_db_prepare_input($_POST['fax']);
	if (isset($_POST['newsletter'])) {
		$newsletter = tep_db_prepare_input($_POST['newsletter']);
	} else {
		$newsletter = false;
	}
	$password     = tep_db_prepare_input($_POST['password']);
	$confirmation = tep_db_prepare_input($_POST['confirmation']);

	//-----   BEGINNING OF ADDITION: MATC   -----//
	$termsAgree = $rgpd->postFormCheckTermsGeneral();

	// Politica de privacidad
	if ($termsAgree == '') {
		$error = true;
		$messageStack->add('create_account', ERROR_POLITICA . "<br/");
	}

	//-----   END OF ADDITION: MATC   -----//

	if (ACCOUNT_GENDER == 'true') {
		if (($gender != 'm') && ($gender != 'f')) {
			$error = true;

			$messageStack->add('create_account', ENTRY_GENDER_ERROR);
		}
	}

	if (strlen($firstname) < ENTRY_FIRST_NAME_MIN_LENGTH) {
		$error = true;

		$messageStack->add('create_account', ENTRY_FIRST_NAME_ERROR);
	}

	if (strlen($lastname) < ENTRY_LAST_NAME_MIN_LENGTH) {
		$error = true;

		$messageStack->add('create_account', ENTRY_LAST_NAME_ERROR);
	}

	if (RGPD_ACCOUNT_DELETE_DOB == '2') {
		if (checkdate($_POST['dob_inm'], $_POST['dob_ind'], $_POST['dob_inY']) == false) {
			$error = true;
			$messageStack->add('create_account', ENTRY_DATE_OF_BIRTH_ERROR . "<br/");
		}

		// Comprobamos la mayoria de edad
		if (!date::greaterDate($_POST['dob_ind'] . '/' . $_POST['dob_inm'] . '/' . $_POST['dob_inY'], 16)) {
			$error = true;
			$messageStack->add('create_account', ENTRY_DATE_OF_BIRTH_OLD_ERROR . "<br/");
		}
	}

	if (strlen($email_address) < ENTRY_EMAIL_ADDRESS_MIN_LENGTH) {
		$error = true;

		$messageStack->add('create_account', ENTRY_EMAIL_ADDRESS_ERROR);
	} else if (tep_validate_email($email_address) == false) {
		$error = true;

		$messageStack->add('create_account', ENTRY_EMAIL_ADDRESS_CHECK_ERROR);
	} else {
		$check_email_query = tep_db_query("select count(*) as total from " . TABLE_CUSTOMERS . " where customers_email_address = '" . tep_db_input($email_address) . "'");
		$check_email       = tep_db_fetch_array($check_email_query);
		if ($check_email['total'] > 0) {
			$error = true;

			$messageStack->add('create_account', ENTRY_EMAIL_ADDRESS_ERROR_EXISTS);
		}
	}


	//NIF start
	if (ACCOUNT_NIF == 'true') {
		if (($nif == "") && (ACCOUNT_NIF_REQ == 'true')) {
			$error = true;
			$messageStack->add('create_account', ENTRY_NO_NIF_ERROR);
		}
	}
	//NIF end

	if (strlen($street_address) < ENTRY_STREET_ADDRESS_MIN_LENGTH) {
		$error = true;

		$messageStack->add('create_account', ENTRY_STREET_ADDRESS_ERROR);
	}

	if (strlen($postcode) < ENTRY_POSTCODE_MIN_LENGTH) {
		$error = true;

		$messageStack->add('create_account', ENTRY_POST_CODE_ERROR);
	}
	if ($city_id == 0 && $city == '') {
		$error = true;
		$messageStack->add('create_account', ENTRY_CITY_ID_ERROR);
	}
	if (strlen($city) < ENTRY_CITY_MIN_LENGTH && strlen($city) > 0) {
		$error = true;

		$messageStack->add('create_account', ENTRY_CITY_ERROR);
	}

	if (is_numeric($country) == false) {
		$error = true;

		$messageStack->add('create_account', ENTRY_COUNTRY_ERROR);
	}

	if (ACCOUNT_STATE == 'true') {
		// +Country-State Selector
		if ($zone_id == 0) {
			// -Country-State Selector

			if (strlen((string)$state) < ENTRY_STATE_MIN_LENGTH) {
				$error = true;

				$messageStack->add('create_account', ENTRY_STATE_ERROR);
			}
		}
	}

	if (strlen(preg_replace('/\D/', '', (string) $telephone)) < 9) {
		$error = true;

		$messageStack->add('create_account', ENTRY_TELEPHONE_NUMBER_ERROR);
	}


	if (strlen($password) < ENTRY_PASSWORD_MIN_LENGTH) {
		$error = true;

		$messageStack->add('create_account', ENTRY_PASSWORD_ERROR);
	} else if ($password != $confirmation) {
		$error = true;

		$messageStack->add('create_account', ENTRY_PASSWORD_ERROR_NOT_MATCHING);
	}

	/* [+] subir el archivo al servidor */
	if ($bDistribuidor && !empty($_FILES['iae']['tmp_name']) && is_uploaded_file($_FILES['iae']['tmp_name']) && $error == false) {
		// Segun el mime realizamos una instancia diferente
		if (preg_match('/pdf|doc|jpeg|png/i', $_FILES['iae']['type'])) {
			$nombre_iae  = str_replace('@', '_', $email_address);
			$nombre_iae  = str_replace('.', '_', $nombre_iae);
			$partes_ruta = pathinfo($_FILES['iae']['name']);
			$nombre_iae  = '_admin/iae/' . $nombre_iae . '.' . $partes_ruta['extension'];
			copy($_FILES['iae']['tmp_name'], $nombre_iae);
			$varname = $_FILES['iae']['name'];
			$vartemp = $_FILES['iae']['tmp_name'];

			$mail           = new \PHPMailer\PHPMailer\PHPMailer();
			$mail->Host     = "localhost";
			$mail->From     = STORE_OWNER_EMAIL_ADDRESS;
			$mail->FromName = TITLE;
			$mail->Subject  = 'Archivo IAE de ' . $email_address;
			$mail->AddAddress(STORE_OWNER_EMAIL_ADDRESS);

			if ($varname != "") $mail->AddAttachment($vartemp, $varname);

			$body       = $email_address;
			$mail->Body = $body;
			$mail->IsHTML(true);
			$mail->Send();
		} else {
			$error = true;
			$messageStack->add('create_account', ENTRY_IAE_ERROR . "<br/");
		}
	} else if ($bDistribuidor) {
		$error = true;
		$messageStack->add('create_account', ENTRY_IAE_ERROR);
	}
	/* [-] fin de subir archivo al servidor */

	// Points/Rewards system V2.1rc2a + Remember referrer BOF
	if (tep_not_null(USE_REFERRAL_SYSTEM) && isset($_POST['customer_referred']) && tep_not_null($_POST['customer_referred'])) {
		$valid_referral_query = tep_db_query("SELECT customers_id FROM " . TABLE_CUSTOMERS . " WHERE customers_email_address = '" . tep_db_input(tep_db_prepare_input($_POST['customer_referred'])) . "'");
		$valid_referral       = tep_db_fetch_array($valid_referral_query);
		if (!tep_db_num_rows($valid_referral_query)) {
			$error = true;
			$messageStack->add('create_account', REFERRAL_ERROR_NOT_FOUND);
		} else {
			if ($_POST['customer_referred'] == $order->customer['email_address']) {
				$error = true;
				$messageStack->add('create_account', REFERRAL_ERROR_SELF);
			} else {
				$customer_referral = $valid_referral['customers_id'];
				if (!tep_session_is_registered('customer_referral')) tep_session_register('customer_referral');
			}
		}
	}
	// Points/Rewards system V2.1rc2a + Remember referrer EOF
	if ($error == false) {
		$sql_data_array = ['customers_firstname'     => $firstname,
						   'customers_lastname'      => $lastname,
						   'customers_email_address' => $email_address,
						   'customers_telephone'     => $telephone,
						   'customers_fax'           => $fax,
						   'customers_newsletter'    => $newsletter,
						   'recargo_equivalencia'    => $recargo_equivalencia,
						   'id_term_pivacy_general'  => $rgpd->aTermGeneral['id_term_pivacy_general'],
						   'customers_password'      => tep_encrypt_password($password)];
		// Si es distribuidor
		if ($bDistribuidor) {
			$sql_data_array['proveedor']          = 1;
			$sql_data_array['proveedor_iae']      = isset($nombre_iae) ? $nombre_iae : '';
			$sql_data_array['customers_group_id'] = 0;
			$sql_data_array['member_level']       = 0;
		}

		// Points/Rewards system V2.1rc2a + Remember referrer BOF
		if (isset($customer_referral) && tep_not_null($customer_referral) && KEEP_REFERRER_ID == 'true') {
			$sql_data_array['customer_referral'] = $customer_referral;
		}
		// Points/Rewards system V2.1rc2a + Remember referrer EOF

		if (RGPD_ACCOUNT_DELETE_DOB == '2')
			$sql_data_array['customers_dob'] = tep_date_raw($dob);

		if (ACCOUNT_GENDER == 'true') $sql_data_array['customers_gender'] = $gender;
		if (ACCOUNT_DOB == 'true') $sql_data_array['customers_dob'] = tep_date_raw($dob);
		// BOF Separate Pricing Per Customer
		// if you would like to have an alert in the admin section when either a company name has been entered in
		// the appropriate field or a tax id number, or both then uncomment the next line and comment the default
		// setting: only alert when a tax_id number has been given
		//    if ( (ACCOUNT_COMPANY == 'true' && tep_not_null($company) ) || (ACCOUNT_COMPANY == 'true' && tep_not_null($company_tax_id) ) ) {
		if (ACCOUNT_COMPANY == 'true' && tep_not_null($company_tax_id)) {
			$sql_data_array['customers_group_ra'] = '1';
			// entry_company_tax_id moved from table address_book to table customers in version 4.2.0
			$sql_data_array['entry_company_tax_id'] = $company_tax_id;
		}
		// EOF Separate Pricing Per Customer
		if (RGPD_ACCOUNT_DELETE_DOB == '2')
			$sql_data_array['customers_dob'] = date::dateRaw($dob);

		tep_db_perform(TABLE_CUSTOMERS, $sql_data_array);

		$customer_id = tep_db_insert_id();

		// Si hemos activado el newsletter activamos todos los terminos comerciales
		if ($newsletter != false) {
			$aSubscribedAll = array_values(pharaonix_getArrayAssociativeSql('SELECT id_term_pivacy_trade, title, info FROM rgpd_term_privacy_trade WHERE language_id = "' . $languages_id . '"', 'id_term_pivacy_trade', 'title', false));

			foreach ($aSubscribedAll as $aSubscribed) {
				$nIdAll = $aSubscribed['id'];
				$sTitle = $aSubscribed['text'];

				tep_db_perform('rgpd_account_term', ['customers_id' => $customer_id, 'id_term_pivacy_trade' => $nIdAll]);

				tep_db_perform('rgpd_log_term_privacy', [
					'customers_id'   => $customer_id,
					'customers_mail' => strtolower($email_address),
					'ip'             => tools::getIP(),
					'date'           => date('Y-m-d H:i:s'),
					'type'           => 'comercial',
					'term_name'      => $sTitle,
					'id_term_pivacy' => $nIdAll,
					'status'         => 1,
				]);
			}

			// Añadimos el cliente al subscribers
			tep_db_perform('subscribers', [
				'customers_id'              => $customer_id,
				'subscribers_firstname'     => $firstname,
				'subscribers_lastname'      => $lastname,
				'subscribers_email_address' => strtolower($email_address),
				'language_id'               => $languages_id,
				'date_account_created'      => date('Y-m-d H:i:s'),
				'customers_newsletter'      => 1,
				'status_sent1'              => 1,
				'source_import'             => 'subscribe_newsletter',
			]);
		}

		// Añadimos log termino
		tep_db_perform('rgpd_log_term_privacy', [
			'customers_id'   => $customer_id,
			'customers_mail' => strtolower($email_address),
			'ip'             => tools::getIP(),
			'date'           => date('Y-m-d H:i:s'),
			'type'           => 'general',
			'term_name'      => $rgpd->aTermGeneral['title'],
			'id_term_pivacy' => $rgpd->aTermGeneral['id_term_pivacy_general'],
			'status'         => 1,
		]);

		if (is_numeric($city_id) && intval($city_id) > 0 && $city == '') {
			$city = pharaonix_queryOne('SELECT name FROM cities WHERE id = "' . (int)$city_id . '"')->records['name'];
		} else {
			//$city = tep_db_prepare_input($city_id);
			$city_id = 0;
		}

		$sql_data_array = ['customers_id'         => $customer_id,
						   'entry_firstname'      => $firstname,
						   'entry_lastname'       => $lastname,
						   'entry_telephone'      => $telephone,
						   'entry_street_address' => $street_address,
						   'entry_postcode'       => $postcode,
						   'entry_city'           => $city,
						   'entry_city_id'        => (int)$city_id,
						   'entry_country_id'     => $country];

		if (ACCOUNT_GENDER == 'true') {
			$sql_data_array['entry_gender'] = $gender;
		}
		if (ACCOUNT_COMPANY == 'true') {
			$sql_data_array['entry_company'] = $company;
		}
		//NIF start
		if (ACCOUNT_NIF == 'true') {
			if ($nif == '') {
				$sql_data_array['entry_nif'] = $nif;
			} else {
				$sql_data_array['entry_nif'] = $nif;
			}
		}

		if (ACCOUNT_STATE == 'true') {
			if ($zone_id > 0) {
				$sql_data_array['entry_zone_id'] = $zone_id;
				$sql_data_array['entry_state']   = '';
			} else {
				$sql_data_array['entry_zone_id'] = ($country == STORE_COUNTRY ? STORE_ZONE : '0');
				$sql_data_array['entry_state']   = $state;
			}
		}

		tep_db_perform(TABLE_ADDRESS_BOOK, $sql_data_array);

		$address_id = tep_db_insert_id();

		tep_db_query("update " . TABLE_CUSTOMERS . " set customers_default_address_id = '" . (int)$address_id . "' where customers_id = '" . (int)$customer_id . "'");

		tep_db_query("insert into " . TABLE_CUSTOMERS_INFO . " (customers_info_id, customers_info_number_of_logons, customers_info_date_account_created) values ('" . (int)$customer_id . "', '0', now())");

		if (defined('MODULE_ORDER_TOTAL_SPONSORSHIP_STATUS') && MODULE_ORDER_TOTAL_SPONSORSHIP_STATUS == 'true') {
			if (tep_not_null($sponsorship_email) && tep_not_null($email_address)) {
				$cs_query = tep_db_query("select customers_id, customers_gender, customers_lastname, customers_firstname from " . TABLE_CUSTOMERS . " where customers_email_address = '" . tep_db_input($sponsorship_email) . "'");
				$cs       = tep_db_fetch_array($cs_query);

				tep_db_query("insert into " . TABLE_CUSTOMERS_SPONSORSHIP . " (customers_godson_id, customers_sponsorship_id, customers_email_address, customers_sponsorship_email, date_added) values ('" . (int)$customer_id . "', '" . (int)$cs['customers_id'] . "', '" . $email_address . "', '" . $sponsorship_email . "', now())");
			}
		}


		try {
			$customerCore = Customer::createById((int)$customer_id);
			$customerCore->login();
			$cookie->password = $customerCore->getPassword();
		} catch (CustomerNotApprovedException $e) {
			$cart->restore_contents();
		}


		// restore cart contents
		$cart->restore_contents();

		// build the message content
		//---  Beginning of addition: Ultimate HTML Emails  ---//
		if (EMAIL_USE_HTML == 'true') {
			$bProfesional = ($bDistribuidor ? true : false);
			require(DIR_WS_MODULES . 'UHtmlEmails/' . ULTIMATE_HTML_EMAIL_LAYOUT . '/create_account.php');
			$email_text = $html_email;
		} else {
			//---  End of addition: Ultimate HTML Emails  ---//
			$name = $firstname . ' ' . $lastname;

			if (ACCOUNT_GENDER == 'true') {
				if ($gender == 'm') {
					$email_text = sprintf(EMAIL_GREET_MR, $lastname);
				} else {
					$email_text = sprintf(EMAIL_GREET_MS, $lastname);
				}
			} else {
				$email_text = sprintf(EMAIL_GREET_NONE, $firstname);
			}

			$email_text .= EMAIL_WELCOME . EMAIL_TEXT . EMAIL_CONTACT . EMAIL_WARNING;
			//---  Beginning of addition: Ultimate HTML Emails  ---//
		}
		if (ULTIMATE_HTML_EMAIL_DEVELOPMENT_MODE === 'true') {
			//Save the contents of the generated html email to the harddrive in .htm file. This can be practical when developing a new layout.
			$TheFileName = 'Last_mail_from_create_account.php.htm';
			$TheFileHandle = fopen($TheFileName, 'w') or die("can't open error log file");
			fwrite($TheFileHandle, $email_text);
			fclose($TheFileHandle);
		}
		//---  End of addition: Ultimate HTML Emails  ---//
		tep_mail($name, $email_address, EMAIL_SUBJECT, $email_text, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);

		// Email para avisar de cliente distribuidor
		if ($bDistribuidor) {
			$alert_email_text = "Se informa que " . $firstname . " " . $lastname . " de la compañia: " . ((empty($company)) ? "-no especificada-" : $company) . " ha creado una cuenta.";
			tep_mail(STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, 'Profesional Registrado', $alert_email_text, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
		}

		// Añadiamos los wishlist a tu cuenta
		if (tep_session_is_registered('aWishlist')) {
			foreach ($aWishlist as $value) {
				// Datos sql
				$aDatosSql = ['customers_id' => $customer_id, 'products_id' => $value['products_id'], 'atributo' => $value['atributo']];

				// Insertamos
				tep_db_perform('wishlist', $aDatosSql);
			}

			unset($aWishlist);
			tep_session_unregister('aWishlist');
		}
		tep_session_register('createAccountSuccessEvent');

		// --- SalesManago — async data sync + sync smclient cookie ---
		try {
			if (defined('SALESMANAGO_STATUS') && SALESMANAGO_STATUS === 'true') {
				require_once DIR_FS_CATALOG . 'includes/classes/SalesManagoQueue.php';
				SalesManagoQueue::setIdentityCookie((int) $customer_id);       // cookie (sync, before redirect)
				SalesManagoQueue::emitContactUpsert((int) $customer_id, true); // data sync (async)
			}
		} catch (\Throwable $_smE) { @error_log('SM emit signup: ' . $_smE->getMessage()); }

		tep_redirect(tep_href_link(FILENAME_CREATE_ACCOUNT_SUCCESS, ($bDistribuidor ? 't=prf' : ''), 'SSL'));
	}
}
// +Country-State Selector
if (!isset($country)) {
	$country = DEFAULT_COUNTRY;
}
// -Country-State Selector

$breadcrumb->add(NAVBAR_TITLE, tep_href_link(FILENAME_CREATE_ACCOUNT, '', 'SSL'));


include(DIR_THEME . 'html/header.php');
include("includes/form_check.js.php");
include(DIR_THEME . 'html/column_left.php');

    echo '<div class="xmessage xmessage-info"><input type="checkbox"/><i class="fa fa-times"></i><div><i class="fa fa-info-circle"></i>' . sprintf(TEXT_ORIGIN_LOGIN, tep_href_link(FILENAME_LOGIN, tep_get_all_get_params(), 'SSL')) . '</div></div>';
    echo ($bDistribuidor ? '<div class="xmessage xmessage-warning"><input type="checkbox"/><i class="fa fa-times"></i><div><i class="fa fa-info-circle"></i>' . TEXT_PROFESIONAL_WARNING . '</div></div>' : '');

    if ($messageStack->size('create_account') > 0) {
        echo '<div class="xmessage xmessage-error"><input type="checkbox"/><i class="fa fa-times"></i><div><i class="fa fa-exclamation-circle"></i>' . strip_tags( $messageStack->output('create_account') ) . '</div></div>';
    }

    echo tep_draw_form('create_account', tep_href_link(FILENAME_CREATE_ACCOUNT, ($bDistribuidor ? 'action=dist' : ''), 'SSL'), 'post', ($bDistribuidor ? 'enctype="multipart/form-data" ' : '') . 'onSubmit="return check_form(create_account);" class="ax row xform atop" id="create_account"');
        echo tep_draw_hidden_field('action', 'process');

		if( !$bDistribuidor )
		{
			echo '<div class="col a12 ax row sp10">';
				echo '<h4 class="col a12">' . CREATE_ACCOUNT_TITLE_SOY . '</h4>';
				echo '<input class="create_account_type" type="radio" checked="checked" name="type" id="particular"/><label for="particular"><span></span>' . CREATE_ACCOUNT_PARTICULAR . '</label>';
				echo '<input class="create_account_type" type="radio" name="type" id="empresa"/><label for="empresa"><span></span>' . CREATE_ACCOUNT_EMPRESA . '</label>';
			echo '</div>';
		}

        echo '<div class="col a06 ax row sp10 m12" style="padding-right: 10px;">';
            echo '<h4 class="col a12">' . CATEGORY_PERSONAL . '</h4>';

            if (ACCOUNT_GENDER == 'true') {
                echo ENTRY_GENDER . '&nbsp;&nbsp;' . tep_draw_radio_field('gender', 'm', false, 'id="male"') . ' <label for="male"><span></span>' . MALE . '</label>';
                echo tep_draw_radio_field('gender', 'f', false, 'id="female"') . ' <label for="female"><span></span>' . FEMALE . '</label>';
            }

            echo '<div class="col a12 aflex row ax amiddle">';
                echo tep_draw_input_field('firstname', '', 'class="column" placeholder="' . str_replace(':', '', ENTRY_FIRST_NAME) . '"');
                echo tep_not_null(ENTRY_FIRST_NAME_TEXT) ? '<span class="inputRequirement col afixed">' . ENTRY_FIRST_NAME_TEXT . '</span>': '';
            echo '</div>';

            echo '<div class="col a12 aflex row ax amiddle">';
                echo tep_draw_input_field('lastname', '', 'class="column" placeholder="' . str_replace(':', '', ENTRY_LAST_NAME) . '"');
                echo tep_not_null(ENTRY_LAST_NAME_TEXT) ? '<span class="inputRequirement col afixed">' . ENTRY_LAST_NAME_TEXT . '</span>': '';
            echo '</div>';

            if (ACCOUNT_NIF == 'true') {
                echo '<div class="col a12 aflex row ax amiddle">';
                echo tep_draw_input_field('nif', '', 'class="column" placeholder="' . str_replace(':', '', ENTRY_NIF) . '"');
                echo tep_not_null(ENTRY_NIF_TEXT) && (ACCOUNT_NIF_REQ == 'true') ? '<span class="inputRequirement col afixed">' . ENTRY_NIF_TEXT . '</span>': '';
                echo tep_not_null(ENTRY_NIF_EXAMPLE) ? '<span class="inputRequirement col afixed">' . ENTRY_NIF_EXAMPLE . '</span>': '';
                echo '</div>';
            }

			if( !$bDistribuidor )
			{
				echo '<div class="company-hidden col a12 row sp10" style="display: none; padding: 0px;">';
					echo '<div class="col a12">';
						echo tep_draw_checkbox_field('recargo_equivalencia', '1', false, 'id="recargo_equivalencia"') . ' <label for="recargo_equivalencia"><span></span>' . CREATE_ACCOUNT_RE . ' (+' .  MODULE_ORDER_TOTAL_REC_VALUE . '%)</label>';
					echo '</div>';
				echo '</div>';
			}

            if( RGPD_ACCOUNT_DELETE_DOB == '2' )
			{
                echo '<div class="col a12 aflex row ax amiddle">';
				echo tep_draw_pull_down_date('dob_in', '', '', (isset($_POST['dob_inY'])? $_POST['dob_inY'] : date('Y') - 25), false, true, date('Y') - 120);
                echo tep_not_null(ENTRY_DATE_OF_BIRTH_TEXT) ? '<span class="inputRequirement col afixed">' . ENTRY_DATE_OF_BIRTH_TEXT . '</span>': '';
                echo '</div>';
            }

            echo '<div class="col a12 aflex row ax amiddle">';
                echo tep_draw_input_field('email_address', '', 'class="column" placeholder="' . str_replace(':', '', ENTRY_EMAIL_ADDRESS) . '"');
                echo tep_not_null(ENTRY_EMAIL_ADDRESS_TEXT) ? '<span class="inputRequirement col afixed">' . ENTRY_EMAIL_ADDRESS_TEXT . '</span>': '';
            echo '</div>';

            echo '<div class="col a12 aflex row ax amiddle" style="display: none;">';
                echo tep_draw_input_field('email_address_re', '', 'class="column" placeholder="Repita E-Mail"');
                echo tep_not_null(ENTRY_EMAIL_ADDRESS_TEXT) ? '<span class="inputRequirement col afixed">' . ENTRY_EMAIL_ADDRESS_TEXT . '</span>': '';
            echo '</div>';

            echo '<div class="col a12 aflex row ax amiddle">';
                echo tep_draw_input_field('telephone', '', 'class="column" placeholder="' . str_replace(':', '', ENTRY_TELEPHONE_NUMBER) . '"');
                echo tep_not_null(ENTRY_TELEPHONE_NUMBER_TEXT) ? '<span class="inputRequirement col afixed">' . ENTRY_TELEPHONE_NUMBER_TEXT . '</span>': '';
            echo '</div>';

            echo '<div class="col a12 aflex row ax amiddle" style="display: none;">';
                echo tep_draw_input_field('fax', '', 'class="column" placeholder="' . str_replace(':', '', ENTRY_FAX_NUMBER) . '"');
                echo tep_not_null(ENTRY_FAX_NUMBER_TEXT) ? '<span class="inputRequirement col afixed">' . ENTRY_FAX_NUMBER_TEXT . '</span>': '';
            echo '</div>';

        echo '</div>';
        echo '<div class="col a06 ax row sp10 m12" style="padding-left: 10px;">';
            echo '<h4 class="col a12">' . CATEGORY_ADDRESS . '</h4>';
            echo '<div class="col a12 aflex row ax amiddle">';
                echo tep_draw_input_field('street_address', '', 'class="column" placeholder="' . str_replace(':', '', ENTRY_STREET_ADDRESS) . '"');
                echo tep_not_null(ENTRY_STREET_ADDRESS_TEXT) ? '<span class="inputRequirement col afixed">' . ENTRY_STREET_ADDRESS_TEXT . '</span>': '';
            echo '</div>';

            echo '<div class="col a12 aflex row ax amiddle">';
                echo tep_draw_input_field('postcode', '', 'class="column" maxlength="10" data-ajax-postcode placeholder="' . str_replace(':', '', ENTRY_POST_CODE) . '"');
                echo tep_not_null(ENTRY_POST_CODE_TEXT) ? '<span class="inputRequirement col afixed">' . ENTRY_POST_CODE_TEXT . '</span>': '';
            echo '</div>';

            echo '<div class="col a12 aflex row ax amiddle">';
                echo '<div id="ajax-country" class="column">' . getCountries( array( 'country' => $country ) ) . '</div>';
                echo tep_not_null(ENTRY_COUNTRY_TEXT) ? '<span class="inputRequirement col afixed">' . ENTRY_COUNTRY_TEXT . '</span>': '';
            echo '</div>';

            if (ACCOUNT_STATE == 'true') {
                echo '<div class="col a12 aflex row ax amiddle">';
					echo '<div id="ajax-zone" class="column">' . getZonesByCountry( array( 'country' => $country ) ) . '</div>';
					echo tep_not_null(ENTRY_STATE_TEXT) ? '<span class="inputRequirement col afixed">' . ENTRY_STATE_TEXT . '</span>': '';
                echo '</div>';

                echo '<div class="col a12 aflex row ax amiddle">';
					echo '<div id="ajax-city" class="column">';
							echo getCitiesByCountryByZone(array('country' => $country, 'zone' => $zone_id, 'input_mode' => (isset($city_id) && !is_numeric($city_id) ? $city_id : false)));
					echo '</div>';
					echo tep_not_null(ENTRY_CITY_TEXT) ? '<span class="inputRequirement col afixed">' . ENTRY_CITY_TEXT . '</span>': '';
                echo '</div>';
            }
        echo '</div>';

        if (ACCOUNT_COMPANY == 'true')
		{
            echo '<div class="col a12 ax row sp10 company-hidden" style="display: ' . ($bDistribuidor ? 'flex' : 'none') . '; ">';
				echo '<h4 class="col a12">' . CATEGORY_COMPANY . '</h4>';
				echo '<div class="col a06 aflex row ax amiddle m12">';
					echo tep_draw_input_field('company', '', 'class="column" placeholder="' . str_replace(':', '', ENTRY_COMPANY) . '"');
					echo tep_not_null(ENTRY_COMPANY_TEXT) ? '<span class="inputRequirement col afixed">' . ENTRY_COMPANY_TEXT . '</span>': '';
				echo '</div>';
				echo '<div class="col a06 aflex row ax amiddle m12">';
					echo tep_draw_input_field('cif', '', 'class="column" placeholder="CIF"');
				echo '</div>';

				if( $bDistribuidor )
				{
					echo '<div class="col a12">';
						echo '<input name="iae" id="iae" type="file" data-button-color="azul" accept="application/pdf,application/doc,image/jpeg,image/png" data-file="IAE o Modelo 036 (solo pdf, doc, png o jpg)" data-button-icon="fa-cloud-upload" data-button-text="Subir archivo" class="form-file-inpt"/>';
					echo '</div>';

					echo '<div class="col a12">';
						echo tep_draw_checkbox_field('recargo_equivalencia', '1', false, 'id="recargo_equivalencia"') . ' <label for="recargo_equivalencia"><span></span>Me acojo al recargo de equivalencia (+' .  MODULE_ORDER_TOTAL_REC_VALUE . '%)</label>';
					echo '</div>';
				}
            echo '</div>';
        }

		echo '<div class="col a12 ax row sp10">';
		echo '<h4 class="col a12">' . CATEGORY_PASSWORD . '</h4>';
		echo '<div class="col a06 aflex row ax amiddle m12">';
		echo tep_draw_password_field('password', '', 'class="column" placeholder="' . str_replace(':', '', ENTRY_PASSWORD) . '"');
		echo tep_not_null(ENTRY_PASSWORD_TEXT) ? '<span class="inputRequirement col afixed">' . ENTRY_PASSWORD_TEXT . '</span>': '';
		echo '</div>';
		echo '<div class="col a06 aflex row ax amiddle m12">';
		echo tep_draw_password_field('confirmation', '', 'class="column" placeholder="' . str_replace(':', '', ENTRY_PASSWORD_CONFIRMATION) . '"');
		echo tep_not_null(ENTRY_PASSWORD_CONFIRMATION_TEXT) ? '<span class="inputRequirement col afixed">' . ENTRY_PASSWORD_CONFIRMATION_TEXT . '</span>': '';
		echo '</div>';

		echo '</div>';


        echo '<div class="col a12 ax row sp10 amiddle" style="border-top: 1px solid #e1e7ec; padding-top: 20px; margin-top: 20px;">';
            echo '<div class="col a07 m12">';
				echo $rgpd->formCheckTermsGeneral();
				echo '<div class="col a12" style="margin-bottom: 8px;">';
					$aText = json_decode( str_replace( array( '\"', "\\\'" ), array('"', "'"), RGPD_TERMS_TRADE_TEXT_CHECK ), true );
					$aTextTooltop = json_decode( str_replace( array( '\"' ), array('"'), RGPD_TERMS_TRADE_TEXT ), true );

					echo '<div class="col a12 xform check rgpd-check"><input type="checkbox" name="newsletter" value="1" id="newsletter"><label style="margin-right: 0px;" for="newsletter"><span></span>' . str_replace( '{LINK}', tep_href_link('information.php', 'info_id=' . RGPD_TERMS_TRADE_INFO_ID),  html_entity_decode( $aText[$languages_id] ) ) . ' <i title="' . $aTextTooltop[$languages_id] . '" class="fa fa-exclamation-circle"></i></label></div>';
                echo '</div>';
            echo '</div>';
            echo '<div class="col a05 m12">';
                echo '<div class="col a12 tright"><span class="trojo">' . FORM_REQUIRED_INFORMATION . '</span>&nbsp;&nbsp; <input class="bton-dflt verde tblanco hv9" id="TheSubmitButton" type="submit" value="' . IMAGE_BUTTON_CONTINUE . '" /></div>';
                echo '<div id="captcha_container"></div>';
            echo '</div>';
        echo '</div>';
    echo '</form>';

	// Mayor de edad
	if( RGPD_ACCOUNT_DELETE_DOB == '1' && $_SERVER['REQUEST_METHOD'] == 'GET' )
	{
		$sHtml = '<div id="rgpd-wndw" class="mfp-hide rgpd-dob zoom-anim-dialog win-repn mfp-white">';
			$sHtml .= '<div class="cntd">';
				$sHtml .= '<div class="rgpd-cntd">';
					$sHtml .= '<div class="rgpd-extr">';
						$sHtml .= '<span>' . RGPD_WINDOW_MODAL_TITLE_DOB . '</span>';
						$sHtml .= '<i><img src="theme/web/images/general/rgpd-date.png" /></i>';
						$sHtml .= '<small>' . RGPD_WINDOW_MODAL_SUBTITLE_DOB . '</small>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';
				$sHtml .= '<div class="rgpd-btn">';
					$sHtml .= '<div id="rgpd-dob-accp">' . RGPD_WINDOW_MODAL_DOB_ACCEPT . '</div>';
					$sHtml .= '<a href="' . tep_href_link( 'index.php' ) . '" id="rgpd-dob-dngt" class="red">' . RGPD_WINDOW_MODAL_DOB_DENEGATE. '</a> ';
				$sHtml .= '</div>';
			$sHtml .= '</div>';
		$sHtml .= '</div>';

		echo $sHtml;
		echo '<a href="#rgpd-wndw" data-modal="true" class="mgp-inln mgp-auto" style="display: none;"></a>';
	}

    include(DIR_THEME. 'html/column_right.php');
    include(DIR_THEME. 'html/footer.php');
    include(DIR_WS_INCLUDES . 'application_bottom.php');
?>
