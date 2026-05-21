<?php

/**
 * Fichero para la cración de un nuevo afiliado
 * #XCC-313-91043
 * @author Daniel Lucia <daniel.lucia@denox.es>
 */

use util\authentication\Customer;
use util\authentication\Exception\CustomerNotApprovedException;
use util\basket\BasketMysql;
use util\date as date;
use util\tools as tools;

//error_reporting(E_ALL);
//ini_set('display_errors', '1');

// Libreria
include 'includes/application_top.php';

if ((defined('AFFILLIATES_STATUS') && AFFILLIATES_STATUS == 'false') || !defined('AFFILLIATES_STATUS')) {
    tep_redirect(tep_href_link('index.php'));
}

if (tep_session_is_registered('customer_id')) {
    $sql = sprintf(
        'SELECT id FROM %s WHERE customers_id = %d',
        TABLE_AFFILIATES,
        $customer_id
    );

    $sql = tep_db_query($sql);

    if (tep_db_num_rows($sql) > 0) {
        tep_redirect(tep_href_link('account/account_affiliate.php'));
    }

}

$customersExists = false;

// Idioma
require DIR_WS_LANGUAGES . $language . '/' . FILENAME_CREATE_AFFILIATE;

if (!defined('AFFILLIATES_SALES_COMISSION')) {
    tep_redirect(tep_href_link('index.php', '', 'SSL'));
}

$process = false;

