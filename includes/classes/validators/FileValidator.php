<?php

namespace util\validators;

use util\arrays;
use util\strings;

class FileValidator extends ValidatorBase
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
	 * Url web del directorio
	 * @var string
	 */
	public $pathWeb;

	/**
	 * Nombr del archivo final
	 * @var string
	 */
	public $fileName;

	/**
	 * Elemento inputFile
	 * @var string
	 */
	public $name;

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
	 * Indicates whether the file is base64-encoded.
	 * @var bool
	 */
	public $base64;
	
	/**
	 * Constructor
	 */
	public function __construct($aArguments = array())
	{
		// Propiedades
		$this->base64 = arrays::getValueByKey($aArguments, 'base64', false);
		$this->postMaxSize = arrays::getValueByKey($aArguments, 'post_max_size', ini_get('post_max_size'));
		$this->uploadMaxFileSize = arrays::getValueByKey($aArguments, 'upload_max_filesize', ini_get('upload_max_filesize'));
		$this->maxFileUploads = arrays::getValueByKey($aArguments, 'max_file_uploads', ini_get('max_file_uploads'));
		$this->mimeType = arrays::getValueByKey($aArguments, 'mime_type', array('image/bmp', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'image/jpeg', 'image/gif', 'image/png', 'text/plain', 'application/pdf'));
		$this->fileExtension = arrays::getValueByKey($aArguments, 'file_extension', array('bmp', 'doc', 'xls', 'jpeg', 'jpg', 'jpe', 'jfif', 'jfi', 'jif', 'gif', 'png', 'txt', 'pdf'));
		$this->pathUpload = $aArguments['path_upload'];
		$this->fileName = arrays::getValueByKey($aArguments, 'file_name', false);
		$this->name = $aArguments['name'];
		$this->required = arrays::getValueByKey($aArguments, 'required', false);
		$this->dimension = arrays::getValueByKey($aArguments, 'dimension', false);

		// Obtenemos el tamaño minimo de las 2 propiedades que tienes que ver con el maximo permitido en post
		$this->postMaxSize = min($this->convertPHPSizeToBytes($this->postMaxSize), $this->convertPHPSizeToBytes($this->uploadMaxFileSize));

		// Llamamos al padre
		parent::__construct($aArguments);
	}

	/**
	 * Se lanza para validar
	 */
	public function validate($sValue, $bEmpty = true)
	{
		// Si es base 64
		if ($this->base64) {
			// Si queremos o no saltarnos que el valor este vacio
			if (!$bEmpty && ($sValue === null || $sValue === '')) {
				return $this->isValid();
			}

			// Tamaño
			$nSize = (strlen($sValue) * 3 / 4) - substr_count(substr($sValue, -2), '=');

			// Comprobamos si el tamaño excede del permitido
			if ($nSize > $this->postMaxSize) {
				$this->messageError('VALIDATORS_FILE_SIZE', $this->formatBytes($this->postMaxSize));
				return $this->notValid();
			}

			// Mime type del archivo
			$sMimeType = preg_replace('/^data:|;base64.*$/', '', $sValue);

			// Comprobamos el mimetype
			if (!in_array($sMimeType, $this->mimeType)) {
				$this->messageError('VALIDATORS_FILE_MIME_TYPE', $sMimeType, implode(', ', $this->mimeType));
				return $this->notValid();
			}
		} else {
			// Elemento file dentro del array de $_FILES
			$file = $_FILES[$this->name];

			// Si no contenemos tmp_name y no es un campo requerido pasamos
			if ($file['tmp_name'] == '' && !$this->required) {
				return $this->isValid();
			}

			// Comprobamos si el tamaño excede del permitido
			if ($file['size'] > $this->postMaxSize || in_array($file['error'], array(self::UPLOAD_ERR_INI_SIZE, self::UPLOAD_ERR_FORM_SIZE))) {
				$this->messageError('VALIDATORS_FILE_SIZE', $this->formatBytes($this->postMaxSize));
				return $this->notValid();
			}

			// Comprobamos si solo se ha podido subir una parte del archivo
			if ($file['error'] == self::UPLOAD_ERR_PARTIAL) {
				$this->messageError('VALIDATORS_FILE_PARTIAL');
				return $this->notValid();
			}

			// Comprobamos si es un archivo
			if (!is_file($file['tmp_name'])) {
				$this->messageError('VALIDATORS_FILE_NO_FOUND');
				return $this->notValid();
			}

			// Comprobamos si se puede leer
			if (!is_readable($file['tmp_name'])) {
				$this->messageError('VALIDATORS_FILE_NO_READABLE');
				return $this->notValid();
			}

			// Si tenemos tamaño permitido
			if ($this->dimension !== false) {
				// Tamaño
				$aSize = getimagesize($file['tmp_name']);

				// Comprobamos si es mas grande la imagen que lo permitido
				if ($aSize[0] > $this->dimension[0] || $aSize[1] > $this->dimension[1]) {
					$this->messageError('VALIDATORS_FILE_DIMENSION', $this->dimension[0] . 'x' . $this->dimension[1]);
					return $this->notValid();
				}
			}

			// Mime type del archivo
			$sMimeType = mime_content_type($file['tmp_name']);

			// Extensión del archivo
			$sExtension = preg_replace('/.*\./', '', $file['name']);

			// Comprobamos el mimetype
			if (!in_array($sMimeType, $this->mimeType)) {
				$this->messageError('VALIDATORS_FILE_MIME_TYPE', $sMimeType, implode(', ', $this->mimeType));
				return $this->notValid();
			}

			// Comprobamos extension
			if (!in_array(strtolower($sExtension), $this->fileExtension)) {
				$this->messageError('VALIDATORS_FILE_EXTENSION', $sExtension, implode(', ', $this->fileExtension));
				return $this->notValid();
			}
		}

		return $this->isValid();
	}

	/**
	 * De byte a kilobyte, megabyte etc con su prefijo
	 */
	private function formatBytes($bytes, $precision = 2)
	{
		$units = array('B', 'KB', 'MB', 'GB', 'TB');

		$bytes = max($bytes, 0);
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
		$pow = min($pow, count($units) - 1);
		$bytes /= (1 << (10 * $pow));

		return round($bytes, $precision) . ' ' . $units[$pow];
	}

	/**
	 * Convierte los tamaños de ini_get que te lo devuelve como 2M a bytes
	 */
	private function convertPHPSizeToBytes($sSize)
	{
		if (is_numeric($sSize)) {
			return $sSize;
		}

		$sSuffix = substr($sSize, -1);
		$iValue = substr($sSize, 0, -1);

		switch (strtoupper($sSuffix)) {
			case 'P':
				$iValue *= 1024;
			// no break
			case 'T':
				$iValue *= 1024;
			// no break
			case 'G':
				$iValue *= 1024;
			// no break
			case 'M':
				$iValue *= 1024;
			// no break
			case 'K':
				$iValue *= 1024;
				break;
		}

		return $iValue;
	}
}
