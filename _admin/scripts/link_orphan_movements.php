<?php
/**
 * _admin/scripts/link_orphan_movements.php
 *
 * Reconciliador de movimientos de pago huerfanos → pedido.
 *
 * El modulo redsys (y similares) inserta el movimiento en before_process SIN orders_id
 * y lo vincula en after_process con "ultimo movimiento del cliente"; cuando ese paso no
 * casa (notificacion async sin cartID en sesion, etc.) el movimiento queda con orders_id=0
 * y el pedido pierde la opcion de devolucion en el admin. Este cron lo cura a diario:
 * vincula SOLO coincidencias inequivocas (1 solo candidato):
 *   mismo customer_id + importe == ot_total (±0.01) + fecha a <=5 min + pedido sin mov. positivo.
 * Coincidencias 0 o >1 se dejan intactas (revision manual).
 *
 * Cron sugerido (diario):
 *   30 6 * * * /usr/bin/php /home/francobordo/public_html/_admin/scripts/link_orphan_movements.php --execute >> /home/francobordo/logs/link_orphan_movements.log 2>&1
 *
 * Self-contained (mysqli + configure.php), NO usa application_top (evita el gotcha de includes en CLI).
 */

$EXECUTE = in_array('--execute', $argv, true);

require '/home/francobordo/public_html/includes/configure.php'; // DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_error) { fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] DB fail: ' . $mysqli->connect_error . "\n"); exit(1); }

// Solo huerfanos de los ultimos 60 dias: el backlog historico ya se reconcilio una vez,
// y limita el coste del cron. Para un rescan total, ejecutar a mano sin este filtro.
$sql = "SELECT m.id, m.customer_id, m.value, m.date_created, m.module
        FROM redsys_payment_movements m
        WHERE (m.orders_id = 0 OR m.orders_id IS NULL) AND m.value > 0
          AND m.date_created >= (NOW() - INTERVAL 60 DAY)";
$res = $mysqli->query($sql);

$stmt = $mysqli->prepare(
    "SELECT o.orders_id
     FROM orders o
     JOIN orders_total t ON t.orders_id = o.orders_id AND t.class = 'ot_total'
     WHERE o.customers_id = ?
       AND ABS(t.value - ?) < 0.01
       AND ABS(TIMESTAMPDIFF(MINUTE, o.date_purchased, ?)) <= 5
       AND NOT EXISTS (SELECT 1 FROM redsys_payment_movements m2 WHERE m2.orders_id = o.orders_id AND m2.value > 0)"
);

$nTotal = 0; $nLinked = 0; $nNone = 0; $nAmbig = 0;
while ($m = $res->fetch_assoc()) {
    $nTotal++;
    $cid = (int)$m['customer_id']; $val = (float)$m['value']; $dat = $m['date_created'];
    $stmt->bind_param('ids', $cid, $val, $dat);
    $stmt->execute();
    $r = $stmt->get_result();
    $oids = [];
    while ($row = $r->fetch_assoc()) $oids[] = (int)$row['orders_id'];
    if (count($oids) === 1) {
        if ($EXECUTE) {
            $oid = $oids[0]; $mid = (int)$m['id'];
            $mysqli->query("UPDATE redsys_payment_movements SET orders_id = $oid WHERE id = $mid AND (orders_id = 0 OR orders_id IS NULL)");
            if ($mysqli->affected_rows === 1) { $nLinked++; }
        } else { $nLinked++; }
    } elseif (count($oids) === 0) { $nNone++; } else { $nAmbig++; }
}

echo '[' . date('Y-m-d H:i:s') . '] ' . ($EXECUTE ? 'EXEC' : 'DRY')
   . " huerfanos=$nTotal vinculados=$nLinked sin_match=$nNone ambiguos=$nAmbig\n";
