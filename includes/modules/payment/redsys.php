<?php

//Logs Redsys - Funciones
if(!function_exists("escribirLog"))
	require_once('apiRedsys/redsysLibrary.php');

//Classe API Redsys
if(!class_exists("RedsysAPI"))
	require_once('apiRedsys/apiRedsysFinal.php');

class redsys {
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

// class constructor
    function __construct() {
      global $order;

      $this->code = 'redsys';
      $this->title = MODULE_PAYMENT_REDSYS_TEXT_TITLE;
      $this->description = MODULE_PAYMENT_REDSYS_TEXT_DESCRIPTION;
      $this->enabled = ((MODULE_PAYMENT_REDSYS_STATUS == 'True') ? true : false);
      $this->sort_order = MODULE_PAYMENT_REDSYS_SORT_ORDER;
      $this->mantener_pedido_ante_error_pago = ((MODULE_PAYMENT_REDSYS_ERROR_PAGO == 'si') ? true : false);
      $this->logActivo = MODULE_PAYMENT_REDSYS_LOG;
	  $this->logActivo = 'si';

      if ((int)MODULE_PAYMENT_REDSYS_ORDER_STATUS_ID > 0)
        $this->order_status = MODULE_PAYMENT_REDSYS_ORDER_STATUS_ID;

      if (is_object($order)) $this->update_status();

	  //Seleccion del entorno de pago
	  if(MODULE_PAYMENT_REDSYS_ENTORNO=="Entorno Real")
			$this->form_action_url = "https://sis.redsys.es/sis/realizarPago/utf-8";
	  else if(MODULE_PAYMENT_REDSYS_ENTORNO=="Entorno Pruebas")
			$this->form_action_url = "https://sis-t.redsys.es:25443/sis/realizarPago/utf-8";
	  else
			$this->form_action_url = "http://sis-d.redsys.es/sis/realizarPago/utf-8";
    }

	// class methods
    function update_status() {
		return false;
	}

    function javascript_validation() {
    	return false;
    }

    function selection() {
    	return array('id' => $this->code,
                     'module' => $this->title);
    }

    function pre_confirmation_check() {
    	global $cartID, $cart;

		if (empty($cart->cartID))
			$cartID = $cart->cartID = $cart->generate_cart_id();

		if (!tep_session_is_registered('cartID'))
			tep_session_register('cartID');

    }

    function confirmation() {
      return false;
    }

    function process_button() {
    	global $order, $currency, $customer_id, $language;
		$numpedido="1".time();

		//Amount
		$total=$order->info['total'];
		$cantidad = round($total*$order->info['currency_value'],2);
		$cantidad = number_format($cantidad, 2, '.', '');
		$cantidad = preg_replace('/\./', '', $cantidad);

		//Terminal
		$terminal = MODULE_PAYMENT_REDSYS_TERMINAL;

		// Tipo de trans.
		$trans = "0";

		//Idioma
		$idioma = MODULE_PAYMENT_REDSYS_IDIOMA;

		if( $idioma == "Si") {
			$idioma_web =substr($_SERVER["HTTP_ACCEPT_LANGUAGE"],0,2);

			switch ($idioma_web) {
				case 'es':
				$idiomaFinal='001';
				break;
				case 'en':
				$idiomaFinal='002';
				break;
				case 'ca':
				$idiomaFinal='003';
				break;
				case 'fr':
				$idiomaFinal='004';
				break;
				case 'de':
				$idiomaFinal='005';
				break;
				case 'nl':
				$idiomaFinal='006';
				break;
				case 'it':
				$idiomaFinal='007';
				break;
				case 'sv':
				$idiomaFinal='008';
				break;
				case 'pt':
				$idiomaFinal='009';
				break;
				case 'pl':
				$idiomaFinal='011';
				break;
				case 'gl':
				$idiomaFinal='012';
				break;
				case 'eu':
				$idiomaFinal='013';
				break;
				default:
				$idiomaFinal='002';
			}
			$idioma_tpv=$idiomaFinal;
		}else{
			$idioma_tpv="0";
		}

		//Merchant URL
		$urltienda =  tep_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL');
		$idSesion = tep_session_id();
		$urltienda = $urltienda."?osCsid=".$idSesion;
		$clave256 = MODULE_PAYMENT_REDSYS_ID_CLAVE256;

		$contador = mb_substr_count($urltienda, '?osCsid=');
		if($contador>1)
			$urltienda = tep_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL');
		//FUC
		$codigo = MODULE_PAYMENT_REDSYS_ID_COM;

		//URL_KO y URL_OK
		$ds_merchant_urlok =  tep_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL');
		$ds_merchant_urlko =  tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode("ERROR: Lo sentimos pero el pago con esta tarjeta no ha sido aceptado por la entidad de crédito. Por favor consulte con su banco o seleccione otro de los métodos de pago disponibles."), 'SSL');

