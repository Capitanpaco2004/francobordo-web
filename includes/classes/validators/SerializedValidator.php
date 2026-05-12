<?php

namespace util\validators;

use util\arrays;

class SerializedValidator extends ValidatorBase
{
	private $strict;

	public function __construct($arguments = [])
	{
		$this->strict = arrays::getValueByKey( $arguments, 'strict', true );

		parent::__construct($arguments);
	}

	public function validate($value, $isEmpty = true): bool
	{
		// Si queremos o no saltarnos que el valor este vacio
		if (!$isEmpty && ($value === null || $value === '')) {
			return $this->isValid();
		}

		// If it isn't a string, it isn't serialized.
		if ( ! is_string( $value ) ) {
			return $this->notValid();
		}
		$value = trim( $value );
		if ( 'N;' === $value ) {
			return $this->isValid();
		}
		if ( strlen( $value ) < 4 ) {
			return $this->notValid();
		}
		if ( ':' !== $value[1] ) {
			return $this->notValid();
		}
		if ( $this->strict ) {
			$lastc = substr( $value, -1 );
			if ( ';' !== $lastc && '}' !== $lastc ) {
				return $this->notValid();
			}
		} else {
			$semicolon = strpos( $value, ';' );
			$brace     = strpos( $value, '}' );
			// Either ; or } must exist.
			if ( false === $semicolon && false === $brace ) {
				return $this->notValid();
			}
			// But neither must be in the first X characters.
			if ( false !== $semicolon && $semicolon < 3 ) {
				return $this->notValid();
			}
			if ( false !== $brace && $brace < 4 ) {
				return $this->notValid();
			}
		}
		$token = $value[0];
		switch ( $token ) {
			case 's':
				if ( $this->strict ) {
					if ( '"' !== substr( $value, -2, 1 ) ) {
						return $this->notValid();
					}
				} elseif ( false === strpos( $value, '"' ) ) {
					return $this->notValid();
				}
			// Or else fall through.
			case 'a':
			case 'O':
				return ((bool) preg_match( "/^{$token}:[0-9]+:/s", $value )) ? $this->isValid() : $this->notValid();
			case 'b':
			case 'i':
			case 'd':
				$end = $this->strict ? '$' : '';
				return ((bool) preg_match( "/^{$token}:[0-9.E+-]+;$end/", $value )) ? $this->isValid() : $this->notValid();
		}

		return $this->notValid();
	}
}
