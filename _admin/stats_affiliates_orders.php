<?php

/**
 * #XCC-313-91043
 * @author Daniel Lucia <daniel.lucia@denox.es>
 */
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require 'includes/application_top.php';

// Variables
$sUrlPage = 'stats_affiliates_orders.php';
$sTitle = 'Informe de pedidos de afiliados';
$sPostAction = (isset($_GET['action']) ? tep_db_prepare_input($_GET['action']) : false);
$sSubtitle = '';

$sHtml = '';
$and = false;

# Messagestack estilo
$messageStack->style = 'solenopsis';

$dateFrom = (isset($_GET['date_from']) && $_GET['date_from'] != '' ? tep_db_prepare_input($_GET['date_from']) : false);
$dateTo = (isset($_GET['date_to']) && $_GET['date_to'] != '' ? tep_db_prepare_input($_GET['date_to']) : false);
$affiliate = (isset($_GET['affiliate']) && $_GET['affiliate'] != '' ? preg_replace('/\"/i', '\"', tep_db_prepare_input($_GET['affiliate'])) : false);

if ($dateFrom !== false) {
    $dateFromFormat = explode('/', $dateFrom);
    $dateFromFormat = $dateFromFormat[2] . '-' . $dateFromFormat[1] . '-' . $dateFromFormat[0];
}

if ($dateTo !== false) {
    $dateToFormat = explode('/', $dateTo);
    $dateToFormat = $dateToFormat[2] . '-' . $dateToFormat[1] . '-' . $dateToFormat[0];
}

