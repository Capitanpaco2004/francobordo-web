<?php
use util\HoldingOrderManager;

$ids = array_filter((array) ($_POST['ocID'] ?? $_GET['ocID'] ?? []));

if (!empty($ids)) {
	foreach ($ids as $ocID) {
		HoldingOrderManager::removeOrderFromHoldingOrder((int)$ocID);
	}

	$messageStack->addSession(
		'success',
		'Pedido(s) Salvaguardado(s) eliminado(s) correctamente: #' . implode(', #', $ids),
		'success'
	);
} else {
	$messageStack->addSession('error', 'No se seleccionó ningún pedido para eliminar.', 'error');
}

tep_redirect(tep_href_link($sUrlPage));
