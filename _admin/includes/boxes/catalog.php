<?php echo tep_admin_files_boxes(FILENAME_CATEGORIES, '<i class="bullet"></i> ' . BOX_CATALOG_CATEGORIES_PRODUCTS); ?>
<?php echo tep_admin_files_boxes("stock_sync.php", '<i class="bullet"></i> Sync Stock VStock'); ?>
<?php echo tep_admin_files_boxes("search-francobordo.php", '<i class="bullet"></i> Buscador Francobordo'); ?>
<div>
	<a class="prnt" href="javascript:void(0);"><i class="bullet"></i> <?php echo BOX_CATALOG_CATEGORIES_PRODUCTS_ATTRIBUTES; ?> <i class="fa fa-angle-right"></i></a>
	<div class="sbmn">
		<?php echo tep_admin_files_boxes(FILENAME_PRODUCTS_ATTRIBUTES, '<i class="bullet"></i> Crear Atributos'); ?>
		<?php echo tep_admin_files_boxes('new_attributes.php', '<i class="bullet"></i> Asignar Atributos'); ?>
	</div>
</div>
<div>
	<a class="prnt" href="javascript:void(0);"><i class="bullet"></i> Ofertas <i class="fa fa-angle-right"></i></a>
	<div class="sbmn">
		<?php echo tep_admin_files_boxes(FILENAME_SPECIALS, '<i class="bullet"></i> Ofertas'); ?>
		<?php echo tep_admin_files_boxes('specials_avanzado.php', '<i class="bullet"></i> Ofertas Avanzadas'); ?>
		<?php echo tep_admin_files_boxes('specialsbycategory.php', '<i class="bullet"></i> Ofertas por Categorias'); ?>
		<?php echo tep_admin_files_boxes('auto_specials_preview.php', '<i class="bullet"></i> Candidatos auto (poca rotación)'); ?>
		<?php echo tep_admin_files_boxes('auto_specials_rules.php', '<i class="bullet"></i> Reglas auto-ofertas'); ?>
	</div>
</div>
<div>
	<a class="prnt" href="javascript:void(0);"><i class="bullet"></i> Ventas Cruzadas <i class="fa fa-angle-right"></i></a>
	<div class="sbmn">
		<?php echo (defined('RELATED_PRODUCTS_ACTIVE') && RELATED_PRODUCTS_ACTIVE == 'true' ? tep_admin_files_boxes('related_products.php', '<i class="bullet"></i> Productos relacionados') : ''); ?>
		<?php echo tep_admin_files_boxes('products_together.php', '<i class="bullet"></i> Productos Relacionados con oferta'); ?>
		<?php echo tep_admin_files_boxes(FILENAME_XSELL_PRODUCTS, '<i class="bullet"></i> Otros productos de la Categoría'); ?>
	</div>
