<?php use util\event; ?>
<!-- promotions //-->
<div>
	<a class="prnt" href="javascript:void(0);"><i class="bullet"></i> Sistema de Puntos <i class="fa fa-angle-right"></i></a>
	<div class="sbmn">
		<?php echo tep_admin_files_boxes(FILENAME_CUSTOMERS_POINTS, '<i class="bullet"></i> ' . BOX_CUSTOMERS_POINTS); ?>
		<?php echo tep_admin_files_boxes(FILENAME_CUSTOMERS_POINTS_PENDING, '<i class="bullet"></i> ' . BOX_CUSTOMERS_POINTS_PENDING); ?>
		<?php echo tep_admin_files_boxes(FILENAME_CUSTOMERS_POINTS_REFERRAL, '<i class="bullet"></i> ' . BOX_CUSTOMERS_POINTS_REFERRAL); ?>
	</div>
</div>
<?php echo tep_admin_files_boxes(FILENAME_DISCOUNT_COUPONS, '<i class="bullet"></i> ' . BOX_CATALOG_DISCOUNT_COUPONS); ?>
<?php echo tep_admin_files_boxes(FILENAME_PROMOTIONS, '<i class="bullet"></i> Promociones de productos'); ?>
<?php echo tep_admin_files_boxes('notificaciones.php', '<i class="bullet"></i> Notificaciones'); ?>
<?php echo tep_admin_files_boxes(FILENAME_NEWSLETTERS, '<i class="bullet"></i> ' . BOX_TOOLS_NEWSLETTER_MANAGER); ?>
<?php echo tep_admin_files_boxes('editor_boletines.php', '<i class="bullet"></i> Editor Boletines HTML'); ?>
<?php echo tep_admin_files_boxes(FILENAME_BANNER_MANAGER, '<i class="bullet"></i> ' . BOX_TOOLS_BANNER_MANAGER); ?>
<?php echo tep_admin_files_boxes('seo_files.php', '<i class="bullet"></i> Archivos SEO'); ?>
<?php echo tep_admin_files_boxes('feedmachine_admin.php', '<i class="bullet"></i> FeedMachine'); ?>
<?php echo tep_admin_files_boxes('sincronizar_phplist.php', '<i class="bullet"></i> Sincronizar PHPList'); ?>
<?php echo tep_admin_files_boxes(FILENAME_WHOS_ONLINE, '<i class="bullet"></i> ' . BOX_TOOLS_WHOS_ONLINE); ?>

<?php echo implode('', event::getInstance()->execute('back_office_includes_boxes_promocion_after')); ?>
<?php echo implode('', event::getInstance()->execute('back_office_includes_boxes_promotions_after')); ?>
<!-- promotions_eof //-->
