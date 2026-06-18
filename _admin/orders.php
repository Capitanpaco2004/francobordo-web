<?php

require('includes/application_top.php');


require(DIR_WS_CLASSES . 'currencies.php');
$currencies = new currencies();

if( !tep_session_is_registered('back_orders') ) {
	tep_session_register('back_orders');
}

if( !array_key_exists( 'action', $_GET ) && !array_key_exists( 'update_order', $_GET ) ) {
	$back_orders = getCurrentUrl();
}

if (isset($_SERVER['HTTP_REFERER']) && (preg_match('/_admin\/$/', (string) $_SERVER['HTTP_REFERER']) || preg_match('/orders\.php$/', (string) $_SERVER['HTTP_REFERER']))) {
	$back_orders = $_SERVER['HTTP_REFERER'];
}
	$oID = $oID ?? '';
	//get the date from the order table
	$date_resource = tep_db_query("select date_purchased from " . TABLE_ORDERS . " where orders_id = '" . (int)$oID . "'");
	//get the array from the result
	$date = tep_db_fetch_array($date_resource);
	//get the date as a string from the result
	$date_purchased_str = is_array($date) ? (string)($date['date_purchased'] ?? '') : '';
	$date_purchased = $date_purchased_str ? substr($date_purchased_str, 8, 2) . '/' . substr($date_purchased_str, 5, 2) . '/' . substr($date_purchased_str, 0, 4) : '';
	$orders_statuses = array();
	$orders_status_array = array();
	$orders_status_query = tep_db_query("select orders_status_id, orders_status_name from " . TABLE_ORDERS_STATUS . " where language_id = '" . (int)$languages_id . "'");

	while ($orders_status = tep_db_fetch_array($orders_status_query))
	{
		$orders_statuses[] = array('id' => $orders_status['orders_status_id'], 'text' => $orders_status['orders_status_name']);
		$orders_status_array[$orders_status['orders_status_id']] = $orders_status['orders_status_name'];
	}

$action = ($_GET['action'] ?? '');

