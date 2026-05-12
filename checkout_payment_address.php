<?php
/*
  $Id: checkout_payment_address.php 1739 2007-12-20 00:52:16Z hpdl $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
*/

  require('includes/application_top.php');
  include_once('includes/functions/' . FILENAME_ACCOUNT_WORD_CLEANER);
  
  // +Country-State Selector
 // require(DIR_WS_FUNCTIONS . 'ajax.php');
if (isset($_POST['action']) && $_POST['action'] == 'getStates' && isset($_POST['country'])) {
	ajax_get_zones_html(tep_db_prepare_input($_POST['country']), true);
} else {
  // -Country-State Selector
// if the customer is not logged on, redirect them to the login page
  if (!tep_session_is_registered('customer_id')) {
    $navigation->set_snapshot();
    tep_redirect(tep_href_link(FILENAME_LOGIN, '', 'SSL'));
  }

// if there is nothing in the customers cart, redirect them to the shopping cart page
  if ($cart->count_contents() < 1) {
    tep_redirect(tep_href_link(FILENAME_SHOPPING_CART));
  }

// needs to be included earlier to set the success message in the messageStack
  require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_CHECKOUT_PAYMENT_ADDRESS);

  $error = false;
  $process = false;
  if (isset($_POST['action']) && ($_POST['action'] == 'submit')) {

// process a new billing address
    if (tep_not_null($_POST['firstname']) && tep_not_null($_POST['lastname']) && tep_not_null($_POST['street_address'])) {
      $process = true;

      if (ACCOUNT_GENDER == 'true') $gender = tep_db_prepare_input($_POST['gender']);
      if (ACCOUNT_COMPANY == 'true') $company = tep_db_prepare_input($_POST['company']);
	  $city_id = (int)tep_db_prepare_input($_POST['city_id']);
      $firstname = tep_db_prepare_input($_POST['firstname']);
      $lastname = tep_db_prepare_input($_POST['lastname']);
      $street_address = tep_db_prepare_input($_POST['street_address']);
      if (ACCOUNT_SUBURB == 'true') $suburb = tep_db_prepare_input($_POST['suburb']);
      $postcode = tep_db_prepare_input($_POST['postcode']);
      $city = tep_db_prepare_input($_POST['city']);
      $country = tep_db_prepare_input($_POST['country']);
      
      $firstname = RemoveShouting($firstname,true);
	  $lastname = RemoveShoutingLN($lastname,true);
   	  $company = RemoveShoutingCN($company);
	  $street_address = RemoveShouting($street_address);
	  $street_address2 = RemoveShouting($street_address2);
	  $suburb = RemoveShouting($suburb);
	  $postcode = strtoupper($postcode);
	  $city = RemoveShouting($city);
	  $state = RemoveShouting($state);
	
      if (ACCOUNT_STATE == 'true') {
        if (isset($_POST['zone_id'])) {
          $zone_id = tep_db_prepare_input($_POST['zone_id']);
        } else {
          $zone_id = false;
        }
        $state = tep_db_prepare_input($_POST['state']);
      }

      if (ACCOUNT_GENDER == 'true') {
        if ( ($gender != 'm') && ($gender != 'f') ) {
          $error = true;

          $messageStack->add('checkout_address', ENTRY_GENDER_ERROR);
        }
      }
  
      if (strlen($firstname) < ENTRY_FIRST_NAME_MIN_LENGTH) {
        $error = true;

        $messageStack->add('checkout_address', ENTRY_FIRST_NAME_ERROR);
      }

      if (strlen($lastname) < ENTRY_LAST_NAME_MIN_LENGTH) {
        $error = true;

        $messageStack->add('checkout_address', ENTRY_LAST_NAME_ERROR);
      }

      if (strlen($street_address) < ENTRY_STREET_ADDRESS_MIN_LENGTH) {
        $error = true;

        $messageStack->add('checkout_address', ENTRY_STREET_ADDRESS_ERROR);
      }

      if (strlen($postcode) < ENTRY_POSTCODE_MIN_LENGTH) {
        $error = true;

        $messageStack->add('checkout_address', ENTRY_POST_CODE_ERROR);
      }

	  if ($city_id == 0 && $city == '') {
  		$error = true;
  		$messageStack->add('checkout_address', ENTRY_CITY_ID_ERROR);
	  }

      if (ACCOUNT_STATE == 'true') {
				// +Country-State Selector
				if ($zone_id == 0) {
				// -Country-State Selector
	
					if (strlen($state) < ENTRY_STATE_MIN_LENGTH) {
						$error = true;
	
						$messageStack->add('checkout_address', ENTRY_STATE_ERROR);
					}
				}
      }

      if ( (is_numeric($country) == false) || ($country < 1) ) {
        $error = true;

        $messageStack->add('checkout_address', ENTRY_COUNTRY_ERROR);
      }


      if ($error == false) {
        $sql_data_array = array('customers_id' => $customer_id,
                                'entry_firstname' => $firstname,
                                'entry_lastname' => $lastname,
                                'entry_street_address' => $street_address,
                                'entry_postcode' => $postcode,
                                'entry_city' => $city,
								'entry_city_id' => $city_id,
                                'entry_country_id' => $country,
								'entry_NIF' => tep_db_prepare_input($_POST['nif']) );

        if (ACCOUNT_GENDER == 'true') $sql_data_array['entry_gender'] = $gender;
        if (ACCOUNT_COMPANY == 'true') $sql_data_array['entry_company'] = $company;
        if (ACCOUNT_SUBURB == 'true') $sql_data_array['entry_suburb'] = $suburb;
        if (ACCOUNT_STATE == 'true') {
          if ($zone_id > 0) {
            $sql_data_array['entry_zone_id'] = $zone_id;
            $sql_data_array['entry_state'] = '';
          } else {
            $sql_data_array['entry_zone_id'] = '0';
            $sql_data_array['entry_state'] = $state;
          }
        }

        if (!tep_session_is_registered('billto')) tep_session_register('billto');
		
        tep_db_perform(TABLE_ADDRESS_BOOK, $sql_data_array);

        $billto = tep_db_insert_id();

        if (tep_session_is_registered('payment')) tep_session_unregister('payment');

        tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'));
      }
// process the selected billing destination
    } elseif (isset($_POST['address'])) {
      $reset_payment = false;
      if (tep_session_is_registered('billto')) {
        if ($billto != $_POST['address']) {
          if (tep_session_is_registered('payment')) {
            $reset_payment = true;
          }
        }
      } else {
        tep_session_register('billto');
      }

      $billto = $_POST['address'];

      $check_address_query = tep_db_query("select count(*) as total from " . TABLE_ADDRESS_BOOK . " where customers_id = '" . $customer_id . "' and address_book_id = '" . $billto . "'");
      $check_address = tep_db_fetch_array($check_address_query);

      if ($check_address['total'] == '1') {
        if ($reset_payment == true) tep_session_unregister('payment');
        tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'));
      } else {
        tep_session_unregister('billto');
      }
// no addresses to select from - customer decided to keep the current assigned address
    } else {
      if (!tep_session_is_registered('billto')) tep_session_register('billto');
      $billto = $customer_default_address_id;

      tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'));
    }
  }

