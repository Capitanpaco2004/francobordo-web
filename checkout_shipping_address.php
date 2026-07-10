<?php
/*
  $Id: checkout_shipping_address.php 1739 2007-12-20 00:52:16Z hpdl $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
*/

  require('includes/application_top.php');
  include_once('includes/functions/' . FILENAME_ACCOUNT_WORD_CLEANER);

  // +Country-State Selector
  //require(DIR_WS_FUNCTIONS . 'ajax.php');
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
  require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_CHECKOUT_SHIPPING_ADDRESS);

  require(DIR_WS_CLASSES . 'order.php');
  $order = new order;

// if the order contains only virtual products, forward the customer to the billing page as
// a shipping address is not needed
  if ($order->content_type == 'virtual') {
    if (!tep_session_is_registered('shipping')) tep_session_register('shipping');
    $shipping = false;
    if (!tep_session_is_registered('sendto')) tep_session_register('sendto');
    $sendto = false;
    tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'));
  }

  $error = false;
  $process = false;
  if (isset($_POST['action']) && ($_POST['action'] == 'submit')) {
// process a new shipping address
    if (tep_not_null($_POST['firstname']) && tep_not_null($_POST['lastname']) && tep_not_null($_POST['street_address'])) {
      $process = true;

	  //Quito la session del impuesto por si cambia que se recalcule
	  tep_session_unregister('tax_rate');
      if (ACCOUNT_GENDER == 'true') $gender = tep_db_prepare_input($_POST['gender']);
      if (ACCOUNT_COMPANY == 'true') $company = tep_db_prepare_input($_POST['company']);
      //NIF start
      if (ACCOUNT_NIF == 'true') $nif = tep_db_prepare_input($_POST['nif']);
      //NIF end
	  $city_id = (int)tep_db_prepare_input($_POST['city_id']);
      $firstname = tep_db_prepare_input($_POST['firstname']);
      $lastname = tep_db_prepare_input($_POST['lastname']);
      $street_address = tep_db_prepare_input($_POST['street_address']);
	  $telephone = tep_db_prepare_input($_POST['telephone']);
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

      // Formato de CP (parche postcode-fix 2026-07-09): España = 5 dígitos con provincia 01-52; resto de países, sin '@'
      $postcode = trim($postcode);
      if ((int)$country == 195) {
        if (!preg_match('/^(0[1-9]|[1-4][0-9]|5[0-2])[0-9]{3}$/', $postcode)) {
          $error = true;

          $messageStack->add('checkout_address', defined('ENTRY_POST_CODE_FORMAT_ERROR') ? ENTRY_POST_CODE_FORMAT_ERROR : 'El código postal no parece válido para el país seleccionado. En España son 5 dígitos (ej. 03700).');
        }
      } elseif ((int)$country == 171) {
        if (preg_match('/^([0-9]{4})\s*-?\s*([0-9]{3})$/', $postcode, $_cpm)) {
          $postcode = $_cpm[1] . '-' . $_cpm[2];
        } else {
          $error = true;

          $messageStack->add('checkout_address', defined('ENTRY_POST_CODE_FORMAT_ERROR') ? ENTRY_POST_CODE_FORMAT_ERROR : 'El código postal no parece válido para el país seleccionado. En Portugal son 7 dígitos (ej. 4400-123).');
        }
      } elseif ($postcode == '' || strpos($postcode, '@') !== false) {
        $error = true;

        $messageStack->add('checkout_address', defined('ENTRY_POST_CODE_FORMAT_ERROR') ? ENTRY_POST_CODE_FORMAT_ERROR : 'El código postal no parece válido para el país seleccionado. En España son 5 dígitos (ej. 03700).');
      }

	  if ($city_id == 0 && $city == '') {
  		$error = true;
  		$messageStack->add('checkout_address', ENTRY_CITY_ID_ERROR);
	  }
      elseif (strlen($city) < ENTRY_CITY_MIN_LENGTH && strlen($city) > 0) {
        $error = true;

        $messageStack->add('checkout_address', ENTRY_CITY_ERROR);
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
								'entry_telephone' => $telephone,
                                'entry_postcode' => $postcode,
                                'entry_city' => $city,
                                'entry_country_id' => $country,
  						  		'entry_city_id' => $city_id);

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
            $sql_data_array['entry_zone_id'] = '0';
            $sql_data_array['entry_state'] = $state;
          }
        }

        if (!tep_session_is_registered('sendto')) tep_session_register('sendto');

        tep_db_perform(TABLE_ADDRESS_BOOK, $sql_data_array);

        $sendto = tep_db_insert_id();

        if (tep_session_is_registered('shipping')) tep_session_unregister('shipping');

        tep_redirect(tep_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL'));
      }
