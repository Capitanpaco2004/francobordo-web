<?php
/**
 * redsys_xpay.php
 *
 * Apple Pay / Google Pay vía Redsys (X-Pay) — integración por redirección.
 *
 * Clon funcional del modulo `redsys` que FUERZA Ds_Merchant_PayMethods="xpay".
 * Con ese valor, la pagina hosted de Redsys muestra directamente la pantalla del
 * wallet (sin formulario de tarjeta) y autodetecta el dispositivo: Apple Pay en
 * Safari/iOS, Google Pay en Chrome/Android. Redsys gestiona token + 3DS en su
 * servidor — no necesitamos EMV3DS ni decodificar el token (eso es solo para la
 * integracion directa/inSite). Doc: pagosonline.redsys.es .../otros-metodos-de-pago-apple-pay/
 *
 * COMPARTE credenciales con el modulo `redsys` (FUC, clave SHA-256, terminal,
 * entorno, nombre, moneda) leyendo sus constantes MODULE_PAYMENT_REDSYS_*. Solo
 * instala config propia: STATUS, SORT_ORDER, ORDER_STATUS_ID. Asi hay una unica
 * fuente de verdad para la clave del comercio. Si `redsys` no estuviera instalado,
 * este modulo se autodesactiva defensivamente.
 *
 * Terminal 8 de Comercia Global Payments tiene Apple/Google Pay activados (jun 2026).
 */

if(!function_exists("escribirLog"))
	require_once('apiRedsys/redsysLibrary.php');
if(!class_exists("RedsysAPI"))
	require_once('apiRedsys/apiRedsysFinal.php');

class redsys_xpay {
    public $code;
    public $title;
    public $description;
    public $enabled;
    public $sort_order;
    public $mantener_pedido_ante_error_pago;
    public $logActivo;
    public $order_status;
    public $form_action_url;
    public $_check;

    function __construct() {
      global $order;

      $this->code        = 'redsys_xpay';
      $this->title       = defined('MODULE_PAYMENT_REDSYS_XPAY_TEXT_TITLE') ? MODULE_PAYMENT_REDSYS_XPAY_TEXT_TITLE : 'Apple Pay / Google Pay';
      $this->description  = defined('MODULE_PAYMENT_REDSYS_XPAY_TEXT_DESCRIPTION') ? MODULE_PAYMENT_REDSYS_XPAY_TEXT_DESCRIPTION : '';
      $this->enabled     = ( defined('MODULE_PAYMENT_REDSYS_XPAY_STATUS') && MODULE_PAYMENT_REDSYS_XPAY_STATUS == 'True' );

      // Comparte credenciales con `redsys`: sin la clave SHA-256 del comercio no podemos firmar.
      if ( ! defined('MODULE_PAYMENT_REDSYS_ID_CLAVE256') || MODULE_PAYMENT_REDSYS_ID_CLAVE256 === '' ) {
          $this->enabled = false;
      }

      $this->mantener_pedido_ante_error_pago = ( defined('MODULE_PAYMENT_REDSYS_ERROR_PAGO') && MODULE_PAYMENT_REDSYS_ERROR_PAGO == 'si' );
      $this->logActivo = 'si';

      if ( defined('MODULE_PAYMENT_REDSYS_XPAY_ORDER_STATUS_ID') && (int)MODULE_PAYMENT_REDSYS_XPAY_ORDER_STATUS_ID > 0 )
        $this->order_status = MODULE_PAYMENT_REDSYS_XPAY_ORDER_STATUS_ID;

      if (defined('MODULE_PAYMENT_REDSYS_XPAY_SORT_ORDER'))
        $this->sort_order = MODULE_PAYMENT_REDSYS_XPAY_SORT_ORDER;

      if (is_object($order)) $this->update_status();

      // Entorno: lo lee de la config del modulo redsys (compartido)
      $sEntorno = defined('MODULE_PAYMENT_REDSYS_ENTORNO') ? MODULE_PAYMENT_REDSYS_ENTORNO : 'Entorno Real';
      if($sEntorno=="Entorno Real")
            $this->form_action_url = "https://sis.redsys.es/sis/realizarPago/utf-8";
      else if($sEntorno=="Entorno Pruebas")
            $this->form_action_url = "https://sis-t.redsys.es:25443/sis/realizarPago/utf-8";
      else
            $this->form_action_url = "http://sis-d.redsys.es/sis/realizarPago/utf-8";
    }

    function update_status() { return false; }
    function javascript_validation() { return false; }

