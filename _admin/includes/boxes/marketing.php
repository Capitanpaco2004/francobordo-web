<?php
/**
 * Marketing/CRM — Admin menu box.
 *
 * Loaded by _admin/includes/header_navigation.php (via DIR_WS_BOXES).
 * Each item is rendered with tep_admin_files_boxes(filename, label),
 * which checks the user's admin group against admin_files.admin_groups_id.
 *
 * To add a new tool, INSERT in admin_files first (admin_files_to_boxes = id of
 * the marketing.php box row) then add a line here.
 *
 * Created: 2026-05-18
 */
?>
<?php echo tep_admin_files_boxes('salesmanago_config.php', '<i class="bullet"></i> Sales Manago'); ?>
