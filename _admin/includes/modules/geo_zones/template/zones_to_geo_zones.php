<?php
	// Mensajes comprobamos si tenemos datos
	if( tep_db_num_rows( $aRows ) <= 0 ) {
		echo $messageStack->show( array( 'text' => 'No existe ningun registro para mostrar.', 'class' => 'warning' ) );
	}
?>

<div class="oeBox oeTable column a12 row ax">
	<div class="oeWrpr">
		<div class="oeTitu"><i class="fas fa-globe-asia"></i> <?php echo $sSubtitle; ?></div>
		<form method="post" action="#" class="oeCntd row ax">
			<div class="oeBoxFltr column a12 ax row">
				<div class="column a09 row ax amiddle input-search">
					<label class="column">Buscar: </label> <div class="column"><input id="zone_search" type="text" name="filter[search]" placeholder="Introduce búsqueda" autocomplete="off" autofocus/> <i class="fa fa-search"></i></div>
				</div>
				<div class="column a03 tright">
					<a id="zone_delete_filter" title="Quitar filtro" href="javascript:void(0)" class="xbutton hv9 rojo small"><i class="fas fa-times"></i></a>
				</div>
			</div>

			<table id="zone" class="xform">
				<thead>
				<tr>
					<th width="17" class="chck"><input type="checkbox" id="all_check"/><label for="all_check"><span></span></label></th>
					<th>Pais</th>
					<th>Provincia</th>
					<th width="125">Acciones</th>
				</tr>
				</thead>
				<tbody>
				<?php while( $aRow = tep_db_fetch_array( $aRows ) ): ?>
					<tr data-dblclick="<?php echo tep_href_link( $sUrlPage, 'action=zones_to_geo_zones&geo_zone_id=' . $aRow['geo_zone_id'] ); ?>">
						<td class="chck" align="center"><input type="checkbox" id="id_<?php echo $aRow['association_id']; ?>" name="id[]" value="<?php echo $aRow['association_id']; ?>"/><label for="id_<?php echo $aRow['association_id']; ?>"><span></span></label></td>
						<td>
							<?php echo $aRow['countries_name']; ?>
						</td>
						<td>
							<?php echo ($aRow['zone_name'] == '' ? 'Todas' : $aRow['zone_name']); ?>
						</td>
						<td>
							<div class="drop xfselect">
								<div>Acciones</div>
								<ul class="down down-dngt">
									<li><a data-confirm="¿Realmente deseas eliminar el registro?" href="<?php echo tep_href_link( $sUrlPage, 'action=zones_to_geo_zones_delete&id=' . $aRow['association_id'] ); ?>" class="hv"><i class="fas fa-trash-alt"></i> Eliminar registro</a></li>
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


<div id="dialog-insert-subzone" class="dialog-insert-subzone"  title="Insertar por provincias" style="display:none;">
	<form action="<?php echo tep_href_link( $sUrlPage, 'action=zones_to_geo_zones_add' ); ?>" method="post" class="rows ax xform sp10">
		<input type="hidden" name="geo_zone_id" value="<?php echo $geoZoneId; ?>"/>
		<input type="checkbox" name="all_zones" value="true"/>
		<label class="column a12">Pais:</label>
		<div class="column a12">
			<?php echo tep_draw_pull_down_menu( 'id_country', tep_get_countries(), STORE_COUNTRY, 'data-ajax-states="zone_state"' ); ?>
		</div>
		<label class="column a12">Provincias:</label>
		<div class="column a05">
			<input class="input-search" name="filter[search]" placeholder="Escribe la provincia a buscar" type="text" autocomplete="nope" />
			<select class="skip select-search from" multiple="multiple"></select>
		</div>
		<div class="column a02 buttons">
			<div class="add-right hvr7"><i class="fas fa-angle-right"></i></div>
			<div class="add-all-right hvr7"><i class="fas fa-angle-double-right"></i></div>
			<div class="add-left hvr7"><i class="fas fa-angle-left"></i></div>
			<div class="add-all-left hvr7"><i class="fas fa-angle-double-left"></i></div>
		</div>
		<div class="column a05">
			<input class="input-search" name="filter[search]" placeholder="Escribe la provincia a buscar" type="text" autocomplete="nope" />
			<select class="skip select-search to" name="states[]" multiple="multiple"></select>
		</div>
		<div class="column a12 clearfix">
			<input type="submit" class="fright xbutton small verde" value="Insertar provincias"/>
		</div>
	</form>
</div>


<div id="dialog-insert-country" class="dialog-insert-subzone"  title="Insertar por país" style="display:none;">
	<form action="<?php echo tep_href_link( $sUrlPage, 'action=zones_to_geo_zones_add_country' ); ?>" method="post" class="rows ax xform sp10">
		<input type="hidden" name="geo_zone_id" value="<?php echo $geoZoneId; ?>"/>
		<label class="column a12">Pais:</label>
		<div class="column a05">
			<input class="input-search" name="filter[search]" placeholder="Escribe el país a buscar" type="text" autocomplete="off" />
			<?php echo str_replace(['OnMouseWheel="return false;"'], [''], tep_draw_pull_down_menu( 'id_country', tep_get_countries(), STORE_COUNTRY, 'class="skip select-search from" multiple="multiple"' )); ?>
		</div>
		<div class="column a02 buttons">
			<div class="add-right hvr7"><i class="fas fa-angle-right"></i></div>
			<div class="add-all-right hvr7"><i class="fas fa-angle-double-right"></i></div>
			<div class="add-left hvr7"><i class="fas fa-angle-left"></i></div>
			<div class="add-all-left hvr7"><i class="fas fa-angle-double-left"></i></div>
		</div>
		<div class="column a05">
			<input class="input-search" name="filter[search]" placeholder="Escribe la país a buscar" type="text" autocomplete="nope" />
			<select class="skip select-search to" name="states[]" multiple="multiple"></select>
		</div>
		<div class="column a12 clearfix">
			<input type="submit" class="fright xbutton small verde" value="Insertar países"/>
		</div>
	</form>
</div>
