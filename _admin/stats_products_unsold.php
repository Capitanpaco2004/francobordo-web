<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2010 osCommerce

  Released under the GNU General Public License
*/

//###########################################################################
// hard coded the number to show on the page, you can remove this or edit it
  define('MAX_DISPLAY_SEARCH_RESULTS', '100');
//###########################################################################


  require('includes/application_top.php');
  
  require(THEME . 'html/header.php');
?>

<table border="0" width="100%" cellspacing="2" cellpadding="2">
  <tr>
	<td width="100%" valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="0">
      <tr>
        <td><table align="center" border="0" width="70%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo HEADING_TITLE; ?></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><table align="center" border="0" width="70%" cellspacing="0" cellpadding="0">
          <tr>
            <td valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr class="dataTableHeadingRow">
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_NUMBER; ?></td>
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_PRODUCTS; ?></td>
				<td class="dataTableHeadingContent"><?php echo TABLE_HEADING_NAME; ?></td>
				<td class="dataTableHeadingContent"><?php echo TABLE_HEADING_UNSOLD; ?>&nbsp;</td>
              </tr>
<?php
  if (isset($_GET['page']) && ($_GET['page'] > 1)) $rows = $_GET['page'] * MAX_DISPLAY_SEARCH_RESULTS - MAX_DISPLAY_SEARCH_RESULTS;

$products_query_raw = "
select p.products_id, p.products_quantity, p.products_model, pd.products_name from (" . TABLE_PRODUCTS . " p,  " . TABLE_PRODUCTS_DESCRIPTION . " pd) LEFT JOIN " . TABLE_ORDERS_PRODUCTS . " op ON p.products_id = op.products_id where op.products_id IS NULL and p.products_id = pd.products_id order by p.products_model";

  $products_split = new splitPageResults($_GET['page'], MAX_DISPLAY_SEARCH_RESULTS, $products_query_raw, $products_query_numrows);

  $rows = 0;
  $products_query = tep_db_query($products_query_raw);
  while ($products = tep_db_fetch_array($products_query)) {
    $rows++;

    if (strlen($rows) < 2) {
      $rows = '0' . $rows;
    }
?>
              <tr class="dataTableRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href='<?php echo tep_href_link(FILENAME_CATEGORIES, 'action=new_product_preview&read=only&pID=' . $products['products_id'] . '&origin=' . FILENAME_STATS_PRODUCTS_PURCHASED . '?page=' . $_GET['page'], 'NONSSL'); ?>'">

                <td class="dataTableContent"><?php echo $rows; ?>.</td>
				<td class="dataTableContent"><?php echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'action=new_product_preview&read=only&pID=' . $products['products_id'] . '&origin=stats_products_unsold.php?page=' . $_GET['page'], 'NONSSL') . '">' . $products['products_model'] . '</a>'; ?></td>
                <td class="dataTableContent"><?php echo $products['products_name']; ?>&nbsp;</td>
                <td class="dataTableContent"><?php echo $products['products_quantity']; ?>&nbsp;</td>
              </tr>
<?php
  }
?>
            </table></td>
          </tr>
          <tr>
            <td colspan="3"><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr>
                <td class="smallText" valign="top"><?php echo $products_split->display_count($products_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, $_GET['page'], TEXT_DISPLAY_NUMBER_OF_PRODUCTS); ?></td>
                <td class="smallText" align="right"><?php echo $products_split->display_links($products_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, MAX_DISPLAY_PAGE_LINKS, $_GET['page']); ?>&nbsp;</td>
              </tr>
            </table></td>
          </tr>
        </table></td>
      </tr>
    </table>

<?php require( THEME . 'html/footer.php'); ?>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>