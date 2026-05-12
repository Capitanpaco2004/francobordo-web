<?php
  require('includes/application_top.php');


////INSERTAR FECHA DE INICIO - CALCULO FECHA DE FINALIZACION////
$insert_id = $_POST['id'];
$ndias = $_POST['dias_caducar'];

$fecha = date("d") ."-". date("m") ."-". date("Y");
$sql = "update orders set orders_fecha_inicio='".$fecha."' where orders_id='" .$insert_id . "'";
$actualizar = tep_db_query($sql) or die("fallo en $sql");

function suma_fechas($fecha,$ndias)          
{
      if (preg_match("/[0-9]{1,2}\/[0-9]{1,2}\/([0-9][0-9]){1,2}/",$fecha))
              list($dia,$mes,$a�o)=explode("/", $fecha);
      if (preg_match("/[0-9]{1,2}-[0-9]{1,2}-([0-9][0-9]){1,2}/",$fecha))
              list($dia,$mes,$a�o)=explode("-",$fecha);
        $nueva = mktime(0,0,0, $mes,$dia,$a�o) + $ndias * 24 * 60 * 60;
        $nuevafecha=date("d-m-Y",$nueva);
      return ($nuevafecha);  
}





$fecha_fin = suma_fechas($fecha, $ndias);
$sql = "update orders set orders_fecha_fin='".$fecha_fin."' where orders_id='" . $insert_id . "'";
$actualizar = tep_db_query($sql) or die("fallo en $sql");

  echo "<META HTTP-EQUIV=\"Refresh\" CONTENT=\"0;URL=orders.php?page=1&oID=".$insert_id."&action=edit \">";

//////////////////////////////fin jose/////////////////////////////////////
?>
