<?php
/*
  $Id: checkout_shipping.php 1739 2007-12-20 00:52:16Z hpdl $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
*/

  require('includes/application_top.php');

// if the customer is not logged on, redirect them to the login page
  if (!tep_session_is_registered('customer_id')) {
    $navigation->set_snapshot();
    tep_redirect(tep_href_link(FILENAME_LOGIN, '', 'SSL'));
  }

// if there is nothing in the customers cart, redirect them to the shopping cart page
  if ($cart->count_contents() < 1) {
    tep_redirect(tep_href_link(FILENAME_SHOPPING_CART));
  }

// if no shipping destination address was selected, use the customers own address as default
  if (!tep_session_is_registered('sendto')) {
    tep_session_register('sendto');
    $sendto = $customer_default_address_id;
  } else {
// verify the selected shipping address
    if ( (is_array($sendto) && empty($sendto)) || is_numeric($sendto) ) {
      $check_address_query = tep_db_query("select count(*) as total from " . TABLE_ADDRESS_BOOK . " where customers_id = '" . (int)$customer_id . "' and address_book_id = '" . (int)$sendto . "'");
      $check_address = tep_db_fetch_array($check_address_query);

      if ($check_address['total'] != '1') {
        $sendto = $customer_default_address_id;
        if (tep_session_is_registered('shipping')) tep_session_unregister('shipping');
      }
    }
  }

	// Si estamos enviando el ZONE ID por POST
	if( isset( $_POST['zone_id'] ) )
	{
		// Actualizamos la ZONE ID
			// Validamos y resolvemos city desde id (parche city-fix 2026-05-16)
		$_city_id = (int)$_POST['city_id'];
		$_postcode_raw = trim(tep_db_prepare_input($_POST['postcode']));
		$_postcode = tep_db_input($_postcode_raw);
		$_zid = (int)$_POST['zone_id'];
			// Formato de CP (parche postcode-fix 2026-07-09): España = 5 dígitos con provincia 01-52;
			// Portugal (171) = CP7 normalizado a 1234-567; resto de países, sin '@'
		$_country = (int)(isset($_POST['country']) ? $_POST['country'] : 0);
		$_cp_ok = true;
		if( $_country == 195 )
			$_cp_ok = (bool)preg_match('/^(0[1-9]|[1-4][0-9]|5[0-2])[0-9]{3}$/', $_postcode_raw);
		else if( $_country == 171 )
		{
			if( preg_match('/^([0-9]{4})\s*-?\s*([0-9]{3})$/', $_postcode_raw, $_cpm) )
			{
				$_postcode_raw = $_cpm[1] . '-' . $_cpm[2];
				$_postcode = tep_db_input($_postcode_raw);
			}
			else
				$_cp_ok = false;
		}
		else if( $_postcode_raw == '' || strpos($_postcode_raw, '@') !== false )
			$_cp_ok = false;
		if( !$_cp_ok ) {
			$messageStack->add_session( 'checkout_address', defined('ENTRY_POST_CODE_FORMAT_ERROR') ? ENTRY_POST_CODE_FORMAT_ERROR : 'El código postal no parece válido para el país seleccionado. En España son 5 dígitos (ej. 03700).', 'error' );
			tep_redirect( tep_href_link( FILENAME_CHECKOUT_SELECT_ZONE, '', 'SSL' ) );
		}
		$_city_name = '';
		if( $_city_id > 0 ) {
			$_cq = tep_db_query( 'SELECT name FROM cities WHERE id = ' . $_city_id . ' LIMIT 1' );
			if( $_crow = tep_db_fetch_array($_cq) ) $_city_name = $_crow['name'];
		}
		if( $_city_id == 0 || $_city_name == '' ) {
			$messageStack->add_session( 'checkout_address', 'Selecciona una ciudad de la lista para continuar.', 'error' );
			tep_redirect( tep_href_link( FILENAME_CHECKOUT_SELECT_ZONE, '', 'SSL' ) );
		}
		tep_db_query( 'UPDATE address_book SET entry_city_id = ' . $_city_id . ', entry_city = "' . tep_db_input($_city_name) . '", entry_postcode = "' . $_postcode . '", entry_zone_id = ' . $_zid . ' WHERE address_book_id = ' . (int)$customer_default_address_id );

		// Redireccionamos
		tep_redirect( FILENAME_CHECKOUT_SHIPPING );
	}

  require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_CHECKOUT_SELECT_ZONE);

  $breadcrumb->add(NAVBAR_TITLE_1, tep_href_link(FILENAME_CHECKOUT_SELECT_ZONE, '', 'SSL'));
  $breadcrumb->add(NAVBAR_TITLE_2, tep_href_link(FILENAME_CHECKOUT_SELECT_ZONE, '', 'SSL'));
?>


<?php require(DIR_THEME. 'html/header.php'); ?>
<!-- header_eof //-->

<!-- body //-->

<!-- left_navigation //-->
<?php require(DIR_THEME. 'html/column_left.php'); ?>
<!-- left_navigation_eof //-->


<!-- body_text //-->
<?php echo tep_draw_form('checkout_select_zone', tep_href_link(FILENAME_CHECKOUT_SELECT_ZONE, '', 'SSL')) . tep_draw_hidden_field('action', 'process'); ?>
<h1 class="pageHeading"><?php echo HEADING_TITLE; ?></h1>
<?php
$aZoneID = tep_db_query( 'SELECT * FROM address_book WHERE address_book_id = ' . $customer_default_address_id . ';' );
$aZoneID = tep_db_fetch_array( $aZoneID );
 ?>
<div class="formas-envio" style="margin-top: 30px;">
<p><?php echo '<b>' . TITLE_CONTINUE_CHECKOUT_PROCEDURE . '</b><br />' . TEXT_CONTINUE_CHECKOUT_PROCEDURE; ?></p>
<p class="campo getCitiesFromCP"><label for="postcode"><?php echo ENTRY_POST_CODE; ?></label> <?php echo tep_draw_input_field('postcode', $aZoneID['entry_postcode'], 'class="ncsp"') . '&nbsp;' . (tep_not_null(ENTRY_POST_CODE_TEXT) ? '<span class="inputRequirement">' . ENTRY_POST_CODE_TEXT . '</span>': ''); ?></p>
<p class="campo city">
	<?php echo ajax_get_cities_html($aZoneID['entry_country_id'], $aZoneID['entry_zone_id'], false, $aZoneID['entry_city_id'], true); 	?>
</p>
<p class="campo getCitiesFromZone"><label for="states"><?php echo ENTRY_STATE; ?></label>
<span id="states">
<?php echo ajax_get_zones_html($customer_country_id, $aZoneID['entry_zone_id'], false); ?>
</span>
</p>

<?php echo tep_get_country_list('country',$customer_country_id,'style="display: none"'); ?>

<div class="botonera">                
	<?php echo tep_image_submit('button_continue.gif', IMAGE_BUTTON_CONTINUE); ?>
</div>
</div>
<?php 
define('CHECKOUT_BREADCRUMB', 'checkout_shipping_bar');
?>
</form>
</div>

<!-- footer //-->
<?php require(DIR_THEME. 'html/footer.php'); ?>
<!-- footer_eof //-->

<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>