if( tep_not_null($action) )
{
	switch( $action )
	{
			/**
			 * Devolución manual en puntos al cliente (2026-05-18)
			 * El operador introduce el importe en € desde el panel del pedido y
			 * el sistema crea una entrada en customers_points_pending (status=2)
			 * con o sin bonus +10% según el checkbox.
			 */
			case 'refund-points':
				$oID    = (int) ($_GET['oID'] ?? 0);
				$amount = (float) str_replace(',', '.', (string) ($_POST['amount'] ?? '0'));
				$bonus  = !empty($_POST['bonus']);

				if ($oID <= 0 || $amount <= 0) {
					tep_redirect(tep_href_link('orders.php', 'oID=' . $oID . '&action=edit'));
				}

				$qOrd = tep_db_query("SELECT customers_id FROM " . TABLE_ORDERS . " WHERE orders_id = " . $oID . " LIMIT 1");
				$rOrd = tep_db_fetch_array($qOrd);
				if (!$rOrd || !$rOrd['customers_id']) {
					tep_redirect(tep_href_link('orders.php', 'oID=' . $oID . '&action=edit'));
				}
				$cID = (int) $rOrd['customers_id'];

				$rate = (float) (defined('REDEEM_POINT_VALUE') ? REDEEM_POINT_VALUE : 0.05);
				if ($rate <= 0) $rate = 0.05;
				$bonusEur = $bonus ? min($amount * 0.10, 50.00) : 0.0;
				$totalEur = $amount + $bonusEur;
				$puntos   = (int) round($totalEur / $rate);

				if ($puntos <= 0) {
					tep_redirect(tep_href_link('orders.php', 'oID=' . $oID . '&action=edit'));
				}

				$qSaldo = tep_db_query("SELECT customers_shopping_points FROM " . TABLE_CUSTOMERS . " WHERE customers_id = " . $cID);
				$rSaldo = tep_db_fetch_array($qSaldo);
				$saldoAntes = (int) round((float) $rSaldo['customers_shopping_points']);

				$sComment = sprintf(
					'Devolución del pedido #%d desde admin — %s€%s',
					$oID,
					number_format($amount, 2, ',', '.'),
					$bonusEur > 0 ? ' + bonus ' . number_format($bonusEur, 2, ',', '.') . '€' . ($amount * 0.10 > 50.00 ? ' (capado a 50€)' : '') : ''
				);

				tep_db_query('START TRANSACTION');
				tep_db_query(sprintf(
					"INSERT INTO " . TABLE_CUSTOMERS_POINTS_PENDING . "
					  (customer_id, orders_id, points_pending, date_added, points_status, points_type, points_comment)
					VALUES (%d, %d, %d, NOW(), 2, 'SP', '%s')",
					$cID, $oID, $puntos, tep_db_input($sComment)
				));
				$insertId = (int) tep_db_insert_id();
				tep_db_query("UPDATE " . TABLE_CUSTOMERS . "
					SET customers_shopping_points = GREATEST(0, customers_shopping_points + " . $puntos . ")
					WHERE customers_id = " . $cID . " LIMIT 1");
				tep_db_query('COMMIT');

				$saldoDespues = $saldoAntes + $puntos;

				// Auditoría (si existe la tabla)
				$qChk = tep_db_query("SHOW TABLES LIKE 'customers_points_audit'");
				if (tep_db_num_rows($qChk) > 0) {
					$adminEmail = '';
					if (!empty($login_id)) {
						$qA = tep_db_query("SELECT admin_email_address FROM admin WHERE admin_id = " . (int) $login_id . " LIMIT 1");
						$rA = tep_db_fetch_array($qA);
						$adminEmail = $rA['admin_email_address'] ?? '';
					}
					tep_db_perform('customers_points_audit', [
						'admin_id'          => (int) ($login_id ?? 0),
						'admin_email'       => $adminEmail,
						'customer_id'       => $cID,
						'action'            => 'order_refund_points',
						'pending_unique_id' => $insertId,
						'points_delta'      => $puntos,
						'balance_before'    => $saldoAntes,
						'balance_after'     => $saldoDespues,
						'data_after'        => json_encode([
							'orders_id'  => $oID,
							'amount_eur' => $amount,
							'bonus_eur'  => $bonusEur,
							'total_eur'  => $totalEur,
							'rate'       => $rate,
						], JSON_UNESCAPED_UNICODE),
						'comment'           => $sComment,
						'notify_sent'       => 0,
						'ip'                => $_SERVER['REMOTE_ADDR'] ?? null,
						'created_at'        => 'now()',
					]);
				}

				tep_redirect(tep_href_link('orders.php', 'oID=' . $oID . '&action=edit'));
				break;

			/**
			 * #AUI-747-91109
			 * @author Daniel Lucia <daniel.lucia@denox.es>
			 */
			case 'refund-order':

				ini_set('display_errors', 1);
				ini_set('display_startup_errors', 1);
				error_reporting(E_ALL);

				$allowed = ['redsys', 'bizum', 'paypal'];

				if (!class_exists("RedsysAPI")) {
					require_once '../includes/modules/payment/apiRedsys/apiRedsysFinal.php';
				}

				$sql = sprintf('SELECT module, customer_id, reference, SUM(value) as amount FROM redsys_payment_movements WHERE orders_id = %d GROUP BY orders_id', $_GET['oID']);
				$sql = tep_db_query($sql);
				if (!tep_db_num_rows($sql)) {
					die('');
				}

				$order = tep_db_fetch_array($sql);

				if (in_array($order['module'], ['redsys', 'bizum'])) {
					if ($_GET['amount'] > $order['amount']) {
						$_GET['amount'] = $order['amount'];
					}

					$cantidad = round(floatval($_GET['amount']), 2);
					$cantidad = number_format($cantidad, 2, '.', '');
					$cantidad = preg_replace('/\./', '', $cantidad);

					if (MODULE_PAYMENT_REDSYS_CURRENCY == "Euro") {
						$moneda = "978";
					} else {
						$moneda = "840";
					}

					//Seleccion del entorno de pago
					if (MODULE_PAYMENT_REDSYS_ENTORNO == "Entorno Real") {
						$form_action_url = "https://sis.redsys.es/sis/rest/trataPeticionREST";
					} else {
						$form_action_url = "https://sis-t.redsys.es:25443/sis/rest/trataPeticionREST";
					}

					$clave256 = MODULE_PAYMENT_REDSYS_ID_CLAVE256;

					$miObj = new RedsysAPI;
					$miObj->setParameter("DS_MERCHANT_AMOUNT", $cantidad);
					$miObj->setParameter("DS_MERCHANT_ORDER", strval($order['reference']));
					$miObj->setParameter("DS_MERCHANT_MERCHANTCODE", MODULE_PAYMENT_REDSYS_ID_COM);
					$miObj->setParameter("DS_MERCHANT_CURRENCY", $moneda);
					$miObj->setParameter("DS_MERCHANT_TRANSACTIONTYPE", "3");
					$miObj->setParameter("DS_MERCHANT_TERMINAL", MODULE_PAYMENT_REDSYS_TERMINAL);

					//Datos de configuración
					$version = "HMAC_SHA256_V1";

					$paramsBase64 = $miObj->createMerchantParameters();
					$signatureMac = $miObj->createMerchantSignature($clave256);

					// Elementos del Form al SIS
					$data = http_build_query(
							[
							'Ds_MerchantParameters' => $paramsBase64,
							'Ds_Signature' => $signatureMac,
							'Ds_SignatureVersion' => $version,
						]
					);

					$header = array(
						"Cache-Control: no-cache",
						"Pragma: no-cache",
						"Content-length: " . strlen($data),
					);

					$curl = curl_init();

					curl_setopt($curl, CURLOPT_HEADER, false);
					curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
					curl_setopt($curl, CURLOPT_POST, false);
					curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
					curl_setopt($curl, CURLOPT_URL, $form_action_url);
					curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

					$result = curl_exec($curl);

					if (strpos(strtolower($result), 'error') === false) {
						$values = [
							'reference' => $order['reference'],
							'value' => $_GET['amount'] * -1,
							'customer_id' => $order['customer_id'],
							'module' => $order['module'],
							'date_created' => 'now()',
							'orders_id' => intval($_GET['oID']),
							'admin_id' => $login_id,
						];

						tep_db_perform(
							'redsys_payment_movements',
							$values
						);

						$messageStack->add_session('Parece que la devolución ha ido correctamente. De todos modos, verifíquelo.', 'success');
					} else {
						$messageStack->add_session('Ha ocurrido un error al generar la devolución.', 'error');
					}
				} /*elseif(in_array($order['module'], ['paypal'])) {

					if ($_GET['amount'] > $order['amount']) {
						$_GET['amount'] = $order['amount'];
					}

					// Braintree via Composer (braintree/braintree_php ^6.0) — cargado via autoload
					// Configuración
					Braintree\Configuration::environment(strtolower(MODULE_PAYMENT_PAYPAL_VZERO_ENVIRONMENT));
					Braintree\Configuration::merchantId(MODULE_PAYMENT_PAYPAL_VZERO_MERCHANTID);
					Braintree\Configuration::publicKey(MODULE_PAYMENT_PAYPAL_VZERO_PUBLICKEY);
					Braintree\Configuration::privateKey(MODULE_PAYMENT_PAYPAL_VZERO_PRIVATEKEY);

					$result = Braintree\Transaction::refund($order['reference'], floatval($_GET['amount']));

					if (get_class($result) != 'Braintree\Result\Error') {
						$values = [
							'reference' => $order['reference'],
							'value' => $_GET['amount'] * -1,
							'customer_id' => $order['customer_id'],
							'module' => $order['module'],
							'date_created' => 'now()',
							'orders_id' => intval($_GET['oID']),
							'admin_id' => $login_id,
						];

						tep_db_perform(
							'redsys_payment_movements',
							$values
						);

						$messageStack->add_session('Parece que la devolución ha ido correctamente. De todos modos, verifíquelo.', 'success');
					} else {
						$messageStack->add_session('Ha ocurrido un error al generar la devolución.', 'error');
					}
				} */elseif(in_array($order['module'], ['paypal'])) {

					if ($_GET['amount'] > $order['amount']) {
						$_GET['amount'] = $order['amount'];
					}

					$refundType = 'Partial';
					if ($_GET['amount'] == $order['amount']) {
						$refundType = 'Full';
					}

					if (MODULE_PAYMENT_PAYPAL_EXPRESS_TRANSACTION_SERVER == 'Live') {
						$url = 'https://api-3t.paypal.com/nvp';
					} else {
						$url = 'https://api-3t.sandbox.paypal.com/nvp';
					}

					$params = array(
						'USER' => MODULE_PAYMENT_PAYPAL_EXPRESS_API_USERNAME,
						'PWD' => MODULE_PAYMENT_PAYPAL_EXPRESS_API_PASSWORD,
						'VERSION' => '3.2',
						'SIGNATURE' => MODULE_PAYMENT_PAYPAL_EXPRESS_API_SIGNATURE,
						'METHOD' => 'RefundTransaction',
						'TRANSACTIONID' => $order['reference'],
						'REFUNDTYPE' => $refundType,
					);

					if ($refundType == 'Partial') {
						$params['AMT'] = floatval($_GET['amount']);
						$params['CURRENCYCODE'] = 'EUR';
					}

					$parameters = http_build_query($params);

					$server = parse_url($url);

					if (!isset($server['port'])) {
						$server['port'] = ($server['scheme'] == 'https') ? 443 : 80;
					}

					if (!isset($server['path'])) {
						$server['path'] = '/';
					}

					if (isset($server['user']) && isset($server['pass'])) {
						$header[] = 'Authorization: Basic ' . base64_encode($server['user'] . ':' . $server['pass']);
					}

					if (function_exists('curl_init')) {
						$curl = curl_init($server['scheme'] . '://' . $server['host'] . $server['path'] . (isset($server['query']) ? '?' . $server['query'] : ''));
						curl_setopt($curl, CURLOPT_PORT, $server['port']);
						curl_setopt($curl, CURLOPT_HEADER, 0);
						curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
						curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
						curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);
						curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1);
						curl_setopt($curl, CURLOPT_POST, 1);
						curl_setopt($curl, CURLOPT_POSTFIELDS, $parameters);

						$result = curl_exec($curl);
					} else {
						exec(escapeshellarg(MODULE_PAYMENT_PAYPAL_EXPRESS_CURL) . ' -d ' . escapeshellarg($parameters) . ' "' . $server['scheme'] . '://' . $server['host'] . $server['path'] . (isset($server['query']) ? '?' . $server['query'] : '') . '" -P ' . $server['port'] . ' -k', $result);
						$result = implode("\n", $result);
					}

					$response_array = array();
        			parse_str($result, $response_array);

					if (($response_array['ACK'] != 'Success') && ($response_array['ACK'] != 'SuccessWithWarning')) {

						$messageStack->add_session('Ha ocurrido un error al generar la devolución.', 'error');

					} else {
						$values = [
							'reference' => $order['reference'],
							'value' => $_GET['amount'] * -1,
							'customer_id' => $order['customer_id'],
							'module' => $order['module'],
							'date_created' => 'now()',
							'orders_id' => intval($_GET['oID']),
							'admin_id' => $login_id,
						];

						tep_db_perform(
							'redsys_payment_movements',
							$values
						);

						$messageStack->add_session('Parece que la devolución ha ido correctamente. De todos modos, verifíquelo.', 'success');
					}

					echo '<pre>'.print_r($response_array, 1).'</pre>';


				}


				echo '<pre>'.print_r($result, 1).'</pre>';
				die();

        break;
			case 'show_delete_orders_masivo':
				echo '<div id="hdr-ordr-cmt" class="hdr-tlbr" style="height: auto; padding: 0px;">';
					echo tep_draw_form('status', FILENAME_ORDERS, 'action=delete_orders_masivo', 'post', 'id="form_orders_ajax"');
						echo '<input type="hidden" id="ids" name="ids" value="' . $_GET['ids'] . '" />';
						echo '<input type="hidden" id="page" name="page" value="' . $_GET['page'] . '" />';
						?>
						<div class="formRow" style="height: 22px;">
							<div class="grid3 check">
								<?php echo tep_draw_checkbox_field('stock', '', false); ?>
							</div>
							<div class="grid3" style="line-height: 22px;">Añadir productos al almacen</div>
							<div class="clear"></div>
						</div>
						<div class="formRow" style="padding: 4px 16px 8px;">
								<div class="wButton grid6">
									<input type="submit" class="buttonL bGreen" style="margin-top: 10px;" value="Aceptar" />
									&nbsp;&nbsp;<a href="javascript: closeWindow();" class="buttonL bRed" style="margin-top: 10px;">Cancelar</a>
								</div>
							<div class="clear"></div>
						</div>
						<?php
				echo '</div>';
				exit();
			break;

			case 'delete_orders_masivo':
				// Obtenemos los pedidos
				$aAux = explode( ',', tep_db_prepare_input($_POST['ids']) );
				$sRestock = array_key_exists( 'stock', $_POST ) ? 'on' : false;

				// Recorremos para eliminar
				foreach( $aAux as $oID )
					tep_remove_order( $oID, $sRestock );

				// Redireccionamos
				$messageStack->add_session( "Se han eliminado los pedidos " . $_POST['ids'] . " correctamente", 'success' );
				tep_redirect( tep_href_link(FILENAME_ORDERS, 'page='.$_POST['page']) );
			break;

			case 'show_estado_notificacion':
				echo '<div id="hdr-ordr-cmt" class="hdr-tlbr" style="height: auto; padding: 0px;">';
					echo tep_draw_form('status', FILENAME_ORDERS, 'action=execute_estado_notificacion', 'post', 'id="form_orders_ajax"'); ?>
						<input type="hidden" id="input_orders" name="input_orders" value="<?php echo $_GET['ids']; ?>" />
						<input type="hidden" id="page" name="page" value="<?php echo $_GET['page']; ?>" />
						<div class="formRow">
							<div class="grid3"><label style="line-height: 33px;">Añadir texto predefinido:&nbsp;&nbsp;</label></div>
							<div class="grid9">
								<select id="txtValor">
									<optgroup label="Comentarios estáticos">
										<option value="">Elegir un texto</option>
										<option value="<?php echo STORE_NAME_ADDRESS; ?>">Dirección de la tienda</option>
										<option value="<?php echo STORE_NAME; ?>">Nombre de la tienda</option>
										<option value="<?php echo $_SERVER['SERVER_NAME']; ?>">Web</option>
										<option value="<?php echo STORE_OWNER; ?>">Dueño de la tienda</option>
										<option value="<?php echo STORE_OWNER_EMAIL_ADDRESS; ?>">Email de la tienda</option>
									</optgroup>

									<optgroup label="Comentarios dinámicos">
									<?php
										$get_premades_query = tep_db_query("select * from orders_premade_comments order by id");
										while($result = tep_db_fetch_array($get_premades_query))
										{
											echo '<option value="'.htmlentities($result["text"]).'">'. $result["title"].'</option>';
										}
									?>
									</optgroup>
									<optgroup label="Seguimiento de pedido">
										<option value="mercedes">Mercedes</option>
										<option value="audi">Audi</option>
									</optgroup>
								</select>
								&nbsp;&nbsp;&nbsp;<input type="button" value="Insertar" onclick="insereTexto('txtValor')" class="buttonS bGreen"/>  <input type="button" value="Limpiar" onclick="this.form.elements['comments'].value=''" class="buttonS bRed"  /> <font class="smallText"><a target="_blank" href="premade_comments.php">[Configurar textos]</a></font></p>
							</div>
							<div class="clear"></div>
						</div>
						<div class="formRow">
								<div class="grid3"><strong>Escribe tu comentario:</strong></div>
								<div class="grid9"><?php echo tep_draw_textarea_field_tinymce('comments', 'soft', '80', '5','','id="txt" class="tinymce-simple"'); ?></div>
								<div class="clear"></div>
						</div>
						<div class="formRow">
							<div class="grid3"><label><?php echo ENTRY_STATUS; ?>&nbsp;&nbsp;</label></div>
							<div class="grid9">
								<?php echo tep_draw_pull_down_menu('status', array_merge( array( array( 'id' => '', 'text' => 'No cambiar estado' ) ), $orders_statuses ), ''); ?>
							</div>
							<div class="clear"></div>
						</div>
						<div class="formRow" style="height: 22px;">
							<div class="grid3"><label style="position: relative; top: -5px;"><?php echo ENTRY_NOTIFY_CUSTOMER; ?>&nbsp;&nbsp;</label></div>
							<div class="grid9 check">
								<?php echo tep_draw_checkbox_field('notify', '', true); ?> <label for="check2" style="position: relative; top: -5px;" class="mr20">Cambio de estado&nbsp;&nbsp;&nbsp;</label><?php echo tep_draw_checkbox_field('notify_comments', '', true); ?> <label style="position: relative; top: -5px;" for="check2" class="mr20">Comentarios</label>
							</div>
							<div class="clear"></div>
						</div>
						<div class="formRow">
								<div class="wButton grid6">
									<input type="submit" class="buttonL bGreen" style="margin-top: 10px;" value="Enviar" />
								</div>
							<div class="clear"></div>
						</div>
					</form>
				</div><?php
				exit();
			break;

			case 'execute_estado_notificacion':
				$aAux = explode( ',', tep_db_prepare_input($_POST['input_orders']) );

				foreach( $aAux as $oID )
				{
					$status = tep_db_prepare_input($_POST['status']);
					$comments = tep_db_prepare_input($_POST['comments']);

					$order_updated = false;
					$check_status_query = tep_db_query("select customers_name, customers_id, customers_email_address, orders_status, date_purchased from " . TABLE_ORDERS . " where orders_id = '" . (int)$oID . "'");
					$check_status = tep_db_fetch_array($check_status_query);

					# Inicio, sistema de opiniones
					if( SISTEMA_OPINION_ENABLED == 'true' )
					{
						// Obtenemos la opinion
						$aDatos = tep_db_query( 'select id_opinion, orders_id, email_primero_enviado from opinion where orders_id = ' . $oID );

						// Obtenemos el array de estados
						$aAux = explode( ',', SISTEMA_OPINION_ESTADO_PEDIDO );

						// Si el estado es el configurado y no existe opinion aun para este pedido, insertamos el registro de opinion
						if( in_array( $status, $aAux ) && tep_db_num_rows( $aDatos ) == 0 )
							tep_db_query( 'insert into opinion (customers_id,orders_id, uniqid) values (' . $check_status['customers_id'] . ', ' . $oID . ', "' . uniqid( '', true ) . '_' . md5(mt_rand() ) . '")' );
						// Si cambia el estado por otro este sera eliminado si existe la opinion y esta aun no ha sido enviada
						else if( $check_status['orders_status'] != $status && tep_db_num_rows( $aDatos ) > 0 )
						{
							$aDatos = tep_db_fetch_array( $aDatos );

							if( $aDatos['email_primero_enviado'] == 'false' )
								tep_db_query( 'delete from opinion where id_opinion = ' . $aDatos['id_opinion'] );
						}
					}
					# Fin, sistema de opiniones

					if ( ($check_status['orders_status'] != $status) || tep_not_null($comments))
					{
						if( $status == '' )
							$status = $check_status['orders_status'];


						tep_db_query("update " . TABLE_ORDERS . " set orders_status = '" . tep_db_input($status) . "', last_modified = now() where orders_id = '" . (int)$oID . "'");

						$customer_notified = '0';
						if(isset($_POST['notify']) && ($_POST['notify'] == 'on'))
						{
							$notify_comments = '';

							if(isset($_POST['notify_comments']) && ($_POST['notify_comments'] == 'on'))
								$notify_comments = sprintf(EMAIL_TEXT_COMMENTS_UPDATE, $comments) . "\n\n";

							//---  Beginning of addition: Ultimate HTML Emails  ---//
							require(DIR_FS_CATALOG_MODULES . 'UHtmlEmails/'. ULTIMATE_HTML_EMAIL_LAYOUT .'/orders.php');
							$email = $html_email;

							//---  End of addition: Ultimate HTML Emails  ---//
							tep_mail($check_status['customers_name'], $check_status['customers_email_address'], EMAIL_TEXT_SUBJECT . ' (Nº de Pedido: ' . $oID . ')', $email, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);

							$customer_notified = '1';
						}

						######## Points/Rewards Module V2.1rc2a BOF ##################
						if((USE_POINTS_SYSTEM == 'true') && !tep_not_null(POINTS_AUTO_ON))
						{
							if ((isset($_POST['confirm_points']) && ($_POST['confirm_points'] == 'on'))||(isset($_POST['delete_points']) && ($_POST['delete_points'] == 'on')))
							{
								$comments = ENTRY_CONFIRMED_POINTS  . $comments;
								$customer_query = tep_db_query("select customer_id, points_pending from " . TABLE_CUSTOMERS_POINTS_PENDING . " where points_status = 1 and points_type = 'SP' and orders_id = '" . (int)$oID . "' limit 1");
								$customer_points = tep_db_fetch_array($customer_query);
								if (tep_db_num_rows($customer_query))
								{
									if (tep_not_null(POINTS_AUTO_EXPIRES))
									{
										$expire  = date('Y-m-d', strtotime('+ '. POINTS_AUTO_EXPIRES .' month'));
										tep_db_query("update " . TABLE_CUSTOMERS . " set customers_shopping_points = customers_shopping_points + '". $customer_points['points_pending'] ."', customers_points_expires = '". $expire ."' where customers_id = '". (int)$customer_points['customer_id'] ."'");
									}
									else
										tep_db_query("update " . TABLE_CUSTOMERS . " set customers_shopping_points = customers_shopping_points + '". $customer_points['points_pending'] ."' where customers_id = '". (int)$customer_points['customer_id'] ."'");

									if( isset($_POST['delete_points']) && ($_POST['delete_points'] == 'on') )
									{
										tep_db_query("delete from " . TABLE_CUSTOMERS_POINTS_PENDING . " where orders_id = '" . (int)$oID . "' and points_type = 'SP' limit 1");
										$sql = "optimize table " . TABLE_CUSTOMERS_POINTS_PENDING . "";
									}

									if (isset($_POST['confirm_points']) && ($_POST['confirm_points'] == 'on'))
									{
										tep_db_query("update " . TABLE_CUSTOMERS_POINTS_PENDING . " set points_status = 2 where orders_id = '" . (int)$oID . "' and points_type = 'SP' limit 1");
										$sql = "optimize table " . TABLE_CUSTOMERS_POINTS_PENDING . "";
									}
								}
							}
						}
						######## Points/Rewards Module V2.1rc2a EOF ##################

						tep_db_query("insert into " . TABLE_ORDERS_STATUS_HISTORY . " (orders_id, orders_status_id, date_added, customer_notified, comments) values ('" . (int)$oID . "', '" . tep_db_input($status) . "', now(), '" . tep_db_input($customer_notified) . "', '" . html_entity_decode( tep_db_input($comments) )  . "')");
						$order_updated = true;

						/**
						 * Si el cambio de estado coincide con los
						 * que tenemos configurados para compeltar pedidos
						 * en afiliados. Actualizamos el pedido.
						 * @author Daniel Lucia <daniel.lucia@denox.es>
						 */
						 /**
				                     * #XCC-313-91043
				                     * @author Daniel Lucia <daniel.lucia@denox.es>
				                     */

						$orderStatusAllowed = array_map('intval', explode(',', AFFILLIATES_ORDERS_STATUS));

						if (in_array(intval($status), $orderStatusAllowed)) {
							tep_db_perform(
								'affiliates_orders',
								[
									'date_order_completed' => 'now()'
								],
								'update',
								'orders_id = ' . intval($oID)
							);
						}
					}
				}

				// Redireccionamos
				$messageStack->add_session( "Se han notificado a los pedidos " . $_POST['input_orders'] . " correctamente", 'success' );
				tep_redirect( tep_href_link(FILENAME_ORDERS, 'page='.$_POST['page']) );
			break;

			case 'update_order':
				$oID = tep_db_prepare_input($_GET['oID']);
				$status = tep_db_prepare_input($_POST['status']);
				$comments = tep_db_prepare_input($_POST['comments']);

				$order_updated = false;
				$check_status_query = tep_db_query("select customers_id, customers_name, customers_email_address, orders_status, date_purchased from " . TABLE_ORDERS . " where orders_id = '" . (int)$oID . "'");
				$check_status = tep_db_fetch_array($check_status_query);


				# Inicio, sistema de opiniones
				if( SISTEMA_OPINION_ENABLED == 'true' )
				{
					// Obtenemos la opinion
					$aDatos = tep_db_query( 'select id_opinion, orders_id, email_primero_enviado from opinion where orders_id = ' . $oID );

					// Obtenemos el array de estados
					$aAux = explode( ',', SISTEMA_OPINION_ESTADO_PEDIDO );

					// Si el estado es el configurado y no existe opinion aun para este pedido, insertamos el registro de opinion
					if( in_array( $status, $aAux ) && tep_db_num_rows( $aDatos ) == 0 )
						tep_db_query( 'insert into opinion (customers_id,orders_id, uniqid) values (' . $check_status['customers_id'] . ', ' . $oID . ', "' . uniqid( '', true ) . '_' . md5(mt_rand() ) . '")' );
					// Si cambia el estado por otro este sera eliminado si existe la opinion y esta aun no ha sido enviada
					else if( $check_status['orders_status'] != $status && tep_db_num_rows( $aDatos ) > 0 )
					{
						$aDatos = tep_db_fetch_array( $aDatos );

						if( $aDatos['email_primero_enviado'] == 'false' )
							tep_db_query( 'delete from opinion where id_opinion = ' . $aDatos['id_opinion'] );
					}
				}
				# Fin, sistema de opiniones

				if( ($check_status['orders_status'] != $status) || tep_not_null($comments))
				{
					tep_db_query("update " . TABLE_ORDERS . " set orders_status = '" . tep_db_input($status) . "', last_modified = now() where orders_id = '" . (int)$oID . "'");

					$customer_notified = '0';

					if( isset($_POST['notify']) && ($_POST['notify'] == 'on') )
					{
						$notify_comments = '';

						if (isset($_POST['notify_comments']) && ($_POST['notify_comments'] == 'on'))
						{
							$notify_comments = sprintf(EMAIL_TEXT_COMMENTS_UPDATE, $comments) . "\n\n";
						}

						//---  Beginning of addition: Ultimate HTML Emails  ---//
						if( EMAIL_USE_HTML == 'true' )
						{
							require(DIR_FS_CATALOG_MODULES . 'UHtmlEmails/'. ULTIMATE_HTML_EMAIL_LAYOUT .'/orders.php');
							$email = $html_email;
						}
						else
						{
							//Send text email
							//---  End of addition: Ultimate HTML Emails  ---//
							$email = STORE_NAME . "\n" . EMAIL_SEPARATOR . "\n" . EMAIL_TEXT_ORDER_NUMBER . ' ' . $oID . "\n" . EMAIL_TEXT_INVOICE_URL . ' ' . tep_catalog_href_link(FILENAME_CATALOG_ACCOUNT_HISTORY_INFO, 'order_id=' . $oID, 'SSL') . "\n" . EMAIL_TEXT_DATE_ORDERED . ' ' . tep_date_long($check_status['date_purchased']) . "\n\n" . $notify_comments . sprintf(EMAIL_TEXT_STATUS_UPDATE, $orders_status_array[$status]);
							//---  Beginning of addition: Ultimate HTML Emails  ---//
						}

						if( ULTIMATE_HTML_EMAIL_DEVELOPMENT_MODE === 'true' )
						{
							//Save the contents of the generated html email to the harddrive in .htm file. This can be practical when developing a new layout.
							$TheFileName = DIR_FS_CATALOG . 'Last_mail_from_orders.php.htm';
							$TheFileHandle = fopen($TheFileName, 'w') or die("can't open error log file");
							fwrite($TheFileHandle, $email);
							fclose($TheFileHandle);
						}

						//---  End of addition: Ultimate HTML Emails  ---//
						tep_mail($check_status['customers_name'], $check_status['customers_email_address'], EMAIL_TEXT_SUBJECT . ' (Nº de Pedido: ' . $oID . ')', $email, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
						$customer_notified = '1';
					}

					######## Points/Rewards Module V2.1rc2a BOF ##################
					if ((USE_POINTS_SYSTEM == 'true') && !tep_not_null(POINTS_AUTO_ON))
					{
						if ((isset($_POST['confirm_points']) && ($_POST['confirm_points'] == 'on'))||(isset($_POST['delete_points']) && ($_POST['delete_points'] == 'on')))
						{
							$comments = ENTRY_CONFIRMED_POINTS  . $comments;
							$customer_query = tep_db_query("select customer_id, points_pending from " . TABLE_CUSTOMERS_POINTS_PENDING . " where points_status = 1 and points_type = 'SP' and orders_id = '" . (int)$oID . "' limit 1");
							$customer_points = tep_db_fetch_array($customer_query);

							if( tep_db_num_rows($customer_query) )
							{
								if (tep_not_null(POINTS_AUTO_EXPIRES))
								{
									$expire  = date('Y-m-d', strtotime('+ '. POINTS_AUTO_EXPIRES .' month'));
									tep_db_query("update " . TABLE_CUSTOMERS . " set customers_shopping_points = customers_shopping_points + '". $customer_points['points_pending'] ."', customers_points_expires = '". $expire ."' where customers_id = '". (int)$customer_points['customer_id'] ."'");
								}
								else
								{
									tep_db_query("update " . TABLE_CUSTOMERS . " set customers_shopping_points = customers_shopping_points + '". $customer_points['points_pending'] ."' where customers_id = '". (int)$customer_points['customer_id'] ."'");
								}

								if(isset($_POST['delete_points']) && ($_POST['delete_points'] == 'on'))
								{
									tep_db_query("delete from " . TABLE_CUSTOMERS_POINTS_PENDING . " where orders_id = '" . (int)$oID . "' and points_type = 'SP' limit 1");
									$sql = "optimize table " . TABLE_CUSTOMERS_POINTS_PENDING . "";
								}

								if(isset($_POST['confirm_points']) && ($_POST['confirm_points'] == 'on'))
								{
									tep_db_query("update " . TABLE_CUSTOMERS_POINTS_PENDING . " set points_status = 2 where orders_id = '" . (int)$oID . "' and points_type = 'SP' limit 1");
									$sql = "optimize table " . TABLE_CUSTOMERS_POINTS_PENDING . "";
								}
							}
						}
					}
					######## Points/Rewards Module V2.1rc2a EOF ##################
					tep_db_query("insert into " . TABLE_ORDERS_STATUS_HISTORY . " (orders_id, orders_status_id, date_added, customer_notified, comments) values ('" . (int)$oID . "', '" . tep_db_input($status) . "', now(), '" . tep_db_input($customer_notified) . "', '" . tep_db_input($comments)  . "')");
					$order_updated = true;
				}

				if ($order_updated == true)
				{
					/**
					 * Si el cambio de estado coincide con los
					 * qu tnemos configurados para compeltar pedidos
					 * en adiliados. Actualizamos el pedido.
					 * @author Daniel Lucia <daniel.lucia@denox.es>
					 */
					 /**
			                     * #XCC-313-91043
			                     * @author Daniel Lucia <daniel.lucia@denox.es>
			                     */

					$orderStatusAllowed = array_map('intval', explode(',', AFFILLIATES_ORDERS_STATUS));
					if (in_array(intval($status), $orderStatusAllowed)) {
						tep_db_perform(
							'affiliates_orders',
							[
								'date_order_completed' => 'now()'
							],
							'update',
							'orders_id = ' . intval($oID)
						);
					}

					$messageStack->add_session(SUCCESS_ORDER_UPDATED, 'success');
				}
				else
				{
					$messageStack->add_session(WARNING_ORDER_NOT_UPDATED, 'warning');
				}

				tep_redirect(tep_href_link(FILENAME_ORDERS, tep_get_all_get_params(array('action')) . 'action=edit'));
			break;

			case 'deleteconfirm':
				$oID = tep_db_prepare_input($_GET['oID']);
				tep_remove_order($oID, $_POST['restock']);
				tep_redirect(tep_href_link(FILENAME_ORDERS, tep_get_all_get_params(array('oID', 'action'))));
			break;

			case 'update_delivery_estimate':
				$oID = (int)tep_db_prepare_input($_GET['oID']);

				if( file_exists( DIR_WS_MODULES . 'delivery_estimate/classes/delivery_estimate_admin.php' ) ) {
					require_once( DIR_WS_MODULES . 'delivery_estimate/classes/delivery_estimate_admin.php' );
					$de_module = new delivery_estimate_admin();

					if( ! $de_module->isInstalled() ) {
						$messageStack->add_session( 'El modulo de fecha estimada no esta instalado.', 'warning' );
					}
					else {
						$estimated = tep_db_prepare_input( $_POST['delivery_estimated_date'] ?? '' );
						$comment   = tep_db_prepare_input( $_POST['delivery_comment'] ?? '' );
						$notify    = ( isset( $_POST['delivery_notify'] ) && $_POST['delivery_notify'] == 'on' );

						// Formato desde input type="date" ya es YYYY-MM-DD
						$adminUser = '';
						if( tep_session_is_registered( 'admin' ) ) {
							global $admin;
							$adminUser = isset( $admin['username'] ) ? $admin['username'] : '';
						}

						if( $de_module->saveManualUpdate( $oID, $estimated, $comment, $notify, $adminUser ) ) {
							$messageStack->add_session( 'Fecha estimada actualizada' . ( $notify ? ' y email enviado al cliente.' : '.' ), 'success' );
						}
						else {
							$messageStack->add_session( 'No se pudo actualizar la fecha estimada. Revisa el formato.', 'error' );
						}
					}
				}
				else {
					$messageStack->add_session( 'Modulo de fecha estimada no disponible.', 'warning' );
				}

				tep_redirect( tep_href_link( FILENAME_ORDERS, tep_get_all_get_params( array( 'action' ) ) . 'action=edit' ) );
			break;
		}
	}

  if (($action == 'edit') && isset($_GET['oID'])) {
    $oID = tep_db_prepare_input($_GET['oID']);

    $orders_query = tep_db_query("select orders_id from " . TABLE_ORDERS . " where orders_id = '" . (int)$oID . "'");
    $order_data = tep_db_fetch_array($orders_query);
    $order_exists = true;
    if (!tep_db_num_rows($orders_query)) {
      $order_exists = false;
      $messageStack->add(sprintf(ERROR_ORDER_DOES_NOT_EXIST, $oID), 'error');
    }
  }

  include(DIR_WS_CLASSES . 'order.php');
