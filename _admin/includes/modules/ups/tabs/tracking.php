<?php
$orders_states_list = tep_get_orders_status();

$orderStatusesTracked 	= explode(';', Tools::getConfigValue('MODULE_SHIPPING_UPS_ORDER_STATUS_TRACKED'));
$deliveredOrderStatus	= Tools::getConfigValue('MODULE_SHIPPING_UPS_DELIVERED_ORDER_STATUS');
$inTransitOrderStatus 	= Tools::getConfigValue('MODULE_SHIPPING_UPS_IN_TRANSIT_ORDER_STATUS');
$trackingBy			 	= Tools::getConfigValue('MODULE_SHIPPING_UPS_TRACKING_BY');
$cronLink 				= Tools::getConfigValue('MODULE_SHIPPING_UPS_CRON_LINK');

?>
<div class="tab-content<?php if($activeTab == 'tracking') echo ' active'; ?>" id="tracking">				
	<form action="ups_configure.php?action=updateTrackingSettings" method="POST">
		<fieldset>
			<legend><?php echo UPS_TRACKING_LABEL; ?></legend>
			<table border="0" width="100%" cellspacing="0" cellpadding="2">
				<tr>
					<td valign="top">
						<p><?php echo UPS_ORDER_STATUS_BEING_TRACKED; ?></p>
					</td>
					<td>
						<table id="trackingOrderStatesList" border="0" width="100%" cellspacing="0" cellpadding="2">			
						<?php foreach($orders_states_list as $key => $order_state){ ?>
							<tr class="row<?php echo $key % 2; ?>">
								<td><?php echo $order_state['text']; ?></td>
								<td><?php echo tep_draw_checkbox_field('order_state_tracked[]', $order_state['id'], (in_array($order_state['id'], $orderStatusesTracked)) ? 1 : 0); ?></td>
							</tr>
						<?php } ?>
						</table>
					</td>
				</tr>
				<tr>
					<td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
				</tr>				
				<tr>
					<td colspan="2">
						<h3><?php echo UPS_TRACKING_RESULTS; ?></h3>
					</td>
				</tr>
				<tr>
					<td class="main" valign="top"><label for="ups_delivered_order_status"><?php echo UPS_DELIVERED_ORDER_STATUS; ?></label></td>
					<td class="main"><?php echo tep_draw_pull_down_menu('ups_delivered_order_status', $orders_states_list, $deliveredOrderStatus, ' class="ups_field_large"'); ?></td>
				</tr>
				<tr>
					<td class="main" valign="top"><label for="ups_in_transit_order_status"><?php echo UPS_IN_TRANSIT_ORDER_STATUS; ?></label></td>
					<td class="main"><?php echo tep_draw_pull_down_menu('ups_in_transit_order_status', $orders_states_list, $inTransitOrderStatus, ' class="ups_field_large"'); ?></td>
				</tr>
				<tr>
					<td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
				</tr>				
				<tr>
					<td colspan="2">
						<h3><?php echo UPS_TRACKING_BY; ?></h3>
					</td>
				</tr>
				<tr>
					<td class="main">
						<?php echo tep_draw_radio_field('tracking_by', '0', ((!$trackingBy)? 1 : 0)); ?>
						<label for="tracking_by"><?php echo UPS_VISITOR_ACTION; ?></label>
					</td>
				</tr>
				<tr>
					<td class="main" colspan="2">
						<?php echo tep_draw_radio_field('tracking_by', '1', (($trackingBy)? 1 : 0)); ?>
						<label for="tracking_by"><?php echo UPS_CRON; ?></label>
					</td>
				</tr>
				<tr>
					<td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
				</tr>				
				<tr id="cron_link_row">
					<td class="main" valign="top"><label for="cron_link"><?php echo UPS_CRON_LINK; ?></label></td>
					<td class="main"><?php echo tep_draw_input_field('cron_link', $cronLink, ' id="cron_link" style="width:100%;"'); ?></td>
				</tr>
				<tr>
					<td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
				</tr>
				<tr>
					<td colspan="2" class="right"><input class="ups-btn" type="submit" value="<?php echo UPS_UPDATE_TRACKING; ?>" /></td>
				</tr>					
			</table>
		</fieldset>
	</form>
</div>