<?php
// Sirve un PDF de factura QFacWin almacenado en /public_html/facturas/.
// Solo accesible al cliente propietario del pedido (verificación por sesión).
include('includes/application.php');

if (!$customerCore->hasLogin()) {
    $navigation->set_snapshot();
    tep_redirect(tep_href_link(FILENAME_LOGIN, '', 'SSL'));
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    tep_redirect(tep_href_link(FILENAME_ACCOUNT_HISTORY, '', 'SSL'));
}

// JOIN con orders garantiza que la factura pertenece al cliente logueado.
$q = tep_db_query(
    "SELECT qi.pdf_filename, qi.cnumfra
     FROM qfac_invoices qi
     INNER JOIN " . TABLE_ORDERS . " o ON o.orders_id = qi.orders_id
     WHERE qi.id = '" . (int)$id . "'
       AND o.customers_id = '" . (int)$customer_id . "'
     LIMIT 1"
);
if (!tep_db_num_rows($q)) {
    tep_redirect(tep_href_link(FILENAME_ACCOUNT_HISTORY, '', 'SSL'));
}
$row = tep_db_fetch_array($q);

// Defensa: el fichero solo puede ser un PDF dentro de /public_html/facturas
$fname = basename($row['pdf_filename']);
if (!preg_match('/^[A-Za-z0-9_.-]+\.pdf$/', $fname)) {
    tep_redirect(tep_href_link(FILENAME_ACCOUNT_HISTORY, '', 'SSL'));
}
$base = realpath(DIR_FS_CATALOG . 'facturas');
$path = realpath(DIR_FS_CATALOG . 'facturas/' . $fname);
if ($base === false || $path === false || strpos($path, $base . '/') !== 0) {
    tep_redirect(tep_href_link(FILENAME_ACCOUNT_HISTORY, '', 'SSL'));
}
if (!is_file($path)) {
    tep_redirect(tep_href_link(FILENAME_ACCOUNT_HISTORY, '', 'SSL'));
}

while (ob_get_level() > 0) {
    ob_end_clean();
}
$dispoName = $row['cnumfra'] ? $row['cnumfra'] . '.pdf' : $fname;
header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . $dispoName . '"');
header('Cache-Control: private, no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
readfile($path);
exit;