?>


<?php require(THEME . 'html/header.php'); ?>

<style type="text/css">
	#arrow_select_all
	{
		background: url("images/arrow_select_all.png") no-repeat scroll 0 0 rgba(0, 0, 0, 0);
		float: left;
		height: 23px;
		left: 2px;
		margin-right: 12px;
		position: relative;
		top: -5px;
		width: 16px;
	}
	#lgbox-bg
	{
		background: none repeat scroll 0 0 rgba(45, 51, 57, 0.5);
		bottom: 0;
		display: none;
		left: 0;
		padding: 0;
		position: fixed;
		right: 0;
		top: 0;
		z-index: 550;
	}
	#lgbox-load
	{
		background-color: #FFFFFF;
		background-image: url("images/loading.gif");
		background-position: center center;
		background-repeat: no-repeat;
		display: none;
		height: 78px;
		left: 50%;
		margin-left: -39px;
		margin-top: -39px;
		position: fixed;
		top: 50%;
		width: 78px;
		z-index: 551;
	}

	#lgbox
	{
		background: none repeat scroll 0 0 #FFFFFF;
		display: none;
		left: 50%;
		margin-left: -413px;
		padding: 67px 22px 35px;
		position: fixed;
		top: 50%;
		width: 780px;
		z-index: 552;
		height: 486px;
		margin-top: -294px;
	}

	#lgbox.lgbox-mini
	{
		height: 142px;
		margin-left: -227px;
		margin-top: -123px;
		width: 410px;
	}

	#lgbox.lgbox-mini #lgbox-cntd
	{
		padding: 0px !important;
	}

	#lgbox-clse
	{
		background: none repeat scroll 0 0 #CCCCCC;
		color: #FFFFFF;
		cursor: pointer;
		font-size: 13px;
		font-weight: bold;
		line-height: 22px;
		padding: 0 6px;
		position: absolute;
		right: 21px;
		top: 21px;
	}

	#lgbox-clse:hover
	{
		background: #666;
	}

	#lgbox-titl
	{
		color: #313E45;
		font-size: 16px;
		font-weight: bold;
		left: 21px;
		position: absolute;
		top: 24px;
	}

	#lgbox-cntd
	{
		height: 100%;
		overflow-y: auto;
		padding-right: 20px;
	}

	.buttonL
	{
		cursor: pointer;
	}
</style>

<script language="javascript" src="includes/general.js"></script>
<script language="javascript" src="includes/ajax.js"></script>
<script language="javascript">
	  function insereTexto(sId){
		 //Pega a textarea
		 var textarea = document.getElementById("txt");

		 //Texto a ser inserido
		 var texto = document.getElementById(sId).value;

		 //inicio da seleção
		 var sel_start = textarea.selectionStart;

		 //final da seleção
		 var sel_end = textarea.selectionEnd;


		 if (!isNaN(textarea.selectionStart))
		 //tratamento para Mozilla
		 {
			var sel_start = textarea.selectionStart;
			var sel_end = textarea.selectionEnd;

			mozWrap(textarea, texto, '')
			textarea.selectionStart = sel_start + texto.length;
			textarea.selectionEnd = sel_end + texto.length;
		 }

		 else if (textarea.createTextRange && textarea.caretPos)
		 {
			if (baseHeight != textarea.caretPos.boundingHeight)
			{
			   textarea.focus();
			   storeCaret(textarea);
			}
			var caret_pos = textarea.caretPos;
			caret_pos.text = caret_pos.texto.charAt(caret_pos.texto.length - 1) == ' ' ? caret_pos.text + text + ' ' : caret_pos.text + text;

		 }
		 else //Para quem não é possível inserir, inserimos no final mesmo (IE...)
		 {
			textarea.value = textarea.value + texto;
		 }

		 if (typeof tinymce !== 'undefined' && tinymce.get('txt')) {
			 tinymce.get('txt').insertContent(texto);
		 }
	  }

	  /*
	  Essa função abre o texto em duas strings e insere o texto bem na posição do cursor, após ele une novamento o texto mas com o texto inserido
	  Essa maravilhosa função só funciona no Mozilla... No IE não temos as propriedades selectionstart, textLength...
	  */
	  function mozWrap(txtarea, open, close)
	  {
		 var selLength = txtarea.textLength;
		 var selStart = txtarea.selectionStart;
		 var selEnd = txtarea.selectionEnd;
		 var scrollTop = txtarea.scrollTop;

		 if (selEnd == 1 || selEnd == 2)
		 {
			selEnd = selLength;
		 }
		 //S1 tem o texto do começo até a posição do cursor
		 var s1 = (txtarea.value).substring(0,selStart);

		 //S2 tem o texto selecionado
		 var s2 = (txtarea.value).substring(selStart, selEnd)

		 //S3 tem todo o texto selecionado
		 var s3 = (txtarea.value).substring(selEnd, selLength);

		 //COloca o texto na textarea. Utiliza a string que estava no início, no meio a string de entrada, depois a seleção seguida da string
		 //de fechamento e por fim o que sobrou após a seleção
		 txtarea.value = s1 + open + s2 + close + s3;
		 txtarea.selectionStart = selEnd + open.length + close.length;
		 txtarea.selectionEnd = txtarea.selectionStart;
		 txtarea.focus();
		 txtarea.scrollTop = scrollTop;
		 return;
	  }
	  /*
	  Insert at Caret position. Code from
	   [url]http://www.faqts.com/knowledge_base/view.phtml/aid/1052/fid/130[/url]
	  */
	  function storeCaret(textEl)
	  {
			if (textEl.createTextRange)
			{
			   textEl.caretPos = document.selection.createRange().duplicate();
			}
	  }
</script>


