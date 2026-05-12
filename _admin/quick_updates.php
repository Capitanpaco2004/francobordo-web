<?php
	require('includes/application_top.php');

 ini_set('memory_limit', '-1');

 if (isset($_GET['row_by_page'])) {
   $row_by_page = (int)$_GET['row_by_page'];
 }
  if (isset($_GET['manufacturer'])) {
   $manufacturer = (int)$_GET['manufacturer'];
 }
  if (isset($_GET['sort_by'])) {	  
   $sort_by = $_GET['sort_by'];
   
	if( !preg_match( '/^(p\.products_model|pd\.products_name|p\.products_status|p\.products_weight|p\.products_quantity|p\.products_quantity_deseada|p\.products_ubicacion|manufacturers_id|p\.products_price|p\.products_tax_class_id) (ASC|DESC)$/i', $sort_by ) )
		$sort_by = '';
 }
  if (isset($_GET['page'])) {
   $page = $_GET['page'];
 }
  if (isset($_GET['customers_group_id'])) {
   $customers_group_id = (int)$_GET['customers_group_id'];
 }


 $row_by_page = $row_by_page ?? 0;
 ($row_by_page) ? define('MAX_DISPLAY_ROW_BY_PAGE' , $row_by_page ) : $row_by_page = MAX_DISPLAY_SEARCH_RESULTS; define('MAX_DISPLAY_ROW_BY_PAGE' , MAX_DISPLAY_SEARCH_RESULTS );

//// Tax Row
    $tax_class_array = array(array('id' => '0', 'text' => NO_TAX_TEXT));
    $tax_class_query = tep_db_query("select tax_class_id, tax_class_title from " . TABLE_TAX_CLASS . " order by tax_class_title");
    while ($tax_class = tep_db_fetch_array($tax_class_query)) {
      $tax_class_array[] = array('id' => $tax_class['tax_class_id'],
                                 'text' => $tax_class['tax_class_title']);
    }

////Info Row pour le champ fabriquant
        $manufacturers_array = array(array('id' => '0', 'text' => NO_MANUFACTURER));
        $manufacturers_query = tep_db_query("select manufacturers_id, manufacturers_name from " . TABLE_MANUFACTURERS . " order by manufacturers_name");
        while ($manufacturers = tep_db_fetch_array($manufacturers_query)) {
                $manufacturers_array[] = array('id' => $manufacturers['manufacturers_id'],
                'text' => $manufacturers['manufacturers_name']);
        }

// Display the list of the manufacturers
function manufacturers_list(){
        global $manufacturer;

        $manufacturers_query = tep_db_query("select m.manufacturers_id, m.manufacturers_name from " . TABLE_MANUFACTURERS . " m order by m.manufacturers_name ASC");
        $return_string = '<select name="manufacturer" onChange="this.form.submit();">';
        $return_string .= '<option value="' . 0 . '">' . TEXT_ALL_MANUFACTURERS . '</option>';
        while($manufacturers = tep_db_fetch_array($manufacturers_query)){
                $return_string .= '<option value="' . $manufacturers['manufacturers_id'] . '"';
                if($manufacturer && $manufacturers['manufacturers_id'] == $manufacturer) $return_string .= ' SELECTED';
                $return_string .= '>' . $manufacturers['manufacturers_name'] . '</option>';
        }
        $return_string .= '</select>';
        return $return_string;
}
// display the customer groups

function customers_groups_list(){
        global $customers_group_id;

        $customers_group_query = tep_db_query("select customers_group_id, customers_group_name from " . TABLE_CUSTOMERS_GROUPS . " order by customers_group_id");
        $return_string = '<select name="customers_group_id" onChange="this.form.submit();">';
        while($customers_groups = tep_db_fetch_array($customers_group_query)){
                $return_string .= '<option value="' . $customers_groups['customers_group_id'] . '"';
                if($customers_group_id && $customers_groups['customers_group_id'] == $customers_group_id) $return_string .= ' SELECTED';
                $return_string .= '>' . $customers_groups['customers_group_name'] . '</option>';
        }
        $return_string .= '</select>';
        return $return_string;
}

##// Update database
  switch ($_GET['action'] ?? '') {
    case 'update' :
      $count_update=0;
      $item_updated = array();
                  if($_POST['product_new_model']){
                   foreach($_POST['product_new_model'] as $id => $new_model) {
                         if (trim($_POST['product_new_model'][$id]) != trim($_POST['product_old_model'][$id])) {
                           $count_update++;
                           $item_updated[$id] = 'updated';
                           tep_db_query("UPDATE " . TABLE_PRODUCTS . " SET products_model='" . $new_model . "', products_last_modified=now() WHERE products_id='" . $id . "'");
                         }
                   }
                }
                if($_POST['product_new_ubicacion']){
                   foreach($_POST['product_new_ubicacion'] as $id => $new_ubicacion) {
                         if (trim($_POST['product_new_ubicacion'][$id]) != trim($_POST['product_old_ubicacion'][$id])) {
                           $count_update++;
                           $item_updated[$id] = 'updated';
                           tep_db_query("UPDATE " . TABLE_PRODUCTS . " SET products_ubicacion='" . $new_ubicacion . "', products_last_modified=now() WHERE products_id='" . $id . "'");
                         }
                   }
                }
                  if($_POST['product_new_name']){
                   foreach($_POST['product_new_name'] as $id => $new_name) {
                         if (trim($_POST['product_new_name'][$id]) != trim($_POST['product_old_name'][$id])) {
                           $count_update++;
                           $item_updated[$id] = 'updated';
                           tep_db_query("UPDATE " . TABLE_PRODUCTS_DESCRIPTION . " SET products_name='" . $new_name . "' WHERE products_id=$id and language_id=" . $languages_id);
                           tep_db_query("UPDATE " . TABLE_PRODUCTS . " SET products_last_modified=now() WHERE products_id='" . $id . "'");
                         }
                   }
                }
                  if($_POST['product_new_price']){
                   foreach($_POST['product_new_price'] as $id => $new_price) {
                         if ($_POST['product_new_price'][$id] != $_POST['product_old_price'][$id] && $_POST['update_price'][$id] == 'yes') {
                           $count_update++;
                           $item_updated[$id] = 'updated';
			   if ($_POST['customers_group_id'] == '0') {
                           tep_db_query("UPDATE " . TABLE_PRODUCTS . " SET products_price='" . $new_price . "', products_last_modified=now() WHERE products_id='" . $id . "'");
			   } else {
				   if ($_POST['cg_price_in_db'][$id] == 'yes') {
					if (trim($_POST['product_new_price'][$id]) == '') {
					tep_db_query("DELETE FROM " . TABLE_PRODUCTS_GROUPS . " WHERE products_id='" . $id . "' AND customers_group_id = '" . $_POST['customers_group_id'] ."'");	   
					   } else {
				tep_db_query("UPDATE " . TABLE_PRODUCTS_GROUPS . " SET customers_group_price='" . $new_price . "' WHERE products_id='" . $id . "' AND customers_group_id = '" . $_POST['customers_group_id'] ."'");
					   }
				   } elseif ($_POST['cg_price_in_db'][$id] == 'no') {
					tep_db_query("INSERT INTO " . TABLE_PRODUCTS_GROUPS . " SET products_id='" . $id . "', customers_group_price='" . $new_price . "', customers_group_id = '" . $_POST['customers_group_id'] ."'");
				   }
			   } // end if-else ($_POST['customers_group_id'] == '0')
                         }
                   }
                }
                if($_POST['product_new_weight']){
                   foreach($_POST['product_new_weight'] as $id => $new_weight) {
                         if ($_POST['product_new_weight'][$id] != $_POST['product_old_weight'][$id]) {
                           $count_update++;
                           $item_updated[$id] = 'updated';
                           tep_db_query("UPDATE " . TABLE_PRODUCTS . " SET products_weight='" . $new_weight . "', products_last_modified=now() WHERE products_id='" . $id . "'");
                         }
                   }
                }
                if($_POST['product_new_quantity']){
                   foreach($_POST['product_new_quantity'] as $id => $new_quantity) {
                         if ($_POST['product_new_quantity'][$id] != $_POST['product_old_quantity'][$id]) {
                           $count_update++;
                           $item_updated[$id] = 'updated';
                           tep_db_query("UPDATE " . TABLE_PRODUCTS . " SET products_quantity='". $new_quantity . "', products_last_modified=now() WHERE products_id='" . $id . "'");
                         }
                   }
                }
                
                if($_POST['product_new_quantity_deseada']){
                   foreach($_POST['product_new_quantity_deseada'] as $id => $new_quantity_deseada) {
                         if ($_POST['product_new_quantity_deseada'][$id] != $_POST['product_old_quantity_deseada'][$id]) {
                           $count_update++;
                           $item_updated[$id] = 'updated';
                           tep_db_query("UPDATE " . TABLE_PRODUCTS . " SET products_quantity_deseada='". $new_quantity_deseada . "', products_last_modified=now() WHERE products_id='" . $id . "'");
                         }
                   }
                }
                if($_POST['product_new_manufacturer']){
                   foreach($_POST['product_new_manufacturer'] as $id => $new_manufacturer) {
                         if ($_POST['product_new_manufacturer'][$id] != $_POST['product_old_manufacturer'][$id]) {
                           $count_update++;
                           $item_updated[$id] = 'updated';
                           tep_db_query("UPDATE " . TABLE_PRODUCTS . " SET manufacturers_id='" . $new_manufacturer . "', products_last_modified=now() WHERE products_id='" . $id . "'");
                         }
                   }
                }
                if($_POST['product_new_image']){
                   foreach($_POST['product_new_image'] as $id => $new_image) {
                         if (trim($_POST['product_new_image'][$id]) != trim($_POST['product_old_image'][$id])) {
                           $count_update++;
                           $item_updated[$id] = 'updated';
                           tep_db_query("UPDATE " . TABLE_PRODUCTS . " SET products_image='" . $new_image . "', products_last_modified=now() WHERE products_id='" . $id . "'");
                         }
                   }
                }
                   if($_POST['product_new_status']){
                           foreach($_POST['product_new_status'] as $id => $new_status) {
                                 if ($_POST['product_new_status'][$id] != $_POST['product_old_status'][$id]) {
                                   $count_update++;
                                   $item_updated[$id] = 'updated';
                                   tep_set_product_status($id, $new_status);
                                   tep_db_query("UPDATE " . TABLE_PRODUCTS . " SET products_last_modified=now() WHERE products_id='" . $id . "'");

                                 }
                           }
                }
                   if($_POST['product_new_tax']){
                           foreach($_POST['product_new_tax'] as $id => $new_tax_id) {
                                 if ($_POST['product_new_tax'][$id] != $_POST['product_old_tax'][$id]) {
                                   $count_update++;
                                   $item_updated[$id] = 'updated';
                                   tep_db_query("UPDATE " . TABLE_PRODUCTS . " SET products_tax_class_id='" . $new_tax_id . "', products_last_modified=now() WHERE products_id='" . $id . "'");
                                 }
                           }
                }
     $count_item = array_count_values($item_updated);
     if ($count_item['updated'] > 0) $messageStack->add($count_item['updated'].' '.TEXT_PRODUCTS_UPDATED . " $count_update " . TEXT_QTY_UPDATED, 'success');
     break;

     case 'calcul' :
      if (!empty($_POST['spec_price'])) $preview_global_price = 'true';
     break;
 }

