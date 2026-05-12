<?php

if ( $_POST['signature'] != SequraHelper::sign( $_POST['sid'] ) ) {
    die( 'Hacking attenpt' );
}
$data = $builder->build( 'confirmed' );
$data['merchant_reference'] = array(
    'order_ref_1' => (string)$oID
);
$_SESSION['SeQuraURI'] = MODULE_PAYMENT_SEQURA_ENDPOINT . '/' . $_POST['order_ref'];
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
tep_db_perform( TABLE_ORDERS, $data, 'update', "orders_id='" . $oID . "'" );

$data = array( "orders_id" => $oID );
tep_db_perform( 'sequra', $data, 'update', "uri='" . $_SESSION['SeQuraURI'] . "'" );

tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, array(
    'orders_id' => $oID,
    'orders_status_id' => sequra::APPROVED_STATUS,
    'date_added' => 'now()',
    'comments' => 'Se ha pagado presupuesto por SeQura',
    'customer_notified' => 1
));