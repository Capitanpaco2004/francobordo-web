<?php
/**
 * Endpoint AJAX para aplicar/quitar puntos como descuento en un pedido editado.
 * Llamado desde el widget "Aplicar puntos del cliente" en edit_orders.php.
 *
 * POST params:
 *   oID    (int)   — orders_id
 *   pts    (int)   — puntos a aplicar (0 o ausente para quitar)
 *   action (str)   — 'apply' | 'remove'
 *
 * Devuelve JSON {ok, message, redirect}.
 */
require('includes/application_top.php');

header('Content-Type: application/json; charset=utf-8');

$oID    = (int) ($_POST['oID'] ?? 0);
$pts    = (int) round((float) ($_POST['pts'] ?? 0));
$action = trim((string) ($_POST['action'] ?? ''));

if ($oID <= 0) {
    echo json_encode(['ok' => false, 'message' => 'oID inválido']);
    exit;
}

// Obtener cliente y total actual del pedido
$qOrd = tep_db_query("SELECT customers_id FROM " . TABLE_ORDERS . " WHERE orders_id = " . $oID . " LIMIT 1");
$rOrd = tep_db_fetch_array($qOrd);
if (!$rOrd) {
    echo json_encode(['ok' => false, 'message' => 'Pedido no encontrado']);
    exit;
}
$cID = (int) $rOrd['customers_id'];

$rate = (float) (defined('REDEEM_POINT_VALUE') ? REDEEM_POINT_VALUE : 0.05);
if ($rate <= 0) $rate = 0.05;

