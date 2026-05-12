<?php
	// Util
	use util\arrays;
	use util\tools;

	/**
	* Clase que controla los errores de la aplicación
	*/
	class errorBacktrace
	{
		/**
		* Contiene el directorio raiz del proyecto
		*/
		private $sPathRoot;

		/**
		* Contiene el html de errores de php
		*/
		private static $htmlErrorPhp = array();

		/**
		* Instancia de la clase
		* @var object
		*/
		private static $instance;

		/**
		* Retorna la instancia del objeto en uso, si no esta creada la crea
		* @var object
		*/
		public static function getInstance($sTypeError = E_ALL)
		{
			if( !(self::$instance instanceof self) )
				self::$instance = new self( $sTypeError );

			return self::$instance;
		}

		/**
		* Constructor de la clase
		*/
		public function __construct($sTypeError = E_ALL)
		{
			// Guardamos el directorio raiz
			$this->sPathRoot = getcwd();

			// Mostrar errores
			ini_set( 'display_errors', 1 );
			error_reporting( $sTypeError );

			// Añadimos que cuando se termine el script muestre errores si procede
			register_shutdown_function( array( $this, 'show' ) );

			// Interpretar errores php
			set_error_handler( array( $this, 'errorPhp'), $sTypeError );

			// Interpretar excepciones
			set_exception_handler( array( $this, 'exception' ) );

			// Activa el almacenamiento en búfer de la salida
			ob_start();
		}

		/**
		* Interpreta las excepciones php y los va guardando para mostrar información
		*/
		public static function exception($exception)
		{
  			self::$htmlErrorPhp[] = array(
				'title' => $exception->getMessage(),
				'subtitle' => 'Error en la línea ' . $exception->getLine() . ' en el archivo ' . $exception->getFile(),
				'class' => 'error',
				'backtrace' => self::backtrace(false)
			);
		}

		/**
		* Interpreta los errores PHP y los va guardando para mostrar información
		*/
		public static function errorPhp($errno, $errstr, $errfile, $errline)
		{
			// Este código de error no está incluido en error_reporting con lo cual retornamos false para que salga los errores de php
		    if( !(error_reporting() & $errno) )
		        return false;

			// Segun el tipo de error
		    switch( $errno )
		    {
				case E_USER_ERROR:
					$sClass = 'error';
				break;

				case E_USER_WARNING:
					$sClass = 'warning';
				break;

				case E_USER_NOTICE:
					$sClass = 'notice';
				break;

				default:
					$sClass = 'unknown';
				break;
			}

			// Guardamos
			self::$htmlErrorPhp[] = array(
				'title' => $errstr,
				'subtitle' => 'Error en la línea ' . $errline . ' en el archivo ' . $errfile,
				'class' => $sClass,
				'backtrace' => self::backtrace()
			);

		    // No ejecutar el gestor de errores interno de PHP
		    return true;
		}


		/**
		* Obtiene los errores php
		*/
		private static function backtrace($bArgs = true)
		{
			// Variables
			$sReturn = '';
			$nCont = -1;

			// Recorremos el debug
        	foreach( debug_backtrace() as $aDebug )
        	{
     			$nCont++;

          		// No incluirse a si misma
          		if( $nCont == 0 )
          			continue;

				$sText = '  ' . '[' . $nCont . '] ';

            	if( isset( $aDebug['file'] ) )
             		$sText .= basename( $aDebug['file'] ) . ':' . $aDebug['line'];
            	else
             		$sText .= '[PHP callback]';

            	$sText .= ' -- ';

            	if( isset( $aDebug['class'] ) )
            		$sText .= $aDebug['class'] . $aDebug['type'];

            	$sText .= $aDebug['function'];

            	if( isset($aDebug['args'] ) && count( $aDebug['args'] ) > 0 )
					$sText .= '(' . ( ($bArgs) ? arrays::implodeRecursive($aDebug['args']) : '...') . ')';
            	else
	            	$sText .= '()';

            	$sReturn .= '<p>' . $sText . '</p>';
        	}

        	// Retornamos
			return $sReturn;
 		}

 		/**
 		* Devuelve la estructura del email para enviar el error por correo
 		* @param int $nPosition
 		* @param int $nTotal
 		* @param string $sTitle
 		* @param string $sSubtitle
 		* @param string $sText
 		*/
 		private function showStructureEmail($nPosition, $nTotal, $sTitle, $sSubtitle, $sText)
 		{
			$sEmail = '<tr>';
				$sEmail .= '<td>';
					$sEmail .= '<table width="100%" align="left"  border="0" cellpadding="0" cellspacing="0" style="background-color: #dadada;">';
						$sEmail .= '<tr>';
							$sEmail .= '<td colspan="3" height="10" style="line-height: 10px">&nbsp;</td>';
						$sEmail .= '</tr>';
						$sEmail .= '<tr>';
							$sEmail .= '<td width="10">&nbsp;</td>';
							$sEmail .= '<td>';
								$sEmail .= '<table width="100%" align="left"  border="0" cellpadding="0" cellspacing="0" style="background-color: #dadada;">';
									$sEmail .= '<tr>';
										$sEmail .= '<td width="80">';
											$sEmail .= '<table width="100%" align="left"  border="0" cellpadding="0" cellspacing="0" style="background-color: #fff;">';
												$sEmail .= '<tr>';
													$sEmail .= '<td height="10" style="line-height: 10px">&nbsp;</td>';
												$sEmail .= '</tr>';
												$sEmail .= '<tr>';
													$sEmail .= '<td align="center">';
														$sEmail .= '<b style="font-family: Arial, Helvetica, sans-serif;  font-size: 26px; color: #666; line-height: 26px;">' . $nPosition . '/' . $nTotal . '</b>';
													$sEmail .= '</td>';
												$sEmail .= '</tr>';
												$sEmail .= '<tr>';
													$sEmail .= '<td height="10" style="line-height: 10px">&nbsp;</td>';
												$sEmail .= '</tr>';
											$sEmail .= '</table>';
										$sEmail .= '</td>';
										$sEmail .= '<td width="10">&nbsp;</td>';
										$sEmail .= '<td>';
											$sEmail .= '<p style="font-family: Arial, Helvetica, sans-serif; font-size: 20px; color: #666; line-height: 20px; text-align: left; margin:0px;">' . $sTitle . '</p>';
											$sEmail .= '<p style="font-family: Arial, Helvetica, sans-serif; font-size: 15px; color: #666; line-height: 15px; text-align: left; margin:0px;">' . $sSubtitle . '</p>';
										$sEmail .= '</td>';
									$sEmail .= '</tr>';
								$sEmail .= '</table>';
							$sEmail .= '</td>';
							$sEmail .= '<td width="10">&nbsp;</td>';
						$sEmail .= '</tr>';
						$sEmail .= '<tr>';
							$sEmail .= '<td colspan="3" height="10" style="line-height: 10px">&nbsp;</td>';
						$sEmail .= '</tr>';
					$sEmail .= '</table>';
				$sEmail .= '</td>';
			$sEmail .= '</tr>';
			$sEmail .= '<tr>';
				$sEmail .= '<td>';
					$sEmail .= '<table width="100%" align="left"  border="0" cellpadding="0" cellspacing="0" style="background-color: #fff;">';
						$sEmail .= '<tr>';
							$sEmail .= '<td colspan="3" height="10" style="line-height: 10px">&nbsp;</td>';
						$sEmail .= '</tr>';
						$sEmail .= '<tr>';
							$sEmail .= '<td width="10">&nbsp;</td>';
							$sEmail .= '<td>';
								$sEmail .= '<font style="font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #666; line-height: 25px; text-align: left; margin:0px;">' . $sText . '</font>';
							$sEmail .= '</td>';
							$sEmail .= '<td width="10">&nbsp;</td>';
						$sEmail .= '</tr>';
						$sEmail .= '<tr>';
							$sEmail .= '<td colspan="3" height="10" style="line-height: 10px">&nbsp;</td>';
						$sEmail .= '</tr>';
					$sEmail .= '</table>';
				$sEmail .= '</td>';
			$sEmail .= '</tr>';

			// Retornamos
			return $sEmail;
 		}

		/**
		 * Verifica si una dirección IP se encuentra dentro de un rango de IPs o es igual a una IP estática.
		 *
		 * @param string $ip Dirección IP que se desea verificar.
		 * @param string $range Rango de direcciones IP o IP estática.
		 *   Ejemplos de rangos válidos: "192.168.1.0/24", "10.0.0.1/32".
		 *   Ejemplos de IP estática válida: "192.168.1.1".
		 *
		 * @return bool Retorna `true` si la dirección IP se encuentra dentro del rango o es igual a la IP estática,
		 *              de lo contrario retorna `false`.
		 */
		public static function checkIPRange($ip, $range) {
			if (strpos($range, '/') === false) {
				$range .= '/32';
			}

			list($range, $netmask) = explode('/', $range, 2);
			$ip_decimal = ip2long($ip);
			$range_decimal = ip2long($range);
			$wildcard_decimal = pow(2, (32 - $netmask)) - 1;
			$netmask_decimal = ~ $wildcard_decimal;

			return (($ip_decimal & $netmask_decimal) == ($range_decimal & $netmask_decimal));
		}

		/**
		* Al terminar la aplicación se lanzara este método
		*/
		public function show()
		{
			// Cambiamos directorio al raiz porque el algunas ocasiones se va al directorio /sys/
			chdir( $this->sPathRoot );

			// Variables
			global $request_type;
			$nPhpErrorTotal = count( self::$htmlErrorPhp );
			$sPathResources = ($request_type == 'SSL' ? HTTPS_SERVER : HTTP_SERVER) . DIR_WS_CATALOG . '';
			$sIPRanges = DEBUGBAR_IPS_ALLOWED != '' ? explode("\n", DEBUGBAR_IPS_ALLOWED) : array();
			$bDebug = false;

			foreach ($sIPRanges as $range) {
				if ($this->checkIPRange($_SERVER['REMOTE_ADDR'], $range)) {
					$bDebug = true;
					break;
				}
			}

			// Si contenemos errores PHP
			if( $nPhpErrorTotal > 0 )
			{
				// Eliminamos toda salida
				ob_end_clean();

				// Si no estamos en modo debug mostramos error 500 y enviamos email
				if( !$bDebug )
				{
					$sEmail = '<html>';
						$sEmail .= '<head>';
					       $sEmail .= '<title>Denox - Error</title>';
					        $sEmail .= '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />';
					        $sEmail .= '<meta name="language" content="es" />';
						$sEmail .= '</head>';
						$sEmail .= '<body style="margin:0; padding:0;background-color:#f6f6f6">';
							$sEmail .= '<table width="100%" align="center"  border="0" cellpadding="0" cellspacing="0">';
								$sEmail .= '<tr>';
									$sEmail .= '<td>&nbsp;</td>';
									$sEmail .= '<td width="900" align="left" valign="top" style="vertical-align:top; width: 900;">';
										$sEmail .= '<table width="100%" align="center"  border="0" cellpadding="0" cellspacing="0">';
											$sEmail .= '<tr>';
												$sEmail .= '<td height="10" style="line-height: 10px">&nbsp;</td>';
											$sEmail .= '</tr>';
											$sEmail .= '<tr>';
												$sEmail .= '<td align="center">';
													$sEmail .= '<img style="display:block; border: 0;" border="0" src="' . $sPathResources . '/theme/web/logo-trans.png" />';
													$sEmail .= '<p style="font-family: Arial, Helvetica, sans-serif; font-size: 22px; color: #666; font-weight: bold; line-height: 50px; text-align: center; margin: 0px;">Pharaonix - Ups, parece que algo salió mal</p>';
													$sEmail .= '<p style="font-family: Arial, Helvetica, sans-serif; font-size: 15px; color: #a9a9a9; line-height: 30px; text-align: justify; margin:0px;"><b>Hora:</b> ' . date( 'd/m/Y H:i:s' ) . '</p>';
													$sEmail .= '<p style="font-family: Arial, Helvetica, sans-serif; font-size: 15px; color: #a9a9a9; line-height: 30px; text-align: justify; margin:0px;"><b>IP cliente:</b> ' . $_SERVER['REMOTE_ADDR'] . '</p>';
													$sEmail .= '<p style="font-family: Arial, Helvetica, sans-serif; font-size: 15px; color: #a9a9a9; line-height: 30px; text-align: justify; margin:0px;"><b>IP Server:</b> ' . $_SERVER['SERVER_ADDR'] . '</p>';
													$sEmail .= '<p style="font-family: Arial, Helvetica, sans-serif; font-size: 15px; color: #a9a9a9; line-height: 30px; text-align: justify; margin:0px;"><b>User-Agent:</b> ' . $_SERVER['HTTP_USER_AGENT'] . '</p>';
													$sEmail .= '<p style="font-family: Arial, Helvetica, sans-serif; font-size: 15px; color: #a9a9a9; line-height: 30px; text-align: justify; margin:0px;"><b>Domino:</b> ' . $_SERVER['HTTP_HOST'] . '</p>';
													$sEmail .= '<p style="font-family: Arial, Helvetica, sans-serif; font-size: 15px; color: #a9a9a9; line-height: 30px; text-align: justify; margin:0px;"><b>Url:</b> ' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '</p>';
													$sEmail .= '<p style="font-family: Arial, Helvetica, sans-serif; font-size: 15px; color: #a9a9a9; line-height: 30px; text-align: justify; margin:0px;"><b>Referido:</b> ' . arrays::getValueByKey( $_SERVER, 'HTTP_REFERER' ) . '</p>';
												$sEmail .= '</td>';
											$sEmail .= '</tr>';
											$sEmail .= '<tr>';
												$sEmail .= '<td height="10" style="line-height: 10px">&nbsp;</td>';
											$sEmail .= '</tr>';

											// Total de errores php
											$nPhpErrorTotalResta = $nPhpErrorTotal;

											// Recorremos errores php
											foreach( array_reverse( self::$htmlErrorPhp ) as $aError )
											{
												$sEmail .= $this->showStructureEmail( $nPhpErrorTotalResta, $nPhpErrorTotal, $aError['title'], $aError['subtitle'], $aError['backtrace'] );
												$nPhpErrorTotalResta--;
											}

											$sEmail .= '<tr>';
												$sEmail .= '<td height="10" style="line-height: 10px">&nbsp;</td>';
											$sEmail .= '</tr>';
										$sEmail .= '</table>';
									$sEmail .= '</td>';
									$sEmail .= '<td>&nbsp;</td>';
								$sEmail .= '</tr>';
							$sEmail .= '</table>';
						$sEmail .= '</body>';
					$sEmail .= '</html>';

					// Si tenemos email enviamos
					if( ERROR_BACKTRACE_EMAIL != '' )
					{
						$sEmails = explode( "\n", ERROR_BACKTRACE_EMAIL );

						if( count( $sEmails ) > 0 )
						{
							foreach( $sEmails as $sMail )
								tep_mail( STORE_OWNER, $sMail, TITLE . ' - [DEBUG_OSCDENOX] Archivo: ' . $_SERVER['SCRIPT_NAME'], $sEmail, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
						}
					}

					// Redireccionamos a que sea un error
					include( 'includes/modules/500/index.php' );
					exit();
				}

				// Cabecera 500
				header( $_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500 );

				echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">';
				echo '<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="es-ES">';
					echo '<head>';
						echo '<title>Error Pharaonix</title>';
						echo '<meta name="robots" content="noindex, nofollow" />';
						echo '<style>
							HTML, BODY, DIV, SPAN, APPLET, OBJECT, IFRAME, H1, H2, H3, H4, H5, H6, P, BLOCKQUOTE, PRE, A, ABBR, ACRONYM, ADDRESS, BIG, CITE, CODE, DEL, DFN, EM, FONT, IMG, INS, KBD, Q, S, SAMP, SMALL, STRIKE, STRONG, SUB, SUP, TT, VAR, DL, DT, DD, OL, UL, LI, FIELDSET, FORM, LABEL, LEGEND, CAPTION
							{
								margin: 0;
								padding: 0;
								border: 0;
								outline: 0px solid #000000;
								font-weight: inherit;
								font-style: inherit;
								font-size: 100%;
								font-family: inherit;
								vertical-align: baseline;
								line-height: 13px;
								font-size: 13px;
							}

							*:focus
							{
							    outline: 0;
							}

							:focus
							{
								outline: 0px solid #000000;
							}
							BODY
							{
								margin: 0px;
								padding: 0px;
								font-family: sans-serif;
								font-size: 12px;
								-x-system-font: none;
								font-size-adjust: none;
								font-stretch: normal;
								font-style: normal;
								font-variant: normal;
								font-weight: normal;
								line-height: 18px;
								padding: 20px;
							}
							OL, UL
							{
								list-style: none;
							}
							TABLE
							{
								border-collapse: separate;
								border-spacing: 0;
							}
							CAPTION, TH, TD
							{
								font-weight: normal;
							}
							BLOCKQUOTE:before, BLOCKQUOTE:after, Q:before, Q:after
							{
								content: "";
							}
							BLOCKQUOTE, Q
							{
								quotes: """";
							}
							TEXTAREA
							{
								resize: none;
							}

							select
							{
								max-width: 100px;
							}

							body, html
							{
								min-width: 320px;
							    background: #efefef;
							}

							*, :after, :before
							{
								-webkit-box-sizing: border-box;
								-moz-box-sizing: border-box;
								-ms-box-sizing: border-box;
								box-sizing: border-box;
								-webkit-text-size-adjust: none;
							}

							#logo
							{
								display: block;
								margin: 0px auto;
							}

							#titl
							{
							    font-size: 22px;
							    font-weight: bold;
							    line-height: 22px;
							    margin: 11px 0 40px;
							    text-align: center;
							}

							.cntd
							{
								max-width: 962px;
								width: 100%;
								margin: 0px auto 20px;
							}

							.cntd .titl
							{
							    padding: 20px 20px 20px 120px;
							    position: relative;
							}

							.cntd .titl .t0
							{
							    position: absolute;
							    left: 20px;
							    top: 16px;
							    font-size: 26px;
							    color: #666;
							    line-height: 26px;
							    background: #FFF;
							    padding: 10px;
							    font-weight: bold;
							}

							.cntd .titl .t1
							{
								font-weight: bold;
								line-height: 20px;
								font-size: 20px;
								margin-bottom: 5px;
							}

							.cntd .titl .t2
							{
							    font-size: 14px;
							    line-height: 14px;
							    color: #666;
							}

							.cntd .back
							{
								background: #FFF;
								word-wrap: break-word;
								padding: 20px;
							}

							.cntd .back p
							{
								color: #777777;
								font-size: 14px;
								line-height: 25px;
							}

							.unknown{background: #dadada;}
							.error{background: #f3d1d1;}
						</style>';
					echo '</head>';
					echo '<body>';
						echo '<img id="logo" src="' . $sPathResources . '/theme/web/logo-trans.png" />';
						echo '<div id="titl">' . TITLE . ' - Ups, parece que algo salió mal</div>';

						// Total de errores php
						$nPhpErrorTotalResta = $nPhpErrorTotal;

						// Recorremos errores
						foreach( array_reverse( self::$htmlErrorPhp ) as $aError )
						{
							echo '<div class="cntd">';
								echo '<div class="titl ' . $aError['class'] . '">';
									echo '<div class="t0">' . $nPhpErrorTotalResta . '/' . $nPhpErrorTotal . '</div>';
									echo '<div class="t1">' . $aError['title'] . '</div>';
									echo '<div class="t2">' . $aError['subtitle'] . '</div>';
								echo '</div>';
								echo '<div class="back">';
									echo $aError['backtrace'];
								echo '</div>';
							echo '</div>';

							$nPhpErrorTotalResta--;
						}

				    echo '</body>';
				echo '</html>';

				// Detenemos
				exit(0);
			}
		}
	}
?>