<?php
//Detalles de un pedido
  if (($action == 'edit') && ($order_exists == true)) {
    $order = new order($oID);

		// Comprobaciones DNI Cliente
		if( strlen( (string) $order->billing['nif'] ) == 0 )
		{
			// Obtenemos el DNI
			$aDatos = tep_db_query( 'select entry_NIF from address_book where customers_id = ' . $order->customer['id'] . ' order by address_book_id asc limit 1' );
			$aDatos = tep_db_fetch_array( $aDatos );

			$order->billing['nif'] = $aDatos['entry_NIF'];
		}

?>
<!-- BEGIN NEXT AND PREVIOUS ORDERS DISPLAY IN ADMIN //-->

   <tr>
    <td width="100%"><table border="0" width="100%" cellspacing="0" cellpadding="0">
      <tr>
        <td class="pageHeading"><?php if( $nextid = get_order_id($oID,'prev')) { echo '<a href="' .tep_href_link(FILENAME_ORDERS, tep_get_all_get_params(array('oID', 'action')) . 'oID=' . $nextid . '&action=edit') . '">' . PREV_ORDER . '</a>'; } ?></td>
        <td class="pageHeading" align="right"><?php echo tep_draw_separator('pixel_trans.gif', 1, 10); ?></td>
        <td class="pageHeading" align="right"><?php if( $previd = get_order_id($oID)) echo '<a href="' .tep_href_link(FILENAME_ORDERS, tep_get_all_get_params(array('oID', 'action')) . 'oID=' . $previd . '&action=edit') . '">' . NEXT_ORDER . '</a>'; ?></td>
      </tr>
    </table></td>
   </tr>
<!-- END NEXT AND PREVIOUS ORDERS DISPLAY IN ADMIN //-->
<?php /* EOF - Toolbar Denox */ ?>
	<div>
		<div class="toolbarHead">
			<div class="hdr-tlbr">
				<h1 class="pageHeading">Pedido Nº <?php echo $oID; ?> - <?php echo tep_date_short( $order->info['date_purchased'] ); ?> <?php echo date('H:i', strtotime($order->info['date_purchased'])); ?><br><br><?php echo ($order->info['amazon_id'] ? 'ID Amazon: ' . $order->info['amazon_id'] : ($order->info['ebay_id'] ? 'ID eBay: ' . $order->info['ebay_id'] : '')); ?></h1>
				<div class="btn-right">
					<a href="#" onclick="connectAsCustomer('<?php echo htmlspecialchars($order->customer['email_address'], ENT_QUOTES); ?>', '<?php echo tep_master_connect_token($order->customer['email_address']); ?>');return false;" style="float: left;"><img class="dx-hovr" src="images/icons/cnct_user.png" border="0" /></a>
					<a href="<?php echo tep_href_link(FILENAME_ORDERS_EDIT, 'oID=' . $oID);?>" title="Crear Pedido"><img class="dx-hovr" src="images/icons/icon_edit_order.png"></a>
					<a href="#" onclick="showDeleteOptions(<?php echo $oID; ?>); return false;" title="Eliminar Pedido"><img class="dx-hovr" src="images/icons/icon_delete_order.png"></a>
					<a href="<?php echo tep_href_link(FILENAME_ORDERS_PACKINGSLIP, 'oID=' . $oID);?>" title="Etiqueta de Envío"><img class="dx-hovr" src="images/icons/icon_edit_order_etiqueta.png"></a>
					<a href="<?php echo tep_href_link('albaran.php', 'oID=' . $oID);?>" title="Albarán"><img class="dx-hovr" src="images/icons/icon_edit_order_albaran.png"></a> &nbsp;&nbsp;&nbsp;&nbsp; <a href="<?php echo $back_orders;?>" title="Volver Atrás"><img class="dx-hovr" src="images/icons/icon_back.png"></a>
				</div>
			</div>
		</div>
	</div>
<?php /* BOF - Toolbar Denox */ ?>
<?php /* EOF Direcciones y datos */ ?>
		<div class="cntd-tbl">
			<div style="float:left;width:33.33%;">
				<div class="box-tbl grid" style="width:100%;">
					<div class="box-head">
						<h6><?php echo ENTRY_CUSTOMER; ?></h6>
						<div class="clear"></div>
					</div>
					<div class="box-txt">
						<?php echo tep_address_format($order->customer['format_id'], $order->customer, 1, '', '<br>', 'customers_', $oID); ?>
						<p>&nbsp;</p>
						<p><?php echo (!empty($order->billing['nif']) ? ENTRY_NIF . ' ' . $order->billing['nif'] : ''); ?></p>
						<p><?php echo ENTRY_EMAIL_ADDRESS; ?> <a href="javascript: updateOrderField('<?php echo $oID; ?>', 'orders', 'customers_email_address', '<?php echo $order->customer['email_address']; ?>');" class="ajaxLink"><?php echo $order->customer['email_address'] . '</a>'; ?></p>
						<p><?php echo ENTRY_TELEPHONE_NUMBER; ?> <a href="javascript: updateOrderField('<?php echo $oID; ?>', 'orders', 'customers_telephone', '<?php echo $order->customer['telephone']; ?>');" class="ajaxLink"><?php echo $order->customer['telephone']; ?></a></p>
						<p>&nbsp;</p>
						<p><?php echo ENTRY_CUSTOMER_GROUP . ' ' . $order->customer['customers_group_name']; ?></p>
						<p><?php echo ENTRY_FECHA_REGISTRO . ' ' . getFechaRegistroCliente($order->customer['email_address'], $order->customer['id']);?>
						<?php echo (isset($order->info['admin']) ? '<p>&nbsp;</p><p>Pedido creado por: '. $order->info['admin'] . '</p>' : ''); ?>
						<?php
							$comission = Affiliates::getComissionFromOrder(intval($oID));
							if ($comission > 0):
						?>
							<p style="margin-top: 10px;">Comisión por venta: <strong><?php echo $currencies->format($comission); ?></strong></p>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<div style="float:left;width:33.33%;padding:0 1%;box-sizing:border-box;">
				<div class="box-tbl grid" style="width:100%;">
					<div class="box-head">
						<h6><?php echo ENTRY_SHIPPING_ADDRESS; ?></h6>
						<div class="clear"></div>
					</div>
					<div class="box-txt"><?php echo tep_address_format($order->delivery['format_id'], $order->delivery, 1, '', '<br>', 'customers_', $oID); ?><br><?php echo ENTRY_TELEPHONE_NUMBER . ' ' . $order->customer['telephone']; ?></div>
				</div>
				<?php
					// Fecha estimada de entrega (se renderiza dentro de la caja "Método de envío" de abajo)
					$aDeliveryCurrent = null;
					$aDeliveryHistory = array();
					$bHasDeliveryModule = false;
					if( file_exists( DIR_FS_CATALOG . 'includes/modules/delivery_estimate/delivery_estimate.php' ) ) {
						$bHasDeliveryModule = true;
						require_once( DIR_FS_CATALOG . 'includes/modules/delivery_estimate/delivery_estimate.php' );
						require_once( DIR_WS_MODULES . 'delivery_estimate/classes/delivery_estimate_admin.php' );
						$aDeliveryCurrent = delivery_estimate::getCurrent( (int)$oID );
						$aDeliveryHistory = delivery_estimate_admin::getHistory( (int)$oID );
					}
					$bHasHistory = is_array( $aDeliveryHistory ) && count( $aDeliveryHistory ) > 1;
				?>
				<div class="box-tbl grid" style="width:100%;margin-top:4%;">
					<div class="box-head">
						<h6>Método de envío</h6>
						<div class="clear"></div>
					</div>
					<div class="box-txt">
						<!-- Método de envío -->
						<div style="margin-bottom:10px;">
							<?php if( $order->info['shipping_module'] == 'retira_retira' ): ?>
								<?php
									$aDatos = tep_db_query( 'select store_name, store_address from store where id_store = "' . (int)$order->info['id_store'] . '"' );
									if( tep_db_num_rows( $aDatos ) > 0 ) {
										$aDato = tep_db_fetch_array( $aDatos );
										echo '<i class="fa fa-map-marker" style="margin-right:6px;color:#27a5d2;"></i><strong>Recogida en tienda</strong> <small style="color:#888;">(' . htmlspecialchars( $aDato['store_name'] . ', ' . $aDato['store_address'], ENT_QUOTES, 'UTF-8' ) . ')</small>';
									}
								?>
							<?php else: ?>
								<?php
									// Resuelve el nombre bonito del módulo a partir de shipping_module (ej: tipsa_tipsa → "Mensajería")
									$sShippingCode = trim( (string)( $order->info['shipping_module'] ?? '' ) );
									$sShippingName = '';
									if( $sShippingCode !== '' ) {
										// shipping_module suele ser "<file>_<method>"; el fichero está en includes/modules/shipping/<file>.php
										$sModuleFile = strtolower( preg_replace( '/_.*$/', '', $sShippingCode ) );
										$sLangFile = DIR_FS_CATALOG . DIR_WS_LANGUAGES . $language . '/modules/shipping/' . $sModuleFile . '.php';
										if( file_exists( $sLangFile ) ) @include_once( $sLangFile );
										$sConstTitle = 'MODULE_' . strtoupper( $sModuleFile ) . '_TEXT_TITLE';
										if( defined( $sConstTitle ) ) $sShippingName = (string)constant( $sConstTitle );
									}

									// Compañía REAL de transporte: tablas de tracking API o URL del comentario de Enviado.
									$sCarrierReal = '';
									// SEUR: una fila activa en seur_shipments es señal autoritativa (cubre la ref regenerada F{oid}R{n}).
									if( tep_db_fetch_array( tep_db_query( "SELECT 1 FROM seur_shipments WHERE orders_id = " . (int)$oID . " AND ok = 1 AND cancelled_at IS NULL LIMIT 1" ) ) ) $sCarrierReal = 'SEUR';
									if( $sCarrierReal === '' ) foreach( array( 'seur_tracking' => 'SEUR', 'cex_tracking' => 'Correos Express', 'correos_tracking' => 'Correos', 'ontime_tracking' => 'Ontime' ) as $sTblT => $sNomT ) {
										if( tep_db_fetch_array( tep_db_query( "SELECT 1 FROM " . $sTblT . " WHERE referencia IN ('F" . (int)$oID . "', '" . (int)$oID . "') LIMIT 1" ) ) ) { $sCarrierReal = $sNomT; break; }
									}
									if( $sCarrierReal === '' ) {
										$rUrlT = tep_db_fetch_array( tep_db_query( "SELECT comments FROM orders_status_history WHERE orders_id = " . (int)$oID . " AND orders_status_id = 5 AND comments LIKE '%http%' ORDER BY date_added DESC LIMIT 1" ) );
										$sUrlT = (string)( $rUrlT['comments'] ?? '' );
										foreach( array( 'correosexpress' => 'Correos Express', 'cexpr.es' => 'Correos Express', 'correos.es' => 'Correos', 'seur' => 'SEUR', 'ontime' => 'Ontime', 'alertran' => 'Ontime', 'gls' => 'GLS', 'dhl' => 'DHL', 'tnt' => 'TNT' ) as $sDomT => $sNomT )
											if( stripos( $sUrlT, $sDomT ) !== false ) { $sCarrierReal = $sNomT; break; }
									}
									echo '<i class="fa fa-truck" style="margin-right:6px;color:#27a5d2;"></i>';
									if( $sCarrierReal !== '' ) {
										echo '<strong>' . htmlspecialchars( $sCarrierReal, ENT_QUOTES, 'UTF-8' ) . '</strong>';
										echo ' <small style="color:#888;">(' . htmlspecialchars( $sShippingName !== '' ? $sShippingName : $sShippingCode, ENT_QUOTES, 'UTF-8' ) . ')</small>';
									}
									elseif( $sShippingName !== '' ) {
										echo '<strong>' . htmlspecialchars( $sShippingName, ENT_QUOTES, 'UTF-8' ) . '</strong>';
										if( $sShippingCode !== '' )
											echo ' <small style="color:#888;">(' . htmlspecialchars( $sShippingCode, ENT_QUOTES, 'UTF-8' ) . ')</small>';
									}
									else {
										echo '<strong>' . htmlspecialchars( $sShippingCode, ENT_QUOTES, 'UTF-8' ) . '</strong>';
									}
								?>
							<?php endif; ?>
						</div>

						<?php
						// === Registros del envío (tracking API: seur_tracking / cex_tracking /
						//     ontime_tracking / correos_tracking, los alimentan los crons horarios).
						//     Se muestra el del transportista que tenga datos para este pedido. ===
						$sTrkCarrier = ''; $sTrkEstado = ''; $nTrkEntregado = 0; $sTrkChecked = ''; $aTrkRegs = array();
						$seurRefA = '';
						$qsrA = tep_db_query( "SELECT ref FROM seur_shipments WHERE orders_id = " . (int)$oID . " AND ref <> '' AND ok = 1 AND cancelled_at IS NULL ORDER BY id DESC LIMIT 1" );
						if ( tep_db_num_rows( $qsrA ) ) { $rA = tep_db_fetch_array( $qsrA ); $seurRefA = $rA['ref']; }
						$qTrk = ( $seurRefA !== '' )
							? tep_db_query( "SELECT * FROM seur_tracking WHERE referencia = '" . tep_db_input( $seurRefA ) . "' LIMIT 1" )
							: tep_db_query( "SELECT * FROM seur_tracking WHERE referencia IN ('F" . (int)$oID . "', '" . (int)$oID . "') ORDER BY referencia = 'F" . (int)$oID . "' DESC LIMIT 1" );
						if( $rTrk = tep_db_fetch_array( $qTrk ) ) {
							$sTrkCarrier = 'SEUR'; $sTrkEstado = $rTrk['estado_desc']; $nTrkEntregado = (int)$rTrk['entregado']; $sTrkChecked = (string)$rTrk['last_checked'];
							foreach( (array)json_decode( (string)$rTrk['eventos_json'], true ) as $eTrk )
								$aTrkRegs[] = array( 'f' => (string)( $eTrk['f'] ?? '' ), 'd' => (string)( $eTrk['d'] ?? '' ) );
						}
						if( $sTrkCarrier === '' ) {
							$qTrk = tep_db_query( "SELECT * FROM cex_tracking WHERE referencia = 'F" . (int)$oID . "' LIMIT 1" );
							if( $rTrk = tep_db_fetch_array( $qTrk ) ) {
								$sTrkCarrier = 'Correos Express'; $sTrkEstado = $rTrk['estado_desc']; $nTrkEntregado = (int)$rTrk['entregado']; $sTrkChecked = (string)$rTrk['last_checked'];
								// Histórico: fetch lazy del detalle API + caché en timeline_json (el cron horario solo
								// actualiza el estado actual; sin esto el desplegable salía vacío casi siempre).
								$aTrkTl = json_decode( (string)$rTrk['timeline_json'], true );
								$bTrkFresh = !empty( $rTrk['timeline_at'] ) && ( strtotime( $rTrk['timeline_at'] ) > time() - 3600 );
								if( !is_array( $aTrkTl ) || ( !$nTrkEntregado && !$bTrkFresh ) ) {
									require_once DIR_FS_CATALOG . 'includes/classes/correos_express.php';
									$qTrkEnv = tep_db_query( "SELECT config_value FROM cex_config WHERE config_key = 'env'" );
									$sTrkEnv = tep_db_num_rows( $qTrkEnv ) ? tep_db_fetch_array( $qTrkEnv )['config_value'] : 'test';
									$oTrkCex = new correos_express( $sTrkEnv ); $oTrkCex->setTimeout( 5 );
									$aTrkRes = $oTrkCex->seguimientoEnvio( 'F' . (int)$oID );
									if( !empty( $aTrkRes['ok'] ) && !empty( $aTrkRes['data']['estadoEnvios'] ) ) {
										$aTrkTl = array();
										foreach( $aTrkRes['data']['estadoEnvios'] as $eTrk )
											$aTrkTl[] = array( 'd' => $eTrk['descEstado'] ?? '', 'f' => $eTrk['fechaEstado'] ?? '', 'h' => $eTrk['horaEstado'] ?? '', 'i' => $eTrk['descIncEstado'] ?? '', 'e' => ( ( $eTrk['codEstado'] ?? '' ) === '12' ) ? 1 : 0 );
										tep_db_query( "UPDATE cex_tracking SET timeline_json = '" . tep_db_input( json_encode( $aTrkTl, JSON_UNESCAPED_UNICODE ) ) . "', timeline_at = now() WHERE referencia = 'F" . (int)$oID . "'" );
									}
								}
								foreach( (array)$aTrkTl as $eTrk ) {
									$sTrkF = (string)( $eTrk['f'] ?? '' ); $sTrkH = (string)( $eTrk['h'] ?? '' );
									$sTrkFmt = ( strlen( $sTrkF ) === 8 ) ? substr( $sTrkF, 0, 2 ) . '/' . substr( $sTrkF, 2, 2 ) . '/' . substr( $sTrkF, 4, 4 ) : $sTrkF;
									if( strlen( $sTrkH ) >= 4 ) $sTrkFmt .= ' ' . substr( $sTrkH, 0, 2 ) . ':' . substr( $sTrkH, 2, 2 );
									$aTrkRegs[] = array( 'f' => trim( $sTrkFmt ), 'd' => (string)( $eTrk['d'] ?? '' ) . ( !empty( $eTrk['i'] ) ? ' — ' . $eTrk['i'] : '' ) );
								}
							}
						}
						if( $sTrkCarrier === '' ) {
							$qTrk = tep_db_query( "SELECT * FROM ontime_tracking WHERE referencia = '" . (int)$oID . "' LIMIT 1" );
							if( $rTrk = tep_db_fetch_array( $qTrk ) ) {
								$sTrkCarrier = 'Ontime'; $sTrkEstado = $rTrk['estado_desc']; $nTrkEntregado = (int)$rTrk['entregado']; $sTrkChecked = (string)$rTrk['last_checked'];
								foreach( (array)json_decode( (string)$rTrk['eventos_json'], true ) as $eTrk )
									$aTrkRegs[] = array( 'f' => (string)( $eTrk['f'] ?? '' ), 'd' => (string)( $eTrk['d'] ?? '' ) . ( !empty( $eTrk['g'] ) ? ' · ' . $eTrk['g'] : '' ) );
							}
						}
						if( $sTrkCarrier === '' ) {
							$qTrk = tep_db_query( "SELECT * FROM correos_tracking WHERE referencia = 'F" . (int)$oID . "' LIMIT 1" );
							if( $rTrk = tep_db_fetch_array( $qTrk ) ) {
								$sTrkCarrier = 'Correos'; $sTrkEstado = $rTrk['estado_desc']; $nTrkEntregado = (int)$rTrk['entregado']; $sTrkChecked = (string)$rTrk['last_checked'];
								foreach( (array)json_decode( (string)$rTrk['eventos_json'], true ) as $eTrk )
									$aTrkRegs[] = array( 'f' => trim( (string)( $eTrk['f'] ?? '' ) . ' ' . (string)( $eTrk['h'] ?? '' ) ), 'd' => (string)( $eTrk['txt'] ?? '' ) );
						}
						}
						// Pedido SEUR sin fila de tracking: consulta EN VIVO a la API (cubre la
						// ventana entre el albaran y el cron) y cachea en seur_tracking para que
						// el cron horario lo siga refrescando.
						if( $sTrkCarrier === '' && strpos( (string)( $order->info['shipping_module'] ?? '' ), 'seur' ) === 0 ) {
							$sTrkCarrier = 'SEUR'; $sTrkEstado = '';
							$qEnv = tep_db_query( "SELECT config_value FROM seur_config WHERE config_key = 'env'" );
							$sSeurEnv = ( $rEnv = tep_db_fetch_array( $qEnv ) ) ? $rEnv['config_value'] : 'pre';
							if( $sSeurEnv === 'pro' ) {
								require_once( DIR_FS_CATALOG . 'includes/classes/seur.php' );
								$oSeurTrk = new seur( 'pro' ); $oSeurTrk->setTimeout( 6 );
								$sCpPed   = preg_replace( '/[^0-9A-Za-z]/', '', (string)( $order->delivery['postcode'] ?? '' ) );
								$sCityPed = preg_replace( '/[^0-9A-Za-z]/', '', (string)( $order->delivery['city'] ?? '' ) );
								foreach( array( 'F' . (int)$oID, (string)(int)$oID ) as $sRefTry ) {
									$aResT = $oSeurTrk->trackingExtended( $sRefTry );
									if( (int)$aResT['http'] === 204 ) $aResT = $oSeurTrk->trackingExtended( $sRefTry, 'REFERENCE', false );
									if( (int)$aResT['http'] === 204 || !$aResT['ok'] ) continue;
									$aPayT = seur::payload( $aResT );
									if( !is_array( $aPayT ) ) continue;
									$aSitsT = array(); $aVistoT = array(); $sEcbT = '';
									foreach( $aPayT as $aEntT ) {
										if( ( $aEntT['type'] ?? '' ) !== 'Exp' ) continue;
										$sCpE   = preg_replace( '/[^0-9A-Za-z]/', '', (string)( $aEntT['postalCode'] ?? '' ) );
										$sCityE = preg_replace( '/[^0-9A-Za-z]/', '', (string)( $aEntT['cityName'] ?? '' ) );
										$bOkCp = ( $sCpPed !== '' && $sCpE !== '' && ( stripos( $sCpPed, $sCpE ) !== false || stripos( $sCpE, $sCpPed ) !== false ) );
										$bOkCity = false;
										foreach( array( $sCityPed, $sCpPed ) as $sPedTxt )
											if( $sPedTxt !== '' && $sCityE !== '' && ( stripos( $sCityE, $sPedTxt ) !== false || stripos( $sPedTxt, $sCityE ) !== false ) ) $bOkCity = true;
										if( ( $sCpPed !== '' || $sCityPed !== '' ) && !$bOkCp && !$bOkCity ) continue;
										foreach( (array)( $aEntT['situations'] ?? array() ) as $aSitT ) {
											$kT = ( $aSitT['eventCode'] ?? '' ) . '|' . ( $aSitT['situationDate'] ?? '' );
											if( isset( $aVistoT[$kT] ) ) continue;
											$aVistoT[$kT] = true; $aSitsT[] = $aSitT;
										}
										if( $sEcbT === '' && !empty( $aEntT['packs'][0]['ecb'] ) ) $sEcbT = (string)$aEntT['packs'][0]['ecb'];
									}
									if( !count( $aSitsT ) ) continue;
									usort( $aSitsT, function( $a, $b ) { return strcmp( (string)( $a['situationDate'] ?? '' ), (string)( $b['situationDate'] ?? '' ) ); } );
									$sFentT = ''; $aEvJsonT = array();
									foreach( $aSitsT as $aSitT ) {
										$sDT = (string)( $aSitT['description'] ?? '' );
										$sFT = substr( str_replace( 'T', ' ', (string)( $aSitT['situationDate'] ?? '' ) ), 0, 19 );
										$sDU = mb_strtoupper( $sDT );
										if( strpos( $sDU, 'ENTREGAD' ) !== false && !preg_match( '/\\b(NO|SIN)\\s+ENTREG/u', $sDU ) ) { $nTrkEntregado = 1; $sFentT = $sFT; }
										$aTrkRegs[] = array( 'f' => substr( $sFT, 0, 16 ), 'd' => $sDT );
										$aEvJsonT[] = array( 'f' => substr( $sFT, 0, 16 ), 't' => (string)( $aSitT['eventCode'] ?? '' ), 'd' => $sDT );
									}
									$aUltT = end( $aSitsT );
									$sTrkEstado = (string)( $aUltT['description'] ?? '' ); $sTrkChecked = date( 'Y-m-d H:i:s' );
									tep_db_query( "INSERT INTO seur_tracking (referencia, ecb, estado_code, estado_desc, entregado, fecha_entrega, eventos_json, last_checked) VALUES ('"
										. tep_db_input( $sRefTry ) . "', '" . tep_db_input( $sEcbT ) . "', '" . tep_db_input( substr( (string)( $aUltT['eventCode'] ?? '' ), 0, 8 ) ) . "', '" . tep_db_input( substr( $sTrkEstado, 0, 80 ) ) . "', " . (int)$nTrkEntregado . ", "
										. ( $sFentT !== '' ? "'" . tep_db_input( $sFentT ) . "'" : "NULL" ) . ", '" . tep_db_input( json_encode( $aEvJsonT, JSON_UNESCAPED_UNICODE ) ) . "', now()) "
										. "ON DUPLICATE KEY UPDATE ecb=VALUES(ecb), estado_code=VALUES(estado_code), estado_desc=VALUES(estado_desc), entregado=VALUES(entregado), fecha_entrega=COALESCE(VALUES(fecha_entrega), fecha_entrega), eventos_json=VALUES(eventos_json), last_checked=now()" );
									break;
								}
							}
						}
						?>
						<?php if( $sTrkCarrier !== '' ): $aTrkRegs = array_reverse( $aTrkRegs ); ?>
						<!-- Separador -->
						<div style="border-top:1px dashed #ddd;margin:10px 0;"></div>
						<div style="font-size:11px;color:#999;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">Registros del envío <small>(<?php echo htmlspecialchars( $sTrkCarrier, ENT_QUOTES, 'UTF-8' ); ?>)</small></div>
						<div style="margin-bottom:6px;">
							<?php if( $sTrkEstado === '' ): ?>
								<i class="fa fa-circle" style="font-size:9px;margin-right:5px;color:#bbb;"></i>
								<span style="color:#888;">Sin registros todav&iacute;a &mdash; aparecer&aacute;n cuando el almac&eacute;n grabe el env&iacute;o (se actualizan cada hora).</span>
							<?php else: ?>
								<i class="fa fa-circle" style="font-size:9px;margin-right:5px;color:<?php echo $nTrkEntregado ? '#2e9e44' : '#ee7f00'; ?>"></i>
								<strong><?php echo htmlspecialchars( (string)$sTrkEstado, ENT_QUOTES, 'UTF-8' ); ?></strong>
								<?php if( $sTrkChecked !== '' ): ?><small style="color:#999;">(<?php echo date( 'd/m H:i', strtotime( $sTrkChecked ) ); ?>)</small><?php endif; ?>
							<?php endif; ?>
						</div>
						<?php if( count( $aTrkRegs ) ): ?>
						<details style="font-size:12px;">
							<summary style="cursor:pointer;color:#27a5d2;">Ver los <?php echo count( $aTrkRegs ); ?> registros</summary>
							<ul style="list-style:none;margin:6px 0 0;padding:0;">
							<?php foreach( $aTrkRegs as $eTrk ):
								$sTrkD  = mb_strtoupper( $eTrk['d'] );
								$bTrkOk = ( strpos( $sTrkD, 'ENTREGAD' ) !== false && !preg_match( '/\b(NO|SIN)\s+ENTREG/u', $sTrkD ) );
								$bTrkKo = ( strpos( $sTrkD, 'ANULAD' ) !== false || strpos( $sTrkD, 'CANCELAD' ) !== false || strpos( $sTrkD, 'DEVOL' ) !== false || strpos( $sTrkD, 'DEVUELTO' ) !== false || preg_match( '/\b(NO|SIN)\s+ENTREG/u', $sTrkD ) );
							?>
								<li style="padding:2px 0;">
									<i class="fa fa-circle" style="font-size:8px;margin-right:6px;color:<?php echo $bTrkOk ? '#2e9e44' : ( $bTrkKo ? '#c0392b' : '#9bb7cc' ); ?>"></i>
									<?php echo htmlspecialchars( mb_convert_case( mb_strtolower( $eTrk['d'] ), MB_CASE_TITLE ), ENT_QUOTES, 'UTF-8' ); ?>
									<?php if( $eTrk['f'] !== '' ): ?><small style="color:#999;"> — <?php echo htmlspecialchars( $eTrk['f'], ENT_QUOTES, 'UTF-8' ); ?></small><?php endif; ?>
								</li>
							<?php endforeach; ?>
							</ul>
						</details>
						<?php endif; ?>
						<?php endif; ?>

						<?php if( $bHasDeliveryModule ): ?>
						<!-- Separador -->
						<div style="border-top:1px dashed #ddd;margin:10px 0;"></div>

						<!-- Fecha estimada de entrega -->
						<div style="font-size:11px;color:#999;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">Fecha estimada de entrega</div>
						<?php if( is_array( $aDeliveryCurrent ) && ! empty( $aDeliveryCurrent['estimated_date'] ) ): ?>
							<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:8px;">
								<div>
									<i class="fa fa-calendar" style="color:#27a5d2;margin-right:6px;"></i>
									<strong><?php echo tep_date_short( $aDeliveryCurrent['estimated_date'] ); ?></strong>
								</div>
								<?php if( $aDeliveryCurrent['is_manual'] == 1 ): ?>
									<small style="color:#888;white-space:nowrap;"<?php echo $aDeliveryCurrent['admin_user'] ? ' title="' . htmlspecialchars( $aDeliveryCurrent['admin_user'], ENT_QUOTES, 'UTF-8' ) . '"' : ''; ?>><i class="fa fa-user-circle-o"></i> Manual</small>
								<?php else: ?>
									<small style="color:#888;white-space:nowrap;"><i class="fa fa-cog"></i> Automática</small>
								<?php endif; ?>
							</div>
						<?php else: ?>
							<div style="margin-bottom:8px;color:#888;"><i class="fa fa-info-circle" style="margin-right:6px;"></i>Sin fecha estimada registrada.</div>
						<?php endif; ?>
						<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px;">
							<a href="#delivery-estimate-change-popup" class="buttonS bGreen delivery-estimate-popup-link" title="Cambiar fecha estimada"><i class="fa fa-calendar"></i> Cambiar</a>
							<?php if( $bHasHistory ): ?>
								<a href="#delivery-estimate-history-popup" class="buttonS delivery-estimate-popup-link" title="Ver histórico de cambios" style="background:#7a8899;"><i class="fa fa-history"></i> Histórico (<?php echo count( $aDeliveryHistory ); ?>)</a>
							<?php endif; ?>
						</div>
						<?php endif; ?>
					</div>
				</div>

				<?php if( $bHasDeliveryModule ): ?>

				<!-- Lightbox: cambiar fecha -->
				<div id="delivery-estimate-change-popup" class="mfp-hide mfp-white" style="padding:0;max-width:560px;">
					<style>#delivery-estimate-change-popup .mfp-close, #delivery-estimate-history-popup .mfp-close { color:#ffffff !important; opacity:1 !important; }</style>
					<div style="background:#27a5d2;color:#fff;padding:14px 20px;">
						<h3 style="margin:0;font-size:16px;"><i class="fa fa-calendar"></i> Cambiar fecha estimada de entrega</h3>
					</div>
					<form action="<?php echo tep_href_link( FILENAME_ORDERS, tep_get_all_get_params( array( 'action' ) ) . 'action=update_delivery_estimate', 'NONSSL' ); ?>" method="post" style="padding:20px;">
						<?php if( is_array( $aDeliveryCurrent ) && ! empty( $aDeliveryCurrent['estimated_date'] ) ): ?>
							<div style="background:#f6f6f6;padding:10px 14px;border-radius:4px;margin-bottom:16px;font-size:13px;color:#555;">
								<i class="fa fa-info-circle" style="margin-right:6px;color:#888;"></i>
								Fecha actual: <strong><?php echo tep_date_short( $aDeliveryCurrent['estimated_date'] ); ?></strong>
							</div>
						<?php endif; ?>
						<div style="margin-bottom:14px;">
							<label style="display:block;margin-bottom:4px;font-weight:bold;">Nueva fecha:</label>
							<input type="date" name="delivery_estimated_date" value="<?php echo ( is_array( $aDeliveryCurrent ) ? htmlspecialchars( $aDeliveryCurrent['estimated_date'], ENT_QUOTES, 'UTF-8' ) : '' ); ?>" required style="width:100%;padding:8px;box-sizing:border-box;" />
						</div>
						<div style="margin-bottom:14px;">
							<label style="display:block;margin-bottom:4px;font-weight:bold;">Comentario (opcional):</label>
							<textarea name="delivery_comment" rows="4" style="width:100%;padding:8px;box-sizing:border-box;resize:vertical;" placeholder="Este mensaje se mostrará al cliente en su pedido y en el email si lo notificas."></textarea>
						</div>
						<div style="margin-bottom:18px;background:#f6f6f6;padding:10px 14px;border-radius:4px;">
							<label style="font-weight:normal;cursor:pointer;">
								<input type="checkbox" name="delivery_notify" checked="checked" style="margin-right:6px;" />
								<i class="fa fa-envelope" style="margin-right:4px;color:#27a5d2;"></i>
								Enviar email al cliente con la nueva fecha
							</label>
						</div>
						<div style="text-align:right;">
							<button type="submit" class="buttonS bGreen"><i class="fa fa-check"></i> Actualizar fecha</button>
						</div>
					</form>
				</div>

				<?php if( $bHasHistory ): ?>
				<!-- Lightbox: histórico -->
				<div id="delivery-estimate-history-popup" class="mfp-hide mfp-white" style="padding:0;max-width:780px;">
					<div style="background:#7a8899;color:#fff;padding:14px 20px;">
						<h3 style="margin:0;font-size:16px;"><i class="fa fa-history"></i> Histórico de fechas estimadas</h3>
					</div>
					<div style="padding:20px;max-height:70vh;overflow-y:auto;">
						<table class="tAlt wGeneral" style="width:100%;border-collapse:collapse;">
							<thead>
								<tr style="background:#f3f3f3;">
									<th style="text-align:left;padding:8px;border-bottom:1px solid #e4e4e4;">Fecha registro</th>
									<th style="text-align:left;padding:8px;border-bottom:1px solid #e4e4e4;">Fecha estimada</th>
									<th style="text-align:left;padding:8px;border-bottom:1px solid #e4e4e4;">Origen</th>
									<th style="text-align:center;padding:8px;border-bottom:1px solid #e4e4e4;"><i class="fa fa-envelope" title="Email enviado"></i></th>
									<th style="text-align:left;padding:8px;border-bottom:1px solid #e4e4e4;">Comentario</th>
								</tr>
							</thead>
							<tbody>
							<?php foreach( $aDeliveryHistory as $aH ): ?>
								<tr>
									<td style="padding:8px;border-bottom:1px solid #f0f0f0;font-size:12px;color:#888;white-space:nowrap;"><?php echo date( 'd/m/Y H:i', strtotime( $aH['created_at'] ) ); ?></td>
									<td style="padding:8px;border-bottom:1px solid #f0f0f0;"><strong><?php echo tep_date_short( $aH['estimated_date'] ); ?></strong></td>
									<td style="padding:8px;border-bottom:1px solid #f0f0f0;font-size:13px;">
										<?php if( $aH['is_manual'] == 1 ): ?>
											<i class="fa fa-user-circle-o" style="color:#7a8899;"></i> Manual<?php echo $aH['admin_user'] ? ' <small style="color:#888;">· ' . htmlspecialchars( $aH['admin_user'], ENT_QUOTES, 'UTF-8' ) . '</small>' : ''; ?>
										<?php else: ?>
											<i class="fa fa-cog" style="color:#27a5d2;"></i> Automática
										<?php endif; ?>
									</td>
									<td style="padding:8px;border-bottom:1px solid #f0f0f0;text-align:center;">
										<?php if( $aH['email_sent'] == 1 ): ?>
											<i class="fa fa-check" style="color:#4daa15;" title="Email enviado"></i>
										<?php else: ?>
											<span style="color:#ccc;">–</span>
										<?php endif; ?>
									</td>
									<td style="padding:8px;border-bottom:1px solid #f0f0f0;font-size:12px;font-style:italic;color:#555;"><?php echo ! empty( $aH['comment'] ) ? nl2br( htmlspecialchars( $aH['comment'], ENT_QUOTES, 'UTF-8' ) ) : '<span style="color:#ccc;font-style:normal;">–</span>'; ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
				<?php endif; ?>

				<script type="text/javascript">
				(function initDeliveryPopups(){
					// jQuery y magnific-popup se cargan en el footer — esperamos hasta que estén disponibles
					if( typeof jQuery === 'undefined' || typeof jQuery.fn === 'undefined' || typeof jQuery.fn.magnificPopup === 'undefined' ) {
						return setTimeout( initDeliveryPopups, 50 );
					}
					jQuery('.delivery-estimate-popup-link').magnificPopup({
						type: 'inline',
						midClick: true,
						removalDelay: 160,
						mainClass: 'my-mfp-zoom-in'
					});
				})();
				</script>
				<?php endif; // EOF Fecha estimada de entrega lightboxes ?>
			</div>

			<div style="float:left;width:33.33%;">
				<div class="box-tbl grid" style="width:100%;">
					<div class="box-head">
						<h6><?php echo ENTRY_BILLING_ADDRESS; ?></h6>
						<div class="clear"></div>
					</div>
					<div class="box-txt">
						<?php echo tep_address_format($order->billing['format_id'], $order->billing, 1, '', '<br>', 'customers_', $oID); ?>
					</div>
				</div>
				<div class="box-tbl grid" style="width:100%;margin-top:4%;">
					<div class="box-head">
						<h6><?php echo ENTRY_PAYMENT_METHOD; ?></h6>
						<div class="clear"></div>
					</div>
					<div class="box-txt">
						<?php echo $order->info['payment_method']; ?>
						<?php if (tep_not_null($order->info['cc_type']) || tep_not_null($order->info['cc_owner']) || tep_not_null($order->info['cc_number'])): ?>
							<p><?php echo ENTRY_CREDIT_CARD_TYPE; ?> <?php echo $order->info['cc_type']; ?></p>
							<p><?php echo ENTRY_CREDIT_CARD_OWNER; ?> <?php echo $order->info['cc_owner']; ?></p>
							<p><?php echo ENTRY_CREDIT_CARD_NUMBER; ?> <?php echo $order->info['cc_number']; ?></p>
							<p><?php echo ENTRY_CREDIT_CARD_EXPIRES; ?> <?php echo $order->info['cc_expires']; ?></p>
						<?php endif; ?>
						<?php echo buttonGenerarDevolucion(intval($oID)); ?>
						<?php echo buttonDevolverPuntos(intval($oID)); ?>
					</div>
				</div>
			</div>
		</div>
<?php
	/* BOF Direcciones y datos
	   EOF Tabla de productos */
?>
		<div class="clear"></div>
			<div class="box-tbl" style="width: 100%">
				<div class="box-head">
					<h6>Productos del pedido</h6>
					<div class="clear"></div>
				</div>

			<?php
			// --- Estado de almacén por línea (alimentado por sync_warehouse_to_web.py
			// cada 5 min). Se muestra una columna "Almacén" sólo si hay datos para
			// el pedido (orders en preparación). Para cada producto cruzamos por
			// products.CCODIART = orders_warehouse_status.sku.
			// Clave compuesta sku|variante (variante = concat CCODIVAL de orders_products_attributes,
			// igual formato que el sync; permite distinguir variantes que comparten CCODIART)
			$aWarehouseStatus = []; // 'sku|variante' => ['status'=>..., 'arrival_date'=>...]
			$qWh = tep_db_query("SELECT sku, variante, status, arrival_date FROM orders_warehouse_status WHERE orders_id = " . (int)$oID);
			while ($rWh = tep_db_fetch_array($qWh)) {
				$aWarehouseStatus[$rWh['sku'] . '|' . $rWh['variante']] = [
					'status' => $rWh['status'],
					'arrival_date' => $rWh['arrival_date'],
				];
			}
			$bShowWarehouseColumn = ! empty($aWarehouseStatus);
			$aProductSkuCache = []; // products_id => CCODIART
			// Variante por orders_products_id (CCODIVAL concatenados, mismo formato que sync)
			$aLineVariante = [];
			$qLv = tep_db_query(
				'SELECT op.orders_products_id, '
				. "COALESCE(GROUP_CONCAT(DISTINCT pov.CCODIVAL ORDER BY opa.products_options_id SEPARATOR ' / '), '') AS variante "
				. 'FROM ' . TABLE_ORDERS_PRODUCTS . ' op '
				. 'LEFT JOIN orders_products_attributes opa ON opa.orders_products_id = op.orders_products_id '
				. 'LEFT JOIN products_options_values pov ON pov.products_options_values_id = opa.products_options_values_id '
				. 'WHERE op.orders_id = ' . (int)$oID . ' '
				. 'GROUP BY op.orders_products_id'
			);
			while ($rLv = tep_db_fetch_array($qLv)) {
				$aLineVariante[(int)$rLv['orders_products_id']] = (string)$rLv['variante'];
			}
			?>
			<table cellpadding="0" cellspacing="0" width="100%" class="tAlt wGeneral">
				<thead>
					<tr>
						<td width="100px">Acciones</td>
						<td width="60px">Imagen</td>
						<td width="30px">Cantidad</td>
						<td><?php echo TABLE_HEADING_PRODUCTS; ?></td>
						<td><?php echo TABLE_HEADING_PRODUCTS_MODEL; ?></td>
						<td><?php echo TABLE_HEADING_PRODUCTS_REF; ?></td>
						<td><?php echo 'Ubicacion'; ?></td>
						<?php if ($bShowWarehouseColumn): ?>
							<td width="180px">Almacén</td>
						<?php endif; ?>
						<td>Marca/Fabricante</td>
						<td><?php echo TABLE_HEADING_TAX; ?></td>
						<td><?php echo TABLE_HEADING_PRICE_EXCLUDING_TAX; ?></td>
						<td><?php echo TABLE_HEADING_PRICE_INCLUDING_TAX; ?></td>
						<td><?php echo TABLE_HEADING_TOTAL_EXCLUDING_TAX; ?></td>
						<td><?php echo TABLE_HEADING_TOTAL_INCLUDING_TAX; ?></td>
					</tr>
				</thead>
				<tbody>
				<?php

					for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
						// Obtenemos la imagen
						$sImagen = 'no-image.png';
						if($order->products[$i]['products_id']!=''){
							$aDatos = tep_db_query( 'select products_image from products where products_id = ' . $order->products[$i]['products_id'] );
							if( tep_db_num_rows( $aDatos ) > 0 )
							{
								$aDatos = tep_db_fetch_array( $aDatos );
								$sImagen = $aDatos['products_image'];
							}
						}

						$category_query = tep_db_query("select categories_id from " . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . $order->products[$i]['products_id'] . "'");
    					$category_data = tep_db_fetch_array($category_query);


						echo '          <tr>' . "\n" .
								'            <td align="center">
												<div class="btn-group" style="display: inline-block; margin-bottom: -4px;">
								                    <a class="buttonS bDefault" data-toggle="dropdown" href="#">Acciones<span class="caret"></span></a>
								                    <ul class="dropdown-menu">
								                        <li><a href="../product_info.php?products_id='.$order->products[$i]['products_id'].'" target="_blank"><span class="icos-preview"></span>Ver en tienda</a></li>
								                        <li><a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $category_data['categories_id'] . '&pID=' . $order->products[$i]['products_id'] . '&action=new_product') . '" target="_blank"><span class="icos-pencil"></span>Editar</a></li>
								                    </ul>
								                </div>
                            				 </td>' . "\n" .
								'            <td align="center">' . tep_image(DIR_WS_CATALOG_IMAGES . 'productos/' . $sImagen, $order->products[$i]['name'], 50, 50, '', false) . '</td>' . "\n" .
								'            <td align="center">' . $order->products[$i]['qty'] . '</td>' . "\n" .
								'            <td>' . $order->products[$i]['name'] . $return_link;

						// Marca de linea modificada por el sync de QFacWin
						if (!empty($order->products[$i]['qfac_sync_note'])) {
							$qn = htmlspecialchars($order->products[$i]['qfac_sync_note']);
							$qd = !empty($order->products[$i]['qfac_sync_at']) ? date('d/m/Y H:i', strtotime($order->products[$i]['qfac_sync_at'])) : '';
							echo '<br><span style="display:inline-block;background:#e67e22;color:#fff;font-size:9px;font-weight:600;padding:1px 6px;border-radius:8px;line-height:1.2;margin-top:3px;" title="Modificado por sincronizacion con QFacWin' . ($qd ? ' · ' . $qd : '') . '"><i class="fa fa-pencil" style="font-size:8px;"></i> Modificado QFac: ' . $qn . '</span>';
						}
						echo '';

						if (isset($order->products[$i]['attributes']) && (sizeof($order->products[$i]['attributes']) > 0)) {
							for ($j = 0, $k = sizeof($order->products[$i]['attributes']); $j < $k; $j++) {
							  echo '<br><nobr><small>&nbsp;<i> - ' . $order->products[$i]['attributes'][$j]['option'] . ': ' . $order->products[$i]['attributes'][$j]['value'];
							  if ($order->products[$i]['attributes'][$j]['price'] != '0') echo ' (' . $order->products[$i]['attributes'][$j]['prefix'] . $currencies->format($order->products[$i]['attributes'][$j]['price'] * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']) . ')';
							  echo '</i></small></nobr>';
							}
						}

						echo '            </td>' . "\n" .
							   '            <td align="center">' . $order->products[$i]['model'] . '</td>' . "\n" .
							   '            <td align="center">' . $order->products[$i]['products_reference'] . '</td>' . "\n" .
							   '            <td align="center">' . $order->products[$i]['ubicacion'] . '</td>' . "\n";

						if ($bShowWarehouseColumn) {
							// Cruce por products.CCODIART (cache en $aProductSkuCache para
							// evitar 1 consulta por línea cuando el pedido tiene varias).
							$pid = (int)$order->products[$i]['products_id'];
							if (!isset($aProductSkuCache[$pid])) {
								$qSku = tep_db_query("SELECT CCODIART FROM products WHERE products_id = $pid LIMIT 1");
								$rSku = tep_db_fetch_array($qSku);
								$aProductSkuCache[$pid] = $rSku ? (string)$rSku['CCODIART'] : '';
							}
							$skuLine = $aProductSkuCache[$pid];
							$opid = (int)$order->products[$i]['id'];
							$varLine = $aLineVariante[$opid] ?? '';
							$keyWh = $skuLine . '|' . $varLine;
							$wh = ($skuLine !== '' && isset($aWarehouseStatus[$keyWh])) ? $aWarehouseStatus[$keyWh] : null;
							$cellHtml = '';
							if ($wh === null) {
								$cellHtml = '<span style="color:#aaa;">—</span>';
							} elseif ($wh['status'] === 'reservado') {
								$cellHtml = '<span style="display:inline-block;background:#22a06b;color:#fff;font-size:11px;font-weight:600;padding:3px 9px;border-radius:10px;line-height:1;"><i class="fa fa-check" style="font-size:10px;"></i> Reservado</span>';
							} elseif ($wh['status'] === 'enviado') {
								$cellHtml = '<span style="display:inline-block;background:#3373c4;color:#fff;font-size:11px;font-weight:600;padding:3px 9px;border-radius:10px;line-height:1;" title="Producto ya enviado al cliente (incluido en albaran VStock)">📦 Enviado</span>';
							} else { // esperando
								if (! empty($wh['arrival_date'])) {
									$dateFmt = date('d/m/Y', strtotime($wh['arrival_date']));
									$cellHtml = '<span style="display:inline-block;background:#f0a020;color:#fff;font-size:12px;font-weight:600;padding:4px 10px;border-radius:11px;line-height:1.1;" title="Hay un pedido de compra abierto al proveedor con fecha de llegada confirmada"><i class="fa fa-truck" style="font-size:10px;"></i> Pedido a Proveedor · Llegada ' . $dateFmt . '</span>';
								} else {
									$cellHtml = '<span style="display:inline-block;background:#d23a3a;color:#fff;font-size:11px;font-weight:600;padding:3px 9px;border-radius:10px;line-height:1;" title="No hay pedido de compra abierto en QFacWin"><i class="fa fa-warning" style="font-size:10px;"></i> Pendiente pedir proveedor</span>';
								}
							}
							echo '            <td align="center">' . $cellHtml . '</td>' . "\n";
						}

						echo '            <td align="center">' . getFabricanteName($order->products[$i]['products_id']) . '</td>' . "\n" .
							   '            <td align="center">' . tep_display_tax_value($order->products[$i]['tax']) . '%</td>' . "\n" .
							   '            <td align="center"><b>' . $currencies->format($order->products[$i]['final_price'], true, $order->info['currency'], $order->info['currency_value']) . '</b></td>' . "\n" .
							   '            <td align="center"><b>' . $currencies->format(tep_add_tax($order->products[$i]['final_price'], $order->products[$i]['tax'], true), true, $order->info['currency'], $order->info['currency_value']) . '</b></td>' . "\n" .
							   '            <td align="center"><b>' . $currencies->format($order->products[$i]['final_price'] * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']) . '</b></td>' . "\n" .
							   '            <td align="center"><b>' . $currencies->format(tep_add_tax($order->products[$i]['final_price'], $order->products[$i]['tax'], true) * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']) . '</b></td>' . "\n";
						 echo '          </tr>' . "\n";
					}
				?>
				</tbody>
			</table>
			</div>
			<div class="total">
				<?php
					for ($i = 0, $n = sizeof($order->totals); $i < $n; $i++) {
						echo '<p>'.  $order->totals[$i]['title'] . ' ' .  $order->totals[$i]['text'] . '</p>';
					}
				?>
			</div>

<?php /* EOF Comentarios de pedido */ ?>
		<div class="fluid grid">
			<div class="box-tbl grid6">
				<div class="box-head">
					<h6><?php echo TABLE_HEADING_COMMENTS; ?></h6>
					<div class="clear"></div>
				</div>
				<?php echo tep_draw_form('status', FILENAME_ORDERS, tep_get_all_get_params(array('action')) . 'action=update_order'); ?>
				<div class="formRow">
                    <div class="grid3"><label>Añadir texto predefinido:</label></div>
                    <div class="grid9">
						<select id="txtValor">
							<optgroup label="Comentarios estáticos">
								<option value="">Elegir un texto</option>
								<option value="<?php echo STORE_NAME_ADDRESS; ?>">Dirección de la tienda</option>
								<option value="<?php echo STORE_NAME; ?>">Nombre de la tienda</option>
								<option value="<?php echo $_SERVER['SERVER_NAME']; ?>">Web</option>
								<option value="<?php echo STORE_OWNER; ?>">Dueño de la tienda</option>
								<option value="<?php echo STORE_OWNER_EMAIL_ADDRESS; ?>">Email de la tienda</option>
								<option value="<?php echo $_GET['oID']; ?>">Nº Pedido</option>
								<option value="<?php echo str_replace( '/', '-', tep_date_short( $order->info['date_purchased'] ) ); ?>">Fecha del Pedido</option>
							</optgroup>

							<optgroup label="Comentarios dinámicos">
							<?php
								$get_premades_query = tep_db_query("select * from orders_premade_comments order by id");
								while($result = tep_db_fetch_array($get_premades_query))
								{
									echo '<option value="'.htmlentities($result["text"]).'">'. $result["title"].'</option>';
								}
							?>
							</optgroup>
							<optgroup label="Seguimiento de pedido">
								<option value="http://www.seur.com/seguimiento/<?php echo $_GET['oID']; ?>/fecha/<?php echo str_replace( '/', '-', tep_date_short( $order->info['date_purchased'] ) ); ?>">Seur</option>
								<option value="http://www.gls-group.eu/276-I-PORTAL-WEB/content/GLS/ES01/ES/5004.htm?txtAction=71010&un=7240003784&pw=1234&rf=&crf=<?php echo $_GET['oID']; ?>&lc=ES&no=7240003784">GLS</option>
								<option value="http://trackandtrace.kiala.com/search?countryid=ES&language=es&dspid=34600140&dspparcelid=<?php echo $_GET['oID']; ?>">Kiala</option>
							</optgroup>

						</select>
						<p></p><input type="button" value="Insertar" onclick="insereTexto('txtValor')"/>  <input type="button" value="Limpiar" onclick="this.form.elements['comments'].value=''"> <font class="smallText"><a href="premade_comments.php">[Configurar textos]</a></font></p>
					</div>
					<div class="clear"></div>
                </div>
				<div class="formRow">
					<div class="grid3"><label style="line-height: 33px;">Formas de envio:&nbsp;&nbsp;</label></div>
					<div class="grid9">
						<select id="txtValor2">
							<option value="">Elegir un texto</option>
							<option value="<?php echo TABLE_HEADING_COMENTARIO_1; ?>">Correos ordinario</option>
							<option value="<?php echo TABLE_HEADING_COMENTARIO_2; ?>">Correos certificado</option>
							<option value="<?php echo preg_replace( '/\<CP\>/i', $order->delivery['postcode'], TABLE_HEADING_COMENTARIO_23 ); ?>">Correos Express</option>
							<option value="<?php echo TABLE_HEADING_COMENTARIO_4; ?>">Seur</option>
							<option value="<?php echo TABLE_HEADING_COMENTARIO_18; ?>">GLS</option>
							<option value="<?php echo TABLE_HEADING_COMENTARIO_21; ?>">Kiala</option>
						</select>
						<p></p><input type="button" value="Insertar" onclick="insereTexto('txtValor2')"/>  <input type="button" value="Limpiar" onclick="this.form.elements['comments'].value=''"/></p>
					</div>
					<div class="clear"></div>
				</div>
                <div class="formRow">
                        <div class="grid3"><label>Escribe tu comentario:</label></div>
                        <div class="grid9"><?php echo tep_draw_textarea_field_tinymce('comments', 'soft', '80', '5','','id="txt" class="tinymce-simple"'); ?></div>
                        <div class="clear"></div>
                </div>
                <div class="formRow">
                    <div class="grid3"><label><?php echo ENTRY_STATUS; ?></label></div>
                    <div class="grid9">
                    	<?php echo tep_draw_pull_down_menu('status', $orders_statuses, $order->info['orders_status']); ?>
                    </div>
                    <div class="clear"></div>
                </div>
	            <div class="formRow">
	                <div class="grid3"><label><?php echo ENTRY_NOTIFY_CUSTOMER; ?></label></div>
	                <div class="grid9 check">
	                    <?php echo tep_draw_checkbox_field('notify', '', true); ?> <label for="check2" class="mr20">Cambio de estado&nbsp;&nbsp;&nbsp;</label><?php echo tep_draw_checkbox_field('notify_comments', '', true); ?> <label for="check2" class="mr20">Comentarios</label>
	                </div>
	                <div class="clear"></div>
	            </div>
				<?php
				if ((USE_POINTS_SYSTEM == 'true') && !tep_not_null(POINTS_AUTO_ON)) {
					$p_status_query = tep_db_query("select points_status from " . TABLE_CUSTOMERS_POINTS_PENDING . " where points_status = 1 and points_type = 'SP' and orders_id = '" . (int)$oID . "' limit 1");
					if (tep_db_num_rows($p_status_query)) {
				?>
				<div class="formRow">
					<div class="grid3"><label><?php echo ENTRY_NOTIFY_POINTS; ?></label></div>
					<div class="grid9 check">
						<?php echo tep_draw_checkbox_field('confirm_points', '', false); ?> <label class="mr20"><?php echo ENTRY_QUE_POINTS; ?></label>
						<?php echo tep_draw_checkbox_field('delete_points', '', false); ?> <label class="mr20"><?php echo ENTRY_QUE_DEL_POINTS; ?></label>
					</div>
					<div class="clear"></div>
				</div>
				<?php
					}
				}
				?>
	            <div class="formRow">
		                <div class="wButton grid6">
		                	<a href="#" onclick="$(this).closest('form').submit()" title="" class="buttonL bGreen" style="margin-top: 10px;">Enviar comentario</a>
		                </div>
	                <div class="clear"></div>
	            </div>
			</div>
			</form>
			<div class="sResults grid6">
                        <span class="arrow"></span>
                        <ul class="updates">
						<?php
							$orders_history_query = tep_db_query("select orders_status_id, date_added, customer_notified, comments from " . TABLE_ORDERS_STATUS_HISTORY . " where orders_id = '" . tep_db_input($oID) . "' order by date_added desc");
							if (tep_db_num_rows($orders_history_query)) {
							  while ($orders_history = tep_db_fetch_array($orders_history_query)) {
								if ($orders_history['customer_notified'] == '1')
									$tick = 'uDone';
								else
									$tick = 'uAlert';

								echo '<li>
										<span class="'.$tick.'">
											<p>' . $orders_status_array[$orders_history['orders_status_id']] . '</p>
											<span class="cmnt-dscrpt">' . str_replace( chr(13), '<br/>', $orders_history['comments'] ?? '' ) . '</span>
										</span>
										<span class="uDate"><span>' . tep_date_day_short($orders_history['date_added']) . '</span>' . tep_date_month_short($orders_history['date_added']) . '<br>' . tep_datetime_hour_short($orders_history['date_added']) . '</span>
										<span class="clear"></span>
									</li>';
							  }
							} else {
							?>
								<li>
									<span class="uNoticee">
										<a href="#" title=""><?php echo TEXT_NO_ORDER_HISTORY; ?></a>
										<span>Puedes notificarle cualquier cambio de estado que quieras al cliente desde aquí. Con ello recibirá un e-mail automáticamente y se mostrará en si historial de pedidos si así lo deseas.</span>
									</span>
									<span class="clear"></span>
								</li>
							<?php } ?>
                        </ul>
                    </div>
		</div>

<?php
  } else {
  //Listado de pedidos

// --- Construcción dinámica de WHERE ---
$where = [];

// filtros dinámicos
if (!empty($_GET['cID'])) {
	$where[] = "o.customers_id = " . (int)tep_db_prepare_input($_GET['cID']);
}
if (!empty($_GET['status'])) {
	$where[] = "o.orders_status = " . (int)tep_db_prepare_input($_GET['status']);
}
if (!empty($_GET['ship'])) {
	$sh = tep_db_prepare_input($_GET['ship']);
	if ($sh === 'seururgente') {
		// SEUR 13:30 (seurnacional) + SEUR 10 (seurdiez) = los urgentes a priorizar
		$where[] = "o.shipping_module IN ('seurnacional_seurnacional', 'seurdiez_seurdiez')";
	} else {
		$where[] = "o.shipping_module = '" . tep_db_input($sh) . "'";
	}
}
if (!empty($_GET['aID'])) {
	$aID = tep_db_prepare_input($_GET['aID']);
	$where[] = "o.amazon_id LIKE '%" . tep_db_input($aID) . "%'";
}
if (!empty($_GET['ebay_id'])) {
	$eID = tep_db_prepare_input($_GET['ebay_id']);
	$where[] = "(o.ebay_id LIKE '%" . tep_db_input($eID) . "%'
                OR o.ebay_nick LIKE '%" . tep_db_input($eID) . "%')";
}

