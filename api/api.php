<?php
	/**
	* Se encarga de las modificaciones del frontend
	*/
	class api
	{
		/**
		* Objeto environment a usar
		* @var object
		*/
		private $environment = false;
	
		/**
		* Metodo a usar
		* @var string
		*/
		private $method = false;
	
		/**
		* Variables pasadas por get
		* @var array
		*/
		public $vars = false;
	
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
			{
				$rc = new \ReflectionClass( get_called_class() );
				self::$instance = $rc->newInstanceArgs( func_get_args() );
			}

			return self::$instance;
		}
		
		/**
		* Constructor de la clase
		*/
		public function __construct()
		{
			// Variables
			global $sPathRoot;
			$sFileApiKey = $sPathRoot . '/api/apikey';

			// Si no nos envian send por get detenemos
			if( !array_key_exists( 'send', $_GET ) )
				die( 'Error no se le ha solicitado nada a la API' );

			// Si no tenemos apikey
			if( !file_exists( $sFileApiKey ) )
				die( 'Error no se encuentra apikey' );
			
			// Obtenemos la clave
			$sApiKey = file_get_contents( $sFileApiKey );
			
			// Decodificamos
			$sDecodeUrl = base64_decode( strtr( $_GET['send'], '-_', '+/' ) );
						
			// Descomponemos en array
			parse_str( $sDecodeUrl, $this->vars );
			
			// Api
			$this->vars['api'] = preg_replace( '/^.+\&api\=/', '', $sDecodeUrl );
			
			// Comparamos la clave
			if( $sApiKey != $this->vars['api'] )
				die( 'La contraseña es incorrecta' );
		}
		
		/**
		* Obtiene el environment y la accion a llamar
		*/
		public function getEnvironmentAction()
		{
			// Variables
			global $frontend;
			global $backend;
			
			// Si no nos envian entorno detenemos
			if( !array_key_exists( 'environment', $this->vars ) )
				die( 'Error no se le ha solicitado ningun entorno' );

			// Entornos
			$aEnvironments = array( $backend, $frontend, $this );
			
			// Recorremos los objetos que son los entornos para obtener cual deseamos ejecutar
			foreach( $aEnvironments as $aArg )
			{
				if( str_replace( 'api\\', '', get_class( $aArg ) ) == $this->vars['environment'] )
					$this->environment = $aArg;
			}

			// Si no hemos encontrado entorno
			if( $this->environment === false )
				die( 'Error no se le ha solicitado ningun entorno' );
				
			// Comprobamos que exista el metodo
			if( !array_key_exists( 'action', $this->vars ) || !method_exists( $this->environment, $this->vars['action'] ) )
				die( 'Error no existe la acción' );
			
			// Guardamos el método
			$this->method = $this->vars['action'];
		}
		
		/**
		* Actualiza archivos
		*/
		public function update()
		{
			${"\x47\x4c\x4fBAL\x53"}["\x63\x6e\x6csz\x63"]="\x73Pa\x74\x68\x52\x6fo\x74";${"\x47L\x4fB\x41LS"}["\x67\x73\x63\x62\x68\x78y\x76t"]="a\x46i\x6ce";global$sPathRoot;${"\x47\x4c\x4f\x42\x41\x4c\x53"}["\x79h\x66\x65\x78\x62\x68\x6d\x78p"]="\x61\x46\x69\x6c\x65";$zvyfoesuryz="\x61F\x69\x6c\x65";${"\x47L\x4f\x42\x41L\x53"}["\x6bs\x70bk\x70\x6b\x65\x72o\x77"]="re\x73";${${"GLO\x42A\x4cS"}["gsc\x62\x68\x78\x79\x76t"]}=$_FILES["fil\x65"];${"\x47\x4c\x4f\x42\x41\x4c\x53"}["\x63l\x78y\x6d\x63e\x76\x66r"]="zi\x70";$mhymszq="s\x50\x61\x74\x68\x52\x6f\x6f\x74";if(!in_array(${${"G\x4c\x4fBA\x4c\x53"}["y\x68fe\x78bh\x6d\x78p"]}["type"],array("\x61p\x70\x6ci\x63ati\x6fn/\x7a\x69p","\x61p\x70\x6c\x69c\x61ti\x6fn/\x78-\x7a\x69\x70-compr\x65s\x73e\x64","\x6dulti\x70ar\x74/\x78-z\x69p","ap\x70\x6c\x69c\x61\x74\x69on/x-c\x6fmpr\x65ss\x65\x64")))die("\x45\x6c\x20\x61\x72c\x68\x69v\x6f no\x20e\x73 \x75\x6e Z\x49\x50\x20v\x61l\x69\x64\x6f");move_uploaded_file(${$zvyfoesuryz}["t\x6dp_\x6e\x61\x6de"],${$mhymszq}."/\x61p\x69/\x75\x70\x64\x61te\x2e\x7a\x69p");$ybxtixv="\x73P\x61\x74\x68R\x6f\x6ft";${${"\x47\x4c\x4fBA\x4cS"}["c\x6c\x78\x79\x6d\x63\x65v\x66\x72"]}=new\ZipArchive;${${"G\x4cO\x42\x41LS"}["\x6bsp\x62\x6bpk\x65\x72\x6f\x77"]}=$zip->open(${$ybxtixv}."/a\x70i/\x75\x70d\x61te\x2e\x7ai\x70");$zip->extractTo(${${"\x47\x4c\x4f\x42\x41L\x53"}["\x63\x6e\x6c\x73\x7ac"]}."/\x61\x70i/");$zip->close();$sjnaqft="s\x50\x61thR\x6f\x6f\x74";unlink(${$sjnaqft}."/a\x70\x69/u\x70\x64a\x74\x65\x2e\x7aip");echo 1;exit();
		}
		
		/**
		* Comprueba el checksum de los archivos API
		*/
		public function checksum()
		{
			// Variables
			global $sPathRoot;
			$nReturn = 0;
						
			// Recorremos todos los archivos api
			$aFiles = glob( $sPathRoot . '/api/*.php' );
						
			foreach( $aFiles as $sFile )
				$nReturn += crc32( file_get_contents( $sFile ) );
			
			// Detenemos
			echo $nReturn;
			exit();
		}

		/**
		* Comprueba si tenemos virus
		*/
		public function search_defacement()
		{
			// Variables
			global $sPathRoot;
			$api = api::getInstance();
			$sReturn = '';
			
			// Obtenemos todos los archivos php
			$aFiles = api::rsearch( $sPathRoot, '/.+\.php$/' );

			// Recorremos para buscar
			foreach( $aFiles as $sFile )
			{
				// El listado se hace antes de leer: ficheros efímeros (p.ej. _admin/qfacwin_cfg.php,
				// que el proceso QFac crea y borra) desaparecen a mitad del escaneo → file_get_contents
				// emitía "Failed to open stream". Comprobamos existencia y silenciamos la lectura.
				if( ! is_file( $sFile ) )
					continue;

				// Shell
				if( !preg_match( '/threat_scanner\.php/' , $sFile ) && preg_match( '/128\/2/i', (string) @file_get_contents( $sFile ) ) )
					$sReturn .= 'Shell: ' . $sFile;

				// Checkout confirmation tiene js hacia el ie/6
				if( preg_match( '/checkout_confirmation\.php/i', $sFile ) && preg_match( '/js\/ie/i', file_get_contents( $sFile ) ) )
					$sReturn .= 'Defacement: ' . $sFile;
				
				// Checkout payment ext tiene js hacia el ie/6
				if( preg_match( '/checkout_payment_ext\.php/i', $sFile ) && preg_match( '/js\/ie/i', file_get_contents( $sFile ) ) )
					$sReturn .= 'Defacement: ' . $sFile;
			}
			
			// Retorno
			echo ($sReturn == '' ? 1 : $sReturn);
			
			// Salida
			exit();
		}
		
		/**
		* Devuelve un array recursivo con todos los archivos del proyecto, pudiendo añadir un patron de búsqueda
		*/
		public function rsearch($folder, $pattern) 
		{
			$dir = new \RecursiveDirectoryIterator($folder);
			$ite = new \RecursiveIteratorIterator($dir);
			$files = new \RegexIterator( $ite, $pattern, \RegexIterator::GET_MATCH );
			$fileList = array();

			foreach($files as $file)
				$fileList = array_merge($fileList, $file);

			return $fileList;
		}
		
		/**
		* Ejecuta un metodo de la api
		*/
		public function execute()
		{
			// Ejecutamos el metodo
			call_user_func( array( $this->environment, $this->method ) );
		}
	}
?>