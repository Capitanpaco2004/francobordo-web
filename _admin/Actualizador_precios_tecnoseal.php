<?php
require 'includes/application_top.php';
require_once dirname(dirname(__FILE__)) . '/includes/vendor/autoload.php';

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);

/* Actualizador de precios TECNOSEAL.
 * Fuente: /import/Tecnoseal/TARIFA PRECIOS DR.xlsx (A=CODIGO, C=DR=coste, D=PVP sin IVA).
 * Regla: cost = DR ; price = roundToNickel(PVP). G1 tiers+piso. Variantes por código. */

const TS_DIR             = '/home/francobordo/public_html/import/Tecnoseal/';
const ORIGIN_FLAG        = 'tecnoseal';
const G1_GROUP_ID        = 1;
const G1_FLOOR_FACTOR    = 1.10;
const PRICE_THRESHOLD    = 0.005;          // 0.5%
const MAX_CHANGE_PCT_DEF = 30;             // cap por defecto
const VAT_RATE           = 0.21;

function roundToNickel($net) { $wi=((float)$net)*(1+VAT_RATE); $r=round($wi*20)/20; return round($r/(1+VAT_RATE),4); }
function calcG1Price($price,$cost){ $price=(float)$price;$cost=(float)$cost; if($price<=0)return 0.0; $mult=0.90; if($cost>0){$m=($price-$cost)/$price; if($m>=0.45)$mult=0.75; elseif($m>=0.40)$mult=0.80; elseif($m>=0.35)$mult=0.82; elseif($m>=0.30)$mult=0.85;} return round(max($price*$mult,$cost*G1_FLOOR_FACTOR),4); }
function fmt4($n){ return number_format((float)$n,4,'.',''); }
function priceDeltaPct($o,$n){ $ref=max(abs((float)$o),0.01); return abs((float)$n-(float)$o)/$ref; }
function tsNum($v){ $v=trim((string)$v); if($v==='')return null; $v=str_replace(',','.',$v); return is_numeric($v)?(float)$v:null; }
function logMsg($msg){ echo '<pre style="margin:0;padding:2px 8px;border-bottom:1px solid #eee;white-space:pre-wrap;overflow-wrap:break-word;word-break:break-word;font-family:monospace;font-size:12px;">' . htmlspecialchars('['.date('H:i:s').'] '.$msg) . "</pre>"; @flush(); }

function findNewestXlsx($dir){ if(!is_dir($dir))return null; $best=null;$bt=0; foreach(scandir($dir) as $f){ if(substr($f,-5)!=='.xlsx'||substr($f,0,1)==='~')continue; $t=filemtime($dir.$f); if($t>$bt){$bt=$t;$best=$dir.$f;} } return $best; }

/** code -> ['cost'=>DR, 'price'=>roundToNickel(PVP)] */
function loadTsPrices($path){
    $reader=\PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path); $reader->setReadDataOnly(true);
    $ss=$reader->load($path); $sheet=$ss->getActiveSheet(); $hi=$sheet->getHighestRow();
    $prices=[];
    for($r=2;$r<=$hi;$r++){
        $code=trim((string)$sheet->getCell('A'.$r)->getValue());
        if($code==='')continue;
        $dr=tsNum($sheet->getCell('C'.$r)->getValue());
        $pvp=tsNum($sheet->getCell('D'.$r)->getValue());
        if($pvp===null||$pvp<=0||$dr===null||$dr<0)continue;
        $prices[$code]=['cost'=>round($dr,4),'price'=>roundToNickel($pvp)];
    }
    return $prices;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$dryRun = !isset($_POST['confirm_execute']) && !isset($_GET['confirm_execute']);
$applyExtremes = isset($_POST['apply_extremes']) || isset($_GET['apply_extremes']);
$maxChangePct  = isset($_GET['max_change_pct']) ? (float)$_GET['max_change_pct'] : (isset($_POST['max_change_pct']) ? (float)$_POST['max_change_pct'] : MAX_CHANGE_PCT_DEF);
if ($maxChangePct < 0) $maxChangePct = 0;
$maxChangeRatio = $maxChangePct / 100.0;
$isAction = ($action === 'plan' || $action === 'execute');

