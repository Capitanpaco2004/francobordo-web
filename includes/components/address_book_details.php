<?php
/*
  $Id: address_book_details.php 1739 2007-12-20 00:52:16Z hpdl $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2007 osCommerce

  Released under the GNU General Public License
*/

  if (!isset($process)) $process = false;
?>
<h4><?php echo NEW_ADDRESS_TITLE; ?> <span><?php echo FORM_REQUIRED_INFORMATION; ?></span></h4>

<?php
  if (ACCOUNT_GENDER == 'true') {
    $male = $female = false;
    if (isset($gender)) {
      $male = ($gender == 'm') ? true : false;
      $female = !$male;
    } elseif (isset($entry['entry_gender'])) {
      $male = ($entry['entry_gender'] == 'm') ? true : false;
      $female = !$male;
    }
?>
<p class="campo"><label for="gender"><?php echo ENTRY_GENDER; ?></label> <?php echo tep_draw_radio_field('gender', 'm', $male) . '&nbsp;&nbsp;' . MALE . '&nbsp;&nbsp;' . tep_draw_radio_field('gender', 'f', $female) . '&nbsp;&nbsp;' . FEMALE .  (tep_not_null(ENTRY_GENDER_TEXT) ? '<span class="inputRequirement">' . ENTRY_GENDER_TEXT . '</span>': ''); ?></p>
<?php
  }
?>
<p class="campo"><label for="firstname"><?php echo ENTRY_FIRST_NAME; ?></label> <?php echo tep_draw_input_field('firstname', $entry['entry_firstname']) .  (tep_not_null(ENTRY_FIRST_NAME_TEXT) ? '<span class="inputRequirement">' . ENTRY_FIRST_NAME_TEXT . '</span>': ''); ?></p>
<p class="campo"><label for="lastname"><?php echo ENTRY_LAST_NAME; ?></label> <?php echo tep_draw_input_field('lastname', $entry['entry_lastname']) .  (tep_not_null(ENTRY_LAST_NAME_TEXT) ? '<span class="inputRequirement">' . ENTRY_LAST_NAME_TEXT . '</span>': ''); ?></p>
          </tr>
<!--NIF start-->
<?php
  if (ACCOUNT_NIF == 'true') {
?>
<p class="campo"><label for="nif"><?php echo ENTRY_NIF; ?></label> <?php echo tep_draw_input_field('nif', $entry['entry_nif']) .  ((tep_not_null(ENTRY_NIF_TEXT) && (ACCOUNT_NIF_REQ == 'true')) ? '<span class="inputRequirement">' . ENTRY_NIF_TEXT . '</span>': ''); ?></p>
<?php
  }
  if (ACCOUNT_COMPANY == 'true') {
?>
<p class="campo"><label for="company"><?php echo ENTRY_COMPANY; ?></label> <?php echo tep_draw_input_field('company', $entry['entry_company']) .  (tep_not_null(ENTRY_COMPANY_TEXT) ? '<span class="inputRequirement">' . ENTRY_COMPANY_TEXT . '</span>': ''); ?></p>
<?php
  }
?>

<p class="campo"><label for="telephone"><?php echo ENTRY_TELEPHONE_NUMBER; ?></label> <?php echo tep_draw_input_field('telephone', $entry['entry_telephone']) .  (tep_not_null(ENTRY_TELEPHONE_NUMBER_TEXT) ? '<span class="inputRequirement">' . ENTRY_TELEPHONE_NUMBER_TEXT . '</span>': ''); ?></p>

<p class="campo"><label for="street_address"><?php echo ENTRY_STREET_ADDRESS; ?></label> <?php echo tep_draw_input_field('street_address', $entry['entry_street_address']) .  (tep_not_null(ENTRY_STREET_ADDRESS_TEXT) ? '<span class="inputRequirement">' . ENTRY_STREET_ADDRESS_TEXT . '</span>': ''); ?></p>
<?php
  if (ACCOUNT_SUBURB == 'true') {
?>
<p class="campo"><label for="suburb"><?php echo ENTRY_SUBURB; ?></label> <?php echo tep_draw_input_field('suburb', $entry['entry_suburb']) .  (tep_not_null(ENTRY_SUBURB_TEXT) ? '<span class="inputRequirement">' . ENTRY_SUBURB_TEXT . '</span>': ''); ?></p>
<?php
  }
?>
<p class="campo getCitiesFromCP"><label for="postcode"><?php echo ENTRY_POST_CODE; ?></label> <?php echo tep_draw_input_field('postcode', $entry['entry_postcode']) . (tep_not_null(ENTRY_POST_CODE_TEXT) ? '<span class="inputRequirement">' . ENTRY_POST_CODE_TEXT . '</span>': ''); ?></p>
<p class="campo city">
	<?php echo ajax_get_cities_html($entry['entry_country_id'], $entry['entry_zone_id'], false, $entry['entry_city_id'], true); ?>
</p>
<?php
  if (ACCOUNT_STATE == 'true') {
?>
<p class="campo getCitiesFromZone"><label for="states"><?php echo ENTRY_STATE; ?></label>
<span id="states">
				<?php
				// +Country-State Selector
				echo ajax_get_zones_html($entry['entry_country_id'],($entry['entry_zone_id'] == 0 ? $entry['entry_state'] : $entry['entry_zone_id']), false);
				// -Country-State Selector
				?>
				</span>
</p>                
<?php
  }
?>
<p class="campo"><label for="country"><?php echo ENTRY_COUNTRY; ?></label>
	<?php // +Country-State Selector ?>
	<?php echo tep_get_country_list('country', $entry['entry_country_id'],'onChange="getStates(this.value,\'states\');"') .  (tep_not_null(ENTRY_COUNTRY_TEXT) ? '<span class="inputRequirement">' . ENTRY_COUNTRY_TEXT . '</span>': ''); ?>
	<?php // -Country-State Selector ?>
	<div id="indicator"></div>
</p>
<?php
  if ((isset($_GET['edit']) && ($customer_default_address_id != $_GET['edit'])) || (isset($_GET['edit']) == false) ) {
?>
<p class="campo"><label for="primary"><?php echo SET_AS_PRIMARY ?></label> <?php echo tep_draw_checkbox_field('primary', 'on', false, 'id="primary"') ; ?></p>
<?php
  }
?>