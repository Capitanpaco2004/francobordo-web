<?php
/**
 * osculati_round_weight_backfill.php — CLI
 *
 * PARTE A: redondea a nickel (PVP con IVA múltiplo de 0,05) los productos Osculati
 *          NO-VIVOS (products_status != 1) con precio no-nickel. Toca:
 *          products_price, products_groups (G1), y deltas de variante
 *          (products_attributes.options_values_price + products_attributes_groups).
 *
 * PARTE B: recalcula los PESOS de variante (delta sobre el padre) desde el origen
 *          (ItemPrice4Web weight_g/base_qty) en los 18 productos con weight_prefix='%'.
 *          Base = variante más barata (street_price), igual que el importador.
 *
 *   php osculati_round_weight_backfill.php          → DRY-RUN
 *   php osculati_round_weight_backfill.php APPLY
 */
$apply = in_array('APPLY', $argv ?? [], true);

const OSC_USER = 'C54293'; const OSC_PASS = '0XxBkWSb'; const OSC_FTP = 'ftp://fw.osculati.it/';
require_once __DIR__ . '/osculati_gateway.inc.php';
const VAT_RATE = 0.21;
const OSC_MFG = 259;
const G1_GROUP = 1;

require '/home/francobordo/public_html/includes/configure.php';
$m = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
$m->set_charset('utf8');

function roundToNickel($net) { $wi = ((float)$net) * (1 + VAT_RATE); $r = round($wi * 20) / 20; return round($r / (1 + VAT_RATE), 4); }
function isNickel($net) { $wi = ((float)$net) * (1 + VAT_RATE); return abs(round($wi * 20) / 20 - round($wi, 2)) < 0.0011; }
function f4($v) { return number_format((float)$v, 4, '.', ''); }
function f3($v) { return number_format((float)$v, 3, '.', ''); }

echo "Modo: " . ($apply ? "APPLY" : "DRY-RUN") . "\n";
$scopeMfg = "(p.manufacturers_id=" . OSC_MFG . " OR p.products_import_origin LIKE 'osculati%')";

