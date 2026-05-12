<?php
// Alias
namespace util\validators;

/**
 * Url valida
 */
class UrlValidator extends ValidatorBase
{
    /**
     * Protocolos
     * @var string
     */
    private $protocol = 'http|https';

    /**
     * Constructor de la clase
     */
    public function __construct($aArguments = array())
    {
        // Protocolo
        $this->protocol = array_key_exists('protocol', $aArguments) ? $aArguments['protocol'] : $this->protocol;

        // Llamamos al padre
        parent::__construct($aArguments);
    }

    /**
     * Se lanza para validar
     */
    public function validate($value, $bEmpty = true)
    {
        // Si queremos o no saltarnos que el valor este vacio
        if (!$bEmpty && ($value === null || $value === '')) {
            return $this->isValid();
        }

        // Pattern
        $sPattern = '~^
			    ((%s)://)?                              # protocol
			    (
			      ([a-z0-9-_]+\.)+[a-z_]{2,6}           # a domain name
			        |                                   #  or
			      \d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}    # a IP address
			    )
			    (:[0-9]+)?                              # a port (optional)
			    (/?|/\S+)                               # a /, nothing or a / with something
			  $~ix';

        // Protocolos
        $sPattern = sprintf($sPattern, $this->protocol);

        // Si no es una url correcta
        if (!preg_match($sPattern, $value)) {
            $this->messageError('VALIDATORS_URL');

            return $this->notValid();
        }

        // Retornamos
        return $this->isValid();
    }
}