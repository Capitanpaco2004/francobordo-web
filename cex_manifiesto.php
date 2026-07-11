<?php
/**
 * Manifiesto / Relación de entrega CORREOS EXPRESS — para el PC del almacén.
 *
 * MODELO "PENDIENTES" (2026-07-10): por defecto el manifiesto incluye TODOS los envíos
 * preparados (etiqueta OK, no anulados) que NO han salido en ningún manifiesto anterior
 * (cex_shipments.manifested_at IS NULL), y los marca al emitirlo. Así los pedidos
 * preparados DESPUÉS de la recogida del repartidor salen en el manifiesto siguiente, en
 * vez de perderse (el modelo antiguo era "solo lo del día", más el finde pendiente).
 *
 * GET /cex_manifiesto.php?token=...
 *   (sin parámetros)   -> PDF con lo pendiente + marca manifested_at (emisión real)
 *   preview=1          -> el mismo PDF SIN marcar, rotulado BORRADOR (comprobación)
 *   reprint_last=1     -> reimprime el ÚLTIMO manifiesto emitido (sin re-marcar)
 *   date=YYYY-MM-DD    -> modo por-día antiguo (reimpresión puntual, no marca)
 *
 * Respuesta: PDF (attachment) o JSON {ok:false,error} si no hay envíos/fallo.
 * Gemelo de seur_manifiesto.php (mismo modelo) y correos_manifiesto.php (mismo TCPDF).
 * El .bat del almacén llama al relay del .112 sin date= => modo pendientes.
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

chdir(__DIR__);
include 'includes/configure.php';

mysqli_report(MYSQLI_REPORT_OFF);
$db = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($db->connect_errno) { error_log('cex_manifiesto db: ' . $db->connect_error); out_json(array('ok' => false, 'error' => 'db no disponible')); }
$db->set_charset('utf8mb4');

/* ---- Modo ---- */
$rawDate = trim((string) ($in['date'] ?? ''));
$legacyDay = '';
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)) {
    $dt = DateTime::createFromFormat('Y-m-d', $rawDate);
    if ($dt && $dt->format('Y-m-d') === $rawDate) $legacyDay = $rawDate;
}
$preview     = (($in['preview'] ?? '') === '1');
$reprintLast = (($in['reprint_last'] ?? '') === '1');

$selCols = "SELECT s.id, s.orders_id, s.albaran_id, s.shipment_code, s.ecb, s.product_code, s.ref, s.kilos,
                   s.request_json, s.date_added, s.manifested_at,
                   o.delivery_name, o.delivery_city, o.delivery_postcode
            FROM cex_shipments s
            LEFT JOIN orders o ON o.orders_id = s.orders_id
            WHERE s.tipo='envio' AND s.ok=1 AND s.entorno='pro' AND s.cancelled_at IS NULL";

$mMax = '';
if ($legacyDay !== '') {
    /* MODO LEGADO: solo ese día, marcados o no, y NO marca. */
    $st = $db->prepare($selCols . " AND DATE(s.date_added)=? ORDER BY s.id ASC");
    if ($st) $st->bind_param('s', $legacyDay);
} elseif ($reprintLast) {
    /* Reimpresión del último lote emitido. */
    $q = $db->query("SELECT MAX(manifested_at) m FROM cex_shipments WHERE manifested_at IS NOT NULL");
    $mMax = $q ? (string) ($q->fetch_assoc()['m'] ?? '') : '';
    if ($mMax === '') out_json(array('ok' => false, 'error' => 'No hay ningun manifiesto emitido todavia.'));
    $st = $db->prepare($selCols . " AND s.manifested_at = ? ORDER BY s.id ASC");
    if ($st) $st->bind_param('s', $mMax);
} else {
    /* MODO PENDIENTES (por defecto): todo lo preparado que no salió en manifiestos anteriores. */
    $st = $db->prepare($selCols . " AND s.manifested_at IS NULL ORDER BY s.id ASC");
}
if (!$st) { error_log('cex_manifiesto prepare: ' . $db->error); out_json(array('ok' => false, 'error' => 'consulta no disponible')); }
$st->execute();
$res = $st->get_result();