//// explode string parameters from preview product
     $info_back = $info_back ?? '';
     if($info_back && $info_back!="-") {
       $infoback = explode('-',$info_back);
       $sort_by = $infoback[0];
       $page =  $infoback[1];
       $current_category_id = $infoback[2];
       $row_by_page = $infoback[3];
       $manufacturer = $infoback[4];
       $customers_group_id = $infoback[5];
     }

     $manufacturer = $manufacturer ?? '';
     $customers_group_id = $customers_group_id ?? '';
     $page = $page ?? 1;
     $sort_by = $sort_by ?? '';
     $preview_global_price = $preview_global_price ?? '';
     $rows = $rows ?? 0;
     $flag_spec = $flag_spec ?? false;

//// define the step for rollover lines per page
   for ($i = 10; $i <= 100 ; $i = $i+5) {
     if ($row_by_page < 10 && $i == 10) {
       $row_bypage_array[] = array('id' => $row_by_page, 'text' => $row_by_page);
     }
      $row_bypage_array[] = array('id' => $i, 'text' => $i);
     if ($row_by_page > 10 && $row_by_page%5 !== 0) {
       if (($i < $row_by_page) && ($i+5 > $row_by_page)) {
         $row_bypage_array[] = array('id' => $row_by_page, 'text' => $row_by_page);
       }
     }    
   } // end for ($i = 10; $i <= 100 ; $i = $i+5)
##// Let's start displaying page with forms
?>

<?php require(THEME . 'html/header.php'); ?>

<script language="javascript">
<!--
var browser_family;
var up = 1;

if (document.all && !document.getElementById)
  browser_family = "dom2";
else if (document.layers)
  browser_family = "ns4";
else if (document.getElementById)
  browser_family = "dom2";
else
  browser_family = "other";

function display_ttc(action, prix, taxe, up){
  if(action == 'display'){
          if(up != 1)
          valeur = Math.round((prix + (taxe / 100) * prix) * 100) / 100;
  }else{
          if(action == 'keyup'){
                valeur = Math.round((parseFloat(prix) + (taxe / 100) * parseFloat(prix)) * 100) / 100;
        }else{
         valeur = '0';
        }
  }
  switch (browser_family){
    case 'dom2':
          document.getElementById('descDiv').innerHTML = '<?php echo TOTAL_COST; ?> : '+valeur;
      break;
    case 'ie4':
      document.all.descDiv.innerHTML = '<?php echo TOTAL_COST; ?> : '+valeur;
      break;
    case 'ns4':
      document.descDiv.document.descDiv_sub.document.write(valeur);
      document.descDiv.document.descDiv_sub.document.close();
      break;
    case 'other':
      break;
  }
}

var tax_rates = new Array();
<?php
    for ($i=0, $n=sizeof($tax_class_array); $i<$n; $i++) {
      if ($tax_class_array[$i]['id'] > 0) {
        echo 'tax_rates["' . $tax_class_array[$i]['id'] . '"] = ' . tep_get_tax_rate_value($tax_class_array[$i]['id']) . ';' . "\n";
      }
    }
?>

function doRound(x, places) {
  return Math.round(x * Math.pow(10, places)) / Math.pow(10, places);
}

function getTaxRate(product_id) {
  var selected_value = document.forms.update.elements["product_new_tax[" + product_id + "]"].selectedIndex;
  var parameterVal = document.forms.update.elements["product_new_tax[" + product_id + "]"].value;

  if ( (parameterVal > 0) && (tax_rates[parameterVal] > 0) ) {
    return tax_rates[parameterVal];
  } else {
    return 0;
  }
}


function updateGross(product_id) {
  var taxRate = getTaxRate(product_id);
  var grossValue = document.forms.update.elements["product_new_price[" + product_id + "]"].value;

  if (taxRate > 0) {
    grossValue = grossValue * ((taxRate / 100) + 1);
  }

  document.forms.update.elements["product_new_price_gross[" + product_id + "]"].value = doRound(grossValue, 4);
}

function updateNet(product_id) {
  var taxRate = getTaxRate(product_id);
  var netValue = document.forms.update.elements["product_new_price_gross[" + product_id + "]"].value;

  if (taxRate > 0) {
    netValue = netValue / ((taxRate / 100) + 1);
  }

  document.forms.update.elements["product_new_price[" + product_id + "]"].value = doRound(netValue, 4);
}
-->
</script>

