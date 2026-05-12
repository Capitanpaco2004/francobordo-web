<?php

$filename = $_GET['filename'];
if (!isset($_GET['filename'])) {
    die();
}
if(!substr_count($filename,'export')) {
    die();
}
$_GET['filename'] = addslashes($_GET['filename']);
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="'.$filename.'"'); //<<< Note the " " surrounding the file name
header('Content-Transfer-Encoding: binary');
header('Connection: Keep-Alive');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
ob_clean();
flush();
readfile(dirname(__FILE__)."/exports/".$filename);
sleep(2);
?>