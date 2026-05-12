<?php
require('includes/application_top.php');

global $customer_id;

$selectkp = tep_db_query("select kp from kiala_client_suggest where id='".$customer_id."'");
$fetchkp = tep_db_fetch_array($selectkp);
if (tep_not_null($fetchkp['kp'])) {
	$mykp = $fetchkp['kp'];
	tep_db_query("update kiala_client_suggest set kp='".$_GET['kp_id']."' where id='".$customer_id."'");
} else { 
	tep_db_query("insert into kiala_client_suggest(id,kp) values ('".$customer_id."','".$_GET['kp_id']."')");
}
?>