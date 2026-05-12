<div class="oeBox oeTable column a12 row ax">
	<div class="oeWrpr">
		<div class="oeTitu"><i class="fa fa-eye"></i> <?php echo TABLE_HEADING_ORDERS_STATUS ?></div>
		<form method="post" action="<?php echo tep_href_link( $sUrlPage ) ?>" class="oeCntd row ax">
			<table class="xform">
				<thead>
					<tr>
						<th width="17" class="chck"><input type="checkbox" id="all_check"/><label for="all_check"><span></span></label></th>
						<th><?php echo TABLE_HEADING_ORDERS_STATUS ?></th>
						<th><?php echo TABLE_HEADING_PUBLIC_STATUS ?></th>
						<?php
						foreach( $aLanguages as $aLanguage ) {
							echo '<th>' . $aLanguage['name'] . '</th>';
						}
						?>
						<th><?php echo ORDERS_STATUS_TEXT_ORDER ?></th>
						<th><?php echo ORDERS_STATUS_TEXT_COLOR ?></th>
						<th width="125"><?php echo TABLE_HEADING_ACTION ?></th>
					</tr>
				</thead>
				<tbody>
				<?php while( $status = tep_db_fetch_array( $aRows ) ): ?>
					<tr data-dblclick="<?php echo tep_href_link( $sUrlPage, 'action=crud&id=' . $status['orders_status_id'] ) ?>">
						<td class="chck" align="center"><input type="checkbox" id="id_<?php echo $status['orders_status_id'] ?>" name="id[]" value="<?php echo $status['orders_status_id'] ?>"/><label for="id_<?php echo $status['orders_status_id'] ?>"><span></span></label></td>
						<td><?php echo $status['orders_status_name'] . (DEFAULT_ORDERS_STATUS_ID == $status['orders_status_id'] ? ' (' . TEXT_DEFAULT . ')' : '') ?></td>
						<td><?php echo tep_image(DIR_WS_IMAGES . 'icons/' . (($status['public_flag'] == '1') ? 'tick.gif' : 'cross.gif')) ?></td>
						<?php
						foreach( $aLanguages as $aLanguage ) {
							echo '<td>' . tep_get_orders_status_name($status['orders_status_id'], $aLanguage['id']) . '</td>';
						}
						?>
						<td><?php echo $status['sort_order'] ?></td>
						<td>
							<div style="background-color: <?php echo $status['color'] ?>;height: 20px;width: 20px;display: inline-block;border-radius: 3px;"></div>
						</td>
						<td>
							<div class="drop xfselect">
								<div><?php echo TABLE_HEADING_ACTION ?></div>
								<ul class="down down-dngt">
									<li><a href="<?php echo tep_href_link( $sUrlPage, 'action=crud&id=' . $status['orders_status_id'] ) ?>" class="hv"><i class="fa fa-pencil"></i><?php echo TEXT_EDIT ?></a></li>
									<li><a data-confirm="<?php echo TEXT_DELETES_CONFIRM ?>" href="<?php echo tep_href_link( $sUrlPage, 'action=delete&id=' . $status['orders_status_id'] ) ?>" class="hv"><i class="fa fa-trash"></i><?php echo TEXT_DELETE ?></a></li>
								</ul>
							</div>
						</td>
					</tr>
				<?php endwhile; ?>
				</tbody>
			</table>

			<?php echo $aRowsSplit->showPaginateTable( tep_get_all_get_params( ['page'] ), 'page', $sHtmlActionMasivo ?? '', 'solenopsis' ); ?>

			</div>
		</form>
	</div>
</div>
