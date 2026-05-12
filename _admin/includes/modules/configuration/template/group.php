<?php
use util\tools;

echo $messageStack->show(['text' => CONFIGURATION_WARNING_INFO, 'class' => 'info']);

// Mensajes comprobamos si tenemos datos
if( tep_db_num_rows( $aRows ) <= 0 )
{
	if ($sWhere != '') {
        echo $messageStack->show( [ 'text' => CONFIGURATION_FILTER_NO_DATA, 'class' => 'warning' ] );
    } else {
        echo $messageStack->show( [ 'text' => CONFIGURATION_NO_DATA, 'class' => 'warning' ] );
    }
}
?>

<div class="oeBox oeTable column a12 row ax">
	<div class="oeWrpr">
		<div class="oeTitu"><i class="far fa-cog"></i> <?php echo CONFIGURATION_CONFIG_LIST; ?></div>
		<form method="post" action="<?php echo tep_href_link( $sUrlPage, 'action=group' ); ?>" class="oeCntd row ax">
			<div class="oeBoxFltr column a12 ax row">
				<div class="column a09 row ax amiddle input-search">
					<label class="column"><?php echo TEXT_SEARCH; ?>: </label> <div class="column"><input type="text" name="filter[search]" placeholder="<?php echo TEXT_SEARCH_PLACEHOLDER; ?>" value="<?php echo $aFiler['search']; ?>" autofocus/> <input type="submit" style="display: none" /> <i class="fa fa-search"></i></div>
				</div>
				<div class="column a03 tright">
					<?php echo ($sWhere != '' ? '<a title="' . TEXT_CLEAN_FILTER . '" href=" ' . tep_href_link( $sUrlPage, tep_get_all_get_params( [ 'filter' ] ) ) . '" class="xbutton hv9 rojo small"><i class="fas fa-times"></i></a> ' : ''); ?>
					<a href="#fltr-lstd" title="<?php echo TEXT_FILTER; ?>" class="xbutton small hv9 verde-turquesa mgp-inln"><i class="fa fa-filter"></i></a>
				</div>
			</div>

			<table class="xform">
				<thead>
				<tr>
					<th width="17" class="chck"><input type="checkbox" id="all_check"/><label for="all_check"><span></span></label></th>
					<th class="sort"><?php echo tableSetSort( 'configuration_group_title', CONFIGURATION_TABLE_CONFIGURATION ); ?></th>
					<th class="sort"><?php echo tableSetSort( 'configuration_group_description', CONFIGURATION_TABLE_DESCRIPTION ); ?></th>
					<th width="80" class="sort"><?php echo tableSetSort( 'sort_order', CONFIGURATION_TABLE_ORDER ); ?></th>
					<th width="125px"><?php echo CONFIGURATION_TEXT_ACTIONS; ?></th>
				</tr>
				</thead>
				<tbody>

				<?php while( $aRow = tep_db_fetch_array( $aRows ) ): ?>
					<tr data-dblclick="<?php echo tep_href_link( $sUrlPage, 'action=options&id=' . $aRow['configuration_group_id'] ); ?>">
						<td class="chck" align="center"><input type="checkbox" id="id_<?php echo $aRow['configuration_group_id']; ?>" name="id[]" value="<?php echo $aRow['configuration_group_id']; ?>"/><label for="id_<?php echo $aRow['configuration_group_id']; ?>"><span></span></label></td>
						<td><?php echo $aRow['configuration_group_title']; ?></td>
						<td><?php echo $aRow['configuration_group_description']; ?></td>
						<td><?php echo $aRow['sort_order']; ?></td>
						<td>
							<div class="drop xfselect">
								<div><?php echo CONFIGURATION_TEXT_ACTIONS; ?></div>
								<ul class="down down-dngt">
									<li><a href="<?php echo tep_href_link( $sUrlPage, 'action=group_crud&id=' . $aRow['configuration_group_id'] ); ?>" class="hv"><i class="fas fa-pencil-alt"></i> <?php echo CONFIGURATION_TABLE_EDIT_RECORD; ?></a></li>
									<li><a data-confirm="<?php echo CONFIGURATION_TABLE_DELETE_RECORD_CONFIRM; ?>" href="<?php echo tep_href_link( $sUrlPage, 'action=group_delete&id=' . $aRow['configuration_group_id'] ); ?>" class="hv"><i class="fas fa-trash-alt"></i> <?php echo CONFIGURATION_TABLE_DELETE_RECORD; ?></a></li>
								</ul>
							</div>
						</td>
					</tr>
				<?php endwhile; ?>

				</tbody>
			</table>

			<?php echo $aRowsSplit->showPaginateTable( tep_get_all_get_params( ['page'] ), 'page', $sHtmlActionMasivo, 'solenopsis' ); ?>
		</form>
	</div>
</div>

<form action="<?php echo tep_href_link( $sUrlPage ); ?>" method="get" id="fltr-lstd" class="vntn-form mfp-hide zoom-anim-dialog oeBox mfp-white">
	<input type="hidden" name="action" value="group" />
	<div class="oeWrpr">
		<div class="oeTitu"><i class="far fa-cog"></i> <?php echo CONFIGURATION_CONFIG_LIST; ?></div>
		<div class="oeCntd row ax xform xform-horizontal">
			<label for="search" class="column a02 tright"><?php echo TEXT_SEARCH; ?>:</label>
			<div class="column a10">
				<input type="text" name="filter[search]" placeholder="<?php echo TEXT_SEARCH_PLACEHOLDER; ?>" value="<?php echo $aFiler['search']; ?>"/>
			</div>
			<div class="xline xline-none"></div>
			<div class="column a12 tright">
				<?php echo ($sWhere != '' ? '<a href="' . tep_href_link( $sUrlPage, tep_get_all_get_params( [ 'filter' ] ) ) . '" class="xbutton hv9 rojo small"><i class="fas fa-times"></i> ' . TEXT_DELETE . '</a> ' : ''); ?>
				<div class="xbutton verde hv9 small"><input type="submit"/><span class="fa fa-filter"></span> <?php echo TEXT_FILTER; ?></div>
			</div>
		</div>
	</div>
</form>
