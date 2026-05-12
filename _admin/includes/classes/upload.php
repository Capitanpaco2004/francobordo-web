<?php
	use util\arrays as arrays;

	/**
	* Crea input file
	*/
	class inputFile
	{
		// Archivo subido con exito
 		const UPLOAD_ERR_OK = 0;

 		// El fichero subido excede la directiva upload_max_filesize
 		const UPLOAD_ERR_INI_SIZE = 1;

 		// El fichero subido excede la directiva max_file_uploads
 		const UPLOAD_ERR_FORM_SIZE = 2;

		// El fichero fue sólo parcialmente subido
		const UPLOAD_ERR_PARTIAL = 3;

		// No se subió ningún fichero
		const UPLOAD_ERR_NO_FILE = 4;

		/**
		* Nombre identificativo dentro del array $_FILES
		* @var string
		*/
		private $name = '';

		/**
		* Añadir extension automaticamente
		* @var bool
		*/
		private $extension = true;

		/**
		* Si está a true eliminara la imagen con mismo nombre si la encuentra antes de subir la nueva
		* @var bool
		*/
		private $delete = true;

		/**
		* Tamaño maximo de datos en el post
		* @var int
		*/
		public $postMaxSize;

		/**
		* Tamaño maximo que se le permite a un archivo
		* @var int
		*/
		public $uploadMaxFileSize;

		/**
		* Maximo permitido de archivos subidos en una sentada
		* @var int
		*/
		public $maxFileUploads;

		/**
		* Tipos de archivos permitidos
		* @var array
		*/
		public $mimeType;

		/**
		* Tipos de extensiones permitidas
		* @var array
		*/
		public $fileExtension;

		/**
		* Directorio donde se subira la imagen
		* @var string
		*/
		public $pathUpload;

		/**
		* Nombre del archivo final
		* @var string
		*/
		public $fileName;

		/**
		* Si es requerida una imagen
		* @var bool
		*/
		public $required;

		/**
		* Tamaño de la imagen permitido
		* @var array
		*/
		public $dimension;

		/**
		* Mensaje error
		* @var string
		*/
		public $error = false;

		/**
		* Url para eliminar la imagen
		* @var string
		*/
		public $UrlDelete = false;

		/**
		* Constructor
		*/
		public function __construct($aArguments = [])
		{
			// Eliminación automatica
			$this->delete = arrays::getValueByKey( $aArguments, 'delete', $this->delete, true );

			// Extension automatica
			$this->extension = arrays::getValueByKey( $aArguments, 'extension', $this->extension, true );

			// Comprobamos si nos han mandado directorio donde se subira
			if (!array_key_exists( 'path_upload', $aArguments )) {
                throw new \Exception( 'Debes especificar el directorio donde se subira el archivo' );
            }

			// Comprobamos si no existe el directorio donde se va a subir
			if (!is_dir( $aArguments['path_upload'] )) {
                throw new \Exception( 'El directorio ' . $aArguments['path_upload'] . ' no existe' );
            }

			// Comprobamos si se puede escribir
			if (!is_writable( $aArguments['path_upload'] )) {
                throw new \Exception( 'El directorio ' . $aArguments['path_upload'] . ' no tiene permiso de escritura' );
            }

			// Comprobamos si se puede escribir
			if (!array_key_exists( 'name', $aArguments )) {
                throw new \Exception( 'Debes especificar un identificador para el array $_FILES' );
            }

			// Propiedades
			$this->name = $aArguments['name'];
			$this->UrlDelete = arrays::getValueByKey( $aArguments, 'url_delete', false );
			$this->postMaxSize = arrays::getValueByKey( $aArguments, 'post_max_size', ini_get( 'post_max_size' ) );
			$this->uploadMaxFileSize = arrays::getValueByKey( $aArguments, 'upload_max_filesize', ini_get( 'upload_max_filesize' ) );
			$this->maxFileUploads = arrays::getValueByKey( $aArguments, 'max_file_uploads', ini_get( 'max_file_uploads' ) );
			$this->mimeType = arrays::getValueByKey( $aArguments, 'mime_type', [ 'image/bmp', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'image/jpeg', 'image/gif', 'image/png', 'image/webp', 'text/plain', 'application/pdf' ] );
			$this->fileExtension = arrays::getValueByKey( $aArguments, 'file_extension', [ 'bmp', 'doc', 'xls', 'jpeg', 'jpg', 'jpe', 'jfif', 'jfi', 'jif', 'gif', 'png', 'txt', 'pdf', 'webp' ] );
			$this->pathUpload = $aArguments['path_upload'];
			$this->fileName = arrays::getValueByKey( $aArguments, 'file_name', false );
			$this->required = arrays::getValueByKey( $aArguments, 'required', false );
			$this->dimension = arrays::getValueByKey( $aArguments, 'dimension', false );

			// Obtenemos el tamaño minimo de las 2 propiedades que tienes que ver con el maximo permitido en post
			$this->postMaxSize = min( $this->convertPHPSizeToBytes( $this->postMaxSize ), $this->convertPHPSizeToBytes( $this->uploadMaxFileSize  ) );
		}

		/**
		* Se lanza para validar
		*/
		public function validate()
		{
			// Elemento file dentro del array de $_FILES
			$file = $_FILES[$this->name];

			// Si no contenemos tmp_name y no es un campo requerido pasamos
			if ($file['tmp_name'] == '' && !$this->required) {
                return true;
            }

			// Comprobamos si el tamaño excede del permitido
			if( $file['size'] > $this->postMaxSize || in_array( $file['error'], [ self::UPLOAD_ERR_INI_SIZE, self::UPLOAD_ERR_FORM_SIZE ] ) )
			{
				$this->error = 'El archivo es demasiado pesado. El tamaño máximo permitido es ' .  $this->formatBytes( $this->postMaxSize ) ;
				return false;
			}

			// Comprobamos si solo se ha podido subir una parte del archivo
			if( $file['error'] == self::UPLOAD_ERR_PARTIAL )
			{
				$this->error = 'El archivo fue sólo subido parcialmente.';
				return false;
			}

			// Comprobamos si es un archivo
			if( !is_file( $file['tmp_name'] ) )
			{
				$this->error = 'No se pudo encontrar el archivo.';
				return false;
			}

			// Comprobamos si se puede leer
			if( !is_readable( $file['tmp_name'] ) )
			{
				$this->error = 'No se puede leer el archivo';
				return false;
			}

			// Si tenemos tamaño permitido
			if( $this->dimension !== false )
			{
				// Tamaño
				$aSize = getimagesize( $file['tmp_name'] );

				// Comprobamos si es mas grande la imagen que lo permitido
				if( $aSize[0] > $this->dimension[0] || $aSize[1] > $this->dimension[1] )
				{
					$this->error = 'La imagen supera el tamaño permitido de ' . $this->dimension[0] . 'x' . $this->dimension[1] . 'PX';
					return false;
				}
			}

			// Mime type del archivo
			$sMimeType = mime_content_type( $file['tmp_name'] );

			// Extensión del archivo
			$sExtension = preg_replace( '/.*\./', '', (string) $file['name'] );

			// Comprobamos el mimetype
			if( !in_array( $sMimeType, $this->mimeType ) )
			{
				$this->error = 'El tipo mime del archivo no es válido (' . $sMimeType . '). Los tipos mime válidos son ' . implode( ', ', $this->mimeType ) . '.';
				return false;
			}

			// Comprobamos extension
			if( !in_array( strtolower( (string) $sExtension ), $this->fileExtension ) )
			{
				$this->error = 'La extensión del archivo no es válida (' . $sExtension . '). Las extensiones validas son ' . implode( ', ', $this->fileExtension ) . '.';
				return false;
			}

			// Retornamos
			return true;
		}

		/**
		* Subir el archivo
		*/
		public function upload()
		{
			// Si hemos tenido errores no realizamos nada
			if ($this->error !== false) {
                return false;
            }

			// Extension
			$sExtension = '';

			// Archivo
			$file = $_FILES[$this->name];

			// Decidimos el nombre del archivo si no nos han especificado ninguno
			if ($this->fileName === false) {
                $sNameFile = $file['name'];
            } else
			{
				// Nombre del archivo
				$sNameFile = $this->fileName;

				// Si no contiene extension
				if( $this->extension && !preg_match( '/\..*/i', $this->fileName ) )
				{
					// Obtenemos su extensión
					$sMime = mime_content_type( $file['tmp_name'] );
					$aMimes = $this->generateUpToDateMimeArray();
					$sExtension .= '.' . $aMimes[$sMime];
				}
			}

			// Si contenemos eliminacion
			if ($this->delete) {
                $this->delete( $this->pathUpload . '/' . $sNameFile );
            }

			// Movemos el archivo a su directorio
			move_uploaded_file( $file['tmp_name'], $this->pathUpload . '/' . $sNameFile . ($this->extension ? $sExtension : '') );
            return null;
		}

		/**
		* Elimina el archivo pasado por argumento
		*/
		private function delete($sFile)
		{
			// Buscamos
			$aAux = glob( $sFile . '.*' );

			// Si existe
			if (count( $aAux ) > 0) {
                unlink( $aAux[0] );
            }
		}

		/**
		* Muestra el input para subir
		*/
		public function show()
		{
			// Variables
			$sHtml = '';
			$bExist = file_exists( $this->pathUpload . '/' . $this->fileName );

			$sHtml .= '<input type="file" name="' . $this->name . '" id="' . $this->name . '"' . ($bExist ? ' data-inner="drop"' : '') . ' data-button-text="' . TEXT_SELECT_FILE . '" />';

			// Si existe la imagen
			if( $bExist )
			{
				$sHtml .= '<div data-value-update="true" class="drop xfselect afixed" style="width: 150px">';
					$sHtml .= '<div>' . TEXT_OPTIONS . '</div>';
					$sHtml .= '<ul class="down down-dngt drch">';
						$sHtml .= '<li><a href="' . preg_replace( '/\/$/i', '', ($request_type == 'SSL' ? HTTPS_SERVER : HTTP_SERVER) ) . DIR_WS_CATALOG . str_replace( realpath( getcwd() . '/../' ), '', realpath( $this->pathUpload ) ) . '/' . $this->fileName . '"><i class="fa fa-eye"></i> ' . TEXT_VIEW_FILE . '</a></li>';

						if ($this->UrlDelete !== false) {
                            $sHtml .= '<li><a href="' . $this->UrlDelete . '"><i class="fa fa-trash-o"></i> ' . TEXT_DELETE_IMAGE . '</a>';
                        }
					$sHtml .= '</ul>';
				$sHtml .= '</div>';
			}

			// Retornamos
			return $sHtml;
		}

		/**
		* Convierte los tamaños de ini_get que te lo devuelve como 2M a bytes
		*/
		private function convertPHPSizeToBytes($sSize)
		{
		    if (is_numeric( $sSize)) {
                return $sSize;
            }

		    $sSuffix = substr( (string) $sSize, -1 );
		    $iValue = substr( (string) $sSize, 0, -1 );

		    switch( strtoupper( $sSuffix ) )
		    {
			    case 'P':
			        $iValue *= 1024;
			    case 'T':
			        $iValue *= 1024;
			    case 'G':
			        $iValue *= 1024;
			    case 'M':
			        $iValue *= 1024;
			    case 'K':
			        $iValue *= 1024;
		        break;
		    }

		    return $iValue;
		}

		/**
		* Devuelve su mime type con su extension
		*/
		private function generateUpToDateMimeArray()
		{
			$return = [];
			$mimes = file_get_contents( 'http://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types' );

			preg_match_all('#^([^\s]{2,}?)\s+(.+?)$#ism', $mimes, $matches, PREG_SET_ORDER);

			foreach ($matches as $match)
			{
				$exts = explode(" ", $match[2]);
				foreach ($exts as $ext)
				{
					if (!array_key_exists( $match[1], $return )) {
                        $return[$match[1]]=$ext;
                    }
				}
			}

			return $return;
		}
	}


	// Clase oscommerce upload image
  class upload
  {
    public $file, $filename, $destination, $permissions, $extensions, $tmp_filename, $message_location, $language = '';
	//BOF SEO images
    public $fabricante, $nombreProducto;
    public $exten;
    public $img_origin;
    public $modificat;
	//EOF SEO images

	//BOF SEO images
    function __construct($file = '', $destination = '', $permissions = '777', $extensions = '', $fabricante = '', $nombreProducto = '') {
	//EOF SEO images
      $this->set_file($file);
      $this->set_destination($destination);
      $this->set_permissions($permissions);
      $this->set_extensions($extensions);
      $this->set_fabricante($fabricante);
      $this->set_nombreProducto($nombreProducto);
      $this->set_mofificat(false);

      $this->set_output_messages('direct');

      if (tep_not_null($this->file) && tep_not_null($this->destination)) {
        $this->set_output_messages('session');

		//BOF SEO images
        if ( ($this->parse($fabricante, $nombreProducto) == true) && ($this->save() == true) ) {
		//EOF SEO images
          return;
        } else {
          return;
        }
      }
    }

	function set_language($sId)
	{
		$this->language = $sId;
	}

	//BOF SEO images
    function parse($fabricante = '', $nombreProducto = '') {
	//EOF SEO images
      global $messageStack;

	  //BOF SEO images
      $this->set_fabricante($fabricante);
      $this->set_nombreProducto($nombreProducto);
	  //EOF SEO images

      if (isset($_FILES[$this->file]))
	  {
		if( $this->language != '' )
		{
			$sName = $_FILES[$this->file]['name'][$this->language];
			$sType = $_FILES[$this->file]['type'][$this->language];
			$sSize = $_FILES[$this->file]['size'][$this->language];
			$sTmpName = $_FILES[$this->file]['tmp_name'][$this->language];
		}
		else
		{
			$sName = $_FILES[$this->file]['name'];
			$sType = $_FILES[$this->file]['type'];
			$sSize = $_FILES[$this->file]['size'];
			$sTmpName = $_FILES[$this->file]['tmp_name'];
		}

        $file = ['name' => $sName,
                      'type' => $sType,
                      'size' => $sSize,
                      'tmp_name' => $sTmpName];
      }
	  elseif (isset($GLOBALS['HTTP_POST_FILES'][$this->file]))
	  {
        global $_FILES;

		if( $this->language != '' )
		{
			$sName = $_FILES[$this->file]['name'][$this->language];
			$sType = $_FILES[$this->file]['type'][$this->language];
			$sSize = $_FILES[$this->file]['size'][$this->language];
			$sTmpName = $_FILES[$this->file]['tmp_name'][$this->language];
		}
		else
		{
			$sName = $_FILES[$this->file]['name'];
			$sType = $_FILES[$this->file]['type'];
			$sSize = $_FILES[$this->file]['size'];
			$sTmpName = $_FILES[$this->file]['tmp_name'];
		}

        $file = ['name' => $sName,
                      'type' => $sType,
                      'size' => $sSize,
                      'tmp_name' => $sTmpName];
      }
	  else
	  {
        $file = ['name' => ($GLOBALS[$this->file . '_name'] ?? ''),
                      'type' => ($GLOBALS[$this->file . '_type'] ?? ''),
                      'size' => ($GLOBALS[$this->file . '_size'] ?? ''),
                      'tmp_name' => ($GLOBALS[$this->file] ?? '')];
      }

      if ( tep_not_null($file['tmp_name']) && ($file['tmp_name'] != 'none') && is_uploaded_file($file['tmp_name']) ) {
        if (count($this->extensions) > 0 && !in_array(strtolower(substr((string) $file['name'], strrpos((string) $file['name'], '.')+1)), $this->extensions)) {
            if ($this->message_location == 'direct') {
              $messageStack->add(ERROR_FILETYPE_NOT_ALLOWED, 'error');
            } else {
              $messageStack->add_session(ERROR_FILETYPE_NOT_ALLOWED, 'error');
            }
            return false;
        }

        $this->set_file($file);

		//BOF SEO images
		$ext = substr ((string) $file['type'], strlen((string) $file['type']) - 4);
		$ext_c = strtolower($ext);
		if ($ext_c === 'jpeg') {
            $ext_c = 'jpg';
        } elseif ($ext_c === '-png') {
            $ext_c = 'png';
        } elseif ($ext_c === '/gif') {
            $ext_c = 'gif';
        } else {
			$ext = substr ((string) $file['type'], strlen((string) $file['type']) - 5);
			$ext_c = strtolower($ext);
		}

		$exten = $ext_c;

		$ant = [" ", ",", "'", '"', ";", "&", "$", "*", "%", "+", "?", "”", "“", "/", "\\", "Á", "á", "É", "é", "Í", "í", "Ó", "ó", "Ú", "ú", "Ñ", "ñ"];
  		$desp= ["-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-",  "a", "a", "e", "e", "i", "i", "o", "o", "u", "u", "n", "n"];

		if ($this->fabricante != '' && $this->nombreProducto != '') {
            $fabricant = str_replace($ant, $desp, $this->fabricante);
            $nomP = str_replace($ant, $desp, $this->nombreProducto);
            $this->set_fabricante($fabricant);
            $this->set_nombreProducto($nomP);
            $this->set_filename($fabricant . '-' . $nomP . '.' . $ext_c);
            if (!$this->modificat) {
				$this->set_img_origin($this->filename);
				$this->set_mofificat(true);
			}
        } elseif ($this->fabricante == '' && $this->nombreProducto != '') {
            $nomP = str_replace($ant, $desp, $this->nombreProducto);
            $this->set_nombreProducto($nomP);
            $this->set_filename($nomP);
            if (!$this->modificat) {
				$this->set_img_origin($this->filename);
				$this->set_mofificat(true);
			}
        } else {
			$this->set_filename($file['name']);

			if (!$this->modificat) {
				$this->set_img_origin($this->filename);
				$this->set_mofificat(true);
			}
		}
		//EOF SEO images

        $this->set_tmp_filename($file['tmp_name']);

        return $this->check_destination();
      } else {
        if ($this->message_location == 'direct') {
          $messageStack->add(WARNING_NO_FILE_UPLOADED, 'warning');
        } else {
          $messageStack->add_session(WARNING_NO_FILE_UPLOADED, 'warning');
        }

        return false;
      }
    }

    function save() {
      global $messageStack;

      if (!str_ends_with((string) $this->destination, '/')) {
          $this->destination .= '/';
      }

		//BOF SEO images
		$num = 0;
		while(file_exists($this->destination . $this->filename)) {
			$this->set_filename($num . '_' . $this->img_origin);
			$num++;
		}
		//EOF SEO images

      if (move_uploaded_file($this->file['tmp_name'], $this->destination . $this->filename)) {
        chmod($this->destination . $this->filename, $this->permissions);

        if ($this->message_location == 'direct') {
          $messageStack->add(SUCCESS_FILE_SAVED_SUCCESSFULLY, 'success');
        } else {
          $messageStack->add_session(SUCCESS_FILE_SAVED_SUCCESSFULLY, 'success');
        }

        return true;
      } else {
        if ($this->message_location == 'direct') {
          $messageStack->add(ERROR_FILE_NOT_SAVED, 'error');
        } else {
          $messageStack->add_session(ERROR_FILE_NOT_SAVED, 'error');
        }

        return false;
      }
    }

    function set_file($file) {
      $this->file = $file;
    }

    function set_destination($destination) {
      $this->destination = $destination;
    }

    function set_permissions($permissions) {
      $this->permissions = octdec((string) $permissions);
    }

    function set_filename($filename) {
      $this->filename = $filename;
    }

    function set_tmp_filename($filename) {
      $this->tmp_filename = $filename;
    }

    function set_extensions($extensions) {
      if (tep_not_null($extensions)) {
          $this->extensions = is_array($extensions) ? $extensions : [$extensions];
      } else {
        $this->extensions = [];
      }
    }

	//BOF SEO images
    function set_fabricante($fabricante) {
      $this->fabricante = $fabricante;
    }

    function set_nombreProducto($nombreProducto) {
      $this->nombreProducto = $nombreProducto;
    }

    function set_exten($exten) {
      $this->exten = $exten;
    }

    function set_img_origin($img_origin) {
      $this->img_origin = $img_origin;
    }

    function set_mofificat($modificat) {
      $this->modificat = $modificat;
    }
	//EOF SEO images

    function check_destination() {
      global $messageStack;

      if (!is_writable($this->destination)) {
        if (is_dir($this->destination)) {
            if ($this->message_location == 'direct') {
              $messageStack->add(sprintf(ERROR_DESTINATION_NOT_WRITEABLE, $this->destination), 'error');
            } else {
              $messageStack->add_session(sprintf(ERROR_DESTINATION_NOT_WRITEABLE, $this->destination), 'error');
            }
        } elseif ($this->message_location == 'direct') {
            $messageStack->add(sprintf(ERROR_DESTINATION_DOES_NOT_EXIST, $this->destination), 'error');
        } else {
          $messageStack->add_session(sprintf(ERROR_DESTINATION_DOES_NOT_EXIST, $this->destination), 'error');
        }

        return false;
      } else {
        return true;
      }
    }

    function set_output_messages($location) {
      $this->message_location = match ($location) {
          'session' => 'session',
          default => 'direct',
      };
    }
  }
?>
