<?php
// Alias
namespace util\validators;


/**
 * Email valido
 */
class EmailValidator extends ValidatorBase
{
    /**
     * Validador basada regex que no valida todas las direcciones RFC822 pero atrapa el 99,9 % de ellos. La expresión regular se basa en el código que se encuentra en http://www.regular-expressions.info/email.html
     */
    public function validate($value, $bEmpty = true)
    {
        // Si queremos o no saltarnos que el valor este vacio
        if (!$bEmpty && ($value === null || $value === '')) {
            return $this->isValid();
        }

        // Limpiamos espacios
        $value = trim($value);

        // Validamos
        if (strlen($value) > 255)
            return $this->error();
        else {
            if (substr_count($value, '@') > 1)
                return $this->error();

            if (preg_match('/^[a-zA-Z0-9.!#$%&\'*+\\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$/', $value))
                return $this->isValid();
            else
                return $this->error();
        }
    }

    /**
     * Añade el error de validacion
     */
    private function error(): bool
    {
        $this->messageError('VALIDATORS_EMAIL');

        return $this->notValid();
    }
}