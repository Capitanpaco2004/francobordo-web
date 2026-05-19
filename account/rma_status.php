<?php
// Página dedicada para ver el estado de un RMA (con sidebar de "Mi cuenta").
// Sigue el mismo patrón que /account/account_history_info.php:
//  - include('includes/application.php')         -> /account/includes/application.php
//    hace chdir('..'), valida login, carga lang account.php + rma_status.php (auto)
//    y añade NAVBAR_TITLE_1 al breadcrumb
//  - include('account/includes/header.php')      -> abre wrapper sidebar + columna contenido
//  - <template>
//  - include('account/includes/footer.php')      -> cierra wrapper + footer

include( 'includes/application.php' );

require( DIR_WS_CLASSES . 'rma.php' );

$ordersID   = isset($_GET['orders_id'])   ? (int) $_GET['orders_id']   : 0;
$productsID = isset($_GET['products_id']) ? (int) $_GET['products_id'] : 0;
if (!$ordersID || !$productsID) {
    tep_redirect( tep_href_link( FILENAME_ACCOUNT_HISTORY, '', 'SSL' ) );
}

// El pedido debe pertenecer al cliente logueado
$ownerQ = tep_db_query("SELECT customers_id FROM " . TABLE_ORDERS . " WHERE orders_id = '" . $ordersID . "'");
$owner  = tep_db_fetch_array($ownerQ);
if (!$owner || (int) $owner['customers_id'] !== (int) $customer_id) {
    tep_redirect( tep_href_link( FILENAME_ACCOUNT_HISTORY, '', 'SSL' ) );
}

// Lang del módulo RMA (necesario para RMA_STATUS_RETURN, RMA_BACK, etc.)
require_once( DIR_WS_LANGUAGES . $language . '/' . FILENAME_RMA );

// Lang de account_history_info (para NAVBAR_TITLE_2/3)
$ahLang = DIR_WS_LANGUAGES . $language . '/account_history_info.php';
if (is_file($ahLang)) {
    require_once($ahLang);
}

// Cargar datos del RMA
$rma = new rma($ordersID, $productsID);

// getOrderProduct() en la clase sólo se llama cuando se está creando un RMA nuevo.
// Para la vista de estado, cargamos el producto manualmente.
if (empty($rma->Product) || empty($rma->Product['products_name'])) {
    $pq = tep_db_query("SELECT products_name, products_model FROM " . TABLE_ORDERS_PRODUCTS . " WHERE products_id = '" . $productsID . "' AND orders_id = '" . $ordersID . "' LIMIT 1");
    if (tep_db_num_rows($pq)) {
        $rma->Product = tep_db_fetch_array($pq);
    }
}

$pageTitle = defined('RMA_PAGE_TITLE_STATUS') ? RMA_PAGE_TITLE_STATUS : 'Estado de la devolución';

// NAVBAR_TITLE_1 lo añadió ya /account/includes/application.php
if (defined('NAVBAR_TITLE_2')) {
    $breadcrumb->add( NAVBAR_TITLE_2, tep_href_link( FILENAME_ACCOUNT_HISTORY, '', 'SSL' ) );
}
if (defined('NAVBAR_TITLE_3')) {
    $breadcrumb->add( sprintf( NAVBAR_TITLE_3, $ordersID ), tep_href_link( FILENAME_ACCOUNT_HISTORY_INFO, 'order_id=' . $ordersID, 'SSL' ) );
}
$breadcrumb->add( $pageTitle );

// Header (abre estructura sidebar + columna contenido)
include( 'account/includes/header.php' );

// Template (renderiza el contenido dentro de la columna derecha)
include( DIR_THEME_ROOT . 'html/templates/' . basename(__FILE__) );

// Footer (cierra wrapper + footer global)
include( 'account/includes/footer.php' );
?>
