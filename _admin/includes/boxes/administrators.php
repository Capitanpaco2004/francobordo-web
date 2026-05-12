<?php if( tep_admin_check_boxes( 'administrators.php' ) ): ?>
	<div>
		<a class="prnt" href="javascript:void(0);"><i class="bullet"></i> <?php echo BOX_SYSTEM_ADMINS; ?> <i class="fa fa-angle-right"></i></a>
		<div class="sbmn">
			<?php echo tep_admin_files_boxes(FILENAME_ADMIN_MEMBERS, '<i class="bullet"></i> ' . BOX_SYSTEM_ADMIN_LIST); ?>
			<?php echo tep_admin_files_boxes(FILENAME_ADMIN_ACCOUNT, '<i class="bullet"></i> ' . BOX_SYSTEM_ACCOUNT_EDIT); ?>
			<?php echo tep_admin_files_boxes('log.php', '<i class="bullet"></i> ' . BOX_SYSTEM_ADMIN_LOG); ?>
		</div>
	<div>
<?php endif; ?>