/* Producto CEX -> etiqueta legible. 93 ePaq24 · 18 Paq Punto · 63 Paq 24 (intl) ·
 * 26 Islas Express · 62 Paq 14 · 33 Devolución. */
function svcLabel($producto, $sabado) {
    $map = array('93' => 'ePaq24', '18' => 'Paq Punto', '63' => 'Paq 24 intl', '26' => 'Islas Exp.', '62' => 'Paq 14', '33' => 'Devolución');
    $p = trim((string) $producto);
    $l = $map[$p] ?? ($p !== '' ? 'Prod. ' . $p : '—');
    if ($sabado) $l .= ' · Sáb';
    return $l;
}

$rows = array(); $ids = array();
$totBultos = 0; $totKg = 0.0;
while ($r = $res->fetch_assoc()) {
    // Destinatario: preferimos el pedido (autoritativo); si no (manual/QFac), el request_json.
    $j = json_decode((string) $r['request_json'], true);
    $jd = is_array($j) ? $j : array();
    $name = trim((string) ($r['delivery_name'] ?: ($jd['nomDest'] ?? '')));
    $loc  = trim((string) ($r['delivery_city'] ?: ($jd['pobDest'] ?? '')));
    $cp   = trim((string) ($r['delivery_postcode'] ?: ($jd['codPosNacDest'] ?? ($jd['codPosIntDest'] ?? ''))));
    $bultos = max(1, (int) ($jd['numBultos'] ?? 1));
    $peso = (float) $r['kilos'];
    if ($peso <= 0 && isset($jd['kilos'])) $peso = (float) str_replace(',', '.', (string) $jd['kilos']);
    $prod = trim((string) $r['product_code']);
    if ($prod === '') $prod = trim((string) ($jd['producto'] ?? ''));
    $sab = isset($jd['entrSabado']) && strtoupper(trim((string) $jd['entrSabado'])) === 'S';
    $totBultos += $bultos;
    $totKg += $peso;
    $ids[] = (int) $r['id'];
    $rows[] = array(
        'ref'     => (string) $r['ref'],
        'oid'     => (string) $r['orders_id'],
        'name'    => $name,
        'destino' => trim($loc . ($cp !== '' ? ' (' . $cp . ')' : '')),
        'svc'     => svcLabel($prod, $sab),
        'bultos'  => $bultos,
        'peso'    => $peso,
        'sc'      => (string) $r['shipment_code'],
        'ecb'     => (string) $r['ecb'],
        'fecha'   => substr((string) $r['date_added'], 5, 11),
    );
}

if (!$rows) {
    if ($legacyDay !== '')   out_json(array('ok' => false, 'error' => 'Sin envios Correos Express registrados el ' . $legacyDay . '.'));
    if ($reprintLast)        out_json(array('ok' => false, 'error' => 'El ultimo manifiesto no tiene envios (?)'));
    out_json(array('ok' => false, 'error' => 'Sin envios Correos Express pendientes de manifiesto (todo lo preparado ya salio en manifiestos anteriores).'));
}

/* Nº de manifiesto = timestamp de emisión (o el del lote reimpreso / el día en modo legado). */
$manifiestoTs = $legacyDay !== '' ? $legacyDay : ($reprintLast ? $mMax : date('Y-m-d H:i:s'));

/* ---- PDF con TCPDF ---- */
require_once 'includes/vendor/tecnickcom/tcpdf/tcpdf.php';