// Acciones
switch ($sPostAction) {
    case 'export':
        $results = array();
        $index = 0;
        $spread = new Spreadsheet();

        // Sql
        $sSql = 'SELECT a.id AS affiliates_id, c.customers_id, c.customers_firstname, c.customers_lastname, c.customers_email_address, a.username_social_networks, o.orders_id, op.products_name, op.products_cost AS cost, op.products_quantity, p.products_cost FROM orders o INNER JOIN affiliates a ON (o.customers_id = a.customers_id) INNER JOIN customers c ON (a.customers_id = c.customers_id) INNER JOIN orders_products op ON (o.orders_id = op.orders_id) LEFT OUTER JOIN products p ON (op.products_id = p.products_id)';

        if ($dateFrom !== false || $dateTo !== false || $affiliate !== false) {
            $sSql .= ' WHERE ';
        }

        if ($dateFrom !== false) {
            $sSql .= 'o.date_purchased >= "' . $dateFromFormat . '"';
            $and = true;
        }

        if ($dateTo !== false) {
            $sSql .= ($and ? ' AND ' : '') . 'o.date_purchased <= "' . $dateToFormat . '"';
            $and = true;
        }

        if ($affiliate !== false) {
            $sSql .= ($and ? ' AND ' : '') . '(a.id = "' . $affiliate . '" OR c.customers_firstname LIKE "%' . $affiliate . '%" OR c.customers_lastname LIKE "%' . $affiliate . '%" OR CONCAT(c.customers_firstname, " ", c.customers_lastname) LIKE "%' . $affiliate . '%" OR c.customers_email_address LIKE "%' . $affiliate . '%" OR a.username_social_networks LIKE "%' . $affiliate . '%")';
            $and = true;
        }

        $sSql = preg_replace('/[\r\n\t]+/', ' ', $sSql);

        $datas = tep_db_query($sSql);

        while ($data = tep_db_fetch_array($datas)) {
            if (!isset($results[$data['customers_id']])) {
                $results[$data['customers_id']] = array(
                    'affiliates_id' => $data['affiliates_id'],
                    'firstname' => $data['customers_firstname'],
                    'lastname' => $data['customers_lastname'],
                    'email' => $data['customers_email_address'],
                    'username' => $data['username_social_networks'],
                    'orders' => array(),
                );
            }

            if (!isset($results[$data['customers_id']]['orders'][$data['orders_id']])) {
                $results[$data['customers_id']]['orders'][$data['orders_id']] = array(
                    'products' => array(),
                );
            }

            $results[$data['customers_id']]['orders'][$data['orders_id']]['products'][] = array(
                'name' => $data['products_name'],
                'quantity' => $data['products_quantity'],
                'cost' => ($data['cost'] == '' || $data['cost'] == 0 ? ($data['products_cost'] == '' || $data['products_cost'] == 0 ? 0 : $data['products_cost']) : $data['cost']),
            );
        }

        $spread->getProperties()->setCreator("Denox")->setTitle('Devoluciones Productos no enviados');

        foreach ($results as $customers_id => $result) {
            $spread->createSheet();
            $spread->setActiveSheetIndex($index);
            $spread->getActiveSheet()->setTitle(preg_replace('/\//i', '', substr($result['username'], 0, 20)));
            $sheet = $spread->getActiveSheet();

            $sheet->setCellValue('A1', 'ID');
            $sheet->getColumnDimension('A')->setAutoSize(true);
            $sheet->setCellValue('B1', 'Afiliado');
            $sheet->getColumnDimension('B')->setAutoSize(true);
            $sheet->setCellValue('C1', 'Nombre');
            $sheet->getColumnDimension('C')->setAutoSize(true);
            $sheet->setCellValue('D1', 'Email');
            $sheet->getColumnDimension('D')->setAutoSize(true);
            $sheet->setCellValue('E1', 'Pedido');
            $sheet->getColumnDimension('E')->setAutoSize(true);
            $sheet->setCellValue('F1', 'Producto');
            $sheet->getColumnDimension('F')->setAutoSize(true);
            $sheet->setCellValue('G1', 'Cantidad');
            $sheet->getColumnDimension('G')->setAutoSize(true);
            $sheet->setCellValue('H1', 'Coste');
            $sheet->getColumnDimension('H')->setAutoSize(true);
            $sheet->setCellValue('I1', 'Total');
            $sheet->getColumnDimension('I')->setAutoSize(true);

            $sheet->getStyle('A1:I1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('404646');
            $sheet->getStyle('A1:I1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFF');

            $sheet->setCellValue('A2', $customers_id);
            $sheet->setCellValue('B2', $result['username']);
            $sheet->setCellValue('C2', $result['firstname'] . ' ' . $result['lastname']);
            $sheet->setCellValue('D2', $result['email']);

            $line = 2;

            $sumQty = 0;
            $sumCost = 0;

            foreach ($result['orders'] as $orders_id => $order) {
                $sheet->setCellValue('E' . $line, '# ' . $orders_id);

                foreach ($order['products'] as $product) {
                    $sheet->setCellValue('F' . $line, $product['name']);
                    $sheet->setCellValue('G' . $line, $product['quantity']);
                    $sheet->setCellValue('H' . $line, number_format($product['cost'], 2, ',', '') . ' €');
                    $sheet->setCellValue('I' . $line, number_format($product['cost'] * $product['quantity'], 2, ',', '') . ' €');

                    $sumQty += $product['quantity'];
                    $sumCost += $product['cost'] * $product['quantity'];

                    ++$line;
                }

                ++$line;
            }

            $sheet->setCellValue('H' . $line, $sumQty . ' producto(s)');
            $sheet->getColumnDimension('H')->setAutoSize(true);
            $sheet->setCellValue('I' . $line, number_format($sumCost, 2, ',', '') . ' €');
            $sheet->getColumnDimension('I')->setAutoSize(true);
            $sheet->getStyle('H' . $line . ':I' . $line)->getFont()->setBold(true);

            ++$index;
        }

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="stats_affiliates_orders' . ($dateFrom !== false ? '_' . $dateFromFormat : '') . ($dateTo !== false ? '_' . $dateToFormat : '') . '.xls"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spread);
        $writer->save('php://output');
        exit;
        break;
    default:
        // Variables
        $sSubtitle = 'Resultado';
        $records = array();

        // Sql
        $sSql = 'SELECT a.id AS affiliates_id, c.customers_id, c.customers_firstname, customers_lastname, a.username_social_networks, COUNT(DISTINCT o.orders_id) AS orders_qty, SUM(IF(op.products_cost != "" AND op.products_cost != 0, op.products_cost, IF(p.products_cost != "" AND p.products_cost != 0, p.products_cost, 0)) * op.products_quantity) AS sum_cost FROM orders o INNER JOIN affiliates a ON (o.customers_id = a.customers_id) INNER JOIN customers c ON (a.customers_id = c.customers_id) INNER JOIN orders_products op ON (o.orders_id = op.orders_id) LEFT OUTER JOIN products p ON (op.products_id = p.products_id)';

        if ($dateFrom !== false || $dateTo !== false || $affiliate !== false) {
            $sSql .= ' WHERE ';
        }

        if ($dateFrom !== false) {
            $sSql .= 'o.date_purchased >= "' . $dateFromFormat . '"';
            $and = true;
        }

        if ($dateTo !== false) {
            $sSql .= ($and ? ' AND ' : '') . 'o.date_purchased <= "' . $dateToFormat . '"';
            $and = true;
        }

        if ($affiliate !== false) {
            $sSql .= ($and ? ' AND ' : '') . '(a.id = "' . $affiliate . '" OR c.customers_firstname LIKE "%' . $affiliate . '%" OR c.customers_lastname LIKE "%' . $affiliate . '%" OR CONCAT(c.customers_firstname, " ", c.customers_lastname) LIKE "%' . $affiliate . '%" OR c.customers_email_address LIKE "%' . $affiliate . '%" OR a.username_social_networks LIKE "%' . $affiliate . '%")';
            $and = true;
        }

        $sSql .= ' GROUP BY c.customers_id';

        $sSql = preg_replace('/[\r\n\t]+/', ' ', $sSql);

        $datas = tep_db_query($sSql);

        // Tabla
        $sHtml .= '<div class="oeBox oeTable column a12 row ax">';
        $sHtml .= '<div class="oeWrpr">';
        $sHtml .= '<div class="oeTitu"><i class="fa fa-eye"></i> Resultado</div>';
        $sHtml .= '<form method="get" action="' . tep_href_link($sUrlPage) . '" class="oeCntd row ax" id="filter_form">';

        $sHtml .= '<div class="oeBoxFltr column a12 ax row">';
        $sHtml .= '<div class="column a01 row ax amiddle">';
        $sHtml .= 'Fechas:';
        $sHtml .= '</div>';
        $sHtml .= '<div class="column a02 row ax amiddle">';
        $sHtml .= '<input type="text" name="date_from" value="' . $dateFrom . '" class="dxdatepicker" data-autoupdate="true" autocomplete="off" />';
        $sHtml .= '</div>';
        $sHtml .= '<div class="column a01 row ax amiddle">';
        $sHtml .= '<span style="text-align: center; width: 100%;">hasta</span>';
        $sHtml .= '</div>';
        $sHtml .= '<div class="column a02 row ax amiddle">';
        $sHtml .= '<input type="text" name="date_to" value="' . $dateTo . '" class="dxdatepicker" data-autoupdate="true" autocomplete="off" />';
        $sHtml .= '</div>';
        $sHtml .= '<div class="column a02 row ax aright">';
        $sHtml .= '&nbsp;<div style="height: 26px; top: 5px; text-align: center; line-height: 26px; padding: 0px 10px; margin-left: 30px;" class="xbutton verde hv9 small"><input type="submit"/><span class="fa fa-filter"></span> Filtrar</div>';
        $sHtml .= '</div>';
        if ($dateFrom !== false || $dateTo !== false || $affiliate !== false) {
            $sHtml .= '<div class="column a02 row ax aright">';
            $sHtml .= '&nbsp;<div style="height: 26px; top: 5px; text-align: center; line-height: 26px; padding: 0px 10px;" class="xbutton rojo hv9 small"><a href="' . $sUrlPage . '" style="color: #fff; text-decoration: none;"><span class="fa fa-close"></span> Limpiar</a></div>';
            $sHtml .= '</div>';
        }
        $sHtml .= '</div>';

        $sHtml .= '<div class="oeBoxFltr column a12 ax row">';
        $sHtml .= '<div class="column a01 row ax amiddle">';
        $sHtml .= 'Afiliado:';
        $sHtml .= '</div>';
        $sHtml .= '<div class="column a08 row ax amiddle">';
        $sHtml .= '<input type="text" name="affiliate" value="' . $affiliate . '" placeholder="Busca afiliados por su ID, nombre, email o usuario de RRSS" style="width: 100%;" />';
        $sHtml .= '</div>';
        $sHtml .= '</div>';

        $sHtml .= '<table class="xform">';
        $sHtml .= '<thead>';
        $sHtml .= '<tr>';
        $sHtml .= '<th width="200px;">Afiliado</th>';
        $sHtml .= '<th>Nombre</th>';
        $sHtml .= '<th>Pedidos</th>';
        $sHtml .= '<th>Coste total</th>';
        $sHtml .= '<th width="150px;">Acciones</th>';
        $sHtml .= '</tr>';
        $sHtml .= '</thead>';
        $sHtml .= '<tbody>';

        while ($data = tep_db_fetch_array($datas)) {
            $sHtml .= '<tr>';
            $sHtml .= '<td><a href="affiliates.php?action=view&id=' . $data['affiliates_id'] . '" target="_blank"># ' . $data['username_social_networks'] . '</a></td>';
            $sHtml .= '<td>' . $data['customers_firstname'] . ' ' . $data['customers_lastname'] . '</td>';
            $sHtml .= '<td>' . $data['orders_qty'] . ' pedido(s)</td>';
            $sHtml .= '<td>' . number_format($data['sum_cost'], 2, ',', '') . ' €</td>';
            $sHtml .= '<td><div class="xbutton hv9 small"><a href="' . $sUrlPage . '?action=export' . ($dateFrom !== false ? '&date_from=' . $dateFrom : '') . ($dateTo !== false ? '&date_to=' . $dateTo : '') . '&affiliate=' . $data['affiliates_id'] . '" style="color: #fff; text-decoration: none;"><span class="fa fa-file-excel"></span> Exportar</a></div></td>';
            $sHtml .= '</tr>';
        }

        $sHtml .= '</tbody>';
        $sHtml .= '</table>';

        $sHtml .= '</div>';
        $sHtml .= '</form>';
        $sHtml .= '</div>';
        $sHtml .= '</div>';
        break;
}

// Reemplazamos variable
$sHtmlModuleOe = $sHtml;

// MessageStack
$sMessageStack = $messageStack->output(false);
$messageStack->reset();

// Header
include 'theme/solenopsis/html/header.php';

// Cabecera
echo '<div class="oeHead column a12 row ax amiddle aflex">';
echo '<div class="oeTitu column logo afixed" style="padding-left: 59px;"><b><i class="fa fa-clone"></i> ' . $sTitle . '</b>' . ($sSubtitle ? '<small>' . $sSubtitle . '</small>' : '') . '</div>';
echo '<div class="btn-right" style="position: absolute; right: 15px;">';
echo '<a href="' . $sUrlPage . '?action=export' . ($dateFrom !== false ? '&date_from=' . $dateFrom : '') . ($dateTo !== false ? '&date_to=' . $dateTo : '') . ($affiliate !== false ? '&affiliate=' . $affiliate : '') . '" title="Exportar a Excel"><img class="dx-hovr" src="images/icons/icon_export_excel.png"></a>';
echo '</div>';
echo '</div>';

// Mensajes
echo $sMessageStack;

// Pintamos
echo $sHtmlModuleOe;

// Footer
include 'theme/solenopsis/html/footer.php';