if (isset($_POST['action']) && ($_POST['action'] == 'process')) {

    // Fecha nacimiento
    $_POST['dob'] = $_POST['dob_ind'] . '/' . $_POST['dob_inm'] . '/' . $_POST['dob_inY'];

    $process = true;

    if (ACCOUNT_GENDER == 'true') {
        if (isset($_POST['gender'])) {
            $gender = tep_db_prepare_input($_POST['gender']);
        } else {
            $gender = false;
        }
    }

    // CAPTCHA //
    /*$secret = RECAPTCHA_PRIVATE_KEY;
    $remoteip = $_SERVER["REMOTE_ADDR"];
    $response = $_POST["g-recaptcha-response"];
    $url = "https://www.google.com/recaptcha/api/siteverify";

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, array(
    'secret' => $secret,
    'response' => $response,
    'remoteip' => $remoteip,
    ));

    $curlData = curl_exec($curl);
    curl_close($curl);
    $recaptcha = json_decode($curlData, true);

    if (!$recaptcha["success"]) {
    $messageStack->add('contact_error', ERROR_CAPTCHA, 'error', true);
    $error = true;
    }*/

    $username_social_networks = tep_db_prepare_input($_POST['username_social_networks']);
    if ($username_social_networks == '') {
        $error = true;
        $messageStack->add('create_account', ENTRY_USER_NAME_ERROR . "<br/>");
    }

    $social_networks_list = tep_db_prepare_input($_POST['social_networks_list']);
    if ($social_networks_list == '') {
        $error = true;
        $messageStack->add('create_account', ENTRY_SOCIAL_NETWORKS_ERROR . "<br/>");
    }

    $firstname = tep_db_prepare_input($_POST['firstname']);
    $recargo_equivalencia = tep_db_prepare_input($_POST['recargo_equivalencia']);
    $lastname = tep_db_prepare_input($_POST['lastname']);

    if (RGPD_ACCOUNT_DELETE_DOB == '2') {
        $dob = tep_db_prepare_input($_POST['dob']);
    }

    if (!tep_session_is_registered('customer_id')) {
        $email_address = tep_db_prepare_input($_POST['email_address']);
    } else {
        $email_address = tep_db_prepare_input($_SESSION['sCustomersEmailAddress']);
    }

    if (ACCOUNT_COMPANY == 'true') {
        $company = tep_db_prepare_input($_POST['company']);
        $company_tax_id = tep_db_prepare_input($_POST['company_tax_id']);
    }
    // EOF Separate Pricing Per Customer, added: field for tax id number
    //NIF start
    if (ACCOUNT_NIF == 'true') {
        if ($_POST['cif'] != '') {
            $nif = tep_db_prepare_input($_POST['cif']);
        } else {
            $nif = tep_db_prepare_input($_POST['nif']);
        }
    }
    //NIF end

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
    if ($termsAgree == '') {
        if (!tep_session_is_registered('customer_id')) {
            $error = true;
            $messageStack->add('create_account', ERROR_POLITICA . "<br/>");
        }
    }
    //-----   END OF ADDITION: MATC   -----//

    if (strlen((string)$firstname) < ENTRY_FIRST_NAME_MIN_LENGTH) {
        if (!tep_session_is_registered('customer_id')) {
            $error = true;
            $messageStack->add('create_account', ENTRY_FIRST_NAME_ERROR . "<br/>");
        }
    }

    if (strlen((string)$lastname) < ENTRY_LAST_NAME_MIN_LENGTH) {
        if (!tep_session_is_registered('customer_id')) {
            $error = true;
            $messageStack->add('create_account', ENTRY_LAST_NAME_ERROR . "<br/>");
        }
    }

    if (RGPD_ACCOUNT_DELETE_DOB == '2') {

        if (checkdate((int)($_POST['dob_inm'] ?? 0), (int)($_POST['dob_ind'] ?? 0), (int)($_POST['dob_inY'] ?? 0)) == false) {
            if (!tep_session_is_registered('customer_id')) {
                $error = true;
                $messageStack->add('create_account', ENTRY_DATE_OF_BIRTH_ERROR . "<br/>");
            }
        }

        // Comprobamos la mayoria de edad
        if (!isset($_POST['dob_inY']) || !isset($_POST['dob_inm']) || !isset($_POST['dob_ind']) || !date::greaterDate($_POST['dob_inY'] . '/' . $_POST['dob_inm'] . '/' . $_POST['dob_ind'], 16)) {
            if (!tep_session_is_registered('customer_id')) {
                $error = true;
                $messageStack->add('create_account', ENTRY_DATE_OF_BIRTH_OLD_ERROR . "<br/>");
            }
        }
    }

    if (strlen($email_address) < ENTRY_EMAIL_ADDRESS_MIN_LENGTH) {
        if (!tep_session_is_registered('customer_id')) {
            $error = true;
            $messageStack->add('create_account', ENTRY_EMAIL_ADDRESS_ERROR . "<br/>");
        }
    } elseif (tep_validate_email($email_address) == false) {
        if (!tep_session_is_registered('customer_id')) {
            $error = true;
            $messageStack->add('create_account', ENTRY_EMAIL_ADDRESS_CHECK_ERROR . "<br/>");
        }
    } else {
        $check_email_query = tep_db_query("select count(*) as total from " . TABLE_CUSTOMERS . " where LOWER(customers_email_address) = '" . strtolower(tep_db_input($email_address)) . "' AND guest_account != '1'");
        $check_email = tep_db_fetch_array($check_email_query);
        if ($check_email['total'] > 0) {
            $customersExists = true;
        }
    }

    $check_username_query = tep_db_query("select count(*) as total from " . TABLE_AFFILIATES . " where LOWER(username_social_networks) = '" . strtolower(tep_db_prepare_input($_POST['username_social_networks'])) . "'");
    $check_username = tep_db_fetch_array($check_username_query);
    if ($check_username['total'] > 0) {
        $error = true;
        $messageStack->add('create_account', "El nombre de usuario ya existe.<br/>");
    }

    //NIF start
    if (ACCOUNT_NIF == 'true') {
        /**
         * Validar el DNI/NIF/NIE
         * @author Daniel Lucia <daniel.lucia@denox.es>
         */
        if (defined('VALIDATE_CIF') && VALIDATE_CIF == 'true') {
            if (checkCif($nif) <= 0) {
                $error = true;
                $messageStack->add('create_account', ENTRY_FORMATO_NIF_ERROR . "<br/>");
            }
        } else {
            if (($nif == "") && (ACCOUNT_NIF_REQ == 'true')) {
                $error = true;
                $messageStack->add('create_account', ENTRY_NO_NIF_ERROR . "<br/>");
            }
        }
    }
    //NIF end

    if (strlen((string)$telephone) < ENTRY_TELEPHONE_MIN_LENGTH) {
        if (!tep_session_is_registered('customer_id')) {
            $error = true;
            $messageStack->add('create_account', ENTRY_TELEPHONE_NUMBER_ERROR . "<br/>");
        }
    }

    if (!tep_session_is_registered('customer_id')) {
        if (strlen($password) < ENTRY_PASSWORD_MIN_LENGTH) {
            $error = true;
            $messageStack->add('create_account', ENTRY_PASSWORD_ERROR . "<br/>");
        } elseif ($password != $confirmation) {
            $error = true;
            $messageStack->add('create_account', ENTRY_PASSWORD_ERROR_NOT_MATCHING . "<br/>");
        }
    }

    if ($error == false) {

        /**
         * Si el cliente ya existe, no lo registramos
         * hacemos una consulta luego
         * y obtenemos su id de cliente
         * @author Daniel Lucia <daniel.lucia@denox.es>
         */
        if (!$customersExists) {
            $sql_data_array = array(
                'customers_firstname' => $firstname,
                'customers_lastname' => $lastname,
                'customers_language_id' => $languages_id,
                'customers_email_address' => strtolower($email_address),
                'customers_telephone' => $telephone,
                'customers_fax' => $fax,
                'guest_account' => 0,
                'recargo_equivalencia' => $recargo_equivalencia,
                'id_term_pivacy_general' => $rgpd->aTermGeneral['id_term_pivacy_general'],
                'customers_password' => tep_encrypt_password($password),
            );

            if (ACCOUNT_GENDER == 'true') {
                $sql_data_array['customers_gender'] = $gender;
            }

            if (RGPD_ACCOUNT_DELETE_DOB == '2') {
                $sql_data_array['customers_dob'] = tep_date_raw($dob);
            }

            if (ACCOUNT_COMPANY == 'true' && tep_not_null($company_tax_id)) {
                $sql_data_array['customers_group_ra'] = '1';
                $sql_data_array['entry_company_tax_id'] = $company_tax_id;
            }

            tep_db_perform(TABLE_CUSTOMERS, $sql_data_array);

            $customer_id = tep_db_insert_id();

            $sql_data_array = array(
                'customers_id' => $customer_id,
                'entry_firstname' => $firstname,
                'entry_lastname' => $lastname,
                'entry_city_id' => 0,
                'entry_nif' => $nif,
            );

            tep_db_perform(TABLE_ADDRESS_BOOK, $sql_data_array);
            $address_id = tep_db_insert_id();

            tep_db_query("update " . TABLE_CUSTOMERS . " set customers_default_address_id = '" . (int) $address_id . "' where customers_id = '" . (int) $customer_id . "'");
            tep_db_query("insert into " . TABLE_CUSTOMERS_INFO . " (customers_info_id, customers_info_number_of_logons, customers_info_date_account_created) values ('" . (int) $customer_id . "', '0', now())");

            // Si hemos activado el newsletter activamos todos los terminos comerciales
            if ($newsletter != false) {
                $aSubscribedAll = array_values(pharaonix_getArrayAssociativeSql('SELECT id_term_pivacy_trade, title, info FROM rgpd_term_privacy_trade WHERE language_id = "' . $languages_id . '"', 'id_term_pivacy_trade', 'title', false));

                foreach ($aSubscribedAll as $aSubscribed) {
                    $nIdAll = $aSubscribed['id'];
                    $sTitle = $aSubscribed['text'];

                    tep_db_perform('rgpd_account_term', array('customers_id' => $customer_id, 'id_term_pivacy_trade' => $nIdAll));

                    tep_db_perform('rgpd_log_term_privacy', array(
                        'customers_id' => $customer_id,
                        'customers_mail' => strtolower($email_address),
                        'ip' => tools::getIP(),
                        'date' => date('Y-m-d H:i:s'),
                        'type' => 'comercial',
                        'term_name' => $sTitle,
                        'id_term_pivacy' => $nIdAll,
                        'status' => 1,
                    ));
                }

                // Añadimos el cliente al subscribers
                tep_db_perform('subscribers', array(
                    'customers_id' => $customer_id,
                    'subscribers_firstname' => $firstname,
                    'subscribers_lastname' => $lastname,
                    'subscribers_email_address' => strtolower($email_address),
                    'language_id' => $languages_id,
                    'date_account_created' => date('Y-m-d H:i:s'),
                    'customers_newsletter' => 1,
                    'status_sent1' => 1,
                    'source_import' => 'subscribe_newsletter',
                ));
            }

            // Añadimos log termino
            tep_db_perform('rgpd_log_term_privacy', array(
                'customers_id' => $customer_id,
                'customers_mail' => strtolower($email_address),
                'ip' => tools::getIP(),
                'date' => date('Y-m-d H:i:s'),
                'type' => 'general',
                'term_name' => $rgpd->aTermGeneral['title'],
                'id_term_pivacy' => $rgpd->aTermGeneral['id_term_pivacy_general'],
                'status' => 1,
            ));
        } else {

            $check_email_query = tep_db_query("select customers_id from " . TABLE_CUSTOMERS . " where LOWER(customers_email_address) = '" . strtolower(tep_db_input($email_address)) . "' AND guest_account != '1'");
            $check_email = tep_db_fetch_array($check_email_query);
            $customer_id = $check_email['customers_id'];
        }

        /**
         * Comprobamos si ya está registrado
         * como afiliado. De ser así, retornamos y
         * mostramos un error
         */

        $check_affiliate_query = tep_db_query(
            sprintf(
                'SELECT COUNT(*) as total FROM %s WHERE customers_id = %d',
                TABLE_AFFILIATES,
                $customer_id
            )
        );
        $check_affiliate = tep_db_fetch_array($check_affiliate_query);

        if ($check_affiliate['total'] > 0) {

            $messageStack->add('create_account', AFFILIATE_EXISTS . "<br/>");
        } else {

            $sales_comission = AFFILLIATES_SALES_COMISSION;
            $sales_comission_eu = AFFILLIATES_SALES_COMISSION_EU;
            $coupon_value = AFFILLIATES_VALUE_COUPON;
            $coupon = Affiliates::affiliatesGenerateCoupon($username_social_networks);

            $data_affiliates = [
                'customers_id' => $customer_id,
                'username_social_networks' => $username_social_networks,
                'social_networks_list' => $social_networks_list,
                'sales_comission' => $sales_comission,
                'sales_comission_eu' => $sales_comission_eu,
                'coupon_value' => $coupon_value,
                'telephone' => $telephone,
                'nif' => $nif,
                'affiliate_active' => 0,
                'date_created' => 'now()',
                'date_modified' => 'now()',
                'coupon' => $coupon,
            ];

            tep_db_perform(TABLE_AFFILIATES, $data_affiliates);

            $affiliate = true;
            tep_session_register('affiliate');

            // Idioma para el email
            include DIR_WS_LANGUAGES . $language . '/modules/email/new-affiliate.php';

            // Email
            $mail = new util\mail();

            // Html del email
            $mail->includeEmail('new-affiliate.php', array(
                'name' => $firstname . ' ' . $lastname,
            ));

            tep_mail($name, $email_address, EMAIL_SUBJECT, $mail->html, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);

			try{
				$customerCore = Customer::createById((int)$customer_id);
				$customerCore->login();
				$cookie->password = $customerCore->getPassword();
			} catch (CustomerNotApprovedException $e) {
				$cart->restore_contents();
			}

            tep_redirect(tep_href_link(FILENAME_CREATE_AFFILIATE_SUCCESS, 'exist-account=' . ($customersExists ? 1 : 0), 'SSL'));
        }
    }
}
// +Country-State Selector
if (!isset($country)) {
    $country = DEFAULT_COUNTRY;
}
// -Country-State Selector

