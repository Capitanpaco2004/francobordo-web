<?php
/*
  $Id: optional_related_products.php, ver 1.0 02/05/2007 Exp $

  Copyright (c) 2007 Anita Cross (http://www.callofthewildphoto.com/)

  Based on: products_options.php, ver 2.0 05/01/2005
  Copyright (c) 2004-2005 Daniel Bahna (daniel.bahna@gmail.com)

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Released under the GNU General Public License
*/

  require('includes/application_top.php');
  $languages = tep_get_languages();

  $version = tep_db_fetch_array(tep_db_query("select configuration_value as version, configuration_group_id as gID from " . TABLE_CONFIGURATION . " where configuration_key = 'RELATED_PRODUCTS_VERSION_INSTALLED'"));
  if ($version['version'] != TEXT_VERSION_CONTROL){
    tep_redirect(tep_href_link('sql_setup_related_products.php'));
  }
  $gID = $version['gID'];

  $action = (isset($_GET['action']) ? $_GET['action'] : '');

  $products_id_view = $_GET['products_id_view'];
  $products_id_master = $_GET['products_id_master'];
  if ($products_id_master) { $products_id_view = $products_id_master; }

  if (tep_not_null($action)) {
    $page_info = '';
    if (isset($_GET['attribute_page'])) $page_info .= 'attribute_page=' . $_GET['attribute_page'] . '&';
    if (tep_not_null($page_info)) {
      $page_info = substr($page_info, 0, -1);
    }

    switch ($action) {
      case 'Insertar':
        $products_id_master = tep_db_prepare_input($_REQUEST['products_id_master']);
        $products_id_slave = tep_db_prepare_input($_REQUEST['products_id_slave']);
        $pop_order_id = tep_db_prepare_input($_REQUEST['pop_order_id']);

        if ($products_id_master != $products_id_slave) {
          $check = tep_db_query("select p.pop_id from " . TABLE_PRODUCTS_RELATED_PRODUCTS . " p where p.pop_products_id_master=" . $products_id_master ." and p.pop_products_id_slave=" . $products_id_slave);
          if (!tep_db_fetch_array($check)) {
            tep_db_query("insert into " . TABLE_PRODUCTS_RELATED_PRODUCTS . " values ('', '" . (int)$products_id_master . "', '" . (int)$products_id_slave . "', '". (int)$pop_order_id."')");
          }
        }
        tep_redirect(tep_href_link(FILENAME_RELATED_PRODUCTS, $page_info.'&products_id_master='.$products_id_master.'&products_id_slave='.$products_id_slave.'&products_id_view='.$products_id_view));
        break;

      case 'Enlace Reciproco':
        $products_id_master = tep_db_prepare_input($_REQUEST['products_id_master']);
        $products_id_slave = tep_db_prepare_input($_REQUEST['products_id_slave']);
        $pop_order_id = tep_db_prepare_input($_REQUEST['pop_order_id']);
        if ($products_id_master != $products_id_slave) {
          $check = tep_db_query("select p.pop_id from " . TABLE_PRODUCTS_RELATED_PRODUCTS . " p where p.pop_products_id_master=" . $products_id_master ." and p.pop_products_id_slave=" . $products_id_slave);
          if (!tep_db_fetch_array($check)) {
            tep_db_query("insert into " . TABLE_PRODUCTS_RELATED_PRODUCTS . " values ('', '" . (int)$products_id_master . "', '" . (int)$products_id_slave . "', '". (int)$pop_order_id."')");
          }
          $check = tep_db_query("select p.pop_id from " . TABLE_PRODUCTS_RELATED_PRODUCTS . " p where p.pop_products_id_master=" . $products_id_slave ." and p.pop_products_id_slave=" . $products_id_master );
          if (!tep_db_fetch_array($check)) {
            tep_db_query("insert into " . TABLE_PRODUCTS_RELATED_PRODUCTS . " values ('', '" . (int)$products_id_slave . "', '" . (int)$products_id_master . "', '". (int)$pop_order_id."')");
          }
        }
        tep_redirect(tep_href_link(FILENAME_RELATED_PRODUCTS, $page_info.'&products_id_master='.$products_id_master.'&products_id_slave='.$products_id_slave.'&products_id_view='.$products_id_view));
        break;

      case 'Heredar productos relacionados':
        $products_id_master = tep_db_prepare_input($_REQUEST['products_id_master']);
        $products_id_slave = tep_db_prepare_input($_REQUEST['products_id_slave']);
        $pop_order_id = tep_db_prepare_input($_REQUEST['pop_order_id']);

        if ($products_id_master != $products_id_slave) {
          if (INSERT_AND_INHERIT == 'True') {
            $check = tep_db_query("select p.pop_id from " . TABLE_PRODUCTS_RELATED_PRODUCTS . " p where p.pop_products_id_master=" . $products_id_master." and p.pop_products_id_slave=" . $products_id_slave);
            if (!tep_db_fetch_array($check)) {
               tep_db_query("insert into " . TABLE_PRODUCTS_RELATED_PRODUCTS . " values ('', '" . (int)$products_id_master . "', '" . (int)$products_id_slave . "', '". (int)$pop_order_id."')");
            }
          }
          $products = tep_db_query("select p.pop_products_id_slave from " . TABLE_PRODUCTS_RELATED_PRODUCTS . " p where p.pop_products_id_master=" . $products_id_slave . " order by p.pop_id");
          while ($products_values = tep_db_fetch_array($products)) {
            $products_id_slave2 = $products_values['pop_products_id_slave'];
            if ($products_id_master != $products_id_slave2) {

              $check = tep_db_query("select p.pop_id from " . TABLE_PRODUCTS_RELATED_PRODUCTS . " p where p.pop_products_id_master=" . $products_id_master." and p.pop_products_id_slave=" . $products_id_slave2);
              if (!tep_db_fetch_array($check)) {
                tep_db_query(" insert into " . TABLE_PRODUCTS_RELATED_PRODUCTS . " values ('', '" . (int)$products_id_master . "', '" . (int)$products_id_slave2 . "', '". (int)$pop_order_id."')");
              }
            }
          }
        }
        tep_redirect(tep_href_link(FILENAME_RELATED_PRODUCTS, $page_info.'&products_id_master='.$products_id_master.'&products_id_slave='.$products_id_slave.'&products_id_view='.$products_id_view));
        break;

      case 'update_product_attribute':
        $products_id_master = tep_db_prepare_input($_REQUEST['products_id_master']);
        $products_id_slave = tep_db_prepare_input($_REQUEST['products_id_slave']);
        $pop_order_id = tep_db_prepare_input($_REQUEST['pop_order_id']);
        $pop_id = tep_db_prepare_input($_REQUEST['pop_id']);

        tep_db_query("update " . TABLE_PRODUCTS_RELATED_PRODUCTS . " set pop_products_id_master = '" . (int)$products_id_master . "', pop_products_id_slave = '" . (int)$products_id_slave . "', pop_order_id = '".(int)$pop_order_id."' where pop_id = '" . (int)$pop_id . "'");
        tep_redirect(tep_href_link(FILENAME_RELATED_PRODUCTS, $page_info.'&products_id_view='.$products_id_view));
        break;
      case 'delete_attribute':
        $pop_id = tep_db_prepare_input($_GET['pop_id']);

        tep_db_query("delete from " . TABLE_PRODUCTS_RELATED_PRODUCTS . " where pop_id = '" . (int)$pop_id . "'");

        tep_redirect(tep_href_link(FILENAME_RELATED_PRODUCTS, $page_info.'&products_id_view='.$products_id_view));
        break;
    }
  }
