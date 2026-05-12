<?php

	require('includes/application_top.php');

	$page = (isset( $_GET['page'] ) ? $_GET['page'] : 1);
	$oID = $_GET['oID'];
	$serie = 'A' . date('y');
	$fecha = date('Y-m-d');

	// comprobamos si el pedido ya tiene factura creada, para no crear otra
	$sql = "select * from facturas where facturas_pedido_id = '".$oID."' and facturas_abono='1'";
	$act = tep_db_query($sql);

	if (tep_db_num_rows($act) == 0) {
		
		// comprobamos si existen facturas con la misma serie para obtener el siguiente número correlativo
		$sql = "select * from facturas where facturas_serie = '".$serie."'";
		$act = tep_db_query($sql);
	
		if (tep_db_num_rows($act) == 0) {
			// es la primera factura con esta serie
			$numero = 1;
		} else {
			// averiguamos el número y le sumamos 1
			$sql_numero = "select facturas_numero from facturas where facturas_serie = '".$serie."' order by facturas_numero desc limit 0,1";
			$act_numero = tep_db_query($sql_numero);
			$numero = tep_db_fetch_array($act_numero);	
			$numero = $numero['facturas_numero'] + 1;
		}
		
		// creamos la factura de este pedido
		$sql_factura = "INSERT INTO facturas (`facturas_id`, `facturas_serie`, `facturas_numero`, `facturas_fecha`, `facturas_pedido_id`, `facturas_abono`) VALUES ('', '".$serie."', '".$numero."', '".$fecha."', '".$oID."', 1)";
		$act_factura = tep_db_query($sql_factura);
		
		if (isset($_GET['edit'])) {
			echo "<META HTTP-EQUIV=\"Refresh\" CONTENT=\"0;URL=orders.php?page=".$page."&oID=".$oID."&action=edit \">";
		} else {
			echo "<META HTTP-EQUIV=\"Refresh\" CONTENT=\"0;URL=orders.php?page=".$page."&oID=".$oID." \">";
		}
	}else{
     	$messageStack->add_session( 'Error: El Abono que has intentado crear ya estaba creado', 'error' );
     	
		if (isset($_GET['edit'])) {
			echo "<META HTTP-EQUIV=\"Refresh\" CONTENT=\"0;URL=orders.php?page=".$page."&oID=".$oID."&action=edit \">";
		} else {
			echo "<META HTTP-EQUIV=\"Refresh\" CONTENT=\"0;URL=orders.php?page=".$page."&oID=".$oID." \">";
		}	
	}
?>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>