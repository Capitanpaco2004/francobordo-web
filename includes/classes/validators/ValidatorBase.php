<?php

namespace util\validators;

abstract class ValidatorBase
{
	private $messageError;
	private $valid;

	public function __construct($arguments = [])
	{
		// Mensaje de error personalizado
		$this->messageError = array_key_exists('message_error', $arguments) ? $arguments['message_error'] : $this->messageError;

		// Valido
		$this->valid = false;
	}

	/**
	 * Contruye el mensaje error
	 */
	public function messageError()
	{
		global $language;

		$language = isset($language) ? 'espanol' : 'espanol';

		// Idiomas
		require_once (__DIR__ . '/translations/' . $language . '.php');

		// Argumentos de la funcion
		$arguments = func_get_args();

		// El primer argumento es identificador del texto
		$keyMessage = $arguments[0];
		$keyMessage = $this->messageError !== null ? $this->messageError : $keyMessage;

		// Quitamos los argumentos innecesarios
		unset($arguments[0]);

		// Reindexamos
		$arguments = array_values($arguments);

		$message = $keyMessage;

		// LLamamos al mensaje
		if (defined($keyMessage)){
			$message = count($arguments) ? vsprintf(constant($keyMessage), $arguments) : constant($keyMessage);
		}

		$this->messageError = $message;
	}

	public function getMessageError()
	{
		return $this->messageError;
	}

	public function isValid(): bool
	{
		$this->valid = true;
		return true;
	}

	public function notValid(): bool
	{
		$this->valid = false;
		return false;
	}

	public function hasValid(): bool
	{
		return $this->valid;
	}

	/**
	 * Metodo abstracto para obligar que todos los hijos tengan este método
	 * @param $value string
	 * @param bool $empty
	 */
	abstract public function validate($value, $empty = true);
}
