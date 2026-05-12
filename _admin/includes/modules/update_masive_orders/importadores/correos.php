<?php

class importador_correos extends importador {

    function __construct() {
        $this->nombre = 'Correos';
        $this->valor = 'correos';
        $this->active = true;
        $this->allowExtension = array('csv', 'txt');
    }

    public function importData() {
        if (file_exists($this->file)) {
            $lineas = file($this->file);
            foreach ($lineas as $linea) {
                $campos = preg_split("/[\t]/", $linea);

                if (isset($campos[3]) && (int)$campos[3] > 0) {
                    $order['oID'] = (int)$campos[3];

                    if ($campos[6] != '' && $campos[7] != '') {
                        $anio = substr($campos[6], 0, 4);
                        $mes = substr($campos[6], 4, 2);
                        $dia = substr($campos[6], 6, 2);
                        $hora = $campos[7].':00';
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