?>
<?php require(THEME . 'html/header.php'); ?>
<table border="0" width="100%" cellspacing="2" cellpadding="2">
  <tr>
    <td width="100%" valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="0">

      </tr>
      <tr>
        <td width="100%"><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading">&nbsp;Relacionar Productos</td>
            <td><form name="formview"><select name="products_id_view" onChange="return formview.submit();">
<?php

    echo '<option name="Mostrar todos" value="">Mostrar todos</option>';
    $products = tep_db_query("select p.products_id, p.products_model, pd.products_name from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd where pd.products_id = p.products_id and pd.language_id = '" . $languages_id . "' order by pd.products_name");
    while ($products_values = tep_db_fetch_array($products)) {
        $model = (RELATED_PRODUCTS_ADMIN_USE_MODEL == 'True')?$products_values['products_model'] . RELATED_PRODUCTS_ADMIN_MODEL_SEPARATOR:'';
        $name = (RELATED_PRODUCTS_ADMIN_USE_NAME == 'True')?$products_values['products_name']:'';
        if ($products_id_view == $products_values['products_id']) {
              echo '<option name="' . $name . '" value="' . $products_values['products_id'] . '" SELECTED>' . $model . $name . '</option>';
        } else {
              echo '<option name="' . $name . '" value="' . $products_values['products_id'] . '">' . $model . $name . '</option>';
        }
    }
