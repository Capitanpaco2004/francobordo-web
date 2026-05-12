<?php
/*
  $Id: stats_configuration_changes.php,v 1.29 2003/06/29 22:50:52 hpdl Exp $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
*/

  require('includes/application_top.php');
?>

<?php require(THEME . 'html/header.php'); ?>



<table border="0" width="100%" cellspacing="2" cellpadding="2">
  <tr>
<!-- body_text //-->
    <td width="100%" valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="0">
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo HEADING_TITLE; ?></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr class="dataTableHeadingRow">
                <td class="dataTableHeadingContent" width="20%"><?php echo TABLE_HEADING_TITLE; ?></td>
                <td class="dataTableHeadingContent" width="25%"><?php echo TABLE_HEADING_DESCRIPTION; ?></td>
                <td class="dataTableHeadingContent" width="15%"><?php echo TABLE_HEADING_DATE_CHANGE; ?></td>
                <td class="dataTableHeadingContent" width="20%"><?php echo TABLE_HEADING_PREVIOUS_SETTING; ?></td>
                <td class="dataTableHeadingContent" width="20%"><?php echo TABLE_HEADING_NEW_SETTING; ?></td>
              </tr>
<?php
  if (isset($_GET['page']) && ($_GET['page'] > 1)) $rows = $_GET['page'] * 50;
  $rows = 0;
  $changes_query_raw = "select previous_setting, new_setting, change_date, change_title, change_description from " . TABLE_CONFIGURATION_CHANGES . " order by change_id desc";
  $changes_split = new splitPageResults($_GET['page'], 50, $changes_query_raw, $changes_query_numrows);
  $changes_query = tep_db_query($changes_query_raw);
  while ($changes = tep_db_fetch_array($changes_query)) {
    $rows++;

    if (strlen($rows) < 2) {
      $rows = '0' . $rows;
    }
?>
              <tr class="dataTableRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)">
                <td class="dataTableContent" valign="top"><?php echo $changes['change_title']; ?></td>
                <td class="dataTableContent" valign="top"><?php echo $changes['change_description']; ?></td>
                <td class="dataTableContent" valign="top"><?php echo $changes['change_date']; ?></td>
                <td class="dataTableContent" valign="top"><?php echo $changes['previous_setting']; ?></td>
                <td class="dataTableContent" valign="top"><?php echo $changes['new_setting']; ?></td>
              </tr>
<?php
  }
?>
            </table></td>
          </tr>
          <tr>
            <td colspan="3"><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr>
                <td class="smallText" valign="top"><?php echo $changes_split->display_count($changes_query_numrows, 50, $_GET['page'], TEXT_DISPLAY_NUMBER_OF_PRODUCTS); ?></td>
                <td class="smallText" align="right"><?php echo $changes_split->display_links($changes_query_numrows, 50, MAX_DISPLAY_PAGE_LINKS, $_GET['page']); ?></td>
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

<?php require(THEME . 'html/footer.php'); ?>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>