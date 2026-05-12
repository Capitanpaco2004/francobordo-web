<?php
/*
  $Id: support_details.php,v 1.1 2003/02/04 16:07:59 puddled Exp $
  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com
  Copyright (c) 2002 osCommerce
  Released under the GNU General Public License
*/
// okay lets run some database queries
 $ticket_query = tep_db_query("SELECT * FROM " . TABLE_SUPPORT_TICKETS . " where ticket_id = '" . $_GET['ticket_id'] . "' and customers_id = '" . $customer_id . "'");
  $ticket = tep_db_fetch_array($ticket_query);
  $dept_query = tep_db_query("SELECT support_department_name FROM " . TABLE_SUPPORT_DEPARTMENT . " where support_department_id = '" . $ticket['department_id'] . "' and language_id = '" . $languages_id . "'");
  $department = tep_db_fetch_array($dept_query);
  $status_query = tep_db_query("SELECT support_status_name FROM " . TABLE_SUPPORT_STATUS . " where support_status_id = '" . $ticket['ticket_status'] . "' and language_id = '" . $languages_id . "'");
  $status = tep_db_fetch_array($status_query);
  $priority_query = tep_db_query("SELECT support_priority_name FROM " . TABLE_SUPPORT_PRIORITY . " where support_priority_id = '" . $ticket['priority_id'] . "' and language_id = '" . $languages_id . "'");
  $priority = tep_db_fetch_array($priority_query);
  $assigned_query = tep_db_query("SELECT support_assign_name FROM " . TABLE_SUPPORT_ASSIGN . " where support_assign_id = '" . $ticket['admin_id'] . "'");
  $assigned = tep_db_fetch_array($assigned_query);
  $default_status_query = tep_db_query("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " where configuration_key = 'DEFAULT_SUPPORT_TICKET_STATUS'");
  $default_status = tep_db_fetch_array($default_status_query);
  $default_admin_query = tep_db_query("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " where configuration_key = 'DEFAULT_SUPPORT_ADMIN_ID'");
  $default_admin = tep_db_fetch_array($default_admin_query);
  $default_priority_query = tep_db_query("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " where configuration_key = 'DEFAULT_SUPPORT_TICKET_PRIORITY'");
  $default_priority = tep_db_fetch_array($default_priority_query);
   $default_department_query = tep_db_query("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " where configuration_key = 'DEFAULT_SUPPORT_TICKET_DEPARTMENT'");
  $default_department = tep_db_fetch_array($default_department_query);
?>
<table border="0" width="100%" cellspacing="0" cellpadding="2">
  <tr>
    <td class="formAreaTitle"><?php echo CATEGORY_PERSONAL; ?></td>
  </tr>
  <tr>
    <td class="main"><table border="0" width="100%" cellspacing="0" cellpadding="2" class="formArea">
      <tr>
        <td class="main"><table border="0" cellspacing="0" cellpadding="2">
          <tr>
            <td class="main">&nbsp;<?php echo ENTRY_NAME; ?></td>
            <td class="main">&nbsp;
<?php
    echo $ticket['customers_name'] . tep_draw_hidden_field('name', $ticket['customers_name']);
?></td>
          </tr>
          <tr>
            <td class="main">&nbsp;<?php echo ENTRY_EMAIL_ADDRESS; ?></td>
            <td class="main">&nbsp;
<?php
   echo $ticket['customers_email_address'] . tep_draw_hidden_field('email_address', $ticket['customers_email_address']);
?></td>
          </tr>
        </table></td>
      </tr>
    </table></td>
  </tr>
 <tr>
    <td class="formAreaTitle"><br><?php echo TICKET_DETAILS; ?></td>
  </tr>
  <tr>
    <td class="main"><table border="0" width="100%" cellspacing="0" cellpadding="2" class="formArea">
      <tr>
        <td class="main"><table border="0" cellspacing="0" cellpadding="2" width=100%>
          <tr>
            <td class="main" align=right><strong><?php echo ENTRY_SUBJECT; ?></strong></td>
            <td class="main" width=80%>
<?php
      echo tep_draw_input_field('domain', $ticket['customers_domain']) ;
?></td>
          <tr>
            <td class="main" align=right><strong><?php echo ENTRY_PRIORITY; ?></strong></td>
            <td class="main">
<?php
    $priority_query = tep_db_query("select * from ". TABLE_SUPPORT_PRIORITY . " where language_id = '" . $languages_id . "' order by support_priority_id desc");
$select_box = '<select name="support_priority"  size="' . MAX_MANUFACTURERS_LIST . '">';
    if (MAX_MANUFACTURERS_LIST < 2) {
          }
    while ($priority_values = tep_db_fetch_array($priority_query)) {
      $select_box .= '<option value="' . $priority_values['support_priority_id'] . '"';
      if ($default_priority['configuration_value'] ==  $priority_values['priority_id']) $select_box .= ' SELECTED';
      $select_box .= '>' . substr($priority_values['support_priority_name'], 0, MAX_DISPLAY_MANUFACTURER_NAME_LEN) . '</option>';
    }
    $select_box .= "</select>";
    $select_box .= tep_hide_session_id();
    echo $select_box;
?></td>
          </tr>
          <tr>
            <td class="main" align=right><strong><?php echo ENTRY_DEPARTMENT; ?></strong></td>
            <td class="main">
<?php
   $department_query = tep_db_query("select * from ". TABLE_SUPPORT_DEPARTMENT . " where language_id = '" . $languages_id . "' order by support_department_id");
$select_box = '<select name="support_dept"  size="' . MAX_MANUFACTURERS_LIST . '">';
    if (MAX_MANUFACTURERS_LIST < 2) {
          }
    while ($department_values = tep_db_fetch_array($department_query)) {
      $select_box .= '<option value="' . $department_values['support_department_id'] . '"';
      if ($default_department['configuration_value'] ==  $department_values['support_department_id']) $select_box .= ' SELECTED';
      $select_box .= '>' . substr($department_values['support_department_name'], 0, MAX_DISPLAY_MANUFACTURER_NAME_LEN) . '</option>';
    }
    $select_box .= "</select>";
    $select_box .= tep_hide_session_id();
    echo $select_box;
?></td>
          </tr>
          <tr>
            <td class="main" valign=top align=right><strong>Your Ticket</strong></td>
            <td class="main" >
			    <?php new infoBox(array(array('text' => $ticket['ticket_comments']))); ?></td>
          </tr>
          <tr>
            <td class="main" valign=top align=right><strong>Add to Ticket</strong></td>
            <td class="main" >
<?php
     echo tep_draw_textarea_field('comments', 'soft', '60', '5', '');
?></td>
          </tr>
        </table></td>
      </tr>
    </table></td>
  </tr>
  <tr>
    <td class="formAreaTitle"><br><?php echo CATEGORY_ADMIN; ?></td>
  </tr>
  <tr>
    <td class="main"><table border="0" width="100%" cellspacing="0" cellpadding="2" class="formArea">
      <tr>
        <td class="main"><table border="0" cellspacing="0" cellpadding="2">
          <tr>
            <td class="main">&nbsp;<?php echo ENTRY_ASSIGN; ?></td>
            <td class="main">&nbsp;
<?php
  echo $assigned['support_assign_name'];
?></td>
          </tr>
          <tr>
            <td class="main">&nbsp;<?php echo ENTRY_LAST_MODIFIED; ?></td>
            <td class="main">&nbsp;
<?php
 echo tep_date_long($ticket['last_modified']);
?></td>
          </tr>
          <tr>
            <td class="main">&nbsp;<?php echo ENTRY_LAST_STATUS; ?></td>
            <td class="main">&nbsp;
<?php
 echo $status['support_status_name'];
?></td>
          </tr>
          <tr>
            <td class="main">&nbsp;<?php echo ENTRY_ADMIN_COMMENTS; ?></td>
            <td class="main">&nbsp;
<?php
 echo ((strlen($ticket['admin_comments']) > 0) ? nl2br($ticket['admin_comments']) : '<i>' . TEXT_NO_COMMENTS_AVAILABLE . '</i>');
?></td>
          </tr>
        </table></td>
      </tr>
    </table></td>
  </tr>
</table>
