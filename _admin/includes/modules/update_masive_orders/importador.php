<?php

class importador {
    var $nombre;
    var $valor;
    var $active;
    var $file;
    var $extension;
    var $content;
    var $orders;
    var $result;
    var $log;
    var $path;
    var $compress = false;
    var $extensionPermitidas = array('xls', 'csv', 'txt', 'xlsx', 'dat');
    var $status = array('entregado' => 3, 'devuelto' => 306);
    var $allowExcel = false;
    var $allowExtension = array();

    public function __construct() {

    }

	
	public function setFileCron($sTransportista) {
		global $sFileCron;

		$this->path = PATH_UPDATE_MASIVE_ORDERS . 'cronjob/' . $sTransportista . '/';
        $filename = $this->path . basename( $sFileCron );

		$this->file = $filename;
		$this->extension = strtolower(pathinfo($this->file, PATHINFO_EXTENSION));

		switch ($this->extension ) {
			case 'zip':
				$this->unZipFile();
				break;
			case 'rar':
				$this->unRarFile();
				break;
		}
    }

    public function setFile() {
        $this->createTemp();

        $file = $_FILES['archivo']['tmp_name'];
        $filename = $this->path. basename( $_FILES['archivo']['name']);
        if (move_uploaded_file($file, $filename)){
            $this->file = $filename;
            $this->extension = strtolower(pathinfo($this->file, PATHINFO_EXTENSION));

            switch ($this->extension ) {
                case 'zip':
                    $this->unZipFile();
                    break;
                case 'rar':
                    $this->unRarFile();
                    break;
            }
        }

    }

    private function unZipFile() {
        $this->addLog('Archivo ZIP encontrado');
        $zip = new ZipArchive;
        $res = $zip->open($this->file);
        if ($res === true) {
          $zip->extractTo($this->path);
          $this->addLog('Archivo ZIP extraido en ' .$this->path);
          $zip->close();

          foreach (glob( $this->path.'*') as $filename) {
              $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
              if (in_array($extension, $this->extensionPermitidas)) {
                  $this->extension = $extension;
                  $this->file = $filename;
                  $this->addLog('Encontrado archivo ' . $this->file);
              }
          }
          $this->compress = 'zip';
        }
    }

    private function unRarFile() {
        $this->addLog('Archivo RAR encontrado');
        require (PATH_UPDATE_MASIVE_ORDERS . 'RarArchiver.php');

        $rar = new RarArchiver($this->file, RarArchiver::CREATE);
        $rar->extractTo($this->path);
        $this->addLog('Archivo RAR extraido en ' .$this->path);

        foreach (glob( $this->path.'*') as $filename) {
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($extension, $this->extensionPermitidas)) {
                $this->extension = $extension;
                $this->file = $filename;
                $this->addLog('Encontrado archivo ' . $this->file);
            }
        }

