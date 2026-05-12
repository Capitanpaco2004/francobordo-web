<?php
	// Alias
	namespace util;

	// Util
	use util\arrays;
	use util\tools;

	/**
	* Clase donde se encuentra herramientas para el sistema de emails
	*/
	class mail
	{
		/**
		* Directorio donde se encuentra los emails
		* @var string
		*/
		public $pathEmail;

		/**
		* Url del email
		* @var string
		*/
		public $urlEmail;

		/**
		* Html del email
		* @var string
		*/
		public $html = '';

		/**
		 * Url base del sistema
		 * @var string
		 */
		public $url;
		
		/**
		* Constructor de la clase
		*/
		public function __construct($aArguments = array())
		{
			// Variables
			global $request_type;
			
			// Directorio donde se encuentra los emails
			$this->pathEmail = 'includes/modules/email';
			
			// Url del email
			$this->urlEmail = preg_replace( '/\/$/i', '', ($request_type == 'SSL' ? HTTPS_SERVER : HTTP_SERVER) . DIR_WS_CATALOG . $this->pathEmail );
			$this->url = preg_replace( '/\/$/i', '', ($request_type == 'SSL' ? HTTPS_SERVER : HTTP_SERVER) . DIR_WS_CATALOG );
		}

		/**
		* Envia email
		* <code>
		* $aArguments = array(
		*   'to_name'    => Nombre de la persona que recibira el correo
		*   'to_email'   => Email de la persona que recibira el correo
		*   'from_name'  => Nombre que envia el correo
		*   'from_email' => Email que envia el correo
		*   'subject'    => Asunto
		*   'attachment' => array( 'nombre archivo', 'archivo' )
		* );
		* </code>
		*/
		public function send($aArguments = array())
		{
			// Guardamos texto
			$aArguments['text'] = $this->html;

			// Enviamos email
			tools::mail( $aArguments );
		}

		/**
		* Incluye un archivo email
		* @param string $sFile Archivo a cargar
		* @param array $aVars Variables del email
		*/
		public function includeFile($sFile, $aVars = array())
		{			
			// Extraemos las variables para el email
			extract( $aVars );

			// Almacenamos la salida hasta aqui para obtener solo el contenido a incluir
			ob_start();

			// Incluimos el archivo
			include( realpath( __DIR__ . '/../..' ) . '/' . $this->pathEmail . '/' . $sFile );

			// Contenido obtenido
			$sHtmlContent = ob_get_contents();

			// Continuamos con la salida por donde ibamos
			ob_end_clean();

			// Retornamos
			return $sHtmlContent;
		}

		/**
		* Incluye un archivo email con la plantilla que se le diga
		* @param string $sFile Archivo a cargar
		* @param array $aVars Variables del email
		* @param array $aBlocks Bloques a remplazar dentro del email
		*/
		public function includeEmail($sFile, $aVars = array(), $aBlocks = array())
		{
			// Variables
			$sExtends = '';

			// Obtenemos
			$this->html = $this->includeFile( $sFile, $aVars );

			// Si contenemos bloques remplazamos sus valores
			if( count( $aBlocks ) > 0 )
			{
				foreach( $aBlocks as $key => $sValue )
					$this->html = str_replace( '{% block ' . $key . ' %}{% endblock %}', $aBlocks[$key], $this->html );
			}

			// Eliminamos bloques que no han podido ser remplazados para que no nos de problemas
			$this->html = preg_replace( '/{% block [a-z._\']+ %}{% endblock %}/', '', $this->html );

			// Obtenemos las estructuras
			preg_match_all('/{% (?P<function>\w+) (?P<variable>[a-z:._\']+) %}/', $this->html, $aMatches);

			// Si contenemos estructuras
			foreach( $aMatches['function'] as $sKey => $sFunction )
			{
				$sVariable = $aMatches['variable'][$sKey];

				switch($sFunction)
				{
					case 'extends':
						// Quitamos del contenido
						$this->html = str_replace( '{% extends ' . $sVariable . ' %}', '', $this->html);

						// Guardamos email
						$sExtends = $sVariable;
					break;

					case 'block':
						// Obtenemos el valor
						preg_match( '#{% block ' . $sVariable . ' %}([\w\W]*?){% endblock %}#', $this->html, $aAux );

						// Guardamos
						$aBlocks[$sVariable] = $aAux[1];

						// Quitamos del contenido
						$this->html = str_replace( $aAux[0], '', $this->html);
					break;
				}
			}

			// Si contenemos email que descienda
			if( $sExtends != '' )
				$this->html .= $this->includeEmail( $sExtends, array_merge( $GLOBALS, $aVars ), $aBlocks );

			// Guardamos eliminando lineas vacias
			$this->html = trim( preg_replace( "/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n",  $this->html ) );
		}
	}
?>