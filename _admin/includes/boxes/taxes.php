<!-- taxes //-->
<?php echo tep_admin_files_boxes(FILENAME_COUNTRIES, '<i class="bullet"></i> ' . BOX_TAXES_COUNTRIES); ?>
<?php echo tep_admin_files_boxes(FILENAME_ZONES, '<i class="bullet"></i> ' . BOX_TAXES_ZONES); ?>
<?php echo tep_admin_files_boxes('cities.php', '<i class="bullet"></i> Ciudades'); ?>
<?php echo tep_admin_files_boxes(FILENAME_GEO_ZONES, '<i class="bullet"></i> ' . BOX_TAXES_GEO_ZONES); ?>
<?php echo tep_admin_files_boxes(FILENAME_TAX_CLASSES, '<i class="bullet"></i> ' . BOX_TAXES_TAX_CLASSES); ?>
<?php echo tep_admin_files_boxes(FILENAME_TAX_RATES, '<i class="bullet"></i> ' . BOX_TAXES_TAX_RATES); ?>
<?php echo tep_admin_files_boxes('geo_zones_type.php', '<i class="bullet"></i> ' . BOX_TAXES_GEO_ZONES_TYPE); ?>
<div>
	<a class="prnt" href="javascript:void(0);"><i class="bullet"></i> Localización <i class="fa fa-angle-right"></i></a>
	<div class="sbmn">
		<?php echo tep_admin_files_boxes(FILENAME_CURRENCIES, '<i class="bullet"></i> ' . BOX_LOCALIZATION_CURRENCIES); ?>
		<?php echo tep_admin_files_boxes(FILENAME_LANGUAGES, '<i class="bullet"></i> ' . BOX_LOCALIZATION_LANGUAGES); ?>
	</div>
</div>
<!-- taxes_eof //-->
