<form method="post" id="saveform-send" class="form-customers-note-status" action="<?php echo tep_href_link( $sUrlPage, 'action=crud' ) ?>">
	<div class="oeBox column a12 row ax">
		<div class="oeWrpr">
			<div class="oeTitu"><i class="fa fa-gear"></i> <?php echo ($sGetId !== false ? CUSTOMERS_TYPE_EDIT : CUSTOMERS_TYPE_NEW) . ' ' . CUSTOMERS_TYPE_TYPE ?></div>
			<div class="oeCntd row ax xform xform-horizontal">

				<?php echo $sGetId !== false ? '<input type="hidden" name="id" value="' . $sGetId . '" />' : '' ?>
				<input type="submit" style="display: none;" />

				<label class="column a02 tright"><?php echo CUSTOMERS_TYPE_TABLE_TYPE ?>:</label>
				<div class="column a10">
					<input type="text" name="nombre" id="nombre" value="<?php echo (array_key_exists( 'nombre', $aRecord ) ? $aRecord['nombre'] : '') ?>"/>
				</div>
				<div class="xline xline-dashed"></div>

				<label class="column a02 tright"><?php echo CUSTOMERS_TYPE_TABLE_COLOR ?>:</label>
				<div class="column a10">
					<input type="color" name="color" id="color" value="<?php echo (array_key_exists( 'color', $aRecord ) ? $aRecord['color'] : '') ?>"/>
				</div>

			</div>
		</div>
	</div>
</form>