// process the selected shipping destination
    } elseif (isset($_POST['address'])) {
      $reset_shipping = false;
      if (tep_session_is_registered('sendto')) {
        if ($sendto != $_POST['address']) {
          if (tep_session_is_registered('shipping')) {
            $reset_shipping = true;
          }
        }
      } else {
        tep_session_register('sendto');
      }

      $sendto = $_POST['address'];

      $check_address_query = tep_db_query("select count(*) as total from " . TABLE_ADDRESS_BOOK . " where customers_id = '" . (int)$customer_id . "' and address_book_id = '" . (int)$sendto . "'");
      $check_address = tep_db_fetch_array($check_address_query);

      if ($check_address['total'] == '1') {
        if ($reset_shipping == true) tep_session_unregister('shipping');
        tep_redirect(tep_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL'));
      } else {
        tep_session_unregister('sendto');
      }
    } else {
      if (!tep_session_is_registered('sendto')) tep_session_register('sendto');
      $sendto = $customer_default_address_id;

      tep_redirect(tep_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL'));
    }
  }

// if no shipping destination address was selected, use their own address as default
  if (!tep_session_is_registered('sendto')) {
    $sendto = $customer_default_address_id;
  }
  // +Country-State Selector 
  if (!isset($country)){$country = DEFAULT_COUNTRY;}
  // -Country-State Selector

  $breadcrumb->add(NAVBAR_TITLE_1, tep_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL'));
  $breadcrumb->add(NAVBAR_TITLE_2, tep_href_link(FILENAME_CHECKOUT_SHIPPING_ADDRESS, '', 'SSL'));

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

<?php require(DIR_THEME. 'html/column_left.php'); ?>
<div id="indicator" style="display:none;"></div>


<!-- body_text //-->
<?php echo tep_draw_form('checkout_address', tep_href_link(FILENAME_CHECKOUT_SHIPPING_ADDRESS, '', 'SSL'), 'post', 'onSubmit="return check_form_optional(checkout_address);"'); ?>
            <h1 class="pageHeading"><?php echo HEADING_TITLE; ?></h1>
<?php
  if ($messageStack->size('checkout_address') > 0) {
?>
      <div class="mensaje"><?php echo $messageStack->output('checkout_address'); ?></div>
<?php
  }
  if ($process == false) {
?>
<div class="overflow">
<h4><?php echo TABLE_HEADING_SHIPPING_ADDRESS; ?></h4>
<p class="informacion"><?php echo TEXT_SELECTED_SHIPPING_DESTINATION; ?></p>
<p class="direccion"><?php echo tep_address_label($customer_id, $sendto, true, ' ', '<br />'); ?></p>
<?php
    if ($addresses_count > 1) {
?>
<h4><?php echo TABLE_HEADING_ADDRESS_BOOK_ENTRIES; ?></h4>
<p class="informacion"><?php echo TEXT_SELECT_OTHER_SHIPPING_DESTINATION; ?></p>
<p><?php echo '<strong class="titulo_peq">' . TITLE_PLEASE_SELECT . '</strong>';?>
<?php
      $radio_buttons = 0;

      //NIF start
      $addresses_query = tep_db_query("select ab.address_book_id, ab.entry_firstname as firstname, ab.entry_lastname as lastname, ab.entry_company as company, ab.entry_nif as nif, ab.entry_street_address as street_address, ab.entry_suburb as suburb, IF(ci.name IS NULL, ab.entry_city, ci.name) AS city, ab.entry_postcode as postcode, ab.entry_state as state, ab.entry_zone_id as zone_id, ab.entry_country_id as country_id, ab.entry_telephone as telephone 
	  from " . TABLE_ADDRESS_BOOK . " ab 
	  LEFT JOIN cities ci ON ci.id = ab.entry_city_id
	  where ab.customers_id = '" . (int)$customer_id . "'");
      //NIF end
      while ($addresses = tep_db_fetch_array($addresses_query)) {
        $format_id = tep_get_address_format_id($addresses['country_id']);
?>
<?php
       if ($addresses['address_book_id'] == $sendto) {
          echo '                  <tr id="defaultSelected" class="moduleRowSelected" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="selectRowEffect(this, ' . $radio_buttons . ')">' . "\n";
        } else {
          echo '                  <tr class="moduleRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="selectRowEffect(this, ' . $radio_buttons . ')">' . "\n";
        }
?>
<div class="cambiar_direcciones"><p><strong><?php echo tep_output_string_protected($addresses['firstname'] . ' ' . $addresses['lastname']); ?></strong> <?php echo tep_draw_radio_field('address', $addresses['address_book_id'], ($addresses['address_book_id'] == $sendto)); ?></p>
<p><?php echo tep_address_format($format_id, $addresses, true, ' ', ', '); ?></p></div>
<?php
        $radio_buttons++;
      }
?>
<?php
    }
  }

  if ($addresses_count < MAX_ADDRESS_BOOK_ENTRIES) {
?>
<h4><?php echo TABLE_HEADING_NEW_SHIPPING_ADDRESS; ?></h4>
<p class="informacion"><?php echo TEXT_CREATE_NEW_SHIPPING_ADDRESS; ?></p>
<?php require(DIR_WS_COMPONENTS . 'checkout_new_address.php'); ?>
<?php
  }
?>
</div>
<p><?php echo '<strong>' . TITLE_CONTINUE_CHECKOUT_PROCEDURE . '</strong><br />' . TEXT_CONTINUE_CHECKOUT_PROCEDURE; ?></p>
<div class="botonera">
	<?php echo tep_draw_hidden_field('action', 'submit') . tep_image_submit('button_continue.gif', IMAGE_BUTTON_CONTINUE); ?>
    <?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?>
    <?php
      if ($process == true) {
    ?>
	<?php echo '<a href="' . tep_href_link(FILENAME_CHECKOUT_SHIPPING_ADDRESS, '', 'SSL') . '">' . tep_image_button('button_back.gif', IMAGE_BUTTON_BACK) . '</a>'; ?></td>
    <?php
      }
    ?>
</div>      
<?php 
define('CHECKOUT_BREADCRUMB', 'checkout_shipping_bar');


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
