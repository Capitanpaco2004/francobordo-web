<table border="0" width="100%" cellspacing="0" cellpadding="4">
  <tr class="dataTableHeadingRow">
    <td class="dataTableHeadingContent"><?php echo 'Statistics' ?></td>
  </tr>
<?php
$customers_query = tep_db_query("select count(*) as count from " . TABLE_CUSTOMERS);
  $customers = tep_db_fetch_array($customers_query);
  $products_query = tep_db_query("select count(*) as count from " . TABLE_PRODUCTS . " where products_status = '1'");
  $products = tep_db_fetch_array($products_query);
  $reviews_query = tep_db_query("select count(*) as count from " . TABLE_REVIEWS);
  $reviews = tep_db_fetch_array($reviews_query);
	if (STOCK_CHECK == 'true') {
	  $stock_query = tep_db_query('select p.products_quantity from ' . TABLE_PRODUCTS. " p" . " where p.products_quantity <= " . STOCK_REORDER_LEVEL);
  	  $totalRestock = 0;
  	  while($stock = tep_db_fetch_array($stock_query))
 	  {
  		$totalRestock++;
  	  }
   	}
   	else {
           $totalRestock = 'N/A';
       }
  $heading = [];
  $contents = [];
  $contents[] = ['params' => 'class="menuBoxContent"',
                      'text'  => BOX_ENTRY_CUSTOMERS . ' ' . $customers['count'] . '<br>' .
                                 BOX_ENTRY_PRODUCTS . ' ' . $products['count'] . '<br>' .
                                 BOX_ENTRY_REVIEWS . ' ' . $reviews['count'] . '<br>' .
	'<a href="' . tep_href_link(FILENAME_STATS_LOW_STOCK, '', 'NONSSL') . '">' . 'Re-Stock' . '</a>' . ': ' . $totalRestock];
	$box = new box;
  echo '<tr><td>';
  echo $box->menuBox($heading, $contents);  
  echo '</td></tr>';
?>
</table>