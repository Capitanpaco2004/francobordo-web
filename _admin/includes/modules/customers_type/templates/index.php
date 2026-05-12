<div class="oeBox oeTable column a12 row ax">
	<div class="oeWrpr">
		<div class="oeTitu"><i class="fa fa-eye"></i> <?php echo CUSTOMERS_TYPE_TITLE ?></div>
		<form method="post" action="<?php echo tep_href_link( $sUrlPage ) ?>" class="oeCntd row ax">
			<table class="xform">
				<thead>
					<tr>
						<th width="17" class="chck"><input type="checkbox" id="all_check"/><label for="all_check"><span></span></label></th>
						<th>ID</th>
						<th width="65%"><?php echo CUSTOMERS_TYPE_TABLE_TYPE ?></th>
						<th><?php echo CUSTOMERS_TYPE_TABLE_COLOR ?></th>
						<th width="125"><?php echo CUSTOMERS_TYPE_TABLE_ACTIONS ?></th>
					</tr>
				</thead>
				<tbody>
				<?php while( $type = tep_db_fetch_array( $aRows ) ): ?>
					<tr data-dblclick="<?php echo tep_href_link( $sUrlPage, 'action=crud&id=' . $type['id_customers_type'] ) ?>">
						<td class="chck" align="center"><input type="checkbox" id="id_<?php echo $type['id_customers_type'] ?>" name="id[]" value="<?php echo $type['id_customers_type'] ?>"/><label for="id_<?php echo $type['id_customers_type'] ?>"><span></span></label></td>
						<td><?php echo $type['id_customers_type'] ?></td>
						<td><?php echo $type['nombre'] ?></td>
						<td><?php echo ($type['color'] == '' ? '-' : '<span style="background: ' . $type['color'] . '; height: 20px; width: 50px; position: relative; top: 1px; display: inline-block;"></span>') ?></td>
						<td>
							<div class="drop xfselect">
								<div><?php echo CUSTOMERS_TYPE_TABLE_ACTIONS ?></div>
								<ul class="down down-dngt">
									<li><a href="<?php echo tep_href_link( $sUrlPage, 'action=crud&id=' . $type['id_customers_type'] ) ?>" class="hv"><i class="fa fa-pencil"></i><?php echo TEXT_EDIT ?></a></li>
									<li><a data-confirm="<?php echo TEXT_DELETES_CONFIRM ?>" href="<?php echo tep_href_link( $sUrlPage, 'action=delete&id=' . $type['id_customers_type'] ) ?>" class="hv"><i class="fa fa-trash"></i><?php echo TEXT_DELETE ?></a></li>
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
