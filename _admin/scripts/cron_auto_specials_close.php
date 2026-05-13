<?php
/**
 * cron_auto_specials_close.php — Fase 5 auto_specials
 *
 * Cron horario. Cierra/revierte ofertas auto cuando:
 *   - producto agotado: stock_variant = 0 → closed_by_stock
 *   - producto no activo: products_status != 1 → closed_by_status
 *   - oferta de variante con fecha_fin vencida → closed_by_expiry
 *   - oferta de variante próxima a vencer (<24h) con stock → renovar fecha_fin
 *
 * Reversión:
 *   - Sin variantes (cgid=0/1): UPDATE specials SET status=0, expires_repeat=0
 *   - Variantes cgid=0: restaurar products_attributes.options_values_price y price_prefix
 *   - Variantes cgid=1: restaurar products_attributes_groups (pa_id, cgid=1)
 *
 * NOTA: productos sin variantes no se cierran por expiración porque
 * application_top.php los autorenueva mientras expires_repeat=1.
 *
 * Uso:
 *   php cron_auto_specials_close.php DRY   # reporta sin tocar
 *   php cron_auto_specials_close.php       # ejecuta (default)
 */

$confPath = '/home/francobordo/public_html/includes/configure.php';
$conf = file_get_contents($confPath);
preg_match("/'DB_SERVER',\s*'([^']+)'/", $conf, $m); $DB_HOST = $m[1] ?? 'localhost';
preg_match("/'DB_SERVER_USERNAME',\s*'([^']+)'/", $conf, $m); $DB_USER = $m[1];
preg_match("/'DB_SERVER_PASSWORD',\s*'([^']+)'/", $conf, $m); $DB_PASS = $m[1];
preg_match("/'DB_DATABASE',\s*'([^']+)'/", $conf, $m); $DB_NAME = $m[1];

$dryRun = false;
$verbose = false;
foreach (array_slice($argv, 1) as $a) {
    if (strtoupper($a) === 'DRY') $dryRun = true;
    if (strtoupper($a) === 'V') $verbose = true;
}

function lm($s) { echo '[' . date('Y-m-d H:i:s') . '] ' . $s . "\n"; }
function esc($v) { global $mysqli; return $mysqli->real_escape_string($v); }

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_error) { lm('FATAL conn: ' . $mysqli->connect_error); exit(1); }
$mysqli->set_charset('utf8mb4');

lm($dryRun ? '== DRY-RUN ==' : '== EJECUCION ==');

// 1) Cargar todas las activas con datos derivados
$sql = "
SELECT a.id, a.products_id, a.options_values_id, a.customers_group_id,
       a.specials_id,
       a.pvp_original, a.pvp_oferta, a.pct_aplicado,
       a.fecha_inicio, a.fecha_fin,
       a.ovp_orig, a.prefix_orig,
       a.prev_specials_price, a.prev_specials_existed,
       a.rule_id,
       p.products_status,
       COALESCE(v.stock_variant, 0) AS cur_stock,
       (SELECT pa.products_attributes_id FROM products_attributes pa
        WHERE pa.products_id=a.products_id AND pa.options_values_id=a.options_values_id
        LIMIT 1) AS pa_id,
       r.vigencia_dias AS rule_vig
FROM auto_specials_active a
JOIN products p ON p.products_id = a.products_id
LEFT JOIN qfac_sales_velocity v
       ON v.products_id = a.products_id AND v.options_values_id = a.options_values_id
LEFT JOIN auto_specials_tier_rules r ON r.id = a.rule_id
WHERE a.estado = 'active'
";
$rs = $mysqli->query($sql);
if (!$rs) { lm('FATAL select: ' . $mysqli->error); exit(1); }

$active = [];
while ($r = $rs->fetch_assoc()) $active[] = $r;
lm('Ofertas activas en seguimiento: ' . count($active));

$cnt_close_stock = 0; $cnt_close_status = 0; $cnt_close_expiry = 0; $cnt_renew = 0; $cnt_keep = 0;
$now_ts = time();
$soon_ts = $now_ts + 24*3600;
$now_sql = date('Y-m-d H:i:s', $now_ts);

// Backup pre-cambios (mínimo) solo si hay cambios y no dry-run
$pending_changes = [];

