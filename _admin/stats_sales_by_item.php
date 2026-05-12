<?php
/*
  $Id: stats_products_purchased.php 1739 2007-12-20 00:52:16Z hpdl $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
*/

  require('includes/application_top.php');
   require(DIR_WS_CLASSES . 'currencies.php');
  $currencies = new currencies();
  if ($_POST['method'])
	{
		$method_type =$_POST['method'];
		
	}
	
  
?>

<?php require(THEME . 'html/header.php'); ?>


<!-- body //-->
<table border="0" width="100%" cellspacing="2" cellpadding="2">
  <tr>
<!-- body_text //-->
    <td width="100%" valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo 'Product sales by item'; ?></td>
          </tr>
		  <!--<tr>
            <td ><?php echo "<b>Please Select the product</b>"; ?></td>
            <td align="left"> 
						<form name="payment_method"  method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
					 <select id="method" name="method" size="1" onchange="form.submit()">
					<option>-----Select ---- </option>
					<? for($i=0;$i<sizeof($pay_method);$i++){?>
					<option><?= $pay_method[$i]; ?> </option>
					
					<? } ?>
					</select>
					</form>
		</td>
          </tr>-->
        </table></td>
      </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr class="dataTableHeadingRow">
                <td class="dataTableHeadingContent">No</td>
                <td class="dataTableHeadingContent">Productos</td>
				 <td class="dataTableHeadingContent" align="center">
				 <?php
				 if (isset($_GET['sort']) && ($_GET['sort'] =='howmany_tickets'))
				$sort1='sort=howmany_tickets desc';
				 elseif (isset($_GET['sort']) && ($_GET['sort'] =='howmany_tickets desc'))
				$sort1='sort=howmany_tickets';
				else
				$sort1='sort=howmany_tickets';
				?>				
				<a href="<?php echo tep_href_link('stats_sales_by_item.php',$sort1, 'NONSSL'); ?>" > <?php echo 'Qty Sold'; ?>&nbsp;</a>
				 </td>
                <td class="dataTableHeadingContent" align="right">
				<?php
				 if (isset($_GET['sort']) && ($_GET['sort'] =='ordersum'))
				$sort='sort=ordersum desc';
				 elseif (isset($_GET['sort']) && ($_GET['sort'] =='ordersum desc'))
				$sort='sort=ordersum';
				else
				$sort='sort=ordersum';
				?>				
				<a href="<?php echo tep_href_link('stats_sales_by_item.php',$sort, 'NONSSL'); ?>" > <?php echo 'Total amount'; ?>&nbsp;</a></td>
              </tr>
<?php
if (isset($_GET['sort']) && !empty($_GET['sort']))
 $ORDER_BY=$_GET['sort'];
else
$ORDER_BY='ordersum';

if (isset($_GET['page']) && ($_GET['page'] > 1)) $rows = $_GET['page'] * MAX_DISPLAY_SEARCH_RESULTS - MAX_DISPLAY_SEARCH_RESULTS;
$products_query_raw="select p.products_id, p.products_name,count(op.products_quantity) as howmany_tickets , sum(op.products_quantity * op.final_price) as ordersum from  products_description  p , orders_products op where op.products_id = p.products_id  group by p.products_name order by $ORDER_BY";

  $rows = 0;
  $products_query = tep_db_query($products_query_raw);
  
  
  while ($products = tep_db_fetch_array($products_query)) {
    $rows++;
	//print_r($products);

    if (strlen($rows) < 2) {
      $rows = '0' . $rows;
    }
?>
              <tr class="dataTableRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href='<?php echo tep_href_link(FILENAME_CATEGORIES, 'action=new_product_preview&read=only&pID=' . $products['products_id'] . '&origin=' . FILENAME_STATS_PRODUCTS_PURCHASED . '?page=' . $_GET['page'], 'NONSSL'); ?>'">
                <td class="dataTableContent"><?php echo $rows; ?>.</td>
                <td class="dataTableContent"><?php echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'action=new_product_preview&read=only&pID=' . $products['products_id'] . '&origin=' . FILENAME_STATS_PRODUCTS_PURCHASED . '?page=' . $_GET['page'], 'NONSSL') . '">' . $products['products_name'] . '</a>'; ?></td>
             
				<td class="dataTableContent" align="center"><?php echo $products['howmany_tickets']; ?>&nbsp;</td>
				<td class="dataTableContent" align="right"><?php echo $currencies->display_price($products['ordersum'],'');?>&nbsp;</td>
              </tr>
<?php
 
 $total_val= $total_val + $products['ordersum'];
 
  }
?>
            </table></td>
          </tr>
          <tr>
            <td colspan="3"><table border="0" width="100%" cellspacing="0" cellpadding="2">
              
			  <tr>
                <td class="smallText" valign="top">	</td>
                <td class="smallText" align="right">&nbsp;</td>
              </tr>
			  <tr>
                <td class="smallText" valign="top">	</td>
                <td class="smallText" align="right"> <b >Total Sales: <?php echo $currencies->display_price($total_val,'');?></b>&nbsp;</td>
              </tr>

			  <tr>
                <td class="smallText" valign="top">
				<?php //echo $products_split->display_count($products_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, $_GET['page'], TEXT_DISPLAY_NUMBER_OF_PRODUCTS); ?></td>
                <td class="smallText" align="right"><?php //echo $products_split->display_links($products_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, MAX_DISPLAY_PAGE_LINKS, $_GET['page']); ?>&nbsp;</td>
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