// --- SELECT principal ---
$orders_query_raw = "
    SELECT
        o.paypal_transaction_id, o.ebay_nick, o.ebay_id, o.orders_id, o.amazon_id,
        o.customers_name, o.delivery_state, o.customers_id, o.payment_method,
        o.date_purchased, o.last_modified, o.currency, o.currency_value,
        s.orders_status_name, s.color as status_color, ot.text AS order_total, cg.customers_group_name,
        ot_ship.title AS shipping_title, ot_ship.value AS shipping_value
    FROM " . TABLE_ORDERS . " o
    LEFT JOIN " . TABLE_ORDERS_TOTAL . " ot
           ON (o.orders_id = ot.orders_id AND ot.class = 'ot_total')
    LEFT JOIN " . TABLE_ORDERS_TOTAL . " ot_ship
           ON (o.orders_id = ot_ship.orders_id AND ot_ship.class = 'ot_shipping')
    LEFT JOIN " . TABLE_CUSTOMERS . " c
           ON c.customers_id = o.customers_id
    LEFT JOIN " . TABLE_CUSTOMERS_GROUPS . " cg
           USING(customers_group_id)
    JOIN " . TABLE_ORDERS_STATUS . " s
           ON (o.orders_status = s.orders_status_id
           AND s.language_id = '" . (int)$languages_id . "')
    " . ($where ? "WHERE " . implode(" AND ", $where) : "") . "
    ORDER BY o.orders_id DESC
