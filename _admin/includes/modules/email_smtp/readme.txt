Módulo email smtp: Instalación
-------

###Instalación

####1) Realizar diff con _admin/includes/functions/database.php

####2) Realizar diff con _admin/includes/classes/message_stack.php

####3) Instalar el theme solenopsis:
`3.1: ` Copiar el theme solenopsis en _admin/theme/

`3.2: ` Añadir _admin/.htaccess
```php
  RewriteRule style_base.css$ theme/solenopsis/css/style.php [L,QSA,NC]
```

####4) _admin/includes/application_top.php
Linea `~173`: comprobar que existe la llamada a la clase tools y date si no añadirla:
```php
  // Tools
  include( '../includes/classes/tools.php' );
  include( '../includes/classes/date.php' );
```

```

####5) _admin/includes/boxes/configuration.php
Linea `~710`: Añadir al menu:
```php
	'<a href="' . tep_href_link('email_smtp.php', '', 'NONSSL') . '" class="menuBoxContentLink2">Configurar Emails</a>' 
```

####6) _admin/includes/functions/general.php
Linea `~1470`: Sustituimos función tep_mail (atento a cualquier código a medida):
```php
	function tep_mail($to_name, $to_email_address, $email_subject, $email_text, $from_email_name, $from_email_address)
	{
		if (SEND_EMAILS != 'true') return false;

		require_once( getcwd() . '/../includes/classes/classmail/class.smtp.php' );
		require_once( getcwd() . '/../includes/classes/classmail/class.phpmailer.php' );

		// Decodificamos el json de emails
		$aEmails = json_decode( stripslashes( STORE_OWNER_EMAIL_ADDRESS_GROUP ), true );

		// Declaramos en false la variable semaforo para enviar el email por smtp o como siempre.
		$bSmtp = false;
		
		// Variables
		$sEmail = '';
		$sHost = '';
		$sPort = '';
		$sPassword = '';

		if( count( $aEmails ) > 0 )
		{
			// Recorremos el array de emails configurados
			foreach( $aEmails as $sUser => $aEmail )
			{
				$aSecciones = explode( ',', $aEmail[2] );

				foreach( $aSecciones as $sSeccion )
				{
					// Si coincide la sección en la que estamos
					if( preg_match( '/' . $sSeccion . '/i', $_SERVER['SCRIPT_NAME'] ) )
					{
						// Guardamos los datos del envío smtp
						$sEmail = $sUser;
						$sHost = $aEmail[0];
						$sPort = $aEmail[1];
						$sPassword = tools::decrypt( $aEmail[3] );

						// Marcamos para envío smtp
						if( $sEmail != '' && $sHost != '' && $sPort != '' && $sPassword != '' )
							$bSmtp = true;

						break;
					}
				}
				
				if( $bSmtp )
					break;
			}
		}

		// Si enviamos por smtp
		if( $bSmtp || (SMTP_ACTIVE == 'smtp' && SMTP_HOST != '' && SMTP_PUERTO != '' && SMTP_PASS != '') )
		{
			$mail = new PHPMailer(true);
			$mail->IsHTML(true);
			$mail->IsSMTP();
			$mail->SMTPAuth = true;
			$mail->SMTPSecure = "tls";
			$mail->Host = ($bSmtp ? $sHost : SMTP_HOST);
			$mail->Port = ($bSmtp ? $sPort : SMTP_PUERTO);
			$mail->Username = ($bSmtp ? $sEmail : STORE_OWNER_EMAIL_ADDRESS);
			$mail->Password = ($bSmtp ? $sPassword : tools::decrypt( SMTP_PASS ));

			$mail->CharSet = 'utf-8';
			$mail->IsHTML( true );
			$mail->From = ($bSmtp ? $sEmail : STORE_OWNER_EMAIL_ADDRESS);
			$mail->FromName = $from_email_name;
			$mail->Subject = $email_subject;
			$mail->AddAddress($to_email_address,$to_name);

			$mail->Body = nl2br($email_text);
			$mail->AltBody = htmlentities( $mail->Body );
			try
			{
				@$mail->Send();
			}
			catch(Exception $e)
			{
			}
		}
		// Si no, enviamos como siempre
		else
		{
			$mail = new PHPMailer();
			$mail->Host = "localhost";
			$mail->CharSet = 'utf-8';
			$mail->IsHTML( true );
			$mail->From = $from_email_address;
			$mail->FromName = $from_email_name;
			$mail->Subject = $email_subject;
			$mail->AddAddress($to_email_address,$to_name);

			$mail->Body = $email_text;
			$mail->AltBody = htmlentities( $eMail->Body );
			$mail->Send();
		}
	}