if ($isAction) { @header('X-Accel-Buffering: no'); @header('Content-Type: text/html; charset=utf-8'); while(ob_get_level()>0)@ob_end_flush(); @ob_implicit_flush(true); }
?>
<?php require THEME . 'html/header.php'; ?>
<table style="width:100%;"><tr><td><div style="padding:20px;">
<?php if ($isAction): ?>
    <h2>Actualizador precios Tecnoseal — <?php echo $dryRun ? 'PLAN (dry-run)' : 'EJECUCIÓN REAL'; ?></h2>
    <p><a href="<?php echo tep_href_link('Actualizador_precios_tecnoseal.php'); ?>" class="xbutton small hv9">← Volver</a></p>
    <div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n"; @flush();
logMsg("Modo: " . ($dryRun ? "PLAN (dry-run)" : "EXECUTE")
    . " | tope=" . ($applyExtremes ? "OFF (aplica extremos)" : ("|Δ| ≤ " . rtrim(rtrim(number_format($maxChangePct,1,'.',''),'0'),'.') . "%")));

$xlsx = file_exists(TS_DIR.'TARIFA PRECIOS DR.xlsx') ? TS_DIR.'TARIFA PRECIOS DR.xlsx' : findNewestXlsx(TS_DIR);
if (!$xlsx) { logMsg("ERROR: no hay xlsx en " . TS_DIR); goto end_action; }
logMsg("xlsx: " . basename($xlsx) . " (mtime " . date('Y-m-d H:i', filemtime($xlsx)) . ")");
$xlsxPrices = loadTsPrices($xlsx);
logMsg("Códigos con precio válido: " . count($xlsxPrices));

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_error) { logMsg("ERROR DB: " . $mysqli->connect_error); goto end_action; }
$mysqli->set_charset('utf8');

$prods = [];
$r = $mysqli->query("SELECT products_id, products_model, reference_prov, products_price, products_cost FROM products WHERE products_import_origin LIKE 'tecnoseal%'");
while ($row = $r->fetch_assoc()) $prods[(int)$row['products_id']] = $row;
logMsg("Productos Tecnoseal en BD: " . count($prods));
if (empty($prods)) { logMsg("Nada que hacer."); goto end_action; }

$ids = implode(',', array_keys($prods));
$g1Cur = [];
$r = $mysqli->query("SELECT products_id, customers_group_price FROM products_groups WHERE customers_group_id=" . G1_GROUP_ID . " AND products_id IN ($ids)");
while ($row = $r->fetch_assoc()) $g1Cur[(int)$row['products_id']] = (float)$row['customers_group_price'];

$attrsByProd = [];
$r = $mysqli->query("SELECT products_attributes_id, products_id, options_values_id, reference, reference_prov, options_values_price, price_prefix FROM products_attributes WHERE products_id IN ($ids)");
while ($row = $r->fetch_assoc()) $attrsByProd[(int)$row['products_id']][] = $row;

$paIds = [];
foreach ($attrsByProd as $arr) foreach ($arr as $a) $paIds[] = (int)$a['products_attributes_id'];
$g1AttrCur = [];
if (!empty($paIds)) {
    $paIn = implode(',', $paIds);
    $r = $mysqli->query("SELECT products_attributes_id, options_values_price, price_prefix FROM products_attributes_groups WHERE customers_group_id=" . G1_GROUP_ID . " AND products_attributes_id IN ($paIn)");
    while ($row = $r->fetch_assoc()) $g1AttrCur[(int)$row['products_attributes_id']] = $row;
}

$updPriceMain=[];$updCostMain=[];$updG1Main=[];$insG1Main=[];
$updAttrPrice=[];$updAttrG1=[];$insAttrG1=[];$extremesProds=[];$noMatch=[];$processed=0;

