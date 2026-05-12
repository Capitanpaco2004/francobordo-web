<?php
	// Mensajes comprobamos si tenemos datos
	if( tep_db_num_rows( $aRows ) <= 0 ) {
		echo $messageStack->show( array( 'text' => GEO_ZONES_NO_RECORDS, 'class' => 'warning' ) );
	}
?>

<div class="oeBox oeTable column a12 row ax">
	<div class="oeWrpr">
		<div class="oeTitu"><i class="fas fa-globe-asia"></i> <?php echo GEO_ZONES_SUBTITLE; ?></div>
		<form method="post" action="#" class="oeCntd row ax">
			<div class="oeBoxFltr column a12 ax row">
				<div class="column a09 row ax amiddle input-search">
					<label class="column"><?php echo TEXT_SEARCH; ?>: </label>
					<div class="column"><input id="zone_search" type="text" name="filter[search]" placeholder="<?php echo TEXT_SEARCH_PLACEHOLDER; ?>" autocomplete="off" autofocus/> <i class="fa fa-search"></i></div>
					<?php echo tep_draw_pull_down_menu( 'filter[search_type]', array_merge( array(array( 'id' => '', 'text' => GEO_ZONES_TYPE_ALL )), $geoZonesTypes ), '', 'id="selected-zones-type" class="skip"'); ?>
				</div>
				<div class="column a03 tright">
					<a id="zone_delete_filter" title="Quitar filtro" href="javascript:void(0)" class="xbutton hv9 rojo small"><i class="fas fa-times"></i></a>
				</div>
			</div>

			<table id="zone" class="xform" data-processing="<?php echo GEO_ZONES_FILTER_PROCESSING; ?>" data-show-records="<?php echo GEO_ZONES_FILTER_SHOW_RECORDS; ?>" data-no-results="<?php echo GEO_ZONES_FILTER_NO_RESULTS; ?>" data-no-data="<?php echo GEO_ZONES_FILTER_NO_DATA; ?>" data-view="<?php echo GEO_ZONES_FILTER_VIEW; ?>" data-show-records-2="<?php echo GEO_ZONES_FILTER_SHOW_RECORDS_2; ?>" data-show-filter="<?php echo GEO_ZONES_FILTER_SHOW_FILTER; ?>" data-search="<?php echo TEXT_SEARCH; ?>" data-loading="<?php echo GEO_ZONES_FILTER_LOADING; ?>" data-first="<?php echo GEO_ZONES_FILTER_FIRST; ?>" data-last="<?php echo GEO_ZONES_FILTER_LAST; ?>" data-next="<?php echo GEO_ZONES_FILTER_NEXT; ?>" data-before="<?php echo GEO_ZONES_FILTER_BEFORE; ?>" data-order-asc="<?php echo GEO_ZONES_FILTER_ORDER_ASC; ?>" data-order-desc="<?php echo GEO_ZONES_FILTER_ORDER_DESC; ?>" data-copy="<?php echo GEO_ZONES_FILTER_COPY; ?>" data-visibility="<?php echo GEO_ZONES_FILTER_VISIBILITY; ?>">
				<thead>
				<tr>
					<th width="17" class="chck"><input type="checkbox" id="all_check"/><label for="all_check"><span></span></label></th>
					<th><?php echo GEO_ZONES_TABLE_GEO_ZONE; ?></th>
					<th><?php echo GEO_ZONES_CRUD_DESCRIPTION; ?></th>
					<th><?php echo GEO_ZONES_COUNT; ?></th>
					<th width="125"><?php echo GEO_ZONES_ACTIONS; ?></th>
				</tr>
				</thead>
				<tbody>
				<?php while( $aRow = tep_db_fetch_array( $aRows ) ): ?>
					<tr data-zone-type="<?php echo $aRow['geo_zones_type_id']; ?>" data-dblclick="<?php echo tep_href_link( $sUrlPage, 'action=zones_to_geo_zones&geo_zone_id=' . $aRow['geo_zone_id'] ); ?>">
						<td class="chck" align="center"><input type="checkbox" id="id_<?php echo $aRow['geo_zone_id']; ?>" name="id[]" value="<?php echo $aRow['geo_zone_id']; ?>"/><label for="id_<?php echo $aRow['geo_zone_id']; ?>"><span></span></label></td>
						<td>
							<?php echo $aRow['geo_zone_name']; ?>
						</td>
						<td>
							<?php echo $aRow['geo_zone_description']; ?>
						</td>
						<td>
							<?php echo $aRow['zones_count']; ?>
						</td>
						<td>
							<div class="drop xfselect">
								<div><?php echo GEO_ZONES_ACTIONS; ?></div>
								<ul class="down down-dngt">
									<li><a href="<?php echo tep_href_link( $sUrlPage, 'action=default_crud&id=' . $aRow['geo_zone_id'] ); ?>" class="hv"><i class="fas fa-pencil-alt"></i> <?php echo GEO_ZONES_EDIT_RECORD; ?></a></li>
									<li><a data-confirm="<?php echo GEO_ZONES_DELETE_RECORD_CONFIRM; ?>" href="<?php echo tep_href_link( $sUrlPage, 'action=default_delete&id=' . $aRow['geo_zone_id'] ); ?>" class="hv"><i class="fas fa-trash-alt"></i> <?php echo GEO_ZONES_DELETE_RECORD; ?></a></li>
								</ul>
							</div>
						</td>
					</tr>
				<?php endwhile; ?>
				</tbody>
			</table>
			<div class="column a12 ax row xform oeTableBottom amiddle">
				<?php echo $sHtmlActionMasivo; ?>
			</div>
		</form>
	</div>
</div>
