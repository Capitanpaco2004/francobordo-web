<?php 
require("includes/application_top.php");
if (isset($_GET['pId'])) {
	$cart->remove($_GET['pId']);
	header('location: '.$_SERVER['HTTP_REFERER']);
}
else
{
	$cart->remove_all();
	header('location: '.$_SERVER['HTTP_REFERER']);	
}
?>