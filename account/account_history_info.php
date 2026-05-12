<?php
	// Aplicacion
	include( 'includes/application.php' );

	require(DIR_WS_CLASSES . 'order.php');

	if (!$customerCore->hasLogin()) {
		$navigation->set_snapshot();
		tep_redirect(tep_href_link(FILENAME_LOGIN, '', 'SSL'));
	}

	if( !isset($_GET['order_id']) || (isset($_GET['order_id']) && !is_numeric($_GET['order_id'])) )
		tep_redirect(tep_href_link(FILENAME_ACCOUNT_HISTORY, '', 'SSL'));

	if (isset($_GET['action']) && (tep_db_prepare_input($_GET['action']) == 'cancel' || tep_db_prepare_input($_GET['action']) == 'cancelconfirm')) {
		$order = new order($_GET['order_id']);
		if ($order->info['CFACTUR'] == 'S' || in_array($order->info['orders_status_id'], array(100))) {
			tep_redirect(tep_href_link(FILENAME_ACCOUNT_HISTORY, '', 'SSL'));
		}
	}

	$customer_info_query = tep_db_query("select o.customers_id from " . TABLE_ORDERS . " o, " . TABLE_ORDERS_STATUS . " s where o.orders_id = '". (int)$_GET['order_id'] . "' and o.orders_status = s.orders_status_id and s.language_id = '" . (int)$languages_id . "'");
	$customer_info = tep_db_fetch_array($customer_info_query);

	if( $customer_info['customers_id'] != $customer_id )
		tep_redirect(tep_href_link(FILENAME_ACCOUNT_HISTORY, '', 'SSL'));

	// Begin RMA Returns System - added order status ID to query
	$customer_info_query = tep_db_query("select customers_id, orders_status from " . TABLE_ORDERS . " where orders_id = '". (int)$_GET['order_id'] . "'");
	$customer_info = tep_db_fetch_array($customer_info_query);
	$orders_status = $customer_info['orders_status'];
	if ($customer_info['customers_id'] != $customer_id) {
		tep_redirect(tep_href_link(FILENAME_ACCOUNT_HISTORY, '', 'SSL'));
	}
	// End RMA Returns System

	// Cancelar el pedido
	if (isset($_GET['action']) && tep_db_prepare_input($_GET['action']) == 'cancelconfirm') {
		// Actualizamos el estado
		tep_db_query('UPDATE ' . TABLE_ORDERS . ' SET orders_status = "' . tep_db_input(100) . '", last_modified = now() where orders_id = "' . (int)$_GET['order_id'] . '"');
		tep_db_query('INSERT INTO ' . TABLE_ORDERS_STATUS_HISTORY . ' (orders_id, orders_status_id, date_added, customer_notified, comments) values ("' . (int)$_GET['order_id'] . '", "' . tep_db_input(100) . '", now(), "0", "")');

		$products = tep_db_query('select products_id, products_quantity from ' . TABLE_ORDERS_PRODUCTS . ' where orders_id = "' . (int)$_GET['order_id'] . '"');

		while ($product = tep_db_fetch_array($products)) {
			tep_db_query('UPDATE ' . TABLE_PRODUCTS . ' SET products_quantity = products_quantity + ' . $product['products_quantity'] . ', products_ordered = products_ordered - ' . $product['products_quantity'] . ' where products_id = "' . (int)$product['products_id'] . '"');
		}

		$sql = sprintf('SELECT orders_products_id, products_id, products_quantity FROM orders_products WHERE orders_id = %d', (int)$_GET['order_id']);
		$products_query = tep_db_query($sql);

		while ($product = tep_db_fetch_array($products_query)) {
			$sql = sprintf('SELECT products_options_id, products_options_values_id FROM orders_products_attributes WHERE orders_id = %d AND orders_products_id = %d', (int)$_GET['order_id'], $product['orders_products_id']);
			$attrributes_query = tep_db_query($sql);

			if (tep_db_num_rows($attrributes_query) > 0) {
				$attributes = tep_db_fetch_array($attrributes_query);
				$attributes = array($attributes['products_options_id']=>$attributes['products_options_values_id']);
			} else {
				$attributes = '';
			}

			$cart->add_cart($product['products_id'], $product['products_quantity'], $attributes);
		}

		tep_redirect(tep_href_link(FILENAME_ACCOUNT_HISTORY, 'm=success', 'SSL'));
	}

	require(DIR_WS_LANGUAGES . $language . '/account_history_info.php');

	$breadcrumb->add(NAVBAR_TITLE_1, tep_href_link(FILENAME_ACCOUNT, '', 'SSL'));
	$breadcrumb->add(NAVBAR_TITLE_2, tep_href_link(FILENAME_ACCOUNT_HISTORY, '', 'SSL'));
	$breadcrumb->add(sprintf(NAVBAR_TITLE_3, $_GET['order_id']), tep_href_link(FILENAME_ACCOUNT_HISTORY_INFO, 'order_id=' . $_GET['order_id'], 'SSL'));

	$sql_invoice = "select * from facturas where facturas_pedido_id = '".(int)$oID."'";
	$act_invoice = tep_db_query($sql_invoice);
	while ($row_sql = tep_db_fetch_array($act_invoice))
	{
		$factura = $row_sql['facturas_serie'].'-'.$row_sql['facturas_numero'];
		$fecha = $row_sql['facturas_fecha'];
		$abono = $row_sql['facturas_abono'];
	}

	// Facturas QFacWin disponibles para descarga (sync_facturas_web)
	$qfac_invoices = array();
	$qres = tep_db_query("SELECT id, cserie, nnumfra, cnumfra, fecha, total, is_rectificativa, pdf_filename FROM qfac_invoices WHERE orders_id = '" . (int)$_GET['order_id'] . "' ORDER BY is_rectificativa ASC, fecha ASC, id ASC");
	while ($qrow = tep_db_fetch_array($qres)) {
		$qfac_invoices[] = $qrow;
	}

	// Header
	include( 'account/includes/header.php' );
	
	$order = new order($_GET['order_id']);

	if (isset($_GET['action']) && tep_db_prepare_input($_GET['action']) == 'cancel') {
		include( DIR_THEME_ROOT . 'html/templates/account_history_info_cancel.php' );
	} else {
		include( DIR_THEME_ROOT . 'html/templates/' . basename(__FILE__) );
	}

	// Footer
	include( 'account/includes/footer.php' );
?>