foreach ($prods as $pid => $p) {
    $variants = $attrsByProd[$pid] ?? [];
    if (empty($variants)) {
        $entry=null;
        foreach (array_unique(array_filter([$p['products_model'],$p['reference_prov']])) as $c) if (isset($xlsxPrices[$c])){$entry=$xlsxPrices[$c];break;}
        if ($entry===null) { $noMatch[]=['pid'=>$pid,'ref'=>$p['products_model']]; continue; }
        $processed++;
        $newCost=$entry['cost']; $newPrice=$entry['price']; $newG1=roundToNickel(calcG1Price($newPrice,$newCost));
        $curPrice=(float)$p['products_price']; $curCost=(float)$p['products_cost'];
        $dP=priceDeltaPct($curPrice,$newPrice); $dC=priceDeltaPct($curCost,$newCost);
        if (!$applyExtremes && $maxChangeRatio>0 && max($dP,$dC)>$maxChangeRatio){ $extremesProds[]=['pid'=>$pid,'ref'=>$p['products_model'],'why'=>'suelto','oldP'=>$curPrice,'newP'=>$newPrice,'oldC'=>$curCost,'newC'=>$newCost]; continue; }
        if ($dP>PRICE_THRESHOLD) $updPriceMain[]=['pid'=>$pid,'ref'=>$p['products_model'],'old'=>$curPrice,'new'=>$newPrice];
        if ($dC>PRICE_THRESHOLD) $updCostMain[]=['pid'=>$pid,'ref'=>$p['products_model'],'old'=>$curCost,'new'=>$newCost];
        if (isset($g1Cur[$pid])){ if(priceDeltaPct($g1Cur[$pid],$newG1)>PRICE_THRESHOLD)$updG1Main[]=['pid'=>$pid,'ref'=>$p['products_model'],'old'=>$g1Cur[$pid],'new'=>$newG1]; }
        else $insG1Main[]=['pid'=>$pid,'ref'=>$p['products_model'],'new'=>$newG1];
        continue;
    }
    // con variantes
    $variantPrices=[];$missing=[];
    foreach ($variants as $a) {
        $entry=null;
        foreach (array_unique(array_filter([$a['reference'],$a['reference_prov']])) as $c) if (isset($xlsxPrices[$c])){$entry=$xlsxPrices[$c];break;}
        if ($entry===null) $missing[]=$a['reference'];
        else $variantPrices[(int)$a['products_attributes_id']]=['cost'=>$entry['cost'],'price'=>$entry['price'],'g1'=>roundToNickel(calcG1Price($entry['price'],$entry['cost']))];
    }
    if (!empty($missing)) { $noMatch[]=['pid'=>$pid,'ref'=>$p['products_model'].' (variante '.implode(',',$missing).' sin match)']; continue; }
    $processed++;
    $cheapestPa=null;$cheapestPrice=PHP_FLOAT_MAX;
    foreach ($variantPrices as $paId=>$vp){ if($vp['price']<$cheapestPrice){$cheapestPrice=$vp['price'];$cheapestPa=$paId;} }
    $mainNew=$variantPrices[$cheapestPa]; $newCost=$mainNew['cost'];$newPrice=$mainNew['price'];$newG1Main=$mainNew['g1'];
    $curPrice=(float)$p['products_price'];$curCost=(float)$p['products_cost'];
    $dP=priceDeltaPct($curPrice,$newPrice);$dC=priceDeltaPct($curCost,$newCost);
    if (!$applyExtremes && $maxChangeRatio>0 && max($dP,$dC)>$maxChangeRatio){ $extremesProds[]=['pid'=>$pid,'ref'=>$p['products_model'],'why'=>'con variantes ('.count($variants).')','oldP'=>$curPrice,'newP'=>$newPrice,'oldC'=>$curCost,'newC'=>$newCost]; continue; }
    if ($dP>PRICE_THRESHOLD) $updPriceMain[]=['pid'=>$pid,'ref'=>$p['products_model'],'old'=>$curPrice,'new'=>$newPrice];
    if ($dC>PRICE_THRESHOLD) $updCostMain[]=['pid'=>$pid,'ref'=>$p['products_model'],'old'=>$curCost,'new'=>$newCost];
    if (isset($g1Cur[$pid])){ if(priceDeltaPct($g1Cur[$pid],$newG1Main)>PRICE_THRESHOLD)$updG1Main[]=['pid'=>$pid,'ref'=>$p['products_model'],'old'=>$g1Cur[$pid],'new'=>$newG1Main]; }
    else $insG1Main[]=['pid'=>$pid,'ref'=>$p['products_model'],'new'=>$newG1Main];
    foreach ($variants as $a) {
        $paId=(int)$a['products_attributes_id']; if(!isset($variantPrices[$paId]))continue; $vp=$variantPrices[$paId];
        $delta=round($vp['price']-$newPrice,4);$prefix=$delta<0?'-':'+';$absDelta=abs($delta);
        $curAbs=(float)$a['options_values_price'];$curPref=$a['price_prefix']?:'+';
        $signedNew=($prefix==='-'?-$absDelta:$absDelta);$signedCur=($curPref==='-'?-$curAbs:$curAbs);
        if (priceDeltaPct($signedCur,$signedNew)>PRICE_THRESHOLD || ($absDelta>0&&$curAbs==0) || ($curPref!==$prefix&&$absDelta>0.0001))
            $updAttrPrice[]=['paid'=>$paId,'pid'=>$pid,'ref'=>$a['reference'],'absOld'=>$curAbs,'prefOld'=>$curPref,'absNew'=>$absDelta,'prefNew'=>$prefix];
        $g1Delta=round($vp['g1']-$newG1Main,4);$g1Prefix=$g1Delta<0?'-':'+';$g1Abs=abs($g1Delta);
        if (isset($g1AttrCur[$paId])){
            $curG1Abs=(float)$g1AttrCur[$paId]['options_values_price'];$curG1Pref=$g1AttrCur[$paId]['price_prefix']?:'+';
            $signedNewG1=($g1Prefix==='-'?-$g1Abs:$g1Abs);$signedCurG1=($curG1Pref==='-'?-$curG1Abs:$curG1Abs);
            if (priceDeltaPct($signedCurG1,$signedNewG1)>PRICE_THRESHOLD || ($g1Abs>0&&$curG1Abs==0) || ($curG1Pref!==$g1Prefix&&$g1Abs>0.0001))
                $updAttrG1[]=['paid'=>$paId,'pid'=>$pid,'absNew'=>$g1Abs,'prefNew'=>$g1Prefix];
        } else $insAttrG1[]=['paid'=>$paId,'pid'=>$pid,'absNew'=>$g1Abs,'prefNew'=>$g1Prefix];
    }
}