?>
            </select></form>&nbsp;</td>
            <td>&nbsp;</td>
            <td><a href="<?php echo tep_href_link(FILENAME_CONFIGURATION, 'selected_box=configuration&gID=' . $gID); ?>">Configurar Opciones</a><br>
              <!--  <a href="<?php echo tep_href_link('sql_setup_related_products.php'); ?>">SQL Setup Utility</a> -->
            </td>
          </tr>
        </table></td>
      </tr>
      <tr>
<?php
/*
foreach ($_REQUEST as $key => $value) {
   echo "Key: $key; Value: $value<br>\n";
}
*/
  if ($action == 'update_attribute') {
    $form_action = 'update_product_attribute';
  } else {
    $form_action = 'add_product_attributes';
  }

  if (!isset($attribute_page)) {
    $attribute_page = 1;
  }
  $prev_attribute_page = $attribute_page - 1;
  $next_attribute_page = $attribute_page + 1;
  $form_params = 'action=' . $form_action . '&option_page=' . $option_page . '&value_page=' . $value_page . '&attribute_page=' . $attribute_page;
?>
        <td><form name="attributes" action="<?php echo tep_href_link(FILENAME_RELATED_PRODUCTS, $form_params); ?>" method="get"><table border="0" width="100%" cellspacing="0" cellpadding="2">
          <tr>
            <td colspan="5" class="smallText">
