<?php
/*
  $Id: my_points.php, V2.1rc2a 2008/OCT/01 16:04:22 dsa_ Exp $
  created by Ben Zukrel, Deep Silver Accessories
  http://www.deep-silver.com

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2005 osCommerce

  Released under the GNU General Public License
*/

  require('includes/application_top.php');

  if (!tep_session_is_registered('customer_id')) {
    $navigation->set_snapshot();
    tep_redirect(tep_href_link(FILENAME_LOGIN, '', 'SSL'));
  }

  require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_MY_POINTS);

  $breadcrumb->add(NAVBAR_TITLE, tep_href_link(FILENAME_MY_POINTS, '', 'SSL'));

?>

<?php require(DIR_THEME. 'html/header.php'); ?>

<script language="javascript"><!--
function rowOverEffect(object) {
  if (object.className == 'moduleRow') object.className = 'moduleRowOver';
}

function rowOutEffect(object) {
  if (object.className == 'moduleRowOver') object.className = 'moduleRow';
}
//--></script>


<!-- body //-->
<!-- left_navigation //-->
<?php require(DIR_THEME. 'html/column_left.php'); ?>
<!-- left_navigation_eof //-->
<!-- body_text //-->

<table border="0" width="100%" cellspacing="0" cellpadding="0">
      <tr>
        <td>
          <table border="0" width="100%" cellspacing="0" cellpadding="2">
            <tr>
              <td class="main"><?php echo MY_POINTS_HELP_LINK; ?></td>
            </tr>
<?php
  $has_point = tep_get_shopping_points($customer_id);
  $expires   = ['customers_points_expires' => null];
  if ($has_point > 0) {
      if (tep_not_null(POINTS_AUTO_EXPIRES)) {
          $expires_query = tep_db_query("select customers_points_expires from " . TABLE_CUSTOMERS . " where customers_id = '" . (int)$customer_id . "' and customers_points_expires > curdate() limit 1");
          $expires_row   = tep_db_fetch_array($expires_query);
          if (is_array($expires_row)) $expires = $expires_row;
      }
      $caducidad_html = tep_not_null($expires['customers_points_expires'])
          ? '&nbsp;&nbsp;&nbsp;<strong>' . MY_POINTS_EXPIRE . '</strong> ' . tep_date_short($expires['customers_points_expires'])
          : '';
?>
              <td class="main"><?php echo sprintf(MY_POINTS_CURRENT_BALANCE, number_format($has_point, POINTS_DECIMAL_PLACES, ',', '.'), $currencies->format(tep_calc_shopping_pvalue($has_point))) . $caducidad_html; ?></td>
<?php
  } else {
         echo '<td class="main"><strong>' . TEXT_NO_POINTS . '</strong></td>';
  }
?>
            </tr>
          </table>