<!-- body //-->
<table border="0" width="100%" cellspacing="2" cellpadding="2">
  <tr>
<!-- body_text //-->

<td width="100%" valign="top">
  <table border="0" width="100%" cellspacing="0" cellpadding="2">
      <tr>
        <td>
         <table border="0" width="100%" cellspacing="0" cellpadding="0">
            <tr>
             <td class="pageHeading" colspan="3" valign="top"><?php echo HEADING_TITLE; ?></td>
                         <td class="pageHeading" align="right">
                         <?php
                                 if($current_category_id != 0){
                                        $image_query = tep_db_query("select c.categories_image from " . TABLE_CATEGORIES . " c where c.categories_id=" . $current_category_id);
                                        $image = tep_db_fetch_array($image_query);
                                        echo tep_image(DIR_WS_CATALOG . DIR_WS_IMAGES . $image['categories_image'], '', 40);
                                }else{
                                        if($manufacturer){
                                                $image_query = tep_db_query("select manufacturers_image from " . TABLE_MANUFACTURERS . " where manufacturers_id=" . $manufacturer);
                                                $image = tep_db_fetch_array($image_query);
                                                echo tep_image(DIR_WS_CATALOG . DIR_WS_IMAGES . $image['manufacturers_image'], '', 40);
                                        }
                                }
                        ?>
                   </td></tr>
                 </table></td></tr>
      <tr><td align="center">
                   <table width="100%" cellspacing="0" cellpadding="0" height="100"><tr align="left"><td valign="middle">
                   <?php echo tep_draw_form('row_by_page', FILENAME_QUICK_UPDATES, '', 'get'); echo tep_draw_hidden_field( 'manufacturer', $manufacturer); echo tep_draw_hidden_field( 'cPath', $current_category_id); echo tep_draw_hidden_field('customers_group_id', $customers_group_id); ?>
                                <table width="100%" cellspacing="0" cellpadding="0" border="0">

<tr align="center">

  <td class="smallText"><?php echo TEXT_MAXI_ROW_BY_PAGE . '&nbsp;&nbsp;' . tep_draw_pull_down_menu('row_by_page', $row_bypage_array, $row_by_page, 'onChange="this.form.submit();"'); 
  echo tep_hide_session_id(); ?></form></td>
  <?php echo tep_draw_form('categorie', FILENAME_QUICK_UPDATES, '', 'get'); echo tep_draw_hidden_field( 'row_by_page', $row_by_page); echo tep_draw_hidden_field( 'manufacturer', $manufacturer); echo tep_draw_hidden_field('customers_group_id', $customers_group_id);?>
  <td class="smallText" align="center" valign="top"><?php echo DISPLAY_CATEGORIES . '&nbsp;&nbsp;' . tep_draw_pull_down_menu('cPath', tep_get_category_tree(), $current_category_id, 'onChange="this.form.submit();"'); ?></td>
  <?php echo tep_hide_session_id(); ?></form>
  <?php echo tep_draw_form('manufacturers', FILENAME_QUICK_UPDATES, '', 'get'); echo tep_draw_hidden_field( 'row_by_page', $row_by_page); echo tep_draw_hidden_field( 'cPath', $current_category_id); echo tep_draw_hidden_field('customers_group_id', $customers_group_id);?>
  <td class="smallText" align="center" valign="top"><?php echo DISPLAY_MANUFACTURERS . '&nbsp;&nbsp' . manufacturers_list(); ?></td>
  <?php echo tep_hide_session_id(); ?></form>
  <td class="smallText" align="center" valign="top"><?php 
  echo tep_draw_form('customers_groups', FILENAME_QUICK_UPDATES, '', 'get'); echo tep_draw_hidden_field( 'row_by_page', $row_by_page); echo tep_draw_hidden_field( 'cPath', $current_category_id); echo tep_draw_hidden_field( 'manufacturer', $manufacturer);
  echo DISPLAY_CUSTOMERS_GROUPS . '&nbsp;&nbsp' . customers_groups_list(); 
  echo tep_hide_session_id(); ?></td>
    </tr>
       </table>
</form>
                        <table width="100%" cellspacing="0" cellpadding="0" border="0">
                                        <tr align="center">


                                                <td align="center">
   <table border="0" cellspacing="0">
      <form name="spec_price" <?php echo 'action="' . tep_href_link(FILENAME_QUICK_UPDATES, tep_get_all_get_params(array('action', 'info', 'pID')) . "action=calcul&page=" . $page . "&sort_by=" . $sort_by . "&cPath=" . $current_category_id . "&row_by_page=". $row_by_page . "&manufacturer=" . $manufacturer."&customers_group_id=" . $customers_group_id ."" , 'NONSSL') . '"'; ?> method="post">
      <tr>
         <td class="main"  align="center" valign="middle" nowrap> <?php echo TEXT_INPUT_SPEC_PRICE; ?></td>
         <td align="center" valign="middle"> <?php echo tep_draw_input_field('spec_price',0,'size="5"'); ?> </td>
         <td class="smalltext" align="center" valign="middle"><?php
          if ($preview_global_price != 'true') {
            echo '&nbsp;&nbsp;' . tep_image_submit('button_preview.png', IMAGE_PREVIEW, "page=$page&sort_by=$sort_by&cPath=$current_category_id&row_by_page=$row_by_page&manufacturer=$manufacturer&customers_group_id=" . $customers_group_id ."");
            } else echo '&nbsp;&nbsp;<a href="' . tep_href_link(FILENAME_QUICK_UPDATES, "page=$page&sort_by=$sort_by&cPath=$current_category_id&row_by_page=$row_by_page&manufacturer=$manufacturer&customers_group_id=" . $customers_group_id ."") . '">' . tep_image_button('button_cancel.png', IMAGE_CANCEL) . '</a>';?></td>
<?php if(ACTIVATE_COMMERCIAL_MARGIN == 'true') { 
echo '<td class="smalltext" align="center" valign="middle">&nbsp;&nbsp;&nbsp;&nbsp;' . tep_draw_checkbox_field('marge','yes','','no') . '&nbsp;' . tep_image(DIR_WS_IMAGES . 'icon_info.png', TEXT_MARGE_INFO) . '</td>';}
?>
    </tr>
    <tr>
      <td class="smalltext" align="center" valign="middle" colspan="3" nowrap>
      <?php if ($preview_global_price != 'true') {
            echo TEXT_SPEC_PRICE_INFO1 ;
            } else echo TEXT_SPEC_PRICE_INFO2;?>
      </td>
    </tr>
<?php echo tep_hide_session_id(); ?>
    </form>
</table>
                                                </td>
                                        </tr>
                                        <tr><td height="5"></td></tr>

                        </td></tr>
                        <br>
