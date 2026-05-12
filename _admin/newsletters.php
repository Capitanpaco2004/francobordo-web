<?php
/*
  $Id: newsletters.php 1751 2007-12-21 05:26:09Z hpdl $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2007 osCommerce

  Released under the GNU General Public License
*/

  require('includes/application_top.php');
// BOF Separate Pricing Per Customer
  $customers_group_query = tep_db_query("select customers_group_id, customers_group_name from " . TABLE_CUSTOMERS_GROUPS . " order by customers_group_id");
  $cg_array = array();
  while ($customers_group = tep_db_fetch_array($customers_group_query)) {
    $cg_array[] = array('id' => $customers_group['customers_group_id'], 'customers_group_name' => $customers_group['customers_group_name']);
  }
 // this is now defined as a global variable since we need it everywhere
 global $cg_array;
// EOF Separate Pricing Per Customer

  $action = (isset($_GET['action']) ? $_GET['action'] : '');

  if (tep_not_null($action)) {
      switch ($action) {
    // BOF Separate Pricing Per Customer
    case 'update_cgs':
    $customer_groups_chosen = '';
    if (isset($_POST['send_to_cgs'])) {
	    foreach ($_POST['send_to_cgs'] as $key => $value) {
		    $customer_groups_chosen .= tep_db_prepare_input($value).',';
	    }
	    // remove last comma
	    $customer_groups_chosen = substr($customer_groups_chosen,0,strlen($customer_groups_chosen)-1);
    } // end if (isset($HTTP_POST['send_to_cgs']))
	    $newsletter_id = tep_db_prepare_input($_GET['nID']);

	    tep_db_query("update " . TABLE_NEWSLETTERS . " set send_to_customer_groups = '" . $customer_groups_chosen . "' where newsletters_id = '" . (int)$newsletter_id . "'");

        tep_redirect(tep_href_link(FILENAME_NEWSLETTERS, 'page=' . $_GET['page'] . '&nID=' . $_GET['nID']));
        break;
    // EOF Separate Pricing Per Customer
      case 'preview':
	  	if (EMAIL_USE_HTML == 'true') {
			  $newsletter_query = tep_db_query("select content from " . TABLE_NEWSLETTERS . " where newsletters_id = '" . (int)$_GET['nID']. "'");
			  $newsletter = tep_db_fetch_array($newsletter_query);
			  $HTMLNewsletterContents = $newsletter['content'];
			  require(DIR_FS_CATALOG_MODULES . 'UHtmlEmails/'. ULTIMATE_HTML_EMAIL_LAYOUT .'/newsletters.php');
			  print $html_email;
			  exit();
		}
	    break;
//---  End of addition: Ultimate HTML Emails  ---//
      case 'lock':
      case 'unlock':
        $newsletter_id = tep_db_prepare_input($_GET['nID']);
        $status = (($action == 'lock') ? '1' : '0');

        tep_db_query("update " . TABLE_NEWSLETTERS . " set locked = '" . $status . "' where newsletters_id = '" . (int)$newsletter_id . "'");

        tep_redirect(tep_href_link(FILENAME_NEWSLETTERS, 'page=' . $_GET['page'] . '&nID=' . $_GET['nID']));
        break;
      case 'insert':
      case 'update':
        if (isset($_POST['newsletter_id'])) $newsletter_id = tep_db_prepare_input($_POST['newsletter_id']);
        $newsletter_module = tep_db_prepare_input($_POST['module']);
        $title = tep_db_prepare_input($_POST['title']);
        $content = tep_db_prepare_input($_POST['content']);

        $newsletter_error = false;
        if (empty($title)) {
          $messageStack->add(ERROR_NEWSLETTER_TITLE, 'error');
          $newsletter_error = true;
        }

        if (empty($newsletter_module)) {
          $messageStack->add(ERROR_NEWSLETTER_MODULE, 'error');
          $newsletter_error = true;
        }

        if ($newsletter_error == false) {
          $sql_data_array = array('title' => $title,
                                  'content' => $content,
                                  'module' => $newsletter_module);

          if ($action == 'insert') {
            $sql_data_array['date_added'] = 'now()';
            $sql_data_array['status'] = '0';
            $sql_data_array['locked'] = '0';

			// Por defecto al cliente final
			$sql_data_array['send_to_customer_groups'] = '0';

            tep_db_perform(TABLE_NEWSLETTERS, $sql_data_array);
            $newsletter_id = tep_db_insert_id();
          } elseif ($action == 'update') {
            tep_db_perform(TABLE_NEWSLETTERS, $sql_data_array, 'update', "newsletters_id = '" . (int)$newsletter_id . "'");
          }

          tep_redirect(tep_href_link(FILENAME_NEWSLETTERS, (isset($_GET['page']) ? 'page=' . $_GET['page'] . '&' : '') . 'nID=' . $newsletter_id));
        } else {
          $action = 'new';
        }
        break;
      case 'deleteconfirm':
        $newsletter_id = tep_db_prepare_input($_GET['nID']);

        tep_db_query("delete from " . TABLE_NEWSLETTERS . " where newsletters_id = '" . (int)$newsletter_id . "'");

        tep_redirect(tep_href_link(FILENAME_NEWSLETTERS, 'page=' . $_GET['page']));
        break;
      case 'delete':
      case 'new': if (!isset($_GET['nID'])) break;
      case 'send':
      case 'confirm_send':
        $newsletter_id = tep_db_prepare_input($_GET['nID']);

        $check_query = tep_db_query("select locked from " . TABLE_NEWSLETTERS . " where newsletters_id = '" . (int)$newsletter_id . "'");
        $check = tep_db_fetch_array($check_query);


        if ($check['locked'] < 1) {
          switch ($action) {
            case 'delete': $error = ERROR_REMOVE_UNLOCKED_NEWSLETTER; break;
            case 'new': $error = ERROR_EDIT_UNLOCKED_NEWSLETTER; break;
            case 'send': $error = ERROR_SEND_UNLOCKED_NEWSLETTER; break;
            case 'confirm_send': $error = ERROR_SEND_UNLOCKED_NEWSLETTER; break;
          }

          $messageStack->add_session($error, 'error');

          tep_redirect(tep_href_link(FILENAME_NEWSLETTERS, 'page=' . $_GET['page'] . '&nID=' . $_GET['nID']));
        }
        break;
    }
  }
