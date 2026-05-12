<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function tep_validate_email($email)
{
	$email = trim($email);

	$mail = new PHPMailer(true);
	if (!$mail->validateAddress($email)) {
		$valid_address = false;
	}else{
		$valid_address = true;
	}

	return $valid_address;
}
?>