// ───────────────────── SPECIALS A BORRAR (post main loop) — V3: solo repreciados en este run si !apply_extremes ─────────────────────
// Política V3 (2026-07-17): solo ofertas de productos cuyo PVP cambia en ESTE run.
// (La V2 borraba todas las del scope — incidente Osculati 2026-07-16, 383 ofertas
// purgadas de 15+ marcas y restauradas desde backup.)
$badSpecials = [];
if (!$applyExtremes && !empty($updPriceMain)) {
    $effPrice = [];
    foreach ($updPriceMain as $u) $effPrice[(int)$u['pid']] = (float) $u['new'];
    $idsRepriced = implode(',', array_map('intval', array_keys($effPrice)));

    $rs = $mysqli->query("SELECT specials_id, products_id, specials_new_products_price, specials_date_added, expires_date, expires_repeat FROM specials WHERE status=1 AND products_id IN ($idsRepriced)");
    if (!$rs) { logMsg("ERROR SELECT specials: " . $mysqli->error); goto end_action; }
    while ($s = $rs->fetch_assoc()) {
        $pid = (int) $s['products_id'];
        $eff = (float) ($effPrice[$pid] ?? 0);
        $sp  = (float) $s['specials_new_products_price'];
        $dtoPct = $eff > 0 ? (($eff - $sp) / $eff) * 100 : 0.0;
        $badSpecials[] = [
            'specials_id' => (int) $s['specials_id'],
            'pid' => $pid,
            'ref' => $prods[$pid]['products_model'] ?? '?',
            'eff_price' => $eff,
            'sp_price'  => $sp,
            'dto_pct'   => $dtoPct,
            'reason'    => ($sp > $eff) ? 'NEGATIVO (special > PVP nuevo)' : (sprintf('dto %.1f%%', $dtoPct) . ' sobre PVP nuevo — PVP repreciado en este run'),
            'created'   => substr((string)$s['specials_date_added'], 0, 10),
            'expires'   => substr((string)$s['expires_date'], 0, 10),
        ];
    }
}

