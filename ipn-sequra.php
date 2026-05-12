<?php

session_id($_POST['sid']);
$_GET['osCsid'] = $_POST['sid'];

if(file_exists(dirname(__FILE__).'/checkout_process_sequra.php')){
	require('checkout_process_sequra.php');
}else{
	require('checkout_process.php');
}
