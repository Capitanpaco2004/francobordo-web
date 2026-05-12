<?php
use util\HoldingOrderManager;

$ids = array_filter((array) ($_POST['ocID'] ?? $_GET['ocID'] ?? []));

// Verifica si es individual o múltiple
if (!empty($ids)) {
	foreach ($ids as $ocID) {
		HoldingOrderManager::moveHoldingOrderManager((int)$ocID);
	}
	$messageStack->addSession('success', 'Pedido(s) Salvaguardado(s) movido(s) correctamente al Listado de Pedidos.', 'success');
} else {
	$messageStack->addSession('error', 'No se seleccionó ningún pedido para mover.', 'error');
}

tep_redirect(tep_href_link($sUrlPage));