logMsg("==================== PLAN ====================");
logMsg("Procesados: $processed");
logMsg("UPDATE price : " . count($updPriceMain) . " | UPDATE cost : " . count($updCostMain));
logMsg("G1 principal: UPD " . count($updG1Main) . " / INS " . count($insG1Main));
logMsg("Variantes: price UPD " . count($updAttrPrice) . " | G1 UPD " . count($updAttrG1) . " / INS " . count($insAttrG1));
if (!$applyExtremes && $maxChangeRatio>0) logMsg("⚠️ Extremos > {$maxChangePct}% EXCLUIDOS: " . count($extremesProds));
if (!$applyExtremes) logMsg("🗑️  Specials a BORRAR (solo de productos repreciados en este run) : " . count($badSpecials) . (empty($badSpecials)?" (ninguno)":""));
logMsg("Sin match en xlsx: " . count($noMatch));

$showLimit=25;
foreach ([['UPDATE price',$updPriceMain],['UPDATE cost',$updCostMain],['INSERT G1',$insG1Main],['UPDATE G1',$updG1Main]] as [$title,$arr]){
    if(empty($arr))continue; logMsg("--- $title (top $showLimit) ---");
    foreach (array_slice($arr,0,$showLimit) as $u){
        if(isset($u['old'])){$pct=priceDeltaPct($u['old'],$u['new'])*100; logMsg(sprintf("  pid=%d ref=%s : %.4f → %.4f (%s%.1f%%)",$u['pid'],$u['ref'],$u['old'],$u['new'],$u['new']>=$u['old']?'+':'-',$pct));}
        else logMsg(sprintf("  pid=%d ref=%s : (sin G1) → %.4f",$u['pid'],$u['ref'],$u['new']));
    }
    if(count($arr)>$showLimit) logMsg("  …y ".(count($arr)-$showLimit)." más");
}
if (!empty($extremesProds)){
    logMsg("--- ⚠️ EXTREMOS excluidos (top $showLimit) ---");
    foreach (array_slice($extremesProds,0,$showLimit) as $u){ $pP=priceDeltaPct($u['oldP'],$u['newP'])*100;$pC=priceDeltaPct($u['oldC'],$u['newC'])*100; logMsg(sprintf("  pid=%d ref=%s (%s): price %.2f→%.2f (%.1f%%) cost %.2f→%.2f (%.1f%%)",$u['pid'],$u['ref'],$u['why'],$u['oldP'],$u['newP'],$pP,$u['oldC'],$u['newC'],$pC)); }
    if(count($extremesProds)>$showLimit) logMsg("  …y ".(count($extremesProds)-$showLimit)." más");
}
if (!empty($badSpecials)) {
    logMsg(sprintf("--- 🗑️ Specials a BORRAR (repreciados en este run: %d) ---", count($badSpecials)));
    foreach ($badSpecials as $b) {
        logMsg(sprintf("  specials_id=%d pid=%d ref=%-14s PVP=%7.2f sp=%7.2f dto=%5.1f%% creado=%s expira=%s — %s",
            $b['specials_id'], $b['pid'], $b['ref'], $b['eff_price']*1.21, $b['sp_price']*1.21, $b['dto_pct'], $b['created'], $b['expires'], $b['reason']));
    }
}
if (!empty($noMatch)){ logMsg("--- Sin match (top $showLimit) ---"); foreach (array_slice($noMatch,0,$showLimit) as $u) logMsg(sprintf("  pid=%d ref=%s",$u['pid'],$u['ref'])); if(count($noMatch)>$showLimit) logMsg("  …y ".(count($noMatch)-$showLimit)." más"); }

if ($dryRun) { logMsg("=== Dry-run finalizado. Sin cambios. ==="); goto end_action; }

