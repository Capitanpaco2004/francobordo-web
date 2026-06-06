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

const ELIGIBLE_STATUS = [1, 2, 7, 13];       // Pendiente / Proceso / Enviado Parcialmente / En preparacion
const PARTIAL_STATUS  = 7;                    // Enviado Parcialmente
const WINDOW_DAYS     = 30;
const ALLOWED_TOTALS  = ['ot_subtotal', 'ot_shipping', 'ot_insurance', 'ot_tax', 'ot_total'];
const TOTAL_CAP_PCT   = 0.50;                // bajada sospechosa si baja > 50% del total ...
const TOTAL_CAP_ABS   = 100.0;               // ... Y ademas > 100 EUR (ambas -> revision manual)
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
/** Formato de la columna orders_total.text tal cual lo muestra la web: "1.186,59&euro;".
 *  ot_total va envuelto en <strong>. (miles '.', decimal ',', simbolo &euro;). */
function ot_text($v, bool $bold = false) {
    $s = number_format((float)$v, 2, ',', '.') . '&euro;';
    return $bold ? '<strong>'.$s.'</strong>' : $s;
}

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
    // JOIN a products para traer el CCODIART de cada linea (para detectar composicion).
    // vcode = CCODIVAL AUTORITATIVO de la variante de la linea (via opa->products_options_values),
    // porque orders_products.CCODIVAL1 a menudo viene vacio. Es la clave de emparejado con QFac.
    $r = q($link, "SELECT op.orders_products_id, op.products_id, op.products_price, op.final_price, op.products_cost,
                          op.products_quantity, op.products_tax, op.CCODIVAL1, p.CCODIART,
                          (SELECT pov.CCODIVAL FROM orders_products_attributes opa
                             JOIN products_options_values pov ON pov.products_options_values_id=opa.products_options_values_id AND pov.language_id=".NAME_LANG."
                             WHERE opa.orders_products_id=op.orders_products_id
                             ORDER BY opa.orders_products_attributes_id LIMIT 1) AS vcode
                   FROM orders_products op LEFT JOIN products p ON p.products_id=op.products_id
                   WHERE op.orders_id=".(int)$oid);
    while ($row = mysqli_fetch_assoc($r)) {
        $vcode = (string)($row['vcode'] ?? '');
        if ($vcode === '') $vcode = (string)$row['CCODIVAL1']; // fallback al de la linea
        $ops[] = [
            'op_id'   => (int)$row['orders_products_id'],
            'pid'     => (int)$row['products_id'],
            'price'   => (float)$row['final_price'],     // precio cobrado neto (comparable con QFac)
            'base'    => (float)$row['products_price'],  // precio base de catalogo (no se toca)
            'cost'    => (float)$row['products_cost'],
            'qty'     => (int)$row['products_quantity'],
            'tax'     => (float)$row['products_tax'],
            'cvar'    => $vcode,
            'ccodiart'=> (string)($row['CCODIART'] ?? ''),
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

/** Resuelve (pid, CCODIVAL de QFac) -> atributo del catalogo. NULL si no casa.
 *  products_options_values.CCODIVAL guarda el codigo de variante de QFac tal cual. */
function resolve_variant_attr($link, int $pid, string $ccodival): ?array {
    $r = q($link, "SELECT pa.products_attributes_id, pa.options_id, pa.options_values_id,
                          pa.options_values_price, pa.price_prefix, pa.options_values_weight, pa.weight_prefix, pa.reference,
                          pov.products_options_values_name AS vname, po.products_options_name AS oname
                   FROM products_attributes pa
                   JOIN products_options_values pov ON pov.products_options_values_id=pa.options_values_id AND pov.language_id=".NAME_LANG."
                   JOIN products_options po ON po.products_options_id=pa.options_id AND po.language_id=".NAME_LANG."
                   WHERE pa.products_id=".(int)$pid." AND pov.CCODIVAL='".esc($link,$ccodival)."' LIMIT 1");
    $row = mysqli_fetch_assoc($r);
    return $row ?: null;
}

function line_rate(array $line, array $header): ?float {
    $t = (int)$line['ntipiva'];
    $k = 'xiva'.($t >= 1 && $t <= 3 ? $t : 1);
    $rate = isset($header[$k]) ? (float)$header[$k] : 0.0;
    if ($rate <= 0) return null;
    return $rate;
}

// ----------------------------------------------------------------------------
/** Diff de lineas (cantidades/precios/altas/bajas) + recalculo de totales.
 *  Devuelve ['changes'=>[], 'totals'=>?, 'old_total'=>, 'new_total'=>, 'reason'=>?].
 *  reason != null => NO se aplican cambios de linea (guard fallido). changes vacio + reason null => sin cambios. */
function plan_lines($link, array $web, array $prodLines): array {
    $out = ['changes'=>[], 'totals'=>null,
            'old_total'=>($web['tot']['ot_total']['value'] ?? null), 'new_total'=>null, 'reason'=>null];

    // Limpieza: orders_total solo clases permitidas
    foreach ($web['tot'] as $class => $_) {
        if (!in_array($class, ALLOWED_TOTALS, true)) { $out['reason']='extras:'.$class; return $out; }
    }
    if (!isset($web['tot']['ot_total'])) { $out['reason']='sin_ot_total'; return $out; }

    // GUARD COMPOSICION (kits/escandallos): si el pedido contiene un producto CON COMPOSICION
    // (CCODIART padre en EA15_COMPOA), QFac lo explota en componentes y el mapeo 1-linea del web
    // se rompe. Sacamos esos pedidos de la ecuacion -> NO tocar lineas (revision manual).
    global $COMPOSITE;
    foreach ($web['ops'] as $op) {
        if (!empty($op['ccodiart']) && isset($COMPOSITE[$op['ccodiart']])) { $out['reason']='producto_con_composicion'; return $out; }
    }
    foreach ($prodLines as $l) {
        if (isset($COMPOSITE[$l['ccodiart']])) { $out['reason']='producto_con_composicion'; return $out; }
    }

    // GUARD secundario anti-kit: si alguna linea de producto trae NPREU=0 (componente de kit o
    // regalo) -> NO tocar lineas. (Red por si un compuesto no estuviera en EA15_COMPOA.)
    foreach ($prodLines as $l) {
        if ((float)$l['npreu'] < PRICE_EPS) { $out['reason']='kit_o_linea_precio_0'; return $out; }
    }

    // Resolver articulos -> products_id. NPREU crudo (IVA inc); el neto se calcula con el
    // IVA del PROPIO web (products_tax), no con NTIPIVA de QFac (codigo interno, no fiable).
    $artMap = resolve_articles($link, array_column($prodLines, 'ccodiart'));
    $qfacByPid = [];
    foreach ($prodLines as $l) {
        $art = $l['ccodiart'];
        if (!isset($artMap[$art])) { $out['reason']='ccodiart_no_mapea:'.$art; return $out; }
        $qfacByPid[$artMap[$art]][] = ['cvar'=>$l['ccodival1'], 'qty'=>(int)round($l['quant']), 'npreu'=>(float)$l['npreu']];
    }

    $webRates = [];
    foreach ($web['ops'] as $op) $webRates[(string)round($op['tax'],2)] = true;
    $orderRate = (count($webRates) === 1) ? (float)array_key_first($webRates) : null;
    $net_of = function(float $npreu, float $rate): float { return r4($npreu / (1 + $rate/100)); };

    $webByPid = [];
    foreach ($web['ops'] as $op) $webByPid[$op['pid']][] = $op;

    $changes = []; $finalLines = [];
    $pids = array_unique(array_merge(array_keys($webByPid), array_keys($qfacByPid)));
    foreach ($pids as $pid) {
        $wl = $webByPid[$pid] ?? [];
        $ql = $qfacByPid[$pid] ?? [];

        $pairs = [];
        if (count($wl) <= 1 && count($ql) <= 1) {
            $pairs[] = [$wl[0] ?? null, $ql[0] ?? null];
        } else {
            $qByVar = [];
            foreach ($ql as $i => $q1) $qByVar[vnorm($q1['cvar'])][] = $i;
            $usedQ = [];
            foreach ($wl as $w1) {
                $vn = vnorm($w1['cvar']);
                if (!empty($qByVar[$vn])) { $idx = array_shift($qByVar[$vn]); $usedQ[$idx]=true; $pairs[] = [$w1, $ql[$idx]]; }
                else { $pairs[] = [$w1, null]; }
            }
            foreach ($ql as $i => $q1) if (empty($usedQ[$i])) $pairs[] = [null, $q1];
        }

        foreach ($pairs as [$w1, $q1]) {
            if ($w1 && $q1) {
                $net = $net_of($q1['npreu'], (float)$w1['tax']);
                $qtyChanged   = ($w1['qty'] !== $q1['qty']);
                $priceChanged = (abs($w1['price'] - $net) > PRICE_EPS);
                if ($qtyChanged || $priceChanged) {
                    $changes[] = ['type'=>'update', 'op_id'=>$w1['op_id'], 'pid'=>$pid, 'cost'=>$w1['cost'],
                        'old'=>['qty'=>$w1['qty'],'price'=>$w1['price']], 'new'=>['qty'=>$q1['qty'],'price'=>$net]];
                }
                $finalLines[] = ['price'=>$net, 'qty'=>$q1['qty']];
            } elseif ($w1 && !$q1) {
                $changes[] = ['type'=>'remove', 'op_id'=>$w1['op_id'], 'pid'=>$pid, 'old'=>['qty'=>$w1['qty'],'price'=>$w1['price']]];
            } elseif (!$w1 && $q1) {
                if ($orderRate === null) { $out['reason']='alta_en_pedido_iva_mixto:pid'.$pid; return $out; }
                $net = $net_of($q1['npreu'], $orderRate);
                $variant = null;
                if ($q1['cvar'] !== '') {
                    // Alta CON variante: resolver CCODIVAL -> atributo del catalogo (products_attributes).
                    $variant = resolve_variant_attr($link, $pid, $q1['cvar']);
                    if ($variant === null) { $out['reason']='alta_variante_no_mapea:pid'.$pid.':'.$q1['cvar']; return $out; }
                }
                $changes[] = ['type'=>'add', 'pid'=>$pid, 'cvar'=>$q1['cvar'],
                    'new'=>['qty'=>$q1['qty'],'price'=>$net,'rate'=>$orderRate], 'variant'=>$variant];
                $finalLines[] = ['price'=>$net, 'qty'=>$q1['qty']];
            }
        }
    }

    if (!$changes) { return $out; }  // sin cambios de linea

    if ($orderRate === null) { $out['reason']='iva_mixto_con_cambios'; return $out; }
    $rate = $orderRate;

    $subtotal = 0.0;
    foreach ($finalLines as $fl) $subtotal += $fl['price'] * $fl['qty'];
    $subtotal = r4($subtotal);
    $shipping  = $web['tot']['ot_shipping']['value']  ?? 0.0;
    $insurance = $web['tot']['ot_insurance']['value'] ?? 0.0;
    $hasTax    = isset($web['tot']['ot_tax']);
    $tax       = $hasTax ? r4(($subtotal + $shipping + $insurance) * $rate/100) : 0.0;
    $total     = r4($subtotal + $shipping + $insurance + $tax);
    $out['new_total'] = $total;

    // Cap DIRECCIONAL: solo bloquea BAJADAS grandes en % Y en importe (ambas), la firma de un
    // descuadre real (kit no detectado, etc.). Las reducciones normales de cantidad/linea pasan
    // (en pedidos pequenos quitar 1 ud ya es >30%, por eso NO basta el %). Subidas siempre OK.
    if ($out['old_total'] > 0) {
        $drop = $out['old_total'] - $total;                 // positivo si baja
        $dropPct = $drop / $out['old_total'];
        if ($dropPct > TOTAL_CAP_PCT && $drop > TOTAL_CAP_ABS) {
            $out['reason'] = 'cap_bajada(' . round(-$dropPct*100,1) . '%, ' . round($drop,2) . 'EUR)';
            return $out;
        }
    }

    $out['changes'] = $changes;
    $out['totals']  = [
        'subtotal'=>$subtotal, 'shipping'=>$shipping, 'insurance'=>$insurance,
        'tax'=>$tax, 'total'=>$total, 'rate'=>$rate,
        'ids'=>[
            'sub'=>$web['tot']['ot_subtotal']['id'] ?? null,
            'tax'=>$web['tot']['ot_tax']['id'] ?? null,
            'tot'=>$web['tot']['ot_total']['id'] ?? null,
        ],
    ];
    return $out;
}

/** Procesa un pedido. Solo actua sobre pedidos NO servidos (CSERVIDA='N').
 *  Combina: (a) cambio a "Enviado Parcialmente" si hay unidades servidas, y
 *  (b) sync de lineas (con sus guardas). NO escribe (eso lo hace apply_changes). */
function plan_order($link, int $oid, array $qfac): array {
    $res = ['orders_id'=>$oid, 'action'=>'skip', 'reason'=>null, 'changes'=>[],
            'old_total'=>null, 'new_total'=>null, 'status_change'=>null, 'cur_status'=>null, 'partial'=>false];

    $header = $qfac['headers'][0];

    // GATE: solo pedidos NO servidos en QFac (CSERVIDA = 'N'). Servidos = fuera de alcance.
    $cservida = strtoupper(trim((string)($header['cservida'] ?? '')));
    if ($cservida !== 'N') { $res['reason']='servido_cservida='.($cservida ?: 'vacio'); return $res; }

    $web = load_web_order($link, $oid);
    if (!$web['ops']) { $res['reason']='web_sin_lineas'; return $res; }
    $res['old_total'] = $web['tot']['ot_total']['value'] ?? null;

    // Estado web actual
    $rowSt = mysqli_fetch_assoc(q($link, "SELECT orders_status FROM orders WHERE orders_id=".(int)$oid));
    $curStatus = (int)($rowSt['orders_status'] ?? 0);
    $res['cur_status'] = $curStatus;

    // Lineas de producto QFac (CCODIART != NULL; pseudo envio/seguro fuera)
    $prodLines = array_values(array_filter($qfac['lines'], fn($l)=>$l['ccodiart'] !== null && $l['ccodiart'] !== ''));
    if (!$prodLines) { $res['reason']='qfac_sin_productos'; return $res; }

    // (a) Parcial: alguna linea GENUINAMENTE partida -> 0 < NSERVIT < QUANT (servida en parte).
    //     OJO: NSERVIT>0 a secas NO sirve: QFac pone unidades servidas durante el picking normal,
    //     mucho antes de un envio parcial real -> marcaba parcial prematuramente (caso 10360619).
    $partial = false;
    foreach ($prodLines as $l) {
        $ns = (float)($l['nservit'] ?? 0);
        $qt = (float)($l['quant'] ?? 0);
        if ($ns > 0 && $ns < $qt) { $partial = true; break; }
    }
    $res['partial'] = $partial;
    $statusChange = ($partial && $curStatus !== PARTIAL_STATUS) ? PARTIAL_STATUS : null;
    $res['status_change'] = $statusChange;

    // (b) Sync de lineas (independiente del cambio de estado)
    $lp = plan_lines($link, $web, $prodLines);
    $res['old_total'] = $lp['old_total'];
    $res['new_total'] = $lp['new_total'];
    $res['changes']   = $lp['changes'];
    $res['totals']    = $lp['totals'] ?? null;

    if ($lp['changes'] || $statusChange !== null) {
        $res['action'] = 'sync';
        $res['reason'] = $lp['reason'];   // informativo: por que NO se sincronizaron lineas (si aplica)
        return $res;
    }
    if ($lp['reason']) { $res['action']='skip'; $res['reason']=$lp['reason']; return $res; }
    $res['action']='noop'; $res['reason']='sin_cambios';
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
        // totales (solo si hubo cambios de linea). OJO: orders_total tiene 'value' (numerico)
        // y 'text' (cadena formateada que es la que MUESTRA la web). Hay que actualizar AMBAS,
        // o el total visible queda stale (formato: "1.186,59&euro;", ot_total en <strong>).
        $hasLineChanges = !empty($res['changes']);
        if ($hasLineChanges && !empty($res['totals'])) {
            $t = $res['totals'];
            if ($t['ids']['sub']) q($link, "UPDATE orders_total SET value=".(float)$t['subtotal'].", text='".esc($link,ot_text($t['subtotal']))."' WHERE orders_total_id=".(int)$t['ids']['sub']);
            if ($t['ids']['tax']) q($link, "UPDATE orders_total SET value=".(float)$t['tax'].", text='".esc($link,ot_text($t['tax']))."' WHERE orders_total_id=".(int)$t['ids']['tax']);
            if ($t['ids']['tot']) q($link, "UPDATE orders_total SET value=".(float)$t['total'].", text='".esc($link,ot_text($t['total'], true))."' WHERE orders_total_id=".(int)$t['ids']['tot']);
        }

        // cambio de estado a Enviado Parcialmente (si procede)
        $statusChange = $res['status_change'] ?? null;
        $curStatus    = (int)($res['cur_status'] ?? 0);
        $newStatus    = $statusChange !== null ? (int)$statusChange : $curStatus;
        if ($statusChange !== null) {
            q($link, "UPDATE orders SET orders_status=".(int)$newStatus.", last_modified=NOW() WHERE orders_id=".(int)$oid);
        }

        // historial SILENCIOSO: customer_notified=1 (ya notificado) -> cron_mail_status.php lo
        // IGNORA y NO emaila al cliente. notify=0 NO es silencioso: es el disparador del email de
        // estados publicos (5/13/7) y ademas secuestraria el email legitimo del flujo de envio.
        $parts = [];
        if ($hasLineChanges) {
            $n = count($res['changes']);
            $parts[] = "sync de lineas: $n cambio(s), total $".number_format($res['old_total'],2)." -> $".number_format($res['totals']['total'],2);
        }
        if ($statusChange === PARTIAL_STATUS) {
            $parts[] = "marcado Enviado Parcialmente (QFacWin: linea servida parcialmente)";
        }
        $comment = "Pedido actualizado automaticamente desde QFacWin (".implode('; ', $parts).").";
        q($link, "INSERT INTO orders_status_history SET orders_id=".(int)$oid.
            ", orders_status_id=".(int)$newStatus.", date_added=NOW(), customer_notified=1".
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
    $variant = $c['variant'] ?? null;
    $cvar = (string)($c['cvar'] ?? '');
    // orders_products.product_ean es INT de 4 bytes (no cabe un EAN-13) y la tienda lo deja a 0
    // en todas las lineas; ponerlo a 0 para respetar esa convencion (y evitar el cap a 2147483647).
    q($link, "INSERT INTO orders_products SET
        orders_id=".(int)$oid.", products_id=$pid,
        products_model='".esc($link,$p['products_model'])."',
        product_ean=0, products_name='".esc($link,$name)."',
        products_price=$base, products_cost=$cost, final_price=$price,
        products_tax=".(float)$rate.", products_quantity=$qty, profit=$profit,
        CCODIVAL1='".esc($link,$cvar)."', CCODIVAL2='', CCODIPROP1='', CCODIPROP2='', CPROP1='', CPROP2='',
        qfac_sync_note='alta desde QFac (cant $qty)', qfac_sync_at=NOW()");
    if ($variant !== null) {
        $opid = (int)mysqli_insert_id($link);
        // Fila de atributo (variante). products_attributes_ean en opa es INT -> 0. NIDATRIB = id catalogo.
        q($link, "INSERT INTO orders_products_attributes SET
            orders_id=".(int)$oid.", orders_products_id=$opid,
            products_options='".esc($link,$variant['oname'])."',
            products_options_values='".esc($link,$variant['vname'])."',
            options_values_price=".(float)$variant['options_values_price'].",
            price_prefix='".esc($link,$variant['price_prefix'] ?: '+')."',
            reference='".esc($link,(string)$variant['reference'])."',
            products_attributes_ean=0,
            NIDATRIB=".(int)$variant['products_attributes_id'].",
            products_options_id=".(int)$variant['options_id'].",
            products_options_values_id=".(int)$variant['options_values_id'].",
            options_values_weight=".(float)$variant['options_values_weight'].",
            weight_prefix='".esc($link,(string)($variant['weight_prefix'] ?: ''))."'");
    }
}

function log_run($link, array $res, string $mode): void {
    $changes = json_encode($res['changes'] ?? [], JSON_UNESCAPED_UNICODE);
    $reason = $res['reason'];
    if (($res['status_change'] ?? null) === PARTIAL_STATUS) $reason = ($reason ? $reason.' + ' : '').'parcial->7';
    q($link, "INSERT INTO qfac_order_sync_log SET orders_id=".(int)$res['orders_id'].
        ", run_at=NOW(), mode='".esc($link,$mode)."', action='".esc($link,$res['action'])."'".
        ", reason=".($reason===null?'NULL':"'".esc($link,$reason)."'").
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
// Set de articulos CON COMPOSICION (kits) para excluirlos del sync de lineas. global.
$COMPOSITE = array_fill_keys($pull['composite_arts'] ?? [], true);
echo "QFac devolvio ".count($qfacOrders)." pedidos con datos. Articulos con composicion: ".count($COMPOSITE).".\n";

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
        $tot = ($n && $res['new_total']!==null) ? "  total ".number_format($res['old_total'],2)." -> ".number_format($res['new_total'],2) : "";
        echo "  #$oid SYNC: $n cambio(s) de linea$tot\n";
        if ($res['status_change'] === PARTIAL_STATUS) echo "      * estado {$res['cur_status']} -> 7 (Enviado Parcialmente)\n";
        if ($res['reason']) echo "      (lineas NO sincronizadas: {$res['reason']})\n";
        foreach ($res['changes'] as $c) {
            if ($c['type']==='update')      echo "      ~ pid {$c['pid']}: qty {$c['old']['qty']}->{$c['new']['qty']}  precio ".number_format($c['old']['price'],4)."->".number_format($c['new']['price'],4)."\n";
            elseif ($c['type']==='remove')  echo "      - pid {$c['pid']} (qty {$c['old']['qty']}) BAJA\n";
            elseif ($c['type']==='add')     echo "      + pid {$c['pid']}".(!empty($c['cvar'])?" [var ".$c['cvar']."]":"")." (qty {$c['new']['qty']} @ ".number_format($c['new']['price'],4).") ALTA".(!empty($c['variant'])?" -> ".$c['variant']['oname'].": ".$c['variant']['vname']:"")."\n";
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
