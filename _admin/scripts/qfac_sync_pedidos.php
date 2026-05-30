<?php
/**
 * qfac_sync_pedidos.php  — Sincroniza modificaciones de pedidos QFacWin -> web.
 *
 * PULL: lee EA15_COMAN/EA15_COMANLIN via helper Python (tunel SSH on-demand),
 * compara con orders_products/orders_total y aplica cantidades, altas/bajas y
 * precios. Recalcula orders_total. SOLO pedidos "limpios" y de IVA unico.
 *
 * Uso (CLI):
 *   php qfac_sync_pedidos.php            -> DRY-RUN (no escribe nada, solo informa)
 *   php qfac_sync_pedidos.php --apply    -> aplica cambios en BD
 *   php qfac_sync_pedidos.php --apply --order=10360858   -> un solo pedido
 *
 * Mapeo y gotchas: ver memoria francobordo_qfac_order_datamodel.
 *   products.CCODIART == EA15_COMANLIN.CCODIART (join exacto).
 *   NPREU es IVA INCLUIDO; products_price es SIN IVA.
 *   Lineas CCODIART NULL = envio/seguro (orders_total), no producto.
 */

date_default_timezone_set('Europe/Madrid');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

const CONFIGURE      = '/home/francobordo/public_html/_admin/includes/configure.php';
const HELPER_PY      = '/home/francobordo/qfac_recovery/venv/bin/python';
const HELPER_BIN     = '/home/francobordo/qfac_recovery/qfac_sync_pull.py';
const HELPER_TIMEOUT = 60;

const ELIGIBLE_STATUS = [1, 2, 13];          // Pendiente / Proceso / En preparacion
const WINDOW_DAYS     = 30;
const ALLOWED_TOTALS  = ['ot_subtotal', 'ot_shipping', 'ot_insurance', 'ot_tax', 'ot_total'];
const TOTAL_CAP_PCT   = 0.30;                // no aplicar si el total cambia > 30%
const PRICE_EPS       = 0.005;
const NAME_LANG       = 3;                    // idioma ES para products_name de altas

// ----------------------------------------------------------------------------
$APPLY    = in_array('--apply', $argv, true);
$ONLY     = null;
foreach ($argv as $a) {
    if (strpos($a, '--order=') === 0) $ONLY = (int)substr($a, 8);
}
$MODE = $APPLY ? 'APPLY' : 'DRY-RUN';