";

// --- COUNT optimizado ---
$sSqlCount = "
    SELECT COUNT(*) AS total
    FROM " . TABLE_ORDERS . " o
    JOIN " . TABLE_ORDERS_STATUS . " s
         ON (o.orders_status = s.orders_status_id
         AND s.language_id = '" . (int)$languages_id . "')
    " . ($where ? "WHERE " . implode(" AND ", $where) : "")
;

// --- Paginar ---
$orders_split = new splitPageResults(
	$_GET['page'],
	MAX_DISPLAY_SEARCH_RESULTS,
	$orders_query_raw,
	$orders_query_numrows,
	$sSqlCount
);
$orders_query = tep_db_query($orders_query_raw);
?>
<div>
	<div class="toolbarHead">
		<div class="hdr-tlbr">
			<h1 class="pageHeading"><?php echo TEXT_ORDER_NUMBER; ?> <?php echo $oID ?? ''; ?></h1>
			<div class="btn-right">
				<a href="create_order.php" title="Crear Pedido"><img class="dx-hovr" src="images/icons/icon_new_order.png"></a>
				<a href="export_orders.php" title="Exportar pedidos"><img class="dx-hovr" src="images/icons/icon_excel_order.png"></a>
			</div>
			<div class="forms">
				<ul>
					<li>
						<?php echo tep_draw_form('orders', FILENAME_ORDERS, '', 'get'); ?>
						<?php echo 'Número de ' . HEADING_TITLE_SEARCH . ' ' . tep_draw_input_field('oID', '', 'size="12"') . tep_draw_hidden_field('action', 'edit'); ?></td>
						<?php echo tep_hide_session_id(); ?>
						<input style="visibility:hidden;height: 0px;width: 0px;float: right;" type="submit"/>
						</form>
					</li>
					<li>
						<?php echo tep_draw_form('orders', FILENAME_ORDERS, '', 'get'); ?>
						<?php echo 'ID Amazon: ' . tep_draw_input_field('aID', '', 'size="12"') . tep_draw_hidden_field('action', 'edit'); ?></td>
						<?php echo tep_hide_session_id(); ?>
						<input style="visibility:hidden;height: 0px;width: 0px;float: right;" type="submit"/>
						</form>
					</li>
					<li>
						<?php echo tep_draw_form('orders', FILENAME_ORDERS, '', 'get'); ?>
						<?php echo 'ID eBay ' . tep_draw_input_field('ebay_id', '', 'size="12" placeholder="ID o seudónimo"') ?></td>
						<?php echo tep_hide_session_id(); ?>
						<input style="visibility:hidden;height: 0px;width: 0px;float: right;" type="submit"/>
						</form>
					</li>
					<li>
						<?php echo tep_draw_form('orders', FILENAME_ORDERS, '', 'get'); ?>
						<?php echo 'ID Cliente ' . tep_draw_input_field('cID', '', 'size="12" placeholder=""') ?></td>
						<?php echo tep_hide_session_id(); ?>
						<input style="visibility:hidden;height: 0px;width: 0px;float: right;" type="submit"/>
						</form>
					</li>
					<li>
						<?php echo tep_draw_form('status', FILENAME_ORDERS, '', 'get', 'style="position:relative; top: 2px;"'); ?>
						<?php echo HEADING_TITLE_STATUS . ' ' . tep_draw_pull_down_menu('status', array_merge(array(array('id' => '', 'text' => TEXT_ALL_ORDERS)), $orders_statuses), (isset($_GET['status']) ? $_GET['status'] : ''), 'onChange="this.form.submit();"'); ?>&nbsp;&nbsp;<?php $aShipFilter = array(array('id'=>'','text'=>'Forma de envío: todas'), array('id'=>'seururgente','text'=>'SEUR urgentes (10h + 13:30)'), array('id'=>'seurdiez_seurdiez','text'=>'SEUR antes de las 10h'), array('id'=>'seurnacional_seurnacional','text'=>'SEUR antes 13:30h'), array('id'=>'seurpunto_seurpunto','text'=>'SEUR Punto de Recogida'), array('id'=>'seureuropack_seureuropack','text'=>'SEUR EuroPACK'), array('id'=>'tipsa_tipsa','text'=>'Mensajería (Tipsa)'), array('id'=>'correos_Normal','text'=>'Correos Domicilio'), array('id'=>'correosoficina_correosoficina','text'=>'Correos - Recoger en oficina'), array('id'=>'correoscert_Normal','text'=>'Correos Certificado'), array('id'=>'retira_retira','text'=>'Recogida en tienda'), array('id'=>'freeamount_freeamount','text'=>'Envío Gratis')); echo 'Forma de envío ' . tep_draw_pull_down_menu('ship', $aShipFilter, (isset($_GET['ship']) ? $_GET['ship'] : ''), 'onChange="this.form.submit();"'); ?></td>
						<?php echo tep_hide_session_id(); ?>
						<input style="visibility:hidden;height: 0px;width: 0px;float: right;" type="submit"/>
						</form>
					</li>
			</div>
		</div>
	</div>
