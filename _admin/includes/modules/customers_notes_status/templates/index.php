<div class="oeBox oeTable column a12 row ax">
	<div class="oeWrpr">
		<div class="oeTitu"><i class="fa fa-eye"></i> <?php echo CUSTOMERS_NOTES_STATUS_TITLE ?></div>
		<form method="post" action="<?php echo tep_href_link( $sUrlPage ) ?>" class="oeCntd row ax">
			<table class="xform">
				<thead>
					<tr>
						<th width="17" class="chck"><input type="checkbox" id="all_check"/><label for="all_check"><span></span></label></th>
						<th>ID</th>
						<th width="65%"><?php echo CUSTOMERS_NOTES_STATUS_TABLE_STATUS ?></th>
						<th><?php echo CUSTOMERS_NOTES_STATUS_TABLE_COLOR ?></th>
						<th width="125"><?php echo CUSTOMERS_NOTES_STATUS_TABLE_ACTIONS ?></th>
					</tr>
				</thead>
				<tbody>
				<?php while( $status = tep_db_fetch_array( $aRows ) ): ?>
					<tr data-dblclick="<?php echo tep_href_link( $sUrlPage, 'action=crud&id=' . $status['id_customers_notes_status'] ) ?>">
						<td class="chck" align="center"><input type="checkbox" id="id_<?php echo $status['id_customers_notes_status'] ?>" name="id[]" value="<?php echo $status['id_customers_notes_status'] ?>"/><label for="id_<?php echo $status['id_customers_notes_status'] ?>"><span></span></label></td>
						<td><?php echo $status['id_customers_notes_status'] ?></td>
						<td><?php echo $status['customers_notes_status'] ?></td>
						<td><?php echo ($status['customers_notes_color'] == '' ? '-' : '<span style="background: ' . $status['customers_notes_color'] . '; height: 20px; width: 50px; position: relative; top: 1px; display: inline-block;"></span>') ?></td>
						<td>
							<div class="drop xfselect">
								<div><?php echo CUSTOMERS_NOTES_STATUS_TABLE_ACTIONS ?></div>
								<ul class="down down-dngt">
									<li><a href="<?php echo tep_href_link( $sUrlPage, 'action=crud&id=' . $status['id_customers_notes_status'] ) ?>" class="hv"><i class="fa fa-pencil"></i><?php echo TEXT_EDIT ?></a></li>
									<li><a data-confirm="<?php echo TEXT_DELETES_CONFIRM ?>" href="<?php echo tep_href_link( $sUrlPage, 'action=delete&id=' . $status['id_customers_notes_status'] ) ?>" class="hv"><i class="fa fa-trash"></i><?php echo TEXT_DELETE ?></a></li>
								</ul>
							</div>
						</td>
					</tr>
				<?php endwhile; ?>
				</tbody>
			</table>

			<? echo $aRowsSplit->showPaginateTable( tep_get_all_get_params( ['page'] ), 'page', $sHtmlActionMasivo, 'solenopsis' ); ?>

			</div>
		</form>
	</div>
</div>
