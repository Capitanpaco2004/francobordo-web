<?php
	echo $messageStack->show( array( 'text' => 'Esta es la tabla de clasificaciones pendientes de categorías. Cuando el proveedor nos envia un producto, el administrador deberá crear una nueva categoría o escoger una ya existente y marcar en este panel a que categoría quiere que se redirijan los productos (puede seleccionar una o varías).', 'class' => 'info' ) );
?>

<select multiple="multiple" class="template-select-categories skip" style="display: none">
	<?php foreach ($categories as $category): ?>
	<option value="<?php echo $category['id']; ?>"><?php echo $category['text']; ?></option>
	<?php endforeach; ?>
</select>

<table id="table" class="tAlt wGeneral tDefault" width="100%" cellspacing="0" cellpadding="0">
	<thead>
		<tr>
			<td width="15%" style="text-align: left;">Categorías</td>
			<td align="center">Clasificación</td>
			<td width="15%" align="center">Estado</td>
			<td width="15%" align="center">Navegar</td>
		</tr>
	</thead>
	<tbody>
		<?php while($categoryMapped = tep_db_fetch_array($categoriesMapped)): ?>
		<?php
			if ($categoryMapped['import_categories_parent_id'] != $parent) {
				continue;
			}

			$mappeds = explode(',', $categoryMapped['import_categories_mapped']);
		?>
		<tr>
			<td width="15%"><?php echo $categoryMapped['import_categories_name']; ?></td>

			<td width="30%" align="center" class="select2-categories-multiple">
				<select name="categories_id[<?php echo $categoryMapped['import_categories_id']; ?>][]" data-id="<?php echo $categoryMapped['import_categories_id']; ?>" data-module="<?php echo $module; ?>" multiple="multiple" class="select2 skip" style="width: 100%;">
					<?php foreach ($categories as $category): ?>
						<option value="<?php echo $category['id']; ?>" <?php echo (in_array($category['id'], $mappeds) ? ' selected="selected"' : ''); ?>><?php echo $category['text']; ?></option>
					<?php endforeach; ?>
				</select>
			</td>

			<td class="dataTableContent chg-stts" align="center">
				<a href="import.php?module=azimut&action=status&flag=1&mapped=<?php echo $categoryMapped['import_categories_id']; ?>" data-title="<?php echo $categoryMapped['import_categories_name']; ?>" data-action="enable"><img src="images/icon_status_green<?php echo ($categoryMapped['import_categories_status'] == 0 ? '_light' : ''); ?>.png" width="10" height="10" border="0" alt="Activar" data-flag="1" title="Activar"></a>&nbsp;&nbsp;
				<a href="import.php?module=azimut&action=status&flag=0&mapped=<?php echo $categoryMapped['import_categories_id']; ?>" data-title="<?php echo $categoryMapped['import_categories_name']; ?>" data-action="disable"><img src="images/icon_status_red<?php echo ($categoryMapped['import_categories_status'] == 1 ? '_light' : ''); ?>.png" width="10" height="10" border="0" alt="Desactivar" data-flag="0" title="Desactivar"></a>
			</td>

			<td class="dataTableContent" align="center">
				<?php if ($categoryMapped['qty_subcategories'] > 0): ?>
					<?php echo '<button onclick="location.href=\'import.php?module=azimut&parent=' . $categoryMapped['import_categories_id'] . '\';return false;" style="padding: 5px; cursor: pointer;' . (hasSubcategoryMapped($categoryMapped['import_categories_id']) ? ' background-color: #ffbf7b;' : '') . '">Ver las ' . $categoryMapped['qty_subcategories'] . ' subcategoría/s</button>'; ?>
				<?php endif; ?>
			</td>
		</tr>
		<?php endwhile; ?>
	</tbody>
</table>

<?php echo $aDatoSplit->showPaginateTable(tep_get_all_get_params(array('page')), 'page', '', 'solenopsis'); ?>