</div>

<tr>
  <td colspan="12">
	  <table class="table-page" border="0" width="100%" cellspacing="0" cellpadding="2" style="padding-bottom: 0px;">
		  <tr>
			  <td class="smallText" align="left">
				  <div id="arrow_select_all" style="transform: rotatex(179deg); margin-top:17px; transform-origin: center center;"></div>
				  <select id="ords-msva" data-page="<?php echo $_GET['page']; ?>">
					  <option value="">Seleccione opción masiva</option>
					  <option value="1">Cambiar estados / notificaciones</option>
					  <option value="3">Eliminar pedidos</option>
					  <option value="4" data-href="albaran.php?null=null" data-text="¿Realmente deseas imprimir los albaránes?">Imprimir albaránes</option>
					  <option value="5" data-href="packingslip.php?null=null" data-text="¿Realmente deseas imprimir las etiquetas?">Imprimir etiquetas</option>
				  </select>
			  </td>

			  <td class="smallText" align="right">
				  <div style="float: right;">
					  <?php echo $orders_split->display_links($orders_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, MAX_DISPLAY_PAGE_LINKS, $_GET['page'], tep_get_all_get_params(array('page', 'oID', 'action'))); ?>
				  </div>

				  <div style="float: right; margin-right: 20px; <?php echo ($orders_split->num_pages > 1 ? 'position:relative; top: 7px;': ''); ?>">
					  <?php echo $orders_split->display_count($orders_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, $_GET['page'], TEXT_DISPLAY_NUMBER_OF_ORDERS); ?>
				  </div>
			  </td>
		  </tr>
	  </table>
  </td>
</tr>
<tr>
	<td><table border="0" width="100%" cellspacing="0" cellpadding="0">
	  <tr>
		<td valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
		  <tr class="dataTableHeadingRow">
			<td align="center" width="30"><input type="checkbox" id="orders_all"></td>
			<td class="dataTableHeadingContent">Núm.</td>
			<td class="dataTableHeadingContent">ID Amazon / eBay</td>
			<td class="dataTableHeadingContent"><?php echo TABLE_HEADING_CUSTOMERS; ?></td>
			<td class="dataTableHeadingContent">ID</td>
			<td class="dataTableHeadingContent"><?php echo TABLE_HEADING_CUSTOMERS_GROUPS; ?></td>
			<td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_ORDER_TOTAL; ?></td>
			<td class="dataTableHeadingContent" align="center"><?php echo TABLE_HEADING_DATE_PURCHASED; ?></td>
			<td class="dataTableHeadingContent" align="right">F. Pago</td>
			<td class="dataTableHeadingContent" align="right">F. Envío</td>
			<td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_STATUS; ?></td>
			<td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_ACTION; ?>&nbsp;</td>
		  </tr>