foreach ($active as $r) {
    $id    = (int)$r['id'];
    $pid   = (int)$r['products_id'];
    $ovid  = (int)$r['options_values_id'];
    $cgid  = (int)$r['customers_group_id'];
    $sid   = (int)($r['specials_id'] ?? 0);
    $stat  = (int)$r['products_status'];
    $stock = (int)$r['cur_stock'];
    $finTs = strtotime($r['fecha_fin']);

    // Decidir acción
    if ($stat !== 1) {
        $pending_changes[] = ['type' => 'close_status', 'row' => $r];
        $cnt_close_status++;
    } elseif ($stock <= 0) {
        $pending_changes[] = ['type' => 'close_stock', 'row' => $r];
        $cnt_close_stock++;
    } elseif ($ovid > 0 && $finTs < $now_ts) {
        $pending_changes[] = ['type' => 'close_expiry', 'row' => $r];
        $cnt_close_expiry++;
    } elseif ($ovid > 0 && $finTs < $soon_ts) {
        $pending_changes[] = ['type' => 'renew', 'row' => $r];
        $cnt_renew++;
    } else {
        $cnt_keep++;
        if ($verbose) lm("  keep id=$id pid=$pid ovid=$ovid cgid=$cgid stock=$stock fin={$r['fecha_fin']}");
    }
}

lm("Plan: close_stock=$cnt_close_stock close_status=$cnt_close_status close_expiry=$cnt_close_expiry renew=$cnt_renew keep=$cnt_keep");

if (empty($pending_changes)) { lm('Nada que hacer.'); exit(0); }

// Backup defensivo de las filas a tocar
if (!$dryRun) {
    $bkDir = '/home/francobordo/backups';
    if (!is_dir($bkDir)) @mkdir($bkDir, 0755, true);
    $bk = $bkDir . '/auto_specials_close_' . date('YmdHis') . '.sql';
    $fh = fopen($bk, 'w');
    fwrite($fh, "-- Backup pre-cierre auto_specials " . date('c') . "\n");
    foreach ($pending_changes as $ch) {
        $r = $ch['row'];
        if ((int)$r['options_values_id'] === 0) {
            $sid = (int)$r['specials_id'];
            if ($sid > 0) {
                $bq = $mysqli->query("SELECT * FROM specials WHERE specials_id=$sid");
                if ($bq && ($brow = $bq->fetch_assoc())) {
                    $cols = implode(',', array_map(fn($k) => "`$k`", array_keys($brow)));
                    $vals = implode(',', array_map(fn($v) => $v === null ? 'NULL' : "'" . $mysqli->real_escape_string($v) . "'", $brow));
                    fwrite($fh, "INSERT INTO specials ($cols) VALUES ($vals) ON DUPLICATE KEY UPDATE specials_id=specials_id;\n");
                }
            }
        } else {
            $pa_id = (int)$r['pa_id'];
            if ($pa_id > 0) {
                if ((int)$r['customers_group_id'] === 0) {
                    $bq = $mysqli->query("SELECT * FROM products_attributes WHERE products_attributes_id=$pa_id");
                    if ($bq && ($brow = $bq->fetch_assoc())) {
                        fwrite($fh, "UPDATE products_attributes SET options_values_price='{$brow['options_values_price']}', price_prefix='{$brow['price_prefix']}' WHERE products_attributes_id=$pa_id;\n");
                    }
                } else {
                    $cgid = (int)$r['customers_group_id'];
                    $bq = $mysqli->query("SELECT options_values_price, price_prefix FROM products_attributes_groups WHERE products_attributes_id=$pa_id AND customers_group_id=$cgid");
                    if ($bq && ($brow = $bq->fetch_assoc())) {
                        fwrite($fh, "UPDATE products_attributes_groups SET options_values_price='{$brow['options_values_price']}', price_prefix='{$brow['price_prefix']}' WHERE products_attributes_id=$pa_id AND customers_group_id=$cgid;\n");
                    }
                }
            }
        }
    }
    fclose($fh);
    lm("Backup: $bk (" . filesize($bk) . " bytes)");
}

