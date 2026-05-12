<?php
	require_once(DIR_WS_MODULES . 'ups/classes/UpsExports.php');
	
	$exportable_orders = UpsExports::getExportablesOrders();
	$past_orders = UpsExports::getPastsOrders();
?>
<div class="tab-content<?php if($activeTab == 'shipping') echo ' active'; ?>" id="shipping">				
	<form action="ups_configure.php?action=edit" method="POST">
		<ul class="shipping-nav-tabs">
			<li><a href="#shipping1" class="active"><?php echo UPS_EXPORTABLE_ORDERS; ?></a></li>
			<li><a href="#shipping2"><?php echo UPS_PAST_EXPORTS; ?></a></li>								
		</ul>
		<span class="msg"></span>			
		<div class="shipping-tab-content active" id="shipping1">
			<table border="0" width="100%" cellspacing="0" cellpadding="2">
				<tr>
					<th><?php echo UPS_ID_ORDER; ?></th>
					<th><?php echo UPS_COUNTRY; ?></th>
					<th><?php echo UPS_SERVICE; ?></th>
					<!-- <th><?php echo UPS_ACTION; ?></th> -->
				</tr>				
				<?php 
				if(!empty($exportable_orders)) {
					foreach($exportable_orders as $key => $order){ ?>
				<tr class="row<?php echo $key % 2; ?>">
					<td class="center"><?php echo $order['orders_id']; ?></td>
					<td class="center"><?php echo $order['delivery_country']; ?></td>
					<td class="center"><?php echo $order['service_name']; ?></td></td>
					<!-- <td class="center"></td> -->
				</tr>
				<?php }				
				} 
				else { ?>
				<tr><td colspan="4"><center><?php echo UPS_SHIPPING_NO_EXPORTABLE_ORDER; ?></center></td></tr>
				<?php } ?>		
			</table>
			<?php if(!empty($exportable_orders)) { ?>
			<p><a href="<?php echo tep_href_link('ups_configure.php', '&action=export_worldship', 'NONSSL'); ?>" target="_blank" class="ups-btn export"><?php echo UPS_EXPORT_FILE_FOR_WORLDSHIP; ?></a></p>
			<p><a href="<?php echo tep_href_link('ups_configure.php', '&action=export_ups', 'NONSSL'); ?>" target="_blank" class="ups-btn export"><?php echo UPS_EXPORT_FILE_FOR_UPS; ?></a></p>
			<p><a href="<?php echo tep_href_link('ups_configure.php', '&action=export_pdf', 'NONSSL'); ?>" target="_blank" class="ups-btn export"><?php echo UPS_EXPORT_PDF_SHIPPING_MARK; ?></a></p>
			<!-- <p><label for="ups_import_tracking"><?php echo UPS_IMPORT_TRACKING; ?></label><?php echo tep_draw_input_field('ups_import_tracking', $ups_import_tracking, '', false, 'file'); ?></p>	 -->		
			<?php } ?>
		</div>
		
		<div class="shipping-tab-content" id="shipping2">
			<table border="0" width="100%" cellspacing="0" cellpadding="2">
				<tr>
					<th><?php echo UPS_ID_ORDER; ?></th>
					<th><?php echo UPS_COUNTRY; ?></th>
					<th><?php echo UPS_SERVICE; ?></th>
					<!-- <th><?php echo UPS_ACTION; ?></th> -->
				</tr>
				<?php 
				if(!empty($past_orders)) {
					foreach($past_orders as $key => $order){ ?>
				<tr class="row<?php echo $key % 2; ?>">
					<td class="center"><?php echo $order['orders_id']; ?></td>
					<td class="center"><?php echo $order['delivery_country']; ?></td>
					<td class="center"><?php echo $order['service_name']; ?></td></td>
					<!-- <td class="center"></td> -->
				</tr>
				<?php }					
				} else { ?>
				<tr><td colspan="4"><center><?php echo UPS_SHIPPING_NO_PAST_ORDER; ?></center></td></tr>
				<?php } ?>			
			</table>	
		</div>
	</form>
</div>