<?php
    while ($orders = tep_db_fetch_array($orders_query)) {
		if ((!isset($_GET['oID']) || (isset($_GET['oID']) && ($_GET['oID'] == $orders['orders_id']))) && !isset($oInfo)) {
			$oInfo = new objectInfo($orders);
		}

		if (isset($oInfo) && is_object($oInfo) && ($orders['orders_id'] == $oInfo->orders_id)) {
			echo '<tr id="defaultSelected" class="dataTableRowSelected" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="if($(event.target).closest(\'.btn-group\').length)return;document.location.href=\'' . tep_href_link(FILENAME_ORDERS, tep_get_all_get_params(array('oID', 'action')) . 'oID=' . $oInfo->orders_id . '&action=edit') . '\'">' . "\n";
		} else {
			echo '<tr class="dataTableRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="if($(event.target).closest(\'.btn-group\').length)return;document.location.href=\'' . tep_href_link(FILENAME_ORDERS, tep_get_all_get_params(array('oID')) . 'oID=' . $orders['orders_id']) . '\'">' . "\n";
		}
?>
				<td align="center" class="checkbox_orders"><input style="position: relative; top: 7px; margin: 0px;" type="checkbox" value="<?php echo $orders['orders_id']; ?>" name="orders"></td>
                <td class="dataTableContent">#<?php echo $orders['orders_id']; ?></td>
				<td class="dataTableContent"><?php echo ($orders['ebay_id'] ? tep_image(DIR_WS_IMAGES . 'ebay.png').'<br /><small style="font-size: 8px; opacity: 0.6;">'.$orders['ebay_id'].'</small>' : ''); ?><?php echo ($orders['amazon_id'] ? tep_image(DIR_WS_IMAGES . 'amazon.png').'<br /><small style="font-size: 8px; opacity: 0.6;">'.$orders['amazon_id'].'</small>' : ''); ?></td>
                <td class="dataTableContent"><?php echo '<a href="' . tep_href_link(FILENAME_ORDERS, tep_get_all_get_params(array('oID', 'action')) . 'oID=' . $orders['orders_id'] . '&action=edit') . '">' . tep_image(DIR_WS_ICONS . 'preview.png', ICON_PREVIEW) . '</a>&nbsp;' . $orders['customers_name']; ?></td><!-- next td added for SPPC -->
                <td class="dataTableContent"><?php echo $orders['customers_id']; ?></td>
				<td class="dataTableContent"><?php echo $orders['customers_group_name']; ?></td>
                <td class="dataTableContent" align="right"><?php echo strip_tags($orders['order_total']); ?></td>
                <td class="dataTableContent" align="center"><?php echo tep_datetime_short($orders['date_purchased']); ?></td>
				<td class="dataTableContent" align="right">
					<?php echo $orders['payment_method']; ?>
					<?php if(strpos(strtolower($orders['payment_method']), 'paypal') !== false): ?>
						<br />ID Transacción:
						<?php if ($orders['paypal_transaction_id'] == ''): ?>
							<strong style="color: orange;">Cuidado! Pedido sin ID</strong>
						<?php else: ?>
							<strong><?php echo $orders['paypal_transaction_id']; ?></strong>
						<?php endif; ?>
					<?php endif; ?>
				</td>
					<td class="dataTableContent" align="right">
						<?php
						if (empty($orders['shipping_title'])) {
							echo '<em>Sin forma de envío</em>';
						} else {
							echo '<span>' . substr_replace($orders['shipping_title'], "", -1) . '</span>';
						}
						?>
					</td>

                <td class="dataTableContent" align="right"><?php
					$statusColor = !empty($orders['status_color']) ? $orders['status_color'] : '#282A3C';
					$textColor = isDarkColor($statusColor) ? '#ffffff' : '#333333';
					echo '<span class="status-pill" style="background-color:' . $statusColor . ';color:' . $textColor . ';padding:3px 10px;border-radius:12px;font-size:11px;white-space:nowrap;">' . $orders['orders_status_name'] . '</span>';
				?></td>
                <td class="dataTableContent" align="center" style="text-align:center;vertical-align:middle;">
					<div class="btn-group" style="display:inline-block;vertical-align:middle;">
						<a class="buttonS bDefault" style="white-space:nowrap;" data-toggle="dropdown" href="#">Acciones <span class="caret"></span></a>
						<ul class="dropdown-menu" style="margin-left:-72px;">
							<li><a href="<?php echo tep_href_link(FILENAME_ORDERS, tep_get_all_get_params(array('oID', 'action')) . 'oID=' . $orders['orders_id'] . '&action=edit'); ?>"><i class="fas fa-eye"></i> Detalles</a></li>
							<li><a href="<?php echo tep_href_link('edit_orders.php', tep_get_all_get_params(array('oID', 'action')) . 'oID=' . $orders['orders_id'] . '&action=edit'); ?>"><i class="fas fa-edit"></i> Editar</a></li>
							<li><a href="#" onclick="showDeleteOptions(<?php echo $orders['orders_id']; ?>); return false;"><i class="fas fa-trash-alt"></i> Eliminar</a></li>
							<li><a href="<?php echo tep_href_link(FILENAME_ORDERS_PACKINGSLIP, 'oID=' . $orders['orders_id']); ?>" target="_blank"><i class="fas fa-file-alt"></i> Ver Etiqueta</a></li>
							<li><a href="<?php echo tep_href_link('albaran.php', 'oID=' . $orders['orders_id']); ?>" target="_blank"><i class="fas fa-truck"></i> Ver Albarán</a></li>
						</ul>
					</div>
				</td>
              </tr>
<?php
    }
?>
              <tr>
                <td colspan="12">
					<table class="table-page" border="0" width="100%" cellspacing="0" cellpadding="2">
						<tr>
							<td class="smallText" align="left">
								<div id="arrow_select_all"></div>
								<select id="ords-msva" data-page="<?php echo $_GET['page']; ?>">
									<option value="">Seleccione opción masiva</option>
									<option value="1">Cambiar estados / notificaciones</option>
									<option value="3">Eliminar pedidos</option>
									<option value="4" data-href="albaran.php?null=null" data-text="¿Realmente deseas imprimir los albaránes?">Imprimir albaránes</option>
									<option value="5" data-href="packingslip.php?null=null" data-text="¿Realmente deseas imprimir las etiquetas?">Imprimir etiquetas</option>
								</select>
							</td>

							<td class="smallText" align="right">
								<div style="float: right;">
									<?php echo $orders_split->display_links($orders_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, MAX_DISPLAY_PAGE_LINKS, $_GET['page'], tep_get_all_get_params(array('page', 'oID', 'action'))); ?>
								</div>

								<div style="float: right; margin-right: 20px; <?php echo ($orders_split->num_pages > 1 ? 'position:relative; top: 7px;': ''); ?>">
									<?php echo $orders_split->display_count($orders_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, $_GET['page'], TEXT_DISPLAY_NUMBER_OF_ORDERS); ?>
								</div>
							</td>
						</tr>
					</table>
				</td>
              </tr>
            </table></td>
<?php
  // Las acciones ahora están en el dropdown de cada fila
?>
          </tr>
        </table></td>
      </tr>
<?php
  }
?>
    </table></td>
  </tr>
</table>

<div id="lgbox-bg" style="display: none;"></div>
<div id="lgbox-load" style="display: none;"></div>

<div id="lgbox" class="" style="display: none;">
	<div id="lgbox-clse">✖</div>
	<div id="lgbox-titl"></div>
	<div id="lgbox-cntd"></div>
</div>

<?php require(THEME . 'html/footer.php'); ?>

<script type="text/javascript">
	// Cuando seleccionamos una opcion del combobox masivo
	$("#ords-msva").change(function()
	{
		var aCheckboxs = $("tbody .checkbox_orders input:checked");
		var nOpcion = $(this).val();

		// Volvemos a posicionarnos en el primer valor
		$(this).val(0);
		// $.uniform.update();

		// Comprobamos si tenemos
		if( aCheckboxs.length == 0 )
		{
			alert( "Para realizar alguna de estas operaciones necesitas seleccionar algún pedido" );
			return false;
		}

		// Obtenemos los id de los pedidos
		var sIds = aCheckboxs.map(function(){return this.value;}).get().join(',');

		// Segun el valor hacemos una cosa u otra
		switch( nOpcion )
		{
			case "1": // Cambiar estados
				showWindow( "orders.php?action=show_estado_notificacion&ids=" + sIds + "&page=" + $(this).data("page"), "Cambio de estado/notificación", "" );
				// Inicializamos TinyMCE en el modal
				setTimeout(function(){
					  if (typeof initializeTinyMCESimple === 'function') initializeTinyMCESimple();
				  }, 1000);
			break;

			case "3": // Eliminar pedidos
				showWindow( "orders.php?action=show_delete_orders_masivo&ids=" + sIds + "&page=" + $(this).data("page"), "¿Deseas eliminar los pedidos seleccionados?", "lgbox-mini" );
				return false;

				if( confirm( "¿Deseas eliminar los pedidos seleccionados?" ) )
				{
					var bRestock = false;

					if( confirm( "¿Deseas añadir los productos al almacen?" ) )
						bRestock = true;

					// Mostramos loadding
					$("#lgbox-bg").css("display", "block");
					$("#lgbox-load").css("display", "block");

					// Cargamos la web para ser eliminados
					window.location.href="orders.php?action=delete_orders_masivo&ids=" + sIds + "&page=" + $(this).data("page") + (bRestock ? "&stock=true" : "");
				}
			break;

			case "4": // Imprimir
			case "5":
			case "6":
				if( !confirm( $($(this).find("option")[nOpcion]).data("text") ) )
					return false;

				// Mostramos loadding
				$("#lgbox-bg").css("display", "block");
				$("#lgbox-load").css("display", "block");

				$.ajax(
				{
					type: "GET",
					url: $($(this).find("option")[nOpcion]).data("href") + "&oID=" + sIds,
					success: function(data)
					{
						// Mandamos a imprimir
						var newWin = window.open();
						newWin.document.write(data);
						newWin.document.close();
						newWin.focus();
						newWin.print();
						newWin.close();

						// Quitamos checkbox
						aCheckboxs.click();
						$("#orders_all").attr("checked", false);

						// Ocultamos loadding
						$("#lgbox-bg").css("display", "none");
						$("#lgbox-load").css("display", "none");
					}
				});
			break;
		};
	});

	// Inicio, lightbox
	var showWindow = function(sEnlace, sTitulo, sClass)
	{
		// Cambiamos el titulo
		$("#lgbox-titl").html(sTitulo);

		// Añadimos la clase
		if( sClass == "" )
			$("#lgbox").attr("class", "");
		else
			$("#lgbox").addClass(sClass);

		// Mostramos fondo
		$("#lgbox-bg").fadeIn(400, function()
		{
			$.ajax(
			{
				url: sEnlace
			}).done(function(sHtml)
			{
				// Cargamos el html resultante
				$("#lgbox-cntd").html( sHtml );

				// Cargamos el uniform
				$("#lgbox-cntd select, #lgbox-cntd .check, #lgbox-cntd .check :checkbox, #lgbox-cntd input:radio, #lgbox-cntd input:file").uniform();

				// El fomulario sera por ajax
				$("#form_orders_ajax").submit(function(e)
				{
					// Variables
					var dmForm = $(this);

					// Ocultamos
					$("#lgbox").fadeOut();

					return true;
				});

				$("#lgbox").fadeIn();


			});
		});

		// Mostramos loading
		$("#lgbox-load").fadeIn();
	};

	var closeWindow = function()
	{
		$("#lgbox-load").css("display", "none");
		$("#lgbox").fadeOut(400, function()
		{
			$("#lgbox-cntd").html("");
		});
		$("#lgbox-bg").fadeOut();
	};

	// Evento para cerrar el lighbox
	$("#lgbox-clse").click( closeWindow );
	$("#lgbox-bg").click( closeWindow );
	// Fin, lightbox

	// No realizar evento click en la fila cuando se pulsa un td con checkbox
	$(".checkbox_orders").click(function(e)
	{
		e.stopPropagation();
	});

	// Cuando pulsamos el checkbox all
	$("#orders_all").click(function(e)
	{
		// Si esta pulsado quitamos checkbox
		if( $(this).is(':checked') )
			$("tbody .checkbox_orders input:not(:checked)").click();
		else
			$("tbody .checkbox_orders input:checked").click();
	});

	// Cuando pulsamos un checkbox
	$("tbody .checkbox_orders input").click(function(e)
	{
		// Obtenemos la fila
		var dmTr = $(this).parent().parent();

		// Seleccionamos la fila
		if( $(this).is(':checked') )
		{
			dmTr.css("background", "#cceaff");
			dmTr.effect('highlight', {color: "#b1defe"}, 250, function(){dmTr.css("background", "#d8efff");});
			dmTr.attr( "onmouseout", "" );
			dmTr.attr( "onmouseover", "" );
		}
		else
		{
			dmTr.css("background", "");
			dmTr.attr( "onmouseout", "rowOutEffect(this)" );
			dmTr.attr( "onmouseover", "rowOverEffect(this)" );
		}
	});

	// Eliminar pedido con opciones de restock
	function showDeleteOptions(orderId) {
		document.getElementById('lgbox-titl').innerText = 'Confirmación de eliminación';
		document.getElementById('lgbox-cntd').innerHTML = `
			<p style="font-size: 14px;">¿Estás seguro de que deseas eliminar el pedido ID ${orderId}?</p>
			<div style="display: flex; justify-content: space-around; margin-top: 30px">
				<button class="xbutton verde" id="deleteOrderBtn">ELIMINAR</button>
				<button class="xbutton azul" id="deleteOrderRestockBtn">ELIMINAR Y RESTAURAR STOCK</button>
				<button class="xbutton rojo" onclick="closeWindow()">Cancelar</button>
			</div>
		`;

		$("#lgbox-bg").fadeIn(400, function() {
			$("#lgbox").fadeIn();
		});

		document.getElementById('deleteOrderBtn').onclick = function() {
			deleteOrder(orderId, false);
		};
		document.getElementById('deleteOrderRestockBtn').onclick = function() {
			deleteOrder(orderId, true);
		};
	}

	function deleteOrder(orderId, restock) {
		var form = document.createElement('form');
		form.method = 'post';
		form.action = '<?php echo tep_href_link(FILENAME_ORDERS, 'action=deleteconfirm'); ?>&oID=' + orderId;

		var input = document.createElement('input');
		input.type = 'hidden';
		input.name = 'restock';
		input.value = restock ? '1' : '0';
		form.appendChild(input);

		document.body.appendChild(form);
		form.submit();
		closeWindow();
	}
	// Conectar como cliente
	// - Abrimos window.open() en el gesto del click (popup-blocker safe)
	// - Creamos form en document.body y lo apuntamos a esa ventana por name
	function connectAsCustomer(email, pass) {
		var winName = 'connectAsCustomer_' + Date.now();
		var newWin = window.open('', winName);
		if (!newWin) {
			alert('El navegador ha bloqueado la ventana emergente. Permite popups para este sitio.');
			return;
		}

		var loginUrl = location.protocol + '//' + location.host + '/login.php?action=process';

		var form = document.createElement('form');
		form.setAttribute('method', 'post');
		form.setAttribute('action', loginUrl);
		form.setAttribute('target', winName);

		var inputEmail = document.createElement('input');
		inputEmail.type = 'hidden';
		inputEmail.name = 'email_address';
		inputEmail.value = email;
		form.appendChild(inputEmail);

		var inputPass = document.createElement('input');
		inputPass.type = 'hidden';
		inputPass.name = 'password';
		inputPass.value = pass;
		form.appendChild(inputPass);

		var inputModo = document.createElement('input');
		inputModo.type = 'hidden';
		inputModo.name = 'modo';
		inputModo.value = 'login';
		form.appendChild(inputModo);

		// Flag para que login.php ignore el snapshot de navegación y fuerce
		// logoff previo si había otra sesión abierta
		var inputMast = document.createElement('input');
		inputMast.type = 'hidden';
		inputMast.name = 'mast_connect';
		inputMast.value = '1';
		form.appendChild(inputMast);
		document.body.appendChild(form);
		form.submit();
		setTimeout(function() {
			if (form.parentNode) form.parentNode.removeChild(form);
		}, 2000);
	}
</script>

<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
