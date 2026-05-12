<?php
require('includes/application_top.php');

$select_qry = tep_db_query('select id from kiala_orders_status where id='.$_GET["oID"]);
$id = tep_db_fetch_array($select_qry);

if (empty($id)) {
	tep_db_query('insert into kiala_orders_status(id,status) values ('.$_GET["oID"].' , "SENT - '.$_GET["tnumber"].'")');
} else {
	tep_db_query('update kiala_orders_status set status="SENT - '.$_GET["tnumber"].'" where id='.$_GET["oID"]);
}

?>