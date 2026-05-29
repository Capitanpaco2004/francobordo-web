<?php
	// Tools
	use util\tools as tools;

	/**
	* Barra de depuración para ver consultas lanzadas, tiempo de la página, variables, etc
	*/
	class debugbar
	{
		/**
		* Configuraciones
		* @var array
		*/
		public $configuration = array();

		/**
		* Sabemos si esta activo o no el debug
		* @var bool
		*/
		public $active;

		/**
		* Array con los debug de SQL
		* @var array
		*/
		private $sql = array();

		/**
		* Hora cuando se inicia la web
		* @var int
		*/
		private $start;

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
			// Configuracion
			$this->configuration = tools::parseConfiguration( $this->getKeysConfiguration() );

			// Separamos las configuraciones necesarias en arrays
			$this->configuration['DEBUGBAR_IPS_ALLOWED'] = explode( "\n", $this->configuration['DEBUGBAR_IPS_ALLOWED'] );

			// Activo o no segun la configuracion
			$this->active = $this->configuration['DEBUGBAR_ACTIVE'] && $this->isAllowedIP($_SERVER['REMOTE_ADDR']);

			// Verifica si el dominio de la solicitud termina en ".loc"
			if (strpos((string)($_SERVER['HTTP_HOST'] ?? ''), '.loc') !== false) {
				// El dominio termina en ".loc", habilita la funcionalidad
				$this->active = true;
			}

			// Hora de inicio
			$this->start = microtime();
		}

		/**
		 * Verifica si una dirección IP dada está permitida en base a la lista de rangos de IP y direcciones IP individuales
		 * en la configuración.
		 *
		 * @param string $ip La dirección IP que se desea verificar.
		 *
		 * @return bool Retorna `true` si la dirección IP dada está dentro de alguno de los rangos permitidos o es igual
		 *              a alguna de las IPs individuales, de lo contrario, retorna `false`.
		 */
		private function isAllowedIP($ip)
		{
			foreach ($this->configuration['DEBUGBAR_IPS_ALLOWED'] as $range) {
				if (errorBacktrace::checkIPRange($ip, $range)) {
					return true;
				}
			}
			return false;
		}

		/**
		* Keys de configuración del modulo
		*/
		public function getKeysConfiguration()
		{
			return array( 'DEBUGBAR_ACTIVE', 'DEBUGBAR_IPS_ALLOWED' );
		}

		/**
		* Debug SQL pasa como primer argumento el tiempo antes de lanzar el SQL y como segundo el SQL
		* @param int $nTimerStart
		* @param string $sSql
		*/
		public function debugSql($nTimerStart, $sSql)
		{
			// Si no esta activo no hacemos nada
			if( !$this->active )
				return false;

			// Descomponemos la hora inicio la hora fin y las restamos para obtener el tiempo que ha tardado la consulta
			$nStart = explode( ' ', $nTimerStart );
			$nEnd = explode( ' ', microtime() );
			$nTime = number_format( $nEnd[1] + $nEnd[0] - ($nStart[1] + $nStart[0]), 6 );

			// Guardamos
			$this->sql[] = array( 'time' => $nTime, 'sql' => $sSql );
		}

		/**
		* Pinta la funcion var_dump de php
		*/
		private function vardump($aArray)
		{
  			ob_start();
  			var_dump($aArray);
  			return ob_get_clean();
		}

		/**
		* Pinta la funcion phpinfo de php
		*/
		private function phpinfo()
		{
  			ob_start();
			phpinfo();
  			return ob_get_clean();
		}

		/**
		* Metodo que se llama al finalizar la petición mostrando la barra
		*/
		public function show()
		{
			// Si no esta activo no hacemos nada
			if( !$this->active ) {
				return false;
			}

			// Variables
			global $request_type;
			$sPathResources = ($request_type == 'SSL' ? HTTPS_SERVER : HTTP_SERVER) . DIR_WS_CATALOG . 'includes/modules/debugbar';
			$nTimeSql = 0;
			$sHtmlSql = '';

			// Calculamos el tiempo que ha tardado la pagina en procesar
			$nStart = explode( ' ', $this->start );
    		$nEnd = explode( ' ', microtime() );
    		$nTimePage = number_format( $nEnd[1] + $nEnd[0] - ($nStart[1] + $nStart[0]), 3 );

			// Obtenemos la salida actual
			$sContenido = ob_get_contents();

			// Obtenemos el tiempo que se ha tardado en todos los SQL y el html de las consultas
			foreach( $this->sql as $aSql )
			{
				$sHtmlSql .= '<div class="debug_bar_stitl"><span class="fa fa-clock-o"></span>' . $aSql['time'] . 'ms</div>';
				$sHtmlSql .= '<div class="degub_bar_togg">';
					$sHtmlSql .= '<pre><code class="sql">';
						$sHtmlSql .= $aSql['sql'];
					$sHtmlSql .= '</pre></code>';
				$sHtmlSql .= '</div>';

				// Vamos sumando
				$nTimeSql += $aSql['time'];
			}

			// Codigo
			$nCode = 200;

			// Debugbar
			echo '<div id="debug_bar" class="open">';
				echo '<div class="debug_bar_close"><span class="fa fa-times"></span></div>';
				echo '<div class="debug_bar_vrsn"><img src="' . $sPathResources . '/images/logo.jpg"/> Oscdenox</div>';
				echo '<div class="debug_bar_stas debug_bar_main debug_bar_stas' . $nCode . '"><div>' . $nCode . '</div></div>';
				echo '<div class="debug_bar_time debug_bar_main"><div><span class="fa fa-clock-o"></span>' . $nTimePage . 'ms</div></div>';
				echo '<div class="debug_bar_size debug_bar_main"><div><span class="fa fa-file-o"></span>' . number_format( strlen($sContenido) / 1024, 2 ) . 'kb</div></div>';
				echo '<div class="debug_bar_php debug_bar_main debug_bar_tab">';
					echo '<div class="debug_bar_bton"><span class="fa fa-life-ring"></span>Php ' . preg_replace( '/-.+$/', '', phpversion() ) . '</div>';
					echo '<div class="debug_bar_cntd">';
						echo '<div id="phpinfo" style="display: none;"">' . htmlentities( $this->phpinfo() ) . '</div><iframe id="phpinfo-iframe"></iframe>';
					echo '</div>';
				echo '</div>';
				echo '<div class="debug_bar_vars debug_bar_main debug_bar_tab">';
					echo '<div class="debug_bar_bton"><span class="fa fa-flask"></span>Variables</div>';
					echo '<div class="debug_bar_cntd">';
						echo '<div class="debug_bar_titl">Variables</div>';
						echo '<div class="debug_bar_ovfl">';
							echo '<div class="debug_bar_stitl"><span class="fa fa-flask"></span>SESSION</div>';
							echo '<div class="degub_bar_togg">';
								echo $this->vardump( $_SESSION );
							echo '</div>';
							echo '<div class="debug_bar_stitl"><span class="fa fa-flask"></span>COOKIE</div>';
							echo '<div class="degub_bar_togg">';
								echo $this->vardump( $_COOKIE );
							echo '</div>';
							echo '<div class="debug_bar_stitl"><span class="fa fa-flask"></span>POST</div>';
							echo '<div class="degub_bar_togg">';
								echo $this->vardump( $_POST );
							echo '</div>';
							echo '<div class="debug_bar_stitl"><span class="fa fa-flask"></span>GET</div>';
							echo '<div class="degub_bar_togg">';
								echo $this->vardump( $_GET );
							echo '</div>';
							echo '<div class="debug_bar_stitl"><span class="fa fa-flask"></span>Variables generadas</div>';
							echo '<div class="degub_bar_togg">';
								extract($GLOBALS);
								echo $this->vardump( array_keys( get_defined_vars() ) );
							echo '</div>';
						echo '</div>';
					echo '</div>';
				echo '</div>';
				echo '<div class="debug_bar_data debug_bar_main debug_bar_tab">';
					echo '<div class="debug_bar_bton"><span class="fa fa-database"></span>' . count( $this->sql ) . ' en ' . $nTimeSql . 'ms</div>';
					echo '<div class="debug_bar_cntd">';
						echo '<div class="debug_bar_titl">Consultas SQL</div>';
						echo '<div class="debug_bar_ovfl">';
							echo $sHtmlSql;
						echo '</div>';
					echo '</div>';
				echo '</div>';
			echo '</div>';

			// CSS y JS
			echo '<link rel="stylesheet" type="text/css" href="' .  $sPathResources . '/css/style.css"/>';
			echo '<link rel="stylesheet" type="text/css" href="' .  $sPathResources . '/css/zenburn.css"/>';
			echo '<script src="' .  $sPathResources . '/js/highlight.pack.js" type="text/javascript"></script>';
			echo '<script src="' .  $sPathResources . '/js/javascript.js" type="text/javascript"></script>';
		}
	}
?>
