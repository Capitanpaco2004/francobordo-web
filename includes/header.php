<?php
// check if the 'install' directory exists, and warn of its existence
if (WARN_INSTALL_EXISTENCE == 'true') {
	if (file_exists(dirname($_SERVER['SCRIPT_FILENAME']) . '/install')) {
		echo '<div class="mensaje">' . WARNING_INSTALL_DIRECTORY_EXISTS . '</div>';
	}
}

// check if the session folder is writeable
if (WARN_SESSION_DIRECTORY_NOT_WRITEABLE == 'true') {
	if (STORE_SESSIONS == '') {
		if (!is_dir(tep_session_save_path())) {
			echo '<div class="mensaje">' . WARNING_SESSION_DIRECTORY_NON_EXISTENT . '</div>';

		} else if (!is_writeable(tep_session_save_path())) {
			echo '<div class="mensaje">' . WARNING_SESSION_DIRECTORY_NOT_WRITEABLE . '</div>';
		}
	}
}

// check session.auto_start is disabled
if ((function_exists('ini_get')) && (WARN_SESSION_AUTO_START == 'true')) {
	if (ini_get('session.auto_start') == '1') {
		echo '<div class="mensaje">' . WARNING_SESSION_AUTO_START . '</div>';
	}
}

if ((WARN_DOWNLOAD_DIRECTORY_NOT_READABLE == 'true') && (DOWNLOAD_ENABLED == 'true')) {
	if (!is_dir(DIR_FS_DOWNLOAD)) {
		echo '<div class="mensaje">' . WARNING_DOWNLOAD_DIRECTORY_NON_EXISTENT . '</div>';

	}
}

$moq_pids = [];
$qtb_pids = [];
if (isset($_SESSION['min_order_qty_not_met']) && count($_SESSION['min_order_qty_not_met']) > 0) {
	foreach ($_SESSION['min_order_qty_not_met'] as $moq_key => $moq_pid) {
		if ((int)$moq_pid > 0) {
			$moq_pids[] = (int)$moq_pid;
		}
	}
}

if (isset($_SESSION['qty_blocks_not_met']) && count($_SESSION['qty_blocks_not_met']) > 0) {
	foreach ($_SESSION['qty_blocks_not_met'] as $qtb_key => $qtb_pid) {
		if ((int)$qtb_pid > 0) {
			$qtb_pids[] = (int)$qtb_pid;
		}
	}
}
$moq_qtb_pids = array_merge($moq_pids, $qtb_pids);
$moq_qtb_pids = array_unique($moq_qtb_pids);

if (count($moq_qtb_pids) > 0 && tep_not_null($moq_qtb_pids[0])) {
	if (isset($_SESSION['sppc_customer_group_id']) && $_SESSION['sppc_customer_group_id'] != '0') {
		$customer_group_id = $_SESSION['sppc_customer_group_id'];
	} else {
		$customer_group_id = '0';
	}
	if ($customer_group_id == '0') {
		$product_names_query = tep_db_query("select p.products_id, pd.products_name, p.products_min_order_qty, p.products_qty_blocks from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd where p.products_id in (" . implode(',', $moq_qtb_pids) . ") and p.products_id = pd.products_id and pd.language_id = '" . (int)$languages_id . "'");
	} else {
		$product_names_query = tep_db_query("select pd.products_id, pd.products_name, pg.products_min_order_qty, pg.products_qty_blocks from " . TABLE_PRODUCTS_DESCRIPTION . " pd left join (select products_id, products_min_order_qty, products_qty_blocks from " . TABLE_PRODUCTS_GROUPS . " where customers_group_id = '" . $customer_group_id . "' and products_id in (" . implode(',', $moq_qtb_pids) . ")) pg on pd.products_id = pg.products_id where pd.products_id in (" . implode(',', $moq_qtb_pids) . ") and pd.language_id = '" . (int)$languages_id . "'");
	}
	while ($_product_names = tep_db_fetch_array($product_names_query)) {
		if (in_array($_product_names['products_id'], $moq_pids)) {
			$messageStack->add('cart_notice', sprintf(MINIMUM_ORDER_NOTICE, $_product_names['products_name'], $_product_names['products_min_order_qty']), 'warning');
		}
		if (in_array($_product_names['products_id'], $qtb_pids)) {
			$messageStack->add('cart_notice', sprintf(QUANTITY_BLOCKS_NOTICE, $_product_names['products_name'], $_product_names['products_qty_blocks']), 'warning');
		}
	}
}

if ($messageStack->size('header') > 0) {
	echo $messageStack->output('header');
}
