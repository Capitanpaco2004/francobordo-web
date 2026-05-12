<?php

class importador_seur extends importador {

    function __construct() {
        $this->nombre = 'Seur';
        $this->valor = 'seur';
        $this->active = true;
        $this->allowExtension = array('txt');
    }

    public function importData() {
        if (file_exists($this->file)) {
            $lineas = file($this->file);
            foreach ($lineas as $linea) {
				$linea = stripslashes(trim($linea));

				if (substr($linea, 0, 3) == "\xef\xbb\xbf") {
					$linea = substr($linea, 3);
				}

                $order['oID'] = substr($linea, 30, 8);

                $anio = (int)substr($linea, 72, 2) + 2000;
	
                $mes = substr($linea, 70, 2);
                $dia = substr($linea, 68, 2);
                $hora = substr($linea, 74, 2).':'.substr($linea, 76, 2).':00';
                $order['fecha'] = $anio.'-'.$mes.'-'.$dia.' ' .$hora;

                $estado = strtolower(substr($linea, 64, 3));
                if (is_numeric($order['oID'])) {
                    if ($estado == 'l00') {
                        $order['estado'] = 'entregado';
                    } elseif ($estado == 'l30') {
                        $order['estado'] = 'devuelto';
                    } else {
                        $order['estado'] = $estado;
                    }

                    $this->content[] = $order;
                }

            }
        }
    }
}