```

####7) /includes/functions/general.php
Linea `~1452`: Sustituimos función tep_mail (atento a cualquier código a medida):
```php
	function tep_mail($to_name, $to_email_address, $email_subject, $email_text, $from_email_name, $from_email_address)
	{
		require_once( getcwd() . '/includes/classes/classmail/class.smtp.php' );
		require_once( getcwd() . '/includes/classes/classmail/class.phpmailer.php' );

		// Decodificamos el json de emails
		$aEmails = json_decode( stripslashes( STORE_OWNER_EMAIL_ADDRESS_GROUP ), true );

		// Declaramos en false la variable semaforo para enviar el email por smtp o como siempre.
		$bSmtp = false;
		
		// Variables
		$sEmail = '';
		$sHost = '';
		$sPort = '';
		$sPassword = '';

		if( count( $aEmails ) > 0 )
		{
			// Recorremos el array de emails configurados
			foreach( $aEmails as $sUser => $aEmail )
			{
				$aSecciones = explode( ',', $aEmail[2] );

				foreach( $aSecciones as $sSeccion )
				{
					// Si coincide la sección en la que estamos
					if( preg_match( '/' . $sSeccion . '/i', $_SERVER['SCRIPT_NAME'] ) )
					{
						// Guardamos los datos del envío smtp
						$sEmail = $sUser;
						$sHost = $aEmail[0];
						$sPort = $aEmail[1];
						$sPassword = tools::decrypt( $aEmail[3] );

						// Marcamos para envío smtp
						if( $sEmail != '' && $sHost != '' && $sPort != '' && $sPassword != '' )
							$bSmtp = true;

						break;
					}
				}
				
				if( $bSmtp )
					break;
			}
		}

		// Si enviamos por smtp
		if( $bSmtp || (SMTP_ACTIVE == 'smtp' && SMTP_HOST != '' && SMTP_PUERTO != '' && SMTP_PASS != '') )
		{
			$mail = new PHPMailer(true);
			$mail->IsHTML(true);
			$mail->IsSMTP();
			$mail->SMTPAuth = true;
			$mail->SMTPSecure = "tls";
			$mail->Host = ($bSmtp ? $sHost : SMTP_HOST);
			$mail->Port = ($bSmtp ? $sPort : SMTP_PUERTO);
			$mail->Username = ($bSmtp ? $sEmail : STORE_OWNER_EMAIL_ADDRESS);
			$mail->Password = ($bSmtp ? $sPassword : tools::decrypt( SMTP_PASS ));

			$mail->CharSet = 'utf-8';
			$mail->IsHTML( true );
			$mail->From = ($bSmtp ? $sEmail : STORE_OWNER_EMAIL_ADDRESS);
			$mail->FromName = $from_email_name;
			$mail->Subject = $email_subject;
			$mail->AddAddress($to_email_address,$to_name);

			$mail->Body = nl2br($email_text);
			$mail->AltBody = htmlentities( $mail->Body );
			try
			{
				@$mail->Send();
			}
			catch(Exception $e)
			{
			}
		}
		// Si no, enviamos como siempre
		else
		{
			$mail = new PHPMailer();
			$mail->Host = "localhost";
			$mail->CharSet = 'utf-8';
			$mail->IsHTML( true );
			$mail->From = $from_email_address;
			$mail->FromName = $from_email_name;
			$mail->Subject = $email_subject;
			$mail->AddAddress($to_email_address,$to_name);

			$mail->Body = $email_text;
			$mail->AltBody = htmlentities( $eMail->Body );
			$mail->Send();
		}
	}
```