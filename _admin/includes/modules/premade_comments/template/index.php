<?php
	// Mensajes comprobamos si tenemos datos
	if( tep_db_num_rows( $aRows ) <= 0 )
	{
		if ($sWhere != '') {
            echo $messageStack->show( [ 'text' => PREMADE_COMMENTS_FILTER_NO_DATA, 'class' => 'warning' ] );
        } else {
            echo $messageStack->show( [ 'text' => PREMADE_COMMENTS_NO_RECORDS, 'class' => 'warning' ] );
        }
	}
?>

<div class="oeBox oeTable column a12 row ax">
	<div class="oeWrpr">
		<div class="oeTitu"><i class="fas fa-comment"></i> <?php echo PREMADE_COMMENTS_PREMADE_COMMENTS; ?></div>
		<form method="post" action="<?php echo tep_href_link_pc( $sUrlPage ); ?>" class="oeCntd row ax">
			<div class="oeBoxFltr column a12 ax row">
				<div class="column a09 row ax amiddle input-search">
					<label class="column"><?php echo TEXT_SEARCH; ?>: </label> <div class="column"><input type="text" name="filter[search]" placeholder="<?php echo TEXT_SEARCH_PLACEHOLDER; ?>" value="<?php echo $aFiler['search']; ?>" autofocus/> <i class="fa fa-search"></i></div>
				</div>
				<div class="column a03 tright">
					<?php echo ($sWhere != '' ? '<a title="' . TEXT_CLEAN_FILTER . '" href=" ' . tep_href_link_pc( $sUrlPage, tep_get_all_get_params( [ 'filter', 'orders_id' ] ) ) . '" class="xbutton hv9 rojo small"><i class="fas fa-times"></i></a> ' : ''); ?>
					<a href="#fltr-lstd" title="<?php echo TEXT_FILTER; ?>" class="xbutton small hv9 verde-turquesa mgp-inln"><i class="fa fa-filter"></i></a>
				</div>
			</div>

			<table class="xform">
				<thead>
					<tr>
						<th width="17" class="chck"><input type="checkbox" id="all_check"/><label for="all_check"><span></span></label></th>
						<th class="sort"><?php echo tableSetSort( 'title', PREMADE_COMMENTS_TEXT_TITLE ); ?></th>
						<th width="125"><?php echo PREMADE_COMMENTS_TEXT_ACTIONS; ?></th>
					</tr>
				</thead>
				<tbody>

					<?php while( $aRow = tep_db_fetch_array( $aRows ) ): ?>
						<tr data-dblclick="<?php echo tep_href_link_pc( $sUrlPage, 'action=crud&id=' . $aRow['id'] ); ?>">
							<td class="chck" align="center"><input type="checkbox" id="id_<?php echo $aRow['id']; ?>" name="id[]" value="<?php echo $aRow['id']; ?>"/><label for="id_<?php echo $aRow['id']; ?>"><span></span></label></td>
							<td><?php echo $aRow['title']; ?></td>
							<td>
								<div class="drop xfselect">
									<div><?php echo PREMADE_COMMENTS_TEXT_ACTIONS; ?></div>
									<ul class="down down-dngt">
										<li><a href="<?php echo tep_href_link_pc( $sUrlPage, 'action=crud&id=' . $aRow['id'] ); ?>" class="hv"><i class="fas fa-pencil-alt"></i> <?php echo PREMADE_COMMENTS_TEXT_EDIT_RECORD; ?></a></li>
										<li><a data-confirm="<?php echo PREMADE_COMMENTS_TEXT_DELETE_RECORD_CONFIRM; ?>" href="<?php echo tep_href_link_pc( $sUrlPage, 'action=delete&id=' . $aRow['id'] ); ?>" class="hv"><i class="fas fa-trash-alt"></i> <?php echo PREMADE_COMMENTS_TEXT_DELETE_RECORD; ?></a></li>
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
	<?php echo isset($_GET['orders_id']) ? '<input type="hidden" name="orders_id" value="' . $_GET['orders_id'] . '"/>' : ''; ?>
	<div class="oeWrpr">
		<div class="oeTitu"><i class="fas fa-search"></i> <?php echo PREMADE_COMMENTS_PREMADE_COMMENTS; ?></div>
		<div class="oeCntd row ax xform xform-horizontal">
			<label for="search" class="column a02 tright"><?php echo TEXT_SEARCH; ?>:</label>
			<div class="column a10">
				<input type="text" name="filter[search]" placeholder="<?php echo TEXT_SEARCH_PLACEHOLDER; ?>" value="<?php echo $aFiler['search']; ?>"/>
			</div>
			<div class="xline xline-none"></div>
			<div class="column a12 tright">
				<?php echo ($sWhere != '' ? '<a href="' . tep_href_link_pc( $sUrlPage, tep_get_all_get_params( [ 'filter', 'orders_id' ] ) ) . '" class="xbutton hv9 rojo small"><i class="fas fa-times"></i> ' . TEXT_DELETE . '</a> ' : ''); ?>
				<div class="xbutton verde hv9 small"><input type="submit"/><span class="fa fa-filter"></span> <?php echo TEXT_FILTER; ?></div>
			</div>
		</div>
	</div>
</form>
