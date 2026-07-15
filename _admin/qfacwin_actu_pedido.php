<?php
/* qfacwin_actu_pedido.php Francobordo: Actualización de pedidos desde QFACWIN */
$crlf     = "\r\n";
$strdebug = "S";

$tip = $_REQUEST['tip'];


if (!isset($_REQUEST['comanda'])) {
	echo "Error actualizando tienda virtual: qfacwin_actu_cmd: error(1)";
	die;
}
$comanda = $_REQUEST['comanda'];

if (!isset($_REQUEST['numfra'])) {
	$numfra = "";
} else {
	$numfra = $_REQUEST['numfra'];
}

if (!isset($_REQUEST['serie'])) {
	$cserie = "";
} else {
	$cserie = urldecode($_REQUEST['serie']);
}

if (!isset($_REQUEST['tracking'])) {
	$tracking = "";
} else {
	$tracking = urldecode($_REQUEST['tracking']);
}


$strprefixtaules = ''; //isset( $_GET['prefix'] ) ? $_GET['prefix'] : '' ;


include('includes/configure.php');

$link = mysqli_connect(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
mysqli_set_charset($link, 'utf8');

echo "osCommerce Pedido: " . $comanda . $crlf;

// pedido albaranado
if ($tip == "A") {

	// Guarda anti-retroceso (2026-07-14): no aplicar "En preparación" (13) si el pedido
	// ya está en preparación o más avanzado (13, 5=Enviado, 7=Parcial, 3=Entregado,
	// 310=Listo para Recoger en Tienda). Evita que un tip=A rezagado pise un pedido
	// ya Enviado y re-avise al cliente (bug pedido 10364828). Espeja la guarda de
	// idempotencia de tip=F.
	$strsql = "select orders_status from " . $strprefixtaules . "orders where orders_id = " . $comanda . "  and orders_status in ( 13, 5, 7, 3, 310 )";
	$result = db_query($strsql);
	if ($rowchk = mysqli_fetch_array($result)) {
		echo "pedido " . $comanda . " ya en estado " . $rowchk["orders_status"] . " (>= preparacion); tip=A ignorado" . $crlf;
		echo 'codret=0';
		die;
	}

	//añadimos / modificamos order status history
	$strset = "orders_status_id = 13, date_added = now(), customer_notified = 0, ";
	$strset .= "comments = '<p>Estimado cliente:</p>
<p>Su pedido ha sido remitido a nuestro almacén para su preparación y envío inmediato. A partir de este momento este pedido no puede ser cancelado ni modificado.<br />
Gracias y un saludo,</p>
<p>info@francobordo.com<br />
--------------------------------------</p>

<p>Dear customer:&nbsp;</p>
<p>Your order has been sent to our warehouse for his preparation and sending.<br />
<br />
From this moment this order can\'t be cancelled or modified.<br />
<br />
Thank you and greetings<br />
<br />
info@francobordo.com</p>' ";

	//buscamos si existe orders_status_history = 13
	$strsql = "select orders_status_history_id from " . $strprefixtaules . "orders_status_history where orders_id = " . $comanda . "  and orders_status_id = 13";
	$result = db_query($strsql);
	// si existe lo modificamos pero dejamos igual status del pedido
	if ($rowsts = mysqli_fetch_array($result)) {
		$strsql = "update " . $strprefixtaules . "orders_status_history set orders_id = " . $comanda . ", " . $strset . " where  orders_status_history_id = " . $rowsts["orders_status_history_id"];
		$result = db_query($strsql);
		echo "orders_status_history modificado" . $crlf;
	} else {
		//no encontrado: alta	y cambio status de orders
		//modificamos status del pedido
		$strsql = "update " . $strprefixtaules . "orders set orders_status = 13 where orders_id = " . $comanda;
		$result = db_query($strsql);
		if (mysqli_affected_rows($link) == 0) {
			echo "Error Actualizando tienda virtual: Pedido no existe: " . $comanda . $crlf;
			die;
		}
		echo "order_status del pedido cambiado a 13 " . $crlf;
		//modificamos status_history
		$strsql = "insert into " . $strprefixtaules . "orders_status_history set orders_id = " . $comanda . ", " . $strset;
		$result = db_query($strsql);
		echo "orders_status_history 13 añadido" . $crlf;
	}

	echo 'codret=0';
	die;
} // albaranado


// facturado
if ($tip == "F") {

	// Idempotencia: si ya está en estado final (3=Entregado, 5=Enviado, 7=Enviado Parcialmente,
	// 310=Listo para Recoger en Tienda), no rehacer la transición — sólo se actualiza el
	// numero de factura más abajo.
	$strsql = "select orders_status from " . $strprefixtaules . "orders where orders_id = " . $comanda . "  and orders_status in ( 3, 5, 7, 310 ) ";
	$result = db_query($strsql);
	if ($roworder = mysqli_fetch_array($result)) {
		//nada
	} else {

		// Decidir 5 (Enviado) vs 7 (Enviado Parcialmente) según si quedan
		// líneas pendientes en orders_warehouse_status (alimentado por el
		// sync VStock cada 5 min). Si hay reservado/esperando todavía, este
		// albarán no cubre el total → el pedido se va a 7. Cuando el
		// siguiente albarán cierre el pedido, otra llamada con tip="F"
		// volverá a entrar (porque 7 está incluido en la guarda IN(3,5,7,310)
		// — lo dejamos pasar manualmente más abajo cuando el sync ya haya
		// despejado las pendientes; en la práctica la auto-corrección del
		// sync 7→5 lo hará antes).
		$strsqlPending = "select count(*) as c from orders_warehouse_status where orders_id = " . $comanda . " and status in ('reservado','esperando')";
		$resPending = db_query($strsqlPending);
		$rowPending = mysqli_fetch_array($resPending);
		$bPartial = ((int)$rowPending['c'] > 0);

		// Recogida en tienda (2026-07-14): si el envío del pedido es "Recoger en tienda"
		// (orders_total class ot_shipping), el pedido facturado completo pasa a 310
		// (Listo para Recoger en Tienda) con su propio email, en vez de 5 (Enviado).
		// El email lo envía cron_mail_status.php (que también acepta el 310).
		$bPickup = false;
		if (!$bPartial) {
			$strsqlPickup = "select count(*) as c from " . $strprefixtaules . "orders_total where orders_id = " . (int)$comanda . " and class = 'ot_shipping' and title like 'Recoger en tienda%'";
			$resPickup = db_query($strsqlPickup);
			$rowPickup = mysqli_fetch_array($resPickup);
			$bPickup = ((int)$rowPickup['c'] > 0);
		}
		$nNewStatus = $bPartial ? 7 : ($bPickup ? 310 : 5);

		if ($bPartial) {
			$strset = "orders_status_id = 7, date_added = now(), customer_notified = 0, ";
			$strset .= "comments = '<p>Estimado cliente:</p>
	<p>Parte de su pedido ya está en camino. Las unidades disponibles han sido entregadas al transportista y el número de seguimiento es:</p>
	<p>" . $tracking . "<br></p>
	<p>Le notificaremos cuando se complete el envío del resto de los productos.</p>
	<p>Así mismo queremos informarle de que la factura de su pedido le será enviada en formato PDF a la dirección de correo electrónico que usted nos ha facilitado.</p>
	<p> Gracias por su confianza.
	</p>' ";
		} elseif ($bPickup) {
			// Recogida en tienda: sin tracking. Dirección y horario de la tienda.
			$strset = "orders_status_id = 310, date_added = now(), customer_notified = 0, ";
			$strset .= "comments = '<p>Estimado cliente:</p>
	<p>Le comunicamos que su pedido ya está preparado y <strong>disponible para recoger en nuestra tienda</strong>.</p>
	<p>Puede pasar a recogerlo en:<br>
	<strong>Francobordo</strong> — Calle San Rafael nº 8, 28108 Alcobendas (Madrid)<br>
	Horario: Lunes a Viernes de 10:00 a 20:00 · Sábados de 10:00 a 14:00</p>
	<p>Le recomendamos traer su número de pedido y un documento identificativo.</p>
	<p>Así mismo queremos informarle de que la factura de su pedido le será enviada en formato PDF a la dirección de correo electrónico que usted nos ha facilitado.</p>
	<p> Gracias por su confianza.
	</p>' ";
		} else {
			// OJO: el hueco exacto "seguimiento es:</p><p>...<br></p>" lo rellena
			// cron_mail_status.php (resolverTrackingWeb) con el tracking real — no cambiar.
			$strset = "orders_status_id = 5, date_added = now(), customer_notified = 0, ";
			$strset .= "comments = '<p>Estimado cliente:</p>
	<p>Le comunicamos que su pedido ya ha sido finalizado y entregado al transportista. El número de seguimiento es:</p>
	<p>" . $tracking . "<br></p>
	<p>Así mismo queremos informarle de que la factura de su pedido le será enviada en formato PDF a la dirección de correo electrónico que usted nos ha facilitado.</p>
	<p> Gracias por su confianza.
	</p>' ";
		}

		//buscamos si existe orders_status_history para el estado destino (5, 7 o 310)
		$strsql = "select orders_status_history_id from " . $strprefixtaules . "orders_status_history where orders_id = " . $comanda . "  and orders_status_id = " . $nNewStatus;
		$result = db_query($strsql);
		// si existe lo modificamos pero dejamos igual el status del pedido
		if ($rowsts = mysqli_fetch_array($result)) {
			$strsql = "update " . $strprefixtaules . "orders_status_history set " . $strset . " where  orders_status_history_id = " . $rowsts["orders_status_history_id"];
			$result = db_query($strsql);
			echo "orders_status_history modificado" . $crlf;
			//modificamos numero de factura
			$strsql = "update " . $strprefixtaules . "orders set invoice_number = " . $numfra . ", invoice_serial = '" . $cserie . "'  where orders_id = " . $comanda;
			$result = db_query($strsql);
			echo "numero factura modificada en orders" . $crlf;
		} else {
			//no encontrado: alta	y cambio status de orders
			//modificamos status del pedido
			$strsql = "update " . $strprefixtaules . "orders set orders_status = " . $nNewStatus . ", invoice_number = " . $numfra . ", invoice_serial = '" . $cserie . "'  where orders_id = " . $comanda;
			$result = db_query($strsql);
			echo $strsql;
			if (mysqli_affected_rows($link) == 0) {
				echo "Error actualizando tienda virtual: Pedido no existe: " . $comanda . $crlf;
				die;
			}
			echo "order_status del pedido cambiado a " . $nNewStatus . $crlf;
			//modificamos status_history
			$strsql = "insert into " . $strprefixtaules . "orders_status_history set orders_id = " . $comanda . ", " . $strset;
			$result = db_query($strsql);
			echo "orders_status_history " . $nNewStatus . " añadido" . $crlf;
		}
	} //pedido no tiene order status 3, 5 o 7

	echo 'codret=0' . $crlf;
	die;
} // facturado


// ----------------------------------------------------------
//  hace un query. die si error
// ----------------------------------------------------------
function db_query($query) {
	global $link, $crlf, $strdebug;
	//if ($strdebug == "S") {echo $crlf . $query;}
	$result = mysqli_query($link, $query);
	if ($result == false) {
		if ($strdebug == "S") {
			echo $crlf . "Error:  " . mysqli_errno($link) . ": " . mysqli_error($link) . $crlf . " sql: " . $query . $crlf;
		}
		die;
	}
	return $result;
}

?>
