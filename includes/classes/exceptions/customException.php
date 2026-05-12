<?php
// Alias
namespace util\exceptions;

// Librerias
use util\exceptions\exceptionInterface;

/**
 * Clase customException
 */
abstract class customException extends \Exception implements exceptionInterface
{
	protected $message = 'Excepcion desconocida';
    	private $string;
	protected $code = 0;
	protected string $file;
	protected int $line;
	private $trace;

	/**
	 * Constructor
	 *
	 * @param string|null $message
	 * @param string $code
	 */
	public function __construct(?string $message = null, int $code = 0)
	{
		if (!$message) {
			throw new $this('Unknown ' . get_class($this));
		}

		parent::__construct($message, $code);
	}

	/**
	 * Pinta el mensaje de error
	 *
	 * @return string
	 */
	public function __toString(): string
	{
		return get_class($this) . " '{$this->message}' in {$this->file}({$this->line})\n" . "{$this->getTraceAsString()}";
	}
}
