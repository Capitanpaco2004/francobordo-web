<?php
	use util\authentication\Customer;
	use util\authentication\Exception\CustomerNotApprovedException;
	use util\basket\BasketMysql;
	use util\tools as tools;
	use util\date as date;
	use AddonDomainEvent\Customer\Application\Update\NewsletterOptIn;

  require('includes/application_top.php');
  include_once('includes/functions/' . FILENAME_ACCOUNT_WORD_CLEANER);


  tep_redirect(tep_href_link('create_account.php', 'action=dist'));

  
  // +Country-State Selector

if (isset($_POST['action']) && $_POST['action'] == 'getStates' && isset($_POST['country'])) {
	ajax_get_zones_html(tep_db_prepare_input($_POST['country']), true);
} else {
  // -Country-State Selector
// needs to be included earlier to set the success message in the messageStack
  require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_CREATE_ACCOUNT);

  $process = false;
  if (isset($_POST['action']) && ($_POST['action'] == 'process')) {
    $process = true;

		// Fecha nacimiento
		$_POST['dob'] = $_POST['dob_ind'].'/'.$_POST['dob_inm'].'/'.$_POST['dob_inY'];

    if (ACCOUNT_GENDER == 'true') {
      if (isset($_POST['gender'])) {
        $gender = tep_db_prepare_input($_POST['gender']);
      } else {
        $gender = false;
      }
    }
    $firstname = tep_db_prepare_input($_POST['firstname']);
	$lastname = tep_db_prepare_input($_POST['lastname']);
	$recargo_equivalencia = tep_db_prepare_input($_POST['recargo_equivalencia']);
    if (ACCOUNT_DOB == 'true') $dob = tep_db_prepare_input($_POST['dob']);
    $email_address = tep_db_prepare_input($_POST['email_address']);
    $email_address_re = tep_db_prepare_input($_POST['email_address_re']);

	if (defined('MODULE_ORDER_TOTAL_SPONSORSHIP_STATUS') && MODULE_ORDER_TOTAL_SPONSORSHIP_STATUS == 'true') {
	  $sponsorship_email = tep_db_prepare_input($_POST['sponsorship_email']);
	}
// BOF Separate Pricing Per Customer, added: field for tax id number
    if (ACCOUNT_COMPANY == 'true') {
    $company = tep_db_prepare_input($_POST['company']);
    $company_tax_id = tep_db_prepare_input($_POST['company_tax_id']);
    }
// EOF Separate Pricing Per Customer, added: field for tax id number
	/* [+] subir el archivo al servidor */

	if (is_uploaded_file($HTTP_POST_FILES['iae']['tmp_name'])) {
		$nombre_iae=str_replace('@', '_', $email_address);
		$nombre_iae=str_replace('.', '_', $nombre_iae);
		$partes_ruta = pathinfo($HTTP_POST_FILES['iae']['name']);
		$nombre_iae='_admin/iae/'.$nombre_iae.'.'.$partes_ruta['extension'];

		$nombre_iae=tep_db_prepare_input($nombre_iae);

		copy($HTTP_POST_FILES['iae']['tmp_name'], $nombre_iae);
		$varname = $_FILES['iae']['name'];
		$vartemp = $_FILES['iae']['tmp_name'];

		$mail = new \PHPMailer\PHPMailer\PHPMailer();
		$mail->Host = "localhost";
		$mail->From = STORE_OWNER_EMAIL_ADDRESS;
		$mail->FromName = TITLE;
		$mail->Subject = 'Archivo IAE de '.$email_address;
		$mail->AddAddress(STORE_OWNER_EMAIL_ADDRESS);
		if ($varname != "") {
			$mail->AddAttachment($vartemp, $varname);
		}
		$body = $email_address;
		$mail->Body = $body;
		$mail->IsHTML(true);
		$mail->Send();
	}
	/* [-] fin de subir archivo al servidor */
    //NIF start
    if (ACCOUNT_NIF == 'true')
	if ($_POST['cif']!='')
	$nif = tep_db_prepare_input($_POST['cif']);
	else
	$nif = tep_db_prepare_input($_POST['nif']);
    //NIF end
    $street_address = tep_db_prepare_input($_POST['street_address']);
    if (ACCOUNT_SUBURB == 'true') $suburb = tep_db_prepare_input($_POST['suburb']);
    $postcode = tep_db_prepare_input($_POST['postcode']);
    $city = tep_db_prepare_input($_POST['city']);
	$city_id = (int)tep_db_prepare_input($_POST['city_id']);

    if (ACCOUNT_STATE == 'true') {
      $state = tep_db_prepare_input($_POST['state']);
      if (isset($_POST['zone_id'])) {
        $zone_id = tep_db_prepare_input($_POST['zone_id']);
      } else {
        $zone_id = false;
      }
    }
    $country = tep_db_prepare_input($_POST['country']);

	$firstname = preg_replace('/\'/i', '', RemoveShouting($firstname,true));
    $lastname = preg_replace('/\'/i', '', RemoveShoutingLN($lastname,true));

		if( RGPD_ACCOUNT_DELETE_DOB == '2' )
          $dob = tep_db_prepare_input($_POST['dob']);

    $email_address = strtolower($email_address);
    $email_address_re = strtolower($email_address_re);
    $company = RemoveShoutingCN($company);
    $street_address = RemoveShouting($street_address);
    $street_address2 = RemoveShouting($street_address2);
    $suburb = RemoveShouting($suburb);
    $postcode = strtoupper($postcode);
    $city = RemoveShouting($city);
    $state = RemoveShouting($state);
    $nif = strtoupper($nif);

    $telephone = tep_db_prepare_input($_POST['telephone']);
    $fax = tep_db_prepare_input($_POST['fax']);
    if (isset($_POST['newsletter'])) {
      $newsletter = tep_db_prepare_input($_POST['newsletter']);
    } else {
      $newsletter = false;
    }
    $password = tep_db_prepare_input($_POST['password']);
    $confirmation = tep_db_prepare_input($_POST['confirmation']);

//-----   BEGINNING OF ADDITION: MATC   -----//
		$termsAgree = $rgpd->postFormCheckTermsGeneral();

		// Politica de privacidad
		if( $termsAgree == '' )
		{
          $error = true;
          $messageStack->add('create_account', ERROR_POLITICA . "<br/");
		}
//-----   END OF ADDITION: MATC   -----//

    if ($email_address != $email_address_re) {
      $error = true;
      $messageStack->add('create_account', 'Los e-mails no coinciden, pòr favor, verifique que ha escrito los dos e-mails iguales.');
    }

    if (ACCOUNT_GENDER == 'true') {
      if ( ($gender != 'm') && ($gender != 'f') ) {
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

		if( RGPD_ACCOUNT_DELETE_DOB == '2' )
		{		
			if( checkdate($_POST['dob_inm'], $_POST['dob_ind'], $_POST['dob_inY']) == false )
			{
				$error = true;
				$messageStack->add('create_account', ENTRY_DATE_OF_BIRTH_ERROR . "<br/");
			}
						
			// Comprobamos la mayoria de edad
			if( !date::greaterDate( $_POST['dob_inY'] .'/' . $_POST['dob_inm'] .'/' . $_POST['dob_ind'], 16 ) )
			{
				$error = true;
				$messageStack->add('create_account', ENTRY_DATE_OF_BIRTH_OLD_ERROR . "<br/");
			}
		}

    if (strlen($email_address) < ENTRY_EMAIL_ADDRESS_MIN_LENGTH) {
      $error = true;

      $messageStack->add('create_account', ENTRY_EMAIL_ADDRESS_ERROR);
    } elseif (tep_validate_email($email_address) == false) {
      $error = true;

      $messageStack->add('create_account', ENTRY_EMAIL_ADDRESS_CHECK_ERROR);
    } else {
      $check_email_query = tep_db_query("select count(*) as total from " . TABLE_CUSTOMERS . " where customers_email_address = '" . tep_db_input($email_address) . "'");
      $check_email = tep_db_fetch_array($check_email_query);
      if ($check_email['total'] > 0) {
        $error = true;

        $messageStack->add('create_account', ENTRY_EMAIL_ADDRESS_ERROR_EXISTS);
      }
    }

	
    //NIF start
    if (ACCOUNT_NIF == 'true'){
      if (($nif == "") && (ACCOUNT_NIF_REQ == 'true')) {
        $error = true;
        $messageStack->add('create_account', ENTRY_NO_NIF_ERROR);
      } else if ($nif == "")  {
        $error = true;
        $messageStack->add('create_account', ENTRY_FORMATO_NIF_ERROR);
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

        if (strlen($state) < ENTRY_STATE_MIN_LENGTH) {
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
    } elseif ($password != $confirmation) {
      $error = true;

      $messageStack->add('create_account', ENTRY_PASSWORD_ERROR_NOT_MATCHING);
    }

    if ($error == false) {
      $sql_data_array = array('customers_firstname' => $firstname,
							  'recargo_equivalencia' => $recargo_equivalencia,
                              'customers_lastname' => $lastname,
                              'customers_email_address' => $email_address,
                              'customers_telephone' => $telephone,
                              'customers_fax' => $fax,
							  'proveedor' => '1',
							  'proveedor_iae' => $nombre_iae,
                              'customers_newsletter' => $newsletter,
							  'customers_group_id' => '0',
							  'member_level' => '0',
							  'id_term_pivacy_general' => $rgpd->aTermGeneral['id_term_pivacy_general'],
                              'customers_password' => tep_encrypt_password($password));

		if( RGPD_ACCOUNT_DELETE_DOB == '2' )
              $sql_data_array['customers_dob'] = tep_date_raw($dob);

      if (ACCOUNT_GENDER == 'true') $sql_data_array['customers_gender'] = $gender;
      if (ACCOUNT_DOB == 'true') $sql_data_array['customers_dob'] = tep_date_raw($dob);
// BOF Separate Pricing Per Customer
   // if you would like to have an alert in the admin section when either a company name has been entered in
   // the appropriate field or a tax id number, or both then uncomment the next line and comment the default
   // setting: only alert when a tax_id number has been given
   //    if ( (ACCOUNT_COMPANY == 'true' && tep_not_null($company) ) || (ACCOUNT_COMPANY == 'true' && tep_not_null($company_tax_id) ) ) {
	  if ( ACCOUNT_COMPANY == 'true' && tep_not_null($company_tax_id)  ) {
      $sql_data_array['customers_group_ra'] = '1';
// entry_company_tax_id moved from table address_book to table customers in version 4.2.0
      $sql_data_array['entry_company_tax_id'] = $company_tax_id;
    }
// EOF Separate Pricing Per Customer

      tep_db_perform(TABLE_CUSTOMERS, $sql_data_array);

      $customer_id = tep_db_insert_id();

	  // Si hemos activado el newsletter activamos todos los terminos comerciales
	  if( $newsletter != false )
	  {
		  $aSubscribedAll = array_values( pharaonix_getArrayAssociativeSql( 'SELECT id_term_pivacy_trade, title, info FROM rgpd_term_privacy_trade WHERE language_id = "' . $languages_id . '"', 'id_term_pivacy_trade', 'title', false ) );

		  foreach( $aSubscribedAll as $aSubscribed )
		  {
			$nIdAll = $aSubscribed['id'];
			$sTitle = $aSubscribed['text'];
			  
			tep_db_perform( 'rgpd_account_term', array( 'customers_id' => $customer_id, 'id_term_pivacy_trade' => $nIdAll ) );

			tep_db_perform( 'rgpd_log_term_privacy', array(
				'customers_id' => $customer_id,
				'customers_mail' => $email_address,
				'ip' => tools::getIP(),
				'date' => date( 'Y-m-d H:i:s' ),
				'type' => 'comercial',
				'term_name' => $sTitle,
				'id_term_pivacy' => $nIdAll,
				'status' => 1
			) );
		  }
	  }

		// Añadimos log termino
		tep_db_perform( 'rgpd_log_term_privacy', array(
			'customers_id' => $customer_id,
			'customers_mail' => $email_address,
			'ip' => tools::getIP(),
			'date' => date( 'Y-m-d H:i:s' ),
			'type' => 'general',
			'term_name' => $rgpd->aTermGeneral['title'],
			'id_term_pivacy' => $rgpd->aTermGeneral['id_term_pivacy_general'],
			'status' => 1
		) );

      $sql_data_array = array('customers_id' => $customer_id,
                              'entry_firstname' => $firstname,
                              'entry_lastname' => $lastname,
							  'entry_telephone' => $telephone,
                              'entry_street_address' => $street_address,
                              'entry_postcode' => $postcode,
                              'entry_city' => $city,
							  'entry_city_id' => $city_id,
                              'entry_country_id' => $country);

      if (ACCOUNT_GENDER == 'true') $sql_data_array['entry_gender'] = $gender;
      if (ACCOUNT_COMPANY == 'true') $sql_data_array['entry_company'] = $company;
      //NIF start
      if (ACCOUNT_NIF == 'true') $sql_data_array['entry_nif'] = $nif;
      //NIF end
      if (ACCOUNT_SUBURB == 'true') $sql_data_array['entry_suburb'] = $suburb;
      if (ACCOUNT_STATE == 'true') {
        if ($zone_id > 0) {
          $sql_data_array['entry_zone_id'] = $zone_id;
          $sql_data_array['entry_state'] = '';
        } else {
          $sql_data_array['entry_zone_id'] = ($country == 195 ? STORE_ZONE : '0');
          $sql_data_array['entry_state'] = $state;
        }
      }

      tep_db_perform(TABLE_ADDRESS_BOOK, $sql_data_array);

      $address_id = tep_db_insert_id();

      tep_db_query("update " . TABLE_CUSTOMERS . " set customers_default_address_id = '" . (int)$address_id . "' where customers_id = '" . (int)$customer_id . "'");

      tep_db_query("insert into " . TABLE_CUSTOMERS_INFO . " (customers_info_id, customers_info_number_of_logons, customers_info_date_account_created) values ('" . (int)$customer_id . "', '0', now())");

	  	  if (defined('MODULE_ORDER_TOTAL_SPONSORSHIP_STATUS') && MODULE_ORDER_TOTAL_SPONSORSHIP_STATUS == 'true') {
	    if (tep_not_null($sponsorship_email) && tep_not_null($email_address) ) {
	      $cs_query = tep_db_query("select customers_id, customers_gender, customers_lastname, customers_firstname from " . TABLE_CUSTOMERS . " where customers_email_address = '" . tep_db_input($sponsorship_email) . "'");
	      $cs = tep_db_fetch_array($cs_query);

          tep_db_query("insert into " . TABLE_CUSTOMERS_SPONSORSHIP . " (customers_godson_id, customers_sponsorship_id, customers_email_address, customers_sponsorship_email, date_added) values ('" . (int)$customer_id . "', '" . (int)$cs['customers_id'] . "', '" . $email_address . "', '" . $sponsorship_email . "', now())");
	    }
	  }


	try{
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
	$bProfesional = true;
	require(DIR_WS_MODULES . 'UHtmlEmails/'. ULTIMATE_HTML_EMAIL_LAYOUT .'/create_account.php');
	$email_text = $html_email;
}else{
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
if(ULTIMATE_HTML_EMAIL_DEVELOPMENT_MODE === 'true'){
	//Save the contents of the generated html email to the harddrive in .htm file. This can be practical when developing a new layout.
	$TheFileName = 'Last_mail_from_create_account.php.htm';
	$TheFileHandle = fopen($TheFileName, 'w') or die("can't open error log file");
	fwrite($TheFileHandle, $email_text);
	fclose($TheFileHandle);
}
//---  End of addition: Ultimate HTML Emails  ---//
      tep_mail($name, $email_address, EMAIL_SUBJECT, $email_text, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);

		// Añadiamos los wishlist a tu cuenta
		if( tep_session_is_registered('aWishlist') )
		{
			foreach( $aWishlist as $value )
			{
				// Datos sql
				$aDatosSql = array( 'customers_id' => $customer_id, 'products_id' => $value['products_id'], 'atributo' => $value['atributo'] );

				// Insertamos
				tep_db_perform( 'wishlist', $aDatosSql );
			}

			unset($aWishlist);
			tep_session_unregister('aWishlist');
		}

      $alert_email_text = "Se informa que " . $firstname . " " . $lastname . " de la compañia: " . ((empty($company)) ? "-no especificada-" : $company) . " ha creado una cuenta.";
      tep_mail(STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, 'Profesional Registrado', $alert_email_text, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);

      tep_redirect(tep_href_link(FILENAME_CREATE_ACCOUNT_SUCCESS, 't=prf', 'SSL'));
    }
  }
 // +Country-State Selector
if (!isset($country)){$country = DEFAULT_COUNTRY;}
// -Country-State Selector

  $breadcrumb->add(NAVBAR_TITLE, tep_href_link('create_account_profesionales.php', '', 'SSL'));
?>

<?php require(DIR_THEME. 'html/header.php'); ?>

<?php
 // +Country-State Selector
require('includes/form_check.js.php');
require('includes/ajax.js.php');
// -Country-State Selector
?>


<!-- header_eof //-->

<!-- body //-->
<!-- left_navigation //-->
<?php require(DIR_THEME. 'html/column_left.php'); ?>
<!-- left_navigation_eof //-->
<!-- body_text //-->

<?php echo tep_draw_form('create_account', tep_href_link('create_account_profesionales.php', '', 'SSL'), 'post', 'onSubmit="return check_form(create_account);" enctype="multipart/form-data"') . tep_draw_hidden_field('action', 'process'); ?>

<div class="msje msje-info"><?php echo sprintf(TEXT_ORIGIN_LOGIN, tep_href_link(FILENAME_LOGIN, tep_get_all_get_params(), 'SSL')); ?></div>
<div class="msje msje-wrng"><?php echo TEXT_PROFESIONAL_WARNING; ?></div>
<?php
  if ($messageStack->size('create_account') > 0) {
?>
<div class="mensaje"><?php echo $messageStack->output('create_account'); ?></div>

<?php
  }
  ?>
  <?php

   /*************************************
  Champs pour entrer l'email du parrain
  **************************************/
  if (defined('MODULE_ORDER_TOTAL_SPONSORSHIP_STATUS') && MODULE_ORDER_TOTAL_SPONSORSHIP_STATUS == 'true') {
?>
<p class="campo"><label for="sponsorship_email"><?php echo ENTRY_SPONSORSHIP_EMAIL; ?></label> <?php echo tep_draw_input_field('sponsorship_email'); ?></p>
<?php }
?>
<div class="overflow">
<h4><?php echo CATEGORY_PERSONAL; ?> <span><?php echo FORM_REQUIRED_INFORMATION; ?></span></h4>
<?php
  if (ACCOUNT_GENDER == 'true') {
?>
<p class="campo"><label for="gender"><?php echo ENTRY_GENDER; ?></label>
									<?php echo tep_draw_radio_field('gender', 'm') . '&nbsp;&nbsp;' . MALE . '&nbsp;&nbsp;' . tep_draw_radio_field('gender', 'f') . '&nbsp;&nbsp;' . FEMALE . (tep_not_null(ENTRY_GENDER_TEXT) ? '<span class="inputRequirement">' . ENTRY_GENDER_TEXT . '</span>': ''); ?></p>
<?php
  }
?>
<p class="campo"><label for="firstname"><?php echo ENTRY_FIRST_NAME; ?></label>
										<?php echo tep_draw_input_field('firstname') . (tep_not_null(ENTRY_FIRST_NAME_TEXT) ? '<span class="inputRequirement">' . ENTRY_FIRST_NAME_TEXT . '</span>': ''); ?></p>

<p class="campo"><label for="lastname"><?php echo ENTRY_LAST_NAME; ?></label>
                <?php echo tep_draw_input_field('lastname') . (tep_not_null(ENTRY_LAST_NAME_TEXT) ? '<span class="inputRequirement">' . ENTRY_LAST_NAME_TEXT . '</span>': ''); ?>
              </p>
<!--NIF start-->
<?php  if (ACCOUNT_NIF == 'true') { ?>
<p class="campo"><label for="nif"><?php echo ENTRY_NIF; ?></label><?php echo tep_draw_input_field('nif') . ((tep_not_null(ENTRY_NIF_TEXT) && (ACCOUNT_NIF_REQ == 'true')) ? '<span class="inputRequirement">' . ENTRY_NIF_TEXT . '</span>': '') . (tep_not_null(ENTRY_NIF_EXAMPLE) ? '<span class="inputRequirement" style="left: auto; right: 0px;">' . ENTRY_NIF_EXAMPLE . '</span>': ''); ?></p>
<?php  }?>
<!--NIF end-->
<?php
  if( RGPD_ACCOUNT_DELETE_DOB == '2' )
	{
		echo '<div class="overflow campo" id="dobc">';
			echo '<label for="dob">' . ENTRY_DATE_OF_BIRTH . '</label>';
			echo '<p class="aflex row ax amiddle">';
				echo tep_draw_pull_down_date('dob_in', '', '', (isset($_POST['dob_inY'])? $_POST['dob_inY'] : date('Y') - 25), false, true, date('Y') - 120);
			echo '</p>';
		echo '</div>';
	}
?>
</div>
<div class="overflow">
<p class="campo"><label for="email_address"><?php echo ENTRY_EMAIL_ADDRESS; ?></label><?php echo tep_draw_input_field('email_address') . (tep_not_null(ENTRY_EMAIL_ADDRESS_TEXT) ? '<span class="inputRequirement">' . ENTRY_EMAIL_ADDRESS_TEXT . '</span>': ''); ?></p>
<p class="campo"><label for="email_address_re">Repita E-Mail</label><?php echo tep_draw_input_field('email_address_re') . (tep_not_null(ENTRY_EMAIL_ADDRESS_TEXT) ? '<span class="inputRequirement">' . ENTRY_EMAIL_ADDRESS_TEXT . '</span>': ''); ?></p>
</div>
<?php
  if (ACCOUNT_COMPANY == 'true') {
?>

<div class="overflow">
<h4><?php echo CATEGORY_COMPANY; ?></h4>
<p class="campo"><label for="company"><?php echo ENTRY_COMPANY; ?></label><?php echo tep_draw_input_field('company') . (tep_not_null(ENTRY_COMPANY_TEXT) ? '<span class="inputRequirement">' . ENTRY_COMPANY_TEXT . '</span>': ''); ?></p>
<p class="campo"><label for="cif">CIF</label><?php echo tep_draw_input_field('cif'); ?></p>
<p class="campo"><label for="company">IAE o Modelo 036:</label><input type="file" name="iae" id="iae" /></p>
<p class="campo xform"><?php echo tep_draw_checkbox_field('recargo_equivalencia', '1',false, 'id="recargo_equivalencia"'); ?><label for="recargo_equivalencia"><span></span><?php echo TEXT_ACOGERSE . ' (+' . MODULE_ORDER_TOTAL_REC_VALUE; ?>%)</label> </p>
<?php
  }
?>
</div>
<div class="overflow">
<h4><?php echo CATEGORY_ADDRESS; ?></h4>
<p class="campo"><label for="street_address"><?php echo ENTRY_STREET_ADDRESS; ?></label><?php echo tep_draw_input_field('street_address') . (tep_not_null(ENTRY_STREET_ADDRESS_TEXT) ? '<span class="inputRequirement">' . ENTRY_STREET_ADDRESS_TEXT . '</span>': ''); ?></p>
<?php
  if (ACCOUNT_SUBURB == 'true') {
?>
<p class="campo"><label for="suburb"><?php echo ENTRY_SUBURB; ?></label><?php echo tep_draw_input_field('suburb') . (tep_not_null(ENTRY_SUBURB_TEXT) ? '<span class="inputRequirement">' . ENTRY_SUBURB_TEXT . '</span>': ''); ?></p>
<?php
  }
?>
<p class="campo getCitiesFromCP"><label for="postcode"><?php echo ENTRY_POST_CODE; ?></label><?php echo tep_draw_input_field('postcode') .  (tep_not_null(ENTRY_POST_CODE_TEXT) ? '<span class="inputRequirement">' . ENTRY_POST_CODE_TEXT . '</span>': ''); ?></p>
<p class="campo city">
	<?php echo ajax_get_cities_html($country, $zone_id, $postcode, false, true); 	?>
</p>
<?php
  if (ACCOUNT_STATE == 'true') {
?>
<p class="campo getCitiesFromZone"><label for="state"><?php echo ENTRY_STATE; ?></label><span id="states">
                          <?php
				// +Country-State Selector
				echo ajax_get_zones_html($country,'',false);
				// -Country-State Selector
				?>
                        </span></p>
<?php
  }
?>
<div id="indicator"></div>
<p class="campo"><label for="country"><?php echo ENTRY_COUNTRY; ?></label>
                      <?php // +Country-State Selector ?>
                      <?php echo tep_get_country_list('country',$country,'onChange="getStates(this.value, \'states\');"') .  (tep_not_null(ENTRY_COUNTRY_TEXT) ? '<span class="inputRequirement">' . ENTRY_COUNTRY_TEXT . '</span>': ''); ?></p>
                      <?php // -Country-State Selector ?>
</div>
<div class="overflow">
<h4><?php echo CATEGORY_CONTACT; ?></h4>
<p class="campo"><label for="telephone"><?php echo ENTRY_TELEPHONE_NUMBER; ?></label><?php echo tep_draw_input_field('telephone') . (tep_not_null(ENTRY_TELEPHONE_NUMBER_TEXT) ? '<span class="inputRequirement">' . ENTRY_TELEPHONE_NUMBER_TEXT . '</span>': ''); ?></p>
<p class="campo"><label for="fax"><?php echo ENTRY_FAX_NUMBER; ?></label><?php echo tep_draw_input_field('fax') . (tep_not_null(ENTRY_FAX_NUMBER_TEXT) ? '<span class="inputRequirement">' . ENTRY_FAX_NUMBER_TEXT . '</span>': ''); ?></p>
</div>
<div class="overflow">
<h4><?php echo CATEGORY_PASSWORD; ?></h4>
<p class="campo"><label for="password"><?php echo ENTRY_PASSWORD; ?></label><?php echo tep_draw_password_field('password') . (tep_not_null(ENTRY_PASSWORD_TEXT) ? '<span class="inputRequirement">' . ENTRY_PASSWORD_TEXT . '</span>': ''); ?></p>
<p class="campo"><label for="confirmation"><?php echo ENTRY_PASSWORD_CONFIRMATION; ?></label><?php echo tep_draw_password_field('confirmation') . (tep_not_null(ENTRY_PASSWORD_CONFIRMATION_TEXT) ? '<span class="inputRequirement">' . ENTRY_PASSWORD_CONFIRMATION_TEXT . '</span>': ''); ?></p>
  </div>
<?php
	echo '<div class="overflow xform">';
		echo $rgpd->formCheckTermsGeneral();
		echo '<div class="column a12" style="margin-bottom: 8px;">';
		$aText = json_decode( str_replace( array( '\"', "\\\'" ), array('"', "'"), RGPD_TERMS_TRADE_TEXT_CHECK ), true );
		$aTextTooltop = json_decode( str_replace( array( '\"' ), array('"'), RGPD_TERMS_TRADE_TEXT ), true );
		echo '<div class="column a12 check rgpd-check"><input type="checkbox" name="newsletter" value="1" id="newsletter"><label style="margin-right: 0px;" for="newsletter"><span></span>' . str_replace( '{LINK}', tep_href_link('information.php', 'info_id=' . RGPD_TERMS_TRADE_INFO_ID),  html_entity_decode( $aText[$languages_id] ) ) . ' <i title="' . $aTextTooltop[$languages_id] . '" class="fa fa-exclamation-circle"></i></label></div>';
		echo '</div>';
	echo '</div>';
	
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
?>

<div class="botonera"><?php echo tep_image_submit('button_continue.gif', IMAGE_BUTTON_CONTINUE); ?></div>
</form>
<!-- body_text_eof //-->

<!-- right_navigation //-->
<?php require(DIR_THEME. 'html/column_right.php'); ?>
<!-- right_navigation_eof //-->
<!-- body_eof //-->

<!-- footer //-->
<?php require(DIR_THEME. 'html/footer.php'); ?>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
<?php
// +Country-State Selector
}
// -Country-State Selector
?>