<?php

namespace Oscdenox\Domain\Order\Service;

use AddonDomainEvent\Order\Application\OrderChangeStatus;

class OrderStatusChanger {
	public function change(
		int    $orderId,
		int    $oldStatus,
		int    $newStatus,
		string $comments = '',
		bool   $notifyCustomer = false
	): void {
		if ($oldStatus === $newStatus && $comments === '') {
			return;
		}

		// 1) Actualizar tabla orders
		tep_db_query("
            UPDATE " . TABLE_ORDERS . "
            SET orders_status = " . (int)$newStatus . ",
                last_modified = NOW()
            WHERE orders_id = " . (int)$orderId . "
        ");

		// 2) Insertar en historial
		tep_db_query("
            INSERT INTO " . TABLE_ORDERS_STATUS_HISTORY . "
                (orders_id, orders_status_id, date_added, customer_notified, comments)
            VALUES (
                " . (int)$orderId . ",
                " . (int)$newStatus . ",
                NOW(),
                " . ($notifyCustomer ? 1 : 0) . ",
                '" . tep_db_input($comments) . "'
            )
        ");

		// 3) Publicar evento de dominio
		$eventData = [
			'order_id'   => $orderId,
			'old_status' => $oldStatus,
			'new_status' => $newStatus,
			'comments'   => $comments,
			'date'       => date('Y-m-d H:i:s'),
		];

		(new OrderChangeStatus())(['order_change_status' => [$eventData],]);
	}
}
