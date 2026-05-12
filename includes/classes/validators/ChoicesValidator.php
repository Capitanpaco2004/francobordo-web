<?php
// Alias
namespace util\validators;

/**
 * Comprueba que el valor exista en las opciones
 */
class ChoicesValidator extends ValidatorBase
{
	const VALIDATOR_KEY = 0;
	const VALIDATOR_VALUE = 1;

    /**
     * Opciones disponibles
     * @var array
     */
    private $choices;

	/**
	 * Opciones de validacion
	 * @var int
	 */
	private $methodValidate;

	/**
	 * Constructor de la clase
	 * @param array $arguments
	 */
    public function __construct($arguments = array())
    {
        // Obtenemos las opciones
        $this->choices = $arguments['choices'];
        $this->methodValidate = isset($arguments['method_validate']) ? $arguments['method_validate'] : self::VALIDATOR_VALUE;

        // Llamamos al padre
        parent::__construct($arguments);
    }

    /**
     * Se lanza para validar
     */
    public function validate($value, $isEmpty = true): bool
	{
        // Si el valor no existe
        if ($value != '' && !$this->validator($value)) {
            $this->messageError('VALIDATORS_CHOICE');

			return $this->notValid();
        }

        // Retornamos
		return $this->isValid();
    }

    private function validator($value): bool
	{
		return $this->methodValidate == self::VALIDATOR_VALUE ? in_array($value, $this->choices) : array_key_exists($value, $this->choices);
	}
}
