<?php
require('includes/application_top.php');

// Si se ha realizado el pago por Redsys
if (isset($_SESSION['redsys']) && !empty($_SESSION['redsys'])) {
	if (is_object($_SESSION['redsys'])) {
		$_SESSION['redsys'] = (array)$_SESSION['redsys'];
	}


	// Obtenemos las tarjetas del cliente
	$aCheckCards = tep_db_query('SELECT * FROM customers_cards WHERE customers_id = "' . (int)$customer_id . '"');

	// Si no tiene tarjetas
	if (tep_db_num_rows($aCheckCards) <= 0) {
		// Insertamos tarjeta del cliente
		tep_db_query('INSERT customers_cards (customers_id, customers_cards_identifier, customers_cards_expire) VALUES(' . (int)$customer_id . ', "' . (array)$_SESSION['redsys']['ds_identifier'] . '", "' . (array)$_SESSION['redsys']['ds_expire'] . '")');
		$nIdCard = tep_db_insert_id();

		// Asignamos por defecto la tarjeta
		tep_db_query('UPDATE customers SET customers_default_card_id = ' . $nIdCard . ' WHERE customers_id = ' . (int)$customer_id);

		// Guardamos la tarjeta en sesion
		$customer_default_card_id = $nIdCard;
		tep_session_register('customer_default_card_id');
	} else {
		// Comprobamos si existe la tarjeta comparando con la fecha expedicion
		if (isset($_SESSION['redsys']['ds_expire']) && $_SESSION['redsys']['ds_expire'] != '') {
			$aCheckCards = tep_db_query('SELECT * FROM customers_cards WHERE customers_id = "' . (int)$customer_id . '" AND customers_cards_expire = "' . $_SESSION['redsys']['ds_expire'] . '"');

			// Si no existe la tarjeta, Insertamos tarjeta del cliente
			if (tep_db_num_rows($aCheckCards) <= 0)
				tep_db_query('INSERT customers_cards (customers_id, customers_cards_identifier, customers_cards_expire) VALUES(' . (int)$customer_id . ', "' . $_SESSION['redsys']['ds_identifier'] . '", "' . $_SESSION['redsys']['ds_expire'] . '")');
		}
	}

	// Limpiamos session redsys
	tep_session_unregister('redsys');
}

// BOF Separate Pricing Per Customer
if (isset($_SESSION['sppc_customer_group_id']) && $_SESSION['sppc_customer_group_id'] != '0') {
	$customer_group_id = $_SESSION['sppc_customer_group_id'];
} else {
	$customer_group_id = '0';
}

// if the customer is not logged on, redirect them to the shopping cart page
if (!tep_session_is_registered('customer_id')) {
	tep_redirect(tep_href_link(FILENAME_SHOPPING_CART));
}


if (isset($_GET['action']) && ($_GET['action'] == 'update')) {
	$notify_string = '';

	if (isset($_POST['notify']) && !empty($_POST['notify'])) {
		$notify = $_POST['notify'];

		if (!is_array($notify)) {
			$notify = [$notify];
		}

		for ($i = 0, $n = sizeof($notify); $i < $n; $i++) {
			if (is_numeric($notify[$i])) {
				$notify_string .= 'notify[]=' . $notify[$i] . '&';
			}
		}

		if (!empty($notify_string)) {
			$notify_string = 'action=notify&' . substr($notify_string, 0, -1);
		}
	}

	tep_redirect(tep_href_link(FILENAME_DEFAULT, $notify_string));
}

require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_CHECKOUT_SUCCESS);

$breadcrumb->add(NAVBAR_TITLE_2, tep_href_link(FILENAME_CHECKOUT_SUCCESS));

$global_query = tep_db_query("select global_product_notifications from " . TABLE_CUSTOMERS_INFO . " where customers_info_id = '" . (int)$customer_id . "'");
$global       = tep_db_fetch_array($global_query);

