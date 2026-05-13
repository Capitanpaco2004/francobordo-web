<?php
// Endpoint AJAX para crear/actualizar/limpiar overrides de descuento.
// POST JSON o form:
//   pid    (int, req)
//   ovid   (int, default 0)
//   pct    (float or empty/string '' to clear)
//   nota   (string optional)
require 'includes/application_top.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$pid  = (int)($_POST['pid'] ?? 0);
$ovid = (int)($_POST['ovid'] ?? 0);
$pct_raw = trim((string)($_POST['pct'] ?? ''));
$nota = tep_db_input(substr(trim((string)($_POST['nota'] ?? '')), 0, 120));
$user = tep_db_input((string)($_SESSION['login']['user_login'] ?? 'admin'));
$now  = date('Y-m-d H:i:s');

if ($pid <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'pid_required']);
    exit;
}

// Verificar que el producto existe y está activo
$exq = tep_db_query("SELECT 1 FROM products WHERE products_id={$pid} AND products_status=1 LIMIT 1");
if (!tep_db_fetch_array($exq)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'product_not_active']);
    exit;
}

// Limpiar override
if ($pct_raw === '' || $pct_raw === 'clear') {
    tep_db_query("DELETE FROM auto_specials_overrides WHERE products_id={$pid} AND options_values_id={$ovid}");
    echo json_encode(['ok' => true, 'action' => 'cleared', 'pid' => $pid, 'ovid' => $ovid]);
    exit;
}

$pct = (float)str_replace(',', '.', $pct_raw);
if ($pct < 0 || $pct > 100) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'pct_out_of_range', 'received' => $pct]);
    exit;
}
$pct_s = sprintf('%.2f', $pct);

// Upsert
$exq = tep_db_query("SELECT id FROM auto_specials_overrides WHERE products_id={$pid} AND options_values_id={$ovid} LIMIT 1");
$row = tep_db_fetch_array($exq);
if ($row) {
    tep_db_query("UPDATE auto_specials_overrides SET
        descuento_pct={$pct_s}, nota='{$nota}', usuario='{$user}', updated_at='{$now}'
        WHERE id=" . (int)$row['id']);
    $action = 'updated';
    $id = (int)$row['id'];
} else {
    tep_db_query("INSERT INTO auto_specials_overrides
        (products_id, options_values_id, descuento_pct, nota, usuario, created_at, updated_at)
        VALUES ({$pid}, {$ovid}, {$pct_s}, '{$nota}', '{$user}', '{$now}', '{$now}')");
    $action = 'inserted';
    $id = (int)tep_db_insert_id();
}

echo json_encode([
    'ok'     => true,
    'action' => $action,
    'id'     => $id,
    'pid'    => $pid,
    'ovid'   => $ovid,
    'pct'    => $pct,
]);
