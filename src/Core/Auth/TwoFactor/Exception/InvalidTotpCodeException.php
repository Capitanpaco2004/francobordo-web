<?php

namespace Oscdenox\Core\Auth\TwoFactor\Exception;

class InvalidTotpCodeException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Codigo TOTP invalido');
    }
}
