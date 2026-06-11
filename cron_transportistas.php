<?php
	// Libreria
	use util\tools as tools;

	// Incluimos clase tools
	include( 'includes/classes/tools.php' );

	// Fix 2026-05-16: errores solo al log, no a la respuesta (endpoint público).
	error_reporting(E_ALL & ~E_NOTICE);
	ini_set('display_errors', '0');

	// Salto de línea según contexto (web o CLI)
	$br = php_sapi_name() === 'cli' ? "\n" : "<br>\n";

	// Obtenemos directorio admin
	$sAdminPath = tools::getPathAdmin();

	// Engañamos al admin para saltar login
	$_SERVER['PHP_SELF'] = 'cron_transportistas.php';
	$_SERVER['SCRIPT_FILENAME'] = 'cron_transportistas.php';
	$_SERVER['SCRIPT_NAME'] = str_replace( 'cron_transportistas.php', '', $_SERVER['SCRIPT_NAME'] ) . $sAdminPath . '/cron_transportistas.php';

	// Posicionamos a el admin para obtener sus mismas funciones
	chdir( $sAdminPath );

	// Includes
	require('includes/application_top.php');

	//Definimos ruta para las clases
	define('PATH_UPDATE_MASIVE_ORDERS', realpath(DIR_FS_ADMIN) . '/includes/modules/update_masive_orders/');

	// Array para guardar transportista y sus feeds
	$aEmails = array();

	// Fix 2026-05-16: bloque IMAP envuelto en try/catch — si falla la conexión o el query
	// (excepción GetMessagesFailedException), seguimos con DHL en lugar de abortar el cron.
	try {
		// Webklex IMAP (reemplazo de ext-imap eliminada en PHP 8.4)
		$cm = new \Webklex\PHPIMAP\ClientManager();
		$oClient = $cm->make([
			'host'          => 'mail.francobordo.com',
			'port'          => 993,
			'encryption'    => 'ssl',
			'validate_cert' => false,
			'username'      => 'transporte',
			'password'      => 'Paco0908;',
			'protocol'      => 'imap',
		]);

		$oClient->connect();
		$oFolder = $oClient->getFolder('INBOX');

		// Buscar emails no eliminados desde ayer
		$sSince = date('d M Y', strtotime('-1 day'));
		$aMessages = $oFolder->query()->since($sSince)->get();

		foreach ($aMessages as $oMessage)
		{
		$sSubject = $oMessage->getSubject();

		// Vamos a preparar la key de nuestro array global a través del asunto del email
		// SEUR DESACTIVADO 2026-06-11: el tracking lo gestiona cron_seur_tracking.php (API
		// PIC, tabla seur_tracking) - cubre la integracion API (ref F{oid}) y la vieja de
		// Vstock (ref oid a pelo, fallback sin filtros de cuenta para internacionales).
		// if (preg_match('/Fichero situaciones/i', $sSubject))
		//	$sTransportista = 'importador_seur';
		if (preg_match('/Retorno de Situacion de Envios de Correos/i', $sSubject))
			$sTransportista = 'importador_correos';
		// Correos Express DESACTIVADO 2026-06-06: el tracking lo gestiona cron_cex_tracking.php (API).
		// elseif (preg_match('/Fichero FRANCOBORDO PRODUCTOS/i', $sSubject))
		//	$sTransportista = 'importador_correos_express';
		elseif (preg_match('/Reporte de Entregas de TNT/i', $sSubject))
			$sTransportista = 'importador_tnt';
		else
			continue;

		// Obtenemos adjuntos
		$aAttachments = $oMessage->getAttachments();

		foreach ($aAttachments as $oAttachment)
		{
			$filename = $oAttachment->getName();

			if (empty($filename))
				$filename = time() . '.dat';

			// Filtrar adjuntos no válidos (imágenes de firma, logos, etc.)
			$sExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
			if (in_array($sExt, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'ico', 'webp'])) continue;

			$folder = PATH_UPDATE_MASIVE_ORDERS . 'cronjob/' . $sTransportista . '/';

			if (!is_dir($folder))
				mkdir($folder, 0777, true);

			// Evitar duplicados
			if (isset($aEmails[$sTransportista]) && in_array($filename, $aEmails[$sTransportista])) continue;

			$sFilePath = $folder . $filename;
			$bOk = @file_put_contents($sFilePath, $oAttachment->getContent());
			if ($bOk !== false) {
				echo "OK: $filename" . $br;
			} else {
				echo "Error: no se pudo crear $sFilePath" . $br;
				continue;
			}

			$aEmails[$sTransportista][] = $filename;
		}
	}

		// Cerramos conexion
		$oClient->disconnect();
	} catch (\Throwable $e) {
		echo "Aviso: IMAP falló - " . $e->getMessage() . $br;
		error_log('cron_transportistas IMAP error: ' . $e->getMessage());
		// Seguimos al bloque DHL.
	}

	//Incluimos clases
	require(PATH_UPDATE_MASIVE_ORDERS . 'update_masive_orders.php');
	require(PATH_UPDATE_MASIVE_ORDERS . 'importador.php');

	// Obtenemos feed de DHL via phpseclib (SFTP) //
	try {
		$sftp = new \phpseclib3\Net\SFTP('ftp3.dhl.com', 22);
		if (!$sftp->login('hg6nmp6d', 'xDRMc!J&Ds$m2oSg')) {
			echo "Aviso: no se pudo conectar al SFTP de DHL." . $br;
		} else {
			$files = $sftp->nlist('/out/workt');
			if (!empty($files)) {
				rsort($files);
				$folderDhl = PATH_UPDATE_MASIVE_ORDERS . 'cronjob/importador_dhl/';
				if (!is_dir($folderDhl)) mkdir($folderDhl, 0777, true);

				foreach ($files as $file) {
					if ($file == '.' || $file == '..') continue;

					$nAnno = substr($file, 10, 4);
					$nMes = substr($file, 14, 2);
					$nDia = substr($file, 16, 2);

					if (strtotime($nAnno . '-' . $nMes . '-' . $nDia) >= strtotime(date('Y-m-d', strtotime('-1 day')))) {
						$content = $sftp->get('/out/workt/' . $file);
						if ($content !== false) {
							file_put_contents($folderDhl . $file, $content);
							$aEmails['importador_dhl'][] = $file;
						}
					}
				}
			}
			$sftp->disconnect();
		}
	} catch (Exception $e) {
		echo "Aviso: error DHL SFTP - " . $e->getMessage() . $br;
	}
	// /Obtenemos feed de DHL //

	$_GET['action'] = 'import';
	$crontag = true;

	// Si tenemos feeds en algun transportista
	if( count( $aEmails ) > 0 )
	{
		echo $br . '--- Procesando ' . count($aEmails) . ' transportista(s) ---' . $br;

		// Recorremos transportistas
		foreach( $aEmails as $sTransportista => $aFiles )
		{
			echo $br . 'Transportista: ' . $sTransportista . ' (' . count($aFiles) . ' archivos)' . $br;

			// Recorremos sus archivos
			foreach( $aFiles as $sFile )
			{
				sleep(1);

				$_POST['transportista'] = $sTransportista;
				$sFileCron = $sFile;

				echo 'Importando: ' . $sFile . '... ';
				$importador = new importadorPedidos;
				echo 'OK' . $br;

				@unlink( PATH_UPDATE_MASIVE_ORDERS . 'cronjob/' . $sTransportista . '/' . $sFile );
			}

			// Limpiamos directorio donde guardamos los feeds para el cronjob
			foreach(glob(PATH_UPDATE_MASIVE_ORDERS . 'cronjob/' . $sTransportista . '/*') as $archivosCarpeta){
				@unlink($archivosCarpeta);
			}
		}
	} else {
		echo 'No hay feeds de transportistas que procesar.' . $br;
	}

	echo $br . 'FIN - ' . date('d/m/Y H:i:s');
?>