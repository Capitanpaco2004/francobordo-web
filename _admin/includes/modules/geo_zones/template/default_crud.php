<div class="oeBox column a12 row ax">
	<div class="oeWrpr">
		<div class="oeTitu"><i class="fas fa-edit"></i> <?php echo $sSubtitle; ?></div>
		<form method="post" id="saveform-send" action="<?php echo tep_href_link( $sUrlPage, 'action=default_crud' ); ?>" class="oeCntd row ax xform xform-horizontal">
			<?php echo $sGetId !== false ? '<input type="hidden" name="id" value="' . $sGetId . '" />' : ''; ?>
			<input type="submit" style="display: none;" />

			<label for="geo_zone_name" class="column a02 tright"><?php echo GEO_ZONES_CRUD_NAME; ?>:</label>
			<div class="column a10">
				<?php echo array_key_exists( 'geo_zone_name', $aMessageError ) ? $aMessageError['geo_zone_name'] : ''; ?>
				<input type="text" name="geo_zone_name" id="geo_zone_name" value="<?php echo (array_key_exists( 'geo_zone_name', $aRecord ) ? $aRecord['geo_zone_name'] : ''); ?>">
				<div class="DFhelp"><?php echo GEO_ZONES_CRUD_NAME_HELP; ?></div>
			</div>

			<div class="xline xline-dashed"></div>

			<label for="geo_zone_description" class="column a02 tright"><?php echo GEO_ZONES_CRUD_DESCRIPTION; ?>:</label>
			<div class="column a10">
				<?php echo array_key_exists( 'geo_zone_description', $aMessageError ) ? $aMessageError['geo_zone_description'] : ''; ?>
				<input type="text" name="geo_zone_description" id="geo_zone_description" value="<?php echo (array_key_exists( 'geo_zone_description', $aRecord ) ? $aRecord['geo_zone_description'] : ''); ?>">
				<div class="DFhelp"><?php echo GEO_ZONES_CRUD_DESCRIPTION_HELP; ?></div>
			</div>

			<div class="xline xline-dashed"></div>

			<label for="geo_zone_type" class="column a02 tright"><?php echo GEO_ZONES_CRUD_TYPE; ?>:</label>
			<div class="column a10">
				<?php echo array_key_exists( 'geo_zone_type', $aMessageError ) ? $aMessageError['geo_zone_type'] : ''; ?>
				<?php echo tep_draw_pull_down_menu('geo_zone_type', $selectGeoZoneTypes, (array_key_exists('geo_zones_type_id', $aRecord ) ? $aRecord['geo_zones_type_id'] : '')); ?>
				<div class="DFhelp"><?php echo GEO_ZONES_CRUD_TYPE_HELP; ?></div>
			</div>
		</form>
	</div>
</div>
