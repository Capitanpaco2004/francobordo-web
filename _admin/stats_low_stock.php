<?php
/*
  $Id: stats_customers.php 1739 2007-12-20 00:52:16Z hpdl $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
*/

  require('includes/application_top.php');

  require(DIR_WS_CLASSES . 'currencies.php');
  $currencies = new currencies();
?>


<?php include( THEME . '/html/header.php' ); ?>

<table border="0" width="100%" cellspacing="2" cellpadding="2">
  <tr>
<td width="100%" valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo HEADING_TITLE; ?></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
          <tr>
            <td valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr class="dataTableHeadingRow">
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_NUMBER; ?></td>
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_PRODUCTS; ?></td>
                <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_QTY_LEFT; ?>&nbsp;</td>
              </tr>
<?php

  if ($_GET['page'] > 1) $rows = $_GET['page'] * 20 - 20;
  $products_query_raw = "select categories_id,p.products_id, p.products_quantity, pd.products_name from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd INNER JOIN products_to_categories USING(products_id) where p.products_id = pd.products_id and pd.language_id = '" . $languages_id. "' and p.products_quantity <= " . STOCK_REORDER_LEVEL . " group by pd.products_id order by pd.products_name ASC";
  $products_split = new splitPageResults($_GET['page'], MAX_DISPLAY_SEARCH_RESULTS, $products_query_raw, $products_query_numrows);
  $products_query = tep_db_query($products_query_raw);
  while ($products = tep_db_fetch_array($products_query)) {
    $rows++;

    if (strlen($rows) < 2) {
      $rows = '0' . $rows;
    }
?>
          <tr class="datatableRow" onMouseOver="this.className='datatableRowOver';this.style.cursor='hand'" onMouseOut="this.className='datatableRow'" onClick="document.location.href='<?php echo tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $category_path_string . '&cID=' . $cInfo->categories_id . '&action=edit_category'); ?>'">
            <td align="left" class="smallText">&nbsp;<?php echo $rows; ?>.&nbsp;</td>
            <td class="smallText">&nbsp;
            <?php
             // echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $category_path_string . '&cID=' . $cInfo->categories_id . '&action=edit_category') . '" class="blacklink">' . $products['products_name'] . '</a>'; 
             echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $products['categories_id']) . '&pID=' .$products['products_id'] . '" class="blacklink">' . $products['products_name'] . '</a>'; 
             ?>
             &nbsp;</td>
            <td align="right" class="smallText">&nbsp;<?php echo $products['products_quantity']; ?>&nbsp;</td>
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
    </table></td>
<!-- body_text_eof //-->
  </tr>
</table>
<!-- body_eof //-->

<?php include( THEME . '/html/footer.php' ); ?>

<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>