    function selection() {
    	return array('id' => $this->code, 'module' => $this->title);
    }

    function pre_confirmation_check() {
    	global $cartID, $cart;
		if (empty($cart->cartID))
			$cartID = $cart->cartID = $cart->generate_cart_id();
		if (!tep_session_is_registered('cartID'))
			tep_session_register('cartID');
    }

    function confirmation() { return false; }

    function process_button() {
    	global $order, $currency, $customer_id, $language;
		$numpedido="1".time();

		$total=$order->info['total'];
		$cantidad = round($total*$order->info['currency_value'],2);
		$cantidad = number_format($cantidad, 2, '.', '');
		$cantidad = preg_replace('/\./', '', $cantidad);

		$terminal = defined('MODULE_PAYMENT_REDSYS_TERMINAL') ? MODULE_PAYMENT_REDSYS_TERMINAL : '1';
		$trans = "0";

		$idioma = defined('MODULE_PAYMENT_REDSYS_IDIOMA') ? MODULE_PAYMENT_REDSYS_IDIOMA : 'No';
		if( $idioma == "Si") {
			$idioma_web = substr((string)($_SERVER["HTTP_ACCEPT_LANGUAGE"] ?? ''),0,2);
			switch ($idioma_web) {
				case 'es': $idiomaFinal='001'; break;
				case 'en': $idiomaFinal='002'; break;
				case 'ca': $idiomaFinal='003'; break;
				case 'fr': $idiomaFinal='004'; break;
				case 'de': $idiomaFinal='005'; break;
				case 'nl': $idiomaFinal='006'; break;
				case 'it': $idiomaFinal='007'; break;
				case 'sv': $idiomaFinal='008'; break;
				case 'pt': $idiomaFinal='009'; break;
				case 'pl': $idiomaFinal='011'; break;
				case 'gl': $idiomaFinal='012'; break;
				case 'eu': $idiomaFinal='013'; break;
				default:   $idiomaFinal='002';
			}
			$idioma_tpv=$idiomaFinal;
		}else{
			$idioma_tpv="0";
		}

		$urltienda =  tep_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL');
		$idSesion  = tep_session_id();
		$urltienda = $urltienda."?osCsid=".$idSesion;
		$clave256  = MODULE_PAYMENT_REDSYS_ID_CLAVE256;

		$contador = mb_substr_count($urltienda, '?osCsid=');
		if($contador>1)
			$urltienda = tep_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL');

		$codigo = MODULE_PAYMENT_REDSYS_ID_COM;

		$ds_merchant_urlok =  tep_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL');
		$ds_merchant_urlko =  tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode("ERROR: El pago con Apple Pay / Google Pay no se ha podido completar. Por favor intentelo de nuevo o seleccione otro metodo de pago."), 'SSL');

		$ds_merchant_data=sha1($urltienda);

		// X-PAY: fuerza la pantalla de wallet (Apple Pay / Google Pay) en la pagina hosted
		$tipopago = "xpay";

		$moneda = ( defined('MODULE_PAYMENT_REDSYS_CURRENCY') && MODULE_PAYMENT_REDSYS_CURRENCY == "Euro" ) ? "978" : "840";

		$ds_merchant_name = defined('MODULE_PAYMENT_REDSYS_NOMBRE') ? MODULE_PAYMENT_REDSYS_NOMBRE : 'Francobordo.com';
		$Descripcion = 'Pedido Cliente: ' . $customer_id .' - ' .$order->customer['firstname'] . ' ' .$order->customer['lastname'] . ' (' .$order->customer['email_address'] . ')';

		$miObj = new RedsysAPI;
		$miObj->setParameter("DS_MERCHANT_AMOUNT",$cantidad);
		$miObj->setParameter("DS_MERCHANT_ORDER",strval($numpedido));
		$miObj->setParameter("DS_MERCHANT_MERCHANTCODE",$codigo);
		$miObj->setParameter("DS_MERCHANT_CURRENCY",$moneda);
		$miObj->setParameter("DS_MERCHANT_TRANSACTIONTYPE",$trans);
		$miObj->setParameter("DS_MERCHANT_TERMINAL",$terminal);
		$miObj->setParameter("DS_MERCHANT_MERCHANTURL",$urltienda);
		$miObj->setParameter("DS_MERCHANT_URLOK",$ds_merchant_urlok);
		$miObj->setParameter("DS_MERCHANT_URLKO",$ds_merchant_urlko);
		$miObj->setParameter("Ds_Merchant_ConsumerLanguage",$idioma_tpv);
		$miObj->setParameter("Ds_Merchant_ProductDescription", $Descripcion);
		$miObj->setParameter("Ds_Merchant_Titular",$ds_merchant_name);
		$miObj->setParameter("Ds_Merchant_MerchantData",$ds_merchant_data);
		$miObj->setParameter("Ds_Merchant_MerchantName",$ds_merchant_name);
		$miObj->setParameter("Ds_Merchant_PayMethods",$tipopago);
		$miObj->setParameter("Ds_Merchant_Module","oscDenox");
		$miObj->setParameter("Ds_Merchant_Identifier","REQUIRED");

