<?php

class importador_dhl extends importador {

    function __construct() {
        $this->nombre = 'DHL';
        $this->valor = 'dhl';
        $this->active = true;
        $this->allowExtension = array('dat');
    }

    public function importData() {
        if (file_exists($this->file)) {
            $lineas = file($this->file);
            foreach ($lineas as $linea) {
				$linea = stripslashes(trim($linea));

				if (substr($linea, 0, 3) == "\xef\xbb\xbf") {
					$linea = substr($linea, 3);
				}

                $order['oID'] = trim( substr($linea, 12, 35) );

                $anio = (int)substr($linea, 66, 4);
	
                $mes = substr($linea, 70, 2);
                $dia = substr($linea, 72, 2);
                $hora = substr($linea, 74, 2).':'.substr($linea, 76, 2).':00';
                $order['fecha'] = $anio.'-'.$mes.'-'.$dia.' ' .$hora;

                $estado = strtolower(substr($linea, 143, 6));
                if (is_numeric($order['oID'])) {
                    if ($estado == '101999') {
                        $order['estado'] = 'entregado';
                    } elseif ($estado == '900999') {
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
