<?php
/**
 * Manifiesto / Relación de entrega CORREOS — para el PC del almacén.
 *
 * Correos NO tiene endpoint de manifiesto en su API (a diferencia de SEUR), así que
 * el documento se construye localmente a partir de correos_shipments.
 *
 * MODELO "PENDIENTES" (2026-07-10): por defecto el manifiesto incluye TODOS los envíos
 * preparados (etiqueta OK, no anulados) que NO han salido en ningún manifiesto anterior
 * (correos_shipments.manifested_at IS NULL), y los marca al emitirlo. Así lo preparado
 * DESPUÉS de la recogida sale en el manifiesto siguiente, en vez de perderse. El modelo
 * antiguo miraba una ventana de hoy+fin de semana y el estado del TRACKING, de modo que
 * un envío con el tracking atascado contaminaba la lista día tras día.
 *
 * GET /correos_manifiesto.php?token=...
 *   (sin parámetros)   -> PDF con lo pendiente + marca manifested_at (emisión real)
 *   preview=1          -> el mismo PDF SIN marcar (comprobación, rotulado BORRADOR)
 *   reprint_last=1     -> reimprime el ÚLTIMO manifiesto emitido (sin re-marcar)
 *   date=YYYY-MM-DD    -> reimpresión de los envíos registrados ese día (legado, no marca)
 *
 * Respuesta: PDF (attachment) o JSON {ok:false,error} si no hay envíos/fallo.
 * Patrón: seur_manifiesto.php. El relay del .112 (correos_manifest_server.py) solo
 * reenvía 'date', así que la llamada sin parámetros del almacén = emisión real.
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

/* ---- MODOS ----
 * date=YYYY-MM-DD : los envíos registrados ese día (reimpresión puntual, NO marca).
 * reprint_last=1  : el último lote emitido (MAX(manifested_at)), NO re-marca.
 * preview=1       : lo pendiente, SIN marcar (rotulado BORRADOR).
 * por defecto     : lo pendiente (manifested_at IS NULL) y lo MARCA al emitir. */