$breadcrumb->add(NAVBAR_TITLE, tep_href_link(FILENAME_CREATE_AFFILIATE, '', 'SSL'));

include DIR_THEME . 'html/header.php';
include "includes/form_check.js.php";
include DIR_THEME . 'html/column_left.php';

if ($messageStack->size('create_account') > 0) {
    echo '<div class="xmessage xmessage-error"><input type="checkbox"/><i class="fa fa-times"></i><div><i class="fa fa-exclamation-circle"></i>' . strip_tags($messageStack->output('create_account')) . '</div></div>';
}

echo tep_draw_form('create_account', tep_href_link(FILENAME_CREATE_AFFILIATE, '', 'SSL'), 'post', 'onSubmit="return check_form(create_account);" class="ax row xform atop" id="create_account"');
echo tep_draw_hidden_field('action', 'process');

echo '<div class="col a12 ax row sp10">';
echo '<div class="affiliates-description">';
echo AFFILIATES_DESCRIPTION;
echo '</div>';
echo '</div>';

$classColumns = tep_session_is_registered('customer_id') ? 'a12' : 'a06';

echo '<div class="col ' . $classColumns . ' ax row sp10 m12" style="padding-right: 10px;">';
echo '<h4 class="col a12">' . CATEGORY_PERSONAL . '</h4>';

