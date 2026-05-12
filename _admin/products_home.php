<?php
/*
  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2002 osCommerce

  Released under the GNU General Public License

  Featured Products admin
*/

  require('includes/application_top.php');

  function tep_set_featured_status($featured_id, $status) {
    if ($status == '1') {
      return tep_db_query("update " . TABLE_PRODUCTS_HOME . " set status = '1', expires_date = NULL, date_status_change = NULL where featured_id = '" . $featured_id . "'");
    } elseif ($status == '0') {
      return tep_db_query("update " . TABLE_PRODUCTS_HOME . " set status = '0', date_status_change = now() where featured_id = '" . $featured_id . "'");
    } else {
      return -1;
    }
  }

  require(DIR_WS_CLASSES . 'currencies.php');
  $currencies = new currencies();

  switch ($_GET['action']) {
    case 'setflag':
      tep_set_featured_status($_GET['id'], $_GET['flag']);
      tep_redirect(tep_href_link(FILENAME_PRODUCTS_HOME, '', 'NONSSL'));
      break;
    case 'insert':
		$expires_date = tep_db_prepare_input($_POST['expires_date']);
		if( $expires_date != '' )
		{
			$aAux = explode( '/', $expires_date );
			$expires_date = $aAux[2] . '-' . $aAux[1] . '-' . $aAux[0] . ' 00:00:00';
		}

      tep_db_query("insert into " . TABLE_PRODUCTS_HOME . " (products_id, featured_date_added, expires_date, status) values ('" . $_POST['products_id'] . "', now(), '" . $expires_date . "', '1')");
      tep_redirect(tep_href_link(FILENAME_PRODUCTS_HOME, 'page=' . $_GET['page']));
      break;
    case 'update':
		$expires_date = tep_db_prepare_input($_POST['expires_date']);
		if( $expires_date != '' )
		{
			$aAux = explode( '/', $expires_date );
			$expires_date = $aAux[2] . '-' . $aAux[1] . '-' . $aAux[0] . ' 00:00:00';
		}

      tep_db_query("update " . TABLE_PRODUCTS_HOME . " set featured_last_modified = now(), expires_date = '" . $expires_date . "' where featured_id = '" . $_POST['featured_id'] . "'");
      tep_redirect(tep_href_link(FILENAME_PRODUCTS_HOME, 'page=' . $_GET['page'] . '&sID=' . $featured_id));
      break;
    case 'deleteconfirm':
      $featured_id = tep_db_prepare_input($_GET['sID']);

      tep_db_query("delete from " . TABLE_PRODUCTS_HOME . " where featured_id = '" . tep_db_input($featured_id) . "'");

      tep_redirect(tep_href_link(FILENAME_PRODUCTS_HOME, 'page=' . $_GET['page']));
      break;
  }
?>


<?php require(THEME . 'html/header.php'); ?>


<?php
  if ( ($_GET['action'] == 'new') || ($_GET['action'] == 'edit') ) {
?>
	<link rel="stylesheet" type="text/css" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/themes/smoothness/jquery-ui.min.css">
	<script language="javascript" src="includes/general.js"></script>
<?php
  }
?>



<div id="popupcalendar" class="text"></div>

<!-- body //-->
<table border="0" width="100%" cellspacing="2" cellpadding="2">
  <tr>
<!-- body_text //-->
    <td width="100%" valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
      <tr>
        <td width="100%"><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo HEADING_TITLE; ?></td>
          </tr>
        </table></td>
      </tr>