<table width="100%" cellspacing="0" cellpadding="0" border="0">
  <tr align="center">
     <form name="update" method="POST" action="<?php echo $_SERVER['PHP_SELF']."?action=update&page=". $page."&sort_by=". $sort_by ."&cPath=". $current_category_id. "&row_by_page=". $row_by_page ."&manufacturer=". $manufacturer ."&customers_group_id=" . $customers_group_id .""; ?>">
     <td class="smalltext" align="middle"><?php echo WARNING_MESSAGE; ?></td>
     <?php echo "<td class=\"pageHeading\" align=\"right\">" . '<script language="javascript"><!--
           switch (browser_family)
           {
           case "dom2":
           case "ie4":
           document.write(\'<div id="descDiv">\');
           break;
           default:
           document.write(\'<ilayer id="descDiv"><layer id="descDiv_sub">\');
           break;
           }
           -->
           </script>' . "</td>\n";
      ?>
      <td align="right" valign="middle"><?php echo tep_image_submit('button_update.png', IMAGE_UPDATE, "action=update&cPath=$current_category_id&page=$page&sort_by=$sort_by&row_by_page=$row_by_page");?></td>
<!-- question: why no manufacturer above? -->
   </tr>
</table>
   </td>
      </tr>
          <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
          <tr>
            <td valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr class="dataTableHeadingRow" style="background-repeat: repeat-x;">
                <td class="dataTableHeadingContent" align="left" valign="middle">
                  <table border="0" cellspacing="0" cellpadding="0">
                    <tr>
                     <td class="dataTableHeadingContent" align="left" valign="middle" width="120">
                     <?php if(DISPLAY_MODEL == 'true') echo " <a href=\"" . tep_href_link( FILENAME_QUICK_UPDATES, 'cPath='. $current_category_id .'&sort_by=p.products_model ASC&page=' . $page.'&row_by_page=' . $row_by_page . '&manufacturer=' . $manufacturer . '&customers_group_id=' . $customers_group_id)."\" >".tep_image(DIR_WS_IMAGES . 'icon_up.gif', TEXT_SORT_ALL . TABLE_HEADING_MODEL . ' ' . TEXT_ASCENDINGLY)."</a>
                     <a href=\"" . tep_href_link( FILENAME_QUICK_UPDATES, 'cPath='. $current_category_id .'&sort_by=p.products_model DESC&page=' . $page.'&row_by_page=' . $row_by_page . '&manufacturer=' . $manufacturer . '&customers_group_id=' . $customers_group_id)."\" >".tep_image(DIR_WS_IMAGES . 'icon_down.gif', TEXT_SORT_ALL . TABLE_HEADING_MODEL . ' ' . TEXT_DESCENDINGLY)."</a>
                     &nbsp;"  .TABLE_HEADING_MODEL . "</td>" ; ?>
                    </tr>
                  </table>
                </td>
                
                <td class="dataTableHeadingContent" align="left" valign="middle">
                  <table border="0" cellspacing="0" cellpadding="0">
                    <tr>
                     <td class="dataTableHeadingContent" align="left" valign="middle">
                     <?php echo " <a href=\"" . tep_href_link( FILENAME_QUICK_UPDATES, 'cPath='. $current_category_id .'&sort_by=pd.products_name ASC&page=' . $page.'&row_by_page=' . $row_by_page . '&manufacturer=' . $manufacturer . '&customers_group_id=' . $customers_group_id)."\" >".tep_image(DIR_WS_IMAGES . 'icon_up.gif', TEXT_SORT_ALL . TABLE_HEADING_PRODUCTS . TEXT_ASCENDINGLY)."</a>
                     <a href=\"" . tep_href_link( FILENAME_QUICK_UPDATES, 'cPath='. $current_category_id .'&sort_by=pd.products_name DESC&page=' . $page.'&row_by_page=' . $row_by_page . '&manufacturer=' . $manufacturer . '&customers_group_id=' . $customers_group_id)."\" >".tep_image(DIR_WS_IMAGES . 'icon_down.gif', TEXT_SORT_ALL . TABLE_HEADING_PRODUCTS . ' ' . TEXT_DESCENDINGLY)."</a>
                     &nbsp;"  .TABLE_HEADING_PRODUCTS . "</td>" ; ?>
                    </tr>
                  </table>
                </td>
                <td class="dataTableHeadingContent" align="center" valign="middle">
                  <table border="0" cellspacing="0" cellpadding="0">
                    <tr>
                     <td class="dataTableHeadingContent" align="center" valign="middle" width="115">
                     <?php if(DISPLAY_STATUT == 'true')echo " <a href=\"" . tep_href_link( FILENAME_QUICK_UPDATES, 'cPath='. $current_category_id .'&sort_by=p.products_status ASC&page=' . $page.'&row_by_page=' . $row_by_page . '&manufacturer=' . $manufacturer . '&customers_group_id=' . $customers_group_id)."\" >".tep_image(DIR_WS_IMAGES . 'icon_up.gif', TEXT_SORT_ALL . 'OFF ' . TEXT_ASCENDINGLY)."</a>
                     <a href=\"" . tep_href_link( FILENAME_QUICK_UPDATES, 'cPath='. $current_category_id .'&sort_by=p.products_status DESC&page=' . $page.'&row_by_page=' . $row_by_page . '&manufacturer=' . $manufacturer . '&customers_group_id=' . $customers_group_id)."\" >".tep_image(DIR_WS_IMAGES . 'icon_down.gif', TEXT_SORT_ALL . 'ON ' . TEXT_ASCENDINGLY)."</a>
                     &nbsp;off/on</td>" ; ?>
                    </tr>
                  </table>
                </td>
                <td class="dataTableHeadingContent" align="center" valign="middle">
                  <table border="0" cellspacing="0" cellpadding="0">
                    <tr>
                     <td class="dataTableHeadingContent" align="center" valign="middle" width="110">
                     <?php if(DISPLAY_WEIGHT == 'true')echo " <a href=\"" . tep_href_link( FILENAME_QUICK_UPDATES, 'cPath='. $current_category_id .'&sort_by=p.products_weight ASC&page=' . $page.'&row_by_page=' . $row_by_page . '&manufacturer=' . $manufacturer . '&customers_group_id=' . $customers_group_id)."\" >".tep_image(DIR_WS_IMAGES . 'icon_up.gif', TEXT_SORT_ALL . TABLE_HEADING_WEIGHT . ' ' . TEXT_ASCENDINGLY)."</a>
                     <a href=\"" . tep_href_link( FILENAME_QUICK_UPDATES, 'cPath='. $current_category_id .'&sort_by=p.products_weight DESC&page=' . $page.'&row_by_page=' . $row_by_page . '&manufacturer=' . $manufacturer . '&customers_group_id=' . $customers_group_id)."\" >".tep_image(DIR_WS_IMAGES . 'icon_down.gif', TEXT_SORT_ALL . TABLE_HEADING_WEIGHT . ' ' . TEXT_DESCENDINGLY)."</a>
                     &nbsp;" . TABLE_HEADING_WEIGHT . "</td>" ; ?>
                    </tr>
                  </table>
                </td>
                <td class="dataTableHeadingContent" align="center" valign="middle">
                  <table border="0" cellspacing="0" cellpadding="0">
                    <tr>
                     <td class="dataTableHeadingContent" align="center" valign="middle" width="130">
                     <?php if(DISPLAY_QUANTITY == 'true')echo " <a href=\"" . tep_href_link( FILENAME_QUICK_UPDATES, 'cPath='. $current_category_id .'&sort_by=p.products_quantity ASC&page=' . $page.'&row_by_page=' . $row_by_page . '&manufacturer=' . $manufacturer . '&customers_group_id=' . $customers_group_id)."\" >".tep_image(DIR_WS_IMAGES . 'icon_up.gif', TEXT_SORT_ALL . TABLE_HEADING_QUANTITY . ' ' . TEXT_ASCENDINGLY)."</a>
                     <a href=\"" . tep_href_link( FILENAME_QUICK_UPDATES, 'cPath='. $current_category_id .'&sort_by=p.products_quantity DESC&page=' . $page.'&row_by_page=' . $row_by_page . '&manufacturer=' . $manufacturer . '&customers_group_id=' . $customers_group_id)."\" >".tep_image(DIR_WS_IMAGES . 'icon_down.gif', TEXT_SORT_ALL . TABLE_HEADING_QUANTITY . ' ' . TEXT_DESCENDINGLY)."</a>
                     &nbsp;" . TABLE_HEADING_QUANTITY . "</td>" ; ?>
                    </tr>
                  </table>
                </td>
                <td class="dataTableHeadingContent" align="center" valign="middle">
                  <table border="0" cellspacing="0" cellpadding="0">
                    <tr>
                     <td class="dataTableHeadingContent" align="center" valign="middle" width="130">
                     <?php if(DISPLAY_QUANTITY == 'true')echo " <a href=\"" . tep_href_link( FILENAME_QUICK_UPDATES, 'cPath='. $current_category_id .'&sort_by=p.products_quantity_deseada ASC&page=' . $page.'&row_by_page=' . $row_by_page . '&manufacturer=' . $manufacturer . '&customers_group_id=' . $customers_group_id)."\" >".tep_image(DIR_WS_IMAGES . 'icon_up.gif', TEXT_SORT_ALL . TABLE_HEADING_QUANTITY . ' ' . TEXT_ASCENDINGLY)."</a>
                     <a href=\"" . tep_href_link( FILENAME_QUICK_UPDATES, 'cPath='. $current_category_id .'&sort_by=p.products_quantity_deseada DESC&page=' . $page.'&row_by_page=' . $row_by_page . '&manufacturer=' . $manufacturer . '&customers_group_id=' . $customers_group_id)."\" >".tep_image(DIR_WS_IMAGES . 'icon_down.gif', TEXT_SORT_ALL . TABLE_HEADING_QUANTITY . ' ' . TEXT_DESCENDINGLY)."</a>
                     &nbsp;Stock Deseado</td>" ; ?>
                    </tr>
                  </table>
                </td>
                <td class="dataTableHeadingContent" align="left" valign="middle">
                  <table border="0" cellspacing="0" cellpadding="0">
                    <tr>
                     <td class="dataTableHeadingContent" align="left" valign="middle" width="120">
                     <?php if(DISPLAY_MODEL == 'true') echo " <a href=\"" . tep_href_link( FILENAME_QUICK_UPDATES, 'cPath='. $current_category_id .'&sort_by=p.products_ubicacion ASC&page=' . $page.'&row_by_page=' . $row_by_page . '&manufacturer=' . $manufacturer . '&customers_group_id=' . $customers_group_id)."\" >".tep_image(DIR_WS_IMAGES . 'icon_up.gif', TEXT_SORT_ALL . TABLE_HEADING_MODEL . ' ' . TEXT_ASCENDINGLY)."</a>
                     <a href=\"" . tep_href_link( FILENAME_QUICK_UPDATES, 'cPath='. $current_category_id .'&sort_by=p.products_ubicacion DESC&page=' . $page.'&row_by_page=' . $row_by_page . '&manufacturer=' . $manufacturer . '&customers_group_id=' . $customers_group_id)."\" >".tep_image(DIR_WS_IMAGES . 'icon_down.gif', TEXT_SORT_ALL . TABLE_HEADING_MODEL . ' ' . TEXT_DESCENDINGLY)."</a>
                     &nbsp;Ubicaci�n</td>" ; ?>
                    </tr>
                  </table>
                </td>
                
                  <td class="dataTableHeadingContent" align="left" valign="middle">
                  <table border="0" cellspacing="0" cellpadding="0">
                    <tr>
                     <td class="dataTableHeadingContent" align="left" valign="middle">
                     <?php if(DISPLAY_IMAGE == 'true')echo "&nbsp; <a href=\"" . tep_href_link( FILENAME_QUICK_UPDATES, 'cPath='. $current_category_id .'&sort_by=p.products_image ASC&page=' . $page.'&row_by_page=' . $row_by_page . '&manufacturer=' . $manufacturer . '&customers_group_id=' . $customers_group_id)."\" >".tep_image(DIR_WS_IMAGES . 'icon_up.gif', TEXT_SORT_ALL . TABLE_HEADING_IMAGE . ' ' . TEXT_ASCENDINGLY)."</a>
                     <a href=\"" . tep_href_link( FILENAME_QUICK_UPDATES, 'cPath='. $current_category_id .'&sort_by=p.products_image DESC&page=' . $page.'&row_by_page=' . $row_by_page . '&manufacturer=' . $manufacturer . '&customers_group_id=' . $customers_group_id)."\" >".tep_image(DIR_WS_IMAGES . 'icon_down.gif', TEXT_SORT_ALL . TABLE_HEADING_IMAGE . ' ' . TEXT_DESCENDINGLY)."</a>
                     <br>&nbsp; " . TABLE_HEADING_IMAGE . "</td>" ; ?>
                    </tr>
                  </table>
                </td>
                  <td class="dataTableHeadingContent" align="left" valign="middle">
                  <table border="0" cellspacing="0" cellpadding="0">
                    <tr>
                     <td class="dataTableHeadingContent" align="left" valign="middle">
                     <?php if(DISPLAY_MANUFACTURER == 'true')echo "&nbsp;&nbsp; <a href=\"" . tep_href_link( FILENAME_QUICK_UPDATES, 'cPath='. $current_category_id .'&sort_by=manufacturers_id ASC&page=' . $page.'&row_by_page=' . $row_by_page . '&manufacturer=' . $manufacturer . '&customers_group_id=' . $customers_group_id)."\" >".tep_image(DIR_WS_IMAGES . 'icon_up.gif', TEXT_SORT_ALL . TABLE_HEADING_MANUFACTURERS . ' ' . TEXT_ASCENDINGLY)."</a>
                     <a href=\"" . tep_href_link( FILENAME_QUICK_UPDATES, 'cPath='. $current_category_id .'&sort_by=manufacturers_id DESC&page=' . $page.'&row_by_page=' . $row_by_page . '&manufacturer=' . $manufacturer . '&customers_group_id=' . $customers_group_id)."\" >".tep_image(DIR_WS_IMAGES . 'icon_down.gif', TEXT_SORT_ALL . TABLE_HEADING_MANUFACTURERS . ' ' . TEXT_DESCENDINGLY)."</a>
                     &nbsp; " . TABLE_HEADING_MANUFACTURERS . "</td>" ; ?>
                    </tr>
                  </table>
                </td>
                  <td class="dataTableHeadingContent" align="left" valign="middle">
                   <?php echo "&nbsp;&nbsp;&nbsp; <a href=\"" . tep_href_link( FILENAME_QUICK_UPDATES, 'cPath='. $current_category_id .'&sort_by=p.products_price ASC&page=' . $page.'&row_by_page=' . $row_by_page . '&manufacturer=' . $manufacturer . '&customers_group_id=' . $customers_group_id) ."\" >".tep_image(DIR_WS_IMAGES . 'icon_up.gif', TEXT_SORT_ALL . TABLE_HEADING_PRICE . ' ' . TEXT_ASCENDINGLY)."</a>
                    <a href=\"" . tep_href_link( FILENAME_QUICK_UPDATES, 'cPath='. $current_category_id .'&sort_by=p.products_price DESC&page=' . $page.'&row_by_page=' . $row_by_page . '&manufacturer=' . $manufacturer . '&customers_group_id=' . $customers_group_id) ."\" >".tep_image(DIR_WS_IMAGES . 'icon_down.gif', TEXT_SORT_ALL . TABLE_HEADING_PRICE . ' ' . TEXT_DESCENDINGLY)."</a>
                    &nbsp; " . TABLE_HEADING_PRICE . "</td>";?>

                  <td class="dataTableHeadingContent" align="center" valign="middle" width="65px">
                   <?php if(DISPLAY_TAX == 'true')echo " <a href=\"" . tep_href_link( FILENAME_QUICK_UPDATES, 'cPath='. $current_category_id .'&sort_by=p.products_tax_class_id ASC&page=' . $page.'&row_by_page=' . $row_by_page . '&manufacturer=' . $manufacturer . '&customers_group_id=' . $customers_group_id)."\" >".tep_image(DIR_WS_IMAGES  . 'icon_up.gif', TEXT_SORT_ALL . TABLE_HEADING_TAX . ' ' . TEXT_ASCENDINGLY)."</a>
                    <a href=\"" . tep_href_link( FILENAME_QUICK_UPDATES, 'cPath='. $current_category_id .'&sort_by=p.products_tax_class_id DESC&page=' . $page.'&row_by_page=' . $row_by_page . '&manufacturer=' . $manufacturer . '&customers_group_id=' . $customers_group_id)."\" >".tep_image(DIR_WS_IMAGES  . 'icon_down.gif', TEXT_SORT_ALL . TABLE_HEADING_TAX . ' ' . TEXT_DESCENDINGLY)."</a>
                    &nbsp;" . TABLE_HEADING_TAX . " </td> " ; ?>
                 <td class="dataTableHeadingContent" align="center" valign="middle"></td>
                <td class="dataTableHeadingContent" align="center" valign="middle"></td>
                </tr><tr class="datatableRow">
<?php
//// control string sort page
     if ($sort_by && !preg_match('/order by/i',$sort_by)) $sort_by = 'order by '.$sort_by ;
//// define the string parameters for good back preview product
     $origin = FILENAME_QUICK_UPDATES."?info_back=$sort_by-$page-$current_category_id-$row_by_page-$manufacturer-$customers_group_id";
//// controle length (lines per page)
     $split_page = (int)$_GET['page'];
     if ($split_page > 1) $rows = $split_page * MAX_DISPLAY_ROW_BY_PAGE - MAX_DISPLAY_ROW_BY_PAGE;

////  select categories
  if ($current_category_id == 0){
          if($manufacturer){
            $products_query_raw = "select p.products_id, p.products_image, p.products_model, p.products_ubicacion, pd.products_name, p.products_status, p.products_weight, p.products_quantity, p.products_quantity_deseada, p.manufacturers_id, p.products_price, p.products_tax_class_id from  " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION .  " pd where p.products_id = pd.products_id and pd.language_id = '$languages_id' and p.manufacturers_id = " . $manufacturer . " $sort_by ";
          }else{
                $products_query_raw = "select p.products_id, p.products_image, p.products_model, p.products_ubicacion, pd.products_name, p.products_status, p.products_weight, p.products_quantity, p.products_quantity_deseada, p.manufacturers_id, p.products_price, p.products_tax_class_id from  " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION .  " pd where p.products_id = pd.products_id and pd.language_id = '$languages_id' $sort_by ";
        }
  } // end if ($current_category_id == 0)
  else {
         if($manufacturer){
                 $products_query_raw = "select p.products_id, p.products_image, p.products_model, p.products_ubicacion, pd.products_name, p.products_status, p.products_weight, p.products_quantity, p.products_quantity_deseada, p.manufacturers_id, p.products_price, p.products_tax_class_id from  " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION .  " pd, " . TABLE_PRODUCTS_TO_CATEGORIES . " pc where p.products_id = pd.products_id and pd.language_id = '$languages_id' and p.products_id = pc.products_id and pc.categories_id = '" . $current_category_id . "' and p.manufacturers_id = " . $manufacturer . " $sort_by ";
          }else{
                $products_query_raw = "select p.products_id, p.products_image, p.products_model, p.products_ubicacion, pd.products_name, p.products_status, p.products_weight, p.products_quantity, p.products_quantity_deseada, p.manufacturers_id, p.products_price, p.products_tax_class_id from  " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION .  " pd, " . TABLE_PRODUCTS_TO_CATEGORIES . " pc where p.products_id = pd.products_id and pd.language_id = '$languages_id' and p.products_id = pc.products_id and pc.categories_id = '" . $current_category_id . "' $sort_by ";
        }
  }

//// page splitter and display each products info
  $products_split = new splitPageResults($split_page, MAX_DISPLAY_ROW_BY_PAGE, $products_query_raw, $products_query_numrows);
  $products_query = tep_db_query($products_query_raw);
  while ($_products = tep_db_fetch_array($products_query)) {
	$products[] = $_products;
	$list_of_products_ids[] = $_products['products_id'];
  }
  if (tep_not_null($list_of_products_ids)) {
	  if (isset($customers_group_id) && $customers_group_id != '0' && $customers_group_id != '') {
		$pg_query = tep_db_query("select pg.products_id, customers_group_price as price from " . TABLE_PRODUCTS_GROUPS . " pg where products_id in ('".implode("','",$list_of_products_ids)."') and pg.customers_group_id = '".$customers_group_id."' ");
		while ($pg_array = tep_db_fetch_array($pg_query)) {
		   $new_prices[] = array ('products_id' => $pg_array['products_id'], 'products_price' => $pg_array['price']);
		}

   for ($x = 0; $x < count($list_of_products_ids); $x++) {
	   // delete products_price (retail) first
	   $products[$x]['products_price'] = '';
	   // need to know whether a customer group price is in the table products_groups or not 
	   // (for choosing update or insert)
	   $products[$x]['cg_price_in_db'] = 'no';
// replace products prices with those from customers_group table
      if(!empty($new_prices)) {
         for ($i = 0; $i < count($new_prices); $i++) {
		 if ($products[$x]['products_id'] == $new_prices[$i]['products_id'] ) {
			$products[$x]['products_price'] = $new_prices[$i]['products_price'];
			$products[$x]['cg_price_in_db'] = 'yes';
		 }
	 } // end for ($i = 0; $i < count($new_prices); $i++)
      } // end if(!empty($new_prices))

   } // end for ($x = 0; $x < count($list_of_products_ids); $x++)
	  } // end if (isset($customers_group_id) && $customers_group_id != '0')
	  
// now make sure we get all the specials_id and specials_prices in one query instead of one by one
  if (isset($customers_group_id) && $customers_group_id != '0' && $customers_group_id != '') {
	  $specials_query = tep_db_query("select products_id, specials_id from " . TABLE_SPECIALS . " where products_id in ('".implode("','",$list_of_products_ids)."') and status = '1' and customers_group_id = '" .$customers_group_id. "'");
  } else {
	  $specials_query = tep_db_query("select products_id, specials_id from " . TABLE_SPECIALS . " where products_id in ('".implode("','",$list_of_products_ids)."') and status = '1' and customers_group_id = '0'");
  }
  	while ($specials_array = tep_db_fetch_array($specials_query)) {
	$new_s_prices[] = array ('products_id' => $specials_array['products_id'], 'specials_id' => $specials_array['specials_id']);
	}
	// put in the specials id's
   for ($x = 0; $x < count($list_of_products_ids); $x++) {
	   // make sure a value for special price and specials_id is added
	   $products[$x]['specials_id'] = '';
	         if(!empty($new_s_prices)) {
         for ($i = 0; $i < count($new_s_prices); $i++) {
		 if ($products[$x]['products_id'] == $new_s_prices[$i]['products_id'] ) {
			$products[$x]['specials_id'] = $new_s_prices[$i]['specials_id'];
		 }
	 } // end for ($i = 0; $i < count($new_prices); $i++)
      } // end if(!empty($new_s_prices))   
   } // end ($x = 0; $x < count($list_of_products_ids); $x++)
   
// debug:   echo '<pre>products array'; print_r($products);
	  
   for ($x = 0; $x < count($list_of_products_ids); $x++) {	  
    $rows++;
    if (strlen($rows) < 2) {
      $rows = '0' . $rows;
    }
//// check for global add value or rates, calcul and round values rates
    if (!empty($_POST['spec_price'])){
      $flag_spec = 'true' ;
      if (substr($_POST['spec_price'],-1) == '%') {
                  if($_POST['marge'] && substr($_POST['spec_price'],0,1) != '-'){
                        $valeur = (1 - (preg_replace("/\%/i", "", $_POST['spec_price']) / 100));
                        $price = sprintf("%01.2f", round($products[$x]['products_price'] / $valeur,2));
                }else{
                $price = sprintf("%01.2f", round($products[$x]['products_price'] + (($spec_price / 100) * $products[$x]['products_price']),2));
              }
          } else $price = sprintf("%01.2f", round($products[$x]['products_price'] + $spec_price,2));
    } else $price = $products[$x]['products_price'] ;

//// Check Tax_rate for displaying TTC
        $tax_query = tep_db_query("select r.tax_rate, c.tax_class_title from " . TABLE_TAX_RATES . " r, " . TABLE_TAX_CLASS . " c where r.tax_class_id=" . $products[$x]['products_tax_class_id'] . " and c.tax_class_id=" . $products[$x]['products_tax_class_id']);
        $tax_rate = tep_db_fetch_array($tax_query);
        if($tax_rate['tax_rate'] == '')$tax_rate['tax_rate'] = 0;
// SPPC v1.0: added && DISPLAY_MANUFACTURER == 'true'
        if (MODIFY_MANUFACTURER == 'false' && DISPLAY_MANUFACTURER == 'true') {
                $manufacturer_query = tep_db_query("select manufacturers_name from " . TABLE_MANUFACTURERS . " where manufacturers_id=" . $products[$x]['manufacturers_id']);
		// mixing of global manufacturer and local manufacturer in original quick_updates
		// change original $manufacturer to another variable
                $products_manufacturer = tep_db_fetch_array($manufacturer_query);
        }
//// display infos per row
// SPPC v1.1 this.style.cursor='hand' changed to this.style.cursor='pointer' (valid CSS)
                if($flag_spec){echo '<tr class="dataTableRow" onmouseover="'; if(DISPLAY_TVA_OVER == 'true'){echo 'display_ttc(\'display\', ' . $price . ', ' . $tax_rate['tax_rate'] . ');';} echo 'this.className=\'dataTableRowOver\';this.style.cursor=\'pointer\'" onmouseout="'; if(DISPLAY_TVA_OVER == 'true'){echo 'display_ttc(\'delete\');';} echo 'this.className=\'dataTableRow\'">'; }else{ echo '<tr class="dataTableRow" onmouseover="'; if(DISPLAY_TVA_OVER == 'true'){echo 'display_ttc(\'display\', ' . $products[$x]['products_price'] . ', ' . $tax_rate['tax_rate'] . ');';} echo 'this.className=\'dataTableRowOver\';this.style.cursor=\'pointer\'" onmouseout="'; if(DISPLAY_TVA_OVER == 'true'){echo 'display_ttc(\'delete\', \'\', \'\', 0);';} echo 'this.className=\'dataTableRow\'">';}
                if(DISPLAY_MODEL == 'true'){if(MODIFY_MODEL == 'true')echo "<td class=\"smallText\" align=\"center\"><input type=\"text\" size=\"15\" name=\"product_new_model[".$products[$x]['products_id']."]\" value=\"".$products[$x]['products_model']."\"></td>\n";else echo "<td class=\"smallText\" align=\"left\">" . $products[$x]['products_model'] . "</td>\n";}else{ echo "<td class=\"smallText\" align=\"left\">";}
        if(MODIFY_NAME == 'true')echo "<td class=\"smallText\" align=\"center\"><input type=\"text\" size=\"60\" name=\"product_new_name[".$products[$x]['products_id']."]\" value=\"".str_replace("\"","&quot;",$products[$x]['products_name'])."\"></td>\n";else echo "<td class=\"smallText\" align=\"left\">".$products[$x]['products_name']."</td>\n";
//// Product status radio button
                if(DISPLAY_STATUT == 'true'){
                        if ($products[$x]['products_status'] == '1') {
                         echo "<td class=\"smallText\" align=\"center\" style=\"white-space: nowrap;\"><input  type=\"radio\" name=\"product_new_status[".$products[$x]['products_id']."]\" value=\"0\" ><input type=\"radio\" name=\"product_new_status[".$products[$x]['products_id']."]\" value=\"1\" checked ></td>\n";
                        } else {
                         echo "<td class=\"smallText\" align=\"center\" style=\"white-space: nowrap;\"><input type=\"radio\" style=\"background-color: #EEEEEE\" name=\"product_new_status[".$products[$x]['products_id']."]\" value=\"0\" checked ><input type=\"radio\" style=\"background-color: #EEEEEE\" name=\"product_new_status[".$products[$x]['products_id']."]\" value=\"1\"></td>\n";
                        }
                }else{
                        echo "<td class=\"smallText\" align=\"center\"></td>";
                }
        if(DISPLAY_WEIGHT == 'true')echo "<td class=\"smallText\" align=\"center\"><input type=\"text\" size=\"5\" name=\"product_new_weight[".$products[$x]['products_id']."]\" value=\"".$products[$x]['products_weight']."\"></td>\n";else echo "<td class=\"smallText\" align=\"center\"></td>";
        if(DISPLAY_QUANTITY == 'true')echo "<td class=\"smallText\" align=\"center\"><input type=\"text\" size=\"3\" name=\"product_new_quantity[".$products[$x]['products_id']."]\" value=\"".$products[$x]['products_quantity']."\"></td>\n";else echo "<td class=\"smallText\" align=\"center\"></td>";
        if(DISPLAY_QUANTITY == 'true')echo "<td class=\"smallText\" align=\"center\"><input type=\"text\" size=\"3\" name=\"product_new_quantity_deseada[".$products[$x]['products_id']."]\" value=\"".$products[$x]['products_quantity_deseada']."\"></td>\n";else echo "<td class=\"smallText\" align=\"center\"></td>";
        if(DISPLAY_MODEL == 'true'){if(MODIFY_MODEL == 'true')echo "<td class=\"smallText\" align=\"center\"><input type=\"text\" size=\"8\" name=\"product_new_ubicacion[".$products[$x]['products_id']."]\" value=\"".$products[$x]['products_ubicacion']."\"></td>\n";else echo "<td class=\"smallText\" align=\"left\">" . $products[$x]['products_ubicacion'] . "</td>\n";}
                if(DISPLAY_IMAGE == 'true')echo "<td class=\"smallText\" align=\"center\"><input type=\"text\" size=\"8\" name=\"product_new_image[".$products[$x]['products_id']."]\" value=\"".$products[$x]['products_image']."\"></td>\n";else echo "<td class=\"smallText\" align=\"center\"></td>";
                if(DISPLAY_MANUFACTURER == 'true'){if(MODIFY_MANUFACTURER == 'true')echo "<td class=\"smallText\" align=\"center\">".tep_draw_pull_down_menu("product_new_manufacturer[".$products[$x]['products_id']."]", $manufacturers_array, $products[$x]['manufacturers_id'])."</td>\n";else echo "<td class=\"smallText\" align=\"center\">" . $products_manufacturer['manufacturers_name'] . "</td>";}else{ echo "<td class=\"smallText\" align=\"center\"></td>";}
//// get the specials products list
/*   deleted code */
//// check specials
//  original: if ( in_array($products[$x]['products_id'],$specials_array)) {
        if (tep_not_null($products[$x]['specials_id'])) {		
 /* deleted code */
            echo "<td class=\"smallText\" style=\"white-space: nowrap;\">&nbsp;&nbsp;&nbsp;&nbsp;<input type=\"text\" size=\"8\" name=\"product_new_price[".$products[$x]['products_id']."]\" value=\"".$products[$x]['products_price']."\" style=\"background-color: lightyellow;\" disabled >&nbsp;<a target=blank href=\"".tep_href_link (FILENAME_SPECIALS, 'sID='.$products[$x]['specials_id']).'&action=edit'."\">". tep_image(DIR_WS_IMAGES . 'icon_info.png', TEXT_SPECIALS_PRODUCTS) ."</a></td>\n";
        } else {
            if ($flag_spec == 'true') {
                   echo "<td class=\"smallText\" align=\"left\">&nbsp;&nbsp;&nbsp;&nbsp;<input type=\"text\" size=\"8\" name=\"product_new_price[".$products[$x]['products_id']."]\" "; if(DISPLAY_TVA_UP == 'true'){ echo "onKeyUp=\"display_ttc('keyup', this.value" . ", " . $tax_rate['tax_rate'] . ", 1);\"";} echo " value=\"".$price ."\" style=\"background-color: lightyellow;\">". tep_draw_checkbox_field('update_price['. $products[$x]['products_id'] .']','yes','checked','no')."</td>\n";
            } else { 
	    echo "<td class=\"smallText\" style=\"white-space: nowrap;\">&nbsp;&nbsp;&nbsp;&nbsp;<input type=\"text\" size=\"6\" name=\"product_new_price_gross[".$products[$x]['products_id']."]\" ";
      echo "onkeyup=\"updateNet(".$products[$x]['products_id'].");\"";
      echo " value=\"".($price*(1+$tax_rate['tax_rate']/100)) ."\">&#160;&#160;<input type=\"text\" size=\"6\" name=\"product_new_price[".$products[$x]['products_id']."]\" "; 
	    echo "onkeyup=\"updateGross(".$products[$x]['products_id'].");\""; 
	    echo " value=\"".$price ."\" style=\"background-color: lightyellow;\">".tep_draw_hidden_field('update_price['.$products[$x]['products_id'].']','yes'). "</td>\n";}
        } // end if-else (tep_not_null($products[$x]['specials_id']))
   if (DISPLAY_TAX == 'true') { 
	   if (MODIFY_TAX == 'true') { 
	      echo "<td class=\"smallText\" align=\"left\">". tep_draw_pull_down_menu("product_new_tax[". $products[$x]['products_id'] ."]", $tax_class_array, $products[$x]['products_tax_class_id'], 'onchange="updateGross('.$products[$x]['products_id'].')"')."</td>\n";
           } else {
              echo "<td class=\"smallText\" align=\"left\">" . $tax_rate['tax_class_title'] . "<input type=\"hidden\" name=\"product_new_tax[". $products[$x]['products_id'] ."]\" value=\"" . $products[$x]['products_tax_class_id'] . "\"></td>";
	   } // end if-else (MODIFY_TAX == 'true')
   }  else { 
		echo "<td class=\"smallText\" align=\"center\"><input type=\"hidden\" name=\"product_new_tax[". $products[$x]['products_id'] ."]\" value=\"" . $products[$x]['products_tax_class_id'] . "\"></td>";
           }
//// links to preview or full edit
  if (DISPLAY_PREVIEW == 'true') {
	echo "<td class=\"smallText\" align=\"left\"><a href=\"". tep_href_link (FILENAME_CATEGORIES, 'pID='. $products[$x]['products_id'] .'&action=new_product_preview&read=only&sort_by='. $sort_by .'&page='. $split_page .'&origin='. $origin)."\">". tep_image(DIR_WS_IMAGES . 'icon_info.png', TEXT_IMAGE_PREVIEW) ."</a></td>\n";
  } // end if(DISPLAY_PREVIEW == 'true')
  if (DISPLAY_EDIT == 'true') {
      echo "<td class=\"smallText\" align=\"left\"><a href=\"". tep_href_link (FILENAME_CATEGORIES, 'pID='. $products[$x]['products_id'] .'&cPath='. $categories_products[0] .'&action=new_product')."\">". tep_image(DIR_WS_IMAGES . 'icon_arrow_right.png', TEXT_IMAGE_SWITCH_EDIT) ."</a></td>\n";
  } // end if (DISPLAY_EDIT == 'true')
//// Hidden parameters for cache old values
   if (MODIFY_NAME == 'true') {
	echo tep_draw_hidden_field('product_old_name['.$products[$x]['products_id'].'] ',$products[$x]['products_name']);
   } // end if (MODIFY_NAME == 'true')
   if (MODIFY_MODEL == 'true') {
   echo tep_draw_hidden_field('product_old_model['.$products[$x]['products_id'].'] ',$products[$x]['products_model']);
  } // end if (MODIFY_MODEL == 'true')
  echo tep_draw_hidden_field('product_old_ubicacion['.$products[$x]['products_id'].'] ',$products[$x]['products_ubicacion']);
   echo tep_draw_hidden_field('product_old_status['. $products[$x]['products_id'] .']',$products[$x]['products_status']);
   echo tep_draw_hidden_field('product_old_quantity['. $products[$x]['products_id'] .']',$products[$x]['products_quantity']);
    echo tep_draw_hidden_field('product_old_quantity_deseada['. $products[$x]['products_id'] .']',$products[$x]['products_quantity_deseada']);
   echo tep_draw_hidden_field('product_old_image['. $products[$x]['products_id'] .']',$products[$x]['products_image']); 
   if (MODIFY_MANUFACTURER == 'true') {
	   echo tep_draw_hidden_field('product_old_manufacturer['. $products[$x]['products_id'] .']',$products[$x]['manufacturers_id']);
   } // end if (MODIFY_MANUFACTURER == 'true')	   
   echo tep_draw_hidden_field('product_old_weight['. $products[$x]['products_id'] .']',$products[$x]['products_weight']);
   echo tep_draw_hidden_field('product_old_price['. $products[$x]['products_id'] .']',$products[$x]['products_price']);
   echo tep_draw_hidden_field('cg_price_in_db['. $products[$x]['products_id'] .']',$products[$x]['cg_price_in_db']);   
   if (MODIFY_TAX == 'true') {
	   echo tep_draw_hidden_field('product_old_tax['. $products[$x]['products_id'] .']',$products[$x]['products_tax_class_id']);
   } // end if (MODIFY_TAX == 'true')
  } // end for ($x = 0; $x < count($list_of_products_ids); $x++)
  //// hidden display parameters (only once)
        echo tep_draw_hidden_field( 'row_by_page', $row_by_page);
        echo tep_draw_hidden_field( 'sort_by', $sort_by);
        echo tep_draw_hidden_field( 'page', $split_page);
	if (isset($customers_group_id) && $customers_group_id !='') {
	echo tep_draw_hidden_field( 'customers_group_id', $customers_group_id);
	} else {
	echo tep_draw_hidden_field( 'customers_group_id', '0');	
	}

     } // end if (tep_not_null($list_of_products_ids)
    echo "</table>\n";

?>
          </td>
        </tr>
       </table></td>
      </tr>
<tr>
<td align="right">
    <br/>
<?php
                 //// display bottom page buttons
				echo '<a href="javascript:window.print();">' . tep_image_button('button_print.png', PRINT_TEXT) . '</a>&nbsp;&nbsp;';
              echo tep_image_submit('button_update.png', IMAGE_UPDATE);
              echo '&nbsp;&nbsp;<a href="' . tep_href_link(FILENAME_QUICK_UPDATES,"row_by_page=".$row_by_page."&customers_group_id=" . $customers_group_id . "") . '">' . tep_image_button('button_cancel.png', IMAGE_CANCEL) . '</a>';
?><br/><br/></td>
</tr>
<?php echo tep_hide_session_id(); ?>
</form>
            <td><table class="table-page" border="0" width="100%" cellspacing="0" cellpadding="2">
                <td class="smallText" valign="top"><?php echo $products_split->display_count($products_query_numrows, MAX_DISPLAY_ROW_BY_PAGE, $split_page, TEXT_DISPLAY_NUMBER_OF_PRODUCTS);  ?></td>
                <td class="smallText" align="right"><?php echo $products_split->display_links($products_query_numrows, MAX_DISPLAY_ROW_BY_PAGE, MAX_DISPLAY_PAGE_LINKS, $split_page, '&cPath='. $current_category_id .'&sort_by='.$sort_by . '&row_by_page=' . $row_by_page . '&manufacturer=' . $manufacturer . '&customers_group_id=' . $customers_group_id); ?></td>
            </table></td>
          </tr>
        </table></td>
      </tr>
    </table></td>
<!-- body_text_eof //-->
  </tr>
</table>
<!-- body_eof //-->
  </tr>
</table>

<!-- footer //-->
<?php require(THEME . 'html/footer.php'); ?>
<!-- footer_eof //-->
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>