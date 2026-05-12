<div class="oeBox column a12 row ax">
	<div class="oeWrpr">
		<div class="oeTitu"><i class="fa fa-shield"></i> Configuración de Azimut </div>
		<form method="post" id="saveform-send" action="<?php echo tep_href_link($url, 'module=azimut&action=configure'); ?>" class="oeCntd row ax xform xform-horizontal">	
			<label for="IMPORT_AZIMUT_ACTIVE" class="column a02 tright">Activo:</label>
			<div class="column a10">
				<select name="IMPORT_AZIMUT_ACTIVE">
					<option value="true"<?php echo (defined('IMPORT_AZIMUT_ACTIVE') && IMPORT_AZIMUT_ACTIVE == 'true' ? ' selected' : ''); ?>>Sí</option>
					<option value="false"<?php echo (defined('IMPORT_AZIMUT_ACTIVE') && IMPORT_AZIMUT_ACTIVE == 'false' ? ' selected' : ''); ?>>No</option>
				</select>
				<div class="DFhelp">Activa o desactiva el proveedor del importador. Si desactivas el proveedor, todos los productos asociados al proveedor se desactivarán.</div>
			</div>

			<div class="xline xline-dashed"></div>

			<label for="IMPORT_AZIMUT_MIN_ORDER" class="column a02 tright">Pedido mínimo:</label>
			<div class="column a10">
				<input type="text" name="IMPORT_AZIMUT_MIN_ORDER" id="IMPORT_AZIMUT_MIN_ORDER" value="<?php echo (defined('IMPORT_AZIMUT_MIN_ORDER') && 'IMPORT_AZIMUT_MIN_ORDER' != '' ? IMPORT_AZIMUT_MIN_ORDER : ''); ?>"/>
				<div class="DFhelp">Valor mínimo del pedido para que se pueda realizar</div>
			</div>

			<div class="xline xline-dashed"></div>

			<label for="IMPORT_AZIMUT_MIN_PRICE" class="column a02 tright">Precio mínimo a importar:</label>
			<div class="column a10">
				<input type="text" name="IMPORT_AZIMUT_MIN_PRICE" id="IMPORT_AZIMUT_MIN_PRICE" value="<?php echo (defined('IMPORT_AZIMUT_MIN_PRICE') && 'IMPORT_AZIMUT_MIN_PRICE' != '' ? IMPORT_AZIMUT_MIN_PRICE : ''); ?>"/>
				<div class="DFhelp">Valor mínimo del precio del producto para que sea procesado por el importador</div>
			</div>

			<div class="xline xline-dashed"></div>

			<label for="IMPORT_AZIMUT_INCREASE_PRICE" class="column a02 tright">Incremento en el precio:</label>
			<div class="column a08">
				<input type="text" name="IMPORT_AZIMUT_INCREASE_PRICE" id="IMPORT_AZIMUT_INCREASE_PRICE" value="<?php echo (defined('IMPORT_AZIMUT_INCREASE_PRICE') && 'IMPORT_AZIMUT_INCREASE_PRICE' != '' ? IMPORT_AZIMUT_INCREASE_PRICE : ''); ?>"/>
				<div class="DFhelp">Incremento porcentual del precio por cada producto subido, ya sea sobre el PVP o el PVD del producto.<br />El formato para introducir es numérico con los decimales con un punto, por ejemplo: 12.33.</div>
			</div>

			<label for="IMPORT_AZIMUT_INCREASE_PRICE_OVER" class="column a01 tright">Sobre el:</label>
			<div class="column a01">
				<select name="IMPORT_AZIMUT_INCREASE_PRICE_OVER">
					<option value="PVP"<?php echo (defined('IMPORT_AZIMUT_INCREASE_PRICE_OVER') && IMPORT_AZIMUT_INCREASE_PRICE_OVER == 'PVP' ? ' selected' : ''); ?>>PVP</option>
					<option value="PVD"<?php echo (defined('IMPORT_AZIMUT_INCREASE_PRICE_OVER') && IMPORT_AZIMUT_INCREASE_PRICE_OVER == 'PVD' ? ' selected' : ''); ?>>PVD</option>
				</select>
			</div>

			<div class="xline xline-dashed"></div>

			<label for="IMPORT_AZIMUT_DATE_ADDED" class="column a02 tright">Fecha de nuevos productos:</label>
			<div class="column a10">
				<input type="text" name="IMPORT_AZIMUT_DATE_ADDED" id="IMPORT_AZIMUT_DATE_ADDED" class="dxdatepicker" value="<?php echo (defined('IMPORT_AZIMUT_DATE_ADDED') && 'IMPORT_AZIMUT_DATE_ADDED' != '' ? IMPORT_AZIMUT_DATE_ADDED : ''); ?>"/>
				<div class="DFhelp">Fecha de inserción de nuevos productos para evitar saturar la sección de novedades. El formato es DD/MM/YYYY. Dejar en blanco para que el sistema obtenga la fecha actual.</div>
			</div>

			<div class="xline xline-dashed"></div>

			<label for="IMPORT_AZIMUT_REMOVE_REMAPED" class="column a02 tright">¿Eliminar productos al desactivar el mapeo?:</label>
			<div class="column a10">
				<select name="IMPORT_AZIMUT_REMOVE_REMAPED">
					<option value="1"<?php echo (defined('IMPORT_AZIMUT_REMOVE_REMAPED') && IMPORT_AZIMUT_REMOVE_REMAPED == '1' ? ' selected' : ''); ?>>Si</option>
					<option value="0"<?php echo (defined('IMPORT_AZIMUT_REMOVE_REMAPED') && IMPORT_AZIMUT_REMOVE_REMAPED == '0' ? ' selected' : ''); ?>>No</option>
				</select>
				<div class="DFhelp">Cuando hemos mapeado y sincronizado una categoría, pero luego la hemos quitado del mapeo, ¿eliminamos los productos asociados?.</div>
			</div>

			<div class="xline xline-dashed"></div>

			<label for="IMPORT_AZIMUT_SANDBOX_CATEGORY" class="column a02 tright">Categoría "Sandbox":</label>
			<div class="column a10">
				<select name="IMPORT_AZIMUT_SANDBOX_CATEGORY">
					<option value=""<?php echo (! defined('IMPORT_AZIMUT_SANDBOX_CATEGORY') || IMPORT_AZIMUT_SANDBOX_CATEGORY == '' ? ' selected' : ''); ?>>Selecciona categoría</option>

					<?php foreach ($categories as $category): ?>
					<option value="<?php echo $category['id']; ?>"<?php echo (defined('IMPORT_AZIMUT_SANDBOX_CATEGORY') && IMPORT_AZIMUT_SANDBOX_CATEGORY == $category['id'] ? ' selected' : ''); ?>><?php echo $category['text']; ?></option>
					<?php endforeach; ?>
				</select>
				<div class="DFhelp">Selecciona la categoría "Sandbox" donde van a ir a parar todos los productos y categorías importados.</div>
			</div>

			<input type="submit" style="display: none;" />
		</form>
	</div>
</div>