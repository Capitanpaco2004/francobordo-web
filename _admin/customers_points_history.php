<?php
/*
 * Historial de puntos de un cliente — vista a pantalla completa con edición.
 * Creada 2026-05-17. Edición/añadir/confirmar añadidos 2026-05-17 (rev2).
 *
 * Parámetro: cID (int)
 * Acciones POST: add (añadir puntos), delete (eliminar fila), confirm (confirmar pendiente)
 * Permisos: registrado en admin_files.
 *
 * Lógica de saldo: las filas canjeadas (status=4) NO se pueden borrar. Al eliminar
 * cualquier otra fila CONFIRMADA o CADUCADA o PENDIENTE, se recalcula el saldo desde
 * el historial: saldo = SUM(points_pending) WHERE status IN (2,4) OR points_type='EX'.
 */

require('includes/application_top.php');
require(DIR_WS_CLASSES . 'currencies.php');
$currencies = new currencies();

$cID = (int) ($_GET['cID'] ?? 0);
if ($cID <= 0) {
    tep_redirect(tep_href_link(FILENAME_CUSTOMERS_POINTS));
}

// ---- Helper: registrar acción admin en customers_points_audit ----------------
function _audit_log($action, $cID, $extras = []) {
    global $login_id;
    static $admin_email = null;
    if ($admin_email === null) {
        $q = tep_db_query("SELECT admin_email_address FROM admin WHERE admin_id = " . (int) ($login_id ?? 0) . " LIMIT 1");
        $r = tep_db_fetch_array($q);
        $admin_email = $r['admin_email_address'] ?? '';
    }
    $fields = [
        'admin_id'          => (int) ($login_id ?? 0),
        'admin_email'       => $admin_email,
        'customer_id'       => (int) $cID,
        'action'            => $action,
        'pending_unique_id' => $extras['pending_unique_id'] ?? null,
        'points_delta'      => $extras['points_delta']      ?? null,
        'balance_before'    => $extras['balance_before']    ?? null,
        'balance_after'     => $extras['balance_after']     ?? null,
        'data_before'       => isset($extras['data_before']) ? json_encode($extras['data_before'], JSON_UNESCAPED_UNICODE) : null,
        'data_after'        => isset($extras['data_after'])  ? json_encode($extras['data_after'],  JSON_UNESCAPED_UNICODE) : null,
        'comment'           => $extras['comment']           ?? null,
        'notify_sent'       => !empty($extras['notify_sent']) ? 1 : 0,
        'ip'                => $_SERVER['REMOTE_ADDR']      ?? null,
        'created_at'        => 'now()',
    ];
    tep_db_perform('customers_points_audit', $fields);
}