// Helper: recalcular el total y total_tax desde las filas no-total
function _recalc_order_total($oID) {
    $q = tep_db_query("
        SELECT class, value FROM orders_total
        WHERE orders_id = " . (int) $oID . "
          AND class IN ('ot_subtotal', 'ot_shipping', 'ot_tax', 'ot_redemptions', 'ot_coupon', 'ot_discount')
    ");
    $sum = 0.0;
    while ($r = tep_db_fetch_array($q)) {
        $sum += (float) $r['value'];
    }
    $sum = round($sum, 2);
    $txt = number_format($sum, 4, '.', '');
    tep_db_query("
        UPDATE orders_total
        SET value = " . $sum . ", text = '" . $txt . "'
        WHERE orders_id = " . (int) $oID . " AND class = 'ot_total' LIMIT 1
    ");
    return $sum;
}

if ($action === 'apply') {
    if ($pts <= 0) {
        echo json_encode(['ok' => false, 'message' => 'Indica un número de puntos > 0']);
        exit;
    }

    // Saldo del cliente
    $qC = tep_db_query("SELECT customers_shopping_points FROM " . TABLE_CUSTOMERS . " WHERE customers_id = " . $cID);
    $rC = tep_db_fetch_array($qC);
    $saldoAntes = (int) round((float) $rC['customers_shopping_points']);

    if ($pts > $saldoAntes) {
        echo json_encode(['ok' => false, 'message' => 'El cliente solo tiene ' . number_format($saldoAntes, 0, ',', '.') . ' pts disponibles']);
        exit;
    }

    // Total actual del pedido (IVA inc.)
    $qT = tep_db_query("SELECT value FROM orders_total WHERE orders_id = " . $oID . " AND class = 'ot_total' LIMIT 1");
    $rT = tep_db_fetch_array($qT);
    $totalEur = round((float) $rT['value'], 2);

    $descuentoEur = $pts * $rate;
    if ($descuentoEur > $totalEur) {
        $ptsCap = (int) floor($totalEur / $rate);
        echo json_encode(['ok' => false, 'message' => 'El descuento (' . number_format($descuentoEur, 2, ',', '.') . '€) supera el total del pedido (' . number_format($totalEur, 2, ',', '.') . '€). Máx aplicable: ' . $ptsCap . ' pts']);
        exit;
    }

    // ¿Ya hay puntos aplicados a este pedido?
    $qExisting = tep_db_query("SELECT unique_id, points_pending FROM " . TABLE_CUSTOMERS_POINTS_PENDING . "
        WHERE customer_id = " . $cID . " AND orders_id = " . $oID . "
          AND points_type = 'RD' AND points_status = 4 LIMIT 1");
    if (tep_db_num_rows($qExisting) > 0) {
        echo json_encode(['ok' => false, 'message' => 'Este pedido ya tiene puntos aplicados. Quítalos primero si quieres cambiar la cantidad.']);
        exit;
    }

    $textoDesc = '-' . number_format($descuentoEur, 4, '.', '');

    tep_db_query('START TRANSACTION');

    // Subir sort_order del ot_total para hacer hueco
    tep_db_query("UPDATE orders_total SET sort_order = sort_order + 1 WHERE orders_id = " . $oID . " AND class = 'ot_total' LIMIT 1");

    // Insertar fila ot_redemptions con sort_order anterior del total
    $qSort = tep_db_query("SELECT MAX(sort_order) AS s FROM orders_total WHERE orders_id = " . $oID . " AND class != 'ot_total'");
    $rSort = tep_db_fetch_array($qSort);
    $newSort = (int) $rSort['s'] + 1;
    tep_db_query("INSERT INTO orders_total (orders_id, title, text, value, class, sort_order) VALUES (
        " . $oID . ",
        'Descuento por puntos (" . $pts . " pts):',
        '" . $textoDesc . "',
        " . ((float) -$descuentoEur) . ",
        'ot_redemptions',
        " . $newSort . "
    )");

    // Descontar puntos del cliente
    tep_db_query("UPDATE " . TABLE_CUSTOMERS . "
        SET customers_shopping_points = GREATEST(0, customers_shopping_points - " . $pts . ")
        WHERE customers_id = " . $cID . " LIMIT 1");

    // Registrar canje en customers_points_pending (status=4 = canjeado)
    $sComment = 'Canje manual desde edit_orders del pedido #' . $oID . ' — ' . number_format($descuentoEur, 2, ',', '.') . '€';
    tep_db_query("INSERT INTO " . TABLE_CUSTOMERS_POINTS_PENDING . "
        (customer_id, orders_id, points_pending, date_added, points_status, points_type, points_comment)
        VALUES (" . $cID . ", " . $oID . ", " . (-$pts) . ", NOW(), 4, 'RD', '" . tep_db_input($sComment) . "')");
    $insertId = (int) tep_db_insert_id();

    // Recalcular total
    $nuevoTotal = _recalc_order_total($oID);

    tep_db_query('COMMIT');

    $saldoDespues = $saldoAntes - $pts;

    // Auditoría
    $qChk = tep_db_query("SHOW TABLES LIKE 'customers_points_audit'");
    if (tep_db_num_rows($qChk) > 0) {
        global $login_id;
        $adminEmail = '';
        if (!empty($login_id)) {
            $qA = tep_db_query("SELECT admin_email_address FROM admin WHERE admin_id = " . (int) $login_id . " LIMIT 1");
            $rA = tep_db_fetch_array($qA);
            $adminEmail = $rA['admin_email_address'] ?? '';
        }
        tep_db_perform('customers_points_audit', [
            'admin_id'          => (int) ($login_id ?? 0),
            'admin_email'       => $adminEmail,
            'customer_id'       => $cID,
            'action'            => 'order_apply_points',
            'pending_unique_id' => $insertId,
            'points_delta'      => -$pts,
            'balance_before'    => $saldoAntes,
            'balance_after'     => $saldoDespues,
            'data_after'        => json_encode([
                'orders_id'      => $oID,
                'points_applied' => $pts,
                'discount_eur'   => $descuentoEur,
                'new_total_eur'  => $nuevoTotal,
                'rate'           => $rate,
            ], JSON_UNESCAPED_UNICODE),
            'comment'           => $sComment,
            'notify_sent'       => 0,
            'ip'                => $_SERVER['REMOTE_ADDR'] ?? null,
            'created_at'        => 'now()',
        ]);
    }

    echo json_encode([
        'ok'      => true,
        'message' => 'Aplicados ' . number_format($pts, 0, ',', '.') . ' pts (-' . number_format($descuentoEur, 2, ',', '.') . '€). Nuevo total: ' . number_format($nuevoTotal, 2, ',', '.') . '€',
        'reload'  => true,
    ]);
    exit;
}

if ($action === 'remove') {
    // Buscar el canje aplicado
    $qP = tep_db_query("SELECT unique_id, points_pending FROM " . TABLE_CUSTOMERS_POINTS_PENDING . "
        WHERE customer_id = " . $cID . " AND orders_id = " . $oID . "
          AND points_type = 'RD' AND points_status = 4 LIMIT 1");
    if (tep_db_num_rows($qP) === 0) {
        echo json_encode(['ok' => false, 'message' => 'No hay puntos aplicados a este pedido']);
        exit;
    }
    $rP = tep_db_fetch_array($qP);
    $ptsADevolver = abs((int) $rP['points_pending']);
    $uniqueId = (int) $rP['unique_id'];

    $qC = tep_db_query("SELECT customers_shopping_points FROM " . TABLE_CUSTOMERS . " WHERE customers_id = " . $cID);
    $rC = tep_db_fetch_array($qC);
    $saldoAntes = (int) round((float) $rC['customers_shopping_points']);

    tep_db_query('START TRANSACTION');

    // Borrar fila orders_total ot_redemptions de este pedido
    tep_db_query("DELETE FROM orders_total WHERE orders_id = " . $oID . " AND class = 'ot_redemptions'");

    // Borrar fila customers_points_pending del canje
    tep_db_query("DELETE FROM " . TABLE_CUSTOMERS_POINTS_PENDING . " WHERE unique_id = " . $uniqueId . " LIMIT 1");

    // Devolver puntos al cliente
    tep_db_query("UPDATE " . TABLE_CUSTOMERS . "
        SET customers_shopping_points = customers_shopping_points + " . $ptsADevolver . "
        WHERE customers_id = " . $cID . " LIMIT 1");

    // Recalcular total
    $nuevoTotal = _recalc_order_total($oID);

    tep_db_query('COMMIT');

    // Auditoría
    $qChk = tep_db_query("SHOW TABLES LIKE 'customers_points_audit'");
    if (tep_db_num_rows($qChk) > 0) {
        global $login_id;
        $adminEmail = '';
        if (!empty($login_id)) {
            $qA = tep_db_query("SELECT admin_email_address FROM admin WHERE admin_id = " . (int) $login_id . " LIMIT 1");
            $rA = tep_db_fetch_array($qA);
            $adminEmail = $rA['admin_email_address'] ?? '';
        }
        tep_db_perform('customers_points_audit', [
            'admin_id'       => (int) ($login_id ?? 0),
            'admin_email'    => $adminEmail,
            'customer_id'    => $cID,
            'action'         => 'order_remove_points',
            'points_delta'   => $ptsADevolver,
            'balance_before' => $saldoAntes,
            'balance_after'  => $saldoAntes + $ptsADevolver,
            'data_after'     => json_encode([
                'orders_id'        => $oID,
                'points_returned'  => $ptsADevolver,
                'new_total_eur'    => $nuevoTotal,
            ], JSON_UNESCAPED_UNICODE),
            'comment'        => 'Revertido canje del pedido #' . $oID . ' desde edit_orders',
            'notify_sent'    => 0,
            'ip'             => $_SERVER['REMOTE_ADDR'] ?? null,
            'created_at'     => 'now()',
        ]);
    }

    echo json_encode([
        'ok'      => true,
        'message' => 'Devueltos ' . number_format($ptsADevolver, 0, ',', '.') . ' pts al cliente. Nuevo total: ' . number_format($nuevoTotal, 2, ',', '.') . '€',
        'reload'  => true,
    ]);
    exit;
}

echo json_encode(['ok' => false, 'message' => 'Acción no reconocida']);
exit;