?>

<?php require(THEME . 'html/header.php'); ?>

<link href="<?php echo (($request_type == 'SSL') ? HTTPS_SERVER : HTTP_SERVER) . DIR_WS_ADMIN; ?>ckfinder/sample.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="<?php echo (($request_type == 'SSL') ? HTTPS_SERVER : HTTP_SERVER) . DIR_WS_ADMIN; ?>ckeditor/ckeditor.js"></script>
<script type="text/javascript" src="<?php echo (($request_type == 'SSL') ? HTTPS_SERVER : HTTP_SERVER) . DIR_WS_ADMIN; ?>ckfinder/ckfinder.js"></script>
<script language="javascript" src="includes/general.js"></script>


<div id="spiffycalendar" class="text"></div>

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
<?php
  if ($action == 'new') {
    $form_action = 'insert';

    $parameters = array('title' => '',
                        'content' => '',
			// BOF Separate Pricing Per Customer
                        'module' => '',
			'send_to_customer_groups' => '');
			// EOF Separate Pricing Per Customer

    $nInfo = new objectInfo($parameters);

    if (isset($_GET['nID'])) {
      $form_action = 'update';

      $nID = tep_db_prepare_input($_GET['nID']);

// BOF Separate Pricing Per Customer
      $newsletter_query = tep_db_query("select title, content, module, send_to_customer_groups from " . TABLE_NEWSLETTERS . " where newsletters_id = '" . (int)$nID . "'");
// EOF Separate Pricing Per Customer
      $newsletter = tep_db_fetch_array($newsletter_query);

      $nInfo->addProperties($newsletter);
    } elseif ($_POST) {
      $nInfo->addProperties($_POST);
    }

    $file_extension = substr($PHP_SELF, strrpos($PHP_SELF, '.'));
    $directory_array = array();
    if ($dir = dir(DIR_WS_MODULES . 'newsletters/')) {
      while ($file = $dir->read()) {
        if (!is_dir(DIR_WS_MODULES . 'newsletters/' . $file)) {
          if (substr($file, strrpos($file, '.')) == $file_extension) {
            $directory_array[] = $file;
          }
        }
      }
      sort($directory_array);
      $dir->close();
    }

    for ($i=0, $n=sizeof($directory_array); $i<$n; $i++) {
      $modules_array[] = array('id' => substr($directory_array[$i], 0, strrpos($directory_array[$i], '.')), 'text' => substr($directory_array[$i], 0, strrpos($directory_array[$i], '.')));
    }
?>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.png', '1', '10'); ?></td>
      </tr>
      <tr>
        <td>
		<?php echo tep_draw_form('newsletter', FILENAME_NEWSLETTERS, (isset($_GET['page']) ? 'page=' . $_GET['page'] . '&' : '') . 'action=' . $form_action); if ($form_action == 'update') echo tep_draw_hidden_field('newsletter_id', $nID); ?>
		<table border="0" cellspacing="0" cellpadding="2">
          <tr>
            <td class="main"><?php echo TEXT_NEWSLETTER_MODULE; ?></td>
            <td class="main"><?php echo tep_draw_pull_down_menu('module', $modules_array, $nInfo->module); ?></td>
          </tr>
          <tr>
            <td colspan="2"><?php echo tep_draw_separator('pixel_trans.png', '1', '10'); ?></td>
          </tr>
          <tr>
            <td class="main"><?php echo TEXT_NEWSLETTER_TITLE; ?></td>
            <td class="main"><?php echo tep_draw_input_field('title', $nInfo->title, '', true); ?></td>
          </tr>
          <tr>
            <td colspan="2"><?php echo tep_draw_separator('pixel_trans.png', '1', '10'); ?></td>
          </tr>
          <tr>
            <td class="main" valign="top"><?php echo TEXT_NEWSLETTER_CONTENT; ?></td>
            <td class="main"><?php echo tep_draw_textarea_field_tinymce('content', 'soft', '70', '15', $nInfo->content); ?></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.png', '1', '10'); ?></td>
      </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
          <tr>
            <td class="main" align="right"><?php echo (($form_action == 'insert') ? tep_image_submit('button_save.png', IMAGE_SAVE) : tep_image_submit('button_update.png', IMAGE_UPDATE)). '&nbsp;&nbsp;<a href="' . tep_href_link(FILENAME_NEWSLETTERS, (isset($_GET['page']) ? 'page=' . $_GET['page'] . '&' : '') . (isset($_GET['nID']) ? 'nID=' . $_GET['nID'] : '')) . '">' . tep_image_button('button_cancel.png', IMAGE_CANCEL) . '</a>'; ?></td>
          </tr>
        </table></form></td>
      </tr>
<?php
  } elseif ($action == 'preview') {
    $nID = tep_db_prepare_input($_GET['nID']);

// BOF Separate Pricing Per Customer
    $newsletter_query = tep_db_query("select title, content, module, send_to_customer_groups from " . TABLE_NEWSLETTERS . " where newsletters_id = '" . (int)$nID . "'");
// EOF Separate Pricing Per Customer
    $newsletter = tep_db_fetch_array($newsletter_query);

    $nInfo = new objectInfo($newsletter);
?>
      <tr>
        <td align="right"><?php echo '<a href="' . tep_href_link(FILENAME_NEWSLETTERS, 'page=' . $_GET['page'] . '&nID=' . $_GET['nID']) . '">' . tep_image_button('button_back.png', IMAGE_BACK) . '</a>'; ?></td>
      </tr>
      <tr>
        <td><tt><?php echo nl2br($nInfo->content); ?></tt></td>
      </tr>
      <tr>
        <td align="right"><?php echo '<a href="' . tep_href_link(FILENAME_NEWSLETTERS, 'page=' . $_GET['page'] . '&nID=' . $_GET['nID']) . '">' . tep_image_button('button_back.png', IMAGE_BACK) . '</a>'; ?></td>
      </tr>
<?php
  } elseif ($action == 'send') {
    $nID = tep_db_prepare_input($_GET['nID']);

    $newsletter_query = tep_db_query("select title, content, module, send_to_customer_groups from " . TABLE_NEWSLETTERS . " where newsletters_id = '" . (int)$nID . "'");
    $newsletter = tep_db_fetch_array($newsletter_query);

    $nInfo = new objectInfo($newsletter);

	if( $nInfo->send_to_customer_groups == '' )
		$nInfo->send_to_customer_groups = 0;

    include(DIR_WS_LANGUAGES . $language . '/modules/newsletters/' . $nInfo->module . substr($PHP_SELF, strrpos($PHP_SELF, '.')));
    include(DIR_WS_MODULES . 'newsletters/' . $nInfo->module . substr($PHP_SELF, strrpos($PHP_SELF, '.')));
    $module_name = $nInfo->module;

// BOF Separate Pricing Per Customer
    $module = new $module_name($nInfo->title, $nInfo->content, $nInfo->send_to_customer_groups);
// EOF Separate Pricing Per Customer
?>
      <tr>
        <td><?php if ($module->show_choose_audience) { echo $module->choose_audience(); } else { echo $module->confirm(); } ?></td>
      </tr>
<?php
  } elseif ($action == 'confirm') {
    $nID = tep_db_prepare_input($_GET['nID']);

// BOF Separate Pricing Per Customer
    $newsletter_query = tep_db_query("select title, content, module, send_to_customer_groups from " . TABLE_NEWSLETTERS . " where newsletters_id = '" . (int)$nID . "'");
// EOF Separate Pricing Per Customer
    $newsletter = tep_db_fetch_array($newsletter_query);

    $nInfo = new objectInfo($newsletter);

	if( $nInfo->send_to_customer_groups == '' )
		$nInfo->send_to_customer_groups = 0;

    include(DIR_WS_LANGUAGES . $language . '/modules/newsletters/' . $nInfo->module . substr($PHP_SELF, strrpos($PHP_SELF, '.')));
    include(DIR_WS_MODULES . 'newsletters/' . $nInfo->module . substr($PHP_SELF, strrpos($PHP_SELF, '.')));
    $module_name = $nInfo->module;
    // BOF Separate Pricing Per Customer
    $module = new $module_name($nInfo->title, $nInfo->content, $nInfo->send_to_customer_groups);
    // EOF Separate Pricing Per Customer
?>
      <tr>
        <td><?php echo $module->confirm(); ?></td>
      </tr>
<?php
  } elseif ($action == 'confirm_send') {
    $nID = tep_db_prepare_input($_GET['nID']);

// BOF Separate Pricing Per Customer
    $newsletter_query = tep_db_query("select newsletters_id, title, content, module, send_to_customer_groups from " . TABLE_NEWSLETTERS . " where newsletters_id = '" . (int)$nID . "'");
// EOF Separate Pricing Per Customer
    $newsletter = tep_db_fetch_array($newsletter_query);
//---  Beginning of addition: Ultimate HTML Emails  ---//
	if (EMAIL_USE_HTML == 'true') {
		$HTMLNewsletterContents = $newsletter['content'];
		include( DIR_FS_CATALOG_LANGUAGES . $language . '.php' );
		require(DIR_FS_CATALOG_MODULES . 'UHtmlEmails/'. ULTIMATE_HTML_EMAIL_LAYOUT .'/newsletters.php');
		$newsletter['content'] = $html_email;

		if(ULTIMATE_HTML_EMAIL_DEVELOPMENT_MODE === 'true'){
			//Save the contents of the generated html email to the harddrive in .htm file. This can be practical when developing a new layout.
			$TheFileName = DIR_FS_CATALOG . 'Last_mail_from_newsletters.php.htm';
			$TheFileHandle = fopen($TheFileName, 'w') or die("can't open error log file");
			fwrite($TheFileHandle, $newsletter['content']);
			fclose($TheFileHandle);
		}

	}
//---  End of addition: Ultimate HTML Emails  ---//

    $nInfo = new objectInfo($newsletter);

	if( $nInfo->send_to_customer_groups == '' )
		$nInfo->send_to_customer_groups = 0;

    include(DIR_WS_LANGUAGES . $language . '/modules/newsletters/' . $nInfo->module . substr($PHP_SELF, strrpos($PHP_SELF, '.')));
    include(DIR_WS_MODULES . 'newsletters/' . $nInfo->module . substr($PHP_SELF, strrpos($PHP_SELF, '.')));
    $module_name = $nInfo->module;
    // BOF Separate Pricing Per Customer
    $module = new $module_name($nInfo->title, $nInfo->content, $nInfo->send_to_customer_groups);
    // EOF Separate Pricing Per Customer
?>
      <tr>
        <td><table border="0" cellspacing="0" cellpadding="2">
          <tr>
            <td class="main" valign="middle"><?php echo tep_image(DIR_WS_IMAGES . 'ani_send_email.png', IMAGE_ANI_SEND_EMAIL); ?></td>
            <td class="main" valign="middle"><b><?php echo TEXT_PLEASE_WAIT; ?></b></td>
          </tr>
        </table></td>
      </tr>
<?php
  tep_set_time_limit(0);
  flush();
    // BOF Separate Pricing Per Customer
  $module->send($nInfo->newsletters_id, $nInfo->send_to_customer_groups);
    // EOF Separate Pricing Per Customer
?>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.png', '1', '10'); ?></td>
      </tr>
      <tr>
        <td class="main"><font color="#ff0000"><b><?php echo TEXT_FINISHED_SENDING_EMAILS; ?></b></font></td>
      </tr>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.png', '1', '10'); ?></td>
      </tr>
      <tr>
        <td><?php echo '<a href="' . tep_href_link(FILENAME_NEWSLETTERS, 'page=' . $_GET['page'] . '&nID=' . $_GET['nID']) . '">' . tep_image_button('button_back.png', IMAGE_BACK) . '</a>'; ?></td>
      </tr>
<?php
  } else {
?>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr class="dataTableHeadingRow">
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_NEWSLETTERS; ?></td>
                <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_SIZE; ?></td>
                <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_MODULE; ?></td>
                <td class="dataTableHeadingContent" align="center"><?php echo TABLE_HEADING_SENT; ?></td>
                <td class="dataTableHeadingContent" align="center"><?php echo TABLE_HEADING_STATUS; ?></td>
                <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_ACTION; ?>&nbsp;</td>
              </tr>
<?php
// BOF Separate Pricing Per Customer
    $newsletters_query_raw = "select newsletters_id, title, length(content) as content_length, module, date_added, date_sent, status, locked, send_to_customer_groups from " . TABLE_NEWSLETTERS . " order by date_added desc";
// EOF Separate Pricing Per Customer
    $newsletters_split = new splitPageResults($_GET['page'], MAX_DISPLAY_SEARCH_RESULTS, $newsletters_query_raw, $newsletters_query_numrows);
    $newsletters_query = tep_db_query($newsletters_query_raw);
    while ($newsletters = tep_db_fetch_array($newsletters_query)) {
    if ((!isset($_GET['nID']) || (isset($_GET['nID']) && ($_GET['nID'] == $newsletters['newsletters_id']))) && !isset($nInfo) && (substr($action, 0, 3) != 'new')) {
        $nInfo = new objectInfo($newsletters);
      }

      if (isset($nInfo) && is_object($nInfo) && ($newsletters['newsletters_id'] == $nInfo->newsletters_id) ) {
        echo '                  <tr id="defaultSelected" class="dataTableRowSelected" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href=\'' . tep_href_link(FILENAME_NEWSLETTERS, 'page=' . $_GET['page'] . '&nID=' . $nInfo->newsletters_id . '&action=preview') . '\'">' . "\n";
      } else {
        echo '                  <tr class="dataTableRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href=\'' . tep_href_link(FILENAME_NEWSLETTERS, 'page=' . $_GET['page'] . '&nID=' . $newsletters['newsletters_id']) . '\'">' . "\n";
      }
?>
                <td class="dataTableContent"><?php echo '<a href="' . tep_href_link(FILENAME_NEWSLETTERS, 'page=' . $_GET['page'] . '&nID=' . $newsletters['newsletters_id'] . '&action=preview') . '">' . tep_image(DIR_WS_ICONS . 'preview.png', ICON_PREVIEW) . '</a>&nbsp;' . $newsletters['title']; ?></td>
                <td class="dataTableContent" align="right"><?php echo number_format($newsletters['content_length']) . ' bytes'; ?></td>
                <td class="dataTableContent" align="right"><?php echo $newsletters['module']; ?></td>
                <td class="dataTableContent" align="center"><?php if ($newsletters['status'] == '1') { echo tep_image(DIR_WS_ICONS . 'tick.png', ICON_TICK); } else { echo tep_image(DIR_WS_ICONS . 'cross.png', ICON_CROSS); } ?></td>
                <td class="dataTableContent" align="center"><?php if ($newsletters['locked'] > 0) { echo tep_image(DIR_WS_ICONS . 'locked.png', ICON_LOCKED); } else { echo tep_image(DIR_WS_ICONS . 'unlocked.png', ICON_UNLOCKED); } ?></td>
                <td class="dataTableContent" align="right"><?php if (isset($nInfo) && is_object($nInfo) && ($newsletters['newsletters_id'] == $nInfo->newsletters_id) ) { echo tep_image(DIR_WS_IMAGES . 'icon_arrow_right.png', ''); } else { echo '<a href="' . tep_href_link(FILENAME_NEWSLETTERS, 'page=' . $_GET['page'] . '&nID=' . $newsletters['newsletters_id']) . '">' . tep_image(DIR_WS_IMAGES . 'icon_info.png', IMAGE_ICON_INFO) . '</a>'; } ?>&nbsp;</td>
              </tr>
<?php
    }
?>
              <tr>
                <td colspan="6"><table border="0" width="100%" cellspacing="0" cellpadding="2">
                  <tr>
                    <td class="smallText" valign="top"><?php echo $newsletters_split->display_count($newsletters_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, $_GET['page'], TEXT_DISPLAY_NUMBER_OF_NEWSLETTERS); ?></td>
                    <td class="smallText" align="right"><?php echo $newsletters_split->display_links($newsletters_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, MAX_DISPLAY_PAGE_LINKS, $_GET['page']); ?></td>
                  </tr>
                  <tr>
                    <td align="right" colspan="2"><?php echo '<a href="' . tep_href_link(FILENAME_NEWSLETTERS, 'action=new') . '">' . tep_image_button('button_new_newsletter.png', IMAGE_NEW_NEWSLETTER) . '</a>'; ?></td>
                  </tr>
                </table></td>
              </tr>
            </table></td>
<?php
  $heading = array();
  $contents = array();

  switch ($action) {
    case 'delete':
      $heading[] = array('text' => '<b>' . $nInfo->title . '</b>');

      $contents = array('form' => tep_draw_form('newsletters', FILENAME_NEWSLETTERS, 'page=' . $_GET['page'] . '&nID=' . $nInfo->newsletters_id . '&action=deleteconfirm'));
      $contents[] = array('text' => TEXT_INFO_DELETE_INTRO);
      $contents[] = array('text' => '<br><b>' . $nInfo->title . '</b>');
      $contents[] = array('align' => 'center', 'text' => '<br>' . tep_image_submit('button_delete.png', IMAGE_DELETE) . ' <a href="' . tep_href_link(FILENAME_NEWSLETTERS, 'page=' . $_GET['page'] . '&nID=' . $_GET['nID']) . '">' . tep_image_button('button_cancel.png', IMAGE_CANCEL) . '</a>');
      break;
    default:
      if (is_object($nInfo)) {
        $heading[] = array('text' => '<b>' . $nInfo->title . '</b>');
// BOF Separate Pricing Per Customer
	if (tep_not_null($cg_array)) {
		$cg_array_in_db = explode(',', $nInfo->send_to_customer_groups);
		$table_with_check_boxes_cgs = "
		<table border='0' cellspacing='0' cellpadding='2' style='border: 1px solid #c9c9c9; margin-top='10px;'>
		 <tr class='dataTableHeadingRow'>
		 <td class='dataTableHeadingContent'>" . TABLE_HEADING_CUSTOMERS_GROUPS . "</td>
		 <td class='dataTableHeadingContent'>&#160;</td>
		 </tr>";
 $c = '0'; // variable used for background coloring of rows
   for ($z = 0; $z < sizeof($cg_array); $z++) {
	  $bgcolor = ($c++ & 1) ? " class='dataTableRow'" : "";
$table_with_check_boxes_cgs .= "
		 <tr" . $bgcolor . ">
		 <td class='dataTableContent'>" . $cg_array[$z]['customers_group_name'] . "&#160;</td>";

		 $bCheck = false;

		 if( $nInfo->send_to_customer_groups == '' && $z == 0 )
			$bCheck = true;
		 else
			$bCheck = (in_array ($cg_array[$z]['id'], $cg_array_in_db)) ?  1 : 0;

		 $table_with_check_boxes_cgs .= "<td class='dataTableContent' align='right'>" . tep_draw_checkbox_field('send_to_cgs[' . $z . ']', $cg_array[$z]['id'] , (in_array ($cg_array[$z]['id'], $cg_array_in_db)) ?  1 : 0) . "</td>
		 </tr>
		 ";
   } // end for ($z = 0; $z < sizeof($cg_array); $z++)
$table_with_check_boxes_cgs .= "		 </table>";
}
  // EOF Separate Pricing Per Customer

        if ($nInfo->locked > 0) {
          $contents[] = array('align' => 'center', 'text' => '<a href="' . tep_href_link(FILENAME_NEWSLETTERS, 'page=' . $_GET['page'] . '&nID=' . $nInfo->newsletters_id . '&action=new') . '">' . tep_image_button('button_edit.png', IMAGE_EDIT) . '</a> <a href="' . tep_href_link(FILENAME_NEWSLETTERS, 'page=' . $_GET['page'] . '&nID=' . $nInfo->newsletters_id . '&action=delete') . '">' . tep_image_button('button_delete.png', IMAGE_DELETE) . '</a> <a href="' . tep_href_link(FILENAME_NEWSLETTERS, 'page=' . $_GET['page'] . '&nID=' . $nInfo->newsletters_id . '&action=preview') . '">' . tep_image_button('button_preview.png', IMAGE_PREVIEW) . '</a> <a href="' . tep_href_link(FILENAME_NEWSLETTERS, 'page=' . $_GET['page'] . '&nID=' . $nInfo->newsletters_id . '&action=send') . '">' . tep_image_button('button_send.png', IMAGE_SEND) . '</a> <a href="' . tep_href_link(FILENAME_NEWSLETTERS, 'page=' . $_GET['page'] . '&nID=' . $nInfo->newsletters_id . '&action=unlock') . '">' . tep_image_button('button_unlock.png', IMAGE_UNLOCK) . '</a>');
        } else {
          $contents[] = array('align' => 'center', 'text' => '<a href="' . tep_href_link(FILENAME_NEWSLETTERS, 'page=' . $_GET['page'] . '&nID=' . $nInfo->newsletters_id . '&action=preview') . '">' . tep_image_button('button_preview.png', IMAGE_PREVIEW) . '</a> <a href="' . tep_href_link(FILENAME_NEWSLETTERS, 'page=' . $_GET['page'] . '&nID=' . $nInfo->newsletters_id . '&action=lock') . '">' . tep_image_button('button_lock.png', IMAGE_LOCK) . '</a>');
        }
        $contents[] = array('text' => '<br>' . TEXT_NEWSLETTER_DATE_ADDED . ' ' . tep_date_short($nInfo->date_added));
        if ($nInfo->status == '1') $contents[] = array('text' => TEXT_NEWSLETTER_DATE_SENT . ' ' . tep_date_short($nInfo->date_sent));
	// BOF Separate Pricing Per Customer
        $contents[] = array('text' => TEXT_SEND_TO_CUSTOMERS_GROUPS . '<div align="center" style="margin-bottom: 10px;">'. tep_draw_form('select_customer_groups', FILENAME_NEWSLETTERS, tep_get_all_get_params() . 'action=update_cgs', 'post') . $table_with_check_boxes_cgs . '<br clear="all">'. tep_image_submit('button_update.png', IMAGE_UPDATE) . '</form></div>');
	// EOF Separate Pricing Per Customer
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

<?php require(THEME . 'html/footer.php'); ?>

<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
