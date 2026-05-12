<?php
  require('includes/application_top.php');
  require(DIR_WS_FUNCTIONS . 'ajax.php');
  require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_CREATE_ACCOUNT);

  if (isset( $_POST['action'] ) && $_POST['action'] == 'getCities' && (isset( $_POST['zone'] ) || isset( $_POST['cp'] )))
  {
      if( (int)$_POST['zone'] > 0 )
          ajax_get_cities_html( (int)$_POST['country'], tep_db_prepare_input( $_POST['zone'] ) );

      if( (int)$_POST['cp'] > 0 )
          ajax_get_cities_html( (int)$_POST['country'], false, tep_db_prepare_input( $_POST['cp'] ) );

      die();
  }


  if (isset($_POST['action']) && $_POST['action'] == 'getStates' && isset($_POST['country'])) {
	ajax_get_zones_html_id(tep_db_prepare_input($_POST['country']), '', true);
  } else {
?>
<?php require(THEME . 'html/header.php'); ?>
<?php require('includes/form_check.js.php'); ?>
<?php require("includes/ajax.js.php"); ?>
<!-- body //-->

<?php
  if (is_array($navigation->snapshot) && sizeof($navigation->snapshot) > 0) {
?>
      <tr>
        <td class="smallText"><br><?php echo sprintf(TEXT_ORIGIN_LOGIN, tep_href_link(FILENAME_LOGIN, tep_get_all_get_params(), 'SSL')); ?></td>
      </tr>
<?php
  }
?>


	  <?php echo tep_draw_form('account_edit', FILENAME_CREATE_ACCOUNT_PROCESS, '', 'post', '', '') . tep_draw_hidden_field('action', 'process'); ?>
<?php
  //$email_address = tep_db_prepare_input($_GET['email_address']);
  $account['entry_country_id'] = STORE_COUNTRY;
  $account['entry_zone_id'] = STORE_ZONE;

  require(DIR_WS_MODULES . 'account_details.php');
?>
<!-- body_eof //-->
</form>
<?php require(THEME . 'html/footer.php'); ?>

<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>

<?php } ?>
