<?php
	// Alias
	namespace util;

	// Librerias
	use Composer\Script\Event;
	use Composer\Installer\PackageEvent;
	use util\validators\RecursiveValidator;
	use util\arrays;
	use util\validators\ValidatorBase;
	use RecursiveDirectoryIterator;
	use RecursiveIteratorIterator;
	use RegexIterator;
	use ReflectionClass;

	/**
	* Clase donde se encuentra herramientas para el sistema, por ejemplo métodos para añadir configuraciones, instalaciones, etc
	*/
	class tools
	{
		private static $ot_shipping = null;
		/**
		 * Llamada cada vez que se elimina un paquete de composer
		 *
		 * @param Event $event
		 * @return void
		 */
		public static function composerPrePackageUninstall(PackageEvent $event)
		{
			$package = $event->getOperation()->getPackage();
			$namespaces = $package->getNames();
			$installationManager = $event->getComposer()->getInstallationManager();

			if (is_array($namespaces) && isset($namespaces[0]) && preg_match('/^oscdenox/i', $namespaces[0])) {
				// Directorio de instalacion
				$installPath = $installationManager->getInstallPath($package);

				// Fichero install
				$fileInstall = $installPath . '/Services/AddonInstall.php';

				if (!file_exists($fileInstall)) {
					$psr4 = substr(arrays::keyFirst($package->getAutoload()['psr-4']), 0, -1);
					$class =  $psr4 . '\\' . $psr4;
				}

				$class = isset($class) ? $class : arrays::keyFirst($package->getAutoload()['psr-4']) . 'Services\AddonInstall';

				// Ejecutamos uninstall
				if (method_exists($class, 'uninstall')) {
					require_once 'includes/configure.php';
					require_once  'includes/functions/database.php';
					require_once  'includes/database_tables.php';

					tep_db_connect();

					//echo "Desinstalando addon: " . $package->getName() . " (" . $class . ")\n";

					$class::uninstall();
				}

                if (method_exists($class, 'event')) {
                    $fileCache = getcwd() . '/cache/events.json';

                    if (!file_exists($fileCache)) {
                        return false;
                    }

                    $cacheEvents = json_decode(file_get_contents($fileCache), true);

                    foreach ($class::event() as $eventFinder => $callsFinder) {
                        foreach ($cacheEvents as $event => $calls) {
                            if ($eventFinder != $event){
                                continue;
                            }

                            foreach ($callsFinder as $indexFinder => $valueFinder) {
                                foreach ($calls as $index => $value) {
                                    if($value['execute'] == $valueFinder['execute']){
                                        unset($cacheEvents[$event][$index]);
                                    }
                                }
                            }

                            $cacheEvents[$event] = array_values($cacheEvents[$event]);

                            if(count($cacheEvents[$event]) == 0){
                                unset($cacheEvents[$event]);
                            }
                        }
                    }

                    file_put_contents('cache/events.json', json_encode($cacheEvents, JSON_PRETTY_PRINT | JSON_FORCE_OBJECT));
                }
			}
		}

		/**
		 * Llamada cada vez que se quiere instalar los fixture
		 *
		 * @param Event $event
		 * @return void
		 */
		public static function composerFixtures(Event $event)
		{
			// Obtenemos todos los paquetes
			$packages = $event->getComposer()->getRepositoryManager()->getLocalRepository()->getPackages();
			$installationManager = $event->getComposer()->getInstallationManager();

			// Confirm
			$io = $event->getIO();
			$confirm = $io->askConfirmation('Se van a destruir todos los datos y añadir los Fixtures ¿estas de acuerdo? [Y,N]: ', false);

			// Si no confirmamos
			if ($confirm === false){
				return false;
			}

			// Obtenemos parametro que seria el package
			$arguments = $event->getArguments();
			$argumentPackage = count($arguments) > 0 ? $arguments[0] : false;

			// Configure
			include 'includes/configure.php';

			// Funciones de base de datos
			require_once 'includes/functions/database.php';
			require_once  'includes/database_tables.php';

			// Conectamos con database
			tep_db_connect();

			// Recorremos
			foreach ($packages as $package) {
				// Directorio de instalacion
				$installPath = $installationManager->getInstallPath($package);

				// Mostramos
				echo $package->getName() . "\n---------------------\n";

				// Si existe
				if ($installPath . '/DataFixtures'){
					// Recorremos
					foreach (glob($installPath . '/DataFixtures/*.php') as $file)
					{
						// Si nos envian un package en concreto
						if ($argumentPackage !== false && $package->getName() != $argumentPackage) {
							continue;
						}

						// Instanciamos
						$class = arrays::keyFirst($package->getAutoload()['psr-4']) . 'DataFixtures\\' . basename($file, '.php');
						$class = new $class;

						// LLamamos
						$class($io);
					}
				}
			}
		}

		/**
		 * Llamada cada vez que se instala un modulo
		 *
		 * @param Event $event
		 * @return void
		 */
		public static function composerPostInstall(Event $event)
		{
			// Variables
			$cacheEvents = array();

			// Obtenemos todos los paquetes
			$packages = $event->getComposer()->getRepositoryManager()->getLocalRepository()->getPackages();
			$installationManager = $event->getComposer()->getInstallationManager();

			// Configure
			include 'includes/configure.php';

			// Funciones de base de datos
			require_once 'includes/functions/database.php';
			require_once  'includes/database_tables.php';

			// Conectamos con database
			tep_db_connect();

			// Defines cache
			require('includes/configuration_cache_read.php');

			// Obtenemos parametro que seria el package
			$arguments = $event->getArguments();
			$argumentPackage = count($arguments) > 0 ? $arguments[0] : false;

			// Recorremos
			foreach ($packages as $package) {
				// Si nos envian un package en concreto
				if ($argumentPackage !== false && $package->getName() != $argumentPackage) {
					continue;
				}

				echo "\n\e[1;34m=== Instalando modulo: " . $package->getName() . " ===\e[0m\n";
				// Obtenemos
				$psr4Find = $package->getAutoload();

				// Si no contiene continuamos
				if (!isset($psr4Find['psr-4'])){
					echo "\e[33m[AVISO]\e[0m El paquete no tiene autoload PSR-4, se omite.\n";
					continue;
				}

				// Directorio de instalacion
				$installPath = $installationManager->getInstallPath($package);

				// Fichero install
				$fileInstall = $installPath . '/Services/AddonInstall.php';

				if (!file_exists($fileInstall)) {
					// Instanciamos install
					$psr4 = substr(arrays::keyFirst($package->getAutoload()['psr-4']), 0, -1);
					$class =  $psr4 . '\\' . $psr4;
				}

				// Si contiene para instalar
				if (file_exists($fileInstall) || isset($class)) {
					// Instanciamos
					$class = isset($class) ? $class : arrays::keyFirst($package->getAutoload()['psr-4']) . 'Services\AddonInstall';

					// Ejecutamos install
					if (method_exists($class, 'install')) {
						$class::install();
					}

					// Ejecutamos SQL
					if (method_exists($class, 'sql')) {
						$class::sql();
					}

					// Ejecutamos Eventos
					if (method_exists($class, 'event')) {
						// Obtenemos
						$cacheEvents = array_merge_recursive($cacheEvents, $class::event());
					}
				}

				unset($class);

				// Si contiene migrations
				if (is_dir($installPath . '/Migrations')) {
					// Recorremos
					foreach (glob($installPath . '/Migrations/*.php') as $file)
					{
						$versionFile = str_replace(array('.php', 'Version'), array('',''), basename($file));
						$versionPackage = str_replace('.', '', $package->getPrettyVersion());

						// Instanciamos
						$class = arrays::keyFirst($package->getAutoload()['psr-4']) . 'Migrations\\' . basename($file, '.php');
						$class = (new $class)();

						if ($versionPackage == $versionFile){
							break;
						}
					}

				}
			}

			// Eventos de dominio
			$cacheDomainEvent = ['events' => [], 'mapping' => []];
			$search = 'implements DomainEventSubscriber';
			$pattern = '/.+On.+\.(php)$/';
			$dir = new RecursiveDirectoryIterator(getcwd());
			$ite = new RecursiveIteratorIterator($dir);
			$files = new RegexIterator($ite, $pattern, RegexIterator::GET_MATCH);

			// Suscriptores
			foreach ($files as $file) {
				if (isset($file[0]) && file_exists($file[0])) {
					$content = file_get_contents($file[0]);

					if (preg_match('/' . $search . '/i', $content)) {
						preg_match_all('/namespace (?P<namespace>.*);/i', $content, $match);
						$namespace = $match['namespace'][0] . '\\' . preg_replace('/^.*\/|\.php$/', '', $file[0]);
						$class = new ReflectionClass($namespace);
						$subscribers = $class->getMethod('subscribedTo')->invoke($class);

						foreach ($subscribers as $subscriber) {
							$reflector = new ReflectionClass($subscriber);
							$eventName = $reflector->getMethod('eventName')->invoke($reflector);

							if (!isset($cacheDomainEvent['events'][$eventName])) {
								$cacheDomainEvent['events'][$eventName] = [];
							}

							if (!in_array($namespace, $cacheDomainEvent['events'][$eventName])) {
								$cacheDomainEvent['events'][$eventName][] = $namespace;
							}
						}
					}
				}
			}

			// Manejadores
			$search = 'extends DomainEvent';
			$pattern = '/.+\DomainEvent\.php$/';
			$dir = new RecursiveDirectoryIterator(getcwd());
			$ite = new RecursiveIteratorIterator($dir);
			$files = new RegexIterator($ite, $pattern, RegexIterator::GET_MATCH);

			foreach ($files as $file) {
				if (isset($file[0]) && file_exists($file[0])) {
					$content = file_get_contents($file[0]);

					if (preg_match('/' . $search . '/i', $content)) {
						preg_match_all('/namespace (?P<namespace>.*);/i', $content, $match);
						$namespace = $match['namespace'][0] . '\\' . preg_replace('/^.*\/|\.php$/', '', $file[0]);
						$class = new ReflectionClass($namespace);
						$eventName = $class->getMethod('eventName')->invoke($class);

						$cacheDomainEvent['mapping'][$eventName] = $class->getName();
					}
				}
			}

			// Combinamos arrays
			file_put_contents('cache/events.json', json_encode($cacheEvents, JSON_PRETTY_PRINT | JSON_FORCE_OBJECT));
			file_put_contents('cache/domain_events.json', json_encode($cacheDomainEvent, JSON_PRETTY_PRINT | JSON_FORCE_OBJECT));
		}

        public static function composerUpdatePost(Event $event)
        {
            // Variables
            $cacheEvents = array();

            // Obtenemos todos los paquetes
            $packages = $event->getComposer()->getRepositoryManager()->getLocalRepository()->getPackages();
            $installationManager = $event->getComposer()->getInstallationManager();

            // Configure
            include 'includes/configure.php';

            // Funciones de base de datos
            require_once 'includes/functions/database.php';
            require_once 'includes/database_tables.php';

            // Conectamos con database
            tep_db_connect();

            // Defines cache
            require('includes/configuration_cache_read.php');

            // Obtenemos parametro que seria el package
            $arguments = $event->getArguments();
            $argumentPackage = count($arguments) > 0 ? $arguments[0] : false;

            // Recorremos
            foreach ($packages as $package) {
                // Si nos envian un package en concreto
                if ($argumentPackage !== false && $package->getName() != $argumentPackage) {
                    continue;
                }

                // Obtenemos
                $psr4Find = $package->getAutoload();

                // Si no contiene continuamos
                if (!isset($psr4Find['psr-4'])) {
                    continue;
                }

                // Directorio de instalacion
                $installPath = $installationManager->getInstallPath($package);

                // Fichero isntall
                $fileInstall = $installPath . '/Services/AddonInstall.php';

                if (!file_exists($fileInstall)) {
                    // Instanciamos install
                    $psr4 = substr(arrays::keyFirst($package->getAutoload()['psr-4']), 0, -1);
                    $class = $psr4 . '\\' . $psr4;
                }

                // Si contiene para instalar
                if (file_exists($fileInstall) || isset($class)) {
                    // Instanciamos
                    $class = isset($class) ? $class : arrays::keyFirst($package->getAutoload()['psr-4']) . 'Services\AddonInstall';

                    // Ejecutamos install
                    if (method_exists($class, 'update')) {
                        $class::update();
                    }


                }

                unset($class);
            }
        }

		/*
		* Obtenemos href de productos
		* @return string href
		*/
		public static function getHrefFrontend($sFile, $sParameter, $nLanguage = 3)
		{
			require_once(getcwd() . '/../includes/classes/language.php');
			$lng = new \language();

			if (!defined('FILENAME_PRODUCT_INFO')) {
				define('FILENAME_PRODUCT_INFO', 'product_info.php');
			}

			if (!defined('FILENAME_CATEGORIES')) {
				define('FILENAME_CATEGORIES', 'categories.php');
			}

			if (!defined('DIR_WS_HTTP_CATALOG')) {
				define('DIR_WS_HTTP_CATALOG', '/');
			}

			include_once(getcwd() . '/../includes/classes/seo.class.php');
			$seo_urls = new \SEO_URL($nLanguage);

			return preg_replace('/&/','&', $seo_urls->href_link( $sFile, $sParameter, 'NONSSL', false ) );
		}

		/**
		 * Utiliza los objetos validadores para usarlos en cualquier situacion
		 * @param string $validator Nombre del validador a usar
		 * @param string $value Valor ha evaluar
		 * @param array $arguments Argumentos para pasarlo al validador
		 * @param bool $returnValid Si deseamos que nos retorne el mensaje de error o un bool
		 * @return bool|ValidatorBase
		 */
		public static function validate($validator, $value, $arguments = array(), $returnValid = true, $isEmpty = true)
		{
			// Componemos el validador
			$validator = 'util\\validators\\' . $validator . 'Validator';

			// Validador
			$instance = new $validator($arguments);

			// Comprobamos si tenemos que validar con un metodo u otro
			if (method_exists($validator, 'validateAbstract')) {
				$return = $instance->validateAbstract($value, $isEmpty);
			} else {
				$return = $instance->validate($value, $isEmpty);
			}

			// Si deseamos bool o mensaje
			if ($returnValid) {
				return $return;
			} else {
				return $instance;
			}
		}

       /**
         * Pasado un array post y un array de validadores, validara cada elemento del POST
         * @param array $requestPost
         * @param array $validatorsGroup
         * @return array
         */
		public static function validateFromPost(array $requestPost, array $validatorsGroup): array
		{
			$messageErrors = [];

			foreach ($validatorsGroup as $field => $validators) {
				foreach ($validators as $validator) {
					if (!is_array($requestPost[$field]) || $validator instanceof RecursiveValidator) {
						$requestPost[$field] = [$requestPost[$field]];
					}

					foreach ($requestPost[$field] as $value) {
						$validators = clone $validator;

						if ($validators->validate($value, false) === false) {
							$messageErrors[$field] = is_array($messageErrors[$field]) ? $messageErrors[$field] : [];

							$messageErrors[$field][] = $validators->getMessageError();
						}
					}
				}
			}

			return $messageErrors;
		}

		// Funcion que te retorna una valor aleatorio y codificado
		public static function createRandomValue($length, $type = 'mixed')
		{
		    if (($type != 'mixed') && ($type != 'chars') && ($type != 'digits')) {
		        $type = 'mixed';
		    }

		    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		    $digits = '0123456789';

		    $base = '';

		    if (($type == 'mixed') || ($type == 'chars')) {
		        $base .= $chars;
		    }

		    if (($type == 'mixed') || ($type == 'digits')) {
		        $base .= $digits;
		    }

		    $value = '';

		    if (!class_exists('PasswordHash')) {
		        include DIR_WS_CLASSES . 'passwordhash.php';
		    }

		    $hasher = new \PasswordHash(10, true);

		    do {
		        $random = base64_encode($hasher->get_random_bytes($length));

		        for ($i = 0, $n = strlen($random); $i < $n; $i++) {
		            $char = substr($random, $i, 1);

		            if (strpos($base, $char) !== false) {
		                $value .= $char;
		            }
		        }
		    } while (strlen($value) < $length);

		    if (strlen($value) > $length) {
		        $value = substr($value, 0, $length);
		    }

		    return $value;
		}

		// Funcion recursiva para crear un array con el arbol perfecto de los campos descendiendo por el orden
		private static function _sort_(&$aAllRowsSort, $aAllRows, $sIndex, $nParent, $sKey, $requestId, &$sPosition)
		{
			// Si no existe padre no hacemos nada
			if( !array_key_exists( $nParent, $aAllRows ) )
				return array();

			$nCont = count($aAllRowsSort);

			foreach( $aAllRows[$nParent] as $aAux )
			{
				if( !array_key_exists( $nCont, $aAllRowsSort ) )
					$aAllRowsSort[$nCont] = array();

				$aAllRowsSort[$nCont][] = $aAux[$sIndex];

				// Si encontramos el ID a mover
				if( $aAux[$sIndex] == $requestId )
					$sPosition = ($sKey != '' ? $sKey . '_' : '') . $nCont;

				// LLamamos de nuevo
				_sort_( $aAllRowsSort[$nCont], $aAllRows, $sIndex, $aAux[$sIndex], ($sKey != '' ? $sKey . '_' : '') . $nCont, $requestId, $sPosition );

				// Aumentamos
				$nCont++;
			}
		}

		// Funcion para crear el SQL
		private static function sortCreateSql($aAllRowsSort, &$sSql, &$sIds, &$nCont)
		{
			foreach( $aAllRowsSort as $aAux )
			{
				if( is_array( $aAux ) )
					self::sortCreateSql($aAux, $sSql, $sIds, $nCont);
				else
				{
					$sSql .= ' WHEN ' . $aAux . ' THEN ' . $nCont . ' ';
					$sIds .= $aAux . ',';
					$nCont++;
				}
			}
		}

		/**
		* Ordena los campos de una tabla en base de datos
		*/
		public static function sort($aArguments = array())
		{
			// Variables
			$aAllRowsSort = array(0);
			$sPosition = '';
			$sMove = 'down';

			// Request
			$requestId = isset( $_GET['id'] ) ? (int)$_GET['id'] : 0;
			$requestNowPosition = isset( $_GET['nowPosition'] ) ? (int)$_GET['nowPosition'] : false;
			$requestPrevPosition = isset( $_GET['prevPosition'] ) ? (int)$_GET['prevPosition'] : false;

			// Si las posiciones son iguales no hacemos nada
			if( $requestPrevPosition !== false && $requestNowPosition !== false && $requestPrevPosition == $requestNowPosition )
				return false;

			// Argumentos
			$sTable = arrays::getValueByKey( $aArguments, 'table' );
			$sIndex = arrays::getValueByKey( $aArguments, 'index' );
			$sIndexSort = arrays::getValueByKey( $aArguments, 'index_sort' );
			$aAllRows = arrays::getValueByKey( $aArguments, 'all_rows' );
			$bParent = arrays::getValueByKey( $aArguments, 'parent', false );

			// Mandamos a crear el array ordenado si tiene padre
			if( $bParent )
				self::sort( $aAllRowsSort, $aAllRows, $sIndex, 0, '', $requestId, $sPosition );
			else
			{
				// Si no tiene padre es que son simple
				foreach( $aAllRows as $nCont => $aAux )
				{
					$aAllRowsSort[] = $aAux[$sIndex];

					// Si lo encuentra
					if( $aAux[$sIndex] == $requestId )
						$sPosition = $nCont;
				}
			}

			// Explotamos las posiciones
			$aPosition = explode( '_', $sPosition );

			// Si tenemos que adentrarnos en el array
			if( count( $aPosition ) > 1 )
			{
				// Nos posicionamos en la primera
				$aAux = $aAllRowsSort[$aPosition[0]];

				// Codigo eval para posicionarnos en el array
				$sEvalCode = '[' . $aPosition[0] . ']';

				// Eliminamos la primera
				unset( $aPosition[0] );

				// Eliminamos la ultima
				unset( $aPosition[count($aPosition)] );

				// Nos posicionamos
				foreach( $aPosition as $nPosition )
				{
					$aAux = $aAux[$nPosition];
					$sEvalCode .= '[' . $nPosition . ']';
				}
			}
			else
			{
				$sEvalCode = '';
				$aAux = $aAllRowsSort;
			}

			// Si nos mandan posicion
			if( $requestNowPosition !== false )
			{
				// Decidimos si movemos hacia arriba porque para abajo es por defecto
				if( $requestNowPosition < $requestPrevPosition )
					$sMove = 'up';

				// Ordenamos
				if( $sMove == 'up' )
				{
					// Movemos hacia arriba
					for( $nCont = $requestPrevPosition + 1; $nCont > $requestNowPosition + 1; $nCont-- )
					{
						$b = array_slice( $aAux, 0, ($nCont - 1), true );
						$b[] = $aAux[$nCont];
						$b[] = $aAux[$nCont - 1];
						$b += array_slice( $aAux, ($nCont + 1), count( $aAux ), true );
						$aAux = $b;
					}
				}
				else
				{
					// Movemos hacia abajo
					for( $nCont = $requestPrevPosition + 1; $nCont < $requestNowPosition + 1; $nCont++ )
					{
						$b = array_slice( $aAux, 0, $nCont, true );
						$b[] = $aAux[$nCont + 1];
						$b[] = $aAux[$nCont];
						$b += array_slice( $aAux, $nCont + 2, count( $aAux ), true );
						$aAux = $b;
					}
				}
			}

			// Guardamos en su posicion
			eval( '$aAllRowsSort' . $sEvalCode . ' = $aAux;' );

			// Eliminamos la posicion 0
			unset( $aAllRowsSort[0] );

			// Variables
			$sIds = '';
			$sSql = '';
			$nCont = 0;

			// Creamos SQL
			self::sortCreateSql( $aAllRowsSort, $sSql, $sIds, $nCont );

			// Actualizamos
			$query = 'UPDATE ' . $sTable . ' SET ' . $sIndexSort . ' = CASE ' . $sIndex . ' ' . $sSql . ' ELSE ' . $sIndexSort . ' END WHERE ' . $sIndex . ' IN (' . substr($sIds, 0, -1) . ')';

			$result = tep_db_query($query);

			return $result ? true : false;
		}

		public static function setOtShipping($mock) {
			self::$ot_shipping = $mock;
		}

		/*
		* Nos devuelve si tenemos envío gratuito o no
		* @return bool
		*/
		public static function hasFreeShipping()
		{
			// Si hay un mock, lo usamos
			if (self::$ot_shipping !== null) {
				return self::$ot_shipping->hasFreeShipping();
			}

			// Si no, usamos la implementación real
			if (!class_exists('ot_shipping')) {
				include DIR_WS_MODULES . 'order_total/ot_shipping.php';
			}

			$reflection = new \ReflectionClass('ot_shipping');
			self::$ot_shipping = $reflection->newInstanceWithoutConstructor();

			return self::$ot_shipping->hasFreeShipping();
		}


		/*
		* Obtenemos la IP que esta navegando
		* @return string IP
		*/
		public static function getIP()
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

		/**
		* Aumenta la version pasada por argumento, si es pasada 0.0.0 retornara 0.0.1 hasta llegar al 99
		* @param string $sVersion Version para aumentar
		* @return string Devuelve la version aumentada
		*/
		public static function version($sVersion)
		{
			$aVersion = explode('.', $sVersion);

			if( $aVersion[2] + 1 < 99 )
				$aVersion[2]++;
			else
			{
				$aVersion[2] = 0;

				if ($aVersion[1] + 1 < 99)
					$aVersion[1]++;
				else
				{
					$aVersion[1] = 0;
					$aVersion[0]++;
				}
			}

			return implode('.', $aVersion);
		}

  		/**
		* Encriptación OpenSSL de la cadena pasada por argumento
		*
		* @param string $sString - Texto a encriptar
		* @param string $key - Clave de encriptación
		* @return string - Devuelve la cadena encriptada
		*/
		public static function encrypt($sString, $key = '$P$DTb75wqwFAqNg1vFylQivQcRb1EHU5.')
		{
			if (!function_exists("openssl_digest"))
			{
			    throw new Exception("Es necesario el módulo de OpenSSL para PHP. Contacte con su Hosting para su instalación.");
			}

			$keyHash = hash('sha256', $key);
			$key     = openssl_digest($keyHash, 'sha256');
			$method  = 'AES-256-CBC';
			$ivSize  = openssl_cipher_iv_length($method);
			$iv      = openssl_random_pseudo_bytes($ivSize);

			return base64_encode($iv . openssl_encrypt ($sString, $method, $key, OPENSSL_RAW_DATA, $iv));
		}

		/**
		* Desencripta OpenSSL de la cadena pasada por argumento
		*
		* @param string $sString - Texto a desepcriptar
		* @param string $key - Clave de desencriptacion
		* @return string - Devuelve la cadena desepcriptada
		*/
		public static function decrypt($sString, $key = '$P$DTb75wqwFAqNg1vFylQivQcRb1EHU5.')
		{
			if (!function_exists("openssl_decrypt"))
			{
			    throw new Exception("Es necesario el módulo de OpenSSL para PHP. Contacte con su Hosting para su instalación.");
			}

			$sString = base64_decode($sString);
			$keyHash = hash('sha256', $key);
			$key     = openssl_digest($keyHash, 'sha256');
			$method  = 'AES-256-CBC';
			$ivSize  = openssl_cipher_iv_length($method);
			$iv      = substr($sString, 0, $ivSize);

			return openssl_decrypt (substr($sString, $ivSize), $method, $key, OPENSSL_RAW_DATA, $iv);
		}

		/**
		* Devuelve la cadena de texto y si encuentra un enlace sera remplazado por un anchor
		* @param string $sText Texto a convertir
		* @return string Devuelve el mismo string pero con los enlaces anchor
		*/
		public static function stringToLinks($sText)
		{
			$sText = preg_replace( '#(script|about|applet|activex|chrome):#is', "\\1:", $sText );
			$sText = ' ' . $sText;
			$sText = preg_replace( "#(^|[\n ])([\w]+?://[\w\#$%&~/.\-;:=,?@\[\]+]*)#is", "\\1<a style=\"color:#135EAE;outline:medium none;\" href=\"\\2\" target=\"_blank\">\\2</a>", $sText );
			$sText = preg_replace( "#(^|[\n ])((www|ftp)\.[\w\#$%&~/.\-;:=,?@\[\]+]*)#is", "\\1<a style=\"color:#135EAE;outline:medium none;\" href=\"http://\\2\" target=\"_blank\">\\2</a>", $sText );
			$sText = preg_replace( "#(^|[\n ])([a-z0-9&\-_.]+?)@([\w\-]+\.([\w\-\.]+\.)*[\w]+)#i", "\\1<a style=\"color:#135EAE;outline:medium none;\" href=\"mailto:\\2@\\3\">\\2@\\3</a>", $sText );
			$sText = preg_replace( "#(^|[\n ])(\#)([\w\#$%&~/.\-;:=,?@\[\]+]*)#is", "\\1\\2<a href=\"http://search.twitter.com/search?q=%23\\3\" target=\"_blank\">\\3</a>", $sText );
			$sText = preg_replace( "#(^|[\n ])(\@)([\w\#$%&~/.\-;:=,?@\[\]+]*)#is", "\\1\\2<a href=\"http://twitter.com/\\3\" target=\"_blank\">\\3</a>", $sText );
			$sText = substr( $sText, 1 );

			return $sText;
		}

		/**
		* Devuelve varios input por idioma con un seleccionable, util para añadir varios textos en diferentes idiomas
		* @param int $sIdGroup Id del grupo de configuración a obtener
		* @param bool $bEval Si queremos evaluar las configuraciones
		* @return array Devuelve un array con las configuraciones
		*/
		public static function getInputLanguages($sKeyInput, $sLabel, $aRecord = array(), $sHelp = '', $sHtmlError = '', $nSize = 10, $bInput = true, $bTextArea = false)
		{
			// Variables
			$sHtml = '';
			$sHtmlPull = '';
			$sHtmlFake = '';
			$sHtmlInput = '';
			$aLanguages = tep_get_languages();
			$aRecord = is_array( $aRecord ) ? $aRecord : array();

			// Recorremos idiomas
			foreach( $aLanguages as $aLanguage )
			{
				$sHtmlLanguage = tep_image( DIR_WS_CATALOG_LANGUAGES . $aLanguage['directory'] . '/images/' . $aLanguage['image'], $aLanguage['name'], '', '', 'style="margin-top: 0px;width: 17px;margin-right: 3px;position: relative;top: -2px;"' ) . ' ' . $aLanguage['name'];

				if( $sHtmlFake == '' )
					$sHtmlFake = '<div>' . $sHtmlLanguage . '</div>';

				$sHtmlPull .= '<li><a data-id="' . $aLanguage['id'] . '" href="javascript:void(0);" class="hv">' . $sHtmlLanguage . '</a></li>';

				if( $bInput )
					$sHtmlInput .= '<input data-id="' . $aLanguage['id'] . '" style="border-right: 0px; display: ' . ($sHtmlInput == '' ? 'block' : 'none' ) . '" class="column input-language" type="text" name="' . $sKeyInput . '[' . $aLanguage['id'] . ']" id="' . $sKeyInput . '" value="' . (array_key_exists( $aLanguage['id'], $aRecord ) ? $aRecord[$aLanguage['id']] : '') . '"/>';
				elseif( $bTextArea )
					$sHtmlInput .= '<textarea data-id="' . $aLanguage['id'] . '" style="min-height: 100px; display: ' . ($sHtmlInput == '' ? 'block' : 'none' ) . '" class="column input-language" name="' . $sKeyInput . '[' . $aLanguage['id'] . ']" id="' . $sKeyInput . '">' . (array_key_exists( $aLanguage['id'], $aRecord ) ? $aRecord[$aLanguage['id']] : '') . '</textarea>';
				else
					$sHtmlInput .= '<div data-id="' . $aLanguage['id'] . '" style="border-right: 0px; display: ' . ($sHtmlInput == '' ? 'block' : 'none' ) . '" class="column input-language" id="' . $sKeyInput . '">' . tep_draw_textarea_field_tinymce( $sKeyInput . '[' . $aLanguage['id'] . ']', 'tinymce-lng', '70', '20', (array_key_exists( $aLanguage['id'], $aRecord ) && $aRecord[$aLanguage['id']] !== null ? htmlspecialchars_decode( $aRecord[$aLanguage['id']] ) : '') ) . '</div>';

			}

			$sHtml .= '<label for="' . $sKeyInput . '" class="column a02 tright">' . $sLabel . '</label>';
			$sHtml .= '<div class="column a' . $nSize . ' ax row aflex">';
				$sHtml .= $sHtmlError != '' ? '<div class="column a12 afixed">' . $sHtmlError . '</div>' : '';
				$sHtml .= $sHtmlInput;
				$sHtml .= '<div class="column afixed">';
					$sHtml .= '<div data-value-update="true" class="drop xfselect">';
						$sHtml .= $sHtmlFake;
						$sHtml .= '<ul class="down">';
							$sHtml .= $sHtmlPull;
						$sHtml .= '</ul>';
					$sHtml .= '</div>';
				$sHtml .= '</div>';

				if( $sHelp != '' )
					$sHtml .= '<div class="column a12 DFhelp afixed">' . $sHelp . '</div>';
			$sHtml .= '</div>';

			// Retornamos
			return $sHtml;
		}


		/*
		 * Funcion que devuelve un array con los textos de un archivo del idioma
		 *
		 * @return array
		*/
		public static function getLangugeFile($sFile, $language = '', $bJson = false)
		{
			$aDenegado = array( '<?', '<?php', '?>', '' ); // Lineas denegadas cuando leemos un archivo
			$aReturn = array();

			// Si no nos envian la ruta
			if( !preg_match( '/\//i', $sFile ) )
				$sFile = DIR_FS_CATALOG . DIR_WS_LANGUAGES . $language . '/' . $sFile;

			// Comprobamos si el archivo existe
			if( file_exists( $sFile ) )
			{
				$aDatos = tools::getDefineKeysValuesByFile( $sFile, $aDenegado );

				foreach( $aDatos as $key => $value )
					$aReturn[tep_db_prepare_input($key)] = html_entity_decode($value, ENT_QUOTES, "UTF-8");
			}

			if( $bJson )
				return json_encode( $aReturn );
			else
				return $aReturn;
		}

		/*
		 * Funcion que lee un archivo lleno de defines y devuelve un array con KEY, VALUE
		 *
		 * @return array
		*/
		public static function getDefineKeysValuesByFile($sRutaCompleta, $aDenegado)
		{
			// Array de retorno
			$aReturn = array();

			// Abrimos el archivo
			$flFile = tools::getLinesFileUtf8( $sRutaCompleta );

			// Si hemos obtenido el archivo
			if( $flFile )
			{
				// Recorremos las lineas
				foreach( $flFile as $sLine )
				{
					// Inicio, Limpiamos la linea \\

					// Quitamos tabuladores
					$sLine = str_replace( "\t", '', $sLine );

					// Quitamos los alt+255
					$sLine = str_replace( " ", '', $sLine );

					// Quitamos espacios
					$sLine = trim( $sLine );

					// Fin, Limpiamos la linea \\

					// Comprobamos que la linea obtenida no sea algo que no queremos
					if( in_array( $sLine, $aDenegado ) )
						continue;

					// Comprobamos que sea un define
					if( !preg_match( '/^(define)(\s?)(\()/i', $sLine ) )
						continue;

					$definesExplode = self::defineExplode($sLine);

					foreach($definesExplode as $key => $value) {
						$aReturn[$key] = $value;
					}
				}

				return $aReturn;
			}

			return false;
		}

		public static function defineExplode($textDefine): ?array
		{
			// Comprobamos que sea un define
			if( !preg_match( '/^(define)(\s?)(\()/i', $textDefine ) )
				return null;

			$return = [];

			// Obtenemos los define de la linea, normalmente sera uno por cada linea, pero puede existir el caso que haya mas de un define en una linea
			preg_match_all( "/(define)(\s?)*(\()(.*)(\);)/Ui", $textDefine, $aDefines, PREG_PATTERN_ORDER );

			// Si no hemos obtenido nada es que hemos encontrado algun define sin ; al final
			if( count( $aDefines[0] ) == 0 )
				preg_match_all( "/(define)(\s?)*(\()(.*)(\))/Ui", $textDefine, $aDefines, PREG_PATTERN_ORDER );

			// Recorremos los define obtenidos
			foreach( $aDefines[0] as $sLine ) {
				// Inicio, descomponer el define obtenido \\
				// Descomponemos el define obtenido en KEY y VALUE
				preg_match('/(define)(\s*)(\()(?<KEY>[^,]+)(\s*)(\,)(\s*)(?<VALUE>.+)(\s*)(\))(\;?)$/i', $sLine, $aAux);

				// Comprobamos que el key sea una llamada a funcion y se ha quedado rota, de ser asi utilizamos otro preg_match para obtener el KEY y VALUE
				if( preg_match( '/\(/i', $aAux['KEY'] ) && ! preg_match( '/\)$/i', $aAux['KEY'] ) )
					preg_match('/(define)(\s*)(\()(?<KEY>.+)(\s*)(\,)(\s*)(?<VALUE>.+)(\s*)(\))(\;?)$/i', $sLine, $aAux);
				// Fin, descomponer el define obtenido \\

				// Inicio, limpiamos el key \\
				// Quitamos espacios
				$aAux['KEY'] = trim( $aAux['KEY'] );

				// Si contiene una concatenación la cadena dejamos las comas por el contrario eliminariamos
				if( ! preg_match( '/(\'|")(\s*)\.|\.(\s*)(\'|")/i', $aAux['KEY'] ) )
					$aAux['KEY'] = preg_replace( '/^(\'|")|(\'|")$/i', '', $aAux['KEY'] );
				// Fin, limpiamos el key \\

				// Inicio, limpiamos el value \\
				// Quitamos espacios
				$aAux['VALUE'] = trim( $aAux['VALUE'] );

				eval( '$sAux = ' . $aAux['VALUE'] . ';' );
				$aAux['VALUE'] = $sAux;

				// Si contiene una concatenación la cadena dejamos las comas por el contrario eliminariamos
				if( ! preg_match( '/(\'|")(\s*)\.(.+)|(\s*)\.(\s*)(\'|")(.+)/i', $aAux['VALUE'] ) )
					$aAux['VALUE'] = preg_replace( '/^(\'|")|(\'|")$/i', '', $aAux['VALUE'] );

				// Mostramos html como texto para que no afecte cuando se muestre en el input
				$aAux['VALUE'] = htmlentities( $aAux['VALUE'], ENT_QUOTES, "UTF-8");
				// Fin, limpiamos el value \\

				// Añadimos la linea al array
				$return[$aAux['KEY']] = $aAux['VALUE'];
			}

			return $return;
		}

		/*
		 * Funcion que lee un archivo y devuelve un array linea a linea en utf8
		 *
		 * @return array
		*/
		public static function getLinesFileUtf8( $sFile, $sCharset = 'UTF-8' )
		{
			$sData = '';

			if( !file_exists( $sFile ) ) {
				return false;
			}

			$sData = file_get_contents( $sFile );

			if( ! isset( $sFile ) ) {
				return false;
			}

			if( $sData && $sEncoding = 'UTF-8' != $sCharset ) {
				$sData = @mb_convert_encoding($sData, $sCharset, $sEncoding);
			}

			return preg_split( '/\R/', $sData );
		}

		/**
		* Devuelve un array con las configuraciones de un grupo
		* @param int $sIdGroup Id del grupo de configuración a obtener
		* @param bool $bEval Si queremos evaluar las configuraciones
		* @return array Devuelve un array con las configuraciones
		*/
		public static function getConfigurationArrayByIdGroupConfiguration($sIdGroup, $bEval = true)
		{
			// Variables
			$aReturn = array();

			// Obtenemos la configracion
			$aDatos = tep_db_query( 'SELECT configuration_key, configuration_value FROM configuration WHERE configuration_group_id = "' . (int)$sIdGroup . '"' );

			while( $aDato = tep_db_fetch_array( $aDatos ) )
			{
				$aDato['configuration_value'] = self::parseConfiguration( array( 'value' => $aDato['configuration_value'] ), false );
				$aReturn[$aDato['configuration_key']] = $aDato['configuration_value']['value'];
			}

			// Retornamos
			return $aReturn;
		}

		/**
		* Se le pasa un array con las keys de configuraciones y procesara su valor devolviendo un array key => value
		* @param array $aConfig
		* @param bool $bDefined
		* @return array
		*/
		public static function parseConfiguration($aConfig, $bDefined = true)
		{
			// Variables
			$aReturn = array();

			// Recorremos
			foreach( $aConfig as $sKey => $sValue )
			{
				if( $bDefined && defined( $sValue ) )
				{
					$sKey = $sValue;
					$sValue = constant( $sValue );
				}

				// Si tenemos que evaluar algunos códigos como true/false o json a array
				if( $sValue != '' )
				{
					switch (true)
					{
						// Comprobamos si es true/false
						case preg_match( '/^(true|false)$/i', trim( $sValue ) ):
							$sValue = filter_var( trim( $sValue ), FILTER_VALIDATE_BOOLEAN );
							break;

						// Comprobamos si es json
						case self::isJson( $sValue ):
							$sValue = json_decode( $sValue, true );
							break;

						// Si es numerico
						case is_numeric($sValue):
							$sValue =  $sValue + 0;
							break;
					}
				}

				// Damos valor
				$aReturn[$sKey] = $sValue;
			}

			// Retornamos
			return $aReturn;
		}

		/**
		* Devuelve true o false si la cadena pasada es un json
		* @param string $sJson
		* @return bool
		*/
		public static function isJson($sJson)
		{
			if( $sJson == '' || $sJson == null || $sJson == NULL || $sJson == 'null' || $sJson == 'NULL' || is_numeric($sJson) )
				return false;

			json_decode($sJson);
			return (json_last_error() == JSON_ERROR_NONE);
		}

		/**
		* Lee un archivo parsedown para documentación
		* @param string $sFile Archivo a leer
		* @return string Html formateado del archivo pasado como argumento
		*/
		public static function parsedown($sFile)
		{
			// Libreria
			require_once( DIR_WS_CLASSES . '/Parsedown.php' );

			// Variables
			$parsedown = new \Parsedown();
			$sCSS = '<style type="text/css" >.markdown-body {font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Helvetica,Arial,sans-serif,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol";font-size: 16px;line-height: 1.5;word-wrap: break-word;}.markdown-body::before {content: "";display: table;}.markdown-body::after {clear: both;content: "";display: table;}.markdown-body > *:first-child {margin-top: 0 !important;}.markdown-body > *:last-child {margin-bottom: 0 !important;}.markdown-body a:not([href]) {color: inherit;text-decoration: none;}.markdown-body .absent {color: #cb2431;}.markdown-body .anchor {float: left;line-height: 1;margin-left: -20px;padding-right: 4px;}.markdown-body .anchor:focus {outline: medium none;}.markdown-body p, .markdown-body blockquote, .markdown-body ul, .markdown-body ol, .markdown-body dl, .markdown-body table, .markdown-body pre {margin-bottom: 16px;margin-top: 0;}.markdown-body hr {background-color: #e1e4e8;border: 0 none;height: 0.25em;margin: 24px 0;padding: 0;}.markdown-body blockquote {border-left: 0.25em solid #dfe2e5;color: #6a737d;padding: 0 1em;}.markdown-body blockquote > *:first-child {margin-top: 0;}.markdown-body blockquote > *:last-child {margin-bottom: 0;}.markdown-body kbd {-moz-border-bottom-colors: none;-moz-border-left-colors: none;-moz-border-right-colors: none;-moz-border-top-colors: none;background-color: #fafbfc;border-color: #c6cbd1 #c6cbd1 #959da5;border-image: none;border-radius: 3px;border-style: solid;border-width: 1px;box-shadow: 0 -1px 0 #959da5 inset;color: #444d56;display: inline-block;font-size: 11px;line-height: 10px;padding: 3px 5px;vertical-align: middle;}.markdown-body h1, .markdown-body h2, .markdown-body h3, .markdown-body h4, .markdown-body h5, .markdown-body h6 {font-weight: 600;line-height: 1.25;margin-bottom: 16px;margin-top: 24px;}.markdown-body h1 .octicon-link, .markdown-body h2 .octicon-link, .markdown-body h3 .octicon-link, .markdown-body h4 .octicon-link, .markdown-body h5 .octicon-link, .markdown-body h6 .octicon-link {color: #1b1f23;vertical-align: middle;visibility: hidden;}.markdown-body h1:hover .anchor, .markdown-body h2:hover .anchor, .markdown-body h3:hover .anchor, .markdown-body h4:hover .anchor, .markdown-body h5:hover .anchor, .markdown-body h6:hover .anchor {text-decoration: none;}.markdown-body h1:hover .anchor .octicon-link, .markdown-body h2:hover .anchor .octicon-link, .markdown-body h3:hover .anchor .octicon-link, .markdown-body h4:hover .anchor .octicon-link, .markdown-body h5:hover .anchor .octicon-link, .markdown-body h6:hover .anchor .octicon-link {visibility: visible;}.markdown-body h1 tt, .markdown-body h1 code, .markdown-body h2 tt, .markdown-body h2 code, .markdown-body h3 tt, .markdown-body h3 code, .markdown-body h4 tt, .markdown-body h4 code, .markdown-body h5 tt, .markdown-body h5 code, .markdown-body h6 tt, .markdown-body h6 code {font-size: inherit;}.markdown-body h1 {border-bottom: 1px solid #eaecef;font-size: 2em;padding-bottom: 0.3em;}.markdown-body h2 {border-bottom: 1px solid #ccc;font-size: 1.5em;padding-bottom: 0.3em;}.markdown-body h3 {font-size: 1.25em;}.markdown-body h4 {font-size: 1em;}.markdown-body h5 {font-size: 0.875em;}.markdown-body h6 {color: #6a737d;font-size: 0.85em;}.markdown-body ul, .markdown-body ol {padding-left: 2em;}.markdown-body ul.no-list, .markdown-body ol.no-list {list-style-type: none;padding: 0;}.markdown-body ul ul, .markdown-body ul ol, .markdown-body ol ol, .markdown-body ol ul {margin-bottom: 0;margin-top: 0;}.markdown-body li > p {margin-top: 16px;}.markdown-body li + li {margin-top: 0.25em;}.markdown-body dl {padding: 0;}.markdown-body dl dt {font-size: 1em;font-style: italic;font-weight: 600;margin-top: 16px;padding: 0;}.markdown-body dl dd {margin-bottom: 16px;padding: 0 16px;}.markdown-body table {border-collapse: collapse;display: block;overflow: auto;width: 100%;}.markdown-body table th {font-weight: 600;}.markdown-body table th, .markdown-body table td {border: 1px solid #dfe2e5;padding: 6px 13px;}.markdown-body table tr {background-color: #fff;border-top: 1px solid #c6cbd1;}.markdown-body table tr:nth-child(2n) {background-color: #f6f8fa;}.markdown-body table img {background-color: transparent;}.markdown-body img {background-color: #fff;box-sizing: content-box;max-width: 100%;}.markdown-body img[align="right"] {padding-left: 20px;}.markdown-body img[align="left"] {padding-right: 20px;}.markdown-body .emoji {background-color: transparent;max-width: none;vertical-align: text-top;}.markdown-body span.frame {display: block;overflow: hidden;}.markdown-body span.frame > span {border: 1px solid #dfe2e5;display: block;float: left;margin: 13px 0 0;overflow: hidden;padding: 7px;width: auto;}.markdown-body span.frame span img {display: block;float: left;}.markdown-body span.frame span span {clear: both;color: #24292e;display: block;padding: 5px 0 0;}.markdown-body span.align-center {clear: both;display: block;overflow: hidden;}.markdown-body span.align-center > span {display: block;margin: 13px auto 0;overflow: hidden;text-align: center;}.markdown-body span.align-center span img {margin: 0 auto;text-align: center;}.markdown-body span.align-right {clear: both;display: block;overflow: hidden;}.markdown-body span.align-right > span {display: block;margin: 13px 0 0;overflow: hidden;text-align: right;}.markdown-body span.align-right span img {margin: 0;text-align: right;}.markdown-body span.float-left {display: block;float: left;margin-right: 13px;overflow: hidden;}.markdown-body span.float-left span {margin: 13px 0 0;}.markdown-body span.float-right {display: block;float: right;margin-left: 13px;overflow: hidden;}.markdown-body span.float-right > span {display: block;margin: 13px auto 0;overflow: hidden;text-align: right;}.markdown-body code, .markdown-body tt {background-color: rgba(27, 31, 35, 0.05);border-radius: 3px;font-size: 85%;margin: 0;padding: 0.2em 0;}.markdown-body code::before, .markdown-body code::after, .markdown-body tt::before, .markdown-body tt::after {content: " ";letter-spacing: -0.2em;}.markdown-body code br, .markdown-body tt br {display: none;}.markdown-body del code {text-decoration: inherit;}.markdown-body pre {word-wrap: normal;}.markdown-body pre > code {background: transparent none repeat scroll 0 0;border: 0 none;font-size: 100%;margin: 0;padding: 0; white-space: pre-wrap; word-wrap: break-word;}.markdown-body .highlight {margin-bottom: 16px;}.markdown-body .highlight pre {margin-bottom: 0;word-break: normal;}.markdown-body .highlight pre, .markdown-body pre {border: 1px solid #d9d9d9; background-color: #f6f8fa;border-radius: 3px;font-size: 85%;line-height: 1.45;overflow: auto;padding: 16px;}.markdown-body pre code, .markdown-body pre tt {background-color: transparent;border: 0 none;display: inline;line-height: inherit;margin: 0;overflow: visible;padding: 0;word-wrap: normal;}.markdown-body pre code::before, .markdown-body pre code::after, .markdown-body pre tt::before, .markdown-body pre tt::after {content: normal;}.markdown-body .csv-data td, .markdown-body .csv-data th {font-size: 12px;line-height: 1;overflow: hidden;padding: 5px;text-align: left;white-space: nowrap;}.markdown-body .csv-data .blob-num {background: #fff none repeat scroll 0 0;border: 0 none;padding: 10px 8px 9px;text-align: right;}.markdown-body .csv-data tr {border-top: 0 none;}.markdown-body .csv-data th {background: #f6f8fa none repeat scroll 0 0;border-top: 0 none;font-weight: 600;}</style>';

			return $sCSS . '<div class="markdown-body">' . $parsedown->text( file_get_contents( $sFile ) ) . '</div>';
		}

		/**
		* Devuelve los valores de las columnas en un nuevo array
		* @param array $aArray Array para extraer sus columnas
		* @param string|array $key Todas las columnas que necesites obtener en el nuevo array separadas por como o un array
		* @return array Array nuevo con las columnas solicitadas
		*/
		public static function arrayColumn()
		{
			// Variables
			$aReturn = array();
			$nStar = 1;

			// Obtenemos los argumentos
			$aArguments = func_get_args();

			// Obtenemos el array principal
			$aArray = $aArguments[0];

			// Si el segundo parametro es un array obtenemos los argumentos de alli
			if( is_array( $aArguments[1] ) )
			{
				$nStar = 0;
				$aArguments = $aArguments[1];
			}

			// Total de argumentos
			$nTotal = count( $aArguments );

			// Recorremos los argumentos
			for( $nCont = $nStar; $nCont < $nTotal; $nCont++ )
				if( array_key_exists( $aArguments[$nCont], $aArray ) )
					$aReturn[$aArguments[$nCont]] = $aArray[$aArguments[$nCont]];

			// Retornamos
			return $aReturn;
		}

		/**
		* Recrea el archivo cachefile.inc.php, recuerda usarlo una vez por recarga, ya que es un include y no puede incluirse más de una vez
		*/
		public static function createCacheFile()
		{
			// Variables
			$config_cache_file = (defined('DIR_FS_CATALOG') ? DIR_FS_CATALOG : '') . 'cache/cachefile.inc.php';
			$adminPath = self::getPathAdmin();

			// Si no esta definida
			if (!defined('TABLE_CONFIGURATION')) {
				define('TABLE_CONFIGURATION', 'configuration');
			}

			// Si no esta definida
			if (!defined('FILENAME_CONFIGURATION_CACHE')) {
				define('FILENAME_CONFIGURATION_CACHE', $config_cache_file);
			}

			// Incluimos
			require_once( str_replace($adminPath . '/', '', getcwd() . '/' ) . $adminPath . '/includes/configuration_cache.php' );
		}

		/**
		* Inserta un archivo en admin_files para su permiso en el admin
		* @param string $sFile Archivo del admin
		* @param int $sGroupId Grupo de usuario para permitir el archivo
		* @return stdClass Propiedades records y num_rows para recorrer los datos y el número de registros
		*/
		public static function insertAdminFiles($sFile, $sGroupId)
		{
			// Comprobamos si existe el campo
			$objRecords = pharaonix_checkDataColumTable( array(
				'VALUE' => $sFile,
				'COLUMN' => 'admin_files_name',
				'TABLE' => 'admin_files',
				'WHERE' => 'and admin_groups_id = "' . (int)$sGroupId . '"'
			));

			// Si no existe insertamos
			if( $objRecords->num_rows == 0 )
			{
				// Array de insercción
				$aData = array(
					'admin_files_name' => $sFile,
					'admin_files_is_boxes' => 0,
					'admin_files_to_boxes' => 3,
					'admin_groups_id' => $sGroupId
				);

				// Añadimos a admin_files
				tep_db_perform( 'admin_files', $aData );

				// ID insertado
				$aData['admin_files_id'] = tep_db_insert_id();

				// Retornamos
				$objRecords->records = $aData;
			}

			// Retornamos
			return $objRecords;
		}

		/**
		* Inserta un grupo de configuracion en configuration_group y devuelve la fila insertada
		* @param string $sTitle Titulo de la configuración
		* @param string $sDescription Descripción de la configuración
		* @param bool $bVisible Si se permite o no ser visible en el menu de administración
		* @return stdClass Propiedades records y num_rows para recorrer los datos y el número de registros
		*/
		public static function insertConfigurationGroup($sTitle, $sDescription = '', $bVisible = 1)
		{
			// Comprobamos si existe el campo
			$objRecords = pharaonix_checkDataColumTable( array(
				'VALUE' => $sTitle,
				'COLUMN' => 'configuration_group_title',
				'TABLE' => 'configuration_group'
			));

			// Si no existe insertamos
			if( $objRecords->num_rows == 0 )
			{
				// Obtenemos el orden de las configuraciones para añadirla al final
				$aDatoMax = pharaonix_queryOne( 'select MAX(sort_order) as max from configuration_group' );

				// Array de insercción
				$aData = array(
					'configuration_group_title' => $sTitle,
					'configuration_group_description' => $sDescription,
					'sort_order' => $aDatoMax->records['max'],
					'visible' => $bVisible
				);

				// Añadimos a las configuraciones
				tep_db_perform( 'configuration_group', $aData );

				// ID insertado
				$aData['configuration_group_id'] = tep_db_insert_id();

				// Retornamos
				$objRecords->records = $aData;
			}

			// Retornamos
			return $objRecords;
		}

		/**
		 * Inserta una configuración dentro de un grupo
		 * @param string $sTitle Titulo de la configuración
		 * @param string $sKey Nombre de variable
		 * @param string $sValue Valor de la configuración
		 * @param string $sDescription Descripción de la configuración
		 * @param int $sIdGroup Grupo al que pertenece
		 * @param int $sOrden Orden de la configuración
		 * @param string $setFunction
		 * @param string $useFunction
		 * @return stdClass Propiedades records y num_rows para recorrer los datos y el número de registros
		 */
		public static function insertConfiguration($sTitle, $sKey, $sValue, $sDescription, $sIdGroup, $sOrden = 0, $setFunction = '', $useFunction = '')
		{
			// Comprobamos si existe el campo
			$objRecords = pharaonix_checkDataColumTable( array(
				'VALUE' => $sKey,
				'COLUMN' => 'configuration_key',
				'TABLE' => 'configuration'
			));

			// Si no existe insertamos
			if( $objRecords->num_rows == 0 )
			{
				// Si el orden es 0 obtenemos el orden
				if( $sOrden == 0 )
				{
					// Consultamos el max order
					$aDatos = pharaonix_queryOne( 'SELECT MAX(sort_order) AS max FROM configuration WHERE configuration_group_id = "' . (int)$sIdGroup . '"' );

					// Si contenemos datos
					if( $aDatos->num_rows > 0 )
						$sOrden = $aDatos->records['max'] + 1;
				}

				// Array de inserccion
				$aData = array(
					'configuration_title' => $sTitle,
					'configuration_key' => $sKey,
					'configuration_value' => $sValue,
					'configuration_description' => $sDescription,
					'configuration_group_id' => $sIdGroup,
					'sort_order' => $sOrden,
					'set_function' => $setFunction,
					'use_function' => $useFunction
				);

				// Añadimos a las configuraciones
				tep_db_perform( 'configuration' , $aData );

				// ID insertado
				$aData['configuration_id'] = tep_db_insert_id();

				// Retornamos
				$objRecords->records = $aData;
			}

			// Devolvemos el ID insertado
			return $objRecords;
		}

		/**
		* Incluye un archivo template
		* @param string $sFile Archivo a cargar
		* @param array $aVars Variables del template
		*/
		public static function includeTemplate($sFile, $aVars = array())
		{
			// Juntamos las variables globales con las variables pasadas
			$aVars = array_merge( $GLOBALS, $aVars );

			// Extraemos las variables para el template
			extract( $aVars );

			// Almacenamos la salida hasta aqui para obtener solo el contenido a incluir
			ob_start();

			// Incluimos el archivo
			include( $sFile );

			// Contenido obtenido
			$sHtmlContent = ob_get_contents();

			// Continuamos con la salida por donde ibamos
			ob_end_clean();

			// Retornamos
			return $sHtmlContent;
		}

		/**
		* Nos devuelve si estamos en el admin o no
		*/
		public static function isAdmin()
		{
			if( strstr( dirname( $_SERVER['SCRIPT_NAME'] ), self::getPathAdmin() ) ){
				return true;
			}

			return false;
		}

		/**
		* Obtenemos el directorio del backend
		*/
		public static function getPathAdmin()
		{
			// 1. En runtime normal, si la constante está definida, la usamos
			if (defined('ADMIN_FOLDER_NAME')) {
				return ADMIN_FOLDER_NAME;
			}

			// 2. Fallback: intentar deducirlo leyendo define.php
			$sPathRoot   = realpath(__DIR__ . '/../..');
			$sFileDefine = $sPathRoot . '/includes/define.php';

			if (!file_exists($sFileDefine)) {
				// Si no encontramos nada, devolvemos por defecto "_admin"
				return '_admin';
			}

			// Leemos el contenido y quitamos espacios y comillas
			$sFile = preg_replace("/[ \r\n\t]+|\"/", "", file_get_contents($sFileDefine));

			// Intentamos encontrar la constante ADMIN_FOLDER_NAME
			if (preg_match("/define\('ADMIN_FOLDER_NAME','([^']+)'\)/", $sFile, $matches)) {
				return $matches[1];
			}

			// Fallback legacy: seguir usando el parseo anterior si no estaba la constante
			$nInit = stripos($sFile, 'dirname($_SERVER[\'SCRIPT_NAME\']),');
			$nEnd  = stripos($sFile, '\')){');

			if ($nInit !== false && $nEnd !== false) {
				return substr($sFile, $nInit + 34, $nEnd - ($nInit + 34));
			}

			// Última defensa: devolver "_admin"
			return '_admin';
		}

		/**
		* Comprueba si la extensión de opCache está instalada en el servidor
		*/
		public static function checkOpcache()
		{
		    if( function_exists('opcache_get_status') )
		    {
		        $aInfo = opcache_get_status();

		        if( $aInfo['opcache_enabled'] === true )
		            return true;
		    }

		    return false;
		}

		/**
		 * Obtenemos la url actual donde nos enconramos
		 *
		 * @return string $sUrl
		 */
		public static function getCurrentUrl()
		{
			// Variables
			$sUrl = 'http';

			if( $_SERVER["HTTPS"] == "on" )
				$sUrl .= "s";

			$sUrl .= "://";

			if( $_SERVER["SERVER_PORT"] != "80" && $_SERVER["SERVER_PORT"] != "443" )
				$sUrl .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
			else
				$sUrl .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];

			return $sUrl;
		}
	}
