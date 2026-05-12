<?php

namespace util\validators;

class RecursiveValidator extends ValidatorBase
{
    private $validator;

    public function __construct(ValidatorBase $validator, $arguments = array())
    {
        $this->validator = $validator;

        // Llamamos al padre
        parent::__construct($arguments);
    }

    /**
     * Se lanza para validar
     */
    public function validate($values, $isEmpty = true): bool
    {
        // Si queremos o no saltarnos que el valor este vacio
        if (!$isEmpty && ($values === null || count($values) == 0)) {
            return true;
        }

        foreach ($values as $value) {
            $validator = clone $this->validator;

            if ($validator->validate($value, $isEmpty) === false) {
                $this->messageError($value . ' -- ' . $validator->getMessageError());
                return $this->notValid();
            }
        }

        // Retornamos
        return $this->isValid();
    }
}