// Ejecutar
$n_ok = 0; $n_fail = 0;
foreach ($pending_changes as $ch) {
    $r = $ch['row'];
    $type = $ch['type'];
    $id = (int)$r['id'];
    $pid = (int)$r['products_id'];
    $ovid = (int)$r['options_values_id'];
    $cgid = (int)$r['customers_group_id'];

    if ($type === 'renew') {
        // Renovar: extender fecha_fin con la vigencia de la regla (o 14 si no hay)
        $vig = (int)($r['rule_vig'] ?? 14);
        if ($vig <= 0) $vig = 14;
        $new_fin = date('Y-m-d H:i:s', strtotime("+{$vig} days"));
        if ($dryRun) {
            lm("  RENEW id=$id pid=$pid ovid=$ovid cgid=$cgid hasta {$new_fin}");
        } else {
            if (!$mysqli->query("UPDATE auto_specials_active SET fecha_fin='{$new_fin}', updated_at='{$now_sql}' WHERE id={$id}")) {
                lm("  RENEW FAIL id=$id: " . $mysqli->error); $n_fail++; continue;
            }
            $n_ok++;
        }
        continue;
    }

    // Cierre: revertir y marcar
    $reason = ['close_stock' => 'closed_by_stock',
               'close_status' => 'closed_by_status',
               'close_expiry' => 'closed_by_expiry'][$type];

    if ($ovid === 0) {
        // ---- Producto sin variantes: desactivar specials ----
        $sid = (int)$r['specials_id'];
        if ($sid > 0) {
            $msg = "  CLOSE($reason) sin-variante id=$id pid=$pid cgid=$cgid sid=$sid → specials status=0, expires_repeat=0";
            if ($dryRun) { lm($msg); } else {
                lm($msg);
                if (!$mysqli->query("UPDATE specials SET status=0, expires_repeat=0, expires_date='{$now_sql}', specials_last_modified='{$now_sql}' WHERE specials_id={$sid}")) {
                    lm("    FAIL specials: " . $mysqli->error); $n_fail++; continue;
                }
            }
        } else {
            lm("  WARN sin specials_id para id=$id (no se puede desactivar specials)");
        }
    } else {
        // ---- Variante: restaurar delta ----
        $pa_id = (int)$r['pa_id'];
        $ovp_orig = $r['ovp_orig'];
        $pfx_orig = $r['prefix_orig'];
        if ($pa_id <= 0 || $ovp_orig === null || $pfx_orig === null) {
            lm("  WARN variant sin snapshot id=$id pid=$pid ovid=$ovid pa_id=$pa_id ovp_orig=" . ($ovp_orig ?? 'NULL'));
            // marcar cerrada igualmente
        } else {
            $ovp_s = sprintf('%.4f', (float)$ovp_orig);
            $pfx_s = $mysqli->real_escape_string($pfx_orig);
            if ($cgid === 0) {
                $msg = "  CLOSE($reason) variante id=$id pid=$pid ovid=$ovid cgid=0 → PA delta {$ovp_s}/{$pfx_s}";
                if ($dryRun) { lm($msg); } else {
                    lm($msg);
                    if (!$mysqli->query("UPDATE products_attributes SET options_values_price={$ovp_s}, price_prefix='{$pfx_s}', attributes_last_modified='{$now_sql}' WHERE products_attributes_id={$pa_id}")) {
                        lm("    FAIL PA: " . $mysqli->error); $n_fail++; continue;
                    }
                }
            } else {
                $msg = "  CLOSE($reason) variante id=$id pid=$pid ovid=$ovid cgid=$cgid → PAG delta {$ovp_s}/{$pfx_s}";
                if ($dryRun) { lm($msg); } else {
                    lm($msg);
                    if (!$mysqli->query("UPDATE products_attributes_groups SET options_values_price={$ovp_s}, price_prefix='{$pfx_s}' WHERE products_attributes_id={$pa_id} AND customers_group_id={$cgid}")) {
                        lm("    FAIL PAG: " . $mysqli->error); $n_fail++; continue;
                    }
                }
            }
        }
    }

    // Marcar cerrada en active
    if (!$dryRun) {
        if (!$mysqli->query("UPDATE auto_specials_active SET estado='{$reason}', updated_at='{$now_sql}' WHERE id={$id}")) {
            lm("    FAIL update active: " . $mysqli->error); $n_fail++; continue;
        }
        $n_ok++;
    }
}

if ($dryRun) {
    lm('DRY-RUN: no se ha tocado nada.');
} else {
    lm("OK={$n_ok} FAIL={$n_fail}");
}
lm('Fin.');
