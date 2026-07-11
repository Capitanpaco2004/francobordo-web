<?php
/**
 * Manifiesto de entrega SEUR — para el PC del almacén.
 *
 * MODELO "PENDIENTES" (2026-07-10): por defecto el manifiesto incluye TODOS los envíos
 * preparados (etiqueta OK, no anulados) que NO han salido en ningún manifiesto anterior
 * (seur_shipments.manifested_at IS NULL), y los marca al emitirlo. Así los pedidos
 * preparados DESPUÉS de la recogida de SEUR salen en el manifiesto siguiente, en vez
 * de perderse (el modelo antiguo era "por día" contra la API de SEUR).
 *
 * GET /seur_manifiesto.php?token=...
 *   (sin parámetros)   -> PDF LOCAL con lo pendiente + marca manifested_at (emisión real)
 *   preview=1          -> el mismo PDF SIN marcar (comprobación)
 *   reprint_last=1     -> reimprime el ÚLTIMO manifiesto emitido (sin re-marcar)
 *   date=YYYY-MM-DD    -> PDF OFICIAL de SEUR de ese día (API delivery-manifest, legado)
 *   from=..&to=..      -> ídem por rango (legado)
 *
 * Respuesta: PDF (attachment) o JSON {ok:false,error}.
 * Ver memoria francobordo_seur_api.
 */
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');
date_default_timezone_set('Europe/Madrid');

define('SEUR_MAN_TOKEN', 'seurman_4b8e2d17c9');

$in = array_merge($_GET, $_POST);
function out_json($arr) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}
if (($in['token'] ?? '') !== SEUR_MAN_TOKEN) {
    http_response_code(403);
    out_json(array('ok' => false, 'error' => 'forbidden'));
}

chdir(__DIR__);
include 'includes/configure.php';

$date  = trim((string) ($in['date'] ?? ''));
$pFrom = trim((string) ($in['from'] ?? ''));
$pTo   = trim((string) ($in['to'] ?? ''));

mysqli_report(MYSQLI_REPORT_OFF);
$db = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($db->connect_errno) { error_log('seur_manifiesto db: ' . $db->connect_error); out_json(array('ok' => false, 'error' => 'db no disponible')); }
$db->set_charset('utf8mb4');

/* ---- MODO LEGADO: PDF oficial de SEUR por día/rango (reimpresión puntual) ---- */
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || (preg_match('/^\d{4}-\d{2}-\d{2}$/', $pFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $pTo))) {
    $mFrom = $date !== '' ? $date : $pFrom;
    $mTo   = $date !== '' ? $date : $pTo;
    require_once 'includes/classes/seur.php';
    $env = 'pre';
    if ($r = $db->query("SELECT config_value FROM seur_config WHERE config_key='env'")) {
        if ($row = $r->fetch_assoc()) $env = ($row['config_value'] === 'pro') ? 'pro' : 'pre';
    }
    $s = new seur($env);
    $s->setTimeout(60);
    $r = $s->deliveryManifest($mFrom, $mTo);
    if (!empty($r['pdf_bin'])) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="Manifiesto_SEUR_' . $mFrom . ($mFrom !== $mTo ? '_a_' . $mTo : '') . ($env === 'pre' ? '_PRUEBAS' : '') . '.pdf"');
        header('Content-Length: ' . strlen($r['pdf_bin']));
        header('Cache-Control: private, no-store');
        echo $r['pdf_bin'];
        exit;
    }
    if ((int) $r['http'] === 204) out_json(array('ok' => false, 'error' => 'SEUR no tiene envios en el rango ' . $mFrom . ' a ' . $mTo . '.'));
    out_json(array('ok' => false, 'error' => 'No se pudo generar el manifiesto oficial (HTTP ' . $r['http'] . '): ' . seur::primerError($r)));
}

/* ---- MODO PENDIENTES (por defecto): generación LOCAL + marca de emisión ---- */
$preview     = (($in['preview'] ?? '') === '1');
$reprintLast = (($in['reprint_last'] ?? '') === '1');

