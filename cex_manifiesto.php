<?php
/**
 * Manifiesto / Relación de entrega CORREOS EXPRESS (PDF del día) — para el PC del almacén.
 *
 * Se construye localmente a partir de cex_shipments (tipo 'envio', ok=1, no anulados,
 * entorno pro, del día). Es la relación de envíos que se entrega al repartidor de CEX.
 * Gemelo de correos_manifiesto.php (generación local con TCPDF, casilla de firma).
 *
 * GET /cex_manifiesto.php?token=...&date=YYYY-MM-DD
 *   token  (obligatorio)
 *   date   opcional, def. hoy (Europe/Madrid). Sin date = hoy + fin de semana pendiente.
 *
 * Respuesta: PDF (attachment) o JSON {ok:false,error} si no hay envíos/fallo.
 * Ver memoria francobordo_correos_express_api.
 */
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');
date_default_timezone_set('Europe/Madrid');

define('CEX_MAN_TOKEN', 'cexman_7d2b9f04a1');

$in = array_merge($_GET, $_POST);
function out_json($arr) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($in['token'] ?? '') !== CEX_MAN_TOKEN) {
    http_response_code(403);
    out_json(array('ok' => false, 'error' => 'forbidden'));
}

$date = trim((string) ($in['date'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
$dt = DateTime::createFromFormat('Y-m-d', $date);
if (!$dt || $dt->format('Y-m-d') !== $date) $date = date('Y-m-d');

chdir(__DIR__);
include 'includes/configure.php';

mysqli_report(MYSQLI_REPORT_OFF);
$db = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($db->connect_errno) { error_log('cex_manifiesto db: ' . $db->connect_error); out_json(array('ok' => false, 'error' => 'db no disponible')); }
$db->set_charset('utf8mb4');

/* Con date= explícito: solo ese día. Sin date: HOY + días no laborables inmediatamente
 * anteriores (el repartidor no pasa sáb/dom), así el lunes salen los del finde. */
$explicitDay = (isset($in['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) $in['date'])) && $dt && $dt->format('Y-m-d') === trim((string) $in['date'])) ? $date : '';
if ($explicitDay !== '') {
    $whereDate = "DATE(s.date_added)=?"; $bindDate = $explicitDay;
} else {
    $mFrom = date('Y-m-d'); $probe = strtotime($mFrom);
    for ($i = 0; $i < 3; $i++) {
        $prevTs = strtotime('-1 day', $probe);
        if ((int) date('N', $prevTs) >= 6) { $mFrom = date('Y-m-d', $prevTs); $probe = $prevTs; }
        else break;
    }
    $whereDate = "DATE(s.date_added) >= ?"; $bindDate = $mFrom;
}

$st = $db->prepare("SELECT s.id, s.orders_id, s.albaran_id, s.shipment_code, s.ecb, s.product_code, s.ref, s.kilos,
                           s.request_json, s.date_added,
                           o.delivery_name, o.delivery_city, o.delivery_postcode, o.delivery_state
                    FROM cex_shipments s
                    LEFT JOIN orders o ON o.orders_id = s.orders_id
                    WHERE s.tipo='envio' AND s.ok=1 AND s.entorno='pro' AND s.cancelled_at IS NULL
                      AND $whereDate
                    ORDER BY s.id ASC");
if (!$st) { error_log('cex_manifiesto prepare: ' . $db->error); out_json(array('ok' => false, 'error' => 'consulta no disponible')); }
$st->bind_param('s', $bindDate);
$st->execute();
$res = $st->get_result();

$rows = array();
$totBultos = 0; $totKg = 0.0;
while ($r = $res->fetch_assoc()) {
    // Destinatario: preferimos el pedido (autoritativo); si no (manual/QFac), el request_json.
    $j = json_decode((string) $r['request_json'], true);
    $jd = is_array($j) ? $j : array();
    $name = trim((string) ($r['delivery_name'] ?: ($jd['nomDest'] ?? '')));
    $loc  = trim((string) ($r['delivery_city'] ?: ($jd['pobDest'] ?? '')));
    $cp   = trim((string) ($r['delivery_postcode'] ?: ($jd['codPosNacDest'] ?? ($jd['codPosIntDest'] ?? ''))));
    $prov = trim((string) ($r['delivery_state'] ?? ''));
    $bultos = max(1, (int) ($jd['numBultos'] ?? 1));
    $peso = (float) $r['kilos'];
    if ($peso <= 0 && isset($jd['kilos'])) $peso = (float) str_replace(',', '.', (string) $jd['kilos']);
    $totBultos += $bultos;
    $totKg += $peso;
    $rows[] = array(
        'ref'     => (string) $r['ref'],
        'oid'     => (string) $r['orders_id'],
        'name'    => $name,
        'destino' => trim($loc . ($cp !== '' ? ' (' . $cp . ($prov !== '' ? '/' . $prov : '') . ')' : '')),
        'bultos'  => $bultos,
        'peso'    => $peso,
        'sc'      => (string) $r['shipment_code'],
        'ecb'     => (string) $r['ecb'],
        'hora'    => substr((string) $r['date_added'], 11, 5),
    );
}

if (!$rows) {
    out_json(array('ok' => false, 'error' => ($explicitDay !== '' ? 'Sin envios Correos Express registrados el ' . $explicitDay . '.' : 'Sin envios Correos Express pendientes de entregar.')));
}

/* ---- PDF con TCPDF ---- */
require_once 'includes/vendor/tecnickcom/tcpdf/tcpdf.php';

class CexManifest extends TCPDF {
    public $fechaManifiesto = '';
    public $totEnvios = 0;
    public function Header() {
        $this->SetFont('helvetica', 'B', 13);
        $this->SetTextColor(210, 0, 40);
        $this->Cell(0, 7, 'RELACIÓN DE ENTREGA · CORREOS EXPRESS', 0, 1, 'L');
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 4, 'FRANCOBORDO S.L. · NIF B82574690 · Cliente Correos Express 632140001', 0, 1, 'L');
        $this->Cell(0, 4, 'Fecha: ' . $this->fechaManifiesto . '   ·   Envíos: ' . $this->totEnvios, 0, 1, 'L');
        $this->Ln(1);
        $this->SetDrawColor(210, 0, 40);
        $this->SetLineWidth(0.3);
        $this->Line($this->GetX(), $this->GetY(), $this->GetX() + 277, $this->GetY());
        $this->Ln(2);
    }
    public function Footer() {
        $this->SetY(-12);
        $this->SetFont('helvetica', '', 7);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 4, 'Generado ' . date('Y-m-d H:i') . ' · Francobordo', 0, 0, 'L');
        $this->Cell(0, 4, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}

$pdf = new CexManifest('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->fechaManifiesto = $date;
$pdf->totEnvios = count($rows);
$pdf->SetCreator('Francobordo');
$pdf->SetAuthor('Francobordo S.L.');
$pdf->SetTitle('Manifiesto Correos Express ' . $date);
$pdf->SetMargins(10, 26, 10);
$pdf->SetHeaderMargin(6);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(true, 16);
$pdf->AddPage();

$h = '<table border="0.5" cellpadding="3" style="border-color:#999;">';
$h .= '<thead><tr style="background-color:#D2002A;color:#FFFFFF;font-weight:bold;font-size:8px;">'
    . '<th width="4%">Nº</th>'
    . '<th width="9%">Pedido</th>'
    . '<th width="26%">Destinatario</th>'
    . '<th width="22%">Destino</th>'
    . '<th width="6%">Bultos</th>'
    . '<th width="7%">Peso&nbsp;kg</th>'
    . '<th width="14%">Nº envío</th>'
    . '<th width="12%">Cód. barras</th>'
    . '</tr></thead><tbody>';
$i = 0;
foreach ($rows as $r) {
    $i++;
    $bg = ($i % 2 === 0) ? ' style="background-color:#FBEFF1;"' : '';
    $h .= '<tr' . $bg . '>'
        . '<td width="4%" align="center">' . $i . '</td>'
        . '<td width="9%">' . htmlspecialchars($r['ref'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td width="26%">' . htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td width="22%">' . htmlspecialchars($r['destino'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td width="6%" align="center">' . $r['bultos'] . '</td>'
        . '<td width="7%" align="right">' . number_format($r['peso'], 2, ',', '.') . '</td>'
        . '<td width="14%" style="font-size:7px;">' . htmlspecialchars($r['sc'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td width="12%" style="font-size:7px;">' . htmlspecialchars($r['ecb'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '</tr>';
}
$h .= '</tbody><tfoot><tr style="background-color:#F6D9DF;font-weight:bold;">'
    . '<td width="39%" align="right">TOTALES</td>'
    . '<td width="22%"></td>'
    . '<td width="6%" align="center">' . $totBultos . '</td>'
    . '<td width="7%" align="right">' . number_format($totKg, 2, ',', '.') . '</td>'
    . '<td width="26%"></td>'
    . '</tr></tfoot></table>';

$pdf->SetFont('helvetica', '', 8);
$pdf->writeHTML($h, true, false, false, false, '');

$pdf->Ln(6);
$pdf->SetFont('helvetica', '', 9);
$y = $pdf->GetY();
if ($y + 22 > $pdf->getPageHeight() - $pdf->getBreakMargin()) { $pdf->AddPage(); $y = $pdf->GetY(); }
$pdf->MultiCell(130, 22, "\nEntregado por (Francobordo):\n\n\nFirma y fecha", 1, 'L', false, 0, 12, $y, true);
$pdf->MultiCell(130, 22, "\nRecibido por (Correos Express):\n\n\nNombre, firma y fecha", 1, 'L', false, 1, 155, $y, true);

$bin = $pdf->Output('', 'S');

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Manifiesto_CorreosExpress_' . $date . '.pdf"');
header('Content-Length: ' . strlen($bin));
header('Cache-Control: private, no-store');
echo $bin;
