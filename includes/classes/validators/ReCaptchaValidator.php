<?php

namespace util\validators;

class ReCaptchaValidator extends ValidatorBase
{
	public function validate($sValue, $bEmpty = true)
	{
		// Si queremos o no saltarnos que el valor este vacio
		if (RECAPTCHA_ENABLE !== 'true' || (!$bEmpty && ($sValue === null || $sValue === ''))) {
			return $this->isValid();
		}

		// Validamos
		$secret = RECAPTCHA_PRIVATE_KEY;
		$remoteip = $_SERVER["REMOTE_ADDR"];
		$sValue = $_POST["g-recaptcha-response"];
		$url = "https://www.google.com/recaptcha/api/siteverify";

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, array(
			'secret' => $secret,
			'response' => $sValue,
			'remoteip' => $remoteip
		));

		$curlData = curl_exec($curl);
		curl_close($curl);
		$recaptcha = json_decode($curlData, true);

		// Si es error
		if( !$recaptcha["success"] ){
			$this->messageError('ERROR_CAPTCHA');

			return $this->notValid();
		}

		// Retornamos
		return $this->isValid();
	}
}