if ($global['global_product_notifications'] != '1') {
	$orders_query = tep_db_query("select orders_id, payment_method from " . TABLE_ORDERS . " where customers_id = '" . (int)$customer_id . "' order by date_purchased desc limit 1");
	$orders       = tep_db_fetch_array($orders_query);

	$products_array = [];
	$products_query = tep_db_query("select products_id, products_name from " . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . (int)$orders['orders_id'] . "' order by products_name");
	while ($products = tep_db_fetch_array($products_query)) {
		$products_array[] = ['id'   => $products['products_id'],
							 'text' => $products['products_name']];
	}
}

// Obtenemos al cliente
$aCustomer = tep_db_query('SELECT * FROM ' . TABLE_CUSTOMERS . ' WHERE customers_id = "' . (int)$customer_id . '";');
$aCustomer = tep_db_fetch_array($aCustomer);

// Total del pedido
$aTotal = tep_db_query('SELECT value FROM ' . TABLE_ORDERS_TOTAL . ' WHERE orders_id = "' . (int)$orders['orders_id'] . '" AND class = "ot_total";');
$aTotal = tep_db_fetch_array($aTotal);

require(DIR_THEME . 'html/header.php');

require(DIR_THEME . 'html/column_left.php');

echo tep_draw_form('order', tep_href_link(FILENAME_CHECKOUT_SUCCESS, 'action=update', 'SSL'));

$orders_query = tep_db_query("select orders_id from " . TABLE_ORDERS . " where customers_id = '" . (int)$customer_id . "' order by date_purchased desc limit 1");
$orders       = tep_db_fetch_array($orders_query);

$products_array = [];
$products_query = tep_db_query("select products_id, products_name, products_quantity from " . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . (int)$orders['orders_id'] . "' order by products_name");

// Recorremos los productos del pedido
$bajoPedido = false;

while ($products = tep_db_fetch_array($products_query)) {
	$products_array[] = ['id' => $products['products_id'], 'text' => $products['products_name']];

	// Entre 2 y 6 días
	if ($products['products_quantity'] <= -100 && $products['products_quantity'] >= -150) {
		if ($nAdd1 <= (24 * 2))
			$nAdd1 = (24 * 2);
		if ($nAdd2 <= (24 * 6))
			$nAdd2 = (24 * 6);
	} // Entre 8 y 13 días
	else if ($products['products_quantity'] <= 0 && $products['products_quantity'] >= -799) {
		if ($nAdd1 <= (24 * 8))
			$nAdd1 = (24 * 8);
		if ($nAdd2 <= (24 * 13))
			$nAdd2 = (24 * 13);
	} // Bajo pedido
	else if ($products['products_quantity'] <= -800 && $products['products_quantity'] >= -899) {
		$nAdd1      = false;
		$nAdd2      = false;
		$bajoPedido = true;
		break;
	} // Agotado
	else if ($products['products_quantity'] <= -900 && $products['products_quantity'] >= -901) {
		$nAdd1 = false;
		$nAdd2 = false;
		break;
	}
}

// Si tenemos predicción
if ($nAdd1 !== false && $bajoPedido == false) {
	// Obtenemos las dos estimaciones
	$aEstimate1 = getShippingEstimate(true, false, $nAdd1);
	$aEstimate2 = getShippingEstimate(true, false, $nAdd2);

	// Si las fechas son iguales, sumamos un día
	if ($aEstimate1['date'] == $aEstimate2['date'])
		$aEstimate2 = addHoursToDate($aEstimate2['year'] . '-' . $aEstimate2['month'] . '-' . $aEstimate2['day'], 24);
} else
	$aEstimate2 = date('Y-m-d', strtotime("+" . ($bajoPedido ? 30 : 5) . " day", time()));

?>


