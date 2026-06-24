<?php
/* g1_reprice_from_retail.php  —  recalcula el precio de un GRUPO de clientes = precio RETAIL (grupo 0) x factor.
 *
 *   Base      : products_groups[grupo].customers_group_price        = products.products_price            * factor
 *   Atributos : products_attributes_groups[pa,grupo].options_values_price = products_attributes.options_values_price * factor
 *               (el price_prefix se copia del de retail)
 *
 * El grupo 0 (Retail) NO tiene filas override: el precio se lee de products / products_attributes (verificado a nivel global).
 * Solo toca el grupo indicado. No toca stock, pesos, ni otros grupos. Resultado: precio final del grupo = precio final retail x factor.
 *
 * SELECCION DE PRODUCTOS (obligatoria, elige una):
 *   --pid=N           un producto
 *   --pids=1,2,3      lista de productos
 *   --all             todos los productos que YA tienen precio de ese grupo (fila products_groups[grupo])
 *
 * OPCIONES:
 *   --group=N         grupo destino           (def 1 = Profesionales)
 *   --factor=F        multiplicador sobre retail (def 0.75)
 *   --mode=update|upsert   update(def)=solo recalcula filas existentes; upsert=ademas CREA las que falten
 *                          (upsert solo se permite con --pid/--pids, no con --all)
 *   --limit=N         limita nº de productos (util para probar lotes con --all)
 *   --apply           ESCRIBE. Sin esto = DRY-RUN (no cambia nada)
 *   --no-backup       no genera backup (por defecto, en --apply vuelca un .sql de restauracion)
 *
 * EJEMPLOS:
 *   php g1_reprice_from_retail.php --pid=9799                 # dry-run de un producto
 *   php g1_reprice_from_retail.php --pid=9799 --apply         # aplica a un producto
 *   php g1_reprice_from_retail.php --pids=9799,9801 --apply
 *   php g1_reprice_from_retail.php --all --limit=50 --apply   # prueba con 50 productos
 *   php g1_reprice_from_retail.php --all --apply              # recalcula TODO el grupo 1 (37k+ productos)
 *   php g1_reprice_from_retail.php --pid=9799 --group=1 --factor=0.80 --apply
 */
error_reporting(E_ALL & ~E_DEPRECATED);
date_default_timezone_set('Europe/Madrid');
require '/home/francobordo/public_html/includes/configure.php';

/* ---------- args ---------- */
$o = getopt('', ['pid:','pids:','all','group:','factor:','mode:','limit:','apply','no-backup']);
$GROUP  = isset($o['group'])  ? (int)$o['group']     : 1;
$FACTOR = isset($o['factor']) ? (float)$o['factor']  : 0.75;
$MODE   = isset($o['mode'])   ? strtolower($o['mode']) : 'update';
$LIMIT  = isset($o['limit'])  ? (int)$o['limit']     : 0;
$APPLY  = isset($o['apply']);
$BACKUP = !isset($o['no-backup']);
if (!in_array($MODE, ['update','upsert'], true)) { fwrite(STDERR, "mode debe ser update|upsert\n"); exit(2); }
if ($FACTOR <= 0 || $FACTOR > 5) { fwrite(STDERR, "factor fuera de rango razonable (0,5]\n"); exit(2); }

$ALL = isset($o['all']);
$pids = [];
if (isset($o['pid']))  $pids[] = (int)$o['pid'];
if (isset($o['pids'])) foreach (explode(',', $o['pids']) as $x) { $x = (int)trim($x); if ($x>0) $pids[] = $x; }
$pids = array_values(array_unique(array_filter($pids)));
if (!$ALL && !$pids) { fwrite(STDERR, "Falta seleccion: usa --pid, --pids o --all\n"); exit(2); }
if ($ALL && $pids)   { fwrite(STDERR, "--all es incompatible con --pid/--pids\n"); exit(2); }
if ($ALL && $MODE === 'upsert') { fwrite(STDERR, "upsert no se permite con --all (evita insertar filas masivas). Usa --pids para crear filas que falten.\n"); exit(2); }

$m = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($m->connect_errno) { die("CONNECT ERR: ".$m->connect_error."\n"); }
$m->set_charset('utf8');

/* ---------- resolver scope de products_id ---------- */
$idScope = null;   // null = todo el grupo (solo --all sin limit); array = lista concreta
if ($pids) {
    $idScope = $pids;
} elseif ($LIMIT > 0) { // --all con limite: coge los primeros N productos con fila de grupo
    $r = $m->query("SELECT products_id FROM products_groups WHERE customers_group_id=$GROUP ORDER BY products_id LIMIT $LIMIT");
    $idScope = []; while ($x = $r->fetch_assoc()) $idScope[] = (int)$x['products_id'];
}
$inIds = $idScope !== null ? (' AND %s.products_id IN ('.implode(',', array_map('intval',$idScope)).')') : '';
$basePidFilter = $idScope !== null ? sprintf($inIds,'pg')  : '';
$attrPidFilter = $idScope !== null ? sprintf($inIds,'pag') : '';

