<?php
/*

$Id: total_configuration.php

//----------------------------------------------------------------------------
// Copyright (c) 2006-2007 Asymmetric Software - Innovation & Excellence
// Author: Mark Samios
// http://www.asymmetrics.com
// Total Configuration module for osCommerce Admin
// Based on admin\configuration.php v1.43
//----------------------------------------------------------------------------
// Script is intended to be used with:
// osCommerce, Open Source E-Commerce Solutions
// http://www.oscommerce.com
// Copyright (c) 2003 osCommerce
//----------------------------------------------------------------------------
// Released under the GNU General Public License
//----------------------------------------------------------------------------
[[[[                                                    ]]]]
[[[[ Table adjustments by http://www.ButteWebDesign.com ]]]]
[[[[ Date: 8-21-07                                      ]]]]
//----------------------------------------------------------------------------
*/

  require('includes/application_top.php');
  require(DIR_WS_CLASSES . 'currencies.php');
  $currencies = new currencies();

  $action = (isset($_GET['action']) ? $_GET['action'] : '');
  $gID = (isset($_GET['gID']) ? $_GET['gID'] : '');

  switch($action) {
    case 'save':
      $configuration_value = tep_db_prepare_input($_POST['configuration_value']);
      $cID = tep_db_prepare_input($_GET['cID']);

      tep_db_query("update " . TABLE_CONFIGURATION . " set configuration_value = '" . tep_db_input($configuration_value) . "', last_modified = now() where configuration_id = '" . (int)$cID . "'");

      tep_redirect(tep_href_link(FILENAME_TOTAL_CONFIGURATION, 'gID=' . $_GET['gID'] . '&cID=' . $cID));
      break;
    case 'modify_confirm':
      if( $_POST['sort_duplicates'] == 'on' ) {
        $duplicates_query = tep_db_query("select configuration_key, configuration_id, count(*) as total from " . TABLE_CONFIGURATION . " group by configuration_key having count(*) > 1");
        $duplicates_array = array();
        while($duplicates = tep_db_fetch_array($duplicates_query) ) {
          $duplicates_array[$duplicates['configuration_key']] = $duplicates;
        }

        foreach($duplicates_array as $key => $value) {
          tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_id = '" . (int)$value['configuration_id'] ."'");
        }
      }
      if($_POST['sort_config'] == 'on') {
        tep_db_query("alter table " . TABLE_CONFIGURATION . " drop configuration_id");
        tep_db_query("alter table " . TABLE_CONFIGURATION . " add configuration_id INT( 11 ) not null auto_increment first, add primary key (configuration_id)");
      }
      tep_redirect(tep_href_link(FILENAME_TOTAL_CONFIGURATION));
      break;
     case 'modify':
      if( $_POST['sort_duplicates'] == '0' && $_POST['sort_config'] == '0' ) {
        $action = '';
        break;
      }
      break;
			// BEGIN add/edit configuration field values
      case 'insert_value':
      case 'update_value':

        $sql_data_array = array('configuration_title' => tep_db_prepare_input($_POST['title']),
								'configuration_key' => tep_db_prepare_input($_POST['key']),
								'configuration_value' => tep_db_prepare_input($_POST['value']),
								'configuration_description' => tep_db_prepare_input($_POST['description']),
								'configuration_group_id' => tep_db_prepare_input($_POST['group_id']),
								'sort_order' => tep_db_prepare_input($_POST['sort_order']),
								'use_function' => tep_db_prepare_input($_POST['use_function']),
								'set_function' => tep_db_prepare_input($_POST['set_function']),
								'date_added' => tep_db_prepare_input($_POST['date_added']));

		if ($action == 'insert_value') {
			$insert_sql_data = array('date_added' => 'now()');

			$sql_data_array = array_merge($sql_data_array, $insert_sql_data);

			tep_db_perform(TABLE_CONFIGURATION, $sql_data_array);

			$cID = tep_db_insert_id();

			$messageStack->add_session(SUCCESS_HIDDEN_FIELD_ADDED, 'success');		

        } elseif ($action == 'update_value') {
          $update_sql_data = array('last_modified' => 'now()');

          $sql_data_array = array_merge($sql_data_array, $update_sql_data);

          tep_db_perform(TABLE_CONFIGURATION, $sql_data_array, 'update', "configuration_id = '" . (int)$_GET['cID'] . "'");
        	$cID = $_GET['cID'];

		  $messageStack->add_session(SUCCESS_HIDDEN_FIELD_UPDATED, 'success');
        }
          tep_redirect(tep_href_link(FILENAME_TOTAL_CONFIGURATION, 'gID=' . (int)$_GET['gID'] . '&cID=' . (int)$cID));
        break;

      case 'delete_confirm':

		if (isset($_GET['cID']))
		  tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_id = '" . (int)$_GET['cID'] . "'");

		  $messageStack->add_session(SUCCESS_HIDDEN_FIELD_REMOVED, 'success');

          	tep_redirect(tep_href_link(FILENAME_TOTAL_CONFIGURATION, 'gID=' . (int)$_GET['gID']) . (isset($_GET['cID']) ? '&cID=' . $_GET['cID'] : ''));

        break;
	  // END add/edit configuration field values
    default:
      break;
  }

	 // BEGIN add/edit configuration field values
		$show_hidden = tep_db_query('select configuration_value from ' . TABLE_CONFIGURATION . ' where configuration_key = "ENABLE_ADMIN_CONFIGURATION_CHANGING"');

		if (!tep_db_num_rows($show_hidden))
		    tep_db_query('insert into ' . TABLE_CONFIGURATION . ' (configuration_key, configuration_value, date_added) values ("ENABLE_ADMIN_CONFIGURATION_CHANGING", "0", now())');
		else {
			$show_hidden = tep_db_fetch_array($show_hidden);
        $status = false;

        if ($show_hidden['configuration_value'] != '0')
		$status = true;			
		}

		if (isset($_GET['showhide_configuration'])) {
		    tep_db_query("update " . TABLE_CONFIGURATION . " set configuration_value = '" . tep_db_prepare_input($_GET['showhide_configuration']) . "', last_modified = now() where configuration_key = 'ENABLE_ADMIN_CONFIGURATION_CHANGING'");

		if ($show_hidden['configuration_value'] == '0') {

			$messageStack->add_session(SUCCESS_HIDDEN_FIELDS_ACTIVE, 'success');
		} else {
			$messageStack->add_session(SUCCESS_HIDDEN_FIELDS_DEACTIVATED, 'success');
		}
		tep_redirect(tep_href_link(FILENAME_TOTAL_CONFIGURATION, 'gID=' . (int)$_GET['gID']));

        $status = false;		
		}
	  // END add/edit configuration field values 
		
  $cfg_group_query = tep_db_query("select distinct configuration_group_id as id, CONCAT('Group','-',configuration_group_id) as text from " . TABLE_CONFIGURATION . " order by configuration_group_id");
  $group_array = array( array('id' => '0', 'text' => 'Show All'));
  $cfg_name_array = tep_db_query("select configuration_group_id, configuration_group_title from " . TABLE_CONFIGURATION_GROUP . " order by configuration_group_id");
	 $name_array = array( array('id' => '0', 'text' => 'Show All'));
	 while ($cfg_name = tep_db_fetch_array($cfg_name_array)) {
          $name_array[] = array('id' => $cfg_name['configuration_group_id'], 'text' => $cfg_name['configuration_group_title']);	}								
											
  while($group_array[]=tep_db_fetch_array($cfg_group_query));
  array_pop($group_array);
  if( $gID != 0 ) {
    $cfg_group_query = tep_db_query("select configuration_group_title from " . TABLE_CONFIGURATION_GROUP . " where configuration_group_id = '" . (int)$gID . "'");
    $group_array = array( array('id' => '0', 'text' => 'Show All'));
    if( $cfg_group = tep_db_fetch_array($cfg_group_query) ) {
      $group_name = $cfg_group['configuration_group_title'];
    } else {
      $group_name = 'Unnamed Group-ID=' . $gID;
    }
  }
