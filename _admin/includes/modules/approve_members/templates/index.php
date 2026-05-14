<div class="oeBox oeTable column a12 row ax">
	<div class="oeWrpr">
		<div class="oeTitu"><i class="fa fa-eye"></i> <?php echo HEADING_TITLE ?></div>
		<form method="post" action="<?php echo tep_href_link( $sUrlPage ) ?>" class="oeCntd row ax">
			<div class="oeBoxFltr column a12 ax row">
				<div class="column a09 row ax amiddle input-search">
					<label class="column"><?php echo TEXT_SEARCH ?>: </label> <div class="column"><input type="text" name="filter[search]" placeholder="Introduce búsqueda" value="<?php echo $aFilter['search']; ?>" autofocus/> <input type="submit" style="display: none" /> <i class="fa fa-search"></i></div>
				</div>
				<div class="column a03 tright">
					<?php echo ($sWhere != '' ? '<a title="Quitar filtro" href=" ' . tep_href_link( $sUrlPage, tep_get_all_get_params( [ 'filter' ] ) ) . '" class="xbutton hv9 rojo small"><i class="fas fa-times"></i></a> ' : ''); ?>
					<a href="#fltr-lstd" title="Filtrar registros" class="xbutton small hv9 verde-turquesa mgp-inln"><i class="fa fa-filter"></i></a>
				</div>
			</div>

			<table class="xform">
				<thead>
					<tr>
						<th width="17" class="chck"><input type="checkbox" id="all_check"/><label for="all_check"><span></span></label></th>
						<th><?php echo TABLE_HEADING_FIRSTNAME ?></th>
						<th><?php echo TABLE_HEADING_LASTNAME ?></th>
						<th><?php echo TABLE_HEADING_TELEPHONE ?></th>
						<th><?php echo TABLE_HEADING_EMAIL ?></th>
						<th><?php echo TABLE_HEADING_IAE ?></th>
						<th ><?php echo TABLE_HEADING_ACCOUNT_CREATED ?></th>
						<th width="125"><?php echo TABLE_HEADING_ACTION ?></th>
					</tr>
				</thead>
				<tbody>
				<?php while( $customers = tep_db_fetch_array( $aRows ) ): ?>
					<tr>
						<td class="chck" align="center"><input type="checkbox" id="id_<?php echo $customers['customers_id'] ?>" name="id[]" value="<?php echo $customers['customers_id'] ?>"/><label for="id_<?php echo $customers['customers_id'] ?>"><span></span></label></td>
						<td><?php echo $customers['customers_firstname'] ?></td>
						<td><?php echo $customers['customers_lastname'] ?></td>
						<td><?php echo $customers['customers_telephone'] ?></td>
						<td><?php echo $customers['customers_email_address'] ?></td>
						<td><a href="/<?php echo $customers['proveedor_iae']; ?>" target="_blank"><i class="fas fa-file"></i> <?php echo TEXT_VIEW_IAE; ?></a></td>
						<td><?php echo tep_date_short($customers['customers_info_date_account_created']); ?></td>
						<td>
							<div class="drop xfselect">
								<div><?php echo TABLE_HEADING_ACTION ?></div>
								<ul class="down down-dngt">
									<li><a href="<?php echo tep_href_link(FILENAME_CUSTOMERS, tep_get_all_get_params(['cID', 'action']) . 'cID=' . $customers['customers_id'] . '&action=edit') ?>" class="hv"><i class="fa fa-pencil"></i><?php echo TEXT_EDIT_MEMBER ?></a></li>
									<li><a href="<?php echo tep_href_link( $sUrlPage, 'action=accept&id=' . $customers['customers_id'] ) ?>" class="hv"><i class="fa fa-check-circle"></i><?php echo TEXT_ACTIVATE_MEMBER ?></a></li>
									<li><a href="<?php echo tep_href_link( $sUrlPage, 'action=confirm&id=' . $customers['customers_id'] ) ?>" class="hv"><i class="fa fa-circle-xmark"></i><?php echo TEXT_DELETE_MEMBER ?></a></li>
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

<form action="<?php echo tep_href_link( $sUrlPage ); ?>" method="post" id="fltr-lstd" class="vntn-form mfp-hide zoom-anim-dialog oeBox mfp-white">
	<input type="hidden" name="action" value="list" />
	<div class="oeWrpr">
		<div class="oeTitu"><i class="far fa-user-check"></i> <?php echo HEADING_TITLE ?></div>
		<div class="oeCntd row ax xform xform-horizontal">
			<label for="search" class="column a02 tright"><?php echo TEXT_SEARCH ?>:</label>
			<div class="column a10">
				<input type="text" name="filter[search]" placeholder="Introducte búsqueda" value="<?php echo $aFilter['search']; ?>"/>
			</div>
			<div class="xline xline-none"></div>
			<div class="column a12 tright">
				<?php echo ($sWhere != '' ? '<a href="' . tep_href_link( $sUrlPage, tep_get_all_get_params( [ 'filter' ] ) ) . '" class="xbutton hv9 rojo small"><i class="fas fa-times"></i> Eliminar</a> ' : ''); ?>
				<div class="xbutton verde hv9 small"><input type="submit"/><span class="fa fa-filter"></span> Filtrar</div>
			</div>
		</div>
	</div>
</form>
