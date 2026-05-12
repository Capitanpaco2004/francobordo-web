<?php

class importadorPedidos {
    var $action;
    public $modules;
    public $active;
	public $crontag;
    public $result;
    public $log;
    public $importadores = [];

    function __construct() {
		global $crontag;

        $this->action = (isset($_GET['action']) ? tep_db_prepare_input($_GET['action']) : 'list');
        $this->active = (isset($_POST['transportista']) ? tep_db_prepare_input($_POST['transportista']) : false);
		$this->crontag = (isset($crontag) && $crontag ? true : false);

        foreach (glob( DIR_WS_INCLUDES . 'modules/update_masive_orders/importadores/*.php') as $filename) {
            include_once($filename);
            $basename = str_replace('.php','',basename ( $filename ));
            $class = 'importador_'.$basename;
            $this->importadores[$class] = new $class;

            if ($this->importadores[$class]->active == true) {
                $this->modules[] = array('value' => $class, 'text' => $this->importadores[$class]->nombre .' (' . implode(',', $this->importadores[$class]->allowExtension) . ')');
            }

        }

        $this->checkAction();
    }


    private function checkAction() {
        $salida = false;

        switch ($this->action) {
            case 'import':
                $salida = $this->import();
            break;
            case 'delete-log':
                $log = base64_decode($_GET['log']);
                $this->removeLog($log);
            break;
            case 'view-log':
                $log = base64_decode($_GET['log']);
                $this->log = $this->getLog($log);
            break;
        }

        return $salida;
    }

    private function import() {

        if ($this->active) {
            $include = $this->active;
            $import = $this->importadores[$include];
			if( $this->crontag )
				$import->setFileCron($this->active);
			else
				$import->setFile();

			$import->importData();
			$this->result = $import->saveData();

			if( !$this->crontag )
				tep_redirect(tep_href_link('update_masive_orders.php'));
        }
    }

    public function getLogs() {
        $archivosTemp  = scandir(PATH_UPDATE_MASIVE_ORDERS . 'log/');
        $archivos = array();
        foreach ($archivosTemp as $archivo) {
            if (strlen($archivo) > 2) {
                $filemtime = filemtime(PATH_UPDATE_MASIVE_ORDERS . 'log/'.$archivo);
                $archivos[] = array(
                    'archivo' => $archivo,
                    'fecha' => date ("d/m/Y H:i:s",$filemtime)
                );
            }
		}
        return array_reverse($archivos);
    }

    public function getLog($log) {
        if ($log != '' && file_exists(PATH_UPDATE_MASIVE_ORDERS . 'log/'.$log)) {
            return file_get_contents(PATH_UPDATE_MASIVE_ORDERS . 'log/'.$log);
        }

    }

    private function removeLog($log) {
        global $messageStack;

        if (file_exists(PATH_UPDATE_MASIVE_ORDERS . 'log/'.$log)) {
            unlink(PATH_UPDATE_MASIVE_ORDERS . 'log/'.$log);
            $messageStack->add_session('Archivo borrado con éxito', 'success');
        } else {
            $messageStack->add_session('No se ha encontrado el archivo: ' . $log, 'warning');
        }

        tep_redirect(tep_href_link('update_masive_orders.php'));
    }
}
