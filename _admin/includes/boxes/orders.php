<?php use util\event; ?>
<!-- orders //-->
<?php echo tep_admin_files_boxes(FILENAME_ORDERS, '<i class="bullet"></i> Listado de pedidos'); ?>
<?php echo tep_admin_files_boxes('facturas.php', '<i class="bullet"></i> Listado de facturas'); ?>
<?php echo tep_admin_files_boxes(FILENAME_CREATE_ORDER, '<i class="bullet"></i> Crear Pedido'); ?>
<?php echo tep_admin_files_boxes('holding_orders.php', '<i class="bullet"></i> ' . BOX_CUSTOMERS_ORDERS_CHECK); ?>
<?php echo tep_admin_files_boxes(FILENAME_ORDERS_STATUS, '<i class="bullet"></i> ' . BOX_LOCALIZATION_ORDERS_STATUS); ?>
<?php echo tep_admin_files_boxes('update_masive_orders.php', '<i class="bullet"></i> Actualizador masivo de estados'); ?>
<?php echo tep_admin_files_boxes("arreglar_pedidos_qfac.php", "<i class=\"bullet\"></i> Arreglar pedidos QFac"); ?>
<div>
	<a class="prnt" href="javascript:void(0);"><i class="bullet"></i> RMA <i class="fa fa-angle-right"></i></a>
	<div class="sbmn">
		<?php echo tep_admin_files_boxes('rma.php', '<i class="bullet"></i> Listado Devoluciones'); ?>
		<?php echo tep_admin_files_boxes('rma.php', '<i class="bullet"></i> ' . BOX_RETURNS_REASONS, 'action=options-return'); ?>
		<?php echo tep_admin_files_boxes('rma.php', '<i class="bullet"></i> Tipos de retorno (Envío)', 'action=types-return'); ?>
		<?php echo tep_admin_files_boxes('rma.php', '<i class="bullet"></i> Métodos de reembolso (Pago)', 'action=payment-method'); ?>
		<?php echo tep_admin_files_boxes('rma.php', '<i class="bullet"></i> Estados', 'action=status'); ?>
	</div>
</div>
<?php echo tep_admin_files_boxes('affiliates.php', '<i class="bullet"></i> Afiliados'); ?>

<?php echo implode('', event::getInstance()->execute('back_office_includes_boxes_orders_after')); ?>
<!-- orders_eof //-->