?>
<?php include( THEME . '/html/header.php' ); ?>

<!-- body //-->
<table border="0" width="100%" cellspacing="2" cellpadding="2">
  <tr>
<!-- body_text //-->
    <td width="100%" valign="top" ><table border="0" width="100%" cellspacing="0" cellpadding="2">
<?php
  // Modify/Confirm screen
  if($action == 'modify' && $gID == 0) {
?>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo HEADING_CONFIRM; ?></td>
          </tr>
          <tr>
            <td><hr /></td>
          </tr>
          <tr>
            <td><?php echo tep_draw_form('global_form', FILENAME_TOTAL_CONFIGURATION, tep_get_all_get_params(array('action, gID')) . 'action=modify_confirm', 'post') ?><table border="0" width="100%" cellspacing="0" cellpadding="0">
<?php
    if($_POST['sort_duplicates'] == 'on') {
?>
              <tr>
                <td class="smallText"><?php echo TEXT_INFO_CONFIRM_DUPLICATES; ?></td>
              </tr>
              <tr>
                <td><?php echo tep_draw_separator('pixel_trans.png', '100%', '8'); ?></td>
              </tr>
              <tr>
                <td valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
                  <tr class="dataTableHeadingRow">
                    <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_CONFIGURATION_ID; ?></td>
                    <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_CONFIGURATION_KEY; ?></td>
                  </tr>
<?php
      $duplicates_query = tep_db_query("select configuration_key, configuration_id, count(*) as total from " . TABLE_CONFIGURATION . " group by configuration_key having count(*) > 1");
      $duplicates_array = array();

      while($duplicates = tep_db_fetch_array($duplicates_query) ) {
        $duplicates_array[$duplicates['configuration_key']] = $duplicates;
      }
      foreach($duplicates_array as $key => $value) {
?>
                  <tr class="dataTableRow">
                    <td class="dataTableContent"><?php echo $value['configuration_id']; ?></td>
                    <td class="dataTableContent"><?php echo $value['configuration_key'];; ?></td>
                  </tr>
<?php
      }
?>
                </table></td>
              </tr>
              <tr>
                <td><?php echo tep_draw_separator('pixel_trans.png', '1', '8'); ?></td>
              </tr>
<?php
    }
    if($_POST['sort_config'] == 'on') {
?>
              <tr>
                <td class="smallText"><b><?php echo TEXT_INFO_CONFIRM_CONFIG; ?></b></td>
              </tr>
<?php
    }
    foreach( $_POST as $key => $value ) {
      echo tep_draw_hidden_field($key, $value);
    }
?>
              <tr>
                <td><?php echo tep_draw_separator('pixel_trans.png', '100%', '6'); ?></td>
              </tr>
              <tr>
                <td class="main"><?php echo tep_image_submit('button_confirm.png', IMAGE_CONFIRM) . ' <a href="' . tep_href_link(FILENAME_TOTAL_CONFIGURATION) .'">' . tep_image_button('button_cancel.png', IMAGE_CANCEL) . '</a>'; ?></td>
              </tr>
            </table></form></td>
          </tr>
        </table></td>
      </tr>
<?php
  // Show them all
  } elseif($gID == 0) {
?>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo HEADING_ALL; ?></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><?php echo tep_draw_form('global_form', FILENAME_TOTAL_CONFIGURATION, 'action=modify', 'post') ?><table border="0" width="400" cellspacing="0" cellpadding="0">
          <tr>
            <td class="formarea"><table width="400" border="0" cellspacing="0" cellpadding="0">
                <tr>
                  <td class="smallText"><b><?php echo '&nbsp;&nbsp;' . TEXT_INFO_OPTIMIZE_SORT; ?></b></td>
                  <td class="smallText"><b><?php echo TEXT_INFO_OPTIMIZE_DUPLICATES; ?></b></td>
                </tr>
                <tr>
                  <td class="smallText"><?php echo '&nbsp;&nbsp;' . TEXT_INFO_NO_CHANGES . '&nbsp;' . tep_draw_radio_field('sort_config', '0', true); ?></td>
                  <td class="smallText"><?php echo TEXT_INFO_NO_CHANGES . '&nbsp;' . tep_draw_radio_field('sort_duplicates', '0', true); ?></td>
                </tr>
                <tr>
                  <td class="smallText"><?php echo '&nbsp;&nbsp;' . TEXT_INFO_ENABLE . '&nbsp;' . tep_draw_radio_field('sort_config', 'on', false); ?></td>
                  <td class="smallText"><?php echo TEXT_INFO_ENABLE . '&nbsp;' . tep_draw_radio_field('sort_duplicates', 'on', false); ?></td>
                </tr>
                <tr>
                  <td colspan="2" class="smallText"><br><br><?php echo '&nbsp;&nbsp;' . tep_image_submit('button_send.png', IMAGE_SUBMIT); ?> <br><br></td>
                </tr>
              </table></td>
          </tr>
          <tr>
            <td><?php echo tep_draw_separator('pixel_trans.png', '100%', '6'); ?></td>
          </tr>
          <tr>
            <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
              <tr>
                <td><?php echo tep_draw_separator('pixel_trans.png', '12', '1'); ?></td>
                <td class="smallText"><?php echo TEXT_INFO_OPERATION ?></td>
              </tr>
            </table></td>
          </tr>
        </table></form></td>
      </tr>
      <tr>
        <td><hr /></td>
      </tr>

<?php
    //unset($group_array[0]);
		$group_array[0] = NULL;
		$name_array[0] = NULL; 
		$sub_group_array = array_slice($group_array,1,sizeof($group_array)); 
		 
		foreach($sub_group_array as $key => $value) {
      $cfg_group_query = tep_db_query("select configuration_group_title from " . TABLE_CONFIGURATION_GROUP . " where configuration_group_id = '" . (int)$value['id'] . "'");
      if( tep_db_num_rows($cfg_group_query) ) {
        $cfg_group = tep_db_fetch_array($cfg_group_query);
        $group_name = $cfg_group['configuration_group_title'];
      } else {
        $group_name = 'Unnamed'; if ($value['id'] == 0) continue;
      }
?>
      <tr>
        <td class="main">
<?php
    echo tep_draw_form('group', FILENAME_TOTAL_CONFIGURATION, '', 'get');
    echo HEADING_SELECT . '&nbsp;' . tep_draw_pull_down_menu('gID', $name_array, $gID, 'onChange="this.form.submit();"');
    echo '</form>';
?>
            </td>
      </tr>
      <tr>
        <td class="pageHeading"><?php echo $value['id'] . '.&nbsp;' . $group_name; ?></td>
      </tr>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.png', '100%', '4'); ?></td>
      </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr class="dataTableHeadingRow">
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_CONFIGURATION_ID; ?></td>
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_CONFIGURATION_KEY; ?></td>
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_CONFIGURATION_TITLE; ?></td>
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_CONFIGURATION_VALUE; ?></td>
              </tr>
<?php
      $configuration_query = tep_db_query("select c.configuration_id, c.configuration_key, c.configuration_title, c.configuration_value, c.use_function from " . TABLE_CONFIGURATION . " c where c.configuration_group_id = '" . (int)$value['id'] . "' order by c.configuration_key");
      while ($configuration = tep_db_fetch_array($configuration_query)) {
        if (tep_not_null($configuration['use_function'])) {
          $use_function = $configuration['use_function'];
          if (preg_match('/->/', $use_function)) {
            $class_method = explode('->', $use_function);
            if (!is_object(${$class_method[0]})) {
              include(DIR_WS_CLASSES . $class_method[0] . '.php');
              ${$class_method[0]} = new $class_method[0]();
            }
            $cfgValue = tep_call_function($class_method[1], $configuration['configuration_value'], ${$class_method[0]});
          } else {
            $cfgValue = tep_call_function($use_function, $configuration['configuration_value']);
          }
        } else {
          $cfgValue = $configuration['configuration_value'];
        }
?>
              <tr class="dataTableRow">
                <td class="dataTableContent"><?php echo $configuration['configuration_id']; ?></td>
                <td class="dataTableContent"><?php echo $configuration['configuration_key']; ?></td>
                <td class="dataTableContent"><?php echo $configuration['configuration_title']; ?></td>
                <td class="dataTableContent"><?php echo htmlspecialchars($cfgValue); ?></td>
              </tr>
<?php
      }
?>
            </table></td>
          </tr>
          <tr>
            <td><hr /></td>
          </tr>
<?php
    }
?>
        </table></td>
      </tr>
<?php
  // Show selected group
  } else {
?>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo HEADING_TITLE . '&nbsp;&raquo;&nbsp;' . $group_name; ?></td>
            <td class="main" align="right">
<?php
    echo tep_draw_form('group', FILENAME_TOTAL_CONFIGURATION, '', 'get');
    echo HEADING_SELECT . '&nbsp;' . tep_draw_pull_down_menu('gID', $name_array, $gID, 'onChange="this.form.submit();"');
    echo '</form>';
?>
            </td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr class="dataTableHeadingRow">
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_CONFIGURATION_ID; ?></td>
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_CONFIGURATION_KEY; ?></td>
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_CONFIGURATION_TITLE; ?></td>
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_CONFIGURATION_VALUE; ?></td>
                <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_ACTION; ?>&nbsp;</td>
              </tr>
<?php
  $configuration_query = tep_db_query("select c.configuration_id, c.configuration_key, c.configuration_title, c.sort_order, c.configuration_value, c.use_function from " . TABLE_CONFIGURATION . " c where c.configuration_group_id = '" . (int)$gID . "' order by c.sort_order");
  while ($configuration = tep_db_fetch_array($configuration_query)) {
    if (tep_not_null($configuration['use_function'])) {
      $use_function = $configuration['use_function'];
      if (preg_match('/->/', $use_function)) {
        $class_method = explode('->', $use_function);
        if (!is_object(${$class_method[0]})) {
          include(DIR_WS_CLASSES . $class_method[0] . '.php');
          ${$class_method[0]} = new $class_method[0]();
        }
        $cfgValue = tep_call_function($class_method[1], $configuration['configuration_value'], ${$class_method[0]});
      } else {
        $cfgValue = tep_call_function($use_function, $configuration['configuration_value']);
      }
    } else {
      $cfgValue = $configuration['configuration_value'];
    }
    if (isset($_GET['cID']) && ($_GET['cID'] == NULL)) {unset ($_GET['cID']);} // fix cancel bug
    if ((!isset($_GET['cID']) || (isset($_GET['cID']) && ($_GET['cID'] == $configuration['configuration_id']))) && !isset($cInfo) && (substr($action, 0, 3) != 'new')) {
      $cfg_extra_query = tep_db_query("select configuration_key, configuration_description, date_added, last_modified, use_function, set_function from " . TABLE_CONFIGURATION . " where configuration_id = '" . (int)$configuration['configuration_id'] . "'");
      $cfg_extra = tep_db_fetch_array($cfg_extra_query);

      $cInfo_array = array_merge($configuration, $cfg_extra);
      $cInfo = new objectInfo($cInfo_array);
    }

    if ( (isset($cInfo) && is_object($cInfo)) && ($configuration['configuration_id'] == $cInfo->configuration_id) ) {
      echo '                  <tr id="defaultSelected" class="dataTableRowSelected" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href=\'' . tep_href_link(FILENAME_TOTAL_CONFIGURATION, 'gID=' . $gID . '&cID=' . $cInfo->configuration_id . '&action=edit') . '\'">' . "\n";
    } else {
      echo '                  <tr class="dataTableRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href=\'' . tep_href_link(FILENAME_TOTAL_CONFIGURATION, 'gID=' . $gID . '&cID=' . $configuration['configuration_id']) . '\'">' . "\n";
    }
?>
                <td class="dataTableContent"><?php echo $configuration['configuration_id']; ?></td>
                <td class="dataTableContent"><?php echo $configuration['configuration_key']; ?></td>
                <td class="dataTableContent"><?php echo $configuration['configuration_title']; ?></td>
                <td class="dataTableContent"><?php echo htmlspecialchars($cfgValue); ?></td>
                <td class="dataTableContent" align="right"><?php if ( (isset($cInfo) && is_object($cInfo)) && ($configuration['configuration_id'] == $cInfo->configuration_id) ) { echo tep_image(DIR_WS_IMAGES . 'icon_arrow_right.png', ''); } else { echo '<a href="' . tep_href_link(FILENAME_TOTAL_CONFIGURATION, 'gID=' . $gID . '&cID=' . $configuration['configuration_id']) . '">' . tep_image(DIR_WS_IMAGES . 'icon_info.png', IMAGE_ICON_INFO) . '</a>'; } ?>&nbsp;</td>
              </tr>
<?php
    }
?>
            </table></td>
<?php
    $heading = array();
    $contents = array();

    switch ($action) {
      case 'edit':
        $heading[] = array('text' => '<b>' . $cInfo->configuration_title . '</b>');

        if ($cInfo->set_function) {
          eval('$value_field = ' . $cInfo->set_function . '"' . htmlspecialchars($cInfo->configuration_value) . '");');
        } else {
          $value_field = tep_draw_input_field('configuration_value', $cInfo->configuration_value);
        }

        $contents = array('form' => tep_draw_form('configuration', FILENAME_TOTAL_CONFIGURATION, 'gID=' . $gID . '&cID=' . $cInfo->configuration_id . '&action=save'));
        $contents[] = array('text' => TEXT_INFO_EDIT_INTRO);
        $contents[] = array('text' => '<br><b>' . $cInfo->configuration_title . '</b><br>' . $cInfo->configuration_description . '<br>' . $value_field);
        $contents[] = array('align' => 'center', 'text' => '<br>' . tep_image_submit('button_update.png', IMAGE_UPDATE) . '&nbsp;<a href="' . tep_href_link(FILENAME_TOTAL_CONFIGURATION, 'gID=' . $gID . '&cID=' . $cInfo->configuration_id) . '">' . tep_image_button('button_cancel.png', IMAGE_CANCEL) . '</a>');
        break;
			 // BEGIN add/edit configuration field values
    case 'new':
      $heading[] = array('text' => '&nbsp;<font color="#0000FF"><b>' . TEXT_INFO_HEADING_NEW . '</b></font>');

      $contents = array('form' => tep_draw_form('configuration', FILENAME_TOTAL_CONFIGURATION, 'gID=' . (int)$_GET['gID'] . '&action=insert_value'));
      $contents[] = array('text' => '<br>&nbsp;' . TEXT_INFO_NEW);
      $contents[] = array('text' => '<br>&nbsp;' . TEXT_CONFIGURATION_TITLE . '<br>&nbsp;' . tep_draw_input_field('title', '', 'size="32"'));
      $contents[] = array('text' => '<br>&nbsp;' . TEXT_CONFIGURATION_KEY . '<br>&nbsp;' . tep_draw_input_field('key', '', 'size="32"'));
      $contents[] = array('text' => '<br>&nbsp;' . TEXT_CONFIGURATION_VALUE . '<br>&nbsp;' . tep_draw_input_field('value', '', 'size="32"'));
      $contents[] = array('text' => '<br>&nbsp;' . TEXT_CONFIGURATION_DESCRIPTION . '<br>&nbsp;' . tep_draw_textarea_field('description', 'soft', '34', '5'));
      $contents[] = array('text' => '<br>&nbsp;' . TEXT_CONFIGURATION_GROUP_ID . '<br>&nbsp;' . tep_draw_input_field('group_id', (int)$_GET['gID'], 'size="6" maxlength="6"'));
      $contents[] = array('text' => '<br>&nbsp;' . TEXT_CONFIGURATION_SORT_ORDER . '<br>&nbsp;' . tep_draw_input_field('sort_order', '', 'size="6" maxlength="6"'));
      $contents[] = array('text' => '<br>&nbsp;' . TEXT_CONFIGURATION_USE_FUNCTION . '<br>&nbsp;' . tep_draw_input_field('use_function', '', 'size="32"'));
      $contents[] = array('text' => '<br>&nbsp;' . TEXT_CONFIGURATION_SET_FUNCTION . '<br>&nbsp;' . tep_draw_textarea_field('set_function', 'soft', '34', '5'));
      $contents[] = array('align' => 'center', 'text' => '<br>' . tep_image_submit('button_save.png', IMAGE_UPDATE) . '&nbsp;<a href="' . tep_href_link(FILENAME_TOTAL_CONFIGURATION, 'gID=' . (int)$_GET['gID'] . '&cID=' . $cInfo->configuration_id) . '">' . tep_image_button('button_cancel.png', IMAGE_CANCEL) . '</a><br><br>');
      break;

    case 'edit_value':
      $heading[] = array('text' => '&nbsp;<font color="#0000FF"><b>' . TEXT_INFO_HEADING_EDIT . '</b></font>');

      $contents = array('form' => tep_draw_form('configuration', FILENAME_TOTAL_CONFIGURATION, 'gID=' . (int)$_GET['gID'] . '&cID=' . $cInfo->configuration_id . '&action=update_value'));
      $contents[] = array('text' => '<br>&nbsp;' . TEXT_INFO_EDIT);
      $contents[] = array('text' => '<br>&nbsp;' . TEXT_CONFIGURATION_TITLE . '<br>&nbsp;' . tep_draw_input_field('title', $cInfo->configuration_title, 'size="32"'));
      $contents[] = array('text' => '<br>&nbsp;' . TEXT_CONFIGURATION_KEY . '<br>&nbsp;' . tep_draw_input_field('key', $cInfo->configuration_key, 'size="32"'));
      $contents[] = array('text' => '<br>&nbsp;' . TEXT_CONFIGURATION_VALUE . '<br>&nbsp;' . tep_draw_input_field('value', $cInfo->configuration_value, 'size="32"'));
      $contents[] = array('text' => '<br>&nbsp;' . TEXT_CONFIGURATION_DESCRIPTION . '<br>&nbsp;' . tep_draw_textarea_field('description', 'soft', '34', '5', $cInfo->configuration_description));
      $contents[] = array('text' => '<br>&nbsp;' . TEXT_CONFIGURATION_GROUP_ID . '<br>&nbsp;' . tep_draw_input_field('group_id', (int)$_GET['gID'], 'size="6" maxlength="6"'));
      $contents[] = array('text' => '<br>&nbsp;' . TEXT_CONFIGURATION_SORT_ORDER . '<br>&nbsp;' . tep_draw_input_field('sort_order', $cInfo->sort_order, 'size="6" maxlength="6"'));	  
      $contents[] = array('text' => '<br>&nbsp;' . TEXT_CONFIGURATION_USE_FUNCTION . '<br>&nbsp;' . tep_draw_input_field('use_function', $cInfo->use_function, 'size="32"'));
      $contents[] = array('text' => '<br>&nbsp;' . TEXT_CONFIGURATION_SET_FUNCTION . '<br>&nbsp;' . tep_draw_textarea_field('set_function', 'soft', '34', '5', $cInfo->set_function));

	  if (($cInfo->date_added == '' || NULL) || ($cInfo->date_added == '0000-00-00 00:00:00'))
	    $contents[] = array('text' => '<br>&nbsp;' . TEXT_CONFIGURATION_DATE_ADDED . '<br>&nbsp;' . tep_draw_input_field('date_added', '0000-00-00 00:00:00', 'size="19" maxlength="20"') . ' [ yyyy-mm-dd ]');
	  else
		$contents[] = array('text' => '<br>&nbsp;' . TEXT_CONFIGURATION_DATE_ADDED . '<br>&nbsp;' . tep_draw_input_field('date_added', $cInfo->date_added, 'size="19" maxlength="20"') . ' [ yyyy-mm-dd ]');

      $contents[] = array('align' => 'center', 'text' => '<br>' . tep_image_submit('button_save.png', IMAGE_UPDATE) . '&nbsp;<a href="' . tep_href_link(FILENAME_TOTAL_CONFIGURATION, 'gID=' . (int)$_GET['gID'] . '&cID=' . $cInfo->configuration_id) . '">' . tep_image_button('button_cancel.png', IMAGE_CANCEL) . '</a><br><br>');
      break;

	case 'edit_group':
	  $gInfo_array = tep_db_fetch_array(tep_db_query('select configuration_group_title, configuration_group_description, sort_order from ' . TABLE_CONFIGURATION_GROUP . ' where configuration_group_id = "' . (int)$_GET['gID'] . '"'));
      $gInfo = new objectInfo($gInfo_array);

      $heading[] = array('text' => '&nbsp;<font color="#0000FF"><b>' . $gInfo->configuration_group_title . '</b></font>');	  

      $contents = array('form' => tep_draw_form('configuration', FILENAME_TOTAL_CONFIGURATION, 'gID=' . (int)$_GET['gID'] . '&cID=' . $cInfo->configuration_id . '&action=update_group'));
      $contents[] = array('text' => '<br>&nbsp;' . TEXT_INFO_EDIT_GROUP);
      $contents[] = array('text' => '<br>&nbsp;' . TEXT_CONFIGURATION_GROUP_TITLE . '<br>&nbsp;' . tep_draw_input_field('configuration_group_title', $gInfo->configuration_group_title, 'size="32"'));
      $contents[] = array('text' => '<br>&nbsp;' . TEXT_CONFIGURATION_GROUP_DESCRIPTION . '<br>&nbsp;' . tep_draw_textarea_field('configuration_group_description', 'soft', '34', '5', $gInfo->configuration_group_description));
      $contents[] = array('text' => '<br>&nbsp;' . TEXT_CONFIGURATION_SORT_ORDER . '<br>&nbsp;' . tep_draw_input_field('sort_order', $gInfo->sort_order, 'size="6" maxlength="6"'));
      $contents[] = array('align' => 'center', 'text' => '<br>' . tep_image_submit('button_save.png', IMAGE_UPDATE) . '&nbsp;<a href="' . tep_href_link(FILENAME_TOTAL_CONFIGURATION, 'gID=' . (int)$_GET['gID'] . '&cID=' . $cInfo->configuration_id) . '">' . tep_image_button('button_cancel.png', IMAGE_CANCEL) . '</a><br><br>');
      break;

    case 'delete':
      $heading[] = array('text' => '&nbsp;<font color="#FF0000"><b>' . TEXT_INFO_HEADING_DELETE . '</b></font>');

	  $contents[] = array('text' => '<br>&nbsp;<font color="#FF0000">' . TEXT_INFO_DELETE . '</font>');
	  $contents[] = array('text' => '<br>&nbsp;' . TEXT_INFO_TITLE . '<br>&nbsp;' . $cInfo->configuration_title);
      $contents[] = array('text' => '<br>&nbsp;' . TEXT_INFO_KEY . '<br>&nbsp;' . $cInfo->configuration_key);
      $contents[] = array('align' => 'center', 'text' => '<br><a href="' . tep_href_link(FILENAME_TOTAL_CONFIGURATION, 'gID=' . (int)$_GET['gID'] . '&cID=' . $cInfo->configuration_id . '&action=delete_confirm') . '">' . tep_image_button('button_confirm.png', IMAGE_COFIRM) . '</a>&nbsp;<a href="' . tep_href_link(FILENAME_TOTAL_CONFIGURATION, 'gID=' . (int)$_GET['gID'] . '&cID=' . $cInfo->configuration_id) . '">' . tep_image_button('button_cancel.png', IMAGE_CANCEL) . '</a><br><br>');
      break;
// END add/edit configuration field values
	
      default:
			  if (isset($cInfo) && is_object($cInfo)) { 

          $heading[] = array('text' => '<b>' . $cInfo->configuration_title . '</b>');

          $contents[] = array('align' => 'center', 'text' => '<a href="' . tep_href_link(FILENAME_TOTAL_CONFIGURATION, 'gID=' . $gID . '&cID=' . $cInfo->configuration_id . '&action=edit') . '">' . tep_image_button('button_edit.png', IMAGE_EDIT) . '</a>');
          $contents[] = array('text' => '<br>' . $cInfo->configuration_description);
          if (($cInfo->date_added == '' || NULL) || ($cInfo->date_added == '0000-00-00 00:00:00'))
		  $contents[] = array('text' => '<br>&nbsp;' . TEXT_INFO_DATE_ADDED . '&nbsp;&nbsp; ' . 'Not Set');
		else
        $contents[] = array('text' => '<br>&nbsp;' . TEXT_INFO_DATE_ADDED . '&nbsp;&nbsp; ' . tep_date_short($cInfo->date_added));
        if (tep_not_null($cInfo->last_modified)) $contents[] = array('text' => '&nbsp;' . TEXT_INFO_LAST_MODIFIED . ' ' . tep_date_short($cInfo->last_modified));
		if ( (tep_not_null($cInfo->sort_order)) && ($cInfo->sort_order >= 0) )
		  $contents[] = array('text' => '<br>&nbsp;' . TEXT_INFO_SORT_ORDER . '&nbsp;&nbsp; ' . $cInfo->sort_order . '<br><br>');
		else
		  $contents[] = array('text' => '<br>&nbsp;' . TEXT_INFO_SORT_ORDER . '&nbsp;&nbsp; ' . 'Not Set' . '<br><br>');
          		// BEGIN add/edit configuration field values
				$cidtag = (isset($_GET['cID']) ? '&cID=' . $_GET['cID'] : '');
		if ($status) {
		   $contents[] = array('text' => '&nbsp;<font color="#FF0000"><b>' . TEXT_INFO_HEADING_HIDDEN_FIELDS . '</b></font>');

		   $contents[] = array('align' => 'center', 'text' => '<br><a href="' . tep_href_link(FILENAME_TOTAL_CONFIGURATION, 'gID=' . (int)$_GET['gID'] . '&action=new') . '">' . tep_image_button('button_nuevo_valor.png', IMAGE_INSERT) . '</a> <a href="' . tep_href_link(FILENAME_TOTAL_CONFIGURATION, 'gID=' . (int)$_GET['gID'] . '&cID=' . $cInfo->configuration_id . '&action=edit_value') . '">' . tep_image_button('button_editar_datos.png', IMAGE_EDIT) . '</a><br><a href="' . tep_href_link(FILENAME_TOTAL_CONFIGURATION, 'gID=' . (int)$_GET['gID'] . '&cID=' . $cInfo->configuration_id . '&action=edit_group') . '">' . tep_image_button('button_editar_todo.png', IMAGE_EDIT_GROUP) . '</a> <a href="' . tep_href_link(FILENAME_TOTAL_CONFIGURATION, 'gID=' . (int)$_GET['gID'] . '&cID=' . $cInfo->configuration_id . '&action=delete') . '">' . tep_image_button('button_delete.png', IMAGE_DELETE) . '</a><br><br><a href="' . tep_href_link(FILENAME_TOTAL_CONFIGURATION, 'gID=' . (int)$_GET['gID'] . $cidtag  . '&showhide_configuration=0') . '">' . tep_image_button('button_ocultar.png', 'Ocultar el valor en la configuración') . '</a><br>');
		   
		   $contents[] = array('text' => '<br>&nbsp;' . TEXT_CONFIG_TITLE . '<br>&nbsp;' . $cInfo->configuration_title);
		   $contents[] = array('text' => '<br>&nbsp;' . TEXT_CONFIGURATION_KEY . '<br>&nbsp;' . $cInfo->configuration_key);
		   $contents[] = array('text' => '<br>&nbsp;' . TEXT_CONFIG_DESCRIPTION . '<br>&nbsp;' . $cInfo->configuration_description);
		   $contents[] = array('text' => '<br>&nbsp;' . TEXT_CONFIG_VALUE . '<br>&nbsp;' . $cInfo->configuration_value);
		   $contents[] = array('text' => '<br>&nbsp;' . TEXT_CONFIG_SORT_ORDER . '&nbsp;' . $cInfo->sort_order);
		   $contents[] = array('text' => '<br>&nbsp;' . TEXT_CONFIG_USE_FUNCTION . '<br>&nbsp;' . (tep_not_null($cInfo->use_function) ? $cInfo->use_function : TEXT_NONE));
		   $contents[] = array('text' => '<br>&nbsp;' . TEXT_CONFIG_SET_FUNCTION . '<br>&nbsp;' . (tep_not_null($cInfo->set_function) ? $cInfo->set_function : TEXT_NONE));
		   $contents[] = array('text' => '<br>');	
		   
		} else {

		   $contents[] = array('text' => '&nbsp;<font color="#FF0000"><b>' . TEXT_INFO_HEADING_HIDDEN_FIELDS . '</b></font>');
$contents[] = array('align' => 'center', 'text' => '<br><a href="' . tep_href_link(FILENAME_TOTAL_CONFIGURATION, 'gID=' . (int)$_GET['gID']. $cidtag  . '&showhide_configuration=1') . '">' . tep_image_button('button_unlock.png', IMAGE_UNLOCK) . '</a><br><br>');
		}
		// END add/edit configuration field values
        }
        break;
    }

    if ( (tep_not_null($heading)) && (tep_not_null($contents)) ) {
      echo '            <td width="25%" valign="top">' . "\n";

      $box = new box;
      echo $box->infoBox($heading, $contents);

      echo '            </td>' . "\n";
    }
?>
          </tr>
        </table></td>
      </tr>
<?php
  }
?>
    </table></td>
<!-- body_text_eof //-->
  </tr>
</table>
<!-- body_eof //-->

<?php include( THEME . '/html/footer.php' ); ?>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>