echo '<div class="col a12 aflex row ax amiddle">';
echo tep_draw_input_field('username_social_networks', '', 'class="column" placeholder="' . str_replace(':', '', ENTRY_USER_NAME) . '"');
echo tep_not_null(ENTRY_USER_NAME_TEXT) ? '<span class="inputRequirement col afixed">' . ENTRY_USER_NAME_TEXT . '</span>' : '';
echo '</div>';

echo '<div class="col a12 aflex row ax amiddle">';
echo tep_draw_textarea_field('social_networks_list', 'soft', '60', '5', '', 'class="column" placeholder="' . str_replace(':', '', ENTRY_SOCIAL_NETWORKS) . '"');
echo tep_not_null(ENTRY_SOCIAL_NETWORKS_TEXT) ? '<span class="inputRequirement col afixed">' . ENTRY_SOCIAL_NETWORKS_TEXT . '</span>' : '';
echo '</div>';

echo '</div>';

if (!tep_session_is_registered('customer_id')) {
    echo '<div class="col a06 ax row sp10 m12" style="padding-right: 10px;">';
    echo '<h4 class="col a12">' . CATEGORY_PERSONAL . '</h4>';

    echo '<div class="col a12 aflex row ax amiddle">';
    echo tep_draw_input_field('firstname', '', 'class="column" placeholder="' . str_replace(':', '', ENTRY_FIRST_NAME) . '"');
    echo tep_not_null(ENTRY_FIRST_NAME_TEXT) ? '<span class="inputRequirement col afixed">' . ENTRY_FIRST_NAME_TEXT . '</span>' : '';
    echo '</div>';

    echo '<div class="col a12 aflex row ax amiddle">';
    echo tep_draw_input_field('lastname', '', 'class="column" placeholder="' . str_replace(':', '', ENTRY_LAST_NAME) . '"');
    echo tep_not_null(ENTRY_LAST_NAME_TEXT) ? '<span class="inputRequirement col afixed">' . ENTRY_LAST_NAME_TEXT . '</span>' : '';
    echo '</div>';

    if (ACCOUNT_NIF == 'true') {
        echo '<div class="col a12 aflex row ax amiddle">';
        echo tep_draw_input_field('nif', '', 'class="column" placeholder="' . str_replace(':', '', ENTRY_NIF) . '" required ');
        echo tep_not_null(ENTRY_NIF_TEXT) && (ACCOUNT_NIF_REQ == 'true') ? '<span class="inputRequirement col afixed">' . ENTRY_NIF_TEXT . '</span>' : '';
        echo tep_not_null(ENTRY_NIF_EXAMPLE) ? '<span class="inputRequirement col afixed">' . ENTRY_NIF_EXAMPLE . '</span>' : '';
        echo '</div>';
    }

    echo '<div class="company-hidden col a12 row sp10" style="display: none; padding: 0px;">';
    echo '<div class="col a12">';
    echo tep_draw_checkbox_field('recargo_equivalencia', '1', false, 'id="recargo_equivalencia"') . ' <label for="recargo_equivalencia"><span></span>Me acojo al recargo de equivalencia (+' . MODULE_ORDER_TOTAL_REC_VALUE . '%)</label>';
    echo '</div>';
    echo '</div>';

    if (RGPD_ACCOUNT_DELETE_DOB == '2') {
        echo '<div class="col a12 aflex row ax amiddle">';
        echo tep_draw_pull_down_date('dob_in', '', '', (isset($_POST['dob_inY']) ? $_POST['dob_inY'] : date('Y') - 25), false, true, date('Y') - 120);
        echo tep_not_null(ENTRY_DATE_OF_BIRTH_TEXT) ? '<span class="inputRequirement col afixed">' . ENTRY_DATE_OF_BIRTH_TEXT . '</span>' : '';
        echo '</div>';
    }

    echo '<div class="col a12 aflex row ax amiddle">';
    echo tep_draw_input_field('email_address', '', 'class="column" placeholder="' . str_replace(':', '', ENTRY_EMAIL_ADDRESS) . '"');
    echo tep_not_null(ENTRY_EMAIL_ADDRESS_TEXT) ? '<span class="inputRequirement col afixed">' . ENTRY_EMAIL_ADDRESS_TEXT . '</span>' : '';
    echo '</div>';

    echo '<div class="col a12 aflex row ax amiddle" style="display: none;">';
    echo tep_draw_input_field('email_address_re', '', 'class="column" placeholder="Repita E-Mail"');
    echo tep_not_null(ENTRY_EMAIL_ADDRESS_TEXT) ? '<span class="inputRequirement col afixed">' . ENTRY_EMAIL_ADDRESS_TEXT . '</span>' : '';
    echo '</div>';

    echo '<div class="col a12 aflex row ax amiddle">';
    echo tep_draw_input_field('telephone', '', 'class="column" placeholder="' . str_replace(':', '', ENTRY_TELEPHONE_NUMBER) . '"');
    echo tep_not_null(ENTRY_TELEPHONE_NUMBER_TEXT) ? '<span class="inputRequirement col afixed">' . ENTRY_TELEPHONE_NUMBER_TEXT . '</span>' : '';
    echo '</div>';

    echo '</div>';
}

