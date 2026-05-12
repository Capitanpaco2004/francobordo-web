<?php
	// Alias
	namespace denox;

	// Tools
	use util\tools as tools;

	/**
	* Clase security
	*/
	class security
	{
		/**
		* Configuraciones
		* @var array
		*/
		public $configuration = array();

		/**
		* Ip del cliente
		* @var string
		*/
		public $ip = "0.0.0.0";

		/**
		* Dice si la ip esta en lista blanca o no
		* @var bool
		*/
		public $ipAllow = false;

		/**
		* Constructor de la clase
		*/
		public function __construct()
		{
			// ID de configuración grupo
			$aRecords = pharaonix_queryOne( 'SELECT configuration_group_id FROM configuration_group WHERE configuration_group_title = "Seguridad"' );

			// Configuracion
			$this->configuration = tools::parseConfiguration( $this->getKeysConfiguration() );

			// Separamos las configuraciones necesarias en arrays
			$this->configuration['SECURITY_GLOBAL_WHITELIST'] = array_filter( explode( ',', $this->configuration['SECURITY_GLOBAL_WHITELIST'] ) );
			$this->configuration['SECURITY_GLOBAL_BLACKLIST'] = array_filter( explode( ',', $this->configuration['SECURITY_GLOBAL_BLACKLIST'] ) );
			$this->configuration['SECURITY_GLOBAL_EMAIL_DATABASE'] = array_filter( explode( ',', $this->configuration['SECURITY_GLOBAL_EMAIL_DATABASE'] ) );
			$this->configuration['SECURITY_GLOBAL_EMAIL_NOTIFICATION'] = array_filter( explode( ',', $this->configuration['SECURITY_GLOBAL_EMAIL_NOTIFICATION'] ) );
			$this->configuration['SECURITY_DETECTION_404_FILES_WHITELIST'] = array_filter( explode( ',', $this->configuration['SECURITY_DETECTION_404_FILES_WHITELIST'] ) );
			$this->configuration['SECURITY_DETECTION_404_FILES_WHITELIST_EXTENSION'] = array_filter( explode( ',', $this->configuration['SECURITY_DETECTION_404_FILES_WHITELIST_EXTENSION'] ) );
			$this->configuration['SECURITY_BANED_AGENT'] = array_filter( explode( ',', $this->configuration['SECURITY_BANED_AGENT'] ) );

			// Ip del cliente
			$this->ip = $this->getIP();

			// Comprobamos si estamos en lista blanca
			if( in_array( $this->ip, $this->configuration['SECURITY_GLOBAL_WHITELIST'] ) )
				$this->ipAllow = true;

			// Limpiamos ip
			$this->cleanIpBlackList();

			// Recorremos los agentes de usuario para bloquear
			foreach( $this->configuration['SECURITY_BANED_AGENT'] as $sAgent )
				if( stristr( $_SERVER['HTTP_USER_AGENT'], $sAgent ) !== false )
					$this->redirectLockoutsBlacklist();

			// Si no estamos en lista blanca y tenemos ip bloqueada en lista negra o si el agente de usuario está bloqueado
			if( !$this->ipAllow && in_array( $this->ip, $this->configuration['SECURITY_GLOBAL_BLACKLIST'] ) )
				$this->redirectLockoutsBlacklist();

			// Si nos encontramos fuera del frontend es que estamos en el admin
			if( tep_session_name() != 'osCsid' )
				$this->adminSleepMode();
		}

		/**
		* Keys de configuración del modulo
		*/
		public function getKeysConfiguration()
		{
			return array( 'SECURITY_GLOBAL_WRITE_HTACCESS', 'SECURITY_GLOBAL_EMAIL_NOTIFICATION', 'SECURITY_GLOBAL_EMAIL_SUMMARY', 'SECURITY_GLOBAL_EMAIL_DATABASE', 'SECURITY_GLOBAL_MESSAGE_LOCKOUT_SERVER', 'SECURITY_GLOBAL_MESSAGE_LOCKOUT_LOGIN', 'SECURITY_BRUTEFORCE', 'SECURITY_BRUTEFORCE_BLACKLIST_COUNT', 'SECURITY_BRUTEFORCE_LOGIN_PERIOD', 'SECURITY_BRUTEFORCE_BLACKLIST_TOTAL', 'SECURITY_BRUTEFORCE_BLACKLIST_PERIOD', 'SECURITY_GLOBAL_WHITELIST', 'SECURITY_GLOBAL_BLACKLIST', 'SECURITY_GLOBAL_MESSAGE_LOCKOUT_SERVER_RESOLVE', 'SECURITY_DETECTION_404', 'SECURITY_DETECTION_404_PERIOD', 'SECURITY_DETECTION_404_COUNT', 'SECURITY_DETECTION_404_FILES_WHITELIST', 'SECURITY_DETECTION_404_FILES_WHITELIST_EXTENSION', 'SECURITY_ADMIN_AWAY', 'SECURITY_ADMIN_AWAY_START', 'SECURITY_ADMIN_AWAY_END', 'SECURITY_BANED_AGENT' );
		}

		/**
		* Modo reposo para la administración
		*/
		public function adminSleepMode()
		{
			// Variables
			$sGetAction = isset($_GET['action']) ? tep_db_prepare_input( $_GET['action'] ) : '';

			// Comprobamos que este activo el modo reposo y que no tengamos acceso
			if( $sGetAction != '' && $this->configuration['SECURITY_ADMIN_AWAY'] && $sGetAction != 'sleep_mode' && !(date('H:i:s') >= $this->configuration['SECURITY_ADMIN_AWAY_START'] && date('H:i:s') <= $this->configuration['SECURITY_ADMIN_AWAY_END']) )
				tep_redirect( tep_href_link( 'security.php', 'action=sleep_mode' ) );
		}

		/**
		* Cuando nos encontramos un 404
		*/
		public function error404()
		{
			// Variables
			$bActive = true;

			// Nos quedamos con la extension
			$sExtension = '.' . preg_replace( '/^.+\./i', '', $_SERVER['REQUEST_URI'] );

			// Nos quedamos con el archivo
			$sFile = preg_replace( '/^' . str_replace( '/', '\/', DIR_WS_HTTP_CATALOG ) . '/i', '/', $_SERVER['REQUEST_URI'] );

			// Si la extension esta registrada o el archivo esta registrado o tenemos puesto a 0 el conteo de 404 desactivamos
			if( $this->configuration['SECURITY_DETECTION_404_COUNT'] == 0 || in_array( $sExtension, $this->configuration['SECURITY_DETECTION_404_FILES_WHITELIST_EXTENSION'] ) || in_array( $sFile, $this->configuration['SECURITY_DETECTION_404_FILES_WHITELIST'] ) )
				$bActive =  false;

			// Registramos log
			$this->addLog( array(
				'security_log_type' => 'error404',
				'security_log_function' => 'Error 404',
				'security_log_date' => date( 'Y-m-d H:i:s' ),
				'security_log_active' => $bActive
			) );

			// Si no tenemos activo el umbral de 404 jamas podremos bloquear una IP 404
			if( $this->configuration['SECURITY_DETECTION_404_COUNT'] > 0 )
			{
				// Comprobamos cuantos error404 almacenamos en el umbral de tiempo configurado
				$nTotal = pharaonix_queryOne( 'SELECT COUNT(security_log_id) AS total FROM security_log WHERE ADDDATE( DATE_FORMAT( security_log_date, "%Y-%m-%d %H:%i:%s" ), INTERVAL ' . $this->configuration['SECURITY_DETECTION_404_PERIOD'] . ' MINUTE ) > DATE_FORMAT( NOW(), "%Y-%m-%d %H:%i:%s" ) AND security_log_active = 1 AND security_log_ip = "' . $this->ip . '"' )->records['total'];

				// Añadimos IP a la lista negra si hemos sobrepasado el umbral
				if( $nTotal >= $this->configuration['SECURITY_DETECTION_404_COUNT'] )
					$this->addIPBlackList( $this->ip );
			}
		}

		/**
		* Añade registro al log
		* @param array $aData
		*/
		public function addLog($aData)
		{
			// Variables
			$aData['security_log_date'] = date( 'Y-m-d H:i:s' );
			$aData['security_log_url'] = $this->getCurrentUrl();
			$aData['security_log_ip'] = $this->ip;
			$aDato['security_log_referer'] = array_key_exists( 'HTTP_REFERER', $_SERVER ) ? $_SERVER['HTTP_REFERER'] : '';

			// Insertamos
			tep_db_perform( 'security_log', $aData );
		}

		/**
		* Devuelve la URL actual
		* return string
		*/
		public function getCurrentUrl()
		{
			// Variables
			$sUrl = 'http';

			if( $_SERVER["HTTPS"] == 'on' )
				$sUrl .= "s";

			$sUrl .= "://";

			if( $_SERVER["SERVER_PORT"] != "80" && $_SERVER["SERVER_PORT"] != "443" )
				$sUrl .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
			else
				$sUrl .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];

			return $sUrl;
		}

		/**
		* Limpia los procesos fallidos de login al loguearse correctamente
		*/
		public function loginSuccess()
		{
			// Limpiamos
			tep_db_query( 'UPDATE security_lockouts SET security_lockouts_active = 0 WHERE security_lockouts_ip = "' . $this->ip . '" AND security_lockouts_active = "1"' );
		}

		/**
		* Limpia las ip de la lista negra transcurrido X dias desde que se añadieron
		*/
		public function cleanIpBlackList()
		{
			// Variables
			$sId = '';

			// Obtenemos todas las ip que esten bloqueadas
			$objRecords = pharaonix_query( 'SELECT security_lockouts_id, security_lockouts_ip FROM security_lockouts WHERE security_lockouts_end <= NOW() AND security_lockouts_active = 1 AND security_lockouts_type = "ip_black_list"' );

			// Si contenemos registros
			if( $objRecords->num_rows > 0 )
			{
				// Recorremos
				while( $aRecord = tep_db_fetch_array( $objRecords->records ) )
				{
					// Quitamos la IP de la lista
					if( in_array( $aRecord['security_lockouts_ip'], $this->configuration['SECURITY_GLOBAL_BLACKLIST'] ) )
						unset( $this->configuration['SECURITY_GLOBAL_BLACKLIST'][array_search( $aRecord['security_lockouts_ip'], $this->configuration['SECURITY_GLOBAL_BLACKLIST'] )] );

					$sId = $aRecord['security_lockouts_id'] . ',';
				}

				// Actualizamos
				tep_db_query( 'UPDATE configuration SET configuration_value = "' . implode( ',', $this->configuration['SECURITY_GLOBAL_BLACKLIST'] ) . '" WHERE configuration_key = "SECURITY_GLOBAL_BLACKLIST"' );
				tep_db_query( 'UPDATE security_lockouts SET security_lockouts_active = 0 WHERE security_lockouts_id IN(' . substr( $sId, 0, -1 ) . ')' );
			}
		}

		/**
		* Redirecciona hacia la página de bloqueo de IP
		*/
		public function redirectLockoutsBlacklist()
		{
			// Variables
			$sGetAction = tep_db_prepare_input( $_GET['action'] );

			// Redireccionamos si el action no es lockouts_blacklist
			if( $sGetAction != 'lockouts_blacklist' )
			{
				header( 'Location: ' . (defined( 'HTTP_CATALOG_SERVER' ) ? ($_SERVER["HTTPS"] == 'on' ? HTTPS_CATALOG_SERVER : HTTP_CATALOG_SERVER) . DIR_WS_CATALOG : '') . 'security.php?action=lockouts_blacklist' );
				exit();
			}
		}

		/**
		* Cuando realizamos login en el admin y ha fallado
		*/
		public function loginAdminFailed()
		{
			// Variables
			global $messageStack;

			// Si tenemos activo la fuerza bruta y no estamos en lista blanca
			if( $this->configuration['SECURITY_BRUTEFORCE'] && !$this->ipAllow )
			{
				// Variables
				$sType = 'login';

				// Aumentamos el tiempo en minutos que tenemos configurado
				$sPeriod = 'PT' . $this->configuration['SECURITY_BRUTEFORCE_LOGIN_PERIOD'] . 'M';

				// Comprobamos cuantas veces ha sido bloqueada la ip
				$nTotal = pharaonix_queryOne( 'SELECT COUNT(security_lockouts_id) AS total FROM security_lockouts WHERE security_lockouts_type = "login" AND security_lockouts_ip = "' . $this->ip . '" AND security_lockouts_active = 1' )->records['total'] + 1;

				// Si hemos llegado al total configurado bloquearemos la IP durante X minutos
				if( $nTotal == $this->configuration['SECURITY_BRUTEFORCE_BLACKLIST_TOTAL'] )
				{
					// Añadimos a la tabla de bloqueos
					$this->addLockouts( $sType, $sPeriod );

					// Eliminamos los registros que teniamos y bloqueamos por periodo
					tep_db_query( 'UPDATE security_lockouts SET security_lockouts_active = 0 WHERE security_lockouts_type = "login" AND security_lockouts_ip = "' . $this->ip . '"' );

					// Cambiamos el tipo
					$sType = 'login_period';
				}

				// Añadimos a la tabla de bloqueos
				$this->addLockouts( $sType, $sPeriod );

				// Mensaje y redirección
				$messageStack->addSession( 'error', str_replace( array('{COUNT}', '{COUNT_TOTAL}'), array( $nTotal, $this->configuration['SECURITY_BRUTEFORCE_BLACKLIST_TOTAL'] ), 'Nombre de usuario o contraseña errónea. Te quedan {COUNT} de {COUNT_TOTAL} intentos' ), 'error' );
				tep_redirect( tep_href_link( 'login.php' ) );
			}
		}

		/**
		* Añadir registro a la tabla de security_lockouts
		*/
		public function addLockouts($sType, $sPeriod)
		{
			// Variables
			$sPostEmail = tep_db_prepare_input( $_POST['email_address'] );
			$dateStar = date( 'Y-m-d H:i:s' );
			$dateEnd = new \DateTime('now');

			// Aumentamos el tiempo en minutos que tenemos configurado
			$dateEnd->add( new \DateInterval( $sPeriod ) );

			// Añadimos a la tabla de bloqueos
			tep_db_perform( 'security_lockouts', array(
				'security_lockouts_type' => $sType,
				'security_lockouts_start' => $dateStar,
				'security_lockouts_end' => $dateEnd->format( 'Y-m-d H:i:s' ),
				'security_lockouts_ip' => $this->ip,
				'security_lockouts_user' => $sPostEmail
			) );
		}

		/**
		* Añade una IP a la lista negra
		*/
		public function addIPBlackList($sIp)
		{
			// Si ya esta no hacemos nada
			if( in_array( $sIp, $this->configuration['SECURITY_GLOBAL_BLACKLIST'] ) )
				return false;

			// Añadimos la ip a la lista de bloqueos
			$this->configuration['SECURITY_GLOBAL_BLACKLIST'][] = $sIp;

			// Actualizamos
			tep_db_query( 'UPDATE configuration SET configuration_value = "' . implode( ',', $this->configuration['SECURITY_GLOBAL_BLACKLIST'] ) . '" WHERE configuration_key = "SECURITY_GLOBAL_BLACKLIST"' );

			// Reset cache
			tools::createCacheFile();

			// Retornamos
			return true;
		}

		/**
		* Comprueba en en login del admin si estamos en un periodo de bloqueo para no dejarte más intentos
		**/
		public function loginAdminPeriodLockouts()
		{
			// Variables
			global $messageStack;

			// Si tenemos activo la fuerza bruta y no estamos en lista blanca
			if( $this->configuration['SECURITY_BRUTEFORCE'] && !$this->ipAllow )
			{
				// Consultamos si tenemos bloqueado el login por un periodo de tiempo
				$objRecords = pharaonix_query( 'SELECT security_lockouts_id, security_lockouts_start, security_lockouts_end FROM security_lockouts WHERE security_lockouts_type = "login_period" AND security_lockouts_ip = "' . $this->ip . '" AND security_lockouts_active = 1 ORDER BY security_lockouts_start DESC', true );

				// Si tenemos el conjunto de bloqueos y supera el umbral añadimos ya a la lista negra la IP
				if( $objRecords->num_rows == $this->configuration['SECURITY_BRUTEFORCE_BLACKLIST_COUNT'] )
				{
					// Actualizamos los registros excepto el ultimo
					tep_db_query( 'UPDATE security_lockouts SET security_lockouts_active = 0 WHERE security_lockouts_type = "login_period" AND security_lockouts_ip = "' . $this->ip . '" AND security_lockouts_active = 1' );

					// Añadimos la ip a la lista negra si no lo esta ya
					if( !in_array( $this->ip, $this->configuration['SECURITY_GLOBAL_BLACKLIST'] ) )
					{
						// Añadimos IP a la lista negra
						$this->addIPBlackList( $this->ip );

						// Añadimos a la tabla de bloqueos
						$this->addLockouts( 'ip_black_list', 'P' . $this->configuration['SECURITY_BRUTEFORCE_BLACKLIST_PERIOD'] . 'D' );

						// Reset cache
						tools::createCacheFile();
					}

					// Redireccionamos
					$this->redirectLockoutsBlacklist();
				}
				// Si tenemos bloqueo
				elseif(	$objRecords->num_rows > 0 )
				{
					// Comprobamos si el tiempo no ha pasado
					if( $objRecords->records[0]['security_lockouts_end'] >= date( 'Y-m-d H:i:s' ) )
					{
						// Mensaje y redirección
						$messageStack->addSession( 'error', str_replace( array('{TIME}'), array( $this->configuration['SECURITY_BRUTEFORCE_LOGIN_PERIOD'] ), $this->configuration['SECURITY_GLOBAL_MESSAGE_LOCKOUT_LOGIN'] ), 'error' );
						tep_redirect( tep_href_link( 'login.php' ) );
					}
				}
			}
		}

		/*
		* Obtenemos la IP que esta navegando
		* @return string IP
		*/
		public function getIP()
		{
			$ip = false;
			$ips = [];

			if( !empty($_SERVER['HTTP_CLIENT_IP']) )
				$ip = $_SERVER['HTTP_CLIENT_IP'];

			if( !empty($_SERVER['HTTP_X_FORWARDED_FOR']) )
				$ips = explode(', ', $_SERVER['HTTP_X_FORWARDED_FOR']);

			if( $ip )
			{
				array_unshift($ips, $ip);
				$ip = false;
			}

			for ($i = 0; $i < count($ips); $i++)
			{
				if (!preg_match("/^(10|172\.16|192\.168)\./i", $ips[$i]))
				{
					$ip = $ips[$i];
					break;
				}
			}

			return ( $ip ? $ip : $_SERVER['REMOTE_ADDR'] );
		}
	}
?>
