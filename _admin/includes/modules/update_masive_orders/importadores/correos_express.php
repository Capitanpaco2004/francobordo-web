<?php

class importador_correos_express extends importador {

    function __construct() {
        $this->nombre = 'Correos Express';
        $this->valor = 'correos_express';
        $this->active = true;
        $this->allowExtension = array('xls', 'xlsx');
    }

    public function _convertDateToMySql($date)
        {
            $date = str_replace('/', '-', $date);
            $dFecha = new DateTime($date);
            //dd($dFecha);
            //$dFecha->setTime(0, 0, 0);

            return $dFecha->format('Y-m-d H:i:s');
        }

    public function importData() {
        if (file_exists($this->file)) {
            try {
                $inputFileType = \PhpOffice\PhpSpreadsheet\IOFactory::identify($this->file);
                $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
                $spreadsheet = $objReader->load($this->file);
            } catch (Exception $e) {
                die('Error cargando  "' . pathinfo($this->file, PATHINFO_BASENAME) . '": ' . $e->getMessage());
            }

            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestDataColumn();

            for ($row = 1; $row <= $highestRow; $row++) {
                $campos = $sheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, null, true, false);

                $campos = $campos[0];

                if ($campos[19] != '' && strpos($campos[19], '/') !== false) {
                    $order['fecha'] = $campos[19].':00';
                    $order['fecha'] = $this->_convertDateToMySql($order['fecha']);
                }
                $order['oID'] = (int)str_replace('f', '', strtolower($campos[4]));
                //echo '<pre>'.print_r($campos, 1).'</pre>';
                //echo '<pre>'.print_r($order['oID'], 1).'</pre>';
                if ($order['oID'] > 0) {
                    if (strpos(strtolower($campos[18]), 'entrega') !== false) {
                        $order['estado'] = 'entregado';
                    } elseif (strpos(strtolower($campos[18]), 'devuelto') !== false) {
                        $order['estado'] = 'devuelto';
                    } else {
                        $order['estado'] = $campos[18];
                    }

                    $this->content[] = $order;
                }
            }
        }
    }
}