if (!tep_session_is_registered('customer_id')) {
    echo '<div class="col a12 ax row sp10">';
    echo '<h4 class="col a12">' . CATEGORY_PASSWORD . '</h4>';
    echo '<div class="col a06 aflex row ax amiddle m12">';
    echo tep_draw_password_field('password', '', 'class="column" placeholder="' . str_replace(':', '', ENTRY_PASSWORD) . '"');
    echo tep_not_null(ENTRY_PASSWORD_TEXT) ? '<span class="inputRequirement col afixed">' . ENTRY_PASSWORD_TEXT . '</span>' : '';
    echo '</div>';
    echo '<div class="col a06 aflex row ax amiddle m12">';
    echo tep_draw_password_field('confirmation', '', 'class="column" placeholder="' . str_replace(':', '', ENTRY_PASSWORD_CONFIRMATION) . '"');
    echo tep_not_null(ENTRY_PASSWORD_CONFIRMATION_TEXT) ? '<span class="inputRequirement col afixed">' . ENTRY_PASSWORD_CONFIRMATION_TEXT . '</span>' : '';
    echo '</div>';

    echo '</div>';
}

echo '<div class="col a12 ax row sp10 amiddle" style="border-top: 1px solid #e1e7ec; padding-top: 20px; margin-top: 20px;">';
if (!tep_session_is_registered('customer_id')) {
    echo '<div class="col a07 m12">';
    echo $rgpd->formCheckTermsGeneral('termsAgree', true);
    echo '<div class="col a12" style="margin-bottom: 8px;">';
    $aText = json_decode(str_replace(array('\"', "\\\'"), array('"', "'"), RGPD_TERMS_TRADE_TEXT_CHECK), true);
    $aTextTooltop = json_decode(str_replace(array('\"'), array('"'), RGPD_TERMS_TRADE_TEXT), true);

    echo '<div class="col a12 xform check rgpd-check"><input type="checkbox" name="newsletter" value="1" id="newsletter"><label style="margin-right: 0px;" for="newsletter"><span></span>' . str_replace('{LINK}', tep_href_link('information.php', 'info_id=' . RGPD_TERMS_TRADE_INFO_ID), html_entity_decode($aText[$languages_id])) . ' <i title="' . $aTextTooltop[$languages_id] . '" class="fa fa-exclamation-circle"></i></label></div>';
    echo '</div>';
    echo '</div>';
} else {
    echo '<div class="col a07 m12"></div>';
}