/* ================= PARTE A: PRECIOS (status != 1) ================= */
echo "\n========== PARTE A: redondeo precios (status != 1) ==========\n";
$r = $m->query("SELECT p.products_id pid, p.products_price price FROM products p
                WHERE $scopeMfg AND p.products_status <> 1
                  AND ABS(ROUND(p.products_price*1.21/0.05)*0.05 - ROUND(p.products_price*1.21,2)) > 0.001");
$priceProds = [];
while ($x = $r->fetch_assoc()) $priceProds[(int)$x['pid']] = (float)$x['price'];
echo "Productos a redondear: " . count($priceProds) . "\n";

$nPrice = $nVarPrice = $nG1 = 0;
foreach ($priceProds as $pid => $oldParent) {
    $newParent = roundToNickel($oldParent);
    // variantes (precio)
    $vr = $m->query("SELECT products_attributes_id paid, options_values_price d, price_prefix pp FROM products_attributes WHERE products_id=$pid");
    $vars = [];
    while ($v = $vr->fetch_assoc()) $vars[] = $v;
    // G1 padre
    $g1row = $m->query("SELECT customers_group_price g1 FROM products_groups WHERE products_id=$pid AND customers_group_id=" . G1_GROUP)->fetch_assoc();
    $oldG1 = $g1row ? (float)$g1row['g1'] : null;
    $newG1 = $oldG1 !== null ? roundToNickel($oldG1) : null;

    if (!$apply) {
        echo sprintf("  pid=%d price %.4f→%.4f (IVA %.2f→%.2f)%s | %d variantes\n",
            $pid, $oldParent, $newParent, $oldParent*1.21, $newParent*1.21,
            $newG1!==null ? sprintf(" G1 %.4f→%.4f", $oldG1, $newG1) : "", count($vars));
    } else {
        $m->query("UPDATE products SET products_price=" . f4($newParent) . " WHERE products_id=$pid");
        $nPrice++;
        if ($newG1 !== null) { $m->query("UPDATE products_groups SET customers_group_price=" . f4($newG1) . " WHERE products_id=$pid AND customers_group_id=" . G1_GROUP); $nG1++; }
        foreach ($vars as $v) {
            $sign = $v['pp'] === '-' ? -1 : 1;
            $oldEff = $oldParent + $sign * (float)$v['d'];
            $newEff = roundToNickel($oldEff);
            $nd = round($newEff - $newParent, 4);
            $np = $nd < 0 ? '-' : '+';
            $m->query("UPDATE products_attributes SET options_values_price=" . f4(abs($nd)) . ", price_prefix='$np' WHERE products_attributes_id=" . (int)$v['paid']);
            $nVarPrice++;
        }
    }
}
if ($apply) echo "Aplicado: $nPrice precios padre, $nG1 G1, $nVarPrice deltas variante\n";

/* ================= PARTE B: PESOS (prefix '%') ================= */
echo "\n========== PARTE B: recálculo pesos (18 con prefix %) ==========\n";
// Descargar ItemPrice4Web
$ipFile = '/tmp/ItemPrice4Web_bf.txt';
if (!file_exists($ipFile)) {
    echo "Descargando ItemPrice4Web.txt...\n";
    if (!osculatiGw('ItemPrice4Web.txt', $ipFile, 1000)) { echo "Descarga (pasarela HTTPS) falló\n"; exit(1); }
}
$raw = file_get_contents($ipFile);
$u8 = ltrim(mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE'), "\xEF\xBB\xBF\xFF\xFE"); unset($raw);
$byBase = []; // base_code → [weight_g, base_qty, street_price]
foreach (preg_split("/\r\n|\r|\n/", $u8) as $l) {
    if ($l === '') continue; $c = explode("\t", $l);
    if (count($c) < 16) continue;
    $bc = trim($c[1]); if ($bc === '') continue;
    $byBase[$bc] = ['wg'=>(int)$c[15], 'bq'=>max(1,(int)$c[2]), 'sp'=>(int)$c[13]];
}
echo "ItemPrice4Web: " . count($byBase) . " base codes\n";

$r = $m->query("SELECT DISTINCT p.products_id pid, p.products_weight pw, p.products_status st FROM products_attributes pa JOIN products p ON p.products_id=pa.products_id WHERE $scopeMfg AND pa.weight_prefix='%'");
$wProds = [];
while ($x = $r->fetch_assoc()) $wProds[(int)$x['pid']] = $x;
echo "Productos con peso roto: " . count($wProds) . "\n";

$nWp = $nWv = 0;
foreach ($wProds as $pid => $info) {
    $vr = $m->query("SELECT products_attributes_id paid, reference, options_values_id vid FROM products_attributes WHERE products_id=$pid");
    $vs = [];
    while ($v = $vr->fetch_assoc()) {
        $bc = preg_replace('/#.*$/', '', trim($v['reference']));
        $src = $byBase[$bc] ?? null;
        if (!$src) { $vs[] = ['paid'=>$v['paid'],'bc'=>$bc,'w'=>null,'sp'=>PHP_INT_MAX]; continue; }
        $vs[] = ['paid'=>(int)$v['paid'], 'bc'=>$bc, 'w'=>$src['wg']/$src['bq']/1000, 'sp'=>$src['sp']/max(1,$src['bq'])];
    }
    // base = variante más barata con peso conocido
    $base = null;
    foreach ($vs as $v) if ($v['w'] !== null && ($base === null || $v['sp'] < $base['sp'])) $base = $v;
    if (!$base) { echo "  pid=$pid SIN datos de peso en origen, skip\n"; continue; }
    $baseW = round($base['w'], 3);

    if (!$apply) {
        echo sprintf("  pid=%d st=%s padre_peso %.3f→%.3f | variantes:\n", $pid, $info['st'], (float)$info['pw'], $baseW);
        foreach ($vs as $v) {
            if ($v['w'] === null) { echo "     {$v['bc']}: SIN peso origen\n"; continue; }
            $dl = round($v['w'] - $baseW, 3);
            echo sprintf("     %s: %.3f kg → delta %s%.3f\n", $v['bc'], $v['w'], $dl<0?'-':'+', abs($dl));
        }
    } else {
        $m->query("UPDATE products SET products_weight=" . f3($baseW) . " WHERE products_id=$pid");
        $nWp++;
        foreach ($vs as $v) {
            if ($v['w'] === null) continue;
            $dl = round($v['w'] - $baseW, 3);
            $pp = $dl < 0 ? '-' : '+';
            $m->query("UPDATE products_attributes SET options_values_weight=" . f3(abs($dl)) . ", weight_prefix='$pp' WHERE products_attributes_id=" . (int)$v['paid']);
            $nWv++;
        }
    }
}
if ($apply) echo "Aplicado: $nWp pesos padre, $nWv deltas peso variante\n";

if (!$apply) echo "\nDRY-RUN. Para aplicar: php " . basename(__FILE__) . " APPLY\n";
