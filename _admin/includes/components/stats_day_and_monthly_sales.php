<?php
//Comprobamos si el administrador tiene permisos para ver estadisticas
if( tep_admin_check_boxes( 'reports.php' ) )
{
	?>
	<?php
	// Mostramos las ventas del día Actual
	$aTotalsToday = tep_db_query( 'SELECT o.payment_method, sum(ot.value) as total
										   FROM orders as o
										   INNER JOIN orders_total as ot ON o.orders_id = ot.orders_id AND ot.class = "ot_total"
										   AND o.date_purchased >= "' . date('Y-m-d') . ' 00:00:00"
										   AND o.date_purchased <= "' . date('Y-m-d') . ' 23:59:59"
										   GROUP BY o.payment_method
										   ORDER BY payment_method DESC' );
	?>
	<div style="width: 48%; float: left;margin-right: 3%;">
		<div class="pageHeading"><?php echo DASHBOARD_SELLS_TODAY; ?></div>
		<table width="100%" cellspacing="2" cellpadding="3" border="0" style="margin-bottom: 20px;">
			<tr class="dataTableHeadingRow">
				<td class="dataTableHeadingContent"><strong><?php echo DASHBOARD_SELLS_PAYMENT_METHOD; ?></strong></td>
				<td class="dataTableHeadingContent" align="right"><strong><?php echo DASHBOARD_SELLS_TOTAL; ?></strong></td>
			</tr>
			<?php
			if( tep_db_num_rows( $aTotalsToday ) > 0 )
			{
				$nCont = 0;
				$nTotal = 0;
				while( $aTotalToday = tep_db_fetch_array( $aTotalsToday ) )
				{
					$sClass = ($nCont % 2 !== 0 ? 'dataTableRowSelected' : 'dataTableRowOver');
					echo '<tr class="dataTableContent">';
					echo '<td class="' . $sClass . '" style="height: 25px">' . $aTotalToday['payment_method'] . '</td>';
					echo '<td class="' . $sClass . '" style="height: 25px" align="right">' . number_format( $aTotalToday['total'], 2, '.', ' ' ) . '</td>';
					echo '</tr>';
					++$nCont;
					$nTotal += $aTotalToday['total'];
				}

				$sClass = ($nCont % 2 !== 0 ? 'dataTableRowSelected' : 'dataTableRowOver');
				echo '<tr class="dataTableContent">';
				echo '<td class="' . $sClass . '" style="height: 25px"><b>' . DASHBOARD_SELLS_TOTAL . '</b></td>';
				echo '<td class="' . $sClass . '" style="height: 25px" align="right"><b>' . number_format( $nTotal, 2, '.', ' ' ) . '</b></td>';
				echo '</tr>';
			}else{
				echo '<tr class="dataTableContent"><td class="dataTableRowSelected" style="height: 25px;text-align:center">' . DASHBOARD_SELLS_NO_SELLS . '</td><td class="dataTableRowSelected" align="right">0,00€</td></tr>';
			}
			?>
		</table>
	</div>
	<?php
	// Mostramos las Ventas del mes Actual
	$aTotalsMonth = tep_db_query( 'SELECT o.payment_method, sum(ot.value) as total
										   FROM orders as o
										   INNER JOIN orders_total as ot ON o.orders_id = ot.orders_id AND ot.class = "ot_total"
										   AND o.date_purchased >= "' . date('Y-m') . '-01 00:00:00"
										   AND o.date_purchased <= "' . date('Y-m') . '-31 23:59:59"
										   GROUP BY o.payment_method
										   ORDER BY payment_method DESC' );
	date_default_timezone_set("Europe/Madrid");
	setlocale(LC_TIME, substr((string) $language, 0, 2) . '_' . strtoupper(substr((string) $language, 0, 2)));
	?>
	<div style="width: 48%; float: left;">
		<div class="pageHeading">
			<?php
			$date = new DateTime();
			$formatter = new IntlDateFormatter(
				'es_ES',
				IntlDateFormatter::NONE,
				IntlDateFormatter::NONE,
				null,
				null,
				'MMMM yyyy'
			);
			echo DASHBOARD_SELLS_TEXT . ' ' . $formatter->format($date);
			?>
		</div>
		<table width="100%" cellspacing="2" cellpadding="3" border="0" style="margin-bottom: 20px;">
			<tr class="dataTableHeadingRow">
				<td class="dataTableHeadingContent"><strong><?php echo DASHBOARD_SELLS_PAYMENT_METHOD; ?></strong></td>
				<td class="dataTableHeadingContent" align="right"><strong><?php echo DASHBOARD_SELLS_TOTAL; ?></strong></td>
			</tr>
			<?php
			if( tep_db_num_rows( $aTotalsMonth ) > 0 )
			{
				$nCont = 0;
				$nTotal = 0;
				while( $aTotalMonth = tep_db_fetch_array( $aTotalsMonth ) )
				{
					$sClass = ($nCont % 2 !== 0 ? 'dataTableRowSelected' : 'dataTableRowOver');
					echo '<tr class="dataTableContent">';
					echo '<td class="' . $sClass . '" style="height: 25px">' . $aTotalMonth['payment_method'] . '</td>';
					echo '<td class="' . $sClass . '" style="height: 25px" align="right">' . number_format( $aTotalMonth['total'], 2, '.', ' ' ) . '</td>';
					echo '</tr>';
					++$nCont;
					$nTotal += $aTotalMonth['total'];
				}
				$sClass = ($nCont % 2 !== 0 ? 'dataTableRowSelected' : 'dataTableRowOver');
				echo '<tr class="dataTableContent">';
				echo '<td class="' . $sClass . '" style="height: 25px"><b>' . DASHBOARD_SELLS_TOTAL . '</b></td>';
				echo '<td class="' . $sClass . '" style="height: 25px" align="right"><b>' . number_format( $nTotal, 2, '.', ' ' ) . '</b></td>';
				echo '</tr>';
			}else{
				echo '<tr class="dataTableContent"><td class="dataTableRowSelected" style="height: 25px;text-align:center">' . DASHBOARD_SELLS_NO_SELLS_MONTH . '</td><td class="dataTableRowSelected" align="right">0,00€</td></tr>';
			}
			?>
		</table>
	</div>
	<div style="clear: both;"></div>
<?php } ?>
