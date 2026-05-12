<?php

use util\HoldingOrder;
use util\HoldingOrderManager;

require_once 'includes/application_top.php';

// Inicializa la clase HoldingOrder
$holdingOrder = new HoldingOrder();

// Primero ejecuta la búsqueda masiva de los últimos 30 días
$holdingOrder->matchRecentTransactions();

// Ejecuta el método que actualiza transacciones Redsys
$holdingOrder->updateRedsysTransactions();

$holdingOrderManager = new HoldingOrderManager();

// Elimina todo lo que tenga mas de 3 meses
$holdingOrderManager->removeOldHoldingOrders();

// Eliminar duplicados de holding_orders que ya existan en orders
$holdingOrderManager->removeDuplicateHoldingOrders();

// Unifica los holding_orders duplicados que sean iguales
$holdingOrderManager->unifyHoldingOrdersDuplicates();
