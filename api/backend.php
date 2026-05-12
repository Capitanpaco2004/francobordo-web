<?php
	/**
	* Se encarga de las modificaciones del frontend
	*/
	class backend
	{
		/**
		* Directorio del admin
		* @var string
		*/
		public $path;
	
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
			// Obtenemos directorio del backend
			$path = $this->getPath();
		}
		
		/**
		* Obtenemos el directorio del backend
		*/		
		private function getPath()
		{
			// Variables
			global $sPathRoot;
			$sFileDefine = $sPathRoot . '/includes/define.php';
		
			// Comprobamos si tenemos el archivo define
			if( !file_exists( $sFileDefine ) )
				return false;
			
			// Leemos archivo define		
			$sFile = preg_replace( "/[ \r\n\t]+|\"/", "", file_get_contents( $sFileDefine ) );
			
			// Obtenemos el directorio
			$nInit = stripos( $sFile, 'dirname($_SERVER[\'SCRIPT_NAME\']),' );
			$nEnd = stripos( $sFile, '\')){' );
			
			// Directorio admin
			$this->path = substr( $sFile, $nInit + 34, $nEnd - ($nInit + 34) );
		}
		
		/**
		* Nos posicionamos en el directorio e incluimos application_top
		*/
		private function setApplicationTop()
		{
			// Variables
			global $sPathRoot;

			// Nos posicionamos en el directorio backend
			chdir( $sPathRoot . '/' . $this->path );
			
			// Determinamos el archivo login
			if( file_exists( $sPathRoot . '/' . $this->path . '/login.php' ) )
				$sFileLogin = 'login.php';
			else
				$sFileLogin = 'login_admin.php';
			
			// Falseamos donde estamos para que no nos hagan redirect
			$_SERVER['SCRIPT_NAME'] = $this->path . '/' . $sFileLogin;
			$_SERVER['PHP_SELF'] = $sFileLogin;
			$_SERVER['SCRIPT_FILENAME'] = $sFileLogin;
			
			// Incluimos application_top
			include( 'includes/application_top.php' );
		}
		
		/**
		* Cambia la contraseña del admin
		*/
		public function password_change_admin()
		{
			// Variables
			global $api;
			
			// Application_top
			$this->setApplicationTop();
			
			// Comprobamos que nos hayan mandado la contraseña y el usuario
			if( !array_key_exists( 'user', $api->vars ) || !array_key_exists( 'password', $api->vars ) )
				die( 'Faltan datos' );
				
			// Modificamos la contraseña
			tep_db_perform( 'admin', array( 'admin_password' => tep_encrypt_password( $api->vars['password'] ) ), 'update', 'admin_email_address = "' . $api->vars['user'] . '"' );

			// Salida
			echo 1; exit();
		}
	}
?>