</div>
<?php echo tep_admin_files_boxes(FILENAME_BANNERS_DESTACADOS, '<i class="bullet"></i> Banners Destacados'); ?>
<?php echo tep_admin_files_boxes('products_specifications.php', '<i class="bullet"></i> Especificaciones de productos'); ?>
<?php echo tep_admin_files_boxes(FILENAME_MANUFACTURERS, '<i class="bullet"></i> ' . BOX_CATALOG_MANUFACTURERS); ?>
<?php echo tep_admin_files_boxes(FILENAME_REVIEWS, '<i class="bullet"></i> ' . BOX_CATALOG_REVIEWS); ?>
<?php echo tep_admin_files_boxes(FILENAME_PRODUCTS_EXPECTED, '<i class="bullet"></i> ' . BOX_CATALOG_PRODUCTS_EXPECTED); ?>
<?php echo tep_admin_files_boxes(FILENAME_QUICK_UPDATES, '<i class="bullet"></i> ' . BOX_CATALOG_QUICK_UPDATES); ?>
<?php echo tep_admin_files_boxes('import_log.php', '<i class="bullet"></i> Log Minderest'); ?>
<?php echo tep_admin_files_boxes('actuprice_minderest_run.php', '<i class="bullet"></i> Minderest (repricer)'); ?>
<?php echo tep_admin_files_boxes('ebay.php', '<i class="bullet"></i> Ebay'); ?>
<?php echo tep_admin_files_boxes(FILENAME_PRODUCTS_MULTI, '<i class="bullet"></i> Admin. Multiples Productos'); ?>
<?php //echo tep_admin_files_boxes('csv-products.php', '<i class="bullet"></i> Exportar/Importar DENOX'); ?>
<div>
	<a class="prnt" href="javascript:void(0);"><i class="bullet"></i> Exportador/Importador Francobordo <i class="fa fa-angle-right"></i></a>
	<div class="sbmn">
		<?php echo tep_admin_files_boxes('exportador.php', '<i class="bullet"></i> Exportador CSV'); ?>
		<?php echo tep_admin_files_boxes('importador.php', '<i class="bullet"></i> Importador CSV'); ?>
		<?php echo tep_admin_files_boxes('ExU.php', '<i class="bullet"></i> Exportador Universal'); ?>
		<?php echo tep_admin_files_boxes('ExU_CDBARCOS.php', '<i class="bullet"></i> Exportador CD Barcos'); ?>
		<?php echo tep_admin_files_boxes('precios_catalogo.php', '<i class="bullet"></i> Precios catalogos'); ?>
		<?php echo tep_admin_files_boxes('precios_etiquetas.php', '<i class="bullet"></i> Precios etiquetas'); ?>
		<?php echo tep_admin_files_boxes('exportador_better_together.php', '<i class="bullet"></i> Exportador Better Together'); ?>
		<?php echo tep_admin_files_boxes('importador_better_together.php', '<i class="bullet"></i> Importador Better Together'); ?>
		<?php echo tep_admin_files_boxes('importador_stock_aributo.php', '<i class="bullet"></i> Importador Stock Atributos'); ?>
		<?php echo tep_admin_files_boxes('backfill_ean_internos.php', '<i class="bullet"></i> Backfill EAN internos'); ?>
		<div>
			<a class="prnt" href="javascript:void(0);"><i class="bullet"></i> Osculati <i class="fa fa-angle-right"></i></a>
			<div class="sbmn">
				<?php echo tep_admin_files_boxes('import-osculati-altas.php', '<i class="bullet"></i> Importador'); ?>
				<?php echo tep_admin_files_boxes('Actualizador_precios_osculati.php', '<i class="bullet"></i> Actualizador precios'); ?>
				<?php echo tep_admin_files_boxes('rectificar_osculati_variantes.php', '<i class="bullet"></i> Rectificar variantes'); ?>
			</div>
		</div>
		<div>
			<a class="prnt" href="javascript:void(0);"><i class="bullet"></i> FNI <i class="fa fa-angle-right"></i></a>
			<div class="sbmn">
				<?php echo tep_admin_files_boxes('import-fni-altas.php', '<i class="bullet"></i> Importador'); ?>
				<?php echo tep_admin_files_boxes('Actualizador_precios_fni.php', '<i class="bullet"></i> Actualizador precios'); ?>
				<?php echo tep_admin_files_boxes('retraducir-fni.php', '<i class="bullet"></i> Retraducir'); ?>
			</div>
		</div>
		<div>
			<a class="prnt" href="javascript:void(0);"><i class="bullet"></i> Azimut <i class="fa fa-angle-right"></i></a>
			<div class="sbmn">
				<?php echo tep_admin_files_boxes('import-azimut-altas.php', '<i class="bullet"></i> Importador'); ?>
				<?php echo tep_admin_files_boxes('Actualizador_precios_azimut.php', '<i class="bullet"></i> Actualizador precios'); ?>
			</div>
		</div>
		<div>
			<a class="prnt" href="javascript:void(0);"><i class="bullet"></i> Cressi <i class="fa fa-angle-right"></i></a>
			<div class="sbmn">
				<?php echo tep_admin_files_boxes('import-cressi-altas.php', '<i class="bullet"></i> Importador'); ?>
				<?php echo tep_admin_files_boxes('Actualizador_precios_cressi.php', '<i class="bullet"></i> Actualizador precios'); ?>
			</div>
		</div>
		<div>
			<a class="prnt" href="javascript:void(0);"><i class="bullet"></i> Trem <i class="fa fa-angle-right"></i></a>
			<div class="sbmn">
				<?php echo tep_admin_files_boxes('import-trem-altas.php', '<i class="bullet"></i> Importador'); ?>
				<?php echo tep_admin_files_boxes('Actualizador_precios_trem.php', '<i class="bullet"></i> Actualizador precios'); ?>
			</div>
		</div>
		<div>
			<a class="prnt" href="javascript:void(0);"><i class="bullet"></i> Garmin <i class="fa fa-angle-right"></i></a>
			<div class="sbmn">
				<?php echo tep_admin_files_boxes('import-garmin-altas.php', '<i class="bullet"></i> Importador'); ?>
				<?php echo tep_admin_files_boxes('Actualizador_precios_garmin.php', '<i class="bullet"></i> Actualizador precios'); ?>
			</div>
		</div>
		<div>
			<a class="prnt" href="javascript:void(0);"><i class="bullet"></i> Lankhorst <i class="fa fa-angle-right"></i></a>
			<div class="sbmn">
				<?php echo tep_admin_files_boxes('import-lankhorst-altas.php', '<i class="bullet"></i> Importador'); ?>
				<?php echo tep_admin_files_boxes('Actualizador_precios_lankhorst.php', '<i class="bullet"></i> Actualizador precios'); ?>
			</div>
		</div>
		<div>
			<a class="prnt" href="javascript:void(0);"><i class="bullet"></i> Marine Business <i class="fa fa-angle-right"></i></a>
			<div class="sbmn">
				<?php echo tep_admin_files_boxes('import-marinebusiness-altas.php', '<i class="bullet"></i> Importador'); ?>
				<?php echo tep_admin_files_boxes('Actualizador_precios_marinebusiness.php', '<i class="bullet"></i> Actualizador precios'); ?>
			</div>
		</div>
		<div>
			<a class="prnt" href="javascript:void(0);"><i class="bullet"></i> Yachticon <i class="fa fa-angle-right"></i></a>
			<div class="sbmn">
				<?php echo tep_admin_files_boxes('import-yachticon-altas.php', '<i class="bullet"></i> Importador'); ?>
				<?php echo tep_admin_files_boxes('Actualizador_precios_yachticon.php', '<i class="bullet"></i> Actualizador precios'); ?>
			</div>
		</div>
		<div>
			<a class="prnt" href="javascript:void(0);"><i class="bullet"></i> Vetus <i class="fa fa-angle-right"></i></a>
			<div class="sbmn">
				<?php echo tep_admin_files_boxes('import-vetus-altas.php', '<i class="bullet"></i> Importador'); ?>
			</div>
		</div>
		<div>
			<a class="prnt" href="javascript:void(0);"><i class="bullet"></i> Lalizas <i class="fa fa-angle-right"></i></a>
			<div class="sbmn">
				<?php echo tep_admin_files_boxes('import-lalizas-altas.php', '<i class="bullet"></i> Importador'); ?>
				<?php echo tep_admin_files_boxes('Actualizador_precios_lalizas.php', '<i class="bullet"></i> Actualizador precios'); ?>
			</div>
		</div>
		<div>
			<a class="prnt" href="javascript:void(0);"><i class="bullet"></i> Motomarine <i class="fa fa-angle-right"></i></a>
			<div class="sbmn">
				<?php echo tep_admin_files_boxes('import-motomarine-altas.php', '<i class="bullet"></i> Importador'); ?>
			</div>
		</div>
		<div>
			<a class="prnt" href="javascript:void(0);"><i class="bullet"></i> Foresti &amp; Suardi <i class="fa fa-angle-right"></i></a>
			<div class="sbmn">
				<?php echo tep_admin_files_boxes('import-forestisuardi-altas.php', '<i class="bullet"></i> Importador'); ?>
			</div>
		</div>
	</div>
</div>
