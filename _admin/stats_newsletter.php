<?php
/*
 $Id: stats_newsletter.php,v 1.1 2007/11/22 09:00:00 
 Contribution by Nekosign, webmaster@nekosign.com 

 osCommerce, Open Source E-Commerce Solutions
 http://www.oscommerce.com

 Copyright (c) 2002 osCommerce

 Released under the GNU General Public License
*/

  require('includes/application_top.php');

?>


<?php include( THEME . '/html/header.php' ); ?>

<!-- body //-->
<table border="0" width="100%" cellspacing="2" cellpadding="2">
  <tr>
<!-- body_text //-->
    <td width="100%" valign="top">      

    <table border="0" width="100%" cellspacing="0" cellpadding="2">
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo HEADING_TITLE; ?></td>
          </tr>
        </table></td>
      </tr>
<?php
  if ($_GET['cpage'] > 1) $rows = $_GET['cpage'] * MAX_DISPLAY_SEARCH_RESULTS - MAX_DISPLAY_SEARCH_RESULTS;

    $customers_query_raw = "select c.customers_id, c.customers_firstname, c.customers_lastname, c.customers_email_address, c.customers_newsletter , b.customers_info_id , b.customers_info_date_account_created as date_account_created
                            from " . TABLE_CUSTOMERS . " c," . TABLE_CUSTOMERS_INFO . " b where c.customers_id = b.customers_info_id and c.customers_newsletter = '1'
                            order by b.customers_info_date_account_created, c.customers_lastname, c.customers_firstname";


    $customers_split = new splitPageResults($_GET['cpage'], MAX_DISPLAY_SEARCH_RESULTS, $customers_query_raw, $customers_query_numrows);
    $customers_query = tep_db_query($customers_query_raw);
    
?>
      <tr>
          <td class="dataTableContent" colspan="3"><?php echo TEXT_DESCRIPTION; ?><strong><?php echo $customers_query_numrows; ?></strong></td>
      </tr>
      <tr>
          <td>&nbsp;</td>
      </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="4">
              <tr class="dataTableHeadingRow">
                <td class="dataTableHeadingContent" width="10%"><?php echo TABLE_HEADING_NUMBER; ?></td>
                <td class="dataTableHeadingContent" width="20%"><?php echo TABLE_HEADING_LAST_NAME; ?></td>
                <td class="dataTableHeadingContent" width="20%"><?php echo TABLE_HEADING_FIRST_NAME; ?></td>
                <td class="dataTableHeadingContent" width="30%"><?php echo TABLE_HEADING_EMAIL; ?></td>
                <td class="dataTableHeadingContent" width="25%"><?php echo TABLE_HEADING_CREATE; ?></td>
              </tr>

<?php      

    while ($customers = tep_db_fetch_array($customers_query)) {
      $rows++;
                
                if (strlen($rows) < 2) {
        $rows = '0' . $rows;
      }
?>
              <tr class="dataTableRow" onMouseOver="this.className='dataTableRowOver';this.style.cursor='hand'" onMouseOut="this.className='dataTableRow'">
                <td width="30" nowrap class="dataTableContent"><?php echo $rows; ?>.</td>
                <td class="dataTableContent"><?php echo '<a href="' . tep_href_link(FILENAME_CUSTOMERS, tep_get_all_get_params(array('cID', 'action')) . 'cID=' . $customers['customers_id']. '&action=edit') . '">' . $customers['customers_lastname'] . '</a>';?>&nbsp;</td>
                <td class="dataTableContent"><?php echo $customers['customers_firstname']; ?></td>
                <td class="dataTableContent"><?php echo '<a href="' . tep_href_link(FILENAME_MAIL, 'selected_box=tools&customer=' . $customers['customers_email_address'], 'NONSSL') . '">' . $customers['customers_email_address'] . '</a>'; ?>&nbsp;</td>
                <td class="dataTableContent"><?php echo tep_date_short($customers['date_account_created']); ?></td>
              </tr>
<?php
    }
?>
            </table></td>
          </tr>
          <tr>
            <td colspan="3"><table class="table-page" border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr>
                <td class="smallText" valign="top"><?php echo $customers_split->display_count($customers_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, $_GET['cpage'], 'Total <b>%s</b> de <b>%s</b> (de <b>%s</b> resultados)' , '', 'cpage'); ?></td>
                <td class="smallText" align="right"><?php echo $customers_split->display_links($customers_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, MAX_DISPLAY_PAGE_LINKS, $_GET['cpage'], tep_get_all_get_params(array('cpage')), 'cpage'); ?></td>
              </tr>
            </table></td>
          </tr>
        </table></td>
      </tr>
    </table>
        </td>
<!-- body_text_eof //-->
  </tr>
</table>
<!-- body_eof //-->


<?php include( THEME . '/html/footer.php' ); ?>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>