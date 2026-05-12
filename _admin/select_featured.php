<?php
/*
  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2002 osCommerce

  Released under the GNU General Public License

  Featured Products admin
*/

  require('includes/application_top.php');

  require(DIR_WS_CLASSES . 'currencies.php');
  $currencies = new currencies();

?>

<?php require(THEME . 'html/header.php'); ?>
<!-- header_eof //-->
<table border="0" width="100%" cellspacing="0" cellpadding="2">
      <tr>
        <td width="100%"><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo HEADING_TITLE; ?></td>
          </tr>
        </table></td>
      </tr>

      <tr>
        <td width="100%"><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="main"><br/>

            <?php echo tep_draw_form('search_featured', FILENAME_SELECT_FEATURED, '', 'post'); ?>
              <table border='0'>
                <tr>
                  <td class='main'><?php echo TEXT_FEATURED_PRODUCT; ?></td>
                  <td class='main'><?php echo tep_draw_input_field('products_name') ?></td>
                </tr>

                <tr>
                  <td class='main'><?php echo TEXT_FEATURED_MODEL; ?></td>
                  <td class='main'><?php echo tep_draw_input_field('products_model') ?></td>
                </tr>

                <tr>
                  <td class='main' colspan='2' align='right'><input type='submit' value='<?php echo SUBMIT_SEARCH; ?>'/></td>
                </tr>
              </table>
              </form>
			  <table border="0" width="100%" cellspacing="0" cellpadding="2">
			    <tr class="dataTableHeadingRow">
                  <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_PRODUCTS; ?></td>
                  <td class="dataTableHeadingContent" align="left"><?php echo TABLE_HEADING_MODEL; ?></td>
                  <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_PRODUCTS_PRICE; ?></td>
                  <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_STATUS; ?></td>
                </tr>

<?php
	$name = $_POST['products_name'];
	$model = $_POST['products_model'];

	if (tep_not_null($name) || tep_not_null($model)) {

		$sql = "select d.products_id, d.products_name, p.products_model, p.products_price, p.products_status from ".TABLE_PRODUCTS_DESCRIPTION." d, ".TABLE_PRODUCTS." p where d.language_id = '" . (int)$languages_id . "' and d.products_id = p.products_id";
		if (tep_not_null($name))  $sql .= " and d.products_name like '%" . tep_db_prepare_input($name) . "%'";
		if (tep_not_null($model))  $sql .= " and p.products_model like '%" . tep_db_prepare_input($model) . "%'";

		$search_query = tep_db_query($sql);
		while ($result = tep_db_fetch_array($search_query)) {
			if ($result['products_status'] == '1')
				$icon = tep_image(DIR_WS_IMAGES . 'icon_status_green.gif', '', 10, 10) . '&nbsp;&nbsp;' . tep_image(DIR_WS_IMAGES . 'icon_status_red_light.gif', '', 10, 10);
			else
				$icon = tep_image(DIR_WS_IMAGES . 'icon_status_green_light.gif', '', 10, 10) . '&nbsp;&nbsp;' . tep_image(DIR_WS_IMAGES . 'icon_status_red.gif', IMAGE_ICON_STATUS_RED, 10, 10);
?>
                <tr class="dataTableRow" onMouseOver="rowOverEffect(this)" onMouseOut="rowOutEffect(this)" onClick="document.location.href='<?php echo tep_href_link(FILENAME_FEATURED, '&prodID=' . $result['products_id'] . '&action=new'); ?>'">
                  <td  class="dataTableContent">
                    <a href="<?php echo tep_href_link(FILENAME_FEATURED, '&prodID=' . $result['products_id'] . '&action=new'); ?>"><?php echo $result['products_name']; ?></a>
                  </td>
                  <td  class="dataTableContent" align="left"><?php echo $result['products_model']; ?></td>
                  <td  class="dataTableContent" align="right"><?php echo $currencies->format($result['products_price']); ?></td>
                  <td  class="dataTableContent" align="right"><?php echo $icon; ?></td>
                </tr>
<?php
    	}
	}
?>

              </table>


            </td>
          </tr>
        </table></td>
      </tr>
      </table>

    </td>
<!-- body_text_eof //-->
  </tr>
</table>
<!-- body_eof //-->

<?php require(THEME . 'html/footer.php'); ?>

<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>