		//Merchant Data
		$ds_merchant_data=sha1($urltienda);

		//Paymethods
		if(MODULE_PAYMENT_REDSYS_TIPOPAGO=="Tarjeta")
			$tipopago = "C";
	  	else if(MODULE_PAYMENT_REDSYS_TIPOPAGO=="Todos")
	  		$tipopago = "";
	  	else
			$tipopago = "T";

		//Moneda
		if(MODULE_PAYMENT_REDSYS_CURRENCY == "Euro"){
			$moneda = "978";
		}else{
			$moneda = "840";
		}

		//Productos
		foreach ($order->products as $product) {
			$productos.= $product['name']. ", ";
		}

		//Firma
	    $clave256=MODULE_PAYMENT_REDSYS_ID_CLAVE256;
		$ds_merchant_name = MODULE_PAYMENT_REDSYS_NOMBRE;

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

		//Datos de configuración
		$version = "HMAC_SHA256_V1";

		//Clave del comercio que se extrae de la configuración del comercio
		// Se generan los parámetros de la petición
		$request = "";
		$paramsBase64 = $miObj->createMerchantParameters();
		$signatureMac = $miObj->createMerchantSignature($clave256);

		// Elementos del Form al SIS
      	$process_button_string =
		tep_draw_hidden_field('Ds_SignatureVersion', $version) .
		tep_draw_hidden_field('Ds_MerchantParameters', $paramsBase64) .
		tep_draw_hidden_field('Ds_Signature', $signatureMac);