echo ($APPLY?"== APPLY ==":"== DRY-RUN (añade --apply para escribir) ==")
   . "  grupo=$GROUP  factor=$FACTOR  mode=$MODE  "
   . ($pids ? "pids=".implode(',',$pids) : ("--all".($LIMIT?" limit=$LIMIT (".count($idScope)." productos)":""))) . "\n\n";

/* ---------- DRY-RUN: contar cambios ---------- */
function scalar($m,$sql){ $r=$m->query($sql); if(!$r){ fwrite(STDERR,"SQL ERR: ".$m->error."\n[".$sql."]\n"); exit(3);} $a=$r->fetch_row(); return $a?$a[0]:0; }

$baseChg = scalar($m, "SELECT COUNT(*) FROM products_groups pg JOIN products p ON p.products_id=pg.products_id
                       WHERE pg.customers_group_id=$GROUP AND ROUND(p.products_price*$FACTOR,4) <> pg.customers_group_price $basePidFilter");
$baseTot = scalar($m, "SELECT COUNT(*) FROM products_groups pg WHERE pg.customers_group_id=$GROUP $basePidFilter");
$attrChg = scalar($m, "SELECT COUNT(*) FROM products_attributes_groups pag JOIN products_attributes pa ON pa.products_attributes_id=pag.products_attributes_id
                       WHERE pag.customers_group_id=$GROUP AND (ROUND(pa.options_values_price*$FACTOR,4) <> pag.options_values_price OR pa.price_prefix <> pag.price_prefix) $attrPidFilter");
$attrTot = scalar($m, "SELECT COUNT(*) FROM products_attributes_groups pag WHERE pag.customers_group_id=$GROUP $attrPidFilter");

echo "Filas a recalcular (mode=update):\n";
echo "  base      : $baseChg cambian de $baseTot existentes\n";
echo "  atributos : $attrChg cambian de $attrTot existentes\n";

$baseMiss = 0; $attrMiss = 0;
if ($MODE === 'upsert' && $idScope !== null) {
    $idin = implode(',', array_map('intval',$idScope));
    $baseMiss = scalar($m, "SELECT COUNT(*) FROM products p LEFT JOIN products_groups pg ON pg.products_id=p.products_id AND pg.customers_group_id=$GROUP
                            WHERE p.products_id IN ($idin) AND pg.products_id IS NULL");
    $attrMiss = scalar($m, "SELECT COUNT(*) FROM products_attributes pa LEFT JOIN products_attributes_groups pag ON pag.products_attributes_id=pa.products_attributes_id AND pag.customers_group_id=$GROUP
                            WHERE pa.products_id IN ($idin) AND pag.products_attributes_id IS NULL");
    echo "  + crear (upsert): base=$baseMiss  atributos=$attrMiss\n";
}

/* ---------- tabla detallada si son pocos productos ---------- */
if ($idScope !== null && count($idScope) <= 8) {
    foreach ($idScope as $PID) {
        $p = $m->query("SELECT products_price FROM products WHERE products_id=$PID")->fetch_assoc();
        if (!$p) { echo "\n  [pid $PID NO EXISTE]\n"; continue; }
        $g0 = (float)$p['products_price'];
        $cur = $m->query("SELECT customers_group_price FROM products_groups WHERE products_id=$PID AND customers_group_id=$GROUP")->fetch_assoc();
        printf("\n  pid %d  BASE: G0=%.4f -> %.4f (antes %s)\n", $PID, $g0, round($g0*$FACTOR,4), $cur?number_format($cur['customers_group_price'],4,'.',''):'sin fila');
        $r = $m->query("SELECT pa.products_attributes_id paid, pa.options_values_price g0, pa.price_prefix p0,
                               pov.products_options_values_name v, pag.options_values_price g1
                        FROM products_attributes pa
                        LEFT JOIN products_options_values pov ON pov.products_options_values_id=pa.options_values_id AND pov.language_id=4
                        LEFT JOIN products_attributes_groups pag ON pag.products_attributes_id=pa.products_attributes_id AND pag.customers_group_id=$GROUP
                        WHERE pa.products_id=$PID ORDER BY pa.products_attributes_id");
        $n=0; while ($a=$r->fetch_assoc()) { $n++;
            printf("      %-8s %-20s %9.4f -> %9.4f (antes %s)\n", $a['paid'], mb_substr((string)$a['v'],0,20),
                   (float)$a['g0'], round((float)$a['g0']*$FACTOR,4), $a['g1']===null?'INSERT':number_format($a['g1'],4,'.',''));
            if ($n>=60){ echo "      ... (".($r->num_rows-$n)." mas)\n"; break; }
        }
    }
}

if ($baseChg==0 && $attrChg==0 && $baseMiss==0 && $attrMiss==0) { echo "\nNada que cambiar (ya esta en retail x $FACTOR).\n"; }

if (!$APPLY) { echo "\n== DRY-RUN: nada escrito ==\n"; $m->close(); exit(0); }

/* ---------- BACKUP ---------- */
if ($BACKUP) {
    $dir = '/home/francobordo/public_html/_admin/scripts/_backups';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $stamp = date('Ymd_His');
    $tag   = $pids ? ('pids_'.implode('-',array_slice($pids,0,5)).(count($pids)>5?'_etc':'')) : ('all'.($LIMIT?"_lim$LIMIT":''));
    $bf = "$dir/g{$GROUP}_reprice_{$tag}_{$stamp}.sql";
    $fh = fopen($bf, 'w');
    fwrite($fh, "-- backup grupo $GROUP antes de reprice x$FACTOR  ($stamp)\n-- restaurar:  mysql DB < ESTE_FICHERO\n");
    foreach ([
        'products_groups'           => "SELECT * FROM products_groups pg WHERE pg.customers_group_id=$GROUP $basePidFilter",
        'products_attributes_groups' => "SELECT * FROM products_attributes_groups pag WHERE pag.customers_group_id=$GROUP $attrPidFilter",
    ] as $tbl=>$sql) {
        $r = $m->query($sql); $cols=null;
        while ($row = $r->fetch_assoc()) {
            if ($cols===null) $cols = array_keys($row);
            $vals = array_map(fn($v)=> $v===null?'NULL':"'".$m->real_escape_string($v)."'", array_values($row));
            fwrite($fh, "REPLACE INTO `$tbl` (`".implode('`,`',$cols)."`) VALUES (".implode(',',$vals).");\n");
        }
    }
    fclose($fh);
    echo "\nBackup: $bf (".number_format(filesize($bf))." bytes)\n";
    echo "NOTA: el backup restaura filas EXISTENTES; en upsert, las filas nuevas insertadas no se borran al restaurar.\n";
}

/* ---------- APLICAR (set-based) ---------- */
$m->begin_transaction();
try {
    $nBaseUpd = 0; $nAttrUpd = 0; $nBaseIns = 0; $nAttrIns = 0;

    $m->query("UPDATE products_groups pg JOIN products p ON p.products_id=pg.products_id
               SET pg.customers_group_price = ROUND(p.products_price*$FACTOR,4)
               WHERE pg.customers_group_id=$GROUP $basePidFilter") or throw new Exception($m->error);
    $nBaseUpd = $m->affected_rows;

    $m->query("UPDATE products_attributes_groups pag JOIN products_attributes pa ON pa.products_attributes_id=pag.products_attributes_id
               SET pag.options_values_price = ROUND(pa.options_values_price*$FACTOR,4), pag.price_prefix = pa.price_prefix
               WHERE pag.customers_group_id=$GROUP $attrPidFilter") or throw new Exception($m->error);
    $nAttrUpd = $m->affected_rows;

    if ($MODE === 'upsert' && $idScope !== null) {
        $idin = implode(',', array_map('intval',$idScope));
        $m->query("INSERT INTO products_groups (customers_group_id, customers_group_price, products_id, products_qty_blocks, products_min_order_qty)
                   SELECT $GROUP, ROUND(p.products_price*$FACTOR,4), p.products_id, 1, 1
                   FROM products p LEFT JOIN products_groups pg ON pg.products_id=p.products_id AND pg.customers_group_id=$GROUP
                   WHERE p.products_id IN ($idin) AND pg.products_id IS NULL") or throw new Exception($m->error);
        $nBaseIns = $m->affected_rows;
        $m->query("INSERT INTO products_attributes_groups (products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id, options_values_weight, weight_prefix)
                   SELECT pa.products_attributes_id, $GROUP, ROUND(pa.options_values_price*$FACTOR,4), pa.price_prefix, pa.products_id, 0.000, ''
                   FROM products_attributes pa LEFT JOIN products_attributes_groups pag ON pag.products_attributes_id=pa.products_attributes_id AND pag.customers_group_id=$GROUP
                   WHERE pa.products_id IN ($idin) AND pag.products_attributes_id IS NULL") or throw new Exception($m->error);
        $nAttrIns = $m->affected_rows;
    }

    $m->commit();
    echo "\n== APLICADO ==  base: update=$nBaseUpd".($nBaseIns?" insert=$nBaseIns":"")
       . "  | atributos: update=$nAttrUpd".($nAttrIns?" insert=$nAttrIns":"")."\n";
} catch (Throwable $e) {
    $m->rollback();
    echo "\n== ERROR, ROLLBACK: ".$e->getMessage()." ==\n"; exit(4);
}
$m->close();
