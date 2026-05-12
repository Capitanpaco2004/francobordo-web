<?php
/*
  $Id: stats_products.php,v 2.00 2008/04/16 20:30:00 Exp $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2002 osCommerce

  Released under the GNU General Public License
*/

  require('includes/application_top.php');
?>

<?php include( THEME . '/html/header.php' ); ?>

<!-- body //-->
<table border="0" width="100%" cellspacing="3" cellpadding="3">
  <tr>
    <td></td>
<!-- body_text //-->
    <td width="100%" valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="0">
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading">Product Sales and Stats v2.0</td>
            <td class="menuboxheading" align="center"><?php echo date('d/m/Y'); ?></td>
          </tr>

        </table></td>
      </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">

          <tr>
            <td valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr>
                <td class="main"><b><?php echo 'Model'; ?></b></td>
                <td class="main"><?php  if (!isset($orderby) or ($orderby == "name" and $sorted == "ASC"))  $to_sort = "DESC"; else $to_sort = "ASC"; echo '<a href="' . tep_href_link('stats_products.php', 'orderby=name&sorted='. $to_sort) . '" class="main"><b> Products </b></a>';  ?></td>
                <td class="main"><?php  if (!isset($orderby) or ($orderby == "manu" and $sorted == "ASC"))  $to_sort = "DESC"; else $to_sort = "ASC"; echo '<a href="' . tep_href_link('stats_products.php', 'orderby=manu&sorted='. $to_sort) . '" class="main"><b>' . 'Manufacture' . '</b></a>';  ?></td>
                <td class="main"><?php  if (!isset($orderby) or ($orderby == "qty" and $sorted == "ASC"))  $to_sort = "DESC"; else $to_sort = "ASC"; echo '<a href="' . tep_href_link('stats_products.php', 'orderby=qty&sorted='. $to_sort) . '" class="main"><b>' . 'Stock' . '</b></a>';  ?></td>
                <td class="main" align="right"><?php  if (!isset($orderby) or ($orderby == "sku" and $sorted == "ASC"))  $to_sort = "DESC"; else $to_sort = "ASC"; echo '<a href="' . tep_href_link('stats_products.php', 'orderby=sku&sorted='. $to_sort) . '" class="main"><b>' . 'SKU' . '</b></a>';  ?></td>
                <td class="main" align="right"><?php  if (!isset($orderby) or ($orderby == "20" and $sorted == "ASC"))  $to_sort = "DESC"; else $to_sort = "ASC"; echo '<a href="' . tep_href_link('stats_products.php', 'orderby=20&sorted='. $to_sort) . '" class="main"><b>' . '20T' . '</b></a>';  ?></td>
                <td class="main" align="right"><?php  if (!isset($orderby) or ($orderby == "60t" and $sorted == "ASC"))  $to_sort = "DESC"; else $to_sort = "ASC"; echo '<a href="' . tep_href_link('stats_products.php', 'orderby=60t&sorted='. $to_sort) . '" class="main"><b>' . '60T' . '</b></a>';  ?></td>
                <td class="main" align="right"><?php  if (!isset($orderby) or ($orderby == "60s" and $sorted == "ASC"))  $to_sort = "DESC"; else $to_sort = "ASC"; echo '<a href="' . tep_href_link('stats_products.php', 'orderby=60s&sorted='. $to_sort) . '" class="main"><b>' . '60S' . '</b></a>';  ?></td>
                <td class="main" align="right"><?php  if (!isset($orderby) or ($orderby == "120" and $sorted == "ASC"))  $to_sort = "DESC"; else $to_sort = "ASC"; echo '<a href="' . tep_href_link('stats_products.php', 'orderby=120&sorted='. $to_sort) . '" class="main"><b>' . '120T' . '</b></a>';  ?></td>
                <td class="main" align="right"><?php  if (!isset($orderby) or ($orderby == "365" and $sorted == "ASC"))  $to_sort = "DESC"; else $to_sort = "ASC"; echo '<a href="' . tep_href_link('stats_products.php', 'orderby=365&sorted='. $to_sort) . '" class="main"><b>' . '365S' . '</b></a>';  ?></td>
                <td class="main" align="right"><b><?php echo 'Live'; ?></b></td>
  				<td class="main" align="right"><b><?php echo 'Est Stock'; ?></b></td>
              </tr>
              <tr>
                <td colspan="12"><hr></td>
              </tr>
