<?php

require($sPathModule . '/classes/order_holding_order.php');

// Validar existencia del pedido
if (empty($ocID)) {
	$messageStack->add('Pedido no encontrado.', 'error');
	tep_redirect(tep_href_link($sUrlPage));
}

$order = new order_holding_order($ocID);

$aButtons = [
	['title' => 'Volver atrás', 'href' => tep_href_link($sUrlPage), 'icon' => 'fa-arrow-left', 'anchor_class' => 'gris'],
	['title' => 'Mover a pedido', 'href' => tep_href_link($sUrlPage, 'action=move&ocID=' . $ocID), 'icon' => 'fa-cart-plus', 'anchor_class' => 'verde', 'extra' => 'data-confirm="¿Realmente deseas mover este pedido?"'],
	['title' => 'Eliminar pedido', 'href' => tep_href_link($sUrlPage, 'action=delete&ocID=' . $ocID), 'icon' => 'fa-trash-alt', 'anchor_class' => 'rojo', 'extra' => 'data-confirm="¿Realmente deseas eliminar este pedido?"']
];

$sHtmlModule = includeTemplate($sPathModule . '/templates/view_order.php', compact('order', 'orders_status_array', 'currencies', 'ocID'));
