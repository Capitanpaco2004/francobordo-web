<?php
	// Mensajes comprobamos si tenemos datos
	if (tep_db_num_rows($aRows) <= 0) {
		if ($sWhere != '') {
			echo $messageStack->show(['text' => TEXT_FILTER_NO_RESULTS, 'class' => 'warning']);
		} else {
			echo $messageStack->show(['text' => TEXT_NO_BANNERS, 'class' => 'warning']);
		}
	}
?>

<div class="oeBox oeTable column a12 row ax">
	<div class="oeWrpr">
		<div class="oeTitu"><i class="fas fa-list"></i> <?php echo TEXT_BANNERS_LIST; ?></div>
		<form method="post" action="<?php echo tep_href_link($sUrlPage); ?>" class="oeCntd row ax">
			<div class="oeBoxFltr column a12 ax row">
				<div class="column a09 row ax amiddle input-search">
					<label class="column"><?php echo TEXT_SEARCH; ?>: </label>
					<div class="column">
						<input type="text" name="filter[search]" placeholder="<?php echo TEXT_SEARCH_PLACEHOLDER; ?>" value="<?php echo $aFilter['search'] ?? ''; ?>" autofocus/>
						<i class="fa fa-search"></i>
					</div>
				</div>
				<div class="column a03 tright">
					<?php echo ($sWhere != '' ? '<a title="' . TEXT_CLEAN_FILTER . '" href="' . tep_href_link($sUrlPage, tep_get_all_get_params(['filter'])) . '" class="xbutton hv9 rojo small"><i class="fas fa-times"></i></a> ' : ''); ?>
				</div>
			</div>

			<table class="xform">
				<thead>
					<tr>
						<th width="17" class="chck"><input type="checkbox" id="all_check"/><label for="all_check"><span></span></label></th>
						<th width="80"><?php echo TABLE_HEADING_IMAGE; ?></th>
						<th class="sort"><?php echo tableSetSort('banners_title', TABLE_HEADING_BANNERS); ?></th>
						<th class="sort"><?php echo tableSetSort('banners_group', TABLE_HEADING_GROUPS); ?></th>
						<th class="sort"><?php echo tableSetSort('date_added', TEXT_BANNERS_DATE_ADDED); ?></th>
						<th class="sort"><?php echo tableSetSort('expires_date', TEXT_BANNERS_EXPIRES_ON); ?></th>
						<th style="text-align: center;"><?php echo TABLE_HEADING_STATUS; ?></th>
						<th width="125"><?php echo TEXT_ACTIONS; ?></th>
					</tr>
				</thead>
				<tbody>
					<?php while ($aRow = tep_db_fetch_array($aRows)): ?>
						<?php
							// Obtener imagen del banner
							$sImagen = '';
							if (function_exists('getImagenBanner')) {
								$sImagen = getImagenBanner($aRow['banners_id']);
							}
						?>
						<tr data-dblclick="<?php echo tep_href_link($sUrlPage, 'action=crud&bID=' . $aRow['banners_id']); ?>">
							<td class="chck" align="center">
								<input type="checkbox" id="id_<?php echo $aRow['banners_id']; ?>" name="id[]" value="<?php echo $aRow['banners_id']; ?>"/>
								<label for="id_<?php echo $aRow['banners_id']; ?>"><span></span></label>
							</td>
							<td><?php echo $sImagen; ?></td>
							<td><?php echo htmlspecialchars($aRow['banners_title']); ?></td>
							<td><?php echo htmlspecialchars($aRow['banners_group']); ?></td>
							<td><?php echo ($aRow['date_added'] != '' ? tep_date_short($aRow['date_added']) : '-'); ?></td>
							<td><?php echo ($aRow['expires_date'] != '' && $aRow['expires_date'] != '0000-00-00 00:00:00' ? tep_date_short($aRow['expires_date']) : '-'); ?></td>
							<td align="center">
								<?php if ($aRow['status'] == '1'): ?>
									<?php echo tep_image(DIR_WS_IMAGES . 'icon_status_green.png', IMAGE_ICON_STATUS_GREEN, 10, 10); ?>&nbsp;&nbsp;<a href="<?php echo tep_href_link($sUrlPage, 'action=setflag&flag=0&bID=' . $aRow['banners_id'] . '&page=' . $sGetPage); ?>"><?php echo tep_image(DIR_WS_IMAGES . 'icon_status_red_light.png', IMAGE_ICON_STATUS_RED_LIGHT, 10, 10); ?></a>
								<?php else: ?>
									<a href="<?php echo tep_href_link($sUrlPage, 'action=setflag&flag=1&bID=' . $aRow['banners_id'] . '&page=' . $sGetPage); ?>"><?php echo tep_image(DIR_WS_IMAGES . 'icon_status_green_light.png', IMAGE_ICON_STATUS_GREEN_LIGHT, 10, 10); ?></a>&nbsp;&nbsp;<?php echo tep_image(DIR_WS_IMAGES . 'icon_status_red.png', IMAGE_ICON_STATUS_RED, 10, 10); ?>
								<?php endif; ?>
							</td>
							<td>
								<div class="drop xfselect">
									<div><?php echo TEXT_ACTIONS; ?></div>
									<ul class="down down-dngt">
										<li><a href="<?php echo tep_href_link($sUrlPage, 'action=crud&bID=' . $aRow['banners_id']); ?>" class="hv"><i class="fas fa-pencil-alt"></i> <?php echo IMAGE_EDIT; ?></a></li>
										<li><a data-confirm="<?php echo TEXT_INFO_DELETE_INTRO; ?>" href="<?php echo tep_href_link($sUrlPage, 'action=delete&bID=' . $aRow['banners_id'] . '&page=' . $sGetPage); ?>" class="hv"><i class="fas fa-trash-alt"></i> <?php echo IMAGE_DELETE; ?></a></li>
									</ul>
								</div>
							</td>
						</tr>
					<?php endwhile; ?>
				</tbody>
			</table>

			<?php echo $aRowsSplit->showPaginateTable(tep_get_all_get_params(['page']), 'page', $sHtmlActionMasivo, 'solenopsis'); ?>
		</form>
	</div>
</div>