// if no billing destination address was selected, use their own address as default
  if (!tep_session_is_registered('billto')) {
    $billto = $customer_default_address_id;
  }
  if (!isset($country)){$country = DEFAULT_COUNTRY;}

  $breadcrumb->add(NAVBAR_TITLE_1, tep_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'));
  $breadcrumb->add(NAVBAR_TITLE_2, tep_href_link(FILENAME_CHECKOUT_PAYMENT_ADDRESS, '', 'SSL'));

  $addresses_count = tep_count_customer_address_book_entries();
?>
<?php require(DIR_THEME. 'html/header.php'); ?>

<script language="javascript"><!--
var selected;

function selectRowEffect(object, buttonSelect) {
  if (!selected) {
    if (document.getElementById) {
      selected = document.getElementById('defaultSelected');
    } else {
      selected = document.all['defaultSelected'];
    }
  }

  if (selected) selected.className = 'moduleRow';
  object.className = 'moduleRowSelected';
  selected = object;

// one button is not an array
  if (document.checkout_address.address[0]) {
    document.checkout_address.address[buttonSelect].checked=true;
  } else {
    document.checkout_address.address.checked=true;
  }
}

function rowOverEffect(object) {
  if (object.className == 'moduleRow') object.className = 'moduleRowOver';
}

function rowOutEffect(object) {
  if (object.className == 'moduleRowOver') object.className = 'moduleRow';
}

function check_form_optional(form_name) {
  var form = form_name;

  var firstname = form.elements['firstname'].value;
  var lastname = form.elements['lastname'].value;
  var street_address = form.elements['street_address'].value;

  if (firstname == '' && lastname == '' && street_address == '') {
    return true;
  } else {
    return check_form(form_name);
  }
}
//--></script>
<?php 
 // +Country-State Selector 
require('includes/form_check.js.php'); 
require('includes/ajax.js.php'); 
// -Country-State Selector
?>


<!-- body //-->
<!-- left_navigation //-->
<?php require(DIR_THEME. 'html/column_left.php'); ?>
<!-- left_navigation_eof //-->
<!-- body_text //-->
<div id="indicator" style="display:none;"></div>
<?php echo tep_draw_form('checkout_address', tep_href_link(FILENAME_CHECKOUT_PAYMENT_ADDRESS, '', 'SSL'), 'post', 'onSubmit="return check_form_optional(checkout_address);"'); ?>
            <h1 class="pageHeading"><?php echo HEADING_TITLE; ?></h1>
      
<?php
  if ($messageStack->size('checkout_address') > 0) {
?>
      <div class="mensaje"<?php echo $messageStack->output('checkout_address'); ?></div>
      
<?php
  }

  if ($process == false) {
?>

<h4><?php echo TABLE_HEADING_PAYMENT_ADDRESS; ?></h4>
<p class="informacion"><?php echo TEXT_SELECTED_PAYMENT_DESTINATION; ?></p>
<p class="direccion"><?php echo tep_address_label($customer_id, $billto, true, ' ', '<br />'); ?></p>
<?php
    if ($addresses_count > 1) {
?>
<h4><?php echo TABLE_HEADING_ADDRESS_BOOK_ENTRIES; ?></h4>
<p class="informacion"><?php echo TEXT_SELECT_OTHER_PAYMENT_DESTINATION; ?></p>
<p><?php echo '<strong>' . TITLE_PLEASE_SELECT . '</strong>'; ?></p>
<?php
      $radio_buttons = 0;
      
      $addresses_query = tep_db_query("select address_book_id, entry_firstname as firstname, entry_lastname as lastname, entry_company as company, entry_street_address as street_address, entry_telephone as telephone, entry_suburb as suburb, entry_city as city, entry_postcode as postcode, entry_state as state, entry_zone_id as zone_id, entry_country_id as country_id from " . TABLE_ADDRESS_BOOK . " where customers_id = '" . (int)$customer_id . "'");

      while ($addresses = tep_db_fetch_array($addresses_query)) {
        $format_id = tep_get_address_format_id($addresses['country_id']);
?>
<?php
       if ($addresses['address_book_id'] == $billto) {
          echo '                  <p id="defaultSelected" class="moduleRowSelected" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="selectRowEffect(this, ' . $radio_buttons . ')">' . "\n";
        } else {
          echo '                  <p class="moduleRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="selectRowEffect(this, ' . $radio_buttons . ')">' . "\n";
        }
?>
                    <strong><?php echo $addresses['firstname'] . ' ' . $addresses['lastname']; ?></strong> <?php echo tep_draw_radio_field('address', $addresses['address_book_id'], ($addresses['address_book_id'] == $billto)); ?><br />
					<?php echo tep_address_format($format_id, $addresses, true, ' ', ', '); ?>
<?php
        echo '</p>';
		$radio_buttons++;
      }
?>
<?php
    }
  }

  if ($addresses_count < MAX_ADDRESS_BOOK_ENTRIES) {
?>
<h4><?php echo TABLE_HEADING_NEW_PAYMENT_ADDRESS; ?></h4>
<p class="informacion"><?php echo TEXT_CREATE_NEW_PAYMENT_ADDRESS; ?></p>
<?php require(DIR_WS_COMPONENTS . 'checkout_new_address.php'); ?>
<?php
  }
?>
<p><?php echo '<strong>' . TITLE_CONTINUE_CHECKOUT_PROCEDURE . '</strong><br />' . TEXT_CONTINUE_CHECKOUT_PROCEDURE; ?></p>
<div class="botonera"><?php echo tep_draw_hidden_field('action', 'submit') . tep_image_submit('button_continue.gif', IMAGE_BUTTON_CONTINUE); ?>
	<?php
      if ($process == true) {
    ?>
	<?php echo '<a href="' . tep_href_link(FILENAME_CHECKOUT_PAYMENT_ADDRESS, '', 'SSL') . '">' . tep_image_button('button_back.gif', IMAGE_BUTTON_BACK) . '</a>'; ?>
    <?php
      }
    ?>
</div>
<?php 
define('CHECKOUT_BREADCRUMB', 'checkout_payment_bar');
?>

<?php include( 'theme/web/html/breadcrumb_checkout.php' ); ?>

</form>

<?php require(DIR_THEME. 'html/column_right.php'); ?>


<?php require(DIR_THEME. 'html/footer.php'); ?>


<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
<?php
// +Country-State Selector 
}
// -Country-State Selector 
?>
