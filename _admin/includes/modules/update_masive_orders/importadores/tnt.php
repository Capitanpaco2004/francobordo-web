<?php

class importador_tnt extends importador {

    function __construct() {
        $this->nombre = 'TNT';
        $this->valor = 'tnt';
        $this->active = true;
        $this->allowExtension = array('txt');
    }

    public function importData() {
        if (file_exists($this->file)) {
            $lineas = file($this->file);
            foreach ($lineas as $linea) {
				$campos = str_getcsv($linea, ",", '"');

                if (isset($campos[10]) && (int)$campos[10] > 0) {
                    $order['oID'] = (int)$campos[10];

                    if (preg_match( '/^Entregado/i', $campos[55] )) {
                        $anio = substr($campos[53], 0, 4);
                        $mes = substr($campos[53], 4, 2);
                        $dia = substr($campos[53], 6, 2);
                        $hora = substr($campos[54], 0, 2) . ':' . substr($campos[54], 2, 2);
                        $order['fecha'] = $anio.'-'.$mes.'-'.$dia.' ' .$hora;

						$order['estado'] = 'entregado';
					}
					else
						$order['estado'] = $campos[55];

                    $this->content[] = $order;
                }
            }
        }
    }

}
