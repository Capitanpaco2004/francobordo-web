<?php
/*
  $Id: stats_products_notifications.php,v 1.1 2003/05/16 00:10:05 ft01189 Exp $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
  
  Contribution by Radu Manole, radu_manole@hotmail.com 
*/

  require('includes/application_top.php');
?>
<!doctype html public "-//W3C//DTD HTML 4.01 Transitional//EN">
<html <?php echo HTML_PARAMS; ?>>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>">
<title><?php echo TITLE; ?></title>
<link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
</head>
<body marginwidth="0" marginheight="0" topmargin="0" bottommargin="0" leftmargin="0" rightmargin="0" bgcolor="#FFFFFF">
<!-- header //-->
<?php include( THEME . '/html/header.php' ); ?>
<!-- header_eof //-->

<!-- body //-->
<table border="0" width="100%" cellspacing="2" cellpadding="2">
  <tr>
<!-- body_text //-->
    <td width="100%" valign="top">		

<?php
// show customers for a product
if ($_GET['action'] == 'show_customers' && (int)$_GET['pID']) {
  $products_id = (int)$_GET['pID'];
?>

    <table border="0" width="100%" cellspacing="0" cellpadding="0">
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo HEADING_TITLE; ?></td>
          </tr>
        </table></td>
      </tr>
	<tr>
          <td class="dataTableContent"><?php echo TEXT_DESCRIPTION_TO; ?><b>"<?php echo tep_get_products_name($products_id); ?>"</b>.</td>
        </tr>
        <tr>
          <td>&nbsp;</td>
        </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr class="dataTableHeadingRow">
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_NUMBER; ?></td>
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_NAME; ?></td>
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_EMAIL; ?></td>
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_DATE; ?></td>
              </tr>
<?php
  if ($_GET['cpage'] > 1) $rows = $_GET['cpage'] * MAX_DISPLAY_SEARCH_RESULTS - MAX_DISPLAY_SEARCH_RESULTS;

    $customers_query_raw = "select customers_firstname, customers_email_address, date_added
                            from " . TABLE_PRODUCTS_NOTIFICATIONS . " 
                            where products_id = '" . $products_id . "' 
                            order by customers_firstname";

    $customers_split = new splitPageResults($_GET['cpage'], MAX_DISPLAY_SEARCH_RESULTS, $customers_query_raw, $customers_query_numrows);
    $customers_query = tep_db_query($customers_query_raw);
	
    while ($customers = tep_db_fetch_array($customers_query)) {
      $rows++;

      if (strlen($rows) < 2) {
        $rows = '0' . $rows;
      }
//	  if ($customers['customers_id']==0) $noregistrado=' (No registrado)'; else $noregistrado='';
?>
              <tr class="dataTableRow" onMouseOver="rowOverEffect(this)" onMouseOut="rowOutEffect(this)">
                <td width="30" nowrap class="dataTableContent"><?php echo $rows; ?>.</td>
                <td class="dataTableContent"><?php echo '<a href="' . tep_href_link(FILENAME_CUSTOMERS, 'search=' . $customers['customers_firstname'], 'NONSSL') . '">' . $customers['customers_firstname'] .  '</a>'/*.$noregistrado*/; ?></td>
                <td class="dataTableContent"><?php echo '<a href="' . tep_href_link(FILENAME_MAIL, 'selected_box=tools&customer=' . $customers['customers_email_address'], 'NONSSL') . '">' . $customers['customers_email_address'] . '</a>'; ?>&nbsp;</td>
                <td class="dataTableContent"><?php echo tep_date_long($customers['date_added']); ?>&nbsp;</td>
              </tr>
<?php
    }
?>
            </table></td>
          </tr>
          <tr>
            <td colspan="3"><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr>
                <td class="smallText" valign="top"><?php echo $customers_split->display_count($customers_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, $_GET['cpage'], 'Viendo <b>%s</b> de <b>%s</b> (de <b>%s</b> clientes)' , '', 'cpage'); ?></td>
                <td class="smallText" align="right"><?php echo $customers_split->display_links($customers_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, MAX_DISPLAY_PAGE_LINKS, $_GET['cpage'], tep_get_all_get_params(array('cpage')), 'cpage'); ?></td>
              </tr>
            </table></td>
          </tr>
        </table></td>
      </tr>
    </table>
      <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr>
          <td height="5"></td>
        </tr>
        <tr> 
          <td align="right"><?php echo '<a href="' . tep_href_link(FILENAME_STATS_PRODUCTS_NOTIFICATIONS, tep_get_all_get_params(array('action', 'pID', 'cpage'))) . '">' . tep_image_button('button_back.png', IMAGE_BACK) . '</a>'; ?></td>
        </tr>
      </table>
<?php
// default
} else {

?>
      <table border="0" width="100%" cellspacing="0" cellpadding="0">
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo HEADING_TITLE; ?></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td class="dataTableContent"><?php echo TEXT_DESCRIPTION; ?></td>
      </tr>
      <tr>
        <td>&nbsp;</td>
      </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr class="dataTableHeadingRow">
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_NUMBER; ?></td>
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_PRODUCTS; ?></td>
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_MODEL; ?></td>
                <td class="dataTableHeadingContent" align="center">Nº Clientes pendientes de notificación</td>
              </tr>
<?php
  if ($_GET['page'] > 1) $rows = $_GET['page'] * MAX_DISPLAY_SEARCH_RESULTS - MAX_DISPLAY_SEARCH_RESULTS;

    $products_notifications_query_raw = "select count(pn.products_id) as count_notifications, pn.products_id, pd.products_name, p.products_model from products_notifications pn INNER JOIN " . TABLE_PRODUCTS . " p ON (pn.products_id = p.products_id) INNER JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON (p.products_id = pd.products_id) where pd.language_id = '" . $languages_id . "' group by pn.products_id order by count_notifications desc, pn.products_id";
                                         
                                         

                                         
    // fix numrows
    $products_count_query = tep_db_query($products_notifications_query_raw);

    $products_notifications_split = new splitPageResults($_GET['page'], MAX_DISPLAY_SEARCH_RESULTS, $products_notifications_query_raw,     $products_notifications_query_numrows);			
    $products_notifications_query = tep_db_query($products_notifications_query_raw);
    $products_notifications_query_numrows = tep_db_num_rows($products_count_query);
	
    while ($products = tep_db_fetch_array($products_notifications_query)) {
      $rows++;
      

      if (strlen($rows) < 2) {
        $rows = '0' . $rows;
      }
?>
              <tr class="dataTableRow" onMouseOver="rowOverEffect(this)" onMouseOut="rowOutEffect(this)" onClick="document.location.href='<?php echo tep_href_link(FILENAME_STATS_PRODUCTS_NOTIFICATIONS, 'action=show_customers&pID=' . $products['products_id'] . '&page=' . $page, 'NONSSL'); ?>'">
                <td width="30" nowrap class="dataTableContent"><?php echo $rows; ?>.</td>
                <td class="dataTableContent"><?php echo '<a href="' . tep_href_link(FILENAME_STATS_PRODUCTS_NOTIFICATIONS, 'action=show_customers&pID=' . $products['products_id'] . '&page=' . $page, 'NONSSL') . '">' . $products['products_name'] . '</a>'; ?></td>
                <td class="dataTableContent" align="center"><?php echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'pID=' . $products['products_id'] . '&action=new_product') . '">' . $products['products_model'] . '</a>'; ?>&nbsp;</td>
                <td class="dataTableContent" align="center"><?php echo $products['count_notifications']; ?>&nbsp;</td>
              </tr>
<?php
  }
?>
            </table></td>
          </tr>
          <tr>
            <td colspan="3"><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr>
                <td class="smallText" valign="top"><?php echo $products_notifications_split->display_count($products_notifications_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, $_GET['page'], TEXT_DISPLAY_NUMBER_OF_PRODUCTS); ?></td>
                <td class="smallText" align="right"><?php echo $products_notifications_split->display_links($products_notifications_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, MAX_DISPLAY_PAGE_LINKS, $_GET['page']); ?></td>
              </tr>
            </table></td>
          </tr>
        </table></td>
      </tr>
    </table>
<?php
} // end else
?>
		</td>
<!-- body_text_eof //-->
  </tr>
</table>
<!-- body_eof //-->

<!-- footer //-->
<?php include( THEME . '/html/footer.php' ); ?>
<!-- footer_eof //-->
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
