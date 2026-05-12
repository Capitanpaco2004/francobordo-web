<?php
/*
  $Id: payment_method_report.php, v1 11/06/2008 delete13 Exp $
  modified to multilanguagesupport and other modifications by clasf 20090126
  
  This script is not included in the original version of osCommerce

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
*/


require('includes/application_top.php') ; ?>

<?php include( THEME . '/html/header.php' ); ?>

<table border="0" width="100%" cellspacing="2" cellpadding="2">
    <tr>

<!-- body_text //-->
    <td width="100%" valign="top"align="center"><table border="0" width="70%" cellspacing="0" cellpadding="2">
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo HEADING_TITLE; ?><hr></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><table border="0" cellpadding="4" width="100%">
          <tr>
<?php                

// --------------------------------------------------------------------------------------------- //

$payment_method_query_raw = '
	SELECT monthname(o.date_purchased) 
			row_month, year(o.date_purchased) row_year, o.payment_method, sum(ot.value) total,month(o.date_purchased) i_month, o.orders_status
                FROM orders as o
                LEFT JOIN orders_total as ot
                ON o.orders_id = ot.orders_id
                AND ot.class = "ot_total"
                GROUP BY o.payment_method, month(o.date_purchased), row_year ORDER BY row_year DESC, i_month DESC, payment_method
                ;' ;


$payment_method_query 	= tep_db_query($payment_method_query_raw);

$detail_field 	= '<tr class=dataTableContent><td class="%s">%s</td><td class="%s">%s</td><td class="%s">%s</td><td class="%s" align="right">%s</td></tr>' ; // Line detail format
$total_field  	= '<tr><td></td><td></td><td class=dataTableContent align="right"><strong>%s%s</strong></td><td class=dataTableContent align="right"><strong>%s</strong></td></tr><tr><td></td></tr>' ; // Total line format (by year)
$subtotal_field = '<tr class=dataTableHeadingRow><td class=dataTableHeadingContent></td><td class=dataTableHeadingContent></td><td class=dataTableHeadingContent align="right">%s%s</td><td class=dataTableHeadingContent align="right"><strong>%s</strong></td></tr>' ; // Subtotal line format (by month)

// Header 
//
$header = sprintf('<tr class=dataTableHeadingRow><td class=dataTableHeadingContent><strong>%s</strong></td><td class=dataTableHeadingContent><strong>%s</strong></td><td class=dataTableHeadingContent><strong>%s</strong></td><td class=dataTableHeadingContent align="center"><strong>%s</strong></td></tr>', PAYMENT_METHOD_REPORT_HEADER_YEAR, PAYMENT_METHOD_REPORT_HEADER_MONTH, PAYMENT_METHOD_REPORT_HEADER_METHOD, PAYMENT_METHOD_REPORT_HEADER_TOTAL) ;

echo '<table width="100%" border="0" cellspacing="2" cellpadding="3">' ; 
echo $header ; 

// Report loop
//
for( $i = 0 ;  $detail_line = tep_db_fetch_array($payment_method_query ) ; $i++) {
	
	$year  	= $detail_line['row_year'] ;
	$month 	= ucfirst(date("F", mktime(1,1,1, $detail_line['i_month'], 1, $detail_line['row_year']))) ;
	$method = $detail_line['payment_method'] ;
	$total	= $detail_line['total'] ;
  
	// Displaying subtotal (month report)
	//
	if ( $previous_month != $month && $i )
	{
			printf($subtotal_field, PAYMENT_METHOD_REPORT_TOTAL_MONTH, $previous_month, number_format($sub_total, 2, '.', ' ')) ;
			$sub_total = 0 ;
	}	
	// Displaying total (year report)
	//
	if ( $previous_year != $year && $i )
	{
		printf($total_field, PAYMENT_METHOD_REPORT_TOTAL_YEAR, $previous_year, number_format($grand_total, 2, '.', ' ')) ;

		$grand_total = 0 ;

		if ( ! $detail_line )	break ; // FIN DU TABLEAU
	
		echo $header ; 
	}
	
	// Displays the month one time
	//
	if 		( $previous_month == $month && $i )
	{
		$show_mounth = '' ;
	}
	else $show_mounth = $month ;
	
	// Displays the year one time
	//
	if 		( $previous_year == $year && $i )
	{
		$show_year = '' ; 
	}
	else $show_year = $year ;
	
	// Detail line result (display Y/M, total sales amount per payment method)
	//
	$class = $i % 2 ? "dataTableRowSelected" : "dataTableRowOver" ; 
	printf($detail_field, $class, $show_year, $class, $show_mounth , $class, $method, $class, number_format($total, 2, '.', ' ') ) ;
	
	$grand_total  += $total ;  
	$sub_total 	  += $total ;
	$previous_month= $month ;
	$previous_year = $year ;
}
echo '</table>' ; 

// --------------------------------------------------------------------------------------------- //
?>
</td>
</tr>
</table>
<!-- body_text_eof //-->
</td>
</tr>
</table>
<!-- body_eof //-->

<?php include( THEME . '/html/footer.php' ); ?>
</body>
