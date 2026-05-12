<?php
use util\tools;

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
		<div class="oeTitu"><i class="far fa-history"></i> <?php echo CONFIGURATION_HISTORY_TITLE; ?></div>
		<form method="post" action="<?php echo tep_href_link( $sUrlPage, 'action=history' ); ?>" class="oeCntd row ax">
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
					<th class="sort"><?php echo CONFIGURATION_HISTORY_TABLE_CONFIGURATION; ?></th>
					<th class="sort"><?php echo CONFIGURATION_HISTORY_TABLE_DESCRIPTION; ?></th>
					<th class="sort"><?php echo tableSetSort( 'change_date', CONFIGURATION_HISTORY_TABLE_CHANGE_DATE ); ?></th>
					<th width="120" class="sort"><?php echo CONFIGURATION_HISTORY_TABLE_OLD_VALUE; ?></th>
					<th width="120" class="sort"><?php echo CONFIGURATION_HISTORY_TABLE_NEW_VALUE; ?></th>
				</tr>
				</thead>
				<tbody>

				<?php while( $aRow = tep_db_fetch_array( $aRows ) ): ?>
					<tr>
						<td><?php echo $aRow['change_title']; ?></td>
						<td><?php echo $aRow['change_description']; ?></td>
						<td><?php echo $aRow['change_date']; ?></td>
						<td><?php echo $aRow['previous_setting']; ?></td>
						<td><?php echo $aRow['new_setting']; ?></td>
					</tr>
				<?php endwhile; ?>

				</tbody>
			</table>

			<?php echo $aRowsSplit->showPaginateTable( tep_get_all_get_params( ['page'] ), 'page', '', 'solenopsis' ); ?>
		</form>
	</div>
</div>

<form action="<?php echo tep_href_link( $sUrlPage ); ?>" method="get" id="fltr-lstd" class="vntn-form mfp-hide zoom-anim-dialog oeBox mfp-white">
	<input type="hidden" name="action" value="history" />
	<div class="oeWrpr">
		<div class="oeTitu"><i class="far fa-cog"></i> <?php echo CONFIGURATION_HISTORY_TITLE; ?></div>
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
