<?php use util\event; ?>
<!-- tools //-->
<?php echo tep_admin_files_boxes(FILENAME_SHIPPING_PREDICTION, '<i class="bullet"></i> Predicción de envío'); ?>
<?php echo tep_admin_files_boxes('delivery_estimate.php', '<i class="bullet"></i> Fecha estimada de entrega'); ?>
<?php echo tep_admin_files_boxes(FILENAME_QTPRODOCTOR, '<i class="bullet"></i> QTPro Doctor'); ?>
<?php echo tep_admin_files_boxes(FILENAME_RECOVER_CART_SALES, '<i class="bullet"></i> Recuperador de Carritos'); ?>
<?php echo tep_admin_files_boxes('integracion_amazon.php', '<i class="bullet"></i> Integración de productos Amazon'); ?>
<?php echo tep_admin_files_boxes('actualizador_pedidos_amazon.php', '<i class="bullet"></i> Actualizador de pedidos Amazon'); ?>
<?php echo tep_admin_files_boxes(FILENAME_WHOS_ONLINE, '<i class="bullet"></i> ' . BOX_TOOLS_WHOS_ONLINE); ?>

<?php echo tep_admin_files_boxes("scraper_reports.php", '<i class="bullet"></i> &#x1F6E1; Scraper Reports'); ?>

<?php echo tep_admin_files_boxes('google_merchant_envios.php', '<i class="bullet"></i> Google Merchant &mdash; Env&iacute;os'); ?>

<?php echo tep_admin_files_boxes('google_merchant_catalogo.php', '<i class="bullet"></i> Google Merchant &mdash; Cat&aacute;logo'); ?>

<?php echo implode('', event::getInstance()->execute('back_office_includes_boxes_tools_after')); ?>
<!-- tools_eof //-->