class CexManifest extends TCPDF {
    public $fechaManifiesto = '';
    public $totEnvios = 0;
    public $esReimpresion = false;
    public $esPreview = false;
    public $esLegado = false;
    public function Header() {
        $this->SetFont('helvetica', 'B', 13);
        $this->SetTextColor(210, 0, 40);
        $this->Cell(0, 7, 'RELACIÓN DE ENTREGA · CORREOS EXPRESS' . ($this->esReimpresion ? ' (REIMPRESIÓN)' : '') . ($this->esPreview ? ' (BORRADOR — no emitido)' : ''), 0, 1, 'L');
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 4, 'FRANCOBORDO S.L. · NIF B82574690 · Cliente Correos Express 632140001', 0, 1, 'L');
        if ($this->esLegado) {
            $this->Cell(0, 4, 'Fecha: ' . $this->fechaManifiesto . '   ·   Envíos: ' . $this->totEnvios, 0, 1, 'L');
        } else {
            $this->Cell(0, 4, 'Manifiesto Nº ' . $this->fechaManifiesto . '   ·   Envíos: ' . $this->totEnvios . '   ·   Incluye todo lo preparado no recogido en manifiestos anteriores', 0, 1, 'L');
        }
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
        $this->Cell(0, 4, 'Generado ' . date('Y-m-d H:i') . ' · Francobordo' . ($this->esLegado ? '' : ' · Reimprimir este lote: ?reprint_last=1'), 0, 0, 'L');
        $this->Cell(0, 4, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}

$pdf = new CexManifest('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->fechaManifiesto = $manifiestoTs;
$pdf->totEnvios = count($rows);
$pdf->esReimpresion = $reprintLast;
$pdf->esPreview = $preview;
$pdf->esLegado = ($legacyDay !== '');
$pdf->SetCreator('Francobordo');
$pdf->SetAuthor('Francobordo S.L.');
$pdf->SetTitle('Manifiesto Correos Express ' . $manifiestoTs);
$pdf->SetMargins(10, 26, 10);
$pdf->SetHeaderMargin(6);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(true, 16);
$pdf->AddPage();

$h = '<table border="0.5" cellpadding="3" style="border-color:#999;">';
$h .= '<thead><tr style="background-color:#D2002A;color:#FFFFFF;font-weight:bold;font-size:8px;">'
    . '<th width="4%">Nº</th>'
    . '<th width="9%">Pedido</th>'
    . '<th width="18%">Destinatario</th>'
    . '<th width="16%">Destino</th>'
    . '<th width="9%">Servicio</th>'
    . '<th width="5%">Bultos</th>'
    . '<th width="6%">Peso&nbsp;kg</th>'
    . '<th width="12%">Nº envío</th>'
    . '<th width="13%">Cód. barras</th>'
    . '<th width="8%">Preparado</th>'
    . '</tr></thead><tbody>';
$i = 0;
foreach ($rows as $r) {
    $i++;
    $bg = ($i % 2 === 0) ? ' style="background-color:#FBEFF1;"' : '';
    $h .= '<tr' . $bg . '>'
        . '<td width="4%" align="center">' . $i . '</td>'
        . '<td width="9%">' . htmlspecialchars($r['ref'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td width="18%" style="font-size:7px;">' . htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td width="16%" style="font-size:7px;">' . htmlspecialchars($r['destino'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td width="9%" align="center" style="font-size:7px;">' . htmlspecialchars($r['svc'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td width="5%" align="center">' . $r['bultos'] . '</td>'
        . '<td width="6%" align="right">' . number_format($r['peso'], 2, ',', '.') . '</td>'
        . '<td width="12%" style="font-size:7px;">' . htmlspecialchars($r['sc'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td width="13%" style="font-size:6px;">' . htmlspecialchars($r['ecb'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td width="8%" align="center" style="font-size:7px;">' . htmlspecialchars($r['fecha'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '</tr>';
}
$h .= '</tbody><tfoot><tr style="background-color:#F6D9DF;font-weight:bold;">'
    . '<td width="58%" align="right">TOTALES</td>'
    . '<td width="5%" align="center">' . $totBultos . '</td>'
    . '<td width="6%" align="right">' . number_format($totKg, 2, ',', '.') . '</td>'
    . '<td width="31%"></td>'
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

/* Marcar como manifestados SOLO en emisión real (ni preview, ni reimpresión, ni modo
 * legado por día), y SOLO tras generar el PDF con éxito. */
if (!$preview && !$reprintLast && $legacyDay === '' && count($ids)) {
    $db->query("UPDATE cex_shipments SET manifested_at = '" . $db->real_escape_string($manifiestoTs) . "' WHERE id IN (" . implode(',', array_map('intval', $ids)) . ") AND manifested_at IS NULL");
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Manifiesto_CorreosExpress_' . str_replace(array(' ', ':'), array('_', ''), $manifiestoTs) . ($preview ? '_BORRADOR' : '') . '.pdf"');
header('Content-Length: ' . strlen($bin));
header('Cache-Control: private, no-store');
echo $bin;