// ---- Helper: aplicar delta directo al saldo (sin recalcular desde historial) -
// IMPORTANTE: `recalcular_saldo()` se eliminó el 2026-05-17 — causaba que se restauraran
// puntos ya canjeados en clientes con descuadre histórico (caso de los 41.096 clientes).
// Ahora cada acción modifica el saldo en su delta directo, no recalcula desde cero.
function aplicar_delta_saldo($cID, $delta) {
    if ($delta == 0) return _leer_saldo($cID);
    tep_db_query("
        UPDATE " . TABLE_CUSTOMERS . "
        SET customers_shopping_points = GREATEST(0, customers_shopping_points + (" . (float) $delta . "))
        WHERE customers_id = " . (int) $cID . " LIMIT 1
    ");
    return _leer_saldo($cID);
}
function _leer_saldo($cID) {
    $r = tep_db_query("SELECT customers_shopping_points FROM " . TABLE_CUSTOMERS . " WHERE customers_id = " . (int) $cID);
    $row = tep_db_fetch_array($r);
    return (int) round((float) $row['customers_shopping_points']);
}

// ---- Flash message via $_SESSION + PRG (POST/Redirect/GET) -------------------
// Recupera mensaje flash de la sesión y lo limpia. Evita "Fila no encontrada" tras F5.
$sMessage     = $_SESSION['cph_flash_msg']  ?? '';
$sMessageType = $_SESSION['cph_flash_type'] ?? 'info';
unset($_SESSION['cph_flash_msg'], $_SESSION['cph_flash_type']);

// Helper: setear flash y redirigir a GET (evita doble submit en refresh)
function _flash_redirect($msg, $type, $cID) {
    $_SESSION['cph_flash_msg']  = $msg;
    $_SESSION['cph_flash_type'] = $type;
    tep_redirect(tep_href_link('customers_points_history.php', 'cID=' . (int) $cID));
}

// ---- Procesar acciones POST --------------------------------------------------
$action = $_POST['action'] ?? '';

if ($action === 'add' && isset($_POST['cID']) && (int) $_POST['cID'] === $cID) {
    $nPuntos   = (int) round((float) ($_POST['puntos'] ?? 0));
    $nImporte  = (float) ($_POST['importe'] ?? 0);
    $sComment  = trim((string) ($_POST['comentario'] ?? ''));
    $bNotify   = !empty($_POST['notify']);
    $sExpireIn = trim((string) ($_POST['expires'] ?? ''));   // YYYY-MM-DD o vacío
    // Si no llegan puntos pero sí importe, convertir a puntos: puntos = importe / REDEEM_POINT_VALUE
    if ($nPuntos === 0 && $nImporte != 0 && (float) REDEEM_POINT_VALUE > 0) {
        $nPuntos = (int) round($nImporte / (float) REDEEM_POINT_VALUE);
    }
    if ($nPuntos === 0) {
        $sMessage = 'Debes indicar un número de puntos o un importe distinto de 0.';
        $sMessageType = 'error';
    } else {
        if ($sComment === '') $sComment = 'Puntos manuales';
        $sCommentEsc = tep_db_input($sComment);
        // Validar fecha de caducidad (opcional)
        $sExpireToUse = '';
        if ($sExpireIn !== '') {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $sExpireIn) && strtotime($sExpireIn) !== false) {
                $sExpireToUse = $sExpireIn;
            } else {
                $sMessage = 'Fecha de caducidad inválida (usa YYYY-MM-DD).';
                $sMessageType = 'error';
            }
        }
        try {
            // Saldo antes
            $r = tep_db_query("SELECT customers_shopping_points FROM " . TABLE_CUSTOMERS . " WHERE customers_id = " . $cID);
            $row = tep_db_fetch_array($r);
            $saldoAntes = (int) round((float) $row['customers_shopping_points']);

            tep_db_query('START TRANSACTION');
            tep_db_query("
                INSERT INTO " . TABLE_CUSTOMERS_POINTS_PENDING . "
                  (customer_id, orders_id, points_pending, date_added, points_status, points_type, points_comment)
                VALUES (" . $cID . ", 0, " . $nPuntos . ", NOW(), 2, 'SP', '" . $sCommentEsc . "')
            ");
            $insertId = (int) tep_db_insert_id();
            // Aplicar fecha de caducidad: usar la elegida o el default POINTS_AUTO_EXPIRES
            if ($sExpireToUse !== '') {
                tep_db_query("
                    UPDATE " . TABLE_CUSTOMERS . "
                    SET customers_points_expires = '" . $sExpireToUse . "'
                    WHERE customers_id = " . $cID . " LIMIT 1
                ");
            } elseif (tep_not_null(POINTS_AUTO_EXPIRES)) {
                $sExpireToUse = date('Y-m-d', strtotime('+' . (int) POINTS_AUTO_EXPIRES . ' month'));
                tep_db_query("
                    UPDATE " . TABLE_CUSTOMERS . "
                    SET customers_points_expires = '" . $sExpireToUse . "'
                    WHERE customers_id = " . $cID . " LIMIT 1
                ");
            }
            // Delta directo (no recalcular: respeta saldo histórico real del cliente)
            $saldoDespues = aplicar_delta_saldo($cID, $nPuntos);
            tep_db_query('COMMIT');

            $sMessage     = ($nPuntos > 0 ? 'Añadidos +' : 'Restados ') . abs($nPuntos) . ' puntos al cliente.';
            if ($sExpireToUse !== '') {
                $sMessage .= ' Caducidad: ' . tep_date_short($sExpireToUse) . '.';
            }
            $sMessageType = 'success';

            _audit_log('add', $cID, [
                'pending_unique_id' => $insertId,
                'points_delta'      => $nPuntos,
                'balance_before'    => $saldoAntes,
                'balance_after'     => $saldoDespues,
                'data_after'        => ['points_pending' => $nPuntos, 'comment' => $sComment, 'status' => 2, 'type' => 'SP', 'expires' => $sExpireToUse],
                'comment'           => $sComment . ($sExpireToUse ? ' (caducidad ' . $sExpireToUse . ')' : ''),
                'notify_sent'       => $bNotify,
            ]);

            if ($bNotify) {
                $cliQ = tep_db_query("
                    SELECT customers_id, customers_gender, customers_firstname, customers_lastname,
                           customers_email_address, customers_shopping_points, customers_points_expires
                    FROM " . TABLE_CUSTOMERS . " WHERE customers_id = " . $cID . " LIMIT 1
                ");
                $c = tep_db_fetch_array($cliQ);
                $expireMsg = tep_not_null(POINTS_AUTO_EXPIRES) && tep_not_null($c['customers_points_expires'])
                    ? sprintf(EMAIL_TEXT_EXPIRE, tep_date_short($c['customers_points_expires']))
                    : null;
                tep_send_points_notification([
                    'customer_id'         => (int) $c['customers_id'],
                    'customers_email'     => $c['customers_email_address'],
                    'customers_gender'    => $c['customers_gender'],
                    'customers_firstname' => $c['customers_firstname'],
                    'customers_lastname'  => $c['customers_lastname'],
                    'points_delta'        => $nPuntos,
                    'new_balance'         => (int) $c['customers_shopping_points'],
                    'subject'             => EMAIL_TEXT_SUBJECT,
                    'comment'             => $sComment,
                    'expire_msg'          => $expireMsg,
                ]);
                $sMessage .= ' Email enviado a ' . htmlspecialchars($c['customers_email_address']) . '.';
            }
        } catch (\Throwable $e) {
            tep_db_query('ROLLBACK');
            $sMessage     = 'Error: ' . $e->getMessage();
            $sMessageType = 'error';
        }
    }
}

// Helper: enviar email de cambio de saldo al cliente (delta = puntos sumados o restados)
function _notificar_cambio_saldo($cID, $delta, $comment) {
    if ($delta == 0) return;
    $cliQ = tep_db_query("
        SELECT customers_id, customers_gender, customers_firstname, customers_lastname,
               customers_email_address, customers_shopping_points, customers_points_expires
        FROM " . TABLE_CUSTOMERS . " WHERE customers_id = " . (int) $cID . " LIMIT 1
    ");
    $c = tep_db_fetch_array($cliQ);
    if (!$c) return;
    $expireMsg = tep_not_null(POINTS_AUTO_EXPIRES) && tep_not_null($c['customers_points_expires'])
        ? sprintf(EMAIL_TEXT_EXPIRE, tep_date_short($c['customers_points_expires']))
        : null;
    tep_send_points_notification([
        'customer_id'         => (int) $c['customers_id'],
        'customers_email'     => $c['customers_email_address'],
        'customers_gender'    => $c['customers_gender'],
        'customers_firstname' => $c['customers_firstname'],
        'customers_lastname'  => $c['customers_lastname'],
        'points_delta'        => (int) $delta,
        'new_balance'         => (int) $c['customers_shopping_points'],
        'subject'             => EMAIL_TEXT_SUBJECT,
        'comment'             => $comment,
        'expire_msg'          => $expireMsg,
    ]);
}

if ($action === 'delete' && isset($_POST['uID'], $_POST['cID']) && (int) $_POST['cID'] === $cID) {
    $uID     = (int) $_POST['uID'];
    $bNotify = !empty($_POST['notify']);
    $sMotivo = trim((string) ($_POST['motivo'] ?? ''));
    // Snapshot completo de la fila antes de borrar (para auditoría)
    $checkQ = tep_db_query("
        SELECT unique_id, orders_id, points_pending, points_status, points_type, points_comment, date_added
        FROM " . TABLE_CUSTOMERS_POINTS_PENDING . "
        WHERE unique_id = " . $uID . " AND customer_id = " . $cID . " LIMIT 1
    ");
    $check = tep_db_fetch_array($checkQ);
    if (!$check) {
        $sMessage     = 'Fila no encontrada.';
        $sMessageType = 'error';
    } elseif ((int) $check['points_status'] === 4) {
        $sMessage     = 'Las filas canjeadas no se pueden eliminar (los puntos ya están aplicados).';
        $sMessageType = 'error';
    } else {
        try {
            // Capturar saldo antes para calcular delta
            $r = tep_db_query("SELECT customers_shopping_points FROM " . TABLE_CUSTOMERS . " WHERE customers_id = " . $cID);
            $row = tep_db_fetch_array($r);
            $saldoAntes = (int) round((float) $row['customers_shopping_points']);

            // Delta directo según el tipo de fila que se elimina:
            //  - status=2 (confirmado, contaba al saldo): restar points_pending
            //  - type='EX' (caducado, points_pending negativo): restar (efectivamente devuelve los puntos caducados)
            //  - status=1 o 3 (pending o cancelado, no contaban): no tocar saldo
            $iStatusOrig = (int) $check['points_status'];
            $sTypeOrig   = (string) $check['points_type'];
            $nPtsOrig    = (float) $check['points_pending'];
            $nDeltaSaldo = ($iStatusOrig === 2 || $sTypeOrig === 'EX') ? -$nPtsOrig : 0;

            tep_db_query('START TRANSACTION');
            tep_db_query("
                DELETE FROM " . TABLE_CUSTOMERS_POINTS_PENDING . "
                WHERE unique_id = " . $uID . " AND customer_id = " . $cID . " LIMIT 1
            ");
            $saldoDespues = aplicar_delta_saldo($cID, $nDeltaSaldo);
            tep_db_query('COMMIT');

            $delta = $saldoDespues - $saldoAntes;
            $sMessage     = 'Movimiento eliminado. Saldo: ' . $saldoAntes . ' → ' . $saldoDespues . ' pts.';
            $sMessageType = 'success';

            if ($bNotify && $delta != 0) {
                _notificar_cambio_saldo($cID, $delta, 'Ajuste manual en tu historial de puntos');
                $sMessage .= ' Email enviado al cliente.';
            }

            $sAuditComment = 'Eliminada fila pending #' . $uID;
            if ((int) $check['orders_id'] > 0) {
                $sAuditComment .= ' del pedido #' . (int) $check['orders_id'];
            }
            if ($sMotivo !== '') {
                $sAuditComment .= ' — Motivo: ' . $sMotivo;
            }
            _audit_log('delete', $cID, [
                'pending_unique_id' => $uID,
                // Delta de la OPERACIÓN sobre la fila (puntos eliminados, en negativo).
                // No usamos el diff del saldo: si la fila era pending o cancelada no afectaba al saldo
                // pero la operación sí cambió N puntos en el historial. El efecto neto al saldo está
                // en balance_before→balance_after.
                'points_delta'      => -$nPtsOrig,
                'balance_before'    => $saldoAntes,
                'balance_after'     => $saldoDespues,
                'data_before'       => $check,
                'data_after'        => null,
                'comment'           => $sAuditComment,
                'notify_sent'       => $bNotify,
            ]);
        } catch (\Throwable $e) {
            tep_db_query('ROLLBACK');
            $sMessage     = 'Error: ' . $e->getMessage();
            $sMessageType = 'error';
        }
    }
}

if ($action === 'modify' && isset($_POST['uID'], $_POST['cID']) && (int) $_POST['cID'] === $cID) {
    $uID      = (int) $_POST['uID'];
    $nNuevos  = (int) round((float) ($_POST['nuevos'] ?? 0));
    $bNotify  = !empty($_POST['notify']);
    $sMotivo  = trim((string) ($_POST['motivo'] ?? ''));

    $checkQ = tep_db_query("
        SELECT unique_id, orders_id, points_pending, points_status, points_type, points_comment, date_added
        FROM " . TABLE_CUSTOMERS_POINTS_PENDING . "
        WHERE unique_id = " . $uID . " AND customer_id = " . $cID . " LIMIT 1
    ");
    $check = tep_db_fetch_array($checkQ);
    if (!$check) {
        $sMessage     = 'Fila no encontrada.';
        $sMessageType = 'error';
    } elseif ((int) $check['points_status'] === 4) {
        $sMessage     = 'Las filas canjeadas no se pueden modificar.';
        $sMessageType = 'error';
    } else {
        try {
            $r = tep_db_query("SELECT customers_shopping_points FROM " . TABLE_CUSTOMERS . " WHERE customers_id = " . $cID);
            $row = tep_db_fetch_array($r);
            $saldoAntes = (int) round((float) $row['customers_shopping_points']);

            $iStatusOrig = (int) $check['points_status'];
            $sTypeOrig   = (string) $check['points_type'];
            $nPtsAntes   = (int) round((float) $check['points_pending']);
            $nDiff       = $nNuevos - $nPtsAntes;   // diferencia que se aplicará al saldo (si la fila contaba)
            $nDeltaSaldo = ($iStatusOrig === 2 || $sTypeOrig === 'EX') ? $nDiff : 0;

            tep_db_query('START TRANSACTION');
            tep_db_query("
                UPDATE " . TABLE_CUSTOMERS_POINTS_PENDING . "
                SET points_pending = " . $nNuevos . "
                WHERE unique_id = " . $uID . " AND customer_id = " . $cID . " LIMIT 1
            ");
            $saldoDespues = aplicar_delta_saldo($cID, $nDeltaSaldo);
            tep_db_query('COMMIT');

            $delta = $saldoDespues - $saldoAntes;
            $sMessage     = 'Fila modificada: ' . $nPtsAntes . ' → ' . $nNuevos . ' pts. Saldo: ' . $saldoAntes . ' → ' . $saldoDespues . ' pts.';
            $sMessageType = 'success';

            if ($bNotify && $delta != 0) {
                _notificar_cambio_saldo($cID, $delta, $sMotivo !== '' ? $sMotivo : 'Ajuste manual de puntos');
                $sMessage .= ' Email enviado al cliente.';
            }

            $sAuditComment = 'Modificada fila #' . $uID . ': ' . $nPtsAntes . ' → ' . $nNuevos . ' pts';
            if ((int) $check['orders_id'] > 0) {
                $sAuditComment .= ' del pedido #' . (int) $check['orders_id'];
            }
            if ($sMotivo !== '') $sAuditComment .= ' — Motivo: ' . $sMotivo;
            _audit_log('modify', $cID, [
                'pending_unique_id' => $uID,
                // Delta de la OPERACIÓN sobre la fila (nuevos - antes).
                // No usamos el diff del saldo: el efecto neto al saldo está en balance_before→balance_after.
                'points_delta'      => $nDiff,
                'balance_before'    => $saldoAntes,
                'balance_after'     => $saldoDespues,
                'data_before'       => $check,
                'data_after'        => array_merge($check, ['points_pending' => $nNuevos]),
                'comment'           => $sAuditComment,
                'notify_sent'       => $bNotify,
            ]);
        } catch (\Throwable $e) {
            tep_db_query('ROLLBACK');
            $sMessage     = 'Error: ' . $e->getMessage();
            $sMessageType = 'error';
        }
    }
}

if ($action === 'expires' && isset($_POST['cID']) && (int) $_POST['cID'] === $cID) {
    $sNewExp = trim((string) ($_POST['new_expires'] ?? ''));
    // Vacío = quitar caducidad (NULL). Si hay valor, validar YYYY-MM-DD.
    if ($sNewExp !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sNewExp) || strtotime($sNewExp) === false)) {
        $sMessage     = 'Fecha de caducidad inválida (usa YYYY-MM-DD).';
        $sMessageType = 'error';
    } else {
        try {
            $r = tep_db_query("SELECT customers_points_expires FROM " . TABLE_CUSTOMERS . " WHERE customers_id = " . $cID);
            $row = tep_db_fetch_array($r);
            $sExpAntes = $row['customers_points_expires'];

            if ($sNewExp === '') {
                tep_db_query("UPDATE " . TABLE_CUSTOMERS . " SET customers_points_expires = NULL WHERE customers_id = " . $cID . " LIMIT 1");
                $sMessage = 'Caducidad eliminada (los puntos del cliente quedan SIN fecha de vencimiento).';
            } else {
                tep_db_query("UPDATE " . TABLE_CUSTOMERS . " SET customers_points_expires = '" . tep_db_input($sNewExp) . "' WHERE customers_id = " . $cID . " LIMIT 1");
                $sMessage = 'Caducidad actualizada: '
                          . ($sExpAntes && $sExpAntes !== '0000-00-00' ? tep_date_short($sExpAntes) : 'sin caducidad')
                          . ' → ' . tep_date_short($sNewExp) . '.';
            }
            $sMessageType = 'success';

            _audit_log('expires', $cID, [
                'data_before' => ['customers_points_expires' => $sExpAntes],
                'data_after'  => ['customers_points_expires' => $sNewExp ?: null],
                'comment'     => 'Caducidad cambiada manualmente: ' . ($sExpAntes ?: 'NULL') . ' → ' . ($sNewExp ?: 'NULL'),
            ]);
        } catch (\Throwable $e) {
            $sMessage     = 'Error: ' . $e->getMessage();
            $sMessageType = 'error';
        }
    }
}

if ($action === 'confirm' && isset($_POST['uID'], $_POST['cID']) && (int) $_POST['cID'] === $cID) {
    $uID     = (int) $_POST['uID'];
    $bNotify = !empty($_POST['notify']);
    // Solo confirma si está pendiente (1)
    $checkQ = tep_db_query("
        SELECT unique_id, orders_id, points_pending, points_status, points_type, points_comment, date_added
        FROM " . TABLE_CUSTOMERS_POINTS_PENDING . "
        WHERE unique_id = " . $uID . " AND customer_id = " . $cID . " LIMIT 1
    ");
    $check = tep_db_fetch_array($checkQ);
    if (!$check || (int) $check['points_status'] !== 1) {
        $sMessage     = 'Solo se pueden confirmar filas pendientes.';
        $sMessageType = 'error';
    } else {
        try {
            // Saldo antes
            $r = tep_db_query("SELECT customers_shopping_points FROM " . TABLE_CUSTOMERS . " WHERE customers_id = " . $cID);
            $row = tep_db_fetch_array($r);
            $saldoAntes = (int) round((float) $row['customers_shopping_points']);

            // Confirmar = pasar status 1→2 + sumar points_pending al saldo (delta directo)
            $nPtsConfirm = (int) round((float) $check['points_pending']);
            tep_db_query('START TRANSACTION');
            tep_db_query("
                UPDATE " . TABLE_CUSTOMERS_POINTS_PENDING . "
                SET points_status = 2
                WHERE unique_id = " . $uID . " AND customer_id = " . $cID . " LIMIT 1
            ");
            $saldoDespues = aplicar_delta_saldo($cID, $nPtsConfirm);
            tep_db_query('COMMIT');
            $sMessage     = 'Movimiento confirmado. Saldo: ' . $saldoAntes . ' → ' . $saldoDespues . ' pts.';
            $sMessageType = 'success';

            if ($bNotify) {
                _notificar_cambio_saldo($cID, $nPtsConfirm, 'Puntos confirmados y disponibles para canjear');
                $sMessage .= ' Email enviado al cliente.';
            }

            $checkAfter            = $check;
            $checkAfter['points_status'] = 2;
            $sAuditComment = 'Confirmada fila pending #' . $uID;
            if ((int) $check['orders_id'] > 0) {
                $sAuditComment .= ' del pedido #' . (int) $check['orders_id'];
            }
            _audit_log('confirm', $cID, [
                'pending_unique_id' => $uID,
                // Delta TEÓRICO de la operación, no el diff del saldo (que puede haber sido capado por GREATEST(0,...))
                'points_delta'      => $nPtsConfirm,
                'balance_before'    => $saldoAntes,
                'balance_after'     => $saldoDespues,
                'data_before'       => $check,
                'data_after'        => $checkAfter,
                'comment'           => $sAuditComment,
                'notify_sent'       => $bNotify,
            ]);
        } catch (\Throwable $e) {
            tep_db_query('ROLLBACK');
            $sMessage     = 'Error: ' . $e->getMessage();
            $sMessageType = 'error';
        }
    }
}

// PRG pattern: si hubo acción POST, redirigir a GET (evita doble submit en refresh y "Fila no encontrada")
if (in_array($action, ['add', 'delete', 'confirm', 'expires', 'modify'], true)) {
    _flash_redirect($sMessage, $sMessageType, $cID);
}

// ---- Cargar datos del cliente (después de posibles acciones) -----------------
$cliQ = tep_db_query("
    SELECT c.customers_id, c.customers_firstname, c.customers_lastname,
           c.customers_email_address, c.customers_shopping_points, c.customers_points_expires
    FROM " . TABLE_CUSTOMERS . " c
    WHERE c.customers_id = " . $cID . " LIMIT 1
");
$cliente = tep_db_fetch_array($cliQ);
if (!$cliente) {
    tep_redirect(tep_href_link(FILENAME_CUSTOMERS_POINTS));
}

// Historial
$logQ = tep_db_query("
    SELECT unique_id, orders_id, points_pending, points_comment, date_added,
           points_status, points_type
    FROM " . TABLE_CUSTOMERS_POINTS_PENDING . "
    WHERE customer_id = " . $cID . "
    ORDER BY date_added DESC, unique_id DESC
    LIMIT 500
");

$aTipoLabel = [
    'SP' => 'Compra', 'RV' => 'Review', 'RF' => 'Referral', 'EX' => 'Caducado', 'RC' => 'Rectificativa', 'RD' => 'Canje',
];
$aStatusLabel = [
    1 => 'Pendiente', 2 => 'Confirmado', 3 => 'Cancelado', 4 => 'Canjeado',
];
$aStatusColor = [
    1 => '#b89500', 2 => '#2a7a2a', 3 => '#a02020', 4 => '#666',
];

// Estilos comunes
$sBtnDark   = 'display:inline-block;padding:8px 16px;margin:3px;background:linear-gradient(to bottom,#5a5a5a,#2a2a2a);color:#fff;border:1px solid #1a1a1a;border-radius:4px;font-weight:bold;font-size:12px;font-family:Arial,sans-serif;text-shadow:0 -1px 0 rgba(0,0,0,0.4);vertical-align:middle;text-decoration:none;cursor:pointer';
$sBtnGreen  = 'display:inline-block;padding:4px 10px;background:#2a7a2a;color:#fff;border:1px solid #1f5a1f;border-radius:3px;font-size:11px;font-weight:bold;cursor:pointer;text-decoration:none';
$sBtnRed    = 'display:inline-block;padding:4px 10px;background:#a02020;color:#fff;border:1px solid #701818;border-radius:3px;font-size:11px;font-weight:bold;cursor:pointer;text-decoration:none';
?>
<?php require(THEME . 'html/header.php'); ?>

<style>
  #modalDelete, #modalEdit { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; }
  #modalDelete.open, #modalEdit.open { display:flex; }
  #modalDelete .box, #modalEdit .box { background:#fff; border-radius:8px; padding:24px; width:480px; max-width:90vw; box-shadow:0 8px 32px rgba(0,0,0,0.3); font-family:Arial,sans-serif; }
  #modalDelete h3, #modalEdit h3 { margin:0 0 6px 0; font-size:18px; }
  #modalDelete h3 { color:#a02020; }
  #modalDelete .info, #modalEdit .info { font-size:12px; color:#666; margin:0 0 16px 0; }
  #modalDelete label, #modalEdit label { display:block; font-size:12px; color:#333; margin:10px 0 4px; font-weight:bold; }
  #modalDelete textarea, #modalEdit textarea { width:100%; box-sizing:border-box; padding:8px; border:1px solid #aaa; border-radius:4px; font-size:13px; font-family:inherit; resize:vertical; }
  #modalDelete .row-notify, #modalEdit .row-notify { margin:14px 0 6px; font-size:12px; color:#444; }
  #modalDelete .row-notify input, #modalEdit .row-notify input { vertical-align:middle; }
  #modalDelete .actions, #modalEdit .actions { margin-top:20px; text-align:right; }
  #modalDelete .actions button, #modalEdit .actions button { margin-left:8px; padding:9px 18px; border-radius:4px; border:1px solid #1a1a1a; font-weight:bold; font-size:12px; cursor:pointer; font-family:inherit; }
  #modalDelete .btn-cancel, #modalEdit .btn-cancel { background:#e8e8e8; color:#333; border-color:#bbb; }
  #modalDelete .btn-delete { background:#a02020; color:#fff; border-color:#701818; }
</style>

<div id="modalDelete" role="dialog" aria-modal="true">
  <div class="box">
    <h3>Eliminar movimiento</h3>
    <p class="info">El saldo del cliente se recalculará automáticamente y la acción quedará registrada en la auditoría.</p>
    <form method="post" id="formDelete">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="cID"    value="<?php echo $cID; ?>">
      <input type="hidden" name="uID"    id="mdUid" value="">
      <label for="mdMotivo">Motivo (opcional):</label>
      <textarea name="motivo" id="mdMotivo" rows="3" placeholder="Ej. devolución del pedido, error de registro..."></textarea>
      <div class="row-notify">
        <label style="font-weight:normal;display:inline">
          <input type="checkbox" name="notify" value="1" id="mdNotify"> Enviar email al cliente avisando del cambio
        </label>
      </div>
      <div class="actions">
        <button type="button" class="btn-cancel" onclick="cerrarModalDelete()">Cancelar</button>
        <button type="submit" class="btn-delete">✕ Eliminar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal MODIFICAR (ajustar puntos de una fila sin borrarla) -->
<div id="modalEdit" role="dialog" aria-modal="true">
  <div class="box">
    <h3 style="color:#1c7099">Modificar movimiento</h3>
    <p class="info">Cambia los puntos de esta fila. El saldo del cliente se ajustará por la diferencia y la acción queda en auditoría.</p>
    <form method="post" id="formEdit">
      <input type="hidden" name="action" value="modify">
      <input type="hidden" name="cID"    value="<?php echo $cID; ?>">
      <input type="hidden" name="uID"    id="meUid" value="">
      <label>Puntos actuales en la fila: <b id="mePtsActual">—</b></label>
      <label for="meNuevos" style="margin-top:10px">Nuevos puntos (entero, puede ser negativo):</label>
      <input type="number" name="nuevos" id="meNuevos" step="1" style="width:140px;padding:7px;border:1px solid #aaa;border-radius:4px;font-size:13px" required>
      <label for="meMotivo" style="margin-top:14px">Motivo (opcional):</label>
      <textarea name="motivo" id="meMotivo" rows="3" placeholder="Ej. ajuste por error de cálculo, devolución parcial..."></textarea>
      <div class="row-notify">
        <label style="font-weight:normal;display:inline">
          <input type="checkbox" name="notify" value="1" id="meNotify"> Enviar email al cliente avisando del cambio
        </label>
      </div>
      <div class="actions">
        <button type="button" class="btn-cancel" onclick="cerrarModalEdit()">Cancelar</button>
        <button type="submit" style="background:#1c7099;color:#fff;border-color:#155a78">✓ Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
function abrirModalDelete(uID) {
  document.getElementById('mdUid').value    = uID;
  document.getElementById('mdMotivo').value = '';
  document.getElementById('mdNotify').checked = false;
  document.getElementById('modalDelete').classList.add('open');
  setTimeout(function(){ document.getElementById('mdMotivo').focus(); }, 50);
}
function cerrarModalDelete() {
  document.getElementById('modalDelete').classList.remove('open');
}
function abrirModalEdit(uID, ptsActual) {
  document.getElementById('meUid').value       = uID;
  document.getElementById('mePtsActual').textContent = ptsActual;
  document.getElementById('meNuevos').value    = ptsActual;
  document.getElementById('meMotivo').value    = '';
  document.getElementById('meNotify').checked  = false;
  document.getElementById('modalEdit').classList.add('open');
  setTimeout(function(){ document.getElementById('meNuevos').focus(); document.getElementById('meNuevos').select(); }, 50);
}
function cerrarModalEdit() {
  document.getElementById('modalEdit').classList.remove('open');
}
document.addEventListener('keydown', function(e){
  if (e.key === 'Escape') { cerrarModalDelete(); cerrarModalEdit(); }
});
document.getElementById('modalDelete').addEventListener('click', function(e){
  if (e.target.id === 'modalDelete') cerrarModalDelete();
});
document.getElementById('modalEdit').addEventListener('click', function(e){
  if (e.target.id === 'modalEdit') cerrarModalEdit();
});
</script>

<table border="0" width="100%" cellspacing="2" cellpadding="2">
  <tr>
    <td width="100%" valign="top">

      <table border="0" width="100%" cellspacing="0" cellpadding="2">
        <tr>
          <td class="pageHeading">Historial de puntos</td>
          <td align="right">
            <a href="<?php echo tep_href_link(FILENAME_CUSTOMERS, 'cID=' . $cID . '&action=edit'); ?>" style="<?php echo $sBtnDark; ?>">
               &laquo; Volver al cliente
            </a>
          </td>
        </tr>
      </table>

<?php if ($sMessage): ?>
      <div style="padding:10px;margin:10px 0;border-radius:4px;<?php
          echo $sMessageType === 'success' ? 'background:#d6f0d6;border:1px solid #2a7a2a;color:#1f5a1f'
             : ($sMessageType === 'error'   ? 'background:#f5d6d6;border:1px solid #a02020;color:#701818'
                                            : 'background:#f0f0d6;border:1px solid #b89500;color:#7a5f00');
      ?>">
        <?php echo $sMessage; ?>
      </div>
<?php endif; ?>

      <!-- Tarjeta cliente -->
      <table border="0" width="100%" cellspacing="0" cellpadding="6" style="background:#f4f4f4;border:1px solid #ddd;margin:10px 0">
        <tr>
          <td><b>Cliente</b><br><?php echo htmlspecialchars($cliente['customers_firstname'] . ' ' . $cliente['customers_lastname']); ?></td>
          <td><b>Email</b><br><?php echo htmlspecialchars($cliente['customers_email_address']); ?></td>
          <td><b>ID</b><br><?php echo (int) $cliente['customers_id']; ?></td>
          <td align="right"><b>Saldo actual</b><br>
            <span style="font-size:16px;color:#1fa1d0;font-weight:bold">
              <?php echo number_format($cliente['customers_shopping_points'], POINTS_DECIMAL_PLACES, ',', '.'); ?> pts
            </span>
            &nbsp;=&nbsp;
            <span style="font-size:16px;color:#1fa1d0;font-weight:bold">
              <?php echo $currencies->format($cliente['customers_shopping_points'] * REDEEM_POINT_VALUE); ?>
            </span>
          </td>
          <td align="right">
            <b>Caducidad</b><br>
            <span style="font-size:18px;font-weight:bold;color:#1fa1d0">
              <?php echo $cliente['customers_points_expires'] ? tep_date_short($cliente['customers_points_expires']) : '<i style="color:#999;font-size:13px;font-weight:normal">sin caducidad activa</i>'; ?>
            </span>
            <form method="post" style="margin-top:6px;display:inline-flex;align-items:center;gap:4px" onsubmit="return confirm('¿Cambiar la fecha de caducidad de TODOS los puntos del cliente?');">
              <input type="hidden" name="action" value="expires">
              <input type="hidden" name="cID" value="<?php echo $cID; ?>">
              <input type="date" name="new_expires" value="<?php echo $cliente['customers_points_expires'] ?: ''; ?>" min="<?php echo date('Y-m-d'); ?>" style="padding:4px;font-size:11px;border:1px solid #aaa;border-radius:3px">
              <button type="submit" style="padding:4px 10px;background:#1fa1d0;color:#fff;border:1px solid #178bb5;border-radius:3px;font-size:11px;font-weight:bold;cursor:pointer">Cambiar</button>
            </form>
          </td>
        </tr>
      </table>

      <!-- Formulario añadir puntos -->
<?php
$sExpireDefault = tep_not_null(POINTS_AUTO_EXPIRES)
    ? date('Y-m-d', strtotime('+' . (int) POINTS_AUTO_EXPIRES . ' month'))
    : '';
?>
      <form method="post" style="background:#fff;border:1px solid #ddd;padding:12px;margin:10px 0;border-radius:4px" id="formAdd">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="cID" value="<?php echo $cID; ?>">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
          <b style="color:#1fa1d0">Añadir / Restar:</b>
          <input type="number" name="puntos" id="addPuntos" placeholder="Puntos (ej. 100 o -50)" step="1" style="width:170px;padding:6px;font-size:13px;border:1px solid #aaa;border-radius:3px">
          <span style="color:#888;font-size:12px">o</span>
          <input type="number" name="importe" id="addImporte" placeholder="Importe €" step="0.01" style="width:130px;padding:6px;font-size:13px;border:1px solid #aaa;border-radius:3px">
          <input type="text" name="comentario" placeholder="Comentario (opcional)" style="width:220px;padding:6px;font-size:13px;border:1px solid #aaa;border-radius:3px">
          <label style="font-size:12px;color:#555">
            Caducidad:
            <input type="date" name="expires" value="<?php echo $sExpireDefault; ?>" min="<?php echo date('Y-m-d'); ?>" style="padding:5px;font-size:13px;border:1px solid #aaa;border-radius:3px">
          </label>
          <label style="font-size:12px">
            <input type="checkbox" name="notify" value="1"> Enviar email
          </label>
          <button type="submit" style="<?php echo $sBtnDark; ?>">+ Aplicar</button>
        </div>
        <div style="margin-top:6px;color:#888;font-size:11px">
          Positivo = añadir · Negativo = restar · 1 punto = <?php echo number_format((float) REDEEM_POINT_VALUE, 2, ',', '.'); ?> € · Caducidad por defecto: hoy + <?php echo (int) POINTS_AUTO_EXPIRES; ?> meses · La fecha es <b>global del cliente</b> (afecta a todos sus puntos)
        </div>
      </form>
      <script>
      (function(){
        var pointValue = <?php echo json_encode((float) REDEEM_POINT_VALUE); ?>;
        var inPts   = document.getElementById('addPuntos');
        var inImp   = document.getElementById('addImporte');
        var locking = false;
        if (!inPts || !inImp || !pointValue) return;
        // Puntos → Importe (exacto)
        inPts.addEventListener('input', function(){
          if (locking) return;
          locking = true;
          var p = parseFloat(inPts.value);
          inImp.value = (!isNaN(p) && p !== 0) ? (p * pointValue).toFixed(2) : '';
          locking = false;
        });
        // Importe → Puntos (redondeado a entero)
        inImp.addEventListener('input', function(){
          if (locking) return;
          locking = true;
          var e = parseFloat(inImp.value);
          inPts.value = (!isNaN(e) && e !== 0) ? Math.round(e / pointValue) : '';
          locking = false;
        });
        // Validación: al menos uno
        document.getElementById('formAdd').addEventListener('submit', function(ev){
          var p = parseFloat(inPts.value) || 0;
          var e = parseFloat(inImp.value) || 0;
          if (p === 0 && e === 0) {
            ev.preventDefault();
            alert('Indica puntos o importe (distinto de 0).');
          }
        });
      })();
      </script>

      <!-- Tabla de movimientos -->
      <table border="0" width="100%" cellspacing="0" cellpadding="6" style="border-collapse:collapse;font-size:12px">
        <thead>
          <tr style="background:#1fa1d0;color:#fff">
            <th align="left" style="padding:8px">Fecha</th>
            <th align="left" style="padding:8px">Tipo</th>
            <th align="left" style="padding:8px">Pedido</th>
            <th align="left" style="padding:8px">Comentario</th>
            <th align="left" style="padding:8px">Estado</th>
            <th align="right" style="padding:8px">Puntos</th>
            <th align="right" style="padding:8px">Valor</th>
            <th align="center" style="padding:8px">Acciones</th>
          </tr>
        </thead>
        <tbody>
<?php
$nTotal = 0;
$bAlt   = false;
while ($aLog = tep_db_fetch_array($logQ)) {
    $nTotal++;
    $uID      = (int) $aLog['unique_id'];
    $sComment = defined($aLog['points_comment']) ? constant($aLog['points_comment']) : $aLog['points_comment'];
    $sTipo    = $aTipoLabel[$aLog['points_type']] ?? $aLog['points_type'];
    $iStatus  = (int) $aLog['points_status'];
    $sStatus  = $aStatusLabel[$iStatus] ?? '';
    $sStatusC = $aStatusColor[$iStatus] ?? '#666';
    $nPts     = (int) round((float) $aLog['points_pending']);
    $nValor   = $aLog['points_pending'] * REDEEM_POINT_VALUE;
    $sColor   = $nPts > 0 ? '#2a7a2a' : ($nPts < 0 ? '#a02020' : '#666');
    $sSigno   = $nPts > 0 ? '+' : '';
    $sBg      = $bAlt ? '#fafafa' : '#fff';
    $bAlt     = !$bAlt;

    if (in_array($sComment, ['TEXT_DEFAULT_COMMENT', 'TEXT_DEFAULT_REDEEMED'], true)) {
        $sComment = '';
    }
?>
          <tr style="background:<?php echo $sBg; ?>;border-bottom:1px solid #eee">
            <td style="padding:8px;white-space:nowrap;color:#444"><?php echo tep_date_short($aLog['date_added']); ?></td>
            <td style="padding:8px"><b><?php echo htmlspecialchars($sTipo); ?></b></td>
            <td style="padding:8px">
<?php if ((int) $aLog['orders_id'] > 0) { ?>
              <a href="<?php echo tep_href_link(FILENAME_ORDERS, 'oID=' . (int) $aLog['orders_id'] . '&action=edit'); ?>" title="Ver pedido">#<?php echo (int) $aLog['orders_id']; ?></a>
<?php } else { ?>
              <span style="color:#999">—</span>
<?php } ?>
            </td>
            <td style="padding:8px;color:#666"><?php echo htmlspecialchars((string) $sComment); ?></td>
            <td style="padding:8px;color:<?php echo $sStatusC; ?>;font-weight:bold"><?php echo htmlspecialchars($sStatus); ?></td>
            <td align="right" style="padding:8px;color:<?php echo $sColor; ?>;font-weight:bold;white-space:nowrap"><?php echo $sSigno . number_format($nPts, 0, ',', '.'); ?></td>
            <td align="right" style="padding:8px;color:<?php echo $sColor; ?>;white-space:nowrap"><?php echo $sSigno . $currencies->format($nValor); ?></td>
            <td align="center" style="padding:8px;white-space:nowrap">
<?php if ($iStatus === 1): ?>
              <form method="post" style="display:inline-block;margin:0 4px" onsubmit="return confirm('¿Confirmar este movimiento pendiente? Sumará al saldo del cliente.');">
                <input type="hidden" name="action" value="confirm">
                <input type="hidden" name="cID" value="<?php echo $cID; ?>">
                <input type="hidden" name="uID" value="<?php echo $uID; ?>">
                <label style="font-size:10px;color:#666;display:block;margin-bottom:2px">
                  <input type="checkbox" name="notify" value="1" style="vertical-align:middle"> Avisar
                </label>
                <button type="submit" style="<?php echo $sBtnGreen; ?>">✓ Confirmar</button>
              </form>
<?php endif; ?>
<?php if ($iStatus !== 4): ?>
              <button type="button" onclick="abrirModalEdit(<?php echo $uID; ?>, <?php echo (int) $nPts; ?>)" style="display:inline-block;padding:4px 10px;background:#1c7099;color:#fff;border:1px solid #155a78;border-radius:3px;font-size:11px;font-weight:bold;cursor:pointer;margin-right:3px">✎ Modificar</button>
              <button type="button" onclick="abrirModalDelete(<?php echo $uID; ?>)" style="<?php echo $sBtnRed; ?>;border:1px solid #701818">✕ Eliminar</button>
<?php else: ?>
              <span style="color:#aaa;font-size:11px;font-style:italic">canje — bloqueado</span>
<?php endif; ?>
            </td>
          </tr>
<?php
}
?>
        </tbody>
        <tfoot>
          <tr><td colspan="8" style="padding:8px;color:#888;font-size:11px;background:#f8f8f8">
<?php
if ($nTotal === 0) {
    echo '<i>Sin movimientos registrados.</i>';
} else {
    echo 'Mostrando ' . $nTotal . ' movimiento' . ($nTotal === 1 ? '' : 's') . ' (max 500). Tipos: <b>SP</b> Compra/Canje, <b>RV</b> Review, <b>RF</b> Referral, <b>RC</b> Rectificativa, <b>EX</b> Caducado (interno). Las filas canjeadas no se pueden eliminar.';
}
?>
          </td></tr>
        </tfoot>
      </table>


      <!-- Resumen de puntos canjeados (en qué pedidos los ha usado el cliente) -->
<?php
$canjeQ = tep_db_query("
    SELECT pp.orders_id, pp.date_added, pp.points_pending,
           o.date_purchased, o.customers_name,
           (SELECT value FROM " . TABLE_ORDERS_TOTAL . " WHERE orders_id = pp.orders_id AND class = 'ot_total' LIMIT 1) total_pedido
    FROM " . TABLE_CUSTOMERS_POINTS_PENDING . " pp
    LEFT JOIN " . TABLE_ORDERS . " o ON o.orders_id = pp.orders_id
    WHERE pp.customer_id = " . $cID . "
      AND pp.points_status = 4
      AND pp.points_pending < 0
    ORDER BY pp.date_added DESC
");
$nCanjeTotal     = 0;
$nCanjeFilas     = tep_db_num_rows($canjeQ);
$aPedidosCanjes  = [];
while ($c = tep_db_fetch_array($canjeQ)) {
    $nCanjeTotal += abs((float) $c['points_pending']);
    $aPedidosCanjes[] = $c;
}
?>
      <div style="margin-top:20px;border:1px solid #ddd;border-radius:4px;background:#fafafa">
        <div style="padding:10px 14px;background:#a02020;color:#fff;border-radius:4px 4px 0 0;font-weight:bold">
          🎟️ Puntos canjeados —
          <span style="font-weight:normal">Total:</span> <?php echo number_format($nCanjeTotal, 0, ',', '.'); ?> pts
          (<?php echo $currencies->format($nCanjeTotal * REDEEM_POINT_VALUE); ?>)
          en <?php echo $nCanjeFilas; ?> pedido<?php echo $nCanjeFilas === 1 ? '' : 's'; ?>
        </div>
<?php if ($nCanjeFilas === 0): ?>
        <div style="padding:14px;color:#888;font-style:italic">El cliente nunca ha canjeado puntos.</div>
<?php else: ?>
        <table border="0" width="100%" cellspacing="0" cellpadding="6" style="border-collapse:collapse;font-size:12px">
          <thead>
            <tr style="background:#e4e4e4;color:#333">
              <th align="left" style="padding:6px">Fecha canje</th>
              <th align="left" style="padding:6px">Pedido</th>
              <th align="left" style="padding:6px">Fecha pedido</th>
              <th align="right" style="padding:6px">Total pedido</th>
              <th align="right" style="padding:6px">Pts canjeados</th>
              <th align="right" style="padding:6px">Valor canje</th>
            </tr>
          </thead>
          <tbody>
<?php
$bAltC = false;
foreach ($aPedidosCanjes as $c) {
    $bgC = $bAltC ? '#fff' : '#fafafa'; $bAltC = !$bAltC;
    $pts = (int) round(abs((float) $c['points_pending']));
?>
            <tr style="background:<?php echo $bgC; ?>;border-bottom:1px solid #eee">
              <td style="padding:6px;white-space:nowrap;color:#555"><?php echo tep_date_short($c['date_added']); ?></td>
              <td style="padding:6px">
<?php if ((int) $c['orders_id'] > 0): ?>
                <a href="<?php echo tep_href_link(FILENAME_ORDERS, 'oID=' . (int) $c['orders_id'] . '&action=edit'); ?>" title="Ver pedido"><b>#<?php echo (int) $c['orders_id']; ?></b></a>
<?php else: ?>
                <span style="color:#999">—</span>
<?php endif; ?>
              </td>
              <td style="padding:6px;color:#666;white-space:nowrap"><?php echo $c['date_purchased'] ? tep_date_short($c['date_purchased']) : '<span style="color:#999">—</span>'; ?></td>
              <td align="right" style="padding:6px;color:#555;white-space:nowrap"><?php echo $c['total_pedido'] !== null ? $currencies->format((float) $c['total_pedido']) : '<span style="color:#999">—</span>'; ?></td>
              <td align="right" style="padding:6px;color:#a02020;font-weight:bold;white-space:nowrap">−<?php echo number_format($pts, 0, ',', '.'); ?></td>
              <td align="right" style="padding:6px;color:#a02020;white-space:nowrap">−<?php echo $currencies->format($pts * REDEEM_POINT_VALUE); ?></td>
            </tr>
<?php } ?>
          </tbody>
        </table>
<?php endif; ?>
      </div>

      <!-- Auditoría de acciones admin sobre este cliente -->
<?php
$auditQ = tep_db_query("
    SELECT created_at, admin_email, action, points_delta, balance_before, balance_after, comment, notify_sent, ip,
           JSON_UNQUOTE(JSON_EXTRACT(data_before, '$.orders_id')) order_id_before,
           JSON_UNQUOTE(JSON_EXTRACT(data_after,  '$.orders_id')) order_id_after
    FROM customers_points_audit
    WHERE customer_id = " . $cID . "
    ORDER BY created_at DESC, audit_id DESC
    LIMIT 50
");
$nAudit = tep_db_num_rows($auditQ);
?>
      <details style="margin-top:20px;border:1px solid #ddd;border-radius:4px;background:#fafafa">
        <summary style="padding:10px 14px;cursor:pointer;font-weight:bold;color:#444;background:#eee;border-radius:4px 4px 0 0">
          🔍 Auditoría de acciones admin (<?php echo $nAudit; ?> registros, max 50)
        </summary>
<?php if ($nAudit === 0): ?>
        <div style="padding:14px;color:#888;font-style:italic">Sin acciones registradas aún.</div>
<?php else: ?>
        <table border="0" width="100%" cellspacing="0" cellpadding="6" style="border-collapse:collapse;font-size:11px">
          <thead>
            <tr style="background:#e4e4e4;color:#333">
              <th align="left" style="padding:6px">Fecha</th>
              <th align="left" style="padding:6px">Admin</th>
              <th align="left" style="padding:6px">Acción</th>
              <th align="left" style="padding:6px">Pedido</th>
              <th align="left"  style="padding:6px">Δ pts</th>
              <th align="right" style="padding:6px">Saldo antes → después</th>
              <th align="left" style="padding:6px">Comentario</th>
              <th align="center" style="padding:6px">Email</th>
              <th align="left" style="padding:6px">IP</th>
            </tr>
          </thead>
          <tbody>
<?php
$bAltA = false;
$aActionLabel = ['add' => 'Añadir', 'delete' => 'Eliminar', 'confirm' => 'Confirmar', 'modify' => 'Modificados', 'expires' => 'Caducidad', 'fix' => 'Corrección'];
$aActionColor = ['add' => '#2a7a2a', 'delete' => '#a02020', 'confirm' => '#1fa1d0', 'modify' => '#8B4513', 'expires' => '#b89500', 'fix' => '#555'];
while ($a = tep_db_fetch_array($auditQ)) {
    $bgA = $bAltA ? '#fff' : '#fafafa'; $bAltA = !$bAltA;
    $actLbl = $aActionLabel[$a['action']] ?? $a['action'];
    $actCol = $aActionColor[$a['action']] ?? '#666';
    $delta  = (int) round((float) $a['points_delta']);
    $dCol   = $delta > 0 ? '#2a7a2a' : ($delta < 0 ? '#a02020' : '#666');
    $dSign  = $delta > 0 ? '+' : '';
    // Operación que cambió la fila pero NO afectó al saldo del cliente
    // (típico: modificar/eliminar una fila Pendiente, Cancelada o Canjeada)
    $bSinEfecto = ($delta != 0
                   && $a['balance_before'] !== null
                   && $a['balance_after']  !== null
                   && (int) round((float) $a['balance_before']) === (int) round((float) $a['balance_after']));
?>
<?php
$oidAudit = (int) ($a['order_id_before'] ?: $a['order_id_after'] ?: 0);
?>
            <tr style="background:<?php echo $bgA; ?>;border-bottom:1px solid #eee">
              <td style="padding:6px;white-space:nowrap;color:#555"><?php echo htmlspecialchars($a['created_at']); ?></td>
              <td style="padding:6px;color:#555"><?php echo htmlspecialchars((string) $a['admin_email']); ?></td>
              <td style="padding:6px;color:<?php echo $actCol; ?>;font-weight:bold"><?php echo $actLbl; ?></td>
              <td style="padding:6px;white-space:nowrap">
<?php if ($oidAudit > 0): ?>
                <a href="<?php echo tep_href_link(FILENAME_ORDERS, 'oID=' . $oidAudit . '&action=edit'); ?>" title="Ver pedido">#<?php echo $oidAudit; ?></a>
<?php else: ?>
                <span style="color:#bbb">—</span>
<?php endif; ?>
              </td>
              <td align="left" style="padding:6px;color:<?php echo $dCol; ?>;font-weight:bold;white-space:nowrap">
                <span style="display:inline-block;min-width:55px;text-align:right"><?php echo $dSign . number_format($delta, 0, ',', '.'); ?></span>
<?php if ($bSinEfecto): ?>
                <span title="Esta operación cambió el historial pero NO afectó al saldo del cliente — la fila estaba en estado Pendiente, Cancelado o Canjeado y no contaba al balance"
                      style="display:inline-block;margin-left:6px;padding:1px 6px;background:#e4e4e4;color:#666;border:1px solid #bbb;border-radius:10px;font-size:9px;font-weight:normal;cursor:help;vertical-align:middle">ⓘ sin efecto</span>
<?php endif; ?>
              </td>
              <td align="right" style="padding:6px;color:#666;white-space:nowrap">
                <?php echo $a['balance_before'] !== null ? (int) $a['balance_before'] : '—'; ?>
                &rarr;
                <?php echo $a['balance_after']  !== null ? (int) $a['balance_after']  : '—'; ?>
              </td>
              <td style="padding:6px;color:#666"><?php echo htmlspecialchars((string) $a['comment']); ?></td>
              <td align="center" style="padding:6px"><?php echo $a['notify_sent'] ? '✉️' : ''; ?></td>
              <td style="padding:6px;color:#999;font-size:10px;font-family:monospace"><?php echo htmlspecialchars((string) $a['ip']); ?></td>
            </tr>
<?php } ?>
          </tbody>
        </table>
<?php endif; ?>
      </details>

    </td>
  </tr>
</table>

<?php require(THEME . 'html/footer.php'); ?>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