		return $process_button_string;
    }

    function before_process() {
        global $customer_id;
		
		$idLog = generateIdLog();
		$logActivo = MODULE_PAYMENT_REDSYS_LOG;
		$logActivo = 'si';
		$valido = FALSE;
		if (!empty( $_POST ) ) {//URL DE RESP. ONLINE

			$clave256=MODULE_PAYMENT_REDSYS_ID_CLAVE256;

			/** Recoger datos de respuesta **/
			$version       = $_POST["Ds_SignatureVersion"];
			$datos         = $_POST["Ds_MerchantParameters"];
			$firma_remota  = $_POST["Ds_Signature"];

			// Se crea Objeto
			$miObj = new RedsysAPI;

			/** Se decodifican los datos enviados y se carga el array de datos **/
			$decodec = $miObj->decodeMerchantParameters($datos);

			/** Se calcula la firma **/
			$firma_local = $miObj->createMerchantSignatureNotif($clave256,$datos);

			/** Extraer datos de la notificación **/
			$total     = $miObj->getParameter('Ds_Amount');
			$pedido    = $miObj->getParameter('Ds_Order');
			$codigo    = $miObj->getParameter('Ds_MerchantCode');
			$moneda    = $miObj->getParameter('Ds_Currency');
			$respuesta = $miObj->getParameter('Ds_Response');
			$identifier = $miObj->getParameter('Ds_Merchant_Identifier');
			$expire = $miObj->getParameter('Ds_ExpiryDate');
			$id_trans  = $miObj->getParameter('Ds_AuthorisationCode');

			$_SESSION['redsys'] = array( 'ds_identifier' => $identifier, 'ds_expire' => $expire );

			$message = $ds_amount.$ds_order.$ds_merchantcode.$ds_currency.$ds_response.$ds_merchant_identifier.$clave256;
			$signature = strtoupper (sha1( $message ));

			//Nuevas variables
			$codigoOrig=MODULE_PAYMENT_REDSYS_ID_COM;

			if(checkRespuesta($respuesta)
				&& checkMoneda($moneda)
				&& checkFuc($codigo)
				&& checkPedidoNum($pedido)
				&& checkImporte($total)
				&& $codigo == $codigoOrig
			){
				escribirLog($idLog." -- El pedido con ID " . $pedido . " es válido y se ha registrado correctamente.",$logActivo);
				$valido = TRUE;
			} else {
				escribirLog($idLog." -- Parámetros incorrectos.",$logActivo);
				if(!checkImporte($total)) {
					escribirLog($idLog." -- Formato de importe incorrecto.",$logActivo);
				}
				if(!checkPedidoNum($pedido)) {
					escribirLog($idLog." -- Formato de nº de pedido incorrecto.",$logActivo);
				}
				if(!checkFuc($codigo)) {
					escribirLog($idLog." -- Formato de FUC incorrecto.",$logActivo);
				}
				if(!checkMoneda($moneda)) {
					escribirLog($idLog." -- Formato de moneda incorrecto.",$logActivo);
				}
				if(!checkRespuesta($respuesta)) {
					escribirLog($idLog." -- Formato de respuesta incorrecto.",$logActivo);
				}
				if(!checkFirma($firma_remota)) {
					escribirLog($idLog." -- Formato de firma incorrecto.",$logActivo);
				}
				escribirLog($idLog." -- El pedido con ID " . $pedido . " NO es válido.",$logActivo);
				$valido = FALSE;
			}

			if ($firma_local != $firma_remota || FALSE === $valido) {
				//El proceso no puede ser completado, error de autenticación
				escribirLog($idLog." -- La firma no es correcta.",$logActivo);
				die ("FALLO DE FIRMA");
				exit;
			}

			$iresponse=(int)$respuesta;

			if (($iresponse>=0) && ($iresponse<=100)) {
				//Transacción aprobada
				//after_process();
                /**
                 * Guardamos el movimiento.
                 * #AUI-747-91109
                 * @author Daniel Lucia <daniel.lucia@denox.es>
                 */

                $values = [
                    'reference' => $pedido,
                    'value' => floatval(intval($total) / 100),
                    'customer_id' => intval($customer_id),
                    'module' => 'redsys',
                    'date_created' => 'now()',
                ];

                tep_db_perform(
                    'redsys_payment_movements',
                    $values
                ); 
			} else {
				//Transacción denegada
				if(!$this->mantener_pedido_ante_error_pago){
					$_SESSION['cart']->reset(true);
					escribirLog($idLog." -- Error de respuesta. Vaciando carrito.",$logActivo);
				} else {
					escribirLog($idLog." -- Error de respuesta. Manteniendo carrito.",$logActivo);
				}
				die ("FALLO EN LA RESPUESTA");
				exit;
			}
		} else {
      		//Transacción denegada
			escribirLog($idLog." -- Error. Hacking attempt!",$logActivo);
      		die ("Hacking attempt!");
			exit;
      	}

    }

    function after_process() {
		global $order, $insert_id, $cart, $customer_id;

		if (tep_session_is_registered('cartID')) {
			$cart->reset(true);
			tep_db_query("update " . TABLE_ORDERS_STATUS_HISTORY . " set orders_status_id = ".MODULE_PAYMENT_REDSYS_ORDER_STATUS_ID." where orders_id = '" . (int)$insert_id . "'");
			tep_db_query("update " . TABLE_ORDERS . " set orders_status = ".MODULE_PAYMENT_REDSYS_ORDER_STATUS_ID.", last_modified = now() where orders_id = '" . (int)$insert_id . "'");
            /**
             * Actualizamos el movimiento con el ID de pedido.
             * #AUI-747-91109
             * @author Daniel Lucia <daniel.lucia@denox.es>
             */
            if (!empty($_POST) && isset($_POST["Ds_MerchantParameters"])) {
                $sql = sprintf(
                    'UPDATE redsys_payment_movements SET orders_id = %d WHERE customer_id = %d ORDER BY id DESC LIMIT 1',
                    $insert_id,
                    $customer_id
                );
                tep_db_query($sql);
			}
		}
    }

    function output_error() {
    	return false;
    }

    function check() {
      if (!isset($this->_check)) {
        $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_REDSYS_STATUS'");
        $this->_check = tep_db_num_rows($check_query);
      }
      return $this->_check;
    }

	//Instalar módulo
    function install() {
	  tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Activar pasarela TPV', 'MODULE_PAYMENT_REDSYS_STATUS', 'True', 'Aceptar pagos mediante Tarjeta de Crédito', '6', '3', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
	  tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Nombre del Comercio', 'MODULE_PAYMENT_REDSYS_NOMBRE', '', 'Nombre del comercio', '6', '4', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('FUC Comercio', 'MODULE_PAYMENT_REDSYS_ID_COM', '', 'Cod. de comercio proporcionado por la entidad bancaria', '6', '4', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Clave de Encriptación (SHA-256)', 'MODULE_PAYMENT_REDSYS_ID_CLAVE256', '', 'Clave de encriptación SHA-256 proporcionada por la entidad bancaria', '6', '4', now())");
	  tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Terminal', 'MODULE_PAYMENT_REDSYS_TERMINAL', '', 'Terminal del comercio', '6', '4', now())");
	  tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Moneda', 'MODULE_PAYMENT_REDSYS_CURRENCY', 'Euro', 'Moneda permitida', '6', '3', 'tep_cfg_select_option(array(\'Euro\', \'Dolar\'), ', now())");
	  tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function,date_added) values ('Error pago', 'MODULE_PAYMENT_REDSYS_ERROR_PAGO', 'si', 'Mantener carrito si se produce un error en el pago', '6', '4','tep_cfg_select_option(array(\'si\', \'no\'), ',  now())");
	  tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function,date_added) values ('Log activo', 'MODULE_PAYMENT_REDSYS_LOG', 'no', 'Crear trazas de log', '6', '4','tep_cfg_select_option(array(\'si\', \'no\'), ',  now())");
	  tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Entorno de la pasarela de pago', 'MODULE_PAYMENT_REDSYS_ENTORNO', 'Entorno Pruebas', 'Entorno de la pasarela de pago', '6', '3', 'tep_cfg_select_option(array(\'Sis-d\', \'Entorno Pruebas\', \'Entorno Real\'), ', now())");
	  tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Tipos de pago permitidos', 'MODULE_PAYMENT_REDSYS_TIPOPAGO', 'Todos', 'Tipos de pago permitidos', '6', '3', 'tep_cfg_select_option(array(\'Todos\', \'Tarjeta\', \'Tarjeta y iuPay!\'), ', now())");
	  tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Activar idiomas', 'MODULE_PAYMENT_REDSYS_IDIOMA', 'No', 'Activar idiomas del TPV', '6', '3', 'tep_cfg_select_option(array(\'Si\', \'No\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Orden de mostrado.', 'MODULE_PAYMENT_REDSYS_SORT_ORDER', '1', 'Orden de mostrado. El menor valor es mostrado antes que los mayores.', '6', '0', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Estado del pedido', 'MODULE_PAYMENT_REDSYS_ORDER_STATUS_ID', '0', 'Seleccione el estado del pedido un vez procesado', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");

	}

	//Desinstalar módulo
    function remove() {
      tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys() {
      return array( 'MODULE_PAYMENT_REDSYS_STATUS',
					'MODULE_PAYMENT_REDSYS_NOMBRE',
					'MODULE_PAYMENT_REDSYS_ID_COM',
					'MODULE_PAYMENT_REDSYS_ID_CLAVE256',
					'MODULE_PAYMENT_REDSYS_TERMINAL',
					'MODULE_PAYMENT_REDSYS_CURRENCY',
					'MODULE_PAYMENT_REDSYS_ERROR_PAGO',
					'MODULE_PAYMENT_REDSYS_LOG',
					'MODULE_PAYMENT_REDSYS_ENTORNO',
					'MODULE_PAYMENT_REDSYS_TIPOPAGO',
					'MODULE_PAYMENT_REDSYS_IDIOMA',
					'MODULE_PAYMENT_REDSYS_SORT_ORDER',
					'MODULE_PAYMENT_REDSYS_ORDER_STATUS_ID');
    }
  }

?>