<div id="chck-sccs">
    <h2 style="font-weight: bold; font-size: 24px; text-align: center;">¡GRACIAS POR TU COMPRA!</h2>
    <br/><br/>
    <span style="font-weight: bold; font-size: 14px; text-align: center;">Tu numero de pedido es :  <?php echo (int)$orders['orders_id']; ?></span>
    <br/><br/>
	<?php //echo $messageStack->show( array( 'text' => MENSAJE_VACACIONES, 'class' => 'eror' ) ); ?>
    <a id="impr" target="_blank" href="imprimir_pedido.php?order_id=<?php echo (int)$orders['orders_id']; ?>&m=true">IMPRIMIR
        RECIBO DE COMPRA</a>

    <pre style="white-space: pre-wrap; white-space: -moz-pre-wrap; white-space: -pre-wrap; white-space: -o-pre-wrap; word-wrap: break-word;">
Le hemos enviado un email con la confirmación del pedido y  los detalles del pago a su dirección de correo electrónico.

Si pasados unos minutos no lo ha recibido, busque en la carpeta “Spam”, “Correo Basura” o similar e indique que los correos recibidos de <strong>francobordo.com</strong> son de total confianza, para que así le puedan llegar todas nuestras notificaciones correctamente. Si aun así no encuentra un email con la confirmación del pedido, póngase en contacto con nosotros.
</pre>
    <br/><br/><br/>
    <h3 style="font-weight: bold; font-size: 24px; text-align: center;">PREGUNTAS FRECUENTES</h3>
    <br/><br/>

    A continuación tienes información sobre las preguntas más frecuentes:
    <br/><br/>

    <ul id="slde-chkout" data-accordion>
        <li class="xaccordion-item" data-accordion-item style="margin-bottom:15px;">
            <div class="xaccordion-title dxp" data-accordion-link>¿Cómo puedo ver el estado de mi pedido?</div>
            <div class="xaccordion-content dxc" data-accordion-content>Puede consultar en cualquier momento cualquier
                detalle sobre su pedido, accediendo al <a href="account.php" title="Mi Cuenta">panel de control de su
                    Cuenta de cliente</a>, en el apartado “Pedidos”

                En cualquier caso, le mantendremos informado de cualquier novedad sobre el estado de su pedido y el
                proceso de envío a través de su dirección de correo electrónico. (le enviaremos el número de referencia
                del envío, para que pueda consultar con la mensajería en cualquier momento el estado en el que se
                encuentra)
            </div>
        </li>
        <li class="xaccordion-item" data-accordion-item style="margin-bottom:15px;">
            <div class="xaccordion-title dxp" data-accordion-link>¿Cuándo se enviará mi pedido?</div>
            <div class="xaccordion-content dxc" data-accordion-content>Si su pedido ha sido realizado antes de las 16:00
                de la tarde, ya ha realizado el pago del mismo y todos los artículos que ha solicitado están en stock en
                24/72 horas se enviará. (Sólo se hacen envíos de lunes a viernes).

                <b>Debe tener en cuenta que si ha comprado algún artículo cuya disponibilidad no sea inmediata (por
                    ejemplo que fuera de 1-3 días o de 4-6 días) todo el pedido se enviará cuando este artículo esté
                    disponible.</b>

                <b>Es importante que sepa que si ha elegido como método de pago transferencia bancaria o tarjeta de
                    crédito por teléfono</b>, el pedido no será enviado hasta que el ingreso no se refleje en nuestra
                cuenta. Si realiza el pago desde otra entidad bancaria, puede tardar entre 24 y 72h en llegar a nuestro
                banco. Cuando recibamos el pago, su pedido cambiará el estado a "En proceso".
            </div>
        </li>
        <li class="xaccordion-item" data-accordion-item style="margin-bottom:15px;">
            <div class="xaccordion-title dxp" data-accordion-link>¿Por qué empresa de mensajería se enviará mi pedido si
                he escogido la opción mensajería 24-48h?
            </div>
            <div class="xaccordion-content dxc" data-accordion-content>Si la dirección de envío es dentro de la
                península, el almacén decidirá por qué empresa de mensajería saldrá su pedido. Los envíos a Andorra,
                Ceuta, Melilla o Canarias, el encargado de entregar el pedido es Correos.

                En el momento en el que dispongamos del número de referencia de su envío, le informaremos para que pueda
                contactar con la mensajería y conocer en todo momento el estado del mismo.
            </div>
        </li>
        <li class="xaccordion-item" data-accordion-item style="margin-bottom:15px;">
            <div class="xaccordion-title dxp" data-accordion-link>¿Cuándo recibiré mi pedido? ¿Puedo especificar un
                horario concreto?
            </div>
            <div class="xaccordion-content dxc" data-accordion-content>Cuando el pedido salga de nuestras instalaciones
                recibirá un email avisándole. A partir de ese momento es cuando empieza a contar el plazo de entrega.
                Por lo tanto, todos los pedidos que se envíen bajo la modalidad de 24/48h. se deberían recibir antes de
                las 20:00 horas del segundo día posterior a cuando se ha realizado el envío.

                En las observaciones puede especificar la franja horaria que mejor le venga y nosotros se lo
                transmitiremos a la mensajería. Sin embargo, ellos no garantizan que lo cumplan puesto que depende de
                las rutas que tengan los repartidores en su zona. El envío puede ser entregado hasta las 20:00 de la
                tarde.
            </div>
        </li>
        <li class="xaccordion-item" data-accordion-item style="margin-bottom:15px;">
            <div class="xaccordion-title dxp" data-accordion-link>¿Puedo cambiar la dirección de entrega?</div>
            <div class="xaccordion-content dxc" data-accordion-content>Una vez que el pedido esté en preparación ya no
                se podrá cambiar la dirección de entrega. Podrá comprobar el estado del pedido dentro de su cuenta en el
                apartado “MIS PEDIDOS”. Si no puede recibirlo en la dirección indicada, contacte con la mensajería para
                ver si puede acercarse a recogerlo a la delegación más cercana.
            </div>
        </li>
        <li class="xaccordion-item" data-accordion-item style="margin-bottom:15px;">
            <div class="xaccordion-title dxp" data-accordion-link>¿Puedo modificar o cancelar mi pedido?</div>
            <div class="xaccordion-content dxc" data-accordion-content>Sí, pero debe ponerse en contacto con nosotros lo
                más rápido posible para indicarnos los cambios que desee realizar. Una vez que el pedido esté en
                preparación ya NO podrán realizarse cambios o cancelaciones sobre el mismo. Podrá comprobar el estado
                del pedido dentro de su cuenta en el apartado “MIS PEDIDOS”. Cualquier modificación puede solicitarla
                escribiendo a <a href="mailto:info@francobordo.com">info@francobordo.com</a> o llamando al <strong>91
                    652 88 58</strong></div>
        </li>
        <li class="xaccordion-item" data-accordion-item style="margin-bottom:15px;">
            <div class="xaccordion-title dxp" data-accordion-link>¿Qué hago si no me llega el pedido o el paquete viene
                en mal estado?
            </div>
            <div class="xaccordion-content dxc" data-accordion-content>Mediante el número de Tracking o Seguimiento que
                le enviamos una vez que su pedido sale de nuestras instalaciones, puede contactar con la mensajería para
                conocer con exactitud el estado en el que se encuentra su envío. Si no ha recibido ese nº de referencia,
                envíenos un email a <a href="mailto:info@francobordo.com">info@francobordo.com</a> solicitándolo para
                que pueda contactar con la mensajería y conocer el estado del mismo.

                <b>Si pasado el plazo indicado en las condiciones generales no ha recibido su pedido o bien su paquete
                    ha llegado dañado</b>, desprecintado, en mal estado, o sospecha que puede haber sido manipulado,
                <b style="text-decoration:underline;">es fundamental que lo anote en el albarán de entrega que le harán
                    firmar al recibir el pedido el mensajero</b>. A continuación y en un <b>plazo inferior a 24h</b>.
                debe ponerse en contacto con nosotros para informarnos de la incidencia y que podamos reclamarlo a la
                agencia de transportes. Pasadas 24h. no podremos reclamar nada a la mensajería, porque se entiende que
                ha recibido su paquete en buen estado.
            </div>
        </li>
        <li class="xaccordion-item" data-accordion-item style="margin-bottom:15px;">
            <div class="xaccordion-title dxp" data-accordion-link>Tengo otras dudas…</div>
            <div class="xaccordion-content dxc" data-accordion-content>Puede hacernos llegar cualquier consulta a
                <a href="mailto:info@francobordo.com">info@francobordo.com</a> o llamando al <strong>916 528
                    858</strong>.
                Estaremos encantados de atenderle y poder ayudarle.
            </div>
        </li>
    </ul>

    <br/><br/>
    <span style="text-align: center; display:block;">Una vez más, gracias por comprar en francobordo.com</span>
