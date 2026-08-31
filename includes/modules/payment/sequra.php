<?php
/*
   SeQura Payment GateWay
   Copyright (c) 2014 SeQura WorldWide SL
 */
if ( ! defined( 'DIR_FS_SEQURA' ) ) {
	define( 'DIR_FS_SEQURA', DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/SeQura/' );
}
if ( ! defined( 'DIR_WS_SEQURA' ) ) {
	if(ENABLE_SSL) {
		define( 'DIR_WS_SEQURA', DIR_WS_HTTPS_CATALOG . DIR_WS_MODULES . 'payment/SeQura/' );
	} else {
		define( 'DIR_WS_SEQURA', DIR_WS_HTTP_CATALOG . DIR_WS_MODULES . 'payment/SeQura/' );
	}
}
$charset = strtolower( CHARSET );
define( 'ISUTF8', $charset == 'utf8' || $charset == 'utf-8' );
define('SEQURA_ASSEST_BASE_URL','https://s3-eu-west-1.amazonaws.com/shop-assets.sequrapi.com/base/');
include_once( DIR_FS_CATALOG . 'includes/compat/compatibility_functions.php' );
require_once( DIR_FS_SEQURA . 'SequraHelper.php' );

if(!class_exists('sequra')){
	class sequra {
		const PENDING_STATUS  = 304;
		const APPROVED_STATUS = 305;
		const REJECTED_STATUS = 306;
		const INFORMED_STATUS = 307;
		const VERSION         = '3.1.0';

		protected $table_name = 'sequra';

		public $code;
		public $signature;
		public $api_version;
		public $debug;
		public $title;
		public $public_title;
		public $service_name;
		public $description;
		public $text_confirmation_other_methods;
		public $text_confirmation_header;
		public $text_confirmation_title;
		public $enabled;
		public $public_description;
		public $mode;
		public $order_status;
		public $clave;
		public $endpoint;
		public $form_action_url;
		public $sort_order;
		public $pp_cost_url;
		public $data;
		public $_check;

		function __construct() {
			global $order, $request_type;
			$this->checkforupgrades();
			$this->signature   = 'sequra|sequra|' . self::VERSION . '|2.4';
			$this->api_version = '1.0.0';
			$this->debug       = false;
			if((defined('MODULE_PAYMENT_SEQURA_SANDBOX') ? MODULE_PAYMENT_SEQURA_SANDBOX : 'False') == 'True'){
				define('MODULE_PAYMENT_SEQURA_ENDPOINT','https://sandbox.sequrapi.com/orders');
			} else {
				define('MODULE_PAYMENT_SEQURA_ENDPOINT','https://live.sequrapi.com/orders');
			}

			$this->code                            = 'sequra';
			// FIX 2026-05-29 payment_method vacio: si el contexto no cargo el fichero de idioma
			// del modulo (confirmaciones server-to-server de SeQura sin la sesion web normal),
			// las constantes de titulo quedaban indefinidas y el pedido se grababa con
			// payment_method vacio (caja 'Metodo de Pago' en blanco en el admin). Cargar el
			// idioma aqui replica el flujo normal; en checkout normal este bloque NO se ejecuta
			// porque payment.php ya incluyo el fichero de idioma antes del 'new'.
			if ( ! defined( 'MODULE_PAYMENT_SEQURA_TEXT_PUBLIC_TITLE' ) ) {
				global $language;
				$sequra_lang = ( isset( $language ) && $language !== '' ) ? $language : ( defined( 'DEFAULT_LANGUAGE' ) ? DEFAULT_LANGUAGE : 'espanol' );
				$sequra_lang_file = DIR_WS_LANGUAGES . $sequra_lang . '/modules/payment/sequra.php';
				if ( file_exists( $sequra_lang_file ) ) { include_once( $sequra_lang_file ); }
			}
			$this->title = defined( 'MODULE_PAYMENT_SEQURA_TEXT_TITLE' ) ? MODULE_PAYMENT_SEQURA_TEXT_TITLE : 'SeQura';
			$this->public_title = defined( 'MODULE_PAYMENT_SEQURA_TEXT_PUBLIC_TITLE' ) ? MODULE_PAYMENT_SEQURA_TEXT_PUBLIC_TITLE : 'SeQura';
			$this->service_name = defined( 'MODULE_PAYMENT_SEQURA_SERVICE_NAME' ) ? MODULE_PAYMENT_SEQURA_SERVICE_NAME : '';
			$this->description = defined( 'MODULE_PAYMENT_SEQURA_TEXT_DESCRIPTION' ) ? MODULE_PAYMENT_SEQURA_TEXT_DESCRIPTION : '';
			$this->text_confirmation_other_methods = defined( 'MODULE_PAYMENT_SEQURA_TEXT_CONFIRMATION_OTHER_METHODS' ) ? MODULE_PAYMENT_SEQURA_TEXT_CONFIRMATION_OTHER_METHODS : '';
			$this->text_confirmation_header = defined( 'MODULE_PAYMENT_SEQURA_TEXT_CONFIRMATION_HEADER' ) ? MODULE_PAYMENT_SEQURA_TEXT_CONFIRMATION_HEADER : '';
			$this->text_confirmation_title = defined( 'MODULE_PAYMENT_SEQURA_TEXT_CONFIRMATION_TITLE' ) ? MODULE_PAYMENT_SEQURA_TEXT_CONFIRMATION_TITLE : '';
			$this->enabled                         = $this->isEnabled();
			$this->sort_order                      = (defined('MODULE_PAYMENT_SEQURA_SORT_ORDER') ? MODULE_PAYMENT_SEQURA_SORT_ORDER : 0);
			$this->public_description = defined( 'MODULE_PAYMENT_SEQURA_TEXT_PUBLIC_DESCRIPTION' ) ? MODULE_PAYMENT_SEQURA_TEXT_PUBLIC_DESCRIPTION : '';
			$this->mode                            = 'i1';
			if ( is_object( $order ) ) {
				$this->update_status();
			}
			if ( (int) (defined('MODULE_PAYMENT_SEQURA_ORDER_STATUS_ID') ? MODULE_PAYMENT_SEQURA_ORDER_STATUS_ID : 0) > 0 ) {
				$this->order_status = MODULE_PAYMENT_SEQURA_ORDER_STATUS_ID;
			}

			$this->clave    = (defined('MODULE_PAYMENT_SEQURA_PASS') ? MODULE_PAYMENT_SEQURA_PASS : '');
			$this->endpoint = MODULE_PAYMENT_SEQURA_ENDPOINT;
			/*If onestepcheckout contrib redirect */
			if ( $_SERVER['PHP_SELF'] == '/checkout.php' && ! isset( $_REQUEST['sequra_approved'] ) ) {
				$this->form_action_url = tep_href_link( 'sequrapayment.php', null, 'SSL', true, false );
			}
		}

		static function allowedIp() {
			$ips_query = tep_db_query( "select configuration_value ips from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_SEQURA_IPS'" );
			$row       = tep_db_fetch_array( $ips_query );
			if ( $row['ips'] != '' ) {
				$ips = explode( ',', $row['ips'] );

				return in_array( $_SERVER['REMOTE_ADDR'], $ips );
			}

			return true;
		}

		function isEnabled() {
			$sql =  "select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_" .
			strtoupper(get_class($this)) . "_STATUS'";
			$check_query = tep_db_query( $sql );

			/* #FB-SEQURA-ENABLED  ATENCION: NO DESPLEGAR SIN CONFIRMAR.
			   Antes se devolvia la EXISTENCIA de la fila, no su valor, asi que
			   sequra_pp quedaba ACTIVO con MODULE_PAYMENT_SEQURA_PP_STATUS='False'.
			   Ese modulo es hoy el UNICO que cobra por SeQura: 129 pedidos y
			   64.696,35 EUR en 12 meses. Desplegar esto tal cual lo APAGA. */
			$check_row = tep_db_fetch_array( $check_query );
			if ( ! $check_row ) {
				return false;
			}
			return strtolower( trim( $check_row['configuration_value'] ) ) === 'true';
		}

		function isOrderAmountInRange() {
			global $order;
			if (isset($order->info['total'])){
				return $this->isAmountInRange( $order->info['total'] );
			}
			return true;
		}

		static function isAmountInRange( $amount ) {
			$toohigh = is_numeric( MODULE_PAYMENT_SEQURA_MAX_AMOUNT ) && MODULE_PAYMENT_SEQURA_MAX_AMOUNT > 0 && $amount > MODULE_PAYMENT_SEQURA_MAX_AMOUNT;

			return ! is_null( $amount ) && ! $toohigh;
		}

		function check() {
			$this->trace( "check()" );
			if ( ! isset( $this->_check ) ) {
				$this->_check =
					$this->allowedIp() &&
					$this->enabled &&
					$this->isOrderAmountInRange();
			}

			return $this->_check;
		}

		function email_footer() {
			return false;
		}

		function selection() {
			global $order;
			$amount               = round($order->info['total'] * $order->info['currency_value'] * 100);
			$title                = $this->public_description;
			$vars = $this->getTemplateVars();
			$vars['service-name'] = $this->service_name;
			$vars['total-amount'] = $amount;
			$field = SequraHelper::render('selection' . $this->getSuffix(), $vars);
			if ($this->check())
				return array('id' => $this->code,
										 'module' => $this->public_title,
										 'fields' => array(
											 array('title' => $title,
												'field' => $field)
										 ));
		}

		function getOptionsForIdentityFrom(){
			return array('product' => $this->mode);
		}

		function process_button() {
			global $language;
			include_once( DIR_WS_LANGUAGES . $language . '/modules/payment/sequra.php' );
			/*If onestepcheckout contrib redirect */
			if ($_SERVER['PHP_SELF'] == '/checkout.php')
				return false;

			global $order, $currency, $language, $shipping, $sendto, $billto, $comments, $customer_id;
			$this->trace("process_button()");
			//Amount
			$amount                = round($order->info['total'] * $order->info['currency_value'] * 100);
			$client                = SequraHelper::getClient();
			$builder               = SequraHelper::getBuilder();
			$data                  = $builder->build();
			$client->startSolicitation($data);
			$vars = $this->getTemplateVars();

			if ($client->succeeded()) {
				$uri                   = $client->getOrderUri();
				$options               = $this->getOptionsForIdentityFrom();
				$vars['identity_form'] = $client->getIdentificationForm($uri, $options);
				if (!ISUTF8) {
					$vars['identity_form'] = mb_convert_encoding($vars['identity_form'] ?? '', 'ISO-8859-1', 'UTF-8');
				}
				$vars['back']        = tep_href_link(FILENAME_CHECKOUT_PAYMENT);

				$_SESSION['SeQuraURI'] = $uri;
				$process_button_string = SequraHelper::render('form', $vars);
			} else {
				//tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . MODULE_PAYMENT_SEQURA_TEXT_ERROR_SOLICITATION, 'SSL', true, false));
				//exit;
				$url = tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . MODULE_PAYMENT_SEQURA_TEXT_ERROR_SOLICITATION, 'SSL', true, false);
				echo '<script>document.location.href="' . $url . '"</script>';
			}
			$data = array(
				'amount' => (int)$amount,
				'serialized_order' => urlencode(serialize($order)),
				'uri' => $uri,
				'customer_id' => $customer_id
			);
			tep_db_perform($this->table_name, $data);
			return $process_button_string;
			$this->trace("process_button(): process_button_string" . $process_button_string);
		}

		function before_process() {
			/* #FB-SEQURA-SIG Firma OBLIGATORIA y comparacion en tiempo constante.
			   El FORMATO no cambia (sigue siendo sign(sid)) A PROPOSITO: este es el
			   camino del checkout normal, mueve dinero real a diario y sus
			   notification_parameters viajan de ida y vuelta por SeQura. Ampliarlo
			   aqui exige probar antes en sandbox que SeQura hace eco de parametros
			   extra; ver deployNotes. Ademas el oID no existe todavia en este punto
			   (el pedido se crea despues), asi que no se puede atar. */
			$fb_sig = isset( $_POST['signature'] ) && is_string( $_POST['signature'] ) ? $_POST['signature'] : '';
			if ( $fb_sig === '' || ! hash_equals( SequraHelper::sign( tep_session_id() ), $fb_sig ) ) {
				SequraHelper::forbid();
			}
			$client     = SequraHelper::getClient();
			$builder    = SequraHelper::getBuilder();
			$this->data = $builder->build( 'confirmed' );
			$client->updateOrder( $_SESSION['SeQuraURI'], $this->data );
			if ( ! $client->succeeded() ) {
				http_response_code(410);
				//tep_redirect( tep_href_link( FILENAME_CHECKOUT_PAYMENT, 'error_message=No+se+ha+podido+realizar+el+pago', 'SSL', true, false ) );
				//echo '<script>document.location.href="'.tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . MODULE_PAYMENT_SEQURA_TEXT_ERROR_CART_CHANGED, 'SSL', true, false).'"</script>';
				exit;
			}

			return false;
		}

		function after_process() {
			global $insert_id;
			$client  = SequraHelper::getClient();
			$builder = SequraHelper::getBuilder();
			if ( ! $this->data ) {
				$this->data = $builder->build( 'confirmed' );
			} else {
				$this->data['merchant_reference'] = array(
					'order_ref_1' => $insert_id
				);
			}
			$client->updateOrder( $_SESSION['SeQuraURI'], $this->data );
			$data = array( "orders_id" => $insert_id );
			/* #FB-SEQURA-SIG WHERE sin escapar. tep_db_perform pega $parameters
			   VERBATIM (includes/functions/database.php:162). pay-with-sequra.php
			   escribia esta MISMA clave de sesion desde $_POST['order_ref'], asi
			   que era inyectable desde el otro flujo. */
			tep_db_perform( $this->table_name, $data, 'update', "uri='" . tep_db_input( isset( $_SESSION['SeQuraURI'] ) ? (string)$_SESSION['SeQuraURI'] : '' ) . "'" );
			if ( ! $client->succeeded() ) {
				$data = array( "orders_status" => self::REJECTED_STATUS );
				tep_db_perform( TABLE_ORDERS, $data, 'update', "orders_id='" . (int)$insert_id . "'" );
				$info['orders_id'] = $insert_id;
				http_response_code( 410 );
				//echo SequraHelper::render('error', $info); /*TODO: Crear pagina para estos casos*/
				exit;
			}
			unset( $_SESSION['SeQuraURI'] );

			return false;
		}

		function checkforupgrades() {
			$query = tep_db_query( "select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_SEQURA_INSTALLED_VERSION'" );
			$row   = tep_db_fetch_array( $query );
			if ( version_compare( self::VERSION, $row['configuration_value'], '>' ) ) {
				$this->upgrade( $row['configuration_value'], self::VERSION );
			}
		}

		function upgrade( $from, $to ) {
			$d    = dir( DIR_FS_SEQURA . '/upgrade/' );
			$file = $d->read();
			while ( false !== $file ) {
				$version = array();
				preg_match( '/update([\d]+\.[\d]*.[\d]*)\.php/', $file, $version );
				if ( version_compare( $version[1], $from, '>' ) ) {
					include( DIR_FS_SEQURA . 'upgrade/' . $file );
					tep_db_query( "update " . TABLE_CONFIGURATION . " set configuration_value ='" . $version[1] . "' where configuration_key = 'MODULE_PAYMENT_SEQURA_INSTALLED_VERSION'" );
				}
				$file = $d->read();
			}
		}

		function install() {
			$languages = tep_get_languages();
			for ( $i = 0, $n = sizeof( $languages ); $i < $n; $i ++ ) {
				tep_db_query( "insert ignore into " . TABLE_ORDERS_STATUS . " (orders_status_id,language_id,orders_status_name) values
	             ('" . self::PENDING_STATUS . "'," . $languages[ $i ]['id'] . ",'Pedido no finalizado'),
	             ('" . self::APPROVED_STATUS . "'," . $languages[ $i ]['id'] . ",'Aprobado'),
	             ('" . self::REJECTED_STATUS . "'," . $languages[ $i ]['id'] . ",'Rechazado'),
	             ('" . self::INFORMED_STATUS . "'," . $languages[ $i ]['id'] . ",'Sequra: Informado')" );
			}
			/*
			tep_db_query("update configuration set configuration_value = configuration_value + 1
				where configuration_group_id = 6
				and configuration_title like 'MODULE_PAYMENT%SORT_ORDER';");
			*/

			tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Activar m&oacute;dulo SeQura: Recibir antes de pagar', 'MODULE_PAYMENT_SEQURA_STATUS', 'True', '&iquest;Quiere aceptar pagos usando SeQura?', '6', '10', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())" );
			tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Activar solo estas IPs', 'MODULE_PAYMENT_SEQURA_IPS', '127.0.0.1,".gethostbyname('proxy-es.dev.sequra.es')."," . $_SERVER['REMOTE_ADDR'] . "','Es posible limitar la aparic&oacute;n del m&oacute;dulo solo a ciertas direcciones IPs para poder testearlo', '6', '15', now())" );
			tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Merchant reference', 'MODULE_PAYMENT_SEQURA_MERCHANT', '', '', '6', '20', now())" );
			tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Username', 'MODULE_PAYMENT_SEQURA_USER', '', '', '6', '30', now())" );
			tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Password', 'MODULE_PAYMENT_SEQURA_PASS', '', '', '6', '40', now())" );
			tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Assets key', 'MODULE_PAYMENT_SEQURA_ASSETS_KEY', '', '', '6', '30', now())" );
			tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Sandbox', 'MODULE_PAYMENT_SEQURA_SANDBOX', 'True', '', '6', '50', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())" );
			tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Orden de aparici&oacute;n.', 'MODULE_PAYMENT_SEQURA_SORT_ORDER', '0', 'Orden de aparicion. N&uacute;mero menor es mostrado antes que los mayores.', '6', '90', now())" );
			tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Zona de pago', 'MODULE_PAYMENT_SEQURA_ZONE', '0', 'Seleccione la zona correspondiente a Espa&ntilde;a, SeQura no env&iacute;a fuera de Espa&ntilde;a.', '6', '100', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())" );
			tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Importe m&aacute;ximo', 'MODULE_PAYMENT_SEQURA_MAX_AMOUNT', '400', 'Importe m&aacute;ximo para los pedidos a tramitar por SeQura', '6', '110', '', '', now())" );
			tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Estado pedido', 'MODULE_PAYMENT_SEQURA_ORDER_STATUS_ID', '" . self::APPROVED_STATUS . "', 'Seleccione el estado del pedido un vez procesado con este m&oacute;dulo', '6', '120', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())" );
			tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Estado pedidos enviados', 'MODULE_PAYMENT_SEQURA_SHIPPED_STATUS_ID', '3', 'Seleccione el estado en el que se dejan los pedidos cuando son enviados', '6', '130', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())" );
			tep_db_query( "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, date_added) values ('Version', 'MODULE_PAYMENT_SEQURA_INSTALLED_VERSION', '" . self::VERSION . "', '', '6', now())" );

			$sql = "CREATE TABLE if not exists " . $this->table_name . " (" .
			       "id INT NOT NULL AUTO_INCREMENT," .
			       "serialized_order TEXT NULL," .
			       "uri VARCHAR( 128 ) NULL," .
			       "amount INT DEFAULT '0' NOT NULL," .
			       "sent_to_sequra BIT DEFAULT 0," .
			       "customer_id INT( 11 )," .
			       "orders_id INT( 11 )," .
			       "session_id INT( 11 )," .
			       "PRIMARY KEY ( id ));";
			tep_db_query( $sql );
		}

		function keys() {
			return array
			(
				'MODULE_PAYMENT_SEQURA_STATUS',
				'MODULE_PAYMENT_SEQURA_MERCHANT',
				'MODULE_PAYMENT_SEQURA_USER',
				'MODULE_PAYMENT_SEQURA_PASS',
				'MODULE_PAYMENT_SEQURA_SANDBOX',
				'MODULE_PAYMENT_SEQURA_IPS',
				'MODULE_PAYMENT_SEQURA_SORT_ORDER',
				'MODULE_PAYMENT_SEQURA_ZONE',
				'MODULE_PAYMENT_SEQURA_MAX_AMOUNT',
				'MODULE_PAYMENT_SEQURA_ORDER_STATUS_ID',
				'MODULE_PAYMENT_SEQURA_SHIPPED_STATUS_ID',
				'MODULE_PAYMENT_SEQURA_ASSETS_KEY'
			);
		}

		function trace( $log, $force = false ) {
			if ( ! $this->debug && ! $force ) {
				return;
			}
			if ( is_writable( DIR_FS_CATALOG . '/images/' . $this->code . '.log' ) ) {
				$fp = fopen( DIR_FS_CATALOG . '/images/' . $this->code . '.log', "a+" );
				fwrite( $fp, date( "Y-m-d H:i:s" ) . " - " . $log . "\n" );
				fclose( $fp );
			} else {
				print $log . "\n";
			}
		}

	// class methods
		function update_status() {
			global $order;

			if (
				$this->enabled == true &&
				(int) constant( 'MODULE_PAYMENT_SEQURA_ZONE' ) > 0 &&
				isset($order->delivery['country']['id'])
			) {
				$check_flag  = false;
				$check_query = tep_db_query( "select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . constant( 'MODULE_PAYMENT_SEQURA_ZONE' ) . "' and zone_country_id = '" . $order->delivery['country']['id'] . "' order by zone_id" );
				while ( $check = tep_db_fetch_array( $check_query ) ) {
					if ( $check['zone_id'] < 1 ) {
						$check_flag = true;
						break;
					} elseif ( $check['zone_id'] == $order->delivery['zone_id'] ) {
						$check_flag = true;
						break;
					}
				}

				if ( $check_flag == false ) {
					$this->enabled = false;
				}
			}
		}

		function javascript_validation() {
			return false;
		}

		function pre_confirmation_check() {
			return false;
		}

		function confirmation() {
			$_SESSION['sequra_sufix'] = $this->getSuffix();

			return false;
		}

		function output_error() {
			return false;
		}

		function remove() {
			tep_db_query( "delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode( "', '", $this->keys() ) . "')" );
			/*
					 $sql = "DROP TABLE IF EXISTS " . $this->code;
					$result = tep_db_query($sql);
			*/
		}

		function load_config_description() {
			$query = tep_db_query( "select configuration_key,configuration_title, configuration_description from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode( "', '", $this->keys() ) . "')" );

			while ( $row = tep_db_fetch_array( $query ) ) {
				define( $row['configuration_key'] . "_DESC", $row['configuration_description'] );
				define( $row['configuration_key'] . "_TITLE", $row['configuration_title'] );
			}
		}

		function getSuffix() {
			return '';
		}

		function getTemplateVars( $filename = null ) {
			global $currency;
			$c           = new currencies();
			$css_to_load = '<link rel="stylesheet" type="text/css" href="' .DIR_WS_SEQURA . 'view/css/sequrapayment.css" />';
			$js_to_load  = '';
			return array(
				'css_to_load'     => $css_to_load,
				'js_to_load'      => $js_to_load,
				'decimal_point'   => $c->currencies[ $currency ]['decimal_point'],
				'thousands_point' => $c->currencies[ $currency ]['thousands_point'],
				'symbol_right'    => $c->currencies[ $currency ]['symbol_right'],
				'symbol_left'     => $c->currencies[ $currency ]['symbol_left'],
				'assetKey'        => MODULE_PAYMENT_SEQURA_ASSETS_KEY,
				'merchant'        => MODULE_PAYMENT_SEQURA_MERCHANT,
				'scriptUri'       => MODULE_PAYMENT_SEQURA_SANDBOX == 'True' ?
					'https://sandbox.sequracdn.com/assets/sequra-checkout.min.js' :
					'https://live.sequracdn.com/assets/sequra-checkout.min.js',
			);
		}
	}
}