echo '<div class="col a05 m12">';
echo '<div class="col a12 tright"><span class="trojo">' . FORM_REQUIRED_INFORMATION . '</span>&nbsp;&nbsp;';
//echo '<input class="bton-dflt verde tblanco hv9" id="TheSubmitButton" type="submit" value="' . IMAGE_BUTTON_CONTINUE . '" />';

echo '<button type="submit" class="bton-dflt verde tblanco hv9">' . IMAGE_BUTTON_CONTINUE . '</button>';

echo '</div>';
echo '</div>';
echo '</div>';
echo '</form>';

// Mayor de edad
if (RGPD_ACCOUNT_DELETE_DOB == '1' && $_SERVER['REQUEST_METHOD'] == 'GET') {
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
    $sHtml .= '<a href="' . tep_href_link('index.php') . '" id="rgpd-dob-dngt" class="red">' . RGPD_WINDOW_MODAL_DOB_DENEGATE . '</a> ';
    $sHtml .= '</div>';
    $sHtml .= '</div>';
    $sHtml .= '</div>';

    echo $sHtml;
    echo '<a href="#rgpd-wndw" data-modal="true" class="mgp-inln mgp-auto" style="display: none;"></a>';
}

include DIR_THEME . 'html/column_right.php';
include DIR_THEME . 'html/footer.php';
include DIR_WS_INCLUDES . 'application_bottom.php';