logMsg("Aplicando cambios en transacción…");
$mysqli->begin_transaction();
try {
    foreach ($updPriceMain as $u){ if(!$mysqli->query("UPDATE products SET products_price=".fmt4($u['new']).", products_last_modified=NOW() WHERE products_id=".(int)$u['pid'])) throw new Exception("price pid=".$u['pid'].": ".$mysqli->error); }
    foreach ($updCostMain as $u){ if(!$mysqli->query("UPDATE products SET products_cost=".fmt4($u['new']).", products_last_modified=NOW() WHERE products_id=".(int)$u['pid'])) throw new Exception("cost pid=".$u['pid'].": ".$mysqli->error); }
    foreach ($updG1Main as $u){ if(!$mysqli->query("UPDATE products_groups SET customers_group_price=".fmt4($u['new'])." WHERE products_id=".(int)$u['pid']." AND customers_group_id=".G1_GROUP_ID)) throw new Exception("g1 pid=".$u['pid'].": ".$mysqli->error); }
    foreach ($insG1Main as $u){ if(!$mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (".G1_GROUP_ID.", ".(int)$u['pid'].", ".fmt4($u['new']).", 1, 1)")) throw new Exception("ins g1 pid=".$u['pid'].": ".$mysqli->error); }
    foreach ($updAttrPrice as $u){ if(!$mysqli->query("UPDATE products_attributes SET options_values_price=".fmt4($u['absNew']).", price_prefix='".$u['prefNew']."' WHERE products_attributes_id=".(int)$u['paid'])) throw new Exception("attr paid=".$u['paid'].": ".$mysqli->error); }
    foreach ($updAttrG1 as $u){ if(!$mysqli->query("UPDATE products_attributes_groups SET options_values_price=".fmt4($u['absNew']).", price_prefix='".$u['prefNew']."' WHERE products_attributes_id=".(int)$u['paid']." AND customers_group_id=".G1_GROUP_ID)) throw new Exception("attr g1 paid=".$u['paid'].": ".$mysqli->error); }
    foreach ($insAttrG1 as $u){ if(!$mysqli->query("INSERT INTO products_attributes_groups (products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id, options_values_weight, weight_prefix) VALUES (".(int)$u['paid'].", ".G1_GROUP_ID.", ".fmt4($u['absNew']).", '".$u['prefNew']."', ".(int)$u['pid'].", 0, '+')")) throw new Exception("ins attr g1 paid=".$u['paid'].": ".$mysqli->error); }
    // ───────────────────── SPECIALS A BORRAR (backup + DELETE + bump) ─────────────────────
    if (!empty($badSpecials)) {
        $bakDir = '/home/francobordo/backups';
        @mkdir($bakDir, 0755, true);
        $bakPath = $bakDir . '/tecnoseal_specials_purge_' . date('Ymd_His') . '.sql';
        $freeBytes = @disk_free_space($bakDir);
        if ($freeBytes !== false && $freeBytes < 100 * 1024 * 1024) {
            logMsg("WARN: poco espacio en $bakDir (" . round(($freeBytes ?: 0) / 1024 / 1024) . "MB libres, mínimo 100MB) — abortando DELETE de specials.");
            throw new Exception("disco insuficiente para backup specials");
        }
        $fh = @fopen($bakPath, 'w');
        if ($fh) {
            fwrite($fh, "-- Backup specials borrados por Actualizador_precios_tecnoseal.php " . date('Y-m-d H:i:s') . "\n");
            fwrite($fh, "-- Política V3: !apply_extremes ⇒ borrar ofertas de productos repreciados en este run. Total: " . count($badSpecials) . " filas.\n\n");
            $idList = implode(',', array_map(fn($b) => (int) $b['specials_id'], $badSpecials));
            $rb = $mysqli->query("SELECT * FROM specials WHERE specials_id IN ($idList)");
            if ($rb) while ($srow = $rb->fetch_assoc()) {
                $cols = array_keys($srow);
                $vals = array_map(function ($v) use ($mysqli) {
                    if ($v === null) return 'NULL';
                    return "'" . $mysqli->real_escape_string((string) $v) . "'";
                }, array_values($srow));
                fwrite($fh, "INSERT INTO specials (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n");
            }
            fclose($fh);
            $bakSize = @filesize($bakPath);
            if ($bakSize === false || $bakSize < 100) {
                @unlink($bakPath);
                logMsg("WARN: backup escrito vacío/truncado ($bakSize bytes) — abortando DELETE de specials.");
                throw new Exception("backup specials truncado o vacío");
            }
            logMsg("Backup specials borrados: $bakPath ($bakSize bytes)");
        } else {
            logMsg("WARN: no pude crear backup en $bakDir — abortando DELETE de specials por seguridad.");
            throw new Exception("backup specials no escribible");
        }
        $idList = implode(',', array_map(fn($b) => (int) $b['specials_id'], $badSpecials));
        if (!$mysqli->query("DELETE FROM specials WHERE specials_id IN ($idList)"))
            throw new Exception("delete specials: " . $mysqli->error);
        logMsg("Specials borrados: " . $mysqli->affected_rows);
        $pidsBumpList = implode(',', array_unique(array_map(fn($b)=>(int)$b['pid'], $badSpecials)));
        $mysqli->query("UPDATE products SET products_last_modified=NOW() WHERE products_id IN ($pidsBumpList)");
    }
    $mysqli->commit();
    logMsg("=== COMMIT OK ===");
} catch (Exception $e) { $mysqli->rollback(); logMsg("=== ROLLBACK: " . $e->getMessage() . " ==="); }

end_action:
?>
    </div>
    <p style="margin-top:15px;"><a href="<?php echo tep_href_link('Actualizador_precios_tecnoseal.php'); ?>" class="xbutton small hv9">← Volver</a></p>
<?php else: ?>
    <h2>Actualizador de precios — Tecnoseal</h2>
    <?php $xlsx = file_exists(TS_DIR.'TARIFA PRECIOS DR.xlsx') ? TS_DIR.'TARIFA PRECIOS DR.xlsx' : findNewestXlsx(TS_DIR); ?>
    <p>Relee el xlsx de <code><?php echo TS_DIR; ?></code> y actualiza precio/cost/G1 de productos con <code>products_import_origin = 'tecnoseal'</code>.</p>
    <p>Fórmula: <code>cost = DR (col C)</code> · <code>price = roundToNickel(PVP col D, sin IVA)</code> · G1 tiers+piso. Stock NO se toca.</p>
    <p>xlsx detectado: <?php echo $xlsx ? '<code>'.htmlspecialchars(basename($xlsx)).'</code> ('.date('Y-m-d H:i',filemtime($xlsx)).')' : '<span style="color:red">NO ENCONTRADO</span>'; ?></p>
    <form method="get" action="<?php echo tep_href_link('Actualizador_precios_tecnoseal.php'); ?>" style="margin:10px 0;">
        <input type="hidden" name="action" value="plan">
        <fieldset><legend>1. PLAN (dry-run)</legend>
            <label>Tope cambio % (default <?php echo MAX_CHANGE_PCT_DEF; ?>): <input name="max_change_pct" type="number" min="0" step="0.5" value="<?php echo MAX_CHANGE_PCT_DEF; ?>" style="width:80px"></label>
            <label><input type="checkbox" name="apply_extremes"> aplicar también extremos &gt; tope</label>
            <button type="submit">Generar PLAN</button>
        </fieldset>
    </form>
    <form method="get" action="<?php echo tep_href_link('Actualizador_precios_tecnoseal.php'); ?>" style="margin:10px 0;" onsubmit="return confirm('Confirmar EJECUCIÓN REAL en BD?');">
        <input type="hidden" name="action" value="execute">
        <input type="hidden" name="confirm_execute" value="1">
        <fieldset><legend>2. EJECUTAR cambios</legend>
            <label>Tope cambio %: <input name="max_change_pct" type="number" min="0" step="0.5" value="<?php echo MAX_CHANGE_PCT_DEF; ?>" style="width:80px"></label>
            <label><input type="checkbox" name="apply_extremes"> aplicar también extremos &gt; tope</label>
            <button type="submit" style="background:#c30;color:#fff;">EXECUTE</button>
        </fieldset>
    </form>
<?php endif; ?>
</div></td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