<?php
  $per_page = RELATED_PRODUCTS_MAX_ROW_LISTS_OPTIONS;

  $attributes = "
         SELECT
                pa.*
           FROM " .
                TABLE_PRODUCTS_RELATED_PRODUCTS . " pa
           LEFT JOIN " .
                TABLE_PRODUCTS_DESCRIPTION . " pd
             ON pa.pop_products_id_master = pd.products_id
            AND pd.language_id = '" . (int)$languages_id . "'";

  if ($products_id_view) { $attributes .= "
          WHERE pd.products_id = '$products_id_view'"; }
  $attributes .= "
       ORDER BY pd.products_name, pa.pop_order_id, pa.pop_id";

  $attribute_query = tep_db_query($attributes);

  $attribute_page_start = ($per_page * $attribute_page) - $per_page;
  $num_rows = tep_db_num_rows($attribute_query);

  if ($num_rows <= $per_page) {
     $num_pages = 1;
  } else if (($num_rows % $per_page) == 0) {
     $num_pages = ($num_rows / $per_page);
  } else {
     $num_pages = ($num_rows / $per_page) + 1;
  }
  $num_pages = (int) $num_pages;

  $attributes = $attributes . " LIMIT $attribute_page_start, $per_page";

  $view_id = '';
  if ($products_id_view) {
    $products_id_view = $products_id_master?$products_id_master:$products_id_view;
    $view_id = '&products_id_view=' . $products_id_view;
  }

  // Previous
  if ($prev_attribute_page) {
    echo '<a href="' . tep_href_link(FILENAME_RELATED_PRODUCTS, 'attribute_page=' . $prev_attribute_page . $view_id) . '"> &lt;&lt; </a> | ';
  }

  for ($i = 1; $i <= $num_pages; $i++) {
    if ($i != $attribute_page) {
      echo '<a href="' . tep_href_link(FILENAME_RELATED_PRODUCTS, 'attribute_page=' . $i) . $view_id . '">' . $i . '</a> | ';
    } else {
      echo '<b><font color="red">' . $i . '</font></b> | ';
    }
  }

  // Next
  if ($attribute_page != $num_pages) {
    echo '<a href="' . tep_href_link(FILENAME_RELATED_PRODUCTS, 'attribute_page=' . $next_attribute_page . $view_id) . '"> &gt;&gt; </a>';
  }
?>
            </td>
          </tr>
          <tr>
            <td colspan="5"><?php echo tep_black_line(); ?></td>
          </tr>
          <tr class="dataTableHeadingRow">
            <td class="dataTableHeadingContent">&nbsp;<?php echo TABLE_HEADING_ID; ?>&nbsp;</td>
            <td class="dataTableHeadingContent">&nbsp;Nombre del producto&nbsp;</td>
            <td class="dataTableHeadingContent">&nbsp;Nombre del producto a relacionar&nbsp;</td>
            <td class="dataTableHeadingContent" align="center">&nbsp;Orden&nbsp;</td>
            <td class="dataTableHeadingContent" align="center">&nbsp;Accion&nbsp;</td>
          </tr>
          <tr>
            <td colspan="5"><?php echo tep_black_line(); ?></td>
          </tr>
<?php
  $next_id = 1;
  $attributes = tep_db_query($attributes);
  while ($attributes_values = tep_db_fetch_array($attributes)) {
    $products_name_master = tep_get_products_name($attributes_values['pop_products_id_master']);
    $products_name_slave = tep_get_products_name($attributes_values['pop_products_id_slave']);
    if (RELATED_PRODUCTS_ADMIN_USE_MODEL == 'True') {
      $mModel = tep_get_products_model($attributes_values['pop_products_id_master']) . RELATED_PRODUCTS_ADMIN_MODEL_SEPARATOR . ' ';
      $sModel = tep_get_products_model($attributes_values['pop_products_id_slave']) . RELATED_PRODUCTS_ADMIN_MODEL_SEPARATOR . ' ';
    } else {
      $mModel = $sModel = '';
    }
    $pop_order_id = $attributes_values['pop_order_id'];
    $rows++;
?>
          <tr class="<?php echo (floor($rows/2) == ($rows/2) ? 'attributes-even' : 'attributes-odd'); ?>">
<?php
    if (($action == 'update_attribute') && ($_GET['pop_id'] == $attributes_values['pop_id'])) {
?>
            <td class="smallText">&nbsp;<?php echo $attributes_values['pop_id']; ?><input type="hidden" name="pop_id" value="<?php echo $attributes_values['pop_id']; ?>">&nbsp;</td>
            <td class="smallText">&nbsp;<select name="products_id_master">
<?php
      $products = tep_db_query("select p.products_id, p.products_model, pd.products_name from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd where pd.products_id = p.products_id and pd.language_id = '" . $languages_id . "' order by pd.products_name");
      while($products_values = tep_db_fetch_array($products)) {
        $model = (RELATED_PRODUCTS_ADMIN_USE_MODEL == 'True')?$products_values['products_model'] . RELATED_PRODUCTS_ADMIN_MODEL_SEPARATOR:'';
        $name = (RELATED_PRODUCTS_ADMIN_USE_NAME == 'True')?$products_values['products_name']:'';
        $product_name = (RELATED_PRODUCTS_MAX_NAME_LENGTH == '0')?$name:substr($name, 0, RELATED_PRODUCTS_MAX_NAME_LENGTH);
        if ($attributes_values['pop_products_id_master'] == $products_values['products_id']) {
          echo "\n" . '<option name="' . $products_values['products_name'] . '" value="' . $products_values['products_id'] . '" SELECTED>' . $model . $product_name . '</option>';
        } else {
          echo "\n" . '<option name="' . $products_values['products_name'] . '" value="' . $products_values['products_id'] . '">' . $model . $product_name . '</option>';
        }
      }
?>
            </select>&nbsp;</td>
            <td class="smallText">&nbsp;<select name="products_id_slave">
<?php
      $products = tep_db_query("select p.products_id, p.products_model, pd.products_name from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd where pd.products_id = p.products_id and pd.language_id = '" . $languages_id . "' order by pd.products_name");
      while($products_values = tep_db_fetch_array($products)) {
        $model = (RELATED_PRODUCTS_ADMIN_USE_MODEL == 'True')?$products_values['products_model'] . RELATED_PRODUCTS_ADMIN_MODEL_SEPARATOR:'';
        $name = (RELATED_PRODUCTS_ADMIN_USE_NAME == 'True')?$products_values['products_name']:'';
        $product_name = (RELATED_PRODUCTS_MAX_NAME_LENGTH == '0')?$name:substr($name, 0, RELATED_PRODUCTS_MAX_NAME_LENGTH);
        if ($attributes_values['pop_products_id_slave'] == $products_values['products_id']) {
          echo "\n" . '<option name="' . $products_values['products_name'] . '" value="' . $products_values['products_id'] . '" SELECTED>' . $products_values['products_model'] . ' - ' . $product_name . '</option>';
        } else {
          echo "\n" . '<option name="' . $products_values['products_name'] . '" value="' . $products_values['products_id'] . '">' . $products_values['products_model'] . ' - ' . $product_name . '</option>';
        }
      }
?>
            </select>&nbsp;</td>
            <td align="center" class="smallText">&nbsp;<input type="text" name="pop_order_id" value="<?php echo $attributes_values['pop_order_id']; ?>" size="6">&nbsp;</td>
            <td align="center" class="smallText">&nbsp;<input type="hidden" name="action" value="update_product_attribute"><?php echo tep_image_submit('button_update.gif', IMAGE_UPDATE); ?>&nbsp;<?php echo '<a href="' . tep_href_link(FILENAME_RELATED_PRODUCTS, '&attribute_page=' . $attribute_page . '&products_id_view='.$products_id_view, 'NONSSL') . '">'; ?><?php echo tep_image_button('button_cancel.gif', IMAGE_CANCEL); ?></a>&nbsp;</td>
<?php
    } else {
//  basic browse table list
?>
            <td class="smallText">&nbsp;<?php echo $attributes_values["pop_id"]; ?>&nbsp;</td>
            <td class="smallText">&nbsp;<?php echo $mModel ?><?php echo (RELATED_PRODUCTS_MAX_DISPLAY_LENGTH== '0')?$products_name_master:substr($products_name_master, 0, RELATED_PRODUCTS_MAX_DISPLAY_LENGTH); ?>&nbsp;</td>
            <td class="smallText">&nbsp;<?php echo $sModel ?><?php echo (RELATED_PRODUCTS_MAX_DISPLAY_LENGTH== '0')?$products_name_slave:substr($products_name_slave, 0, RELATED_PRODUCTS_MAX_DISPLAY_LENGTH); ?>&nbsp;</td>
            <td class="smallText" align="center">&nbsp;<?php echo $pop_order_id; ?>&nbsp;</td>
            <td align="center" class="smallText">

               <?php
                 $params = 'action=update_attribute&pop_id='
                          . $attributes_values['pop_id']
                          . '&attribute_page=' . $attribute_page
                          . '&products_id_view=' . $products_id_view;
                     echo '<a href="' . tep_href_link(FILENAME_RELATED_PRODUCTS, $params, 'NONSSL') . '">'; ?>
                     Editar</a>&nbsp;&nbsp;
               <?php
                 $params = 'action=delete_attribute&pop_id='
                          . $attributes_values['pop_id']
                          . '&attribute_page=' . $attribute_page
                          . '&products_id_view=' . $products_id_view;
                     if (RELATED_PRODUCTS_CONFIRM_DELETE == 'False') { ?>
               <a href="<?php echo tep_href_link(FILENAME_RELATED_PRODUCTS, $params, 'NONSSL')?>">Eliminar</a>
               <?php }else { ?>
               <a href="<?php echo tep_href_link(FILENAME_RELATED_PRODUCTS, $params, 'NONSSL')?>" onclick="return confirm('<?php echo sprintf(TEXT_CONFIRM_DELETE_ATTRIBUTE, addslashes($products_name_slave), addslashes($products_name_master)); ?>');">Eliminar</a>
               <?php } ?></td>
<?php
    }
    $max_attributes_id_query = tep_db_query("select max(pop_id) + 1 as next_id from " . TABLE_PRODUCTS_RELATED_PRODUCTS);
    $max_attributes_id_values = tep_db_fetch_array($max_attributes_id_query);
    $next_id = $max_attributes_id_values['next_id'];
?>
          </tr>
<?php
  }
  if ($action != 'update_attribute') {
?>
          <tr>
            <td colspan="5"><?php echo tep_black_line(); ?></td>
          </tr>
          <tr class="<?php echo (floor($rows/2) == ($rows/2) ? 'attributes-even' : 'attributes-odd'); ?>">
            <td class="smallText">&nbsp;<?php echo $next_id; ?>&nbsp;</td>
      	    <td class="smallText"><b>A:</b>&nbsp;<select style="width: 90%;" name="products_id_master">
<?php
    $products = tep_db_query("select p.products_id, p.products_model, pd.products_name from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd where pd.products_id = p.products_id and pd.language_id = '" . $languages_id . "' order by pd.products_name");
    $products_id_master = $_GET['products_id_master'];
    if (!$products_id_master) { $products_id_master = $products_id_view; }
    while ($products_values = tep_db_fetch_array($products)) {
      $model = (RELATED_PRODUCTS_ADMIN_USE_MODEL == 'True')?$products_values['products_model'] . RELATED_PRODUCTS_ADMIN_MODEL_SEPARATOR:'';
      $name = (RELATED_PRODUCTS_ADMIN_USE_NAME == 'True')?$products_values['products_name']:'';
	  $product_name = $name;
      // $product_name = (RELATED_PRODUCTS_MAX_NAME_LENGTH == '0')?$name:substr($name, 0, RELATED_PRODUCTS_MAX_NAME_LENGTH);
      if ($products_id_master == $products_values['products_id']) {
        echo '<option name="' . $products_values['products_name'] . '" value="' . $products_values['products_id'] . '" SELECTED>' . $products_values['products_model'] . ' - ' . $product_name . '</option>';
      } else {
        echo '<option name="' . $products_values['products_name'] . '" value="' . $products_values['products_id'] . '">' . $products_values['products_model'] . ' - ' . $product_name . '</option>';
      }
    }
?>
            </select>&nbsp;</td>
            <td class="smallText"><b>B:</b>&nbsp;<select  style="width: 90%;" name="products_id_slave">
<?php
    $products = tep_db_query("select p.products_id, p.products_model, pd.products_name from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd where pd.products_id = p.products_id and pd.language_id = '" . $languages_id . "' order by pd.products_name");
    while ($products_values = tep_db_fetch_array($products)) {
      $model = (RELATED_PRODUCTS_ADMIN_USE_MODEL == 'True')?$products_values['products_model'] . RELATED_PRODUCTS_ADMIN_MODEL_SEPARATOR:'';
      $name = (RELATED_PRODUCTS_ADMIN_USE_NAME == 'True')?$products_values['products_name']:'';
      $product_name = (RELATED_PRODUCTS_MAX_NAME_LENGTH == '0')?$name:substr($name, 0, RELATED_PRODUCTS_MAX_NAME_LENGTH);
	  $product_name = $name;
      if ($_GET['products_id_slave'] == $products_values['products_id']) {
        echo '<option name="' . $products_values['products_name'] . '" value="' . $products_values['products_id'] . '" SELECTED>' . $products_values['products_model'] . ' - ' . $product_name . '</option>';
      } else {
        echo '<option name="' . $products_values['products_name'] . '" value="' . $products_values['products_id'] . '">' . $products_values['products_model'] . ' - ' . $product_name . '</option>';
      }
    }
?>
            </select>&nbsp;</td>
            <td class="smallText" align="center">&nbsp;<input type="text" name="pop_order_id" size="3">&nbsp;</td>
          </tr>
          <tr><td colspan="5" align="center" class="smallText">
            <input type="submit" name="action" value="Insertar">
            <input type="submit" name="action" value="Enlace Reciproco">
            <input type="submit" name="action" value="Heredar productos relacionados">
          </td></tr>
<?php
  }
?>
          <tr>
            <td colspan="5"><?php echo tep_black_line(); ?></td>
          </tr>
        </table>
        <input type="hidden" name="products_id_view" value="<?php echo $products_id_view; ?>">
        </form></td>
      </tr>
    </table></td>
  </tr>
</table>
<?php require(THEME . 'html/footer.php'); ?>
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>