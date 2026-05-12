<?php


namespace util\validators;

class JsonValidator extends ValidatorBase
{
	public function validate($value, $isEmpty = true)
	{
		// Si queremos o no saltarnos que el valor este vacio
		if (!$isEmpty && ($value === null || $value === '')) {
			return $this->isValid();
		}

		$result = json_decode($value);

		switch (json_last_error()) {
			case JSON_ERROR_NONE:
				$error = '';
				break;
			case JSON_ERROR_DEPTH:
				$error = 'The maximum stack depth has been exceeded.';
				break;
			case JSON_ERROR_STATE_MISMATCH:
				$error = 'Invalid or malformed JSON.';
				break;
			case JSON_ERROR_CTRL_CHAR:
				$error = 'Control character error, possibly incorrectly encoded.';
				break;
			case JSON_ERROR_SYNTAX:
				$error = 'Syntax error, malformed JSON.';
				break;
			case JSON_ERROR_UTF8:
				$error = 'Malformed UTF-8 characters, possibly incorrectly encoded.';
				break;
			case JSON_ERROR_RECURSION:
				$error = 'One or more recursive references in the value to be encoded.';
				break;
			case JSON_ERROR_INF_OR_NAN:
				$error = 'One or more NAN or INF values in the value to be encoded.';
				break;
			case JSON_ERROR_UNSUPPORTED_TYPE:
				$error = 'A value of a type that cannot be encoded was given.';
				break;
			default:
				$error = 'Unknown JSON error occured.';
				break;
		}

		// Si es error
		if($result == false || $error !== ''){
			$this->messageError('VALIDATORS_JSON_ERROR_NONE');

			return $this->notValid();
		}

		// Retornamos
		return $this->isValid();
	}
}