if ($reprintLast) {
    $q = $db->query("SELECT MAX(manifested_at) m FROM seur_shipments WHERE manifested_at IS NOT NULL");
    $mMax = $q ? (string) ($q->fetch_assoc()['m'] ?? '') : '';
    if ($mMax === '') out_json(array('ok' => false, 'error' => 'No hay ningun manifiesto emitido todavia.'));
    $st = $db->prepare("SELECT id, orders_id, id_rma, ref, shipment_code, ecb, service_code, product_code, kilos, request_json, date_added, manifested_at
                        FROM seur_shipments
                        WHERE tipo='envio' AND ok=1 AND entorno='pro' AND cancelled_at IS NULL AND manifested_at = ?
                        ORDER BY id ASC");
    $st->bind_param('s', $mMax);
} else {
    $st = $db->prepare("SELECT id, orders_id, id_rma, ref, shipment_code, ecb, service_code, product_code, kilos, request_json, date_added, manifested_at
                        FROM seur_shipments
                        WHERE tipo='envio' AND ok=1 AND entorno='pro' AND cancelled_at IS NULL AND manifested_at IS NULL
                        ORDER BY id ASC");
}
if (!$st) { error_log('seur_manifiesto prepare: ' . $db->error); out_json(array('ok' => false, 'error' => 'consulta no disponible')); }
$st->execute();
$res = $st->get_result();

function svcLabel($s, $p) {
    $k = (int) $s . '/' . (int) $p;
    $map = array('31/2' => 'SEUR 24', '1/48' => 'Punto', '9/2' => '13:30', '3/2' => 'SEUR 10', '57/2' => 'Sábado', '77/104' => 'Internacional', '77/48' => 'Punto intl');
    return $map[$k] ?? ((int) $s . '/' . (int) $p);
}

$rows = array(); $ids = array(); $totBultos = 0; $totKg = 0.0;
while ($r = $res->fetch_assoc()) {
    $name = ''; $ciudad = ''; $cp = ''; $bultos = 1;
    $j = json_decode((string) $r['request_json'], true);
    $pl = isset($j['payload']) && is_array($j['payload']) ? $j['payload'] : array();
    if (isset($pl['receiver']['name']))                    $name   = trim((string) $pl['receiver']['name']);
    if (isset($pl['receiver']['address']['cityName']))     $ciudad = trim((string) $pl['receiver']['address']['cityName']);
    if (isset($pl['receiver']['address']['postalCode']))   $cp     = trim((string) $pl['receiver']['address']['postalCode']);
    if (isset($pl['parcels']) && is_array($pl['parcels'])) $bultos = max(1, count($pl['parcels']));
    $peso = (float) $r['kilos'];
    $totBultos += $bultos; $totKg += $peso;
    $ids[] = (int) $r['id'];
    $rows[] = array(
        'ref'     => (string) $r['ref'],
        'name'    => $name,
        'destino' => trim($ciudad . ($cp !== '' ? ' (' . $cp . ')' : '')),
        'svc'     => svcLabel($r['service_code'], $r['product_code']),
        'bultos'  => $bultos,
        'peso'    => $peso,
        'ecb'     => (string) $r['ecb'],
        'fecha'   => substr((string) $r['date_added'], 5, 11),
    );
}

if (!$rows) {
    out_json(array('ok' => false, 'error' => $reprintLast ? 'El ultimo manifiesto no tiene envios (?)' : 'Sin envios SEUR pendientes de manifiesto (todo lo preparado ya salio en manifiestos anteriores).'));
}

/* Nº de manifiesto = timestamp de emisión (o el del lote reimpreso). */
$manifiestoTs = $reprintLast ? $mMax : date('Y-m-d H:i:s');

/* ---- PDF con TCPDF (mismo patrón que correos_manifiesto.php) ---- */
require_once 'includes/vendor/tecnickcom/tcpdf/tcpdf.php';

class SeurManifest extends TCPDF {
    public $numManifiesto = '';
    public $totEnvios = 0;
    public $esReimpresion = false;
    public $esPreview = false;
    public function Header() {
        $this->SetFont('helvetica', 'B', 13);
        $this->SetTextColor(204, 0, 0);
        $this->Cell(0, 7, 'MANIFIESTO DE SALIDA · SEUR' . ($this->esReimpresion ? ' (REIMPRESIÓN)' : '') . ($this->esPreview ? ' (BORRADOR — no emitido)' : ''), 0, 1, 'L');
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 4, 'FRANCOBORDO ARTICULOS NAUTICOS S.L. · NIF B82574690 · CCC SEUR 36598-28', 0, 1, 'L');
        $this->Cell(0, 4, 'Manifiesto Nº ' . $this->numManifiesto . '   ·   Expediciones: ' . $this->totEnvios . '   ·   Incluye todo lo preparado no recogido en manifiestos anteriores', 0, 1, 'L');
        $this->Ln(1);
        $this->SetDrawColor(204, 0, 0);
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

$pdf = new SeurManifest('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->numManifiesto = $manifiestoTs;
$pdf->totEnvios = count($rows);
$pdf->esReimpresion = $reprintLast;
$pdf->esPreview = $preview;
$pdf->SetCreator('Francobordo');
$pdf->SetAuthor('Francobordo S.L.');
$pdf->SetTitle('Manifiesto SEUR ' . $manifiestoTs);
$pdf->SetMargins(10, 26, 10);
$pdf->SetHeaderMargin(6);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(true, 16);
$pdf->AddPage();

$h = '<table border="0.5" cellpadding="3" style="border-color:#999;">';
$h .= '<thead><tr style="background-color:#CC0000;color:#FFFFFF;font-weight:bold;font-size:8px;">'
    . '<th width="4%">Nº</th>'
    . '<th width="10%">Pedido/Ref</th>'
    . '<th width="24%">Destinatario</th>'
    . '<th width="21%">Destino</th>'
    . '<th width="9%">Servicio</th>'
    . '<th width="5%">Bultos</th>'
    . '<th width="7%">Peso&nbsp;kg</th>'
    . '<th width="12%">ECB</th>'
    . '<th width="8%">Preparado</th>'
    . '</tr></thead><tbody>';
$i = 0;
foreach ($rows as $r) {
    $i++;
    $bg = ($i % 2 === 0) ? ' style="background-color:#FBF0F0;"' : '';
    $h .= '<tr' . $bg . '>'
        . '<td width="4%" align="center">' . $i . '</td>'
        . '<td width="10%">' . htmlspecialchars($r['ref'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td width="24%">' . htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td width="21%">' . htmlspecialchars($r['destino'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td width="9%" align="center">' . htmlspecialchars($r['svc'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td width="5%" align="center">' . $r['bultos'] . '</td>'
        . '<td width="7%" align="right">' . number_format($r['peso'], 2, ',', '.') . '</td>'
        . '<td width="12%" style="font-size:7px;">' . htmlspecialchars($r['ecb'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td width="8%" align="center" style="font-size:7px;">' . htmlspecialchars($r['fecha'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '</tr>';
}
$h .= '</tbody><tfoot><tr style="background-color:#F5DDDD;font-weight:bold;">'
    . '<td width="59%" align="right">TOTALES</td>'
    . '<td width="9%"></td>'
    . '<td width="5%" align="center">' . $totBultos . '</td>'
    . '<td width="7%" align="right">' . number_format($totKg, 2, ',', '.') . '</td>'
    . '<td width="20%"></td>'
    . '</tr></tfoot></table>';

$pdf->SetFont('helvetica', '', 8);
$pdf->writeHTML($h, true, false, false, false, '');

$pdf->Ln(6);
$pdf->SetFont('helvetica', '', 9);
$y = $pdf->GetY();
if ($y + 22 > $pdf->getPageHeight() - $pdf->getBreakMargin()) { $pdf->AddPage(); $y = $pdf->GetY(); }
$pdf->MultiCell(130, 22, "\nEntregado por (Francobordo):\n\n\nFirma y fecha", 1, 'L', false, 0, 12, $y, true);
$pdf->MultiCell(130, 22, "\nRecibido por (SEUR):\n\n\nNombre, firma y fecha", 1, 'L', false, 1, 155, $y, true);

$bin = $pdf->Output('', 'S');

/* Marcar como manifestados SOLO en emisión real (ni preview ni reimpresión),
 * y SOLO tras generar el PDF con éxito. */
if (!$preview && !$reprintLast && count($ids)) {
    $db->query("UPDATE seur_shipments SET manifested_at = '" . $db->real_escape_string($manifiestoTs) . "' WHERE id IN (" . implode(',', array_map('intval', $ids)) . ") AND manifested_at IS NULL");
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Manifiesto_SEUR_' . str_replace(array(' ', ':'), array('_', ''), $manifiestoTs) . ($preview ? '_BORRADOR' : '') . '.pdf"');
header('Content-Length: ' . strlen($bin));
header('Cache-Control: private, no-store');
echo $bin;