</div>

<div id="trustedShopsCheckout" style="display: none;">
    <span id="tsCheckoutOrderNr"><?php echo $orders['orders_id']; ?></span>
    <span id="tsCheckoutBuyerEmail"><?php echo $aCustomer['customers_email_address']; ?></span>
    <span id="tsCheckoutOrderAmount"><?php echo $aTotal['value']; ?></span>
    <span id="tsCheckoutOrderCurrency"><?php echo 'EUR'; ?></span>
    <span id="tsCheckoutOrderPaymentType"><?php echo $orders['payment_method']; ?></span>
    <span id="tsCheckoutOrderEstDeliveryDate"><?php echo date('Y-m-d', (strtotime("+48 Hours"))); ?></span>
</div>

<?php
// --- Datos para Google Customer Reviews (anadido 2026-06-15) ---
// Pais de envio real del pedido (ES por defecto; PT si va a Portugal)
$sGcrCountry = 'ES';
$qGcrC = tep_db_query("select delivery_country from " . TABLE_ORDERS . " where orders_id = '" . (int)$orders['orders_id'] . "' limit 1");
if ($rGcrC = tep_db_fetch_array($qGcrC)) {
    $sDc = strtolower(trim((string)$rGcrC['delivery_country']));
    if (strpos($sDc, 'portug') !== false || $sDc === 'pt') { $sGcrCountry = 'PT'; }
}
// estimated_delivery_date robusto: $aEstimate2 puede ser array (year/month/day) o string 'Y-m-d'
$sGcrEstDate = is_array($aEstimate2) ? ($aEstimate2['year'] . '-' . $aEstimate2['month'] . '-' . $aEstimate2['day']) : (string)$aEstimate2;
// GTINs reales (EAN-13 GS1) de los productos del pedido -> resenas de PRODUCTO en Google
$aGcrGtins = [];
$qGcrP = tep_db_query("select distinct product_ean from " . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . (int)$orders['orders_id'] . "' and product_ean is not null and product_ean <> ''");
while ($rGcrP = tep_db_fetch_array($qGcrP)) {
    $ean = trim((string)$rGcrP['product_ean']);
    // 13 digitos, fuera de rangos restringidos (2x, 0[245] = EAN internos), digito de control valido
    if (preg_match('/^[0-9]{13}$/', $ean) && !preg_match('/^(2|0[245])/', $ean)) {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) { $d = (int)$ean[$i]; $sum += ($i % 2 === 0) ? $d : $d * 3; }
        if (((10 - ($sum % 10)) % 10) === (int)$ean[12]) { $aGcrGtins[] = ['gtin' => $ean]; }
    }
}
?>
<script src="https://apis.google.com/js/platform.js?onload=renderOptIn" async defer></script>
<script>
	window.renderOptIn = function () {
		window.gapi.load('surveyoptin', function () {
			window.gapi.surveyoptin.render(
				{
					// REQUIRED FIELDS
					"merchant_id": 7605527,
					"order_id": "<?php echo (int)$orders['orders_id']; ?>",
					"email": "<?php echo $sCustomersEmailAddress; ?>",
					"delivery_country": "<?php echo $sGcrCountry; ?>",
					"estimated_delivery_date": "<?php echo $sGcrEstDate; ?>",
					<?php if (!empty($aGcrGtins)) { echo '"products": ' . json_encode($aGcrGtins) . ','; } ?>
				});
		});
	}
</script>

<?php require(DIR_THEME . 'html/footer.php'); ?>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
