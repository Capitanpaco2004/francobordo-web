<?php
/**
 * Re-redondea PVP de productos Lankhorst para que el precio con IVA acabe en .05.
 *
 * Aplica a:
 *   - products.products_price (padre)
 *   - products_attributes.options_values_price (deltas de variantes ajustados para
 *     que el precio absoluto de cada variante = padre + delta también sea múltiplo .05 con IVA)
 *
 * NO toca:
 *   - cost (decisión de diseño)
 *   - G1 ni delta G1 (no podemos recalcular sin cost por variante; queda como estaba)
 *   - stock
 *
 * Uso CLI:
 *   php lankhorst_reprice.php DRY      # solo lista cambios
 *   php lankhorst_reprice.php          # aplica
 */

$confPath = '/home/francobordo/public_html/includes/configure.php';
$conf = file_get_contents($confPath);
preg_match("/'DB_SERVER',\s*'([^']+)'/", $conf, $m); $DB_HOST = $m[1] ?? 'localhost';
preg_match("/'DB_SERVER_USERNAME',\s*'([^']+)'/", $conf, $m); $DB_USER = $m[1];
preg_match("/'DB_SERVER_PASSWORD',\s*'([^']+)'/", $conf, $m); $DB_PASS = $m[1];
preg_match("/'DB_DATABASE',\s*'([^']+)'/", $conf, $m); $DB_NAME = $m[1];

$dryRun = false;
foreach (array_slice($argv, 1) as $a) {
    if (strtoupper($a) === 'DRY') $dryRun = true;
}

const VAT_RATE = 0.21;
const G1_GROUP_ID = 1;
const G1_FLOOR_FACTOR = 1.10;

function roundToNickel($net) {
    $wi = ((float) $net) * (1 + VAT_RATE);
    $r  = round($wi * 20) / 20;
    return round($r / (1 + VAT_RATE), 4);
}

/** Recalcula G1 con la misma lógica que el importador, pero redondeando con IVA */
function calcG1Price($price, $cost) {
    $price = (float) $price; $cost = (float) $cost;
    if ($price <= 0) return 0.0;
    $mult = 0.90;
    if ($cost > 0) {
        $margin = ($price - $cost) / $price;
        if      ($margin >= 0.45) $mult = 0.75;
        elseif  ($margin >= 0.40) $mult = 0.80;
        elseif  ($margin >= 0.35) $mult = 0.82;
        elseif  ($margin >= 0.30) $mult = 0.85;
    }
    $tier  = $price * $mult;
    $floor = $cost * G1_FLOOR_FACTOR;
    return roundToNickel(max($tier, $floor));
}

function logMsg($s) { echo '[' . date('H:i:s') . '] ' . $s . "\n"; }

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_error) { logMsg("ERROR DB: " . $mysqli->connect_error); exit(1); }
$mysqli->set_charset('utf8');

logMsg("Modo: " . ($dryRun ? 'DRY-RUN' : 'EXECUTE'));

$res = $mysqli->query("
    SELECT p.products_id, p.products_price
    FROM products p
    WHERE p.products_import_origin='lankhorst'
    ORDER BY p.products_id
");

$productosCambiados = 0;
$variantesCambiadas = 0;

while ($p = $res->fetch_assoc()) {
    $pid  = (int) $p['products_id'];
    $oldP = (float) $p['products_price'];
    $newP = roundToNickel($oldP);
    $changedP = abs($newP - $oldP) > 0.0001;

    if ($changedP) {
        logMsg(sprintf("pid=%d price: %.4f → %.4f (con IVA: %.2f → %.2f)",
            $pid, $oldP, $newP, $oldP * 1.21, $newP * 1.21));
        if (!$dryRun) {
            $mysqli->query("UPDATE products SET products_price=" . number_format($newP, 4, '.', '') . " WHERE products_id=$pid");
        }
        $productosCambiados++;
    }

    // Variantes: redondeamos el price absoluto y guardamos el delta nuevo respecto al padre nuevo
    $resAttrs = $mysqli->query("
        SELECT products_attributes_id, options_values_price, price_prefix
        FROM products_attributes WHERE products_id=$pid
    ");
    while ($a = $resAttrs->fetch_assoc()) {
        $attrId   = (int) $a['products_attributes_id'];
        $signed   = ((float) $a['options_values_price']) * ($a['price_prefix'] === '-' ? -1 : 1);
        $variantPriceOld = $oldP + $signed;
        $variantPriceNew = roundToNickel($variantPriceOld);
        $newDelta  = round($variantPriceNew - $newP, 4);
        $newPrefix = $newDelta < 0 ? '-' : '+';
        $newAbs    = abs($newDelta);

        $oldAbs    = (float) $a['options_values_price'];
        $oldPref   = $a['price_prefix'];

        $needUpdate = (abs($newAbs - $oldAbs) > 0.0001) || ($newPrefix !== $oldPref);
        if ($needUpdate) {
            logMsg(sprintf("  attr=%d delta: %s%.4f → %s%.4f (variante con IVA: %.2f → %.2f)",
                $attrId, $oldPref, $oldAbs, $newPrefix, $newAbs,
                $variantPriceOld * 1.21, $variantPriceNew * 1.21));
            if (!$dryRun) {
                $mysqli->query("UPDATE products_attributes SET options_values_price=" . number_format($newAbs, 4, '.', '') . ", price_prefix='$newPrefix' WHERE products_attributes_id=$attrId");
            }
            $variantesCambiadas++;
        }
    }
}

logMsg("==================== RESUMEN ====================");
logMsg("Productos con price actualizado: $productosCambiados");
logMsg("Variantes con delta actualizado: $variantesCambiadas");