<?php
  if ($_GET['page'] > 1) $rows = $_GET['page'] * 20 - 20;
  if ($orderby == "name") {$db_orderby = "pd.products_name";}
  elseif ($orderby == "manu") {$db_orderby = "suppliername";}
  elseif ($orderby == "sku") {$db_orderby = "p.products_id";}
  elseif ($orderby == "qty") {$db_orderby = "p.products_quantity";}
  elseif ($orderby == "20") {$db_orderby = "ps.products_trans_20";}
  elseif ($orderby == "60t") {$db_orderby = "ps.products_trans_60";}
  elseif ($orderby == "60s") {$db_orderby = "ps.products_sales_60";}
  elseif ($orderby == "120") {$db_orderby = "ps.products_trans_120";}
  elseif ($orderby == "365") {$db_orderby = "ps.products_sales_365";}
  else {$db_orderby = "pd.products_name";}

   $products_query_raw = "select p.products_id, p.products_model, p.products_date_added, ps.products_trans_20, ps.products_trans_60, ps.products_sales_60, ps.products_trans_120, ps.products_sales_365, m.manufacturers_name as suppliername, p.products_model, pd.products_name, p.products_quantity, p.products_price, l.name from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_YEARLY_SALES . " ps, " . TABLE_PRODUCTS_DESCRIPTION . " pd, " . TABLE_MANUFACTURERS . " m, " . TABLE_LANGUAGES . " l where p.products_id = pd.products_id and p.products_id = pd.products_id and ps.products_id = p.products_id and l.languages_id = pd.language_id group by p.products_id order by $db_orderby $sorted";
   $products_split = new splitPageResults($_GET['page'], 70, $products_query_raw, $products_query_numrows);
   $products_query = tep_db_query($products_query_raw);
  while ($products = tep_db_fetch_array($products_query)) {
    $rows++;

    if (strlen($rows) < 2) {
      $rows = '0' . $rows;
    }

$products_id = $products['products_id'];


// Calculating days stock
$SalesPerDay = '';
if ($products['products_quantity'] > 0) {
	$StockOnHand = $products['products_quantity'];
	if ($products['products_trans_60'] > 0) {
		$SalesPerDay = $products['products_sales_60'] / 60;
		round ($SalesPerDay, 2);
		$daysSupply = 0;
		$daysSupply = $StockOnHand / $SalesPerDay;
	} else {
	$daysSupply = '+60 '. DAY;
	}

	round($daysSupply);
	if ($daysSupply <= '20') {
	  $daysSupply = '<b>' . round($daysSupply) . ' ' . DAY . '</b>';
	} else {
	  $daysSupply = round ($daysSupply) .' ' . DAY;
	}
	if ($SalesPerDay == 0) {
	  $daysSupply = '+60 '. DAY;
	}

} else {
$daysSupply = '<b>NA</b>';
}

// Make negative qtys bold b/c people have backordered them!
if (($products['products_quantity'] < 0) && ($products['products_quantity'] > -10000)) {
  $productsQty = '<b>' . $products['products_quantity'] . '</b>';
} else {
  $productsQty = $products['products_quantity'];
}

// Find out how long ago the product was added
 $then = strtotime($products['products_date_added']);
 $diff = time() - $then;
 $days_old = floor($diff/(60*60*24));
?>

              <tr class="tableRow">

				<td class="list" valign="top"><?php echo $products['products_model']; ?></td>
                <td class="list" valign="top"><?php echo substr($products['products_name'], 0, 35); ?></td>
				<td class="list" valign="top"><?php echo substr($products['suppliername'], 0, 20); ?></td>
                <td class="list" valign="top"><?php echo $productsQty; ?></td>
				<td class="list" align="right" valign="top"><?php echo $products['products_id']; ?></td>
                <td class="list" align="right" valign="top"><?php echo $products['products_trans_20']; ?></td>
                <td class="list" align="right" valign="top"><?php echo $products['products_trans_60']; ?></td>
                <td class="list" align="right" valign="top"><?php echo $products['products_sales_60']; ?></td>
                <td class="list" align="right" valign="top"><?php echo $products['products_trans_120']; ?></td>
                <td class="list" align="right" valign="top"><?php echo $products['products_sales_365']; ?></td>
                <td class="list" align="right" valign="top"><?php echo $days_old; ?></td>
                <td class="list" align="right" valign="top"><?php echo $daysSupply; ?></td>
              </tr>
<?php
  }
?>
              <tr>
                <td colspan="12"><?php echo tep_draw_separator(); ?></td>
              </tr>
            </table></td>

          <tr>
            <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr>
                <td class="smallText" valign="top"><?php echo $products_split->display_count($products_query_numrows, 70, $_GET['page'], TEXT_DISPLAY_NUMBER_OF_PRODUCTS); ?></td>
                <td class="smallText" align="right"><?php echo $products_split->display_links($products_query_numrows, 70, MAX_DISPLAY_PAGE_LINKS, $_GET['page'], "orderby=" . $orderby . "&sorted=" . $sorted); ?>&nbsp;</td>
              </tr>
            </table></td>
          </tr>
        </table></td>
      </tr>
    </table></td>
<!-- body_text_eof //-->
  </tr>
</table>
<?php include( THEME . '/html/footer.php' ); ?>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>