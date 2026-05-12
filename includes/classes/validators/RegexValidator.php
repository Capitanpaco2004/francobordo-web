<?php
namespace util\validators;

/**
 * Valida que una cadena cumpla un patrón PCRE.
 */
class RegexValidator extends ValidatorBase
{
	private string $pattern;

	/**
	 * @param string $pattern Patrón PCRE, e.g. '/^#[0-9A-Fa-f]{6}$/'
	 * @param array  $arguments Opcional: ['message_error' => 'MI_DEFINE']
	 */
	public function __construct(string $pattern, array $arguments = [])
	{
		$this->pattern = $pattern;
		parent::__construct($arguments);
	}

	public function validate($value, $bEmpty = true)
	{
		if (!$bEmpty && ($value === null || $value === '')) {
			return $this->isValid();
		}

		if (!is_string($value) || !preg_match($this->pattern, $value)) {
			$this->messageError();
			return $this->notValid();
		}

		return $this->isValid();
	}
}