		$version = "HMAC_SHA256_V1";
		$paramsBase64  = $miObj->createMerchantParameters();
		$signatureMac  = $miObj->createMerchantSignature($clave256);

      	$process_button_string =
		tep_draw_hidden_field('Ds_SignatureVersion', $version) .
		tep_draw_hidden_field('Ds_MerchantParameters', $paramsBase64) .
		tep_draw_hidden_field('Ds_Signature', $signatureMac);

		return $process_button_string;
    }

	/******  #FB-REDSYS-REPLAY (2026-08-30) - notificacion servidor-servidor de Redsys  ******/
	// La notificacion es la UNICA peticion que llega con Ds_MerchantParameters + Ds_Signature.
	// El navegador del cliente NO pasa por checkout/process en el flujo Redsys: vuelve por
	// Ds_Merchant_UrlOK a checkout/success. Los POST de navegador de otros medios de pago
	// llegan sin campos Ds_* y no entran por aqui, asi que siguen recibiendo su redireccion.
	public $fbNotifParams  = null;   // Ds_* decodificados (SIN validar; la firma se verifica aparte)
	public $fbNotifHttp    = null;   // array(codigo, cuerpo) que hay que devolverle a Redsys
	public $fbNotifObNivel = 0;      // nivel de buffers de salida antes de abrir el nuestro

	function fbEsNotificacion() {
		return isset($_POST['Ds_MerchantParameters']) && isset($_POST['Ds_Signature']);
	}

	// Lee un parametro de la notificacion (base64url + JSON). Esto NO valida nada y NO
	// sustituye a ninguna comprobacion: la firma HMAC-SHA256 se sigue verificando igual
	// que siempre unas lineas mas abajo, y sigue abortando si no cuadra.
	function fbNotifParam($sClave) {
		if ($this->fbNotifParams === null) {
			$this->fbNotifParams = array();
			if ($this->fbEsNotificacion()) {
				$aTmp = json_decode(base64_decode(strtr((string)$_POST['Ds_MerchantParameters'], '-_', '+/')), true);
				if (is_array($aTmp)) $this->fbNotifParams = $aTmp;
			}
		}
		if (isset($this->fbNotifParams[$sClave])) return (string)$this->fbNotifParams[$sClave];
		$sAlt = strtoupper($sClave);
		if (isset($this->fbNotifParams[$sAlt])) return (string)$this->fbNotifParams[$sAlt];
		return '';
	}

	// Ds_Order saneado: nombre del lock y clave de idempotencia.
	function fbNotifReferencia() {
		return substr(preg_replace('/[^0-9A-Za-z]/', '', $this->fbNotifParam('Ds_Order')), 0, 20);
	}

	// Importe realmente cobrado por el banco, en euros (Ds_Amount viaja en centimos).
	function fbNotifImporte() {
		$sImporte = $this->fbNotifParam('Ds_Amount');
		return preg_match('/^[0-9]+$/', $sImporte) ? ((int)$sImporte / 100) : null;
	}

	// Respuesta HTTP para Redsys. El router de checkout termina SIEMPRE en tep_redirect(),
	// que emite 301, y Redsys reintenta toda notificacion que no responda 200: en el access
	// log el 100% de las notificaciones correctas recibe 301. Como el router no es
	// alcanzable desde el modulo, sustituimos la cabecera en el shutdown, que se ejecuta
	// despues del exit() de tep_redirect y antes de que PHP vuelque los buffers de salida.
	function fbNotifRespuesta($nCodigo, $sCuerpo) {
		$this->fbNotifHttp = array((int)$nCodigo, (string)$sCuerpo);
	}

	function fbNotifShutdown() {
		if (!is_array($this->fbNotifHttp)) return;
		while (ob_get_level() > $this->fbNotifObNivel) { @ob_end_clean(); }
		if (!headers_sent()) {
			@header_remove('Location');
			@header('Content-Type: text/plain; charset=utf-8');
			@http_response_code($this->fbNotifHttp[0]);
		}
		echo $this->fbNotifHttp[1];
	}
	/******  fin #FB-REDSYS-REPLAY  ******/

    function before_process() {
        global $customer_id;

		// #FB-REDSYS-REPLAY: se serializa por Ds_Order ANTES de cualquier comprobacion y se
		// corta el reproceso de una referencia ya consumida. El 2026-03-29 la referencia
		// 11774802408 genero DOS pedidos (10356674 y 10356675, a 7 s) con un unico cargo de
		// 51,73 EUR. El lock se libera al cerrar la peticion: USE_PCONNECT='false' y
		// tep_db_connect() no usa el prefijo 'p:'. Mismo patron que #FB-PAYPAL-REPLAY en
		// includes/modules/payment/paypal_rest.php.
		if ($this->fbEsNotificacion()) {
			$this->fbNotifObNivel = ob_get_level();
			ob_start();
			register_shutdown_function(array($this, 'fbNotifShutdown'));
			// Por defecto NO confirmamos nada: si la peticion muere sin llegar a
			// after_process() el pedido no existe y Redsys tiene que reintentar.
			$this->fbNotifRespuesta(500, 'KO');

			$sFbRef = $this->fbNotifReferencia();
			if ($sFbRef === '') {
				@error_log('[FB-REDSYS-REPLAY] notificacion sin Ds_Order legible module=redsys_xpay customer=' . (int)$customer_id);
			}
		}

		$idLog = generateIdLog();
		$logActivo = 'si';
		$valido = FALSE;
		if (!empty( $_POST ) ) {
			$clave256=MODULE_PAYMENT_REDSYS_ID_CLAVE256;

			$version       = $_POST["Ds_SignatureVersion"];
			$datos         = $_POST["Ds_MerchantParameters"];
			$firma_remota  = $_POST["Ds_Signature"];

			$miObj = new RedsysAPI;
			$decodec = $miObj->decodeMerchantParameters($datos);
			$firma_local = $miObj->createMerchantSignatureNotif($clave256,$datos);

			$total     = $miObj->getParameter('Ds_Amount');
			$pedido    = $miObj->getParameter('Ds_Order');
			$codigo    = $miObj->getParameter('Ds_MerchantCode');
			$moneda    = $miObj->getParameter('Ds_Currency');
			$respuesta = $miObj->getParameter('Ds_Response');
			$identifier = $miObj->getParameter('Ds_Merchant_Identifier');
			$expire = $miObj->getParameter('Ds_ExpiryDate');
			$id_trans  = $miObj->getParameter('Ds_AuthorisationCode');

			$_SESSION['redsys'] = array( 'ds_identifier' => $identifier, 'ds_expire' => $expire );

			$codigoOrig=MODULE_PAYMENT_REDSYS_ID_COM;

			if(checkRespuesta($respuesta)
				&& checkMoneda($moneda)
				&& checkFuc($codigo)
				&& checkPedidoNum($pedido)
				&& checkImporte($total)
				&& $codigo == $codigoOrig
			){
				escribirLog($idLog." -- [xpay] El pedido con ID " . $pedido . " es valido.",$logActivo);
				$valido = TRUE;
			} else {
				escribirLog($idLog." -- [xpay] Parametros incorrectos.",$logActivo);
				$valido = FALSE;
			}

			if ($firma_local != $firma_remota || FALSE === $valido) {
				escribirLog($idLog." -- [xpay] La firma no es correcta.",$logActivo);
				// #FB-REDSYS-REPLAY: se mantiene el 200 de siempre (con la firma mal, reintentar no arregla nada).
				$this->fbNotifRespuesta(200, 'FALLO DE FIRMA');
				die ("FALLO DE FIRMA");
				exit;
			}

			// #FB-REDSYS-LOCK-TRAS-FIRMA (2026-08-30): el lock y la guarda de duplicado se toman
			// AQUI, DESPUES de verificar la firma. Antes se tomaban al principio de before_process(),
			// lo que daba a cualquiera SIN AUTENTICAR un recurso que bloquear: basta un POST con
			// Ds_MerchantParameters y Ds_Signature arbitrarios, y el Ds_Order es predecible ('1'.time()).
			// Mover el lock es seguro porque la verificacion de firma es calculo HMAC puro y no toca
			// la BD: el lock se sigue tomando ANTES del INSERT del movimiento y de la creacion del
			// pedido, que es lo unico que hay que serializar.
			if ($this->fbEsNotificacion() && $sFbRef !== '') {
				$rFbLock = tep_db_query("select get_lock('" . tep_db_input('fbrs_' . $sFbRef) . "', 15) as l");
				$aFbLock = tep_db_fetch_array($rFbLock);
				if (empty($aFbLock['l'])) {
					// Fallo CERRADO: sin lock no se crea pedido. 503 para que Redsys reintente;
					// el reintento encontrara la referencia consumida y respondera 200.
					@error_log('[FB-REDSYS-REPLAY] LOCK no obtenido module=redsys_xpay ref=' . $sFbRef . ' customer=' . (int)$customer_id);
					$this->fbNotifRespuesta(503, 'REINTENTAR');
					die('REINTENTAR');
				}

				$rFbDup = tep_db_query("select id, orders_id from redsys_payment_movements where module = 'redsys_xpay' and reference = '" . tep_db_input($sFbRef) . "' and admin_id = 0 and value > 0 order by id desc limit 1");
				if ($aFbDup = tep_db_fetch_array($rFbDup)) {
					// #FB-REDSYS-REPLAY: la referencia YA tiene movimiento -> se considera consumida y no
					// se crea ningun pedido nuevo. 200 para que Redsys deje de reintentar.
					//
					// OJO, esto se probo con la rama contraria (recrear el pedido si orders_id=0) y ERA
					// PELIGROSO: el movimiento se INSERTA en before_process pero solo se ENLAZA con el
					// pedido en after_process, con el INSERT del pedido y DOS tep_mail por medio. Un fatal
					// en esa ventana deja el movimiento con orders_id=0 AUNQUE EL PEDIDO YA EXISTA, asi que
					// recrear duplicaba el pedido de un unico cargo. Medido: de 40 movimientos con
					// orders_id=0 en 12 meses, 35 tienen pedido del mismo cliente en el MISMO SEGUNDO y
					// ninguno es un cobro sin pedido. Por eso aqui se falla CERRADO.
					// Si algun dia hiciera falta recrear, primero hay que enlazar el movimiento con el
					// pedido justo despues del INSERT del pedido, no en after_process.
					@error_log('[FB-REDSYS-REPLAY] referencia YA procesada module=redsys_xpay ref=' . $sFbRef . ' movimiento=' . (int)$aFbDup['id'] . ' pedido=' . (int)$aFbDup['orders_id'] . ' customer=' . (int)$customer_id);
					$this->fbNotifRespuesta(200, 'OK');
					die('OK');
				}
			}

			$iresponse=(int)$respuesta;
			if (($iresponse>=0) && ($iresponse<=100)) {
                $values = [
                    'reference' => $pedido,
                    'value' => floatval(intval($total) / 100),
                    'customer_id' => intval($customer_id),
                    'module' => 'redsys_xpay',
                    'date_created' => 'now()',
                ];
                tep_db_perform('redsys_payment_movements', $values);
			} else {
				if(!$this->mantener_pedido_ante_error_pago){
					$_SESSION['cart']->reset(true);
					escribirLog($idLog." -- [xpay] Error de respuesta. Vaciando carrito.",$logActivo);
				} else {
					escribirLog($idLog." -- [xpay] Error de respuesta. Manteniendo carrito.",$logActivo);
				}
				// #FB-REDSYS-REPLAY: pago DENEGADO por el banco. Se mantiene el 200 de siempre: no
				// hay pedido que registrar y devolver 5xx solo provocaria reintentos inutiles.
				$this->fbNotifRespuesta(200, 'FALLO EN LA RESPUESTA');
				die ("FALLO EN LA RESPUESTA");
				exit;
			}
		} else {
			escribirLog($idLog." -- [xpay] Error. Hacking attempt!",$logActivo);
      		die ("Hacking attempt!");
			exit;
      	}
    }

    function after_process() {
		global $order, $insert_id, $cart, $customer_id;

		// #FB-REDSYS-NOTIF: el pedido YA esta creado (el INSERT ocurre entre before_process
		// y after_process), asi que confirmamos la notificacion con 200 OK en lugar del 301
		// del router, que hace que Redsys la reintente.
		if ($this->fbEsNotificacion()) {
			$this->fbNotifRespuesta(200, 'OK');

			// #FB-REDSYS-DESCUADRE: el importe NO bloquea. Sobre 12 meses (15.216 cobros)
			// hay 105 cobros cuyo Ds_Amount no coincide con el total grabado - 1 de cada 60
			// desde 2026-06 - y solo 6 quedan por debajo de 2 EUR, con 78 por encima de 10
			// EUR: ninguna tolerancia da cero falsos positivos. Abortar dejaria ~100 cargos
			// al anio cobrados y sin pedido, que es peor que un pedido con el importe a
			// revisar. Se crea el pedido y se deja traza para reconciliar a mano.
			$nFbCobrado = $this->fbNotifImporte();
			if ($nFbCobrado !== null && is_object($order) && isset($order->info['total'])) {
				$nFbCambio  = isset($order->info['currency_value']) ? (float)$order->info['currency_value'] : 1;
				$nFbPedido  = round((float)$order->info['total'] * $nFbCambio, 2);
				$nFbCobrado = round($nFbCobrado, 2);
				if (abs($nFbCobrado - $nFbPedido) > 0.005) {
					@error_log('[FB-REDSYS-DESCUADRE] pedido=' . (int)$insert_id
						. ' module=redsys_xpay ref=' . $this->fbNotifReferencia()
						. ' ds_amount=' . number_format($nFbCobrado, 2, '.', '')
						. ' total_pedido=' . number_format($nFbPedido, 2, '.', '')
						. ' diferencia=' . number_format($nFbCobrado - $nFbPedido, 2, '.', '')
						. ' customer=' . (int)$customer_id);
				}
			}
		}
		if (tep_session_is_registered('cartID')) {
			$cart->reset(true);
			$nStatus = defined('MODULE_PAYMENT_REDSYS_XPAY_ORDER_STATUS_ID') ? (int)MODULE_PAYMENT_REDSYS_XPAY_ORDER_STATUS_ID : 2;
			tep_db_query("update " . TABLE_ORDERS_STATUS_HISTORY . " set orders_status_id = ".$nStatus." where orders_id = '" . (int)$insert_id . "'");
			tep_db_query("update " . TABLE_ORDERS . " set orders_status = ".$nStatus.", last_modified = now() where orders_id = '" . (int)$insert_id . "'");
            if (!empty($_POST) && isset($_POST["Ds_MerchantParameters"])) {
                // #FB-REDSYS-REPLAY: el movimiento se enlaza por (module, reference) y ya no por
                // "el ultimo movimiento del cliente", que dejaba filas sin orders_id (40 en
                // los ultimos 12 meses) y podia enlazar la fila equivocada.
                $sFbRef = $this->fbNotifReferencia();
                if ($sFbRef !== '') {
                    tep_db_query("update redsys_payment_movements set orders_id = " . (int)$insert_id . " where module = 'redsys_xpay' and reference = '" . tep_db_input($sFbRef) . "' and admin_id = 0 and value > 0 and orders_id = 0 order by id desc limit 1");
                } else {
                    $sql = sprintf(
                        'UPDATE redsys_payment_movements SET orders_id = %d WHERE customer_id = %d AND module = "redsys_xpay" ORDER BY id DESC LIMIT 1',
                        $insert_id,
                        $customer_id
                    );
                    tep_db_query($sql);
                }
			}
		}
    }

    function output_error() { return false; }

    function check() {
      if (!isset($this->_check)) {
        $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_REDSYS_XPAY_STATUS'");
        $this->_check = tep_db_num_rows($check_query);
      }
      return $this->_check;
    }

    function install() {
      tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Activar Apple Pay / Google Pay (Redsys)', 'MODULE_PAYMENT_REDSYS_XPAY_STATUS', 'False', 'Muestra Apple Pay / Google Pay via Redsys (X-Pay). Comparte FUC, clave SHA-256 y terminal con el modulo Redsys de tarjeta — configura aquel primero.', '6', '3', 'tep_cfg_select_option(array(\\'True\\', \\'False\\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Orden de mostrado', 'MODULE_PAYMENT_REDSYS_XPAY_SORT_ORDER', '2', 'Orden de aparicion en checkout (menor = antes).', '6', '0', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Estado del pedido', 'MODULE_PAYMENT_REDSYS_XPAY_ORDER_STATUS_ID', '2', 'Estado del pedido una vez pagado.', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
    }

    function remove() {
      tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys() {
      return array( 'MODULE_PAYMENT_REDSYS_XPAY_STATUS',
					'MODULE_PAYMENT_REDSYS_XPAY_SORT_ORDER',
					'MODULE_PAYMENT_REDSYS_XPAY_ORDER_STATUS_ID');
    }
  }
?>