<?php
  if ( ($_GET['action'] == 'new') || ($_GET['action'] == 'edit') ) {
    $form_action = 'insert';
    if ( ($_GET['action'] == 'edit') && ($_GET['sID']) ) {
	  $form_action = 'update';

      $product_query = tep_db_query("select p.products_id, pd.products_name, DATE_FORMAT( s.expires_date, '%d/%m/%Y') as expires_date from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd, " . TABLE_PRODUCTS_HOME . " s where p.products_id = pd.products_id and pd.language_id = '" . $languages_id . "' and p.products_id = s.products_id and s.featured_id = '" . $_GET['sID'] . "' order by pd.products_name");
      $product = tep_db_fetch_array($product_query);

      $sInfo = new objectInfo($product);
    } else {
      if ( ($_GET['action'] == 'new') && isset($_GET['prodID']) ) {
      	$product_query = tep_db_query("select p.products_id, pd.products_name from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd where p.products_id = pd.products_id and pd.language_id = '" . (int)$languages_id . "' and p.products_id = '". (int) $_GET['prodID']. "'");
      	$product = tep_db_fetch_array($product_query);
      	$sInfo = new objectInfo($product);
      }
      else {
      	$sInfo = new objectInfo(array());

		// create an array of featured products, which will be excluded from the pull down menu of products
		// (when creating a new featured product)
      	$featured_array = array();
      	$featured_query = tep_db_query("select p.products_id from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_HOME . " s where s.products_id = p.products_id");
      	while ($featured = tep_db_fetch_array($featured_query)) {
        	$featured_array[] = $featured['products_id'];
        }
      }
    }
?>
      <tr><form name="new_feature" <?php echo 'action="' . tep_href_link(FILENAME_PRODUCTS_HOME, tep_get_all_get_params(array('action', 'info', 'sID')) . 'action=' . $form_action, 'NONSSL') . '"'; ?> method="post"><?php if ($form_action == 'update') echo tep_draw_hidden_field('featured_id', $_GET['sID']); ?>
        <td><br><table border="0" cellspacing="0" cellpadding="2">
          <tr>
            <td class="main"><?php echo TEXT_FEATURED_PRODUCT; ?>&nbsp;</td>
	    <td class="main">
<?php
		if ( ($_GET['action'] == 'new') && isset($_GET['prodID']) ) 
		{
			echo tep_draw_hidden_field('products_id', $_GET['prodID']);
			echo $sInfo->products_name;
		}
        else
        	echo (isset($sInfo->products_name)) ? $sInfo->products_name : tep_draw_products_pull_down('products_id', 'style="font-size:10px"', $featured_array);

?>
            </td>
          </tr>
          <tr>
            <td class="main"><?php echo TEXT_FEATURED_EXPIRES_DATE; ?>&nbsp;</td>
            <td class="main"><?php echo tep_draw_input_field( 'expires_date', (isset($sInfo->expires_date) ? $sInfo->expires_date : ''), 'size="2" style="width: 95px !important" maxlength="2" class="dxdatepicker cal-TextBox"' );?> </td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
          <tr>
            <td class="main" align="right" valign="top"><br><?php echo (($form_action == 'insert') ? tep_image_submit('button_insert.png', IMAGE_INSERT) : tep_image_submit('button_update.png', IMAGE_UPDATE)). '&nbsp;&nbsp;&nbsp;<a href="' . tep_href_link(FILENAME_PRODUCTS_HOME, 'page=' . $_GET['page'] . '&sID=' . $_GET['sID']) . '">' . tep_image_button('button_cancel.png', IMAGE_CANCEL) . '</a>'; ?></td>
          </tr>
        </table></td>
      </form></tr>
<?php
  } else {
?>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr class="dataTableHeadingRow">
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_PRODUCTS; ?></td>
                <td class="dataTableHeadingContent" align="right">&nbsp;</td>
                <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_STATUS; ?></td>
                <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_ACTION; ?>&nbsp;</td>
              </tr>
<?php
    $featured_query_raw = "select p.products_id, pd.products_name, s.featured_id, s.featured_date_added, s.featured_last_modified, s.expires_date, s.date_status_change, s.status from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_HOME . " s, " . TABLE_PRODUCTS_DESCRIPTION . " pd where p.products_id = pd.products_id and pd.language_id = '" . $languages_id . "' and p.products_id = s.products_id order by pd.products_name";
    $featured_split = new splitPageResults($_GET['page'], MAX_DISPLAY_SEARCH_RESULTS, $featured_query_raw, $featured_query_numrows);
    $featured_query = tep_db_query($featured_query_raw);
    while ($featured = tep_db_fetch_array($featured_query)) {
      if ( ((!$_GET['sID']) || ($_GET['sID'] == $featured['featured_id'])) && (!$sInfo) ) {

        $products_query = tep_db_query("select products_image from " . TABLE_PRODUCTS . " where products_id = '" . $featured['products_id'] . "'");
        $products = tep_db_fetch_array($products_query);
        $sInfo_array = array_merge($featured, $products);
        $sInfo = new objectInfo($sInfo_array);
      }

      if ( (is_object($sInfo)) && ($featured['featured_id'] == $sInfo->featured_id) ) {
        echo '                  <tr class="dataTableRowSelected" onmouseover="this.style.cursor=\'hand\'" onclick="document.location.href=\'' . tep_href_link(FILENAME_PRODUCTS_HOME, 'page=' . $_GET['page'] . '&sID=' . $sInfo->featured_id . '&action=edit') . '\'">' . "\n";
      } else {
        echo '                  <tr class="dataTableRow" onmouseover="this.className=\'dataTableRowOver\';this.style.cursor=\'hand\'" onmouseout="this.className=\'dataTableRow\'" onclick="document.location.href=\'' . tep_href_link(FILENAME_PRODUCTS_HOME, 'page=' . $_GET['page'] . '&sID=' . $featured['featured_id']) . '\'">' . "\n";
      }
?>
                <td  class="dataTableContent"><?php echo $featured['products_name']; ?></td>
                <td  class="dataTableContent" align="right">&nbsp;</td>
                <td  class="dataTableContent" align="right">
<?php
      if ($featured['status'] == '1') {
        echo tep_image(DIR_WS_IMAGES . 'icon_status_green.png', IMAGE_ICON_STATUS_GREEN, 10, 10) . '&nbsp;&nbsp;<a href="' . tep_href_link(FILENAME_PRODUCTS_HOME, 'action=setflag&flag=0&id=' . $featured['featured_id'], 'NONSSL') . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_red_light.png', IMAGE_ICON_STATUS_RED_LIGHT, 10, 10) . '</a>';
      } else {
        echo '<a href="' . tep_href_link(FILENAME_PRODUCTS_HOME, 'action=setflag&flag=1&id=' . $featured['featured_id'], 'NONSSL') . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_green_light.png', IMAGE_ICON_STATUS_GREEN_LIGHT, 10, 10) . '</a>&nbsp;&nbsp;' . tep_image(DIR_WS_IMAGES . 'icon_status_red.png', IMAGE_ICON_STATUS_RED, 10, 10);
      }
?></td>
                <td class="dataTableContent" align="right"><?php if ( (is_object($sInfo)) && ($featured['featured_id'] == $sInfo->featured_id) ) { echo tep_image(DIR_WS_IMAGES . 'icon_arrow_right.png', ''); } else { echo '<a href="' . tep_href_link(FILENAME_PRODUCTS_HOME, 'page=' . $_GET['page'] . '&sID=' . $featured['featured_id']) . '">' . tep_image(DIR_WS_IMAGES . 'icon_info.png', IMAGE_ICON_INFO) . '</a>'; } ?>&nbsp;</td>
      </tr>
<?php
    }
?>
              <tr>
                <td colspan="4"><table class="table-page" border="0" width="100%" cellpadding="0"cellspacing="2">
                  <tr>
                    <td class="smallText" valign="top"><?php echo $featured_split->display_count($featured_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, $_GET['page'], TEXT_DISPLAY_NUMBER_OF_FEATURED); ?></td>
                    <td class="smallText" align="right"><?php echo $featured_split->display_links($featured_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, MAX_DISPLAY_PAGE_LINKS, $_GET['page']); ?></td>
                  </tr>
<?php
  if (!$_GET['action']) {

?>
                  <tr>
                    <td colspan="2" align="right">
                      <?php
                       // Uncomment this line and comment out following 2 to ONLY use the Search feature
                        //echo '<a href="' . tep_href_link(FILENAME_SELECT_PRODUCTS_HOME, 'page=' . $_GET['page'] . '&action=new') . '">' . tep_image_button('button_new_product.png', IMAGE_NEW_PRODUCT) . '</a>';
                        // Two buttons - search for products or default of using select box
                        echo '<a href="' . tep_href_link(FILENAME_SELECT_PRODUCTS_HOME, 'page=' . $_GET['page'] . '&action=new') . '">' . tep_image_button('button_search.png', IMAGE_NEW_PRODUCT) . '</a>';
                        echo '<a href="' . tep_href_link(FILENAME_PRODUCTS_HOME, 'page=' . $_GET['page'] . '&action=new') . '">' . tep_image_button('button_new_product.png', IMAGE_NEW_PRODUCT) . '</a>';
                      ?>
                    </td>
                  </tr>
<?php
  }
?>
                </table></td>
              </tr>
            </table></td>
<?php
  $heading = array();
  $contents = array();
  switch ($_GET['action']) {
    case 'delete':
      $heading[] = array('text' => '<b>' . TEXT_INFO_HEADING_DELETE_FEATURED . '</b>');

      $contents = array('form' => tep_draw_form('featured', FILENAME_PRODUCTS_HOME, 'page=' . $_GET['page'] . '&sID=' . $sInfo->featured_id . '&action=deleteconfirm'));
      $contents[] = array('text' => TEXT_INFO_DELETE_INTRO);
      $contents[] = array('text' => '<br><b>' . $sInfo->products_name . '</b>');
      $contents[] = array('align' => 'center', 'text' => '<br>' . tep_image_submit('button_delete.png', IMAGE_DELETE) . '&nbsp;<a href="' . tep_href_link(FILENAME_PRODUCTS_HOME, 'page=' . $_GET['page'] . '&sID=' . $sInfo->featured_id) . '">' . tep_image_button('button_cancel.png', IMAGE_CANCEL) . '</a>');
      break;
    default:
      if (is_object($sInfo)) {
        $heading[] = array('text' => '<b>' . $sInfo->products_name . '</b>');

        $contents[] = array('align' => 'center', 'text' => '<a href="' . tep_href_link(FILENAME_PRODUCTS_HOME, 'page=' . $_GET['page'] . '&sID=' . $sInfo->featured_id . '&action=edit') . '">' . tep_image_button('button_edit.png', IMAGE_EDIT) . '</a> <a href="' . tep_href_link(FILENAME_PRODUCTS_HOME, 'page=' . $_GET['page'] . '&sID=' . $sInfo->featured_id . '&action=delete') . '">' . tep_image_button('button_delete.png', IMAGE_DELETE) . '</a>');
        $contents[] = array('text' => '<br>' . TEXT_INFO_DATE_ADDED . ' ' . tep_date_short($sInfo->featured_date_added));
        $contents[] = array('text' => '' . TEXT_INFO_LAST_MODIFIED . ' ' . tep_date_short($sInfo->featured_last_modified));
        $contents[] = array('align' => 'center', 'text' => '<br>' . tep_info_image( 'productos/' . $sInfo->products_image, $sInfo->products_name, SMALL_IMAGE_WIDTH, SMALL_IMAGE_HEIGHT));

        $contents[] = array('text' => '<br>' . TEXT_INFO_EXPIRES_DATE . ' <b>' . tep_date_short($sInfo->expires_date) . '</b>');
        $contents[] = array('text' => '' . TEXT_INFO_STATUS_CHANGE . ' ' . tep_date_short($sInfo->date_status_change));
      }
      break;
  }
  if ( (tep_not_null($heading)) && (tep_not_null($contents)) ) {
    echo '            <td width="25%" valign="top">' . "\n";

    $box = new box;
    echo $box->infoBox($heading, $contents);

    echo '            </td>' . "\n";
  }
}
?>
          </tr>
        </table></td>
      </tr>
    </table></td>
<!-- body_text_eof //-->
  </tr>
</table>

<?php require(THEME . 'html/footer.php'); ?>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>