<?php
    // Solo mostrar el historial si los puntos del cliente NO han caducado
    $expires_date = $expires['customers_points_expires'] ?? null;
    $points_alive = ($has_point > 0) && tep_not_null($expires_date) && (strtotime($expires_date) > strtotime('today'));

    $expires_months = (int) POINTS_AUTO_EXPIRES;
    if ($expires_months <= 0) $expires_months = 12;

    // points_type='EX' son filas internas de caducidad (no visibles al cliente)
    $pending_points_query = "select unique_id, orders_id, points_pending, points_comment, date_added, points_status, points_type from " . TABLE_CUSTOMERS_POINTS_PENDING . " where customer_id = '" . (int)$customer_id . "' and points_type != 'EX' and ( DATE_ADD( date_added, INTERVAL " . $expires_months . " MONTH ) > curdate() )  order by unique_id desc";
    $pending_points_split = new splitPageResults($pending_points_query, MAX_DISPLAY_POINTS_RECORD);
    $pending_points_query = tep_db_query($pending_points_split->sql_query);

    if ($points_alive && tep_db_num_rows($pending_points_query)) {
?>
          <table border="0" width="100%" cellspacing="1" cellpadding="2" class="productListing-heading">
            <tr class="productListing-heading">
              <td class="productListing-heading" width="11%"><?php echo HEADING_ORDER_DATE; ?></td>
              <td class="productListing-heading" width="22%"><?php echo HEADING_ORDERS_NUMBER; ?></td>
              <td class="productListing-heading" width="29%"><?php echo HEADING_POINTS_COMMENT; ?></td>
              <td class="productListing-heading" width="14%"><?php echo HEADING_POINTS_STATUS; ?></td>
              <td class="productListing-heading" width="12%" align="right"><?php echo HEADING_POINTS_TOTAL; ?></td>
              <td class="productListing-heading" width="12%" align="right"><?php echo HEADING_POINTS_VALUE; ?></td>
            </tr>
          </table>
          <table border="0" width="100%" cellspacing="1" cellpadding="2" class="infoBox">
            <tr class="infoBoxContents">
              <td><table border="0" width="100%" cellspacing="1" cellpadding="2">
                <tr>
<?php
    while ($pending_points = tep_db_fetch_array($pending_points_query)) {
	    $orders_status_query = tep_db_query("select o.orders_id, o.orders_status, s.orders_status_name from " . TABLE_ORDERS . " o, " . TABLE_ORDERS_STATUS . " s where o.customers_id = '" . (int)$customer_id . "' and o.orders_id = '" . (int)$pending_points['orders_id'] . "' and o.orders_status = s.orders_status_id and s.language_id = '" . (int)$languages_id . "'");
	    $orders_status = tep_db_fetch_array($orders_status_query);
	    if (!is_array($orders_status)) $orders_status = ['orders_id' => 0, 'orders_status' => 0, 'orders_status_name' => ''];  // fila sin pedido asociado (orders_id=0)
	    $referred_name = [];
	    
	    if ($pending_points['points_status'] == '1') $points_status_name = TEXT_POINTS_PENDING;
	    if ($pending_points['points_status'] == '2') $points_status_name = TEXT_POINTS_CONFIRMED;
	    if ($pending_points['points_status'] == '3') $points_status_name = '<span class="pointWarning">' . TEXT_POINTS_CANCELLED . '</span>';
	    if ($pending_points['points_status'] == '4') $points_status_name = '<span class="pointWarning">' . TEXT_POINTS_REDEEMED . '</span>';
	    
	    if ($orders_status['orders_status'] == 2 && $pending_points['points_status'] == 1 || $orders_status['orders_status'] == 3 && $pending_points['points_status'] == 1) {
		    $points_status_name = TEXT_POINTS_PROCESSING;
	    }
	    
	    if (($pending_points['points_type'] == 'SP') && ($pending_points['points_comment'] == 'TEXT_DEFAULT_COMMENT')) {
		    $pending_points['points_comment'] = TEXT_DEFAULT_COMMENT;
	    }
	    
		if ($pending_points['points_comment'] == 'TEXT_DEFAULT_REDEEMED') {
			$pending_points['points_comment'] = TEXT_DEFAULT_REDEEMED;
		}
		
		if ($pending_points['points_type'] == 'RF') {
			$referred_name_query = tep_db_query("select customers_name from " . TABLE_ORDERS . " where orders_id = '" . (int)$pending_points['orders_id'] . "' limit 1");
			$referred_name = tep_db_fetch_array($referred_name_query);
			if ($pending_points['points_comment'] == 'TEXT_DEFAULT_REFERRAL') {
				$pending_points['points_comment'] = TEXT_DEFAULT_REFERRAL;
			}
		}
	
	if (($pending_points['points_type'] == 'RV') && ($pending_points['points_comment'] == 'TEXT_DEFAULT_REVIEWS')) {
		$pending_points['points_comment'] = TEXT_DEFAULT_REVIEWS;
	}
	
	if (($pending_points['orders_id'] > '0') && (($pending_points['points_type'] == 'SP')||($pending_points['points_type'] == 'RD'))) {
?>
        <tr class="moduleRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href='<?php echo tep_href_link(FILENAME_ACCOUNT_HISTORY_INFO, 'order_id=' . $pending_points['orders_id'], 'SSL'); ?>'" title="<?php echo TEXT_ORDER_HISTORY .'&nbsp;' . $pending_points['orders_id']; ?>">
<?php
	}
	
	if ($pending_points['points_type'] == 'RV') {
?>
        <tr class="moduleRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href='<?php echo tep_href_link('product_info.php', 'products_id=' . $pending_points['orders_id'], 'NONSSL'); ?>#cmtr'" title="<?php echo TEXT_REVIEW_HISTORY; ?>">
<?php
	}
	
	if (($pending_points['orders_id'] == 0) || ($pending_points['points_type'] == 'RF') || ($pending_points['points_type'] == 'RV')) {
		$orders_status['orders_status_name'] = '<span class="pointWarning">' . TEXT_STATUS_ADMINISTATION . '</span>';
		$pending_points['orders_id'] = '<span class="pointWarning">' . TEXT_ORDER_ADMINISTATION . '</span>';
	}
?>
                  <td class="productListing-data" width="11%"><?php echo tep_date_short($pending_points['date_added']); ?></td>
                  <td class="productListing-data" width="22%"><?php echo '#' . $pending_points['orders_id'] . '&nbsp;&nbsp;' . $orders_status['orders_status_name']; ?></td>
                  <td class="productListing-data" width="29%"><?php echo $pending_points['points_comment'] . '&nbsp;' . ($referred_name['customers_name'] ?? ''); ?></td>
                  <td class="productListing-data" width="14%"><?php echo $points_status_name; ?></td>
                  <td class="productListing-data" width="12%" align="right"><?php echo number_format($pending_points['points_pending'], POINTS_DECIMAL_PLACES, ',', '.'); ?></td>
                  <td class="productListing-data" width="12%" align="right"><?php echo $currencies->format($pending_points['points_pending'] * REDEEM_POINT_VALUE); ?></td>
                </tr>
<?php
	}
?>
              </table></td>
            </tr>
          </table>
          <table border="0" width="100%" cellspacing="0" cellpadding="2">
            <tr>
              <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
            </tr>
          </table>
        </td>
      </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
          <tr>
            <td class="smallText" valign="top"><?php echo $pending_points_split->display_count(TEXT_DISPLAY_NUMBER_OF_RECORDS); ?></td>
            <td class="smallText" align="right"><?php echo TEXT_RESULT_PAGE . ' ' . $pending_points_split->display_links(MAX_DISPLAY_PAGE_LINKS, tep_get_all_get_params(array('page', 'info', 'x', 'y'))); ?></td>
          </tr>
        </table></td>
      </tr>
<?php
  }
?>
    </table>
     
	 <div class="botonera">
		<a href="javascript:history.go(-1)"><?php echo tep_image_button('button_back.gif', IMAGE_BUTTON_BACK); ?></a>
      </div>
   

<!-- body_text_eof //-->

<!-- right_navigation //-->
<?php require(DIR_THEME. 'html/column_right.php'); ?>
<!-- right_navigation_eof //-->

<!-- body_eof //-->


<?php require(DIR_THEME. 'html/footer.php'); ?>

<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