$explicitDay = (isset($in['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) $in['date'])) && $dt && $dt->format('Y-m-d') === trim((string) $in['date'])) ? $date : '';
/* Si piden un dia concreto y la fecha no es real (2026-13-40), NO caer al modo por defecto:
 * emitiria y MARCARIA el lote pendiente sin que nadie lo pidiera. */
if (trim((string) ($in['date'] ?? '')) !== '' && $explicitDay === '') {
    out_json(array('ok' => false, 'error' => 'Fecha invalida (' . htmlspecialchars(substr(trim((string) $in['date']), 0, 20)) . '). Usa date=YYYY-MM-DD.'));
}
$preview     = (($in['preview'] ?? '') === '1');
$reprintLast = (($in['reprint_last'] ?? '') === '1');
$mMax        = '';

$SEL = "SELECT id, orders_id, albaran_id, shipment_code, package_code, producto, ref, kilos,
               tracking_url, request_json, date_added
        FROM correos_shipments
        WHERE tipo='envio' AND ok=1 AND entorno='pro' AND cancelled_at IS NULL";

if ($explicitDay !== '') {
    $st = $db->prepare($SEL . " AND DATE(date_added)=? ORDER BY id ASC");
    if (!$st) { error_log('correos_manifiesto prepare: ' . $db->error); out_json(array('ok' => false, 'error' => 'consulta no disponible')); }
    $st->bind_param('s', $explicitDay);
} elseif ($reprintLast) {
    $q = $db->query("SELECT MAX(manifested_at) m FROM correos_shipments WHERE manifested_at IS NOT NULL");
    if ($q && ($row = $q->fetch_assoc())) $mMax = (string) ($row['m'] ?? '');
    if ($mMax === '') out_json(array('ok' => false, 'error' => 'No hay ningun manifiesto emitido todavia.'));
    $st = $db->prepare($SEL . " AND manifested_at = ? ORDER BY id ASC");
    if (!$st) { error_log('correos_manifiesto prepare: ' . $db->error); out_json(array('ok' => false, 'error' => 'consulta no disponible')); }
    $st->bind_param('s', $mMax);
} else {
    // Pendiente = preparado y no salido en ningun manifiesto anterior. Sin ventanas de
    // fecha ni dependencia del tracking: determinista y no pierde nada.
    $st = $db->prepare($SEL . " AND manifested_at IS NULL ORDER BY id ASC");
    if (!$st) { error_log('correos_manifiesto prepare: ' . $db->error); out_json(array('ok' => false, 'error' => 'consulta no disponible')); }
}
$st->execute();
$res = $st->get_result();

$rows = array(); $ids = array();
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
    $ids[] = (int) $r['id'];
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
    if ($explicitDay !== '') $err = 'Sin envios Correos registrados el ' . $explicitDay . '.';
    elseif ($reprintLast)    $err = 'El ultimo manifiesto no tiene envios (?).';
    else                     $err = 'Sin envios Correos pendientes de manifiesto (todo lo preparado ya salio en manifiestos anteriores).';
    out_json(array('ok' => false, 'error' => $err));
}

/* Nº de manifiesto = timestamp de emisión (o el del lote reimpreso). */
$manifiestoTs  = $reprintLast ? $mMax : date('Y-m-d H:i:s');
$esEmisionReal = ($explicitDay === '' && !$preview && !$reprintLast);

/* ---- PDF con TCPDF ---- */
require_once 'includes/vendor/tecnickcom/tcpdf/tcpdf.php';

class CorreosManifest extends TCPDF {
    public $fechaManifiesto = '';
    public $totEnvios = 0;
    public $esReimpresion = false;
    public $esPreview = false;
    public $lineaExtra = '';
    public function Header() {
        $this->SetFont('helvetica', 'B', 13);
        $this->SetTextColor(0, 51, 153);
        $this->Cell(0, 7, 'RELACIÓN DE ENTREGA · CORREOS' . ($this->esReimpresion ? ' (REIMPRESIÓN)' : '') . ($this->esPreview ? ' (BORRADOR — no emitido)' : ''), 0, 1, 'L');
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 4, 'FRANCOBORDO S.L. · NIF B82574690 · Contrato Correos 54002749 · Cliente 80123054', 0, 1, 'L');
        $this->Cell(0, 4, $this->lineaExtra, 0, 1, 'L');
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
        $this->Cell(0, 4, 'Generado ' . date('Y-m-d H:i') . ' · Francobordo · Reimprimir este lote: ?reprint_last=1', 0, 0, 'L');
        $this->Cell(0, 4, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}

$pdf = new CorreosManifest('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->fechaManifiesto = ($explicitDay !== '') ? $explicitDay : $manifiestoTs;
$pdf->totEnvios = count($rows);
$pdf->esReimpresion = ($reprintLast || $explicitDay !== '');
$pdf->esPreview = $preview;
$pdf->lineaExtra = ($explicitDay !== '')
    ? 'Envíos registrados el ' . $explicitDay . '   ·   Envíos: ' . count($rows)
    : 'Manifiesto Nº ' . $manifiestoTs . '   ·   Envíos: ' . count($rows) . '   ·   Incluye todo lo preparado no entregado en manifiestos anteriores';
$pdf->SetCreator('Francobordo');
$pdf->SetAuthor('Francobordo S.L.');
$pdf->SetTitle('Manifiesto Correos ' . $pdf->fechaManifiesto);
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

/* Marcar como manifestados SOLO en emisión real (ni preview, ni reimpresión, ni date=),
 * y SOLO tras generar el PDF con éxito. */
if ($esEmisionReal && count($ids)) {
    $db->query("UPDATE correos_shipments SET manifested_at = '" . $db->real_escape_string($manifiestoTs) . "'
                WHERE id IN (" . implode(',', array_map('intval', $ids)) . ") AND manifested_at IS NULL");
}

/* El nombre del PDF del modo por defecto NO cambia: el bat del almacén depende de él. */
$fnDate = ($explicitDay !== '') ? $explicitDay : ($reprintLast ? substr($mMax, 0, 10) : $date);
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Manifiesto_Correos_' . $fnDate . ($preview ? '_BORRADOR' : '') . '.pdf"');
header('Content-Length: ' . strlen($bin));
header('Cache-Control: private, no-store');
echo $bin;
