<?php
	// Alias
	namespace api;
	
	// Directorio raiz
	$sPathRoot = realpath( __DIR__ . '/..' );

	/**
	* Se encarga de cargar todas las librerias automaticamente
	*/
	class autoload
	{
		/**
		* Mapping
		* @var array
		*/
		private static $maps = array();

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
		public function __construct()
		{
			// Cargador
			spl_autoload_register( array( $this, 'loadClassLoader' ), true, true );
		}

		/**
		* Añade un map mediante un archivo
		* @param array $aMaps
		*/
		public function addMapArray($aMaps)
		{
			// Cargamos los mapping
			foreach( $aMaps as $sMap => $sInclude )
				$this->addMap( $sMap, $sInclude );
		}

		/**
		* Insertar mapa de autooload
		* @param string Idenficador del mapping
		* @param sDir Directorio donde buscar
		* @param bFirst Añadir al principio
		*/
		public function addMap($sId, $sDir, $bFirst = false)
		{
			if( $bFirst )
				self::$maps = array_merge( array( $sId => $sDir ), self::$maps );
			else
				self::$maps[$sId] = $sDir;
		}

		/**
		* Cargardor de librerias
		*/
		public static function loadClassLoader($sFileInclude)
		{
			// Variable
			global $sPathRoot;
			$bFound = false;

			// Recorremos los mapping
			foreach( self::$maps as $sMap => $sInclude )
			{
				$sMap = str_replace( '\\', '\\\\', $sMap );

				// Si contenemos un mapping
				if( preg_match( '/^' . $sMap . '/i', $sFileInclude) )
				{
					// Encontrado
					$bFound = true;

					// Remplazamos
					$sFileInclude = $sInclude . preg_replace( '/^' . $sMap . '/i', '', str_replace( '_', '/', $sFileInclude ) );

					// Detenemos
					break;
				}
			}

			// Remplazamos las barras y class
			$sFileInclude = str_replace( array( '\\', '//', 'class./', 'api/' ), array( '/', '/', 'class.', '' ), $sFileInclude );

			// Unimos la extension
			$sFileInclude = $sFileInclude . '.php';

			// Si no lo hemos encontrado añadimos el directorio root
			if( !$bFound )
				$sFileInclude = $sPathRoot . '/api/' . $sFileInclude;

			// Si existe incluimos
			if( file_exists( $sFileInclude ) )
				include( $sFileInclude );
		}
	}
	
	// Instanciamos el autoload
	$autoload = autoload::getInstance();
?>