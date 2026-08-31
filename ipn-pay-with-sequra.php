<?php
/* #FB-SEQURA-SIG
   Este fichero se incluye desde pay-with-sequra.php y SOLO despues de que
   alli se haya verificado la firma ampliada (sid|oID|importe|ts) con
   hash_equals, se haya cargado el pedido y se haya comprobado su estado.
   Por eso aqui ya no se comprueba la firma: quedan las validaciones que
   necesitan $order cargado. Todo rechazo usa el mismo 403 generico. */

/* El importe firmado tiene que ser el del pedido de verdad. Sin esto, una
   firma valida de un presupuesto de 50 EUR servia para marcar pagado uno
   de 3.000. */
$fb_amt_signed = isset($_POST['amt']) ? (int)$_POST['amt'] : -1;
$fb_amt_order  = SequraHelper::orderAmountCents($order);
if ($fb_amt_order < 0 || $fb_amt_signed !== $fb_amt_order) {
    SequraHelper::forbid();
}

/* order_ref decidia (a) a que pedido de SeQura se le hace updateOrder y
   (b) entraba SIN ESCAPAR en el WHERE de la linea de abajo, porque
   tep_db_perform pega $parameters verbatim (database.php:162).
   Ahora solo se acepta si corresponde a una solicitud NUESTRA creada por el
   dueno de este pedido: esa es la comprobacion de propiedad del camino de
   notificacion, que no tiene sesion de cliente. */
$fb_order_ref = isset($_POST['order_ref']) && is_string($_POST['order_ref']) ? $_POST['order_ref'] : '';
if (!preg_match('/^[A-Za-z0-9._-]{1,96}$/', $fb_order_ref)) {
    SequraHelper::forbid();
}
$fb_uri = MODULE_PAYMENT_SEQURA_ENDPOINT . '/' . $fb_order_ref;
$fb_owner_query = tep_db_query("select id from sequra where uri = '" . tep_db_input($fb_uri) . "' and customer_id = '" . (int)$order->customer['id'] . "' limit 1");
if (!tep_db_num_rows($fb_owner_query)) {
    SequraHelper::forbid();
}

$data = $builder->build( 'confirmed' );
$data['merchant_reference'] = array(
    'order_ref_1' => (string)$oID
);
$_SESSION['SeQuraURI'] = $fb_uri;
$client->updateOrder($_SESSION['SeQuraURI'] , $data );
if ( ! $client->succeeded() ) {
    http_response_code(410);
    //tep_redirect( tep_href_link( FILENAME_CHECKOUT_PAYMENT, 'error_message=No+se+ha+podido+realizar+el+pago', 'SSL', true, false ) );
    //echo '<script>document.location.href="'.tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . MODULE_PAYMENT_SEQURA_TEXT_ERROR_CART_CHANGED, 'SSL', true, false).'"</script>';
    exit;
}
$data = array(
    "orders_status" => sequra::APPROVED_STATUS,
    "date_purchased" => 'now()'
 );
tep_db_perform( TABLE_ORDERS, $data, 'update', "orders_id='" . (int)$oID . "'" );

$data = array( "orders_id" => $oID );
tep_db_perform( 'sequra', $data, 'update', "uri='" . tep_db_input( $_SESSION['SeQuraURI'] ) . "'" );

tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, array(
    'orders_id' => $oID,
    'orders_status_id' => sequra::APPROVED_STATUS,
    'date_added' => 'now()',
    'comments' => 'Se ha pagado presupuesto por SeQura',
    'customer_notified' => 1
));
