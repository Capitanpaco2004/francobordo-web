<?php
/*
  $Id: address_book.php 1739 2007-12-20 00:52:16Z hpdl $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
*/

  require('includes/application_top.php');

  if (!$customerCore->hasLogin()) {
    $navigation->set_snapshot();
    tep_redirect(tep_href_link(FILENAME_LOGIN, '', 'SSL'));
  }

  require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_ADDRESS_BOOK);

  $breadcrumb->add(NAVBAR_TITLE_1, tep_href_link(FILENAME_ACCOUNT, '', 'SSL'));
  $breadcrumb->add(NAVBAR_TITLE_2, tep_href_link(FILENAME_ADDRESS_BOOK, '', 'SSL'));

?>


<?php require(DIR_THEME. 'html/header.php'); ?>
<!-- header_eof //-->

<script language="javascript"><!--
function rowOverEffect(object) {
  if (object.className == "moduleRow") object.className = "moduleRowOver";
}

function rowOutEffect(object) {
  if (object.className == "moduleRowOver") object.className = "moduleRow";
}
//--></script>

<!-- left_navigation //-->
<?php require(DIR_THEME. 'html/column_left.php'); ?>
<!-- left_navigation_eof //-->


            <h1 class="pageHeading"><?php echo HEADING_TITLE; ?></h1>
<?php
  if ($messageStack->size('addressbook') > 0) {
?>
      <div class="mensaje"><?php echo $messageStack->output('addressbook'); ?></div>
      
<?php
  }
?>
<h4><?php echo PRIMARY_ADDRESS_TITLE; ?></h4>
<p><?php echo PRIMARY_ADDRESS_DESCRIPTION; ?></p>
<p><strong><?php echo PRIMARY_ADDRESS_TITLE; ?></strong></p>
<p><?php echo tep_address_label($customer_id, $customer_default_address_id, true, ' ', '<br />'); ?></p>

<h4><?php echo ADDRESS_BOOK_TITLE; ?></h4>
<?php
  //NIF start
  $addresses_query = tep_db_query("select c.name as city, entry_city_id, address_book_id, entry_firstname as firstname, entry_lastname as lastname, entry_company as company, entry_nif as nif, entry_street_address as street_address, entry_suburb as suburb, entry_postcode as postcode, entry_state as state, entry_zone_id as zone_id, entry_country_id as country_id, entry_telephone as telephone from " . TABLE_ADDRESS_BOOK . " a LEFT JOIN cities c ON c.id = a.entry_city_id where customers_id = '" . (int)$customer_id . "' order by firstname, lastname");
  //NIF end
  while ($addresses = tep_db_fetch_array($addresses_query)) {
    $format_id = tep_get_address_format_id($addresses['country_id']);
?>
<div class="account_history">
			<table border="0" width="100%" cellspacing="0" cellpadding="2">
                  <tr class="moduleRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onClick="document.location.href='<?php echo tep_href_link(FILENAME_ADDRESS_BOOK_PROCESS, 'edit=' . $addresses['address_book_id'], 'SSL'); ?>'">
                    <td class="main"><strong><?php echo tep_output_string_protected($addresses['firstname'] . ' ' . $addresses['lastname']); ?></strong><?php if ($addresses['address_book_id'] == $customer_default_address_id) echo '&nbsp;<small><i>' . PRIMARY_ADDRESS . '</i></small>'; ?></td>
                    <td class="main" align="right"><?php echo '<a href="' . tep_href_link(FILENAME_ADDRESS_BOOK_PROCESS, 'edit=' . $addresses['address_book_id'], 'SSL') . '">' . tep_image_button('small_edit.gif', SMALL_IMAGE_BUTTON_EDIT) . '</a> <a href="' . tep_href_link(FILENAME_ADDRESS_BOOK_PROCESS, 'delete=' . $addresses['address_book_id'], 'SSL') . '">' . tep_image_button('small_delete.gif', SMALL_IMAGE_BUTTON_DELETE) . '</a>'; ?></td>
                  </tr>
                  <tr>
                    <td colspan="2" style="line-height:15px">
						<?php echo tep_address_format($format_id, $addresses, true, ' ', '<br />'); ?>
                    </td>
                  </tr>
                </table>
</div>                
<?php
  }
?>
<div class="botonera">
<?php echo '<a href="' . tep_href_link(FILENAME_ACCOUNT, '', 'SSL') . '">' . tep_image_button('button_back.gif', IMAGE_BUTTON_BACK) . '</a> '; ?>

<?php
  if (tep_count_customer_address_book_entries() < MAX_ADDRESS_BOOK_ENTRIES) {
?>
                <?php echo '<a href="' . tep_href_link(FILENAME_ADDRESS_BOOK_PROCESS, '', 'SSL') . '">' . tep_image_button('button_add_address.gif', IMAGE_BUTTON_ADD_ADDRESS) . '</a>'; ?>
<?php
  }
?>
</div>
        <div class="mensaje"><?php echo sprintf(TEXT_MAXIMUM_ENTRIES, MAX_ADDRESS_BOOK_ENTRIES); ?></div>

<!-- body_text_eof //-->

<!-- right_navigation //-->
<?php require(DIR_THEME. 'html/column_right.php'); ?>
<!-- right_navigation_eof //-->

<!-- body_eof //-->

<!-- footer //-->
<?php require(DIR_THEME. 'html/footer.php'); ?>
<!-- footer_eof //-->

<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
