<?php

class importador_gls extends importador {

    function __construct() {
        $this->nombre = 'GLS';
        $this->valor = 'gls';
        $this->active = true;
        $this->allowExtension = array('csv', 'txt');
    }

    public function importData() {
        if (file_exists($this->file)) {
            $lineas = file($this->file);
            foreach ($lineas as $linea) {
                $campos = preg_split("/[\t]/", $linea);
                if (isset($campos[9]) && (int)$campos[9] > 0) {
                    $order['oID'] = (int)$campos[9];

                    if ($campos[6] != '' && $campos[7] != '') {
                        $anio = substr($campos[6], 0, 4);
                        $mes = substr($campos[6], 4, 2);
                        $dia = substr($campos[6], 6, 2);
                        $hora = substr($campos[7], 0, 2).':'.substr($campos[7], 2, 2).':00';
                        $order['fecha'] = $anio.'-'.$mes.'-'.$dia.' ' .$hora;
                    }

                    if (strtolower($campos[5]) == 'entregado') {
                        $order['estado'] = 'entregado';
                    } elseif (strpos(strtolower($campos[5]), 'devuelto') !== false) {
                        $order['estado'] = 'devuelto';
                    } else {
                        $order['estado'] = $campos[5];
                    }

                    $this->content[] = $order;
                }

            }
        }
    }

}
