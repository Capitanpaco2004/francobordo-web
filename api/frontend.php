<?php
	/**
	* Se encarga de las modificaciones del frontend
	*/
	class frontend
	{
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
		}
		
		/**
		* Cambia la contraseña del admin
		*/
		public function password_change_customer()
		{
			// Variables
			global $sPathRoot;
			global $api;
			global $currencies;
			global $language;
			
			// Posicionamos directorio
			chdir( $sPathRoot . '/' );
					
			// Application_top
			include( 'includes/application_top.php' );
						
			// Comprobamos que nos hayan mandado la contraseña y el usuario
			if( !array_key_exists( 'user', $api->vars ) || !array_key_exists( 'password', $api->vars ) )
				die( 'Faltan datos' );

			// Modificamos la contraseña
			tep_db_perform( 'customers', array( 'customers_password' => tep_encrypt_password( $api->vars['password'] ) ), 'update', 'customers_email_address = "' . $api->vars['user'] . '"' );

			// Salida
			echo 1; exit();
		}
	}
?>