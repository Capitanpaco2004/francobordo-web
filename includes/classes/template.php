<?php
	// Alias
	namespace util;

	// Util
	use util\tools;

	/**
	* Clase donde se encuentra herramientas para el template
	*/
	class template
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
		* Incluye un archivo template
		* @param string $sFile Archivo a cargar
		* @param array $aVars Variables del template
		*/
		public function includeFile($sFile, $aVars = array())
		{
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
		* Incluye un archivo template con la plantilla que se le diga
		* @param string $sFile Archivo a cargar
		* @param array $aVars Variables del template
		* @param array $aBlocks Bloques a remplazar dentro del template
		*/
		public function includeTemplate($sFile, $aVars = array(), $aBlocks = array())
		{
			// Variables
			$sExtends = '';

			// Obtenemos
			$sHtmlContent = $this->includeFile( $sFile, array_merge( $GLOBALS, $aVars ) );

			// Si contenemos bloques remplazamos sus valores
			if( count( $aBlocks ) > 0 )
			{
				foreach( $aBlocks as $key => $sValue )
					$sHtmlContent = str_replace( '{% block ' . $key . ' %}{% endblock %}', $aBlocks[$key], $sHtmlContent );
			}

			// Eliminamos bloques que no han podido ser remplazados para que no nos de problemas
			$sHtmlContent = preg_replace( '/{% block [a-z._\']+ %}{% endblock %}/', '', $sHtmlContent );

			// Obtenemos las estructuras
			preg_match_all('/{% (?P<function>\w+) (?P<variable>[a-z0-9\/\-:._\']+) (?P<arguments>.+ )?%}/', $sHtmlContent, $aMatches);

			// Si contenemos estructuras
			foreach( $aMatches['function'] as $sKey => $sFunction )
			{
				$sVariable = $aMatches['variable'][$sKey];

				switch($sFunction)
				{
					case 'include':
						$jsonVars = '';

						if ($aMatches['arguments'][$sKey] && tools::validate('Json', trim($aMatches['arguments'][$sKey]))){
							$jsonVars = json_decode($aMatches['arguments'][$sKey], true);
							$aVars = array_merge( $aVars, $jsonVars );
							$jsonVars = ' ' . trim($aMatches['arguments'][$sKey]);
						}

						$sHtmlContent = str_replace( '{% include ' . $sVariable . $jsonVars . ' %}', $this->includeTemplate($sVariable, array_merge( $GLOBALS, $aVars ), $aBlocks ), $sHtmlContent);
					break;

					case 'extends':
						// Quitamos del contenido
						$sHtmlContent = str_replace( '{% extends ' . $sVariable . ' %}', '', $sHtmlContent);

						// Si comienza con dos puntos sustituimos por el directorio de la app
						$sVariable = preg_replace( '/^::/', DIR_THEME . 'html/templates/', $sVariable);

						// Guardamos template
						$sExtends = $sVariable;
					break;

					case 'block':
						// Obtenemos el valor
						preg_match( '#{% block ' . $sVariable . ' %}([\w\W]*?){% endblock %}#', $sHtmlContent, $aAux );

						// Guardamos
						$aBlocks[$sVariable] = $aAux[1];

						// Quitamos del contenido
						$sHtmlContent = str_replace( $aAux[0], '', $sHtmlContent);
					break;
				}
			}

			// Si contenemos template que descienda
			if( $sExtends != '' )
				$sHtmlContent .= $this->includeTemplate( $sExtends, array_merge( $GLOBALS, $aVars ), $aBlocks );

			// Retornamos eliminando lineas vacias
			return trim( preg_replace( "/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n",  $sHtmlContent ) );
		}
	}
?>
