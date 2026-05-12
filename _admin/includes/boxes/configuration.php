<?php use util\event; ?>
<?php include(DIR_WS_BOXES . 'administrators.php'); ?>
<?php echo tep_admin_files_boxes(FILENAME_CONFIGURATION, '<i class="bullet"></i> Configuración General'); ?>
<?php echo tep_admin_files_boxes('rgpd.php', '<i class="bullet"></i> Configurar RGPD'); ?>
<?php echo tep_admin_files_boxes('email_smtp.php', '<i class="bullet"></i> ' . BOX_SYSTEM_EMAIL_CONFIGURATION); ?>
<?php echo tep_admin_files_boxes('ups_configure.php', '<i class="bullet"></i> Configuración UPS'); ?>
<?php echo tep_admin_files_boxes('404.php', '<i class="bullet"></i> Configurar 404'); ?>
<?php echo tep_admin_files_boxes('500.php', '<i class="bullet"></i> Configurar 500'); ?>
<?php echo tep_admin_files_boxes('stores.php', '<i class="bullet"></i> Configurar Tiendas'); ?>
<?php echo tep_admin_files_boxes(FILENAME_SERVER_INFO, '<i class="bullet"></i> Server Info'); ?>

<?php echo implode('', event::getInstance()->execute('back_office_includes_boxes_configuration_after')); ?>