        $this->compress = 'rar';

    }


    public function saveData() {
        global $messageStack;

        $language = 'espanol';
        $orders_status_array = $this->getStatuses();
        include_once(DIR_WS_CLASSES . 'order.php');

        if (!empty($this->content)) {
            foreach ($this->content as $pedido) {
                if ($pedido['estado'] == 'entregado' || $pedido['estado'] == 'devuelto') {
                    $oID = $pedido['oID'];
                    $statusOrder = $this->getStatusOrder($oID);
                    if ($statusOrder != false && $statusOrder != $this->status[$pedido['estado']])
					{
                        $order = new order($oID);

                        $sql = 'UPDATE ' . TABLE_ORDERS . ' SET orders_status = ' . $this->status[$pedido['estado']] . ', last_modified = '.($pedido['fecha'] != '' ? '"'.$pedido['fecha'].'"' : 'now()').' WHERE orders_id = ' . $oID;

                        tep_db_query($sql);


                        //$this->addLog($sql);

                        $check_status['customers_name'] = $order->customer['name'];
                        $check_status['date_purchased'] = $order->info['date_purchased'];
						$status = $this->status[$pedido['estado']];

                        require(DIR_FS_CATALOG_MODULES . 'UHtmlEmails/'. ULTIMATE_HTML_EMAIL_LAYOUT .'/orders.php');
                        $email = $html_email;

                        tep_mail($order->customer['name'], $order->customer['email_address'], 'Actualización del Pedido (Nº de Pedido: ' . $oID . ')', $email, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
                        tep_mail($order->customer['name'], STORE_OWNER, 'Actualización del Pedido (Nº de Pedido: ' . $oID . ') -- Copia', $email, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);

                        $sql = 'INSERT INTO ' . TABLE_ORDERS_STATUS_HISTORY .' SET orders_id = ' . $oID . ', orders_status_id = ' .$this->status[$pedido['estado']] .', date_added = '.($pedido['fecha'] != '' ? '"'.$pedido['fecha'].'"' : 'now()').', customer_notified = 1';
                        tep_db_query($sql);

						# Inicio, sistema de opiniones
						if( SISTEMA_OPINION_ENABLED == 'true' )
						{
							// Obtenemos la opinion
							$aOpinion = tep_db_query( 'select id_opinion, orders_id, email_primero_enviado from opinion where orders_id = ' . $oID );

							// Obtenemos el array de estados
							$aAux = explode( ',', SISTEMA_OPINION_ESTADO_PEDIDO );

							// Si el estado es el configurado y no existe opinion aun para este pedido, insertamos el registro de opinion
							if( in_array( $this->status[$pedido['estado']], $aAux ) && tep_db_num_rows( $aOpinion ) == 0 )
								tep_db_query( 'insert into opinion (customers_id,orders_id, uniqid) values (' . $order->customer['id'] . ', ' . $oID . ', "' . uniqid( '', true ) . '_' . md5(mt_rand() ) . '")' );
							// Si cambia el estado por otro este sera eliminado si existe la opinion y esta aun no ha sido enviada
							else if( $order->info['orders_status'] != $this->status[$pedido['estado']] && tep_db_num_rows( $aOpinion ) > 0 )
							{
								$aOpinion = tep_db_fetch_array( $aOpinion );

								if( $aOpinion['email_primero_enviado'] == 'false' )
									tep_db_query( 'delete from opinion where id_opinion = ' . $aOpinion['id_opinion'] );
							}
						}
						# Fin, sistema de opiniones

                        $this->addLog($sql);
                        $this->addLog('Cambio de estado en pedido ' . $oID . ' a ' . $pedido['estado']);

                        $this->orders[] = $oID .' a <strong>' . strtoupper($pedido['estado']) . '</strong>';

                    } else {
                        $this->addLog('Pedido ' . $oID . ' omitido. Ya tiene ese estado o no se encuentra.');
                    }

                }
            }
            if (is_array( $this->orders ) && count($this->orders) > 0) {
                $messageStack->add_session('Se ha completado la actualización de <strong>' . count($this->orders) . ' pedidos</strong><br /><strong>Resumen:</strong><br /><ul><li>' . implode('</li><li>', $this->orders).'</li></ul>', 'success');
            } else {
                $messageStack->add_session('No se ha actaulizado ningún pedido', 'warning');
            }

        } else {
            if ($this->compress != false) {
                $messageStack->add_session('ERROR: No se han encontrado pedidos en el archivo ' . $this->file . '<br />Pruebe a descomprimir el archivo .' . $this->compress . ' y a subir su contenido.', 'error');
            } else {
                $messageStack->add_session('ERROR: No se han encontrado pedidos en el archivo ' . $this->file, 'error');
            }

            $this->addLog('ERROR: No se han encontrado pedidos en el archivo ' . $this->file);
        }

        $this->saveLog();
		$this->removeTemp();
    }

    private function getStatusOrder($oID) {
        $this->addLog('Obteniendo estado del pedido ' . $oID);
        $sql = 'SELECT orders_status FROM ' . TABLE_ORDERS . ' WHERE orders_id = ' . $oID;
        $check_status = tep_db_query($sql);
        $check_status = tep_db_fetch_array($check_status);
        $this->addLog($sql);
        $status = (int)$check_status['orders_status'];
        if ($status > 0) {
            $this->addLog('Estado del pedido ' . $oID . ': ' . $status);
            return $status;
        } else {
            $this->addLog('NO SE HA ENCONTRADO EL PEDIDO ' . $oID);
            return false;
        }

    }

    private function getStatuses()  {
        global $languages_id;

        $orders_status_array = array();
        $orders_status_query = tep_db_query("select orders_status_id, orders_status_name from " . TABLE_ORDERS_STATUS . " where language_id = '" . (int)$languages_id . "'");

        while ($orders_status = tep_db_fetch_array($orders_status_query))
        {
            $orders_status_array[$orders_status['orders_status_id']] = $orders_status['orders_status_name'];
        }

        return $orders_status_array;
    }

    private function createTemp() {
        $uniqid = uniqid();
        if (!is_dir(PATH_UPDATE_MASIVE_ORDERS . 'temp/')) {
            mkdir(PATH_UPDATE_MASIVE_ORDERS . 'temp/');
        }


        $path = PATH_UPDATE_MASIVE_ORDERS . 'temp/'.$uniqid.'/';
        mkdir($path);

        $this->path = $path;

        $this->addLog('Creado directorio temporal en ' . $this->path);
    }

    public function removeTemp($folder = false) {
        $folder = $folder == false ? PATH_UPDATE_MASIVE_ORDERS . 'temp/' : $folder;
        foreach(glob($folder . "/*") as $archivosCarpeta){
            if (is_dir($archivosCarpeta)){
                $this->removeTemp($archivosCarpeta);
            } else {
                unlink($archivosCarpeta);
            }
        }

		if( file_exists( $folder ) )
			rmdir($folder);
     }

     public function addLog($log) {
         if ($log!='') {
            $this->log[] = array(date('H:i:s'), $log);
         }
     }

     public function saveLog() {
         if (!empty($this->log)) {
            $sName = date('Y-m-d-H-i-s').'-'.$this->valor.'.log';
            $file = fopen(PATH_UPDATE_MASIVE_ORDERS . 'log/' . $sName, "w+");

            foreach ($this->log as $sLine) {
                fwrite($file, '['.$sLine[0].'] ' . $sLine[1] . PHP_EOL);
            }
            fclose($file);
         }

         $archivos  = scandir(PATH_UPDATE_MASIVE_ORDERS . 'log/');
		foreach ($archivos as $archivo) {
            if (strlen($archivo) > 2) {
                $filemtime = filemtime(PATH_UPDATE_MASIVE_ORDERS . 'log/'.$archivo);
    			if ($filemtime < (time() - (86400 * 10))) {
    			   unlink(PATH_UPDATE_MASIVE_ORDERS . 'log/'.$archivo);
    			}
            }

		}
     }
}
