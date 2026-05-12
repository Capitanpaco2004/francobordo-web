<?php

namespace Oscdenox\Core\Auth\TwoFactor\Exception;

class TotpReplayAttackException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Codigo TOTP ya utilizado en esta ventana temporal');
    }
}
