<?php
	// Alias
	namespace util;

	// Liberias
	use util\tools;

/**
	* Clase donde se encuentra herramientas para los eventos de aplicación, constantes de los eventos, llamadas a las funciones, etc
	*/
	class event
	{
		/**
		 * Comprueba si se ha pasado por el archivo cache o no
		 * @var bool
		*/
		private $flagEventCache = false;

		/**
		 * Array con todos los eventos que se ejecutaran en la aplicacion
		 * @var array
		*/
		private $eventsLoad = array();

		/**
		 * Instancia de la clase
		 * @var object
		*/
		private static $instance;

		/**
		 * Retorna la instancia del objeto en uso, si no esta creada la crea
		 * @var object
		*/
		public static function getInstance()
		{
			if( !(self::$instance instanceof self) )
				self::$instance = new self();

			return self::$instance;
		}

		/**
		 * Constructor de la clase
		*/
		private function __construct(){
			if (!$this->flagEventCache) {
				$fileCache = (tools::isAdmin() ? '..' : '.') . '/cache/events.json';

				// Obtenemos eventos
				if (file_exists($fileCache)) {
					$this->multiAdd(json_decode(file_get_contents($fileCache), true));
				}

				// Cache
				$this->flagEventCache = true;
			}
		}

		/**
		* Igual que el metodo add pero podemos pasarle un array entero para que añada eventos
		* @param array $aAdds Array con los eventos para añadirlos
		*/
		public function multiAdd($aAdds)
		{
			foreach( $aAdds as $sEvent => $aCalls )
			{
				if( !is_array( $aCalls ) )
					$aCalls = array( array( 'execute' => $aCalls, 'arguments' => array() ) );

				foreach( $aCalls as $aCall )
				{
					// Si no es un array, convertimos array para poder recorrerlo como tal
					if( !is_array( $aCall ) )
						$aCall = array( 'execute' => $aCall, 'arguments' => array() );

					// Si no tiene argumentos
					if ( !isset($aCall['arguments']) ) {
						$aCall['arguments'] = [];
					}

					// Añadimos eventos
					$this->add( $sEvent, $aCall['execute'], $aCall['arguments'] );
				}
			}
		}

		/**
		* Añade un evento a la cola de ventos
		* @param string $sEvent Evento a ejecutar
		* @param string $fnFunction Funcion a realizar
		* @param array $aArguments Argumentos que queremos pasarle a la funcion del evento
		*/
		public function add($sEvent, $fnFunction, $aArguments = array())
		{
			// Variables
			$sEvent = strtolower( $sEvent );

			// Si no existe lo creamos
			if( !array_key_exists( $sEvent, $this->eventsLoad ) )
				$this->eventsLoad[$sEvent] = array();

			// Vamos añadiendo funciones
			$this->eventsLoad[$sEvent][] = array( 'execute' => $fnFunction, 'arguments' => $aArguments );
		}

		/**
		 * Ejecuta el evento pasado como argumento
		 * @param string $sEvent Evento a ejecutar
		 * @param array $aArguments Array de parametros para pasarlo
		 * @return array Devuelve un array con los eventos procesados
		*/
		public function execute($sEvent, $aArguments = array())
		{
			// Variables
			$returnEvents = array();
			$sEvent = strtolower( $sEvent );

			// Si no existe el evento o la funcion
			if( !array_key_exists( $sEvent, $this->eventsLoad ) )
				return $returnEvents;

			// Obtenemos todas las variables
			extract( $GLOBALS );

			// Recorremos y lanzamos funciones
			foreach( $this->eventsLoad[$sEvent] as $aCalls )
			{
				// Reseteamos
				$aVars = array();
				$call = false;

				switch( true )
				{
					// Si contiene una @ es una clase y se necesita instanciar
					case is_string($aCalls['execute']) && preg_match( '/^@/', $aCalls['execute'] ):
						// Separamos la clase del método
						$aAux = explode( '::', $aCalls['execute'] );

						// Eliminamos la @
						$aAux[0] = substr( $aAux[0], 1);

						// Call
						$call = isset($aAux[1]) ? array( new $aAux[0], $aAux[1] ) : new $aAux[0];
					break;

					// Si existe la function
					default:
						$call = $aCalls['execute'];
					break;
				}

				// Si no hemos encontrado nada
				if( $call === false )
					continue;

				// Convertimos las variables
				if (is_array($aCalls['arguments'])) {
					foreach( $aCalls['arguments'] as $sVar )
						eval( '$aVars[] = $' . $sVar . ';' );
				}

				// Añadimos
				$aVars = array_merge( $aVars, $aArguments );

				// Realizamos la peticion
				$returnEvents[] = call_user_func_array( $call, $aVars );
			}

			// Retornamos
			return $returnEvents;
		}
	}
?>
