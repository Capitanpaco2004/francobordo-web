<?php
/**
 * Manifiesto / Relación de entrega CORREOS (PDF del día) — para el PC del almacén.
 *
 * Correos NO tiene endpoint de manifiesto en su API (a diferencia de SEUR), así que
 * el documento se construye localmente a partir de correos_shipments (tipo 'envio',
 * ok=1, no anulados, del día). Es la relación de envíos que se entrega al repartidor.
 *
 * GET /correos_manifiesto.php?token=...&date=YYYY-MM-DD
 *   token  (obligatorio)
 *   date   opcional, def. hoy (Europe/Madrid)
 *
 * Respuesta: PDF (attachment) o JSON {ok:false,error} si no hay envíos/fallo.
 * Patrón: seur_manifiesto.php (pero generación local, no por API).
 * Ver memoria francobordo_correos_api.
 */
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');
date_default_timezone_set('Europe/Madrid');

define('CORREOS_MAN_TOKEN', 'correosman_5f3a9c21e7');

$in = array_merge($_GET, $_POST);
function out_json($arr) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($in['token'] ?? '') !== CORREOS_MAN_TOKEN) {
    http_response_code(403);
    out_json(array('ok' => false, 'error' => 'forbidden'));
}

$date = trim((string) ($in['date'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
// Validar que es una fecha real (no 2026-13-40)
$dt = DateTime::createFromFormat('Y-m-d', $date);
if (!$dt || $dt->format('Y-m-d') !== $date) $date = date('Y-m-d');

chdir(__DIR__);
include 'includes/configure.php';

mysqli_report(MYSQLI_REPORT_OFF);   // PHP 8.1+: no lanzar excepciones; comprobamos retornos a mano
$db = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($db->connect_errno) { error_log('correos_manifiesto db: ' . $db->connect_error); out_json(array('ok' => false, 'error' => 'db no disponible')); }
$db->set_charset('utf8mb4');

/* Envíos del día (entorno pro), no anulados, con etiqueta efectiva */
/* Por defecto (sin date=) el manifiesto = TODO LO PENDIENTE de entregar al repartidor:
 * envíos registrados/PRE-ADMISIÓN aún SIN recoger (últimos 7 días) → incluye los del
 * fin de semana (el repartidor no pasa sáb/dom). Con date=YYYY-MM-DD: solo ese día
 * (reimpresión puntual). Generación LOCAL, así filtramos los ya recogidos con precisión. */
$explicitDay = (isset($in['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) $in['date'])) && $dt && $dt->format('Y-m-d') === trim((string) $in['date'])) ? $date : '';
if ($explicitDay !== '') {
    $st = $db->prepare("SELECT id, orders_id, albaran_id, shipment_code, package_code, producto, ref, kilos,
                               tracking_url, request_json, date_added
                        FROM correos_shipments
                        WHERE tipo='envio' AND ok=1 AND entorno='pro' AND cancelled_at IS NULL
                          AND DATE(date_added)=?
                        ORDER BY id ASC");
    if (!$st) { error_log('correos_manifiesto prepare: ' . $db->error); out_json(array('ok' => false, 'error' => 'consulta no disponible')); }
    $st->bind_param('s', $explicitDay);
} else {
    $st = $db->prepare("SELECT s.id, s.orders_id, s.albaran_id, s.shipment_code, s.package_code, s.producto, s.ref, s.kilos,
                               s.tracking_url, s.request_json, s.date_added
                        FROM correos_shipments s
                        LEFT JOIN correos_tracking t ON t.referencia = s.ref
                        WHERE s.tipo='envio' AND s.ok=1 AND s.entorno='pro' AND s.cancelled_at IS NULL
                          AND s.date_added > (NOW() - INTERVAL 7 DAY)
                          AND (t.referencia IS NULL OR t.estado_desc LIKE '%ADMISI%' OR t.estado_desc LIKE '%rerregistr%')
                        ORDER BY s.id ASC");
    if (!$st) { error_log('correos_manifiesto prepare: ' . $db->error); out_json(array('ok' => false, 'error' => 'consulta no disponible')); }
}
$st->execute();
$res = $st->get_result();

$rows = array();
$totBultos = 0; $totKg = 0.0;
while ($r = $res->fetch_assoc()) {
    // Datos de entrega: del request_json (sirve para pedidos web Y QFac-nativos).
    $name = ''; $loc = ''; $cp = ''; $prov = ''; $bultos = 1; $peso = (float) $r['kilos'];
    $j = json_decode((string) $r['request_json'], true);
    $sh = $j['payload']['shipments'][0] ?? null;
    if (is_array($sh)) {
        $a = $sh['addressee'] ?? array();
        $name = trim((string) ($a['name'] ?? ''));
        $loc  = trim((string) ($a['locality'] ?? ''));
        $cp   = trim((string) ($a['cp'] ?? ''));
        $prov = trim((string) ($a['province'] ?? ''));
        if (!empty($sh['packagesNumber'])) $bultos = (int) $sh['packagesNumber'];
        if (!empty($sh['totalWeight']))    $peso = ((int) $sh['totalWeight']) / 1000.0;
    }
    if ($bultos < 1) $bultos = 1;
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
        'pc'      => (string) $r['package_code'],
        'hora'    => substr((string) $r['date_added'], 11, 5),
    );
}

if (!$rows) {
    out_json(array('ok' => false, 'error' => ($explicitDay !== '' ? 'Sin envios Correos registrados el ' . $explicitDay . '.' : 'Sin envios Correos pendientes de entregar.')));
}

/* ---- PDF con TCPDF ---- */
require_once 'includes/vendor/tecnickcom/tcpdf/tcpdf.php';

class CorreosManifest extends TCPDF {
    public $fechaManifiesto = '';
    public $totEnvios = 0;
    public function Header() {
        $this->SetFont('helvetica', 'B', 13);
        $this->SetTextColor(0, 51, 153);
        $this->Cell(0, 7, 'RELACIÓN DE ENTREGA · CORREOS', 0, 1, 'L');
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 4, 'FRANCOBORDO S.L. · NIF B82574690 · Contrato Correos 54002749 · Cliente 80123054', 0, 1, 'L');
        $this->Cell(0, 4, 'Fecha: ' . $this->fechaManifiesto . '   ·   Envíos: ' . $this->totEnvios, 0, 1, 'L');
        $this->Ln(1);
        $this->SetDrawColor(0, 51, 153);
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

$pdf = new CorreosManifest('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->fechaManifiesto = $date;
$pdf->totEnvios = count($rows);
$pdf->SetCreator('Francobordo');
$pdf->SetAuthor('Francobordo S.L.');
$pdf->SetTitle('Manifiesto Correos ' . $date);
$pdf->SetMargins(10, 26, 10);
$pdf->SetHeaderMargin(6);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(true, 16);
$pdf->AddPage();

// Tabla en HTML (TCPDF pagina sola)
$h = '<table border="0.5" cellpadding="3" style="border-color:#999;">';
$h .= '<thead><tr style="background-color:#0033A0;color:#FFFFFF;font-weight:bold;font-size:8px;">'
    . '<th width="4%">Nº</th>'
    . '<th width="9%">Pedido</th>'
    . '<th width="26%">Destinatario</th>'
    . '<th width="22%">Destino</th>'
    . '<th width="6%">Bultos</th>'
    . '<th width="7%">Peso&nbsp;kg</th>'
    . '<th width="14%">Cód. envío</th>'
    . '<th width="12%">Cód. localización</th>'
    . '</tr></thead><tbody>';
$i = 0;
foreach ($rows as $r) {
    $i++;
    $bg = ($i % 2 === 0) ? ' style="background-color:#F0F4FB;"' : '';
    $h .= '<tr' . $bg . '>'
        . '<td width="4%" align="center">' . $i . '</td>'
        . '<td width="9%">' . htmlspecialchars($r['ref'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td width="26%">' . htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td width="22%">' . htmlspecialchars($r['destino'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td width="6%" align="center">' . $r['bultos'] . '</td>'
        . '<td width="7%" align="right">' . number_format($r['peso'], 2, ',', '.') . '</td>'
        . '<td width="14%" style="font-size:7px;">' . htmlspecialchars($r['sc'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td width="12%" style="font-size:7px;">' . htmlspecialchars($r['pc'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '</tr>';
}
$h .= '</tbody><tfoot><tr style="background-color:#DDE6F5;font-weight:bold;">'
    . '<td width="39%" align="right">TOTALES</td>'
    . '<td width="22%"></td>'
    . '<td width="6%" align="center">' . $totBultos . '</td>'
    . '<td width="7%" align="right">' . number_format($totKg, 2, ',', '.') . '</td>'
    . '<td width="26%"></td>'
    . '</tr></tfoot></table>';

$pdf->SetFont('helvetica', '', 8);
$pdf->writeHTML($h, true, false, false, false, '');

// Bloque de firmas
$pdf->Ln(6);
$pdf->SetFont('helvetica', '', 9);
$y = $pdf->GetY();
if ($y + 22 > $pdf->getPageHeight() - $pdf->getBreakMargin()) { $pdf->AddPage(); $y = $pdf->GetY(); }
$pdf->MultiCell(130, 22, "\nEntregado por (Francobordo):\n\n\nFirma y fecha", 1, 'L', false, 0, 12, $y, true);
$pdf->MultiCell(130, 22, "\nRecibido por (Correos):\n\n\nNombre, firma y fecha", 1, 'L', false, 1, 155, $y, true);

$bin = $pdf->Output('', 'S');

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Manifiesto_Correos_' . $date . '.pdf"');
header('Content-Length: ' . strlen($bin));
header('Cache-Control: private, no-store');
echo $bin;