require CONFIGURE;
$link = mysqli_connect(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if (!$link) { fwrite(STDERR, "No conecta a MySQL\n"); exit(1); }
mysqli_set_charset($link, 'utf8');

ensure_log_table($link);

// ----------------------------------------------------------------------------
function q($link, $sql) {
    $r = mysqli_query($link, $sql);
    if ($r === false) {
        throw new RuntimeException('SQL: ' . mysqli_error($link) . ' | ' . $sql);
    }
    return $r;
}
function esc($link, $v) { return mysqli_real_escape_string($link, (string)$v); }
function vnorm($v) { return strtoupper(preg_replace('/\s+/', '', (string)$v)); }
function r4($x) { return round((float)$x, 4); }

function ensure_log_table($link) {
    q($link, "CREATE TABLE IF NOT EXISTS qfac_order_sync_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        orders_id INT NOT NULL,
        run_at DATETIME NOT NULL,
        mode VARCHAR(10) NOT NULL,
        action VARCHAR(20) NOT NULL,
        reason VARCHAR(40) NULL,
        old_total DECIMAL(15,4) NULL,
        new_total DECIMAL(15,4) NULL,
        changes_json MEDIUMTEXT NULL,
        KEY (orders_id), KEY (run_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
}

function helper_pull(array $orders_ids): array {
    $payload = json_encode(['orders_ids' => array_values(array_map('intval', $orders_ids))]);
    $desc = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
    $proc = proc_open([HELPER_PY, HELPER_BIN], $desc, $pipes);
    if (!is_resource($proc)) return ['ok'=>false,'error'=>'proc_open fallo'];
    fwrite($pipes[0], $payload); fclose($pipes[0]);
    stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
    $out=''; $err=''; $deadline = microtime(true) + HELPER_TIMEOUT;
    while (true) {
        $out .= stream_get_contents($pipes[1]);
        $err .= stream_get_contents($pipes[2]);
        $st = proc_get_status($proc);
        if (!$st['running']) break;
        if (microtime(true) > $deadline) {
            proc_terminate($proc, 9); fclose($pipes[1]); fclose($pipes[2]); proc_close($proc);
            return ['ok'=>false,'error'=>'timeout helper'];
        }
        usleep(50000);
    }
    $out .= stream_get_contents($pipes[1]); $err .= stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); proc_close($proc);
    $d = json_decode($out, true);
    if (!is_array($d)) return ['ok'=>false,'error'=>'salida no parseable. stderr='.trim($err).' stdout='.substr($out,0,400)];
    return $d;
}

// ----------------------------------------------------------------------------
function load_eligible($link, $only): array {
    $where = "COALESCE(cfactur,'')='S' AND orders_status IN (".implode(',',ELIGIBLE_STATUS).")";
    if ($only) {
        $where = "orders_id=".(int)$only;
    } else {
        $where .= " AND date_purchased >= (NOW() - INTERVAL ".WINDOW_DAYS." DAY)";
    }
    $ids = [];
    $r = q($link, "SELECT orders_id FROM orders WHERE $where ORDER BY orders_id DESC");
    while ($row = mysqli_fetch_assoc($r)) $ids[] = (int)$row['orders_id'];
    return $ids;
}

function load_web_order($link, $oid): array {
    $ops = [];
    // OJO: el precio neto realmente cobrado (y que alimenta ot_subtotal) es final_price,
    // NO products_price (que es el precio base de catalogo). QFac NPREU/(1+IVA) == final_price.
    $r = q($link, "SELECT orders_products_id, products_id, products_price, final_price, products_cost,
                          products_quantity, products_tax, CCODIVAL1
                   FROM orders_products WHERE orders_id=".(int)$oid);
    while ($row = mysqli_fetch_assoc($r)) {
        $ops[] = [
            'op_id'   => (int)$row['orders_products_id'],
            'pid'     => (int)$row['products_id'],
            'price'   => (float)$row['final_price'],     // precio cobrado neto (comparable con QFac)
            'base'    => (float)$row['products_price'],  // precio base de catalogo (no se toca)
            'cost'    => (float)$row['products_cost'],
            'qty'     => (int)$row['products_quantity'],
            'tax'     => (float)$row['products_tax'],
            'cvar'    => (string)$row['CCODIVAL1'],
        ];
    }
    $tot = [];
    $r = q($link, "SELECT orders_total_id, class, value FROM orders_total WHERE orders_id=".(int)$oid);
    while ($row = mysqli_fetch_assoc($r)) {
        $tot[$row['class']] = ['id'=>(int)$row['orders_total_id'], 'value'=>(float)$row['value']];
    }
    return ['ops'=>$ops, 'tot'=>$tot];
}

/** Resuelve CCODIART -> products_id para una lista de articulos. */
function resolve_articles($link, array $arts): array {
    $arts = array_values(array_unique(array_filter($arts, fn($a)=>$a!==null && $a!=='')));
    if (!$arts) return [];
    $in = implode(',', array_map(fn($a)=>"'".esc($link,$a)."'", $arts));
    $map = [];
    $r = q($link, "SELECT products_id, CCODIART FROM products WHERE CCODIART IN ($in)");
    while ($row = mysqli_fetch_assoc($r)) $map[(string)$row['CCODIART']] = (int)$row['products_id'];
    return $map;
}

function line_rate(array $line, array $header): ?float {
    $t = (int)$line['ntipiva'];
    $k = 'xiva'.($t >= 1 && $t <= 3 ? $t : 1);
    $rate = isset($header[$k]) ? (float)$header[$k] : 0.0;
    if ($rate <= 0) return null;
    return $rate;
}

// ----------------------------------------------------------------------------
/** Procesa un pedido. Devuelve estructura con accion/cambios; NO escribe (eso lo hace apply_changes). */
function plan_order($link, int $oid, array $qfac): array {
    $res = ['orders_id'=>$oid, 'action'=>'skip', 'reason'=>null, 'changes'=>[], 'old_total'=>null, 'new_total'=>null];

    $web = load_web_order($link, $oid);
    if (!$web['ops']) { $res['reason']='web_sin_lineas'; return $res; }

    // 1. Limpieza: orders_total solo clases permitidas
    foreach ($web['tot'] as $class => $_) {
        if (!in_array($class, ALLOWED_TOTALS, true)) { $res['reason']='extras:'.$class; return $res; }
    }
    if (!isset($web['tot']['ot_total'])) { $res['reason']='sin_ot_total'; return $res; }
    $res['old_total'] = $web['tot']['ot_total']['value'];

    $header = $qfac['headers'][0];

    // 2. Lineas de producto QFac (CCODIART != NULL); pseudo se ignoran
    $prodLines = array_values(array_filter($qfac['lines'], fn($l)=>$l['ccodiart'] !== null && $l['ccodiart'] !== ''));
    if (!$prodLines) { $res['reason']='qfac_sin_productos'; return $res; }

    // 3. Resolver articulos -> products_id. Guardamos NPREU crudo (IVA inc); el neto
    //    se calcula con el IVA del PROPIO web (products_tax), no con NTIPIVA de QFac
    //    (que es un codigo interno, NO el tipo ni un indice a XIVA1/2/3).
    $artMap = resolve_articles($link, array_column($prodLines, 'ccodiart'));
    $qfacByPid = []; // pid => list of ['cvar','qty','npreu']
    foreach ($prodLines as $l) {
        $art = $l['ccodiart'];
        if (!isset($artMap[$art])) { $res['reason']='ccodiart_no_mapea:'.$art; return $res; }
        $qfacByPid[$artMap[$art]][] = [
            'cvar' => $l['ccodival1'], 'qty' => (int)round($l['quant']),
            'npreu' => (float)$l['npreu'],
        ];
    }

    // 4. Tasa de IVA del pedido (lado web). Mixto solo bloquea si hay cambios reales.
    $webRates = [];
    foreach ($web['ops'] as $op) $webRates[(string)round($op['tax'],2)] = true;
    $orderRate = (count($webRates) === 1) ? (float)array_key_first($webRates) : null;

    $net_of = function(float $npreu, float $rate): float { return r4($npreu / (1 + $rate/100)); };

    // 5. Web ops por pid
    $webByPid = [];
    foreach ($web['ops'] as $op) $webByPid[$op['pid']][] = $op;

    // 6. Emparejar y diffear
    $changes = [];                 // lista de operaciones
    $finalLines = [];              // estado final para recomputar subtotal: ['price','qty']
    $pids = array_unique(array_merge(array_keys($webByPid), array_keys($qfacByPid)));
    foreach ($pids as $pid) {
        $wl = $webByPid[$pid] ?? [];
        $ql = $qfacByPid[$pid] ?? [];

        // Construir pares (web|null, qfac|null)
        $pairs = [];
        if (count($wl) <= 1 && count($ql) <= 1) {
            $pairs[] = [$wl[0] ?? null, $ql[0] ?? null];
        } else {
            // multi-variante: emparejar por CCODIVAL1 normalizado
            $qByVar = [];
            foreach ($ql as $i => $q1) $qByVar[vnorm($q1['cvar'])][] = $i;
            $usedQ = [];
            foreach ($wl as $w1) {
                $vn = vnorm($w1['cvar']);
                if (!empty($qByVar[$vn])) {
                    $idx = array_shift($qByVar[$vn]); $usedQ[$idx]=true;
                    $pairs[] = [$w1, $ql[$idx]];
                } else {
                    $pairs[] = [$w1, null]; // web sin contrapartida -> baja
                }
            }
            foreach ($ql as $i => $q1) if (empty($usedQ[$i])) $pairs[] = [null, $q1]; // qfac extra -> alta
            // si quedo ambiguo (alguna baja+alta del mismo pid simultanea por variante no casada) avisamos
        }

        foreach ($pairs as [$w1, $q1]) {
            if ($w1 && $q1) {
                // neto QFac con el IVA propio de la linea web (historico, exacto)
                $net = $net_of($q1['npreu'], (float)$w1['tax']);
                $qtyChanged   = ($w1['qty'] !== $q1['qty']);
                $priceChanged = (abs($w1['price'] - $net) > PRICE_EPS);
                if ($qtyChanged || $priceChanged) {
                    $changes[] = ['type'=>'update', 'op_id'=>$w1['op_id'], 'pid'=>$pid,
                        'cost'=>$w1['cost'],
                        'old'=>['qty'=>$w1['qty'],'price'=>$w1['price']],
                        'new'=>['qty'=>$q1['qty'],'price'=>$net]];
                }
                $finalLines[] = ['price'=>$net, 'qty'=>$q1['qty']];
            } elseif ($w1 && !$q1) {
                $changes[] = ['type'=>'remove', 'op_id'=>$w1['op_id'], 'pid'=>$pid,
                    'old'=>['qty'=>$w1['qty'],'price'=>$w1['price']]];
                // no aporta a finalLines
            } elseif (!$w1 && $q1) {
                if ($q1['cvar'] !== '') { $res['reason']='alta_con_variante_no_soportada:pid'.$pid; return $res; }
                if ($orderRate === null) { $res['reason']='alta_en_pedido_iva_mixto:pid'.$pid; return $res; }
                $net = $net_of($q1['npreu'], $orderRate);
                $changes[] = ['type'=>'add', 'pid'=>$pid,
                    'new'=>['qty'=>$q1['qty'],'price'=>$net,'rate'=>$orderRate]];
                $finalLines[] = ['price'=>$net, 'qty'=>$q1['qty']];
            }
        }
    }

    if (!$changes) { $res['action']='noop'; $res['reason']='sin_cambios'; return $res; }

    // Hay cambios: el recalculo de ot_tax solo es seguro con IVA unico.
    if ($orderRate === null) { $res['reason']='iva_mixto_con_cambios'; return $res; }
    $rate = $orderRate;

    // 7. Recalcular totales
    $subtotal = 0.0;
    foreach ($finalLines as $fl) $subtotal += $fl['price'] * $fl['qty'];
    $subtotal = r4($subtotal);
    $shipping  = $web['tot']['ot_shipping']['value']  ?? 0.0;
    $insurance = $web['tot']['ot_insurance']['value'] ?? 0.0;
    $hasTax    = isset($web['tot']['ot_tax']);
    $tax       = $hasTax ? r4(($subtotal + $shipping + $insurance) * $rate/100) : 0.0;
    $total     = r4($subtotal + $shipping + $insurance + $tax);
    $res['new_total'] = $total;

    // 8. Cap de variacion
    if ($res['old_total'] > 0 && abs($total - $res['old_total'])/$res['old_total'] > TOTAL_CAP_PCT) {
        $res['reason'] = 'cap_excedido(' . round(($total-$res['old_total'])/$res['old_total']*100,1) . '%)';
        return $res;
    }

    $res['action']  = 'sync';
    $res['reason']  = null;
    $res['changes'] = $changes;
    $res['totals']  = [
        'subtotal'=>$subtotal, 'shipping'=>$shipping, 'insurance'=>$insurance,
        'tax'=>$tax, 'total'=>$total, 'rate'=>$rate,
        'ids'=>[
            'sub'=>$web['tot']['ot_subtotal']['id'] ?? null,
            'tax'=>$web['tot']['ot_tax']['id'] ?? null,
            'tot'=>$web['tot']['ot_total']['id'] ?? null,
        ],
    ];
    return $res;
}

/** Aplica el plan en una transaccion. */
function apply_changes($link, array $res): void {
    $oid = $res['orders_id'];
    q($link, "START TRANSACTION");
    try {
        foreach ($res['changes'] as $c) {
            if ($c['type'] === 'update') {
                $price = $c['new']['price']; $qty = $c['new']['qty']; $cost = $c['cost'];
                $profit = r4(($price - $cost) * $qty);
                // Nota descriptiva del cambio para marcar la linea en el detalle del admin.
                $parts = [];
                if ($c['old']['qty'] != $qty) $parts[] = 'cant '.$c['old']['qty'].'->'.$qty;
                if (abs($c['old']['price'] - $price) > PRICE_EPS) $parts[] = 'precio '.number_format($c['old']['price'],2).'->'.number_format($price,2).' EUR';
                $note = substr(implode(', ', $parts), 0, 255);
                // Solo final_price (precio cobrado) y cantidad. products_price (base) NO se toca.
                q($link, "UPDATE orders_products SET final_price=".(float)$price.
                    ", products_quantity=".(int)$qty.
                    ", profit=".(float)$profit.
                    ", qfac_sync_note='".esc($link,$note)."', qfac_sync_at=NOW()".
                    " WHERE orders_products_id=".(int)$c['op_id']);
            } elseif ($c['type'] === 'remove') {
                q($link, "DELETE FROM orders_products_attributes WHERE orders_products_id=".(int)$c['op_id']);
                q($link, "DELETE FROM orders_products WHERE orders_products_id=".(int)$c['op_id']);
            } elseif ($c['type'] === 'add') {
                add_product_line($link, $oid, $c);
            }
        }
        // totales
        $t = $res['totals'];
        if ($t['ids']['sub']) q($link, "UPDATE orders_total SET value=".(float)$t['subtotal']." WHERE orders_total_id=".(int)$t['ids']['sub']);
        if ($t['ids']['tax']) q($link, "UPDATE orders_total SET value=".(float)$t['tax']." WHERE orders_total_id=".(int)$t['ids']['tax']);
        if ($t['ids']['tot']) q($link, "UPDATE orders_total SET value=".(float)$t['total']." WHERE orders_total_id=".(int)$t['ids']['tot']);

        // historial visible (sin email)
        $n = count($res['changes']);
        $comment = "Pedido actualizado automaticamente desde QFacWin (sync de lineas): $n cambio(s). Total $".number_format($res['old_total'],2)." -> $".number_format($t['total'],2).".";
        $cur = (int) (mysqli_fetch_assoc(q($link,"SELECT orders_status FROM orders WHERE orders_id=".(int)$oid))['orders_status'] ?? 2);
        q($link, "INSERT INTO orders_status_history SET orders_id=".(int)$oid.
            ", orders_status_id=".(int)$cur.", date_added=NOW(), customer_notified=0".
            ", comments='".esc($link,$comment)."'");

        q($link, "COMMIT");
    } catch (Throwable $e) {
        q($link, "ROLLBACK");
        throw $e;
    }
}

function add_product_line($link, int $oid, array $c): void {
    $pid = (int)$c['pid']; $qty = (int)$c['new']['qty']; $price = (float)$c['new']['price']; $rate = (float)$c['new']['rate'];
    $r = q($link, "SELECT p.products_model, p.product_ean, p.products_cost, p.products_price AS base,
                          (SELECT products_name FROM products_description
                            WHERE products_id=p.products_id AND language_id=".NAME_LANG." LIMIT 1) AS pname
                   FROM products p WHERE p.products_id=$pid LIMIT 1");
    $p = mysqli_fetch_assoc($r);
    if (!$p) throw new RuntimeException("alta: products_id $pid no existe");
    $cost = (float)$p['products_cost'];
    $base = (float)$p['base'];          // precio base de catalogo
    $profit = r4(($price - $cost) * $qty);
    $name = substr((string)$p['pname'], 0, 80);
    $ean  = (int)$p['product_ean'];
    q($link, "INSERT INTO orders_products SET
        orders_id=".(int)$oid.", products_id=$pid,
        products_model='".esc($link,$p['products_model'])."',
        product_ean=$ean, products_name='".esc($link,$name)."',
        products_price=$base, products_cost=$cost, final_price=$price,
        products_tax=".(float)$rate.", products_quantity=$qty, profit=$profit,
        CCODIVAL1='', CCODIVAL2='', CCODIPROP1='', CCODIPROP2='', CPROP1='', CPROP2='',
        qfac_sync_note='alta desde QFac (cant $qty)', qfac_sync_at=NOW()");
}

function log_run($link, array $res, string $mode): void {
    $changes = json_encode($res['changes'] ?? [], JSON_UNESCAPED_UNICODE);
    q($link, "INSERT INTO qfac_order_sync_log SET orders_id=".(int)$res['orders_id'].
        ", run_at=NOW(), mode='".esc($link,$mode)."', action='".esc($link,$res['action'])."'".
        ", reason=".($res['reason']===null?'NULL':"'".esc($link,$res['reason'])."'").
        ", old_total=".($res['old_total']===null?'NULL':(float)$res['old_total']).
        ", new_total=".($res['new_total']===null?'NULL':(float)$res['new_total']).
        ", changes_json='".esc($link,$changes)."'");
}

// ----------------------------------------------------------------------------
$started = date('Y-m-d H:i:s');
$eligible = load_eligible($link, $ONLY);
echo "[$started] qfac_sync_pedidos MODE=$MODE  elegibles=".count($eligible)."\n";
if (!$eligible) { echo "Nada que hacer.\n"; exit(0); }

$pull = helper_pull($eligible);
if (empty($pull['ok'])) { fwrite(STDERR, "Helper error: ".($pull['error']??'?')."\n"); exit(2); }
$qfacOrders = $pull['orders'] ?? [];
echo "QFac devolvio ".count($qfacOrders)." pedidos con datos.\n";

$stats = ['sync'=>0,'noop'=>0,'skip'=>0,'applied'=>0,'errors'=>0];
$skips = [];

foreach ($eligible as $oid) {
    $key = (string)$oid;
    if (!isset($qfacOrders[$key])) { continue; } // no esta en QFac -> nada
    try {
        $res = plan_order($link, $oid, $qfacOrders[$key]);
    } catch (Throwable $e) {
        $stats['errors']++; echo "  #$oid ERROR plan: ".$e->getMessage()."\n"; continue;
    }
    $stats[$res['action']]++;
    if ($res['action'] === 'sync') {
        $n = count($res['changes']);
        echo "  #$oid SYNC: $n cambio(s)  total ".number_format($res['old_total'],2)." -> ".number_format($res['new_total'],2)."\n";
        foreach ($res['changes'] as $c) {
            if ($c['type']==='update')      echo "      ~ pid {$c['pid']}: qty {$c['old']['qty']}->{$c['new']['qty']}  precio ".number_format($c['old']['price'],4)."->".number_format($c['new']['price'],4)."\n";
            elseif ($c['type']==='remove')  echo "      - pid {$c['pid']} (qty {$c['old']['qty']}) BAJA\n";
            elseif ($c['type']==='add')     echo "      + pid {$c['pid']} (qty {$c['new']['qty']} @ ".number_format($c['new']['price'],4).") ALTA\n";
        }
        if ($APPLY) {
            try { apply_changes($link, $res); $stats['applied']++; echo "      => APLICADO\n"; }
            catch (Throwable $e) { $stats['errors']++; echo "      => ERROR apply: ".$e->getMessage()."\n"; }
        }
        log_run($link, $res, $MODE);
    } elseif ($res['action'] === 'skip') {
        $skips[$res['reason']] = ($skips[$res['reason']] ?? 0) + 1;
        log_run($link, $res, $MODE);
    }
    // noop: no log para no inflar
}

echo "\n--- RESUMEN ($MODE) ---\n";
echo "sync={$stats['sync']}  aplicados={$stats['applied']}  noop={$stats['noop']}  skip={$stats['skip']}  errores={$stats['errors']}\n";
if ($skips) { echo "Saltados por motivo:\n"; foreach ($skips as $r=>$n) echo "   $n x $r\n"; }
if (!$APPLY) echo "\n(DRY-RUN: no se ha escrito ningun cambio. Lanzar con --apply para aplicar.)\n";
