<?php
/**
 * cron_purge_oe_drafts.php  — Purga borradores de pedido ABANDONADOS del alta manual (order_edit).
 *
 * Un borrador abandonado = pedido creado en el admin (Crear Pedido) que nunca se finalizó:
 *   - 0 productos (NOT EXISTS en orders_products), Y
 *   - señal de borrador no finalizado:
 *       oe_qfac_draft=1  (marcador moderno, create.php desde 2026-06-18)
 *       O  (cfactur='S' AND orders_status=1 AND admin-creado)   [legacy pre-marcador]
 *   - antigüedad > UMBRAL_HORAS (no tocar borradores que se estén editando ahora).
 *
 * Borra el pedido y su huella: orders_total, orders_status_history, orders_edit_backup,
 * orders_products(_attributes) (vacíos) y orders. NO toca pedidos con productos ni reales.
 *
 * Uso (CLI):  php cron_purge_oe_drafts.php           -> DRY-RUN (solo informa)
 *             php cron_purge_oe_drafts.php --apply    -> borra
 */

date_default_timezone_set('Europe/Madrid');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

const CONFIGURE    = '/home/francobordo/public_html/_admin/includes/configure.php';
const UMBRAL_HORAS = 24;     // un borrador vacío de más de 24h se considera abandonado
const CAP          = 200;    // tope de seguridad de borrados por ejecución

$APPLY = in_array('--apply', $argv, true);
$MODE  = $APPLY ? 'APPLY' : 'DRY-RUN';

require CONFIGURE;
$link = mysqli_connect(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if (!$link) { fwrite(STDERR, "No conecta a MySQL\n"); exit(1); }
mysqli_set_charset($link, 'utf8');

function q($link, $sql) {
    $r = mysqli_query($link, $sql);
    if ($r === false) throw new RuntimeException('SQL: ' . mysqli_error($link) . ' | ' . $sql);
    return $r;
}

// Selección de borradores abandonados (criterio airtight; legacy exige admin-creado)
$sql = "SELECT o.orders_id, o.date_purchased, o.customers_name, o.oe_qfac_draft,
               COALESCE(o.cfactur,'') AS cfactur, o.orders_status
        FROM orders o
        WHERE NOT EXISTS (SELECT 1 FROM orders_products p WHERE p.orders_id = o.orders_id)
          AND (
                o.oe_qfac_draft = 1
                OR ( COALESCE(o.cfactur,'') = 'S' AND o.orders_status = 1
                     AND o.customer_service_id IS NOT NULL AND o.customer_service_id <> '' )
              )
          AND o.date_purchased < (NOW() - INTERVAL " . UMBRAL_HORAS . " HOUR)
        ORDER BY o.orders_id
        LIMIT " . (CAP + 1);

$ids = [];
$r = q($link, $sql);
while ($row = mysqli_fetch_assoc($r)) $ids[] = $row;

echo "[" . date('Y-m-d H:i:s') . "] purge_oe_drafts MODE=$MODE  candidatos=" . count($ids) . " (umbral {".UMBRAL_HORAS."}h, cap ".CAP.")\n";

if (count($ids) > CAP) {
    fwrite(STDERR, "ABORTO: " . count($ids) . " candidatos supera el cap de seguridad (" . CAP . "). Revisar criterio antes de borrar.\n");
    exit(2);
}
if (!$ids) { echo "Nada que purgar.\n"; exit(0); }

$n = 0;
foreach ($ids as $o) {
    $oid = (int)$o['orders_id'];
    $tag = "  #$oid  " . $o['date_purchased'] . "  " . substr((string)$o['customers_name'], 0, 25)
         . "  (draft=" . (int)$o['oe_qfac_draft'] . " cfactur=" . $o['cfactur'] . " est=" . (int)$o['orders_status'] . ")";

    if (!$APPLY) { echo $tag . "  -> SE BORRARIA\n"; continue; }

    // Re-verificación dentro de transacción (defensa: que no le hayan metido productos justo ahora)
    q($link, "START TRANSACTION");
    try {
        $chk = mysqli_fetch_assoc(q($link, "SELECT COUNT(*) AS np FROM orders_products WHERE orders_id=$oid"));
        if ((int)$chk['np'] !== 0) { q($link, "ROLLBACK"); echo $tag . "  -> SALTADO (ya tiene productos)\n"; continue; }
        q($link, "DELETE FROM orders_products_attributes WHERE orders_id=$oid");
        q($link, "DELETE FROM orders_products WHERE orders_id=$oid");
        q($link, "DELETE FROM orders_total WHERE orders_id=$oid");
        q($link, "DELETE FROM orders_status_history WHERE orders_id=$oid");
        q($link, "DELETE FROM orders_edit_backup WHERE orders_id=$oid");
        q($link, "DELETE FROM orders WHERE orders_id=$oid LIMIT 1");
        q($link, "COMMIT");
        $n++;
        echo $tag . "  -> BORRADO\n";
    } catch (Throwable $e) {
        q($link, "ROLLBACK");
        echo $tag . "  -> ERROR: " . $e->getMessage() . "\n";
    }
}

echo "--- RESUMEN ($MODE) ---  borrados=$n  de " . count($ids) . " candidatos\n";
if (!$APPLY) echo "(DRY-RUN: no se ha borrado nada. Lanzar con --apply.)\n";
