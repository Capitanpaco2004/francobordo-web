<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

// Función para exportar a Excel
function exportSubscribersToExcel() {
	// Variables de filtro
	$sWhere = '';
	$aFiler = ['search' => ''];
	$aAuxFilter = array_key_exists('filter', $_GET) && is_array($_GET['filter']) ? $_GET['filter'] : (array_key_exists('filter', $_POST) && is_array($_POST['filter']) ? $_POST['filter'] : []);
	array_walk($aFiler, function ($value, $key) use (&$aFiler, $aAuxFilter) {
		$aFiler[$key] = tep_db_prepare_input(array_key_exists($key, $aAuxFilter) ? $aAuxFilter[$key] : $aFiler[$key]);
	});

	if ($aFiler['search'] != '') {
		$sWhere = 'WHERE LOWER(sub.subscribers_lastname) LIKE "%' . strtolower($aFiler['search']) . '%" OR LOWER(sub.subscribers_firstname) LIKE "%' . strtolower($aFiler['search']) . '%" OR LOWER(sub.subscribers_email_address) LIKE "%' . strtolower($aFiler['search']) . '%"';
	}

	// Consulta SQL
	$query = "SELECT sub.subscribers_lastname, sub.subscribers_firstname, sub.subscribers_email_address, sub.customers_newsletter
              FROM subscribers sub
              $sWhere
              ORDER BY sub.subscribers_id DESC";

	// Ejecución de la consulta
	$result = tep_db_query($query);

	// Crear archivo Excel
	$spreadsheet = new Spreadsheet();
	$sheet = $spreadsheet->getActiveSheet();

	// Encabezados
	$sheet->setCellValue('A1', 'Nombre');
	$sheet->setCellValue('B1', 'Apellido');
	$sheet->setCellValue('C1', 'Correo Electrónico');

	// Agregar datos
	$row = 2;
	while ($subscriber = tep_db_fetch_array($result)) {
		$sheet->setCellValue("A$row", $subscriber['subscribers_firstname']);
		$sheet->setCellValue("B$row", $subscriber['subscribers_lastname']);
		$sheet->setCellValue("C$row", $subscriber['subscribers_email_address']);
		$row++;
	}

	// Configurar encabezados para descarga
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment;filename="suscriptores-' . date('Y-m-d') . '.xlsx"');
	header('Cache-Control: max-age=0');

	$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
	$writer->save('php://output');
	exit;
}
